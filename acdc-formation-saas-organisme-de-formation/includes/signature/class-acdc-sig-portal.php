<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * ACDC Signature — Portail front-office
 *
 * Intègre le module signature dans l'extranet front-office via les deux filtres
 * standard du kernel :
 *   - acdc_portal_navigation_groups  → entrée sidebar "Signatures"
 *   - acdc_portal_render_unknown_tab → routage + rendu des onglets
 *
 * Onglets exposés (préfixe sig_) :
 *   sig_requests  — Demandes en cours
 *   sig_archives  — Archives
 *   sig_new       — Nouvelle demande
 *   sig_settings  — Réglages
 *
 * @package ACDC_Formation_SAAS
 * @since   3.21.08
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ACDC_Sig_Portal {

    private ACDC_Sig_Core  $core;
    private ACDC_Sig_Email $email;
    private ACDC_Sig_PDF   $pdf;

    /** Slugs de tabs reconnus par ce module */
    const TABS = array( 'sig_requests', 'sig_archives', 'sig_new', 'sig_settings' );

    public function __construct( ACDC_Sig_Core $core, ACDC_Sig_Email $email, ACDC_Sig_PDF $pdf ) {
        $this->core  = $core;
        $this->email = $email;
        $this->pdf   = $pdf;
    }

    /** Enregistre les hooks front-office */
    public function register_hooks() {
        add_filter( 'acdc_portal_navigation_groups',  array( $this, 'inject_sig_sidebar_entry' ), 25, 2 );
        add_filter( 'acdc_portal_render_unknown_tab',  array( $this, 'render_sig_tab' ), 10, 4 );

        // Handlers POST : renvoi/suppression depuis le front
        add_action( 'admin_post_acdc_sig_portal_resend',  array( $this, 'handle_portal_resend' ) );
        add_action( 'admin_post_acdc_sig_portal_delete',  array( $this, 'handle_portal_delete' ) );
        add_action( 'admin_post_acdc_sig_portal_new',     array( $this, 'handle_portal_new' ) );
        add_action( 'admin_post_acdc_sig_portal_settings',array( $this, 'handle_portal_settings' ) );
    }

    /* ===================================================================
     * Sidebar
     * ================================================================= */

    public function inject_sig_sidebar_entry( $groups, $current_tab ) {
        $is_active = in_array( $current_tab, self::TABS, true );
        $groups[] = array(
            'type'   => 'group',
            'label'  => __( 'Signatures électroniques', 'acdc-formation-saas' ),
            'icon'   => 'send',
            'active' => $is_active,
            'items'  => array(
                array(
                    'tab'   => 'sig_requests',
                    'label' => __( 'Demandes en cours', 'acdc-formation-saas' ),
                    'icon'  => 'send',
                ),
                array(
                    'tab'   => 'sig_archives',
                    'label' => __( 'Archives', 'acdc-formation-saas' ),
                    'icon'  => 'evaluation_result',
                ),
                array(
                    'tab'   => 'sig_new',
                    'label' => __( 'Nouvelle demande', 'acdc-formation-saas' ),
                    'icon'  => 'quiz',
                ),
                array(
                    'tab'   => 'sig_settings',
                    'label' => __( 'Réglages', 'acdc-formation-saas' ),
                    'icon'  => 'positioning',
                ),
            ),
        );
        return $groups;
    }

    /* ===================================================================
     * Routeur principal
     * ================================================================= */

    public function render_sig_tab( $handled, $tab, $action, $item_id ) {
        if ( $handled ) { return $handled; }
        if ( ! in_array( $tab, self::TABS, true ) ) { return false; }

        if ( ! current_user_can( 'manage_options' ) ) {
            echo '<div class="acdc-alert acdc-alert-error">Accès réservé aux administrateurs.</div>';
            return true;
        }

        $this->render_notices();

        switch ( $tab ) {
            case 'sig_requests': $this->render_requests(); break;
            case 'sig_archives': $this->render_archives(); break;
            case 'sig_new':      $this->render_new_form(); break;
            case 'sig_settings': $this->render_settings(); break;
        }

        return true;
    }

    /* ===================================================================
     * Notices flash
     * ================================================================= */

    private function render_notices() {
        if ( isset( $_GET['sig_ok'] ) ) {
            $msgs = array(
                'envoye'   => 'Demande de signature envoyée avec succès.',
                'renvoye'  => 'E-mail renvoyé au signataire.',
                'supprime' => 'Demande supprimée.',
                'saved'    => 'Réglages enregistrés.',
            );
            $key = sanitize_key( $_GET['sig_ok'] );
            if ( ! empty( $msgs[ $key ] ) ) {
                echo '<div class="acdc-alert acdc-alert-success">' . esc_html( $msgs[ $key ] ) . '</div>';
            }
        }
        if ( isset( $_GET['sig_err'] ) ) {
            echo '<div class="acdc-alert acdc-alert-error">' . esc_html( sanitize_text_field( $_GET['sig_err'] ) ) . '</div>';
        }
    }

    /* ===================================================================
     * URL helper
     * ================================================================= */

    private function url( $tab, $extra = array() ) {
        $page_id = (int) get_option( 'acdc_of_extranet_dashboard_page_id', 0 );
        if ( ! $page_id ) { $page_id = (int) get_option( 'acdc_of_portal_page_id', 0 ); }
        $base = $page_id ? get_permalink( $page_id ) : home_url( '/' );
        return add_query_arg( array_merge( array( 'tab' => $tab ), $extra ), $base );
    }

    /* ===================================================================
     * Onglet : Demandes en cours
     * ================================================================= */

    private function render_requests() {
        global $wpdb;
        $items = $wpdb->get_results(
            "SELECT * FROM {$this->core->table_requests}
             WHERE status NOT IN ('signe','expire','refuse','supprime')
             ORDER BY created_at DESC LIMIT 200"
        );
        ?>
        <section class="acdc-section-head">
            <div>
                <h2><?php esc_html_e( 'Demandes en cours', 'acdc-formation-saas' ); ?></h2>
                <p class="acdc-section-subtitle">Signatures en attente d'action signataire.</p>
            </div>
            <div>
                <a href="<?php echo esc_url( $this->url( 'sig_new' ) ); ?>" class="acdc-button acdc-button-primary">+ Nouvelle demande</a>
            </div>
        </section>
        <?php if ( empty( $items ) ) : ?>
            <div class="acdc-panel" style="text-align:center;padding:40px;color:#4b5d76;">
                Aucune demande en cours. <a href="<?php echo esc_url( $this->url( 'sig_new' ) ); ?>">Créer une demande</a>
            </div>
        <?php else : ?>
        <div class="acdc-table-wrap">
            <table class="acdc-table">
                <thead>
                    <tr>
                        <th>Signataire</th>
                        <th>Type de document</th>
                        <th>Niveau</th>
                        <th>Statut</th>
                        <th>Expiration</th>
                        <th class="acdc-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $items as $item ) :
                    $expires   = $item->expires_at ? mysql2date( 'd/m/Y H:i', $item->expires_at ) : '—';
                    $doc_label = ACDC_Sig_Core::DOC_TYPES[ $item->doc_type ] ?? $item->doc_type;
                    $level     = ( ACDC_Sig_Core::LEVEL_RENFORCE === $item->sig_level ) ? '🔒 Renforcée' : '✅ Simple';
                    $resend_url = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_sig_portal_resend&request_id=' . $item->id ), 'acdc_sig_portal_resend_' . $item->id );
                    $delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_sig_portal_delete&request_id=' . $item->id ), 'acdc_sig_portal_delete_' . $item->id );
                    $status_class = 'acdc-sig-badge ' . esc_attr( $item->status );
                ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html( $item->signer_name ); ?></strong><br>
                            <small style="color:#4b5d76"><?php echo esc_html( $item->signer_email ); ?></small>
                        </td>
                        <td><?php echo esc_html( $doc_label ); ?></td>
                        <td><?php echo esc_html( $level ); ?></td>
                        <td><span class="<?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $this->core->status_label( $item->status ) ); ?></span></td>
                        <td><?php echo esc_html( $expires ); ?></td>
                        <td>
                            <div class="acdc-row-actions">
                                <a href="<?php echo esc_url( $resend_url ); ?>" class="acdc-button acdc-button-soft acdc-button-sm"
                                   onclick="return confirm('Renvoyer l\'e-mail au signataire ?')">↻ Renvoyer</a>
                                <a href="<?php echo esc_url( $delete_url ); ?>" class="acdc-button acdc-button-soft acdc-button-sm acdc-button-danger"
                                   onclick="return confirm('Supprimer cette demande ?')" style="color:#c00">✕ Supprimer</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif;
    }

    /* ===================================================================
     * Onglet : Archives
     * ================================================================= */

    private function render_archives() {
        global $wpdb;

        $st  = $wpdb->prefix . 'acdc_of_sessions';
        $ft  = $wpdb->prefix . 'acdc_of_formations';

        /* ---- Source 1 : module e-signature (acdc_sig_requests) ---- */
        $sig_items = $wpdb->get_results(
            "SELECT r.*, f.title AS formation_title, s.title AS session_title,
                    s.start_at AS session_start_at
             FROM {$this->core->table_requests} r
             LEFT JOIN {$st} s ON s.id = r.session_id
             LEFT JOIN {$ft} f ON f.id = s.formation_id
             WHERE r.status IN ('signe','expire','refuse')
             ORDER BY r.updated_at DESC LIMIT 500"
        );

        /* ---- Source 2 : module émargement — formateur ---- */
        $emarg_table_s = $wpdb->prefix . 'acdc_of_emarg_sessions';
        $emarg_trainer = $wpdb->get_results(
            "SELECT es.id, es.trainer_name, es.trainer_signed_at AS signed_at,
                    es.trainer_sig_url AS doc_url, es.session_id,
                    s.title AS session_title, s.start_at AS session_start_at,
                    f.title AS formation_title
             FROM {$emarg_table_s} es
             LEFT JOIN {$st} s ON s.id = es.session_id
             LEFT JOIN {$ft} f ON f.id = s.formation_id
             WHERE es.trainer_status = 'signe'
             ORDER BY es.trainer_signed_at DESC LIMIT 500"
        );

        /* ---- Source 3 : module émargement — apprenants ---- */
        $emarg_table_l = $wpdb->prefix . 'acdc_of_emarg_learners';
        $emarg_learners = $wpdb->get_results(
            "SELECT l.id, l.learner_name, l.learner_email,
                    l.signed_at, l.sig_url AS doc_url,
                    es.session_id,
                    s.title AS session_title, s.start_at AS session_start_at,
                    f.title AS formation_title
             FROM {$emarg_table_l} l
             LEFT JOIN {$emarg_table_s} es ON es.id = l.emarg_session_id
             LEFT JOIN {$st} s ON s.id = es.session_id
             LEFT JOIN {$ft} f ON f.id = s.formation_id
             WHERE l.status = 'signe'
             ORDER BY l.signed_at DESC LIMIT 500"
        );

        /* ---- Assemblage en tableau unifié ---- */
        $rows = array();

        foreach ( $sig_items as $item ) {
            $doc_label   = ACDC_Sig_Core::DOC_TYPES[ $item->doc_type ] ?? $item->doc_type;
            $level_label = 'renforce' === $item->sig_level ? 'Renforcée' : 'Simple';
            $level_class = 'renforce' === $item->sig_level ? 'renforce' : 'simple';

            $context = '';
            if ( ! empty( $item->formation_title ) ) {
                $context = $item->formation_title;
                if ( ! empty( $item->session_start_at ) ) {
                    $context .= ' — ' . wp_date( 'd/m/Y', strtotime( $item->session_start_at ) );
                }
            }

            $rows[] = array(
                'signer_name'  => $item->signer_name,
                'signer_email' => $item->signer_email,
                'doc_type'     => $doc_label,
                'source'       => 'sig',
                'status_label' => $this->core->status_label( $item->status ),
                'status_key'   => $item->status,
                'signed_at'    => $item->signed_at,
                'doc_url'      => $item->signed_doc_url ?: ( $item->audit_pdf_url ?: '' ),
                'session_id'   => $item->session_id,
                'level_label'  => $level_label,
                'level_class'  => $level_class,
                'context'      => $context,
            );
        }

        foreach ( $emarg_trainer as $item ) {
            $context = '';
            if ( ! empty( $item->formation_title ) ) {
                $context = $item->formation_title;
                if ( ! empty( $item->session_start_at ) ) {
                    $context .= ' — ' . wp_date( 'd/m/Y', strtotime( $item->session_start_at ) );
                }
            }
            $rows[] = array(
                'signer_name'  => $item->trainer_name,
                'signer_email' => '',
                'doc_type'     => 'Émargement — Formateur',
                'source'       => 'emarg',
                'emarg_type'   => 'trainer',
                'emarg_id'     => (int) $item->id,
                'status_label' => 'Signé',
                'status_key'   => 'signe',
                'signed_at'    => $item->signed_at,
                'doc_url'      => '',
                'session_id'   => $item->session_id,
                'level_label'  => 'Simple',
                'level_class'  => 'simple',
                'context'      => $context,
            );
        }

        foreach ( $emarg_learners as $item ) {
            $context = '';
            if ( ! empty( $item->formation_title ) ) {
                $context = $item->formation_title;
                if ( ! empty( $item->session_start_at ) ) {
                    $context .= ' — ' . wp_date( 'd/m/Y', strtotime( $item->session_start_at ) );
                }
            }
            $rows[] = array(
                'signer_name'  => $item->learner_name,
                'signer_email' => $item->learner_email ?: '',
                'doc_type'     => 'Émargement — Apprenant',
                'source'       => 'emarg',
                'emarg_type'   => 'learner',
                'emarg_id'     => (int) $item->id,
                'status_label' => 'Signé',
                'status_key'   => 'signe',
                'signed_at'    => $item->signed_at,
                'doc_url'      => '',
                'session_id'   => $item->session_id,
                'level_label'  => 'Simple',
                'level_class'  => 'simple',
                'context'      => $context,
            );
        }

        /* Tri global par date décroissante */
        usort( $rows, function( $a, $b ) {
            return strcmp( (string) ( $b['signed_at'] ?? '' ), (string) ( $a['signed_at'] ?? '' ) );
        });

        $total_sig   = count( $sig_items );
        $total_emarg = count( $emarg_trainer ) + count( $emarg_learners );
        ?>
        <section class="acdc-section-head">
            <div>
                <h2><?php esc_html_e( 'Archives', 'acdc-formation-saas' ); ?></h2>
                <p class="acdc-section-subtitle">
                    <?php echo count( $rows ); ?> signature<?php echo count( $rows ) > 1 ? 's' : ''; ?> au total —
                    <?php echo $total_sig; ?> e-signature<?php echo $total_sig > 1 ? 's' : ''; ?> (Simple/Renforcée) +
                    <?php echo $total_emarg; ?> émargement<?php echo $total_emarg > 1 ? 's' : ''; ?> (Simple)
                </p>
            </div>
        </section>
        <?php if ( empty( $rows ) ) : ?>
            <div class="acdc-panel" style="text-align:center;padding:40px;color:#4b5d76;">Aucune signature archivée.</div>
        <?php else : ?>
        <div class="acdc-table-wrap">
            <table class="acdc-table">
                <thead>
                    <tr>
                        <th>Signataire</th>
                        <th>Type</th>
                        <th>Formation / Séance</th>
                        <th>Niveau</th>
                        <th>Statut</th>
                        <th>Signé le</th>
                        <th>Document</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $rows as $row ) :
                    $signed_at_label = $row['signed_at'] ? mysql2date( 'd/m/Y H:i', $row['signed_at'] ) : '—';
                    $dl = '';
                    // SVG icônes ACDC inline (render_inline_icon inaccessible hors trait)
                    $icon_view = '<span aria-hidden="true" style="display:inline-flex;align-items:center;justify-content:center;width:25px;height:25px;line-height:1;"><svg style="fill:none!important" width="25" height="25" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="3"/></svg></span>';
                    $icon_dl   = '<span aria-hidden="true" style="display:inline-flex;align-items:center;justify-content:center;width:25px;height:25px;line-height:1;"><svg style="fill:none!important" width="25" height="25" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></span>';
                    if ( ! empty( $row['doc_url'] ) ) {
                        $dl  = '<a href="' . esc_url( $row['doc_url'] ) . '" target="_blank" class="acdc-row-action-icon acdc-row-view-link" data-acdc-iconized="1" title="Aperçu du document" aria-label="Aperçu">' . $icon_view . '<span class="acdc-action-hub-sr screen-reader-text">Aperçu</span></a>';
                        $dl .= '<a href="' . esc_url( $row['doc_url'] ) . '" download class="acdc-row-action-icon" data-acdc-iconized="1" title="Télécharger le document" aria-label="Télécharger">' . $icon_dl . '<span class="acdc-action-hub-sr screen-reader-text">Télécharger</span></a>';
                    } elseif ( 'emarg' === $row['source'] && ! empty( $row['emarg_id'] ) ) {
                        $action    = 'trainer' === $row['emarg_type'] ? 'acdc_emarg_download_cert_trainer' : 'acdc_emarg_download_cert_learner';
                        $nonce_key = 'trainer' === $row['emarg_type'] ? 'acdc_emarg_cert_trainer_' : 'acdc_emarg_cert_learner_';
                        $base_args = 'action=' . $action . '&emarg_id=' . (int) $row['emarg_id'];
                        $view_url  = wp_nonce_url( admin_url( 'admin-post.php?' . $base_args . '&preview=1' ), $nonce_key . (int) $row['emarg_id'] );
                        $dl_url    = wp_nonce_url( admin_url( 'admin-post.php?' . $base_args ),                $nonce_key . (int) $row['emarg_id'] );
                        $dl  = '<a href="' . esc_url( $view_url ) . '" target="_blank" class="acdc-row-action-icon acdc-row-view-link" data-acdc-iconized="1" title="Aperçu du certificat" aria-label="Aperçu">' . $icon_view . '<span class="acdc-action-hub-sr screen-reader-text">Aperçu</span></a>';
                        $dl .= '<a href="' . esc_url( $dl_url ) . '" download class="acdc-row-action-icon" data-acdc-iconized="1" title="Télécharger le certificat" aria-label="Télécharger">' . $icon_dl . '<span class="acdc-action-hub-sr screen-reader-text">Télécharger</span></a>';
                    }
                ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html( $row['signer_name'] ); ?></strong>
                            <?php if ( $row['signer_email'] ) : ?>
                            <br><small style="color:#4b5d76"><?php echo esc_html( $row['signer_email'] ); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $row['doc_type'] ); ?></td>
                        <td>
                            <?php if ( $row['context'] ) : ?>
                            <span style="font-size:12px;color:#0f2c52"><?php echo esc_html( $row['context'] ); ?></span>
                            <?php else : ?>
                            <span style="color:#9ca3af">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="acdc-sig-badge <?php echo esc_attr( $row['level_class'] ); ?>"><?php echo esc_html( $row['level_label'] ); ?></span></td>
                        <td><span class="acdc-sig-badge <?php echo esc_attr( $row['status_key'] ); ?>"><?php echo esc_html( $row['status_label'] ); ?></span></td>
                        <td style="white-space:nowrap"><?php echo esc_html( $signed_at_label ); ?></td>
                        <td class="acdc-sig-doc-cell">
                            <div class="acdc-sessions-actions-inline">
                                <?php echo $dl; // phpcs:ignore WordPress.Security.EscapeOutput ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif;
    }

    /* ===================================================================
     * Onglet : Nouvelle demande
     * ================================================================= */

    private function render_new_form() {
        global $wpdb;
        $session_table   = $wpdb->prefix . 'acdc_of_sessions';
        $formation_table = $wpdb->prefix . 'acdc_of_formations';
        $sessions = array();
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$session_table}'" ) ) {
            $sessions = $wpdb->get_results(
                "SELECT s.id, s.title, f.title AS formation_title
                 FROM {$session_table} s
                 LEFT JOIN {$formation_table} f ON f.id = s.formation_id
                 WHERE s.is_draft = 0 AND s.status NOT IN ('Annulée')
                 ORDER BY s.id DESC LIMIT 200"
            );
        }
        ?>
        <section class="acdc-section-head">
            <div>
                <h2><?php esc_html_e( 'Nouvelle demande de signature', 'acdc-formation-saas' ); ?></h2>
                <p class="acdc-section-subtitle">Créer et envoyer une demande de signature électronique.</p>
            </div>
        </section>

        <div class="acdc-panel acdc-profile-section" style="max-width:720px">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field( 'acdc_sig_portal_new' ); ?>
                <input type="hidden" name="action" value="acdc_sig_portal_new">

                <div class="acdc-contract-grid">
                    <div class="acdc-contract-label">Prénom et nom <span class="acdc-required">*</span></div>
                    <div><input type="text" name="signer_name" required placeholder="ex. Marie Dupont"></div>
                </div>
                <div class="acdc-contract-grid">
                    <div class="acdc-contract-label">Adresse e-mail <span class="acdc-required">*</span></div>
                    <div><input type="email" name="signer_email" required placeholder="ex. marie.dupont@entreprise.fr"></div>
                </div>
                <div class="acdc-contract-grid">
                    <div class="acdc-contract-label">Qualité / rôle</div>
                    <div><input type="text" name="signer_role" placeholder="ex. Stagiaire, Responsable formation…"></div>
                </div>
                <div class="acdc-contract-grid">
                    <div class="acdc-contract-label">Type de document <span class="acdc-required">*</span></div>
                    <div>
                        <select name="doc_type" required>
                            <?php foreach ( ACDC_Sig_Core::DOC_TYPES as $slug => $label ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="acdc-contract-grid">
                    <div class="acdc-contract-label">Niveau de traçabilité</div>
                    <div>
                        <select name="sig_level">
                            <option value="simple">Simple — Dessin tactile + horodatage + IP</option>
                            <option value="renforce">Renforcé — Simple + vérification OTP par e-mail (double authentification)</option>
                        </select>
                    </div>
                </div>
                <?php if ( ! empty( $sessions ) ) : ?>
                <div class="acdc-contract-grid">
                    <div class="acdc-contract-label">Session liée (optionnel)</div>
                    <div>
                        <select name="session_id">
                            <option value="">— Aucune —</option>
                            <?php foreach ( $sessions as $s ) : ?>
                                <?php $lbl = $s->title ?: ( $s->formation_title ?: 'Session #' . $s->id ); ?>
                                <option value="<?php echo esc_attr( $s->id ); ?>"><?php echo esc_html( $lbl ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
                <div class="acdc-contract-grid">
                    <div class="acdc-contract-label">Source du document</div>
                    <div>
                        <select name="doc_source" id="sig-portal-doc-source">
                            <option value="upload">Importer un PDF</option>
                            <option value="url">URL d'un PDF existant</option>
                        </select>
                    </div>
                </div>
                <div class="acdc-contract-grid" id="sig-portal-upload-row">
                    <div class="acdc-contract-label">Fichier PDF</div>
                    <div><input type="file" name="doc_file" accept="application/pdf"></div>
                </div>
                <div class="acdc-contract-grid" id="sig-portal-url-row" style="display:none">
                    <div class="acdc-contract-label">URL du PDF</div>
                    <div><input type="text" name="doc_url_input" placeholder="https://…/document.pdf"></div>
                </div>
                <div class="acdc-contract-grid">
                    <div class="acdc-contract-label">Notes internes</div>
                    <div><textarea name="notes" rows="3" placeholder="Visible uniquement dans l'extranet…"></textarea></div>
                </div>
                <div class="acdc-contract-grid">
                    <div class="acdc-contract-label">Délai de validité</div>
                    <div>
                        <select name="ttl_hours">
                            <option value="24">24 heures</option>
                            <option value="48">48 heures</option>
                            <option value="72" selected>72 heures</option>
                            <option value="168">7 jours</option>
                        </select>
                    </div>
                </div>
                <div class="acdc-form-actions" style="margin-top:24px">
                    <a href="<?php echo esc_url( $this->url( 'sig_requests' ) ); ?>" class="acdc-button acdc-button-soft">Annuler</a>
                    <button type="submit" class="acdc-button acdc-button-primary">✉ Envoyer la demande</button>
                </div>
            </form>
        </div>
        <script>
        document.getElementById('sig-portal-doc-source').addEventListener('change',function(){
            var v=this.value;
            document.getElementById('sig-portal-upload-row').style.display=(v==='upload')?'':'none';
            document.getElementById('sig-portal-url-row').style.display=(v==='url')?'':'none';
        });
        </script>
        <?php
    }

    /* ===================================================================
     * Onglet : Réglages
     * ================================================================= */

    private function render_settings() {
        $s = $this->core->get_settings();
        ?>
        <section class="acdc-section-head">
            <div>
                <h2><?php esc_html_e( 'Réglages — Signatures', 'acdc-formation-saas' ); ?></h2>
                <p class="acdc-section-subtitle">Configuration des e-mails, boutons et relances automatiques.</p>
            </div>
        </section>

        <div class="acdc-panel acdc-profile-section" style="max-width:720px">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'acdc_sig_portal_settings' ); ?>
                <input type="hidden" name="action" value="acdc_sig_portal_settings">

                <div class="acdc-contract-grid">
                    <div class="acdc-contract-label">Expéditeur (nom)</div>
                    <div><input type="text" name="from_name" value="<?php echo esc_attr( $s['from_name'] ); ?>"></div>
                </div>
                <div class="acdc-contract-grid">
                    <div class="acdc-contract-label">Expéditeur (e-mail)</div>
                    <div><input type="email" name="from_email" value="<?php echo esc_attr( $s['from_email'] ); ?>"></div>
                </div>
                <div class="acdc-contract-grid">
                    <div class="acdc-contract-label">Objet de l'e-mail</div>
                    <div><input type="text" name="email_subject" value="<?php echo esc_attr( $s['email_subject'] ); ?>"></div>
                </div>
                <div class="acdc-contract-grid">
                    <div class="acdc-contract-label">Message introductif</div>
                    <div><textarea name="email_intro" rows="4"><?php echo esc_textarea( $s['email_intro'] ); ?></textarea></div>
                </div>
                <div class="acdc-contract-grid">
                    <div class="acdc-contract-label">Couleur du bouton</div>
                    <div><input type="text" name="btn_color" value="<?php echo esc_attr( $s['btn_color'] ); ?>" style="max-width:160px"></div>
                </div>
                <div class="acdc-contract-grid">
                    <div class="acdc-contract-label">Texte du bouton</div>
                    <div><input type="text" name="btn_label" value="<?php echo esc_attr( $s['btn_label'] ); ?>"></div>
                </div>
                <div class="acdc-contract-grid">
                    <div class="acdc-contract-label">Mention légale</div>
                    <div><textarea name="legal_notice" rows="3"><?php echo esc_textarea( $s['legal_notice'] ); ?></textarea></div>
                </div>
                <div class="acdc-contract-grid">
                    <div class="acdc-contract-label">E-mail administrateur</div>
                    <div><input type="email" name="admin_email" value="<?php echo esc_attr( $s['admin_email'] ); ?>"></div>
                </div>
                <div class="acdc-contract-grid">
                    <div class="acdc-contract-label">Relances automatiques</div>
                    <div>
                        <label class="acdc-switch">
                            <input type="checkbox" name="auto_relance" value="1" <?php checked( $s['auto_relance'] ?? false ); ?>>
                            <span class="acdc-switch-slider"></span>
                        </label>
                        <p class="acdc-help">Relance les signataires n'ayant pas ouvert le lien.</p>
                    </div>
                </div>
                <div class="acdc-contract-grid">
                    <div class="acdc-contract-label">Délai avant relance (heures)</div>
                    <div><input type="number" name="relance_delai_h" value="<?php echo esc_attr( $s['relance_delai_h'] ?? '48' ); ?>" min="1" max="168" style="max-width:100px"></div>
                </div>

                <div class="acdc-form-actions" style="margin-top:24px">
                    <button type="submit" class="acdc-button acdc-button-primary">Enregistrer</button>
                </div>
            </form>
        </div>
        <?php
    }

    /* ===================================================================
     * Handlers POST front-office
     * ================================================================= */

    public function handle_portal_resend() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès réservé.' ); }
        $request_id = absint( $_GET['request_id'] ?? 0 );
        check_admin_referer( 'acdc_sig_portal_resend_' . $request_id );

        global $wpdb;

        // Anti-double-envoi : ignorer si un renvoi a déjà eu lieu dans les 90 dernières secondes
        $last_resent = $wpdb->get_var( $wpdb->prepare(
            "SELECT created_at FROM {$this->core->table_audit} WHERE request_id = %d AND event = 'resent' ORDER BY id DESC LIMIT 1",
            $request_id
        ) );
        if ( $last_resent && ( time() - strtotime( $last_resent . ' UTC' ) ) < 90 ) {
            wp_safe_redirect( $this->url( 'sig_requests', array( 'sig_ok' => 'renvoye' ) ) );
            exit;
        }

        $expires = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + ACDC_Sig_Core::TOKEN_TTL_HOURS * 3600 );
        $wpdb->update( $this->core->table_requests,
            array( 'status' => 'envoye', 'expires_at' => $expires, 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => $request_id )
        );
        $this->email->send_signature_email( $request_id );
        $this->core->log_event( $request_id, 'resent', "E-mail renvoyé depuis l'extranet." );
        wp_safe_redirect( $this->url( 'sig_requests', array( 'sig_ok' => 'renvoye' ) ) );
        exit;
    }

    public function handle_portal_delete() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès réservé.' ); }
        $request_id = absint( $_GET['request_id'] ?? 0 );
        check_admin_referer( 'acdc_sig_portal_delete_' . $request_id );

        global $wpdb;
        $wpdb->update( $this->core->table_requests,
            array( 'status' => 'supprime', 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => $request_id )
        );
        $this->core->log_event( $request_id, 'deleted', "Demande supprimée depuis l'extranet." );
        wp_safe_redirect( $this->url( 'sig_requests', array( 'sig_ok' => 'supprime' ) ) );
        exit;
    }

    public function handle_portal_new() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès réservé.' ); }
        check_admin_referer( 'acdc_sig_portal_new' );

        $signer_name  = sanitize_text_field( wp_unslash( $_POST['signer_name']  ?? '' ) );
        $signer_email = sanitize_email( wp_unslash( $_POST['signer_email'] ?? '' ) );
        $signer_role  = sanitize_text_field( wp_unslash( $_POST['signer_role']  ?? '' ) );
        $doc_type     = sanitize_key( wp_unslash( $_POST['doc_type']     ?? 'emargement' ) );
        $sig_level    = sanitize_key( wp_unslash( $_POST['sig_level']    ?? 'simple' ) );
        $session_id   = absint( $_POST['session_id'] ?? 0 );
        $doc_source   = sanitize_key( wp_unslash( $_POST['doc_source']   ?? 'upload' ) );
        $doc_url_in   = esc_url_raw( wp_unslash( $_POST['doc_url_input'] ?? '' ) );
        $notes        = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
        $ttl_hours    = min( 168, max( 1, absint( $_POST['ttl_hours'] ?? ACDC_Sig_Core::TOKEN_TTL_HOURS ) ) );

        if ( empty( $signer_name ) || empty( $signer_email ) ) {
            wp_safe_redirect( $this->url( 'sig_new', array( 'sig_err' => 'Nom et e-mail obligatoires.' ) ) );
            exit;
        }
        if ( ! array_key_exists( $doc_type, ACDC_Sig_Core::DOC_TYPES ) ) { $doc_type = 'emargement'; }
        if ( ! in_array( $sig_level, array( ACDC_Sig_Core::LEVEL_SIMPLE, ACDC_Sig_Core::LEVEL_RENFORCE ), true ) ) { $sig_level = ACDC_Sig_Core::LEVEL_SIMPLE; }

        $doc_url = $doc_path = '';
        if ( 'upload' === $doc_source && ! empty( $_FILES['doc_file']['tmp_name'] ) ) {
            $upload = $this->pdf->upload_pdf( $_FILES['doc_file'] );
            if ( ! is_wp_error( $upload ) ) { $doc_url = $upload['url']; $doc_path = $upload['file']; }
        } elseif ( 'url' === $doc_source && ! empty( $doc_url_in ) ) {
            $doc_url = $this->core->is_safe_doc_url( $doc_url_in ) ? $doc_url_in : '';
        }

        $request_id = $this->core->create_signature_request( array(
            'signer_name'  => $signer_name,
            'signer_email' => $signer_email,
            'signer_role'  => $signer_role,
            'doc_type'     => $doc_type,
            'sig_level'    => $sig_level,
            'session_id'   => $session_id ?: null,
            'doc_url'      => $doc_url,
            'doc_path'     => $doc_path,
            'notes'        => $notes,
            'ttl_hours'    => $ttl_hours,
        ) );

        if ( ! $request_id ) {
            wp_safe_redirect( $this->url( 'sig_new', array( 'sig_err' => 'Erreur lors de la création.' ) ) );
            exit;
        }
        $this->email->send_signature_email( $request_id );
        wp_safe_redirect( $this->url( 'sig_requests', array( 'sig_ok' => 'envoye' ) ) );
        exit;
    }

    public function handle_portal_settings() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès réservé.' ); }
        check_admin_referer( 'acdc_sig_portal_settings' );

        update_option( ACDC_Sig_Core::OPTION_SETTINGS, array(
            'from_name'       => sanitize_text_field( wp_unslash( $_POST['from_name']       ?? '' ) ),
            'from_email'      => sanitize_email( wp_unslash( $_POST['from_email']      ?? '' ) ),
            'email_subject'   => sanitize_text_field( wp_unslash( $_POST['email_subject']   ?? '' ) ),
            'email_intro'     => sanitize_textarea_field( wp_unslash( $_POST['email_intro']     ?? '' ) ),
            'btn_color'       => sanitize_hex_color( wp_unslash( $_POST['btn_color']       ?? '#c9a84c' ) ) ?: '#c9a84c',
            'btn_label'       => sanitize_text_field( wp_unslash( $_POST['btn_label']       ?? '' ) ),
            'legal_notice'    => sanitize_textarea_field( wp_unslash( $_POST['legal_notice']    ?? '' ) ),
            'admin_email'     => sanitize_email( wp_unslash( $_POST['admin_email']      ?? '' ) ),
            'auto_relance'    => ! empty( $_POST['auto_relance'] ),
            'relance_delai_h' => min( 168, max( 1, absint( $_POST['relance_delai_h'] ?? 48 ) ) ),
        ) );

        wp_safe_redirect( $this->url( 'sig_settings', array( 'sig_ok' => 'saved' ) ) );
        exit;
    }
}
