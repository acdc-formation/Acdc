<?php
/**
 * ACDC Marketing — actions et handlers
 *
 * Extraction incrémentale du module marketing.
 * Version : 3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Marketing_Actions_Trait {


  public function handle_marketing_public_submit() {
    check_admin_referer( 'acdc_marketing_public_submit' );
    $form_token = isset( $_POST['marketing_form_token'] ) ? sanitize_text_field( wp_unslash( $_POST['marketing_form_token'] ) ) : '';
    $input = isset( $_POST['marketing_public'] ) && is_array( $_POST['marketing_public'] ) ? wp_unslash( $_POST['marketing_public'] ) : array();
    $email = isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '';
    $first_name = isset( $input['first_name'] ) ? sanitize_text_field( $input['first_name'] ) : '';
    $last_name = isset( $input['last_name'] ) ? sanitize_text_field( $input['last_name'] ) : '';
    if ( ! $email || ! $first_name || ! $last_name ) {
      wp_die( esc_html( 'Les champs prénom, nom et e-mail sont obligatoires.' ) );
    }
    $forms = $this->get_marketing_store( 'forms', array() );
    $form = null;
    foreach ( $forms as $item ) {
      if ( isset( $item['public_token'] ) && $item['public_token'] === $form_token ) {
        $form = $item;
        break;
      }
    }
    if ( ! $form ) {
      wp_die( esc_html( 'Formulaire introuvable.' ) );
    }
    $contacts = $this->get_marketing_store( 'contacts', array() );
    $source_key = 'public:' . md5( strtolower( $email ) . '|' . $form_token );
    $list_ids = isset( $form['list_ids'] ) ? array_values( array_filter( array_map( 'strval', (array) $form['list_ids'] ) ) ) : array();
    $tag_ids = isset( $form['tag_ids'] ) ? array_values( array_filter( array_map( 'strval', (array) $form['tag_ids'] ) ) ) : array();
    if ( empty( $list_ids ) ) {
      $list_ids[] = 'list_prospects';
    }
    if ( ! in_array( 'tag_source_site_web', $tag_ids, true ) ) {
      $tag_ids[] = 'tag_source_site_web';
    }
    $statuses = $this->marketing_default_contact_statuses( 'prospect' );
    $statuses['marketing'] = ! empty( $input['consent'] ) ? 'allowed' : 'not_requested';
    $contacts[ $source_key ] = $this->normalize_marketing_contact_record( array(
      'source_key' => $source_key,
      'source_type' => 'public_form',
      'source_id' => 0,
      'name' => trim( $first_name . ' ' . $last_name ),
      'email' => $email,
      'phone' => isset( $input['phone'] ) ? sanitize_text_field( $input['phone'] ) : '',
      'company' => isset( $input['company'] ) ? sanitize_text_field( $input['company'] ) : '',
      'type' => 'prospect',
      'enabled' => 1,
      'statuses' => $statuses,
      'primary_list_id' => isset( $list_ids[0] ) ? $list_ids[0] : 'list_prospects',
      'lists' => $list_ids,
      'tags' => $tag_ids,
      'segments_cache' => array(),
      'score' => 15,
      'score_value' => 0,
      'last_engagement' => $this->now_mysql(),
      'last_interaction_at' => $this->now_mysql(),
      'created_at' => isset( $contacts[ $source_key ]['created_at'] ) ? $contacts[ $source_key ]['created_at'] : $this->now_mysql(),
      'updated_at' => $this->now_mysql(),
    ) );
    $this->update_marketing_store( 'contacts', $contacts );
    $this->refresh_marketing_segments();
    $this->add_marketing_log( 'Soumission d’un formulaire public marketing.', 'info', array( 'form' => $form['name'], 'email' => $email ) );
    $this->add_marketing_notification( 'Nouveau formulaire soumis', $email . ' — ' . $form['name'], 'success' );
    $imports = $this->get_marketing_store( 'imports', array() );
    array_unshift( $imports, array( 'id' => uniqid( 'sub_', true ), 'name' => 'Soumission publique', 'count' => 1, 'created_at' => $this->now_mysql(), 'context' => $form['name'] ) );
    $this->update_marketing_store( 'imports', array_slice( $imports, 0, 100 ) );
    $redirect = add_query_arg( array( 'form' => rawurlencode( $form_token ), 'submitted' => '1' ), $this->get_marketing_public_base_url() );
    wp_safe_redirect( $redirect );
    exit;
  }


  public function handle_marketing_toggle_contact() {
    $this->require_manage_options();
    check_admin_referer( 'acdc_marketing_toggle_contact' );
    $source_key = isset( $_REQUEST['source_key'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['source_key'] ) ) : '';
    $contacts = $this->get_marketing_contacts_index();
    if ( ! isset( $contacts[ $source_key ] ) ) {
      $this->redirect_to_portal( 'marketing_contacts', 'Contact marketing introuvable.', 'error' );
    }
    $contacts[ $source_key ]['enabled'] = ! empty( $contacts[ $source_key ]['enabled'] ) ? 0 : 1;
    $contacts[ $source_key ]['updated_at'] = $this->now_mysql();
    $this->update_marketing_store( 'contacts', $contacts );
    $this->add_marketing_log( 'Statut d’activation marketing modifié.', 'info', array( 'source_key' => $source_key, 'enabled' => $contacts[ $source_key ]['enabled'] ) );
    $this->redirect_to_portal( 'marketing_contacts', ! empty( $contacts[ $source_key ]['enabled'] ) ? 'Contact marketing activé.' : 'Contact marketing désactivé.', 'success' );
  }



  public function handle_marketing_save_contact() {
    $this->require_manage_options();
    check_admin_referer( 'acdc_marketing_save_contact' );
    $input = isset( $_POST['marketing_contact'] ) && is_array( $_POST['marketing_contact'] ) ? wp_unslash( $_POST['marketing_contact'] ) : array();
    $source_key = isset( $input['source_key'] ) ? sanitize_text_field( $input['source_key'] ) : '';
    $contacts = $this->get_marketing_contacts_index();
    if ( ! $source_key || ! isset( $contacts[ $source_key ] ) ) {
      $this->redirect_to_portal( 'marketing_contacts', 'Contact marketing introuvable.', 'error' );
    }
    $type = isset( $input['type'] ) ? sanitize_key( $input['type'] ) : $contacts[ $source_key ]['type'];
    if ( ! isset( $this->marketing_type_labels()[ $type ] ) ) {
      $type = $contacts[ $source_key ]['type'];
    }
    $email = isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '';
    $contacts[ $source_key ]['name'] = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : $contacts[ $source_key ]['name'];
    $contacts[ $source_key ]['type'] = $type;
    $contacts[ $source_key ]['email'] = $email;
    $contacts[ $source_key ]['phone'] = isset( $input['phone'] ) ? sanitize_text_field( $input['phone'] ) : '';
    $contacts[ $source_key ]['company'] = isset( $input['company'] ) ? sanitize_text_field( $input['company'] ) : '';
    $contacts[ $source_key ]['enabled'] = ! empty( $input['enabled'] ) ? 1 : 0;
    $contacts[ $source_key ]['primary_list_id'] = isset( $input['primary_list_id'] ) ? sanitize_text_field( $input['primary_list_id'] ) : '';
    $contacts[ $source_key ]['lists'] = $this->normalize_marketing_csv_array( isset( $input['lists'] ) ? $input['lists'] : '' );
    $contacts[ $source_key ]['tags'] = $this->normalize_marketing_csv_array( isset( $input['tags'] ) ? $input['tags'] : '' );
    $contacts[ $source_key ]['score'] = isset( $input['score'] ) ? max( 0, min( 999, intval( $input['score'] ) ) ) : 0;
    $contacts[ $source_key ]['score_value'] = isset( $input['score_value'] ) ? max( 0, min( 999, intval( $input['score_value'] ) ) ) : 0;
    $contacts[ $source_key ]['notes'] = isset( $input['notes'] ) ? sanitize_textarea_field( $input['notes'] ) : '';
    $contacts[ $source_key ]['statuses'] = $this->normalize_marketing_contact_statuses( isset( $input['statuses'] ) && is_array( $input['statuses'] ) ? $input['statuses'] : array(), $type );
    $last_engagement = isset( $input['last_engagement'] ) ? sanitize_text_field( $input['last_engagement'] ) : '';
    $contacts[ $source_key ]['last_engagement'] = $last_engagement ? str_replace( 'T', ' ', $last_engagement ) . ':00' : '';
    $contacts[ $source_key ]['updated_at'] = $this->now_mysql();
    $contacts[ $source_key ]['last_interaction_at'] = $this->now_mysql();
    $contacts[ $source_key ] = $this->normalize_marketing_contact_record( $contacts[ $source_key ] );
    $this->update_marketing_store( 'contacts', $contacts );
    $this->refresh_marketing_segments();
    $this->add_marketing_log( 'Contact marketing mis à jour.', 'success', array( 'source_key' => $source_key ) );
    $this->redirect_to_portal( 'marketing_contacts', 'Contact marketing enregistré.', 'success' );
  }


  public function handle_marketing_save_settings() {
    $this->require_manage_options();
    check_admin_referer( 'acdc_marketing_save_settings' );
    $input = isset( $_POST['marketing_settings'] ) && is_array( $_POST['marketing_settings'] ) ? wp_unslash( $_POST['marketing_settings'] ) : array();
    $settings = $this->get_marketing_store( 'settings', array() );
    $settings['sender_name'] = isset( $input['sender_name'] ) ? sanitize_text_field( $input['sender_name'] ) : 'ACDC-Formation';
    $settings['sender_email'] = isset( $input['sender_email'] ) ? sanitize_email( $input['sender_email'] ) : $this->acdc_org_identity()['email'];
    $settings['reply_to'] = isset( $input['reply_to'] ) ? sanitize_email( $input['reply_to'] ) : $settings['sender_email'];
    $settings['queue_threshold'] = isset( $input['queue_threshold'] ) ? max( 1, absint( $input['queue_threshold'] ) ) : 20;
    $settings['limit_per_minute'] = isset( $input['limit_per_minute'] ) ? max( 1, absint( $input['limit_per_minute'] ) ) : 30;
    $settings['limit_per_hour'] = isset( $input['limit_per_hour'] ) ? max( 1, absint( $input['limit_per_hour'] ) ) : 300;
    $settings['retry_enabled'] = ! empty( $input['retry_enabled'] ) ? 1 : 0;
    $settings['retry_max'] = isset( $input['retry_max'] ) ? max( 1, absint( $input['retry_max'] ) ) : 3;
    $settings['manual_pause'] = ! empty( $input['manual_pause'] ) ? 1 : 0;
    $settings['allowed_hours_start'] = isset( $input['allowed_hours_start'] ) ? sanitize_text_field( $input['allowed_hours_start'] ) : '08:00';
    $settings['allowed_hours_end'] = isset( $input['allowed_hours_end'] ) ? sanitize_text_field( $input['allowed_hours_end'] ) : '19:00';
    $settings['click_tracking_enabled'] = ! empty( $input['click_tracking_enabled'] ) ? 1 : 0;
    $settings['unsubscribe_mode'] = isset( $input['unsubscribe_mode'] ) && in_array( $input['unsubscribe_mode'], array( 'category', 'global' ), true ) ? sanitize_key( $input['unsubscribe_mode'] ) : 'category';
    $settings['soft_bounce_limit'] = isset( $input['soft_bounce_limit'] ) ? max( 1, absint( $input['soft_bounce_limit'] ) ) : 3;
    $settings['hard_bounce_blacklist'] = ! empty( $input['hard_bounce_blacklist'] ) ? 1 : 0;
    $settings['internal_notification_email'] = isset( $input['internal_notification_email'] ) ? sanitize_email( $input['internal_notification_email'] ) : $this->acdc_org_identity()['email'];
    $settings['scheduler_enabled'] = ! empty( $input['scheduler_enabled'] ) ? 1 : 0;
    $settings['scheduler_retry_lag'] = isset( $input['scheduler_retry_lag'] ) ? max( 1, absint( $input['scheduler_retry_lag'] ) ) : 15;
    $settings['retention_mode'] = isset( $input['retention_mode'] ) && in_array( $input['retention_mode'], array( 'archive_only', 'manual_delete', 'anonymize' ), true ) ? sanitize_key( $input['retention_mode'] ) : 'archive_only';
    $settings['retention_logs_days'] = isset( $input['retention_logs_days'] ) ? max( 0, absint( $input['retention_logs_days'] ) ) : 0;
    $settings['public_form_mode'] = isset( $input['public_form_mode'] ) && in_array( $input['public_form_mode'], array( 'plugin_pages', 'external_pages', 'mixed' ), true ) ? sanitize_key( $input['public_form_mode'] ) : 'plugin_pages';
    $settings['smtp_mode'] = 'wp_mail_smtp_status_only';
    $this->update_marketing_store( 'settings', $settings );
    $this->add_marketing_log( 'Réglages marketing mis à jour.', 'success', array( 'sender_email' => $settings['sender_email'] ) );
    $this->redirect_to_portal( 'marketing_settings', 'Réglages marketing enregistrés.', 'success' );
  }


  public function handle_marketing_save_entity() {
    $this->require_manage_options();
    check_admin_referer( 'acdc_marketing_save_entity' );
    $entity = isset( $_POST['marketing_entity'] ) ? sanitize_key( wp_unslash( $_POST['marketing_entity'] ) ) : '';
    $labels = $this->marketing_entity_labels();
    if ( ! isset( $labels[ $entity ] ) ) {
      $this->redirect_to_portal( 'marketing_dashboard', 'Entité marketing inconnue.', 'error' );
    }
    $input = isset( $_POST['marketing_item'] ) && is_array( $_POST['marketing_item'] ) ? wp_unslash( $_POST['marketing_item'] ) : array();
    $items = $this->get_marketing_store( $entity, array() );
    $id = isset( $input['id'] ) ? sanitize_text_field( $input['id'] ) : '';
    $record = array(
      'id' => $id ? $id : uniqid( $entity . '_', true ),
      'name' => isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '',
      'description' => isset( $input['description'] ) ? sanitize_textarea_field( $input['description'] ) : '',
      'status' => isset( $input['status'] ) ? sanitize_key( $input['status'] ) : 'draft',
      'category' => isset( $input['category'] ) ? sanitize_key( $input['category'] ) : 'marketing',
      'subject' => isset( $input['subject'] ) ? sanitize_text_field( $input['subject'] ) : '',
      'content' => isset( $input['content'] ) ? wp_kses_post( $input['content'] ) : '',
      'rules' => isset( $input['rules'] ) ? sanitize_textarea_field( $input['rules'] ) : '',
      'events' => $this->normalize_marketing_csv_array( isset( $input['events'] ) ? $input['events'] : '' ),
      'url' => isset( $input['url'] ) ? esc_url_raw( $input['url'] ) : '',
      'secret' => isset( $input['secret'] ) ? sanitize_text_field( $input['secret'] ) : '',
      'trigger_type' => isset( $input['trigger_type'] ) ? sanitize_key( $input['trigger_type'] ) : '',
      'planned_at' => isset( $input['planned_at'] ) ? sanitize_text_field( $input['planned_at'] ) : '',
      'direction' => isset( $input['direction'] ) ? sanitize_key( $input['direction'] ) : '',
      'list_ids' => $this->normalize_marketing_csv_array( isset( $input['list_ids'] ) ? $input['list_ids'] : '' ),
      'tag_ids' => $this->normalize_marketing_csv_array( isset( $input['tag_ids'] ) ? $input['tag_ids'] : '' ),
      'segment_ids' => $this->normalize_marketing_csv_array( isset( $input['segment_ids'] ) ? $input['segment_ids'] : '' ),
      'block_ids' => $this->normalize_marketing_csv_array( isset( $input['block_ids'] ) ? $input['block_ids'] : '' ),
      'steps' => isset( $input['steps'] ) ? sanitize_textarea_field( $input['steps'] ) : '',
      'goal' => isset( $input['goal'] ) ? sanitize_text_field( $input['goal'] ) : '',
      'slug' => isset( $input['slug'] ) ? sanitize_title( $input['slug'] ) : '',
      'intro_text' => isset( $input['intro_text'] ) ? sanitize_textarea_field( $input['intro_text'] ) : '',
      'success_message' => isset( $input['success_message'] ) ? sanitize_textarea_field( $input['success_message'] ) : '',
      'template_kind' => isset( $input['template_kind'] ) ? sanitize_key( $input['template_kind'] ) : 'master',
      'parent_template_id' => isset( $input['parent_template_id'] ) ? sanitize_text_field( $input['parent_template_id'] ) : '',
      'reply_to' => isset( $input['reply_to'] ) ? sanitize_email( $input['reply_to'] ) : '',
      'preview_text' => isset( $input['preview_text'] ) ? sanitize_text_field( $input['preview_text'] ) : '',
      'usage' => isset( $input['usage'] ) ? sanitize_key( $input['usage'] ) : '',
      'field_type' => isset( $input['field_type'] ) ? sanitize_key( $input['field_type'] ) : 'text',
      'options' => isset( $input['options'] ) ? sanitize_textarea_field( $input['options'] ) : '',
      'segment_mode' => isset( $input['segment_mode'] ) ? sanitize_key( $input['segment_mode'] ) : 'dynamic',
      'contact_type' => isset( $input['contact_type'] ) ? sanitize_key( $input['contact_type'] ) : '',
      'enabled_only' => ! empty( $input['enabled_only'] ) ? 1 : 0,
      'status_filter' => isset( $input['status_filter'] ) ? sanitize_key( $input['status_filter'] ) : '',
      'engagement_filter' => isset( $input['engagement_filter'] ) ? sanitize_key( $input['engagement_filter'] ) : '',
      'code' => isset( $input['code'] ) ? sanitize_title( $input['code'] ) : '',
      'family' => isset( $input['family'] ) ? sanitize_key( $input['family'] ) : '',
      'icon' => isset( $input['icon'] ) ? sanitize_key( $input['icon'] ) : '',
      'color' => isset( $input['color'] ) ? sanitize_hex_color( $input['color'] ) : '',
      'is_system' => ! empty( $input['is_system'] ) ? 1 : 0,
      'is_required' => ! empty( $input['is_required'] ) ? 1 : 0,
      'usage_forms' => ! empty( $input['usage_forms'] ) ? 1 : 0,
      'usage_templates' => ! empty( $input['usage_templates'] ) ? 1 : 0,
      'sort_order' => isset( $input['sort_order'] ) ? (int) $input['sort_order'] : 100,
      'rules_json' => isset( $input['rules_json'] ) ? $this->normalize_marketing_rules_json( $input['rules_json'] ) : array( 'logic' => 'AND', 'conditions' => array() ),
      'population_count' => isset( $input['population_count'] ) ? (int) $input['population_count'] : 0,
      'updated_at' => $this->now_mysql(),
      'created_at' => $this->now_mysql(),
    );
    if ( 'lists' === $entity ) {
      $record = $this->normalize_marketing_list_record( $record );
    } elseif ( 'tags' === $entity ) {
      $record = $this->normalize_marketing_tag_record( $record );
    } elseif ( 'segments' === $entity ) {
      $record = $this->normalize_marketing_segment_record( $record );
    }
    $found = false;
    foreach ( $items as $index => $item ) {
      if ( isset( $item['id'] ) && $item['id'] === $record['id'] ) {
        $record['created_at'] = ! empty( $item['created_at'] ) ? $item['created_at'] : $this->now_mysql();
        if ( 'forms' === $entity ) {
          $record['public_token'] = ! empty( $item['public_token'] ) ? $item['public_token'] : wp_generate_password( 20, false, false );
        }
        $items[ $index ] = array_merge( $item, $record );
        $found = true;
        break;
      }
    }
    if ( ! $found ) {
      if ( 'forms' === $entity ) {
        $record['public_token'] = wp_generate_password( 20, false, false );
      }
      array_unshift( $items, $record );
    }
    $this->update_marketing_store( $entity, array_values( $items ) );
    if ( in_array( $entity, array( 'lists', 'tags', 'segments' ), true ) ) {
      $this->refresh_marketing_segments();
    }
    if ( 'campaigns' === $entity && 'planned' === $record['status'] ) {
      $queue = $this->get_marketing_store( 'queue', array() );
      array_unshift( $queue, array( 'id' => uniqid( 'queue_', true ), 'name' => $record['name'], 'kind' => 'campaign', 'status' => 'pending', 'planned_at' => $record['planned_at'], 'created_at' => $this->now_mysql() ) );
      $this->update_marketing_store( 'queue', array_slice( $queue, 0, 200 ) );
    }
    $this->add_marketing_log( ucfirst( $labels[ $entity ]['single'] ) . ' enregistré.', 'success', array( 'entity' => $entity, 'id' => $record['id'] ) );
    $this->redirect_to_portal( 'marketing_' . $entity, ucfirst( $labels[ $entity ]['single'] ) . ' enregistré.', 'success' );
  }


  public function handle_marketing_delete_entity() {
    $this->require_manage_options();
    check_admin_referer( 'acdc_marketing_delete_entity' );
    $entity = isset( $_REQUEST['marketing_entity'] ) ? sanitize_key( wp_unslash( $_REQUEST['marketing_entity'] ) ) : '';
    $id = isset( $_REQUEST['item_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['item_id'] ) ) : '';
    $labels = $this->marketing_entity_labels();
    if ( ! isset( $labels[ $entity ] ) || ! $id ) {
      $this->redirect_to_portal( 'marketing_dashboard', 'Suppression marketing impossible.', 'error' );
    }
    $items = $this->get_marketing_store( $entity, array() );
    foreach ( $items as $item ) {
      if ( isset( $item['id'] ) && $item['id'] === $id && ! empty( $item['is_system'] ) ) {
        $this->redirect_to_portal( 'marketing_' . $entity, 'Élément système non supprimable.', 'error' );
      }
    }
    $items = array_values( array_filter( $items, function( $item ) use ( $id ) { return ! isset( $item['id'] ) || $item['id'] !== $id; } ) );
    $this->update_marketing_store( $entity, $items );
    $this->add_marketing_log( ucfirst( $labels[ $entity ]['single'] ) . ' supprimé.', 'warning', array( 'entity' => $entity, 'id' => $id ) );
    $this->redirect_to_portal( 'marketing_' . $entity, ucfirst( $labels[ $entity ]['single'] ) . ' supprimé.', 'success' );
  }


  public function handle_marketing_import_contacts() {
    $this->require_manage_options();
    check_admin_referer( 'acdc_marketing_import_contacts' );
    if ( empty( $_FILES['marketing_import_file']['name'] ) ) {
      $this->redirect_to_portal( 'marketing_imports', 'Veuillez sélectionner un fichier CSV.', 'error' );
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $upload = wp_handle_upload( $_FILES['marketing_import_file'], array( 'test_form' => false, 'mimes' => array( 'csv' => 'text/csv', 'txt' => 'text/plain' ) ) );
    if ( ! empty( $upload['error'] ) ) {
      $this->redirect_to_portal( 'marketing_imports', 'Téléversement impossible : ' . sanitize_text_field( $upload['error'] ), 'error' );
    }
    $file = isset( $upload['file'] ) ? $upload['file'] : '';
    $contacts = $this->get_marketing_store( 'contacts', array() );
    // ACDC 3.25.112 — Déduplication à l'import par e-mail contre les fiches métier
    // (prospects/apprenants/entreprises/financeurs/formateurs/contacts) ET le store
    // existant : un e-mail déjà connu enrichit la fiche existante au lieu de créer un
    // doublon « import: ».
    $base_records = $this->get_marketing_base_records();
    $email_to_key = array();
    foreach ( $base_records as $bk => $brec ) {
      $bem = strtolower( trim( (string) ( $brec['email'] ?? '' ) ) );
      if ( '' !== $bem && ! isset( $email_to_key[ $bem ] ) ) { $email_to_key[ $bem ] = $bk; }
    }
    foreach ( $contacts as $ck => $crec ) {
      $cem = strtolower( trim( (string) ( $crec['email'] ?? '' ) ) );
      if ( '' !== $cem && ! isset( $email_to_key[ $cem ] ) ) { $email_to_key[ $cem ] = $ck; }
    }
    $processed = 0;
    $created = 0;
    $updated = 0;
    $skipped = 0;
    $delimiter = ',';

    if ( $file && file_exists( $file ) ) {
      if ( ( $handle = fopen( $file, 'r' ) ) ) {
        $sample = fgets( $handle );
        if ( false !== $sample && substr_count( (string) $sample, ';' ) > substr_count( (string) $sample, ',' ) ) {
          $delimiter = ';';
        }
        rewind( $handle );
        $header = fgetcsv( $handle, 0, $delimiter );
        $normalized_header = array();
        foreach ( (array) $header as $column ) {
          $column_key = sanitize_key( remove_accents( (string) $column ) );
          if ( 'prenom' === $column_key ) {
            $column_key = 'first_name';
          } elseif ( 'nom' === $column_key ) {
            $column_key = 'last_name';
          } elseif ( 'societe' === $column_key || 'organisme' === $column_key || 'entreprise_organisme' === $column_key ) {
            $column_key = 'company';
          } elseif ( 'telephone' === $column_key ) {
            $column_key = 'phone';
          } elseif ( 'courriel' === $column_key || 'mail' === $column_key ) {
            $column_key = 'email';
          }
          $normalized_header[] = $column_key;
        }

        while ( ( $row = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
          if ( 1 === count( $row ) && '' === trim( (string) $row[0] ) ) {
            continue;
          }
          $data = array();
          foreach ( $normalized_header as $index => $column ) {
            $data[ $column ] = isset( $row[ $index ] ) ? sanitize_text_field( $row[ $index ] ) : '';
          }
          $email = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
          if ( ! $email ) {
            $skipped++;
            continue;
          }
          $email_norm = strtolower( trim( $email ) );
          $source_key = isset( $email_to_key[ $email_norm ] ) ? $email_to_key[ $email_norm ] : 'import:' . md5( $email_norm );
          $matched_base = isset( $base_records[ $source_key ] ) ? $base_records[ $source_key ] : null;
          $contact_type = isset( $data['type'] ) && isset( $this->marketing_type_labels()[ sanitize_key( $data['type'] ) ] ) ? sanitize_key( $data['type'] ) : 'prospect';
          $statuses = $this->marketing_default_contact_statuses( $contact_type );
          $statuses['marketing'] = 'not_requested';
          $is_existing = isset( $contacts[ $source_key ] );
          $csv_name = trim( ( isset( $data['first_name'] ) ? $data['first_name'] : '' ) . ' ' . ( isset( $data['last_name'] ) ? $data['last_name'] : '' ) );
          if ( $is_existing ) {
            // Ré-import : fusionner uniquement les champs d'identité non vides du CSV,
            // et préserver listes/étiquettes/score/enabled/liste principale ajoutés manuellement.
            $existing = $contacts[ $source_key ];
            if ( '' !== $csv_name ) {
              $existing['name'] = $csv_name;
            }
            if ( isset( $data['phone'] ) && '' !== trim( (string) $data['phone'] ) ) {
              $existing['phone'] = $data['phone'];
            }
            if ( isset( $data['company'] ) && '' !== trim( (string) $data['company'] ) ) {
              $existing['company'] = $data['company'];
            }
            $existing['email'] = $email;
            $existing['updated_at'] = $this->now_mysql();
            $contacts[ $source_key ] = $this->normalize_marketing_contact_record( $existing );
          } else {
            $contacts[ $source_key ] = $this->normalize_marketing_contact_record( array(
              'source_key' => $source_key,
              'source_type' => $matched_base ? (string) $matched_base['source_type'] : 'import',
              'source_id' => $matched_base ? (int) $matched_base['source_id'] : 0,
              'name' => $csv_name,
              'email' => $email,
              'phone' => isset( $data['phone'] ) ? $data['phone'] : '',
              'company' => isset( $data['company'] ) ? $data['company'] : '',
              'type' => $contact_type,
              'enabled' => 1,
              'statuses' => $statuses,
              'primary_list_id' => 'list_prospects',
              'lists' => array( 'list_prospects' ),
              'tags' => array( 'tag_source_import_csv', 'tag_donnee_a_nettoyer' ),
              'segments_cache' => array(),
              'score' => 0,
              'score_value' => 0,
              'last_engagement' => '',
              'created_at' => $this->now_mysql(),
              'updated_at' => $this->now_mysql(),
            ) );
          }
          $processed++;
          if ( $is_existing ) {
            $updated++;
          } else {
            $created++;
          }
        }
        fclose( $handle );
      }
    }
    $this->update_marketing_store( 'contacts', $contacts );
    $this->refresh_marketing_segments();
    $imports = $this->get_marketing_store( 'imports', array() );
    array_unshift( $imports, array(
      'id' => uniqid( 'import_', true ),
      'name' => basename( (string) $file ),
      'count' => $processed,
      'created_count' => $created,
      'updated_count' => $updated,
      'skipped_count' => $skipped,
      'delimiter' => $delimiter,
      'created_at' => $this->now_mysql(),
      'context' => 'CSV contacts marketing',
    ) );
    $this->update_marketing_store( 'imports', array_slice( $imports, 0, 100 ) );
    $this->add_marketing_log( 'Import marketing réalisé.', 'success', array( 'count' => $processed, 'created' => $created, 'updated' => $updated, 'skipped' => $skipped ) );
    $this->add_marketing_notification( 'Import contacts terminé', (string) $processed . ' ligne(s) traitée(s), ' . (string) $created . ' création(s), ' . (string) $updated . ' mise(s) à jour.', 'success' );
    $message = (string) $processed . ' ligne(s) traitée(s) — ' . (string) $created . ' création(s), ' . (string) $updated . ' mise(s) à jour, ' . (string) $skipped . ' ignorée(s).';
    $this->redirect_to_portal( 'marketing_imports', $message, 'success' );
  }


  public function handle_marketing_resend_archived_email() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $archive_id = isset( $_REQUEST['archive_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['archive_id'] ) ) : '';
    check_admin_referer( 'acdc_marketing_resend_archived_email_' . $archive_id );
    $entry = $this->get_marketing_archive_entry( $archive_id );
    if ( ! $entry ) {
      $this->redirect_to_portal( 'marketing_email_archive', 'E-mail archivé introuvable.', 'error' );
    }
    $headers = ! empty( $entry['headers'] ) && is_array( $entry['headers'] ) ? $entry['headers'] : array();
    $normalized_headers = array();
    $has_content_type = false;
    foreach ( $headers as $header_line ) {
      if ( ! is_string( $header_line ) ) {
        continue;
      }
      $header_line = trim( $header_line );
      if ( '' === $header_line ) {
        continue;
      }
      if ( 0 === stripos( $header_line, 'Content-Type:' ) ) {
        $has_content_type = true;
        continue;
      }
      $normalized_headers[] = $header_line;
    }
    $headers = $normalized_headers;
    if ( ! $has_content_type ) {
      $headers[] = 'Content-Type: text/html; charset=UTF-8';
    }
    if ( ! empty( $entry['from_email'] ) ) {
      $from_name = ! empty( $entry['from_name'] ) ? $entry['from_name'] : $this->acdc_expediteur_organisme();
      $headers[] = 'From: ' . $from_name . ' <' . $entry['from_email'] . '>';
    }
    if ( ! empty( $entry['reply_to'] ) ) {
      $headers[] = 'Reply-To: ' . $entry['reply_to'];
    }
    if ( ! empty( $entry['cc'] ) && is_array( $entry['cc'] ) ) {
      $headers[] = 'Cc: ' . implode( ',', array_map( 'sanitize_email', $entry['cc'] ) );
    }
    if ( ! empty( $entry['bcc'] ) && is_array( $entry['bcc'] ) ) {
      $headers[] = 'Bcc: ' . implode( ',', array_map( 'sanitize_email', $entry['bcc'] ) );
    }
    $headers[] = 'X-ACDC-Source-Module: ' . ( ! empty( $entry['source_module'] ) ? sanitize_key( $entry['source_module'] ) : 'archive' );
    $headers[] = 'X-ACDC-Source-Action: resend_archive';
    if ( ! empty( $entry['related_entity_type'] ) ) {
      $headers[] = 'X-ACDC-Related-Entity-Type: ' . sanitize_key( $entry['related_entity_type'] );
      $headers[] = 'X-ACDC-Related-Entity-Id: ' . absint( $entry['related_entity_id'] );
    }
    if ( ! empty( $entry['category'] ) ) {
      $headers[] = 'X-ACDC-Email-Category: ' . sanitize_key( $entry['category'] );
    }
    $sent = wp_mail(
      ! empty( $entry['to'] ) ? $entry['to'] : array(),
      ! empty( $entry['subject'] ) ? $entry['subject'] : trim( 'E-mail ' . $this->acdc_org_identity()['raison_sociale'] ),
      ! empty( $entry['body'] ) ? $entry['body'] : '',
      $headers,
      ! empty( $entry['attachments'] ) ? (array) $entry['attachments'] : array()
    );
    if ( $sent ) {
      $this->touch_marketing_archive_resend( $archive_id );
      $this->redirect_to_portal( 'marketing_email_archive', 'E-mail renvoyé.', 'success', array( 'action' => 'view', 'archive_id' => $archive_id ) );
    }
    $this->redirect_to_portal( 'marketing_email_archive', 'Le renvoi de l’e-mail a échoué.', 'error', array( 'action' => 'view', 'archive_id' => $archive_id ) );
  }

  /* ACDC 3.24.32 — Sauvegarde d'une signature e-mail */
  public function handle_marketing_save_signature() {
    $this->require_manage_options();
    check_admin_referer( 'acdc_marketing_save_signature' );
    $input  = isset( $_POST['sig'] ) && is_array( $_POST['sig'] ) ? wp_unslash( $_POST['sig'] ) : array();
    $sig_id = isset( $_POST['sig_id'] ) ? sanitize_key( wp_unslash( $_POST['sig_id'] ) ) : '';

    $id = isset( $input['id'] ) ? sanitize_key( $input['id'] ) : '';
    if ( ! $id ) {
      $this->redirect_to_portal( 'marketing_signatures', 'Identifiant de signature manquant.', 'error' );
    }

    $sig = array(
      'id'           => $id,
      'display_name' => isset( $input['display_name'] ) ? sanitize_text_field( $input['display_name'] ) : '',
      'job_title'    => isset( $input['job_title'] )    ? sanitize_text_field( $input['job_title'] )    : '',
      'email'        => isset( $input['email'] )        ? sanitize_email( $input['email'] )             : '',
      'phone'        => isset( $input['phone'] )        ? sanitize_text_field( $input['phone'] )        : '',
      'company_name' => isset( $input['company_name'] ) ? sanitize_text_field( $input['company_name'] ) : '',
      'company_url'  => isset( $input['company_url'] )  ? esc_url_raw( $input['company_url'] )          : '',
      'address'      => isset( $input['address'] )      ? sanitize_text_field( $input['address'] )      : '',
      'photo_url'    => isset( $input['photo_url'] )    ? esc_url_raw( $input['photo_url'] )            : '',
      'logo_url'     => isset( $input['logo_url'] )     ? esc_url_raw( $input['logo_url'] )             : '',
      'linkedin_url' => isset( $input['linkedin_url'] ) ? esc_url_raw( $input['linkedin_url'] )         : '',
      'facebook_url' => isset( $input['facebook_url'] ) ? esc_url_raw( $input['facebook_url'] )         : '',
      'instagram_url'=> isset( $input['instagram_url'] )? esc_url_raw( $input['instagram_url'] )        : '',
      'is_default'   => ! empty( $input['is_default'] ) ? 1 : 0,
    );

    $signatures = $this->get_marketing_store( 'signatures', array() );

    // Si cette signature est définie comme par défaut, réinitialiser les autres
    if ( $sig['is_default'] ) {
      foreach ( $signatures as &$s ) {
        $s['is_default'] = 0;
      }
      unset( $s );
    }

    // Mettre à jour si exist, sinon ajouter
    $found = false;
    foreach ( $signatures as &$s ) {
      if ( isset( $s['id'] ) && $s['id'] === $id ) {
        $s = $sig;
        $found = true;
        break;
      }
    }
    unset( $s );
    if ( ! $found ) {
      $signatures[] = $sig;
    }

    $this->update_marketing_store( 'signatures', array_values( $signatures ) );
    $this->redirect_to_portal( 'marketing_signatures', 'Signature enregistrée.', 'success', array( 'acdc_notice' => 'sig_saved' ) );
  }

  /* ACDC 3.24.32 — Suppression d'une signature e-mail */
  public function handle_marketing_delete_signature() {
    $this->require_manage_options();
    $sig_id = isset( $_GET['sig_id'] ) ? sanitize_key( wp_unslash( $_GET['sig_id'] ) ) : '';
    check_admin_referer( 'acdc_marketing_delete_signature_' . $sig_id );
    if ( ! $sig_id ) {
      $this->redirect_to_portal( 'marketing_signatures', 'Identifiant manquant.', 'error' );
    }
    $signatures = $this->get_marketing_store( 'signatures', array() );
    $signatures = array_values( array_filter( $signatures, function( $s ) use ( $sig_id ) {
      return ! isset( $s['id'] ) || $s['id'] !== $sig_id;
    } ) );
    $this->update_marketing_store( 'signatures', $signatures );
    $this->redirect_to_portal( 'marketing_signatures', 'Signature supprimée.', 'success', array( 'acdc_notice' => 'sig_deleted' ) );
  }

}
