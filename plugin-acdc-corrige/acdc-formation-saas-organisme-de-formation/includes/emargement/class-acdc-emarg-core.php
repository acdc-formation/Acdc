<?php
/**
 * ACDC Émargement Numérique — Core
 *
 * Tables BDD, tokens, CRUD.
 *
 * @package ACDC_Formation_SAAS
 * @since   3.21.09
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ACDC_Emarg_Core {

    const TOKEN_TTL_HOURS = 72;

    public $table_sessions; // acdc_of_emarg_sessions
    public $table_learners; // acdc_of_emarg_learners

    public function __construct() {
        global $wpdb;
        $this->table_sessions = $wpdb->prefix . 'acdc_of_emarg_sessions';
        $this->table_learners = $wpdb->prefix . 'acdc_of_emarg_learners';
    }

    /* -----------------------------------------------------------------------
     * Install / upgrade
     * -------------------------------------------------------------------- */
    public function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql_sessions = "CREATE TABLE {$this->table_sessions} (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          session_id BIGINT UNSIGNED NOT NULL,
          trainer_token VARCHAR(64) NOT NULL DEFAULT '',
          list_token VARCHAR(64) NOT NULL DEFAULT '',
          trainer_id BIGINT UNSIGNED DEFAULT NULL,
          trainer_name VARCHAR(190) DEFAULT '',
          trainer_email VARCHAR(190) DEFAULT '',
          trainer_status VARCHAR(30) DEFAULT 'pending',
          trainer_signed_at DATETIME DEFAULT NULL,
          trainer_sig_url TEXT,
          trainer_sig_path TEXT,
          status VARCHAR(30) DEFAULT 'pending',
          expires_at DATETIME DEFAULT NULL,
          created_at DATETIME NOT NULL,
          updated_at DATETIME NOT NULL,
          PRIMARY KEY (id),
          UNIQUE KEY trainer_token (trainer_token),
          UNIQUE KEY list_token (list_token),
          KEY session_id (session_id),
          KEY trainer_id (trainer_id),
          KEY status (status)
        ) {$charset};";

        $sql_learners = "CREATE TABLE {$this->table_learners} (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          emarg_session_id BIGINT UNSIGNED NOT NULL,
          session_id BIGINT UNSIGNED NOT NULL,
          learner_id BIGINT UNSIGNED DEFAULT NULL,
          learner_name VARCHAR(190) DEFAULT '',
          learner_email VARCHAR(190) DEFAULT '',
          sign_token VARCHAR(64) NOT NULL DEFAULT '',
          status VARCHAR(30) DEFAULT 'pending',
          signed_at DATETIME DEFAULT NULL,
          sig_url TEXT,
          sig_path TEXT,
          late_minutes INT DEFAULT 0,
          is_absent TINYINT(1) DEFAULT 0,
          ip VARCHAR(64) DEFAULT '',
          created_at DATETIME NOT NULL,
          updated_at DATETIME NOT NULL,
          PRIMARY KEY (id),
          UNIQUE KEY sign_token (sign_token),
          KEY emarg_session_id (emarg_session_id),
          KEY session_id (session_id),
          KEY learner_id (learner_id),
          KEY status (status)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_sessions );
        dbDelta( $sql_learners );

        // Colonnes ajoutées après la création initiale (rétrocompatibilité)
        global $wpdb;
        $wpdb->query( "ALTER TABLE {$this->table_sessions} ADD COLUMN IF NOT EXISTS trainer_ip VARCHAR(64) DEFAULT '' AFTER trainer_sig_path" );
        $wpdb->query( "ALTER TABLE {$this->table_sessions} ADD COLUMN IF NOT EXISTS trainer_ua TEXT AFTER trainer_ip" );
        $wpdb->query( "ALTER TABLE {$this->table_learners} ADD COLUMN IF NOT EXISTS learner_ua TEXT AFTER ip" );
    }

    /* -----------------------------------------------------------------------
     * Token
     * -------------------------------------------------------------------- */
    public function generate_token() {
        return bin2hex( random_bytes( 24 ) );
    }

    /* -----------------------------------------------------------------------
     * Créer une session d'émargement
     * -------------------------------------------------------------------- */
    public function create_emarg_session( $session_id, $trainer_id, $trainer_name, $trainer_email, $learners ) {
        global $wpdb;
        $session_id = absint( $session_id );
        if ( ! $session_id ) { return false; }

        // Vérifier si une session d'émargement existe déjà
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_sessions} WHERE session_id = %d LIMIT 1",
            $session_id
        ) );
        if ( $existing ) { return (int) $existing->id; }

        $now     = current_time( 'mysql' );
        $expires = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + self::TOKEN_TTL_HOURS * 3600 );

        $wpdb->insert( $this->table_sessions, array(
            'session_id'    => $session_id,
            'trainer_token' => $this->generate_token(),
            'list_token'    => $this->generate_token(),
            'trainer_id'    => $trainer_id ?: null,
            'trainer_name'  => $trainer_name,
            'trainer_email' => $trainer_email,
            'trainer_status'=> 'pending',
            'status'        => 'pending',
            'expires_at'    => $expires,
            'created_at'    => $now,
            'updated_at'    => $now,
        ) );
        $emarg_id = (int) $wpdb->insert_id;
        if ( ! $emarg_id ) { return false; }

        // Créer les lignes apprenants
        foreach ( $learners as $learner ) {
            $wpdb->insert( $this->table_learners, array(
                'emarg_session_id' => $emarg_id,
                'session_id'       => $session_id,
                'learner_id'       => ! empty( $learner['id'] ) ? absint( $learner['id'] ) : null,
                'learner_name'     => sanitize_text_field( $learner['name'] ),
                'learner_email'    => sanitize_email( $learner['email'] ?? '' ),
                'sign_token'       => $this->generate_token(),
                'status'           => 'pending',
                'created_at'       => $now,
                'updated_at'       => $now,
            ) );
        }

        return $emarg_id;
    }

    /* -----------------------------------------------------------------------
     * Getters
     * -------------------------------------------------------------------- */
    public function get_by_trainer_token( $token ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_sessions} WHERE trainer_token = %s AND ( expires_at IS NULL OR expires_at > %s ) LIMIT 1",
            sanitize_text_field( $token ),
            current_time( 'mysql' )
        ) );
    }

    public function get_by_list_token( $token ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_sessions} WHERE list_token = %s AND ( expires_at IS NULL OR expires_at > %s ) LIMIT 1",
            sanitize_text_field( $token ),
            current_time( 'mysql' )
        ) );
    }

    public function get_learner_by_sign_token( $token ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_learners} WHERE sign_token = %s AND ( expires_at IS NULL OR expires_at > %s ) LIMIT 1",
            sanitize_text_field( $token ),
            current_time( 'mysql' )
        ) );
    }

    public function get_by_session_id( $session_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_sessions} WHERE session_id = %d ORDER BY id DESC LIMIT 1",
            absint( $session_id )
        ) );
    }

    public function get_learners_for_emarg( $emarg_session_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table_learners} WHERE emarg_session_id = %d ORDER BY learner_name ASC",
            absint( $emarg_session_id )
        ) );
    }

    /* -----------------------------------------------------------------------
     * Sauvegarder la signature du formateur
     * -------------------------------------------------------------------- */
    public function save_trainer_signature( $emarg_session_id, $sig_data, $ip, $session_start_at ) {
        $emarg_session_id = absint( $emarg_session_id );
        $png_result = $this->save_signature_png( $sig_data, 'trainer', $emarg_session_id );
        if ( ! $png_result ) { return false; }

        global $wpdb;
        $now = current_time( 'mysql' );
        $wpdb->update( $this->table_sessions, array(
            'trainer_status'    => 'signe',
            'trainer_signed_at' => $now,
            'trainer_sig_url'   => $png_result['url'],
            'trainer_sig_path'  => $png_result['path'],
            'trainer_ip'        => sanitize_text_field( $ip ),
            'trainer_ua'        => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
            'status'            => 'trainer_signed',
            'updated_at'        => $now,
        ), array( 'id' => $emarg_session_id ) );

        return true;
    }

    /* -----------------------------------------------------------------------
     * Sauvegarder la signature d'un apprenant
     * -------------------------------------------------------------------- */
    public function save_learner_signature( $learner_row_id, $sig_data, $ip, $session_start_at ) {
        $learner_row_id = absint( $learner_row_id );
        $png_result = $this->save_signature_png( $sig_data, 'learner', $learner_row_id );
        if ( ! $png_result ) { return false; }

        $now = current_time( 'mysql' );
        $late_minutes = 0;
        if ( $session_start_at && ( $session_ts = strtotime( $session_start_at ) ) ) {
            $now_ts = current_time( 'timestamp' );
            if ( $now_ts > $session_ts + 900 ) { // > 15 min
                $late_minutes = (int) round( ( $now_ts - $session_ts ) / 60 );
            }
        } else {
            $late_minutes = 0;
        }

        global $wpdb;
        $wpdb->update( $this->table_learners, array(
            'status'       => 'signe',
            'signed_at'    => $now,
            'sig_url'      => $png_result['url'],
            'sig_path'     => $png_result['path'],
            'late_minutes' => $late_minutes,
            'ip'           => sanitize_text_field( $ip ),
            'learner_ua'   => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
            'updated_at'   => $now,
        ), array( 'id' => $learner_row_id ) );

        // Vérifier si tous les apprenants ont signé
        $emarg_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT emarg_session_id FROM {$this->table_learners} WHERE id = %d",
            $learner_row_id
        ) );
        if ( $emarg_row ) {
            $pending = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_learners} WHERE emarg_session_id = %d AND status = 'pending'",
                (int) $emarg_row->emarg_session_id
            ) );
            if ( 0 === (int) $pending ) {
                $wpdb->update( $this->table_sessions,
                    array( 'status' => 'completed', 'updated_at' => $now ),
                    array( 'id' => (int) $emarg_row->emarg_session_id )
                );
            }
        }

        return array( 'late_minutes' => $late_minutes );
    }

    /* -----------------------------------------------------------------------
     * Marquer absent
     * -------------------------------------------------------------------- */
    public function mark_absent( $learner_row_id ) {
        global $wpdb;
        $wpdb->update( $this->table_learners,
            array( 'status' => 'absent', 'is_absent' => 1, 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => absint( $learner_row_id ) )
        );
    }

    /* -----------------------------------------------------------------------
     * Sauvegarder PNG signature
     * -------------------------------------------------------------------- */
    private function save_signature_png( $sig_data, $type, $record_id ) {
        if ( empty( $sig_data ) || 0 !== strpos( $sig_data, 'data:image/' ) ) {
            return false;
        }
        $base64 = preg_replace( '/^data:image\/\w+;base64,/', '', $sig_data );
        $raw    = base64_decode( $base64, true );
        if ( false === $raw || strlen( $raw ) < 100 ) { return false; }

        $finfo = new finfo( FILEINFO_MIME_TYPE );
        $mime  = $finfo->buffer( $raw );
        if ( ! in_array( $mime, array( 'image/png', 'image/jpeg' ), true ) ) { return false; }

        $ext = ( 'image/jpeg' === $mime ) ? 'jpg' : 'png';
        $upload_dir = wp_upload_dir();
        $dir  = trailingslashit( $upload_dir['basedir'] ) . 'acdc-emargement/';
        wp_mkdir_p( $dir );

        $filename = $type . '-' . $record_id . '-' . time() . '.' . $ext;
        $path     = $dir . $filename;
        $url      = trailingslashit( $upload_dir['baseurl'] ) . 'acdc-emargement/' . $filename;

        if ( false === file_put_contents( $path, $raw ) ) { return false; }
        return array( 'path' => $path, 'url' => $url );
    }

    /* -----------------------------------------------------------------------
     * URL publique d'émargement
     * -------------------------------------------------------------------- */
    public function get_public_url( $mode, $token ) {
        return add_query_arg( array( 'acdc_emarg' => $mode, 'tok' => $token ), home_url( '/' ) );
    }

    /* -----------------------------------------------------------------------
     * Statut label
     * -------------------------------------------------------------------- */
    public function status_label( $status ) {
        $map = array(
            'pending'        => 'En attente',
            'signe'          => 'Signé',
            'absent'         => 'Absent',
            'trainer_signed' => 'Formateur signé',
            'completed'      => 'Complété',
        );
        return $map[ (string) $status ] ?? ucfirst( (string) $status );
    }
}
