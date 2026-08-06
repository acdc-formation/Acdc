<?php
/**
 * ACDC Signature — Core
 *
 * Bootstrap, constantes, tables BDD, install/upgrade,
 * réglages, utilitaires partagés, génération de token,
 * journal d'audit, création de demande (méthode factoriée).
 *
 * @package ACDC_Formation_SAAS
 * @since   3.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ACDC_Sig_Core {

    /* -----------------------------------------------------------------------
     * Constantes
     * -------------------------------------------------------------------- */

    const VERSION         = '2.0.0';
    const OPTION_DB_VER   = 'acdc_sig_db_version';
    const OPTION_PAGE     = 'acdc_sig_page_id';
    const OPTION_SETTINGS = 'acdc_sig_settings';

    const DOC_TYPES = array(
        'emargement'       => "Feuille d'émargement",
        'convention'       => 'Convention de formation',
        'contrat'          => 'Contrat individuel de formation',
        'convocation'      => 'Convocation / Accusé de réception',
        'devis'            => 'Devis',
        'contrat_formateur'=> 'Contrat formateur',
    );

    const LEVEL_SIMPLE   = 'simple';
    const LEVEL_RENFORCE = 'renforce';

    const DOC_DEFAULT_LEVEL = array(
        'emargement'       => 'simple',
        'convention'       => 'renforce',
        'contrat'          => 'renforce',
        'convocation'      => 'simple',
        'devis'            => 'renforce',
        'contrat_formateur'=> 'renforce',
    );

    const TOKEN_TTL_HOURS = 72;

    /** Taille maximale autorisée pour sig_data (canvas PNG base64) — 600 Ko */
    const MAX_SIG_DATA_BYTES = 614400;

    /* -----------------------------------------------------------------------
     * Propriétés
     * -------------------------------------------------------------------- */

    public $table_requests;
    public $table_audit;

    /* -----------------------------------------------------------------------
     * Install / upgrade
     * -------------------------------------------------------------------- */

    public function install() {
        $this->create_or_update_tables();
        $this->ensure_signature_page();
        update_option( self::OPTION_DB_VER, self::VERSION );
    }

    public function maybe_upgrade() {
        if ( get_option( self::OPTION_DB_VER, '' ) !== self::VERSION ) {
            $this->create_or_update_tables();
            $this->ensure_signature_page();
            update_option( self::OPTION_DB_VER, self::VERSION );
        }
    }

    private function create_or_update_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        $sql_requests = "CREATE TABLE {$this->table_requests} (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            token         VARCHAR(64) NOT NULL,
            doc_type      VARCHAR(30) NOT NULL DEFAULT 'emargement',
            sig_level     VARCHAR(20) NOT NULL DEFAULT 'simple',
            signer_name   VARCHAR(190) NOT NULL DEFAULT '',
            signer_email  VARCHAR(190) NOT NULL DEFAULT '',
            signer_role   VARCHAR(100) NOT NULL DEFAULT '',
            session_id    BIGINT UNSIGNED DEFAULT NULL,
            learner_id    BIGINT UNSIGNED DEFAULT NULL,
            doc_url       TEXT,
            doc_path      TEXT,
            signed_doc_url  TEXT,
            signed_doc_path TEXT,
            audit_pdf_url   TEXT,
            audit_pdf_path  TEXT,
            doc_sha256        CHAR(64) NOT NULL DEFAULT '',
            signed_pdf_sha256 CHAR(64) NOT NULL DEFAULT '',
            status        VARCHAR(30) NOT NULL DEFAULT 'en_attente',
            expires_at    DATETIME DEFAULT NULL,
            signed_at     DATETIME DEFAULT NULL,
            signer_ip     VARCHAR(45) DEFAULT NULL,
            signer_ua     TEXT,
            notes         LONGTEXT,
            created_by    BIGINT UNSIGNED DEFAULT NULL,
            created_at    DATETIME NOT NULL,
            updated_at    DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY doc_type (doc_type),
            KEY status (status),
            KEY session_id (session_id),
            KEY learner_id (learner_id),
            KEY signer_email (signer_email)
        ) {$charset};";

        $sql_audit = "CREATE TABLE {$this->table_audit} (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            request_id   BIGINT UNSIGNED NOT NULL,
            event        VARCHAR(50) NOT NULL,
            details      LONGTEXT,
            ip           VARCHAR(45) DEFAULT NULL,
            user_agent   TEXT,
            created_at   DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY request_id (request_id),
            KEY event (event)
        ) {$charset};";

        dbDelta( $sql_requests );
        dbDelta( $sql_audit );
    }

    /* -----------------------------------------------------------------------
     * Page publique de signature
     * -------------------------------------------------------------------- */

    public function ensure_signature_page() {
        $page_id = (int) get_option( self::OPTION_PAGE, 0 );
        if ( $page_id && get_post( $page_id ) ) {
            return;
        }
        $new_id = wp_insert_post( array(
            'post_title'   => 'Signature électronique',
            'post_name'    => 'signature-electronique',
            'post_content' => '[acdc_signature_page]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ) );
        if ( $new_id && ! is_wp_error( $new_id ) ) {
            update_option( self::OPTION_PAGE, $new_id );
        }
    }

    public function get_signature_page_url() {
        $page_id = (int) get_option( self::OPTION_PAGE, 0 );
        return $page_id ? get_permalink( $page_id ) : home_url( '/signature-electronique/' );
    }

    /* -----------------------------------------------------------------------
     * Méthode factoriée : créer une demande de signature
     * Correction audit : date() → gmdate()
     * -------------------------------------------------------------------- */

    public function create_signature_request( array $args ) {
        global $wpdb;

        $defaults = array(
            'signer_name'  => '',
            'signer_email' => '',
            'signer_role'  => '',
            'doc_type'     => 'emargement',
            'sig_level'    => 'simple',
            'session_id'   => null,
            'learner_id'   => null,
            'doc_url'      => '',
            'doc_path'     => '',
            'notes'        => '',
            'ttl_hours'    => self::TOKEN_TTL_HOURS,
        );
        $a = wp_parse_args( $args, $defaults );

        if ( empty( $a['signer_name'] ) || empty( $a['signer_email'] ) ) {
            return 0;
        }

        // Correction audit : gmdate() au lieu de date()
        $now     = current_time( 'mysql' );
        $expires = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + (int) $a['ttl_hours'] * 3600 );
        $token   = $this->generate_token();

        $data = array(
            'token'        => $token,
            'doc_type'     => $a['doc_type'],
            'sig_level'    => $a['sig_level'],
            'signer_name'  => $a['signer_name'],
            'signer_email' => $a['signer_email'],
            'signer_role'  => $a['signer_role'],
            'session_id'   => $a['session_id'] ?: null,
            'learner_id'   => $a['learner_id'] ?: null,
            'doc_url'      => $a['doc_url'],
            'doc_path'     => $a['doc_path'],
            'notes'        => $a['notes'],
            'status'       => 'envoye',
            'expires_at'   => $expires,
            'created_by'   => get_current_user_id(),
            'created_at'   => $now,
            'updated_at'   => $now,
        );

        $wpdb->insert( $this->table_requests, $data );
        $request_id = (int) $wpdb->insert_id;

        if ( $request_id ) {
            $this->log_event( $request_id, 'created', 'Demande créée.' );
        }

        return $request_id;
    }

    /* -----------------------------------------------------------------------
     * Journal d'audit
     * -------------------------------------------------------------------- */

    public function log_event( $request_id, $event, $details = '', $ip = '', $ua = '' ) {
        global $wpdb;
        $wpdb->insert( $this->table_audit, array(
            'request_id' => (int) $request_id,
            'event'      => sanitize_key( $event ),
            'details'    => (string) $details,
            'ip'         => $ip ?: sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
            'user_agent' => $ua,
            'created_at' => gmdate( 'Y-m-d H:i:s' ), // UTC — conversion à l'affichage
        ) );
    }

    /* -----------------------------------------------------------------------
     * Rate limiting — correction audit CRITIQUE
     * Limite : max 5 tentatives par IP + token sur 10 minutes
     * -------------------------------------------------------------------- */

    public function check_rate_limit( $token, $ip ) {
        $key     = 'acdc_sig_rl_' . md5( $token . '_' . $ip );
        $count   = (int) get_transient( $key );
        if ( $count >= 5 ) {
            return false;
        }
        set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
        return true;
    }

    public function clear_rate_limit( $token, $ip ) {
        delete_transient( 'acdc_sig_rl_' . md5( $token . '_' . $ip ) );
    }

    /* -----------------------------------------------------------------------
     * OTP e-mail — double authentification niveau renforcé
     * Code 6 chiffres, TTL 15 min. Stockage via transient WordPress.
     * -------------------------------------------------------------------- */

    /**
     * Génère un code OTP à 6 chiffres (avec zéros de remplissage).
     */
    public function generate_otp() {
        return str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
    }

    /*
     * ACDC — Stockage OTP via OPTION persistante (et non transient).
     * Motif : sous cache objet externe (Redis/Memcached), un transient ne vit QUE dans le
     * cache et n'a aucun repli en base : s'il est évincé, get_transient() renvoie false et
     * le code est déclaré « expiré » immédiatement, même émis à l'instant. Une option
     * (autoload=no) est toujours persistée en base : elle survit à l'éviction du cache.
     * L'expiration (15 min) est gérée explicitement via un timestamp UTC (time()),
     * indépendant du fuseau du site.
     */

    const OTP_TTL_SECONDS    = 900;  // 15 minutes
    const OTP_OK_TTL_SECONDS = 3600; // 60 minutes

    /**
     * Stocke le code OTP hashé (option persistante) pour 15 minutes.
     */
    public function store_otp( $request_id, $otp ) {
        update_option(
            'acdc_sig_otp_' . (int) $request_id,
            array(
                'hash'    => wp_hash( (string) $otp ),
                'expires' => time() + self::OTP_TTL_SECONDS,
            ),
            false
        );
    }

    /**
     * Vérifie le code OTP soumis.
     * Retourne 'ok', 'invalid' ou 'expired'.
     */
    public function verify_otp( $request_id, $otp ) {
        $stored = get_option( 'acdc_sig_otp_' . (int) $request_id );
        if ( ! is_array( $stored ) || empty( $stored['hash'] ) || empty( $stored['expires'] ) ) {
            /* ACDC 3.25.154 — On distingue « code CONSOMMÉ » (absent parce qu'il vient
               d'être validé — cas d'une soumission en double) de « code PÉRIMÉ » (TTL
               dépassé, ci-dessous). Les deux renvoyaient « expired », ce qui affichait
               une erreur trompeuse au signataire dont l'identité venait d'être vérifiée. */
            return 'consumed';
        }
        if ( time() > (int) $stored['expires'] ) {
            delete_option( 'acdc_sig_otp_' . (int) $request_id );
            return 'expired';
        }
        if ( ! hash_equals( (string) $stored['hash'], wp_hash( (string) $otp ) ) ) {
            return 'invalid';
        }
        delete_option( 'acdc_sig_otp_' . (int) $request_id );
        return 'ok';
    }

    /**
     * Marque l'OTP comme validé pour ce token (option persistante, 60 min).
     */
    public function mark_otp_verified( $token ) {
        update_option(
            'acdc_sig_otpok_' . md5( (string) $token ),
            time() + self::OTP_OK_TTL_SECONDS,
            false
        );
    }

    /**
     * Vérifie si l'OTP a déjà été validé pour ce token.
     */
    public function is_otp_verified( $token, $fresh = false ) {
        /* ACDC 3.25.154 — $fresh force une relecture hors cache d'objet. Sous un cache
           persistant (LiteSpeed, Redis…), deux requêtes quasi simultanées pouvaient lire
           une valeur périmée : la seconde ne « voyait » pas encore la vérification que
           la première venait d'écrire, et concluait à tort à un échec. */
        if ( $fresh ) {
            wp_cache_delete( 'acdc_sig_otpok_' . md5( (string) $token ), 'options' );
            wp_cache_delete( 'notoptions', 'options' );
        }
        $expires = (int) get_option( 'acdc_sig_otpok_' . md5( (string) $token ), 0 );
        if ( $expires <= 0 ) {
            return false;
        }
        if ( time() > $expires ) {
            delete_option( 'acdc_sig_otpok_' . md5( (string) $token ) );
            return false;
        }
        return true;
    }

    /**
     * Supprime le marqueur de validation OTP (appelé après signature réussie).
     */
    public function clear_otp_verified( $token ) {
        delete_option( 'acdc_sig_otpok_' . md5( (string) $token ) );
    }

    /* -----------------------------------------------------------------------
     * Utilitaires partagés
     * -------------------------------------------------------------------- */

    public function generate_token() {
        return bin2hex( random_bytes( 32 ) );
    }

    public function status_label( $status ) {
        $labels = array(
            'en_attente' => 'En attente',
            'envoye'     => 'Envoyé',
            'ouvert'     => 'Ouvert',
            'signe'      => 'Signé',
            'expire'     => 'Expiré',
            'refuse'     => 'Refusé',
            'supprime'   => 'Supprimé',
        );
        return $labels[ $status ] ?? $status;
    }

    public function get_settings() {
        $defaults = array(
            'from_name'      => get_bloginfo( 'name' ),
            'from_email'     => get_option( 'admin_email' ),
            'email_subject'  => 'Document à signer',
            'email_intro'    => 'Vous avez reçu une demande de signature électronique. Merci de signer le document en cliquant sur le bouton ci-dessous.',
            'btn_color'      => '#c9a84c',
            'btn_label'      => 'Signer le document',
            'legal_notice'   => 'En apposant ma signature, je confirme avoir pris connaissance du document et en accepte le contenu.',
            'admin_email'    => get_option( 'admin_email' ),
            'auto_relance'   => false,
            'relance_delai_h'=> 48,
        );
        return wp_parse_args( get_option( self::OPTION_SETTINGS, array() ), $defaults );
    }

    /**
     * Validation d'URL de document — correction audit MOYEN (iframe src).
     * Accepte uniquement les URLs du domaine WordPress ou du répertoire uploads.
     */
    public function is_safe_doc_url( $url ) {
        if ( empty( $url ) ) {
            return false;
        }
        $home     = home_url( '/' );
        $uploads  = wp_upload_dir();
        $base_url = $uploads['baseurl'];

        return ( 0 === strpos( $url, $home ) || 0 === strpos( $url, $base_url ) );
    }

    /**
     * Écriture sécurisée de fichier — correction audit MOYEN (file_put_contents).
     * Retourne true/false et logue l'erreur si échec.
     */
    public function safe_file_write( $path, $content, $context = '' ) {
        wp_mkdir_p( dirname( $path ) );
        $result = file_put_contents( $path, $content );
        if ( false === $result ) {
            error_log( '[ACDC Signature] Échec écriture fichier' . ( $context ? " ($context)" : '' ) . ' : ' . $path );
            return false;
        }
        return true;
    }

    /**
     * Initialise les propriétés de table (appelé par l'orchestrateur).
     */
    public function init_tables() {
        global $wpdb;
        $this->table_requests = $wpdb->prefix . 'acdc_sig_requests';
        $this->table_audit    = $wpdb->prefix . 'acdc_sig_audit';
    }
}
