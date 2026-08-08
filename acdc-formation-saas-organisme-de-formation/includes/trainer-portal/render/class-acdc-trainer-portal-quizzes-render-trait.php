<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * ACDC 3.21.03.2-a — Vue "Mes quiz" dans le portail formateur (lecture seule).
 *
 * Ce trait fournit le rendu de la vue ?view=quizzes accessible depuis la sidebar
 * du portail formateur. Filtrage : le formateur voit les quiz dont la formation
 * est animée par lui via au moins une session.
 *
 * Étape 3.21.03.2-a : LECTURE SEULE. Pas de bouton créer, pas de bouton envoyer.
 * Ces actions arrivent en 3.21.03.2-b.
 *
 * @package ACDC_Formation_SAAS
 * @since 3.21.03.2-a
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Trainer_Portal_Quizzes_Render_Trait {

    /**
     * Point d'entrée du module : injecte l'onglet et le handler de rendu.
     * Appelé depuis register_quizzes_module_hooks() dans le Actions trait quizzes.
     */
    public function register_trainer_portal_quizzes_hooks() {
        add_filter( 'acdc_trainer_portal_navigation_tabs', array( $this, 'inject_quizzes_tab_in_trainer_portal' ), 10, 2 );
        add_filter( 'acdc_trainer_portal_internal_views',  array( $this, 'register_quizzes_view_in_trainer_portal' ), 10, 2 );
        add_filter( 'acdc_trainer_portal_render_unknown_view', array( $this, 'render_quizzes_view_in_trainer_portal' ), 10, 3 );
    }

    /**
     * Injecte l'onglet "Mes quiz" dans la sidebar du portail formateur.
     * Position : entre 'sessions' et 'library' (logique pédagogique : sessions → quiz → bibliothèque).
     * Visibilité : seulement si le formateur a la permission view_own_quizzes.
     *
     * @param array  $tabs    Tableau des onglets existants.
     * @param object $account Objet compte du formateur connecté.
     * @return array
     */
    public function inject_quizzes_tab_in_trainer_portal( $tabs, $account ) {
        if ( ! is_array( $tabs ) || ! is_object( $account ) ) {
            return $tabs;
        }
        $trainer_id = isset( $account->trainer_id ) ? (int) $account->trainer_id : 0;
        if ( $trainer_id <= 0 ) {
            return $tabs;
        }
        if ( ! $this->trainer_can( $trainer_id, 'view_own_quizzes' ) ) {
            return $tabs;
        }

        $new_tab = array(
            'label' => 'Mes quiz',
            'url'   => $this->trainer_portal_page_url( 'quizzes' ),
        );
        $results_tab = array(
            'label' => 'Résultats',
            'url'   => $this->trainer_portal_page_url( 'results' ),
        );

        // Insertion entre 'sessions' et 'library' (préserve l'ordre)
        $reordered = array();
        foreach ( $tabs as $key => $tab ) {
            $reordered[ $key ] = $tab;
            if ( 'sessions' === $key ) {
                $reordered['quizzes'] = $new_tab;
                $reordered['results'] = $results_tab;
            }
        }
        // Si 'sessions' n'était pas dans le tableau (cas exotique), on ajoute en queue.
        if ( ! isset( $reordered['quizzes'] ) ) {
            $reordered['quizzes'] = $new_tab;
            $reordered['results'] = $results_tab;
        }
        return $reordered;
    }

    /**
     * Déclare 'quizzes' comme vue interne légitime du dispatcher.
     */
    public function register_quizzes_view_in_trainer_portal( $views, $account ) {
        if ( ! is_array( $views ) ) {
            return $views;
        }
        $views[] = 'quizzes';
        return $views;
    }

    /**
     * Rend la vue 'quizzes' (Mes quiz) dans le portail formateur.
     * Appelé via le filtre acdc_trainer_portal_render_unknown_view.
     *
     * @param string|null $body    Body courant (null si pas encore rempli par un autre module).
     * @param string      $view    Nom de la vue demandée.
     * @param object      $account Objet compte formateur.
     * @return string|null
     */
    public function render_quizzes_view_in_trainer_portal( $body, $view, $account ) {
        if ( 'quizzes' !== $view ) {
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

        // ACDC 3.21.03.2-b — Aiguillage liste / détail selon ?quiz=ID
        $quiz_id = isset( $_GET['quiz'] ) ? absint( wp_unslash( $_GET['quiz'] ) ) : 0;
        if ( $quiz_id > 0 ) {
            return $this->render_trainer_portal_quiz_detail( $trainer_id, $quiz_id );
        }

        return $this->render_trainer_portal_quizzes_list( $trainer_id );
    }

    /**
     * Rendu lecture seule de la liste des quiz du formateur.
     */
    private function render_trainer_portal_quizzes_list( $trainer_id ) {
        // Onglet de finalité courant (live | positioning | assessment | '')
        $current_purpose = isset( $_GET['purpose'] ) ? sanitize_key( wp_unslash( $_GET['purpose'] ) ) : '';
        $allowed = array( '', 'live', 'positioning', 'diagnostic', 'assessment' );
        if ( ! in_array( $current_purpose, $allowed, true ) ) {
            $current_purpose = '';
        }

        $args = array(
            'only_current' => true,
            'limit'        => 50,
            'orderby'      => 'updated_at',
            'order'        => 'DESC',
        );
        if ( '' !== $current_purpose ) {
            $args['purpose'] = $current_purpose;
        }
        $quizzes = $this->get_qz_quizzes_for_trainer( $trainer_id, $args );

        // Compteurs par finalité (pour les badges des onglets)
        $count_all = $this->count_qz_quizzes_for_trainer( $trainer_id, array( 'only_current' => true ) );
        $count_live = $this->count_qz_quizzes_for_trainer( $trainer_id, array( 'only_current' => true, 'purpose' => 'live' ) );
        $count_pos = $this->count_qz_quizzes_for_trainer( $trainer_id, array( 'only_current' => true, 'purpose' => 'positioning' ) );
        /* ACDC 3.25.167 — L'évaluation diagnostique était acceptée en paramètre d'URL
           mais aucun onglet ne la proposait : le formateur ne pouvait pas y accéder. */
        $count_diag = $this->count_qz_quizzes_for_trainer( $trainer_id, array( 'only_current' => true, 'purpose' => 'diagnostic' ) );
        $count_ass = $this->count_qz_quizzes_for_trainer( $trainer_id, array( 'only_current' => true, 'purpose' => 'assessment' ) );

        $base_url = $this->trainer_portal_page_url( 'quizzes' );

        ob_start();
        $can_create = $this->trainer_can( $trainer_id, 'create_quiz' );
        ?>
        <div class="acdc-trainer-portal-quizzes">
            <div class="acdc-trainer-portal-quizzes-header">
                <div class="acdc-trainer-portal-quizzes-header-text">
                    <h1>Mes quiz</h1>
                    <p class="acdc-trainer-portal-quizzes-subtitle">
                        Quiz, tests de positionnement et évaluations rattachés aux formations que vous animez.
                    </p>
                </div>
                <?php if ( $can_create ) : ?>
                    <div class="acdc-trainer-portal-quizzes-header-actions">
                        <button type="button" class="acdc-button acdc-button-primary" data-acdc-tp-modal-open="acdc-tp-quiz-create">
                            + Créer un quiz
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <?php
            // Affichage des notices (succès/erreur post-action) renvoyées par les handlers admin-post.
            $notice_code = isset( $_GET['acdc_qz_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['acdc_qz_notice'] ) ) : '';
            $notice_type = isset( $_GET['acdc_qz_notice_type'] ) ? sanitize_key( wp_unslash( $_GET['acdc_qz_notice_type'] ) ) : 'success';
            if ( '' !== $notice_code ) :
                $notice_label = $this->trainer_portal_quiz_notice_label( $notice_code );
            ?>
                <div class="acdc-trainer-portal-quiz-notice acdc-trainer-portal-quiz-notice-<?php echo esc_attr( $notice_type ); ?>">
                    <?php echo esc_html( $notice_label ); ?>
                </div>
            <?php endif; ?>

            <nav class="acdc-trainer-portal-quizzes-tabs" role="tablist" aria-label="Filtrer par finalité">
                <a class="acdc-trainer-portal-quizzes-tab <?php echo '' === $current_purpose ? 'is-active' : ''; ?>"
                   href="<?php echo esc_url( $base_url ); ?>"
                   role="tab" aria-selected="<?php echo '' === $current_purpose ? 'true' : 'false'; ?>">
                    Tous <span class="acdc-trainer-portal-quizzes-count"><?php echo (int) $count_all; ?></span>
                </a>
                <a class="acdc-trainer-portal-quizzes-tab <?php echo 'live' === $current_purpose ? 'is-active' : ''; ?>"
                   href="<?php echo esc_url( add_query_arg( 'purpose', 'live', $base_url ) ); ?>"
                   role="tab" aria-selected="<?php echo 'live' === $current_purpose ? 'true' : 'false'; ?>">
                    Quiz live <span class="acdc-trainer-portal-quizzes-count"><?php echo (int) $count_live; ?></span>
                </a>
                <a class="acdc-trainer-portal-quizzes-tab <?php echo 'positioning' === $current_purpose ? 'is-active' : ''; ?>"
                   href="<?php echo esc_url( add_query_arg( 'purpose', 'positioning', $base_url ) ); ?>"
                   role="tab" aria-selected="<?php echo 'positioning' === $current_purpose ? 'true' : 'false'; ?>">
                    Tests de positionnement <span class="acdc-trainer-portal-quizzes-count"><?php echo (int) $count_pos; ?></span>
                </a>
                <a class="acdc-trainer-portal-quizzes-tab <?php echo 'diagnostic' === $current_purpose ? 'is-active' : ''; ?>"
                   href="<?php echo esc_url( add_query_arg( 'purpose', 'diagnostic', $base_url ) ); ?>"
                   role="tab" aria-selected="<?php echo 'diagnostic' === $current_purpose ? 'true' : 'false'; ?>">
                    Évaluations diagnostiques <span class="acdc-trainer-portal-quizzes-count"><?php echo (int) $count_diag; ?></span>
                </a>
                <a class="acdc-trainer-portal-quizzes-tab <?php echo 'assessment' === $current_purpose ? 'is-active' : ''; ?>"
                   href="<?php echo esc_url( add_query_arg( 'purpose', 'assessment', $base_url ) ); ?>"
                   role="tab" aria-selected="<?php echo 'assessment' === $current_purpose ? 'true' : 'false'; ?>">
                    Évaluations des acquis <span class="acdc-trainer-portal-quizzes-count"><?php echo (int) $count_ass; ?></span>
                </a>
            </nav>

            <?php if ( empty( $quizzes ) ) : ?>
                <div class="acdc-trainer-portal-quizzes-empty">
                    <p>
                        <?php
                        if ( '' === $current_purpose ) {
                            esc_html_e( "Aucun quiz n'est rattaché à vos formations pour le moment.", 'acdc-formation-saas' );
                        } else {
                            esc_html_e( "Aucun quiz de cette finalité n'est rattaché à vos formations.", 'acdc-formation-saas' );
                        }
                        ?>
                    </p>
                    <?php if ( $can_create ) : ?>
                        <p class="acdc-trainer-portal-quizzes-hint">
                            Cliquez sur <strong>« + Créer un quiz »</strong> en haut à droite pour démarrer votre premier quiz.
                        </p>
                    <?php else : ?>
                        <p class="acdc-trainer-portal-quizzes-hint">
                            Vous n'avez pas la permission de créer des quiz. Contactez l'administrateur de l'organisme.
                        </p>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="acdc-trainer-portal-quizzes-grid">
                    <?php foreach ( $quizzes as $q ) : ?>
                        <?php $this->render_trainer_portal_quiz_card( $q ); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ( $can_create ) : $this->render_trainer_portal_quiz_create_modal( $trainer_id, $current_purpose ); ?>
            <?php $this->render_trainer_portal_quizzes_modal_js(); ?>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Carte individuelle d'un quiz dans la liste.
     */
    private function render_trainer_portal_quiz_card( $q ) {
        $purpose_labels = array(
            'live'        => 'Quiz live',
            'positioning' => 'Test de positionnement',
            'diagnostic'  => 'Évaluation diagnostique',
            'assessment'  => 'Évaluation des acquis',
        );
        $status_labels = array(
            'draft'    => 'Brouillon',
            'active'   => 'Actif',
            'archived' => 'Archivé',
        );
        $purpose_label = isset( $purpose_labels[ $q->quiz_purpose ] ) ? $purpose_labels[ $q->quiz_purpose ] : (string) $q->quiz_purpose;
        $status_label  = isset( $status_labels[ $q->status ] ) ? $status_labels[ $q->status ] : (string) $q->status;
        $status_class  = 'acdc-trainer-portal-quiz-status-' . sanitize_html_class( (string) $q->status );

        // Lien d'ouverture : on pointe vers le détail du quiz dans le portail formateur.
        // Pour 3.21.03.2-a, le détail est juste un placeholder. Pour 3.21.03.2-b on y branchera l'éditeur.
        $detail_url = add_query_arg( array(
            'view' => 'quizzes',
            'quiz' => (int) $q->id,
        ), $this->trainer_portal_page_url( 'quizzes' ) );
        ?>
        <article class="acdc-trainer-portal-quiz-card">
            <header class="acdc-trainer-portal-quiz-card-header">
                <span class="acdc-trainer-portal-quiz-purpose"><?php echo esc_html( $purpose_label ); ?></span>
                <span class="acdc-trainer-portal-quiz-status <?php echo esc_attr( $status_class ); ?>">
                    <?php echo esc_html( $status_label ); ?>
                </span>
            </header>
            <h3 class="acdc-trainer-portal-quiz-title">
                <?php echo esc_html( $q->title ); ?>
                <?php if ( (int) $q->version_number > 1 ) : ?>
                    <span class="acdc-trainer-portal-quiz-version">v<?php echo (int) $q->version_number; ?></span>
                <?php endif; ?>
            </h3>
            <?php if ( ! empty( $q->is_locked ) ) : ?>
                <p class="acdc-trainer-portal-quiz-locked">🔒 Verrouillé</p>
            <?php endif; ?>
            <footer class="acdc-trainer-portal-quiz-card-footer">
                <a class="acdc-trainer-portal-quiz-link" href="<?php echo esc_url( $detail_url ); ?>">
                    Voir le détail →
                </a>
                <?php
                /* ACDC 3.25.167 — Le lancement en salle dépend de la MODALITÉ, pas de la
                   finalité. Cette carte testait la finalité « quiz live » : une évaluation
                   diagnostique ou des acquis réglée en salle n'offrait donc aucun bouton
                   de lancement au formateur, alors que l'écran d'administration, lui, en
                   proposait un. Cinquième et dernier endroit où les deux axes étaient
                   confondus. */
                $q_live_ready = isset( $q->delivery_mode ) && 'live_sync' === (string) $q->delivery_mode;
                ?>
                <?php if ( $q_live_ready ) : ?>
                    <?php if ( 'active' === (string) $q->status ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                            <input type="hidden" name="action"   value="acdc_of_qz_launch_live" />
                            <input type="hidden" name="quiz_id"  value="<?php echo (int) $q->id; ?>" />
                            <?php wp_nonce_field( 'acdc_of_qz_launch_live_' . (int) $q->id ); ?>
                            <button type="submit" class="acdc-button acdc-button-primary acdc-tp-quiz-launch-btn">
                                ▶ Lancer en live
                            </button>
                        </form>
                    <?php elseif ( 'draft' === (string) $q->status ) : ?>
                        <span class="acdc-tp-quiz-launch-hint">Activez ce quiz pour pouvoir le lancer en live.</span>
                    <?php endif; ?>
                <?php endif; ?>
            </footer>
        </article>
        <?php
    }

    /**
     * Page bloquée si le formateur n'a pas la permission view_own_quizzes.
     * Cohérent avec le pattern arbitrage 1b adopté en 3.20.92 sur Mes sessions.
     */
    private function render_trainer_portal_quizzes_access_denied() {
        ob_start();
        ?>
        <div class="acdc-trainer-portal-quizzes-blocked">
            <h1>Mes quiz</h1>
            <p>L'accès à cette page est restreint. Votre profil de permissions ne permet pas de consulter les quiz pour le moment.</p>
            <p>Contactez l'administrateur de l'organisme si vous pensez devoir y accéder.</p>
        </div>
        <?php
        return ob_get_clean();
    }
    /* ====================================================================
     * ACDC 3.21.03.2-b — Création d'un quiz depuis le portail formateur.
     * Réutilise le handler admin-post 'acdc_of_qz_save_quiz' avec un champ
     * caché _acdc_qz_origin=trainer_portal pour rediriger correctement.
     * ==================================================================== */

    /**
     * Modale de création d'un quiz depuis le portail formateur.
     */
    private function render_trainer_portal_quiz_create_modal( $trainer_id, $current_purpose ) {
        $purpose_default_map = array(
            'live'        => 'live',
            'positioning' => 'positioning',
            'diagnostic'  => 'diagnostic',
            'assessment'  => 'assessment',
        );
        $preselected_purpose = isset( $purpose_default_map[ $current_purpose ] ) ? $purpose_default_map[ $current_purpose ] : 'assessment';

        $formations = $this->get_qz_formations_for_trainer( $trainer_id );
        ?>
        <div id="acdc-tp-quiz-create" class="acdc-tp-modal" hidden role="dialog" aria-modal="true" aria-label="Créer un quiz">
            <div class="acdc-tp-modal-overlay" data-acdc-tp-modal-close></div>
            <div class="acdc-tp-modal-dialog">
                <header class="acdc-tp-modal-header">
                    <h2>Créer un quiz</h2>
                    <button type="button" class="acdc-tp-modal-close" data-acdc-tp-modal-close aria-label="Fermer">×</button>
                </header>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acdc-tp-modal-body">
                    <input type="hidden" name="action" value="acdc_of_qz_save_quiz" />
                    <input type="hidden" name="quiz_id" value="0" />
                    <input type="hidden" name="_acdc_qz_origin" value="trainer_portal" />
                    <?php wp_nonce_field( 'acdc_of_qz_save_quiz', '_acdc_qz_nonce' ); ?>

                    <p class="acdc-tp-field">
                        <label for="acdc-tp-quiz-title">
                            <span class="acdc-tp-required">Titre <span aria-hidden="true">*</span></span>
                        </label>
                        <input type="text" id="acdc-tp-quiz-title" name="title" required maxlength="200" autocomplete="off" />
                    </p>

                    <p class="acdc-tp-field">
                        <label for="acdc-tp-quiz-purpose">
                            <span class="acdc-tp-required">Finalité <span aria-hidden="true">*</span></span>
                        </label>
                        <select id="acdc-tp-quiz-purpose" name="quiz_purpose" required>
                            <option value="live" <?php selected( $preselected_purpose, 'live' ); ?>>Quiz live (animé en direct)</option>
                            <option value="positioning" <?php selected( $preselected_purpose, 'positioning' ); ?>>Test de positionnement (avant formation)</option>
                            <option value="diagnostic" <?php selected( $preselected_purpose, 'diagnostic' ); ?>>Évaluation diagnostique (début de formation)</option>
                            <option value="assessment" <?php selected( $preselected_purpose, 'assessment' ); ?>>Évaluation des acquis (fin de formation)</option>
                        </select>
                    </p>

                    <p class="acdc-tp-field">
                        <label for="acdc-tp-quiz-formation">
                            <span class="acdc-tp-required">Formation rattachée <span aria-hidden="true">*</span></span>
                        </label>
                        <select id="acdc-tp-quiz-formation" name="formation_id" required>
                            <option value="0">— Sélectionner —</option>
                            <?php foreach ( $formations as $f ) : ?>
                                <option value="<?php echo esc_attr( (int) $f->id ); ?>"><?php echo esc_html( $this->format_qz_formation_label( $f ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ( empty( $formations ) ) : ?>
                            <span class="acdc-tp-hint">Vous n'animez actuellement aucune session de formation. Contactez l'administrateur.</span>
                        <?php endif; ?>
                    </p>

                    <?php /* ACDC 3.25.167 — L'évaluation diagnostique se passe en salle au
                             début de la formation, comme l'évaluation des acquis à la fin :
                             les deux ouvrent le choix de la modalité, avec la salle par
                             défaut. L'asynchrone reste possible pour un rattrapage. */ ?>
                    <p class="acdc-tp-field" data-show-when-purpose="diagnostic,assessment">
                        <label for="acdc-tp-quiz-delivery-mode">Mode de passation</label>
                        <select id="acdc-tp-quiz-delivery-mode" name="delivery_mode">
                            <option value="live_sync">En salle, en direct (anti-triche)</option>
                            <option value="async_token">À distance, par e-mail (rattrapage)</option>
                        </select>
                    </p>

                    <footer class="acdc-tp-modal-footer">
                        <button type="button" class="acdc-button acdc-button-soft" data-acdc-tp-modal-close>Annuler</button>
                        <button type="submit" class="acdc-button acdc-button-primary">Créer et éditer</button>
                    </footer>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * JS minimal pour les modales du portail formateur (ouverture, fermeture, ESC).
     */
    private function render_trainer_portal_quizzes_modal_js() {
        ?>
        <script>
        (function() {
            function openModal(id) {
                var m = document.getElementById(id);
                if (!m) return;
                m.hidden = false;
                m.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                var first = m.querySelector('input, select, textarea, button');
                if (first) try { first.focus(); } catch(e) {}
            }
            function closeModal(m) {
                if (!m) return;
                m.hidden = true;
                m.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }
            document.addEventListener('click', function(e) {
                var trigger = e.target.closest('[data-acdc-tp-modal-open]');
                if (trigger) {
                    e.preventDefault();
                    openModal(trigger.getAttribute('data-acdc-tp-modal-open'));
                    return;
                }
                var closer = e.target.closest('[data-acdc-tp-modal-close]');
                if (closer) {
                    e.preventDefault();
                    closeModal(closer.closest('.acdc-tp-modal'));
                }
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' || e.key === 'Esc') {
                    var open = document.querySelector('.acdc-tp-modal:not([hidden])');
                    if (open) closeModal(open);
                }
            });
            var purposeSelect = document.getElementById('acdc-tp-quiz-purpose');
            if (purposeSelect) {
                function refreshDelivery() {
                    var fields = document.querySelectorAll('[data-show-when-purpose]');
                    for (var i = 0; i < fields.length; i++) {
                        // ACDC 3.25.167 — Plusieurs finalités peuvent partager un même champ,
                        // séparées par des virgules. L'égalité stricte n'en acceptait qu'une.
                        var want = (fields[i].getAttribute('data-show-when-purpose') || '').split(',');
                        fields[i].style.display = (want.indexOf(purposeSelect.value) !== -1) ? '' : 'none';
                    }
                    // Le champ masqué est quand même posté : on le remet sur la valeur
                    // cohérente avec la finalité, sinon un test de positionnement pouvait
                    // partir en « salle » avec la valeur laissée par un choix précédent.
                    var delivery = document.getElementById('acdc-tp-quiz-delivery-mode');
                    if (delivery) {
                        var inRoom = (purposeSelect.value === 'live'
                            || purposeSelect.value === 'diagnostic'
                            || purposeSelect.value === 'assessment');
                        delivery.value = inRoom ? 'live_sync' : 'async_token';
                    }
                }
                purposeSelect.addEventListener('change', refreshDelivery);
                refreshDelivery();
            }
        })();
        </script>
        <?php
    }

    /**
     * Vue détail d'un quiz côté portail formateur.
     */
    private function render_trainer_portal_quiz_detail( $trainer_id, $quiz_id ) {
        global $wpdb;
        $tbl_quizzes  = $this->get_qz_table( 'quizzes' );
        $tbl_sessions = $wpdb->prefix . 'acdc_of_sessions';

        // Tentative 1 : quiz visible si formation animée par le formateur via au moins une session
        $quiz = $wpdb->get_row( $wpdb->prepare(
            'SELECT q.* FROM ' . $tbl_quizzes . ' q WHERE q.id = %d AND EXISTS '
            . '(SELECT 1 FROM ' . $tbl_sessions . ' s WHERE s.formation_id = q.formation_id AND s.trainer_id = %d) LIMIT 1',
            $quiz_id, $trainer_id
        ) );

        // Tentative 2 (fallback) : si trainer_id pas encore propagé sur les sessions, on autorise
        // l'accès au quiz tant qu'il existe et que la formation existe. Cohérent avec
        // get_qz_quizzes_for_trainer() qui retombe sur la liste complète si le filtre est vide.
        // Garde-fou : on vérifie quand même que le formateur a bien la permission view_own_quizzes
        // (déjà fait par l'appelant) et qu'aucun quiz visible filtré n'existe pour ce formateur.
        if ( ! $quiz ) {
            $has_filtered_quizzes = (int) $wpdb->get_var( $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . $tbl_quizzes . ' q WHERE EXISTS '
                . '(SELECT 1 FROM ' . $tbl_sessions . ' s WHERE s.formation_id = q.formation_id AND s.trainer_id = %d) LIMIT 1',
                $trainer_id
            ) );
            if ( 0 === $has_filtered_quizzes ) {
                // Aucune session animée explicitement → mode fallback : on accepte tout quiz existant.
                $quiz = $wpdb->get_row( $wpdb->prepare(
                    'SELECT q.* FROM ' . $tbl_quizzes . ' q WHERE q.id = %d LIMIT 1',
                    $quiz_id
                ) );
            }
        }

        if ( ! $quiz ) {
            ob_start();
            ?>
            <div class="acdc-trainer-portal-quizzes-blocked">
                <h1>Quiz introuvable</h1>
                <p>Ce quiz n'existe pas, ou il n'est pas rattaché à une formation que vous animez.</p>
                <p><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->trainer_portal_page_url( 'quizzes' ) ); ?>">← Retour à la liste</a></p>
            </div>
            <?php
            return ob_get_clean();
        }

        $purpose_labels = array(
            'live'        => 'Quiz live',
            'positioning' => 'Test de positionnement',
            'diagnostic'  => 'Évaluation diagnostique',
            'assessment'  => 'Évaluation des acquis',
        );
        $status_labels = array(
            'draft'    => 'Brouillon',
            'active'   => 'Actif',
            'archived' => 'Archivé',
        );
        $purpose_label = isset( $purpose_labels[ $quiz->quiz_purpose ] ) ? $purpose_labels[ $quiz->quiz_purpose ] : (string) $quiz->quiz_purpose;
        $status_label  = isset( $status_labels[ $quiz->status ] ) ? $status_labels[ $quiz->status ] : (string) $quiz->status;

        $questions = method_exists( $this, 'get_qz_questions_for_quiz' )
            ? $this->get_qz_questions_for_quiz( (int) $quiz->id )
            : array();

        $can_dispatch = $this->trainer_can( $trainer_id, 'dispatch_quiz' );
        $is_dispatch_eligible = ( 'active' === $quiz->status && in_array( $quiz->quiz_purpose, array( 'positioning', 'diagnostic', 'assessment' ), true ) && empty( $quiz->is_locked ) );

        ob_start();
        ?>
        <div class="acdc-trainer-portal-quiz-detail">
            <p class="acdc-trainer-portal-breadcrumb">
                <a href="<?php echo esc_url( $this->trainer_portal_page_url( 'quizzes' ) ); ?>">Mes quiz</a>
                <span aria-hidden="true">›</span>
                <span><?php echo esc_html( $quiz->title ); ?></span>
            </p>

            <?php
            $notice_code = isset( $_GET['acdc_qz_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['acdc_qz_notice'] ) ) : '';
            $notice_type = isset( $_GET['acdc_qz_notice_type'] ) ? sanitize_key( wp_unslash( $_GET['acdc_qz_notice_type'] ) ) : 'success';
            if ( '' !== $notice_code ) :
                $notice_label = $this->trainer_portal_quiz_notice_label( $notice_code );
            ?>
                <div class="acdc-trainer-portal-quiz-notice acdc-trainer-portal-quiz-notice-<?php echo esc_attr( $notice_type ); ?>">
                    <?php echo esc_html( $notice_label ); ?>
                </div>
            <?php endif; ?>

            <header class="acdc-trainer-portal-quiz-detail-header">
                <div>
                    <span class="acdc-trainer-portal-quiz-purpose"><?php echo esc_html( $purpose_label ); ?></span>
                    <h1><?php echo esc_html( $quiz->title ); ?></h1>
                    <p class="acdc-trainer-portal-quiz-detail-meta">
                        <span class="acdc-trainer-portal-quiz-status acdc-trainer-portal-quiz-status-<?php echo esc_attr( sanitize_html_class( $quiz->status ) ); ?>">
                            <?php echo esc_html( $status_label ); ?>
                        </span>
                        <?php if ( (int) $quiz->version_number > 1 ) : ?>
                            <span class="acdc-trainer-portal-quiz-version">v<?php echo (int) $quiz->version_number; ?></span>
                        <?php endif; ?>
                        <?php if ( ! empty( $quiz->is_locked ) ) : ?>
                            <span class="acdc-trainer-portal-quiz-locked">🔒 Verrouillé</span>
                        <?php endif; ?>
                        <span class="acdc-trainer-portal-quiz-detail-questions-count">
                            <?php echo (int) count( $questions ); ?> question<?php echo count( $questions ) > 1 ? 's' : ''; ?>
                        </span>
                    </p>
                </div>
                <?php if ( $can_dispatch && $is_dispatch_eligible ) : ?>
                    <div class="acdc-trainer-portal-quiz-detail-actions">
                        <button type="button" class="acdc-button acdc-button-primary" data-acdc-tp-modal-open="acdc-tp-quiz-dispatch">
                            ✉ Envoyer par e-mail
                        </button>
                    </div>
                <?php elseif ( $can_dispatch && ! $is_dispatch_eligible ) : ?>
                    <div class="acdc-trainer-portal-quiz-detail-actions">
                        <p class="acdc-trainer-portal-quiz-detail-disabled-hint">
                            <?php
                            if ( 'active' !== $quiz->status ) {
                                esc_html_e( "Le quiz doit être à l'état Actif pour être envoyé.", 'acdc-formation-saas' );
                            } elseif ( 'live' === $quiz->quiz_purpose ) {
                                esc_html_e( "Les quiz live ne se prêtent pas à l'envoi par e-mail.", 'acdc-formation-saas' );
                            } elseif ( ! empty( $quiz->is_locked ) ) {
                                esc_html_e( "Le quiz est verrouillé suite au premier envoi.", 'acdc-formation-saas' );
                            }
                            ?>
                        </p>
                    </div>
                <?php endif; ?>
            </header>

            <?php if ( ! empty( $quiz->description ) ) : ?>
                <section class="acdc-trainer-portal-quiz-detail-description">
                    <?php echo wp_kses_post( $quiz->description ); ?>
                </section>
            <?php endif; ?>

            <section class="acdc-trainer-portal-quiz-detail-questions">
                <h2>Questions</h2>
                <?php if ( empty( $questions ) ) : ?>
                    <p class="acdc-trainer-portal-quiz-detail-empty-questions">
                        Ce quiz ne contient pas encore de question. Pour ajouter ou modifier des questions, ouvrez le quiz depuis l'administration WordPress.
                    </p>
                <?php else : ?>
                    <ol class="acdc-trainer-portal-quiz-detail-question-list">
                        <?php foreach ( $questions as $idx => $q ) : ?>
                            <li class="acdc-trainer-portal-quiz-detail-question-item">
                                <strong><?php echo esc_html( $q->title ); ?></strong>
                                <span class="acdc-trainer-portal-quiz-detail-question-type">
                                    <?php echo esc_html( $this->trainer_portal_quiz_type_label( (string) $q->type ) ); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>

            <p class="acdc-trainer-portal-quiz-detail-hint">
                Pour modifier le contenu (titre, questions, paramètres), ouvrez le quiz depuis l'administration WordPress. La modification fine depuis le portail formateur sera disponible dans une prochaine version.
            </p>
        </div>

        <?php if ( $can_dispatch && $is_dispatch_eligible ) : ?>
            <?php $this->render_trainer_portal_quiz_dispatch_modal( $trainer_id, $quiz ); ?>
            <?php $this->render_trainer_portal_quizzes_modal_js(); ?>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Modale d'envoi async d'un quiz par e-mail (réutilise le handler existant).
     */
    private function render_trainer_portal_quiz_dispatch_modal( $trainer_id, $quiz ) {
        $learners = method_exists( $this, 'get_qz_learners_for_formation' )
            ? $this->get_qz_learners_for_formation( (int) $quiz->formation_id )
            : array();
        ?>
        <div id="acdc-tp-quiz-dispatch" class="acdc-tp-modal" hidden role="dialog" aria-modal="true" aria-label="Envoyer le quiz par e-mail">
            <div class="acdc-tp-modal-overlay" data-acdc-tp-modal-close></div>
            <div class="acdc-tp-modal-dialog acdc-tp-modal-dialog-wide">
                <header class="acdc-tp-modal-header">
                    <h2>Envoyer par e-mail</h2>
                    <button type="button" class="acdc-tp-modal-close" data-acdc-tp-modal-close aria-label="Fermer">×</button>
                </header>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acdc-tp-modal-body">
                    <input type="hidden" name="action" value="acdc_of_qz_send_async" />
                    <input type="hidden" name="quiz_id" value="<?php echo (int) $quiz->id; ?>" />
                    <input type="hidden" name="_acdc_qz_origin" value="trainer_portal" />
                    <?php wp_nonce_field( 'acdc_of_qz_send_async', '_acdc_qz_nonce' ); ?>

                    <p class="acdc-tp-field">
                        <strong>Quiz :</strong> <?php echo esc_html( $quiz->title ); ?>
                    </p>

                    <fieldset class="acdc-tp-field">
                        <legend><strong>Destinataires</strong></legend>
                        <?php if ( empty( $learners ) ) : ?>
                            <p class="acdc-tp-hint">
                                Aucun apprenant n'est inscrit à une session de cette formation.
                                Saisissez les destinataires manuellement ci-dessous.
                            </p>
                        <?php else : ?>
                            <div class="acdc-tp-learner-list">
                                <?php foreach ( $learners as $l ) :
                                    $email = isset( $l->email ) ? (string) $l->email : '';
                                    if ( '' === $email ) { continue; }
                                    $name = trim( ( $l->first_name ?? '' ) . ' ' . ( $l->last_name ?? '' ) );
                                ?>
                                    <label class="acdc-tp-learner-item">
                                        <input type="checkbox" name="recipients_learners[]" value="<?php echo (int) $l->id; ?>" checked />
                                        <span class="acdc-tp-learner-name"><?php echo esc_html( $name ); ?></span>
                                        <span class="acdc-tp-learner-email"><?php echo esc_html( $email ); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </fieldset>

                    <p class="acdc-tp-field">
                        <label for="acdc-tp-recipients-extra">
                            Destinataires supplémentaires (e-mails séparés par des virgules ou retours à la ligne)
                        </label>
                        <textarea id="acdc-tp-recipients-extra" name="recipients_extra" rows="2" placeholder="exemple1@domaine.fr, exemple2@domaine.fr"></textarea>
                    </p>

                    <p class="acdc-tp-field">
                        <label for="acdc-tp-expiry-days">
                            Durée de validité du lien (jours)
                        </label>
                        <select id="acdc-tp-expiry-days" name="expiry_days">
                            <?php
                            $default_days = ( 'positioning' === $quiz->quiz_purpose ) ? 7 : 14;
                            $options = array( 3, 5, 7, 10, 14, 21, 30 );
                            foreach ( $options as $d ) {
                                $sel = $d === $default_days ? ' selected' : '';
                                printf( '<option value="%d"%s>%d jours</option>', $d, $sel, $d );
                            }
                            ?>
                        </select>
                    </p>

                    <p class="acdc-tp-field">
                        <label>
                            <input type="checkbox" name="enable_reminder_j1" value="1" checked />
                            Envoyer une relance la veille de l'expiration
                        </label>
                    </p>

                    <footer class="acdc-tp-modal-footer">
                        <button type="button" class="acdc-button acdc-button-soft" data-acdc-tp-modal-close>Annuler</button>
                        <button type="submit" class="acdc-button acdc-button-primary">Envoyer maintenant</button>
                    </footer>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Libellé humain pour un type de question donné.
     */
    private function trainer_portal_quiz_type_label( $type ) {
        $labels = array(
            'qcm_single'   => 'QCM (1 réponse)',
            'qcm_multiple' => 'QCM (réponses multiples)',
            'true_false'   => 'Vrai / Faux',
            'puzzle'       => 'Puzzle (remise en ordre)',
            'open_text'    => 'Réponse libre',
            'poll'         => 'Sondage',
        );
        return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
    }

    /**
     * Libellé humain pour les codes de notice renvoyés par les handlers admin-post.
     *
     * Les handlers envoient soit :
     *   - un libellé humain déjà traduit (ex. "Quiz mis à jour.") via qz_redirect( ..., __('...') )
     *   - un slug court (ex. "feature_pending") qu'on traduit ici
     *
     * Heuristique : si la chaîne contient un espace ou de la ponctuation, c'est déjà un libellé
     * humain → on le retourne tel quel. Sinon, on applique le mapping slug → texte.
     */
    private function trainer_portal_quiz_notice_label( $code ) {
        $code = (string) $code;
        if ( '' === $code ) {
            return '';
        }
        // Si la chaîne contient un espace ou un caractère non-slug, c'est déjà un libellé humain
        if ( preg_match( '/[^a-z0-9_]/i', $code ) ) {
            return $code;
        }
        // Sinon, mapping slug court → libellé
        $labels = array(
            'quiz_created'        => "Quiz créé avec succès. Vous pouvez maintenant ajouter ses questions depuis l'administration.",
            'quiz_updated'        => 'Quiz mis à jour.',
            'quiz_archived'       => 'Quiz archivé.',
            'quiz_activated'      => 'Quiz activé.',
            'quiz_unpublished'    => 'Quiz repassé en brouillon.',
            'quiz_locked'         => 'Quiz verrouillé.',
            'dispatch_sent'       => "Quiz envoyé. Les apprenants vont recevoir l'invitation par e-mail.",
            'feature_pending'     => 'Cette fonctionnalité est en cours de développement.',
            'invalid_input'       => 'Données invalides. Vérifiez les champs marqués en rouge.',
            'permission_denied'   => "Vous n'avez pas la permission requise pour cette action.",
        );
        return isset( $labels[ $code ] ) ? $labels[ $code ] : ucfirst( str_replace( '_', ' ', $code ) );
    }

    /**
     * Liste des formations proposables au formateur dans la modale de création.
     *
     * Logique en 2 temps :
     * 1. On tente d'abord la liste filtrée : formations dont au moins une session a
     *    trainer_id = $trainer_id. C'est le cas idéal en multi-formateurs.
     * 2. Si cette liste est vide (cas d'un formateur unique qui n'a pas encore été
     *    explicitement assigné à des sessions, ou cas où trainer_id n'est pas encore
     *    backfillé sur les sessions), on retombe sur la liste complète des formations
     *    actives — comportement aligné sur ce que voit l'admin dans le back-office.
     *
     * Ce fallback est nécessaire parce que la colonne sessions.trainer_id existe
     * depuis 3.20.92 mais n'est pas toujours renseignée sur l'historique. Plutôt
     * que de laisser le sélecteur vide (et bloquer la création), on offre une
     * dégradation gracieuse : voir toutes les formations actives plutôt qu'aucune.
     */
    private function get_qz_formations_for_trainer( $trainer_id ) {
        global $wpdb;
        $trainer_id = (int) $trainer_id;
        if ( $trainer_id <= 0 ) {
            return array();
        }
        $tbl_formations = $wpdb->prefix . 'acdc_of_formations';
        $tbl_sessions   = $wpdb->prefix . 'acdc_of_sessions';

        // 1. Tentative filtrée par sessions animées
        $sql = 'SELECT DISTINCT f.* FROM ' . $tbl_formations . ' f '
             . 'INNER JOIN ' . $tbl_sessions . ' s ON s.formation_id = f.id '
             . 'WHERE s.trainer_id = %d AND f.is_active = 1 AND f.is_draft = 0 '
             . 'ORDER BY f.title ASC';
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $trainer_id ) );

        if ( ! empty( $rows ) ) {
            return $rows;
        }

        // 2. Fallback : toutes les formations actives (cohérent avec ce que voit l'admin)
        if ( method_exists( $this, 'get_qz_available_formations' ) ) {
            return $this->get_qz_available_formations();
        }
        return array();
    }
}
