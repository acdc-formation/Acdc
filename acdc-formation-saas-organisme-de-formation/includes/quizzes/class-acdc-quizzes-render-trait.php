<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * ACDC Formation SAAS — Module Quizzes (Render trait — partie 1/2 : liste et dispatch).
 *
 * 3.21.01.1 — Refonte complète : on n'override plus aucune méthode du kernel.
 * Le rendu est intégralement déclenché par le filtre `acdc_portal_render_unknown_tab`
 * qui est consommé par le trait Actions, qui appelle `render_qz_extranet_screen()`
 * de ce trait.
 *
 * Le wrapper portal-shell complet (sidebar + topbar + container) est déjà rendu
 * par le kernel — on ne fait que remplir la zone <main>.
 *
 * Aiguillage : selon le query var `view`, soit on rend la liste des quiz
 * (par finalité), soit on rend l'éditeur.
 *
 * Le rendu détaillé de l'éditeur (3 zones, modales, types de questions) est
 * délégué au trait éditeur (class-acdc-quizzes-render-editor-trait.php).
 *
 * @package ACDC_OF_SAAS
 * @since   3.21.00
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Quizzes_Render_Trait {

    use ACDC_Quizzes_Render_Editor_Trait;

    /* ==================================================================== */
    /*  Point d'entrée extranet                                             */
    /* ==================================================================== */

    /**
     * Rend le contenu principal d'un écran du moteur Quizzes.
     *
     * Appelé par le hook `acdc_portal_render_unknown_tab` du kernel via
     * le trait Actions. Le wrapper portal-shell, la sidebar et la topbar
     * sont déjà rendus à ce stade — on doit juste remplir la <main>.
     *
     * @param string $purpose
     *
     * @return void
     */
    public function render_qz_extranet_screen( $purpose ) {
        $view    = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : self::ACDC_OF_QZ_VIEW_LIST;
        $quiz_id = isset( $_GET['quiz_id'] ) ? absint( wp_unslash( $_GET['quiz_id'] ) ) : 0;

        /* ACDC 3.21.04.1-hotfix1+2 — Quand on arrive via un tab Résultats (transverse
           ou par finalité), on impose la vue results par défaut. */
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
        /* ACDC 3.25.168 — Cette liste omettait l'évaluation diagnostique : son onglet
           « Résultats — Diagnostiques » n'imposait donc pas la vue résultats et retombait
           sur la liste des quiz. Liste unique désormais, dans qz_results_tabs(). */
        if ( in_array( $current_tab, $this->qz_results_tabs(), true ) ) {
            $view = self::ACDC_OF_QZ_VIEW_RESULTS;
        }
        ?>
        <div class="acdc-qz-app">
            <?php $this->render_qz_breadcrumb( $purpose, $view, $quiz_id ); ?>
            <?php $this->render_qz_inline_notice_from_query(); ?>

            <?php
            if ( self::ACDC_OF_QZ_VIEW_EDIT === $view && $quiz_id > 0 ) {
                $this->render_qz_editor_screen( $quiz_id, $purpose );
            } elseif ( self::ACDC_OF_QZ_VIEW_RESULTS === $view ) {
                /* ACDC 3.21.04.1 — Écrans de résultats détaillés. */
                $this->render_qz_results_screen( $purpose );
            } else {
                $this->render_qz_list_screen( $purpose );
            }
            ?>
        </div>
        <?php
    }

    /**
     * Fil d'Ariane simple : finalité > vue courante.
     *
     * @param string $purpose
     * @param string $view
     * @param int    $quiz_id
     *
     * @return void
     */
    private function render_qz_breadcrumb( $purpose, $view, $quiz_id ) {
        $purpose_labels = $this->get_quiz_purpose_labels();
        $label = isset( $purpose_labels[ $purpose ] ) ? $purpose_labels[ $purpose ] : __( 'Quiz', 'acdc-formation-saas' );

        $list_url = $this->qz_admin_url( $purpose );
        ?>
        <nav class="acdc-qz-breadcrumb" aria-label="<?php esc_attr_e( "Fil d'Ariane", 'acdc-formation-saas' ); ?>">
            <a href="<?php echo esc_url( $list_url ); ?>"><?php echo esc_html( $label ); ?></a>
            <?php if ( self::ACDC_OF_QZ_VIEW_EDIT === $view && $quiz_id > 0 ) :
                $quiz = $this->get_qz_quiz( $quiz_id );
                $title = $quiz ? $quiz->title : __( 'Édition', 'acdc-formation-saas' );
                ?>
                <span class="sep">›</span>
                <span class="current"><?php echo esc_html( $title ); ?></span>
            <?php endif; ?>
        </nav>
        <?php
    }

    /**
     * Affiche un avis (notice) si présent dans les query args.
     *
     * @return void
     */
    private function render_qz_inline_notice_from_query() {
        if ( empty( $_GET['acdc_qz_notice'] ) ) {
            return;
        }
        $notice = sanitize_text_field( rawurldecode( wp_unslash( $_GET['acdc_qz_notice'] ) ) );
        $type   = isset( $_GET['acdc_qz_notice_type'] ) ? sanitize_key( wp_unslash( $_GET['acdc_qz_notice_type'] ) ) : 'info';

        $allowed = array( 'info', 'success', 'warning', 'error' );
        if ( ! in_array( $type, $allowed, true ) ) {
            $type = 'info';
        }

        if ( 'feature_pending' === $notice ) {
            $notice = __( 'Cette fonctionnalité sera disponible dans une prochaine sous-version 3.21.', 'acdc-formation-saas' );
            $type   = 'info';
        }

        if ( '' === trim( $notice ) ) {
            return;
        }
        ?>
        <div class="acdc-qz-notice acdc-qz-notice-<?php echo esc_attr( $type ); ?>">
            <p><?php echo esc_html( $notice ); ?></p>
        </div>
        <?php
    }

    /* ==================================================================== */
    /*  Liste des quiz (par finalité)                                       */
    /* ==================================================================== */

    /**
     * Rend l'écran liste pour une finalité.
     *
     * @param string $purpose
     *
     * @return void
     */
    public function render_qz_list_screen( $purpose ) {
        $purpose_labels = $this->get_quiz_purpose_labels();
        $title = isset( $purpose_labels[ $purpose ] ) ? $purpose_labels[ $purpose ] : __( 'Quiz', 'acdc-formation-saas' );

        $formation_filter = isset( $_GET['filter_formation_id'] ) ? absint( wp_unslash( $_GET['filter_formation_id'] ) ) : 0;
        $status_filter    = isset( $_GET['filter_status'] ) ? sanitize_key( wp_unslash( $_GET['filter_status'] ) ) : '';
        /* ACDC 3.25.330 — RANGER LES QUIZ PAR THÉMATIQUE, SANS EMPILER LES ONGLETS.
           La recette anticipe le jour où il y aura un quiz par formation et par
           thématique, et propose deux niveaux d'onglets imbriqués : thématique,
           puis formation, puis les quiz. Le besoin est juste ; la forme coûte
           cher — deux niveaux d'onglets, c'est trois clics et un retour arrière
           pour atteindre un quiz, et une barre d'onglets qui déborde dès la
           sixième thématique.
           Cet écran a déjà une barre de filtres, et elle porte déjà la
           formation. Il lui manquait le cran AU-DESSUS. Choisir une thématique
           restreint la liste des formations à celles de cette thématique : on
           obtient la même descente, en une seule ligne, sans jamais quitter la
           page ni perdre les autres filtres. */
        $thematique_filter = isset( $_GET['filter_thematique'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_thematique'] ) ) : '';
        // 3.21.02 — Filtre versions : par défaut, on n'affiche que les versions
        // courantes (is_current = 1) pour ne pas noyer la liste sous les anciennes
        // versions verrouillées. L'utilisateur peut basculer sur "all" pour audit.
        $versions_filter = isset( $_GET['filter_versions'] ) ? sanitize_key( wp_unslash( $_GET['filter_versions'] ) ) : 'current';
        if ( ! in_array( $versions_filter, array( 'current', 'all' ), true ) ) {
            $versions_filter = 'current';
        }

        $args = array(
            'purpose'      => $purpose,
            'formation_id' => $formation_filter,
            'status'       => '' !== $status_filter ? $status_filter : '',
            'limit'        => 500,
        );
        $quizzes = $this->get_qz_quizzes( $args );

        // Filtre côté PHP pour ne garder que les versions courantes si demandé.
        if ( 'current' === $versions_filter && ! empty( $quizzes ) ) {
            $quizzes = array_values( array_filter( $quizzes, function( $q ) {
                // is_current = 1 OU pas encore versionné (replaces_quiz_id = 0 et replaced_by_quiz_id = 0)
                if ( (int) $q->is_current === 1 ) {
                    return true;
                }
                $no_chain = ( (int) $q->replaces_quiz_id === 0 && (int) $q->replaced_by_quiz_id === 0 );
                return $no_chain;
            } ) );
        }

        $formations = $this->get_qz_available_formations();

        /* La liste des thématiques proposées est celle des formations
           RÉELLEMENT utilisables ici : offrir une thématique qui ne mènerait à
           aucune formation serait offrir un cul-de-sac. */
        $thematiques_qz = array();
        foreach ( (array) $formations as $__f ) {
            $__code = isset( $__f->thematique ) ? trim( (string) $__f->thematique ) : '';
            if ( '' !== $__code ) { $thematiques_qz[ $__code ] = $__code; }
        }
        if ( ! empty( $thematiques_qz ) && method_exists( $this, 'acdc_thematiques_par_code' ) ) {
            $thematiques_qz = $this->acdc_thematiques_par_code( array_keys( $thematiques_qz ) );
        }
        if ( '' !== $thematique_filter && ! isset( $thematiques_qz[ $thematique_filter ] ) ) {
            $thematique_filter = '';
        }

        /* Le filtre thématique agit sur les DEUX listes : celle des formations
           proposées au choix, et celle des quiz affichés. N'en faire qu'une des
           deux donnerait un écran qui se contredit. */
        if ( '' !== $thematique_filter ) {
            $ids_thematique = array();
            foreach ( (array) $formations as $__f ) {
                if ( trim( (string) ( $__f->thematique ?? '' ) ) === $thematique_filter ) { $ids_thematique[] = (int) $__f->id; }
            }
            $formations = array_values( array_filter( (array) $formations, function ( $f ) use ( $thematique_filter ) {
                return trim( (string) ( $f->thematique ?? '' ) ) === $thematique_filter;
            } ) );
            if ( 0 === $formation_filter ) {
                $quizzes = array_values( array_filter( (array) $quizzes, function ( $q ) use ( $ids_thematique ) {
                    return in_array( (int) ( $q->formation_id ?? 0 ), $ids_thematique, true );
                } ) );
            }
        }
        ?>
        <header class="acdc-qz-list-header">
            <h1 class="acdc-qz-list-title"><?php echo esc_html( $title ); ?></h1>
            <div class="acdc-qz-list-actions">
                <button type="button" class="acdc-button acdc-button-primary acdc-qz-create-btn"
                        data-purpose="<?php echo esc_attr( $purpose ); ?>"
                        data-target="acdc-qz-modal-create">
                    <?php esc_html_e( '+ Créer', 'acdc-formation-saas' ); ?>
                </button>
                <button type="button" class="acdc-button acdc-button-soft acdc-qz-duplicate-from-btn"
                        data-purpose="<?php echo esc_attr( $purpose ); ?>"
                        data-target="acdc-qz-modal-duplicate-from">
                    <?php esc_html_e( "Dupliquer depuis…", 'acdc-formation-saas' ); ?>
                </button>
                <button type="button" class="acdc-button acdc-button-soft acdc-qz-import-ai-list-btn"
                        data-purpose="<?php echo esc_attr( $purpose ); ?>"
                        data-target="acdc-qz-modal-import-ai-list">
                    ⬆ <?php esc_html_e( 'Importer un quiz IA', 'acdc-formation-saas' ); ?>
                </button>
                <?php /* 3.21.04.1-hotfix1 — Le lien "Résultats" est désormais un onglet sidebar dédié, plus un bouton ici. */ ?>
            </div>
        </header>

        <form method="get" class="acdc-qz-filters">
            <input type="hidden" name="tab" value="<?php echo esc_attr( $this->qz_tab_for_purpose( $purpose ) ); ?>" />
            <?php
            // Conserver le `page` du portal extranet (slug WP de la page).
            $page_id = (int) get_option( 'acdc_of_extranet_dashboard_page_id', 0 );
            if ( $page_id > 0 ) {
                $page_obj = get_post( $page_id );
                if ( $page_obj instanceof WP_Post ) {
                    echo '<input type="hidden" name="page_id" value="' . esc_attr( $page_id ) . '" />';
                }
            }
            ?>
            <label>
                <span><?php esc_html_e( 'Thématique :', 'acdc-formation-saas' ); ?></span>
                <select name="filter_thematique" onchange="this.form.filter_formation_id.value='0';this.form.submit();">
                    <option value=""><?php esc_html_e( 'Toutes', 'acdc-formation-saas' ); ?></option>
                    <?php foreach ( $thematiques_qz as $__code => $__libelle ) : ?>
                        <option value="<?php echo esc_attr( $__code ); ?>" <?php selected( $thematique_filter, (string) $__code ); ?>>
                            <?php echo esc_html( $__libelle ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?php esc_html_e( 'Formation :', 'acdc-formation-saas' ); ?></span>
                <select name="filter_formation_id">
                    <option value="0"><?php esc_html_e( 'Toutes', 'acdc-formation-saas' ); ?></option>
                    <?php foreach ( $formations as $f ) : ?>
                        <option value="<?php echo esc_attr( (int) $f->id ); ?>" <?php selected( $formation_filter, (int) $f->id ); ?>>
                            <?php echo esc_html( $this->format_qz_formation_label( $f ) ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?php esc_html_e( 'Statut :', 'acdc-formation-saas' ); ?></span>
                <select name="filter_status">
                    <option value=""><?php esc_html_e( 'Tous', 'acdc-formation-saas' ); ?></option>
                    <?php foreach ( $this->get_quiz_status_labels() as $key => $label ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status_filter, $key ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span><?php esc_html_e( 'Versions :', 'acdc-formation-saas' ); ?></span>
                <select name="filter_versions">
                    <option value="current" <?php selected( $versions_filter, 'current' ); ?>>
                        <?php esc_html_e( 'Versions courantes', 'acdc-formation-saas' ); ?>
                    </option>
                    <option value="all" <?php selected( $versions_filter, 'all' ); ?>>
                        <?php esc_html_e( 'Toutes les versions', 'acdc-formation-saas' ); ?>
                    </option>
                </select>
            </label>
            <button type="submit" class="acdc-button acdc-button-soft"><?php esc_html_e( 'Filtrer', 'acdc-formation-saas' ); ?></button>
        </form>

        <?php if ( empty( $quizzes ) ) : ?>
            <div class="acdc-panel acdc-qz-empty-state">
                <p>
                    <?php
                    if ( $formation_filter > 0 ) {
                        esc_html_e( 'Aucun quiz pour cette formation pour le moment.', 'acdc-formation-saas' );
                    } else {
                        esc_html_e( 'Aucun quiz pour le moment. Cliquez sur « + Créer » pour démarrer.', 'acdc-formation-saas' );
                    }
                    ?>
                </p>
            </div>
        <?php else : ?>
            <div class="acdc-qz-cards">
                <?php foreach ( $quizzes as $quiz ) : ?>
                    <?php $this->render_qz_card( $quiz ); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php
        $this->render_qz_modal_create( $purpose );
        $this->render_qz_modal_duplicate_from( $purpose );
        // 3.21.03.1 — Modale d'envoi async depuis la liste (positionnement et évaluation)
        if ( in_array( $purpose, array( self::ACDC_OF_QZ_PURPOSE_POSITIONING, self::ACDC_OF_QZ_PURPOSE_DIAGNOSTIC, self::ACDC_OF_QZ_PURPOSE_ASSESSMENT ), true ) ) {
            $this->render_qz_modal_send_list( $purpose );
        }
        /* ACDC 3.25.165 — La modale de lancement en salle vaut pour toute finalité
           susceptible d'être passée en synchrone : diagnostique et acquis le sont par
           défaut. Sans cela, leur bouton « Lancer en live » n'ouvrait rien. */
        if ( in_array( $purpose, array( self::ACDC_OF_QZ_PURPOSE_LIVE, self::ACDC_OF_QZ_PURPOSE_DIAGNOSTIC, self::ACDC_OF_QZ_PURPOSE_ASSESSMENT ), true ) ) {
            $this->render_qz_modal_live_launch();
        }
        // 3.21.31 — Modale import IA Mode B (création complète)
        $this->render_qz_modal_import_ai_list( $purpose );
        ?>
        <?php
    }

    /**
     * Rend une carte de quiz dans la liste.
     *
     * @param object $quiz
     *
     * @return void
     */
    private function render_qz_card( $quiz ) {
        $quiz_id   = (int) $quiz->id;
        $purpose   = (string) $quiz->quiz_purpose;
        $is_locked = (int) $quiz->is_locked === 1;
        $version_number = (int) $quiz->version_number;
        $is_current     = (int) $quiz->is_current === 1;
        // Une "ancienne version" est définie comme : appartenant à une chaîne ET non courante.
        $is_old_version = ! $is_current
                          && ( (int) $quiz->replaces_quiz_id > 0 || (int) $quiz->replaced_by_quiz_id > 0 );

        $edit_url    = $this->qz_admin_url( $purpose, array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => $quiz_id ) );
        $formation_title = $this->get_qz_formation_title( (int) $quiz->formation_id );
        $count_questions = count( $this->get_qz_questions_for_quiz( $quiz_id ) );
        $count_answers   = $this->count_qz_player_answers_for_quiz( $quiz_id );

        $status_labels = $this->get_quiz_status_labels();
        $status_label  = isset( $status_labels[ $quiz->status ] ) ? $status_labels[ $quiz->status ] : (string) $quiz->status;

        $purpose_labels = $this->get_quiz_purpose_labels();
        $purpose_label  = isset( $purpose_labels[ $purpose ] ) ? $purpose_labels[ $purpose ] : $purpose;

        $delivery_labels = $this->get_quiz_delivery_mode_labels();
        $delivery_label  = isset( $delivery_labels[ $quiz->delivery_mode ] ) ? $delivery_labels[ $quiz->delivery_mode ] : (string) $quiz->delivery_mode;

        $card_classes = array(
            'acdc-qz-card',
            'acdc-qz-card-' . esc_attr( $quiz->status ),
        );
        if ( $is_locked ) {
            $card_classes[] = 'acdc-qz-card-locked';
        }
        if ( $is_old_version ) {
            $card_classes[] = 'acdc-qz-card-old-version';
        }
        ?>
        <article class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>" data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>">
            <header class="acdc-qz-card-header">
                <h3 class="acdc-qz-card-title">
                    <a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $quiz->title ); ?></a>
                    <?php if ( $version_number > 1 || $is_old_version ) : ?>
                        <span class="acdc-qz-version-badge" title="<?php esc_attr_e( 'Numéro de version', 'acdc-formation-saas' ); ?>">v<?php echo esc_html( (string) $version_number ); ?></span>
                    <?php endif; ?>
                </h3>
                <div class="acdc-qz-card-menu-wrap">
                    <button type="button" class="acdc-qz-card-menu-toggle" aria-label="<?php esc_attr_e( 'Menu', 'acdc-formation-saas' ); ?>">⋯</button>
                    <ul class="acdc-qz-card-menu" hidden>
                        <li>
                            <a href="<?php echo esc_url( $edit_url ); ?>">
                                <?php
                                if ( $is_locked ) {
                                    esc_html_e( 'Consulter (lecture seule)', 'acdc-formation-saas' );
                                } else {
                                    esc_html_e( 'Modifier', 'acdc-formation-saas' );
                                }
                                ?>
                            </a>
                        </li>
                        <li>
                            <button type="button" class="acdc-qz-action-duplicate-direct"
                                    data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>"
                                    data-nonce="<?php echo esc_attr( wp_create_nonce( 'acdc_of_qz_duplicate_quiz_direct' ) ); ?>">
                                <?php esc_html_e( 'Dupliquer', 'acdc-formation-saas' ); ?>
                            </button>
                        </li>
                        <?php if ( $is_locked || (int) $quiz->is_current === 1 ) : ?>
                            <li>
                                <button type="button" class="acdc-qz-action-new-version"
                                        data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>"
                                        data-nonce="<?php echo esc_attr( wp_create_nonce( 'acdc_of_qz_create_new_version' ) ); ?>">
                                    <?php esc_html_e( 'Créer une nouvelle version', 'acdc-formation-saas' ); ?>
                                </button>
                            </li>
                        <?php endif; ?>
                        <?php
                        /* ACDC 3.25.157 — Cette entrée était un simple placeholder
                           « Disponible 3.21.10 », désactivé sans condition : l'écran de
                           résultats existe pourtant et fonctionne. On la branche, filtrée
                           sur le quiz de la ligne. */
                        $qz_results_url = $this->qz_admin_url( $purpose, array( 'view' => 'results', 'qz_quiz' => $quiz_id ) );
                        ?>
                        <li>
                            <a href="<?php echo esc_url( $qz_results_url ); ?>">
                                <?php esc_html_e( 'Voir les résultats', 'acdc-formation-saas' ); ?>
                            </a>
                        </li>
                        <?php if ( self::ACDC_OF_QZ_DELIVERY_LIVE_SYNC === $quiz->delivery_mode ) : ?>
                            <?php if ( self::ACDC_OF_QZ_STATUS_ACTIVE === $quiz->status ) : ?>
                                <li>
                                    <button type="button"
                                        onclick="acdcQzLiveLaunchOpen(this,event)"
                                        data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>"
                                        data-quiz-title="<?php echo esc_attr( $quiz->title ); ?>"
                                        data-launch-nonce="<?php echo esc_attr( wp_create_nonce( 'acdc_of_qz_live_launch_sessions_' . $quiz_id ) ); ?>"
                                        data-launch-url="<?php echo esc_attr( admin_url( 'admin-post.php' ) ); ?>"
                                        data-launch-action-nonce="<?php echo esc_attr( wp_create_nonce( 'acdc_of_qz_launch_live_' . $quiz_id ) ); ?>"
                                        data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>">
                                        <?php esc_html_e( '▶ Lancer en live', 'acdc-formation-saas' ); ?>
                                    </button>
                                </li>
                            <?php else : ?>
                                <?php /* ACDC 3.25.159 — L'entrée désactivée ne portait qu'un title :
                                         au clic il ne se passait rien, aucune requête, aucun message, et
                                         l'utilisateur pouvait croire à une panne. La raison est désormais
                                         écrite à l'écran, et le clic l'explique. */ ?>
                                <li class="acdc-qz-menu-disabled">
                                    <span title="<?php esc_attr_e( 'Activez le quiz pour pouvoir le lancer en live', 'acdc-formation-saas' ); ?>"
                                          onclick="alert('Ce quiz est en brouillon. Activez-le depuis ses paramètres pour pouvoir le lancer en session live.');"
                                          style="cursor:help;">
                                        <?php esc_html_e( '▶ Lancer en live', 'acdc-formation-saas' ); ?>
                                        <small style="display:block;font-size:11px;opacity:.75;"><?php esc_html_e( 'Activez le quiz au préalable', 'acdc-formation-saas' ); ?></small>
                                    </span>
                                </li>
                            <?php endif; ?>
                        <?php else : ?>
                            <li>
                                <button type="button"
                                    class="acdc-qz-list-send-btn"
                                    data-target="acdc-qz-modal-send-list"
                                    data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>"
                                    data-quiz-title="<?php echo esc_attr( $quiz->title ); ?>"
                                    data-quiz-formation="<?php echo esc_attr( (int) $quiz->formation_id ); ?>">
                                    <?php esc_html_e( 'Envoyer par e-mail', 'acdc-formation-saas' ); ?>
                                </button>
                            </li>
                        <?php endif; ?>
                        <li>
                            <button type="button" class="acdc-qz-action-archive"
                                    data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>"
                                    data-nonce="<?php echo esc_attr( wp_create_nonce( 'acdc_of_qz_archive_quiz' ) ); ?>">
                                <?php esc_html_e( 'Archiver', 'acdc-formation-saas' ); ?>
                            </button>
                        </li>
                        <li>
                            <button type="button" class="acdc-qz-action-delete"
                                    data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>"
                                    data-nonce="<?php echo esc_attr( wp_create_nonce( 'acdc_of_qz_delete_quiz' ) ); ?>">
                                <?php esc_html_e( 'Supprimer', 'acdc-formation-saas' ); ?>
                            </button>
                        </li>
                    </ul>
                </div>
            </header>

            <ul class="acdc-qz-card-meta">
                <li><?php echo esc_html( $purpose_label ); ?></li>
                <li><?php echo esc_html( $delivery_label ); ?></li>
                <?php if ( '' !== $formation_title ) : ?>
                    <li><strong><?php echo esc_html( $formation_title ); ?></strong></li>
                <?php endif; ?>
                <li>
                    <?php
                    /* translators: %d: number of questions */
                    printf( esc_html( _n( '%d question', '%d questions', $count_questions, 'acdc-formation-saas' ) ), (int) $count_questions );
                    ?>
                </li>
                <?php if ( $count_answers > 0 ) : ?>
                    <li>
                        <?php
                        /* translators: %d: number of player answers */
                        printf( esc_html( _n( '%d réponse', '%d réponses', $count_answers, 'acdc-formation-saas' ) ), (int) $count_answers );
                        ?>
                    </li>
                <?php endif; ?>
                <li>
                    <span class="acdc-qz-status acdc-qz-status-<?php echo esc_attr( $quiz->status ); ?>">
                        <?php echo esc_html( $status_label ); ?>
                    </span>
                </li>
                <?php if ( $is_locked ) : ?>
                    <li class="acdc-qz-locked-flag">🔒 <?php esc_html_e( 'Verrouillé', 'acdc-formation-saas' ); ?></li>
                <?php endif; ?>
            </ul>

            <?php if ( ! empty( $quiz->description ) ) : ?>
                <p class="acdc-qz-card-description">
                    <?php echo esc_html( wp_strip_all_tags( wp_trim_words( $quiz->description, 25 ) ) ); ?>
                </p>
            <?php endif; ?>

            <footer class="acdc-qz-card-footer">
                <a href="<?php echo esc_url( $edit_url ); ?>" class="acdc-button acdc-button-soft">
                    <?php esc_html_e( 'Ouvrir', 'acdc-formation-saas' ); ?>
                </a>
            </footer>
        </article>
        <?php
    }

    /* ==================================================================== */
    /*  Modale "Créer"                                                       */
    /* ==================================================================== */

    /**
     * @param string $purpose
     * @return void
     */
    private function render_qz_modal_create( $purpose ) {
        $formations = $this->get_qz_available_formations();
        $purpose_labels = $this->get_quiz_purpose_labels();
        $purpose_label  = isset( $purpose_labels[ $purpose ] ) ? $purpose_labels[ $purpose ] : $purpose;
        ?>
        <div id="acdc-qz-modal-create" class="acdc-qz-modal" hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Créer un quiz', 'acdc-formation-saas' ); ?>">
            <div class="acdc-qz-modal-overlay" data-close></div>
            <div class="acdc-qz-modal-dialog">
                <header class="acdc-qz-modal-header">
                    <h2><?php
                        /* translators: %s: purpose label (e.g., "Test de positionnement") */
                        printf( esc_html__( 'Créer — %s', 'acdc-formation-saas' ), esc_html( $purpose_label ) );
                    ?></h2>
                    <button type="button" class="acdc-qz-modal-close" data-close aria-label="<?php esc_attr_e( 'Fermer', 'acdc-formation-saas' ); ?>">×</button>
                </header>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acdc-qz-modal-body">
                    <input type="hidden" name="action" value="acdc_of_qz_save_quiz" />
                    <input type="hidden" name="quiz_id" value="0" />
                    <input type="hidden" name="quiz_purpose" value="<?php echo esc_attr( $purpose ); ?>" />
                    <?php wp_nonce_field( 'acdc_of_qz_save_quiz', '_acdc_qz_nonce' ); ?>

                    <p>
                        <label>
                            <?php /* ACDC 3.25.157 — L'astérisque est déjà ajouté par .acdc-required::after (CSS) : le répéter ici affichait « Titre * * ». */ ?><span class="acdc-required"><?php esc_html_e( 'Titre', 'acdc-formation-saas' ); ?></span>
                            <input type="text" name="title" required maxlength="200" autocomplete="off" />
                        </label>
                    </p>

                    <p>
                        <label>
                            <span class="acdc-required"><?php esc_html_e( 'Formation rattachée', 'acdc-formation-saas' ); ?></span>
                            <select name="formation_id" required>
                                <option value="0">— <?php esc_html_e( 'Sélectionner', 'acdc-formation-saas' ); ?> —</option>
                                <?php foreach ( $formations as $f ) : ?>
                                    <option value="<?php echo esc_attr( (int) $f->id ); ?>"><?php echo esc_html( $this->format_qz_formation_label( $f ) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </p>

                    <?php /* ACDC 3.25.165 — Le choix du mode de passation est offert au
                             diagnostique comme aux acquis : tous deux se passent en salle par
                             défaut, mais doivent pouvoir être envoyés à distance pour un
                             rattrapage. */ ?>
                    <?php if ( in_array( $purpose, array( self::ACDC_OF_QZ_PURPOSE_DIAGNOSTIC, self::ACDC_OF_QZ_PURPOSE_ASSESSMENT ), true ) ) : ?>
                        <p>
                            <label>
                                <span><?php esc_html_e( "Mode de passation par défaut", 'acdc-formation-saas' ); ?></span>
                                <select name="delivery_mode">
                                    <option value="<?php echo esc_attr( self::ACDC_OF_QZ_DELIVERY_ASYNC_TOKEN ); ?>" selected>
                                        <?php esc_html_e( 'E-mail asynchrone (par défaut)', 'acdc-formation-saas' ); ?>
                                    </option>
                                    <option value="<?php echo esc_attr( self::ACDC_OF_QZ_DELIVERY_LIVE_SYNC ); ?>">
                                        <?php esc_html_e( 'Présentiel synchrone (anti-triche)', 'acdc-formation-saas' ); ?>
                                    </option>
                                </select>
                            </label>
                        </p>
                    <?php elseif ( self::ACDC_OF_QZ_PURPOSE_POSITIONING === $purpose ) : ?>
                        <input type="hidden" name="delivery_mode" value="<?php echo esc_attr( self::ACDC_OF_QZ_DELIVERY_ASYNC_TOKEN ); ?>" />
                        <p class="description">
                            <?php esc_html_e( "Le test de positionnement sera envoyé par e-mail aux apprenants avant la formation.", 'acdc-formation-saas' ); ?>
                        </p>
                    <?php else : /* live */ ?>
                        <input type="hidden" name="delivery_mode" value="<?php echo esc_attr( self::ACDC_OF_QZ_DELIVERY_LIVE_SYNC ); ?>" />
                        <p class="description">
                            <?php esc_html_e( "Le quiz sera animé en direct par le formateur (PIN, lobby, leaderboard).", 'acdc-formation-saas' ); ?>
                        </p>
                    <?php endif; ?>

                    <footer class="acdc-qz-modal-footer">
                        <button type="button" class="acdc-button acdc-button-soft" data-close><?php esc_html_e( 'Annuler', 'acdc-formation-saas' ); ?></button>
                        <button type="submit" class="acdc-button acdc-button-primary"><?php esc_html_e( 'Créer et éditer', 'acdc-formation-saas' ); ?></button>
                    </footer>
                </form>
            </div>
        </div>
        <?php
    }

    /* ==================================================================== */
    /*  Modale "Dupliquer depuis…"                                          */
    /* ==================================================================== */

    /**
     * @param string $purpose
     * @return void
     */
    private function render_qz_modal_send_list( $purpose ) {
        $formations = $this->get_qz_available_formations();
        $groups     = $this->get_qz_available_groups();
        $nonce      = wp_create_nonce( 'acdc_of_qz_send_async' );
        ?>
        <div id="acdc-qz-modal-send-list" class="acdc-qz-modal" hidden role="dialog" aria-modal="true"
             aria-label="<?php esc_attr_e( 'Envoyer par e-mail', 'acdc-formation-saas' ); ?>">
            <div class="acdc-qz-modal-overlay" data-close></div>
            <div class="acdc-qz-modal-dialog">
                <div class="acdc-qz-modal-header">
                    <div>
                        <h2>✉ Envoyer par e-mail</h2>
                        <p id="acdc-qz-send-list-subtitle" style="margin:4px 0 0;font-size:13px;color:#4b5d76;font-style:italic;font-weight:400"></p>
                    </div>
                    <button type="button" class="acdc-qz-modal-close" data-close aria-label="<?php esc_attr_e( 'Fermer', 'acdc-formation-saas' ); ?>">✕</button>
                </div>
                <form id="acdc-qz-send-list-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action"       value="acdc_of_qz_send_async" />
                    <input type="hidden" name="_acdc_qz_nonce" value="<?php echo esc_attr( $nonce ); ?>" />
                    <input type="hidden" name="quiz_id"      id="acdc-qz-send-list-quiz-id"     value="" />
                    <input type="hidden" name="purpose"      value="<?php echo esc_attr( $purpose ); ?>" />

                    <div class="acdc-qz-modal-field">
                        <label class="acdc-qz-field-label">Envoyer à</label>
                        <div class="acdc-qz-send-list-choices">
                            <label class="acdc-qz-send-list-choice <?php echo empty( $formations ) ? 'is-disabled' : ''; ?>">
                                <input type="radio" name="send_mode" value="session" <?php echo empty( $formations ) ? 'disabled' : ''; ?>>
                                <span>📅 Une séance de formation</span>
                            </label>
                            <label class="acdc-qz-send-list-choice <?php echo empty( $groups ) ? 'is-disabled' : ''; ?>">
                                <input type="radio" name="send_mode" value="group" <?php echo empty( $groups ) ? 'disabled' : ''; ?>>
                                <span>👥 Un groupe d’apprenants</span>
                            </label>
                            <label class="acdc-qz-send-list-choice">
                                <input type="radio" name="send_mode" value="learner" checked>
                                <span>👤 Un apprenant spécifique</span>
                            </label>
                        </div>
                    </div>

                    <?php if ( ! empty( $formations ) ) : ?>
                    <!-- Bloc séance — hotfix43 : chargement auto via quiz_id, sans select formation intermédiaire -->
                    <div id="acdc-qz-send-mode-session" class="acdc-qz-send-mode-block" style="display:none">
                        <div class="acdc-qz-modal-field">
                            <label class="acdc-qz-field-label" for="acdc-qz-send-session-sel">
                                Séance (jour de formation)
                            </label>
                            <select id="acdc-qz-send-session-sel" name="session_id" class="acdc-qz-field-select">
                                <option value="">— Sélectionner une séance —</option>
                            </select>
                            <p id="acdc-qz-send-session-msg" style="margin:4px 0 0;font-size:12px;color:#4b5d76"></p>
                        </div>
                    </div>

                    <!-- Bloc groupe d’apprenants — hotfix45 : vrai select acdc_of_groups -->
                    <div id="acdc-qz-send-mode-formation" class="acdc-qz-send-mode-block" style="display:none">
                        <div class="acdc-qz-modal-field">
                            <label class="acdc-qz-field-label" for="acdc-qz-send-group-sel">
                                Groupe d’apprenants
                            </label>
                            <select id="acdc-qz-send-group-sel" name="group_id" class="acdc-qz-field-select">
                                <option value="">— Sélectionner un groupe —</option>
                                <?php foreach ( $groups as $g ) : ?>
                                    <option value="<?php echo esc_attr( $g->id ); ?>">
                                        <?php
                                        echo esc_html( $g->name );
                                        if ( ! empty( $g->formation_title ) ) {
                                            /* ACDC 3.25.277 — Modalité comprise : deux groupes
                                               de la même formation, l'un en présentiel l'autre
                                               en distanciel, portaient le même libellé. */
                                            echo ' — ' . esc_html( $this->acdc_formation_cell( $g ) );
                                        }
                                        echo ' (' . (int) $g->learner_count . ' apprenant' . ( (int) $g->learner_count > 1 ? 's' : '' ) . ')';
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p style="margin:4px 0 0;font-size:12px;color:#4b5d76">
                                Tous les apprenants du groupe sélectionné recevront le quiz.
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Bloc apprenant individuel -->
                    <!-- Bloc apprenant spécifique — hotfix48 : autocomplete sur acdc_of_learners -->
                    <div id="acdc-qz-send-mode-learner" class="acdc-qz-send-mode-block">
                        <!-- Champs cachés remplis à la sélection -->
                        <input type="hidden" name="learner_id"         id="acdc-qz-learner-id"    value="" />
                        <input type="hidden" name="learner_email"      id="acdc-qz-learner-email" value="" />
                        <input type="hidden" name="learner_first_name" id="acdc-qz-learner-fname" value="" />

                        <div class="acdc-qz-modal-field" style="position:relative">
                            <label class="acdc-qz-field-label" for="acdc-qz-learner-search">
                                Apprenant (nom, prénom ou e-mail)
                            </label>
                            <input type="text" id="acdc-qz-learner-search" autocomplete="off"
                                   class="acdc-qz-field-input"
                                   placeholder="Tapez au moins 2 caractères…" />
                            <ul id="acdc-qz-learner-results" style="
                                display:none;position:absolute;top:100%;left:0;right:0;
                                background:#fff;border:1.5px solid #d6a353;border-radius:0 0 8px 8px;
                                max-height:200px;overflow-y:auto;margin:0;padding:0;
                                list-style:none;z-index:9999;box-shadow:0 4px 16px rgba(0,0,0,.12)
                            "></ul>
                        </div>

                        <!-- Afficheage de la sélection courante -->
                        <div id="acdc-qz-learner-selected" style="display:none;
                             padding:10px 14px;background:#fef9f0;border:1.5px solid #d6a353;
                             border-radius:8px;font-size:13px;color:#0f2c52;
                             display:flex;align-items:center;justify-content:space-between;gap:8px">
                            <span id="acdc-qz-learner-selected-label"></span>
                            <button type="button" id="acdc-qz-learner-clear"
                                    style="background:none;border:none;cursor:pointer;
                                           font-size:16px;color:#4b5d76;padding:0 4px"
                                    aria-label="Désélectionner">✕</button>
                        </div>
                    </div>

                    <!-- Options communes -->
                    <div class="acdc-qz-modal-field">
                        <label class="acdc-qz-field-label" for="acdc-qz-send-deadline">Date limite de réponse</label>
                        <input type="date" id="acdc-qz-send-deadline" name="deadline_date"
                               value="<?php echo esc_attr( date( 'Y-m-d', strtotime( '+7 days' ) ) ); ?>"
                               min="<?php echo esc_attr( date( 'Y-m-d', strtotime( '+1 day' ) ) ); ?>"
                               class="acdc-qz-field-input" />
                    </div>

                    <div class="acdc-qz-modal-actions">
                        <button type="button" class="acdc-button acdc-button-soft" data-close>Annuler</button>
                        <button type="submit" class="acdc-button acdc-button-primary" id="acdc-qz-send-list-submit">
                            ✉ Envoyer
                        </button>
                    </div>
                </form>
            </div><!-- /.acdc-qz-modal-dialog -->
        </div><!-- /#acdc-qz-modal-send-list -->

        <script>
        (function(){
            function updateSendMode(mode) {
                // hotfix45 : radio value='group' → div id='acdc-qz-send-mode-formation'
                var divMode = (mode === 'group') ? 'formation' : mode;
                ['session','formation','learner'].forEach(function(m){
                    var el = document.getElementById('acdc-qz-send-mode-' + m);
                    if (el) el.style.display = (m === divMode) ? '' : 'none';
                });
                // hotfix43 — Auto-charger les séances quand mode séance activé
                if (mode === 'session') { loadSeancesForCurrentQuiz(); }
            }

            // Ouvrir la modale — délégation sur document pour les boutons dynamiques
            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.acdc-qz-list-send-btn');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                var modal = document.getElementById('acdc-qz-modal-send-list');
                if (!modal) return;
                document.getElementById('acdc-qz-send-list-quiz-id').value = btn.dataset.quizId || '';
                var sub = document.getElementById('acdc-qz-send-list-subtitle');
                if (sub) sub.textContent = btn.dataset.quizTitle || '';
                // Reset mode par défaut
                var radios = modal.querySelectorAll('[name="send_mode"]');
                radios.forEach(function(r){ if(r.value==='learner') r.checked=true; });
                updateSendMode('learner');
                // Ouvrir — même mécanique que bindModals jQuery existant
                modal.removeAttribute('hidden');
                document.querySelectorAll('.acdc-qz-card-menu:not([hidden])').forEach(function(m){ m.setAttribute('hidden',''); });
            });

            // Changement de mode envoi
            document.addEventListener('change', function(e) {
                if (e.target.name === 'send_mode') { updateSendMode(e.target.value); }
            });

            // hotfix43 — Chargement auto des séances via quiz_id (sans select formation intermédiaire).
            function loadSeancesForCurrentQuiz() {
                var quizId     = document.getElementById('acdc-qz-send-list-quiz-id');
                var sessionSel = document.getElementById('acdc-qz-send-session-sel');
                var sessionMsg = document.getElementById('acdc-qz-send-session-msg');
                if (!sessionSel || !quizId || !quizId.value) return;
                sessionSel.innerHTML = '<option value="">⏳ Chargement…</option>';
                if (sessionMsg) sessionMsg.textContent = '';
                var body = new URLSearchParams();
                body.append('action',   'acdc_of_qz_get_formation_sessions');
                body.append('quiz_id',  quizId.value);
                body.append('_wpnonce','<?php echo esc_js( wp_create_nonce( 'acdc_of_qz_get_formation_sessions' ) ); ?>');
                fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>', {
                    method:'POST', body:body, credentials:'same-origin'
                }).then(function(r){return r.json();}).then(function(j){
                    if (j.success && j.data && j.data.length) {
                        sessionSel.innerHTML = '<option value="">— Sélectionner une séance —</option>';
                        j.data.forEach(function(s){
                            var opt = document.createElement('option');
                            opt.value = s.id;
                            opt.textContent = s.label;
                            sessionSel.appendChild(opt);
                        });
                    } else {
                        sessionSel.innerHTML = '<option value="">Aucune séance disponible</option>';
                        if (sessionMsg) sessionMsg.textContent = 'Ce quiz n’a pas encore de séance planifiée.';
                    }
                }).catch(function(){ sessionSel.innerHTML = '<option value="">Erreur</option>'; });
            }

            // Empêcher la fermeture quand on clique DANS la boite de dialogue
            document.addEventListener('click', function(e) {
                var box = e.target.closest('.acdc-qz-modal-box');
                if (box && box.closest('#acdc-qz-modal-send-list')) {
                    e.stopPropagation();
                }
            }, true);

            // ============================================================
            // hotfix48 — Autocomplete "Un apprenant spécifique"
            // Recherche dans acdc_of_learners (nom, prénom, email)
            // ============================================================
            var learnerSearchTimer = null;
            var learnerNonce = '<?php echo esc_js( wp_create_nonce( 'acdc_of_qz_search_learners' ) ); ?>';
            var learnerAjaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';

            var searchInput   = document.getElementById('acdc-qz-learner-search');
            var resultsList   = document.getElementById('acdc-qz-learner-results');
            var selectedBox   = document.getElementById('acdc-qz-learner-selected');
            var selectedLabel = document.getElementById('acdc-qz-learner-selected-label');
            var clearBtn      = document.getElementById('acdc-qz-learner-clear');
            var hiddenId      = document.getElementById('acdc-qz-learner-id');
            var hiddenEmail   = document.getElementById('acdc-qz-learner-email');
            var hiddenFname   = document.getElementById('acdc-qz-learner-fname');

            function learnerResetSelection() {
                if (hiddenId)    hiddenId.value    = '';
                if (hiddenEmail) hiddenEmail.value = '';
                if (hiddenFname) hiddenFname.value = '';
                if (selectedBox)   { selectedBox.style.display = 'none'; }
                if (searchInput)   { searchInput.style.display = ''; searchInput.value = ''; }
                if (resultsList)   { resultsList.style.display = 'none'; resultsList.innerHTML = ''; }
            }

            function learnerSelect(id, label, email, fname) {
                if (hiddenId)    hiddenId.value    = id;
                if (hiddenEmail) hiddenEmail.value = email;
                if (hiddenFname) hiddenFname.value = fname;
                if (selectedLabel) selectedLabel.textContent = label + ' — ' + email;
                if (selectedBox)   { selectedBox.style.display = 'flex'; }
                if (searchInput)   { searchInput.style.display = 'none'; }
                if (resultsList)   { resultsList.style.display = 'none'; resultsList.innerHTML = ''; }
            }

            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(learnerSearchTimer);
                    var q = this.value.trim();
                    if (q.length < 2) {
                        if (resultsList) { resultsList.style.display = 'none'; resultsList.innerHTML = ''; }
                        return;
                    }
                    learnerSearchTimer = setTimeout(function() {
                        if (resultsList) { resultsList.innerHTML = '<li style="padding:8px 14px;color:#4b5d76;font-size:13px">⏳ Recherche…</li>'; resultsList.style.display = ''; }
                        var body = new URLSearchParams();
                        body.append('action',   'acdc_of_qz_search_learners');
                        body.append('q',        q);
                        body.append('_wpnonce', learnerNonce);
                        fetch(learnerAjaxUrl, { method:'POST', body:body, credentials:'same-origin' })
                            .then(function(r){ return r.json(); })
                            .then(function(j) {
                                if (!resultsList) return;
                                if (!j.success || !j.data || !j.data.length) {
                                    resultsList.innerHTML = '<li style="padding:8px 14px;color:#4b5d76;font-size:13px">Aucun résultat</li>';
                                    resultsList.style.display = '';
                                    return;
                                }
                                resultsList.innerHTML = '';
                                j.data.forEach(function(item) {
                                    var li = document.createElement('li');
                                    li.style.cssText = 'padding:9px 14px;cursor:pointer;font-size:13px;color:#0f2c52;border-bottom:1px solid #f0e6dc';
                                    li.innerHTML = '<strong>' + item.label + '</strong> <span style="color:#4b5d76;font-size:12px">— ' + item.email + '</span>';
                                    li.addEventListener('mousedown', function(e) {
                                        e.preventDefault();
                                        var parts = item.label.split(' ');
                                        learnerSelect(item.id, item.label, item.email, parts[0] || '');
                                    });
                                    li.addEventListener('mouseover', function(){ this.style.background='#fef9f0'; });
                                    li.addEventListener('mouseout',  function(){ this.style.background=''; });
                                    resultsList.appendChild(li);
                                });
                                resultsList.style.display = '';
                            })
                            .catch(function() {
                                if (resultsList) resultsList.innerHTML = '<li style="padding:8px 14px;color:#e06d6d;font-size:13px">Erreur de recherche</li>';
                            });
                    }, 280);
                });

                // Fermer la liste si clic ailleurs
                document.addEventListener('click', function(e) {
                    if (resultsList && !resultsList.contains(e.target) && e.target !== searchInput) {
                        resultsList.style.display = 'none';
                    }
                });
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', function() { learnerResetSelection(); });
            }

            // Réinitialiser l'autocomplete quand la modale s'ouvre
            document.addEventListener('click', function(e) {
                if (e.target.closest('.acdc-qz-list-send-btn')) { learnerResetSelection(); }
            });

        })();
        </script>

        <?php
    }

    /**
     * ACDC 3.21.06 — Modal sélection de séance avant lancement live.
     */
    private function render_qz_modal_live_launch() {
        ?>
        <!-- ── Modal lancement live ──────────────────────────────────────────────── -->
        <div id="acdc-qz-live-launch-modal" class="acdc-qz-modal" hidden role="dialog" aria-modal="true"
             aria-label="<?php esc_attr_e( 'Lancer en live', 'acdc-formation-saas' ); ?>">
            <div class="acdc-qz-modal-overlay" onclick="document.getElementById('acdc-qz-live-launch-modal').setAttribute('hidden','');document.getElementById('acdc-qz-live-launch-modal').style.display='';"></div>
            <div class="acdc-qz-modal-dialog" style="max-width:520px;">
                <div class="acdc-qz-modal-header">
                    <div>
                        <h2>▶ Lancer en live</h2>
                        <p id="acdc-qz-live-launch-quiz-name" style="margin:4px 0 0;font-size:13px;color:#4b5d76;font-style:italic;"></p>
                    </div>
                    <button type="button" class="acdc-qz-modal-close"
                        onclick="document.getElementById('acdc-qz-live-launch-modal').setAttribute('hidden','');document.getElementById('acdc-qz-live-launch-modal').style.display='';"
                        aria-label="Fermer">✕</button>
                </div>
                <div class="acdc-qz-modal-body" style="padding:24px;">
                    <div id="acdc-qz-live-launch-loading" style="text-align:center;padding:12px;display:none;color:#4b5d76;font-size:14px;">Chargement des séances…</div>
                    <div id="acdc-qz-live-launch-sessions-wrap">
                        <p style="font-size:14px;color:#4b5d76;margin:0 0 12px;font-weight:600;">Séances du jour pour ce quiz :</p>
                        <div id="acdc-qz-live-launch-sessions-list" style="display:flex;flex-direction:column;gap:8px;"></div>
                        <p id="acdc-qz-live-launch-no-sessions" style="display:none;font-size:13px;color:#8a9ab0;font-style:italic;margin:8px 0;">Aucune séance planifiée aujourd'hui pour cette formation.</p>
                        <div style="margin-top:18px;padding-top:14px;border-top:1px solid #f0e6dc;">
                            <button type="button" class="acdc-button acdc-button-soft" style="font-size:13px;"
                                onclick="acdcQzLiveLaunchDo(0)">
                                Lancer sans lier à une séance
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * @param string $purpose
     * @return void
     */
    private function render_qz_modal_duplicate_from( $purpose ) {
        $formations = $this->get_qz_available_formations();
        $sources    = $this->get_qz_quizzes( array( 'purpose' => $purpose, 'limit' => 500 ) );
        ?>
        <div id="acdc-qz-modal-duplicate-from" class="acdc-qz-modal" hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Dupliquer depuis', 'acdc-formation-saas' ); ?>">
            <div class="acdc-qz-modal-overlay" data-close></div>
            <div class="acdc-qz-modal-dialog">
                <header class="acdc-qz-modal-header">
                    <h2><?php esc_html_e( "Dupliquer un quiz dans une formation", 'acdc-formation-saas' ); ?></h2>
                    <button type="button" class="acdc-qz-modal-close" data-close aria-label="<?php esc_attr_e( 'Fermer', 'acdc-formation-saas' ); ?>">×</button>
                </header>

                <?php if ( empty( $sources ) ) : ?>
                    <div class="acdc-qz-modal-body">
                        <p><?php esc_html_e( "Aucun quiz source disponible pour cette finalité.", 'acdc-formation-saas' ); ?></p>
                    </div>
                <?php else : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acdc-qz-modal-body">
                        <input type="hidden" name="action" value="acdc_of_qz_duplicate_quiz" />
                        <?php wp_nonce_field( 'acdc_of_qz_duplicate_quiz', '_acdc_qz_nonce' ); ?>

                        <p>
                            <label>
                                <span class="acdc-required"><?php esc_html_e( 'Quiz source', 'acdc-formation-saas' ); ?></span>
                                <select name="source_quiz_id" required>
                                    <option value="0">— <?php esc_html_e( 'Sélectionner', 'acdc-formation-saas' ); ?> —</option>
                                    <?php foreach ( $sources as $s ) :
                                        $ftitle = $this->get_qz_formation_title( (int) $s->formation_id );
                                        ?>
                                        <option value="<?php echo esc_attr( (int) $s->id ); ?>">
                                            <?php
                                            echo esc_html( $s->title );
                                            if ( '' !== $ftitle ) {
                                                echo ' — ' . esc_html( $ftitle );
                                            }
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </p>

                        <p>
                            <label>
                                <span class="acdc-required"><?php esc_html_e( 'Formation cible', 'acdc-formation-saas' ); ?></span>
                                <select name="target_formation_id" required>
                                    <option value="0">— <?php esc_html_e( 'Sélectionner', 'acdc-formation-saas' ); ?> —</option>
                                    <?php foreach ( $formations as $f ) : ?>
                                        <option value="<?php echo esc_attr( (int) $f->id ); ?>"><?php echo esc_html( $this->format_qz_formation_label( $f ) ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </p>

                        <p class="description">
                            <?php esc_html_e( "La copie est totalement indépendante : modifier la copie n'affecte pas l'original, et inversement.", 'acdc-formation-saas' ); ?>
                        </p>

                        <footer class="acdc-qz-modal-footer">
                            <button type="button" class="acdc-button acdc-button-soft" data-close><?php esc_html_e( 'Annuler', 'acdc-formation-saas' ); ?></button>
                            <button type="submit" class="acdc-button acdc-button-primary"><?php esc_html_e( 'Dupliquer', 'acdc-formation-saas' ); ?></button>
                        </footer>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /* ==================================================================== */
    /*  Shortcodes publics                                                  */
    /* ==================================================================== */

    /**
     * Shortcode [acdc_qz_async_public].
     *
     * @param array $atts
    /**
     * Shortcode [acdc_qz_async_public] — Page publique de passation d'un quiz async.
     * URL: /acdc-quiz-public/?token=acdc_pos_a3f9b2e8c4d1...
     *
     * Gère 3 états :
     *  - Pré-passation : page d'accueil avec les infos du quiz et un bouton « Commencer ».
     *  - En cours : passation question par question, navigation et soumission.
     *  - Terminé : page de remerciement.
     *
     * Erreurs gérées : token vide / inconnu / expiré / déjà utilisé / quiz archivé.
     *
     * @since 3.21.03.1 (réécriture)
     */
    public function render_qz_async_public_shortcode( $atts = array() ) {
        // Charger le CSS public
        $this->enqueue_qz_public_assets();

        $token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
        $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

        ob_start();

        if ( '' === $token ) {
            ?>
            <div class="acdc-qz-public-wrapper">
                <div class="acdc-qz-public-card">
                    <h1><?php esc_html_e( 'Lien manquant', 'acdc-formation-saas' ); ?></h1>
                    <p><?php esc_html_e( "Cette page n'est accessible qu'à partir d'un lien personnel reçu par e-mail.", 'acdc-formation-saas' ); ?></p>
                </div>
            </div>
            <?php
            return (string) ob_get_clean();
        }

        $check = $this->validate_qz_async_token( $token );
        if ( is_wp_error( $check ) ) {
            $this->render_qz_public_error( $check );
            return (string) ob_get_clean();
        }

        $participant = $check['participant'];
        $session     = $check['session'];
        $quiz        = $check['quiz'];

        // Marque le participant comme "opened" et déclenche le verrouillage si premier clic
        $this->consume_qz_async_token( $participant );
        // Recharger le participant pour avoir le statut à jour
        $participant = $this->get_qz_participant( (int) $participant->id );

        // Cas 1 — déjà complété : on affiche la page de remerciement
        if ( 'completed' === $participant->status ) {
            $this->render_qz_public_thanks( $participant, $quiz );
            return (string) ob_get_clean();
        }

        // Cas 2 — soumission de la passation
        if ( 'submit' === $action && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
            $this->handle_qz_public_submit( $participant, $quiz, $token );
            return (string) ob_get_clean();
        }

        // Cas 3 — passation en cours
        if ( 'start' === $action ) {
            $this->render_qz_public_passation( $participant, $quiz, $token );
            return (string) ob_get_clean();
        }

        // Cas par défaut : page d'accueil pré-passation
        $this->render_qz_public_welcome( $participant, $quiz, $token );
        return (string) ob_get_clean();
    }

    /**
     * Affiche un écran d'erreur public.
     */
    private function render_qz_public_error( $wp_error ) {
        ?>
        <div class="acdc-qz-public-wrapper">
            <div class="acdc-qz-public-card acdc-qz-public-error">
                <h1><?php esc_html_e( "Lien indisponible", 'acdc-formation-saas' ); ?></h1>
                <p><?php echo esc_html( $wp_error->get_error_message() ); ?></p>
                <p class="acdc-qz-public-help">
                    <?php esc_html_e( "Si vous pensez qu'il s'agit d'une erreur, contactez votre formateur.", 'acdc-formation-saas' ); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Page d'accueil de la passation (avant que l'apprenant clique « Commencer »).
     */
    private function render_qz_public_welcome( $participant, $quiz, $token ) {
        $first_name = trim( explode( ' ', (string) $participant->full_name )[0] );
        if ( '' === $first_name ) {
            $first_name = esc_html__( 'apprenant', 'acdc-formation-saas' );
        }
        $questions_count = count( $this->get_qz_questions_for_quiz( (int) $quiz->id ) );
        $total_seconds   = 0;
        foreach ( $this->get_qz_questions_for_quiz( (int) $quiz->id ) as $q ) {
            $total_seconds += (int) $q->time_limit;
        }
        $estimated_minutes = max( 1, round( $total_seconds / 60 ) );

        $start_url = add_query_arg( array( 'token' => rawurlencode( $token ), 'action' => 'start' ),
                                    $this->build_qz_async_public_url( '' ) );
        // L'URL de base contient déjà ?token= via build_..., on doit reconstruire propre
        $page_id = (int) get_option( 'acdc_of_qz_async_public_page_id', 0 );
        $base_url = $page_id ? get_permalink( $page_id ) : home_url( '/acdc-quiz-public/' );
        $start_url = add_query_arg( array(
            'token'  => rawurlencode( $token ),
            'action' => 'start',
        ), $base_url );
        ?>
        <div class="acdc-qz-public-wrapper">
            <div class="acdc-qz-public-card">
                <h1>
                    <?php
                    /* translators: %s: first name */
                    printf( esc_html__( 'Bonjour %s', 'acdc-formation-saas' ), esc_html( $first_name ) );
                    ?>
                </h1>
                <p class="acdc-qz-public-intro">
                    <?php
                    /* translators: %s: quiz title */
                    printf( esc_html__( 'Vous êtes invité à passer le quiz : %s.', 'acdc-formation-saas' ), '<strong>' . esc_html( $quiz->title ) . '</strong>' );
                    ?>
                </p>
                <?php if ( ! empty( $quiz->description ) ) : ?>
                    <div class="acdc-qz-public-description">
                        <?php echo wp_kses_post( $quiz->description ); ?>
                    </div>
                <?php endif; ?>

                <ul class="acdc-qz-public-meta">
                    <li>
                        <span class="meta-label"><?php esc_html_e( 'Nombre de questions', 'acdc-formation-saas' ); ?></span>
                        <span class="meta-value"><?php echo esc_html( $questions_count ); ?></span>
                    </li>
                    <li>
                        <span class="meta-label"><?php esc_html_e( 'Durée estimée', 'acdc-formation-saas' ); ?></span>
                        <span class="meta-value"><?php
                        /* translators: %d: minutes */
                        printf( esc_html( _n( '%d minute', '%d minutes', $estimated_minutes, 'acdc-formation-saas' ) ), $estimated_minutes );
                        ?></span>
                    </li>
                </ul>

                <div class="acdc-qz-public-tips">
                    <p><strong><?php esc_html_e( "Avant de commencer :", 'acdc-formation-saas' ); ?></strong></p>
                    <ul>
                        <li><?php esc_html_e( "Installez-vous dans un environnement calme.", 'acdc-formation-saas' ); ?></li>
                        <li><?php esc_html_e( "Une fois commencé, terminez le quiz d'une traite si possible.", 'acdc-formation-saas' ); ?></li>
                        <li><?php esc_html_e( "Vos réponses sont enregistrées au fur et à mesure.", 'acdc-formation-saas' ); ?></li>
                    </ul>
                </div>

                <a href="<?php echo esc_url( $start_url ); ?>" class="acdc-qz-public-button-primary">
                    <?php esc_html_e( 'Commencer le quiz', 'acdc-formation-saas' ); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Affiche la passation question par question (formulaire unique soumis en POST).
     */
    private function render_qz_public_passation( $participant, $quiz, $token ) {
        $questions = $this->get_qz_questions_for_quiz( (int) $quiz->id );
        if ( empty( $questions ) ) {
            ?>
            <div class="acdc-qz-public-wrapper">
                <div class="acdc-qz-public-card acdc-qz-public-error">
                    <h1><?php esc_html_e( 'Quiz vide', 'acdc-formation-saas' ); ?></h1>
                    <p><?php esc_html_e( "Ce quiz ne contient aucune question. Contactez votre formateur.", 'acdc-formation-saas' ); ?></p>
                </div>
            </div>
            <?php
            return;
        }

        // Marque "started" si pas déjà fait
        $this->mark_qz_async_started( (int) $participant->id );

        // 3.21.03.1-hotfix7 : expose le participant ID pour le seed du mélange des Puzzle.
        // Permet d'avoir un ordre aléatoire mais reproductible pour cet apprenant donné.
        $GLOBALS['acdc_qz_current_participant_id'] = (int) $participant->id;

        $page_id = (int) get_option( 'acdc_of_qz_async_public_page_id', 0 );
        $submit_url = $page_id ? get_permalink( $page_id ) : home_url( '/acdc-quiz-public/' );
        $submit_url = add_query_arg( array(
            'token'  => rawurlencode( $token ),
            'action' => 'submit',
        ), $submit_url );
        ?>
        <div class="acdc-qz-public-wrapper acdc-qz-public-passation-wrapper acdc-qz-public-fullscreen">
            <?php /* ACDC 3.25.157 — novalidate : la validation native du navigateur prenait
                     la main AVANT l'événement submit, si bien que notre propre contrôle (qui
                     sait quelle question est affichée) n'était jamais atteint. Pire, les
                     questions non affichées portent des champs required dans une section
                     hidden : le navigateur refuse alors la soumission sans rien afficher,
                     faute de pouvoir donner le focus au champ fautif. L'apprenant cliquait
                     « Valider » et il ne se passait rien. */ ?>
            <form method="post" action="<?php echo esc_url( $submit_url ); ?>" class="acdc-qz-public-form" novalidate>
                <header class="acdc-qz-public-passation-header">
                    <h2><?php echo esc_html( $quiz->title ); ?></h2>
                    <div class="acdc-qz-public-progress">
                        <span id="acdc-qz-current">1</span> / <span><?php echo esc_html( count( $questions ) ); ?></span>
                    </div>
                </header>

                <?php foreach ( $questions as $idx => $q ) :
                    $position = (int) $idx + 1;
                    $answers  = $this->get_qz_answers_for_question_db( (int) $q->id );
                    $count    = count( $answers );
                    $type     = (string) $q->type;

                    // Classe d'agencement selon le type et le nombre de réponses :
                    //   qcm_single / qcm_multi avec 4+ réponses → grille 2x2 (ou 2x3 etc.)
                    //   qcm_single / qcm_multi avec 3 réponses  → grille 1x3
                    //   true_false, open_text, ou QCM à 2 réponses → empilé pleine largeur
                    if ( in_array( $type, array( self::ACDC_OF_QZ_QTYPE_QCM_SINGLE, self::ACDC_OF_QZ_QTYPE_QCM_MULTIPLE, self::ACDC_OF_QZ_QTYPE_POLL ), true ) && $count >= 4 ) {
                        $layout_class = 'acdc-qz-layout-grid';
                    } elseif ( in_array( $type, array( self::ACDC_OF_QZ_QTYPE_QCM_SINGLE, self::ACDC_OF_QZ_QTYPE_QCM_MULTIPLE, self::ACDC_OF_QZ_QTYPE_POLL ), true ) && 3 === $count ) {
                        $layout_class = 'acdc-qz-layout-row';
                    } else {
                        $layout_class = 'acdc-qz-layout-stack';
                    }

                    // Image illustrative (depuis media_id)
                    $media_url = '';
                    if ( ! empty( $q->media_id ) ) {
                        $media_url = wp_get_attachment_image_url( (int) $q->media_id, 'large' );
                    }
                    ?>
                    <section class="acdc-qz-public-question <?php echo esc_attr( $layout_class ); ?>" data-question-position="<?php echo esc_attr( $position ); ?>" <?php echo $idx > 0 ? 'hidden' : ''; ?>>

                        <?php if ( $media_url ) : ?>
                            <div class="acdc-qz-public-q-media">
                                <img src="<?php echo esc_url( $media_url ); ?>" alt="" />
                            </div>
                        <?php endif; ?>

                        <div class="acdc-qz-public-q-statement">
                            <h3 class="acdc-qz-public-q-title">
                                <?php echo esc_html( $q->title ); ?>
                            </h3>
                            <?php if ( ! empty( $q->description ) ) : ?>
                                <div class="acdc-qz-public-q-description">
                                    <?php echo wp_kses_post( $q->description ); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php $this->render_qz_public_question_answers( $q, $answers, $position ); ?>

                        <div class="acdc-qz-public-q-nav">
                            <?php if ( $idx > 0 ) : ?>
                                <button type="button" class="acdc-qz-public-button-soft" data-prev-question><?php esc_html_e( '&larr; Précédente', 'acdc-formation-saas' ); ?></button>
                            <?php endif; ?>
                            <?php if ( $idx < count( $questions ) - 1 ) : ?>
                                <button type="button" class="acdc-qz-public-button-primary" data-next-question><?php esc_html_e( 'Suivante →', 'acdc-formation-saas' ); ?></button>
                            <?php else : ?>
                                <button type="submit" class="acdc-qz-public-button-primary"><?php esc_html_e( '✓ Valider mes réponses', 'acdc-formation-saas' ); ?></button>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </form>
        </div>

        <?php // ACDC 3.21.70 — Anti-triche : détection changement onglet, blocage copier-coller et clic-droit ?>
        <div id="acdc-qz-anticheat-banner" style="display:none;position:fixed;top:0;left:0;right:0;z-index:99999;background:#b91c1c;color:#fff;text-align:center;padding:12px 16px;font-size:15px;font-weight:600;letter-spacing:.01em;">
            ⚠️ <?php esc_html_e( "Changement d'onglet détecté. Cet événement est enregistré.", 'acdc-formation-saas' ); ?>
        </div>
        <script>
        (function() {
            if (typeof window._acdcQzAntiCheatInit !== 'undefined') { return; }
            window._acdcQzAntiCheatInit = true;

            var ajaxUrl     = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
            var participantId = <?php echo (int) $participant->id; ?>;
            var token       = '<?php echo esc_js( $token ); ?>';
            var banner      = document.getElementById('acdc-qz-anticheat-banner');
            var bannerTimer = null;

            function showBanner() {
                if (!banner) { return; }
                banner.style.display = 'block';
                clearTimeout(bannerTimer);
                bannerTimer = setTimeout(function() {
                    banner.style.display = 'none';
                }, 4000);
            }

            function reportTabSwitch() {
                try {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', ajaxUrl, true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.send(
                        'action=acdc_of_qz_player_tab_switch'
                        + '&participant_id=' + participantId
                        + '&token=' + encodeURIComponent(token)
                    );
                } catch(e) {}
            }

            // Détection changement d'onglet / mise en arrière-plan
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'hidden') {
                    reportTabSwitch();
                } else {
                    showBanner();
                }
            });

            // Blocage copier-coller
            document.addEventListener('copy',  function(e) { e.preventDefault(); });
            document.addEventListener('cut',   function(e) { e.preventDefault(); });
            document.addEventListener('paste', function(e) { e.preventDefault(); });

            // Blocage clic-droit
            document.addEventListener('contextmenu', function(e) { e.preventDefault(); });

            // Blocage sélection de texte (CSS fallback géré côté CSS si besoin)
            document.addEventListener('selectstart', function(e) {
                var tag = e.target ? (e.target.tagName || '').toUpperCase() : '';
                if (tag !== 'INPUT' && tag !== 'TEXTAREA') {
                    e.preventDefault();
                }
            });
        })();
        </script>
        <?php
    }

    /**
     * Rend les propositions de réponse selon le type de question.
     */
    /**
     * ACDC 3.25.168 — Consigne de saisie affichée sous l'énoncé.
     *
     * @param string $type Type de question.
     *
     * @return void
     */
    private function render_qz_answer_instruction( $type ) {
        $multi  = in_array( $type, array( self::ACDC_OF_QZ_QTYPE_QCM_MULTIPLE, self::ACDC_OF_QZ_QTYPE_POLL ), true );
        $single = in_array( $type, array( self::ACDC_OF_QZ_QTYPE_QCM_SINGLE, self::ACDC_OF_QZ_QTYPE_TRUE_FALSE ), true );
        /* ACDC 3.25.174 — La question de remise en ordre ne portait AUCUNE consigne : ni
           « une seule », ni « plusieurs », rien. L'apprenant découvrait qu'il fallait
           faire glisser cinq éléments, et devait le comprendre seul. */
        if ( self::ACDC_OF_QZ_QTYPE_PUZZLE === $type ) {
            ?>
            <p class="acdc-qz-answer-instruction is-single">
                <span aria-hidden="true">↕</span>
                <?php esc_html_e( 'Remettez TOUS les éléments dans le bon ordre', 'acdc-formation-saas' ); ?>
            </p>
            <?php
            return;
        }
        if ( ! $multi && ! $single ) {
            return;
        }
        if ( $multi ) {
            /* ACDC 3.25.173 — « Plusieurs réponses possibles » ne disait pas qu'il fallait
               les cocher TOUTES : on pouvait n'en cocher qu'une ou deux et croire avoir
               répondu. On ne dit pas COMBIEN — ce serait donner la réponse — mais on dit
               ce qu'il faut faire. */
            $label = ( self::ACDC_OF_QZ_QTYPE_POLL === $type )
                ? __( 'Plusieurs réponses possibles (sondage)', 'acdc-formation-saas' )
                : __( 'Plusieurs réponses possibles — cochez TOUTES les bonnes réponses', 'acdc-formation-saas' );
        } else {
            $label = __( 'Une seule réponse possible', 'acdc-formation-saas' );
        }
        ?>
        <p class="acdc-qz-answer-instruction <?php echo $multi ? 'is-multi' : 'is-single'; ?>">
            <span aria-hidden="true"><?php echo $multi ? '⚠' : '●'; ?></span>
            <?php echo esc_html( $label ); ?>
        </p>
        <?php
    }

    private function render_qz_public_question_answers( $question, $answers, $position ) {
        $name = "answers[{$question->id}]";
        $type = (string) $question->type;

        /* ACDC 3.25.325 — LA BONNE RÉPONSE ÉTAIT TOUJOURS LA PREMIÈRE.
           Les propositions sortaient dans l'ordre de saisie, et un quiz se
           rédige en écrivant d'abord la bonne réponse : A était juste à chaque
           question, et les choix multiples s'alignaient sur l'alphabet.
           On mélange donc, avec une graine tirée de la question ET de
           l'apprenant : chacun a son ordre, et il ne bouge pas s'il revient sur
           la question. Le Vrai/Faux est laissé tel quel — « Faux » avant
           « Vrai » ne trompe personne et se lit mal — et le sondage garde
           l'ordre voulu par son auteur : il n'y a rien à y deviner. */
        if ( in_array( $type, array( self::ACDC_OF_QZ_QTYPE_QCM_SINGLE, self::ACDC_OF_QZ_QTYPE_QCM_MULTIPLE ), true ) ) {
            $answers = $this->acdc_qz_melange_deterministe(
                $answers,
                (int) $question->id * 1000 + ( isset( $GLOBALS['acdc_qz_current_participant_id'] ) ? (int) $GLOBALS['acdc_qz_current_participant_id'] : 0 )
            );
        }

        /* ACDC 3.25.168 — La consigne « plusieurs réponses possibles » était une ligne
           grise parmi d'autres, et le choix unique n'en portait aucune : l'apprenant
           découvrait la règle en cochant. C'est piégeux sur une évaluation dont le
           résultat est opposable. La consigne est désormais annoncée pour les deux cas,
           juste sous la question, et signalée en rouge quand plusieurs cases sont
           attendues — c'est là qu'on se trompe. */
        $this->render_qz_answer_instruction( $type );

        switch ( $type ) {
            case self::ACDC_OF_QZ_QTYPE_QCM_SINGLE:
            case self::ACDC_OF_QZ_QTYPE_TRUE_FALSE:
                ?>
                <div class="acdc-qz-public-answers">
                    <?php foreach ( $answers as $a ) : ?>
                        <label class="acdc-qz-public-answer">
                            <input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (int) $a->id ); ?>" required />
                            <span><?php echo esc_html( $a->text ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php
                break;

            // hotfix43 — Sondage : checkbox (multi-select), pas radio.
            case self::ACDC_OF_QZ_QTYPE_POLL:
                ?>
                <div class="acdc-qz-public-answers">
                    <?php foreach ( $answers as $a ) : ?>
                        <label class="acdc-qz-public-answer">
                            <input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( (int) $a->id ); ?>" />
                            <span><?php echo esc_html( $a->text ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php
                break;

            case self::ACDC_OF_QZ_QTYPE_QCM_MULTIPLE:
                ?>
                <div class="acdc-qz-public-answers">
                    <?php foreach ( $answers as $a ) : ?>
                        <label class="acdc-qz-public-answer">
                            <input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( (int) $a->id ); ?>" />
                            <span><?php echo esc_html( $a->text ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php
                break;

            case self::ACDC_OF_QZ_QTYPE_OPEN_TEXT:
                ?>
                <div class="acdc-qz-public-answers">
                    <textarea name="<?php echo esc_attr( $name ); ?>" rows="5" placeholder="<?php esc_attr_e( 'Votre réponse…', 'acdc-formation-saas' ); ?>" required></textarea>
                </div>
                <?php
                break;

            case self::ACDC_OF_QZ_QTYPE_PUZZLE:
                // Mélange déterministe basé sur l'ID de question + ID du participant.
                // De cette façon, l'ordre affiché reste stable tant que l'apprenant n'a
                // pas terminé (s'il revient en arrière, il retrouve son ordre courant).
                /* ACDC 3.25.325 — Même porte que les QCM : un seul mélange dans
                   tout le module, une seule façon de le semer. */
                $shuffled = $this->acdc_qz_melange_deterministe(
                    $answers,
                    (int) $question->id * 1000 + ( isset( $GLOBALS['acdc_qz_current_participant_id'] ) ? (int) $GLOBALS['acdc_qz_current_participant_id'] : 0 )
                );
                $hidden_value = implode( ',', array_map( function( $a ) { return (int) $a->id; }, $shuffled ) );
                ?>
                <p class="acdc-qz-public-help-inline">
                    <?php esc_html_e( 'Remettez les éléments dans le bon ordre avec les flèches.', 'acdc-formation-saas' ); ?>
                </p>
                <div class="acdc-qz-public-puzzle" data-question-id="<?php echo esc_attr( (int) $question->id ); ?>">
                    <input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $hidden_value ); ?>" class="acdc-qz-public-puzzle-order" />
                    <ol class="acdc-qz-public-puzzle-list">
                        <?php foreach ( $shuffled as $idx => $a ) : ?>
                            <li class="acdc-qz-public-puzzle-item" data-answer-id="<?php echo esc_attr( (int) $a->id ); ?>">
                                <span class="acdc-qz-public-puzzle-position"><?php echo esc_html( (int) $idx + 1 ); ?></span>
                                <span class="acdc-qz-public-puzzle-text"><?php echo esc_html( $a->text ); ?></span>
                                <span class="acdc-qz-public-puzzle-controls">
                                    <button type="button" class="acdc-qz-public-puzzle-btn" data-direction="up" aria-label="<?php esc_attr_e( 'Monter', 'acdc-formation-saas' ); ?>">↑</button>
                                    <button type="button" class="acdc-qz-public-puzzle-btn" data-direction="down" aria-label="<?php esc_attr_e( 'Descendre', 'acdc-formation-saas' ); ?>">↓</button>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
                <?php
                break;

            default:
                /* Types non supportés en 3.21.03.1 (puzzle, etc.) — affichés comme texte libre temporairement */
                ?>
                <div class="acdc-qz-public-answers">
                    <p class="acdc-qz-public-help-inline">
                        <?php esc_html_e( 'Cette question utilise un format non encore supporté en passation à distance. Une saisie libre est proposée à la place.', 'acdc-formation-saas' ); ?>
                    </p>
                    <textarea name="<?php echo esc_attr( $name ); ?>" rows="3"></textarea>
                </div>
                <?php
                break;
        }
    }

    /**
     * Traite la soumission du quiz par l'apprenant : enregistre les réponses, calcule un score,
     * marque le participant comme complété, envoie le mail de confirmation, affiche la page merci.
     */
    private function handle_qz_public_submit( $participant, $quiz, $token ) {
        global $wpdb;

        $tbl_player_answers = $this->get_qz_table( 'player_answers' );
        $now = current_time( 'mysql' );
        $answers_in = isset( $_POST['answers'] ) && is_array( $_POST['answers'] ) ? wp_unslash( $_POST['answers'] ) : array();

        $questions = $this->get_qz_questions_for_quiz( (int) $quiz->id );
        $score_total = 0;
        $score_max   = 0;

        foreach ( $questions as $q ) {
            $qid = (int) $q->id;
            $reponse = $answers_in[ $qid ] ?? null;
            /* ACDC 3.25.168 — NULL, et non zéro, pour ce qui ne se corrige pas tout seul.
               Une réponse rédigée était enregistrée à zéro dès l'envoi : l'écran de
               résultats la présentait « ✗ Faux » avant même que le formateur l'ait lue. */
            $is_correct = null;
            $value_text = '';
            $value_answer_ids = array();

            if ( in_array( $q->type, array( self::ACDC_OF_QZ_QTYPE_QCM_SINGLE, self::ACDC_OF_QZ_QTYPE_TRUE_FALSE ), true ) ) {
                $value_answer_ids = $reponse ? array( (int) $reponse ) : array();
            // hotfix43 — POLL checkbox (multi-select), traiter comme QCM_MULTIPLE.
            } elseif ( self::ACDC_OF_QZ_QTYPE_POLL === $q->type ) {
                $value_answer_ids = is_array( $reponse ) ? array_map( 'intval', $reponse ) : array();
            } elseif ( self::ACDC_OF_QZ_QTYPE_QCM_MULTIPLE === $q->type ) {
                $value_answer_ids = is_array( $reponse ) ? array_map( 'intval', $reponse ) : array();
            } elseif ( self::ACDC_OF_QZ_QTYPE_OPEN_TEXT === $q->type ) {
                $value_text = sanitize_textarea_field( (string) $reponse );
            } elseif ( self::ACDC_OF_QZ_QTYPE_PUZZLE === $q->type ) {
                // Le Puzzle envoie une chaîne CSV des IDs dans l'ordre saisi par l'apprenant.
                // Format attendu : "12,15,11,14"
                $csv = is_string( $reponse ) ? $reponse : '';
                $value_answer_ids = array_values( array_filter( array_map( 'intval', explode( ',', $csv ) ) ) );
            }

            /* Calcul du score (uniquement pour types scorés). Le sondage et le texte
               libre ne sont jamais scorés automatiquement.
               ACDC 3.25.168 — Même correcteur que la passation en salle : une part de
               réussite, et non plus un tout-ou-rien qui mettait à zéro un apprenant
               n'ayant manqué qu'une case sur trois. */
            $score_ratio = null;
            if ( (int) $q->is_scored === 1 && ! in_array( $q->type, array( self::ACDC_OF_QZ_QTYPE_POLL, self::ACDC_OF_QZ_QTYPE_OPEN_TEXT ), true ) ) {
                $score_max += (int) $q->points_value;

                $verdict     = $this->qz_grade_answer_set( $q, $value_answer_ids );
                $score_ratio = (float) $verdict['ratio'];
                $is_correct  = ( null === $verdict['is_correct'] ) ? 0 : (int) $verdict['is_correct'];
                $score_total += (int) $q->points_value * $score_ratio;
            }

            $wpdb->insert( $tbl_player_answers, array(
                'session_id'       => (int) $participant->session_id,
                'participant_id'   => (int) $participant->id,
                'question_id'      => $qid,
                'answer_id'        => ( count( $value_answer_ids ) === 1 ) ? (int) $value_answer_ids[0] : null,
                'answer_ids_json'  => wp_json_encode( $value_answer_ids ),
                'answer_text'      => $value_text,
                'is_correct'       => $is_correct,
                'score_ratio'      => $score_ratio,
                'score_earned'     => ( null === $score_ratio ) ? 0 : (float) $q->points_value * $score_ratio,
                'answered_at'      => $now,
            ) );
        }

        $score_percentage = ( $score_max > 0 ) ? round( ( $score_total / $score_max ) * 100, 2 ) : null;
        $this->mark_qz_async_completed( (int) $participant->id, $score_percentage );

        // hotfix52 — Générer le PDF ET propager vers la fiche inscription (onglets Qualiopi)
        // La propagation est découplée : si la génération PDF échoue (classes PDF absentes,
        // questions open_text en attente de correction, etc.), on stocke quand même un lien
        // vers la page de résultats dans la fiche inscription — la ligne apparaît dans les tabs.
        $pdf_result = $this->generate_qz_result_pdf_for_participant( (int) $participant->id, (int) $participant->session_id );

        if ( ! $pdf_result || empty( $pdf_result['url'] ) ) {
            // Fallback : lien vers la page de résultats en ligne du nouveau moteur
            $session_obj = $this->get_qz_dispatch_session( (int) $participant->session_id );
            if ( $session_obj ) {
                /* ACDC 3.25.167 — Cette comparaison confrontait une FINALITÉ à un slug
                   d'onglet : elle ne pouvait jamais être vraie, et tout résultat, y
                   compris un test de positionnement, atterrissait sur l'onglet des
                   évaluations des acquis. */
                $results_url = $this->portal_page_url( array(
                    'tab'         => $this->qz_results_tab_for_purpose( $session_obj->quiz_purpose ?? '' ),
                    'view'        => 'results',
                    'participant' => (int) $participant->id,
                ) );
                $this->qz_propagate_result_url_to_registration( $participant, $session_obj, $results_url );
            }
        }
        // (si PDF généré avec succès, la propagation a déjà eu lieu dans generate_qz_result_pdf_for_participant)

        // Mail de confirmation
        $this->send_qz_async_confirmation_email( array(
            'email'      => $participant->email,
            'first_name' => trim( explode( ' ', (string) $participant->full_name )[0] ),
            'last_name'  => '',
            'token'      => $token,
            'quiz'       => $quiz,
            'score'      => $score_percentage,
        ) );

        // Recharger le participant
        $participant = $this->get_qz_participant( (int) $participant->id );
        $this->render_qz_public_thanks( $participant, $quiz );
    }

    /**
     * Page de remerciement après passation (ou si déjà passé).
     */
    private function render_qz_public_thanks( $participant, $quiz ) {
        ?>
        <div class="acdc-qz-public-wrapper">
            <div class="acdc-qz-public-card acdc-qz-public-success">
                <div class="acdc-qz-public-checkmark">✓</div>
                <h1><?php esc_html_e( 'Merci pour votre participation !', 'acdc-formation-saas' ); ?></h1>
                <p>
                    <?php
                    /* translators: %s: quiz title */
                    printf( esc_html__( 'Vos réponses au quiz « %s » ont bien été enregistrées.', 'acdc-formation-saas' ), esc_html( $quiz->title ) );
                    ?>
                </p>
                <p class="acdc-qz-public-help">
                    <?php esc_html_e( "Vous recevrez un e-mail de confirmation. Vous pouvez maintenant fermer cette page.", 'acdc-formation-saas' ); ?>
                </p>
                <?php if ( ! empty( $participant->total_score_percentage ) ) : ?>
                    <p class="acdc-qz-public-score">
                        <?php
                        /* translators: %s: score percentage */
                        printf( esc_html__( 'Score indicatif : %s %%', 'acdc-formation-saas' ), esc_html( number_format_i18n( $participant->total_score_percentage, 1 ) ) );
                        ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Récupère un participant par ID (helper).
     */
    public function get_qz_participant( $participant_id ) {
        global $wpdb;
        $tbl = $this->get_qz_table( 'participants' );
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl} WHERE id = %d", (int) $participant_id ) );
    }

    /**
     * Charge les assets CSS et JS de la page publique.
     * 3.21.03.1-hotfix3 : conservé pour rétro-compatibilité, mais l'enqueue
     * effectif a lieu dans includes/quizzes/templates/standalone-public-page.php
     * (le CSS dans le head a besoin d'être ajouté AVANT wp_head, pas pendant
     * le rendu du shortcode qui survient dans le_content — trop tard).
     */
    private function enqueue_qz_public_assets() {
        // Tentative de fallback au cas où le template autonome n'aurait pas pris
        // (par exemple si le filtre template_include est bypassed par un autre plugin).
        if ( ! wp_style_is( 'acdc-quizzes-public', 'enqueued' ) ) {
            wp_enqueue_style(
                'acdc-quizzes-public',
                ACDC_OF_SAAS_URL . 'assets/css/quizzes-public.css',
                array(),
                ACDC_OF_SAAS_VERSION
            );
        }
        if ( ! wp_script_is( 'acdc-quizzes-public', 'enqueued' ) ) {
            wp_enqueue_script(
                'acdc-quizzes-public',
                ACDC_OF_SAAS_URL . 'assets/js/quizzes-public.js',
                array(),
                ACDC_OF_SAAS_VERSION,
                true
            );
        }
    }

    /**
     * Shortcode [acdc_qz_live_player] — écran apprenant pleine page.
     * 3.21.04.2-a : remplacement du stub par le vrai écran live.
     *
     * @param array $atts
     * @return string
     */
    public function render_qz_live_player_shortcode( $atts = array() ) {
        $pin_param = isset( $_GET['pin'] ) ? preg_replace( '/[^0-9]/', '', wp_unslash( (string) $_GET['pin'] ) ) : '';
        $ajax_url = admin_url( 'admin-ajax.php' );
        ob_start();
        ?>
        <div class="acdc-qz-live-player" data-ajax-url="<?php echo esc_url( $ajax_url ); ?>">

            <!-- HEADER -->
            <header class="acdc-qz-live-player-header">
                <div class="acdc-qz-live-player-brand">ACDC Formation</div>
                <div class="acdc-qz-live-player-meta" id="acdc-qz-player-meta">
                    <span class="acdc-qz-live-player-nickname" id="acdc-qz-player-nickname"></span>
                    <span class="acdc-qz-live-player-score" id="acdc-qz-player-score"></span>
                </div>
            </header>

            <!-- États successifs gérés par JS -->
            <main class="acdc-qz-live-player-main" id="acdc-qz-player-main">

                <!-- État JOIN (saisie PIN + pseudo + avatar) -->
                <section class="acdc-qz-player-state acdc-qz-player-state-join is-active" data-state="join">
                    <div class="acdc-qz-player-join-inner">
                        <h1 class="acdc-qz-player-join-title">REJOINDRE LA PARTIE</h1>
                        <p class="acdc-qz-player-join-subtitle">Entrez le code PIN affiché sur l'écran du formateur.</p>

                        <div class="acdc-qz-player-join-fields">
                            <label class="acdc-qz-player-field">
                                <span class="acdc-qz-player-field-label">Code PIN</span>
                                <input type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off"
                                       id="acdc-qz-player-pin" maxlength="8"
                                       value="<?php echo esc_attr( $pin_param ); ?>"
                                       placeholder="ex. 767 766" />
                            </label>
                        </div>

                        <!-- Zone sélection apprenant (chargée dynamiquement après saisie du PIN) -->
                        <div id="acdc-qz-player-learner-pick" style="display:none;width:100%;margin:16px 0;">
                            <p style="font-size:13px;color:#4b5d76;margin:0 0 10px;font-weight:600;">Sélectionnez votre nom dans la liste</p>
                            <div id="acdc-qz-player-learner-list" style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center;"></div>
                            <p style="font-size:12px;color:#8a9ab0;margin:12px 0 0;">
                                Votre nom n'est pas dans la liste ? <a href="#" id="acdc-qz-player-guest-link" style="color:#d6a353;">Rejoindre comme invité</a>
                            </p>
                        </div>

                        <?php /* ACDC 3.25.327 — Le repli en invité s'annonce. Sans cette
                                 ligne, l'apprenant ne voyait aucune différence entre
                                 « je suis rattaché à mon dossier » et « je joue en
                                 invité » : le 19 août, trois évaluations des acquis
                                 sont allées au bout sans rattachement. */ ?>
                        <p id="acdc-qz-player-guest-note" class="acdc-qz-player-guest-note" style="display:none;"></p>

                        <div id="acdc-qz-player-nickname-wrap" class="acdc-qz-player-join-fields" style="display:none;">
                            <label class="acdc-qz-player-field">
                                <span class="acdc-qz-player-field-label">Pseudo</span>
                                <input type="text" maxlength="30" id="acdc-qz-player-nickname-input" placeholder="ex. Alex" />
                            </label>
                        </div>

                        <p class="acdc-qz-player-avatar-label-text">Choisis ton avatar</p>
                        <div class="acdc-qz-player-avatar-grid">
                            <label class="acdc-qz-player-avatar-card-big">
                                <input type="radio" name="avatar" value="femme" />
                                <span class="acdc-qz-player-avatar-card-inner">
                                    <span class="acdc-qz-player-avatar-symbol">♀</span>
                                    <span class="acdc-qz-player-avatar-name">Femme</span>
                                </span>
                            </label>
                            <label class="acdc-qz-player-avatar-card-big">
                                <input type="radio" name="avatar" value="homme" />
                                <span class="acdc-qz-player-avatar-card-inner">
                                    <span class="acdc-qz-player-avatar-symbol">♂</span>
                                    <span class="acdc-qz-player-avatar-name">Homme</span>
                                </span>
                            </label>
                            <?php /* ACDC 3.25.173 — Le choix se limitait à « Femme » ou
                                     « Homme » : une donnée de genre exigée de l'apprenant
                                     pour un simple pictogramme, sans possibilité de ne pas
                                     répondre. Troisième option neutre, sélectionnée par
                                     défaut pour que le choix reste facultatif. */ ?>
                            <label class="acdc-qz-player-avatar-card-big">
                                <input type="radio" name="avatar" value="neutre" checked />
                                <span class="acdc-qz-player-avatar-card-inner">
                                    <span class="acdc-qz-player-avatar-symbol">★</span>
                                    <span class="acdc-qz-player-avatar-name">Sans préférence</span>
                                </span>
                            </label>
                        </div>

                        <button type="button" class="acdc-button acdc-button-primary acdc-qz-player-join-submit" id="acdc-qz-player-join-btn">
                            REJOINDRE LA PARTIE
                        </button>

                        <p class="acdc-qz-player-error" id="acdc-qz-player-error"></p>

                        <p class="acdc-qz-player-waiting-inline" id="acdc-qz-player-waiting-inline" style="display:none">
                            <span class="acdc-qz-player-waiting-spinner"></span>
                            En attente des autres joueurs…
                        </p>
                    </div>
                </section>

                <!-- État WAITING (en attente du démarrage) -->
                <section class="acdc-qz-player-state acdc-qz-player-state-waiting" data-state="waiting">
                    <div class="acdc-qz-player-card">
                        <div class="acdc-qz-player-loader"></div>
                        <h2 class="acdc-qz-player-waiting-title">Tu es dans la partie !</h2>
                        <p class="acdc-qz-player-waiting-text">En attente du démarrage par le formateur…</p>
                        <div class="acdc-qz-player-waiting-avatar" id="acdc-qz-player-waiting-avatar"></div>
                    </div>
                </section>

                <!-- État QUESTION -->
                <section class="acdc-qz-player-state acdc-qz-player-state-question" data-state="question">
                    <div class="acdc-qz-player-timer-bar-wrap" id="acdc-qz-player-timer-wrap" style="display:none">
                        <div class="acdc-qz-player-timer-bar" id="acdc-qz-player-timer-bar"></div>
                        <span class="acdc-qz-player-timer-count" id="acdc-qz-player-timer-count">—</span>
                    </div>
                    <h2 class="acdc-qz-player-question-title" id="acdc-qz-player-q-title">…</h2>
                    <?php /* ACDC 3.25.168 — La consigne ne figurait qu'en pied d'écran, en
                             petit, à droite : personne ne la lisait avant de répondre. */ ?>
                    <p class="acdc-qz-answer-instruction" id="acdc-qz-player-q-instruction" style="display:none"></p>
                    <!-- QCM / Vrai-Faux / Sondage -->
                    <div class="acdc-qz-player-question-answers" id="acdc-qz-player-q-answers"></div>
                    <!-- Réponse libre -->
                    <div class="acdc-qz-player-open-wrap" id="acdc-qz-player-open-wrap" style="display:none">
                        <textarea id="acdc-qz-player-open-text" class="acdc-qz-player-open-textarea" placeholder="Rédigez votre réponse ici…" rows="6"></textarea>
                        <button type="button" class="acdc-button acdc-button-primary acdc-qz-player-open-submit" id="acdc-qz-player-open-submit">Envoyer</button>
                    </div>
                    <!-- Puzzle (remise en ordre) -->
                    <div class="acdc-qz-player-puzzle-wrap" id="acdc-qz-player-puzzle-wrap" style="display:none">
                        <div class="acdc-qz-player-puzzle-inner">
                            <div class="acdc-qz-player-puzzle-slots" id="acdc-qz-player-puzzle-slots"></div>
                            <div class="acdc-qz-player-puzzle-bank" id="acdc-qz-player-puzzle-bank"></div>
                        </div>
                        <button type="button" class="acdc-button acdc-button-primary acdc-qz-player-puzzle-submit" id="acdc-qz-player-puzzle-submit" style="display:none">Valider l'ordre</button>
                    </div>
                    <!-- Bouton valider (multi-select) -->
                    <div class="acdc-qz-player-multi-footer" id="acdc-qz-player-multi-footer" style="display:none">
                        <button type="button" class="acdc-button acdc-button-primary acdc-qz-player-multi-submit" id="acdc-qz-player-multi-submit">Valider mes réponses</button>
                    </div>
                    <footer class="acdc-qz-player-question-footer">
                        <span class="acdc-qz-player-q-counter" id="acdc-qz-player-q-num">1/1</span>
                        <span class="acdc-qz-player-q-type" id="acdc-qz-player-q-type"></span>
                    </footer>
                </section>

                <!-- État FEEDBACK (entre les questions, après révélation) -->
                <section class="acdc-qz-player-state acdc-qz-player-state-feedback" data-state="feedback">
                    <div class="acdc-qz-player-feedback-card" id="acdc-qz-player-feedback-card">
                        <div class="acdc-qz-player-feedback-icon" id="acdc-qz-player-feedback-icon">✓</div>
                        <h2 class="acdc-qz-player-feedback-title" id="acdc-qz-player-feedback-title">…</h2>
                        <p class="acdc-qz-player-feedback-score" id="acdc-qz-player-feedback-score"></p>
                        <p class="acdc-qz-player-feedback-sub" id="acdc-qz-player-feedback-sub"></p>
                    </div>
                </section>

                <!-- État PODIUM FINAL -->
                <section class="acdc-qz-player-state acdc-qz-player-state-podium" data-state="podium">
                    <div class="acdc-qz-podium-wrap">
                        <div class="acdc-qz-podium" id="acdc-qz-player-podium"></div>
                    </div>
                    <div class="acdc-qz-player-podium-myrank" id="acdc-qz-player-podium-myrank"></div>
                    <canvas class="acdc-qz-confetti-canvas" id="acdc-qz-player-confetti"></canvas>
                </section>

            </main>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /* ==================================================================== */
    /*  Modale Import IA Mode B — 3.21.31                                   */
    /* ==================================================================== */

    /**
     * Rend la modale d'import IA Mode B (création complète depuis la liste).
     *
     * @param string $purpose
     *
     * @return void
     */
    private function render_qz_modal_import_ai_list( $purpose ) {
        $formations     = $this->get_qz_available_formations();
        $purpose_labels = $this->get_quiz_purpose_labels();
        $edit_base_url  = $this->qz_admin_url( $purpose, array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT ) );
        ?>
        <div id="acdc-qz-modal-import-ai-list" class="acdc-qz-modal" hidden aria-modal="true" role="dialog" aria-labelledby="acdc-qz-import-list-title">
            <div class="acdc-qz-modal-overlay" data-close></div>
            <div class="acdc-qz-modal-dialog acdc-qz-modal-dialog-large">

                <!-- EN-TÊTE -->
                <div class="acdc-qz-modal-header">
                    <h2 id="acdc-qz-import-list-title">⬆ <?php esc_html_e( 'Importer un quiz généré par IA', 'acdc-formation-saas' ); ?></h2>
                    <button type="button" class="acdc-qz-modal-close" data-close aria-label="<?php esc_attr_e( 'Fermer', 'acdc-formation-saas' ); ?>">×</button>
                </div>

                <!-- ÉTAPE 1 : PARAMÉTRAGE + UPLOAD -->
                <div class="acdc-qz-modal-body acdc-qz-import-step" id="acdc-qz-import-list-step-1">

                    <label for="acdc-qz-import-list-formation">
                        <span class="acdc-required"><?php esc_html_e( 'Formation rattachée', 'acdc-formation-saas' ); ?></span>
                        <select id="acdc-qz-import-list-formation">
                            <option value="0">— <?php esc_html_e( 'Sélectionner une formation', 'acdc-formation-saas' ); ?> —</option>
                            <?php foreach ( $formations as $f ) : ?>
                                <option value="<?php echo esc_attr( (int) $f->id ); ?>">
                                    <?php echo esc_html( $this->format_qz_formation_label( $f ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label for="acdc-qz-import-list-purpose">
                        <span class="acdc-required"><?php esc_html_e( 'Type de quiz', 'acdc-formation-saas' ); ?></span>
                        <select id="acdc-qz-import-list-purpose">
                            <?php foreach ( $purpose_labels as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $purpose ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label for="acdc-qz-import-list-file">
                        <span class="acdc-required"><?php esc_html_e( 'Fichier JSON', 'acdc-formation-saas' ); ?></span>
                        <div class="acdc-qz-import-dropzone" id="acdc-qz-import-list-dropzone">
                            <div class="acdc-qz-import-dropzone-inner">
                                <span class="acdc-qz-import-dropzone-icon">📂</span>
                                <span class="acdc-qz-import-dropzone-label"><?php esc_html_e( 'Glissez votre fichier ici ou cliquez pour sélectionner', 'acdc-formation-saas' ); ?></span>
                                <span class="acdc-qz-import-dropzone-hint"><?php esc_html_e( 'Fichier .json uniquement — généré via le prompt officiel ACDC', 'acdc-formation-saas' ); ?></span>
                                <span class="acdc-qz-import-dropzone-filename" id="acdc-qz-import-list-filename" hidden></span>
                            </div>
                            <input type="file" id="acdc-qz-import-list-file" accept=".json,application/json" style="position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;" />
                        </div>
                    </label>

                    <label for="acdc-qz-import-list-timer">
                        <span><?php esc_html_e( 'Timer appliqué à toutes les questions (secondes)', 'acdc-formation-saas' ); ?></span>
                        <input type="number" id="acdc-qz-import-list-timer" value="20" min="10" max="300" step="5" />
                    </label>

                    <div class="acdc-qz-import-error" id="acdc-qz-import-list-error-1" hidden></div>
                </div>

                <!-- ÉTAPE 2 : APERÇU -->
                <div class="acdc-qz-modal-body acdc-qz-import-step" id="acdc-qz-import-list-step-2" hidden>
                    <div class="acdc-qz-import-summary" id="acdc-qz-import-list-summary"></div>
                    <div class="acdc-qz-import-warnings" id="acdc-qz-import-list-warnings" hidden></div>
                    <div class="acdc-qz-import-objectives-block" id="acdc-qz-import-list-objectives" hidden></div>
                    <div class="acdc-qz-import-preview-list" id="acdc-qz-import-list-preview"></div>
                </div>

                <!-- PIED -->
                <div class="acdc-qz-modal-footer">
                    <div id="acdc-qz-import-list-footer-1">
                        <button type="button" class="acdc-button acdc-button-soft" data-close>
                            <?php esc_html_e( 'Annuler', 'acdc-formation-saas' ); ?>
                        </button>
                        <button type="button" class="acdc-button acdc-button-primary" id="acdc-qz-import-list-analyze-btn" disabled>
                            <span class="acdc-qz-import-btn-label"><?php esc_html_e( 'Analyser le fichier', 'acdc-formation-saas' ); ?></span>
                            <span class="acdc-qz-import-btn-spinner" hidden>⏳</span>
                        </button>
                    </div>
                    <div id="acdc-qz-import-list-footer-2" hidden>
                        <button type="button" class="acdc-button acdc-button-soft" id="acdc-qz-import-list-back-btn">
                            &larr; <?php esc_html_e( 'Corriger le fichier', 'acdc-formation-saas' ); ?>
                        </button>
                        <button type="button" class="acdc-button acdc-button-primary" id="acdc-qz-import-list-confirm-btn">
                            <span class="acdc-qz-import-btn-label">✅ <?php esc_html_e( 'Confirmer et créer le quiz', 'acdc-formation-saas' ); ?></span>
                            <span class="acdc-qz-import-btn-spinner" hidden>⏳</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
        <script type="text/javascript">
        (function($) {
            if (typeof window._acdcQzImportListInit !== 'undefined') { return; }
            window._acdcQzImportListInit = true;

            var ajaxUrl = (typeof acdcQzEditor !== 'undefined') ? acdcQzEditor.ajaxUrl : '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
            var nonce   = '<?php echo esc_js( wp_create_nonce( 'acdc_of_qz_import' ) ); ?>';
            var editBase = <?php echo wp_json_encode( $edit_base_url ); ?>;
            var jsonB64  = '';

            /* --- Activation du bouton Analyser --- */
            function refreshAnalyzeBtn() {
                var hasFile      = !!($('#acdc-qz-import-list-file')[0] && $('#acdc-qz-import-list-file')[0].files && $('#acdc-qz-import-list-file')[0].files[0]);
                var hasFormation = parseInt($('#acdc-qz-import-list-formation').val(), 10) > 0;
                $('#acdc-qz-import-list-analyze-btn').prop('disabled', !(hasFile && hasFormation));
            }

            $(document).on('change', '#acdc-qz-import-list-file', function() {
                var f = this.files && this.files[0];
                if (f) { $('#acdc-qz-import-list-filename').text(f.name).removeAttr('hidden'); }
                else   { $('#acdc-qz-import-list-filename').attr('hidden', 'hidden'); }
                refreshAnalyzeBtn();
            });
            $(document).on('change', '#acdc-qz-import-list-formation', function() { refreshAnalyzeBtn(); });

            /* --- Drag & drop cosmétique --- */
            $(document).on('dragover', '#acdc-qz-import-list-dropzone', function(e) {
                e.preventDefault(); $(this).addClass('is-dragover');
            });
            $(document).on('dragleave drop', '#acdc-qz-import-list-dropzone', function() {
                $(this).removeClass('is-dragover');
            });

            /* --- Analyser --- */
            $(document).on('click', '#acdc-qz-import-list-analyze-btn', function() {
                var fileInput = document.getElementById('acdc-qz-import-list-file');
                if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                    showErr('<?php echo esc_js( __( 'Sélectionnez un fichier JSON.', 'acdc-formation-saas' ) ); ?>'); return;
                }
                var timer = parseInt($('#acdc-qz-import-list-timer').val(), 10) || 20;
                setLoading('#acdc-qz-import-list-analyze-btn', true);
                hideErr();

                var fd = new FormData();
                fd.append('action',       'acdc_of_qz_import_preview');
                fd.append('nonce',        nonce);
                fd.append('quiz_id',      0);
                fd.append('timer_global', timer);
                fd.append('json_file',    fileInput.files[0]);

                $.ajax({ url: ajaxUrl, type: 'POST', data: fd, processData: false, contentType: false,
                    success: function(resp) {
                        setLoading('#acdc-qz-import-list-analyze-btn', false);
                        if (!resp.success) { showErr(resp.data && resp.data.message ? resp.data.message : '<?php echo esc_js( __( 'Erreur.', 'acdc-formation-saas' ) ); ?>'); return; }
                        jsonB64 = resp.data.json_b64;
                        renderPreview(resp.data);
                        showStep(2);
                    },
                    error: function() {
                        setLoading('#acdc-qz-import-list-analyze-btn', false);
                        showErr('<?php echo esc_js( __( 'Erreur réseau.', 'acdc-formation-saas' ) ); ?>');
                    }
                });
            });

            /* --- Retour --- */
            $(document).on('click', '#acdc-qz-import-list-back-btn', function() { showStep(1); });

            /* --- Confirmer --- */
            $(document).on('click', '#acdc-qz-import-list-confirm-btn', function() {
                var formationId = parseInt($('#acdc-qz-import-list-formation').val(), 10) || 0;
                var purpose     = $('#acdc-qz-import-list-purpose').val();
                var timer       = parseInt($('#acdc-qz-import-list-timer').val(), 10) || 20;
                if (formationId <= 0) { alert('<?php echo esc_js( __( 'Sélectionnez une formation.', 'acdc-formation-saas' ) ); ?>'); return; }
                setLoading('#acdc-qz-import-list-confirm-btn', true);

                $.post(ajaxUrl, {
                    action:       'acdc_of_qz_import_confirm',
                    nonce:        nonce,
                    quiz_id:      0,
                    formation_id: formationId,
                    purpose:      purpose,
                    timer_global: timer,
                    json_b64:     jsonB64
                }, function(resp) {
                    setLoading('#acdc-qz-import-list-confirm-btn', false);
                    if (!resp.success) {
                        alert(resp.data && resp.data.message ? resp.data.message : '<?php echo esc_js( __( 'Erreur.', 'acdc-formation-saas' ) ); ?>');
                        return;
                    }
                    /* Redirection vers l'éditeur du quiz créé */
                    var quizId = parseInt(resp.data.quiz_id, 10);
                    if (quizId > 0) {
                        var url = editBase + '&quiz_id=' + quizId;
                        window.location.href = url;
                    } else {
                        window.location.reload();
                    }
                });
            });

            /* --- Helpers --- */
            function showStep(n) {
                if (n === 1) {
                    $('#acdc-qz-import-list-step-1').removeAttr('hidden');
                    $('#acdc-qz-import-list-step-2').attr('hidden', 'hidden');
                    $('#acdc-qz-import-list-footer-1').removeAttr('hidden');
                    $('#acdc-qz-import-list-footer-2').attr('hidden', 'hidden');
                    /* Sécurité : Confirmer désactivé quand on revient en step 1 */
                    $('#acdc-qz-import-list-confirm-btn').prop('disabled', true);
                } else {
                    $('#acdc-qz-import-list-step-1').attr('hidden', 'hidden');
                    $('#acdc-qz-import-list-step-2').removeAttr('hidden');
                    $('#acdc-qz-import-list-footer-1').attr('hidden', 'hidden');
                    $('#acdc-qz-import-list-footer-2').removeAttr('hidden');
                    /* Confirmer activé seulement si jsonB64 est bien défini */
                    $('#acdc-qz-import-list-confirm-btn').prop('disabled', !jsonB64);
                }
            }
            function showErr(msg) { $('#acdc-qz-import-list-error-1').text(msg).removeAttr('hidden'); }
            function hideErr()    { $('#acdc-qz-import-list-error-1').attr('hidden', 'hidden').text(''); }
            function setLoading(sel, on) {
                var $b = $(sel);
                $b.find('.acdc-qz-import-btn-label').toggle(!on);
                $b.find('.acdc-qz-import-btn-spinner').toggle(on);
                $b.prop('disabled', on);
            }

            function renderPreview(data) {
                var questions  = data.questions  || [];
                var warnings   = data.warnings   || [];
                var timer      = data.timer      || 20;
                var objectives = [];

                /* Extraire les objectifs depuis le JSON (décodé en preview dry_run) */
                questions.forEach(function(q) {
                    if (q.objective_ref && objectives.indexOf(q.objective_ref) < 0) {
                        objectives.push(q.objective_ref);
                    }
                });

                var valid   = 0;
                var warned  = 0;
                var skipped = data.skipped || 0;
                questions.forEach(function(q) {
                    if (q.skip)                              { skipped++; }
                    else if (q.warnings && q.warnings.length){ warned++; valid++; }
                    else                                     { valid++; }
                });

                /* Bandeau récapitulatif */
                var summary = '<div class="acdc-qz-import-summary-row">'
                    + '<span class="acdc-qz-import-pill acdc-qz-import-pill-ok">✅ ' + valid + ' <?php echo esc_js( __( 'question(s)', 'acdc-formation-saas' ) ); ?></span>'
                    + (objectives.length > 0 ? '<span class="acdc-qz-import-pill acdc-qz-import-pill-obj">🎯 ' + objectives.length + ' <?php echo esc_js( __( 'objectif(s)', 'acdc-formation-saas' ) ); ?></span>' : '')
                    + (warned  > 0 ? '<span class="acdc-qz-import-pill acdc-qz-import-pill-warn">⚠ ' + warned  + ' <?php echo esc_js( __( 'avert.', 'acdc-formation-saas' ) ); ?></span>' : '')
                    + (skipped > 0 ? '<span class="acdc-qz-import-pill acdc-qz-import-pill-err">✗ '  + skipped + ' <?php echo esc_js( __( 'ignorée(s)', 'acdc-formation-saas' ) ); ?></span>' : '')
                    + '<span class="acdc-qz-import-pill acdc-qz-import-pill-timer">⏱ ' + timer + ' s</span>'
                    + '</div>';
                $('#acdc-qz-import-list-summary').html(summary);

                /* Avertissements globaux */
                if (warnings.length) {
                    var whtml = '<ul>';
                    warnings.forEach(function(w) { whtml += '<li>' + escHtml(w) + '</li>'; });
                    whtml += '</ul>';
                    $('#acdc-qz-import-list-warnings').html(whtml).removeAttr('hidden');
                } else { $('#acdc-qz-import-list-warnings').attr('hidden', 'hidden'); }

                /* Bloc objectifs (liste des refs détectées) */
                if (objectives.length) {
                    var ohtml = '<div class="acdc-qz-import-obj-title"><?php echo esc_js( __( 'Objectifs pédagogiques détectés', 'acdc-formation-saas' ) ); ?></div><ul>';
                    objectives.forEach(function(r) { ohtml += '<li>🎯 ' + escHtml(r) + '</li>'; });
                    ohtml += '</ul>';
                    $('#acdc-qz-import-list-objectives').html(ohtml).removeAttr('hidden');
                } else { $('#acdc-qz-import-list-objectives').attr('hidden', 'hidden'); }

                /* Questions */
                var typeLabels = { qcm_single:'QCM', qcm_multiple:'Multi', true_false:'V/F', open_text:'Ouvert', poll:'Sondage', puzzle:'Puzzle' };
                var html = '';
                questions.forEach(function(q) {
                    var rowCls = 'acdc-qz-import-qrow';
                    if (q.skip)                              { rowCls += ' acdc-qz-import-qrow-error'; }
                    else if (q.warnings && q.warnings.length){ rowCls += ' acdc-qz-import-qrow-warn'; }
                    var tl = typeLabels[q.type] || q.type;
                    html += '<div class="' + rowCls + '">';
                    html += '<div class="acdc-qz-import-qrow-head">';
                    html += '<span class="acdc-qz-import-qnum">' + q.num + '</span>';
                    html += '<span class="acdc-qz-import-qtype acdc-qz-import-qtype-' + escAttr(q.type) + '">' + escHtml(tl) + '</span>';
                    html += '<span class="acdc-qz-import-qtitle">' + escHtml(q.title) + '</span>';
                    if (q.objective_ref) { html += '<span class="acdc-qz-import-qobj">🎯 ' + escHtml(q.objective_ref) + '</span>'; }
                    html += '</div>';
                    if (q.description) { html += '<div class="acdc-qz-import-qdesc">' + escHtml(q.description) + '</div>'; }
                    if (q.answers && q.answers.length) {
                        html += '<ul class="acdc-qz-import-qanswers">';
                        q.answers.forEach(function(a) {
                            var icon = a.is_correct ? '✅' : (q.type === 'poll' ? '—' : '❌');
                            html += '<li class="' + (a.is_correct ? 'is-correct' : '') + '">';
                            html += '<span class="acdc-qz-import-answer-icon">' + icon + '</span><span>' + escHtml(a.text) + '</span>';
                            if (a.feedback_text) { html += '<em class="acdc-qz-import-feedback"> — ' + escHtml(a.feedback_text) + '</em>'; }
                            html += '</li>';
                        });
                        html += '</ul>';
                    }
                    if (q.expected_answer) { html += '<div class="acdc-qz-import-expected"><strong><?php echo esc_js( __( 'Réponse de référence :', 'acdc-formation-saas' ) ); ?></strong> ' + escHtml(q.expected_answer) + '</div>'; }
                    if (q.warnings && q.warnings.length) {
                        html += '<ul class="acdc-qz-import-qwarnings">';
                        q.warnings.forEach(function(w) { html += '<li>⚠ ' + escHtml(w) + '</li>'; });
                        html += '</ul>';
                    }
                    html += '</div>';
                });
                $('#acdc-qz-import-list-preview').html(html);
            }

            function escHtml(s) {
                return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }
            function escAttr(s) { return String(s).replace(/[^a-z0-9_-]/gi,''); }

        }(jQuery));
        </script>
        <?php
    }
}
