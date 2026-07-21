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
        add_action( 'init', array( $this->core, 'install' ), 5 );

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
        $session_id = absint( $_POST['session_id'] ?? 0 );
        check_admin_referer( 'acdc_emarg_send_trainer_' . $session_id );

        global $wpdb;

        // Récupérer ou créer la session d'émargement
        $emarg = $this->core->get_by_session_id( $session_id );
        if ( ! $emarg ) {
            // Charger les données de la session
            $session_table  = $wpdb->prefix . 'acdc_of_sessions';
            $trainer_table  = $wpdb->prefix . 'acdc_of_trainers';
            $learner_table  = $wpdb->prefix . 'acdc_of_learners';
            $group_table    = $wpdb->prefix . 'acdc_of_groups';

            $session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$session_table} WHERE id = %d", $session_id ) );
            if ( ! $session ) {
                wp_safe_redirect( add_query_arg( 'emarg_err', 'session', wp_get_referer() ?: admin_url() ) );
                exit;
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

            // Apprenants
            $learners_raw = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, first_name, usage_last_name, last_name, email FROM {$learner_table} WHERE session_id = %d",
                $session_id
            ) );
            $learners = array();
            foreach ( $learners_raw as $lr ) {
                $name = trim( $lr->first_name . ' ' . ( ! empty( $lr->usage_last_name ) ? $lr->usage_last_name : $lr->last_name ) );
                $learners[] = array( 'id' => $lr->id, 'name' => $name, 'email' => $lr->email ?? '' );
            }
            // Apprenants via groupe
            $group = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$group_table} WHERE session_id = %d", $session_id ) );
            if ( $group && ! empty( $group->learner_ids ) ) {
                foreach ( explode( ',', $group->learner_ids ) as $lid ) {
                    $lid = absint( $lid );
                    if ( ! $lid ) { continue; }
                    // Éviter doublons
                    $already = array_filter( $learners, function( $l ) use ( $lid ) { return (int) $l['id'] === $lid; } );
                    if ( ! empty( $already ) ) { continue; }
                    $lr = $wpdb->get_row( $wpdb->prepare( "SELECT id, first_name, usage_last_name, last_name, email FROM {$learner_table} WHERE id = %d", $lid ) );
                    if ( $lr ) {
                        $name = trim( $lr->first_name . ' ' . ( ! empty( $lr->usage_last_name ) ? $lr->usage_last_name : $lr->last_name ) );
                        $learners[] = array( 'id' => $lr->id, 'name' => $name, 'email' => $lr->email ?? '' );
                    }
                }
            }

            $emarg_id = $this->core->create_emarg_session( $session_id, $trainer_id, $trainer_name, $trainer_email, $learners );
            if ( ! $emarg_id ) {
                wp_safe_redirect( add_query_arg( 'emarg_err', 'create', wp_get_referer() ?: admin_url() ) );
                exit;
            }
            $emarg = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->core->table_sessions} WHERE id = %d", $emarg_id ) );
        }

        if ( $emarg && $emarg->trainer_email ) {
            $this->email->send_trainer_email( $emarg );
        }

        wp_safe_redirect( add_query_arg( 'emarg_ok', 'trainer_sent', wp_get_referer() ?: admin_url() ) );
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

    public static function install() {
        self::get_instance()->core->install();
    }

    /* -----------------------------------------------------------------------
     * Handler : Télécharger la feuille d'émargement PDF
     * -------------------------------------------------------------------- */
    public function handle_download_pdf() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
        $session_id = isset( $_GET['session_id'] ) ? absint( $_GET['session_id'] ) : 0;
        check_admin_referer( 'acdc_emarg_pdf_' . $session_id );
        $this->pdf->serve_pdf( $session_id );
    }
}
