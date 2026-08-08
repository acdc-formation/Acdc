<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * ACDC 3.21.04.1 — Écrans de résultats détaillés dans le portail formateur.
 *
 * Réutilise les méthodes Core (get_qz_dispatch_sessions, get_qz_results_by_question, etc.)
 * en filtrant par trainer_id. Les vues sont accessibles depuis l'onglet "Mes quiz" :
 * un nouveau bouton "Résultats" sur chaque carte renvoie ici.
 *
 * Vues fournies (parallèles à l'admin) :
 *   ?view=results                     → liste des envois du formateur
 *   ?view=results&session=ID          → détail (3 onglets)
 *   ?view=results&session=ID&tab=...  → onglets (questions / objectives)
 *   ?view=results&participant=ID      → détail individuel
 *
 * @package ACDC_Formation_SAAS
 * @since 3.21.04.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Trainer_Portal_Results_Render_Trait {

    /**
     * Branchement des hooks pour la vue results dans le portail formateur.
     * Appelé depuis register_quizzes_module_hooks().
     */
    public function register_trainer_portal_results_hooks() {
        add_filter( 'acdc_trainer_portal_internal_views', array( $this, 'register_results_view_in_trainer_portal' ), 10, 2 );
        add_filter( 'acdc_trainer_portal_render_unknown_view', array( $this, 'render_results_view_in_trainer_portal' ), 10, 3 );
    }

    public function register_results_view_in_trainer_portal( $views, $account ) {
        if ( ! is_array( $views ) ) {
            return $views;
        }
        $views[] = 'results';
        return $views;
    }

    /**
     * Aiguille la vue results selon les paramètres.
     */
    public function render_results_view_in_trainer_portal( $body, $view, $account ) {
        if ( 'results' !== $view ) {
            return $body;
        }
        if ( ! is_object( $account ) ) {
            return $body;
        }
        $trainer_id = isset( $account->trainer_id ) ? (int) $account->trainer_id : 0;
        if ( $trainer_id <= 0 ) {
            return $body;
        }
        if ( ! $this->trainer_can( $trainer_id, 'view_own_quizzes' ) ) {
            return $this->render_trainer_portal_quizzes_access_denied();
        }

        $session_id     = isset( $_GET['session'] ) ? absint( wp_unslash( $_GET['session'] ) ) : 0;
        $participant_id = isset( $_GET['participant'] ) ? absint( wp_unslash( $_GET['participant'] ) ) : 0;
        /* 3.21.04.1-hotfix5 — Renommage pour éviter la collision avec le tab sidebar. */
        $tab            = isset( $_GET['qz_subtab'] ) ? sanitize_key( wp_unslash( $_GET['qz_subtab'] ) ) : 'participants';

        if ( $participant_id > 0 ) {
            return $this->render_tp_results_participant_detail( $trainer_id, $participant_id );
        }
        if ( $session_id > 0 ) {
            return $this->render_tp_results_session_detail( $trainer_id, $session_id, $tab );
        }
        return $this->render_tp_results_sessions_list( $trainer_id );
    }

    /**
     * Liste des envois du formateur (avec fallback automatique).
     */
    private function render_tp_results_sessions_list( $trainer_id ) {
        $sessions = $this->get_qz_dispatch_sessions( array(
            'trainer_id' => $trainer_id,
            'limit'      => 100,
            'fallback_all_active' => true,
        ) );
        $base_url = $this->trainer_portal_page_url( 'results' );

        ob_start();
        ?>
        <div class="acdc-trainer-portal-quizzes">
            <div class="acdc-trainer-portal-quizzes-header">
                <div class="acdc-trainer-portal-quizzes-header-text">
                    <h1>Résultats des quiz</h1>
                    <p class="acdc-trainer-portal-quizzes-subtitle">
                        Suivez la progression de vos apprenants envoi par envoi.
                    </p>
                </div>
                <div class="acdc-trainer-portal-quizzes-header-actions">
                    <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->trainer_portal_page_url( 'quizzes' ) ); ?>">
                        ← Mes quiz
                    </a>
                </div>
            </div>

            <?php if ( empty( $sessions ) ) : ?>
                <div class="acdc-trainer-portal-quizzes-empty">
                    <p>Aucun envoi enregistré pour le moment.</p>
                    <p class="acdc-trainer-portal-quizzes-hint">
                        Les résultats apparaîtront ici dès qu'un quiz aura été envoyé à des apprenants.
                    </p>
                </div>
            <?php else : ?>
                <table class="acdc-tp-results-table">
                    <thead>
                        <tr>
                            <th>Quiz</th>
                            <th>Formation</th>
                            <th>Envoyé le</th>
                            <th>Participants</th>
                            <th>Score moyen</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $sessions as $s ) :
                        $count_inv = (int) $s->count_invited;
                        $count_cpl = (int) $s->count_completed;
                        $detail_url = add_query_arg( array( 'view' => 'results', 'session' => (int) $s->id ), $this->trainer_portal_page_url( 'results' ) );
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $s->quiz_title ); ?></strong>
                            </td>
                            <td><?php echo esc_html( $s->formation_title ?? '—' ); ?></td>
                            <?php
                            /* ACDC 3.25.171 — Le formateur ne lisait que sent_at, qui n'est
                               rempli que pour un envoi par e-mail : toutes ses passations
                               EN SALLE affichaient « — », alors que le gestionnaire, lui,
                               voyait bien la date de lancement. Même repli que côté
                               administration. */
                            $launched_at = ! empty( $s->started_at ) ? $s->started_at
                                : ( ! empty( $s->sent_at ) ? $s->sent_at : $s->created_at );
                            ?>
                            <td><?php echo esc_html( $this->qz_format_datetime( $launched_at ) ); ?></td>
                            <td><?php echo (int) $count_cpl; ?> / <?php echo (int) $count_inv; ?></td>
                            <td>
                                <?php
                                /* ACDC 3.25.171 — Un quiz live se compte en POINTS, pas en
                                   pourcentage : cette colonne n'affichait qu'un pourcentage et
                                   rendait donc « — » là où le gestionnaire lisait 4 800 pts.
                                   Même passation, deux valeurs selon le rôle. */
                                if ( 'live' === ( $s->quiz_purpose ?? '' ) ) {
                                    $tp_avg_pts = $this->get_qz_avg_live_score_for_session( (int) $s->id );
                                    echo ( null === $tp_avg_pts )
                                        ? '—'
                                        : esc_html( number_format( (float) $tp_avg_pts, 0, ',', ' ' ) ) . ' pts';
                                } elseif ( null === $s->avg_score ) {
                                    echo '—';
                                } else {
                                    echo esc_html( number_format( (float) $s->avg_score, 1, ',', ' ' ) ) . ' %';
                                }
                                ?>
                            </td>
                            <td>
                                <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $detail_url ); ?>">Voir →</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Détail d'un envoi avec onglets.
     */
    private function render_tp_results_session_detail( $trainer_id, $session_id, $tab ) {
        if ( ! $this->can_qz_trainer_view_dispatch_session( $session_id, $trainer_id ) ) {
            return $this->render_trainer_portal_quizzes_access_denied();
        }
        $session = $this->get_qz_dispatch_session( $session_id );
        if ( ! $session ) {
            ob_start();
            ?>
            <div class="acdc-trainer-portal-quizzes-blocked">
                <h1>Envoi introuvable</h1>
                <p><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->trainer_portal_page_url( 'results' ) ); ?>">← Retour</a></p>
            </div>
            <?php
            return ob_get_clean();
        }

        $back_url   = $this->trainer_portal_page_url( 'results' );
        $base_url   = add_query_arg( array( 'view' => 'results', 'session' => $session_id ), $back_url );
        $tab_parts  = $base_url;
        $tab_quest  = add_query_arg( 'qz_subtab', 'questions',  $base_url );
        $tab_objs   = add_query_arg( 'qz_subtab', 'objectives', $base_url );
        $export_url = add_query_arg( array(
            'action'     => 'acdc_of_qz_export_results',
            'session_id' => $session_id,
            '_wpnonce'   => wp_create_nonce( 'acdc_of_qz_export_results' ),
        ), admin_url( 'admin-post.php' ) );

        ob_start();
        ?>
        <div class="acdc-tp-results">
            <p class="acdc-trainer-portal-breadcrumb">
                <a href="<?php echo esc_url( $back_url ); ?>">Résultats</a>
                <span aria-hidden="true">›</span>
                <span><?php echo esc_html( $session->quiz_title ); ?></span>
            </p>

            <header class="acdc-tp-results-header">
                <div>
                    <h1><?php echo esc_html( $session->quiz_title ); ?></h1>
                    <p class="acdc-tp-results-meta">
                        <strong>Formation :</strong> <?php echo esc_html( $session->formation_title ?? '—' ); ?>
                        &nbsp;·&nbsp;
                        <?php
                        $hdr_launched = ! empty( $session->started_at ) ? $session->started_at
                            : ( ! empty( $session->sent_at ) ? $session->sent_at : $session->created_at );
                        ?>
                        <strong><?php echo esc_html( ! empty( $session->started_at ) ? 'Lancé le :' : 'Envoyé le :' ); ?></strong>
                        <?php echo esc_html( $this->qz_format_datetime( $hdr_launched ) ); ?>
                    </p>
                </div>
                <div>
                    <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $export_url ); ?>">⬇ Exporter en CSV</a>
                </div>
            </header>

            <nav class="acdc-tp-results-tabs">
                <a class="<?php echo 'participants' === $tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( $tab_parts ); ?>">Participants</a>
                <a class="<?php echo 'questions' === $tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( $tab_quest ); ?>">Par question</a>
                <a class="<?php echo 'objectives' === $tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( $tab_objs ); ?>">Par objectif</a>
            </nav>

            <?php
            if ( 'questions' === $tab ) {
                $this->render_tp_results_tab_by_question( $session );
            } elseif ( 'objectives' === $tab ) {
                $this->render_tp_results_tab_by_objective( $session );
            } else {
                $this->render_tp_results_tab_participants( $session );
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_tp_results_tab_participants( $session ) {
        $participants = $this->get_qz_participants_for_dispatch_session( (int) $session->id );
        if ( empty( $participants ) ) {
            echo '<div class="acdc-trainer-portal-quizzes-empty"><p>Aucun participant.</p></div>';
            return;
        }
        $status_labels = array(
            'pending' => 'En attente', 'invited' => 'Invité', 'opened' => 'Ouvert',
            'in_progress' => 'En cours', 'completed' => 'Terminé', 'expired' => 'Expiré', 'cancelled' => 'Annulé',
        );
        ?>
        <table class="acdc-tp-results-table">
            <thead>
                <tr><th>Apprenant</th><th>Statut</th><th>Terminé le</th><th>Score</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ( $participants as $p ) :
                $detail_url = add_query_arg( array( 'view' => 'results', 'participant' => (int) $p->id ), $this->trainer_portal_page_url( 'results' ) );
            ?>
                <?php
                /* ACDC 3.25.171 — La cellule était ENTIÈREMENT VIDE pour une passation en
                   salle : ni nom (jamais rempli), ni e-mail, et aucun repli sur le pseudo.
                   Le formateur ne pouvait pas savoir qui avait passé l'évaluation qu'il
                   venait lui-même d'animer. Ordre de préférence identique à celui de
                   l'administration : nom de la fiche apprenant, puis nom saisi, puis
                   pseudo, puis e-mail. */
                $tp_name = trim( (string) ( $p->learner_full_name ?? '' ) )
                    ?: trim( (string) $p->full_name )
                    ?: trim( (string) $p->nickname )
                    ?: (string) $p->email;
                ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html( $tp_name ?: '—' ); ?></strong>
                        <?php if ( empty( $p->learner_id ) && '' === trim( (string) $p->email ) ) : ?>
                            <br><small style="color:#b45309;font-weight:600;">⚠ non rattaché à un apprenant</small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $status_labels[ $p->status ] ?? $p->status ); ?></td>
                    <td><?php echo esc_html( $this->qz_format_datetime( $p->completed_at ) ); ?></td>
                    <td>
                        <?php
                        /* ACDC 3.25.171 — Même correction que sur la liste : un quiz live se
                           lit en points. */
                        if ( 'live' === ( $session->quiz_purpose ?? '' ) ) {
                            echo ( null === $p->total_score )
                                ? '—'
                                : '<strong>' . esc_html( number_format( (float) $p->total_score, 0, ',', ' ' ) ) . ' pts</strong>';
                        } elseif ( null === $p->total_score_percentage ) {
                            echo '—';
                        } else {
                            echo '<strong>' . esc_html( number_format( (float) $p->total_score_percentage, 1, ',', ' ' ) ) . ' %</strong>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ( in_array( $p->status, array( 'completed', 'in_progress' ), true ) ) : ?>
                            <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $detail_url ); ?>">Détail</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function render_tp_results_tab_by_question( $session ) {
        $rows = $this->get_qz_results_by_question( (int) $session->id );
        if ( empty( $rows ) ) {
            echo '<div class="acdc-trainer-portal-quizzes-empty"><p>Aucune question.</p></div>';
            return;
        }
        ?>
        <table class="acdc-tp-results-table">
            <thead>
                <?php /* ACDC 3.25.171 — Colonne « Partielles » ajoutée, comme côté
                         administration : sans elle, « 0 bonne réponse » à côté de « 66,7 % de
                         réussite » sur la même ligne est illisible. */ ?>
                <tr><th>Question</th><th>Type</th><th>Répondants</th><th>Bonnes</th><th>Partielles</th><th>Réussite</th></tr>
            </thead>
            <tbody>
            <?php foreach ( $rows as $r ) : ?>
                <tr>
                    <td><?php echo esc_html( $r->title ); ?></td>
                    <td><?php echo esc_html( $this->qz_type_label( (string) $r->type ) ); ?></td>
                    <td><?php echo (int) $r->count_answered; ?></td>
                    <td><?php echo (int) $r->count_correct; ?></td>
                    <td><?php echo ! empty( $r->count_partial ) ? (int) $r->count_partial : '—'; ?></td>
                    <td>
                        <?php if ( null === $r->success_rate ) : ?>
                            <?php if ( ! empty( $r->count_pending ) ) : ?>
                                <span style="color:#8a6d2a;font-weight:600;">⏳ à corriger</span>
                            <?php else : ?>—<?php endif; ?>
                        <?php else : ?><strong><?php echo esc_html( number_format( (float) $r->success_rate, 1, ',', ' ' ) ); ?>%</strong><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function render_tp_results_tab_by_objective( $session ) {
        $rows = $this->get_qz_results_by_objective( (int) $session->id );
        if ( empty( $rows ) ) {
            echo '<div class="acdc-trainer-portal-quizzes-empty">'
               . '<p>Aucun objectif pédagogique défini sur ce quiz.</p>'
               . '<p class="acdc-trainer-portal-quizzes-hint">Pour activer cette vue, rattachez chaque question à un objectif depuis l\'éditeur de quiz.</p>'
               . '</div>';
            return;
        }
        ?>
        <table class="acdc-tp-results-table">
            <thead>
                <tr><th>Objectif</th><th>Questions</th><th>Score moyen</th><th>Seuil</th><th>Atteint ?</th></tr>
            </thead>
            <tbody>
            <?php foreach ( $rows as $r ) : ?>
                <tr>
                    <td><?php echo esc_html( $r->label ); ?></td>
                    <td><?php echo (int) $r->count_questions; ?></td>
                    <td>
                        <?php if ( null === $r->score_avg ) : ?>—<?php
                        else : ?><strong><?php echo esc_html( number_format( (float) $r->score_avg, 1, ',', ' ' ) ); ?>%</strong><?php endif; ?>
                    </td>
                    <td>
                        <?php
                        /* ACDC 3.25.171 — Cet écran affichait le seuil BRUT de l'objectif,
                           vide tant qu'aucun seuil n'y avait été saisi, tout en affichant un
                           verdict « ✗ Non » calculé, lui, sur un seuil de repli. Un objectif
                           à 65 % était donc déclaré non atteint sans qu'aucun seuil ne soit
                           visible — et un formateur exportant cela annonçait à son client
                           qu'aucun objectif n'avait été atteint. On affiche le seuil
                           réellement appliqué, et d'où il vient. */
                        $tp_th_note = array( 'objective' => '', 'quiz' => 'seuil du quiz', 'default' => 'seuil par défaut' );
                        $tp_th_src  = isset( $r->threshold_source ) ? (string) $r->threshold_source : 'objective';
                        $tp_th_val  = isset( $r->threshold_applied ) ? (float) $r->threshold_applied : null;
                        ?>
                        <?php if ( null === $tp_th_val ) : ?>—<?php else : ?>
                            <?php echo esc_html( number_format( $tp_th_val, 0, ',', '' ) ); ?>%
                            <?php if ( ! empty( $tp_th_note[ $tp_th_src ] ) ) : ?>
                                <small style="display:block;color:#6b7280;"><?php echo esc_html( $tp_th_note[ $tp_th_src ] ); ?></small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( null === $r->is_passing ) : ?>—<?php
                        elseif ( $r->is_passing ) : ?><span class="acdc-tp-pass-yes">✓ Oui</span><?php
                        else : ?><span class="acdc-tp-pass-no">✗ Non</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Détail individuel d'un participant côté formateur.
     */
    private function render_tp_results_participant_detail( $trainer_id, $participant_id ) {
        $p = $this->get_qz_participant( $participant_id );
        if ( ! $p ) {
            return $this->render_trainer_portal_quizzes_access_denied();
        }
        if ( ! $this->can_qz_trainer_view_dispatch_session( (int) $p->session_id, $trainer_id ) ) {
            return $this->render_trainer_portal_quizzes_access_denied();
        }
        $session = $this->get_qz_dispatch_session( (int) $p->session_id );
        if ( ! $session ) {
            return $this->render_trainer_portal_quizzes_access_denied();
        }
        $questions = $this->get_qz_questions_for_quiz( (int) $session->quiz_id );
        $answers   = $this->get_qz_answers_by_participant( $participant_id );
        $back_url  = add_query_arg( array( 'view' => 'results', 'session' => (int) $session->id ), $this->trainer_portal_page_url( 'results' ) );

        ob_start();

        // Affichage notice après correction manuelle
        $notice_code = isset( $_GET['acdc_qz_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['acdc_qz_notice'] ) ) : '';
        $notice_type = isset( $_GET['acdc_qz_notice_type'] ) ? sanitize_key( wp_unslash( $_GET['acdc_qz_notice_type'] ) ) : 'success';
        ?>
        <div class="acdc-tp-results">
            <p class="acdc-trainer-portal-breadcrumb">
                <a href="<?php echo esc_url( $back_url ); ?>">← Retour à l'envoi</a>
            </p>

            <?php if ( '' !== $notice_code ) : ?>
                <div class="acdc-trainer-portal-quiz-notice acdc-trainer-portal-quiz-notice-<?php echo esc_attr( $notice_type ); ?>">
                    <?php echo esc_html( $this->trainer_portal_quiz_notice_label( $notice_code ) ); ?>
                </div>
            <?php endif; ?>

            <header class="acdc-tp-results-header">
                <div>
                    <h1><?php echo esc_html( $p->full_name ?: $p->email ); ?></h1>
                    <p class="acdc-tp-results-meta">
                        <strong>Quiz :</strong> <?php echo esc_html( $session->quiz_title ); ?>
                        &nbsp;·&nbsp;
                        <strong>Score :</strong>
                        <?php if ( null === $p->total_score_percentage ) : ?>—<?php
                        else : ?><?php echo esc_html( number_format( (float) $p->total_score_percentage, 1, ',', ' ' ) ); ?>%<?php endif; ?>
                    </p>
                </div>
            </header>

            <ol class="acdc-tp-results-question-list">
                <?php foreach ( $questions as $idx => $q ) :
                    $a = $answers[ (int) $q->id ] ?? null;
                    $is_open = ( self::ACDC_OF_QZ_QTYPE_OPEN_TEXT === $q->type );
                    $correct_answers = $this->get_qz_answers_for_question_public( (int) $q->id );
                ?>
                    <li class="acdc-tp-result-question">
                        <header class="acdc-tp-result-question-header">
                            <span class="acdc-tp-result-question-num">Q<?php echo (int) $idx + 1; ?></span>
                            <h3><?php echo esc_html( $q->title ); ?></h3>
                            <?php if ( $a && null !== $a->is_correct ) : ?>
                                <?php if ( (int) $a->is_correct === 1 ) : ?>
                                    <span class="acdc-tp-result-badge acdc-tp-result-badge-correct">✓ Correct</span>
                                <?php else : ?>
                                    <span class="acdc-tp-result-badge acdc-tp-result-badge-wrong">✗ Faux</span>
                                <?php endif; ?>
                            <?php elseif ( $is_open ) : ?>
                                <span class="acdc-tp-result-badge acdc-tp-result-badge-pending">⏳ À corriger</span>
                            <?php endif; ?>
                        </header>

                        <?php if ( ! $a ) : ?>
                            <p class="acdc-tp-result-no-answer"><em>Pas de réponse fournie.</em></p>
                        <?php elseif ( $is_open ) : ?>
                            <div class="acdc-tp-result-open-answer">
                                <p><strong>Réponse de l'apprenant :</strong></p>
                                <blockquote><?php echo nl2br( esc_html( (string) $a->answer_text ) ); ?></blockquote>
                            </div>
                            <?php $this->render_tp_manual_grading_form( $p, $q, $a ); ?>
                        <?php else : ?>
                            <?php $this->render_tp_result_question_choices( $q, $a, $correct_answers ); ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Affichage des choix pour une question fermée côté formateur.
     */
    private function render_tp_result_question_choices( $question, $player_answer, $all_answers ) {
        $selected_ids = array();
        if ( ! empty( $player_answer->answer_ids_json ) ) {
            $decoded = json_decode( (string) $player_answer->answer_ids_json, true );
            if ( is_array( $decoded ) ) { $selected_ids = array_map( 'intval', $decoded ); }
        } elseif ( ! empty( $player_answer->answer_id ) ) {
            $selected_ids = array( (int) $player_answer->answer_id );
        }
        $is_puzzle = ( self::ACDC_OF_QZ_QTYPE_PUZZLE === $question->type );

        if ( $is_puzzle ) {
            $by_id = array();
            foreach ( $all_answers as $a ) { $by_id[ (int) $a->id ] = (string) $a->text; }
            ?>
            <div class="acdc-tp-result-puzzle">
                <p><strong>Ordre saisi :</strong></p>
                <ol class="acdc-tp-result-puzzle-list">
                    <?php foreach ( $selected_ids as $aid ) : ?>
                        <li><?php echo esc_html( $by_id[ $aid ] ?? '?' ); ?></li>
                    <?php endforeach; ?>
                </ol>
                <p><strong>Ordre attendu :</strong></p>
                <ol class="acdc-tp-result-puzzle-list is-expected">
                    <?php foreach ( $all_answers as $a ) : ?>
                        <li><?php echo esc_html( (string) $a->text ); ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <?php
            return;
        }
        ?>
        <ul class="acdc-tp-result-choices">
            <?php foreach ( $all_answers as $a ) :
                $is_selected = in_array( (int) $a->id, $selected_ids, true );
                $is_correct  = ( (int) $a->is_correct === 1 );
                $cls = array();
                if ( $is_selected ) { $cls[] = 'is-selected'; }
                if ( $is_correct )  { $cls[] = 'is-correct'; }
                if ( $is_selected && ! $is_correct ) { $cls[] = 'is-wrong-selection'; }
            ?>
                <li class="<?php echo esc_attr( implode( ' ', $cls ) ); ?>">
                    <span class="acdc-tp-result-choice-marker"><?php echo $is_selected ? '●' : '○'; ?></span>
                    <span class="acdc-tp-result-choice-text"><?php echo esc_html( (string) $a->text ); ?></span>
                    <?php if ( $is_correct ) : ?>
                        <span class="acdc-tp-result-choice-flag-correct">✓ Bonne réponse</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    /**
     * Formulaire de correction manuelle côté formateur (réutilise le handler admin-post).
     */
    private function render_tp_manual_grading_form( $participant, $question, $player_answer ) {
        $current_correct = ( null !== $player_answer->is_correct ) ? (int) $player_answer->is_correct : null;
        $current_score   = (float) $player_answer->score_earned;
        $max_points      = (float) $question->points_value;
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acdc-tp-manual-grading">
            <input type="hidden" name="action" value="acdc_of_qz_grade_open_answer" />
            <input type="hidden" name="participant_id" value="<?php echo (int) $participant->id; ?>" />
            <input type="hidden" name="question_id" value="<?php echo (int) $question->id; ?>" />
            <input type="hidden" name="_acdc_qz_origin" value="trainer_portal" />
            <?php wp_nonce_field( 'acdc_of_qz_grade_open_answer', '_acdc_qz_nonce' ); ?>

            <fieldset>
                <legend><strong>Correction manuelle</strong></legend>
                <p>
                    <label><input type="radio" name="is_correct" value="1" <?php checked( 1, $current_correct ); ?> /> ✓ Validé</label>
                    &nbsp;&nbsp;
                    <label><input type="radio" name="is_correct" value="0" <?php checked( 0, $current_correct ); ?> /> ✗ Non validé</label>
                </p>
                <p>
                    <label>Points (sur <?php echo esc_html( number_format( $max_points, 0, ',', '' ) ); ?>) :
                        <input type="number" name="score_earned" value="<?php echo esc_attr( number_format( $current_score, 2, '.', '' ) ); ?>"
                               min="0" max="<?php echo esc_attr( $max_points ); ?>" step="0.01" />
                    </label>
                </p>
                <p>
                    <button type="submit" class="acdc-button acdc-button-primary">Enregistrer la correction</button>
                </p>
            </fieldset>
        </form>
        <?php
    }
}
