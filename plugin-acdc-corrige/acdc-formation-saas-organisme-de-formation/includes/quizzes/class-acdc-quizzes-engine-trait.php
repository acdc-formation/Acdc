<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * ACDC Formation SAAS — Module Quizzes (Engine trait)
 *
 * Couvre :
 *  - Moteur de jeu commun aux modes live et async (logique partagée).
 *  - Calcul du score (formule Kahoot-like).
 *  - Génération du PIN, validation des tokens.
 *
 * En 3.21.00, ce trait expose principalement les signatures et stubs.
 * Les implémentations seront ajoutées en 3.21.05+ (live) et 3.21.03+ (async).
 *
 * @package ACDC_OF_SAAS
 * @since   3.21.00
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Quizzes_Engine_Trait {

    /* -------------------------------------------------------------------- */
    /*  PIN — génération unique pour les sessions live                      */
    /* -------------------------------------------------------------------- */

    /**
     * Génère un PIN à 6 chiffres unique parmi les sessions actives.
     *
     * @param int $max_attempts Nombre maximal d'essais avant abandon (sécurité).
     *
     * @return string|false PIN à 6 chiffres ou false si génération impossible.
     */
    public function generate_unique_qz_pin( $max_attempts = 20 ) {
        global $wpdb;
        $tbl = $this->get_qz_table( 'sessions' );
        if ( '' === $tbl ) {
            return false;
        }

        // Vérification d'existence défensive.
        $exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $tbl ) );
        if ( $exists !== $tbl ) {
            return false;
        }

        $length = (int) apply_filters( 'acdc_of_qz_pin_length', 6 );
        $length = max( 4, min( $length, 10 ) );

        for ( $i = 0; $i < $max_attempts; $i++ ) {
            $pin = $this->random_numeric_pin( $length );
            // Doit être inutilisé, ou utilisé uniquement par des sessions terminées/annulées.
            $taken = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tbl}
                 WHERE pin_code = %s
                 AND status IN ('draft','lobby','active','paused')",
                $pin
            ) );
            if ( 0 === $taken ) {
                return $pin;
            }
        }
        return false;
    }

    /**
     * Génère une chaîne numérique aléatoire de la longueur demandée.
     *
     * @param int $length
     *
     * @return string
     */
    private function random_numeric_pin( $length ) {
        $length = max( 4, (int) $length );
        $digits = '';
        for ( $i = 0; $i < $length; $i++ ) {
            $digits .= (string) wp_rand( 0, 9 );
        }
        return $digits;
    }

    /* -------------------------------------------------------------------- */
    /*  Scoring — formule Kahoot-like                                       */
    /* -------------------------------------------------------------------- */

    /**
     * Calcule le score d'une réponse selon la rapidité.
     *
     * Formule par défaut, alignée sur la convention Kahoot :
     *     score = points_value * (1 - response_time / time_limit / 2)
     *
     * - Score plein (points_value) si répondu instantanément.
     * - Score = points_value / 2 si répondu pile à la limite.
     * - Score = 0 si réponse incorrecte ou question non scorée.
     *
     * @param array $context {
     *   @type bool   $is_correct
     *   @type bool   $is_scored      Si false, retourne 0.
     *   @type string $points_type    'standard' | 'double' | 'none'
     *   @type int    $points_value
     *   @type int    $time_limit     en secondes
     *   @type float  $response_time  en secondes
     * }
     *
     * @return float
     */
    public function calculate_qz_score( $context ) {
        $is_correct    = ! empty( $context['is_correct'] );
        $is_scored     = ! empty( $context['is_scored'] );
        $points_type   = isset( $context['points_type'] ) ? (string) $context['points_type'] : 'standard';
        $points_value  = isset( $context['points_value'] ) ? (int) $context['points_value'] : 1000;
        $time_limit    = isset( $context['time_limit'] ) ? (int) $context['time_limit'] : 20;
        $response_time = isset( $context['response_time'] ) ? (float) $context['response_time'] : 0.0;

        if ( ! $is_scored || ! $is_correct || 'none' === $points_type ) {
            return 0.0;
        }
        if ( $time_limit <= 0 ) {
            $time_limit = 20;
        }
        if ( $response_time < 0 ) {
            $response_time = 0.0;
        }
        if ( $response_time > $time_limit ) {
            $response_time = (float) $time_limit;
        }

        $ratio    = $response_time / $time_limit;
        $base     = $points_value * ( 1 - ( $ratio / 2 ) );
        $modifier = ( 'double' === $points_type ) ? 2 : 1;

        $score = round( $base * $modifier, 2 );

        // Filtre d'extension pour permettre une formule personnalisée.
        return (float) apply_filters( 'acdc_of_qz_score_calculation', $score, $context );
    }

    /* -------------------------------------------------------------------- */
    /*  Helpers de timing                                                   */
    /* -------------------------------------------------------------------- */

    /**
     * Retourne le timestamp Unix actuel en haute précision (secondes flottantes).
     *
     * @return float
     */
    public function qz_microtime_now() {
        return microtime( true );
    }

    /**
     * Calcule la différence en secondes (float) entre maintenant et un instant.
     *
     * @param float $started_at Timestamp Unix flottant.
     *
     * @return float
     */
    public function qz_seconds_since( $started_at ) {
        return max( 0.0, $this->qz_microtime_now() - (float) $started_at );
    }

    /* ====================================================================
     * 3.21.04.2-a — MOTEUR DE JEU LIVE
     * Cycle : create → lobby → start → next/close (auto-reveal) → end → podium
     * ==================================================================== */

    /**
     * Crée une session live à partir d'un quiz et génère un PIN unique.
     * Appelée par le handler launch_live (admin-post).
     *
     * @return int|WP_Error ID de la session ou erreur.
     */
    public function create_qz_live_session( $quiz_id, $host_user_id, $formation_id = 0, $formation_session_id = 0 ) {
        global $wpdb;
        $quiz_id = (int) $quiz_id;
        if ( $quiz_id <= 0 ) {
            return new WP_Error( 'qz_invalid_quiz', 'Quiz invalide.' );
        }
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz || self::ACDC_OF_QZ_PURPOSE_LIVE !== $quiz->quiz_purpose ) {
            return new WP_Error( 'qz_not_live', 'Ce quiz n\'est pas un quiz live.' );
        }
        $pin = $this->generate_unique_qz_pin();
        if ( ! $pin ) {
            return new WP_Error( 'qz_pin_failed', 'Impossible de générer un PIN unique.' );
        }
        $now = current_time( 'mysql' );
        $tbl = $this->get_qz_table( 'sessions' );
        $ok = $wpdb->insert( $tbl, array(
            'quiz_id'              => $quiz_id,
            'formation_id'         => (int) $formation_id,
            'formation_session_id' => (int) $formation_session_id ?: null,
            'host_id'              => (int) $host_user_id,
            'delivery_mode'        => 'live',
            'status'               => 'lobby',
            'pin_code'             => $pin,
            'lobby_opened_at'      => $now,
            'created_at'           => $now,
            'updated_at'           => $now,
        ), array( '%d','%d','%d','%d','%s','%s','%s','%s','%s','%s' ) );
        if ( false === $ok ) {
            return new WP_Error( 'qz_insert_failed', 'Création de session impossible.' );
        }
        return (int) $wpdb->insert_id;
    }

    /**
     * Démarre une session live (passage de lobby → in_progress, charge la 1ère question).
     */
    public function start_qz_live_session( $session_id ) {
        global $wpdb;
        $session = $this->get_qz_dispatch_session( (int) $session_id );
        if ( ! $session || 'live' !== $session->delivery_mode ) {
            return new WP_Error( 'qz_not_live_session', 'Session live invalide.' );
        }
        if ( 'lobby' !== $session->status ) {
            return new WP_Error( 'qz_already_started', 'Session déjà démarrée.' );
        }
        $questions = $this->get_qz_questions_for_quiz( (int) $session->quiz_id );
        if ( empty( $questions ) ) {
            return new WP_Error( 'qz_no_questions', 'Ce quiz n\'a aucune question.' );
        }
        $first = reset( $questions );
        $now   = current_time( 'mysql' );
        $tbl   = $this->get_qz_table( 'sessions' );
        $wpdb->update( $tbl, array(
            'status'                      => 'in_progress',
            'started_at'                  => $now,
            'current_question_id'         => (int) $first->id,
            'current_question_started_at' => $now,
            'updated_at'                  => $now,
        ), array( 'id' => (int) $session->id ), array( '%s','%s','%d','%s','%s' ), array( '%d' ) );
        // Marquer tous les participants connectés comme "in_progress"
        $tbl_p = $this->get_qz_table( 'participants' );
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$tbl_p} SET status='in_progress', started_at=%s, updated_at=%s WHERE session_id=%d AND status='invited'",
            $now, $now, (int) $session->id
        ) );
        return true;
    }

    /**
     * Avance à la question suivante. Si dernière question, termine la session.
     */
    public function advance_qz_live_question( $session_id ) {
        global $wpdb;
        $session = $this->get_qz_dispatch_session( (int) $session_id );
        if ( ! $session || 'in_progress' !== $session->status ) {
            return new WP_Error( 'qz_not_running', 'Session non en cours.' );
        }
        $questions = $this->get_qz_questions_for_quiz( (int) $session->quiz_id );
        if ( empty( $questions ) ) {
            return new WP_Error( 'qz_no_questions', 'Aucune question.' );
        }
        // Trouver la position courante
        $current_id = (int) $session->current_question_id;
        $next = null;
        $found = false;
        foreach ( $questions as $q ) {
            if ( $found ) { $next = $q; break; }
            if ( (int) $q->id === $current_id ) { $found = true; }
        }
        $now = current_time( 'mysql' );
        $tbl = $this->get_qz_table( 'sessions' );
        if ( ! $next ) {
            // Plus de question → on termine
            return $this->end_qz_live_session( (int) $session->id );
        }
        $wpdb->update( $tbl, array(
            'current_question_id'         => (int) $next->id,
            'current_question_started_at' => $now,
            'updated_at'                  => $now,
        ), array( 'id' => (int) $session->id ), array( '%d','%s','%s' ), array( '%d' ) );
        return (int) $next->id;
    }

    /**
     * Termine la session live, calcule le leaderboard.
     */
    public function end_qz_live_session( $session_id ) {
        global $wpdb;
        $session = $this->get_qz_dispatch_session( (int) $session_id );
        if ( ! $session ) {
            return new WP_Error( 'qz_not_found', 'Session introuvable.' );
        }
        $now = current_time( 'mysql' );
        $tbl = $this->get_qz_table( 'sessions' );
        $wpdb->update( $tbl, array(
            'status'     => 'ended',
            'ended_at'   => $now,
            'updated_at' => $now,
        ), array( 'id' => (int) $session->id ), array( '%s','%s','%s' ), array( '%d' ) );
        // Marquer tous les participants comme "completed"
        $tbl_p = $this->get_qz_table( 'participants' );
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$tbl_p} SET status='completed', completed_at=%s, updated_at=%s WHERE session_id=%d AND status IN ('in_progress','opened')",
            $now, $now, (int) $session->id
        ) );
        // 3.21.04.2-b — Recalcul définitif des scores depuis player_answers (contournement is_scored=0)
        $tbl_pa = $this->get_qz_table( 'player_answers' );
        $part_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$tbl_p} WHERE session_id=%d",
            (int) $session->id
        ) );
        foreach ( $part_ids as $pid ) {
            $total = (float) $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(score_earned),0) FROM {$tbl_pa} WHERE participant_id=%d AND session_id=%d",
                (int) $pid, (int) $session->id
            ) );
            $wpdb->update( $tbl_p, array( 'total_score' => $total ), array( 'id' => (int) $pid ), array( '%f' ), array( '%d' ) );
        }

        // 3.21.06 — Résoudre registration_id si absent, puis générer le PDF
        foreach ( $part_ids as $pid ) {
            $p = $wpdb->get_row( $wpdb->prepare(
                "SELECT id, learner_id, registration_id FROM {$tbl_p} WHERE id=%d LIMIT 1",
                (int) $pid
            ) );
            if ( ! $p || (int) $p->learner_id <= 0 ) { continue; }
            $this->qz_resolve_registration_id_and_generate_pdf( $p, (int) $session->id );
        }

        return true;
    }

    /**
     * Vérifie si tous les participants actifs ont répondu à la question courante.
     * Utilisé pour la révélation automatique.
     */
    public function qz_check_all_answered( $session_id ) {
        global $wpdb;
        $session = $this->get_qz_dispatch_session( (int) $session_id );
        if ( ! $session || ! $session->current_question_id ) {
            return false;
        }
        $tbl_p  = $this->get_qz_table( 'participants' );
        $tbl_pa = $this->get_qz_table( 'player_answers' );
        // Nombre de participants actifs (présents au début de la question courante)
        $active_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl_p} WHERE session_id=%d AND status IN ('in_progress','opened')",
            (int) $session->id
        ) );
        if ( 0 === $active_count ) {
            return false;
        }
        // Nombre de réponses pour cette question sur cette session
        $answered_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT pa.participant_id) FROM {$tbl_pa} pa
             INNER JOIN {$tbl_p} p ON p.id = pa.participant_id
             WHERE p.session_id=%d AND pa.question_id=%d",
            (int) $session->id, (int) $session->current_question_id
        ) );
        return ( $answered_count >= $active_count );
    }

    /**
     * Calcule le score Kahoot-like pour une réponse.
     * - Réponse correcte instantanée : 1000 pts
     * - Décroît linéairement vers 500 pts à la fin du timer
     * - Sans timer : 1000 ou 0
     * - Réponse fausse : 0 pts
     */
    public function qz_calculate_kahoot_score( $is_correct, $response_time_ms, $time_limit_seconds ) {
        // Logique pure déportée dans \ACDC\Support\QuizScore (couverte par PHPUnit).
        return \ACDC\Support\QuizScore::kahoot( $is_correct, $response_time_ms, $time_limit_seconds );
    }

    /**
     * Retourne l'état complet d'une session live pour polling (host ou player).
     */
    public function get_qz_live_state( $session_id ) {
        global $wpdb;
        $session = $this->get_qz_dispatch_session( (int) $session_id );
        if ( ! $session ) {
            return null;
        }
        $tbl_p = $this->get_qz_table( 'participants' );
        $participants = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, nickname, avatar, total_score, status FROM {$tbl_p}
             WHERE session_id=%d AND status NOT IN ('cancelled')
             ORDER BY joined_at ASC",
            (int) $session->id
        ) );
        // tab_switch_count — colonne optionnelle, vérifiée avant utilisation
        $tab_switch_map = array();
        $ts_cols = $wpdb->get_col( "SHOW COLUMNS FROM {$tbl_p} LIKE 'tab_switch_count'" );
        if ( ! empty( $ts_cols ) ) {
            $ts_rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, tab_switch_count FROM {$tbl_p} WHERE session_id=%d AND status NOT IN ('cancelled')",
                (int) $session->id
            ) );
            if ( is_array( $ts_rows ) ) {
                foreach ( $ts_rows as $r ) {
                    $tab_switch_map[ (int) $r->id ] = (int) $r->tab_switch_count;
                }
            }
        }
        // Nombre total de questions du quiz + numéro de la question courante
        $all_questions = $this->get_qz_questions_for_quiz( (int) $session->quiz_id );
        $total_q = is_array( $all_questions ) ? count( $all_questions ) : 0;
        $current_q_num = 0;
        if ( $session->current_question_id && $total_q > 0 ) {
            foreach ( $all_questions as $idx => $aq ) {
                if ( (int) $aq->id === (int) $session->current_question_id ) {
                    $current_q_num = $idx + 1;
                    break;
                }
            }
        }

        $state = array(
            'session_id'    => (int) $session->id,
            'quiz_id'       => (int) $session->quiz_id,
            'pin_code'      => (string) $session->pin_code,
            'status'        => (string) $session->status,
            'started_at'    => $session->started_at,
            'ended_at'      => $session->ended_at,
            'current_q_id'  => (int) $session->current_question_id,
            'current_q_num' => $current_q_num,
            'total_q'       => $total_q,
            'current_q_started_at'      => $session->current_question_started_at,
            // ACDC 3.25.113 — base temps WP homogène.
            'current_q_elapsed_seconds' => $session->current_question_started_at
                ? max( 0, current_time( 'timestamp' ) - strtotime( $session->current_question_started_at ) )
                : 0,
            'participants'  => array_map( function( $p ) use ( $tab_switch_map ) {
                return array(
                    'id'              => (int) $p->id,
                    'nickname'        => (string) $p->nickname,
                    'avatar'          => (string) $p->avatar,
                    'score'           => (float) $p->total_score,
                    'status'          => (string) $p->status,
                    'tab_switch'      => isset( $tab_switch_map[ (int) $p->id ] ) ? (int) $tab_switch_map[ (int) $p->id ] : 0,
                );
            }, $participants ),
        );
        // Flags anti-triche du quiz — transmis au player pour conditionner son comportement
        $quiz_obj = $this->get_qz_quiz( (int) $session->quiz_id );
        if ( $quiz_obj ) {
            $state['antifraud'] = array(
                'no_back'          => (bool) $quiz_obj->antifraud_no_back,
                'visibility_check' => (bool) $quiz_obj->antifraud_visibility_check,
                'time_limit'       => (bool) $quiz_obj->antifraud_time_limit,
                'fullscreen'       => (bool) $quiz_obj->antifraud_fullscreen,
            );
        } else {
            $state['antifraud'] = array(
                'no_back'          => false,
                'visibility_check' => false,
                'time_limit'       => false,
                'fullscreen'       => false,
            );
        }
        // Détails de la question courante si pertinent
        if ( in_array( $session->status, array( 'in_progress' ), true ) && $session->current_question_id ) {
            $q = $this->get_qz_question( (int) $session->current_question_id );
            if ( $q ) {
                $answers_pub = $this->get_qz_answers_for_question_public( (int) $q->id );
                $type_labels = array(
                    'qcm_single'   => 'Une seule réponse',
                    'qcm_multiple' => 'Plusieurs réponses',
                    'true_false'   => 'Vrai ou Faux',
                    'puzzle'       => 'Remise en ordre',
                    'open_text'    => 'Réponse libre',
                    'poll'         => 'Sondage',
                );
                $state['current_question'] = array(
                    'id'         => (int) $q->id,
                    'title'      => (string) $q->title,
                    'type'       => (string) $q->type,
                    'type_label' => isset( $type_labels[ $q->type ] ) ? $type_labels[ $q->type ] : 'Question',
                    'time_limit' => (int) $q->time_limit,
                    'answers'        => array_map( function( $a ) {
                        return array(
                            'id'    => (int) $a->id,
                            'label' => (string) $a->text,
                        );
                    }, $answers_pub ),
                );
                $state['all_answered'] = $this->qz_check_all_answered( (int) $session->id );
                // 3.21.04.2-b — Données de révélation toujours présentes (host clique Réponse avant all_answered)
                {
                    global $wpdb;
                    $tbl_pa   = $this->get_qz_table( 'player_answers' );
                    $tbl_p    = $this->get_qz_table( 'participants' );
                    $all_pa   = $wpdb->get_results( $wpdb->prepare(
                        "SELECT answer_ids_json FROM {$tbl_pa} WHERE question_id=%d AND session_id=%d",
                        (int) $q->id, (int) $session->id
                    ) );
                    // Nombre de participants distincts ayant répondu
                    $count_total_parts = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT COUNT(DISTINCT participant_id) FROM {$tbl_pa} WHERE question_id=%d AND session_id=%d",
                        (int) $q->id, (int) $session->id
                    ) );
                    // Nombre de participants distincts ayant eu au moins un point
                    $count_correct_parts = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT COUNT(DISTINCT participant_id) FROM {$tbl_pa} WHERE question_id=%d AND session_id=%d AND score_earned > 0",
                        (int) $q->id, (int) $session->id
                    ) );
                    $vote_count = array();
                    foreach ( $all_pa as $pa ) {
                        $chosen = json_decode( $pa->answer_ids_json, true );
                        if ( ! is_array( $chosen ) || empty( $chosen ) ) {
                            preg_match_all( '/\d+/', (string) $pa->answer_ids_json, $m );
                            $chosen = array_map( 'intval', $m[0] );
                        }
                        foreach ( $chosen as $aid ) {
                            $aid = (int) $aid;
                            if ( $aid > 0 ) {
                                $vote_count[ $aid ] = isset( $vote_count[ $aid ] ) ? $vote_count[ $aid ] + 1 : 1;
                            }
                        }
                    }
                    $total_pa       = $count_total_parts; // nb de participants, pas de lignes
                    $reveal_answers = array();
                    $answers_db     = $this->get_qz_answers_for_question_public( (int) $q->id );
                    foreach ( $answers_db as $a ) {
                        $cnt = isset( $vote_count[ (int) $a->id ] ) ? $vote_count[ (int) $a->id ] : 0;
                        $reveal_answers[] = array(
                            'id'         => (int) $a->id,
                            'label'      => (string) $a->text,
                            'is_correct' => (int) $a->is_correct,
                            'count'      => $cnt,
                            'percent'    => $total_pa > 0 ? (int) round( $cnt / $total_pa * 100 ) : 0,
                        );
                    }
                    $state['current_question']['reveal_answers']     = $reveal_answers;
                    $state['current_question']['count_total_parts']  = $count_total_parts;
                    $state['current_question']['count_correct_parts']= $count_correct_parts;
                    // Réponse attendue open_text (si colonne existe)
                    if ( $q->type === self::ACDC_OF_QZ_QTYPE_OPEN_TEXT ) {
                        $tbl_q   = $this->get_qz_table( 'questions' );
                        $cols_ea = $wpdb->get_col( "SHOW COLUMNS FROM {$tbl_q} LIKE 'expected_answer'" );
                        if ( ! empty( $cols_ea ) ) {
                            $state['current_question']['expected_answer'] = (string) $wpdb->get_var( $wpdb->prepare(
                                "SELECT expected_answer FROM {$tbl_q} WHERE id=%d LIMIT 1", (int) $q->id
                            ) );
                        }
                    }
                }
            }
        }
        return $state;
    }

    /**
     * Retourne le leaderboard complet trié.
     */
    public function get_qz_live_leaderboard( $session_id ) {
        global $wpdb;
        $tbl_p = $this->get_qz_table( 'participants' );
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, nickname, avatar, total_score, total_score_percentage
             FROM {$tbl_p}
             WHERE session_id=%d AND status='completed'
             ORDER BY total_score DESC, completed_at ASC",
            (int) $session_id
        ) );
        $rank = 0;
        foreach ( $rows as $r ) {
            $rank++;
            $r->rank = $rank;
        }
        return $rows;
    }

    /**
     * Recalcule le total_score d'un participant à partir de toutes ses player_answers.
     * NOTE 3.21.04.2-a : la méthode existe déjà dans Core trait. On délègue.
     */

    /* ==================================================================== */
    /*  Vue consolidée résultats — 3.21.33                                  */
    /* ==================================================================== */

    /**
     * Retourne toutes les passations complétées pour les formations d'un formateur.
     *
     * @param int   $trainer_id   ID WordPress du formateur connecté. 0 = pas de filtre (admin).
     * @param array $filters      Filtres optionnels : purpose, quiz_id, formation_id, search.
     * @param int   $limit        Max lignes (défaut 200).
     *
     * @return array
     */
    public function get_qz_all_results_consolidated( $trainer_id, $filters = array(), $limit = 200 ) {
        global $wpdb;

        $tbl_p  = $this->get_qz_table( 'participants' );
        $tbl_s  = $this->get_qz_table( 'sessions' );
        $tbl_q  = $this->get_qz_table( 'quizzes' );
        $tbl_f  = $wpdb->prefix . 'acdc_of_formations';
        if ( ! $tbl_p || ! $tbl_s || ! $tbl_q ) {
            return array();
        }

        $has_result_col = ! empty( $wpdb->get_col( "SHOW COLUMNS FROM {$tbl_p} LIKE 'result_document_url'" ) );
        $result_col_sql = $has_result_col ? 'p.result_document_url,' : "'' AS result_document_url,";

        $where  = array( "p.status = 'completed'", 'p.is_anonymized = 0' );
        $params = array();

        if ( (int) $trainer_id > 0 ) {
            // Filtre via sessions liées aux formations animées par ce formateur
            $tbl_sess = $wpdb->prefix . 'acdc_of_sessions';
            $where[]  = "EXISTS (
                SELECT 1 FROM {$tbl_sess} fs
                WHERE fs.id = s.formation_session_id AND fs.trainer_id = %d
            )";
            $params[] = (int) $trainer_id;
        }

        $purpose = isset( $filters['purpose'] ) ? sanitize_key( $filters['purpose'] ) : '';
        if ( in_array( $purpose, array( 'live', 'positioning', 'assessment' ), true ) ) {
            $where[]  = 'q.quiz_purpose = %s';
            $params[] = $purpose;
        }

        $quiz_id = isset( $filters['quiz_id'] ) ? (int) $filters['quiz_id'] : 0;
        if ( $quiz_id > 0 ) {
            $where[]  = 'q.id = %d';
            $params[] = $quiz_id;
        }

        $formation_id = isset( $filters['formation_id'] ) ? (int) $filters['formation_id'] : 0;
        if ( $formation_id > 0 ) {
            $where[]  = 'q.formation_id = %d';
            $params[] = $formation_id;
        }

        $search = isset( $filters['search'] ) ? trim( sanitize_text_field( $filters['search'] ) ) : '';
        if ( '' !== $search ) {
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $where[]  = '(p.full_name LIKE %s OR p.email LIKE %s OR q.title LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where );
        $limit     = max( 1, min( 500, (int) $limit ) );

        $sql = "SELECT p.id AS participant_id,
                       p.full_name, p.email, p.nickname,
                       p.total_score, p.total_score_percentage, p.is_passed,
                       p.completed_at, p.started_at, p.status,
                       {$result_col_sql}
                       q.id AS quiz_id, q.title AS quiz_title, q.quiz_purpose,
                       f.title AS formation_title
                FROM {$tbl_p} p
                INNER JOIN {$tbl_s} s  ON s.id = p.session_id
                INNER JOIN {$tbl_q} q  ON q.id = s.quiz_id
                LEFT  JOIN {$tbl_f} f  ON f.id = q.formation_id
                {$where_sql}
                ORDER BY p.completed_at DESC
                LIMIT {$limit}";

        if ( ! empty( $params ) ) {
            return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        }
        return $wpdb->get_results( $sql );
    }
}
