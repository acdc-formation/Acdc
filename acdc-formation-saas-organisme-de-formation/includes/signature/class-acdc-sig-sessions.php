<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * ACDC Signature — Sessions (Phase 3)
 *
 * Intégration avec les sessions de formation ACDC :
 * - Onglet sessions avec jauge de signatures
 * - Fiche session : tableau stagiaires + statuts par document
 * - Envoi groupé vers tous les stagiaires
 * - Envoi individuel par stagiaire/document
 * - Cron de relances automatiques
 *
 * Correction audit : cron nettoyé à la désactivation (géré dans l'orchestrateur).
 * Correction audit : IN() avec liste d'IDs — IDs castés absint, sans entrée utilisateur.
 * Correction audit : arrow function fn() remplacée pour compatibilité PHP 7.3+.
 *
 * @package ACDC_Formation_SAAS
 * @since   3.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ACDC_Sig_Sessions {

    private ACDC_Sig_Core  $core;
    private ACDC_Sig_Email $email;

    public function __construct( ACDC_Sig_Core $core, ACDC_Sig_Email $email ) {
        $this->core  = $core;
        $this->email = $email;
    }

    /* -----------------------------------------------------------------------
     * Hooks
     * -------------------------------------------------------------------- */

    public function register_hooks() {
        add_action( 'admin_post_acdc_sig_send_session_bulk',   array( $this, 'handle_send_bulk' ) );
        add_action( 'admin_post_acdc_sig_send_single_learner', array( $this, 'handle_send_single' ) );
        add_action( 'acdc_sig_cron_relances',                  array( $this, 'process_cron' ) );
        add_action( 'acdc_sig_admin_tab_sessions',             array( $this, 'render_tab' ) );

        if ( ! wp_next_scheduled( 'acdc_sig_cron_relances' ) ) {
            wp_schedule_event( time(), 'twicedaily', 'acdc_sig_cron_relances' );
        }
    }

    /* -----------------------------------------------------------------------
     * Onglet Sessions — liste
     * -------------------------------------------------------------------- */

    public function render_tab() {
        global $wpdb;

        $action     = isset( $_GET['sig_action'] ) ? sanitize_key( $_GET['sig_action'] ) : 'list';
        $session_id = isset( $_GET['sig_session'] ) ? absint( $_GET['sig_session'] ) : 0;

        if ( 'view' === $action && $session_id ) {
            $this->render_detail( $session_id );
            return;
        }

        $session_table   = $wpdb->prefix . 'acdc_of_sessions';
        $formation_table = $wpdb->prefix . 'acdc_of_formations';
        $learner_table   = $wpdb->prefix . 'acdc_of_learners';

        $search = isset( $_GET['sig_s'] ) ? sanitize_text_field( wp_unslash( $_GET['sig_s'] ) ) : '';
        $where  = "WHERE s.is_draft = 0 AND s.status NOT IN ('Annulée')";
        if ( $search ) {
            $like  = '%' . $wpdb->esc_like( $search ) . '%';
            $where .= $wpdb->prepare( ' AND (s.title LIKE %s OR f.title LIKE %s)', $like, $like );
        }

        $sessions = $wpdb->get_results(
            "SELECT s.id, s.title, s.start_date, s.end_date, s.status,
                    f.title AS formation_title,
                    COUNT(DISTINCT l.id) AS learner_count
             FROM {$session_table} s
             LEFT JOIN {$formation_table} f ON f.id = s.formation_id
             LEFT JOIN {$learner_table} l ON l.session_id = s.id
             {$where}
             GROUP BY s.id
             ORDER BY COALESCE(s.start_date, s.end_date) DESC
             LIMIT 200"
        );

        // Comptage signatures par session
        // Correction audit : array_map sans arrow function (PHP 7.3 compat)
        $sig_counts = array();
        if ( ! empty( $sessions ) ) {
            $ids = array_map( function( $s ) { return (int) $s->id; }, $sessions );
            $in  = implode( ',', $ids );
            $rows = $wpdb->get_results(
                "SELECT session_id, status, COUNT(*) AS cnt
                 FROM {$this->core->table_requests}
                 WHERE session_id IN ({$in}) AND status != 'supprime'
                 GROUP BY session_id, status"
            );
            foreach ( $rows as $r ) {
                $sig_counts[ (int) $r->session_id ][ $r->status ] = (int) $r->cnt;
            }
        }

        $base_url = admin_url( 'admin.php?page=acdc-of-signatures&sig_tab=sessions' );

        echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" style="margin-bottom:16px;display:flex;gap:10px;align-items:center">';
        echo '<input type="hidden" name="page" value="acdc-of-signatures"><input type="hidden" name="sig_tab" value="sessions">';
        echo '<input type="search" name="sig_s" value="' . esc_attr( $search ) . '" placeholder="Rechercher une session…" style="max-width:320px">';
        echo '<button type="submit" class="button">Rechercher</button>';
        if ( $search ) echo ' <a href="' . esc_url( $base_url ) . '" class="button button-secondary">✕ Effacer</a>';
        echo '</form>';

        if ( empty( $sessions ) ) { echo '<p style="color:#666">Aucune session active.</p>'; return; }

        echo '<table class="acdc-sig-table"><thead><tr><th>Session / Formation</th><th>Dates</th><th>Stagiaires</th><th>Émargements signés</th><th>Actions</th></tr></thead><tbody>';

        foreach ( $sessions as $s ) {
            $sc      = $sig_counts[ (int) $s->id ] ?? array();
            $signes  = $sc['signe']  ?? 0;
            $envoyes = ( $sc['envoye'] ?? 0 ) + ( $sc['ouvert'] ?? 0 );
            $total   = (int) $s->learner_count;
            $pct     = $total > 0 ? round( $signes / $total * 100 ) : 0;

            $dates    = $s->start_date ? wp_date( 'd/m/Y', strtotime( $s->start_date ) ) : '—';
            if ( $s->end_date && $s->end_date !== $s->start_date ) $dates .= ' → ' . wp_date( 'd/m/Y', strtotime( $s->end_date ) );
            $view_url = add_query_arg( array( 'sig_action' => 'view', 'sig_session' => $s->id ), $base_url );
            $title    = $s->title ?: ( $s->formation_title ?: 'Session #' . $s->id );

            echo '<tr>';
            echo '<td><strong><a href="' . esc_url( $view_url ) . '" style="color:#1a2744;text-decoration:none">' . esc_html( $title ) . '</a></strong>';
            if ( $s->formation_title && $s->formation_title !== $s->title ) echo '<br><small style="color:#666">' . esc_html( $s->formation_title ) . '</small>';
            echo '</td><td>' . esc_html( $dates ) . '</td><td>' . esc_html( $total ) . '</td>';
            echo '<td>';
            if ( $total > 0 ) {
                echo '<div style="display:flex;align-items:center;gap:8px">';
                echo '<div style="flex:1;background:#e2e2e2;border-radius:4px;height:8px;min-width:80px"><div style="width:' . $pct . '%;background:#c9a84c;height:8px;border-radius:4px"></div></div>';
                echo '<span style="font-size:12px;white-space:nowrap">' . $signes . '/' . $total . '</span>';
                if ( $envoyes > 0 ) echo ' <span class="acdc-sig-badge envoye" style="font-size:10px">' . $envoyes . ' en attente</span>';
                echo '</div>';
            } else { echo '<span style="color:#999;font-size:12px">Aucun stagiaire</span>'; }
            echo '</td><td><a href="' . esc_url( $view_url ) . '" class="button button-small">Gérer les signatures</a></td></tr>';
        }
        echo '</tbody></table>';
    }

    /* -----------------------------------------------------------------------
     * Fiche session — détail
     * -------------------------------------------------------------------- */

    private function render_detail( $session_id ) {
        global $wpdb;

        $session_table  = $wpdb->prefix . 'acdc_of_sessions';
        $learner_table  = $wpdb->prefix . 'acdc_of_learners';
        $group_table    = $wpdb->prefix . 'acdc_of_groups';

        $session = $wpdb->get_row( $wpdb->prepare(
            "SELECT s.*, f.title AS formation_title, c.name AS company_name
             FROM {$session_table} s
             LEFT JOIN {$wpdb->prefix}acdc_of_formations f ON f.id = s.formation_id
             LEFT JOIN {$wpdb->prefix}acdc_of_companies c ON c.id = s.company_id
             WHERE s.id = %d GROUP BY s.id",
            $session_id
        ) );

        if ( ! $session ) { echo '<p style="color:#c00">Session introuvable.</p>'; return; }

        $learners = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, first_name, COALESCE(NULLIF(usage_last_name,''), last_name) AS last_name, email, status
             FROM {$learner_table}
             WHERE session_id = %d ORDER BY last_name ASC, first_name ASC",
            $session_id
        ) );

        $back_url     = admin_url( 'admin.php?page=acdc-of-signatures&sig_tab=sessions' );
        $session_title = $session->title ?: ( $session->formation_title ?: 'Session #' . $session->id );
        $dates        = $session->start_date ? wp_date( 'd/m/Y', strtotime( $session->start_date ) ) : '—';
        if ( $session->end_date && $session->end_date !== $session->start_date ) $dates .= ' → ' . wp_date( 'd/m/Y', strtotime( $session->end_date ) );

        // Demandes existantes
        $existing     = array();
        $existing_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->core->table_requests}
             WHERE session_id = %d AND status != 'supprime' ORDER BY created_at DESC",
            $session_id
        ) );
        foreach ( $existing_rows as $row ) {
            $lid = (int) $row->learner_id;
            if ( ! isset( $existing[ $lid ][ $row->doc_type ] ) ) $existing[ $lid ][ $row->doc_type ] = $row;
        }

        // Notice succès
        if ( isset( $_GET['sig_success'] ) ) {
            $msgs = array( 'bulk_envoye' => 'Demandes envoyées.', 'single_envoye' => 'Demande envoyée.' );
            $msg  = $msgs[ sanitize_key( $_GET['sig_success'] ) ] ?? 'Opération réussie.';
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
        }

        // En-tête
        echo '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px">';
        echo '<div><h2 style="margin:0;color:#1a2744">' . esc_html( $session_title ) . '</h2>';
        if ( $session->formation_title ) echo '<p style="margin:4px 0 0;color:#666;font-size:13px">' . esc_html( $session->formation_title ) . ' — ' . esc_html( $dates ) . '</p>';
        echo '</div><a href="' . esc_url( $back_url ) . '" class="button button-secondary">← Retour</a></div>';

        if ( empty( $learners ) ) {
            echo '<div class="acdc-sig-panel" style="text-align:center;padding:32px"><p style="color:#666">Aucun stagiaire inscrit.</p></div>';
            return;
        }

        // Envoi groupé
        echo '<div class="acdc-sig-panel" style="margin-bottom:20px">';
        echo '<h3 style="margin-top:0;color:#1a2744">Envoi groupé</h3>';
        echo '<p style="font-size:13px;color:#555;margin-bottom:14px">Seuls les stagiaires avec e-mail et sans demande en cours seront contactés.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'acdc_sig_send_session_bulk_' . $session_id );
        echo '<input type="hidden" name="action" value="acdc_sig_send_session_bulk">';
        echo '<input type="hidden" name="session_id" value="' . esc_attr( $session_id ) . '">';
        echo '<div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end">';
        echo '<div><label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px">Type de document</label><select name="doc_type">';
        foreach ( ACDC_Sig_Core::DOC_TYPES as $slug => $label ) echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</option>';
        echo '</select></div>';
        echo '<div><label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px">Niveau</label><select name="sig_level"><option value="simple">Simple</option><option value="renforce">Renforcé</option></select></div>';
        echo '<div><label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px">Validité</label><select name="ttl_hours"><option value="48">48 h</option><option value="72" selected>72 h</option><option value="168">7 jours</option></select></div>';
        echo '<div><button type="submit" class="acdc-sig-btn" onclick="return confirm(\'Envoyer à tous les stagiaires ?\')">✉ Envoyer à tous</button></div>';
        echo '</div></form></div>';

        // Tableau stagiaires
        echo '<div class="acdc-sig-panel"><h3 style="margin-top:0;color:#1a2744">Stagiaires (' . count( $learners ) . ')</h3>';
        echo '<table class="acdc-sig-table"><thead><tr><th>Stagiaire</th><th>E-mail</th><th>Émargement</th><th>Convention</th><th>Contrat</th><th>Convocation</th><th>↻</th></tr></thead><tbody>';

        foreach ( $learners as $learner ) {
            $lid       = (int) $learner->id;
            $full_name = trim( $learner->first_name . ' ' . $learner->last_name );
            $has_email = ! empty( $learner->email );

            echo '<tr><td><strong>' . esc_html( $full_name ) . '</strong></td>';
            echo '<td>' . ( $has_email ? esc_html( $learner->email ) : '<span style="color:#c00;font-size:11px">Aucun e-mail</span>' ) . '</td>';

            foreach ( array( 'emargement', 'convention', 'contrat', 'convocation' ) as $dt ) {
                echo '<td>';
                if ( isset( $existing[ $lid ][ $dt ] ) ) {
                    $req = $existing[ $lid ][ $dt ];
                    echo '<span class="acdc-sig-badge ' . esc_attr( $req->status ) . '">' . esc_html( $this->core->status_label( $req->status ) ) . '</span>';
                    if ( 'signe' === $req->status && $req->signed_doc_url ) echo ' <a href="' . esc_url( $req->signed_doc_url ) . '" target="_blank" style="font-size:11px">📄</a>';
                } elseif ( $has_email ) {
                    $nonce = wp_create_nonce( 'acdc_sig_single_' . $lid . '_' . $dt );
                    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
                    echo '<input type="hidden" name="action" value="acdc_sig_send_single_learner">';
                    echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '">';
                    echo '<input type="hidden" name="learner_id" value="' . esc_attr( $lid ) . '">';
                    echo '<input type="hidden" name="session_id" value="' . esc_attr( $session_id ) . '">';
                    echo '<input type="hidden" name="doc_type" value="' . esc_attr( $dt ) . '">';
                    echo '<button type="submit" class="button button-small" style="font-size:10px">✉</button></form>';
                } else { echo '<span style="color:#bbb;font-size:11px">—</span>'; }
                echo '</td>';
            }

            // Renvoi rapide émargement
            echo '<td>';
            if ( $has_email ) {
                $nonce_r = wp_create_nonce( 'acdc_sig_single_' . $lid . '_emargement' );
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
                echo '<input type="hidden" name="action" value="acdc_sig_send_single_learner">';
                echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce_r ) . '">';
                echo '<input type="hidden" name="learner_id" value="' . esc_attr( $lid ) . '">';
                echo '<input type="hidden" name="session_id" value="' . esc_attr( $session_id ) . '">';
                echo '<input type="hidden" name="doc_type" value="emargement"><input type="hidden" name="force" value="1">';
                echo '<button type="submit" class="button button-small" title="Renvoyer émargement">↻</button></form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';

        // Demandes en attente
        $active = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->core->table_requests}
             WHERE session_id = %d AND status NOT IN ('signe','expire','supprime')
             ORDER BY created_at DESC",
            $session_id
        ) );

        if ( ! empty( $active ) ) {
            echo '<div class="acdc-sig-panel" style="margin-top:20px">';
            echo '<h3 style="margin-top:0;color:#1a2744">Demandes en attente (' . count( $active ) . ')</h3>';
            echo '<table class="acdc-sig-table"><thead><tr><th>Signataire</th><th>Document</th><th>Statut</th><th>Expiration</th><th>Actions</th></tr></thead><tbody>';
            foreach ( $active as $req ) {
                $doc_label  = ACDC_Sig_Core::DOC_TYPES[ $req->doc_type ] ?? $req->doc_type;
                $expires    = $req->expires_at ? mysql2date( 'd/m/Y H:i', $req->expires_at ) : '—';
                $resend_url = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_sig_resend_request&request_id=' . $req->id ), 'acdc_sig_resend_' . $req->id );
                $delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_sig_delete_request&request_id=' . $req->id ), 'acdc_sig_delete_' . $req->id );
                echo '<tr>';
                echo '<td>' . esc_html( $req->signer_name ) . '<br><small>' . esc_html( $req->signer_email ) . '</small></td>';
                echo '<td>' . esc_html( $doc_label ) . '</td>';
                echo '<td><span class="acdc-sig-badge ' . esc_attr( $req->status ) . '">' . esc_html( $this->core->status_label( $req->status ) ) . '</span></td>';
                echo '<td>' . esc_html( $expires ) . '</td>';
                echo '<td><a href="' . esc_url( $resend_url ) . '" onclick="return confirm(\'Renvoyer ?\')">↻</a> <a href="' . esc_url( $delete_url ) . '" style="color:#c00" onclick="return confirm(\'Supprimer ?\')">✕</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
    }

    /* -----------------------------------------------------------------------
     * Handler : envoi groupé
     * -------------------------------------------------------------------- */

    public function handle_send_bulk() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Accès réservé.' );

        $session_id = absint( $_POST['session_id'] ?? 0 );
        check_admin_referer( 'acdc_sig_send_session_bulk_' . $session_id );

        $doc_type  = sanitize_key( $_POST['doc_type']  ?? 'emargement' );
        $sig_level = sanitize_key( $_POST['sig_level'] ?? 'simple' );
        $ttl_hours = min( 168, max( 1, absint( $_POST['ttl_hours'] ?? 72 ) ) );

        if ( ! array_key_exists( $doc_type, ACDC_Sig_Core::DOC_TYPES ) ) $doc_type = 'emargement';

        global $wpdb;
        $learner_table = $wpdb->prefix . 'acdc_of_learners';
        $learners = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, first_name, COALESCE(NULLIF(usage_last_name,''), last_name) AS last_name, email
             FROM {$learner_table}
             WHERE session_id = %d AND email != '' AND email IS NOT NULL",
            $session_id
        ) );

        foreach ( $learners as $learner ) {
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$this->core->table_requests}
                 WHERE session_id = %d AND learner_id = %d AND doc_type = %s
                   AND status NOT IN ('signe','expire','supprime','refuse')",
                $session_id, $learner->id, $doc_type
            ) );
            if ( $existing ) continue;

            $request_id = $this->core->create_signature_request( array(
                'signer_name'  => trim( $learner->first_name . ' ' . $learner->last_name ),
                'signer_email' => $learner->email,
                'signer_role'  => 'Stagiaire',
                'doc_type'     => $doc_type,
                'sig_level'    => $sig_level,
                'session_id'   => $session_id,
                'learner_id'   => $learner->id,
                'ttl_hours'    => $ttl_hours,
            ) );
            if ( $request_id ) $this->email->send_signature_email( $request_id );
        }

        wp_safe_redirect( add_query_arg( array( 'sig_tab' => 'sessions', 'sig_action' => 'view', 'sig_session' => $session_id, 'sig_success' => 'bulk_envoye' ), admin_url( 'admin.php?page=acdc-of-signatures' ) ) );
        exit;
    }

    /* -----------------------------------------------------------------------
     * Handler : envoi individuel
     * -------------------------------------------------------------------- */

    public function handle_send_single() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Accès réservé.' );

        $learner_id = absint( $_POST['learner_id'] ?? 0 );
        $doc_type   = sanitize_key( $_POST['doc_type'] ?? 'emargement' );
        check_admin_referer( 'acdc_sig_single_' . $learner_id . '_' . $doc_type );

        $session_id = absint( $_POST['session_id'] ?? 0 );
        $force      = ! empty( $_POST['force'] );
        $sig_level  = sanitize_key( $_POST['sig_level'] ?? ( ACDC_Sig_Core::DOC_DEFAULT_LEVEL[ $doc_type ] ?? 'simple' ) );

        if ( ! array_key_exists( $doc_type, ACDC_Sig_Core::DOC_TYPES ) ) $doc_type = 'emargement';

        global $wpdb;
        $learner_table = $wpdb->prefix . 'acdc_of_learners';
        $learner = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, first_name, COALESCE(NULLIF(usage_last_name,''), last_name) AS last_name, email
             FROM {$learner_table} WHERE id = %d",
            $learner_id
        ) );

        if ( ! $learner || empty( $learner->email ) ) {
            wp_safe_redirect( add_query_arg( array( 'sig_tab' => 'sessions', 'sig_action' => 'view', 'sig_session' => $session_id, 'sig_error' => 'no_email' ), admin_url( 'admin.php?page=acdc-of-signatures' ) ) );
            exit;
        }

        if ( $force ) {
            foreach ( array( 'envoye', 'ouvert' ) as $st ) {
                $wpdb->update( $this->core->table_requests, array( 'status' => 'expire', 'updated_at' => current_time( 'mysql' ) ), array( 'session_id' => $session_id, 'learner_id' => $learner_id, 'doc_type' => $doc_type, 'status' => $st ) );
            }
        }

        $request_id = $this->core->create_signature_request( array(
            'signer_name'  => trim( $learner->first_name . ' ' . $learner->last_name ),
            'signer_email' => $learner->email,
            'signer_role'  => 'Stagiaire',
            'doc_type'     => $doc_type,
            'sig_level'    => $sig_level,
            'session_id'   => $session_id,
            'learner_id'   => $learner_id,
        ) );

        if ( $request_id ) $this->email->send_signature_email( $request_id );

        wp_safe_redirect( add_query_arg( array( 'sig_tab' => 'sessions', 'sig_action' => 'view', 'sig_session' => $session_id, 'sig_success' => 'single_envoye' ), admin_url( 'admin.php?page=acdc-of-signatures' ) ) );
        exit;
    }

    /* -----------------------------------------------------------------------
     * Cron : relances automatiques
     * Correction audit : gmdate() au lieu de date()
     * -------------------------------------------------------------------- */

    public function process_cron() {
        global $wpdb;

        $s = $this->core->get_settings();
        if ( empty( $s['auto_relance'] ) ) return;

        $delai_h = max( 1, (int) ( $s['relance_delai_h'] ?? 48 ) );
        // Correction audit : gmdate() au lieu de date()
        $cutoff  = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $delai_h * 3600 );

        $to_relance = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->core->table_requests}
             WHERE status = 'envoye' AND updated_at <= %s AND expires_at > %s
             LIMIT 50",
            $cutoff,
            current_time( 'mysql' )
        ) );

        foreach ( $to_relance as $req ) {
            $this->email->send_signature_email( $req->id );
            $this->core->log_event( $req->id, 'relance_auto', 'Relance automatique par le cron.' );
        }
    }
}
