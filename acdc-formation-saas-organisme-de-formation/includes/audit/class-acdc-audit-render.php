<?php
/**
 * ACDC Audit Qualiopi — Rendu
 *
 * Vue admin (espace audit avec filtres) et vue auditeur (lien temporaire lecture seule).
 *
 * @package ACDC_Formation_SAAS
 * @since   3.21.09-hotfix25
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ACDC_Audit_Render {

    /** @var ACDC_Audit_Core */
    private $core;

    public function __construct( ACDC_Audit_Core $core ) {
        $this->core = $core;
    }

    /* -----------------------------------------------------------------------
     * Vue publique auditeur (?acdc_audit=view&tok=TOKEN)
     * -------------------------------------------------------------------- */
    public function maybe_handle_public() {
        if ( ! isset( $_GET['acdc_audit'] ) ) { return; }
        $mode  = sanitize_key( wp_unslash( $_GET['acdc_audit'] ) );
        $token = sanitize_text_field( wp_unslash( $_GET['tok'] ?? '' ) );
        if ( 'view' !== $mode || '' === $token ) { return; }

        $token_row = $this->core->validate_token( $token );
        status_header( 200 );
        nocache_headers();
        header( 'X-LiteSpeed-Cache-Control: no-cache' );

        ?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Espace Audit Qualiopi — ACDC Formation</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#f5f6fa;color:#1a2744;font-size:14px}
.audit-header{background:#0f2c52;color:#fff;padding:18px 32px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.audit-header h1{font-size:18px;font-weight:800}
.audit-badge{background:#c9a84c;color:#0f2c52;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700}
.audit-wrap{max-width:1100px;margin:0 auto;padding:24px 20px}
.audit-notice{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:13px}
.audit-notice-warn{background:#fff3cd;color:#856404;border:1px solid #ffc107}
.audit-notice-err{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
<?php echo $this->get_shared_css(); ?>
</style>
</head>
<body>
<div class="audit-header">
    <div>
        <h1>Espace Audit Qualiopi</h1>
        <?php if ( $token_row && $token_row->label ) : ?>
        <div style="font-size:12px;color:#c9a84c;margin-top:4px"><?php echo esc_html( $token_row->label ); ?></div>
        <?php endif; ?>
    </div>
    <?php if ( $token_row ) : ?>
    <span class="audit-badge">Accès temporaire — expire le <?php echo esc_html( wp_date( 'd/m/Y', strtotime( $token_row->expires_at ) ) ); ?></span>
    <?php endif; ?>
</div>
<div class="audit-wrap">
    <?php if ( ! $token_row ) : ?>
    <div class="audit-notice audit-notice-err">
        Ce lien est invalide, expiré ou a été révoqué. Veuillez contacter l'organisme de formation.
    </div>
    <?php else : ?>
    <div class="audit-notice audit-notice-warn">
        Accès en lecture seule — Consulté <?php echo (int) $token_row->access_count; ?> fois.
        <?php if ( $token_row->last_accessed_at ) : ?> Dernier accès : <?php echo esc_html( wp_date( 'd/m/Y à H\hi', strtotime( $token_row->last_accessed_at ) ) ); ?><?php endif; ?>
    </div>
    <?php
    $filters = $this->get_filters_from_request();
    $docs    = $this->core->get_documents( $filters );
    $this->render_filters( $filters, false );
    $this->render_doc_tree( $docs, false );
    endif; ?>
</div>
</body></html>
        <?php
        exit;
    }

    /* -----------------------------------------------------------------------
     * Vue admin — onglet "Audit Qualiopi"
     * -------------------------------------------------------------------- */
    public function render_admin_tab() {
        $sub = isset( $_GET['audit_sub'] ) ? sanitize_key( $_GET['audit_sub'] ) : 'docs';
        ?>
        <div class="acdc-audit-admin">
        <nav class="audit-subnav">
            <a href="<?php echo esc_url( $this->admin_url( array( 'audit_sub' => 'docs' ) ) ); ?>"
               class="acdc-button <?php echo 'docs' === $sub ? 'acdc-button-primary' : 'acdc-button-soft'; ?>">
                Documents justificatifs
            </a>
            <a href="<?php echo esc_url( $this->admin_url( array( 'audit_sub' => 'tokens' ) ) ); ?>"
               class="acdc-button <?php echo 'tokens' === $sub ? 'acdc-button-primary' : 'acdc-button-soft'; ?>">
                Liens auditeur
            </a>
        </nav>
        <style><?php echo $this->get_shared_css(); ?></style>
        <?php
        if ( 'tokens' === $sub ) {
            $this->render_tokens_tab();
        } else {
            $this->render_docs_tab();
        }
        echo '</div>';
    }

    private function render_docs_tab() {
        $filters = $this->get_filters_from_request();
        $docs    = $this->core->get_documents( $filters );
        $total   = $this->count_docs( $docs );

        // URL export ZIP (transmet les filtres actifs)
        $zip_args = array_merge(
            array( 'action' => 'acdc_audit_export_zip' ),
            array_filter( array(
                'formation_id' => $filters['formation_id'] ?? 0,
                'learner_id'   => $filters['learner_id'] ?? 0,
                'company_id'   => $filters['company_id'] ?? 0,
                'doc_type'     => $filters['doc_type'] ?? '',
                'date_from'    => $filters['date_from'] ?? '',
                'date_to'      => $filters['date_to'] ?? '',
            ) )
        );
        $zip_url = wp_nonce_url( admin_url( 'admin-post.php?' . http_build_query( $zip_args ) ), 'acdc_audit_export_zip' );
        ?>
        <div class="audit-section-head">
            <div>
                <h2 class="audit-section-title">Documents justificatifs</h2>
                <p class="audit-section-sub">Agrégation complète de tous les justificatifs Qualiopi — <?php echo $total; ?> document<?php echo $total > 1 ? 's' : ''; ?> trouvé<?php echo $total > 1 ? 's' : ''; ?></p>
            </div>
            <?php if ( $total > 0 ) : ?>
            <div>
                <a href="<?php echo esc_url( $zip_url ); ?>" class="acdc-button acdc-button-primary" title="Télécharger tous les documents en archive ZIP organisée par formation">
                    ⬇ Exporter en ZIP
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php
        $this->render_filters( $filters, true );
        $this->render_doc_tree( $docs, true );
    }

    private function render_tokens_tab() {
        $tokens = $this->core->get_tokens();
        ?>
        <div class="audit-section-head">
            <div>
                <h2 class="audit-section-title">Liens d'accès auditeur</h2>
                <p class="audit-section-sub">Générez un lien temporaire sécurisé à transmettre à votre auditeur Qualiopi — aucun mot de passe requis</p>
            </div>
            <div>
                <button type="button" class="acdc-button acdc-button-primary" onclick="document.getElementById('audit-create-token-form').style.display='block';this.style.display='none'">
                    + Créer un lien
                </button>
            </div>
        </div>

        <div id="audit-create-token-form" style="display:none;background:#fff;border:1px solid #e2e6ea;border-radius:10px;padding:20px 24px;margin-bottom:20px">
            <h3 style="margin:0 0 14px;font-size:14px;color:#0f2c52">Nouveau lien d'accès auditeur</h3>
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                <?php wp_nonce_field( 'acdc_audit_create_token' ); ?>
                <input type="hidden" name="action" value="acdc_audit_create_token">
                <div style="display:grid;grid-template-columns:1fr 160px auto;gap:12px;align-items:end">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#0f2c52;margin-bottom:6px">Label (ex : Audit Qualiopi 2026)</label>
                        <input type="text" name="token_label" placeholder="ex : Audit Qualiopi 2026" style="width:100%;height:40px;border:1px solid #dce4ec;border-radius:8px;padding:0 12px">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#0f2c52;margin-bottom:6px">Durée de validité</label>
                        <select name="token_days" style="width:100%;height:40px;border:1px solid #dce4ec;border-radius:8px;padding:0 12px">
                            <option value="7">7 jours</option>
                            <option value="15">15 jours</option>
                            <option value="30" selected>30 jours</option>
                            <option value="60">60 jours</option>
                            <option value="90">90 jours</option>
                        </select>
                    </div>
                    <button type="submit" class="acdc-button acdc-button-primary" style="height:40px">Générer</button>
                </div>
            </form>
        </div>

        <?php if ( empty( $tokens ) ) : ?>
        <div class="audit-empty">Aucun lien créé pour l'instant.</div>
        <?php else : ?>
        <div class="audit-tokens-list">
            <?php foreach ( $tokens as $tok ) :
                $is_expired = strtotime( $tok->expires_at ) < time();
                $is_revoked = (bool) $tok->is_revoked;
                $status_color = $is_revoked ? '#721c24' : ( $is_expired ? '#856404' : '#155724' );
                $status_bg    = $is_revoked ? '#f8d7da' : ( $is_expired ? '#fff3cd' : '#d4edda' );
                $status_label = $is_revoked ? 'Révoqué' : ( $is_expired ? 'Expiré' : 'Actif' );
                $public_url   = $this->core->get_public_url( $tok->token );
            ?>
            <div class="audit-token-row <?php echo $is_revoked || $is_expired ? 'is-inactive' : ''; ?>">
                <div class="audit-token-info">
                    <div style="font-weight:700;color:#0f2c52"><?php echo esc_html( $tok->label ?: 'Lien #' . $tok->id ); ?></div>
                    <div style="font-size:12px;color:#6b7280;margin-top:3px">
                        Créé le <?php echo esc_html( wp_date( 'd/m/Y', strtotime( $tok->created_at ) ) ); ?>
                        &nbsp;·&nbsp;
                        Expire le <?php echo esc_html( wp_date( 'd/m/Y', strtotime( $tok->expires_at ) ) ); ?>
                        &nbsp;·&nbsp;
                        Consulté <?php echo (int) $tok->access_count; ?> fois
                        <?php if ( $tok->last_accessed_at ) : ?>
                        &nbsp;·&nbsp; Dernier accès : <?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $tok->last_accessed_at ) ) ); ?>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:11px;color:#9ca3af;margin-top:4px;word-break:break-all"><?php echo esc_url( $public_url ); ?></div>
                </div>
                <div class="audit-token-actions">
                    <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:<?php echo esc_attr( $status_bg ); ?>;color:<?php echo esc_attr( $status_color ); ?>"><?php echo esc_html( $status_label ); ?></span>
                    <?php if ( ! $is_revoked && ! $is_expired ) : ?>
                    <button type="button" class="acdc-button acdc-button-soft" style="font-size:12px;padding:5px 12px" onclick="navigator.clipboard.writeText('<?php echo esc_js( $public_url ); ?>');this.textContent='✅ Copié!'">Copier</button>
                    <a href="<?php echo esc_url( $public_url ); ?>" target="_blank" class="acdc-button acdc-button-soft" style="font-size:12px;padding:5px 12px">Prévisualiser</a>
                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline" onsubmit="return confirm('Révoquer ce lien ?')">
                        <?php wp_nonce_field( 'acdc_audit_revoke_' . $tok->id ); ?>
                        <input type="hidden" name="action" value="acdc_audit_revoke_token">
                        <input type="hidden" name="token_id" value="<?php echo esc_attr( $tok->id ); ?>">
                        <button type="submit" class="acdc-button acdc-button-soft" style="font-size:12px;padding:5px 12px;color:#c00;border-color:#c00">Révoquer</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif;
    }

    /* -----------------------------------------------------------------------
     * Filtres
     * -------------------------------------------------------------------- */
    private function render_filters( $filters, $is_admin ) {
        $formations = $this->core->get_filter_formations();
        $learners   = $this->core->get_filter_learners();
        $companies  = $this->core->get_filter_companies();
        $doc_types  = $this->core->get_document_types();
        $form_action = $is_admin ? $this->admin_url() : add_query_arg( array( 'acdc_audit' => 'view', 'tok' => sanitize_text_field( $_GET['tok'] ?? '' ) ), home_url('/') );
        ?>
        <form method="get" action="<?php echo esc_url( $form_action ); ?>" class="audit-filters">
            <?php if ( $is_admin ) : ?>
            <?php /* Conserver les paramètres de navigation du portail */ ?>
            <?php foreach ( array( 'tab', 'page' ) as $_pk ) : if ( isset( $_GET[ $_pk ] ) ) : ?>
            <input type="hidden" name="<?php echo esc_attr( $_pk ); ?>" value="<?php echo esc_attr( sanitize_text_field( $_GET[ $_pk ] ) ); ?>">
            <?php endif; endforeach; ?>
            <input type="hidden" name="audit_sub" value="docs">
            <?php else : ?>
            <input type="hidden" name="acdc_audit" value="view">
            <input type="hidden" name="tok" value="<?php echo esc_attr( $_GET['tok'] ?? '' ); ?>">
            <?php endif; ?>
            <div class="audit-filters-grid">
                <div class="audit-filter-field">
                    <label>Formation</label>
                    <select name="formation_id">
                        <option value="">Toutes</option>
                        <?php foreach ( $formations as $f ) : ?>
                        <option value="<?php echo esc_attr( $f->id ); ?>" <?php selected( $filters['formation_id'] ?? '', $f->id ); ?>><?php echo esc_html( \ACDC\Support\FormationLabel::fromRow( $f ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="audit-filter-field">
                    <label>Apprenant</label>
                    <select name="learner_id">
                        <option value="">Tous</option>
                        <?php foreach ( $learners as $l ) : ?>
                        <option value="<?php echo esc_attr( $l->id ); ?>" <?php selected( $filters['learner_id'] ?? '', $l->id ); ?>><?php echo esc_html( $l->full_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="audit-filter-field">
                    <label>Commanditaire</label>
                    <select name="company_id">
                        <option value="">Tous</option>
                        <?php foreach ( $companies as $c ) : ?>
                        <option value="<?php echo esc_attr( $c->id ); ?>" <?php selected( $filters['company_id'] ?? '', $c->id ); ?>><?php echo esc_html( $c->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="audit-filter-field">
                    <label>Type de document</label>
                    <select name="doc_type">
                        <option value="">Tous</option>
                        <?php foreach ( $doc_types as $key => $def ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['doc_type'] ?? '', $key ); ?>><?php echo esc_html( $def['icon'] . ' ' . $def['label'] ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="audit-filter-field">
                    <label>Période du</label>
                    <input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ?? '' ); ?>">
                </div>
                <div class="audit-filter-field">
                    <label>Au</label>
                    <input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ?? '' ); ?>">
                </div>
            </div>
            <div style="margin-top:12px;display:flex;gap:10px">
                <button type="submit" class="acdc-button acdc-button-primary">Filtrer</button>
                <a href="<?php echo esc_url( $form_action ); ?>" class="acdc-button acdc-button-soft">Réinitialiser</a>
            </div>
        </form>
        <?php
    }

    /* -----------------------------------------------------------------------
     * Arbre documentaire
     * -------------------------------------------------------------------- */
    private function render_doc_tree( $docs, $is_admin ) {
        $doc_types = $this->core->get_document_types();
        if ( empty( $docs ) ) {
            echo '<div class="audit-empty">Aucun document trouvé avec les critères sélectionnés.</div>';
            return;
        }
        foreach ( $docs as $fid => $formation ) :
            $total_f = count( $formation['docs_formation'] ) + count( $formation['docs_learners'] );
            foreach ( $formation['sessions'] as $session ) { $total_f += count( $session['docs'] ); }
        ?>
        <?php
        /* ACDC 3.25.330 — CE QUI MANQUE EST AUSSI UNE INFORMATION.
           Cet écran ne montrait que ce qu'il avait trouvé : un parcours sans
           attestation ressemblait trait pour trait à un parcours dont
           personne n'avait cherché l'attestation. Impossible, en le lisant,
           de distinguer une pièce ABSENTE d'une pièce NON PRODUITE — et c'est
           précisément la question que pose un auditeur.
           On dresse donc la liste des types attendus et on marque ceux qui
           sont couverts. Le vert dit « la preuve est là », le gris dit « rien
           n'a été trouvé de ce type ». Le gris n'est pas un reproche : sur un
           parcours sans financeur, l'enquête financeur n'a pas lieu d'être.
           Mais il se voit, et c'est tout ce qu'on lui demande. */
        $types_presents = array();
        foreach ( (array) $formation['docs_formation'] as $__d ) { $types_presents[ (string) $__d['type'] ] = true; }
        foreach ( (array) $formation['docs_learners'] as $__d )  { $types_presents[ (string) $__d['type'] ] = true; }
        foreach ( (array) $formation['sessions'] as $__s ) {
            foreach ( (array) $__s['docs'] as $__d ) { $types_presents[ (string) $__d['type'] ] = true; }
        }
        $couverts = 0;
        foreach ( $doc_types as $__k => $__def ) { if ( isset( $types_presents[ $__k ] ) ) { $couverts++; } }
        ?>
        <details class="audit-formation" open>
            <summary class="audit-formation-summary">
                <?php echo esc_html( $formation['title'] ); ?>
                <span class="audit-count"><?php echo $total_f; ?> doc<?php echo $total_f > 1 ? 's' : ''; ?></span>
                <span class="audit-count"><?php echo (int) $couverts; ?>/<?php echo (int) count( $doc_types ); ?> types de pièces</span>
            </summary>
            <div class="audit-formation-body">
                <div class="audit-couverture">
                    <?php foreach ( $doc_types as $__k => $__def ) :
                        $__ok = isset( $types_presents[ $__k ] ); ?>
                        <span class="audit-chip<?php echo $__ok ? ' is-ok' : ''; ?>" title="<?php echo esc_attr( $__ok ? 'Pièce présente au dossier' : 'Aucune pièce de ce type trouvée' ); ?>"><?php echo $__ok ? '&#10003;' : '&#9675;'; ?> <?php echo esc_html( $__def['label'] ); ?></span>
                    <?php endforeach; ?>
                </div>

                <?php /* Docs au niveau formation (conventions) */
                if ( ! empty( $formation['docs_formation'] ) ) : ?>
                <div class="audit-group">
                    <div class="audit-group-title">Documents de formation</div>
                    <div class="audit-doc-list">
                    <?php foreach ( $formation['docs_formation'] as $doc ) :
                        $def = $doc_types[ $doc['type'] ] ?? array( 'label' => $doc['type'], 'icon' => '📄' );
                    ?>
                        <?php $this->render_doc_row( $doc, $def, $is_admin ); ?>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php /* Séances */
                foreach ( $formation['sessions'] as $sid => $session ) :
                    if ( empty( $session['docs'] ) ) continue;
                ?>
                <div class="audit-group">
                    <div class="audit-group-title">Séance — <?php echo esc_html( $session['date_label'] ); ?><?php if ( $session['title'] && $session['title'] !== 'Séance ' . $session['date_label'] ) : ?> <span style="font-weight:400;color:#6b7280"> — <?php echo esc_html( $session['title'] ); ?></span><?php endif; ?></div>
                    <div class="audit-doc-list">
                    <?php foreach ( $session['docs'] as $doc ) :
                        $def = $doc_types[ $doc['type'] ] ?? array( 'label' => $doc['type'], 'icon' => '📄' );
                    ?>
                        <?php $this->render_doc_row( $doc, $def, $is_admin ); ?>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php /* Docs apprenants */
                if ( ! empty( $formation['docs_learners'] ) ) :
                    /* Regrouper par apprenant */
                    $by_learner = array();
                    foreach ( $formation['docs_learners'] as $doc ) {
                        $k = $doc['learner_name'] ?: '(Groupe)';
                        $by_learner[ $k ][] = $doc;
                    }
                ?>
                <?php foreach ( $by_learner as $learner_name => $l_docs ) : ?>
                <div class="audit-group">
                    <div class="audit-group-title"><?php echo esc_html( $learner_name ); ?></div>
                    <div class="audit-doc-list">
                    <?php foreach ( $l_docs as $doc ) :
                        $def = $doc_types[ $doc['type'] ] ?? array( 'label' => $doc['type'], 'icon' => '📄' );
                    ?>
                        <?php $this->render_doc_row( $doc, $def, $is_admin ); ?>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </details>
        <?php endforeach;
    }

    private function render_doc_row( $doc, $def, $is_admin ) {
        $is_admin_url = ! empty( $doc['is_admin_url'] );
        $can_open     = ! $is_admin_url || $is_admin;
        $url_view     = $doc['url'] ?? '';
        $url_dl       = $doc['url_download'] ?? $url_view; // fallback sur url si pas de url_download

        $icon_view = '<span aria-hidden="true" style="display:inline-flex;align-items:center;justify-content:center;width:25px;height:25px;line-height:1;"><svg style="fill:none!important" width="25" height="25" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="3"/></svg></span>';
        $icon_dl   = '<span aria-hidden="true" style="display:inline-flex;align-items:center;justify-content:center;width:25px;height:25px;line-height:1;"><svg style="fill:none!important" width="25" height="25" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></span>';
        ?>
        <div class="audit-doc-row">
            <div class="audit-doc-icon">
                <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:var(--acdc-primary,#8b5b23);margin-top:2px"></span>
            </div>
            <div class="audit-doc-info">
                <div class="audit-doc-label"><?php echo esc_html( $doc['label'] ); ?></div>
                <?php if ( ! empty( $doc['meta'] ) ) : ?>
                <div class="audit-doc-meta"><?php echo esc_html( $doc['meta'] ); ?></div>
                <?php endif; ?>
            </div>
            <?php if ( $doc['created_at'] ) : ?>
            <div class="audit-doc-date"><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $doc['created_at'] ) ) ); ?></div>
            <?php endif; ?>
            <div class="audit-doc-actions">
                <?php if ( $can_open && ! empty( $url_view ) ) : ?>
                <a href="<?php echo esc_url( $url_view ); ?>" target="_blank" class="acdc-row-action-icon acdc-row-view-link" data-acdc-iconized="1" title="Aperçu du document" aria-label="Aperçu">
                    <?php echo $icon_view; // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </a>
                <a href="<?php echo esc_url( $url_dl ); ?>" download class="acdc-row-action-icon" data-acdc-iconized="1" title="Télécharger le document" aria-label="Télécharger">
                    <?php echo $icon_dl; // phpcs:ignore WordPress.Security.EscapeOutput ?>
                </a>
                <?php elseif ( $is_admin_url && ! $is_admin ) : ?>
                <span style="font-size:11px;color:#9ca3af">Connexion requise</span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /* -----------------------------------------------------------------------
     * CSS partagé admin + public
     * -------------------------------------------------------------------- */
    private function get_shared_css() {
        return '
.audit-subnav{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap}
/* ACDC 3.25.330 — La ligne de couverture : ce qui est là, et ce qui ne l\'est pas. */
.audit-couverture{display:flex;flex-wrap:wrap;gap:6px;padding:12px 0 16px;border-bottom:1px solid #eef2f6;margin-bottom:14px}
.audit-chip{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:#f1f3f5;color:#6b7280;border:1px solid #e2e8f0}
.audit-chip.is-ok{background:#d9f3e5;color:#1a7a50;border-color:#a7e0c4}
.audit-section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px;flex-wrap:wrap}
.audit-section-title{font-size:18px;font-weight:800;color:#0f2c52;margin:0}
.audit-section-sub{font-size:13px;color:#6b7280;margin-top:4px}
.audit-filters{background:#fff;border:1px solid #dce4ec;border-radius:10px;padding:18px 20px;margin-bottom:20px}
.audit-filters-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px}
.audit-filter-field{display:flex;flex-direction:column;gap:5px}
.audit-filter-field label{font-size:11px;font-weight:700;color:#0f2c52;text-transform:uppercase;letter-spacing:.03em}
.audit-filter-field select,.audit-filter-field input{height:36px;border:1px solid #dce4ec;border-radius:8px;padding:0 10px;font-size:13px;background:#fff;color:#1a2744}
.audit-formation{background:#fff;border:1px solid #dce4ec;border-radius:10px;margin-bottom:12px;overflow:hidden}
.audit-formation-summary{padding:14px 18px;cursor:pointer;font-weight:700;color:#0f2c52;font-size:14px;display:flex;align-items:center;gap:10px;user-select:none;list-style:none}
.audit-formation-summary::-webkit-details-marker{display:none}
.audit-formation-summary::before{content:"▶";font-size:10px;transition:transform .2s}
details[open] .audit-formation-summary::before{transform:rotate(90deg)}
.audit-count{margin-left:auto;background:#f0f4f8;color:#4b5d76;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600}
.audit-formation-body{padding:0 16px 16px}
.audit-group{margin-top:12px}
.audit-group-title{font-size:12px;font-weight:700;color:#4b5d76;text-transform:uppercase;letter-spacing:.04em;padding:6px 0;border-bottom:1px solid #f0f4f8;margin-bottom:6px}
.audit-doc-list{display:flex;flex-direction:column;gap:4px}
.audit-doc-row{display:flex;align-items:center;gap:12px;padding:8px 10px;border-radius:8px;background:#f8f9fa;transition:background .15s}
.audit-doc-row:hover{background:#eef2f8}
.audit-doc-icon{font-size:16px;flex-shrink:0;width:24px;text-align:center}
.audit-doc-info{flex:1;min-width:0}
.audit-doc-label{font-size:13px;font-weight:600;color:#1a2744;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.audit-doc-meta{font-size:11px;color:#9ca3af;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.audit-doc-date{font-size:11px;color:#9ca3af;white-space:nowrap;flex-shrink:0}
.audit-doc-actions{display:flex;gap:6px;flex-shrink:0}
.audit-tokens-list{display:flex;flex-direction:column;gap:10px}
.audit-token-row{background:#fff;border:1px solid #dce4ec;border-radius:10px;padding:16px 18px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}
.audit-token-row.is-inactive{opacity:.6}
.audit-token-info{flex:1;min-width:200px}
.audit-token-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.audit-empty{padding:32px;text-align:center;color:#9ca3af;font-size:14px;background:#fff;border:1px solid #dce4ec;border-radius:10px}
';
    }

    /* -----------------------------------------------------------------------
     * Helpers
     * -------------------------------------------------------------------- */
    private function get_filters_from_request() {
        return array(
            'formation_id' => isset( $_GET['formation_id'] ) ? absint( $_GET['formation_id'] ) : 0,
            'learner_id'   => isset( $_GET['learner_id'] )   ? absint( $_GET['learner_id'] )   : 0,
            'company_id'   => isset( $_GET['company_id'] )   ? absint( $_GET['company_id'] )   : 0,
            'doc_type'     => isset( $_GET['doc_type'] )     ? sanitize_key( $_GET['doc_type'] ) : '',
            'date_from'    => isset( $_GET['date_from'] )    ? sanitize_text_field( $_GET['date_from'] ) : '',
            'date_to'      => isset( $_GET['date_to'] )      ? sanitize_text_field( $_GET['date_to'] )   : '',
        );
    }

    private function count_docs( $docs ) {
        $n = 0;
        foreach ( $docs as $f ) {
            $n += count( $f['docs_formation'] ) + count( $f['docs_learners'] );
            foreach ( $f['sessions'] as $s ) { $n += count( $s['docs'] ); }
        }
        return $n;
    }

    private function admin_url( $extra = array() ) {
        // Construire à partir de l'URL courante pour rester dans le portail
        $current = remove_query_arg( array( 'audit_sub', 'token_created' ) );
        return add_query_arg( $extra, $current );
    }
}
