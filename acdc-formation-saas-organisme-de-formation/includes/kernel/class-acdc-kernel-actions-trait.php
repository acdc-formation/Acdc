<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Kernel_Actions_Trait {

  public function handle_save_training_file_profile() {
    $this->require_manage_options();
    $registration_id = isset( $_POST['registration_id'] ) ? absint( wp_unslash( $_POST['registration_id'] ) ) : 0;
    if ( ! $registration_id ) {
      wp_die( esc_html( 'Dossier introuvable.' ) );
    }
    $this->verify_nonce_or_die( 'acdc_save_training_file_profile_' . $registration_id );

    $section = isset( $_POST['profile_section'] ) ? sanitize_key( wp_unslash( $_POST['profile_section'] ) ) : 'need';
    if ( ! in_array( $section, array( 'need', 'objectives', 'positioning', 'contract', 'welcome', 'sessions', 'support', 'evaluations', 'documents', 'complaints', 'compliance', 'quality', 'public_info', 'accounting' ), true ) ) {
      $section = 'need';
    }

    $profile = $this->get_training_file_profile( $registration_id );
    $submitted = isset( $_POST[ $section ] ) ? wp_unslash( $_POST[ $section ] ) : array();
    $submitted = is_array( $submitted ) ? $submitted : array();

    $fields_by_section = array(
      'need' => array( 'beneficiary_need', 'company_need', 'funder_need', 'context', 'constraints', 'feasibility', 'handicap_adaptation', 'internal_summary' ),
      'objectives' => array( 'operational_objectives', 'pedagogical_objectives', 'targeted_skills', 'program_content', 'modalities', 'evaluation_methods', 'certification_reference' ),
      'positioning' => array( 'prerequisites_checked', 'entry_test', 'interview_notes', 'self_positioning', 'initial_level', 'identified_gaps', 'decided_adaptation' ),
      'contract' => array( 'contract_type', 'funder', 'contract_dates', 'signature_status', 'sent_at', 'received_at', 'follow_up', 'attachments_summary' ),
      'welcome' => array( 'invitation_sent', 'welcome_pack_sent', 'internal_rules_shared', 'pedagogical_contact', 'administrative_contact', 'lms_access', 'technical_support', 'pedagogical_support', 'safety_information', 'psh_information', 'delivery_proof' ),
      'sessions' => array( 'session_summary', 'attendance_status', 'attendance_method', 'incidents', 'operational_notes' ),
      'support' => array( 'pedagogical_referent', 'support_date', 'support_type', 'encounters_summary', 'identified_difficulties', 'hazards', 'dropout_risk', 'corrective_actions', 'specific_support', 'company_exchanges', 'follow_up_status' ),
      'evaluations' => array( 'positioning_status', 'mid_survey_status', 'hot_survey_status', 'final_evaluation_status', 'cold_survey_status', 'trainer_survey_status', 'company_survey_status', 'funder_survey_status', 'evaluation_summary', 'related_documents_summary' ),
      'documents' => array( 'proof_status', 'pedagogical_documents', 'administrative_documents', 'end_of_training_documents', 'document_delivery_proof', 'document_summary' ),
      'complaints' => array( 'complaint_status', 'complaint_source', 'complaint_summary', 'incident_summary', 'severity_level', 'assigned_manager', 'corrective_measure', 'improvement_action', 'improvement_deadline', 'improvement_effectiveness' ),
      'compliance' => array( 'handicap_status', 'handicap_referent', 'compensation_need', 'adaptation_decision', 'mobilized_resources', 'subcontractor_name', 'subcontracting_scope', 'subcontracting_contract', 'subcontracting_quality_control', 'internal_trainer_name', 'internal_trainer_skills', 'internal_training_actions', 'compliance_summary' ),
      'quality' => array( 'regulatory_watch', 'business_watch', 'pedagogical_watch', 'quality_indicators', 'internal_audit_summary', 'internal_audit_actions', 'quality_summary' ),
      'public_info' => array( 'public_title', 'public_summary', 'public_prerequisites', 'public_modalities', 'public_duration', 'public_access_delay', 'public_tariffs', 'public_contacts', 'public_certification', 'public_results', 'publication_status', 'published_at', 'publication_notes' ),
      'accounting' => array( 'funder_link_id', 'funder_type_label', 'funder_dossier_number', 'funder_pec_status', 'funder_amount', 'funder_sent_date', 'funder_expected_reply_date', 'funder_pec_doc_url', 'funder_other_doc_url', 'quote_reference', 'invoice_reference', 'credit_note_reference', 'activity_report_reference', 'billing_status', 'billing_amount', 'payment_status', 'payment_due_date', 'funder_billing_notes', 'reporting_summary', 'accounting_notes' ),
    );

    foreach ( $fields_by_section[ $section ] as $field ) {
      $profile[ $section ][ $field ] = isset( $submitted[ $field ] ) ? sanitize_textarea_field( $submitted[ $field ] ) : '';
    }
    $profile['updated_at'] = $this->now_mysql();
    $profile['updated_by'] = get_current_user_id();
    $this->save_training_file_profile( $registration_id, $profile );
    $this->log_action_event( 'save_training_file_profile', 'training_registration', $registration_id, 'success', array( 'section' => $section ) );

    $redirect_url = isset( $_POST['redirect_url'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_url'] ) ) : admin_url( 'admin.php?page=acdc-of-training-files' );
    $redirect_url = add_query_arg(
      array(
        'action' => 'view',
        'item_id' => $registration_id,
        'subtab' => $section,
        'message' => rawurlencode( 'Données enregistrées.' ),
      ),
      $redirect_url
    );
    wp_safe_redirect( $redirect_url );
    exit;
  }

  /* -----------------------------------------------------------------------
   * ACDC 3.24.16 — R-15 : Sous-traitants par dossier (indicateur 15 Qualiopi).
   * Stockage : acdc_of_dossier_subcontractors_{registration_id} en wp_options.
   * Pattern identique training_sites / acdc_of_subcontractors.
   * Routing WAF-safe via dsc_post_action / dsc_pid_action dans l'URL.
   * ----------------------------------------------------------------------- */

  private function get_dossier_subcontractors( $registration_id ) {
    $raw = get_option( 'acdc_of_dossier_subcontractors_' . (int) $registration_id, array() );
    return is_array( $raw ) ? $raw : array();
  }

  public function handle_save_dossier_subcontractor() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_save_dossier_subcontractor' );
    $registration_id = isset( $_POST['registration_id'] ) ? absint( wp_unslash( $_POST['registration_id'] ) ) : 0;
    if ( ! $registration_id ) { wp_die( esc_html( 'Dossier introuvable.' ) ); }
    $input = isset( $_POST['dsc'] ) && is_array( $_POST['dsc'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['dsc'] ) ) : array();
    if ( empty( $input['nom'] ) ) {
      $base = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'training_files' ) );
      wp_safe_redirect( add_query_arg( array( 'action' => 'view', 'item_id' => $registration_id, 'subtab' => 'compliance', 'notice' => rawurlencode( 'Le nom du sous-traitant est requis.' ), 'notice_type' => 'error' ), $base ) );
      exit;
    }
    $items       = $this->get_dossier_subcontractors( $registration_id );
    $existing_id = ! empty( $input['id'] ) ? (string) $input['id'] : '';
    $item = array(
      'id'         => $existing_id ?: 'dsc_' . wp_generate_uuid4(),
      'nom'        => sanitize_text_field( $input['nom'] ?? '' ),
      'role'       => sanitize_text_field( $input['role'] ?? '' ),
      'date_debut' => sanitize_text_field( $input['date_debut'] ?? '' ),
      'date_fin'   => sanitize_text_field( $input['date_fin'] ?? '' ),
      'montant_ht' => preg_replace( '/[^0-9.,]/', '', $input['montant_ht'] ?? '' ),
      'notes'      => sanitize_textarea_field( $input['notes'] ?? '' ),
      'updated_at' => current_time( 'mysql' ),
    );
    $found = false;
    foreach ( $items as &$it ) {
      if ( ( $it['id'] ?? '' ) === $existing_id && $existing_id ) {
        $it = $item; $found = true; break;
      }
    }
    unset( $it );
    if ( ! $found ) { $items[] = $item; }
    update_option( 'acdc_of_dossier_subcontractors_' . $registration_id, array_values( $items ), false );
    $base = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'training_files' ) );
    wp_safe_redirect( add_query_arg( array( 'action' => 'view', 'item_id' => $registration_id, 'subtab' => 'compliance', 'notice' => rawurlencode( 'Sous-traitant enregistré.' ), 'notice_type' => 'success' ), $base ) );
    exit;
  }

  public function handle_delete_dossier_subcontractor() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    $registration_id = isset( $_GET['dsc_reg'] ) ? absint( wp_unslash( $_GET['dsc_reg'] ) ) : 0;
    $iid = isset( $_GET['dsc_pid'] ) ? sanitize_key( rawurldecode( wp_unslash( $_GET['dsc_pid'] ) ) ) : '';
    if ( ! $registration_id || ! $iid ) {
      $base = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'training_files' ) );
      wp_safe_redirect( add_query_arg( array( 'action' => 'view', 'item_id' => $registration_id, 'subtab' => 'compliance' ), $base ) );
      exit;
    }
    check_admin_referer( 'acdc_delete_dossier_subcontractor_' . $iid );
    $items = $this->get_dossier_subcontractors( $registration_id );
    $items = array_values( array_filter( $items, function( $it ) use ( $iid ) { return ( $it['id'] ?? '' ) !== $iid; } ) );
    update_option( 'acdc_of_dossier_subcontractors_' . $registration_id, $items, false );
    $base = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'training_files' ) );
    wp_safe_redirect( add_query_arg( array( 'action' => 'view', 'item_id' => $registration_id, 'subtab' => 'compliance', 'notice' => rawurlencode( 'Sous-traitant supprimé.' ), 'notice_type' => 'success' ), $base ) );
    exit;
  }

  /* ACDC 3.25.278 — Enregistrement et suppression des « tests de positionnement »
     retirés avec leur module : il faisait double emploi avec le quiz, et son
     envoi automatique appelait depuis toujours une fonction inexistante. */

public function handle_save_quiz() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    check_admin_referer( 'acdc_save_quiz' );
    global $wpdb;
    $quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
    $is_admin_page = is_admin() && isset( $_POST['page'] ) && 'acdc-of-quiz' === $_POST['page'];
    $input = isset( $_POST['quiz'] ) && is_array( $_POST['quiz'] ) ? wp_unslash( $_POST['quiz'] ) : array();
    $title = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
    $duration = isset( $input['duration_minutes'] ) ? absint( $input['duration_minutes'] ) : 0;
    if ( '' === $title || $duration < 1 ) {
      $msg = 'Intitulé et durée du questionnaire sont obligatoires.';
      if ( $is_admin_page ) { wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-quiz&action=' . ( $quiz_id ? 'edit&item_id=' . $quiz_id : 'new' ) . '&notice=' . rawurlencode( $msg ) . '&notice_type=error' ) ); exit; }
      $this->redirect_to_portal( 'quiz', $msg, 'error', array( 'action' => $quiz_id ? 'edit' : 'new', 'item_id' => $quiz_id ) );
    }
    $questions_in = isset( $_POST['questions'] ) && is_array( $_POST['questions'] ) ? wp_unslash( $_POST['questions'] ) : array();
    $questions = array();
    foreach ( $questions_in as $q ) {
      $label = sanitize_text_field( $q['label'] ?? '' );
      $type = sanitize_text_field( $q['type'] ?? '' );
      $options = sanitize_textarea_field( $q['options'] ?? '' );
      $answers = array();
      if ( isset( $q['answers'] ) && is_array( $q['answers'] ) ) {
        foreach ( $q['answers'] as $answer ) {
          $answer = sanitize_text_field( $answer );
          if ( '' !== $answer ) {
            $answers[] = $answer;
          }
        }
      }
      $correct_answers = array();
      if ( isset( $q['correct_answers'] ) ) {
        if ( is_array( $q['correct_answers'] ) ) {
          foreach ( $q['correct_answers'] as $correct_answer ) {
            $correct_answers[] = (string) absint( $correct_answer );
          }
        } elseif ( '' !== (string) $q['correct_answers'] ) {
          $correct_answers[] = (string) absint( $q['correct_answers'] );
        }
      }
      $correct_answers = array_values( array_unique( $correct_answers ) );
      if ( empty( $label ) && empty( $type ) && empty( $options ) && empty( $answers ) ) { continue; }
      if ( in_array( $type, array( 'Choix unique', 'Choix multiples', 'Cases à cocher' ), true ) && ! empty( $answers ) ) {
        $option_lines = array();
        foreach ( $answers as $answer_index => $answer_label ) {
          $prefix = in_array( (string) $answer_index, $correct_answers, true ) ? '* ' : '';
          $option_lines[] = $prefix . $answer_label;
        }
        $options = implode( "
", $option_lines );
      }
      $question = array(
        'label' => $label,
        'type' => $type,
        'options' => $options,
      );
      if ( ! empty( $answers ) ) {
        $question['answers'] = $answers;
        $question['correct_answers'] = $correct_answers;
      }
      $questions[] = $question;
    }
    $scores_in = isset( $_POST['scoring'] ) && is_array( $_POST['scoring'] ) ? wp_unslash( $_POST['scoring'] ) : array();
    $scores = array();
    foreach ( $scores_in as $row ) {
      if ( empty( $row['type'] ) && empty( $row['threshold'] ) && empty( $row['label'] ) ) { continue; }
      $scores[] = array(
        'type' => sanitize_text_field( $row['type'] ?? '' ),
        'threshold' => sanitize_text_field( $row['threshold'] ?? '' ),
        'label' => sanitize_text_field( $row['label'] ?? '' ),
      );
    }
    $data = array(
      'title' => $title,
      'description_text' => isset( $input['description_text'] ) ? sanitize_textarea_field( $input['description_text'] ) : '',
      'correction_type' => isset( $input['correction_type'] ) ? sanitize_text_field( $input['correction_type'] ) : 'Correction automatique',
      'duration_minutes' => $duration,
      'question_blocks' => wp_json_encode( $questions ),
      'scoring_blocks' => wp_json_encode( $scores ),
      'alert_notation' => isset( $input['alert_notation'] ) ? sanitize_text_field( $input['alert_notation'] ) : '',
      'updated_at' => $this->now_mysql(),
    );
    if ( $quiz_id ) {
      $result = $wpdb->update( $this->quiz_table, $data, array( 'id' => $quiz_id ) );
      $message = 'Quiz mis à jour.';
    } else {
      $data['created_at'] = $this->now_mysql();
      $result = $wpdb->insert( $this->quiz_table, $data );
      $quiz_id = (int) $wpdb->insert_id;
      $message = 'Quiz créé.';
    }
    if ( false === $result ) {
      $msg = $this->get_safe_db_error_message( 'Une erreur technique est survenue.' );
      if ( $is_admin_page ) { wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-quiz&action=' . ( $quiz_id ? 'edit&item_id=' . $quiz_id : 'new' ) . '&notice=' . rawurlencode( $msg ) . '&notice_type=error' ) ); exit; }
      $this->redirect_to_portal( 'quiz', $msg, 'error', array( 'action' => $quiz_id ? 'edit' : 'new', 'item_id' => $quiz_id ) );
    }
    $target = $is_admin_page ? admin_url( 'admin.php?page=acdc-of-quiz&action=edit&item_id=' . $quiz_id . '&notice=' . rawurlencode( $message ) . '&notice_type=success' ) : $this->portal_page_url( array( 'tab' => 'quiz', 'action' => 'edit', 'item_id' => $quiz_id, 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ) );
    if ( ! empty( $_POST['save_and_add'] ) ) {
      $target = $is_admin_page ? admin_url( 'admin.php?page=acdc-of-quiz&action=new&notice=' . rawurlencode( $message ) . '&notice_type=success' ) : $this->portal_page_url( array( 'tab' => 'quiz', 'action' => 'new', 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ) );
    } elseif ( ! empty( $_POST['save_and_prepare_session'] ) ) {
      $target = $this->get_questionnaire_new_session_url( 'quiz', $quiz_id );
    }
    wp_safe_redirect( $target ); exit;
  }  public function handle_delete_quiz() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    $quiz_id = isset( $_GET['quiz_id'] ) ? absint( wp_unslash( $_GET['quiz_id'] ) ) : 0;
    if ( ! $quiz_id ) { $this->redirect_to_portal( 'quiz', 'Quiz introuvable.', 'error' ); }
    check_admin_referer( 'acdc_delete_quiz_' . $quiz_id );
    global $wpdb;
    $wpdb->delete( $this->quiz_table, array( 'id' => $quiz_id ) );
    if ( is_admin() && isset( $_GET['page'] ) && 'acdc-of-quiz' === $_GET['page'] ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-quiz&notice=' . rawurlencode( 'Quiz supprimé.' ) . '&notice_type=success' ) ); exit;
    }
    $this->redirect_to_portal( 'quiz', 'Quiz supprimé.', 'success' );
  }  public function handle_send_quiz() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_send_quiz' );
    $quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
    $directory_type = isset( $_POST['directory_type'] ) ? sanitize_text_field( wp_unslash( $_POST['directory_type'] ) ) : '';
    $send_type = isset( $_POST['send_type'] ) ? sanitize_text_field( wp_unslash( $_POST['send_type'] ) ) : '';
    $message = ( $quiz_id && $directory_type && $send_type ) ? 'Paramètres d’envoi du quiz enregistrés.' : 'Merci de compléter tous les champs de l’envoi de quiz.';
    $type = ( $quiz_id && $directory_type && $send_type ) ? 'success' : 'error';
    if ( is_admin() && isset( $_POST['page'] ) && 'acdc-of-quiz' === $_POST['page'] ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-quiz&notice=' . rawurlencode( $message ) . '&notice_type=' . $type ) ); exit;
    }
    $this->redirect_to_portal( 'quiz', $message, $type );
  }  public function handle_download_positioning_result_document() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $registration_id = isset( $_GET['registration_id'] ) ? absint( wp_unslash( $_GET['registration_id'] ) ) : 0;
    if ( ! $registration_id ) {
      wp_die( esc_html( 'Résultat introuvable.' ) );
    }
    check_admin_referer( 'acdc_download_positioning_result_document_' . $registration_id );
    $registration = $this->get_training_registration( $registration_id );
    if ( ! $registration ) {
      wp_die( esc_html( 'Résultat introuvable.' ) );
    }
    $context = $this->get_positioning_result_context( $registration );
    $document = $context['document'];
    $mode = isset( $_GET['mode'] ) && 'inline' === sanitize_key( wp_unslash( $_GET['mode'] ) ) ? 'inline' : 'attachment';
    $filename = $this->get_positioning_result_display_file_name( $registration, $context, $document );
    $path = ! empty( $document['path'] ) ? (string) $document['path'] : '';
    if ( '' !== $path && file_exists( $path ) ) {
      while ( ob_get_level() ) { ob_end_clean(); }
      nocache_headers();
      header( 'Content-Type: application/pdf' );
      header( 'Content-Length: ' . filesize( $path ) );
      header( 'Content-Disposition: ' . $mode . '; filename="' . sanitize_file_name( $filename ) . '"' );
      readfile( $path );
      exit;
    }
    $pages = $this->build_positioning_result_pdf_pages( $registration, $context );
    $this->render_simple_pdf( $pages, $filename, $mode );
  }  public function handle_update_positioning_result_document() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $registration_id = isset( $_POST['registration_id'] ) ? absint( wp_unslash( $_POST['registration_id'] ) ) : 0;
    if ( ! $registration_id ) {
      wp_die( esc_html( 'Résultat introuvable.' ) );
    }
    check_admin_referer( 'acdc_update_positioning_result_document_' . $registration_id );
    if ( empty( $_FILES['positioning_result_document_file']['name'] ) ) {
      wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'positioning_results', 'notice' => rawurlencode( 'Aucun fichier reçu.' ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
      exit;
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $upload = wp_handle_upload( $_FILES['positioning_result_document_file'], array( 'test_form' => false, 'mimes' => array( 'pdf' => 'application/pdf' ) ) );
    if ( ! empty( $upload['error'] ) ) {
      wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'positioning_results', 'notice' => rawurlencode( 'Téléversement impossible : ' . $upload['error'] ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
      exit;
    }
    global $wpdb;
    $result = $wpdb->update(
      $this->training_registration_table,
      array(
        'positioning_result_document_url' => isset( $upload['url'] ) ? esc_url_raw( $upload['url'] ) : '',
        'positioning_result_document_path' => isset( $upload['file'] ) ? sanitize_text_field( $upload['file'] ) : '',
        'updated_at' => $this->now_mysql(),
      ),
      array( 'id' => $registration_id )
    );
    if ( false === $result ) {
      wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'positioning_results', 'notice' => rawurlencode( 'Erreur lors de la mise à jour.' ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
      exit;
    }
    wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'positioning_results', 'notice' => rawurlencode( 'Résultat test de positionnement mis à jour.' ), 'notice_type' => 'success' ), admin_url( 'admin.php' ) ) );
    exit;
  }  public function handle_export_positioning_qcm_details() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $registration_id = isset( $_POST['registration_id'] ) ? absint( wp_unslash( $_POST['registration_id'] ) ) : 0;
    if ( ! $registration_id ) {
      wp_die( esc_html( 'Résultat introuvable.' ) );
    }
    check_admin_referer( 'acdc_export_positioning_qcm_details_' . $registration_id );
    $registration = $this->get_training_registration( $registration_id );
    if ( ! $registration ) {
      wp_die( esc_html( 'Résultat introuvable.' ) );
    }
    $context = $this->get_positioning_result_context( $registration );
    $filename = isset( $_POST['export_filename'] ) ? sanitize_file_name( wp_unslash( $_POST['export_filename'] ) ) : 'details-qcm';
    if ( '' === $filename ) { $filename = 'details-qcm'; }
    $type = isset( $_POST['export_type'] ) ? sanitize_key( wp_unslash( $_POST['export_type'] ) ) : 'excel';
    $rows = array();
    $rows[] = array( 'Ordre', 'Question', 'Type', 'Options / consignes', 'Apprenant', 'Formation', 'Résultat' );
    foreach ( (array) $context['questions_details'] as $question ) {
      $rows[] = array(
        (string) $question['number'],
        (string) $question['label'],
        (string) $question['type'],
        (string) $question['options'],
        (string) $context['learner_name'],
        (string) $context['formation_title'],
        (string) $context['result_label'],
      );
    }
    while ( ob_get_level() ) { ob_end_clean(); }
    nocache_headers();
    if ( 'csv' === $type ) {
      header( 'Content-Type: text/csv; charset=utf-8' );
      header( 'Content-Disposition: attachment; filename="' . $filename . '.csv"' );
      $output = fopen( 'php://output', 'w' );
      foreach ( $rows as $row ) {
        fputcsv( $output, $row, ';' );
      }
      fclose( $output );
      exit;
    }
    header( 'Content-Type: application/vnd.ms-excel; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '.xls"' );
    echo "<html><head><meta charset='utf-8'></head><body><table border='1'>";
    foreach ( $rows as $index => $row ) {
      echo '<tr>';
      foreach ( $row as $cell ) {
        $tag = 0 === $index ? 'th' : 'td';
        echo '<' . $tag . '>' . esc_html( (string) $cell ) . '</' . $tag . '>';
      }
      echo '</tr>';
    }
    echo "</table></body></html>";
    exit;
  }

  /**
   * ACDC 3.25.229 — ENVOI MANUEL DE LA CONVOCATION.
   *
   * La convocation ne partait que par le moteur, à J-7 de la formation. Un
   * dossier créé après cette échéance n'en recevait donc jamais aucune, et
   * aucun écran ne permettait de rattraper : ni l'onglet Convocations, ni la
   * carte de séance. Le testeur l'a formulé sans détour — « un dossier créé
   * après J-7 n'en recevra jamais et aucun écran ne permet de rattraper le
   * coup ». Une convocation est le document sur lequel une personne se fonde
   * pour se déplacer : ne pas pouvoir l'envoyer est une impasse.
   *
   * Cet envoi reprend le MÊME document et le même contenu que l'envoi
   * automatique : il n'y a qu'une convocation, quel que soit le chemin qui la
   * déclenche. La seule différence est qu'un humain a cliqué, et le journal le
   * dit.
   */
  public function handle_send_training_convocation() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }

    $registration_id = isset( $_POST['registration_id'] ) ? absint( wp_unslash( $_POST['registration_id'] ) ) : 0;
    if ( ! $registration_id ) {
      wp_die( esc_html( 'Dossier introuvable.' ) );
    }
    check_admin_referer( 'acdc_send_training_convocation_' . $registration_id );

    $registration = $this->get_training_registration( $registration_id );
    if ( ! $registration ) {
      $this->redirect_to_portal( 'training_convocations', 'Dossier introuvable : aucune convocation envoyée.', 'error' );
    }

    $context = $this->get_training_convocation_context( $registration );

    /* Règle métier : le commanditaire n'a pas lieu d'être convoqué. Ce sont
       uniquement les apprenants. */
    if ( ! empty( $context['is_commanditaire'] ) ) {
      $this->redirect_to_portal( 'training_convocations', 'Le commanditaire n’est pas convoqué : seuls les apprenants le sont.', 'error' );
    }

    $email = isset( $context['email'] ) ? sanitize_email( (string) $context['email'] ) : '';
    if ( '' === $email || ! is_email( $email ) ) {
      $this->redirect_to_portal( 'training_convocations', 'Aucune adresse e-mail sur la fiche apprenant : rien n’a été envoyé.', 'error' );
    }

    /* ACDC 3.25.268 — LA MÊME PORTE, DEPUIS CE BOUTON AUSSI.
       Une règle qui ne vaut que sur un chemin n'est pas une règle. Le moteur
       retient la convocation tant que la séance est en brouillon ; ce bouton-ci
       l'aurait envoyée quand même — et pire qu'ailleurs, car le contexte écarte
       les brouillons : faute de séance, il retombe sur la date de création du
       dossier et annonce à l'apprenant une journée qui n'a jamais été
       planifiée. Un refus qui nomme le geste manquant vaut mieux qu'un envoi
       qui invente une date. */
    $acdc_seances_liees = $this->get_training_file_linked_sessions( $registration );
    $acdc_a_une_validee = false;
    $acdc_a_un_brouillon = false;
    foreach ( (array) $acdc_seances_liees as $acdc_seance ) {
      if ( \ACDC\Support\SessionDraftGate::isDraft( $acdc_seance ) ) {
        $acdc_a_un_brouillon = true;
      } elseif ( 'Annulée' !== (string) ( $acdc_seance->status ?? '' ) ) {
        $acdc_a_une_validee = true;
      }
    }
    if ( $acdc_a_un_brouillon && ! $acdc_a_une_validee ) {
      $this->redirect_to_portal(
        'training_convocations',
        'Convocation retenue : la séance de ce dossier est encore en brouillon. Désignez son formateur et validez-la — la convocation annonce un intervenant, et un apprenant convoqué sans formateur se présente pour rien.',
        'error'
      );
    }

    /* ACDC 3.25.252 — Récapitulatif, corps et pièce jointe : composés une seule
       fois, pour les trois chemins d'envoi. Cet e-mail-ci annonçait « Durée » et
       « Format » quand les deux autres annonçaient les horaires et le lieu ;
       l'apprenant recevait un courrier différent selon le bouton cliqué par
       l'organisme. */
    global $wpdb;
    $conv_contract = ! empty( $registration->autofill_contract_id )
      ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->registration_contract_table} WHERE id = %d", (int) $registration->autofill_contract_id ) )
      : null;
    /* La première séance planifiée du dossier : c'est elle qui porte le déroulé
       en demi-journées et, quand elle est renseignée, l'adresse du terrain. */
    $conv_session   = ( ! empty( $context['sessions'] ) && is_array( $context['sessions'] ) ) ? $context['sessions'][0] : null;
    $conv_formation = ( isset( $context['formation'] ) && is_object( $context['formation'] ) ) ? $context['formation'] : null;

    $formation = (string) ( $context['formation_title'] ?? 'Formation' );
    $parts     = $this->acdc_convocation_email_parts( array(
      'formation_title' => $formation,
      'session'         => $conv_session,
      'contract'        => $conv_contract,
      'formation'       => $conv_formation,
      'registration'    => $registration,
      'start'           => (string) ( $context['start_date'] ?? '' ),
      'end'             => (string) ( $context['end_date'] ?? '' ),
    ) );
    $attachments = $parts['attachments'];

    $sent = $this->acdc_send_transactional_email(
      $email,
      'Convocation — ' . $formation,
      array(
        'greeting_name' => (string) ( $context['learner_name'] ?? '' ),
        'intro_html'    => '',
        'summary_title' => 'DÉTAILS DE VOTRE CONVOCATION',
        'summary_rows'  => $parts['summary_rows'],
        'body_html'     => $parts['body_html'],
        'footer_notice' => 'Cet e-mail est votre convocation officielle. Conservez-le pour vos dossiers.',
      ),
      array(
        'source_module'       => 'documents',
        'source_action'       => 'convocation_manuelle',
        'related_entity_type' => 'registration',
        'related_entity_id'   => $registration_id,
        'email_category'      => 'convocation',
        'email_audience'      => 'apprenant',
      ),
      $attachments
    );

    if ( ! $sent ) {
      $this->redirect_to_portal(
        'training_convocations',
        'La convocation n’est pas partie à ' . $email . '. Si le mode recette est actif, vérifiez la liste des destinataires autorisés — le refus est journalisé.',
        'error'
      );
    }

    if ( method_exists( $this, 'log_action_event' ) ) {
      $this->log_action_event( 'send', 'training_convocation', $registration_id, 'success', array(
        'destinataire' => $email,
        'origine'      => 'envoi manuel',
      ) );
    }

    $this->redirect_to_portal( 'training_convocations', 'Convocation envoyée à ' . $email . '.', 'success' );
  }

  public function handle_download_completion_certificate_document() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  $registration_id = isset( $_GET['registration_id'] ) ? absint( wp_unslash( $_GET['registration_id'] ) ) : 0;
  if ( ! $registration_id ) {
    wp_die( esc_html( 'Certificat introuvable.' ) );
  }
  check_admin_referer( 'acdc_download_completion_certificate_document_' . $registration_id );
  $registration = $this->get_training_registration( $registration_id );
  if ( ! $registration ) {
    wp_die( esc_html( 'Certificat introuvable.' ) );
  }
  $context = $this->get_completion_certificate_context( $registration );
  $document = $context['document'];
  $filename = $this->get_completion_certificate_display_file_name( $registration, $context, $document );
  $mode = ( isset( $_GET['mode'] ) && 'inline' === sanitize_key( wp_unslash( $_GET['mode'] ) ) ) ? 'inline' : 'attachment';
  if ( ! empty( $document['path'] ) && file_exists( $document['path'] ) ) {
    $mime = 'application/pdf';
    while ( ob_get_level() ) { ob_end_clean(); }
    nocache_headers();
    header( 'Content-Type: ' . $mime );
    header( 'Content-Disposition: ' . $mode . '; filename="' . sanitize_file_name( $filename ) . '"' );
    readfile( $document['path'] );
    exit;
  }
  $pages = $this->build_completion_certificate_pdf_pages( $registration, $context );
  $this->render_simple_pdf( $pages, $filename, $mode );
}public function handle_update_completion_certificate_document() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  $registration_id = isset( $_POST['registration_id'] ) ? absint( wp_unslash( $_POST['registration_id'] ) ) : 0;
  if ( ! $registration_id ) {
    wp_die( esc_html( 'Certificat introuvable.' ) );
  }
  check_admin_referer( 'acdc_update_completion_certificate_document_' . $registration_id );
  if ( empty( $_FILES['completion_certificate_document_file']['name'] ) ) {
    wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'completion_certificates', 'notice' => rawurlencode( 'Aucun fichier reçu.' ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
    exit;
  }
  require_once ABSPATH . 'wp-admin/includes/file.php';
  $upload = wp_handle_upload( $_FILES['completion_certificate_document_file'], array( 'test_form' => false, 'mimes' => array( 'pdf' => 'application/pdf' ) ) );
  if ( ! empty( $upload['error'] ) ) {
    wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'completion_certificates', 'notice' => rawurlencode( 'Téléversement impossible : ' . $upload['error'] ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
    exit;
  }
  global $wpdb;
  $result = $wpdb->update(
    $this->training_registration_table,
    array(
      'completion_certificate_document_url' => isset( $upload['url'] ) ? esc_url_raw( $upload['url'] ) : '',
      'completion_certificate_document_path' => isset( $upload['file'] ) ? sanitize_text_field( $upload['file'] ) : '',
      'updated_at' => current_time( 'mysql' ),
    ),
    array( 'id' => $registration_id )
  );
  if ( false === $result ) {
    wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'completion_certificates', 'notice' => rawurlencode( 'Erreur lors de la mise à jour.' ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
    exit;
  }
  wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'completion_certificates', 'notice' => rawurlencode( 'Certificat de réalisation mis à jour.' ), 'notice_type' => 'success' ), admin_url( 'admin.php' ) ) );
  exit;
}public function handle_export_end_training_certificate_qcm_details() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  $registration_id = isset( $_POST['registration_id'] ) ? absint( wp_unslash( $_POST['registration_id'] ) ) : 0;
  if ( ! $registration_id ) {
    wp_die( esc_html( 'Document introuvable.' ) );
  }
  check_admin_referer( 'acdc_export_end_training_certificate_qcm_details_' . $registration_id );
  $registration = $this->get_training_registration( $registration_id );
  if ( ! $registration ) {
    wp_die( esc_html( 'Document introuvable.' ) );
  }
  $context = $this->get_end_training_certificate_context( $registration );
  $filename = ! empty( $_POST['export_filename'] ) ? sanitize_file_name( wp_unslash( $_POST['export_filename'] ) ) : 'details-qcm';
  if ( '' === $filename ) { $filename = 'details-qcm'; }
  $type = ! empty( $_POST['export_type'] ) ? sanitize_key( wp_unslash( $_POST['export_type'] ) ) : 'excel';
  $rows = array();
  $rows[] = array( 'Ordre', 'Question', 'Type', 'Options / consignes', 'Apprenant', 'Formation', 'Résultat' );
  foreach ( (array) $context['questions_details'] as $question ) {
    $rows[] = array(
      (string) $question['number'],
      (string) $question['label'],
      (string) $question['type'],
      (string) $question['options'],
      (string) $context['learner_name'],
      (string) $context['formation_title'],
      (string) $context['result_label'],
    );
  }
  while ( ob_get_level() ) { ob_end_clean(); }
  nocache_headers();
  if ( 'csv' === $type ) {
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '.csv"' );
    $output = fopen( 'php://output', 'w' );
    foreach ( $rows as $row ) {
      fputcsv( $output, $row, ';' );
    }
    fclose( $output );
    exit;
  }
  header( 'Content-Type: application/vnd.ms-excel; charset=utf-8' );
  header( 'Content-Disposition: attachment; filename="' . $filename . '.xls"' );
  echo "<html><head><meta charset='utf-8'></head><body><table border='1'>";
  foreach ( $rows as $index => $row ) {
    echo '<tr>';
    foreach ( $row as $cell ) {
      $tag = 0 === $index ? 'th' : 'td';
      echo '<' . $tag . '>' . esc_html( (string) $cell ) . '</' . $tag . '>';
    }
    echo '</tr>';
  }
  echo "</table></body></html>";
  exit;
}public function handle_download_end_training_certificate_document() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  $registration_id = isset( $_GET['registration_id'] ) ? absint( wp_unslash( $_GET['registration_id'] ) ) : 0;
  if ( ! $registration_id ) {
    wp_die( esc_html( 'Document introuvable.' ) );
  }
  check_admin_referer( 'acdc_download_end_training_certificate_document_' . $registration_id );
  $registration = $this->get_training_registration( $registration_id );
  if ( ! $registration ) {
    wp_die( esc_html( 'Document introuvable.' ) );
  }
  $context = $this->get_end_training_certificate_context( $registration );
  $document = $context['document'];
  $filename = $this->get_end_training_certificate_display_file_name( $registration, $context, $document );
  $mode = ( isset( $_GET['mode'] ) && 'inline' === sanitize_key( wp_unslash( $_GET['mode'] ) ) ) ? 'inline' : 'attachment';
  if ( ! empty( $document['path'] ) && file_exists( $document['path'] ) ) {
    while ( ob_get_level() ) { ob_end_clean(); }
    nocache_headers();
    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: ' . $mode . '; filename="' . sanitize_file_name( $filename ) . '"' );
    readfile( $document['path'] );
    exit;
  }
  $pages = $this->build_end_training_certificate_pdf_pages( $registration, $context );
  $this->render_simple_pdf( $pages, $filename, $mode );
}public function handle_update_end_training_certificate_document() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  $registration_id = isset( $_POST['registration_id'] ) ? absint( wp_unslash( $_POST['registration_id'] ) ) : 0;
  if ( ! $registration_id ) {
    wp_die( esc_html( 'Document introuvable.' ) );
  }
  check_admin_referer( 'acdc_update_end_training_certificate_document_' . $registration_id );
  if ( empty( $_FILES['end_training_certificate_document_file']['name'] ) ) {
    wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'end_training_certificates', 'notice' => rawurlencode( 'Aucun fichier reçu.' ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
    exit;
  }
  require_once ABSPATH . 'wp-admin/includes/file.php';
  $upload = wp_handle_upload( $_FILES['end_training_certificate_document_file'], array( 'test_form' => false, 'mimes' => array( 'pdf' => 'application/pdf' ) ) );
  if ( ! empty( $upload['error'] ) ) {
    wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'end_training_certificates', 'notice' => rawurlencode( 'Téléversement impossible : ' . $upload['error'] ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
    exit;
  }
  global $wpdb;
  $result = $wpdb->update(
    $this->training_registration_table,
    array(
      'end_training_certificate_document_url' => isset( $upload['url'] ) ? esc_url_raw( $upload['url'] ) : '',
      'end_training_certificate_document_path' => isset( $upload['file'] ) ? sanitize_text_field( $upload['file'] ) : '',
      'updated_at' => current_time( 'mysql' ),
    ),
    array( 'id' => $registration_id )
  );
  if ( false === $result ) {
    wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'end_training_certificates', 'notice' => rawurlencode( 'Erreur lors de la mise à jour.' ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
    exit;
  }
  wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'end_training_certificates', 'notice' => rawurlencode( 'Attestation de fin de formation mise à jour.' ), 'notice_type' => 'success' ), admin_url( 'admin.php' ) ) );
  exit;
}public function handle_save_company() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      $this->redirect_to_login( 'Accès réservé aux administrateurs.', 'error' );
    }

    check_admin_referer( 'acdc_save_company' );

    global $wpdb;

    $company_id = isset( $_POST['company_id'] ) ? absint( wp_unslash( $_POST['company_id'] ) ) : 0;
    $form_input = array(
      'siret'               => isset( $_POST['siret'] ) ? wp_unslash( $_POST['siret'] ) : '',
      'name'                => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
      'legal_form'          => isset( $_POST['legal_form'] ) ? wp_unslash( $_POST['legal_form'] ) : '',
      'website'             => isset( $_POST['website'] ) ? wp_unslash( $_POST['website'] ) : '',
      'naf_code'            => isset( $_POST['naf_code'] ) ? wp_unslash( $_POST['naf_code'] ) : '',
      'nafa_code'           => isset( $_POST['nafa_code'] ) ? wp_unslash( $_POST['nafa_code'] ) : '',
      'aprn_code'           => isset( $_POST['aprn_code'] ) ? wp_unslash( $_POST['aprn_code'] ) : '',
      'address'             => isset( $_POST['address'] ) ? wp_unslash( $_POST['address'] ) : '',
      'address_extra'       => isset( $_POST['address_extra'] ) ? wp_unslash( $_POST['address_extra'] ) : '',
      'postal_code'         => isset( $_POST['postal_code'] ) ? wp_unslash( $_POST['postal_code'] ) : '',
      'city'                => isset( $_POST['city'] ) ? wp_unslash( $_POST['city'] ) : '',
      'email'               => isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '',
      'phone'               => isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '',
      'signer_first_name'   => isset( $_POST['signer_first_name'] ) ? wp_unslash( $_POST['signer_first_name'] ) : '',
      'signer_last_name'    => isset( $_POST['signer_last_name'] ) ? wp_unslash( $_POST['signer_last_name'] ) : '',
      'signer_quality'      => isset( $_POST['signer_quality'] ) ? wp_unslash( $_POST['signer_quality'] ) : '',
      'signature_documents' => isset( $_POST['signature_documents'] ) ? wp_unslash( $_POST['signature_documents'] ) : '',
      'copy_recipients'     => isset( $_POST['copy_recipients'] ) ? wp_unslash( $_POST['copy_recipients'] ) : '',
      'contacts_annexes'    => isset( $_POST['contacts_annexes'] ) ? wp_unslash( $_POST['contacts_annexes'] ) : '',
      'contact_alert_at'    => isset( $_POST['contact_alert_at'] ) ? wp_unslash( $_POST['contact_alert_at'] ) : '',
      'notes'               => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
    );

    $required_keys = array( 'siret', 'name', 'address', 'postal_code', 'city', 'email', 'signer_first_name', 'signer_last_name' );
    $missing_required = array();
    foreach ( $required_keys as $required_key ) {
      if ( '' === trim( (string) $form_input[ $required_key ] ) ) {
        $missing_required[] = $required_key;
      }
    }

    if ( ! empty( $missing_required ) ) {
      $this->acdc_store_form_state( 'company', $form_input, $missing_required );
      $this->redirect_to_portal( 'companies', 'Merci de renseigner les champs obligatoires.', 'error', array( 'action' => $company_id ? 'edit' : 'new', 'item_id' => $company_id ) );
    }

    /* ACDC 3.25.157 — LOT 2 : contrôle du format SIRET, absent ici alors que le
       formulaire Prospect impose strictement 14 chiffres. Un commanditaire à 13
       chiffres passait, et se retrouvait ensuite sur les devis et les conventions.
       Le SIRET est également NORMALISÉ (espaces et points retirés) : le répertoire
       contenait les deux graphies, ce qui empêchait tout rapprochement fiable. */
    $siret_digits = preg_replace( '/\D/', '', (string) $form_input['siret'] );
    if ( '' !== trim( (string) $form_input['siret'] ) && 14 !== strlen( $siret_digits ) ) {
      $this->acdc_store_form_state( 'company', $form_input, array( 'siret' ) );
      $this->redirect_to_portal( 'companies', 'Le SIRET doit contenir exactement 14 chiffres.', 'error', array( 'action' => $company_id ? 'edit' : 'new', 'item_id' => $company_id ) );
    }
    if ( 14 === strlen( $siret_digits ) ) {
      $form_input['siret'] = $siret_digits;
    }

    $email = sanitize_email( (string) $form_input['email'] );
    if ( '' === $email || ! is_email( $email ) ) {
      $this->acdc_store_form_state( 'company', $form_input, array( 'email' ) );
      $this->redirect_to_portal( 'companies', 'Merci de renseigner une adresse e-mail valide.', 'error', array( 'action' => $company_id ? 'edit' : 'new', 'item_id' => $company_id ) );
    }
    $signature_documents = $this->acdc_build_company_signature_documents_label( $form_input['signer_first_name'], $form_input['signer_last_name'], $form_input['signer_quality'] );

    $data = array(
      'name'                => sanitize_text_field( (string) $form_input['name'] ),
      'legal_form'          => sanitize_text_field( (string) $form_input['legal_form'] ),
      'siret'               => sanitize_text_field( (string) $form_input['siret'] ),
      'naf_code'            => sanitize_text_field( (string) $form_input['naf_code'] ),
      'nafa_code'           => sanitize_text_field( (string) $form_input['nafa_code'] ),
      'aprn_code'           => sanitize_text_field( (string) $form_input['aprn_code'] ),
      'email'               => $email,
      'phone'               => sanitize_text_field( (string) $form_input['phone'] ),
      'website'             => esc_url_raw( (string) $form_input['website'] ),
      'address'             => sanitize_text_field( (string) $form_input['address'] ),
      'address_extra'       => sanitize_text_field( (string) $form_input['address_extra'] ),
      'postal_code'         => sanitize_text_field( (string) $form_input['postal_code'] ),
      'city'                => sanitize_text_field( (string) $form_input['city'] ),
      'signer_first_name'   => sanitize_text_field( (string) $form_input['signer_first_name'] ),
      'signer_last_name'    => sanitize_text_field( (string) $form_input['signer_last_name'] ),
      'signer_quality'      => sanitize_text_field( (string) $form_input['signer_quality'] ),
      'signature_documents' => sanitize_text_field( (string) $signature_documents ),
      'copy_recipients'     => sanitize_textarea_field( (string) $form_input['copy_recipients'] ),
      'contacts_annexes'    => sanitize_textarea_field( (string) $form_input['contacts_annexes'] ),
      'contact_alert_at'    => $this->datetime_from_local( (string) $form_input['contact_alert_at'] ),
      'notes'               => sanitize_textarea_field( (string) $form_input['notes'] ),
      'updated_at'          => $this->now_mysql(),
    );

    if ( $company_id ) {
      $result  = $wpdb->update( $this->company_table, $data, array( 'id' => $company_id ) );
      if ( false === $result ) {
        $this->acdc_store_form_state( 'company', $form_input );
        $this->redirect_to_portal( 'companies', 'Erreur lors de la mise à jour. Veuillez réessayer.', 'error', array( 'action' => 'edit', 'item_id' => $company_id ) );
      }
      $message = 'Entreprise mise à jour.';
    } else {
      $data['created_at'] = $this->now_mysql();
      $result = $wpdb->insert( $this->company_table, $data );
      if ( false === $result ) {
        $this->acdc_store_form_state( 'company', $form_input );
        $this->redirect_to_portal( 'companies', 'Erreur lors de l\'enregistrement. Veuillez réessayer.', 'error', array( 'action' => 'new' ) );
      }
      $company_id = (int) $wpdb->insert_id;
      $message = 'Entreprise enregistrée.';
    }

    $return_after_save = $this->acdc_get_post_return_after_save( 'view', array( 'view', 'edit', 'list', 'new' ) );
    $success_args = array();
    if ( 'list' === $return_after_save ) {
      $success_args['action'] = 'list';
    } elseif ( 'new' === $return_after_save ) {
      $success_args['action'] = 'new';
    } else {
      $success_args['action'] = $return_after_save;
      $success_args['item_id'] = $company_id;
    }

    $this->redirect_to_portal( 'companies', $message, 'success', $success_args );
  }  public function handle_delete_company() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      $this->redirect_to_login( 'Accès réservé aux administrateurs.', 'error' );
    }

    $company_id = isset( $_GET['company_id'] ) ? absint( wp_unslash( $_GET['company_id'] ) ) : 0;
    if ( ! $company_id ) {
      $this->redirect_to_portal( 'companies', 'Entreprise introuvable.', 'error' );
    }

    check_admin_referer( 'acdc_delete_company_' . $company_id );

    global $wpdb;
    $contact_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->contact_table} WHERE company_id = %d", $company_id ) );
    $document_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->document_table} WHERE company_id = %d", $company_id ) );
    $session_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->session_table} WHERE company_id = %d", $company_id ) );
    $learner_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->learner_table} WHERE company_id = %d", $company_id ) );

    if ( $contact_count || $document_count || $session_count || $learner_count ) {
      $this->redirect_to_portal( 'companies', 'Suppression impossible : cette entreprise est encore liée à des données.', 'error' );
    }

    $wpdb->delete( $this->company_table, array( 'id' => $company_id ) );
    $this->redirect_to_portal( 'companies', 'Entreprise supprimée.', 'success' );
  }  private function handle_optional_upload( $file_key ) {
    if ( empty( $_FILES[ $file_key ] ) || empty( $_FILES[ $file_key ]['name'] ) ) {
      return '';
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';

    $validated = $this->validate_uploaded_file_array( $_FILES[ $file_key ], $file_key );
    if ( is_wp_error( $validated ) ) {
      return '';
    }

    $upload = wp_handle_upload(
      $validated,
      array(
        'test_form' => false,
        'mimes'     => $this->get_allowed_mimes_for_upload_key( $file_key ),
      )
    );
    if ( isset( $upload['error'] ) ) {
      return '';
    }
    return isset( $upload['url'] ) ? esc_url_raw( $upload['url'] ) : '';
  }

  /**
   * ACDC 3.20.77 — Fusion d'une liste existante de documents avec les retraits demandés
   * et les nouveaux uploads. Préserve l'ordre d'origine, ajoute les nouveaux à la fin,
   * déduplique. Les éléments sont des URLs séparées par des retours ligne.
   *
   * @param string $existing_multiline   Anciennes URLs en BD (séparées par \n).
   * @param array  $urls_to_remove       URLs marquées pour suppression (cases cochées).
   * @param string $newly_uploaded_multi URLs fraichement uploadées (séparées par \n).
   * @return string                      Nouvelle valeur à stocker en BD.
   */
  private function acdc_merge_doc_lists( $existing_multiline, $urls_to_remove, $newly_uploaded_multi ) {
    $existing_list = array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) $existing_multiline ) ) );
    $remove_list   = array();
    if ( is_array( $urls_to_remove ) ) {
      foreach ( $urls_to_remove as $u ) {
        $u = trim( (string) $u );
        if ( '' !== $u ) { $remove_list[ $u ] = true; }
      }
    }
    $kept = array();
    foreach ( $existing_list as $url ) {
      if ( ! isset( $remove_list[ $url ] ) ) {
        $kept[] = $url;
      }
    }
    $new_list = array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) $newly_uploaded_multi ) ) );
    foreach ( $new_list as $url ) {
      if ( ! in_array( $url, $kept, true ) ) {
        $kept[] = $url;
      }
    }
    return implode( "\n", $kept );
  }

  private function handle_multiple_uploads( $file_key ) {
    if ( empty( $_FILES[ $file_key ] ) || empty( $_FILES[ $file_key ]['name'] ) || ! is_array( $_FILES[ $file_key ]['name'] ) ) {
      return '';
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $urls = array();
    $count = count( $_FILES[ $file_key ]['name'] );
    for ( $i = 0; $i < $count; $i++ ) {
      if ( empty( $_FILES[ $file_key ]['name'][ $i ] ) ) {
        continue;
      }
      $file = array(
        'name'     => $_FILES[ $file_key ]['name'][ $i ],
        'type'     => $_FILES[ $file_key ]['type'][ $i ],
        'tmp_name' => $_FILES[ $file_key ]['tmp_name'][ $i ],
        'error'    => $_FILES[ $file_key ]['error'][ $i ],
        'size'     => $_FILES[ $file_key ]['size'][ $i ],
      );
      $validated = $this->validate_uploaded_file_array( $file, $file_key );
      if ( is_wp_error( $validated ) ) {
        continue;
      }
      $upload = wp_handle_upload(
        $validated,
        array(
          'test_form' => false,
          'mimes'     => $this->get_allowed_mimes_for_upload_key( $file_key ),
        )
      );
      if ( empty( $upload['error'] ) && ! empty( $upload['url'] ) ) {
        $urls[] = esc_url_raw( $upload['url'] );
      }
    }
    return implode( "
", $urls );
  }  public function handle_import_formations() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      $this->redirect_to_login( 'Accès réservé aux administrateurs.', 'error' );
    }
    check_admin_referer( 'acdc_import_formations' );

    if ( empty( $_FILES['formations_file'] ) || ! is_array( $_FILES['formations_file'] ) ) {
      $this->redirect_to_portal( 'formations', 'Aucun fichier Excel n’a été reçu.', 'error', array( 'action' => 'import' ) );
    }

    $validated = $this->validate_uploaded_file_array( wp_unslash( $_FILES['formations_file'] ), 'formations_file' );
    if ( is_wp_error( $validated ) ) {
      $this->redirect_to_portal( 'formations', $validated->get_error_message(), 'error', array( 'action' => 'import' ) );
    }

    $rows = $this->read_xlsx_rows( $validated['tmp_name'] );
    if ( is_wp_error( $rows ) ) {
      $this->redirect_to_portal( 'formations', $rows->get_error_message(), 'error', array( 'action' => 'import' ) );
    }

    if ( empty( $rows ) || empty( $rows[0] ) ) {
      $this->redirect_to_portal( 'formations', 'Le fichier Excel est vide ou ne contient pas d’en-têtes exploitables.', 'error', array( 'action' => 'import' ) );
    }

    $mapping = $this->map_formation_import_headers( $rows[0] );
    // La colonne Intitulé est obligatoire SAUF si la colonne ID formation est présente
    // (dans ce cas, la mise à jour se fait par ID — l'intitulé peut être conservé depuis l'existant).
    if ( ! isset( $mapping['title'] ) && ! isset( $mapping['formation_id'] ) ) {
      $this->redirect_to_portal( 'formations', 'Le fichier doit contenir au moins la colonne « Intitulé » ou la colonne « ID formation ».', 'error', array( 'action' => 'import' ) );
    }

    // Option "Écraser les champs vides" : décochée par défaut.
    $overwrite_empty = ! empty( $_POST['overwrite_empty'] );
    $import_options  = array( 'overwrite_empty' => $overwrite_empty );

    // Index de la colonne ID formation (facultatif — prioritaire sur la recherche par titre).
    $formation_id_col = isset( $mapping['formation_id'] ) ? (int) $mapping['formation_id'] : null;

    global $wpdb;
    $created = 0;
    $updated = 0;
    $errors  = array();

    foreach ( $rows as $row_index => $row ) {
      if ( 0 === $row_index ) {
        continue;
      }

      $title_index = isset( $mapping['title'] ) ? (int) $mapping['title'] : -1;
      $raw_title   = ( $title_index >= 0 && isset( $row[ $title_index ] ) ) ? trim( (string) $row[ $title_index ] ) : '';

      // Recherche de la formation existante :
      // 1. Par ID si la colonne "ID formation" est présente et non vide.
      // 2. Par intitulé exact sinon.
      $existing = null;
      if ( null !== $formation_id_col ) {
        $raw_id = isset( $row[ $formation_id_col ] ) ? absint( $row[ $formation_id_col ] ) : 0;
        if ( $raw_id > 0 ) {
          $existing = $this->get_formation( $raw_id );
        }
      }
      if ( ! $existing && '' !== $raw_title ) {
        $existing = $this->get_formation_by_title( $raw_title );
      }

      // Ligne sans intitulé ET sans ID valide : ignorer.
      if ( '' === $raw_title && ! $existing ) {
        if ( $row_index > 0 ) {
          $errors[] = 'Ligne ' . ( $row_index + 1 ) . ' : intitulé manquant et aucun ID reconnu — ligne ignorée.';
        }
        continue;
      }

      $data = $this->build_formation_import_data_from_row( $row, $mapping, $existing, $import_options );
      if ( is_wp_error( $data ) ) {
        $errors[] = 'Ligne ' . ( $row_index + 1 ) . ' : ' . $data->get_error_message();
        continue;
      }

      if ( $existing ) {
        $result = $wpdb->update( $this->formation_table, $data, array( 'id' => $existing->id ) );
        if ( false === $result ) {
          $errors[] = 'Ligne ' . ( $row_index + 1 ) . ' : échec de mise à jour pour « ' . ( $raw_title ?: 'ID ' . $existing->id ) . ' ». ' . $this->get_safe_db_error_message( 'Erreur base de données.' );
          continue;
        }
        $updated++;
      } else {
        $result = $wpdb->insert( $this->formation_table, $data );
        if ( false === $result ) {
          $errors[] = 'Ligne ' . ( $row_index + 1 ) . ' : échec de création pour « ' . $raw_title . ' ». ' . $this->get_safe_db_error_message( 'Erreur base de données.' );
          continue;
        }
        $created++;
      }
    }

    $message = sprintf(
      'Import terminé : %1$d création(s), %2$d mise(s) à jour, %3$d erreur(s).',
      (int) $created,
      (int) $updated,
      (int) count( $errors )
    );

    if ( ! empty( $errors ) ) {
      $message .= ' ' . implode( ' | ', array_slice( $errors, 0, 5 ) );
      if ( count( $errors ) > 5 ) {
        $message .= ' | Autres erreurs : ' . ( count( $errors ) - 5 ) . '.';
      }
    }

    $this->redirect_to_portal( 'formations', $message, empty( $errors ) ? 'success' : 'error', array( 'action' => 'import' ) );
  }

  /**
   * Handler admin_post : exporte toutes les formations en XLSX.
   * Le fichier produit peut être réimporté sans doublon.
   */
  public function handle_export_formations() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      wp_die( esc_html( 'Accès réservé aux administrateurs.' ) );
    }
    check_admin_referer( 'acdc_export_formations' );

    $formations = $this->get_formations(); // toutes (actives + archivées)
    $xlsx       = $this->build_formations_xlsx( $formations );

    if ( '' === $xlsx ) {
      $this->redirect_to_portal( 'formations', 'Impossible de générer le fichier Excel. Vérifiez que l’extension ZipArchive est disponible sur le serveur.', 'error' );
    }

    $filename = 'acdc-formations-export-' . date_i18n( 'Y-m-d-His' ) . '.xlsx';
    while ( ob_get_level() ) {
      ob_end_clean();
    }
    nocache_headers();
    header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Length: ' . strlen( $xlsx ) );
    echo $xlsx; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
  }

    public function handle_duplicate_formation() {
    $this->require_admin_manager_access();
    $formation_id = isset( $_GET['formation_id'] ) ? absint( wp_unslash( $_GET['formation_id'] ) ) : 0;

    if ( ! $formation_id ) {
      $this->redirect_to_portal( 'formations', 'Formation introuvable.', 'error' );
    }

    $this->verify_nonce_or_die( 'acdc_duplicate_formation_' . $formation_id );

    global $wpdb;
    $source = $this->get_formation( $formation_id );

    if ( ! $source ) {
      $this->redirect_to_portal( 'formations', 'Formation introuvable.', 'error' );
    }

    $base_id = ! empty( $source->base_formation_id ) ? (int) $source->base_formation_id : (int) $source->id;
    $max_variant = (int) $wpdb->get_var(
      $wpdb->prepare(
        "SELECT MAX(variant_number) FROM {$this->formation_table} WHERE base_formation_id = %d",
        $base_id
      )
    );

    if ( $max_variant < 1 ) {
      $codes = $wpdb->get_col(
        $wpdb->prepare(
          "SELECT code FROM {$this->formation_table} WHERE code LIKE %s",
          $wpdb->esc_like( (string) $base_id . '.' ) . '%'
        )
      );
      foreach ( (array) $codes as $code ) {
        if ( preg_match( '/^' . preg_quote( (string) $base_id, '/' ) . '\.(\d+)$/', (string) $code, $matches ) ) {
          $max_variant = max( $max_variant, (int) $matches[1] );
        }
      }
    }

    $next_variant = $max_variant + 1;
    $data = (array) $source;
    unset( $data['id'] );

    $data['code'] = $base_id . '.' . $next_variant;
    $data['base_formation_id'] = $base_id;
    $data['variant_number'] = $next_variant;
    $data['created_at'] = $this->now_mysql();
    $data['updated_at'] = $this->now_mysql();

    $inserted = $wpdb->insert( $this->formation_table, $data );

    if ( false === $inserted ) {
      $this->redirect_to_portal( 'formations', 'Erreur lors de la duplication de la formation.', 'error' );
    }

    $new_id = (int) $wpdb->insert_id;
    $this->redirect_to_portal( 'formations', 'Formation dupliquée avec l’identifiant ' . $data['code'] . '.', 'success', array( 'action' => 'edit', 'item_id' => $new_id ) );
  }

  public function handle_toggle_formation_archive() {
    $this->require_admin_manager_access();
    $formation_id = isset( $_GET['formation_id'] ) ? absint( wp_unslash( $_GET['formation_id'] ) ) : 0;
    $archive = isset( $_GET['archive'] ) ? absint( wp_unslash( $_GET['archive'] ) ) : 0;
    if ( ! $formation_id ) {
      $this->redirect_to_portal( 'formations', 'Formation introuvable.', 'error' );
    }
    $this->verify_nonce_or_die( 'acdc_toggle_formation_archive_' . $formation_id );
    global $wpdb;
    $result = $wpdb->update( $this->formation_table, array( 'is_active' => $archive ? 0 : 1, 'status' => $archive ? 'ARCHIVÉE' : 'VALIDÉE', 'updated_at' => $this->now_mysql() ), array( 'id' => $formation_id ) );
    if ( false === $result ) {
      $this->redirect_to_portal( 'formations', 'Erreur lors de la mise à jour du statut.', 'error' );
    }
    $this->redirect_to_portal( 'formations', $archive ? 'Formation archivée.' : 'Formation restaurée.', 'success', array( 'action' => $archive ? 'archived' : 'list' ) );
  }  public function handle_save_formation() {
    $this->require_admin_manager_nonce( 'acdc_save_formation' );

    global $wpdb;
    $formation_id = isset( $_POST['formation_id'] ) ? absint( wp_unslash( $_POST['formation_id'] ) ) : 0;
    $title    = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';

    if ( '' === $title ) {
      $this->redirect_to_portal( 'formations', 'Le titre est obligatoire.', 'error', array( 'action' => $formation_id ? 'edit' : 'new', 'item_id' => $formation_id ) );
    }

    $program_file_url = $this->handle_optional_upload( 'program_file' );
    $catalog_image_url = $this->handle_optional_upload( 'catalog_image' );
    /* ACDC 3.24.29 — S3 : si aucun fichier uploadé, on utilise l'URL directe saisie dans le formulaire. */
    if ( empty( $program_file_url ) && ! empty( $_POST['program_file_url_direct'] ) ) {
      $program_file_url = esc_url_raw( wp_unslash( $_POST['program_file_url_direct'] ) );
    }
    if ( empty( $catalog_image_url ) && ! empty( $_POST['catalog_image_url_direct'] ) ) {
      $catalog_image_url = esc_url_raw( wp_unslash( $_POST['catalog_image_url_direct'] ) );
    }
    /* ACDC 3.20.77 — Comportement add + delete sélectif :
       on conserve les anciens documents, on retire ceux marqués via les checkbox de suppression,
       et on concatène les nouveaux fichiers uploadés. */
    $shared_docs_uploads   = $this->handle_multiple_uploads( 'shared_docs' );
    $internal_docs_uploads = $this->handle_multiple_uploads( 'internal_docs' );

    if ( $formation_id ) {
      $existing = $this->get_formation( $formation_id );
      if ( empty( $program_file_url ) && $existing ) {
        $program_file_url = $existing->program_file_url;
      }
      if ( empty( $catalog_image_url ) && $existing ) {
        $catalog_image_url = $existing->catalog_image_url;
      }

      // Documents partagés : merge ancien (filtré) + nouveaux uploadés.
      $existing_shared = $existing ? (string) $existing->shared_docs : '';
      $remove_shared   = isset( $_POST['shared_docs_remove'] ) && is_array( $_POST['shared_docs_remove'] )
        ? array_map( 'esc_url_raw', wp_unslash( $_POST['shared_docs_remove'] ) )
        : array();
      $shared_docs = $this->acdc_merge_doc_lists( $existing_shared, $remove_shared, $shared_docs_uploads );

      // Documents internes : idem.
      $existing_internal = $existing ? (string) $existing->internal_docs : '';
      $remove_internal   = isset( $_POST['internal_docs_remove'] ) && is_array( $_POST['internal_docs_remove'] )
        ? array_map( 'esc_url_raw', wp_unslash( $_POST['internal_docs_remove'] ) )
        : array();
      $internal_docs = $this->acdc_merge_doc_lists( $existing_internal, $remove_internal, $internal_docs_uploads );
    } else {
      // Création : pas d'existant à fusionner, on prend juste les uploads.
      $shared_docs   = $shared_docs_uploads;
      $internal_docs = $internal_docs_uploads;
    }

    $is_draft = ! empty( $_POST['is_draft'] ) ? 1 : 0;
    $is_active = 'ARCHIVÉE' === ( isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '' ) ? 0 : 1;

    // Le formulaire d'édition ne contient pas de champ "code" — on préserve la valeur existante
    // (issue d'une duplication via "Dupliquer", au format "X.Y") au lieu de l'écraser à vide.
    // Si la valeur a été perdue par un enregistrement antérieur (versions < 3.20.71) mais que
    // les marqueurs de variante sont présents, on la reconstruit automatiquement.
    if ( isset( $_POST['code'] ) ) {
      $code_value = sanitize_text_field( wp_unslash( $_POST['code'] ) );
    } elseif ( $formation_id && ! empty( $existing ) ) {
      if ( ! empty( $existing->code ) ) {
        $code_value = (string) $existing->code;
      } elseif ( ! empty( $existing->base_formation_id ) && ! empty( $existing->variant_number ) ) {
        $code_value = (int) $existing->base_formation_id . '.' . (int) $existing->variant_number;
      } else {
        $code_value = '';
      }
    } else {
      $code_value = '';
    }

    $data = array(
      'title' => $title,
      'code' => $code_value,
      'status' => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : ( $is_draft ? 'BROUILLON' : 'VALIDÉE' ),
      'qualiopi_compliant' => ! empty( $_POST['qualiopi_compliant'] ) ? 1 : 0,
      'duration' => isset( $_POST['duration'] ) ? sanitize_text_field( wp_unslash( $_POST['duration'] ) ) : '',
      'modality' => isset( $_POST['modality'] ) ? sanitize_text_field( wp_unslash( $_POST['modality'] ) ) : '',
      'description_text' => isset( $_POST['description_text'] ) ? wp_kses_post( wp_unslash( $_POST['description_text'] ) ) : '',
      'prerequisites' => isset( $_POST['prerequisites'] ) ? wp_kses_post( wp_unslash( $_POST['prerequisites'] ) ) : '',
      'objectives' => isset( $_POST['objectives'] ) ? wp_kses_post( wp_unslash( $_POST['objectives'] ) ) : '',
      'program' => isset( $_POST['program'] ) ? sanitize_textarea_field( wp_unslash( $_POST['program'] ) ) : '',
      'address' => isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '',
      'postal_code' => isset( $_POST['postal_code'] ) ? sanitize_text_field( wp_unslash( $_POST['postal_code'] ) ) : '',
      'city' => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
      'price_ht' => isset( $_POST['price_ht'] ) ? sanitize_text_field( wp_unslash( $_POST['price_ht'] ) ) : '',
      'program_file_url' => $program_file_url,
      'include_bpf' => ! empty( $_POST['include_bpf'] ) ? 1 : 0,
      'service_objective' => isset( $_POST['service_objective'] ) ? sanitize_text_field( wp_unslash( $_POST['service_objective'] ) ) : '',
      'service_objective_level' => isset( $_POST['service_objective_level'] ) ? sanitize_text_field( wp_unslash( $_POST['service_objective_level'] ) ) : '',
      'specialty' => isset( $_POST['specialty'] ) ? sanitize_text_field( wp_unslash( $_POST['specialty'] ) ) : '',
      'shared_docs' => $shared_docs,
      'internal_docs' => $internal_docs,
      'shared_links' => isset( $_POST['shared_links'] ) ? sanitize_textarea_field( wp_unslash( $_POST['shared_links'] ) ) : '',
      /* ACDC 3.25.278 — Les interrupteurs se lisent sur la liste commune : un
         interrupteur ajouté à l'écran et oublié ici ne serait jamais
         enregistré, et l'écran le rouvrirait éteint sans rien dire. */
      'catalog_public' => ! empty( $_POST['catalog_public'] ) ? 1 : 0,
      'catalog_slug' => isset( $_POST['catalog_slug'] ) ? sanitize_title( wp_unslash( $_POST['catalog_slug'] ) ) : '',
      'catalog_image_url' => $catalog_image_url,
      'catalog_order' => isset( $_POST['catalog_order'] ) ? absint( wp_unslash( $_POST['catalog_order'] ) ) : 0,
      'catalog_audience' => isset( $_POST['catalog_audience'] ) ? wp_kses_post( wp_unslash( $_POST['catalog_audience'] ) ) : '',
      'future_sessions' => isset( $_POST['future_sessions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['future_sessions'] ) ) : '',
      'is_draft' => $is_draft,
      'notes' => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
      'is_active' => $is_active,
      'updated_at'  => $this->now_mysql(),
      // ACDC 3.21.14 — Thématique analyse du besoin
      'thematique'  => ! empty( $_POST['thematique'] ) ? sanitize_text_field( wp_unslash( $_POST['thematique'] ) ) : null,
      // ACDC 3.21.23 — Moyens pédagogiques, ressources, sanction, évaluations détaillées
      'moyens_pedago'     => isset( $_POST['moyens_pedago'] )     ? sanitize_textarea_field( wp_unslash( $_POST['moyens_pedago'] ) )     : '',
      'ressources'        => isset( $_POST['ressources'] )        ? sanitize_textarea_field( wp_unslash( $_POST['ressources'] ) )        : '',
      'sanction'          => isset( $_POST['sanction'] )          ? sanitize_textarea_field( wp_unslash( $_POST['sanction'] ) )          : '',
      'evaluation_entree' => isset( $_POST['evaluation_entree'] ) ? sanitize_textarea_field( wp_unslash( $_POST['evaluation_entree'] ) ) : '',
      'evaluation_sortie' => isset( $_POST['evaluation_sortie'] ) ? sanitize_textarea_field( wp_unslash( $_POST['evaluation_sortie'] ) ) : '',
      // ACDC 3.21.36 — Champs étendus
      'accroche'            => isset( $_POST['accroche'] )            ? wp_kses_post( wp_unslash( $_POST['accroche'] ) )                            : '',
      'trainer_ref'         => isset( $_POST['trainer_ref'] )         ? wp_kses_post( wp_unslash( $_POST['trainer_ref'] ) )                         : '',
      'accessibilite'       => isset( $_POST['accessibilite'] )       ? wp_kses_post( wp_unslash( $_POST['accessibilite'] ) )                       : '',
      'referent_handicap'   => isset( $_POST['referent_handicap'] )   ? sanitize_text_field( wp_unslash( $_POST['referent_handicap'] ) )             : '',
      'suivi'               => isset( $_POST['suivi'] )               ? wp_kses_post( wp_unslash( $_POST['suivi'] ) )                               : '',
      'lieu_acces'          => isset( $_POST['lieu_acces'] )          ? wp_kses_post( wp_unslash( $_POST['lieu_acces'] ) )                          : '',
      'demarches'           => isset( $_POST['demarches'] )           ? wp_kses_post( wp_unslash( $_POST['demarches'] ) )                           : '',
      'annulation'          => isset( $_POST['annulation'] )          ? wp_kses_post( wp_unslash( $_POST['annulation'] ) )                          : '',
      'platform_url'        => isset( $_POST['platform_url'] )        ? esc_url_raw( wp_unslash( $_POST['platform_url'] ) )                         : '',
      'effectif_min'        => isset( $_POST['effectif_min'] )        ? absint( wp_unslash( $_POST['effectif_min'] ) )                              : 0,
      'effectif_max'        => isset( $_POST['effectif_max'] )        ? absint( wp_unslash( $_POST['effectif_max'] ) )                              : 0,
      'cpf_eligible'        => ! empty( $_POST['cpf_eligible'] )      ? 1                                                                           : 0,
      'cpf_code'            => isset( $_POST['cpf_code'] )            ? sanitize_text_field( wp_unslash( $_POST['cpf_code'] ) )                     : '',
      'financement'         => isset( $_POST['financement'] ) && is_array( $_POST['financement'] )
                                 ? wp_json_encode( array_map( 'sanitize_text_field', wp_unslash( $_POST['financement'] ) ), JSON_UNESCAPED_UNICODE )
                                 : ( isset( $_POST['financement'] ) ? sanitize_text_field( wp_unslash( $_POST['financement'] ) ) : '' ),
      'taux_reussite'       => isset( $_POST['taux_reussite'] )       ? absint( wp_unslash( $_POST['taux_reussite'] ) )                             : 0,
      'taux_satisfaction'   => isset( $_POST['taux_satisfaction'] )   ? absint( wp_unslash( $_POST['taux_satisfaction'] ) )                         : 0,
      'taux_recommandation' => isset( $_POST['taux_recommandation'] ) ? absint( wp_unslash( $_POST['taux_recommandation'] ) )                       : 0,
      'taux_completion'     => isset( $_POST['taux_completion'] )     ? absint( wp_unslash( $_POST['taux_completion'] ) )                           : 0,
    );

    // ACDC 3.21.23 — Programme pédagogique structuré (Jour 1–5 × Matin/APM × 4 champs).
    $blocs_prog = array();
    for ( $j = 1; $j <= 5; $j++ ) {
      foreach ( array( 'matin', 'apm' ) as $moment ) {
        $pfx   = 'prog_j' . $j . '_' . $moment . '_';
        $titre = isset( $_POST[ $pfx . 'titre' ] )       ? sanitize_text_field( wp_unslash( $_POST[ $pfx . 'titre' ] ) )           : '';
        $cont  = isset( $_POST[ $pfx . 'contenus' ] )    ? sanitize_textarea_field( wp_unslash( $_POST[ $pfx . 'contenus' ] ) )    : '';
        $comp  = isset( $_POST[ $pfx . 'competences' ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $pfx . 'competences' ] ) ) : '';
        $opo   = isset( $_POST[ $pfx . 'opo' ] )         ? sanitize_textarea_field( wp_unslash( $_POST[ $pfx . 'opo' ] ) )         : '';
        if ( '' !== $titre || '' !== $cont || '' !== $comp || '' !== $opo ) {
          $blocs_prog[] = array( 'jour' => $j, 'moment' => $moment, 'titre' => $titre, 'contenus' => $cont, 'competences' => $comp, 'opo' => $opo );
        }
      }
    }
    $data['programme_detail'] = ! empty( $blocs_prog ) ? wp_json_encode( $blocs_prog, JSON_UNESCAPED_UNICODE ) : '';

    /* ACDC 3.25.278 — LES DIX INTERRUPTEURS QUALIOPI, DEPUIS LA LISTE COMMUNE.
       Ils étaient énumérés à la main ici, et « Enquête financeur » avait été
       ajoutée à part, sous condition, parce qu'elle est arrivée après les
       autres. Résultat : une liste à l'écran, une autre à l'enregistrement.
       Un interrupteur ajouté d'un côté et oublié de l'autre ne s'enregistre
       jamais — et l'écran le rouvre éteint, sans rien signaler.

       La vérification d'existence de colonne est conservée : sur un premier
       déploiement dont le cache n'a pas été vidé, une colonne absente ferait
       échouer TOUT l'enregistrement de la formation, pas seulement ce champ. */
    $colonnes_formation = array();
    foreach ( (array) $wpdb->get_col( "SHOW COLUMNS FROM {$this->formation_table}" ) as $col ) {
      $colonnes_formation[ (string) $col ] = true;
    }
    foreach ( array_keys( $this->acdc_qualiopi_toggles() ) as $interrupteur ) {
      if ( isset( $colonnes_formation[ $interrupteur ] ) ) {
        $data[ $interrupteur ] = ! empty( $_POST[ $interrupteur ] ) ? 1 : 0;
      }
    }
    // ACDC 3.24.8 — R2 : codes RNCP / RS.
    if ( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s', $this->formation_table, 'rncp_code' ) ) ) {
      $data['rncp_code'] = isset( $_POST['rncp_code'] ) ? sanitize_text_field( wp_unslash( $_POST['rncp_code'] ) ) : '';
    }
    if ( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s', $this->formation_table, 'rs_code' ) ) ) {
      $data['rs_code'] = isset( $_POST['rs_code'] ) ? sanitize_text_field( wp_unslash( $_POST['rs_code'] ) ) : '';
    }

    if ( $formation_id ) {
      $result = $wpdb->update( $this->formation_table, $data, array( 'id' => $formation_id ) );
      $message = 'Formation mise à jour.';
    } else {
      $data['created_at'] = $this->now_mysql();
      $result = $wpdb->insert( $this->formation_table, $data );
      $message = 'Formation enregistrée.';
    }

    $return_action = isset( $_POST['return_action'] ) && 'archived' === sanitize_key( wp_unslash( $_POST['return_action'] ) ) ? 'archived' : 'list';
    if ( false === $result ) {
      $this->redirect_to_portal( 'formations', $this->get_safe_db_error_message( 'Impossible d’enregistrer la formation pour le moment.' ), 'error', array( 'action' => $formation_id ? 'edit' : 'new', 'item_id' => $formation_id ) );
    }

    if ( ! $formation_id ) {
      $formation_id = (int) $wpdb->insert_id;
    }

    $return_after_save = $this->acdc_get_post_return_after_save( $formation_id ? 'edit' : $return_action, array( 'view', 'edit', 'list', 'archived', 'new' ) );
    $success_args = array();
    if ( in_array( $return_after_save, array( 'list', 'archived', 'new' ), true ) ) {
      $success_args['action'] = $return_after_save;
    } else {
      $success_args['action'] = $return_after_save;
      $success_args['item_id'] = $formation_id;
    }

    if ( empty( $success_args['action'] ) ) {
      $success_args['action'] = $return_action;
    }

    // ACDC 3.21.58 — Push des indicateurs vers le Manager si lien présent
    $this->push_indicators_to_manager( $formation_id );

    $this->redirect_to_portal( 'formations', $message, 'success', $success_args );
  }  public function handle_delete_formation() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      $this->redirect_to_login( 'Accès réservé aux administrateurs.', 'error' );
    }

    $formation_id = isset( $_GET['formation_id'] ) ? absint( wp_unslash( $_GET['formation_id'] ) ) : 0;
    if ( ! $formation_id ) {
      $this->redirect_to_portal( 'formations', 'Formation introuvable.', 'error' );
    }

    check_admin_referer( 'acdc_delete_formation_' . $formation_id );

    global $wpdb;
    $session_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->session_table} WHERE formation_id = %d", $formation_id ) );
    if ( $session_count ) {
      $this->redirect_to_portal( 'formations', 'Suppression impossible : cette formation est liée à des sessions.', 'error' );
    }

    $wpdb->delete( $this->formation_table, array( 'id' => $formation_id ) );
    $this->redirect_to_portal( 'formations', 'Formation supprimée.', 'success' );
  }  public function handle_save_trainer() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    check_admin_referer( 'acdc_save_trainer' );

    global $wpdb;
    $trainer_id = isset( $_POST['trainer_id'] ) ? absint( wp_unslash( $_POST['trainer_id'] ) ) : 0;
    $input = isset( $_POST['trainer'] ) && is_array( $_POST['trainer'] ) ? wp_unslash( $_POST['trainer'] ) : array();
    $first_name = isset( $input['first_name'] ) ? sanitize_text_field( $input['first_name'] ) : '';
    $last_name = isset( $input['last_name'] ) ? sanitize_text_field( $input['last_name'] ) : '';
    $email = isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '';
    if ( '' === $first_name || '' === $last_name || '' === $email ) {
      $required_fields = array();
      if ( '' === $first_name ) { $required_fields[] = 'first_name'; }
      if ( '' === $last_name ) { $required_fields[] = 'last_name'; }
      if ( '' === $email ) { $required_fields[] = 'email'; }
      $this->acdc_store_form_state( 'trainer', $input, $required_fields );
      $target = is_admin() && isset( $_POST['page'] ) && 'acdc-of-trainers' === $_POST['page'] ? admin_url( 'admin.php?page=acdc-of-trainers&action=' . ( $trainer_id ? 'edit&item_id=' . $trainer_id : 'new' ) . '&notice=' . rawurlencode( 'Prénom, nom et e-mail sont obligatoires.' ) . '&notice_type=error' ) : $this->portal_page_url( array( 'tab' => 'trainers', 'action' => $trainer_id ? 'edit' : 'new', 'item_id' => $trainer_id, 'notice' => rawurlencode( 'Prénom, nom et e-mail sont obligatoires.' ), 'notice_type' => 'error' ) );
      wp_safe_redirect( $target );
      exit;
    }
    $photo_url = $this->handle_optional_upload( 'trainer_photo' );
    if ( empty( $photo_url ) && isset( $input['photo_url'] ) ) {
      $photo_url = esc_url_raw( $input['photo_url'] );
    } elseif ( empty( $photo_url ) && $trainer_id ) {
      $existing = $this->get_trainer( $trainer_id );
      $photo_url = $existing ? $existing->photo_url : '';
    }
    /* ACDC 3.20.90 — availability_json n'est plus écrit côté admin.
       Seul le formateur peut modifier ses disponibilités via son portail
       (handle_trainer_update_weekly_schedule + handle_trainer_toggle_availability).
       Préservation des données existantes garantie : on ne passe pas la clé,
       donc $wpdb->update ne touche pas à la colonne. */
    $data = array(
      'is_self_trainer' => ( isset( $input['trainer_type'] ) && 'Interne' === $input['trainer_type'] ) ? 1 : 0,
      'photo_url' => $photo_url,
      'gender' => isset( $input['gender'] ) ? sanitize_text_field( $input['gender'] ) : '',
      'first_name' => $first_name,
      'last_name' => $last_name,
      'email' => $email,
      'phone' => isset( $input['phone'] ) ? sanitize_text_field( $input['phone'] ) : '',
      'birth_date' => isset( $input['birth_date'] ) ? sanitize_text_field( $input['birth_date'] ) : null,
      'nda_number' => isset( $input['nda_number'] ) ? sanitize_text_field( $input['nda_number'] ) : '',
      'role_name' => isset( $input['role_name'] ) ? sanitize_text_field( $input['role_name'] ) : 'Formateur simple',
      'trainer_type' => isset( $input['trainer_type'] ) ? sanitize_text_field( $input['trainer_type'] ) : '',
      'description_text' => isset( $input['description_text'] ) ? sanitize_textarea_field( $input['description_text'] ) : '',
      'session_reminder_enabled' => ! empty( $input['session_reminder_enabled'] ) ? 1 : 0,
      'session_start_enabled' => ! empty( $input['session_start_enabled'] ) ? 1 : 0,
      'contact_alert_at' => isset( $input['contact_alert_at'] ) ? $this->datetime_from_local( $input['contact_alert_at'] ) : null,
      'comment_text' => isset( $input['comment_text'] ) ? sanitize_textarea_field( $input['comment_text'] ) : '',
      'learner_info_photo' => ! empty( $input['learner_info_photo'] ) ? 1 : 0,
      'learner_info_name' => ! empty( $input['learner_info_name'] ) ? 1 : 0,
      'learner_info_description' => ! empty( $input['learner_info_description'] ) ? 1 : 0,
      'learner_info_availability' => ! empty( $input['learner_info_availability'] ) ? 1 : 0,
      'siret' => isset( $input['siret'] ) ? sanitize_text_field( $input['siret'] ) : '',
      'access_enabled' => 1,
      'updated_at' => $this->now_mysql(),
    );
    /* ACDC 3.20.91 — Permissions du portail formateur.
       permission_profile : whitelist stricte simple|autonome|custom (fallback simple).
       permissions_json   : sérialisé uniquement si profil = custom, vidé sinon
                            pour repartir des defaults. Le set canonique vient de
                            get_acdc_trainer_permissions_definition() — toute clé hors set
                            est silencieusement ignorée (allowlist serveur). */
    $perm_profile_in = isset( $input['permission_profile'] ) ? sanitize_key( $input['permission_profile'] ) : 'simple';
    if ( ! in_array( $perm_profile_in, array( 'simple', 'autonome', 'custom' ), true ) ) {
      $perm_profile_in = 'simple';
    }
    $data['permission_profile'] = $perm_profile_in;
    if ( 'custom' === $perm_profile_in ) {
      $perms_in = ( isset( $input['permissions'] ) && is_array( $input['permissions'] ) ) ? $input['permissions'] : array();
      $perm_definitions = $this->get_acdc_trainer_permissions_definition();
      $clean_perms = array();
      foreach ( $perm_definitions as $perm_key => $perm_row ) {
        $clean_perms[ $perm_key ] = ! empty( $perms_in[ $perm_key ] );
      }
      $data['permissions_json'] = wp_json_encode( $clean_perms );
    } else {
      $data['permissions_json'] = '';
    }
    if ( $trainer_id ) {
      $result = $wpdb->update( $this->trainer_table, $data, array( 'id' => $trainer_id ) );
      $message = 'Formateur mis à jour.';
    } else {
      $data['created_at'] = $this->now_mysql();
      $result = $wpdb->insert( $this->trainer_table, $data );
      $trainer_id = (int) $wpdb->insert_id;
      $message = 'Formateur enregistré.';
    }
    /* ACDC 3.25.164 — La synchronisation du registre des sous-traitants ne se
       faisait QU'À LA CRÉATION : passer un formateur existant en « Externe » ne
       l'y inscrivait pas, et le repasser en « Interne » ne l'en retirait pas. Elle
       s'applique désormais dans les deux sens, à chaque enregistrement. */
    if ( false !== $result && $trainer_id ) {
      $this->acdc_sync_subcontractor_for_trainer( (int) $trainer_id, (int) $data['is_self_trainer'], $first_name, $last_name, isset( $data['siret'] ) ? (string) $data['siret'] : '' );
    }
    if ( false === $result ) {
      $this->acdc_store_form_state( 'trainer', $input );
      $safe_message = $this->get_safe_db_error_message( 'Une erreur technique est survenue.' );
      $target = is_admin() && isset( $_POST['page'] ) && 'acdc-of-trainers' === $_POST['page'] ? admin_url( 'admin.php?page=acdc-of-trainers&action=' . ( $trainer_id ? 'edit&item_id=' . $trainer_id : 'new' ) . '&notice=' . rawurlencode( $safe_message ) . '&notice_type=error' ) : $this->portal_page_url( array( 'tab' => 'trainers', 'action' => $trainer_id ? 'edit' : 'new', 'item_id' => $trainer_id, 'notice' => rawurlencode( $safe_message ), 'notice_type' => 'error' ) );
      wp_safe_redirect( $target );
      exit;
    }
    $target = is_admin() && isset( $_POST['page'] ) && 'acdc-of-trainers' === $_POST['page'] ? admin_url( 'admin.php?page=acdc-of-trainers&notice=' . rawurlencode( $message ) . '&notice_type=success' ) : $this->portal_page_url( array( 'tab' => 'trainers', 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ) );
    if ( ! empty( $_POST['save_and_add'] ) ) {
      $target = is_admin() && isset( $_POST['page'] ) && 'acdc-of-trainers' === $_POST['page'] ? admin_url( 'admin.php?page=acdc-of-trainers&action=new&notice=' . rawurlencode( $message ) . '&notice_type=success' ) : $this->portal_page_url( array( 'tab' => 'trainers', 'action' => 'new', 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ) );
    }
    wp_safe_redirect( $target );
    exit;
  }  public function handle_delete_trainer() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $trainer_id = isset( $_GET['trainer_id'] ) ? absint( wp_unslash( $_GET['trainer_id'] ) ) : 0;
    if ( ! $trainer_id ) {
      $this->redirect_to_portal( 'trainers', 'Formateur introuvable.', 'error' );
    }
    check_admin_referer( 'acdc_delete_trainer_' . $trainer_id );
    global $wpdb;

    /* ACDC 3.25.155 — Un contrat de sous-traitance SIGNÉ est une pièce contractuelle
       et comptable (conservation 10 ans, art. L123-22 du Code de commerce) dotée
       d'une valeur probante. Supprimer le formateur détruisait jusqu'ici ces PDF
       définitivement, en un clic et sans avertissement. On bloque donc la
       suppression tant qu'une mission signée subsiste : à l'organisme de l'archiver
       puis de la retirer sciemment. La minimisation RGPD ne prime pas sur une
       obligation légale de conservation. */
    /* ACDC 3.25.157 — La garde ne compte QUE les contrats signés non archivés.
       La version 3.25.155 comptait tous les contrats signés et créait une impasse :
       ce message renvoyait vers la suppression de la mission, que l'autre garde
       interdisait — aucun chemin ne permettait plus de retirer un formateur. Le
       déblocage passe désormais par une action explicite « Archiver » (le PDF est
       téléchargé, puis horodaté en base), et non par la levée de la protection. */
    $signed_count = (int) $wpdb->get_var( $wpdb->prepare(
      "SELECT COUNT(*) FROM {$this->trainer_contract_table} WHERE trainer_id = %d AND signature_status = %s AND archived_at IS NULL",
      $trainer_id,
      'signée'
    ) );
    if ( $signed_count > 0 ) {
      $this->redirect_to_portal(
        'trainers',
        sprintf(
          'Suppression impossible : ce formateur a %d contrat%s signé%s non archivé%s, à conserver comme pièce comptable. Ouvrez sa fiche, et pour chaque mission signée : ouvrez le contrat avec « ⤓ », enregistrez-le sur votre poste, puis cliquez sur « confirmer l’archivage ». Relancez ensuite la suppression.',
          $signed_count,
          $signed_count > 1 ? 's' : '',
          $signed_count > 1 ? 's' : '',
          $signed_count > 1 ? 's' : ''
        ),
        'error'
      );
      return;
    }

    /* ACDC 3.25.161 — Retirer l'entrée du registre des sous-traitants créée
       AUTOMATIQUEMENT depuis cette fiche. La garde posée plus haut interdit déjà de
       supprimer un formateur ayant un contrat signé : un formateur supprimable n'a
       donc aucune sous-traitance à attester, et son entrée n'est que du bruit dans
       un registre auditable. On ne touche JAMAIS à une entrée saisie à la main —
       seules celles portant le lien au formateur, ou à défaut son nom exact ET la
       mention d'ajout automatique, sont retirées. */
    $sc_registry = get_option( 'acdc_of_subcontractors', array() );
    if ( is_array( $sc_registry ) && ! empty( $sc_registry ) ) {
      $trainer_row  = $this->get_trainer( $trainer_id );
      $trainer_name = $trainer_row ? strtolower( trim( (string) $trainer_row->first_name . ' ' . (string) $trainer_row->last_name ) ) : '';
      $sc_kept      = array();
      foreach ( $sc_registry as $sc_entry ) {
        $auto_note = isset( $sc_entry['notes'] ) && false !== stripos( (string) $sc_entry['notes'], 'automatiquement depuis la fiche formateur' );
        $by_id     = isset( $sc_entry['trainer_id'] ) && (int) $sc_entry['trainer_id'] === (int) $trainer_id;
        $by_name   = '' !== $trainer_name && isset( $sc_entry['nom'] ) && strtolower( trim( (string) $sc_entry['nom'] ) ) === $trainer_name && $auto_note;
        if ( $by_id || $by_name ) {
          continue;
        }
        $sc_kept[] = $sc_entry;
      }
      if ( count( $sc_kept ) !== count( $sc_registry ) ) {
        update_option( 'acdc_of_subcontractors', array_values( $sc_kept ), false );
      }
    }

    /* ACDC 3.25.148 — F11 : purger les PDF de TOUTES les missions du formateur
       avant de le supprimer (sinon les contrats restent sur le disque). */
    $contract_ids = (array) $wpdb->get_col( $wpdb->prepare(
      "SELECT id FROM {$this->trainer_contract_table} WHERE trainer_id = %d",
      $trainer_id
    ) );
    foreach ( $contract_ids as $cid ) {
      $this->acdc_purge_trainer_contract_files( (int) $cid );
    }

    /* ACDC 3.25.153 — V3.8 : la suppression ne retirait QUE la ligne du formateur.
       Le compte du portail formateur survivait, avec ses jetons et ses sessions.
       Conséquence démontrée en recette : réutiliser la même adresse pour un nouveau
       formateur déclenchait « Cet e-mail est déjà associé à un autre formateur », le
       contrôle d'unicité trouvant un compte orphelin invisible depuis l'interface.
       On purge donc l'ensemble de la chaîne : sessions → jetons → compte. */
    $account_ids = (array) $wpdb->get_col( $wpdb->prepare(
      "SELECT id FROM {$this->trainer_portal_account_table} WHERE trainer_id = %d",
      $trainer_id
    ) );
    foreach ( $account_ids as $aid ) {
      $aid = (int) $aid;
      if ( ! $aid ) { continue; }
      $wpdb->delete( $this->trainer_portal_session_table, array( 'account_id' => $aid ), array( '%d' ) );
      $wpdb->delete( $this->trainer_portal_token_table,   array( 'account_id' => $aid ), array( '%d' ) );
    }
    $wpdb->delete( $this->trainer_portal_account_table, array( 'trainer_id' => $trainer_id ), array( '%d' ) );

    $wpdb->delete( $this->trainer_table, array( 'id' => $trainer_id ) );
    $this->redirect_to_portal( 'trainers', 'Formateur supprimé.', 'success' );
  }

  /* ── ACDC 3.22.1 — Contrats / missions formateurs ──────────────────────── */

  /**
   * Sauvegarde (ajout ou modification) d'un contrat/mission formateur.
   * Accessible via admin-post.php action=acdc_save_trainer_contract.
   * Calcul automatique montant_ht = nb_heures × taux_ht (la valeur saisie prime si non nul).
   */
  public function handle_save_trainer_contract() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $trainer_id  = isset( $_POST['trainer_id'] ) ? absint( wp_unslash( $_POST['trainer_id'] ) ) : 0;
    $contract_id = isset( $_POST['contract_id'] ) ? absint( wp_unslash( $_POST['contract_id'] ) ) : 0;
    if ( ! $trainer_id ) {
      $this->redirect_to_portal( 'trainers', 'Formateur introuvable.', 'error' );
      return;
    }
    check_admin_referer( 'acdc_save_trainer_contract_' . $trainer_id );
    /* ACDC 3.25.262 — LE CALCUL VA DANS LES DEUX SENS, Y COMPRIS ICI.
       L'écran déduisait le montant des heures et du taux, jamais l'inverse ; le
       serveur, lui, gardait le montant saisi mais laissait le taux à zéro. Une
       mission négociée au forfait — « 800 € pour deux jours », le cas courant —
       s'enregistrait donc avec « 0,00 €/H », et c'est ce que le contrat de
       sous-traitance imprimait.
       La règle est la même des deux côtés, et elle vit dans une seule classe :
       le montant saisi fait foi, le taux s'en déduit. Le serveur l'applique
       quoi qu'il arrive — un formulaire sans JavaScript ne doit pas produire
       une pièce contractuelle fausse. */
    $__mission = \ACDC\Support\MissionAmount::reconcile(
      isset( $_POST['nb_heures'] )  ? wp_unslash( $_POST['nb_heures'] )  : 0,
      isset( $_POST['taux_ht'] )    ? wp_unslash( $_POST['taux_ht'] )    : 0,
      isset( $_POST['montant_ht'] ) ? wp_unslash( $_POST['montant_ht'] ) : 0
    );
    $nb_heures  = $__mission['hours'];
    $taux_ht    = $__mission['rate'];
    $montant_ht = $__mission['amount'];
    $date_start = isset( $_POST['date_start'] ) && '' !== sanitize_text_field( wp_unslash( $_POST['date_start'] ) ) ? sanitize_text_field( wp_unslash( $_POST['date_start'] ) ) : null;
    $date_end   = isset( $_POST['date_end'] )   && '' !== sanitize_text_field( wp_unslash( $_POST['date_end'] ) )   ? sanitize_text_field( wp_unslash( $_POST['date_end'] ) )   : null;
    global $wpdb;

    /* ACDC 3.25.227 — L'identifiant fait foi ; le libellé s'en déduit.
       Le formulaire transmettait auparavant le TITRE de la formation, et lui
       seul : deux variantes portant le même intitulé étaient indiscernables, et
       renommer une formation aurait rendu muets tous les contrats déjà signés.
       On garde malgré tout le libellé en base, parce que c'est lui qui
       s'imprime sur le contrat — un document contractuel doit rester lisible
       même si la formation disparaît du catalogue. */
    $formation_id  = isset( $_POST['formation_id'] ) ? absint( wp_unslash( $_POST['formation_id'] ) ) : 0;
    $formation_ref = isset( $_POST['formation_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['formation_ref'] ) ) : '';
    if ( $formation_id > 0 ) {
      $formation_row = $this->get_formation( $formation_id );
      if ( $formation_row && ! empty( $formation_row->title ) ) {
        $formation_ref = $this->acdc_formation_labelled( $formation_id, (string) $formation_row->title );
      }
    }

    $data = array(
      'trainer_id'    => $trainer_id,
      'label'         => isset( $_POST['label'] )         ? sanitize_text_field( wp_unslash( $_POST['label'] ) )         : '',
      'formation_id'  => $formation_id > 0 ? $formation_id : null,
      'formation_ref' => $formation_ref,
      'date_start'    => $date_start,
      'date_end'      => $date_end,
      'nb_heures'     => $nb_heures,
      'taux_ht'       => $taux_ht,
      'montant_ht'    => $montant_ht,
      'statut'        => isset( $_POST['statut'] ) ? sanitize_text_field( wp_unslash( $_POST['statut'] ) ) : 'En attente',
      'notes'         => isset( $_POST['notes'] )  ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
    );
    $formats = array( '%d', '%s', '%d', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s' );
    if ( $contract_id ) {
      $wpdb->update( $this->trainer_contract_table, $data, array( 'id' => $contract_id ), $formats, array( '%d' ) );
      $msg = 'Mission mise à jour.';
    } else {
      $data['created_at'] = current_time( 'mysql' );
      $formats[] = '%s';
      $wpdb->insert( $this->trainer_contract_table, $data, $formats );
      $msg = 'Mission ajoutée.';
    }
    /* ACDC 3.25.262 — ENREGISTRER ET ENVOYER, D'UN SEUL GESTE.
       Il fallait six clics et deux rechargements pour contractualiser une
       mission : ouvrir un formulaire déjà présent, enregistrer, rouvrir une
       modale pour redésigner la mission qu'on venait de créer, la fabriquer en
       PDF, rouvrir une seconde modale, la redésigner encore, envoyer. Chaque
       bouton global rouvrait une liste pour faire redésigner ce qu'on avait
       sous les yeux.
       Le bouton principal enchaîne désormais les trois étapes. En cas d'échec
       de l'envoi, la mission RESTE enregistrée et le message le dit : perdre
       une saisie parce qu'un e-mail n'est pas parti serait le pire des deux. */
    $notice_type = 'success';
    if ( ! empty( $_POST['and_sign'] ) ) {
      $saved_id = $contract_id ? $contract_id : (int) $wpdb->insert_id;
      $trainer  = $this->get_trainer( $trainer_id );
      $contract = $saved_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->trainer_contract_table} WHERE id = %d", $saved_id ) ) : null;
      if ( $trainer && $contract ) {
        $verdict     = $this->acdc_request_trainer_contract_signature( $trainer, $contract );
        $msg         = $msg . ' ' . $verdict['message'];
        $notice_type = $verdict['ok'] ? 'success' : 'warning';
      }
    }

    /* Le retour se fait SUR LA SECTION DES MISSIONS, pas en haut de la fiche :
       une page qui remonte à chaque enregistrement fait perdre le fil. */
    $is_admin_ctx = isset( $_POST['page'] ) && 'acdc-of-trainers' === $_POST['page'];
    $redirect = $is_admin_ctx
      ? admin_url( 'admin.php?page=acdc-of-trainers&action=edit&item_id=' . $trainer_id . '&notice=' . rawurlencode( $msg ) . '&notice_type=' . $notice_type )
      : $this->portal_page_url( array( 'tab' => 'trainers', 'action' => 'edit', 'item_id' => $trainer_id, 'notice' => rawurlencode( $msg ), 'notice_type' => $notice_type ) );
    wp_safe_redirect( $redirect . '#acdc-tc-section' );
    exit;
  }

  /**
   * Suppression d'un contrat/mission formateur.
   * Accessible via admin-post.php action=acdc_delete_trainer_contract (GET + nonce).
   */
  public function handle_delete_trainer_contract() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $contract_id = isset( $_GET['contract_id'] ) ? absint( wp_unslash( $_GET['contract_id'] ) ) : 0;
    $trainer_id  = isset( $_GET['trainer_id'] )  ? absint( wp_unslash( $_GET['trainer_id'] ) )  : 0;
    if ( ! $contract_id || ! $trainer_id ) {
      $this->redirect_to_portal( 'trainers', 'Mission introuvable.', 'error' );
      return;
    }
    check_admin_referer( 'acdc_delete_trainer_contract_' . $contract_id );
    global $wpdb;

    /* ACDC 3.25.155 — Même protection que sur la suppression du formateur : une
       mission SIGNÉE ne se supprime pas d'un clic, son PDF est une pièce probante.
       Sans ce garde-fou, celui posé sur le formateur se contournerait simplement
       en supprimant d'abord ses missions. La suppression reste possible via une
       confirmation explicite (paramètre force=1) pour les cas légitimes. */
    $signed_row = $wpdb->get_row( $wpdb->prepare(
      "SELECT signature_status, archived_at FROM {$this->trainer_contract_table} WHERE id = %d AND trainer_id = %d",
      $contract_id,
      $trainer_id
    ) );
    $is_signed_mission = $signed_row ? (string) $signed_row->signature_status : '';
    $is_archived       = $signed_row && ! empty( $signed_row->archived_at );
    $forced = isset( $_GET['force'] ) && '1' === (string) $_GET['force'];
    /* ACDC 3.25.157 — Une fois le PDF archivé (sorti de l'application), la
       suppression redevient possible : la pièce comptable existe ailleurs. */
    if ( 'signée' === $is_signed_mission && ! $is_archived && ! $forced ) {
      $msg_signed  = 'Cette mission est signée : son contrat est une pièce comptable à conserver. Ouvrez d’abord le contrat avec « ⤓ » dans la colonne Actions, enregistrez-le sur votre poste, puis cliquez sur « confirmer l’archivage ». La suppression sera alors autorisée.';
      $back_signed = isset( $_GET['ctx'] ) && 'admin' === $_GET['ctx']
        ? admin_url( 'admin.php?page=acdc-of-trainers&action=edit&item_id=' . $trainer_id . '&notice=' . rawurlencode( $msg_signed ) . '&notice_type=error' )
        : $this->portal_page_url( array( 'tab' => 'trainers', 'action' => 'edit', 'item_id' => $trainer_id, 'notice' => rawurlencode( $msg_signed ), 'notice_type' => 'error' ) );
      wp_safe_redirect( $back_signed );
      exit;
    }

    $wpdb->delete( $this->trainer_contract_table, array( 'id' => $contract_id, 'trainer_id' => $trainer_id ) );
    /* ACDC 3.25.148 — F11 : les PDF ne doivent pas survivre à la mission supprimée. */
    $this->acdc_purge_trainer_contract_files( $contract_id );
    $msg = 'Mission supprimée.';
    $is_admin_ctx = isset( $_GET['ctx'] ) && 'admin' === $_GET['ctx'];
    $redirect = $is_admin_ctx
      ? admin_url( 'admin.php?page=acdc-of-trainers&action=edit&item_id=' . $trainer_id . '&notice=' . rawurlencode( $msg ) . '&notice_type=success' )
      : $this->portal_page_url( array( 'tab' => 'trainers', 'action' => 'edit', 'item_id' => $trainer_id, 'notice' => rawurlencode( $msg ), 'notice_type' => 'success' ) );
    wp_safe_redirect( $redirect );
    exit;
  }

  /**
   * Génération du PDF de contrat de sous-traitance formateur.
   * Accessible via admin-post.php action=acdc_generate_trainer_contract_pdf (POST).
   * Lit les clauses depuis get_subcontract_params_options().
   * Stocke l'URL générée dans acdc_of_trainer_contracts.contract_pdf_url.
   */
  /**
   * ACDC 3.25.262 — FABRIQUER LE PDF DU CONTRAT, UNE SEULE FOIS DANS LE CODE.
   *
   * Trois chemins en avaient besoin — le bouton « Générer », l'envoi en
   * signature, et depuis cette version l'enregistrement d'une mission. La
   * fabrication vivait dans le premier ; les deux autres la refaisaient à leur
   * façon. Une pièce contractuelle produite par trois codes différents finit
   * par exister en trois versions.
   *
   * @return string L'URL du PDF écrit, ou '' en cas d'échec.
   */
  private function acdc_store_trainer_contract_pdf( $trainer, $contract ) {
    global $wpdb;
    if ( ! $trainer || ! $contract || empty( $contract->id ) ) {
      return '';
    }
    $pages = $this->build_trainer_contract_pdf_pages( $trainer, $contract );
    if ( empty( $pages ) ) {
      return '';
    }
    $pdf_content = '';
    if ( class_exists( 'ACDC_Sig_Core' ) && class_exists( 'ACDC_Sig_PDF' ) ) {
      $sig_core = new ACDC_Sig_Core();
      $sig_core->init_tables();
      $sig_pdf  = new ACDC_Sig_PDF( $sig_core );
      $ref      = new ReflectionClass( $sig_pdf );
      $method   = $ref->getMethod( 'render_to_string' );
      $method->setAccessible( true );
      $pdf_content = $method->invoke( $sig_pdf, $pages );
    }
    if ( ( ! is_string( $pdf_content ) || '' === $pdf_content ) && method_exists( $this, '_build_simple_pdf_string' ) ) {
      $pdf_content = $this->_build_simple_pdf_string( $pages );
    }
    if ( ! is_string( $pdf_content ) || '' === $pdf_content ) {
      return '';
    }

    $contract_id = (int) $contract->id;
    $upload_dir  = wp_upload_dir();
    $safe_name   = $this->acdc_trainer_contract_filename( (int) $contract->trainer_id, $contract_id );
    $dir_path    = trailingslashit( $upload_dir['basedir'] ) . 'acdc-of-contracts/' . $contract_id . '/';
    $file_path   = $dir_path . $safe_name;
    $file_url    = trailingslashit( $upload_dir['baseurl'] ) . 'acdc-of-contracts/' . $contract_id . '/' . $safe_name;
    /* ACDC 3.25.148 — F11 : dossier créé PROTÉGÉ (.htaccess + index.php). */
    $this->acdc_protect_contracts_dir( $dir_path );
    file_put_contents( $file_path, $pdf_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    $wpdb->update( $this->trainer_contract_table, array( 'contract_pdf_url' => esc_url_raw( $file_url ) ), array( 'id' => $contract_id ), array( '%s' ), array( '%d' ) );
    $contract->contract_pdf_url = $file_url;

    $this->log_action_event( 'generate', 'trainer_contract_pdf', $contract_id, 'success', array( 'file' => $safe_name, 'bytes' => strlen( $pdf_content ) ) );
    return $file_url;
  }

  /**
   * ACDC 3.25.262 — DEMANDER LA SIGNATURE DU CONTRAT AU FORMATEUR.
   *
   * Même raison que ci-dessus : le bouton dédié et l'enregistrement d'une
   * mission mènent tous deux ici. Elle rend un verdict au lieu de rediriger,
   * pour que l'appelant compose son message — une fonction qui redirige ne
   * peut pas être appelée au milieu d'un autre traitement.
   *
   * @return array{ok:bool,message:string}
   */
  private function acdc_request_trainer_contract_signature( $trainer, $contract ) {
    global $wpdb;
    if ( ! $trainer || ! $contract ) {
      return array( 'ok' => false, 'message' => 'Contrat ou formateur introuvable.' );
    }
    $tr_email = sanitize_email( (string) $trainer->email );
    if ( '' === $tr_email || ! is_email( $tr_email ) ) {
      return array( 'ok' => false, 'message' => "Le formateur n'a pas d'adresse e-mail valide : le contrat est enregistré, mais rien n'a été envoyé." );
    }
    if ( ! class_exists( 'ACDC_Sig_Core' ) || ! class_exists( 'ACDC_Sig_Email' ) ) {
      return array( 'ok' => false, 'message' => "Le module de signature électronique n'est pas disponible : le contrat est enregistré, mais rien n'a été envoyé." );
    }

    /* Le PDF doit exister sur le disque : c'est lui qu'on fait signer. */
    $upload_dir = wp_upload_dir();
    $doc_url    = esc_url_raw( (string) $contract->contract_pdf_url );
    $doc_path   = '' !== $doc_url ? str_replace( trailingslashit( $upload_dir['baseurl'] ), trailingslashit( $upload_dir['basedir'] ), $doc_url ) : '';
    if ( '' === $doc_path || ! file_exists( $doc_path ) ) {
      $doc_url = $this->acdc_store_trainer_contract_pdf( $trainer, $contract );
      if ( '' === $doc_url ) {
        return array( 'ok' => false, 'message' => "Le PDF du contrat n'a pas pu être fabriqué : rien n'a été envoyé." );
      }
      $doc_path = str_replace( trailingslashit( $upload_dir['baseurl'] ), trailingslashit( $upload_dir['basedir'] ), $doc_url );
    }

    $sig_core = new ACDC_Sig_Core();
    $sig_core->init_tables();
    $tr_name    = trim( (string) $trainer->first_name . ' ' . (string) $trainer->last_name );
    $request_id = $sig_core->create_signature_request( array(
      'signer_name'  => $tr_name,
      'signer_email' => $tr_email,
      'signer_role'  => 'Formateur — contrat de sous-traitance',
      'doc_type'     => 'contrat_formateur',
      'sig_level'    => ACDC_Sig_Core::LEVEL_RENFORCE,
      'doc_url'      => $doc_url,
      'doc_path'     => $doc_path,
      'notes'        => wp_json_encode( array(
        'entity_type'           => 'trainer_contract',
        'trainer_contract_id'   => (int) $contract->id,
        'trainer_id'            => (int) $contract->trainer_id,
        'signed_delivery_email' => sanitize_email( (string) get_option( 'admin_email' ) ),
      ) ),
    ) );
    if ( ! $request_id ) {
      return array( 'ok' => false, 'message' => "La demande de signature n'a pas pu être créée." );
    }

    $sig_email = new ACDC_Sig_Email( $sig_core );
    $sig_email->send_signature_email( $request_id );
    $wpdb->update(
      $this->trainer_contract_table,
      array( 'signature_request_id' => (int) $request_id, 'signature_status' => 'envoyée' ),
      array( 'id' => (int) $contract->id ),
      array( '%d', '%s' ),
      array( '%d' )
    );
    return array( 'ok' => true, 'message' => 'Demande de signature envoyée à ' . $tr_email . '.' );
  }

  public function handle_generate_trainer_contract_pdf() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $trainer_id  = isset( $_POST['trainer_id'] )  ? absint( wp_unslash( $_POST['trainer_id'] ) )  : 0;
    $contract_id = isset( $_POST['contract_id'] ) ? absint( wp_unslash( $_POST['contract_id'] ) ) : 0;
    if ( ! $trainer_id || ! $contract_id ) {
      wp_die( esc_html( 'Données manquantes.' ) );
    }
    check_admin_referer( 'acdc_generate_trainer_contract_pdf_' . $trainer_id );
    global $wpdb;
    $trainer  = $this->get_trainer( $trainer_id );
    $contract = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->trainer_contract_table} WHERE id = %d AND trainer_id = %d", $contract_id, $trainer_id ) );
    if ( ! $trainer || ! $contract ) {
      wp_die( esc_html( 'Données introuvables.' ) );
    }
    /* ACDC 3.25.262 — La fabrication vit dans acdc_store_trainer_contract_pdf :
       trois chemins en ont besoin, un seul code la produit. */
    $file_url = $this->acdc_store_trainer_contract_pdf( $trainer, $contract );
    if ( '' === $file_url ) {
      wp_die( esc_html( 'Génération PDF échouée.' ) );
    }

    /* ACDC 3.25.148 — G4 : ce handler NE FAIT PLUS QUE TÉLÉCHARGER.
       Il envoyait auparavant le contrat par e-mail au formateur à chaque appel :
       un bouton « Générer le PDF » expédiait donc un document contractuel à un
       tiers, sans confirmation ni trace — et comme la réponse HTTP se perdait,
       le gestionnaire recliquait et l'envoi était dupliqué.
       L'envoi est désormais une action distincte et explicite :
       acdc_send_trainer_contract_email (bouton dédié + confirmation). */
    /* ACDC 3.25.159 — CE HANDLER NE DIFFUSE PLUS LE PDF.
       Il écrivait le fichier puis le renvoyait en pièce jointe : le fichier
       arrivait bien sur le disque, mais la réponse HTTP retombait en 503 — même
       symptôme que l'archivage, et contrairement au service de consultation qui
       répond 200 sur le même fichier. Conséquences observées en recette : le
       gestionnaire croit la génération échouée, reclique, et surtout la chaîne
       s'arrête là puisque « Envoyer pour signature » exige un PDF existant.
       Le handler enregistre désormais et redirige avec un avis ; la consultation
       et le téléchargement passent par acdc_serve_trainer_contract, seul chemin
       dont on sait qu'il aboutit. */
    wp_safe_redirect( add_query_arg(
      array(
        'notice'      => rawurlencode( 'Contrat PDF généré. Utilisez « Voir » pour le consulter ou l’enregistrer.' ),
        'notice_type' => 'success',
      ),
      $this->acdc_trainer_contract_back_url( $trainer_id )
    ) . '#acdc-tc-section' );
    exit;
  }

  /* ====================================================================
   * ACDC 3.25.155 — Contrats formateurs ORPHELINS (nettoyage manuel).
   *
   * Décision assumée : AUCUNE suppression automatique de document. Un contrat
   * signé est une pièce probante et comptable ; le supprimer au démarrage du
   * plugin serait irréversible et pris à la place de l'organisme.
   * On fournit donc un écran qui INVENTORIE et laisse l'humain décider :
   *   - contrats NON signés orphelins : suppression proposée (aucune valeur
   *     juridique une fois la mission disparue — simple minimisation RGPD) ;
   *   - contrats SIGNÉS orphelins : signalés « à conserver », suppression
   *     possible seulement via une confirmation supplémentaire.
   * ==================================================================== */

  /** Inventaire des fichiers de contrats sans mission correspondante en base. */
  private function acdc_scan_orphan_contract_files() {
    global $wpdb;
    $uploads = wp_upload_dir();
    $root    = trailingslashit( $uploads['basedir'] ) . 'acdc-of-contracts/';
    $out     = array();
    if ( ! is_dir( $root ) ) {
      return $out;
    }

    /* Fichiers à la racine (nommage « plat » historique) + un niveau de sous-dossiers. */
    $candidates = (array) glob( $root . '*.pdf' );
    foreach ( (array) glob( $root . '*', GLOB_ONLYDIR ) as $sub ) {
      $candidates = array_merge( $candidates, (array) glob( trailingslashit( $sub ) . '*.pdf' ) );
    }

    foreach ( $candidates as $file ) {
      if ( ! is_file( $file ) ) {
        continue;
      }
      /* contrat-formateur-<trainer_id>-<contract_id>[-signe][-<jeton>].pdf */
      if ( ! preg_match( '/contrat-formateur-(\d+)-(\d+)/', basename( $file ), $m ) ) {
        continue;
      }
      $contract_id = (int) $m[2];
      $exists = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$this->trainer_contract_table} WHERE id = %d",
        $contract_id
      ) );
      if ( $exists > 0 ) {
        continue; // Mission bien présente : ce n'est pas un orphelin.
      }
      $out[] = array(
        'path'        => $file,
        'name'        => basename( $file ),
        'size'        => (int) filesize( $file ),
        'mtime'       => (int) filemtime( $file ),
        'signed'      => ( false !== strpos( basename( $file ), '-signe' ) ),
        'trainer_id'  => (int) $m[1],
        'contract_id' => $contract_id,
      );
    }
    return $out;
  }

  /** Suppression d'UN fichier orphelin, sur action volontaire de l'administrateur. */
  public function handle_delete_orphan_contract_file() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $name = isset( $_GET['file'] ) ? sanitize_file_name( wp_unslash( $_GET['file'] ) ) : '';
    if ( '' === $name ) {
      wp_die( esc_html( 'Fichier introuvable.' ) );
    }
    check_admin_referer( 'acdc_delete_orphan_contract_' . $name );

    /* On ne supprime QUE ce que l'inventaire a reconnu comme orphelin : aucun
       chemin ne provient de la requête. */
    $target = '';
    foreach ( $this->acdc_scan_orphan_contract_files() as $f ) {
      if ( $f['name'] === $name ) {
        $target = $f['path'];
        break;
      }
    }
    /* ACDC 3.25.156 — L'écran vit désormais dans l'extranet (Paramètres →
       Documents orphelins). On revient à l'endroit d'où l'action a été lancée. */
    $from_front = isset( $_GET['ctx'] ) && 'front' === $_GET['ctx'];
    $back = $from_front
      ? $this->portal_page_url( array( 'tab' => 'settings', 'settings_section' => 'orphan_docs' ) )
      : admin_url( 'options-general.php?page=acdc-orphan-docs' );
    if ( '' === $target || ! is_file( $target ) ) {
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Fichier introuvable ou déjà supprimé.' ), 'notice_type' => 'error' ), $back ) );
      exit;
    }

    @unlink( $target ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
    $this->log_action_event( 'delete', 'orphan_contract_file', 0, 'success', array( 'file' => $name ) );
    wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Fichier supprimé.' ), 'notice_type' => 'success' ), $back ) );
    exit;
  }

  /**
   * ACDC 3.25.150 — F11 (complément) : SERVICE AUTHENTIFIÉ des contrats formateurs.
   *
   * Le correctif 3.25.148 a rendu le dossier des contrats inaccessible en direct,
   * mais l'interface continuait de pointer sur l'URL statique : le gestionnaire ne
   * pouvait donc plus reconsulter un contrat déjà généré (403 même connecté).
   * Ce handler sert le fichier après contrôle de capacité et de nonce.
   */
  public function handle_serve_trainer_contract() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $contract_id = isset( $_GET['contract_id'] ) ? absint( wp_unslash( $_GET['contract_id'] ) ) : 0;
    $signed      = isset( $_GET['signed'] ) && '1' === (string) $_GET['signed'];
    if ( ! $contract_id ) {
      wp_die( esc_html( 'Document introuvable.' ) );
    }
    check_admin_referer( 'acdc_serve_trainer_contract_' . $contract_id );

    global $wpdb;
    $contract = $wpdb->get_row( $wpdb->prepare(
      "SELECT id, trainer_id, contract_pdf_url, signed_document_url FROM {$this->trainer_contract_table} WHERE id = %d",
      $contract_id
    ) );
    if ( ! $contract ) {
      wp_die( esc_html( 'Document introuvable.' ) );
    }

    $real_path = $this->acdc_trainer_contract_file_path( $contract, $signed );
    if ( '' === $real_path ) {
      wp_die( esc_html( 'Document introuvable.' ) );
    }

    $this->log_action_event( 'view', 'trainer_contract_pdf', $contract_id );
    while ( ob_get_level() ) { ob_end_clean(); }
    nocache_headers();
    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: inline; filename="' . basename( $real_path ) . '"' );
    header( 'Content-Length: ' . filesize( $real_path ) );
    readfile( $real_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
    exit;
  }

  /**
   * ACDC 3.25.150 — URL de consultation authentifiée d'un contrat formateur.
   * À utiliser partout à la place de l'URL brute dans /uploads (désormais interdite).
   *
   * @param int  $contract_id Identifiant de la mission.
   * @param bool $signed      true pour l'exemplaire signé.
   * @return string URL nonce-ée.
   */
  private function acdc_trainer_contract_view_url( $contract_id, $signed = false ) {
    $args = array( 'action' => 'acdc_serve_trainer_contract', 'contract_id' => (int) $contract_id );
    if ( $signed ) {
      $args['signed'] = 1;
    }
    return wp_nonce_url(
      add_query_arg( $args, admin_url( 'admin-post.php' ) ),
      'acdc_serve_trainer_contract_' . (int) $contract_id
    );
  }


  /**
   * ACDC 3.25.164 — Aligne le registre des sous-traitants sur le type du formateur.
   *
   * Un formateur EXTERNE est un sous-traitant : il doit figurer au registre de
   * l'indicateur 28. Un formateur INTERNE n'en est pas un : son entrée créée
   * automatiquement doit disparaître s'il change de type.
   *
   * On ne touche JAMAIS à une entrée saisie à la main — seules celles portant le
   * lien au formateur, ou à défaut son nom exact ET la mention d'ajout automatique,
   * sont gérées ici.
   *
   * @param int    $trainer_id
   * @param int    $is_self_trainer 1 = interne, 0 = externe.
   * @param string $first_name
   * @param string $last_name
   * @param string $siret
   */
  private function acdc_sync_subcontractor_for_trainer( $trainer_id, $is_self_trainer, $first_name, $last_name, $siret = '' ) {
    $records = get_option( 'acdc_of_subcontractors', array() );
    if ( ! is_array( $records ) ) {
      $records = array();
    }
    $name       = trim( (string) $first_name . ' ' . (string) $last_name );
    $name_key   = strtolower( $name );
    $found_key  = null;

    foreach ( $records as $key => $entry ) {
      $auto_note = isset( $entry['notes'] ) && false !== stripos( (string) $entry['notes'], 'automatiquement depuis la fiche formateur' );
      $by_id     = isset( $entry['trainer_id'] ) && (int) $entry['trainer_id'] === (int) $trainer_id;
      $by_name   = '' !== $name_key && isset( $entry['nom'] ) && strtolower( trim( (string) $entry['nom'] ) ) === $name_key && $auto_note;
      if ( $by_id || $by_name ) {
        $found_key = $key;
        break;
      }
    }

    if ( 0 !== (int) $is_self_trainer ) {
      /* Formateur interne : retirer l'entrée automatique s'il en avait une. */
      if ( null !== $found_key ) {
        unset( $records[ $found_key ] );
        update_option( 'acdc_of_subcontractors', array_values( $records ), false );
      }
      return;
    }

    if ( '' === $name ) {
      return;
    }

    $clean_siret = preg_replace( '/[^0-9]/', '', (string) $siret );
    if ( null !== $found_key ) {
      /* Entrée déjà présente : on rafraîchit le lien et les identifiants, sans
         écraser ce que l'organisme aurait complété à la main (qualifications,
         dates, notes). */
      $records[ $found_key ]['trainer_id'] = (int) $trainer_id;
      $records[ $found_key ]['nom']        = $name;
      if ( '' !== $clean_siret ) {
        $records[ $found_key ]['siret'] = $clean_siret;
      }
      $records[ $found_key ]['updated_at'] = current_time( 'mysql' );
      update_option( 'acdc_of_subcontractors', array_values( $records ), false );
      return;
    }

    /* Anti-doublon sur le nom, y compris pour une entrée saisie à la main : on ne
       crée pas un second sous-traitant portant le même nom. */
    foreach ( $records as $entry ) {
      if ( isset( $entry['nom'] ) && strtolower( trim( (string) $entry['nom'] ) ) === $name_key ) {
        return;
      }
    }

    $records[] = array(
      'id'             => 'sc_' . wp_generate_uuid4(),
      'trainer_id'     => (int) $trainer_id,
      'nom'            => $name,
      'siret'          => $clean_siret,
      'type'           => 'independant',
      'qualifications' => '',
      'date_debut'     => '',
      'date_fin'       => '',
      'notes'          => 'Ajouté automatiquement depuis la fiche formateur.',
      'updated_at'     => current_time( 'mysql' ),
    );
    update_option( 'acdc_of_subcontractors', array_values( $records ), false );
  }

  /**
   * ACDC 3.25.157 — Chemin disque VÉRIFIÉ du PDF d'une mission.
   *
   * Le chemin est reconstruit depuis l'URL stockée en base : on ne fait JAMAIS
   * confiance à un chemin fourni par la requête (traversée de répertoire). Le
   * fichier doit en outre résider dans le dossier de CETTE mission.
   *
   * @param object $contract Ligne de la table des missions (id, contract_pdf_url,
   *                         signed_document_url).
   * @param bool   $signed   true pour l'exemplaire signé.
   * @return string Chemin réel, ou '' si aucun fichier exploitable.
   */
  private function acdc_trainer_contract_file_path( $contract, $signed = false ) {
    if ( ! is_object( $contract ) || empty( $contract->id ) ) {
      return '';
    }
    $url = $signed ? (string) $contract->signed_document_url : (string) $contract->contract_pdf_url;
    if ( '' === $url ) {
      return '';
    }
    $upload_dir   = wp_upload_dir();
    $path         = str_replace( trailingslashit( $upload_dir['baseurl'] ), trailingslashit( $upload_dir['basedir'] ), $url );
    $expected_dir = trailingslashit( $upload_dir['basedir'] ) . 'acdc-of-contracts/' . (int) $contract->id . '/';
    $real_path    = realpath( $path );
    $real_dir     = realpath( $expected_dir );
    if ( ! $real_path || ! $real_dir || 0 !== strpos( $real_path, $real_dir ) || ! is_file( $real_path ) ) {
      return '';
    }
    return $real_path;
  }

  /**
   * ACDC 3.25.157 — ARCHIVAGE d'un contrat formateur signé.
   *
   * Les gardes posées en 3.25.155 empêchaient de détruire une pièce comptable,
   * mais renvoyaient vers un « archivage » qui n'existait nulle part : plus aucun
   * chemin ne permettait de retirer un formateur une fois son contrat signé.
   * Cette action comble le vide sans affaiblir la protection : le PDF est
   * TÉLÉCHARGÉ (il sort de l'application, donc la conservation dix ans au titre de
   * l'article L123-22 du Code de commerce est assurée hors ligne), puis horodaté
   * en base. La suppression n'est alors plus un effacement sans copie.
   */
  public function handle_archive_trainer_contract() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $contract_id = isset( $_GET['contract_id'] ) ? absint( wp_unslash( $_GET['contract_id'] ) ) : 0;
    if ( ! $contract_id ) {
      wp_die( esc_html( 'Document introuvable.' ) );
    }
    check_admin_referer( 'acdc_archive_trainer_contract_' . $contract_id );

    global $wpdb;
    $contract = $wpdb->get_row( $wpdb->prepare(
      "SELECT id, trainer_id, contract_pdf_url, signed_document_url FROM {$this->trainer_contract_table} WHERE id = %d",
      $contract_id
    ) );
    if ( ! $contract ) {
      wp_die( esc_html( 'Document introuvable.' ) );
    }

    /* On archive l'exemplaire SIGNÉ ; à défaut, le contrat généré. */
    $real_path = $this->acdc_trainer_contract_file_path( $contract, true );
    if ( '' === $real_path ) {
      $real_path = $this->acdc_trainer_contract_file_path( $contract, false );
    }
    if ( '' === $real_path ) {
      /* Le PDF a disparu du disque : on refuse d'horodater un archivage qui n'a pas
         eu lieu — sans quoi la garde se lèverait sur une pièce inexistante. */
      $back = $this->portal_page_url( array(
        'tab'         => 'trainers',
        'action'      => 'edit',
        'item_id'     => (int) $contract->trainer_id,
        'notice'      => rawurlencode( 'Archivage impossible : le PDF de cette mission est introuvable sur le serveur. Régénérez-le avant d’archiver.' ),
        'notice_type' => 'error',
      ) );
      wp_safe_redirect( $back );
      exit;
    }

    /* ACDC 3.25.158 — L'ARCHIVAGE NE DIFFUSE PLUS LE FICHIER.
       Version précédente : cette action diffusait le PDF puis n'horodatait qu'après
       avoir compté les octets émis. En recette, la réponse HTTP retombait malgré
       tout en 503 — comme la génération du PDF, et contrairement au service de
       consultation qui répond 200 sur le même fichier — alors que PHP, lui, voyait
       une diffusion complète et une connexion normale. Le garde-fou mesurait donc
       une chose que PHP ne peut pas observer : un échec survenu dans la couche
       serveur après la fin du script.
       Le téléchargement et l'horodatage sont désormais deux gestes distincts :
       l'organisme ouvre le PDF par le service de consultation — celui qui
       fonctionne — puis confirme explicitement l'archivage. La confirmation a du
       sens, puisqu'elle suit la consultation effective du document. Cette action
       ne produit plus aucune sortie binaire : il ne reste rien qui puisse casser
       la réponse. */
    if ( ! is_file( $real_path ) || filesize( $real_path ) <= 0 ) {
      $back_empty = $this->portal_page_url( array(
        'tab'         => 'trainers',
        'action'      => 'edit',
        'item_id'     => (int) $contract->trainer_id,
        'notice'      => rawurlencode( 'Archivage impossible : le PDF de cette mission est introuvable ou vide sur le serveur. Régénérez-le avant d’archiver.' ),
        'notice_type' => 'error',
      ) );
      wp_safe_redirect( $back_empty );
      exit;
    }

    $wpdb->update(
      $this->trainer_contract_table,
      array( 'archived_at' => current_time( 'mysql' ) ),
      array( 'id' => $contract_id ),
      array( '%s' ),
      array( '%d' )
    );
    $this->log_action_event( 'archive', 'trainer_contract_pdf', $contract_id, 'success', array( 'file' => basename( $real_path ), 'bytes' => (int) filesize( $real_path ) ) );

    wp_safe_redirect( $this->portal_page_url( array(
      'tab'         => 'trainers',
      'action'      => 'edit',
      'item_id'     => (int) $contract->trainer_id,
      'notice'      => rawurlencode( 'Contrat archivé. La suppression de cette mission est désormais autorisée.' ),
      'notice_type' => 'success',
    ) ) );
    exit;
  }

  /**
   * ACDC 3.25.157 — URL d'archivage (téléchargement + horodatage) d'un contrat.
   *
   * @param int $contract_id Identifiant de la mission.
   * @return string URL nonce-ée.
   */
  private function acdc_trainer_contract_archive_url( $contract_id ) {
    return wp_nonce_url(
      add_query_arg(
        array( 'action' => 'acdc_archive_trainer_contract', 'contract_id' => (int) $contract_id ),
        admin_url( 'admin-post.php' )
      ),
      'acdc_archive_trainer_contract_' . (int) $contract_id
    );
  }

  /**
   * ACDC 3.25.148 — F11 : nom de fichier NON DEVINABLE pour un contrat formateur.
   *
   * Le nom précédent (`contrat-formateur-4-5.pdf`) était entièrement prévisible :
   * n'importe qui pouvait énumérer les identifiants et récupérer les contrats.
   * On y adjoint un condensat dérivé des clés secrètes du site (wp_hash) :
   * déterministe — donc une régénération retrouve le même fichier — mais
   * impossible à deviner de l'extérieur.
   *
   * Cette protection vaut EN PLUS du .htaccess, et reste efficace sur les
   * serveurs qui ignorent .htaccess (nginx notamment).
   *
   * @param int $trainer_id  Identifiant du formateur.
   * @param int $contract_id Identifiant de la mission.
   * @param string $suffix   Suffixe optionnel (ex. '-signe').
   * @return string Nom de fichier.
   */
  private function acdc_trainer_contract_filename( $trainer_id, $contract_id, $suffix = '' ) {
    $token = substr( wp_hash( 'acdc-trainer-contract-' . (int) $trainer_id . '-' . (int) $contract_id ), 0, 20 );
    return 'contrat-formateur-' . (int) $trainer_id . '-' . (int) $contract_id . $suffix . '-' . $token . '.pdf';
  }

  /**
   * ACDC 3.25.148 — F11 : rend un dossier de contrats inaccessible en direct.
   * Même procédé que celui déjà employé pour les pièces d'identité de signature.
   *
   * @param string $dir_path Chemin du dossier à créer/protéger.
   */
  private function acdc_protect_contracts_dir( $dir_path ) {
    wp_mkdir_p( $dir_path );
    $ht = trailingslashit( $dir_path ) . '.htaccess';
    if ( ! file_exists( $ht ) ) {
      file_put_contents( $ht, "deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    }
    $idx = trailingslashit( $dir_path ) . 'index.php';
    if ( ! file_exists( $idx ) ) {
      file_put_contents( $idx, "<?php // Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    }
    /* Protection de la racine acdc-of-contracts/ : couvre aussi les dossiers déjà
       créés avant ce correctif (serveurs Apache), en une seule passe. */
    $root = trailingslashit( dirname( untrailingslashit( $dir_path ) ) );

    /* ACDC 3.25.240 — CETTE FONCTION A INTERDIT TOUTE LA MÉDIATHÈQUE.
       Elle protège un dossier ET son parent, ce qui est juste quand on lui
       passe « acdc-of-contracts/42/ » : le parent est « acdc-of-contracts/ ».
       En 3.25.225 je lui ai passé « uploads/acdc-certificates/ », dont le
       parent est « wp-content/uploads/ ». Un « deny from all » s'est donc écrit
       à la racine des téléversements, et le serveur a rendu 403 sur TOUT ce qui
       s'y trouve : les images du site, les logos des e-mails, les pages HTML des
       propositions commerciales envoyées aux prospects.
       Une fonction qui écrit chez son parent doit vérifier de qui elle est
       l'enfant. Elle ne remonte donc plus jamais au-dessus de son propre
       dossier racine. */
    $uploads  = wp_upload_dir();
    $base_dir = ! empty( $uploads['basedir'] ) ? trailingslashit( (string) $uploads['basedir'] ) : '';
    if ( '' !== $base_dir && ( $root === $base_dir || strlen( $root ) <= strlen( $base_dir ) ) ) {
      return;
    }

    $ht_root = $root . '.htaccess';
    if ( is_dir( $root ) && ! file_exists( $ht_root ) ) {
      file_put_contents( $ht_root, "deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    }
  }

  /**
   * ACDC 3.25.240 — RÉPARE LA MÉDIATHÈQUE INTERDITE PAR LA 3.25.225.
   *
   * Le fichier fautif existe déjà sur les sites qui ont généré une attestation :
   * réinstaller le plugin ne le retire pas, puisque toutes les écritures sont
   * gardées par `! file_exists()`. Il faut donc le supprimer explicitement.
   *
   * On ne supprime QUE si le contenu est exactement celui que le plugin écrit.
   * Un `.htaccess` que l'hébergeur ou David aurait posé lui-même à la racine des
   * téléversements ne nous appartient pas : on n'y touche pas, et on le dit dans
   * le journal plutôt que de décider à sa place.
   */
  private function acdc_repair_uploads_htaccess() {
    $uploads = wp_upload_dir();
    if ( empty( $uploads['basedir'] ) ) {
      return;
    }
    $base = trailingslashit( (string) $uploads['basedir'] );

    /* La racine des téléversements, et le dossier des attestations : leurs
       fichiers sont diffusés par URL, les interdire revient à les perdre. */
    $targets = array( $base . '.htaccess', $base . 'acdc-certificates/.htaccess' );

    foreach ( $targets as $path ) {
      if ( ! file_exists( $path ) ) {
        continue;
      }
      $contents = trim( (string) @file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
      if ( 'deny from all' === strtolower( $contents ) ) {
        @unlink( $path );
        error_log( '[ACDC 3.25.240] Interdiction levée sur ' . $path . ' — elle bloquait toute la médiathèque.' );
      } else {
        error_log( '[ACDC 3.25.240] ' . $path . ' conservé : son contenu n’est pas celui du plugin.' );
      }
    }
  }

  /**
   * ACDC 3.25.148 — F11 : purge des PDF d'une mission (contrat + exemplaire signé).
   * Appelée à la suppression d'une mission ou d'un formateur : les fichiers ne
   * doivent pas survivre à la donnée qu'ils documentent.
   *
   * @param int $contract_id Identifiant de la mission.
   */
  private function acdc_purge_trainer_contract_files( $contract_id ) {
    $contract_id = (int) $contract_id;
    if ( ! $contract_id ) {
      return;
    }
    $upload_dir = wp_upload_dir();
    $dir        = trailingslashit( $upload_dir['basedir'] ) . 'acdc-of-contracts/' . $contract_id . '/';
    if ( ! is_dir( $dir ) ) {
      return;
    }
    /* glob('*') n'attrape PAS les fichiers cachés : sans le motif .{*}, le .htaccess
       posé par la protection restait en place et rmdir() échouait, laissant un dossier
       vide derrière chaque mission supprimée. */
    $files = array_merge(
      (array) glob( $dir . '*' ),
      (array) glob( $dir . '.*' )
    );
    foreach ( $files as $f ) {
      if ( is_file( $f ) ) {
        @unlink( $f ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
      }
    }
    @rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
  }

  /**
   * ACDC 3.25.148 — G4 : envoi EXPLICITE du contrat au formateur.
   * Action séparée du téléchargement, déclenchée par un bouton dédié avec
   * confirmation. Génère le PDF s'il n'existe pas encore.
   */
  public function handle_send_trainer_contract_email() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $trainer_id  = isset( $_POST['trainer_id'] )  ? absint( wp_unslash( $_POST['trainer_id'] ) )  : 0;
    $contract_id = isset( $_POST['contract_id'] ) ? absint( wp_unslash( $_POST['contract_id'] ) ) : 0;
    if ( ! $trainer_id || ! $contract_id ) {
      wp_die( esc_html( 'Données manquantes.' ) );
    }
    check_admin_referer( 'acdc_send_trainer_contract_email_' . $trainer_id );

    global $wpdb;
    $trainer  = $this->get_trainer( $trainer_id );
    $contract = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->trainer_contract_table} WHERE id = %d AND trainer_id = %d", $contract_id, $trainer_id ) );
    if ( ! $trainer || ! $contract ) {
      wp_die( esc_html( 'Données introuvables.' ) );
    }

    $back = $this->acdc_trainer_contract_back_url( $trainer_id );

    $tr_email_send = sanitize_email( (string) $trainer->email );
    if ( '' === $tr_email_send || ! is_email( $tr_email_send ) ) {
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Ce formateur n\'a pas d\'adresse e-mail valide.' ), 'notice_type' => 'error' ), $back ) );
      exit;
    }

    /* Le PDF doit exister : on le régénère au besoin (sans rien envoyer d'autre). */
    $upload_dir = wp_upload_dir();
    $safe_name  = $this->acdc_trainer_contract_filename( $trainer_id, $contract_id );
    $dir_path   = trailingslashit( $upload_dir['basedir'] ) . 'acdc-of-contracts/' . $contract_id . '/';
    $file_path  = $dir_path . $safe_name;
    if ( ! file_exists( $file_path ) ) {
      $pages       = $this->build_trainer_contract_pdf_pages( $trainer, $contract );
      $pdf_content = '';
      if ( ! empty( $pages ) && class_exists( 'ACDC_Sig_Core' ) && class_exists( 'ACDC_Sig_PDF' ) ) {
        $sig_core = new ACDC_Sig_Core();
        $sig_core->init_tables();
        $sig_pdf  = new ACDC_Sig_PDF( $sig_core );
        $ref      = new ReflectionClass( $sig_pdf );
        $method   = $ref->getMethod( 'render_to_string' );
        $method->setAccessible( true );
        $pdf_content = $method->invoke( $sig_pdf, $pages );
      }
      if ( ( ! is_string( $pdf_content ) || '' === $pdf_content ) && method_exists( $this, '_build_simple_pdf_string' ) ) {
        $pdf_content = $this->_build_simple_pdf_string( $pages );
      }
      if ( '' === (string) $pdf_content ) {
        wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Impossible de générer le contrat PDF.' ), 'notice_type' => 'error' ), $back ) );
        exit;
      }
      $this->acdc_protect_contracts_dir( $dir_path );
      file_put_contents( $file_path, $pdf_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    }

    $tr_name_send = trim( (string) $trainer->first_name . ' ' . (string) $trainer->last_name );
    $label        = wp_strip_all_tags( html_entity_decode( (string) $contract->label, ENT_QUOTES, 'UTF-8' ) );
    $profile_mail = $this->get_company_profile_options();
    $from_name    = ! empty( $profile_mail['enterprise_contact_name'] ) ? sanitize_text_field( (string) $profile_mail['enterprise_contact_name'] ) : get_bloginfo( 'name' );
    $from_email   = ! empty( $profile_mail['enterprise_contact_email'] ) ? sanitize_email( (string) $profile_mail['enterprise_contact_email'] ) : sanitize_email( (string) get_option( 'admin_email' ) );

    $subject = '📄 Votre contrat de sous-traitance — ' . $label;
    /* ACDC 3.25.246 — Gabarit commun. Cet envoi construisait déjà ses en-têtes
       d'attribution par le point de passage, mais écrivait son corps à la main :
       le formateur recevait un contrat sans logo ni pied de page, là où le
       commanditaire en reçoit un habillé. Et il échappait au mode recette. */
    $sent = $this->acdc_send_transactional_email(
      $tr_email_send,
      $subject,
      array(
        'greeting_name' => $tr_name_send,
        'intro_html'    => '<p>Veuillez trouver en pièce jointe votre contrat de sous-traitance pour la mission <strong>' . esc_html( $label ) . '</strong>.</p>',
        'body_html'     => '<p>Ce document vous sera renvoyé contresigné après validation.</p>',
        'footer_notice' => 'Cet e-mail vous est adressé dans le cadre de votre mission de formation. Vos données sont traitées conformément au RGPD.',
      ),
      array(
        'source_module'       => 'trainers',
        'source_action'       => 'trainer_contract_sent',
        'related_entity_type' => 'trainer',
        'related_entity_id'   => (int) $trainer_id,
        'email_category'      => 'contractuel',
        'email_audience'      => 'formateur',
      ),
      array( $file_path )
    );
    $this->log_action_event( 'send', 'trainer_contract_email', $contract_id, $sent ? 'success' : 'error' );

    wp_safe_redirect( add_query_arg( array(
      'notice'      => rawurlencode( $sent ? 'Contrat envoyé au formateur.' : 'L\'envoi du contrat a échoué.' ),
      'notice_type' => $sent ? 'success' : 'error',
    ), $back ) );
    exit;
  }

  /** URL de retour de la fiche formateur (front-office ou wp-admin selon l'origine réelle). */
  private function acdc_trainer_contract_back_url( $trainer_id ) {
    $page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : '';
    if ( '' !== $page && 0 === strpos( $page, 'acdc-of-' ) ) {
      return admin_url( 'admin.php?page=acdc-of-trainers&action=edit&item_id=' . (int) $trainer_id );
    }
    return $this->portal_page_url( array( 'tab' => 'trainers', 'action' => 'edit', 'item_id' => (int) $trainer_id ) );
  }

  /**
   * ACDC 3.22.3 — Construit les pages PDF du contrat de sous-traitance formateur.
   * Mise en page identique à la convention : logo, header, footer, $add_box AFM.
   * Appel : $this->build_trainer_contract_pdf_pages( $trainer, $contract )
   *
   * @param object $trainer  Ligne acdc_of_trainers.
   * @param object $contract Ligne acdc_of_trainer_contracts.
   * @return array           Tableau de pages compatible ACDC_Sig_PDF / _build_simple_pdf_string.
   */
  private function build_trainer_contract_pdf_pages( $trainer, $contract, $handwritten_sig_path = '' ) {
    $clauses  = $this->get_subcontract_params_options();
    $profile  = $this->get_company_profile_options();
    $navy = '#0C2D52'; $gold = '#C5A253'; $ink = '#1f2937'; $muted = '#6b7280'; $line = '#d7dde6';
    $page_w = 595; $page_h = 842; $left = 35.43; $right = 35.43;
    $content_w = $page_w - $left - $right;
    // ── Données organisme ─────────────────────────────────────────────────
    $org_name  = ! empty( $profile['enterprise'] )               ? (string) $profile['enterprise']               : 'ACDC-Formation';
    $org_addr  = trim( ( ! empty( $profile['address'] )           ? (string) $profile['address']          : '' ) . ( ! empty( $profile['postal_code'] ) ? ', ' . (string) $profile['postal_code'] : '' ) . ( ! empty( $profile['city'] ) ? ' ' . (string) $profile['city'] : '' ) );
    $org_siret = ! empty( $profile['siret_identification'] )      ? (string) $profile['siret_identification']      : '';
    $org_nda   = ! empty( $profile['nda_number'] )                ? (string) $profile['nda_number']                : '';
    /* Téléphone, e-mail et site ne servaient qu'au pied de page : la charte les
       lit elle-même dans les Réglages. */
    $org_rep   = trim( ( ! empty( $profile['first_name'] ) ? (string) $profile['first_name'] : '' ) . ' ' . ( ! empty( $profile['last_name'] ) ? (string) $profile['last_name'] : '' ) );
    if ( '' === trim( $org_rep ) ) { $org_rep = $org_name; }
    // ── Données formateur ─────────────────────────────────────────────────
    $tr_name  = trim( (string) $trainer->first_name . ' ' . (string) $trainer->last_name );
    $tr_siret = ! empty( $trainer->siret ) ? (string) $trainer->siret : '';
    $tr_email = ! empty( $trainer->email ) ? (string) $trainer->email : '';
    // ── Données mission ───────────────────────────────────────────────────
    $nb_h    = number_format( (float) $contract->nb_heures,  1, ',', ' ' );
    $taux    = number_format( (float) $contract->taux_ht,    2, ',', ' ' );
    $montant = number_format( (float) $contract->montant_ht, 2, ',', ' ' );
    $d_start = ! empty( $contract->date_start ) ? date_i18n( 'd/m/Y', strtotime( $contract->date_start ) ) : '—';
    $d_end   = ! empty( $contract->date_end )   ? date_i18n( 'd/m/Y', strtotime( $contract->date_end ) )   : '—';
    $mission_label = wp_strip_all_tags( html_entity_decode( (string) $contract->label, ENT_QUOTES, 'UTF-8' ) );
    $formation_ref = wp_strip_all_tags( html_entity_decode( (string) $contract->formation_ref, ENT_QUOTES, 'UTF-8' ) );
    // ── Images ────────────────────────────────────────────────────────────
    /* Le logo est posé par la charte : le charger ici une seconde fois coûtait
       une lecture de fichier à chaque génération, pour rien. */
    /* ACDC 3.25.254 — Le cachet et la signature sont posés plus bas par la
       charte : c'est elle qui connaît l'ordre des sources et le calcul d'échelle. */
    // Signature manuscrite du formateur (apposée après OTP) — même pattern que la convention
    $handwritten_image = null;
    if ( '' !== $handwritten_sig_path && file_exists( $handwritten_sig_path ) && function_exists( 'imagecreatefromstring' ) ) {
      $hw_raw = @file_get_contents( $handwritten_sig_path );
      if ( $hw_raw ) {
        $hw_gd = @imagecreatefromstring( $hw_raw );
        if ( $hw_gd ) {
          $hw_w = imagesx( $hw_gd ); $hw_h = imagesy( $hw_gd );
          $hw_ratio = ( $hw_w && $hw_h ) ? min( 220 / $hw_w, 100 / $hw_h, 1 ) : 1;
          $hw_dw = max( 1, (int) round( $hw_w * $hw_ratio ) );
          $hw_dh = max( 1, (int) round( $hw_h * $hw_ratio ) );
          $hw_flat = imagecreatetruecolor( $hw_w, $hw_h );
          imagefilledrectangle( $hw_flat, 0, 0, $hw_w, $hw_h, imagecolorallocate( $hw_flat, 255, 255, 255 ) );
          imagealphablending( $hw_flat, true );
          imagecopy( $hw_flat, $hw_gd, 0, 0, 0, 0, $hw_w, $hw_h );
          ob_start(); imagejpeg( $hw_flat, null, 92 ); $hw_jpeg = ob_get_clean();
          imagedestroy( $hw_flat ); imagedestroy( $hw_gd );
          if ( $hw_jpeg ) {
            $handwritten_image = array(
              'key'            => 'hw_sig_' . md5( $handwritten_sig_path ),
              'data'           => $hw_jpeg,
              'width'          => $hw_w,
              'height'         => $hw_h,
              'display_width'  => $hw_dw,
              'display_height' => $hw_dh,
            );
          }
        }
      }
    }
    /* ACDC 3.25.254 — En-tête et pied viennent de la charte commune. Ce sont
       les mesures de ce document — il est la référence — mais elles vivent
       maintenant à un seul endroit, où la convention et la convocation les
       lisent aussi. */
    $add_footer = function( &$page ) {
      $this->acdc_pdf_charte_footer( $page );
    };
    $create_page = function( $title_line, $subtitle_line = '' ) {
      return $this->acdc_pdf_charte_header( $title_line, $subtitle_line );
    };
    // ── Helper : bloc article ─────────────────────────────────────────────
    $add_box = function( &$page, &$cursor_y, $title, $text ) use ( $left, $content_w, $line, $gold, $ink, $navy ) {
      $padding = 12.76; $usable_w = $content_w - 2 * $padding;
      $max_chars = max( 22, (int) floor( $usable_w / 3.6 ) );
      $wrap = static function( $t ) use ( $max_chars ) {
        $t = trim( wp_strip_all_tags( html_entity_decode( (string) $t, ENT_QUOTES, 'UTF-8' ) ) );
        if ( '' === $t ) { return array(); }
        $words = explode( ' ', $t ); $out = array(); $cur = '';
        foreach ( $words as $w ) {
          $test = '' === $cur ? $w : $cur . ' ' . $w;
          if ( mb_strlen( $test ) <= $max_chars ) { $cur = $test; } else { if ( '' !== $cur ) { $out[] = $cur; } $cur = $w; }
        }
        if ( '' !== $cur ) { $out[] = $cur; }
        return $out;
      };
      $wrapped = $wrap( $text );
      $line_h = 11.8; $title_h = 15.0; $pad_v = 11.34;
      $box_h = $title_h + $pad_v + count( $wrapped ) * $line_h + $pad_v;
      if ( $cursor_y - $box_h < 60 ) { return false; }
      $box_y = $cursor_y - $box_h;
      $page[] = array( 'type' => 'rect', 'x' => $left, 'y' => $box_y, 'width' => $content_w, 'height' => $box_h, 'fill_color' => '#f8fafc', 'stroke_color' => $line );
      $page[] = array( 'type' => 'rect', 'x' => $left, 'y' => $box_y + $box_h - $title_h - 2, 'width' => $content_w, 'height' => $title_h + 2, 'fill_color' => '#eef2f8' );
      $page[] = array( 'text' => $title, 'x' => $left + $padding, 'y' => $box_y + $box_h - $title_h + 3, 'size' => 8.5, 'font' => 'Helvetica-Bold', 'color' => $navy );
      $ty = $box_y + $box_h - $title_h - $pad_v - $line_h + 2;
      foreach ( $wrapped as $l ) {
        $page[] = array( 'text' => $l, 'x' => $left + $padding, 'y' => $ty, 'size' => 8.3, 'font' => 'Helvetica', 'color' => $ink );
        $ty -= $line_h;
      }
      $cursor_y -= ( $box_h + 6 );
      return true;
    };
    // ── Page 1 ────────────────────────────────────────────────────────────
    $p1 = $create_page( 'CONTRAT DE SOUS-TRAITANCE FORMATEUR', 'Ref. mission : ' . $mission_label );
    $cy1 = 700.0;
    $p1[] = array( 'type' => 'rect', 'x' => $left, 'y' => $cy1 - 1, 'width' => $content_w, 'height' => 0.8, 'fill_color' => $gold );
    $p1[] = array( 'text' => 'ENTRE LES SOUSSIGNÉS', 'x' => $left, 'y' => $cy1 + 10, 'size' => 9, 'font' => 'Helvetica-Bold', 'color' => $navy );
    $cy1 -= 22;
    $half = ( $content_w - 10 ) / 2;
    $p1[] = array( 'type' => 'rect', 'x' => $left,          'y' => $cy1 - 60, 'width' => $half, 'height' => 68, 'fill_color' => '#f0f4fa', 'stroke_color' => $line );
    $p1[] = array( 'text' => "L'Organisme de formation :", 'x' => $left + 8,     'y' => $cy1 - 3,  'size' => 8,   'font' => 'Helvetica-Bold', 'color' => $navy );
    $p1[] = array( 'text' => $org_name,                     'x' => $left + 8,   'y' => $cy1 - 16, 'size' => 8.5, 'font' => 'Helvetica-Bold', 'color' => $ink );
    if ( $org_addr )  { $p1[] = array( 'text' => $org_addr,                'x' => $left + 8, 'y' => $cy1 - 27, 'size' => 7.8, 'font' => 'Helvetica', 'color' => $muted ); }
    if ( $org_siret ) { $p1[] = array( 'text' => 'Siret : ' . $org_siret,  'x' => $left + 8, 'y' => $cy1 - 38, 'size' => 7.8, 'font' => 'Helvetica', 'color' => $muted ); }
    if ( $org_nda )   { $p1[] = array( 'text' => 'NDA : '  . $org_nda,     'x' => $left + 8, 'y' => $cy1 - 49, 'size' => 7.8, 'font' => 'Helvetica', 'color' => $muted ); }
    $rx = $left + $half + 10;
    $p1[] = array( 'type' => 'rect', 'x' => $rx, 'y' => $cy1 - 60, 'width' => $half, 'height' => 68, 'fill_color' => '#f0f4fa', 'stroke_color' => $line );
    $p1[] = array( 'text' => 'Le Formateur :', 'x' => $rx + 8, 'y' => $cy1 - 3,  'size' => 8,   'font' => 'Helvetica-Bold', 'color' => $navy );
    $p1[] = array( 'text' => $tr_name,         'x' => $rx + 8, 'y' => $cy1 - 16, 'size' => 8.5, 'font' => 'Helvetica-Bold', 'color' => $ink );
    if ( $tr_siret ) { $p1[] = array( 'text' => 'Siret : ' . $tr_siret, 'x' => $rx + 8, 'y' => $cy1 - 27, 'size' => 7.8, 'font' => 'Helvetica', 'color' => $muted ); }
    if ( $tr_email ) { $p1[] = array( 'text' => 'E-mail : ' . $tr_email, 'x' => $rx + 8, 'y' => $cy1 - 38, 'size' => 7.8, 'font' => 'Helvetica', 'color' => $muted ); }
    $cy1 -= 72;
    $p1[] = array( 'text' => 'Il est convenu ce qui suit :', 'x' => $left, 'y' => $cy1, 'size' => 8.5, 'font' => 'Helvetica', 'color' => $muted );
    $cy1 -= 16;
    $add_box( $p1, $cy1, 'Article 1 — Objet de la mission',    $mission_label . ( '' !== $formation_ref ? ' — Formation : ' . $formation_ref : '' ) );
    $add_box( $p1, $cy1, 'Article 2 — Durée et volume horaire', 'Période : du ' . $d_start . ' au ' . $d_end . ' — Volume horaire : ' . $nb_h . ' H dispensées par le formateur.' );
    $add_box( $p1, $cy1, 'Article 3 — Obligations du formateur', ! empty( $clauses['article_3_trainer_obligations'] ) ? (string) $clauses['article_3_trainer_obligations'] : '' );
    $add_box( $p1, $cy1, "Article 4 — Obligations de l'OF",     ! empty( $clauses['article_4_of_obligations'] )       ? (string) $clauses['article_4_of_obligations']       : '' );
    $add_box( $p1, $cy1, 'Article 5 — Statut juridique',        ! empty( $clauses['article_5_status_liability'] )     ? (string) $clauses['article_5_status_liability']     : '' );
    $add_footer( $p1 );
    // ── Page 2 ────────────────────────────────────────────────────────────
    $p2 = $create_page( 'CONTRAT DE SOUS-TRAITANCE FORMATEUR — Suite', $tr_name . ' — ' . $mission_label );
    $cy2 = 700.0;
    $remun = 'Taux horaire HT : ' . $taux . ' €/H × ' . $nb_h . ' H = ' . $montant . ' € HT. Règlement sur présentation de facture.';
    $add_box( $p2, $cy2, 'Article 6 — Rémunération',             $remun );
    $add_box( $p2, $cy2, 'Article 7 — Modalités de paiement',    ! empty( $clauses['article_7_payment_terms'] )         ? (string) $clauses['article_7_payment_terms']         : '' );
    $add_box( $p2, $cy2, 'Article 8 — Durée et résiliation',     ! empty( $clauses['article_8_duration_termination'] )  ? (string) $clauses['article_8_duration_termination']  : '' );
    $add_box( $p2, $cy2, 'Article 9 — Propriété intellectuelle', ! empty( $clauses['article_9_intellectual_property'] ) ? (string) $clauses['article_9_intellectual_property'] : '' );
    $add_box( $p2, $cy2, 'Article 10 — Confidentialité',         ! empty( $clauses['article_10_confidentiality'] )      ? (string) $clauses['article_10_confidentiality']      : '' );
    $add_box( $p2, $cy2, 'Article 11 — Différends',              ! empty( $clauses['article_11_disputes'] )             ? (string) $clauses['article_11_disputes']             : '' );
    if ( ! empty( $clauses['additional_sections'] ) && is_array( $clauses['additional_sections'] ) ) {
      $sn = 12;
      foreach ( $clauses['additional_sections'] as $sec ) {
        $st = isset( $sec['title'] )   ? sanitize_text_field( (string) $sec['title'] )                                        : '';
        $sc = isset( $sec['content'] ) ? wp_strip_all_tags( html_entity_decode( (string) $sec['content'], ENT_QUOTES, 'UTF-8' ) ) : '';
        if ( '' === $st && '' === trim( $sc ) ) { continue; }
        $add_box( $p2, $cy2, 'Article ' . $sn . ' — ' . $st, $sc );
        $sn++;
      }
    }
    // ── Bloc signatures page 2 ────────────────────────────────────────────
    // ACDC 3.22.13 — sig_top remonté pour laisser de la place au cachet+signature OF
    $sig_top = min( $cy2 - 10, 230.0 );
    $sign_box_w = 220;
    $p2[] = array( 'text' => 'Fait en deux exemplaires originaux — ' . date_i18n( 'd/m/Y' ), 'x' => $left, 'y' => $sig_top + 10, 'size' => 8, 'font' => 'Helvetica', 'color' => $muted );
    $sig_top -= 8;
    // Labels
    $p2[] = array( 'text' => "Pour l'organisme de formation", 'x' => $left,                          'y' => $sig_top,     'size' => 8.5, 'font' => 'Helvetica-Bold', 'color' => $navy );
    $p2[] = array( 'text' => 'Pour le formateur',              'x' => $page_w - $right - $sign_box_w, 'y' => $sig_top,     'size' => 8.5, 'font' => 'Helvetica-Bold', 'color' => $navy );
    $p2[] = array( 'text' => $org_rep, 'x' => $left,                          'y' => $sig_top - 12, 'size' => 8, 'font' => 'Helvetica', 'color' => $ink );
    $p2[] = array( 'text' => $tr_name, 'x' => $page_w - $right - $sign_box_w, 'y' => $sig_top - 12, 'size' => 8, 'font' => 'Helvetica', 'color' => $ink );
    $sig_img_y = $sig_top - 18;
    // Cachet+signature OF (gauche) — ratio préservé, même pattern que la convention
    /* ACDC 3.25.254 — Le cachet sortait ÉCRASÉ de 16 %. Deux plafonds
       indépendants — 280 en largeur, 160 en hauteur — rabotaient la hauteur
       seule dès qu'elle mordait : un cachet de 1600 × 1200 devenait 255 × 160
       au lieu de 255 × 191, et un cachet rond devient alors un ovale. La charte
       calcule un SEUL rapport de réduction pour les deux dimensions. */
    $stamp_line = $this->acdc_pdf_charte_stamp( $left, 0 );
    if ( $stamp_line ) {
      $stamp_line['y'] = max( 42, $sig_img_y - (float) $stamp_line['display_height'] );
      $p2[] = $stamp_line;
    } else {
      $p2[] = array( 'type' => 'rect', 'x' => $left, 'y' => $sig_img_y - 40, 'width' => $sign_box_w, 'height' => 0.5, 'fill_color' => '#aab4c0' );
    }
    // Signature manuscrite formateur (droite) — coordonnées calibrées
    $tr_sig_x = 332.57;
    if ( $handwritten_image ) {
      /* ACDC 3.25.260 — Deux plafonds indépendants, plus un facteur 1,2 :
         la signature du formateur pouvait sortir déformée comme celle du
         bénéficiaire sur la convention. La charte décide seule de la taille. */
      list( $hw_box_w, $hw_box_h ) = $this->acdc_pdf_signature_box( 'signature' );
      list( $hw_dw, $hw_dh ) = $this->acdc_pdf_scaled_size(
        $handwritten_image['width'],
        $handwritten_image['height'],
        $hw_box_w,
        $hw_box_h
      );
      $p2[] = array(
        'type'           => 'image',
        'image_key'      => $handwritten_image['key'],
        'image_data'     => $handwritten_image['data'],
        'image_width'    => $handwritten_image['width'],
        'image_height'   => $handwritten_image['height'],
        'display_width'  => $hw_dw,
        'display_height' => $hw_dh,
        'x'              => $tr_sig_x,
        'y'              => max( 42, $sig_img_y - $hw_dh ),
      );
    } else {
      $p2[] = array( 'type' => 'rect', 'x' => $tr_sig_x, 'y' => $sig_img_y - 40, 'width' => $sign_box_w, 'height' => 0.5, 'fill_color' => '#aab4c0' );
      $p2[] = array( 'text' => 'Signature électronique en attente', 'x' => $tr_sig_x, 'y' => $sig_img_y - 52, 'size' => 7, 'font' => 'Helvetica', 'color' => '#9ca3af' );
    }

    /* ACDC 3.25.227 — LE DOCUMENT SIGNÉ NE DISAIT PAS QU'IL L'ÉTAIT.
       La recette l'a formulé exactement : « le PDF signé ne porte aucune
       mention horodatée de la signature ; l'image remplace simplement la ligne
       d'attente ». Une image manuscrite, seule, ne prouve rien — n'importe qui
       peut coller un dessin dans un PDF. Ce qui fait la valeur probante d'une
       signature électronique, c'est la trace de son procédé : quand, par quel
       moyen, avec quelle vérification. Ce contrat a exigé un code à usage
       unique envoyé par e-mail ; le document ne le mentionnait nulle part.
       On écrit donc, sous la signature, ce que l'application sait avec
       certitude — et rien d'autre. */
    if ( $handwritten_image && ! empty( $contract->signature_completed_at ) ) {
      $proof_y = max( 30, $sig_img_y - 62 );
      $p2[] = array(
        'text'  => 'Signée électroniquement le ' . mysql2date( 'd/m/Y à H\hi', (string) $contract->signature_completed_at ),
        'x'     => $tr_sig_x,
        'y'     => $proof_y,
        'size'  => 6.6,
        'font'  => 'Helvetica-Bold',
        'color' => '#4b5563',
      );
      $p2[] = array(
        'text'  => 'Identité vérifiée par code à usage unique adressé par e-mail au signataire.',
        'x'     => $tr_sig_x,
        'y'     => $proof_y - 8,
        'size'  => 6,
        'font'  => 'Helvetica',
        'color' => '#6b7280',
      );
      if ( ! empty( $contract->signature_request_id ) ) {
        $p2[] = array(
          'text'  => 'Référence de la demande de signature : ' . (int) $contract->signature_request_id . '.',
          'x'     => $tr_sig_x,
          'y'     => $proof_y - 16,
          'size'  => 6,
          'font'  => 'Helvetica',
          'color' => '#6b7280',
        );
      }
    }

    $add_footer( $p2 );
    return array( $p1, $p2 );
  }


  /**
   * ACDC 3.22.3 — Envoie le contrat formateur en signature électronique OTP.
   * Crée la signature_request, envoie l'e-mail au formateur, met à jour signature_status.
   */
  public function handle_send_trainer_contract_signature() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $trainer_id  = isset( $_POST['trainer_id'] )  ? absint( wp_unslash( $_POST['trainer_id'] ) )  : 0;
    $contract_id = isset( $_POST['contract_id'] ) ? absint( wp_unslash( $_POST['contract_id'] ) ) : 0;
    if ( ! $trainer_id || ! $contract_id ) {
      $this->redirect_to_portal( 'trainers', 'Données manquantes.', 'error' );
      return;
    }
    check_admin_referer( 'acdc_send_trainer_contract_signature_' . $trainer_id );
    global $wpdb;
    $trainer  = $this->get_trainer( $trainer_id );
    $contract = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->trainer_contract_table} WHERE id = %d AND trainer_id = %d", $contract_id, $trainer_id ) );
    if ( ! $trainer || ! $contract ) {
      $this->redirect_to_portal( 'trainers', 'Contrat ou formateur introuvable.', 'error' );
      return;
    }
    /* ACDC 3.25.262 — Fabrication du PDF et demande de signature vivent
       désormais dans deux fonctions partagées : le bouton dédié et le nouvel
       « Enregistrer et envoyer pour signature » suivent exactement le même
       chemin. Le PDF manquant n'est plus une impasse — il se fabrique. */
    $verdict = $this->acdc_request_trainer_contract_signature( $trainer, $contract );
    $msg     = $verdict['message'];
    if ( ! $verdict['ok'] ) {
      $is_admin_ctx = isset( $_POST['page'] ) && 'acdc-of-trainers' === $_POST['page'];
      $redirect = $is_admin_ctx
        ? admin_url( 'admin.php?page=acdc-of-trainers&action=edit&item_id=' . $trainer_id . '&notice=' . rawurlencode( $msg ) . '&notice_type=error' )
        : $this->portal_page_url( array( 'tab' => 'trainers', 'action' => 'edit', 'item_id' => $trainer_id, 'notice' => rawurlencode( $msg ), 'notice_type' => 'error' ) );
      wp_safe_redirect( $redirect . '#acdc-tc-section' );
      exit;
    }
    $is_admin_ctx = isset( $_POST['page'] ) && 'acdc-of-trainers' === $_POST['page'];
    $redirect = $is_admin_ctx
      ? admin_url( 'admin.php?page=acdc-of-trainers&action=edit&item_id=' . $trainer_id . '&notice=' . rawurlencode( $msg ) . '&notice_type=success' )
      : $this->portal_page_url( array( 'tab' => 'trainers', 'action' => 'edit', 'item_id' => $trainer_id, 'notice' => rawurlencode( $msg ), 'notice_type' => 'success' ) );
    wp_safe_redirect( $redirect . '#acdc-tc-section' );
    exit;
  }

  /**
   * ACDC 3.22.3 — Callback déclenché par do_action('acdc_sig_request_signed', $request_id, $request).
   * Retrouve le contrat formateur lié, régénère le PDF avec signature manuscrite, met à jour le statut.
   */
  public function handle_trainer_contract_signed( $request_id, $request ) {
    $request_id = absint( $request_id );
    if ( ! $request_id ) {
      return;
    }
    global $wpdb;
    $notes = json_decode( isset( $request->notes ) ? (string) $request->notes : '', true );
    if ( ! is_array( $notes ) || 'trainer_contract' !== ( isset( $notes['entity_type'] ) ? $notes['entity_type'] : '' ) ) {
      return;
    }
    $contract_id = ! empty( $notes['trainer_contract_id'] ) ? absint( $notes['trainer_contract_id'] ) : 0;
    $trainer_id  = ! empty( $notes['trainer_id'] )          ? absint( $notes['trainer_id'] )          : 0;
    if ( ! $contract_id || ! $trainer_id ) {
      return;
    }
    $contract = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->trainer_contract_table} WHERE id = %d AND trainer_id = %d", $contract_id, $trainer_id ) );
    $trainer  = $this->get_trainer( $trainer_id );
    if ( ! $contract || ! $trainer ) {
      return;
    }
    // Chercher la signature manuscrite PNG dans le répertoire ACDC_Sig
    $upload_dir = wp_upload_dir();
    $sig_dir    = trailingslashit( $upload_dir['basedir'] ) . 'acdc-signatures/' . $request_id . '/';
    $sig_files  = glob( $sig_dir . 'signature-*.png' );
    if ( ! empty( $sig_files ) ) { rsort( $sig_files ); }
    $sig_img_path = ! empty( $sig_files ) ? (string) $sig_files[0] : '';
    /* ACDC 3.25.227 — L'horodatage est posé AVANT la régénération du PDF.
       C'est ce qui permet au document de porter sa propre preuve : le bloc
       « Signée électroniquement le … » lit cette valeur. L'ordre n'est pas un
       détail — l'écrire après aurait produit un PDF muet, et il aurait fallu
       le régénérer une seconde fois pour qu'il dise la vérité. */
    $signature_completed_at = current_time( 'mysql' );
    if ( $this->acdc_schema_has_column( $this->trainer_contract_table, 'signature_completed_at' ) ) {
      $wpdb->update(
        $this->trainer_contract_table,
        array( 'signature_completed_at' => $signature_completed_at ),
        array( 'id' => $contract_id ),
        array( '%s' ),
        array( '%d' )
      );
      $contract->signature_completed_at = $signature_completed_at;
    }

    // ── ACDC 3.22.5 — Régénérer le PDF avec la signature manuscrite apposée ──
    $pages = $this->build_trainer_contract_pdf_pages( $trainer, $contract, $sig_img_path );
    $pdf_c = '';
    if ( class_exists( 'ACDC_Sig_Core' ) && class_exists( 'ACDC_Sig_PDF' ) ) {
      $sc = new ACDC_Sig_Core(); $sc->init_tables();
      $sp = new ACDC_Sig_PDF( $sc );
      $rf = new ReflectionClass( $sp ); $m = $rf->getMethod( 'render_to_string' ); $m->setAccessible( true );
      $pdf_c = $m->invoke( $sp, $pages );
    }
    if ( '' === $pdf_c && method_exists( $this, '_build_simple_pdf_string' ) ) { $pdf_c = $this->_build_simple_pdf_string( $pages ); }
    $signed_url = ''; $signed_path = '';
    if ( '' !== $pdf_c ) {
      /* ACDC 3.25.148 — F11 : même protection que le contrat non signé
         (nom non devinable + dossier interdit d'accès direct). */
      $safe_name   = $this->acdc_trainer_contract_filename( $trainer_id, $contract_id, '-signe' );
      $dir_path    = trailingslashit( $upload_dir['basedir'] ) . 'acdc-of-contracts/' . $contract_id . '/';
      $signed_path = $dir_path . $safe_name;
      $signed_url  = trailingslashit( $upload_dir['baseurl'] ) . 'acdc-of-contracts/' . $contract_id . '/' . $safe_name;
      $this->acdc_protect_contracts_dir( $dir_path );
      file_put_contents( $signed_path, $pdf_c ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    }
    $wpdb->update( $this->trainer_contract_table, array(
      'signature_status'    => 'signée',
      'signed_document_url' => $signed_url ? esc_url_raw( $signed_url ) : (string) $contract->contract_pdf_url,
      'contract_pdf_url'    => $signed_url ? esc_url_raw( $signed_url ) : (string) $contract->contract_pdf_url,
    ), array( 'id' => $contract_id ), array( '%s', '%s', '%s' ), array( '%d' ) );
    $tr_name_s = trim( (string) $trainer->first_name . ' ' . (string) $trainer->last_name );
    $signed_at = wp_date( 'd/m/Y à H:i' );
    $profile_s = $this->get_company_profile_options();
    $from_name_s  = ! empty( $profile_s['enterprise_contact_name'] )  ? sanitize_text_field( (string) $profile_s['enterprise_contact_name'] )  : get_bloginfo( 'name' );
    $from_email_s = ! empty( $profile_s['enterprise_contact_email'] ) ? sanitize_email( (string) $profile_s['enterprise_contact_email'] )       : sanitize_email( (string) get_option( 'admin_email' ) );
    /* ACDC 3.25.160 — L'exemplaire contresigné du contrat formateur part d'ICI, et
       non du module signature : les en-têtes ajoutés là-bas ne l'atteignaient donc
       pas, et la recette l'a vu retomber sur « plugin / wp_mail » alors que les
       deux autres e-mails du parcours étaient qualifiés. Ces deux envois — copie
       organisme et copie formateur — portent désormais leur attribution. */
    /* ACDC 3.25.227 — Deux e-mails partaient à la même seconde sous la MÊME
       source « signature / signed_copy » : l'accusé de réception émis par le
       module de signature, et l'exemplaire contresigné émis ici. Dans la boîte
       du formateur comme dans l'archive, cela se lit comme un doublon. Ce sont
       deux messages différents ; ils portent désormais deux attributions
       différentes. */
    $attr_s = array(
      'source_module'       => 'signature',
      'source_action'       => 'trainer_contract_countersigned_copy',
      'email_category'      => 'signature',
      'related_entity_type' => 'trainer_contract',
      'related_entity_id'   => (string) (int) $contract_id,
    );
    $attachment_s = ( '' !== $signed_path && file_exists( $signed_path ) ) ? array( $signed_path ) : array();
    // Notifier l'organisme (admin)
    $admin_email = sanitize_email( (string) get_option( 'admin_email' ) );
    if ( '' !== $admin_email && is_email( $admin_email ) ) {
      $subj_admin = '✅ Contrat formateur signé — ' . $tr_name_s;
      /* ACDC 3.25.246 — Gabarit commun. */
      $this->acdc_send_transactional_email( $admin_email, $subj_admin, array(
        'intro_html' => '<p>Le contrat de sous-traitance de <strong>' . esc_html( $tr_name_s ) . '</strong> a été signé électroniquement le <strong>' . esc_html( $signed_at ) . '</strong>.</p>',
        'body_html'  => '<p>Le document signé est joint à cet e-mail.</p>',
      ), $attr_s, $attachment_s );
    }
    // Envoyer le PDF signé au formateur
    $tr_email_s = sanitize_email( (string) $trainer->email );
    if ( '' !== $tr_email_s && is_email( $tr_email_s ) ) {
      $subj_tr = '📄 Votre exemplaire signé — contrat de sous-traitance';
      $this->acdc_send_transactional_email( $tr_email_s, $subj_tr, array(
        'greeting_name' => $tr_name_s,
        'intro_html'    => '<p>Votre contrat de sous-traitance a été signé le <strong>' . esc_html( $signed_at ) . '</strong>. Vous trouverez votre exemplaire contresigné en pièce jointe.</p>',
        'body_html'     => '<p>Conservez ce document pour vos archives.</p>',
        'footer_notice' => 'Cet e-mail vous est adressé dans le cadre de votre mission de formation. Vos données sont traitées conformément au RGPD.',
      ), $attr_s, $attachment_s );
    }
  }

  /**
   * Crée (ou retrouve) un compte dans wp_acdc_of_trainer_portal_accounts, génère un token
   * d'activation valable 7 jours et envoie un e-mail via le template ACDC central.
   *
   * Idempotent : si le formateur a déjà un compte actif, regénère et renvoie un nouveau lien.
   */
  /**
   * ACDC 3.20.93 — Test manuel d'envoi des alertes Qualiopi pour un formateur.
   * Ignore le log anti-doublon (utile pour valider les templates en prod sans attendre le cron).
   * Sécurisé par nonce + capability admin.
   */
  public function handle_qualiopi_test_alert() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $trainer_id = isset( $_POST['trainer_id'] ) ? absint( wp_unslash( $_POST['trainer_id'] ) ) : 0;
    if ( ! $trainer_id ) {
      $this->redirect_to_portal( 'trainers', 'Formateur introuvable.', 'error' );
    }
    check_admin_referer( 'acdc_qualiopi_test_' . $trainer_id );

    $result = $this->acdc_qualiopi_run_test_for_trainer( $trainer_id );

    if ( $result['items'] === 0 ) {
      $message = 'Aucune date d’expiration aux seuils J-30, J-7 ou J-0 pour ce formateur. Test non envoyé.';
      $type = 'error';
    } elseif ( $result['sent'] === 0 ) {
      $message = 'Échec de l’envoi des e-mails. Vérifiez la configuration SMTP du site.';
      $type = 'error';
    } else {
      $message = sprintf(
        '%d alerte(s) test envoyée(s) à : %s.',
        (int) $result['sent'],
        implode( ', ', array_map( 'sanitize_email', $result['recipients'] ) )
      );
      $type = 'success';
    }

    $target = is_admin() && isset( $_POST['page'] ) && 'acdc-of-trainers' === $_POST['page']
      ? admin_url( 'admin.php?page=acdc-of-trainers&action=edit&item_id=' . $trainer_id . '&notice=' . rawurlencode( $message ) . '&notice_type=' . $type )
      : $this->portal_page_url( array( 'tab' => 'trainers', 'action' => 'edit', 'item_id' => $trainer_id, 'notice' => rawurlencode( $message ), 'notice_type' => $type ) );
    wp_safe_redirect( $target );
    exit;
  }

  public function handle_invite_trainer() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $trainer_id = isset( $_POST['trainer_id'] ) ? absint( wp_unslash( $_POST['trainer_id'] ) ) : 0;
    if ( ! $trainer_id ) {
      $this->redirect_to_portal( 'trainers', 'Formateur introuvable.', 'error' );
    }
    check_admin_referer( 'acdc_invite_trainer_' . $trainer_id );

    global $wpdb;
    $trainer = $this->get_trainer( $trainer_id );
    if ( ! $trainer ) {
      $this->redirect_to_portal( 'trainers', 'Formateur introuvable.', 'error' );
    }

    $email = sanitize_email( (string) $trainer->email );
    if ( '' === $email || ! is_email( $email ) ) {
      $this->redirect_back_to_trainer_edit( $trainer_id, 'L’adresse e-mail du formateur est invalide ou manquante. Renseignez-la avant d’inviter.', 'error' );
      return;
    }

    $now = current_time( 'mysql' );

    // 1. Vérifier qu'aucun compte avec cet e-mail n'existe déjà pour un autre formateur.
    $existing_email = $wpdb->get_row( $wpdb->prepare(
      "SELECT * FROM {$this->trainer_portal_account_table} WHERE email = %s",
      $email
    ) );
    if ( $existing_email && (int) $existing_email->trainer_id !== (int) $trainer_id ) {
      $this->redirect_back_to_trainer_edit( $trainer_id, 'Cet e-mail est déjà associé à un autre formateur. Vérifiez la fiche concernée.', 'error' );
      return;
    }

    // 2. Récupérer (ou créer) le compte associé à ce formateur.
    $account = $wpdb->get_row( $wpdb->prepare(
      "SELECT * FROM {$this->trainer_portal_account_table} WHERE trainer_id = %d",
      (int) $trainer_id
    ) );

    if ( ! $account ) {
      $wpdb->insert(
        $this->trainer_portal_account_table,
        array(
          'trainer_id'           => (int) $trainer_id,
          'email'                => $email,
          'password_hash'        => wp_hash_password( wp_generate_password( 32, true, true ) ), // placeholder, sera réécrit à l'activation
          'status'               => 'never_activated',
          'must_change_password' => 1,
          'created_at'           => $now,
          'updated_at'           => $now,
        ),
        array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
      );
      $account_id = (int) $wpdb->insert_id;
      $account    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->trainer_portal_account_table} WHERE id = %d", $account_id ) );
    } else {
      // E-mail à jour si la fiche formateur a été modifiée depuis.
      if ( $account->email !== $email ) {
        $wpdb->update(
          $this->trainer_portal_account_table,
          array( 'email' => $email, 'updated_at' => $now ),
          array( 'id' => (int) $account->id )
        );
        $account->email = $email;
      }
    }

    if ( ! $account ) {
      $this->redirect_back_to_trainer_edit( $trainer_id, 'Erreur technique lors de la création du compte. Réessayez.', 'error' );
      return;
    }

    // 3. Tracer l'invitation côté formateur.
    $wpdb->update(
      $this->trainer_table,
      array( 'invited_at' => $now, 'updated_at' => $now ),
      array( 'id' => (int) $trainer_id )
    );

    // 4. Envoyer l'e-mail d'activation via le template ACDC central.
    $sent = $this->trainer_portal_send_activation_email( $account, $trainer );

    // 5. Tracer l'invitation côté logs.
    $this->trainer_portal_log_event( (int) $account->id, 'invitation_sent', array( 'sent' => (bool) $sent ), (int) $trainer_id );

    if ( $sent ) {
      $this->redirect_back_to_trainer_edit( $trainer_id, 'Invitation envoyée à ' . $email . '.', 'success' );
    } else {
      $this->redirect_back_to_trainer_edit( $trainer_id, 'Compte créé, mais l’envoi de l’e-mail a échoué. Vérifiez la configuration SMTP du site.', 'error' );
    }
  }

  /**
   * ACDC 3.25.169 — Affiche le lien d'activation d'un accès formateur, sans e-mail.
   *
   * Le pendant exact de « Obtenir le lien d'activation » côté apprenant. Le portail
   * formateur a la même mécanique : un unique lien envoyé par courriel. Si cet
   * envoi échoue, le compte reste « jamais activé » et la page de connexion oppose
   * un refus qui renvoie à un e-mail inexistant. On donne donc au gestionnaire
   * déjà authentifié le lien qu'il aurait relayé depuis sa messagerie.
   *
   * @return void
   */
  public function handle_open_trainer_extranet_access() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $trainer_id = isset( $_POST['trainer_id'] ) ? absint( wp_unslash( $_POST['trainer_id'] ) ) : 0;
    if ( ! $trainer_id ) {
      $this->redirect_to_portal( 'trainers', 'Formateur introuvable.', 'error' );
    }
    check_admin_referer( 'acdc_open_trainer_access_' . $trainer_id );

    global $wpdb;
    $trainer = $this->get_trainer( $trainer_id );
    if ( ! $trainer ) {
      $this->redirect_to_portal( 'trainers', 'Formateur introuvable.', 'error' );
    }

    $account = $wpdb->get_row( $wpdb->prepare(
      "SELECT * FROM {$this->trainer_portal_account_table} WHERE trainer_id = %d",
      (int) $trainer_id
    ) );
    if ( ! $account ) {
      $this->redirect_back_to_trainer_edit( $trainer_id, "Aucun compte formateur n'existe encore. Cliquez d'abord sur « Inviter le formateur ».", 'error' );
      return;
    }

    $activation_url = method_exists( $this, 'trainer_portal_build_activation_url' )
      ? $this->trainer_portal_build_activation_url( $account )
      : '';
    if ( '' === $activation_url ) {
      $this->redirect_back_to_trainer_edit( $trainer_id, "Le lien d'activation n'a pas pu être généré.", 'error' );
      return;
    }

    $this->trainer_portal_log_event( (int) $account->id, 'activation_link_revealed', array( 'by_admin' => true ), (int) $trainer_id );

    $return_url = is_admin()
      ? admin_url( 'admin.php?page=acdc-of-trainers&action=edit&item_id=' . (int) $trainer_id )
      : $this->portal_page_url( array( 'tab' => 'trainers', 'action' => 'edit', 'item_id' => (int) $trainer_id ) );

    $this->acdc_render_learner_activation_link_page( (string) $account->email, $activation_url, $return_url, 'formateur' );
    exit;
  }

  /**
   * ACDC 3.20.82 — Helper : redirige vers la page d’édition du formateur, admin ou portail selon contexte.
   */
  private function redirect_back_to_trainer_edit( $trainer_id, $message, $type = 'success' ) {
    $is_admin_ctx = is_admin() && isset( $_POST['page'] ) && 'acdc-of-trainers' === $_POST['page'];
    if ( $is_admin_ctx ) {
      $target = admin_url( 'admin.php?page=acdc-of-trainers&action=edit&item_id=' . (int) $trainer_id . '&notice=' . rawurlencode( $message ) . '&notice_type=' . $type );
      wp_safe_redirect( $target );
      exit;
    }
    $this->redirect_to_portal( 'trainers', $message, $type, array( 'action' => 'edit', 'item_id' => (int) $trainer_id ) );
  }

  /**
   * ACDC 3.20.86 — Helper : retire les accents d'une chaîne pour générer un username propre.
   */
  private function acdc_strip_accents( $str ) {
    $str = (string) $str;
    if ( function_exists( 'iconv' ) ) {
      $converted = @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $str );
      if ( false !== $converted ) {
        return preg_replace( '/[^a-z0-9]/i', '', $converted );
      }
    }
    return preg_replace( '/[^a-z0-9]/i', '', $str );
  }

  /* ====================================================================
   * ACDC 3.20.86 — Handlers de la bibliothèque personnelle du formateur (côté admin).
   * ==================================================================== */

  /**
   * Upload d'un nouveau document pour un formateur.
   * - Vérification capability admin + nonce
   * - Validation : trainer existe, catégorie valide, fichier conforme (taille, mime)
   * - Stockage dans wp-content/uploads/acdc-of/trainer-documents/{trainer_id}/
   * - Insertion en BD avec chemin RELATIF
   */
  public function handle_admin_upload_trainer_document() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $trainer_id = isset( $_POST['trainer_id'] ) ? absint( wp_unslash( $_POST['trainer_id'] ) ) : 0;
    if ( ! $trainer_id ) {
      $this->redirect_to_portal( 'trainers', 'Formateur introuvable.', 'error' );
    }
    check_admin_referer( 'acdc_upload_trainer_document_' . $trainer_id );

    $trainer = $this->get_trainer( $trainer_id );
    if ( ! $trainer ) {
      $this->redirect_back_to_trainer_edit( $trainer_id, 'Formateur introuvable.', 'error' );
      return;
    }

    // 1. Validation catégorie.
    $category = isset( $_POST['category'] ) ? sanitize_key( wp_unslash( $_POST['category'] ) ) : '';
    $cats     = $this->get_acdc_trainer_document_categories();
    if ( '' === $category || ! isset( $cats[ $category ] ) ) {
      $this->redirect_back_to_trainer_edit( $trainer_id, 'Catégorie de document invalide.', 'error' );
      return;
    }

    // 2. Validation fichier.
    if ( empty( $_FILES['document_file'] ) || empty( $_FILES['document_file']['name'] ) || UPLOAD_ERR_OK !== (int) $_FILES['document_file']['error'] ) {
      $error_code = isset( $_FILES['document_file']['error'] ) ? (int) $_FILES['document_file']['error'] : -1;
      $msg = ( UPLOAD_ERR_INI_SIZE === $error_code || UPLOAD_ERR_FORM_SIZE === $error_code )
        ? 'Le fichier dépasse la taille maximale autorisée par la configuration serveur. Demandez à votre hébergeur d’augmenter `upload_max_filesize` et `post_max_size` à 100M minimum.'
        : 'Aucun fichier valide reçu. Vérifiez que vous avez bien sélectionné un fichier avant d’envoyer.';
      $this->redirect_back_to_trainer_edit( $trainer_id, $msg, 'error' );
      return;
    }

    $file_name_orig = sanitize_file_name( (string) $_FILES['document_file']['name'] );
    $file_size      = (int) $_FILES['document_file']['size'];
    $file_tmp       = (string) $_FILES['document_file']['tmp_name'];

    if ( $file_size > $this->get_acdc_trainer_document_max_size_bytes() ) {
      $this->redirect_back_to_trainer_edit( $trainer_id, 'Le fichier dépasse 100 Mo.', 'error' );
      return;
    }
    if ( ! is_uploaded_file( $file_tmp ) ) {
      $this->redirect_back_to_trainer_edit( $trainer_id, 'Erreur d’upload : fichier temporaire introuvable.', 'error' );
      return;
    }

    // 3. Validation extension + mime.
    $allowed_mimes = $this->get_acdc_trainer_document_allowed_mimes();
    $ext           = strtolower( pathinfo( $file_name_orig, PATHINFO_EXTENSION ) );
    if ( '' === $ext || ! isset( $allowed_mimes[ $ext ] ) ) {
      $this->redirect_back_to_trainer_edit( $trainer_id, 'Format de fichier non autorisé. Formats acceptés : PDF, JPG, PNG, DOC, DOCX, PPTX.', 'error' );
      return;
    }
    $check = wp_check_filetype_and_ext( $file_tmp, $file_name_orig, $allowed_mimes );
    if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
      $this->redirect_back_to_trainer_edit( $trainer_id, 'Le contenu du fichier ne correspond pas à son extension. Upload refusé.', 'error' );
      return;
    }

    // 4. Préparation du dossier {trainer_id}/.
    $dirs = $this->ensure_trainer_documents_upload_dir();
    if ( ! $dirs ) {
      $this->redirect_back_to_trainer_edit( $trainer_id, 'Impossible de préparer le dossier d’upload.', 'error' );
      return;
    }
    $trainer_dir = trailingslashit( $dirs['base_dir'] ) . (int) $trainer_id;
    if ( ! file_exists( $trainer_dir ) ) {
      wp_mkdir_p( $trainer_dir );
    }

    // 5. Nom de fichier sécurisé : {category}_{timestamp}_{slug}.{ext}
    $base_slug = pathinfo( $file_name_orig, PATHINFO_FILENAME );
    $base_slug = $this->acdc_strip_accents( $base_slug );
    $base_slug = preg_replace( '/[^a-zA-Z0-9_-]+/', '-', $base_slug );
    $base_slug = trim( strtolower( (string) $base_slug ), '-' );
    if ( '' === $base_slug ) {
      $base_slug = 'doc';
    }
    $base_slug   = substr( $base_slug, 0, 80 );
    $stored_name = $category . '_' . time() . '_' . $base_slug . '.' . $ext;
    $stored_full = trailingslashit( $trainer_dir ) . $stored_name;

    if ( ! @move_uploaded_file( $file_tmp, $stored_full ) ) {
      $this->redirect_back_to_trainer_edit( $trainer_id, 'Erreur lors du déplacement du fichier. Vérifiez les droits sur wp-content/uploads/.', 'error' );
      return;
    }
    @chmod( $stored_full, 0644 );

    // 6. Insertion en BD.
    global $wpdb;
    $now = current_time( 'mysql' );
    $relative_path = (int) $trainer_id . '/' . $stored_name;
    $label         = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
    if ( '' === $label ) {
      $label = $cats[ $category ]['label'];
    }
    $issued_at  = isset( $_POST['issued_at'] ) && '' !== $_POST['issued_at'] ? sanitize_text_field( wp_unslash( $_POST['issued_at'] ) ) : null;
    $expires_at = isset( $_POST['expires_at'] ) && '' !== $_POST['expires_at'] ? sanitize_text_field( wp_unslash( $_POST['expires_at'] ) ) : null;
    $is_qualiopi = ! empty( $_POST['is_qualiopi_proof'] ) ? 1 : 0;
    $notes_admin = isset( $_POST['notes_admin'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes_admin'] ) ) : '';

    $wpdb->insert(
      $this->trainer_document_table,
      array(
        'trainer_id'        => (int) $trainer_id,
        'category'          => $category,
        'label'             => $label,
        'file_url'          => $relative_path,
        'file_name'         => $file_name_orig,
        'issued_at'         => $issued_at,
        'expires_at'        => $expires_at,
        'uploaded_by'       => 'admin',
        'uploader_user_id'  => (int) get_current_user_id(),
        'is_qualiopi_proof' => $is_qualiopi,
        'notes_admin'       => $notes_admin,
        'created_at'        => $now,
        'updated_at'        => $now,
      )
    );

    $this->redirect_back_to_trainer_edit( $trainer_id, 'Document ajouté à la bibliothèque.', 'success' );
  }

  /**
   * Suppression d'un document (hard delete : BD + fichier physique).
   */
  public function handle_admin_delete_trainer_document() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $document_id = isset( $_GET['document_id'] ) ? absint( wp_unslash( $_GET['document_id'] ) ) : 0;
    if ( ! $document_id ) {
      $this->redirect_to_portal( 'trainers', 'Document introuvable.', 'error' );
    }
    check_admin_referer( 'acdc_delete_trainer_document_' . $document_id );

    global $wpdb;
    $doc = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->trainer_document_table} WHERE id = %d", $document_id ) );
    if ( ! $doc ) {
      $this->redirect_to_portal( 'trainers', 'Document introuvable.', 'error' );
    }

    // 1. Suppression du fichier physique (avec validation chemin).
    $abs = $this->get_trainer_document_absolute_path( (string) $doc->file_url );
    if ( $abs && file_exists( $abs ) ) {
      @unlink( $abs );
    }

    // 2. Suppression BD.
    $wpdb->delete( $this->trainer_document_table, array( 'id' => (int) $document_id ) );

    $this->redirect_back_to_trainer_edit( (int) $doc->trainer_id, 'Document supprimé.', 'success' );
  }

  /**
   * ACDC 3.20.94 — Mise à jour des métadonnées d'un document de la bibliothèque formateur.
   *
   * Champs éditables : category, label, issued_at, expires_at, is_qualiopi_proof, notes_admin.
   * Champs intouchés : file_url, file_name, trainer_id, uploaded_by, uploader_user_id, created_at.
   * Le fichier physique n'est pas remplaçable depuis cette action — pour le remplacer,
   * supprimer puis ré-uploader (évite les incohérences entre URL stockée et contenu réel).
   *
   * Sécurité : capability manage_options + nonce dédié au document_id.
   * Sanitisation : whitelist sur catégorie, validation format DATE sur les deux dates.
   */
  public function handle_admin_update_trainer_document() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $document_id = isset( $_POST['document_id'] ) ? absint( wp_unslash( $_POST['document_id'] ) ) : 0;
    if ( ! $document_id ) {
      $this->redirect_to_portal( 'trainers', 'Document introuvable.', 'error' );
    }
    check_admin_referer( 'acdc_update_trainer_document_' . $document_id );

    global $wpdb;
    $doc = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->trainer_document_table} WHERE id = %d", $document_id ) );
    if ( ! $doc ) {
      $this->redirect_to_portal( 'trainers', 'Document introuvable.', 'error' );
    }

    // Sanitisation des entrées.
    $cats_def = $this->get_acdc_trainer_document_categories();
    $allowed_cats = array_keys( (array) $cats_def );
    $cat_in = isset( $_POST['category'] ) ? sanitize_key( wp_unslash( $_POST['category'] ) ) : '';
    if ( ! in_array( $cat_in, $allowed_cats, true ) ) {
      $cat_in = (string) $doc->category; // Conserve l'existant si valeur invalide.
    }

    $label_in = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';

    $issued_in = isset( $_POST['issued_at'] ) ? sanitize_text_field( wp_unslash( $_POST['issued_at'] ) ) : '';
    if ( '' !== $issued_in && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $issued_in ) ) {
      $issued_in = '';
    }

    $expires_in = isset( $_POST['expires_at'] ) ? sanitize_text_field( wp_unslash( $_POST['expires_at'] ) ) : '';
    if ( '' !== $expires_in && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expires_in ) ) {
      $expires_in = '';
    }

    $is_qualiopi_in = ! empty( $_POST['is_qualiopi_proof'] ) ? 1 : 0;

    $notes_in = isset( $_POST['notes_admin'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes_admin'] ) ) : '';

    $update_data = array(
      'category'          => $cat_in,
      'label'             => $label_in,
      'issued_at'         => '' !== $issued_in ? $issued_in : null,
      'expires_at'        => '' !== $expires_in ? $expires_in : null,
      'is_qualiopi_proof' => $is_qualiopi_in,
      'notes_admin'       => $notes_in,
      'updated_at'        => $this->now_mysql(),
    );

    $result = $wpdb->update(
      $this->trainer_document_table,
      $update_data,
      array( 'id' => (int) $document_id )
    );

    if ( false === $result ) {
      $this->redirect_back_to_trainer_edit( (int) $doc->trainer_id, 'Erreur lors de la mise à jour du document.', 'error' );
      return;
    }

    $this->redirect_back_to_trainer_edit( (int) $doc->trainer_id, 'Document mis à jour.', 'success' );
  }

  /**
   * Téléchargement sécurisé d'un document.
   * Capability admin obligatoire. Validation du chemin pour éviter path traversal.
   */
  public function handle_admin_download_trainer_document() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $document_id = isset( $_GET['document_id'] ) ? absint( wp_unslash( $_GET['document_id'] ) ) : 0;
    if ( ! $document_id ) {
      wp_die( esc_html( 'Document introuvable.' ) );
    }
    check_admin_referer( 'acdc_download_trainer_document_' . $document_id );

    global $wpdb;
    $doc = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->trainer_document_table} WHERE id = %d", $document_id ) );
    if ( ! $doc ) {
      wp_die( esc_html( 'Document introuvable.' ) );
    }

    $abs = $this->get_trainer_document_absolute_path( (string) $doc->file_url );
    if ( ! $abs || ! file_exists( $abs ) ) {
      wp_die( esc_html( 'Fichier introuvable sur le serveur.' ) );
    }

    $filename = ! empty( $doc->file_name ) ? (string) $doc->file_name : basename( $abs );
    $mime     = function_exists( 'mime_content_type' ) ? @mime_content_type( $abs ) : '';
    if ( ! $mime ) {
      $allowed = $this->get_acdc_trainer_document_allowed_mimes();
      $ext     = strtolower( pathinfo( $abs, PATHINFO_EXTENSION ) );
      $mime    = isset( $allowed[ $ext ] ) ? $allowed[ $ext ] : 'application/octet-stream';
    }

    nocache_headers();
    header( 'Content-Type: ' . $mime );
    header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $filename ) . '"' );
    header( 'Content-Length: ' . filesize( $abs ) );
    header( 'X-Content-Type-Options: nosniff' );
    readfile( $abs );
    exit;
  }

  public function handle_save_need_analysis() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  check_admin_referer( 'acdc_save_need_analysis' );
  global $wpdb;
  $analysis_id = isset( $_POST['analysis_id'] ) ? absint( wp_unslash( $_POST['analysis_id'] ) ) : 0;
  $is_admin_page = is_admin() && isset( $_POST['page'] ) && 'acdc-of-need-analyses' === $_POST['page'];
  $input = isset( $_POST['analysis'] ) && is_array( $_POST['analysis'] ) ? wp_unslash( $_POST['analysis'] ) : array();
  $title = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
  $description = isset( $input['description_text'] ) ? sanitize_textarea_field( $input['description_text'] ) : '';
  $analysis_type = isset( $input['analysis_type'] ) ? sanitize_text_field( $input['analysis_type'] ) : 'Entreprise';
  $is_model = $analysis_id ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT is_model FROM {$this->need_analysis_table} WHERE id = %d", $analysis_id ) ) : 0;
  $blocks_in = isset( $_POST['blocks'] ) && is_array( $_POST['blocks'] ) ? wp_unslash( $_POST['blocks'] ) : array();
  $blocks = $this->normalize_need_analysis_blocks( $blocks_in );
  if ( '' === $title ) {
    if ( $is_admin_page ) {
      $target = admin_url( 'admin.php?page=acdc-of-need-analyses&notice=' . rawurlencode( "Veuillez renseigner l'intitulé." ) . '&notice_type=error' );
      if ( $analysis_id ) {
        $target = add_query_arg( array( 'action' => 'edit', 'item_id' => $analysis_id ), $target );
      } else {
        $target = add_query_arg( array( 'action' => 'new' ), $target );
      }
      wp_safe_redirect( $target );
      exit;
    }
    $this->redirect_to_portal( 'need_analyses', "Veuillez renseigner l'intitulé.", 'error', array( 'action' => $analysis_id ? 'edit' : 'new', 'item_id' => $analysis_id ) );
  }
  /* ACDC 3.21.12 — Blocs sélectionnés + réponses + notes. */
  $nad_blocs_ids_raw = isset( $_POST['nad_blocs_ids_json'] ) ? sanitize_text_field( wp_unslash( $_POST['nad_blocs_ids_json'] ) ) : '';
  $nad_blocs_ids_arr = array();
  if ( '' !== $nad_blocs_ids_raw ) {
    $decoded_ids = json_decode( $nad_blocs_ids_raw, true );
    if ( is_array( $decoded_ids ) ) { $nad_blocs_ids_arr = array_map( 'absint', $decoded_ids ); }
  }
  // Réponses (overrides manuels par code de question)
  $nad_reponses_raw = isset( $_POST['reponses'] ) && is_array( $_POST['reponses'] ) ? wp_unslash( $_POST['reponses'] ) : array();
  $nad_reponses     = array();
  foreach ( $nad_reponses_raw as $code => $val ) {
    $code_clean = sanitize_key( $code );
    if ( is_array( $val ) ) {
      $nad_reponses[ $code_clean ] = array_map( 'sanitize_text_field', $val );
    } else {
      $nad_reponses[ $code_clean ] = sanitize_textarea_field( (string) $val );
    }
  }
  $nad_notes_internes = isset( $input['notes_internes'] ) ? sanitize_textarea_field( $input['notes_internes'] ) : null;

  /* ACDC 3.21.11 — Sources liées. */
  $source_prospect_id = isset( $_POST['analysis']['source_type_prospect'] ) ? absint( wp_unslash( $_POST['analysis']['source_type_prospect'] ) ) : 0;
  $apprenant_id_post  = isset( $input['apprenant_id'] )  ? absint( $input['apprenant_id'] )  : 0;
  $entreprise_id_post = isset( $input['entreprise_id'] ) ? absint( $input['entreprise_id'] ) : 0;
  $formation_id_post  = isset( $input['formation_id'] )  ? absint( $input['formation_id'] )  : 0;
  $dossier_id_post    = isset( $input['dossier_id'] )    ? absint( $input['dossier_id'] )    : 0;
  $profil     = isset( $input['profil'] )       ? sanitize_text_field( $input['profil'] )       : null;
  $thematique = isset( $input['thematique'] )   ? sanitize_text_field( $input['thematique'] )   : null;
  $statut     = isset( $input['statut'] )       ? sanitize_text_field( $input['statut'] )       : 'brouillon';
  $rep_nom    = isset( $input['repondant_nom'] )    ? sanitize_text_field( $input['repondant_nom'] )    : null;
  $rep_prenom = isset( $input['repondant_prenom'] ) ? sanitize_text_field( $input['repondant_prenom'] ) : null;
  $rep_email  = isset( $input['repondant_email'] )  ? sanitize_email( $input['repondant_email'] )       : null;
  $valid_profils    = array( 'particulier', 'apprenant', 'salarie', 'entreprise', 'independant' );
  $valid_thematiques= array( 'ia', 'automatisation_nocode', 'marketing_wordpress', 'management_leadership', 'management_restauration', 'hygiene_alimentaire', 'softskills' );
  $valid_statuts    = array( 'brouillon', 'nouveau', 'a_traiter', 'traite', 'archive' );
  $profil_clean     = in_array( $profil,     $valid_profils,     true ) ? $profil     : null;
  $thematique_clean = in_array( $thematique, $valid_thematiques, true ) ? $thematique : null;
  $statut_clean     = in_array( $statut,     $valid_statuts,     true ) ? $statut     : 'brouillon';
  $data = array(
    'title'            => $title,
    'description_text' => $description,
    'analysis_type'    => in_array( $analysis_type, array( 'Entreprise', 'Particulier' ), true ) ? $analysis_type : 'Entreprise',
    'blocks_json'      => wp_json_encode( $blocks ),
    'is_model'         => $is_model,
    'profil'           => $profil_clean,
    'thematique'       => $thematique_clean,
    'statut'           => $statut_clean,
    'repondant_nom'    => $rep_nom    ?: null,
    'repondant_prenom' => $rep_prenom ?: null,
    'repondant_email'  => $rep_email  ?: null,
    /* ACDC 3.21.11 — Sources liées. */
    'source_type'   => $source_prospect_id ? 'prospect' : null,
    'source_id'     => $source_prospect_id ?: null,
    'apprenant_id'  => $apprenant_id_post  ?: null,
    'entreprise_id' => $entreprise_id_post ?: null,
    'formation_id'  => $formation_id_post  ?: null,
    'dossier_id'    => $dossier_id_post    ?: null,
    /* ACDC 3.21.12 — Blocs, réponses, notes. */
    'blocs_ids'      => ! empty( $nad_blocs_ids_arr ) ? wp_json_encode( $nad_blocs_ids_arr )   : null,
    'reponses'       => ! empty( $nad_reponses )      ? wp_json_encode( $nad_reponses, JSON_UNESCAPED_UNICODE ) : null,
    'notes_internes' => $nad_notes_internes ?: null,
  );
  $now = current_time( 'mysql' );
  if ( $analysis_id ) {
    $data['updated_at'] = $now;
    $result = $wpdb->update( $this->need_analysis_table, $data, array( 'id' => $analysis_id ) );
    $message = 'Analyse du besoin mise à jour.';
    $error_context = "Impossible de mettre à jour l'analyse du besoin.";
  } else {
    $data['created_at'] = $now;
    $data['updated_at'] = $now;
    $result = $wpdb->insert( $this->need_analysis_table, $data );
    $message = 'Analyse du besoin créée.';
    $error_context = "Impossible de créer l'analyse du besoin.";
    // ACDC 3.23.2 — NAD créée avec dossier_id → dossier passe à analyse_besoin_en_cours.
    if ( $result && $dossier_id_post ) {
      $this->advance_registration_workflow( $dossier_id_post, 'analyse_besoin_en_cours' );
    }
  }
  $this->redirect_with_db_result(
    $result,
    $message,
    $error_context,
    'need_analyses',
    $is_admin_page,
    'acdc-of-need-analyses',
    array(),
    ! empty( $_POST['save_and_add'] )
  );
}public function handle_delete_need_analysis() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  $analysis_id = isset( $_GET['analysis_id'] ) ? absint( wp_unslash( $_GET['analysis_id'] ) ) : 0;
  check_admin_referer( 'acdc_delete_need_analysis_' . $analysis_id );
  global $wpdb;
  if ( $analysis_id ) {
    $wpdb->delete( $this->need_analysis_table, array( 'id' => $analysis_id ) );
  }
  if ( is_admin() && isset( $_REQUEST['page'] ) && 'acdc-of-need-analyses' === $_REQUEST['page'] ) {
    wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-need-analyses&notice=' . rawurlencode( 'Analyse du besoin supprimée.' ) . '&notice_type=success' ) );
    exit;
  }
  $this->redirect_to_portal( 'need_analyses', 'Analyse du besoin supprimée.', 'success' );
}public function handle_create_need_analysis_from_model() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  check_admin_referer( 'acdc_create_need_analysis_from_model' );
  global $wpdb;
  $is_admin_page = is_admin() && isset( $_POST['page'] ) && 'acdc-of-need-analyses' === $_POST['page'];
  $profil_post    = isset( $_POST['analysis_profil'] )    ? sanitize_text_field( wp_unslash( $_POST['analysis_profil'] ) )    : '';
  $thematique_post= isset( $_POST['analysis_thematique'] ) ? sanitize_text_field( wp_unslash( $_POST['analysis_thematique'] ) ) : '';
  $formation_id   = isset( $_POST['analysis_formation_id'] ) ? absint( wp_unslash( $_POST['analysis_formation_id'] ) )         : 0;
  // Legacy : si ancien champ analysis_type envoyé, on le conserve pour rétrocompat.
  $type = isset( $_POST['analysis_type'] ) ? sanitize_text_field( wp_unslash( $_POST['analysis_type'] ) ) : '';
  $valid_profils = array( 'particulier', 'apprenant', 'salarie', 'entreprise', 'independant' );
  $valid_thematiques = array( 'ia', 'automatisation_nocode', 'marketing_wordpress', 'management_leadership', 'management_restauration', 'hygiene_alimentaire', 'softskills' );
  if ( '' === $profil_post && '' === $type ) {
    $this->redirect_to_nad( '', $is_admin_page, array( 'notice' => rawurlencode( 'Veuillez choisir un profil et une thématique.' ), 'notice_type' => 'error' ) );
    return;
  }
  $profil_clean    = in_array( $profil_post, $valid_profils, true ) ? $profil_post : null;
  $thematique_clean= in_array( $thematique_post, $valid_thematiques, true ) ? $thematique_post : null;
  // Titre lisible
  $profil_labels = array( 'particulier' => 'Particulier', 'apprenant' => 'Apprenant', 'salarie' => 'Salarié', 'entreprise' => 'Entreprise', 'independant' => 'Indépendant' );
  $them_labels   = array( 'ia' => 'IA', 'automatisation_nocode' => 'No-code', 'marketing_wordpress' => 'Marketing', 'management_leadership' => 'Management', 'management_restauration' => 'Mgmt Resto', 'hygiene_alimentaire' => 'Hygiène', 'softskills' => 'Soft skills' );
  $title_parts   = array_filter( array( 'Analyse du besoin', isset( $profil_labels[ $profil_clean ] ) ? $profil_labels[ $profil_clean ] : $type, isset( $them_labels[ $thematique_clean ] ) ? $them_labels[ $thematique_clean ] : '' ) );
  $generated_title = implode( ' — ', $title_parts );
  // Récupérer un modèle legacy si disponible
  $model = null;
  if ( '' !== $type ) {
    $model = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->need_analysis_table} WHERE is_model = 1 AND analysis_type = %s ORDER BY id ASC LIMIT 1", $type ) );
  }
  $now = current_time( 'mysql' );
  $wpdb->insert( $this->need_analysis_table, array(
    'title'            => $generated_title,
    'description_text' => $model ? $model->description_text : '',
    'analysis_type'    => $profil_clean ? ucfirst( $profil_clean ) : $type,
    'blocks_json'      => $model ? $model->blocks_json : wp_json_encode( array() ),
    'is_model'         => 0,
    'profil'           => $profil_clean,
    'thematique'       => $thematique_clean,
    'statut'           => 'brouillon',
    'formation_id'     => $formation_id ?: null,
    /* ACDC 3.21.11 — Sources liées depuis la modale. */
    'source_type'   => isset( $_POST['modal_source_prospect'] ) && absint( wp_unslash( $_POST['modal_source_prospect'] ) ) ? 'prospect' : null,
    'source_id'     => isset( $_POST['modal_source_prospect'] ) ? absint( wp_unslash( $_POST['modal_source_prospect'] ) ) ?: null : null,
    'apprenant_id'  => isset( $_POST['modal_apprenant_id'] )  ? absint( wp_unslash( $_POST['modal_apprenant_id'] ) )  ?: null : null,
    'entreprise_id' => isset( $_POST['modal_entreprise_id'] ) ? absint( wp_unslash( $_POST['modal_entreprise_id'] ) ) ?: null : null,
    'created_at'    => $now,
    'updated_at'    => $now,
  ) );
  $new_id = (int) $wpdb->insert_id;
  if ( ! $new_id ) {
    if ( $is_admin_page ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-need-analyses&notice=' . rawurlencode( 'Erreur lors de la création.' ) . '&notice_type=error' ) );
      exit;
    }
    $this->redirect_to_portal( 'need_analyses', 'Erreur lors de la création.', 'error' );
  }
  $target = is_admin() && isset( $_POST['page'] ) && 'acdc-of-need-analyses' === $_POST['page'] ? admin_url( 'admin.php?page=acdc-of-need-analyses&action=edit&item_id=' . $new_id . '&notice=' . rawurlencode( 'Analyse créée depuis le modèle.' ) . '&notice_type=success' ) : $this->portal_page_url( array( 'tab' => 'need_analyses', 'action' => 'edit', 'item_id' => $new_id, 'notice' => rawurlencode( 'Analyse créée depuis le modèle.' ), 'notice_type' => 'success' ) );
  wp_safe_redirect( $target );
  exit;
}public function handle_save_need() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }

  check_admin_referer( 'acdc_save_need' );

  $need_id = isset( $_POST['need_id'] ) ? absint( $_POST['need_id'] ) : 0;
  $input  = isset( $_POST['need'] ) && is_array( $_POST['need'] ) ? wp_unslash( $_POST['need'] ) : array();
  $source_prospect_id = isset( $input['source_prospect_id'] ) ? absint( $input['source_prospect_id'] ) : 0;
  /* M1 — L'e-mail au prospect n'est plus envoyé implicitement : il n'est envoyé que si
     l'interrupteur « Envoyer le recueil au prospect » est actif (coché par défaut). Champ
     absent (appel hérité) → on conserve l'envoi pour ne pas changer le comportement. */
  $notify_client = isset( $input['notify_client'] )
    ? ( '' !== (string) $input['notify_client'] && '0' !== (string) $input['notify_client'] )
    : true;

  $data = array(
    'company_id'     => isset( $input['company_id'] ) ? absint( $input['company_id'] ) : 0,
    'contact_id'     => isset( $input['contact_id'] ) ? absint( $input['contact_id'] ) : 0,
    'source_prospect_id' => $source_prospect_id ? (int) $source_prospect_id : null,
    'theme'        => isset( $input['theme'] ) ? sanitize_text_field( $input['theme'] ) : '',
    'collection_channel' => isset( $input['collection_channel'] ) ? sanitize_text_field( $input['collection_channel'] ) : '',
    'collection_date'   => isset( $input['collection_date'] ) ? sanitize_text_field( $input['collection_date'] ) : '',
    'acdc_interlocutor'  => isset( $input['acdc_interlocutor'] ) ? sanitize_text_field( $input['acdc_interlocutor'] ) : '',
    'status'       => isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'Nouveau',
    'expressed_need'   => isset( $input['expressed_need'] ) ? sanitize_textarea_field( $input['expressed_need'] ) : '',
    'reframed_need'    => isset( $input['reframed_need'] ) ? sanitize_textarea_field( $input['reframed_need'] ) : '',
    'context_text'    => isset( $input['context_text'] ) ? sanitize_textarea_field( $input['context_text'] ) : '',
    'current_situation'  => isset( $input['current_situation'] ) ? sanitize_textarea_field( $input['current_situation'] ) : '',
    'target_audience'   => isset( $input['target_audience'] ) ? sanitize_textarea_field( $input['target_audience'] ) : '',
    'audience_level'   => isset( $input['audience_level'] ) ? sanitize_text_field( $input['audience_level'] ) : '',
    'learners_count'   => isset( $input['learners_count'] ) ? sanitize_text_field( $input['learners_count'] ) : '',
    'formation_planning'   => isset( $input['formation_planning'] )   ? sanitize_text_field( $input['formation_planning'] )   : '',
    'formation_nb_groupes' => isset( $input['formation_nb_groupes'] ) ? sanitize_text_field( $input['formation_nb_groupes'] ) : '',
    'expected_objectives' => isset( $input['expected_objectives'] ) ? sanitize_textarea_field( $input['expected_objectives'] ) : '',
    'constraints_text'  => isset( $input['constraints_text'] ) ? sanitize_textarea_field( $input['constraints_text'] ) : '',
    'urgency'       => isset( $input['urgency'] ) ? sanitize_text_field( $input['urgency'] ) : '',
    'desired_deadline'  => isset( $input['desired_deadline'] ) ? sanitize_text_field( $input['desired_deadline'] ) : '',
    'budget'       => isset( $input['budget'] ) ? sanitize_text_field( $input['budget'] ) : '',
    'desired_format'   => isset( $input['desired_format'] ) ? sanitize_text_field( $input['desired_format'] ) : '',
    'planned_funding'   => isset( $input['planned_funding'] ) ? sanitize_text_field( $input['planned_funding'] ) : '',
    'funder_id'         => isset( $input['funder_id'] ) && absint( $input['funder_id'] ) ? absint( $input['funder_id'] ) : null,
    'next_action'     => isset( $input['next_action'] ) ? sanitize_textarea_field( $input['next_action'] ) : '',
    'next_action_date'  => isset( $input['next_action_date'] ) ? sanitize_text_field( $input['next_action_date'] ) : '',
    'priority_level'   => isset( $input['priority_level'] ) ? sanitize_text_field( $input['priority_level'] ) : 'Normale',
    'internal_summary'  => isset( $input['internal_summary'] ) ? sanitize_textarea_field( $input['internal_summary'] ) : '',
  );

  if ( $source_prospect_id && empty( $data['company_id'] ) ) {
    $source_prospect = $this->get_prospect( $source_prospect_id );
    if ( $source_prospect ) {
      $data['company_id'] = (int) $this->acdc_find_company_id_from_prospect( $source_prospect );
      if ( ! empty( $data['company_id'] ) && empty( $data['contact_id'] ) ) {
        $data['contact_id'] = (int) $this->acdc_find_contact_id_from_prospect( $source_prospect, (int) $data['company_id'] );
      }
    }
  }

  $required_fields = array();
  if ( empty( $data['company_id'] ) && ! $source_prospect_id ) {
    $required_fields[] = 'company_id';
  }
  if ( empty( $data['theme'] ) ) {
    $required_fields[] = 'theme';
  }
  if ( empty( $data['expressed_need'] ) ) {
    $required_fields[] = 'expressed_need';
  }
  if ( ! empty( $required_fields ) ) {
    $input['company_id'] = isset( $data['company_id'] ) ? (int) $data['company_id'] : 0;
    $input['contact_id'] = isset( $data['contact_id'] ) ? (int) $data['contact_id'] : 0;
    $input['source_prospect_id'] = $source_prospect_id;
    $this->acdc_store_form_state( 'need', $input, $required_fields );
    $redirect_args = array( 'action' => $need_id ? 'edit' : 'new', 'item_id' => $need_id );
    if ( $source_prospect_id ) {
      $redirect_args['prospect_id'] = $source_prospect_id;
    }
    $this->redirect_to_portal( 'needs', 'Veuillez renseigner les champs obligatoires du recueil.', 'error', $redirect_args );
  }

  if ( empty( $data['collection_date'] ) ) {
    $data['collection_date'] = $this->current_date();
  }
  if ( empty( $data['desired_deadline'] ) ) {
    $data['desired_deadline'] = null;
  }
  if ( empty( $data['next_action_date'] ) ) {
    $data['next_action_date'] = null;
  }

  $this->ensure_storage_ready();
  $data['created_by'] = get_current_user_id();
  $now = current_time( 'mysql' );
  global $wpdb;

  if ( $need_id ) {
    $data['updated_at'] = $now;
    unset( $data['created_by'] );
    $result = $wpdb->update( $this->need_table, $data, array( 'id' => $need_id ) );
    $message = 'Recueil mis à jour.';
    $error_context = 'Impossible de mettre à jour le recueil.';
  } else {
    $data['created_at'] = $now;
    $data['updated_at'] = $now;
    $result = $wpdb->insert( $this->need_table, $data );
    $need_id = (int) $wpdb->insert_id;
    $message = 'Recueil enregistré.';
    $error_context = 'Impossible d’enregistrer le recueil.';
    /* ACDC 3.25.185 — Le parcours s'arme ici, à la création du recueil : un
       prospect n'engage rien, un recueil oui. Sans attendre le cron, pour que
       le dossier apparaisse dans le suivi dans la foulée. */
    if ( false !== $result && $need_id > 0 && method_exists( $this, 'acdc_wf_on_need_saved' ) ) {
      $this->acdc_wf_on_need_saved( $need_id );
    }
  }

  $email_results = array(
    'client_attempted' => false,
    'client_sent' => false,
    'notification_attempted' => false,
    'notification_sent' => false,
  );

  if ( false !== $result && $need_id && $notify_client ) {
    $email_results = $this->acdc_send_need_email_notifications( $need_id, $data, $source_prospect_id );
    if ( ! empty( $email_results['client_sent'] ) ) {
      $wpdb->update( $this->need_table, array( 'sent_at' => $now, 'updated_at' => $now ), array( 'id' => $need_id ) );
      $message .= ' Le recueil a été envoyé au prospect.';
    } elseif ( ! empty( $email_results['client_attempted'] ) ) {
      $message .= ' Recueil enregistré, mais l\'e-mail au prospect n\'a pas pu être envoyé.';
    }

    if ( ! empty( $email_results['notification_sent'] ) ) {
      $message .= ' Notification interne envoyée à ACDC Formation.';
    } elseif ( ! empty( $email_results['notification_attempted'] ) ) {
      $message .= ' Notification interne non envoyée.';
    }
  } elseif ( false !== $result && $need_id && ! $notify_client ) {
    $message .= ' (Non envoyé au prospect — envoi désactivé.)';
  }

  $success_extra = array( 'action' => 'edit', 'item_id' => $need_id );
  if ( $source_prospect_id ) {
    $success_extra['prospect_id'] = $source_prospect_id;
    if ( false !== $result ) {
      if ( $notify_client ) {
        $this->maybe_advance_prospect_status( $source_prospect_id, 'Recueil envoyé' );
      }

      /* ── Cascade retour : need → prospect ── */
      global $wpdb;
      $prospect_cascade = array( 'updated_at' => current_time( 'mysql' ) );
      if ( ! empty( $data['planned_funding'] ) ) {
        $prospect_cascade['planned_funding'] = $data['planned_funding'];
      }
      if ( count( $prospect_cascade ) > 1 ) {
        $wpdb->update( $this->prospect_table, $prospect_cascade, array( 'id' => $source_prospect_id ) );
      }
    }
  }
  $this->redirect_with_db_result(
    $result,
    $message,
    $error_context,
    'needs',
    ( is_admin() && isset( $_REQUEST['page'] ) && 'acdc-of-needs' === $_REQUEST['page'] ),
    'acdc-of-needs',
    $success_extra
  );
}private function acdc_send_need_email_notifications( $need_id, $need_data, $source_prospect_id = 0 ) {
  $results = array(
    'client_attempted' => false,
    'client_sent' => false,
    'notification_attempted' => false,
    'notification_sent' => false,
  );

  $need = $this->get_need( $need_id );
  if ( ! $need ) {
    return $results;
  }

  $branding = $this->get_branding_options();
  $marketing_settings = get_option( 'acdc_of_marketing_settings', array() );
  if ( ! is_array( $marketing_settings ) ) {
    $marketing_settings = array();
  }

  $sender_name = ! empty( $marketing_settings['sender_name'] ) ? sanitize_text_field( $marketing_settings['sender_name'] ) : 'ACDC-Formation';
  $sender_email = ! empty( $marketing_settings['sender_email'] ) ? sanitize_email( $marketing_settings['sender_email'] ) : '';
  if ( '' === $sender_email && ! empty( $branding['email'] ) ) {
    $sender_email = sanitize_email( $branding['email'] );
  }
  if ( '' === $sender_email ) {
    $sender_email = 'contact@acdc-formation.com';
  }
  $reply_to = ! empty( $marketing_settings['reply_to'] ) ? sanitize_email( $marketing_settings['reply_to'] ) : $sender_email;
  $internal_email = ! empty( $marketing_settings['internal_notification_email'] ) ? sanitize_email( $marketing_settings['internal_notification_email'] ) : $sender_email;

  $company = ! empty( $need->company_id ) ? $this->get_company( (int) $need->company_id ) : null;
  $contact = ! empty( $need->contact_id ) ? $this->get_contact( (int) $need->contact_id ) : null;
  $prospect = $source_prospect_id ? $this->get_prospect( (int) $source_prospect_id ) : null;

  $recipient_email = '';
  $recipient_name = '';
  if ( $prospect ) {
    $recipient_email = $this->get_prospect_primary_email( $prospect );
    /* ACDC 3.25.239 — On écrit à une personne. */
    $recipient_name  = trim( $this->get_prospect_contact_person_name( $prospect ) );
  }
  if ( '' === $recipient_email && $contact && ! empty( $contact->email ) ) {
    $recipient_email = sanitize_email( $contact->email );
    $recipient_name = trim( (string) $contact->first_name . ' ' . (string) $contact->last_name );
  }
  if ( '' === $recipient_email && $company && ! empty( $company->email ) ) {
    $recipient_email = sanitize_email( $company->email );
    $recipient_name = ! empty( $company->name ) ? (string) $company->name : 'Client';
  }

  $download_url = $this->acdc_get_need_pdf_download_url( $need_id, $source_prospect_id );
  $company_name = $company && ! empty( $company->name ) ? (string) $company->name : ( $prospect ? $this->get_prospect_company_display_name( $prospect ) : '' );
  $theme_label = ! empty( $need->theme ) ? (string) $need->theme : 'Recueil des besoins';
  $collection_date = ! empty( $need->collection_date ) ? mysql2date( 'd/m/Y', $need->collection_date ) : current_time( 'd/m/Y' );

  $headers = array( 'Content-Type: text/html; charset=UTF-8' );
  if ( $sender_email ) {
    $headers[] = 'From: ' . $sender_name . ' <' . $sender_email . '>';
  }
  if ( $reply_to ) {
    $headers[] = 'Reply-To: ' . $reply_to;
  }

  if ( $recipient_email && $download_url ) {
    $results['client_attempted'] = true;
    $subject = 'Votre recueil des besoins - ACDC Formation';
    $hello_name = '' !== trim( $recipient_name ) ? trim( $recipient_name ) : 'Client';
    $summary_rows = array(
      array( 'label' => 'Prospect', 'value' => $hello_name ),
      array( 'label' => 'Entreprise', 'value' => $company_name ? $company_name : '—' ),
      array( 'label' => 'Thématique', 'value' => $theme_label ),
      array( 'label' => 'Date du recueil', 'value' => $collection_date ),
    );
    $body_html  = '<p style="font-size:19px;line-height:1.7;margin:0 0 24px;">Votre recueil des besoins a bien été enregistré par ACDC Formation.</p>';
    $body_html .= '<p style="font-size:18px;line-height:1.7;margin:0 0 24px;">Vous pouvez télécharger votre recueil des besoins en PDF en cliquant sur le lien ci-dessous.</p>';
    $body_html .= '<p style="margin:0 0 10px;"><a href="' . esc_url( $download_url ) . '" style="display:inline-block;padding:12px 18px;background:#C5A253;color:#0B0706;text-decoration:none;border-radius:8px;font-weight:700;">Télécharger le recueil des besoins</a></p>';
    $results['client_sent'] = (bool) $this->acdc_send_transactional_email(
      $recipient_email,
      $subject,
      array(
        'greeting_name' => $hello_name,
        'intro_html' => '',
        'summary_title' => 'RÉCAPITULATIF DE VOTRE RECUEIL DES BESOINS',
        'summary_rows' => $summary_rows,
        'body_html' => $body_html,
        'footer_notice' => 'Cet e-mail a été envoyé suite à votre recueil des besoins. Vos données sont traitées conformément au RGPD.',
      ),
      array(
        'source_module' => 'kernel',
        'source_action' => 'need_pdf_send',
        'related_entity_type' => 'need',
        'related_entity_id' => (int) $need_id,
        'related_sub_id' => (int) $source_prospect_id,
        'email_category' => 'commercial',
        'email_audience' => 'prospect',
      )
    );
  }

  if ( $results['client_sent'] ) {
    global $wpdb;
    $wpdb->update( $this->need_table, array( 'sent_at' => $this->now_mysql(), 'updated_at' => $this->now_mysql() ), array( 'id' => (int) $need_id ) );
  }

  if ( $results['client_sent'] && $internal_email ) {
    $results['notification_attempted'] = true;
    $subject = 'Notification interne - recueil des besoins envoyé';
    $summary_rows = array(
      array( 'label' => 'Prospect', 'value' => $recipient_name ? $recipient_name : '—' ),
      array( 'label' => 'E-mail destinataire', 'value' => $recipient_email ),
      array( 'label' => 'Entreprise', 'value' => $company_name ? $company_name : '—' ),
      array( 'label' => 'Thématique', 'value' => $theme_label ),
      array( 'label' => 'Date du recueil', 'value' => $collection_date ),
    );
    $body_html = '<p style="font-size:19px;line-height:1.7;margin:0 0 24px;">Le recueil des besoins a bien été envoyé au client.</p>';
    $body_html .= '<p style="margin:0 0 10px;"><a href="' . esc_url( $download_url ) . '" style="display:inline-block;padding:12px 18px;background:#C5A253;color:#0B0706;text-decoration:none;border-radius:8px;font-weight:700;">Télécharger le PDF envoyé</a></p>';
    $results['notification_sent'] = (bool) $this->acdc_send_transactional_email(
      $internal_email,
      $subject,
      array(
        'greeting_name' => 'ACDC Formation',
        'intro_html' => '',
        'summary_title' => 'RÉCAPITULATIF DE L’ENVOI DU RECUEIL DES BESOINS',
        'summary_rows' => $summary_rows,
        'body_html' => $body_html,
        'footer_notice' => 'Cet e-mail a été envoyé dans le cadre du suivi interne ACDC Formation.',
      ),
      array(
        'source_module' => 'kernel',
        'source_action' => 'need_pdf_internal_notification',
        'related_entity_type' => 'need',
        'related_entity_id' => (int) $need_id,
        'related_sub_id' => (int) $source_prospect_id,
        'email_category' => 'notification_interne',
        'email_audience' => 'interne',
      )
    );
  }

  return $results;
}

private function acdc_get_need_pdf_download_url( $need_id, $source_prospect_id = 0 ) {
  $token = $this->acdc_build_need_pdf_token( $need_id, $source_prospect_id );
  return add_query_arg(
    array(
      'action' => 'acdc_download_need_pdf',
      'need_id' => (int) $need_id,
      'prospect_id' => (int) $source_prospect_id,
      'token' => rawurlencode( $token ),
    ),
    admin_url( 'admin-post.php' )
  );
}

private function acdc_build_need_pdf_token( $need_id, $source_prospect_id = 0 ) {
  $need = $this->get_need( $need_id );
  $seed = 'need_pdf|' . (int) $need_id . '|' . (int) $source_prospect_id . '|' . ( $need && ! empty( $need->created_at ) ? (string) $need->created_at : '' );
  return hash_hmac( 'sha256', $seed, wp_salt( 'nonce' ) );
}

public function handle_download_need_pdf() {
  $need_id = isset( $_GET['need_id'] ) ? absint( $_GET['need_id'] ) : 0;
  $source_prospect_id = isset( $_GET['prospect_id'] ) ? absint( $_GET['prospect_id'] ) : 0;
  $token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

  if ( ! $need_id || '' === $token ) {
    wp_die( esc_html( 'Lien invalide.' ) );
  }

  $expected = $this->acdc_build_need_pdf_token( $need_id, $source_prospect_id );
  if ( ! hash_equals( $expected, $token ) ) {
    wp_die( esc_html( 'Lien de téléchargement invalide.' ) );
  }

  $need = $this->get_need( $need_id );
  if ( ! $need ) {
    wp_die( esc_html( 'Recueil introuvable.' ) );
  }

  /* client_only = lien tokenisé = page 1 uniquement, même si admin connecté */
  $client_only = isset( $_GET['token'] ) && '' !== $_GET['token'];
  $html        = $this->acdc_build_need_pdf_html( $need, $source_prospect_id, $client_only );
  $filename    = 'recueil-des-besoins-' . (int) $need_id . '.pdf';
  /* ACDC 3.25.256 — Filet commun à toutes les fabrications de PDF : en cas
     d'échec, le document part en version imprimable plutôt que de laisser un
     écran blanc. L'erreur réelle est journalisée par render_html_pdf(). */
  try {
    $this->render_html_pdf( $html, $filename, 'inline' );
  } catch ( \Throwable $e ) {
    while ( ob_get_level() ) { ob_end_clean(); }
    nocache_headers();
    header( 'Content-Type: text/html; charset=UTF-8' );
    echo '<div style="max-width:800px;margin:12px auto;padding:10px 14px;border:1px solid #e8c97a;background:#fff8e8;border-radius:6px;font-family:sans-serif;font-size:13px;color:#7a5c00;">Le PDF n\'a pas pu être fabriqué : voici la version imprimable. Utilisez « Imprimer » puis « Enregistrer au format PDF ».</div>' . $html; // phpcs:ignore WordPress.Security.EscapeOutput
  }
  exit;
}

private function acdc_build_need_pdf_pages( $need, $source_prospect_id = 0, $client_only = false ) {
  $company = ! empty( $need->company_id ) ? $this->get_company( (int) $need->company_id ) : null;
  $contact = ! empty( $need->contact_id ) ? $this->get_contact( (int) $need->contact_id ) : null;
  $prospect = $source_prospect_id ? $this->get_prospect( (int) $source_prospect_id ) : null;
  $profile = $this->get_company_profile_options();
  $pdf_assets = $this->get_acdc_internal_pdf_asset_urls();
  $default_logo_url = 'https://acdcformation.com/wp-content/uploads/2026/03/Logo-ACDC.png';
  $default_signature_url = ! empty( $pdf_assets['cachet_signature_url'] ) ? (string) $pdf_assets['cachet_signature_url'] : 'https://acdcformation.com/wp-content/uploads/2026/04/Cachet-et-signature.png';
  /* ACDC 3.25.232 — Résolution en cascade : le repli écrit en dur ici
     désignait un fichier absent de la médiathèque, et le logo disparaissait
     du PDF sans un mot. */
  $logo_url = $this->acdc_resolve_pdf_logo_url();
  $signature_url = ! empty( $profile['signature_url'] ) ? (string) $profile['signature_url'] : ( ! empty( $profile['stamp_url'] ) ? (string) $profile['stamp_url'] : $default_signature_url );
  $logo = $this->prepare_pdf_jpeg_image( $logo_url, 71, 71 );

  /* ACDC 3.25.233 — Le PDF nommait le MODE de financement et jamais
     l'organisme. « OPCO » ne permet d'appeler personne ni de monter un dossier
     de prise en charge : c'est le nom qui sert. */
  $need_funder_name = '';
  if ( ! empty( $need->funder_id ) ) {
    $need_funder = $this->get_funder( (int) $need->funder_id );
    if ( $need_funder && ! empty( $need_funder->name ) ) {
      $need_funder_name = (string) $need_funder->name;
    }
  }

  /* Le mode et l'organisme tiennent sur la même ligne : la boîte du haut est
     calibrée pour sept lignes, et une huitième déborderait sous son cadre. */
  $need_funding_label = trim( (string) $need->planned_funding );
  if ( '' !== $need_funder_name ) {
    $need_funding_label = '' !== $need_funding_label
      ? $need_funding_label . ' - ' . $need_funder_name
      : $need_funder_name;
  }
  /* Cachet+signature : asset dédié, pas la signature seule du profil */
  $cachet_sig_url = ! empty( $pdf_assets['cachet_signature_url'] )
    ? (string) $pdf_assets['cachet_signature_url']
    : 'https://acdcformation.com/wp-content/uploads/2026/04/Cachet-et-signature.png';
  $signature = $this->prepare_pdf_jpeg_image( $cachet_sig_url, 170, 100 );

  $company_name = $company && ! empty( $company->name ) ? (string) $company->name : ( $prospect ? $this->get_prospect_company_display_name( $prospect ) : '' );
  $contact_name = '';
  if ( $contact ) {
    $contact_name = trim( (string) $contact->first_name . ' ' . (string) $contact->last_name );
  } elseif ( $prospect ) {
    /* ACDC 3.25.239 — « Contact » nomme une personne, pas le dossier. */
    $contact_name = trim( $this->get_prospect_contact_person_name( $prospect ) );
  }

  $clean = function( $value, $default = '—' ) {
    if ( ! is_scalar( $value ) ) {
      return $default;
    }
    $value = html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, 'UTF-8' );
    $value = str_replace( array( "
", "
", "	" ), array( "
", "
", ' ' ), $value );
    $value = preg_replace( '/[ ]{2,}/', ' ', $value );
    $value = trim( (string) $value );
    return '' !== $value ? $value : $default;
  };

  $fmt_date = function( $value, $default = '—' ) {
    if ( empty( $value ) ) {
      return $default;
    }
    $timestamp = strtotime( (string) $value );
    return $timestamp ? date_i18n( 'd/m/Y', $timestamp ) : $default;
  };

  $wrap = function( $value, $limit ) use ( $clean ) {
    $value = $clean( $value );
    $raw_lines = explode( "
", $value );
    $lines = array();
    foreach ( $raw_lines as $raw_line ) {
      $raw_line = trim( (string) $raw_line );
      if ( '' === $raw_line ) {
        $lines[] = ' ';
        continue;
      }
      $lines = array_merge( $lines, explode( "
", wordwrap( $raw_line, (int) $limit, "
", true ) ) );
    }
    return ! empty( $lines ) ? $lines : array( '—' );
  };

  $page_meta = array( 'type' => 'page_meta', 'width' => 595, 'height' => 842 );
  $navy = '#0C2D52';
  $gold = '#C5A253';
  $ink  = '#1f2937';
  $line = '#d7dde6';
  $soft = '#f8fafc';
  $title_case = function( $value ) {
    $value = is_scalar( $value ) ? trim( (string) $value ) : '';
    if ( '' === $value ) {
      return '';
    }
    if ( function_exists( 'remove_accents' ) ) {
      $value = remove_accents( $value );
    }
    return strtoupper( $value );
  };

  $pages = array();
  $page1 = array( $page_meta, array( 'type' => 'rect', 'x' => 0, 'y' => 0, 'width' => 595, 'height' => 842, 'fill_color' => '#ffffff' ) );
  $page2 = array( $page_meta, array( 'type' => 'rect', 'x' => 0, 'y' => 0, 'width' => 595, 'height' => 842, 'fill_color' => '#ffffff' ) );

  $add_text = function( &$page, $text, $x, $y, $size, $bold, $color ) {
    $page[] = array(
      'text'  => (string) $text,
      'x'     => (float) $x,
      'y'     => (float) $y,
      'size'  => (float) $size,
      'font'  => $bold ? 'Helvetica-Bold' : 'Helvetica',
      'color' => $color,
    );
  };

  $add_rect = function( &$page, $x, $y, $w, $h, $fill, $stroke, $line_width ) {
    $page[] = array(
      'type'         => 'rect',
      'x'            => (float) $x,
      'y'            => (float) $y,
      'width'        => (float) $w,
      'height'       => (float) $h,
      'fill_color'   => $fill,
      'stroke_color' => $stroke,
      'line_width'   => (float) $line_width,
    );
  };

  $add_wrapped = function( &$page, $text, $x, $y, $limit, $size, $bold, $color, $line_height, $max_lines = 0 ) use ( $wrap, $add_text ) {
    $lines = $wrap( $text, $limit );
    if ( $max_lines > 0 && count( $lines ) > $max_lines ) {
      $lines = array_slice( $lines, 0, $max_lines );
      $last_index = count( $lines ) - 1;
      $lines[ $last_index ] = rtrim( substr( (string) $lines[ $last_index ], 0, max( 0, strlen( (string) $lines[ $last_index ] ) - 1 ) ) ) . '…';
    }
    $current_y = $y;
    foreach ( $lines as $line_text ) {
      $add_text( $page, $line_text, $x, $current_y, $size, $bold, $color );
      $current_y -= $line_height;
    }
    return $current_y;
  };

  /* ACDC 3.25.260 — Le logo était plafonné en largeur ET en hauteur, chacune
     de son côté : un logo non carré en serait sorti déformé. */
  list( $logo_dw, $logo_dh ) = $logo
    ? $this->acdc_pdf_scaled_size( $logo['width'], $logo['height'], 71, 71 )
    : array( 71, 71 );
  $draw_header = function( &$page, $title ) use ( $add_text, $add_rect, $navy, $gold, $logo, $logo_dw, $logo_dh ) {
    if ( $logo ) {
      $page[] = array(
        'type' => 'image',
        'image_key' => $logo['key'],
        'image_data' => $logo['data'],
        'image_width' => $logo['width'],
        'image_height' => $logo['height'],
        'display_width' => $logo_dw,
        'display_height' => $logo_dh,
        'x' => 35,
        'y' => 767,
      );
      $add_text( $page, 'ACDC - Formation', 118, 803, 15.2, true, $navy );
    } else {
      $add_text( $page, 'ACDC - Formation', 35, 803, 15.2, true, $navy );
    }
    $add_text( $page, 'AZUR COMPETENCES DEVELOPPEMENT & CONSEIL', 35, 758, 8.5, false, $gold );
    $add_text( $page, 'ACDC-Formation', 420, 803, 8.2, true, '#374151' );
    $add_text( $page, '7 avenue Paul Cezanne', 420, 792, 8.0, false, '#374151' );
    $add_text( $page, '83310 Cogolin - France', 420, 781, 8.0, false, '#374151' );
    $add_text( $page, 'Siret : 405109901 00042', 420, 770, 8.0, false, '#374151' );
    $add_text( $page, 'NDA : 93 83 08347 83', 420, 759, 8.0, false, '#374151' );
    $add_rect( $page, 35, 745, 525, 1.2, $gold, null, 0 );
    $add_text( $page, $title, 35, 722, 15.4, true, $navy );
  };

  $draw_footer = function( &$page ) use ( $add_rect, $add_text, $line ) {
    $add_rect( $page, 25, 34, 545, 0.8, $line, null, 0 );
    $add_text( $page, '7 avenue Paul Cézanne - 83310 Cogolin - France - Siret : 405109901 00042 - NDA : 93 83 08347 83', 40, 20, 6.5, false, '#4b5563' );
    $add_text( $page, 'e-mail : contact@acdc-formation.com - Tél : 06 78 26 91 10 - site web : acdc-formation.com', 68, 10, 6.5, false, '#4b5563' );
  };

  $draw_card = function( &$page, $x, $y, $w, $h, $title ) use ( $add_text, $add_rect, $gold, $title_case ) {
    $add_rect( $page, $x, $y, $w, $h, '#ffffff', '#d7dde6', 0.6 );
    $add_text( $page, $title_case( $title ), $x + 6, $y + $h - 17, 7.5, true, $gold );
  };

  $draw_labeled_rows = function( &$page, $x, $y, $w, $h, $title, $rows ) use ( $draw_card, $add_text, $add_rect, $add_wrapped, $navy, $ink, $clean ) {
    $draw_card( $page, $x, $y, $w, $h, $title );
    $row_y = $y + $h - 40;
    $value_wrap = $w > 240 ? 30 : 26;
    foreach ( $rows as $row ) {
      $value = $clean( $row['value'] );
      $lines = preg_split( "/\r\n|\r|\n/", wordwrap( $value, $value_wrap, "
", true ) );
      $lines = array_values( array_filter( array_map( 'trim', (array) $lines ), static function( $line ) { return '' !== $line; } ) );
      if ( empty( $lines ) ) {
        $lines = array( '—' );
      }
      $line_count = min( 2, count( $lines ) );
      $row_height = max( 16, 10 + ( $line_count * 10 ) );
      $add_text( $page, $row['label'], $x + 12, $row_y, 8.7, true, $navy );
      $line_y = $row_y - ( $line_count > 1 ? ( $line_count - 1 ) * 5 : 0 );
      $add_rect( $page, $x + 120, $line_y - 2, $w - 132, 0.6, '#bfc8d5', null, 0 );
      $add_wrapped( $page, implode( "
", array_slice( $lines, 0, 2 ) ), $x + 126, $row_y + 1, $value_wrap, 8.3, false, $ink, 10, 2 );
      $row_y -= $row_height;
    }
  };

  $draw_textarea = function( &$page, $x, $y, $w, $h, $title, $blocks ) use ( $draw_card, $add_text, $add_wrapped, $navy ) {
    $draw_card( $page, $x, $y, $w, $h, $title );
    $cursor = $y + $h - 36;
    foreach ( $blocks as $block ) {
      $has_label  = isset( $block['label'] ) && '' !== (string) $block['label'];
      $box_height = $block['height'];
      if ( $has_label ) {
        $add_text( $page, $block['label'], $x + 6, $cursor, 8.3, true, $navy );
        $box_y = $cursor - $box_height - 4;
      } else {
        /* Pas de label : remonter le contenu de la hauteur du label (~14 pts) */
        $box_y = $cursor - $box_height + 10;
      }
      $add_wrapped( $page, $block['value'], $x + 6, $box_y + $box_height - 12, $block['wrap'], 8.1, false, '#374151', 10, $block['max_lines'] );
      $cursor = $box_y - 6;
    }
  };

  $draw_mini_card = function( &$page, $x, $y, $w, $h, $title, $value ) use ( $add_text, $add_rect, $gold, $ink, $wrap, $title_case ) {
    $add_rect( $page, $x, $y, $w, $h, null, '#d7dde6', 0.6 );
    $add_text( $page, $title_case( $title ), $x + 6, $y + $h - 10, 7.0, true, $gold );
    $lines = $wrap( $value, 22 );
    $vy    = $y + $h - 22;
    foreach ( array_slice( $lines, 0, 2 ) as $line_text ) {
      $add_text( $page, $line_text, $x + 6, $vy, 8.5, false, $ink );
      $vy -= 11;
    }
  };

  $draw_header( $page1, 'Recueil des besoins' );
  $draw_labeled_rows(
    $page1, 35, 545, 250, 158, 'Identification',
    array(
      array( 'label' => 'Entreprise :', 'value' => $company_name ),
      array( 'label' => 'Contact :', 'value' => $contact_name ),
      array( 'label' => 'Thématique :', 'value' => $need->theme ),
      array( 'label' => 'Canal de recueil :', 'value' => $need->collection_channel ),
      array( 'label' => 'Date du recueil :', 'value' => $fmt_date( $need->collection_date ) ),
      array( 'label' => 'Interlocuteur ACDC :', 'value' => $need->acdc_interlocutor ),
      array( 'label' => 'Statut :', 'value' => $need->status ),
    )
  );
  $draw_labeled_rows(
    $page1, 310, 545, 250, 158, 'Public concerne',
    array(
      array( 'label' => 'Public :', 'value' => $need->target_audience ),
      array( 'label' => 'Niveau :', 'value' => $need->audience_level ),
      array( 'label' => 'Effectif :', 'value' => $need->learners_count ),
      array( 'label' => 'Format souhaité :', 'value' => $need->desired_format ),
      array( 'label' => 'Financement :', 'value' => $need_funding_label ),
      array( 'label' => 'Urgence :', 'value' => $need->urgency ),
      array( 'label' => 'Échéance souhaitée :', 'value' => $fmt_date( $need->desired_deadline ) ),
    )
  );
  $draw_textarea(
    $page1, 35, 224, 525, 320, 'Besoin',
    array(
      array( 'label' => 'Besoin exprimé',      'value' => $need->expressed_need,    'height' => 54, 'wrap' => 147, 'max_lines' => 5 ),
      array( 'label' => 'Besoin reformulé',    'value' => $need->reframed_need,     'height' => 54, 'wrap' => 147, 'max_lines' => 5 ),
      array( 'label' => 'Contexte',            'value' => $need->context_text,      'height' => 54, 'wrap' => 147, 'max_lines' => 5 ),
      array( 'label' => 'Situation actuelle',  'value' => $need->current_situation, 'height' => 54, 'wrap' => 147, 'max_lines' => 5 ),
    )
  );
  $draw_textarea(
    $page1, 35, 102, 252, 120, 'Objectifs attendus',
    array(
      array( 'label' => '', 'value' => $need->expected_objectives, 'height' => 96, 'wrap' => 48, 'max_lines' => 9 ),
    )
  );
  $draw_textarea(
    $page1, 308, 102, 252, 120, 'Contraintes',
    array(
      array( 'label' => '', 'value' => $need->constraints_text, 'height' => 96, 'wrap' => 48, 'max_lines' => 9 ),
    )
  );
  /* Mini-cards sous Objectifs / Contraintes */
  $draw_mini_card( $page1, 35,  56, 165, 32, 'Budget estimatif', $need->budget );
  $draw_mini_card( $page1, 215, 56, 165, 32, 'Prochaine action', $need->next_action );
  $draw_mini_card( $page1, 395, 56, 165, 32, 'Date prochaine action', $fmt_date( $need->next_action_date ) );
  /* Cachet + signature (6cm large, centré, sous les mini-cards) */
  if ( $signature ) {
    $sig_display_w = min( 170, $signature['display_width'] );
    $sig_display_h = isset( $signature['display_height'] ) ? (int) round( $sig_display_w * $signature['display_height'] / max( 1, $signature['display_width'] ) ) : 65;
    $sig_x = (int) round( ( 595 - $sig_display_w ) / 2 );
    $page1[] = array(
      'type'           => 'image',
      'image_key'      => $signature['key'],
      'image_data'     => $signature['data'],
      'image_width'    => $signature['width'],
      'image_height'   => $signature['height'],
      'display_width'  => $sig_display_w,
      'display_height' => $sig_display_h,
      'x'              => $sig_x,
      'y'              => 46,
    );
  }
  $draw_footer( $page1 );

  $draw_header( $page2, 'Recueil des besoins - suite' );
  /* Zones texte agrandies (mini-cards et cachet déplacés en page 1) */
  $draw_textarea(
    $page2, 35, 503, 525, 185, 'Resume interne',
    array(
      array( 'label' => 'Resume interne', 'value' => $need->internal_summary, 'height' => 148, 'wrap' => 88, 'max_lines' => 13 ),
    )
  );
  $draw_textarea(
    $page2, 35, 293, 525, 200, 'Observations complementaires',
    array(
      array( 'label' => 'Observations complementaires', 'value' => '', 'height' => 162, 'wrap' => 88, 'max_lines' => 14 ),
    )
  );
  $draw_textarea(
    $page2, 35, 83, 525, 200, 'Synthese / preconisation',
    array(
      array( 'label' => 'Synthese / preconisation', 'value' => '', 'height' => 162, 'wrap' => 88, 'max_lines' => 14 ),
    )
  );
  $draw_footer( $page2 );

  $pages[] = $page1;
  if ( ! $client_only ) {
    $pages[] = $page2;
  }
  return $pages;
}

public function handle_delete_need() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  $need_id = isset( $_GET['need_id'] ) ? absint( $_GET['need_id'] ) : 0;
  check_admin_referer( 'acdc_delete_need_' . $need_id );
  global $wpdb;
  if ( $need_id ) {
    /* Vérifier si le prospect source est lié à une convention */
    $need = $wpdb->get_row( $wpdb->prepare( "SELECT source_prospect_id FROM {$this->need_table} WHERE id = %d", $need_id ) );
    if ( $need && ! empty( $need->source_prospect_id ) ) {
      $convention_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$this->registration_contract_table} WHERE source_prospect_id = %d",
        (int) $need->source_prospect_id
      ) );
      if ( $convention_count ) {
        $msg = 'Suppression impossible : ce recueil des besoins est lié à une convention ou un contrat de formation (preuve Qualiopi).';
        if ( is_admin() && isset( $_REQUEST['page'] ) && 'acdc-of-needs' === $_REQUEST['page'] ) {
          wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-needs&notice=' . rawurlencode( $msg ) . '&notice_type=error' ) );
          exit;
        }
        $this->redirect_to_portal( 'needs', $msg, 'error' );
      }
    }
    $wpdb->delete( $this->need_table, array( 'id' => $need_id ) );
  }
  if ( is_admin() && isset( $_REQUEST['page'] ) && 'acdc-of-needs' === $_REQUEST['page'] ) {
    wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-needs&notice=' . rawurlencode( 'Recueil supprimé.' ) . '&notice_type=success' ) );
    exit;
  }
  $this->redirect_to_portal( 'needs', 'Recueil supprimé.', 'success' );
}public function handle_resend_need_email() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  $need_id = isset( $_POST['need_id'] ) ? absint( $_POST['need_id'] ) : 0;
  check_admin_referer( 'acdc_resend_need_email_' . $need_id );
  if ( ! $need_id ) {
    wp_die( esc_html( 'Identifiant manquant.' ) );
  }
  global $wpdb;
  $need = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->need_table} WHERE id = %d", $need_id ) );
  if ( ! $need ) {
    wp_die( esc_html( 'Recueil introuvable.' ) );
  }
  $return_url = ! empty( $_POST['return_url'] ) ? esc_url_raw( wp_unslash( $_POST['return_url'] ) ) : '';
  $source_prospect_id = ! empty( $need->source_prospect_id ) ? (int) $need->source_prospect_id : 0;
  $need_data = (array) $need;
  $results = $this->acdc_send_need_email_notifications( $need_id, $need_data, $source_prospect_id );
  $wpdb->update( $this->need_table, array( 'sent_at' => $this->now_mysql(), 'updated_at' => $this->now_mysql() ), array( 'id' => $need_id ) );
  $notice = ! empty( $results['client_sent'] ) ? 'Recueil renvoyé au prospect.' : 'Recueil mis à jour (e-mail non envoyé — vérifier la configuration).';
  $notice_type = ! empty( $results['client_sent'] ) ? 'success' : 'warning';
  if ( $return_url ) {
    $redirect = add_query_arg( array( 'notice' => rawurlencode( $notice ), 'notice_type' => $notice_type ), $return_url );
    wp_safe_redirect( $redirect );
    exit;
  }
  if ( is_admin() ) {
    wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-needs&action=edit&item_id=' . $need_id . '&notice=' . rawurlencode( $notice ) . '&notice_type=' . $notice_type ) );
    exit;
  }
  $this->redirect_to_portal( 'needs', $notice, $notice_type, array( 'action' => 'edit', 'item_id' => $need_id ) );
}public function handle_save_group() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  check_admin_referer( 'acdc_save_group' );

  global $wpdb;
  $group_id = isset( $_POST['group_id'] ) ? absint( wp_unslash( $_POST['group_id'] ) ) : 0;
  $input = isset( $_POST['group'] ) && is_array( $_POST['group'] ) ? wp_unslash( $_POST['group'] ) : array();
  $name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';
  if ( '' === $name ) {
    /* ACDC 3.20.92 — Conservation des données saisies (trainer_id, comment_text…) en cas d'erreur. */
    $this->acdc_store_form_state( 'group', $input, array( 'name' ) );
    $target = is_admin() && isset( $_POST['page'] ) && 'acdc-of-groups' === $_POST['page'] ? admin_url( 'admin.php?page=acdc-of-groups&action=' . ( $group_id ? 'edit&item_id=' . $group_id : 'new' ) . '&notice=' . rawurlencode( 'Le nom du groupe est obligatoire.' ) . '&notice_type=error' ) : $this->portal_page_url( array( 'tab' => 'groups', 'action' => $group_id ? 'edit' : 'new', 'item_id' => $group_id, 'notice' => rawurlencode( 'Le nom du groupe est obligatoire.' ), 'notice_type' => 'error' ) );
    wp_safe_redirect( $target );
    exit;
  }

  /* ACDC 3.20.92 — Persistance du formateur désigné.
     trainer_id : référence id-based vers wp_acdc_of_trainers. Vérification d'existence pour
                  éviter une référence orpheline (sinon on ignore et on remet à NULL).
     trainer_name : auto-rempli "first_name last_name" pour conserver la rétrocompatibilité
                    avec les lectures existantes (merge tags PDF, extranet apprenant). */
  $trainer_id_in = isset( $input['trainer_id'] ) ? absint( $input['trainer_id'] ) : 0;
  $trainer_name_auto = '';
  $trainer_id_to_store = null;
  if ( $trainer_id_in > 0 ) {
    $trainer_obj = $this->get_trainer( $trainer_id_in );
    if ( $trainer_obj ) {
      $trainer_id_to_store = (int) $trainer_obj->id;
      $trainer_name_auto = trim( (string) $trainer_obj->first_name . ' ' . (string) $trainer_obj->last_name );
    }
  }

  $data = array(
    'name'         => $name,
    'comment_text' => isset( $input['comment_text'] ) ? sanitize_textarea_field( $input['comment_text'] ) : '',
    'trainer_id'   => $trainer_id_to_store,
    'trainer_name' => $trainer_name_auto,
    'formation_id' => isset( $input['formation_id'] ) && absint( $input['formation_id'] ) > 0 ? absint( $input['formation_id'] ) : null,
    'learner_ids'  => '',
    'updated_at'   => $this->now_mysql(),
  );

  // Apprenants cochés
  $raw_learner_ids = isset( $_POST['learner_ids'] ) && is_array( $_POST['learner_ids'] )
    ? array_values( array_filter( array_map( 'absint', wp_unslash( $_POST['learner_ids'] ) ) ) )
    : array();
  if ( ! empty( $raw_learner_ids ) ) {
    $data['learner_ids'] = implode( ',', $raw_learner_ids );
  }

  if ( $group_id ) {
    $result = $wpdb->update( $this->group_table, $data, array( 'id' => $group_id ) );
    $message = 'Groupe mis à jour.';
  } else {
    $data['created_at'] = $this->now_mysql();
    $result = $wpdb->insert( $this->group_table, $data );
    $group_id = (int) $wpdb->insert_id;
    $message = 'Groupe enregistré.';
  }

  if ( $group_id ) {
    $fresh_group = $this->get_group( $group_id );
    if ( $fresh_group ) {
      $this->acdc_ensure_group_document_space( $fresh_group );
    }
  }

  $extra = array();
  $add_new = ! empty( $_POST['save_and_add'] );
  if ( is_admin() && isset( $_POST['page'] ) && 'acdc-of-groups' === $_POST['page'] ) {
    $query = array( 'page' => 'acdc-of-groups', 'tab' => 'groups', 'notice' => rawurlencode( $message ), 'notice_type' => 'success' );
    if ( $add_new ) {
      $query['action'] = 'new';
    }
    wp_safe_redirect( add_query_arg( $query, admin_url( 'admin.php' ) ) );
    exit;
  }

  if ( $add_new ) {
    $extra['action'] = 'new';
  }
  $this->redirect_to_portal( 'groups', $message, 'success', $extra );
}public function handle_delete_group() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  $group_id = isset( $_GET['group_id'] ) ? absint( wp_unslash( $_GET['group_id'] ) ) : 0;
  if ( ! $group_id ) {
    $this->redirect_to_portal( 'groups', 'Groupe introuvable.', 'error' );
  }
  check_admin_referer( 'acdc_delete_group_' . $group_id );
  global $wpdb;
  $wpdb->delete( $this->group_table, array( 'id' => $group_id ) );
  if ( is_admin() && isset( $_GET['page'] ) && 'acdc-of-groups' === $_GET['page'] ) {
    wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-groups&tab=groups&notice=' . rawurlencode( 'Groupe supprimé.' ) . '&notice_type=success' ) );
    exit;
  }
  $this->redirect_to_portal( 'groups', 'Groupe supprimé.', 'success' );
}public function handle_interrupt_group() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  $group_id = isset( $_GET['group_id'] ) ? absint( wp_unslash( $_GET['group_id'] ) ) : 0;
  if ( ! $group_id ) {
    $this->redirect_to_portal( 'groups', 'Groupe introuvable.', 'error' );
  }
  check_admin_referer( 'acdc_interrupt_group_' . $group_id );
  $interrupted = get_option( 'acdc_of_interrupted_groups', array() );
  if ( ! is_array( $interrupted ) ) {
    $interrupted = array();
  }
  $interrupted[ $group_id ] = array(
    'group_id' => $group_id,
    'status' => 'Interrompue',
    'interrupted_at' => $this->now_mysql(),
    'interrupted_by' => get_current_user_id(),
  );
  update_option( 'acdc_of_interrupted_groups', $interrupted, false );
  if ( is_admin() && isset( $_GET['page'] ) && 'acdc-of-groups' === $_GET['page'] ) {
    wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-groups&tab=groups&notice=' . rawurlencode( 'Formation du groupe interrompue.' ) . '&notice_type=success' ) );
    exit;
  }
  $this->redirect_to_portal( 'groups', 'Formation du groupe interrompue.', 'success' );
}

  /**
   * ACDC 3.25.257 — RÉVÉLER LE MOT DE PASSE D'UN ESPACE FINANCEUR.
   *
   * Il n'est jamais écrit dans la page : l'imprimer masqué en HTML reviendrait
   * à le publier dans le code source, et le masque ne serait qu'un décor. Il
   * n'est déchiffré qu'ici, sur demande explicite, après vérification du droit
   * d'administration et d'un jeton propre à cette fiche. La lecture est
   * journalisée : qui a révélé quoi, et quand.
   */
  public function ajax_reveal_funder_password() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
    }
    $funder_id = isset( $_POST['funder_id'] ) ? absint( wp_unslash( $_POST['funder_id'] ) ) : 0;
    $nonce     = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( ! $funder_id || ! wp_verify_nonce( $nonce, 'acdc_reveal_funder_password_' . $funder_id ) ) {
      wp_send_json_error( array( 'message' => 'Lien expiré : rechargez la fiche.' ) );
    }

    global $wpdb;
    $stored = (string) $wpdb->get_var( $wpdb->prepare(
      "SELECT portal_password FROM {$this->funder_table} WHERE id = %d",
      $funder_id
    ) );
    if ( '' === $stored ) {
      wp_send_json_error( array( 'message' => 'Aucun mot de passe enregistré.' ) );
    }

    $plain = method_exists( $this, 'acdc_secret_decrypt' ) ? $this->acdc_secret_decrypt( $stored ) : $stored;
    if ( '' === $plain ) {
      wp_send_json_error( array( 'message' => 'Déchiffrement impossible sur ce serveur.' ) );
    }

    if ( method_exists( $this, 'log_action_event' ) ) {
      $this->log_action_event( 'read', 'funder_portal_password', $funder_id );
    }
    wp_send_json_success( array( 'password' => $plain ) );
  }

  /**
   * ACDC 3.25.261 — « PLUS TARD » NE VEUT PAS DIRE « PLUS JAMAIS ».
   *
   * Le rappel du contrat formateur se repousse d'une journée, par utilisateur.
   * Il n'existe volontairement aucun moyen de le fermer définitivement : c'est
   * l'oubli lui-même qu'on essaie d'empêcher, et un rappel qu'on peut éteindre
   * s'éteint le premier jour où il dérange. Il disparaît de lui-même quand le
   * contrat existe.
   */
  public function ajax_snooze_trainer_contract_popup() {
    if ( ! is_user_logged_in() ) {
      wp_send_json_error( array( 'message' => 'Accès refusé.' ) );
    }
    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'acdc_snooze_trainer_contract_popup' ) ) {
      wp_send_json_error( array( 'message' => 'Lien expiré.' ) );
    }
    update_user_meta( get_current_user_id(), 'acdc_of_tc_popup_snoozed_until', wp_date( 'Y-m-d' ) );
    wp_send_json_success( array( 'snoozed_until' => wp_date( 'Y-m-d' ) ) );
  }

  public function handle_save_funder() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  check_admin_referer( 'acdc_save_funder' );

  $funder_id = isset( $_POST['funder_id'] ) ? absint( $_POST['funder_id'] ) : 0;
  $input = isset( $_POST['funder'] ) && is_array( $_POST['funder'] ) ? wp_unslash( $_POST['funder'] ) : array();

  $data = array(
    'name' => isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '',
    'sector' => isset( $input['sector'] ) ? sanitize_text_field( $input['sector'] ) : '',
    'address' => isset( $input['address'] ) ? sanitize_text_field( $input['address'] ) : '',
    'city' => isset( $input['city'] ) ? sanitize_text_field( $input['city'] ) : '',
    'postal_code' => isset( $input['postal_code'] ) ? sanitize_text_field( $input['postal_code'] ) : '',
    'funder_type' => isset( $input['funder_type'] ) ? sanitize_text_field( $input['funder_type'] ) : '',
    'email'    => isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '',
    'phone'    => isset( $input['phone'] ) ? sanitize_text_field( $input['phone'] ) : '',
    'website' => isset( $input['website'] ) ? esc_url_raw( $input['website'] ) : '',
    'contact_type' => isset( $input['contact_type'] ) ? sanitize_text_field( $input['contact_type'] ) : '',
    'has_paca_presence' => isset( $input['has_paca_presence'] ) ? sanitize_text_field( $input['has_paca_presence'] ) : '',
    'paca_contact_details' => isset( $input['paca_contact_details'] ) ? sanitize_textarea_field( $input['paca_contact_details'] ) : '',
    /* ACDC 3.25.257 — L'interlocuteur dédié : c'est avec lui qu'on traite. */
    'contact_first_name' => isset( $input['contact_first_name'] ) ? sanitize_text_field( $input['contact_first_name'] ) : '',
    'contact_last_name'  => isset( $input['contact_last_name'] )  ? sanitize_text_field( $input['contact_last_name'] )  : '',
    'contact_phone'      => isset( $input['contact_phone'] )      ? sanitize_text_field( $input['contact_phone'] )      : '',
    'contact_email'      => isset( $input['contact_email'] )      ? sanitize_email( $input['contact_email'] )           : '',
    'portal_url'         => isset( $input['portal_url'] )         ? esc_url_raw( $input['portal_url'] )                 : '',
    'portal_login'       => isset( $input['portal_login'] )       ? sanitize_text_field( $input['portal_login'] )       : '',
  );

  /* ACDC 3.25.257 — LE MOT DE PASSE DE L'ESPACE FINANCEUR, CHIFFRÉ AU REPOS.
   *
   * Même mécanisme que les clés API de veille depuis la 3.25.92 : AES-256, clé
   * dérivée d'un secret qui ne vit pas dans la base (ACDC_SECRET_KEY, sinon un
   * sel WordPress). Une sauvegarde SQL exportée ne révèle donc plus rien.
   *
   * Ce que cela ne protège pas, et qui doit être dit : un administrateur du
   * site peut cliquer « révéler ». C'est inhérent — un mot de passe qu'il faut
   * pouvoir relire ne peut pas être haché comme un mot de passe de connexion.
   *
   * Le champ vide ne signifie JAMAIS « efface » : le formulaire n'envoie
   * jamais le mot de passe existant, il ne l'affiche même pas. Sans saisie, on
   * garde ce qui est enregistré ; c'est la case « effacer » qui supprime. */
  $password_input = isset( $input['portal_password'] ) ? (string) $input['portal_password'] : '';
  $clear_password = ! empty( $input['portal_password_clear'] );
  switch ( \ACDC\Support\StoredSecret::decide( $password_input, $clear_password ) ) {
    case \ACDC\Support\StoredSecret::CLEAR:
      $data['portal_password'] = '';
      break;
    case \ACDC\Support\StoredSecret::SET:
      $data['portal_password'] = method_exists( $this, 'acdc_secret_encrypt' )
        ? $this->acdc_secret_encrypt( $password_input )
        : $password_input;
      break;
    /* KEEP : la colonne n'est pas dans $data, donc pas touchée par l'UPDATE. */
  }

  $missing_fields = array();
  if ( empty( $data['name'] ) ) {
    $missing_fields[] = 'name';
  }
  if ( empty( $data['funder_type'] ) ) {
    $missing_fields[] = 'funder_type';
  }

  if ( ! empty( $missing_fields ) ) {
    $target_args = array( 'tab' => 'funders', 'action' => $funder_id ? 'edit' : 'new', 'item_id' => $funder_id );
    $this->acdc_store_form_state( 'funder', $input, $missing_fields );
    $this->redirect_to_portal( 'funders', 'Veuillez renseigner les champs obligatoires du financeur.', 'error', $target_args );
  }

  $this->ensure_storage_ready();
  global $wpdb;
  $now = current_time( 'mysql' );

  if ( $funder_id ) {
    $data['updated_at'] = $now;
    $result = $wpdb->update( $this->funder_table, $data, array( 'id' => $funder_id ) );
    $message = 'Financeur mis à jour.';
    $error_context = 'Impossible de mettre à jour le financeur.';
  } else {
    $data['created_at'] = $now;
    $data['updated_at'] = $now;
    $result = $wpdb->insert( $this->funder_table, $data );
    $funder_id = (int) $wpdb->insert_id;
    $message = 'Financeur créé.';
    $error_context = 'Impossible de créer le financeur.';
  }

  if ( false === $result ) {
    $this->acdc_store_form_state( 'funder', $input );
  }

  $this->redirect_with_db_result(
    $result,
    $message,
    $error_context,
    'funders',
    ( is_admin() && isset( $_REQUEST['page'] ) && 'acdc-of-funders' === $_REQUEST['page'] ),
    'acdc-of-funders',
    array(),
    ! empty( $_POST['save_and_add'] )
  );
}public function handle_delete_funder() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  $funder_id = isset( $_GET['funder_id'] ) ? absint( $_GET['funder_id'] ) : 0;
  check_admin_referer( 'acdc_delete_funder_' . $funder_id );
  global $wpdb;
  if ( $funder_id ) {
    $wpdb->delete( $this->funder_table, array( 'id' => $funder_id ) );
  }
  if ( is_admin() && isset( $_REQUEST['page'] ) && 'acdc-of-funders' === $_REQUEST['page'] ) {
    wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-funders&notice=' . rawurlencode( 'Financeur supprimé.' ) . '&notice_type=success' ) );
    exit;
  }
  $this->redirect_to_portal( 'funders', 'Financeur supprimé.', 'success' );
}public function handle_save_pre_meeting() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    check_admin_referer( 'acdc_save_pre_meeting' );
    global $wpdb;
    $pre_meeting_id = isset( $_POST['pre_meeting_id'] ) ? absint( wp_unslash( $_POST['pre_meeting_id'] ) ) : 0;
    $input = isset( $_POST['pre_meeting'] ) ? (array) wp_unslash( $_POST['pre_meeting'] ) : array();
    $learner_id = isset( $input['learner_id'] ) ? absint( $input['learner_id'] ) : 0;
    $formation_id = isset( $input['formation_id'] ) ? absint( $input['formation_id'] ) : 0;
    $trainer_id = isset( $input['trainer_id'] ) ? absint( $input['trainer_id'] ) : 0;
    $rdv_at_local = isset( $input['rdv_at'] ) ? sanitize_text_field( $input['rdv_at'] ) : '';
    $duration_label = isset( $input['duration_label'] ) ? sanitize_text_field( $input['duration_label'] ) : '';

    $error_base = is_admin() && isset( $_POST['page'] ) && 'acdc-of-pre-meetings' === $_POST['page']
      ? admin_url( 'admin.php?page=acdc-of-pre-meetings&action=' . ( $pre_meeting_id ? 'edit&item_id=' . $pre_meeting_id : 'new' ) )
      : $this->portal_page_url( array( 'tab' => 'pre_meetings', 'action' => $pre_meeting_id ? 'edit' : 'new', 'item_id' => $pre_meeting_id ) );

    if ( ! $learner_id || ! $formation_id || ! $trainer_id || '' === $rdv_at_local || '' === $duration_label ) {
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Tous les champs obligatoires doivent être renseignés.' ), 'notice_type' => 'error' ), $error_base ) );
      exit;
    }

    $learner = $this->get_learner( $learner_id );
    $formation = $this->get_formation( $formation_id );
    $trainer = $this->get_trainer( $trainer_id );
    if ( ! $learner || ! $formation || ! $trainer ) {
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Apprenant, formation ou formateur introuvable.' ), 'notice_type' => 'error' ), $error_base ) );
      exit;
    }

    $session_dates_map = $this->get_session_dates_grouped_by_formation();
    $allowed_dates = isset( $session_dates_map[ $formation_id ] ) ? $session_dates_map[ $formation_id ] : array();
    $rdv_at_mysql = $this->datetime_from_local( $rdv_at_local );
    $rdv_date = ! empty( $rdv_at_mysql ) ? gmdate( 'Y-m-d', strtotime( $rdv_at_mysql ) ) : '';
    if ( empty( $allowed_dates ) || ! in_array( $rdv_date, $allowed_dates, true ) ) {
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Le rendez-vous post-formation doit correspondre à une date de séance de formation.' ), 'notice_type' => 'error' ), $error_base ) );
      exit;
    }

    $data = array(
      'learner_id' => $learner_id,
      'learner_label' => trim( $learner->first_name . ' ' . $learner->usage_last_name ),
      'formation_id' => $formation_id,
      'formation_title' => $formation->title,
      'rdv_at' => $rdv_at_mysql,
      'duration_label' => $duration_label,
      'trainer_id' => $trainer_id,
      'trainer_label' => trim( $trainer->first_name . ' ' . $trainer->last_name ),
      'updated_at' => $this->now_mysql(),
    );

    if ( $pre_meeting_id ) {
      $result = $wpdb->update( $this->pre_meeting_table, $data, array( 'id' => $pre_meeting_id ) );
      $message = 'Rendez-vous préalable mis à jour.';
    } else {
      $data['created_at'] = $this->now_mysql();
      $result = $wpdb->insert( $this->pre_meeting_table, $data );
      $pre_meeting_id = (int) $wpdb->insert_id;
      $message = 'Rendez-vous préalable enregistré.';
    }

    if ( false === $result ) {
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( $this->get_safe_db_error_message( 'Une erreur technique est survenue.' ) ), 'notice_type' => 'error' ), $error_base ) );
      exit;
    }

    $target = is_admin() && isset( $_POST['page'] ) && 'acdc-of-pre-meetings' === $_POST['page']
      ? admin_url( 'admin.php?page=acdc-of-pre-meetings' )
      : $this->portal_page_url( array( 'tab' => 'pre_meetings' ) );
    if ( ! empty( $_POST['save_and_add'] ) ) {
      $target = is_admin() && isset( $_POST['page'] ) && 'acdc-of-pre-meetings' === $_POST['page']
        ? admin_url( 'admin.php?page=acdc-of-pre-meetings&action=new' )
        : $this->portal_page_url( array( 'tab' => 'pre_meetings', 'action' => 'new' ) );
    }
    wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ), $target ) );
    exit;
  }public function handle_delete_pre_meeting() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $pre_meeting_id = isset( $_GET['pre_meeting_id'] ) ? absint( wp_unslash( $_GET['pre_meeting_id'] ) ) : 0;
    if ( ! $pre_meeting_id ) {
      $this->redirect_to_portal( 'pre_meetings', 'Rendez-vous introuvable.', 'error' );
    }
    check_admin_referer( 'acdc_delete_pre_meeting_' . $pre_meeting_id );
    global $wpdb;
    $wpdb->delete( $this->pre_meeting_table, array( 'id' => $pre_meeting_id ) );
    $target = ( isset( $_GET['page'] ) && 'acdc-of-pre-meetings' === $_GET['page'] )
      ? admin_url( 'admin.php?page=acdc-of-pre-meetings' )
      : $this->portal_page_url( array( 'tab' => 'pre_meetings' ) );
    wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Rendez-vous supprimé.' ), 'notice_type' => 'success' ), $target ) );
    exit;
  }public function handle_save_convocation_params() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }

  check_admin_referer( 'acdc_save_convocation_params' );

  $defaults = $this->get_convocation_params_defaults();
  $saved  = get_option( 'acdc_of_convocation_params', array() );
  $input  = isset( $_POST['convocation_params'] ) && is_array( $_POST['convocation_params'] ) ? wp_unslash( $_POST['convocation_params'] ) : array();
  $clean  = array();

  foreach ( $defaults as $key => $default ) {
    if ( 'additional_sections' === $key ) {
      continue;
    }
    $value = array_key_exists( $key, $input ) ? $input[ $key ] : ( isset( $saved[ $key ] ) ? $saved[ $key ] : $default );
    if ( 'pmr_access' === $key ) {
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
  $clean['additional_sections'] = $sections;

  update_option( 'acdc_of_convocation_params', $clean, false );
  wp_cache_delete( 'acdc_of_convocation_params', 'options' );

  $return_context = isset( $_POST['return_context'] ) ? sanitize_key( wp_unslash( $_POST['return_context'] ) ) : 'front';
  if ( 'admin' === $return_context ) {
    wp_safe_redirect( $this->admin_tab_url( 'convocation_parameters', array( 'updated' => 1 ) ) );
    exit;
  }

  wp_safe_redirect( $this->portal_page_url( array(
    'tab' => 'convocation_parameters',
    'updated' => 1,
    'notice' => rawurlencode( 'Paramètres des convocations enregistrés.' ),
    'notice_type' => 'success',
  ) ) );
  exit;
}public function handle_save_attestation_params() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }

  check_admin_referer( 'acdc_save_attestation_params' );

  $defaults = $this->get_attestation_params_defaults();
  $input  = isset( $_POST['attestation_params'] ) && is_array( $_POST['attestation_params'] ) ? wp_unslash( $_POST['attestation_params'] ) : array();
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
  update_option( 'acdc_of_attestation_params', array( 'additional_sections' => $sections ), false );
  wp_cache_delete( 'acdc_of_attestation_params', 'options' );

  $return_context = isset( $_POST['return_context'] ) ? sanitize_key( wp_unslash( $_POST['return_context'] ) ) : 'front';
  if ( 'admin' === $return_context ) {
    wp_safe_redirect( $this->admin_tab_url( 'attestation_parameters', array( 'updated' => 1 ) ) );
    exit;
  }

  wp_safe_redirect( $this->portal_page_url( array(
    'tab' => 'attestation_parameters',
    'updated' => 1,
    'notice' => rawurlencode( 'Paramètres des attestations enregistrés.' ),
    'notice_type' => 'success',
  ) ) );
  exit;
}public function handle_download_training_convocation_document() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  $registration_id = isset( $_GET['registration_id'] ) ? absint( wp_unslash( $_GET['registration_id'] ) ) : 0;
  if ( ! $registration_id ) {
    wp_die( esc_html( 'Convocation introuvable.' ) );
  }
  check_admin_referer( 'acdc_download_training_convocation_document_' . $registration_id );
  $registration = $this->get_training_registration( $registration_id );
  if ( ! $registration ) {
    wp_die( esc_html( 'Convocation introuvable.' ) );
  }
  $context = $this->get_training_convocation_context( $registration );
  $document = $context['document'];
  $mode = isset( $_GET['mode'] ) && 'inline' === sanitize_key( wp_unslash( $_GET['mode'] ) ) ? 'inline' : 'attachment';
  $filename = $this->get_training_convocation_display_file_name( $registration, $context, $document );
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
  $pages = $this->build_training_convocation_pdf_pages( $registration, $context );
  $this->render_simple_pdf( $pages, $filename, $mode );
}public function handle_update_training_convocation_document() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  $registration_id = isset( $_POST['registration_id'] ) ? absint( wp_unslash( $_POST['registration_id'] ) ) : 0;
  if ( ! $registration_id ) {
    wp_die( esc_html( 'Convocation introuvable.' ) );
  }
  check_admin_referer( 'acdc_update_training_convocation_document_' . $registration_id );
  if ( empty( $_FILES['training_convocation_document_file']['name'] ) ) {
    wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'training_convocations', 'notice' => rawurlencode( 'Aucun fichier reçu.' ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
    exit;
  }
  require_once ABSPATH . 'wp-admin/includes/file.php';
  $upload = wp_handle_upload( $_FILES['training_convocation_document_file'], array( 'test_form' => false, 'mimes' => array( 'pdf' => 'application/pdf' ) ) );
  if ( ! empty( $upload['error'] ) ) {
    wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'training_convocations', 'notice' => rawurlencode( 'Téléversement impossible : ' . $upload['error'] ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
    exit;
  }
  global $wpdb;
  $result = $wpdb->update(
    $this->training_registration_table,
    array(
      'convocation_document_url' => isset( $upload['url'] ) ? esc_url_raw( $upload['url'] ) : '',
      'convocation_document_path' => isset( $upload['file'] ) ? sanitize_text_field( $upload['file'] ) : '',
      'updated_at' => $this->now_mysql(),
    ),
    array( 'id' => $registration_id )
  );
  if ( false === $result ) {
    wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'training_convocations', 'notice' => rawurlencode( 'Erreur lors de la mise à jour.' ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
    exit;
  }
  wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'training_convocations', 'notice' => rawurlencode( 'Convocation mise à jour.' ), 'notice_type' => 'success' ), admin_url( 'admin.php' ) ) );
  exit;
}public function handle_create_blank_attendance_sheet() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  check_admin_referer( 'acdc_create_blank_attendance_sheet' );
  $input = isset( $_POST['blank_attendance'] ) && is_array( $_POST['blank_attendance'] ) ? wp_unslash( $_POST['blank_attendance'] ) : array();
  $learner_count = isset( $input['count'] ) ? absint( $input['count'] ) : 0;
  if ( $learner_count < 1 ) {
    wp_die( esc_html( 'Le nombre d\'apprenants est obligatoire.' ) );
  }
  $pages = $this->build_blank_attendance_pdf_pages( $learner_count );
  $filename = 'feuille-emargement-vierge-' . date_i18n( 'Ymd-His' ) . '.pdf';
  $this->render_simple_pdf( $pages, $filename, 'attachment' );
}

public function handle_create_manual_backup() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }

  check_admin_referer( 'acdc_create_manual_backup' );

  $result = $this->create_manual_backup_snapshot( 'settings', array( 'user_id' => get_current_user_id() ) );
  if ( empty( $result['success'] ) ) {
    $this->redirect_to_portal( 'settings', 'Impossible de créer la sauvegarde manuelle.', 'error' );
  }

  $message = 'Sauvegarde manuelle créée.';
  if ( ! empty( $result['archive'] ) ) {
    $message .= ' Archive prête au téléchargement.';
  }
  $this->redirect_to_portal( 'settings', $message, 'success' );
}

public function handle_download_backup() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }

  check_admin_referer( 'acdc_download_backup' );

  $relative = isset( $_GET['file'] ) ? sanitize_text_field( wp_unslash( $_GET['file'] ) ) : '';
  $path = $this->get_backup_absolute_path( $relative );
  if ( '' === $path || ! is_file( $path ) ) {
    wp_die( esc_html( 'Sauvegarde introuvable.' ) );
  }

  $this->send_file_download_response( $path, 'application/zip', basename( $path ) );
}

public function handle_restore_backup_import() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }

  check_admin_referer( 'acdc_restore_backup_import' );

  if ( empty( $_FILES['acdc_backup_import_file']['name'] ) ) {
    $this->redirect_to_portal( 'settings', 'Aucun fichier de sauvegarde reçu.', 'error' );
  }

  require_once ABSPATH . 'wp-admin/includes/file.php';
  $upload = wp_handle_upload( $_FILES['acdc_backup_import_file'], array(
    'test_form' => false,
    'mimes' => array( 'zip' => 'application/zip' ),
  ) );

  if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
    $this->redirect_to_portal( 'settings', 'Import impossible : fichier ZIP invalide.', 'error' );
  }

  if ( ! class_exists( 'ZipArchive' ) ) {
    @unlink( $upload['file'] );
    $this->redirect_to_portal( 'settings', 'Restauration impossible : support ZIP indisponible sur le serveur.', 'error' );
  }

  $extract_dir = trailingslashit( dirname( $upload['file'] ) ) . 'acdc-restore-' . time() . '-' . wp_generate_password( 6, false, false );
  wp_mkdir_p( $extract_dir );

  $zip = new ZipArchive();
  if ( true !== $zip->open( $upload['file'] ) ) {
    @unlink( $upload['file'] );
    $this->redirect_to_portal( 'settings', 'Impossible d’ouvrir l’archive de sauvegarde.', 'error' );
  }
  // Sécurité — protection contre le Zip-Slip (path traversal) : rejeter toute entrée
  // dont le nom contient « .. » ou commence par un slash absolu.
  for ( $i = 0; $i < $zip->numFiles; $i++ ) {
    $entry_name = $zip->getNameIndex( $i );
    if ( false === $entry_name
      || false !== strpos( $entry_name, '..' )
      || 0 === strpos( $entry_name, '/' )
      || 0 === strpos( $entry_name, '\\' ) ) {
      $zip->close();
      @unlink( $upload['file'] );
      $this->redirect_to_portal( 'settings', 'Archive de sauvegarde invalide (chemin non autorisé).', 'error' );
    }
  }
  $zip->extractTo( $extract_dir );
  $zip->close();

  $manifest_path = trailingslashit( $extract_dir ) . 'manifest.json';
  if ( ! file_exists( $manifest_path ) ) {
    $candidates = glob( trailingslashit( $extract_dir ) . '*/manifest.json' );
    if ( is_array( $candidates ) && ! empty( $candidates[0] ) ) {
      $manifest_path = $candidates[0];
    }
  }

  $result = $this->restore_backup_snapshot_from_manifest( $manifest_path );

  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator( $extract_dir, FilesystemIterator::SKIP_DOTS ),
    RecursiveIteratorIterator::CHILD_FIRST
  );
  foreach ( $iterator as $file ) {
    if ( $file->isDir() ) {
      @rmdir( $file->getPathname() );
    } else {
      @unlink( $file->getPathname() );
    }
  }
  @rmdir( $extract_dir );
  @unlink( $upload['file'] );

  if ( is_wp_error( $result ) ) {
    $this->redirect_to_portal( 'settings', 'Restauration impossible : ' . $result->get_error_message(), 'error' );
  }

  /* ACDC 3.25.179 — L'écran annonçait « restaurée avec succès » quel que soit le
     nombre de lignes réellement remises. Une table vidée puis refusée en insertion
     passait donc pour une réussite, et l'utilisateur ne découvrait le trou que des
     jours plus tard, quand il cherchait une donnée. On nomme les tables incomplètes. */
  if ( ! empty( $result['partial'] ) && ! empty( $result['failed'] ) ) {
    $this->redirect_to_portal(
      'settings',
      'Restauration TERMINÉE MAIS INCOMPLÈTE. Tables non entièrement restaurées : '
        . implode( ', ', array_map( 'sanitize_text_field', (array) $result['failed'] ) )
        . '. Conservez l\'archive : ces lignes n\'ont pas été remises en base.',
      'warning'
    );
  }

  if ( ! empty( $result['adapted'] ) ) {
    // ACDC 3.25.180 — On dit aussi ce qui a été restauré en s'adaptant au schéma.
    $this->redirect_to_portal(
      'settings',
      'Sauvegarde restaurée. Colonnes absentes de la base, ignorées : '
        . implode( ' · ', array_map( 'sanitize_text_field', (array) $result['adapted'] ) ),
      'warning'
    );
  }

  $this->redirect_to_portal( 'settings', 'Sauvegarde importée et restaurée avec succès.', 'success' );
}

/**
 * ACDC 3.25.184 — Relance manuelle de la migration de schéma.
 *
 * Appelée depuis l'avertissement d'administration, après trois tentatives
 * automatiques infructueuses. On efface le verrou et le drapeau d'abandon, puis
 * on rejoue install_or_update() — sans nouvel instantané préalable : celui de la
 * première tentative est déjà sur le disque, et c'est justement lui qui coûte le
 * plus cher.
 */
public function handle_retry_upgrade() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Action non autorisée.' );
  }
  check_admin_referer( 'acdc_retry_upgrade' );

  global $wpdb;
  $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", 'acdc_of_upgrade_lock' ) );
  wp_cache_delete( 'acdc_of_upgrade_lock', 'options' );
  wp_cache_delete( 'notoptions', 'options' );
  delete_option( 'acdc_of_upgrade_blocked' );

  $this->install_or_update();
  $this->ensure_default_pages();

  $done = ( ACDC_OF_SAAS_VERSION === get_option( 'acdc_of_saas_version' ) );
  $this->redirect_to_portal(
    'settings',
    $done ? 'Migration de base rejouée avec succès.' : 'La migration n\'a pas abouti : consultez le journal des erreurs.',
    $done ? 'success' : 'error'
  );
}

public function handle_purge_plugin_data() {
  if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }

  check_admin_referer( 'acdc_purge_plugin_data' );

  if ( $this->is_production_purge_blocked() ) {
    /* ACDC 3.25.210 — Le message disait le blocage sans dire comment le lever.
       Un refus qui n'indique pas la sortie oblige à chercher dans le code. */
    $this->redirect_to_portal(
      'settings',
      'Purge bloquée : environnement de production détecté. WordPress répond « production » tant qu’aucun environnement n’est déclaré. Cochez « Autoriser la purge en production » dans ACDC → Configuration, ou déclarez WP_ENVIRONMENT_TYPE dans wp-config.php.',
      'error'
    );
  }

  $ack = isset( $_POST['acdc_purge_acknowledge'] ) ? sanitize_text_field( wp_unslash( $_POST['acdc_purge_acknowledge'] ) ) : '';
  if ( 'yes' !== $ack ) {
    $this->redirect_to_portal( 'settings', 'Confirmation de sécurité incomplète. Cochez la validation finale.', 'error' );
  }

  $confirm = isset( $_POST['acdc_purge_confirm'] ) ? strtoupper( trim( sanitize_text_field( wp_unslash( $_POST['acdc_purge_confirm'] ) ) ) ) : '';
  $expected = strtoupper( $this->get_plugin_data_purge_confirmation_phrase() );
  if ( $confirm !== $expected ) {
    $this->redirect_to_portal( 'settings', 'Confirmation incorrecte. Saisissez exactement « ' . $this->get_plugin_data_purge_confirmation_phrase() . ' ».', 'error' );
  }

  /* ACDC 3.25.178 — La sauvegarde de sécurité était CRÉÉE, puis on continuait quoi
     qu'il arrive. La case à cocher demandait à l'utilisateur de « confirmer avoir
     vérifié la sauvegarde de sécurité », déclaration que rien ne vérifiait — et
     l'écran affichait par ailleurs « Dernière sauvegarde de sécurité : Aucune ». On
     faisait donc confirmer l'existence d'une sauvegarde que l'écran lui-même
     déclarait inexistante. Désormais : si le filet n'a pas été tendu, on ne saute
     pas. */
  $safety_backup = $this->create_safety_backup_snapshot( 'purge_plugin_data', array( 'user_id' => get_current_user_id() ) );
  if ( empty( $safety_backup ) ) {
    $this->redirect_to_portal( 'settings', "Purge annulée : la sauvegarde de sécurité n'a pas pu être créée. Aucune donnée n'a été touchée.", 'error' );
  }

  global $wpdb;

  $preserve = array(
    'company_profile'         => get_option( 'acdc_of_company_profile', array() ),
    'branding'                => get_option( 'acdc_of_branding', array() ),
    'billing_settings'        => get_option( 'acdc_of_billing_settings', array() ),
    'catalog_settings'        => get_option( 'acdc_of_catalog_settings', array() ),
    'contract_params'         => get_option( 'acdc_of_contract_params', array() ),
    'subcontract_params'      => get_option( 'acdc_of_subcontract_params', array() ),
    'convocation_params'      => get_option( 'acdc_of_convocation_params', array() ),
    'attestation_params'      => get_option( 'acdc_of_attestation_params', array() ),
    'questionnaire_settings'  => get_option( 'acdc_of_questionnaire_settings', array() ),
    'marketing_settings'      => get_option( 'acdc_of_marketing_settings', array() ),
    'agent_audit_settings'    => get_option( 'acdc_of_agent_audit_settings', array() ),
    'catalog_page_id'         => (int) get_option( 'acdc_of_catalog_page_id', 0 ),
    'portal_page_id'          => (int) get_option( 'acdc_of_portal_page_id', 0 ),
    'login_page_id'           => (int) get_option( 'acdc_of_login_page_id', 0 ),
    'extranet_parent_page_id' => (int) get_option( 'acdc_of_extranet_parent_page_id', 0 ),
    'extranet_login_page_id'  => (int) get_option( 'acdc_of_extranet_login_page_id', 0 ),
    'extranet_dashboard_page_id' => (int) get_option( 'acdc_of_extranet_dashboard_page_id', 0 ),
    'extranet_needs_page_id'  => (int) get_option( 'acdc_of_extranet_needs_page_id', 0 ),
    'extranet_prospects_page_id' => (int) get_option( 'acdc_of_extranet_prospects_page_id', 0 ),
    'extranet_learners_page_id' => (int) get_option( 'acdc_of_extranet_learners_page_id', 0 ),
    'extranet_companies_page_id' => (int) get_option( 'acdc_of_extranet_companies_page_id', 0 ),
    'extranet_contacts_page_id' => (int) get_option( 'acdc_of_extranet_contacts_page_id', 0 ),
    'extranet_documents_page_id' => (int) get_option( 'acdc_of_extranet_documents_page_id', 0 ),
    'extranet_settings_page_id' => (int) get_option( 'acdc_of_extranet_settings_page_id', 0 ),
    'questionnaire_public_page_id' => (int) get_option( 'acdc_of_questionnaire_public_page_id', 0 ),
    'questionnaire_session_page_id' => (int) get_option( 'acdc_of_questionnaire_session_page_id', 0 ),
    'marketing_public_page_id' => (int) get_option( 'acdc_of_marketing_public_page_id', 0 ),
    'signature_page_id'       => (int) get_option( 'acdc_sig_page_id', 0 ),
    'signature_settings'      => get_option( 'acdc_sig_settings', array() ),
    /* ACDC 3.25.178 — Le POINTEUR vers la sauvegarde de sécurité était emporté par
       l'effacement des options acdc_of_%. Le plugin tendait donc le filet, puis
       effaçait l'adresse qui permettait de le retrouver : l'écran affichait ensuite
       « Dernière sauvegarde de sécurité : Aucune », et l'utilisateur en concluait
       qu'il n'avait rien à restaurer. C'est le détail qui décide si quelqu'un
       récupère ses données ou y renonce. */
    'last_safety_backup_file' => get_option( 'acdc_of_last_safety_backup_file', '' ),
    'last_safety_backup_at'   => get_option( 'acdc_of_last_safety_backup_at', '' ),
    'last_manual_backup_file' => get_option( 'acdc_of_last_manual_backup_file', '' ),
    'last_manual_backup_at'   => get_option( 'acdc_of_last_manual_backup_at', '' ),
    'last_backup_file'        => get_option( 'acdc_of_last_backup_file', '' ),
    'last_backup_at'          => get_option( 'acdc_of_last_backup_at', '' ),
  );

  $paths = $this->collect_plugin_generated_file_paths();
  $deleted_files = $this->delete_plugin_generated_files( $paths );

  $tables = $this->get_plugin_data_purge_table_names();
  foreach ( $tables as $table ) {
    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    if ( $exists !== $table ) {
      continue;
    }
    $wpdb->query( "DELETE FROM {$table}" );
    $wpdb->query( "ALTER TABLE {$table} AUTO_INCREMENT = 1" );
  }

  $options_table = $wpdb->options;
  $wpdb->query( "DELETE FROM {$options_table} WHERE option_name LIKE 'acdc_of_%'" );
  $wpdb->query( "DELETE FROM {$options_table} WHERE option_name LIKE 'acdc_sig_%'" );

  update_option( 'acdc_of_saas_version', ACDC_OF_SAAS_VERSION, false );
  /* ACDC 3.25.178 — On rétablit d'abord les pointeurs de sauvegarde : ce sont eux
     qui permettent de revenir en arrière si la purge n'était pas voulue. */
  foreach ( array(
    'acdc_of_last_safety_backup_file' => 'last_safety_backup_file',
    'acdc_of_last_safety_backup_at'   => 'last_safety_backup_at',
    'acdc_of_last_manual_backup_file' => 'last_manual_backup_file',
    'acdc_of_last_manual_backup_at'   => 'last_manual_backup_at',
    'acdc_of_last_backup_file'        => 'last_backup_file',
    'acdc_of_last_backup_at'          => 'last_backup_at',
  ) as $opt => $key ) {
    if ( '' !== $preserve[ $key ] ) {
      update_option( $opt, $preserve[ $key ], false );
    }
  }

  update_option( 'acdc_of_company_profile', $preserve['company_profile'], false );
  update_option( 'acdc_of_branding', $preserve['branding'], false );
  update_option( 'acdc_of_billing_settings', $preserve['billing_settings'], false );
  update_option( 'acdc_of_catalog_settings', $preserve['catalog_settings'], false );
  update_option( 'acdc_of_contract_params', $preserve['contract_params'], false );
  update_option( 'acdc_of_subcontract_params', $preserve['subcontract_params'], false );
  update_option( 'acdc_of_convocation_params', $preserve['convocation_params'], false );
  update_option( 'acdc_of_attestation_params', $preserve['attestation_params'], false );
  update_option( 'acdc_of_questionnaire_settings', $preserve['questionnaire_settings'], false );
  update_option( 'acdc_of_marketing_settings', $preserve['marketing_settings'], false );
  update_option( 'acdc_of_agent_audit_settings', $preserve['agent_audit_settings'], false );
  update_option( 'acdc_of_documents_billing_demo_enabled', '0', false );

  foreach ( array(
    'acdc_of_catalog_page_id' => $preserve['catalog_page_id'],
    'acdc_of_portal_page_id' => $preserve['portal_page_id'],
    'acdc_of_login_page_id' => $preserve['login_page_id'],
    'acdc_of_extranet_parent_page_id' => $preserve['extranet_parent_page_id'],
    'acdc_of_extranet_login_page_id' => $preserve['extranet_login_page_id'],
    'acdc_of_extranet_dashboard_page_id' => $preserve['extranet_dashboard_page_id'],
    'acdc_of_extranet_needs_page_id' => $preserve['extranet_needs_page_id'],
    'acdc_of_extranet_prospects_page_id' => $preserve['extranet_prospects_page_id'],
    'acdc_of_extranet_learners_page_id' => $preserve['extranet_learners_page_id'],
    'acdc_of_extranet_companies_page_id' => $preserve['extranet_companies_page_id'],
    'acdc_of_extranet_contacts_page_id' => $preserve['extranet_contacts_page_id'],
    'acdc_of_extranet_documents_page_id' => $preserve['extranet_documents_page_id'],
    'acdc_of_extranet_settings_page_id' => $preserve['extranet_settings_page_id'],
    'acdc_of_questionnaire_public_page_id' => $preserve['questionnaire_public_page_id'],
    'acdc_of_questionnaire_session_page_id' => $preserve['questionnaire_session_page_id'],
    'acdc_of_marketing_public_page_id' => $preserve['marketing_public_page_id'],
    'acdc_sig_page_id' => $preserve['signature_page_id'],
  ) as $option_name => $option_value ) {
    if ( ! empty( $option_value ) ) {
      update_option( $option_name, $option_value, false );
    }
  }

  update_option( 'acdc_sig_settings', $preserve['signature_settings'], false );

  if ( function_exists( 'delete_transient' ) ) {
    delete_transient( 'acdc_of_saas_runtime_notice' );
  }

  if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
    // ACDC 3.25.115 — déprogrammer TOUS les crons (aligné sur la désactivation).
    wp_clear_scheduled_hook( 'acdc_sig_cron_relances' );
    wp_clear_scheduled_hook( 'acdc_of_surveys_cron_dispatches' );
    wp_clear_scheduled_hook( 'acdc_of_surveys_cron_reminders' );
    wp_clear_scheduled_hook( 'acdc_of_surveys_cron_expirations' );
    wp_clear_scheduled_hook( 'acdc_of_surveys_cron_action_followups' );
    wp_clear_scheduled_hook( 'acdc_of_learner_portal_cron_maintenance' );
    wp_clear_scheduled_hook( 'acdc_nad_cron_send_and_relance' );
    wp_clear_scheduled_hook( 'acdc_of_cron_push_indicators' );
    wp_clear_scheduled_hook( 'acdc_of_cron_sync_formations' );
    wp_clear_scheduled_hook( 'acdc_of_absence_alert_cron' );
    wp_clear_scheduled_hook( 'acdc_of_invoices_overdue_cron' ); // ACDC 3.25.118
    wp_clear_scheduled_hook( 'acdc_of_retention_scan_cron' ); // ACDC 3.25.130
    wp_clear_scheduled_hook( 'acdc_of_session_close_cron' );
    wp_clear_scheduled_hook( 'acdc_of_convocation_cron' );
    wp_clear_scheduled_hook( 'acdc_of_positioning_test_cron' );
    wp_clear_scheduled_hook( 'acdc_of_qualiopi_alerts_cron' );
    wp_clear_scheduled_hook( 'acdc_of_trainer_portal_cron_maintenance' );
    wp_clear_scheduled_hook( 'acdc_of_qz_cron_dispatches' );
    wp_clear_scheduled_hook( 'acdc_of_qz_cron_reminders' );
    wp_clear_scheduled_hook( 'acdc_of_qz_cron_expirations' );
    wp_clear_scheduled_hook( 'acdc_of_qz_cron_close_inactive_sessions' );
    wp_clear_scheduled_hook( 'acdc_of_qz_cron_rgpd_purge' );
    wp_clear_scheduled_hook( 'acdc_of_watch_collect_cron' );
    wp_clear_scheduled_hook( 'acdc_of_watch_analyze_cron' );
    wp_clear_scheduled_hook( 'acdc_of_watch_digest_cron' );
    wp_clear_scheduled_hook( 'acdc_of_watch_reminder_cron' );
  }

  $agent_users = get_users( array(
    'meta_key'   => '_acdc_agent_audit_managed',
    'meta_value' => '1',
    'fields'     => 'ids',
    'number'     => 200,
  ) );
  if ( ! function_exists( 'wp_delete_user' ) ) {
    require_once ABSPATH . 'wp-admin/includes/user.php';
  }
  foreach ( $agent_users as $user_id ) {
    $user_id = (int) $user_id;
    if ( $user_id > 0 && $user_id !== get_current_user_id() ) {
      wp_delete_user( $user_id );
    }
  }

  $this->ensure_default_pages();
  $this->ensure_questionnaire_public_page();
  $this->ensure_marketing_public_page();
  $this->init_marketing_module_defaults();

  $backup_message = ! empty( $safety_backup['manifest'] ) ? ' Sauvegarde de sécurité : ' . $safety_backup['manifest'] . '.' : '';
  $message = sprintf( 'Toutes les données du plugin ont été supprimées. %d fichier(s) généré(s) supprimé(s).', (int) $deleted_files ) . $backup_message;
  $this->log_action_event( 'purge_plugin_data', 'settings', 0, 'success', array( 'deleted_files' => (int) $deleted_files ) );

  $is_admin_page = is_admin() && isset( $_POST['page'] ) && 'acdc-of-dashboard' === sanitize_key( wp_unslash( $_POST['page'] ) );
  if ( $is_admin_page ) {
    wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'settings', 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ), admin_url( 'admin.php' ) ) );
    exit;
  }

  $this->redirect_to_portal( 'settings', $message, 'success' );
}

  /* =====================================================================
   * ACDC 3.21.10 — CRUD Bibliothèque blocs & questions
   * ===================================================================== */

  public function handle_save_need_block() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_save_need_block' );
    global $wpdb;
    $is_admin_page = is_admin() && isset( $_POST['page'] ) && 'acdc-of-need-analyses' === $_POST['page'];
    $block_id  = isset( $_POST['block_id'] ) ? absint( wp_unslash( $_POST['block_id'] ) ) : 0;
    $nom       = isset( $_POST['nom'] )       ? sanitize_text_field( wp_unslash( $_POST['nom'] ) )       : '';
    $type_bloc = isset( $_POST['type_bloc'] ) ? sanitize_text_field( wp_unslash( $_POST['type_bloc'] ) ) : 'personnalise';
    $profil    = isset( $_POST['profil_lie'] )? sanitize_text_field( wp_unslash( $_POST['profil_lie'] ) ): null;
    $thematique= isset( $_POST['thematique_liee'] ) ? sanitize_text_field( wp_unslash( $_POST['thematique_liee'] ) ) : null;
    $desc      = isset( $_POST['description'] )     ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) )  : '';
    $ordre     = isset( $_POST['ordre'] )     ? absint( wp_unslash( $_POST['ordre'] ) )  : 10;

    if ( '' === $nom ) {
      $this->redirect_to_nad( 'blocks', $is_admin_page, array( 'notice' => rawurlencode( 'Le nom du bloc est obligatoire.' ), 'notice_type' => 'error' ) );
      return;
    }
    $valid_types = array( 'commun', 'profil', 'thematique', 'formation', 'personnalise' );
    if ( ! in_array( $type_bloc, $valid_types, true ) ) { $type_bloc = 'personnalise'; }
    $profil_clean    = ( '' === (string) $profil )     ? null : $profil;
    $thematique_clean= ( '' === (string) $thematique ) ? null : $thematique;

    $now = current_time( 'mysql' );
    if ( $block_id ) {
      $data = array( 'nom' => $nom, 'type_bloc' => $type_bloc, 'profil_lie' => $profil_clean, 'thematique_liee' => $thematique_clean, 'description' => $desc, 'ordre' => $ordre, 'updated_at' => $now );
      $result  = $wpdb->update( $this->need_block_table, $data, array( 'id' => $block_id ) );
      $message = 'Bloc mis à jour.';
    } else {
      $code = sanitize_key( str_replace( ' ', '_', strtolower( $nom ) ) ) . '_' . time();
      $code = substr( $code, 0, 60 );
      $data = array( 'code' => $code, 'nom' => $nom, 'type_bloc' => $type_bloc, 'profil_lie' => $profil_clean, 'thematique_liee' => $thematique_clean, 'description' => $desc, 'ordre' => $ordre, 'actif' => 1, 'verrouille' => 0, 'created_at' => $now, 'updated_at' => $now );
      $result  = $wpdb->insert( $this->need_block_table, $data );
      $message = 'Bloc créé.';
    }
    $error = false === $result ? 'Erreur lors de l\'enregistrement du bloc.' : '';
    $this->redirect_to_nad( 'blocks', $is_admin_page, array( 'notice' => rawurlencode( $error ?: $message ), 'notice_type' => $error ? 'error' : 'success' ) );
  }

  public function handle_delete_need_block() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    global $wpdb;
    $block_id = isset( $_GET['block_id'] ) ? absint( wp_unslash( $_GET['block_id'] ) ) : 0;
    check_admin_referer( 'acdc_delete_need_block_' . $block_id );
    $is_admin_page = is_admin();
    if ( ! $block_id ) {
      $this->redirect_to_nad( 'blocks', $is_admin_page, array( 'notice' => rawurlencode( 'Identifiant invalide.' ), 'notice_type' => 'error' ) );
      return;
    }
    $bloc = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->need_block_table} WHERE id = %d", $block_id ) );
    if ( ! $bloc ) {
      $this->redirect_to_nad( 'blocks', $is_admin_page, array( 'notice' => rawurlencode( 'Bloc introuvable.' ), 'notice_type' => 'error' ) );
      return;
    }
    if ( (int) $bloc->verrouille ) {
      $this->redirect_to_nad( 'blocks', $is_admin_page, array( 'notice' => rawurlencode( 'Ce bloc système ne peut pas être supprimé. Vous pouvez le désactiver.' ), 'notice_type' => 'error' ) );
      return;
    }
    $now = current_time( 'mysql' );
    $wpdb->update( $this->need_block_table, array( 'actif' => 0, 'updated_at' => $now ), array( 'id' => $block_id ) );
    $this->redirect_to_nad( 'blocks', $is_admin_page, array( 'notice' => rawurlencode( 'Bloc désactivé.' ), 'notice_type' => 'success' ) );
  }

  public function handle_save_need_question() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_save_need_question' );
    global $wpdb;
    $is_admin_page = is_admin() && isset( $_POST['page'] ) && 'acdc-of-need-analyses' === $_POST['page'];
    $question_id = isset( $_POST['question_id'] ) ? absint( wp_unslash( $_POST['question_id'] ) ) : 0;
    $bloc_id     = isset( $_POST['bloc_id'] )     ? absint( wp_unslash( $_POST['bloc_id'] ) )     : 0;
    $libelle     = isset( $_POST['libelle'] )      ? sanitize_text_field( wp_unslash( $_POST['libelle'] ) )  : '';
    $type_rep    = isset( $_POST['type_reponse'] ) ? sanitize_text_field( wp_unslash( $_POST['type_reponse'] ) ) : 'texte_court';
    $obligatoire = isset( $_POST['obligatoire'] )  ? 1 : 0;
    $ordre       = isset( $_POST['ordre'] )        ? absint( wp_unslash( $_POST['ordre'] ) ) : 10;
    $aide        = isset( $_POST['aide'] )         ? sanitize_textarea_field( wp_unslash( $_POST['aide'] ) ) : null;
    $placeholder = isset( $_POST['placeholder'] )  ? sanitize_text_field( wp_unslash( $_POST['placeholder'] ) ) : null;
    $source_pref = isset( $_POST['source_prefill'] ) ? sanitize_text_field( wp_unslash( $_POST['source_prefill'] ) ) : null;

    // Options (textarea nad_options_raw → JSON)
    $options_raw_text = isset( $_POST['nad_options_raw'] ) ? sanitize_textarea_field( wp_unslash( $_POST['nad_options_raw'] ) ) : '';
    $options_lines    = array_filter( array_map( 'trim', explode( "\n", $options_raw_text ) ) );
    $options_clean    = array();
    foreach ( $options_lines as $opt ) {
      $o = sanitize_text_field( $opt );
      if ( '' !== $o ) { $options_clean[] = $o; }
    }
    $options_json = ! empty( $options_clean ) ? wp_json_encode( $options_clean, JSON_UNESCAPED_UNICODE ) : null;

    $valid_types = array( 'texte_court','texte_long','choix_unique','choix_multiple','nombre','date','email','telephone','consentement','url' );
    if ( ! in_array( $type_rep, $valid_types, true ) ) { $type_rep = 'texte_court'; }

    if ( '' === $libelle || ! $bloc_id ) {
      $redir = array( 'nad_subtab' => 'block_detail', 'nad_block_id' => $bloc_id, 'notice' => rawurlencode( 'Le libellé et le bloc sont obligatoires.' ), 'notice_type' => 'error' );
      $this->redirect_to_nad( '', $is_admin_page, $redir );
      return;
    }
    $now = current_time( 'mysql' );
    if ( $question_id ) {
      $data = array( 'libelle' => $libelle, 'type_reponse' => $type_rep, 'obligatoire' => $obligatoire, 'ordre' => $ordre, 'options' => $options_json, 'aide' => $aide ?: null, 'placeholder' => $placeholder ?: null, 'source_prefill' => $source_pref ?: null, 'updated_at' => $now );
      $wpdb->update( $this->need_question_table, $data, array( 'id' => $question_id ) );
      $message = 'Question mise à jour.';
    } else {
      $code = 'q_' . $bloc_id . '_' . time();
      $data = array( 'bloc_id' => $bloc_id, 'code' => $code, 'ordre' => $ordre, 'libelle' => $libelle, 'type_reponse' => $type_rep, 'obligatoire' => $obligatoire, 'options' => $options_json, 'aide' => $aide ?: null, 'placeholder' => $placeholder ?: null, 'source_prefill' => $source_pref ?: null, 'visible_admin' => 1, 'visible_public' => 1, 'afficher_pdf' => 1, 'actif' => 1, 'verrouille' => 0, 'created_at' => $now, 'updated_at' => $now );
      $wpdb->insert( $this->need_question_table, $data );
      $message = 'Question ajoutée.';
    }
    $this->redirect_to_nad( '', $is_admin_page, array( 'nad_subtab' => 'block_detail', 'nad_block_id' => $bloc_id, 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ) );
  }

  public function handle_toggle_need_question() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    global $wpdb;
    $question_id = isset( $_GET['question_id'] ) ? absint( wp_unslash( $_GET['question_id'] ) ) : 0;
    $bloc_id     = isset( $_GET['bloc_id'] )      ? absint( wp_unslash( $_GET['bloc_id'] ) )      : 0;
    check_admin_referer( 'acdc_toggle_need_question_' . $question_id );
    $is_admin_page = is_admin();
    $q = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->need_question_table} WHERE id = %d", $question_id ) );
    if ( ! $q ) {
      $this->redirect_to_nad( '', $is_admin_page, array( 'nad_subtab' => 'block_detail', 'nad_block_id' => $bloc_id, 'notice' => rawurlencode( 'Question introuvable.' ), 'notice_type' => 'error' ) );
      return;
    }
    $new_actif = ( (int) $q->actif ) ? 0 : 1;
    $wpdb->update( $this->need_question_table, array( 'actif' => $new_actif, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $question_id ) );
    $msg = $new_actif ? 'Question activée.' : 'Question désactivée.';
    $this->redirect_to_nad( '', $is_admin_page, array( 'nad_subtab' => 'block_detail', 'nad_block_id' => (int) $q->bloc_id, 'notice' => rawurlencode( $msg ), 'notice_type' => 'success' ) );
  }

  /** ACDC 3.21.29-hotfix4b — Suppression définitive d'une question de bloc. */
  public function handle_delete_need_question() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    global $wpdb;
    $question_id = isset( $_GET['question_id'] ) ? absint( wp_unslash( $_GET['question_id'] ) ) : 0;
    $bloc_id     = isset( $_GET['bloc_id'] )      ? absint( wp_unslash( $_GET['bloc_id'] ) )      : 0;
    check_admin_referer( 'acdc_delete_need_question_' . $question_id );
    $is_admin_page = is_admin();
    $q = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->need_question_table} WHERE id = %d", $question_id ) );
    if ( ! $q ) {
      $this->redirect_to_nad( '', $is_admin_page, array( 'nad_subtab' => 'block_detail', 'nad_block_id' => $bloc_id, 'notice' => rawurlencode( 'Question introuvable.' ), 'notice_type' => 'error' ) );
      return;
    }
    $wpdb->delete( $this->need_question_table, array( 'id' => $question_id ) );
    $this->redirect_to_nad( '', $is_admin_page, array( 'nad_subtab' => 'block_detail', 'nad_block_id' => (int) $q->bloc_id, 'notice' => rawurlencode( 'Question supprimée.' ), 'notice_type' => 'success' ) );
  }

  /** Redirection centralisée vers l'onglet need_analyses (admin ou front). */
  private function redirect_to_nad( $default_subtab, $is_admin_page, $extra = array() ) {
    if ( '' !== $default_subtab && ! isset( $extra['nad_subtab'] ) ) {
      $extra['nad_subtab'] = $default_subtab;
    }
    if ( $is_admin_page ) {
      $url = add_query_arg( array_merge( array( 'page' => 'acdc-of-need-analyses' ), $extra ), admin_url( 'admin.php' ) );
    } else {
      $url = $this->portal_page_url( array_merge( array( 'tab' => 'need_analyses' ), $extra ) );
    }
    wp_safe_redirect( $url );
    exit;
  }


  /* =====================================================================
   * ACDC 3.21.13 — Analyses du besoin : triggers + soumission publique.
   * ===================================================================== */

  /** Déclenché par acdc_sig_request_signed (prio 20) — convention signée. */
  public function handle_nad_auto_create_from_signature( $request_id, $request ) {
    global $wpdb;
    $request_id = absint( $request_id );
    if ( ! $request_id ) { return; }
    // Trouver la convention liée
    $contract = $wpdb->get_row( $wpdb->prepare(
      "SELECT * FROM {$this->registration_contract_table} WHERE signature_request_id = %d LIMIT 1",
      $request_id
    ) );
    if ( ! $contract ) { return; }
    // Vérifier que les analyses n'ont pas déjà été créées pour cette convention
    $existing = (int) $wpdb->get_var( $wpdb->prepare(
      "SELECT COUNT(*) FROM {$this->need_analysis_table} WHERE dossier_id = %d AND is_model = 0",
      (int) $contract->id
    ) );
    if ( $existing > 0 ) { return; }
    $this->nad_auto_create_from_contract( $contract, $request );
  }

  /** Soumission publique du formulaire analyse du besoin (nopriv). */
  public function handle_nad_public_submit() {
    // Pas de nonce (public) — sécurité assurée par le token unique
    $token     = isset( $_POST['adb_token'] ) ? sanitize_text_field( wp_unslash( $_POST['adb_token'] ) ) : '';
    if ( '' === $token ) { wp_send_json_error( array( 'message' => 'Token manquant.' ) ); }

    global $wpdb;
    $analysis = $wpdb->get_row( $wpdb->prepare(
      "SELECT * FROM {$this->need_analysis_table} WHERE token_public = %s LIMIT 1",
      $token
    ) );

    if ( ! $analysis ) {
      wp_send_json_error( array( 'message' => 'Formulaire introuvable ou lien invalide.' ) );
    }
    // ACDC 3.25.113 — comparer en heure WP (token stocké en local).
    if ( ! empty( $analysis->token_expire_at ) && strtotime( $analysis->token_expire_at ) < current_time( 'timestamp' ) ) {
      wp_send_json_error( array( 'message' => 'Ce lien a expiré. Contactez votre organisme de formation.' ) );
    }
    if ( 'traite' === $analysis->statut ) {
      wp_send_json_error( array( 'message' => 'Cette analyse a déjà été soumise.' ) );
    }

    // Lire les réponses POST
    $reponses_raw = isset( $_POST['reponses'] ) && is_array( $_POST['reponses'] ) ? wp_unslash( $_POST['reponses'] ) : array();
    $reponses = array();
    foreach ( $reponses_raw as $code => $val ) {
      $code_clean = sanitize_key( $code );
      if ( is_array( $val ) ) {
        $reponses[ $code_clean ] = array_map( 'sanitize_text_field', $val );
      } else {
        $reponses[ $code_clean ] = sanitize_textarea_field( (string) $val );
      }
    }

    $now = current_time( 'mysql' );
    $wpdb->update(
      $this->need_analysis_table,
      array(
        'reponses'   => wp_json_encode( $reponses, JSON_UNESCAPED_UNICODE ),
        'statut'     => 'traite',
        'sent_at'    => $now,
        'updated_at' => $now,
      ),
      array( 'id' => (int) $analysis->id )
    );

    // ACDC 3.21.29-hotfix4 — Propagation des corrections d'identité.
    // Si le répondant a corrigé son nom/prénom/email/téléphone, on met à jour :
    // 1. Les champs repondant_* de l'analyse elle-même.
    // 2. La fiche source (prospect ou apprenant) si elle existe.
    $identity_map = array(
      'com_nom'       => 'last_name',
      'com_prenom'    => 'first_name',
      'com_email'     => 'email',
      'com_telephone' => 'phone',
    );
    $analysis_updates = array();
    $source_updates   = array();
    foreach ( $identity_map as $question_code => $field ) {
      if ( ! isset( $reponses[ $question_code ] ) || '' === $reponses[ $question_code ] ) { continue; }
      $val = (string) $reponses[ $question_code ];
      // Mettre à jour le champ repondant_* sur l'analyse
      $analysis_col = 'phone' === $field ? null : ( 'last_name' === $field ? 'repondant_nom' : ( 'first_name' === $field ? 'repondant_prenom' : ( 'email' === $field ? 'repondant_email' : null ) ) );
      if ( $analysis_col ) { $analysis_updates[ $analysis_col ] = $val; }
      $source_updates[ $field ] = $val;
    }
    if ( ! empty( $analysis_updates ) ) {
      $analysis_updates['updated_at'] = $now;
      $wpdb->update( $this->need_analysis_table, $analysis_updates, array( 'id' => (int) $analysis->id ) );
    }
    // Mettre à jour la fiche source si présente
    if ( ! empty( $source_updates ) && ! empty( $analysis->source_type ) && ! empty( $analysis->source_id ) ) {
      $source_table = null;
      if ( 'prospect' === $analysis->source_type )   { $source_table = $this->prospect_table; }
      if ( 'apprenant' === $analysis->source_type )  { $source_table = $this->learner_table; }
      if ( 'entreprise' === $analysis->source_type ) { $source_table = null; } // pas de mise à jour entreprise sur identité individuelle
      if ( $source_table ) {
        $src_data = array_intersect_key( $source_updates, array_flip( array( 'last_name', 'first_name', 'email', 'phone' ) ) );
        if ( ! empty( $src_data ) ) {
          $src_data['updated_at'] = $now;
          $wpdb->update( $source_table, $src_data, array( 'id' => (int) $analysis->source_id ) );
        }
      }
    }

    // ACDC 3.21.29-hotfix4b — Notification interne : alerte l'admin/formateur que l'analyse a été soumise.
    $company_profile  = get_option( 'acdc_of_company_profile', array() );
    $internal_email   = ! empty( $company_profile['email'] ) ? sanitize_email( $company_profile['email'] ) : get_option( 'admin_email' );
    /* ACDC 3.25.211 — L'intitulé nomme l'entreprise, puis la personne, puis sa
       qualité : deux analyses d'une même signataire qui est aussi apprenante ne
       peuvent plus arriver sous le même titre. */
    $repondant_label  = $this->nad_analysis_display_label( $analysis );
    $analysis_url     = $this->portal_page_url( array( 'tab' => 'need_analyses', 'action' => 'view', 'item_id' => (int) $analysis->id ) );
    if ( $internal_email ) {
      $this->acdc_send_transactional_email(
        $internal_email,
        'Analyse du besoin soumise — ' . ( $repondant_label ?: 'Répondant inconnu' ),
        array(
          'greeting_name' => 'ACDC Formation',
          'intro_html'    => '',
          'summary_title' => 'ANALYSE DU BESOIN REÇUE',
          'summary_rows'  => array(
            array( 'label' => 'Répondant',  'value' => $repondant_label ?: '—' ),
            array( 'label' => 'E-mail',     'value' => (string) ( $analysis->repondant_email ?? '—' ) ),
            array( 'label' => 'Analyse',    'value' => (string) ( $analysis->title ?? '—' ) ),
            array( 'label' => 'Soumis le',  'value' => wp_date( 'j/m/Y à H\hi', current_time( 'timestamp' ) ) ),
          ),
          'body_html'     => '<p style="text-align:center;margin:24px 0;"><a href="' . esc_url( $analysis_url ) . '" style="display:inline-block;padding:12px 24px;background:#C5A253;color:#0B0706;text-decoration:none;border-radius:8px;font-weight:700;">Consulter l\'analyse du besoin</a></p>',
          'footer_notice' => 'Notification interne ACDC Formation — analyse du besoin complétée.',
        ),
        array(
          'source_module'         => 'kernel',
          'source_action'         => 'nad_submitted_internal',
          'related_entity_type'   => 'need_analysis',
          'related_entity_id'     => (int) $analysis->id,
          'email_category'        => 'notification_interne',
          'email_audience'        => 'interne',
        )
      );
    }

    wp_send_json_success( array( 'message' => 'Merci ! Vos réponses ont bien été enregistrées.' ) );
  }

  /** Filtre template_include — page publique NAD en plein écran sans thème. */
  public function nad_maybe_override_template( $template ) {
    $page_id = (int) get_option( 'acdc_of_nad_public_page_id', 0 );
    if ( ! $page_id ) { return $template; }
    if ( is_singular() && (int) get_the_ID() === $page_id ) {
      $standalone = ACDC_OF_SAAS_DIR . 'includes/nad/templates/standalone-nad-form.php';
      if ( file_exists( $standalone ) ) {
        return $standalone;
      }
    }
    return $template;
  }


  /* =====================================================================
   * ACDC 3.21.15 — Thématiques : save + delete.
   * ===================================================================== */

  public function handle_save_thematique() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
    check_admin_referer( 'acdc_save_thematique' );
    global $wpdb;
    $tid        = isset( $_POST['thematique_id'] ) ? absint( wp_unslash( $_POST['thematique_id'] ) ) : 0;
    $label      = isset( $_POST['th_label'] )       ? sanitize_text_field( wp_unslash( $_POST['th_label'] ) )       : '';
    $code       = isset( $_POST['th_code'] )        ? sanitize_key( wp_unslash( $_POST['th_code'] ) )               : '';
    $icon       = isset( $_POST['th_icon_emoji'] )  ? sanitize_text_field( wp_unslash( $_POST['th_icon_emoji'] ) )  : null;
    $couleur    = isset( $_POST['th_couleur_hex'] ) ? sanitize_hex_color( wp_unslash( $_POST['th_couleur_hex'] ) )  : null;
    $image_url  = isset( $_POST['th_image_hero_url'] ) ? esc_url_raw( wp_unslash( $_POST['th_image_hero_url'] ) )   : null;
    $bloc_nad   = isset( $_POST['th_bloc_nad_id'] ) ? absint( wp_unslash( $_POST['th_bloc_nad_id'] ) )              : null;
    $ordre      = isset( $_POST['th_ordre'] )       ? absint( wp_unslash( $_POST['th_ordre'] ) )                    : 10;
    $actif      = isset( $_POST['th_actif'] )       ? (int) $_POST['th_actif']                                       : 1;
    $base_url   = $this->acdc_thematiques_base_url();

    if ( '' === $label || '' === $code ) {
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Le code et le label sont obligatoires.' ), 'notice_type' => 'error' ), $base_url ) );
      exit;
    }

    $now  = current_time( 'mysql' );
    $data = array(
      'label'          => $label,
      'image_hero_url' => $image_url  ?: null,
      'icon_emoji'     => $icon       ?: null,
      'couleur_hex'    => $couleur    ?: null,
      'bloc_nad_id'    => $bloc_nad   ?: null,
      'ordre'          => $ordre,
      'actif'          => $actif,
      'updated_at'     => $now,
    );

    if ( $tid ) {
      $existing = $this->get_thematique_by_id( $tid );
      // Code non modifiable si verrouillé
      if ( $existing && ! (int) $existing->verrouille ) {
        $data['code'] = $code;
      }
      $wpdb->update( $this->thematique_table, $data, array( 'id' => $tid ) );
      $msg = 'Thématique mise à jour.';
    } else {
      $data['code']       = $code;
      $data['verrouille'] = 0;
      $data['created_at'] = $now;
      $wpdb->insert( $this->thematique_table, $data );
      $msg = 'Thématique créée.';
    }
    wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( $msg ), 'notice_type' => 'success' ), $base_url ) );
    exit;
  }

  /**
   * ACDC 3.25.148 — URL de retour de l'écran Thématiques.
   *
   * Corrige E2/E5 : le code utilisait `is_admin() ? admin_url('admin.php?page=acdc-of-thematiques') : …`.
   * Deux défauts cumulés :
   *   1. les actions passent par admin-post.php, qui EST dans /wp-admin/ → is_admin() est
   *      toujours vrai, donc la branche front-office n'était jamais empruntée ;
   *   2. la page « acdc-of-thematiques » n'est déclarée par aucun add_submenu_page().
   * Résultat : l'écriture réussissait mais l'utilisateur atterrissait sur
   * « Désolé, vous n'avez pas l'autorisation d'accéder à cette page » (403).
   *
   * On détecte donc la VRAIE origine via le paramètre `page` de la requête, et on
   * pointe vers un écran qui existe réellement.
   *
   * @return string URL de retour.
   */
  private function acdc_thematiques_base_url() {
    $page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : '';
    if ( '' !== $page && 0 === strpos( $page, 'acdc-of-' ) ) {
      // Origine wp-admin : l'écran Thématiques est un onglet du tableau de bord.
      return admin_url( 'admin.php?page=acdc-of-dashboard&tab=thematiques' );
    }
    return $this->portal_page_url( array( 'tab' => 'thematiques' ) );
  }

  public function handle_delete_thematique() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
    global $wpdb;
    $tid      = isset( $_GET['thematique_id'] ) ? absint( wp_unslash( $_GET['thematique_id'] ) ) : 0;
    check_admin_referer( 'acdc_delete_thematique_' . $tid );
    $base_url = $this->acdc_thematiques_base_url();
    $t = $this->get_thematique_by_id( $tid );
    if ( ! $t || (int) $t->verrouille ) {
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Impossible de supprimer cette thématique système.' ), 'notice_type' => 'error' ), $base_url ) );
      exit;
    }

    /* ACDC 3.25.148 — Garde-fou : refuser la suppression d'une thématique utilisée.
       Auparavant la suppression était inconditionnelle : les formations rattachées
       se retrouvaient avec une thématique orpheline, sans aucun avertissement. */
    /* La table des formations ne possède QUE la colonne `thematique` (qui stocke le
       CODE de la thématique). Une version antérieure de ce garde-fou testait aussi
       `thematique_liee`, colonne qui appartient en réalité à la table des blocs de
       recueil : MySQL levait « Unknown column », get_var() renvoyait null, et le
       compteur retombait à 0 — le garde-fou ne se déclenchait donc jamais. */
    $code_t = (string) $t->code;
    $used   = (int) $wpdb->get_var( $wpdb->prepare(
      "SELECT COUNT(*) FROM {$this->formation_table} WHERE thematique = %s",
      $code_t
    ) );
    if ( $used > 0 ) {
      $msg = sprintf(
        'Impossible de supprimer cette thématique : elle est utilisée par %d formation%s. Retirez-la de ces formations avant de la supprimer.',
        $used,
        $used > 1 ? 's' : ''
      );
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( $msg ), 'notice_type' => 'error' ), $base_url ) );
      exit;
    }

    $wpdb->delete( $this->thematique_table, array( 'id' => $tid ) );
    wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Thématique supprimée.' ), 'notice_type' => 'success' ), $base_url ) );
    exit;
  }


  /* =====================================================================
   * ACDC 3.21.17 — Relance manuelle analyse du besoin.
   * ===================================================================== */

  public function handle_nad_manual_relance() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
    global $wpdb;
    $analysis_id = isset( $_GET['analysis_id'] ) ? absint( wp_unslash( $_GET['analysis_id'] ) ) : 0;
    check_admin_referer( 'acdc_nad_manual_relance_' . $analysis_id );
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-need-analyses&nad_subtab=alerts' ) : $this->portal_page_url( array( 'tab' => 'need_analyses', 'nad_subtab' => 'alerts' ) );

    $analysis = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->need_analysis_table} WHERE id = %d", $analysis_id ) );
    if ( ! $analysis ) {
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Analyse introuvable.' ), 'notice_type' => 'error' ), $base_url ) );
      exit;
    }
    if ( 'traite' === $analysis->statut ) {
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Cette analyse a déjà été soumise.' ), 'notice_type' => 'info' ), $base_url ) );
      exit;
    }
    if ( empty( $analysis->repondant_email ) || ! is_email( $analysis->repondant_email ) ) {
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Adresse e-mail introuvable pour cette analyse.' ), 'notice_type' => 'error' ), $base_url ) );
      exit;
    }

    /* ACDC 3.25.228 — Même correction que sur le renvoi depuis la liste : on
       envoie, on constate, PUIS on enregistre et on annonce. Écrire la date de
       relance avant de savoir si le message est parti avançait le compteur
       pour rien et affichait un succès imaginaire. */
    $now = current_time( 'mysql' );
    $r1  = ! empty( $analysis->relance_1_at );
    $r2  = ! empty( $analysis->relance_2_at );
    $r3  = ! empty( $analysis->relance_3_at );

    if ( $r1 && $r2 && $r3 ) {
      wp_safe_redirect( add_query_arg( array(
        'notice'      => rawurlencode( 'Les 3 relances ont déjà été envoyées pour cette analyse.' ),
        'notice_type' => 'info',
      ), $base_url ) );
      exit;
    }

    if ( ! $r1 ) {
      $rank = 1;
      $column = 'relance_1_at';
    } elseif ( ! $r2 ) {
      $rank = 2;
      $column = 'relance_2_at';
    } else {
      $rank = 3;
      $column = 'relance_3_at';
    }

    if ( ! $this->nad_send_relance_email( $analysis, $rank ) ) {
      wp_safe_redirect( add_query_arg( array(
        'notice'      => rawurlencode( 'Aucun envoi : la relance n’est pas partie à ' . $analysis->repondant_email . '. Vérifiez l’adresse du répondant et, si le mode recette est actif, la liste des destinataires autorisés — le refus est journalisé.' ),
        'notice_type' => 'error',
      ), $base_url ) );
      exit;
    }

    $update = array( $column => $now, 'updated_at' => $now );
    if ( 1 === $rank ) {
      $update['statut'] = 'a_traiter';
    }
    $wpdb->update( $this->need_analysis_table, $update, array( 'id' => $analysis_id ) );

    $msg = ( 3 === $rank )
      ? 'Relance 3 (dernière) envoyée à ' . esc_html( $analysis->repondant_email ) . '.'
      : 'Relance ' . (int) $rank . ' envoyée à ' . esc_html( $analysis->repondant_email ) . '.';

    wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( $msg ), 'notice_type' => 'success' ), $base_url ) );
    exit;
  }

  /**
   * ACDC 3.21.29-hotfix4 — Renvoi manuel depuis la liste principale des analyses.
   * Même logique que handle_nad_manual_relance, redirige vers la liste (pas l'onglet alertes).
   */
  public function handle_nad_resend() {
    // ACDC 3.21.29-hotfix4 — fallback admin-post (admin WP uniquement).
    if ( ! is_user_logged_in() ) { wp_die( 'Accès refusé.' ); }
    if ( ! current_user_can( 'manage_options' ) && ! $this->is_admin_manager() ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    global $wpdb;
    $analysis_id = isset( $_GET['analysis_id'] ) ? absint( wp_unslash( $_GET['analysis_id'] ) ) : 0;
    $nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'acdc_nad_resend_' . $analysis_id ) ) {
      wp_die( 'Lien expiré ou invalide.', 'Erreur de sécurité', array( 'response' => 403 ) );
    }
    $base_url = is_admin()
      ? admin_url( 'admin.php?page=acdc-of-need-analyses' )
      : $this->portal_page_url( array( 'tab' => 'need_analyses' ) );
    $this->handle_nad_resend_inline( $analysis_id, $base_url );
  }

  // ACDC 3.21.29-hotfix4 — Logique métier du renvoi, appelable depuis le renderer de portail.
  public function handle_nad_resend_inline( $analysis_id, $base_url ) {
    global $wpdb;
    $analysis = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->need_analysis_table} WHERE id = %d", $analysis_id ) );
    if ( ! $analysis ) {
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Analyse introuvable.' ), 'notice_type' => 'error' ), $base_url ) );
      exit;
    }
    if ( 'traite' === $analysis->statut ) {
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Cette analyse a déjà été soumise.' ), 'notice_type' => 'info' ), $base_url ) );
      exit;
    }
    if ( empty( $analysis->repondant_email ) || ! is_email( $analysis->repondant_email ) ) {
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Adresse e-mail introuvable pour cette analyse.' ), 'notice_type' => 'error' ), $base_url ) );
      exit;
    }
    /* ACDC 3.25.228 — L'ÉCRAN ANNONÇAIT UN ENVOI QUI N'AVAIT PAS EU LIEU.
       « Action réussie — Relance 2 envoyée à … » s'affichait quoi qu'il
       arrive : la fonction d'envoi ne rendait rien, et l'on écrivait la date
       de relance en base avant même de savoir si le message était parti. Un
       destinataire refusé par le mode recette, un jeton absent, une adresse
       invalide — trois cas silencieux, trois avis de succès, et un compteur qui
       avançait pour rien.
       On envoie d'abord, on constate, puis seulement on enregistre et on
       annonce. Et l'on nomme le rang RÉELLEMENT joué : la version précédente
       annonçait « Relance 2 » sur le troisième envoi. */
    $now  = current_time( 'mysql' );
    $r1   = ! empty( $analysis->relance_1_at );
    $r2   = ! empty( $analysis->relance_2_at );
    $r3   = ! empty( $analysis->relance_3_at );

    if ( ! $r1 ) {
      $rank = 1;
      $column = 'relance_1_at';
    } elseif ( ! $r2 ) {
      $rank = 2;
      $column = 'relance_2_at';
    } else {
      /* ACDC 3.21.29-hotfix4 — Renvoi manuel illimité : on peut renvoyer même
         après les trois relances automatiques. */
      $rank = 3;
      $column = 'relance_3_at';
    }

    $sent = $this->nad_send_relance_email( $analysis, $rank );

    if ( ! $sent ) {
      wp_safe_redirect( add_query_arg( array(
        'notice'      => rawurlencode( 'Aucun envoi : la relance n’est pas partie à ' . $analysis->repondant_email . '. Vérifiez l’adresse du répondant et, si le mode recette est actif, la liste des destinataires autorisés — le refus est journalisé.' ),
        'notice_type' => 'error',
      ), $base_url ) );
      exit;
    }

    $update = array( $column => $now, 'updated_at' => $now );
    if ( 1 === $rank ) {
      $update['statut'] = 'a_traiter';
    }
    $wpdb->update( $this->need_analysis_table, $update, array( 'id' => $analysis_id ) );

    if ( $r3 ) {
      $msg = 'Relance manuelle envoyée à ' . esc_html( $analysis->repondant_email ) . '.';
    } elseif ( 3 === $rank ) {
      $msg = 'Relance 3 (dernière) envoyée à ' . esc_html( $analysis->repondant_email ) . '.';
    } else {
      $msg = 'Relance ' . (int) $rank . ' envoyée à ' . esc_html( $analysis->repondant_email ) . '.';
    }

    wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( $msg ), 'notice_type' => 'success' ), $base_url ) );
    exit;
  }

  public function cron_nad_send_and_relance() {
    $this->cron_nad_send_and_relance_core();
  }


  /* =====================================================================
   * ACDC 3.21.19 — Filtre template_include : page publique enquêtes
   * en plein écran sans thème (uniquement pour les sessions survey).
   * ===================================================================== */

  public function survey_maybe_override_template( $template ) {
    $page_id = (int) get_option( 'acdc_of_questionnaire_session_page_id', 0 );
    if ( ! $page_id ) {
      return $template;
    }
    if ( ! is_singular() || (int) get_the_ID() !== $page_id ) {
      return $template;
    }
    $token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
    if ( '' === $token ) {
      return $template;
    }
    if ( ! method_exists( $this, 'get_questionnaire_session_by_token' ) ) {
      return $template;
    }
    $session = $this->get_questionnaire_session_by_token( $token );
    if ( ! $session || empty( $session->source_type ) ) {
      return $template;
    }
    if ( ! method_exists( $this, 'is_survey_questionnaire_source_type' ) ) {
      return $template;
    }
    if ( ! $this->is_survey_questionnaire_source_type( (string) $session->source_type ) ) {
      return $template;
    }
    $standalone = ACDC_OF_SAAS_DIR . 'includes/questionnaires/templates/standalone-survey-form.php';
    if ( file_exists( $standalone ) ) {
      return $standalone;
    }
    return $template;
  }


  /* ═══════════════════════════════════════════════════════════════
     ACDC 3.21.36 — REST API : synchronisation formations + réception leads
  ═══════════════════════════════════════════════════════════════ */

  /**
   * Enregistre les routes REST du SAAS.
   * Appelé via add_action( 'rest_api_init', ... ) dans class-acdc-plugin.php
   */
  public function register_saas_rest_routes() {
    register_rest_route( 'acdc-of/v1', '/sync-formations', array(
      'methods'             => \WP_REST_Server::CREATABLE,
      'callback'            => array( $this, 'rest_sync_formations' ),
      'permission_callback' => array( $this, 'rest_check_api_key' ),
    ) );

    register_rest_route( 'acdc-of/v1', '/leads', array(
      'methods'             => \WP_REST_Server::CREATABLE,
      'callback'            => array( $this, 'rest_receive_lead' ),
      'permission_callback' => array( $this, 'rest_check_api_key' ),
    ) );

    // ACDC 3.21.58 — Indicateurs globaux (public, sans auth)
    register_rest_route( 'acdc-of/v1', '/indicators', array(
      'methods'             => \WP_REST_Server::READABLE,
      'callback'            => array( $this, 'rest_get_indicators_global' ),
      'permission_callback' => '__return_true',
    ) );

    // ACDC 3.21.58 — Indicateurs par formation (public, sans auth)
    register_rest_route( 'acdc-of/v1', '/indicators/(?P<id>[0-9]+)', array(
      'methods'             => \WP_REST_Server::READABLE,
      'callback'            => array( $this, 'rest_get_indicators_formation' ),
      'permission_callback' => '__return_true',
      'args'                => array(
        'id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
      ),
    ) );
  }

  /**
   * Vérifie la clé Bearer.
   */
  public function rest_check_api_key( \WP_REST_Request $request ) {
    $stored = get_option( 'acdc_of_rest_api_key', '' );
    if ( '' === $stored ) return false;

    $auth   = $request->get_header( 'Authorization' );
    $prefix = 'Bearer ';
    if ( ! $auth || strpos( $auth, $prefix ) !== 0 ) return false;

    return hash_equals( $stored, trim( substr( $auth, strlen( $prefix ) ) ) );
  }

  /**
   * POST /wp-json/acdc-of/v1/sync-formations
   * Corps : { formations: [...] }  (format identique à GET /acdc-fm/v1/formations)
   */
  public function rest_sync_formations( \WP_REST_Request $request ) {
    global $wpdb;
    $body       = $request->get_json_params();
    $formations = isset( $body['formations'] ) && is_array( $body['formations'] ) ? $body['formations'] : array();

    if ( empty( $formations ) ) {
      return new \WP_REST_Response( array( 'success' => false, 'message' => 'Aucune formation reçue.' ), 400 );
    }

    $created = 0;
    $updated = 0;
    $errors  = array();

    foreach ( $formations as $f ) {
      if ( empty( $f['title'] ) ) continue;

      $manager_id = isset( $f['manager_formation_id'] ) ? (int) $f['manager_formation_id'] : 0;
      $modalites  = isset( $f['modalites'] ) && is_array( $f['modalites'] ) ? $f['modalites'] : array( '' );
      $tarifs     = isset( $f['tarifs'] ) && is_array( $f['tarifs'] ) ? $f['tarifs'] : array();

      // Garantir au moins une ligne si aucune modalité
      if ( empty( $modalites ) ) $modalites = array( '' );

      foreach ( $modalites as $modalite ) {
        // Recherche par (manager_formation_id + modalité)
        $existing = null;
        if ( $manager_id > 0 ) {
          $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->formation_table} WHERE manager_formation_id = %d AND modality = %s LIMIT 1",
            $manager_id, $modalite
          ) );
        }

        $tarif_map = array(
          'Présentiel'      => 'Présentiel',
          'présentiel'      => 'Présentiel',
          'Distanciel'      => 'Distanciel',
          'distanciel'      => 'Distanciel',
          'Mixte (hybride)' => 'Mixte (hybride)',
          'hybride'         => 'Mixte (hybride)',
          'E-learning'      => 'E-learning',
          'e-learning'      => 'E-learning',
        );
        $tarif_key = $tarif_map[ $modalite ] ?? $modalite;
        $price     = isset( $tarifs[ $tarif_key ] ) && $tarifs[ $tarif_key ] !== '' ? (string) $tarifs[ $tarif_key ] : '';

        $is_physical = in_array( $modalite, array( 'Présentiel', 'présentiel', 'Mixte (hybride)', 'hybride' ), true );

        $financement_val = isset( $f['financement'] ) && is_array( $f['financement'] )
          ? wp_json_encode( $f['financement'], JSON_UNESCAPED_UNICODE )
          : ( isset( $f['financement'] ) ? (string) $f['financement'] : '' );

        $data = array(
          'title'               => sanitize_text_field( (string) $f['title'] ),
          'status'              => isset( $f['status'] ) ? sanitize_text_field( (string) $f['status'] ) : 'VALIDÉE',
          'qualiopi_compliant'  => isset( $f['qualiopi_compliant'] ) ? (int) $f['qualiopi_compliant'] : 1,
          'modality'            => sanitize_text_field( $modalite ),
          'price_ht'            => sanitize_text_field( $price ),
          'duration'            => sanitize_text_field( (string) ( $f['duration'] ?? '' ) ),
          'address'             => $is_physical ? sanitize_text_field( (string) ( $f['address'] ?? '' ) ) : '',
          'postal_code'         => $is_physical ? sanitize_text_field( (string) ( $f['postal_code'] ?? '' ) ) : '',
          'city'                => $is_physical ? sanitize_text_field( (string) ( $f['city'] ?? '' ) ) : '',
          'description_text'    => sanitize_textarea_field( (string) ( $f['description_text'] ?? '' ) ),
          'accroche'            => sanitize_textarea_field( (string) ( $f['accroche'] ?? '' ) ),
          'objectives'          => sanitize_textarea_field( (string) ( $f['objectives'] ?? '' ) ),
          'prerequisites'       => sanitize_textarea_field( (string) ( $f['prerequisites'] ?? '' ) ),
          'catalog_audience'    => sanitize_textarea_field( (string) ( $f['catalog_audience'] ?? '' ) ),
          'moyens_pedago'       => sanitize_textarea_field( (string) ( $f['moyens_pedago'] ?? '' ) ),
          'ressources'          => sanitize_textarea_field( (string) ( $f['ressources'] ?? '' ) ),
          'sanction'            => sanitize_textarea_field( (string) ( $f['sanction'] ?? '' ) ),
          'evaluation_entree'   => sanitize_textarea_field( (string) ( $f['evaluation_entree'] ?? '' ) ),
          'evaluation_sortie'   => sanitize_textarea_field( (string) ( $f['evaluation_sortie'] ?? '' ) ),
          'trainer_ref'         => sanitize_textarea_field( (string) ( $f['trainer_ref'] ?? '' ) ),
          'accessibilite'       => sanitize_textarea_field( (string) ( $f['accessibilite'] ?? '' ) ),
          'referent_handicap'   => sanitize_text_field( (string) ( $f['referent_handicap'] ?? '' ) ),
          'suivi'               => sanitize_textarea_field( (string) ( $f['suivi'] ?? '' ) ),
          'lieu_acces'          => sanitize_textarea_field( (string) ( $f['lieu_acces'] ?? '' ) ),
          'demarches'           => sanitize_textarea_field( (string) ( $f['demarches'] ?? '' ) ),
          'annulation'          => sanitize_textarea_field( (string) ( $f['annulation'] ?? '' ) ),
          'effectif_min'        => (int) ( $f['effectif_min'] ?? 0 ),
          'effectif_max'        => (int) ( $f['effectif_max'] ?? 0 ),
          'cpf_eligible'        => (int) ( $f['cpf_eligible'] ?? 0 ),
          'cpf_code'            => sanitize_text_field( (string) ( $f['cpf_code'] ?? '' ) ),
          'financement'         => $financement_val,
          'taux_reussite'       => (int) ( $f['taux_reussite'] ?? 0 ),
          'taux_satisfaction'   => (int) ( $f['taux_satisfaction'] ?? 0 ),
          'taux_recommandation' => (int) ( $f['taux_recommandation'] ?? 0 ),
          'taux_completion'     => (int) ( $f['taux_completion'] ?? 0 ),
          'catalog_image_url'   => esc_url_raw( (string) ( $f['image_url'] ?? '' ) ),
          'program_file_url'    => esc_url_raw( (string) ( $f['programme_url'] ?? '' ) ),
          'manager_formation_id'=> $manager_id,
          'is_active'           => 1,
          'is_draft'            => 0,
          'updated_at'          => $this->now_mysql(),
        );

        // Programme structuré — convertir depuis le format manager vers le format SAAS
        if ( ! empty( $f['programme_blocs'] ) && is_array( $f['programme_blocs'] ) ) {
          $blocs = array();
          foreach ( $f['programme_blocs'] as $day ) {
            if ( ! isset( $day['demi_journees'] ) ) continue;
            $jour_num = (int) ( $day['numero'] ?? preg_replace( '/[^0-9]/', '', (string) ( $day['jour'] ?? '' ) ) );
            foreach ( $day['demi_journees'] as $half ) {
              $moment_raw = strtolower( (string) ( $half['moment'] ?? '' ) );
              $moment     = ( strpos( $moment_raw, 'pm' ) !== false || strpos( $moment_raw, 'apr' ) !== false ) ? 'apm' : 'matin';
              $blocs[] = array(
                'jour'        => $jour_num,
                'moment'      => $moment,
                'titre'       => sanitize_text_field( (string) ( $half['titre'] ?? '' ) ),
                'contenus'    => sanitize_textarea_field( is_array( $half['contenus'] ?? '' ) ? implode( "\n", $half['contenus'] ) : (string) ( $half['contenus'] ?? '' ) ),
                'ateliers'    => sanitize_textarea_field( is_array( $half['ateliers'] ?? '' ) ? implode( "\n", $half['ateliers'] ) : (string) ( $half['ateliers'] ?? '' ) ),
                'competences' => sanitize_textarea_field( is_array( $half['competences'] ?? '' ) ? implode( "\n", $half['competences'] ) : (string) ( $half['competences'] ?? '' ) ),
                'opo'         => sanitize_textarea_field( is_array( $half['objectifs'] ?? '' ) ? implode( "\n", $half['objectifs'] ) : (string) ( $half['objectifs'] ?? '' ) ),
              );
            }
          }
          if ( ! empty( $blocs ) ) {
            $data['programme_detail'] = wp_json_encode( $blocs, JSON_UNESCAPED_UNICODE );
          }
        }

        // ACDC 3.21.55 — Objectifs pédagogiques structurés
        if ( ! empty( $f['objectifs_blocs'] ) && is_array( $f['objectifs_blocs'] ) ) {
          $data['objectifs_detail'] = wp_json_encode( $f['objectifs_blocs'], JSON_UNESCAPED_UNICODE );
        }

        // ACDC 3.21.55 — Tarifs par modalité
        if ( ! empty( $f['tarifs'] ) && is_array( $f['tarifs'] ) ) {
          $data['tarifs_detail'] = wp_json_encode( $f['tarifs'], JSON_UNESCAPED_UNICODE );
        }

        // ACDC 3.21.55 — Nombre de jours (reformatage durée PDF)
        if ( isset( $f['jours_count'] ) && (int) $f['jours_count'] > 0 ) {
          $data['jours_count'] = (int) $f['jours_count'];
        }

        // Mapping thématique manager → thématique SAAS
        // Comparaison strtolower uniquement (sans remove_accents — non fiable en contexte REST)
        $thema_manager_raw = sanitize_text_field( (string) ( $f['thematique_manager'] ?? '' ) );
        $thema_manager     = strtolower( $thema_manager_raw );
        $thema_saas        = '';
        $nsf_code          = '100'; // Formations générales par défaut

        // IMPORTANT : la formation stocke le CODE de la thématique (pas le label)
        // Codes définis dans seed_thematiques() : ia, automatisation_nocode, marketing_wordpress,
        // management_leadership, management_restauration, hygiene_alimentaire, softskills
        // Décoder les entités HTML (ex: &amp; → &) pour que la comparaison fonctionne
        $thema_manager = html_entity_decode( $thema_manager, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        $thema_map = array(
          'intelligence artificielle'  => array( 'code' => 'ia',                      'nsf' => '326' ),
          'no-code'                    => array( 'code' => 'automatisation_nocode',    'nsf' => '326' ),
          'automatisation'             => array( 'code' => 'automatisation_nocode',    'nsf' => '326' ),
          'marketing digital'          => array( 'code' => 'marketing_wordpress',      'nsf' => '320' ),
          'management & leadership'    => array( 'code' => 'management_leadership',    'nsf' => '315' ),
          'management en restauration' => array( 'code' => 'management_restauration',  'nsf' => '334' ),
          'hygiene alimentaire'        => array( 'code' => 'hygiene_alimentaire',       'nsf' => '221' ),
          'hygiene'                    => array( 'code' => 'hygiene_alimentaire',       'nsf' => '221' ),
          'soft skills'                => array( 'code' => 'softskills',               'nsf' => '413' ),
          'soft skill'                 => array( 'code' => 'softskills',               'nsf' => '413' ),
        );

        foreach ( $thema_map as $keyword => $mapping ) {
          if ( '' !== $thema_manager && false !== stripos( $thema_manager, $keyword ) ) {
            $thema_saas = $mapping['code'];
            $nsf_code   = $mapping['nsf'];
            break;
          }
        }

        if ( '' !== $thema_saas ) {
          $data['thematique'] = $thema_saas; // code ex: 'ia', 'management_leadership'
        }

        // Objectif prestation : toujours 'autre_pro' si vide ou si la sync peut l'enrichir
        if ( empty( $existing->service_objective ) || 'autre_pro' === ( $existing->service_objective ?? '' ) ) {
          $data['service_objective'] = 'autre_pro';
        }
        // Spécialité NSF : toujours mise à jour depuis la thématique (sauf si déjà personnalisée par l'utilisateur)
        $default_nsf_codes = array( '100', '326', '320', '315', '334', '221', '413' );
        if ( empty( $existing->specialty ) || in_array( (string) ( $existing->specialty ?? '' ), $default_nsf_codes, true ) ) {
          $data['specialty'] = $nsf_code;
        }

        if ( $existing ) {
          $data['catalog_public']  = (int) ( $existing->catalog_public ?? 1 );
          $data['catalog_slug']    = (string) ( $existing->catalog_slug ?? '' );
          $data['catalog_order']   = (int) ( $existing->catalog_order ?? 0 );
          $r = $wpdb->update( $this->formation_table, $data, array( 'id' => (int) $existing->id ) );
          if ( false !== $r ) { $updated++; } else { $errors[] = $f['title']; }
        } else {
          /* ACDC 3.24.21 — S2 : ne pas créer une formation si elle est archivée/dépubliée côté Manager.
           * Les statuts actifs reconnus : 'VALIDÉE', 'publish', 'active', '' (vide = considéré actif).
           * Tout autre statut (ARCHIVÉE, draft, trash, pending…) → on ignore la formation. */
          $raw_status = strtolower( trim( (string) ( $f['status'] ?? '' ) ) );
          $active_statuses = array( 'validée', 'validee', 'publish', 'active', '' );
          if ( ! in_array( $raw_status, $active_statuses, true ) ) {
            continue; // Formation archivée côté Manager et inexistante côté SAAS → on ne la crée pas.
          }
          $data['created_at']     = $this->now_mysql();
          $data['catalog_public'] = 1;
          $data['catalog_slug']   = '';
          $data['catalog_order']  = 0;
          $data['code']           = '';
          $data['notes']          = '';
          $r = $wpdb->insert( $this->formation_table, $data );
          if ( false !== $r ) { $created++; } else { $errors[] = $f['title']; }
        }
      }
    }

    // Journaliser la synchronisation
    update_option( 'acdc_of_last_sync', array(
      'at'      => current_time( 'c' ),
      'created' => $created,
      'updated' => $updated,
      'errors'  => count( $errors ),
    ) );

    return new \WP_REST_Response( array(
      'success' => true,
      'created' => $created,
      'updated' => $updated,
      'errors'  => $errors,
      'message' => 'Synchronisation terminée — ' . ( $created + $updated ) . ' formations traitées : ' . $created . ' créées, ' . $updated . ' mises à jour.',
    ), 200 );
  }

  /**
   * POST /wp-json/acdc-of/v1/leads
   * Reçoit un lead depuis le manager et crée un prospect dans le CRM.
   */
  public function rest_receive_lead( \WP_REST_Request $request ) {
    global $wpdb;
    $body = $request->get_json_params();
    if ( empty( $body ) ) {
      return new \WP_REST_Response( array( 'success' => false, 'message' => 'Corps JSON vide.' ), 400 );
    }

    // Mapping manager → prospect SAAS
    $source_labels = array(
      'devis'      => 'Devis (site web)',
      'mesure'     => 'Formation sur mesure (site web)',
      'programme'  => 'Demande programme (site web)',
      'session'    => 'Inscription session (site web)',
      'generique'  => 'Contact (site web)',
    );
    $form_type = sanitize_text_field( (string) ( $body['form_type'] ?? 'generique' ) );
    $source    = $source_labels[ $form_type ] ?? 'Site web';

    // Enrichir le commentaire avec les champs supplémentaires
    $comment_parts = array();
    if ( ! empty( $body['message'] ) )         $comment_parts[] = 'Message : ' . sanitize_textarea_field( (string) $body['message'] );
    if ( ! empty( $body['objectifs'] ) )        $comment_parts[] = 'Objectifs : ' . sanitize_textarea_field( (string) $body['objectifs'] );
    if ( ! empty( $body['contraintes'] ) )      $comment_parts[] = 'Contraintes : ' . sanitize_textarea_field( (string) $body['contraintes'] );
    if ( ! empty( $body['nb_participants'] ) )  $comment_parts[] = 'Nb participants : ' . sanitize_text_field( (string) $body['nb_participants'] );
    if ( ! empty( $body['niveau'] ) )           $comment_parts[] = 'Niveau : ' . sanitize_text_field( (string) $body['niveau'] );
    if ( ! empty( $body['budget'] ) )           $comment_parts[] = 'Budget : ' . sanitize_text_field( (string) $body['budget'] );
    if ( ! empty( $body['dates'] ) )            $comment_parts[] = 'Dates souhaitées : ' . sanitize_text_field( (string) $body['dates'] );
    if ( ! empty( $body['lieu'] ) )             $comment_parts[] = 'Lieu : ' . sanitize_text_field( (string) $body['lieu'] );
    if ( ! empty( $body['secteur'] ) )          $comment_parts[] = 'Secteur : ' . sanitize_text_field( (string) $body['secteur'] );
    if ( ! empty( $body['public_vise'] ) )      $comment_parts[] = 'Public visé : ' . sanitize_text_field( (string) $body['public_vise'] );
    // Type de demande toujours tracé
    $comment_parts[] = 'Type de demande : ' . $source;

    // Retrouver la formation SAAS liée via manager_formation_id
    $desired_formation_id = null;
    $manager_fid = isset( $body['manager_formation_id'] ) ? (int) $body['manager_formation_id'] : 0;
    if ( $manager_fid > 0 ) {
      $linked = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$this->formation_table} WHERE manager_formation_id = %d LIMIT 1",
        $manager_fid
      ) );
      if ( $linked ) $desired_formation_id = (int) $linked;
    }

    // Détection automatique du profil selon le contenu du formulaire
    // Valeurs SAAS : 'entreprise', 'independant', 'salarie', 'particulier'
    $form_type_raw = sanitize_key( (string) ( $body['form_type'] ?? 'generique' ) );
    $public_vise   = strtolower( remove_accents( sanitize_text_field( (string) ( $body['public_vise'] ?? '' ) ) ) );
    $has_entreprise = ! empty( $body['entreprise'] );

    // Mots-clés indicateurs de profil dans public_vise (formulaire mesure)
    $kw_independant = array( 'independant', 'formateur', 'auto-entrepreneur', 'freelance' );
    $kw_salarie     = array( 'salarie', 'manager', 'administration', 'commercial', 'employe' );
    $kw_entreprise  = array( 'chef d entreprise', 'restaurateur', 'tpe', 'pme', 'tpe/pme', 'dirigeant' );

    $profile_type = 'particulier'; // défaut

    if ( $has_entreprise ) {
      $profile_type = 'entreprise';
    }
    // Affiner selon public_vise (formulaire "mesure")
    if ( '' !== $public_vise ) {
      foreach ( $kw_independant as $kw ) {
        if ( false !== strpos( $public_vise, $kw ) ) { $profile_type = 'independant'; break; }
      }
      foreach ( $kw_salarie as $kw ) {
        if ( false !== strpos( $public_vise, $kw ) ) { $profile_type = 'salarie'; break; }
      }
      foreach ( $kw_entreprise as $kw ) {
        if ( false !== strpos( $public_vise, $kw ) ) { $profile_type = 'entreprise'; break; }
      }
    }
    // Source réelle du formulaire (ex: "Recommandation d'un partenaire")
    $real_source = ! empty( $body['source'] ) ? sanitize_text_field( wp_unslash( (string) $body['source'] ) ) : $source;

    // ACDC 3.25.115 — libellés canoniques attendus par le CRM.
    $profile_type_labels = array(
      'particulier' => 'Particulier',
      'salarie'     => 'Salarié',
      'independant' => 'Indépendant',
      'entreprise'  => 'Entreprise',
    );
    if ( isset( $profile_type_labels[ $profile_type ] ) ) {
      $profile_type = $profile_type_labels[ $profile_type ];
    }

    $data = array(
      'profile_type'          => $profile_type,
      'first_name'            => sanitize_text_field( (string) ( $body['prenom'] ?? '' ) ),
      'last_name'             => sanitize_text_field( (string) ( $body['nom'] ?? '' ) ),
      'email'                 => sanitize_email( (string) ( $body['email'] ?? '' ) ),
      'phone'                 => sanitize_text_field( (string) ( $body['telephone'] ?? '' ) ),
      'company_name'          => sanitize_text_field( (string) ( $body['entreprise'] ?? '' ) ),
      'job_title'             => sanitize_text_field( (string) ( $body['fonction'] ?? '' ) ),
      'desired_training'      => sanitize_text_field( (string) ( $body['formation_nom'] ?? '' ) ),
      'desired_formation_id'  => $desired_formation_id,
      'desired_thematique'    => sanitize_text_field( (string) ( $body['thematiques'] ?? '' ) ),
      'desired_format'        => sanitize_text_field( (string) ( $body['modalite'] ?? '' ) ),
      'source'                => $real_source,
      'status'                => 'À traiter',
      // Signataire = la personne qui a rempli le formulaire (profil Entreprise)
      'signer_first_name'     => sanitize_text_field( (string) ( $body['prenom'] ?? '' ) ),
      'signer_last_name'      => sanitize_text_field( (string) ( $body['nom'] ?? '' ) ),
      'signer_quality'        => sanitize_text_field( (string) ( $body['fonction'] ?? '' ) ),
      'signer_email'          => sanitize_email( (string) ( $body['email'] ?? '' ) ),
      'signer_phone'          => sanitize_text_field( (string) ( $body['telephone'] ?? '' ) ),
      'comment_text'          => implode( "\n\n", array_filter( $comment_parts ) ),
      'created_at'            => $this->now_mysql(),
      'updated_at'            => $this->now_mysql(),
    );

    // Demande de rappel → note RDV
    if ( ! empty( $body['rappel'] ) && (int) $body['rappel'] === 1 ) {
      $data['rdv_notes'] = 'Rappel demandé par le prospect via le site web.';
    }

    $inserted = $wpdb->insert( $this->prospect_table, $data );
    if ( ! $inserted ) {
      return new \WP_REST_Response( array( 'success' => false, 'message' => 'Erreur BDD : ' . $wpdb->last_error ), 500 );
    }

    $prospect_id = (int) $wpdb->insert_id;

    // Notification e-mail admin
    $this->send_new_lead_notification( $data, $prospect_id );

    // Badge "nouveau lead" — incrémenter le compteur
    $count = (int) get_option( 'acdc_of_new_leads_count', 0 );
    update_option( 'acdc_of_new_leads_count', $count + 1 );

    return new \WP_REST_Response( array(
      'success'     => true,
      'prospect_id' => $prospect_id,
      'message'     => 'Prospect créé avec succès.',
    ), 201 );
  }

  /**
   * E-mail de notification admin à l'arrivée d'un nouveau lead.
   */
  private function send_new_lead_notification( array $data, int $prospect_id ) {
    $admin_email = get_option( 'admin_email', '' );
    if ( ! $admin_email ) return;

    $branding    = $this->get_branding_options();
    $company     = ! empty( $branding['company_name'] ) ? $branding['company_name'] : 'ACDC Formation';
    // Forcer l'URL extranet front-end (pas wp-admin) pour le lien dans l'e-mail
    $portal_page_id = (int) get_option( 'acdc_of_portal_page_id', 0 );
    if ( $portal_page_id > 0 ) {
      $portal_url = trailingslashit( get_permalink( $portal_page_id ) )
        . '?tab=prospects&action=view&item_id=' . $prospect_id;
    } elseif ( method_exists( $this, 'portal_page_url' ) ) {
      // Fallback : portal_page_url retourne l'extranet si disponible
      add_filter( 'acdc_portal_force_front', '__return_true' );
      $portal_url = $this->portal_page_url( array( 'tab' => 'prospects', 'action' => 'view', 'item_id' => $prospect_id ) );
      remove_filter( 'acdc_portal_force_front', '__return_true' );
    } else {
      $portal_url = home_url( '/extranet/tableau-de-bord/?tab=prospects&action=view&item_id=' . $prospect_id );
    }

    $name        = trim( $data['first_name'] . ' ' . $data['last_name'] );
    $formation   = ! empty( $data['desired_training'] ) ? $data['desired_training'] : '(non précisé)';
    $source      = $data['source'];

    $subject = '🔔 Nouveau prospect — ' . $name . ' — ' . $formation;

    $body  = '<p>Un nouveau prospect vient d\'être créé automatiquement depuis le site web.</p>';
    $body .= '<table cellpadding="6" style="border-collapse:collapse;">';
    $body .= '<tr><td><strong>Nom</strong></td><td>' . esc_html( $name ) . '</td></tr>';
    $body .= '<tr><td><strong>E-mail</strong></td><td>' . esc_html( $data['email'] ) . '</td></tr>';
    $body .= '<tr><td><strong>Téléphone</strong></td><td>' . esc_html( $data['phone'] ) . '</td></tr>';
    $body .= '<tr><td><strong>Entreprise</strong></td><td>' . esc_html( $data['company_name'] ) . '</td></tr>';
    $body .= '<tr><td><strong>Formation souhaitée</strong></td><td>' . esc_html( $formation ) . '</td></tr>';
    $body .= '<tr><td><strong>Modalité</strong></td><td>' . esc_html( $data['desired_format'] ) . '</td></tr>';
    $body .= '<tr><td><strong>Fonction</strong></td><td>' . esc_html( $data['job_title'] ) . '</td></tr>';
    $body .= '<tr><td><strong>Source</strong></td><td>' . esc_html( $data['source'] ) . '</td></tr>';
    $body .= '</table>';
    if ( ! empty( $data['comment_text'] ) ) {
      $body .= '<p><strong>Message :</strong><br>' . nl2br( esc_html( $data['comment_text'] ) ) . '</p>';
    }
    $body .= '<p><a href="' . esc_url( $portal_url ) . '" style="background:#d6a353;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;">Voir le prospect dans le SAAS</a></p>';

    /* ACDC 3.25.246 — Enveloppe commune, y compris pour cet avis interne :
       l'uniformité demandée vaut pour tous les envois, et l'archive des e-mails
       y gagne une présentation homogène. */
    $this->acdc_send_transactional_email(
      $admin_email,
      $subject,
      array( 'body_html' => $body ),
      array(
        'source_module'  => 'crm',
        'source_action'  => 'new_prospect_notice',
        'email_category' => 'crm',
        'email_audience' => 'interne',
      )
    );
  }

  /**
   * ACDC 3.21.58 — GET /wp-json/acdc-of/v1/indicators
   * Indicateurs globaux agrégés. Public, sans authentification.
   * Transient 1h pour éviter les requêtes répétées.
   */
  public function rest_get_indicators_global( \WP_REST_Request $request ) {
    global $wpdb;

    $cached = get_transient( 'acdc_of_indicators_global' );
    if ( false !== $cached && is_array( $cached ) ) {
      $response = new \WP_REST_Response( array( 'success' => true, 'data' => $cached, 'cached' => true ), 200 );
      $response->header( 'Cache-Control', 'public, max-age=3600' );
      return $response;
    }

    $total_formations  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->formation_table} WHERE is_active = 1 AND status = 'VALIDÉE'" );

    /* ACDC 3.25.270 — LA PAGE D'ACCUEIL ANNONÇAIT 2 APPRENANTS ET 16 HEURES.
       Le compte des apprenants passait par `learner.session_id` — la colonne
       que la convention ne renseigne jamais — et les trois taux étaient la
       moyenne arithmétique de valeurs SAISIES À LA MAIN sur les fiches
       formation. Un site commercial affichait donc « 97 % de satisfaction »
       que rien n'étayait, et un effectif que rien ne mesurait.
       Tout est désormais calculé sur les mêmes compteurs que les indicateurs
       par formation, réunis une seule fois. La pondération va de soi puisqu'on
       additionne des notes et non des pourcentages : une formation notée par
       cinquante personnes pèse cinquante fois celle notée par une. */
    $actives = array_map( 'intval', (array) $wpdb->get_col(
      "SELECT id FROM {$this->formation_table} WHERE is_active = 1"
    ) );

    $counters_global = $this->acdc_formation_counters( $actives );
    $mesures_global  = \ACDC\Support\Indicators::rates( $counters_global );

    $total_apprenants = (int) $mesures_global['nb_apprenants'];

    /* Heures dispensées : les créneaux réellement planifiés, séance par séance,
       comme les compte déjà l'écran des statistiques pédagogiques. La somme
       start_at → end_at ne voyait que les séances portant ces deux colonnes. */
    $total_heures         = 0.0;
    $total_heures_suivies = 0.0;
    if ( ! empty( $actives ) && method_exists( $this, 'acdc_completion_planned_time' ) ) {
      $ph_actives  = implode( ',', array_fill( 0, count( $actives ), '%d' ) );
      $session_ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare(
        "SELECT id FROM {$this->session_table}
          WHERE formation_id IN ({$ph_actives})
            AND COALESCE(is_draft,0) = 0
            AND COALESCE(status,'') NOT IN ('Annulée','Annulee','Brouillon')",
        $actives
      ) ) );
      $planned      = $this->acdc_completion_planned_time( $session_ids );
      $total_heures = round( ( (int) $planned['minutes'] ) / 60, 1 );

      /* ACDC 3.25.281 — Les heures SUIVIES de l'organisme, séance par séance.
         Multiplier le total d'heures par le total d'apprenants donnerait un
         nombre sans rapport avec la réalité dès que deux séances n'ont pas le
         même effectif : on compte donc chaque séance avec le sien. */
      $seances_pour_suivies = array();
      foreach ( $session_ids as $sid ) {
        $seance = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->session_table} WHERE id = %d", (int) $sid ) );
        if ( ! $seance ) { continue; }
        $minutes = 0;
        if ( method_exists( $this, 'acdc_completion_planned_time' ) ) {
          $p       = $this->acdc_completion_planned_time( array( (int) $sid ) );
          $minutes = (int) $p['minutes'];
        }
        $nb = method_exists( $this, 'acdc_session_learner_ids' )
          ? count( (array) $this->acdc_session_learner_ids( $seance ) )
          : 0;
        $seances_pour_suivies[] = array( 'minutes' => $minutes, 'apprenants' => $nb );
      }
      $total_heures_suivies = \ACDC\Support\PublicIndicators::attendedHours( $seances_pour_suivies );
    }

    /* Le repli déclaré ne s'applique qu'en l'absence totale de mesure : sans
       lui, le site passerait de « 97 % » à rien du jour au lendemain. */
    $moy_declaree = function( $colonne ) use ( $wpdb ) {
      $row = $wpdb->get_row( "SELECT AVG({$colonne}) AS v, COUNT(*) AS n FROM {$this->formation_table} WHERE is_active = 1 AND {$colonne} > 0" );
      return ( $row && (int) $row->n > 0 ) ? (int) round( (float) $row->v ) : 0;
    };

    $g_satisfaction  = \ACDC\Support\Indicators::publish( $mesures_global['taux_satisfaction'], $moy_declaree( 'taux_satisfaction' ) );
    $g_reussite      = \ACDC\Support\Indicators::publish( $mesures_global['taux_reussite'], $moy_declaree( 'taux_reussite' ) );
    $g_recommandation = \ACDC\Support\Indicators::publish( $mesures_global['taux_recommandation'], $moy_declaree( 'taux_recommandation' ) );

    /* ACDC 3.25.281 — LE SITE VITRINE ANNONÇAIT DES CHIFFRES RECOPIÉS À LA MAIN.
       La page d'accueil affichait « 73 apprenants formés » : ce nombre était
       saisi dans les réglages du plugin vitrine, parce que le SAAS ne remontait
       que l'activité de l'organisme — une petite part de ce qui a réellement
       été animé. Le reste, ce sont les prestations extérieures : les
       interventions faites pour d'autres organismes, qui appartiennent au
       formateur et non à l'organisme.
       On publie donc les deux, sans jamais écraser l'une par l'autre. Les clés
       d'origine gardent leur sens : un site vitrine non mis à jour continue
       d'afficher l'organisme seul et ne gonfle jamais ses chiffres tout seul.
       Les taux, eux, restent l'organisme seul : ils reposent sur des enquêtes,
       et il n'y a d'enquête que pour ses propres apprenants. */
    $externes = \ACDC\Support\PublicIndicators::externalTotals(
      (array) get_option( 'acdc_of_external_mission_records', array() )
    );
    $publiables = \ACDC\Support\PublicIndicators::publishable(
      array(
        'apprenants'        => $total_apprenants,
        'heures_dispensees' => $total_heures,
        'heures_suivies'    => $total_heures_suivies,
      ),
      $externes
    );

    $data = array(
      'total_formations'        => $total_formations,
      'total_apprenants'        => $total_apprenants,
      'total_heures'            => $total_heures,
      /* Tout compris — ce que le site vitrine affiche. */
      'total_apprenants_tous'         => $publiables['total_apprenants_tous'],
      'total_heures_dispensees_tous'  => $publiables['total_heures_dispensees_tous'],
      'total_heures_suivies_tous'     => $publiables['total_heures_suivies_tous'],
      'detail_activite'               => $publiables['detail'],
      'taux_satisfaction_moyen'   => (int) $g_satisfaction['value'],
      'taux_reussite_moyen'       => (int) $g_reussite['value'],
      'taux_recommandation_moyen' => (int) $g_recommandation['value'],
      /* Combien de réponses derrière chaque taux : c'est ce qu'un auditeur
         demande en premier, et c'est ce qui manquait pour les défendre. */
      'mesures'                 => array(
        'reponses_satisfaction'   => (int) $counters_global['notes_count'],
        'reponses_recommandation' => (int) $counters_global['reco_count'],
        'evaluations'             => (int) $counters_global['eval_total'],
        'sources'                 => array(
          'taux_satisfaction'   => $g_satisfaction['source'],
          'taux_reussite'       => $g_reussite['source'],
          'taux_recommandation' => $g_recommandation['source'],
        ),
      ),
      'generated_at'            => current_time( 'c' ),
    );

    set_transient( 'acdc_of_indicators_global', $data, HOUR_IN_SECONDS );

    $response = new \WP_REST_Response( array( 'success' => true, 'data' => $data, 'cached' => false ), 200 );
    $response->header( 'Cache-Control', 'public, max-age=3600' );
    return $response;
  }

  /**
   * ACDC 3.21.58 — GET /wp-json/acdc-of/v1/indicators/{id}
   * Indicateurs d'une formation identifiée par manager_formation_id. Public.
   */
  public function rest_get_indicators_formation( \WP_REST_Request $request ) {
    global $wpdb;

    $manager_id = (int) $request->get_param( 'id' );
    if ( ! $manager_id ) {
      return new \WP_REST_Response( array( 'success' => false, 'message' => 'ID invalide.' ), 400 );
    }

    $formation = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->formation_table} WHERE manager_formation_id = %d AND is_active = 1 LIMIT 1", $manager_id ) );
    if ( ! $formation ) {
      return new \WP_REST_Response( array( 'success' => false, 'message' => 'Formation introuvable.' ), 404 );
    }

    /* ACDC 3.25.270 — Mêmes chiffres que le push, calculés au même endroit :
       deux lectures de la même vérité finissent toujours par diverger, et
       celle-ci est publique. */
    $freres     = $this->acdc_formation_siblings( (int) $formation->id );
    $counters   = $this->acdc_formation_counters( $freres );
    $mesures    = \ACDC\Support\Indicators::rates( $counters );
    $reussite   = \ACDC\Support\Indicators::publish( $mesures['taux_reussite'], $formation->taux_reussite ?? 0 );
    $satisfait  = \ACDC\Support\Indicators::publish( $mesures['taux_satisfaction'], $formation->taux_satisfaction ?? 0 );
    $recommande = \ACDC\Support\Indicators::publish( $mesures['taux_recommandation'], $formation->taux_recommandation ?? 0 );

    $data = array(
      'manager_formation_id' => $manager_id,
      'saas_formation_id'    => (int) $formation->id,
      'saas_formation_ids'   => $freres,
      'title'                => (string) $formation->title,
      'taux_reussite'        => (int) $reussite['value'],
      'taux_satisfaction'    => (int) $satisfait['value'],
      'taux_recommandation'  => (int) $recommande['value'],
      'nb_apprenants'        => (int) $mesures['nb_apprenants'],
      /* Ce que vaut chaque chiffre. Un auditeur Qualiopi a le droit de savoir
         si le taux publié est mesuré ou déclaré. */
      'sources'              => array(
        'taux_reussite'       => $reussite['source'],
        'taux_satisfaction'   => $satisfait['source'],
        'taux_recommandation' => $recommande['source'],
      ),
      'generated_at'         => current_time( 'c' ),
    );

    $response = new \WP_REST_Response( array( 'success' => true, 'data' => $data ), 200 );
    $response->header( 'Cache-Control', 'public, max-age=3600' );
    return $response;
  }

  /**
   * ACDC 3.25.270 — LES INDICATEURS PUBLIÉS SONT DÉSORMAIS MESURÉS.
   *
   * Ce qui partait vers le site commercial était saisi à la main sur la fiche
   * formation — trois cases, remplies une fois, jamais revues — et le nombre
   * d'apprenants venait de la jointure par `learner.session_id`, celle que la
   * convention ne renseigne jamais. La page d'accueil annonçait ainsi
   * « 2 apprenants formés » et « 16 heures » à un organisme qui en a bien
   * davantage, à côté de trois taux que rien n'étayait. Or l'indicateur 2 du
   * référentiel Qualiopi demande des résultats publiés ET défendables.
   *
   * On lit donc les enquêtes et les évaluations. Trois précautions :
   *   — on transporte des COMPTEURS BRUTS, jamais des pourcentages, pour
   *     pouvoir additionner sans moyenner des moyennes ;
   *   — la recommandation se lit sur la question de notation qui la porte,
   *     repérée par son libellé, faute d'un marqueur dédié dans le modèle ;
   *   — sans aucune réponse, la mesure vaut null : c'est l'appelant qui décide
   *     de se taire ou de publier la valeur déclarée, en le sachant.
   *
   * @param int[] $formation_ids Formations SAAS à réunir.
   * @return array Bloc de compteurs au sens de ACDC\Support\Indicators.
   */
  private function acdc_formation_counters( $formation_ids ) {
    global $wpdb;

    $counters = \ACDC\Support\Indicators::emptyCounters();

    $formation_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $formation_ids ) ) ) );
    if ( empty( $formation_ids ) ) {
      return $counters;
    }
    $ph = implode( ',', array_fill( 0, count( $formation_ids ), '%d' ) );

    $counters['learner_ids'] = $this->acdc_learners_for_formations( $formation_ids );

    /* Satisfaction : toutes les notes des enquêtes apprenant. */
    $row = $wpdb->get_row( $wpdb->prepare(
      "SELECT COALESCE(SUM(a.numeric_score),0) AS somme, COUNT(*) AS nb
         FROM {$this->questionnaire_answer_table} a
         INNER JOIN {$this->questionnaire_session_table} s ON s.id = a.session_id
        WHERE s.formation_id IN ({$ph})
          AND s.is_survey_session = 1
          AND s.source_type IN ('hot_survey','mid_survey','cold_survey')
          AND a.answer_type = 'notation'
          AND a.numeric_score IS NOT NULL",
      $formation_ids
    ) );
    if ( $row ) {
      $counters['notes_sum']   = (float) $row->somme;
      $counters['notes_count'] = (int) $row->nb;
    }

    /* Recommandation : la même échelle, mais la seule question qui la pose. */
    $row = $wpdb->get_row( $wpdb->prepare(
      "SELECT COALESCE(SUM(a.numeric_score),0) AS somme, COUNT(*) AS nb
         FROM {$this->questionnaire_answer_table} a
         INNER JOIN {$this->questionnaire_session_table} s ON s.id = a.session_id
        WHERE s.formation_id IN ({$ph})
          AND s.is_survey_session = 1
          AND a.answer_type = 'notation'
          AND a.numeric_score IS NOT NULL
          AND a.question_label LIKE %s",
      array_merge( $formation_ids, array( '%recommand%' ) )
    ) );
    if ( $row ) {
      $counters['reco_sum']   = (float) $row->somme;
      $counters['reco_count'] = (int) $row->nb;
    }

    /* Réussite : les évaluations des acquis, seuil de 70 % — le même que celui
       de l'écran « Indicateurs de performance », pour qu'ils ne disent pas deux
       chiffres différents de la même chose. */
    $row = $wpdb->get_row( $wpdb->prepare(
      "SELECT COUNT(*) AS total,
              SUM( CASE WHEN p.final_score IS NOT NULL AND p.final_score >= 70 THEN 1 ELSE 0 END ) AS reussis
         FROM {$this->questionnaire_participant_table} p
         INNER JOIN {$this->questionnaire_session_table} s ON s.id = p.session_id
        WHERE s.formation_id IN ({$ph})
          AND s.source_type = 'evaluation'
          AND p.responded_at IS NOT NULL",
      $formation_ids
    ) );
    if ( $row ) {
      $counters['eval_total']  = (int) $row->total;
      $counters['eval_passed'] = (int) $row->reussis;
    }

    return $counters;
  }

  /**
   * ACDC 3.25.270 — Les lignes SAAS qui décrivent LA MÊME formation commerciale.
   *
   * Le SAAS crée une ligne par couple (formation du site, modalité) : la même
   * formation existe en présentiel ET en distanciel, avec deux identifiants
   * internes et un seul identifiant côté site. Les indicateurs étaient poussés
   * ligne par ligne vers ce même identifiant : le présentiel écrivait, le
   * distanciel écrasait. La page affichait les chiffres de la dernière modalité
   * synchronisée, jamais le total — et personne ne pouvait s'en apercevoir,
   * puisque le nombre affiché restait plausible.
   *
   * « Tu additionnes les statistiques de présentiel et distanciel dès l'instant
   * où ce sont les mêmes » : c'est exactement ce que ce regroupement fait.
   *
   * @param int $formation_id Une formation SAAS.
   * @return int[] Toutes les formations SAAS qui partagent son identifiant site.
   */
  private function acdc_formation_siblings( $formation_id ) {
    global $wpdb;

    $formation_id = (int) $formation_id;
    if ( $formation_id <= 0 ) {
      return array();
    }

    $manager_id = (int) $wpdb->get_var( $wpdb->prepare(
      "SELECT COALESCE(manager_formation_id,0) FROM {$this->formation_table} WHERE id = %d",
      $formation_id
    ) );
    if ( $manager_id <= 0 ) {
      /* Une formation qui n'existe pas sur le site n'a pas de jumelle. */
      return array( $formation_id );
    }

    $ids = (array) $wpdb->get_col( $wpdb->prepare(
      "SELECT id FROM {$this->formation_table} WHERE manager_formation_id = %d",
      $manager_id
    ) );

    return array_values( array_unique( array_map( 'intval', $ids ) ) );
  }

  /**
   * ACDC 3.21.58 — Push des indicateurs d'une formation vers le Manager.
   * Appelé après handle_save_formation() et par le cron nuit.
   * Ne bloque jamais la navigation — les erreurs sont silencieuses.
   *
   * @param int $formation_id  ID interne SAAS de la formation.
   */
  public function push_indicators_to_manager( $formation_id ) {
    global $wpdb;

    $manager_url = trim( (string) get_option( 'acdc_of_manager_url', '' ) );
    $manager_key = trim( (string) get_option( 'acdc_of_manager_api_key', '' ) );

    if ( '' === $manager_url || '' === $manager_key ) {
      return; // Manager non configuré — silencieux
    }

    $formation = $this->get_formation( (int) $formation_id );
    if ( ! $formation || empty( $formation->manager_formation_id ) ) {
      return; // Pas de lien Manager — silencieux
    }

    /* ACDC 3.25.270 — On pousse la formation COMMERCIALE, pas la ligne SAAS :
       présentiel et distanciel sont réunis avant l'envoi, sans quoi le second
       écrase le premier à l'arrivée. */
    $freres   = $this->acdc_formation_siblings( (int) $formation->id );
    $counters = $this->acdc_formation_counters( $freres );
    $mesures  = \ACDC\Support\Indicators::rates( $counters );

    /* La valeur saisie à la main reste le repli d'une formation jamais
       évaluée — mais elle ne masque plus une mesure existante. */
    $reussite    = \ACDC\Support\Indicators::publish( $mesures['taux_reussite'], $formation->taux_reussite ?? 0 );
    $satisfait   = \ACDC\Support\Indicators::publish( $mesures['taux_satisfaction'], $formation->taux_satisfaction ?? 0 );
    $recommande  = \ACDC\Support\Indicators::publish( $mesures['taux_recommandation'], $formation->taux_recommandation ?? 0 );
    $nb_apprenants = $mesures['nb_apprenants'] > 0
      ? $mesures['nb_apprenants']
      : (int) ( isset( $formation->taux_completion ) ? $formation->taux_completion : 0 );

    $endpoint = rtrim( $manager_url, '/' ) . '/wp-json/acdc-fm/v1/update-indicators';
    $payload   = array(
      'manager_formation_id' => (int) $formation->manager_formation_id,
      'taux_reussite'        => (int) $reussite['value'],
      'taux_satisfaction'    => (int) $satisfait['value'],
      'taux_recommandation'  => (int) $recommande['value'],
      'nb_apprenants'        => $nb_apprenants,
    );

    wp_remote_post( $endpoint, array(
      'timeout'   => 10,
      'sslverify' => true,
      'headers'   => array(
        'Authorization' => 'Bearer ' . $manager_key,
        'Content-Type'  => 'application/json',
        'User-Agent'    => 'ACDC-SAAS-Push/1.0',
      ),
      'body' => wp_json_encode( $payload ),
    ) );

    // Invalider le transient global pour forcer un recalcul au prochain appel
    delete_transient( 'acdc_of_indicators_global' );
  }

  /**
   * ACDC 3.21.58 — Cron nuit : push les indicateurs de toutes les formations liées au Manager.
   */
  public function cron_push_all_indicators() {
    global $wpdb;

    $manager_url = trim( (string) get_option( 'acdc_of_manager_url', '' ) );
    $manager_key = trim( (string) get_option( 'acdc_of_manager_api_key', '' ) );
    if ( '' === $manager_url || '' === $manager_key ) {
      return;
    }

    /* ACDC 3.25.270 — Une formation commerciale, un envoi. Les modalités sont
       réunies avant le calcul : les pousser une par une referait le même travail
       plusieurs fois pour écrire la même valeur. */
    $formations = $wpdb->get_results(
      "SELECT MIN(id) AS id FROM {$this->formation_table}
        WHERE is_active = 1 AND manager_formation_id IS NOT NULL AND manager_formation_id > 0
        GROUP BY manager_formation_id"
    );
    foreach ( $formations as $row ) {
      $this->push_indicators_to_manager( (int) $row->id );
    }
  }

  /**
   * Déclenche la synchronisation des formations depuis le manager.
   * Appelé via le bouton "Synchroniser" dans le SAAS ou via WP-Cron.
   */
  public function run_formations_sync() {
    $manager_url = trim( (string) get_option( 'acdc_of_manager_url', '' ) );
    $manager_key = trim( (string) get_option( 'acdc_of_manager_api_key', '' ) );

    if ( '' === $manager_url || '' === $manager_key ) {
      return new \WP_Error( 'acdc_sync_config', 'URL ou clé API du manager non configurée.' );
    }

    $endpoint = rtrim( $manager_url, '/' ) . '/wp-json/acdc-fm/v1/formations';

    $response = wp_remote_get( $endpoint, array(
      'timeout'   => 30,
      'sslverify' => true, // Requis sur hébergement mutualisé N0C PlanetHoster
      'headers'   => array(
        'Authorization'       => 'Bearer ' . $manager_key,
        'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        'Pragma'              => 'no-cache',
        'X-LiteSpeed-Purge'   => '*',
        'X-No-Cache'          => '1',
        'User-Agent'          => 'ACDC-SAAS-Sync/1.0',
      ),
    ) );

    if ( is_wp_error( $response ) ) {
      return $response;
    }

    $code = wp_remote_retrieve_response_code( $response );
    if ( (int) $code !== 200 ) {
      return new \WP_Error( 'acdc_sync_http', 'Le manager a répondu avec le code HTTP ' . $code );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! isset( $body['formations'] ) ) {
      return new \WP_Error( 'acdc_sync_json', 'Réponse JSON invalide depuis le manager.' );
    }

    // Appel interne à rest_sync_formations sans passer par HTTP
    $request = new \WP_REST_Request( 'POST', '/acdc-of/v1/sync-formations' );
    $request->set_header( 'Content-Type', 'application/json' );
    $request->set_body( wp_json_encode( $body ) );
    return $this->rest_sync_formations( $request );
  }

  /**
   * Handler admin-post : déclenche la sync manuelle depuis le bouton SAAS.
   */
  public function handle_manual_sync_formations() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      wp_die( 'Accès réservé.' );
    }
    check_admin_referer( 'acdc_manual_sync_formations' );

    $result = $this->run_formations_sync();

    if ( is_wp_error( $result ) ) {
      $this->redirect_to_portal( 'formations', 'Erreur de synchronisation : ' . $result->get_error_message(), 'error' );
    } else {
      $msg = isset( $result->data['message'] ) ? $result->data['message'] : 'Synchronisation terminée.';
      $this->redirect_to_portal( 'formations', $msg, 'success' );
    }
  }


  /**
   * Handler admin-post dédié pour les réglages de synchronisation.
   * Formulaire indépendant — ne dépend pas de handle_save_branding.
   */
  public function handle_save_sync_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( 'Accès refusé.' );
    }
    check_admin_referer( 'acdc_save_sync_settings' );

    $manager_url = isset( $_POST['acdc_of_manager_url'] )
      ? esc_url_raw( trim( wp_unslash( (string) $_POST['acdc_of_manager_url'] ) ) )
      : '';
    $manager_key = isset( $_POST['acdc_of_manager_api_key'] )
      ? sanitize_text_field( wp_unslash( (string) $_POST['acdc_of_manager_api_key'] ) )
      : '';

    update_option( 'acdc_of_manager_url',     $manager_url, false );
    update_option( 'acdc_of_manager_api_key', $manager_key, false );

    $redirect_page = isset( $_POST['redirect_page'] )
      ? sanitize_key( wp_unslash( $_POST['redirect_page'] ) )
      : 'acdc-of-configuration';

    wp_safe_redirect( admin_url( 'admin.php?page=' . $redirect_page . '&sync_saved=1#sync' ) );
    exit;
  }


  /* -----------------------------------------------------------------------
   * ACDC 3.24.11 — Bilans compétences formateurs (indicateur 21 Qualiopi).
   * ----------------------------------------------------------------------- */

  public function handle_save_trainer_evaluation() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    $trainer_id = isset( $_POST['trainer_id'] ) ? absint( wp_unslash( $_POST['trainer_id'] ) ) : 0;
    $eval_id    = isset( $_POST['eval_id'] )    ? absint( wp_unslash( $_POST['eval_id'] ) )    : 0;
    if ( ! $trainer_id ) { $this->redirect_to_portal( 'trainers', 'Formateur introuvable.', 'error' ); return; }
    check_admin_referer( 'acdc_save_trainer_evaluation_' . $trainer_id );
    $eval_date = isset( $_POST['evaluation_date'] ) && '' !== sanitize_text_field( wp_unslash( $_POST['evaluation_date'] ) ) ? sanitize_text_field( wp_unslash( $_POST['evaluation_date'] ) ) : current_time( 'Y-m-d' );
    global $wpdb;
    $data = array(
      'trainer_id'       => $trainer_id,
      'evaluation_date'  => $eval_date,
      'eval_type'        => isset( $_POST['eval_type'] )        ? sanitize_text_field( wp_unslash( $_POST['eval_type'] ) )           : 'entretien',
      'skills_evaluated' => isset( $_POST['skills_evaluated'] ) ? sanitize_textarea_field( wp_unslash( $_POST['skills_evaluated'] ) ) : '',
      'level_reached'    => isset( $_POST['level_reached'] )    ? min( 4, max( 0, absint( $_POST['level_reached'] ) ) )              : 0,
      'objectives_set'   => isset( $_POST['objectives_set'] )   ? sanitize_textarea_field( wp_unslash( $_POST['objectives_set'] ) )  : '',
      'comment_text'     => isset( $_POST['comment_text'] )     ? sanitize_textarea_field( wp_unslash( $_POST['comment_text'] ) )    : '',
    );
    $formats = array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' );
    if ( $eval_id ) {
      $wpdb->update( $this->trainer_evaluation_table, $data, array( 'id' => $eval_id ), $formats, array( '%d' ) );
      $msg = 'Bilan mis à jour.';
    } else {
      $data['created_at'] = current_time( 'mysql' );
      $formats[] = '%s';
      $wpdb->insert( $this->trainer_evaluation_table, $data, $formats );
      $msg = 'Bilan enregistré.';
    }
    $is_admin_ctx = isset( $_POST['page'] ) && 'acdc-of-trainers' === $_POST['page'];
    $redirect = $is_admin_ctx ? admin_url( 'admin.php?page=acdc-of-trainers&action=edit&item_id=' . $trainer_id . '&notice=' . rawurlencode( $msg ) . '&notice_type=success' ) : $this->portal_page_url( array( 'tab' => 'trainers', 'action' => 'edit', 'item_id' => $trainer_id, 'notice' => rawurlencode( $msg ), 'notice_type' => 'success' ) );
    wp_safe_redirect( $redirect );
    exit;
  }

  public function handle_delete_trainer_evaluation() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    $eval_id    = isset( $_GET['eval_id'] )    ? absint( wp_unslash( $_GET['eval_id'] ) )    : 0;
    $trainer_id = isset( $_GET['trainer_id'] ) ? absint( wp_unslash( $_GET['trainer_id'] ) ) : 0;
    if ( ! $eval_id || ! $trainer_id ) { $this->redirect_to_portal( 'trainers', 'Bilan introuvable.', 'error' ); return; }
    check_admin_referer( 'acdc_delete_trainer_evaluation_' . $eval_id );
    global $wpdb;
    $wpdb->delete( $this->trainer_evaluation_table, array( 'id' => $eval_id, 'trainer_id' => $trainer_id ), array( '%d', '%d' ) );
    $is_admin_ctx = isset( $_GET['page'] ) && 'acdc-of-trainers' === $_GET['page'];
    $redirect = $is_admin_ctx ? admin_url( 'admin.php?page=acdc-of-trainers&action=edit&item_id=' . $trainer_id . '&notice=' . rawurlencode( 'Bilan supprimé.' ) . '&notice_type=success' ) : $this->portal_page_url( array( 'tab' => 'trainers', 'action' => 'edit', 'item_id' => $trainer_id, 'notice' => rawurlencode( 'Bilan supprimé.' ), 'notice_type' => 'success' ) );
    wp_safe_redirect( $redirect );
    exit;
  }

  /* ---------------------------------------------------------------
   * ACDC 3.24.21 — Planification des crons sur hook 'wp'
   * Règle : jamais wp_schedule_event() dans __construct() — toujours via hook 'wp'.
   * --------------------------------------------------------------- */
  public function register_cron_events() {
    /* Cron NAD — envoi/relance analyses du besoin (2×/jour). */
    if ( ! wp_next_scheduled( 'acdc_nad_cron_send_and_relance' ) ) {
      wp_schedule_event( time(), 'twicedaily', 'acdc_nad_cron_send_and_relance' );
    }
    /* Cron nuit push indicateurs SAAS → Manager (3h). */
    if ( ! wp_next_scheduled( 'acdc_of_cron_push_indicators' ) ) {
      $tomorrow_3am = strtotime( 'tomorrow 3:00am' );
      wp_schedule_event( $tomorrow_3am, 'daily', 'acdc_of_cron_push_indicators' );
    }
    /* Cron nuit sync formations Manager → SAAS (3h15 pour décaler du push). */
    if ( ! wp_next_scheduled( 'acdc_of_cron_sync_formations' ) ) {
      $sync_time = strtotime( 'tomorrow 3:15am' );
      wp_schedule_event( $sync_time, 'daily', 'acdc_of_cron_sync_formations' );
    }
    /* Cron alerte absences / ruptures de parcours (1×/jour). */
    if ( ! wp_next_scheduled( 'acdc_of_absence_alert_cron' ) ) {
      wp_schedule_event( time(), 'daily', 'acdc_of_absence_alert_cron' );
    }
    /* Cron rapport de rétention RGPD (1×/jour, lecture seule — ne supprime rien). */
    if ( ! wp_next_scheduled( 'acdc_of_retention_scan_cron' ) ) {
      $retention_time = strtotime( 'tomorrow 3:30am' );
      wp_schedule_event( $retention_time ?: time(), 'daily', 'acdc_of_retention_scan_cron' );
    }
  }

  /* ---------------------------------------------------------------
   * Rapport de rétention RGPD (art. 5-1-e) — MODE RAPPORT, non destructif.
   *
   * Recense (en lecture seule) le nombre d'enregistrements ayant dépassé leur
   * durée de conservation, à partir de la brique ACDC\Support\Retention, et stocke
   * un rapport dans l'option `acdc_of_retention_report`. AUCUNE suppression n'est
   * effectuée : la purge effective fera l'objet d'une action explicite et confirmée.
   * Entièrement protégé (try/catch) : ne peut ni planter le site ni perdre de données.
   * --------------------------------------------------------------- */
  public function cron_retention_scan() {
    global $wpdb;
    try {
      if ( ! class_exists( '\\ACDC\\Support\\Retention' ) ) {
        return;
      }
      $table = isset( $this->prospect_table ) && $this->prospect_table
        ? $this->prospect_table
        : $wpdb->prefix . 'acdc_of_prospects';

      // La table doit exister (installation partielle, autre schéma…).
      if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
        return;
      }

      $reference = gmdate( 'Y-m-d' );
      $years     = \ACDC\Support\Retention::yearsFor( 'prospect' );
      $cutoff    = \ACDC\Support\Retention::cutoffDate( $years, $reference );
      if ( '' === $cutoff ) {
        return;
      }

      $total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
      $purgeable = (int) $wpdb->get_var(
        $wpdb->prepare(
          "SELECT COUNT(*) FROM {$table} WHERE DATE( COALESCE( updated_at, created_at ) ) <= %s",
          $cutoff
        )
      );

      update_option(
        'acdc_of_retention_report',
        array(
          'generated_at' => current_time( 'mysql' ),
          'reference'    => $reference,
          'categories'   => array(
            'prospect' => array(
              'label'     => 'Prospects (CRM)',
              'years'     => $years,
              'cutoff'    => $cutoff,
              'total'     => $total,
              'purgeable' => $purgeable,
            ),
          ),
        ),
        false
      );
    } catch ( \Throwable $e ) {
      update_option(
        'acdc_of_retention_report_error',
        array( 'at' => current_time( 'mysql' ), 'message' => $e->getMessage() ),
        false
      );
    }
  }

  /* ---------------------------------------------------------------
   * Encart tableau de bord — rapport de rétention RGPD (lecture seule).
   * Réservé aux administrateurs ; affiche le dernier rapport du cron.
   * --------------------------------------------------------------- */
  public function register_retention_dashboard_widget() {
    if ( ! function_exists( 'wp_add_dashboard_widget' ) || ! current_user_can( 'manage_options' ) ) {
      return;
    }
    wp_add_dashboard_widget(
      'acdc_of_retention_widget',
      'ACDC — Rétention RGPD',
      array( $this, 'render_retention_dashboard_widget' )
    );
  }

  public function render_retention_dashboard_widget() {
    $report = get_option( 'acdc_of_retention_report', array() );
    if ( empty( $report ) || empty( $report['categories'] ) || ! is_array( $report['categories'] ) ) {
      echo '<p>' . esc_html__( 'Aucun rapport de rétention pour le moment. Le scan quotidien s’exécutera automatiquement.', 'acdc-formation-saas-organisme-de-formation' ) . '</p>';
      return;
    }
    echo '<p style="color:#666;margin-top:0;">'
      . esc_html( sprintf( 'Généré le %s — mode rapport (aucune suppression automatique).', (string) ( $report['generated_at'] ?? '' ) ) )
      . '</p>';
    echo '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
    echo '<thead><tr>'
      . '<th style="text-align:left;padding:4px 6px;border-bottom:1px solid #e0e0e0;">Catégorie</th>'
      . '<th style="text-align:right;padding:4px 6px;border-bottom:1px solid #e0e0e0;">Conservation</th>'
      . '<th style="text-align:right;padding:4px 6px;border-bottom:1px solid #e0e0e0;">Total</th>'
      . '<th style="text-align:right;padding:4px 6px;border-bottom:1px solid #e0e0e0;">À purger</th>'
      . '</tr></thead><tbody>';
    foreach ( $report['categories'] as $cat ) {
      if ( ! is_array( $cat ) ) {
        continue;
      }
      $purgeable = (int) ( $cat['purgeable'] ?? 0 );
      echo '<tr>'
        . '<td style="padding:4px 6px;">' . esc_html( (string) ( $cat['label'] ?? '' ) ) . '</td>'
        . '<td style="text-align:right;padding:4px 6px;">' . esc_html( (string) ( $cat['years'] ?? '' ) ) . ' ans</td>'
        . '<td style="text-align:right;padding:4px 6px;">' . esc_html( (string) (int) ( $cat['total'] ?? 0 ) ) . '</td>'
        . '<td style="text-align:right;padding:4px 6px;font-weight:600;color:' . ( $purgeable > 0 ? '#b32d2e' : '#1a7f37' ) . ';">' . esc_html( (string) $purgeable ) . '</td>'
        . '</tr>';
    }
    echo '</tbody></table>';
    echo '<p style="color:#666;margin-bottom:0;">' . esc_html__( 'Les données « à purger » ont dépassé leur durée de conservation. La suppression reste une action manuelle et confirmée.', 'acdc-formation-saas-organisme-de-formation' ) . '</p>';
  }

  /* ---------------------------------------------------------------
   * ACDC 3.25.135 — Vérification publique d'authenticité d'une attestation.
   * Route : ?acdc_verify=<REFERENCE>&sig=<TOKEN>
   *   - Public : vérifie que la référence est bien formée (clé de contrôle) et que
   *     la signature correspond au secret du site → attestation authentique ou non.
   *   - Admin sans `sig` : génère l'URL vérifiable à communiquer (mode génération).
   * S'appuie sur ACDC\Support\CertificateCode. Rendu autonome, aucune donnée modifiée.
   * --------------------------------------------------------------- */
  public function maybe_handle_attestation_verify() {
    if ( ! isset( $_GET['acdc_verify'] ) ) {
      return;
    }
    if ( ! class_exists( '\\ACDC\\Support\\CertificateCode' ) ) {
      return;
    }
    $reference = sanitize_text_field( wp_unslash( $_GET['acdc_verify'] ) );
    $sig       = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';
    $secret    = wp_salt( 'secure_auth' );
    $org       = method_exists( $this, 'get_branding_options' ) ? (string) ( $this->get_branding_options()['company_name'] ?? 'ACDC Formation' ) : 'ACDC Formation';

    // Mode génération (administrateur, sans signature fournie) : proposer l'URL vérifiable.
    if ( '' === $sig && current_user_can( 'manage_options' ) && '' !== $reference ) {
      $ref   = ctype_digit( $reference )
        ? \ACDC\Support\CertificateCode::format( (int) gmdate( 'Y' ), (int) $reference )
        : strtoupper( $reference );
      $token = \ACDC\Support\CertificateCode::sign( $ref, $secret );
      $url   = add_query_arg( array( 'acdc_verify' => rawurlencode( $ref ), 'sig' => $token ), home_url( '/' ) );
      $this->render_attestation_verify_page(
        'generate',
        $org,
        array( 'reference' => $ref, 'url' => $url )
      );
      return;
    }

    $ref_upper   = strtoupper( $reference );
    $well_formed = \ACDC\Support\CertificateCode::isWellFormed( $ref_upper );
    $valid       = $well_formed && \ACDC\Support\CertificateCode::verify( $ref_upper, $sig, $secret );
    $this->render_attestation_verify_page(
      $valid ? 'valid' : 'invalid',
      $org,
      array( 'reference' => $ref_upper )
    );
  }

  /**
   * Rendu autonome de la page de vérification, puis arrêt.
   *
   * @param string $state 'valid' | 'invalid' | 'generate'
   * @param string $org
   * @param array  $data
   * @return void
   */
  private function render_attestation_verify_page( $state, $org, $data ) {
    while ( ob_get_level() ) {
      ob_end_clean();
    }
    nocache_headers();
    header( 'Content-Type: text/html; charset=UTF-8' );
    status_header( 'invalid' === $state ? 404 : 200 );

    $ref = isset( $data['reference'] ) ? (string) $data['reference'] : '';
    if ( 'valid' === $state ) {
      $badge = '#1a7f37'; $icon = '✓';
      $title = 'Attestation authentique';
      $msg   = 'Cette attestation a bien été émise par ' . $org . '. Sa référence et sa signature sont valides.';
    } elseif ( 'generate' === $state ) {
      $badge = '#2271b1'; $icon = '🔗';
      $title = 'URL de vérification';
      $msg   = 'Communiquez cette adresse (ou son QR code) pour permettre la vérification de l’attestation.';
    } else {
      $badge = '#b32d2e'; $icon = '✕';
      $title = 'Attestation non vérifiée';
      $msg   = 'La référence ou la signature est invalide. Ce document n’a pas pu être authentifié.';
    }

    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . esc_html( $title ) . ' — ' . esc_html( $org ) . '</title></head>';
    echo '<body style="margin:0;font-family:system-ui,Arial,sans-serif;background:#f4f5f7;color:#1d2327;">';
    echo '<div style="max-width:560px;margin:8vh auto;padding:28px;background:#fff;border-radius:14px;box-shadow:0 6px 24px rgba(0,0,0,.08);">';
    echo '<div style="font-size:44px;line-height:1;color:' . esc_attr( $badge ) . ';">' . esc_html( $icon ) . '</div>';
    echo '<h1 style="margin:10px 0 6px;font-size:22px;">' . esc_html( $title ) . '</h1>';
    echo '<p style="color:#50575e;">' . esc_html( $msg ) . '</p>';
    if ( '' !== $ref ) {
      echo '<p style="margin-top:16px;"><span style="color:#666;">Référence :</span><br><code style="font-size:16px;">' . esc_html( $ref ) . '</code></p>';
    }
    if ( 'generate' === $state && ! empty( $data['url'] ) ) {
      echo '<p style="margin-top:12px;word-break:break-all;"><a href="' . esc_url( $data['url'] ) . '">' . esc_html( $data['url'] ) . '</a></p>';
    }
    echo '<p style="margin-top:22px;color:#8c8f94;font-size:12px;">' . esc_html( $org ) . ' — vérification d’authenticité</p>';
    echo '</div></body></html>';
    exit;
  }

  /* ---------------------------------------------------------------
   * ACDC 3.25.135 — Preuve d'horodatage scellé à la signature.
   * Écoute `acdc_sig_request_signed` (émis APRÈS la signature) et enregistre un
   * jeton d'horodatage scellé (ACDC\Support\Timestamp) sur l'empreinte du document
   * signé, dans le journal d'audit de la signature. Prêt pour l'horodatage qualifié
   * eIDAS (branchable via le filtre acdc_timestamp_token). Entièrement protégé :
   * s'exécute après coup et n'interrompt jamais le flux de signature.
   *
   * @param int    $request_id
   * @param object $request
   * @return void
   * --------------------------------------------------------------- */
  public function handle_sig_timestamp_proof( $request_id, $request ) {
    try {
      if ( ! class_exists( '\\ACDC\\Support\\Timestamp' ) || ! is_object( $request ) ) {
        return;
      }
      $hash = ! empty( $request->signed_pdf_sha256 )
        ? (string) $request->signed_pdf_sha256
        : (string) ( $request->doc_sha256 ?? '' );
      if ( ! preg_match( '/^[0-9a-f]{64}$/', $hash ) ) {
        return;
      }
      $token = \ACDC\Support\Timestamp::create( $hash );
      if ( empty( $token ) ) {
        return;
      }
      global $wpdb;
      $table = $wpdb->prefix . 'acdc_sig_audit';
      if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
        return;
      }
      $wpdb->insert(
        $table,
        array(
          'request_id' => (int) $request_id,
          'event'      => 'horodatage_scelle',
          'details'    => wp_json_encode( $token ),
          'ip'         => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
          'user_agent' => '',
          'created_at' => gmdate( 'Y-m-d H:i:s' ),
        )
      );
    } catch ( \Throwable $e ) {
      // Silencieux : la preuve d'horodatage est un plus ; elle ne doit jamais perturber la signature.
      return;
    }
  }

  /* ---------------------------------------------------------------
   * ACDC 3.24.21 — Exécution cron sync formations Manager → SAAS
   * --------------------------------------------------------------- */
  public function cron_sync_formations_from_manager() {
    $result = $this->run_formations_sync();
    if ( is_wp_error( $result ) ) {
      update_option( 'acdc_of_last_sync_cron_error', array(
        'at'      => current_time( 'mysql' ),
        'message' => $result->get_error_message(),
      ), false );
    }
  }

  // ── ACDC 3.25.22 — Génération PDF analyse du besoin apprenant ────────────
  public function handle_generate_nad_apprenant_pdf() {
    $this->require_manage_options();
    $nad_id = isset( $_GET['nad_id'] ) ? absint( wp_unslash( $_GET['nad_id'] ) ) : 0;
    if ( ! $nad_id || ! check_admin_referer( 'acdc_nad_apprenant_pdf_' . $nad_id ) ) {
      wp_die( esc_html( 'Requête invalide.' ) );
    }
    global $wpdb;
    $nad = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->need_analysis_table} WHERE id = %d", $nad_id ) );
    if ( ! $nad ) { wp_die( esc_html( 'Analyse introuvable.' ) ); }

    $html     = $this->acdc_build_nad_apprenant_pdf_html( $nad );
    $filename = 'analyse-besoin-' . sanitize_file_name( (string) $nad->title ) . '.pdf';

    // Sauvegarder le PDF sur disque et stocker l'URL
    $uploads = wp_upload_dir();
    if ( ! empty( $uploads['basedir'] ) && ! empty( $uploads['baseurl'] ) ) {
      $dir_path = trailingslashit( $uploads['basedir'] ) . 'acdc-nad-apprenants/' . $nad_id . '/';
      $dir_url  = trailingslashit( $uploads['baseurl'] ) . 'acdc-nad-apprenants/' . $nad_id . '/';
      if ( wp_mkdir_p( $dir_path ) ) {
        $filepath = $dir_path . $filename;
        $fileurl  = $dir_url . rawurlencode( $filename );
        // Générer le PDF en string via mPDF output 'S'
        $autoload = dirname( dirname( dirname( dirname( plugin_dir_path( __FILE__ ) ) ) ) ) . '/acdc-libs/vendor/autoload.php';
        if ( file_exists( $autoload ) ) { require_once $autoload; }
        if ( class_exists( '\Mpdf\Mpdf' ) ) {
          while ( ob_get_level() ) { ob_end_clean(); }
          $mpdf = new \Mpdf\Mpdf( array(
            'format' => 'A4', 'margin_top' => 14, 'margin_bottom' => 14,
            'margin_left' => 14, 'margin_right' => 14, 'tempDir' => sys_get_temp_dir(),
          ) );
          $mpdf->SetTitle( sanitize_file_name( $filename ) );
          $mpdf->WriteHTML( $html );
          $pdf_raw = $mpdf->Output( $filename, 'S' );
          if ( is_string( $pdf_raw ) && '' !== $pdf_raw ) {
            file_put_contents( $filepath, $pdf_raw );
            // Stocker l'URL dans la NAD
            $wpdb->update(
              $this->need_analysis_table,
              array( 'document_url_apprenant' => esc_url_raw( $fileurl ) ),
              array( 'id' => $nad_id ),
              array( '%s' ), array( '%d' )
            );
            // Streamer depuis le fichier sauvegardé
            header( 'Content-Type: application/pdf' );
            header( 'Content-Disposition: inline; filename="' . $filename . '"' );
            header( 'Content-Length: ' . filesize( $filepath ) );
            readfile( $filepath );
            exit;
          }
        }
      }
    }
    // Fallback : stream direct sans sauvegarde
  /* ACDC 3.25.256 — Filet commun à toutes les fabrications de PDF : en cas
     d'échec, le document part en version imprimable plutôt que de laisser un
     écran blanc. L'erreur réelle est journalisée par render_html_pdf(). */
    try {
      $this->render_html_pdf( $html, $filename, 'inline' );
    } catch ( \Throwable $e ) {
      while ( ob_get_level() ) { ob_end_clean(); }
      nocache_headers();
      header( 'Content-Type: text/html; charset=UTF-8' );
      echo '<div style="max-width:800px;margin:12px auto;padding:10px 14px;border:1px solid #e8c97a;background:#fff8e8;border-radius:6px;font-family:sans-serif;font-size:13px;color:#7a5c00;">Le PDF n\'a pas pu être fabriqué : voici la version imprimable. Utilisez « Imprimer » puis « Enregistrer au format PDF ».</div>' . $html; // phpcs:ignore WordPress.Security.EscapeOutput
      exit;
    }
  }

  // ── ACDC 3.25.42 — Dispatcher front des actions trf_action (template_redirect, avant tout rendu)
  public function handle_front_trf_actions() {
    if ( is_admin() ) { return; }
    if ( ! isset( $_GET['trf_action'] ) ) { return; }
    $trf_action = sanitize_key( wp_unslash( $_GET['trf_action'] ) );
    /* ACDC 3.25.169 — 'resend_extranet' et 'open_extranet_access' rejoignent ce
       dispatcher central. Le renvoi de l'e-mail d'ouverture n'était traité qu'à
       l'intérieur de render_front_register_training_tab() : l'URL du bouton ne
       portait aucun paramètre « tab », l'onglet retombait donc sur le tableau de
       bord et le code de renvoi n'était JAMAIS exécuté. Le clic renvoyait
       silencieusement à l'accueil, sans e-mail et sans message d'erreur. Ici, on
       s'exécute sur template_redirect, avant tout rendu, quel que soit l'onglet. */
    if ( ! in_array( $trf_action, array( 'delete_file', 'delete_formation', 'delete_complaint', 'delete_archive_email', 'delete_proposal', 'resend_extranet', 'open_extranet_access' ), true ) ) { return; }
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) { return; }
    global $wpdb;

    if ( 'resend_extranet' === $trf_action || 'open_extranet_access' === $trf_action ) {
      $rid    = isset( $_GET['registration_id'] ) ? absint( wp_unslash( $_GET['registration_id'] ) ) : 0;
      $rnonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
      $nonce_action = ( 'resend_extranet' === $trf_action )
        ? 'acdc_resend_learner_extranet_email_' . $rid
        : 'acdc_open_learner_extranet_access_' . $rid;
      if ( ! $rid || ! wp_verify_nonce( $rnonce, $nonce_action ) ) {
        wp_safe_redirect( $this->portal_page_url( array( 'tab' => 'trf_apprenants', 'notice' => rawurlencode( 'Lien expiré. Rechargez la page et réessayez.' ), 'notice_type' => 'error' ) ) );
        exit;
      }
      if ( 'resend_extranet' === $trf_action ) {
        $this->handle_resend_learner_extranet_email();
      } else {
        $this->handle_open_learner_extranet_access();
      }
      exit;
    }

    if ( 'delete_proposal' === $trf_action ) {
      $pid    = isset( $_GET['proposal_id'] ) ? absint( wp_unslash( $_GET['proposal_id'] ) ) : 0;
      $rnonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
      if ( ! $pid || ! wp_verify_nonce( $rnonce, 'acdc_delete_proposal_' . $pid ) ) {
        wp_safe_redirect( $this->portal_page_url( array( 'tab' => 'proposals' ) ) );
        exit;
      }
      $proposal_table = $wpdb->prefix . 'acdc_of_proposals';
      // Vérifier si liée à une convention
      $proposal_row = $wpdb->get_row( $wpdb->prepare( "SELECT need_id FROM {$proposal_table} WHERE id = %d", $pid ) );
      if ( $proposal_row && ! empty( $proposal_row->need_id ) ) {
        $need_row = $wpdb->get_row( $wpdb->prepare( "SELECT source_prospect_id FROM {$this->need_table} WHERE id = %d", (int) $proposal_row->need_id ) );
        if ( $need_row && ! empty( $need_row->source_prospect_id ) ) {
          $convention_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->registration_contract_table} WHERE source_prospect_id = %d", (int) $need_row->source_prospect_id ) );
          if ( $convention_count ) {
            $redirect = add_query_arg( array( 'notice' => rawurlencode( 'Suppression impossible : cette proposition est liée à une convention.' ), 'notice_type' => 'error' ), $this->portal_page_url( array( 'tab' => 'proposals' ) ) );
            wp_safe_redirect( $redirect );
            exit;
          }
        }
      }
      $wpdb->delete( $proposal_table, array( 'id' => $pid ) );
      if ( function_exists( 'acdc_of_saas_purge_all_caches' ) ) { acdc_of_saas_purge_all_caches(); }
      $redirect = add_query_arg( array( 'notice' => rawurlencode( 'Proposition supprimée.' ), 'notice_type' => 'success' ), $this->portal_page_url( array( 'tab' => 'proposals' ) ) );
      wp_safe_redirect( $redirect );
      exit;
    }
    if ( 'delete_archive_email' === $trf_action ) {
      $aid    = isset( $_GET['archive_id'] ) ? sanitize_text_field( wp_unslash( $_GET['archive_id'] ) ) : '';
      $rnonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
      $sub    = isset( $_GET['archive_sub'] ) ? sanitize_key( wp_unslash( $_GET['archive_sub'] ) ) : 'destinataires';
      if ( ! $aid || ! wp_verify_nonce( $rnonce, 'acdc_delete_archive_email_' . $aid ) ) {
        wp_safe_redirect( $this->portal_page_url( array( 'tab' => 'marketing_email_archive' ) ) );
        exit;
      }
      $archive = $this->get_marketing_store( 'email_archive', array() );
      if ( is_array( $archive ) ) {
        $archive = array_values( array_filter( $archive, function( $e ) use ( $aid ) {
          return ! isset( $e['id'] ) || (string) $e['id'] !== $aid;
        } ) );
        $this->update_marketing_store( 'email_archive', $archive );
      }
      if ( function_exists( 'acdc_of_saas_purge_all_caches' ) ) { acdc_of_saas_purge_all_caches(); }
      $redirect = add_query_arg( array( 'notice' => rawurlencode( 'E-mail supprimé de l\'archive.' ), 'notice_type' => 'success', 'archive_sub' => $sub ), $this->portal_page_url( array( 'tab' => 'marketing_email_archive' ) ) );
      wp_safe_redirect( $redirect );
      exit;
    }
    if ( 'delete_complaint' === $trf_action ) {
      $cid    = isset( $_GET['complaint_id'] ) ? absint( wp_unslash( $_GET['complaint_id'] ) ) : 0;
      $rnonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
      if ( ! $cid || ! wp_verify_nonce( $rnonce, 'acdc_delete_complaint_' . $cid ) ) {
        wp_safe_redirect( $this->portal_page_url( array( 'tab' => 'complaints' ) ) );
        exit;
      }

      $wpdb->delete( $this->complaint_table, array( 'id' => $cid ) );
      if ( function_exists( 'acdc_of_saas_purge_all_caches' ) ) { acdc_of_saas_purge_all_caches(); }
      $redirect = add_query_arg( array( 'notice' => rawurlencode( 'Réclamation supprimée.' ), 'notice_type' => 'success' ), $this->portal_page_url( array( 'tab' => 'complaints' ) ) );
      wp_safe_redirect( $redirect );
      exit;
    }
    if ( 'delete_formation' === $trf_action ) {
      $fid    = isset( $_GET['formation_id'] ) ? absint( wp_unslash( $_GET['formation_id'] ) ) : 0;
      $rnonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
      if ( ! $fid || ! wp_verify_nonce( $rnonce, 'acdc_delete_formation_' . $fid ) ) {
        wp_safe_redirect( $this->portal_page_url( array( 'tab' => 'formations' ) ) );
        exit;
      }
      $session_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->session_table} WHERE formation_id = %d", $fid ) );
      if ( $session_count ) {
        $redirect = add_query_arg( array( 'notice' => rawurlencode( 'Suppression impossible : cette formation est liée à des sessions.' ), 'notice_type' => 'error' ), $this->portal_page_url( array( 'tab' => 'formations' ) ) );
        wp_safe_redirect( $redirect );
        exit;
      }
      $wpdb->delete( $this->formation_table, array( 'id' => $fid ) );
      if ( function_exists( 'acdc_of_saas_purge_all_caches' ) ) { acdc_of_saas_purge_all_caches(); }
      $redirect = add_query_arg( array( 'notice' => rawurlencode( 'Formation supprimée.' ), 'notice_type' => 'success' ), $this->portal_page_url( array( 'tab' => 'formations' ) ) );
      wp_safe_redirect( $redirect );
      exit;
    }
    $rid    = isset( $_GET['registration_id'] ) ? absint( wp_unslash( $_GET['registration_id'] ) ) : 0;
    $rnonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
    if ( ! $rid || ! wp_verify_nonce( $rnonce, 'acdc_delete_training_file_' . $rid ) ) {
      wp_safe_redirect( $this->portal_page_url( array( 'tab' => 'trf_inscriptions' ) ) );
      exit;
    }

    // Suppression en cascade

    // ACDC 3.25.115 — la table contrats n'a pas de registration_id : supprimer par id = autofill_contract_id.
    $reg = $wpdb->get_row( $wpdb->prepare( "SELECT autofill_contract_id FROM {$this->training_registration_table} WHERE id = %d", $rid ) );
    if ( $reg && ! empty( $reg->autofill_contract_id ) ) {
      $wpdb->delete( $this->registration_contract_table, array( 'id' => (int) $reg->autofill_contract_id ) );
    }
    $wpdb->delete( $this->need_analysis_table, array( 'dossier_id' => $rid ) );
    $wpdb->delete( $this->training_registration_table, array( 'id' => $rid ) );

    // Purger tous les caches
    if ( function_exists( 'acdc_of_saas_purge_all_caches' ) ) {
      acdc_of_saas_purge_all_caches();
    }

    // Redirection avec notice
    $redirect = add_query_arg(
      array( 'notice' => rawurlencode( 'Dossier supprimé.' ), 'notice_type' => 'success' ),
      $this->portal_page_url( array( 'tab' => 'trf_inscriptions' ) )
    );
    wp_safe_redirect( $redirect );
    exit;
  }
}
