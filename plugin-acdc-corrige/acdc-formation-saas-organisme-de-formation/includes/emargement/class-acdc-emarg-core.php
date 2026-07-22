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
          seance_index INT NOT NULL DEFAULT 0,
          seance_label VARCHAR(190) DEFAULT '',
          seance_start_at DATETIME DEFAULT NULL,
          seance_end_at DATETIME DEFAULT NULL,
          trainer_token VARCHAR(64) NOT NULL DEFAULT '',
          list_token VARCHAR(64) NOT NULL DEFAULT '',
          trainer_id BIGINT UNSIGNED DEFAULT NULL,
          trainer_name VARCHAR(190) DEFAULT '',
          trainer_email VARCHAR(190) DEFAULT '',
          trainer_status VARCHAR(30) DEFAULT 'pending',
          trainer_signed_at DATETIME DEFAULT NULL,
          trainer_sig_url TEXT,
          trainer_sig_path TEXT,
          trainer_ip VARCHAR(64) DEFAULT '',
          trainer_ua TEXT,
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
          seance_index INT NOT NULL DEFAULT 0,
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
          learner_ua TEXT,
          created_at DATETIME NOT NULL,
          updated_at DATETIME NOT NULL,
          PRIMARY KEY (id),
          UNIQUE KEY sign_token (sign_token),
          KEY emarg_session_id (emarg_session_id),
          KEY session_id (session_id),
          KEY learner_id (learner_id),
          KEY status (status),
          KEY is_absent (is_absent)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_sessions );
        dbDelta( $sql_learners );

        // Colonnes ajoutées après la création initiale (rétrocompatibilité).
        // NB : on n'utilise PAS « ADD COLUMN IF NOT EXISTS » (extension MariaDB
        // uniquement, rejetée par MySQL avec une erreur de syntaxe). On teste
        // d'abord l'existence de la colonne via SHOW COLUMNS, portable MySQL/MariaDB.
        $this->maybe_add_column( $this->table_sessions, 'trainer_ip', "VARCHAR(64) DEFAULT '' AFTER trainer_sig_path" );
        $this->maybe_add_column( $this->table_sessions, 'trainer_ua', 'TEXT AFTER trainer_ip' );
        $this->maybe_add_column( $this->table_learners, 'learner_ua', 'TEXT AFTER ip' );

        // ACDC — Émargement par séance (multi-créneaux). Colonnes additives, DEFAULT 0/NULL :
        // les feuilles existantes deviennent seance_index=0 → comportement mono-séance inchangé.
        $this->maybe_add_column( $this->table_sessions, 'seance_index', 'INT NOT NULL DEFAULT 0 AFTER session_id' );
        $this->maybe_add_column( $this->table_sessions, 'seance_label', "VARCHAR(190) DEFAULT '' AFTER seance_index" );
        $this->maybe_add_column( $this->table_sessions, 'seance_start_at', 'DATETIME NULL AFTER seance_label' );
        $this->maybe_add_column( $this->table_sessions, 'seance_end_at', 'DATETIME NULL AFTER seance_start_at' );
        $this->maybe_add_column( $this->table_learners, 'seance_index', 'INT NOT NULL DEFAULT 0 AFTER session_id' );
    }

    /**
     * Ajoute une colonne à une table si elle n'existe pas déjà.
     *
     * Équivalent portable de « ALTER TABLE ... ADD COLUMN IF NOT EXISTS »
     * (qui n'existe que sous MariaDB). Compatible MySQL et MariaDB.
     *
     * @param string $table      Nom complet de la table (avec préfixe).
     * @param string $column     Nom de la colonne.
     * @param string $definition Définition SQL de la colonne (type, défaut, position...).
     * @return bool True si la colonne a été ajoutée, false sinon.
     */
    private function maybe_add_column( $table, $column, $definition ) {
        global $wpdb;
        if ( empty( $table ) || empty( $column ) || empty( $definition ) ) {
            return false;
        }
        $exists = $wpdb->get_results( $wpdb->prepare(
            "SHOW COLUMNS FROM {$table} LIKE %s",
            $column
        ) );
        if ( ! empty( $exists ) ) {
            return false;
        }
        return false !== $wpdb->query( "ALTER TABLE {$table} ADD COLUMN {$column} {$definition}" );
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
    /**
     * Crée une feuille d'émargement pour une séance (créneau) d'une session.
     *
     * SIGNATURE ÉTENDUE, rétro-compatible : $seance_index et $seance_meta ont une
     * valeur par défaut. Appelée sans ces arguments, le comportement est STRICTEMENT
     * identique à l'origine (feuille unique de la session, seance_index = 0).
     *
     * La déduplication porte sur le couple (session_id, seance_index) : une session
     * mono-créneau ne peut donc avoir qu'une feuille d'index 0 — comme aujourd'hui.
     *
     * @param int    $session_id
     * @param int    $trainer_id
     * @param string $trainer_name
     * @param string $trainer_email
     * @param array  $learners
     * @param int    $seance_index Index du créneau (0 = première/unique séance).
     * @param array  $seance_meta  ['label' => string, 'start_at' => 'Y-m-d H:i:s', 'end_at' => 'Y-m-d H:i:s'].
     * @return int|false ID de la feuille d'émargement, ou false.
     */
    public function create_emarg_session( $session_id, $trainer_id, $trainer_name, $trainer_email, $learners, $seance_index = 0, $seance_meta = array() ) {
        global $wpdb;
        $session_id   = absint( $session_id );
        $seance_index = max( 0, (int) $seance_index );
        if ( ! $session_id ) { return false; }

        // Vérifier si une feuille d'émargement existe déjà pour CE créneau.
        // Dédup sur (session_id, seance_index) : identique au cas mono-séance pour l'index 0.
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_sessions} WHERE session_id = %d AND seance_index = %d LIMIT 1",
            $session_id,
            $seance_index
        ) );
        if ( $existing ) { return (int) $existing->id; }

        $now     = current_time( 'mysql' );
        $expires = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + self::TOKEN_TTL_HOURS * 3600 );

        $wpdb->insert( $this->table_sessions, array(
            'session_id'      => $session_id,
            'seance_index'    => $seance_index,
            'seance_label'    => isset( $seance_meta['label'] ) ? sanitize_text_field( $seance_meta['label'] ) : '',
            'seance_start_at' => ! empty( $seance_meta['start_at'] ) ? $seance_meta['start_at'] : null,
            'seance_end_at'   => ! empty( $seance_meta['end_at'] ) ? $seance_meta['end_at'] : null,
            'trainer_token'   => $this->generate_token(),
            'list_token'      => $this->generate_token(),
            'trainer_id'      => $trainer_id ?: null,
            'trainer_name'    => $trainer_name,
            'trainer_email'   => $trainer_email,
            'trainer_status'  => 'pending',
            'status'          => 'pending',
            'expires_at'      => $expires,
            'created_at'      => $now,
            'updated_at'      => $now,
        ) );
        $emarg_id = (int) $wpdb->insert_id;
        if ( ! $emarg_id ) { return false; }

        // Créer les lignes apprenants
        foreach ( $learners as $learner ) {
            $wpdb->insert( $this->table_learners, array(
                'emarg_session_id' => $emarg_id,
                'session_id'       => $session_id,
                'seance_index'     => $seance_index,
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

    /**
     * Crée (si besoin) une feuille d'émargement par créneau à partir de schedule_json.
     *
     * L'index 0 est créé EXACTEMENT comme aujourd'hui (create_emarg_session sans surcouche).
     * Idempotent : réutilise les feuilles déjà présentes (dédup sur session_id + index).
     *
     * @param int   $session_id
     * @param int   $trainer_id
     * @param string $trainer_name
     * @param string $trainer_email
     * @param array $learners
     * @param array $slots  Tableau décodé de schedule_json ([{start_at,end_at,start_date,end_date}, ...]).
     * @return int[] Liste des emarg_id créés/retrouvés, indexés par seance_index.
     */
    public function ensure_emarg_sheets_for_session( $session_id, $trainer_id, $trainer_name, $trainer_email, $learners, $slots = array() ) {
        $session_id = absint( $session_id );
        if ( ! $session_id ) { return array(); }

        $slots = is_array( $slots ) ? array_values( $slots ) : array();
        // Au moins une séance (index 0) — cas mono/legacy identique à aujourd'hui.
        $count = max( 1, count( $slots ) );

        $ids = array();
        for ( $i = 0; $i < $count; $i++ ) {
            $meta = isset( $slots[ $i ] ) ? $this->slot_to_meta( $slots[ $i ], $i, $count ) : array();
            $ids[ $i ] = $this->create_emarg_session( $session_id, $trainer_id, $trainer_name, $trainer_email, $learners, $i, $meta );
        }
        return $ids;
    }

    /**
     * Convertit un créneau schedule_json en métadonnées de séance (label + dates).
     *
     * @param array $slot
     * @param int   $index
     * @param int   $total
     * @return array
     */
    public function slot_to_meta( $slot, $index = 0, $total = 1 ) {
        $slot = (array) $slot;
        $start = '';
        if ( ! empty( $slot['start_at'] ) ) {
            $start = (string) $slot['start_at'];
        } elseif ( ! empty( $slot['start_date'] ) ) {
            $start = (string) $slot['start_date'] . ' 09:00:00';
        }
        $end = '';
        if ( ! empty( $slot['end_at'] ) ) {
            $end = (string) $slot['end_at'];
        } elseif ( ! empty( $slot['end_date'] ) ) {
            $end = (string) $slot['end_date'] . ' 17:00:00';
        }
        $label = sprintf( 'Séance %d/%d', (int) $index + 1, (int) $total );
        if ( $start ) {
            $label .= ' — ' . date_i18n( 'd/m/Y', strtotime( $start ) );
        }
        return array( 'label' => $label, 'start_at' => $start ?: null, 'end_at' => $end ?: null );
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
            "SELECT * FROM {$this->table_learners} WHERE sign_token = %s LIMIT 1",
            sanitize_text_field( $token )
        ) );
    }

    /**
     * Retourne UNE feuille d'émargement d'une session.
     *
     * Rétro-compat : sans $seance_index, retourne la feuille de la séance d'index le
     * plus bas (0 = feuille primaire/legacy). Pour une session mono-séance (une seule
     * feuille, seance_index = 0), le résultat est identique à l'ancien comportement.
     *
     * @param int      $session_id
     * @param int|null $seance_index Si fourni, cible précisément ce créneau.
     * @return object|null
     */
    public function get_by_session_id( $session_id, $seance_index = null ) {
        global $wpdb;
        if ( null !== $seance_index ) {
            return $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$this->table_sessions} WHERE session_id = %d AND seance_index = %d ORDER BY id DESC LIMIT 1",
                absint( $session_id ),
                max( 0, (int) $seance_index )
            ) );
        }
        // Priorité à la séance d'index le plus bas (feuille primaire) puis à l'id le
        // plus récent — pour une feuille unique, identique à « ORDER BY id DESC ».
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_sessions} WHERE session_id = %d ORDER BY seance_index ASC, id DESC LIMIT 1",
            absint( $session_id )
        ) );
    }

    /**
     * Retourne TOUTES les feuilles d'une session, ordonnées par séance.
     *
     * @param int $session_id
     * @return object[]
     */
    public function get_all_by_session_id( $session_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table_sessions} WHERE session_id = %d ORDER BY seance_index ASC, id ASC",
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

    /**
     * Anti N+1 : précharge UNE fiche représentative d'émargement par session.
     *
     * Rétro-compat stricte : retourne [session_id => fiche primaire], soit la feuille
     * de séance d'index le plus bas (0). Pour une session mono-séance (une seule feuille),
     * le résultat est identique à l'ancien comportement — les appelants existants
     * (kernel/sessions render, sessions core) restent inchangés.
     *
     * @param int[] $session_ids
     * @return array [session_id => fiche primaire (seance_index le plus bas)]
     */
    public function get_by_session_ids( $session_ids ) {
        global $wpdb;
        $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $session_ids ) ) ) );
        if ( empty( $ids ) ) {
            return array();
        }
        $ph   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        // Tri : seance_index DESC puis id ASC. En écrasant dans la boucle, la dernière
        // valeur retenue par session est celle de l'index le plus bas (feuille primaire).
        // Pour une feuille unique (mono-séance), le comportement est inchangé.
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table_sessions} WHERE session_id IN ($ph) ORDER BY seance_index DESC, id ASC",
            $ids
        ) );
        $map = array();
        foreach ( (array) $rows as $r ) {
            $map[ (int) $r->session_id ] = $r;
        }
        return $map;
    }

    /**
     * Anti N+1 : précharge TOUTES les feuilles d'émargement de plusieurs sessions.
     *
     * @param int[] $session_ids
     * @return array [session_id => [feuilles ordonnées par seance_index]]
     */
    public function get_all_by_session_ids( $session_ids ) {
        global $wpdb;
        $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $session_ids ) ) ) );
        if ( empty( $ids ) ) {
            return array();
        }
        $ph   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table_sessions} WHERE session_id IN ($ph) ORDER BY seance_index ASC, id ASC",
            $ids
        ) );
        $map = array();
        foreach ( (array) $rows as $r ) {
            $map[ (int) $r->session_id ][] = $r;
        }
        return $map;
    }

    /**
     * Anti N+1 : précharge les apprenants d'émargement de plusieurs fiches en une requête.
     *
     * @param int[] $emarg_session_ids
     * @return array [emarg_session_id => [apprenants...]]
     */
    public function get_learners_for_emarg_ids( $emarg_session_ids ) {
        global $wpdb;
        $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $emarg_session_ids ) ) ) );
        if ( empty( $ids ) ) {
            return array();
        }
        $ph   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table_learners} WHERE emarg_session_id IN ($ph) ORDER BY learner_name ASC",
            $ids
        ) );
        $map = array();
        foreach ( (array) $rows as $r ) {
            $map[ (int) $r->emarg_session_id ][] = $r;
        }
        return $map;
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
        // ACDC 3.25.111 — calcul du retard en base de temps HOMOGÈNE (UTC réel) : start_at
        // est stocké en heure locale WP ; get_gmt_from_date() le convertit en timestamp UTC,
        // comparé à time() (UTC). Évite le décalage d'offset GMT (retard fantôme ou masqué)
        // qui survenait en mélangeant strtotime() (fuseau serveur) et current_time('timestamp').
        $late_minutes = 0;
        if ( $session_start_at ) {
            $session_ts = (int) get_gmt_from_date( $session_start_at, 'U' );
            if ( $session_ts > 0 ) {
                $now_ts = time();
                if ( $now_ts > $session_ts + 900 ) { // > 15 min
                    $late_minutes = (int) round( ( $now_ts - $session_ts ) / 60 );
                }
            }
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
        $now = current_time( 'mysql' );
        $wpdb->update( $this->table_learners,
            array( 'status' => 'absent', 'is_absent' => 1, 'updated_at' => $now ),
            array( 'id' => absint( $learner_row_id ) )
        );

        // ACDC 3.25.115 — réévaluer la complétion après un marquage absent.
        $emarg_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT emarg_session_id FROM {$this->table_learners} WHERE id = %d",
            absint( $learner_row_id )
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

        // RGPD : nom de fichier non devinable (jeton aléatoire) pour empêcher l'énumération
        // des signatures manuscrites par URL (l'ancien format type-id-timestamp était prévisible).
        $rand     = function_exists( 'wp_generate_password' ) ? wp_generate_password( 20, false, false ) : bin2hex( random_bytes( 10 ) );
        $filename = $type . '-' . $record_id . '-' . time() . '-' . $rand . '.' . $ext;
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
