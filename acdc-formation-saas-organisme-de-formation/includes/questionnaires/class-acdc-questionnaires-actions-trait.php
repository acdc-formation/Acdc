<?php
/**
 * ACDC Questionnaires / Enquêtes — actions extraites.
 *
 * @since 3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Questionnaires_Actions_Trait {

  public function handle_download_mid_survey_document() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $registration_id = isset( $_GET['registration_id'] ) ? absint( wp_unslash( $_GET['registration_id'] ) ) : 0;
    if ( ! $registration_id ) {
      wp_die( esc_html( 'Enquête intermédiaire introuvable.' ) );
    }
    check_admin_referer( 'acdc_download_mid_survey_document_' . $registration_id );
    $registration = $this->get_training_registration( $registration_id );
    if ( ! $registration ) {
      wp_die( esc_html( 'Enquête intermédiaire introuvable.' ) );
    }
    $context = $this->get_mid_survey_context( $registration );
    $document = $context['document'];
    $mode = isset( $_GET['mode'] ) && 'inline' === sanitize_key( wp_unslash( $_GET['mode'] ) ) ? 'inline' : 'attachment';
    $filename = $this->get_mid_survey_display_file_name( $registration, $context, $document );
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
    $pages = $this->build_mid_survey_pdf_pages( $registration, $context );
    $this->render_simple_pdf( $pages, $filename, $mode );
  }


  public function handle_update_mid_survey_document() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $registration_id = isset( $_POST['registration_id'] ) ? absint( wp_unslash( $_POST['registration_id'] ) ) : 0;
    if ( ! $registration_id ) {
      wp_die( esc_html( 'Enquête intermédiaire introuvable.' ) );
    }
    check_admin_referer( 'acdc_update_mid_survey_document_' . $registration_id );
    if ( empty( $_FILES['mid_survey_document_file']['name'] ) ) {
      wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'mid_surveys_documents', 'notice' => rawurlencode( 'Aucun fichier reçu.' ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
      exit;
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $upload = wp_handle_upload( $_FILES['mid_survey_document_file'], array( 'test_form' => false, 'mimes' => array( 'pdf' => 'application/pdf' ) ) );
    if ( ! empty( $upload['error'] ) ) {
      wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'mid_surveys_documents', 'notice' => rawurlencode( 'Téléversement impossible : ' . $upload['error'] ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
      exit;
    }
    global $wpdb;
    $result = $wpdb->update(
      $this->training_registration_table,
      array(
        'mid_survey_document_url' => isset( $upload['url'] ) ? esc_url_raw( $upload['url'] ) : '',
        'mid_survey_document_path' => isset( $upload['file'] ) ? sanitize_text_field( $upload['file'] ) : '',
        'updated_at' => $this->now_mysql(),
      ),
      array( 'id' => $registration_id )
    );
    if ( false === $result ) {
      wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'mid_surveys_documents', 'notice' => rawurlencode( 'Erreur lors de la mise à jour.' ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
      exit;
    }
    wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'mid_surveys_documents', 'notice' => rawurlencode( 'Enquêtes intermédiaires mise à jour.' ), 'notice_type' => 'success' ), admin_url( 'admin.php' ) ) );
    exit;
  }



  public function handle_download_hot_survey_document() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $registration_id = isset( $_GET['registration_id'] ) ? absint( wp_unslash( $_GET['registration_id'] ) ) : 0;
    if ( ! $registration_id ) {
      wp_die( esc_html( 'Enquête à chaud introuvable.' ) );
    }
    check_admin_referer( 'acdc_download_hot_survey_document_' . $registration_id );
    $registration = $this->get_training_registration( $registration_id );
    if ( ! $registration ) {
      wp_die( esc_html( 'Enquête à chaud introuvable.' ) );
    }
    $context = $this->get_hot_survey_context( $registration );
    $document = $context['document'];
    $mode = isset( $_GET['mode'] ) && 'inline' === sanitize_key( wp_unslash( $_GET['mode'] ) ) ? 'inline' : 'attachment';
    $filename = $this->get_hot_survey_display_file_name( $registration, $context, $document );
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
    $pages = $this->build_hot_survey_pdf_pages( $registration, $context );
    $this->render_simple_pdf( $pages, $filename, $mode );
  }


  public function handle_update_hot_survey_document() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $registration_id = isset( $_POST['registration_id'] ) ? absint( wp_unslash( $_POST['registration_id'] ) ) : 0;
    if ( ! $registration_id ) {
      wp_die( esc_html( 'Enquête à chaud introuvable.' ) );
    }
    check_admin_referer( 'acdc_update_hot_survey_document_' . $registration_id );
    if ( empty( $_FILES['hot_survey_document_file']['name'] ) ) {
      wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'hot_surveys_documents', 'notice' => rawurlencode( 'Aucun fichier reçu.' ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
      exit;
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $upload = wp_handle_upload( $_FILES['hot_survey_document_file'], array( 'test_form' => false, 'mimes' => array( 'pdf' => 'application/pdf' ) ) );
    if ( ! empty( $upload['error'] ) ) {
      wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'hot_surveys_documents', 'notice' => rawurlencode( 'Téléversement impossible : ' . $upload['error'] ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
      exit;
    }
    global $wpdb;
    $result = $wpdb->update(
      $this->training_registration_table,
      array(
        'hot_survey_document_url' => isset( $upload['url'] ) ? esc_url_raw( $upload['url'] ) : '',
        'hot_survey_document_path' => isset( $upload['file'] ) ? sanitize_text_field( $upload['file'] ) : '',
        'updated_at' => $this->now_mysql(),
      ),
      array( 'id' => $registration_id )
    );
    if ( false === $result ) {
      wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'hot_surveys_documents', 'notice' => rawurlencode( 'Erreur lors de la mise à jour.' ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
      exit;
    }
    wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'hot_surveys_documents', 'notice' => rawurlencode( 'Enquêtes à chaud mise à jour.' ), 'notice_type' => 'success' ), admin_url( 'admin.php' ) ) );
    exit;
  }


  public function handle_export_hot_survey_qcm_details() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $registration_id = isset( $_POST['registration_id'] ) ? absint( wp_unslash( $_POST['registration_id'] ) ) : 0;
    if ( ! $registration_id ) {
      wp_die( esc_html( 'Enquête à chaud introuvable.' ) );
    }
    check_admin_referer( 'acdc_export_hot_survey_qcm_details_' . $registration_id );
    $registration = $this->get_training_registration( $registration_id );
    if ( ! $registration ) {
      wp_die( esc_html( 'Enquête à chaud introuvable.' ) );
    }
    $context = $this->get_hot_survey_context( $registration );
    $survey = ! empty( $context['survey'] ) ? $context['survey'] : null;
    $question_blocks = array();
    if ( $survey && ! empty( $survey->question_blocks ) ) {
      if ( is_array( $survey->question_blocks ) ) {
        $question_blocks = $survey->question_blocks;
      } else {
        $decoded = json_decode( (string) $survey->question_blocks, true );
        if ( is_array( $decoded ) ) {
          $question_blocks = $decoded;
        }
      }
    }
    $filename = isset( $_POST['export_filename'] ) ? sanitize_file_name( wp_unslash( $_POST['export_filename'] ) ) : 'details-qcm-enquete-chaud';
    if ( '' === $filename ) { $filename = 'details-qcm-enquete-chaud'; }
    $type = isset( $_POST['export_type'] ) ? sanitize_key( wp_unslash( $_POST['export_type'] ) ) : 'excel';
    $rows = array();
    $rows[] = array( 'Ordre', 'Question', 'Type', 'Explication', 'Apprenant', 'Formation', 'Enquête' );
    $index = 1;
    foreach ( (array) $question_blocks as $question ) {
      $rows[] = array(
        (string) $index,
        isset( $question['question'] ) ? (string) $question['question'] : '',
        isset( $question['block_type'] ) ? (string) $question['block_type'] : '',
        isset( $question['explanation'] ) ? (string) $question['explanation'] : '',
        (string) $context['learner_name'],
        (string) $context['formation_title'],
        (string) $context['survey_title'],
      );
      $index++;
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



  public function handle_download_cold_survey_document() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $registration_id = isset( $_GET['registration_id'] ) ? absint( wp_unslash( $_GET['registration_id'] ) ) : 0;
    if ( ! $registration_id ) {
      wp_die( esc_html( 'Enquête à froid introuvable.' ) );
    }
    check_admin_referer( 'acdc_download_cold_survey_document_' . $registration_id );
    $registration = $this->get_training_registration( $registration_id );
    if ( ! $registration ) {
      wp_die( esc_html( 'Enquête à froid introuvable.' ) );
    }
    $context = $this->get_cold_survey_context( $registration );
    $document = $context['document'];
    $mode = isset( $_GET['mode'] ) && 'inline' === sanitize_key( wp_unslash( $_GET['mode'] ) ) ? 'inline' : 'attachment';
    $filename = $this->get_cold_survey_display_file_name( $registration, $context, $document );
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
    $pages = $this->build_cold_survey_pdf_pages( $registration, $context );
    $this->render_simple_pdf( $pages, $filename, $mode );
  }


  public function handle_update_cold_survey_document() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $registration_id = isset( $_POST['registration_id'] ) ? absint( wp_unslash( $_POST['registration_id'] ) ) : 0;
    if ( ! $registration_id ) {
      wp_die( esc_html( 'Enquête à froid introuvable.' ) );
    }
    check_admin_referer( 'acdc_update_cold_survey_document_' . $registration_id );
    if ( empty( $_FILES['cold_survey_document_file']['name'] ) ) {
      wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'cold_surveys_documents', 'notice' => rawurlencode( 'Aucun fichier reçu.' ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
      exit;
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $upload = wp_handle_upload( $_FILES['cold_survey_document_file'], array( 'test_form' => false, 'mimes' => array( 'pdf' => 'application/pdf' ) ) );
    if ( ! empty( $upload['error'] ) ) {
      wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'cold_surveys_documents', 'notice' => rawurlencode( 'Téléversement impossible : ' . $upload['error'] ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
      exit;
    }
    global $wpdb;
    $result = $wpdb->update(
      $this->training_registration_table,
      array(
        'cold_survey_document_url' => isset( $upload['url'] ) ? esc_url_raw( $upload['url'] ) : '',
        'cold_survey_document_path' => isset( $upload['file'] ) ? sanitize_text_field( $upload['file'] ) : '',
        'updated_at' => $this->now_mysql(),
      ),
      array( 'id' => $registration_id )
    );
    if ( false === $result ) {
      wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'cold_surveys_documents', 'notice' => rawurlencode( 'Erreur lors de la mise à jour.' ), 'notice_type' => 'error' ), admin_url( 'admin.php' ) ) );
      exit;
    }
    wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-dashboard', 'tab' => 'cold_surveys_documents', 'notice' => rawurlencode( 'Enquêtes à froid mise à jour.' ), 'notice_type' => 'success' ), admin_url( 'admin.php' ) ) );
    exit;
  }


  public function handle_export_cold_survey_qcm_details() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $registration_id = isset( $_POST['registration_id'] ) ? absint( wp_unslash( $_POST['registration_id'] ) ) : 0;
    if ( ! $registration_id ) {
      wp_die( esc_html( 'Enquête à froid introuvable.' ) );
    }
    check_admin_referer( 'acdc_export_cold_survey_qcm_details_' . $registration_id );
    $registration = $this->get_training_registration( $registration_id );
    if ( ! $registration ) {
      wp_die( esc_html( 'Enquête à froid introuvable.' ) );
    }
    $context = $this->get_cold_survey_context( $registration );
    $survey = ! empty( $context['survey'] ) ? $context['survey'] : null;
    $question_blocks = array();
    if ( $survey && ! empty( $survey->question_blocks ) ) {
      if ( is_array( $survey->question_blocks ) ) {
        $question_blocks = $survey->question_blocks;
      } else {
        $decoded = json_decode( (string) $survey->question_blocks, true );
        if ( is_array( $decoded ) ) {
          $question_blocks = $decoded;
        }
      }
    }
    $filename = isset( $_POST['export_filename'] ) ? sanitize_file_name( wp_unslash( $_POST['export_filename'] ) ) : 'details-qcm-enquete-froid';
    if ( '' === $filename ) { $filename = 'details-qcm-enquete-froid'; }
    $type = isset( $_POST['export_type'] ) ? sanitize_key( wp_unslash( $_POST['export_type'] ) ) : 'excel';
    $rows = array();
    $rows[] = array( 'Ordre', 'Question', 'Type', 'Explication', 'Apprenant', 'Formation', 'Enquête' );
    $index = 1;
    foreach ( (array) $question_blocks as $question ) {
      $rows[] = array(
        (string) $index,
        isset( $question['question'] ) ? (string) $question['question'] : '',
        isset( $question['block_type'] ) ? (string) $question['block_type'] : '',
        isset( $question['explanation'] ) ? (string) $question['explanation'] : '',
        (string) $context['learner_name'],
        (string) $context['formation_title'],
        (string) $context['survey_title'],
      );
      $index++;
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


  public function handle_save_mid_survey() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    check_admin_referer( 'acdc_save_mid_survey' );
    $survey_id = isset( $_POST['survey_id'] ) ? absint( wp_unslash( $_POST['survey_id'] ) ) : 0;
    $is_admin_page = is_admin() && isset( $_POST['page'] ) && 'acdc-of-mid-surveys' === $_POST['page'];
    $input = isset( $_POST['survey'] ) && is_array( $_POST['survey'] ) ? wp_unslash( $_POST['survey'] ) : array();
    $title = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
    if ( '' === $title ) {
      $msg = 'Veuillez renseigner l’intitulé.';
      if ( $is_admin_page ) { wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-mid-surveys&action=' . ( $survey_id ? 'edit&item_id=' . $survey_id : 'new' ) . '&notice=' . rawurlencode( $msg ) . '&notice_type=error' ) ); exit; }
      $this->redirect_to_portal( 'mid_surveys', $msg, 'error', array( 'action' => $survey_id ? 'edit' : 'new', 'item_id' => $survey_id ) );
    }
    $existing = $survey_id ? $this->get_mid_survey( $survey_id ) : null;
    $blocks_in = isset( $_POST['blocks'] ) && is_array( $_POST['blocks'] ) ? wp_unslash( $_POST['blocks'] ) : array();
    $blocks = array();
    foreach ( $blocks_in as $block ) {
      $sanitized = $this->sanitize_survey_block_full( $block );
      if ( $sanitized ) {
        $blocks[] = $sanitized;
      }
    }
    $record = array(
      'id' => $survey_id ? $survey_id : $this->get_next_mid_survey_id(),
      'title' => $title,
      'description_text' => isset( $input['description_text'] ) ? sanitize_textarea_field( $input['description_text'] ) : '',
      'question_blocks' => $blocks,
      'alert_notation' => isset( $input['alert_notation'] ) ? sanitize_text_field( $input['alert_notation'] ) : '',
      'survey_settings' => $this->sanitize_survey_settings_input( isset( $input['survey_settings'] ) ? $input['survey_settings'] : array(), 'mid' ),
      'is_model' => $existing ? (int) $existing->is_model : 0,
      'created_at' => $existing ? ( $existing->created_at ?? current_time( 'mysql' ) ) : current_time( 'mysql' ),
      'updated_at' => current_time( 'mysql' ),
    );
    $this->save_mid_survey_record( $record );
    $message = $existing ? 'Enquête intermédiaire mise à jour.' : 'Enquête intermédiaire créée.';
    $target = $is_admin_page ? admin_url( 'admin.php?page=acdc-of-mid-surveys&notice=' . rawurlencode( $message ) . '&notice_type=success' ) : $this->portal_page_url( array( 'tab' => 'mid_surveys', 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ) );
    if ( ! empty( $_POST['save_and_add'] ) ) {
      $target = $is_admin_page ? admin_url( 'admin.php?page=acdc-of-mid-surveys&action=new&notice=' . rawurlencode( $message ) . '&notice_type=success' ) : $this->portal_page_url( array( 'tab' => 'mid_surveys', 'action' => 'new', 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ) );
    }
    wp_safe_redirect( $target ); exit;
  }


  public function handle_delete_mid_survey() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    $survey_id = isset( $_GET['survey_id'] ) ? absint( wp_unslash( $_GET['survey_id'] ) ) : 0;
    if ( ! $survey_id ) { $this->redirect_to_portal( 'mid_surveys', 'Enquête introuvable.', 'error' ); }
    check_admin_referer( 'acdc_delete_mid_survey_' . $survey_id );
    $this->delete_mid_survey_record( $survey_id );
    if ( is_admin() && isset( $_GET['page'] ) && 'acdc-of-mid-surveys' === $_GET['page'] ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-mid-surveys&notice=' . rawurlencode( 'Enquête intermédiaire supprimée.' ) . '&notice_type=success' ) ); exit;
    }
    $this->redirect_to_portal( 'mid_surveys', 'Enquête intermédiaire supprimée.', 'success' );
  }


  public function handle_create_mid_survey_from_model() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_create_mid_survey_from_model' );
    $this->maybe_seed_default_mid_surveys();
    $surveys = $this->get_mid_surveys( '', true );
    $model = null;
    foreach ( $surveys as $survey ) {
      if ( ! empty( $survey['is_model'] ) ) {
        $model = (object) $survey;
        break;
      }
    }
    if ( ! $model ) {
      if ( is_admin() && isset( $_POST['page'] ) && 'acdc-of-mid-surveys' === $_POST['page'] ) {
        wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-mid-surveys&notice=' . rawurlencode( 'Aucun modèle disponible.' ) . '&notice_type=error' ) ); exit;
      }
      $this->redirect_to_portal( 'mid_surveys', 'Aucun modèle disponible.', 'error' );
    }
    $record = array(
      'id' => $this->get_next_mid_survey_id(),
      'title' => str_replace( ' (modèle)', '', (string) $model->title ),
      'description_text' => (string) $model->description_text,
      'question_blocks' => is_array( $model->question_blocks ) ? $model->question_blocks : $this->get_default_mid_survey_model_blocks(),
      'alert_notation' => (string) $model->alert_notation,
      'survey_settings' => $this->get_normalized_survey_settings( $model, 'mid' ),
      'is_model' => 0,
      'created_at' => current_time( 'mysql' ),
      'updated_at' => current_time( 'mysql' ),
    );
    $this->save_mid_survey_record( $record );
    $message = 'Enquête intermédiaire créée depuis le modèle.';
    if ( is_admin() && isset( $_POST['page'] ) && 'acdc-of-mid-surveys' === $_POST['page'] ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-mid-surveys&action=edit&item_id=' . (int) $record['id'] . '&notice=' . rawurlencode( $message ) . '&notice_type=success' ) ); exit;
    }
    $this->redirect_to_portal( 'mid_surveys', $message, 'success', array( 'action' => 'edit', 'item_id' => (int) $record['id'] ) );
  }



  public function handle_save_hot_survey() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    check_admin_referer( 'acdc_save_hot_survey' );
    $survey_id = isset( $_POST['hot_survey_id'] ) ? absint( wp_unslash( $_POST['hot_survey_id'] ) ) : 0;
    $is_admin_page = is_admin() && isset( $_POST['page'] ) && 'acdc-of-hot-surveys' === $_POST['page'];
    $input = isset( $_POST['survey'] ) && is_array( $_POST['survey'] ) ? wp_unslash( $_POST['survey'] ) : array();
    $title = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
    if ( '' === $title ) {
      $msg = 'Veuillez renseigner l’intitulé.';
      if ( $is_admin_page ) { wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-hot-surveys&action=' . ( $survey_id ? 'edit&item_id=' . $survey_id : 'new' ) . '&notice=' . rawurlencode( $msg ) . '&notice_type=error' ) ); exit; }
      $this->redirect_to_portal( 'hot_surveys', $msg, 'error', array( 'action' => $survey_id ? 'edit' : 'new', 'item_id' => $survey_id ) );
    }
    $existing = $survey_id ? $this->get_hot_survey( $survey_id ) : null;
    $blocks_in = isset( $_POST['blocks'] ) && is_array( $_POST['blocks'] ) ? wp_unslash( $_POST['blocks'] ) : array();
    $blocks = array();
    foreach ( $blocks_in as $block ) {
      $sanitized = $this->sanitize_survey_block_full( $block );
      if ( $sanitized ) {
        $blocks[] = $sanitized;
      }
    }
    $record = array(
      'id' => $survey_id ? $survey_id : $this->get_next_hot_survey_id(),
      'title' => $title,
      'description_text' => isset( $input['description_text'] ) ? sanitize_textarea_field( $input['description_text'] ) : '',
      'question_blocks' => $blocks,
      'alert_notation' => isset( $input['alert_notation'] ) ? sanitize_text_field( $input['alert_notation'] ) : '',
      'survey_settings' => $this->sanitize_survey_settings_input( isset( $input['survey_settings'] ) ? $input['survey_settings'] : array(), 'hot' ),
      'is_model' => $existing ? (int) $existing->is_model : 0,
      'created_at' => $existing ? ( $existing->created_at ?? current_time( 'mysql' ) ) : current_time( 'mysql' ),
      'updated_at' => current_time( 'mysql' ),
    );
    $this->save_hot_survey_record( $record );
    $message = $existing ? 'Enquête à chaud mise à jour.' : 'Enquête à chaud créée.';
    $target = $is_admin_page ? admin_url( 'admin.php?page=acdc-of-hot-surveys&notice=' . rawurlencode( $message ) . '&notice_type=success' ) : $this->portal_page_url( array( 'tab' => 'hot_surveys', 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ) );
    if ( ! empty( $_POST['save_and_add'] ) ) {
      $target = $is_admin_page ? admin_url( 'admin.php?page=acdc-of-hot-surveys&action=new&notice=' . rawurlencode( $message ) . '&notice_type=success' ) : $this->portal_page_url( array( 'tab' => 'hot_surveys', 'action' => 'new', 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ) );
    }
    wp_safe_redirect( $target ); exit;
  }


  public function handle_delete_hot_survey() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    $survey_id = isset( $_GET['survey_id'] ) ? absint( wp_unslash( $_GET['survey_id'] ) ) : 0;
    if ( ! $survey_id ) { $this->redirect_to_portal( 'hot_surveys', 'Enquête introuvable.', 'error' ); }
    check_admin_referer( 'acdc_delete_hot_survey_' . $survey_id );
    $this->delete_hot_survey_record( $survey_id );
    if ( is_admin() && isset( $_GET['page'] ) && 'acdc-of-hot-surveys' === $_GET['page'] ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-hot-surveys&notice=' . rawurlencode( 'Enquête à chaud supprimée.' ) . '&notice_type=success' ) ); exit;
    }
    $this->redirect_to_portal( 'hot_surveys', 'Enquête à chaud supprimée.', 'success' );
  }


  public function handle_create_hot_survey_from_model() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_create_hot_survey_from_model' );
    $this->maybe_seed_default_hot_surveys();
    $surveys = $this->get_hot_surveys( '', true );
    $model = null;
    foreach ( $surveys as $survey ) {
      if ( ! empty( $survey['is_model'] ) ) {
        $model = (object) $survey;
        break;
      }
    }
    if ( ! $model ) {
      if ( is_admin() && isset( $_POST['page'] ) && 'acdc-of-hot-surveys' === $_POST['page'] ) {
        wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-hot-surveys&notice=' . rawurlencode( 'Aucun modèle disponible.' ) . '&notice_type=error' ) ); exit;
      }
      $this->redirect_to_portal( 'hot_surveys', 'Aucun modèle disponible.', 'error' );
    }
    $record = array(
      'id' => $this->get_next_hot_survey_id(),
      'title' => str_replace( ' (modèle)', '', (string) $model->title ),
      'description_text' => (string) $model->description_text,
      'question_blocks' => is_array( $model->question_blocks ) ? $model->question_blocks : $this->get_default_hot_survey_model_blocks(),
      'alert_notation' => (string) $model->alert_notation,
      'survey_settings' => $this->get_normalized_survey_settings( $model, 'hot' ),
      'is_model' => 0,
      'created_at' => current_time( 'mysql' ),
      'updated_at' => current_time( 'mysql' ),
    );
    $this->save_hot_survey_record( $record );
    $message = 'Enquête à chaud créée depuis le modèle.';
    if ( is_admin() && isset( $_POST['page'] ) && 'acdc-of-hot-surveys' === $_POST['page'] ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-hot-surveys&action=edit&item_id=' . (int) $record['id'] . '&notice=' . rawurlencode( $message ) . '&notice_type=success' ) ); exit;
    }
    $this->redirect_to_portal( 'hot_surveys', $message, 'success', array( 'action' => 'edit', 'item_id' => (int) $record['id'] ) );
  }



  public function handle_save_cold_survey() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_save_cold_survey' );
    $survey_id = isset( $_POST['cold_survey_id'] ) ? absint( wp_unslash( $_POST['cold_survey_id'] ) ) : 0;
    $is_admin_page = is_admin() && isset( $_POST['page'] ) && 'acdc-of-cold-surveys' === $_POST['page'];
    $input = isset( $_POST['survey'] ) && is_array( $_POST['survey'] ) ? wp_unslash( $_POST['survey'] ) : array();
    $title = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
    if ( '' === $title ) {
      $msg = 'Veuillez renseigner l’intitulé.';
      if ( $is_admin_page ) { wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-cold-surveys&action=' . ( $survey_id ? 'edit&item_id=' . $survey_id : 'new' ) . '&notice=' . rawurlencode( $msg ) . '&notice_type=error' ) ); exit; }
      $this->redirect_to_portal( 'cold_surveys', $msg, 'error', array( 'action' => $survey_id ? 'edit' : 'new', 'item_id' => $survey_id ) );
    }
    $existing = $survey_id ? $this->get_cold_survey( $survey_id ) : null;
    $blocks_in = isset( $_POST['blocks'] ) && is_array( $_POST['blocks'] ) ? wp_unslash( $_POST['blocks'] ) : array();
    $blocks = array();
    foreach ( $blocks_in as $block ) {
      $sanitized = $this->sanitize_survey_block_full( $block );
      if ( $sanitized ) {
        $blocks[] = $sanitized;
      }
    }
    $record = array(
      'id' => $survey_id ? $survey_id : $this->get_next_cold_survey_id(),
      'title' => $title,
      'description_text' => isset( $input['description_text'] ) ? sanitize_textarea_field( $input['description_text'] ) : '',
      'question_blocks' => $blocks,
      'alert_notation' => isset( $input['alert_notation'] ) ? sanitize_text_field( $input['alert_notation'] ) : '',
      'survey_settings' => $this->sanitize_survey_settings_input( isset( $input['survey_settings'] ) ? $input['survey_settings'] : array(), 'cold' ),
      'is_model' => $existing ? (int) $existing->is_model : 0,
      'created_at' => $existing ? ( $existing->created_at ?? current_time( 'mysql' ) ) : current_time( 'mysql' ),
      'updated_at' => current_time( 'mysql' ),
    );
    $this->save_cold_survey_record( $record );
    $message = $existing ? 'Enquête à froid mise à jour.' : 'Enquête à froid créée.';
    $target = $is_admin_page ? admin_url( 'admin.php?page=acdc-of-cold-surveys&notice=' . rawurlencode( $message ) . '&notice_type=success' ) : $this->portal_page_url( array( 'tab' => 'cold_surveys', 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ) );
    if ( ! empty( $_POST['save_and_add'] ) ) {
      $target = $is_admin_page ? admin_url( 'admin.php?page=acdc-of-cold-surveys&action=new&notice=' . rawurlencode( $message ) . '&notice_type=success' ) : $this->portal_page_url( array( 'tab' => 'cold_surveys', 'action' => 'new', 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ) );
    }
    wp_safe_redirect( $target ); exit;
  }


  public function handle_delete_cold_survey() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    $survey_id = isset( $_GET['survey_id'] ) ? absint( wp_unslash( $_GET['survey_id'] ) ) : 0;
    if ( ! $survey_id ) { $this->redirect_to_portal( 'cold_surveys', 'Enquête introuvable.', 'error' ); }
    check_admin_referer( 'acdc_delete_cold_survey_' . $survey_id );
    $this->delete_cold_survey_record( $survey_id );
    if ( is_admin() && isset( $_GET['page'] ) && 'acdc-of-cold-surveys' === $_GET['page'] ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-cold-surveys&notice=' . rawurlencode( 'Enquête à froid supprimée.' ) . '&notice_type=success' ) ); exit;
    }
    $this->redirect_to_portal( 'cold_surveys', 'Enquête à froid supprimée.', 'success' );
  }


  public function handle_create_cold_survey_from_model() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_create_cold_survey_from_model' );
    $this->maybe_seed_default_cold_surveys();
    $surveys = $this->get_cold_surveys( '', true );
    $model = null;
    foreach ( $surveys as $survey ) {
      if ( ! empty( $survey['is_model'] ) ) { $model = (object) $survey; break; }
    }
    if ( ! $model ) {
      if ( is_admin() && isset( $_POST['page'] ) && 'acdc-of-cold-surveys' === $_POST['page'] ) {
        wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-cold-surveys&notice=' . rawurlencode( 'Aucun modèle disponible.' ) . '&notice_type=error' ) ); exit;
      }
      $this->redirect_to_portal( 'cold_surveys', 'Aucun modèle disponible.', 'error' );
    }
    $record = array(
      'id' => $this->get_next_cold_survey_id(),
      'title' => str_replace( ' (modèle)', '', (string) $model->title ),
      'description_text' => (string) $model->description_text,
      'question_blocks' => is_array( $model->question_blocks ) ? $model->question_blocks : $this->get_default_cold_survey_model_blocks(),
      'alert_notation' => (string) $model->alert_notation,
      'survey_settings' => $this->get_normalized_survey_settings( $model, 'cold' ),
      'is_model' => 0,
      'created_at' => current_time( 'mysql' ),
      'updated_at' => current_time( 'mysql' ),
    );
    $this->save_cold_survey_record( $record );
    $message = 'Enquête à froid créée depuis le modèle.';
    if ( is_admin() && isset( $_POST['page'] ) && 'acdc-of-cold-surveys' === $_POST['page'] ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-cold-surveys&action=edit&item_id=' . (int) $record['id'] . '&notice=' . rawurlencode( $message ) . '&notice_type=success' ) ); exit;
    }
    $this->redirect_to_portal( 'cold_surveys', $message, 'success', array( 'action' => 'edit', 'item_id' => (int) $record['id'] ) );
  }




  public function handle_save_trainer_survey() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_save_trainer_survey' );
    $survey_id = isset( $_POST['trainer_survey_id'] ) ? absint( wp_unslash( $_POST['trainer_survey_id'] ) ) : 0;
    $is_admin_page = is_admin() && isset( $_POST['page'] ) && 'acdc-of-trainer-surveys' === $_POST['page'];
    $input = isset( $_POST['survey'] ) && is_array( $_POST['survey'] ) ? wp_unslash( $_POST['survey'] ) : array();
    $scope = isset( $input['scope'] ) && 'annual' === sanitize_key( $input['scope'] ) ? 'annual' : 'action';
    $title = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
    if ( '' === $title ) {
      $msg = 'Veuillez renseigner l’intitulé.';
      if ( $is_admin_page ) { wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-trainer-surveys&scope=' . $scope . '&action=' . ( $survey_id ? 'edit&item_id=' . $survey_id : 'new' ) . '&notice=' . rawurlencode( $msg ) . '&notice_type=error' ) ); exit; }
      $this->redirect_to_portal( 'trainer_surveys', $msg, 'error', array( 'scope' => $scope, 'action' => $survey_id ? 'edit' : 'new', 'item_id' => $survey_id ) );
    }
    $existing = $survey_id ? $this->get_trainer_survey( $survey_id ) : null;
    $blocks_in = isset( $_POST['blocks'] ) && is_array( $_POST['blocks'] ) ? wp_unslash( $_POST['blocks'] ) : array();
    $blocks = array();
    foreach ( $blocks_in as $block ) {
      $sanitized = $this->sanitize_survey_block_full( $block );
      if ( $sanitized ) {
        $blocks[] = $sanitized;
      }
    }
    $record = array(
      'id' => $survey_id ? $survey_id : $this->get_next_trainer_survey_id(),
      'scope' => $scope,
      'title' => $title,
      'description_text' => isset( $input['description_text'] ) ? sanitize_textarea_field( $input['description_text'] ) : '',
      'question_blocks' => $blocks,
      'alert_notation' => isset( $input['alert_notation'] ) ? sanitize_text_field( $input['alert_notation'] ) : '',
      'survey_settings' => $this->sanitize_survey_settings_input( isset( $input['survey_settings'] ) ? $input['survey_settings'] : array(), 'trainer', $scope ),
      'is_model' => $existing ? (int) $existing->is_model : 0,
      'created_at' => $existing ? ( $existing->created_at ?? current_time( 'mysql' ) ) : current_time( 'mysql' ),
      'updated_at' => current_time( 'mysql' ),
    );
    $this->save_trainer_survey_record( $record );
    $message = $existing ? ( 'annual' === $scope ? 'Enquête formateur annuelle mise à jour.' : 'Enquête formateur mise à jour.' ) : ( 'annual' === $scope ? 'Enquête formateur annuelle créée.' : 'Enquête formateur créée.' );
    $target = $is_admin_page ? admin_url( 'admin.php?page=acdc-of-trainer-surveys&scope=' . $scope . '&notice=' . rawurlencode( $message ) . '&notice_type=success' ) : $this->portal_page_url( array( 'tab' => 'trainer_surveys', 'scope' => $scope, 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ) );
    if ( ! empty( $_POST['save_and_add'] ) ) {
      $target = $is_admin_page ? admin_url( 'admin.php?page=acdc-of-trainer-surveys&scope=' . $scope . '&action=new&notice=' . rawurlencode( $message ) . '&notice_type=success' ) : $this->portal_page_url( array( 'tab' => 'trainer_surveys', 'scope' => $scope, 'action' => 'new', 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ) );
    }
    wp_safe_redirect( $target ); exit;
  }


  public function handle_delete_trainer_survey() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    $survey_id = isset( $_GET['survey_id'] ) ? absint( wp_unslash( $_GET['survey_id'] ) ) : 0;
    $scope = isset( $_GET['scope'] ) && 'annual' === sanitize_key( wp_unslash( $_GET['scope'] ) ) ? 'annual' : 'action';
    if ( ! $survey_id ) { $this->redirect_to_portal( 'trainer_surveys', 'Enquête introuvable.', 'error', array( 'scope' => $scope ) ); }
    check_admin_referer( 'acdc_delete_trainer_survey_' . $survey_id );
    $this->delete_trainer_survey_record( $survey_id );
    if ( is_admin() && isset( $_GET['page'] ) && 'acdc-of-trainer-surveys' === $_GET['page'] ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-trainer-surveys&scope=' . $scope . '&notice=' . rawurlencode( 'annual' === $scope ? 'Enquête formateur annuelle supprimée.' : 'Enquête formateur supprimée.' ) . '&notice_type=success' ) ); exit;
    }
    $this->redirect_to_portal( 'trainer_surveys', 'annual' === $scope ? 'Enquête formateur annuelle supprimée.' : 'Enquête formateur supprimée.', 'success', array( 'scope' => $scope ) );
  }


  public function handle_create_trainer_survey_from_model() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_create_trainer_survey_from_model' );
    $scope = isset( $_POST['scope'] ) && 'annual' === sanitize_key( wp_unslash( $_POST['scope'] ) ) ? 'annual' : 'action';
    $this->maybe_seed_default_trainer_surveys();
    $surveys = $this->get_trainer_surveys( $scope, '', true );
    $model = null;
    foreach ( $surveys as $survey ) {
      if ( ! empty( $survey['is_model'] ) ) { $model = (object) $survey; break; }
    }
    if ( ! $model ) {
      if ( is_admin() && isset( $_POST['page'] ) && 'acdc-of-trainer-surveys' === $_POST['page'] ) {
        wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-trainer-surveys&scope=' . $scope . '&notice=' . rawurlencode( 'Aucun modèle disponible.' ) . '&notice_type=error' ) ); exit;
      }
      $this->redirect_to_portal( 'trainer_surveys', 'Aucun modèle disponible.', 'error', array( 'scope' => $scope ) );
    }
    $record = array(
      'id' => $this->get_next_trainer_survey_id(),
      'scope' => $scope,
      'title' => 'annual' === $scope ? 'Enquête formateur annuelle' : 'Enquête formateur',
      'description_text' => (string) $model->description_text,
      'question_blocks' => is_array( $model->question_blocks ) ? $model->question_blocks : $this->get_default_trainer_survey_model_blocks( $scope ),
      'alert_notation' => (string) $model->alert_notation,
      'is_model' => 0,
      'created_at' => current_time( 'mysql' ),
      'updated_at' => current_time( 'mysql' ),
    );
    $this->save_trainer_survey_record( $record );
    $message = 'annual' === $scope ? 'Enquête formateur annuelle créée depuis le modèle.' : 'Enquête formateur créée depuis le modèle.';
    if ( is_admin() && isset( $_POST['page'] ) && 'acdc-of-trainer-surveys' === $_POST['page'] ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-trainer-surveys&scope=' . $scope . '&action=edit&item_id=' . (int) $record['id'] . '&notice=' . rawurlencode( $message ) . '&notice_type=success#acdc-trainer-survey-form' ) ); exit;
    }
    $this->redirect_to_portal( 'trainer_surveys', $message, 'success', array( 'scope' => $scope, 'action' => 'edit', 'item_id' => (int) $record['id'] ) );
  }




  public function handle_save_company_survey() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    check_admin_referer( 'acdc_save_company_survey' );
    $survey_id = isset( $_POST['company_survey_id'] ) ? absint( wp_unslash( $_POST['company_survey_id'] ) ) : 0;
    $is_admin_page = is_admin() && isset( $_POST['page'] ) && 'acdc-of-company-surveys' === $_POST['page'];
    $input = isset( $_POST['survey'] ) && is_array( $_POST['survey'] ) ? wp_unslash( $_POST['survey'] ) : array();
    $title = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
    if ( '' === $title ) {
      $msg = 'Veuillez renseigner l’intitulé.';
      if ( $is_admin_page ) { wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-company-surveys&action=' . ( $survey_id ? 'edit&item_id=' . $survey_id : 'new' ) . '&notice=' . rawurlencode( $msg ) . '&notice_type=error' ) ); exit; }
      $this->redirect_to_portal( 'company_surveys', $msg, 'error', array( 'action' => $survey_id ? 'edit' : 'new', 'item_id' => $survey_id ) );
    }
    $existing = $survey_id ? $this->get_company_survey( $survey_id ) : null;
    $blocks_in = isset( $_POST['blocks'] ) && is_array( $_POST['blocks'] ) ? wp_unslash( $_POST['blocks'] ) : array();
    $blocks = array();
    foreach ( $blocks_in as $block ) {
      $sanitized = $this->sanitize_survey_block_full( $block );
      if ( $sanitized ) {
        $blocks[] = $sanitized;
      }
    }
    $record = array(
      'id' => $survey_id ? $survey_id : $this->get_next_company_survey_id(),
      'title' => $title,
      'description_text' => isset( $input['description_text'] ) ? sanitize_textarea_field( $input['description_text'] ) : '',
      'question_blocks' => $blocks,
      'alert_notation' => isset( $input['alert_notation'] ) ? sanitize_text_field( $input['alert_notation'] ) : '',
      'survey_settings' => $this->sanitize_survey_settings_input( isset( $input['survey_settings'] ) ? $input['survey_settings'] : array(), 'company' ),
      'is_model' => $existing ? (int) $existing->is_model : 0,
      'created_at' => $existing ? ( $existing->created_at ?? current_time( 'mysql' ) ) : current_time( 'mysql' ),
      'updated_at' => current_time( 'mysql' ),
    );
    $this->save_company_survey_record( $record );
    $message = $existing ? 'Enquête entreprise mise à jour.' : 'Enquête entreprise créée.';
    $target = $is_admin_page ? admin_url( 'admin.php?page=acdc-of-company-surveys&notice=' . rawurlencode( $message ) . '&notice_type=success' ) : $this->portal_page_url( array( 'tab' => 'company_surveys', 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ) );
    if ( ! empty( $_POST['save_and_add'] ) ) {
      $target = $is_admin_page ? admin_url( 'admin.php?page=acdc-of-company-surveys&action=new&notice=' . rawurlencode( $message ) . '&notice_type=success' ) : $this->portal_page_url( array( 'tab' => 'company_surveys', 'action' => 'new', 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ) );
    }
    wp_safe_redirect( $target ); exit;
  }


  public function handle_delete_company_survey() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    $survey_id = isset( $_GET['survey_id'] ) ? absint( wp_unslash( $_GET['survey_id'] ) ) : 0;
    if ( ! $survey_id ) { $this->redirect_to_portal( 'company_surveys', 'Enquête introuvable.', 'error' ); }
    check_admin_referer( 'acdc_delete_company_survey_' . $survey_id );
    $this->delete_company_survey_record( $survey_id );
    if ( is_admin() && isset( $_GET['page'] ) && 'acdc-of-company-surveys' === $_GET['page'] ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-company-surveys&notice=' . rawurlencode( 'Enquête entreprise supprimée.' ) . '&notice_type=success' ) ); exit;
    }
    $this->redirect_to_portal( 'company_surveys', 'Enquête entreprise supprimée.', 'success' );
  }


  public function handle_create_company_survey_from_model() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_create_company_survey_from_model' );
    $this->maybe_seed_default_company_surveys();
    $surveys = $this->get_company_surveys( '', true );
    $model = null;
    foreach ( $surveys as $survey ) {
      if ( ! empty( $survey['is_model'] ) ) {
        $model = (object) $survey;
        break;
      }
    }
    if ( ! $model ) {
      if ( is_admin() && isset( $_POST['page'] ) && 'acdc-of-company-surveys' === $_POST['page'] ) {
        wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-company-surveys&notice=' . rawurlencode( 'Aucun modèle disponible.' ) . '&notice_type=error' ) ); exit;
      }
      $this->redirect_to_portal( 'company_surveys', 'Aucun modèle disponible.', 'error' );
    }
    $record = array(
      'id' => $this->get_next_company_survey_id(),
      'title' => trim( str_replace( ' (modèle)', '', (string) $model->title ) ),
      'description_text' => (string) $model->description_text,
      'question_blocks' => is_array( $model->question_blocks ) ? $model->question_blocks : $this->get_default_company_survey_model_blocks(),
      'alert_notation' => (string) $model->alert_notation,
      'survey_settings' => $this->get_normalized_survey_settings( $model, 'company' ),
      'is_model' => 0,
      'created_at' => current_time( 'mysql' ),
      'updated_at' => current_time( 'mysql' ),
    );
    if ( '' === $record['title'] ) {
      $record['title'] = 'Enquête entreprise';
    }
    $this->save_company_survey_record( $record );
    $message = 'Enquête entreprise créée depuis le modèle.';
    if ( is_admin() && isset( $_POST['page'] ) && 'acdc-of-company-surveys' === $_POST['page'] ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-company-surveys&action=edit&item_id=' . (int) $record['id'] . '&notice=' . rawurlencode( $message ) . '&notice_type=success#acdc-company-survey-form' ) ); exit;
    }
    $this->redirect_to_portal( 'company_surveys', $message, 'success', array( 'action' => 'edit', 'item_id' => (int) $record['id'] ) );
  }

  public function handle_save_funder_survey() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    check_admin_referer( 'acdc_save_funder_survey' );
    $survey_id = isset( $_POST['funder_survey_id'] ) ? absint( wp_unslash( $_POST['funder_survey_id'] ) ) : 0;
    $is_admin_page = is_admin() && isset( $_POST['page'] ) && 'acdc-of-funder-surveys' === $_POST['page'];
    $input = isset( $_POST['survey'] ) && is_array( $_POST['survey'] ) ? wp_unslash( $_POST['survey'] ) : array();
    $scope = isset( $_POST['survey_scope'] ) ? sanitize_key( wp_unslash( $_POST['survey_scope'] ) ) : 'action';
    if ( ! in_array( $scope, array( 'action', 'annual' ), true ) ) {
      $scope = 'action';
    }
    $title = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
    if ( '' === $title ) {
      $msg = 'Veuillez renseigner l’intitulé.';
      if ( $is_admin_page ) { wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-funder-surveys&scope=' . $scope . '&action=' . ( $survey_id ? 'edit&item_id=' . $survey_id : 'new' ) . '&notice=' . rawurlencode( $msg ) . '&notice_type=error' ) ); exit; }
      $this->redirect_to_portal( 'funder_surveys', $msg, 'error', array( 'scope' => $scope, 'action' => $survey_id ? 'edit' : 'new', 'item_id' => $survey_id ) );
    }
    $existing = $survey_id ? $this->get_funder_survey( $survey_id ) : null;
    if ( $existing && isset( $existing->scope ) ) {
      $scope = sanitize_key( (string) $existing->scope );
    }
    $blocks_in = isset( $_POST['blocks'] ) && is_array( $_POST['blocks'] ) ? wp_unslash( $_POST['blocks'] ) : array();
    $blocks = array();
    foreach ( $blocks_in as $block ) {
      $sanitized = $this->sanitize_survey_block_full( $block );
      if ( $sanitized ) {
        $blocks[] = $sanitized;
      }
    }
    $record = array(
      'id' => $survey_id ? $survey_id : $this->get_next_funder_survey_id(),
      'scope' => $scope,
      'title' => $title,
      'description_text' => isset( $input['description_text'] ) ? sanitize_textarea_field( $input['description_text'] ) : '',
      'question_blocks' => $blocks,
      'alert_notation' => isset( $input['alert_notation'] ) ? sanitize_text_field( $input['alert_notation'] ) : '',
      'survey_settings' => $this->sanitize_survey_settings_input( isset( $input['survey_settings'] ) ? $input['survey_settings'] : array(), 'funder', $scope ),
      'is_model' => $existing ? (int) $existing->is_model : 0,
      'created_at' => $existing ? ( $existing->created_at ?? current_time( 'mysql' ) ) : current_time( 'mysql' ),
      'updated_at' => current_time( 'mysql' ),
    );
    $this->save_funder_survey_record( $record );
    $message = $existing ? ( 'annual' === $scope ? 'Enquête financeur annuelle mise à jour.' : 'Enquête financeur mise à jour.' ) : ( 'annual' === $scope ? 'Enquête financeur annuelle créée.' : 'Enquête financeur créée.' );
    $target = $is_admin_page ? admin_url( 'admin.php?page=acdc-of-funder-surveys&scope=' . $scope . '&notice=' . rawurlencode( $message ) . '&notice_type=success' ) : $this->portal_page_url( array( 'tab' => 'funder_surveys', 'scope' => $scope, 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ) );
    if ( ! empty( $_POST['save_and_add'] ) ) {
      $target = $is_admin_page ? admin_url( 'admin.php?page=acdc-of-funder-surveys&scope=' . $scope . '&action=new&notice=' . rawurlencode( $message ) . '&notice_type=success' ) : $this->portal_page_url( array( 'tab' => 'funder_surveys', 'scope' => $scope, 'action' => 'new', 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ) );
    }
    wp_safe_redirect( $target ); exit;
  }


  public function handle_delete_funder_survey() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    $survey_id = isset( $_GET['survey_id'] ) ? absint( wp_unslash( $_GET['survey_id'] ) ) : 0;
    $scope = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : 'action';
    if ( ! in_array( $scope, array( 'action', 'annual' ), true ) ) {
      $scope = 'action';
    }
    if ( ! $survey_id ) { $this->redirect_to_portal( 'funder_surveys', 'Enquête introuvable.', 'error', array( 'scope' => $scope ) ); }
    $survey = $this->get_funder_survey( $survey_id );
    if ( $survey && ! empty( $survey->scope ) ) {
      $scope = sanitize_key( (string) $survey->scope );
    }
    check_admin_referer( 'acdc_delete_funder_survey_' . $survey_id );
    $this->delete_funder_survey_record( $survey_id );
    if ( is_admin() && isset( $_GET['page'] ) && 'acdc-of-funder-surveys' === $_GET['page'] ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-funder-surveys&scope=' . $scope . '&notice=' . rawurlencode( 'annual' === $scope ? 'Enquête financeur annuelle supprimée.' : 'Enquête financeur supprimée.' ) . '&notice_type=success' ) ); exit;
    }
    $this->redirect_to_portal( 'funder_surveys', 'annual' === $scope ? 'Enquête financeur annuelle supprimée.' : 'Enquête financeur supprimée.', 'success', array( 'scope' => $scope ) );
  }


  public function handle_create_funder_survey_from_model() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_create_funder_survey_from_model' );
    $scope = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'action';
    if ( ! in_array( $scope, array( 'action', 'annual' ), true ) ) {
      $scope = 'action';
    }
    $this->maybe_seed_default_funder_surveys();
    $surveys = $this->get_funder_surveys( $scope, '', true );
    $model = null;
    foreach ( $surveys as $survey ) {
      if ( ! empty( $survey['is_model'] ) ) {
        $model = (object) $survey;
        break;
      }
    }
    if ( ! $model ) {
      if ( is_admin() && isset( $_POST['page'] ) && 'acdc-of-funder-surveys' === $_POST['page'] ) {
        wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-funder-surveys&scope=' . $scope . '&notice=' . rawurlencode( 'Aucun modèle disponible.' ) . '&notice_type=error' ) ); exit;
      }
      $this->redirect_to_portal( 'funder_surveys', 'Aucun modèle disponible.', 'error', array( 'scope' => $scope ) );
    }
    $record = array(
      'id' => $this->get_next_funder_survey_id(),
      'scope' => $scope,
      'title' => trim( str_replace( ' (modèle)', '', (string) $model->title ) ),
      'description_text' => (string) $model->description_text,
      'question_blocks' => is_array( $model->question_blocks ) ? $model->question_blocks : $this->get_default_funder_survey_model_blocks( $scope ),
      'alert_notation' => (string) $model->alert_notation,
      'survey_settings' => $this->get_normalized_survey_settings( $model, 'funder', $scope ),
      'is_model' => 0,
      'created_at' => current_time( 'mysql' ),
      'updated_at' => current_time( 'mysql' ),
    );
    if ( '' === $record['title'] ) {
      $record['title'] = 'annual' === $scope ? 'Enquête financeur annuelle' : 'Enquête financeur';
    }
    $this->save_funder_survey_record( $record );
    $message = 'annual' === $scope ? 'Enquête financeur annuelle créée depuis le modèle.' : 'Enquête financeur créée depuis le modèle.';
    if ( is_admin() && isset( $_POST['page'] ) && 'acdc-of-funder-surveys' === $_POST['page'] ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-funder-surveys&scope=' . $scope . '&action=edit&item_id=' . (int) $record['id'] . '&notice=' . rawurlencode( $message ) . '&notice_type=success#acdc-funder-survey-form' ) ); exit;
    }
    $this->redirect_to_portal( 'funder_surveys', $message, 'success', array( 'scope' => $scope, 'action' => 'edit', 'item_id' => (int) $record['id'] ) );
  }


  public function handle_save_questionnaire_session() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_save_questionnaire_session' );
    global $wpdb;
    $session_id = isset( $_POST['questionnaire_session_id'] ) ? absint( wp_unslash( $_POST['questionnaire_session_id'] ) ) : 0;
    $input = isset( $_POST['questionnaire_session'] ) && is_array( $_POST['questionnaire_session'] ) ? wp_unslash( $_POST['questionnaire_session'] ) : array();
    $existing_session = $session_id ? $this->get_questionnaire_session( $session_id ) : null;
    $source_ref = isset( $input['source_ref'] ) ? sanitize_text_field( $input['source_ref'] ) : '';
    $source_type = $existing_session && ! empty( $existing_session->source_type ) ? (string) $existing_session->source_type : '';
    $source_id = $existing_session && ! empty( $existing_session->source_id ) ? (int) $existing_session->source_id : 0;
    if ( strpos( $source_ref, ':' ) !== false ) {
      list( $source_type, $source_id ) = array_pad( explode( ':', $source_ref, 2 ), 2, '' );
      $source_id = absint( $source_id );
    }
    $source = $this->get_questionnaire_source_data( $source_type, $source_id );
    if ( ! $source ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-questionnaire-sessions&notice=' . rawurlencode( 'Questionnaire source introuvable.' ) . '&notice_type=error' ) );
      exit;
    }
    $questionnaire_settings = get_option( 'acdc_of_questionnaire_settings', array() );
    $default_status = ! empty( $questionnaire_settings['new_session_status_default'] ) ? sanitize_key( $questionnaire_settings['new_session_status_default'] ) : 'brouillon';
    $existing_session_settings = $existing_session ? $this->get_questionnaire_session_settings_array( $existing_session ) : array();
    $survey_source_settings = $this->get_survey_settings_from_source_data( $source );
    $has_target_input = isset( $input['target_learner_ids_present'] );
    $selected_learner_ids = isset( $input['target_learner_ids'] ) && is_array( $input['target_learner_ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', $input['target_learner_ids'] ) ) ) ) : array();
    if ( $has_target_input ) {
      $existing_session_settings['target_learner_ids'] = $selected_learner_ids;
    }
    if ( ! empty( $survey_source_settings['reminder_days_array'] ) ) {
      $existing_session_settings['reminder_days'] = $survey_source_settings['reminder_days_array'];
    }
    if ( empty( $existing_session_settings['reminder_days'] ) || ! is_array( $existing_session_settings['reminder_days'] ) ) {
      $existing_session_settings['reminder_days'] = array( 5, 10, 15 );
    }
    if ( ! empty( $survey_source_settings ) ) {
      $existing_session_settings['survey_trigger_mode'] = isset( $survey_source_settings['trigger_mode'] ) ? (string) $survey_source_settings['trigger_mode'] : 'manual_auto';
      $existing_session_settings['survey_trigger_rule'] = isset( $survey_source_settings['trigger_rule'] ) ? (string) $survey_source_settings['trigger_rule'] : 'manual';
      $existing_session_settings['survey_deadline_days'] = isset( $survey_source_settings['deadline_days'] ) ? absint( $survey_source_settings['deadline_days'] ) : 15;
      $existing_session_settings['survey_internal_comment'] = isset( $survey_source_settings['internal_comment'] ) ? (string) $survey_source_settings['internal_comment'] : '';
    }
    $token = $session_id ? '' : $this->generate_questionnaire_session_token();
    $url = $session_id ? '' : $this->build_questionnaire_session_public_url( $token );
    $qr = $session_id ? '' : $this->generate_questionnaire_session_qrcode_url( $url );
    $survey_type = $this->is_survey_questionnaire_source_type( $source_type ) ? $source_type : '';
    $company_id = isset( $input['company_id'] ) ? absint( $input['company_id'] ) : 0;
    $company_contact_id = isset( $input['company_contact_id'] ) ? absint( $input['company_contact_id'] ) : 0;
    $funder_id = isset( $input['funder_id'] ) ? absint( $input['funder_id'] ) : 0;
    $annual_campaign_year = isset( $input['annual_campaign_year'] ) ? absint( $input['annual_campaign_year'] ) : 0;
    $session_date_raw = ! empty( $input['session_date'] ) ? str_replace( 'T', ' ', sanitize_text_field( $input['session_date'] ) ) . ':00' : null;
    $scheduled_at_raw = ! empty( $input['scheduled_at'] ) ? str_replace( 'T', ' ', sanitize_text_field( $input['scheduled_at'] ) ) . ':00' : null;
    $deadline_days = ! empty( $survey_source_settings['deadline_days'] ) ? absint( $survey_source_settings['deadline_days'] ) : 15;
    if ( $deadline_days <= 0 ) {
      $deadline_days = 15;
    }
    $deadline_at = null;
    if ( ! empty( $scheduled_at_raw ) ) {
      $deadline_at = gmdate( 'Y-m-d H:i:s', strtotime( $scheduled_at_raw . ' +' . $deadline_days . ' days' ) );
    } elseif ( ! empty( $session_date_raw ) ) {
      $deadline_at = gmdate( 'Y-m-d H:i:s', strtotime( $session_date_raw . ' +' . $deadline_days . ' days' ) );
    } elseif ( $existing_session && ! empty( $existing_session->deadline_at ) ) {
      $deadline_at = $existing_session->deadline_at;
    }

    $data = array(
      'source_type' => $source_type,
      'source_id' => $source_id,
      'survey_type' => $survey_type ?: null,
      'survey_subtype' => '',
      'survey_model_id' => $survey_type ? $source_id : null,
      'session_title' => isset( $input['session_title'] ) ? sanitize_text_field( $input['session_title'] ) : $source['title'],
      'formation_id' => isset( $input['formation_id'] ) ? absint( $input['formation_id'] ) : 0,
      'registration_id' => null,
      'formateur_id' => isset( $input['formateur_id'] ) ? absint( $input['formateur_id'] ) : 0,
      'company_id' => $company_id ? $company_id : null,
      'company_contact_id' => $company_contact_id ? $company_contact_id : null,
      'funder_id' => $funder_id ? $funder_id : null,
      'funder_contact_id' => null,
      'annual_campaign_year' => $annual_campaign_year ? $annual_campaign_year : null,
      'send_mode' => ! empty( $scheduled_at_raw ) ? 'scheduled' : 'manual',
      'session_date' => $session_date_raw,
      'scheduled_at' => ( 'planifiee' === ( isset( $input['status'] ) ? sanitize_key( $input['status'] ) : $default_status ) && ! empty( $scheduled_at_raw ) ) ? $scheduled_at_raw : null,
      'deadline_at' => $deadline_at,
      'reminder_days_json' => wp_json_encode( $existing_session_settings['reminder_days'] ),
      'status' => isset( $input['status'] ) ? sanitize_key( $input['status'] ) : $default_status,
      'pseudo_required' => isset( $input['pseudo_required'] ) ? ( empty( $input['pseudo_required'] ) ? 0 : 1 ) : ( empty( $questionnaire_settings['pseudo_required_default'] ) ? 0 : 1 ),
      'pseudo_editable' => empty( $input['pseudo_editable'] ) ? 0 : 1,
      'restrict_to_registered_learners' => isset( $input['restrict_to_registered_learners'] ) ? ( empty( $input['restrict_to_registered_learners'] ) ? 0 : 1 ) : ( empty( $questionnaire_settings['restrict_to_registered_default'] ) ? 0 : 1 ),
      'show_final_score' => isset( $input['show_final_score'] ) ? ( empty( $input['show_final_score'] ) ? 0 : 1 ) : ( empty( $questionnaire_settings['show_final_score_default'] ) ? 0 : 1 ),
      'is_survey_session' => $survey_type ? 1 : 0,
      'session_settings_json' => wp_json_encode( $existing_session_settings ),
      'updated_at' => $this->now_mysql(),
    );
    if ( ! $session_id ) {
      $data['public_token'] = $token;
      $data['public_url'] = $url;
      $data['qr_code_url'] = $qr;
      $data['created_at'] = $this->now_mysql();
      $wpdb->insert( $this->questionnaire_session_table, $data );
      $session_id = (int) $wpdb->insert_id;
    } else {
      $wpdb->update( $this->questionnaire_session_table, $data, array( 'id' => $session_id ) );
    }
    $saved_session = $session_id ? $this->get_questionnaire_session( $session_id ) : null;
    $redirect_source_type = isset( $_POST['redirect_source_type'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_source_type'] ) ) : $source_type;
    $redirect_source_id = isset( $_POST['redirect_source_id'] ) ? absint( wp_unslash( $_POST['redirect_source_id'] ) ) : $source_id;
    $redirect_context_args = array();
    if ( '' !== $redirect_source_type ) { $redirect_context_args['source_type'] = $redirect_source_type; }
    if ( $redirect_source_id > 0 ) { $redirect_context_args['source_id'] = $redirect_source_id; }
    /* ACDC 3.25.157 — Le retour doit revenir D'OÙ L'ON VIENT. Ce handler s'exécute
       sous admin-post.php, où is_admin() est toujours vrai : on ne peut donc pas
       déduire le contexte, il faut le lire sur le formulaire. */
    $from_admin = isset( $_POST['acdc_origin'] ) && 'admin' === sanitize_key( wp_unslash( $_POST['acdc_origin'] ) );
    if ( isset( $_POST['save_and_animate'] ) && $session_id > 0 ) {
      $animate_args = array_merge( array( 'action' => 'animate', 'item_id' => $session_id, 'notice' => rawurlencode( 'Session enregistrée. Animation ouverte.' ), 'notice_type' => 'success' ), $redirect_context_args );
      wp_safe_redirect(
        $from_admin
          ? add_query_arg( array_merge( array( 'page' => 'acdc-of-questionnaire-sessions' ), $animate_args ), admin_url( 'admin.php' ) )
          : $this->portal_page_url( array_merge( array( 'tab' => 'questionnaire_sessions' ), $animate_args ) )
      );
      exit;
    }
    if ( isset( $_POST['save_and_open_public'] ) && $saved_session && ! empty( $saved_session->public_url ) ) {
      wp_safe_redirect( $saved_session->public_url );
      exit;
    }
    $redirect_args = array(
      'notice' => rawurlencode( 'Session questionnaire enregistrée.' ),
      'notice_type' => 'success',
    );
    if ( '' !== $redirect_source_type ) { $redirect_args['source_type'] = $redirect_source_type; }
    if ( $redirect_source_id > 0 ) { $redirect_args['source_id'] = $redirect_source_id; }
    wp_safe_redirect(
      $from_admin
        ? add_query_arg( $redirect_args, admin_url( 'admin.php?page=acdc-of-questionnaire-sessions' ) )
        : $this->portal_page_url( array_merge( array( 'tab' => 'questionnaire_sessions' ), $redirect_args ) )
    );
    exit;
  }


  public function handle_send_questionnaire_session_emails() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    $session_id = isset( $_GET['questionnaire_session_id'] ) ? absint( wp_unslash( $_GET['questionnaire_session_id'] ) ) : 0;
    check_admin_referer( 'acdc_send_questionnaire_session_emails_' . $session_id );
    $session = $this->get_questionnaire_session( $session_id );
    if ( ! $session ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-questionnaire-sessions&notice=' . rawurlencode( 'Session introuvable.' ) . '&notice_type=error' ) );
      exit;
    }
    $source = $this->get_questionnaire_source_data( $session->source_type, $session->source_id );
    $targets = $this->get_questionnaire_delivery_targets( $session );
    $result = $this->send_questionnaire_session_emails( $session, $source, $targets );
    $this->update_questionnaire_session_settings( $session->id, array(
      'last_email_sent_at' => $this->now_mysql(),
      'last_email_sent_count' => (int) $result['sent'],
      'last_email_failed_count' => (int) $result['failed'],
      'last_email_target_count' => (int) $result['target'],
    ) );
    $notice_type = $result['sent'] > 0 ? 'success' : 'error';
    $notice = $result['sent'] > 0
      ? sprintf( 'Envoi terminé : %d e-mail(s) envoyé(s), %d en échec.', (int) $result['sent'], (int) $result['failed'] )
      : 'Aucun e-mail n’a pu être envoyé.';
    $redirect_args = array(
      'page' => 'acdc-of-questionnaire-sessions',
      'action' => 'edit',
      'item_id' => $session->id,
      'notice' => rawurlencode( $notice ),
      'notice_type' => $notice_type,
    );
    if ( ! empty( $_GET['source_type'] ) ) { $redirect_args['source_type'] = sanitize_text_field( wp_unslash( $_GET['source_type'] ) ); }
    if ( ! empty( $_GET['source_id'] ) ) { $redirect_args['source_id'] = absint( wp_unslash( $_GET['source_id'] ) ); }
    wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
    exit;
  }


  public function handle_delete_questionnaire_session() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    $session_id = isset( $_GET['questionnaire_session_id'] ) ? absint( wp_unslash( $_GET['questionnaire_session_id'] ) ) : 0;
    check_admin_referer( 'acdc_delete_questionnaire_session_' . $session_id );
    global $wpdb;
    $wpdb->delete( $this->questionnaire_answer_table, array( 'session_id' => $session_id ) );
    $wpdb->delete( $this->questionnaire_participant_table, array( 'session_id' => $session_id ) );
    $wpdb->delete( $this->questionnaire_session_table, array( 'id' => $session_id ) );
    $redirect = admin_url( 'admin.php?page=acdc-of-questionnaire-sessions&notice=' . rawurlencode( 'Session questionnaire supprimée.' ) . '&notice_type=success' ); if ( ! empty( $_GET['source_type'] ) ) { $redirect = add_query_arg( array( 'source_type' => sanitize_text_field( wp_unslash( $_GET['source_type'] ) ) ), $redirect ); } if ( ! empty( $_GET['source_id'] ) ) { $redirect = add_query_arg( array( 'source_id' => absint( wp_unslash( $_GET['source_id'] ) ) ), $redirect ); } wp_safe_redirect( $redirect ); exit;
  }


  public function handle_start_questionnaire_session() { $this->update_questionnaire_session_status_action( 'en_cours', 'acdc_start_questionnaire_session', 0 ); }

  public function handle_pause_questionnaire_session() { $this->update_questionnaire_session_status_action( 'en_pause', 'acdc_pause_questionnaire_session', null ); }

  public function handle_finish_questionnaire_session() { $this->update_questionnaire_session_status_action( 'terminee', 'acdc_finish_questionnaire_session', null, true ); }

  public function handle_next_questionnaire_session_question() { $this->move_questionnaire_session_question_index( 1, 'acdc_next_questionnaire_session_question' ); }

  public function handle_previous_questionnaire_session_question() { $this->move_questionnaire_session_question_index( -1, 'acdc_previous_questionnaire_session_question' ); }


  public function handle_join_questionnaire_session() {
    // ACDC 3.25.113 — Vérification du nonce (déjà émis par le formulaire de join,
    // render-trait:4024) alignée sur handle_submit_questionnaire_answer : protège
    // ce writer public (insert/update participant) contre le CSRF.
    check_admin_referer( 'acdc_front_secure_action' );
    $token = isset( $_POST['session_token'] ) ? sanitize_text_field( wp_unslash( $_POST['session_token'] ) ) : '';
    // Anti-abus : limite la création de participants par IP (seuil large pour absorber
    // une classe entière derrière une IP partagée, tout en bloquant l'automatisation massive).
    $q_join_ip  = sanitize_text_field( wp_unslash( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '' ) );
    $q_join_key = 'acdc_qn_joinrl_' . md5( $q_join_ip );
    $q_join_cnt = (int) get_transient( $q_join_key );
    if ( $q_join_cnt >= 100 ) {
      wp_safe_redirect( add_query_arg( array( 'token' => rawurlencode( $token ), 'q_notice' => rawurlencode( 'Trop de connexions depuis ce réseau. Patientez quelques minutes.' ), 'q_notice_type' => 'error' ), $this->get_questionnaire_public_base_url() ) ); exit;
    }
    set_transient( $q_join_key, $q_join_cnt + 1, 10 * MINUTE_IN_SECONDS );
    $session = $this->get_questionnaire_session_by_token( $token );
    if ( ! $session ) { wp_safe_redirect( add_query_arg( array( 'token' => rawurlencode( $token ), 'q_notice' => rawurlencode( 'Session introuvable.' ), 'q_notice_type' => 'error' ), $this->get_questionnaire_public_base_url() ) ); exit; }
    global $wpdb;
    $allowed_learners = $this->get_questionnaire_targeted_learners_for_questionnaire_session( $session );
    $allowed_ids = array_values( array_unique( array_filter( array_map( 'absint', wp_list_pluck( (array) $allowed_learners, 'id' ) ) ) ) );
    $apprenant_id = isset( $_POST['apprenant_id'] ) ? absint( wp_unslash( $_POST['apprenant_id'] ) ) : 0;
    $pseudo = isset( $_POST['pseudo'] ) ? sanitize_text_field( wp_unslash( $_POST['pseudo'] ) ) : '';
    if ( (int) $session->restrict_to_registered_learners && ! empty( $allowed_ids ) && ( ! $apprenant_id || ! in_array( $apprenant_id, $allowed_ids, true ) ) ) {
      wp_safe_redirect( add_query_arg( array( 'token' => rawurlencode( $token ), 'q_notice' => rawurlencode( 'Vous ne faites pas partie des apprenants ciblés pour cette session.' ), 'q_notice_type' => 'error' ), $this->get_questionnaire_public_base_url() ) ); exit;
    }
    if ( '' === $pseudo ) { $pseudo = 'Participant'; }
    $participant = null;
    if ( $apprenant_id ) {
      $participant = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->questionnaire_participant_table} WHERE session_id = %d AND apprenant_id = %d", $session->id, $apprenant_id ) );
    }
    if ( $participant ) {
      $participant_token = $participant->participant_token;
      $wpdb->update( $this->questionnaire_participant_table, array( 'pseudo' => $pseudo, 'participant_status' => 'connecte', 'joined_at' => $this->now_mysql() ), array( 'id' => $participant->id ) );
    } else {
      $participant_token = $this->generate_questionnaire_participant_token();
      $wpdb->insert( $this->questionnaire_participant_table, array( 'session_id' => $session->id, 'apprenant_id' => $apprenant_id, 'participant_token' => $participant_token, 'pseudo' => $pseudo, 'device_type' => wp_is_mobile() ? 'mobile' : 'desktop', 'participant_status' => 'connecte', 'joined_at' => $this->now_mysql(), 'created_at' => $this->now_mysql() ) );
    }
    wp_safe_redirect( add_query_arg( array( 'token' => rawurlencode( $token ), 'participant' => rawurlencode( $participant_token ) ), $this->get_questionnaire_public_base_url() ) ); exit;
  }


  public function handle_submit_questionnaire_answer() {
    check_admin_referer( 'acdc_front_secure_action' );
    $token = isset( $_POST['session_token'] ) ? sanitize_text_field( wp_unslash( $_POST['session_token'] ) ) : '';
    $participant_token = isset( $_POST['participant_token'] ) ? sanitize_text_field( wp_unslash( $_POST['participant_token'] ) ) : '';
    $session = $this->get_questionnaire_session_by_token( $token );
    $participant = $this->get_questionnaire_participant_by_token( $participant_token );
    if ( ! $session || ! $participant || (int) $participant->session_id !== (int) $session->id ) {
      wp_safe_redirect( add_query_arg( array( 'token' => rawurlencode( $token ), 'q_notice' => rawurlencode( 'Réponse impossible.' ), 'q_notice_type' => 'error' ), $this->get_questionnaire_public_base_url() ) ); exit;
    }
    $source = $this->get_questionnaire_source_data( $session->source_type, $session->source_id );
    $question_index = (int) $session->current_question_index;
    $question = $source && isset( $source['questions'][ $question_index ] ) ? $source['questions'][ $question_index ] : null;
    if ( ! $question ) { wp_safe_redirect( add_query_arg( array( 'token' => rawurlencode( $token ), 'participant' => rawurlencode( $participant_token ) ), $this->get_questionnaire_public_base_url() ) ); exit; }
    global $wpdb;
    if ( $this->get_questionnaire_session_answer_for_participant( $session->id, $participant->id, $question_index ) ) {
      wp_safe_redirect( add_query_arg( array( 'token' => rawurlencode( $token ), 'participant' => rawurlencode( $participant_token ) ), $this->get_questionnaire_public_base_url() ) ); exit;
    }
    $answer_value = '';
    if ( 'Question ouverte' === ( $question['type'] ?? '' ) ) {
      $answer_value = isset( $_POST['answer_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['answer_text'] ) ) : '';
    } elseif ( ! empty( $_POST['answer_values'] ) && is_array( $_POST['answer_values'] ) ) {
      $answer_value = implode( '||', array_map( 'sanitize_text_field', wp_unslash( $_POST['answer_values'] ) ) );
    } else {
      $answer_value = isset( $_POST['answer_value'] ) ? sanitize_text_field( wp_unslash( $_POST['answer_value'] ) ) : '';
    }
    $choices = $this->parse_question_choices( $question );
    $correct_values = array();
    foreach ( $choices as $choice ) { if ( ! empty( $choice['is_correct'] ) ) { $correct_values[] = (string) $choice['value']; } }
    $submitted_values = '' !== $answer_value ? explode( '||', $answer_value ) : array();
    sort( $correct_values ); sort( $submitted_values );
    $is_correct = ! empty( $correct_values ) && $correct_values === $submitted_values;
    $points = $is_correct ? 1 : 0;
    $wpdb->insert( $this->questionnaire_answer_table, array( 'session_id' => $session->id, 'participant_id' => $participant->id, 'question_index' => $question_index, 'answer_value' => $answer_value, 'is_correct' => $is_correct ? 1 : 0, 'points_awarded' => $points, 'answered_at' => $this->now_mysql() ) );
    $wpdb->update( $this->questionnaire_participant_table, array( 'participant_status' => 'en_cours' ), array( 'id' => $participant->id ) );
    wp_safe_redirect( add_query_arg( array( 'token' => rawurlencode( $token ), 'participant' => rawurlencode( $participant_token ), 'answered' => 1 ), $this->get_questionnaire_public_base_url() ) ); exit;
  }


  public function handle_save_survey_engine_settings() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_save_survey_engine_settings' );

    $source_type = isset( $_POST['source_type'] ) ? sanitize_text_field( wp_unslash( $_POST['source_type'] ) ) : '';
    $survey_type = method_exists( $this, 'get_survey_engine_type_key_from_source_type' ) ? $this->get_survey_engine_type_key_from_source_type( $source_type ) : '';
    if ( '' === $survey_type ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-questionnaire-settings&notice=' . rawurlencode( 'Type d’enquête introuvable.' ) . '&notice_type=error' ) );
      exit;
    }

    $input = isset( $_POST['survey_engine'] ) && is_array( $_POST['survey_engine'] ) ? wp_unslash( $_POST['survey_engine'] ) : array();
    $saved = $this->update_survey_engine_type_settings( $survey_type, array(
      'source_type' => $source_type,
      'recipient_type' => isset( $input['recipient_type'] ) ? $input['recipient_type'] : '',
      'mandatory' => empty( $input['mandatory'] ) ? 0 : 1,
      'default_trigger' => isset( $input['default_trigger'] ) ? $input['default_trigger'] : '',
      'trigger_rule' => isset( $input['trigger_rule'] ) ? $input['trigger_rule'] : '',
      'trigger_delay_days' => isset( $input['trigger_delay_days'] ) ? $input['trigger_delay_days'] : 0,
      'deadline_days' => isset( $input['deadline_days'] ) ? $input['deadline_days'] : 15,
      'reminder_days' => isset( $input['reminder_days'] ) ? $input['reminder_days'] : '',
      'subtypes' => array(),
    ) );

    $redirect = admin_url( 'admin.php?page=acdc-of-questionnaire-settings&source_type=' . rawurlencode( $source_type ) );
    $redirect = add_query_arg( array(
      'notice' => rawurlencode( $saved ? 'Paramètres du moteur d’enquête enregistrés.' : 'Enregistrement impossible.' ),
      'notice_type' => $saved ? 'success' : 'error',
      'from_context' => $source_type,
    ), $redirect );
    wp_safe_redirect( $redirect );
    exit;
  }


  public function handle_save_questionnaire_settings() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_save_questionnaire_settings' );
    $settings = isset( $_POST['questionnaire_settings'] ) && is_array( $_POST['questionnaire_settings'] ) ? wp_unslash( $_POST['questionnaire_settings'] ) : array();
    $context  = isset( $_POST['questionnaire_settings_context'] ) ? sanitize_text_field( wp_unslash( $_POST['questionnaire_settings_context'] ) ) : '';
    update_option( 'acdc_of_questionnaire_settings', array( 'show_final_score_default' => empty( $settings['show_final_score_default'] ) ? '0' : '1', 'pseudo_required_default' => empty( $settings['pseudo_required_default'] ) ? '0' : '1', 'restrict_to_registered_default' => empty( $settings['restrict_to_registered_default'] ) ? '0' : '1', 'new_session_status_default' => ( isset( $settings['new_session_status_default'] ) && 'prete' === $settings['new_session_status_default'] ) ? 'prete' : 'brouillon' ), false );
    $redirect = admin_url( 'admin.php?page=acdc-of-questionnaire-settings&notice=' . rawurlencode( 'Paramètres questionnaires enregistrés.' ) . '&notice_type=success' );
    if ( '' !== $context ) {
      $redirect = add_query_arg( array( 'from_context' => $context ), $redirect );
    }
    wp_safe_redirect( $redirect ); exit;
  }


  public function handle_submit_public_questionnaire_session() {
    $token = isset( $_POST['session_token'] ) ? sanitize_text_field( wp_unslash( $_POST['session_token'] ) ) : '';
    $session = $token ? $this->get_questionnaire_session_by_token( $token ) : null;
    if ( $session && $this->is_survey_questionnaire_source_type( (string) $session->source_type ) ) {
      $this->handle_submit_public_survey_response();
      return;
    }
    $this->handle_submit_questionnaire_answer();
  }



  private function handle_submit_public_survey_response() {
    check_admin_referer( 'acdc_front_secure_action' );

    $token = isset( $_POST['session_token'] ) ? sanitize_text_field( wp_unslash( $_POST['session_token'] ) ) : '';
    $participant_token = isset( $_POST['participant_token'] ) ? sanitize_text_field( wp_unslash( $_POST['participant_token'] ) ) : '';
    $session = $this->get_questionnaire_session_by_token( $token );
    $participant = $this->get_questionnaire_participant_by_token( $participant_token );

    if ( ! $session || ! $participant || (int) $participant->session_id !== (int) $session->id || ! $this->is_survey_questionnaire_source_type( (string) $session->source_type ) ) {
      wp_safe_redirect( $this->get_public_survey_redirect_url( $token, $participant_token, 'Réponse impossible.', 'error' ) );
      exit;
    }

    if ( $this->is_questionnaire_session_expired( $session ) || in_array( (string) $session->status, array( 'expiree', 'annulee' ), true ) ) {
      wp_safe_redirect( $this->get_public_survey_redirect_url( $token, $participant_token, 'Le lien de réponse n’est plus disponible.', 'error' ) );
      exit;
    }

    if ( in_array( (string) ( $participant->participant_status ?? '' ), array( 'repondu', 'termine' ), true ) ) {
      wp_safe_redirect( $this->get_public_survey_redirect_url( $token, $participant_token, 'Cette réponse a déjà été enregistrée.', 'info' ) );
      exit;
    }

    $source = $this->get_questionnaire_source_data( $session->source_type, $session->source_id );
    $questions = ! empty( $source['questions'] ) && is_array( $source['questions'] ) ? $source['questions'] : array();
    if ( empty( $questions ) ) {
      wp_safe_redirect( $this->get_public_survey_redirect_url( $token, $participant_token, 'Aucune question disponible pour cette enquête.', 'error' ) );
      exit;
    }

    global $wpdb;
    $wpdb->delete(
      $this->questionnaire_answer_table,
      array(
        'session_id' => (int) $session->id,
        'participant_id' => (int) $participant->id,
      ),
      array( '%d', '%d' )
    );

    $posted_answers = isset( $_POST['survey_answers'] ) && is_array( $_POST['survey_answers'] ) ? wp_unslash( $_POST['survey_answers'] ) : array();
    $scores = array();
    $flags = array(
      'alert_flag' => 0,
      'issue_flag' => 0,
      'complaint_flag' => 0,
      'reservation_flag' => 0,
      'improvement_need_flag' => 0,
    );

    foreach ( $questions as $index => $question ) {
      $type = isset( $question['type'] ) ? (string) $question['type'] : 'Question ouverte';
      $label = isset( $question['label'] ) ? sanitize_text_field( (string) $question['label'] ) : 'Question ' . ( $index + 1 );
      $answer_value = '';
      $numeric_score = null;
      $points_awarded = 0;
      $is_alert = 0;
      $entry = isset( $posted_answers[ $index ] ) && is_array( $posted_answers[ $index ] ) ? $posted_answers[ $index ] : array();

      if ( 'Notation' === $type ) {
        $rating = isset( $entry['rating'] ) ? absint( $entry['rating'] ) : 0;
        if ( $rating < 1 ) {
          $rating = 1;
        }
        if ( $rating > 5 ) {
          $rating = 5;
        }
        $answer_value = (string) $rating;
        $numeric_score = (float) $rating;
        $points_awarded = $rating;
        $scores[] = $rating;
        if ( $rating <= 2 ) {
          $is_alert = 1;
          $flags['alert_flag'] = 1;
        }
      } elseif ( 'Choix multiples' === $type ) {
        $choices = isset( $entry['choices'] ) && is_array( $entry['choices'] ) ? array_map( 'sanitize_text_field', $entry['choices'] ) : array();
        $choices = array_values( array_filter( $choices, static function( $value ) {
          return '' !== (string) $value;
        } ) );
        $answer_value = implode( '||', $choices );
      } elseif ( 'Cases à cocher' === $type ) {
        $answer_value = isset( $entry['value'] ) ? sanitize_text_field( (string) $entry['value'] ) : '';
      } else {
        $answer_value = isset( $entry['text'] ) ? sanitize_textarea_field( (string) $entry['text'] ) : ( isset( $entry['value'] ) ? sanitize_text_field( (string) $entry['value'] ) : '' );
      }

      $lower_value = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $answer_value ) : strtolower( (string) $answer_value );
      if ( false !== strpos( $lower_value, 'réclamation' ) || false !== strpos( $lower_value, 'reclamation' ) ) {
        $flags['complaint_flag'] = 1;
        $flags['alert_flag'] = 1;
      }
      if ( false !== strpos( $lower_value, 'difficult' ) || false !== strpos( $lower_value, 'probl' ) || false !== strpos( $lower_value, 'insatisf' ) ) {
        $flags['issue_flag'] = 1;
        $flags['alert_flag'] = 1;
      }
      if ( false !== strpos( $lower_value, 'réserve' ) || false !== strpos( $lower_value, 'reserve' ) ) {
        $flags['reservation_flag'] = 1;
        $flags['alert_flag'] = 1;
      }
      if ( false !== strpos( $lower_value, 'amélioration' ) || false !== strpos( $lower_value, 'amelioration' ) || false !== strpos( $lower_value, 'besoin' ) ) {
        $flags['improvement_need_flag'] = 1;
      }

      $wpdb->insert(
        $this->questionnaire_answer_table,
        array(
          'session_id' => (int) $session->id,
          'participant_id' => (int) $participant->id,
          'question_index' => (int) $index,
          'question_key' => 'survey_question_' . (int) $index,
          'question_label' => $label,
          'answer_type' => sanitize_key( str_replace( ' ', '_', strtolower( $type ) ) ),
          'answer_value' => $answer_value,
          'is_correct' => 0,
          'points_awarded' => (int) $points_awarded,
          'answered_at' => $this->now_mysql(),
          'numeric_score' => null !== $numeric_score ? (float) $numeric_score : null,
          'is_alert' => $is_alert,
        ),
        array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%f', '%d' )
      );
    }

    $final_score = ! empty( $scores ) ? round( array_sum( $scores ) / count( $scores ), 2 ) : null;

    $update_data = array(
      'participant_status' => 'repondu',
      'opened_at' => ! empty( $participant->opened_at ) ? $participant->opened_at : $this->now_mysql(),
      'started_at' => ! empty( $participant->started_at ) ? $participant->started_at : $this->now_mysql(),
      'responded_at' => $this->now_mysql(),
      'finished_at' => $this->now_mysql(),
      'reminder_count' => isset( $participant->reminder_count ) ? (int) $participant->reminder_count : 0,
      'last_reminder_at' => ! empty( $participant->last_reminder_at ) ? $participant->last_reminder_at : null,
      'final_score' => $final_score,
      'alert_flag' => $flags['alert_flag'],
      'issue_flag' => $flags['issue_flag'],
      'complaint_flag' => $flags['complaint_flag'],
      'reservation_flag' => $flags['reservation_flag'],
      'improvement_need_flag' => $flags['improvement_need_flag'],
    );
    $update_formats = array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%f', '%d', '%d', '%d', '%d', '%d' );

    $wpdb->update(
      $this->questionnaire_participant_table,
      $update_data,
      array( 'id' => (int) $participant->id ),
      $update_formats,
      array( '%d' )
    );

    $this->maybe_mark_survey_session_completed( (int) $session->id );
    $this->log_questionnaire_event( array(
      'questionnaire_session_id' => (int) $session->id,
      'participant_id' => (int) $participant->id,
      'event_type' => 'survey_answer_submitted',
      'event_label' => 'Réponse enquête enregistrée',
    ) );

    $participant_after_submit = $this->get_questionnaire_session_participant( (int) $participant->id );
    $created_actions_count = $this->maybe_auto_create_questionnaire_actions_from_response( $session, $participant_after_submit ? $participant_after_submit : $participant );
    $success_notice = 'Merci, vos réponses ont bien été enregistrées.';
    if ( $created_actions_count > 0 ) {
      $success_notice .= ' ' . $created_actions_count . ' action(s) de suivi ont été créées automatiquement.';
    }

    wp_safe_redirect( $this->get_public_survey_redirect_url( $token, $participant_token, $success_notice, 'success' ) );
    exit;
  }


  public function handle_send_questionnaire_session_reminder() {
    $this->require_manage_options();
    $participant_id = isset( $_GET['participant_id'] ) ? absint( wp_unslash( $_GET['participant_id'] ) ) : 0;
    $this->verify_nonce_or_die( 'acdc_send_questionnaire_session_reminder_' . $participant_id );
    $participant = $participant_id ? $this->get_questionnaire_session_participant( $participant_id ) : null;
    if ( ! $participant ) {
      wp_die( esc_html( 'Participant introuvable.' ) );
    }
    $session = $this->get_questionnaire_session( (int) $participant->session_id );
    if ( ! $session ) {
      wp_die( esc_html( 'Session introuvable.' ) );
    }
    $email = $this->get_questionnaire_participant_contact_email( $participant );
    if ( ! $email ) {
      wp_die( esc_html( 'Aucune adresse e-mail exploitable pour ce participant.' ) );
    }
    $settings = $this->get_questionnaire_mail_settings();
    $sender_name = sanitize_text_field( (string) ( $settings['sender_name'] ?? '' ) );
    $sender_email = sanitize_email( (string) ( $settings['sender_email'] ?? '' ) );
    $reply_to = sanitize_email( ! empty( $settings['reply_to'] ) ? (string) $settings['reply_to'] : $sender_email );
    $headers = array( 'Content-Type: text/html; charset=UTF-8' );
    if ( $sender_email ) {
      $headers[] = 'From: ' . ( $sender_name ? $sender_name . ' <' . $sender_email . '>' : $sender_email );
    }
    if ( $reply_to ) {
      $headers[] = 'Reply-To: ' . $reply_to;
    }
    $url = add_query_arg(
      array(
        'token' => rawurlencode( (string) $session->public_token ),
        'participant' => rawurlencode( (string) $participant->participant_token ),
      ),
      $this->get_questionnaire_public_base_url()
    );
    $body = '<p>Bonjour ' . esc_html( $this->get_questionnaire_session_participant_display_name( $participant ) ) . ',</p>';
    $body .= '<p>Voici un rappel pour répondre au questionnaire : <strong>' . esc_html( $session->session_title ) . '</strong>.</p>';
    $body .= '<p><a href="' . esc_url( $url ) . '">Accéder au questionnaire</a></p>';
    $sent = $this->acdc_send_transactional_email(
      $email,
      $session->session_title . ' — rappel',
      array(
        'greeting_name' => $this->get_questionnaire_session_participant_display_name( $participant ),
        'intro_html' => '',
        'body_html' => $body,
        'footer_notice' => 'Cet e-mail a été envoyé dans le cadre du suivi de votre questionnaire. Vos données sont traitées conformément au RGPD.',
      ),
      array(
        'source_module' => 'questionnaires',
        'source_action' => 'questionnaire_manual_reminder',
        'email_category' => 'questionnaire',
        'email_audience' => 'destinataire',
      )
    );
    if ( $sent ) {
      global $wpdb;
      $data = array(
        'reminder_count' => max( 1, (int) ( $participant->reminder_count ?? 0 ) + 1 ),
        'last_reminder_at' => $this->now_mysql(),
      );
      $formats = array( '%d', '%s' );
      $wpdb->update( $this->questionnaire_participant_table, $data, array( 'id' => $participant_id ), $formats, array( '%d' ) );
      $this->log_questionnaire_event( array(
        'questionnaire_session_id' => (int) $session->id,
        'participant_id' => $participant_id,
        'event_type' => 'reminder_sent',
        'event_label' => 'Relance questionnaire envoyée',
        'created_by' => get_current_user_id(),
      ) );
    }
    wp_safe_redirect( add_query_arg( array(
      'page' => 'acdc-of-questionnaire-sessions',
      'action' => 'results',
      'item_id' => (int) $session->id,
      'notice' => rawurlencode( $sent ? 'Relance envoyée.' : 'Échec de l’envoi de la relance.' ),
      'notice_type' => $sent ? 'success' : 'error',
    ), admin_url( 'admin.php' ) ) );
    exit;
  }


  public function handle_send_questionnaire_session_bulk_reminders() {
    $this->require_manage_options();
    $session_id = isset( $_GET['questionnaire_session_id'] ) ? absint( wp_unslash( $_GET['questionnaire_session_id'] ) ) : 0;
    $this->verify_nonce_or_die( 'acdc_send_questionnaire_session_bulk_reminders_' . $session_id );
    $session = $session_id ? $this->get_questionnaire_session( $session_id ) : null;
    if ( ! $session ) {
      wp_die( esc_html( 'Session introuvable.' ) );
    }

    $participants = $this->get_questionnaire_session_participants( $session_id );
    $sent = 0;
    $skipped = 0;
    global $wpdb;

    foreach ( (array) $participants as $participant ) {
      if ( in_array( (string) ( $participant->participant_status ?? '' ), array( 'repondu', 'termine' ), true ) ) {
        $skipped++;
        continue;
      }
      $email = $this->get_questionnaire_participant_contact_email( $participant );
      if ( ! $email ) {
        $skipped++;
        continue;
      }

      $url = add_query_arg(
        array(
          'token'       => rawurlencode( (string) $session->public_token ),
          'participant' => rawurlencode( (string) $participant->participant_token ),
        ),
        $this->get_questionnaire_public_base_url()
      );
      $body = '<p>Bonjour ' . esc_html( $this->get_questionnaire_session_participant_display_name( $participant ) ) . ',</p>';
      $body .= '<p>Nous vous relançons pour répondre à l’enquête : <strong>' . esc_html( $session->session_title ) . '</strong>.</p>';
      $body .= '<p><a href="' . esc_url( $url ) . '">Accéder au questionnaire</a></p>';

      $mail_sent = $this->acdc_send_transactional_email(
        $email,
        $session->session_title . ' — relance',
        array(
          'greeting_name' => $this->get_questionnaire_session_participant_display_name( $participant ),
          'intro_html'    => '',
          'body_html'     => $body,
          'footer_notice' => 'Cet e-mail a été envoyé dans le cadre du suivi de votre questionnaire. Vos données sont traitées conformément au RGPD.',
        ),
        array(
          'source_module' => 'questionnaires',
          'source_action' => 'questionnaire_bulk_reminder',
          'email_category'=> 'questionnaire',
          'email_audience'=> 'destinataire',
        )
      );

      if ( $mail_sent ) {
        $sent++;
        $wpdb->update(
          $this->questionnaire_participant_table,
          array(
            'reminder_count'    => (int) ( $participant->reminder_count ?? 0 ) + 1,
            'last_reminder_at'  => $this->now_mysql(),
          ),
          array( 'id' => (int) $participant->id ),
          array( '%d', '%s' ),
          array( '%d' )
        );
      } else {
        $skipped++;
      }
    }

    $notice = $sent > 0
      ? sprintf( 'Relance envoyée à %d destinataire(s). %d ignoré(s).', (int) $sent, (int) $skipped )
      : 'Aucune relance n’a pu être envoyée.';
    $notice_type = $sent > 0 ? 'success' : 'error';
    $redirect_args = array(
      'page'        => 'acdc-of-questionnaire-sessions',
      'action'      => 'edit',
      'item_id'     => $session_id,
      'notice'      => rawurlencode( $notice ),
      'notice_type' => $notice_type,
    );
    if ( ! empty( $_GET['source_type'] ) ) { $redirect_args['source_type'] = sanitize_text_field( wp_unslash( $_GET['source_type'] ) ); }
    if ( ! empty( $_GET['source_id'] ) ) { $redirect_args['source_id'] = absint( wp_unslash( $_GET['source_id'] ) ); }
    wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
    exit;
  }


  public function handle_reopen_questionnaire_participant() {
    $this->require_manage_options();
    $participant_id = isset( $_GET['participant_id'] ) ? absint( wp_unslash( $_GET['participant_id'] ) ) : 0;
    $this->verify_nonce_or_die( 'acdc_reopen_questionnaire_participant_' . $participant_id );
    $participant = $participant_id ? $this->get_questionnaire_session_participant( $participant_id ) : null;
    if ( ! $participant ) {
      wp_die( esc_html( 'Participant introuvable.' ) );
    }
    global $wpdb;
    $update = array(
      'participant_status' => 'en_attente',
      'finished_at' => null,
    );
    $formats = array( '%s', '%s' );
    if ( property_exists( $participant, 'responded_at' ) || false !== stripos( $wpdb->last_query ?? '', 'responded_at' ) ) {
      $update['responded_at'] = null;
      $formats[] = '%s';
    }
    if ( property_exists( $participant, 'started_at' ) || property_exists( $participant, 'opened_at' ) ) {
      if ( array_key_exists( 'opened_at', (array) $participant ) ) {
        $update['opened_at'] = null;
        $formats[] = '%s';
      }
      if ( array_key_exists( 'started_at', (array) $participant ) ) {
        $update['started_at'] = null;
        $formats[] = '%s';
      }
    }
    $wpdb->update( $this->questionnaire_participant_table, $update, array( 'id' => $participant_id ), $formats, array( '%d' ) );
    $wpdb->delete( $this->questionnaire_answer_table, array( 'participant_id' => $participant_id ), array( '%d' ) );
    $this->log_questionnaire_event( array(
      'questionnaire_session_id' => (int) $participant->session_id,
      'participant_id' => $participant_id,
      'event_type' => 'participant_reopened',
      'event_label' => 'Réponse questionnaire rouverte',
      'created_by' => get_current_user_id(),
    ) );
    wp_safe_redirect( add_query_arg( array(
      'page' => 'acdc-of-questionnaire-sessions',
      'action' => 'results',
      'item_id' => (int) $participant->session_id,
      'notice' => rawurlencode( 'Le participant peut répondre à nouveau.' ),
      'notice_type' => 'success',
    ), admin_url( 'admin.php' ) ) );
    exit;
  }


  public function handle_create_questionnaire_action() {
    $this->require_manage_options();
    check_admin_referer( 'acdc_create_questionnaire_action' );
    global $wpdb;
    $session_id = isset( $_POST['questionnaire_session_id'] ) ? absint( wp_unslash( $_POST['questionnaire_session_id'] ) ) : 0;
    $participant_id = isset( $_POST['participant_id'] ) ? absint( wp_unslash( $_POST['participant_id'] ) ) : 0;
    $action_type = isset( $_POST['action_type'] ) ? sanitize_key( wp_unslash( $_POST['action_type'] ) ) : 'improvement';
    $action_label = isset( $_POST['action_label'] ) ? sanitize_text_field( wp_unslash( $_POST['action_label'] ) ) : '';
    $action_status = isset( $_POST['action_status'] ) ? sanitize_key( wp_unslash( $_POST['action_status'] ) ) : 'ouverte';
    $assigned_to = isset( $_POST['assigned_to'] ) ? absint( wp_unslash( $_POST['assigned_to'] ) ) : get_current_user_id();
    $priority_level = $this->normalize_questionnaire_action_priority_level( isset( $_POST['priority_level'] ) ? wp_unslash( $_POST['priority_level'] ) : 'Moyenne' );
    $notes = isset( $_POST['notes'] ) ? wp_kses_post( wp_unslash( $_POST['notes'] ) ) : '';
    $target_due_days = isset( $_POST['target_due_days'] ) ? max( 1, absint( wp_unslash( $_POST['target_due_days'] ) ) ) : 0;
    $due_at = isset( $_POST['due_at'] ) ? $this->normalize_questionnaire_action_due_at_input( wp_unslash( $_POST['due_at'] ), $target_due_days ) : ( $target_due_days > 0 ? $this->calculate_questionnaire_action_due_at_from_days( $target_due_days ) : null );
    $return_source_type = isset( $_POST['return_source_type'] ) ? sanitize_text_field( wp_unslash( $_POST['return_source_type'] ) ) : '';
    $return_source_id = isset( $_POST['return_source_id'] ) ? absint( wp_unslash( $_POST['return_source_id'] ) ) : 0;
    if ( ! $session_id || '' === $action_label ) {
      wp_die( esc_html( 'Données incomplètes.' ) );
    }
    if ( ! in_array( $action_status, array( 'ouverte', 'en_cours', 'traitee', 'annulee' ), true ) ) {
      $action_status = 'ouverte';
    }
    $now = $this->now_mysql();
    $closed_at = in_array( $action_status, array( 'traitee', 'annulee' ), true ) ? $now : null;
    $wpdb->insert(
      $this->questionnaire_action_table,
      array(
        'questionnaire_session_id' => $session_id,
        'participant_id' => $participant_id ? $participant_id : null,
        'action_type' => $action_type,
        'action_label' => $action_label,
        'action_status' => $action_status,
        'assigned_to' => $assigned_to ? $assigned_to : null,
        'priority_level' => $priority_level,
        'target_due_days' => $target_due_days ? $target_due_days : null,
        'due_at' => $due_at,
        'opened_at' => $now,
        'closed_at' => $closed_at,
        'notes' => $notes,
        'created_at' => $now,
        'updated_at' => $now,
      ),
      array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
    );
    $this->log_questionnaire_event( array(
      'questionnaire_session_id' => $session_id,
      'participant_id' => $participant_id,
      'event_type' => 'action_created',
      'event_label' => 'Action questionnaire créée',
      'event_payload' => array( 'status' => $action_status, 'assigned_to' => $assigned_to, 'priority_level' => $priority_level, 'target_due_days' => $target_due_days, 'due_at' => $due_at ),
      'created_by' => get_current_user_id(),
    ) );
    $args = $this->get_questionnaire_action_redirect_args( $session_id, $participant_id, $return_source_type, $return_source_id );
    $args['notice'] = rawurlencode( 'Action créée.' );
    $args['notice_type'] = 'success';
    wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
    exit;
  }


  public function handle_update_questionnaire_action() {
    $this->require_manage_options();
    $action_id = isset( $_POST['action_id'] ) ? absint( wp_unslash( $_POST['action_id'] ) ) : 0;
    check_admin_referer( 'acdc_update_questionnaire_action_' . $action_id );
    global $wpdb;
    $session_id = isset( $_POST['questionnaire_session_id'] ) ? absint( wp_unslash( $_POST['questionnaire_session_id'] ) ) : 0;
    $participant_id = isset( $_POST['participant_id'] ) ? absint( wp_unslash( $_POST['participant_id'] ) ) : 0;
    $action_status = isset( $_POST['action_status'] ) ? sanitize_key( wp_unslash( $_POST['action_status'] ) ) : 'ouverte';
    $assigned_to = isset( $_POST['assigned_to'] ) ? absint( wp_unslash( $_POST['assigned_to'] ) ) : 0;
    $notes = isset( $_POST['notes'] ) ? wp_kses_post( wp_unslash( $_POST['notes'] ) ) : '';
    $target_due_days = isset( $_POST['target_due_days'] ) ? max( 1, absint( wp_unslash( $_POST['target_due_days'] ) ) ) : 0;
    $due_at = isset( $_POST['due_at'] ) ? $this->normalize_questionnaire_action_due_at_input( wp_unslash( $_POST['due_at'] ), $target_due_days ) : ( $target_due_days > 0 ? $this->calculate_questionnaire_action_due_at_from_days( $target_due_days ) : null );
    $return_source_type = isset( $_POST['return_source_type'] ) ? sanitize_text_field( wp_unslash( $_POST['return_source_type'] ) ) : '';
    $return_source_id = isset( $_POST['return_source_id'] ) ? absint( wp_unslash( $_POST['return_source_id'] ) ) : 0;
    if ( ! $action_id || ! $session_id ) {
      wp_die( esc_html( 'Action introuvable.' ) );
    }
    if ( ! in_array( $action_status, array( 'ouverte', 'en_cours', 'traitee', 'annulee' ), true ) ) {
      $action_status = 'ouverte';
    }
    $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->questionnaire_action_table} WHERE id = %d", $action_id ) );
    if ( ! $existing ) {
      wp_die( esc_html( 'Action introuvable.' ) );
    }
    $priority_level = $this->normalize_questionnaire_action_priority_level( isset( $_POST['priority_level'] ) ? wp_unslash( $_POST['priority_level'] ) : ( ! empty( $existing->priority_level ) ? $existing->priority_level : 'Moyenne' ) );
    $data = array(
      'action_status' => $action_status,
      'assigned_to' => $assigned_to ? $assigned_to : null,
      'priority_level' => $priority_level,
      'target_due_days' => $target_due_days ? $target_due_days : null,
      'due_at' => $due_at,
      'notes' => $notes,
      'updated_at' => $this->now_mysql(),
    );
    $formats = array( '%s', '%d', '%s', '%d', '%s', '%s', '%s' );
    if ( in_array( $action_status, array( 'traitee', 'annulee' ), true ) ) {
      $data['closed_at'] = $existing->closed_at ? $existing->closed_at : $this->now_mysql();
      $formats[] = '%s';
    } else {
      $data['closed_at'] = null;
      $formats[] = '%s';
    }
    if ( empty( $existing->opened_at ) ) {
      $data['opened_at'] = $this->now_mysql();
      $formats[] = '%s';
    }
    $wpdb->update( $this->questionnaire_action_table, $data, array( 'id' => $action_id ), $formats, array( '%d' ) );
    $this->log_questionnaire_event( array(
      'questionnaire_session_id' => $session_id,
      'participant_id' => $participant_id,
      'event_type' => 'action_updated',
      'event_label' => 'Action questionnaire mise à jour',
      'event_payload' => array( 'action_id' => $action_id, 'status' => $action_status, 'assigned_to' => $assigned_to, 'priority_level' => $priority_level, 'target_due_days' => $target_due_days, 'due_at' => $due_at ),
      'created_by' => get_current_user_id(),
    ) );
    $args = $this->get_questionnaire_action_redirect_args( $session_id, $participant_id, $return_source_type, $return_source_id );
    $args['notice'] = rawurlencode( 'Action mise à jour.' );
    $args['notice_type'] = 'success';
    wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
    exit;
  }


}
