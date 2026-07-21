<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * ACDC Watch Render Trait — v3 (charte plugin)
 * Navigation acdc-subtabs + acdc-button-primary/soft
 * KPIs acdc-kpi-card + acdc-panel
 * Formulaires 40px / radius 10px
 * PHP 7.3 compatible.
 */
trait ACDC_Watch_Render_Trait {

  /* -----------------------------------------------------------------------
   * Routeur principal
   * ----------------------------------------------------------------------- */

  private function render_watch_ia_tab() {
    $subtab = isset( $_GET['watch_sub'] ) ? sanitize_key( wp_unslash( $_GET['watch_sub'] ) ) : 'general';
    if ( ! in_array( $subtab, array( 'general', 'legal', 'metiers', 'pedagogique', 'sources' ), true ) ) {
      $subtab = 'general';
    }
    $this->render_watch_nav( $subtab );
    switch ( $subtab ) {
      case 'legal':       $this->render_watch_axis_view( 'legal' );       break;
      case 'metiers':     $this->render_watch_axis_view( 'metiers' );     break;
      case 'pedagogique': $this->render_watch_axis_view( 'pedagogique' ); break;
      case 'sources':     $this->render_watch_sources_settings_view();    break;
      default:            $this->render_watch_general_view();             break;
    }
    $this->render_watch_exploit_modal();
  }

  /* -----------------------------------------------------------------------
   * Navigation — pattern acdc-subtabs + acdc-button
   * ----------------------------------------------------------------------- */

  private function render_watch_nav( $active ) {
    $base         = $this->portal_page_url( array( 'tab' => 'watch_ia' ) );
    $stats        = $this->get_watch_dashboard_stats();
    $collect_url  = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_watch_trigger_collect' ), 'acdc_watch_trigger_collect' );
    $analyze_url  = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_watch_trigger_analyze' ), 'acdc_watch_trigger_analyze' );
    $new_count    = (int) $stats['total_new'];

    $tabs = array(
      array( 'key' => 'general',     'label' => 'Vue generale',          'badge' => 0 ),
      array( 'key' => 'legal',       'label' => 'Legal — Ind. 23',       'badge' => (int) $stats['axis_legal'] ),
      array( 'key' => 'metiers',     'label' => 'Metier — Ind. 24',      'badge' => (int) $stats['axis_metiers'] ),
      array( 'key' => 'pedagogique', 'label' => 'Pedagogique — Ind. 25', 'badge' => (int) $stats['axis_pedagogique'] ),
      array( 'key' => 'sources',     'label' => 'Sources & Reglages',    'badge' => 0 ),
    );
    ?>
    <section class="acdc-section-head">
      <div>
        <span class="acdc-eyebrow">Qualite &amp; Conformite</span>
        <h2>Veille automatisee IA<?php if ( $new_count ) : ?> <span class="acdc-badge acdc-badge-danger" style="font-size:11px;vertical-align:middle;"><?php echo (int) $new_count; ?> a analyser</span><?php endif; ?></h2>
      </div>
      <div class="acdc-section-head-actions">
        <a href="<?php echo esc_url( $collect_url ); ?>" class="acdc-button acdc-button-soft" data-acdc-no-iconize="1" onclick="return confirm('Lancer la collecte maintenant ?');">Collecter</a>
        <a href="<?php echo esc_url( $analyze_url ); ?>" class="acdc-button acdc-button-soft" data-acdc-no-iconize="1" onclick="return confirm('Lancer l\'analyse IA maintenant ?');">Analyser maintenant</a>
      </div>
    </section>
    <?php $this->render_watch_notice(); ?>
    <div class="acdc-subtabs" style="margin:0 0 24px;display:flex;gap:8px;flex-wrap:wrap;">
      <?php foreach ( $tabs as $t ) :
        $url   = esc_url( add_query_arg( 'watch_sub', $t['key'], $base ) );
        $class = 'acdc-button' . ( $t['key'] === $active ? ' acdc-button-primary' : ' acdc-button-soft' );
      ?>
      <a class="<?php echo esc_attr( $class ); ?>" href="<?php echo $url; ?>"><?php echo esc_html( $t['label'] ); ?><?php if ( $t['badge'] > 0 ) : ?> <span style="display:inline-flex;align-items:center;justify-content:center;background:rgba(0,0,0,.18);color:#fff;border-radius:20px;font-size:10px;font-weight:700;min-width:18px;height:18px;padding:0 5px;margin-left:4px;"><?php echo (int) $t['badge']; ?></span><?php endif; ?></a>
      <?php endforeach; ?>
    </div>
    <?php
  }

  /* -----------------------------------------------------------------------
   * Vue generale
   * ----------------------------------------------------------------------- */

  private function render_watch_general_view() {
    $stats      = $this->get_watch_dashboard_stats();
    $base       = $this->portal_page_url( array( 'tab' => 'watch_ia' ) );
    $no_exploit = empty( $stats['last_exploitation'] ) || strtotime( $stats['last_exploitation'] ) < ( current_time( 'timestamp' ) - 30 * DAY_IN_SECONDS );

    if ( $no_exploit ) : ?>
    <div class="acdc-panel acdc-panel-warning" style="margin-bottom:20px;border-left:4px solid var(--acdc-warning,#f0b45e);">
      <strong>Alerte Qualiopi :</strong> Aucune exploitation depuis plus de 30 jours — risque de non-conformite (critere 6).
      <a href="<?php echo esc_url( add_query_arg( 'watch_sub', 'legal', $base ) ); ?>" class="acdc-button acdc-button-soft" style="height:32px;font-size:12px;margin-left:12px;">Exploiter un article</a>
    </div>
    <?php endif;

    // KPIs — pattern acdc-kpis + acdc-kpi-card
    $kpis = array(
      array( 'val' => $stats['total_new'],        'lbl' => 'A analyser',       'color' => 'var(--acdc-danger,#e06d6d)' ),
      array( 'val' => $stats['total_relevant'],   'lbl' => 'Pertinents',       'color' => 'var(--acdc-warning,#f0b45e)' ),
      array( 'val' => $stats['total_exploited'],  'lbl' => 'Exploités',        'color' => 'var(--acdc-success,#35b37e)' ),
      array( 'val' => $stats['total_month'],      'lbl' => 'Ce mois',          'color' => 'var(--acdc-text-sub,#1e4777)' ),
      array( 'val' => $stats['total_irrelevant'], 'lbl' => 'Non pertinents',   'color' => '#aab4c4' ),
    );
    ?>
    <div class="acdc-kpis" style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:20px;">
      <?php foreach ( $kpis as $kpi ) : ?>
      <div class="acdc-panel acdc-kpi-card" style="padding:18px;border-top:4px solid <?php echo $kpi['color']; ?>;">
        <div class="acdc-kpi-value" style="font-size:26px;font-weight:700;color:<?php echo $kpi['color']; ?>;"><?php echo (int) $kpi['val']; ?></div>
        <div class="acdc-kpi-sub" style="font-size:12px;color:var(--acdc-text,#4b5d76);margin-top:4px;"><?php echo esc_html( $kpi['lbl'] ); ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Axes par indicateur -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:28px;">
      <?php
      $axes = array(
        array( 'key' => 'axis_legal',       'lbl' => 'Ind. 23 — Legal',       'color' => 'var(--acdc-text-sub,#1e4777)', 'sub' => 'legal' ),
        array( 'key' => 'axis_metiers',     'lbl' => 'Ind. 24 — Metier',      'color' => 'var(--acdc-primary,#8b5b23)',  'sub' => 'metiers' ),
        array( 'key' => 'axis_pedagogique', 'lbl' => 'Ind. 25 — Pedagogique', 'color' => 'var(--acdc-success,#35b37e)', 'sub' => 'pedagogique' ),
      );
      foreach ( $axes as $ax ) : ?>
      <a href="<?php echo esc_url( add_query_arg( 'watch_sub', $ax['sub'], $base ) ); ?>" class="acdc-panel" style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center;text-decoration:none;border-left:5px solid <?php echo $ax['color']; ?>;">
        <span style="font-size:13px;font-weight:600;color:var(--acdc-text-strong,#0f2c52);"><?php echo esc_html( $ax['lbl'] ); ?></span>
        <span style="font-size:24px;font-weight:700;color:<?php echo $ax['color']; ?>;"><?php echo (int) $stats[ $ax['key'] ]; ?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Derniers articles pertinents -->
    <?php
    $recent = $this->get_watch_items_list( array( 'status' => 'reviewed', 'limit' => 8, 'order' => 'score' ) );
    ?>
    <div class="acdc-panel acdc-mb-18">
      <div style="padding:14px 18px;background:var(--acdc-table-header,#fbf2e3);border-bottom:1px solid var(--acdc-border-soft,#f0e6dc);">
        <span style="font-size:12px;font-weight:700;color:var(--acdc-eyebrow,#8a6d2a);text-transform:uppercase;letter-spacing:.06em;">Articles les plus pertinents</span>
      </div>
      <?php if ( ! empty( $recent ) ) :
        $this->render_watch_table_header();
        foreach ( $recent as $item ) { $this->render_watch_item_card( $item ); }
        $this->render_watch_table_footer();
      else : ?>
      <div style="padding:24px;text-align:center;color:var(--acdc-text,#4b5d76);font-size:14px;">
        Aucun article analyse. Lancez la collecte puis attendez le cron d'analyse.
      </div>
      <?php endif; ?>
    </div>

    <!-- Export PDF -->
    <div class="acdc-panel acdc-mb-18">
      <div style="padding:14px 18px;background:var(--acdc-table-header,#fbf2e3);border-bottom:1px solid var(--acdc-border-soft,#f0e6dc);">
        <span style="font-size:12px;font-weight:700;color:var(--acdc-eyebrow,#8a6d2a);text-transform:uppercase;letter-spacing:.06em;">Exporter un rapport Qualiopi</span>
      </div>
      <div style="padding:18px;">
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
          <input type="hidden" name="action" value="acdc_watch_export_pdf">
          <?php wp_nonce_field( 'acdc_watch_export_pdf' ); ?>
          <div>
            <label class="acdc-contract-label">Indicateur</label>
            <select name="watch_export_axis" style="height:40px;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;padding:0 12px;font-size:13px;min-width:200px;">
              <option value="legal">Indicateur 23 — Legal</option>
              <option value="metiers">Indicateur 24 — Metier</option>
              <option value="pedagogique">Indicateur 25 — Pedagogique</option>
            </select>
          </div>
          <div>
            <label class="acdc-contract-label">Du</label>
            <input type="date" name="watch_export_from" style="height:40px;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;padding:0 12px;font-size:13px;">
          </div>
          <div>
            <label class="acdc-contract-label">Au</label>
            <input type="date" name="watch_export_to" style="height:40px;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;padding:0 12px;font-size:13px;">
          </div>
          <button type="submit" class="acdc-button acdc-button-primary">Generer PDF</button>
        </form>
      </div>
    </div>
    <?php
  }

  /* -----------------------------------------------------------------------
   * Vue par axe (Legal / Metier / Pedagogique)
   * ----------------------------------------------------------------------- */

  private function render_watch_axis_view( $axis ) {
    $cfgs = array(
      'legal'       => array( 'ind' => '23', 'label' => 'Legal — Indicateur 23',        'color' => 'var(--acdc-text-sub,#1e4777)',  'desc' => 'Evolutions reglementaires, loi formation, CPF, Qualiopi, DREETS' ),
      'metiers'     => array( 'ind' => '24', 'label' => 'Metier — Indicateur 24',        'color' => 'var(--acdc-primary,#8b5b23)',   'desc' => 'Evolutions des metiers et des emplois sur vos secteurs de formation' ),
      'pedagogique' => array( 'ind' => '25', 'label' => 'Pedagogique — Indicateur 25',   'color' => 'var(--acdc-success,#35b37e)',   'desc' => 'Innovations pedagogiques, EdTech, nouvelles modalites et outils' ),
    );
    $cfg            = isset( $cfgs[ $axis ] ) ? $cfgs[ $axis ] : $cfgs['legal'];
    $filter_status  = isset( $_GET['watch_status'] )  ? sanitize_key( wp_unslash( $_GET['watch_status'] ) )  : '';
    $filter_urgency = isset( $_GET['watch_urgency'] ) ? sanitize_key( wp_unslash( $_GET['watch_urgency'] ) ) : '';
    $args           = array( 'axis' => $axis, 'limit' => 60 );
    // Par défaut : n'afficher que les articles analysés ou exploités. Le filtre manuel permet de voir les autres.
    if ( $filter_status ) {
      $args['status'] = $filter_status;
    } else {
      $args['status_in'] = array( 'reviewed', 'exploited' );
    }
    if ( $filter_urgency ) { $args['urgency'] = $filter_urgency; }
    $items   = $this->get_watch_items_list( $args );
    $base    = $this->portal_page_url( array( 'tab' => 'watch_ia' ) );

    global $wpdb;
    $tbl          = $this->get_watch_items_table();
    $nb_total       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl} WHERE watch_axis = %s", $axis ) );
    $nb_reviewed    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl} WHERE watch_axis = %s AND status = 'reviewed'", $axis ) );
    $nb_exploited   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl} WHERE watch_axis = %s AND status = 'exploited'", $axis ) );
    $nb_urgent      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl} WHERE watch_axis = %s AND ai_urgency = 'immediat' AND status = 'reviewed'", $axis ) );
    $nb_irrelevant  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl} WHERE watch_axis = %s AND status = 'irrelevant'", $axis ) );
    $last_exp       = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(exploited_at) FROM {$tbl} WHERE watch_axis = %s AND status = 'exploited'", $axis ) );
    ?>

    <!-- En-tete axe -->
    <div class="acdc-panel acdc-mb-14" style="padding:18px 22px;border-left:5px solid <?php echo $cfg['color']; ?>;">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:14px;">
        <div>
          <div style="font-size:16px;font-weight:700;color:var(--acdc-text-strong,#0f2c52);"><?php echo esc_html( $cfg['label'] ); ?></div>
          <div style="font-size:13px;color:var(--acdc-text,#4b5d76);margin-top:4px;"><?php echo esc_html( $cfg['desc'] ); ?></div>
          <?php if ( $last_exp ) : ?><div style="font-size:12px;color:var(--acdc-success,#35b37e);margin-top:6px;font-weight:500;">Derniere exploitation : <?php echo esc_html( wp_date( 'd/m/Y', strtotime( $last_exp ) ) ); ?></div><?php endif; ?>
        </div>
        <div style="display:flex;gap:18px;align-items:center;flex-wrap:wrap;">
          <?php foreach ( array(
            array( 'v' => $nb_total,      'l' => 'Total',          'c' => 'var(--acdc-text,#4b5d76)' ),
            array( 'v' => $nb_reviewed,   'l' => 'Analyses',       'c' => 'var(--acdc-warning,#f0b45e)' ),
            array( 'v' => $nb_exploited,  'l' => 'Exploités',      'c' => 'var(--acdc-success,#35b37e)' ),
            array( 'v' => $nb_urgent,     'l' => 'Urgents',        'c' => 'var(--acdc-danger,#e06d6d)' ),
            array( 'v' => $nb_irrelevant, 'l' => 'Non pertinents', 'c' => '#aab4c4' ),
          ) as $ms ) : ?>
          <div style="text-align:center;">
            <div style="font-size:22px;font-weight:700;color:<?php echo $ms['c']; ?>;"><?php echo (int) $ms['v']; ?></div>
            <div style="font-size:11px;color:var(--acdc-text,#4b5d76);"><?php echo esc_html( $ms['l'] ); ?></div>
          </div>
          <?php endforeach; ?>
          <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="acdc_watch_export_pdf">
            <input type="hidden" name="watch_export_axis" value="<?php echo esc_attr( $axis ); ?>">
            <?php wp_nonce_field( 'acdc_watch_export_pdf' ); ?>
            <button type="submit" class="acdc-button acdc-button-soft">PDF Ind.<?php echo esc_html( $cfg['ind'] ); ?></button>
          </form>
        </div>
      </div>
    </div>

    <!-- Filtres -->
    <div class="acdc-panel acdc-mb-14" style="padding:12px 18px;">
      <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <input type="hidden" name="tab" value="watch_ia">
        <input type="hidden" name="watch_sub" value="<?php echo esc_attr( $axis ); ?>">
        <select name="watch_status" style="height:40px;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;padding:0 12px;font-size:13px;min-width:140px;">
          <option value="">Tous statuts</option>
          <option value="new" <?php selected( $filter_status, 'new' ); ?>>Nouveau</option>
          <option value="reviewed" <?php selected( $filter_status, 'reviewed' ); ?>>Analyse IA</option>
          <option value="exploited" <?php selected( $filter_status, 'exploited' ); ?>>Exploite</option>
          <option value="archived" <?php selected( $filter_status, 'archived' ); ?>>Archive</option>
          <option value="irrelevant" <?php selected( $filter_status, 'irrelevant' ); ?>>Non pertinent</option>
        </select>
        <select name="watch_urgency" style="height:40px;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;padding:0 12px;font-size:13px;min-width:140px;">
          <option value="">Toute urgence</option>
          <option value="immediat" <?php selected( $filter_urgency, 'immediat' ); ?>>Immediat</option>
          <option value="ce_mois" <?php selected( $filter_urgency, 'ce_mois' ); ?>>Ce mois</option>
          <option value="surveiller" <?php selected( $filter_urgency, 'surveiller' ); ?>>Surveiller</option>
        </select>
        <button type="submit" class="acdc-button acdc-button-soft">Filtrer</button>
        <?php if ( $filter_status || $filter_urgency ) : ?>
        <a href="<?php echo esc_url( add_query_arg( 'watch_sub', $axis, $base ) ); ?>" class="acdc-button acdc-button-soft">Reinitialiser</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Articles -->
    <?php if ( empty( $items ) ) : ?>
    <div class="acdc-panel" style="padding:32px;text-align:center;color:var(--acdc-text,#4b5d76);font-size:14px;">
      Aucun article pour cet axe<?php echo ( $filter_status || $filter_urgency ) ? ' avec ces filtres' : ''; ?>.
      Les sources actives alimenteront cet onglet automatiquement lors de la prochaine collecte.
    </div>
    <?php else : ?>
    <div class="acdc-panel">
      <?php $this->render_watch_table_header();
        foreach ( $items as $item ) { $this->render_watch_item_card( $item ); }
        $this->render_watch_table_footer(); ?>
    </div>
    <?php endif;
  }

  /* -----------------------------------------------------------------------
   * En-tête du tableau articles — appelé une fois avant la boucle
   * ----------------------------------------------------------------------- */

  private function render_watch_table_header() {
    ?>
    <div class="acdc-table-wrap" style="margin-bottom:8px;">
    <table class="acdc-table">
      <thead>
        <tr>
          <th style="width:130px;">Source</th>
          <th style="width:80px;">Date</th>
          <th>Titre &amp; résumé IA</th>
          <th style="width:80px;text-align:center;">Score</th>
          <th style="width:80px;text-align:center;">Urgence</th>
          <th style="width:80px;text-align:center;">Statut</th>
          <th style="width:100px;text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
    <?php
  }

  private function render_watch_table_footer() {
    echo '</tbody></table></div>';
  }

  /* -----------------------------------------------------------------------
   * Ligne tableau article — résumé visible sans ouvrir le site
   * ----------------------------------------------------------------------- */

  private function render_watch_item_card( $item ) {
    $urgency_labels = array( 'immediat' => 'Immédiat', 'ce_mois' => 'Ce mois', 'surveiller' => 'Surveiller' );
    $urgency_colors = array( 'immediat' => '#e06d6d', 'ce_mois' => '#f0b45e', 'surveiller' => '#6e8fb3' );
    $axis_colors    = array( 'legal' => '#1e4777', 'metiers' => '#8b5b23', 'pedagogique' => '#35b37e' );

    $urgency_color = isset( $urgency_colors[ $item->ai_urgency ] ) ? $urgency_colors[ $item->ai_urgency ] : '#4b5d76';
    $urgency_label = isset( $urgency_labels[ $item->ai_urgency ] ) ? $urgency_labels[ $item->ai_urgency ] : '—';
    $axis_color    = isset( $axis_colors[ $item->watch_axis ] ) ? $axis_colors[ $item->watch_axis ] : '#dfe5ee';
    $score         = (int) $item->ai_score;
    $score_color   = $score >= 8 ? '#35b37e' : ( $score >= 6 ? '#f0b45e' : ( $score > 0 ? '#e06d6d' : '#aaa' ) );
    $source_label  = $this->get_watch_source_label( $item->source_id );
    $pub_date      = ! empty( $item->published_at ) && '0000-00-00 00:00:00' !== $item->published_at ? wp_date( 'd/m/Y', strtotime( $item->published_at ) ) : wp_date( 'd/m/Y', strtotime( $item->collected_at ) );

    $nonce_exploit    = wp_create_nonce( 'acdc_watch_exploit' );
    $nonce_archive    = wp_create_nonce( 'acdc_watch_archive' );
    $nonce_irrelevant = wp_create_nonce( 'acdc_watch_irrelevant' );

    // Labels statut
    $status_labels = array( 'new' => 'Nouveau', 'reviewed' => 'Analysé', 'exploited' => 'Exploité', 'archived' => 'Archivé', 'irrelevant' => 'N/P' );
    $status_colors = array( 'new' => 'is-info', 'reviewed' => 'is-warning', 'exploited' => 'is-success', 'archived' => 'is-muted', 'irrelevant' => 'is-muted' );
    $status_label  = isset( $status_labels[ $item->status ] ) ? $status_labels[ $item->status ] : $item->status;
    $status_class  = isset( $status_colors[ $item->status ] ) ? $status_colors[ $item->status ] : 'is-muted';

    // Badge urgence basé sur le score
    $needs_validation = ( $score >= 6 && 'reviewed' === $item->status );
    if ( $score >= 8 ) {
      $score_badge_bg  = '#e06d6d'; $score_badge_lbl = 'Urgent';
    } elseif ( $score >= 6 ) {
      $score_badge_bg  = '#4a90d9'; $score_badge_lbl = 'À valider';
    } else {
      $score_badge_bg  = '#f0b45e'; $score_badge_lbl = 'Impact faible';
    }
    ?>
    <tr data-watch-id="<?php echo (int) $item->id; ?>" style="border-left:3px solid <?php echo esc_attr( $axis_color ); ?>;">

      <!-- Source -->
      <td style="font-size:12px;color:var(--acdc-text,#4b5d76);vertical-align:top;padding-top:14px;">
        <?php echo esc_html( $source_label ); ?>
        <?php if ( 'youtube' === $item->source_type ) : ?><br><span style="font-size:10px;font-weight:700;color:#e06d6d;">YouTube</span><?php endif; ?>
      </td>

      <!-- Date -->
      <td style="font-size:12px;color:var(--acdc-text,#4b5d76);white-space:nowrap;vertical-align:top;padding-top:14px;"><?php echo esc_html( $pub_date ); ?></td>

      <!-- Titre + résumé + implications + action -->
      <td style="vertical-align:top;">
        <a href="<?php echo esc_url( $item->url ); ?>" target="_blank" rel="noopener"
           style="font-size:14px;font-weight:600;color:var(--acdc-text-strong,#0f2c52);text-decoration:none;display:block;margin-bottom:6px;line-height:1.4;"><?php echo esc_html( $item->title ); ?> <span style="font-size:11px;color:#aaa;font-weight:400;">↗</span></a>
        <?php if ( ! empty( $item->ai_summary ) ) : ?>
        <div style="font-size:12px;color:var(--acdc-text,#4b5d76);line-height:1.6;margin-bottom:6px;padding:8px 10px;background:var(--acdc-soft-bg,#fbf8f7);border:1px solid var(--acdc-border-soft,#f0e6dc);border-radius:8px;">
          <?php echo nl2br( esc_html( $item->ai_summary ) ); ?>
        </div>
        <?php endif; ?>
        <?php if ( ! empty( $item->ai_action ) ) : ?>
        <div style="font-size:11px;color:#35b37e;font-weight:500;">
          <strong style="color:var(--acdc-eyebrow,#8a6d2a);">Action :</strong> <?php echo esc_html( $item->ai_action ); ?>
        </div>
        <?php endif; ?>
        <?php if ( 'exploited' === $item->status && ! empty( $item->exploitation_note ) ) : ?>
        <div style="font-size:11px;color:var(--acdc-text,#4b5d76);margin-top:4px;font-style:italic;"><?php echo esc_html( wp_trim_words( $item->exploitation_note, 15, '…' ) ); ?></div>
        <?php endif; ?>
      </td>

      <!-- Score -->
      <td style="text-align:center;vertical-align:top;padding-top:14px;">
        <?php if ( $score > 0 ) : ?>
        <span style="font-size:18px;font-weight:700;color:<?php echo esc_attr( $score_color ); ?>;"><?php echo $score; ?></span>
        <span style="font-size:10px;color:var(--acdc-text,#4b5d76);display:block;">/10</span>
        <?php else : ?><span style="color:#aaa;font-size:12px;">—</span><?php endif; ?>
      </td>

      <!-- Urgence — badge basé sur le score -->
      <td style="text-align:center;vertical-align:top;padding-top:14px;">
        <?php if ( $score > 0 && 'exploited' !== $item->status && 'archived' !== $item->status ) : ?>
        <span style="display:inline-block;background:<?php echo esc_attr( $score_badge_bg ); ?>;color:#fff;font-size:10px;font-weight:700;padding:3px 8px;border-radius:20px;white-space:nowrap;"><?php echo esc_html( $score_badge_lbl ); ?></span>
        <?php elseif ( $item->ai_urgency && 'exploited' !== $item->status ) : ?>
        <span class="acdc-status-pill" style="background:<?php echo esc_attr( $urgency_color ); ?>;color:#fff;font-size:10px;font-weight:600;padding:2px 8px;border-radius:20px;white-space:nowrap;"><?php echo esc_html( $urgency_label ); ?></span>
        <?php else : ?><span style="color:#aaa;font-size:12px;">—</span><?php endif; ?>
      </td>

      <!-- Statut -->
      <td style="text-align:center;vertical-align:top;padding-top:14px;">
        <span class="acdc-status-dot <?php echo esc_attr( $status_class ); ?>" style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:500;">
          <span class="dot"></span><?php echo esc_html( $status_label ); ?>
        </span>
      </td>

      <!-- Actions -->
      <td style="text-align:right;vertical-align:top;padding-top:10px;white-space:nowrap;">
        <?php if ( 'exploited' !== $item->status && 'archived' !== $item->status && 'irrelevant' !== $item->status ) : ?>
        <?php if ( $needs_validation ) : ?>
        <a href="javascript:void(0)" class="acdc-watch-exploit-btn acdc-watch-validate-alert"
          data-id="<?php echo (int) $item->id; ?>"
          data-note="<?php echo esc_attr( (string) $item->ai_note ); ?>"
          data-action="<?php echo esc_attr( (string) $item->ai_action ); ?>"
          data-formation-id="<?php echo (int) $item->ai_formation_id; ?>"
          data-title="<?php echo esc_attr( $item->title ); ?>"
          data-nonce="<?php echo esc_attr( $nonce_exploit ); ?>"
          title="À valider — cliquez pour exploiter" style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;background:#e06d6d;border-radius:50%;color:#fff;font-size:14px;font-weight:700;text-decoration:none;margin-right:4px;" data-acdc-no-iconize="1">!</a>
        <?php endif; ?>
        <a href="javascript:void(0)" class="acdc-row-action-icon acdc-watch-exploit-btn"
          data-id="<?php echo (int) $item->id; ?>"
          data-note="<?php echo esc_attr( (string) $item->ai_note ); ?>"
          data-action="<?php echo esc_attr( (string) $item->ai_action ); ?>"
          data-formation-id="<?php echo (int) $item->ai_formation_id; ?>"
          data-title="<?php echo esc_attr( $item->title ); ?>"
          data-nonce="<?php echo esc_attr( $nonce_exploit ); ?>"
          title="Exploiter (Qualiopi)" aria-label="Exploiter cet article"><?php echo $this->render_inline_icon( 'clipboard', 25 ); ?><span class="screen-reader-text">Exploiter</span></a>
        <a href="javascript:void(0)" class="acdc-row-action-icon acdc-watch-archive-btn"
          data-id="<?php echo (int) $item->id; ?>"
          data-nonce="<?php echo esc_attr( $nonce_archive ); ?>"
          title="Archiver" aria-label="Archiver cet article"><?php echo $this->render_inline_icon( 'archive', 25 ); ?><span class="screen-reader-text">Archiver</span></a>
        <a href="javascript:void(0)" class="acdc-row-action-icon acdc-row-delete-link acdc-watch-irrelevant-btn"
          data-id="<?php echo (int) $item->id; ?>"
          data-nonce="<?php echo esc_attr( $nonce_irrelevant ); ?>"
          title="Non pertinent" aria-label="Marquer non pertinent"><?php echo $this->render_inline_icon( 'trash', 25 ); ?><span class="screen-reader-text">Non pertinent</span></a>
        <?php else : ?>
        <span style="font-size:11px;color:#aaa;">—</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php
  }

  /* -----------------------------------------------------------------------
   * Modale exploitation Qualiopi — pattern acdc-modal
   * ----------------------------------------------------------------------- */

  private function render_watch_exploit_modal() {
    global $wpdb;
    $formations = $wpdb->get_results( "SELECT id, title FROM {$this->formation_table} WHERE is_draft = 0 AND is_active = 1 ORDER BY title ASC" );
    ?>
    <div id="acdc-watch-exploit-modal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(15,44,82,.45);overflow-y:auto;">
      <div style="background:#fff;border-radius:10px;max-width:640px;margin:60px auto;box-shadow:0 10px 30px rgba(28,44,64,.12);position:relative;">
        <div style="padding:22px 28px;border-bottom:1px solid var(--acdc-border-soft,#f0e6dc);display:flex;align-items:center;justify-content:space-between;">
          <h3 style="margin:0;font-size:18px;color:var(--acdc-text-strong,#0f2c52);">Exploiter — Preuve Qualiopi</h3>
          <button type="button" id="acdc-watch-modal-close" style="background:none;border:none;font-size:22px;cursor:pointer;color:var(--acdc-text,#4b5d76);line-height:1;">&times;</button>
        </div>
        <div style="padding:22px 28px;">
          <div id="exploit-article-title" style="font-size:13px;color:var(--acdc-text,#4b5d76);margin-bottom:18px;font-style:italic;padding:10px 14px;background:var(--acdc-soft-bg,#fbf8f7);border-radius:10px;border:1px solid var(--acdc-border-soft,#f0e6dc);"></div>
          <form id="acdc-watch-exploit-form" style="display:flex;flex-direction:column;gap:16px;">
            <input type="hidden" id="exploit_item_id" name="item_id" value="">
            <div>
              <label class="acdc-contract-label">Note d'exploitation <em style="color:var(--acdc-danger,#e06d6d);">*</em></label>
              <p style="font-size:12px;color:var(--acdc-text,#4b5d76);margin:0 0 6px;">Decrivez l'impact concret sur votre organisme. C'est votre preuve d'audit.</p>
              <textarea id="exploit_note" name="exploitation_note" rows="4" style="width:100%;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;padding:10px 12px;font-size:13px;resize:vertical;font-family:inherit;" placeholder="Ex : Cet article m'alerte sur le nouveau decret CPF. J'ai mis a jour les mentions sur nos conventions..."></textarea>
            </div>
            <div>
              <label class="acdc-contract-label">Action engagée <em style="color:var(--acdc-danger,#e06d6d);">*</em></label>
              <textarea id="exploit_action" name="action_taken" rows="2" style="width:100%;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;padding:10px 12px;font-size:13px;resize:vertical;font-family:inherit;" placeholder="Ex : Mise a jour du programme, information des apprenants..."></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
              <div>
                <label class="acdc-contract-label">Formation impactée</label>
                <select name="formation_id" id="exploit_formation" style="width:100%;height:40px;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;padding:0 12px;font-size:13px;">
                  <option value="">— Aucune —</option>
                  <?php foreach ( $formations as $f ) : ?><option value="<?php echo (int) $f->id; ?>"><?php echo esc_html( $f->title ); ?></option><?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="acdc-contract-label">Entrée en vigueur</label>
                <input type="date" name="effect_date" id="exploit_effect_date" style="width:100%;height:40px;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;padding:0 12px;font-size:13px;">
              </div>
            </div>
            <div style="display:flex;gap:12px;align-items:center;padding:12px 14px;background:var(--acdc-soft-bg,#fbf8f7);border:1px solid var(--acdc-border-soft,#f0e6dc);border-radius:10px;">
              <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;font-weight:500;color:var(--acdc-text-strong,#0f2c52);">
                <input type="checkbox" id="exploit_diffused" name="diffused" value="1"> Diffuse en interne
              </label>
              <input type="text" id="exploit_diffusion_note" name="diffusion_note" placeholder="A qui / comment..." style="flex:1;height:40px;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;padding:0 12px;font-size:13px;">
            </div>
          </form>
        </div>
        <div style="padding:16px 28px;border-top:1px solid var(--acdc-border-soft,#f0e6dc);display:flex;justify-content:flex-end;gap:10px;">
          <button type="button" id="acdc-watch-modal-cancel" class="acdc-button acdc-button-soft">Annuler</button>
          <button type="button" id="acdc-watch-exploit-submit" class="acdc-button acdc-button-primary">Valider et enregistrer</button>
        </div>
      </div>
    </div>

    <script>
    (function(){
      var _ajaxUrl=typeof ajaxurl!=='undefined'?ajaxurl:(typeof AcdcUiKernelSettings!=='undefined'?AcdcUiKernelSettings.ajaxUrl:'/wp-admin/admin-ajax.php');
      var modal=document.getElementById('acdc-watch-exploit-modal');
      if(!modal){return;}
      function closeModal(){modal.style.display='none';}
      document.getElementById('acdc-watch-modal-close').addEventListener('click',closeModal);
      document.getElementById('acdc-watch-modal-cancel').addEventListener('click',closeModal);
      modal.addEventListener('click',function(e){if(e.target===modal){closeModal();}});

      document.querySelectorAll('.acdc-watch-exploit-btn').forEach(function(btn){
        btn.addEventListener('click',function(){
          document.getElementById('exploit_item_id').value=this.dataset.id;
          document.getElementById('exploit_note').value=this.dataset.note||'';
          document.getElementById('exploit_action').value=this.dataset.action||'';
          document.getElementById('exploit-article-title').textContent=this.dataset.title||'';
          // Pré-remplir formation impactée
          var fid=this.dataset.formationId||'';
          var sel=document.getElementById('exploit_formation');
          if(sel&&fid){sel.value=fid;}else if(sel){sel.value='';}
          // Pré-remplir date du jour
          var today=new Date();
          var dd=String(today.getDate()).padStart(2,'0');
          var mm=String(today.getMonth()+1).padStart(2,'0');
          document.getElementById('exploit_effect_date').value=today.getFullYear()+'-'+mm+'-'+dd;
          document.getElementById('exploit_diffused').checked=false;
          document.getElementById('exploit_diffusion_note').value='';
          modal.style.display='block';
        });
      });

      document.getElementById('acdc-watch-exploit-submit').addEventListener('click',function(){
        var btn=this;
        if(!document.getElementById('exploit_note').value.trim()){document.getElementById('exploit_note').focus();return;}
        if(!document.getElementById('exploit_action').value.trim()){document.getElementById('exploit_action').focus();return;}
        btn.disabled=true;btn.textContent='Enregistrement...';
        var id=document.getElementById('exploit_item_id').value;
        var nonceBtn=document.querySelector('.acdc-watch-exploit-btn[data-id="'+id+'"]');
        var nonceVal=nonceBtn?nonceBtn.dataset.nonce:'';
        var fd=new FormData(document.getElementById('acdc-watch-exploit-form'));
        fd.append('action','acdc_of_watch_exploit');fd.append('_nonce',nonceVal);
        fetch(_ajaxUrl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(res){
          if(res.success){
            var card=document.querySelector('[data-watch-id="'+id+'"]');
            if(card){card.style.opacity='.3';card.style.pointerEvents='none';}
            closeModal();
          }else{alert('Erreur : '+(res.data||'inconnue'));btn.disabled=false;btn.textContent='Valider et enregistrer';}
        }).catch(function(){btn.disabled=false;btn.textContent='Valider et enregistrer';});
      });

      document.querySelectorAll('.acdc-watch-archive-btn').forEach(function(btn){
        btn.addEventListener('click',function(){
          if(!confirm('Archiver cet article ?')){return;}
          var id=this.dataset.id,n=this.dataset.nonce;
          var card=document.querySelector('[data-watch-id="'+id+'"]');
          var fd=new FormData();fd.append('action','acdc_of_watch_archive');fd.append('_nonce',n);fd.append('item_id',id);
          fetch(_ajaxUrl,{method:'POST',body:fd}).then(function(){if(card){card.style.display='none';}});
        });
      });

      document.querySelectorAll('.acdc-watch-irrelevant-btn').forEach(function(btn){
        btn.addEventListener('click',function(){
          if(!confirm('Marquer comme non pertinent ?')){return;}
          var id=this.dataset.id,n=this.dataset.nonce;
          var card=document.querySelector('[data-watch-id="'+id+'"]');
          var fd=new FormData();fd.append('action','acdc_of_watch_irrelevant');fd.append('_nonce',n);fd.append('item_id',id);
          fetch(_ajaxUrl,{method:'POST',body:fd}).then(function(){if(card){card.style.display='none';}});
        });
      });
    }());
    </script>
    <?php
  }

  /* -----------------------------------------------------------------------
   * Sources & Reglages — layout 2 colonnes acdc-contract-grid
   * ----------------------------------------------------------------------- */

  private function render_watch_sources_settings_view() {
    $settings_url = admin_url( 'admin-post.php' );
    $sources      = $this->get_ia_watch_sources();
    $nonce_test   = wp_create_nonce( 'acdc_watch_settings' );
    $axis_colors  = array( 'legal' => 'var(--acdc-text-sub,#1e4777)', 'metiers' => 'var(--acdc-primary,#8b5b23)', 'pedagogique' => 'var(--acdc-success,#35b37e)' );
    $axis_labels  = array( 'legal' => '23', 'metiers' => '24', 'pedagogique' => '25' );
    $api_fields   = array(
      array( 'opt' => 'acdc_of_watch_api_openai',     'lbl' => 'OpenAI (GPT-4o)',    'prov' => 'openai',     'help' => 'Score + resume de chaque article' ),
      array( 'opt' => 'acdc_of_watch_api_anthropic',  'lbl' => 'Anthropic (Claude)', 'prov' => 'anthropic',  'help' => 'Analyse approfondie + action ACDC' ),
      array( 'opt' => 'acdc_of_watch_api_gemini',     'lbl' => 'Google Gemini',      'prov' => 'gemini',     'help' => 'Resume des videos YouTube' ),
      array( 'opt' => 'acdc_of_watch_api_perplexity', 'lbl' => 'Perplexity',         'prov' => 'perplexity', 'help' => 'Verification reglementaire axe legal' ),
      array( 'opt' => 'acdc_of_watch_api_youtube',    'lbl' => 'YouTube Data v3',    'prov' => 'youtube',    'help' => 'Collecte des videos des chaines' ),
    );
    ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;align-items:flex-start;">

    <!-- Sources de collecte -->
    <div>
      <div class="acdc-panel acdc-mb-18">
        <div style="padding:14px 18px;background:var(--acdc-table-header,#fbf2e3);border-bottom:1px solid var(--acdc-border-soft,#f0e6dc);">
          <span style="font-size:12px;font-weight:700;color:var(--acdc-eyebrow,#8a6d2a);text-transform:uppercase;letter-spacing:.06em;">Sources de collecte</span>
        </div>
        <!-- Onglets axes -->
        <div style="display:flex;gap:0;border-bottom:1px solid var(--acdc-border,#dfe5ee);padding:0 18px;">
          <?php foreach ( array( 'legal' => 'Légal — Ind.23', 'metiers' => 'Métier — Ind.24', 'pedagogique' => 'Pédago — Ind.25' ) as $ax => $axlbl ) : ?>
          <button type="button" class="acdc-watch-src-tab" data-axis="<?php echo esc_attr( $ax ); ?>"
            style="background:none;border:none;border-bottom:2px solid transparent;padding:10px 14px;font-size:12px;font-weight:600;cursor:pointer;color:var(--acdc-text,#4b5d76);white-space:nowrap;">
            <?php echo esc_html( $axlbl ); ?>
            <span class="acdc-watch-src-count" data-axis="<?php echo esc_attr( $ax ); ?>" style="background:var(--acdc-border,#dfe5ee);color:var(--acdc-text,#4b5d76);font-size:10px;border-radius:20px;padding:1px 6px;margin-left:4px;">
              <?php echo (int) count( array_filter( $sources, function( $s ) use ( $ax ) { return isset( $s['axis'] ) && $s['axis'] === $ax; } ) ); ?>
            </span>
          </button>
          <?php endforeach; ?>
        </div>
        <form method="post" action="<?php echo esc_url( $settings_url ); ?>">
          <input type="hidden" name="action" value="acdc_save_watch_sources">
          <?php wp_nonce_field( 'acdc_save_watch_sources' ); ?>
          <?php foreach ( array( 'legal', 'metiers', 'pedagogique' ) as $ax ) : ?>
          <div class="acdc-watch-src-panel" data-axis="<?php echo esc_attr( $ax ); ?>" style="display:none;padding:4px 0;">
            <?php
            $ax_sources = array_values( array_filter( $sources, function( $s ) use ( $ax ) { return isset( $s['axis'] ) && $s['axis'] === $ax; } ) );
            foreach ( $sources as $i => $s ) :
              if ( ! isset( $s['axis'] ) || $s['axis'] !== $ax ) continue;
              $ac = isset( $axis_colors[ $s['axis'] ] ) ? $axis_colors[ $s['axis'] ] : 'var(--acdc-text,#4b5d76)';
            ?>
            <div class="acdc-watch-src-row" style="padding:10px 18px;border-bottom:1px solid var(--acdc-bg,#f4f5f7);">
              <input type="hidden" name="watch_sources[<?php echo (int) $i; ?>][id]" value="<?php echo esc_attr( $s['id'] ); ?>">
              <input type="hidden" name="watch_sources[<?php echo (int) $i; ?>][type]" value="<?php echo esc_attr( $s['type'] ); ?>">
              <input type="hidden" name="watch_sources[<?php echo (int) $i; ?>][axis]" value="<?php echo esc_attr( $s['axis'] ); ?>">
              <input type="hidden" class="acdc-src-delete-flag" name="watch_sources[<?php echo (int) $i; ?>][delete]" value="0">
              <div style="display:flex;gap:8px;align-items:center;margin-bottom:6px;">
                <label class="acdc-switch" style="transform:scale(.8);transform-origin:left center;cursor:pointer;">
                  <input type="checkbox" name="watch_sources[<?php echo (int) $i; ?>][active]" value="1" <?php checked( ! empty( $s['active'] ) ); ?>>
                  <span class="acdc-switch-slider"></span>
                </label>
                <input type="text" name="watch_sources[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $s['label'] ); ?>" placeholder="Libellé" style="flex:1;height:36px;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;padding:0 10px;font-size:12px;">
                <button type="button" class="acdc-watch-src-delete" title="Supprimer cette source"
                  style="flex:0 0 auto;width:32px;height:32px;border:none;background:none;cursor:pointer;color:var(--acdc-danger,#e53e3e);font-size:18px;line-height:1;border-radius:8px;display:flex;align-items:center;justify-content:center;"
                  data-acdc-no-iconize="1">✕</button>
              </div>
              <input type="<?php echo 'youtube' === $s['type'] ? 'text' : 'url'; ?>" name="watch_sources[<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr( $s['url'] ); ?>" placeholder="<?php echo 'youtube' === $s['type'] ? 'URL youtube.com/@handle ou channel ID (UC...)' : 'https://.../feed/'; ?>" style="width:100%;height:36px;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;padding:0 10px;font-size:12px;color:var(--acdc-text,#4b5d76);">
            </div>
            <?php endforeach; ?>
            <!-- Ajouter une source dans cet axe -->
            <div style="padding:14px 18px;background:var(--acdc-soft-bg,#fbf8f7);border-top:1px solid var(--acdc-border-soft,#f0e6dc);">
              <div style="font-size:12px;font-weight:600;color:var(--acdc-text-strong,#0f2c52);margin-bottom:10px;">Ajouter une source</div>
              <div style="display:flex;flex-direction:column;gap:8px;">
                <input type="text" name="new_source_label_<?php echo esc_attr( $ax ); ?>" placeholder="Libellé de la source" style="height:40px;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;padding:0 12px;font-size:13px;">
                <select name="new_source_type_<?php echo esc_attr( $ax ); ?>" style="height:40px;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;font-size:13px;padding:0 12px;">
                  <option value="rss">RSS / Atom</option>
                  <option value="youtube">YouTube</option>
                </select>
                <input type="text" name="new_source_url_<?php echo esc_attr( $ax ); ?>" placeholder="URL du flux RSS ou URL/handle YouTube" style="height:40px;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;padding:0 12px;font-size:13px;">
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <div style="padding:14px 18px;border-top:1px solid var(--acdc-border,#dfe5ee);">
            <button type="submit" class="acdc-button acdc-button-primary" data-acdc-no-iconize="1" style="width:100%;">Enregistrer les sources</button>
          </div>
        </form>
      </div>
    </div>
    <script>
    (function(){
      function initWatchSrcTabs() {
        var tabs = document.querySelectorAll('.acdc-watch-src-tab');
        var panels = document.querySelectorAll('.acdc-watch-src-panel');
        if (!tabs.length) return;
        function activate(axis) {
          tabs.forEach(function(t) {
            var active = t.dataset.axis === axis;
            t.style.borderBottomColor = active ? 'var(--acdc-primary,#8b5b23)' : 'transparent';
            t.style.color = active ? 'var(--acdc-primary,#8b5b23)' : 'var(--acdc-text,#4b5d76)';
          });
          panels.forEach(function(p) {
            p.style.display = p.dataset.axis === axis ? 'block' : 'none';
          });
        }
        tabs.forEach(function(t) {
          t.addEventListener('click', function() { activate(t.dataset.axis); });
        });
        activate('legal');
        // Boutons supprimer
        document.querySelectorAll('.acdc-watch-src-delete').forEach(function(btn) {
          btn.addEventListener('click', function() {
            if (!confirm('Supprimer cette source ?')) return;
            var row = btn.closest('.acdc-watch-src-row');
            var flag = row.querySelector('.acdc-src-delete-flag');
            if (flag) flag.value = '1';
            row.style.opacity = '0.3';
            row.style.pointerEvents = 'none';
            row.querySelectorAll('input,select').forEach(function(el) {
              if (el.type !== 'hidden') el.disabled = true;
            });
          });
        });
      }
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWatchSrcTabs);
      } else {
        initWatchSrcTabs();
      }
    })();
    </script>

    <!-- Cles API + Parametres -->
    <div>
      <div class="acdc-panel acdc-mb-18">
        <div style="padding:14px 18px;background:var(--acdc-table-header,#fbf2e3);border-bottom:1px solid var(--acdc-border-soft,#f0e6dc);">
          <span style="font-size:12px;font-weight:700;color:var(--acdc-eyebrow,#8a6d2a);text-transform:uppercase;letter-spacing:.06em;">Cles API</span>
        </div>
        <form method="post" action="<?php echo esc_url( $settings_url ); ?>">
          <input type="hidden" name="action" value="acdc_save_watch_settings">
          <?php wp_nonce_field( 'acdc_save_watch_settings' ); ?>
          <div style="padding:14px 18px;display:flex;flex-direction:column;gap:16px;">
            <?php foreach ( $api_fields as $af ) : $val = (string) $this->acdc_secret_decrypt( get_option( $af['opt'], '' ) ); ?>
            <div>
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px;">
                <label class="acdc-contract-label" style="margin:0;"><?php echo esc_html( $af['lbl'] ); ?></label>
                <div style="display:flex;align-items:center;gap:8px;">
                  <span id="acdc-test-result-<?php echo esc_attr( $af['prov'] ); ?>" style="font-size:12px;font-weight:600;min-width:60px;text-align:center;"></span>
                  <button type="button" class="acdc-watch-test-key acdc-button acdc-button-soft"
                    style="height:32px;font-size:12px;padding:0 14px;"
                    data-acdc-no-iconize="1"
                    data-provider="<?php echo esc_attr( $af['prov'] ); ?>"
                    data-nonce="<?php echo esc_attr( $nonce_test ); ?>"
                    data-field="<?php echo esc_attr( $af['opt'] ); ?>"
                    data-result-id="acdc-test-result-<?php echo esc_attr( $af['prov'] ); ?>">Tester</button>
                </div>
              </div>
              <p style="font-size:11px;color:var(--acdc-text,#4b5d76);margin:0 0 4px;"><?php echo esc_html( $af['help'] ); ?></p>
              <input type="password" name="<?php echo esc_attr( $af['opt'] ); ?>" value="<?php echo esc_attr( $val ); ?>" placeholder="Coller la cle ici — ne jamais taper manuellement" style="width:100%;height:40px;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;padding:0 12px;font-size:13px;" autocomplete="off">
            </div>
            <?php endforeach; ?>
          </div>
          <div style="padding:0 18px 14px;border-top:1px solid var(--acdc-border-soft,#f0e6dc);">
            <div style="margin-top:14px;font-size:12px;font-weight:700;color:var(--acdc-text-strong,#0f2c52);text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px;">Parametres</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div>
                <label class="acdc-contract-label">Score min Claude (0-10)</label>
                <input type="number" name="acdc_of_watch_min_score" value="<?php echo (int) get_option( 'acdc_of_watch_min_score', 6 ); ?>" min="0" max="10" style="width:100%;height:40px;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;padding:0 12px;font-size:13px;">
              </div>
              <div>
                <label class="acdc-contract-label">Articles par lot IA</label>
                <input type="number" name="acdc_of_watch_ai_batch" value="<?php echo (int) get_option( 'acdc_of_watch_ai_batch', 20 ); ?>" min="1" max="100" style="width:100%;height:40px;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;padding:0 12px;font-size:13px;">
              </div>
              <div>
                <label class="acdc-contract-label">Max articles par source</label>
                <input type="number" name="acdc_of_watch_max_per_source" value="<?php echo (int) get_option( 'acdc_of_watch_max_per_source', 10 ); ?>" min="1" max="50" style="width:100%;height:40px;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;padding:0 12px;font-size:13px;">
              </div>
              <div>
                <label class="acdc-contract-label">Email digest hebdo</label>
                <input type="email" name="acdc_of_watch_digest_email" value="<?php echo esc_attr( get_option( 'acdc_of_watch_digest_email', get_option( 'admin_email' ) ) ); ?>" style="width:100%;height:40px;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;padding:0 12px;font-size:13px;">
              </div>
            </div>
            <!-- Planification collecte -->
            <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--acdc-border-soft,#f0e6dc);">
              <div style="font-size:12px;font-weight:700;color:var(--acdc-text-strong,#0f2c52);text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px;">Planification collecte automatique</div>
              <?php
                $freq    = get_option( 'acdc_of_watch_collect_frequency', 'weekly' );
                $c_day   = (int) get_option( 'acdc_of_watch_collect_day', 1 );
                $c_hour  = (int) get_option( 'acdc_of_watch_collect_hour', 8 );
                $next_ts = wp_next_scheduled( 'acdc_of_watch_collect_cron' );
                $next_lbl = $next_ts ? wp_date( 'd/m/Y à H\hi', $next_ts ) : 'Non planifié';
              ?>
              <p style="font-size:11px;color:var(--acdc-text,#4b5d76);margin:0 0 10px;">
                Prochaine collecte : <strong><?php echo esc_html( $next_lbl ); ?></strong>
              </p>
              <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
                <div>
                  <label class="acdc-contract-label">Fréquence</label>
                  <select name="acdc_of_watch_collect_frequency" style="width:100%;height:40px;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;font-size:13px;padding:0 12px;">
                    <option value="daily"       <?php selected( $freq, 'daily' ); ?>>Quotidien</option>
                    <option value="weekly"      <?php selected( $freq, 'weekly' ); ?>>Hebdomadaire</option>
                    <option value="fortnightly" <?php selected( $freq, 'fortnightly' ); ?>>Quinzaine</option>
                    <option value="monthly"     <?php selected( $freq, 'monthly' ); ?>>Mensuel</option>
                  </select>
                </div>
                <div>
                  <label class="acdc-contract-label">Jour (hebdo/quinzaine)</label>
                  <select name="acdc_of_watch_collect_day" style="width:100%;height:40px;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;font-size:13px;padding:0 12px;">
                    <option value="1" <?php selected( $c_day, 1 ); ?>>Lundi</option>
                    <option value="2" <?php selected( $c_day, 2 ); ?>>Mardi</option>
                    <option value="3" <?php selected( $c_day, 3 ); ?>>Mercredi</option>
                    <option value="4" <?php selected( $c_day, 4 ); ?>>Jeudi</option>
                    <option value="5" <?php selected( $c_day, 5 ); ?>>Vendredi</option>
                    <option value="6" <?php selected( $c_day, 6 ); ?>>Samedi</option>
                    <option value="0" <?php selected( $c_day, 0 ); ?>>Dimanche</option>
                  </select>
                </div>
                <div>
                  <label class="acdc-contract-label">Heure</label>
                  <select name="acdc_of_watch_collect_hour" style="width:100%;height:40px;border:1px solid var(--acdc-border,#dfe5ee);border-radius:10px;font-size:13px;padding:0 12px;">
                    <?php for ( $h = 0; $h < 24; $h++ ) : ?>
                    <option value="<?php echo $h; ?>" <?php selected( $c_hour, $h ); ?>><?php echo sprintf( '%02dh00', $h ); ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
              </div>
            </div>
          </div>
          <div style="padding:14px 18px;border-top:1px solid var(--acdc-border,#dfe5ee);">
            <button type="submit" class="acdc-button acdc-button-primary" style="width:100%;">Enregistrer les reglages</button>
          </div>
        </form>
      </div>
    </div>

    </div><!-- /grid -->

    <script>
    (function(){
      var _ajaxUrl=typeof ajaxurl!=='undefined'?ajaxurl:(typeof AcdcUiKernelSettings!=='undefined'?AcdcUiKernelSettings.ajaxUrl:'/wp-admin/admin-ajax.php');
      document.querySelectorAll('.acdc-watch-test-key').forEach(function(btn){
        btn.addEventListener('click',function(){
          var prov=this.dataset.provider,nonce=this.dataset.nonce,field=this.dataset.field;
          var key=document.querySelector('[name="'+field+'"]').value,t=this;
          if(!key){alert('Entrez la cle avant de tester.');return;}
          var resultEl=this.dataset.resultId?document.getElementById(this.dataset.resultId):null;
          t.textContent='...';t.disabled=true;
          if(resultEl){resultEl.textContent='';resultEl.style.color='';}
          var fd=new FormData();
          fd.append('action','acdc_of_watch_test_api_key');fd.append('_nonce',nonce);
          fd.append('provider',prov);fd.append('api_key',key);
          fetch(_ajaxUrl,{method:'POST',body:fd})
            .then(function(r){return r.json();})
            .then(function(res){
              t.textContent='Tester';t.disabled=false;
              if(resultEl){resultEl.textContent=res.success?'OK':'Erreur';resultEl.style.color=res.success?'#35b37e':'#e06d6d';resultEl.style.fontWeight='700';setTimeout(function(){resultEl.textContent='';},6000);}
            })
            .catch(function(err){t.textContent='Tester';t.disabled=false;if(resultEl){resultEl.textContent='Erreur reseau';resultEl.style.color='#e06d6d';}console.error('Watch test error:',err);});
        });
      });
    }());
    </script>
    <?php
  }

  /* -----------------------------------------------------------------------
   * Notice redirect
   * ----------------------------------------------------------------------- */

  private function render_watch_notice() {
    if ( empty( $_GET['notice'] ) ) { return; }
    $msg  = sanitize_text_field( rawurldecode( wp_unslash( $_GET['notice'] ) ) );
    $type = isset( $_GET['notice_type'] ) && 'error' === sanitize_key( wp_unslash( $_GET['notice_type'] ) ) ? 'error' : 'success';
    $cls  = 'error' === $type ? 'acdc-panel-error' : 'acdc-panel-success';
    echo '<div class="acdc-panel ' . esc_attr( $cls ) . '" style="margin-bottom:16px;">' . esc_html( $msg ) . '</div>';
  }

}
