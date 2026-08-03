<?php
    /**
     * Trait ACDC_Crm_Commercial_Actions_Trait
     *
     * Sous-bloc CRM commercial extrait incrémentalement depuis class-acdc-plugin.php.
     *
     * @since 3.12.0
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    trait ACDC_Crm_Commercial_Actions_Trait {

private function store_prospect_form_state( $input, $required_fields = array() ) {
  $this->acdc_store_form_state( 'prospect', $input, $required_fields );
}

private function ensure_prospect_schema_integrity() {
  if ( empty( $this->prospect_table ) ) {
    return;
  }

  $this->maybe_add_table_column( $this->prospect_table, 'additional_contacts_json', 'LONGTEXT NULL' );
  $this->maybe_add_table_column( $this->prospect_table, 'copy_recipients_json', 'LONGTEXT NULL' );
  $this->maybe_add_table_column( $this->prospect_table, 'contact_alert_at', 'DATETIME DEFAULT NULL' );
  $this->maybe_add_table_column( $this->prospect_table, 'company_phone', "VARCHAR(50) DEFAULT ''" );
  $this->maybe_add_table_column( $this->prospect_table, 'signer_phone', "VARCHAR(50) DEFAULT ''" );
  $this->maybe_add_table_column( $this->prospect_table, 'naf_code', "VARCHAR(190) DEFAULT ''" );
  $this->maybe_modify_table_column( $this->prospect_table, 'naf_code', "VARCHAR(190) DEFAULT ''" );
  $this->maybe_add_table_index( $this->prospect_table, 'contact_alert_at', 'INDEX contact_alert_at (contact_alert_at)' );
}

private function log_prospect_save_failure( $context, $prospect_id, $data ) {
  global $wpdb;

  $table_columns = array();
  $columns = $wpdb->get_col( "SHOW COLUMNS FROM {$this->prospect_table}", 0 );
  if ( is_array( $columns ) ) {
    $table_columns = array_values( array_filter( array_map( 'strval', $columns ) ) );
  }

  $this->log_error(
    'prospect_save_failure',
    $context . ( ! empty( $wpdb->last_error ) ? ' ' . $wpdb->last_error : '' ),
    array(
      'prospect_id'   => (int) $prospect_id,
      'table'         => (string) $this->prospect_table,
      'table_columns' => $table_columns,
      'data_keys'     => array_values( array_keys( (array) $data ) ),
    )
  );
}

public function handle_save_prospect() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  check_admin_referer( 'acdc_save_prospect' );

  $prospect_id = isset( $_POST['prospect_id'] ) ? absint( $_POST['prospect_id'] ) : 0;
  $input = isset( $_POST['prospect'] ) && is_array( $_POST['prospect'] ) ? wp_unslash( $_POST['prospect'] ) : array();
  $existing = $prospect_id ? $this->get_prospect( $prospect_id ) : null;
  $posted = function( $key, $default = '' ) use ( $input, $existing ) {
    if ( array_key_exists( $key, $input ) ) {
      return $input[ $key ];
    }
    return $existing && isset( $existing->$key ) ? $existing->$key : $default;
  };

  /* hotfix15 — copy_recipients est désormais extrait des contacts annexes (is_copy_recipient).
     Le formulaire n'a plus de champ copy_recipients_list direct. */
  $copy_recipients = array();

  $additional_contacts = array();
  if ( ! empty( $input['additional_contacts'] ) && is_array( $input['additional_contacts'] ) ) {
    foreach ( $input['additional_contacts'] as $row ) {
      if ( ! is_array( $row ) ) {
        continue;
      }
      $clean = array(
        'last_name'         => isset( $row['last_name'] ) ? sanitize_text_field( $row['last_name'] ) : '',
        'first_name'        => isset( $row['first_name'] ) ? sanitize_text_field( $row['first_name'] ) : '',
        'job_title'         => isset( $row['job_title'] ) ? sanitize_text_field( $row['job_title'] ) : '',
        'email'             => isset( $row['email'] ) ? sanitize_email( $row['email'] ) : '',
        'phone'             => isset( $row['phone'] ) ? sanitize_text_field( $row['phone'] ) : '',
        'is_copy_recipient' => ! empty( $row['is_copy_recipient'] ) ? '1' : '0',
      );
      if ( implode( '', $clean ) !== '' ) {
        $additional_contacts[] = $clean;
      }
    }
  } elseif ( $existing ) {
    $additional_contacts = $this->get_prospect_additional_contacts( $existing );
  }

  /* hotfix15 — Auto-extraction des destinataires en copie depuis les contacts */
  $copy_recipients_from_contacts = array();
  foreach ( $additional_contacts as $ac ) {
    if ( ! empty( $ac['is_copy_recipient'] ) && '1' === (string) $ac['is_copy_recipient'] && ! empty( $ac['email'] ) ) {
      $copy_recipients_from_contacts[] = sanitize_email( $ac['email'] );
    }
  }
  $copy_recipients = array_values( array_unique( array_filter( $copy_recipients_from_contacts ) ) );

  $data = array(
    'profile_type'            => sanitize_text_field( (string) $posted( 'profile_type' ) ),
    'gender'                  => sanitize_text_field( (string) $posted( 'gender' ) ),
    'first_name'              => sanitize_text_field( (string) $posted( 'first_name' ) ),
    'last_name'               => sanitize_text_field( (string) $posted( 'last_name' ) ),
    'company_name'            => sanitize_text_field( (string) $posted( 'company_name' ) ),
    'siret'                   => sanitize_text_field( (string) $posted( 'siret' ) ),
    'naf_code'                => sanitize_text_field( (string) $posted( 'naf_code' ) ),
    'address'                 => sanitize_text_field( (string) $posted( 'address' ) ),
    'postal_code'             => sanitize_text_field( (string) $posted( 'postal_code' ) ),
    'city'                    => sanitize_text_field( (string) $posted( 'city' ) ),
    'email'                   => sanitize_email( (string) $posted( 'email' ) ),
    'phone'                   => sanitize_text_field( (string) $posted( 'phone' ) ),
    'is_france_travail'       => sanitize_text_field( (string) $posted( 'is_france_travail' ) ),
    'birth_date'              => sanitize_text_field( (string) $posted( 'birth_date' ) ) ?: null,
    'job_title'               => sanitize_text_field( (string) $posted( 'job_title' ) ),
    'education_level'         => sanitize_text_field( (string) $posted( 'education_level' ) ),
    'accessibility_needs'     => sanitize_textarea_field( (string) $posted( 'accessibility_needs' ) ),
    'signer_first_name'       => sanitize_text_field( (string) $posted( 'signer_first_name' ) ),
    'signer_last_name'        => sanitize_text_field( (string) $posted( 'signer_last_name' ) ),
    'signer_quality'          => sanitize_text_field( (string) $posted( 'signer_quality' ) ),
    'signer_email'            => sanitize_email( (string) $posted( 'signer_email' ) ),
    'signer_phone'            => sanitize_text_field( (string) $posted( 'signer_phone' ) ),
    'company_phone'           => sanitize_text_field( (string) $posted( 'company_phone' ) ),
    'additional_contacts_json'=> ! empty( $additional_contacts ) ? wp_json_encode( $additional_contacts ) : '',
    'desired_training'        => sanitize_text_field( (string) $posted( 'desired_training' ) ),
    'desired_thematique'      => sanitize_text_field( (string) $posted( 'desired_thematique' ) ),
    'planned_funding'         => sanitize_text_field( (string) $posted( 'planned_funding' ) ),
    'funder_id'               => absint( $posted( 'funder_id' ) ) ?: null,
    'desired_formation_id'    => absint( $posted( 'desired_formation_id' ) ) ?: null,
    'company_email'           => sanitize_email( (string) $posted( 'company_email' ) ),
    'employer_name'           => sanitize_text_field( (string) $posted( 'employer_name' ) ),
    'website'                 => esc_url_raw( (string) $posted( 'website' ) ),
    'activity'                => sanitize_text_field( (string) $posted( 'activity' ) ),
    'session_label'           => sanitize_text_field( (string) $posted( 'session_label' ) ),
    'status'                  => sanitize_text_field( (string) $posted( 'status', 'À traiter' ) ) ?: 'À traiter',
    'rdv_at'                  => $this->sanitize_datetime_input( (string) $posted( 'rdv_at' ) ),
    'rdv_notes'               => sanitize_textarea_field( (string) $posted( 'rdv_notes' ) ),
    'last_followup'           => sanitize_text_field( (string) $posted( 'last_followup' ) ),
    'assigned_to'             => sanitize_text_field( (string) $posted( 'assigned_to' ) ),
    'source'                  => sanitize_text_field( (string) $posted( 'source' ) ),
    'copy_recipients'         => implode( ', ', $copy_recipients ),
    'copy_recipients_json'    => ! empty( $copy_recipients ) ? wp_json_encode( $copy_recipients ) : '',
    'comment_text'            => sanitize_textarea_field( (string) $posted( 'comment_text' ) ),
    'contact_alert_at'        => $this->sanitize_datetime_input( (string) $posted( 'contact_alert_at' ) ),
  );

  $data['birth_date'] = $data['birth_date'] ?: null;

  /* hotfix9 — Si une formation est sélectionnée, synchroniser desired_training avec son titre */
  if ( ! empty( $data['desired_formation_id'] ) ) {
    $linked_formation = $this->get_formation( (int) $data['desired_formation_id'] );
    if ( $linked_formation && ! empty( $linked_formation->title ) ) {
      $data['desired_training'] = (string) $linked_formation->title;
    }
  }
  $redirect_to = isset( $_POST['redirect_to'] ) ? sanitize_key( wp_unslash( $_POST['redirect_to'] ) ) : '';
  $return_tab  = isset( $_POST['return_tab'] ) ? sanitize_key( wp_unslash( $_POST['return_tab'] ) ) : '';
  $target_args = array( 'tab' => 'prospects', 'action' => $prospect_id ? 'edit' : 'new', 'item_id' => $prospect_id );
  if ( '' !== $redirect_to ) {
    $target_args['redirect_to'] = $redirect_to;
  }
  if ( '' !== $return_tab ) {
    $target_args['return_tab'] = $return_tab;
  }

  if ( empty( $data['profile_type'] ) ) {
    $this->store_prospect_form_state( $input, array( 'profile_type' ) );
    $this->redirect_to_portal( 'prospects', 'Veuillez renseigner le profil du prospect.', 'error', $target_args );
  }

  if ( $this->is_individual_prospect_profile( $data['profile_type'] ) ) {
    if ( empty( $data['gender'] ) || empty( $data['first_name'] ) || empty( $data['last_name'] ) || empty( $data['email'] ) ) {
      $required_fields = array();
      foreach ( array( 'gender', 'first_name', 'last_name', 'email' ) as $field_key ) {
        if ( empty( $data[ $field_key ] ) ) {
          $required_fields[] = $field_key;
        }
      }
      $this->store_prospect_form_state( $input, $required_fields );
      $this->redirect_to_portal( 'prospects', 'Pour un prospect particulier ou salarié, genre, prénom, nom et e-mail sont obligatoires.', 'error', $target_args );
    }
    $data['company_name'] = '';
    $data['siret'] = '';
    $data['naf_code'] = '';
    $data['signer_first_name'] = '';
    $data['signer_last_name'] = '';
    $data['signer_quality'] = '';
    $data['signer_email'] = '';
    $data['signer_phone'] = '';
    $data['company_phone'] = '';
    $data['additional_contacts_json'] = '';
  } else {
    if ( empty( $data['company_name'] ) || empty( $data['siret'] ) || empty( $data['signer_first_name'] ) || empty( $data['signer_last_name'] ) ) {
      $required_fields = array();
      foreach ( array( 'company_name', 'siret', 'signer_first_name', 'signer_last_name' ) as $field_key ) {
        if ( empty( $data[ $field_key ] ) ) {
          $required_fields[] = $field_key;
        }
      }
      $this->store_prospect_form_state( $input, $required_fields );
      $this->redirect_to_portal( 'prospects', 'Pour un prospect entreprise ou indépendant, entreprise, SIRET et nom / prénom du signataire sont obligatoires.', 'error', $target_args );
    }
    /* Validation format SIRET — exactement 14 chiffres */
    if ( ! empty( $data['siret'] ) && strlen( preg_replace( '/\D/', '', $data['siret'] ) ) !== 14 ) {
      $this->store_prospect_form_state( $input, array( 'siret' ) );
      $this->redirect_to_portal( 'prospects', 'Le SIRET doit contenir exactement 14 chiffres.', 'error', $target_args );
    }
    $data['gender'] = '';
    $data['birth_date'] = null;
    $data['is_france_travail'] = '';
    $data['education_level'] = '';
    $data['accessibility_needs'] = '';
  }

  $this->ensure_storage_ready();
  $this->ensure_prospect_schema_integrity();
  global $wpdb;
  $now = current_time( 'mysql' );

  if ( $prospect_id ) {
    $data['updated_at'] = $now;
    $result = $wpdb->update( $this->prospect_table, $data, array( 'id' => $prospect_id ) );
    $message = 'Prospect mis à jour.';
    $error_context = 'Impossible de mettre à jour le prospect.';
  } else {
    $data['created_at'] = $now;
    $data['updated_at'] = $now;
    $result = $wpdb->insert( $this->prospect_table, $data );
    $prospect_id = (int) $wpdb->insert_id;
    $message = 'Prospect créé.';
    $error_context = 'Impossible de créer le prospect.';
  }

  if ( false === $result ) {
    $db_error = (string) $wpdb->last_error;
    if ( false !== stripos( $db_error, 'Unknown column' ) || false !== stripos( $db_error, "doesn't exist" ) ) {
      $this->ensure_prospect_schema_integrity();
      if ( $prospect_id ) {
        $result = $wpdb->update( $this->prospect_table, $data, array( 'id' => $prospect_id ) );
      } else {
        $result = $wpdb->insert( $this->prospect_table, $data );
        $prospect_id = (int) $wpdb->insert_id;
      }
    }

    if ( false === $result ) {
      $this->store_prospect_form_state( $input );
      $this->log_prospect_save_failure( $error_context, $prospect_id, $data );
      $this->redirect_to_portal( 'prospects', $error_context . ' Une erreur technique est survenue.', 'error', $target_args );
    }
  }

  if ( 'needs' === $redirect_to || 'recueil_besoins' === $redirect_to ) {
    if ( is_admin() && isset( $_REQUEST['page'] ) && 'acdc-of-prospects' === $_REQUEST['page'] ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-needs&action=new&prospect_id=' . (int) $prospect_id . '&notice=' . rawurlencode( 'Prospect créé.' ) . '&notice_type=success' ) );
      exit;
    }

    $this->redirect_to_portal(
      'needs',
      'Prospect créé.',
      'success',
      array(
        'action' => 'new',
        'prospect_id' => (int) $prospect_id,
      )
    );
  }

  $return_after_save = $this->acdc_get_post_return_after_save( 'view', array( 'view', 'edit', 'list', 'new' ) );
  $redirect_extra = array(
    'saved_prospect_id' => $prospect_id,
    '_acdc_rt'          => time(),
  );

  // Cascade après sauvegarde réussie
  if ( false !== $result && $prospect_id ) {
    $this->acdc_run_cascade_after_prospect_save( $prospect_id, $data );
  }

  if ( in_array( $return_after_save, array( 'view', 'edit' ), true ) ) {
    $redirect_extra['action']  = $return_after_save;
    $redirect_extra['item_id'] = $prospect_id;
  } else {
    $redirect_extra['action'] = $return_after_save;
  }

  $this->redirect_with_db_result(
    $result,
    $message,
    $error_context,
    'prospects',
    ( is_admin() && isset( $_REQUEST['page'] ) && 'acdc-of-prospects' === $_REQUEST['page'] ),
    'acdc-of-prospects',
    $redirect_extra,
    false
  );
}



public function handle_delete_prospect() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  $prospect_id = isset( $_GET['prospect_id'] ) ? absint( $_GET['prospect_id'] ) : 0;
  check_admin_referer( 'acdc_delete_prospect_' . $prospect_id );
  global $wpdb;
  if ( $prospect_id ) {
    $convention_count = (int) $wpdb->get_var( $wpdb->prepare(
      "SELECT COUNT(*) FROM {$this->registration_contract_table} WHERE source_prospect_id = %d",
      $prospect_id
    ) );
    if ( $convention_count ) {
      $msg = 'Suppression impossible : ce prospect est lié à une convention ou un contrat de formation.';
      if ( is_admin() && isset( $_REQUEST['page'] ) && 'acdc-of-prospects' === $_REQUEST['page'] ) {
        wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-prospects&notice=' . rawurlencode( $msg ) . '&notice_type=error' ) );
        exit;
      }
      $this->redirect_to_portal( 'prospects', $msg, 'error' );
    }
    $wpdb->delete( $this->prospect_rdv_table, array( 'prospect_id' => $prospect_id ) );
    $wpdb->delete( $this->prospect_table, array( 'id' => $prospect_id ) );
  }
  if ( is_admin() && isset( $_REQUEST['page'] ) && 'acdc-of-prospects' === $_REQUEST['page'] ) {
    wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-prospects&notice=' . rawurlencode( 'Prospect supprimé.' ) . '&notice_type=success' ) );
    exit;
  }
  $this->redirect_to_portal( 'prospects', 'Prospect supprimé.', 'success' );
}

public function handle_quick_update_prospect_field() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  check_admin_referer( 'acdc_quick_update_prospect_field' );

  $prospect_id = isset( $_POST['prospect_id'] ) ? absint( $_POST['prospect_id'] ) : 0;
  $field       = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
  $value       = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';

  if ( ! $prospect_id ) {
    wp_send_json_error( array( 'message' => 'Prospect introuvable.' ), 400 );
  }

  $prospect = $this->get_prospect( $prospect_id );
  if ( ! $prospect ) {
    wp_send_json_error( array( 'message' => 'Prospect introuvable.' ), 404 );
  }

  if ( 'assigned_to' === $field ) {
    $allowed = $this->get_assignment_options();
    if ( '' !== $value && ! array_key_exists( $value, $allowed ) ) {
      wp_send_json_error( array( 'message' => 'Responsable invalide.' ), 400 );
    }
  } elseif ( 'status' === $field ) {
    $allowed = $this->get_prospect_status_options();
    if ( ! array_key_exists( $value, $allowed ) ) {
      wp_send_json_error( array( 'message' => 'Statut invalide.' ), 400 );
    }
  } else {
    wp_send_json_error( array( 'message' => 'Champ non autorisé.' ), 400 );
  }

  global $wpdb;
  $updated = $wpdb->update(
    $this->prospect_table,
    array(
      $field        => $value,
      'updated_at'  => current_time( 'mysql' ),
    ),
    array( 'id' => $prospect_id ),
    array( '%s', '%s' ),
    array( '%d' )
  );

  if ( false === $updated ) {
    wp_send_json_error( array( 'message' => 'Impossible de mettre à jour le prospect.' ), 500 );
  }

  $label = '—';
  if ( 'assigned_to' === $field ) {
    $options = $this->get_assignment_options();
    $label   = isset( $options[ $value ] ) ? $options[ $value ] : ( '' !== $value ? $value : '—' );
  } elseif ( 'status' === $field ) {
    $options = $this->get_prospect_status_options();
    $label   = isset( $options[ $value ] ) ? $options[ $value ] : ( '' !== $value ? $value : '—' );
  } elseif ( '' !== $value ) {
    $label = $value;
  }

  wp_send_json_success(
    array(
      'prospect_id' => $prospect_id,
      'field'       => $field,
      'value'       => $value,
      'label'       => $label,
    )
  );
}

/**
 * Actions groupées sur les prospects (sélection multiple depuis la liste) :
 * suppression, changement de statut ou attribution d'un responsable.
 */
public function handle_bulk_prospect_action() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  check_admin_referer( 'acdc_bulk_prospect_action' );

  $ids = array();
  if ( isset( $_POST['prospect_ids'] ) && is_array( $_POST['prospect_ids'] ) ) {
    $ids = array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['prospect_ids'] ) ) ) ) );
  }
  $bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
  $bulk_value  = isset( $_POST['bulk_value'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_value'] ) ) : '';
  $is_admin_page = is_admin() && isset( $_REQUEST['page'] ) && 'acdc-of-prospects' === $_REQUEST['page'];

  $redirect = function ( $message, $type ) use ( $is_admin_page ) {
    if ( $is_admin_page ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-prospects&notice=' . rawurlencode( $message ) . '&notice_type=' . $type ) );
      exit;
    }
    $this->redirect_to_portal( 'prospects', $message, $type );
  };

  if ( empty( $ids ) ) {
    $redirect( 'Aucun prospect sélectionné.', 'error' );
  }
  if ( '' === $bulk_action ) {
    $redirect( 'Aucune action groupée sélectionnée.', 'error' );
  }

  global $wpdb;
  $now = current_time( 'mysql' );

  if ( 'delete' === $bulk_action ) {
    $deleted = 0;
    $skipped = 0;
    foreach ( $ids as $pid ) {
      $convention_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$this->registration_contract_table} WHERE source_prospect_id = %d",
        $pid
      ) );
      if ( $convention_count ) {
        $skipped++;
        continue;
      }
      $wpdb->delete( $this->prospect_rdv_table, array( 'prospect_id' => $pid ) );
      $wpdb->delete( $this->prospect_table, array( 'id' => $pid ) );
      $deleted++;
    }
    $message = sprintf( '%d prospect(s) supprimé(s).', $deleted );
    if ( $skipped ) {
      $message .= ' ' . sprintf( '%d ignoré(s) car lié(s) à une convention ou un contrat.', $skipped );
    }
    $redirect( $message, $deleted > 0 ? 'success' : 'error' );
  }

  if ( 'status' === $bulk_action ) {
    $allowed = $this->get_prospect_status_options();
    if ( ! array_key_exists( $bulk_value, $allowed ) ) {
      $redirect( 'Statut invalide.', 'error' );
    }
    $updated = 0;
    foreach ( $ids as $pid ) {
      $result = $wpdb->update(
        $this->prospect_table,
        array( 'status' => $bulk_value, 'updated_at' => $now ),
        array( 'id' => $pid ),
        array( '%s', '%s' ),
        array( '%d' )
      );
      if ( false !== $result ) {
        $updated++;
      }
    }
    $redirect( sprintf( 'Statut « %s » appliqué à %d prospect(s).', $allowed[ $bulk_value ], $updated ), 'success' );
  }

  if ( 'assign' === $bulk_action ) {
    $allowed = $this->get_assignment_options();
    if ( '' !== $bulk_value && ! array_key_exists( $bulk_value, $allowed ) ) {
      $redirect( 'Responsable invalide.', 'error' );
    }
    $updated = 0;
    foreach ( $ids as $pid ) {
      $result = $wpdb->update(
        $this->prospect_table,
        array( 'assigned_to' => $bulk_value, 'updated_at' => $now ),
        array( 'id' => $pid ),
        array( '%s', '%s' ),
        array( '%d' )
      );
      if ( false !== $result ) {
        $updated++;
      }
    }
    $label = ( '' !== $bulk_value && isset( $allowed[ $bulk_value ] ) ) ? $allowed[ $bulk_value ] : 'Non attribué';
    $redirect( sprintf( '« %s » attribué à %d prospect(s).', $label, $updated ), 'success' );
  }

  $redirect( 'Action groupée inconnue.', 'error' );
}


/* =========================================================
   * CASCADE — Propagation après sauvegarde d'un prospect
   * ======================================================= */

  private function acdc_run_cascade_after_prospect_save( $prospect_id, $data ) {
    global $wpdb;
    $now          = current_time( 'mysql' );
    $is_individual = $this->is_individual_prospect_profile( (string) ( $data['profile_type'] ?? '' ) );

    /* ── 1. Synchronisation du fiche entreprise (acdc_of_companies) ── */
    if ( ! $is_individual ) {
      $company_id = (int) $this->acdc_find_company_id_from_prospect( (object) $data );
      if ( $company_id ) {
        $company_update = array(
          'name'              => (string) ( $data['company_name']     ?? '' ),
          'siret'             => (string) ( $data['siret']            ?? '' ),
          'naf_code'          => (string) ( $data['naf_code']         ?? '' ),
          'address'           => (string) ( $data['address']          ?? '' ),
          'postal_code'       => (string) ( $data['postal_code']      ?? '' ),
          'city'              => (string) ( $data['city']             ?? '' ),
          'email'             => (string) ( $data['company_email']    ?: ( $data['email'] ?? '' ) ),
          'phone'             => (string) ( $data['company_phone']    ?: ( $data['phone'] ?? '' ) ),
          'signer_first_name' => (string) ( $data['signer_first_name'] ?? '' ),
          'signer_last_name'  => (string) ( $data['signer_last_name']  ?? '' ),
          'signer_quality'    => (string) ( $data['signer_quality']    ?? '' ),
          'updated_at'        => $now,
        );
        $wpdb->update( $this->company_table, $company_update, array( 'id' => $company_id ) );

        /* ── 2. Synchronisation du contact lié à l'entreprise ── */
        $contact_id = (int) $this->acdc_find_contact_id_from_prospect( (object) $data, $company_id );
        if ( $contact_id ) {
          $wpdb->update(
            $this->contact_table,
            array(
              'first_name' => (string) ( $data['first_name'] ?? '' ),
              'last_name'  => (string) ( $data['last_name']  ?? '' ),
              'email'      => (string) ( $data['email']      ?? '' ),
              'phone'      => (string) ( $data['phone']      ?? '' ),
              'job_title'  => (string) ( $data['job_title']  ?? '' ),
              'updated_at' => $now,
            ),
            array( 'id' => $contact_id )
          );
        }
      }
    } else {
      /* Prospect individuel : synchroniser le contact par e-mail si existant */
      $email = sanitize_email( (string) ( $data['email'] ?? '' ) );
      if ( $email ) {
        $contact_id = (int) $wpdb->get_var( $wpdb->prepare(
          "SELECT id FROM {$this->contact_table} WHERE email = %s LIMIT 1",
          $email
        ) );
        if ( $contact_id ) {
          $wpdb->update(
            $this->contact_table,
            array(
              'first_name' => (string) ( $data['first_name'] ?? '' ),
              'last_name'  => (string) ( $data['last_name']  ?? '' ),
              'phone'      => (string) ( $data['phone']      ?? '' ),
              'job_title'  => (string) ( $data['job_title']  ?? '' ),
              'updated_at' => $now,
            ),
            array( 'id' => $contact_id )
          );
        }
      }
    }

    /* ── 3. Synchronisation des conventions/contrats non encore signés ── */
    if ( ! $is_individual ) {
      $unsigned_contracts = $wpdb->get_results( $wpdb->prepare(
        "SELECT id FROM {$this->registration_contract_table}
         WHERE source_prospect_id = %d
           AND ( signature_completed_at IS NULL OR signature_completed_at = '' )",
        $prospect_id
      ) );
      if ( $unsigned_contracts ) {
        $contract_signer_update = array(
          'commanditaire_signer_last_name'  => (string) ( $data['signer_last_name']  ?? '' ),
          'commanditaire_signer_first_name' => (string) ( $data['signer_first_name'] ?? '' ),
          'updated_at'                      => $now,
        );
        foreach ( $unsigned_contracts as $uc ) {
          $wpdb->update( $this->registration_contract_table, $contract_signer_update, array( 'id' => (int) $uc->id ) );
        }
      }
    }

    /* ── 4. Synchronisation des recueils liés (financement prévu) ── */
    $funding = (string) ( $data['planned_funding'] ?? '' );
    if ( '' !== $funding ) {
      $wpdb->update(
        $this->need_table,
        array( 'planned_funding' => $funding, 'updated_at' => $now ),
        array( 'source_prospect_id' => $prospect_id )
      );
    }
  }


public function handle_save_prospect_rdv() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  check_admin_referer( 'acdc_save_prospect_rdv' );

  $prospect_id = isset( $_POST['prospect_id'] ) ? absint( $_POST['prospect_id'] ) : 0;
  $prospect = $prospect_id ? $this->get_prospect( $prospect_id ) : null;
  if ( ! $prospect ) {
    $this->redirect_to_portal( 'prospect_followup', 'Prospect introuvable.', 'error' );
  }

  $input = isset( $_POST['rdv'] ) && is_array( $_POST['rdv'] ) ? wp_unslash( $_POST['rdv'] ) : array();
  $current_rdv = null;
  if ( ! empty( $input['rdv_id'] ) ) {
    global $wpdb;
    $current_rdv = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->prospect_rdv_table} WHERE id = %d AND prospect_id = %d", absint( $input['rdv_id'] ), $prospect_id ) );
    if ( ! $current_rdv ) {
      $this->redirect_to_portal( 'prospect_followup', 'Rendez-vous introuvable.', 'error', array( 'tab' => 'prospect_followup', 'action' => 'view', 'item_id' => $prospect_id ) );
    }
  }
  $rdv_at = isset( $input['rdv_at'] ) ? $this->sanitize_datetime_input( $input['rdv_at'] ) : null;
  $info_html = isset( $input['info_html'] ) ? wp_kses_post( $input['info_html'] ) : '';
  $rdv_id = isset( $input['rdv_id'] ) ? absint( $input['rdv_id'] ) : 0;
  $meeting_mode = isset( $input['meeting_mode'] ) ? sanitize_key( $input['meeting_mode'] ) : '';
  $meeting_link = isset( $input['meeting_link'] ) ? esc_url_raw( trim( (string) $input['meeting_link'] ) ) : '';
  $comment_text = '';
  $notify_email = ! empty( $input['notify_email'] ) ? 1 : 0;
  $timezone_label = wp_timezone_string() ? wp_timezone_string() : 'Europe/Paris';
  $back_args = array( 'tab' => 'prospect_followup', 'action' => 'view', 'item_id' => $prospect_id, 'open_rdv' => 1 );
  $success_args = array( 'tab' => 'prospect_followup', 'action' => 'view', 'item_id' => $prospect_id );

  if ( empty( $rdv_at ) ) {
    $this->redirect_to_portal( 'prospect_followup', 'Veuillez renseigner une date de rendez-vous.', 'error', $back_args );
  }

  $allowed_meeting_modes = array( 'visioconference', 'telephonique', 'au_bureau', 'chez_le_client' );
  if ( ! in_array( $meeting_mode, $allowed_meeting_modes, true ) ) {
    $this->redirect_to_portal( 'prospect_followup', 'Veuillez sélectionner un type de rendez-vous.', 'error', $back_args );
  }
  /* M6 — Validation SYNTAXIQUE du lien visio (http/https bien formé). wp_http_validate_url()
     exigeait une URL joignable (résolution DNS, IP non privée) et rejetait des liens pourtant
     valides (ex. un sous-domaine pas encore actif). Un lien de réunion n'a pas à être joignable
     depuis le serveur au moment de la saisie. */
  $meeting_link_valid = ( '' !== $meeting_link )
    && ( false !== filter_var( $meeting_link, FILTER_VALIDATE_URL ) )
    && in_array( strtolower( (string) wp_parse_url( $meeting_link, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true );
  if ( 'visioconference' === $meeting_mode && ! $meeting_link_valid ) {
    $this->redirect_to_portal( 'prospect_followup', 'Veuillez renseigner un lien de visioconférence valide (URL commençant par https://).', 'error', $back_args );
  }

  switch ( $meeting_mode ) {
    case 'visioconference':
      $comment_text = 'Rendez-vous en visioconférence — lien : ' . $meeting_link;
      $mode_html = '<p style="font-size:18px;line-height:1.7;margin:30px 0 0;"><strong>Modalité du rendez-vous :</strong> Le rendez-vous se tiendra en visioconférence. Voici le lien de connexion : <a href="' . esc_url( $meeting_link ) . '" style="color:#c59a2a;text-decoration:underline;">' . esc_html( $meeting_link ) . '</a>.</p>';
      break;
    case 'telephonique':
      $comment_text = 'Rendez-vous téléphonique — nous vous appellerons à la date et à l’heure convenues.';
      $mode_html = '<p style="font-size:18px;line-height:1.7;margin:30px 0 0;"><strong>Modalité du rendez-vous :</strong> Nous vous appellerons à la date et à l’heure convenues.</p>';
      break;
    case 'au_bureau':
      $branding_rdv  = $this->get_branding_options();
      $acdc_addr_rdv = trim(
        ( ! empty( $branding_rdv['address'] )     ? (string) $branding_rdv['address']     : '7 avenue Paul Cézanne' ) . ', ' .
        ( ! empty( $branding_rdv['postal_code'] ) ? (string) $branding_rdv['postal_code'] : '83310' ) . ' ' .
        ( ! empty( $branding_rdv['city'] )        ? (string) $branding_rdv['city']        : 'Cogolin' )
      );
      $comment_text = 'Rendez-vous dans nos bureaux — ' . $acdc_addr_rdv . '.';
      $mode_html = '<p style="font-size:18px;line-height:1.7;margin:30px 0 0;"><strong>Modalité du rendez-vous :</strong> Le rendez-vous aura lieu dans nos bureaux, au ' . esc_html( $acdc_addr_rdv ) . '.</p>';
      break;
    case 'chez_le_client':
    default:
      $comment_text = 'Rendez-vous dans vos bureaux à la date et à l’heure convenues.';
      $mode_html = '<p style="font-size:18px;line-height:1.7;margin:30px 0 0;"><strong>Modalité du rendez-vous :</strong> Le rendez-vous se tiendra dans vos bureaux à la date et à l’heure convenues.</p>';
      break;
  }

  $info_html = trim( $info_html );
  $info_html = '' !== $info_html ? $info_html . $mode_html : $mode_html;

  $branding = $this->get_branding_options();
  $subject = 'Confirmation de votre rendez-vous avec ' . ( ! empty( $branding['company_name'] ) ? $branding['company_name'] : 'ACDC Formation' );
  $primary_email = $this->get_prospect_primary_email( $prospect );
  $copy_recipients = $this->get_prospect_copy_recipients( $prospect );

  global $wpdb;
  $this->ensure_storage_ready();
  $now = current_time( 'mysql' );
  $appointment = array(
    'prospect_id'    => $prospect_id,
    'rdv_at'         => $rdv_at,
    'timezone_label' => $timezone_label,
    'info_html'      => $info_html,
    'comment_text'   => $comment_text,
    'notify_email'   => $notify_email,
    'email_subject'  => $subject,
    'email_to'       => $primary_email,
    'email_cc'       => ! empty( $copy_recipients ) ? wp_json_encode( $copy_recipients ) : '',
    'updated_at'     => $now,
  );
  if ( $current_rdv ) {
    $updated = $wpdb->update(
      $this->prospect_rdv_table,
      $appointment,
      array( 'id' => (int) $current_rdv->id ),
      array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ),
      array( '%d' )
    );
    if ( false === $updated ) {
      $this->redirect_to_portal( 'prospect_followup', 'Impossible de reporter le rendez-vous.', 'error', $back_args );
    }
    $appointment_id = (int) $current_rdv->id;
    $appointment['created_at'] = ! empty( $current_rdv->created_at ) ? $current_rdv->created_at : $now;
  } else {
    $appointment['created_at'] = $now;
    $inserted = $wpdb->insert(
      $this->prospect_rdv_table,
      $appointment,
      array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
    );
    if ( false === $inserted ) {
      $this->redirect_to_portal( 'prospect_followup', 'Impossible d’enregistrer le rendez-vous.', 'error', $back_args );
    }
    $appointment_id = (int) $wpdb->insert_id;
  }

  $saved_rdv_rows = $this->get_prospect_rdv_rows( $prospect_id );
  $latest_saved_rdv = $this->get_prospect_latest_rdv( $prospect, $saved_rdv_rows );
  $summary_note = $this->get_prospect_rdv_summary( $latest_saved_rdv );
  $latest_saved_rdv_at = ( $latest_saved_rdv && ! empty( $latest_saved_rdv->rdv_at ) ) ? $latest_saved_rdv->rdv_at : $rdv_at;

  $wpdb->update(
    $this->prospect_table,
    array(
      'rdv_at'        => $latest_saved_rdv_at,
      'rdv_notes'     => $summary_note,
      'last_followup' => 'Rendez-vous planifié le ' . mysql2date( 'd/m/Y à H\hi', $latest_saved_rdv_at ) . ( $summary_note ? ' — ' . wp_trim_words( $summary_note, 12, '…' ) : '' ),
      'updated_at'    => $now,
    ),
    array( 'id' => $prospect_id ),
    array( '%s', '%s', '%s', '%s' ),
    array( '%d' )
  );
  $this->maybe_advance_prospect_status( $prospect_id, 'Rendez-vous planifié' );

  $mail_message = $current_rdv ? 'Rendez-vous reporté.' : 'Rendez-vous enregistré.';
  if ( $notify_email ) {
    if ( $primary_email && is_email( $primary_email ) ) {
      $appointment['id'] = $appointment_id;
      $sent = $this->send_prospect_rdv_notification_email( $prospect, $appointment );
      if ( $sent ) {
        $wpdb->update( $this->prospect_rdv_table, array( 'email_sent_at' => current_time( 'mysql' ) ), array( 'id' => $appointment_id ), array( '%s' ), array( '%d' ) );
        $mail_message = 'Rendez-vous enregistré et e-mail de confirmation envoyé.';
      } else {
        $mail_message = 'Rendez-vous enregistré, mais l’e-mail de confirmation n’a pas pu être envoyé.';
      }
    } else {
      $mail_message = 'Rendez-vous enregistré. Aucun e-mail n’a été envoyé, car aucune adresse valide n’a été trouvée pour ce prospect.';
    }
  }

  $return_tab  = isset( $_POST['return_tab'] ) ? sanitize_key( wp_unslash( $_POST['return_tab'] ) ) : 'prospect_followup';
  $return_page = isset( $_POST['page'] ) ? sanitize_key( wp_unslash( $_POST['page'] ) ) : '';

  if ( is_admin() && 'acdc-of-prospects' === $return_page ) {
    wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-prospects&notice=' . rawurlencode( $mail_message ) . '&notice_type=success' ) );
    exit;
  }

  if ( is_admin() && isset( $_REQUEST['page'] ) && 'acdc-of-prospect-followup' === $_REQUEST['page'] ) {
    wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-prospect-followup&action=view&item_id=' . $prospect_id . '&notice=' . rawurlencode( $mail_message ) . '&notice_type=success' ) );
    exit;
  }

  if ( 'prospects' === $return_tab ) {
    $this->redirect_to_portal( 'prospects', $mail_message, 'success' );
  }

  $this->redirect_to_portal( 'prospect_followup', $mail_message, 'success', $success_args );
}

private function ensure_prospect_rdv_post_comment_column() {
  global $wpdb;

  $column = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$this->prospect_rdv_table} LIKE %s", 'post_comment_text' ) );
  if ( $column ) {
    return true;
  }

  $altered = $wpdb->query( "ALTER TABLE {$this->prospect_rdv_table} ADD COLUMN post_comment_text LONGTEXT NULL AFTER comment_text" );

  return false !== $altered;
}

public function handle_save_prospect_rdv_post_comment() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }

  $prospect_id = isset( $_POST['prospect_id'] ) ? absint( $_POST['prospect_id'] ) : 0;
  $rdv_id      = isset( $_POST['rdv_id'] ) ? absint( $_POST['rdv_id'] ) : 0;

  if ( ! $prospect_id || ! $rdv_id ) {
    $this->redirect_to_portal( 'prospect_followup', 'Rendez-vous introuvable.', 'error' );
  }

  check_admin_referer( 'acdc_save_prospect_rdv_post_comment_' . $rdv_id );

  $prospect = $this->get_prospect( $prospect_id );
  if ( ! $prospect ) {
    $this->redirect_to_portal( 'prospect_followup', 'Prospect introuvable.', 'error' );
  }

  global $wpdb;

  if ( ! $this->ensure_prospect_rdv_post_comment_column() ) {
    $message = 'Impossible de préparer l’enregistrement du commentaire post rendez-vous.';
    if ( isset( $_POST['page'] ) && 'acdc-of-prospect-followup' === sanitize_key( wp_unslash( $_POST['page'] ) ) ) {
      wp_safe_redirect( $this->admin_prospect_followup_url( array( 'action' => 'view', 'item_id' => $prospect_id, 'notice' => rawurlencode( $message ), 'notice_type' => 'error' ) ) );
      exit;
    }
    $this->redirect_to_portal( 'prospect_followup', $message, 'error', array( 'tab' => 'prospect_followup', 'action' => 'view', 'item_id' => $prospect_id ) );
  }

  $rdv_row = $wpdb->get_row(
    $wpdb->prepare(
      "SELECT * FROM {$this->prospect_rdv_table} WHERE id = %d AND prospect_id = %d",
      $rdv_id,
      $prospect_id
    )
  );

  if ( ! $rdv_row ) {
    $this->redirect_to_portal( 'prospect_followup', 'Rendez-vous introuvable.', 'error', array( 'tab' => 'prospect_followup', 'action' => 'view', 'item_id' => $prospect_id ) );
  }

  $post_comment_text = isset( $_POST['post_comment_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['post_comment_text'] ) ) : '';

  $updated = $wpdb->update(
    $this->prospect_rdv_table,
    array(
      'post_comment_text' => $post_comment_text,
      'updated_at'        => current_time( 'mysql' ),
    ),
    array( 'id' => $rdv_id ),
    array( '%s', '%s' ),
    array( '%d' )
  );

  if ( false === $updated ) {
    $message = 'Impossible d’enregistrer le commentaire post rendez-vous.';
    if ( isset( $_POST['page'] ) && 'acdc-of-prospect-followup' === sanitize_key( wp_unslash( $_POST['page'] ) ) ) {
      wp_safe_redirect( $this->admin_prospect_followup_url( array( 'action' => 'view', 'item_id' => $prospect_id, 'notice' => rawurlencode( $message ), 'notice_type' => 'error' ) ) );
      exit;
    }
    $this->redirect_to_portal( 'prospect_followup', $message, 'error', array( 'tab' => 'prospect_followup', 'action' => 'view', 'item_id' => $prospect_id ) );
  }

  $success_message = 'Commentaire post rendez-vous enregistré.';
  if ( isset( $_POST['page'] ) && 'acdc-of-prospect-followup' === sanitize_key( wp_unslash( $_POST['page'] ) ) ) {
    wp_safe_redirect( $this->admin_prospect_followup_url( array( 'action' => 'view', 'item_id' => $prospect_id, 'notice' => rawurlencode( $success_message ), 'notice_type' => 'success' ) ) );
    exit;
  }

  $this->redirect_to_portal( 'prospect_followup', $success_message, 'success', array( 'tab' => 'prospect_followup', 'action' => 'view', 'item_id' => $prospect_id ) );
}

public function handle_cancel_prospect_rdv() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }

  $prospect_id = isset( $_POST['prospect_id'] ) ? absint( $_POST['prospect_id'] ) : 0;
  $rdv_id      = isset( $_POST['rdv_id'] ) ? absint( $_POST['rdv_id'] ) : 0;

  if ( ! $prospect_id || ! $rdv_id ) {
    $this->redirect_to_portal( 'prospect_followup', 'Rendez-vous introuvable.', 'error' );
  }

  check_admin_referer( 'acdc_cancel_prospect_rdv_' . $rdv_id );

  $prospect = $this->get_prospect( $prospect_id );
  if ( ! $prospect ) {
    $this->redirect_to_portal( 'prospect_followup', 'Prospect introuvable.', 'error' );
  }

  global $wpdb;

  $rdv_row = $wpdb->get_row(
    $wpdb->prepare(
      "SELECT * FROM {$this->prospect_rdv_table} WHERE id = %d AND prospect_id = %d",
      $rdv_id,
      $prospect_id
    )
  );

  if ( ! $rdv_row ) {
    $this->redirect_to_portal(
      'prospect_followup',
      'Rendez-vous introuvable.',
      'error',
      array( 'tab' => 'prospect_followup', 'action' => 'view', 'item_id' => $prospect_id )
    );
  }

  $comment_text = ! empty( $rdv_row->comment_text ) ? (string) $rdv_row->comment_text : '';
  if ( false === strpos( $comment_text, '[RDV_ANNULE]' ) ) {
    $comment_text = '[RDV_ANNULE] ' . $comment_text;
  }

  $updated = $wpdb->update(
    $this->prospect_rdv_table,
    array(
      'comment_text' => $comment_text,
      'updated_at'   => current_time( 'mysql' ),
    ),
    array( 'id' => $rdv_id ),
    array( '%s', '%s' ),
    array( '%d' )
  );

  if ( false === $updated ) {
    $target_args = array( 'action' => 'view', 'item_id' => $prospect_id );
    if ( ! empty( $_POST['open_rdv'] ) ) {
      $target_args['open_rdv'] = 1;
    }
    $message = 'Impossible d’annuler le rendez-vous.';
    if ( isset( $_POST['page'] ) && 'acdc-of-prospect-followup' === sanitize_key( wp_unslash( $_POST['page'] ) ) ) {
      wp_safe_redirect( $this->admin_prospect_followup_url( $target_args + array( 'notice' => rawurlencode( $message ), 'notice_type' => 'error' ) ) );
      exit;
    }
    $this->redirect_to_portal( 'prospect_followup', $message, 'error', array( 'tab' => 'prospect_followup', 'action' => 'view', 'item_id' => $prospect_id ) );
  }

  $saved_rdv_rows   = $this->get_prospect_rdv_rows( $prospect_id );
  $latest_saved_rdv = $this->get_prospect_latest_rdv( $prospect, $saved_rdv_rows );

  if ( $latest_saved_rdv && ! empty( $latest_saved_rdv->rdv_at ) ) {
    $latest_saved_rdv_at = $latest_saved_rdv->rdv_at;
    $summary_note        = $this->get_prospect_rdv_summary( $latest_saved_rdv );
    $last_followup       = 'Rendez-vous planifié le ' . mysql2date( 'd/m/Y à H\hi', $latest_saved_rdv_at ) . ( $summary_note ? ' — ' . wp_trim_words( $summary_note, 12, '…' ) : '' );
  } else {
    $latest_saved_rdv_at = null;
    $summary_note        = '';
    $last_followup       = '';
  }

  $wpdb->update(
    $this->prospect_table,
    array(
      'rdv_at'        => $latest_saved_rdv_at,
      'rdv_notes'     => $summary_note,
      'last_followup' => $last_followup,
      'updated_at'    => current_time( 'mysql' ),
    ),
    array( 'id' => $prospect_id ),
    array( '%s', '%s', '%s', '%s' ),
    array( '%d' )
  );

  $success_message = 'Rendez-vous annulé.';
  $target_args = array( 'action' => 'view', 'item_id' => $prospect_id );
  if ( ! empty( $_POST['open_rdv'] ) ) {
    $target_args['open_rdv'] = 1;
  }

  if ( isset( $_POST['page'] ) && 'acdc-of-prospect-followup' === sanitize_key( wp_unslash( $_POST['page'] ) ) ) {
    wp_safe_redirect( $this->admin_prospect_followup_url( $target_args + array( 'notice' => rawurlencode( $success_message ), 'notice_type' => 'success' ) ) );
    exit;
  }

  $this->redirect_to_portal(
    'prospect_followup',
    $success_message,
    'success',
    array( 'tab' => 'prospect_followup', 'action' => 'view', 'item_id' => $prospect_id )
  );
}

}

