<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * ACDC Formation SAAS — Module Quizzes (Render trait — partie 2/2 : éditeur).
 *
 * Couvre l'écran d'édition complet d'un quiz :
 *  - Layout 3 zones : panneau gauche (miniatures), zone centrale (édition de
 *    la question courante), panneau droit (paramètres de la question).
 *  - 6 types de questions avec rendus dédiés : qcm_single, qcm_multiple,
 *    true_false, puzzle, open_text, poll.
 *  - Modale paramètres globaux du quiz (titre, scoring, anti-triche, RGPD).
 *  - Modale gestion des objectifs pédagogiques.
 *  - Compatible mobile via CSS responsive (accordéon).
 *
 * Les actions de sauvegarde sont AJAX (gérées dans le trait Actions :
 * `ajax_acdc_of_qz_editor_*`). Le JS `assets/js/quizzes-editor.js` orchestre
 * le drag&drop, l'autosave au fil de l'eau et l'ouverture/fermeture des modales.
 *
 * @package ACDC_OF_SAAS
 * @since   3.21.01
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Quizzes_Render_Editor_Trait {

    /* ==================================================================== */
    /*  Écran principal — Éditeur 3 zones                                   */
    /* ==================================================================== */

    /**
     * Rend l'écran d'édition complet d'un quiz.
     *
     * @param int    $quiz_id
     * @param string $purpose Finalité (live|positioning|assessment), pour breadcrumb.
     *
     * @return void
     */
    public function render_qz_editor_screen( $quiz_id, $purpose ) {
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) {
            echo '<div class="acdc-qz-notice acdc-qz-notice-error"><p>' . esc_html__( 'Quiz introuvable.', 'acdc-formation-saas' ) . '</p></div>';
            return;
        }

        $is_locked = (int) $quiz->is_locked === 1;
        $questions = $this->get_qz_questions_for_quiz( $quiz_id );
        $objectives = $this->get_qz_objectives_for_quiz( $quiz_id );

        // Question courante par défaut : la première, ou aucune si liste vide.
        $requested_q = isset( $_GET['question_id'] ) ? absint( wp_unslash( $_GET['question_id'] ) ) : 0;
        $current_question = null;
        if ( ! empty( $questions ) ) {
            if ( $requested_q > 0 ) {
                foreach ( $questions as $q ) {
                    if ( (int) $q->id === $requested_q ) {
                        $current_question = $q;
                        break;
                    }
                }
            }
            if ( ! $current_question ) {
                $current_question = $questions[0];
            }
        }
        ?>
        <div class="acdc-qz-editor"
             data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>"
             data-quiz-purpose="<?php echo esc_attr( $quiz->quiz_purpose ); ?>"
             data-quiz-locked="<?php echo $is_locked ? '1' : '0'; ?>">

            <header class="acdc-qz-editor-toolbar">
                <div class="acdc-qz-editor-title-wrap">
                    <h1 class="acdc-qz-editor-title">
                        <?php echo esc_html( $quiz->title ); ?>
                        <?php if ( (int) $quiz->version_number > 1 ) : ?>
                            <span class="acdc-qz-version-badge" title="<?php esc_attr_e( 'Version courante', 'acdc-formation-saas' ); ?>">
                                v<?php echo esc_html( (string) $quiz->version_number ); ?>
                            </span>
                        <?php endif; ?>
                    </h1>
                    <?php if ( $is_locked ) : ?>
                        <span class="acdc-qz-locked-flag" title="<?php esc_attr_e( 'Ce quiz est verrouillé : créez une nouvelle version pour le modifier.', 'acdc-formation-saas' ); ?>">
                            🔒 <?php esc_html_e( 'Verrouillé', 'acdc-formation-saas' ); ?>
                        </span>
                    <?php else : ?>
                        <?php
                        $status_labels = $this->get_quiz_status_labels();
                        $status_label  = isset( $status_labels[ $quiz->status ] ) ? $status_labels[ $quiz->status ] : $quiz->status;
                        ?>
                        <span class="acdc-qz-status-pill acdc-qz-status-pill-<?php echo esc_attr( $quiz->status ); ?>">
                            <?php echo esc_html( $status_label ); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="acdc-qz-editor-status" aria-live="polite">
                    <span class="acdc-qz-autosave-indicator" hidden>
                        <span class="acdc-qz-autosave-saving" hidden><?php esc_html_e( 'Enregistrement…', 'acdc-formation-saas' ); ?></span>
                        <span class="acdc-qz-autosave-saved" hidden><?php esc_html_e( 'Enregistré', 'acdc-formation-saas' ); ?></span>
                        <span class="acdc-qz-autosave-error" hidden><?php esc_html_e( 'Erreur', 'acdc-formation-saas' ); ?></span>
                    </span>
                </div>
                <div class="acdc-qz-editor-actions">
                    <?php if ( $is_locked ) : ?>
                        <button type="button" class="acdc-button acdc-button-primary acdc-qz-action-new-version"
                                data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>"
                                data-nonce="<?php echo esc_attr( wp_create_nonce( 'acdc_of_qz_create_new_version' ) ); ?>">
                            <?php esc_html_e( '+ Nouvelle version', 'acdc-formation-saas' ); ?>
                        </button>
                    <?php elseif ( in_array( $quiz->quiz_purpose, array( self::ACDC_OF_QZ_PURPOSE_POSITIONING, self::ACDC_OF_QZ_PURPOSE_ASSESSMENT ), true )
                        && in_array( $quiz->status, array( self::ACDC_OF_QZ_STATUS_ACTIVE, self::ACDC_OF_QZ_STATUS_DRAFT ), true ) ) : ?>
                        <button type="button" class="acdc-button acdc-button-primary" data-target="acdc-qz-modal-send-async">
                            <?php esc_html_e( '✉ Envoyer par e-mail', 'acdc-formation-saas' ); ?>
                        </button>
                    <?php elseif ( self::ACDC_OF_QZ_STATUS_ACTIVE === $quiz->status
                        /* ACDC 3.25.165 — Le lancement en salle dépend de la MODALITÉ, pas
                           de la finalité. Cette condition testait la finalité « quiz live » :
                           une évaluation des acquis réglée en synchrone n'affichait donc
                           aucun bouton de lancement, alors que la liste, elle, en proposait
                           un — c'est la même confusion des deux axes. */
                        && self::ACDC_OF_QZ_DELIVERY_LIVE_SYNC === $quiz->delivery_mode ) :
                        $launch_url = wp_nonce_url(
                            add_query_arg( array(
                                'action'  => 'acdc_of_qz_launch_live',
                                'quiz_id' => (int) $quiz_id,
                            ), admin_url( 'admin-post.php' ) ),
                            'acdc_of_qz_launch_live_' . (int) $quiz_id
                        );
                        ?>
                        <a href="<?php echo esc_url( $launch_url ); ?>" class="acdc-button acdc-button-primary" target="_blank" rel="noopener">
                            <?php esc_html_e( '▶ Lancer en live', 'acdc-formation-saas' ); ?>
                        </a>
                    <?php endif; ?>
                    <button type="button" class="acdc-button acdc-button-soft" data-target="acdc-qz-modal-objectives">
                        <?php
                        /* translators: %d: number of objectives */
                        printf( esc_html__( 'Objectifs (%d)', 'acdc-formation-saas' ), count( $objectives ) );
                        ?>
                    </button>
                    <?php if ( ! $is_locked ) : ?>
                        <button type="button"
                                class="acdc-button acdc-button-soft acdc-qz-import-ai-btn"
                                data-target="acdc-qz-modal-import-ai"
                                data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>">
                            ⬆ <?php esc_html_e( 'Importer via IA', 'acdc-formation-saas' ); ?>
                        </button>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_of_qz_export_json&quiz_id=' . (int) $quiz_id ), 'acdc_of_qz_export_json_' . (int) $quiz_id ) ); ?>"
                       class="acdc-button acdc-button-soft"
                       title="<?php esc_attr_e( 'Télécharger ce quiz au format JSON (réimport IA)', 'acdc-formation-saas' ); ?>">
                        ⬇ <?php esc_html_e( 'Exporter JSON', 'acdc-formation-saas' ); ?>
                    </a>
                    <button type="button" class="acdc-button acdc-button-soft" data-target="acdc-qz-modal-settings" title="<?php esc_attr_e( 'Paramètres globaux du quiz', 'acdc-formation-saas' ); ?>">
                        ⚙ <?php esc_html_e( 'Paramètres', 'acdc-formation-saas' ); ?>
                    </button>
                    <a href="<?php echo esc_url( $this->qz_admin_url( $purpose ) ); ?>" class="acdc-button acdc-button-soft">
                        <?php esc_html_e( 'Fermer', 'acdc-formation-saas' ); ?>
                    </a>
                </div>
            </header>

            <?php
            // 3.21.03.1 — Bandeau bleu post-envoi : tant qu'aucun apprenant n'a cliqué,
            // on alerte le formateur que ses modifications affecteraient les futurs clics.
            $invited_count = $this->count_qz_async_invited_for_quiz( $quiz_id );
            $clicked_count = $this->count_qz_async_clicked_for_quiz( $quiz_id );
            if ( ! $is_locked && $invited_count > 0 && 0 === $clicked_count ) :
                ?>
                <div class="acdc-qz-pending-banner">
                    <strong>ℹ️ <?php esc_html_e( 'Quiz envoyé, en attente du premier clic.', 'acdc-formation-saas' ); ?></strong>
                    <span>
                        <?php
                        /* translators: %1$d: clicked, %2$d: invited */
                        printf(
                            esc_html__( '%1$d / %2$d apprenants ont commencé.', 'acdc-formation-saas' ),
                            $clicked_count, $invited_count
                        );
                        ?>
                    </span>
                    <p>
                        <?php esc_html_e( "Vous pouvez encore modifier ce quiz. Mais dès qu'un apprenant cliquera son lien, le quiz sera automatiquement verrouillé pour traçabilité Qualiopi. Les apprenants suivants verront alors la version au moment du verrouillage.", 'acdc-formation-saas' ); ?>
                    </p>
                </div>
            <?php elseif ( ! $is_locked && $invited_count > 0 && $clicked_count > 0 && self::ACDC_OF_QZ_DELIVERY_LIVE_SYNC !== $quiz->delivery_mode ) :
                /* Ce cas est théoriquement impossible en passation asynchrone : le
                   verrouillage se déclenche au premier clic. Il l'est en revanche tout
                   à fait NORMAL en quiz live, où l'on joue sans lien d'invitation —
                   l'alerte se déclenchait donc après chaque session live et signalait
                   une incohérence qui n'en était pas une. Le mode live est exclu.
                   ACDC 3.25.158 — le pluriel était en outre figé (« 1 apprenants »). */
                ?>
                <div class="acdc-qz-pending-banner acdc-qz-pending-banner-warning">
                    <strong>⚠️ <?php esc_html_e( 'État incohérent détecté.', 'acdc-formation-saas' ); ?></strong>
                    <span>
                        <?php
                        printf(
                            esc_html( _n( '%1$d apprenant a commencé, mais le quiz n\'est pas verrouillé.', '%1$d apprenants ont commencé, mais le quiz n\'est pas verrouillé.', $clicked_count, 'acdc-formation-saas' ) ),
                            $clicked_count
                        );
                        ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ( $is_locked ) : ?>
                <div class="acdc-qz-locked-banner">
                    <strong>🔒 <?php esc_html_e( 'Ce quiz est verrouillé.', 'acdc-formation-saas' ); ?></strong>
                    <?php
                    if ( ! empty( $quiz->locked_at ) ) {
                        echo ' ';
                        printf(
                            /* translators: %s: locked date in human-readable form */
                            esc_html__( 'Verrouillé le %s. ', 'acdc-formation-saas' ),
                            esc_html( mysql2date( 'j F Y à H\hi', $quiz->locked_at, true ) )
                        );
                    }
                    if ( ! empty( $quiz->locked_reason ) && 'async_first_click' === $quiz->locked_reason ) {
                        esc_html_e( "Verrouillé suite au premier clic d'apprenant. ", 'acdc-formation-saas' );
                    }
                    ?>
                    <?php esc_html_e( "Pour le faire évoluer, créez une nouvelle version.", 'acdc-formation-saas' ); ?>
                </div>
            <?php endif; ?>

            <div class="acdc-qz-editor-layout">
                <!-- Panneau gauche : liste des questions (miniatures) -->
                <aside class="acdc-qz-editor-pane acdc-qz-editor-pane-left">
                    <h2 class="acdc-qz-pane-title"><?php esc_html_e( 'Questions', 'acdc-formation-saas' ); ?></h2>

                    <?php if ( empty( $questions ) ) : ?>
                        <p class="acdc-qz-pane-empty">
                            <?php esc_html_e( 'Aucune question pour le moment.', 'acdc-formation-saas' ); ?>
                        </p>
                    <?php else : ?>
                        <ol class="acdc-qz-question-list" id="acdc-qz-question-list" data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>">
                            <?php foreach ( $questions as $idx => $q ) : ?>
                                <?php $this->render_qz_question_thumb( $q, $current_question && (int) $q->id === (int) $current_question->id, (int) $idx + 1 ); ?>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>

                    <?php if ( ! $is_locked ) : ?>
                        <button type="button" class="acdc-button acdc-button-primary acdc-qz-btn-add-question" data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>">
                            + <?php esc_html_e( 'Ajouter une question', 'acdc-formation-saas' ); ?>
                        </button>
                    <?php endif; ?>
                </aside>

                <!-- Zone centrale : édition de la question courante -->
                <section class="acdc-qz-editor-pane acdc-qz-editor-pane-center" id="acdc-qz-question-editor">
                    <?php if ( $current_question ) : ?>
                        <?php $this->render_qz_question_editor( $current_question, $objectives, $is_locked ); ?>
                    <?php else : ?>
                        <div class="acdc-qz-editor-empty">
                            <p>
                                <?php esc_html_e( "Ce quiz est encore vide. Cliquez sur « Ajouter une question » pour démarrer.", 'acdc-formation-saas' ); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Panneau droit : paramètres de la question -->
                <aside class="acdc-qz-editor-pane acdc-qz-editor-pane-right" id="acdc-qz-question-settings">
                    <?php if ( $current_question ) : ?>
                        <?php $this->render_qz_question_settings_panel( $current_question, $objectives, $is_locked ); ?>
                    <?php else : ?>
                        <p class="acdc-qz-pane-empty"><?php esc_html_e( 'Aucune question sélectionnée.', 'acdc-formation-saas' ); ?></p>
                    <?php endif; ?>
                </aside>
            </div>
        </div>

        <?php
        // Modales attachées à cet écran.
        $this->render_qz_modal_settings( $quiz );
        $this->render_qz_modal_objectives( $quiz_id, $objectives );

        // 3.21.03.1 — Modale d'envoi async (positionnement et évaluation, draft ou actif)
        if ( ! $is_locked
            && in_array( $quiz->quiz_purpose, array( self::ACDC_OF_QZ_PURPOSE_POSITIONING, self::ACDC_OF_QZ_PURPOSE_ASSESSMENT ), true )
            && in_array( $quiz->status, array( self::ACDC_OF_QZ_STATUS_DRAFT, self::ACDC_OF_QZ_STATUS_ACTIVE ), true ) ) {
            $this->render_qz_modal_send_async( $quiz );
        }

        // 3.21.03.1 — Écran de suivi des sessions async (s'il y en a)
        $async_sessions = $this->get_qz_async_sessions_for_quiz( $quiz_id );
        if ( ! empty( $async_sessions ) ) {
            $this->render_qz_async_sessions_panel( $quiz, $async_sessions );
        }

        // 3.21.30 — Modale import IA (Mode A — non verrouillé uniquement)
        if ( ! $is_locked ) {
            $this->render_qz_modal_import_ai( $quiz_id );
        }
        ?>
        <?php
    }

    /* ==================================================================== */
    /*  Panneau gauche — Miniature de question                              */
    /* ==================================================================== */

    /**
     * Rend une vignette de question dans la liste de gauche.
     *
     * @param object $question
     * @param bool   $is_current
     * @param int    $position 1-based.
     *
     * @return void
     */
    private function render_qz_question_thumb( $question, $is_current, $position ) {
        $type_labels = $this->get_quiz_question_type_labels_v2();
        $type_label  = isset( $type_labels[ $question->type ] ) ? $type_labels[ $question->type ] : (string) $question->type;
        $title_short = '' !== trim( (string) $question->title )
            ? mb_substr( wp_strip_all_tags( $question->title ), 0, 60 )
            : __( '(question sans titre)', 'acdc-formation-saas' );
        ?>
        <li class="acdc-qz-question-thumb <?php echo $is_current ? 'is-current' : ''; ?>"
            data-question-id="<?php echo esc_attr( (int) $question->id ); ?>"
            data-type="<?php echo esc_attr( $question->type ); ?>">
            <span class="acdc-qz-question-thumb-handle" aria-label="<?php esc_attr_e( 'Déplacer', 'acdc-formation-saas' ); ?>">⋮⋮</span>
            <span class="acdc-qz-question-thumb-num"><?php echo esc_html( (string) $position ); ?></span>
            <span class="acdc-qz-question-thumb-content">
                <span class="acdc-qz-question-thumb-title"><?php echo esc_html( $title_short ); ?></span>
                <span class="acdc-qz-question-thumb-type"><?php echo esc_html( $type_label ); ?></span>
            </span>
            <button type="button" class="acdc-qz-question-thumb-delete" aria-label="<?php esc_attr_e( 'Supprimer', 'acdc-formation-saas' ); ?>" title="<?php esc_attr_e( 'Supprimer cette question', 'acdc-formation-saas' ); ?>">×</button>
        </li>
        <?php
    }

    /* ==================================================================== */
    /*  Zone centrale — Éditeur de question                                 */
    /* ==================================================================== */

    /**
     * Rend l'éditeur de la question courante (zone centrale).
     *
     * @param object $question
     * @param array  $objectives
     * @param bool   $is_locked
     *
     * @return void
     */
    private function render_qz_question_editor( $question, $objectives, $is_locked ) {
        $disabled = $is_locked ? 'disabled' : '';
        $type     = (string) $question->type;
        $answers  = $this->get_qz_answers_for_question_safely( (int) $question->id );

        // Image illustrative.
        $media_id  = isset( $question->media_id ) ? (int) $question->media_id : 0;
        $media_url = $media_id > 0 ? wp_get_attachment_image_url( $media_id, 'medium' ) : '';
        ?>
        <form class="acdc-qz-question-form"
              data-question-id="<?php echo esc_attr( (int) $question->id ); ?>"
              data-type="<?php echo esc_attr( $type ); ?>">

            <div class="acdc-qz-field">
                <label for="acdc-qz-q-title-<?php echo esc_attr( (int) $question->id ); ?>" class="acdc-qz-field-label">
                    <?php esc_html_e( 'Énoncé', 'acdc-formation-saas' ); ?>
                </label>
                <textarea
                    id="acdc-qz-q-title-<?php echo esc_attr( (int) $question->id ); ?>"
                    name="title"
                    rows="3"
                    class="acdc-qz-input acdc-qz-input-title"
                    placeholder="<?php esc_attr_e( "Saisissez l'énoncé de la question…", 'acdc-formation-saas' ); ?>"
                    <?php echo esc_attr( $disabled ); ?>><?php echo esc_textarea( (string) $question->title ); ?></textarea>
            </div>

            <div class="acdc-qz-field acdc-qz-field-media">
                <label class="acdc-qz-field-label">
                    <?php esc_html_e( "Image illustrative (optionnelle)", 'acdc-formation-saas' ); ?>
                </label>
                <div class="acdc-qz-media-picker" data-target="question">
                    <input type="hidden" name="media_id" value="<?php echo esc_attr( $media_id ); ?>" />
                    <div class="acdc-qz-media-preview">
                        <?php if ( $media_url ) : ?>
                            <img src="<?php echo esc_url( $media_url ); ?>" alt="" />
                        <?php endif; ?>
                    </div>
                    <button type="button" class="acdc-button acdc-button-soft acdc-qz-media-pick" <?php echo esc_attr( $disabled ); ?>>
                        <?php echo $media_id ? esc_html__( "Remplacer l'image", 'acdc-formation-saas' ) : esc_html__( 'Ajouter une image', 'acdc-formation-saas' ); ?>
                    </button>
                    <?php if ( $media_id ) : ?>
                        <button type="button" class="acdc-button acdc-button-link acdc-qz-media-clear" <?php echo esc_attr( $disabled ); ?>>
                            <?php esc_html_e( 'Retirer', 'acdc-formation-saas' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="acdc-qz-field">
                <label class="acdc-qz-field-label"><?php esc_html_e( 'Réponses', 'acdc-formation-saas' ); ?></label>
                <div class="acdc-qz-answers-zone" data-type="<?php echo esc_attr( $type ); ?>" data-locked="<?php echo $is_locked ? '1' : '0'; ?>">
                    <?php $this->render_qz_answers_zone( $type, $answers, $is_locked ); ?>
                </div>
            </div>

            <div class="acdc-qz-field acdc-qz-field-description">
                <label for="acdc-qz-q-desc-<?php echo esc_attr( (int) $question->id ); ?>" class="acdc-qz-field-label">
                    <?php esc_html_e( 'Aide / explication (optionnelle)', 'acdc-formation-saas' ); ?>
                </label>
                <textarea
                    id="acdc-qz-q-desc-<?php echo esc_attr( (int) $question->id ); ?>"
                    name="description"
                    rows="2"
                    class="acdc-qz-input"
                    placeholder="<?php esc_attr_e( "Texte d'aide ou explication affiché après réponse…", 'acdc-formation-saas' ); ?>"
                    <?php echo esc_attr( $disabled ); ?>><?php echo esc_textarea( (string) $question->description ); ?></textarea>
            </div>
        </form>
        <?php
    }

    /**
     * Charge les réponses d'une question. Sécurise contre l'absence d'une
     * éventuelle méthode helper non encore définie en Core.
     *
     * @param int $question_id
     *
     * @return array
     */
    private function get_qz_answers_for_question_safely( $question_id ) {
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

    /* ==================================================================== */
    /*  Zone réponses — Rendu par type de question                          */
    /* ==================================================================== */

    /**
     * Rend la zone de réponses adaptée au type de question.
     *
     * @param string $type
     * @param array  $answers
     * @param bool   $is_locked
     *
     * @return void
     */
    private function render_qz_answers_zone( $type, $answers, $is_locked ) {
        switch ( $type ) {
            case self::ACDC_OF_QZ_QTYPE_QCM_SINGLE:
                $this->render_qz_answers_qcm( $answers, $is_locked, false );
                break;
            case self::ACDC_OF_QZ_QTYPE_QCM_MULTIPLE:
                $this->render_qz_answers_qcm( $answers, $is_locked, true );
                break;
            case self::ACDC_OF_QZ_QTYPE_TRUE_FALSE:
                $this->render_qz_answers_true_false( $answers, $is_locked );
                break;
            case self::ACDC_OF_QZ_QTYPE_PUZZLE:
                $this->render_qz_answers_puzzle( $answers, $is_locked );
                break;
            case self::ACDC_OF_QZ_QTYPE_OPEN_TEXT:
                $this->render_qz_answers_open_text();
                break;
            case self::ACDC_OF_QZ_QTYPE_POLL:
                $this->render_qz_answers_poll( $answers, $is_locked );
                break;
            default:
                $this->render_qz_answers_qcm( $answers, $is_locked, false );
                break;
        }
    }

    /**
     * QCM (single ou multiple). Les réponses ont une coche "correcte".
     *
     * @param array $answers
     * @param bool  $is_locked
     * @param bool  $multiple
     *
     * @return void
     */
    private function render_qz_answers_qcm( $answers, $is_locked, $multiple ) {
        $disabled = $is_locked ? 'disabled' : '';
        // Au minimum 2 lignes vides pour faciliter la saisie au clic.
        $needed = max( 2, count( $answers ) );
        ?>
        <ul class="acdc-qz-answers-list acdc-qz-answers-qcm" data-multiple="<?php echo $multiple ? '1' : '0'; ?>">
            <?php for ( $i = 0; $i < $needed; $i++ ) :
                $a = isset( $answers[ $i ] ) ? $answers[ $i ] : null;
                $aid = $a ? (int) $a->id : 0;
                $atext = $a ? (string) $a->text : '';
                $iscorrect = $a ? ( (int) $a->is_correct === 1 ) : false;
                ?>
                <li class="acdc-qz-answer-row" data-answer-id="<?php echo esc_attr( $aid ); ?>" data-sort-order="<?php echo esc_attr( $i ); ?>">
                    <span class="acdc-qz-answer-handle" aria-label="<?php esc_attr_e( 'Déplacer', 'acdc-formation-saas' ); ?>">⋮⋮</span>
                    <input type="<?php echo $multiple ? 'checkbox' : 'radio'; ?>"
                           name="acdc_qz_correct[]"
                           class="acdc-qz-answer-correct"
                           <?php checked( $iscorrect ); ?>
                           <?php echo esc_attr( $disabled ); ?>
                           aria-label="<?php esc_attr_e( 'Marquer comme bonne réponse', 'acdc-formation-saas' ); ?>" />
                    <input type="text"
                           name="acdc_qz_answer_text[]"
                           class="acdc-qz-answer-text acdc-qz-input"
                           value="<?php echo esc_attr( $atext ); ?>"
                           placeholder="<?php esc_attr_e( 'Saisissez une proposition…', 'acdc-formation-saas' ); ?>"
                           maxlength="500"
                           <?php echo esc_attr( $disabled ); ?> />
                    <button type="button" class="acdc-qz-answer-delete" aria-label="<?php esc_attr_e( 'Retirer', 'acdc-formation-saas' ); ?>" <?php echo esc_attr( $disabled ); ?>>×</button>
                </li>
            <?php endfor; ?>
        </ul>
        <?php if ( ! $is_locked ) : ?>
            <button type="button" class="acdc-button acdc-button-soft acdc-qz-answer-add">
                + <?php esc_html_e( 'Ajouter une proposition', 'acdc-formation-saas' ); ?>
            </button>
        <?php endif; ?>
        <?php
    }

    /**
     * Vrai / Faux : exactement 2 réponses figées.
     *
     * @param array $answers
     * @param bool  $is_locked
     *
     * @return void
     */
    private function render_qz_answers_true_false( $answers, $is_locked ) {
        $disabled = $is_locked ? 'disabled' : '';
        // Détermine quelle est la réponse "correcte" parmi les 2.
        $correct_index = 0;
        if ( ! empty( $answers ) ) {
            foreach ( $answers as $idx => $a ) {
                if ( (int) $a->is_correct === 1 ) {
                    $correct_index = (int) $idx;
                    break;
                }
            }
        }
        $true_id  = isset( $answers[0] ) ? (int) $answers[0]->id : 0;
        $false_id = isset( $answers[1] ) ? (int) $answers[1]->id : 0;
        ?>
        <ul class="acdc-qz-answers-list acdc-qz-answers-true-false">
            <li class="acdc-qz-answer-row" data-answer-id="<?php echo esc_attr( $true_id ); ?>" data-sort-order="0">
                <input type="radio" name="acdc_qz_tf_correct" class="acdc-qz-answer-correct" value="0" <?php checked( 0 === $correct_index ); ?> <?php echo esc_attr( $disabled ); ?> />
                <input type="hidden" name="acdc_qz_answer_text[]" value="<?php echo esc_attr( __( 'Vrai', 'acdc-formation-saas' ) ); ?>" />
                <strong><?php esc_html_e( 'Vrai', 'acdc-formation-saas' ); ?></strong>
            </li>
            <li class="acdc-qz-answer-row" data-answer-id="<?php echo esc_attr( $false_id ); ?>" data-sort-order="1">
                <input type="radio" name="acdc_qz_tf_correct" class="acdc-qz-answer-correct" value="1" <?php checked( 1 === $correct_index ); ?> <?php echo esc_attr( $disabled ); ?> />
                <input type="hidden" name="acdc_qz_answer_text[]" value="<?php echo esc_attr( __( 'Faux', 'acdc-formation-saas' ) ); ?>" />
                <strong><?php esc_html_e( 'Faux', 'acdc-formation-saas' ); ?></strong>
            </li>
        </ul>
        <p class="description">
            <?php esc_html_e( "Cochez la proposition correcte (Vrai ou Faux).", 'acdc-formation-saas' ); ?>
        </p>
        <?php
    }

    /**
     * Puzzle : remettre les éléments dans l'ordre. L'ordre saisi côté éditeur
     * est l'ordre attendu (la bonne réponse).
     *
     * @param array $answers
     * @param bool  $is_locked
     *
     * @return void
     */
    private function render_qz_answers_puzzle( $answers, $is_locked ) {
        $disabled = $is_locked ? 'disabled' : '';
        $needed = max( 3, count( $answers ) );
        ?>
        <p class="description">
            <?php esc_html_e( "Saisissez les éléments dans l'ordre attendu (cet ordre sera la bonne réponse). Les apprenants verront ces éléments mélangés et devront les remettre dans le bon ordre.", 'acdc-formation-saas' ); ?>
        </p>
        <ol class="acdc-qz-answers-list acdc-qz-answers-puzzle">
            <?php for ( $i = 0; $i < $needed; $i++ ) :
                $a = isset( $answers[ $i ] ) ? $answers[ $i ] : null;
                $aid = $a ? (int) $a->id : 0;
                $atext = $a ? (string) $a->text : '';
                ?>
                <li class="acdc-qz-answer-row" data-answer-id="<?php echo esc_attr( $aid ); ?>" data-sort-order="<?php echo esc_attr( $i ); ?>">
                    <span class="acdc-qz-answer-handle" aria-label="<?php esc_attr_e( 'Déplacer', 'acdc-formation-saas' ); ?>">⋮⋮</span>
                    <span class="acdc-qz-answer-pos"><?php echo esc_html( (string) ( $i + 1 ) ); ?></span>
                    <input type="hidden" name="acdc_qz_correct[]" value="1" />
                    <input type="text"
                           name="acdc_qz_answer_text[]"
                           class="acdc-qz-answer-text acdc-qz-input"
                           value="<?php echo esc_attr( $atext ); ?>"
                           placeholder="<?php esc_attr_e( "Élément à placer…", 'acdc-formation-saas' ); ?>"
                           maxlength="500"
                           <?php echo esc_attr( $disabled ); ?> />
                    <button type="button" class="acdc-qz-answer-delete" aria-label="<?php esc_attr_e( 'Retirer', 'acdc-formation-saas' ); ?>" <?php echo esc_attr( $disabled ); ?>>×</button>
                </li>
            <?php endfor; ?>
        </ol>
        <?php if ( ! $is_locked ) : ?>
            <button type="button" class="acdc-button acdc-button-soft acdc-qz-answer-add">
                + <?php esc_html_e( 'Ajouter un élément', 'acdc-formation-saas' ); ?>
            </button>
        <?php endif; ?>
        <?php
    }

    /**
     * Réponse libre (texte) : rien à saisir côté éditeur, juste un message.
     *
     * @return void
     */
    private function render_qz_answers_open_text() {
        global $wpdb;
        // Récupérer la réponse attendue existante si la colonne existe
        $expected = '';
        $qid = isset( $_GET['qz_question'] ) ? absint( $_GET['qz_question'] ) : 0;
        if ( $qid > 0 ) {
            $tbl_q = $this->get_qz_table( 'questions' );
            $cols  = $wpdb->get_col( "SHOW COLUMNS FROM {$tbl_q} LIKE 'expected_answer'" );
            if ( ! empty( $cols ) ) {
                $expected = (string) $wpdb->get_var( $wpdb->prepare(
                    "SELECT expected_answer FROM {$tbl_q} WHERE id=%d LIMIT 1", $qid
                ) );
            }
        }
        ?>
        <div class="acdc-qz-answers-open-text">
            <p class="description">
                <?php esc_html_e( "Les apprenants répondent en texte libre. Ce type de question est corrigé manuellement par le formateur après la session.", 'acdc-formation-saas' ); ?>
            </p>
            <div style="margin-top:12px">
                <label style="display:block;font-weight:700;color:#0f2c52;margin-bottom:6px">
                    Réponse attendue <small style="font-weight:400;color:#4b5d76">(affichée sur l'écran host lors de la révélation)</small>
                </label>
                <textarea name="expected_answer" rows="3"
                    style="width:100%;max-width:600px;padding:8px 10px;border:1px solid #d6a353;border-radius:6px;font-family:inherit;font-size:13px;resize:vertical"
                    placeholder="Saisissez ici la réponse type que vous souhaitez partager avec les apprenants…"><?php echo esc_textarea( $expected ); ?></textarea>
            </div>
        </div>
        <?php
    }

    /**
     * Sondage : comme un QCM mais sans bonne réponse.
     *
     * @param array $answers
     * @param bool  $is_locked
     *
     * @return void
     */
    private function render_qz_answers_poll( $answers, $is_locked ) {
        $disabled = $is_locked ? 'disabled' : '';
        $needed = max( 2, count( $answers ) );
        ?>
        <p class="description">
            <?php esc_html_e( "Sondage d'opinion : aucune réponse n'est juste ou fausse, les résultats sont affichés sous forme de barres en fin de question.", 'acdc-formation-saas' ); ?>
        </p>
        <ul class="acdc-qz-answers-list acdc-qz-answers-poll">
            <?php for ( $i = 0; $i < $needed; $i++ ) :
                $a = isset( $answers[ $i ] ) ? $answers[ $i ] : null;
                $aid = $a ? (int) $a->id : 0;
                $atext = $a ? (string) $a->text : '';
                ?>
                <li class="acdc-qz-answer-row" data-answer-id="<?php echo esc_attr( $aid ); ?>" data-sort-order="<?php echo esc_attr( $i ); ?>">
                    <span class="acdc-qz-answer-handle" aria-label="<?php esc_attr_e( 'Déplacer', 'acdc-formation-saas' ); ?>">⋮⋮</span>
                    <input type="hidden" name="acdc_qz_correct[]" value="0" />
                    <input type="text"
                           name="acdc_qz_answer_text[]"
                           class="acdc-qz-answer-text acdc-qz-input"
                           value="<?php echo esc_attr( $atext ); ?>"
                           placeholder="<?php esc_attr_e( "Saisissez une option…", 'acdc-formation-saas' ); ?>"
                           maxlength="500"
                           <?php echo esc_attr( $disabled ); ?> />
                    <button type="button" class="acdc-qz-answer-delete" aria-label="<?php esc_attr_e( 'Retirer', 'acdc-formation-saas' ); ?>" <?php echo esc_attr( $disabled ); ?>>×</button>
                </li>
            <?php endfor; ?>
        </ul>
        <?php if ( ! $is_locked ) : ?>
            <button type="button" class="acdc-button acdc-button-soft acdc-qz-answer-add">
                + <?php esc_html_e( 'Ajouter une option', 'acdc-formation-saas' ); ?>
            </button>
        <?php endif; ?>
        <?php
    }

    /* ==================================================================== */
    /*  Panneau droit — Paramètres de la question                           */
    /* ==================================================================== */

    /**
     * Rend le panneau droit (paramètres de la question courante).
     *
     * @param object $question
     * @param array  $objectives
     * @param bool   $is_locked
     *
     * @return void
     */
    private function render_qz_question_settings_panel( $question, $objectives, $is_locked ) {
        $disabled = $is_locked ? 'disabled' : '';
        $type_labels = $this->get_quiz_question_type_labels_v2();
        ?>
        <h2 class="acdc-qz-pane-title"><?php esc_html_e( 'Paramètres', 'acdc-formation-saas' ); ?></h2>

        <form class="acdc-qz-settings-form">
            <div class="acdc-qz-field">
                <label class="acdc-qz-field-label"><?php esc_html_e( 'Type', 'acdc-formation-saas' ); ?></label>
                <select name="type" class="acdc-qz-input" <?php echo esc_attr( $disabled ); ?>>
                    <?php foreach ( $type_labels as $key => $label ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $question->type, $key ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="acdc-qz-field">
                <?php
                /* ACDC 3.25.157 — Le compte à rebours n'existe que sur le quiz live.
                   En passation asynchrone (positionnement, évaluation des acquis) cette
                   durée ne chronomètre rien : elle ne sert qu'à estimer le temps annoncé
                   à l'apprenant sur l'écran d'accueil. Le libellé « Temps limite » y
                   laissait croire à une interruption automatique qui n'a jamais lieu. */
                $tl_quiz    = isset( $question->quiz_id ) ? $this->get_qz_quiz( (int) $question->quiz_id ) : null;
                $tl_is_live = $tl_quiz && self::ACDC_OF_QZ_DELIVERY_LIVE_SYNC === $tl_quiz->delivery_mode;
                ?>
                <label class="acdc-qz-field-label">
                    <?php echo esc_html( $tl_is_live ? __( 'Temps limite', 'acdc-formation-saas' ) : __( 'Durée estimée', 'acdc-formation-saas' ) ); ?>
                </label>
                <?php if ( ! $tl_is_live ) : ?>
                <p class="acdc-qz-field-help" style="margin:0 0 6px;font-size:12px;color:#6b7280;">
                    <?php esc_html_e( 'Sert au temps annoncé à l’apprenant. La passation n’est pas chronométrée hors quiz live.', 'acdc-formation-saas' ); ?>
                </p>
                <?php endif; ?>
                <?php /* ACDC 3.25.168 — Sur une réponse à rédiger, le champ n'a pas de sens :
                         on le remplace par la règle appliquée, et le JS le masque au
                         changement de type. */ ?>
                <p class="acdc-qz-field-help acdc-qz-time-limit-na" style="margin:0;font-size:13px;color:#b91c1c;font-weight:600;<?php echo ( self::ACDC_OF_QZ_QTYPE_OPEN_TEXT === (string) $question->type ) ? '' : 'display:none;'; ?>">
                    <?php esc_html_e( 'Pas de limite de temps : une réponse rédigée ne se chronomètre pas.', 'acdc-formation-saas' ); ?>
                </p>
                <select name="time_limit" class="acdc-qz-input" <?php echo esc_attr( $disabled ); ?> <?php echo ( self::ACDC_OF_QZ_QTYPE_OPEN_TEXT === (string) $question->type ) ? 'style="display:none;"' : ''; ?>>
                    <?php foreach ( array( 5, 10, 20, 30, 45, 60, 90, 120, 180, 300, 600 ) as $sec ) : ?>
                        <option value="<?php echo esc_attr( $sec ); ?>" <?php selected( (int) $question->time_limit, $sec ); ?>>
                            <?php
                            /* translators: %d: number of seconds */
                            printf( esc_html( _n( '%d seconde', '%d secondes', $sec, 'acdc-formation-saas' ) ), (int) $sec );
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="acdc-qz-field">
                <label class="acdc-qz-field-label"><?php esc_html_e( 'Type de points', 'acdc-formation-saas' ); ?></label>
                <select name="points_type" class="acdc-qz-input" <?php echo esc_attr( $disabled ); ?>>
                    <option value="standard" <?php selected( $question->points_type, 'standard' ); ?>><?php esc_html_e( 'Standard', 'acdc-formation-saas' ); ?></option>
                    <option value="double" <?php selected( $question->points_type, 'double' ); ?>><?php esc_html_e( 'Double', 'acdc-formation-saas' ); ?></option>
                    <option value="none" <?php selected( $question->points_type, 'none' ); ?>><?php esc_html_e( 'Aucun (non scoré)', 'acdc-formation-saas' ); ?></option>
                </select>
            </div>

            <div class="acdc-qz-field">
                <label class="acdc-qz-field-label"><?php esc_html_e( 'Points (max)', 'acdc-formation-saas' ); ?></label>
                <input type="number" name="points_value" class="acdc-qz-input" min="0" max="65535" step="100" value="<?php echo esc_attr( (int) $question->points_value ); ?>" <?php echo esc_attr( $disabled ); ?> />
            </div>

            <div class="acdc-qz-field">
                <label class="acdc-qz-field-label"><?php esc_html_e( 'Objectif pédagogique', 'acdc-formation-saas' ); ?></label>
                <select name="objective_id" class="acdc-qz-input" <?php echo esc_attr( $disabled ); ?>>
                    <option value="0">— <?php esc_html_e( 'Aucun', 'acdc-formation-saas' ); ?> —</option>
                    <?php foreach ( $objectives as $obj ) : ?>
                        <option value="<?php echo esc_attr( (int) $obj->id ); ?>" <?php selected( (int) $question->objective_id, (int) $obj->id ); ?>>
                            <?php echo esc_html( $obj->label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">
                    <?php esc_html_e( "Rattachez la question à un objectif pour calculer le score par compétence.", 'acdc-formation-saas' ); ?>
                </p>
            </div>

            <div class="acdc-qz-field">
                <label class="acdc-qz-field-label">
                    <input type="checkbox" name="is_scored" value="1" <?php checked( (int) $question->is_scored, 1 ); ?> <?php echo esc_attr( $disabled ); ?> />
                    <?php esc_html_e( "Question scorée", 'acdc-formation-saas' ); ?>
                </label>
                <p class="description">
                    <?php esc_html_e( "Décocher si la question ne doit pas compter dans la note finale (ex. question d'animation).", 'acdc-formation-saas' ); ?>
                </p>
            </div>
        </form>
        <?php
    }

    /* ==================================================================== */
    /*  Modale "Paramètres globaux du quiz"                                 */
    /* ==================================================================== */

    /**
     * Modale de paramétrage global du quiz (ouverte par le bouton ⚙).
     *
     * @param object $quiz
     *
     * @return void
     */
    private function render_qz_modal_settings( $quiz ) {
        $is_locked = (int) $quiz->is_locked === 1;
        $disabled  = $is_locked ? 'disabled' : '';
        ?>
        <div id="acdc-qz-modal-settings" class="acdc-qz-modal" hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Paramètres du quiz', 'acdc-formation-saas' ); ?>">
            <div class="acdc-qz-modal-overlay" data-close></div>
            <div class="acdc-qz-modal-dialog acdc-qz-modal-dialog-large">
                <header class="acdc-qz-modal-header">
                    <h2><?php esc_html_e( 'Paramètres du quiz', 'acdc-formation-saas' ); ?></h2>
                    <button type="button" class="acdc-qz-modal-close" data-close aria-label="<?php esc_attr_e( 'Fermer', 'acdc-formation-saas' ); ?>">×</button>
                </header>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acdc-qz-modal-body acdc-qz-tabs">
                    <input type="hidden" name="action" value="acdc_of_qz_save_quiz" />
                    <input type="hidden" name="quiz_id" value="<?php echo esc_attr( (int) $quiz->id ); ?>" />
                    <?php wp_nonce_field( 'acdc_of_qz_save_quiz', '_acdc_qz_nonce' ); ?>

                    <nav class="acdc-qz-tabs-nav">
                        <button type="button" class="acdc-qz-tab-link is-active" data-tab="general"><?php esc_html_e( 'Général', 'acdc-formation-saas' ); ?></button>
                        <button type="button" class="acdc-qz-tab-link" data-tab="scoring"><?php esc_html_e( 'Scoring', 'acdc-formation-saas' ); ?></button>
                        <button type="button" class="acdc-qz-tab-link" data-tab="antifraud"><?php esc_html_e( 'Anti-triche', 'acdc-formation-saas' ); ?></button>
                        <button type="button" class="acdc-qz-tab-link" data-tab="rgpd"><?php esc_html_e( 'RGPD', 'acdc-formation-saas' ); ?></button>
                    </nav>

                    <!-- Onglet général -->
                    <div class="acdc-qz-tab-pane is-active" data-tab-pane="general">
                        <p>
                            <label>
                                <?php /* ACDC 3.25.157 — L'astérisque est déjà ajouté par .acdc-required::after (CSS) : le répéter ici affichait « Titre * * ». */ ?><span class="acdc-required"><?php esc_html_e( 'Titre', 'acdc-formation-saas' ); ?></span>
                                <input type="text" name="title" value="<?php echo esc_attr( $quiz->title ); ?>" required maxlength="200" <?php echo esc_attr( $disabled ); ?> />
                            </label>
                        </p>
                        <p>
                            <label>
                                <span><?php esc_html_e( 'Description', 'acdc-formation-saas' ); ?></span>
                                <textarea name="description" rows="4" <?php echo esc_attr( $disabled ); ?>><?php echo esc_textarea( (string) $quiz->description ); ?></textarea>
                            </label>
                        </p>
                        <?php if ( self::ACDC_OF_QZ_PURPOSE_ASSESSMENT === $quiz->quiz_purpose ) : ?>
                            <p>
                                <label>
                                    <span><?php esc_html_e( 'Mode de passation', 'acdc-formation-saas' ); ?></span>
                                    <select name="delivery_mode" <?php echo esc_attr( $disabled ); ?>>
                                        <option value="<?php echo esc_attr( self::ACDC_OF_QZ_DELIVERY_ASYNC_TOKEN ); ?>" <?php selected( $quiz->delivery_mode, self::ACDC_OF_QZ_DELIVERY_ASYNC_TOKEN ); ?>>
                                            <?php esc_html_e( 'E-mail asynchrone', 'acdc-formation-saas' ); ?>
                                        </option>
                                        <option value="<?php echo esc_attr( self::ACDC_OF_QZ_DELIVERY_LIVE_SYNC ); ?>" <?php selected( $quiz->delivery_mode, self::ACDC_OF_QZ_DELIVERY_LIVE_SYNC ); ?>>
                                            <?php esc_html_e( 'Présentiel synchrone', 'acdc-formation-saas' ); ?>
                                        </option>
                                    </select>
                                </label>
                            </p>
                        <?php endif; ?>
                        <p>
                            <label>
                                <span><?php esc_html_e( 'Langue', 'acdc-formation-saas' ); ?></span>
                                <input type="text" name="language" value="<?php echo esc_attr( $quiz->language ); ?>" maxlength="10" <?php echo esc_attr( $disabled ); ?> />
                            </label>
                        </p>

                        <?php if ( ! $is_locked ) : ?>
                            <div class="acdc-qz-lifecycle-box">
                                <h3><?php esc_html_e( 'État du quiz', 'acdc-formation-saas' ); ?></h3>
                                <?php if ( self::ACDC_OF_QZ_STATUS_DRAFT === $quiz->status ) : ?>
                                    <p class="description">
                                        <?php esc_html_e( "Ce quiz est en brouillon. Activez-le pour pouvoir le lancer en session.", 'acdc-formation-saas' ); ?>
                                    </p>
                                    <button type="button" class="acdc-button acdc-button-primary acdc-qz-action-activate"
                                            data-quiz-id="<?php echo esc_attr( (int) $quiz->id ); ?>"
                                            data-nonce="<?php echo esc_attr( wp_create_nonce( 'acdc_of_qz_activate_quiz' ) ); ?>">
                                        ✓ <?php esc_html_e( 'Activer ce quiz', 'acdc-formation-saas' ); ?>
                                    </button>
                                    <p class="description acdc-qz-lifecycle-hint">
                                        <?php esc_html_e( "L'activation vérifie que le quiz est prêt (titre, questions, bonnes réponses) avant de le rendre lançable. Tant qu'aucune session réelle n'est lancée, vous pourrez encore le modifier ou le repasser en brouillon.", 'acdc-formation-saas' ); ?>
                                    </p>
                                <?php elseif ( self::ACDC_OF_QZ_STATUS_ACTIVE === $quiz->status ) : ?>
                                    <p class="description">
                                        <?php esc_html_e( "Ce quiz est actif. Il peut être lancé en session.", 'acdc-formation-saas' ); ?>
                                        <?php if ( ! empty( $quiz->activated_at ) ) {
                                            echo ' ';
                                            printf(
                                                /* translators: %s: activation date */
                                                esc_html__( 'Activé le %s.', 'acdc-formation-saas' ),
                                                esc_html( mysql2date( 'j F Y', $quiz->activated_at, true ) )
                                            );
                                        } ?>
                                    </p>
                                    <div class="acdc-qz-lifecycle-actions">
                                        <button type="button" class="acdc-button acdc-button-soft acdc-qz-action-unpublish"
                                                data-quiz-id="<?php echo esc_attr( (int) $quiz->id ); ?>"
                                                data-nonce="<?php echo esc_attr( wp_create_nonce( 'acdc_of_qz_unpublish_quiz' ) ); ?>">
                                            <?php esc_html_e( 'Repasser en brouillon', 'acdc-formation-saas' ); ?>
                                        </button>
                                        <button type="button" class="acdc-button acdc-button-soft acdc-qz-action-lock"
                                                data-quiz-id="<?php echo esc_attr( (int) $quiz->id ); ?>"
                                                data-nonce="<?php echo esc_attr( wp_create_nonce( 'acdc_of_qz_lock_quiz' ) ); ?>"
                                                title="<?php esc_attr_e( 'Verrouiller manuellement (utile pour figer une version sans attendre une session réelle)', 'acdc-formation-saas' ); ?>">
                                            🔒 <?php esc_html_e( 'Verrouiller manuellement', 'acdc-formation-saas' ); ?>
                                        </button>
                                    </div>
                                    <p class="description acdc-qz-lifecycle-hint">
                                        <?php esc_html_e( "Vous pouvez le repasser en brouillon tant qu'aucune session réelle n'a été lancée. Une fois utilisé en session, il sera verrouillé automatiquement.", 'acdc-formation-saas' ); ?>
                                    </p>
                                <?php elseif ( self::ACDC_OF_QZ_STATUS_ARCHIVED === $quiz->status ) : ?>
                                    <p class="description">
                                        <?php esc_html_e( "Ce quiz est archivé.", 'acdc-formation-saas' ); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Onglet scoring -->
                    <div class="acdc-qz-tab-pane" data-tab-pane="scoring">
                        <p>
                            <label>
                                <span><?php esc_html_e( 'Mode de scoring', 'acdc-formation-saas' ); ?></span>
                                <select name="scoring_mode" <?php echo esc_attr( $disabled ); ?>>
                                    <option value="percentage" <?php selected( $quiz->scoring_mode, 'percentage' ); ?>><?php esc_html_e( 'Pourcentage', 'acdc-formation-saas' ); ?></option>
                                    <option value="absolute" <?php selected( $quiz->scoring_mode, 'absolute' ); ?>><?php esc_html_e( 'Note absolue', 'acdc-formation-saas' ); ?></option>
                                    <option value="level_detection" <?php selected( $quiz->scoring_mode, 'level_detection' ); ?>><?php esc_html_e( 'Détection de niveau', 'acdc-formation-saas' ); ?></option>
                                </select>
                            </label>
                        </p>
                        <p>
                            <label>
                                <span><?php esc_html_e( "Seuil de réussite (%)", 'acdc-formation-saas' ); ?></span>
                                <input type="number" name="pass_threshold" min="0" max="100" step="0.5" value="<?php echo esc_attr( null === $quiz->pass_threshold ? '' : (string) $quiz->pass_threshold ); ?>" <?php echo esc_attr( $disabled ); ?> />
                            </label>
                        </p>
                        <p>
                            <label>
                                <span><?php esc_html_e( "Seuils de niveau (JSON, optionnel)", 'acdc-formation-saas' ); ?></span>
                                <textarea name="level_thresholds_json" rows="3" placeholder='{"A1":[0,20],"A2":[20,40],"B1":[40,60],"B2":[60,80],"C1":[80,100]}' <?php echo esc_attr( $disabled ); ?>><?php echo esc_textarea( (string) $quiz->level_thresholds_json ); ?></textarea>
                            </label>
                            <span class="description"><?php esc_html_e( "Utile pour les tests de positionnement (CECRL, niveaux personnalisés).", 'acdc-formation-saas' ); ?></span>
                        </p>
                        <p>
                            <label>
                                <span><?php esc_html_e( "Action en cas d'échec", 'acdc-formation-saas' ); ?></span>
                                <select name="failure_action" <?php echo esc_attr( $disabled ); ?>>
                                    <option value="none" <?php selected( $quiz->failure_action, 'none' ); ?>><?php esc_html_e( 'Aucune', 'acdc-formation-saas' ); ?></option>
                                    <option value="retake_allowed" <?php selected( $quiz->failure_action, 'retake_allowed' ); ?>><?php esc_html_e( 'Tentative supplémentaire autorisée', 'acdc-formation-saas' ); ?></option>
                                    <option value="recommend_complement" <?php selected( $quiz->failure_action, 'recommend_complement' ); ?>><?php esc_html_e( 'Recommander un complément', 'acdc-formation-saas' ); ?></option>
                                    <option value="attest_presence_only" <?php selected( $quiz->failure_action, 'attest_presence_only' ); ?>><?php esc_html_e( 'Attester la présence uniquement', 'acdc-formation-saas' ); ?></option>
                                </select>
                            </label>
                        </p>
                        <p>
                            <label>
                                <span><?php esc_html_e( 'Tentatives max', 'acdc-formation-saas' ); ?></span>
                                <input type="number" name="max_attempts" min="1" max="255" step="1" value="<?php echo esc_attr( (int) $quiz->max_attempts ); ?>" <?php echo esc_attr( $disabled ); ?> />
                            </label>
                        </p>
                    </div>

                    <!-- Onglet anti-triche -->
                    <div class="acdc-qz-tab-pane" data-tab-pane="antifraud">
                        <p class="description">
                            <?php esc_html_e( "Active selon le contexte. Ces options sont surtout utiles pour les évaluations des acquis en mode présentiel synchrone.", 'acdc-formation-saas' ); ?>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="antifraud_time_limit" value="1" <?php checked( (int) $quiz->antifraud_time_limit, 1 ); ?> <?php echo esc_attr( $disabled ); ?> />
                                <?php esc_html_e( 'Forcer le respect du temps limite par question', 'acdc-formation-saas' ); ?>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="antifraud_no_back" value="1" <?php checked( (int) $quiz->antifraud_no_back, 1 ); ?> <?php echo esc_attr( $disabled ); ?> />
                                <?php esc_html_e( "Interdire de revenir en arrière sur les questions précédentes", 'acdc-formation-saas' ); ?>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="antifraud_visibility_check" value="1" <?php checked( (int) $quiz->antifraud_visibility_check, 1 ); ?> <?php echo esc_attr( $disabled ); ?> />
                                <?php esc_html_e( "Détecter le changement d'onglet pendant la passation", 'acdc-formation-saas' ); ?>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="antifraud_fullscreen" value="1" <?php checked( (int) $quiz->antifraud_fullscreen, 1 ); ?> <?php echo esc_attr( $disabled ); ?> />
                                <?php esc_html_e( "Forcer le mode plein écran", 'acdc-formation-saas' ); ?>
                            </label>
                        </p>
                    </div>

                    <!-- Onglet RGPD -->
                    <div class="acdc-qz-tab-pane" data-tab-pane="rgpd">
                        <p>
                            <label>
                                <span><?php esc_html_e( "Conservation des données (jours)", 'acdc-formation-saas' ); ?></span>
                                <input type="number" name="data_retention_days" min="1" max="3650" step="1" value="<?php echo esc_attr( (int) $quiz->data_retention_days ); ?>" <?php echo esc_attr( $disabled ); ?> />
                            </label>
                            <span class="description">
                                <?php esc_html_e( "Par défaut 1095 jours (3 ans, durée Qualiopi). Au-delà, les réponses individuelles sont anonymisées (les agrégats restent).", 'acdc-formation-saas' ); ?>
                            </span>
                        </p>
                    </div>

                    <footer class="acdc-qz-modal-footer">
                        <button type="button" class="acdc-button acdc-button-soft" data-close><?php esc_html_e( 'Annuler', 'acdc-formation-saas' ); ?></button>
                        <?php if ( ! $is_locked ) : ?>
                            <button type="submit" class="acdc-button acdc-button-primary"><?php esc_html_e( 'Enregistrer', 'acdc-formation-saas' ); ?></button>
                        <?php endif; ?>
                    </footer>
                </form>
            </div>
        </div>
        <?php
    }

    /* ==================================================================== */
    /*  Modale "Objectifs pédagogiques"                                     */
    /* ==================================================================== */

    /**
     * Modale de gestion des objectifs pédagogiques d'un quiz.
     *
     * @param int   $quiz_id
     * @param array $objectives
     *
     * @return void
     */
    private function render_qz_modal_objectives( $quiz_id, $objectives ) {
        ?>
        <div id="acdc-qz-modal-objectives" class="acdc-qz-modal" hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Objectifs pédagogiques', 'acdc-formation-saas' ); ?>">
            <div class="acdc-qz-modal-overlay" data-close></div>
            <div class="acdc-qz-modal-dialog">
                <header class="acdc-qz-modal-header">
                    <h2><?php esc_html_e( 'Objectifs pédagogiques', 'acdc-formation-saas' ); ?></h2>
                    <button type="button" class="acdc-qz-modal-close" data-close aria-label="<?php esc_attr_e( 'Fermer', 'acdc-formation-saas' ); ?>">×</button>
                </header>

                <div class="acdc-qz-modal-body" data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>">
                    <p class="description">
                        <?php esc_html_e( "Les objectifs servent à calculer un score par compétence en fin de quiz. Rattachez ensuite chaque question à un objectif depuis le panneau de paramètres.", 'acdc-formation-saas' ); ?>
                    </p>

                    <ul class="acdc-qz-objectives-list" id="acdc-qz-objectives-list">
                        <?php if ( empty( $objectives ) ) : ?>
                            <li class="acdc-qz-objectives-empty">
                                <?php esc_html_e( "Aucun objectif pour ce quiz pour l'instant.", 'acdc-formation-saas' ); ?>
                            </li>
                        <?php else : ?>
                            <?php foreach ( $objectives as $obj ) : ?>
                                <li class="acdc-qz-objective-row" data-objective-id="<?php echo esc_attr( (int) $obj->id ); ?>">
                                    <div class="acdc-qz-objective-fields">
                                        <input type="text" class="acdc-qz-input acdc-qz-objective-label" value="<?php echo esc_attr( $obj->label ); ?>" maxlength="255" />
                                        <input type="number" class="acdc-qz-input acdc-qz-objective-pass" min="0" max="100" step="0.5" placeholder="<?php esc_attr_e( 'Seuil %', 'acdc-formation-saas' ); ?>" value="<?php echo esc_attr( null === $obj->pass_threshold ? '' : (string) $obj->pass_threshold ); ?>" />
                                    </div>
                                    <textarea class="acdc-qz-input acdc-qz-objective-description" rows="2" placeholder="<?php esc_attr_e( 'Description (optionnelle)', 'acdc-formation-saas' ); ?>"><?php echo esc_textarea( (string) $obj->description ); ?></textarea>
                                    <div class="acdc-qz-objective-actions">
                                        <button type="button" class="acdc-button acdc-button-soft acdc-qz-objective-save"><?php esc_html_e( 'Enregistrer', 'acdc-formation-saas' ); ?></button>
                                        <button type="button" class="acdc-button acdc-button-link acdc-qz-objective-delete"><?php esc_html_e( 'Supprimer', 'acdc-formation-saas' ); ?></button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>

                    <button type="button" class="acdc-button acdc-button-primary acdc-qz-objective-add">
                        + <?php esc_html_e( 'Ajouter un objectif', 'acdc-formation-saas' ); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /* ====================================================================
     *  3.21.03.1 — Modale d'envoi async + écran de suivi
     * ==================================================================== */

    /**
     * Modale "Envoyer par e-mail" : sélection des destinataires + paramètres d'envoi.
     */
    private function render_qz_modal_send_async( $quiz ) {
        $learners        = $this->get_qz_learners_for_formation( (int) $quiz->formation_id );
        $sessions        = $this->get_qz_formation_sessions( (int) $quiz->formation_id );
        $default_expires = $this->get_qz_default_expires_in_days( (string) $quiz->quiz_purpose );
        ?>
        <div id="acdc-qz-modal-send-async" class="acdc-qz-modal" hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Envoyer par e-mail', 'acdc-formation-saas' ); ?>">
            <div class="acdc-qz-modal-overlay" data-close></div>
            <div class="acdc-qz-modal-dialog acdc-qz-modal-dialog-large">
                <header class="acdc-qz-modal-header">
                    <h2><?php esc_html_e( 'Envoyer par e-mail', 'acdc-formation-saas' ); ?></h2>
                    <button type="button" class="acdc-qz-modal-close" data-close aria-label="<?php esc_attr_e( 'Fermer', 'acdc-formation-saas' ); ?>">×</button>
                </header>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acdc-qz-modal-body acdc-qz-send-async-form">
                    <input type="hidden" name="action" value="acdc_of_qz_send_async" />
                    <input type="hidden" name="quiz_id" value="<?php echo esc_attr( (int) $quiz->id ); ?>" />
                    <?php wp_nonce_field( 'acdc_of_qz_send_async', '_acdc_qz_nonce' ); ?>

                    <!-- Onglets sources de destinataires -->
                    <div class="acdc-qz-send-tabs">
                        <button type="button" class="acdc-qz-send-tab is-active" data-mode="formation"><?php esc_html_e( 'Apprenants de la formation', 'acdc-formation-saas' ); ?></button>
                        <button type="button" class="acdc-qz-send-tab" data-mode="session"><?php esc_html_e( 'Apprenants d\'une session', 'acdc-formation-saas' ); ?></button>
                        <button type="button" class="acdc-qz-send-tab" data-mode="custom"><?php esc_html_e( 'Saisie libre', 'acdc-formation-saas' ); ?></button>
                    </div>
                    <input type="hidden" name="recipients_mode" value="formation" />

                    <!-- Mode formation -->
                    <div class="acdc-qz-send-pane is-active" data-mode-pane="formation">
                        <?php if ( empty( $learners ) ) : ?>
                            <p class="description acdc-qz-help-warning">
                                <?php esc_html_e( "Aucun apprenant n'est rattaché à cette formation pour le moment.", 'acdc-formation-saas' ); ?>
                            </p>
                        <?php else : ?>
                            <p class="description">
                                <?php
                                /* translators: %d: number of learners */
                                printf( esc_html__( '%d apprenants disponibles. Cochez les destinataires.', 'acdc-formation-saas' ), count( $learners ) );
                                ?>
                                <a href="#" class="acdc-qz-select-all"><?php esc_html_e( 'Tout cocher', 'acdc-formation-saas' ); ?></a>
                                ·
                                <a href="#" class="acdc-qz-select-none"><?php esc_html_e( 'Tout décocher', 'acdc-formation-saas' ); ?></a>
                            </p>
                            <div class="acdc-qz-learners-list">
                                <?php foreach ( $learners as $l ) : ?>
                                    <label class="acdc-qz-learner-row">
                                        <input type="checkbox" name="learner_ids[]" value="<?php echo esc_attr( (int) $l->id ); ?>" />
                                        <span class="acdc-qz-learner-name"><?php echo esc_html( trim( $l->first_name . ' ' . $l->last_name ) ); ?></span>
                                        <span class="acdc-qz-learner-email"><?php echo esc_html( $l->email ); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Mode session -->
                    <div class="acdc-qz-send-pane" data-mode-pane="session" hidden>
                        <p>
                            <label>
                                <span><?php esc_html_e( 'Session :', 'acdc-formation-saas' ); ?></span>
                                <select name="formation_session_id" class="acdc-qz-session-selector">
                                    <option value="0"><?php esc_html_e( '— Sélectionner une session —', 'acdc-formation-saas' ); ?></option>
                                    <?php foreach ( $sessions as $s ) : ?>
                                        <option value="<?php echo esc_attr( (int) $s->id ); ?>">
                                            <?php
                                            $start = ! empty( $s->start_date ) ? mysql2date( 'j F Y', $s->start_date, true ) : '?';
                                            $end   = ! empty( $s->end_date )   ? mysql2date( 'j F Y', $s->end_date, true ) : '?';
                                            echo esc_html( "Session du {$start} au {$end}" );
                                            if ( ! empty( $s->location ) ) {
                                                echo esc_html( " — {$s->location}" );
                                            }
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </p>
                        <p class="description"><?php esc_html_e( 'Tous les apprenants rattachés à la session sélectionnée recevront le lien.', 'acdc-formation-saas' ); ?></p>
                    </div>

                    <!-- Mode custom -->
                    <div class="acdc-qz-send-pane" data-mode-pane="custom" hidden>
                        <p>
                            <label>
                                <span><?php esc_html_e( 'Adresses e-mail (une par ligne) :', 'acdc-formation-saas' ); ?></span>
                                <textarea name="custom_emails" rows="6" placeholder="jean.dupont@exemple.fr&#10;marie.martin@exemple.fr, Marie Martin"></textarea>
                            </label>
                        </p>
                        <p class="description">
                            <?php esc_html_e( 'Format accepté : "email" ou "email, Prénom Nom".', 'acdc-formation-saas' ); ?>
                        </p>
                    </div>

                    <!-- Paramètres communs -->
                    <hr class="acdc-qz-modal-separator" />

                    <p>
                        <label>
                            <span><?php esc_html_e( 'Délai avant expiration :', 'acdc-formation-saas' ); ?></span>
                            <input type="number" name="expires_in_days" value="<?php echo esc_attr( $default_expires ); ?>" min="1" max="90" />
                            <em><?php esc_html_e( 'jours', 'acdc-formation-saas' ); ?></em>
                        </label>
                    </p>

                    <p>
                        <label class="acdc-qz-checkbox-label">
                            <input type="checkbox" name="reminders_enabled" value="1" checked />
                            <span><?php esc_html_e( 'Envoyer un rappel automatique 24h avant expiration', 'acdc-formation-saas' ); ?></span>
                        </label>
                    </p>

                    <p>
                        <label>
                            <span><?php esc_html_e( 'Message personnalisé (optionnel) :', 'acdc-formation-saas' ); ?></span>
                            <textarea name="custom_message" rows="3" maxlength="500" placeholder="<?php esc_attr_e( 'Un mot pour vos apprenants…', 'acdc-formation-saas' ); ?>"></textarea>
                        </label>
                    </p>

                    <div class="acdc-qz-send-info-box">
                        <strong>ℹ️ <?php esc_html_e( 'Avant de cliquer Envoyer :', 'acdc-formation-saas' ); ?></strong>
                        <ul>
                            <li><?php esc_html_e( "Vérifiez que le contenu du quiz est prêt — vous pourrez encore modifier tant qu'aucun apprenant n'a cliqué.", 'acdc-formation-saas' ); ?></li>
                            <li><?php esc_html_e( "Au premier clic d'apprenant, le quiz sera automatiquement verrouillé pour traçabilité Qualiopi.", 'acdc-formation-saas' ); ?></li>
                        </ul>
                    </div>

                    <footer class="acdc-qz-modal-footer">
                        <button type="button" class="acdc-button acdc-button-soft" data-close><?php esc_html_e( 'Annuler', 'acdc-formation-saas' ); ?></button>
                        <button type="submit" class="acdc-button acdc-button-primary"><?php esc_html_e( '✉ Envoyer maintenant', 'acdc-formation-saas' ); ?></button>
                    </footer>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Panneau de suivi des sessions async d'un quiz.
     */
    private function render_qz_async_sessions_panel( $quiz, $sessions ) {
        ?>
        <div class="acdc-qz-async-sessions-panel">
            <h2 class="acdc-qz-section-title"><?php esc_html_e( '📨 Suivi des envois', 'acdc-formation-saas' ); ?></h2>
            <?php foreach ( $sessions as $session ) :
                $stats = $this->get_qz_async_session_stats( (int) $session->id );
                $participants = $this->get_qz_async_participants_for_session( (int) $session->id );
                ?>
                <div class="acdc-qz-async-session-card">
                    <header class="acdc-qz-async-session-header">
                        <div>
                            <strong>
                                <?php
                                /* translators: %s: send date */
                                printf( esc_html__( 'Envoi du %s', 'acdc-formation-saas' ),
                                    esc_html( mysql2date( 'j F Y à H\hi', $session->sent_at, true ) ) );
                                ?>
                            </strong>
                            <span class="acdc-qz-async-session-summary">
                                <?php
                                /* translators: 1: completed, 2: in-progress, 3: invited, 4: total */
                                printf(
                                    esc_html__( '%1$d terminés · %2$d en cours · %3$d non commencés · %4$d total', 'acdc-formation-saas' ),
                                    $stats['completed'],
                                    $stats['in_progress'] + $stats['opened'],
                                    $stats['invited'],
                                    $stats['total']
                                );
                                ?>
                            </span>
                        </div>
                        <div class="acdc-qz-async-session-meta">
                            <?php
                            if ( ! empty( $session->expires_at ) ) {
                                printf(
                                    /* translators: %s: expiration date */
                                    esc_html__( 'Expire le %s', 'acdc-formation-saas' ),
                                    esc_html( mysql2date( 'j F Y', $session->expires_at, true ) )
                                );
                            }
                            ?>
                        </div>
                    </header>

                    <table class="acdc-qz-async-participants-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Apprenant', 'acdc-formation-saas' ); ?></th>
                                <th><?php esc_html_e( 'E-mail', 'acdc-formation-saas' ); ?></th>
                                <th><?php esc_html_e( 'Statut', 'acdc-formation-saas' ); ?></th>
                                <th><?php esc_html_e( 'Score', 'acdc-formation-saas' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'acdc-formation-saas' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $participants as $p ) :
                                $status_label = array(
                                    'invited'     => __( '🕒 Envoyé', 'acdc-formation-saas' ),
                                    'opened'      => __( '👁 Lu', 'acdc-formation-saas' ),
                                    'in_progress' => __( '🟡 En cours', 'acdc-formation-saas' ),
                                    'completed'   => __( '✅ Terminé', 'acdc-formation-saas' ),
                                    'expired'     => __( '⏰ Expiré', 'acdc-formation-saas' ),
                                    'cancelled'   => __( '❌ Annulé', 'acdc-formation-saas' ),
                                )[ $p->status ] ?? $p->status;
                                ?>
                                <tr>
                                    <td><?php echo esc_html( $p->full_name ?: '—' ); ?></td>
                                    <td><?php echo esc_html( $p->email ); ?></td>
                                    <td><?php echo esc_html( $status_label ); ?></td>
                                    <td>
                                        <?php
                                        if ( null !== $p->total_score_percentage ) {
                                            echo esc_html( number_format_i18n( $p->total_score_percentage, 1 ) . ' %' );
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </td>
                                    <td class="acdc-qz-async-actions">
                                        <?php if ( in_array( $p->status, array( 'invited', 'opened', 'in_progress', 'expired' ), true ) ) : ?>
                                            <?php
                                            // hotfix57 — Bouton Relancer : indiquer si déjà relancé récemment
                                            $reminded_recently = false;
                                            if ( ! empty( $p->last_reminder_sent_at ) ) {
                                                $reminded_recently = ( strtotime( (string) $p->last_reminder_sent_at ) > ( time() - 86400 ) );
                                            }
                                            $remind_title = $reminded_recently
                                                ? 'Relance envoyée il y a moins de 24h'
                                                : 'Envoyer une relance par e-mail';
                                            ?>
                                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline"
                                                  <?php if ( ! $reminded_recently ) : ?>onsubmit="return confirm('Envoyer une relance par e-mail à <?php echo esc_js( $p->full_name ); ?> ?');"<?php endif; ?>>
                                                <input type="hidden" name="action" value="acdc_of_qz_remind_participant" />
                                                <input type="hidden" name="participant_id" value="<?php echo esc_attr( (int) $p->id ); ?>" />
                                                <?php wp_nonce_field( 'acdc_of_qz_remind_participant', '_acdc_qz_nonce' ); ?>
                                                <button type="submit" class="acdc-qz-action-link<?php echo $reminded_recently ? ' acdc-qz-action-muted' : ''; ?>"
                                                        title="<?php echo esc_attr( $remind_title ); ?>"
                                                        <?php if ( $reminded_recently ) : ?>disabled style="opacity:.45;cursor:not-allowed"<?php endif; ?>>
                                                    🔔
                                                </button>
                                            </form>
                                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                                                <input type="hidden" name="action" value="acdc_of_qz_resend_participant" />
                                                <input type="hidden" name="participant_id" value="<?php echo esc_attr( (int) $p->id ); ?>" />
                                                <?php wp_nonce_field( 'acdc_of_qz_resend_participant', '_acdc_qz_nonce' ); ?>
                                                <button type="submit" class="acdc-qz-action-link" title="<?php esc_attr_e( 'Réenvoyer un nouveau lien', 'acdc-formation-saas' ); ?>">🔄</button>
                                            </form>
                                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('Prolonger de combien de jours ? Validez avec le défaut ou annulez.');">
                                                <input type="hidden" name="action" value="acdc_of_qz_extend_participant" />
                                                <input type="hidden" name="participant_id" value="<?php echo esc_attr( (int) $p->id ); ?>" />
                                                <input type="hidden" name="extra_days" value="3" />
                                                <?php wp_nonce_field( 'acdc_of_qz_extend_participant', '_acdc_qz_nonce' ); ?>
                                                <button type="submit" class="acdc-qz-action-link" title="<?php esc_attr_e( 'Prolonger de 3 jours', 'acdc-formation-saas' ); ?>">🔓</button>
                                            </form>
                                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('Annuler définitivement cette invitation ?');">
                                                <input type="hidden" name="action" value="acdc_of_qz_cancel_participant" />
                                                <input type="hidden" name="participant_id" value="<?php echo esc_attr( (int) $p->id ); ?>" />
                                                <?php wp_nonce_field( 'acdc_of_qz_cancel_participant', '_acdc_qz_nonce' ); ?>
                                                <button type="submit" class="acdc-qz-action-link acdc-qz-action-danger" title="<?php esc_attr_e( 'Annuler cette invitation', 'acdc-formation-saas' ); ?>">❌</button>
                                            </form>
                                        <?php elseif ( 'completed' === $p->status ) : ?>
                                            <span class="acdc-qz-async-completed-meta">
                                                <?php
                                                if ( ! empty( $p->completed_at ) ) {
                                                    echo esc_html( mysql2date( 'j F · H\hi', $p->completed_at, true ) );
                                                }
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Renvoie le nombre de jours par défaut pour l'expiration selon la finalité.
     */
    private function get_qz_default_expires_in_days( $purpose ) {
        if ( self::ACDC_OF_QZ_PURPOSE_POSITIONING === $purpose ) {
            return 7;
        }
        if ( self::ACDC_OF_QZ_PURPOSE_ASSESSMENT === $purpose ) {
            return 14;
        }
        return 30;
    }

    /* ==================================================================== */
    /*  Modale Import IA — 3.21.30                                          */
    /* ==================================================================== */

    /**
     * Rend la modale d'import IA (Mode A — ajout de questions à un quiz existant).
     *
     * @param int $quiz_id
     *
     * @return void
     */
    private function render_qz_modal_import_ai( $quiz_id ) {
        /* ACDC 3.25.291 — La fenêtre lisait $quiz->title alors qu'elle ne reçoit
           qu'un identifiant : le nom du quiz cible s'affichait vide, sur l'écran
           même où l'on s'apprête à écraser ses questions. */
        $quiz = $this->get_qz_quiz( (int) $quiz_id );
        ?>
        <div id="acdc-qz-modal-import-ai" class="acdc-qz-modal" hidden aria-modal="true" role="dialog" aria-labelledby="acdc-qz-import-ai-title">
            <div class="acdc-qz-modal-overlay" data-close></div>
            <div class="acdc-qz-modal-dialog acdc-qz-modal-dialog-large">

                <!-- EN-TÊTE -->
                <div class="acdc-qz-modal-header">
                    <h2 id="acdc-qz-import-ai-title"><?php esc_html_e( 'Importer un quiz généré par IA', 'acdc-formation-saas' ); ?></h2>
                    <button type="button" class="acdc-qz-modal-close" data-close aria-label="<?php esc_attr_e( 'Fermer', 'acdc-formation-saas' ); ?>">×</button>
                </div>

                <!-- ÉTAPE 1 : UPLOAD -->
                <div class="acdc-qz-modal-body acdc-qz-import-step" id="acdc-qz-import-step-1">

                    <div class="acdc-qz-import-context-info">
                        <span class="acdc-qz-import-context-label"><?php esc_html_e( 'Quiz cible :', 'acdc-formation-saas' ); ?></span>
                        <span class="acdc-qz-import-context-value" id="acdc-qz-import-quiz-name"></span>
                    </div>

                    <label for="acdc-qz-import-file">
                        <span class="acdc-required"><?php esc_html_e( 'Fichier JSON', 'acdc-formation-saas' ); ?></span>
                        <div class="acdc-qz-import-dropzone" id="acdc-qz-import-dropzone">
                            <div class="acdc-qz-import-dropzone-inner">
                                <span class="acdc-qz-import-dropzone-icon">📂</span>
                                <span class="acdc-qz-import-dropzone-label"><?php esc_html_e( 'Glissez votre fichier ici ou cliquez pour sélectionner', 'acdc-formation-saas' ); ?></span>
                                <span class="acdc-qz-import-dropzone-hint"><?php esc_html_e( 'Fichier .json uniquement — généré via le prompt officiel ACDC', 'acdc-formation-saas' ); ?></span>
                                <span class="acdc-qz-import-dropzone-filename" id="acdc-qz-import-filename" hidden></span>
                            </div>
                            <input type="file" id="acdc-qz-import-file" accept=".json,application/json" style="position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;" />
                        </div>
                    </label>

                    <label for="acdc-qz-import-timer">
                        <span><?php esc_html_e( 'Timer appliqué à toutes les questions (secondes)', 'acdc-formation-saas' ); ?></span>
                        <input type="number" id="acdc-qz-import-timer" value="20" min="10" max="300" step="5" />
                    </label>

                    <div class="acdc-qz-import-error" id="acdc-qz-import-error-1" hidden></div>
                </div>

                <!-- ÉTAPE 2 : APERÇU -->
                <div class="acdc-qz-modal-body acdc-qz-import-step" id="acdc-qz-import-step-2" hidden>
                    <div class="acdc-qz-import-summary" id="acdc-qz-import-summary"></div>
                    <div class="acdc-qz-import-warnings" id="acdc-qz-import-warnings" hidden></div>
                    <div class="acdc-qz-import-preview-list" id="acdc-qz-import-preview-list"></div>
                </div>

                <!-- PIED -->
                <div class="acdc-qz-modal-footer">
                    <!-- Footer étape 1 -->
                    <div id="acdc-qz-import-footer-1">
                        <button type="button" class="acdc-button acdc-button-soft" data-close>
                            <?php esc_html_e( 'Annuler', 'acdc-formation-saas' ); ?>
                        </button>
                        <button type="button" class="acdc-button acdc-button-primary" id="acdc-qz-import-analyze-btn" disabled>
                            <span class="acdc-qz-import-btn-label"><?php esc_html_e( 'Analyser le fichier', 'acdc-formation-saas' ); ?></span>
                            <span class="acdc-qz-import-btn-spinner" hidden>⏳</span>
                        </button>
                    </div>
                    <!-- Footer étape 2 -->
                    <div id="acdc-qz-import-footer-2" hidden>
                        <button type="button" class="acdc-button acdc-button-soft" id="acdc-qz-import-back-btn">
                            ← <?php esc_html_e( 'Corriger le fichier', 'acdc-formation-saas' ); ?>
                        </button>
                        <button type="button" class="acdc-button acdc-button-primary" id="acdc-qz-import-confirm-btn">
                            <span class="acdc-qz-import-btn-label">✅ <?php esc_html_e( 'Confirmer l\'import', 'acdc-formation-saas' ); ?></span>
                            <span class="acdc-qz-import-btn-spinner" hidden>⏳</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
        <script type="text/javascript">
        (function($) {
            if (typeof window._acdcQzImportModalInit !== 'undefined') { return; }
            window._acdcQzImportModalInit = true;

            var quizId   = <?php echo (int) $quiz_id; ?>;
            var ajaxUrl  = (typeof acdcQzEditor !== 'undefined') ? acdcQzEditor.ajaxUrl : '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
            var nonce    = '<?php echo esc_js( wp_create_nonce( 'acdc_of_qz_import' ) ); ?>';
            var jsonB64  = '';

            /* --- Ouverture : remplir le nom du quiz cible --- */
            $(document).on('click', '.acdc-qz-import-ai-btn', function() {
                var name = <?php echo wp_json_encode( $quiz && isset( $quiz->title ) ? (string) $quiz->title : '' ); ?>;
                $('#acdc-qz-import-quiz-name').text(name);
                resetImportModal();
            });

            /* --- Sélection de fichier --- */
            $(document).on('change', '#acdc-qz-import-file', function() {
                var f = this.files && this.files[0];
                if (f) {
                    $('#acdc-qz-import-filename').text(f.name).removeAttr('hidden');
                    $('#acdc-qz-import-analyze-btn').prop('disabled', false);
                } else {
                    $('#acdc-qz-import-filename').attr('hidden', 'hidden');
                    $('#acdc-qz-import-analyze-btn').prop('disabled', true);
                }
            });

            /* --- Drag & drop cosmétique --- */
            $(document).on('dragover', '#acdc-qz-import-dropzone', function(e) {
                e.preventDefault();
                $(this).addClass('is-dragover');
            });
            $(document).on('dragleave drop', '#acdc-qz-import-dropzone', function() {
                $(this).removeClass('is-dragover');
            });

            /* --- Analyser --- */
            $(document).on('click', '#acdc-qz-import-analyze-btn', function() {
                var fileInput = document.getElementById('acdc-qz-import-file');
                if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                    showError1('<?php echo esc_js( __( 'Sélectionnez un fichier JSON.', 'acdc-formation-saas' ) ); ?>');
                    return;
                }
                var timer = parseInt($('#acdc-qz-import-timer').val(), 10) || 20;
                setLoading('#acdc-qz-import-analyze-btn', true);
                hideError1();

                var fd = new FormData();
                fd.append('action',       'acdc_of_qz_import_preview');
                fd.append('nonce',        nonce);
                fd.append('quiz_id',      quizId);
                fd.append('timer_global', timer);
                fd.append('json_file',    fileInput.files[0]);

                $.ajax({
                    url:         ajaxUrl,
                    type:        'POST',
                    data:        fd,
                    processData: false,
                    contentType: false,
                    success: function(resp) {
                        setLoading('#acdc-qz-import-analyze-btn', false);
                        if (!resp.success) {
                            showError1(resp.data && resp.data.message ? resp.data.message : '<?php echo esc_js( __( 'Erreur.', 'acdc-formation-saas' ) ); ?>');
                            return;
                        }
                        jsonB64 = resp.data.json_b64;
                        renderPreview(resp.data);
                        showStep(2);
                    },
                    error: function() {
                        setLoading('#acdc-qz-import-analyze-btn', false);
                        showError1('<?php echo esc_js( __( 'Erreur réseau.', 'acdc-formation-saas' ) ); ?>');
                    }
                });
            });

            /* --- Retour étape 1 --- */
            $(document).on('click', '#acdc-qz-import-back-btn', function() {
                showStep(1);
            });

            /* --- Confirmer l'import --- */
            $(document).on('click', '#acdc-qz-import-confirm-btn', function() {
                var timer = parseInt($('#acdc-qz-import-timer').val(), 10) || 20;
                setLoading('#acdc-qz-import-confirm-btn', true);

                $.post(ajaxUrl, {
                    action:       'acdc_of_qz_import_confirm',
                    nonce:        nonce,
                    quiz_id:      quizId,
                    timer_global: timer,
                    json_b64:     jsonB64
                }, function(resp) {
                    setLoading('#acdc-qz-import-confirm-btn', false);
                    if (!resp.success) {
                        alert(resp.data && resp.data.message ? resp.data.message : '<?php echo esc_js( __( 'Erreur.', 'acdc-formation-saas' ) ); ?>');
                        return;
                    }
                    if (resp.data.imported === 0) {
                        var errMsg = '<?php echo esc_js( __( '0 question importée. Problème lors de l\'insertion en base.', 'acdc-formation-saas' ) ); ?>';
                        if (resp.data.warnings && resp.data.warnings.length) {
                            errMsg += '\n\n' + resp.data.warnings.join('\n');
                        }
                        alert(errMsg);
                        return;
                    }
                    /* Fermer la modale et recharger la page pour afficher les nouvelles questions */
                    $('#acdc-qz-modal-import-ai').attr('hidden', 'hidden');
                    var msg = resp.data.imported + ' <?php echo esc_js( __( 'question(s) importée(s) avec succès.', 'acdc-formation-saas' ) ); ?>';
                    if (resp.data.skipped > 0) {
                        msg += ' (' + resp.data.skipped + ' <?php echo esc_js( __( 'ignorée(s)', 'acdc-formation-saas' ) ); ?>)';
                    }
                    /* Notification ACDC si disponible, sinon alert */
                    if (typeof acdcShowNotice === 'function') {
                        acdcShowNotice(msg, 'success');
                    }
                    setTimeout(function() { window.location.reload(); }, 800);
                });
            });

            /* --- Helpers --- */
            function resetImportModal() {
                showStep(1);
                $('#acdc-qz-import-file').val('');
                $('#acdc-qz-import-filename').attr('hidden', 'hidden');
                $('#acdc-qz-import-analyze-btn').prop('disabled', true);
                hideError1();
                jsonB64 = '';
            }

            function showStep(n) {
                if (n === 1) {
                    $('#acdc-qz-import-step-1').removeAttr('hidden');
                    $('#acdc-qz-import-step-2').attr('hidden', 'hidden');
                    $('#acdc-qz-import-footer-1').removeAttr('hidden');
                    $('#acdc-qz-import-footer-2').attr('hidden', 'hidden');
                    /* Sécurité : Confirmer toujours désactivé quand on revient en step 1 */
                    $('#acdc-qz-import-confirm-btn').prop('disabled', true);
                } else {
                    $('#acdc-qz-import-step-1').attr('hidden', 'hidden');
                    $('#acdc-qz-import-step-2').removeAttr('hidden');
                    $('#acdc-qz-import-footer-1').attr('hidden', 'hidden');
                    $('#acdc-qz-import-footer-2').removeAttr('hidden');
                    /* Confirmer activé seulement si jsonB64 est bien défini */
                    $('#acdc-qz-import-confirm-btn').prop('disabled', !jsonB64);
                }
            }

            function showError1(msg) {
                $('#acdc-qz-import-error-1').text(msg).removeAttr('hidden');
            }
            function hideError1() {
                $('#acdc-qz-import-error-1').attr('hidden', 'hidden').text('');
            }

            function setLoading(selector, loading) {
                var $btn = $(selector);
                $btn.find('.acdc-qz-import-btn-label').toggle(!loading);
                $btn.find('.acdc-qz-import-btn-spinner').toggle(loading);
                $btn.prop('disabled', loading);
            }

            function renderPreview(data) {
                var questions = data.questions || [];
                var warnings  = data.warnings  || [];
                var timer     = data.timer      || 20;

                /* Bandeau récapitulatif */
                var valid   = 0;
                var warned  = 0;
                var skipped = data.skipped || 0;
                questions.forEach(function(q) {
                    if (q.skip)           { skipped++; }
                    else if (q.warnings && q.warnings.length) { warned++; valid++; }
                    else                  { valid++; }
                });

                var summary = '<div class="acdc-qz-import-summary-row">'
                    + '<span class="acdc-qz-import-pill acdc-qz-import-pill-ok">✅ ' + valid + ' <?php echo esc_js( __( 'valide(s)', 'acdc-formation-saas' ) ); ?></span>'
                    + (warned  > 0 ? '<span class="acdc-qz-import-pill acdc-qz-import-pill-warn">⚠ ' + warned  + ' <?php echo esc_js( __( 'avertissement(s)', 'acdc-formation-saas' ) ); ?></span>' : '')
                    + (skipped > 0 ? '<span class="acdc-qz-import-pill acdc-qz-import-pill-err">✗ '  + skipped + ' <?php echo esc_js( __( 'ignorée(s)', 'acdc-formation-saas' ) ); ?></span>' : '')
                    + '<span class="acdc-qz-import-pill acdc-qz-import-pill-timer">⏱ ' + timer + ' s</span>'
                    + '</div>';
                $('#acdc-qz-import-summary').html(summary);

                /* Avertissements globaux */
                if (warnings.length) {
                    var whtml = '<ul>';
                    warnings.forEach(function(w) { whtml += '<li>' + escHtml(w) + '</li>'; });
                    whtml += '</ul>';
                    $('#acdc-qz-import-warnings').html(whtml).removeAttr('hidden');
                } else {
                    $('#acdc-qz-import-warnings').attr('hidden', 'hidden');
                }

                /* Labels de types */
                var typeLabels = {
                    qcm_single:   'QCM',
                    qcm_multiple: 'Multi',
                    true_false:   'V/F',
                    open_text:    'Ouvert',
                    poll:         'Sondage',
                    puzzle:       'Puzzle'
                };

                /* Liste des questions */
                var html = '';
                questions.forEach(function(q) {
                    var rowCls = 'acdc-qz-import-qrow';
                    if (q.skip) {
                        rowCls += ' acdc-qz-import-qrow-error';
                    } else if (q.warnings && q.warnings.length) {
                        rowCls += ' acdc-qz-import-qrow-warn';
                    }
                    var typeLabel = typeLabels[q.type] || q.type;
                    html += '<div class="' + rowCls + '">';
                    html += '<div class="acdc-qz-import-qrow-head">';
                    html += '<span class="acdc-qz-import-qnum">' + q.num + '</span>';
                    html += '<span class="acdc-qz-import-qtype acdc-qz-import-qtype-' + escAttr(q.type) + '">' + escHtml(typeLabel) + '</span>';
                    html += '<span class="acdc-qz-import-qtitle">' + escHtml(q.title) + '</span>';
                    html += '</div>';
                    if (q.description) {
                        html += '<div class="acdc-qz-import-qdesc">' + escHtml(q.description) + '</div>';
                    }
                    /* Réponses */
                    if (q.answers && q.answers.length) {
                        html += '<ul class="acdc-qz-import-qanswers">';
                        q.answers.forEach(function(a) {
                            var icon = a.is_correct ? '✅' : (q.type === 'poll' ? '—' : '❌');
                            html += '<li class="' + (a.is_correct ? 'is-correct' : '') + '">';
                            html += '<span class="acdc-qz-import-answer-icon">' + icon + '</span>';
                            html += '<span>' + escHtml(a.text) + '</span>';
                            if (a.feedback_text) {
                                html += '<em class="acdc-qz-import-feedback"> — ' + escHtml(a.feedback_text) + '</em>';
                            }
                            html += '</li>';
                        });
                        html += '</ul>';
                    }
                    if (q.expected_answer) {
                        html += '<div class="acdc-qz-import-expected"><strong><?php echo esc_js( __( 'Réponse de référence :', 'acdc-formation-saas' ) ); ?></strong> ' + escHtml(q.expected_answer) + '</div>';
                    }
                    /* Avertissements de la question */
                    if (q.warnings && q.warnings.length) {
                        html += '<ul class="acdc-qz-import-qwarnings">';
                        q.warnings.forEach(function(w) { html += '<li>⚠ ' + escHtml(w) + '</li>'; });
                        html += '</ul>';
                    }
                    html += '</div>';
                });
                $('#acdc-qz-import-preview-list').html(html);
            }

            function escHtml(s) {
                return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }
            function escAttr(s) {
                return String(s).replace(/[^a-z0-9_-]/gi,'');
            }

        }(jQuery));
        </script>
        <?php
    }
}
