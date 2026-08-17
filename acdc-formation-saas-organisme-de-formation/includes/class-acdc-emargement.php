<?php
/**
 * ACDC Émargement Numérique — Bootstrap
 *
 * Charge les 3 sous-modules, instancie, enregistre les hooks.
 *
 * @package ACDC_Formation_SAAS
 * @since   3.21.09
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$emarg_dir = __DIR__ . '/emargement/';
$emarg_manifest = array(
    'class-acdc-emarg-core.php'   => 'ACDC_Emarg_Core',
    'class-acdc-emarg-email.php'  => 'ACDC_Emarg_Email',
    'class-acdc-emarg-public.php' => 'ACDC_Emarg_Public',
    'class-acdc-emarg-pdf.php'    => 'ACDC_Emarg_PDF',
);
foreach ( $emarg_manifest as $file => $class ) {
    $path = $emarg_dir . $file;
    if ( ! file_exists( $path ) ) { return; }
    require_once $path;
    if ( ! class_exists( $class ) ) { return; }
}

class ACDC_Emargement {

    /** Version du schéma d'émargement. À incrémenter à chaque changement de structure. */
    /* ACDC 3.25.314 — 3.25.223 → 3.25.314 POUR LA COLONNE « signature_token ».
       LE MÊME DÉFAUT QU'EN 3.25.311, AU MÊME POINT DU RAISONNEMENT. La colonne a
       été ajoutée au CREATE TABLE en 3.25.313 sans toucher à ce numéro. Or
       maybe_install() sort immédiatement quand le numéro stocké est identique :
       sur une installation existante, dbDelta n'aurait jamais tourné, la colonne
       n'aurait jamais été créée, et create_emarg_session() — qui l'écrit à chaque
       appel — aurait échoué. PLUS AUCUNE FEUILLE D'ÉMARGEMENT n'aurait pu être
       ouverte après la mise à jour, et l'écran l'aurait dit sans en donner la
       raison.
       Toute colonne ajoutée à ce module exige d'incrémenter ce numéro ET
       d'ajouter son maybe_add_column() : le premier déclenche la migration, le
       second la rend sûre même si dbDelta est capricieux. */
    const DB_VERSION   = '3.25.314';
    const OPTION_DB_VER = 'acdc_emarg_db_version';

    private static $instance = null;

    /** @var ACDC_Emarg_Core */
    public $core;
    /** @var ACDC_Emarg_Email */
    public $email;
    /** @var ACDC_Emarg_Public */
    public $pub;
    /** @var ACDC_Emarg_PDF */
    public $pdf;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->core  = new ACDC_Emarg_Core();
        $this->email = new ACDC_Emarg_Email( $this->core );
        $this->pub   = new ACDC_Emarg_Public( $this->core, $this->email );
        $this->pdf   = new ACDC_Emarg_PDF( $this->core );
        $this->register_hooks();
    }

    private function register_hooks() {
        // Garde de version : install() (dbDelta + ALTER) ne s'exécute que lorsque
        // le schéma stocké diffère de la version courante, et non à chaque requête.
        add_action( 'init', array( $this, 'maybe_install' ), 5 );

        /* ACDC 3.25.230 — La réparation des feuilles vides vit hors du chemin
           de requête publique : elle ne s'exécute que dans l'administration,
           par lots de dix. */
        add_action( 'admin_init', array( $this->core, 'maybe_repair_empty_sheets' ) );

        // Exclusion cache LiteSpeed au plus tôt (avant template_redirect)
        // pour éviter qu'une page avec nonce soit servie depuis le cache
        add_action( 'init', array( $this->pub, 'maybe_set_nocache' ), 1 );

        // Pages publiques (intercepte ?acdc_emarg=)
        add_action( 'template_redirect', array( $this->pub, 'maybe_handle' ), 1 );

        // Handlers POST — signatures publiques gérées via maybe_handle() (template_redirect)
        // pour éviter le blocage LiteSpeed WAF sur /wp-admin/admin-post.php depuis mobile
        add_action( 'admin_post_acdc_emarg_send_learner_email',      array( $this->pub, 'handle_send_learner_email' ) );
        add_action( 'admin_post_acdc_emarg_mark_absent',             array( $this, 'handle_mark_absent' ) );
        add_action( 'admin_post_acdc_emarg_send_trainer',            array( $this, 'handle_send_trainer' ) );
        add_action( 'admin_post_acdc_emarg_download_pdf',            array( $this, 'handle_download_pdf' ) );
        add_action( 'admin_post_acdc_emarg_download_cert_trainer',   array( $this, 'handle_download_cert_trainer' ) );
        add_action( 'admin_post_acdc_emarg_download_cert_learner',   array( $this, 'handle_download_cert_learner' ) );
    }

    /* -----------------------------------------------------------------------
     * Handler : Marquer absent
     * -------------------------------------------------------------------- */
    public function handle_mark_absent() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die(); }
        $learner_row_id = absint( $_POST['learner_row_id'] ?? 0 );
        check_admin_referer( 'acdc_emarg_absent_' . $learner_row_id );
        $this->core->mark_absent( $learner_row_id );
        wp_safe_redirect( wp_get_referer() ?: home_url('/') );
        exit;
    }

    /* -----------------------------------------------------------------------
     * Handler : Envoyer email formateur (déclencher séance)
     * -------------------------------------------------------------------- */
    public function handle_send_trainer() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die(); }
        $session_id   = absint( $_POST['session_id'] ?? 0 );
        // Émargement par séance : index optionnel (défaut 0 = séance primaire/legacy).
        $seance_index = isset( $_POST['seance_index'] ) ? max( 0, (int) $_POST['seance_index'] ) : 0;

        /* ACDC 3.25.223 — LE BOUTON « ENVOYER » DE LA PREMIÈRE SÉANCE NE POUVAIT
           PAS FONCTIONNER. Deux conventions de jeton cohabitent : les écrans
           mono-séance émettent « ..._<session> », l'affichage multi-créneaux
           émet « ..._<session>_<index> » — y compris pour l'index 0. Le
           gestionnaire, lui, n'acceptait le jeton suffixé qu'à partir de
           l'index 1 : sur une formation de deux jours découpés en quatre
           demi-journées, le bouton du premier créneau butait sur une
           vérification de jeton, et rien ne partait.
           On accepte donc les deux graphies pour l'index 0, plutôt que de
           réécrire les formulaires d'un côté ou de l'autre : un seul point de
           lecture, et aucun écran existant ne casse. */
        $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
        $valid = (bool) wp_verify_nonce( $nonce, 'acdc_emarg_send_trainer_' . $session_id . '_' . $seance_index );
        if ( ! $valid && 0 === $seance_index ) {
            $valid = (bool) wp_verify_nonce( $nonce, 'acdc_emarg_send_trainer_' . $session_id );
        }
        if ( ! $valid ) {
            wp_nonce_ays( 'acdc_emarg_send_trainer_' . $session_id );
        }

        global $wpdb;

        // Récupérer ou créer la feuille d'émargement de la séance ciblée.
        // Pour l'index 0 sans paramètre explicite, on garde l'appel legacy (priorité index 0).
        $emarg = $seance_index > 0
            ? $this->core->get_by_session_id( $session_id, $seance_index )
            : $this->core->get_by_session_id( $session_id );
        if ( ! $emarg ) {
            // Charger les données de la session
            $session_table  = $wpdb->prefix . 'acdc_of_sessions';
            $trainer_table  = $wpdb->prefix . 'acdc_of_trainers';

            $session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$session_table} WHERE id = %d", $session_id ) );
            if ( ! $session ) {
                $this->redirect_with_notice( 'Séance introuvable : rien n’a été envoyé.', 'error', array( 'emarg_err' => 'session' ) );
            }

            // Formateur
            $trainer_name  = '';
            $trainer_email = '';
            $trainer_id    = 0;
            if ( $session->trainer_id ) {
                $trainer = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$trainer_table} WHERE id = %d", $session->trainer_id ) );
                if ( $trainer ) {
                    $trainer_id    = (int) $trainer->id;
                    $trainer_name  = trim( ( $trainer->first_name ?? '' ) . ' ' . ( $trainer->last_name ?? '' ) );
                    $trainer_email = $trainer->email ?? '';
                }
            }

            /* ACDC 3.25.223 — Une seule lecture de « qui vient à cette séance ».
               Cet écran avait la sienne : rattachement direct, plus UN groupe,
               et rien d'autre. La carte de séance, elle, comptait aussi les
               conventions d'inscription — d'où une carte annonçant trois
               apprenants et une feuille d'émargement n'en portant aucun. Deux
               écrans, deux vérités : c'est la panne, pas son symptôme. */
            $learners = $this->core->resolve_session_learners( $session_id );

            // Métadonnées de la séance ciblée depuis schedule_json (si multi-créneaux).
            // Pour l'index 0 d'une session mono-créneau, $seance_meta reste vide →
            // create_emarg_session se comporte comme aujourd'hui.
            $seance_meta = array();
            if ( ! empty( $session->schedule_json ) ) {
                $slots = json_decode( $session->schedule_json, true );
                if ( is_array( $slots ) ) {
                    $slots = array_values( $slots );
                    if ( isset( $slots[ $seance_index ] ) ) {
                        $seance_meta = $this->core->slot_to_meta( $slots[ $seance_index ], $seance_index, max( 1, count( $slots ) ) );
                    }
                }
            }

            $emarg_id = $this->core->create_emarg_session( $session_id, $trainer_id, $trainer_name, $trainer_email, $learners, $seance_index, $seance_meta );
            if ( ! $emarg_id ) {
                $this->redirect_with_notice( 'La feuille d’émargement n’a pas pu être créée : rien n’a été envoyé.', 'error', array( 'emarg_err' => 'create' ) );
            }
            $emarg = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->core->table_sessions} WHERE id = %d", $emarg_id ) );
        }

        if ( $emarg && ! empty( $emarg->trainer_email ) ) {
            $this->email->send_trainer_email( $emarg );
            $this->redirect_with_notice(
                'La feuille d’émargement a été envoyée à ' . $emarg->trainer_email . '.',
                'success',
                array( 'emarg_ok' => 'trainer_sent' )
            );
        }

        /* Aucun formateur joignable : le clic a bien été reçu, et rien n'est
           parti. Le taire aurait donné exactement la même page qu'un envoi
           réussi. */
        $this->redirect_with_notice(
            'Aucune adresse de formateur sur cette séance : rien n’a été envoyé.',
            'error',
            array( 'emarg_err' => 'no_trainer' )
        );
    }

    /**
     * ACDC 3.25.224 — Un envoi qui ne dit pas qu'il est parti n'a pas eu lieu.
     *
     * Le bouton d'émargement revenait avec « emarg_ok=trainer_sent » dans
     * l'URL et pas un mot à l'écran : l'e-mail partait vraiment, mais rien ne
     * distinguait le succès de l'échec, ni même du clic sans effet. L'écran
     * porte déjà un mécanisme de message — « notice » / « notice_type » — que
     * ce module n'utilisait pas. On s'y branche.
     */
    private function redirect_with_notice( $message, $type = 'success', $extra = array() ) {
        $base = wp_get_referer() ?: admin_url();
        $args = array_merge( (array) $extra, array(
            'notice'      => rawurlencode( (string) $message ),
            'notice_type' => in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ? $type : 'info',
            '_acdc_rt'    => time(),
        ) );

        nocache_headers();
        wp_safe_redirect( add_query_arg( $args, $base ) );
        exit;
    }

    public function handle_download_cert_trainer() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
        $id      = isset( $_GET['emarg_id'] ) ? absint( $_GET['emarg_id'] ) : 0;
        $preview = ! empty( $_GET['preview'] );
        check_admin_referer( 'acdc_emarg_cert_trainer_' . $id );
        $this->pdf->serve_signature_cert( 'trainer', $id, $preview );
    }

    public function handle_download_cert_learner() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
        $id      = isset( $_GET['emarg_id'] ) ? absint( $_GET['emarg_id'] ) : 0;
        $preview = ! empty( $_GET['preview'] );
        check_admin_referer( 'acdc_emarg_cert_learner_' . $id );
        $this->pdf->serve_signature_cert( 'learner', $id, $preview );
    }

    /**
     * Exécute install() uniquement si le schéma stocké n'est pas à jour.
     * Branché sur `init` : évite de relancer dbDelta/ALTER à chaque requête.
     */
    public function maybe_install() {
        if ( get_option( self::OPTION_DB_VER, '' ) === self::DB_VERSION ) {
            return;
        }

        /* ACDC 3.25.230 — LE NUMÉRO DE SCHÉMA S'ÉCRIT AVANT LE TRAVAIL.
           Il s'écrivait après : si install() n'allait pas au bout — dépassement
           du temps d'exécution, requête lente, verrou — le numéro n'était jamais
           écrit, et la requête SUIVANTE recommençait tout. Sur `init`, donc sur
           chaque page, y compris publiques : une boucle qui s'auto-entretient et
           finit par éteindre le site.
           C'est la règle déjà appliquée aux migrations du workflow, et elle vaut
           ici mot pour mot : perdre une migration est rattrapable à la main,
           la rejouer en boucle ne l'est pas. */
        update_option( self::OPTION_DB_VER, self::DB_VERSION );
        $this->core->install();
    }

    public static function install() {
        self::get_instance()->core->install();
        update_option( self::OPTION_DB_VER, self::DB_VERSION );
    }

    /* -----------------------------------------------------------------------
     * Handler : Télécharger la feuille d'émargement PDF
     * -------------------------------------------------------------------- */
    public function handle_download_pdf() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
        $session_id = isset( $_GET['session_id'] ) ? absint( $_GET['session_id'] ) : 0;
        /* ACDC 3.25.303 — sheet_id désigne la demi-journée. Le jeton reste posé
           sur la séance : c'est elle qui porte le droit, la demi-journée n'est
           qu'un choix de page. */
        $sheet_id   = isset( $_GET['sheet_id'] ) ? absint( $_GET['sheet_id'] ) : 0;
        check_admin_referer( 'acdc_emarg_pdf_' . $session_id );
        $this->pdf->serve_pdf( $session_id, $sheet_id );
    }
}
