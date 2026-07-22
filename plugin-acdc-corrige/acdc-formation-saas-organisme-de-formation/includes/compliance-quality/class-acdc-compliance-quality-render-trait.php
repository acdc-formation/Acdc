<?php
/**
 * ACDC Conformité / Qualité annexe — rendus et écrans
 *
 * Extraction incrémentale des rendus front/admin du module BPF, amélioration continue, échéances apprenants, veille et prestations annexes.
 * Version : 3.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Compliance_Quality_Render_Trait {


  private function render_front_quality_hub() {
    global $wpdb;

    /* ---- Collecte des donnees ---- */
    /* Reclamations */
    $cpl_unack  = 0;
    $cpl_open   = 0;
    $cpl_total  = 0;
    if ( ! empty( $this->complaint_table ) ) {
      $tbl_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->complaint_table ) );
      if ( $tbl_exists ) {
        $cpl_unack = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->complaint_table} WHERE acknowledged_at IS NULL" );
        $cpl_open  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->complaint_table} WHERE status IN ('ouverte','en_cours')" );
        $cpl_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->complaint_table}" );
      }
    }
    /* Ameliorations continues */
    $ci_records = $this->get_continuous_improvement_records();
    $ci_total   = count( $ci_records );
    $ci_open    = 0;
    $ci_overdue = 0;
    $today      = current_time( 'Y-m-d' );
    foreach ( $ci_records as $r ) {
      $status = strtolower( trim( (string) ( $r['status'] ?? '' ) ) );
      /* "traité", "traite", "clos", "terminé" = fait. "à traiter", "en cours" = ouvert. */
      $is_done = ( $status === 'traité' || $status === 'traite'
        || false !== strpos( $status, 'clos' )
        || false !== strpos( $status, 'termin' )
        || false !== strpos( $status, 'réalis' )
        || false !== strpos( $status, 'realis' ) );
      if ( ! $is_done ) {
        $ci_open++;
        if ( ! empty( $r['deadline'] ) && $r['deadline'] < $today ) { $ci_overdue++; }
      }
    }
    /* Enquetes */
    $survey_todo = method_exists( $this, 'get_dashboard_questionnaire_todo_counts' ) ? $this->get_dashboard_questionnaire_todo_counts() : array();
    $surveys_pending = array_sum( array_values( $survey_todo ) );

    /* URLs */
    $url_complaints     = $this->portal_page_url( array( 'tab' => 'complaints' ) );
    $url_cpl_create     = $this->portal_page_url( array( 'tab' => 'complaints', 'cpl_action' => 'create' ) );
    $url_cpl_unack      = $this->portal_page_url( array( 'tab' => 'complaints', 'cpl_filter' => 'unack' ) );
    $url_improvement    = $this->portal_page_url( array( 'tab' => 'continuous_improvement' ) );
    $url_surveys        = $this->portal_page_url( array( 'tab' => 'hot_surveys' ) );
    $url_bpf            = $this->portal_page_url( array( 'tab' => 'bpf' ) );
    $url_veille         = $this->portal_page_url( array( 'tab' => 'watch' ) );
    $url_cp             = $this->portal_page_url( array( 'tab' => 'conseil_perfectionnement' ) );
    $cp_status          = $this->get_perfectionnement_compliance_status();
    $cp_dot_colors      = array( 'green' => '#22c55e', 'orange' => '#f59e0b', 'red' => '#ef4444' );
    $cp_dot_color       = $cp_dot_colors[ $cp_status ] ?? '#ef4444';
    $url_psh            = $this->portal_page_url( array( 'tab' => 'psh_partners' ) );
    $psh_status         = $this->get_psh_compliance_status();
    /* ACDC 3.24.13 — Ind. 17 : locaux & équipements. */
    $url_training_sites   = $this->portal_page_url( array( 'tab' => 'training_sites' ) );
    $training_sites_status = $this->get_training_sites_compliance_status();
    /* ACDC 3.24.14 — Ind. 28 : sous-traitants formels. */
    $url_subcontractors      = $this->portal_page_url( array( 'tab' => 'subcontractors' ) );
    $subcontractors_status   = $this->get_subcontractors_compliance_status();
    $subcontractors_count    = count( $this->get_subcontractors() );
    /* ACDC 3.24.11 — Ind. 21 : formateurs sans bilan de compétences récent. */
    $eval_alert_trainers = method_exists( $this, 'get_trainers_without_recent_evaluation' ) ? $this->get_trainers_without_recent_evaluation() : array();
    $eval_alert_count    = count( $eval_alert_trainers );
    $url_trainers        = $this->portal_page_url( array( 'tab' => 'trainers' ) );
    ?>
    <style>
    .acdc-qhub{width:100%;}
    .acdc-qhub-head{margin-bottom:28px;}
    .acdc-qhub-head h2{font-size:22px;font-weight:700;color:#0f2c52;margin:0 0 6px;}
    .acdc-qhub-head p{font-size:13px;color:#4b5d76;margin:0;}
    /* Workflow */
    .acdc-qhub-workflow{display:flex;flex-direction:column;gap:0;margin-bottom:32px;}
    .acdc-qhub-step{display:flex;align-items:stretch;gap:0;position:relative;}
    .acdc-qhub-step-left{display:flex;flex-direction:column;align-items:center;width:56px;flex-shrink:0;}
    .acdc-qhub-step-num{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:800;flex-shrink:0;z-index:1;}
    .acdc-qhub-step-line{flex:1;width:2px;background:#e2e8f0;margin:0 auto;}
    .acdc-qhub-step:last-child .acdc-qhub-step-line{display:none;}
    .acdc-qhub-step-body{flex:1;padding:0 0 24px 16px;}
    .acdc-qhub-step-title{font-size:15px;font-weight:700;color:#0f2c52;margin:8px 0 4px;}
    .acdc-qhub-step-desc{font-size:12px;color:#4b5d76;margin:0 0 10px;line-height:1.5;}
    .acdc-qhub-step-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
    .acdc-qhub-step-card.has-alert{border-color:#f5c6c6;background:#fff5f5;}
    .acdc-qhub-step-card.has-warn{border-color:#fde68a;background:#fffbeb;}
    .acdc-qhub-step-card.is-ok{border-color:#bbf7d0;background:#f0fdf4;}
    .acdc-qhub-kpis{display:flex;gap:16px;flex-wrap:wrap;align-items:center;}
    .acdc-qhub-kpi{text-align:center;}
    .acdc-qhub-kpi-val{font-size:26px;font-weight:800;color:#0f2c52;line-height:1;}
    .acdc-qhub-kpi-val.red{color:#c0392b;}
    .acdc-qhub-kpi-val.orange{color:#b45309;}
    .acdc-qhub-kpi-val.green{color:#15803d;}
    .acdc-qhub-kpi-lbl{font-size:10px;color:#4b5d76;font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-top:2px;}
    .acdc-qhub-actions{display:flex;gap:8px;flex-wrap:wrap;}
    /* Ressources rapides */
    .acdc-qhub-resources{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;}
    .acdc-qhub-resource{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;text-decoration:none;display:block;transition:box-shadow .15s,transform .15s;}
    .acdc-qhub-resource:hover{box-shadow:0 4px 12px rgba(15,44,82,.08);transform:translateY(-1px);}
    .acdc-qhub-resource-icon{font-size:22px;margin-bottom:6px;}
    .acdc-qhub-resource-title{font-size:13px;font-weight:700;color:#0f2c52;margin-bottom:3px;}
    .acdc-qhub-resource-desc{font-size:11px;color:#4b5d76;line-height:1.4;}
    .acdc-qhub-section-label{font-size:10px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.08em;margin:0 0 12px;display:flex;align-items:center;gap:8px;}
    .acdc-qhub-section-label::after{content:'';flex:1;height:1px;background:#f0e6dc;}
    @media(max-width:640px){
      .acdc-qhub-resources{grid-template-columns:1fr 1fr;}
      .acdc-qhub-step-card{flex-direction:column;align-items:flex-start;}
    }
    </style>

    <div class="acdc-qhub">

      <section class="acdc-section-head">
        <div>
          <h2>Hub qualit&eacute;</h2>
          <p>Tableau de bord qualit&eacute; Qualiopi &mdash; indicateurs 31 et 32 &mdash; de la r&eacute;clamation &agrave; la cl&ocirc;ture.</p>
        </div>
      </section>
      <?php if ( isset( $_GET['cpl_closed'] ) ) : ?>
      <div class="acdc-alert acdc-alert-success acdc-alert-dismissible" role="status"><div class="acdc-alert-body"><strong class="acdc-alert-title">R&eacute;clamation cl&ocirc;tur&eacute;e</strong><p class="acdc-alert-message">La r&eacute;clamation a &eacute;t&eacute; cl&ocirc;tur&eacute;e avec succ&egrave;s.</p></div><button type="button" class="acdc-alert-dismiss" data-acdc-dismiss-alert aria-label="Fermer">&times;</button></div>
      <?php endif; ?>

      <?php /* ===== WORKFLOW 5 ETAPES ===== */ ?>
      <div class="acdc-qhub-section-label">Workflow r&eacute;clamations &mdash; indicateurs 31 &amp; 32</div>
      <div class="acdc-qhub-workflow">

        <?php /* ETAPE 1 */ ?>
        <div class="acdc-qhub-step">
          <div class="acdc-qhub-step-left">
            <div class="acdc-qhub-step-num" style="background:#dbeafe;color:#1e40af;">1</div>
            <div class="acdc-qhub-step-line"></div>
          </div>
          <div class="acdc-qhub-step-body">
            <div class="acdc-qhub-step-title">Une r&eacute;clamation arrive</div>
            <div class="acdc-qhub-step-desc">Un apprenant, commanditaire, financeur ou formateur vous signale un probl&egrave;me par &eacute;crit. C'est le point de d&eacute;part obligatoire.</div>
            <div class="acdc-qhub-step-card <?php echo $cpl_total > 0 ? 'is-ok' : ''; ?>">
              <div class="acdc-qhub-kpis">
                <div class="acdc-qhub-kpi">
                  <div class="acdc-qhub-kpi-val <?php echo $cpl_total === 0 ? '' : 'green'; ?>"><?php echo $cpl_total; ?></div>
                  <div class="acdc-qhub-kpi-lbl">Enregistr&eacute;es</div>
                </div>
              </div>
              <div class="acdc-qhub-actions">
                <a href="<?php echo esc_url( $url_cpl_create ); ?>" class="acdc-button acdc-button-primary">+ Enregistrer une r&eacute;clamation</a>
                <?php if ( $cpl_total > 0 ) : ?><a href="<?php echo esc_url( $url_complaints ); ?>" class="acdc-button acdc-button-soft">Voir le registre</a><?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <?php /* ETAPE 2 */ ?>
        <div class="acdc-qhub-step">
          <div class="acdc-qhub-step-left">
            <div class="acdc-qhub-step-num" style="background:<?php echo $cpl_unack > 0 ? '#fee2e2;color:#991b1b' : '#dbeafe;color:#1e40af'; ?>;"><?php echo $cpl_unack > 0 ? '&#9888;' : '2'; ?></div>
            <div class="acdc-qhub-step-line"></div>
          </div>
          <div class="acdc-qhub-step-body">
            <div class="acdc-qhub-step-title">Envoyer l'accus&eacute; de r&eacute;ception <span style="font-size:11px;font-weight:400;color:#c0392b;"><?php echo $cpl_unack > 0 ? '&mdash; action requise' : ''; ?></span></div>
            <div class="acdc-qhub-step-desc">Qualiopi l'exige &mdash; chaque r&eacute;clamation doit faire l'objet d'un accus&eacute; de r&eacute;ception. Sans cela, vous &ecirc;tes en non-conformit&eacute; majeure (indicateur 31).</div>
            <div class="acdc-qhub-step-card <?php echo $cpl_unack > 0 ? 'has-alert' : ( $cpl_total > 0 ? 'is-ok' : '' ); ?>">
              <div class="acdc-qhub-kpis">
                <div class="acdc-qhub-kpi">
                  <div class="acdc-qhub-kpi-val <?php echo $cpl_unack > 0 ? 'red' : 'green'; ?>"><?php echo $cpl_unack; ?></div>
                  <div class="acdc-qhub-kpi-lbl">Sans accus&eacute;</div>
                </div>
                <div class="acdc-qhub-kpi">
                  <div class="acdc-qhub-kpi-val green"><?php echo $cpl_total - $cpl_unack; ?></div>
                  <div class="acdc-qhub-kpi-lbl">Accus&eacute;es</div>
                </div>
              </div>
              <div class="acdc-qhub-actions">
                <?php if ( $cpl_unack > 0 ) : ?>
                  <a href="<?php echo esc_url( $url_cpl_unack ); ?>" class="acdc-button acdc-button-primary" style="background:#c0392b;border-color:#c0392b;">&#9888; Traiter les <?php echo $cpl_unack; ?> accus&eacute;(s) manquant(s)</a>
                <?php elseif ( $cpl_total > 0 ) : ?>
                  <span style="font-size:13px;color:#15803d;font-weight:600;">&#10003; Tous les accus&eacute;s sont enregistr&eacute;s</span>
                <?php else : ?>
                  <span style="font-size:12px;color:#4b5d76;">Aucune r&eacute;clamation enregistr&eacute;e pour l'instant</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <?php /* ETAPE 3 */ ?>
        <div class="acdc-qhub-step">
          <div class="acdc-qhub-step-left">
            <div class="acdc-qhub-step-num" style="background:<?php echo $cpl_open > 0 ? '#fef3cd;color:#856404' : '#dbeafe;color:#1e40af'; ?>;">3</div>
            <div class="acdc-qhub-step-line"></div>
          </div>
          <div class="acdc-qhub-step-body">
            <div class="acdc-qhub-step-title">Analyser, traiter et cl&ocirc;turer</div>
            <div class="acdc-qhub-step-desc">Pour chaque r&eacute;clamation ouverte : renseignez la mesure corrective, puis cliquez <strong>Cl&ocirc;turer</strong> directement ici. Si la r&eacute;clamation est grave, une action d'am&eacute;lioration est cr&eacute;&eacute;e automatiquement (indicateur 32).</div>
            <div class="acdc-qhub-step-card <?php echo $cpl_open > 0 ? 'has-warn' : ( $cpl_total > 0 ? 'is-ok' : '' ); ?>">
              <div style="width:100%;">
                <?php if ( $cpl_open > 0 ) : ?>
                  <?php
                  global $wpdb;
                  $open_list = array();
                  if ( ! empty( $this->complaint_table ) ) {
                    $open_list = (array) $wpdb->get_results( "SELECT id, summary, severity, source FROM {$this->complaint_table} WHERE status IN ('ouverte','en_cours') ORDER BY id ASC" );
                  }
                  $severity_labels = $this->get_complaint_severity_options();
                  ?>
                  <div style="display:flex;flex-direction:column;gap:10px;width:100%;">
                    <?php foreach ( $open_list as $cpl ) :
                      $sev_lbl  = isset( $severity_labels[ $cpl->severity ] ) ? $severity_labels[ $cpl->severity ] : $cpl->severity;
                      $sev_col  = 'grave' === $cpl->severity ? '#c0392b' : ( 'moderee' === $cpl->severity ? '#b45309' : '#15803d' );
                      $edit_url = $this->portal_page_url( array( 'tab' => 'complaints', 'cpl_action' => 'edit', 'cpl_id' => $cpl->id ) );
                      $hub_url  = $this->portal_page_url( array( 'tab' => 'quality', 'cpl_closed' => '1' ) );
                    ?>
                    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                      <div style="flex:1;min-width:200px;">
                        <span style="font-size:12px;font-weight:700;color:<?php echo $sev_col; ?>;margin-right:8px;"><?php echo esc_html( $sev_lbl ); ?></span>
                        <span style="font-size:13px;color:#0f2c52;font-weight:600;">#<?php echo (int) $cpl->id; ?></span>
                        <span style="font-size:13px;color:#4b5d76;margin-left:8px;"><?php echo esc_html( mb_substr( $cpl->summary, 0, 80 ) ); ?><?php echo mb_strlen( $cpl->summary ) > 80 ? '&hellip;' : ''; ?></span>
                      </div>
                      <div style="display:flex;gap:8px;flex-shrink:0;">
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="acdc-button acdc-button-soft" style="font-size:12px;height:34px;padding:0 12px;">Modifier</a>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('Cl&ocirc;turer d&eacute;finitivement cette r&eacute;clamation ?');">
                          <input type="hidden" name="action" value="acdc_close_complaint">
                          <input type="hidden" name="complaint_id" value="<?php echo (int) $cpl->id; ?>">
                          <input type="hidden" name="_front_redirect" value="<?php echo esc_attr( $hub_url ); ?>">
                          <?php wp_nonce_field( 'acdc_close_complaint' ); ?>
                          <button type="submit" class="acdc-button acdc-button-primary" style="font-size:12px;height:34px;padding:0 12px;background:#15803d;border-color:#15803d;">&#10003; Cl&ocirc;turer</button>
                        </form>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                <?php elseif ( $cpl_total > 0 ) : ?>
                  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                    <span style="font-size:14px;color:#15803d;font-weight:700;">&#10003; Toutes les r&eacute;clamations sont cl&ocirc;tur&eacute;es (<?php echo $cpl_total; ?>)</span>
                    <a href="<?php echo esc_url( $url_complaints ); ?>" class="acdc-button acdc-button-soft" style="font-size:12px;">Voir le registre</a>
                  </div>
                <?php else : ?>
                  <span style="font-size:13px;color:#4b5d76;">Aucune r&eacute;clamation ouverte pour l'instant.</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <?php /* ETAPE 4 */ ?>
        <div class="acdc-qhub-step">
          <div class="acdc-qhub-step-left">
            <div class="acdc-qhub-step-num" style="background:<?php echo $ci_overdue > 0 ? '#fee2e2;color:#991b1b' : ( $ci_open > 0 ? '#fef3cd;color:#856404' : '#dbeafe;color:#1e40af' ); ?>;">4</div>
            <div class="acdc-qhub-step-line"></div>
          </div>
          <div class="acdc-qhub-step-body">
            <div class="acdc-qhub-step-title">Piloter les am&eacute;liorations</div>
            <div class="acdc-qhub-step-desc">Suivez vos actions d'am&eacute;lioration continue &mdash; celles issues des r&eacute;clamations graves et celles que vous avez cr&eacute;&eacute;es manuellement. Chaque action a un responsable et une &eacute;ch&eacute;ance (indicateur 32).</div>
            <div class="acdc-qhub-step-card <?php echo $ci_overdue > 0 ? 'has-alert' : ( $ci_open > 0 ? 'has-warn' : ( $ci_total > 0 ? 'is-ok' : '' ) ); ?>">
              <div class="acdc-qhub-kpis">
                <div class="acdc-qhub-kpi">
                  <div class="acdc-qhub-kpi-val <?php echo $ci_open > 0 ? 'orange' : 'green'; ?>"><?php echo $ci_open; ?></div>
                  <div class="acdc-qhub-kpi-lbl">En cours</div>
                </div>
                <?php if ( $ci_overdue > 0 ) : ?>
                <div class="acdc-qhub-kpi">
                  <div class="acdc-qhub-kpi-val red"><?php echo $ci_overdue; ?></div>
                  <div class="acdc-qhub-kpi-lbl">En retard</div>
                </div>
                <?php endif; ?>
                <div class="acdc-qhub-kpi">
                  <div class="acdc-qhub-kpi-val"><?php echo $ci_total - $ci_open; ?></div>
                  <div class="acdc-qhub-kpi-lbl">Trait&eacute;es</div>
                </div>
              </div>
              <div class="acdc-qhub-actions">
                <a href="<?php echo esc_url( $this->portal_page_url( array( 'tab' => 'continuous_improvement' ) ) ); ?>" class="acdc-button acdc-button-soft">Voir le registre d'am&eacute;lioration</a>
                <a href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard&tab=continuous_improvement&action=new' ) : $this->portal_page_url( array( 'tab' => 'continuous_improvement', 'action' => 'new' ) ) ); ?>" class="acdc-button acdc-button-soft">+ Nouvelle am&eacute;lioration</a>
              </div>
            </div>
          </div>
        </div>

        <?php /* ETAPE 5 */ ?>
        <div class="acdc-qhub-step">
          <div class="acdc-qhub-step-left">
            <div class="acdc-qhub-step-num" style="background:#d1fae5;color:#065f46;">5</div>
          </div>
          <div class="acdc-qhub-step-body">
            <div class="acdc-qhub-step-title">Cl&ocirc;turer &amp; prouver</div>
            <div class="acdc-qhub-step-desc">Une fois la r&eacute;clamation r&eacute;gl&eacute;e et l'action d'am&eacute;lioration r&eacute;alis&eacute;e, clôturez les deux. L'export PDF du registre est votre preuve pour l'auditeur Qualiopi.</div>
            <div class="acdc-qhub-step-card is-ok">
              <div class="acdc-qhub-kpis">
                <div class="acdc-qhub-kpi">
                  <div class="acdc-qhub-kpi-val green"><?php echo max( 0, $cpl_total - $cpl_open ); ?></div>
                  <div class="acdc-qhub-kpi-lbl">R&eacute;clamations cl&ocirc;tur&eacute;es</div>
                </div>
              </div>
              <div class="acdc-qhub-actions">
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_export_complaints_pdf' ), 'acdc_export_complaints_pdf' ) ); ?>" class="acdc-button acdc-button-soft" target="_blank">&#8595; Exporter le registre PDF (audit)</a>
              </div>
            </div>
          </div>
        </div>

      </div><?php /* fin workflow */ ?>

      <?php /* ===== PARTENAIRES PSH ===== */ ?>
      <div class="acdc-qhub-section-label" style="margin-top:8px;">Partenaires PSH &mdash; indicateur 26</div>
      <div style="background:<?php echo 'green' === $psh_status ? '#dff6e5' : '#fee2e2'; ?>;border:1px solid <?php echo 'green' === $psh_status ? '#86efac' : '#fca5a5'; ?>;border-radius:10px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
        <div>
          <strong style="font-size:15px;color:#3d3d3d;display:flex;align-items:center;gap:8px;">
            <span style="width:10px;height:10px;border-radius:50%;background:<?php echo 'green' === $psh_status ? '#22c55e' : '#ef4444'; ?>;display:inline-block;flex-shrink:0;"></span>
            <?php echo 'green' === $psh_status ? 'Conforme — partenaires enregistrés' : 'Non conforme — aucun partenaire PSH'; ?>
          </strong>
          <p style="font-size:12px;color:#4b5d76;margin:3px 0 0;"><?php echo count( $this->get_psh_partners() ); ?> partenaire(s) référencé(s)</p>
        </div>
        <a href="<?php echo esc_url( $url_psh ); ?>" class="acdc-button acdc-button-soft">Gérer les partenaires</a>
      </div>

      <?php /* ===== LOCAUX & ÉQUIPEMENTS IND. 17 — ACDC 3.24.13 ===== */ ?>
      <div class="acdc-qhub-section-label" style="margin-top:8px;">Locaux &amp; équipements &mdash; indicateur 17</div>
      <div style="background:<?php echo 'green' === $training_sites_status ? '#dff6e5' : '#fee2e2'; ?>;border:1px solid <?php echo 'green' === $training_sites_status ? '#86efac' : '#fca5a5'; ?>;border-radius:10px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
        <div>
          <strong style="font-size:15px;color:#3d3d3d;display:flex;align-items:center;gap:8px;">
            <span style="width:10px;height:10px;border-radius:50%;background:<?php echo 'green' === $training_sites_status ? '#22c55e' : '#ef4444'; ?>;display:inline-block;flex-shrink:0;"></span>
            <?php echo 'green' === $training_sites_status ? 'Conforme — locaux documentés' : 'Non conforme — aucun local enregistré'; ?>
          </strong>
          <p style="font-size:12px;color:#4b5d76;margin:3px 0 0;"><?php echo count( $this->get_training_sites() ); ?> local(aux) référencé(s)</p>
        </div>
        <a href="<?php echo esc_url( $url_training_sites ); ?>" class="acdc-button acdc-button-soft">Gérer les locaux</a>
      </div>

      <?php /* ===== SOUS-TRAITANTS IND. 28 — ACDC 3.24.14 ===== */ ?>
      <div class="acdc-qhub-section-label" style="margin-top:8px;">Sous-traitants &mdash; indicateur 28</div>
      <div style="background:<?php echo 'green' === $subcontractors_status ? '#dff6e5' : '#fee2e2'; ?>;border:1px solid <?php echo 'green' === $subcontractors_status ? '#86efac' : '#fca5a5'; ?>;border-radius:10px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
        <div>
          <strong style="font-size:15px;color:#3d3d3d;display:flex;align-items:center;gap:8px;">
            <span style="width:10px;height:10px;border-radius:50%;background:<?php echo 'green' === $subcontractors_status ? '#22c55e' : '#ef4444'; ?>;display:inline-block;flex-shrink:0;"></span>
            <?php echo 'green' === $subcontractors_status ? 'Conforme — sous-traitants enregistrés' : 'Non conforme — aucun sous-traitant enregistré'; ?>
          </strong>
          <p style="font-size:12px;color:#4b5d76;margin:3px 0 0;"><?php echo $subcontractors_count; ?> sous-traitant(s) référencé(s)</p>
        </div>
        <a href="<?php echo esc_url( $url_subcontractors ); ?>" class="acdc-button acdc-button-soft">Gérer les sous-traitants</a>
      </div>

      <?php /* ===== ÉVALUATION COMPÉTENCES FORMATEURS IND. 21 — ACDC 3.24.11 ===== */ ?>
      <div class="acdc-qhub-section-label" style="margin-top:8px;">Compétences formateurs &mdash; indicateur 21</div>
      <div style="background:<?php echo 0 === $eval_alert_count ? '#dff6e5' : '#fff7ed'; ?>;border:1px solid <?php echo 0 === $eval_alert_count ? '#86efac' : '#fcd34d'; ?>;border-radius:10px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
        <div>
          <strong style="font-size:15px;color:#3d3d3d;display:flex;align-items:center;gap:8px;">
            <span style="width:10px;height:10px;border-radius:50%;background:<?php echo 0 === $eval_alert_count ? '#22c55e' : '#f59e0b'; ?>;display:inline-block;flex-shrink:0;"></span>
            <?php if ( 0 === $eval_alert_count ) : ?>Conforme &mdash; tous les formateurs ont un bilan récent<?php else : ?><?php echo $eval_alert_count; ?> formateur(s) sans bilan depuis plus de 12 mois<?php endif; ?>
          </strong>
          <?php if ( $eval_alert_count > 0 ) : ?>
          <p style="font-size:12px;color:#4b5d76;margin:4px 0 0;">
            <?php
            $names = array();
            foreach ( $eval_alert_trainers as $t ) {
              $names[] = esc_html( trim( $t->first_name . ' ' . $t->last_name ) );
            }
            echo implode( ', ', array_slice( $names, 0, 5 ) );
            if ( $eval_alert_count > 5 ) { echo ' … et ' . ( $eval_alert_count - 5 ) . ' autre(s)'; }
            ?>
          </p>
          <?php endif; ?>
        </div>
        <a href="<?php echo esc_url( $url_trainers ); ?>" class="acdc-button acdc-button-soft">Voir les formateurs</a>
      </div>

      <?php /* ===== CONSEIL DE PERFECTIONNEMENT ===== */ ?>
      <div class="acdc-qhub-section-label" style="margin-top:8px;">Conseil de perfectionnement &mdash; indicateur 20</div>
      <div style="background:<?php echo 'green' === $cp_status ? '#dff6e5' : ( 'orange' === $cp_status ? '#fef9c3' : '#fee2e2' ); ?>;border:1px solid <?php echo 'green' === $cp_status ? '#86efac' : ( 'orange' === $cp_status ? '#fde68a' : '#fca5a5' ); ?>;border-radius:10px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
        <div>
          <strong style="font-size:15px;color:#3d3d3d;display:flex;align-items:center;gap:8px;">
            <span style="width:10px;height:10px;border-radius:50%;background:<?php echo esc_attr( $cp_dot_color ); ?>;display:inline-block;flex-shrink:0;"></span>
            <?php
            $cp_meetings_count = count( $this->get_perfectionnement_meetings() );
            $cp_members_count  = count( $this->get_perfectionnement_members() );
            if ( 'green' === $cp_status ) {
              echo 'Conforme — dernière réunion < 12 mois';
            } elseif ( 'orange' === $cp_status ) {
              echo 'Attention — dernière réunion > 12 mois';
            } else {
              echo 'Non conforme — aucune réunion enregistrée';
            }
            ?>
          </strong>
          <p style="font-size:12px;color:#4b5d76;margin:3px 0 0;"><?php echo $cp_members_count; ?> membre(s) · <?php echo $cp_meetings_count; ?> réunion(s)</p>
        </div>
        <a href="<?php echo esc_url( $url_cp ); ?>" class="acdc-button acdc-button-soft">Gérer le conseil</a>
      </div>

      <?php /* ===== SATISFACTION ET ENQUETES ===== */ ?>
      <?php if ( $surveys_pending > 0 ) : ?>
      <div class="acdc-qhub-section-label" style="margin-top:8px;">Satisfaction &amp; &eacute;valuations &mdash; &agrave; traiter</div>
      <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
        <div>
          <strong style="font-size:15px;color:#92400e;"><?php echo $surveys_pending; ?> enqu&ecirc;te(s) &agrave; traiter</strong>
          <p style="font-size:12px;color:#4b5d76;margin:3px 0 0;">Enqu&ecirc;tes &agrave; chaud, &agrave; froid, interm&eacute;diaires, formateurs, entreprises, financeurs.</p>
        </div>
        <a href="<?php echo esc_url( $url_surveys ); ?>" class="acdc-button acdc-button-soft">Voir les enqu&ecirc;tes</a>
      </div>
      <?php endif; ?>

    </div>
    <?php
  }




  private function render_front_bpf_tab() {
    // ACDC 3.21.78 — Interception des actions BPF via portal_page_url (WAF-safe)
    $bpf_action = isset( $_REQUEST['bpf_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['bpf_action'] ) ) : '';

    if ( 'generate' === $bpf_action ) {
      if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
      check_admin_referer( 'acdc_generate_bpf_prefilled' );
      $this->handle_generate_bpf_prefilled();
      return;
    }

    if ( 'import' === $bpf_action && isset( $_FILES['bpf_file'] ) ) {
      if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
      check_admin_referer( 'acdc_import_bpf' );
      $this->handle_import_bpf();
      return;
    }

    if ( 'reset' === $bpf_action ) {
      if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
      check_admin_referer( 'acdc_reset_bpf' );
      $this->handle_reset_bpf();
      return;
    }

    if ( 'reset_year' === $bpf_action ) {
      if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
      check_admin_referer( 'acdc_reset_bpf_year' );
      $this->handle_reset_bpf_year();
      return;
    }

    $view = isset( $_GET['bpf_view'] ) ? sanitize_key( wp_unslash( $_GET['bpf_view'] ) ) : 'list';
    if ( 'detail' === $view ) {
      $year = isset( $_GET['bpf_year'] ) ? sanitize_text_field( wp_unslash( $_GET['bpf_year'] ) ) : '2025';
      $this->render_front_bpf_detail_page( $year );
      return;
    }
    $this->render_front_bpf_list_page();
  }



  private function render_front_bpf_list_page() {
    $records = $this->get_bpf_records();
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'bpf' ) );
    ?>
    <section class="acdc-section-head"><div><h2>BPF</h2></div></section>
    <div class="acdc-panel acdc-mb-18">
      <form method="get" action="">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
        <input type="hidden" name="tab" value="bpf">
        <div class="acdc-inline-wrap" style="justify-content:space-between;align-items:center;gap:16px;">
          <input type="search" name="q" value="" placeholder="Rechercher" style="max-width:420px;">
          <div class="acdc-inline-wrap" style="gap:10px;">
            <a href="<?php echo esc_url( $this->get_bpf_cerfa_blank_url() ); ?>" class="acdc-button acdc-button-primary" target="_blank" rel="noopener">Télécharger BPF vierge</a>
            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'tab' => 'bpf', 'bpf_action' => 'reset' ), $base_url ), 'acdc_reset_bpf' ) ); ?>" class="acdc-button acdc-button-soft" style="color:#dc2626;border-color:#fca5a5;" onclick="return confirm('Réinitialiser tous les records BPF ?\nCela supprime les statuts, URLs de PDF et dates sauvegardées.\nLes données de formation en BDD ne sont pas affectées.');">Réinitialiser les BPF</a>
          </div>
        </div>
      </form>
    </div>
    <div class="acdc-panel acdc-table-panel">
      <div class="acdc-table-wrap">
        <table class="acdc-table acdc-bpf-table"><thead><tr><th>INTITULÉ</th><th>DÉBUT DE L'EXERCICE COMPTABLE</th><th>FIN DE L'EXERCICE COMPTABLE</th><th>STATUT</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ( $records as $row ) :
          $detail_url = add_query_arg( array( 'tab' => 'bpf', 'bpf_view' => 'detail', 'bpf_year' => $row['year'] ), $base_url );
          $status = $row['status'];
          $status_label = 'generated' === $status ? 'GÉNÉRÉ' : 'À GÉNÉRER';
          $status_class = 'generated' === $status ? 'acdc-status-pill acdc-status-pill-green' : 'acdc-status-pill acdc-status-pill-orange';
        ?>
        <tr>
          <td><?php echo esc_html( $row['title'] ); ?></td>
          <td><?php echo esc_html( $row['start_date'] ); ?></td>
          <td><?php echo esc_html( $row['end_date'] ); ?></td>
          <td><span class="<?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
          <td class="acdc-actions-cell-icons">
            <div class="acdc-groups-actions-inline">
              <div class="acdc-row-menu" data-acdc-bpf-row-menu>
                <button type="button"
                        class="acdc-row-action-icon"
                        data-acdc-bpf-row-menu-toggle
                        data-acdc-iconized="1"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-label="Plus d'actions"
                        title="Plus d'actions">
                  <?php echo $this->render_inline_icon( 'more-horizontal', 25 ); ?>
                  <span class="acdc-action-hub-sr screen-reader-text">Plus d'actions</span>
                </button>
                <div class="acdc-row-menu-dropdown" data-acdc-bpf-row-menu-dropdown hidden>
                  <a href="#" data-acdc-bpf-modal-open="acdc-bpf-generate-modal" data-bpf-year="<?php echo esc_attr( $row['year'] ); ?>" data-bpf-start="<?php echo esc_attr( $row['start_date'] ); ?>" data-bpf-end="<?php echo esc_attr( $row['end_date'] ); ?>">Générer le BPF prérempli</a>
                  <a href="#" data-acdc-bpf-modal-open="acdc-bpf-import-modal" data-bpf-year="<?php echo esc_attr( $row['year'] ); ?>" data-bpf-start="<?php echo esc_attr( $row['start_date'] ); ?>" data-bpf-end="<?php echo esc_attr( $row['end_date'] ); ?>">Importer le BPF</a>
                  <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'tab' => 'bpf', 'bpf_action' => 'reset_year', 'bpf_year' => $row['year'] ), $base_url ), 'acdc_reset_bpf_year' ) ); ?>" style="color:#dc2626;" onclick="return confirm('Réinitialiser le BPF <?php echo esc_js( $row['year'] ); ?> ?\nCela supprime le statut et les PDFs générés pour cette année.');">Réinitialiser ce BPF</a>
                </div>
              </div>
              <a href="<?php echo esc_url( $detail_url ); ?>"
                 class="acdc-row-action-icon acdc-row-view-link"
                 data-acdc-iconized="1"
                 title="Voir le BPF"
                 aria-label="Voir">
                <?php echo $this->render_inline_icon( 'view', 25 ); ?>
                <span class="acdc-action-hub-sr screen-reader-text">Voir</span>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody></table>
      </div>
      <div class="acdc-quotes-footer"><div>«</div><div>‹</div><div class="acdc-page-current">1</div><div>›</div><div>»</div><div class="acdc-page-meta">1-<?php echo count( $records ); ?> de <?php echo count( $records ); ?></div></div>
    </div>
    <?php $this->render_bpf_generate_modal(); ?>
    <?php $this->render_bpf_import_modal(); ?>
    <style>.acdc-bpf-table td,.acdc-bpf-table th{vertical-align:middle}.acdc-status-pill-green{background:#dff6e5;color:#35B37E}.acdc-status-pill-orange{background:#fdecc9;color:#c99512}</style>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
      function closeBpfMenus(){
        document.querySelectorAll('[data-acdc-bpf-row-menu-dropdown]').forEach(function(menu){ menu.hidden = true; });
        document.querySelectorAll('[data-acdc-bpf-row-menu-toggle]').forEach(function(btn){ btn.setAttribute('aria-expanded','false'); });
      }
      function openBpfModal(id){
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.hidden = false;
        modal.setAttribute('aria-hidden','false');
        document.body.classList.add('acdc-modal-open');
        document.documentElement.classList.add('acdc-modal-open');
      }
      document.querySelectorAll('[data-acdc-bpf-row-menu-toggle]').forEach(function(btn){
        btn.addEventListener('click', function(e){
          e.preventDefault(); e.stopPropagation();
          var wrap = btn.closest('[data-acdc-bpf-row-menu]');
          var drop = wrap ? wrap.querySelector('[data-acdc-bpf-row-menu-dropdown]') : null;
          var open = btn.getAttribute('aria-expanded') === 'true';
          closeBpfMenus();
          if (drop && !open) { drop.hidden = false; btn.setAttribute('aria-expanded','true'); }
        });
      });
      document.addEventListener('click', function(e){
        var triggerGenerate = e.target.closest('[data-acdc-bpf-modal-open="acdc-bpf-generate-modal"]');
        var triggerImport   = e.target.closest('[data-acdc-bpf-modal-open="acdc-bpf-import-modal"]');
        if (!e.target.closest('[data-acdc-bpf-row-menu]')) { closeBpfMenus(); }
        if (triggerGenerate) {
          e.preventDefault(); e.stopPropagation();
          var modal = document.getElementById('acdc-bpf-generate-modal');
          if (modal) {
            modal.querySelector('input[name="bpf_year"]').value   = triggerGenerate.getAttribute('data-bpf-year')  || '';
            modal.querySelector('input[name="start_date"]').value = triggerGenerate.getAttribute('data-bpf-start') || '';
            modal.querySelector('input[name="end_date"]').value   = triggerGenerate.getAttribute('data-bpf-end')   || '';
            openBpfModal('acdc-bpf-generate-modal');
          }
        }
        if (triggerImport) {
          e.preventDefault(); e.stopPropagation();
          var modalImport = document.getElementById('acdc-bpf-import-modal');
          if (modalImport) {
            modalImport.querySelector('input[name="bpf_year"]').value   = triggerImport.getAttribute('data-bpf-year')  || '';
            modalImport.querySelector('input[name="start_date"]').value = triggerImport.getAttribute('data-bpf-start') || '';
            modalImport.querySelector('input[name="end_date"]').value   = triggerImport.getAttribute('data-bpf-end')   || '';
            openBpfModal('acdc-bpf-import-modal');
          }
        }
      });
    });
    </script>
    <?php
  }



  private function render_bpf_generate_modal() {
    // ACDC 3.22.14 — Récupérer les valeurs du dernier BPF enregistré pour aperçu dans la modale
    $bpf_records   = $this->get_bpf_records();
    $last_bpf_data = array();
    $last_bpf_year = '';
    if ( ! empty( $bpf_records ) ) {
      $last_record = end( $bpf_records );
      if ( ! empty( $last_record['data'] ) ) {
        $last_bpf_data = $last_record['data'];
        $last_bpf_year = $last_record['year'] ?? '';
      }
    }
    $has_preview = ! empty( $last_bpf_data );
    $fmt_eur = function( $val ) { return number_format( (float) $val, 0, ',', ' ' ) . ' €'; };
    ?>
    <div class="acdc-modal-shell" id="acdc-bpf-generate-modal" hidden>
      <div class="acdc-modal-backdrop" data-acdc-modal-close></div>
      <div class="acdc-modal-dialog" style="max-width:700px;">
        <button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button>
        <div class="acdc-modal-header"><h3>GÉNÉRER LE BPF PRÉREMPLI</h3></div>
        <div class="acdc-modal-body">
          <form method="post" action="<?php echo esc_url( $this->portal_page_url( array( 'tab' => 'bpf' ) ) ); ?>">
            <input type="hidden" name="bpf_action" value="generate">
            <?php wp_nonce_field( 'acdc_generate_bpf_prefilled' ); ?>
            <input type="hidden" name="bpf_year" value="">
            <div class="acdc-contract-grid" style="grid-template-columns:240px minmax(0,1fr);">
              <div class="acdc-contract-label">Début de l'exercice comptable <span class="acdc-required">*</span></div>
              <div><input type="text" name="start_date" value="" placeholder="JJ/MM/AAAA"></div>
              <div class="acdc-contract-label">Fin de l'exercice comptable <span class="acdc-required">*</span></div>
              <div><input type="text" name="end_date" value="" placeholder="JJ/MM/AAAA"></div>
            </div>
            <?php if ( $has_preview ) : ?>
            <div style="margin-top:18px;padding:14px 16px;background:#f8fafc;border:1px solid #d7dde6;border-radius:8px;">
              <p style="margin:0 0 10px;font-size:12px;font-weight:700;color:#0f2c52;text-transform:uppercase;letter-spacing:.05em;">
                Aperçu des valeurs calculées
                <span style="font-weight:400;text-transform:none;color:#6b7280;margin-left:6px;">— dernier BPF généré (exercice <?php echo esc_html( $last_bpf_year ); ?>)</span>
              </p>
              <p style="margin:0 0 6px;font-size:11px;color:#6b7280;font-style:italic;">Ces montants seront recalculés pour la période saisie ci-dessus à la génération.</p>
              <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <tbody>
                  <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:5px 8px;color:#374151;">C1 — Produits des entreprises (formation salariés)</td>
                    <td style="padding:5px 8px;text-align:right;font-weight:700;color:#0f2c52;"><?php echo esc_html( $fmt_eur( $last_bpf_data['c1'] ?? 0 ) ); ?></td>
                  </tr>
                  <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:5px 8px;color:#374151;">C9 — Produits des particuliers à leurs frais</td>
                    <td style="padding:5px 8px;text-align:right;font-weight:700;color:#0f2c52;"><?php echo esc_html( $fmt_eur( $last_bpf_data['c9'] ?? 0 ) ); ?></td>
                  </tr>
                  <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:5px 8px;color:#374151;">D — Total charges liées à la formation</td>
                    <td style="padding:5px 8px;text-align:right;font-weight:700;color:#0f2c52;"><?php echo esc_html( $fmt_eur( $last_bpf_data['d_total'] ?? 0 ) ); ?></td>
                  </tr>
                  <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:5px 8px;color:#374151;padding-left:20px;">dont salaires formateurs internes</td>
                    <td style="padding:5px 8px;text-align:right;color:#6b7280;"><?php echo esc_html( $fmt_eur( $last_bpf_data['d_salaires'] ?? 0 ) ); ?></td>
                  </tr>
                  <tr>
                    <td style="padding:5px 8px;color:#374151;padding-left:20px;">dont honoraires formateurs externes (contrats)</td>
                    <td style="padding:5px 8px;text-align:right;color:#6b7280;"><?php echo esc_html( $fmt_eur( $last_bpf_data['d_achats'] ?? 0 ) ); ?></td>
                  </tr>
                </tbody>
              </table>
              <?php if ( 0 === (int)( $last_bpf_data['d_achats'] ?? 0 ) ) : ?>
              <p style="margin:8px 0 0;font-size:11px;color:#b45309;"><strong>Note :</strong> Aucun contrat formateur externe enregistré pour cette période — renseigner les honoraires manuellement sur MAF si applicable.</p>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="acdc-actions-end acdc-actions-gap-top-lg">
              <button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-close>Annuler</button>
              <button type="submit" class="acdc-button acdc-button-primary">Générer le BPF</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php
  }



  private function render_bpf_import_modal() {
    ?>
    <div class="acdc-modal-shell" id="acdc-bpf-import-modal" hidden><div class="acdc-modal-backdrop" data-acdc-modal-close></div><div class="acdc-modal-dialog"><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button><div class="acdc-modal-header"><h3>IMPORTER LE BPF</h3></div><div class="acdc-modal-body"><form method="post" action="<?php echo esc_url( $this->portal_page_url( array( 'tab' => 'bpf' ) ) ); ?>" enctype="multipart/form-data"><input type="hidden" name="bpf_action" value="import"><?php wp_nonce_field( 'acdc_import_bpf' ); ?><input type="hidden" name="bpf_year" value=""><div class="acdc-contract-grid acdc-contract-grid-compact"><div class="acdc-contract-label">Début de l'exercice comptable <span class="acdc-required">*</span></div><div><input type="text" name="start_date" value=""></div><div class="acdc-contract-label">Fin de l'exercice comptable <span class="acdc-required">*</span></div><div><input type="text" name="end_date" value=""></div><div class="acdc-contract-label">BPF <span class="acdc-required">*</span></div><div><label class="acdc-upload-dropzone"><span class="acdc-button acdc-button-primary">Choisir le fichier</span><span>Déposez le fichier ou cliquez pour choisir</span><input type="file" name="bpf_file" accept="application/pdf,.pdf,.doc,.docx,image/*" required></label><p class="acdc-help">Ajoutez un document au format PDF, Word ou une image</p></div></div><div class="acdc-actions-end acdc-actions-gap-top-lg"><button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-close>Annuler</button><button type="submit" class="acdc-button acdc-button-primary">Exécuter l'action</button></div></form></div></div></div>
    <?php
  }



  private function render_front_bpf_detail_page( $year ) {
    $records = $this->get_bpf_records();
    $record = isset( $records[ $year ] ) ? $records[ $year ] : reset( $records );
    $formations = array_values( array_filter( $this->get_catalog_formations( true ), function( $row ) { return (int) $row->include_bpf === 1; } ) );
    $company = $this->get_company_profile_options();
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'bpf' ) );
    $back_url = add_query_arg( array( 'tab' => 'bpf' ), $base_url );
    // ACDC 3.25.118 — Période d'exercice convertie JJ/MM/AAAA → AAAA-MM-JJ pour les requêtes par formation
    $bpf_start_sql = ''; $bpf_end_sql = '';
    if ( preg_match( '#^(\d{2})/(\d{2})/(\d{4})$#', (string) ( $record['start_date'] ?? '' ), $ms ) ) { $bpf_start_sql = $ms[3] . '-' . $ms[2] . '-' . $ms[1]; }
    if ( preg_match( '#^(\d{2})/(\d{2})/(\d{4})$#', (string) ( $record['end_date'] ?? '' ), $me ) )   { $bpf_end_sql   = $me[3] . '-' . $me[2] . '-' . $me[1]; }
    // ACDC 3.25.118 — Helpers de formatage pour les tableaux par formation
    $bpf_fmt_eur  = function( $v ) { return number_format( (float) str_replace( ',', '.', (string) $v ), 2, ',', ' ' ) . ' €'; };
    $bpf_fmt_date = function( $d ) { $d = (string) $d; if ( '' === $d || 0 === strpos( $d, '0000' ) ) { return ''; } $ts = strtotime( $d ); return $ts ? date_i18n( 'd/m/Y', $ts ) : $d; };
    ?>
    <section class="acdc-section-head"><div><h2>Bilan Pédagogique et Financier pré-rempli - <?php echo esc_html( $record['year'] ); ?></h2></div></section>
    <div style="padding:0 0 24px;"></div>
    <div class="acdc-panel acdc-mb-18"><a href="<?php echo esc_url( $back_url ); ?>" class="acdc-back-button"><?php echo $this->render_inline_icon( 'arrow-left', 16 ); ?></a></div>
    <div class="acdc-panel acdc-mb-18" style="padding:20px 24px;"><h3>Identification de l'organisme</h3><div class="acdc-bpf-meta-grid"><div><strong>Numéro NDA :</strong> <?php echo esc_html( $company['activity_declaration_number'] ?? '—' ); ?></div><div><strong>Forme juridique :</strong> <?php echo esc_html( $company['legal_form'] ?? '—' ); ?></div><div><strong>SIRET :</strong> <?php echo esc_html( $company['siret_identification'] ?? '—' ); ?></div><div><strong>Code NAF :</strong> <?php echo esc_html( $company['naf_code'] ?? '—' ); ?></div><div><strong>Nom :</strong> <?php echo esc_html( $company['enterprise'] ?? '—' ); ?></div><div><strong>Adresse :</strong> <?php echo esc_html( trim( ( $company['address'] ?? '' ) . ' ' . ( $company['postal_code'] ?? '' ) . ' ' . ( $company['city'] ?? '' ) ) ?: '—' ); ?></div><div><strong>Email :</strong> <?php echo esc_html( $company['enterprise_contact_email'] ?? '—' ); ?></div><div><strong>Téléphone :</strong> <?php echo esc_html( $company['enterprise_contact_phone'] ?? '—' ); ?></div></div></div>
    <div class="acdc-panel acdc-mb-18"><h3>Informations générales</h3><div><strong>Exercice comptable du <?php echo esc_html( $record['start_date'] ); ?> au <?php echo esc_html( $record['end_date'] ); ?></strong></div></div>
    <div class="acdc-panel acdc-mb-18"><h3>Formations intégrées au BPF</h3>
      <div class="acdc-bpf-accordion-list">
      <?php foreach ( $formations as $index => $formation ) : ?>
        <details class="acdc-bpf-accordion" <?php echo 0 === $index ? 'open' : ''; ?>>
          <summary><span class="acdc-bpf-toggle"><span class="acdc-bpf-switch on"></span><?php echo esc_html( $formation->title . ' - ' . $formation->modality . ' - ' . $formation->duration ); ?></span><span class="acdc-bpf-chevron"><?php echo $this->render_inline_icon( 'chevron-down', 14 ); ?></span></summary>
          <div class="acdc-bpf-accordion-body">
            <?php
            // ACDC 3.25.118 — Liste des actions de formation : peuplée depuis les conventions (registration_contracts) de cette formation sur la période
            $bpf_actions = $this->get_bpf_formation_actions( (int) $formation->id, $bpf_start_sql, $bpf_end_sql );
            ?>
            <div class="acdc-panel" style="margin:0 0 18px;"><h4>Liste des actions de formation</h4><div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Commanditaire</th><th>Dates de formation</th><th>Sous-traitance</th><th>Frais pédagogique</th><th>Frais transport / hébergement</th><th>BPF</th><th>Convention</th><th>Facture(s)</th></tr></thead><tbody>
              <?php if ( empty( $bpf_actions ) ) : ?>
                <tr><td colspan="8"><?php $this->render_empty_table_state( 'Aucune action de formation enregistrée pour cette formation sur la période.' ); ?></td></tr>
              <?php else : foreach ( $bpf_actions as $a ) :
                // ACDC 3.25.118
                $commanditaire = trim( (string) ( $a->company_name ?? '' ) );
                if ( '' === $commanditaire ) { $commanditaire = trim( (string) ( $a->commanditaire_type ?? '' ) ); }
                if ( '' === $commanditaire ) { $commanditaire = '—'; }
                $d_start = $bpf_fmt_date( $a->start_date ?? '' );
                $d_end   = $bpf_fmt_date( $a->end_date ?? '' );
                $dates   = $d_start;
                if ( '' !== $d_end && $d_end !== $d_start ) { $dates = ( '' !== $d_start ? $d_start . ' → ' : '' ) . $d_end; }
                if ( '' === $dates ) { $dates = '—'; }
                $frais_ped = ( isset( $a->price_ht ) && '' !== trim( (string) $a->price_ht ) ) ? $bpf_fmt_eur( $a->price_ht ) : '—';
                $frais_tr  = ( (int) ( $a->transport_fees_enabled ?? 0 ) ? (float) str_replace( ',', '.', (string) ( $a->transport_fees_amount_ht ?? 0 ) ) : 0 )
                           + ( (int) ( $a->meal_fees_enabled ?? 0 )      ? (float) str_replace( ',', '.', (string) ( $a->meal_fees_amount_ht ?? 0 ) ) : 0 );
                $frais_tr_txt = $frais_tr > 0 ? $bpf_fmt_eur( $frais_tr ) : '—';
                $bpf_flag  = ( (int) ( $formation->include_bpf ?? 0 ) === 1 ) ? 'Oui' : 'Non';
                $conv_url  = trim( (string) ( $a->signed_document_url ?? '' ) );
                if ( '' === $conv_url ) { $conv_url = trim( (string) ( $a->document_url ?? '' ) ); }
              ?>
                <tr>
                  <td><?php echo esc_html( $commanditaire ); ?></td>
                  <td><?php echo esc_html( $dates ); ?></td>
                  <td>—</td>
                  <td><?php echo esc_html( $frais_ped ); ?></td>
                  <td><?php echo esc_html( $frais_tr_txt ); ?></td>
                  <td><?php echo esc_html( $bpf_flag ); ?></td>
                  <td><?php if ( '' !== $conv_url ) : ?><a href="<?php echo esc_url( $conv_url ); ?>" target="_blank" rel="noopener">Voir</a><?php else : ?>—<?php endif; ?></td>
                  <td>—</td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody></table></div></div>
            <?php
            // ACDC 3.25.118 — Liste des formateurs : peuplée depuis les sessions (trainer_id) de cette formation sur la période
            $bpf_trainers = $this->get_bpf_formation_trainers( (int) $formation->id, $bpf_start_sql, $bpf_end_sql, (string) ( $formation->title ?? '' ), (string) ( $formation->code ?? '' ) );
            ?>
            <div class="acdc-panel"><h4>Liste des formateurs de cette formation</h4><div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Formateur</th><th>Type</th><th>Taux horaire (HT)</th><th>BPF</th></tr></thead><tbody>
              <?php if ( empty( $bpf_trainers ) ) : ?>
                <tr><td colspan="4"><?php $this->render_empty_table_state( 'Aucun formateur rattaché aux sessions de cette formation sur la période.' ); ?></td></tr>
              <?php else : foreach ( $bpf_trainers as $t ) :
                // ACDC 3.25.118
                $t_name = trim( (string) ( $t->first_name ?? '' ) . ' ' . (string) ( $t->last_name ?? '' ) );
                if ( '' === $t_name ) { $t_name = '—'; }
                $t_type = ( (int) ( $t->is_self_trainer ?? 0 ) === 1 ) ? 'Interne (salarié)' : 'Externe (sous-traitant)';
                $t_taux = ( (int) ( $t->is_self_trainer ?? 0 ) === 0 && null !== $t->taux_ht ) ? $bpf_fmt_eur( $t->taux_ht ) . '/h' : '—';
                $t_bpf  = ( (int) ( $formation->include_bpf ?? 0 ) === 1 ) ? 'Oui' : 'Non';
              ?>
                <tr>
                  <td><?php echo esc_html( $t_name ); ?></td>
                  <td><?php echo esc_html( $t_type ); ?></td>
                  <td><?php echo esc_html( $t_taux ); ?></td>
                  <td><?php echo esc_html( $t_bpf ); ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody></table></div></div>
          </div>
        </details>
      <?php endforeach; ?>
      </div>
    </div>

    <?php
    // ACDC 3.21.72 — Afficher les données calculées si disponibles
    $bpf_data = isset( $record['data'] ) && is_array( $record['data'] ) ? $record['data'] : array();
    if ( ! empty( $bpf_data ) ) :
    ?>
    <div class="acdc-panel acdc-mb-18">
      <h3>Cadre E — Formateurs et heures dispensées <small style="color:#6b7280;font-size:12px;font-weight:400;">(calculé automatiquement)</small></h3>
      <table class="acdc-table" style="max-width:560px;">
        <thead><tr><th>Type</th><th>Nb formateurs</th><th>H</th></tr></thead>
        <tbody>
          <tr><td>Formateurs internes (salariés)</td><td><?php echo (int) ( $bpf_data['e_nb_internes'] ?? 0 ); ?></td><td><?php echo esc_html( number_format_i18n( (float) ( $bpf_data['e_h_internes'] ?? 0 ), 1 ) ); ?> H</td></tr>
          <tr><td>Formateurs externes</td><td><?php echo (int) ( $bpf_data['e_nb_externes'] ?? 0 ); ?></td><td><?php echo esc_html( number_format_i18n( (float) ( $bpf_data['e_h_externes'] ?? 0 ), 1 ) ); ?> H</td></tr>
          <tr style="font-weight:700;"><td>Total</td><td><?php echo (int) ( ( $bpf_data['e_nb_internes'] ?? 0 ) + ( $bpf_data['e_nb_externes'] ?? 0 ) ); ?></td><td><?php echo esc_html( number_format_i18n( (float) ( ( $bpf_data['e_h_internes'] ?? 0 ) + ( $bpf_data['e_h_externes'] ?? 0 ) ), 1 ) ); ?> H</td></tr>
        </tbody>
      </table>
    </div>
    <div class="acdc-panel acdc-mb-18">
      <h3>Cadre F1 — Types de stagiaires <small style="color:#6b7280;font-size:12px;font-weight:400;">(calculé automatiquement)</small></h3>
      <table class="acdc-table" style="max-width:560px;">
        <thead><tr><th>Catégorie</th><th>Nb stagiaires</th></tr></thead>
        <tbody>
          <tr><td>Salariés</td><td><?php echo (int) ( $bpf_data['f1_salaries'] ?? 0 ); ?></td></tr>
          <tr><td>Apprentis</td><td><?php echo (int) ( $bpf_data['f1_apprentis'] ?? 0 ); ?></td></tr>
          <tr><td>Demandeurs d'emploi (France Travail)</td><td><?php echo (int) ( $bpf_data['f1_demandeurs'] ?? 0 ); ?></td></tr>
          <tr><td>Particuliers à leurs frais</td><td><?php echo (int) ( $bpf_data['f1_particuliers'] ?? 0 ); ?></td></tr>
          <tr><td>Indépendants / Dirigeants</td><td><?php echo (int) ( $bpf_data['f1_independants'] ?? 0 ); ?></td></tr>
          <tr><td>Autres</td><td><?php echo (int) ( $bpf_data['f1_autres'] ?? 0 ); ?></td></tr>
          <tr style="font-weight:700;"><td>Total stagiaires</td><td><?php echo (int) ( $bpf_data['f1_total'] ?? 0 ); ?></td></tr>
        </tbody>
      </table>
    </div>
    <?php if ( ! empty( $bpf_data['f3'] ) ) : ?>
    <div class="acdc-panel acdc-mb-18">
      <h3>Cadre F3 — Objectif général des prestations <small style="color:#6b7280;font-size:12px;font-weight:400;">(calculé automatiquement)</small></h3>
      <table class="acdc-table" style="max-width:560px;">
        <thead><tr><th>Objectif</th><th>Nb stagiaires</th></tr></thead>
        <tbody>
          <?php foreach ( (array) $bpf_data['f3'] as $label => $nb ) : ?>
          <tr><td><?php echo esc_html( $label ); ?></td><td><?php echo (int) $nb; ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
    <?php if ( ! empty( $bpf_data['f4'] ) ) : ?>
    <div class="acdc-panel acdc-mb-18">
      <h3>Cadre F4 — Spécialités NSF <small style="color:#6b7280;font-size:12px;font-weight:400;">(calculé automatiquement)</small></h3>
      <table class="acdc-table" style="max-width:560px;">
        <thead><tr><th>Code NSF</th><th>Stagiaires</th><th>Sessions</th></tr></thead>
        <tbody>
          <?php foreach ( (array) $bpf_data['f4'] as $r ) : $nsf = trim( (string) ( is_object( $r ) ? $r->specialty : ( $r['specialty'] ?? '' ) ) ); if ( '' === $nsf ) $nsf = 'Non renseigné'; ?>
          <tr><td><?php echo esc_html( $nsf ); ?></td><td><?php echo (int) ( is_object( $r ) ? $r->nb_apprenants : $r['nb_apprenants'] ); ?></td><td><?php echo (int) ( is_object( $r ) ? $r->nb_sessions : $r['nb_sessions'] ); ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
    <div class="acdc-panel acdc-mb-18">
      <h3>Cadre C — Bilan financier : origine des produits (HT) <small style="color:#6b7280;font-size:12px;font-weight:400;">(calculé automatiquement)</small></h3>
      <table class="acdc-table" style="max-width:560px;">
        <thead><tr><th>Ligne</th><th style="text-align:right;">Montant HT</th></tr></thead>
        <tbody>
          <tr><td>1 — Entreprises (formation salariés)</td><td style="text-align:right;"><?php echo esc_html( number_format( (float)( $bpf_data['c1'] ?? 0 ), 0, ',', ' ' ) ); ?> €</td></tr>
          <tr><td>9 — Particuliers à titre individuel</td><td style="text-align:right;"><?php echo esc_html( number_format( (float)( $bpf_data['c9'] ?? 0 ), 0, ',', ' ' ) ); ?> €</td></tr>
          <tr><td>10 — Autres organismes de formation (sous-traitance reçue)</td><td style="text-align:right;font-weight:700;"><?php echo esc_html( number_format( (float)( $bpf_data['c10'] ?? 0 ), 0, ',', ' ' ) ); ?> €</td></tr>
          <tr style="font-weight:700;border-top:2px solid #d7dde6;"><td>TOTAL (lignes 1 à 11)</td><td style="text-align:right;"><?php echo esc_html( number_format( (float)( $bpf_data['c_total'] ?? 0 ), 0, ',', ' ' ) ); ?> €</td></tr>
        </tbody>
      </table>
      <?php if ( empty( $bpf_data['c1'] ) && empty( $bpf_data['c9'] ) ) : ?>
      <p style="margin:8px 0 0;font-size:12px;color:#6b7280;font-style:italic;">C1 et C9 à 0 — activité 100 % sous-traitance pour d'autres organismes de formation (normal pour un formateur indépendant).</p>
      <?php endif; ?>
    </div>
    <div class="acdc-panel acdc-mb-18">
      <h3>Cadre D — Bilan financier : charges (HT) <small style="color:#6b7280;font-size:12px;font-weight:400;">(calculé automatiquement)</small></h3>
      <table class="acdc-table" style="max-width:560px;">
        <thead><tr><th>Ligne</th><th style="text-align:right;">Montant HT</th></tr></thead>
        <tbody>
          <tr><td>Total charges liées à la formation</td><td style="text-align:right;font-weight:700;"><?php echo esc_html( number_format( (float)( $bpf_data['d_total'] ?? 0 ), 0, ',', ' ' ) ); ?> €</td></tr>
          <tr><td style="padding-left:20px;">dont salaires formateurs internes</td><td style="text-align:right;color:#6b7280;"><?php echo esc_html( number_format( (float)( $bpf_data['d_salaires'] ?? 0 ), 0, ',', ' ' ) ); ?> €</td></tr>
          <tr><td style="padding-left:20px;">dont honoraires formateurs externes (contrats)</td><td style="text-align:right;color:#6b7280;"><?php echo esc_html( number_format( (float)( $bpf_data['d_achats'] ?? 0 ), 0, ',', ' ' ) ); ?> €</td></tr>
        </tbody>
      </table>
      <?php if ( empty( $bpf_data['d_achats'] ) ) : ?>
      <p style="margin:8px 0 0;font-size:12px;color:#b45309;font-style:italic;">Aucun contrat formateur externe enregistré sur cette période — renseigner manuellement sur MAF si applicable.</p>
      <?php endif; ?>
    </div>
    <div class="acdc-panel acdc-mb-18" style="background:#fef3c7;border-left:4px solid #C5A253;padding:14px 18px;">
      <strong style="color:#92400e;">Cadres restants à compléter manuellement sur monactiviteformation.emploi.gouv.fr :</strong>
      <ul style="margin:8px 0 0 18px;color:#78350f;font-size:13px;">
        <li><strong>C lignes 2 à 8, 11</strong> : OPCO, pouvoirs publics, autres produits</li>
        <li><strong>G — Activité sous-traitée</strong> : formations confiées par un tiers</li>
        <li><strong>H — Dirigeant</strong> : nom, prénom, qualité</li>
      </ul>
      <?php if ( ! empty( $bpf_data['generated_at'] ) ) : ?>
      <p style="margin:10px 0 0;font-size:12px;color:#78350f;">Données calculées le <?php echo esc_html( mysql2date( 'd/m/Y à H:i', $bpf_data['generated_at'] ) ); ?></p>
      <?php endif; ?>
    </div>
    <?php endif; // $bpf_data ?>

    <div class="acdc-actions-end" style="gap:12px;">

      <?php if ( ! empty( $record['cerfa_url'] ) ) : ?>
        <a href="<?php echo esc_url( $record['cerfa_url'] ); ?>" class="acdc-button acdc-button-primary" target="_blank" download>⬇ CERFA BPF officiel (PDF)</a>
      <?php endif; ?>
      <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'tab' => 'bpf', 'bpf_action' => 'generate', 'bpf_year' => $record['year'], 'start_date' => $record['start_date'], 'end_date' => $record['end_date'] ), $this->portal_page_url( array( 'tab' => 'bpf' ) ) ), 'acdc_generate_bpf_prefilled' ) ); ?>" class="acdc-button acdc-button-primary">Recalculer le BPF</a>
    </div>
    <style>.acdc-bpf-meta-grid{display:grid;gap:14px}.acdc-bpf-accordion-list{display:grid;gap:14px}.acdc-bpf-accordion{border:1px solid #dce4ec;border-radius:10px;background:#fff;overflow:hidden}.acdc-bpf-accordion summary{list-style:none;display:flex;align-items:center;justify-content:space-between;padding:14px 16px;cursor:pointer;color:#1E4777;font-weight:600}.acdc-bpf-accordion summary::-webkit-details-marker{display:none}.acdc-bpf-accordion-body{padding:12px 14px 16px;background:#F9FAFB}.acdc-bpf-toggle{display:flex;align-items:center;gap:10px}.acdc-bpf-switch{display:inline-flex;width:28px;height:18px;border-radius:999px;background:#d7dee8;position:relative}.acdc-bpf-switch:before{content:'';position:absolute;top:2px;left:2px;width:14px;height:14px;border-radius:50%;background:#fff}.acdc-bpf-switch.on{background:#bb7af7}.acdc-bpf-switch.on:before{left:12px}.acdc-bpf-chevron{display:inline-flex;width:22px;height:22px;align-items:center;justify-content:center;border-radius:999px;background:#C5A253;color:#0B0706}.acdc-back-button{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:10px;border:1px solid #dce4ec;color:#1E4777;text-decoration:none}</style>
    <?php
  }


  // ACDC 3.21.75 — Prestations extérieures (formateur indépendant)

  private function render_front_external_missions_tab() {
    // ACDC 3.21.78 — Interception des actions via portal_page_url (WAF-safe)
    $ext_action = isset( $_REQUEST['ext_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['ext_action'] ) ) : '';

    if ( 'save' === $ext_action ) {
      if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
      check_admin_referer( 'acdc_save_external_mission' );
      $this->handle_save_external_mission();
      return;
    }
    if ( 'delete' === $ext_action ) {
      if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
      $mid = isset( $_GET['mission_id'] ) ? sanitize_text_field( wp_unslash( $_GET['mission_id'] ) ) : '';
      check_admin_referer( 'acdc_delete_external_mission_' . $mid );
      $this->handle_delete_external_mission();
      return;
    }
    if ( 'import_csv' === $ext_action && isset( $_FILES['csv_file'] ) ) {
      if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
      check_admin_referer( 'acdc_import_external_missions_csv' );
      $this->handle_import_external_missions_csv();
      return;
    }

    $action    = isset( $_GET['action'] )  ? sanitize_key( wp_unslash( $_GET['action'] ) )  : 'list';
    $item_id   = isset( $_GET['item_id'] ) ? sanitize_text_field( wp_unslash( $_GET['item_id'] ) ) : '';
    $base_url  = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'external_missions' ) );
    $new_url   = add_query_arg( array( 'tab' => 'external_missions', 'action' => 'new' ), $base_url );
    $list_url  = add_query_arg( array( 'tab' => 'external_missions' ), $base_url );

    if ( in_array( $action, array( 'new', 'edit' ), true ) ) {
      $mission = ( 'edit' === $action && '' !== $item_id ) ? $this->get_external_mission( $item_id ) : null;
      $this->render_front_external_mission_form( $mission, $base_url, $list_url );
      return;
    }

    $records  = $this->get_external_mission_records();
    $search   = isset( $_GET['q'] ) ? strtolower( sanitize_text_field( wp_unslash( $_GET['q'] ) ) ) : '';
    if ( '' !== $search ) {
      $records = array_values( array_filter( $records, function( $r ) use ( $search ) {
        return false !== strpos( strtolower( (string) ( $r['commanditaire'] ?? '' ) ), $search )
            || false !== strpos( strtolower( (string) ( $r['type_public'] ?? '' ) ), $search )
            || false !== strpos( strtolower( (string) ( $r['financement'] ?? '' ) ), $search );
      } ) );
    }

    // Totaux
    $total_heures           = array_sum( array_column( $records, 'heures_total' ) );
    $total_heures_formateur = array_sum( array_column( $records, 'heures_par_stagiaire' ) );
    $total_ca               = array_sum( array_column( $records, 'ca_ht' ) );
    $total_stag             = array_sum( array_column( $records, 'nb_stagiaires' ) );

    $notice_code = isset( $_GET['notice'] ) ? sanitize_text_field( wp_unslash( $_GET['notice'] ) ) : '';
    $notice_type = isset( $_GET['notice_type'] ) ? sanitize_key( wp_unslash( $_GET['notice_type'] ) ) : 'success';
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Prestations extérieures</h2>
        <p>Journal des interventions en tant que formateur indépendant pour d'autres organismes — intégré automatiquement au BPF.</p>
      </div>
      <div class="acdc-inline-wrap">
        <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( add_query_arg( array( 'tab' => 'external_missions', 'action' => 'import' ), $base_url ) ); ?>" data-acdc-modal-open="acdc-ext-import-modal">⬆ Importer CSV</a>
        <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $new_url ); ?>">+ Ajouter une prestation</a>
      </div>
    </section>

    <?php if ( '' !== $notice_code ) : ?>
    <div class="acdc-notice acdc-notice-<?php echo esc_attr( $notice_type ); ?>" style="margin-bottom:16px;padding:12px 16px;border-radius:8px;background:<?php echo 'success' === $notice_type ? '#dff6e5' : '#fee2e2'; ?>;color:<?php echo 'success' === $notice_type ? '#1B5E20' : '#991b1b'; ?>;">
      <?php echo esc_html( $notice_code ); ?>
    </div>
    <?php endif; ?>

    <!-- Cartes récapitulatives -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px;">
      <div class="acdc-panel" style="text-align:center;padding:18px;">
        <div style="font-size:26px;font-weight:700;color:#0C2D52;"><?php echo (int) count( $records ); ?></div>
        <div style="font-size:13px;color:#6b7280;margin-top:4px;">Prestations</div>
      </div>
      <div class="acdc-panel" style="text-align:center;padding:18px;">
        <div style="font-size:26px;font-weight:700;color:#0C2D52;"><?php echo esc_html( number_format_i18n( $total_heures, 1 ) ); ?> H</div>
        <div style="font-size:13px;color:#6b7280;margin-top:4px;">Heures totales</div>
      </div>
      <div class="acdc-panel" style="text-align:center;padding:18px;">
        <div style="font-size:26px;font-weight:700;color:#C5A253;"><?php echo esc_html( number_format_i18n( $total_ca, 2 ) ); ?> €</div>
        <div style="font-size:13px;color:#6b7280;margin-top:4px;">CA HT total</div>
      </div>
    </div>

    <!-- Barre de recherche -->
    <div class="acdc-panel acdc-mb-18">
      <form method="get" action="">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
        <input type="hidden" name="tab" value="external_missions">
        <div class="acdc-inline-wrap">
          <input type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="Rechercher (commanditaire, type…)" style="max-width:380px;">
          <button type="submit" class="acdc-button acdc-button-soft">Rechercher</button>
          <?php if ( '' !== $search ) : ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $list_url ); ?>">Réinitialiser</a><?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Tableau -->
    <div class="acdc-panel">
      <?php if ( empty( $records ) ) : ?>
        <div style="padding:48px;text-align:center;color:#6b7280;">
          <p>Aucune prestation enregistrée.<br>Cliquez sur <strong>"+ Ajouter une prestation"</strong> ou importez votre fichier CSV Notion.</p>
        </div>
      <?php else : ?>
      <div class="acdc-table-wrap">
        <table class="acdc-table">
          <thead>
            <tr>
              <th>Commanditaire</th>
              <th>Période</th>
              <th>Stagiaires</th>
              <th>Heures de formation</th>
              <th>Heures cumulées apprenants</th>
              <th>CA HT</th>
              <th>Tarif/H</th>
              <th>Public</th>
              <th>Modalité</th>
              <th>Financement</th>
              <th>Statut</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php
          // Récupérer les dates d'exercice depuis le profil
          $profile_ex    = method_exists( $this, 'get_company_profile_options' ) ? $this->get_company_profile_options() : array();
          $acc_start_raw = ! empty( $profile_ex['accounting_start'] ) ? rtrim( (string) $profile_ex['accounting_start'], '/' ) : '01/01';
          $acc_end_raw   = ! empty( $profile_ex['accounting_end'] )   ? rtrim( (string) $profile_ex['accounting_end'],   '/' ) : '31/12';

          // Déterminer l'exercice d'appartenance d'une date SQL (YYYY-MM-DD)
          $get_exercice = function( $date_sql ) use ( $acc_start_raw ) {
            if ( empty( $date_sql ) ) { return 0; }
            $y  = (int) substr( $date_sql, 0, 4 );
            $sm = (int) substr( $acc_start_raw, 3, 2 );
            $sd = (int) substr( $acc_start_raw, 0, 2 );
            $dm = (int) substr( $date_sql, 5, 2 );
            $dd = (int) substr( $date_sql, 8, 2 );
            // Si la date est avant le début d'exercice dans l'année Y → exercice Y-1
            if ( $dm < $sm || ( $dm === $sm && $dd < $sd ) ) { return $y - 1; }
            return $y;
          };

          $current_ex   = null;
          $subtotal_h   = 0.0;
          $subtotal_hf  = 0.0;
          $subtotal_ca  = 0.0;
          foreach ( $records as $r ) :
            $r_id    = (string) ( $r['id'] ?? '' );
            $periode = $r['start_date_display'] ?? substr( (string) ( $r['start_date'] ?? '' ), 0, 10 );
            if ( ! empty( $r['end_date'] ) && $r['end_date'] !== $r['start_date'] ) {
              $end_d = $r['end_date_display'] ?? substr( (string) $r['end_date'], 0, 10 );
              if ( $end_d !== $periode ) { $periode .= ' → ' . $end_d; }
            }
            $row_ex       = $get_exercice( (string) ( $r['start_date'] ?? '' ) );
            $statut_color = 'OK' === ( $r['statut'] ?? '' ) ? '#22c55e' : '#f97316';
            $edit_url     = add_query_arg( array( 'tab' => 'external_missions', 'action' => 'edit', 'item_id' => $r_id ), $base_url );
            $delete_url   = wp_nonce_url( add_query_arg( array( 'tab' => 'external_missions', 'ext_action' => 'delete', 'mission_id' => rawurlencode( $r_id ) ), $this->portal_page_url( array( 'tab' => 'external_missions' ) ) ), 'acdc_delete_external_mission_' . $r_id );

            // Sous-total exercice précédent + séparateur
            if ( $current_ex !== null && $row_ex !== $current_ex ) {
              $ex_start_lbl = $acc_start_raw . '/' . $current_ex;
              $sm2 = (int) substr( $acc_start_raw, 3, 2 ); $sd2 = (int) substr( $acc_start_raw, 0, 2 );
              $em2 = (int) substr( $acc_end_raw, 3, 2 );   $ed2 = (int) substr( $acc_end_raw, 0, 2 );
              $ex_end_year  = ( $em2 < $sm2 || ( $em2 === $sm2 && $ed2 < $sd2 ) ) ? $current_ex + 1 : $current_ex;
              $ex_end_lbl   = $acc_end_raw . '/' . $ex_end_year;
          ?>
            <tr style="background:var(--acdc-ui-table-header-bg,#fbf8f7);font-style:italic;color:#555;">
              <td colspan="3" style="text-align:right;padding-right:8px;font-weight:600;">Sous-total exercice <?php echo esc_html( $current_ex ); ?></td>
              <td style="text-align:right;font-weight:700;"><?php echo esc_html( number_format_i18n( $subtotal_hf, 1 ) ); ?> H</td>
              <td style="text-align:right;font-weight:700;"><?php echo esc_html( number_format_i18n( $subtotal_h, 1 ) ); ?> H</td>
              <td style="text-align:right;font-weight:700;color:#C5A253;"><?php echo esc_html( number_format_i18n( $subtotal_ca, 2 ) ); ?> €</td>
              <td colspan="6"></td>
            </tr>
            <tr><td colspan="12" style="padding:0;"><div style="height:2px;background:linear-gradient(90deg,#C5A253,transparent);margin:2px 0;"></div></td></tr>
          <?php
              $subtotal_h = $subtotal_hf = $subtotal_ca = 0.0;
            }

            // Bandeau exercice
            if ( $row_ex !== $current_ex ) {
              $current_ex   = $row_ex;
              $sm2 = (int) substr( $acc_start_raw, 3, 2 ); $sd2 = (int) substr( $acc_start_raw, 0, 2 );
              $em2 = (int) substr( $acc_end_raw, 3, 2 );   $ed2 = (int) substr( $acc_end_raw, 0, 2 );
              $ex_end_year  = ( $em2 < $sm2 || ( $em2 === $sm2 && $ed2 < $sd2 ) ) ? $current_ex + 1 : $current_ex;
              $ex_lbl = $acc_start_raw . '/' . $current_ex . ' → ' . $acc_end_raw . '/' . $ex_end_year;
          ?>
            <tr>
              <td colspan="12" style="padding:6px 10px;background:var(--acdc-ui-table-header-bg,#fbf8f7);color:#0f2c52;font-size:11px;font-weight:700;letter-spacing:1px;border-left:3px solid #C5A253;">
                📅 Exercice <?php echo esc_html( $current_ex ); ?> — <?php echo esc_html( $ex_lbl ); ?>
              </td>
            </tr>
          <?php } ?>
          <tr>
            <td><strong><?php echo esc_html( $r['commanditaire'] ?? '—' ); ?></strong></td>
            <td style="white-space:nowrap;"><?php echo esc_html( $periode ); ?></td>
            <td style="text-align:center;"><?php echo (int) ( $r['nb_stagiaires'] ?? 0 ); ?></td>
            <td style="text-align:right;"><?php echo esc_html( number_format_i18n( (float) ( $r['heures_par_stagiaire'] ?? 0 ), 1 ) ); ?> H</td>
            <td style="text-align:right;font-weight:600;"><?php echo esc_html( number_format_i18n( (float) ( $r['heures_total'] ?? 0 ), 1 ) ); ?> H</td>
            <td style="text-align:right;color:#C5A253;font-weight:600;"><?php echo esc_html( number_format_i18n( (float) ( $r['ca_ht'] ?? 0 ), 2 ) ); ?> €</td>
            <td style="text-align:right;"><?php echo $r['tarif_horaire'] ?? '' ? esc_html( number_format_i18n( (float) $r['tarif_horaire'], 2 ) ) . ' €' : '—'; ?></td>
            <td><?php echo esc_html( $r['type_public'] ?? '—' ); ?></td>
            <td><?php echo esc_html( $r['modalite'] ?? '—' ); ?></td>
            <td><?php echo esc_html( $r['financement'] ?? '—' ); ?></td>
            <td><span style="font-weight:600;color:<?php echo esc_attr( $statut_color ); ?>;"><?php echo esc_html( $r['statut'] ?? '—' ); ?></span></td>
            <td class="acdc-actions-cell-icons">
              <div class="acdc-groups-actions-inline">
                <a href="<?php echo esc_url( $edit_url ); ?>"
                   class="acdc-row-action-icon acdc-row-edit-link"
                   data-acdc-iconized="1"
                   title="Modifier la prestation"
                   aria-label="Modifier">
                  <?php echo $this->render_inline_icon( 'edit-pencil', 25 ); ?>
                  <span class="acdc-action-hub-sr screen-reader-text">Modifier</span>
                </a>
                <a href="<?php echo esc_url( $delete_url ); ?>"
                   class="acdc-row-action-icon acdc-row-delete-link"
                   data-acdc-iconized="1"
                   title="Supprimer la prestation"
                   aria-label="Supprimer"
                   onclick="return confirm('Supprimer cette prestation ?');">
                  <?php echo $this->render_inline_icon( 'trash-bin', 25 ); ?>
                  <span class="acdc-action-hub-sr screen-reader-text">Supprimer</span>
                </a>
              </div>
            </td>
          </tr>
          <?php
            $subtotal_h  += (float) ( $r['heures_total'] ?? 0 );
            $subtotal_hf += (float) ( $r['heures_par_stagiaire'] ?? 0 );
            $subtotal_ca += (float) ( $r['ca_ht'] ?? 0 );
          endforeach;
          // Sous-total dernier groupe
          if ( $current_ex !== null ) : ?>
            <tr style="background:var(--acdc-ui-table-header-bg,#fbf8f7);font-style:italic;color:#555;">
              <td colspan="3" style="text-align:right;padding-right:8px;font-weight:600;">Sous-total exercice <?php echo esc_html( $current_ex ); ?></td>
              <td style="text-align:right;font-weight:700;"><?php echo esc_html( number_format_i18n( $subtotal_hf, 1 ) ); ?> H</td>
              <td style="text-align:right;font-weight:700;"><?php echo esc_html( number_format_i18n( $subtotal_h, 1 ) ); ?> H</td>
              <td style="text-align:right;font-weight:700;color:#C5A253;"><?php echo esc_html( number_format_i18n( $subtotal_ca, 2 ) ); ?> €</td>
              <td colspan="6"></td>
            </tr>
            <tr><td colspan="12" style="padding:0;"><div style="height:2px;background:linear-gradient(90deg,#C5A253,transparent);margin:2px 0;"></div></td></tr>
          <?php endif; ?>
          <tr style="background:#f0f4f8;font-weight:700;">
            <td colspan="3" style="text-align:right;padding-right:8px;">TOTAUX</td>
            <td style="text-align:right;"><?php echo esc_html( number_format_i18n( $total_heures_formateur, 1 ) ); ?> H</td>
            <td style="text-align:right;"><?php echo esc_html( number_format_i18n( $total_heures, 1 ) ); ?> H</td>
            <td style="text-align:right;color:#C5A253;"><?php echo esc_html( number_format_i18n( $total_ca, 2 ) ); ?> €</td>
            <td colspan="6"></td>
          </tr>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Modale import CSV -->
    <div class="acdc-modal-shell" id="acdc-ext-import-modal" hidden>
      <div class="acdc-modal-backdrop" data-acdc-modal-close></div>
      <div class="acdc-modal-dialog">
        <button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button>
        <div class="acdc-modal-header"><h3>Importer des prestations (CSV)</h3></div>
        <div class="acdc-modal-body">
          <form method="post" action="<?php echo esc_url( $this->portal_page_url( array( 'tab' => 'external_missions' ) ) ); ?>" enctype="multipart/form-data">
            <input type="hidden" name="ext_action" value="import_csv">
            <?php wp_nonce_field( 'acdc_import_external_missions_csv' ); ?>
            <p style="color:#6b7280;font-size:13px;margin:0 0 14px;">Colonnes reconnues : <em>Nom de l'action, Période, Nombre de stagiaires, Heures de formation, Heures total, CA généré, Tarif horaire, Type de public, Modalité, Financement</em>.<br>Séparateur virgule ou point-virgule. Compatible export Notion.</p>
            <label class="acdc-upload-dropzone">
              <span class="acdc-button acdc-button-soft">Choisir le fichier CSV</span>
              <input type="file" name="csv_file" accept=".csv,text/csv" required>
            </label>
            <div class="acdc-actions-end acdc-actions-gap-top-lg">
              <button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-close>Annuler</button>
              <button type="submit" class="acdc-button acdc-button-primary">Importer</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php
  }


  private function render_front_external_mission_form( $mission, $base_url, $list_url ) {
    $is_edit = null !== $mission;
    $v = function( $key, $default = '' ) use ( $mission ) {
      return $mission && isset( $mission[ $key ] ) ? $mission[ $key ] : $default;
    };
    $page_title = $is_edit ? 'Modifier une prestation' : 'Ajouter une prestation';
    // ACDC 3.21.129 — type="date" : valeur attendue en YYYY-MM-DD
    // start_date est toujours stocké en YYYY-MM-DD (via $to_sql_date dans handle_save_external_mission)
    $start_date_val = $v('start_date'); // YYYY-MM-DD ou vide
    $end_date_val   = $v('end_date');   // YYYY-MM-DD ou vide
    ?>
    <section class="acdc-section-head">
      <div><h2><?php echo esc_html( $page_title ); ?></h2></div>
      <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $list_url ); ?>">← Retour</a>
    </section>
    <div class="acdc-panel">
      <form method="post" action="<?php echo esc_url( $this->portal_page_url( array( 'tab' => 'external_missions' ) ) ); ?>" class="acdc-form">
        <input type="hidden" name="ext_action" value="save">
        <input type="hidden" name="mission_id" value="<?php echo esc_attr( $is_edit ? (string) $mission['id'] : '' ); ?>">
        <?php wp_nonce_field( 'acdc_save_external_mission' ); ?>

        <div class="acdc-contract-grid" style="grid-template-columns:220px minmax(0,1fr);align-items:start;gap:12px 24px;">

          <div class="acdc-contract-label">Commanditaire (OF) <span class="acdc-required">*</span></div>
          <div><input type="text" name="mission[commanditaire]" value="<?php echo esc_attr( $v('commanditaire') ); ?>" required maxlength="190" placeholder="Ex : K Formation, SUP'IPGV…"></div>

          <div class="acdc-contract-label">Date de début <span class="acdc-required">*</span></div>
          <div><input type="date" id="mission_start_date" name="mission[start_date]" value="<?php echo esc_attr( $start_date_val ); ?>" required style="max-width:180px;"></div>

          <div class="acdc-contract-label">Date de fin</div>
          <div><input type="date" id="mission_end_date" name="mission[end_date]" value="<?php echo esc_attr( $end_date_val ); ?>" style="max-width:180px;"><small style="color:#6b7280;margin-left:8px;">Laisser vide si même journée</small></div>

          <div class="acdc-contract-label">Nombre de stagiaires</div>
          <div><input type="number" name="mission[nb_stagiaires]" value="<?php echo esc_attr( (int) $v('nb_stagiaires', 1) ); ?>" min="0" style="max-width:100px;"></div>

          <div class="acdc-contract-label">Heures de formation</div>
          <div><input type="text" id="mission_heures" name="mission[heures_par_stagiaire]" value="<?php echo esc_attr( $v('heures_par_stagiaire', '') ); ?>" placeholder="Ex : 7" style="max-width:100px;"></div>

          <div class="acdc-contract-label">Heures totales</div>
          <div><input type="text" name="mission[heures_total]" value="<?php echo esc_attr( $v('heures_total', '') ); ?>" placeholder="Auto-calculé si vide" style="max-width:100px;"><small style="color:#6b7280;margin-left:8px;">Si vide : heures de form. × nb stagiaires</small></div>

          <div class="acdc-contract-label">CA HT (€)</div>
          <div><input type="text" id="mission_ca_ht" name="mission[ca_ht]" value="<?php echo esc_attr( $v('ca_ht', '') ); ?>" placeholder="Ex : 1200" style="max-width:140px;"></div>

          <div class="acdc-contract-label">Tarif horaire HT (€/h)</div>
          <div>
            <input type="text" id="mission_tarif" name="mission[tarif_horaire]" value="<?php echo esc_attr( $v('tarif_horaire', '') ); ?>" placeholder="Calculé auto." style="max-width:140px;background:#f9fafb;" readonly>
            <small style="color:#6b7280;margin-left:8px;">CA HT ÷ Heures de formation</small>
          </div>

          <div class="acdc-contract-label">Type de public</div>
          <div>
            <select name="mission[type_public]">
              <?php foreach ( array( '', 'Indépendant', 'CFA', 'Salarié', 'Demandeur emploi', 'Autre' ) as $opt ) : ?>
                <option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $v('type_public'), $opt ); ?>><?php echo '' === $opt ? '— Sélectionner —' : esc_html( $opt ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="acdc-contract-label">Modalité</div>
          <div>
            <select name="mission[modalite]">
              <?php foreach ( array( '', 'Présentiel', 'Distanciel', 'Mixte' ) as $opt ) : ?>
                <option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $v('modalite'), $opt ); ?>><?php echo '' === $opt ? '— Sélectionner —' : esc_html( $opt ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="acdc-contract-label">Financement</div>
          <div>
            <select name="mission[financement]">
              <?php foreach ( array( '', 'Organisme de formation', 'Entreprise', 'OPCO', 'CPF', 'Autre' ) as $opt ) : ?>
                <option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $v('financement'), $opt ); ?>><?php echo '' === $opt ? '— Sélectionner —' : esc_html( $opt ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="acdc-contract-label">Statut</div>
          <div>
            <select name="mission[statut]">
              <option value="OK" <?php selected( $v('statut','OK'), 'OK' ); ?>>✅ OK</option>
              <option value="Incomplet" <?php selected( $v('statut'), 'Incomplet' ); ?>>⚠️ Incomplet</option>
            </select>
          </div>

          <div class="acdc-contract-label">Notes</div>
          <div><textarea name="mission[notes]" rows="3" style="width:100%;"><?php echo esc_textarea( $v('notes') ); ?></textarea></div>

        </div>

        <div class="acdc-actions-end" style="margin-top:24px;">
          <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $list_url ); ?>">Annuler</a>
          <button type="submit" class="acdc-button acdc-button-primary"><?php echo $is_edit ? 'Enregistrer les modifications' : 'Ajouter la prestation'; ?></button>
        </div>
      </form>
    </div>
    <script>
    (function(){
      var fCa     = document.getElementById('mission_ca_ht');
      var fHeures = document.getElementById('mission_heures');
      var fTarif  = document.getElementById('mission_tarif');
      if (!fCa || !fHeures || !fTarif) { return; }
      function calcTarif() {
        var ca = parseFloat(String(fCa.value).replace(',', '.').replace(/\s/g, ''));
        var h  = parseFloat(String(fHeures.value).replace(',', '.'));
        if (!isNaN(ca) && !isNaN(h) && h > 0) {
          fTarif.value = (ca / h).toFixed(2);
        } else {
          fTarif.value = '';
        }
      }
      fCa.addEventListener('input', calcTarif);
      fHeures.addEventListener('input', calcTarif);
      calcTarif();
    })();
    </script>
    <?php
  }



  private function render_front_continuous_improvement_tab() {
    $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
    if ( 'new' === $action ) {
      $this->render_front_continuous_improvement_create_page();
      return;
    }
    $this->render_front_continuous_improvement_list_page();
  }



  private function render_front_continuous_improvement_list_page() {
    $records = $this->get_continuous_improvement_records();
    $create_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard&tab=continuous_improvement&action=new' ) : $this->portal_page_url( array( 'tab' => 'continuous_improvement', 'action' => 'new' ) );
    ?>
    <?php
    $this->acdc_render_section_head( 'Améliorations continues' );
    $this->acdc_render_search_toolbar(
      array(
        'tab'           => 'continuous_improvement',
        'search_name'   => 'q',
        'search_value'  => '',
        'placeholder'   => 'Rechercher',
        'toolbar_class' => 'acdc-panel acdc-mb-18 acdc-toolbar-standard-panel',
        'trailing_html' => '<div class="acdc-toolbar-actions"><a href="' . esc_url( $create_url ) . '" class="acdc-button acdc-button-primary">Créer une amélioration continue</a></div>',
      )
    );
    ?>
    <div class="acdc-panel acdc-table-panel">
      <?php if ( empty( $records ) ) : ?>
        <?php $this->acdc_render_empty_state( 'Aucune donnée ne correspond aux critères demandés.', array( 'actions_html' => '<a href="' . esc_url( $create_url ) . '" class="acdc-button acdc-button-soft">Créer une amélioration continue</a>' ) ); ?>
      <?php else : ?>
        <div class="acdc-table-wrap">
          <table class="acdc-table"><thead><tr><th>INTITULÉ</th><th>SOURCE</th><th>TYPE</th><th>STATUT</th><th>CRÉÉ LE</th><th>ACTIONS</th></tr></thead><tbody>
          <?php foreach ( array_reverse( $records ) as $rec_key => $row ) :
            $row_id = $row['id'] ?? $row['source'] ?? '';
            $source_val = $row['source'] ?? '';
            $complaint_link_id = 0;
            if ( preg_match( '/^complaint_(\d+)$/', $source_val, $m ) ) {
              $complaint_link_id = (int) $m[1];
            }
            $source_html = $complaint_link_id > 0
              ? '<a href="' . esc_url( is_admin() ? add_query_arg( array( 'action' => 'view', 'complaint_id' => $complaint_link_id ), admin_url( 'admin.php?page=acdc-of-complaints' ) ) : $this->portal_page_url( array( 'tab' => 'complaints', 'cpl_action' => 'view', 'cpl_id' => $complaint_link_id ) ) ) . '" style="font-weight:600;color:var(--acdc-primary,#8b5b23);">&#8594; R&eacute;clamation #' . $complaint_link_id . '</a>'
              : esc_html( $source_val );
            $status_raw = $row['status'] ?? '';
            $status_map = array( 'planifie' => 'Planifi&eacute;', 'A traiter' => '&Agrave; traiter', 'À traiter' => '&Agrave; traiter', 'en_cours' => 'En cours', 'traite' => 'Trait&eacute;', 'Traite' => 'Trait&eacute;', 'Traité' => 'Trait&eacute;' );
            $status_html = isset( $status_map[ $status_raw ] ) ? $status_map[ $status_raw ] : esc_html( $status_raw );
            $is_done_row = ( $status_raw === 'Traité' || $status_raw === 'Traite' || $status_raw === 'traite' || false !== strpos( strtolower( $status_raw ), 'clos' ) );
            $back_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard&tab=continuous_improvement' ) : $this->portal_page_url( array( 'tab' => 'continuous_improvement' ) );
          ?>
            <tr>
              <td><?php echo esc_html( $row['title'] ?? '' ); ?></td>
              <td><?php echo $source_html; ?></td>
              <td><?php echo esc_html( $row['incident_type'] ?? $row['type'] ?? '' ); ?></td>
              <td><?php echo $status_html; ?></td>
              <td><?php echo esc_html( isset( $row['created_at'] ) ? wp_date( 'd/m/Y', strtotime( $row['created_at'] ) ) : '' ); ?></td>
              <td class="acdc-actions-cell-icons">
                <div style="display:flex;gap:6px;flex-wrap:nowrap;">
                  <?php if ( ! $is_done_row ) : ?>
                  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="acdc_mark_improvement_done">
                    <input type="hidden" name="improvement_id" value="<?php echo esc_attr( $row_id ); ?>">
                    <input type="hidden" name="_front_redirect" value="<?php echo esc_attr( $back_url ); ?>">
                    <?php wp_nonce_field( 'acdc_mark_improvement_done' ); ?>
                    <button type="submit" class="acdc-button acdc-button-soft" style="font-size:11px;height:30px;padding:0 10px;color:#15803d;border-color:#bbf7d0;" title="Marquer comme traité">&#10003; Trait&eacute;</button>
                  </form>
                  <?php endif; ?>
                  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Supprimer cette amélioration ?');">
                    <input type="hidden" name="action" value="acdc_delete_improvement">
                    <input type="hidden" name="improvement_id" value="<?php echo esc_attr( $row_id ); ?>">
                    <input type="hidden" name="_front_redirect" value="<?php echo esc_attr( $back_url ); ?>">
                    <?php wp_nonce_field( 'acdc_delete_improvement' ); ?>
                    <button type="submit" class="acdc-row-action-icon acdc-row-delete-link" data-acdc-iconized="1" title="Supprimer" aria-label="Supprimer">
                      <?php echo $this->render_inline_icon( 'trash-bin', 25 ); ?>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody></table>
        </div>
      <?php endif; ?>
    </div>
    <?php
  }



  private function render_front_continuous_improvement_create_page() {
    $back_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard&tab=continuous_improvement' ) : $this->portal_page_url( array( 'tab' => 'continuous_improvement' ) );
    /* Réclamations ouvertes pour le select de liaison */
    $open_complaints = array();
    if ( ! empty( $this->complaint_table ) ) {
      global $wpdb;
      $tbl_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->complaint_table ) );
      if ( $tbl_exists ) {
        $open_complaints = (array) $wpdb->get_results( "SELECT id, summary, severity, source FROM {$this->complaint_table} WHERE status IN ('ouverte','en_cours') ORDER BY opened_at DESC" );
      }
    }
    $source_labels   = $this->get_complaint_source_options();
    $severity_labels = $this->get_complaint_severity_options();
    /* Préremplissage depuis une réclamation liée (?from_complaint=ID) */
    $from_id   = isset( $_GET['from_complaint'] ) ? (int) $_GET['from_complaint'] : 0;
    $prefill_title  = '';
    $prefill_source = '';
    $prefill_find   = '';
    if ( $from_id > 0 && ! empty( $this->complaint_table ) ) {
      global $wpdb;
      $cpl = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->complaint_table} WHERE id = %d", $from_id ) );
      if ( $cpl ) {
        $sev_lbl = isset( $severity_labels[ $cpl->severity ] ) ? $severity_labels[ $cpl->severity ] : $cpl->severity;
        $prefill_title  = 'Réclamation #' . $cpl->id . ' — ' . mb_substr( $cpl->summary, 0, 60 );
        $prefill_source = 'complaint_' . $cpl->id;
        $prefill_find   = '[' . $sev_lbl . '] ' . $cpl->summary . ( ! empty( $cpl->corrective_action ) ? "\n\nMesure corrective : " . $cpl->corrective_action : '' );
      }
    }
    ?>
    <?php $this->acdc_render_section_head( 'Créer une amélioration continue' ); ?>
    <?php if ( ! empty( $open_complaints ) ) : ?>
    <div style="background:#fef6e4;border:1px solid #f0e6dc;border-radius:10px;padding:14px 18px;margin-bottom:16px;">
      <p style="font-size:13px;font-weight:600;color:#0f2c52;margin:0 0 10px;">Lier cette action &agrave; une r&eacute;clamation ouverte</p>
      <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <?php foreach ( $open_complaints as $cpl ) :
          $sev_lbl = isset( $severity_labels[ $cpl->severity ] ) ? $severity_labels[ $cpl->severity ] : $cpl->severity;
          $src_lbl = isset( $source_labels[ $cpl->source ] ) ? $source_labels[ $cpl->source ] : $cpl->source;
          $is_active = $from_id === (int) $cpl->id;
          $url = $this->portal_page_url( array( 'tab' => 'continuous_improvement', 'action' => 'new', 'from_complaint' => $cpl->id ) );
        ?>
          <a href="<?php echo esc_url( $url ); ?>" class="acdc-button <?php echo $is_active ? 'acdc-button-primary' : 'acdc-button-soft'; ?>" style="font-size:12px;">
            #<?php echo (int) $cpl->id; ?> &mdash; <?php echo esc_html( mb_substr( $cpl->summary, 0, 40 ) . ( mb_strlen( $cpl->summary ) > 40 ? '…' : '' ) ); ?>
            <span style="opacity:.7;margin-left:4px;">(<?php echo esc_html( $sev_lbl ); ?>)</span>
          </a>
        <?php endforeach; ?>
        <a href="<?php echo esc_url( $this->portal_page_url( array( 'tab' => 'continuous_improvement', 'action' => 'new' ) ) ); ?>" class="acdc-button acdc-button-soft" style="font-size:12px;">Aucune r&eacute;clamation li&eacute;e</a>
      </div>
    </div>
    <?php endif; ?>
    <div class="acdc-panel">
      <form class="acdc-form acdc-contract-builder-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="acdc_save_continuous_improvement">
        <?php if ( $from_id > 0 ) : ?><input type="hidden" name="improvement[linked_complaint_id]" value="<?php echo $from_id; ?>"><?php endif; ?>
        <?php wp_nonce_field( 'acdc_front_secure_action' ); ?>
        <div class="acdc-contract-grid" style="grid-template-columns:340px minmax(0,1fr);align-items:start;">
          <div class="acdc-contract-label">Intitul&eacute; <span class="acdc-required">*</span></div>
          <div><input type="text" name="improvement[title]" placeholder="Intitulé" value="<?php echo esc_attr( $prefill_title ); ?>" required></div>

          <div class="acdc-contract-label">Source <span class="acdc-required">*</span></div>
          <div><input type="text" name="improvement[source]" placeholder="Source (ex: complaint_3, enquête, audit)" value="<?php echo esc_attr( $prefill_source ); ?>" required></div>

          <div class="acdc-contract-label">Constat <span class="acdc-required">*</span></div>
          <div><textarea name="improvement[finding]" rows="4" placeholder="Décrivez le problème constaté" required><?php echo esc_textarea( $prefill_find ); ?></textarea></div>

          <div class="acdc-contract-label">Action corrective imm&eacute;diate</div>
          <div><textarea name="improvement[curative_action]" rows="3" placeholder="Que faites-vous tout de suite ?"></textarea></div>

          <div class="acdc-contract-label">Date de r&eacute;alisation imm&eacute;diate</div>
          <div><input type="date" name="improvement[curative_date]"></div>

          <div class="acdc-contract-label">Action d&eacute;finitive &agrave; mettre en place</div>
          <div><textarea name="improvement[definitive_action]" rows="3" placeholder="Solution durable pour que ça ne se reproduise pas"></textarea></div>

          <div class="acdc-contract-label">Date de r&eacute;alisation d&eacute;finitive</div>
          <div><input type="date" name="improvement[definitive_date]"></div>

          <div class="acdc-contract-label">Type d'incident <span class="acdc-required">*</span></div>
          <div>
            <select name="improvement[incident_type]" required>
              <option value="">Choisir</option>
              <option value="Réclamation" <?php selected( $from_id > 0, true ); ?>>R&eacute;clamation</option>
              <option value="Difficulté">Difficult&eacute;</option>
              <option value="Aléas">Al&eacute;as</option>
              <option value="Affichage info dans l'oeil">Autre</option>
            </select>
          </div>

          <div class="acdc-contract-label">Statut</div>
          <div>
            <select name="improvement[status]">
              <option value="À traiter">À traiter</option>
              <option value="En cours de traitement">En cours de traitement</option>
              <option value="Traité">Trait&eacute;</option>
            </select>
          </div>
        </div>
        <div class="acdc-actions-end" style="margin-top:24px;">
          <a href="<?php echo esc_url( $back_url ); ?>" class="acdc-button acdc-button-soft">Annuler</a>
          <button type="submit" name="submit_mode" value="save" class="acdc-button acdc-button-primary">Créer cette amélioration</button>
        </div>
      </form>
    </div>
    <?php
  }



  private function render_front_learner_deadlines_tab() {
    $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
    if ( 'new' === $action ) {
      $this->render_front_learner_deadline_create_page();
      return;
    }
    $this->render_front_learner_deadline_list_page();
  }



  private function render_front_learner_deadline_list_page() {
    $records = $this->get_learner_deadline_records();
    $create_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard&tab=learner_deadlines&action=new' ) : $this->portal_page_url( array( 'tab' => 'learner_deadlines', 'action' => 'new' ) );
    ?>
    <section class="acdc-section-head"><div><h2>Échéances apprenants</h2></div></section>
    <div class="acdc-panel acdc-mb-18">
      <form method="get" action="">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
        <input type="hidden" name="tab" value="learner_deadlines">
        <div class="acdc-inline-wrap" style="justify-content:space-between;align-items:center;gap:16px;">
          <input type="search" name="q" value="" placeholder="Rechercher" style="max-width:420px;">
          <div class="acdc-inline-wrap" style="gap:10px;">
            <a href="<?php echo esc_url( $create_url ); ?>" class="acdc-button acdc-button-primary">Créer une échéance</a>
          </div>
        </div>
      </form>
    </div>
    <div class="acdc-panel acdc-table-panel">
      <div style="display:flex;justify-content:flex-end;align-items:center;gap:12px;padding:8px 0 18px;">
        <button type="button" class="acdc-filter-toggle-icons-only" aria-label="Filtres"><?php echo $this->render_inline_icon( 'filter', 18 ); ?></button>
        <button type="button" class="acdc-filter-toggle-icons-only" aria-label="Options"><?php echo $this->render_inline_icon( 'chevron-down', 16 ); ?></button>
      </div>
      <?php if ( empty( $records ) ) : ?>
        <div style="padding:44px 20px;text-align:center;color:#1E4777;">
          <div style="font-size:42px;line-height:1;margin-bottom:10px;">⊞</div>
          <div style="margin-bottom:16px;">Impossible de charger Échéances apprenants !</div>
          <button type="button" class="acdc-button acdc-button-soft" onclick="window.location.reload();">Recharger</button>
        </div>
      <?php else : ?>
        <div class="acdc-table-wrap">
          <table class="acdc-table"><thead><tr><th>INTITULÉ</th><th>DATE D'ÉCHÉANCE</th><th>TYPE</th><th>RAPPEL</th></tr></thead><tbody>
          <?php foreach ( $records as $row ) : ?>
            <tr>
              <td><?php echo esc_html( $row['title'] ?? '' ); ?></td>
              <td><?php echo esc_html( $row['due_at'] ?? '' ); ?></td>
              <td><?php echo esc_html( $row['type'] ?? '' ); ?></td>
              <td><?php echo ! empty( $row['email_reminder'] ) ? 'Oui' : 'Non'; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody></table>
        </div>
      <?php endif; ?>
    </div>
    <?php
  }



  private function render_front_learner_deadline_create_page() {
    $back_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard&tab=learner_deadlines' ) : $this->portal_page_url( array( 'tab' => 'learner_deadlines' ) );
    $learners = $this->get_learners();
    $groups = $this->get_groups();
    ?>
    <section class="acdc-section-head"><div><h2>Créer une échéance apprenant</h2></div></section>
    <div class="acdc-panel">
      <form class="acdc-form acdc-contract-builder-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="acdc_save_learner_deadline">
        <?php wp_nonce_field( 'acdc_front_secure_action' ); ?>
        <div class="acdc-contract-grid" style="grid-template-columns:260px minmax(0,1fr) 140px;align-items:start;">
          <div class="acdc-contract-label">Intitulé <span class="acdc-required">*</span></div>
          <div><input type="text" name="deadline[title]" placeholder="Intitulé" required></div>
          <div></div>

          <div class="acdc-contract-label">Date d'échéance <span class="acdc-required">*</span></div>
          <div>
            <input type="text" name="deadline[due_at]" placeholder="jj/mm/aaaa --:--" required>
            <p class="acdc-help">Les échéances peuvent être définies de l'entrée en formation jusqu'à la clôture de l'action de formation (clôturée/interrompue/annulée).</p>
          </div>
          <div class="acdc-help" style="padding-top:12px;">Europe/Paris</div>

          <div class="acdc-contract-label">Type <span class="acdc-required">*</span></div>
          <div>
            <select name="deadline[type]" id="acdc-deadline-type" required>
              <option value="">Choisir une option</option>
              <option value="Individuelle">Individuelle</option>
              <option value="Groupe">Groupe</option>
            </select>
            <p class="acdc-help">Une échéance définie pour un groupe s'appliquera automatiquement à tous ses apprenants, y compris en cas de modification ou de suppression.</p>
          </div>
          <div></div>

          <div class="acdc-contract-label">Compléments d'information</div>
          <div><textarea name="deadline[notes]" rows="4" placeholder="Compléments d'information"></textarea><p class="acdc-help">Ces informations seront incluses dans l'e-mail de rappel envoyé à l'apprenant.</p></div>
          <div></div>

          <div class="acdc-deadline-target-row acdc-deadline-target-learner" style="display:none;">
            <div class="acdc-contract-label">Apprenant <span class="acdc-required">*</span></div>
            <div>
              <div class="acdc-pre-meeting-search-wrap">
                <input type="search" id="acdc-deadline-learner-search" class="acdc-pre-meeting-search-input" value="" placeholder="Rechercher">
                <select name="deadline[learner_id]" id="acdc-deadline-learner">
                  <option value="">Cliquez pour choisir</option>
                  <?php foreach ( $learners as $learner ) : ?>
                    <?php $learner_label = trim( (string) $learner->first_name . ' ' . (string) $learner->usage_last_name ); ?>
                    <option value="<?php echo esc_attr( (int) $learner->id ); ?>"><?php echo esc_html( $learner_label ); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div></div>
          </div>

          <div class="acdc-deadline-target-row acdc-deadline-target-group" style="display:none;">
            <div class="acdc-contract-label">Groupe <span class="acdc-required">*</span></div>
            <div>
              <div class="acdc-pre-meeting-search-wrap">
                <input type="search" id="acdc-deadline-group-search" class="acdc-pre-meeting-search-input" value="" placeholder="Rechercher">
                <select name="deadline[group_id]" id="acdc-deadline-group">
                  <option value="">Cliquez pour choisir</option>
                  <?php foreach ( $groups as $group ) : ?>
                    <?php
                    $group_bits = array();
                    if ( ! empty( $group->formation_title ) ) {
                      $group_bits[] = $group->formation_title;
                    } elseif ( ! empty( $group->name ) ) {
                      $group_bits[] = $group->name;
                    }
                    $date_range = trim( implode( ' et ', array_filter( array( $group->start_date ?? '', $group->end_date ?? '' ) ) ) );
                    if ( '' !== $date_range ) {
                      $group_bits[] = $date_range;
                    }
                    $group_label = implode( ' - ', array_filter( $group_bits ) );
                    ?>
                    <option value="<?php echo esc_attr( (int) $group->id ); ?>"><?php echo esc_html( $group_label ); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div></div>
          </div>

          <div class="acdc-contract-label">Rappel (E-mail)</div>
          <div><label class="acdc-switch"><input type="checkbox" name="deadline[email_reminder]" value="1"><span class="acdc-switch-slider"></span></label></div>
          <div></div>
        </div>
        <div class="acdc-actions-end" style="margin-top:24px;">
          <a href="<?php echo esc_url( $back_url ); ?>" class="acdc-button acdc-button-soft">Annuler</a>
          <button type="submit" name="submit_mode" value="add_new" class="acdc-button acdc-button-soft">Créer &amp; ajouter un autre</button>
          <button type="submit" name="submit_mode" value="save" class="acdc-button acdc-button-primary">Créer une échéance</button>
        </div>
      </form>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
      var typeField = document.getElementById('acdc-deadline-type');
      var learnerRow = document.querySelector('.acdc-deadline-target-learner');
      var groupRow = document.querySelector('.acdc-deadline-target-group');
      var learnerField = document.getElementById('acdc-deadline-learner');
      var groupField = document.getElementById('acdc-deadline-group');
      var learnerSearch = document.getElementById('acdc-deadline-learner-search');
      var groupSearch = document.getElementById('acdc-deadline-group-search');
      if (!typeField || !learnerRow || !groupRow) { return; }
      var syncDeadlineType = function(){
        var value = typeField.value;
        if (value === 'Individuelle') {
          learnerRow.style.display = 'contents';
          groupRow.style.display = 'none';
          if (learnerField) { learnerField.required = true; }
          if (groupField) { groupField.required = false; groupField.value = ''; }
          if (groupSearch) { groupSearch.value = ''; }
        } else if (value === 'Groupe') {
          learnerRow.style.display = 'none';
          groupRow.style.display = 'contents';
          if (groupField) { groupField.required = true; }
          if (learnerField) { learnerField.required = false; learnerField.value = ''; }
          if (learnerSearch) { learnerSearch.value = ''; }
        } else {
          learnerRow.style.display = 'none';
          groupRow.style.display = 'none';
          if (learnerField) { learnerField.required = false; learnerField.value = ''; }
          if (groupField) { groupField.required = false; groupField.value = ''; }
          if (learnerSearch) { learnerSearch.value = ''; }
          if (groupSearch) { groupSearch.value = ''; }
        }
      };
      var filterSelectOptions = function(searchField, selectField){
        if (!searchField || !selectField) { return; }
        var term = (searchField.value || '').toLowerCase();
        Array.prototype.forEach.call(selectField.options, function(option, index){
          if (index === 0) {
            option.hidden = false;
            return;
          }
          option.hidden = term && option.text.toLowerCase().indexOf(term) === -1;
        });
      };
      if (learnerSearch && learnerField) {
        learnerSearch.addEventListener('input', function(){ filterSelectOptions(learnerSearch, learnerField); });
      }
      if (groupSearch && groupField) {
        groupSearch.addEventListener('input', function(){ filterSelectOptions(groupSearch, groupField); });
      }
      typeField.addEventListener('change', syncDeadlineType);
      syncDeadlineType();
    });
    </script>
    <?php
  }



  private function render_front_ancillary_services_tab() {
    $action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
    if ( 'new' === $action ) {
      $this->render_front_ancillary_service_create_page();
      return;
    }
    $this->render_front_ancillary_service_list_page();
  }



  private function render_front_ancillary_service_list_page() {
    $records = $this->get_ancillary_service_records();
    $create_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard&tab=ancillary_services&action=new' ) : $this->portal_page_url( array( 'tab' => 'ancillary_services', 'action' => 'new' ) );
    ?>
    <section class="acdc-section-head"><div><h2>Prestations annexes</h2></div></section>
    <div class="acdc-panel acdc-mb-18">
      <form method="get" action="">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
        <input type="hidden" name="tab" value="ancillary_services">
        <div class="acdc-inline-wrap" style="justify-content:space-between;align-items:center;gap:16px;">
          <input type="search" name="q" value="" placeholder="Rechercher" style="max-width:420px;">
          <div class="acdc-inline-wrap" style="gap:10px;">
            <a href="<?php echo esc_url( $create_url ); ?>" class="acdc-button acdc-button-primary">Créer une prestation annexe</a>
          </div>
        </div>
      </form>
    </div>
    <div class="acdc-panel acdc-table-panel">
      <div style="display:flex;justify-content:flex-end;align-items:center;gap:12px;padding:8px 0 18px;">
        <button type="button" class="acdc-filter-toggle-icons-only" aria-label="Filtres"><?php echo $this->render_inline_icon( 'filter', 18 ); ?></button>
        <button type="button" class="acdc-filter-toggle-icons-only" aria-label="Options"><?php echo $this->render_inline_icon( 'chevron-down', 16 ); ?></button>
      </div>
      <?php if ( empty( $records ) ) : ?>
        <div style="padding:44px 20px;text-align:center;color:#1E4777;">
          <div style="font-size:42px;line-height:1;margin-bottom:10px;">⊞</div>
          <div style="margin-bottom:16px;">Aucune donnée ne correspond aux critères demandés.</div>
          <a href="<?php echo esc_url( $create_url ); ?>" class="acdc-button acdc-button-soft">Créer une prestation annexe</a>
        </div>
      <?php else : ?>
        <div class="acdc-table-wrap">
          <table class="acdc-table"><thead><tr><th>INTITULÉ</th><th>COMMANDITAIRE</th><th>DÉBUT</th><th>FIN</th><th>RÉALISÉ PAR</th><th>TARIF (€ HT)</th></tr></thead><tbody>
          <?php foreach ( array_reverse( $records ) as $row ) : ?>
            <tr>
              <td><?php echo esc_html( $row['title'] ?? '' ); ?></td>
              <td><?php echo esc_html( $row['commanditaire_type'] ?? '' ); ?></td>
              <td><?php echo esc_html( $row['start_at'] ?? '' ); ?></td>
              <td><?php echo esc_html( $row['end_at'] ?? '' ); ?></td>
              <td><?php echo esc_html( $row['trainer_name'] ?? '' ); ?></td>
              <td><?php echo esc_html( $row['price_ht'] ?? '' ); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody></table>
        </div>
      <?php endif; ?>
    </div>
    <?php
  }



  private function render_front_ancillary_service_create_page() {
    $back_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard&tab=ancillary_services' ) : $this->portal_page_url( array( 'tab' => 'ancillary_services' ) );
    $trainers = $this->get_trainers();
    ?>
    <section class="acdc-section-head"><div><h2>Créer une prestation annexe</h2></div></section>
    <div class="acdc-panel">
      <form class="acdc-form acdc-contract-builder-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="acdc_save_ancillary_service">
        <?php wp_nonce_field( 'acdc_front_secure_action' ); ?>
        <div class="acdc-contract-grid" style="grid-template-columns:340px minmax(0,1fr) 110px;align-items:start;">
          <div class="acdc-contract-label">Intitulé <span class="acdc-required">*</span></div>
          <div><input type="text" name="ancillary_service[title]" placeholder="Intitulé" required></div><div></div>

          <div class="acdc-contract-label">Description</div>
          <div><textarea name="ancillary_service[description]" rows="4" placeholder="Description"></textarea></div><div></div>

          <div class="acdc-contract-label">Tarif (€ HT)</div>
          <div><input type="text" name="ancillary_service[price_ht]" placeholder="Tarif (€ HT)"></div><div></div>

          <div class="acdc-contract-label">Commanditaire <span class="acdc-required">*</span></div>
          <div><select name="ancillary_service[commanditaire_type]" required><option value="">Choisir une option</option><option value="Entreprise">Entreprise</option><option value="Particulier">Particulier</option></select><p class="acdc-help">Une fois un devis ou une facture généré, le commanditaire de la prestation ne sera plus éditable.</p></div><div></div>

          <div class="acdc-contract-label">Définir des dates</div>
          <div><label class="acdc-switch"><input type="checkbox" name="ancillary_service[use_dates]" value="1" checked><span class="acdc-switch-slider"></span></label></div><div></div>

          <div class="acdc-contract-label">Début <span class="acdc-required">*</span></div>
          <div><input type="text" name="ancillary_service[start_at]" placeholder="jj/mm/aaaa --:--" required><p class="acdc-help">Un rappel de facturation s'affichera automatiquement à cette date.</p></div><div style="padding-top:11px;color:#1E4777;">Europe/Paris</div>

          <div class="acdc-contract-label">Fin <span class="acdc-required">*</span></div>
          <div><input type="text" name="ancillary_service[end_at]" placeholder="jj/mm/aaaa --:--" required></div><div style="padding-top:11px;color:#1E4777;">Europe/Paris</div>

          <div class="acdc-contract-label">Afficher dans le calendrier</div>
          <div><label class="acdc-switch"><input type="checkbox" name="ancillary_service[show_in_calendar]" value="1" checked><span class="acdc-switch-slider"></span></label></div><div></div>

          <div class="acdc-contract-label">Réalisé par</div>
          <div><select name="ancillary_service[trainer_id]"><option value="">—</option><?php foreach ( $trainers as $trainer ) : $trainer_name = trim( (string) $trainer->first_name . ' ' . (string) $trainer->last_name ); ?><option value="<?php echo esc_attr( (int) $trainer->id ); ?>"><?php echo esc_html( $trainer_name ); ?></option><?php endforeach; ?></select></div><div></div>
        </div>
        <div class="acdc-actions-end" style="margin-top:24px;">
          <a href="<?php echo esc_url( $back_url ); ?>" class="acdc-button acdc-button-soft">Annuler</a>
          <button type="submit" name="submit_mode" value="add_new" class="acdc-button acdc-button-soft">Créer &amp; ajouter un autre</button>
          <button type="submit" name="submit_mode" value="save" class="acdc-button acdc-button-primary">Créer une prestation annexe</button>
        </div>
      </form>
    </div>
    <?php
  }



  /* ACDC 3.22.18 - H4 : Registre des reclamations - tab front office */
  public function render_front_complaints_tab() {
    $action       = isset( $_GET['cpl_action'] ) ? sanitize_key( wp_unslash( $_GET['cpl_action'] ) ) : 'list';
    $complaint_id = isset( $_GET['cpl_id'] ) ? (int) $_GET['cpl_id'] : 0;
    /* Filtre alerte dashboard : unack */
    $filter_unack = isset( $_GET['cpl_filter'] ) && 'unack' === sanitize_key( wp_unslash( $_GET['cpl_filter'] ) );
    if ( $filter_unack && 'list' === $action ) {
      $action = 'list_unack';
    }
    if ( 'create' === $action ) {
      $this->render_complaint_form_front( null );
    } elseif ( 'edit' === $action && $complaint_id > 0 ) {
      $c = $this->get_complaint_by_id( $complaint_id );
      $this->render_complaint_form_front( $c );
    } elseif ( 'view' === $action && $complaint_id > 0 ) {
      $c = $this->get_complaint_by_id( $complaint_id );
      $this->render_complaint_detail_front( $c );
    } elseif ( 'list_unack' === $action ) {
      $this->render_complaints_list_front( array( 'unack_only' => true ) );
    } else {
      $this->render_complaints_list_front( array() );
    }
  }

  private function render_complaints_list_front( $extra_filters = array() ) {
    global $wpdb;
    $filters = array();
    if ( ! empty( $_GET['cpl_status'] ) && 'all' !== $_GET['cpl_status'] )    { $filters['status']   = sanitize_key( wp_unslash( $_GET['cpl_status'] ) ); }
    if ( ! empty( $_GET['cpl_severity'] ) && 'all' !== $_GET['cpl_severity'] ) { $filters['severity']  = sanitize_key( wp_unslash( $_GET['cpl_severity'] ) ); }
    if ( ! empty( $_GET['cpl_from'] ) ) { $filters['date_from'] = sanitize_text_field( wp_unslash( $_GET['cpl_from'] ) ); }
    if ( ! empty( $_GET['cpl_to'] ) )   { $filters['date_to']   = sanitize_text_field( wp_unslash( $_GET['cpl_to'] ) ); }
    $unack_only = ! empty( $extra_filters['unack_only'] );
    $complaints = $this->get_complaints( $filters );
    if ( $unack_only ) {
      $complaints = array_values( array_filter( $complaints, function( $c ) { return empty( $c->acknowledged_at ); } ) );
    }
    $nb_open         = $this->count_open_complaints();
    $nb_unack        = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->complaint_table . ' WHERE acknowledged_at IS NULL' );
    $type_labels     = $this->get_complaint_type_options();
    $source_labels   = $this->get_complaint_source_options();
    $severity_labels = $this->get_complaint_severity_options();
    $status_labels   = $this->get_complaint_status_options();
    $base = is_admin() ? admin_url( 'admin.php?page=acdc-of-complaints' ) : $this->portal_page_url( array( 'tab' => 'complaints' ) );
    $create_url = is_admin() ? add_query_arg( 'action', 'create', $base ) : $this->portal_page_url( array( 'tab' => 'complaints', 'cpl_action' => 'create' ) );
    if ( isset( $_GET['cpl_deleted'] ) || isset( $_GET['deleted'] ) ) { echo '<div class="acdc-notice acdc-notice-success"><p>R&eacute;clamation supprim&eacute;e.</p></div>'; }
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Registre des r&eacute;clamations</h2>
        <p>Suivi des r&eacute;clamations, incidents et aléas &mdash; indicateurs 31 et 32 Qualiopi.</p>
      </div>
      <div class="acdc-inline-wrap">
        <?php if ( $nb_unack > 0 ) : ?><span class="acdc-badge is-error" style="align-self:center;"><?php echo $nb_unack; ?> sans accus&eacute; r&eacute;ception</span><?php endif; ?>
        <?php if ( $nb_open > 0 ) : ?><span class="acdc-badge is-warning" style="align-self:center;"><?php echo $nb_open; ?> ouverte<?php echo $nb_open > 1 ? 's' : ''; ?></span><?php endif; ?>
        <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'acdc_export_complaints_pdf' ), $filters ), admin_url( 'admin-post.php' ) ), 'acdc_export_complaints_pdf' ) ); ?>" target="_blank">&#8595; Export PDF audit</a>
        <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $create_url ); ?>">+ Nouvelle r&eacute;clamation</a>
      </div>
    </section>

    <?php if ( $unack_only ) : ?>
    <div style="margin-bottom:12px;padding:10px 16px;background:#fff0f0;border-left:4px solid #c0392b;border-radius:6px;font-size:13px;">
      <strong>Filtre actif :</strong> r&eacute;clamations sans accus&eacute; de r&eacute;ception uniquement &mdash; <a href="<?php echo esc_url( $base ); ?>">Voir toutes</a>
    </div>
    <?php endif; ?>

    <form method="get" action="<?php echo esc_url( is_admin() ? admin_url( 'admin.php' ) : $this->portal_page_url( array() ) ); ?>" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:14px;">
      <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-complaints"><?php else : ?><input type="hidden" name="tab" value="complaints"><?php endif; ?>
      <select name="<?php echo is_admin() ? 'status' : 'cpl_status'; ?>" style="height:var(--acdc-field-h,40px);border-radius:var(--acdc-radius,10px);border:1px solid var(--acdc-border,#e2e8f0);padding:0 12px;font-size:13px;">
        <option value="all">Tous les statuts</option>
        <?php foreach ( $status_labels as $k => $v ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( isset( $filters['status'] ) && $filters['status'] === $k ); ?>><?php echo esc_html( $v ); ?></option><?php endforeach; ?>
      </select>
      <select name="<?php echo is_admin() ? 'severity' : 'cpl_severity'; ?>" style="height:var(--acdc-field-h,40px);border-radius:var(--acdc-radius,10px);border:1px solid var(--acdc-border,#e2e8f0);padding:0 12px;font-size:13px;">
        <option value="all">Toutes gravit&eacute;s</option>
        <?php foreach ( $severity_labels as $k => $v ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( isset( $filters['severity'] ) && $filters['severity'] === $k ); ?>><?php echo esc_html( $v ); ?></option><?php endforeach; ?>
      </select>
      <label style="font-size:13px;display:flex;align-items:center;gap:6px;">Du <input type="date" name="<?php echo is_admin() ? 'date_from' : 'cpl_from'; ?>" value="<?php echo esc_attr( isset( $filters['date_from'] ) ? $filters['date_from'] : '' ); ?>" style="height:var(--acdc-field-h,40px);border-radius:var(--acdc-radius,10px);border:1px solid var(--acdc-border,#e2e8f0);padding:0 10px;font-size:13px;"></label>
      <label style="font-size:13px;display:flex;align-items:center;gap:6px;">Au <input type="date" name="<?php echo is_admin() ? 'date_to' : 'cpl_to'; ?>" value="<?php echo esc_attr( isset( $filters['date_to'] ) ? $filters['date_to'] : '' ); ?>" style="height:var(--acdc-field-h,40px);border-radius:var(--acdc-radius,10px);border:1px solid var(--acdc-border,#e2e8f0);padding:0 10px;font-size:13px;"></label>
      <button type="submit" class="acdc-button acdc-button-soft">Filtrer</button>
      <a href="<?php echo esc_url( $base ); ?>" class="acdc-button acdc-button-soft">R&eacute;initialiser</a>
    </form>

    <style>
    .acdc-table-complaints{table-layout:fixed;width:100%;min-width:1200px;}
    .acdc-table-complaints th,.acdc-table-complaints td{box-sizing:border-box;vertical-align:middle;overflow:hidden;text-overflow:ellipsis;padding:10px 12px;white-space:nowrap;}
    .acdc-table-complaints th:nth-child(1),.acdc-table-complaints td:nth-child(1){width:60px;}
    .acdc-table-complaints th:nth-child(2),.acdc-table-complaints td:nth-child(2){width:100px;}
    .acdc-table-complaints th:nth-child(3),.acdc-table-complaints td:nth-child(3){width:130px;}
    .acdc-table-complaints th:nth-child(4),.acdc-table-complaints td:nth-child(4){width:120px;}
    .acdc-table-complaints th:nth-child(5),.acdc-table-complaints td:nth-child(5){width:110px;}
    .acdc-table-complaints th:nth-child(6),.acdc-table-complaints td:nth-child(6){width:auto;white-space:normal;}
    .acdc-table-complaints th:nth-child(7),.acdc-table-complaints td:nth-child(7){width:110px;}
    .acdc-table-complaints th:nth-child(8),.acdc-table-complaints td:nth-child(8){width:160px;}
    .acdc-table-complaints th:nth-child(9),.acdc-table-complaints td:nth-child(9){width:140px;}
    .acdc-table-complaints,.acdc-table-complaints tbody,.acdc-table-complaints tr,.acdc-table-complaints td{overflow:visible!important;}
    </style>

    <div class="acdc-table-wrap">
      <table class="acdc-table acdc-table-complaints" data-acdc-table-id="complaints-list">
        <thead>
          <tr>
            <th>#</th>
            <th>Date</th>
            <th>Source</th>
            <th>Type</th>
            <th>Gravit&eacute;</th>
            <th>R&eacute;sum&eacute;</th>
            <th>Statut</th>
            <th>Accus&eacute; r&eacute;ception</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if ( empty( $complaints ) ) : ?>
          <tr><td colspan="9" style="text-align:center;padding:24px;color:#4b5d76;font-style:italic;">Aucune r&eacute;clamation<?php echo $unack_only ? ' sans accus&eacute; de r&eacute;ception' : ' enregistr&eacute;e'; ?>.</td></tr>
        <?php else : ?>
          <?php foreach ( $complaints as $c ) :
            $view_url   = is_admin() ? add_query_arg( array( 'action' => 'view', 'complaint_id' => $c->id ), $base ) : $this->portal_page_url( array( 'tab' => 'complaints', 'cpl_action' => 'view', 'cpl_id' => $c->id ) );
            $edit_url   = is_admin() ? add_query_arg( array( 'action' => 'edit', 'complaint_id' => $c->id ), $base ) : $this->portal_page_url( array( 'tab' => 'complaints', 'cpl_action' => 'edit', 'cpl_id' => $c->id ) );
            $delete_url = is_admin()
              ? wp_nonce_url( admin_url( 'admin-post.php?action=acdc_delete_complaint&complaint_id=' . (int) $c->id . '&_front_redirect=' . rawurlencode( $base . ( is_admin() ? '&deleted=1' : ( strpos( $base, '?' ) !== false ? '&' : '?' ) . 'cpl_deleted=1' ) ) ), 'acdc_delete_complaint' )
              : wp_nonce_url( $this->portal_page_url( array( 'trf_action' => 'delete_complaint', 'complaint_id' => (int) $c->id ) ), 'acdc_delete_complaint_' . (int) $c->id );
            $sev_class  = 'grave' === $c->severity ? 'is-error' : ( 'moderee' === $c->severity ? 'is-warning' : 'is-success' );
            $sta_class  = 'ouverte' === $c->status ? 'is-info' : ( 'cloturee' === $c->status ? 'is-neutral' : 'is-accent' );
            $ack_ok     = ! empty( $c->acknowledged_at );
          ?>
          <tr>
            <td><a href="<?php echo esc_url( $view_url ); ?>" style="font-weight:600;color:var(--acdc-primary,#8b5b23);">#<?php echo (int) $c->id; ?></a></td>
            <td><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $c->opened_at ) ) ); ?></td>
            <td><?php echo esc_html( isset( $source_labels[ $c->source ] ) ? $source_labels[ $c->source ] : $c->source ); ?></td>
            <td><?php echo esc_html( isset( $type_labels[ $c->type ] ) ? $type_labels[ $c->type ] : $c->type ); ?></td>
            <td><span class="acdc-badge <?php echo $sev_class; ?>"><?php echo esc_html( isset( $severity_labels[ $c->severity ] ) ? $severity_labels[ $c->severity ] : $c->severity ); ?></span></td>
            <td><?php echo esc_html( mb_substr( $c->summary, 0, 100 ) ); ?><?php echo mb_strlen( $c->summary ) > 100 ? '&hellip;' : ''; ?></td>
            <td><span class="acdc-badge <?php echo $sta_class; ?>"><?php echo esc_html( isset( $status_labels[ $c->status ] ) ? $status_labels[ $c->status ] : $c->status ); ?></span></td>
            <td><?php echo $ack_ok ? '<span class="acdc-badge is-success">' . esc_html( wp_date( 'd/m/Y', strtotime( $c->acknowledged_at ) ) ) . '</span>' : '<span class="acdc-badge is-error">Manquant</span>'; ?></td>
            <td class="acdc-actions-cell-icons">
              <div class="acdc-groups-actions-inline">
                <a href="<?php echo esc_url( $view_url ); ?>"
                   class="acdc-row-action-icon acdc-row-view-link"
                   data-acdc-iconized="1"
                   title="Voir la r&eacute;clamation"
                   aria-label="Voir">
                  <?php echo $this->render_inline_icon( 'view', 25 ); ?>
                  <span class="acdc-action-hub-sr screen-reader-text">Voir</span>
                </a>
                <a href="<?php echo esc_url( $edit_url ); ?>"
                   class="acdc-row-action-icon acdc-row-edit-link"
                   data-acdc-iconized="1"
                   title="Modifier la r&eacute;clamation"
                   aria-label="Modifier">
                  <?php echo $this->render_inline_icon( 'edit-pencil', 25 ); ?>
                  <span class="acdc-action-hub-sr screen-reader-text">Modifier</span>
                </a>
                <a href="<?php echo esc_url( $delete_url ); ?>"
                   class="acdc-row-action-icon acdc-row-delete-link"
                   data-acdc-iconized="1"
                   title="Supprimer la r&eacute;clamation"
                   aria-label="Supprimer"
                   onclick="return confirm('Supprimer définitivement cette réclamation ?');">
                  <?php echo $this->render_inline_icon( 'trash-bin', 25 ); ?>
                  <span class="acdc-action-hub-sr screen-reader-text">Supprimer</span>
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php
  }

  private function render_complaint_form_front( $c ) {
    $is_edit = ! empty( $c );
    $base    = $this->portal_page_url( array( 'tab' => 'complaints' ) );
    $type_labels     = $this->get_complaint_type_options();
    $source_labels   = $this->get_complaint_source_options();
    $severity_labels = $this->get_complaint_severity_options();
    $status_labels   = $this->get_complaint_status_options();
    $title = $is_edit ? 'Modifier une r&eacute;clamation' : 'Cr&eacute;er une r&eacute;clamation';
    if ( isset( $_GET['cpl_updated'] ) ) { echo '<div class="acdc-notice acdc-notice-success"><p>R&eacute;clamation enregistr&eacute;e.</p></div>'; }
    ?>
    <section class="acdc-section-head"><div><h2><?php echo $title; ?></h2></div></section>
    <div class="acdc-panel">
      <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="acdc_save_complaint">
        <input type="hidden" name="complaint_id" value="<?php echo $is_edit ? (int) $c->id : 0; ?>">
        <input type="hidden" name="_front_redirect" value="<?php echo esc_attr( $this->portal_page_url( array( 'tab' => 'complaints' ) ) ); ?>">
        <?php wp_nonce_field( 'acdc_save_complaint' ); ?>
        <div class="acdc-contract-grid" style="grid-template-columns:220px minmax(0,1fr);align-items:start;">
          <div class="acdc-contract-label">Date d'ouverture <span class="acdc-required">*</span></div>
          <div><input type="datetime-local" name="complaint[opened_at]" value="<?php echo $is_edit ? esc_attr( str_replace( ' ', 'T', $c->opened_at ) ) : esc_attr( current_time( 'Y-m-d' ) . 'T' . current_time( 'H:i' ) ); ?>" required></div>

          <div class="acdc-contract-label">Source <span class="acdc-required">*</span></div>
          <div><select name="complaint[source]"><?php foreach ( $source_labels as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . ( $is_edit && $c->source === $k ? ' selected' : '' ) . '>' . esc_html( $v ) . '</option>'; } ?></select></div>

          <div class="acdc-contract-label">Type <span class="acdc-required">*</span></div>
          <div><select name="complaint[type]"><?php foreach ( $type_labels as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . ( $is_edit && $c->type === $k ? ' selected' : '' ) . '>' . esc_html( $v ) . '</option>'; } ?></select></div>

          <div class="acdc-contract-label">Gravit&eacute; <span class="acdc-required">*</span></div>
          <div><select name="complaint[severity]"><?php foreach ( $severity_labels as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . ( $is_edit && $c->severity === $k ? ' selected' : '' ) . '>' . esc_html( $v ) . '</option>'; } ?></select><p class="acdc-help">Grave = entr&eacute;e automatique dans le pilotage am&eacute;lioration (ind. 32).</p></div>

          <div class="acdc-contract-label">Statut</div>
          <div><select name="complaint[status]"><?php foreach ( $status_labels as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . ( $is_edit && $c->status === $k ? ' selected' : '' ) . '>' . esc_html( $v ) . '</option>'; } ?></select></div>

          <div class="acdc-contract-label">R&eacute;sum&eacute; <span class="acdc-required">*</span></div>
          <div><textarea name="complaint[summary]" rows="3" required><?php echo $is_edit ? esc_textarea( $c->summary ) : ''; ?></textarea></div>

          <div class="acdc-contract-label">Responsable du traitement</div>
          <div><input type="text" name="complaint[assigned_to]" value="<?php echo $is_edit ? esc_attr( $c->assigned_to ) : ''; ?>"></div>

          <div class="acdc-contract-label">Mesure corrective</div>
          <div><textarea name="complaint[corrective_action]" rows="3"><?php echo $is_edit ? esc_textarea( $c->corrective_action ) : ''; ?></textarea></div>

          <div class="acdc-contract-label">Action d'am&eacute;lioration</div>
          <div><textarea name="complaint[improvement_action]" rows="3"><?php echo $is_edit ? esc_textarea( $c->improvement_action ) : ''; ?></textarea></div>

          <div class="acdc-contract-label">Ech&eacute;ance am&eacute;lioration</div>
          <div><input type="date" name="complaint[improvement_deadline]" value="<?php echo $is_edit && ! empty( $c->improvement_deadline ) ? esc_attr( $c->improvement_deadline ) : ''; ?>"></div>

          <div class="acdc-contract-label">Evaluation de l'efficacit&eacute;</div>
          <div><textarea name="complaint[effectiveness_eval]" rows="2"><?php echo $is_edit ? esc_textarea( $c->effectiveness_eval ) : ''; ?></textarea></div>
        </div>
        <div class="acdc-actions-end" style="margin-top:24px;">
          <a href="<?php echo esc_url( $base ); ?>" class="acdc-button acdc-button-soft">Annuler</a>
          <button type="submit" class="acdc-button acdc-button-primary"><?php echo $is_edit ? 'Enregistrer les modifications' : 'Cr&eacute;er la r&eacute;clamation'; ?></button>
        </div>
      </form>
    </div>
    <?php
  }

  private function render_complaint_detail_front( $c ) {
    if ( ! $c ) { echo '<p class="acdc-empty">R&eacute;clamation introuvable.</p>'; return; }
    $is_admin_ctx = is_admin();
    $base         = $is_admin_ctx ? admin_url( 'admin.php?page=acdc-of-complaints' ) : $this->portal_page_url( array( 'tab' => 'complaints' ) );
    $edit_url     = $is_admin_ctx ? add_query_arg( array( 'action' => 'edit', 'complaint_id' => $c->id ), $base ) : $this->portal_page_url( array( 'tab' => 'complaints', 'cpl_action' => 'edit', 'cpl_id' => $c->id ) );
    $ack_redirect = $is_admin_ctx ? add_query_arg( array( 'action' => 'view', 'complaint_id' => $c->id, 'ack' => '1' ), $base ) : $this->portal_page_url( array( 'tab' => 'complaints', 'cpl_action' => 'view', 'cpl_id' => $c->id, 'cpl_ack' => '1' ) );
    $del_redirect = $is_admin_ctx ? add_query_arg( 'deleted', '1', $base ) : $this->portal_page_url( array( 'tab' => 'complaints', 'cpl_deleted' => '1' ) );
    $type_labels     = $this->get_complaint_type_options();
    $source_labels   = $this->get_complaint_source_options();
    $severity_labels = $this->get_complaint_severity_options();
    $status_labels   = $this->get_complaint_status_options();
    $sev_class = 'grave' === $c->severity ? 'is-error' : ( 'moderee' === $c->severity ? 'is-warning' : 'is-success' );
    $sta_class = 'ouverte' === $c->status ? 'is-info' : ( 'cloturee' === $c->status ? 'is-neutral' : 'is-accent' );
    $ack_ok = ! empty( $c->acknowledged_at );
    $sev_lbl = isset( $severity_labels[ $c->severity ] ) ? $severity_labels[ $c->severity ] : $c->severity;
    $sta_lbl = isset( $status_labels[ $c->status ] )   ? $status_labels[ $c->status ]   : $c->status;
    $src_lbl = isset( $source_labels[ $c->source ] )   ? $source_labels[ $c->source ]   : $c->source;
    $typ_lbl = isset( $type_labels[ $c->type ] )       ? $type_labels[ $c->type ]       : $c->type;
    if ( isset( $_GET['cpl_ack'] ) || isset( $_GET['ack'] ) ) {
      echo "<div class=\"acdc-alert acdc-alert-success acdc-alert-dismissible\" role=\"status\"><div class=\"acdc-alert-body\"><strong class=\"acdc-alert-title\">Accus&eacute; enregistr&eacute;</strong><p class=\"acdc-alert-message\">L'accus&eacute; de r&eacute;ception a bien &eacute;t&eacute; enregistr&eacute;.</p></div><button type=\"button\" class=\"acdc-alert-dismiss\" data-acdc-dismiss-alert aria-label=\"Fermer\">&times;</button></div>";
    }
    if ( isset( $_GET['cpl_updated'] ) || isset( $_GET['updated'] ) ) {
      echo "<div class=\"acdc-alert acdc-alert-success acdc-alert-dismissible\" role=\"status\"><div class=\"acdc-alert-body\"><strong class=\"acdc-alert-title\">Enregistr&eacute;</strong><p class=\"acdc-alert-message\">La r&eacute;clamation a bien &eacute;t&eacute; mise &agrave; jour.</p></div><button type=\"button\" class=\"acdc-alert-dismiss\" data-acdc-dismiss-alert aria-label=\"Fermer\">&times;</button></div>";
    }
    ?>
    <style>
    .acdc-cpl-hero{background:linear-gradient(135deg,#fef6e4 0%,#fbf8f7 100%);border:1px solid #f0e6dc;border-radius:12px;padding:20px 24px;margin-bottom:16px;display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;}
    .acdc-cpl-hero-left{flex:1 1 280px;}
    .acdc-cpl-hero-id{font-size:11px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;}
    .acdc-cpl-hero-summary{font-size:16px;font-weight:600;color:#0f2c52;line-height:1.45;margin-bottom:8px;}
    .acdc-cpl-hero-meta{font-size:12px;color:#4b5d76;display:flex;gap:12px;flex-wrap:wrap;align-items:center;}
    .acdc-cpl-hero-right{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start;}
    .acdc-cpl-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;}
    .acdc-cpl-card{background:#fff;border:1px solid #f0e6dc;border-radius:10px;padding:16px 18px;}
    .acdc-cpl-card-title{font-size:10px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.07em;margin-bottom:10px;padding-bottom:7px;border-bottom:1px solid #f0e6dc;}
    .acdc-cpl-row{display:flex;align-items:flex-start;gap:10px;padding:6px 0;border-bottom:1px solid #f9f5f0;}
    .acdc-cpl-row:last-child{border-bottom:none;}
    .acdc-cpl-lbl{font-size:12px;font-weight:600;color:#4b5d76;min-width:140px;flex-shrink:0;}
    .acdc-cpl-val{font-size:13px;color:#0f2c52;flex:1;}
    .acdc-cpl-ack-banner{border-radius:10px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
    .acdc-cpl-ack-banner.missing{background:#fff0f0;border:1px solid #f5c6c6;}
    .acdc-cpl-ack-banner.ok{background:#e8f4ec;border:1px solid #b7dfca;}
    .acdc-cpl-ack-icon{font-size:24px;flex-shrink:0;}
    .acdc-cpl-ack-text{flex:1;}
    .acdc-cpl-ack-text strong{display:block;font-size:13px;color:#0f2c52;margin-bottom:3px;}
    .acdc-cpl-ack-text span{font-size:12px;color:#4b5d76;}
    @media(max-width:700px){.acdc-cpl-grid{grid-template-columns:1fr;}}
    </style>

    <section class="acdc-section-head">
      <div>
        <h2>R&eacute;clamation #<?php echo (int) $c->id; ?></h2>
        <p>Ouverte le <?php echo esc_html( wp_date( 'd/m/Y', strtotime( $c->opened_at ) ) ); ?> &mdash; Source&nbsp;: <?php echo esc_html( $src_lbl ); ?></p>
      </div>
      <div class="acdc-inline-wrap">
        <a href="<?php echo esc_url( $edit_url ); ?>" class="acdc-button acdc-button-soft">Modifier</a>
        <a href="<?php echo esc_url( $base ); ?>" class="acdc-button acdc-button-soft">Retour &agrave; la liste</a>
      </div>
    </section>

    <?php if ( ! $ack_ok ) : ?>
    <div class="acdc-cpl-ack-banner missing">
      <div class="acdc-cpl-ack-icon">&#9888;&#65039;</div>
      <div class="acdc-cpl-ack-text">
        <strong>Accus&eacute; de r&eacute;ception manquant &mdash; action requise</strong>
        <span>L'indicateur 31 Qualiopi exige un accus&eacute; de r&eacute;ception pour chaque r&eacute;clamation. Sans cela, l'organisme est en non-conformit&eacute; majeure.</span>
      </div>
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="acdc_acknowledge_complaint">
        <input type="hidden" name="complaint_id" value="<?php echo (int) $c->id; ?>">
        <input type="hidden" name="_front_redirect" value="<?php echo esc_attr( $ack_redirect ); ?>">
        <?php wp_nonce_field( 'acdc_acknowledge_complaint' ); ?>
        <button type="submit" class="acdc-button acdc-button-primary">Enregistrer l'accus&eacute; de r&eacute;ception</button>
      </form>
    </div>
    <?php else : ?>
    <div class="acdc-cpl-ack-banner ok">
      <div class="acdc-cpl-ack-icon">&#9989;</div>
      <div class="acdc-cpl-ack-text">
        <strong>Accus&eacute; de r&eacute;ception enregistr&eacute;</strong>
        <span>Le <?php echo esc_html( wp_date( 'd F Y \&agrave; H\hi', strtotime( $c->acknowledged_at ) ) ); ?></span>
      </div>
    </div>
    <?php endif; ?>

    <div class="acdc-cpl-hero">
      <div class="acdc-cpl-hero-left">
        <div class="acdc-cpl-hero-id">R&eacute;clamation #<?php echo (int) $c->id; ?></div>
        <div class="acdc-cpl-hero-summary"><?php echo esc_html( $c->summary ?: '—' ); ?></div>
        <div class="acdc-cpl-hero-meta">
          <span class="acdc-badge <?php echo $sev_class; ?>"><?php echo esc_html( $sev_lbl ); ?></span>
          <span class="acdc-badge <?php echo $sta_class; ?>"><?php echo esc_html( $sta_lbl ); ?></span>
          <span><?php echo esc_html( $typ_lbl ); ?></span>
        </div>
      </div>
      <?php if ( ! empty( $c->assigned_to ) ) : ?>
      <div class="acdc-cpl-hero-right">
        <span style="font-size:12px;color:#4b5d76;">Responsable&nbsp;: <strong style="color:#0f2c52;"><?php echo esc_html( $c->assigned_to ); ?></strong></span>
      </div>
      <?php endif; ?>
    </div>

    <div class="acdc-cpl-grid">
      <div class="acdc-cpl-card">
        <div class="acdc-cpl-card-title">Identification</div>
        <div class="acdc-cpl-row"><div class="acdc-cpl-lbl">Source</div><div class="acdc-cpl-val"><?php echo esc_html( $src_lbl ); ?></div></div>
        <div class="acdc-cpl-row"><div class="acdc-cpl-lbl">Type</div><div class="acdc-cpl-val"><?php echo esc_html( $typ_lbl ); ?></div></div>
        <div class="acdc-cpl-row"><div class="acdc-cpl-lbl">Date d'ouverture</div><div class="acdc-cpl-val"><?php echo esc_html( wp_date( 'd/m/Y \&agrave; H\hi', strtotime( $c->opened_at ) ) ); ?></div></div>
        <?php if ( ! empty( $c->closed_at ) ) : ?>
        <div class="acdc-cpl-row"><div class="acdc-cpl-lbl">Date de cl&ocirc;ture</div><div class="acdc-cpl-val"><?php echo esc_html( wp_date( 'd/m/Y \&agrave; H\hi', strtotime( $c->closed_at ) ) ); ?></div></div>
        <?php endif; ?>
        <?php if ( ! empty( $c->improvement_id ) ) :
          $ci_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard&tab=quality' ) : $this->portal_page_url( array( 'tab' => 'quality' ) );
          $ci_new_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard&tab=continuous_improvement&action=new&from_complaint=' . (int) $c->id ) : $this->portal_page_url( array( 'tab' => 'continuous_improvement', 'action' => 'new', 'from_complaint' => (int) $c->id ) );
        ?>
        <div class="acdc-cpl-row"><div class="acdc-cpl-lbl">Ind. 32</div><div class="acdc-cpl-val"><a href="<?php echo esc_url( $ci_new_url ); ?>" class="acdc-badge is-success" style="text-decoration:none;">&#8594; Créer l'action d'amélioration liée</a>&nbsp;&nbsp;<a href="<?php echo esc_url( $ci_url ); ?>" style="font-size:11px;color:#4b5d76;">Voir le hub qualité</a></div></div>
        <?php endif; ?>
      </div>

      <div class="acdc-cpl-card">
        <div class="acdc-cpl-card-title">Traitement</div>
        <div class="acdc-cpl-row"><div class="acdc-cpl-lbl">Mesure corrective</div><div class="acdc-cpl-val"><?php echo nl2br( esc_html( $c->corrective_action ?: '—' ) ); ?></div></div>
        <div class="acdc-cpl-row"><div class="acdc-cpl-lbl">Action d'am&eacute;lioration</div><div class="acdc-cpl-val"><?php echo nl2br( esc_html( $c->improvement_action ?: '—' ) ); ?></div></div>
        <?php if ( ! empty( $c->improvement_deadline ) ) : ?>
        <div class="acdc-cpl-row"><div class="acdc-cpl-lbl">Ech&eacute;ance</div><div class="acdc-cpl-val"><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $c->improvement_deadline ) ) ); ?></div></div>
        <?php endif; ?>
        <div class="acdc-cpl-row"><div class="acdc-cpl-lbl">Efficacit&eacute;</div><div class="acdc-cpl-val"><?php echo nl2br( esc_html( $c->effectiveness_eval ?: '—' ) ); ?></div></div>
      </div>
    </div>

    <div class="acdc-actions-end" style="margin-top:8px;">
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Supprimer définitivement cette réclamation ?');">
        <input type="hidden" name="action" value="acdc_delete_complaint">
        <input type="hidden" name="complaint_id" value="<?php echo (int) $c->id; ?>">
        <input type="hidden" name="_front_redirect" value="<?php echo esc_attr( $del_redirect ); ?>">
        <?php wp_nonce_field( 'acdc_delete_complaint' ); ?>
        <button type="submit" class="acdc-button acdc-button-danger">Supprimer cette r&eacute;clamation</button>
      </form>
    </div>
    <?php
  }


  public function render_admin_complaints_page() {
    $this->acdc_render_backoffice_tabs( 'acdc-of-complaints' );
    $action       = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
    $complaint_id = isset( $_GET['complaint_id'] ) ? (int) $_GET['complaint_id'] : 0;
    echo '<div class="wrap acdc-admin-shell">';
    if ( 'create' === $action ) {
      $this->render_complaint_form( null );
    } elseif ( 'edit' === $action && $complaint_id > 0 ) {
      $c = $this->get_complaint_by_id( $complaint_id );
      $this->render_complaint_form( $c );
    } elseif ( 'view' === $action && $complaint_id > 0 ) {
      $c = $this->get_complaint_by_id( $complaint_id );
      $this->render_complaint_detail( $c );
    } else {
      $this->render_complaints_list();
    }
    echo '</div>';
  }

  private function render_complaints_list() {
    $this->render_complaints_list_front( array() );
  }

    private function render_complaint_form( $c ) {
    $is_edit = ! empty( $c );
    $base = admin_url( 'admin.php?page=acdc-of-complaints' );
    $type_labels     = $this->get_complaint_type_options();
    $source_labels   = $this->get_complaint_source_options();
    $severity_labels = $this->get_complaint_severity_options();
    $status_labels   = $this->get_complaint_status_options();
    $title = $is_edit ? 'Modifier une r&eacute;clamation' : 'Cr&eacute;er une r&eacute;clamation';
    if ( isset( $_GET['updated'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>R&eacute;clamation enregistr&eacute;e.</p></div>'; }
    ?>
    <section class="acdc-section-head"><div><h2><?php echo $title; ?></h2></div></section>
    <div class="acdc-panel">
      <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="acdc_save_complaint">
        <input type="hidden" name="complaint_id" value="<?php echo $is_edit ? (int) $c->id : 0; ?>">
        <?php wp_nonce_field( 'acdc_save_complaint' ); ?>
        <div class="acdc-contract-grid" style="grid-template-columns:220px minmax(0,1fr);align-items:start;">
          <div class="acdc-contract-label">Date d'ouverture <span class="acdc-required">*</span></div>
          <div><input type="datetime-local" name="complaint[opened_at]" value="<?php echo $is_edit ? esc_attr( str_replace( ' ', 'T', $c->opened_at ) ) : esc_attr( current_time( 'Y-m-d' ) . 'T' . current_time( 'H:i' ) ); ?>" required></div>

          <div class="acdc-contract-label">Source <span class="acdc-required">*</span></div>
          <div><select name="complaint[source]"><?php foreach ( $source_labels as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . ( $is_edit && $c->source === $k ? ' selected' : '' ) . '>' . esc_html( $v ) . '</option>'; } ?></select></div>

          <div class="acdc-contract-label">Type <span class="acdc-required">*</span></div>
          <div><select name="complaint[type]"><?php foreach ( $type_labels as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . ( $is_edit && $c->type === $k ? ' selected' : '' ) . '>' . esc_html( $v ) . '</option>'; } ?></select></div>

          <div class="acdc-contract-label">Gravit&eacute; <span class="acdc-required">*</span></div>
          <div><select name="complaint[severity]"><?php foreach ( $severity_labels as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . ( $is_edit && $c->severity === $k ? ' selected' : '' ) . '>' . esc_html( $v ) . '</option>'; } ?></select><p class="acdc-help">Gravit&eacute; = Grave : une entr&eacute;e de pilotage am&eacute;lioration est cr&eacute;&eacute;e automatiquement (indicateur 32).</p></div>

          <div class="acdc-contract-label">Statut</div>
          <div><select name="complaint[status]"><?php foreach ( $status_labels as $k => $v ) { echo '<option value="' . esc_attr( $k ) . '"' . ( $is_edit && $c->status === $k ? ' selected' : '' ) . '>' . esc_html( $v ) . '</option>'; } ?></select></div>

          <div class="acdc-contract-label">R&eacute;sum&eacute; <span class="acdc-required">*</span></div>
          <div><textarea name="complaint[summary]" rows="3" required><?php echo $is_edit ? esc_textarea( $c->summary ) : ''; ?></textarea></div>

          <div class="acdc-contract-label">Responsable du traitement</div>
          <div><input type="text" name="complaint[assigned_to]" value="<?php echo $is_edit ? esc_attr( $c->assigned_to ) : ''; ?>"></div>

          <div class="acdc-contract-label">Mesure corrective</div>
          <div><textarea name="complaint[corrective_action]" rows="3"><?php echo $is_edit ? esc_textarea( $c->corrective_action ) : ''; ?></textarea></div>

          <div class="acdc-contract-label">Action d'am&eacute;lioration</div>
          <div><textarea name="complaint[improvement_action]" rows="3"><?php echo $is_edit ? esc_textarea( $c->improvement_action ) : ''; ?></textarea></div>

          <div class="acdc-contract-label">Ech&eacute;ance am&eacute;lioration</div>
          <div><input type="date" name="complaint[improvement_deadline]" value="<?php echo $is_edit && ! empty( $c->improvement_deadline ) ? esc_attr( $c->improvement_deadline ) : ''; ?>"></div>

          <div class="acdc-contract-label">Evaluation de l'efficacit&eacute;</div>
          <div><textarea name="complaint[effectiveness_eval]" rows="2"><?php echo $is_edit ? esc_textarea( $c->effectiveness_eval ) : ''; ?></textarea></div>
        </div>
        <div class="acdc-actions-end" style="margin-top:24px;">
          <a href="<?php echo esc_url( $base ); ?>" class="acdc-button acdc-button-soft">Annuler</a>
          <button type="submit" class="acdc-button acdc-button-primary"><?php echo $is_edit ? 'Enregistrer les modifications' : 'Cr&eacute;er la r&eacute;clamation'; ?></button>
        </div>
      </form>
    </div>
    <?php
  }

  private function render_complaint_detail( $c ) {
    $this->render_complaint_detail_front( $c );
  }

  /* =========================================================
   * ACDC 3.24.6 - R1 : Conseil de perfectionnement (ind. 20)
   * ========================================================= */

  public function render_front_conseil_perfectionnement_tab() {
    // Interception WAF-safe (portal_page_url, pas admin-post.php depuis le front)
    $cp_mid_action = isset( $_REQUEST['cp_mid_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['cp_mid_action'] ) ) : '';
    $cp_rid_action = isset( $_REQUEST['cp_rid_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['cp_rid_action'] ) ) : '';
    $cp_post_action = isset( $_REQUEST['cp_post_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['cp_post_action'] ) ) : '';

    if ( 'save_member' === $cp_post_action && ! is_admin() ) {
      $this->handle_save_perfectionnement_member();
      return;
    }
    if ( 'save_meeting' === $cp_post_action && ! is_admin() ) {
      $this->handle_save_perfectionnement_meeting();
      return;
    }
    if ( 'delete' === $cp_mid_action ) {
      $this->handle_delete_perfectionnement_member();
      return;
    }
    if ( 'delete' === $cp_rid_action ) {
      $this->handle_delete_perfectionnement_meeting();
      return;
    }

    $cp_action = isset( $_GET['cp_action'] ) ? sanitize_key( wp_unslash( $_GET['cp_action'] ) ) : '';
    $base_url  = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'conseil_perfectionnement' ) );
    $members   = $this->get_perfectionnement_members();
    $meetings  = $this->get_perfectionnement_meetings();
    $status    = $this->get_perfectionnement_compliance_status();

    // Tri réunions : plus récente en premier
    usort( $meetings, function( $a, $b ) { return strcmp( (string) ( $b['date'] ?? '' ), (string) ( $a['date'] ?? '' ) ); } );

    $status_colors = array( 'green' => '#22c55e', 'orange' => '#f59e0b', 'red' => '#ef4444' );
    $status_labels = array( 'green' => 'Conforme — réunion < 12 mois', 'orange' => 'Attention — réunion > 12 mois', 'red' => 'Non conforme — aucune réunion enregistrée' );
    $status_bg     = array( 'green' => '#dff6e5', 'orange' => '#fef9c3', 'red' => '#fee2e2' );
    $dot_color = $status_colors[ $status ] ?? '#ef4444';
    $dot_bg    = $status_bg[ $status ] ?? '#fee2e2';
    $dot_label = $status_labels[ $status ] ?? '';

    $save_member_url  = is_admin() ? admin_url( 'admin-post.php' ) : $this->portal_page_url( array( 'tab' => 'conseil_perfectionnement', 'cp_post_action' => 'save_member' ) );
    $save_meeting_url = is_admin() ? admin_url( 'admin-post.php' ) : $this->portal_page_url( array( 'tab' => 'conseil_perfectionnement', 'cp_post_action' => 'save_meeting' ) );
    ?>
    <section class="acdc-section-head">
      <div><h2>Conseil de perfectionnement</h2><p class="acdc-section-subtitle">Indicateur 20 — Composition et réunions</p></div>
      <div class="acdc-section-head-actions">
        <span style="display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border-radius:20px;background:<?php echo esc_attr( $dot_bg ); ?>;font-size:13px;font-weight:600;color:#3d3d3d;">
          <span style="width:10px;height:10px;border-radius:50%;background:<?php echo esc_attr( $dot_color ); ?>;display:inline-block;"></span>
          <?php echo esc_html( $dot_label ); ?>
        </span>
      </div>
    </section>

    <?php if ( 'add_member' === $cp_action ) : ?>
    <div class="acdc-panel">
      <h3>Ajouter un membre</h3>
      <form method="post" action="<?php echo esc_url( $save_member_url ); ?>" class="acdc-form">
        <?php if ( is_admin() ) : ?><input type="hidden" name="action" value="acdc_save_perfectionnement_member"><?php endif; ?>
        <?php wp_nonce_field( 'acdc_save_cp_member' ); ?>
        <input type="hidden" name="cp_member[id]" value="">
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Nom complet <span class="acdc-required">*</span></div>
          <div><input type="text" name="cp_member[name]" class="acdc-input" placeholder="Prénom Nom" required></div>
          <div class="acdc-contract-label">Rôle / Qualité <span class="acdc-required">*</span></div>
          <div><input type="text" name="cp_member[role]" class="acdc-input" placeholder="Ex : Stagiaire, Employeur, Expert…" required></div>
          <div class="acdc-contract-label">Email</div>
          <div><input type="email" name="cp_member[email]" class="acdc-input" placeholder="adresse@domaine.fr"></div>
        </div>
        <div class="acdc-actions-end acdc-actions-gap-top">
          <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'conseil_perfectionnement' ), $base_url ) ); ?>" class="acdc-button acdc-button-soft">Annuler</a>
          <button type="submit" class="acdc-button acdc-button-primary">Enregistrer</button>
        </div>
      </form>
    </div>

    <?php elseif ( 'add_meeting' === $cp_action ) : ?>
    <div class="acdc-panel">
      <h3>Ajouter une réunion</h3>
      <form method="post" action="<?php echo esc_url( $save_meeting_url ); ?>" class="acdc-form">
        <?php if ( is_admin() ) : ?><input type="hidden" name="action" value="acdc_save_perfectionnement_meeting"><?php endif; ?>
        <?php wp_nonce_field( 'acdc_save_cp_meeting' ); ?>
        <input type="hidden" name="cp_meeting[id]" value="">
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Date de la réunion <span class="acdc-required">*</span></div>
          <div><input type="date" name="cp_meeting[date]" class="acdc-input" required></div>
          <div class="acdc-contract-label">Ordre du jour</div>
          <div><textarea name="cp_meeting[agenda]" class="acdc-input" rows="4" placeholder="Points abordés, thèmes de travail…"></textarea></div>
          <div class="acdc-contract-label">Compte-rendu / Notes</div>
          <div><textarea name="cp_meeting[notes]" class="acdc-input" rows="6" placeholder="Résumé des échanges, décisions, suites données…"></textarea></div>
        </div>
        <div class="acdc-actions-end acdc-actions-gap-top">
          <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'conseil_perfectionnement' ), $base_url ) ); ?>" class="acdc-button acdc-button-soft">Annuler</a>
          <button type="submit" class="acdc-button acdc-button-primary">Enregistrer</button>
        </div>
      </form>
    </div>

    <?php else : ?>
    <div class="acdc-panel acdc-mb-18">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <h3 style="margin:0;">Membres du conseil</h3>
        <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'conseil_perfectionnement', 'cp_action' => 'add_member' ), $base_url ) ); ?>" class="acdc-button acdc-button-primary">+ Ajouter un membre</a>
      </div>
      <?php if ( empty( $members ) ) : ?>
        <p style="color:#888;font-size:14px;">Aucun membre enregistré.</p>
      <?php else : ?>
        <div class="acdc-table-wrap">
          <table class="acdc-table">
            <thead><tr><th>Nom</th><th>Rôle</th><th>Email</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ( $members as $m ) : ?>
              <tr>
                <td><?php echo esc_html( $m['name'] ?? '' ); ?></td>
                <td><?php echo esc_html( $m['role'] ?? '' ); ?></td>
                <td><?php $em = $m['email'] ?? ''; echo $em ? '<a href="mailto:' . esc_attr( $em ) . '">' . esc_html( $em ) . '</a>' : '—'; ?></td>
                <td class="acdc-actions-cell-icons">
                  <div class="acdc-groups-actions-inline">
                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'tab' => 'conseil_perfectionnement', 'cp_mid_action' => 'delete', 'cp_mid' => rawurlencode( $m['id'] ) ), $this->portal_page_url( array( 'tab' => 'conseil_perfectionnement' ) ) ), 'acdc_delete_cp_member_' . $m['id'] ) ); ?>"
                       class="acdc-row-action-icon acdc-row-delete-link"
                       data-acdc-iconized="1"
                       title="Supprimer ce membre"
                       aria-label="Supprimer"
                       onclick="return confirm('Supprimer ce membre ?');">
                      <?php echo $this->render_inline_icon( 'trash', 25 ); ?>
                      <span class="acdc-action-hub-sr screen-reader-text">Supprimer</span>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="acdc-panel">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <h3 style="margin:0;">Réunions</h3>
        <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'conseil_perfectionnement', 'cp_action' => 'add_meeting' ), $base_url ) ); ?>" class="acdc-button acdc-button-primary">+ Ajouter une réunion</a>
      </div>
      <?php if ( empty( $meetings ) ) : ?>
        <p style="color:#888;font-size:14px;">Aucune réunion enregistrée. <strong>Non conforme pour l'indicateur 20.</strong></p>
      <?php else : ?>
        <div class="acdc-table-wrap">
          <table class="acdc-table">
            <thead><tr><th>Date</th><th>Ordre du jour</th><th>Compte-rendu</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ( $meetings as $m ) : ?>
              <tr>
                <td style="white-space:nowrap;font-weight:600;"><?php echo esc_html( $m['date'] ? wp_date( 'd/m/Y', strtotime( $m['date'] ) ) : '' ); ?></td>
                <td style="max-width:260px;white-space:pre-wrap;word-break:break-word;"><?php echo nl2br( esc_html( $m['agenda'] ?? '' ) ); ?></td>
                <td style="max-width:320px;white-space:pre-wrap;word-break:break-word;"><?php echo nl2br( esc_html( $m['notes'] ?? '' ) ); ?></td>
                <td class="acdc-actions-cell-icons">
                  <div class="acdc-groups-actions-inline">
                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'tab' => 'conseil_perfectionnement', 'cp_rid_action' => 'delete', 'cp_rid' => rawurlencode( $m['id'] ) ), $this->portal_page_url( array( 'tab' => 'conseil_perfectionnement' ) ) ), 'acdc_delete_cp_meeting_' . $m['id'] ) ); ?>"
                       class="acdc-row-action-icon acdc-row-delete-link"
                       data-acdc-iconized="1"
                       title="Supprimer cette réunion"
                       aria-label="Supprimer"
                       onclick="return confirm('Supprimer cette réunion ?');">
                      <?php echo $this->render_inline_icon( 'trash', 25 ); ?>
                      <span class="acdc-action-hub-sr screen-reader-text">Supprimer</span>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="acdc-panel" style="background:#f9f5f0;border:1px solid #e8d8c4;">
      <h4 style="margin-top:0;color:#8b5b23;">Preuve exportable pour l'auditeur Qualiopi</h4>
      <p style="font-size:13px;color:#555;margin-bottom:12px;">Les données ci-dessus constituent la preuve documentaire pour l'indicateur 20. Pour l'auditeur, vous pouvez présenter directement cet écran ou exporter les informations.</p>
      <p style="font-size:12px;color:#888;margin:0;">Membres : <?php echo count( $members ); ?> enregistré(s) &nbsp;|&nbsp; Réunions : <?php echo count( $meetings ); ?> enregistrée(s)</p>
    </div>
    <?php endif; ?>
    <?php
  }

  /* =========================================================
   * ACDC 3.24.9 - R3 : Réseau partenaires PSH (ind. 26)
   * ========================================================= */

  public function render_front_psh_partners_tab() {
    // Interception WAF-safe
    $psh_pid_action  = isset( $_REQUEST['psh_pid_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['psh_pid_action'] ) ) : '';
    $psh_post_action = isset( $_REQUEST['psh_post_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['psh_post_action'] ) ) : '';

    if ( 'save' === $psh_post_action && ! is_admin() ) {
      $this->handle_save_psh_partner();
      return;
    }
    if ( 'delete' === $psh_pid_action ) {
      $this->handle_delete_psh_partner();
      return;
    }

    $psh_action  = isset( $_GET['psh_action'] ) ? sanitize_key( wp_unslash( $_GET['psh_action'] ) ) : '';
    $psh_pid_edit = isset( $_GET['psh_pid'] ) ? sanitize_text_field( wp_unslash( $_GET['psh_pid'] ) ) : '';
    $base_url    = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'psh_partners' ) );
    $partners    = $this->get_psh_partners();
    $type_labels = $this->get_psh_partner_types();
    $status      = $this->get_psh_compliance_status();
    $save_url    = is_admin() ? admin_url( 'admin-post.php' ) : $this->portal_page_url( array( 'tab' => 'psh_partners', 'psh_post_action' => 'save' ) );

    // Trouver le partenaire à éditer ou visualiser
    $edit_partner = null;
    if ( in_array( $psh_action, array( 'edit', 'view' ), true ) && '' !== $psh_pid_edit ) {
      foreach ( $partners as $pp ) {
        if ( isset( $pp['id'] ) && (string) $pp['id'] === $psh_pid_edit ) {
          $edit_partner = $pp;
          break;
        }
      }
    }

    $dot_color = 'green' === $status ? '#22c55e' : '#ef4444';
    $dot_bg    = 'green' === $status ? '#dff6e5' : '#fee2e2';
    $dot_label = 'green' === $status ? 'Conforme — au moins 1 partenaire enregistré' : 'Non conforme — aucun partenaire PSH enregistré';
    ?>
    <section class="acdc-section-head">
      <div><h2>Réseau partenaires PSH</h2><p class="acdc-section-subtitle">Indicateur 26 — MDPH, Cap emploi, AGEFIPH et autres partenaires</p></div>
      <div class="acdc-section-head-actions">
        <span style="display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border-radius:20px;background:<?php echo esc_attr( $dot_bg ); ?>;font-size:13px;font-weight:600;color:#3d3d3d;">
          <span style="width:10px;height:10px;border-radius:50%;background:<?php echo esc_attr( $dot_color ); ?>;display:inline-block;"></span>
          <?php echo esc_html( $dot_label ); ?>
        </span>
      </div>
    </section>

    <?php if ( in_array( $psh_action, array( 'add', 'edit' ), true ) ) :
    $is_edit = 'edit' === $psh_action && $edit_partner;
    ?>
    <div class="acdc-panel">
      <h3><?php echo $is_edit ? 'Modifier le partenaire PSH' : 'Ajouter un partenaire PSH'; ?></h3>
      <form method="post" action="<?php echo esc_url( $save_url ); ?>" class="acdc-form">
        <?php if ( is_admin() ) : ?><input type="hidden" name="action" value="acdc_save_psh_partner"><?php endif; ?>
        <?php wp_nonce_field( 'acdc_save_psh_partner' ); ?>
        <input type="hidden" name="psh_partner[id]" value="<?php echo $is_edit ? esc_attr( $edit_partner['id'] ) : ''; ?>">
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Catégorie <span class="acdc-required">*</span></div>
          <div>
            <select name="psh_partner[scope]" class="acdc-input" required>
              <option value="national" <?php selected( ( $is_edit ? ( $edit_partner['scope'] ?? '' ) : '' ), 'national' ); ?>>National</option>
              <option value="local_var" <?php selected( ( $is_edit ? ( $edit_partner['scope'] ?? 'local_var' ) : 'local_var' ), 'local_var' ); ?>>Local — Var / Estérel</option>
            </select>
          </div>
          <div class="acdc-contract-label">Nom de la structure <span class="acdc-required">*</span></div>
          <div><input type="text" name="psh_partner[name]" class="acdc-input" placeholder="Ex : MDPH du Var" value="<?php echo $is_edit ? esc_attr( $edit_partner['name'] ?? '' ) : ''; ?>" required></div>
          <div class="acdc-contract-label">Type <span class="acdc-required">*</span></div>
          <div>
            <select name="psh_partner[type]" class="acdc-input" required>
              <option value="">Choisir…</option>
              <?php foreach ( $type_labels as $k => $l ) : ?>
                <option value="<?php echo esc_attr( $k ); ?>" <?php selected( ( $is_edit ? ( $edit_partner['type'] ?? '' ) : '' ), $k ); ?>><?php echo esc_html( $l ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="acdc-contract-label">Département / Région</div>
          <div><input type="text" name="psh_partner[region]" class="acdc-input" placeholder="Ex : Var (83)" value="<?php echo $is_edit ? esc_attr( $edit_partner['region'] ?? '' ) : ''; ?>"></div>
          <div class="acdc-contract-label">Nom du contact</div>
          <div><input type="text" name="psh_partner[contact]" class="acdc-input" placeholder="Prénom Nom, fonction" value="<?php echo $is_edit ? esc_attr( $edit_partner['contact'] ?? '' ) : ''; ?>"></div>
          <div class="acdc-contract-label">Téléphone</div>
          <div><input type="tel" name="psh_partner[phone]" class="acdc-input" placeholder="04 XX XX XX XX" value="<?php echo $is_edit ? esc_attr( $edit_partner['phone'] ?? '' ) : ''; ?>"></div>
          <div class="acdc-contract-label">Email</div>
          <div><input type="email" name="psh_partner[email]" class="acdc-input" placeholder="contact@structure.fr" value="<?php echo $is_edit ? esc_attr( $edit_partner['email'] ?? '' ) : ''; ?>"></div>
          <div class="acdc-contract-label">Notes</div>
          <div><textarea name="psh_partner[notes]" class="acdc-input" rows="3" placeholder="Convention signée, date de contact, type de collaboration…"><?php echo $is_edit ? esc_textarea( $edit_partner['notes'] ?? '' ) : ''; ?></textarea></div>
        </div>
        <div class="acdc-actions-end acdc-actions-gap-top">
          <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'psh_partners' ), $base_url ) ); ?>" class="acdc-button acdc-button-soft">Annuler</a>
          <button type="submit" class="acdc-button acdc-button-primary"><?php echo $is_edit ? 'Enregistrer les modifications' : 'Enregistrer'; ?></button>
        </div>
      </form>
    </div>

    <?php elseif ( 'view' === $psh_action && $edit_partner ) : ?>
    <div class="acdc-panel">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <h3 style="margin:0;"><?php echo esc_html( $edit_partner['name'] ?? '—' ); ?></h3>
        <div style="display:flex;gap:8px;">
          <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'psh_partners', 'psh_action' => 'edit', 'psh_pid' => esc_attr( $edit_partner['id'] ) ), $base_url ) ); ?>" class="acdc-button acdc-button-soft">Modifier</a>
          <a href="<?php echo esc_url( $base_url ); ?>" class="acdc-button acdc-button-soft">Retour</a>
        </div>
      </div>
      <table class="acdc-piece-table">
        <tbody>
          <tr><td style="font-weight:700;width:200px;">Structure</td><td><?php echo esc_html( $edit_partner['name'] ?? '—' ); ?></td></tr>
          <tr><td style="font-weight:700;">Type</td><td><?php echo esc_html( $type_labels[ $edit_partner['type'] ?? '' ] ?? '—' ); ?></td></tr>
          <tr><td style="font-weight:700;">Catégorie</td><td><?php echo ( $edit_partner['scope'] ?? '' ) === 'national' ? 'National' : 'Local — Var / Estérel'; ?></td></tr>
          <tr><td style="font-weight:700;">Département / Région</td><td><?php echo esc_html( $edit_partner['region'] ?? '—' ); ?></td></tr>
          <tr><td style="font-weight:700;">Contact</td><td><?php echo esc_html( $edit_partner['contact'] ?? '—' ); ?></td></tr>
          <tr><td style="font-weight:700;">Téléphone</td><td><?php echo esc_html( $edit_partner['phone'] ?? '—' ); ?></td></tr>
          <tr><td style="font-weight:700;">Email</td><td><?php $em = $edit_partner['email'] ?? ''; echo $em ? '<a href="mailto:' . esc_attr( $em ) . '">' . esc_html( $em ) . '</a>' : '—'; ?></td></tr>
          <tr><td style="font-weight:700;">Notes</td><td style="white-space:pre-line;"><?php echo esc_html( $edit_partner['notes'] ?? '—' ); ?></td></tr>
        </tbody>
      </table>
    </div>

    <?php else : ?>
    <?php
    $partners_national = array_values( array_filter( $partners, function( $p ) { return ( $p['scope'] ?? '' ) === 'national'; } ) );
    $partners_local    = array_values( array_filter( $partners, function( $p ) { return ( $p['scope'] ?? '' ) !== 'national'; } ) );

    // Fonction d'affichage d'un groupe de partenaires
    $render_partners_table = function( $list ) use ( $type_labels ) {
      if ( empty( $list ) ) {
        echo '<p style="color:#888;font-size:14px;">Aucun partenaire dans cette catégorie.</p>';
        return;
      }
      echo '<div class="acdc-table-wrap"><table class="acdc-table">';
      echo '<thead><tr><th>Structure</th><th>Type</th><th>Département / Région</th><th>Contact</th><th>Notes</th><th>Actions</th></tr></thead><tbody>';
      foreach ( $list as $p ) {
        $pid = $p['id'] ?? '';
        $view_url = add_query_arg( array( 'tab' => 'psh_partners', 'psh_action' => 'view', 'psh_pid' => rawurlencode( $pid ) ), $base_url );
        $edit_url = add_query_arg( array( 'tab' => 'psh_partners', 'psh_action' => 'edit', 'psh_pid' => rawurlencode( $pid ) ), $base_url );
        $del_url = wp_nonce_url( add_query_arg( array( 'tab' => 'psh_partners', 'psh_pid_action' => 'delete', 'psh_pid' => rawurlencode( $pid ) ), $this->portal_page_url( array( 'tab' => 'psh_partners' ) ) ), 'acdc_delete_psh_partner_' . $pid );
        echo '<tr>';
        echo '<td style="font-weight:600;">' . esc_html( $p['name'] ?? '' ) . '</td>';
        echo '<td><span style="display:inline-block;padding:2px 8px;border-radius:12px;background:#f0e6dc;color:#8b5b23;font-size:12px;font-weight:600;">' . esc_html( $type_labels[ $p['type'] ?? '' ] ?? ( $p['type'] ?? '' ) ) . '</span></td>';
        echo '<td>' . ( esc_html( $p['region'] ?? '' ) ?: '—' ) . '</td>';
        echo '<td>' . ( esc_html( $p['contact'] ?? '' ) ?: '' ) . ( ! empty( $p['phone'] ) ? '<br><small style="color:#888;">' . esc_html( $p['phone'] ) . '</small>' : '' ) . ( ! empty( $p['email'] ) ? '<br><a href="mailto:' . esc_attr( $p['email'] ) . '" style="font-size:12px;">' . esc_html( $p['email'] ) . '</a>' : '' ) . '</td>';
        echo '<td style="font-size:12px;color:#555;max-width:240px;">' . nl2br( esc_html( $p['notes'] ?? '' ) ) . '</td>';
        echo '<td class="acdc-actions-cell-icons"><div class="acdc-groups-actions-inline">'
          . '<a href="' . esc_url( $view_url ) . '" class="acdc-row-action-icon acdc-row-view-link" data-acdc-iconized="1" title="Voir" aria-label="Voir">' . $this->render_inline_icon( 'view', 25 ) . '<span class="acdc-action-hub-sr screen-reader-text">Voir</span></a>'
          . '<a href="' . esc_url( $edit_url ) . '" class="acdc-row-action-icon acdc-row-edit-link" data-acdc-iconized="1" title="Modifier" aria-label="Modifier">' . $this->render_inline_icon( 'edit-pencil', 25 ) . '<span class="acdc-action-hub-sr screen-reader-text">Modifier</span></a>'
          . '<a href="' . esc_url( $del_url ) . '" class="acdc-row-action-icon acdc-row-delete-link" data-acdc-iconized="1" title="Supprimer ce partenaire" aria-label="Supprimer" onclick="return confirm(\'Supprimer ce partenaire ?\');">' . $this->render_inline_icon( 'trash', 25 ) . '<span class="acdc-action-hub-sr screen-reader-text">Supprimer</span></a>'
          . '</div></td>';
        echo '</tr>';
      }
      echo '</tbody></table></div>';
    };
    ?>

    <div class="acdc-panel acdc-mb-18">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <div>
          <h3 style="margin:0;">Partenaires nationaux</h3>
          <p style="font-size:12px;color:#888;margin:4px 0 0;">AGEFIPH, FIPHFP, France Travail, ONISEP…</p>
        </div>
        <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'psh_partners', 'psh_action' => 'add' ), $base_url ) ); ?>" class="acdc-button acdc-button-soft">+ Ajouter</a>
      </div>
      <?php $render_partners_table( $partners_national ); ?>
    </div>

    <div class="acdc-panel acdc-mb-18">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <div>
          <h3 style="margin:0;">Partenaires locaux — Var / Estérel</h3>
          <p style="font-size:12px;color:#888;margin:4px 0 0;">Cap Emploi 83, MDPH, ESAT, Mission Locale…</p>
        </div>
        <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'psh_partners', 'psh_action' => 'add' ), $base_url ) ); ?>" class="acdc-button acdc-button-soft">+ Ajouter</a>
      </div>
      <?php $render_partners_table( $partners_local ); ?>
    </div>

    <div class="acdc-panel" style="background:#f9f5f0;border:1px solid #e8d8c4;">
      <h4 style="margin-top:0;color:#8b5b23;">Indicateur 26 — Preuve auditeur</h4>
      <p style="font-size:13px;color:#555;margin-bottom:6px;">Cet écran constitue votre preuve documentaire de l'existence d'un réseau de partenaires pour l'accueil des personnes en situation de handicap.</p>
      <p style="font-size:12px;color:#888;margin:0;"><?php echo count( $partners_national ); ?> partenaire(s) national/nationaux · <?php echo count( $partners_local ); ?> partenaire(s) local/locaux</p>
    </div>
    <?php endif; ?>
    <?php
  }

  /* -----------------------------------------------------------------------
   * ACDC 3.24.13 — R-17 : Fiche locaux & équipements (indicateur 17 Qualiopi).
   * Stockage en wp_options (clé acdc_of_training_sites) — pattern identique PSH.
   * Actions routées via paramètres WAF-safe (portal_page_url) ou admin-post.php.
   * ----------------------------------------------------------------------- */

  /**
   * Retourne la liste des sites de formation.
   */
  private function get_training_sites() {
    $raw = get_option( 'acdc_of_training_sites', array() );
    return is_array( $raw ) ? $raw : array();
  }

  /**
   * Statut conformité ind. 17 : 'green' si au moins 1 local enregistré, 'red' sinon.
   */
  private function get_training_sites_compliance_status() {
    return count( $this->get_training_sites() ) > 0 ? 'green' : 'red';
  }

  public function render_front_training_sites_tab() {
    // Interception WAF-safe (front office)
    $ts_post_action = isset( $_REQUEST['ts_post_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['ts_post_action'] ) ) : '';
    $ts_pid_action  = isset( $_REQUEST['ts_pid_action'] )  ? sanitize_key( wp_unslash( $_REQUEST['ts_pid_action'] ) )  : '';

    if ( 'save' === $ts_post_action && ! is_admin() ) {
      $this->handle_save_training_site();
      return;
    }
    if ( 'delete' === $ts_pid_action ) {
      $this->handle_delete_training_site();
      return;
    }

    $ts_action = isset( $_GET['ts_action'] ) ? sanitize_key( wp_unslash( $_GET['ts_action'] ) ) : '';
    $base_url  = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'training_sites' ) );
    $save_url  = is_admin() ? admin_url( 'admin-post.php' ) : $this->portal_page_url( array( 'tab' => 'training_sites', 'ts_post_action' => 'save' ) );
    $sites     = $this->get_training_sites();
    $status    = $this->get_training_sites_compliance_status();
    $dot_color = 'green' === $status ? '#22c55e' : '#ef4444';
    $dot_bg    = 'green' === $status ? '#dff6e5' : '#fee2e2';
    $dot_label = 'green' === $status ? 'Conforme — au moins 1 local enregistré' : 'Non conforme — aucun local enregistré';

    // Édition d'un site existant
    $edit_id   = isset( $_GET['ts_edit'] ) ? sanitize_key( wp_unslash( $_GET['ts_edit'] ) ) : '';
    $edit_site = array();
    if ( $edit_id ) {
      foreach ( $sites as $s ) {
        if ( ( $s['id'] ?? '' ) === $edit_id ) { $edit_site = $s; break; }
      }
    }
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Locaux &amp; équipements</h2>
        <p class="acdc-section-subtitle">Indicateur 17 — Moyens humains et techniques adaptés</p>
      </div>
      <div class="acdc-section-head-actions">
        <span style="display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border-radius:20px;background:<?php echo esc_attr( $dot_bg ); ?>;font-size:13px;font-weight:600;color:#3d3d3d;">
          <span style="width:10px;height:10px;border-radius:50%;background:<?php echo esc_attr( $dot_color ); ?>;display:inline-block;"></span>
          <?php echo esc_html( $dot_label ); ?>
        </span>
      </div>
    </section>

    <?php if ( 'add' === $ts_action || $edit_id ) : ?>
    <div class="acdc-panel">
      <h3><?php echo $edit_id ? 'Modifier le local' : 'Ajouter un local / site de formation'; ?></h3>
      <form method="post" action="<?php echo esc_url( $save_url ); ?>" class="acdc-form">
        <?php if ( is_admin() ) : ?><input type="hidden" name="action" value="acdc_save_training_site"><?php endif; ?>
        <?php wp_nonce_field( 'acdc_save_training_site' ); ?>
        <input type="hidden" name="ts[id]" value="<?php echo esc_attr( $edit_site['id'] ?? '' ); ?>">
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Nom du local <span class="acdc-required">*</span></div>
          <div><input type="text" name="ts[nom]" class="acdc-input" required value="<?php echo esc_attr( $edit_site['nom'] ?? '' ); ?>" placeholder="Ex : Salle de formation Toulon"></div>
          <div class="acdc-contract-label">Adresse</div>
          <div><input type="text" name="ts[adresse]" class="acdc-input" value="<?php echo esc_attr( $edit_site['adresse'] ?? '' ); ?>" placeholder="12 rue des Acacias"></div>
          <div class="acdc-contract-label">Code postal</div>
          <div><input type="text" name="ts[code_postal]" class="acdc-input" value="<?php echo esc_attr( $edit_site['code_postal'] ?? '' ); ?>" placeholder="83000"></div>
          <div class="acdc-contract-label">Ville</div>
          <div><input type="text" name="ts[ville]" class="acdc-input" value="<?php echo esc_attr( $edit_site['ville'] ?? '' ); ?>" placeholder="Toulon"></div>
          <div class="acdc-contract-label">Capacité max. (pers.)</div>
          <div><input type="number" name="ts[capacite]" class="acdc-input" min="1" value="<?php echo esc_attr( $edit_site['capacite'] ?? '' ); ?>" placeholder="12"></div>
          <div class="acdc-contract-label">Accessibilité PMR</div>
          <div>
            <select name="ts[accessibilite_pmr]" class="acdc-input">
              <option value="oui" <?php selected( ( $edit_site['accessibilite_pmr'] ?? '' ), 'oui' ); ?>>Oui</option>
              <option value="partielle" <?php selected( ( $edit_site['accessibilite_pmr'] ?? '' ), 'partielle' ); ?>>Partielle</option>
              <option value="non" <?php selected( ( $edit_site['accessibilite_pmr'] ?? 'non' ), 'non' ); ?>>Non</option>
            </select>
          </div>
          <div class="acdc-contract-label">Équipements disponibles</div>
          <div><textarea name="ts[equipements]" class="acdc-input" rows="3" placeholder="Vidéoprojecteur, tableau blanc, PC stagiaires, connexion WiFi, imprimante…"><?php echo esc_textarea( $edit_site['equipements'] ?? '' ); ?></textarea></div>
          <div class="acdc-contract-label">Notes</div>
          <div><textarea name="ts[notes]" class="acdc-input" rows="2" placeholder="Accès, parking, transport, disponibilités particulières…"><?php echo esc_textarea( $edit_site['notes'] ?? '' ); ?></textarea></div>
        </div>
        <div class="acdc-actions-end acdc-actions-gap-top">
          <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'training_sites' ), $base_url ) ); ?>" class="acdc-button acdc-button-soft">Annuler</a>
          <button type="submit" class="acdc-button acdc-button-primary"><?php echo $edit_id ? 'Modifier le local' : 'Enregistrer le local'; ?></button>
        </div>
      </form>
    </div>

    <?php else : ?>
    <div class="acdc-panel acdc-mb-18">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <div>
          <h3 style="margin:0;">Sites de formation</h3>
          <p style="font-size:12px;color:#888;margin:4px 0 0;">Locaux et équipements mis à disposition des apprenants</p>
        </div>
        <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'training_sites', 'ts_action' => 'add' ), $base_url ) ); ?>" class="acdc-button acdc-button-soft">+ Ajouter un local</a>
      </div>
      <?php if ( ! empty( $sites ) ) : ?>
      <div class="acdc-table-wrap">
        <table class="acdc-table">
          <thead>
            <tr>
              <th>Nom</th><th>Adresse</th><th>Capacité</th><th>PMR</th><th>Équipements</th><th>Notes</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ( $sites as $s ) :
            $sid     = $s['id'] ?? '';
            $pmr_map = array( 'oui' => '✅ Oui', 'partielle' => '⚠️ Partielle', 'non' => '❌ Non' );
            $del_url = wp_nonce_url( add_query_arg( array( 'tab' => 'training_sites', 'ts_pid_action' => 'delete', 'ts_pid' => rawurlencode( $sid ) ), $this->portal_page_url( array( 'tab' => 'training_sites' ) ) ), 'acdc_delete_training_site_' . $sid );
            $edit_url = add_query_arg( array( 'tab' => 'training_sites', 'ts_edit' => rawurlencode( $sid ) ), $base_url );
            ?>
            <tr>
              <td style="font-weight:600;"><?php echo esc_html( $s['nom'] ?? '' ); ?></td>
              <td><?php echo esc_html( trim( ( $s['adresse'] ?? '' ) . ' ' . ( $s['code_postal'] ?? '' ) . ' ' . ( $s['ville'] ?? '' ) ) ?: '—' ); ?></td>
              <td><?php echo ! empty( $s['capacite'] ) ? esc_html( $s['capacite'] ) . ' pers.' : '—'; ?></td>
              <td><?php echo esc_html( $pmr_map[ $s['accessibilite_pmr'] ?? 'non' ] ?? '—' ); ?></td>
              <td style="font-size:12px;color:#555;max-width:220px;"><?php echo esc_html( wp_trim_words( $s['equipements'] ?? '', 10, '…' ) ?: '—' ); ?></td>
              <td style="font-size:12px;color:#888;max-width:160px;"><?php echo esc_html( wp_trim_words( $s['notes'] ?? '', 8, '…' ) ?: '—' ); ?></td>
              <td class="acdc-actions-cell-icons">
                <div class="acdc-groups-actions-inline">
                  <a href="<?php echo esc_url( $edit_url ); ?>" class="acdc-row-action-icon acdc-row-edit-link" data-acdc-iconized="1" title="Modifier" aria-label="Modifier"><?php echo $this->render_inline_icon( 'edit-pencil', 25 ); ?><span class="acdc-action-hub-sr screen-reader-text">Modifier</span></a>
                  <a href="<?php echo esc_url( $del_url ); ?>" class="acdc-row-action-icon acdc-row-delete-link" data-acdc-iconized="1" title="Supprimer" aria-label="Supprimer" onclick="return confirm('Supprimer ce local ?');"><?php echo $this->render_inline_icon( 'trash', 25 ); ?><span class="acdc-action-hub-sr screen-reader-text">Supprimer</span></a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else : ?>
      <p style="color:#888;font-size:14px;margin:0;">Aucun local enregistré. Ajoutez vos locaux de formation pour documenter l'indicateur 17.</p>
      <?php endif; ?>
    </div>

    <div class="acdc-panel" style="background:#f9f5f0;border:1px solid #e8d8c4;">
      <h4 style="margin-top:0;color:#8b5b23;">Indicateur 17 — Preuve auditeur</h4>
      <p style="font-size:13px;color:#555;margin-bottom:6px;">Cet écran documente les moyens matériels et techniques mis à disposition : locaux, équipements, accessibilité PMR.</p>
      <p style="font-size:12px;color:#888;margin:0;"><?php echo count( $sites ); ?> local(aux) enregistré(s)</p>
    </div>
    <?php endif; ?>
    <?php
  }

  /* -----------------------------------------------------------------------
   * ACDC 3.24.14 — R-28 : Registre sous-traitants formels (indicateur 28 Qualiopi).
   * Stockage en wp_options (clé acdc_of_subcontractors) — pattern identique training_sites.
   * Actions routées via paramètres WAF-safe (portal_page_url) ou admin-post.php.
   * ----------------------------------------------------------------------- */

  /**
   * Retourne la liste des sous-traitants.
   */
  private function get_subcontractors() {
    $raw = get_option( 'acdc_of_subcontractors', array() );
    return is_array( $raw ) ? $raw : array();
  }

  /**
   * Statut conformité ind. 28 : 'green' si au moins 1 sous-traitant enregistré, 'red' sinon.
   */
  private function get_subcontractors_compliance_status() {
    return count( $this->get_subcontractors() ) > 0 ? 'green' : 'red';
  }

  public function render_front_subcontractors_tab() {
    global $wpdb;
    // Interception WAF-safe (front office)
    $sc_post_action = isset( $_REQUEST['sc_post_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['sc_post_action'] ) ) : '';
    $sc_pid_action  = isset( $_REQUEST['sc_pid_action'] )  ? sanitize_key( wp_unslash( $_REQUEST['sc_pid_action'] ) )  : '';

    if ( 'save' === $sc_post_action && ! is_admin() ) {
      $this->handle_save_subcontractor();
      return;
    }
    if ( 'delete' === $sc_pid_action ) {
      $this->handle_delete_subcontractor();
      return;
    }

    $sc_action = isset( $_GET['sc_action'] ) ? sanitize_key( wp_unslash( $_GET['sc_action'] ) ) : '';
    $base_url  = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'subcontractors' ) );
    $save_url  = is_admin() ? admin_url( 'admin-post.php' ) : $this->portal_page_url( array( 'tab' => 'subcontractors', 'sc_post_action' => 'save' ) );
    $items     = $this->get_subcontractors();
    $status    = $this->get_subcontractors_compliance_status();
    $dot_color = 'green' === $status ? '#22c55e' : '#ef4444';
    $dot_bg    = 'green' === $status ? '#dff6e5' : '#fee2e2';
    $dot_label = 'green' === $status ? 'Conforme — au moins 1 sous-traitant enregistré' : 'Non conforme — aucun sous-traitant enregistré';

    // Édition d'un sous-traitant existant
    $edit_id   = isset( $_GET['sc_edit'] ) ? sanitize_key( wp_unslash( $_GET['sc_edit'] ) ) : '';
    $edit_item = array();
    if ( $edit_id ) {
      foreach ( $items as $it ) {
        if ( ( $it['id'] ?? '' ) === $edit_id ) { $edit_item = $it; break; }
      }
    }

    $type_labels = array(
      'independant'  => 'Formateur indépendant',
      'cabinet'      => 'Cabinet de formation',
      'organisme'    => 'Organisme spécialisé',
      'autre'        => 'Autre',
    );
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Sous-traitants</h2>
        <p class="acdc-section-subtitle">Indicateur 28 — Recours à des sous-traitants</p>
      </div>
      <div class="acdc-section-head-actions">
        <span style="display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border-radius:20px;background:<?php echo esc_attr( $dot_bg ); ?>;font-size:13px;font-weight:600;color:#3d3d3d;">
          <span style="width:10px;height:10px;border-radius:50%;background:<?php echo esc_attr( $dot_color ); ?>;display:inline-block;"></span>
          <?php echo esc_html( $dot_label ); ?>
        </span>
      </div>
    </section>

    <?php if ( 'add' === $sc_action || $edit_id ) : ?>
    <div class="acdc-panel">
      <h3><?php echo $edit_id ? 'Modifier le sous-traitant' : 'Ajouter un sous-traitant'; ?></h3>
      <form method="post" action="<?php echo esc_url( $save_url ); ?>" class="acdc-form">
        <?php if ( is_admin() ) : ?><input type="hidden" name="action" value="acdc_save_subcontractor"><?php endif; ?>
        <?php wp_nonce_field( 'acdc_save_subcontractor' ); ?>
        <input type="hidden" name="sc[id]" value="<?php echo esc_attr( $edit_item['id'] ?? '' ); ?>">
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Nom / Raison sociale <span class="acdc-required">*</span></div>
          <div><input type="text" name="sc[nom]" class="acdc-input" required value="<?php echo esc_attr( $edit_item['nom'] ?? '' ); ?>" placeholder="Ex : Jean Dupont Conseil"></div>

          <div class="acdc-contract-label">SIRET</div>
          <div><input type="text" name="sc[siret]" class="acdc-input" maxlength="14" value="<?php echo esc_attr( $edit_item['siret'] ?? '' ); ?>" placeholder="14 chiffres"></div>

          <div class="acdc-contract-label">Type</div>
          <div>
            <select name="sc[type]" class="acdc-input">
              <?php foreach ( $type_labels as $val => $lbl ) : ?>
              <option value="<?php echo esc_attr( $val ); ?>" <?php selected( ( $edit_item['type'] ?? 'independant' ), $val ); ?>><?php echo esc_html( $lbl ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="acdc-contract-label">Qualifications / certifications</div>
          <div><textarea name="sc[qualifications]" class="acdc-input" rows="3" placeholder="Qualiopi, certifications métier, domaines d'expertise…"><?php echo esc_textarea( $edit_item['qualifications'] ?? '' ); ?></textarea></div>

          <div class="acdc-contract-label">Début du contrat</div>
          <div><input type="date" name="sc[date_debut]" class="acdc-input" value="<?php echo esc_attr( $edit_item['date_debut'] ?? '' ); ?>"></div>

          <div class="acdc-contract-label">Fin du contrat</div>
          <div><input type="date" name="sc[date_fin]" class="acdc-input" value="<?php echo esc_attr( $edit_item['date_fin'] ?? '' ); ?>"></div>

          <div class="acdc-contract-label">Notes</div>
          <div><textarea name="sc[notes]" class="acdc-input" rows="2" placeholder="Conditions, modalités, observations…"><?php echo esc_textarea( $edit_item['notes'] ?? '' ); ?></textarea></div>
        </div>
        <div class="acdc-actions-end acdc-actions-gap-top">
          <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'subcontractors' ), $base_url ) ); ?>" class="acdc-button acdc-button-soft">Annuler</a>
          <button type="submit" class="acdc-button acdc-button-primary"><?php echo $edit_id ? 'Modifier le sous-traitant' : 'Enregistrer le sous-traitant'; ?></button>
        </div>
      </form>
    </div>

    <?php else : ?>
    <div class="acdc-panel acdc-mb-18">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <div>
          <h3 style="margin:0;">Registre des sous-traitants</h3>
          <p style="font-size:12px;color:#888;margin:4px 0 0;">Formateurs et organismes sous-traitants de votre OF</p>
        </div>
        <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'subcontractors', 'sc_action' => 'add' ), $base_url ) ); ?>" class="acdc-button acdc-button-soft">+ Ajouter un sous-traitant</a>
      </div>
      <?php if ( ! empty( $items ) ) : ?>
      <?php $today = current_time( 'timestamp' ); ?>
      <div class="acdc-table-wrap">
        <table class="acdc-table">
          <thead>
            <tr>
              <th>Nom</th><th>SIRET</th><th>Type</th><th>Qualifications</th><th>Contrat</th><th>Notes</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ( $items as $it ) :
            $iid      = $it['id'] ?? '';
            $date_fin = $it['date_fin'] ?? '';
            $expired  = $date_fin && strtotime( $date_fin ) < $today;
            $del_url  = wp_nonce_url( add_query_arg( array( 'tab' => 'subcontractors', 'sc_pid_action' => 'delete', 'sc_pid' => rawurlencode( $iid ) ), $this->portal_page_url( array( 'tab' => 'subcontractors' ) ) ), 'acdc_delete_subcontractor_' . $iid );
            $edit_url = add_query_arg( array( 'tab' => 'subcontractors', 'sc_edit' => rawurlencode( $iid ) ), $base_url );
            ?>
            <tr>
              <td style="font-weight:600;"><?php echo esc_html( $it['nom'] ?? '' ); ?></td>
              <td style="font-family:monospace;font-size:12px;"><?php echo esc_html( $it['siret'] ?? '—' ); ?></td>
              <td><?php echo esc_html( $type_labels[ $it['type'] ?? 'autre' ] ?? '—' ); ?></td>
              <td style="font-size:12px;color:#555;max-width:200px;"><?php echo esc_html( wp_trim_words( $it['qualifications'] ?? '', 10, '…' ) ?: '—' ); ?></td>
              <td style="font-size:12px;white-space:nowrap;">
                <?php if ( $it['date_debut'] ?? '' ) : ?><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $it['date_debut'] ) ) ); ?><?php endif; ?>
                <?php if ( $date_fin ) : ?>
                  → <span style="<?php echo $expired ? 'color:#dc2626;font-weight:700;' : 'color:#555;'; ?>"><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $date_fin ) ) ); ?><?php echo $expired ? ' ⚠️' : ''; ?></span>
                <?php endif; ?>
                <?php if ( ! ( $it['date_debut'] ?? '' ) && ! $date_fin ) : ?>—<?php endif; ?>
              </td>
              <td style="font-size:12px;color:#888;max-width:160px;"><?php echo esc_html( wp_trim_words( $it['notes'] ?? '', 8, '…' ) ?: '—' ); ?></td>
              <td class="acdc-actions-cell-icons">
                <div class="acdc-groups-actions-inline">
                  <a href="<?php echo esc_url( $edit_url ); ?>" class="acdc-row-action-icon acdc-row-edit-link" data-acdc-iconized="1" title="Modifier" aria-label="Modifier"><?php echo $this->render_inline_icon( 'edit-pencil', 25 ); ?><span class="acdc-action-hub-sr screen-reader-text">Modifier</span></a>
                  <a href="<?php echo esc_url( $del_url ); ?>" class="acdc-row-action-icon acdc-row-delete-link" data-acdc-iconized="1" title="Supprimer" aria-label="Supprimer" onclick="return confirm('Supprimer ce sous-traitant ?');"><?php echo $this->render_inline_icon( 'trash', 25 ); ?><span class="acdc-action-hub-sr screen-reader-text">Supprimer</span></a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else : ?>
      <p style="color:#888;font-size:14px;margin:0;">Aucun sous-traitant enregistré. Ajoutez vos sous-traitants pour documenter l'indicateur 28.</p>
      <?php endif; ?>
    </div>

    <div class="acdc-panel" style="background:#f9f5f0;border:1px solid #e8d8c4;">
      <h4 style="margin-top:0;color:#8b5b23;">Indicateur 28 — Preuve auditeur</h4>
      <p style="font-size:13px;color:#555;margin-bottom:6px;">Cet écran documente le recours à des sous-traitants : identification, qualifications, durée des contrats.</p>
      <p style="font-size:12px;color:#888;margin:0;"><?php echo count( $items ); ?> sous-traitant(s) enregistré(s)</p>
    </div>
    <?php endif; ?>
    <?php
  }

}
