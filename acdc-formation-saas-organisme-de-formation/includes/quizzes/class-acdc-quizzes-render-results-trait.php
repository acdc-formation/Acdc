<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * ACDC 3.21.04.1 — Écrans de résultats détaillés (admin).
 *
 * Vues fournies :
 *   - Liste des envois (sessions de dispatch)
 *   - Détail d'un envoi : tableau participants + onglets stats par question / par objectif
 *   - Détail individuel d'un participant : ses réponses question par question
 *   - Correction manuelle des réponses libres
 *   - Export CSV
 *
 * Ce trait est consommé par render_qz_extranet_screen() qui aiguille via ?view=results.
 *
 * @package ACDC_Formation_SAAS
 * @since 3.21.04.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Quizzes_Render_Results_Trait {

    /**
     * Point d'entrée — rend l'écran "Résultats" selon les paramètres GET.
     * Aiguillage en cascade :
     *   ?view=results                  → liste des envois
     *   ?view=results&session=ID       → détail de l'envoi (tableau participants)
     *   ?view=results&session=ID&tab=questions → vue agrégée par question
     *   ?view=results&session=ID&tab=objectives → vue agrégée par objectif
     *   ?view=results&participant=ID   → détail individuel d'un participant
     *
     * @param string $purpose Finalité courante (live/positioning/assessment), pour les liens retour.
     */
    public function render_qz_results_screen( $purpose ) {
        $session_id     = isset( $_GET['session'] ) ? absint( wp_unslash( $_GET['session'] ) ) : 0;
        $participant_id = isset( $_GET['participant'] ) ? absint( wp_unslash( $_GET['participant'] ) ) : 0;
        /* ACDC 3.21.04.1-hotfix5 — Renommage du paramètre interne pour éviter
           la collision avec le `tab` principal de la sidebar extranet. */
        $tab            = isset( $_GET['qz_subtab'] ) ? sanitize_key( wp_unslash( $_GET['qz_subtab'] ) ) : 'participants';

        if ( $participant_id > 0 ) {
            $this->render_qz_results_participant_detail( $participant_id, $purpose );
            return;
        }
        if ( $session_id > 0 ) {
            $this->render_qz_results_session_detail( $session_id, $tab, $purpose );
            return;
        }
        $this->render_qz_results_sessions_list( $purpose );
    }

    /**
     * Liste des envois (sessions de dispatch) — mise en page dashboard.
     * 3.21.04.1-hotfix1 : KPI cards en haut, filtres, tableau résumé en bas.
     * 3.21.04.1-hotfix2 : titre + filtres adaptés selon le tab Résultats utilisé
     *                     (transverse, ou spécifique à une finalité).
     */
    private function render_qz_results_sessions_list( $purpose ) {
        // Récupération du filtre purpose (depuis query string : ?purpose=live/positioning/assessment)
        // Si on vient du tab transverse "qz_results", $purpose est vide → on lit ?purpose=
        // Si on vient d'un tab spécifique (qz_results_live, qz_results_positioning, qz_results_assessment),
        // $purpose est imposé par le tab et on ne montre PAS le filtre finalité.
        $filter_purpose = isset( $_GET['purpose'] ) ? sanitize_key( wp_unslash( $_GET['purpose'] ) ) : (string) $purpose;
        $filter_status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

        // Si un purpose est imposé par le tab spécifique, il prime sur ?purpose=.
        if ( ! empty( $purpose ) ) {
            $filter_purpose = (string) $purpose;
        }

        /* ACDC 3.25.157 — Filtre par quiz (?qz_quiz=), posé par l'entrée de menu
           « Voir les résultats » de la liste des quiz. */
        $filter_quiz  = isset( $_GET['qz_quiz'] ) ? absint( wp_unslash( $_GET['qz_quiz'] ) ) : 0;
        $filter_quiz_title = '';
        if ( $filter_quiz > 0 ) {
            $filtered_quiz = $this->get_qz_quiz( $filter_quiz );
            if ( $filtered_quiz ) {
                $filter_quiz_title = (string) $filtered_quiz->title;
            } else {
                $filter_quiz = 0; // Quiz supprimé : on ne filtre pas dans le vide.
            }
        }

        // KPIs globaux (filtrés par finalité si on est sur un tab spécifique, et par quiz si demandé)
        $kpis = $this->get_qz_results_dashboard_kpis( array( 'purpose' => $filter_purpose, 'quiz_id' => $filter_quiz ) );

        // Liste des envois
        $args = array(
            'limit'   => 200,
            'orderby' => 'sent_at',
            'order'   => 'DESC',
        );
        if ( '' !== $filter_status ) {
            $args['status'] = $filter_status;
        }
        $sessions = $this->get_qz_dispatch_sessions( $args );

        if ( $filter_quiz > 0 ) {
            $sessions = array_values( array_filter( $sessions, function( $s ) use ( $filter_quiz ) {
                return ( (int) $s->quiz_id === $filter_quiz );
            } ) );
        }

        // Filtrer par purpose côté PHP (le SQL n'a pas de purpose direct sur sessions)
        if ( '' !== $filter_purpose ) {
            $sessions = array_values( array_filter( $sessions, function( $s ) use ( $filter_purpose ) {
                return ( isset( $s->quiz_purpose ) && $s->quiz_purpose === $filter_purpose );
            } ) );
        }

        // hotfix54 — Mode "Alertes uniquement" : sessions avec au moins 1 participant non complété.
        // Déclenché par ?qz_pending_only=1 (lien depuis le tableau de bord).
        $pending_only = ! empty( $_GET['qz_pending_only'] );
        if ( $pending_only ) {
            $sessions = array_values( array_filter( $sessions, function( $s ) {
                $total     = (int) ( $s->count_invited   ?? 0 );
                $completed = (int) ( $s->count_completed ?? 0 );
                return $total > 0 && $completed < $total;
            } ) );
        }

        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
        $is_transverse = ( self::ACDC_OF_QZ_TAB_RESULTS === $current_tab );
        // Tab spécifique = un des onglets Résultats par finalité (hors transverse).
        // ACDC 3.25.168 — Même omission de l'évaluation diagnostique qu'au-dessus.
        $is_purpose_specific = in_array( $current_tab, $this->qz_results_tabs(), true )
            && ! $is_transverse;

        // Titre et sous-titre adaptés au contexte
        $title    = 'Résultats des quiz';
        $subtitle = 'Centralisez les réponses et scores des sessions issues des quiz, tests de positionnement et évaluations.';
        if ( 'live' === $filter_purpose ) {
            $title    = 'Résultats — Quiz live';
            $subtitle = 'Sessions de quiz live, scores des participants et atteinte des objectifs pédagogiques.';
        } elseif ( 'positioning' === $filter_purpose ) {
            $title    = 'Résultats — Tests de positionnement';
            $subtitle = 'Niveau initial des apprenants détecté à l\'entrée en formation.';
        } elseif ( 'diagnostic' === $filter_purpose ) {
            $title    = 'Résultats — Évaluations diagnostiques';
            $subtitle = 'Niveau des apprenants en début de formation, point de départ de la mesure de progression.';
        } elseif ( 'assessment' === $filter_purpose ) {
            $title    = 'Résultats — Évaluations des acquis';
            $subtitle = 'Atteinte des objectifs pédagogiques en fin de formation (Qualiopi indicateur 12).';
        }

        ?>
        <header class="acdc-qz-results-hero">
            <h1 class="acdc-qz-results-hero-title"><?php echo esc_html( $title ); ?></h1>
            <p class="acdc-qz-results-hero-subline"><?php echo esc_html( $subtitle ); ?></p>
        </header>

        <?php
        /* ACDC 3.25.157 — Un écran filtré doit le dire, et offrir le retour au
           périmètre complet : sans cela les chiffres semblent contredire ceux du
           même écran atteint par l'onglet. */
        if ( $filter_quiz > 0 ) :
            $unfiltered_url = remove_query_arg( 'qz_quiz' );
        ?>
        <div class="acdc-qz-results-filter-banner" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:0 0 16px;padding:10px 14px;border:1px solid #e6ebf2;border-radius:8px;background:#f7f9fc;font-size:13px;">
            <span>Résultats limités au quiz <strong><?php echo esc_html( $filter_quiz_title ); ?></strong>.</span>
            <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $unfiltered_url ); ?>">Voir tous les quiz</a>
        </div>
        <?php endif; ?>

        <?php if ( $pending_only ) : ?>
        <div style="margin:0 0 18px;padding:12px 18px;background:#fff0f0;border:1.5px solid #f5c6c6;
                    border-radius:10px;display:flex;align-items:center;gap:12px;font-size:14px;color:#b91c1c;">
            <span style="font-size:18px">⚠</span>
            <span>
                <strong>Vue alertes uniquement</strong> —
                Seules les sessions avec des apprenants n’ayant pas encore terminé sont affichées.
                <a href="<?php echo esc_url( $this->portal_page_url( array( 'tab' => $current_tab ) ) ); ?>"
                   style="color:#b91c1c;font-weight:600;margin-left:8px;">Voir toutes les sessions →</a>
            </span>
        </div>
        <?php endif; ?>

        <?php if ( $is_transverse || $is_purpose_specific ) : ?>
            <form method="get" class="acdc-qz-results-filters">
                <?php
                $page_id = (int) get_option( 'acdc_of_extranet_dashboard_page_id', 0 );
                if ( $page_id > 0 ) : ?>
                    <input type="hidden" name="page_id" value="<?php echo (int) $page_id; ?>" />
                <?php endif; ?>
                <input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>" />

                <?php if ( $is_transverse ) : ?>
                    <label class="acdc-qz-results-filter-field">
                        <span>Finalité</span>
                        <select name="purpose">
                            <option value="">Toutes les finalités</option>
                            <option value="live" <?php selected( 'live', $filter_purpose ); ?>>Quiz live</option>
                            <option value="positioning" <?php selected( 'positioning', $filter_purpose ); ?>>Tests de positionnement</option>
                            <option value="diagnostic" <?php selected( 'diagnostic', $filter_purpose ); ?>>Évaluations diagnostiques</option>
                            <option value="assessment" <?php selected( 'assessment', $filter_purpose ); ?>>Évaluations des acquis</option>
                        </select>
                    </label>
                <?php endif; ?>

                <label class="acdc-qz-results-filter-field">
                    <span>Statut</span>
                    <select name="status">
                        <option value="">Tous les statuts</option>
                        <option value="sent" <?php selected( 'sent', $filter_status ); ?>>Envoyé</option>
                        <option value="in_progress" <?php selected( 'in_progress', $filter_status ); ?>>En cours</option>
                        <option value="completed" <?php selected( 'completed', $filter_status ); ?>>Terminé</option>
                    </select>
                </label>

                <button type="submit" class="acdc-button acdc-button-primary">Filtrer</button>
            </form>
        <?php endif; ?>

        <div class="acdc-qz-results-kpi-grid">
            <div class="acdc-qz-results-kpi-card">
                <div class="acdc-qz-results-kpi-value"><?php echo (int) $kpis['count_sessions']; ?></div>
                <div class="acdc-qz-results-kpi-label">Envois</div>
            </div>
            <div class="acdc-qz-results-kpi-card">
                <div class="acdc-qz-results-kpi-value"><?php echo (int) $kpis['count_participants_total']; ?></div>
                <div class="acdc-qz-results-kpi-label">Participants cumulés</div>
            </div>
            <div class="acdc-qz-results-kpi-card">
                <div class="acdc-qz-results-kpi-value"><?php echo (int) $kpis['count_participants_completed']; ?></div>
                <div class="acdc-qz-results-kpi-label">Participants ayant terminé</div>
            </div>
            <div class="acdc-qz-results-kpi-card">
                <div class="acdc-qz-results-kpi-value"><?php echo esc_html( number_format( (float) $kpis['response_rate'], 1, ',', ' ' ) ); ?> %</div>
                <div class="acdc-qz-results-kpi-label">Taux de réponse global</div>
            </div>
            <div class="acdc-qz-results-kpi-card">
                <div class="acdc-qz-results-kpi-value">
                    <?php if ( null === $kpis['pct_correct_answers'] ) : ?>—<?php
                    else : echo esc_html( number_format( (float) $kpis['pct_correct_answers'], 1, ',', ' ' ) ); ?> %<?php endif; ?>
                </div>
                <div class="acdc-qz-results-kpi-label">Bonnes réponses</div>
            </div>
            <div class="acdc-qz-results-kpi-card">
                <div class="acdc-qz-results-kpi-value">
                    <?php if ( null === $kpis['avg_score'] ) : ?>—<?php
                    elseif ( 'live' === $filter_purpose ) : echo esc_html( number_format( (float) $kpis['avg_score'], 0, ',', ' ' ) ); ?> pts<?php
                    else : echo esc_html( number_format( (float) $kpis['avg_score'], 1, ',', ' ' ) ); ?> %<?php endif; ?>
                </div>
                <div class="acdc-qz-results-kpi-label">Score moyen global</div>
            </div>
        </div>

        <section class="acdc-qz-results-section">
            <?php echo $this->qz_section_lock_svg(); ?>
            <header class="acdc-qz-results-section-header">
                <h2>Liste des envois</h2>
                <p class="acdc-qz-results-section-subtitle">
                    Cliquez sur un envoi pour consulter le détail des participants, le taux de réussite par question et l'atteinte des objectifs pédagogiques.
                </p>
            </header>

            <?php if ( empty( $sessions ) ) : ?>
                <div class="acdc-qz-empty-state">
                    <p>Aucun envoi ne correspond à ces critères.</p>
                    <p class="description">Les résultats apparaissent ici dès qu'un quiz est envoyé à des apprenants.</p>
                </div>
            <?php else : ?>
                <div class="acdc-qz-results-section-inner">
                    <table class="acdc-qz-results-table">
                        <thead>
                            <tr>
                                <th>Quiz</th>
                                <?php if ( $is_transverse ) : ?>
                                    <th>Finalité</th>
                                <?php endif; ?>
                                <th>Formation</th>
                                <th>Formateur</th>
                                <th>Lancé le</th>
                                <th>Participants</th>
                                <th>Score moyen</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $sessions as $s ) :
                            $count_inv = (int) $s->count_invited;
                            $count_cpl = (int) $s->count_completed;
                            $rate = ( $count_inv > 0 ) ? round( ( $count_cpl / $count_inv ) * 100 ) : 0;
                            $detail_args = array( 'view' => 'results', 'session' => (int) $s->id );
                            if ( $current_tab !== '' ) {
                                $detail_args['tab'] = $current_tab;
                                $detail_url = $this->portal_page_url( $detail_args );
                            } else {
                                $detail_url = $this->qz_admin_url( $s->quiz_purpose, $detail_args );
                            }
                            // Date de lancement : started_at (live) ou sent_at ou created_at
                            $launched_at = ! empty( $s->started_at ) ? $s->started_at
                                : ( ! empty( $s->sent_at ) ? $s->sent_at : $s->created_at );
                            // Score moyen : pour live, moyenne des total_score (pts bruts)
                            $avg_display = '—';
                            if ( 'live' === $s->quiz_purpose ) {
                                // ACDC 3.25.171 — Calcul déplacé dans un helper commun aux
                                // deux rôles : le portail formateur ne l'avait pas du tout.
                                $avg_pts = $this->get_qz_avg_live_score_for_session( (int) $s->id );
                                if ( null !== $avg_pts ) {
                                    $avg_display = number_format( (float) $avg_pts, 0, ',', ' ' ) . ' pts';
                                }
                            } elseif ( null !== $s->avg_score ) {
                                $avg_display = number_format( (float) $s->avg_score, 1, ',', ' ' ) . ' %';
                            }
                            // URL suppression — conservée pour fallback admin-post si JS désactivé
                            $delete_nonce = wp_create_nonce( 'acdc_of_qz_delete_session_' . (int) $s->id );
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html( $s->quiz_title ); ?></strong></td>
                                <?php if ( $is_transverse ) : ?>
                                    <td>
                                        <span class="acdc-qz-purpose-badge acdc-qz-purpose-<?php echo esc_attr( $s->quiz_purpose ); ?>">
                                            <?php echo esc_html( $this->qz_purpose_label( (string) $s->quiz_purpose ) ); ?>
                                        </span>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <?php echo esc_html( $s->formation_title ?? '—' ); ?>
                                    <?php if ( ! empty( $s->formation_modality ) ) : ?>
                                        <small>(<?php echo esc_html( $s->formation_modality ); ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    /* ACDC 3.25.171 — Lisait s.created_by, un champ qui n'existe
                                       pas sur la table des sessions de passation : la colonne
                                       était donc vide partout. Le formateur vient de la séance
                                       de formation ; à défaut, de l'animateur de la session live. */
                                    $trainer_name = trim( (string) ( $s->trainer_name ?? '' ) );
                                    if ( '' === $trainer_name && ! empty( $s->host_id ) ) {
                                        $u = get_userdata( (int) $s->host_id );
                                        if ( $u ) {
                                            $n = trim( $u->first_name . ' ' . $u->last_name );
                                            $trainer_name = $n ?: $u->display_name;
                                        }
                                    }
                                    echo esc_html( '' !== $trainer_name ? $trainer_name : '—' );
                                    ?>
                                </td>
                                <td><?php echo esc_html( $this->qz_format_datetime( $launched_at ) ); ?></td>
                                <td>
                                    <?php echo (int) $count_cpl; ?> / <?php echo (int) $count_inv; ?>
                                    <small>(<?php echo (int) $rate; ?>%)</small>
                                </td>
                                <td><strong><?php echo esc_html( $avg_display ); ?></strong></td>
                                <td style="white-space:nowrap">
                                    <a class="button" href="<?php echo esc_url( $detail_url ); ?>">Voir →</a>
                                    <?php if ( $count_cpl < $count_inv ) : ?>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                                          style="display:inline"
                                          onsubmit="return confirm('Envoyer une relance à tous les participants non complétés ?');">
                                        <input type="hidden" name="action"      value="acdc_of_qz_remind_session" />
                                        <input type="hidden" name="session_id"  value="<?php echo esc_attr( (int) $s->id ); ?>" />
                                        <input type="hidden" name="current_tab" value="<?php echo esc_attr( $current_tab ); ?>" />
                                        <?php wp_nonce_field( 'acdc_of_qz_remind_session', '_acdc_qz_nonce' ); ?>
                                        <button type="submit"
                                            title="Relancer les apprenants non complétés"
                                            style="background:none;border:none;cursor:pointer;padding:4px;font-size:16px;vertical-align:middle">
                                            🔔
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <button type="button"
                                        class="acdc-qz-delete-session-btn"
                                        data-session-id="<?php echo (int) $s->id; ?>"
                                        data-nonce="<?php echo esc_attr( wp_create_nonce( 'acdc_of_qz_delete_session_' . (int) $s->id ) ); ?>"
                                        data-count-inv="<?php echo (int) $count_inv; ?>"
                                        data-count-cpl="<?php echo (int) $count_cpl; ?>"
                                        data-confirm="Supprimer cette session et tous ses résultats ? Cette action est irréversible."
                                        title="Supprimer"
                                        style="background:none;border:none;cursor:pointer;padding:4px;color:#c0392b;margin-left:6px;vertical-align:middle">
                                        <span class="dashicons dashicons-trash" style="font-size:18px;line-height:1"></span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <script>
        function kpiDecrement(labelText, amount) {
            var cards = document.querySelectorAll('.acdc-qz-results-kpi-card');
            cards.forEach(function(card){
                var lbl = card.querySelector('.acdc-qz-results-kpi-label');
                if (lbl && lbl.textContent.trim() === labelText) {
                    var val = card.querySelector('.acdc-qz-results-kpi-value');
                    if (val) {
                        var current = parseInt(val.textContent.replace(/\s/g,''), 10);
                        if (!isNaN(current)) val.textContent = Math.max(0, current - amount);
                    }
                }
            });
        }
        document.querySelectorAll('.acdc-qz-delete-session-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                var msg        = btn.dataset.confirm || 'Supprimer cette session ?';
                if (!confirm(msg)) return;
                var sessionId  = btn.dataset.sessionId;
                var nonce      = btn.dataset.nonce;
                var countInv   = parseInt(btn.dataset.countInv, 10) || 0;
                var countCpl   = parseInt(btn.dataset.countCpl, 10) || 0;
                var row        = btn.closest('tr');
                btn.disabled   = true;
                var body = new URLSearchParams();
                body.append('action',     'acdc_of_qz_delete_session');
                body.append('session_id', sessionId);
                body.append('_wpnonce',   nonce);
                fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>', {
                    method: 'POST', body: body, credentials: 'same-origin'
                })
                .then(function(r){ return r.json(); })
                .then(function(j){
                    if (j.success) {
                        // Supprimer la ligne
                        if (row) {
                            row.style.transition = 'opacity 0.3s';
                            row.style.opacity = '0';
                            setTimeout(function(){ row.remove(); }, 300);
                        }
                        // Mettre à jour les KPIs sans rechargement
                        kpiDecrement('Envois', 1);
                        kpiDecrement('Participants cumulés', countInv);
                        kpiDecrement('Participants ayant terminé', countCpl);
                        // Recalculer le taux de réponse
                        (function(){
                            var cards = document.querySelectorAll('.acdc-qz-results-kpi-card');
                            var total = 0, completed = 0;
                            cards.forEach(function(c){
                                var lbl = c.querySelector('.acdc-qz-results-kpi-label');
                                var val = c.querySelector('.acdc-qz-results-kpi-value');
                                if (!lbl || !val) return;
                                var n = parseInt(val.textContent.replace(/\s/g,''), 10);
                                if (lbl.textContent.trim() === 'Participants cumulés') total = isNaN(n) ? 0 : n;
                                if (lbl.textContent.trim() === 'Participants ayant terminé') completed = isNaN(n) ? 0 : n;
                            });
                            var rate = total > 0 ? Math.round(completed / total * 1000) / 10 : 0;
                            cards.forEach(function(c){
                                var lbl = c.querySelector('.acdc-qz-results-kpi-label');
                                var val = c.querySelector('.acdc-qz-results-kpi-value');
                                if (lbl && val && lbl.textContent.trim() === 'Taux de réponse global') {
                                    val.textContent = rate.toFixed(1).replace('.', ',') + ' %';
                                }
                            });
                        })();
                    } else {
                        btn.disabled = false;
                        alert((j.data && j.data.message) ? j.data.message : 'Erreur lors de la suppression.');
                    }
                })
                .catch(function(){
                    btn.disabled = false;
                    alert('Erreur réseau.');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Détail d'un envoi avec onglets (participants / par question / par objectif).
     * 3.21.04.1-hotfix5 : refonte de l'en-tête avec hero pleine largeur + boutons stylés.
     */
    private function render_qz_results_session_detail( $session_id, $tab, $purpose ) {
        $session = $this->get_qz_dispatch_session( $session_id );
        if ( ! $session ) {
            ?><div class="notice notice-error"><p>Envoi introuvable.</p></div><?php
            return;
        }
        $back_url     = $this->qz_admin_url( $session->quiz_purpose, array( 'view' => 'results' ) );
        $base_url     = $this->qz_admin_url( $session->quiz_purpose, array( 'view' => 'results', 'session' => $session_id ) );
        $tab_parts    = $base_url;
        /* 3.21.04.1-hotfix5 — Le paramètre interne est qz_subtab pour ne pas
           écraser le `tab` principal de la sidebar (qz_results_*). */
        $tab_quest    = add_query_arg( 'qz_subtab', 'questions', $base_url );
        $tab_objs     = add_query_arg( 'qz_subtab', 'objectives', $base_url );
        $export_url   = add_query_arg( array(
            'action'      => 'acdc_of_qz_export_results',
            'session_id'  => $session_id,
            '_wpnonce'    => wp_create_nonce( 'acdc_of_qz_export_results' ),
        ), admin_url( 'admin-post.php' ) );
        $hero_purpose_label = $this->qz_purpose_label( (string) $session->quiz_purpose );
        ?>

        <div class="acdc-qz-results-back-bar">
            <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $back_url ); ?>">
                ← Retour
            </a>
        </div>

        <header class="acdc-qz-results-hero">
            <p class="acdc-qz-results-hero-eyebrow"><?php echo esc_html( $hero_purpose_label ); ?></p>
            <h1 class="acdc-qz-results-hero-title"><?php echo esc_html( $session->quiz_title ); ?></h1>
            <p class="acdc-qz-results-hero-meta">
                <strong>Formation :</strong> <?php echo esc_html( $session->formation_title ?? '—' ); ?>
                &nbsp;·&nbsp;
                <?php
                /* ACDC 3.25.157 — « Envoyé le » et « Échéance » n'ont aucun sens pour une
                   session live, qui n'est ni envoyée ni datée d'expiration : l'en-tête
                   affichait deux tirets. La donnée pertinente existe — started_at — et
                   c'est elle qu'on présente. */
                if ( 'live' === (string) $session->quiz_purpose ) :
                    $live_started = ! empty( $session->started_at ) ? $session->started_at : $session->created_at;
                ?>
                <strong>Lancé le :</strong> <?php echo esc_html( $this->qz_format_datetime( $live_started ) ); ?>
                <?php if ( ! empty( $session->ended_at ) ) : ?>
                &nbsp;·&nbsp;
                <strong>Terminé le :</strong> <?php echo esc_html( $this->qz_format_datetime( $session->ended_at ) ); ?>
                <?php endif; ?>
                <?php else : ?>
                <strong>Envoyé le :</strong> <?php echo esc_html( $this->qz_format_datetime( $session->sent_at ) ); ?>
                &nbsp;·&nbsp;
                <strong>Échéance :</strong> <?php echo esc_html( $this->qz_format_datetime( $session->expires_at ) ); ?>
                <?php endif; ?>
            </p>
            <div class="acdc-qz-results-hero-actions">
                <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $export_url ); ?>">
                    ⬇ Exporter fichier Excel
                </a>
            </div>
        </header>

        <nav class="acdc-qz-results-tabs">
            <a class="<?php echo 'participants' === $tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( $tab_parts ); ?>">Participants</a>
            <a class="<?php echo 'questions' === $tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( $tab_quest ); ?>">Par question</a>
            <a class="<?php echo 'objectives' === $tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( $tab_objs ); ?>">Par objectif pédagogique</a>
        </nav>

        <?php
        if ( 'questions' === $tab ) {
            $this->render_qz_results_tab_by_question( $session );
        } elseif ( 'objectives' === $tab ) {
            $this->render_qz_results_tab_by_objective( $session );
        } else {
            $this->render_qz_results_tab_participants( $session );
        }
    }

    /**
     * Onglet Participants — tableau des apprenants avec leur statut et score.
     */
    private function render_qz_results_tab_participants( $session ) {
        $participants = $this->get_qz_participants_for_dispatch_session( (int) $session->id );
        $status_labels = array(
            'pending'     => array( 'En attente', 'is-pending' ),
            'invited'     => array( 'Invité',     'is-invited' ),
            'opened'      => array( 'Ouvert',     'is-opened' ),
            'in_progress' => array( 'En cours',   'is-progress' ),
            'completed'   => array( 'Terminé',    'is-completed' ),
            'expired'     => array( 'Expiré',     'is-expired' ),
            'cancelled'   => array( 'Annulé',     'is-cancelled' ),
        );
        ?>
        <section class="acdc-qz-results-section">
            <?php echo $this->qz_section_lock_svg(); ?>
            <header class="acdc-qz-results-section-header">
                <h2>Liste des participants</h2>
                <p class="acdc-qz-results-section-subtitle">
                    Statut, durée et score de chaque apprenant pour cet envoi.
                </p>
            </header>

            <?php if ( empty( $participants ) ) : ?>
                <div class="acdc-qz-empty-state"><p>Aucun participant pour cet envoi.</p></div>
            <?php else : ?>
                <div class="acdc-qz-results-section-inner">
                    <table class="acdc-qz-results-table">
                        <thead>
                            <tr>
                                <th>Apprenant</th>
                                <th>Pseudo</th>
                                <th>Statut</th>
                                <th>Date complétion</th>
                                <th>Durée</th>
                                <th>Score</th>
                                <?php if ( 'live' !== $session->quiz_purpose ) : ?>
                                    <th title="Comparaison du score au seuil de réussite du quiz">Acquis&nbsp;?</th>
                                <?php endif; ?>
                                <th title="ACDC 3.21.70 — Nb de changements d'onglet détectés pendant la passation">Chgt. onglet</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $participants as $p ) :
                            $status_meta = $status_labels[ $p->status ] ?? array( $p->status, '' );
                            $duration = '';
                            if ( ! empty( $p->started_at ) && ! empty( $p->completed_at ) ) {
                                $secs = strtotime( $p->completed_at ) - strtotime( $p->started_at );
                                $duration = ( $secs > 0 ) ? sprintf( '%d min', round( $secs / 60 ) ) : '—';
                            }
                            $detail_url = $this->qz_admin_url( $session->quiz_purpose, array( 'view' => 'results', 'participant' => (int) $p->id ) );
                        ?>
                            <tr>
                                <td>
                                    <?php
                                    /* ACDC 3.25.171 — Le nom rattaché prime sur le pseudo de salle. */
                                    $p_name = trim( (string) ( $p->learner_full_name ?? '' ) )
                                        ?: trim( (string) $p->full_name )
                                        ?: trim( (string) $p->nickname )
                                        ?: (string) $p->email;
                                    ?>
                                    <strong><?php echo esc_html( $p_name ?: '—' ); ?></strong>
                                    <?php if ( empty( $p->learner_id ) && '' === trim( (string) $p->email ) ) : ?>
                                        <br><small style="color:#b45309;font-weight:600;">⚠ non rattaché à un apprenant</small>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $p->email ) && $p->full_name ) : ?>
                                        <small><?php echo esc_html( $p->email ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $p->nickname ?: '—' ); ?></td>
                                <td>
                                    <span class="acdc-qz-status acdc-qz-status-<?php echo esc_attr( $status_meta[1] ); ?>">
                                        <?php echo esc_html( $status_meta[0] ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( $this->qz_format_datetime( $p->completed_at ) ); ?></td>
                                <td><?php echo esc_html( $duration ); ?></td>
                                <td>
                                    <?php
                                    if ( 'live' === $session->quiz_purpose ) {
                                        // Quiz live : score en points bruts
                                        /* ACDC 3.25.157 — Seul NULL (aucune passation) vaut
                                           « — ». Un participant à 0 point doit lire « 0 pts ». */
                                        if ( null === $p->total_score ) {
                                            echo '—';
                                        } else {
                                            echo '<strong>' . esc_html( number_format( (float) $p->total_score, 0, ',', ' ' ) ) . ' pts</strong>';
                                        }
                                    } else {
                                        // Positionnement / évaluation : score en %
                                        if ( null === $p->total_score_percentage ) {
                                            echo '—';
                                        } else {
                                            echo '<strong>' . esc_html( number_format( (float) $p->total_score_percentage, 1, ',', ' ' ) ) . ' %</strong>';
                                        }
                                    }
                                    ?>
                                </td>
                                <?php if ( 'live' !== $session->quiz_purpose ) : ?>
                                    <td>
                                        <?php
                                        /* ACDC 3.25.168 — L'écran donnait un pourcentage sans dire s'il
                                           valait acquisition. Le seuil est celui du quiz, à défaut 70 %,
                                           la même règle que l'attestation de résultats. */
                                        $pass_th = ( null !== ( $session->quiz_pass_threshold ?? null ) )
                                            ? (float) $session->quiz_pass_threshold
                                            : 70.0;
                                        if ( null === $p->total_score_percentage ) {
                                            echo '<span class="acdc-qz-muted">—</span>';
                                        } elseif ( (float) $p->total_score_percentage >= $pass_th ) {
                                            echo '<span class="acdc-qz-pass-yes">✓ Acquis</span>';
                                        } else {
                                            echo '<span class="acdc-qz-pass-no">✗ Non acquis</span>';
                                        }
                                        ?>
                                        <small class="acdc-qz-muted" style="display:block;">seuil <?php echo esc_html( number_format( $pass_th, 0, ',', '' ) ); ?>%</small>
                                    </td>
                                <?php endif; ?>
                                <td style="text-align:center;">
                                    <?php
                                    $ts_count = isset( $p->tab_switch_count ) ? (int) $p->tab_switch_count : 0;
                                    if ( $ts_count > 0 ) {
                                        echo '<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:700;background:#fee2e2;color:#991b1b;" title="' . esc_attr( $ts_count . " changement(s) d'onglet detecte(s)" ) . '">' . (int) $ts_count . '</span>';                                    } else {
                                        echo '<span style="color:#9ca3af;">—</span>';
                                    }
                                    ?>
                                </td>
                                <td style="white-space:nowrap">
                                    <?php if ( in_array( $p->status, array( 'completed', 'in_progress' ), true ) ) : ?>
                                        <a class="button button-small" href="<?php echo esc_url( $detail_url ); ?>">Détail</a>
                                    <?php endif; ?>
                                    <?php if ( in_array( $p->status, array( 'invited', 'opened', 'in_progress' ), true ) ) :
                                        $rem_recently = ! empty( $p->last_reminder_sent_at )
                                            && ( strtotime( $p->last_reminder_sent_at ) > time() - 86400 );
                                    ?>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                                              style="display:inline"
                                              <?php if ( ! $rem_recently ) : ?>onsubmit="return confirm('Envoyer une relance à <?php echo esc_js( $p->full_name ); ?> ?');"<?php endif; ?>>
                                            <input type="hidden" name="action"         value="acdc_of_qz_remind_participant" />
                                            <input type="hidden" name="participant_id" value="<?php echo esc_attr( (int) $p->id ); ?>" />
                                            <?php wp_nonce_field( 'acdc_of_qz_remind_participant', '_acdc_qz_nonce' ); ?>
                                            <button type="submit"
                                                title="<?php echo esc_attr( $rem_recently ? 'Relance envoyée il y a moins de 24h' : 'Envoyer une relance' ); ?>"
                                                style="background:none;border:none;cursor:pointer;padding:2px 4px;font-size:15px;vertical-align:middle<?php echo $rem_recently ? ';opacity:.4' : ''; ?>"
                                                <?php if ( $rem_recently ) : ?>disabled<?php endif; ?>>
                                                🔔
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    /**
     * Onglet Par question — taux de réussite agrégé.
     * 3.21.04.1-hotfix4 : section principale + section "Questions les plus faibles".
     */
    private function render_qz_results_tab_by_question( $session ) {
        $rows = $this->get_qz_results_by_question( (int) $session->id );
        ?>
        <section class="acdc-qz-results-section">
            <?php echo $this->qz_section_lock_svg(); ?>
            <header class="acdc-qz-results-section-header">
                <h2>Détail par question</h2>
                <p class="acdc-qz-results-section-subtitle">
                    Taux de réussite par question pour cet envoi. Aide à identifier les notions à reprendre en formation.
                </p>
            </header>

            <?php if ( empty( $rows ) ) : ?>
                <div class="acdc-qz-empty-state"><p>Aucune question dans ce quiz.</p></div>
            <?php else : ?>
                <div class="acdc-qz-results-section-inner">
                    <table class="acdc-qz-results-table">
                        <thead>
                            <tr>
                                <th>Question</th>
                                <th>Type</th>
                                <th>Répondants</th>
                                <th>Bonnes réponses</th>
                                <th title="ACDC 3.25.168 — Réponses en partie justes, comptées au prorata">Partielles</th>
                                <th title="Part moyenne réellement acquise, réussites partielles comprises">Taux de réussite</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $rows as $r ) : ?>
                            <tr>
                                <td><?php echo esc_html( $r->title ); ?></td>
                                <td><?php echo esc_html( $this->qz_type_label( (string) $r->type ) ); ?></td>
                                <td><?php echo (int) $r->count_answered; ?></td>
                                <td><?php echo (int) $r->count_correct; ?></td>
                                <td><?php echo isset( $r->count_partial ) && (int) $r->count_partial > 0 ? (int) $r->count_partial : '—'; ?></td>
                                <td>
                                    <?php if ( null === $r->success_rate ) : ?>
                                        <?php /* ACDC 3.25.171 — Distinguer « pas de barème » de
                                                 « pas encore corrigée » : le formateur doit savoir
                                                 s'il lui reste quelque chose à faire. */ ?>
                                        <?php if ( ! empty( $r->count_pending ) ) : ?>
                                            <span style="color:#8a6d2a;font-weight:600;">⏳ en attente de correction</span>
                                        <?php else : ?>
                                            <span class="acdc-qz-muted">— (non scorée)</span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <div class="acdc-qz-rate-bar" data-rate="<?php echo esc_attr( $r->success_rate ); ?>">
                                            <div class="acdc-qz-rate-bar-fill" style="width: <?php echo esc_attr( $r->success_rate ); ?>%;"></div>
                                            <span class="acdc-qz-rate-bar-label"><?php echo esc_html( number_format( (float) $r->success_rate, 1, ',', ' ' ) ); ?>%</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <?php
        // Sous-section : Questions les plus faibles (top 3 du taux de réussite ascendant)
        $weakest = array_filter( $rows, function( $r ) { return null !== $r->success_rate; } );
        usort( $weakest, function( $a, $b ) { return $a->success_rate <=> $b->success_rate; } );
        $weakest = array_slice( $weakest, 0, 3 );
        if ( ! empty( $weakest ) ) :
        ?>
        <section class="acdc-qz-results-section">
            <?php echo $this->qz_section_lock_svg(); ?>
            <header class="acdc-qz-results-section-header">
                <h2>Questions les plus faibles</h2>
                <p class="acdc-qz-results-section-subtitle">
                    Repérez immédiatement les questions les moins bien réussies pour déclencher une action pédagogique.
                </p>
            </header>
            <div class="acdc-qz-results-section-inner">
                <table class="acdc-qz-results-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Question</th>
                            <th>Type</th>
                            <th>Répondants</th>
                            <th>Indicateur</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $weakest as $idx => $r ) : ?>
                        <tr>
                            <td><?php echo (int) ( $idx + 1 ); ?></td>
                            <td><?php echo esc_html( $r->title ); ?></td>
                            <td><?php echo esc_html( $this->qz_type_label( (string) $r->type ) ); ?></td>
                            <td><?php echo (int) $r->count_answered; ?></td>
                            <td><strong><?php echo esc_html( number_format( (float) $r->success_rate, 1, ',', ' ' ) ); ?>%</strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif;
    }

    /**
     * Onglet Par objectif pédagogique — score moyen par compétence (Qualiopi).
     */
    private function render_qz_results_tab_by_objective( $session ) {
        $rows = $this->get_qz_results_by_objective( (int) $session->id );
        ?>
        <section class="acdc-qz-results-section">
            <?php echo $this->qz_section_lock_svg(); ?>
            <header class="acdc-qz-results-section-header">
                <h2>Atteinte par objectif pédagogique</h2>
                <p class="acdc-qz-results-section-subtitle">
                    Score moyen calculé sur l'ensemble des participants ayant répondu, pour les questions rattachées à chaque objectif. Indicateur Qualiopi 12.
                </p>
            </header>

            <?php if ( empty( $rows ) ) : ?>
                <div class="acdc-qz-empty-state">
                    <p>Aucun objectif pédagogique défini sur ce quiz.</p>
                    <p class="description">Pour activer cette vue, rattachez chaque question à un objectif pédagogique depuis l'éditeur de quiz.</p>
                </div>
            <?php else : ?>
                <div class="acdc-qz-results-section-inner">
                    <table class="acdc-qz-results-table">
                        <thead>
                            <tr>
                                <th>Objectif pédagogique</th>
                                <th>Questions</th>
                                <th>Score moyen</th>
                                <th>Seuil</th>
                                <th>Atteint ?</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $rows as $r ) : ?>
                            <tr>
                                <td><?php echo esc_html( $r->label ); ?></td>
                                <td><?php echo (int) $r->count_questions; ?></td>
                                <td>
                                    <?php if ( null === $r->score_avg ) : ?>
                                        <span class="acdc-qz-muted">—</span>
                                    <?php else : ?>
                                        <strong><?php echo esc_html( number_format( (float) $r->score_avg, 1, ',', ' ' ) ); ?>%</strong>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    /* ACDC 3.25.168 — Le seuil appliqué est toujours connu : celui
                                       de l'objectif, sinon celui du quiz, sinon 70 %. On dit lequel,
                                       pour qu'un « Non atteint » ne surprenne jamais. */
                                    $th_note = array(
                                        'objective' => '',
                                        'quiz'      => 'seuil du quiz',
                                        'default'   => 'seuil par défaut',
                                    );
                                    $th_src = isset( $r->threshold_source ) ? (string) $r->threshold_source : 'objective';
                                    ?>
                                    <?php echo esc_html( number_format( (float) $r->threshold_applied, 0, ',', '' ) ); ?>%
                                    <?php if ( ! empty( $th_note[ $th_src ] ) ) : ?>
                                        <small class="acdc-qz-muted" style="display:block;"><?php echo esc_html( $th_note[ $th_src ] ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( null === $r->is_passing ) : ?>
                                        <span class="acdc-qz-muted">—</span>
                                    <?php elseif ( $r->is_passing ) : ?>
                                        <span class="acdc-qz-pass-yes">✓ Oui</span>
                                    <?php else : ?>
                                        <span class="acdc-qz-pass-no">✗ Non</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    /**
     * Détail individuel d'un participant — ses réponses question par question.
     * Inclut un mini-formulaire de correction manuelle pour les réponses libres.
     */
    private function render_qz_results_participant_detail( $participant_id, $purpose ) {
        $p = $this->get_qz_participant( $participant_id );
        if ( ! $p ) {
            ?><div class="notice notice-error"><p>Apprenant introuvable.</p></div><?php
            return;
        }
        $session = $this->get_qz_dispatch_session( (int) $p->session_id );
        if ( ! $session ) {
            ?><div class="notice notice-error"><p>Envoi introuvable.</p></div><?php
            return;
        }
        $questions    = $this->get_qz_questions_for_quiz( (int) $session->quiz_id );
        $answers      = $this->get_qz_answers_by_participant( $participant_id );
        $back_url     = $this->qz_admin_url( $session->quiz_purpose, array( 'view' => 'results', 'session' => (int) $session->id ) );

        // 3.21.04.1-hotfix5 — Nom complet + email distinct, statut traduit en français
        $full_name = trim( (string) $p->full_name );
        $email     = trim( (string) $p->email );
        /* ACDC 3.25.157 — En quiz live, le participant n'a ni nom ni e-mail : il se
           connecte sous un pseudo. Sans ce repli, l'entête affichait « Apprenant »
           suivi du vide, alors que la liste montrait bien le pseudo. */
        $nickname  = trim( (string) ( $p->nickname ?? '' ) );
        $title     = $full_name !== '' ? $full_name : ( $nickname !== '' ? $nickname : ( $email !== '' ? $email : 'Apprenant' ) );
        $status_fr = array(
            'pending'     => 'En attente',
            'invited'     => 'Invité',
            'opened'      => 'Ouvert',
            'in_progress' => 'En cours',
            'completed'   => 'Complété',
            'expired'     => 'Expiré',
            'cancelled'   => 'Annulé',
        );
        $status_label = $status_fr[ $p->status ] ?? (string) $p->status;
        $hero_purpose_label = $this->qz_purpose_label( (string) $session->quiz_purpose );
        ?>

        <div class="acdc-qz-results-back-bar">
            <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $back_url ); ?>">
                ← Retour à l'envoi
            </a>
        </div>

        <header class="acdc-qz-results-hero">
            <p class="acdc-qz-results-hero-eyebrow"><?php echo esc_html( $hero_purpose_label ); ?> — <?php echo esc_html( $session->quiz_title ); ?></p>
            <h1 class="acdc-qz-results-hero-title"><?php echo esc_html( $title ); ?></h1>
            <?php if ( $full_name !== '' && $email !== '' ) : ?>
                <p class="acdc-qz-results-hero-subline"><?php echo esc_html( $email ); ?></p>
            <?php endif; ?>
            <p class="acdc-qz-results-hero-meta">
                <strong>Score :</strong>
                <?php
                /* ACDC 3.25.157 — Ce rendu avait été oublié lors de la correction du
                   zéro : il ne lisait que total_score_percentage, toujours NULL en
                   quiz live où le score est en points bruts. D'où « Score : — » sur
                   la fiche, alors que tous les autres écrans affichaient « 0 pts ». */
                if ( 'live' === (string) $session->quiz_purpose ) :
                    if ( null === $p->total_score ) : ?>—<?php
                    else : echo esc_html( number_format( (float) $p->total_score, 0, ',', ' ' ) ); ?> pts<?php endif;
                elseif ( null === $p->total_score_percentage ) : ?>—<?php
                else : echo esc_html( number_format( (float) $p->total_score_percentage, 1, ',', ' ' ) ); ?>%<?php endif; ?>
                &nbsp;·&nbsp;
                <strong>Statut :</strong> <?php echo esc_html( $status_label ); ?>
                <?php if ( ! empty( $p->completed_at ) ) : ?>
                    &nbsp;·&nbsp;
                    <strong>Terminé le :</strong> <?php echo esc_html( $this->qz_format_datetime( $p->completed_at ) ); ?>
                <?php endif; ?>
            </p>
        </header>

        <?php $this->render_qz_inline_notice_from_query(); ?>

        <section class="acdc-qz-results-section">
            <?php echo $this->qz_section_lock_svg(); ?>
            <header class="acdc-qz-results-section-header">
                <h2>Détail des réponses</h2>
                <p class="acdc-qz-results-section-subtitle">
                    Réponses de l'apprenant question par question, comparées à l'attendu. Les réponses libres peuvent être corrigées manuellement par le formateur.
                </p>
            </header>

            <ol class="acdc-qz-results-question-list">
                <?php foreach ( $questions as $idx => $q ) :
                    $a = $answers[ (int) $q->id ] ?? null;
                    $is_open = ( self::ACDC_OF_QZ_QTYPE_OPEN_TEXT === $q->type );
                    $correct_answers = $this->get_qz_answers_for_question_public( (int) $q->id );
                ?>
                    <li class="acdc-qz-result-question">
                        <header class="acdc-qz-result-question-header">
                            <span class="acdc-qz-result-question-num">Q<?php echo (int) $idx + 1; ?></span>
                            <h3><?php echo esc_html( $q->title ); ?></h3>
                            <?php if ( $a && null !== $a->is_correct ) : ?>
                                <?php
                                /* ACDC 3.25.168 — Une réponse à moitié juste n'est plus étiquetée
                                   « Faux » : le badge porte la part effectivement acquise, la même
                                   qui a servi à compter les points. */
                                $verdict = $this->qz_answer_verdict( $a->is_correct, $a->score_ratio ?? null );
                                $badge_class = array(
                                    'correct' => 'acdc-qz-result-badge-correct',
                                    'partial' => 'acdc-qz-result-badge-partial',
                                    'wrong'   => 'acdc-qz-result-badge-wrong',
                                    'pending' => 'acdc-qz-result-badge-pending',
                                );
                                $badge_icon = array( 'correct' => '✓', 'partial' => '◐', 'wrong' => '✗', 'pending' => '⏳' );
                                ?>
                                <span class="acdc-qz-result-badge <?php echo esc_attr( $badge_class[ $verdict['state'] ] ); ?>">
                                    <?php echo esc_html( $badge_icon[ $verdict['state'] ] . ' ' . $verdict['label'] ); ?>
                                </span>
                            <?php elseif ( $is_open ) : ?>
                                <span class="acdc-qz-result-badge acdc-qz-result-badge-pending">⏳ À corriger</span>
                            <?php endif; ?>
                        </header>

                        <?php if ( ! $a ) : ?>
                            <p class="acdc-qz-result-no-answer"><em>Pas de réponse fournie.</em></p>
                        <?php elseif ( $is_open ) : ?>
                            <div class="acdc-qz-result-open-answer">
                                <p><strong>Réponse de l'apprenant :</strong></p>
                                <blockquote><?php echo nl2br( esc_html( (string) $a->answer_text ) ); ?></blockquote>
                            </div>
                            <?php $this->render_qz_manual_grading_form( $p, $q, $a, $purpose ); ?>
                        <?php else : ?>
                            <?php $this->render_qz_result_question_choices( $q, $a, $correct_answers ); ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
        <?php
    }

    /**
     * Affichage des choix de réponse pour une question fermée (QCM, V/F, Puzzle).
     * Indique ce qu'a coché l'apprenant et ce qui était attendu.
     */
    private function render_qz_result_question_choices( $question, $player_answer, $all_answers ) {
        $selected_ids = array();
        if ( ! empty( $player_answer->answer_ids_json ) ) {
            $decoded = json_decode( (string) $player_answer->answer_ids_json, true );
            if ( is_array( $decoded ) ) {
                $selected_ids = array_map( 'intval', $decoded );
            }
        } elseif ( ! empty( $player_answer->answer_id ) ) {
            $selected_ids = array( (int) $player_answer->answer_id );
        }

        // Pour le Puzzle, l'ordre de selected_ids = ordre saisi par l'apprenant
        $is_puzzle = ( self::ACDC_OF_QZ_QTYPE_PUZZLE === $question->type );

        if ( $is_puzzle ) {
            $expected_order = array_map( function( $a ) { return (int) $a->id; }, $all_answers );
            $by_id = array();
            foreach ( $all_answers as $a ) {
                $by_id[ (int) $a->id ] = (string) $a->text;
            }
            ?>
            <div class="acdc-qz-result-puzzle">
                <p><strong>Ordre saisi par l'apprenant :</strong></p>
                <ol class="acdc-qz-result-puzzle-list">
                    <?php foreach ( $selected_ids as $aid ) : ?>
                        <?php $is_at_right_pos = ( isset( $expected_order[ array_search( $aid, $selected_ids ) ?? -1 ] ) && $expected_order[ array_search( $aid, $selected_ids ) ] === $aid ); ?>
                        <li class="<?php echo $is_at_right_pos ? 'is-correct-pos' : 'is-wrong-pos'; ?>">
                            <?php echo esc_html( $by_id[ $aid ] ?? '?' ); ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <p><strong>Ordre attendu :</strong></p>
                <ol class="acdc-qz-result-puzzle-list is-expected">
                    <?php foreach ( $all_answers as $a ) : ?>
                        <li><?php echo esc_html( (string) $a->text ); ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <?php
            return;
        }

        ?>
        <ul class="acdc-qz-result-choices">
            <?php foreach ( $all_answers as $a ) :
                $is_selected = in_array( (int) $a->id, $selected_ids, true );
                $is_correct  = ( (int) $a->is_correct === 1 );
                $cls = array();
                if ( $is_selected ) { $cls[] = 'is-selected'; }
                if ( $is_correct )  { $cls[] = 'is-correct'; }
                if ( $is_selected && ! $is_correct ) { $cls[] = 'is-wrong-selection'; }
            ?>
                <li class="<?php echo esc_attr( implode( ' ', $cls ) ); ?>">
                    <span class="acdc-qz-result-choice-marker">
                        <?php if ( $is_selected ) : ?>●<?php else : ?>○<?php endif; ?>
                    </span>
                    <span class="acdc-qz-result-choice-text"><?php echo esc_html( (string) $a->text ); ?></span>
                    <?php if ( $is_correct ) : ?>
                        <span class="acdc-qz-result-choice-flag-correct">✓ Bonne réponse</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    /**
     * Formulaire de correction manuelle d'une réponse libre (Qualiopi).
     */
    private function render_qz_manual_grading_form( $participant, $question, $player_answer, $purpose ) {
        $current_correct  = ( null !== $player_answer->is_correct ) ? (int) $player_answer->is_correct : null;
        $current_score    = (float) $player_answer->score_earned;
        $max_points       = (float) $question->points_value;
        // Lire le commentaire existant (colonne optionnelle)
        $current_comment  = '';
        global $wpdb;
        $tbl_pa = $this->get_qz_table( 'player_answers' );
        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$tbl_pa} LIKE 'grader_comment'" );
        if ( ! empty( $cols ) ) {
            $current_comment = (string) $wpdb->get_var( $wpdb->prepare(
                "SELECT grader_comment FROM {$tbl_pa} WHERE participant_id = %d AND question_id = %d LIMIT 1",
                (int) $participant->id, (int) $question->id
            ) );
        }
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acdc-qz-manual-grading">
            <input type="hidden" name="action" value="acdc_of_qz_grade_open_answer" />
            <input type="hidden" name="participant_id" value="<?php echo (int) $participant->id; ?>" />
            <input type="hidden" name="question_id" value="<?php echo (int) $question->id; ?>" />
            <?php wp_nonce_field( 'acdc_of_qz_grade_open_answer', '_acdc_qz_nonce' ); ?>

            <fieldset>
                <legend><strong>Correction manuelle</strong></legend>
                <p class="acdc-qz-manual-grading-row">
                    <label>
                        <input type="radio" name="is_correct" value="1" <?php checked( 1, $current_correct ); ?> />
                        ✓ Validé
                    </label>
                    <label>
                        <input type="radio" name="is_correct" value="0" <?php checked( 0, $current_correct ); ?> />
                        ✗ Non validé
                    </label>
                </p>
                <p class="acdc-qz-manual-grading-row">
                    <label>
                        Points attribués (sur <?php echo esc_html( number_format( $max_points, 0, ',', '' ) ); ?>) :
                        <input type="number" name="score_earned" value="<?php echo esc_attr( number_format( $current_score, 2, '.', '' ) ); ?>"
                               min="0" max="<?php echo esc_attr( $max_points > 0 ? $max_points : 1000 ); ?>" step="0.01" />
                    </label>
                </p>
                <p class="acdc-qz-manual-grading-row">
                    <label style="display:block">
                        <span style="display:block;margin-bottom:4px;font-weight:600">Commentaire / correction à transmettre à l'apprenant :</span>
                        <textarea name="grader_comment" rows="3"
                            style="width:100%;max-width:600px;padding:8px;border:1px solid #d6a353;border-radius:6px;font-family:inherit;font-size:13px;resize:vertical"
                            placeholder="Exemple : La bonne réponse attendait que vous mentionniez…"><?php echo esc_textarea( $current_comment ); ?></textarea>
                    </label>
                </p>
                <p>
                    <button type="submit" class="acdc-button acdc-button-primary">Enregistrer la correction</button>
                </p>
            </fieldset>
        </form>
        <?php
    }

    /* ====================================================================
     * Helpers de formatage utilisés par les écrans résultats.
     * ==================================================================== */

    /**
     * ACDC 3.25.157 — Identifiant de FORMATEUR ACDC du compte connecté.
     *
     * Retourne 0 pour un gestionnaire (aucune restriction à appliquer) et pour
     * tout compte sans fiche formateur. À n'utiliser que pour restreindre une vue :
     * ne jamais confondre avec get_current_user_id().
     *
     * @return int Identifiant dans acdc_of_trainers, ou 0.
     */
    private function qz_current_user_trainer_id() {
        if ( current_user_can( 'manage_options' ) ) {
            return 0;
        }
        $user = wp_get_current_user();
        if ( ! $user || empty( $user->user_email ) ) {
            return 0;
        }
        global $wpdb;
        $trainer_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$this->trainer_table} WHERE email = %s LIMIT 1",
            $user->user_email
        ) );
        return (int) $trainer_id;
    }

    private function qz_format_datetime( $value ) {
        if ( empty( $value ) || '0000-00-00 00:00:00' === $value ) {
            return '—';
        }
        // Les timestamps sont stockés via current_time('mysql') = heure locale WordPress.
        // strtotime() les interprète comme UTC → wp_date() ajouterait le décalage une seconde fois.
        // On utilise date_create_from_format() dans la timezone WP pour éviter ce double décalage.
        $tz = wp_timezone();
        $dt = DateTime::createFromFormat( 'Y-m-d H:i:s', $value, $tz );
        if ( ! $dt ) {
            // Fallback pour les valeurs sans secondes
            $dt = DateTime::createFromFormat( 'Y-m-d H:i', $value, $tz );
        }
        if ( ! $dt ) {
            return '—';
        }
        return $dt->format( 'd/m/Y H:i' );
    }

    /**
     * Petit cadenas décoratif en haut à droite des sections (style legacy).
     * 3.21.04.1-hotfix4
     */
    private function qz_section_lock_svg() {
        return '<span class="acdc-qz-results-section-lock" aria-hidden="true">'
            . '<svg viewBox="0 0 24 24"><rect x="6" y="11" width="12" height="9" rx="1.5"/><path d="M8.5 11V8a3.5 3.5 0 0 1 7 0v3"/></svg>'
            . '</span>';
    }

    private function qz_purpose_label( $purpose ) {
        $labels = array(
            'live'        => 'Quiz live',
            'positioning' => 'Positionnement',
            'diagnostic'  => 'Diagnostique',
            'assessment'  => 'Évaluation',
        );
        return $labels[ $purpose ] ?? $purpose;
    }

    private function qz_type_label( $type ) {
        $labels = array(
            'qcm_single'   => 'QCM (1 réponse)',
            'qcm_multiple' => 'QCM multi',
            'true_false'   => 'Vrai / Faux',
            'puzzle'       => 'Puzzle',
            'open_text'    => 'Réponse libre',
            'poll'         => 'Sondage',
        );
        return $labels[ $type ] ?? $type;
    }

    /* ==================================================================== */
    /*  Vue consolidée formateur — 3.21.33                                  */
    /* ==================================================================== */

    /**
     * Écran "Tous les résultats" — vue consolidée pour le formateur.
     * Accessible via le tab `qz_results_all`.
     *
     * @return void
     */
    public function render_qz_results_consolidated_screen() {
        /* ACDC 3.25.157 — Cet écran passait get_current_user_id(), c'est-à-dire un
           identifiant d'UTILISATEUR WORDPRESS, à un filtre qui le compare à
           acdc_of_sessions.trainer_id, un identifiant de FORMATEUR ACDC. Les deux
           numérotations n'ont aucun rapport : la clause EXISTS ne trouvait jamais
           rien et l'écran affichait « 0 résultat » quels que soient les filtres,
           alors que les passations existaient et s'affichaient dans les écrans par
           module. C'est un écran de pilotage destiné au gestionnaire : il ne doit
           pas être restreint à un formateur. La restriction n'est conservée que si
           le compte connecté correspond effectivement à une fiche formateur. */
        $trainer_id = $this->qz_current_user_trainer_id();

        // Filtres URL
        $purpose      = isset( $_GET['qz_purpose'] )      ? sanitize_key( wp_unslash( $_GET['qz_purpose'] ) )           : '';
        $quiz_id      = isset( $_GET['qz_quiz_id'] )      ? absint( wp_unslash( $_GET['qz_quiz_id'] ) )                  : 0;
        $formation_id = isset( $_GET['qz_formation_id'] ) ? absint( wp_unslash( $_GET['qz_formation_id'] ) )             : 0;
        $search       = isset( $_GET['qz_search'] )       ? sanitize_text_field( wp_unslash( $_GET['qz_search'] ) )      : '';

        $filters  = compact( 'purpose', 'quiz_id', 'formation_id', 'search' );
        $results  = $this->get_qz_all_results_consolidated( $trainer_id, $filters );
        $base_url = $this->qz_admin_url( '', array( 'view' => 'results_all' ) );

        /* ACDC 3.25.171 — Cette énumération omettait l'évaluation diagnostique : la
           finalité s'affichait en brut dans la colonne Type et restait impossible à
           filtrer, alors que des lignes en portaient. Même oubli que celui corrigé
           sur l'onglet Résultats — on prend désormais la liste de référence. */
        $purpose_labels = $this->get_quiz_purpose_labels();

        // Filtres de page
        ?>
        <header class="acdc-qz-results-hero" style="margin-bottom:20px;">
            <p class="acdc-qz-results-hero-eyebrow">RÉSULTATS CONSOLIDÉS</p>
            <h1 class="acdc-qz-results-hero-title">Toutes les passations</h1>
            <p class="acdc-qz-results-hero-subline">Vue globale de toutes les passations complétées de vos apprenants.</p>
        </header>

        <!-- Filtres -->
        <form method="get" action="" class="acdc-qz-filters-bar" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:20px;">
            <?php
            // Conserver les paramètres de tab/page existants
            foreach ( $_GET as $k => $v ) {
                if ( in_array( $k, array( 'qz_purpose', 'qz_quiz_id', 'qz_formation_id', 'qz_search' ), true ) ) { continue; }
                echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( (string) $v ) . '" />';
            }
            ?>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Type</label>
                <select name="qz_purpose" style="height:36px;border-radius:8px;border:1px solid #dce4ec;padding:0 10px;">
                    <option value="">Tous les types</option>
                    <?php foreach ( $purpose_labels as $k => $l ) : ?>
                        <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $purpose, $k ); ?>>
                            <?php echo esc_html( $l ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Recherche</label>
                <input type="text" name="qz_search" value="<?php echo esc_attr( $search ); ?>"
                       placeholder="Nom, e-mail, quiz…"
                       style="height:36px;border-radius:8px;border:1px solid #dce4ec;padding:0 10px;width:220px;" />
            </div>
            <button type="submit" class="acdc-button acdc-button-soft" style="height:36px;">
                Filtrer
            </button>
            <?php if ( $purpose || $quiz_id || $formation_id || $search ) : ?>
                <?php /* ACDC 3.25.171 — qz_admin_url('') pose déjà un tab (qz_live par
                         défaut) : la concaténation en ajoutait un second, d'où une URL à
                         deux paramètres tab. On construit l'URL proprement. */ ?>
                <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->portal_page_url( array( 'tab' => 'qz_results_all' ) ) ); ?>"
                   style="height:36px;line-height:36px;padding:0 14px;">
                    ✕ Réinitialiser
                </a>
            <?php endif; ?>
        </form>

        <!-- Tableau résultats -->
        <section class="acdc-qz-results-section">
            <?php echo $this->qz_section_lock_svg(); ?>
            <header class="acdc-qz-results-section-header">
                <h2>Passations complétées</h2>
                <p class="acdc-qz-results-section-subtitle">
                    <?php echo count( $results ); ?> résultat(s) — cliquez sur "Détail" pour voir les réponses question par question.
                </p>
            </header>

            <?php if ( empty( $results ) ) : ?>
                <div class="acdc-qz-empty-state">
                    <p>Aucune passation complétée ne correspond à vos critères.</p>
                </div>
            <?php else : ?>
                <div class="acdc-qz-results-section-inner">
                    <table class="acdc-qz-results-table">
                        <thead>
                            <tr>
                                <th>Apprenant</th>
                                <th>Formation</th>
                                <th>Quiz / Test</th>
                                <th>Type</th>
                                <th>Complété le</th>
                                <th>Score</th>
                                <th>Résultat</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $results as $r ) :
                            /* ACDC 3.25.171 — Le nom rattaché prime sur le pseudo de salle. */
                            $name = trim( (string) ( $r->learner_full_name ?? '' ) )
                                ?: trim( (string) $r->full_name )
                                ?: trim( (string) $r->nickname )
                                ?: (string) $r->email
                                ?: '—';
                            $unlinked = empty( $r->learner_id ) && '' === trim( (string) $r->email );
                            $p_label = $purpose_labels[ $r->quiz_purpose ] ?? ucfirst( (string) $r->quiz_purpose );
                            /* ACDC 3.25.157 — wp_date(strtotime()) appliquait le fuseau DEUX
                               fois : les horodatages sont stockés en heure locale WordPress,
                               strtotime() les interprétait comme UTC, puis wp_date ajoutait
                               de nouveau le décalage — d'où +2 h sur ce seul écran. On passe
                               par le formateur commun, qui lit déjà dans le bon fuseau. */
                            $date    = $this->qz_format_datetime( $r->completed_at );
                            $score   = null !== $r->total_score_percentage
                                ? number_format( (float) $r->total_score_percentage, 1, ',', ' ' ) . ' %'
                                : ( 'live' === $r->quiz_purpose && null !== $r->total_score
                                    ? number_format( (float) $r->total_score, 0, ',', ' ' ) . ' pts'
                                    : '—' );
                            $detail_url = $this->qz_admin_url( (string) $r->quiz_purpose, array(
                                'view'        => 'results',
                                'participant' => (int) $r->participant_id,
                            ) );
                            $has_pdf = ! empty( $r->result_document_url );
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $name ); ?></strong>
                                    <?php if ( ! empty( $r->email ) ) : ?>
                                        <br><small style="color:#6b7280;"><?php echo esc_html( $r->email ); ?></small>
                                    <?php endif; ?>
                                    <?php if ( $unlinked ) : ?>
                                        <?php /* ACDC 3.25.171 — Une passation jouée sous pseudo, sans
                                                 apprenant rattaché, ne vaut pas preuve : il faut que
                                                 cela se voie au lieu de se deviner. */ ?>
                                        <br><small style="color:#b45309;font-weight:600;">⚠ non rattaché à un apprenant</small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( (string) $r->formation_title ?: '—' ); ?></td>
                                <td><?php echo esc_html( (string) $r->quiz_title ); ?></td>
                                <td>
                                    <span class="acdc-qz-type-badge acdc-qz-type-badge-<?php echo esc_attr( (string) $r->quiz_purpose ); ?>">
                                        <?php echo esc_html( $p_label ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( $date ); ?></td>
                                <td><strong><?php echo esc_html( $score ); ?></strong></td>
                                <td>
                                    <?php if ( null !== $r->is_passed ) : ?>
                                        <?php if ( (int) $r->is_passed === 1 ) : ?>
                                            <span class="acdc-qz-status acdc-qz-status-is-completed">✓ Réussi</span>
                                        <?php else : ?>
                                            <span class="acdc-qz-status acdc-qz-status-is-expired">✗ Non réussi</span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span style="color:#6b7280;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space:nowrap">
                                    <a class="button button-small" href="<?php echo esc_url( $detail_url ); ?>">
                                        Détail
                                    </a>
                                    <?php if ( $has_pdf ) : ?>
                                        <a class="button button-small" href="<?php echo esc_url( (string) $r->result_document_url ); ?>"
                                           target="_blank" download style="margin-left:4px;">
                                            📄 PDF
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }
}
