<?php
/**
 * ACDC Évaluations des acquis — ACDC_Evaluations_Actions_Trait.
 *
 * Extraction micro-incrémentale du sous-bloc métier :
 * évaluations des acquis.
 *
 * @since 3.11.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Evaluations_Actions_Trait {

  public function handle_save_evaluation() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    check_admin_referer( 'acdc_save_evaluation' );
    global $wpdb;
    $evaluation_id = isset( $_POST['evaluation_id'] ) ? absint( wp_unslash( $_POST['evaluation_id'] ) ) : 0;
    $is_admin_page = is_admin() && isset( $_POST['page'] ) && 'acdc-of-evaluations' === $_POST['page'];
    $input = isset( $_POST['evaluation'] ) && is_array( $_POST['evaluation'] ) ? wp_unslash( $_POST['evaluation'] ) : array();
    $title = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
    $duration = isset( $input['duration_minutes'] ) ? absint( $input['duration_minutes'] ) : 0;
    $formation_ids = isset( $input['formation_ids'] ) && is_array( $input['formation_ids'] ) ? array_values( array_filter( array_map( 'absint', $input['formation_ids'] ) ) ) : array();
    $mode = isset( $input['correction_type'] ) ? sanitize_text_field( $input['correction_type'] ) : 'Correction automatique';
    if ( ! array_key_exists( $mode, $this->get_quiz_correction_options() ) ) {
      $mode = 'Correction automatique';
    }
    if ( '' === $title || $duration < 1 || empty( $formation_ids ) ) {
      $msg = 'Intitulé, durée du questionnaire et au moins une formation sont obligatoires.';
      if ( $is_admin_page ) { wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-evaluations&action=' . ( $evaluation_id ? 'edit&item_id=' . $evaluation_id : 'new' ) . '&notice=' . rawurlencode( $msg ) . '&notice_type=error&mode=' . rawurlencode( $mode ) ) ); exit; }
      $this->redirect_to_portal( 'evaluations', $msg, 'error', array( 'action' => $evaluation_id ? 'edit' : 'new', 'item_id' => $evaluation_id, 'mode' => $mode ) );
    }
    $questions_in = isset( $_POST['questions'] ) && is_array( $_POST['questions'] ) ? wp_unslash( $_POST['questions'] ) : array();
    $questions = array();
    foreach ( $questions_in as $q ) {
      if ( empty( $q['label'] ) && empty( $q['type'] ) && empty( $q['options'] ) ) { continue; }
      $questions[] = array(
        'label'  => sanitize_text_field( $q['label'] ?? '' ),
        'type'  => sanitize_text_field( $q['type'] ?? '' ),
        'options' => sanitize_textarea_field( $q['options'] ?? '' ),
      );
    }
    $scores_in = isset( $_POST['scoring'] ) && is_array( $_POST['scoring'] ) ? wp_unslash( $_POST['scoring'] ) : array();
    $scores = array();
    foreach ( $scores_in as $row ) {
      if ( empty( $row['type'] ) && empty( $row['threshold'] ) && empty( $row['label'] ) ) { continue; }
      $scores[] = array(
        'type'   => sanitize_text_field( $row['type'] ?? '' ),
        'threshold' => sanitize_text_field( $row['threshold'] ?? '' ),
        'label'   => sanitize_text_field( $row['label'] ?? '' ),
      );
    }
    if ( 'Sans correction automatique' === $mode ) {
      $scores = array();
    }
    $data = array(
      'title'      => $title,
      'description_text' => isset( $input['description_text'] ) ? sanitize_textarea_field( $input['description_text'] ) : '',
      'correction_type' => $mode,
      'duration_minutes' => $duration,
      'formation_ids'  => wp_json_encode( $formation_ids ),
      'question_blocks' => wp_json_encode( $questions ),
      'scoring_blocks'  => wp_json_encode( $scores ),
      'alert_notation'  => '',
      'updated_at'    => $this->now_mysql(),
    );
    if ( $evaluation_id ) {
      $result = $wpdb->update( $this->evaluation_table, $data, array( 'id' => $evaluation_id ) );
      $message = 'Évaluation des acquis mise à jour.';
    } else {
      $data['created_at'] = $this->now_mysql();
      $result = $wpdb->insert( $this->evaluation_table, $data );
      $evaluation_id = (int) $wpdb->insert_id;
      $message = 'Évaluation des acquis créée.';
    }
    if ( false === $result ) {
      $msg = $this->get_safe_db_error_message( 'Une erreur technique est survenue.' );
      if ( $is_admin_page ) { wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-evaluations&action=' . ( $evaluation_id ? 'edit&item_id=' . $evaluation_id : 'new' ) . '&notice=' . rawurlencode( $msg ) . '&notice_type=error&mode=' . rawurlencode( $mode ) ) ); exit; }
      $this->redirect_to_portal( 'evaluations', $msg, 'error', array( 'action' => $evaluation_id ? 'edit' : 'new', 'item_id' => $evaluation_id, 'mode' => $mode ) );
    }
    $target = $is_admin_page ? admin_url( 'admin.php?page=acdc-of-evaluations&action=edit&item_id=' . $evaluation_id . '&notice=' . rawurlencode( $message ) . '&notice_type=success' ) : $this->portal_page_url( array( 'tab' => 'evaluations', 'action' => 'edit', 'item_id' => $evaluation_id, 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ) );
    if ( ! empty( $_POST['save_and_add'] ) ) {
      $target = $is_admin_page ? admin_url( 'admin.php?page=acdc-of-evaluations&action=new&mode=' . rawurlencode( $mode ) . '&notice=' . rawurlencode( $message ) . '&notice_type=success' ) : $this->portal_page_url( array( 'tab' => 'evaluations', 'action' => 'new', 'mode' => $mode, 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ) );
    } elseif ( ! empty( $_POST['save_and_prepare_session'] ) ) {
      $target = $this->get_questionnaire_new_session_url( 'evaluation', $evaluation_id );
    }
    wp_safe_redirect( $target );
    exit;
  }

  public function handle_delete_evaluation() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $evaluation_id = isset( $_GET['evaluation_id'] ) ? absint( wp_unslash( $_GET['evaluation_id'] ) ) : 0;
    if ( ! $evaluation_id ) {
      $this->redirect_to_portal( 'evaluations', 'Évaluation des acquis introuvable.', 'error' );
    }
    check_admin_referer( 'acdc_delete_evaluation_' . $evaluation_id );
    global $wpdb;
    $__acdc_supprime = $wpdb->delete( $this->evaluation_table, array( 'id' => $evaluation_id ) );
    /* ACDC 3.25.300 — La trace est posée sur la suppression, pas sur la
       redirection : une page qui annonce « supprimé » ne prouve pas qu'une
       ligne l'ait été. Le résultat écrit ici est celui de la base. */
    $this->log_action_event( 'suppression_evaluation', 'evaluation', (int) $evaluation_id, $__acdc_supprime ? 'success' : 'error', array( 'lignes' => (int) $__acdc_supprime ) );
    if ( is_admin() && isset( $_GET['page'] ) && 'acdc-of-evaluations' === $_GET['page'] ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-evaluations&notice=' . rawurlencode( 'Évaluation des acquis supprimée.' ) . '&notice_type=success' ) ); exit;
    }
    $this->redirect_to_portal( 'evaluations', 'Évaluation des acquis supprimée.', 'success' );
  }


  public function handle_download_evaluation_result_document() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $registration_id = isset( $_GET['registration_id'] ) ? absint( wp_unslash( $_GET['registration_id'] ) ) : 0;
    if ( ! $registration_id ) {
      wp_die( esc_html( 'Résultat introuvable.' ) );
    }
    check_admin_referer( 'acdc_download_evaluation_result_document_' . $registration_id );
    $registration = $this->get_training_registration( $registration_id );
    if ( ! $registration ) {
      wp_die( esc_html( 'Résultat introuvable.' ) );
    }
    $context = $this->get_evaluation_result_context( $registration );
    $document = $context['document'];
    $filename = $this->get_evaluation_result_display_file_name( $registration, $context, $document );
    $mode = ( isset( $_GET['mode'] ) && 'inline' === sanitize_key( wp_unslash( $_GET['mode'] ) ) ) ? 'inline' : 'attachment';
    if ( ! empty( $document['path'] ) && file_exists( $document['path'] ) ) {
      $mime = 'application/pdf';
      while ( ob_get_level() ) { ob_end_clean(); }
      nocache_headers();
      header( 'Content-Type: ' . $mime );
      header( 'Content-Disposition: ' . ( 'inline' === $mode ? 'inline' : 'attachment' ) . '; filename="' . $filename . '"' );
      readfile( $document['path'] );
      exit;
    }
    $pages = $this->build_evaluation_result_pdf_pages( $registration, $context );
    $this->render_simple_pdf( $pages, $filename, $mode );
  }

  public function handle_update_evaluation_result_document() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $registration_id = isset( $_POST['registration_id'] ) ? absint( wp_unslash( $_POST['registration_id'] ) ) : 0;
    if ( ! $registration_id ) {
      wp_die( esc_html( 'Résultat introuvable.' ) );
    }
    check_admin_referer( 'acdc_update_evaluation_result_document_' . $registration_id );
    if ( empty( $_FILES['evaluation_result_document_file']['name'] ) ) {
      wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'evaluation_results', 'notice' => rawurlencode( 'Aucun fichier reçu.' ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
      exit;
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $upload = wp_handle_upload( $_FILES['evaluation_result_document_file'], array( 'test_form' => false, 'mimes' => array( 'pdf' => 'application/pdf' ) ) );
    if ( ! empty( $upload['error'] ) ) {
      wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'evaluation_results', 'notice' => rawurlencode( 'Téléversement impossible : ' . $upload['error'] ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
      exit;
    }
    global $wpdb;
    $result = $wpdb->update(
      $this->training_registration_table,
      array(
        'evaluation_result_document_url' => isset( $upload['url'] ) ? esc_url_raw( $upload['url'] ) : '',
        'evaluation_result_document_path' => isset( $upload['file'] ) ? sanitize_text_field( $upload['file'] ) : '',
        'updated_at' => current_time( 'mysql' ),
      ),
      array( 'id' => $registration_id )
    );
    if ( false === $result ) {
      wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'evaluation_results', 'notice' => rawurlencode( 'Erreur lors de la mise à jour.' ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
      exit;
    }
    wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'evaluation_results', 'notice' => rawurlencode( 'Résultat des évaluations des acquis mis à jour.' ), 'notice_type' => 'success' ), admin_url( 'admin.php' ) ) );
    exit;
  }

  public function handle_export_evaluation_qcm_details() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $registration_id = isset( $_POST['registration_id'] ) ? absint( wp_unslash( $_POST['registration_id'] ) ) : 0;
    if ( ! $registration_id ) {
      wp_die( esc_html( 'Résultat introuvable.' ) );
    }
    check_admin_referer( 'acdc_export_evaluation_qcm_details_' . $registration_id );
    $registration = $this->get_training_registration( $registration_id );
    if ( ! $registration ) {
      wp_die( esc_html( 'Résultat introuvable.' ) );
    }
    $context = $this->get_evaluation_result_context( $registration );
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
}
