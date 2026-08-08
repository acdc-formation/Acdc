<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * ACDC Formation SAAS — Module Quizzes (Core trait)
 *
 * Couvre :
 *  - Constantes du module (finalités, modes, statuts, types de questions, vues).
 *  - Définition des 8 tables `acdc_of_qz_*` et création/MAJ via dbDelta().
 *  - Helpers de cartographie des labels et statuts.
 *  - Création idempotente des pages publiques `acdc-quiz-public` et
 *    `acdc-quiz-live`.
 *  - Initialisation des événements cron du module.
 *
 * IMPORTANT — Préfixe distinct du legacy :
 *  Le plugin 3.20.x dispose d'embryons de tables `acdc_of_quizzes`,
 *  `acdc_of_positioning_tests` et `acdc_of_evaluations`. Ces tables sont
 *  conservées intactes et continueront à fonctionner avec le code existant.
 *  Le nouveau moteur (3.21+) utilise un préfixe distinct `acdc_of_qz_*`
 *  pour éviter tout conflit. Une migration officielle pourra être proposée
 *  plus tard si besoin.
 *
 * @package ACDC_OF_SAAS
 * @since   3.21.00
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Quizzes_Core_Trait {

    /* -------------------------------------------------------------------- */
    /*  Propriétés du module                                                */
    /* -------------------------------------------------------------------- */

    /** @var string Version SQL courante du module Quizzes (utilisée pour dbDelta).
     *  1.1.0 — ACDC 3.25.168 : colonne score_ratio sur player_answers (barème partiel).
     *  1.2.0 — ACDC 3.25.174 : reprise des parts de réussite manquantes. */
    private $qz_db_version = '1.2.0';

    /** @var string Option WP qui stocke la version SQL appliquée. */
    private $qz_db_version_option = 'acdc_of_qz_db_version';

    /** @var array Référentiel des tables du module, peuplé au constructeur. */
    private $qz_tables = array();

    /* -------------------------------------------------------------------- */
    /*  Constantes — Finalités et modes                                     */
    /* -------------------------------------------------------------------- */

    const ACDC_OF_QZ_PURPOSE_LIVE        = 'live';
    const ACDC_OF_QZ_PURPOSE_POSITIONING = 'positioning';
    const ACDC_OF_QZ_PURPOSE_ASSESSMENT  = 'assessment';
    /* ACDC 3.25.165 — Quatrième finalité : l'évaluation DIAGNOSTIQUE, passée en début
       de formation pour situer le niveau de départ. Comparée à l'évaluation des
       acquis, elle mesure la progression — c'est sa raison d'être. À ne pas confondre
       avec le test de positionnement, qui vérifie des PRÉREQUIS avant l'entrée en
       formation et conditionne l'inscription ou une remise à niveau. */
    const ACDC_OF_QZ_PURPOSE_DIAGNOSTIC  = 'diagnostic';

    const ACDC_OF_QZ_DELIVERY_LIVE_SYNC   = 'live_sync';
    const ACDC_OF_QZ_DELIVERY_ASYNC_TOKEN = 'async_token';

    /* -------------------------------------------------------------------- */
    /*  Constantes — Statuts                                                */
    /* -------------------------------------------------------------------- */

    const ACDC_OF_QZ_STATUS_DRAFT    = 'draft';
    const ACDC_OF_QZ_STATUS_ACTIVE   = 'active';
    const ACDC_OF_QZ_STATUS_ARCHIVED = 'archived';
    const ACDC_OF_QZ_STATUS_LOCKED   = 'locked';

    const ACDC_OF_QZ_SESSION_DRAFT     = 'draft';
    const ACDC_OF_QZ_SESSION_LOBBY     = 'lobby';
    const ACDC_OF_QZ_SESSION_ACTIVE    = 'active';
    const ACDC_OF_QZ_SESSION_PAUSED    = 'paused';
    const ACDC_OF_QZ_SESSION_ENDED     = 'ended';
    const ACDC_OF_QZ_SESSION_CANCELLED = 'cancelled';

    const ACDC_OF_QZ_DISPATCH_DRAFT     = 'draft';
    const ACDC_OF_QZ_DISPATCH_PLANNED   = 'planned';
    const ACDC_OF_QZ_DISPATCH_SENT      = 'sent';
    const ACDC_OF_QZ_DISPATCH_PARTIAL   = 'partial';
    const ACDC_OF_QZ_DISPATCH_COMPLETED = 'completed';
    const ACDC_OF_QZ_DISPATCH_EXPIRED   = 'expired';
    const ACDC_OF_QZ_DISPATCH_CANCELLED = 'cancelled';

    const ACDC_OF_QZ_PARTICIPANT_PENDING   = 'pending';
    const ACDC_OF_QZ_PARTICIPANT_INVITED   = 'invited';
    const ACDC_OF_QZ_PARTICIPANT_OPENED    = 'opened';
    const ACDC_OF_QZ_PARTICIPANT_STARTED   = 'started';
    const ACDC_OF_QZ_PARTICIPANT_PARTIAL   = 'partial';
    const ACDC_OF_QZ_PARTICIPANT_COMPLETED = 'completed';
    const ACDC_OF_QZ_PARTICIPANT_EXPIRED   = 'expired';
    const ACDC_OF_QZ_PARTICIPANT_CANCELLED = 'cancelled';

    /* -------------------------------------------------------------------- */
    /*  Constantes — Types de questions                                     */
    /* -------------------------------------------------------------------- */

    const ACDC_OF_QZ_QTYPE_QCM_SINGLE   = 'qcm_single';
    const ACDC_OF_QZ_QTYPE_QCM_MULTIPLE = 'qcm_multiple';
    const ACDC_OF_QZ_QTYPE_TRUE_FALSE   = 'true_false';
    const ACDC_OF_QZ_QTYPE_PUZZLE       = 'puzzle';
    const ACDC_OF_QZ_QTYPE_OPEN_TEXT    = 'open_text';
    const ACDC_OF_QZ_QTYPE_POLL         = 'poll';

    /* -------------------------------------------------------------------- */
    /*  Constantes — Vues admin                                             */
    /* -------------------------------------------------------------------- */

    const ACDC_OF_QZ_VIEW_LIST    = 'list';
    const ACDC_OF_QZ_VIEW_EDIT    = 'edit';
    const ACDC_OF_QZ_VIEW_PREVIEW = 'preview';
    const ACDC_OF_QZ_VIEW_LAUNCH  = 'launch';
    const ACDC_OF_QZ_VIEW_RESULTS = 'results';
    const ACDC_OF_QZ_VIEW_HISTORY = 'history';

    /* -------------------------------------------------------------------- */
    /*  Initialisation — Référentiel des tables                             */
    /* -------------------------------------------------------------------- */

    /**
     * Initialise le tableau $this->qz_tables avec les noms complets
     * (préfixe inclus) des tables du module.
     *
     * Doit être appelée tôt (dans le constructeur du plugin).
     *
     * @return void
     */
    public function init_quizzes_module_tables() {
        global $wpdb;
        $p = $wpdb->prefix . 'acdc_of_qz_';

        $this->qz_tables = array(
            'quizzes'        => $p . 'quizzes',
            'questions'      => $p . 'questions',
            'answers'        => $p . 'answers',
            'objectives'     => $p . 'objectives',
            'sessions'       => $p . 'sessions',
            'participants'   => $p . 'participants',
            'player_answers' => $p . 'player_answers',
            'logs'           => $p . 'logs',
        );
    }

    /**
     * Renvoie le nom complet (avec préfixe WP) d'une table du module.
     *
     * @param string $key Clé logique parmi : quizzes, questions, answers,
     *                    objectives, sessions, participants, player_answers, logs.
     *
     * @return string Nom de la table, ou chaîne vide si clé inconnue.
     */
    public function get_qz_table( $key ) {
        if ( empty( $this->qz_tables ) ) {
            $this->init_quizzes_module_tables();
        }
        return isset( $this->qz_tables[ $key ] ) ? $this->qz_tables[ $key ] : '';
    }

    /* -------------------------------------------------------------------- */
    /*  Migration BDD — dbDelta                                             */
    /* -------------------------------------------------------------------- */

    /**
     * Applique le schéma BDD du module si nécessaire (création ou mise à jour).
     *
     * - Vérifie l'option `acdc_of_qz_db_version` ; si elle correspond déjà à la
     *   version courante, ne fait rien.
     * - Sinon, exécute dbDelta() sur les 8 tables et stocke la nouvelle version.
     *
     * Sûr à appeler à chaque chargement du plugin (idempotent).
     *
     * @return void
     */
    /**
     * ACDC hotfix52 — Migration rétroactive PDF + inscription.
     *
     * Tourne une seule fois au premier chargement après le déploiement.
     * Traite tous les participants "completed" de finalité positioning / assessment
     * qui n'ont pas encore de result_document_url (passations antérieures au hotfix51).
     *
     * Pour chacun :
     *   1. Génère le PDF de résultats.
     *   2. Propage l'URL vers acdc_of_training_registrations
     *      (positioning_result_document_url ou evaluation_result_document_url).
     *
     * La méthode est idem-potente : elle ne re-génère pas les PDFs déjà présents.
     */
    public function maybe_run_qz_retroactive_pdf_migration() {
        $option_key = 'acdc_of_qz_retro_pdf_hotfix53_done'; // hotfix53 : relance avec fallback URL
        if ( get_option( $option_key ) ) {
            return;
        }

        // Marquer immédiatement pour éviter tout double exécution en cas de requête parallèle.
        update_option( $option_key, '1', false );

        if ( empty( $this->qz_tables ) ) {
            return; // module non initialisé
        }

        global $wpdb;
        $tbl_p = $this->get_qz_table( 'participants' );
        $tbl_s = $this->get_qz_table( 'sessions' );
        $tbl_q = $this->get_qz_table( 'quizzes' );

        // Vérifier que la colonne result_document_url existe (créée à la volée par generate_qz_result_pdf)
        $col_exists = ! empty( $wpdb->get_col( "SHOW COLUMNS FROM {$tbl_p} LIKE 'result_document_url'" ) );
        $where_url  = $col_exists
            ? "AND ( p.result_document_url IS NULL OR p.result_document_url = '' )"
            : ''; // colonne absente = tous sans PDF

        $rows = $wpdb->get_results(
            "SELECT p.id AS participant_id, p.session_id
             FROM {$tbl_p} p
             INNER JOIN {$tbl_s} s ON s.id = p.session_id
             INNER JOIN {$tbl_q} q ON q.id = s.quiz_id
             WHERE p.status = 'completed'
               AND q.quiz_purpose IN ('positioning', 'diagnostic', 'assessment')
               {$where_url}
             ORDER BY p.id ASC
             LIMIT 500"
        );

        if ( empty( $rows ) ) {
            return;
        }

        foreach ( (array) $rows as $row ) {
            $pdf = $this->generate_qz_result_pdf_for_participant(
                (int) $row->participant_id,
                (int) $row->session_id
            );
            // Fallback : si PDF échoue, propager un lien vers les résultats en ligne
            if ( ! $pdf || empty( $pdf['url'] ) ) {
                $p_row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl_p} WHERE id=%d LIMIT 1", (int) $row->participant_id ) );
                $s_row   = $this->get_qz_dispatch_session( (int) $row->session_id );
                if ( $p_row && $s_row ) {
                    /* ACDC 3.25.167 — Le repli à deux branches renvoyait toute
                       évaluation diagnostique vers l'onglet des acquis. */
                    $qz_tab = $this->qz_results_tab_for_purpose( $s_row->quiz_purpose ?? '' );
                    $fallback_url = $this->portal_page_url( array(
                        'tab'         => $qz_tab,
                        'view'        => 'results',
                        'participant' => (int) $row->participant_id,
                    ) );
                    $this->qz_propagate_result_url_to_registration( $p_row, $s_row, $fallback_url );
                }
            }
        }
    }

        public function maybe_run_qz_db_upgrade() {
        $installed = get_option( $this->qz_db_version_option, '' );
        if ( $installed === $this->qz_db_version ) {
            return;
        }

        global $wpdb;
        if ( empty( $this->qz_tables ) ) {
            $this->init_quizzes_module_tables();
        }

        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $statements = array();

        // 1 — Quizzes
        $tbl = $this->qz_tables['quizzes'];
        $statements[] = "CREATE TABLE {$tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            formation_id BIGINT UNSIGNED NOT NULL,
            quiz_purpose VARCHAR(20) NOT NULL,
            delivery_mode VARCHAR(20) NOT NULL DEFAULT 'live_sync',
            title VARCHAR(255) NOT NULL,
            description LONGTEXT NULL,
            cover_image_id BIGINT UNSIGNED NULL,
            language VARCHAR(10) NOT NULL DEFAULT 'fr',
            version_label VARCHAR(50) NULL,
            version_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            is_current TINYINT(1) NOT NULL DEFAULT 0,
            replaces_quiz_id BIGINT UNSIGNED NULL,
            replaced_by_quiz_id BIGINT UNSIGNED NULL,
            activated_at DATETIME NULL,
            archived_at DATETIME NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            is_locked TINYINT(1) NOT NULL DEFAULT 0,
            locked_at DATETIME NULL,
            locked_reason VARCHAR(255) NULL,
            scoring_mode VARCHAR(20) NOT NULL DEFAULT 'percentage',
            pass_threshold DECIMAL(5,2) NULL,
            level_thresholds_json LONGTEXT NULL,
            failure_action VARCHAR(30) NOT NULL DEFAULT 'none',
            max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 1,
            antifraud_time_limit TINYINT(1) NOT NULL DEFAULT 0,
            antifraud_no_back TINYINT(1) NOT NULL DEFAULT 0,
            antifraud_visibility_check TINYINT(1) NOT NULL DEFAULT 0,
            antifraud_fullscreen TINYINT(1) NOT NULL DEFAULT 0,
            data_retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 1095,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY formation_id (formation_id),
            KEY quiz_purpose (quiz_purpose),
            KEY delivery_mode (delivery_mode),
            KEY is_current (is_current),
            KEY status (status),
            KEY formation_purpose_current (formation_id, quiz_purpose, is_current)
        ) {$charset_collate};";

        // 2 — Questions
        $tbl = $this->qz_tables['questions'];
        $statements[] = "CREATE TABLE {$tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(30) NOT NULL DEFAULT 'qcm_single',
            title TEXT NOT NULL,
            description LONGTEXT NULL,
            media_id BIGINT UNSIGNED NULL,
            time_limit SMALLINT UNSIGNED NOT NULL DEFAULT 20,
            points_type VARCHAR(20) NOT NULL DEFAULT 'standard',
            points_value SMALLINT UNSIGNED NOT NULL DEFAULT 1000,
            is_scored TINYINT(1) NOT NULL DEFAULT 1,
            objective_id BIGINT UNSIGNED NULL,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY quiz_id (quiz_id),
            KEY type (type),
            KEY objective_id (objective_id),
            KEY quiz_sort (quiz_id, sort_order)
        ) {$charset_collate};";

        // 3 — Answers
        $tbl = $this->qz_tables['answers'];
        $statements[] = "CREATE TABLE {$tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id BIGINT UNSIGNED NOT NULL,
            text VARCHAR(500) NOT NULL,
            media_id BIGINT UNSIGNED NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            feedback_text LONGTEXT NULL,
            sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY question_id (question_id),
            KEY question_sort (question_id, sort_order)
        ) {$charset_collate};";

        // 4 — Objectives
        $tbl = $this->qz_tables['objectives'];
        $statements[] = "CREATE TABLE {$tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id BIGINT UNSIGNED NOT NULL,
            label VARCHAR(255) NOT NULL,
            description LONGTEXT NULL,
            pass_threshold DECIMAL(5,2) NULL,
            sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY quiz_id (quiz_id)
        ) {$charset_collate};";

        // 5 — Sessions
        $tbl = $this->qz_tables['sessions'];
        $statements[] = "CREATE TABLE {$tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id BIGINT UNSIGNED NOT NULL,
            formation_id BIGINT UNSIGNED NOT NULL,
            formation_session_id BIGINT UNSIGNED NULL,
            host_id BIGINT UNSIGNED NULL,
            delivery_mode VARCHAR(20) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'draft',
            pin_code VARCHAR(10) NULL,
            current_question_id BIGINT UNSIGNED NULL,
            current_question_started_at DATETIME NULL,
            lobby_opened_at DATETIME NULL,
            scheduled_at DATETIME NULL,
            sent_at DATETIME NULL,
            expires_at DATETIME NULL,
            reminder_days_json LONGTEXT NULL,
            positioning_skip_reason LONGTEXT NULL,
            started_at DATETIME NULL,
            ended_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY pin_code (pin_code),
            KEY quiz_id (quiz_id),
            KEY formation_id (formation_id),
            KEY formation_session_id (formation_session_id),
            KEY host_id (host_id),
            KEY status (status),
            KEY scheduled_at (scheduled_at)
        ) {$charset_collate};";

        // 6 — Participants
        $tbl = $this->qz_tables['participants'];
        $statements[] = "CREATE TABLE {$tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id BIGINT UNSIGNED NOT NULL,
            learner_id BIGINT UNSIGNED NULL,
            registration_id BIGINT UNSIGNED NULL,
            qualiopi_traceable TINYINT(1) NOT NULL DEFAULT 0,
            nickname VARCHAR(80) NOT NULL,
            email VARCHAR(255) NULL,
            full_name VARCHAR(255) NULL,
            avatar VARCHAR(255) NULL,
            is_anonymized TINYINT(1) NOT NULL DEFAULT 0,
            anonymized_at DATETIME NULL,
            secure_token VARCHAR(255) NULL,
            token_expires_at DATETIME NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            attempt_number TINYINT UNSIGNED NOT NULL DEFAULT 1,
            invited_at DATETIME NULL,
            opened_at DATETIME NULL,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            reminder_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
            last_reminder_at DATETIME NULL,
            total_score DECIMAL(10,2) NULL,
            total_score_percentage DECIMAL(5,2) NULL,
            detected_level VARCHAR(30) NULL,
            is_passed TINYINT(1) NULL,
            objectives_score_json LONGTEXT NULL,
            antifraud_flags_json LONGTEXT NULL,
            joined_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY secure_token (secure_token),
            KEY session_id (session_id),
            KEY learner_id (learner_id),
            KEY registration_id (registration_id),
            KEY qualiopi_traceable (qualiopi_traceable),
            KEY status (status),
            KEY session_status (session_id, status)
        ) {$charset_collate};";

        // 7 — Player answers
        $tbl = $this->qz_tables['player_answers'];
        $statements[] = "CREATE TABLE {$tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id BIGINT UNSIGNED NOT NULL,
            participant_id BIGINT UNSIGNED NOT NULL,
            question_id BIGINT UNSIGNED NOT NULL,
            answer_id BIGINT UNSIGNED NULL,
            answer_ids_json LONGTEXT NULL,
            answer_text LONGTEXT NULL,
            answer_value FLOAT NULL,
            is_correct TINYINT(1) NULL,
            score_ratio DECIMAL(6,5) NULL,
            score_earned DECIMAL(10,2) NOT NULL DEFAULT 0,
            response_time DECIMAL(8,3) NULL,
            answered_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY participant_question (participant_id, question_id),
            KEY session_id (session_id),
            KEY participant_id (participant_id),
            KEY question_id (question_id)
        ) {$charset_collate};";

        // 8 — Logs (append-only pour audit Qualiopi)
        $tbl = $this->qz_tables['logs'];
        $statements[] = "CREATE TABLE {$tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id BIGINT UNSIGNED NULL,
            session_id BIGINT UNSIGNED NULL,
            participant_id BIGINT UNSIGNED NULL,
            event_type VARCHAR(50) NOT NULL,
            event_label VARCHAR(255) NOT NULL,
            event_payload LONGTEXT NULL,
            user_id BIGINT UNSIGNED NULL,
            ip_address VARCHAR(45) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY quiz_id (quiz_id),
            KEY session_id (session_id),
            KEY participant_id (participant_id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) {$charset_collate};";

        foreach ( $statements as $sql ) {
            dbDelta( $sql );
        }

        /* ACDC 3.25.174 — Reprise des parts de réussite. Toutes les réponses
           enregistrées avant l'arrivée du barème au prorata portent une part vide : les
           écrans d'analyse — « Par question », « Par objectif », « Questions les plus
           faibles » — retombent alors sur l'ancien tout-ou-rien et contredisent le score
           du participant, qui, lui, a bien été calculé au prorata. On recalcule ces
           parts à partir des réponses réellement cochées, une fois pour toutes. Sans
           cela, il faudrait rejouer chaque passation pour obtenir une analyse juste. */
        $this->qz_backfill_missing_score_ratios();

        update_option( $this->qz_db_version_option, $this->qz_db_version );
    }

    /* -------------------------------------------------------------------- */
    /*  Pages publiques (idempotentes)                                      */
    /* -------------------------------------------------------------------- */

    /**
     * Garantit l'existence de la page publique servant les quiz async
     * (`/acdc-quiz-public/?token=...`). La page utilise le shortcode
     * `[acdc_qz_async_public]`.
     *
     * @return int|false ID de la page, ou false si création impossible.
     */
    public function ensure_quiz_async_public_page() {
        $option_name = 'acdc_of_qz_async_public_page_id';
        $page_id     = (int) get_option( $option_name, 0 );

        if ( $page_id && get_post( $page_id ) ) {
            return $page_id;
        }

        $existing = get_page_by_path( 'acdc-quiz-public' );
        if ( $existing ) {
            update_option( $option_name, $existing->ID );
            return $existing->ID;
        }

        $new_id = wp_insert_post(
            array(
                'post_title'   => 'Réponse à un quiz',
                'post_name'    => 'acdc-quiz-public',
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_content' => '[acdc_qz_async_public]',
            )
        );

        if ( $new_id && ! is_wp_error( $new_id ) ) {
            update_option( $option_name, (int) $new_id );
            return (int) $new_id;
        }

        return false;
    }

    /**
     * Garantit l'existence de la page publique servant le mode live
     * (`/acdc-quiz-live/`). La page utilise le shortcode
     * `[acdc_qz_live_player]`.
     *
     * @return int|false ID de la page, ou false si création impossible.
     */
    public function ensure_quiz_live_public_page() {
        $option_name = 'acdc_of_qz_live_public_page_id';
        $page_id     = (int) get_option( $option_name, 0 );

        if ( $page_id && get_post( $page_id ) ) {
            return $page_id;
        }

        $existing = get_page_by_path( 'acdc-quiz-live' );
        if ( $existing ) {
            update_option( $option_name, $existing->ID );
            return $existing->ID;
        }

        $new_id = wp_insert_post(
            array(
                'post_title'   => 'Quiz en direct',
                'post_name'    => 'acdc-quiz-live',
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_content' => '[acdc_qz_live_player]',
            )
        );

        if ( $new_id && ! is_wp_error( $new_id ) ) {
            update_option( $option_name, (int) $new_id );
            return (int) $new_id;
        }

        return false;
    }

    /**
     * Garantit l'existence de la page de pilotage formateur (`/acdc-quiz-live-host/`).
     * Utilise le shortcode `[acdc_qz_live_host]`. Pleine page sans header de thème.
     * 3.21.04.2-a — Page host live.
     *
     * @return int|false ID de la page, ou false si création impossible.
     */
    public function ensure_quiz_live_host_page() {
        $option_name = 'acdc_of_qz_live_host_page_id';
        $page_id     = (int) get_option( $option_name, 0 );

        if ( $page_id && get_post( $page_id ) ) {
            return $page_id;
        }

        $existing = get_page_by_path( 'acdc-quiz-live-host' );
        if ( $existing ) {
            update_option( $option_name, $existing->ID );
            return $existing->ID;
        }

        $new_id = wp_insert_post(
            array(
                'post_title'   => 'Pilotage quiz en direct',
                'post_name'    => 'acdc-quiz-live-host',
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_content' => '[acdc_qz_live_host]',
            )
        );

        if ( $new_id && ! is_wp_error( $new_id ) ) {
            update_option( $option_name, (int) $new_id );
            return (int) $new_id;
        }

        return false;
    }

    /* -------------------------------------------------------------------- */
    /*  Cron events                                                         */
    /* -------------------------------------------------------------------- */

    /**
     * Programme les événements cron du module s'ils ne sont pas déjà planifiés.
     * Idempotent — sûr à appeler à chaque init.
     *
     * @return void
     */
    public function ensure_quiz_cron_events() {
        $events = array(
            'acdc_of_qz_cron_dispatches'              => array( 'recurrence' => 'hourly', 'offset' => 300 ),
            'acdc_of_qz_cron_reminders'               => array( 'recurrence' => 'hourly', 'offset' => 600 ),
            'acdc_of_qz_cron_expirations'             => array( 'recurrence' => 'hourly', 'offset' => 900 ),
            'acdc_of_qz_cron_close_inactive_sessions' => array( 'recurrence' => 'hourly', 'offset' => 600 ),
            'acdc_of_qz_cron_rgpd_purge'              => array( 'recurrence' => 'daily',  'offset' => 86400 ),
        );

        foreach ( $events as $hook => $config ) {
            if ( ! wp_next_scheduled( $hook ) ) {
                wp_schedule_event(
                    time() + (int) $config['offset'],
                    $config['recurrence'],
                    $hook
                );
            }
        }
    }

    /**
     * Désinscrit tous les événements cron du module.
     * À utiliser à la désactivation du plugin si nécessaire.
     *
     * @return void
     */
    public function unschedule_quiz_cron_events() {
        $hooks = array(
            'acdc_of_qz_cron_dispatches',
            'acdc_of_qz_cron_reminders',
            'acdc_of_qz_cron_expirations',
            'acdc_of_qz_cron_close_inactive_sessions',
            'acdc_of_qz_cron_rgpd_purge',
        );
        foreach ( $hooks as $hook ) {
            $timestamp = wp_next_scheduled( $hook );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, $hook );
            }
        }
    }

    /* -------------------------------------------------------------------- */
    /*  Cartographies labels / statuts                                      */
    /* -------------------------------------------------------------------- */

    /**
     * Renvoie la map des finalités de quiz (clé technique => libellé affiché).
     *
     * @return array
     */
    public function get_quiz_purpose_labels() {
        return array(
            self::ACDC_OF_QZ_PURPOSE_LIVE        => 'Quiz live',
            self::ACDC_OF_QZ_PURPOSE_POSITIONING => 'Test de positionnement',
            self::ACDC_OF_QZ_PURPOSE_DIAGNOSTIC  => 'Évaluation diagnostique',
            self::ACDC_OF_QZ_PURPOSE_ASSESSMENT  => 'Évaluation des acquis',
        );
    }

    /**
     * Renvoie la map des modes de livraison.
     *
     * @return array
     */
    public function get_quiz_delivery_mode_labels() {
        return array(
            self::ACDC_OF_QZ_DELIVERY_LIVE_SYNC   => 'Animation live (synchrone)',
            self::ACDC_OF_QZ_DELIVERY_ASYNC_TOKEN => 'Envoi par e-mail (asynchrone)',
        );
    }

    /**
     * Renvoie la map des statuts d'un quiz.
     *
     * @return array
     */
    public function get_quiz_status_labels() {
        return array(
            self::ACDC_OF_QZ_STATUS_DRAFT    => 'Brouillon',
            self::ACDC_OF_QZ_STATUS_ACTIVE   => 'Actif',
            self::ACDC_OF_QZ_STATUS_ARCHIVED => 'Archivé',
            self::ACDC_OF_QZ_STATUS_LOCKED   => 'Verrouillé',
        );
    }

    /**
     * Renvoie la map des types de questions supportés.
     *
     * @return array
     */
    public function get_quiz_question_type_labels_v2() {
        return array(
            self::ACDC_OF_QZ_QTYPE_QCM_SINGLE   => 'QCM (1 réponse)',
            self::ACDC_OF_QZ_QTYPE_QCM_MULTIPLE => 'QCM (réponses multiples)',
            self::ACDC_OF_QZ_QTYPE_TRUE_FALSE   => 'Vrai / Faux',
            self::ACDC_OF_QZ_QTYPE_PUZZLE       => 'Puzzle (remise en ordre)',
            self::ACDC_OF_QZ_QTYPE_OPEN_TEXT    => 'Réponse libre (texte)',
            self::ACDC_OF_QZ_QTYPE_POLL         => 'Sondage (sans bonne réponse)',
        );
    }

    /**
     * Indique si un type de question est valide.
     *
     * @param string $type
     *
     * @return bool
     */
    public function is_valid_quiz_question_type( $type ) {
        return array_key_exists(
            (string) $type,
            $this->get_quiz_question_type_labels_v2()
        );
    }

    /**
     * Indique si une finalité de quiz est valide.
     *
     * @param string $purpose
     *
     * @return bool
     */
    public function is_valid_quiz_purpose( $purpose ) {
        return array_key_exists(
            (string) $purpose,
            $this->get_quiz_purpose_labels()
        );
    }

    /**
     * ACDC 3.25.168 — Corrige un jeu de réponses cochées et renvoie une PART de réussite.
     *
     * Jusqu'ici la correction était binaire : une seule case manquante sur trois et
     * l'apprenant repartait avec zéro. C'est faux pédagogiquement — il connaissait deux
     * tiers de la réponse — et cela fausse l'atteinte des objectifs Qualiopi, qui se
     * calcule sur ces points.
     *
     * Barème pour un QCM à réponses multiples :
     *
     *     part = ( cochées justes − cochées fausses ) / nombre de bonnes réponses
     *
     * borné à [0, 1]. Retrancher les cochées fausses est ce qui empêche de tout cocher
     * pour rafler les points : qui coche l'intégralité des propositions obtient zéro,
     * exactement comme qui ne coche rien.
     *
     * Les autres types restent en tout-ou-rien : un choix unique, un vrai/faux ou un
     * puzzle n'ont pas de réussite partielle qui ait un sens.
     *
     * @param object $question   Ligne de question (type attendu).
     * @param array  $answer_ids Identifiants de réponses cochés par l'apprenant.
     *
     * @return array {
     *     @type float    $ratio      Part de réussite entre 0 et 1.
     *     @type int|null $is_correct 1 si tout juste, 0 sinon, null si non corrigeable.
     *     @type bool     $partial    Vrai si 0 < ratio < 1.
     * }
     */
    public function qz_grade_answer_set( $question, $answer_ids ) {
        $none = array( 'ratio' => 0.0, 'is_correct' => 0, 'partial' => false );
        if ( ! is_object( $question ) ) {
            return $none;
        }
        $type       = (string) $question->type;
        $answer_ids = array_values( array_unique( array_map( 'intval', (array) $answer_ids ) ) );

        // Texte libre et sondage ne se corrigent pas automatiquement.
        if ( self::ACDC_OF_QZ_QTYPE_OPEN_TEXT === $type || self::ACDC_OF_QZ_QTYPE_POLL === $type ) {
            return array( 'ratio' => 0.0, 'is_correct' => null, 'partial' => false );
        }

        $all = $this->get_qz_answers_for_question_db( (int) $question->id );

        if ( self::ACDC_OF_QZ_QTYPE_PUZZLE === $type ) {
            // Puzzle : l'ordre fait la réponse, donc comparaison ordonnée, tout ou rien.
            usort( $all, function( $a, $b ) { return ( (int) $a->sort_order ) <=> ( (int) $b->sort_order ); } );
            $expected = array();
            foreach ( $all as $a ) {
                $expected[] = (int) $a->id;
            }
            $ok = ( ! empty( $expected ) && $answer_ids === $expected );
            return array( 'ratio' => $ok ? 1.0 : 0.0, 'is_correct' => $ok ? 1 : 0, 'partial' => false );
        }

        $correct_ids = array();
        foreach ( $all as $a ) {
            if ( 1 === (int) $a->is_correct ) {
                $correct_ids[] = (int) $a->id;
            }
        }
        if ( empty( $correct_ids ) ) {
            // Question sans bonne réponse déclarée : rien de corrigeable.
            return array( 'ratio' => 0.0, 'is_correct' => null, 'partial' => false );
        }

        $sorted_resp = $answer_ids;
        sort( $sorted_resp );
        $sorted_corr = $correct_ids;
        sort( $sorted_corr );
        $exact = ( $sorted_resp === $sorted_corr );

        if ( self::ACDC_OF_QZ_QTYPE_QCM_MULTIPLE !== $type ) {
            // Choix unique, vrai/faux : tout ou rien.
            return array( 'ratio' => $exact ? 1.0 : 0.0, 'is_correct' => $exact ? 1 : 0, 'partial' => false );
        }

        $hits   = count( array_intersect( $answer_ids, $correct_ids ) );
        $misses = count( array_diff( $answer_ids, $correct_ids ) );
        $ratio  = ( $hits - $misses ) / count( $correct_ids );
        $ratio  = max( 0.0, min( 1.0, (float) $ratio ) );

        return array(
            'ratio'      => $ratio,
            'is_correct' => ( $ratio >= 1.0 ) ? 1 : 0,
            'partial'    => ( $ratio > 0.0 && $ratio < 1.0 ),
        );
    }

    /**
     * ACDC 3.25.174 — Recalcule les parts de réussite absentes de player_answers.
     *
     * Corrige l'historique sans toucher aux scores déjà établis : on n'écrit que la
     * colonne score_ratio, et seulement là où elle est vide. Une réponse en attente de
     * correction manuelle reste à NULL — c'est sa valeur juste.
     *
     * @return int Nombre de lignes reprises.
     */
    private function qz_backfill_missing_score_ratios() {
        global $wpdb;
        $tbl_pa = $this->get_qz_table( 'player_answers' );
        $tbl_q  = $this->get_qz_table( 'questions' );
        if ( '' === $tbl_pa || '' === $tbl_q ) {
            return 0;
        }
        // La colonne doit exister : dbDelta vient de passer, mais on ne présume rien.
        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$tbl_pa} LIKE 'score_ratio'" );
        if ( empty( $cols ) ) {
            return 0;
        }

        $rows = $wpdb->get_results(
            "SELECT pa.id, pa.question_id, pa.answer_ids_json, pa.is_correct
             FROM {$tbl_pa} pa
             INNER JOIN {$tbl_q} q ON q.id = pa.question_id
             WHERE pa.score_ratio IS NULL AND pa.is_correct IS NOT NULL
             LIMIT 5000"
        );
        if ( empty( $rows ) ) {
            return 0;
        }

        $questions = array();
        $done      = 0;
        foreach ( $rows as $r ) {
            $qid = (int) $r->question_id;
            if ( ! isset( $questions[ $qid ] ) ) {
                $questions[ $qid ] = $this->get_qz_question( $qid );
            }
            if ( ! $questions[ $qid ] ) {
                continue;
            }
            $ids = json_decode( (string) $r->answer_ids_json, true );
            if ( ! is_array( $ids ) ) {
                preg_match_all( '/\d+/', (string) $r->answer_ids_json, $m );
                $ids = isset( $m[0] ) ? $m[0] : array();
            }
            $verdict = $this->qz_grade_answer_set( $questions[ $qid ], $ids );
            if ( null === $verdict['is_correct'] ) {
                continue; // non corrigeable automatiquement : la part reste vide.
            }
            $wpdb->update(
                $tbl_pa,
                array( 'score_ratio' => (float) $verdict['ratio'] ),
                array( 'id' => (int) $r->id ),
                array( '%f' ),
                array( '%d' )
            );
            $done++;
        }
        return $done;
    }

    /**
     * ACDC 3.25.171 — Score moyen en points bruts d'une session de quiz live.
     *
     * L'administration calculait cela en ligne, dans son gabarit ; le portail formateur
     * ne le calculait pas du tout et affichait « — » là où le gestionnaire lisait
     * 4 800 pts, pour la même passation. Un seul calcul, partagé par les deux rôles.
     *
     * @param int $session_id
     *
     * @return float|null Null si aucune passation notée.
     */
    public function get_qz_avg_live_score_for_session( $session_id ) {
        global $wpdb;
        $tbl_p = $this->get_qz_table( 'participants' );
        if ( '' === $tbl_p || (int) $session_id <= 0 ) {
            return null;
        }
        /* Un score de 0 est une valeur : ni le filtre ni le test d'affichage ne doivent
           l'écarter, sinon une session jouée et notée passe pour vide. */
        $avg = $wpdb->get_var( $wpdb->prepare(
            "SELECT AVG(total_score) FROM {$tbl_p} WHERE session_id = %d AND status = 'completed' AND total_score IS NOT NULL",
            (int) $session_id
        ) );
        return ( null === $avg ) ? null : (float) $avg;
    }

    /**
     * ACDC 3.25.171 — Seuil de réussite applicable à un quiz.
     *
     * Le seuil est facultatif sur la fiche du quiz. À défaut, 70 % — la valeur que le
     * PDF d'attestation appliquait déjà en dur de son côté. On la centralise pour que
     * l'écran, le PDF et la colonne « Résultat » disent tous la même chose.
     *
     * @param int $quiz_id
     *
     * @return float
     */
    public function qz_pass_threshold_for_quiz( $quiz_id ) {
        global $wpdb;
        $tbl = $this->get_qz_table( 'quizzes' );
        if ( '' === $tbl || (int) $quiz_id <= 0 ) {
            return 70.0;
        }
        $raw = $wpdb->get_var( $wpdb->prepare( "SELECT pass_threshold FROM {$tbl} WHERE id = %d", (int) $quiz_id ) );
        return ( null !== $raw && '' !== $raw ) ? (float) $raw : 70.0;
    }

    /**
     * ACDC 3.25.168 — Libellé de correction d'une réponse, part de réussite comprise.
     *
     * @param int|null   $is_correct  Colonne is_correct de la réponse.
     * @param float|null $score_ratio Colonne score_ratio de la réponse.
     *
     * @return array { @type string $label, @type string $state } state : correct|partial|wrong|pending
     */
    public function qz_answer_verdict( $is_correct, $score_ratio ) {
        if ( null === $is_correct ) {
            return array( 'label' => 'À corriger', 'state' => 'pending' );
        }
        if ( (int) $is_correct === 1 ) {
            return array( 'label' => 'Juste', 'state' => 'correct' );
        }
        if ( null !== $score_ratio && (float) $score_ratio > 0.0 ) {
            return array(
                'label' => sprintf( 'Partiellement juste (%d %%)', (int) round( (float) $score_ratio * 100 ) ),
                'state' => 'partial',
            );
        }
        return array( 'label' => 'Faux', 'state' => 'wrong' );
    }

    /**
     * ACDC 3.25.168 — Liste des onglets « Résultats » du portail.
     *
     * Ces onglets imposent la vue résultats et masquent le filtre de finalité. La liste
     * était recopiée à deux endroits et l'évaluation diagnostique n'avait été ajoutée ni
     * à l'un ni à l'autre : son entrée de menu existait, mais elle ouvrait la LISTE des
     * quiz au lieu de leurs résultats — d'où « la page de résultats n'existe pas ».
     *
     * @return array
     */
    public function qz_results_tabs() {
        return array(
            'qz_results',
            'qz_results_live',
            'qz_results_positioning',
            'qz_results_diagnostic',
            'qz_results_assessment',
        );
    }

    /**
     * ACDC 3.25.167 — Onglet de résultats correspondant à une finalité.
     *
     * Deux endroits choisissaient cet onglet avec un ternaire à deux branches, écrit
     * quand il n'existait que trois finalités. L'un comparait même la finalité à un
     * slug d'onglet, donc n'était jamais vrai. Un seul point de vérité évite que la
     * prochaine finalité ajoutée reparte silencieusement sur « évaluations ».
     *
     * @param string $purpose Finalité du quiz.
     *
     * @return string Slug d'onglet du portail.
     */
    public function qz_results_tab_for_purpose( $purpose ) {
        switch ( (string) $purpose ) {
            case self::ACDC_OF_QZ_PURPOSE_POSITIONING:
                return 'qz_results_positioning';
            case self::ACDC_OF_QZ_PURPOSE_DIAGNOSTIC:
                return 'qz_results_diagnostic';
            case self::ACDC_OF_QZ_PURPOSE_LIVE:
                return 'qz_results_live';
            default:
                return 'qz_results_assessment';
        }
    }

    /**
     * Indique si un mode de livraison est valide.
     *
     * @param string $mode
     *
     * @return bool
     */
    public function is_valid_quiz_delivery_mode( $mode ) {
        return array_key_exists(
            (string) $mode,
            $this->get_quiz_delivery_mode_labels()
        );
    }

    /* -------------------------------------------------------------------- */
    /*  Helper journal d'audit                                              */
    /* -------------------------------------------------------------------- */

    /**
     * Insère une entrée dans le journal d'audit du module (table append-only).
     *
     * @param array $data {
     *   @type int|null    $quiz_id
     *   @type int|null    $session_id
     *   @type int|null    $participant_id
     *   @type string      $event_type
     *   @type string      $event_label
     *   @type array|null  $event_payload  Tableau qui sera encodé en JSON.
     *   @type int|null    $user_id        Par défaut, current user.
     *   @type string|null $ip_address
     * }
     *
     * @return int|false ID inséré ou false en cas d'échec.
     */
    public function log_qz_event( $data ) {
        global $wpdb;
        if ( empty( $this->qz_tables ) ) {
            $this->init_quizzes_module_tables();
        }

        $event_type  = isset( $data['event_type'] )  ? sanitize_key( $data['event_type'] ) : '';
        $event_label = isset( $data['event_label'] ) ? (string) $data['event_label']        : '';
        if ( '' === $event_type || '' === $event_label ) {
            return false;
        }

        $payload = isset( $data['event_payload'] ) && is_array( $data['event_payload'] )
            ? wp_json_encode( $data['event_payload'] )
            : null;

        $row = array(
            'quiz_id'        => isset( $data['quiz_id'] )        ? (int) $data['quiz_id']        : null,
            'session_id'     => isset( $data['session_id'] )     ? (int) $data['session_id']     : null,
            'participant_id' => isset( $data['participant_id'] ) ? (int) $data['participant_id'] : null,
            'event_type'     => $event_type,
            'event_label'    => mb_substr( wp_strip_all_tags( $event_label ), 0, 255 ),
            'event_payload'  => $payload,
            'user_id'        => isset( $data['user_id'] ) ? (int) $data['user_id'] : ( get_current_user_id() ?: null ),
            'ip_address'     => isset( $data['ip_address'] ) ? mb_substr( (string) $data['ip_address'], 0, 45 ) : null,
            'created_at'     => current_time( 'mysql' ),
        );

        $ok = $wpdb->insert( $this->qz_tables['logs'], $row );
        return $ok ? (int) $wpdb->insert_id : false;
    }

    /* -------------------------------------------------------------------- */
    /*  Indicateurs simples (pour la page Pilotage)                         */
    /* -------------------------------------------------------------------- */

    /**
     * Compte les quiz actifs par finalité, toutes formations confondues.
     *
     * Utilisé par la page de pilotage Qualiopi pour donner un aperçu rapide.
     * Méthode défensive : retourne des zéros si la table n'existe pas encore.
     *
     * @return array Tableau associatif purpose => count.
     */
    public function get_qz_active_count_by_purpose() {
        global $wpdb;
        if ( empty( $this->qz_tables ) ) {
            $this->init_quizzes_module_tables();
        }

        $result = array(
            self::ACDC_OF_QZ_PURPOSE_LIVE        => 0,
            self::ACDC_OF_QZ_PURPOSE_POSITIONING => 0,
            self::ACDC_OF_QZ_PURPOSE_DIAGNOSTIC  => 0,
            self::ACDC_OF_QZ_PURPOSE_ASSESSMENT  => 0,
        );

        $tbl = $this->qz_tables['quizzes'];
        // Vérification d'existence pour éviter une erreur SQL si dbDelta n'est pas encore passé.
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $tbl ) );
        if ( $exists !== $tbl ) {
            return $result;
        }

        $rows = $wpdb->get_results(
            "SELECT quiz_purpose, COUNT(*) AS cnt
             FROM {$tbl}
             WHERE status = 'active'
             GROUP BY quiz_purpose",
            ARRAY_A
        );

        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                $key = isset( $row['quiz_purpose'] ) ? (string) $row['quiz_purpose'] : '';
                if ( isset( $result[ $key ] ) ) {
                    $result[ $key ] = (int) $row['cnt'];
                }
            }
        }

        return $result;
    }

    /* ====================================================================
     *  CRUD — Quizzes (fetch)                                  [3.21.01]
     * ==================================================================== */

    /**
     * Récupère un quiz par son ID. Retourne null si introuvable.
     *
     * @param int $quiz_id
     *
     * @return object|null Ligne brute (stdClass) ou null.
     */
    public function get_qz_quiz( $quiz_id ) {
        global $wpdb;
        $quiz_id = (int) $quiz_id;
        if ( $quiz_id <= 0 ) {
            return null;
        }
        $tbl = $this->get_qz_table( 'quizzes' );
        if ( '' === $tbl ) {
            return null;
        }
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl} WHERE id = %d", $quiz_id ) );
        if ( ! $row ) { return null; }

        /* Auto-correction du mode de passation, à la lecture.
           ACDC 3.25.165 — L'ÉVALUATION DES ACQUIS EN EST RETIRÉE. Cette correction
           forçait en asynchrone toute évaluation, y compris celle que l'organisme
           avait délibérément réglée en salle : le choix était réécrit au premier
           chargement, sans message, et le bouton de lancement ne réapparaissait
           jamais. Elle ne vaut plus que pour le test de positionnement, qui se passe
           par nature à distance, avant l'entrée en formation.
           L'évaluation diagnostique n'y figure pas non plus : elle se passe en salle
           au début de la formation. */
        if ( in_array( $row->quiz_purpose, array( self::ACDC_OF_QZ_PURPOSE_POSITIONING ), true )
            && $row->delivery_mode !== self::ACDC_OF_QZ_DELIVERY_ASYNC_TOKEN ) {
            $wpdb->update( $tbl, array( 'delivery_mode' => self::ACDC_OF_QZ_DELIVERY_ASYNC_TOKEN ), array( 'id' => $quiz_id ), array( '%s' ), array( '%d' ) );
            $row->delivery_mode = self::ACDC_OF_QZ_DELIVERY_ASYNC_TOKEN;
        }

        return $row;
    }

    /**
     * Liste les quiz selon des filtres simples.
     *
     * @param array $args {
     *   @type string $purpose      live|positioning|assessment (optionnel)
     *   @type int    $formation_id (optionnel ; 0 = toutes)
     *   @type string $status       draft|active|archived|locked (optionnel)
     *   @type int    $limit        (par défaut 200)
     *   @type string $orderby      created_at|updated_at|title|version_number (par défaut updated_at)
     *   @type string $order        ASC|DESC (par défaut DESC)
     * }
     *
     * @return array Tableau d'objets quiz.
     */
    public function get_qz_quizzes( $args = array() ) {
        global $wpdb;
        $tbl = $this->get_qz_table( 'quizzes' );
        if ( '' === $tbl ) {
            return array();
        }

        $defaults = array(
            'purpose'      => '',
            'formation_id' => 0,
            'status'       => '',
            'limit'        => 200,
            'orderby'      => 'updated_at',
            'order'        => 'DESC',
        );
        $args = array_merge( $defaults, $args );

        $where = array( '1=1' );
        $vals  = array();

        if ( $args['purpose'] !== '' && $this->is_valid_quiz_purpose( $args['purpose'] ) ) {
            $where[] = 'quiz_purpose = %s';
            $vals[]  = $args['purpose'];
        }
        if ( (int) $args['formation_id'] > 0 ) {
            $where[] = 'formation_id = %d';
            $vals[]  = (int) $args['formation_id'];
        }
        if ( $args['status'] !== '' && in_array( $args['status'], array( 'draft', 'active', 'archived', 'locked' ), true ) ) {
            $where[] = 'status = %s';
            $vals[]  = $args['status'];
        }

        $allowed_orderby = array( 'created_at', 'updated_at', 'title', 'version_number', 'id' );
        $orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'updated_at';
        $order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
        $limit           = max( 1, min( (int) $args['limit'], 1000 ) );

        $sql = "SELECT * FROM {$tbl} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$orderby} {$order} LIMIT {$limit}";
        if ( ! empty( $vals ) ) {
            $sql = $wpdb->prepare( $sql, $vals );
        }
        $rows = $wpdb->get_results( $sql );
        return is_array( $rows ) ? $rows : array();
    }

    /**
     * Compte le nombre de réponses enregistrées pour un quiz (toutes versions
     * confondues si on s'intéresse à la version spécifiée par quiz_id).
     *
     * Utilisé pour décider si un quiz doit être verrouillé.
     *
     * @param int $quiz_id
     *
     * @return int
     */
    public function count_qz_player_answers_for_quiz( $quiz_id ) {
        global $wpdb;
        $quiz_id = (int) $quiz_id;
        if ( $quiz_id <= 0 ) {
            return 0;
        }
        $tbl_pa = $this->get_qz_table( 'player_answers' );
        $tbl_q  = $this->get_qz_table( 'questions' );
        if ( '' === $tbl_pa || '' === $tbl_q ) {
            return 0;
        }
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl_pa} pa
             INNER JOIN {$tbl_q} q ON q.id = pa.question_id
             WHERE q.quiz_id = %d",
            $quiz_id
        ) );
        return $count;
    }

    /**
     * Indique si un quiz est verrouillé (déjà utilisé par au moins 1 apprenant).
     * Met à jour le drapeau is_locked en base si nécessaire (auto-correction).
     *
     * @param int $quiz_id
     *
     * @return bool
     */
    public function is_qz_quiz_locked( $quiz_id ) {
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) {
            return false;
        }
        if ( (int) $quiz->is_locked === 1 ) {
            return true;
        }
        $count = $this->count_qz_player_answers_for_quiz( $quiz_id );
        if ( $count > 0 ) {
            // Auto-verrouillage rétroactif si on détecte des réponses.
            global $wpdb;
            $wpdb->update(
                $this->get_qz_table( 'quizzes' ),
                array(
                    'is_locked'     => 1,
                    'locked_at'     => current_time( 'mysql' ),
                    'locked_reason' => 'Auto-detected: existing player answers',
                    'updated_at'    => current_time( 'mysql' ),
                ),
                array( 'id' => (int) $quiz_id )
            );
            $this->log_qz_event( array(
                'quiz_id'     => (int) $quiz_id,
                'event_type'  => 'quiz_locked',
                'event_label' => 'Auto-verrouillage suite à détection de réponses existantes',
                'event_payload' => array( 'answers_count' => $count ),
            ) );
            return true;
        }
        return false;
    }

    /* ====================================================================
     *  CRUD — Questions, Answers, Objectives (fetch)            [3.21.01]
     * ==================================================================== */

    /**
     * Récupère les questions d'un quiz (avec leurs réponses chargées en bloc).
     *
     * @param int $quiz_id
     *
     * @return array Liste d'objets question, chacun avec une propriété ->answers (array).
     */
    public function get_qz_questions_for_quiz( $quiz_id ) {
        global $wpdb;
        $quiz_id = (int) $quiz_id;
        if ( $quiz_id <= 0 ) {
            return array();
        }

        $tbl_q = $this->get_qz_table( 'questions' );
        $tbl_a = $this->get_qz_table( 'answers' );
        if ( '' === $tbl_q || '' === $tbl_a ) {
            return array();
        }

        $questions = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$tbl_q} WHERE quiz_id = %d ORDER BY sort_order ASC, id ASC",
            $quiz_id
        ) );
        if ( ! is_array( $questions ) || empty( $questions ) ) {
            return array();
        }

        $ids = wp_list_pluck( $questions, 'id' );
        $ids = array_map( 'intval', $ids );
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

        $answers = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$tbl_a} WHERE question_id IN ({$placeholders}) ORDER BY sort_order ASC, id ASC",
            $ids
        ) );

        $by_q = array();
        if ( is_array( $answers ) ) {
            foreach ( $answers as $a ) {
                $qid = (int) $a->question_id;
                if ( ! isset( $by_q[ $qid ] ) ) {
                    $by_q[ $qid ] = array();
                }
                $by_q[ $qid ][] = $a;
            }
        }

        foreach ( $questions as $q ) {
            $qid = (int) $q->id;
            $q->answers = isset( $by_q[ $qid ] ) ? $by_q[ $qid ] : array();
        }

        return $questions;
    }

    /**
     * Récupère une question par son ID, avec ses réponses chargées.
     *
     * @param int $question_id
     *
     * @return object|null
     */
    public function get_qz_question( $question_id ) {
        global $wpdb;
        $question_id = (int) $question_id;
        if ( $question_id <= 0 ) {
            return null;
        }
        $tbl_q = $this->get_qz_table( 'questions' );
        $tbl_a = $this->get_qz_table( 'answers' );
        if ( '' === $tbl_q ) {
            return null;
        }

        $q = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl_q} WHERE id = %d", $question_id ) );
        if ( ! $q ) {
            return null;
        }
        $q->answers = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$tbl_a} WHERE question_id = %d ORDER BY sort_order ASC, id ASC",
            $question_id
        ) );
        if ( ! is_array( $q->answers ) ) {
            $q->answers = array();
        }
        return $q;
    }

    /**
     * Récupère les objectifs pédagogiques d'un quiz.
     *
     * @param int $quiz_id
     *
     * @return array
     */
    public function get_qz_objectives_for_quiz( $quiz_id ) {
        global $wpdb;
        $quiz_id = (int) $quiz_id;
        if ( $quiz_id <= 0 ) {
            return array();
        }
        $tbl = $this->get_qz_table( 'objectives' );
        if ( '' === $tbl ) {
            return array();
        }
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$tbl} WHERE quiz_id = %d ORDER BY sort_order ASC, id ASC",
            $quiz_id
        ) );
        return is_array( $rows ) ? $rows : array();
    }

    /* ====================================================================
     *  CRUD — Insert / Update                                   [3.21.01]
     * ==================================================================== */

    /**
     * Insère un nouveau quiz "coquille" avec un titre et une finalité.
     *
     * @param array $data {
     *   @type int    $formation_id   Obligatoire, > 0.
     *   @type string $quiz_purpose   Obligatoire, valide.
     *   @type string $title          Obligatoire (1-255 car).
     *   @type string $delivery_mode  Optionnel ; déterminé selon purpose si absent.
     *   @type string $description    Optionnel.
     * }
     *
     * @return int|false ID inséré, ou false si erreur de validation.
     */
    public function insert_qz_quiz( $data ) {
        global $wpdb;
        $tbl = $this->get_qz_table( 'quizzes' );
        if ( '' === $tbl ) {
            return false;
        }

        $formation_id = isset( $data['formation_id'] ) ? (int) $data['formation_id'] : 0;
        $purpose      = isset( $data['quiz_purpose'] ) ? (string) $data['quiz_purpose'] : '';
        $title        = isset( $data['title'] ) ? trim( (string) $data['title'] ) : '';
        $description  = isset( $data['description'] ) ? (string) $data['description'] : '';

        if ( $formation_id <= 0 || ! $this->is_valid_quiz_purpose( $purpose ) || $title === '' ) {
            return false;
        }
        $title = mb_substr( $title, 0, 255 );

        // Délivrance par défaut selon la finalité.
        $delivery = isset( $data['delivery_mode'] ) ? (string) $data['delivery_mode'] : '';
        if ( ! $this->is_valid_quiz_delivery_mode( $delivery ) ) {
            /* ACDC 3.25.165 — Même règle qu'à l'enregistrement : diagnostique et acquis
               se passent en salle par défaut. */
            $delivery = in_array( $purpose, array( self::ACDC_OF_QZ_PURPOSE_LIVE, self::ACDC_OF_QZ_PURPOSE_DIAGNOSTIC, self::ACDC_OF_QZ_PURPOSE_ASSESSMENT ), true )
                ? self::ACDC_OF_QZ_DELIVERY_LIVE_SYNC
                : self::ACDC_OF_QZ_DELIVERY_ASYNC_TOKEN;
        }

        $now = current_time( 'mysql' );
        $row = array(
            'formation_id'        => $formation_id,
            'quiz_purpose'        => $purpose,
            'delivery_mode'       => $delivery,
            'title'               => $title,
            'description'         => $description,
            'language'            => 'fr',
            'version_number'      => 1,
            'is_current'          => 0,
            'status'              => self::ACDC_OF_QZ_STATUS_DRAFT,
            'is_locked'           => 0,
            'scoring_mode'        => 'percentage',
            'pass_threshold'      => null,
            'failure_action'      => 'none',
            'max_attempts'        => 1,
            'antifraud_time_limit'       => 0,
            'antifraud_no_back'          => 0,
            'antifraud_visibility_check' => 0,
            'antifraud_fullscreen'       => 0,
            'data_retention_days' => 1095,
            'created_by'          => get_current_user_id() ?: 0,
            'created_at'          => $now,
            'updated_at'          => $now,
        );

        $ok = $wpdb->insert( $tbl, $row );
        if ( ! $ok ) {
            return false;
        }
        $new_id = (int) $wpdb->insert_id;

        $this->log_qz_event( array(
            'quiz_id'     => $new_id,
            'event_type'  => 'quiz_created',
            'event_label' => sprintf( 'Création quiz #%d : %s', $new_id, $title ),
            'event_payload' => array(
                'formation_id'  => $formation_id,
                'quiz_purpose'  => $purpose,
                'delivery_mode' => $delivery,
            ),
        ) );

        return $new_id;
    }

    /**
     * Met à jour les métadonnées d'un quiz (sans toucher aux questions).
     *
     * Refuse silencieusement si le quiz est verrouillé.
     *
     * @param int   $quiz_id
     * @param array $data Champs autorisés à modifier.
     *
     * @return bool
     */
    public function update_qz_quiz( $quiz_id, $data ) {
        global $wpdb;
        $quiz_id = (int) $quiz_id;
        if ( $quiz_id <= 0 ) {
            return false;
        }
        if ( $this->is_qz_quiz_locked( $quiz_id ) ) {
            return false;
        }

        $tbl = $this->get_qz_table( 'quizzes' );
        if ( '' === $tbl ) {
            return false;
        }

        $allowed = array(
            'title', 'description', 'cover_image_id', 'language',
            'delivery_mode', 'scoring_mode', 'pass_threshold',
            'level_thresholds_json', 'failure_action', 'max_attempts',
            'antifraud_time_limit', 'antifraud_no_back',
            'antifraud_visibility_check', 'antifraud_fullscreen',
            'data_retention_days', 'version_label',
        );

        $set = array();
        foreach ( $allowed as $field ) {
            if ( array_key_exists( $field, $data ) ) {
                $set[ $field ] = $data[ $field ];
            }
        }

        // Sanitization spécifique
        if ( isset( $set['title'] ) ) {
            $set['title'] = mb_substr( trim( (string) $set['title'] ), 0, 255 );
            if ( '' === $set['title'] ) {
                unset( $set['title'] );
            }
        }
        if ( isset( $set['delivery_mode'] ) && ! $this->is_valid_quiz_delivery_mode( $set['delivery_mode'] ) ) {
            unset( $set['delivery_mode'] );
        }
        if ( isset( $set['max_attempts'] ) ) {
            $set['max_attempts'] = max( 1, min( 10, (int) $set['max_attempts'] ) );
        }
        if ( isset( $set['data_retention_days'] ) ) {
            $set['data_retention_days'] = max( 30, min( 3650, (int) $set['data_retention_days'] ) );
        }
        foreach ( array( 'antifraud_time_limit', 'antifraud_no_back', 'antifraud_visibility_check', 'antifraud_fullscreen' ) as $bool_field ) {
            if ( isset( $set[ $bool_field ] ) ) {
                $set[ $bool_field ] = ! empty( $set[ $bool_field ] ) ? 1 : 0;
            }
        }

        if ( empty( $set ) ) {
            return false;
        }

        $set['updated_at'] = current_time( 'mysql' );
        $ok = $wpdb->update( $tbl, $set, array( 'id' => $quiz_id ) );

        if ( false !== $ok ) {
            $this->log_qz_event( array(
                'quiz_id'     => $quiz_id,
                'event_type'  => 'quiz_updated',
                'event_label' => sprintf( 'Mise à jour quiz #%d', $quiz_id ),
                'event_payload' => array_keys( $set ),
            ) );
            return true;
        }
        return false;
    }

    /**
     * Insère ou met à jour une question d'un quiz, avec ses réponses.
     *
     * @param int   $quiz_id
     * @param array $data {
     *   @type int|null $id          Si fourni, mise à jour.
     *   @type string   $type        Type de question (validé).
     *   @type string   $title
     *   @type string   $description
     *   @type int      $time_limit
     *   @type int      $points_value
     *   @type string   $points_type standard|double|none
     *   @type int      $is_scored
     *   @type int|null $objective_id
     *   @type int      $sort_order
     *   @type array    $answers     Liste de réponses (text + is_correct + sort_order + feedback_text).
     * }
     *
     * @return int|false ID question, ou false en cas d'erreur.
     */
    public function upsert_qz_question( $quiz_id, $data ) {
        global $wpdb;
        $quiz_id = (int) $quiz_id;
        if ( $quiz_id <= 0 ) {
            return false;
        }
        if ( $this->is_qz_quiz_locked( $quiz_id ) ) {
            return false;
        }
        $tbl_q = $this->get_qz_table( 'questions' );
        $tbl_a = $this->get_qz_table( 'answers' );
        if ( '' === $tbl_q || '' === $tbl_a ) {
            return false;
        }

        $type = isset( $data['type'] ) ? (string) $data['type'] : self::ACDC_OF_QZ_QTYPE_QCM_SINGLE;
        if ( ! $this->is_valid_quiz_question_type( $type ) ) {
            $type = self::ACDC_OF_QZ_QTYPE_QCM_SINGLE;
        }
        // 3.21.01.2 — Le titre peut être vide à la création : l'utilisateur le
        // saisit dans l'éditeur après ajout, et l'autosave persiste son texte
        // au fil de l'eau. La validation "titre obligatoire" se fait au niveau
        // de la finalisation du quiz (verrouillage / lancement), pas à l'unité.
        $title = isset( $data['title'] ) ? trim( (string) $data['title'] ) : '';

        $now      = current_time( 'mysql' );
        $row      = array(
            'quiz_id'      => $quiz_id,
            'type'         => $type,
            'title'        => mb_substr( $title, 0, 1000 ),
            'description'  => isset( $data['description'] ) ? (string) $data['description'] : null,
            'media_id'     => isset( $data['media_id'] ) && (int) $data['media_id'] > 0 ? (int) $data['media_id'] : null,
            'time_limit'   => isset( $data['time_limit'] ) ? max( 5, min( 600, (int) $data['time_limit'] ) ) : 20,
            'points_type'  => in_array( ( $data['points_type'] ?? 'standard' ), array( 'standard', 'double', 'none' ), true ) ? $data['points_type'] : 'standard',
            'points_value' => isset( $data['points_value'] ) ? max( 0, min( 10000, (int) $data['points_value'] ) ) : 1000,
            'is_scored'    => empty( $data['is_scored'] ) ? 0 : 1,
            'objective_id' => isset( $data['objective_id'] ) && (int) $data['objective_id'] > 0 ? (int) $data['objective_id'] : null,
            'sort_order'   => isset( $data['sort_order'] ) ? max( 0, min( 9999, (int) $data['sort_order'] ) ) : 0,
            'updated_at'   => $now,
        );

        // Réponse attendue pour les questions open_text (affichée sur l'écran host au reveal)
        if ( isset( $data['expected_answer'] ) && $type === self::ACDC_OF_QZ_QTYPE_OPEN_TEXT ) {
            $cols_check = $wpdb->get_col( "SHOW COLUMNS FROM {$tbl_q} LIKE 'expected_answer'" );
            if ( empty( $cols_check ) ) {
                $wpdb->query( "ALTER TABLE {$tbl_q} ADD COLUMN expected_answer TEXT NULL" );
            }
            $row['expected_answer'] = sanitize_textarea_field( (string) $data['expected_answer'] );
        }

        // Sondage et open_text non scorés par défaut.
        if ( in_array( $type, array( self::ACDC_OF_QZ_QTYPE_POLL, self::ACDC_OF_QZ_QTYPE_OPEN_TEXT ), true ) ) {
            $row['is_scored'] = 0;
        }

        $existing_id = isset( $data['id'] ) ? (int) $data['id'] : 0;
        /* ACDC 3.25.157 — Verrou de sérialisation des sauvegardes concurrentes.
           Verrou NOMMÉ MySQL et non transaction : il fonctionne quel que soit le
           moteur de tables (y compris MyISAM, où START TRANSACTION serait un
           no-op silencieux). Il n'est utile que sur une question existante — c'est
           là que le remplacement destructif des réponses a lieu. */
        $lock_name = '';
        if ( $existing_id > 0 ) {
            $lock_name = $this->qz_answers_lock_acquire( $existing_id );
            $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl_q} WHERE id = %d AND quiz_id = %d", $existing_id, $quiz_id ) );
            if ( ! $existing ) {
                $this->qz_answers_lock_release( $lock_name );
                return false;
            }
            $wpdb->update( $tbl_q, $row, array( 'id' => $existing_id ) );
            $question_id = $existing_id;
            /* ACDC 3.25.157 — Le remplacement des réponses est un DELETE suivi de N
               INSERT. Sans sérialisation, deux autosaves concurrents sur la MÊME
               question s'entrelacent : les deux DELETE passent d'abord, puis les deux
               jeux d'INSERT — et la question se retrouve avec ses propositions en
               double (« Gamma, Gamma, Delta, Delta »). Le verrou nommé MySQL, pris
               plus haut, sérialise les deux requêtes ; le DELETE reste ici. */
            $wpdb->delete( $tbl_a, array( 'question_id' => $question_id ) );
        } else {
            $row['created_at'] = $now;
            $wpdb->insert( $tbl_q, $row );
            $question_id = (int) $wpdb->insert_id;
            if ( $question_id <= 0 ) {
                $this->qz_answers_lock_release( $lock_name );
                return false;
            }
        }

        // Réponses
        if ( isset( $data['answers'] ) && is_array( $data['answers'] ) ) {
            $sort = 0;
            foreach ( $data['answers'] as $ans ) {
                $text = isset( $ans['text'] ) ? trim( (string) $ans['text'] ) : '';
                if ( '' === $text ) {
                    continue;
                }
                $wpdb->insert( $tbl_a, array(
                    'question_id'   => $question_id,
                    'text'          => mb_substr( $text, 0, 500 ),
                    'media_id'      => isset( $ans['media_id'] ) && (int) $ans['media_id'] > 0 ? (int) $ans['media_id'] : null,
                    'is_correct'    => empty( $ans['is_correct'] ) ? 0 : 1,
                    'feedback_text' => isset( $ans['feedback_text'] ) ? (string) $ans['feedback_text'] : null,
                    'sort_order'    => $sort++,
                    'created_at'    => $now,
                ) );
            }
        }

        /* ACDC 3.25.157 — Filet de sécurité : répare les doublons déjà en base.
           À l'intérieur d'une sauvegarde, sort_order est strictement croissant et
           donc unique. Deux lignes de même sort_order pour une même question ne
           peuvent donc provenir que d'une double écriture : on ne conserve que la
           première. Aucun contenu légitime n'est perdu. */
        $this->qz_dedupe_question_answers( $question_id );

        // Mise à jour updated_at du quiz parent.
        $wpdb->update( $this->get_qz_table( 'quizzes' ), array( 'updated_at' => $now ), array( 'id' => $quiz_id ) );

        $this->qz_answers_lock_release( $lock_name );

        return $question_id;
    }

    /**
     * ACDC 3.25.157 — Prend un verrou nommé MySQL sur les réponses d'une question.
     *
     * @param int $question_id Identifiant de la question.
     * @return string Nom du verrou obtenu, ou '' si le verrou n'a pas pu être pris
     *                (on n'échoue pas la sauvegarde pour autant : la déduplication
     *                de fin de traitement reste le filet).
     */
    private function qz_answers_lock_acquire( $question_id ) {
        global $wpdb;
        $question_id = (int) $question_id;
        if ( $question_id <= 0 ) {
            return '';
        }
        /* Le nom est préfixé par la base : deux sites partageant un même serveur
           MySQL ne doivent pas se bloquer mutuellement. Limite MySQL : 64 octets. */
        $name = substr( 'acdc_qz_ans_' . md5( DB_NAME . '|' . $question_id ), 0, 64 );
        $got  = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $name, 5 ) );
        return ( '1' === (string) $got ) ? $name : '';
    }

    /**
     * ACDC 3.25.157 — Relâche le verrou pris par qz_answers_lock_acquire().
     *
     * @param string $lock_name Nom retourné à l'acquisition ('' = rien à faire).
     */
    private function qz_answers_lock_release( $lock_name ) {
        if ( '' === (string) $lock_name ) {
            return;
        }
        global $wpdb;
        $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
    }

    /**
     * ACDC 3.25.157 — Supprime les propositions en doublon d'une question.
     * Critère : même sort_order (impossible au sein d'une seule sauvegarde).
     *
     * @param int $question_id Identifiant de la question.
     */
    private function qz_dedupe_question_answers( $question_id ) {
        global $wpdb;
        $question_id = (int) $question_id;
        $tbl_a       = $this->get_qz_table( 'answers' );
        if ( $question_id <= 0 || '' === $tbl_a ) {
            return;
        }
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, sort_order FROM {$tbl_a} WHERE question_id = %d ORDER BY sort_order ASC, id ASC",
            $question_id
        ) );
        if ( empty( $rows ) ) {
            return;
        }
        $seen = array();
        foreach ( $rows as $r ) {
            $key = (int) $r->sort_order;
            if ( isset( $seen[ $key ] ) ) {
                $wpdb->delete( $tbl_a, array( 'id' => (int) $r->id ), array( '%d' ) );
                continue;
            }
            $seen[ $key ] = true;
        }
    }

    /**
     * Supprime une question (et ses réponses, par cascade applicative).
     *
     * @param int $quiz_id
     * @param int $question_id
     *
     * @return bool
     */
    public function delete_qz_question( $quiz_id, $question_id ) {
        global $wpdb;
        $quiz_id     = (int) $quiz_id;
        $question_id = (int) $question_id;
        if ( $quiz_id <= 0 || $question_id <= 0 ) {
            return false;
        }
        if ( $this->is_qz_quiz_locked( $quiz_id ) ) {
            return false;
        }
        $tbl_q = $this->get_qz_table( 'questions' );
        $tbl_a = $this->get_qz_table( 'answers' );
        if ( '' === $tbl_q || '' === $tbl_a ) {
            return false;
        }
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tbl_q} WHERE id = %d AND quiz_id = %d", $question_id, $quiz_id ) );
        if ( ! $existing ) {
            return false;
        }
        $wpdb->delete( $tbl_a, array( 'question_id' => $question_id ) );
        $wpdb->delete( $tbl_q, array( 'id' => $question_id ) );
        $wpdb->update( $this->get_qz_table( 'quizzes' ), array( 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $quiz_id ) );
        return true;
    }

    /**
     * Réordonne les questions d'un quiz selon une liste d'IDs.
     *
     * @param int   $quiz_id
     * @param array $ordered_ids Liste d'IDs dans le nouvel ordre.
     *
     * @return bool
     */
    public function reorder_qz_questions( $quiz_id, $ordered_ids ) {
        global $wpdb;
        $quiz_id = (int) $quiz_id;
        if ( $quiz_id <= 0 || ! is_array( $ordered_ids ) ) {
            return false;
        }
        if ( $this->is_qz_quiz_locked( $quiz_id ) ) {
            return false;
        }
        $tbl_q = $this->get_qz_table( 'questions' );
        if ( '' === $tbl_q ) {
            return false;
        }
        $sort = 0;
        foreach ( $ordered_ids as $qid ) {
            $qid = (int) $qid;
            if ( $qid <= 0 ) {
                continue;
            }
            $wpdb->update( $tbl_q, array( 'sort_order' => $sort++, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $qid, 'quiz_id' => $quiz_id ) );
        }
        $wpdb->update( $this->get_qz_table( 'quizzes' ), array( 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $quiz_id ) );
        return true;
    }

    /**
     * Insère ou met à jour un objectif pédagogique d'un quiz.
     *
     * @param int   $quiz_id
     * @param array $data {
     *   @type int|null   $id
     *   @type string     $label
     *   @type string     $description
     *   @type float|null $pass_threshold
     *   @type int        $sort_order
     * }
     *
     * @return int|false
     */
    public function upsert_qz_objective( $quiz_id, $data ) {
        global $wpdb;
        $quiz_id = (int) $quiz_id;
        if ( $quiz_id <= 0 ) {
            return false;
        }
        if ( $this->is_qz_quiz_locked( $quiz_id ) ) {
            return false;
        }
        $tbl = $this->get_qz_table( 'objectives' );
        if ( '' === $tbl ) {
            return false;
        }
        $label = isset( $data['label'] ) ? trim( (string) $data['label'] ) : '';
        if ( '' === $label ) {
            return false;
        }

        $row = array(
            'quiz_id'        => $quiz_id,
            'label'          => mb_substr( $label, 0, 255 ),
            'description'    => isset( $data['description'] ) ? (string) $data['description'] : null,
            'pass_threshold' => isset( $data['pass_threshold'] ) && '' !== $data['pass_threshold'] ? max( 0.0, min( 100.0, (float) $data['pass_threshold'] ) ) : null,
            'sort_order'     => isset( $data['sort_order'] ) ? max( 0, min( 255, (int) $data['sort_order'] ) ) : 0,
        );

        $existing_id = isset( $data['id'] ) ? (int) $data['id'] : 0;
        if ( $existing_id > 0 ) {
            $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tbl} WHERE id = %d AND quiz_id = %d", $existing_id, $quiz_id ) );
            if ( ! $existing ) {
                return false;
            }
            $wpdb->update( $tbl, $row, array( 'id' => $existing_id ) );
            return $existing_id;
        }
        $row['created_at'] = current_time( 'mysql' );
        $wpdb->insert( $tbl, $row );
        return (int) $wpdb->insert_id;
    }

    /**
     * Supprime un objectif (les questions rattachées sont déliées via NULL).
     *
     * @param int $quiz_id
     * @param int $objective_id
     *
     * @return bool
     */
    public function delete_qz_objective( $quiz_id, $objective_id ) {
        global $wpdb;
        $quiz_id      = (int) $quiz_id;
        $objective_id = (int) $objective_id;
        if ( $quiz_id <= 0 || $objective_id <= 0 ) {
            return false;
        }
        if ( $this->is_qz_quiz_locked( $quiz_id ) ) {
            return false;
        }
        $tbl_o = $this->get_qz_table( 'objectives' );
        $tbl_q = $this->get_qz_table( 'questions' );
        if ( '' === $tbl_o || '' === $tbl_q ) {
            return false;
        }
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tbl_o} WHERE id = %d AND quiz_id = %d", $objective_id, $quiz_id ) );
        if ( ! $existing ) {
            return false;
        }
        // Délier les questions
        $wpdb->update( $tbl_q, array( 'objective_id' => null, 'updated_at' => current_time( 'mysql' ) ), array( 'objective_id' => $objective_id, 'quiz_id' => $quiz_id ) );
        $wpdb->delete( $tbl_o, array( 'id' => $objective_id ) );
        return true;
    }

    /* ====================================================================
     *  Suppression / Archivage / Duplication                    [3.21.01]
     * ==================================================================== */

    /**
     * Archive un quiz (soft : status passe à 'archived', is_current = 0).
     *
     * @param int $quiz_id
     *
     * @return bool
     */
    public function archive_qz_quiz( $quiz_id ) {
        global $wpdb;
        $quiz_id = (int) $quiz_id;
        if ( $quiz_id <= 0 ) {
            return false;
        }
        $tbl = $this->get_qz_table( 'quizzes' );
        if ( '' === $tbl ) {
            return false;
        }
        $now = current_time( 'mysql' );
        $ok = $wpdb->update( $tbl, array(
            'status'      => self::ACDC_OF_QZ_STATUS_ARCHIVED,
            'is_current'  => 0,
            'archived_at' => $now,
            'updated_at'  => $now,
        ), array( 'id' => $quiz_id ) );

        if ( false !== $ok ) {
            $this->log_qz_event( array(
                'quiz_id'     => $quiz_id,
                'event_type'  => 'quiz_archived',
                'event_label' => sprintf( 'Archivage quiz #%d', $quiz_id ),
            ) );
            return true;
        }
        return false;
    }

    /**
     * Supprime définitivement un quiz et tout son contenu.
     *
     * Refuse si le quiz est verrouillé (présence de réponses apprenants).
     * Pour Qualiopi, un quiz utilisé doit être archivé, jamais supprimé.
     *
     * @param int $quiz_id
     *
     * @return bool
     */
    public function delete_qz_quiz( $quiz_id, $force = false ) {
        global $wpdb;
        $quiz_id = (int) $quiz_id;
        if ( $quiz_id <= 0 ) {
            return false;
        }
        if ( ! $force && $this->is_qz_quiz_locked( $quiz_id ) ) {
            return false;
        }

        $tbl_q  = $this->get_qz_table( 'quizzes' );
        $tbl_qu = $this->get_qz_table( 'questions' );
        $tbl_a  = $this->get_qz_table( 'answers' );
        $tbl_o  = $this->get_qz_table( 'objectives' );

        // Suppression en cascade applicative (les sessions / participants /
        // player_answers ne devraient pas exister puisque non verrouillé,
        // mais on les nettoie par sécurité).
        $tbl_s  = $this->get_qz_table( 'sessions' );
        $tbl_p  = $this->get_qz_table( 'participants' );
        $tbl_pa = $this->get_qz_table( 'player_answers' );

        // Récupérer les question_ids puis sessions
        $question_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$tbl_qu} WHERE quiz_id = %d", $quiz_id ) );
        $session_ids  = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$tbl_s} WHERE quiz_id = %d", $quiz_id ) );

        if ( ! empty( $session_ids ) ) {
            $sids_int = array_map( 'intval', $session_ids );
            $sids_ph  = implode( ',', array_fill( 0, count( $sids_int ), '%d' ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$tbl_pa} WHERE session_id IN ({$sids_ph})", $sids_int ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$tbl_p} WHERE session_id IN ({$sids_ph})", $sids_int ) );
            $wpdb->delete( $tbl_s, array( 'quiz_id' => $quiz_id ) );
        }
        if ( ! empty( $question_ids ) ) {
            $qids_int = array_map( 'intval', $question_ids );
            $qids_ph  = implode( ',', array_fill( 0, count( $qids_int ), '%d' ) );
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$tbl_a} WHERE question_id IN ({$qids_ph})", $qids_int ) );
        }
        $wpdb->delete( $tbl_qu, array( 'quiz_id' => $quiz_id ) );
        $wpdb->delete( $tbl_o, array( 'quiz_id' => $quiz_id ) );
        $wpdb->delete( $tbl_q, array( 'id' => $quiz_id ) );

        $this->log_qz_event( array(
            'quiz_id'     => $quiz_id,
            'event_type'  => 'quiz_deleted',
            'event_label' => sprintf( 'Suppression définitive quiz #%d', $quiz_id ),
        ) );

        return true;
    }

    /**
     * Duplique un quiz (et toutes ses questions, réponses, objectifs).
     *
     * @param int      $source_quiz_id
     * @param int|null $target_formation_id Si null, duplication dans la même formation.
     * @param string   $title_suffix        Suffixe ajouté au titre, par défaut " (copie)".
     *
     * @return int|false ID du nouveau quiz.
     */
    public function duplicate_qz_quiz( $source_quiz_id, $target_formation_id = null, $title_suffix = ' (copie)' ) {
        global $wpdb;
        $source = $this->get_qz_quiz( $source_quiz_id );
        if ( ! $source ) {
            return false;
        }
        $target_formation_id = ( null === $target_formation_id ) ? (int) $source->formation_id : (int) $target_formation_id;
        // formation_id = 0 est valide pour les quiz standalone (live sans formation)

        // Insérer la copie principale (statut draft, version_number = 1, is_current = 0)
        $now    = current_time( 'mysql' );
        $tbl_q  = $this->get_qz_table( 'quizzes' );
        $tbl_qu = $this->get_qz_table( 'questions' );
        $tbl_a  = $this->get_qz_table( 'answers' );
        $tbl_o  = $this->get_qz_table( 'objectives' );

        $new_title = mb_substr( (string) $source->title . (string) $title_suffix, 0, 255 );
        $row = array(
            'formation_id'        => $target_formation_id,
            'quiz_purpose'        => $source->quiz_purpose,
            'delivery_mode'       => $source->delivery_mode,
            'title'               => $new_title,
            'description'         => $source->description,
            'cover_image_id'      => $source->cover_image_id,
            'language'            => $source->language,
            'version_number'      => 1,
            'is_current'          => 0,
            'status'              => self::ACDC_OF_QZ_STATUS_DRAFT,
            'is_locked'           => 0,
            'scoring_mode'        => $source->scoring_mode,
            'pass_threshold'      => $source->pass_threshold,
            'level_thresholds_json' => $source->level_thresholds_json,
            'failure_action'      => $source->failure_action,
            'max_attempts'        => $source->max_attempts,
            'antifraud_time_limit'       => $source->antifraud_time_limit,
            'antifraud_no_back'          => $source->antifraud_no_back,
            'antifraud_visibility_check' => $source->antifraud_visibility_check,
            'antifraud_fullscreen'       => $source->antifraud_fullscreen,
            'data_retention_days' => $source->data_retention_days,
            'created_by'          => get_current_user_id() ?: 0,
            'created_at'          => $now,
            'updated_at'          => $now,
        );
        $wpdb->insert( $tbl_q, $row );
        $new_id = (int) $wpdb->insert_id;
        if ( $new_id <= 0 ) {
            return false;
        }

        // Dupliquer les objectifs (mapping ancien_id -> nouveau_id)
        $obj_map = array();
        $objectives = $this->get_qz_objectives_for_quiz( (int) $source->id );
        foreach ( $objectives as $obj ) {
            $wpdb->insert( $tbl_o, array(
                'quiz_id'        => $new_id,
                'label'          => $obj->label,
                'description'    => $obj->description,
                'pass_threshold' => $obj->pass_threshold,
                'sort_order'     => $obj->sort_order,
                'created_at'     => $now,
            ) );
            $obj_map[ (int) $obj->id ] = (int) $wpdb->insert_id;
        }

        // Dupliquer les questions et leurs réponses
        $questions = $this->get_qz_questions_for_quiz( (int) $source->id );
        foreach ( $questions as $q ) {
            $new_objective_id = null;
            if ( ! empty( $q->objective_id ) && isset( $obj_map[ (int) $q->objective_id ] ) ) {
                $new_objective_id = $obj_map[ (int) $q->objective_id ];
            }
            $wpdb->insert( $tbl_qu, array(
                'quiz_id'      => $new_id,
                'type'         => $q->type,
                'title'        => $q->title,
                'description'  => $q->description,
                'media_id'     => $q->media_id,
                'time_limit'   => $q->time_limit,
                'points_type'  => $q->points_type,
                'points_value' => $q->points_value,
                'is_scored'    => $q->is_scored,
                'objective_id' => $new_objective_id,
                'sort_order'   => $q->sort_order,
                'created_at'   => $now,
                'updated_at'   => $now,
            ) );
            $new_qid = (int) $wpdb->insert_id;
            if ( $new_qid > 0 && ! empty( $q->answers ) ) {
                foreach ( $q->answers as $a ) {
                    $wpdb->insert( $tbl_a, array(
                        'question_id'   => $new_qid,
                        'text'          => $a->text,
                        'media_id'      => $a->media_id,
                        'is_correct'    => $a->is_correct,
                        'feedback_text' => $a->feedback_text,
                        'sort_order'    => $a->sort_order,
                        'created_at'    => $now,
                    ) );
                }
            }
        }

        $this->log_qz_event( array(
            'quiz_id'     => $new_id,
            'event_type'  => 'quiz_duplicated',
            'event_label' => sprintf( 'Duplication depuis quiz #%d vers #%d', (int) $source->id, $new_id ),
            'event_payload' => array(
                'source_quiz_id'      => (int) $source->id,
                'target_formation_id' => $target_formation_id,
            ),
        ) );

        return $new_id;
    }

    /* ====================================================================
     *  Helpers — formations disponibles                         [3.21.01]
     * ==================================================================== */

    /**
     * Retourne la liste des formations actives du plugin (id + title), pour
     * peupler les sélecteurs.
     *
     * Méthode défensive : compatible avec la table `acdc_of_formations`
     * existante du plugin.
     *
     * @param string $search Filtre texte sur le titre (optionnel).
     *
     * @return array Liste d'objets {id, title}.
     */
    /**
     * ACDC hotfix45 — Retourne les groupes d'apprenants disponibles pour l'envoi async.
     *
     * Les apprenants ne sont PAS dans acdc_of_groups.learner_ids (champ non peuplé via l'UI).
     * Ils sont rattachés via acdc_of_training_registrations.group_id + learner_id.
     * On compte les apprenants par ce biais et on affiche tous les groupes
     * (même sans apprenant encore inscrit, pour ne pas masquer les groupes récents).
     *
     * @return object[]  Chaque objet a : id, name, formation_title, learner_count.
     */
    /**
     * ACDC hotfix53 — Retourne les compteurs "À traiter" par finalité pour le tableau de bord.
     *
     * - positioning / assessment : participants avec statut invited | opened | in_progress.
     * - live          : sessions actives (lobby ou in_progress).
     *
     * Appelé par le kernel via method_exists() pour ne pas créer de dépendance dure.
     *
     * @return array { quiz: int, positioning: int, assessment: int }
     */
    public function get_qz_pending_counts() {
        if ( empty( $this->qz_tables ) ) {
            return array( 'quiz' => 0, 'positioning' => 0, 'diagnostic' => 0, 'assessment' => 0 );
        }
        global $wpdb;
        $tbl_p = $this->get_qz_table( 'participants' );
        $tbl_s = $this->get_qz_table( 'sessions' );
        $tbl_q = $this->get_qz_table( 'quizzes' );

        // Participants async non complétés (positioning + assessment)
        // Exclure les participants relancés manuellement depuis moins de 24h (snooze)
        $col_reminder = ! empty( $wpdb->get_col( "SHOW COLUMNS FROM {$tbl_p} LIKE 'last_reminder_sent_at'" ) );
        $snooze_clause = $col_reminder
            ? "AND ( p.last_reminder_sent_at IS NULL OR p.last_reminder_sent_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) )"
            : '';
        $rows = $wpdb->get_results(
            "SELECT q.quiz_purpose, COUNT(*) AS cnt
             FROM {$tbl_p} p
             INNER JOIN {$tbl_s} s ON s.id = p.session_id
             INNER JOIN {$tbl_q} q ON q.id = s.quiz_id
             WHERE p.status IN ('invited', 'opened', 'in_progress')
               AND q.quiz_purpose IN ('positioning', 'diagnostic', 'assessment')
               {$snooze_clause}
             GROUP BY q.quiz_purpose"
        );

        $counts = array( 'quiz' => 0, 'positioning' => 0, 'diagnostic' => 0, 'assessment' => 0 );
        foreach ( (array) $rows as $row ) {
            if ( isset( $counts[ $row->quiz_purpose ] ) ) {
                $counts[ $row->quiz_purpose ] = (int) $row->cnt;
            }
        }

        // Sessions live actives
        $live_active = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tbl_s} WHERE status IN ('lobby', 'in_progress') AND delivery_mode = 'live_sync'"
        );
        $counts['quiz'] = $live_active;

        return $counts;
    }

        public function get_qz_available_groups() {
        global $wpdb;
        $tbl_g = $wpdb->prefix . 'acdc_of_groups';
        $tbl_f = $wpdb->prefix . 'acdc_of_formations';
        $tbl_r = $wpdb->prefix . 'acdc_of_training_registrations';
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $tbl_g ) );
        if ( $exists !== $tbl_g ) {
            return array();
        }
        $rows = $wpdb->get_results(
            "SELECT g.id, g.name,
                    f.title AS formation_title,
                    COUNT(DISTINCT r.learner_id) AS learner_count
             FROM {$tbl_g} g
             LEFT JOIN {$tbl_f} f ON f.id = g.formation_id
             LEFT JOIN {$tbl_r} r ON r.group_id = g.id
                                  AND r.learner_id IS NOT NULL
                                  AND r.learner_id > 0
             GROUP BY g.id
             ORDER BY g.name ASC
             LIMIT 500"
        );
        return is_array( $rows ) ? $rows : array();
    }

        public function get_qz_available_formations( $search = '' ) {
        global $wpdb;
        $tbl = $wpdb->prefix . 'acdc_of_formations';
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $tbl ) );
        if ( $exists !== $tbl ) {
            return array();
        }
        // 3.21.01.3 — On récupère aussi `code` et `modality` pour permettre au
        // rendu de différencier les variantes d'une même formation (présentiel /
        // distanciel / hybride / e-learning). On filtre les brouillons et les
        // formations désactivées pour ne pas polluer le sélecteur.
        $cols   = 'id, title, code, modality, is_active, is_draft';
        $where  = 'is_active = 1 AND is_draft = 0';
        $order  = 'title ASC, modality ASC';
        $search = trim( (string) $search );
        if ( '' !== $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT {$cols} FROM {$tbl} WHERE {$where} AND (title LIKE %s OR code LIKE %s) ORDER BY {$order} LIMIT 500",
                $like, $like
            ) );
        } else {
            $rows = $wpdb->get_results(
                "SELECT {$cols} FROM {$tbl} WHERE {$where} ORDER BY {$order} LIMIT 500"
            );
        }
        return is_array( $rows ) ? $rows : array();
    }

    /**
     * Construit le libellé complet d'une formation pour les sélecteurs
     * (création de quiz, dupliquer-depuis, etc.).
     *
     * Format aligné sur la convention déjà utilisée ailleurs dans le plugin :
     *   {code} | {title} ({modality})
     *
     * @since 3.21.01.3
     *
     * @param object|null $formation Ligne de la table acdc_of_formations.
     *
     * @return string
     */
    public function format_qz_formation_label( $formation ) {
        if ( ! is_object( $formation ) ) {
            return '';
        }
        $title    = isset( $formation->title ) ? (string) $formation->title : '';
        $code     = isset( $formation->code ) ? trim( (string) $formation->code ) : '';
        $modality = isset( $formation->modality ) ? trim( (string) $formation->modality ) : '';

        $parts = array();
        if ( '' !== $code ) {
            $parts[] = $code . ' |';
        }
        $parts[] = $title;
        if ( '' !== $modality ) {
            $parts[] = '(' . $modality . ')';
        }
        return implode( ' ', $parts );
    }

    /**
     * Récupère le titre d'une formation par son ID, ou chaîne vide si introuvable.
     *
     * @param int $formation_id
     *
     * @return string
     */
    public function get_qz_formation_title( $formation_id ) {
        global $wpdb;
        $formation_id = (int) $formation_id;
        if ( $formation_id <= 0 ) {
            return '';
        }
        $tbl = $wpdb->prefix . 'acdc_of_formations';
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $tbl ) );
        if ( $exists !== $tbl ) {
            return '';
        }
        // 3.21.01.3 — On retourne le label complet (code | titre (modalité))
        // au lieu du seul titre, pour cohérence avec format_qz_formation_label().
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, title, code, modality FROM {$tbl} WHERE id = %d",
            $formation_id
        ) );
        if ( ! $row ) {
            return '';
        }
        return $this->format_qz_formation_label( $row );
    }

    /* ====================================================================
     *  3.21.02 — Lifecycle : activation, dépublication, verrouillage
     * ==================================================================== */

    /**
     * Liste des messages d'erreur (bloquant) et warnings (non bloquant) qu'un
     * quiz doit corriger avant son activation.
     *
     * Erreurs structurelles (bloquantes) :
     *  - titre vide
     *  - aucune question
     *  - une question scorée sans énoncé
     *  - une question scorée sans bonne réponse (sauf types non scorés natifs)
     *  - formation rattachée disparue
     *
     * Warnings (non bloquants, simplement signalés) :
     *  - description vide
     *  - aucun objectif pédagogique
     *  - moins de 3 propositions sur une QCM (couvert mais inhabituel)
     *
     * @since 3.21.02
     *
     * @param int $quiz_id
     *
     * @return array {
     *   @type array  $errors   Liste des erreurs bloquantes (chaînes de caractères).
     *   @type array  $warnings Liste des avertissements (chaînes de caractères).
     * }
     */
    public function validate_qz_quiz_complete( $quiz_id ) {
        $errors   = array();
        $warnings = array();

        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) {
            $errors[] = __( "Quiz introuvable.", 'acdc-formation-saas' );
            return array( 'errors' => $errors, 'warnings' => $warnings );
        }

        // 1 — Titre du quiz.
        if ( '' === trim( (string) $quiz->title ) ) {
            $errors[] = __( "Le titre du quiz est obligatoire.", 'acdc-formation-saas' );
        }

        // 2 — Formation rattachée toujours présente.
        $formation_title = $this->get_qz_formation_title( (int) $quiz->formation_id );
        if ( '' === $formation_title ) {
            $errors[] = __( "La formation rattachée est introuvable. Le quiz a peut-être été créé sur une formation supprimée depuis.", 'acdc-formation-saas' );
        }

        // 3 — Au moins une question.
        $questions = $this->get_qz_questions_for_quiz( $quiz_id );
        if ( empty( $questions ) ) {
            $errors[] = __( "Le quiz ne contient aucune question.", 'acdc-formation-saas' );
        }

        // 4 — Validation question par question.
        $non_scored_types = array(
            self::ACDC_OF_QZ_QTYPE_POLL,
            self::ACDC_OF_QZ_QTYPE_OPEN_TEXT,
        );
        foreach ( $questions as $idx => $q ) {
            $position = (int) $idx + 1;
            $is_scored_type = ! in_array( $q->type, $non_scored_types, true );

            if ( '' === trim( (string) $q->title ) ) {
                $errors[] = sprintf(
                    /* translators: %d: question position (1-based) */
                    __( "Question %d : l'énoncé est vide.", 'acdc-formation-saas' ),
                    $position
                );
            }

            // Pour les questions scorées (toutes sauf poll et open_text),
            // on vérifie qu'il y a au moins une bonne réponse.
            if ( $is_scored_type && (int) $q->is_scored === 1 ) {
                $answers = $this->get_qz_answers_for_question_db( (int) $q->id );
                if ( empty( $answers ) ) {
                    $errors[] = sprintf(
                        /* translators: %d: question position */
                        __( "Question %d : aucune proposition de réponse.", 'acdc-formation-saas' ),
                        $position
                    );
                } else {
                    $has_correct = false;
                    foreach ( $answers as $a ) {
                        if ( (int) $a->is_correct === 1 ) {
                            $has_correct = true;
                            break;
                        }
                    }
                    if ( ! $has_correct ) {
                        $errors[] = sprintf(
                            /* translators: %d: question position */
                            __( "Question %d : aucune bonne réponse n'est cochée.", 'acdc-formation-saas' ),
                            $position
                        );
                    }
                }
            }
        }

        // 5 — Warnings (non bloquants).
        if ( '' === trim( (string) $quiz->description ) ) {
            $warnings[] = __( "La description du quiz est vide. C'est utile pour que les apprenants comprennent l'objectif.", 'acdc-formation-saas' );
        }
        $objectives = $this->get_qz_objectives_for_quiz( $quiz_id );
        if ( empty( $objectives ) ) {
            $warnings[] = __( "Aucun objectif pédagogique n'est rattaché au quiz. C'est facultatif mais utile pour le score par compétence et la traçabilité Qualiopi.", 'acdc-formation-saas' );
        }

        return array(
            'errors'   => $errors,
            'warnings' => $warnings,
        );
    }

    /**
     * Helper interne pour récupérer les réponses d'une question (utilisé par
     * la validation, pour ne pas dépendre d'une méthode du Render).
     *
     * @param int $question_id
     *
     * @return array
     */
    private function get_qz_answers_for_question_db( $question_id ) {
        return $this->get_qz_answers_for_question_public( $question_id );
    }

    /**
     * Version publique pour réutilisation depuis les écrans de résultats (3.21.04.1).
     * Retourne toutes les réponses d'une question, triées par sort_order.
     *
     * @param int $question_id
     * @return array
     */
    public function get_qz_answers_for_question_public( $question_id ) {
        global $wpdb;
        $tbl = $this->get_qz_table( 'answers' );
        if ( '' === $tbl || $question_id <= 0 ) {
            return array();
        }
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$tbl} WHERE question_id = %d ORDER BY sort_order ASC, id ASC",
            $question_id
        ) );
        return is_array( $rows ) ? $rows : array();
    }

    /**
     * Active un quiz (passage `draft` → `active`). Échoue si la validation
     * structurelle remonte des erreurs bloquantes.
     *
     * @since 3.21.02
     *
     * @param int $quiz_id
     *
     * @return array {
     *   @type bool   $success  true si bascule réussie.
     *   @type array  $errors   Erreurs bloquantes (vide si success=true).
     *   @type array  $warnings Avertissements (toujours retournés).
     * }
     */
    public function activate_qz_quiz( $quiz_id ) {
        global $wpdb;
        $quiz_id = (int) $quiz_id;
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) {
            return array( 'success' => false, 'errors' => array( __( 'Quiz introuvable.', 'acdc-formation-saas' ) ), 'warnings' => array() );
        }
        if ( (int) $quiz->is_locked === 1 ) {
            return array(
                'success'  => false,
                'errors'   => array( __( "Ce quiz est verrouillé. Pour modifier ou réactiver, créez une nouvelle version.", 'acdc-formation-saas' ) ),
                'warnings' => array(),
            );
        }

        $validation = $this->validate_qz_quiz_complete( $quiz_id );
        if ( ! empty( $validation['errors'] ) ) {
            return array(
                'success'  => false,
                'errors'   => $validation['errors'],
                'warnings' => $validation['warnings'],
            );
        }

        $tbl = $this->get_qz_table( 'quizzes' );
        $now = current_time( 'mysql' );
        $wpdb->update(
            $tbl,
            array(
                'status'       => self::ACDC_OF_QZ_STATUS_ACTIVE,
                'activated_at' => $now,
                'is_current'   => 1,
                'updated_at'   => $now,
            ),
            array( 'id' => $quiz_id )
        );

        return array(
            'success'  => true,
            'errors'   => array(),
            'warnings' => $validation['warnings'],
        );
    }

    /**
     * Dépublie un quiz actif (`active` → `draft`). Refuse si verrouillé.
     *
     * @since 3.21.02
     *
     * @param int $quiz_id
     *
     * @return bool
     */
    public function unpublish_qz_quiz( $quiz_id ) {
        global $wpdb;
        $quiz_id = (int) $quiz_id;
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) {
            return false;
        }
        if ( (int) $quiz->is_locked === 1 ) {
            return false;
        }
        if ( self::ACDC_OF_QZ_STATUS_ACTIVE !== $quiz->status ) {
            return false;
        }

        $tbl = $this->get_qz_table( 'quizzes' );
        $now = current_time( 'mysql' );
        $wpdb->update(
            $tbl,
            array(
                'status'     => self::ACDC_OF_QZ_STATUS_DRAFT,
                'updated_at' => $now,
            ),
            array( 'id' => $quiz_id )
        );

        return true;
    }

    /**
     * Verrouille un quiz suite à un premier usage réel (lancement live, envoi
     * async, passation papier). Idempotent : appelable plusieurs fois sans
     * effet de bord.
     *
     * @since 3.21.02
     *
     * @param int    $quiz_id
     * @param string $reason  Code court (ex: 'live_launch', 'async_dispatch', 'paper_session').
     *
     * @return bool true si le verrouillage a effectivement été appliqué (false si déjà verrouillé).
     */
    public function lock_qz_quiz_on_first_use( $quiz_id, $reason = 'first_use' ) {
        global $wpdb;
        $quiz_id = (int) $quiz_id;
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) {
            return false;
        }
        if ( (int) $quiz->is_locked === 1 ) {
            return false; // déjà verrouillé, rien à faire
        }

        $tbl = $this->get_qz_table( 'quizzes' );
        $now = current_time( 'mysql' );
        $wpdb->update(
            $tbl,
            array(
                'is_locked'     => 1,
                'locked_at'     => $now,
                'locked_reason' => mb_substr( (string) $reason, 0, 255 ),
                'updated_at'    => $now,
            ),
            array( 'id' => $quiz_id )
        );

        $this->log_qz_event( array(
            'quiz_id'     => $quiz_id,
            'event_type'  => 'quiz_locked',
            'event_label' => sprintf( 'Quiz verrouillé (raison : %s)', $reason ),
        ) );

        return true;
    }

    /**
     * Crée une nouvelle version d'un quiz verrouillé (ou non, l'utilisateur
     * peut aussi versionner volontairement). Duplique intégralement le quiz
     * et ses sous-objets, incrémente `version_number`, marque le nouveau
     * comme `is_current = 1` et l'ancien `is_current = 0`. Le chaînage est
     * maintenu via `replaces_quiz_id` / `replaced_by_quiz_id`.
     *
     * Le nouveau quiz repart en `draft` et `is_locked = 0`.
     *
     * @since 3.21.02
     *
     * @param int $source_quiz_id
     *
     * @return int|false ID du nouveau quiz, ou false en cas d'erreur.
     */
    public function create_qz_quiz_new_version( $source_quiz_id ) {
        global $wpdb;
        $source_quiz_id = (int) $source_quiz_id;
        $source = $this->get_qz_quiz( $source_quiz_id );
        if ( ! $source ) {
            return false;
        }

        // Si on est en train de versionner un quiz qui a déjà été remplacé,
        // on bascule plutôt sur la version courante de la lignée (cohérent).
        if ( (int) $source->replaced_by_quiz_id > 0 ) {
            $current = $this->get_qz_quiz( (int) $source->replaced_by_quiz_id );
            if ( $current ) {
                $source = $current;
                $source_quiz_id = (int) $current->id;
            }
        }

        // On duplique d'abord via duplicate_qz_quiz() pour profiter de la
        // logique de copie (questions, réponses, objectifs).
        $new_id = $this->duplicate_qz_quiz( $source_quiz_id, (int) $source->formation_id, '' );
        if ( ! $new_id ) {
            return false;
        }

        // Surcharge des champs de versioning : ce n'est PAS une copie indépendante,
        // c'est une nouvelle version dans la même lignée.
        $tbl = $this->get_qz_table( 'quizzes' );
        $now = current_time( 'mysql' );
        $new_version = (int) $source->version_number + 1;

        $wpdb->update(
            $tbl,
            array(
                'title'             => (string) $source->title, // on retire le suffixe " (copie)" hérité de duplicate_qz_quiz
                'version_number'    => $new_version,
                'is_current'        => 1,
                'replaces_quiz_id'  => $source_quiz_id,
                'updated_at'        => $now,
            ),
            array( 'id' => $new_id )
        );

        // Marquer l'ancienne version comme remplacée et plus courante.
        $wpdb->update(
            $tbl,
            array(
                'is_current'           => 0,
                'replaced_by_quiz_id'  => $new_id,
                'updated_at'           => $now,
            ),
            array( 'id' => $source_quiz_id )
        );

        $this->log_qz_event( array(
            'quiz_id'       => $new_id,
            'event_type'    => 'quiz_new_version',
            'event_label'   => sprintf( 'Nouvelle version v%d créée depuis #%d', $new_version, $source_quiz_id ),
            'event_payload' => array(
                'source_quiz_id' => $source_quiz_id,
                'version_number' => $new_version,
            ),
        ) );

        return (int) $new_id;
    }

    /**
     * Récupère la chaîne complète des versions d'un quiz (toutes les versions
     * appartenant à la même lignée, dans l'ordre croissant des versions).
     *
     * Algorithme : on remonte tant que `replaces_quiz_id` existe pour trouver
     * la racine, puis on redescend en suivant `replaced_by_quiz_id`.
     *
     * @since 3.21.02
     *
     * @param int $quiz_id
     *
     * @return array Tableau d'objets quiz (au moins 1 élément si quiz existe).
     */
    public function get_qz_quiz_versions_chain( $quiz_id ) {
        $quiz_id = (int) $quiz_id;
        $current = $this->get_qz_quiz( $quiz_id );
        if ( ! $current ) {
            return array();
        }

        // Remonter à la racine (max 50 itérations par sécurité).
        $iter = 0;
        while ( (int) $current->replaces_quiz_id > 0 && $iter < 50 ) {
            $parent = $this->get_qz_quiz( (int) $current->replaces_quiz_id );
            if ( ! $parent ) {
                break;
            }
            $current = $parent;
            $iter++;
        }

        // Redescendre en collectant tout.
        $chain = array( $current );
        $iter = 0;
        while ( (int) $current->replaced_by_quiz_id > 0 && $iter < 50 ) {
            $next = $this->get_qz_quiz( (int) $current->replaced_by_quiz_id );
            if ( ! $next ) {
                break;
            }
            $chain[] = $next;
            $current = $next;
            $iter++;
        }

        return $chain;
    }

    /**
     * Récupère uniquement la version courante d'une lignée.
     *
     * @since 3.21.02
     *
     * @param int $quiz_id  N'importe quel quiz de la lignée.
     *
     * @return object|null
     */
    public function get_qz_quiz_current_version( $quiz_id ) {
        $chain = $this->get_qz_quiz_versions_chain( $quiz_id );
        foreach ( $chain as $v ) {
            if ( (int) $v->is_current === 1 ) {
                return $v;
            }
        }
        // Fallback : la dernière de la chaîne (au cas où aucun is_current n'est défini)
        return ! empty( $chain ) ? end( $chain ) : null;
    }

    /* ====================================================================
     *  3.21.03.1 — Envoi asynchrone par e-mail
     * ==================================================================== */

    /**
     * Récupère les apprenants rattachés à une formation, via la table sessions.
     *
     * @since 3.21.03.1
     *
     * @param int   $formation_id
     * @param array $args  Optionnel : 'session_id' pour filtrer sur une session précise.
     *
     * @return array<object>
     */
    public function get_qz_learners_for_formation( $formation_id, $args = array() ) {
        global $wpdb;
        $formation_id = (int) $formation_id;
        if ( $formation_id <= 0 ) {
            return array();
        }

        $tbl_learners = $wpdb->prefix . 'acdc_of_learners';
        $tbl_sessions = $wpdb->prefix . 'acdc_of_sessions';

        $exists_l = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $tbl_learners ) );
        $exists_s = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $tbl_sessions ) );
        if ( $exists_l !== $tbl_learners || $exists_s !== $tbl_sessions ) {
            return array();
        }

        $session_filter = isset( $args['session_id'] ) ? (int) $args['session_id'] : 0;

        if ( $session_filter > 0 ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT l.id, l.first_name, l.last_name, l.email, l.session_id, s.formation_id
                 FROM {$tbl_learners} l
                 INNER JOIN {$tbl_sessions} s ON s.id = l.session_id
                 WHERE s.formation_id = %d AND l.session_id = %d AND l.email != ''
                 ORDER BY l.last_name ASC, l.first_name ASC
                 LIMIT 1000",
                $formation_id, $session_filter
            ) );
        } else {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT l.id, l.first_name, l.last_name, l.email, l.session_id, s.formation_id
                 FROM {$tbl_learners} l
                 INNER JOIN {$tbl_sessions} s ON s.id = l.session_id
                 WHERE s.formation_id = %d AND l.email != ''
                 ORDER BY l.last_name ASC, l.first_name ASC
                 LIMIT 1000",
                $formation_id
            ) );
        }

        return is_array( $rows ) ? $rows : array();
    }

    /**
     * Récupère les sessions actives d'une formation (utile pour le sélecteur de session).
     *
     * @since 3.21.03.1
     *
     * @param int $formation_id
     *
     * @return array<object>
     */
    public function get_qz_formation_sessions( $formation_id ) {
        global $wpdb;
        $formation_id = (int) $formation_id;
        if ( $formation_id <= 0 ) {
            return array();
        }
        $tbl = $wpdb->prefix . 'acdc_of_sessions';
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $tbl ) );
        if ( $exists !== $tbl ) {
            return array();
        }
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, formation_id, start_date, end_date, location, status FROM {$tbl}
             WHERE formation_id = %d ORDER BY start_date DESC LIMIT 100",
            $formation_id
        ) );
        return is_array( $rows ) ? $rows : array();
    }

    /**
     * Génère un token sécurisé pour un participant. Le token brut est retourné
     * pour insertion dans le mail ; seul son hash est stocké en BDD pour
     * limiter l'impact en cas de fuite des logs serveur.
     *
     * Format : 32 caractères hex précédés du préfixe finalité.
     *
     * @since 3.21.03.1
     *
     * @param string $purpose  acdc_of_qz_purpose (live/positioning/assessment)
     *
     * @return array { 'token' => string brut (à transmettre), 'hash' => string (à stocker) }
     */
    public function generate_qz_secure_token( $purpose = 'pos' ) {
        $prefix_map = array(
            self::ACDC_OF_QZ_PURPOSE_LIVE        => 'lv',
            self::ACDC_OF_QZ_PURPOSE_POSITIONING => 'pos',
            self::ACDC_OF_QZ_PURPOSE_DIAGNOSTIC  => 'dia',
            self::ACDC_OF_QZ_PURPOSE_ASSESSMENT  => 'eva',
        );
        $prefix = isset( $prefix_map[ $purpose ] ) ? $prefix_map[ $purpose ] : 'qz';
        $random = bin2hex( random_bytes( 16 ) ); // 32 caractères hex
        $token  = 'acdc_' . $prefix . '_' . $random;
        $hash   = hash( 'sha256', $token );
        return array( 'token' => $token, 'hash' => $hash );
    }

    /**
     * Crée une session async et ses participants, génère les tokens, envoie
     * les e-mails. Renvoie l'ID de la session async créée.
     *
     * @since 3.21.03.1
     *
     * @param int   $quiz_id
     * @param array $recipients  Liste de participants : tableaux ['email','first_name','last_name','learner_id'].
     * @param array $args        'expires_in_days', 'reminders_enabled', 'custom_message', 'formation_session_id'.
     *
     * @return int|WP_Error
     */
    public function create_qz_async_dispatch( $quiz_id, $recipients, $args = array() ) {
        global $wpdb;
        $quiz_id = (int) $quiz_id;
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) {
            return new WP_Error( 'quiz_not_found', __( 'Quiz introuvable.', 'acdc-formation-saas' ) );
        }
        if ( self::ACDC_OF_QZ_STATUS_ACTIVE !== $quiz->status ) {
            return new WP_Error( 'quiz_not_active', __( "Le quiz doit être actif pour être envoyé. Activez-le d'abord.", 'acdc-formation-saas' ) );
        }
        if ( empty( $recipients ) || ! is_array( $recipients ) ) {
            return new WP_Error( 'no_recipients', __( 'Aucun destinataire fourni.', 'acdc-formation-saas' ) );
        }

        $expires_in_days = isset( $args['expires_in_days'] ) ? max( 1, min( 90, (int) $args['expires_in_days'] ) ) : 14;
        $reminders_enabled = ! empty( $args['reminders_enabled'] );
        $custom_message  = isset( $args['custom_message'] ) ? wp_kses_post( $args['custom_message'] ) : '';
        $formation_session_id = isset( $args['formation_session_id'] ) ? (int) $args['formation_session_id'] : 0;

        $now = current_time( 'mysql' );
        $expires_at = date( 'Y-m-d H:i:s', strtotime( $now . " +{$expires_in_days} days" ) );

        // Création de la session async (table qz_sessions)
        $reminder_days = $reminders_enabled ? wp_json_encode( array( 1 ) ) : wp_json_encode( array() ); // J-1 avant expiration

        $tbl_sessions = $this->get_qz_table( 'sessions' );
        $ok = $wpdb->insert( $tbl_sessions, array(
            'quiz_id'              => $quiz_id,
            'formation_id'         => (int) $quiz->formation_id,
            'formation_session_id' => $formation_session_id > 0 ? $formation_session_id : null,
            'host_id'              => get_current_user_id(),
            'delivery_mode'        => self::ACDC_OF_QZ_DELIVERY_ASYNC_TOKEN,
            'status'               => 'active',
            'sent_at'              => $now,
            'expires_at'           => $expires_at,
            'reminder_days_json'   => $reminder_days,
            'created_at'           => $now,
            'updated_at'           => $now,
        ) );
        if ( ! $ok ) {
            return new WP_Error( 'session_insert_failed', __( "Impossible de créer la session d'envoi.", 'acdc-formation-saas' ) );
        }
        $session_id = (int) $wpdb->insert_id;

        // Création des participants + génération tokens + envoi mails
        $tbl_participants = $this->get_qz_table( 'participants' );
        $sent_count   = 0;
        $failed_count = 0;
        $send_errors  = array();

        foreach ( $recipients as $rcpt ) {
            $email = isset( $rcpt['email'] ) ? sanitize_email( $rcpt['email'] ) : '';
            if ( ! is_email( $email ) ) {
                $failed_count++;
                continue;
            }
            $first = isset( $rcpt['first_name'] ) ? sanitize_text_field( $rcpt['first_name'] ) : '';
            $last  = isset( $rcpt['last_name'] ) ? sanitize_text_field( $rcpt['last_name'] ) : '';
            $learner_id = isset( $rcpt['learner_id'] ) ? (int) $rcpt['learner_id'] : null;

            $tk = $this->generate_qz_secure_token( (string) $quiz->quiz_purpose );

            $wpdb->insert( $tbl_participants, array(
                'session_id'        => $session_id,
                'learner_id'        => $learner_id ? $learner_id : null,
                'qualiopi_traceable'=> $learner_id ? 1 : 0,
                'nickname'          => trim( $first . ' ' . $last ) !== '' ? trim( $first . ' ' . $last ) : $email,
                'email'             => $email,
                'full_name'         => trim( $first . ' ' . $last ),
                'secure_token'      => $tk['hash'],
                'token_expires_at'  => $expires_at,
                'status'            => 'invited',
                'invited_at'        => $now,
                'joined_at'         => $now,
                'updated_at'        => $now,
            ) );
            $participant_id = (int) $wpdb->insert_id;

            // Envoi mail
            $sent = $this->send_qz_async_invitation_email( array(
                'email'           => $email,
                'first_name'      => $first,
                'last_name'       => $last,
                'token'           => $tk['token'],
                'quiz'            => $quiz,
                'expires_at'      => $expires_at,
                'custom_message'  => $custom_message,
                'participant_id'  => $participant_id,
            ) );

            if ( $sent ) {
                $sent_count++;
            } else {
                $failed_count++;
                $send_errors[] = $email;
            }
        }

        // Log
        $this->log_qz_event( array(
            'quiz_id'       => $quiz_id,
            'event_type'    => 'async_dispatch_sent',
            'event_label'   => sprintf( 'Envoi async : %d destinataires (%d ok, %d échec)', count( $recipients ), $sent_count, $failed_count ),
            'event_payload' => array(
                'session_id'   => $session_id,
                'sent_count'   => $sent_count,
                'failed_count' => $failed_count,
                'send_errors'  => $send_errors,
            ),
        ) );

        // 3.21.03.1 — Décision 8 : verrouillage au PREMIER CLIC d'apprenant, pas à l'envoi.
        // On ne touche donc PAS à is_locked ici. Le verrouillage se déclenchera dans
        // consume_qz_async_token() au premier accès réel.

        // ACDC hotfix43 — Retourner les compteurs d'envoi pour permettre au handler
        // (handle_acdc_of_qz_send_async) d'afficher une notice précise.
        // Anciennement on retournait seulement $session_id (int), ce qui laissait
        // $sent_count / $failed_count / $send_errors indéfinis côté handler → PHP 8 TypeError.
        return array(
            'session_id'   => $session_id,
            'sent_count'   => $sent_count,
            'failed_count' => $failed_count,
            'send_errors'  => $send_errors,
        );
    }

    /**
     * Valide un token (clair) en cherchant son hash dans la BDD. Retourne le
     * participant + le quiz s'il est valide et non expiré, sinon retourne un
     * WP_Error avec un code explicite.
     *
     * @since 3.21.03.1
     *
     * @param string $token_clear  Le token brut tel que reçu par l'apprenant dans le mail.
     *
     * @return array|WP_Error  ['participant', 'session', 'quiz'] si OK.
     */
    public function validate_qz_async_token( $token_clear ) {
        global $wpdb;
        $token_clear = (string) $token_clear;
        if ( '' === trim( $token_clear ) ) {
            return new WP_Error( 'token_empty', __( 'Lien invalide.', 'acdc-formation-saas' ) );
        }
        $hash = hash( 'sha256', $token_clear );

        $tbl_participants = $this->get_qz_table( 'participants' );
        $participant = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$tbl_participants} WHERE secure_token = %s LIMIT 1",
            $hash
        ) );
        if ( ! $participant ) {
            return new WP_Error( 'token_not_found', __( 'Lien invalide ou inconnu.', 'acdc-formation-saas' ) );
        }
        if ( 'completed' === $participant->status ) {
            return new WP_Error( 'token_already_used', __( 'Vous avez déjà passé ce quiz.', 'acdc-formation-saas' ) );
        }
        // ACDC 3.25.113 — comparer en heure WP (token stocké en local).
        if ( 'expired' === $participant->status || ( ! empty( $participant->token_expires_at ) && strtotime( $participant->token_expires_at ) < current_time( 'timestamp' ) ) ) {
            return new WP_Error( 'token_expired', __( 'Ce lien a expiré. Contactez votre formateur si besoin.', 'acdc-formation-saas' ) );
        }
        if ( 'cancelled' === $participant->status ) {
            return new WP_Error( 'token_cancelled', __( 'Cette invitation a été annulée par votre formateur.', 'acdc-formation-saas' ) );
        }

        $tbl_sessions = $this->get_qz_table( 'sessions' );
        $session = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$tbl_sessions} WHERE id = %d",
            (int) $participant->session_id
        ) );
        if ( ! $session ) {
            return new WP_Error( 'session_not_found', __( "La session d'envoi est introuvable.", 'acdc-formation-saas' ) );
        }
        if ( 'cancelled' === $session->status ) {
            return new WP_Error( 'session_cancelled', __( 'Cette série a été annulée par votre formateur.', 'acdc-formation-saas' ) );
        }

        $quiz = $this->get_qz_quiz( (int) $session->quiz_id );
        if ( ! $quiz ) {
            return new WP_Error( 'quiz_not_found', __( 'Le quiz est introuvable.', 'acdc-formation-saas' ) );
        }
        if ( self::ACDC_OF_QZ_STATUS_ARCHIVED === $quiz->status ) {
            return new WP_Error( 'quiz_archived', __( 'Ce quiz a été retiré.', 'acdc-formation-saas' ) );
        }

        return array(
            'participant' => $participant,
            'session'     => $session,
            'quiz'        => $quiz,
        );
    }

    /**
     * À appeler quand un apprenant accède effectivement à la page de passation.
     * Marque le participant comme "opened" + déclenche le verrouillage du quiz
     * si c'est le PREMIER clic apprenant (décision 8).
     *
     * @since 3.21.03.1
     *
     * @param object $participant
     *
     * @return void
     */
    public function consume_qz_async_token( $participant ) {
        global $wpdb;
        $tbl_participants = $this->get_qz_table( 'participants' );
        $tbl_sessions     = $this->get_qz_table( 'sessions' );

        // Marque le participant comme ouvert (idempotent : si déjà opened ou en cours, on ne bouge pas en arrière)
        $now = current_time( 'mysql' );
        $current_status = (string) $participant->status;
        if ( 'invited' !== $current_status ) {
            return;
        }

        $wpdb->update(
            $tbl_participants,
            array(
                'status'     => 'opened',
                'opened_at'  => $now,
                'updated_at' => $now,
            ),
            array( 'id' => (int) $participant->id )
        );

        // 3.21.03.1 — Verrouillage au premier clic. On vérifie si c'est bien
        // le tout premier accès toute session confondue pour ce quiz.
        // Étape 1 : récupérer le quiz_id correspondant à la session du participant.
        $quiz_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT quiz_id FROM {$tbl_sessions} WHERE id = %d",
            (int) $participant->session_id
        ) );
        if ( $quiz_id <= 0 ) {
            return;
        }

        // Étape 2 : compter les autres participants déjà ouverts sur ce quiz.
        $any_other_opened = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl_participants} p
             INNER JOIN {$tbl_sessions} s ON s.id = p.session_id
             WHERE s.quiz_id = %d
               AND p.id != %d
               AND p.opened_at IS NOT NULL",
            $quiz_id,
            (int) $participant->id
        ) );

        if ( 0 === $any_other_opened ) {
            // C'est bien le premier clic apprenant pour ce quiz → on verrouille.
            $this->lock_qz_quiz_on_first_use( $quiz_id, 'async_first_click' );
        }
    }

    /**
     * Marque un participant comme ayant commencé (saisi sa première réponse).
     *
     * @since 3.21.03.1
     */
    public function mark_qz_async_started( $participant_id ) {
        global $wpdb;
        $tbl = $this->get_qz_table( 'participants' );
        $now = current_time( 'mysql' );
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$tbl} SET status = %s, started_at = COALESCE( started_at, %s ), updated_at = %s WHERE id = %d AND status IN ('invited','opened')",
            'in_progress', $now, $now, (int) $participant_id
        ) );
    }

    /**
     * Marque un participant comme ayant terminé.
     *
     * @since 3.21.03.1
     */
    public function mark_qz_async_completed( $participant_id, $score = null ) {
        global $wpdb;
        $tbl = $this->get_qz_table( 'participants' );
        $now = current_time( 'mysql' );
        $update = array(
            'status'       => 'completed',
            'completed_at' => $now,
            'updated_at'   => $now,
        );
        if ( null !== $score ) {
            $update['total_score_percentage'] = (float) $score;
            // ACDC 3.25.171 — Même règle que pour la passation en salle : le verdict est
            // arrêté au moment où le score l'est.
            $tbl_s   = $this->get_qz_table( 'sessions' );
            $quiz_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT s.quiz_id FROM {$tbl_s} s
                 INNER JOIN {$tbl} p ON p.session_id = s.id WHERE p.id = %d LIMIT 1",
                (int) $participant_id
            ) );
            $update['is_passed'] = ( (float) $score >= $this->qz_pass_threshold_for_quiz( $quiz_id ) ) ? 1 : 0;
        }
        // ACDC 3.25.110 — persister aussi le score brut (points) pour les passations async,
        // sinon le PDF de résultat et l'export CSV affichent « 0 pts ».
        $tbl_pa = $this->get_qz_table( 'player_answers' );
        if ( $tbl_pa ) {
            $update['total_score'] = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(score_earned),0) FROM {$tbl_pa} WHERE participant_id = %d", (int) $participant_id ) );
        }
        $wpdb->update( $tbl, $update, array( 'id' => (int) $participant_id ) );
    }

    /**
     * Construit l'URL publique de passation pour un token donné.
     *
     * @since 3.21.03.1
     *
     * @param string $token
     *
     * @return string
     */
    public function build_qz_async_public_url( $token ) {
        $page_id = (int) get_option( 'acdc_of_qz_async_public_page_id', 0 );
        $url = $page_id ? get_permalink( $page_id ) : home_url( '/acdc-quiz-public/' );
        return add_query_arg( 'token', rawurlencode( $token ), $url );
    }

    /**
     * Récupère les statistiques d'une session async (pour l'écran de suivi).
     *
     * @since 3.21.03.1
     */
    public function get_qz_async_session_stats( $session_id ) {
        global $wpdb;
        $tbl = $this->get_qz_table( 'participants' );
        $session_id = (int) $session_id;

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'invited'      THEN 1 ELSE 0 END) AS invited,
                SUM(CASE WHEN status = 'opened'       THEN 1 ELSE 0 END) AS opened,
                SUM(CASE WHEN status = 'in_progress'  THEN 1 ELSE 0 END) AS in_progress,
                SUM(CASE WHEN status = 'completed'    THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status = 'expired'      THEN 1 ELSE 0 END) AS expired
             FROM {$tbl} WHERE session_id = %d",
            $session_id
        ), ARRAY_A );

        return $row ? array_map( 'intval', $row ) : array(
            'total' => 0, 'invited' => 0, 'opened' => 0,
            'in_progress' => 0, 'completed' => 0, 'expired' => 0,
        );
    }

    /**
     * Récupère le total des apprenants ayant cliqué (opened, in_progress, completed)
     * sur toutes les sessions d'un quiz. Sert au compteur live "0/30 ont commencé".
     *
     * @since 3.21.03.1
     */
    public function count_qz_async_clicked_for_quiz( $quiz_id ) {
        global $wpdb;
        $tbl_p = $this->get_qz_table( 'participants' );
        $tbl_s = $this->get_qz_table( 'sessions' );
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl_p} p
             INNER JOIN {$tbl_s} s ON s.id = p.session_id
             WHERE s.quiz_id = %d AND p.status IN ('opened','in_progress','completed')",
            (int) $quiz_id
        ) );
    }

    /**
     * Récupère le total des apprenants invités (toutes sessions confondues) pour un quiz.
     *
     * @since 3.21.03.1
     */
    public function count_qz_async_invited_for_quiz( $quiz_id ) {
        global $wpdb;
        $tbl_p = $this->get_qz_table( 'participants' );
        $tbl_s = $this->get_qz_table( 'sessions' );
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl_p} p
             INNER JOIN {$tbl_s} s ON s.id = p.session_id
             WHERE s.quiz_id = %d",
            (int) $quiz_id
        ) );
    }

    /**
     * Récupère les sessions async d'un quiz, avec leurs stats.
     *
     * @since 3.21.03.1
     */
    public function get_qz_async_sessions_for_quiz( $quiz_id ) {
        global $wpdb;
        $tbl_s = $this->get_qz_table( 'sessions' );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$tbl_s}
             WHERE quiz_id = %d AND delivery_mode = %s
             ORDER BY sent_at DESC LIMIT 50",
            (int) $quiz_id, self::ACDC_OF_QZ_DELIVERY_ASYNC_TOKEN
        ) );
        return is_array( $rows ) ? $rows : array();
    }

    /**
     * Récupère les participants d'une session async.
     *
     * @since 3.21.03.1
     */
    public function get_qz_async_participants_for_session( $session_id ) {
        global $wpdb;
        $tbl = $this->get_qz_table( 'participants' );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$tbl} WHERE session_id = %d ORDER BY full_name ASC, email ASC",
            (int) $session_id
        ) );
        return is_array( $rows ) ? $rows : array();
    }

    /**
     * Prolonge la date d'expiration d'un participant donné (max 7 jours).
     *
     * @since 3.21.03.1
     */
    public function extend_qz_async_token( $participant_id, $extra_days ) {
        global $wpdb;
        $extra_days = max( 1, min( 7, (int) $extra_days ) );
        $tbl = $this->get_qz_table( 'participants' );
        $p = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl} WHERE id = %d", (int) $participant_id ) );
        if ( ! $p ) {
            return false;
        }
        if ( in_array( $p->status, array( 'completed', 'cancelled' ), true ) ) {
            return false;
        }
        $base = ( $p->token_expires_at && strtotime( $p->token_expires_at ) > time() )
            ? $p->token_expires_at
            : current_time( 'mysql' );
        $new_expires = date( 'Y-m-d H:i:s', strtotime( $base . " +{$extra_days} days" ) );
        $update = array(
            'token_expires_at' => $new_expires,
            'updated_at'       => current_time( 'mysql' ),
        );
        if ( 'expired' === $p->status ) {
            $update['status'] = 'invited';
        }
        $wpdb->update( $tbl, $update, array( 'id' => (int) $participant_id ) );
        return $new_expires;
    }

    /**
     * Annule un participant (le lien devient inactif).
     *
     * @since 3.21.03.1
     */
    public function cancel_qz_async_participant( $participant_id ) {
        global $wpdb;
        $tbl = $this->get_qz_table( 'participants' );
        $now = current_time( 'mysql' );
        $wpdb->update( $tbl, array(
            'status'     => 'cancelled',
            'updated_at' => $now,
        ), array( 'id' => (int) $participant_id ) );
        return true;
    }

    /**
     * Cron : marque les tokens expirés. Lancé par acdc_of_qz_cron_expirations.
     *
     * @since 3.21.03.1
     */
    public function process_qz_async_expirations() {
        global $wpdb;
        $tbl = $this->get_qz_table( 'participants' );
        $now = current_time( 'mysql' );
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$tbl} SET status = 'expired', updated_at = %s
             WHERE token_expires_at IS NOT NULL
               AND token_expires_at < %s
               AND status IN ('invited','opened')",
            $now, $now
        ) );
    }

    /**
     * Cron : envoie les rappels J-1 avant expiration.
     *
     * @since 3.21.03.1
     */
    public function process_qz_async_reminders() {
        global $wpdb;
        $tbl_p = $this->get_qz_table( 'participants' );
        $tbl_s = $this->get_qz_table( 'sessions' );

        $tbl_q = $this->get_qz_table( 'quizzes' );

        // Participants encore non terminés dont l'expiration est dans 24-48h
        $rows = $wpdb->get_results(
            "SELECT p.* FROM {$tbl_p} p
             INNER JOIN {$tbl_s} s ON s.id = p.session_id
             WHERE p.status IN ('invited','opened')
               AND p.reminder_count = 0
               AND p.token_expires_at IS NOT NULL
               AND p.token_expires_at BETWEEN NOW() + INTERVAL 23 HOUR AND NOW() + INTERVAL 25 HOUR
               AND s.reminder_days_json LIKE '%1%'
             LIMIT 200"
        );
        foreach ( (array) $rows as $p ) {
            // Re-générer un nouveau token clair n'est PAS possible (on n'a que le hash) ;
            // donc on doit régénérer un nouveau token, hash, et update le participant.
            // C'est en fait la stratégie la plus saine : le rappel utilise un nouveau lien.
            $session = $wpdb->get_row( $wpdb->prepare(
                "SELECT s.*, q.quiz_purpose FROM {$tbl_s} s
                 LEFT JOIN {$tbl_q} q ON q.id = s.quiz_id
                 WHERE s.id = %d",
                (int) $p->session_id
            ) );
            if ( ! $session ) {
                continue;
            }
            $tk = $this->generate_qz_secure_token( (string) $session->quiz_purpose );
            $wpdb->update(
                $tbl_p,
                array(
                    'secure_token'     => $tk['hash'],
                    'reminder_count'   => 1,
                    'last_reminder_at' => current_time( 'mysql' ),
                    'updated_at'       => current_time( 'mysql' ),
                ),
                array( 'id' => (int) $p->id )
            );
            $quiz = $this->get_qz_quiz( (int) $session->quiz_id );
            if ( $quiz ) {
                $this->send_qz_async_reminder_email( array(
                    'email'      => $p->email,
                    'first_name' => '',
                    'last_name'  => '',
                    'token'      => $tk['token'],
                    'quiz'       => $quiz,
                    'expires_at' => $p->token_expires_at,
                ) );
            }
        }
    }

    /**
     * Envoi du mail d'invitation. Renvoie true/false.
     *
     * @since 3.21.03.1
     */
    /**
     * ACDC hotfix57 — Envoi manuel de relance formateur → apprenant.
     * Distinct du rappel automatique (cron 24h avant expiration).
     * Retourne true si envoyé, false sinon.
     *
     * $args attend : email, first_name, last_name, quiz, passation_url,
     *               expires_at, participant_id, sender_name (optionnel)
     */
    public function send_qz_manual_reminder_email( $args ) {
        $template_path = ACDC_OF_SAAS_DIR . 'includes/quizzes/templates/email-async-relance.php';
        if ( ! file_exists( $template_path ) ) {
            return false;
        }
        $body = $this->render_qz_email_template( $template_path, $args );

        $purpose_subjects = array(
            'positioning' => '[Relance] Votre test de positionnement est en attente — %s',
            'diagnostic'  => '[Relance] Votre évaluation diagnostique est en attente — %s',
            'assessment'  => '[Relance] Votre évaluation des acquis est en attente — %s',
            'live'        => '[Relance] Votre quiz est en attente — %s',
        );
        $qz_purpose  = isset( $args['quiz']->quiz_purpose ) ? (string) $args['quiz']->quiz_purpose : 'live';
        $subject_tpl = isset( $purpose_subjects[ $qz_purpose ] ) ? $purpose_subjects[ $qz_purpose ] : $purpose_subjects['live'];
        $subject = sprintf( $subject_tpl, (string) $args['quiz']->title );

        return $this->send_qz_email( $args['email'], $subject, $body, $this->qz_recipient_display_name( $args ) );
    }

        public function send_qz_async_invitation_email( $args ) {
        $template_path = ACDC_OF_SAAS_DIR . 'includes/quizzes/templates/email-async-initial.php';
        if ( ! file_exists( $template_path ) ) {
            return false;
        }
        $body = $this->render_qz_email_template( $template_path, $args );
        // hotfix49 — Objet contextuel selon la finalité du quiz
        $purpose_subjects = array(
            'positioning' => '[%s] Vous êtes invité à passer un test de positionnement',
            'diagnostic'  => '[%s] Vous êtes invité à passer une évaluation diagnostique',
            'assessment'  => '[%s] Vous êtes invité à passer une évaluation des acquis',
            'live'        => '[%s] Vous êtes invité à passer un quiz',
        );
        $qz_purpose  = isset( $args['quiz']->quiz_purpose ) ? (string) $args['quiz']->quiz_purpose : 'live';
        $subject_tpl = isset( $purpose_subjects[ $qz_purpose ] ) ? $purpose_subjects[ $qz_purpose ] : $purpose_subjects['live'];
        $subject = sprintf( $subject_tpl, $this->get_qz_organisation_name() );
        return $this->send_qz_email( $args['email'], $subject, $body, $this->qz_recipient_display_name( $args ) );
    }

    /**
     * Envoi du mail de rappel.
     */
    public function send_qz_async_reminder_email( $args ) {
        $template_path = ACDC_OF_SAAS_DIR . 'includes/quizzes/templates/email-async-reminder.php';
        if ( ! file_exists( $template_path ) ) {
            return false;
        }
        $body = $this->render_qz_email_template( $template_path, $args );
        $subject = sprintf(
            /* translators: %s: quiz title */
            __( '[Rappel] Plus que 24h pour répondre — %s', 'acdc-formation-saas' ),
            (string) $args['quiz']->title
        );
        return $this->send_qz_email( $args['email'], $subject, $body, $this->qz_recipient_display_name( $args ) );
    }

    /**
     * Envoi du mail de confirmation après passation.
     */
    public function send_qz_async_confirmation_email( $args ) {
        $template_path = ACDC_OF_SAAS_DIR . 'includes/quizzes/templates/email-async-confirmation.php';
        if ( ! file_exists( $template_path ) ) {
            return false;
        }
        $body = $this->render_qz_email_template( $template_path, $args );
        // hotfix49 — Objet contextuel selon la finalité du quiz
        $purpose_confirms = array(
            'positioning' => 'Test de positionnement validé : %s',
            'diagnostic'  => 'Évaluation diagnostique validée : %s',
            'assessment'  => 'Évaluation des acquis validée : %s',
            'live'        => 'Quiz validé : %s',
        );
        $qz_purpose   = isset( $args['quiz']->quiz_purpose ) ? (string) $args['quiz']->quiz_purpose : 'live';
        $confirm_tpl  = isset( $purpose_confirms[ $qz_purpose ] ) ? $purpose_confirms[ $qz_purpose ] : $purpose_confirms['live'];
        $subject = sprintf( $confirm_tpl, (string) $args['quiz']->title );
        return $this->send_qz_email( $args['email'], $subject, $body, $this->qz_recipient_display_name( $args ) );
    }

    /**
     * ACDC 3.25.157 — Nom d'affichage du destinataire d'un e-mail quiz.
     * Les jeux d'arguments des quatre modèles portent first_name/last_name, et
     * parfois full_name ; on retient le premier renseigné.
     *
     * @param array $args Arguments passés au modèle d'e-mail.
     * @return string Nom d'affichage, ou '' si aucun n'est connu.
     */
    private function qz_recipient_display_name( $args ) {
        if ( ! is_array( $args ) ) {
            return '';
        }
        $full = isset( $args['full_name'] ) ? trim( (string) $args['full_name'] ) : '';
        if ( '' !== $full ) {
            return $full;
        }
        $first = isset( $args['first_name'] ) ? trim( (string) $args['first_name'] ) : '';
        $last  = isset( $args['last_name'] ) ? trim( (string) $args['last_name'] ) : '';
        return trim( $first . ' ' . $last );
    }

    /**
     * Wrapper d'envoi par wp_mail() avec headers HTML.
     */
    private function send_qz_email( $to, $subject, $html_body, $to_name = '' ) {
        /* ACDC 3.25.157 — Le nom du destinataire n'était jamais transmis à wp_mail() :
           l'e-mail partait vers l'adresse nue, et l'archive — fidèle à ce qu'elle
           reçoit — n'affichait que l'adresse, alors que le nom saisi apparaissait
           partout ailleurs. On le passe désormais au format RFC « Nom <adresse> ». */
        $to_name = trim( (string) $to_name );
        if ( '' !== $to_name && is_string( $to ) && false === strpos( $to, '<' ) ) {
            /* Les virgules et chevrons casseraient l'en-tête : on les retire, et on
               entoure de guillemets pour rester valide avec un nom accentué. */
            $clean_name = trim( str_replace( array( ',', '<', '>', '"' ), ' ', $to_name ) );
            if ( '' !== $clean_name ) {
                $to = '"' . $clean_name . '" <' . $to . '>';
            }
        }
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        $from_name  = $this->get_qz_organisation_name();
        $from_email = $this->get_qz_organisation_email();
        if ( '' !== $from_email ) {
            $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
        }
        // Capture l'erreur PHPMailer si wp_mail échoue
        $mail_error = null;
        $error_handler = function( $wp_error ) use ( &$mail_error ) {
            $mail_error = $wp_error->get_error_message();
        };
        add_action( 'wp_mail_failed', $error_handler );
        /* ACDC 3.25.161 — Attribution d'archive : sans ces en-têtes, l'envoi
           s'affiche « plugin / wp_mail » dans l'archive, sans module identifiable. */
        $headers[] = 'X-ACDC-Source-Module: quizzes';
        $headers[] = 'X-ACDC-Source-Action: quiz_email';
        $headers[] = 'X-ACDC-Email-Category: quizzes';
        $sent = wp_mail( $to, $subject, $html_body, $headers );
        remove_action( 'wp_mail_failed', $error_handler );
        if ( ! $sent && $mail_error ) {
            error_log( '[ACDC QZ] wp_mail échec vers ' . $to . ' : ' . $mail_error );
        }
        return $sent;
    }

    /**
     * Rendu d'un template e-mail avec injection des variables.
     */
    private function render_qz_email_template( $template_path, $args ) {
        $organisation = array(
            'name'    => $this->get_qz_organisation_name(),
            'email'   => $this->get_qz_organisation_email(),
            'phone'   => $this->get_qz_organisation_phone(),
            'address' => $this->get_qz_organisation_address(),
            'website' => $this->get_qz_organisation_website(),
            'tagline' => $this->get_qz_organisation_tagline(),
            'logo_url'=> $this->get_qz_organisation_logo_url(),
        );
        ob_start();
        // Variables disponibles dans le template
        $email           = $args['email'] ?? '';
        $first_name      = $args['first_name'] ?? '';
        $last_name       = $args['last_name'] ?? '';
        $token           = $args['token'] ?? '';
        $quiz            = $args['quiz'] ?? null;
        $expires_at      = $args['expires_at'] ?? '';
        $custom_message  = $args['custom_message'] ?? '';
        $passation_url   = isset( $args['token'] ) ? $this->build_qz_async_public_url( $args['token'] ) : '';
        $sender_name     = $args['sender_name'] ?? '';   // relance manuelle
        include $template_path;
        return (string) ob_get_clean();
    }

    /* ====================================================================
     *  3.21.03.1 — Helpers identité organisme
     *  3.21.03.1-hotfix3 — Lit depuis la fiche acdc_of_company_profile
     *  (déjà gérée par le plugin, pas de duplication d'options).
     * ==================================================================== */

    /**
     * Charge la fiche organisme. Cache statique pour éviter les multiples
     * appels à get_option() pendant un même envoi groupé.
     *
     * @return array
     */
    private function get_qz_company_profile() {
        static $cached = null;
        if ( null === $cached ) {
            $opt = get_option( 'acdc_of_company_profile', array() );
            $cached = is_array( $opt ) ? $opt : array();
        }
        return $cached;
    }

    public function get_qz_organisation_name() {
        $p = $this->get_qz_company_profile();
        $val = isset( $p['enterprise'] ) ? trim( (string) $p['enterprise'] ) : '';
        return '' !== $val ? $val : (string) get_bloginfo( 'name' );
    }
    public function get_qz_organisation_tagline() {
        $p = $this->get_qz_company_profile();
        return isset( $p['offer'] ) ? (string) $p['offer'] : '';
    }
    public function get_qz_organisation_email() {
        $p = $this->get_qz_company_profile();
        $val = isset( $p['enterprise_contact_email'] ) ? trim( (string) $p['enterprise_contact_email'] ) : '';
        return '' !== $val ? $val : (string) get_option( 'admin_email', '' );
    }
    public function get_qz_organisation_phone() {
        $p = $this->get_qz_company_profile();
        return isset( $p['enterprise_contact_phone'] ) ? (string) $p['enterprise_contact_phone'] : '';
    }
    public function get_qz_organisation_address() {
        $p = $this->get_qz_company_profile();
        $parts = array();
        if ( ! empty( $p['address'] ) )      { $parts[] = (string) $p['address']; }
        if ( ! empty( $p['postal_code'] ) || ! empty( $p['city'] ) ) {
            $line2 = trim( ( $p['postal_code'] ?? '' ) . ' ' . ( $p['city'] ?? '' ) );
            if ( '' !== $line2 ) { $parts[] = $line2; }
        }
        return implode( ' — ', $parts );
    }
    public function get_qz_organisation_logo_url() {
        $p = $this->get_qz_company_profile();
        $url = isset( $p['logo_url'] ) ? trim( (string) $p['logo_url'] ) : '';
        if ( '' !== $url ) {
            return $url;
        }
        // Pas de fallback vers une image inexistante : on retourne vide,
        // le template e-mail masquera la balise <img> proprement.
        return '';
    }
    public function get_qz_organisation_website() {
        $p = $this->get_qz_company_profile();
        $val = isset( $p['website_url'] ) ? trim( (string) $p['website_url'] ) : '';
        return '' !== $val ? $val : home_url( '/' );
    }

    /* ====================================================================
     * ACDC 3.21.03.2-a — Liste des quiz visibles par un formateur donné.
     * Logique : un formateur voit les quiz dont la formation est animée par lui via au moins
     * une session (acdc_of_sessions.trainer_id) — quel que soit l'auteur du quiz, et
     * indépendamment de l'état (draft / active / archived) ou du verrouillage.
     * ==================================================================== */

    /**
     * Retourne les quiz visibles pour un formateur donné.
     *
     * @param int   $trainer_id ID du formateur connecté.
     * @param array $args {
     *     Filtres optionnels.
     *     @type string $purpose      'live' | 'positioning' | 'assessment' (filtre par finalité).
     *     @type string $status       'draft' | 'active' | 'archived' (filtre par statut).
     *     @type string $search       Recherche dans le titre.
     *     @type bool   $only_current Si true, ne retourne que les versions courantes (is_current=1).
     *     @type int    $limit        Pagination — par défaut 50.
     *     @type int    $offset       Pagination.
     *     @type string $orderby      'updated_at' (défaut), 'title', 'created_at'.
     *     @type string $order        'DESC' (défaut) ou 'ASC'.
     * }
     * @return array Tableau d'objets quiz (peut être vide).
     */
    public function get_qz_quizzes_for_trainer( $trainer_id, $args = array() ) {
        global $wpdb;

        $trainer_id = (int) $trainer_id;
        if ( $trainer_id <= 0 ) {
            return array();
        }

        $defaults = array(
            'purpose'             => '',
            'status'              => '',
            'search'              => '',
            'only_current'        => true,
            'limit'               => 50,
            'offset'              => 0,
            'orderby'             => 'updated_at',
            'order'               => 'DESC',
            'fallback_all_active' => true, /* 3.21.03.2-b — Si filtre par sessions vide, voir tous les quiz actifs. */
        );
        $args = array_merge( $defaults, $args );

        $tbl_quizzes  = $this->get_qz_table( 'quizzes' );
        $tbl_sessions = $wpdb->prefix . 'acdc_of_sessions';

        // Whitelist orderby pour éviter SQL injection
        $allowed_orderby = array( 'updated_at', 'created_at', 'title' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'updated_at';
        $order   = ( strtoupper( $args['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

        // Tentative 1 : filtrage strict par sessions animées
        $rows = $this->qz_query_quizzes_for_trainer_internal( $trainer_id, $args, $orderby, $order, true );

        // Tentative 2 (fallback) : si vide ET fallback autorisé, tous les quiz dont la formation est visible
        // pour ce formateur. Pour un formateur unique, le fallback affichera tous les quiz du SaaS.
        if ( empty( $rows ) && $args['fallback_all_active'] ) {
            $rows = $this->qz_query_quizzes_for_trainer_internal( $trainer_id, $args, $orderby, $order, false );
        }

        return is_array( $rows ) ? $rows : array();
    }

    /**
     * Helper interne — exécute la requête de listage avec ou sans filtre par session animée.
     *
     * @param int    $trainer_id
     * @param array  $args
     * @param string $orderby   Colonne de tri (whitelistée par l'appelant).
     * @param string $order     'ASC' ou 'DESC'.
     * @param bool   $with_session_filter  Si true, applique le EXISTS sur sessions.trainer_id.
     * @return array
     */
    private function qz_query_quizzes_for_trainer_internal( $trainer_id, $args, $orderby, $order, $with_session_filter ) {
        global $wpdb;
        $tbl_quizzes  = $this->get_qz_table( 'quizzes' );
        $tbl_sessions = $wpdb->prefix . 'acdc_of_sessions';

        $where  = array();
        $params = array();

        if ( $with_session_filter ) {
            $where[]  = 'EXISTS (SELECT 1 FROM ' . $tbl_sessions . ' s WHERE s.formation_id = q.formation_id AND s.trainer_id = %d)';
            $params[] = $trainer_id;
        }

        if ( $args['only_current'] ) {
            $where[] = 'q.is_current = 1';
        }
        if ( ! empty( $args['purpose'] ) ) {
            $where[]  = 'q.quiz_purpose = %s';
            $params[] = (string) $args['purpose'];
        }
        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'q.status = %s';
            $params[] = (string) $args['status'];
        }
        if ( ! empty( $args['search'] ) ) {
            $where[]  = 'q.title LIKE %s';
            $params[] = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
        }

        $where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $sql = 'SELECT q.* FROM ' . $tbl_quizzes . ' q ' . $where_clause
             . ' ORDER BY q.' . $orderby . ' ' . $order
             . ' LIMIT %d OFFSET %d';
        $params[] = (int) $args['limit'];
        $params[] = (int) $args['offset'];

        if ( empty( $params ) ) {
            return $wpdb->get_results( $sql );
        }
        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
    }

    /**
     * Compte les quiz visibles par un formateur donné (pour pagination ou compteurs).
     *
     * @param int   $trainer_id
     * @param array $args  Mêmes filtres que get_qz_quizzes_for_trainer (sauf limit/offset/orderby).
     * @return int
     */
    public function count_qz_quizzes_for_trainer( $trainer_id, $args = array() ) {
        global $wpdb;

        $trainer_id = (int) $trainer_id;
        if ( $trainer_id <= 0 ) {
            return 0;
        }

        $defaults = array(
            'purpose'             => '',
            'status'              => '',
            'search'              => '',
            'only_current'        => true,
            'fallback_all_active' => true,
        );
        $args = array_merge( $defaults, $args );

        // Tentative 1 : filtrage strict
        $count = $this->qz_count_quizzes_for_trainer_internal( $trainer_id, $args, true );

        // Tentative 2 (fallback) : sans filtre par session si la première est vide
        if ( 0 === $count && $args['fallback_all_active'] ) {
            $count = $this->qz_count_quizzes_for_trainer_internal( $trainer_id, $args, false );
        }

        return $count;
    }

    /**
     * Helper interne pour count avec ou sans filtre par session animée.
     */
    private function qz_count_quizzes_for_trainer_internal( $trainer_id, $args, $with_session_filter ) {
        global $wpdb;
        $tbl_quizzes  = $this->get_qz_table( 'quizzes' );
        $tbl_sessions = $wpdb->prefix . 'acdc_of_sessions';

        $where  = array();
        $params = array();

        if ( $with_session_filter ) {
            $where[]  = 'EXISTS (SELECT 1 FROM ' . $tbl_sessions . ' s WHERE s.formation_id = q.formation_id AND s.trainer_id = %d)';
            $params[] = $trainer_id;
        }
        if ( $args['only_current'] ) {
            $where[] = 'q.is_current = 1';
        }
        if ( ! empty( $args['purpose'] ) ) {
            $where[]  = 'q.quiz_purpose = %s';
            $params[] = (string) $args['purpose'];
        }
        if ( ! empty( $args['status'] ) ) {
            $where[]  = 'q.status = %s';
            $params[] = (string) $args['status'];
        }
        if ( ! empty( $args['search'] ) ) {
            $where[]  = 'q.title LIKE %s';
            $params[] = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
        }

        $where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
        $sql = 'SELECT COUNT(*) FROM ' . $tbl_quizzes . ' q ' . $where_clause;

        if ( empty( $params ) ) {
            return (int) $wpdb->get_var( $sql );
        }
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
    }
    /* ====================================================================
     * ACDC 3.21.04.1 — Méthodes Core d'agrégation des résultats.
     * Ces méthodes sont publiques pour être réutilisables depuis l'admin
     * et le portail formateur sans duplication de logique métier.
     * ==================================================================== */

    /**
     * Liste les sessions d'envoi (envoi async ou live) d'un formateur ou tous.
     * Une "session" ici = un envoi groupé acdc_of_qz_sessions, pas une session de formation.
     *
     * @param array $args {
     *     @type int    $trainer_id   Si > 0, filtre sur les sessions liées à des formations animées.
     *     @type int    $quiz_id      Si > 0, filtre sur ce quiz.
     *     @type int    $formation_id Si > 0, filtre sur cette formation.
     *     @type string $status       'draft', 'sent', 'completed', etc.
     *     @type int    $limit
     *     @type int    $offset
     *     @type bool   $fallback_all_active  Si trainer_id défini et résultat vide, fallback sans filtre formateur.
     * }
     * @return array Sessions enrichies avec quiz, formation, et stats agrégées (count_invited, count_completed, avg_score).
     */
    public function get_qz_dispatch_sessions( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'trainer_id'          => 0,
            'quiz_id'             => 0,
            'formation_id'        => 0,
            'status'              => '',
            'limit'               => 50,
            'offset'              => 0,
            'orderby'             => 'sent_at',
            'order'               => 'DESC',
            'fallback_all_active' => true,
        );
        $args = array_merge( $defaults, $args );

        $allowed_orderby = array( 'sent_at', 'created_at', 'expires_at' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'sent_at';
        $order   = ( strtoupper( $args['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

        // Tentative 1 : filtrée par formateur
        $rows = $this->qz_query_dispatch_sessions_internal( $args, $orderby, $order, true );
        if ( empty( $rows ) && $args['fallback_all_active'] && (int) $args['trainer_id'] > 0 ) {
            $rows = $this->qz_query_dispatch_sessions_internal( $args, $orderby, $order, false );
        }
        return $rows;
    }

    /**
     * Helper interne — construit la requête de listage des sessions d'envoi.
     */
    private function qz_query_dispatch_sessions_internal( $args, $orderby, $order, $with_trainer_filter ) {
        global $wpdb;
        $tbl_qz_sessions = $this->get_qz_table( 'sessions' );
        $tbl_qz_quizzes  = $this->get_qz_table( 'quizzes' );
        $tbl_qz_parts    = $this->get_qz_table( 'participants' );
        $tbl_formations  = $wpdb->prefix . 'acdc_of_formations';
        $tbl_of_sessions = $wpdb->prefix . 'acdc_of_sessions';
        $tbl_trainers    = $this->trainer_table;

        $where  = array();
        $params = array();

        if ( $with_trainer_filter && (int) $args['trainer_id'] > 0 ) {
            $where[]  = 'EXISTS (SELECT 1 FROM ' . $tbl_of_sessions . ' s2 WHERE s2.formation_id = s.formation_id AND s2.trainer_id = %d)';
            $params[] = (int) $args['trainer_id'];
        }
        if ( (int) $args['quiz_id'] > 0 ) {
            $where[]  = 's.quiz_id = %d';
            $params[] = (int) $args['quiz_id'];
        }
        if ( (int) $args['formation_id'] > 0 ) {
            $where[]  = 's.formation_id = %d';
            $params[] = (int) $args['formation_id'];
        }
        if ( ! empty( $args['status'] ) ) {
            $where[]  = 's.status = %s';
            $params[] = (string) $args['status'];
        }
        // Exclure les drafts par défaut — un envoi non envoyé n'a pas de résultat à afficher.
        $where[] = "s.status != 'draft'";

        $where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $sql = "
            SELECT
                s.*,
                q.title AS quiz_title,
                q.quiz_purpose,
                q.delivery_mode AS quiz_delivery_mode,
                q.pass_threshold AS quiz_pass_threshold,
                f.title AS formation_title,
                f.code AS formation_code,
                f.modality AS formation_modality,
                /* ACDC 3.25.171 — Nom du formateur. La colonne « Formateur » lisait
                   s.created_by : ce champ n'existe PAS sur la table des sessions de
                   passation (il appartient à la table des quiz). La colonne était donc
                   vide sur les quatre écrans de résultats, et « Animé par » l'était
                   aussi sur le PDF. Le formateur d'une passation, c'est celui de la
                   séance de formation à laquelle elle est rattachée. */
                TRIM(CONCAT(COALESCE(tr.first_name,''), ' ', COALESCE(tr.last_name,''))) AS trainer_name,
                (SELECT COUNT(*) FROM {$tbl_qz_parts} p WHERE p.session_id = s.id) AS count_invited,
                (SELECT COUNT(*) FROM {$tbl_qz_parts} p WHERE p.session_id = s.id AND p.status = 'completed') AS count_completed,
                (SELECT AVG(p.total_score_percentage) FROM {$tbl_qz_parts} p WHERE p.session_id = s.id AND p.status = 'completed' AND p.total_score_percentage IS NOT NULL) AS avg_score
            FROM {$tbl_qz_sessions} s
            INNER JOIN {$tbl_qz_quizzes} q ON q.id = s.quiz_id
            LEFT JOIN {$tbl_formations} f ON f.id = s.formation_id
            LEFT JOIN {$tbl_of_sessions} fsx ON fsx.id = s.formation_session_id
            LEFT JOIN {$tbl_trainers} tr ON tr.id = fsx.trainer_id
            {$where_clause}
            ORDER BY s.{$orderby} {$order}
            LIMIT %d OFFSET %d
        ";
        $params[] = (int) $args['limit'];
        $params[] = (int) $args['offset'];

        if ( empty( $params ) ) {
            return $wpdb->get_results( $sql );
        }
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        return is_array( $rows ) ? $rows : array();
    }

    /**
     * Récupère une session d'envoi par son ID, enrichie comme dans la liste.
     *
     * @param int $session_id
     * @return object|null
     */
    public function get_qz_dispatch_session( $session_id ) {
        global $wpdb;
        $session_id = (int) $session_id;
        if ( $session_id <= 0 ) {
            return null;
        }
        $rows = $this->qz_query_dispatch_sessions_internal(
            array( 'trainer_id' => 0, 'quiz_id' => 0, 'formation_id' => 0, 'status' => '', 'limit' => 1, 'offset' => 0 ),
            'sent_at', 'DESC', false
        );
        // Re-requête ciblée par ID pour éviter de scanner toute la table
        $tbl_qz_sessions = $this->get_qz_table( 'sessions' );
        $tbl_qz_quizzes  = $this->get_qz_table( 'quizzes' );
        $tbl_qz_parts    = $this->get_qz_table( 'participants' );
        $tbl_formations  = $wpdb->prefix . 'acdc_of_formations';
        $tbl_of_sessions = $wpdb->prefix . 'acdc_of_sessions';
        $tbl_trainers    = $this->trainer_table;
        $sql = "
            SELECT
                s.*,
                q.title AS quiz_title,
                q.quiz_purpose,
                q.delivery_mode AS quiz_delivery_mode,
                q.pass_threshold AS quiz_pass_threshold,
                f.title AS formation_title,
                f.code AS formation_code,
                f.modality AS formation_modality,
                /* ACDC 3.25.171 — Nom du formateur. La colonne « Formateur » lisait
                   s.created_by : ce champ n'existe PAS sur la table des sessions de
                   passation (il appartient à la table des quiz). La colonne était donc
                   vide sur les quatre écrans de résultats, et « Animé par » l'était
                   aussi sur le PDF. Le formateur d'une passation, c'est celui de la
                   séance de formation à laquelle elle est rattachée. */
                TRIM(CONCAT(COALESCE(tr.first_name,''), ' ', COALESCE(tr.last_name,''))) AS trainer_name,
                (SELECT COUNT(*) FROM {$tbl_qz_parts} p WHERE p.session_id = s.id) AS count_invited,
                (SELECT COUNT(*) FROM {$tbl_qz_parts} p WHERE p.session_id = s.id AND p.status = 'completed') AS count_completed,
                (SELECT AVG(p.total_score_percentage) FROM {$tbl_qz_parts} p WHERE p.session_id = s.id AND p.status = 'completed' AND p.total_score_percentage IS NOT NULL) AS avg_score
            FROM {$tbl_qz_sessions} s
            INNER JOIN {$tbl_qz_quizzes} q ON q.id = s.quiz_id
            LEFT JOIN {$tbl_formations} f ON f.id = s.formation_id
            LEFT JOIN {$tbl_of_sessions} fsx ON fsx.id = s.formation_session_id
            LEFT JOIN {$tbl_trainers} tr ON tr.id = fsx.trainer_id
            WHERE s.id = %d
            LIMIT 1
        ";
        return $wpdb->get_row( $wpdb->prepare( $sql, $session_id ) );
    }

    /**
     * Vérifie qu'un formateur a bien le droit de voir cette session d'envoi
     * (c'est-à-dire qu'au moins une session de formation associée est animée par lui).
     * Avec fallback identique à get_qz_quizzes_for_trainer : si aucune session de ce
     * formateur n'a trainer_id rempli, on retombe sur tous les envois.
     *
     * @param int $session_id
     * @param int $trainer_id
     * @return bool
     */
    public function can_qz_trainer_view_dispatch_session( $session_id, $trainer_id ) {
        global $wpdb;
        $session_id = (int) $session_id;
        $trainer_id = (int) $trainer_id;
        if ( $session_id <= 0 || $trainer_id <= 0 ) {
            return false;
        }
        $tbl_qz_sessions = $this->get_qz_table( 'sessions' );
        $tbl_of_sessions = $wpdb->prefix . 'acdc_of_sessions';

        // Tentative 1 : strict
        $allowed = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl_qz_sessions} s
             WHERE s.id = %d AND EXISTS (
                SELECT 1 FROM {$tbl_of_sessions} ofs
                WHERE ofs.formation_id = s.formation_id AND ofs.trainer_id = %d
             )",
            $session_id, $trainer_id
        ) );
        if ( $allowed > 0 ) {
            return true;
        }
        // Tentative 2 : fallback (si formateur unique sans trainer_id propagé)
        $has_any_filtered = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl_qz_sessions} s
             WHERE EXISTS (
                SELECT 1 FROM {$tbl_of_sessions} ofs
                WHERE ofs.formation_id = s.formation_id AND ofs.trainer_id = %d
             ) LIMIT 1",
            $trainer_id
        ) );
        if ( 0 === $has_any_filtered ) {
            // Aucune session filtrée → on accepte toutes les sessions existantes
            $exists = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tbl_qz_sessions} WHERE id = %d", $session_id
            ) );
            return $exists > 0;
        }
        return false;
    }

    /**
     * Liste les participants d'une session d'envoi avec leurs stats.
     * Inclut tous les statuts (pending, invited, opened, in_progress, completed, expired, cancelled).
     */
    public function get_qz_participants_for_dispatch_session( $session_id ) {
        global $wpdb;
        $session_id = (int) $session_id;
        if ( $session_id <= 0 ) {
            return array();
        }
        $tbl   = $this->get_qz_table( 'participants' );
        $tbl_l = $this->learner_table;
        /* ACDC 3.25.171 — Même correction que sur l'écran consolidé : en salle,
           full_name reste vide et nickname ne porte que le prénom. La liste des
           participants affichait donc « David » au lieu de « David Contal », et deux
           apprenants de même prénom y étaient indiscernables. */
        $sql = "SELECT p.*,
                       TRIM(CONCAT(COALESCE(l.first_name,''), ' ', COALESCE(NULLIF(l.usage_last_name,''), l.last_name, ''))) AS learner_full_name
                FROM {$tbl} p
                LEFT JOIN {$tbl_l} l ON l.id = p.learner_id
                WHERE p.session_id = %d
                ORDER BY p.full_name ASC, p.email ASC";
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $session_id ) );
        return is_array( $rows ) ? $rows : array();
    }

    /**
     * Réponses d'un participant à un quiz, indexées par question_id pour lookup rapide.
     * Retourne array<question_id, stdClass row de player_answers>.
     */
    public function get_qz_answers_by_participant( $participant_id ) {
        global $wpdb;
        $participant_id = (int) $participant_id;
        if ( $participant_id <= 0 ) {
            return array();
        }
        $tbl = $this->get_qz_table( 'player_answers' );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$tbl} WHERE participant_id = %d", $participant_id
        ) );
        $indexed = array();
        if ( is_array( $rows ) ) {
            foreach ( $rows as $r ) {
                $indexed[ (int) $r->question_id ] = $r;
            }
        }
        return $indexed;
    }

    /**
     * Statistiques agrégées par question pour une session d'envoi donnée.
     * Pour chaque question : nombre de répondants, nombre de bonnes réponses,
     * taux de réussite (uniquement sur les questions scorées).
     *
     * @param int $session_id
     * @return array Tableau d'objets {question_id, title, type, count_answered, count_correct, success_rate}
     */
    public function get_qz_results_by_question( $session_id ) {
        global $wpdb;
        $session_id = (int) $session_id;
        if ( $session_id <= 0 ) {
            return array();
        }
        $tbl_q  = $this->get_qz_table( 'questions' );
        $tbl_pa = $this->get_qz_table( 'player_answers' );
        $tbl_s  = $this->get_qz_table( 'sessions' );

        $sql = "
            SELECT
                q.id AS question_id,
                q.title,
                q.type,
                q.is_scored,
                q.points_value,
                q.objective_id,
                (SELECT COUNT(*) FROM {$tbl_pa} pa WHERE pa.question_id = q.id AND pa.session_id = %d) AS count_answered,
                (SELECT COUNT(*) FROM {$tbl_pa} pa WHERE pa.question_id = q.id AND pa.session_id = %d AND pa.is_correct = 1) AS count_correct,
                /* ACDC 3.25.168 — Réussites partielles, et part moyenne réellement acquise. */
                (SELECT COUNT(*) FROM {$tbl_pa} pa WHERE pa.question_id = q.id AND pa.session_id = %d AND pa.is_correct = 0 AND pa.score_ratio > 0) AS count_partial,
                /* ACDC 3.25.171 — Réponses en attente de correction manuelle du formateur.
                   Sans ce compte, une réponse rédigée non corrigée était noyée dans les
                   « 0 bonne réponse » et son taux de réussite de 0 % passait pour un
                   échec, alors que personne ne l'avait encore lue. */
                (SELECT COUNT(*) FROM {$tbl_pa} pa WHERE pa.question_id = q.id AND pa.session_id = %d AND pa.is_correct IS NULL) AS count_pending,
                (SELECT AVG(COALESCE(pa.score_ratio, pa.is_correct)) FROM {$tbl_pa} pa WHERE pa.question_id = q.id AND pa.session_id = %d AND pa.is_correct IS NOT NULL) AS ratio_avg
            FROM {$tbl_q} q
            INNER JOIN {$tbl_s} s ON s.quiz_id = q.quiz_id
            WHERE s.id = %d
            ORDER BY q.sort_order ASC, q.id ASC
        ";
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $session_id, $session_id, $session_id, $session_id, $session_id, $session_id ) );

        if ( ! is_array( $rows ) ) {
            return array();
        }
        /* Taux de réussite calculé en PHP pour gérer la division par zéro.
           ACDC 3.25.168 — Il comptait les seules réponses parfaites : une question que
           toute la classe avait réussie aux deux tiers s'affichait à 0 %, et passait pour
           la plus faible du quiz. On prend désormais la part moyenne acquise. */
        foreach ( $rows as $r ) {
            if ( (int) $r->count_answered <= 0 ) {
                $r->success_rate = null;
                continue;
            }
            /* ACDC 3.25.171 — Une question dont TOUTES les réponses attendent la
               correction du formateur n'a pas de taux : afficher 0 % laisserait croire
               à un échec collectif. */
            if ( (int) $r->count_answered > 0 && (int) $r->count_pending >= (int) $r->count_answered ) {
                $r->success_rate = null;
                continue;
            }
            $r->success_rate = ( null !== $r->ratio_avg )
                ? round( (float) $r->ratio_avg * 100, 1 )
                : round( ( (int) $r->count_correct / (int) $r->count_answered ) * 100, 1 );
        }
        return $rows;
    }

    /**
     * Statistiques agrégées par objectif pédagogique pour une session.
     * Score moyen sur les questions rattachées à chaque objectif.
     * Critique pour Qualiopi (indicateur 12 — atteinte des objectifs).
     *
     * @param int $session_id
     * @return array Tableau d'objets {objective_id, label, pass_threshold, score_avg, count_questions, count_completed_participants, is_passing}
     */
    public function get_qz_results_by_objective( $session_id ) {
        global $wpdb;
        $session_id = (int) $session_id;
        if ( $session_id <= 0 ) {
            return array();
        }
        $tbl_obj = $this->get_qz_table( 'objectives' );
        $tbl_q   = $this->get_qz_table( 'questions' );
        $tbl_pa  = $this->get_qz_table( 'player_answers' );
        $tbl_s   = $this->get_qz_table( 'sessions' );
        $tbl_qz  = $this->get_qz_table( 'quizzes' );

        $sql = "
            SELECT
                o.id AS objective_id,
                o.label,
                o.description,
                o.pass_threshold,
                qz.pass_threshold AS quiz_pass_threshold,
                (SELECT COUNT(*) FROM {$tbl_q} q WHERE q.objective_id = o.id) AS count_questions,
                /* ACDC 3.25.171 — Cette moyenne divisait les points GAGNÉS par les points
                   NOMINAUX. Sur un quiz live, les points gagnés portent la prime de
                   rapidité : une réponse juste mais lente y vaut la moitié des points.
                   L'atteinte d'un objectif pédagogique se trouvait donc minorée par la
                   vitesse des apprenants, et l'onglet « Par objectif » contredisait
                   l'onglet « Par question » sur la même passation — 54,4 % contre 70 %.
                   On pondère désormais la PART ACQUISE par le poids de chaque question,
                   ce qui mesure l'acquisition et non le temps de réaction. */
                (SELECT
                    CASE WHEN SUM(CASE WHEN pa.id IS NOT NULL AND q.is_scored = 1 AND q.type != 'poll' THEN q.points_value ELSE 0 END) > 0
                         THEN ROUND(
                             (SUM(CASE WHEN pa.id IS NOT NULL AND q.is_scored = 1 AND q.type != 'poll'
                                       THEN q.points_value * COALESCE(pa.score_ratio, pa.is_correct, 0)
                                       ELSE 0 END)
                              / SUM(CASE WHEN pa.id IS NOT NULL AND q.is_scored = 1 AND q.type != 'poll' THEN q.points_value ELSE 0 END)) * 100,
                             1
                         )
                         ELSE NULL
                    END
                 FROM {$tbl_q} q
                 LEFT JOIN {$tbl_pa} pa ON pa.question_id = q.id AND pa.session_id = %d
                 WHERE q.objective_id = o.id AND q.type != 'poll') AS score_avg
            FROM {$tbl_obj} o
            INNER JOIN {$tbl_s} s ON s.quiz_id = o.quiz_id
            INNER JOIN {$tbl_qz} qz ON qz.id = o.quiz_id
            WHERE s.id = %d
            ORDER BY o.sort_order ASC, o.id ASC
        ";
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $session_id, $session_id ) );

        if ( ! is_array( $rows ) ) {
            return array();
        }
        /* ACDC 3.25.168 — Le seuil et la colonne « Atteint ? » restaient vides dès que le
           formateur n'avait pas saisi de seuil sur CHAQUE objectif — ce qui est le cas
           par défaut, le champ étant facultatif. Une évaluation sans verdict d'atteinte
           ne prouve rien au titre de l'indicateur Qualiopi 12 : on retombe donc sur le
           seuil de réussite du quiz, puis sur 70 % comme partout ailleurs dans le
           moteur, et l'on note d'où vient le seuil appliqué pour que l'écran le dise. */
        foreach ( $rows as $r ) {
            if ( null !== $r->pass_threshold ) {
                $r->threshold_applied = (float) $r->pass_threshold;
                $r->threshold_source  = 'objective';
            } elseif ( null !== $r->quiz_pass_threshold ) {
                $r->threshold_applied = (float) $r->quiz_pass_threshold;
                $r->threshold_source  = 'quiz';
            } else {
                $r->threshold_applied = 70.0;
                $r->threshold_source  = 'default';
            }
            $r->is_passing = ( null !== $r->score_avg )
                ? ( (float) $r->score_avg >= $r->threshold_applied )
                : null;
        }
        return $rows;
    }

    /**
     * Mise à jour manuelle du score d'une réponse libre par le formateur.
     * Recalcule ensuite le total_score et total_score_percentage du participant.
     * Trace l'événement dans le journal Qualiopi (event_type = 'manual_grading').
     *
     * @param int    $participant_id
     * @param int    $question_id
     * @param int    $is_correct  0 ou 1 (validé / non validé)
     * @param float  $score_earned Points attribués (entre 0 et points_value de la question)
     * @param string $grader_label Identifiant lisible de qui a corrigé (ex. 'admin' ou 'trainer:42').
     * @return bool|WP_Error
     */
    public function update_qz_manual_grading( $participant_id, $question_id, $is_correct, $score_earned, $grader_label = '', $grader_comment = '' ) {
        global $wpdb;
        $participant_id = (int) $participant_id;
        $question_id    = (int) $question_id;
        if ( $participant_id <= 0 || $question_id <= 0 ) {
            return new WP_Error( 'invalid_args', __( 'Identifiants invalides.', 'acdc-formation-saas' ) );
        }
        $tbl_pa = $this->get_qz_table( 'player_answers' );
        $tbl_p  = $this->get_qz_table( 'participants' );
        $tbl_q  = $this->get_qz_table( 'questions' );

        $q = $wpdb->get_row( $wpdb->prepare( "SELECT type, points_value, quiz_id FROM {$tbl_q} WHERE id = %d", $question_id ) );
        if ( ! $q ) {
            return new WP_Error( 'question_not_found', __( 'Question introuvable.', 'acdc-formation-saas' ) );
        }
        if ( self::ACDC_OF_QZ_QTYPE_OPEN_TEXT !== $q->type ) {
            return new WP_Error( 'not_open_text', __( "Cette question n'est pas une réponse libre.", 'acdc-formation-saas' ) );
        }
        $max_points  = (float) $q->points_value;
        $is_correct  = (int) $is_correct === 1 ? 1 : 0;
        $score_value = max( 0, min( (float) $score_earned, $max_points > 0 ? $max_points : 1000 ) );

        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, session_id FROM {$tbl_pa} WHERE participant_id = %d AND question_id = %d LIMIT 1",
            $participant_id, $question_id
        ) );
        if ( ! $existing ) {
            return new WP_Error( 'answer_not_found', __( "Aucune réponse de l'apprenant à corriger.", 'acdc-formation-saas' ) );
        }

        /* Préparer les données à mettre à jour.
           ACDC 3.25.171 — score_ratio n'était pas remis à jour à la correction manuelle :
           il restait à la valeur posée au moment de la passation, et l'écran « Par
           question » affichait donc une réponse libre validée par le formateur avec
           « 1 bonne réponse » et « 0 % de réussite » sur la même ligne. */
        $ratio_value   = ( $max_points > 0 ) ? max( 0.0, min( 1.0, $score_value / $max_points ) ) : (float) $is_correct;
        $update_data   = array( 'is_correct' => $is_correct, 'score_earned' => $score_value, 'score_ratio' => $ratio_value );
        $update_format = array( '%d', '%f', '%f' );

        // Champ commentaire formateur (colonne optionnelle — créée si absente)
        if ( '' !== $grader_comment ) {
            $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$tbl_pa} LIKE 'grader_comment'" );
            if ( empty( $cols ) ) {
                $wpdb->query( "ALTER TABLE {$tbl_pa} ADD COLUMN grader_comment LONGTEXT NULL" );
            }
            $update_data['grader_comment']   = sanitize_textarea_field( $grader_comment );
            $update_format[]                 = '%s';
        }

        $updated = $wpdb->update( $tbl_pa, $update_data, array( 'id' => (int) $existing->id ), $update_format, array( '%d' ) );
        if ( false === $updated ) {
            return new WP_Error( 'db_error', __( "Erreur lors de l'enregistrement.", 'acdc-formation-saas' ) );
        }

        // Recalcul du score — deux méthodes selon le type de session
        $participant = $wpdb->get_row( $wpdb->prepare( "SELECT session_id FROM {$tbl_p} WHERE id = %d LIMIT 1", $participant_id ) );
        $session_id  = $participant ? (int) $participant->session_id : ( $existing->session_id ? (int) $existing->session_id : 0 );

        $is_live_session = false;
        if ( $session_id > 0 ) {
            $tbl_s = $this->get_qz_table( 'sessions' );
            $delivery = $wpdb->get_var( $wpdb->prepare( "SELECT delivery_mode FROM {$tbl_s} WHERE id = %d LIMIT 1", $session_id ) );
            $is_live_session = ( 'live' === $delivery );
        }

        if ( $is_live_session ) {
            // Session live : total_score = SUM(score_earned) toutes questions confondues
            $total = (float) $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(score_earned),0) FROM {$tbl_pa} WHERE participant_id = %d AND session_id = %d",
                $participant_id, $session_id
            ) );
            $wpdb->update( $tbl_p, array( 'total_score' => $total, 'updated_at' => current_time('mysql') ), array( 'id' => $participant_id ), array( '%f', '%s' ), array( '%d' ) );
        } else {
            // Autre session : recalcul standard via recompute
            $this->recompute_qz_participant_score( $participant_id );
        }

        // Journal Qualiopi
        if ( method_exists( $this, 'log_qz_event' ) ) {
            $this->log_qz_event( array(
                'quiz_id'        => (int) $q->quiz_id,
                'participant_id' => $participant_id,
                'event_type'     => 'manual_grading',
                'event_label'    => sprintf(
                    'Correction manuelle question #%d : %s — %s pts (par %s)%s',
                    $question_id,
                    $is_correct ? 'validé' : 'non validé',
                    number_format( $score_value, 2 ),
                    $grader_label !== '' ? $grader_label : 'inconnu',
                    $grader_comment !== '' ? ' — commentaire : ' . mb_substr( $grader_comment, 0, 100 ) : ''
                ),
            ) );
        }
        return true;
    }

    /**
     * Recalcule total_score et total_score_percentage d'un participant
     * en agrégeant ses player_answers. Utilisé après chaque correction manuelle.
     *
     * @param int $participant_id
     * @return bool
     */
    public function recompute_qz_participant_score( $participant_id ) {
        global $wpdb;
        $participant_id = (int) $participant_id;
        if ( $participant_id <= 0 ) {
            return false;
        }
        $tbl_pa = $this->get_qz_table( 'player_answers' );
        $tbl_q  = $this->get_qz_table( 'questions' );
        $tbl_p  = $this->get_qz_table( 'participants' );

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                SUM(pa.score_earned) AS earned,
                SUM(CASE WHEN q.is_scored = 1 THEN q.points_value ELSE 0 END) AS possible
             FROM {$tbl_pa} pa
             INNER JOIN {$tbl_q} q ON q.id = pa.question_id
             WHERE pa.participant_id = %d
               AND q.is_scored = 1
               AND q.type != 'poll'",
            $participant_id
        ) );
        if ( ! $row ) {
            return false;
        }
        $earned   = (float) $row->earned;
        $possible = (float) $row->possible;
        $pct = ( $possible > 0 ) ? round( ( $earned / $possible ) * 100, 2 ) : null;

        /* ACDC 3.25.171 — is_passed était une colonne que RIEN n'écrivait : lue à
           plusieurs endroits, jamais remplie. La colonne « Résultat » de l'écran
           consolidé affichait donc « — » pour tout le monde, y compris pour des
           passations parfaitement notées. On la calcule ici, au moment même où le
           pourcentage est établi, pour que les deux ne puissent jamais diverger. */
        $is_passed = null;
        if ( null !== $pct ) {
            $tbl_s   = $this->get_qz_table( 'sessions' );
            $quiz_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT s.quiz_id FROM {$tbl_s} s
                 INNER JOIN {$tbl_p} p ON p.session_id = s.id WHERE p.id = %d LIMIT 1",
                $participant_id
            ) );
            $is_passed = ( $pct >= $this->qz_pass_threshold_for_quiz( $quiz_id ) ) ? 1 : 0;
        }

        $wpdb->update(
            $tbl_p,
            array(
                'total_score'            => $earned,
                'total_score_percentage' => $pct,
                'is_passed'              => $is_passed,
            ),
            array( 'id' => $participant_id ),
            array( '%f', '%f', '%d' ),
            array( '%d' )
        );
        return true;
    }

    /**
     * Calcule les KPIs globaux du dashboard Résultats (toutes finalités confondues).
     * Si purpose vide → toutes finalités. Si trainer_id > 0 → filtré + fallback.
     *
     * @param array $args { trainer_id, purpose }
     * @return array {
     *     count_sessions, count_sessions_completed,
     *     count_participants_total, count_participants_completed,
     *     count_answers_recorded, response_rate, avg_score
     * }
     */
    public function get_qz_results_dashboard_kpis( $args = array() ) {
        global $wpdb;
        $trainer_id = isset( $args['trainer_id'] ) ? (int) $args['trainer_id'] : 0;
        $purpose    = isset( $args['purpose'] ) ? (string) $args['purpose'] : '';

        $tbl_s   = $this->get_qz_table( 'sessions' );
        $tbl_q   = $this->get_qz_table( 'quizzes' );
        $tbl_p   = $this->get_qz_table( 'participants' );
        $tbl_pa  = $this->get_qz_table( 'player_answers' );
        $tbl_ofs = $wpdb->prefix . 'acdc_of_sessions';

        $where_s  = array( "s.status != 'draft'" );
        $params_s = array();
        if ( '' !== $purpose ) {
            $where_s[]  = 'q.quiz_purpose = %s';
            $params_s[] = $purpose;
        }
        /* ACDC 3.25.157 — Filtre par quiz, utilisé par l'entrée de menu « Voir les
           résultats » : les vignettes doivent porter sur le même périmètre que la
           liste affichée en dessous, sans quoi le KPI global contredirait le tableau. */
        $quiz_filter_id = isset( $args['quiz_id'] ) ? (int) $args['quiz_id'] : 0;
        if ( $quiz_filter_id > 0 ) {
            $where_s[]  = 'q.id = %d';
            $params_s[] = $quiz_filter_id;
        }
        $apply_trainer = false;
        if ( $trainer_id > 0 ) {
            // On vérifie si le filtre formateur retourne au moins une session
            $check_sql = "SELECT COUNT(*) FROM {$tbl_s} s
                          WHERE EXISTS (SELECT 1 FROM {$tbl_ofs} ofs WHERE ofs.formation_id = s.formation_id AND ofs.trainer_id = %d) LIMIT 1";
            $has_filtered = (int) $wpdb->get_var( $wpdb->prepare( $check_sql, $trainer_id ) );
            if ( $has_filtered > 0 ) {
                $apply_trainer = true;
            }
        }
        if ( $apply_trainer ) {
            $where_s[]  = 'EXISTS (SELECT 1 FROM ' . $tbl_ofs . ' ofs WHERE ofs.formation_id = s.formation_id AND ofs.trainer_id = %d)';
            $params_s[] = $trainer_id;
        }
        $where_clause = 'WHERE ' . implode( ' AND ', $where_s );

        // Sous-requête sessions
        $sub_sessions_sql = "SELECT s.id FROM {$tbl_s} s INNER JOIN {$tbl_q} q ON q.id = s.quiz_id {$where_clause}";

        $count_sessions = (int) $wpdb->get_var(
            empty( $params_s )
                ? "SELECT COUNT(*) FROM ({$sub_sessions_sql}) AS sub"
                : $wpdb->prepare( "SELECT COUNT(*) FROM ({$sub_sessions_sql}) AS sub", $params_s )
        );
        $count_sessions_completed = (int) $wpdb->get_var(
            empty( $params_s )
                ? "SELECT COUNT(*) FROM ({$sub_sessions_sql}) AS sub INNER JOIN {$tbl_s} s2 ON s2.id = sub.id WHERE s2.status = 'completed'"
                : $wpdb->prepare( "SELECT COUNT(*) FROM ({$sub_sessions_sql}) AS sub INNER JOIN {$tbl_s} s2 ON s2.id = sub.id WHERE s2.status = 'completed'", $params_s )
        );
        $count_participants_total = (int) $wpdb->get_var(
            empty( $params_s )
                ? "SELECT COUNT(*) FROM {$tbl_p} p WHERE p.session_id IN ({$sub_sessions_sql})"
                : $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl_p} p WHERE p.session_id IN ({$sub_sessions_sql})", $params_s )
        );
        $count_participants_completed = (int) $wpdb->get_var(
            empty( $params_s )
                ? "SELECT COUNT(*) FROM {$tbl_p} p WHERE p.status = 'completed' AND p.session_id IN ({$sub_sessions_sql})"
                : $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl_p} p WHERE p.status = 'completed' AND p.session_id IN ({$sub_sessions_sql})", $params_s )
        );
        $count_answers = (int) $wpdb->get_var(
            empty( $params_s )
                ? "SELECT COUNT(*) FROM {$tbl_pa} pa WHERE pa.session_id IN ({$sub_sessions_sql}) AND pa.is_correct IS NOT NULL"
                : $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl_pa} pa WHERE pa.session_id IN ({$sub_sessions_sql}) AND pa.is_correct IS NOT NULL", $params_s )
        );
        $count_correct = (int) $wpdb->get_var(
            empty( $params_s )
                ? "SELECT COUNT(*) FROM {$tbl_pa} pa WHERE pa.session_id IN ({$sub_sessions_sql}) AND pa.is_correct = 1"
                : $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl_pa} pa WHERE pa.session_id IN ({$sub_sessions_sql}) AND pa.is_correct = 1", $params_s )
        );
        $pct_correct = ( $count_answers > 0 ) ? round( $count_correct / $count_answers * 100, 1 ) : null;
        /* Pour les sessions live : score moyen en points bruts ; sinon en pourcentage.
           ACDC 3.25.157 — La condition « total_score > 0 » a été retirée : elle
           excluait de la moyenne tout participant ayant marqué 0 point. Avec un seul
           participant à 0, l'AVG ne portait sur aucune ligne, renvoyait NULL, et la
           vignette affichait « — » comme s'il n'y avait pas de donnée. Un zéro est
           une valeur, pas une absence : seul NULL doit produire un tiret. */
        if ( 'live' === $purpose ) {
            $avg_score = $wpdb->get_var(
                empty( $params_s )
                    ? "SELECT AVG(p.total_score) FROM {$tbl_p} p WHERE p.status = 'completed' AND p.total_score IS NOT NULL AND p.session_id IN ({$sub_sessions_sql})"
                    : $wpdb->prepare( "SELECT AVG(p.total_score) FROM {$tbl_p} p WHERE p.status = 'completed' AND p.total_score IS NOT NULL AND p.session_id IN ({$sub_sessions_sql})", $params_s )
            );
        } else {
            $avg_score = $wpdb->get_var(
                empty( $params_s )
                    ? "SELECT AVG(p.total_score_percentage) FROM {$tbl_p} p WHERE p.status = 'completed' AND p.total_score_percentage IS NOT NULL AND p.session_id IN ({$sub_sessions_sql})"
                    : $wpdb->prepare( "SELECT AVG(p.total_score_percentage) FROM {$tbl_p} p WHERE p.status = 'completed' AND p.total_score_percentage IS NOT NULL AND p.session_id IN ({$sub_sessions_sql})", $params_s )
            );
        }

        $response_rate = ( $count_participants_total > 0 )
            ? round( ( $count_participants_completed / $count_participants_total ) * 100, 1 )
            : 0;

        return array(
            'count_sessions'               => $count_sessions,
            'count_sessions_completed'     => $count_sessions_completed,
            'count_participants_total'     => $count_participants_total,
            'count_participants_completed' => $count_participants_completed,
            'count_answers_recorded'       => $count_answers,
            'pct_correct_answers'          => $pct_correct,
            'response_rate'                => $response_rate,
            'avg_score'                    => ( null === $avg_score ) ? null : round( (float) $avg_score, 1 ),
        );
    }

    /**
     * Génère un export CSV des résultats d'une session d'envoi.
     *
     * Colonnes : Nom complet, E-mail, Statut, Date complétion, Durée (min), Score brut,
     * Score %, puis une colonne par question avec la réponse détaillée + correct/faux.
     *
     * @param int $session_id
     * @return string CSV (UTF-8 BOM pour Excel)
     */
    /**
     * Génère le PDF de résultats individuel pour un participant.
     * Stocke le fichier dans wp-uploads/acdc-qz-results/{session_id}/{participant_id}/
     * et sauvegarde l'URL dans result_document_url sur la table participants.
     * Ne génère pas si des questions open_text ne sont pas encore corrigées.
     *
     * @param int $participant_id
     * @param int $session_id
     * @return array|false array('url'=>..., 'path'=>...) ou false si non généré
     */
    /**
     * ACDC 3.21.06 — Résout registration_id depuis learner_id + formation_id si absent,
     * l'écrit en BDD, puis génère le PDF de résultats.
     *
     * @param object $participant  Ligne qz_participants.
     * @param int    $session_id
     */
    public function qz_resolve_registration_id_and_generate_pdf( $participant, $session_id ) {
        global $wpdb;
        $tbl_p   = $this->get_qz_table( 'participants' );
        $tbl_reg = $wpdb->prefix . 'acdc_of_training_registrations';
        $learner_id = (int) $participant->learner_id;
        $reg_id     = (int) $participant->registration_id;
        if ( $learner_id <= 0 ) { return; }
        if ( $reg_id <= 0 ) {
            $session = $this->get_qz_dispatch_session( $session_id );
            $formation_id = ( $session && ! empty( $session->formation_id ) ) ? (int) $session->formation_id : 0;
            if ( $formation_id > 0 ) {
                $reg_id = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$tbl_reg} WHERE learner_id=%d AND formation_id=%d ORDER BY id DESC LIMIT 1",
                    $learner_id, $formation_id
                ) );
            }
            if ( $reg_id <= 0 ) {
                // Fallback : inscription la plus récente pour cet apprenant
                $reg_id = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$tbl_reg} WHERE learner_id=%d ORDER BY id DESC LIMIT 1",
                    $learner_id
                ) );
            }
            if ( $reg_id > 0 ) {
                $wpdb->update( $tbl_p, array( 'registration_id' => $reg_id ), array( 'id' => (int) $participant->id ), array( '%d' ), array( '%d' ) );
            }
        }
        if ( $reg_id > 0 ) {
            $this->generate_qz_result_pdf_for_participant( (int) $participant->id, $session_id );
        }
    }

        public function generate_qz_result_pdf_for_participant( $participant_id, $session_id ) {
        global $wpdb;
        $participant_id = (int) $participant_id;
        $session_id     = (int) $session_id;
        if ( $participant_id <= 0 || $session_id <= 0 ) { return false; }

        $tbl_p  = $this->get_qz_table( 'participants' );
        $tbl_pa = $this->get_qz_table( 'player_answers' );
        $tbl_q  = $this->get_qz_table( 'questions' );

        $p = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl_p} WHERE id=%d LIMIT 1", $participant_id ) );
        if ( ! $p ) { return false; }

        $session   = $this->get_qz_dispatch_session( $session_id );
        if ( ! $session ) { return false; }
        $questions = $this->get_qz_questions_for_quiz( (int) $session->quiz_id );

        // Ne pas générer si des réponses libres ne sont pas encore corrigées
        $pending = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl_pa} pa INNER JOIN {$tbl_q} q ON q.id = pa.question_id
             WHERE pa.participant_id=%d AND pa.session_id=%d AND q.type='open_text' AND pa.is_correct IS NULL",
            $participant_id, $session_id
        ) );
        if ( (int) $pending > 0 ) { return false; }

        if ( ! class_exists( 'ACDC_Sig_Core' ) || ! class_exists( 'ACDC_Sig_PDF' ) ) { return false; }

        $answers         = $this->get_qz_answers_by_participant( $participant_id );
        $has_comment_col = ! empty( $wpdb->get_col( "SHOW COLUMNS FROM {$tbl_pa} LIKE 'grader_comment'" ) );

        // ── Constantes layout (A4 = 595 x 842 pts, marges identiques aux templates HTML)
        $page_w = 595; $page_h = 842;
        $left   = 35;  $right = 560; $content_w = 525;
        // Couleurs des templates HTML ACDC
        $navy   = '0C2D52'; $gold = 'C5A253'; $white = 'FFFFFF';
        $ink    = '1f2937'; $muted = '6b7280'; $line = 'd7dde6';
        $green_bg = 'e8f5e9'; $red_bg = 'ffebee'; $soft = 'f8fafc';
        $gold_bg  = 'fefaf2'; $green_tx = '1B5E20'; $red_tx = 'B71C1C';
        $dark = '374151';

        // ── Infos participant / session ───────────────────────────────────
        $participant_name = $p->full_name ?: $p->nickname ?: '—';
        $quiz_title       = $session->quiz_title ?? '—';
        $formation_title  = $session->formation_title ?? '—';
        $played_on        = ! empty( $session->started_at )
                            ? wp_date( 'd/m/Y', strtotime( $session->started_at ) )
                            : wp_date( 'd/m/Y' );
        $total_pts        = number_format( (float) $p->total_score, 0, ',', ' ' ) . ' pts';
        $hosted_by        = '—';
        if ( ! empty( $session->created_by ) ) {
            $u = get_userdata( (int) $session->created_by );
            if ( $u ) {
                $n = trim( $u->first_name . ' ' . $u->last_name );
                $hosted_by = $n ?: $u->display_name;
            }
        }

        // ACDC 3.21.71 — Score % + passing_score + mention Réussi/Non réussi
        $score_pct_raw  = null !== $p->total_score_percentage ? (float) $p->total_score_percentage : null;
        $score_pct_disp = null !== $score_pct_raw ? number_format( $score_pct_raw, 1, ',', ' ' ) . ' %' : '—';
        $tbl_qz_quizzes_pass = $this->get_qz_table( 'quizzes' );
        $passing_score_raw = $wpdb->get_var( $wpdb->prepare( "SELECT pass_threshold FROM {$tbl_qz_quizzes_pass} WHERE id = %d", (int) $session->quiz_id ) );
        $passing_score = ( null !== $passing_score_raw ) ? (float) $passing_score_raw : 70.0;

        // Calcul is_passed depuis le participant (ou recalcul si absent)
        if ( null !== $p->is_passed ) {
            $is_passed_val = (int) $p->is_passed;
        } elseif ( null !== $score_pct_raw ) {
            $is_passed_val = $score_pct_raw >= $passing_score ? 1 : 0;
        } else {
            $is_passed_val = null;
        }

        // Score max calculé
        $score_max_calc = 0;
        foreach ( $questions as $q_s ) {
            if ( (int) $q_s->is_scored === 1 && ! in_array( $q_s->type, array( self::ACDC_OF_QZ_QTYPE_POLL, self::ACDC_OF_QZ_QTYPE_OPEN_TEXT ), true ) ) {
                $score_max_calc += (int) $q_s->points_value;
            }
        }

        // Nb bonnes réponses
        $nb_correct = 0;
        $nb_scored  = 0;
        foreach ( $answers as $qid => $a ) {
            $q_for_count = null;
            foreach ( $questions as $qc ) {
                if ( (int) $qc->id === (int) $qid ) { $q_for_count = $qc; break; }
            }
            if ( $q_for_count && (int) $q_for_count->is_scored === 1 && ! in_array( $q_for_count->type, array( self::ACDC_OF_QZ_QTYPE_POLL, self::ACDC_OF_QZ_QTYPE_OPEN_TEXT ), true ) ) {
                $nb_scored++;
                if ( (int) $a->is_correct === 1 ) { $nb_correct++; }
            }
        }

        // Bonnes réponses texte par question (pour affichage quand incorrect)
        $correct_answers_by_qid = array();
        foreach ( $questions as $q_ca ) {
            $qid_ca = (int) $q_ca->id;
            $qz_answers_all = $this->get_qz_answers_for_question_db( $qid_ca );
            $correct_texts = array();
            $feedback_texts = array();
            foreach ( (array) $qz_answers_all as $qa ) {
                if ( (int) $qa->is_correct === 1 ) {
                    $correct_texts[] = (string) $qa->text;
                }
                if ( ! empty( $qa->feedback_text ) ) {
                    $feedback_texts[] = (string) $qa->feedback_text;
                }
            }
            $correct_answers_by_qid[ $qid_ca ] = array(
                'correct'  => implode( ' / ', $correct_texts ),
                'feedback' => implode( ' — ', $feedback_texts ),
            );
        }

        // ── Logo (méthode identique aux contrats) ────────────────────────
        $profile   = method_exists( $this, 'get_training_org_profile' ) ? $this->get_training_org_profile() : array();
        $logo_url  = ! empty( $profile['logo_url'] ) ? $profile['logo_url'] : home_url( '/wp-content/uploads/2026/03/Logo-ACDC.png' );
        $logo_image = $this->prepare_pdf_jpeg_image( $logo_url, 45, 45 ); // carré 16mm≈45pt

        // ── Helper nouvelle page ─────────────────────────────────────────
        $new_page = function() use ( $page_w, $page_h, $left, $right, $content_w,
                                     $navy, $gold, $white, $ink, $muted, $line, $dark,
                                     $logo_image, $quiz_title, $participant_name, $played_on,
                                     $hosted_by, $total_pts, $formation_title ) {
            $page = array();
            $page[] = array( 'type' => 'page_meta', 'width' => $page_w, 'height' => $page_h );

            // ── Header : logo + nom ACDC + coordonnées droite ────────────
            $header_y_top = $page_h - 31; // marge top 11mm

            // Logo carré 45x45pt à gauche
            if ( $logo_image ) {
                $page[] = array(
                    'type'          => 'image',
                    'image_key'     => $logo_image['key'],
                    'image_data'    => $logo_image['data'],
                    'image_width'   => $logo_image['width'],
                    'image_height'  => $logo_image['height'],
                    'display_width'  => $logo_image['display_width'],
                    'display_height' => $logo_image['display_height'],
                    'x' => $left,
                    'y' => $header_y_top - $logo_image['display_height'] + 4,
                );
                $text_logo_x = $left + $logo_image['display_width'] + 6;
            } else {
                $text_logo_x = $left;
            }

            // Texte ACDC-Formation à droite du logo
            $page[] = array( 'text' => 'ACDC-Formation', 'x' => $text_logo_x,
                             'y' => $header_y_top - 5, 'size' => 13.6, 'font' => 'Helvetica-Bold', 'color' => $navy );
            $page[] = array( 'text' => 'Azur — Compétences — Développement — Conseil', 'x' => $text_logo_x,
                             'y' => $header_y_top - 18, 'size' => 7.7, 'font' => 'Helvetica', 'color' => $gold );

            // Coordonnées organisme à droite (alignées à droite du header)
            $company_lines = array( 'ACDC-Formation', '7 avenue Paul Cézanne', '83310 Cogolin — France', 'Siret : 405109901 00042', 'NDA : 93 83 08347 83' );
            $cy = $header_y_top - 5;
            foreach ( $company_lines as $cl ) {
                $page[] = array( 'text' => $cl, 'x' => 400, 'y' => $cy, 'size' => 8, 'font' => 'Helvetica', 'color' => $dark );
                $cy -= 11;
            }

            // Séparateur doré sous le header
            $sep_y = $header_y_top - 48;
            $page[] = array( 'type' => 'rect', 'x' => $left, 'y' => $sep_y, 'width' => $content_w, 'height' => 1.5, 'fill_color' => $gold );

            return $page;
        };

        // ── Page 1 ───────────────────────────────────────────────────────
        $page1  = $new_page();
        $cursor = $page_h - 100; // après header

        // Titre H1 — contextuel selon la finalité (hotfix50)
        $pdf_titles = array(
            'positioning' => 'Résultats test de positionnement',
            'diagnostic'  => 'Résultats évaluation diagnostique',
            'assessment'  => 'Résultats évaluation des acquis',
            'live'        => 'Résultats de quiz',
        );
        $qz_purpose_pdf = isset( $session->quiz_purpose ) ? (string) $session->quiz_purpose : 'live';
        $pdf_h1_title   = isset( $pdf_titles[ $qz_purpose_pdf ] ) ? $pdf_titles[ $qz_purpose_pdf ] : $pdf_titles['live'];
        $page1[] = array( 'text' => $pdf_h1_title, 'x' => $left, 'y' => $cursor,
                          'size' => 15.6, 'font' => 'Helvetica-Bold', 'color' => $navy );
        $cursor -= 22;

        // ── Section : Informations enrichie (ACDC 3.21.71) ──────────────
        $section_h = 100;
        $page1[] = array( 'type' => 'rect', 'x' => $left, 'y' => $cursor - $section_h,
                          'width' => $content_w, 'height' => $section_h, 'fill_color' => $white, 'stroke_color' => $line, 'line_width' => 0.7 );
        $page1[] = array( 'text' => 'INFORMATIONS', 'x' => $left + 13, 'y' => $cursor - 14,
                          'size' => 8.4, 'font' => 'Helvetica-Bold', 'color' => $gold );

        $col1 = $left + 13; $col2 = $left + 185; $col3 = $left + 360;
        $row1_y = $cursor - 30; $row2_y = $cursor - 52;

        $page1[] = array( 'text' => 'Apprenant :', 'x' => $col1, 'y' => $row1_y + 8, 'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => $navy );
        $page1[] = array( 'text' => mb_strimwidth( $participant_name, 0, 28, '…' ), 'x' => $col1, 'y' => $row1_y, 'size' => 8.7, 'font' => 'Helvetica', 'color' => $ink );
        $page1[] = array( 'text' => 'Quiz :', 'x' => $col2, 'y' => $row1_y + 8, 'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => $navy );
        $page1[] = array( 'text' => mb_strimwidth( $quiz_title, 0, 30, '…' ), 'x' => $col2, 'y' => $row1_y, 'size' => 8.7, 'font' => 'Helvetica', 'color' => $ink );
        $page1[] = array( 'text' => 'Animé par :', 'x' => $col3, 'y' => $row1_y + 8, 'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => $navy );
        $page1[] = array( 'text' => mb_strimwidth( $hosted_by, 0, 22, '…' ), 'x' => $col3, 'y' => $row1_y, 'size' => 8.7, 'font' => 'Helvetica', 'color' => $ink );

        $page1[] = array( 'text' => 'Date :', 'x' => $col1, 'y' => $row2_y + 8, 'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => $navy );
        $page1[] = array( 'text' => $played_on, 'x' => $col1, 'y' => $row2_y, 'size' => 8.7, 'font' => 'Helvetica', 'color' => $ink );
        $page1[] = array( 'text' => 'Score total :', 'x' => $col2, 'y' => $row2_y + 8, 'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => $navy );
        $page1[] = array( 'text' => $total_pts, 'x' => $col2, 'y' => $row2_y, 'size' => 9, 'font' => 'Helvetica-Bold', 'color' => $gold );
        $page1[] = array( 'text' => 'Formation :', 'x' => $col3, 'y' => $row2_y + 8, 'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => $navy );
        $page1[] = array( 'text' => mb_strimwidth( $formation_title, 0, 22, '…' ), 'x' => $col3, 'y' => $row2_y, 'size' => 8.7, 'font' => 'Helvetica', 'color' => $ink );

        // Ligne 3 : Score %, barre de progression, nb bonnes réponses, badge Réussi
        $row3_y = $cursor - 72;
        $page1[] = array( 'text' => 'Score :', 'x' => $col1, 'y' => $row3_y + 8, 'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => $navy );

        if ( null !== $score_pct_raw ) {
            // Score % en grand
            $pct_color = ( null !== $is_passed_val && 1 === $is_passed_val ) ? $green_tx : $red_tx;
            $page1[] = array( 'text' => $score_pct_disp, 'x' => $col1, 'y' => $row3_y - 2, 'size' => 13, 'font' => 'Helvetica-Bold', 'color' => $pct_color );
            // Barre de progression (fond gris + remplissage coloré)
            $bar_x = $col1; $bar_y = $row3_y - 16; $bar_w = 150; $bar_h = 7;
            $page1[] = array( 'type' => 'rect', 'x' => $bar_x, 'y' => $bar_y, 'width' => $bar_w, 'height' => $bar_h, 'fill_color' => $line );
            $fill_w = (int) round( $bar_w * min( 100, max( 0, $score_pct_raw ) ) / 100 );
            if ( $fill_w > 0 ) {
                $bar_fill_color = ( null !== $is_passed_val && 1 === $is_passed_val ) ? '22c55e' : 'ef4444';
                $page1[] = array( 'type' => 'rect', 'x' => $bar_x, 'y' => $bar_y, 'width' => $fill_w, 'height' => $bar_h, 'fill_color' => $bar_fill_color );
            }
            // Seuil de réussite (trait vertical)
            $threshold_x = $bar_x + (int) round( $bar_w * $passing_score / 100 );
            $page1[] = array( 'type' => 'rect', 'x' => $threshold_x, 'y' => $bar_y - 1, 'width' => 1, 'height' => $bar_h + 2, 'fill_color' => $navy );
        } else {
            $page1[] = array( 'text' => '—', 'x' => $col1, 'y' => $row3_y, 'size' => 9, 'font' => 'Helvetica', 'color' => $muted );
        }

        // Nb bonnes réponses
        $page1[] = array( 'text' => 'Bonnes réponses :', 'x' => $col2, 'y' => $row3_y + 8, 'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => $navy );
        $page1[] = array( 'text' => $nb_correct . ' / ' . $nb_scored, 'x' => $col2, 'y' => $row3_y, 'size' => 10, 'font' => 'Helvetica-Bold', 'color' => $ink );

        // Badge Réussi / Non réussi
        if ( null !== $is_passed_val ) {
            $badge_text  = 1 === $is_passed_val ? 'REUSSI' : 'NON REUSSI';
            $badge_bg    = 1 === $is_passed_val ? $green_bg : $red_bg;
            $badge_color = 1 === $is_passed_val ? $green_tx : $red_tx;
            $badge_w = 1 === $is_passed_val ? 56 : 80;
            $page1[] = array( 'type' => 'rect', 'x' => $col3, 'y' => $row3_y - 3, 'width' => $badge_w, 'height' => 20, 'fill_color' => $badge_bg );
            $page1[] = array( 'text' => $badge_text, 'x' => $col3 + 6, 'y' => $row3_y + 5, 'size' => 9, 'font' => 'Helvetica-Bold', 'color' => $badge_color );
            $page1[] = array( 'text' => '(seuil : ' . number_format( $passing_score, 0 ) . ' %)', 'x' => $col3, 'y' => $row3_y - 10, 'size' => 7, 'font' => 'Helvetica', 'color' => $muted );
        }

        $cursor -= $section_h + 14;

        // ── Titre section détail ──────────────────────────────────────────
        $page1[] = array( 'text' => 'DÉTAIL DES RÉPONSES', 'x' => $left, 'y' => $cursor,
                          'size' => 8.4, 'font' => 'Helvetica-Bold', 'color' => $gold );
        $cursor -= 14;

        // ── Tableau des réponses (ACDC 3.21.71 — colonnes enrichies) ─────
        // Colonnes : N°(22) | Question(155) | Votre réponse(105) | Résultat(58) | Pts(35) | Bonne réponse / Feedback(150)
        $col_w  = array( 22, 155, 105, 58, 35, 150 );
        $col_x  = array( $left );
        foreach ( $col_w as $i => $cw ) {
            if ( $i > 0 ) $col_x[] = $col_x[$i-1] + $col_w[$i-1];
        }
        $th_h = 18;

        // Header tableau
        $page1[] = array( 'type' => 'rect', 'x' => $left, 'y' => $cursor - $th_h,
                          'width' => $content_w, 'height' => $th_h, 'fill_color' => $navy );
        foreach ( array( 'N°', 'Question', 'Votre réponse', 'Résultat', 'Pts', 'Bonne réponse / Feedback' ) as $hi => $hl ) {
            $page1[] = array( 'text' => $hl, 'x' => $col_x[$hi] + 3, 'y' => $cursor - 12,
                              'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => $white );
        }
        $cursor -= $th_h;

        $pages = array( $page1 );
        $pi    = 0;
        $row_h = 22;
        $min_y = 52;

        foreach ( $questions as $qi => $q ) {
            $a           = isset( $answers[ (int) $q->id ] ) ? $answers[ (int) $q->id ] : null;
            $ans_text    = $a ? $this->format_qz_answer_for_export( $q, $a ) : '—';
            $is_correct  = $a ? $a->is_correct : null;
            $score_pts   = $a ? number_format( (float) $a->score_earned, 0 ) : '0';
            $comment     = ( $has_comment_col && $a && ! empty( $a->grader_comment ) ) ? $a->grader_comment : '';
            $is_poll     = ( $q->type === self::ACDC_OF_QZ_QTYPE_POLL );

            // ACDC 3.21.71 — Bonne réponse + feedback quand incorrect
            $correct_info = isset( $correct_answers_by_qid[ (int) $q->id ] ) ? $correct_answers_by_qid[ (int) $q->id ] : array( 'correct' => '', 'feedback' => '' );
            if ( ! $is_poll && null !== $is_correct && (int) $is_correct !== 1 && '' !== $correct_info['correct'] ) {
                // Réponse incorrecte : afficher la bonne réponse
                $col6_text = mb_strimwidth( $correct_info['correct'], 0, 32, '…' );
                $col6_color = $green_tx;
            } elseif ( ! empty( $comment ) ) {
                $col6_text = mb_strimwidth( $comment, 0, 32, '…' );
                $col6_color = $muted;
            } elseif ( ! $is_poll && ! empty( $correct_info['feedback'] ) ) {
                $col6_text = mb_strimwidth( $correct_info['feedback'], 0, 32, '…' );
                $col6_color = $muted;
            } else {
                $col6_text = '—';
                $col6_color = $muted;
            }

            if ( $is_poll ) {
                $row_fill = $soft; $res_label = 'Sondage'; $res_color = $muted;
            } elseif ( null === $is_correct ) {
                $row_fill = $soft; $res_label = 'En attente'; $res_color = $muted;
            } elseif ( (int) $is_correct === 1 ) {
                $row_fill = $green_bg; $res_label = chr(10).' Correct'; $res_color = $green_tx;
            } else {
                $row_fill = $red_bg; $res_label = 'X Incorrect'; $res_color = $red_tx;
            }

            if ( $cursor - $row_h < $min_y ) {
                // Footer page courante
                $pages[$pi][] = array( 'type' => 'rect', 'x' => $left, 'y' => 32, 'width' => $content_w, 'height' => 0.7, 'fill_color' => $line );
                $pages[$pi][] = array( 'text' => '7 avenue Paul Cézanne — 83310 Cogolin — France — Siret : 405109901 00042 — NDA : 93 83 08347 83',
                                       'x' => $left, 'y' => 22, 'size' => 6.8, 'font' => 'Helvetica', 'color' => $muted );
                $pi++;
                $new_p  = $new_page();
                $cursor = $page_h - 112;
                // Répéter entête tableau
                $new_p[] = array( 'type' => 'rect', 'x' => $left, 'y' => $cursor - $th_h,
                                  'width' => $content_w, 'height' => $th_h, 'fill_color' => $navy );
                foreach ( array( 'N°', 'Question', 'Votre réponse', 'Résultat', 'Pts', 'Bonne réponse / Feedback' ) as $hi => $hl ) {
                    $new_p[] = array( 'text' => $hl, 'x' => $col_x[$hi] + 3, 'y' => $cursor - 12,
                                      'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => $white );
                }
                $cursor -= $th_h;
                $pages[] = $new_p;
            }

            // Fond de ligne
            $pages[$pi][] = array( 'type' => 'rect', 'x' => $left, 'y' => $cursor - $row_h,
                                   'width' => $content_w, 'height' => $row_h, 'fill_color' => $row_fill );
            // Séparateur bas de ligne
            $pages[$pi][] = array( 'type' => 'rect', 'x' => $left, 'y' => $cursor - $row_h,
                                   'width' => $content_w, 'height' => 0.4, 'fill_color' => $line );
            $ty = $cursor - 15;
            $pages[$pi][] = array( 'text' => (string)($qi+1), 'x' => $col_x[0]+4, 'y' => $ty, 'size' => 8, 'font' => 'Helvetica', 'color' => $ink );
            $pages[$pi][] = array( 'text' => mb_strimwidth($q->title, 0, 42, '…'), 'x' => $col_x[1]+3, 'y' => $ty, 'size' => 8, 'font' => 'Helvetica', 'color' => $ink );
            $pages[$pi][] = array( 'text' => mb_strimwidth($ans_text, 0, 28, '…'), 'x' => $col_x[2]+3, 'y' => $ty, 'size' => 8, 'font' => 'Helvetica', 'color' => $ink );
            $pages[$pi][] = array( 'text' => $res_label, 'x' => $col_x[3]+3, 'y' => $ty, 'size' => 8, 'font' => 'Helvetica-Bold', 'color' => $res_color );
            $pages[$pi][] = array( 'text' => $is_poll ? '—' : $score_pts, 'x' => $col_x[4]+3, 'y' => $ty, 'size' => 8, 'font' => 'Helvetica', 'color' => $ink );
            $pages[$pi][] = array( 'text' => mb_strimwidth( $col6_text, 0, 32, '…' ), 'x' => $col_x[5]+3, 'y' => $ty, 'size' => 7.5, 'font' => 'Helvetica', 'color' => $col6_color );
            $cursor -= $row_h;
        }

        // Footer dernière page
        $pages[$pi][] = array( 'type' => 'rect', 'x' => $left, 'y' => 32, 'width' => $content_w, 'height' => 0.7, 'fill_color' => $line );
        $pages[$pi][] = array( 'text' => '7 avenue Paul Cézanne — 83310 Cogolin — France — Siret : 405109901 00042 — NDA : 93 83 08347 83',
                               'x' => $left, 'y' => 22, 'size' => 6.8, 'font' => 'Helvetica', 'color' => $muted );
        $pages[$pi][] = array( 'text' => 'e-mail : contact@acdc-formation.com — Tél : 06 78 26 91 10 — acdc-formation.com',
                               'x' => $left, 'y' => 12, 'size' => 6.8, 'font' => 'Helvetica', 'color' => $muted );

        // ── Génération PDF ───────────────────────────────────────────────
        $sig_core = new ACDC_Sig_Core();
        $sig_core->init_tables();
        $sig_pdf  = new ACDC_Sig_PDF( $sig_core );
        $ref      = new ReflectionClass( $sig_pdf );
        $method   = $ref->getMethod( 'render_to_string' );
        $method->setAccessible( true );
        $pdf_raw  = $method->invoke( $sig_pdf, $pages );
        if ( ! is_string( $pdf_raw ) || '' === $pdf_raw ) { return false; }

        // ── Stockage ─────────────────────────────────────────────────────
        $uploads = wp_upload_dir();
        if ( empty( $uploads['basedir'] ) ) { return false; }
        $dir_path = trailingslashit( $uploads['basedir'] ) . 'acdc-qz-results/' . $session_id . '/' . $participant_id . '/';
        $dir_url  = trailingslashit( $uploads['baseurl'] ) . 'acdc-qz-results/' . $session_id . '/' . $participant_id . '/';
        if ( ! wp_mkdir_p( $dir_path ) ) { return false; }
        $safe_name = sanitize_file_name( mb_strimwidth( $quiz_title, 0, 40, '' ) );
        $filename  = 'resultats-quiz-' . $safe_name . '-' . date( 'Y-m-d' ) . '.pdf';
        $filepath  = $dir_path . $filename;
        $fileurl   = $dir_url . rawurlencode( $filename );
        if ( ! $sig_core->safe_file_write( $filepath, $pdf_raw, 'quiz result PDF' ) ) { return false; }

        // Sauvegarder URL dans participants
        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$tbl_p} LIKE 'result_document_url'" );
        if ( empty( $cols ) ) {
            $wpdb->query( "ALTER TABLE {$tbl_p} ADD COLUMN result_document_url VARCHAR(500) NULL" );
        }
        $wpdb->update( $tbl_p, array( 'result_document_url' => $fileurl ), array( 'id' => $participant_id ), array( '%s' ), array( '%d' ) );

        // Propager l'URL PDF vers la fiche inscription Qualiopi
        $this->qz_propagate_result_url_to_registration( $p, $session, $fileurl );

        return array( 'url' => $fileurl, 'path' => $filepath );
    }

    /**
     * ACDC hotfix52 — Méthode séparée : propage l'URL d'un résultat vers la fiche
     * inscription correspondante (acdc_of_training_registrations), quel que soit le
     * mode d'obtention de l'URL (PDF généré OU lien vers les résultats en ligne).
     * Ainsi la ligne apparaît dans les onglets Qualiopi même sans PDF.
     *
     * @param object $participant  Ligne acdc_of_qz_participants.
     * @param object $session      Ligne session enrichie (avec quiz_purpose, formation_id).
     * @param string $url          URL à stocker (PDF ou page de résultats).
     */
    public function qz_propagate_result_url_to_registration( $participant, $session, $url ) {
        if ( empty( $url ) || empty( $participant ) || empty( $session ) ) {
            return;
        }
        $qz_purpose = isset( $session->quiz_purpose ) ? (string) $session->quiz_purpose : '';
        $reg_col = '';
        if ( 'positioning' === $qz_purpose ) {
            $reg_col = 'positioning_result_document_url';
        } elseif ( 'assessment' === $qz_purpose ) {
            $reg_col = 'evaluation_result_document_url';
        }
        if ( '' === $reg_col ) {
            return; // live : pas de fiche inscription
        }
        $learner_id   = ! empty( $participant->learner_id ) ? (int) $participant->learner_id : 0;
        $formation_id = ! empty( $session->formation_id )  ? (int) $session->formation_id   : 0;
        if ( $learner_id <= 0 || $formation_id <= 0 ) {
            return;
        }
        global $wpdb;
        $tbl_reg = $wpdb->prefix . 'acdc_of_training_registrations';
        // Inscription la plus récente pour ce couple apprenant + formation
        $reg_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$tbl_reg}
             WHERE learner_id = %d AND formation_id = %d
             ORDER BY id DESC LIMIT 1",
            $learner_id, $formation_id
        ) );
        if ( $reg_id > 0 ) {
            $wpdb->update( $tbl_reg, array( $reg_col => $url ), array( 'id' => $reg_id ), array( '%s' ), array( '%d' ) );
        }
    }

    /**
     * Export Excel (XLSX) des résultats d'une session — 3 onglets.
     * Format identique au modèle Kahoot (Overview / Classement / Détail réponses).
     * Génère un fichier XLSX valide en PHP pur (Open XML / ZIP), sans dépendance externe.
     *
     * @param int $session_id
     * @return string Contenu binaire du fichier XLSX, ou '' si aucune donnée.
     */
    public function export_qz_session_results_excel( $session_id ) {
        $session_id   = (int) $session_id;
        $session      = $this->get_qz_dispatch_session( $session_id );
        if ( ! $session ) { return ''; }
        $participants = $this->get_qz_participants_for_dispatch_session( $session_id );
        $questions    = $this->get_qz_questions_for_quiz( (int) $session->quiz_id );
        if ( empty( $participants ) && empty( $questions ) ) { return ''; }

        // ── Couleurs ────────────────────────────────────────────────────────
        $c_marine   = 'FF0F2C52'; // fond header marine
        $c_dore     = 'FFD6A353'; // fond header doré
        $c_white    = 'FFFFFFFF';
        $c_beige    = 'FFFBF8F7';
        $c_green_bg = 'FFE6F4EA';
        $c_red_bg   = 'FFFDECEA';
        $c_green_tx = 'FF2E7D32';
        $c_red_tx   = 'FFC62828';
        $c_grey_bg  = 'FFF5F5F5';
        $c_marine_tx= 'FF0F2C52';

        // ── Helpers XML ─────────────────────────────────────────────────────
        $xml_esc = function( $v ) {
            return htmlspecialchars( (string) $v, ENT_XML1, 'UTF-8' );
        };

        // Styles (indices 0-based)
        // 0=défaut, 1=titre, 2=header marine/blanc centré wrap, 3=header doré/marine centré wrap,
        // 4=label gras marine gauche, 5=valeur marine gauche,
        // 6=correct vert gras centré wrap, 7=incorrect rouge gras centré wrap,
        // 8=sous-titre doré, 9=données centré wrap marine,
        // 10=données gauche wrap marine, 11=données centré wrap marine (alias 9),
        // 12=données gauche wrap marine (alias 10), 13=données centré sans wrap
        $styles_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="8">
    <font><sz val="11"/><name val="Arial"/></font>
    <font><sz val="16"/><b/><color rgb="' . $c_marine_tx . '"/><name val="Arial"/></font>
    <font><sz val="11"/><b/><color rgb="' . $c_white . '"/><name val="Arial"/></font>
    <font><sz val="11"/><b/><color rgb="' . $c_marine_tx . '"/><name val="Arial"/></font>
    <font><sz val="11"/><color rgb="' . $c_marine_tx . '"/><name val="Arial"/></font>
    <font><sz val="11"/><b/><color rgb="' . $c_green_tx . '"/><name val="Arial"/></font>
    <font><sz val="11"/><b/><color rgb="' . $c_red_tx . '"/><name val="Arial"/></font>
    <font><sz val="12"/><b/><color rgb="' . $c_dore . '"/><name val="Arial"/></font>
  </fonts>
  <fills count="7">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="' . $c_marine . '"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="' . $c_dore . '"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="' . $c_beige . '"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="' . $c_green_bg . '"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="' . $c_red_bg . '"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border><left style="thin"><color rgb="FFD6A353"/></left><right style="thin"><color rgb="FFD6A353"/></right><top style="thin"><color rgb="FFD6A353"/></top><bottom style="thin"><color rgb="FFD6A353"/></bottom><diagonal/></border>
  </borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="14">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"><alignment horizontal="left" vertical="center"/></xf>
    <xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="3" fillId="3" borderId="0" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0"><alignment horizontal="left" vertical="center"/></xf>
    <xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0"><alignment horizontal="left" vertical="center"/></xf>
    <xf numFmtId="0" fontId="5" fillId="5" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="6" fillId="6" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="7" fillId="0" borderId="0" xfId="0"><alignment horizontal="left" vertical="center"/></xf>
    <xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0"><alignment horizontal="center" vertical="center"/></xf>
  </cellXfs>
</styleSheet>';

        // ── Shared strings ───────────────────────────────────────────────────
        $strings      = array();
        $string_index = array();
        $add_str      = function( $val ) use ( &$strings, &$string_index, $xml_esc ) {
            $val = (string) $val;
            if ( ! isset( $string_index[ $val ] ) ) {
                $string_index[ $val ] = count( $strings );
                $strings[]            = '<si><t xml:space="preserve">' . $xml_esc( $val ) . '</t></si>';
            }
            return (int) $string_index[ $val ];
        };

        // ── Cell helpers ─────────────────────────────────────────────────────
        $col_name = function( $n ) { // 1-based → A,B,…
            $l = '';
            while ( $n > 0 ) {
                $n--;
                $l = chr( 65 + ( $n % 26 ) ) . $l;
                $n = intdiv( $n, 26 );
            }
            return $l;
        };
        $cell_s = function( $col, $row, $val, $style ) use ( $col_name, $add_str ) {
            $ref = $col_name( $col ) . $row;
            $idx = $add_str( $val );
            return "<c r=\"{$ref}\" t=\"s\" s=\"{$style}\"><v>{$idx}</v></c>";
        };
        $cell_n = function( $col, $row, $val, $style ) use ( $col_name ) {
            $ref = $col_name( $col ) . $row;
            return "<c r=\"{$ref}\" s=\"{$style}\"><v>" . (float) $val . "</v></c>";
        };

        // ── Données ──────────────────────────────────────────────────────────
        $nb_participants = count( $participants );
        $nb_questions    = count( $questions );
        $played_on       = ! empty( $session->started_at ) ? date( 'd/m/Y', strtotime( $session->started_at ) )
                           : ( ! empty( $session->sent_at ) ? date( 'd/m/Y', strtotime( $session->sent_at ) ) : '—' );
        $hosted_by = '—';
        if ( ! empty( $session->created_by ) ) {
            $user = get_userdata( (int) $session->created_by );
            if ( $user ) {
                $n = trim( $user->first_name . ' ' . $user->last_name );
                $hosted_by = $n ?: $user->display_name;
            }
        }
        if ( empty( $hosted_by ) || '—' === $hosted_by ) {
            $hosted_by = get_bloginfo( 'name' );
        }

        // Stats globales
        global $wpdb;
        $tbl_pa = $this->get_qz_table( 'player_answers' );
        $tbl_q  = $this->get_qz_table( 'questions' );
        $total_answers  = 0;
        $correct_answers= 0;
        $avg_score      = 0;
        if ( ! empty( $participants ) ) {
            $ids = implode( ',', array_map( function($p){ return (int)$p->id; }, $participants ) );
            $stats = $wpdb->get_row( "SELECT COUNT(*) as total, SUM(CASE WHEN is_correct=1 THEN 1 ELSE 0 END) as correct FROM {$tbl_pa} WHERE participant_id IN ({$ids}) AND is_correct IS NOT NULL" );
            if ( $stats ) {
                $total_answers   = (int) $stats->total;
                $correct_answers = (int) $stats->correct;
            }
            // ACDC 3.25.113 — conserver les scores 0 (sinon moyenne gonflée).
            $scores = array_filter( array_map( static function( $p ) { return $p->total_score; }, $participants ), static function( $v ) { return null !== $v && '' !== $v; } );
            if ( ! empty( $scores ) ) {
                $avg_score = round( array_sum( $scores ) / count( $scores ), 1 );
            }
        }
        $pct_correct   = $total_answers > 0 ? round( $correct_answers / $total_answers * 100, 2 ) : 0;
        $pct_incorrect = 100 - $pct_correct;

        // Réponses par participant
        $answers_map = array();
        foreach ( $participants as $p ) {
            $answers_map[ (int) $p->id ] = $this->get_qz_answers_by_participant( (int) $p->id );
        }

        // Trier par score décroissant
        usort( $participants, function( $a, $b ) {
            return (float) $b->total_score <=> (float) $a->total_score;
        } );

        // ════════════════════════════════════════════════════════════════════
        // SHEET 1 : Résumé
        // ════════════════════════════════════════════════════════════════════
        $s1 = '';
        $s1 .= '<row r="1" ht="32" customHeight="1"><c r="A1" t="s" s="1"><v>' . $add_str( $session->quiz_title ) . '</v></c></row>';
        $r = 3;
        $meta = array(
            array( 'Joué le',      $played_on ),
            array( 'Animé par',    $hosted_by ),
            array( 'Participants', (string) $nb_participants . ' joueur' . ( $nb_participants > 1 ? 's' : '' ) ),
            array( 'Questions',    (string) $nb_questions . ' question' . ( $nb_questions > 1 ? 's' : '' ) ),
        );
        foreach ( $meta as $m ) {
            $s1 .= '<row r="' . $r . '">';
            $s1 .= $cell_s( 1, $r, $m[0], 4 );
            $s1 .= $cell_s( 2, $r, $m[1], 5 );
            $s1 .= '</row>';
            $r++;
        }
        $r++;
        $s1 .= '<row r="' . $r . '">' . $cell_s( 1, $r, 'Performance globale', 8 ) . '</row>';
        $r += 2;
        $kpis1 = array(
            array( 'Réponses correctes (%)',   $pct_correct ),
            array( 'Réponses incorrectes (%)', $pct_incorrect ),
            array( 'Score moyen (points)',      $avg_score ),
        );
        foreach ( $kpis1 as $k ) {
            $s1 .= '<row r="' . $r . '">';
            $s1 .= $cell_s( 1, $r, $k[0], 5 );
            $s1 .= $cell_n( 2, $r, $k[1], 13 ); // KPI valeur : centré sans wrap
            $s1 .= '</row>';
            $r++;
        }
        $sheet1_cols = '<col min="1" max="1" width="32" customWidth="1"/><col min="2" max="2" width="22" customWidth="1"/>';

        // ════════════════════════════════════════════════════════════════════
        // SHEET 2 : Classement
        // ════════════════════════════════════════════════════════════════════
        $s2     = '';
        $s2_cols= '';
        // Ligne 1 : titre
        $s2 .= '<row r="1" ht="28" customHeight="1"><c r="A1" t="s" s="1"><v>' . $add_str( $session->quiz_title ) . '</v></c></row>';
        // Ligne 3 : headers
        $col = 1;
        $h_row = '<row r="3" ht="40" customHeight="1">';
        foreach ( array( 'Rang', 'Pseudo', 'Apprenant', 'Score total' ) as $h ) {
            $h_row .= $cell_s( $col, 3, $h, 2 );
            $col++;
        }
        foreach ( $questions as $qi => $q ) {
            $h_row .= $cell_s( $col, 3, 'Q' . ( $qi + 1 ) . ' : ' . $q->title, 3 );
            $col++;
        }
        $h_row .= '</row>';
        $s2 .= $h_row;
        // Lignes participants
        // Styles : 11=centré wrap, 12=gauche wrap, 6=correct, 7=incorrect
        $rank = 1;
        foreach ( $participants as $p ) {
            $rrow = 3 + $rank;
            $s2 .= '<row r="' . $rrow . '">';
            $s2 .= $cell_n( 1, $rrow, $rank, 11 );          // Rang : centré wrap
            $s2 .= $cell_s( 2, $rrow, $p->nickname ?: '—', 12 );  // Pseudo : gauche wrap
            $s2 .= $cell_s( 3, $rrow, $p->full_name ?: $p->email ?: '—', 12 ); // Apprenant : gauche wrap
            $s2 .= $cell_n( 4, $rrow, (float) $p->total_score, 11 ); // Score : centré wrap
            $pcol = 5;
            $pa   = isset( $answers_map[ (int) $p->id ] ) ? $answers_map[ (int) $p->id ] : array();
            foreach ( $questions as $q ) {
                $a = isset( $pa[ (int) $q->id ] ) ? $pa[ (int) $q->id ] : null;
                if ( ! $a ) {
                    $s2 .= $cell_s( $pcol, $rrow, '—', 11 );
                } elseif ( (int) $a->is_correct === 1 ) {
                    $s2 .= $cell_s( $pcol, $rrow, $this->format_qz_answer_for_export( $q, $a ), 6 );
                } elseif ( (int) $a->is_correct === 0 ) {
                    $s2 .= $cell_s( $pcol, $rrow, $this->format_qz_answer_for_export( $q, $a ), 7 );
                } else {
                    $s2 .= $cell_s( $pcol, $rrow, $this->format_qz_answer_for_export( $q, $a ), 11 );
                }
                $pcol++;
            }
            $s2 .= '</row>';
            $rank++;
        }
        // Largeurs colonnes sheet 2
        $s2_cols = '<col min="1" max="1" width="8" customWidth="1"/>'
                 . '<col min="2" max="2" width="18" customWidth="1"/>'
                 . '<col min="3" max="3" width="24" customWidth="1"/>'
                 . '<col min="4" max="4" width="14" customWidth="1"/>';
        for ( $ci = 5; $ci < 5 + $nb_questions; $ci++ ) {
            $s2_cols .= '<col min="' . $ci . '" max="' . $ci . '" width="22" customWidth="1"/>';
        }

        // ════════════════════════════════════════════════════════════════════
        // SHEET 3 : Détail réponses
        // ════════════════════════════════════════════════════════════════════
        $s3 = '';
        $headers3 = array(
            'N° Question', 'Question', 'Bonne réponse', 'Temps alloué (s)',
            'Pseudo', 'Apprenant', 'Réponse donnée', 'Correct / Incorrect',
            'Points', 'Score cumulé', 'Temps réponse (s)',
        );
        $s3 .= '<row r="1" ht="36" customHeight="1">';
        foreach ( $headers3 as $ci => $h ) {
            $s3 .= $cell_s( $ci + 1, 1, $h, 2 );
        }
        $s3 .= '</row>';

        $drow = 2;
        // Construire correct_ids par question une seule fois
        $correct_ids_map = array();
        foreach ( $questions as $q ) {
            $correct_ids_map[ (int) $q->id ] = array();
            if ( isset( $q->answers ) && is_array( $q->answers ) ) {
                foreach ( $q->answers as $ans ) {
                    if ( (int) $ans->is_correct === 1 ) {
                        $correct_ids_map[ (int) $q->id ][] = $ans->text;
                    }
                }
            }
        }

        foreach ( $questions as $qi => $q ) {
            $correct_text = implode( ' / ', $correct_ids_map[ (int) $q->id ] );
            foreach ( $participants as $p ) {
                $pa  = isset( $answers_map[ (int) $p->id ] ) ? $answers_map[ (int) $p->id ] : array();
                $a   = isset( $pa[ (int) $q->id ] ) ? $pa[ (int) $q->id ] : null;
                $ans_text      = $a ? $this->format_qz_answer_for_export( $q, $a ) : '';
                $is_correct    = $a ? ( null === $a->is_correct ? 'Non noté' : ( (int) $a->is_correct === 1 ? 'Correct' : 'Incorrect' ) ) : '—';
                $score_pts     = $a ? (float) $a->score_earned : 0;
                $score_cumul   = 0;
                // Score cumulé = somme des points de toutes les questions jusqu'ici pour ce participant
                foreach ( $questions as $qb ) {
                    if ( $qb->sort_order > $q->sort_order ) break;
                    $ab = isset( $pa[ (int) $qb->id ] ) ? $pa[ (int) $qb->id ] : null;
                    if ( $ab ) $score_cumul += (float) $ab->score_earned;
                }
                $resp_time = $a && ! empty( $a->response_time ) ? round( (float) $a->response_time, 2 ) : 0;

                // Styles réponse : 6=correct (vert gras centré wrap), 7=incorrect/non noté (rouge)
                $style_ans = ( (int) ( $a ? $a->is_correct : null ) === 1 ) ? 6 : 7;

                $s3 .= '<row r="' . $drow . '">';
                $s3 .= $cell_n( 1,  $drow, $qi + 1,                                   11 ); // N° : centré wrap
                $s3 .= $cell_s( 2,  $drow, $q->title,                                 12 ); // Question : gauche wrap
                $s3 .= $cell_s( 3,  $drow, $correct_text,                             12 ); // Bonne réponse : gauche wrap
                $s3 .= $cell_n( 4,  $drow, (int) $q->time_limit,                      11 ); // Temps : centré wrap
                $s3 .= $cell_s( 5,  $drow, $p->nickname ?: '—',                       12 ); // Pseudo : gauche wrap
                $s3 .= $cell_s( 6,  $drow, $p->full_name ?: $p->email ?: '—',        12 ); // Apprenant : gauche wrap
                $s3 .= $cell_s( 7,  $drow, $ans_text,                                 $style_ans ); // Réponse : coloré
                $s3 .= $cell_s( 8,  $drow, $is_correct,                               $style_ans ); // Correct/Inc : coloré
                $s3 .= $cell_n( 9,  $drow, $score_pts,                                11 ); // Points : centré wrap
                $s3 .= $cell_n( 10, $drow, $score_cumul,                              11 ); // Score cumulé : centré wrap
                $s3 .= $cell_n( 11, $drow, $resp_time,                                11 ); // Temps réponse : centré wrap
                $s3 .= '</row>';
                $drow++;
            }
        }
        // Largeurs sheet3
        $s3_cols = '<col min="1" max="1" width="12" customWidth="1"/>'
                 . '<col min="2" max="2" width="40" customWidth="1"/>'
                 . '<col min="3" max="3" width="30" customWidth="1"/>'
                 . '<col min="4" max="4" width="16" customWidth="1"/>'
                 . '<col min="5" max="5" width="18" customWidth="1"/>'
                 . '<col min="6" max="6" width="24" customWidth="1"/>'
                 . '<col min="7" max="7" width="30" customWidth="1"/>'
                 . '<col min="8" max="8" width="18" customWidth="1"/>'
                 . '<col min="9" max="9" width="12" customWidth="1"/>'
                 . '<col min="10" max="10" width="14" customWidth="1"/>'
                 . '<col min="11" max="11" width="18" customWidth="1"/>';

        // ── Shared strings XML ────────────────────────────────────────────
        $nb_strings   = count( $strings );
        $shared_str_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $nb_strings . '" uniqueCount="' . $nb_strings . '">'
            . implode( '', $strings )
            . '</sst>';

        // ── Sheets XML ────────────────────────────────────────────────────
        $sheet_wrap = function( $cols, $data, $merges = '' ) {
            return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<sheetView showGridLines="0" workbookViewId="0"/>'
                . '<sheetFormatPr defaultRowHeight="18"/>'
                . ( $cols ? '<cols>' . $cols . '</cols>' : '' )
                . '<sheetData>' . $data . '</sheetData>'
                . ( $merges ? '<mergeCells>' . $merges . '</mergeCells>' : '' )
                . '</worksheet>';
        };

        $sheet1_merges = '<mergeCell ref="A1:D1"/>';
        $sheet2_merges = '<mergeCell ref="A1:D1"/>';

        $xml_sheet1 = $sheet_wrap( $sheet1_cols, $s1, $sheet1_merges );
        $xml_sheet2 = $sheet_wrap( $s2_cols, $s2, $sheet2_merges );
        $xml_sheet3 = $sheet_wrap( $s3_cols, $s3 );

        // ── Workbook XML ─────────────────────────────────────────────────
        $workbook_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>'
            . '<sheet name="Résumé" sheetId="1" r:id="rId1"/>'
            . '<sheet name="Classement" sheetId="2" r:id="rId2"/>'
            . '<sheet name="Détail réponses" sheetId="3" r:id="rId3"/>'
            . '</sheets>'
            . '</workbook>';

        $workbook_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/>'
            . '<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '<Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';

        $content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';

        $root_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        // ── Build ZIP ──────────────────────────────────────────────────────
        if ( ! class_exists( 'ZipArchive' ) ) { return ''; }
        $tmp = tempnam( sys_get_temp_dir(), 'acdc_qz_' ) . '.xlsx';
        $zip = new ZipArchive();
        if ( $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) { return ''; }
        $zip->addFromString( '[Content_Types].xml',              $content_types );
        $zip->addFromString( '_rels/.rels',                      $root_rels );
        $zip->addFromString( 'xl/workbook.xml',                  $workbook_xml );
        $zip->addFromString( 'xl/_rels/workbook.xml.rels',       $workbook_rels );
        $zip->addFromString( 'xl/worksheets/sheet1.xml',         $xml_sheet1 );
        $zip->addFromString( 'xl/worksheets/sheet2.xml',         $xml_sheet2 );
        $zip->addFromString( 'xl/worksheets/sheet3.xml',         $xml_sheet3 );
        $zip->addFromString( 'xl/sharedStrings.xml',             $shared_str_xml );
        $zip->addFromString( 'xl/styles.xml',                    $styles_xml );
        $zip->close();
        $content = file_get_contents( $tmp );
        unlink( $tmp );
        return $content !== false ? $content : '';
    }

    /**
     * Export CSV des résultats d'une session (conservé comme fallback).
     */
    public function export_qz_session_results_csv( $session_id ) {
        $session_id = (int) $session_id;
        $session = $this->get_qz_dispatch_session( $session_id );
        if ( ! $session ) {
            return '';
        }
        $participants = $this->get_qz_participants_for_dispatch_session( $session_id );
        $questions    = $this->get_qz_questions_for_quiz( (int) $session->quiz_id );

        // Construction des lignes
        $rows = array();

        // Ligne d'en-têtes
        $headers = array(
            'Nom complet', 'E-mail', 'Statut',
            'Invité le', 'Démarré le', 'Terminé le', 'Durée (minutes)',
            'Score brut', 'Score %',
        );
        foreach ( $questions as $q ) {
            $headers[] = sprintf( 'Q%d : %s', (int) $q->sort_order + 1, $q->title );
            $headers[] = sprintf( 'Q%d : Correct', (int) $q->sort_order + 1 );
        }
        $rows[] = $headers;

        $status_labels = array(
            'pending'     => 'En attente',
            'invited'     => 'Invité',
            'opened'      => 'Ouvert',
            'in_progress' => 'En cours',
            'completed'   => 'Terminé',
            'expired'     => 'Expiré',
            'cancelled'   => 'Annulé',
        );

        foreach ( $participants as $p ) {
            $duration = '';
            if ( ! empty( $p->started_at ) && ! empty( $p->completed_at ) ) {
                $duration = round( ( strtotime( $p->completed_at ) - strtotime( $p->started_at ) ) / 60, 1 );
            }
            $row = array(
                (string) $p->full_name,
                (string) $p->email,
                isset( $status_labels[ $p->status ] ) ? $status_labels[ $p->status ] : (string) $p->status,
                (string) $p->invited_at,
                (string) $p->started_at,
                (string) $p->completed_at,
                (string) $duration,
                null === $p->total_score ? '' : number_format( (float) $p->total_score, 2, ',', '' ),
                null === $p->total_score_percentage ? '' : number_format( (float) $p->total_score_percentage, 1, ',', '' ),
            );

            // Réponses par question
            $answers_by_q = $this->get_qz_answers_by_participant( (int) $p->id );
            foreach ( $questions as $q ) {
                $a = isset( $answers_by_q[ (int) $q->id ] ) ? $answers_by_q[ (int) $q->id ] : null;
                if ( ! $a ) {
                    $row[] = '';
                    $row[] = '';
                    continue;
                }
                $row[] = $this->format_qz_answer_for_export( $q, $a );
                $row[] = ( null === $a->is_correct ) ? 'Non noté' : ( (int) $a->is_correct === 1 ? 'Oui' : 'Non' );
            }
            $rows[] = $row;
        }

        // Encode CSV avec BOM UTF-8 pour Excel
        $output = "\xEF\xBB\xBF";
        foreach ( $rows as $r ) {
            $escaped = array();
            foreach ( $r as $cell ) {
                $cell = (string) $cell;
                if ( false !== strpos( $cell, '"' ) || false !== strpos( $cell, ';' ) || false !== strpos( $cell, "\n" ) ) {
                    $cell = '"' . str_replace( '"', '""', $cell ) . '"';
                }
                $escaped[] = $cell;
            }
            $output .= implode( ';', $escaped ) . "\r\n";
        }
        return $output;
    }

    /**
     * Formatte la réponse d'un apprenant pour l'export CSV (texte lisible).
     */
    private function format_qz_answer_for_export( $question, $player_answer ) {
        if ( self::ACDC_OF_QZ_QTYPE_OPEN_TEXT === $question->type ) {
            return (string) $player_answer->answer_text;
        }
        $answer_ids = array();
        if ( ! empty( $player_answer->answer_ids_json ) ) {
            $decoded = json_decode( (string) $player_answer->answer_ids_json, true );
            if ( is_array( $decoded ) ) {
                $answer_ids = array_map( 'intval', $decoded );
            }
        } elseif ( ! empty( $player_answer->answer_id ) ) {
            $answer_ids = array( (int) $player_answer->answer_id );
        }
        if ( empty( $answer_ids ) ) {
            return '';
        }
        $all_answers = $this->get_qz_answers_for_question_public( (int) $question->id );
        $by_id = array();
        foreach ( $all_answers as $a ) {
            $by_id[ (int) $a->id ] = (string) $a->text;
        }
        $labels = array();
        foreach ( $answer_ids as $aid ) {
            if ( isset( $by_id[ $aid ] ) ) {
                $labels[] = $by_id[ $aid ];
            }
        }
        // Pour le Puzzle, l'ordre compte
        if ( self::ACDC_OF_QZ_QTYPE_PUZZLE === $question->type ) {
            return implode( ' → ', $labels );
        }
        return implode( ' | ', $labels );
    }

    /* ====================================================================
     *  Import IA — 3.21.30
     * ==================================================================== */

    /**
     * Importe des questions (et optionnellement un quiz + objectifs) depuis
     * un tableau PHP issu d'un JSON ACDC validé.
     *
     * Mode A : $quiz_id fourni, $formation_id et $purpose ignorés.
     *          Seul le bloc "questions" du JSON est lu.
     * Mode B : $quiz_id = 0, $formation_id et $purpose obligatoires.
     *          Les blocs "quiz", "objectives" et "questions" sont lus.
     *
     * @param array  $data         Contenu associatif décodé du JSON.
     * @param int    $quiz_id      0 = Mode B (création). >0 = Mode A (ajout).
     * @param int    $formation_id Requis en Mode B.
     * @param string $purpose      'live'|'positioning'|'assessment'. Requis en Mode B.
     * @param int    $timer_global Secondes appliquées à toutes les questions (10-300).
     * @param bool   $dry_run      Si true : parse et valide sans insérer (pour l'aperçu).
     *
     * @return array {
     *   success      : bool,
     *   quiz_id      : int,
     *   imported     : int,
     *   skipped      : int,
     *   objectives   : int,
     *   warnings     : array,
     *   questions    : array  (utilisé par dry_run pour l'aperçu)
     * }
     */
    public function import_qz_from_json( $data, $quiz_id, $formation_id, $purpose, $timer_global, $dry_run = false ) {

        $result = array(
            'success'    => false,
            'quiz_id'    => 0,
            'imported'   => 0,
            'skipped'    => 0,
            'objectives' => 0,
            'warnings'   => array(),
            'questions'  => array(),
        );

        // --- Clamp timer ---
        $timer_global = max( 10, min( 300, (int) $timer_global ) );

        // --- Vérification version ---
        if ( empty( $data['acdc_quiz_import_version'] ) ) {
            $result['warnings'][] = __( 'Fichier non reconnu — utilisez le prompt officiel ACDC.', 'acdc-formation-saas' );
            return $result;
        }

        $questions_raw = isset( $data['questions'] ) && is_array( $data['questions'] ) ? $data['questions'] : array();
        if ( empty( $questions_raw ) ) {
            $result['warnings'][] = __( 'Le fichier ne contient aucune question.', 'acdc-formation-saas' );
            return $result;
        }
        if ( count( $questions_raw ) > 100 ) {
            $result['warnings'][] = __( 'Maximum 100 questions par import.', 'acdc-formation-saas' );
            return $result;
        }

        $mode_b = ( (int) $quiz_id <= 0 );

        // --- Mode A : vérifier quiz existant et verrou ---
        if ( ! $mode_b ) {
            $quiz_id = (int) $quiz_id;
            if ( $this->is_qz_quiz_locked( $quiz_id ) ) {
                $result['warnings'][] = __( 'Ce quiz est verrouillé et ne peut plus être modifié.', 'acdc-formation-saas' );
                return $result;
            }
            if ( ! $this->get_qz_quiz( $quiz_id ) ) {
                $result['warnings'][] = __( 'Quiz introuvable.', 'acdc-formation-saas' );
                return $result;
            }
        }

        // --- Mode B : préparer quiz + objectifs ---
        $obj_ref_map = array(); // 'obj_1' => db_id

        if ( $mode_b && ! $dry_run ) {
            $formation_id = (int) $formation_id;
            if ( $formation_id <= 0 || ! $this->is_valid_quiz_purpose( $purpose ) ) {
                $result['warnings'][] = __( 'Formation ou type de quiz manquant.', 'acdc-formation-saas' );
                return $result;
            }
            $quiz_meta    = isset( $data['quiz'] ) && is_array( $data['quiz'] ) ? $data['quiz'] : array();
            $quiz_title   = isset( $quiz_meta['title'] ) ? trim( (string) $quiz_meta['title'] ) : '';
            $quiz_desc    = isset( $quiz_meta['description'] ) ? (string) $quiz_meta['description'] : '';
            if ( '' === $quiz_title ) {
                $quiz_title = __( 'Quiz importé', 'acdc-formation-saas' );
            }
            $new_quiz_id = $this->insert_qz_quiz( array(
                'formation_id' => $formation_id,
                'quiz_purpose' => $purpose,
                'title'        => $quiz_title,
                'description'  => $quiz_desc,
            ) );
            if ( ! $new_quiz_id ) {
                $result['warnings'][] = __( 'Impossible de créer le quiz.', 'acdc-formation-saas' );
                return $result;
            }
            $quiz_id = (int) $new_quiz_id;

            // Insérer les objectifs
            $objectives_raw = isset( $data['objectives'] ) && is_array( $data['objectives'] ) ? $data['objectives'] : array();
            $obj_sort       = 0;
            foreach ( array_slice( $objectives_raw, 0, 10 ) as $obj ) {
                $obj_label = isset( $obj['label'] ) ? trim( (string) $obj['label'] ) : '';
                $obj_ref   = isset( $obj['ref'] )   ? (string) $obj['ref']           : '';
                if ( '' === $obj_label || '' === $obj_ref ) {
                    continue;
                }
                $obj_id = $this->upsert_qz_objective( $quiz_id, array(
                    'label'       => $obj_label,
                    'description' => isset( $obj['description'] ) ? (string) $obj['description'] : '',
                    'sort_order'  => $obj_sort++,
                ) );
                if ( $obj_id ) {
                    $obj_ref_map[ $obj_ref ] = (int) $obj_id;
                    $result['objectives']++;
                }
            }
        }

        // --- Calcul du sort_order de départ (Mode A) ---
        $base_sort = 0;
        if ( ! $mode_b && ! $dry_run ) {
            global $wpdb;
            $tbl_q = $this->get_qz_table( 'questions' );
            if ( '' !== $tbl_q ) {
                $max = $wpdb->get_var( $wpdb->prepare(
                    "SELECT MAX(sort_order) FROM {$tbl_q} WHERE quiz_id = %d",
                    $quiz_id
                ) );
                $base_sort = is_null( $max ) ? 0 : (int) $max + 1;
            }
        }

        // --- Types valides ---
        $valid_types = array(
            self::ACDC_OF_QZ_QTYPE_QCM_SINGLE,
            self::ACDC_OF_QZ_QTYPE_QCM_MULTIPLE,
            self::ACDC_OF_QZ_QTYPE_TRUE_FALSE,
            self::ACDC_OF_QZ_QTYPE_OPEN_TEXT,
            self::ACDC_OF_QZ_QTYPE_POLL,
            self::ACDC_OF_QZ_QTYPE_PUZZLE,
        );

        // --- Parser et insérer les questions ---
        $sort_offset = 0;
        foreach ( $questions_raw as $idx => $raw ) {
            $num     = (int) $idx + 1;
            $q_warn  = array();
            $skip    = false;

            $type  = isset( $raw['type'] )  ? (string) $raw['type']  : '';
            $title = isset( $raw['title'] ) ? trim( (string) $raw['title'] ) : '';

            if ( ! in_array( $type, $valid_types, true ) ) {
                $result['warnings'][] = sprintf( __( 'Question %d ignorée : type "%s" inconnu.', 'acdc-formation-saas' ), $num, esc_html( $type ) );
                $result['skipped']++;
                continue;
            }
            if ( '' === $title ) {
                $result['warnings'][] = sprintf( __( 'Question %d ignorée : titre vide.', 'acdc-formation-saas' ), $num );
                $result['skipped']++;
                continue;
            }

            $description     = isset( $raw['description'] ) && null !== $raw['description'] ? (string) $raw['description'] : '';
            $expected_answer = isset( $raw['expected_answer'] ) ? (string) $raw['expected_answer'] : null;
            $obj_ref         = isset( $raw['objective_ref'] ) ? (string) $raw['objective_ref'] : '';
            $objective_id    = ( '' !== $obj_ref && isset( $obj_ref_map[ $obj_ref ] ) ) ? $obj_ref_map[ $obj_ref ] : null;

            // Normaliser les réponses
            $answers_raw = isset( $raw['answers'] ) && is_array( $raw['answers'] ) ? $raw['answers'] : array();
            $answers     = array();

            if ( self::ACDC_OF_QZ_QTYPE_TRUE_FALSE === $type ) {
                // Injecter Vrai/Faux si absent ou mal formé
                $has_vrai = false;
                $has_faux = false;
                $vrai_correct = false;
                $faux_correct = false;
                foreach ( $answers_raw as $a ) {
                    $t = isset( $a['text'] ) ? trim( (string) $a['text'] ) : '';
                    if ( 'Vrai' === $t ) { $has_vrai = true; $vrai_correct = ! empty( $a['is_correct'] ); }
                    if ( 'Faux' === $t ) { $has_faux = true; $faux_correct = ! empty( $a['is_correct'] ); }
                }
                if ( ! $has_vrai || ! $has_faux ) {
                    $q_warn[] = __( 'Réponses Vrai/Faux auto-injectées.', 'acdc-formation-saas' );
                    $answers  = array(
                        array( 'text' => 'Vrai', 'is_correct' => false, 'feedback_text' => null ),
                        array( 'text' => 'Faux', 'is_correct' => true,  'feedback_text' => null ),
                    );
                } else {
                    foreach ( $answers_raw as $a ) {
                        $t = isset( $a['text'] ) ? trim( (string) $a['text'] ) : '';
                        if ( 'Vrai' === $t || 'Faux' === $t ) {
                            $answers[] = array(
                                'text'          => $t,
                                'is_correct'    => ! empty( $a['is_correct'] ),
                                'feedback_text' => isset( $a['feedback_text'] ) ? (string) $a['feedback_text'] : null,
                            );
                        }
                    }
                }
            } elseif ( self::ACDC_OF_QZ_QTYPE_OPEN_TEXT === $type ) {
                $answers = array();
            } elseif ( self::ACDC_OF_QZ_QTYPE_POLL === $type ) {
                foreach ( $answers_raw as $a ) {
                    $t = isset( $a['text'] ) ? trim( (string) $a['text'] ) : '';
                    if ( '' === $t ) { continue; }
                    $answers[] = array(
                        'text'          => $t,
                        'is_correct'    => false, // forcé
                        'feedback_text' => null,
                    );
                }
            } else {
                // qcm_single, qcm_multiple, puzzle
                $correct_count = 0;
                foreach ( $answers_raw as $a ) {
                    $t = isset( $a['text'] ) ? trim( (string) $a['text'] ) : '';
                    if ( '' === $t ) { continue; }
                    $is_correct = ! empty( $a['is_correct'] );
                    if ( $is_correct ) { $correct_count++; }
                    $answers[] = array(
                        'text'          => $t,
                        'is_correct'    => $is_correct,
                        'feedback_text' => isset( $a['feedback_text'] ) && null !== $a['feedback_text'] ? (string) $a['feedback_text'] : null,
                    );
                }
                if ( self::ACDC_OF_QZ_QTYPE_QCM_SINGLE === $type && 1 !== $correct_count ) {
                    $q_warn[] = sprintf( __( 'QCM : %d bonne(s) réponse(s) au lieu de 1 exacte.', 'acdc-formation-saas' ), $correct_count );
                }
                if ( self::ACDC_OF_QZ_QTYPE_QCM_MULTIPLE === $type && $correct_count < 2 ) {
                    $q_warn[] = __( 'QCM multiple : moins de 2 bonnes réponses.', 'acdc-formation-saas' );
                }
            }

            // Ajouter les avertissements de cette question au résultat global
            foreach ( $q_warn as $w ) {
                $result['warnings'][] = sprintf( __( 'Question %d : ', 'acdc-formation-saas' ), $num ) . $w;
            }

            // Construire la question pour l'aperçu
            $q_preview = array(
                'num'             => $num,
                'type'            => $type,
                'title'           => $title,
                'description'     => $description,
                'expected_answer' => $expected_answer,
                'objective_ref'   => $obj_ref,
                'answers'         => $answers,
                'warnings'        => $q_warn,
                'skip'            => $skip,
            );
            $result['questions'][] = $q_preview;

            if ( $skip || $dry_run ) {
                if ( $skip ) { $result['skipped']++; }
                continue;
            }

            // --- Insertion BDD ---
            $insert_data = array(
                'type'            => $type,
                'title'           => $title,
                'description'     => '' !== $description ? $description : null,
                // ACDC 3.25.168 — L'import appliquait le chronomètre global à toutes les
                // questions, réponses rédigées comprises. Zéro = pas de limite.
                'time_limit'      => ( self::ACDC_OF_QZ_QTYPE_OPEN_TEXT === $type ) ? 0 : $timer_global,
                'sort_order'      => $base_sort + $sort_offset,
                'is_scored'       => in_array( $type, array( self::ACDC_OF_QZ_QTYPE_POLL, self::ACDC_OF_QZ_QTYPE_OPEN_TEXT ), true ) ? 0 : 1,
                'points_type'     => 'standard',
                'points_value'    => 1000,
                'answers'         => $answers,
            );
            if ( null !== $objective_id ) {
                $insert_data['objective_id'] = $objective_id;
            }
            if ( null !== $expected_answer && '' !== $expected_answer ) {
                $insert_data['expected_answer'] = $expected_answer;
            }

            $inserted = $this->upsert_qz_question( $quiz_id, $insert_data );
            if ( $inserted ) {
                $result['imported']++;
                $sort_offset++;
            } else {
                $result['skipped']++;
                $result['warnings'][] = sprintf( __( 'Question %d : erreur d\'insertion en base.', 'acdc-formation-saas' ), $num );
            }
        }

        // --- Rollback Mode B si aucune question insérée ---
        if ( $mode_b && ! $dry_run && 0 === $result['imported'] && $quiz_id > 0 ) {
            $this->delete_qz_quiz( $quiz_id );
            $quiz_id = 0;
            $result['warnings'][] = __( 'L\'import a échoué. Aucune donnée n\'a été enregistrée.', 'acdc-formation-saas' );
        } else {
            $result['success'] = true;
            $result['quiz_id'] = $quiz_id;
        }

        return $result;
    }
}
