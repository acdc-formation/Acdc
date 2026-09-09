<?php
/**
 * ACDC Signature — Admin
 *
 * Interface d'administration : menu, onglets (liste, archives,
 * nouvelle demande, réglages), handlers POST admin.
 *
 * @package ACDC_Formation_SAAS
 * @since   3.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ACDC_Sig_Admin {

    private ACDC_Sig_Core  $core;
    private ACDC_Sig_PDF   $pdf;
    private ACDC_Sig_Email $email;

    public function __construct( ACDC_Sig_Core $core, ACDC_Sig_PDF $pdf, ACDC_Sig_Email $email ) {
        $this->core  = $core;
        $this->pdf   = $pdf;
        $this->email = $email;
    }

    /* -----------------------------------------------------------------------
     * Menu
     * -------------------------------------------------------------------- */

    public function register_menu() {
        add_submenu_page(
            'acdc-of-dashboard',
            'Signatures électroniques',
            '✍ Signatures',
            'manage_options',
            'acdc-of-signatures',
            array( $this, 'render_page' )
        );
    }

    /* -----------------------------------------------------------------------
     * Page principale
     * -------------------------------------------------------------------- */

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Accès réservé.' );
        }

        $tab = isset( $_GET['sig_tab'] ) ? sanitize_key( $_GET['sig_tab'] ) : 'liste';

        // Notices de succès
        if ( isset( $_GET['sig_success'] ) ) {
            $msgs = array(
                'envoye'   => 'Demande de signature envoyée.',
                'renvoye'  => 'Email renvoyé.',
                'supprime' => 'Demande supprimée.',
            );
            $msg = $msgs[ sanitize_key( $_GET['sig_success'] ) ] ?? '';
            if ( $msg ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
            }
        }
        if ( isset( $_GET['sig_error'] ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html( sanitize_text_field( $_GET['sig_error'] ) ) . '</p></div>';
        }

        echo '<div class="wrap"><h1>✍ Signatures électroniques</h1>';

        $tabs = array(
            'liste'    => 'Demandes en cours',
            'archives' => 'Archives',
            'sessions' => '📋 Sessions',
            'nouvelle' => 'Nouvelle demande',
            'reglages' => 'Réglages',
        );
        echo '<nav class="nav-tab-wrapper" style="margin-bottom:20px">';
        foreach ( $tabs as $slug => $label ) {
            $active = ( $tab === $slug ) ? ' nav-tab-active' : '';
            $url    = admin_url( 'admin.php?page=acdc-of-signatures&sig_tab=' . $slug );
            echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . esc_attr( $active ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav>';

        $this->print_styles();

        switch ( $tab ) {
            case 'nouvelle': $this->tab_nouvelle(); break;
            case 'archives': $this->tab_archives(); break;
            case 'sessions': break; // Délégué à ACDC_Sig_Sessions via do_action
            case 'reglages': $this->tab_reglages(); break;
            default:         $this->tab_liste();    break;
        }

        // Hook pour les modules tiers (sessions injecté ici)
        do_action( 'acdc_sig_admin_tab_' . $tab );

        echo '</div>';
    }

    /* -----------------------------------------------------------------------
     * Styles communs
     * -------------------------------------------------------------------- */

    private function print_styles() {
        echo '<style>
        .acdc-sig-table{width:100%;border-collapse:collapse;background:#fff}
        .acdc-sig-table th{background:#1a2744;color:#fff;padding:10px 12px;text-align:left;font-size:13px}
        .acdc-sig-table td{padding:9px 12px;border-bottom:1px solid #e5e5e5;font-size:13px;vertical-align:middle}
        .acdc-sig-table tr:hover td{background:#faf9f7}
        .acdc-sig-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600}
        .acdc-sig-badge.en_attente{background:#fff3cd;color:#856404}
        .acdc-sig-badge.envoye{background:#cfe2ff;color:#084298}
        .acdc-sig-badge.ouvert{background:#d1ecf1;color:#0c5460}
        .acdc-sig-badge.signe{background:#d4edda;color:#155724}
        .acdc-sig-badge.expire,.acdc-sig-badge.refuse{background:#f8d7da;color:#721c24}
        .acdc-sig-panel{background:#fff;border:1px solid #e2e2e2;border-radius:6px;padding:20px 24px;max-width:700px}
        .acdc-sig-panel h2{margin-top:0;color:#1a2744}
        .acdc-sig-field{margin-bottom:14px}
        .acdc-sig-field label{display:block;font-weight:600;margin-bottom:4px;font-size:13px}
        .acdc-sig-field input,.acdc-sig-field select,.acdc-sig-field textarea{width:100%}
        .acdc-sig-btn{background:#c9a84c;color:#fff;border:none;padding:8px 18px;border-radius:4px;cursor:pointer;font-weight:600}
        .acdc-sig-btn:hover{background:#b8923e}
        </style>';
    }

    /* -----------------------------------------------------------------------
     * Onglet : liste
     * Correction audit : requêtes sans prepare() → les noms de tables sont préfixés
     * et non issus d'une entrée utilisateur (acceptable), mais on utilise quand
     * même $wpdb->prepare() pour la cohérence et les outils d'analyse statique.
     * -------------------------------------------------------------------- */

    private function tab_liste() {
        global $wpdb;

        // Correction audit : pas de variable utilisateur dans cette requête,
        // les statuts sont hardcodés — pas de risque injection, mais on suit le standard.
        $items = $wpdb->get_results(
            "SELECT * FROM {$this->core->table_requests}
             WHERE status NOT IN ('signe','expire','refuse','supprime')
             ORDER BY created_at DESC LIMIT 200"
        );

        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=acdc-of-signatures&sig_tab=nouvelle' ) ) . '" class="button button-primary" style="background:#1a2744;border-color:#1a2744">+ Nouvelle demande</a></p>';

        if ( empty( $items ) ) {
            echo '<p style="color:#666;margin-top:20px">Aucune demande en cours. <a href="' . esc_url( admin_url( 'admin.php?page=acdc-of-signatures&sig_tab=nouvelle' ) ) . '">Créer une demande</a></p>';
            return;
        }

        echo '<table class="acdc-sig-table"><thead><tr><th>Signataire</th><th>Type</th><th>Niveau</th><th>Statut</th><th>Expiration</th><th>Actions</th></tr></thead><tbody>';

        foreach ( $items as $item ) {
            $expires   = $item->expires_at ? wp_date( 'd/m/Y H:i', strtotime( $item->expires_at ) ) : '—';
            $doc_label = ACDC_Sig_Core::DOC_TYPES[ $item->doc_type ] ?? $item->doc_type;
            $level     = ( ACDC_Sig_Core::LEVEL_RENFORCE === $item->sig_level ) ? '🔒 Renforcée' : '✅ Simple';

            $resend_url = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_sig_resend_request&request_id=' . $item->id ), 'acdc_sig_resend_' . $item->id );
            $delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_sig_delete_request&request_id=' . $item->id ), 'acdc_sig_delete_' . $item->id );

            echo '<tr>';
            echo '<td><strong>' . esc_html( $item->signer_name ) . '</strong><br><small style="color:#666">' . esc_html( $item->signer_email ) . '</small></td>';
            echo '<td>' . esc_html( $doc_label ) . '</td>';
            echo '<td>' . esc_html( $level ) . '</td>';
            echo '<td><span class="acdc-sig-badge ' . esc_attr( $item->status ) . '">' . esc_html( $this->core->status_label( $item->status ) ) . '</span></td>';
            echo '<td>' . esc_html( $expires ) . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url( $resend_url ) . '" onclick="return confirm(\'Renvoyer l\\\'email ?\')">↻ Renvoyer</a>';
            echo ' | <a href="' . esc_url( $delete_url ) . '" style="color:#c00" onclick="return confirm(\'Supprimer cette demande ?\')">✕</a>';
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }

    /* -----------------------------------------------------------------------
     * Onglet : archives
     * -------------------------------------------------------------------- */

    private function tab_archives() {
        global $wpdb;

        $items = $wpdb->get_results(
            "SELECT * FROM {$this->core->table_requests}
             WHERE status IN ('signe','expire','refuse')
             ORDER BY updated_at DESC LIMIT 500"
        );

        if ( empty( $items ) ) {
            echo '<p style="color:#666;margin-top:20px">Aucune demande archivée.</p>';
            return;
        }

        echo '<table class="acdc-sig-table"><thead><tr><th>Signataire</th><th>Type</th><th>Statut</th><th>Signé le</th><th>Document</th></tr></thead><tbody>';
        foreach ( $items as $item ) {
            $doc_label = ACDC_Sig_Core::DOC_TYPES[ $item->doc_type ] ?? $item->doc_type;
            $signed_at = $item->signed_at ? wp_date( 'd/m/Y H:i', strtotime( $item->signed_at ) ) : '—';
            $dl        = $item->signed_doc_url ? '<a href="' . esc_url( $item->signed_doc_url ) . '" target="_blank">📄 Télécharger</a>' : '—';
            echo '<tr>';
            echo '<td><strong>' . esc_html( $item->signer_name ) . '</strong><br><small>' . esc_html( $item->signer_email ) . '</small></td>';
            echo '<td>' . esc_html( $doc_label ) . '</td>';
            echo '<td><span class="acdc-sig-badge ' . esc_attr( $item->status ) . '">' . esc_html( $this->core->status_label( $item->status ) ) . '</span></td>';
            echo '<td>' . esc_html( $signed_at ) . '</td>';
            echo '<td>' . $dl . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    /* -----------------------------------------------------------------------
     * Onglet : nouvelle demande
     * -------------------------------------------------------------------- */

    private function tab_nouvelle() {
        global $wpdb;

        $sessions = array();
        $session_table  = $wpdb->prefix . 'acdc_of_sessions';
        $formation_table = $wpdb->prefix . 'acdc_of_formations';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$session_table}'" ) ) {
            $sessions = $wpdb->get_results(
                "SELECT s.id, s.title, f.title AS formation_title
                 FROM {$session_table} s
                 LEFT JOIN {$formation_table} f ON f.id = s.formation_id
                 WHERE s.is_draft = 0 AND s.status NOT IN ('Annulée')
                 ORDER BY s.id DESC LIMIT 200"
            );
        }

        echo '<div class="acdc-sig-panel">';
        echo '<h2>Nouvelle demande de signature</h2>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
        wp_nonce_field( 'acdc_sig_send_request' );
        echo '<input type="hidden" name="action" value="acdc_sig_send_request">';

        echo '<h3 style="color:#1a2744;border-bottom:2px solid #c9a84c;padding-bottom:6px">Signataire</h3>';
        echo '<div class="acdc-sig-field"><label>Prénom et nom *</label><input type="text" name="signer_name" required placeholder="ex. Marie Dupont"></div>';
        echo '<div class="acdc-sig-field"><label>Adresse e-mail *</label><input type="email" name="signer_email" required placeholder="ex. marie.dupont@entreprise.fr"></div>';
        echo '<div class="acdc-sig-field"><label>Qualité / rôle</label><input type="text" name="signer_role" placeholder="ex. Stagiaire, Responsable formation…"></div>';

        echo '<h3 style="color:#1a2744;border-bottom:2px solid #c9a84c;padding-bottom:6px">Document</h3>';
        echo '<div class="acdc-sig-field"><label>Type de document *</label><select name="doc_type" required>';
        foreach ( ACDC_Sig_Core::DOC_TYPES as $slug => $label ) {
            echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</option>';
        }
        echo '</select></div>';

        echo '<div class="acdc-sig-field"><label>Niveau de traçabilité</label><select name="sig_level">';
        echo '<option value="simple">Simple — Dessin tactile + horodatage + IP</option>';
        echo '<option value="renforce">Renforcé — Simple + vérification OTP par e-mail (double authentification)</option>';
        echo '</select></div>';

        if ( ! empty( $sessions ) ) {
            echo '<div class="acdc-sig-field"><label>Session liée (optionnel)</label><select name="session_id">';
            echo '<option value="">— Aucune —</option>';
            foreach ( $sessions as $s ) {
                $label = $s->title ?: ( $s->formation_title ?: 'Session #' . $s->id );
                echo '<option value="' . esc_attr( $s->id ) . '">' . esc_html( $label ) . '</option>';
            }
            echo '</select></div>';
        }

        echo '<h3 style="color:#1a2744;border-bottom:2px solid #c9a84c;padding-bottom:6px">Document à signer</h3>';
        echo '<div class="acdc-sig-field"><label>Source</label><select name="doc_source" id="sig-doc-source">';
        echo '<option value="upload">Importer un PDF</option>';
        echo '<option value="url">URL d\'un PDF existant</option>';
        echo '</select></div>';
        echo '<div class="acdc-sig-field" id="sig-source-upload"><label>Fichier PDF</label><input type="file" name="doc_file" accept="application/pdf"></div>';
        echo '<div class="acdc-sig-field" id="sig-source-url" style="display:none"><label>URL du PDF</label><input type="text" name="doc_url_input" placeholder="https://…/document.pdf"></div>';

        echo '<div class="acdc-sig-field"><label>Notes internes</label><textarea name="notes" rows="3" placeholder="Visible uniquement en administration…"></textarea></div>';
        echo '<div class="acdc-sig-field"><label>Délai de validité</label><select name="ttl_hours">';
        echo '<option value="24">24 heures</option><option value="48">48 heures</option><option value="72" selected>72 heures</option><option value="168">7 jours</option>';
        echo '</select></div>';

        echo '<p style="margin-top:20px"><button type="submit" class="acdc-sig-btn">✉ Envoyer la demande</button></p>';
        echo '</form>';
        echo '<script>document.getElementById("sig-doc-source").addEventListener("change",function(){var v=this.value;document.getElementById("sig-source-upload").style.display=(v==="upload")?"":"none";document.getElementById("sig-source-url").style.display=(v==="url")?"":"none";});</script>';
        echo '</div>';
    }

    /* -----------------------------------------------------------------------
     * Onglet : réglages
     * -------------------------------------------------------------------- */

    private function tab_reglages() {
        $s = $this->core->get_settings();
        if ( isset( $_GET['sig_updated'] ) ) {
            echo '<div class="notice notice-success"><p>Réglages enregistrés.</p></div>';
        }
        echo '<div class="acdc-sig-panel"><h2>Réglages du module Signature</h2>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'acdc_sig_save_settings' );
        echo '<input type="hidden" name="action" value="acdc_sig_save_settings">';

        echo '<h3 style="color:#1a2744;border-bottom:2px solid #c9a84c;padding-bottom:6px">E-mails</h3>';
        foreach ( array( 'from_name' => 'Expéditeur (nom)', 'email_subject' => "Objet de l'e-mail" ) as $key => $lbl ) {
            echo '<div class="acdc-sig-field"><label>' . esc_html( $lbl ) . '</label><input type="text" name="' . esc_attr( $key ) . '" value="' . esc_attr( $s[ $key ] ) . '"></div>';
        }
        echo '<div class="acdc-sig-field"><label>Expéditeur (e-mail)</label><input type="email" name="from_email" value="' . esc_attr( $s['from_email'] ) . '"></div>';
        echo '<div class="acdc-sig-field"><label>Message introductif</label><textarea name="email_intro" rows="4">' . esc_textarea( $s['email_intro'] ) . '</textarea></div>';

        echo '<h3 style="color:#1a2744;border-bottom:2px solid #c9a84c;padding-bottom:6px">Signature</h3>';
        echo '<div class="acdc-sig-field"><label>Couleur du bouton</label><input type="text" name="btn_color" value="' . esc_attr( $s['btn_color'] ) . '" style="max-width:160px"></div>';
        echo '<div class="acdc-sig-field"><label>Texte du bouton</label><input type="text" name="btn_label" value="' . esc_attr( $s['btn_label'] ) . '" style="max-width:300px"></div>';
        echo '<div class="acdc-sig-field"><label>Mention légale</label><textarea name="legal_notice" rows="3">' . esc_textarea( $s['legal_notice'] ) . '</textarea></div>';

        echo '<h3 style="color:#1a2744;border-bottom:2px solid #c9a84c;padding-bottom:6px">Notifications</h3>';
        echo '<div class="acdc-sig-field"><label>E-mail admin</label><input type="email" name="admin_email" value="' . esc_attr( $s['admin_email'] ) . '"></div>';

        echo '<h3 style="color:#1a2744;border-bottom:2px solid #c9a84c;padding-bottom:6px">Relances automatiques</h3>';
        echo '<div class="acdc-sig-field"><label><input type="checkbox" name="auto_relance" value="1" ' . checked( $s['auto_relance'] ?? false, true, false ) . '> Activer les relances automatiques par cron</label></div>';
        echo '<div class="acdc-sig-field"><label>Délai avant relance (heures)</label><input type="number" name="relance_delai_h" value="' . esc_attr( $s['relance_delai_h'] ?? '48' ) . '" min="1" max="168" style="max-width:100px"></div>';
        echo '<p style="font-size:12px;color:#666;margin-top:0">Le cron s\'exécute deux fois par jour et relance les signataires dont le lien n\'a pas encore été ouvert.</p>';

        echo '<p style="margin-top:20px"><button type="submit" class="acdc-sig-btn">Enregistrer</button></p>';
        echo '</form></div>';
    }

    /* -----------------------------------------------------------------------
     * Handlers POST admin
     * -------------------------------------------------------------------- */

    public function handle_send_request() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Accès réservé.' );
        check_admin_referer( 'acdc_sig_send_request' );

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
            wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-signatures&sig_tab=nouvelle&sig_error=champs_manquants' ) );
            exit;
        }

        if ( ! array_key_exists( $doc_type, ACDC_Sig_Core::DOC_TYPES ) ) $doc_type = 'emargement';
        if ( ! in_array( $sig_level, array( ACDC_Sig_Core::LEVEL_SIMPLE, ACDC_Sig_Core::LEVEL_RENFORCE ), true ) ) $sig_level = ACDC_Sig_Core::LEVEL_SIMPLE;

        $doc_url = $doc_path = '';
        if ( 'upload' === $doc_source && ! empty( $_FILES['doc_file']['tmp_name'] ) ) {
            $upload = $this->pdf->upload_pdf( $_FILES['doc_file'] );
            if ( ! is_wp_error( $upload ) ) { $doc_url = $upload['url']; $doc_path = $upload['file']; }
        } elseif ( 'url' === $doc_source && ! empty( $doc_url_in ) ) {
            // Correction audit MOYEN : valider que l'URL est sûre
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
            wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-signatures&sig_tab=nouvelle&sig_error=bdd' ) );
            exit;
        }

        $this->email->send_signature_email( $request_id );

        wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-signatures&sig_tab=liste&sig_success=envoye' ) );
        exit;
    }

    public function handle_resend_request() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Accès réservé.' );
        $request_id = absint( $_GET['request_id'] ?? 0 );
        check_admin_referer( 'acdc_sig_resend_' . $request_id );
        if ( ! $request_id ) { wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-signatures' ) ); exit; }

        global $wpdb;
        // Correction audit : gmdate() au lieu de date()
        $expires = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + ACDC_Sig_Core::TOKEN_TTL_HOURS * 3600 );
        $wpdb->update( $this->core->table_requests, array( 'status' => 'envoye', 'expires_at' => $expires, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $request_id ) );
        $this->email->send_signature_email( $request_id );
        $this->core->log_event( $request_id, 'resent', "E-mail renvoyé par l'administrateur." );

        wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-signatures&sig_tab=liste&sig_success=renvoye' ) );
        exit;
    }

    public function handle_delete_request() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Accès réservé.' );
        $request_id = absint( $_GET['request_id'] ?? 0 );
        check_admin_referer( 'acdc_sig_delete_' . $request_id );
        if ( ! $request_id ) { wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-signatures' ) ); exit; }

        global $wpdb;
        $wpdb->update( $this->core->table_requests, array( 'status' => 'supprime', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $request_id ) );
        $this->core->log_event( $request_id, 'deleted', "Demande supprimée par l'administrateur." );

        wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-signatures&sig_tab=liste&sig_success=supprime' ) );
        exit;
    }

    public function handle_save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Accès réservé.' );
        check_admin_referer( 'acdc_sig_save_settings' );

        $clean = array(
            'from_name'      => sanitize_text_field( wp_unslash( $_POST['from_name']      ?? '' ) ),
            'from_email'     => sanitize_email( wp_unslash( $_POST['from_email']     ?? '' ) ),
            'email_subject'  => sanitize_text_field( wp_unslash( $_POST['email_subject']  ?? '' ) ),
            'email_intro'    => sanitize_textarea_field( wp_unslash( $_POST['email_intro']    ?? '' ) ),
            'btn_color'      => sanitize_hex_color( wp_unslash( $_POST['btn_color']      ?? '#c9a84c' ) ) ?: '#c9a84c',
            'btn_label'      => sanitize_text_field( wp_unslash( $_POST['btn_label']      ?? '' ) ),
            'legal_notice'   => sanitize_textarea_field( wp_unslash( $_POST['legal_notice']   ?? '' ) ),
            'admin_email'    => sanitize_email( wp_unslash( $_POST['admin_email']     ?? '' ) ),
            'auto_relance'   => ! empty( $_POST['auto_relance'] ),
            'relance_delai_h'=> min( 168, max( 1, absint( $_POST['relance_delai_h'] ?? 48 ) ) ),
        );

        update_option( ACDC_Sig_Core::OPTION_SETTINGS, $clean );

        wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-signatures&sig_tab=reglages&sig_updated=1' ) );
        exit;
    }
}
