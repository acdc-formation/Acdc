<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * ACDC Watch Actions Trait
 * Exploitation articles, archivage, réglages, export PDF Qualiopi.
 * PHP 7.3 compatible.
 */
trait ACDC_Watch_Actions_Trait {

  /* -----------------------------------------------------------------------
   * Hooks AJAX et admin-post
   * ----------------------------------------------------------------------- */

  public function register_watch_module_hooks() {
    add_action( 'wp_ajax_acdc_of_watch_exploit',      array( $this, 'ajax_acdc_of_watch_exploit' ) );
    add_action( 'wp_ajax_acdc_of_watch_archive',      array( $this, 'ajax_acdc_of_watch_archive' ) );
    add_action( 'wp_ajax_acdc_of_watch_irrelevant',   array( $this, 'ajax_acdc_of_watch_irrelevant' ) );
    add_action( 'wp_ajax_acdc_of_watch_test_api_key', array( $this, 'ajax_acdc_of_watch_test_api_key' ) );
    add_action( 'admin_post_acdc_save_watch_settings',  array( $this, 'handle_save_watch_settings' ) );
    add_action( 'admin_post_acdc_save_watch_sources',   array( $this, 'handle_save_watch_sources' ) );
    add_action( 'admin_post_acdc_watch_trigger_collect', array( $this, 'handle_watch_trigger_collect' ) );
    add_action( 'admin_post_acdc_watch_trigger_analyze', array( $this, 'handle_watch_trigger_analyze' ) );
    add_action( 'admin_post_acdc_watch_export_pdf',     array( $this, 'handle_watch_export_pdf' ) );
  }

  /* -----------------------------------------------------------------------
   * AJAX : exploitation
   * ----------------------------------------------------------------------- */

  public function ajax_acdc_of_watch_exploit() {
    if ( ! check_ajax_referer( 'acdc_watch_exploit', '_nonce', false ) ) {
      wp_send_json_error( 'Nonce invalide.' );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( 'Permissions insuffisantes.' );
    }

    global $wpdb;
    $tbl     = $this->get_watch_items_table();
    $item_id = absint( isset( $_POST['item_id'] ) ? $_POST['item_id'] : 0 );
    if ( ! $item_id ) {
      wp_send_json_error( 'ID manquant.' );
    }

    $note       = sanitize_textarea_field( isset( $_POST['exploitation_note'] ) ? wp_unslash( $_POST['exploitation_note'] ) : '' );
    $action     = sanitize_textarea_field( isset( $_POST['action_taken'] ) ? wp_unslash( $_POST['action_taken'] ) : '' );
    $diffused   = ! empty( $_POST['diffused'] ) ? 1 : 0;
    $diff_note  = sanitize_textarea_field( isset( $_POST['diffusion_note'] ) ? wp_unslash( $_POST['diffusion_note'] ) : '' );
    $f_id       = absint( isset( $_POST['formation_id'] ) ? $_POST['formation_id'] : 0 );
    $effect_date = sanitize_text_field( isset( $_POST['effect_date'] ) ? wp_unslash( $_POST['effect_date'] ) : '' );

    $update = array(
      'status'             => 'exploited',
      'exploited_at'       => current_time( 'mysql' ),
      'exploitation_note'  => $note,
      'action_taken'       => $action,
      'updated_at'         => current_time( 'mysql' ),
    );
    if ( $diffused ) {
      $update['diffused_at']   = current_time( 'mysql' );
      $update['diffusion_note'] = $diff_note;
    }
    if ( $f_id ) {
      $update['ai_formation_id'] = $f_id;
    }

    $wpdb->update( $tbl, $update, array( 'id' => $item_id ) );
    wp_send_json_success( 'Article exploité.' );
  }

  /* -----------------------------------------------------------------------
   * AJAX : archivage
   * ----------------------------------------------------------------------- */

  public function ajax_acdc_of_watch_archive() {
    if ( ! check_ajax_referer( 'acdc_watch_archive', '_nonce', false ) ) {
      wp_send_json_error( 'Nonce invalide.' );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( 'Permissions insuffisantes.' );
    }

    global $wpdb;
    $item_id = absint( isset( $_POST['item_id'] ) ? $_POST['item_id'] : 0 );
    if ( ! $item_id ) {
      wp_send_json_error( 'ID manquant.' );
    }
    $wpdb->update( $this->get_watch_items_table(), array( 'status' => 'archived', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $item_id ) );
    wp_send_json_success( 'Archivé.' );
  }

  /* -----------------------------------------------------------------------
   * AJAX : non pertinent
   * ----------------------------------------------------------------------- */

  public function ajax_acdc_of_watch_irrelevant() {
    if ( ! check_ajax_referer( 'acdc_watch_irrelevant', '_nonce', false ) ) {
      wp_send_json_error( 'Nonce invalide.' );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( 'Permissions insuffisantes.' );
    }

    global $wpdb;
    $item_id = absint( isset( $_POST['item_id'] ) ? $_POST['item_id'] : 0 );
    if ( ! $item_id ) {
      wp_send_json_error( 'ID manquant.' );
    }
    $wpdb->update( $this->get_watch_items_table(), array( 'status' => 'irrelevant', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $item_id ) );
    wp_send_json_success( 'Marqué non pertinent.' );
  }

  /* -----------------------------------------------------------------------
   * Admin-post : sauvegarde réglages clés API + paramètres
   * ----------------------------------------------------------------------- */

  public function handle_save_watch_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( 'Permissions insuffisantes.' );
    }
    check_admin_referer( 'acdc_save_watch_settings' );

    $fields = array(
      'acdc_of_watch_api_openai'          => 'sanitize_text_field',
      'acdc_of_watch_api_anthropic'       => 'sanitize_text_field',
      'acdc_of_watch_api_gemini'          => 'sanitize_text_field',
      'acdc_of_watch_api_perplexity'      => 'sanitize_text_field',
      'acdc_of_watch_api_youtube'         => 'sanitize_text_field',
      'acdc_of_watch_min_score'           => 'absint',
      'acdc_of_watch_ai_batch'            => 'absint',
      'acdc_of_watch_max_per_source'      => 'absint',
      'acdc_of_watch_digest_email'        => 'sanitize_email',
      'acdc_of_watch_collect_frequency'   => 'sanitize_key',
      'acdc_of_watch_collect_day'         => 'absint',
      'acdc_of_watch_collect_hour'        => 'absint',
    );

    foreach ( $fields as $option => $sanitizer ) {
      if ( isset( $_POST[ $option ] ) ) {
        $val = $sanitizer( wp_unslash( $_POST[ $option ] ) );
        if ( 'absint' === $sanitizer && 0 === $val && ! in_array( $option, array( 'acdc_of_watch_collect_day', 'acdc_of_watch_collect_hour' ), true ) ) {
          continue;
        }
        if ( 0 === strpos( $option, 'acdc_of_watch_api_' ) ) {
          /* ACDC 3.25.313 — UN CHAMP VIDE N'EFFACE JAMAIS UNE CLÉ.
             Depuis que l'écran ne réaffiche plus les clés, le champ arrive vide
             à chaque enregistrement. Sans cette garde, ouvrir l'écran « Veille »
             et cliquer « Enregistrer » effacerait les cinq clés d'un coup.
             Pour retirer une clé volontairement, on coche la case prévue à cet
             effet — le silence d'un formulaire ne vaut pas décision. */
          if ( '' === trim( (string) $val ) ) {
            continue;
          }
          $val = $this->acdc_secret_encrypt( $val );
        }
        update_option( $option, $val );
      }
    }

    /* ACDC 3.25.313 — Le retrait volontaire d'une clé, par une case à cocher.
       C'est le geste explicite qui remplace l'ancien « vider le champ », lequel
       ne se distinguait pas d'un formulaire simplement réenregistré. */
    $__a_effacer = isset( $_POST['acdc_watch_effacer_cle'] ) && is_array( $_POST['acdc_watch_effacer_cle'] )
      ? array_map( 'sanitize_key', wp_unslash( $_POST['acdc_watch_effacer_cle'] ) )
      : array();
    foreach ( $__a_effacer as $__opt ) {
      if ( 0 === strpos( $__opt, 'acdc_of_watch_api_' ) && array_key_exists( $__opt, $fields ) ) {
        update_option( $__opt, '' );
        $this->log_action_event( 'cle_api_retiree', 'settings', 0, 'success', array( 'reglage' => $__opt ) );
      }
    }

    // Replanifier le cron collect avec la nouvelle fréquence/heure
    if ( method_exists( $this, '_reschedule_watch_collect_cron' ) ) {
      $this->_reschedule_watch_collect_cron();
    }

    $redirect_url = $this->portal_page_url( array( 'tab' => 'watch_ia', 'watch_sub' => 'sources', 'notice' => rawurlencode( 'Réglages sauvegardés.' ), 'notice_type' => 'success' ) );
    wp_safe_redirect( $redirect_url );
    exit;
  }

  /* -----------------------------------------------------------------------
   * Admin-post : sauvegarde sources RSS
   * ----------------------------------------------------------------------- */

  public function handle_save_watch_sources() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( 'Permissions insuffisantes.' );
    }
    check_admin_referer( 'acdc_save_watch_sources' );

    $sources_raw = isset( $_POST['watch_sources'] ) ? (array) wp_unslash( $_POST['watch_sources'] ) : array();
    $sources     = array();
    foreach ( $sources_raw as $s ) {
      if ( empty( $s['id'] ) ) {
        continue;
      }
      // Suppression demandée via bouton ✕
      if ( ! empty( $s['delete'] ) && '1' === (string) $s['delete'] ) {
        continue;
      }
      $sources[] = array(
        'id'     => sanitize_key( $s['id'] ),
        'label'  => sanitize_text_field( isset( $s['label'] ) ? $s['label'] : '' ),
        'type'   => sanitize_key( isset( $s['type'] ) ? $s['type'] : 'rss' ),
        'axis'   => sanitize_key( isset( $s['axis'] ) ? $s['axis'] : 'legal' ),
        'url'    => esc_url_raw( isset( $s['url'] ) ? $s['url'] : '' ),
        'active' => ! empty( $s['active'] ) ? 1 : 0,
      );
    }

    // Ajout d'une nouvelle source — formulaire par axe (new_source_label_legal etc.)
    foreach ( array( 'legal', 'metiers', 'pedagogique' ) as $ax ) {
      $lbl_key = 'new_source_label_' . $ax;
      $url_key = 'new_source_url_' . $ax;
      $typ_key = 'new_source_type_' . $ax;
      if ( ! empty( $_POST[ $lbl_key ] ) && ! empty( $_POST[ $url_key ] ) ) {
        $sources[] = array(
          'id'     => 'custom_' . $ax . '_' . time(),
          'label'  => sanitize_text_field( wp_unslash( $_POST[ $lbl_key ] ) ),
          'type'   => sanitize_key( isset( $_POST[ $typ_key ] ) ? wp_unslash( $_POST[ $typ_key ] ) : 'rss' ),
          'axis'   => $ax,
          'url'    => sanitize_text_field( wp_unslash( $_POST[ $url_key ] ) ),
          'active' => 1,
        );
      }
    }

    // Compatibilité ancien formulaire global (new_source_label sans axe)
    if ( ! empty( $_POST['new_source_label'] ) && ! empty( $_POST['new_source_url'] ) ) {
      $sources[] = array(
        'id'     => 'custom_' . time(),
        'label'  => sanitize_text_field( wp_unslash( $_POST['new_source_label'] ) ),
        'type'   => sanitize_key( isset( $_POST['new_source_type'] ) ? wp_unslash( $_POST['new_source_type'] ) : 'rss' ),
        'axis'   => sanitize_key( isset( $_POST['new_source_axis'] ) ? wp_unslash( $_POST['new_source_axis'] ) : 'legal' ),
        'url'    => esc_url_raw( wp_unslash( $_POST['new_source_url'] ) ),
        'active' => 1,
      );
    }

    update_option( 'acdc_of_watch_sources', $sources );

    // ACDC 3.25.110 — Mémoriser les sources PAR DÉFAUT supprimées par l'utilisateur, afin
    // qu'elles ne soient pas ré-injectées par get_ia_watch_sources() au rechargement.
    if ( method_exists( $this, 'get_ia_default_watch_sources' ) ) {
      $kept_ids = array();
      foreach ( $sources as $s ) {
        $kept_ids[ $s['id'] ] = true;
      }
      $deleted_defaults = array();
      foreach ( (array) $this->get_ia_default_watch_sources() as $d ) {
        if ( empty( $kept_ids[ $d['id'] ] ) ) {
          $deleted_defaults[] = $d['id'];
        }
      }
      update_option( 'acdc_of_watch_deleted_default_ids', array_values( array_unique( $deleted_defaults ) ) );
    }

    $redirect_url = $this->portal_page_url( array( 'tab' => 'watch_ia', 'watch_sub' => 'sources', 'notice' => rawurlencode( 'Sources sauvegardées.' ), 'notice_type' => 'success' ) );
    wp_safe_redirect( $redirect_url );
    exit;
  }

  /* -----------------------------------------------------------------------
   * Admin-post : déclenchement manuel collecte
   * ----------------------------------------------------------------------- */

  public function handle_watch_trigger_collect() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( 'Permissions insuffisantes.' );
    }
    check_admin_referer( 'acdc_watch_trigger_collect' );

    $this->process_watch_collect_cron();

    $redirect_url = $this->portal_page_url( array( 'tab' => 'watch_ia', 'watch_sub' => 'general', 'notice' => rawurlencode( 'Collecte lancée.' ), 'notice_type' => 'success' ) );
    wp_safe_redirect( $redirect_url );
    exit;
  }

  /* -----------------------------------------------------------------------
   * Admin-post : déclenchement manuel de l'analyse IA
   * ----------------------------------------------------------------------- */

  public function handle_watch_trigger_analyze() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( 'Permissions insuffisantes.' );
    }
    check_admin_referer( 'acdc_watch_trigger_analyze' );

    $this->process_watch_analyze_cron();

    $redirect_url = $this->portal_page_url( array( 'tab' => 'watch_ia', 'watch_sub' => 'general', 'notice' => rawurlencode( 'Analyse IA lancée.' ), 'notice_type' => 'success' ) );
    wp_safe_redirect( $redirect_url );
    exit;
  }

  /* -----------------------------------------------------------------------
   * Admin-post : export PDF Qualiopi
   * ----------------------------------------------------------------------- */

  public function handle_watch_export_pdf() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( 'Permissions insuffisantes.' );
    }
    check_admin_referer( 'acdc_watch_export_pdf' );

    $axis = isset( $_POST['watch_export_axis'] ) ? sanitize_key( wp_unslash( $_POST['watch_export_axis'] ) ) : 'legal';
    $axis_valid = array( 'legal', 'metiers', 'pedagogique' );
    if ( ! in_array( $axis, $axis_valid, true ) ) {
      $axis = 'legal';
    }

    $date_from = isset( $_POST['watch_export_from'] ) ? sanitize_text_field( wp_unslash( $_POST['watch_export_from'] ) ) : '';
    $date_to   = isset( $_POST['watch_export_to'] )   ? sanitize_text_field( wp_unslash( $_POST['watch_export_to'] ) )   : '';

    $this->_generate_watch_pdf( $axis, $date_from, $date_to );
    exit;
  }

  /* -----------------------------------------------------------------------
   * Génération PDF export Qualiopi (indicateurs 23 / 24 / 25)
   * ----------------------------------------------------------------------- */

  private function _generate_watch_pdf( $axis, $date_from = '', $date_to = '' ) {
    global $wpdb;
    $tbl = $this->get_watch_items_table();

    $axis_labels = array(
      'legal'       => 'Indicateur 23 — Veille légale et réglementaire',
      'metiers'     => 'Indicateur 24 — Veille métiers et emplois',
      'pedagogique' => 'Indicateur 25 — Veille pédagogique et technologique',
    );
    $ind_labels = array( 'legal' => '23', 'metiers' => '24', 'pedagogique' => '25' );
    $axis_label = isset( $axis_labels[ $axis ] ) ? $axis_labels[ $axis ] : $axis;
    $ind_num    = isset( $ind_labels[ $axis ] ) ? $ind_labels[ $axis ] : '';

    // Articles exploités sur cet axe
    $where_vals = array( $axis );
    $date_cond  = '';
    if ( $date_from ) {
      $date_cond  .= ' AND exploited_at >= %s';
      $where_vals[] = $date_from . ' 00:00:00';
    }
    if ( $date_to ) {
      $date_cond  .= ' AND exploited_at <= %s';
      $where_vals[] = $date_to . ' 23:59:59';
    }

    $exploited = $wpdb->get_results( $wpdb->prepare(
      "SELECT * FROM {$tbl} WHERE watch_axis = %s AND status = 'exploited'" . $date_cond . " ORDER BY exploited_at ASC",
      $where_vals
    ) );

    $total_collected = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl} WHERE watch_axis = %s", $axis ) );
    $total_relevant  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl} WHERE watch_axis = %s AND ai_score >= 6", $axis ) );
    $total_exploited = count( $exploited );
    $taux = $total_relevant > 0 ? round( $total_exploited / $total_relevant * 100 ) : 0;

    $branding = $this->get_branding_options();
    $org_name = ! empty( $branding['company_name'] ) ? $branding['company_name'] : 'ACDC Formation';
    $nda      = ! empty( $branding['nda_number'] )   ? $branding['nda_number']   : '';

    $period_label = '';
    if ( $date_from || $date_to ) {
      $period_label = 'du ' . ( $date_from ? wp_date( 'd/m/Y', strtotime( $date_from ) ) : '—' ) . ' au ' . ( $date_to ? wp_date( 'd/m/Y', strtotime( $date_to ) ) : wp_date( 'd/m/Y' ) );
    } else {
      $period_label = 'Période complète — généré le ' . wp_date( 'd/m/Y' );
    }

    // Sources actives sur cet axe
    $sources = $this->get_ia_watch_sources_active();
    $sources_axis = array();
    foreach ( $sources as $s ) {
      if ( $s['axis'] === $axis ) {
        $sources_axis[] = $s;
      }
    }

    // HTML du PDF
    $html  = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">';
    $html .= '<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#111;margin:0;padding:20px}';
    $html .= 'h1{font-size:16px;color:#0f2c52;margin-bottom:4px}h2{font-size:13px;color:#8b5b23;margin-top:24px;margin-bottom:6px;border-bottom:1px solid #d6a353;padding-bottom:3px}';
    $html .= 'table{width:100%;border-collapse:collapse;margin-top:8px}th{background:#0f2c52;color:#fff;padding:6px 8px;text-align:left;font-size:10px}';
    $html .= 'td{padding:5px 8px;border-bottom:1px solid #eee;vertical-align:top;font-size:10px}tr:nth-child(even){background:#f9f9f9}';
    $html .= '.badge{display:inline-block;padding:2px 7px;border-radius:10px;font-size:9px;font-weight:700}';
    $html .= '.stat-box{display:inline-block;border:1px solid #d6a353;border-radius:6px;padding:8px 14px;margin:4px 8px 4px 0;text-align:center}';
    $html .= '.stat-num{font-size:22px;font-weight:700;color:#8b5b23}  .stat-lbl{font-size:9px;color:#4b5d76}</style></head><body>';

    $html .= '<h1>' . esc_html( $axis_label ) . '</h1>';
    $html .= '<p style="color:#4b5d76;margin:0 0 4px;">' . esc_html( $org_name );
    if ( $nda ) { $html .= ' — NDA : ' . esc_html( $nda ); }
    $html .= '</p>';
    $html .= '<p style="color:#4b5d76;margin:0 0 18px;font-size:10px;">' . esc_html( $period_label ) . '</p>';

    // Section 1 : Sources actives
    $html .= '<h2>1. Sources de veille actives</h2>';
    if ( ! empty( $sources_axis ) ) {
      $html .= '<table><thead><tr><th>Libellé</th><th>Type</th><th>URL / Identifiant</th></tr></thead><tbody>';
      foreach ( $sources_axis as $s ) {
        $html .= '<tr><td>' . esc_html( $s['label'] ) . '</td><td>' . esc_html( strtoupper( $s['type'] ) ) . '</td><td style="word-break:break-all;">' . esc_html( $s['url'] ) . '</td></tr>';
      }
      $html .= '</tbody></table>';
    } else {
      $html .= '<p style="color:#aaa;">Aucune source active sur cet axe.</p>';
    }

    // Section 2 : Articles exploités
    $html .= '<h2>2. Articles exploités</h2>';
    if ( ! empty( $exploited ) ) {
      $html .= '<table><thead><tr><th>Date exploitation</th><th>Titre</th><th>Source</th><th>Note d\'exploitation</th><th>Action engagée</th><th>Diffusion interne</th><th>Formation impactée</th></tr></thead><tbody>';
      foreach ( $exploited as $e ) {
        $f_title = '';
        if ( ! empty( $e->ai_formation_id ) ) {
          $f_title = (string) $wpdb->get_var( $wpdb->prepare( "SELECT title FROM {$this->formation_table} WHERE id = %d", (int) $e->ai_formation_id ) );
        }
        $diff_date = ! empty( $e->diffused_at ) ? wp_date( 'd/m/Y', strtotime( $e->diffused_at ) ) : '';
        $html .= '<tr>';
        $html .= '<td>' . esc_html( ! empty( $e->exploited_at ) ? wp_date( 'd/m/Y', strtotime( $e->exploited_at ) ) : '—' ) . '</td>';
        $html .= '<td>' . esc_html( $e->title ) . '</td>';
        $html .= '<td>' . esc_html( $this->get_watch_source_label( $e->source_id ) ) . '</td>';
        $html .= '<td>' . esc_html( $e->exploitation_note ) . '</td>';
        $html .= '<td>' . esc_html( $e->action_taken ) . '</td>';
        $html .= '<td>' . esc_html( $diff_date ? $diff_date . ' — ' . $e->diffusion_note : 'Non diffusé' ) . '</td>';
        $html .= '<td>' . esc_html( $f_title ?: '—' ) . '</td>';
        $html .= '</tr>';
      }
      $html .= '</tbody></table>';
    } else {
      $html .= '<p style="color:#aaa;">Aucun article exploité sur cette période.</p>';
    }

    // Section 3 : Statistiques
    $html .= '<h2>3. Statistiques</h2>';
    $html .= '<div>';
    $html .= '<div class="stat-box"><div class="stat-num">' . $total_collected . '</div><div class="stat-lbl">Collectés</div></div>';
    $html .= '<div class="stat-box"><div class="stat-num">' . $total_relevant . '</div><div class="stat-lbl">Pertinents (≥6/10)</div></div>';
    $html .= '<div class="stat-box"><div class="stat-num">' . $total_exploited . '</div><div class="stat-lbl">Exploités</div></div>';
    $html .= '<div class="stat-box"><div class="stat-num">' . $taux . ' %</div><div class="stat-lbl">Taux exploitation</div></div>';
    $html .= '</div>';

    // Section 4 : Formations mises à jour
    $formation_ids = array();
    foreach ( $exploited as $e ) {
      if ( ! empty( $e->ai_formation_id ) ) {
        $formation_ids[ (int) $e->ai_formation_id ] = true;
      }
    }
    if ( ! empty( $formation_ids ) ) {
      $html .= '<h2>4. Formations mises à jour</h2><ul>';
      foreach ( array_keys( $formation_ids ) as $fid ) {
        $ftitle = (string) $wpdb->get_var( $wpdb->prepare( "SELECT title FROM {$this->formation_table} WHERE id = %d", $fid ) );
        if ( $ftitle ) {
          $html .= '<li>' . esc_html( $ftitle ) . '</li>';
        }
      }
      $html .= '</ul>';
    }

    $html .= '</body></html>';

    // Génération PDF via render_html_pdf (moteur plugin existant)
    if ( method_exists( $this, 'render_html_pdf' ) ) {
      $filename = 'veille-qualiopi-indicateur-' . $ind_num . '-' . wp_date( 'Y-m-d' ) . '.pdf';
      /* ACDC 3.25.256 — Même filet que les autres fabrications : un échec de
         mPDF sert le HTML au lieu d'une page blanche. */
      try {
        $this->render_html_pdf( $html, $filename );
      } catch ( \Throwable $e ) {
        while ( ob_get_level() ) { ob_end_clean(); }
        header( 'Content-Type: text/html; charset=UTF-8' );
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
        exit;
      }
    } else {
      // Fallback : HTML direct
      header( 'Content-Type: text/html; charset=UTF-8' );
      echo $html;
    }
  }

  /* -----------------------------------------------------------------------
   * Digest hebdomadaire (cron)
   * ----------------------------------------------------------------------- */

  public function process_watch_digest_cron() {
    $email = get_option( 'acdc_of_watch_digest_email', get_option( 'admin_email' ) );
    if ( empty( $email ) ) {
      return;
    }

    $tbl   = $this->get_watch_items_table();
    global $wpdb;

    $since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 7 * DAY_IN_SECONDS );
    $new_count      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl} WHERE collected_at >= %s", $since ) );
    $to_exploit     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} WHERE status = 'reviewed' AND ai_score >= 6" );
    $urgent         = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} WHERE ai_urgency = 'immediat' AND status = 'reviewed'" );

    $subject = 'Digest veille hebdomadaire — ACDC Formation';
    $body    = "Bonjour,\n\nVoici le résumé de votre veille de la semaine :\n\n";
    $body   .= "• {$new_count} nouveaux articles collectés cette semaine\n";
    $body   .= "• {$to_exploit} articles pertinents (score ≥ 6) en attente d'exploitation\n";
    $body   .= "• {$urgent} articles marqués IMMÉDIAT\n\n";
    $body   .= "Accédez à votre veille depuis l'interface du plugin pour exploiter ces articles et maintenir votre conformité Qualiopi.\n\n";
    $body   .= "— ACDC Formation SAAS";

    /* ACDC 3.25.246 — Enveloppe commune. Ce digest était rédigé en texte brut :
       on conserve ses retours à la ligne et on l'habille comme le reste. */
    $this->acdc_send_transactional_email(
      $email,
      $subject,
      array( 'body_html' => wpautop( esc_html( $body ) ) ),
      array(
        'source_module'  => 'watch',
        'source_action'  => 'watch_digest',
        'email_category' => 'watch',
        'email_audience' => 'interne',
      )
    );
  }

  /* -----------------------------------------------------------------------
   * Alerte mensuelle si aucune exploitation (cron)
   * ----------------------------------------------------------------------- */

  public function process_watch_reminder_cron() {
    $tbl   = $this->get_watch_items_table();
    global $wpdb;

    $since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 30 * DAY_IN_SECONDS );
    $last  = $wpdb->get_var( "SELECT MAX(exploited_at) FROM {$tbl} WHERE status = 'exploited'" );

    if ( $last && strtotime( $last ) > strtotime( $since ) ) {
      return; // exploitation récente OK
    }

    $email = get_option( 'acdc_of_watch_digest_email', get_option( 'admin_email' ) );
    /* ACDC 3.25.246 — Enveloppe commune. */
    $this->acdc_send_transactional_email(
      $email,
      '⚠️ Alerte Qualiopi — Aucune exploitation de veille depuis 30 jours',
      array(
        'intro_html' => '<p>Aucun article de veille n’a été exploité depuis plus de 30 jours.</p>',
        'body_html'  => '<p>Cela peut constituer une non-conformité mineure lors d’un audit Qualiopi (critère 6).</p>'
                      . '<p>Merci de vous connecter à votre interface ACDC Formation pour exploiter au moins un article.</p>',
      ),
      array(
        'source_module'  => 'watch',
        'source_action'  => 'watch_reminder',
        'email_category' => 'watch',
        'email_audience' => 'interne',
      )
    );
  }

}
