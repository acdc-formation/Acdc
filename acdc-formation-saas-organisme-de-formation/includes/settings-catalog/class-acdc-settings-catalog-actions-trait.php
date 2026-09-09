<?php
/**
 * ACDC Réglages OF / Profil organisme / Catalogue — actions et enregistrements
 *
 * Extraction incrémentale du module réglages OF, profil organisme
 * et catalogue.
 * Version : 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Settings_Catalog_Actions_Trait {

  public function handle_save_billing_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html__( 'Action non autorisée.', 'acdc-formation-saas' ) );
    }
    check_admin_referer( 'acdc_save_billing_settings' );
    $input = isset( $_POST['billing_settings'] ) && is_array( $_POST['billing_settings'] ) ? wp_unslash( $_POST['billing_settings'] ) : array();
    $current = $this->get_billing_settings_options();
    $clean = $current;
    foreach ( array( 'billing', 'quotes', 'invoices', 'credit_notes' ) as $section ) {
      if ( empty( $input[ $section ] ) || ! is_array( $input[ $section ] ) ) {
        continue;
      }
      foreach ( $input[ $section ] as $key => $value ) {
        if ( is_array( $value ) ) {
          continue;
        }
        $clean[ $section ][ $key ] = in_array( $key, array( 'signature_stamp', 'restart_each_year' ), true ) ? ( ! empty( $value ) ? 1 : 0 ) : sanitize_textarea_field( (string) $value );
      }
    }
    if ( empty( $input['billing']['signature_stamp'] ) ) { $clean['billing']['signature_stamp'] = 0; }
    if ( empty( $input['quotes']['restart_each_year'] ) ) { $clean['quotes']['restart_each_year'] = 0; }
    if ( empty( $input['invoices']['restart_each_year'] ) ) { $clean['invoices']['restart_each_year'] = 0; }
    update_option( 'acdc_of_billing_settings', $clean, false );
    /* ACDC 3.24.20 — Toggle activation facturation réelle. */
    $real_enabled = ! empty( $_POST['billing_real_enabled'] ) ? '0' : '1';
    update_option( 'acdc_of_documents_billing_demo_enabled', $real_enabled, false );
    $return_context = isset( $_POST['return_context'] ) && 'admin' === sanitize_key( wp_unslash( $_POST['return_context'] ) ) ? 'admin' : 'front';
    $redirect_url = 'admin' === $return_context ? $this->admin_tab_url( 'billing_settings', array( 'updated' => 1 ) ) : $this->portal_page_url( array( 'tab' => 'billing_settings', 'updated' => 1 ) );
    wp_safe_redirect( $redirect_url );
    exit;
  }


  public function handle_save_catalog_settings() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_save_catalog_settings' );
    $input = isset( $_POST['catalog_settings'] ) && is_array( $_POST['catalog_settings'] ) ? wp_unslash( $_POST['catalog_settings'] ) : array();
    $settings = array(
      'domain_type' => sanitize_text_field( $input['domain_type'] ?? 'catalogue.teetche.com' ),
      'domain_name' => sanitize_title( $input['domain_name'] ?? 'acdc-formation' ),
      'hook_text' => sanitize_textarea_field( $input['hook_text'] ?? '' ),
      'cta_label' => sanitize_text_field( $input['cta_label'] ?? "S'inscrire" ),
      'show_prices' => ! empty( $input['show_prices'] ) ? 1 : 0,
      'show_satisfaction' => ! empty( $input['show_satisfaction'] ) ? 1 : 0,
      'notify_new_registration' => ! empty( $input['notify_new_registration'] ) ? 1 : 0,
      'privacy_policy_url' => esc_url_raw( $input['privacy_policy_url'] ?? $this->get_default_catalog_privacy_policy_url() ),
      'footer_show_address' => ! empty( $input['footer_show_address'] ) ? 1 : 0,
      'footer_show_email' => ! empty( $input['footer_show_email'] ) ? 1 : 0,
      'footer_show_phone' => ! empty( $input['footer_show_phone'] ) ? 1 : 0,
      'footer_show_website' => ! empty( $input['footer_show_website'] ) ? 1 : 0,
      'footer_show_cgv' => ! empty( $input['footer_show_cgv'] ) ? 1 : 0,
      'profile_default' => sanitize_text_field( $input['profile_default'] ?? 'Particulier' ),
      'required_gender' => ! empty( $input['required_gender'] ) ? 1 : 0,
      'required_first_name' => ! empty( $input['required_first_name'] ) ? 1 : 0,
      'required_last_name' => ! empty( $input['required_last_name'] ) ? 1 : 0,
      'required_email' => ! empty( $input['required_email'] ) ? 1 : 0,
      'required_phone' => ! empty( $input['required_phone'] ) ? 1 : 0,
      'required_desired_training' => ! empty( $input['required_desired_training'] ) ? 1 : 0,
      'required_rgpd' => ! empty( $input['required_rgpd'] ) ? 1 : 0,
      'optional_address' => ! empty( $input['optional_address'] ) ? 1 : 0,
      'optional_postal_code' => ! empty( $input['optional_postal_code'] ) ? 1 : 0,
      'optional_city' => ! empty( $input['optional_city'] ) ? 1 : 0,
      'optional_education_level' => ! empty( $input['optional_education_level'] ) ? 1 : 0,
      'optional_comment' => ! empty( $input['optional_comment'] ) ? 1 : 0,
      'optional_future_sessions' => ! empty( $input['optional_future_sessions'] ) ? 1 : 0,
      'active' => ! empty( $input['active'] ) ? 1 : 0,
    );
    update_option( 'acdc_of_catalog_settings', $settings, false );
    $this->redirect_to_portal( 'catalog', 'Paramètres du catalogue enregistrés.', 'success' );
  }


  public function handle_set_catalog_order() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_set_catalog_order' );
    global $wpdb;
    $formation_id  = isset( $_POST['formation_id'] )  ? absint( $_POST['formation_id'] )          : 0;
    $catalog_order = isset( $_POST['catalog_order'] ) ? max( 1, absint( $_POST['catalog_order'] ) ) : 1;
    if ( $formation_id ) {
      $wpdb->query( $wpdb->prepare( "UPDATE {$this->formation_table} SET catalog_order = catalog_order + 1 WHERE id != %d AND catalog_order >= %d", $formation_id, $catalog_order ) );
      $result = $wpdb->update( $this->formation_table, array( 'catalog_order' => $catalog_order, 'catalog_public' => 1, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $formation_id ) );
      if ( false === $result ) {
        $this->redirect_to_portal( 'catalog', 'Erreur lors de la mise à jour de l\'ordre.', 'error' );
      }
    }
    $this->redirect_to_portal( 'catalog', "Ordre d'affichage mis à jour.", 'success' );
  }


  public function handle_remove_from_catalog() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_remove_from_catalog' );
    global $wpdb;
    $formation_id = isset( $_POST['formation_id'] ) ? absint( $_POST['formation_id'] ) : 0;
    if ( $formation_id ) {
      $result = $wpdb->update( $this->formation_table, array( 'catalog_public' => 0, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $formation_id ) );
      if ( false === $result ) {
        $this->redirect_to_portal( 'catalog', 'Erreur lors du retrait du catalogue.', 'error' );
      }
    }
    $this->redirect_to_portal( 'catalog', 'Formation retirée du catalogue.', 'success' );
  }


  public function handle_catalog_register() {
    check_admin_referer( 'acdc_catalog_register' );

    // SEC-04 : honeypot anti-bot — le champ acdc_hp doit rester vide
    if ( ! empty( $_POST['acdc_hp'] ) ) {
      wp_safe_redirect( $this->get_catalog_page_url( array( 'formation_id' => absint( $_POST['formation_id'] ?? 0 ), 'catalog_notice' => rawurlencode( 'Votre demande a bien été enregistrée.' ) ) ) );
      exit;
    }

    // SEC-04 : rate-limiting par IP — max 3 inscriptions catalogue par heure
    $raw_ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
    $rl_key      = 'acdc_catalog_reg_' . md5( $raw_ip );
    $rl_attempts = (int) get_transient( $rl_key );
    if ( $rl_attempts >= 3 ) {
      wp_safe_redirect( $this->get_catalog_page_url( array( 'catalog_notice' => rawurlencode( 'Trop de demandes. Veuillez réessayer dans une heure.' ) ) ) );
      exit;
    }

    $formation_id = isset( $_POST['formation_id'] ) ? absint( $_POST['formation_id'] ) : 0;
    $formation = $this->get_catalog_formation( $formation_id );
    if ( ! $formation ) {
      wp_safe_redirect( $this->get_catalog_page_url( array( 'catalog_notice' => rawurlencode( 'Formation introuvable.' ) ) ) ); exit;
    }
    $input = isset( $_POST['catalog'] ) && is_array( $_POST['catalog'] ) ? wp_unslash( $_POST['catalog'] ) : array();
    $profile = sanitize_text_field( $input['profile_type'] ?? 'Particulier' );
    $first = sanitize_text_field( $input['first_name'] ?? '' );
    $last = sanitize_text_field( $input['last_name'] ?? '' );
    $email = sanitize_email( $input['email'] ?? '' );
    $phone = sanitize_text_field( $input['phone'] ?? '' );
    $address = sanitize_text_field( $input['address'] ?? '' );
    $postal = sanitize_text_field( $input['postal_code'] ?? '' );
    $city = sanitize_text_field( $input['city'] ?? '' );
    $comment = sanitize_textarea_field( $input['comment_text'] ?? '' );
    if ( ! $first || ! $last || ! $email || empty( $input['rgpd'] ) ) {
      wp_safe_redirect( $this->get_catalog_page_url( array( 'formation_id' => $formation_id, 'catalog_notice' => rawurlencode( 'Veuillez compléter les champs obligatoires.' ) ) ) ); exit;
    }
    global $wpdb;
    $now = current_time( 'mysql' );
    $data = array(
      'profile_type' => $profile,
      'gender' => '',
      'first_name' => $first,
      'last_name' => $last,
      'company_name' => 'Entreprise' === $profile ? trim( $first . ' ' . $last ) : '',
      'siret' => 'Entreprise' === $profile ? 'CATALOGUE' : '',
      'address' => $address,
      'postal_code' => $postal,
      'city' => $city,
      'email' => $email,
      'phone' => $phone,
      'signer_first_name' => 'Entreprise' === $profile ? $first : '',
      'signer_last_name' => 'Entreprise' === $profile ? $last : '',
      'desired_training' => sanitize_text_field( $formation->title ),
      'status' => 'Nouveau',
      'source' => 'Catalogue',
      'comment_text' => $comment,
      'created_at' => $now,
      'updated_at' => $now,
    );
    $ok = $wpdb->insert( $this->prospect_table, $data );
    if ( false === $ok || 0 === (int) $wpdb->insert_id ) {
      wp_safe_redirect( $this->get_catalog_page_url( array( 'formation_id' => $formation_id, 'catalog_notice' => rawurlencode( 'Une erreur est survenue. Veuillez réessayer.' ) ) ) );
      exit;
    }
    // Incrémenter le compteur rate-limit après succès
    set_transient( $rl_key, $rl_attempts + 1, HOUR_IN_SECONDS );
    wp_safe_redirect( $this->get_catalog_page_url( array( 'formation_id' => $formation_id, 'catalog_notice' => rawurlencode( 'Votre demande a bien été enregistrée.' ) ) ) );
    exit;
  }






private function handle_company_profile_file_upload( $field_key, $kind = 'image' ) {
  if ( empty( $_FILES['company_profile_uploads'] ) || ! is_array( $_FILES['company_profile_uploads'] ) ) {
    return '';
  }
  $files = $_FILES['company_profile_uploads'];
  if ( empty( $files['name'][ $field_key ] ) ) {
    return '';
  }
  $single = array(
    'name'     => $files['name'][ $field_key ],
    'type'     => $files['type'][ $field_key ],
    'tmp_name' => $files['tmp_name'][ $field_key ],
    'error'    => $files['error'][ $field_key ],
    'size'     => $files['size'][ $field_key ],
  );
  if ( ! empty( $single['error'] ) ) {
    return '';
  }
  require_once ABSPATH . 'wp-admin/includes/file.php';
  $mimes = 'pdf' === $kind ? array( 'pdf' => 'application/pdf' ) : array(
    'jpg|jpeg' => 'image/jpeg',
    'png'      => 'image/png',
    'webp'     => 'image/webp',
    'gif'      => 'image/gif',
    // ACDC sécurité: SVG retiré (XSS stocké possible)
  );
  $upload = wp_handle_upload( $single, array( 'test_form' => false, 'mimes' => $mimes ) );
  if ( ! empty( $upload['error'] ) || empty( $upload['url'] ) ) {
    return '';
  }
  return esc_url_raw( (string) $upload['url'] );
}


private function acdc_push_design_history_snapshot( $branding, $label = 'Sauvegarde automatique' ) {
  if ( ! is_array( $branding ) ) {
    return;
  }
  $history = get_option( 'acdc_design_history', array() );
  if ( ! is_array( $history ) ) {
    $history = array();
  }
  $user = wp_get_current_user();
  array_unshift( $history, array(
    'id' => 'preset_' . time() . '_' . wp_rand( 1000, 9999 ),
    'name' => sanitize_text_field( $label ),
    'date' => current_time( 'mysql' ),
    'user' => $user && ! empty( $user->display_name ) ? sanitize_text_field( $user->display_name ) : 'Administrateur',
    'variables' => $branding,
  ) );
  $history = array_slice( $history, 0, 20 );
  update_option( 'acdc_design_history', $history, false );
}

public function handle_restore_branding_history() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  check_admin_referer( 'acdc_restore_branding_history' );
  $snapshot_id = isset( $_GET['snapshot_id'] ) ? sanitize_text_field( wp_unslash( $_GET['snapshot_id'] ) ) : '';
  $history = get_option( 'acdc_design_history', array() );
  if ( ! is_array( $history ) || '' === $snapshot_id ) {
    wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-ui-system&history_error=1' ) );
    exit;
  }
  foreach ( $history as $snapshot ) {
    if ( ! empty( $snapshot['id'] ) && $snapshot_id === $snapshot['id'] && ! empty( $snapshot['variables'] ) && is_array( $snapshot['variables'] ) ) {
      $this->acdc_push_design_history_snapshot( $this->get_branding_options(), 'Avant restauration' );
      update_option( 'acdc_of_branding', wp_parse_args( $snapshot['variables'], $this->get_branding_defaults() ), false );
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-ui-system&updated=1&restored=1' ) );
      exit;
    }
  }
  wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-ui-system&history_error=1' ) );
  exit;
}
public function handle_save_branding() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  check_admin_referer( 'acdc_save_branding' );
  $defaults = $this->get_branding_defaults();
  $redirect_page = isset( $_POST['redirect_page'] ) ? sanitize_key( wp_unslash( $_POST['redirect_page'] ) ) : 'acdc-of-configuration';
  if ( ! in_array( $redirect_page, array( 'acdc-of-configuration', 'acdc-of-ui-system', 'acdc-of-ui-variables', 'acdc-of-maintenance' ), true ) ) {
    $redirect_page = 'acdc-of-configuration';
  }
  $design_locked_before = get_option( 'acdc_design_locked', 'no' );
  $design_locked_after = isset( $_POST['acdc_design_locked'] ) ? 'yes' : 'no';
  update_option( 'acdc_design_locked', $design_locked_after, false );
  if ( 'yes' === $design_locked_before && 'yes' === $design_locked_after ) {
    wp_safe_redirect( admin_url( 'admin.php?page=' . $redirect_page . '&design_locked=1' ) );
    exit;
  }


  if ( isset( $_POST['acdc_reset_branding_defaults'] ) ) {
    update_option( 'acdc_of_branding', $defaults, false );
    wp_safe_redirect( admin_url( 'admin.php?page=' . $redirect_page . '&updated=1&reset=1' ) );
    exit;
  }

  if ( ! empty( $_FILES['acdc_ui_import_file']['name'] ) ) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $upload = wp_handle_upload( $_FILES['acdc_ui_import_file'], array( 'test_form' => false, 'mimes' => array( 'json' => 'application/json', 'txt' => 'text/plain' ) ) );
    if ( empty( $upload['file'] ) || ! file_exists( $upload['file'] ) ) {
      wp_safe_redirect( admin_url( 'admin.php?page=' . $redirect_page . '&import_error=1' ) );
      exit;
    }
    $raw = file_get_contents( $upload['file'] );
    @unlink( $upload['file'] );
    $json = json_decode( (string) $raw, true );
    if ( ! is_array( $json ) ) {
      wp_safe_redirect( admin_url( 'admin.php?page=' . $redirect_page . '&import_error=1' ) );
      exit;
    }
    $input = $json;
  } else {
    $input = isset( $_POST['branding'] ) && is_array( $_POST['branding'] ) ? wp_unslash( $_POST['branding'] ) : array();
  }

  $number_keys = array( 'sidebar_width', 'menu_font_size', 'radius', 'button_height', 'button_padding_x', 'field_height', 'textarea_height', 'icon_size', 'action_icon_frame_size', 'action_icon_glyph_size', 'action_icon_gap', 'action_icon_radius', 'toggle_width', 'toggle_height', 'table_cell_padding_y', 'table_cell_padding_x', 'table_row_height', 'page_title_size', 'h1_font_size', 'h2_font_size', 'h3_font_size', 'h4_font_size', 'section_title_size', 'subtitle_size', 'body_font_size', 'small_text_size', 'label_font_size', 'field_font_size', 'button_font_size', 'table_header_font_size', 'table_row_font_size', 'table_action_font_size', 'badge_font_size', 'tab_font_size', 'modal_title_size', 'modal_body_size', 'help_text_size', 'error_text_size', 'kpi_label_size', 'kpi_value_size', 'panel_padding', 'modal_padding', 'pdf_title_font_size', 'pdf_text_font_size', 'pdf_page_margin_top', 'pdf_page_margin_right', 'pdf_page_margin_bottom', 'pdf_page_margin_left', 'pdf_logo_max_width', 'pdf_logo_max_height', 'pdf_stamp_max_width', 'pdf_signature_max_width', 'email_button_radius', 'email_width', 'email_title_size', 'email_text_size', 'email_spacing' );
  $url_keys = array( 'logo_url', 'favicon_url', 'website', 'pdf_logo_url', 'email_logo_url' );
  $shadow_keys = array( 'panel_shadow', 'modal_shadow' );
  $css_keys = array( 'custom_css', 'admin_custom_css', 'portal_custom_css', 'email_custom_css', 'pdf_color_map_json', 'pdf_style_overrides_json', 'email_signature', 'pdf_footer_left', 'pdf_footer_right', 'pdf_header_title', 'email_footer_text', 'email_subject_prefix' );
  $hex_keys = array( 'toggle_active_color', 'toggle_inactive_color', 'action_icon_border', 'action_icon_bg', 'action_icon_color', 'action_icon_hover_bg', 'field_bg', 'field_border', 'field_focus', 'field_error', 'panel_bg', 'panel_header_bg', 'panel_footer_bg', 'modal_bg', 'modal_header_bg', 'modal_footer_bg', 'table_border', 'table_header_bg', 'table_header_text', 'button_focus_ring', 'pdf_primary_color', 'pdf_secondary_color', 'pdf_text_color', 'pdf_muted_color', 'pdf_border_color', 'pdf_background_color', 'pdf_header_background', 'pdf_header_text_color', 'pdf_footer_background', 'pdf_footer_text_color', 'email_header_bg', 'email_header_text_color', 'email_body_bg', 'email_panel_bg', 'email_text_color', 'email_muted_color', 'email_link_color', 'email_border_color', 'email_button_bg', 'email_button_text' );
  $clean = array();
  foreach ( $defaults as $key => $default ) {
    $value = isset( $input[ $key ] ) ? $input[ $key ] : $default;
    if ( ( false !== strpos( $key, 'color_' ) || in_array( $key, $hex_keys, true ) ) && ! in_array( $key, $css_keys, true ) ) {
      $value = sanitize_hex_color( $value );
      if ( ! $value ) {
        $value = $default;
      }
    } elseif ( in_array( $key, $number_keys, true ) ) {
      if ( '' === trim( (string) $value ) ) {
        $value = (string) $default;
      } else {
        $value = (string) absint( $value );
      }
    } elseif ( 'siret' === $key ) {
      // Un SIRET valide (clé de Luhn) est mis au format homogène « 405 109 901 00042 » ;
      // une saisie erronée est conservée telle quelle (sanitisée) pour rester visible/corrigeable.
      $digits = preg_replace( '/\D/', '', (string) $value );
      if ( class_exists( '\\ACDC\\Support\\Siret' ) && \ACDC\Support\Siret::isValidSiret( $digits ) ) {
        $value = \ACDC\Support\Siret::formatSiret( $digits );
      } else {
        $value = sanitize_text_field( (string) $value );
      }
    } elseif ( 'email' === $key ) {
      $value = sanitize_email( $value );
    } elseif ( in_array( $key, $url_keys, true ) ) {
      $value = esc_url_raw( $value );
    } elseif ( in_array( $key, $shadow_keys, true ) ) {
      $value = preg_replace( '/[^a-zA-Z0-9#(),.%\s-]/', '', (string) $value );
    } elseif ( in_array( $key, $css_keys, true ) ) {
      $value = (string) wp_kses_post( $value );
    } elseif ( in_array( $key, array( 'pdf_line_height', 'pdf_font_family', 'pdf_heading_font_family', 'email_font_family' ), true ) ) {
      $value = preg_replace( '/[^a-zA-Z0-9#(),.:%\/\s_-]/', '', (string) $value );
    } elseif ( false !== strpos( $key, '_weight' ) || 'font_weight' === $key ) {
      $value = preg_match( '/^(300|400|500|600|700|800)$/', (string) $value ) ? (string) $value : (string) $default;
    } else {
      $value = sanitize_text_field( $value );
    }
    $clean[ $key ] = $value;
  }
  $this->acdc_push_design_history_snapshot( $this->get_branding_options(), 'Avant modification UI' );
  update_option( 'acdc_of_branding', $clean, false );
  $keep_data = isset( $_POST['acdc_of_keep_data_on_uninstall'] ) ? 'yes' : 'no';
  $purge_prod = isset( $_POST['acdc_of_purge_allowed_in_production'] ) ? 'yes' : 'no';
  $backup_retention = isset( $_POST['acdc_of_backup_retention_count'] ) ? absint( wp_unslash( $_POST['acdc_of_backup_retention_count'] ) ) : 30;
  if ( $backup_retention < 5 ) { $backup_retention = 5; }
  if ( $backup_retention > 100 ) { $backup_retention = 100; }
  update_option( 'acdc_of_keep_data_on_uninstall', $keep_data, false );
  update_option( 'acdc_of_purge_allowed_in_production', $purge_prod, false );
  update_option( 'acdc_of_backup_retention_count', $backup_retention, false );
  update_option( 'acdc_of_data_protection_level', '3', false );
  if ( ! get_option( 'acdc_of_db_version' ) ) {
    add_option( 'acdc_of_db_version', '3.0.0' );
  } else {
    update_option( 'acdc_of_db_version', '3.0.0', false );
  }
  wp_safe_redirect( admin_url( 'admin.php?page=' . $redirect_page . '&updated=1' ) );
  exit;
}

public function handle_export_branding() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  check_admin_referer( 'acdc_export_branding' );
  $data = $this->get_branding_options();
  $payload = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
  if ( ! is_string( $payload ) || '' === $payload ) {
    $payload = '{}';
  }
  $this->send_json_download_response( 'acdc-ui-config-' . gmdate( 'Ymd-His' ) . '.json', $payload );
}



public function handle_save_company_profile() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }

  check_admin_referer( 'acdc_save_company_profile' );

  $defaults = $this->get_company_profile_defaults();
  $saved  = get_option( 'acdc_of_company_profile', array() );
  $input  = isset( $_POST['company_profile'] ) && is_array( $_POST['company_profile'] ) ? wp_unslash( $_POST['company_profile'] ) : array();
  $clean  = array();

  foreach ( $defaults as $key => $default ) {
    $value = array_key_exists( $key, $input ) ? $input[ $key ] : ( isset( $saved[ $key ] ) ? $saved[ $key ] : $default );

    if ( false !== strpos( $key, '_url' ) ) {
      $value = trim( (string) $value );
      if ( '' !== $value ) {
        if ( 'website_url' === $key ) {
          if ( ! preg_match( '#^https?://#i', $value ) ) {
            $value = 'https://' . ltrim( $value, '/' );
          }
          $value = esc_url_raw( $value );
        } else {
          $value = esc_url_raw( $value );
          $uploads = wp_get_upload_dir();
          $is_local_upload = ! empty( $uploads['baseurl'] ) && 0 === strpos( $value, $uploads['baseurl'] );
          $is_local_site = 0 === strpos( $value, home_url( '/' ) );
          if ( ! $is_local_upload && ! $is_local_site ) {
            $value = '';
          }
        }
      }
    } elseif ( is_array( $value ) ) {
      $value = '';
    } else {
      $value = sanitize_text_field( trim( (string) $value ) );
    }

    $clean[ $key ] = $value;
  }

  foreach ( array( 'logo_url' => 'image', 'stamp_url' => 'image', 'signature_url' => 'image', 'stamp_only_url' => 'image', 'footer_logo_url' => 'image', 'welcome_booklet_url' => 'pdf', 'training_rules_url' => 'pdf' ) as $upload_key => $upload_kind ) {
    $uploaded_url = $this->handle_company_profile_file_upload( $upload_key, $upload_kind );
    if ( '' !== $uploaded_url ) {
      $clean[ $upload_key ] = $uploaded_url;
    }
  }

  // Sanitisation et propagation des délais d'enquêtes vers le survey engine
  foreach ( array( 'cold_survey_delay', 'company_survey_delay', 'trainer_survey_delay' ) as $delay_key ) {
    if ( isset( $clean[ $delay_key ] ) ) { $clean[ $delay_key ] = max( 1, absint( $clean[ $delay_key ] ) ); }
  }
  update_option( 'acdc_of_company_profile', $clean, false );
  wp_cache_delete( 'acdc_of_company_profile', 'options' );
  $survey_delay_map = array( 'cold_survey_delay' => 'cold', 'company_survey_delay' => 'company', 'trainer_survey_delay' => 'trainer' );
  foreach ( $survey_delay_map as $profile_key => $engine_type ) {
    if ( isset( $clean[ $profile_key ] ) && method_exists( $this, 'update_survey_engine_type_settings' ) && method_exists( $this, 'get_survey_engine_type_settings' ) ) {
      $current_engine = $this->get_survey_engine_type_settings( $engine_type );
      $current_engine['trigger_delay_days'] = max( 1, absint( $clean[ $profile_key ] ) );
      $this->update_survey_engine_type_settings( $engine_type, $current_engine );
    }
  }

  $branding = $this->get_branding_options();
  $branding['company_name'] = $clean['enterprise'];
  $branding['address']   = $clean['address'];
  $branding['postal_code'] = $clean['postal_code'];
  $branding['city']     = $clean['city'];
  $branding['country']   = $clean['country'];
  $branding['email']    = $clean['enterprise_contact_email'];
  $branding['phone']    = $clean['enterprise_contact_phone'];
  $branding['siret']    = $clean['siret_identification'];
  if ( ! empty( $clean['logo_url'] ) ) {
    $branding['logo_url'] = $clean['logo_url'];
  }
  // ACDC 3.21.36 — Sauvegarder les options de synchronisation
  if ( isset( $_POST['acdc_of_manager_url'] ) ) {
    update_option( 'acdc_of_manager_url', esc_url_raw( trim( wp_unslash( $_POST['acdc_of_manager_url'] ) ) ), false );
  }
  if ( isset( $_POST['acdc_of_manager_api_key'] ) ) {
    update_option( 'acdc_of_manager_api_key', sanitize_text_field( wp_unslash( $_POST['acdc_of_manager_api_key'] ) ), false );
  }

  update_option( 'acdc_of_branding', $branding, false );
  wp_cache_delete( 'acdc_of_branding', 'options' );

  if ( function_exists( 'acdc_of_saas_purge_all_caches' ) ) {
    acdc_of_saas_purge_all_caches();
  }

  $return_context = isset( $_POST['return_context'] ) ? sanitize_key( wp_unslash( $_POST['return_context'] ) ) : 'front';
  if ( 'admin' === $return_context ) {
    wp_safe_redirect( $this->admin_tab_url( 'company_profile', array( 'updated' => 1 ) ) );
    exit;
  }

  wp_safe_redirect( $this->portal_page_url( array(
    'tab'     => 'company_profile',
    'updated'   => 1,
    'notice'   => rawurlencode( 'Profil de l’entreprise enregistré.' ),
    'notice_type' => 'success',
  ) ) );
  exit;
}

}
