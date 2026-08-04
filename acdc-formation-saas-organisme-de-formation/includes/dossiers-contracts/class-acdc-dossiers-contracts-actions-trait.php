<?php
/**
 * ACDC Dossiers / Inscriptions / Conventions-contrats — ACDC_Dossiers_Contracts_Actions_Trait.
 *
 * Extraction micro-incrémentale du sous-bloc métier :
 * dossiers / inscription / conventions-contrats.
 *
 * @since 3.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Dossiers_Contracts_Actions_Trait {


public function handle_save_training_registration() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    $this->redirect_to_login( 'Accès réservé aux administrateurs.', 'error' );
  }

  check_admin_referer( 'acdc_save_training_registration' );

  global $wpdb;
  $registration_id = isset( $_POST['registration_id'] ) ? absint( wp_unslash( $_POST['registration_id'] ) ) : 0;
  $input = isset( $_POST['registration'] ) && is_array( $_POST['registration'] ) ? wp_unslash( $_POST['registration'] ) : array();
  $is_admin_page = is_admin() && isset( $_POST['page'] ) && 'acdc-of-register-training' === sanitize_key( wp_unslash( $_POST['page'] ) );
  $base_admin = 'acdc-of-register-training';

  $belongs_to_group = isset( $input['belongs_to_group'] ) ? sanitize_text_field( $input['belongs_to_group'] ) : '';
  $group_id = isset( $input['group_id'] ) ? absint( $input['group_id'] ) : 0;
  $learner_id = isset( $input['learner_id'] ) ? absint( $input['learner_id'] ) : 0;
  $source_prospect_id = isset( $input['source_prospect_id'] ) ? absint( $input['source_prospect_id'] ) : 0;
  $company_id = isset( $input['company_id'] ) ? absint( $input['company_id'] ) : 0;
  $formation_id = isset( $input['formation_id'] ) ? absint( $input['formation_id'] ) : 0;
  $autofill_contract_id = isset( $input['autofill_contract_id'] ) ? absint( $input['autofill_contract_id'] ) : 0;
  $price_ht = isset( $input['price_ht'] ) ? sanitize_text_field( $input['price_ht'] ) : '';
  $trainer_id = isset( $input['trainer_id'] ) ? absint( $input['trainer_id'] ) : 0;
  $seances_dates_raw = isset( $input['seances_dates'] ) ? sanitize_text_field( $input['seances_dates'] ) : '';
  $is_draft = ! empty( $input['is_draft'] ) ? 1 : 0;

  $error = '';
  if ( ! in_array( $belongs_to_group, array( 'Oui', 'Non' ), true ) ) {
    $error = "Le champ L'apprenant appartient à un groupe est obligatoire.";
  } elseif ( 'Oui' === $belongs_to_group && ! $group_id ) {
    $error = 'Le champ Groupe est obligatoire.';
  } elseif ( 'Non' === $belongs_to_group && ! $learner_id ) {
    $error = 'Le champ Apprenant est obligatoire.';
  } elseif ( ! $formation_id ) {
    $error = 'Le champ Formation est obligatoire.';
  } elseif ( ! $is_draft && '' === trim( $price_ht ) ) {
    $error = "Le champ Tarif de l'action de formation (€ HT) est obligatoire sauf si Sauvegarder en tant que brouillon est activé.";
  }

  if ( $error ) {
    $required_fields = array();
    if ( ! in_array( $belongs_to_group, array( 'Oui', 'Non' ), true ) ) {
      $required_fields[] = 'belongs_to_group';
    } elseif ( 'Oui' === $belongs_to_group && ! $group_id ) {
      $required_fields[] = 'group_id';
    } elseif ( 'Non' === $belongs_to_group && ! $learner_id ) {
      $required_fields[] = 'learner_id';
    }
    if ( ! $formation_id ) {
      $required_fields[] = 'formation_id';
    }
    if ( ! $is_draft && '' === trim( $price_ht ) ) {
      $required_fields[] = 'price_ht';
    }
    $this->acdc_store_form_state( 'training_registration', $input, $required_fields );
    $query = $this->acdc_append_notice_args( array( 'action' => $registration_id ? 'edit' : 'new' ), $error, 'error' );
    if ( $registration_id ) {
      $query['item_id'] = $registration_id;
    }
    if ( $source_prospect_id ) {
      $query['prospect_id'] = $source_prospect_id;
    }
    $target = $is_admin_page ? $this->admin_page_url( $base_admin, $query ) : $this->portal_page_url( array_merge( array( 'tab' => 'register_training' ), $query ) );
    wp_safe_redirect( $target );
    exit;
  }

  $formation = $formation_id ? $this->get_formation( $formation_id ) : null;
  $group = $group_id ? $this->get_group( $group_id ) : null;
  $learner = $learner_id ? $this->get_learner( $learner_id ) : null;
  $company = $company_id ? $this->get_company( $company_id ) : null;
  $contract = $autofill_contract_id ? $this->get_registration_contract( $autofill_contract_id ) : null;

  $group_label = $group ? $group->name : '';
  $learner_label = $learner ? trim( $learner->first_name . ' ' . $learner->usage_last_name ) : '';
  $learners_label = '';
  $learner_ids = '';
  if ( 'Oui' === $belongs_to_group && $group ) {
    $learner_ids = isset( $group->learner_ids ) ? sanitize_text_field( $group->learner_ids ) : '';
    $learners_label = implode( ', ', $this->get_group_learner_names( $group ) );
  } elseif ( $learner_id ) {
    $learner_ids = (string) $learner_id;
    $learners_label = $learner_label;
  }

  $formation_title = $formation ? $formation->title : '';
  $company_label = $company ? $company->name : '';
  $autofill_contract_label = $contract ? $contract->title : '';

  // ACDC 3.25.72 — Formateur désigné
  $trainer = $trainer_id ? $this->get_trainer( $trainer_id ) : null;
  $trainer_label = $trainer ? trim( (string) $trainer->first_name . ' ' . (string) $trainer->last_name ) : '';

  $title = $this->get_training_registration_title( $belongs_to_group, $group_label, $learner_label, $learners_label, $formation_title );

  $data = array(
    'title' => $title,
    'belongs_to_group' => $belongs_to_group,
    'autofill_contract_id' => $autofill_contract_id ? $autofill_contract_id : null,
    'autofill_contract_label' => $autofill_contract_label,
    'group_id' => $group_id ? $group_id : null,
    'group_label' => $group_label,
    'learner_id' => $learner_id ? $learner_id : null,
    'learner_label' => $learner_label,
    'learner_ids' => $learner_ids,
    'learners_label' => $learners_label,
    'company_id' => $company_id ? $company_id : null,
    'company_label' => $company_label,
    'formation_id' => $formation_id ? $formation_id : null,
    'formation_title' => $formation_title,
    'extranet_access' => ! empty( $input['extranet_access'] ) ? 1 : 0,
    'price_ht' => $price_ht,
    'transport_fees_enabled' => ! empty( $input['transport_fees_enabled'] ) ? 1 : 0,
    'meal_fees_enabled' => ! empty( $input['meal_fees_enabled'] ) ? 1 : 0,
    'trainer_id' => $trainer_id ? $trainer_id : null,
    'trainer_label' => $trainer_label,
    'is_draft' => $is_draft,
    'updated_at' => $this->now_mysql(),
  );

  if ( $registration_id ) {
    $result = $wpdb->update( $this->training_registration_table, $data, array( 'id' => $registration_id ) );
    $message = $is_draft ? 'Brouillon mis à jour.' : 'Inscription mise à jour.';
  } else {
    $data['created_at'] = $this->now_mysql();
    $result = $wpdb->insert( $this->training_registration_table, $data );
    if ( $wpdb->insert_id ) {
      $registration_id = (int) $wpdb->insert_id;
      // ACDC 3.23.2 — Statut initial à la création du dossier.
      if ( ! $is_draft ) {
        $this->advance_registration_workflow( $registration_id, 'prospect_cree' );
      }
    }
    $message = $is_draft ? 'Brouillon enregistré.' : 'Inscription enregistrée.';

    // ACDC 3.25.74 — Créer les séances depuis seances_dates
    $created_session_ids = array();
    if ( ! $is_draft && $formation_id && ! empty( $seances_dates_raw ) ) {
      $seances_arr = array();
      foreach ( array_filter( array_map( 'trim', explode( ',', $seances_dates_raw ) ) ) as $d ) {
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) { $seances_arr[] = $d; }
      }
      sort( $seances_arr );
      $now_s = $this->now_mysql();
      // Récupérer location et format depuis la formation
      $formation_for_session = $formation_id ? $this->get_formation( $formation_id ) : null;
      $session_location = '';
      $session_format   = '';
      if ( $formation_for_session ) {
        $addr_parts = array_filter( array(
          ! empty( $formation_for_session->address ) ? (string) $formation_for_session->address : '',
          ! empty( $formation_for_session->city )    ? (string) $formation_for_session->city : '',
        ) );
        $session_location = implode( ', ', $addr_parts );
        $session_format   = ! empty( $formation_for_session->modality ) ? (string) $formation_for_session->modality : '';
      }
      foreach ( $seances_arr as $idx => $sdate ) {
        // Anti-doublon : pas deux séances même formation + même date
        $exists = $wpdb->get_var( $wpdb->prepare(
          "SELECT id FROM {$this->session_table} WHERE formation_id = %d AND start_date = %s LIMIT 1",
          $formation_id, $sdate
        ) );
        if ( $exists ) {
          $created_session_ids[] = (int) $exists;
          continue;
        }
        $session_title = 'Séance J' . ( $idx + 1 ) . ( $formation_title ? ' — ' . $formation_title : '' );
        $wpdb->insert( $this->session_table, array(
          'formation_id'   => $formation_id,
          'company_id'     => $company_id ?: null,
          'title'          => $session_title,
          'start_date'     => $sdate,
          'start_at'       => $sdate . ' 09:00:00',
          'status'         => 'Planifiée',
          'trainer_id'     => $trainer_id ?: null,
          'location'       => $session_location,
          'session_format' => $session_format,
          'is_draft'       => 0,
          'created_at'     => $now_s,
          'updated_at'     => $now_s,
        ) );
        if ( $wpdb->insert_id ) { $created_session_ids[] = (int) $wpdb->insert_id; }
      }
    }

    // ACDC 3.25.74 — Créer automatiquement un dossier par apprenant supplémentaire (convention multi-apprenants)
    if ( ! $is_draft && $autofill_contract_id && $registration_id ) {
      $contract_for_multi = $autofill_contract_id ? $this->get_registration_contract( $autofill_contract_id ) : null;
      if ( $contract_for_multi && ! empty( $contract_for_multi->learner_ids ) ) {
        $all_lids = array_values( array_filter( array_map( 'absint', explode( ',', (string) $contract_for_multi->learner_ids ) ) ) );
        // On a déjà créé le dossier pour $learner_id — créer pour les autres
        foreach ( $all_lids as $other_lid ) {
          if ( $other_lid === $learner_id || ! $other_lid ) { continue; }
          $other_learner = $this->get_learner( $other_lid );
          if ( ! $other_learner ) { continue; }
          $other_label = trim( (string) $other_learner->first_name . ' ' . ( ! empty( $other_learner->usage_last_name ) ? $other_learner->usage_last_name : $other_learner->last_name ) );
          $other_title  = $this->get_training_registration_title( 'Non', '', $other_label, $other_label, $formation_title );
          $other_data   = $data;
          $other_data['learner_id']    = $other_lid;
          $other_data['learner_label'] = $other_label;
          $other_data['learner_ids']   = (string) $other_lid;
          $other_data['learners_label'] = $other_label;
          $other_data['title']          = $other_title;
          $other_data['created_at']     = $this->now_mysql();
          $other_data['updated_at']     = $this->now_mysql();
          // Anti-doublon : ne pas créer si dossier déjà existant pour cet apprenant + formation + contrat
          $dup = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$this->training_registration_table} WHERE learner_id = %d AND formation_id = %d AND autofill_contract_id = %d LIMIT 1",
            $other_lid, $formation_id ?: 0, $autofill_contract_id
          ) );
          if ( $dup ) { continue; }
          $wpdb->insert( $this->training_registration_table, $other_data );
          $other_reg_id = (int) $wpdb->insert_id;
          if ( $other_reg_id ) {
            $this->advance_registration_workflow( $other_reg_id, 'prospect_cree' );
            // Lier à la première séance créée
            if ( ! empty( $created_session_ids ) && ! empty( $other_learner->session_id ) === false ) {
              $wpdb->update( $this->learner_table, array( 'session_id' => $created_session_ids[0] ), array( 'id' => $other_lid ) );
            }
          }
        }
        $message = 'Inscriptions créées (' . count( $all_lids ) . ' apprenants).';
      }
    }

    // ACDC 3.25.72 — Lier l'apprenant principal à la première séance créée
    if ( ! $is_draft && $registration_id && $formation_id && $learner_id ) {
      $target_session_id = ! empty( $created_session_ids ) ? $created_session_ids[0] : null;
      if ( ! $target_session_id ) {
        $first_session = $wpdb->get_row( $wpdb->prepare(
          "SELECT id FROM {$this->session_table} WHERE formation_id = %d AND is_draft = 0 ORDER BY start_date ASC, id ASC LIMIT 1",
          $formation_id
        ) );
        if ( $first_session ) { $target_session_id = (int) $first_session->id; }
      }
      if ( $target_session_id ) {
        $already_linked = (int) $wpdb->get_var( $wpdb->prepare( "SELECT session_id FROM {$this->learner_table} WHERE id = %d", $learner_id ) );
        if ( ! $already_linked ) {
          $wpdb->update( $this->learner_table, array( 'session_id' => $target_session_id ), array( 'id' => $learner_id ) );
        }
      }
    }
  }

  if ( false === $result ) {
    $this->acdc_store_form_state( 'training_registration', $input );
    $error = $this->get_safe_db_error_message( 'Une erreur technique est survenue.' );
    $query = $this->acdc_append_notice_args( array( 'action' => $registration_id ? 'edit' : 'new' ), $error, 'error' );
    if ( $registration_id ) {
      $query['item_id'] = $registration_id;
    }
    if ( $source_prospect_id ) {
      $query['prospect_id'] = $source_prospect_id;
    }
    $target = $is_admin_page ? $this->admin_page_url( $base_admin, $query ) : $this->portal_page_url( array_merge( array( 'tab' => 'register_training' ), $query ) );
    wp_safe_redirect( $target );
    exit;
  }

  $save_and_add = ! empty( $_POST['save_and_add'] );
  $return_after_save = $this->acdc_get_post_return_after_save( $is_draft ? 'list' : 'edit', array( 'edit', 'list', 'new', 'pending' ) );

  // ACDC 3.25.72 — E-mail accès portail formateur si formateur désigné et dossier validé
  if ( ! $is_draft && $trainer_id && $trainer ) {
    $trainer_email = ! empty( $trainer->email ) ? sanitize_email( (string) $trainer->email ) : '';
    if ( $trainer_email && is_email( $trainer_email ) ) {
      $tr_account = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$this->trainer_portal_account_table} WHERE trainer_id = %d",
        $trainer_id
      ) );
      $now_mysql = $this->now_mysql();
      if ( ! $tr_account ) {
        $wpdb->insert( $this->trainer_portal_account_table, array(
          'trainer_id'           => $trainer_id,
          'email'                => $trainer_email,
          'password_hash'        => wp_hash_password( wp_generate_password( 32, true, true ) ),
          'status'               => 'never_activated',
          'must_change_password' => 1,
          'created_at'           => $now_mysql,
          'updated_at'           => $now_mysql,
        ), array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' ) );
        $tr_account_id = (int) $wpdb->insert_id;
        $tr_account = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->trainer_portal_account_table} WHERE id = %d", $tr_account_id ) );
      }
      if ( $tr_account && method_exists( $this, 'trainer_portal_send_activation_email' ) ) {
        $this->trainer_portal_send_activation_email( $tr_account, $trainer );
      }
    }
  }
  if ( $save_and_add ) {
    $target_tab = 'register_training';
  } elseif ( 'pending' === $return_after_save || ( 'list' === $return_after_save && $is_draft ) ) {
    $target_tab = 'registrations_pending';
  } elseif ( 'list' === $return_after_save ) {
    $target_tab = 'registrations';
  } else {
    $target_tab = 'register_training';
  }

  $query = $this->acdc_append_notice_args( array(), $message, 'success' );
  if ( $source_prospect_id ) {
    $query['prospect_id'] = $source_prospect_id;
  }
  if ( $save_and_add || 'new' === $return_after_save ) {
    $query['action'] = 'new';
  } elseif ( $registration_id && 'register_training' === $target_tab ) {
    $query['action'] = 'edit';
    $query['item_id'] = $registration_id;
  }
  $target = $is_admin_page
    ? $this->admin_page_url( $base_admin, array_merge( array( 'tab' => $target_tab ), $query ) )
    : $this->portal_page_url( array_merge( array( 'tab' => $target_tab ), $query ) );
  wp_safe_redirect( $target );
  exit;
}


public function handle_delete_training_registration() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    $this->redirect_to_login( 'Accès réservé aux administrateurs.', 'error' );
  }

  $registration_id = isset( $_GET['registration_id'] ) ? absint( wp_unslash( $_GET['registration_id'] ) ) : 0;
  if ( ! $registration_id ) {
    $this->redirect_to_portal( 'register_training', 'Inscription introuvable.', 'error' );
  }

  check_admin_referer( 'acdc_delete_training_registration_' . $registration_id );

  global $wpdb;
  $wpdb->delete( $this->training_registration_table, array( 'id' => $registration_id ) );

  $return_tab = isset( $_GET['return_tab'] ) ? sanitize_key( wp_unslash( $_GET['return_tab'] ) ) : 'register_training';
  if ( ! in_array( $return_tab, array( 'register_training', 'registrations_pending', 'registrations' ), true ) ) {
    $return_tab = 'register_training';
  }
  if ( is_admin() && isset( $_GET['page'] ) && 'acdc-of-register-training' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
    $this->redirect_to_admin_page( 'acdc-of-register-training', 'Inscription supprimée.', 'success', array( 'tab' => $return_tab ) );
  }
  $this->redirect_to_portal( $return_tab, 'Inscription supprimée.', 'success' );
}

// ACDC 3.25.38 — Suppression complète d'un dossier de formation (inscription + convention + NAD)
public function handle_delete_training_file( $nonce_already_verified = false ) {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    $this->redirect_to_login( 'Accès réservé aux administrateurs.', 'error' );
  }
  $registration_id = isset( $_GET['registration_id'] ) ? absint( wp_unslash( $_GET['registration_id'] ) ) : 0;
  if ( ! $registration_id ) {
    $this->redirect_to_portal( 'trf_inscriptions', 'Dossier introuvable.', 'error' );
  }
  if ( ! $nonce_already_verified ) {
    check_admin_referer( 'acdc_delete_training_file_' . $registration_id );
  }
  global $wpdb;
  // ACDC 3.25.115 — la table contrats n'a pas de registration_id : supprimer par id = autofill_contract_id.
  $reg = $wpdb->get_row( $wpdb->prepare( "SELECT autofill_contract_id FROM {$this->training_registration_table} WHERE id = %d", (int) $registration_id ) );
  if ( $reg && ! empty( $reg->autofill_contract_id ) ) {
    $wpdb->delete( $this->registration_contract_table, array( 'id' => (int) $reg->autofill_contract_id ) );
  }
  $wpdb->delete( $this->need_analysis_table, array( 'dossier_id' => $registration_id ) );
  $wpdb->delete( $this->training_registration_table, array( 'id' => $registration_id ) );
  if ( is_admin() ) {
    $this->redirect_to_admin_page( 'acdc-of-register-training', 'Dossier supprimé.', 'success', array( 'tab' => 'registrations' ) );
  }
  $this->redirect_to_portal( 'trf_inscriptions', 'Dossier supprimé.', 'success' );
}


public function handle_save_registration_contract() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }

  check_admin_referer( 'acdc_save_registration_contract' );
  global $wpdb;

  $input = isset( $_POST['registration_contract'] ) && is_array( $_POST['registration_contract'] ) ? wp_unslash( $_POST['registration_contract'] ) : array();
  $contract_id = isset( $_POST['contract_id'] ) ? absint( $_POST['contract_id'] ) : 0;
  $is_admin_page = is_admin() && isset( $_POST['page'] ) && 'acdc-of-registration-contract' === $_POST['page'];

  $generate_mode = isset( $input['generate_mode'] ) ? sanitize_text_field( $input['generate_mode'] ) : '';
  $commanditaire_type = isset( $input['commanditaire_type'] ) ? sanitize_text_field( $input['commanditaire_type'] ) : '';
  $commanditaire_last_name          = isset( $input['commanditaire_last_name'] ) ? sanitize_text_field( $input['commanditaire_last_name'] ) : '';
  $commanditaire_first_name         = isset( $input['commanditaire_first_name'] ) ? sanitize_text_field( $input['commanditaire_first_name'] ) : '';
  $commanditaire_signer_last_name   = isset( $input['commanditaire_signer_last_name'] ) ? sanitize_text_field( $input['commanditaire_signer_last_name'] ) : '';
  $commanditaire_signer_first_name  = isset( $input['commanditaire_signer_first_name'] ) ? sanitize_text_field( $input['commanditaire_signer_first_name'] ) : '';
  $formation_id = isset( $input['formation_id'] ) ? absint( $input['formation_id'] ) : 0;
  $source_prospect_id = isset( $input['source_prospect_id'] ) ? absint( $input['source_prospect_id'] ) : 0;
  $company_id = isset( $input['company_id'] ) ? absint( $input['company_id'] ) : 0;
  $learner_ids = array();
  if ( isset( $input['learner_ids'] ) ) {
    foreach ( (array) $input['learner_ids'] as $raw_learner_id ) {
      $learner_id = absint( $raw_learner_id );
      if ( $learner_id ) {
        $learner_ids[] = $learner_id;
      }
    }
  }
  $learner_ids = array_values( array_unique( $learner_ids ) );
  $start_date = isset( $input['start_date'] ) ? sanitize_text_field( $input['start_date'] ) : '';
  $end_date = isset( $input['end_date'] ) ? sanitize_text_field( $input['end_date'] ) : '';

  // Séances : dériver start_date/end_date depuis la liste de séances
  $raw_seances = isset( $input['seances_dates'] ) ? sanitize_text_field( wp_unslash( $input['seances_dates'] ) ) : '';
  $seances_arr = array();
  foreach ( array_filter( array_map( 'trim', explode( ',', $raw_seances ) ) ) as $d ) {
    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
      $seances_arr[] = $d;
    }
  }
  if ( ! empty( $seances_arr ) ) {
    sort( $seances_arr );
    $start_date = $seances_arr[0];
    $end_date   = end( $seances_arr );
  }
  $seances_dates_clean = implode( ',', $seances_arr );

  if ( '' === $generate_mode || '' === $commanditaire_type || ! $formation_id ) {
    $required_fields = array();
    if ( '' === $generate_mode ) {
      $required_fields[] = 'generate_mode';
    }
    if ( '' === $commanditaire_type ) {
      $required_fields[] = 'commanditaire_type';
    }
    if ( ! $formation_id ) {
      $required_fields[] = 'formation_id';
    }
    $this->acdc_store_form_state( 'registration_contract', $input, $required_fields );
    $message = 'Veuillez renseigner le mode, le commanditaire et la formation.';
    $error_args = array( 'action' => $contract_id ? 'edit' : 'new', 'item_id' => $contract_id );
    if ( $source_prospect_id ) {
      $error_args['prospect_id'] = $source_prospect_id;
    }
    $target = $is_admin_page ? $this->admin_page_url( 'acdc-of-registration-contract', $this->acdc_append_notice_args( $error_args, $message, 'error' ) ) : $this->portal_page_url( $this->acdc_append_notice_args( array_merge( array( 'tab' => 'registration_contract' ), $error_args ), $message, 'error' ) );
    wp_safe_redirect( $target );
    exit;
  }

  $formation = $this->get_formation( $formation_id );
  $formation_title = $formation && ! empty( $formation->title ) ? (string) $formation->title : '';
  $title = $this->get_registration_contract_title( $commanditaire_type, $formation_title, $start_date );

  $data = array(
    'title' => $title,
    'generate_mode' => $generate_mode,
    'commanditaire_type'             => $commanditaire_type,
    'commanditaire_last_name'         => $commanditaire_last_name,
    'commanditaire_first_name'        => $commanditaire_first_name,
    'commanditaire_signer_last_name'  => $commanditaire_signer_last_name,
    'commanditaire_signer_first_name' => $commanditaire_signer_first_name,
    'source_prospect_id' => $source_prospect_id ? $source_prospect_id : null,
    'company_id' => $company_id ? $company_id : null,
    'learner_ids' => implode( ',', $learner_ids ),
    'formation_id' => $formation_id,
    'formation_title' => $formation_title,
    'start_date'     => $start_date ? $start_date : null,
    'end_date'       => $end_date   ? $end_date   : null,
    'seances_dates'  => $seances_dates_clean ?: null,
    'legal_reference' => isset( $input['legal_reference'] ) ? sanitize_textarea_field( $input['legal_reference'] ) : '',
    'objectives_text' => isset( $input['objectives_text'] ) ? sanitize_textarea_field( $input['objectives_text'] ) : '',
    'accessibility_handicap' => isset( $input['accessibility_handicap'] ) ? wp_kses_post( $input['accessibility_handicap'] ) : '',
    'material_environment' => isset( $input['material_environment'] ) ? wp_kses_post( $input['material_environment'] ) : '',
    'implementation_followup_evaluation' => isset( $input['implementation_followup_evaluation'] ) ? wp_kses_post( $input['implementation_followup_evaluation'] ) : '',
    'cancellation_terms' => isset( $input['cancellation_terms'] ) ? wp_kses_post( $input['cancellation_terms'] ) : '',
    'price_ht' => isset( $input['price_ht'] ) ? sanitize_text_field( $input['price_ht'] ) : '',
    'vat_rate' => isset( $input['vat_rate'] ) ? sanitize_text_field( $input['vat_rate'] ) : '',
    'deposit_enabled' => ! empty( $input['deposit_enabled'] ) ? 1 : 0,
    'deposit_amount_ht' => isset( $input['deposit_amount_ht'] ) ? sanitize_text_field( $input['deposit_amount_ht'] ) : '',
    'public_funding'             => isset( $input['public_funding'] ) ? sanitize_text_field( $input['public_funding'] ) : '',
    'public_funding_name'        => isset( $input['public_funding_name'] ) ? sanitize_text_field( $input['public_funding_name'] ) : '',
    'public_funding_name_custom' => isset( $input['public_funding_name_custom'] ) ? sanitize_text_field( $input['public_funding_name_custom'] ) : '',
    // ACDC 3.21.17 — Délai envoi analyses du besoin
    'nad_send_delay_days' => isset( $input['nad_send_delay_days'] ) ? min( 365, absint( $input['nad_send_delay_days'] ) ) : 0,
    'transport_fees_enabled' => ! empty( $input['transport_fees_enabled'] ) ? 1 : 0,
    'transport_fees_amount_ht' => isset( $input['transport_fees_amount_ht'] ) ? sanitize_text_field( $input['transport_fees_amount_ht'] ) : '',
    'meal_fees_enabled' => ! empty( $input['meal_fees_enabled'] ) ? 1 : 0,
    'meal_fees_amount_ht' => isset( $input['meal_fees_amount_ht'] ) ? sanitize_text_field( $input['meal_fees_amount_ht'] ) : '',
    'financial_provisions' => isset( $input['financial_provisions'] ) ? wp_kses_post( $input['financial_provisions'] ) : '',
    'payment_terms' => isset( $input['payment_terms'] ) ? wp_kses_post( $input['payment_terms'] ) : '',
    'disputes_terms' => isset( $input['disputes_terms'] ) ? wp_kses_post( $input['disputes_terms'] ) : '',
    'withdrawal_delay_days' => isset( $input['withdrawal_delay_days'] ) ? sanitize_text_field( $input['withdrawal_delay_days'] ) : '',
    'additional_sections' => wp_json_encode( $this->normalize_registration_contract_sections( isset( $input['additional_sections'] ) ? $input['additional_sections'] : array() ) ),
    'updated_at' => $this->now_mysql(),
  );

  if ( $contract_id ) {
    $result = $wpdb->update( $this->registration_contract_table, $data, array( 'id' => $contract_id ) );
    $message = 'Convention / contrat mis à jour.';
  } else {
    $data['created_at'] = $this->now_mysql();
    $result = $wpdb->insert( $this->registration_contract_table, $data );
    $contract_id = (int) $wpdb->insert_id;
    $message = 'Convention / contrat créé.';
    // ACDC 3.23.2 — Convention créée → dossier lié passe en attente_signature.
    if ( $contract_id ) {
      $reg_id = $this->find_registration_id_for_contract( $contract_id );
      if ( ! $reg_id && ! empty( $source_prospect_id ) ) {
        $reg_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->training_registration_table} WHERE formation_id = %d AND is_draft = 0 ORDER BY id DESC LIMIT 1", $formation_id ) );
      }
      if ( $reg_id ) {
        $this->advance_registration_workflow( $reg_id, 'en_attente_signature' );
      }
    }
  }

  if ( false === $result ) {
    $this->acdc_store_form_state( 'registration_contract', $input );
    $error = $this->get_safe_db_error_message( 'Une erreur technique est survenue.' );
    $error_args = array( 'action' => $contract_id ? 'edit' : 'new', 'item_id' => $contract_id );
    if ( $source_prospect_id ) {
      $error_args['prospect_id'] = $source_prospect_id;
    }
    $target = $is_admin_page ? $this->admin_page_url( 'acdc-of-registration-contract', $this->acdc_append_notice_args( $error_args, $error, 'error' ) ) : $this->portal_page_url( $this->acdc_append_notice_args( array_merge( array( 'tab' => 'registration_contract' ), $error_args ), $error, 'error' ) );
    wp_safe_redirect( $target );
    exit;
  }

  if ( ! $contract_id ) {
    $target = $is_admin_page ? admin_url( 'admin.php?page=acdc-of-registration-contract&notice=' . rawurlencode( 'Convention / contrat introuvable après enregistrement.' ) . '&notice_type=error' ) : $this->portal_page_url( array( 'tab' => 'registration_contract', 'notice' => rawurlencode( 'Convention / contrat introuvable après enregistrement.' ), 'notice_type' => 'error' ) );
    wp_safe_redirect( $target );
    exit;
  }

  $messages = array( $message );
  $is_new_contract = empty( $_POST['contract_id'] );

  /* ── Cascade statut : convention créée → prospect "Converti" ── */
  if ( $is_new_contract && $source_prospect_id && false !== $result ) {
    $this->maybe_advance_prospect_status( $source_prospect_id, 'Converti' );
  }

  /* ── Création automatique des apprenants ─────────────────────────── */
  $new_learners_input = isset( $input['new_learners'] ) && is_array( $input['new_learners'] ) ? $input['new_learners'] : array();
  $created_learner_ids = array();

  /* Si aucun commanditaire (company_id = 0) mais qu'un prospect source existe,
     créer ou retrouver automatiquement le commanditaire dans acdc_of_companies */
  $effective_company_id = $company_id;
  if ( ! $effective_company_id && $source_prospect_id && method_exists( $this, 'acdc_ensure_company_from_prospect' ) ) {
    $source_prospect_obj = $this->get_prospect( $source_prospect_id );
    if ( $source_prospect_obj ) {
      $effective_company_id = (int) $this->acdc_ensure_company_from_prospect( $source_prospect_obj );
      /* Si un commanditaire vient d'être créé ou retrouvé, mettre à jour la convention */
      if ( $effective_company_id && ! $company_id ) {
        $wpdb->update(
          $this->registration_contract_table,
          array( 'company_id' => $effective_company_id ),
          array( 'id' => $contract_id )
        );
      }
    }
  }

  /* Cas Particulier/Salarié : créer l'apprenant depuis les données commanditaire si aucun apprenant saisi */
  $is_indiv_cmd = in_array( $commanditaire_type, array( 'Particulier', 'Salarié', 'Apprenant' ), true );
  if ( $is_indiv_cmd && $is_new_contract && empty( $new_learners_input ) ) {
    /* Récupérer l'email depuis le prospect source */
    $indiv_email = '';
    if ( $source_prospect_id ) {
      $indiv_prospect = $this->get_prospect( $source_prospect_id );
      if ( $indiv_prospect && method_exists( $this, 'get_prospect_primary_email' ) ) {
        $indiv_email = $this->get_prospect_primary_email( $indiv_prospect );
      } elseif ( $indiv_prospect && ! empty( $indiv_prospect->email ) ) {
        $indiv_email = sanitize_email( (string) $indiv_prospect->email );
      }
    }
    $new_learners_input = array(
      array(
        'first_name' => $commanditaire_first_name,
        'last_name'  => $commanditaire_last_name,
        'email'      => $indiv_email,
      ),
    );
  }

  foreach ( $new_learners_input as $nl ) {
    $nl_first = isset( $nl['first_name'] ) ? sanitize_text_field( $nl['first_name'] ) : '';
    $nl_last  = isset( $nl['last_name'] )  ? sanitize_text_field( $nl['last_name'] )  : '';
    $nl_email = isset( $nl['email'] )      ? sanitize_email( $nl['email'] )            : '';
    if ( '' === $nl_first && '' === $nl_last ) { continue; }
    /* Dédoublonnage : réutiliser un apprenant existant (par email, sinon nom+prénom) au lieu d'insérer un doublon */
    $existing_lid = 0;
    if ( '' !== $nl_email ) {
      $existing_lid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->learner_table} WHERE email = %s LIMIT 1", $nl_email ) );
    }
    if ( ! $existing_lid && ( '' !== $nl_first || '' !== $nl_last ) ) {
      $existing_lid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->learner_table} WHERE first_name = %s AND last_name = %s LIMIT 1", $nl_first, $nl_last ) );
    }
    if ( $existing_lid ) {
      $created_learner_ids[] = $existing_lid;
      continue;
    }
    $nl_data = array(
      'company_id'       => $effective_company_id ?: null,
      'prospect_id'      => $source_prospect_id ?: null,
      'gender'           => '',
      'first_name'       => $nl_first,
      'last_name'        => $nl_last,
      'usage_last_name'  => $nl_last,
      'birth_last_name'  => '',
      'email'            => $nl_email,
      'phone'            => '',
      'birth_date'       => null,
      'birth_place'      => '',
      'address'          => '',
      'address_extra'    => '',
      'postal_code'      => '',
      'city'             => '',
      'is_france_travail' => '',
      'socio_category'   => '',
      'education_level'  => '',
      'job_title'        => '',
      'accessibility_needs' => '',
      'comment_text'     => '',
      'status'           => 'Pré-inscrit',
      'funding'          => ! empty( $input['public_funding'] ) ? sanitize_text_field( $input['public_funding'] ) : '',
      'notes'            => '',
      'created_at'       => $this->now_mysql(),
      'updated_at'       => $this->now_mysql(),
    );
    $wpdb->insert( $this->learner_table, $nl_data );
    $new_lid = (int) $wpdb->insert_id;
    if ( $new_lid ) {
      $created_learner_ids[] = $new_lid;
    }
  }

  /* Fusionner les nouveaux IDs avec les existants et mettre à jour learner_ids sur la convention */
  if ( ! empty( $created_learner_ids ) ) {
    $existing_ids = array_filter( array_map( 'absint', explode( ',', (string) ( $data['learner_ids'] ?? '' ) ) ) );
    $all_ids      = array_values( array_unique( array_merge( $existing_ids, $created_learner_ids ) ) );
    $wpdb->update(
      $this->registration_contract_table,
      array( 'learner_ids' => implode( ',', $all_ids ) ),
      array( 'id' => $contract_id )
    );
    $messages[] = count( $created_learner_ids ) . ' apprenant(s) créé(s) dans le répertoire Apprenants.';
  }
  if ( $is_new_contract ) {
    if ( in_array( $generate_mode, array( 'generate_blank', 'generate_blank_email', 'generate_blank_esign' ), true ) ) {
      $pdf_result = $this->generate_registration_contract_pdf_file( $contract_id );
      if ( ! empty( $pdf_result['path'] ) ) {
        $messages[] = 'PDF de la convention / du contrat généré.';
      }
    }
    if ( in_array( $generate_mode, array( 'generate_blank_email', 'generate_blank_esign' ), true ) ) {
      $email_result = $this->maybe_send_registration_contract_bundle_email( $contract_id );
      $messages[] = $email_result['message'];
      // ACDC 3.21.07 — Déclencher automatiquement la demande de signature après envoi de la convention
      if ( 'generate_blank_esign' !== $generate_mode ) {
        $sig_result = $this->maybe_create_registration_contract_signature_request( $contract_id );
        if ( $sig_result['created'] ) {
          $messages[] = 'Demande de signature électronique envoyée automatiquement.';
        }
      }
    }
    if ( 'generate_blank_esign' === $generate_mode ) {
      $sig_result = $this->maybe_create_registration_contract_signature_request( $contract_id );
      $messages[] = $sig_result['message'];
    }
  }

  $return_after_save = $this->acdc_get_post_return_after_save( 'edit', array( 'edit', 'list', 'new', 'view' ) );
  $success_args = array();
  if ( $source_prospect_id ) {
    $success_args['prospect_id'] = $source_prospect_id;
  }
  if ( 'new' === $return_after_save ) {
    $success_args['action'] = 'new';
  } elseif ( in_array( $return_after_save, array( 'edit', 'view' ), true ) ) {
    $success_args['action'] = $return_after_save;
    $success_args['item_id'] = $contract_id;
  }
  $success_args = $this->acdc_append_notice_args( $success_args, implode( ' ', array_filter( $messages ) ), 'success' );
  $target = $is_admin_page ? $this->admin_page_url( 'acdc-of-registration-contract', $success_args ) : $this->portal_page_url( array_merge( array( 'tab' => 'registration_contract' ), $success_args ) );
  wp_safe_redirect( $target );
  exit;
}


public function handle_delete_registration_contract() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  $contract_id = isset( $_GET['contract_id'] ) ? absint( wp_unslash( $_GET['contract_id'] ) ) : 0;
  if ( ! $contract_id ) {
    $this->redirect_to_portal( 'registration_contract', 'Convention / contrat introuvable.', 'error' );
  }
  check_admin_referer( 'acdc_delete_registration_contract_' . $contract_id );
  global $wpdb;
  $wpdb->delete( $this->registration_contract_table, array( 'id' => $contract_id ) );
  if ( is_admin() && isset( $_REQUEST['page'] ) && 'acdc-of-registration-contract' === $_REQUEST['page'] ) {
    wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-registration-contract&notice=' . rawurlencode( 'Convention / contrat supprimé.' ) . '&notice_type=success' ) );
    exit;
  }
  $this->redirect_to_portal( 'registration_contract', 'Convention / contrat supprimé.', 'success' );
}


/**
 * ACDC 3.21.68 — Envoi initial d'une convention pour signature électronique.
 * Déclenché depuis le menu 3 points de la liste des conventions.
 * Utilise maybe_create_registration_contract_signature_request() déjà éprouvé.
 */
public function handle_send_contract_for_signature() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  $contract_id = isset( $_GET['contract_id'] ) ? absint( wp_unslash( $_GET['contract_id'] ) ) : 0;
  if ( ! $contract_id ) {
    $this->redirect_to_portal( 'registration_contract', 'Convention / contrat introuvable.', 'error' );
  }
  check_admin_referer( 'acdc_send_contract_for_signature_' . $contract_id );

  // Vérifier que la signature n'a pas déjà été envoyée
  $contract = $this->get_registration_contract( $contract_id );
  if ( ! $contract ) {
    $this->redirect_to_portal( 'registration_contract', 'Convention / contrat introuvable.', 'error' );
    return;
  }
  if ( ! empty( $contract->signature_request_id ) && (int) $contract->signature_request_id > 0 ) {
    $is_admin_page = is_admin();
    $msg = 'Une demande de signature a déjà été créée pour cette convention.';
    $target = $is_admin_page
      ? admin_url( 'admin.php?page=acdc-of-registration-contract&action=view&item_id=' . $contract_id . '&notice=' . rawurlencode( $msg ) . '&notice_type=error' )
      : $this->portal_page_url( array( 'tab' => 'registration_contract', 'action' => 'view', 'item_id' => $contract_id, 'notice' => rawurlencode( $msg ), 'notice_type' => 'error' ) );
    wp_safe_redirect( $target );
    exit;
  }

  // Générer le PDF si absent
  if ( method_exists( $this, 'generate_registration_contract_pdf_file' ) ) {
    $pdf_result = $this->generate_registration_contract_pdf_file( $contract_id );
    if ( empty( $pdf_result['path'] ) ) {
      $msg = 'Impossible de générer le PDF avant signature.';
      $is_admin_page = is_admin();
      $target = $is_admin_page
        ? admin_url( 'admin.php?page=acdc-of-registration-contract&action=view&item_id=' . $contract_id . '&notice=' . rawurlencode( $msg ) . '&notice_type=error' )
        : $this->portal_page_url( array( 'tab' => 'registration_contract', 'action' => 'view', 'item_id' => $contract_id, 'notice' => rawurlencode( $msg ), 'notice_type' => 'error' ) );
      wp_safe_redirect( $target );
      exit;
    }
  }

  $result = $this->maybe_create_registration_contract_signature_request( $contract_id );
  $notice_type = $result['created'] ? 'success' : 'error';
  $msg = $result['message'];

  $is_admin_page = is_admin();
  $target = $is_admin_page
    ? admin_url( 'admin.php?page=acdc-of-registration-contract&action=view&item_id=' . $contract_id . '&notice=' . rawurlencode( $msg ) . '&notice_type=' . $notice_type )
    : $this->portal_page_url( array( 'tab' => 'registration_contract', 'action' => 'view', 'item_id' => $contract_id, 'notice' => rawurlencode( $msg ), 'notice_type' => $notice_type ) );
  wp_safe_redirect( $target );
  exit;
}


public function handle_save_contract_params() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }

  check_admin_referer( 'acdc_save_contract_params' );

  $defaults = $this->get_contract_params_defaults();
  $saved  = get_option( 'acdc_of_contract_params', array() );
  $input  = isset( $_POST['contract_params'] ) && is_array( $_POST['contract_params'] ) ? wp_unslash( $_POST['contract_params'] ) : array();
  $clean  = array();

  foreach ( $defaults as $key => $default ) {
    if ( 'additional_sections' === $key ) {
      continue;
    }
    $value = array_key_exists( $key, $input ) ? $input[ $key ] : ( isset( $saved[ $key ] ) ? $saved[ $key ] : $default );
    if ( 'attach_program_rules' === $key ) {
      $clean[ $key ] = ! empty( $value ) ? '1' : '0';
    } elseif ( is_array( $value ) ) {
      $clean[ $key ] = '';
    } else {
      $clean[ $key ] = sanitize_textarea_field( (string) $value );
    }
  }

  $sections = array();
  $raw_sections = isset( $input['additional_sections'] ) && is_array( $input['additional_sections'] ) ? $input['additional_sections'] : array();
  foreach ( $raw_sections as $section ) {
    if ( ! is_array( $section ) ) {
      continue;
    }
    $title = isset( $section['title'] ) ? sanitize_text_field( (string) $section['title'] ) : '';
    $content = isset( $section['content'] ) ? sanitize_textarea_field( (string) $section['content'] ) : '';
    if ( '' === $title && '' === $content ) {
      continue;
    }
    $sections[] = array( 'title' => $title, 'content' => $content );
  }
  if ( empty( $sections ) ) {
    $sections = $defaults['additional_sections'];
  }
  $clean['additional_sections'] = $sections;

  update_option( 'acdc_of_contract_params', $clean, false );
  wp_cache_delete( 'acdc_of_contract_params', 'options' );

  $return_context = isset( $_POST['return_context'] ) ? sanitize_key( wp_unslash( $_POST['return_context'] ) ) : 'front';
  if ( 'admin' === $return_context ) {
    wp_safe_redirect( $this->admin_tab_url( 'contract_parameters', array( 'updated' => 1 ) ) );
    exit;
  }

  wp_safe_redirect( $this->portal_page_url( array(
    'tab' => 'contract_parameters',
    'updated' => 1,
    'notice' => rawurlencode( 'Paramètres de la convention enregistrés.' ),
    'notice_type' => 'success',
  ) ) );
  exit;
}



public function handle_save_subcontract_params() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }

  check_admin_referer( 'acdc_save_subcontract_params' );

  $defaults = $this->get_subcontract_params_defaults();
  $saved  = get_option( 'acdc_of_subcontract_params', array() );
  $input  = isset( $_POST['subcontract_params'] ) && is_array( $_POST['subcontract_params'] ) ? wp_unslash( $_POST['subcontract_params'] ) : array();
  $clean  = array();

  foreach ( $defaults as $key => $default ) {
    if ( 'additional_sections' === $key ) {
      continue;
    }
    $value = array_key_exists( $key, $input ) ? $input[ $key ] : ( isset( $saved[ $key ] ) ? $saved[ $key ] : $default );
    if ( is_array( $value ) ) {
      $clean[ $key ] = '';
    } else {
      $clean[ $key ] = sanitize_textarea_field( (string) $value );
    }
  }

  $sections = array();
  $raw_sections = isset( $input['additional_sections'] ) && is_array( $input['additional_sections'] ) ? $input['additional_sections'] : array();
  foreach ( $raw_sections as $section ) {
    if ( ! is_array( $section ) ) {
      continue;
    }
    $title = isset( $section['title'] ) ? sanitize_text_field( (string) $section['title'] ) : '';
    $content = isset( $section['content'] ) ? sanitize_textarea_field( (string) $section['content'] ) : '';
    if ( '' === $title && '' === $content ) {
      continue;
    }
    $sections[] = array( 'title' => $title, 'content' => $content );
  }
  $clean['additional_sections'] = $sections;

  update_option( 'acdc_of_subcontract_params', $clean, false );
  wp_cache_delete( 'acdc_of_subcontract_params', 'options' );

  $return_context = isset( $_POST['return_context'] ) ? sanitize_key( wp_unslash( $_POST['return_context'] ) ) : 'front';
  if ( 'admin' === $return_context ) {
    wp_safe_redirect( $this->admin_tab_url( 'subcontract_parameters', array( 'updated' => 1 ) ) );
    exit;
  }

  wp_safe_redirect( $this->portal_page_url( array(
    'tab' => 'subcontract_parameters',
    'updated' => 1,
    'notice' => rawurlencode( 'Paramètres du contrat de sous-traitance enregistrés.' ),
    'notice_type' => 'success',
  ) ) );
  exit;
}


public function handle_generate_contract_pdf() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  check_admin_referer( 'acdc_generate_contract_pdf' );
  // SEC-02 : extraction explicite depuis $_GET uniquement (le bouton utilise window.open avec params en GET)
  $safe_request = array(
    'contract_id'           => isset( $_GET['contract_id'] )           ? absint( wp_unslash( $_GET['contract_id'] ) )           : 0,
    'contract_company_id'   => isset( $_GET['contract_company_id'] )   ? absint( wp_unslash( $_GET['contract_company_id'] ) )   : 0,
    'contract_formation_id' => isset( $_GET['contract_formation_id'] ) ? absint( wp_unslash( $_GET['contract_formation_id'] ) ) : 0,
    'contract_session_id'   => isset( $_GET['contract_session_id'] )   ? absint( wp_unslash( $_GET['contract_session_id'] ) )   : 0,
    'contract_learner_id'   => isset( $_GET['contract_learner_id'] )   ? absint( wp_unslash( $_GET['contract_learner_id'] ) )   : 0,
  );
  $context = $this->get_contract_pdf_context( $safe_request );
  $pages = $this->build_contract_pdf_pages( $context );
  $filename = 'convention-de-formation-' . date_i18n( 'Ymd-His' ) . '.pdf';
  $this->render_simple_pdf( $pages, $filename );
}



public function handle_download_registration_contract_document() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  $contract_id = isset( $_GET['contract_id'] ) ? absint( wp_unslash( $_GET['contract_id'] ) ) : 0;
  if ( ! $contract_id ) {
    wp_die( esc_html( 'Convention introuvable.' ) );
  }
  check_admin_referer( 'acdc_download_registration_contract_document_' . $contract_id );
  $contract = $this->get_registration_contract( $contract_id );
  if ( ! $contract ) {
    wp_die( esc_html( 'Convention introuvable.' ) );
  }
  $document = $this->get_registration_contract_document_info( $contract );
  $mode = isset( $_GET['mode'] ) && 'inline' === sanitize_key( wp_unslash( $_GET['mode'] ) ) ? 'inline' : 'attachment';
  $filename = $this->get_registration_contract_display_file_name( $contract, $document );
  $path = ! empty( $document['path'] ) ? (string) $document['path'] : '';
  if ( '' !== $path && file_exists( $path ) ) {
    while ( ob_get_level() ) {
      ob_end_clean();
    }
    nocache_headers();
    header( 'Content-Type: application/pdf' );
    header( 'Content-Length: ' . filesize( $path ) );
    header( 'Content-Disposition: ' . $mode . '; filename="' . sanitize_file_name( $filename ) . '"' );
    readfile( $path );
    exit;
  }
  $context = $this->get_contract_pdf_context( array( 'contract_id' => $contract_id ) );
  $pages = $this->build_contract_pdf_pages( $context );
  $this->render_simple_pdf( $pages, $filename, $mode );
}


public function handle_update_registration_contract_document() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  $contract_id = isset( $_POST['contract_id'] ) ? absint( wp_unslash( $_POST['contract_id'] ) ) : 0;
  if ( ! $contract_id ) {
    wp_die( esc_html( 'Convention introuvable.' ) );
  }
  check_admin_referer( 'acdc_update_registration_contract_document_' . $contract_id );
  if ( empty( $_FILES['contract_document_file']['name'] ) ) {
    wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'contracts_documents', 'notice' => rawurlencode( 'Aucun fichier reçu.' ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
    exit;
  }
  require_once ABSPATH . 'wp-admin/includes/file.php';
  $upload = wp_handle_upload( $_FILES['contract_document_file'], array( 'test_form' => false, 'mimes' => array( 'pdf' => 'application/pdf' ) ) );
  if ( ! empty( $upload['error'] ) ) {
    wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'contracts_documents', 'notice' => rawurlencode( 'Téléversement impossible : ' . $upload['error'] ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
    exit;
  }
  global $wpdb;
  $result = $wpdb->update(
    $this->registration_contract_table,
    array(
      'document_url' => isset( $upload['url'] ) ? esc_url_raw( $upload['url'] ) : '',
      'document_path' => isset( $upload['file'] ) ? sanitize_text_field( $upload['file'] ) : '',
      'updated_at' => $this->now_mysql(),
    ),
    array( 'id' => $contract_id )
  );
  if ( false === $result ) {
    wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'contracts_documents', 'notice' => rawurlencode( 'Erreur lors de la mise à jour.' ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
    exit;
  }
  wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'contracts_documents', 'notice' => rawurlencode( 'Convention mise à jour.' ), 'notice_type' => 'success' ), admin_url( 'admin.php' ) ) );
  exit;
}

  /**
   * ACDC 3.21.05-hotfix4 — Renvoie l'e-mail d'ouverture de l'extranet apprenant
   * depuis la fiche inscription, sans nécessiter manage_options.
   * Accessible aux administrateurs/managers via is_admin_manager().
   */
  public function handle_resend_learner_extranet_email() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      $this->redirect_to_login( 'Accès réservé aux administrateurs.', 'error' );
    }

    $registration_id = isset( $_GET['registration_id'] ) ? absint( wp_unslash( $_GET['registration_id'] ) ) : 0;

    // On utilise wp_verify_nonce directement (pas check_admin_referer) pour éviter
    // l'échec sur vérification du HTTP Referer quand le clic vient d'une page front-end.
    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'acdc_resend_learner_extranet_email_' . $registration_id ) ) {
      wp_die( esc_html__( 'Lien invalide ou expiré. Rechargez la page et réessayez.', 'acdc-formation-saas' ) );
    }

    $return_url = is_admin()
      ? admin_url( 'admin.php?page=acdc-of-register-training&tab=registrations&action=edit&item_id=' . $registration_id )
      : $this->portal_page_url( array( 'tab' => 'registrations', 'action' => 'edit', 'item_id' => $registration_id ) );

    if ( $registration_id <= 0 ) {
      wp_safe_redirect( add_query_arg( $this->acdc_append_notice_args( array(), 'Inscription introuvable.', 'error' ), $return_url ) );
      exit;
    }

    global $wpdb;
    $registration = $wpdb->get_row( $wpdb->prepare(
      "SELECT id, learner_id FROM {$this->training_registration_table} WHERE id = %d LIMIT 1",
      $registration_id
    ) );

    if ( ! $registration || empty( $registration->learner_id ) ) {
      wp_safe_redirect( add_query_arg( $this->acdc_append_notice_args( array(), 'Apprenant non trouvé sur cette inscription.', 'error' ), $return_url ) );
      exit;
    }

    $email = (string) $wpdb->get_var( $wpdb->prepare(
      "SELECT email FROM {$this->learner_table} WHERE id = %d LIMIT 1",
      (int) $registration->learner_id
    ) );

    if ( ! is_email( $email ) ) {
      wp_safe_redirect( add_query_arg( $this->acdc_append_notice_args( array(), "Cet apprenant n'a pas d'adresse e-mail valide.", 'error' ), $return_url ) );
      exit;
    }

    // Forcer une synchronisation pour s'assurer que le compte existe
    if ( method_exists( $this, 'learner_portal_sync_accounts' ) ) {
      $this->learner_portal_sync_accounts( true );
    }

    $account = method_exists( $this, 'learner_portal_get_account_by_email' )
      ? $this->learner_portal_get_account_by_email( $email )
      : null;

    if ( ! $account ) {
      wp_safe_redirect( add_query_arg( $this->acdc_append_notice_args( array(), "Aucun compte extranet trouvé pour {$email}. Vérifiez que l'accès extranet est activé et que le dossier n'est pas en brouillon.", 'error' ), $return_url ) );
      exit;
    }

    $sent = method_exists( $this, 'learner_portal_send_activation_email' )
      ? $this->learner_portal_send_activation_email( $account )
      : false;

    if ( $sent ) {
      $message = "E-mail d'ouverture renvoyé à {$email}.";
      wp_safe_redirect( add_query_arg( $this->acdc_append_notice_args( array(), $message, 'success' ), $return_url ) );
    } else {
      wp_safe_redirect( add_query_arg( $this->acdc_append_notice_args( array(), "L'e-mail n'a pas pu être envoyé. Vérifiez la configuration SMTP.", 'error' ), $return_url ) );
    }
    exit;
  }


  /**
   * ACDC 3.21.08 — Après signature électronique d'une convention :
   * régénère le PDF de la convention en y apposant :
   *   - la signature manuscrite du commanditaire (boîte gauche)
   *   - le cachet ACDC (boîte droite, déjà présent via profile['stamp_url'])
   *
   * Déclenché par do_action('acdc_sig_request_signed', $request_id, $request)
   * depuis class-acdc-sig-public.php::handle_submit().
   */
  public function handle_contract_signed_by_learner( $request_id, $request ) {
    $request_id = absint( $request_id );
    if ( ! $request_id ) {
      return;
    }

    global $wpdb;

    // Trouver la convention liée à cette demande de signature
    $contract = $wpdb->get_row( $wpdb->prepare(
      "SELECT * FROM {$this->registration_contract_table} WHERE signature_request_id = %d LIMIT 1",
      $request_id
    ) );

    if ( ! $contract ) {
      // Pas de convention liée — flux signature indépendant, rien à faire
      return;
    }

    // Trouver le PNG de signature manuscrite dans le dossier de la demande
    $upload_dir    = wp_upload_dir();
    $sig_dir       = trailingslashit( $upload_dir['basedir'] ) . 'acdc-signatures/' . $request_id . '/';
    $sig_files     = glob( $sig_dir . 'signature-*.png' );
    if ( ! empty( $sig_files ) ) {
      // Trier pour prendre la signature la plus récente (ordre descendant)
      rsort( $sig_files );
    }
    $sig_img_path  = ! empty( $sig_files ) ? (string) $sig_files[0] : '';

    if ( '' === $sig_img_path || ! file_exists( $sig_img_path ) ) {
      @error_log( '[ACDC] handle_contract_signed_by_learner — signature image introuvable. sig_dir=' . $sig_dir . ' files=' . json_encode( $sig_files ) );
      return;
    }
    @error_log( '[ACDC] handle_contract_signed_by_learner — signature trouvee : ' . $sig_img_path );

    // Régénérer la convention avec la signature manuscrite apposée
    $result = $this->generate_registration_contract_pdf_file( (int) $contract->id, $sig_img_path );

    if ( empty( $result['url'] ) ) {
      return;
    }

    // Mettre à jour signature_status sur la convention
    $wpdb->update(
      $this->registration_contract_table,
      array(
        'signature_status'       => 'completed',
        'signature_completed_at' => current_time( 'mysql' ),
        'signed_document_url'    => $result['url'],
        'signed_document_path'   => $result['path'],
        'document_url'           => $result['url'],
        'document_path'          => $result['path'],
        'updated_at'             => current_time( 'mysql' ),
      ),
      array( 'id' => (int) $contract->id )
    );

    // M23 — Convention signée = conversion : faire passer le prospect à « Converti ».
    if ( ! empty( $contract->source_prospect_id ) && method_exists( $this, 'maybe_advance_prospect_status' ) ) {
      $this->maybe_advance_prospect_status( (int) $contract->source_prospect_id, 'Converti', true );
    }

    // Envoyer le PDF signé à l'organisme + au signataire
    // Récupérer l'email du signataire depuis la sig_request (requête directe — $this->core n'existe pas dans ce trait)
    $sig_table       = $wpdb->prefix . 'acdc_sig_requests';
    $sig_request_row = $wpdb->get_row( $wpdb->prepare(
      "SELECT signer_email, signer_name FROM {$sig_table} WHERE id = %d",
      $request_id
    ) );
    $this->send_signed_contract_to_organisme( $contract, $result, $sig_request_row );
  }

  /**
   * Envoie la convention / contrat signé(e) à l'organisme par e-mail avec le PDF en pièce jointe.
   * Appelé uniquement depuis handle_contract_signed_by_learner(), après régénération du PDF.
   */
  private function send_signed_contract_to_organisme( $contract, $pdf_result, $sig_request_row = null ) {
    if ( empty( $pdf_result['path'] ) || ! file_exists( (string) $pdf_result['path'] ) ) {
      @error_log( '[ACDC] send_signed_contract_to_organisme — PDF introuvable : ' . ( isset( $pdf_result['path'] ) ? $pdf_result['path'] : 'vide' ) );
      return;
    }

    $branding          = get_option( 'acdc_of_branding', array() );
    if ( ! is_array( $branding ) ) { $branding = array(); }
    $marketing         = get_option( 'acdc_of_marketing_settings', array() );
    if ( ! is_array( $marketing ) ) { $marketing = array(); }

    // Destinataire = email interne des notifications (réglages marketing) ou email organisme
    $to = '';
    if ( ! empty( $marketing['internal_notification_email'] ) ) {
      $to = sanitize_email( (string) $marketing['internal_notification_email'] );
    }
    if ( '' === $to && ! empty( $marketing['sender_email'] ) ) {
      $to = sanitize_email( (string) $marketing['sender_email'] );
    }
    if ( '' === $to && ! empty( $branding['email'] ) ) {
      $to = sanitize_email( (string) $branding['email'] );
    }
    if ( '' === $to ) {
      $to = sanitize_email( (string) get_option( 'admin_email' ) );
    }
    if ( '' === $to || ! is_email( $to ) ) {
      return;
    }

    $company_name  = ! empty( $branding['company_name'] ) ? sanitize_text_field( (string) $branding['company_name'] ) : get_bloginfo( 'name' );
    $sender_name   = ! empty( $marketing['sender_name'] ) ? sanitize_text_field( (string) $marketing['sender_name'] ) : $company_name;
    $from_email    = ! empty( $marketing['sender_email'] ) ? sanitize_email( (string) $marketing['sender_email'] ) : sanitize_email( (string) get_option( 'admin_email' ) );

    $kind          = isset( $contract->commanditaire_type ) && 'Particulier' === (string) $contract->commanditaire_type ? 'Contrat' : 'Convention';
    /* Accord grammatical : « Convention signée » (fém.) vs « Contrat signé » (masc.). */
    $kind_signed   = 'Convention' === $kind ? 'signée' : 'signé';
    $signer_name   = isset( $contract->commanditaire_signer_last_name ) ? trim( (string) $contract->commanditaire_signer_first_name . ' ' . (string) $contract->commanditaire_signer_last_name ) : 'le signataire';
    $formation     = isset( $contract->formation_title ) ? (string) $contract->formation_title : '';
    $signed_at     = wp_date( 'd/m/Y à H:i' );

    $subject = '✅ ' . $kind . ' ' . $kind_signed . ' — ' . ( $signer_name ? $signer_name : '' ) . ( $formation ? ' — ' . $formation : '' );
    $body    = '<div style="font-family:Arial,sans-serif;color:#24324a;max-width:600px;margin:0 auto;">'
             . '<h2 style="color:#1f335d;">✅ ' . esc_html( $kind ) . ' ' . esc_html( $kind_signed ) . '</h2>'
             . '<p>' . esc_html( $kind ) . ' ' . esc_html( $kind_signed ) . ' le <strong>' . esc_html( $signed_at ) . '</strong> par <strong>' . esc_html( $signer_name ? $signer_name : 'le signataire' ) . '</strong>.</p>'
             . ( $formation ? '<p>Formation : <strong>' . esc_html( $formation ) . '</strong></p>' : '' )
             . '<p>Le document signé est joint à cet e-mail.</p>'
             . '</div>';

    $headers = array(
      'Content-Type: text/html; charset=UTF-8',
      'From: ' . sanitize_text_field( $sender_name ) . ' <' . sanitize_email( $from_email ) . '>',
    );

    // Envoi à l'organisme
    $sent = wp_mail( $to, $subject, $body, $headers, array( (string) $pdf_result['path'] ) );

    // Log debug dans error_log pour diagnostic
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
      error_log( '[ACDC] send_signed_contract_to_organisme — to=' . $to . ' sent=' . ( $sent ? '1' : '0' ) . ' path=' . $pdf_result['path'] );
    }

    // Envoi au signataire — email lu depuis la sig_request (source de vérité)
    $signer_email_addr = '';
    if ( $sig_request_row && ! empty( $sig_request_row->signer_email ) && is_email( (string) $sig_request_row->signer_email ) ) {
      $signer_email_addr = sanitize_email( (string) $sig_request_row->signer_email );
    } elseif ( isset( $contract->commanditaire_email ) && is_email( (string) $contract->commanditaire_email ) ) {
      $signer_email_addr = sanitize_email( (string) $contract->commanditaire_email );
    }
    if ( '' !== $signer_email_addr ) {
      /* Accord grammatical : « de la convention signée » (fém.) vs « du contrat signé » (masc.). */
      $kind_lc = strtolower( (string) $kind );
      if ( 'convention' === $kind_lc ) {
        $doc_phrase = 'de la convention signée';
      } elseif ( 'contrat' === $kind_lc ) {
        $doc_phrase = 'du contrat signé';
      } else {
        $doc_phrase = 'du ' . $kind_lc . ' signé';
      }
      $signer_subject = '📄 Votre exemplaire — ' . esc_html( $kind ) . ' ' . esc_html( $kind_signed );
      $signer_body    = '<div style="font-family:Arial,sans-serif;color:#24324a;max-width:600px;margin:0 auto;">'
                      . '<h2 style="color:#1f335d;">Votre exemplaire signé</h2>'
                      . '<p>Bonjour,</p>'
                      . '<p>Veuillez trouver en pièce jointe votre exemplaire ' . esc_html( $doc_phrase ) . ' le <strong>' . esc_html( $signed_at ) . '</strong>.</p>'
                      . '<p>Conservez ce document pour vos archives.</p>'
                      . '</div>';
      wp_mail( $signer_email_addr, $signer_subject, $signer_body, $headers, array( (string) $pdf_result['path'] ) );
    }
  }


  /**
   * Handler : inscription groupée depuis convention.
   * Crée une inscription par apprenant (dédoublonnage) + une séance par date (09h–17h).
   * Toutes les données sont propagées depuis la convention source.
   *
   * @since 3.21.34
   */
  public function handle_bulk_enroll_from_contract() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      $this->redirect_to_login( 'Accès réservé aux administrateurs.', 'error' );
    }

    check_admin_referer( 'acdc_bulk_enroll_from_contract' );

    global $wpdb;

    $contract_id = isset( $_POST['contract_id'] ) ? absint( wp_unslash( $_POST['contract_id'] ) ) : 0;

    if ( ! $contract_id ) {
      $this->redirect_to_portal( 'registration_contract', 'Convention introuvable.', 'error' );
      exit;
    }

    $contract = $this->get_registration_contract( $contract_id );
    if ( ! $contract ) {
      $this->redirect_to_portal( 'registration_contract', 'Convention introuvable.', 'error' );
      exit;
    }

    /* ── Données de la convention ─────────────────────────────── */
    $formation_id    = (int) $contract->formation_id;
    $formation_title = ! empty( $contract->formation_title ) ? (string) $contract->formation_title : '';
    $company_id      = ! empty( $contract->company_id )      ? (int) $contract->company_id      : null;
    $price_ht        = ! empty( $contract->price_ht )        ? (string) $contract->price_ht     : '';
    $transport       = ! empty( $contract->transport_fees_enabled ) ? 1 : 0;
    $meal            = ! empty( $contract->meal_fees_enabled )      ? 1 : 0;
    $contract_title  = ! empty( $contract->title )           ? (string) $contract->title        : '';
    $now             = $this->now_mysql();

    /* Label entreprise */
    $company_label = '';
    if ( $company_id ) {
      $company_obj   = $this->get_company( $company_id );
      $company_label = $company_obj && ! empty( $company_obj->name ) ? (string) $company_obj->name : '';
    }

    /* ── Inscriptions — une par apprenant ─────────────────────── */
    $raw_learner_ids = array_values( array_filter( array_map( 'absint', explode( ',', (string) $contract->learner_ids ) ) ) );
    $enrolled = 0;
    $skipped  = 0;

    foreach ( $raw_learner_ids as $learner_id ) {
      /* Dédoublonnage strict : même apprenant + même formation + même convention */
      $exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$this->training_registration_table}
         WHERE learner_id = %d AND formation_id = %d AND autofill_contract_id = %d LIMIT 1",
        $learner_id, $formation_id, $contract_id
      ) );
      if ( $exists ) {
        $skipped++;
        continue;
      }

      $learner       = $this->get_learner( $learner_id );
      $learner_label = $learner ? trim( (string) $learner->first_name . ' ' . (string) $learner->usage_last_name ) : '';
      $reg_title     = $this->get_training_registration_title( 'Non', '', $learner_label, $learner_label, $formation_title );

      $reg_data = array(
        'title'                   => $reg_title,
        'belongs_to_group'        => 'Non',
        'autofill_contract_id'    => $contract_id,
        'autofill_contract_label' => $contract_title,
        'group_id'                => null,
        'group_label'             => '',
        'learner_id'              => $learner_id,
        'learner_label'           => $learner_label,
        'learner_ids'             => (string) $learner_id,
        'learners_label'          => $learner_label,
        'company_id'              => $company_id,
        'company_label'           => $company_label,
        'formation_id'            => $formation_id ?: null,
        'formation_title'         => $formation_title,
        'extranet_access'         => 1,
        'price_ht'                => $price_ht,
        'transport_fees_enabled'  => $transport,
        'meal_fees_enabled'       => $meal,
        'is_draft'                => 0,
        'created_at'              => $now,
        'updated_at'              => $now,
      );

      $result = $wpdb->insert( $this->training_registration_table, $reg_data );
      if ( false !== $result ) {
        $enrolled++;
      }
    }

    /* ── Séances — une par date ────────────────────────────────── */
    $seances_arr = array();
    if ( ! empty( $contract->seances_dates ) ) {
      foreach ( array_filter( array_map( 'trim', explode( ',', (string) $contract->seances_dates ) ) ) as $d ) {
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
          $seances_arr[] = $d;
        }
      }
      sort( $seances_arr );
    }

    $sessions_created = 0;
    foreach ( $seances_arr as $date ) {
      $date_fr = mysql2date( 'd/m/Y', $date );

      /* Demi-journées Qualiopi */
      $half_days = array(
        array(
          'label'  => 'Matin',
          'start'  => $date . ' 09:00:00',
          'end'    => $date . ' 12:30:00',
        ),
        array(
          'label'  => 'Après-midi',
          'start'  => $date . ' 13:30:00',
          'end'    => $date . ' 17:00:00',
        ),
      );

      foreach ( $half_days as $half ) {
        $start_at = $half['start'];
        $end_at   = $half['end'];

        $session_title = $formation_title
          ? sprintf( 'Séance — %s — %s — %s', $formation_title, $date_fr, $half['label'] )
          : sprintf( 'Séance — %s — %s', $date_fr, $half['label'] );

        $slot = array(
          'start_at'   => $start_at,
          'end_at'     => $end_at,
          'start_date' => $date,
          'end_date'   => $date,
        );

        $session_data = array(
          'formation_id'      => $formation_id ?: null,
          'company_id'        => $company_id,
          'trainer_id'        => null,
          'title'             => $session_title,
          'session_type'      => '',
          'attendance_method' => '',
          'session_format'    => '',
          'start_at'          => $start_at,
          'end_at'            => $end_at,
          'start_date'        => $date,
          'end_date'          => $date,
          'location'          => '',
          'remote_link'       => '',
          'status'            => 'Planifiée',
          'max_learners'      => 0,
          'is_draft'          => 0,
          'schedule_json'     => wp_json_encode( array( $slot ) ),
          'notes'             => '',
          'created_at'        => $now,
          'updated_at'        => $now,
        );

        $result = $wpdb->insert( $this->session_table, $session_data );
        if ( false !== $result ) {
          $sessions_created++;
        }
      }
    }

    /* ── Message de succès ─────────────────────────────────────── */
    $parts = array();
    if ( $enrolled > 0 ) {
      $parts[] = 1 === $enrolled ? '1 inscription créée' : $enrolled . ' inscriptions créées';
    }
    if ( $skipped > 0 ) {
      $parts[] = 1 === $skipped ? '1 inscription ignorée (doublon)' : $skipped . ' inscriptions ignorées (doublons)';
    }
    if ( $sessions_created > 0 ) {
      $parts[] = 1 === $sessions_created ? '1 séance créée' : $sessions_created . ' séances créées';
    }
    if ( empty( $parts ) ) {
      $parts[] = 'Aucun enregistrement créé (apprenants déjà inscrits, aucune date de séance).';
    }
    $message = implode( ' — ', $parts );

    /* Toujours rediriger vers le portail (portal_page_url).
       Le handler est déclenché depuis admin-post.php où is_admin()=true
       quelle que soit la page d'origine — redirect_to_admin_page serait
       inaccessible aux utilisateurs sans manage_options natif. */
    $this->redirect_to_portal( 'registrations', $message, 'success' );
    exit;
  }

}