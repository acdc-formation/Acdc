<?php
/**
 * ACDC Questionnaires / Enquêtes — cœur extrait.
 *
 * @since 3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Questionnaires_Core_Trait {

  public function ensure_questionnaire_public_page() {
    $page_id = absint( get_option( 'acdc_of_questionnaire_public_page_id', 0 ) );

    if ( $page_id && get_post( $page_id ) ) {
      return $page_id;
    }

    $existing = get_page_by_path( 'acdc-questionnaire-public', OBJECT, 'page' );
    if ( $existing && ! empty( $existing->ID ) ) {
      update_option( 'acdc_of_questionnaire_public_page_id', (int) $existing->ID );
      return (int) $existing->ID;
    }

    $page_id = wp_insert_post(
      array(
        'post_title'   => 'Réponse à un questionnaire',
        'post_name'    => 'acdc-questionnaire-public',
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_content' => '[acdc_questionnaire_public]',
      ),
      true
    );

    if ( is_wp_error( $page_id ) ) {
      $this->log_error( 'questionnaire_public_page', $page_id->get_error_message() );
      return 0;
    }

    update_option( 'acdc_of_questionnaire_public_page_id', (int) $page_id );

    return (int) $page_id;
  }



  private function get_dashboard_questionnaire_todo_counts() {
    global $wpdb;

    $counts = array(
      'mid'     => 0,
      'hot'     => 0,
      'cold'    => 0,
      'trainer' => 0,
      'company' => 0,
      'funder'  => 0,
    );

    if ( empty( $this->questionnaire_action_table ) || empty( $this->questionnaire_session_table ) ) {
      return $counts;
    }

    $rows = $wpdb->get_results(
      "SELECT s.source_type, COUNT(*) AS total
       FROM {$this->questionnaire_action_table} a
       INNER JOIN {$this->questionnaire_session_table} s ON s.id = a.questionnaire_session_id
       WHERE a.action_status NOT IN ('traitee','annulee')
         AND s.source_type IN ('mid_survey','hot_survey','cold_survey','trainer_survey','company_survey','funder_survey')
       GROUP BY s.source_type"
    );

    if ( ! is_array( $rows ) ) {
      return $counts;
    }

    $map = array(
      'mid_survey'     => 'mid',
      'hot_survey'     => 'hot',
      'cold_survey'    => 'cold',
      'trainer_survey' => 'trainer',
      'company_survey' => 'company',
      'funder_survey'  => 'funder',
    );

    foreach ( $rows as $row ) {
      $source_type = isset( $row->source_type ) ? (string) $row->source_type : '';
      if ( isset( $map[ $source_type ] ) ) {
        $counts[ $map[ $source_type ] ] = isset( $row->total ) ? (int) $row->total : 0;
      }
    }

    return $counts;
  }


  private function get_questionnaire_action_priority_levels() {
    return array(
      'basse'   => 'Basse',
      'moyenne' => 'Moyenne',
      'haute'   => 'Haute',
    );
  }


  private function normalize_questionnaire_action_priority_level( $value ) {
    $value = sanitize_key( remove_accents( (string) $value ) );
    if ( in_array( $value, array( 'haute', 'critique' ), true ) ) {
      return 'Haute';
    }
    if ( in_array( $value, array( 'moyenne', 'normale' ), true ) ) {
      return 'Moyenne';
    }
    if ( 'basse' === $value ) {
      return 'Basse';
    }
    return 'Moyenne';
  }


  private function get_questionnaire_action_priority_badge( $value ) {
    $label = $this->normalize_questionnaire_action_priority_level( $value );
    $key   = sanitize_key( remove_accents( $label ) );
    return array(
      'label' => $label,
      'key'   => $key,
      'class' => 'acdc-priority-badge acdc-priority-badge-' . $key,
    );
  }


  private function get_questionnaire_action_priority_filter_condition( $priority_level, $table_alias = 'a' ) {
    $priority_level = sanitize_key( remove_accents( (string) $priority_level ) );
    $table_alias    = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $table_alias );
    if ( '' === $table_alias ) {
      $table_alias = 'a';
    }

    switch ( $priority_level ) {
      case 'haute':
        return array( 'sql' => "{$table_alias}.priority_level IN (%s,%s)", 'params' => array( 'Haute', 'Critique' ) );
      case 'moyenne':
        return array( 'sql' => "{$table_alias}.priority_level IN (%s,%s)", 'params' => array( 'Moyenne', 'Normale' ) );
      case 'basse':
        return array( 'sql' => "{$table_alias}.priority_level = %s", 'params' => array( 'Basse' ) );
    }

    return array( 'sql' => '', 'params' => array() );
  }


  private function get_questionnaire_dashboard_results_url( $source_type = '', $extra_args = array() ) {
    $source_type = sanitize_key( (string) $source_type );
    $args = array(
      'action_scope' => 'a_traiter',
      'dashboard_focus' => '1',
    );
    if ( '' !== $source_type ) {
      $args['source_type'] = $source_type;
    }
    if ( is_array( $extra_args ) && ! empty( $extra_args ) ) {
      $args = array_merge( $args, $extra_args );
    }
    if ( is_admin() ) {
      return $this->admin_tab_url( 'questionnaire_results', $args );
    }
    return $this->portal_page_url( array_merge( array( 'tab' => 'questionnaire_results' ), $args ) );
  }


  private function get_mid_survey_document_info( $registration, $context = array() ) {
    $info = array(
      'url'   => '',
      'path'  => '',
      'size'  => '',
      'label' => 'Résultats Enquêtes intermédiaires',
    );
    if ( ! $registration ) {
      return $info;
    }
    $url  = isset( $registration->mid_survey_document_url ) ? trim( (string) $registration->mid_survey_document_url ) : '';
    $path = isset( $registration->mid_survey_document_path ) ? trim( (string) $registration->mid_survey_document_path ) : '';
    if ( '' === $path && '' !== $url ) {
      $path = $this->get_local_path_from_upload_url( $url );
    }
    if ( '' === $url ) {
      global $wpdb;
      $learner_name = isset( $context['learner_name'] ) ? (string) $context['learner_name'] : ( ! empty( $registration->learner_label ) ? (string) $registration->learner_label : '' );
      $formation_title = isset( $context['formation_title'] ) ? (string) $context['formation_title'] : ( ! empty( $registration->formation_title ) ? (string) $registration->formation_title : '' );
      $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->document_table} WHERE (document_type LIKE %s OR title LIKE %s) ORDER BY id DESC LIMIT 25", '%interm%', '%interm%' ) );
      foreach ( (array) $rows as $row ) {
        $haystack = trim( (string) $row->title . ' ' . (string) $row->document_type );
        $match = false;
        if ( '' !== $learner_name && false !== stripos( $haystack, $learner_name ) ) {
          $match = true;
        }
        if ( ! $match && '' !== $formation_title && false !== stripos( $haystack, $formation_title ) ) {
          $match = true;
        }
        if ( ! $match ) {
          continue;
        }
        $url  = ! empty( $row->file_url ) ? (string) $row->file_url : '';
        $path = ! empty( $row->file_path ) ? (string) $row->file_path : '';
        break;
      }
    }
    $info['url'] = $url;
    $info['path'] = $path;
    if ( '' !== $path && file_exists( $path ) ) {
      $size = filesize( $path );
      if ( $size ) {
        if ( $size >= 1048576 ) {
          $info['size'] = number_format_i18n( $size / 1048576, 1 ) . ' MB';
        } elseif ( $size >= 1024 ) {
          $info['size'] = number_format_i18n( $size / 1024, 0 ) . ' KB';
        } else {
          $info['size'] = $size . ' octets';
        }
      }
    }
    return $info;
  }


  private function get_mid_survey_display_file_name( $registration, $context = array(), $document = array() ) {
    $context = is_array( $context ) ? $context : array();
    $document = is_array( $document ) ? $document : array();
    if ( ! empty( $document['url'] ) ) {
      $path = wp_parse_url( (string) $document['url'], PHP_URL_PATH );
      if ( $path ) {
        return basename( (string) $path );
      }
    }
    $learner = isset( $context['learner_name'] ) ? (string) $context['learner_name'] : ( ! empty( $registration->learner_label ) ? (string) $registration->learner_label : 'apprenant' );
    $formation = isset( $context['formation_title'] ) ? (string) $context['formation_title'] : ( ! empty( $registration->formation_title ) ? (string) $registration->formation_title : 'formation' );
    return sanitize_file_name( 'enquete-intermediaire-' . $learner . '-' . $formation . '.pdf' );
  }


  private function get_mid_survey_context( $registration ) {
    $context = $this->get_training_convocation_context( $registration );
    $survey = $this->get_mid_survey_for_formation( ! empty( $registration->formation_id ) ? (int) $registration->formation_id : 0 );
    $document = $this->get_mid_survey_document_info( $registration, $context );
    $context['survey'] = $survey;
    $context['survey_title'] = $survey && ! empty( $survey->title ) ? (string) $survey->title : 'Enquête intermédiaire';
    $context['survey_source_label'] = 'Plateforme';
    $context['document'] = $document;
    $context['result_label'] = ( ! empty( $document['url'] ) || ! empty( $document['path'] ) ) ? 'Disponible' : '—';
    return $context;
  }


  private function get_mid_survey_for_formation( $formation_id ) {
    $formation_id = absint( $formation_id );
    if ( ! $formation_id ) {
      return null;
    }
    $surveys = $this->get_mid_surveys( '', true );
    foreach ( (array) $surveys as $survey ) {
      $ids = array();
      if ( ! empty( $survey->formation_ids ) ) {
        $decoded = json_decode( (string) $survey->formation_ids, true );
        if ( is_array( $decoded ) ) {
          $ids = array_values( array_filter( array_map( 'absint', $decoded ) ) );
        } else {
          $ids = array_values( array_filter( array_map( 'absint', explode( ',', (string) $survey->formation_ids ) ) ) );
        }
      }
      if ( in_array( $formation_id, $ids, true ) ) {
        return $survey;
      }
    }
    return null;
  }


  private function get_mid_survey_download_url( $registration, $mode = 'attachment' ) {
    if ( ! $registration || empty( $registration->id ) ) {
      return '';
    }
    $mode = 'inline' === $mode ? 'inline' : 'attachment';
    return wp_nonce_url( admin_url( 'admin-post.php?action=acdc_download_mid_survey_document&registration_id=' . (int) $registration->id . '&mode=' . rawurlencode( $mode ) ), 'acdc_download_mid_survey_document_' . (int) $registration->id );
  }


  private function get_mid_survey_rows( $search = '' ) {
    $search = trim( (string) $search );
    $items = $this->get_training_registrations( false );
    $rows = array();
    foreach ( (array) $items as $entry ) {
      if ( empty( $entry->formation_id ) ) {
        continue;
      }
      $context = $this->get_mid_survey_context( $entry );
      $formation_enabled = ! empty( $context['formation'] ) && ! empty( $context['formation']->intermediate_survey_enabled );
      if ( ! $formation_enabled && empty( $context['survey'] ) && empty( $context['document']['url'] ) && empty( $context['document']['path'] ) ) {
        continue;
      }
      $haystack = strtolower( implode( ' ', array_filter( array(
        (string) $entry->id,
        (string) $context['learner_name'],
        (string) $context['survey_title'],
        (string) $context['formation_title'],
      ) ) ) );
      if ( '' !== $search && false === strpos( $haystack, strtolower( $search ) ) ) {
        continue;
      }
      $state = '—';
      if ( ! empty( $context['end_date'] ) ) {
        $end_ts = strtotime( (string) $context['end_date'] . ' 23:59:59' );
        if ( $end_ts && $end_ts < current_time( 'timestamp' ) ) {
          $state = 'Formation terminée - ---';
        }
      }
      $rows[] = array(
        'registration' => $entry,
        'context' => $context,
        'state' => $state,
      );
    }
    return $rows;
  }


  private function build_mid_survey_pdf_pages( $registration, $context = array() ) {
    $context = is_array( $context ) ? $context : array();
    if ( empty( $context ) ) {
      $context = $this->get_mid_survey_context( $registration );
    }
    $profile = $this->get_company_profile_options();
    $branding = $this->get_branding_options();
    $org_name = ! empty( $profile['enterprise'] ) ? $profile['enterprise'] : ( ! empty( $branding['company_name'] ) ? $branding['company_name'] : 'ACDC Formation' );
    $header_bg = ! empty( $profile['header_footer_bg'] ) ? $profile['header_footer_bg'] : '#F3E3BF';
    $title_color = '#0C2D52';
    $muted = '#1E4777';
    $logo = $this->prepare_pdf_jpeg_image( $this->acdc_resolve_pdf_logo_url(), 42, 42 );
    $footer_logo = ! empty( $profile['footer_logo_url'] ) ? $this->prepare_pdf_jpeg_image( $profile['footer_logo_url'], 120, 40 ) : null;

    $pages = array();
    $page = array();
    $page[] = array( 'type' => 'rect', 'x' => 0, 'y' => 760, 'width' => 595, 'height' => 82, 'fill_color' => $header_bg );
    if ( $logo ) {
      $page[] = array(
        'type' => 'image', 'image_key' => $logo['key'], 'image_data' => $logo['data'], 'image_width' => $logo['width'], 'image_height' => $logo['height'], 'display_width' => $logo['display_width'], 'display_height' => $logo['display_height'], 'x' => 250, 'y' => 786,
      );
    }
    $page[] = array( 'text' => strtoupper( $org_name ), 'x' => 228, 'y' => 812, 'size' => 14, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    $page[] = array( 'text' => 'Résultats Enquêtes intermédiaires', 'x' => 182, 'y' => 782, 'size' => 18, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    $page[] = array( 'text' => $context['learner_name'], 'x' => 235, 'y' => 760, 'size' => 11, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    $page[] = array( 'text' => 'Formation : ' . $context['formation_title'], 'x' => 70, 'y' => 730, 'size' => 9.5, 'font' => 'Helvetica', 'color' => $title_color );
    $page[] = array( 'text' => 'Enquête : ' . $context['survey_title'], 'x' => 70, 'y' => 714, 'size' => 9.5, 'font' => 'Helvetica', 'color' => $title_color );
    $page[] = array( 'text' => 'Dates de formation : ' . $this->format_pdf_date( $context['start_date'] ) . ' au ' . $this->format_pdf_date( $context['end_date'] ), 'x' => 70, 'y' => 698, 'size' => 9.5, 'font' => 'Helvetica', 'color' => $muted );
    $page[] = array( 'text' => 'Format : ' . $context['format'], 'x' => 70, 'y' => 682, 'size' => 9.5, 'font' => 'Helvetica', 'color' => $muted );
    $page[] = array( 'text' => 'Durée : ' . $context['duration'], 'x' => 70, 'y' => 666, 'size' => 9.5, 'font' => 'Helvetica', 'color' => $muted );
    $page[] = array( 'text' => 'Statut : Document généré', 'x' => 70, 'y' => 650, 'size' => 9.5, 'font' => 'Helvetica-Bold', 'color' => '#35B37E' );
    $page[] = array( 'type' => 'rect', 'x' => 54, 'y' => 518, 'width' => 487, 'height' => 104, 'stroke_color' => '#B8E0CF', 'line_width' => 1.2 );
    $wrapped = $this->pdf_wrap_text( "Ce document récapitule le résultat de l’enquête intermédiaire associée à la formation. Aucun détail de réponse n’est disponible dans cette version du plugin. Le document peut être remplacé manuellement depuis la fenêtre de mise à jour si un PDF officiel existe déjà.", 88 );
    $y = 596
    ;
    foreach ( $wrapped as $line ) {
      $page[] = array( 'text' => $line, 'x' => 70, 'y' => $y, 'size' => 10, 'font' => 'Helvetica', 'color' => '#1F2937' );
      $y -= 14;
    }
    if ( $footer_logo ) {
      $page[] = array(
        'type' => 'image', 'image_key' => $footer_logo['key'], 'image_data' => $footer_logo['data'], 'image_width' => $footer_logo['width'], 'image_height' => $footer_logo['height'], 'display_width' => $footer_logo['display_width'], 'display_height' => $footer_logo['display_height'], 'x' => 235, 'y' => 34,
      );
    } else {
      $page[] = array( 'text' => strtoupper( $org_name ), 'x' => 220, 'y' => 50, 'size' => 11.5, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    }
    $pages[] = $page;
    return $pages;
  }


  private function get_hot_survey_document_info( $registration, $context = array() ) {
    $info = array(
      'url'   => '',
      'path'  => '',
      'size'  => '',
      'label' => 'Résultats Enquêtes de satisfaction à chaud',
    );
    if ( ! $registration ) {
      return $info;
    }
    $url  = isset( $registration->hot_survey_document_url ) ? trim( (string) $registration->hot_survey_document_url ) : '';
    $path = isset( $registration->hot_survey_document_path ) ? trim( (string) $registration->hot_survey_document_path ) : '';
    if ( '' === $path && '' !== $url ) {
      $path = $this->get_local_path_from_upload_url( $url );
    }
    if ( '' === $url ) {
      global $wpdb;
      $learner_name = isset( $context['learner_name'] ) ? (string) $context['learner_name'] : ( ! empty( $registration->learner_label ) ? (string) $registration->learner_label : '' );
      $formation_title = isset( $context['formation_title'] ) ? (string) $context['formation_title'] : ( ! empty( $registration->formation_title ) ? (string) $registration->formation_title : '' );
      $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->document_table} WHERE (document_type LIKE %s OR title LIKE %s OR title LIKE %s) ORDER BY id DESC LIMIT 25", '%chaud%', '%chaud%', '%satisfaction%' ) );
      foreach ( (array) $rows as $row ) {
        $haystack = trim( (string) $row->title . ' ' . (string) $row->document_type );
        $match = false;
        if ( '' !== $learner_name && false !== stripos( $haystack, $learner_name ) ) {
          $match = true;
        }
        if ( ! $match && '' !== $formation_title && false !== stripos( $haystack, $formation_title ) ) {
          $match = true;
        }
        if ( ! $match ) {
          continue;
        }
        $url  = ! empty( $row->file_url ) ? (string) $row->file_url : '';
        $path = ! empty( $row->file_path ) ? (string) $row->file_path : '';
        break;
      }
    }
    $info['url'] = $url;
    $info['path'] = $path;
    if ( '' !== $path && file_exists( $path ) ) {
      $size = filesize( $path );
      if ( $size ) {
        if ( $size >= 1048576 ) {
          $info['size'] = number_format_i18n( $size / 1048576, 1 ) . ' MB';
        } elseif ( $size >= 1024 ) {
          $info['size'] = number_format_i18n( $size / 1024, 0 ) . ' KB';
        } else {
          $info['size'] = $size . ' octets';
        }
      }
    }
    return $info;
  }


  private function get_hot_survey_display_file_name( $registration, $context = array(), $document = array() ) {
    $context = is_array( $context ) ? $context : array();
    $document = is_array( $document ) ? $document : array();
    if ( ! empty( $document['url'] ) ) {
      $path = wp_parse_url( (string) $document['url'], PHP_URL_PATH );
      if ( $path ) {
        return basename( (string) $path );
      }
    }
    $learner = isset( $context['learner_name'] ) ? (string) $context['learner_name'] : ( ! empty( $registration->learner_label ) ? (string) $registration->learner_label : 'apprenant' );
    $formation = isset( $context['formation_title'] ) ? (string) $context['formation_title'] : ( ! empty( $registration->formation_title ) ? (string) $registration->formation_title : 'formation' );
    return sanitize_file_name( 'enquete-a-chaud-' . $learner . '-' . $formation . '.pdf' );
  }


  private function get_hot_survey_context( $registration ) {
    $context = $this->get_training_convocation_context( $registration );
    $survey = $this->get_hot_survey_for_formation( ! empty( $registration->formation_id ) ? (int) $registration->formation_id : 0 );
    $document = $this->get_hot_survey_document_info( $registration, $context );
    $context['survey'] = $survey;
    $context['survey_title'] = $survey && ! empty( $survey->title ) ? (string) $survey->title : 'Enquête de satisfaction à chaud';
    $context['survey_source_label'] = 'Plateforme';
    $context['document'] = $document;
    $context['result_label'] = ( ! empty( $document['url'] ) || ! empty( $document['path'] ) ) ? 'Complétée' : '—';
    $context['completed_at_label'] = ! empty( $registration->updated_at ) ? mysql2date( 'j F Y', (string) $registration->updated_at, true ) : '';
    return $context;
  }


  private function get_hot_survey_for_formation( $formation_id ) {
    $formation_id = absint( $formation_id );
    if ( ! $formation_id ) {
      return null;
    }
    $surveys = $this->get_hot_surveys( '', true );
    foreach ( (array) $surveys as $survey ) {
      $ids = array();
      if ( ! empty( $survey->formation_ids ) ) {
        $decoded = json_decode( (string) $survey->formation_ids, true );
        if ( is_array( $decoded ) ) {
          $ids = array_values( array_filter( array_map( 'absint', $decoded ) ) );
        } else {
          $ids = array_values( array_filter( array_map( 'absint', explode( ',', (string) $survey->formation_ids ) ) ) );
        }
      }
      if ( in_array( $formation_id, $ids, true ) ) {
        return $survey;
      }
    }
    return null;
  }


  private function get_hot_survey_download_url( $registration, $mode = 'attachment' ) {
    if ( ! $registration || empty( $registration->id ) ) {
      return '';
    }
    $mode = 'inline' === $mode ? 'inline' : 'attachment';
    return wp_nonce_url( admin_url( 'admin-post.php?action=acdc_download_hot_survey_document&registration_id=' . (int) $registration->id . '&mode=' . rawurlencode( $mode ) ), 'acdc_download_hot_survey_document_' . (int) $registration->id );
  }


  private function get_hot_survey_rows( $search = '' ) {
    $search = trim( (string) $search );
    $items = $this->get_training_registrations( false );
    $rows = array();
    foreach ( (array) $items as $entry ) {
      if ( empty( $entry->formation_id ) ) {
        continue;
      }
      $context = $this->get_hot_survey_context( $entry );
      $formation_enabled = ! empty( $context['formation'] ) && ! empty( $context['formation']->hot_survey_enabled );
      if ( ! $formation_enabled && empty( $context['survey'] ) && empty( $context['document']['url'] ) && empty( $context['document']['path'] ) ) {
        continue;
      }
      $haystack = strtolower( implode( ' ', array_filter( array(
        (string) $entry->id,
        (string) $context['learner_name'],
        (string) $context['survey_title'],
        (string) $context['formation_title'],
      ) ) ) );
      if ( '' !== $search && false === strpos( $haystack, strtolower( $search ) ) ) {
        continue;
      }
      $state = '—';
      if ( ! empty( $context['end_date'] ) ) {
        $end_ts = strtotime( (string) $context['end_date'] . ' 23:59:59' );
        if ( $end_ts && $end_ts < current_time( 'timestamp' ) ) {
          $state = 'Formation terminée - ---';
        }
      }
      $rows[] = array(
        'registration' => $entry,
        'context' => $context,
        'state' => $state,
      );
    }
    return $rows;
  }


  private function build_hot_survey_pdf_pages( $registration, $context = array() ) {
    $context = is_array( $context ) ? $context : array();
    if ( empty( $context ) ) {
      $context = $this->get_hot_survey_context( $registration );
    }
    $profile = $this->get_company_profile_options();
    $branding = $this->get_branding_options();
    $org_name = ! empty( $profile['enterprise'] ) ? $profile['enterprise'] : ( ! empty( $branding['company_name'] ) ? $branding['company_name'] : 'ACDC Formation' );
    $header_bg = ! empty( $profile['header_footer_bg'] ) ? $profile['header_footer_bg'] : '#F3E3BF';
    $title_color = '#0C2D52';
    $muted = '#1E4777';
    $logo = $this->prepare_pdf_jpeg_image( $this->acdc_resolve_pdf_logo_url(), 42, 42 );
    $footer_logo = ! empty( $profile['footer_logo_url'] ) ? $this->prepare_pdf_jpeg_image( $profile['footer_logo_url'], 120, 40 ) : null;

    $pages = array();
    $page = array();
    $page[] = array( 'type' => 'rect', 'x' => 0, 'y' => 760, 'width' => 595, 'height' => 82, 'fill_color' => $header_bg );
    if ( $logo ) {
      $page[] = array(
        'type' => 'image', 'image_key' => $logo['key'], 'image_data' => $logo['data'], 'image_width' => $logo['width'], 'image_height' => $logo['height'], 'display_width' => $logo['display_width'], 'display_height' => $logo['display_height'], 'x' => 250, 'y' => 786,
      );
    }
    $page[] = array( 'text' => strtoupper( $org_name ), 'x' => 228, 'y' => 812, 'size' => 14, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    $page[] = array( 'text' => 'Résultats Enquêtes de satisfaction à chaud', 'x' => 130, 'y' => 782, 'size' => 16, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    $page[] = array( 'text' => $context['learner_name'], 'x' => 235, 'y' => 760, 'size' => 11, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    $page[] = array( 'text' => 'Formation : ' . $context['formation_title'], 'x' => 70, 'y' => 730, 'size' => 9.5, 'font' => 'Helvetica', 'color' => $title_color );
    $page[] = array( 'text' => 'Enquête : ' . $context['survey_title'], 'x' => 70, 'y' => 714, 'size' => 9.5, 'font' => 'Helvetica', 'color' => $title_color );
    $page[] = array( 'text' => 'Dates de formation : ' . $this->format_pdf_date( $context['start_date'] ) . ' au ' . $this->format_pdf_date( $context['end_date'] ), 'x' => 70, 'y' => 698, 'size' => 9.5, 'font' => 'Helvetica', 'color' => $muted );
    $page[] = array( 'text' => 'Format : ' . $context['format'], 'x' => 70, 'y' => 682, 'size' => 9.5, 'font' => 'Helvetica', 'color' => $muted );
    $page[] = array( 'text' => 'Durée : ' . $context['duration'], 'x' => 70, 'y' => 666, 'size' => 9.5, 'font' => 'Helvetica', 'color' => $muted );
    $page[] = array( 'text' => 'Statut : Complétée', 'x' => 70, 'y' => 650, 'size' => 9.5, 'font' => 'Helvetica-Bold', 'color' => '#35B37E' );
    $page[] = array( 'type' => 'rect', 'x' => 54, 'y' => 480, 'width' => 487, 'height' => 142, 'stroke_color' => '#B8E0CF', 'line_width' => 1.2 );
    $wrapped = $this->pdf_wrap_text( "Ce document récapitule les résultats de l’enquête de satisfaction à chaud associée à la formation. Il est destiné au suivi qualité et à la traçabilité documentaire. Les réponses détaillées peuvent être exportées au format Excel ou CSV depuis la plateforme de gestion.", 78 );
    $y = 596;
    foreach ( $wrapped as $line ) {
      $page[] = array( 'text' => $line, 'x' => 72, 'y' => $y, 'size' => 10, 'font' => 'Helvetica', 'color' => $title_color );
      $y -= 16;
    }
    $page[] = array( 'text' => 'Date de génération : ' . date_i18n( 'd/m/Y H:i' ), 'x' => 72, 'y' => 500, 'size' => 9, 'font' => 'Helvetica', 'color' => $muted );
    if ( $footer_logo ) {
      $page[] = array(
        'type' => 'image', 'image_key' => $footer_logo['key'], 'image_data' => $footer_logo['data'], 'image_width' => $footer_logo['width'], 'image_height' => $footer_logo['height'], 'display_width' => $footer_logo['display_width'], 'display_height' => $footer_logo['display_height'], 'x' => 225, 'y' => 40,
      );
    }
    $pages[] = $page;
    return $pages;
  }


  private function get_cold_survey_document_info( $registration, $context = array() ) {
    $info = array(
      'url'   => '',
      'path'  => '',
      'size'  => '',
      'label' => 'Résultats Enquêtes de satisfaction à froid',
    );
    if ( ! $registration ) {
      return $info;
    }
    $url  = isset( $registration->cold_survey_document_url ) ? trim( (string) $registration->cold_survey_document_url ) : '';
    $path = isset( $registration->cold_survey_document_path ) ? trim( (string) $registration->cold_survey_document_path ) : '';
    if ( '' === $path && '' !== $url ) {
      $path = $this->get_local_path_from_upload_url( $url );
    }
    if ( '' === $url ) {
      global $wpdb;
      $learner_name = isset( $context['learner_name'] ) ? (string) $context['learner_name'] : ( ! empty( $registration->learner_label ) ? (string) $registration->learner_label : '' );
      $formation_title = isset( $context['formation_title'] ) ? (string) $context['formation_title'] : ( ! empty( $registration->formation_title ) ? (string) $registration->formation_title : '' );
      $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->document_table} WHERE (document_type LIKE %s OR title LIKE %s OR title LIKE %s) ORDER BY id DESC LIMIT 25", '%froid%', '%froid%', '%satisfaction%' ) );
      foreach ( (array) $rows as $row ) {
        $haystack = trim( (string) $row->title . ' ' . (string) $row->document_type );
        $match = false;
        if ( '' !== $learner_name && false !== stripos( $haystack, $learner_name ) ) {
          $match = true;
        }
        if ( ! $match && '' !== $formation_title && false !== stripos( $haystack, $formation_title ) ) {
          $match = true;
        }
        if ( ! $match ) {
          continue;
        }
        $url  = ! empty( $row->file_url ) ? (string) $row->file_url : '';
        $path = ! empty( $row->file_path ) ? (string) $row->file_path : '';
        break;
      }
    }
    $info['url'] = $url;
    $info['path'] = $path;
    if ( '' !== $path && file_exists( $path ) ) {
      $size = filesize( $path );
      if ( $size ) {
        if ( $size >= 1048576 ) {
          $info['size'] = number_format_i18n( $size / 1048576, 1 ) . ' MB';
        } elseif ( $size >= 1024 ) {
          $info['size'] = number_format_i18n( $size / 1024, 0 ) . ' KB';
        } else {
          $info['size'] = $size . ' octets';
        }
      }
    }
    return $info;
  }


  private function get_cold_survey_display_file_name( $registration, $context = array(), $document = array() ) {
    $context = is_array( $context ) ? $context : array();
    $document = is_array( $document ) ? $document : array();
    if ( ! empty( $document['url'] ) ) {
      $path = wp_parse_url( (string) $document['url'], PHP_URL_PATH );
      if ( $path ) {
        return basename( (string) $path );
      }
    }
    $learner = isset( $context['learner_name'] ) ? (string) $context['learner_name'] : ( ! empty( $registration->learner_label ) ? (string) $registration->learner_label : 'apprenant' );
    $formation = isset( $context['formation_title'] ) ? (string) $context['formation_title'] : ( ! empty( $registration->formation_title ) ? (string) $registration->formation_title : 'formation' );
    return sanitize_file_name( 'enquete-a-froid-' . $learner . '-' . $formation . '.pdf' );
  }


  private function get_cold_survey_context( $registration ) {
    $context = $this->get_training_convocation_context( $registration );
    $survey = $this->get_cold_survey_for_formation( ! empty( $registration->formation_id ) ? (int) $registration->formation_id : 0 );
    $document = $this->get_cold_survey_document_info( $registration, $context );
    $context['survey'] = $survey;
    $context['survey_title'] = $survey && ! empty( $survey->title ) ? (string) $survey->title : 'Enquête de satisfaction à froid';
    $context['survey_source_label'] = 'Plateforme';
    $context['document'] = $document;
    $context['result_label'] = ( ! empty( $document['url'] ) || ! empty( $document['path'] ) ) ? 'Complétée' : '—';
    $context['completed_at_label'] = ! empty( $registration->updated_at ) ? mysql2date( 'j F Y', (string) $registration->updated_at, true ) : '';
    return $context;
  }


  private function get_cold_survey_for_formation( $formation_id ) {
    $formation_id = absint( $formation_id );
    if ( ! $formation_id ) {
      return null;
    }
    $surveys = $this->get_hot_surveys( '', true );
    foreach ( (array) $surveys as $survey ) {
      $ids = array();
      if ( ! empty( $survey->formation_ids ) ) {
        $decoded = json_decode( (string) $survey->formation_ids, true );
        if ( is_array( $decoded ) ) {
          $ids = array_values( array_filter( array_map( 'absint', $decoded ) ) );
        } else {
          $ids = array_values( array_filter( array_map( 'absint', explode( ',', (string) $survey->formation_ids ) ) ) );
        }
      }
      if ( in_array( $formation_id, $ids, true ) ) {
        return $survey;
      }
    }
    return null;
  }


  private function get_cold_survey_download_url( $registration, $mode = 'attachment' ) {
    if ( ! $registration || empty( $registration->id ) ) {
      return '';
    }
    $mode = 'inline' === $mode ? 'inline' : 'attachment';
    return wp_nonce_url( admin_url( 'admin-post.php?action=acdc_download_cold_survey_document&registration_id=' . (int) $registration->id . '&mode=' . rawurlencode( $mode ) ), 'acdc_download_cold_survey_document_' . (int) $registration->id );
  }


  private function get_cold_survey_rows( $search = '' ) {
    $search = trim( (string) $search );
    $items = $this->get_training_registrations( false );
    $rows = array();
    foreach ( (array) $items as $entry ) {
      if ( empty( $entry->formation_id ) ) {
        continue;
      }
      $context = $this->get_cold_survey_context( $entry );
      $formation_enabled = ! empty( $context['formation'] ) && ! empty( $context['formation']->cold_survey_enabled );
      if ( ! $formation_enabled && empty( $context['survey'] ) && empty( $context['document']['url'] ) && empty( $context['document']['path'] ) ) {
        continue;
      }
      $haystack = strtolower( implode( ' ', array_filter( array(
        (string) $entry->id,
        (string) $context['learner_name'],
        (string) $context['survey_title'],
        (string) $context['formation_title'],
      ) ) ) );
      if ( '' !== $search && false === strpos( $haystack, strtolower( $search ) ) ) {
        continue;
      }
      $state = '—';
      if ( ! empty( $context['end_date'] ) ) {
        $end_ts = strtotime( (string) $context['end_date'] . ' 23:59:59' );
        if ( $end_ts && $end_ts < current_time( 'timestamp' ) ) {
          $state = 'Formation terminée - ---';
        }
      }
      $rows[] = array(
        'registration' => $entry,
        'context' => $context,
        'state' => $state,
      );
    }
    return $rows;
  }


  private function build_cold_survey_pdf_pages( $registration, $context = array() ) {
    $context = is_array( $context ) ? $context : array();
    if ( empty( $context ) ) {
      $context = $this->get_cold_survey_context( $registration );
    }
    $profile = $this->get_company_profile_options();
    $branding = $this->get_branding_options();
    $org_name = ! empty( $profile['enterprise'] ) ? $profile['enterprise'] : ( ! empty( $branding['company_name'] ) ? $branding['company_name'] : 'ACDC Formation' );
    $header_bg = ! empty( $profile['header_footer_bg'] ) ? $profile['header_footer_bg'] : '#F3E3BF';
    $title_color = '#0C2D52';
    $muted = '#1E4777';
    $logo = $this->prepare_pdf_jpeg_image( $this->acdc_resolve_pdf_logo_url(), 42, 42 );
    $footer_logo = ! empty( $profile['footer_logo_url'] ) ? $this->prepare_pdf_jpeg_image( $profile['footer_logo_url'], 120, 40 ) : null;

    $pages = array();
    $page = array();
    $page[] = array( 'type' => 'rect', 'x' => 0, 'y' => 760, 'width' => 595, 'height' => 82, 'fill_color' => $header_bg );
    if ( $logo ) {
      $page[] = array(
        'type' => 'image', 'image_key' => $logo['key'], 'image_data' => $logo['data'], 'image_width' => $logo['width'], 'image_height' => $logo['height'], 'display_width' => $logo['display_width'], 'display_height' => $logo['display_height'], 'x' => 250, 'y' => 786,
      );
    }
    $page[] = array( 'text' => strtoupper( $org_name ), 'x' => 228, 'y' => 812, 'size' => 14, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    $page[] = array( 'text' => 'Résultats Enquêtes de satisfaction à froid', 'x' => 130, 'y' => 782, 'size' => 16, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    $page[] = array( 'text' => $context['learner_name'], 'x' => 235, 'y' => 760, 'size' => 11, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    $page[] = array( 'text' => 'Formation : ' . $context['formation_title'], 'x' => 70, 'y' => 730, 'size' => 9.5, 'font' => 'Helvetica', 'color' => $title_color );
    $page[] = array( 'text' => 'Enquête : ' . $context['survey_title'], 'x' => 70, 'y' => 714, 'size' => 9.5, 'font' => 'Helvetica', 'color' => $title_color );
    $page[] = array( 'text' => 'Dates de formation : ' . $this->format_pdf_date( $context['start_date'] ) . ' au ' . $this->format_pdf_date( $context['end_date'] ), 'x' => 70, 'y' => 698, 'size' => 9.5, 'font' => 'Helvetica', 'color' => $muted );
    $page[] = array( 'text' => 'Format : ' . $context['format'], 'x' => 70, 'y' => 682, 'size' => 9.5, 'font' => 'Helvetica', 'color' => $muted );
    $page[] = array( 'text' => 'Durée : ' . $context['duration'], 'x' => 70, 'y' => 666, 'size' => 9.5, 'font' => 'Helvetica', 'color' => $muted );
    $page[] = array( 'text' => 'Statut : Complétée', 'x' => 70, 'y' => 650, 'size' => 9.5, 'font' => 'Helvetica-Bold', 'color' => '#35B37E' );
    $page[] = array( 'type' => 'rect', 'x' => 54, 'y' => 480, 'width' => 487, 'height' => 142, 'stroke_color' => '#B8E0CF', 'line_width' => 1.2 );
    $wrapped = $this->pdf_wrap_text( "Ce document récapitule les résultats de l’enquête de satisfaction à froid associée à la formation. Il est destiné au suivi qualité et à la traçabilité documentaire. Les réponses détaillées peuvent être exportées au format Excel ou CSV depuis la plateforme de gestion.", 78 );
    $y = 596;
    foreach ( $wrapped as $line ) {
      $page[] = array( 'text' => $line, 'x' => 72, 'y' => $y, 'size' => 10, 'font' => 'Helvetica', 'color' => $title_color );
      $y -= 16;
    }
    $page[] = array( 'text' => 'Date de génération : ' . date_i18n( 'd/m/Y H:i' ), 'x' => 72, 'y' => 500, 'size' => 9, 'font' => 'Helvetica', 'color' => $muted );
    if ( $footer_logo ) {
      $page[] = array(
        'type' => 'image', 'image_key' => $footer_logo['key'], 'image_data' => $footer_logo['data'], 'image_width' => $footer_logo['width'], 'image_height' => $footer_logo['height'], 'display_width' => $footer_logo['display_width'], 'display_height' => $footer_logo['display_height'], 'x' => 225, 'y' => 40,
      );
    }
    $pages[] = $page;
    return $pages;
  }



  private function get_trainer_surveys_documents_scope() {
    $scope = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : '';
    return in_array( $scope, array( 'action', 'annual' ), true ) ? $scope : '';
  }


  private function get_trainer_surveys_documents_state() {
    $state = isset( $_GET['state'] ) ? sanitize_key( wp_unslash( $_GET['state'] ) ) : '';
    return in_array( $state, array( 'pending', 'relance' ), true ) ? $state : '';
  }


  private function get_company_surveys_documents_state() {
    $state = isset( $_GET['state'] ) ? sanitize_key( wp_unslash( $_GET['state'] ) ) : '';
    return in_array( $state, array( 'pending', 'relance' ), true ) ? $state : '';
  }


  private function get_funder_surveys_documents_state() {
    $state = isset( $_GET['state'] ) ? sanitize_key( wp_unslash( $_GET['state'] ) ) : '';
    return in_array( $state, array( 'pending', 'relance' ), true ) ? $state : '';
  }


  private function normalize_legacy_survey_collection( $records ) {
    if ( ! is_array( $records ) ) {
      return array();
    }

    $normalized = array();

    foreach ( $records as $record ) {
      if ( is_object( $record ) ) {
        $record = (array) $record;
      }

      if ( ! is_array( $record ) ) {
        continue;
      }

      if ( isset( $record['question_blocks'] ) && is_string( $record['question_blocks'] ) ) {
        $decoded_blocks = json_decode( $record['question_blocks'], true );
        if ( is_array( $decoded_blocks ) ) {
          $record['question_blocks'] = $decoded_blocks;
        }
      }

      if ( empty( $record['question_blocks'] ) || ! is_array( $record['question_blocks'] ) ) {
        $record['question_blocks'] = array();
      }

      if ( isset( $record['survey_settings'] ) && is_string( $record['survey_settings'] ) ) {
        $decoded_settings = json_decode( $record['survey_settings'], true );
        if ( is_array( $decoded_settings ) ) {
          $record['survey_settings'] = $decoded_settings;
        }
      }

      if ( empty( $record['survey_settings'] ) || ! is_array( $record['survey_settings'] ) ) {
        $record['survey_settings'] = array();
      }

      $record['title'] = isset( $record['title'] ) ? (string) $record['title'] : '';
      $record['description_text'] = isset( $record['description_text'] ) ? (string) $record['description_text'] : '';
      $record['alert_notation'] = isset( $record['alert_notation'] ) ? (string) $record['alert_notation'] : '';
      $record['survey_settings'] = is_array( $record['survey_settings'] ) ? $record['survey_settings'] : array();
      $record['is_model'] = ! empty( $record['is_model'] ) ? 1 : 0;
      $record['created_at'] = isset( $record['created_at'] ) ? (string) $record['created_at'] : current_time( 'mysql' );
      $record['updated_at'] = isset( $record['updated_at'] ) ? (string) $record['updated_at'] : current_time( 'mysql' );

      $normalized[] = $record;
    }

    return array_values( $normalized );
  }


  private function maybe_normalize_survey_option_records( $option_name, $survey_type ) {
    $records = get_option( $option_name, array() );
    $records = $this->normalize_legacy_survey_collection( $records );

    if ( empty( $records ) ) {
      return $records;
    }

    $changed = false;

    foreach ( $records as $index => $record ) {
      $title = isset( $record['title'] ) ? trim( (string) $record['title'] ) : '';
      $description = isset( $record['description_text'] ) ? (string) $record['description_text'] : '';

      if ( 'mid' === $survey_type ) {
        if ( false !== stripos( $title, 'à chaud' ) ) {
          $records[ $index ]['title'] = ! empty( $record['is_model'] ) ? 'Enquête de satisfaction intermédiaire (modèle)' : 'Enquête de satisfaction intermédiaire';
          $changed = true;
        }

        if ( false !== strpos( $description, "L'enquête de satisfaction à chaud" ) ) {
          $records[ $index ]['description_text'] = str_replace(
            "L'enquête de satisfaction à chaud",
            "L'enquête de satisfaction intermédiaire",
            $description
          );
          $changed = true;
        }
      } elseif ( 'hot' === $survey_type ) {
        if ( false !== stripos( $title, 'intermédiaire' ) ) {
          $records[ $index ]['title'] = ! empty( $record['is_model'] ) ? 'Enquête de satisfaction à chaud (modèle)' : 'Enquête de satisfaction à chaud';
          $changed = true;
        }

        if ( false !== strpos( $description, "L'enquête de satisfaction intermédiaire" ) ) {
          $records[ $index ]['description_text'] = str_replace(
            "L'enquête de satisfaction intermédiaire",
            "L'enquête de satisfaction à chaud",
            $description
          );
          $changed = true;
        }

        if ( false !== strpos( $records[ $index ]['description_text'], 'à mi-parcours' ) ) {
          $records[ $index ]['description_text'] = trim( preg_replace( '/\s+/', ' ', str_replace( 'à mi-parcours', '', $records[ $index ]['description_text'] ) ) );
          $changed = true;
        }
      }
    }

    if ( $changed ) {
      update_option( $option_name, array_values( $records ), false );
    }

    return $records;
  }




  private function maybe_seed_default_mid_surveys() {
    $surveys = $this->maybe_normalize_survey_option_records( 'acdc_of_mid_surveys', 'mid' );
    foreach ( $surveys as $survey ) {
      if ( ! empty( $survey['is_model'] ) ) {
        return;
      }
    }
    $now = current_time( 'mysql' );
    $surveys[] = array(
      'id'        => $this->get_next_mid_survey_id(),
      'title'      => 'Enquête de satisfaction à chaud (modèle)',
      'description_text' => "L'enquête de satisfaction intermédiaire est une étape importante de votre parcours de formation. C'est l'occasion de réfléchir sur vos progrès jusqu'à présent, de voir ce qui fonctionne bien et ce qui peut être amélioré. Merci de prendre le temps de remplir cette évaluation de manière honnête et détaillée.

A noter : Certaines des questions peuvent être évaluées sur une échelle de 1 à 5. Dans ce cas, 1 représente le niveau le plus bas et 5 le niveau le plus élevé.",
      'question_blocks' => $this->get_default_mid_survey_model_blocks(),
      'alert_notation'  => '3',
      'is_model'     => 1,
      'created_at'    => $now,
      'updated_at'    => $now,
    );
    update_option( 'acdc_of_mid_surveys', $surveys, false );
  }


  private function get_next_mid_survey_id() {
    $last = (int) get_option( 'acdc_of_mid_surveys_last_id', 0 );
    $last++;
    update_option( 'acdc_of_mid_surveys_last_id', $last, false );
    return $last;
  }


  private function get_default_mid_survey_model_blocks() {
    return array(
      array( 'block_type' => 'notation', 'question' => 'Notez votre niveau de motivation à suivre cette formation.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez votre niveau de satisfaction général à mi-parcours.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez la qualité des informations transmises en amont de la formation (description, programme, synopsis, etc.).', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez la cohérence de la formation et des objectifs atteints par rapport à vos attentes à mi-parcours.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez les moyens mis à disposition à mi-parcours.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez le rythme de la formation à mi-parcours.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez le contenu de la formation et la qualité des supports de formation à mi-parcours.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez les méthodes d’apprentissage utilisées à mi-parcours.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez l’équilibre entre la théorie et la pratique à mi-parcours.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez la qualité de l’intervenant (professionnalisme, dynamisme, maîtrise de son sujet, etc.).', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez l’animation et la pédagogie employées mise en place par l’intervenant.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez l’adaptation de l’intervenant par rapport à mes besoins à mi-parcours.', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Décrivez votre appréciation générale à mi-parcours.', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Décrivez ce que vous avez le plus apprécié dans cette formation à mi-parcours.', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Décrivez ce que vous avez le moins apprécié dans cette formation à mi-parcours.', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Commentaire à mi-parcours.', 'explanation' => '' ),
    );
  }


  private function get_mid_surveys( $search = '', $include_models = true ) {
    $this->maybe_seed_default_mid_surveys();
    $surveys = $this->maybe_normalize_survey_option_records( 'acdc_of_mid_surveys', 'mid' );
    if ( ! $include_models ) {
      $surveys = array_values( array_filter( $surveys, function( $survey ) {
        return empty( $survey['is_model'] );
      } ) );
    }
    if ( '' !== trim( (string) $search ) ) {
      $needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $search ) ) : strtolower( trim( (string) $search ) );
      $surveys = array_values( array_filter( $surveys, function( $survey ) use ( $needle ) {
        $hay = (string) ( $survey['title'] ?? '' ) . ' ' . (string) ( $survey['description_text'] ?? '' );
        $hay = function_exists( 'mb_strtolower' ) ? mb_strtolower( $hay ) : strtolower( $hay );
        return false !== strpos( $hay, $needle );
      } ) );
    }
    usort( $surveys, function( $a, $b ) {
      $amodel = ! empty( $a['is_model'] ) ? 1 : 0;
      $bmodel = ! empty( $b['is_model'] ) ? 1 : 0;
      if ( $amodel !== $bmodel ) {
        return $amodel <=> $bmodel;
      }
      return strcmp( (string) ( $b['updated_at'] ?? '' ), (string) ( $a['updated_at'] ?? '' ) );
    } );
    return $surveys;
  }


  private function get_mid_survey( $id ) {
    $surveys = $this->get_mid_surveys( '', true );
    foreach ( $surveys as $survey ) {
      if ( (int) ( $survey['id'] ?? 0 ) === (int) $id ) {
        return (object) $survey;
      }
    }
    return null;
  }


  private function save_mid_survey_record( $record ) {
    $surveys = $this->maybe_normalize_survey_option_records( 'acdc_of_mid_surveys', 'mid' );
    $saved = false;
    foreach ( $surveys as $index => $survey ) {
      if ( (int) ( $survey['id'] ?? 0 ) === (int) $record['id'] ) {
        $surveys[ $index ] = $record;
        $saved = true;
        break;
      }
    }
    if ( ! $saved ) {
      $surveys[] = $record;
    }
    update_option( 'acdc_of_mid_surveys', array_values( $surveys ), false );
  }


  private function delete_mid_survey_record( $id ) {
    $surveys = $this->maybe_normalize_survey_option_records( 'acdc_of_mid_surveys', 'mid' );
    $__acdc_avant = count( (array) $surveys );
    $surveys = array_values( array_filter( $surveys, function( $survey ) use ( $id ) {
      return (int) ( $survey['id'] ?? 0 ) !== (int) $id;
    } ) );
    update_option( 'acdc_of_mid_surveys', $surveys, false );
    /* ACDC 3.25.300 — La trace est posée ICI, dans la fonction qui retire
       réellement la fiche, et non chez ses appelants : elle sert tous les
       chemins d'un coup, et elle compare le nombre avant et après. Une fiche
       introuvable produit donc « error », pas une suppression imaginaire. */
    $this->log_action_event( 'suppression_mid_survey', 'mid_survey', (int) $id, count( (array) $surveys ) < $__acdc_avant ? 'success' : 'error', array( 'restants' => count( (array) $surveys ) ) );
  }



  private function should_render_survey_form_only( $action ) {
    return in_array( $action, array( 'new', 'edit', 'view' ), true );
  }


  private function get_survey_questionnaire_source_type( $survey_type ) {
    $map = array(
      'mid'     => 'mid_survey',
      'hot'     => 'hot_survey',
      'cold'    => 'cold_survey',
      'trainer' => 'trainer_survey',
      'company' => 'company_survey',
      'funder'  => 'funder_survey',
    );

    return isset( $map[ $survey_type ] ) ? $map[ $survey_type ] : '';
  }


  private function get_default_survey_settings_config( $survey_type, $scope = 'action' ) {
    $scope = 'annual' === $scope ? 'annual' : 'action';
    $config = array(
      'default_trigger_rule' => 'manual',
      'trigger_rules'        => array( 'manual' => 'Déclenchement manuel uniquement' ),
      'deadline_days'        => 15,
      'reminder_days'        => '5,10,15',
      'automation_rule_labels' => $this->get_survey_action_automation_rule_labels(),
      'automation_default_enabled' => $this->get_default_survey_action_automation_rules( $survey_type, $scope ),
    );

    switch ( $survey_type ) {
      case 'mid':
        $config['default_trigger_rule'] = 'midpoint';
        $config['trigger_rules'] = array(
          'midpoint'         => 'À mi-parcours de la formation',
          'hours_percent'    => 'Après un pourcentage des heures prévues',
          'days_after_start' => 'Après X jours depuis le début',
          'manual'           => 'Déclenchement manuel uniquement',
        );
        break;
      case 'hot':
        $config['default_trigger_rule'] = 'end_of_training';
        $config['trigger_rules'] = array(
          'end_of_training'  => 'À la fin réelle de la formation',
          'last_session'     => 'Après validation de la dernière séance',
          'custom_delay'     => 'Après un délai personnalisé',
          'manual'           => 'Déclenchement manuel uniquement',
        );
        break;
      case 'cold':
        $config['default_trigger_rule'] = 'plus_30_days';
        $config['trigger_rules'] = array(
          'plus_30_days'     => '30 jours après la fin réelle',
          'custom_delay'     => 'Après un délai personnalisé',
          'manual'           => 'Déclenchement manuel uniquement',
        );
        break;
      case 'trainer':
        if ( 'annual' === $scope ) {
          $config['default_trigger_rule'] = 'annual_campaign';
          $config['deadline_days'] = 20;
          $config['trigger_rules'] = array(
            'annual_campaign' => 'Campagne annuelle planifiée',
            'fixed_date'      => 'Date fixe annuelle',
            'manual'          => 'Déclenchement manuel uniquement',
          );
        } else {
          $config['default_trigger_rule'] = 'last_session';
          $config['deadline_days'] = 10;
          $config['trigger_rules'] = array(
            'end_of_training' => 'À la fin réelle de la formation',
            'last_session'    => 'Après validation de la dernière séance',
            'admin_close'     => 'Après clôture administrative',
            'manual'          => 'Déclenchement manuel uniquement',
          );
        }
        break;
      case 'company':
        $config['default_trigger_rule'] = 'admin_close';
        $config['trigger_rules'] = array(
          'end_of_training' => 'À la fin réelle de la formation',
          'last_session'    => 'Après validation de la dernière séance',
          'admin_close'     => 'Après clôture administrative',
          'manual'          => 'Déclenchement manuel uniquement',
        );
        break;
      case 'funder':
        $config['default_trigger_rule'] = 'manual';
        $config['trigger_rules'] = array(
          'end_of_training' => 'À la fin réelle du dossier',
          'admin_close'     => 'Après clôture administrative',
          'annual_campaign' => 'Campagne annuelle',
          'manual'          => 'Déclenchement manuel uniquement',
        );
        break;
    }

    return $config;
  }


  private function get_survey_action_automation_rule_labels() {
    return array(
      'complaint' => 'Créer une action si une réclamation est déclarée',
      'issue' => 'Créer une action si une difficulté ou insatisfaction est signalée',
      'reservation' => 'Créer une action si une réserve est exprimée',
      'improvement_need' => 'Créer une action si un besoin complémentaire est détecté',
      'low_score' => 'Créer une action si le score final est faible',
    );
  }



  private function get_default_survey_action_due_days( $survey_type, $scope = 'action' ) {
    $scope = 'annual' === $scope ? 'annual' : 'action';
    $defaults = array(
      'complaint' => 3,
      'issue' => 5,
      'reservation' => 5,
      'improvement_need' => 10,
      'low_score' => 7,
    );
    switch ( $survey_type ) {
      case 'mid':
        $defaults['complaint'] = 4;
        $defaults['issue'] = 5;
        $defaults['reservation'] = 5;
        $defaults['improvement_need'] = 10;
        $defaults['low_score'] = 7;
        break;
      case 'hot':
        $defaults['complaint'] = 2;
        $defaults['issue'] = 5;
        $defaults['reservation'] = 5;
        $defaults['improvement_need'] = 10;
        $defaults['low_score'] = 7;
        break;
      case 'cold':
        $defaults['complaint'] = 5;
        $defaults['issue'] = 7;
        $defaults['reservation'] = 7;
        $defaults['improvement_need'] = 15;
        $defaults['low_score'] = 10;
        break;
      case 'trainer':
        if ( 'annual' === $scope ) {
          $defaults['complaint'] = 7;
          $defaults['issue'] = 10;
          $defaults['reservation'] = 10;
          $defaults['improvement_need'] = 20;
          $defaults['low_score'] = 15;
        } else {
          $defaults['complaint'] = 5;
          $defaults['issue'] = 5;
          $defaults['reservation'] = 7;
          $defaults['improvement_need'] = 15;
          $defaults['low_score'] = 10;
        }
        break;
      case 'company':
        $defaults['complaint'] = 3;
        $defaults['issue'] = 5;
        $defaults['reservation'] = 5;
        $defaults['improvement_need'] = 10;
        $defaults['low_score'] = 7;
        break;
      case 'funder':
        if ( 'annual' === $scope ) {
          $defaults['complaint'] = 10;
          $defaults['issue'] = 12;
          $defaults['reservation'] = 12;
          $defaults['improvement_need'] = 20;
          $defaults['low_score'] = 15;
        } else {
          $defaults['complaint'] = 5;
          $defaults['issue'] = 7;
          $defaults['reservation'] = 7;
          $defaults['improvement_need'] = 15;
          $defaults['low_score'] = 10;
        }
        break;
    }
    return $defaults;
  }


  private function normalize_survey_action_due_days( $value, $survey_type, $scope = 'action' ) {
    $defaults = $this->get_default_survey_action_due_days( $survey_type, $scope );
    if ( is_string( $value ) ) {
      $decoded = json_decode( $value, true );
      if ( is_array( $decoded ) ) {
        $value = $decoded;
      } else {
        $value = array();
      }
    }
    $value = is_array( $value ) ? $value : array();
    $normalized = array();
    foreach ( $defaults as $rule_key => $rule_days ) {
      $normalized[ $rule_key ] = isset( $value[ $rule_key ] ) ? max( 1, absint( $value[ $rule_key ] ) ) : absint( $rule_days );
    }
    return $normalized;
  }


  private function get_questionnaire_action_target_due_days( $settings, $rule_key ) {
    $settings = is_array( $settings ) ? $settings : array();
    $rule_key = sanitize_key( (string) $rule_key );
    $map = isset( $settings['action_due_days'] ) && is_array( $settings['action_due_days'] ) ? $settings['action_due_days'] : array();
    if ( isset( $map[ $rule_key ] ) ) {
      return max( 1, absint( $map[ $rule_key ] ) );
    }
    if ( isset( $settings['action_default_due_days'] ) ) {
      return max( 1, absint( $settings['action_default_due_days'] ) );
    }
    return 7;
  }


  private function calculate_questionnaire_action_due_at_from_days( $days, $base_time = '' ) {
    $days = max( 1, absint( $days ) );
    $base_time = is_string( $base_time ) && '' !== $base_time ? $base_time : $this->now_mysql();
    $timestamp = strtotime( $base_time );
    if ( ! $timestamp ) {
      $timestamp = current_time( 'timestamp' );
    }
    $timestamp = strtotime( '+' . $days . ' days', $timestamp );
    return gmdate( 'Y-m-d H:i:s', $timestamp + (int) ( get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) );
  }


  private function format_questionnaire_action_due_at_for_input( $value ) {
    if ( empty( $value ) ) {
      return '';
    }
    $timestamp = strtotime( (string) $value );
    if ( ! $timestamp ) {
      return '';
    }
    return date_i18n( 'Y-m-d\TH:i', $timestamp );
  }


  private function normalize_questionnaire_action_due_at_input( $value, $fallback_days = 0 ) {
    $value = sanitize_text_field( (string) $value );
    if ( '' !== $value ) {
      $timestamp = strtotime( $value );
      if ( $timestamp ) {
        return gmdate( 'Y-m-d H:i:s', $timestamp + (int) ( get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) );
      }
    }
    $fallback_days = absint( $fallback_days );
    return $fallback_days > 0 ? $this->calculate_questionnaire_action_due_at_from_days( $fallback_days ) : null;
  }


  private function get_questionnaire_action_due_state( $action ) {
    $result = array(
      'label' => '—',
      'is_late' => false,
      'state_key' => 'none',
      'badge_label' => 'Sans échéance',
      'class' => 'acdc-urgency-badge acdc-urgency-badge-none',
    );
    if ( empty( $action->due_at ) ) {
      return $result;
    }
    $due_ts = strtotime( (string) $action->due_at );
    if ( ! $due_ts ) {
      return $result;
    }
    $result['label'] = mysql2date( 'j F Y H:i', (string) $action->due_at );
    $status = ! empty( $action->action_status ) ? sanitize_key( (string) $action->action_status ) : 'ouverte';
    if ( in_array( $status, array( 'traitee', 'annulee' ), true ) ) {
      $result['state_key'] = 'closed';
      $result['badge_label'] = 'Clôturée';
      $result['class'] = 'acdc-urgency-badge acdc-urgency-badge-closed';
      return $result;
    }
    $now_ts  = current_time( 'timestamp' );
    $today   = wp_date( 'Y-m-d', $now_ts );
    $horizon = $now_ts + ( 3 * DAY_IN_SECONDS );
    if ( $due_ts < $now_ts ) {
      $days = max( 1, (int) ceil( ( $now_ts - $due_ts ) / DAY_IN_SECONDS ) );
      $result['label'] .= ' · En retard (' . $days . ' j)';
      $result['is_late'] = true;
      $result['state_key'] = 'en_retard';
      $result['badge_label'] = 'En retard';
      $result['class'] = 'acdc-urgency-badge acdc-urgency-badge-overdue';
      return $result;
    }

    $due_date = wp_date( 'Y-m-d', $due_ts );
    if ( $due_date === $today ) {
      $result['label'] .= ' · Échéance aujourd’hui';
      $result['state_key'] = 'aujourd_hui';
      $result['badge_label'] = 'Échéance aujourd’hui';
      $result['class'] = 'acdc-urgency-badge acdc-urgency-badge-today';
      return $result;
    }

    if ( $due_ts <= $horizon ) {
      $days = max( 1, (int) ceil( ( $due_ts - $now_ts ) / DAY_IN_SECONDS ) );
      $result['label'] .= ' · Échéance proche (J-' . $days . ')';
      $result['state_key'] = 'echeance_proche';
      $result['badge_label'] = 'Échéance proche';
      $result['class'] = 'acdc-urgency-badge acdc-urgency-badge-soon';
      return $result;
    }

    $days = (int) floor( ( $due_ts - $now_ts ) / DAY_IN_SECONDS );
    $result['label'] .= ' · Échéance J-' . $days;
    $result['state_key'] = 'a_venir';
    $result['badge_label'] = 'À venir';
    $result['class'] = 'acdc-urgency-badge acdc-urgency-badge-upcoming';
    return $result;
  }



  private function get_survey_action_assignment_mode_labels() {
    return array(
      'current_user' => 'Utilisateur courant',
      'fixed_user'   => 'Responsable fixe',
      'by_formation' => 'Responsable selon la formation',
      'by_trainer'   => 'Responsable selon le formateur',
      'by_company'   => 'Responsable selon l’entreprise',
      'contextual'   => 'Priorité intelligente (entreprise > formateur > formation > responsable fixe)',
    );
  }


  private function get_default_survey_action_assignment_mode( $survey_type, $scope = 'action' ) {
    $scope = 'annual' === $scope ? 'annual' : 'action';
    switch ( $survey_type ) {
      case 'mid':
      case 'hot':
      case 'cold':
        return 'by_formation';
      case 'trainer':
        return 'by_trainer';
      case 'company':
        return 'by_company';
      case 'funder':
        return 'annual' === $scope ? 'contextual' : 'fixed_user';
    }
    return 'fixed_user';
  }


  private function normalize_survey_action_assignment_map( $value ) {
    $normalized = array();
    if ( is_string( $value ) ) {
      $decoded = json_decode( $value, true );
      if ( is_array( $decoded ) ) {
        $value = $decoded;
      } else {
        $value = array();
      }
    }
    if ( ! is_array( $value ) ) {
      return $normalized;
    }
    foreach ( $value as $entity_id => $user_id ) {
      $entity_id = absint( $entity_id );
      $user_id   = absint( $user_id );
      if ( $entity_id > 0 && $user_id > 0 ) {
        $normalized[ $entity_id ] = $user_id;
      }
    }
    return $normalized;
  }


  private function get_survey_assignment_context_entity_labels( $type ) {
    $rows = array();
    switch ( $type ) {
      case 'formation':
        foreach ( (array) $this->get_formations( array( 'archived' => false ) ) as $formation ) {
          if ( ! empty( $formation->id ) ) {
            /* ACDC 3.25.277 — Le titre seul propose deux fois la même ligne
               quand la formation existe en présentiel et en distanciel : on
               affecte alors un questionnaire à une modalité au hasard. */
            $rows[ (int) $formation->id ] = $this->acdc_formation_choice_label( $formation );
          }
        }
        break;
      case 'trainer':
        foreach ( (array) $this->get_trainers() as $trainer ) {
          if ( ! empty( $trainer->id ) ) {
            $rows[ (int) $trainer->id ] = trim( (string) $trainer->first_name . ' ' . (string) $trainer->last_name );
          }
        }
        break;
      case 'company':
        foreach ( (array) $this->get_companies() as $company ) {
          if ( ! empty( $company->id ) ) {
            $rows[ (int) $company->id ] = (string) $company->name;
          }
        }
        break;
    }
    asort( $rows );
    return $rows;
  }


  private function maybe_find_portal_user_id_for_trainer( $trainer_id ) {
    $trainer_id = absint( $trainer_id );
    if ( ! $trainer_id ) {
      return 0;
    }
    $trainer = $this->get_trainer( $trainer_id );
    if ( ! $trainer || empty( $trainer->email ) ) {
      return 0;
    }
    $user = get_user_by( 'email', (string) $trainer->email );
    return $user ? (int) $user->ID : 0;
  }


  private function resolve_questionnaire_action_assigned_user_id( $session, $participant, $source, $settings, $suggestion = array() ) {
    $settings = is_array( $settings ) ? $settings : array();
    $mode = isset( $settings['action_assignment_mode'] ) ? sanitize_key( (string) $settings['action_assignment_mode'] ) : 'fixed_user';
    $labels = $this->get_survey_action_assignment_mode_labels();
    if ( ! isset( $labels[ $mode ] ) ) {
      $mode = 'fixed_user';
    }
    $formation_map = $this->normalize_survey_action_assignment_map( $settings['action_assignment_by_formation'] ?? array() );
    $trainer_map   = $this->normalize_survey_action_assignment_map( $settings['action_assignment_by_trainer'] ?? array() );
    $company_map   = $this->normalize_survey_action_assignment_map( $settings['action_assignment_by_company'] ?? array() );
    $fixed_user    = absint( $settings['action_assignment_fixed_user_id'] ?? 0 );
    $fallback_user = absint( $settings['action_assignment_fallback_user_id'] ?? 0 );

    $formation_id = ! empty( $session->formation_id ) ? absint( $session->formation_id ) : 0;
    $trainer_id   = ! empty( $session->formateur_id ) ? absint( $session->formateur_id ) : 0;
    $company_id   = ! empty( $session->company_id ) ? absint( $session->company_id ) : 0;

    $resolve_by_formation = function() use ( $formation_id, $formation_map ) {
      return ( $formation_id && ! empty( $formation_map[ $formation_id ] ) ) ? absint( $formation_map[ $formation_id ] ) : 0;
    };
    $resolve_by_trainer = function() use ( $trainer_id, $trainer_map ) {
      if ( $trainer_id && ! empty( $trainer_map[ $trainer_id ] ) ) {
        return absint( $trainer_map[ $trainer_id ] );
      }
      return $this->maybe_find_portal_user_id_for_trainer( $trainer_id );
    };
    $resolve_by_company = function() use ( $company_id, $company_map ) {
      return ( $company_id && ! empty( $company_map[ $company_id ] ) ) ? absint( $company_map[ $company_id ] ) : 0;
    };

    $assigned_to = 0;
    switch ( $mode ) {
      case 'current_user':
        $assigned_to = get_current_user_id();
        break;
      case 'fixed_user':
        $assigned_to = $fixed_user;
        break;
      case 'by_formation':
        $assigned_to = $resolve_by_formation();
        break;
      case 'by_trainer':
        $assigned_to = $resolve_by_trainer();
        break;
      case 'by_company':
        $assigned_to = $resolve_by_company();
        break;
      case 'contextual':
        $assigned_to = $resolve_by_company();
        if ( ! $assigned_to ) { $assigned_to = $resolve_by_trainer(); }
        if ( ! $assigned_to ) { $assigned_to = $resolve_by_formation(); }
        if ( ! $assigned_to ) { $assigned_to = $fixed_user; }
        break;
    }

    if ( ! $assigned_to ) {
      $assigned_to = $fallback_user;
    }
    if ( ! $assigned_to ) {
      $assigned_to = get_current_user_id();
    }
    return absint( $assigned_to );
  }


  private function get_default_survey_action_automation_rules( $survey_type, $scope = 'action' ) {
    $scope = 'annual' === $scope ? 'annual' : 'action';
    $enabled = array();
    switch ( $survey_type ) {
      case 'mid':
        $enabled = array( 'issue', 'reservation', 'low_score' );
        break;
      case 'hot':
        $enabled = array( 'complaint', 'issue', 'reservation', 'improvement_need', 'low_score' );
        break;
      case 'cold':
        $enabled = array( 'issue', 'improvement_need', 'low_score' );
        break;
      case 'trainer':
        $enabled = 'annual' === $scope
          ? array( 'issue', 'improvement_need', 'low_score' )
          : array( 'issue', 'reservation', 'improvement_need', 'low_score' );
        break;
      case 'company':
        $enabled = array( 'complaint', 'reservation', 'improvement_need', 'low_score' );
        break;
      case 'funder':
        $enabled = 'annual' === $scope
          ? array( 'improvement_need', 'low_score' )
          : array( 'complaint', 'reservation', 'improvement_need', 'low_score' );
        break;
    }
    return array_values( array_unique( array_filter( $enabled, 'is_string' ) ) );
  }


  private function normalize_survey_action_automation_rules( $settings, $survey_type, $scope = 'action' ) {
    $labels = $this->get_survey_action_automation_rule_labels();
    $defaults = $this->get_default_survey_action_automation_rules( $survey_type, $scope );
    $rules = array();
    if ( isset( $settings['auto_action_rules'] ) ) {
      $raw_rules = $settings['auto_action_rules'];
      if ( is_string( $raw_rules ) ) {
        $decoded = json_decode( $raw_rules, true );
        if ( is_array( $decoded ) ) {
          $raw_rules = $decoded;
        } else {
          $raw_rules = preg_split( '/\s*,\s*/', $raw_rules );
        }
      }
      if ( is_array( $raw_rules ) ) {
        foreach ( $raw_rules as $rule_key => $rule_value ) {
          if ( is_int( $rule_key ) ) {
            $rule = sanitize_key( (string) $rule_value );
            if ( isset( $labels[ $rule ] ) ) {
              $rules[] = $rule;
            }
          } else {
            $rule = sanitize_key( (string) $rule_key );
            if ( isset( $labels[ $rule ] ) && ! empty( $rule_value ) ) {
              $rules[] = $rule;
            }
          }
        }
      }
    }
    if ( empty( $rules ) ) {
      $rules = $defaults;
    }
    $rules = array_values( array_unique( array_filter( $rules, static function( $rule ) use ( $labels ) {
      return isset( $labels[ $rule ] );
    } ) ) );
    return $rules;
  }


  private function get_normalized_survey_settings( $survey, $survey_type, $scope = 'action' ) {
    $config = $this->get_default_survey_settings_config( $survey_type, $scope );
    $settings = array();

    if ( $survey ) {
      if ( is_object( $survey ) && isset( $survey->survey_settings ) ) {
        $settings = $survey->survey_settings;
      } elseif ( is_array( $survey ) && isset( $survey['survey_settings'] ) ) {
        $settings = $survey['survey_settings'];
      }
    }

    if ( is_string( $settings ) ) {
      $decoded = json_decode( $settings, true );
      if ( is_array( $decoded ) ) {
        $settings = $decoded;
      }
    }

    if ( ! is_array( $settings ) ) {
      $settings = array();
    }

    $normalized = array(
      'is_active'           => empty( $settings['is_active'] ) ? '1' : '1',
      'trigger_mode'        => isset( $settings['trigger_mode'] ) && in_array( $settings['trigger_mode'], array( 'manual', 'automatic', 'manual_auto' ), true ) ? $settings['trigger_mode'] : 'manual_auto',
      'trigger_rule'        => isset( $settings['trigger_rule'] ) && isset( $config['trigger_rules'][ $settings['trigger_rule'] ] ) ? $settings['trigger_rule'] : $config['default_trigger_rule'],
      'deadline_days'       => isset( $settings['deadline_days'] ) ? max( 1, absint( $settings['deadline_days'] ) ) : (int) $config['deadline_days'],
      'reminder_days'       => isset( $settings['reminder_days'] ) ? sanitize_text_field( (string) $settings['reminder_days'] ) : (string) $config['reminder_days'],
      'internal_comment'    => isset( $settings['internal_comment'] ) ? (string) $settings['internal_comment'] : '',
      'auto_create_actions' => empty( $settings['auto_create_actions'] ) ? '0' : '1',
      'auto_action_rules'   => $this->normalize_survey_action_automation_rules( $settings, $survey_type, $scope ),
      'action_due_days' => $this->normalize_survey_action_due_days( $settings['action_due_days'] ?? array(), $survey_type, $scope ),
      'action_default_due_days' => isset( $settings['action_default_due_days'] ) ? max( 1, absint( $settings['action_default_due_days'] ) ) : 7,
      'action_assignment_mode' => ( isset( $settings['action_assignment_mode'] ) && isset( $this->get_survey_action_assignment_mode_labels()[ sanitize_key( (string) $settings['action_assignment_mode'] ) ] ) ) ? sanitize_key( (string) $settings['action_assignment_mode'] ) : $this->get_default_survey_action_assignment_mode( $survey_type, $scope ),
      'action_assignment_fixed_user_id' => isset( $settings['action_assignment_fixed_user_id'] ) ? absint( $settings['action_assignment_fixed_user_id'] ) : 0,
      'action_assignment_fallback_user_id' => isset( $settings['action_assignment_fallback_user_id'] ) ? absint( $settings['action_assignment_fallback_user_id'] ) : 0,
      'action_assignment_by_formation' => $this->normalize_survey_action_assignment_map( $settings['action_assignment_by_formation'] ?? array() ),
      'action_assignment_by_trainer' => $this->normalize_survey_action_assignment_map( $settings['action_assignment_by_trainer'] ?? array() ),
      'action_assignment_by_company' => $this->normalize_survey_action_assignment_map( $settings['action_assignment_by_company'] ?? array() ),
    );

    return $normalized;
  }


  private function sanitize_survey_settings_input( $settings, $survey_type, $scope = 'action' ) {
    $config = $this->get_default_survey_settings_config( $survey_type, $scope );
    $settings = is_array( $settings ) ? $settings : array();

    $trigger_mode = isset( $settings['trigger_mode'] ) ? sanitize_key( $settings['trigger_mode'] ) : 'manual_auto';
    if ( ! in_array( $trigger_mode, array( 'manual', 'automatic', 'manual_auto' ), true ) ) {
      $trigger_mode = 'manual_auto';
    }

    $trigger_rule = isset( $settings['trigger_rule'] ) ? sanitize_key( $settings['trigger_rule'] ) : $config['default_trigger_rule'];
    if ( ! isset( $config['trigger_rules'][ $trigger_rule ] ) ) {
      $trigger_rule = $config['default_trigger_rule'];
    }

    $deadline_days = isset( $settings['deadline_days'] ) ? max( 1, absint( $settings['deadline_days'] ) ) : (int) $config['deadline_days'];
    $reminder_days = isset( $settings['reminder_days'] ) ? sanitize_text_field( (string) $settings['reminder_days'] ) : (string) $config['reminder_days'];

    return array(
      'is_active'           => empty( $settings['is_active'] ) ? '0' : '1',
      'trigger_mode'        => $trigger_mode,
      'trigger_rule'        => $trigger_rule,
      'deadline_days'       => $deadline_days,
      'reminder_days'       => $reminder_days,
      'internal_comment'    => isset( $settings['internal_comment'] ) ? sanitize_textarea_field( $settings['internal_comment'] ) : '',
      'auto_create_actions' => empty( $settings['auto_create_actions'] ) ? '0' : '1',
      'auto_action_rules'   => $this->normalize_survey_action_automation_rules( $settings, $survey_type, $scope ),
      'action_due_days' => $this->normalize_survey_action_due_days( $settings['action_due_days'] ?? array(), $survey_type, $scope ),
      'action_default_due_days' => isset( $settings['action_default_due_days'] ) ? max( 1, absint( $settings['action_default_due_days'] ) ) : 7,
      'action_assignment_mode' => ( isset( $settings['action_assignment_mode'] ) && isset( $this->get_survey_action_assignment_mode_labels()[ sanitize_key( (string) $settings['action_assignment_mode'] ) ] ) ) ? sanitize_key( (string) $settings['action_assignment_mode'] ) : $this->get_default_survey_action_assignment_mode( $survey_type, $scope ),
      'action_assignment_fixed_user_id' => isset( $settings['action_assignment_fixed_user_id'] ) ? absint( $settings['action_assignment_fixed_user_id'] ) : 0,
      'action_assignment_fallback_user_id' => isset( $settings['action_assignment_fallback_user_id'] ) ? absint( $settings['action_assignment_fallback_user_id'] ) : 0,
      'action_assignment_by_formation' => $this->normalize_survey_action_assignment_map( $settings['action_assignment_by_formation'] ?? array() ),
      'action_assignment_by_trainer' => $this->normalize_survey_action_assignment_map( $settings['action_assignment_by_trainer'] ?? array() ),
      'action_assignment_by_company' => $this->normalize_survey_action_assignment_map( $settings['action_assignment_by_company'] ?? array() ),
    );
  }


  private function parse_survey_reminder_days_string( $value ) {
    $value = is_string( $value ) ? $value : '';
    $parts = preg_split( '/\s*,\s*/', $value );
    $days = array();
    foreach ( (array) $parts as $part ) {
      $day = absint( $part );
      if ( $day > 0 ) {
        $days[] = $day;
      }
    }
    $days = array_values( array_unique( $days ) );
    sort( $days );
    return $days;
  }


  private function get_survey_settings_from_source_data( $source ) {
    if ( empty( $source ) || empty( $source['type'] ) || empty( $source['record'] ) || ! $this->is_survey_questionnaire_source_type( (string) $source['type'] ) ) {
      return array();
    }

    $map = array(
      'mid_survey'     => 'mid',
      'hot_survey'     => 'hot',
      'cold_survey'    => 'cold',
      'trainer_survey' => 'trainer',
      'company_survey' => 'company',
      'funder_survey'  => 'funder',
    );

    $survey_type = isset( $map[ (string) $source['type'] ] ) ? $map[ (string) $source['type'] ] : '';
    if ( '' === $survey_type ) {
      return array();
    }

    $scope = 'action';
    if ( 'trainer' === $survey_type && ! empty( $source['record']->scope ) && 'annual' === sanitize_key( (string) $source['record']->scope ) ) {
      $scope = 'annual';
    }

    $settings = $this->get_normalized_survey_settings( $source['record'], $survey_type, $scope );
    $settings['scope'] = $scope;
    $settings['survey_type'] = $survey_type;
    $settings['reminder_days_array'] = $this->parse_survey_reminder_days_string( isset( $settings['reminder_days'] ) ? (string) $settings['reminder_days'] : '' );

    return $settings;
  }


  private function get_survey_questionnaire_summary( $survey_type, $survey_id ) {
    $summary = array(
      'source_type'   => $this->get_survey_questionnaire_source_type( $survey_type ),
      'source_id'     => absint( $survey_id ),
      'total'         => 0,
      'participants'  => 0,
      'finished'      => 0,
      'active'        => 0,
    );

    if ( empty( $summary['source_type'] ) || empty( $summary['source_id'] ) ) {
      return $summary;
    }

    $stats = $this->get_questionnaire_session_stats(
      array(
        'source_type' => $summary['source_type'],
        'source_id'   => $summary['source_id'],
      )
    );

    if ( is_array( $stats ) ) {
      $summary = array_merge( $summary, $stats );
    }

    return $summary;
  }


  private function maybe_seed_default_hot_surveys() {
    $surveys = $this->maybe_normalize_survey_option_records( 'acdc_of_hot_surveys', 'hot' );
    foreach ( $surveys as $survey ) {
      if ( ! empty( $survey['is_model'] ) ) {
        return;
      }
    }
    $now = current_time( 'mysql' );
    $surveys[] = array(
      'id'        => $this->get_next_hot_survey_id(),
      'title'      => 'Enquête de satisfaction intermédiaire (modèle)',
      'description_text' => "L'enquête de satisfaction intermédiaire est une étape importante de votre parcours de formation. C'est l'occasion de réfléchir sur vos progrès jusqu'à présent, de voir ce qui fonctionne bien et ce qui peut être amélioré. Merci de prendre le temps de remplir cette évaluation de manière honnête et détaillée.

A noter : Certaines des questions peuvent être évaluées sur une échelle de 1 à 5. Dans ce cas, 1 représente le niveau le plus bas et 5 le niveau le plus élevé.",
      'question_blocks' => $this->get_default_hot_survey_model_blocks(),
      'alert_notation'  => '3',
      'is_model'     => 1,
      'created_at'    => $now,
      'updated_at'    => $now,
    );
    update_option( 'acdc_of_hot_surveys', $surveys, false );
  }


  private function get_next_hot_survey_id() {
    $last = (int) get_option( 'acdc_of_hot_surveys_last_id', 0 );
    $last++;
    update_option( 'acdc_of_hot_surveys_last_id', $last, false );
    return $last;
  }


  private function get_default_hot_survey_model_blocks() {
    return array(
      array( 'block_type' => 'notation', 'question' => 'Notez votre niveau de motivation à suivre cette formation.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez votre niveau de satisfaction général.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez la cohérence de la formation et des objectifs atteints par rapport à vos attentes.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez la qualité des informations transmises en amont de la formation (description, programme, synopsis, etc.).', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez la qualité du suivi lors de votre formation.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez les moyens mis à disposition.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Indiquez dans quelle mesure le rythme de la formation était adapté à vos besoins.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Indiquez dans quelle mesure la durée de la formation était adaptée à vos besoins.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez le contenu de la formation et la qualité des supports de formation.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez les méthodes d’apprentissage utilisées.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez l’équilibre entre la théorie et la pratique.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez la qualité de l’intervenant principal (professionnalisme, dynamisme, maîtrise de son sujet, etc.).', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez l’animation et la pédagogie employées par l’intervenant.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez l’adaptation de l’intervenant par rapport à mes besoins.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez le niveau de connaissances et de compétences acquises par rapport à mes objectifs.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez la cohérence entre les compétences acquises et les compétences nécessaires dans mon activité professionnelle.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez votre niveau de recommandation de cette formation.', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Quelle est votre appréciation générale ?', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Qu’avez-vous le plus apprécié dans cette formation ?', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Qu’avez-vous le moins apprécié dans cette formation ?', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Quelles sont vos recommandations d’améliorations ?', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Quels sont vos commentaires éventuels ?', 'explanation' => '' ),
    );
  }


  private function get_hot_surveys( $search = '', $include_models = true ) {
    $this->maybe_seed_default_hot_surveys();
    $surveys = $this->maybe_normalize_survey_option_records( 'acdc_of_hot_surveys', 'hot' );
    if ( ! $include_models ) {
      $surveys = array_values( array_filter( $surveys, function( $survey ) {
        return empty( $survey['is_model'] );
      } ) );
    }
    if ( '' !== trim( (string) $search ) ) {
      $needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $search ) ) : strtolower( trim( (string) $search ) );
      $surveys = array_values( array_filter( $surveys, function( $survey ) use ( $needle ) {
        $hay = (string) ( $survey['title'] ?? '' ) . ' ' . (string) ( $survey['description_text'] ?? '' );
        $hay = function_exists( 'mb_strtolower' ) ? mb_strtolower( $hay ) : strtolower( $hay );
        return false !== strpos( $hay, $needle );
      } ) );
    }
    usort( $surveys, function( $a, $b ) {
      $amodel = ! empty( $a['is_model'] ) ? 1 : 0;
      $bmodel = ! empty( $b['is_model'] ) ? 1 : 0;
      if ( $amodel !== $bmodel ) {
        return $bmodel <=> $amodel;
      }
      return strcmp( (string) ( $b['updated_at'] ?? '' ), (string) ( $a['updated_at'] ?? '' ) );
    } );
    return $surveys;
  }


  private function get_hot_survey( $id ) {
    $surveys = $this->get_hot_surveys( '', true );
    foreach ( $surveys as $survey ) {
      if ( (int) ( $survey['id'] ?? 0 ) === (int) $id ) {
        return (object) $survey;
      }
    }
    return null;
  }


  private function save_hot_survey_record( $record ) {
    $surveys = $this->maybe_normalize_survey_option_records( 'acdc_of_hot_surveys', 'hot' );
    $saved = false;
    foreach ( $surveys as $index => $survey ) {
      if ( (int) ( $survey['id'] ?? 0 ) === (int) $record['id'] ) {
        $surveys[ $index ] = $record;
        $saved = true;
        break;
      }
    }
    if ( ! $saved ) {
      $surveys[] = $record;
    }
    update_option( 'acdc_of_hot_surveys', array_values( $surveys ), false );
  }


  private function delete_hot_survey_record( $id ) {
    $surveys = $this->maybe_normalize_survey_option_records( 'acdc_of_hot_surveys', 'hot' );
    $__acdc_avant = count( (array) $surveys );
    $surveys = array_values( array_filter( $surveys, function( $survey ) use ( $id ) {
      return (int) ( $survey['id'] ?? 0 ) !== (int) $id;
    } ) );
    update_option( 'acdc_of_hot_surveys', $surveys, false );
    /* ACDC 3.25.300 — La trace est posée ICI, dans la fonction qui retire
       réellement la fiche, et non chez ses appelants : elle sert tous les
       chemins d'un coup, et elle compare le nombre avant et après. Une fiche
       introuvable produit donc « error », pas une suppression imaginaire. */
    $this->log_action_event( 'suppression_hot_survey', 'hot_survey', (int) $id, count( (array) $surveys ) < $__acdc_avant ? 'success' : 'error', array( 'restants' => count( (array) $surveys ) ) );
  }


  private function maybe_seed_default_cold_surveys() {
    $surveys = $this->normalize_legacy_survey_collection( get_option( 'acdc_of_cold_surveys', array() ) );
    foreach ( $surveys as $survey ) {
      if ( ! empty( $survey['is_model'] ) ) {
        return;
      }
    }
    $now = current_time( 'mysql' );
    $surveys[] = array(
      'id'        => $this->get_next_cold_survey_id(),
      'title'      => 'Enquête de satisfaction à froid (modèle)',
      'description_text' => "L'enquête de satisfaction à froid est essentielle pour recueillir vos retours après la formation. Vos retours nous aident à évaluer l'impact de nos formations à plus long terme et à identifier les domaines où des améliorations sont nécessaires. Prenez quelques instants pour remplir les questions et partager votre expérience.

À noter : Certaines des questions peuvent être évaluées sur une échelle de 1 à 5. Dans ce cas, 1 représente le niveau le plus bas et 5 le niveau le plus élevé.",
      'question_blocks' => $this->get_default_cold_survey_model_blocks(),
      'alert_notation'  => '3',
      'is_model'     => 1,
      'created_at'    => $now,
      'updated_at'    => $now,
    );
    update_option( 'acdc_of_cold_surveys', $surveys, false );
  }


  private function get_next_cold_survey_id() {
    $last = (int) get_option( 'acdc_of_cold_surveys_last_id', 0 );
    $last++;
    update_option( 'acdc_of_cold_surveys_last_id', $last, false );
    return $last;
  }


  private function get_default_cold_survey_model_blocks() {
    return array(
      array( 'block_type' => 'notation', 'question' => 'Indiquez dans quelle mesure vous utilisez les connaissances acquises au cours de la formation.', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Quels ont été les éléments facilitants cette mise en pratique ?', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Indiquez dans quelle mesure la formation a-t-elle répondu à vos attentes initiales.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez l’adéquation de la formation avec votre métier ou les réalités du secteur.', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Pour quelle(s) raison(s) ?', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Indiquez dans quelle mesure vous recommanderiez cette formation, notamment à une personne exerçant le même métier que vous.', 'explanation' => '' ),
      array( 'block_type' => 'cases_a_cocher', 'question' => 'Cette formation a-t-elle permis de développer de nouvelles compétences essentielles ?', 'explanation' => '', 'choice_type' => 'choix_unique', 'answers' => array( 'Oui', 'Non' ) ),
      array( 'block_type' => 'cases_a_cocher', 'question' => 'Cette formation a-t-elle permis de vous perfectionner dans un domaine que vous connaissiez déjà ?', 'explanation' => '', 'choice_type' => 'choix_unique', 'answers' => array( 'Oui', 'Non' ) ),
      array( 'block_type' => 'cases_a_cocher', 'question' => 'Cette formation a-t-elle permis d’améliorer et faciliter votre quotidien ?', 'explanation' => '', 'choice_type' => 'choix_unique', 'answers' => array( 'Oui', 'Non' ) ),
      array( 'block_type' => 'champ_texte', 'question' => 'Quels ont été les impacts de la formation sur votre situation professionnelle ?', 'explanation' => '' ),
      array( 'block_type' => 'cases_a_cocher', 'question' => 'Pensez-vous avoir besoin d’une formation complémentaire ?', 'explanation' => '', 'choice_type' => 'choix_unique', 'answers' => array( 'Oui', 'Non' ) ),
      array( 'block_type' => 'champ_texte', 'question' => 'Le cas échéant, quelle formation devrait être envisagée, et pour atteindre quels objectifs ?', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Avec du recul, quelle est votre appréciation générale de cette formation ?', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Avec du recul, qu’est-ce qui vous a le plus été utile dans cette formation ?', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Quelles sont vos recommandations d’améliorations ?', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Quels sont vos commentaires éventuels ?', 'explanation' => '' ),
    );
  }


  private function get_cold_surveys( $search = '', $include_models = true ) {
    $this->maybe_seed_default_cold_surveys();
    $surveys = $this->normalize_legacy_survey_collection( get_option( 'acdc_of_cold_surveys', array() ) );
    if ( ! $include_models ) {
      $surveys = array_values( array_filter( $surveys, function( $survey ) {
        return empty( $survey['is_model'] );
      } ) );
    }
    if ( '' !== trim( (string) $search ) ) {
      $needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $search ) ) : strtolower( trim( (string) $search ) );
      $surveys = array_values( array_filter( $surveys, function( $survey ) use ( $needle ) {
        $hay = (string) ( $survey['title'] ?? '' ) . ' ' . (string) ( $survey['description_text'] ?? '' );
        $hay = function_exists( 'mb_strtolower' ) ? mb_strtolower( $hay ) : strtolower( $hay );
        return false !== strpos( $hay, $needle );
      } ) );
    }
    usort( $surveys, function( $a, $b ) {
      $amodel = ! empty( $a['is_model'] ) ? 1 : 0;
      $bmodel = ! empty( $b['is_model'] ) ? 1 : 0;
      if ( $amodel !== $bmodel ) {
        return $bmodel <=> $amodel;
      }
      return strcmp( (string) ( $b['updated_at'] ?? '' ), (string) ( $a['updated_at'] ?? '' ) );
    } );
    return $surveys;
  }


  private function get_cold_survey( $id ) {
    $surveys = $this->get_cold_surveys( '', true );
    foreach ( $surveys as $survey ) {
      if ( (int) ( $survey['id'] ?? 0 ) === (int) $id ) {
        return (object) $survey;
      }
    }
    return null;
  }


  private function save_cold_survey_record( $record ) {
    $surveys = $this->normalize_legacy_survey_collection( get_option( 'acdc_of_cold_surveys', array() ) );
    $saved = false;
    foreach ( $surveys as $index => $survey ) {
      if ( (int) ( $survey['id'] ?? 0 ) === (int) $record['id'] ) {
        $surveys[ $index ] = $record;
        $saved = true;
        break;
      }
    }
    if ( ! $saved ) {
      $surveys[] = $record;
    }
    update_option( 'acdc_of_cold_surveys', array_values( $surveys ), false );
  }


  private function delete_cold_survey_record( $id ) {
    $surveys = $this->normalize_legacy_survey_collection( get_option( 'acdc_of_cold_surveys', array() ) );
    $__acdc_avant = count( (array) $surveys );
    $surveys = array_values( array_filter( $surveys, function( $survey ) use ( $id ) {
      return (int) ( $survey['id'] ?? 0 ) !== (int) $id;
    } ) );
    update_option( 'acdc_of_cold_surveys', $surveys, false );
    /* ACDC 3.25.300 — La trace est posée ICI, dans la fonction qui retire
       réellement la fiche, et non chez ses appelants : elle sert tous les
       chemins d'un coup, et elle compare le nombre avant et après. Une fiche
       introuvable produit donc « error », pas une suppression imaginaire. */
    $this->log_action_event( 'suppression_cold_survey', 'cold_survey', (int) $id, count( (array) $surveys ) < $__acdc_avant ? 'success' : 'error', array( 'restants' => count( (array) $surveys ) ) );
  }


  private function maybe_seed_default_trainer_surveys() {
    $surveys = $this->normalize_legacy_survey_collection( get_option( 'acdc_of_trainer_surveys', array() ) );
    $has_action_model = false;
    $has_annual_model = false;
    $action_description = "L’enquête formateur est une étape essentielle pour nous permettre d’améliorer nos services et de mieux répondre à vos attentes. Votre retour d’expérience est précieux pour nous aider à adapter nos formations et à vous fournir un accompagnement optimal.
Prenez quelques instants pour partager vos impressions et suggestions.

À noter : Certaines des questions peuvent être évaluées sur une échelle de 1 à 5. Dans ce cas, 1 représente le niveau le plus bas et 5 le niveau le plus élevé.";
    $annual_description = "Cette enquête annuelle a pour objectif de recueillir votre retour global sur votre collaboration avec notre organisme de formation au cours de l’année écoulée.
Vos réponses nous permettront d’évaluer nos pratiques, d’identifier des axes d’amélioration et de répondre aux exigences de la démarche Qualiopi, notamment en matière d’amélioration continue et de suivi des intervenants.

À noter : certaines questions sont évaluées sur une échelle de 1 à 5, où 1 correspond au niveau le plus faible et 5 au niveau le plus élevé.";
    foreach ( $surveys as $index => $survey ) {
      if ( empty( $survey['is_model'] ) ) {
        continue;
      }
      $scope = isset( $survey['scope'] ) ? sanitize_key( (string) $survey['scope'] ) : 'action';
      if ( 'annual' === $scope ) {
        $has_annual_model = true;
        if ( empty( $surveys[ $index ]['title'] ) || false !== strpos( (string) $surveys[ $index ]['title'], 'Annuelles' ) || 'Enquête formateur annuelle (modèle)' === (string) $surveys[ $index ]['title'] ) {
          $surveys[ $index ]['title'] = 'Enquête formateur annuelle (modèle)';
          $surveys[ $index ]['description_text'] = $annual_description;
          $surveys[ $index ]['question_blocks'] = $this->get_default_trainer_survey_model_blocks( 'annual' );
          $surveys[ $index ]['alert_notation'] = '';
        }
      } else {
        $has_action_model = true;
        if ( empty( $surveys[ $index ]['title'] ) || false !== strpos( (string) $surveys[ $index ]['title'], 'Actions de formation' ) || 'Enquête formateur (modèle)' === (string) $surveys[ $index ]['title'] ) {
          $surveys[ $index ]['title'] = 'Enquête formateur (modèle)';
          $surveys[ $index ]['description_text'] = $action_description;
          $surveys[ $index ]['question_blocks'] = $this->get_default_trainer_survey_model_blocks( 'action' );
          $surveys[ $index ]['alert_notation'] = isset( $surveys[ $index ]['alert_notation'] ) && '' !== (string) $surveys[ $index ]['alert_notation'] ? (string) $surveys[ $index ]['alert_notation'] : '3';
        }
      }
    }
    $now = current_time( 'mysql' );
    if ( ! $has_action_model ) {
      $surveys[] = array(
        'id'        => $this->get_next_trainer_survey_id(),
        'scope'      => 'action',
        'title'      => 'Enquête formateur (modèle)',
        'description_text' => $action_description,
        'question_blocks' => $this->get_default_trainer_survey_model_blocks( 'action' ),
        'alert_notation'  => '3',
        'is_model'     => 1,
        'created_at'    => $now,
        'updated_at'    => $now,
      );
    }
    if ( ! $has_annual_model ) {
      $surveys[] = array(
        'id'        => $this->get_next_trainer_survey_id(),
        'scope'      => 'annual',
        'title'      => 'Enquête formateur annuelle (modèle)',
        'description_text' => $annual_description,
        'question_blocks' => $this->get_default_trainer_survey_model_blocks( 'annual' ),
        'alert_notation'  => '',
        'is_model'     => 1,
        'created_at'    => $now,
        'updated_at'    => $now,
      );
    }
    update_option( 'acdc_of_trainer_surveys', $surveys, false );
  }


  private function get_next_trainer_survey_id() {
    $last = (int) get_option( 'acdc_of_trainer_surveys_last_id', 0 );
    $last++;
    update_option( 'acdc_of_trainer_surveys_last_id', $last, false );
    return $last;
  }


  private function get_default_trainer_survey_model_blocks( $scope = 'action' ) {
    if ( 'annual' === $scope ) {
      return array(
        array( 'block_type' => 'notation', 'question' => 'Notez votre niveau de satisfaction globale concernant votre collaboration avec notre organisme sur l’année écoulée.', 'explanation' => '' ),
        array( 'block_type' => 'notation', 'question' => 'Notez la qualité des échanges et de la communication avec l’équipe administrative.', 'explanation' => '' ),
        array( 'block_type' => 'notation', 'question' => 'Notez la clarté et la fiabilité des informations transmises (plannings, convocations, documents, consignes).', 'explanation' => '' ),
        array( 'block_type' => 'notation', 'question' => 'Notez la qualité de l’organisation des actions de formation auxquelles vous avez participé.', 'explanation' => '' ),
        array( 'block_type' => 'notation', 'question' => 'Notez l’adéquation entre les formations dispensées et les profils des apprenants.', 'explanation' => '' ),
        array( 'block_type' => 'notation', 'question' => 'Notez les moyens pédagogiques et outils mis à votre disposition.', 'explanation' => '' ),
        array( 'block_type' => 'notation', 'question' => 'Notez le niveau de charge administrative liée à votre activité de formateur (une note élevée signifie une charge administrative faible).', 'explanation' => '' ),
        array( 'block_type' => 'notation', 'question' => 'Notez la réactivité de l’organisme face à vos demandes ou besoins spécifiques.', 'explanation' => '' ),
        array( 'block_type' => 'notation', 'question' => 'Notez votre niveau de motivation à poursuivre votre collaboration avec notre organisme.', 'explanation' => '' ),
        array( 'block_type' => 'champ_texte', 'question' => 'Quelle est votre appréciation générale de l’année écoulée en tant que formateur au sein de notre organisme ?', 'explanation' => '' ),
        array( 'block_type' => 'champ_texte', 'question' => 'Qu’avez-vous le plus apprécié dans votre collaboration avec notre organisme cette année ?', 'explanation' => '' ),
        array( 'block_type' => 'champ_texte', 'question' => 'Qu’avez-vous le moins apprécié ou rencontré comme difficulté cette année ?', 'explanation' => '' ),
        array( 'block_type' => 'champ_texte', 'question' => 'Quelles améliorations concrètes souhaiteriez-vous voir mises en place pour l’année à venir ?', 'explanation' => '' ),
        array( 'block_type' => 'champ_texte', 'question' => 'Avez-vous des commentaires ou remarques complémentaires à nous transmettre ?', 'explanation' => '' ),
      );
    }
    return array(
      array( 'block_type' => 'notation', 'question' => 'Notez votre niveau de motivation à dispenser cette formation.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez le niveau de motivation général des apprenants.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez la cohérence entre la formation dispensée et les attentes de vos bénéficiaires.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez la qualité des informations transmises par notre organisme en amont de la formation.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez la qualité des contenus de formation.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez le niveau de gestion administrative que vous avez dû effectuer (une note haute signifie un faible niveau de gestion administrative).', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez le délai de traitement de vos demandes.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez la réactivité de notre organisme de formation.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez les moyens mis à disposition.', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Quelle est votre appréciation générale ?', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Qu’avez-vous le plus apprécié dans cette formation ?', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Qu’avez-vous le moins apprécié dans cette formation ?', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Quelles sont vos recommandations d’améliorations ?', 'explanation' => '' ),
    );
  }


  private function get_trainer_surveys( $scope = 'action', $search = '', $include_models = true ) {
    $scope = 'annual' === $scope ? 'annual' : 'action';
    $this->maybe_seed_default_trainer_surveys();
    $surveys = $this->normalize_legacy_survey_collection( get_option( 'acdc_of_trainer_surveys', array() ) );
    $surveys = array_values( array_filter( $surveys, function( $survey ) use ( $scope ) {
      $survey_scope = isset( $survey['scope'] ) ? sanitize_key( (string) $survey['scope'] ) : 'action';
      return $survey_scope === $scope;
    } ) );
    if ( ! $include_models ) {
      $surveys = array_values( array_filter( $surveys, function( $survey ) {
        return empty( $survey['is_model'] );
      } ) );
    }
    if ( '' !== trim( (string) $search ) ) {
      $needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $search ) ) : strtolower( trim( (string) $search ) );
      $surveys = array_values( array_filter( $surveys, function( $survey ) use ( $needle ) {
        $hay = (string) ( $survey['title'] ?? '' ) . ' ' . (string) ( $survey['description_text'] ?? '' );
        $hay = function_exists( 'mb_strtolower' ) ? mb_strtolower( $hay ) : strtolower( $hay );
        return false !== strpos( $hay, $needle );
      } ) );
    }
    usort( $surveys, function( $a, $b ) {
      $amodel = ! empty( $a['is_model'] ) ? 1 : 0;
      $bmodel = ! empty( $b['is_model'] ) ? 1 : 0;
      if ( $amodel !== $bmodel ) {
        return $bmodel <=> $amodel;
      }
      return strcmp( (string) ( $b['updated_at'] ?? '' ), (string) ( $a['updated_at'] ?? '' ) );
    } );
    return $surveys;
  }


  private function get_trainer_survey( $id ) {
    $this->maybe_seed_default_trainer_surveys();
    $surveys = $this->normalize_legacy_survey_collection( get_option( 'acdc_of_trainer_surveys', array() ) );
    foreach ( $surveys as $survey ) {
      if ( (int) ( $survey['id'] ?? 0 ) === (int) $id ) {
        return (object) $survey;
      }
    }
    return null;
  }


  private function save_trainer_survey_record( $record ) {
    $surveys = $this->normalize_legacy_survey_collection( get_option( 'acdc_of_trainer_surveys', array() ) );
    $saved = false;
    foreach ( $surveys as $index => $survey ) {
      if ( (int) ( $survey['id'] ?? 0 ) === (int) $record['id'] ) {
        $surveys[ $index ] = $record;
        $saved = true;
        break;
      }
    }
    if ( ! $saved ) {
      $surveys[] = $record;
    }
    update_option( 'acdc_of_trainer_surveys', array_values( $surveys ), false );
  }


  private function delete_trainer_survey_record( $id ) {
    $surveys = $this->normalize_legacy_survey_collection( get_option( 'acdc_of_trainer_surveys', array() ) );
    $__acdc_avant = count( (array) $surveys );
    $surveys = array_values( array_filter( $surveys, function( $survey ) use ( $id ) {
      return (int) ( $survey['id'] ?? 0 ) !== (int) $id;
    } ) );
    update_option( 'acdc_of_trainer_surveys', $surveys, false );
    /* ACDC 3.25.300 — La trace est posée ICI, dans la fonction qui retire
       réellement la fiche, et non chez ses appelants : elle sert tous les
       chemins d'un coup, et elle compare le nombre avant et après. Une fiche
       introuvable produit donc « error », pas une suppression imaginaire. */
    $this->log_action_event( 'suppression_trainer_survey', 'trainer_survey', (int) $id, count( (array) $surveys ) < $__acdc_avant ? 'success' : 'error', array( 'restants' => count( (array) $surveys ) ) );
  }


  private function get_trainer_survey_scope() {
    $scope = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : '';
    return in_array( $scope, array( 'action', 'annual' ), true ) ? $scope : '';
  }


  private function get_trainer_survey_scope_label( $scope = 'action' ) {
    return 'annual' === $scope ? 'Annuelles' : 'Actions de formation';
  }


  private function maybe_seed_default_company_surveys() {
    $surveys = $this->normalize_legacy_survey_collection( get_option( 'acdc_of_company_surveys', array() ) );
    foreach ( $surveys as $survey ) {
      if ( ! empty( $survey['is_model'] ) ) {
        return;
      }
    }
    $now = current_time( 'mysql' );
    $surveys[] = array(
      'id' => $this->get_next_company_survey_id(),
      'title' => 'Enquête entreprise (modèle)',
      'description_text' => "L'enquête entreprise est une opportunité pour vous d'influencer positivement le développement de nos services. Vos retours nous permettront d'ajuster nos offres et de mieux répondre à vos besoins spécifiques en matière de financement de la formation. Nous vous invitons à partager vos impressions et suggestions afin d'optimiser notre collaboration.\n\nÀ noter : Certaines des questions peuvent être évaluées sur une échelle de 1 à 5. Dans ce cas, 1 représente le niveau le plus bas et 5 le niveau le plus élevé.",
      'question_blocks' => $this->get_default_company_survey_model_blocks(),
      'alert_notation' => '3',
      'is_model' => 1,
      'created_at' => $now,
      'updated_at' => $now,
    );
    update_option( 'acdc_of_company_surveys', $surveys, false );
  }


  private function get_next_company_survey_id() {
    $last = (int) get_option( 'acdc_of_company_surveys_last_id', 0 );
    $last++;
    update_option( 'acdc_of_company_surveys_last_id', $last, false );
    return $last;
  }


  private function get_default_company_survey_model_blocks() {
    return array(
      array( 'block_type' => 'notation', 'question' => 'Notez votre niveau de satisfaction général.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez le niveau de besoin du stagiaire à suivre cette formation.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez la qualité des informations transmises par notre organisme en amont de la formation (description, programme, synopsis, etc.).', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez le délai de traitement de vos demandes.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez le délai de transmission des différents documents.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez la réactivité de notre organisme de formation.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez votre niveau de recommandation cette formation.', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Qui était la personne à l’initiative de cette formation ?', 'explanation' => '' ),
      array( 'block_type' => 'cases_a_cocher', 'question' => 'Depuis la fin de sa formation, le bénéficiaire a-t-il pu mettre en pratique les connaissances acquises lors de sa formation ?', 'explanation' => '', 'choice_type' => 'choix_unique', 'answers' => array( 'Oui', 'Oui partiellement', 'Non' ) ),
      array( 'block_type' => 'champ_texte', 'question' => 'Qu’est-ce qui pourrait permettre au bénéficiaire d’améliorer la mise en pratique des connaissances acquises en formation ?', 'explanation' => '' ),
      array( 'block_type' => 'cases_a_cocher', 'question' => 'Suite à la formation, avez-vous eu un entretien avec le bénéficiaire au sujet de l’apport de sa formation ?', 'explanation' => '', 'choice_type' => 'choix_unique', 'answers' => array( 'Oui', 'Non' ) ),
      array( 'block_type' => 'champ_texte', 'question' => 'Quelle est votre appréciation générale ?', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Qu’avez-vous le plus apprécié ?', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Qu’avez-vous le moins apprécié ?', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Quelles sont vos recommandations d’améliorations ?', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Quels sont vos commentaires éventuels ?', 'explanation' => '' ),
    );
  }


  private function get_company_surveys( $search = '', $include_models = true ) {
    $this->maybe_seed_default_company_surveys();
    $surveys = $this->normalize_legacy_survey_collection( get_option( 'acdc_of_company_surveys', array() ) );
    if ( ! $include_models ) {
      $surveys = array_values( array_filter( $surveys, function( $survey ) {
        return empty( $survey['is_model'] );
      } ) );
    }
    if ( '' !== trim( (string) $search ) ) {
      $needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $search ) ) : strtolower( trim( (string) $search ) );
      $surveys = array_values( array_filter( $surveys, function( $survey ) use ( $needle ) {
        $hay = (string) ( $survey['title'] ?? '' ) . ' ' . (string) ( $survey['description_text'] ?? '' );
        $hay = function_exists( 'mb_strtolower' ) ? mb_strtolower( $hay ) : strtolower( $hay );
        return false !== strpos( $hay, $needle );
      } ) );
    }
    usort( $surveys, function( $a, $b ) {
      $amodel = ! empty( $a['is_model'] ) ? 1 : 0;
      $bmodel = ! empty( $b['is_model'] ) ? 1 : 0;
      if ( $amodel !== $bmodel ) {
        return $bmodel <=> $amodel;
      }
      return strcmp( (string) ( $b['updated_at'] ?? '' ), (string) ( $a['updated_at'] ?? '' ) );
    } );
    return $surveys;
  }


  private function get_company_survey( $id ) {
    $surveys = $this->get_company_surveys( '', true );
    foreach ( $surveys as $survey ) {
      if ( (int) ( $survey['id'] ?? 0 ) === (int) $id ) {
        return (object) $survey;
      }
    }
    return null;
  }


  private function save_company_survey_record( $record ) {
    $surveys = $this->normalize_legacy_survey_collection( get_option( 'acdc_of_company_surveys', array() ) );
    $saved = false;
    foreach ( $surveys as $index => $survey ) {
      if ( (int) ( $survey['id'] ?? 0 ) === (int) $record['id'] ) {
        $surveys[ $index ] = $record;
        $saved = true;
        break;
      }
    }
    if ( ! $saved ) {
      $surveys[] = $record;
    }
    update_option( 'acdc_of_company_surveys', array_values( $surveys ), false );
  }


  private function delete_company_survey_record( $id ) {
    $surveys = $this->normalize_legacy_survey_collection( get_option( 'acdc_of_company_surveys', array() ) );
    $__acdc_avant = count( (array) $surveys );
    $surveys = array_values( array_filter( $surveys, function( $survey ) use ( $id ) {
      return (int) ( $survey['id'] ?? 0 ) !== (int) $id;
    } ) );
    update_option( 'acdc_of_company_surveys', $surveys, false );
    /* ACDC 3.25.300 — La trace est posée ICI, dans la fonction qui retire
       réellement la fiche, et non chez ses appelants : elle sert tous les
       chemins d'un coup, et elle compare le nombre avant et après. Une fiche
       introuvable produit donc « error », pas une suppression imaginaire. */
    $this->log_action_event( 'suppression_company_survey', 'company_survey', (int) $id, count( (array) $surveys ) < $__acdc_avant ? 'success' : 'error', array( 'restants' => count( (array) $surveys ) ) );
  }


  private function maybe_seed_default_funder_surveys() {
    $surveys = $this->normalize_legacy_survey_collection( get_option( 'acdc_of_funder_surveys', array() ) );
    $has_action_model = false;
    $has_annual_model = false;
    $action_description = "L'enquête financeur permet de recueillir l'appréciation du financeur sur la qualité de la collaboration, la lisibilité du dossier, la réactivité de l'organisme et la qualité des éléments transmis à l'issue d'une prestation donnée. Vos retours nous aident à améliorer le suivi administratif et la conformité de nos actions.";
    $annual_description = "Cette enquête annuelle financeur vise à recueillir une appréciation globale de la collaboration avec notre organisme sur l'année écoulée. Elle permet d'identifier les points forts, les réserves éventuelles et les pistes d'amélioration à consolider dans notre démarche qualité et Qualiopi.";
    foreach ( $surveys as $index => $survey ) {
      if ( empty( $survey['is_model'] ) ) {
        continue;
      }
      $scope = isset( $survey['scope'] ) ? sanitize_key( (string) $survey['scope'] ) : 'action';
      if ( 'annual' === $scope ) {
        $has_annual_model = true;
        $surveys[ $index ]['scope'] = 'annual';
        if ( empty( $surveys[ $index ]['title'] ) || false !== strpos( (string) $surveys[ $index ]['title'], 'annuelle' ) || false !== strpos( (string) $surveys[ $index ]['title'], 'Annuelles' ) ) {
          $surveys[ $index ]['title'] = 'Enquête financeur annuelle (modèle)';
        }
        $surveys[ $index ]['description_text'] = $annual_description;
        $surveys[ $index ]['question_blocks'] = $this->get_default_funder_survey_model_blocks( 'annual' );
        if ( '' === (string) ( $surveys[ $index ]['alert_notation'] ?? '' ) ) {
          $surveys[ $index ]['alert_notation'] = '3';
        }
      } else {
        $has_action_model = true;
        $surveys[ $index ]['scope'] = 'action';
        if ( empty( $surveys[ $index ]['title'] ) || false !== strpos( (string) $surveys[ $index ]['title'], 'annuelle' ) ) {
          $surveys[ $index ]['title'] = 'Enquête financeur (modèle)';
        }
        $surveys[ $index ]['description_text'] = $action_description;
        $surveys[ $index ]['question_blocks'] = $this->get_default_funder_survey_model_blocks( 'action' );
        if ( '' === (string) ( $surveys[ $index ]['alert_notation'] ?? '' ) ) {
          $surveys[ $index ]['alert_notation'] = '3';
        }
      }
    }
    $now = current_time( 'mysql' );
    if ( ! $has_action_model ) {
      $surveys[] = array(
        'id' => $this->get_next_funder_survey_id(),
        'scope' => 'action',
        'title' => 'Enquête financeur (modèle)',
        'description_text' => $action_description,
        'question_blocks' => $this->get_default_funder_survey_model_blocks( 'action' ),
        'alert_notation' => '3',
        'is_model' => 1,
        'created_at' => $now,
        'updated_at' => $now,
      );
    }
    if ( ! $has_annual_model ) {
      $surveys[] = array(
        'id' => $this->get_next_funder_survey_id(),
        'scope' => 'annual',
        'title' => 'Enquête financeur annuelle (modèle)',
        'description_text' => $annual_description,
        'question_blocks' => $this->get_default_funder_survey_model_blocks( 'annual' ),
        'alert_notation' => '3',
        'is_model' => 1,
        'created_at' => $now,
        'updated_at' => $now,
      );
    }
    update_option( 'acdc_of_funder_surveys', array_values( $surveys ), false );
  }


  private function get_next_funder_survey_id() {
    $last = (int) get_option( 'acdc_of_funder_surveys_last_id', 0 );
    $last++;
    update_option( 'acdc_of_funder_surveys_last_id', $last, false );
    return $last;
  }


  private function get_default_funder_survey_model_blocks( $scope = 'action' ) {
    if ( 'annual' === $scope ) {
      return array(
        array( 'block_type' => 'notation', 'question' => 'Notez votre niveau de satisfaction globale concernant notre collaboration sur l’année écoulée.', 'explanation' => '' ),
        array( 'block_type' => 'notation', 'question' => 'Notez la qualité et la clarté des échanges avec notre organisme.', 'explanation' => '' ),
        array( 'block_type' => 'notation', 'question' => 'Notez la qualité des pièces administratives et des informations qui vous ont été transmises.', 'explanation' => '' ),
        array( 'block_type' => 'notation', 'question' => 'Notez la réactivité globale de notre organisme sur l’année écoulée.', 'explanation' => '' ),
        array( 'block_type' => 'notation', 'question' => 'Notez le niveau de confiance que vous accordez à notre suivi administratif.', 'explanation' => '' ),
        array( 'block_type' => 'notation', 'question' => 'Notez votre volonté de poursuivre la collaboration avec notre organisme.', 'explanation' => '' ),
        array( 'block_type' => 'champ_texte', 'question' => 'Quels sont les points forts de notre collaboration cette année ?', 'explanation' => '' ),
        array( 'block_type' => 'champ_texte', 'question' => 'Quels sont les points de vigilance ou réserves à signaler ?', 'explanation' => '' ),
        array( 'block_type' => 'champ_texte', 'question' => 'Quelles améliorations recommandez-vous pour l’année à venir ?', 'explanation' => '' ),
        array( 'block_type' => 'champ_texte', 'question' => 'Commentaires complémentaires', 'explanation' => '' ),
      );
    }
    return array(
      array( 'block_type' => 'notation', 'question' => 'Notez votre niveau de satisfaction général.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez l’accueil téléphonique de notre organisme.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez la qualité des informations transmises en amont de la formation.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez la réactivité de notre organisme.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez le délai de traitement de vos demandes.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez le délai de transmission des différents éléments et des documents.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez la qualité et la pertinence des éléments et des documents transmis.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez la pertinence des informations transmises sur nos factures.', 'explanation' => '' ),
      array( 'block_type' => 'notation', 'question' => 'Notez votre niveau de recommandation de notre organisme.', 'explanation' => '' ),
      array( 'block_type' => 'cases_a_cocher', 'question' => 'Suite à sa formation, avez-vous eu un entretien avec le bénéficiaire au sujet de l’apport de sa formation ?', 'explanation' => '', 'choice_type' => 'choix_unique', 'answers' => array( 'Oui', 'Non' ) ),
      array( 'block_type' => 'champ_texte', 'question' => 'Quelle est votre appréciation générale ?', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Qu’avez-vous le plus apprécié ?', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Qu’avez-vous le moins apprécié ?', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Quelles sont vos recommandations d’améliorations ?', 'explanation' => '' ),
      array( 'block_type' => 'champ_texte', 'question' => 'Quels sont vos commentaires éventuels ?', 'explanation' => '' ),
    );
  }


  private function get_funder_surveys( $scope = 'action', $search = '', $include_models = true ) {
    $scope = 'annual' === $scope ? 'annual' : 'action';
    $this->maybe_seed_default_funder_surveys();
    $surveys = $this->normalize_legacy_survey_collection( get_option( 'acdc_of_funder_surveys', array() ) );
    $surveys = array_values( array_filter( $surveys, function( $survey ) use ( $scope ) {
      $survey_scope = isset( $survey['scope'] ) ? sanitize_key( (string) $survey['scope'] ) : 'action';
      return $survey_scope === $scope;
    } ) );
    if ( ! $include_models ) {
      $surveys = array_values( array_filter( $surveys, function( $survey ) {
        return empty( $survey['is_model'] );
      } ) );
    }
    if ( '' !== trim( (string) $search ) ) {
      $needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $search ) ) : strtolower( trim( (string) $search ) );
      $surveys = array_values( array_filter( $surveys, function( $survey ) use ( $needle ) {
        $hay = (string) ( $survey['title'] ?? '' ) . ' ' . (string) ( $survey['description_text'] ?? '' );
        $hay = function_exists( 'mb_strtolower' ) ? mb_strtolower( $hay ) : strtolower( $hay );
        return false !== strpos( $hay, $needle );
      } ) );
    }
    usort( $surveys, function( $a, $b ) {
      $amodel = ! empty( $a['is_model'] ) ? 1 : 0;
      $bmodel = ! empty( $b['is_model'] ) ? 1 : 0;
      if ( $amodel !== $bmodel ) {
        return $bmodel <=> $amodel;
      }
      return strcmp( (string) ( $b['updated_at'] ?? '' ), (string) ( $a['updated_at'] ?? '' ) );
    } );
    return $surveys;
  }


  private function get_funder_survey( $id ) {
    $surveys = $this->normalize_legacy_survey_collection( get_option( 'acdc_of_funder_surveys', array() ) );
    foreach ( $surveys as $survey ) {
      if ( (int) ( $survey['id'] ?? 0 ) === (int) $id ) {
        return (object) $survey;
      }
    }
    return null;
  }


  private function save_funder_survey_record( $record ) {
    $surveys = $this->normalize_legacy_survey_collection( get_option( 'acdc_of_funder_surveys', array() ) );
    $saved = false;
    foreach ( $surveys as $index => $survey ) {
      if ( (int) ( $survey['id'] ?? 0 ) === (int) $record['id'] ) {
        $surveys[ $index ] = $record;
        $saved = true;
        break;
      }
    }
    if ( ! $saved ) {
      $surveys[] = $record;
    }
    update_option( 'acdc_of_funder_surveys', array_values( $surveys ), false );
  }


  private function delete_funder_survey_record( $id ) {
    $surveys = $this->normalize_legacy_survey_collection( get_option( 'acdc_of_funder_surveys', array() ) );
    $__acdc_avant = count( (array) $surveys );
    $surveys = array_values( array_filter( $surveys, function( $survey ) use ( $id ) {
      return (int) ( $survey['id'] ?? 0 ) !== (int) $id;
    } ) );
    update_option( 'acdc_of_funder_surveys', $surveys, false );
    /* ACDC 3.25.300 — La trace est posée ICI, dans la fonction qui retire
       réellement la fiche, et non chez ses appelants : elle sert tous les
       chemins d'un coup, et elle compare le nombre avant et après. Une fiche
       introuvable produit donc « error », pas une suppression imaginaire. */
    $this->log_action_event( 'suppression_funder_survey', 'funder_survey', (int) $id, count( (array) $surveys ) < $__acdc_avant ? 'success' : 'error', array( 'restants' => count( (array) $surveys ) ) );
  }



  private function get_funder_survey_scope() {
    $scope = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : '';
    return in_array( $scope, array( 'action', 'annual' ), true ) ? $scope : 'action';
  }


  private function get_funder_survey_scope_label( $scope = 'action' ) {
    return 'annual' === $scope ? 'Annuelles' : 'Par dossier';
  }


  private function get_questionnaire_source_label_map() {
    return array(
      'quiz' => 'Quiz',
      'positioning_test' => 'Test de positionnement',
      'evaluation' => 'Évaluation des acquis',
      'mid_survey' => 'Enquête intermédiaire',
      'hot_survey' => 'Enquête à chaud',
      'cold_survey' => 'Enquête à froid',
      'trainer_survey' => 'Enquête formateur',
      'company_survey' => 'Enquête entreprise',
      'funder_survey' => 'Enquête financeur',
    );
  }


  private function get_survey_questionnaire_source_types() {
    return array( 'mid_survey', 'hot_survey', 'cold_survey', 'trainer_survey', 'company_survey', 'funder_survey' );
  }


  private function is_survey_questionnaire_source_type( $type ) {
    return in_array( (string) $type, $this->get_survey_questionnaire_source_types(), true );
  }


  private function is_learner_questionnaire_source_type( $type ) {
    return in_array( (string) $type, array( 'mid_survey', 'hot_survey', 'cold_survey' ), true );
  }


  private function normalize_survey_question_blocks_for_questionnaire( $blocks ) {
    $normalized = array();
    foreach ( (array) $blocks as $index => $block ) {
      if ( ! is_array( $block ) ) {
        continue;
      }
      $label = isset( $block['question'] ) ? sanitize_text_field( (string) $block['question'] ) : '';
      $type  = isset( $block['block_type'] ) ? (string) $block['block_type'] : 'notation';
      $explanation = isset( $block['explanation'] ) ? sanitize_textarea_field( (string) $block['explanation'] ) : '';
      $answers = array();
      if ( ! empty( $block['answers'] ) && is_array( $block['answers'] ) ) {
        foreach ( $block['answers'] as $answer ) {
          $answer = sanitize_text_field( (string) $answer );
          if ( '' !== $answer ) {
            $answers[] = $answer;
          }
        }
      }
      $question = array(
        'label' => $label ?: 'Question ' . ( $index + 1 ),
        'type'  => 'Notation',
        'options' => '',
      );
      if ( 'champ_texte' === $type ) {
        $question['type']    = 'Question ouverte';
        $question['options'] = $explanation;
      } elseif ( 'reponse_courte' === $type ) {
        $question['type']    = 'Réponse courte';
        $question['options'] = $explanation;
      } elseif ( 'smileys' === $type ) {
        $question['type']    = 'Smileys';
        $question['options'] = $explanation;
      } elseif ( 'nps' === $type ) {
        $question['type']          = 'NPS';
        $question['nps_min']       = isset( $block['nps_min'] ) ? (int) $block['nps_min'] : 0;
        $question['nps_max']       = isset( $block['nps_max'] ) ? (int) $block['nps_max'] : 10;
        $question['nps_min_label'] = isset( $block['nps_min_label'] ) ? sanitize_text_field( (string) $block['nps_min_label'] ) : 'Pas du tout';
        $question['nps_max_label'] = isset( $block['nps_max_label'] ) ? sanitize_text_field( (string) $block['nps_max_label'] ) : 'Tout à fait';
        $question['options']       = $explanation;
      } elseif ( 'oui_non' === $type ) {
        $question['type']    = 'Oui / Non';
        $question['options'] = "Oui\nNon";
      } elseif ( 'liste_deroulante' === $type ) {
        $question['type']    = 'Liste déroulante';
        $question['options'] = implode( "\n", $answers );
      } elseif ( 'cases_a_cocher' === $type ) {
        $choice_type = isset( $block['choice_type'] ) ? (string) $block['choice_type'] : 'choix_unique';
        $question['type']    = 'choix_multiple' === $choice_type ? 'Choix multiples' : 'Cases à cocher';
        $question['options'] = implode( "\n", $answers );
      } else {
        /* notation défaut — étoiles 1-5 */
        $question['type']    = 'Notation';
        $question['options'] = $explanation;
      }
      $normalized[] = $question;
    }
    return array_values( $normalized );
  }


  /* ACDC 3.21.19 — Helpers visuels enquêtes publiques. */

  private function get_survey_hero_image_url( $survey_type ) {
    $map = array(
      'mid_survey'     => 'https://acdcformation.com/wp-content/uploads/2026/05/Enquete-a-mi-parcours-mid.png',
      'hot_survey'     => 'https://acdcformation.com/wp-content/uploads/2026/05/Enquete-a-chaud-hot.png',
      'cold_survey'    => 'https://acdcformation.com/wp-content/uploads/2026/05/Enquete-a-froid-cold.png',
      'trainer_survey' => 'https://acdcformation.com/wp-content/uploads/2026/05/Enquete-formateur-trainer.png',
      'company_survey' => 'https://acdcformation.com/wp-content/uploads/2026/05/Enquete-entreprise-company.png',
      'funder_survey'  => 'https://acdcformation.com/wp-content/uploads/2026/05/Enquete-financeur-funder.png',
    );
    return isset( $map[ $survey_type ] ) ? $map[ $survey_type ] : '';
  }


  private function get_survey_type_display_label( $survey_type ) {
    $map = array(
      'mid_survey'     => 'Enquête à mi-parcours',
      'hot_survey'     => 'Enquête de satisfaction à chaud',
      'cold_survey'    => 'Enquête de satisfaction à froid',
      'trainer_survey' => 'Enquête formateur',
      'company_survey' => 'Enquête entreprise',
      'funder_survey'  => 'Enquête financeur',
    );
    return isset( $map[ $survey_type ] ) ? $map[ $survey_type ] : 'Enquête';
  }


  private function get_survey_block_types_map() {
    return array(
      'notation'         => 'Étoiles (1 à 5 ★)',
      'smileys'          => 'Smileys (😞 à 😄)',
      'nps'              => 'Échelle NPS (0 à 10)',
      'champ_texte'      => 'Paragraphe (texte long)',
      'reponse_courte'   => 'Réponse courte',
      'cases_a_cocher'   => 'Cases à cocher / Choix unique',
      'liste_deroulante' => 'Liste déroulante',
      'oui_non'          => 'Oui / Non',
    );
  }


  private function sanitize_survey_block_full( $block ) {
    if ( ! is_array( $block ) ) {
      return null;
    }
    $allowed_types = array( 'notation', 'smileys', 'nps', 'champ_texte', 'reponse_courte', 'cases_a_cocher', 'liste_deroulante', 'oui_non' );
    $raw_type   = isset( $block['block_type'] ) ? (string) $block['block_type'] : 'notation';
    $block_type = in_array( $raw_type, $allowed_types, true ) ? $raw_type : 'notation';
    $question   = sanitize_text_field( isset( $block['question'] ) ? (string) $block['question'] : '' );
    $explanation = sanitize_textarea_field( isset( $block['explanation'] ) ? (string) $block['explanation'] : '' );

    if ( '' === $question && '' === $explanation ) {
      return null;
    }

    $entry = array(
      'block_type'  => $block_type,
      'question'    => $question,
      'explanation' => $explanation,
    );

    if ( in_array( $block_type, array( 'cases_a_cocher', 'liste_deroulante', 'oui_non' ), true ) ) {
      $raw_answers    = isset( $block['answers'] ) && is_array( $block['answers'] ) ? (array) $block['answers'] : array();
      $entry['answers'] = array_values( array_filter( array_map( 'sanitize_text_field', $raw_answers ) ) );
      if ( 'cases_a_cocher' === $block_type ) {
        $entry['choice_type'] = ( isset( $block['choice_type'] ) && 'choix_multiple' === (string) $block['choice_type'] ) ? 'choix_multiple' : 'choix_unique';
      }
    }

    if ( 'nps' === $block_type ) {
      $entry['nps_min']       = isset( $block['nps_min'] ) ? (int) $block['nps_min'] : 0;
      $entry['nps_max']       = isset( $block['nps_max'] ) ? (int) $block['nps_max'] : 10;
      $entry['nps_min_label'] = sanitize_text_field( isset( $block['nps_min_label'] ) ? (string) $block['nps_min_label'] : 'Pas du tout' );
      $entry['nps_max_label'] = sanitize_text_field( isset( $block['nps_max_label'] ) ? (string) $block['nps_max_label'] : 'Tout à fait' );
    }

    return $entry;
  }


  private function get_questionnaire_public_base_url() {
    $page_id = (int) get_option( 'acdc_of_questionnaire_session_page_id', 0 );
    if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
      return get_permalink( $page_id );
    }
    return home_url( '/questionnaire-session/' );
  }


  private function get_questionnaire_source_options() {
    $options = array();
    foreach ( $this->get_quizzes() as $item ) {
      $options[] = array( 'type' => 'quiz', 'id' => (int) $item->id, 'label' => 'Quiz — ' . $item->title );
    }
    /* ACDC 3.25.278 — L'ancien module « tests de positionnement » est retiré :
       il n'y a plus de source de ce type à proposer. Le quiz de positionnement
       reste disponible dans la liste des quiz, au-dessus. */
    foreach ( $this->get_evaluations() as $item ) {
      $options[] = array( 'type' => 'evaluation', 'id' => (int) $item->id, 'label' => 'Évaluation des acquis — ' . $item->title );
    }
    foreach ( $this->get_mid_surveys( '', true ) as $item ) {
      if ( empty( $item['id'] ) || empty( $item['title'] ) ) { continue; }
      $options[] = array( 'type' => 'mid_survey', 'id' => (int) $item['id'], 'label' => 'Enquête intermédiaire — ' . $item['title'] );
    }
    foreach ( $this->get_hot_surveys( '', true ) as $item ) {
      if ( empty( $item['id'] ) || empty( $item['title'] ) ) { continue; }
      $options[] = array( 'type' => 'hot_survey', 'id' => (int) $item['id'], 'label' => 'Enquête à chaud — ' . $item['title'] );
    }
    foreach ( $this->get_cold_surveys( '', true ) as $item ) {
      if ( empty( $item['id'] ) || empty( $item['title'] ) ) { continue; }
      $options[] = array( 'type' => 'cold_survey', 'id' => (int) $item['id'], 'label' => 'Enquête à froid — ' . $item['title'] );
    }
    foreach ( array_merge( $this->get_trainer_surveys( 'action', '', true ), $this->get_trainer_surveys( 'annual', '', true ) ) as $item ) {
      if ( empty( $item['id'] ) || empty( $item['title'] ) ) { continue; }
      $scope_label = ( ! empty( $item['scope'] ) && 'annual' === $item['scope'] ) ? 'annuelle' : 'action';
      $options[] = array( 'type' => 'trainer_survey', 'id' => (int) $item['id'], 'label' => 'Enquête formateur (' . $scope_label . ') — ' . $item['title'] );
    }
    foreach ( $this->get_company_surveys( '', true ) as $item ) {
      if ( empty( $item['id'] ) || empty( $item['title'] ) ) { continue; }
      $options[] = array( 'type' => 'company_survey', 'id' => (int) $item['id'], 'label' => 'Enquête entreprise — ' . $item['title'] );
    }
    foreach ( array_merge( $this->get_funder_surveys( 'action', '', true ), $this->get_funder_surveys( 'annual', '', true ) ) as $item ) {
      if ( empty( $item['id'] ) || empty( $item['title'] ) ) { continue; }
      $scope_label = ( ! empty( $item['scope'] ) && 'annual' === $item['scope'] ) ? 'annuelle' : 'par dossier';
      $options[] = array( 'type' => 'funder_survey', 'id' => (int) $item['id'], 'label' => 'Enquête financeur (' . $scope_label . ') — ' . $item['title'] );
    }
    return $options;
  }


  private function get_questionnaire_source_data( $type, $id ) {
    $id = absint( $id );
    if ( ! $id ) { return null; }
    $record = null;
    if ( 'quiz' === $type ) {
      $record = $this->get_quiz( $id );
    } elseif ( 'positioning_test' === $type ) {
      /* ACDC 3.25.278 — Source retirée. Une session historique qui la désigne
         encore rend null : l'écran affiche « Questionnaire supprimé », ce qui
         est exact, plutôt que de faire croire à une source vivante. */
      $record = null;
    } elseif ( 'evaluation' === $type ) {
      $record = $this->get_evaluation( $id );
    } elseif ( 'mid_survey' === $type ) {
      $record = $this->get_mid_survey( $id );
    } elseif ( 'hot_survey' === $type ) {
      $record = $this->get_hot_survey( $id );
    } elseif ( 'cold_survey' === $type ) {
      $record = $this->get_cold_survey( $id );
    } elseif ( 'trainer_survey' === $type ) {
      $record = $this->get_trainer_survey( $id );
    } elseif ( 'company_survey' === $type ) {
      $record = $this->get_company_survey( $id );
    } elseif ( 'funder_survey' === $type ) {
      $record = $this->get_funder_survey( $id );
    }
    if ( ! $record ) { return null; }
    $questions = array();
    if ( ! empty( $record->question_blocks ) ) {
      $decoded = is_string( $record->question_blocks ) ? json_decode( (string) $record->question_blocks, true ) : $record->question_blocks;
      if ( is_array( $decoded ) ) {
        if ( $this->is_survey_questionnaire_source_type( $type ) ) {
          $questions = $this->normalize_survey_question_blocks_for_questionnaire( $decoded );
        } else {
          $questions = array_values( $decoded );
        }
      }
    }
    return array(
      'type' => $type,
      'id' => $id,
      'title' => isset( $record->title ) ? (string) $record->title : '',
      'duration_minutes' => isset( $record->duration_minutes ) ? absint( $record->duration_minutes ) : 0,
      'questions' => $questions,
      'record' => $record,
    );
  }


  private function generate_questionnaire_session_token() {
    return wp_generate_password( 24, false, false );
  }


  private function generate_questionnaire_participant_token() {
    return wp_generate_password( 28, false, false );
  }


  private function build_questionnaire_session_public_url( $token ) {
    return add_query_arg( array( 'token' => rawurlencode( (string) $token ) ), $this->get_questionnaire_public_base_url() );
  }


  private function generate_questionnaire_session_qrcode_url( $url ) {
    return 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' . rawurlencode( (string) $url );
  }


  private function get_questionnaire_context_config( $context = '' ) {
    $context = (string) $context;
    $map = array(
      'quiz' => array(
        'label_plural' => 'Quiz',
        'label_singular' => 'quiz',
        'session_title' => 'Sessions des quiz',
        'session_description' => 'Créez des sessions pilotées par le formateur à partir des quiz.',
        'result_title' => 'Résultats des quiz',
        'result_description' => 'Centralisez les réponses et scores des sessions issues des quiz.',
        'settings_title' => 'Paramètres des quiz',
        'settings_description' => 'Réglages par défaut appliqués aux sessions créées depuis les quiz.',
        'create_session_label' => 'Créer une session quiz',
      ),
      'positioning_test' => array(
        'label_plural' => 'Tests de positionnement',
        'label_singular' => 'test de positionnement',
        'session_title' => 'Sessions des tests de positionnement',
        'session_description' => 'Créez des sessions pilotées par le formateur à partir des tests de positionnement.',
        'result_title' => 'Résultats des tests de positionnement',
        'result_description' => 'Centralisez les réponses et scores des sessions issues des tests de positionnement.',
        'settings_title' => 'Paramètres des tests de positionnement',
        'settings_description' => 'Réglages par défaut appliqués aux sessions créées depuis les tests de positionnement.',
        'create_session_label' => 'Créer une session test',
      ),
      'evaluation' => array(
        'label_plural' => 'Évaluations des acquis',
        'label_singular' => 'évaluation des acquis',
        'session_title' => 'Sessions des évaluations des acquis',
        'session_description' => 'Créez des sessions pilotées par le formateur à partir des évaluations des acquis.',
        'result_title' => 'Résultats des évaluations des acquis',
        'result_description' => 'Centralisez les réponses et scores des sessions issues des évaluations des acquis.',
        'settings_title' => 'Paramètres des évaluations des acquis',
        'settings_description' => 'Réglages par défaut appliqués aux sessions créées depuis les évaluations des acquis.',
        'create_session_label' => 'Créer une session évaluation',
      ),
      'mid_survey' => array(
        'label_plural' => 'Enquêtes intermédiaires',
        'label_singular' => 'enquête intermédiaire',
        'session_title' => 'Envois des enquêtes intermédiaires',
        'session_description' => 'Planifiez et diffusez les enquêtes intermédiaires auprès des apprenants en cours de formation.',
        'result_title' => 'Résultats des enquêtes intermédiaires',
        'result_description' => 'Centralisez les réponses, relances et verbatims des enquêtes intermédiaires.',
        'settings_title' => 'Paramètres des enquêtes intermédiaires',
        'settings_description' => 'Réglages par défaut appliqués aux envois d’enquêtes intermédiaires.',
        'create_session_label' => 'Créer un envoi intermédiaire',
      ),
      'hot_survey' => array(
        'label_plural' => 'Enquêtes à chaud',
        'label_singular' => 'enquête à chaud',
        'session_title' => 'Envois des enquêtes à chaud',
        'session_description' => 'Planifiez et diffusez les enquêtes à chaud à l’issue des formations.',
        'result_title' => 'Résultats des enquêtes à chaud',
        'result_description' => 'Centralisez les réponses, relances et verbatims des enquêtes à chaud.',
        'settings_title' => 'Paramètres des enquêtes à chaud',
        'settings_description' => 'Réglages par défaut appliqués aux envois d’enquêtes à chaud.',
        'create_session_label' => 'Créer un envoi à chaud',
      ),
      'cold_survey' => array(
        'label_plural' => 'Enquêtes à froid',
        'label_singular' => 'enquête à froid',
        'session_title' => 'Envois des enquêtes à froid',
        'session_description' => 'Planifiez et diffusez les enquêtes à froid avec lien nominatif sécurisé.',
        'result_title' => 'Résultats des enquêtes à froid',
        'result_description' => 'Centralisez les réponses, relances et verbatims des enquêtes à froid.',
        'settings_title' => 'Paramètres des enquêtes à froid',
        'settings_description' => 'Réglages par défaut appliqués aux envois d’enquêtes à froid.',
        'create_session_label' => 'Créer un envoi à froid',
      ),
      'trainer_survey' => array(
        'label_plural' => 'Enquêtes formateurs',
        'label_singular' => 'enquête formateur',
        'session_title' => 'Envois des enquêtes formateurs',
        'session_description' => 'Diffusez les enquêtes formateurs fin de formation ou annuelles depuis le moteur questionnaire.',
        'result_title' => 'Résultats des enquêtes formateurs',
        'result_description' => 'Centralisez les réponses, relances et verbatims des enquêtes formateurs.',
        'settings_title' => 'Paramètres des enquêtes formateurs',
        'settings_description' => 'Réglages par défaut appliqués aux envois d’enquêtes formateurs.',
        'create_session_label' => 'Créer un envoi formateur',
      ),
      'company_survey' => array(
        'label_plural' => 'Enquêtes entreprises',
        'label_singular' => 'enquête entreprise',
        'session_title' => 'Envois des enquêtes entreprises',
        'session_description' => 'Diffusez les enquêtes entreprises aux signataires ou contacts d’entreprise ciblés.',
        'result_title' => 'Résultats des enquêtes entreprises',
        'result_description' => 'Centralisez les réponses, relances et verbatims des enquêtes entreprises.',
        'settings_title' => 'Paramètres des enquêtes entreprises',
        'settings_description' => 'Réglages par défaut appliqués aux envois d’enquêtes entreprises.',
        'create_session_label' => 'Créer un envoi entreprise',
      ),
      'funder_survey' => array(
        'label_plural' => 'Enquêtes financeurs',
        'label_singular' => 'enquête financeur',
        'session_title' => 'Envois des enquêtes financeurs',
        'session_description' => 'Diffusez les enquêtes financeurs par dossier ou en campagne annuelle.',
        'result_title' => 'Résultats des enquêtes financeurs',
        'result_description' => 'Centralisez les réponses, relances et verbatims des enquêtes financeurs.',
        'settings_title' => 'Paramètres des enquêtes financeurs',
        'settings_description' => 'Réglages par défaut appliqués aux envois d’enquêtes financeurs.',
        'create_session_label' => 'Créer un envoi financeur',
      ),
      '' => array(
        'label_plural' => 'Questionnaires',
        'label_singular' => 'questionnaire',
        'session_title' => 'Sessions de questionnaires',
        'session_description' => 'Créez des sessions pilotées par le formateur à partir des quiz, tests de positionnement et évaluations des acquis.',
        'result_title' => 'Résultats des sessions',
        'result_description' => 'Toutes les réponses sont centralisées par session, participant et question.',
        'settings_title' => 'Paramètres questionnaires',
        'settings_description' => 'Réglages globaux du moteur de sessions questionnaires.',
        'create_session_label' => 'Créer une session de questionnaire',
      ),
    );

    return isset( $map[ $context ] ) ? $map[ $context ] : $map[''];
  }


  private function get_questionnaire_current_context() {
    if ( isset( $_GET['source_type'] ) ) {
      return sanitize_text_field( wp_unslash( $_GET['source_type'] ) );
    }
    if ( isset( $_GET['from_context'] ) ) {
      return sanitize_text_field( wp_unslash( $_GET['from_context'] ) );
    }
    return '';
  }


  private function get_questionnaire_sessions( $filters = array() ) {
    global $wpdb;
    $where = array( '1=1' );
    $params = array();
    if ( ! empty( $filters['source_type'] ) ) { $where[] = 'source_type = %s'; $params[] = $filters['source_type']; }
    if ( ! empty( $filters['source_id'] ) ) { $where[] = 'source_id = %d'; $params[] = absint( $filters['source_id'] ); }
    if ( ! empty( $filters['status'] ) ) { $where[] = 'status = %s'; $params[] = $filters['status']; }
    if ( ! empty( $filters['formation_id'] ) ) { $where[] = 'formation_id = %d'; $params[] = absint( $filters['formation_id'] ); }
    if ( ! empty( $filters['search'] ) ) {
      $where[] = '(session_title LIKE %s OR public_token LIKE %s)';
      $like = '%' . $wpdb->esc_like( (string) $filters['search'] ) . '%';
      $params[] = $like;
      $params[] = $like;
    }
    $sql = "SELECT * FROM {$this->questionnaire_session_table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC, id DESC';
    if ( ! empty( $params ) ) { $sql = $wpdb->prepare( $sql, $params ); }
    return $wpdb->get_results( $sql );
  }


  private function get_questionnaire_session( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->questionnaire_session_table} WHERE id = %d", absint( $id ) ) );
  }


  private function get_questionnaire_session_by_token( $token ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->questionnaire_session_table} WHERE public_token = %s", (string) $token ) );
  }


  private function get_questionnaire_participant_by_token( $participant_token ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->questionnaire_participant_table} WHERE participant_token = %s", (string) $participant_token ) );
  }


  private function get_questionnaire_session_participants( $session_id ) {
    global $wpdb;
    return $wpdb->get_results( $wpdb->prepare( "SELECT p.*, l.first_name, l.last_name FROM {$this->questionnaire_participant_table} p LEFT JOIN {$this->learner_table} l ON l.id = p.apprenant_id WHERE p.session_id = %d ORDER BY p.joined_at ASC, p.id ASC", absint( $session_id ) ) );
  }

  /**
   * ACDC 3.20.105 — Méthode manquante (bug pré-existant à 3.20.104).
   * Récupère un participant unique par son ID, avec jointure sur la table apprenant
   * pour exposer first_name / last_name comme la version pluriel.
   * Retourne un objet stdClass ou null si l'ID n'existe pas.
   */
  private function get_questionnaire_session_participant( $participant_id ) {
    global $wpdb;
    $participant_id = absint( $participant_id );
    if ( ! $participant_id ) { return null; }
    return $wpdb->get_row( $wpdb->prepare( "SELECT p.*, l.first_name, l.last_name FROM {$this->questionnaire_participant_table} p LEFT JOIN {$this->learner_table} l ON l.id = p.apprenant_id WHERE p.id = %d LIMIT 1", $participant_id ) );
  }


  private function get_registered_learners_for_questionnaire_session( $session ) {
    global $wpdb;
    $session_id = ! empty( $session->seance_id ) ? absint( $session->seance_id ) : 0;
    $formation_id = ! empty( $session->formation_id ) ? absint( $session->formation_id ) : 0;
    if ( $session_id ) {
      /* ACDC 3.25.267 — Les apprenants d'une séance, par les trois
         rattachements. Sur le seul session_id, une enquête de séance née d'une
         convention ne ciblait personne — et rien ne le disait. */
      $seance_row       = method_exists( $this, 'get_session' ) ? $this->get_session( $session_id ) : null;
      $session_learners = ( $seance_row && method_exists( $this, 'acdc_session_learners' ) )
        ? (array) $this->acdc_session_learners( $seance_row )
        : array();
      if ( ! empty( $session_learners ) ) {
        return $session_learners;
      }
    }
    /* ACDC 3.25.157 — Une session SANS PÉRIMÈTRE ne cible personne.
       Sans séance ni formation liée, la boucle ci-dessous ne filtrait rien et
       retenait les apprenants de TOUTES les inscriptions du site : une session
       créée pour un essai arrivait avec de vrais apprenants déjà ciblés, et un
       clic sur « Envoyer les accès » leur écrivait. Un périmètre vide doit
       produire une cible vide, jamais l'annuaire entier. */
    if ( ! $session_id && ! $formation_id ) {
      return array();
    }
    $learner_ids = array();
    $registrations = $wpdb->get_results( "SELECT learner_id, learner_ids, formation_id FROM {$this->training_registration_table} ORDER BY id DESC" );
    foreach ( (array) $registrations as $registration ) {
      if ( $formation_id && (int) $registration->formation_id !== $formation_id ) { continue; }
      if ( ! empty( $registration->learner_id ) ) { $learner_ids[] = (int) $registration->learner_id; }
      if ( ! empty( $registration->learner_ids ) ) {
        $decoded = json_decode( (string) $registration->learner_ids, true );
        if ( is_array( $decoded ) ) {
          foreach ( $decoded as $entry ) { $learner_ids[] = absint( is_array( $entry ) && isset( $entry['id'] ) ? $entry['id'] : $entry ); }
        }
      }
    }
    $learner_ids = array_values( array_unique( array_filter( array_map( 'absint', $learner_ids ) ) ) );
    /* ACDC 3.25.157 — Une formation sans aucune inscription ne cible PERSONNE.
       Le repli précédent renvoyait get_learners(), c'est-à-dire l'annuaire complet :
       l'absence de destinataire était traitée comme « tous les destinataires »,
       exactement l'inverse de l'intention. */
    if ( empty( $learner_ids ) ) { return array(); }
    $in = implode( ',', array_map( 'absint', $learner_ids ) );
    return $wpdb->get_results( "SELECT * FROM {$this->learner_table} WHERE id IN ($in) ORDER BY first_name ASC, last_name ASC" );
  }


  private function get_questionnaire_session_settings_array( $session ) {
    if ( ! $session || empty( $session->session_settings_json ) ) {
      return array();
    }
    $decoded = json_decode( (string) $session->session_settings_json, true );
    return is_array( $decoded ) ? $decoded : array();
  }


  private function get_questionnaire_selected_learner_ids_for_session( $session ) {
    $settings = $this->get_questionnaire_session_settings_array( $session );
    $ids      = isset( $settings['target_learner_ids'] ) && is_array( $settings['target_learner_ids'] ) ? $settings['target_learner_ids'] : array();
    return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
  }


  private function get_questionnaire_targeted_learners_for_questionnaire_session( $session ) {
    $learners      = $this->get_registered_learners_for_questionnaire_session( $session );
    $selected_ids  = $this->get_questionnaire_selected_learner_ids_for_session( $session );
    if ( empty( $selected_ids ) ) {
      return $learners;
    }
    $filtered = array();
    foreach ( (array) $learners as $learner ) {
      if ( in_array( (int) $learner->id, $selected_ids, true ) ) {
        $filtered[] = $learner;
      }
    }
    return $filtered;
  }


  private function is_questionnaire_annual_campaign_source( $source = null ) {
    if ( empty( $source ) || ! is_array( $source ) || empty( $source['record'] ) || ! is_object( $source['record'] ) ) {
      return false;
    }
    $scope = isset( $source['record']->scope ) ? sanitize_key( (string) $source['record']->scope ) : '';
    return 'annual' === $scope;
  }


  private function get_questionnaire_annual_campaign_year_value( $session = null, $source = null ) {
    if ( $session && ! empty( $session->annual_campaign_year ) ) {
      return absint( $session->annual_campaign_year );
    }
    if ( ! empty( $source ) && is_array( $source ) && ! empty( $source['record'] ) && is_object( $source['record'] ) && ! empty( $source['record']->annual_campaign_year ) ) {
      return absint( $source['record']->annual_campaign_year );
    }
    return (int) gmdate( 'Y' );
  }


  private function get_active_trainers_for_annual_questionnaire_campaign() {
    $trainers = $this->get_trainers();
    $results = array();
    foreach ( (array) $trainers as $trainer ) {
      if ( empty( $trainer->email ) || ! is_email( $trainer->email ) ) {
        continue;
      }
      if ( isset( $trainer->access_enabled ) && ! (int) $trainer->access_enabled ) {
        continue;
      }
      $results[] = $trainer;
    }
    return array_values( $results );
  }


  private function get_active_funders_for_annual_questionnaire_campaign() {
    $funders = $this->get_funders();
    $results = array();
    foreach ( (array) $funders as $funder ) {
      if ( empty( $funder->email ) || ! is_email( $funder->email ) ) {
        continue;
      }
      $results[] = $funder;
    }
    return array_values( $results );
  }


  private function get_questionnaire_annual_campaign_preview_count( $source_type, $session = null ) {
    if ( 'trainer_survey' === $source_type ) {
      if ( $session && ! empty( $session->formateur_id ) ) {
        return 1;
      }
      return count( $this->get_active_trainers_for_annual_questionnaire_campaign() );
    }
    if ( 'funder_survey' === $source_type ) {
      if ( $session && ! empty( $session->funder_id ) ) {
        return 1;
      }
      return count( $this->get_active_funders_for_annual_questionnaire_campaign() );
    }
    return 0;
  }


  private function get_questionnaire_delivery_targets( $session ) {
    $targets = array();
    $source_type = isset( $session->source_type ) ? (string) $session->source_type : '';
    $source = $this->get_questionnaire_source_data( $source_type, ! empty( $session->source_id ) ? (int) $session->source_id : 0 );
    $is_annual_campaign = $this->is_questionnaire_annual_campaign_source( $source );

    if ( $this->is_learner_questionnaire_source_type( $source_type ) || in_array( $source_type, array( 'quiz', 'positioning_test', 'evaluation' ), true ) ) {
      foreach ( (array) $this->get_questionnaire_targeted_learners_for_questionnaire_session( $session ) as $learner ) {
        $email = ! empty( $learner->email ) ? sanitize_email( (string) $learner->email ) : '';
        if ( ! $email || ! is_email( $email ) ) {
          continue;
        }
        $targets[] = array(
          'recipient_type' => 'learner',
          'apprenant_id' => (int) $learner->id,
          'email' => $email,
          'full_name' => trim( (string) ( $learner->first_name ?? '' ) . ' ' . (string) ( $learner->last_name ?? '' ) ),
          'pseudo' => trim( (string) ( $learner->first_name ?? '' ) . ' ' . (string) ( $learner->last_name ?? '' ) ),
        );
      }
      return $targets;
    }

    if ( 'trainer_survey' === $source_type ) {
      if ( $is_annual_campaign ) {
        $annual_trainers = array();
        $trainer_id = ! empty( $session->formateur_id ) ? absint( $session->formateur_id ) : 0;
        if ( $trainer_id ) {
          $single_trainer = $this->get_trainer( $trainer_id );
          if ( $single_trainer ) {
            $annual_trainers[] = $single_trainer;
          }
        } else {
          $annual_trainers = $this->get_active_trainers_for_annual_questionnaire_campaign();
        }
        foreach ( (array) $annual_trainers as $trainer ) {
          if ( empty( $trainer->email ) || ! is_email( $trainer->email ) ) {
            continue;
          }
          $targets[] = array(
            'recipient_type' => 'trainer',
            'trainer_id' => (int) $trainer->id,
            'email' => sanitize_email( (string) $trainer->email ),
            'full_name' => trim( (string) $trainer->first_name . ' ' . (string) $trainer->last_name ),
            'pseudo' => trim( (string) $trainer->first_name . ' ' . (string) $trainer->last_name ),
          );
        }
        return $targets;
      }
      $trainer_id = ! empty( $session->formateur_id ) ? absint( $session->formateur_id ) : 0;
      $trainer = $trainer_id ? $this->get_trainer( $trainer_id ) : null;
      if ( $trainer && ! empty( $trainer->email ) && is_email( $trainer->email ) ) {
        $targets[] = array(
          'recipient_type' => 'trainer',
          'trainer_id' => (int) $trainer->id,
          'email' => sanitize_email( (string) $trainer->email ),
          'full_name' => trim( (string) $trainer->first_name . ' ' . (string) $trainer->last_name ),
          'pseudo' => trim( (string) $trainer->first_name . ' ' . (string) $trainer->last_name ),
        );
      }
      return $targets;
    }

    if ( 'company_survey' === $source_type ) {
      $contact = ! empty( $session->company_contact_id ) ? $this->get_contact( (int) $session->company_contact_id ) : null;
      if ( ! $contact && ! empty( $session->company_id ) ) {
        $related = $this->get_related_contacts( (int) $session->company_id );
        $contact = ! empty( $related ) ? $related[0] : null;
      }
      if ( $contact && ! empty( $contact->email ) && is_email( $contact->email ) ) {
        $targets[] = array(
          'recipient_type' => 'company_signatory',
          'company_contact_id' => (int) $contact->id,
          'email' => sanitize_email( (string) $contact->email ),
          'full_name' => trim( (string) ( $contact->first_name ?? '' ) . ' ' . (string) ( $contact->last_name ?? '' ) ),
          'pseudo' => trim( (string) ( $contact->first_name ?? '' ) . ' ' . (string) ( $contact->last_name ?? '' ) ),
        );
      } elseif ( ! empty( $session->company_id ) ) {
        $company = $this->get_company( (int) $session->company_id );
        if ( $company && ! empty( $company->enterprise_contact_email ) && is_email( $company->enterprise_contact_email ) ) {
          $targets[] = array(
            'recipient_type' => 'company_signatory',
            'company_contact_id' => 0,
            'email' => sanitize_email( (string) $company->enterprise_contact_email ),
            'full_name' => ! empty( $company->name ) ? (string) $company->name : 'Entreprise',
            'pseudo' => ! empty( $company->name ) ? (string) $company->name : 'Entreprise',
          );
        }
      }
      return $targets;
    }

    if ( 'funder_survey' === $source_type ) {
      $annual_funders = array();
      if ( $is_annual_campaign ) {
        if ( ! empty( $session->funder_id ) ) {
          $single_funder = $this->get_funder( (int) $session->funder_id );
          if ( $single_funder ) {
            $annual_funders[] = $single_funder;
          }
        } else {
          $annual_funders = $this->get_active_funders_for_annual_questionnaire_campaign();
        }
      } else {
        $single_funder = ! empty( $session->funder_id ) ? $this->get_funder( (int) $session->funder_id ) : null;
        if ( $single_funder ) {
          $annual_funders[] = $single_funder;
        }
      }
      foreach ( (array) $annual_funders as $funder ) {
        if ( empty( $funder->email ) || ! is_email( $funder->email ) ) {
          continue;
        }
        $funder_name = ! empty( $funder->name ) ? (string) $funder->name : 'Financeur';
        $targets[] = array(
          'recipient_type' => 'funder_contact',
          'funder_id' => (int) $funder->id,
          'funder_contact_id' => ! empty( $session->funder_contact_id ) ? (int) $session->funder_contact_id : 0,
          'email' => sanitize_email( (string) $funder->email ),
          'full_name' => $funder_name,
          'pseudo' => $funder_name,
        );
      }
      return $targets;
    }

    return $targets;
  }


  private function get_questionnaire_delivery_targets_count( $session ) {
    return count( $this->get_questionnaire_delivery_targets( $session ) );
  }


  private function ensure_questionnaire_participant_for_target( $session, $target ) {
    global $wpdb;
    $existing = null;
    $session_id = (int) $session->id;
    $recipient_type = isset( $target['recipient_type'] ) ? (string) $target['recipient_type'] : 'learner';
    if ( ! empty( $target['apprenant_id'] ) ) {
      $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->questionnaire_participant_table} WHERE session_id = %d AND apprenant_id = %d ORDER BY id DESC LIMIT 1", $session_id, (int) $target['apprenant_id'] ) );
    } elseif ( ! empty( $target['trainer_id'] ) && $wpdb->get_var( "SHOW COLUMNS FROM {$this->questionnaire_participant_table} LIKE 'trainer_id'" ) ) {
      $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->questionnaire_participant_table} WHERE session_id = %d AND trainer_id = %d ORDER BY id DESC LIMIT 1", $session_id, (int) $target['trainer_id'] ) );
    } elseif ( ! empty( $target['company_contact_id'] ) && $wpdb->get_var( "SHOW COLUMNS FROM {$this->questionnaire_participant_table} LIKE 'company_contact_id'" ) ) {
      $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->questionnaire_participant_table} WHERE session_id = %d AND company_contact_id = %d ORDER BY id DESC LIMIT 1", $session_id, (int) $target['company_contact_id'] ) );
    } elseif ( ! empty( $target['funder_contact_id'] ) && $wpdb->get_var( "SHOW COLUMNS FROM {$this->questionnaire_participant_table} LIKE 'funder_contact_id'" ) ) {
      $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->questionnaire_participant_table} WHERE session_id = %d AND funder_contact_id = %d ORDER BY id DESC LIMIT 1", $session_id, (int) $target['funder_contact_id'] ) );
    } elseif ( ! empty( $target['email'] ) ) {
      $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->questionnaire_participant_table} WHERE session_id = %d AND email = %s ORDER BY id DESC LIMIT 1", $session_id, sanitize_email( $target['email'] ) ) );
    }

    $data = array(
      'session_id' => $session_id,
      'apprenant_id' => ! empty( $target['apprenant_id'] ) ? (int) $target['apprenant_id'] : null,
      'participant_token' => $existing && ! empty( $existing->participant_token ) ? (string) $existing->participant_token : $this->generate_questionnaire_participant_token(),
      'pseudo' => ! empty( $target['pseudo'] ) ? sanitize_text_field( (string) $target['pseudo'] ) : sanitize_text_field( (string) ( $target['full_name'] ?? $target['email'] ?? 'Participant' ) ),
      'participant_status' => $existing && ! empty( $existing->participant_status ) ? (string) $existing->participant_status : 'en_attente',
      'joined_at' => $existing && ! empty( $existing->joined_at ) ? $existing->joined_at : $this->now_mysql(),
      'created_at' => $existing && ! empty( $existing->created_at ) ? $existing->created_at : $this->now_mysql(),
      'recipient_type' => $recipient_type,
      'registration_id' => ! empty( $target['registration_id'] ) ? (int) $target['registration_id'] : null,
      'trainer_id' => ! empty( $target['trainer_id'] ) ? (int) $target['trainer_id'] : null,
      'company_contact_id' => ! empty( $target['company_contact_id'] ) ? (int) $target['company_contact_id'] : null,
      'funder_contact_id' => ! empty( $target['funder_contact_id'] ) ? (int) $target['funder_contact_id'] : null,
      'email' => ! empty( $target['email'] ) ? sanitize_email( (string) $target['email'] ) : '',
      'full_name' => ! empty( $target['full_name'] ) ? sanitize_text_field( (string) $target['full_name'] ) : '',
    );

    if ( $existing ) {
      $wpdb->update( $this->questionnaire_participant_table, $data, array( 'id' => (int) $existing->id ) );
      return $this->get_questionnaire_session_participant( (int) $existing->id );
    }

    $wpdb->insert( $this->questionnaire_participant_table, $data );
    return $this->get_questionnaire_session_participant( (int) $wpdb->insert_id );
  }


  private function build_questionnaire_participant_public_url( $session, $participant ) {
    return add_query_arg(
      array(
        'token' => rawurlencode( (string) $session->public_token ),
        'participant' => rawurlencode( (string) $participant->participant_token ),
      ),
      $this->get_questionnaire_public_base_url()
    );
  }


  private function build_questionnaire_session_share_message( $session, $source = null ) {
    $source = $source ? $source : $this->get_questionnaire_source_data( $session->source_type, $session->source_id );
    $is_annual_campaign = $this->is_questionnaire_annual_campaign_source( $source );
    $annual_campaign_year = $this->get_questionnaire_annual_campaign_year_value( $session, $source );
    $formation_title = '';
    if ( ! empty( $session->formation_id ) ) {
      $formation = $this->get_formation( $session->formation_id );
      $formation_title = $formation ? $formation->title : '';
    }
    $trainer_name = '';
    if ( ! empty( $session->formateur_id ) ) {
      $trainer = $this->get_trainer( $session->formateur_id );
      $trainer_name = $trainer ? trim( $trainer->first_name . ' ' . $trainer->last_name ) : '';
    }
    $lines   = array();
    $lines[] = 'Bonjour,';
    $lines[] = '';
    $lines[] = 'Voici le lien pour rejoindre la session questionnaire : ' . $session->session_title;
    if ( $is_annual_campaign ) {
      $lines[] = 'Campagne annuelle : ' . $annual_campaign_year;
    }
    if ( $source && ! empty( $source['title'] ) ) {
      $lines[] = 'Questionnaire source : ' . $source['title'];
    }
    if ( $formation_title ) {
      $lines[] = 'Formation : ' . $formation_title;
    }
    if ( ! empty( $session->session_date ) ) {
      $lines[] = 'Date : ' . mysql2date( 'j F Y à H\hi', $session->session_date );
    }
    if ( $trainer_name ) {
      $lines[] = 'Formateur : ' . $trainer_name;
    }
    $lines[] = '';
    $lines[] = 'Lien apprenant : ' . $session->public_url;
    $lines[] = '';
    $lines[] = 'Merci de vous connecter depuis votre ordinateur ou smartphone et de renseigner votre pseudo de session.';
    return implode( "
", $lines );
  }


  private function build_questionnaire_session_mailto_url( $session, $source = null ) {
    $targets = $this->get_questionnaire_delivery_targets( $session );
    $emails = array();
    foreach ( (array) $targets as $target ) {
      if ( ! empty( $target['email'] ) && is_email( $target['email'] ) ) {
        $emails[] = sanitize_email( (string) $target['email'] );
      }
    }
    $emails = array_values( array_unique( array_filter( $emails ) ) );
    if ( empty( $emails ) ) {
      return '';
    }
    $subject = $session->session_title;
    $body    = $this->build_questionnaire_session_share_message( $session, $source );
    return 'mailto:?bcc=' . rawurlencode( implode( ',', $emails ) ) . '&subject=' . rawurlencode( $subject ) . '&body=' . rawurlencode( $body );
  }


  private function get_questionnaire_mail_settings() {
    $defaults = array(
      'sender_name' => 'ACDC-Formation',
      /* ACDC 3.25.290 — L'adresse d'expédition par défaut était écrite en dur :
         un changement d'entité laissait les questionnaires partir de l'ancienne
         boîte. À défaut de fiche renseignée, WordPress décidera. */
      'sender_email' => '',
      'reply_to' => '',
    );
    $settings = $this->get_marketing_store( 'settings', array() );
    $settings = is_array( $settings ) ? $settings : array();
    return wp_parse_args( $settings, $defaults );
  }


  private function update_questionnaire_session_settings( $session_id, $extra ) {
    global $wpdb;
    $session = $this->get_questionnaire_session( $session_id );
    if ( ! $session ) {
      return false;
    }
    $settings = $this->get_questionnaire_session_settings_array( $session );
    foreach ( (array) $extra as $key => $value ) {
      $settings[ $key ] = $value;
    }
    $encoded = wp_json_encode( $settings );
    return false !== $wpdb->update(
      $this->questionnaire_session_table,
      array(
        'session_settings_json' => $encoded,
        'updated_at' => $this->now_mysql(),
      ),
      array( 'id' => absint( $session_id ) )
    );
  }


  private function get_questionnaire_session_send_stats( $session ) {
    $settings = $this->get_questionnaire_session_settings_array( $session );
    return array(
      'last_sent_at' => isset( $settings['last_email_sent_at'] ) ? (string) $settings['last_email_sent_at'] : '',
      'sent_count' => isset( $settings['last_email_sent_count'] ) ? absint( $settings['last_email_sent_count'] ) : 0,
      'failed_count' => isset( $settings['last_email_failed_count'] ) ? absint( $settings['last_email_failed_count'] ) : 0,
      'target_count' => isset( $settings['last_email_target_count'] ) ? absint( $settings['last_email_target_count'] ) : 0,
    );
  }


  private function send_questionnaire_session_emails( $session, $source, $targets = array() ) {
    global $wpdb;
    $mail_settings = $this->get_questionnaire_mail_settings();
    $sender_name = sanitize_text_field( (string) $mail_settings['sender_name'] );
    $sender_email = sanitize_email( (string) $mail_settings['sender_email'] );
    $reply_to = sanitize_email( ! empty( $mail_settings['reply_to'] ) ? (string) $mail_settings['reply_to'] : $sender_email );
    $source_scope_data = $source ? $source : $this->get_questionnaire_source_data( $session->source_type, $session->source_id );
    $is_annual_campaign = $this->is_questionnaire_annual_campaign_source( $source_scope_data );
    $annual_campaign_year = $this->get_questionnaire_annual_campaign_year_value( $session, $source_scope_data );
    $subject = $session->session_title . ( $is_annual_campaign ? ' — campagne annuelle ' . $annual_campaign_year : '' );
    $headers = array( 'Content-Type: text/html; charset=UTF-8' );
    if ( $sender_email ) {
      $headers[] = 'From: ' . ( $sender_name ? $sender_name . ' <' . $sender_email . '>' : $sender_email );
    }
    if ( $reply_to ) {
      $headers[] = 'Reply-To: ' . $reply_to;
    }
    if ( empty( $targets ) || ! is_array( $targets ) ) {
      $targets = $this->get_questionnaire_delivery_targets( $session );
    }
    $sent = 0;
    $failed = 0;
    $target = 0;
    foreach ( (array) $targets as $recipient ) {
      $email = ! empty( $recipient['email'] ) ? sanitize_email( (string) $recipient['email'] ) : '';
      if ( ! $email || ! is_email( $email ) ) {
        continue;
      }
      $target++;
      $participant = $this->ensure_questionnaire_participant_for_target( $session, $recipient );
      if ( ! $participant ) {
        $failed++;
        continue;
      }
      $recipient_name = $this->get_questionnaire_session_participant_display_name( $participant );
      $url = $this->build_questionnaire_participant_public_url( $session, $participant );
      $body  = '<p>Bonjour ' . esc_html( $recipient_name ) . ',</p>';
      $body .= '<p>Vous pouvez répondre au questionnaire <strong>' . esc_html( $session->session_title ) . '</strong>.</p>';
      if ( $is_annual_campaign ) {
        $body .= '<p><strong>Campagne annuelle :</strong> ' . esc_html( (string) $annual_campaign_year ) . '</p>';
      }
      if ( $source && ! empty( $source['title'] ) && $source['title'] !== $session->session_title ) {
        $body .= '<p><strong>Questionnaire :</strong> ' . esc_html( $source['title'] ) . '</p>';
      }
      if ( ! empty( $session->deadline_at ) ) {
        $body .= '<p><strong>Date limite :</strong> ' . esc_html( mysql2date( 'j F Y à H\hi', $session->deadline_at ) ) . '</p>';
      }
      $body .= '<p><a href="' . esc_url( $url ) . '">Répondre au questionnaire</a></p>';
      $body .= '<p>Ce lien est personnel et sécurisé.</p>';
      if ( $this->acdc_send_transactional_email( $email, $subject, array( 'greeting_name' => $recipient_name, 'intro_html' => '', 'body_html' => $body, 'footer_notice' => 'Cet e-mail a été envoyé dans le cadre du suivi de votre questionnaire. Vos données sont traitées conformément au RGPD.' ), array( 'source_module' => 'questionnaires', 'source_action' => 'questionnaire_access', 'email_category' => 'questionnaire', 'email_audience' => 'destinataire' ) ) ) {
        $sent++;
        $wpdb->update(
          $this->questionnaire_participant_table,
          array( 'email' => $email, 'full_name' => $recipient_name ),
          array( 'id' => (int) $participant->id ),
          array( '%s', '%s' ),
          array( '%d' )
        );
      } else {
        $failed++;
      }
    }
    if ( $target > 0 ) {
      $wpdb->update(
        $this->questionnaire_session_table,
        array(
          'status' => 'envoyee',
          'dispatch_sent_at' => $this->now_mysql(),
          'updated_at' => $this->now_mysql(),
        ),
        array( 'id' => (int) $session->id ),
        array( '%s', '%s', '%s' ),
        array( '%d' )
      );
    }
    return array( 'sent' => $sent, 'failed' => $failed, 'target' => $target );
  }


  private function count_questionnaire_session_answers_for_index( $session_id, $question_index ) {
    global $wpdb;
    return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->questionnaire_answer_table} WHERE session_id = %d AND question_index = %d", absint( $session_id ), absint( $question_index ) ) );
  }


  private function get_questionnaire_session_answer_for_participant( $session_id, $participant_id, $question_index ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->questionnaire_answer_table} WHERE session_id = %d AND participant_id = %d AND question_index = %d", absint( $session_id ), absint( $participant_id ), absint( $question_index ) ) );
  }


  private function get_questionnaire_session_participant_score( $session_id, $participant_id ) {
    global $wpdb;
    $participant = $this->get_questionnaire_session_participant( $participant_id );
    if ( $participant && isset( $participant->final_score ) && '' !== (string) $participant->final_score ) {
      return round( (float) $participant->final_score, 2 );
    }
    return (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(points_awarded),0) FROM {$this->questionnaire_answer_table} WHERE session_id = %d AND participant_id = %d", absint( $session_id ), absint( $participant_id ) ) );
  }


  private function get_questionnaire_session_summary_rows( $filters = array() ) {
    $rows = array();
    foreach ( $this->get_questionnaire_sessions( $filters ) as $session ) {
      $participants = $this->get_questionnaire_session_participants( $session->id );
      $answers_count = $this->count_questionnaire_session_answers_for_index( $session->id, $session->current_question_index );
      $source = $this->get_questionnaire_source_data( $session->source_type, $session->source_id );
      $rows[] = array(
        'session' => $session,
        'source' => $source,
        'participants' => $participants,
        'answers_count' => $answers_count,
      );
    }
    return $rows;
  }


  private function get_questionnaire_session_stats( $filters = array() ) {
    $sessions = $this->get_questionnaire_sessions( $filters );
    $stats = array(
      'total' => count( $sessions ),
      'active' => 0,
      'finished' => 0,
      'participants' => 0,
      'responses' => 0,
      'alerts' => 0,
      'average_score' => null,
      'response_rate' => null,
    );
    $score_sum = 0;
    $score_count = 0;
    foreach ( $sessions as $session ) {
      if ( in_array( $session->status, array( 'en_cours', 'ouverte', 'en_pause' ), true ) ) {
        $stats['active']++;
      }
      if ( 'terminee' === $session->status ) {
        $stats['finished']++;
      }
      $participants = $this->get_questionnaire_session_participants( $session->id );
      $stats['participants'] += count( $participants );
      foreach ( (array) $participants as $participant ) {
        if ( in_array( (string) ( $participant->participant_status ?? '' ), array( 'repondu', 'termine' ), true ) ) {
          $stats['responses']++;
        }
        if ( ! empty( $participant->alert_flag ) ) {
          $stats['alerts']++;
        }
        if ( in_array( (string) ( $participant->participant_status ?? '' ), array( 'repondu', 'termine' ), true ) ) {
          $score = $this->get_questionnaire_session_participant_score( $session->id, $participant->id );
          if ( '' !== (string) $score && null !== $score ) {
            $score_sum += (float) $score;
            $score_count++;
          }
        }
      }
    }
    if ( $stats['participants'] > 0 ) {
      $stats['response_rate'] = round( ( $stats['responses'] / $stats['participants'] ) * 100, 1 );
    }
    if ( $score_count > 0 ) {
      $stats['average_score'] = round( $score_sum / $score_count, 2 );
    }
    return $stats;
  }


  private function get_questionnaire_session_response_count( $session_id ) {
    global $wpdb;
    return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->questionnaire_participant_table} WHERE session_id = %d AND participant_status IN ('repondu','termine')", absint( $session_id ) ) );
  }


  private function get_questionnaire_session_alert_count( $session_id ) {
    global $wpdb;
    return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->questionnaire_participant_table} WHERE session_id = %d AND alert_flag = 1", absint( $session_id ) ) );
  }


  private function get_questionnaire_session_average_score( $session_id ) {
    $participants = $this->get_questionnaire_session_participants( $session_id );
    $sum = 0;
    $count = 0;
    foreach ( (array) $participants as $participant ) {
      if ( ! in_array( (string) ( $participant->participant_status ?? '' ), array( 'repondu', 'termine' ), true ) ) {
        continue;
      }
      $score = $this->get_questionnaire_session_participant_score( $session_id, $participant->id );
      if ( '' !== (string) $score && null !== $score ) {
        $sum += (float) $score;
        $count++;
      }
    }
    return $count > 0 ? round( $sum / $count, 2 ) : null;
  }


  private function get_questionnaire_participant_answer_count( $session_id, $participant_id ) {
    global $wpdb;
    return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->questionnaire_answer_table} WHERE session_id = %d AND participant_id = %d", absint( $session_id ), absint( $participant_id ) ) );
  }


  private function get_questionnaire_context_export_rows( $filters = array() ) {
    $rows = array();
    foreach ( $this->get_questionnaire_sessions( $filters ) as $session ) {
      $source = $this->get_questionnaire_source_data( $session->source_type, $session->source_id );
      $session_rows = $this->build_questionnaire_results_export_rows( $session->id );
      foreach ( $session_rows as $row ) {
        $row['source_type'] = isset( $session->source_type ) ? (string) $session->source_type : '';
        $row['source_id'] = isset( $session->source_id ) ? (int) $session->source_id : 0;
        $row['source_title'] = $source && ! empty( $source['title'] ) ? (string) $source['title'] : '';
        $row['session_id'] = (int) $session->id;
        $rows[] = $row;
      }
    }
    return $rows;
  }


  private function get_questionnaire_context_export_url( $format, $source_type = '', $source_id = 0 ) {
    $format = in_array( $format, array( 'csv', 'excel', 'pdf' ), true ) ? $format : 'csv';
    $action = 'acdc_export_questionnaire_context_results_' . $format;
    $args = array( 'action' => $action );
    if ( '' !== $source_type ) {
      $args['source_type'] = (string) $source_type;
    }
    if ( $source_id > 0 ) {
      $args['source_id'] = (int) $source_id;
    }
    $url = admin_url( 'admin-post.php?' . http_build_query( $args ) );
    return wp_nonce_url( $url, 'acdc_export_questionnaire_context_results_' . $format . '_' . (string) $source_type . '_' . (int) $source_id );
  }


  private function get_questionnaire_results_url( $source_type = '', $session_id = 0, $source_id = 0, $participant_id = 0 ) {
    $args = array( 'tab' => 'questionnaire_results' );
    if ( '' !== $source_type ) { $args['source_type'] = (string) $source_type; }
    if ( $source_id > 0 ) { $args['source_id'] = (int) $source_id; }
    if ( $session_id > 0 ) { $args['session_id'] = (int) $session_id; }
    if ( $participant_id > 0 ) { $args['participant_id'] = (int) $participant_id; }
    return is_admin() ? admin_url( 'admin.php?page=acdc-of-questionnaire-results&' . http_build_query( $args ) ) : $this->portal_page_url( $args );
  }


  private function get_questionnaire_session_count_for_source( $source_type, $source_id ) {
    global $wpdb;
    return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->questionnaire_session_table} WHERE source_type = %s AND source_id = %d", (string) $source_type, absint( $source_id ) ) );
  }


  private function get_questionnaire_sessions_url( $source_type = '', $source_id = 0 ) {
    $args = array( 'tab' => 'questionnaire_sessions' );
    if ( $source_type ) { $args['source_type'] = $source_type; }
    if ( $source_id ) { $args['source_id'] = (int) $source_id; }
    return is_admin() ? admin_url( 'admin.php?page=acdc-of-questionnaire-sessions' . ( ! empty( $args ) ? '&' . http_build_query( $args ) : '' ) ) : $this->portal_page_url( $args );
  }


  private function get_questionnaire_new_session_url( $source_type, $source_id ) {
    $args = array( 'action' => 'new', 'source_type' => (string) $source_type, 'source_id' => (int) $source_id );
    return is_admin() ? admin_url( 'admin.php?page=acdc-of-questionnaire-sessions&' . http_build_query( $args ) ) : $this->portal_page_url( array_merge( array( 'tab' => 'questionnaire_sessions' ), $args ) );
  }



  private function get_questionnaire_source_duration_label( $source ) {
    $minutes = ! empty( $source['duration_minutes'] ) ? absint( $source['duration_minutes'] ) : 0;
    if ( $minutes <= 0 ) {
      return 'Durée non renseignée';
    }
    return $minutes . ' minute' . ( $minutes > 1 ? 's' : '' );
  }


  private function get_questionnaire_session_question_rows( $session_id, $source ) {
    $rows = array();
    $questions = ! empty( $source['questions'] ) && is_array( $source['questions'] ) ? array_values( $source['questions'] ) : array();
    foreach ( $questions as $index => $question ) {
      $choices = $this->parse_question_choices( $question );
      $has_correct = false;
      foreach ( $choices as $choice ) {
        if ( ! empty( $choice['is_correct'] ) ) {
          $has_correct = true;
          break;
        }
      }
      global $wpdb;
      $total_answers = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->questionnaire_answer_table} WHERE session_id = %d AND question_index = %d", absint( $session_id ), absint( $index ) ) );
      $correct_answers = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->questionnaire_answer_table} WHERE session_id = %d AND question_index = %d AND is_correct = 1", absint( $session_id ), absint( $index ) ) );
      $rows[] = array(
        'index' => $index,
        'label' => isset( $question['label'] ) ? (string) $question['label'] : 'Question',
        'type' => isset( $question['type'] ) ? (string) $question['type'] : '',
        'total_answers' => $total_answers,
        'correct_answers' => $correct_answers,
        'success_rate' => ( $has_correct && $total_answers > 0 ) ? round( ( $correct_answers / $total_answers ) * 100 ) : null,
        'has_correct' => $has_correct,
      );
    }
    return $rows;
  }


  private function get_questionnaire_session_recent_answers( $session_id, $question_index, $limit = 10 ) {
    global $wpdb;
    $limit = max( 1, absint( $limit ) );
    $sql = $wpdb->prepare(
      "SELECT a.*, p.pseudo FROM {$this->questionnaire_answer_table} a LEFT JOIN {$this->questionnaire_participant_table} p ON p.id = a.participant_id WHERE a.session_id = %d AND a.question_index = %d ORDER BY a.answered_at DESC LIMIT %d",
      absint( $session_id ),
      absint( $question_index ),
      $limit
    );
    return $wpdb->get_results( $sql );
  }


  private function get_questionnaire_default_settings_array() {
    $settings = get_option( 'acdc_of_questionnaire_settings', array() );
    return array(
      'show_final_score_default' => empty( $settings['show_final_score_default'] ) ? '0' : '1',
      'pseudo_required_default' => empty( $settings['pseudo_required_default'] ) ? '0' : '1',
      'restrict_to_registered_default' => empty( $settings['restrict_to_registered_default'] ) ? '0' : '1',
      'new_session_status_default' => ( isset( $settings['new_session_status_default'] ) && 'prete' === $settings['new_session_status_default'] ) ? 'prete' : 'brouillon',
    );
  }


  private function get_questionnaire_actions_rows( $session_id, $participant_id = 0 ) {
    global $wpdb;
    $session_id = absint( $session_id );
    $participant_id = absint( $participant_id );
    if ( ! $session_id ) {
      return array();
    }
    if ( $participant_id > 0 ) {
      return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->questionnaire_action_table} WHERE questionnaire_session_id = %d AND participant_id = %d ORDER BY created_at DESC, id DESC", $session_id, $participant_id ) );
    }
    return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->questionnaire_action_table} WHERE questionnaire_session_id = %d ORDER BY created_at DESC, id DESC", $session_id ) );
  }



  private function get_questionnaire_action_statuses_map() {
    return array(
      'ouverte' => 'Ouverte',
      'en_cours' => 'En cours',
      'traitee' => 'Traitée',
      'annulee' => 'Annulée',
    );
  }


  private function get_questionnaire_action_type_labels() {
    return array(
      'improvement' => 'Amélioration',
      'alert' => 'Alerte',
      'complaint' => 'Réclamation',
      'followup' => 'Suivi',
    );
  }


  private function get_questionnaire_action_assignees() {
    $users = get_users( array(
      'orderby' => 'display_name',
      'order' => 'ASC',
      'fields' => array( 'ID', 'display_name' ),
    ) );
    return is_array( $users ) ? $users : array();
  }


  private function get_questionnaire_action_stats( $actions ) {
    $stats = array(
      'total' => 0,
      'ouverte' => 0,
      'en_cours' => 0,
      'traitee' => 0,
      'annulee' => 0,
      'en_retard' => 0,
      'aujourd_hui' => 0,
      'echeance_proche' => 0,
      'priority_high' => 0,
      'priority_medium' => 0,
      'priority_low' => 0,
    );
    foreach ( (array) $actions as $action ) {
      $status = ! empty( $action->action_status ) ? sanitize_key( (string) $action->action_status ) : 'ouverte';
      $priority_label = $this->normalize_questionnaire_action_priority_level( ! empty( $action->priority_level ) ? (string) $action->priority_level : 'Moyenne' );
      $stats['total']++;
      if ( isset( $stats[ $status ] ) ) {
        $stats[ $status ]++;
      }
      if ( 'Haute' === $priority_label ) {
        $stats['priority_high']++;
      } elseif ( 'Basse' === $priority_label ) {
        $stats['priority_low']++;
      } else {
        $stats['priority_medium']++;
      }
      $due_state = $this->get_questionnaire_action_due_state( $action );
      if ( 'en_retard' === $due_state['state_key'] ) {
        $stats['en_retard']++;
      } elseif ( 'aujourd_hui' === $due_state['state_key'] ) {
        $stats['aujourd_hui']++;
      } elseif ( 'echeance_proche' === $due_state['state_key'] ) {
        $stats['echeance_proche']++;
      }
    }
    return $stats;
  }


  public function maybe_process_questionnaire_action_internal_followups() {
    if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
      return;
    }

    if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
      return;
    }

    $now_ts  = current_time( 'timestamp' );
    $last_ts = absint( get_option( 'acdc_of_questionnaire_action_followup_last_scan', 0 ) );
    if ( $last_ts && ( $now_ts - $last_ts ) < 900 ) {
      return;
    }

    update_option( 'acdc_of_questionnaire_action_followup_last_scan', $now_ts, false );
    $this->process_questionnaire_action_internal_followups();
  }


  private function get_questionnaire_action_internal_followup_day_key() {
    return gmdate( 'Y-m-d', current_time( 'timestamp' ) );
  }


  private function get_questionnaire_action_internal_followup_key( $row ) {
    $due_state = ! empty( $row->due_state ) && is_array( $row->due_state ) ? $row->due_state : $this->get_questionnaire_action_due_state( $row );
    $state_key = ! empty( $due_state['state_key'] ) ? sanitize_key( (string) $due_state['state_key'] ) : '';
    if ( ! in_array( $state_key, array( 'en_retard', 'aujourd_hui', 'echeance_proche' ), true ) ) {
      return '';
    }

    return $state_key . '|' . $this->get_questionnaire_action_internal_followup_day_key();
  }


  private function build_questionnaire_action_internal_followup_notification( $row ) {
    $due_state = ! empty( $row->due_state ) && is_array( $row->due_state ) ? $row->due_state : $this->get_questionnaire_action_due_state( $row );
    $state_key = ! empty( $due_state['state_key'] ) ? sanitize_key( (string) $due_state['state_key'] ) : 'a_venir';
    $assignee  = $this->get_questionnaire_action_assignee_label( ! empty( $row->assigned_to ) ? (int) $row->assigned_to : 0 );
    $source_title = ! empty( $row->source['title'] ) ? (string) $row->source['title'] : ( ! empty( $row->session_title ) ? (string) $row->session_title : 'Questionnaire' );
    $participant = ! empty( $row->participant_label ) && '—' !== (string) $row->participant_label ? (string) $row->participant_label : '';
    $due_label   = ! empty( $due_state['label'] ) ? (string) $due_state['label'] : 'Sans échéance';

    $title = 'Relance interne — action d’amélioration';
    $level = 'info';
    if ( 'en_retard' === $state_key ) {
      $title = 'Relance interne — action en retard';
      $level = 'error';
    } elseif ( 'aujourd_hui' === $state_key ) {
      $title = 'Relance interne — échéance aujourd’hui';
      $level = 'warning';
    } elseif ( 'echeance_proche' === $state_key ) {
      $title = 'Relance interne — échéance proche';
      $level = 'warning';
    }

    $parts = array();
    $parts[] = 'Responsable : ' . $assignee;
    $parts[] = 'Action : ' . (string) $row->action_label;
    $parts[] = 'Contexte : ' . $source_title;
    if ( '' !== $participant ) {
      $parts[] = 'Destinataire : ' . $participant;
    }
    $parts[] = 'Échéance : ' . $due_label;

    return array(
      'title' => $title,
      'message' => implode( ' · ', array_filter( $parts ) ),
      'level' => $level,
      'state_key' => $state_key,
      'assignee_label' => $assignee,
    );
  }


  public function process_questionnaire_action_internal_followups() {
    global $wpdb;

    $rows = $this->get_questionnaire_action_followup_rows( 3, 100 );
    if ( empty( $rows ) ) {
      return 0;
    }

    $sent = 0;
    foreach ( (array) $rows as $row ) {
      $action_id = ! empty( $row->id ) ? absint( $row->id ) : 0;
      $assigned_to = ! empty( $row->assigned_to ) ? absint( $row->assigned_to ) : 0;
      if ( ! $action_id || ! $assigned_to ) {
        continue;
      }

      $followup_key = $this->get_questionnaire_action_internal_followup_key( $row );
      if ( '' === $followup_key ) {
        continue;
      }

      if ( ! empty( $row->internal_followup_last_key ) && (string) $row->internal_followup_last_key === $followup_key ) {
        continue;
      }

      $notification = $this->build_questionnaire_action_internal_followup_notification( $row );
      $this->add_marketing_notification( $notification['title'], $notification['message'], $notification['level'] );
      $this->add_marketing_log( $notification['title'], $notification['level'], array(
        'module' => 'questionnaires',
        'action_id' => $action_id,
        'questionnaire_session_id' => ! empty( $row->questionnaire_session_id ) ? (int) $row->questionnaire_session_id : 0,
        'participant_id' => ! empty( $row->participant_id ) ? (int) $row->participant_id : 0,
        'assigned_to' => $assigned_to,
        'state_key' => $notification['state_key'],
      ) );

      $count = ! empty( $row->internal_followup_count ) ? absint( $row->internal_followup_count ) : 0;
      $wpdb->update(
        $this->questionnaire_action_table,
        array(
          'internal_followup_last_key' => $followup_key,
          'internal_followup_last_sent_at' => $this->now_mysql(),
          'internal_followup_count' => $count + 1,
          'updated_at' => $this->now_mysql(),
        ),
        array( 'id' => $action_id ),
        array( '%s', '%s', '%d', '%s' ),
        array( '%d' )
      );

      $this->log_questionnaire_event( array(
        'questionnaire_session_id' => ! empty( $row->questionnaire_session_id ) ? (int) $row->questionnaire_session_id : 0,
        'participant_id' => ! empty( $row->participant_id ) ? (int) $row->participant_id : 0,
        'event_type' => 'action_internal_followup',
        'event_label' => 'Relance interne automatique envoyée',
        'event_payload' => array(
          'action_id' => $action_id,
          'state_key' => $notification['state_key'],
          'assigned_to' => $assigned_to,
          'assigned_to_label' => $notification['assignee_label'],
          'notification_title' => $notification['title'],
          'notification_message' => $notification['message'],
          'followup_key' => $followup_key,
        ),
        'created_by' => 0,
      ) );
      $sent++;
    }

    return $sent;
  }


  private function get_questionnaire_action_followup_rows( $days_ahead = 3, $limit = 12 ) {
    global $wpdb;

    $days_ahead = max( 0, absint( $days_ahead ) );
    $limit      = max( 1, absint( $limit ) );
    $now_ts     = current_time( 'timestamp' );
    $now_mysql  = wp_date( 'Y-m-d H:i:s', $now_ts );
    $horizon    = wp_date( 'Y-m-d H:i:s', $now_ts + ( $days_ahead * DAY_IN_SECONDS ) + DAY_IN_SECONDS - 1 );

    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT a.*, s.source_type, s.source_id, s.session_title, s.status AS questionnaire_session_status
         FROM {$this->questionnaire_action_table} a
         LEFT JOIN {$this->questionnaire_session_table} s ON s.id = a.questionnaire_session_id
         WHERE a.due_at IS NOT NULL
           AND a.action_status NOT IN ('traitee','annulee')
           AND a.due_at <= %s
         ORDER BY CASE WHEN a.due_at < %s THEN 0 ELSE 1 END ASC, a.due_at ASC, a.id DESC
         LIMIT %d",
        $horizon,
        $now_mysql,
        $limit
      )
    );

    if ( ! is_array( $rows ) ) {
      return array();
    }

    foreach ( $rows as $row ) {
      $row->due_state         = $this->get_questionnaire_action_due_state( $row );
      $row->source            = ( ! empty( $row->source_type ) && ! empty( $row->source_id ) ) ? $this->get_questionnaire_source_data( (string) $row->source_type, (int) $row->source_id ) : null;
      $row->participant_label = '—';
      if ( ! empty( $row->participant_id ) ) {
        $participant = $wpdb->get_row(
          $wpdb->prepare(
            "SELECT p.*, l.first_name, l.last_name FROM {$this->questionnaire_participant_table} p LEFT JOIN {$this->learner_table} l ON l.id = p.apprenant_id WHERE p.id = %d",
            (int) $row->participant_id
          )
        );
        if ( $participant ) {
          $row->participant_label = $this->get_questionnaire_session_participant_display_name( $participant );
        }
      }
      $row->followup_url = $this->get_questionnaire_results_url(
        ! empty( $row->source_type ) ? (string) $row->source_type : '',
        ! empty( $row->questionnaire_session_id ) ? (int) $row->questionnaire_session_id : 0,
        ! empty( $row->source_id ) ? (int) $row->source_id : 0,
        ! empty( $row->participant_id ) ? (int) $row->participant_id : 0
      );
    }

    return $rows;
  }


  private function get_questionnaire_action_followup_stats( $rows ) {
    $stats = array(
      'total'       => 0,
      'en_retard'   => 0,
      'aujourd_hui' => 0,
      'a_venir'     => 0,
    );
    $today = gmdate( 'Y-m-d', current_time( 'timestamp' ) );
    foreach ( (array) $rows as $row ) {
      $stats['total']++;
      if ( ! empty( $row->due_state['is_late'] ) ) {
        $stats['en_retard']++;
        continue;
      }
      $due_date = ! empty( $row->due_at ) ? wp_date( 'Y-m-d', strtotime( (string) $row->due_at ) ) : '';
      if ( $due_date && $due_date === $today ) {
        $stats['aujourd_hui']++;
      } else {
        $stats['a_venir']++;
      }
    }
    return $stats;
  }


  private function get_questionnaire_action_followup_badge( $row ) {
    $due_state = ! empty( $row->due_state ) && is_array( $row->due_state ) ? $row->due_state : $this->get_questionnaire_action_due_state( $row );
    return array(
      'label' => ! empty( $due_state['badge_label'] ) ? (string) $due_state['badge_label'] : 'À venir',
      'class' => ! empty( $due_state['class'] ) ? (string) $due_state['class'] : 'acdc-urgency-badge acdc-urgency-badge-upcoming',
      'key'   => ! empty( $due_state['state_key'] ) ? (string) $due_state['state_key'] : 'a_venir',
    );
  }


  private function get_questionnaire_action_assignee_label( $user_id ) {
    $user_id = absint( $user_id );
    if ( ! $user_id ) {
      return 'Non assigné';
    }
    $user = get_user_by( 'id', $user_id );
    if ( ! $user ) {
      return 'Utilisateur supprimé';
    }
    return $user->display_name ? $user->display_name : $user->user_login;
  }


  private function get_questionnaire_action_status_label( $status ) {
    $map = $this->get_questionnaire_action_statuses_map();
    $status = sanitize_key( (string) $status );
    return isset( $map[ $status ] ) ? $map[ $status ] : ucfirst( str_replace( '_', ' ', $status ) );
  }


  private function get_questionnaire_action_type_label( $type ) {
    $map = $this->get_questionnaire_action_type_labels();
    $type = sanitize_key( (string) $type );
    return isset( $map[ $type ] ) ? $map[ $type ] : ucfirst( str_replace( '_', ' ', $type ) );
  }


  private function get_questionnaire_action_redirect_args( $session_id, $participant_id = 0, $source_type = '', $source_id = 0 ) {
    $args = array(
      'page' => 'acdc-of-questionnaire-results',
      'action' => 'results',
      'session_id' => absint( $session_id ),
    );
    if ( $participant_id ) {
      $args['participant_id'] = absint( $participant_id );
    }
    if ( '' !== $source_type ) {
      $args['source_type'] = sanitize_text_field( $source_type );
    }
    if ( $source_id ) {
      $args['source_id'] = absint( $source_id );
    }
    return $args;
  }



private function get_questionnaire_action_prefill_from_request() {
  return array(
    'action_type' => isset( $_GET['suggested_action_type'] ) ? sanitize_key( wp_unslash( $_GET['suggested_action_type'] ) ) : 'improvement',
    'action_status' => isset( $_GET['suggested_action_status'] ) ? sanitize_key( wp_unslash( $_GET['suggested_action_status'] ) ) : 'ouverte',
    'priority_level' => isset( $_GET['suggested_priority_level'] ) ? $this->normalize_questionnaire_action_priority_level( wp_unslash( $_GET['suggested_priority_level'] ) ) : 'Moyenne',
    'action_label' => isset( $_GET['suggested_action_label'] ) ? sanitize_text_field( wp_unslash( $_GET['suggested_action_label'] ) ) : '',
    'notes' => isset( $_GET['suggested_action_notes'] ) ? sanitize_textarea_field( wp_unslash( $_GET['suggested_action_notes'] ) ) : '',
    'assigned_to' => isset( $_GET['suggested_assigned_to'] ) ? absint( wp_unslash( $_GET['suggested_assigned_to'] ) ) : get_current_user_id(),
    'target_due_days' => isset( $_GET['suggested_target_due_days'] ) ? max( 1, absint( wp_unslash( $_GET['suggested_target_due_days'] ) ) ) : 7,
    'due_at' => isset( $_GET['suggested_due_at'] ) ? sanitize_text_field( wp_unslash( $_GET['suggested_due_at'] ) ) : '',
  );
}


private function build_questionnaire_action_suggestion_url( $session_id, $participant_id, $source_type, $source_id, $suggestion ) {
  $args = $this->get_questionnaire_action_redirect_args( $session_id, $participant_id, $source_type, $source_id );
  $args['suggested_action_type'] = isset( $suggestion['action_type'] ) ? sanitize_key( (string) $suggestion['action_type'] ) : 'improvement';
  $args['suggested_action_status'] = isset( $suggestion['action_status'] ) ? sanitize_key( (string) $suggestion['action_status'] ) : 'ouverte';
  $args['suggested_priority_level'] = isset( $suggestion['priority_level'] ) ? rawurlencode( $this->normalize_questionnaire_action_priority_level( (string) $suggestion['priority_level'] ) ) : 'Moyenne';
  $args['suggested_action_label'] = isset( $suggestion['action_label'] ) ? rawurlencode( (string) $suggestion['action_label'] ) : '';
  $args['suggested_action_notes'] = isset( $suggestion['notes'] ) ? rawurlencode( (string) $suggestion['notes'] ) : '';
  $args['suggested_assigned_to'] = isset( $suggestion['assigned_to'] ) ? absint( $suggestion['assigned_to'] ) : get_current_user_id();
  $args['suggested_target_due_days'] = isset( $suggestion['target_due_days'] ) ? absint( $suggestion['target_due_days'] ) : 7;
  $args['suggested_due_at'] = isset( $suggestion['due_at'] ) ? rawurlencode( (string) $suggestion['due_at'] ) : '';
  return add_query_arg( $args, admin_url( 'admin.php' ) );
}


private function get_questionnaire_action_suggestions( $session_id, $participant_id = 0 ) {
  global $wpdb;
  $session_id = absint( $session_id );
  $participant_id = absint( $participant_id );
  $suggestions = array();
  $session = $session_id ? $this->get_questionnaire_session( $session_id ) : null;
  $source = $session ? $this->get_questionnaire_source_data( $session->source_type, $session->source_id ) : null;
  $settings = $this->get_survey_settings_from_source_data( $source );

  if ( $participant_id ) {
    $participant = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->questionnaire_participant_table} WHERE id = %d", $participant_id ) );
    if ( $participant ) {
      $participant_name = $this->get_questionnaire_session_participant_display_name( $participant );
      $score = $this->get_questionnaire_session_participant_score( $session_id, $participant_id );
      if ( ! empty( $participant->complaint_flag ) ) {
        $suggestions[] = array(
          'rule_key' => 'complaint',
          'label' => 'Traiter la réclamation',
          'action_type' => 'complaint',
          'action_status' => 'ouverte',
          'target_due_days' => $this->get_questionnaire_action_target_due_days( $settings, 'complaint' ),
          'due_at' => $this->calculate_questionnaire_action_due_at_from_days( $this->get_questionnaire_action_target_due_days( $settings, 'complaint' ) ),
          'action_label' => 'Traiter la réclamation de ' . $participant_name,
          'notes' => 'Réponse d’enquête avec réclamation déclarée. Vérifier le détail du verbatim, contacter le répondant et formaliser la réponse apportée.',
        );
      }
      if ( ! empty( $participant->issue_flag ) ) {
        $suggestions[] = array(
          'rule_key' => 'issue',
          'label' => 'Analyser la difficulté signalée',
          'action_type' => 'followup',
          'action_status' => 'ouverte',
          'target_due_days' => $this->get_questionnaire_action_target_due_days( $settings, 'issue' ),
          'due_at' => $this->calculate_questionnaire_action_due_at_from_days( $this->get_questionnaire_action_target_due_days( $settings, 'issue' ) ),
          'action_label' => 'Analyser la difficulté signalée par ' . $participant_name,
          'notes' => 'Une difficulté a été signalée dans la réponse. Vérifier la cause, proposer une mesure corrective et tracer la suite donnée.',
        );
      }
      if ( ! empty( $participant->reservation_flag ) ) {
        $suggestions[] = array(
          'rule_key' => 'reservation',
          'label' => 'Lever la réserve exprimée',
          'action_type' => 'alert',
          'action_status' => 'ouverte',
          'target_due_days' => $this->get_questionnaire_action_target_due_days( $settings, 'reservation' ),
          'due_at' => $this->calculate_questionnaire_action_due_at_from_days( $this->get_questionnaire_action_target_due_days( $settings, 'reservation' ) ),
          'action_label' => 'Lever la réserve exprimée par ' . $participant_name,
          'notes' => 'Une réserve ou insatisfaction a été exprimée. Vérifier le point bloquant et décider d’une action d’ajustement.',
        );
      }
      if ( ! empty( $participant->improvement_need_flag ) ) {
        $suggestions[] = array(
          'rule_key' => 'improvement_need',
          'label' => 'Prévoir un complément ou une suite',
          'action_type' => 'improvement',
          'action_status' => 'ouverte',
          'target_due_days' => $this->get_questionnaire_action_target_due_days( $settings, 'improvement_need' ),
          'due_at' => $this->calculate_questionnaire_action_due_at_from_days( $this->get_questionnaire_action_target_due_days( $settings, 'improvement_need' ) ),
          'action_label' => 'Prévoir un complément ou une suite pour ' . $participant_name,
          'notes' => 'Le répondant a exprimé un besoin complémentaire ou un souhait de suite. Vérifier le besoin réel et proposer une action adaptée.',
        );
      }
      if ( ! empty( $participant->alert_flag ) && is_numeric( $score ) && (float) $score <= 6 ) {
        $suggestions[] = array(
          'rule_key' => 'low_score',
          'label' => 'Traiter le score faible',
          'action_type' => 'improvement',
          'action_status' => 'ouverte',
          'target_due_days' => $this->get_questionnaire_action_target_due_days( $settings, 'low_score' ),
          'due_at' => $this->calculate_questionnaire_action_due_at_from_days( $this->get_questionnaire_action_target_due_days( $settings, 'low_score' ) ),
          'action_label' => 'Traiter le score faible de ' . $participant_name,
          'notes' => 'Le score individuel est faible (' . $score . '). Vérifier les questions en alerte, qualifier la cause et suivre l’action engagée.',
        );
      }
      foreach ( $suggestions as $idx => $suggestion ) {
        $suggestions[ $idx ]['assigned_to'] = $this->resolve_questionnaire_action_assigned_user_id( $session, $participant, $source, $settings, $suggestion );
      }
    }
  } else {
    $stats = $this->get_questionnaire_session_stats( array( 'session_id' => $session_id ) );
    $response_rate = isset( $stats['response_rate'] ) ? (float) $stats['response_rate'] : 0.0;
    $average_score = isset( $stats['average_score'] ) && null !== $stats['average_score'] ? (float) $stats['average_score'] : null;
    $alert_count = isset( $stats['alert_count'] ) ? (int) $stats['alert_count'] : 0;
    if ( $alert_count > 0 ) {
      $suggestions[] = array(
        'rule_key' => 'session_alerts',
        'label' => 'Traiter les alertes de la session',
        'action_type' => 'alert',
        'action_status' => 'ouverte',
        'target_due_days' => $this->get_questionnaire_action_target_due_days( $settings, 'issue' ),
        'due_at' => $this->calculate_questionnaire_action_due_at_from_days( $this->get_questionnaire_action_target_due_days( $settings, 'issue' ) ),
        'action_label' => 'Traiter les alertes détectées sur la session',
        'notes' => 'La session contient ' . $alert_count . ' alerte(s). Vérifier les réponses concernées, identifier les causes communes et suivre les décisions prises.',
      );
    }
    if ( null !== $average_score && $average_score <= 6 ) {
      $suggestions[] = array(
        'rule_key' => 'session_low_score',
        'label' => 'Lancer une revue qualité de la session',
        'action_type' => 'improvement',
        'action_status' => 'ouverte',
        'target_due_days' => $this->get_questionnaire_action_target_due_days( $settings, 'low_score' ),
        'due_at' => $this->calculate_questionnaire_action_due_at_from_days( $this->get_questionnaire_action_target_due_days( $settings, 'low_score' ) ),
        'action_label' => 'Lancer une revue qualité sur la session',
        'notes' => 'Le score moyen de la session est faible (' . $average_score . '). Analyser les questions les plus faibles, les verbatims et définir une action d’amélioration.',
      );
    }
    if ( isset( $stats['participant_count'] ) && (int) $stats['participant_count'] > 0 && $response_rate < 50 ) {
      $suggestions[] = array(
        'rule_key' => 'session_low_response_rate',
        'label' => 'Renforcer la relance des non-répondants',
        'action_type' => 'followup',
        'action_status' => 'ouverte',
        'target_due_days' => $this->get_questionnaire_action_target_due_days( $settings, 'issue' ),
        'due_at' => $this->calculate_questionnaire_action_due_at_from_days( $this->get_questionnaire_action_target_due_days( $settings, 'issue' ) ),
        'action_label' => 'Renforcer la relance des non-répondants',
        'notes' => 'Le taux de réponse de la session est faible (' . $response_rate . ' %). Vérifier la diffusion, relancer les destinataires et analyser les causes de non-réponse.',
      );
    }
    foreach ( $suggestions as $idx => $suggestion ) {
      $suggestions[ $idx ]['assigned_to'] = $this->resolve_questionnaire_action_assigned_user_id( $session, null, $source, $settings, $suggestion );
    }
  }

  $unique = array();
  foreach ( $suggestions as $suggestion ) {
    $key = sanitize_key( (string) $suggestion['action_type'] ) . '|' . sanitize_title( (string) $suggestion['action_label'] );
    $unique[ $key ] = $suggestion;
  }
  return array_values( $unique );
}



private function questionnaire_action_exists_for_suggestion( $session_id, $participant_id, $suggestion ) {
  global $wpdb;
  $session_id = absint( $session_id );
  $participant_id = absint( $participant_id );
  $action_type = isset( $suggestion['action_type'] ) ? sanitize_key( (string) $suggestion['action_type'] ) : 'improvement';
  $action_label = isset( $suggestion['action_label'] ) ? sanitize_text_field( (string) $suggestion['action_label'] ) : '';
  if ( ! $session_id || '' === $action_label ) {
    return false;
  }
  $sql = "SELECT id FROM {$this->questionnaire_action_table} WHERE questionnaire_session_id = %d AND action_type = %s AND action_label = %s";
  $args = array( $session_id, $action_type, $action_label );
  if ( $participant_id > 0 ) {
    $sql .= " AND participant_id = %d";
    $args[] = $participant_id;
  } else {
    $sql .= " AND (participant_id IS NULL OR participant_id = 0)";
  }
  $sql .= " AND action_status != 'annulee' LIMIT 1";
  $existing = $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
  return ! empty( $existing );
}


private function create_questionnaire_action_from_suggestion( $session_id, $participant_id, $suggestion, $origin = 'auto' ) {
  global $wpdb;
  $session_id = absint( $session_id );
  $participant_id = absint( $participant_id );
  if ( ! $session_id || empty( $suggestion ) || ! is_array( $suggestion ) ) {
    return 0;
  }
  if ( $this->questionnaire_action_exists_for_suggestion( $session_id, $participant_id, $suggestion ) ) {
    return 0;
  }
  $action_type = isset( $suggestion['action_type'] ) ? sanitize_key( (string) $suggestion['action_type'] ) : 'improvement';
  $action_status = isset( $suggestion['action_status'] ) ? sanitize_key( (string) $suggestion['action_status'] ) : 'ouverte';
  if ( ! in_array( $action_status, array( 'ouverte', 'en_cours', 'traitee', 'annulee' ), true ) ) {
    $action_status = 'ouverte';
  }
  $action_label = isset( $suggestion['action_label'] ) ? sanitize_text_field( (string) $suggestion['action_label'] ) : '';
  if ( '' === $action_label ) {
    return 0;
  }
  $notes = isset( $suggestion['notes'] ) ? sanitize_textarea_field( (string) $suggestion['notes'] ) : '';
  $assigned_to = isset( $suggestion['assigned_to'] ) ? absint( $suggestion['assigned_to'] ) : 0;
  $priority_level = $this->normalize_questionnaire_action_priority_level( isset( $suggestion['priority_level'] ) ? (string) $suggestion['priority_level'] : 'Moyenne' );
  $target_due_days = isset( $suggestion['target_due_days'] ) ? max( 1, absint( $suggestion['target_due_days'] ) ) : 0;
  $due_at = isset( $suggestion['due_at'] ) ? $this->normalize_questionnaire_action_due_at_input( (string) $suggestion['due_at'], $target_due_days ) : ( $target_due_days > 0 ? $this->calculate_questionnaire_action_due_at_from_days( $target_due_days ) : null );
  $now = $this->now_mysql();
  $inserted = $wpdb->insert(
    $this->questionnaire_action_table,
    array(
      'questionnaire_session_id' => $session_id,
      'participant_id' => $participant_id > 0 ? $participant_id : null,
      'action_type' => $action_type,
      'action_label' => $action_label,
      'action_status' => $action_status,
      'assigned_to' => $assigned_to,
      'priority_level' => $priority_level,
      'target_due_days' => $target_due_days ? $target_due_days : null,
      'due_at' => $due_at,
      'opened_at' => $now,
      'closed_at' => in_array( $action_status, array( 'traitee', 'annulee' ), true ) ? $now : null,
      'notes' => $notes,
      'created_at' => $now,
      'updated_at' => $now,
    ),
    array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
  );
  if ( false === $inserted ) {
    return 0;
  }
  $action_id = (int) $wpdb->insert_id;
  $this->log_questionnaire_event( array(
    'questionnaire_session_id' => $session_id,
    'participant_id' => $participant_id,
    'event_type' => 'questionnaire_action_auto_created',
    'event_label' => 'Action d’amélioration créée automatiquement',
    'event_payload' => array(
      'origin' => sanitize_key( (string) $origin ),
      'action_id' => $action_id,
      'action_type' => $action_type,
      'action_label' => $action_label,
      'priority_level' => $priority_level,
      'target_due_days' => $target_due_days,
      'due_at' => $due_at,
    ),
  ) );
  return $action_id;
}


private function maybe_auto_create_questionnaire_actions_from_response( $session, $participant ) {
  if ( ! $session || ! $participant ) {
    return 0;
  }
  $source = $this->get_questionnaire_source_data( $session->source_type, $session->source_id );
  $settings = $this->get_survey_settings_from_source_data( $source );
  if ( empty( $settings ) || '1' !== (string) ( $settings['auto_create_actions'] ?? '0' ) ) {
    return 0;
  }
  $enabled_rules = array_values( array_unique( array_map( 'sanitize_key', (array) ( $settings['auto_action_rules'] ?? array() ) ) ) );
  $suggestions = $this->get_questionnaire_action_suggestions( (int) $session->id, (int) $participant->id );
  if ( ! empty( $enabled_rules ) ) {
    $suggestions = array_values( array_filter( (array) $suggestions, static function( $suggestion ) use ( $enabled_rules ) {
      $rule_key = isset( $suggestion['rule_key'] ) ? sanitize_key( (string) $suggestion['rule_key'] ) : '';
      return '' !== $rule_key && in_array( $rule_key, $enabled_rules, true );
    } ) );
  }
  if ( empty( $suggestions ) ) {
    return 0;
  }
  $created = 0;
  foreach ( $suggestions as $suggestion ) {
    $created += $this->create_questionnaire_action_from_suggestion( (int) $session->id, (int) $participant->id, $suggestion, 'response_submit' ) ? 1 : 0;
  }
  return $created;
}



  private function get_questionnaire_action_scope_labels() {
    return array(
      'a_traiter' => 'À traiter',
      'ouverte'   => 'Ouvertes',
      'en_cours'  => 'En cours',
      'traitee'   => 'Traitées',
      'annulee'   => 'Annulées',
    );
  }


  private function get_questionnaire_session_action_counts_map( $filters = array() ) {
    global $wpdb;

    if ( empty( $this->questionnaire_action_table ) || empty( $this->questionnaire_session_table ) ) {
      return array();
    }

    $where  = array( '1=1' );
    $params = array();

    if ( ! empty( $filters['source_type'] ) ) {
      $where[]  = 's.source_type = %s';
      $params[] = (string) $filters['source_type'];
    }
    if ( ! empty( $filters['source_id'] ) ) {
      $where[]  = 's.source_id = %d';
      $params[] = absint( $filters['source_id'] );
    }
    if ( ! empty( $filters['priority_level'] ) ) {
      $priority_condition = $this->get_questionnaire_action_priority_filter_condition( $filters['priority_level'], 'a' );
      if ( ! empty( $priority_condition['sql'] ) ) {
        $where[] = $priority_condition['sql'];
        $params  = array_merge( $params, $priority_condition['params'] );
      }
    }

    $sql = "SELECT a.questionnaire_session_id AS session_id,
                   COUNT(*) AS total,
                   SUM(CASE WHEN a.action_status = 'ouverte' THEN 1 ELSE 0 END) AS ouvertes,
                   SUM(CASE WHEN a.action_status = 'en_cours' THEN 1 ELSE 0 END) AS en_cours,
                   SUM(CASE WHEN a.action_status = 'traitee' THEN 1 ELSE 0 END) AS traitees,
                   SUM(CASE WHEN a.action_status = 'annulee' THEN 1 ELSE 0 END) AS annulees,
                   SUM(CASE WHEN a.action_status NOT IN ('traitee','annulee') THEN 1 ELSE 0 END) AS a_traiter
            FROM {$this->questionnaire_action_table} a
            INNER JOIN {$this->questionnaire_session_table} s ON s.id = a.questionnaire_session_id
            WHERE " . implode( ' AND ', $where ) . "
            GROUP BY a.questionnaire_session_id";

    if ( ! empty( $params ) ) {
      $sql = $wpdb->prepare( $sql, $params );
    }

    $rows = $wpdb->get_results( $sql );
    if ( ! is_array( $rows ) ) {
      return array();
    }

    $map = array();
    foreach ( $rows as $row ) {
      $map[ (int) $row->session_id ] = array(
        'total'     => isset( $row->total ) ? (int) $row->total : 0,
        'ouverte'   => isset( $row->ouvertes ) ? (int) $row->ouvertes : 0,
        'en_cours'  => isset( $row->en_cours ) ? (int) $row->en_cours : 0,
        'traitee'   => isset( $row->traitees ) ? (int) $row->traitees : 0,
        'annulee'   => isset( $row->annulees ) ? (int) $row->annulees : 0,
        'a_traiter' => isset( $row->a_traiter ) ? (int) $row->a_traiter : 0,
      );
    }

    return $map;
  }


  private function get_questionnaire_action_context_stats( $filters = array() ) {
    global $wpdb;

    $stats = array(
      'total'           => 0,
      'a_traiter'       => 0,
      'ouverte'         => 0,
      'en_cours'        => 0,
      'traitee'         => 0,
      'annulee'         => 0,
      'en_retard'       => 0,
      'aujourd_hui'     => 0,
      'echeance_proche' => 0,
      'priority_high'   => 0,
      'priority_medium' => 0,
      'priority_low'    => 0,
    );

    if ( empty( $this->questionnaire_action_table ) || empty( $this->questionnaire_session_table ) ) {
      return $stats;
    }

    $where  = array( '1=1' );
    $params = array();

    if ( ! empty( $filters['source_type'] ) ) {
      $where[]  = 's.source_type = %s';
      $params[] = (string) $filters['source_type'];
    }
    if ( ! empty( $filters['source_id'] ) ) {
      $where[]  = 's.source_id = %d';
      $params[] = absint( $filters['source_id'] );
    }
    if ( ! empty( $filters['priority_level'] ) ) {
      $priority_condition = $this->get_questionnaire_action_priority_filter_condition( $filters['priority_level'], 'a' );
      if ( ! empty( $priority_condition['sql'] ) ) {
        $where[] = $priority_condition['sql'];
        $params  = array_merge( $params, $priority_condition['params'] );
      }
    }

    $sql = "SELECT a.action_status, a.due_at, a.priority_level
            FROM {$this->questionnaire_action_table} a
            INNER JOIN {$this->questionnaire_session_table} s ON s.id = a.questionnaire_session_id
            WHERE " . implode( ' AND ', $where );

    if ( ! empty( $params ) ) {
      $sql = $wpdb->prepare( $sql, $params );
    }

    $rows = $wpdb->get_results( $sql );
    if ( ! is_array( $rows ) ) {
      return $stats;
    }

    foreach ( $rows as $row ) {
      $status = ! empty( $row->action_status ) ? sanitize_key( (string) $row->action_status ) : 'ouverte';
      $priority_label = $this->normalize_questionnaire_action_priority_level( ! empty( $row->priority_level ) ? (string) $row->priority_level : 'Moyenne' );
      $due_state = $this->get_questionnaire_action_due_state( $row );
      $stats['total']++;
      if ( isset( $stats[ $status ] ) ) {
        $stats[ $status ]++;
      }
      if ( in_array( $status, array( 'ouverte', 'en_cours' ), true ) ) {
        $stats['a_traiter']++;
      }
      if ( 'Haute' === $priority_label ) {
        $stats['priority_high']++;
      } elseif ( 'Basse' === $priority_label ) {
        $stats['priority_low']++;
      } else {
        $stats['priority_medium']++;
      }
      if ( 'en_retard' === $due_state['state_key'] ) {
        $stats['en_retard']++;
      } elseif ( 'aujourd_hui' === $due_state['state_key'] ) {
        $stats['aujourd_hui']++;
      } elseif ( 'echeance_proche' === $due_state['state_key'] ) {
        $stats['echeance_proche']++;
      }
    }

    return $stats;
  }


  private function get_questionnaire_action_treatment_rows( $filters = array(), $limit = 30 ) {
    global $wpdb;

    if ( empty( $this->questionnaire_action_table ) || empty( $this->questionnaire_session_table ) ) {
      return array();
    }

    $where  = array( '1=1' );
    $params = array();

    if ( ! empty( $filters['source_type'] ) ) {
      $where[]  = 's.source_type = %s';
      $params[] = (string) $filters['source_type'];
    }
    if ( ! empty( $filters['source_id'] ) ) {
      $where[]  = 's.source_id = %d';
      $params[] = absint( $filters['source_id'] );
    }

    $scope = ! empty( $filters['action_scope'] ) ? sanitize_key( (string) $filters['action_scope'] ) : 'a_traiter';
    if ( 'a_traiter' === $scope ) {
      $where[] = "a.action_status IN ('ouverte','en_cours')";
    } elseif ( in_array( $scope, array( 'ouverte', 'en_cours', 'traitee', 'annulee' ), true ) ) {
      $where[]  = 'a.action_status = %s';
      $params[] = $scope;
    }
    if ( ! empty( $filters['priority_level'] ) ) {
      $priority_condition = $this->get_questionnaire_action_priority_filter_condition( $filters['priority_level'], 'a' );
      if ( ! empty( $priority_condition['sql'] ) ) {
        $where[] = $priority_condition['sql'];
        $params  = array_merge( $params, $priority_condition['params'] );
      }
    }

    $limit = max( 1, absint( $limit ) );

    $sql = "SELECT a.*, s.source_type, s.source_id, s.session_title, s.status AS questionnaire_session_status
            FROM {$this->questionnaire_action_table} a
            INNER JOIN {$this->questionnaire_session_table} s ON s.id = a.questionnaire_session_id
            WHERE " . implode( ' AND ', $where ) . "
            ORDER BY CASE
              WHEN a.priority_level IN ('Haute','Critique') THEN 0
              WHEN a.priority_level IN ('Moyenne','Normale') THEN 1
              ELSE 2
            END ASC,
            CASE WHEN a.due_at IS NULL THEN 1 ELSE 0 END ASC,
            a.due_at ASC,
            a.id DESC
            LIMIT %d";
    $params[] = $limit;
    $sql = $wpdb->prepare( $sql, $params );

    $rows = $wpdb->get_results( $sql );
    if ( ! is_array( $rows ) ) {
      return array();
    }

    foreach ( $rows as $row ) {
      $row->due_state         = $this->get_questionnaire_action_due_state( $row );
      $row->priority_badge    = $this->get_questionnaire_action_priority_badge( ! empty( $row->priority_level ) ? (string) $row->priority_level : 'Moyenne' );
      $row->source            = ( ! empty( $row->source_type ) && ! empty( $row->source_id ) ) ? $this->get_questionnaire_source_data( (string) $row->source_type, (int) $row->source_id ) : null;
      $row->participant_label = '—';
      if ( ! empty( $row->participant_id ) ) {
        $participant = $wpdb->get_row(
          $wpdb->prepare(
            "SELECT p.*, l.first_name, l.last_name FROM {$this->questionnaire_participant_table} p LEFT JOIN {$this->learner_table} l ON l.id = p.apprenant_id WHERE p.id = %d",
            (int) $row->participant_id
          )
        );
        if ( $participant ) {
          $row->participant_label = $this->get_questionnaire_session_participant_display_name( $participant );
        }
      }
      $row->followup_url = $this->get_questionnaire_results_url(
        ! empty( $row->source_type ) ? (string) $row->source_type : '',
        ! empty( $row->questionnaire_session_id ) ? (int) $row->questionnaire_session_id : 0,
        ! empty( $row->source_id ) ? (int) $row->source_id : 0,
        ! empty( $row->participant_id ) ? (int) $row->participant_id : 0
      );
    }

    return $rows;
  }


  private function get_questionnaire_session_stats_from_rows( $rows ) {
    $stats = array(
      'total' => count( $rows ),
      'active' => 0,
      'finished' => 0,
      'participants' => 0,
      'responses' => 0,
      'alerts' => 0,
      'average_score' => null,
      'response_rate' => null,
    );

    $score_sum   = 0;
    $score_count = 0;

    foreach ( (array) $rows as $row ) {
      if ( empty( $row['session'] ) || ! is_object( $row['session'] ) ) {
        continue;
      }
      $session      = $row['session'];
      $participants = ! empty( $row['participants'] ) && is_array( $row['participants'] ) ? $row['participants'] : $this->get_questionnaire_session_participants( $session->id );

      if ( in_array( $session->status, array( 'en_cours', 'ouverte', 'en_pause' ), true ) ) {
        $stats['active']++;
      }
      if ( 'terminee' === $session->status ) {
        $stats['finished']++;
      }

      $stats['participants'] += count( $participants );

      foreach ( (array) $participants as $participant ) {
        if ( in_array( (string) ( $participant->participant_status ?? '' ), array( 'repondu', 'termine' ), true ) ) {
          $stats['responses']++;
        }
        if ( ! empty( $participant->alert_flag ) ) {
          $stats['alerts']++;
        }
        if ( in_array( (string) ( $participant->participant_status ?? '' ), array( 'repondu', 'termine' ), true ) ) {
          $score = $this->get_questionnaire_session_participant_score( $session->id, $participant->id );
          if ( '' !== (string) $score && null !== $score ) {
            $score_sum += (float) $score;
            $score_count++;
          }
        }
      }
    }

    if ( $stats['participants'] > 0 ) {
      $stats['response_rate'] = round( ( $stats['responses'] / $stats['participants'] ) * 100, 1 );
    }
    if ( $score_count > 0 ) {
      $stats['average_score'] = round( $score_sum / $score_count, 2 );
    }

    return $stats;
  }







  private function get_quality_alert_business_type( $alert_row ) {
    $alert_row = is_array( $alert_row ) ? $alert_row : array();
    $reason    = mb_strtolower( (string) ( $alert_row['alert_reason'] ?? '' ) );
    $scope     = (string) ( $alert_row['alert_scope'] ?? 'participant' );

    $type = array(
      'key' => 'generic_alert',
      'label' => 'Alerte qualité',
      'scenario' => 'Qualification qualité',
      'default_transition' => 'take_in_charge',
      'default_action_label' => 'Prendre en charge',
    );

    if ( false !== strpos( $reason, 'réclamation' ) ) {
      $type = array(
        'key' => 'complaint',
        'label' => 'Réclamation',
        'scenario' => "Traitement prioritaire d'insatisfaction",
        'default_transition' => 'open_treatment',
        'default_action_label' => 'Traiter la réclamation',
      );
    } elseif ( false !== strpos( $reason, 'réserve' ) ) {
      $type = array(
        'key' => 'reservation',
        'label' => 'Réserve',
        'scenario' => 'Levée de réserve',
        'default_transition' => 'open_treatment',
        'default_action_label' => 'Lever la réserve',
      );
    } elseif ( false !== strpos( $reason, 'difficulté' ) || false !== strpos( $reason, 'besoin de complément' ) ) {
      $type = array(
        'key' => 'pedagogical_issue',
        'label' => 'Difficulté pédagogique',
        'scenario' => 'Remédiation pédagogique',
        'default_transition' => 'open_treatment',
        'default_action_label' => 'Lancer la remédiation',
      );
    } elseif ( false !== strpos( $reason, 'taux de réponse faible' ) ) {
      $type = array(
        'key' => 'low_response_rate',
        'label' => 'Collecte faible',
        'scenario' => 'Relance de collecte',
        'default_transition' => 'take_in_charge',
        'default_action_label' => 'Relancer la collecte',
      );
    } elseif ( false !== strpos( $reason, 'score faible' ) || false !== strpos( $reason, 'alerte notation' ) ) {
      $type = array(
        'key' => 'low_score',
        'label' => 'Score faible',
        'scenario' => 'Analyse des causes',
        'default_transition' => 'open_treatment',
        'default_action_label' => 'Analyser les causes',
      );
    } elseif ( 'session' === $scope ) {
      $type = array(
        'key' => 'session_watch',
        'label' => 'Alerte de session',
        'scenario' => 'Qualification collective',
        'default_transition' => 'take_in_charge',
        'default_action_label' => 'Qualifier la session',
      );
    }

    return $type;
  }

  private function get_quality_alert_auto_scenario( $alert_row ) {
    $alert_row = is_array( $alert_row ) ? $alert_row : array();
    $source    = (string) ( $alert_row['source_type'] ?? '' );
    $scope     = (string) ( $alert_row['alert_scope'] ?? 'participant' );
    $type      = $this->get_quality_alert_business_type( $alert_row );

    $owner      = 'Pilotage qualité';
    $scenario   = (string) ( $type['scenario'] ?? 'Qualification qualité' );
    $gap_status = 'Écart auto à qualifier';
    $gap_due    = 'J+7';
    $action_status = 'À ouvrir';
    $action_due = 'J+7';
    $proof_status = 'Alerte détectée';

    switch ( $type['key'] ) {
      case 'complaint':
        $owner         = 'Référent qualité';
        $gap_status    = 'Priorisé';
        $gap_due       = 'J+3';
        $action_status = 'Assignée';
        $action_due    = 'J+3';
        $proof_status  = 'Réclamation à traiter';
        break;
      case 'reservation':
        $owner         = 'Référent qualité';
        $gap_status    = 'À lever';
        $gap_due       = 'J+5';
        $action_status = 'Assignée';
        $action_due    = 'J+7';
        $proof_status  = 'Réserve à objectiver';
        break;
      case 'pedagogical_issue':
        $owner         = 'Référent pédagogique';
        $gap_status    = 'À analyser';
        $gap_due       = 'J+5';
        $action_status = 'Assignée';
        $action_due    = 'J+10';
        $proof_status  = 'Remédiation à enclencher';
        break;
      case 'low_response_rate':
        $owner         = 'Coordination qualité';
        $gap_status    = 'À renforcer';
        $gap_due       = 'J+5';
        $action_status = 'En suivi';
        $action_due    = 'J+10';
        $proof_status  = 'Collecte à relancer';
        break;
      case 'low_score':
        $owner         = 'Référent qualité';
        $gap_status    = 'Priorisé';
        $gap_due       = 'J+5';
        $action_status = 'Assignée';
        $action_due    = 'J+10';
        $proof_status  = 'Signal faible qualifié';
        break;
      case 'session_watch':
        $owner         = 'Pilotage qualité';
        $gap_status    = 'À qualifier';
        $gap_due       = 'J+5';
        $action_status = 'À ouvrir';
        $action_due    = 'J+7';
        $proof_status  = 'Analyse collective à lancer';
        break;
    }

    if ( in_array( $source, array( 'company_survey', 'company' ), true ) ) {
      $owner = 'Référent entreprise';
    } elseif ( in_array( $source, array( 'funder_survey', 'funder' ), true ) ) {
      $owner = 'Référent financeur';
    } elseif ( in_array( $source, array( 'trainer_survey', 'trainer' ), true ) ) {
      $owner = 'Référent pédagogique';
    } elseif ( 'session' === $scope && 'Coordination qualité' !== $owner && 'low_response_rate' !== $type['key'] ) {
      $owner = 'Pilotage qualité';
    }

    return array(
      'alert_type_key' => (string) ( $type['key'] ?? 'generic_alert' ),
      'alert_type_label' => (string) ( $type['label'] ?? 'Alerte qualité' ),
      'scenario_label' => $scenario,
      'assigned_owner' => $owner,
      'gap_status'     => $gap_status,
      'gap_due_label'  => $gap_due,
      'action_status'  => $action_status,
      'action_due_label' => $action_due,
      'proof_status'   => $proof_status,
      'default_transition' => (string) ( $type['default_transition'] ?? 'take_in_charge' ),
      'default_action_label' => (string) ( $type['default_action_label'] ?? 'Prendre en charge' ),
      'journal_detail' => sprintf(
        '%s · %s → écart %s → action %s assignée à %s.',
        (string) ( $type['label'] ?? 'Alerte qualité' ),
        $scenario,
        $gap_status,
        $action_status,
        $owner
      ),
    );
  }

  private function get_quality_alert_bridge_rows() {
    $rows = array();
    $source_map = $this->get_questionnaire_source_label_map();
    $survey_types = $this->get_survey_questionnaire_source_types();

    foreach ( $survey_types as $source_type ) {
      $sessions = $this->get_questionnaire_sessions( array( 'source_type' => $source_type ) );
      foreach ( (array) $sessions as $session ) {
        if ( empty( $session ) || empty( $session->id ) ) {
          continue;
        }

        $source = $this->get_questionnaire_source_data( $session->source_type, $session->source_id );
        $source_label = isset( $source_map[ $source_type ] ) ? (string) $source_map[ $source_type ] : (string) $source_type;
        $session_title = ! empty( $session->session_title ) ? (string) $session->session_title : $source_label;
        $source_title = ! empty( $source['title'] ) ? (string) $source['title'] : $session_title;
        $participants = $this->get_questionnaire_session_participants( $session->id );
        $stats = $this->get_questionnaire_session_stats( array( 'session_id' => $session->id ) );
        $response_rate = isset( $stats['response_rate'] ) ? (float) $stats['response_rate'] : null;
        $average_score = isset( $stats['average_score'] ) ? (float) $stats['average_score'] : null;
        $alert_count = isset( $stats['alerts'] ) ? (int) $stats['alerts'] : 0;

        if ( $alert_count > 0 || ( null !== $response_rate && $response_rate < 50 ) || ( null !== $average_score && $average_score <= 6 ) ) {
          $reason_parts = array();
          if ( $alert_count > 0 ) {
            $reason_parts[] = $alert_count . ' alerte(s) détectée(s)';
          }
          if ( null !== $response_rate && $response_rate < 50 ) {
            $reason_parts[] = 'taux de réponse faible (' . $response_rate . ' %)';
          }
          if ( null !== $average_score && $average_score <= 6 ) {
            $reason_parts[] = 'score moyen faible (' . $average_score . ')';
          }
          $row = array(
            'alert_key' => 'session-' . absint( $session->id ),
            'source_type' => (string) $source_type,
            'source_label' => $source_label,
            'session_id' => (int) $session->id,
            'participant_id' => 0,
            'session_title' => $session_title,
            'source_title' => $source_title,
            'participant_label' => 'Session complète',
            'alert_scope' => 'session',
            'alert_reason' => implode( ' · ', $reason_parts ),
            'severity' => ( $alert_count > 0 || ( null !== $average_score && $average_score <= 6 ) ) ? 'Élevé' : 'Moyen',
            'priority' => ( $alert_count > 0 || ( null !== $average_score && $average_score <= 6 ) ) ? 'Haute' : 'Moyenne',
            'score' => null !== $average_score ? round( $average_score, 2 ) : '',
            'response_rate' => null !== $response_rate ? round( $response_rate, 1 ) : '',
            'status_label' => $alert_count > 0 ? 'Alerte ouverte' : 'À surveiller',
            'gap_title' => 'Alerte session à traiter — ' . $source_label,
            'gap_impact' => 'Une session d’enquête présente un signal faible collectif nécessitant analyse et traitement.',
            'gap_next_step' => 'Qualifier la cause, ouvrir un écart puis suivre l’action décidée.',
            'action_objective' => 'Traiter l’alerte de session — ' . $source_label,
            'action_evidence' => 'Session #' . absint( $session->id ) . ' · ' . $session_title,
          );
          $rows[] = array_merge( $row, $this->get_quality_alert_auto_scenario( $row ) );
        }

        foreach ( (array) $participants as $participant ) {
          if ( empty( $participant ) || empty( $participant->id ) ) {
            continue;
          }
          $flags = array();
          if ( ! empty( $participant->complaint_flag ) ) {
            $flags[] = 'réclamation';
          }
          if ( ! empty( $participant->issue_flag ) ) {
            $flags[] = 'difficulté';
          }
          if ( ! empty( $participant->reservation_flag ) ) {
            $flags[] = 'réserve';
          }
          if ( ! empty( $participant->improvement_need_flag ) ) {
            $flags[] = 'besoin de complément';
          }
          if ( ! empty( $participant->alert_flag ) ) {
            $flags[] = 'alerte notation';
          }
          $score = $this->get_questionnaire_session_participant_score( $session->id, $participant->id );
          $score_is_low = '' !== (string) $score && null !== $score && is_numeric( $score ) && (float) $score <= 6;
          if ( $score_is_low ) {
            $flags[] = 'score faible (' . round( (float) $score, 2 ) . ')';
          }
          $flags = array_values( array_unique( array_filter( $flags ) ) );
          if ( empty( $flags ) ) {
            continue;
          }
          $participant_label = $this->get_questionnaire_session_participant_display_name( $participant );
          $row = array(
            'alert_key' => 'participant-' . absint( $session->id ) . '-' . absint( $participant->id ),
            'source_type' => (string) $source_type,
            'source_label' => $source_label,
            'session_id' => (int) $session->id,
            'participant_id' => (int) $participant->id,
            'session_title' => $session_title,
            'source_title' => $source_title,
            'participant_label' => $participant_label,
            'alert_scope' => 'participant',
            'alert_reason' => implode( ' · ', $flags ),
            'severity' => ( ! empty( $participant->complaint_flag ) || $score_is_low ) ? 'Élevé' : 'Moyen',
            'priority' => ( ! empty( $participant->complaint_flag ) || $score_is_low ) ? 'Haute' : 'Moyenne',
            'score' => '' !== (string) $score && null !== $score ? round( (float) $score, 2 ) : '',
            'response_rate' => '',
            'status_label' => 'Alerte ouverte',
            'gap_title' => 'Alerte répondant à traiter — ' . $participant_label,
            'gap_impact' => 'Un répondant a remonté un signal faible nécessitant qualification et suivi.',
            'gap_next_step' => 'Analyser le retour individuel, qualifier l’écart puis décider de l’action.',
            'action_objective' => 'Traiter le signal faible — ' . $participant_label,
            'action_evidence' => 'Session #' . absint( $session->id ) . ' · Répondant #' . absint( $participant->id ),
          );
          $rows[] = array_merge( $row, $this->get_quality_alert_auto_scenario( $row ) );
        }
      }
    }

    $normalized_rows = array();
    foreach ( $rows as $row ) {
      $normalized_rows[] = $this->apply_quality_loop_overrides_to_alert_row( $row );
    }

    return array_values( $normalized_rows );
  }

  private function get_quality_alert_bridge_stats( $rows ) {
    $stats = array(
      'total' => 0,
      'session' => 0,
      'participant' => 0,
      'high' => 0,
      'medium' => 0,
      'types' => 0,
    );
    $types = array();
    foreach ( (array) $rows as $row ) {
      $stats['total']++;
      if ( 'session' === ( $row['alert_scope'] ?? '' ) ) {
        $stats['session']++;
      } else {
        $stats['participant']++;
      }
      if ( 'Élevé' === ( $row['severity'] ?? '' ) ) {
        $stats['high']++;
      } else {
        $stats['medium']++;
      }
      if ( ! empty( $row['source_type'] ) ) {
        $types[ (string) $row['source_type'] ] = true;
      }
    }
    $stats['types'] = count( $types );
    return $stats;
  }

  private function get_quality_compliance_foundations_data() {
    $survey_types = $this->get_survey_questionnaire_source_types();
    $source_map   = $this->get_questionnaire_source_label_map();

    $overall = array(
      'survey_types'         => 0,
      'sessions'             => 0,
      'participants'         => 0,
      'responses'            => 0,
      'alerts'               => 0,
      'response_rate'        => null,
      'average_score'        => null,
      'actions_total'        => 0,
      'actions_to_process'   => 0,
      'actions_closed'       => 0,
      'linked_actions_types' => 0,
    );

    $by_type = array();
    $score_sum = 0.0;
    $score_count = 0;

    foreach ( $survey_types as $source_type ) {
      $rows          = $this->get_questionnaire_session_summary_rows( array( 'source_type' => $source_type ) );
      $session_stats = $this->get_questionnaire_session_stats_from_rows( $rows );
      $action_stats  = $this->get_questionnaire_action_context_stats( array( 'source_type' => $source_type ) );
      $proof_count   = 0;

      if ( ! empty( $session_stats['total'] ) ) {
        $proof_count++;
      }
      if ( ! empty( $session_stats['responses'] ) ) {
        $proof_count++;
      }
      if ( ! empty( $action_stats['total'] ) ) {
        $proof_count++;
      }

      $proof_status = 'Aucune preuve';
      if ( $proof_count >= 3 ) {
        $proof_status = 'Chaîne complète';
      } elseif ( 2 === $proof_count ) {
        $proof_status = 'Partielle';
      } elseif ( 1 === $proof_count ) {
        $proof_status = 'Initiale';
      }

      $by_type[ $source_type ] = array(
        'label'              => isset( $source_map[ $source_type ] ) ? $source_map[ $source_type ] : $source_type,
        'sessions'           => (int) $session_stats['total'],
        'participants'       => (int) $session_stats['participants'],
        'responses'          => (int) $session_stats['responses'],
        'alerts'             => (int) $session_stats['alerts'],
        'response_rate'      => isset( $session_stats['response_rate'] ) ? $session_stats['response_rate'] : null,
        'average_score'      => isset( $session_stats['average_score'] ) ? $session_stats['average_score'] : null,
        'actions_total'      => (int) $action_stats['total'],
        // ACDC 3.25.115 — clés réelles du provider (a_traiter/traitee), sinon indicateur toujours 0.
        'actions_to_process' => (int) $action_stats['a_traiter'],
        'actions_closed'     => (int) $action_stats['traitee'],
        'proof_status'       => $proof_status,
        'proof_count'        => $proof_count,
      );

      $overall['survey_types']++;
      $overall['sessions']           += (int) $session_stats['total'];
      $overall['participants']       += (int) $session_stats['participants'];
      $overall['responses']          += (int) $session_stats['responses'];
      $overall['alerts']             += (int) $session_stats['alerts'];
      $overall['actions_total']      += (int) $action_stats['total'];
      // ACDC 3.25.115 — clés réelles du provider (a_traiter/traitee), sinon indicateur toujours 0.
      $overall['actions_to_process'] += (int) $action_stats['a_traiter'];
      $overall['actions_closed']     += (int) $action_stats['traitee'];
      if ( (int) $action_stats['total'] > 0 ) {
        $overall['linked_actions_types']++;
      }
      if ( null !== $session_stats['average_score'] ) {
        $score_sum += (float) $session_stats['average_score'];
        $score_count++;
      }
    }

    if ( $overall['participants'] > 0 ) {
      $overall['response_rate'] = round( ( $overall['responses'] / $overall['participants'] ) * 100, 1 );
    }
    if ( $score_count > 0 ) {
      $overall['average_score'] = round( $score_sum / $score_count, 2 );
    }

    return array(
      'overall' => $overall,
      'by_type' => $by_type,
    );
  }

  private function get_quality_compliance_qualiopi_summary( $foundation_data ) {
    $overall = ! empty( $foundation_data['overall'] ) && is_array( $foundation_data['overall'] ) ? $foundation_data['overall'] : array();
    $by_type = ! empty( $foundation_data['by_type'] ) && is_array( $foundation_data['by_type'] ) ? $foundation_data['by_type'] : array();

    $fully_covered = 0;
    $with_response = 0;
    foreach ( $by_type as $row ) {
      if ( 'Chaîne complète' === ( $row['proof_status'] ?? '' ) ) {
        $fully_covered++;
      }
      if ( ! empty( $row['responses'] ) ) {
        $with_response++;
      }
    }

    return array(
      'parties_prenantes_couvertes' => $fully_covered,
      'parties_prenantes_repondu'   => $with_response,
      'preuves_actions'             => (int) ( $overall['actions_total'] ?? 0 ),
      'preuves_a_traiter'           => (int) ( $overall['actions_to_process'] ?? 0 ),
    );
  }


  private function get_quality_compliance_indicator_rows( $foundation_data ) {
    $overall = ! empty( $foundation_data['overall'] ) && is_array( $foundation_data['overall'] ) ? $foundation_data['overall'] : array();
    $by_type = ! empty( $foundation_data['by_type'] ) && is_array( $foundation_data['by_type'] ) ? $foundation_data['by_type'] : array();

    $covered_types      = 0;
    $responding_types   = 0;
    $action_linked      = 0;
    $complete_chain     = 0;
    $partial_chain      = 0;
    $empty_chain        = 0;

    foreach ( $by_type as $row ) {
      $proof_status = isset( $row['proof_status'] ) ? (string) $row['proof_status'] : '';
      if ( ! empty( $row['sessions'] ) ) {
        $covered_types++;
      }
      if ( ! empty( $row['responses'] ) ) {
        $responding_types++;
      }
      if ( ! empty( $row['actions_total'] ) ) {
        $action_linked++;
      }
      if ( 'Chaîne complète' === $proof_status ) {
        $complete_chain++;
      } elseif ( 'Aucune preuve' === $proof_status ) {
        $empty_chain++;
      } else {
        $partial_chain++;
      }
    }

    $total_types = count( $by_type );
    $global_rate = isset( $overall['response_rate'] ) ? $overall['response_rate'] : null;

    return array(
      array(
        'code'        => 'QC-01',
        'label'       => 'Recueil des appréciations des parties prenantes',
        'measure'     => $covered_types . ' / ' . $total_types . ' types couverts',
        'evidence'    => (int) ( $overall['sessions'] ?? 0 ) . ' envois / sessions tracés',
        'status'      => $covered_types >= $total_types && $total_types > 0 ? 'Consolidé' : ( $covered_types > 0 ? 'En cours' : 'À lancer' ),
      ),
      array(
        'code'        => 'QC-02',
        'label'       => 'Traçabilité des sollicitations et des réponses',
        'measure'     => null !== $global_rate ? $global_rate . ' % de taux de réponse global' : 'Aucune réponse consolidée',
        'evidence'    => (int) ( $overall['participants'] ?? 0 ) . ' destinataires / ' . (int) ( $overall['responses'] ?? 0 ) . ' réponses',
        'status'      => null !== $global_rate && $global_rate >= 50 ? 'Consolidé' : ( ! empty( $overall['responses'] ) ? 'À renforcer' : 'À lancer' ),
      ),
      array(
        'code'        => 'QC-03',
        'label'       => 'Analyse des retours et des alertes',
        'measure'     => (int) ( $overall['alerts'] ?? 0 ) . ' alertes détectées',
        'evidence'    => $responding_types . ' types avec réponses exploitables',
        'status'      => $responding_types > 0 ? 'Exploitable' : 'À structurer',
      ),
      array(
        'code'        => 'QC-04',
        'label'       => 'Actions d’amélioration liées aux enquêtes',
        'measure'     => (int) ( $overall['actions_total'] ?? 0 ) . ' actions / ' . (int) ( $overall['actions_to_process'] ?? 0 ) . ' à traiter',
        'evidence'    => $action_linked . ' types reliés à une action',
        'status'      => ! empty( $overall['actions_total'] ) ? 'Consolidé' : 'À lancer',
      ),
      array(
        'code'        => 'QC-05',
        'label'       => 'Chaîne de preuve audit-ready',
        'measure'     => $complete_chain . ' complètes / ' . $partial_chain . ' partielles / ' . $empty_chain . ' sans preuve',
        'evidence'    => $total_types . ' types suivis',
        'status'      => $empty_chain > 0 ? 'Risque d’écart' : ( $partial_chain > 0 ? 'À compléter' : 'Consolidé' ),
      ),
    );
  }

  private function get_quality_compliance_gap_rows( $foundation_data ) {
    $by_type = ! empty( $foundation_data['by_type'] ) && is_array( $foundation_data['by_type'] ) ? $foundation_data['by_type'] : array();
    $rows    = array();

    foreach ( $by_type as $source_type => $row ) {
      $label         = isset( $row['label'] ) ? (string) $row['label'] : (string) $source_type;
      $sessions      = (int) ( $row['sessions'] ?? 0 );
      $responses     = (int) ( $row['responses'] ?? 0 );
      $actions_total = (int) ( $row['actions_total'] ?? 0 );
      $alerts        = (int) ( $row['alerts'] ?? 0 );
      $rate          = isset( $row['response_rate'] ) ? $row['response_rate'] : null;

      if ( 0 === $sessions ) {
        $rows[] = array(
          'type'       => $label,
          'gap'        => 'Aucune sollicitation tracée',
          'severity'   => 'Élevé',
          'impact'     => 'Pas de preuve d’envoi ou de recueil sur ce type de partie prenante.',
          'next_step'  => 'Créer au moins une campagne ou un envoi traçable pour ce type.',
        );
        continue;
      }

      if ( 0 === $responses ) {
        $rows[] = array(
          'type'       => $label,
          'gap'        => 'Aucune réponse exploitée',
          'severity'   => 'Élevé',
          'impact'     => 'Les sollicitations existent mais aucun retour exploitable n’est disponible.',
          'next_step'  => 'Renforcer relances, date limite et ciblage des destinataires.',
        );
      }

      if ( null !== $rate && $rate < 35 ) {
        $rows[] = array(
          'type'       => $label,
          'gap'        => 'Taux de réponse faible',
          'severity'   => 'Moyen',
          'impact'     => 'La représentativité des retours reste fragile pour ce type.',
          'next_step'  => 'Ajuster l’envoi, les relances et le moment de sollicitation.',
        );
      }

      if ( $alerts > 0 && 0 === $actions_total ) {
        $rows[] = array(
          'type'       => $label,
          'gap'        => 'Alertes non transformées en action',
          'severity'   => 'Élevé',
          'impact'     => 'Des signaux faibles sont détectés sans preuve de traitement.',
          'next_step'  => 'Créer des actions d’amélioration liées aux alertes.',
        );
      }
    }

    if ( empty( $rows ) ) {
      $rows[] = array(
        'type'       => 'Global',
        'gap'        => 'Aucun écart majeur détecté',
        'severity'   => 'Maîtrisé',
        'impact'     => 'Les fondations qualité sont cohérentes sur les types actuellement suivis.',
        'next_step'  => 'Continuer le suivi des preuves et la clôture des actions ouvertes.',
      );
    }

    return $rows;
  }

  private function get_quality_compliance_action_plan_rows( $foundation_data ) {
    $by_type = ! empty( $foundation_data['by_type'] ) && is_array( $foundation_data['by_type'] ) ? $foundation_data['by_type'] : array();
    $rows    = array();

    foreach ( $by_type as $source_type => $row ) {
      $label         = isset( $row['label'] ) ? (string) $row['label'] : (string) $source_type;
      $sessions      = (int) ( $row['sessions'] ?? 0 );
      $responses     = (int) ( $row['responses'] ?? 0 );
      $alerts        = (int) ( $row['alerts'] ?? 0 );
      $actions_total = (int) ( $row['actions_total'] ?? 0 );
      $actions_open  = (int) ( $row['actions_to_process'] ?? 0 );
      $proof_status  = isset( $row['proof_status'] ) ? (string) $row['proof_status'] : '';
      $rate          = isset( $row['response_rate'] ) ? $row['response_rate'] : null;

      $priority = 'Moyenne';
      if ( 0 === $sessions || 0 === $responses || ( $alerts > 0 && 0 === $actions_total ) ) {
        $priority = 'Haute';
      } elseif ( null !== $rate && $rate < 35 ) {
        $priority = 'Moyenne';
      } elseif ( $actions_open > 0 ) {
        $priority = 'Suivi';
      }

      if ( 0 === $sessions ) {
        $objective = 'Déployer une première sollicitation tracée';
      } elseif ( 0 === $responses ) {
        $objective = 'Obtenir des réponses exploitables';
      } elseif ( $alerts > 0 && 0 === $actions_total ) {
        $objective = 'Transformer les alertes en actions d’amélioration';
      } elseif ( $actions_open > 0 ) {
        $objective = 'Clôturer les actions ouvertes';
      } else {
        $objective = 'Maintenir la chaîne de preuve et consolider le suivi';
      }

      $rows[] = array(
        'type'          => $label,
        'objective'     => $objective,
        'priority'      => $priority,
        'proof_status'  => $proof_status,
        'actions_open'  => $actions_open,
        'owner'         => 'Pilotage qualité',
      );
    }

    return $rows;
  }

  private function get_quality_compliance_gap_register_rows( $foundation_data ) {
    $gap_rows = $this->get_quality_compliance_gap_rows( $foundation_data );
    $rows     = array();
    $index    = 1;

    foreach ( $gap_rows as $gap_row ) {
      $severity = isset( $gap_row['severity'] ) ? (string) $gap_row['severity'] : 'Moyen';
      $priority = 'Moyenne';
      if ( 'Élevé' === $severity ) {
        $priority = 'Haute';
      } elseif ( 'Maîtrisé' === $severity ) {
        $priority = 'Suivi';
      }

      $status = 'Ouvert';
      if ( 'Maîtrisé' === $severity ) {
        $status = 'Sous contrôle';
      } elseif ( false !== stripos( (string) ( $gap_row['gap'] ?? '' ), 'taux de réponse faible' ) ) {
        $status = 'À renforcer';
      }

      $rows[] = array(
        'id'             => sprintf( 'QC-E%02d', $index ),
        'type'           => isset( $gap_row['type'] ) ? (string) $gap_row['type'] : 'Global',
        'gap'            => isset( $gap_row['gap'] ) ? (string) $gap_row['gap'] : 'Écart à qualifier',
        'severity'       => $severity,
        'status'         => $status,
        'priority'       => $priority,
        'owner'          => 'Pilotage qualité',
        'due_label'      => 'J+30',
        'next_step'      => isset( $gap_row['next_step'] ) ? (string) $gap_row['next_step'] : 'Qualifier puis traiter.',
        'impact'         => isset( $gap_row['impact'] ) ? (string) $gap_row['impact'] : 'Impact à confirmer.',
        'source_proof'   => isset( $gap_row['type'] ) ? 'Enquêtes — ' . (string) $gap_row['type'] : 'Enquêtes',
      );
      $index++;
    }

    foreach ( $this->get_quality_alert_bridge_rows() as $alert_row ) {
      if ( ! is_array( $alert_row ) ) {
        continue;
      }
      $rows[] = array(
        'id' => 'QC-AL-' . strtoupper( sanitize_key( (string) ( $alert_row['alert_key'] ?? '' ) ) ),
        'type' => (string) ( $alert_row['source_label'] ?? 'Enquête' ),
        'gap' => (string) ( $alert_row['gap_title'] ?? 'Alerte qualité' ),
        'severity' => (string) ( $alert_row['severity'] ?? 'Moyen' ),
        'status' => (string) ( $alert_row['gap_status'] ?? $alert_row['status_label'] ?? 'Alerte ouverte' ),
        'priority' => (string) ( $alert_row['priority'] ?? 'Moyenne' ),
        'owner' => (string) ( $alert_row['assigned_owner'] ?? 'Pilotage qualité' ),
        'due_label' => (string) ( $alert_row['gap_due_label'] ?? 'J+7' ),
        'next_step' => (string) ( $alert_row['gap_next_step'] ?? 'Qualifier puis traiter.' ),
        'impact' => (string) ( $alert_row['gap_impact'] ?? 'Impact à confirmer.' ),
        'source_proof' => 'Enquêtes → alertes · ' . (string) ( $alert_row['session_title'] ?? '' ) . ( ! empty( $alert_row['participant_label'] ) ? ' · ' . (string) $alert_row['participant_label'] : '' ),
      );
    }

    foreach ( $this->get_quality_gap_records() as $record ) {
      if ( ! is_array( $record ) ) {
        continue;
      }
      $rows[] = $this->get_quality_gap_form_defaults( $record );
    }

    return $rows;
  }

  private function get_quality_compliance_full_action_plan_rows( $foundation_data ) {
    $base_rows = $this->get_quality_compliance_action_plan_rows( $foundation_data );
    $rows      = array();
    $index     = 1;

    foreach ( $base_rows as $row ) {
      $priority = isset( $row['priority'] ) ? (string) $row['priority'] : 'Moyenne';
      $status   = 'À ouvrir';
      if ( 'Suivi' === $priority ) {
        $status = 'En suivi';
      } elseif ( ! empty( $row['actions_open'] ) ) {
        $status = 'En cours';
      }

      $due_label = 'J+30';
      if ( 'Haute' === $priority ) {
        $due_label = 'J+7';
      } elseif ( 'Suivi' === $priority ) {
        $due_label = 'J+45';
      }

      $rows[] = array(
        'id'            => sprintf( 'QC-A%02d', $index ),
        'type'          => isset( $row['type'] ) ? (string) $row['type'] : 'Global',
        'objective'     => isset( $row['objective'] ) ? (string) $row['objective'] : 'Objectif qualité à préciser',
        'priority'      => $priority,
        'status'        => $status,
        'proof_status'  => isset( $row['proof_status'] ) ? (string) $row['proof_status'] : 'À qualifier',
        'actions_open'  => (int) ( $row['actions_open'] ?? 0 ),
        'owner'         => isset( $row['owner'] ) ? (string) $row['owner'] : 'Pilotage qualité',
        'due_label'     => $due_label,
        'evidence'      => isset( $row['proof_status'] ) ? 'Statut de preuve : ' . (string) $row['proof_status'] : 'Preuve à consolider',
        'success'       => 'Chaîne de preuve consolidée et actions clôturées.',
      );
      $index++;
    }

    foreach ( $this->get_quality_alert_bridge_rows() as $alert_row ) {
      if ( ! is_array( $alert_row ) ) {
        continue;
      }
      $rows[] = array(
        'id' => 'QC-LIA-' . strtoupper( sanitize_key( (string) ( $alert_row['alert_key'] ?? '' ) ) ),
        'type' => (string) ( $alert_row['source_label'] ?? 'Enquête' ),
        'objective' => (string) ( $alert_row['action_objective'] ?? 'Traiter le signal faible détecté' ),
        'priority' => (string) ( $alert_row['priority'] ?? 'Moyenne' ),
        'status' => (string) ( $alert_row['action_status'] ?? 'À ouvrir' ),
        'proof_status' => (string) ( $alert_row['proof_status'] ?? 'Alerte détectée' ),
        'actions_open' => 1,
        'owner' => (string) ( $alert_row['assigned_owner'] ?? 'Pilotage qualité' ),
        'due_label' => (string) ( $alert_row['action_due_label'] ?? 'J+7' ),
        'evidence' => 'Alertes → écarts · ' . (string) ( $alert_row['action_evidence'] ?? '' ),
        'success' => 'Écart ouvert, analyse menée, action suivie et journalisée.',
      );
    }

    foreach ( $this->get_quality_action_records() as $record ) {
      if ( ! is_array( $record ) ) {
        continue;
      }
      $rows[] = $this->get_quality_action_form_defaults( $record );
    }

    return $rows;
  }


  private function get_quality_compliance_due_date_from_label( $label ) {
    $label = trim( (string) $label );
    if ( '' === $label ) {
      return '';
    }
    if ( preg_match( '/(\d{4}-\d{2}-\d{2})/', $label, $matches ) ) {
      return (string) $matches[1];
    }
    if ( preg_match( '/J\+(\d+)/i', $label, $matches ) ) {
      return gmdate( 'Y-m-d', strtotime( '+' . absint( $matches[1] ) . ' days', current_time( 'timestamp' ) ) );
    }
    return '';
  }


  private function get_quality_compliance_processing_stage_label( $status, $type = 'generic' ) {
    $status_lc = mb_strtolower( trim( (string) $status ) );
    if ( '' === $status_lc ) {
      return 'À lancer';
    }
    if ( false !== strpos( $status_lc, 'clos' ) || false !== strpos( $status_lc, 'termin' ) || false !== strpos( $status_lc, 'réalis' ) || false !== strpos( $status_lc, 'realis' ) || false !== strpos( $status_lc, 'résolu' ) || false !== strpos( $status_lc, 'resolu' ) ) {
      return 'Clôturée';
    }
    if ( false !== strpos( $status_lc, 'cours' ) || false !== strpos( $status_lc, 'suivi' ) || false !== strpos( $status_lc, 'trait' ) ) {
      return 'En traitement';
    }
    if ( false !== strpos( $status_lc, 'assign' ) || false !== strpos( $status_lc, 'ouvrir' ) || false !== strpos( $status_lc, 'qualifier' ) || false !== strpos( $status_lc, 'analyser' ) || false !== strpos( $status_lc, 'renforcer' ) ) {
      return 'Prise en charge';
    }
    return 'À lancer';
  }

  private function get_quality_compliance_proof_stage_label( $proof_status ) {
    $proof_lc = mb_strtolower( trim( (string) $proof_status ) );
    if ( '' === $proof_lc ) {
      return 'À produire';
    }
    if ( false !== strpos( $proof_lc, 'consolid') || false !== strpos( $proof_lc, 'complèt') || false !== strpos( $proof_lc, 'complet') || false !== strpos( $proof_lc, 'trace') ) {
      return 'Consolidée';
    }
    if ( false !== strpos( $proof_lc, 'qualifi') || false !== strpos( $proof_lc, 'traiter') || false !== strpos( $proof_lc, 'enclencher') || false !== strpos( $proof_lc, 'relancer') || false !== strpos( $proof_lc, 'détect') || false !== strpos( $proof_lc, 'detect') ) {
      return 'À produire';
    }
    if ( false !== strpos( $proof_lc, 'partiel') ) {
      return 'Partielle';
    }
    return 'À produire';
  }

  private function build_quality_compliance_operational_loop_row( $queue_row ) {
    $owner = (string) ( $queue_row['owner'] ?? 'Pilotage qualité' );
    $intake = ! empty( $queue_row['intake_status'] )
      ? (string) $queue_row['intake_status']
      : ( 'Pilotage qualité' === $owner && empty( $queue_row['owner'] ) ? 'À prendre en charge' : 'Prise en charge' );
    $treatment = ! empty( $queue_row['treatment_status'] )
      ? (string) $queue_row['treatment_status']
      : $this->get_quality_compliance_processing_stage_label( (string) ( $queue_row['action_status'] ?? $queue_row['gap_status'] ?? '' ), 'treatment' );
    $proof = ! empty( $queue_row['proof_stage'] )
      ? (string) $queue_row['proof_stage']
      : $this->get_quality_compliance_proof_stage_label( (string) ( $queue_row['proof_status'] ?? '' ) );
    $closure = ! empty( $queue_row['closure_status'] )
      ? (string) $queue_row['closure_status']
      : ( 'Clôturée' === $treatment ? 'Clôturée' : ( 'Consolidée' === $proof ? 'À valider' : 'Ouverte' ) );
    $reopen = ! empty( $queue_row['reopen_status'] )
      ? (string) $queue_row['reopen_status']
      : ( 'Clôturée' === $closure
        ? ( 'Consolidée' === $proof ? 'Stable' : 'Réouverture possible' )
        : ( 'Haute' === (string) ( $queue_row['priority'] ?? '' ) ? 'Sous surveillance' : 'Non' ) );
    $alert_key = sanitize_key( (string) ( $queue_row['alert_key'] ?? '' ) );

    return array(
      'alert_key' => $alert_key,
      'source' => (string) ( $queue_row['source'] ?? 'Enquête' ),
      'respondent' => (string) ( $queue_row['respondent'] ?? 'Session complète' ),
      'owner' => $owner,
      'alert_type_key' => (string) ( $queue_row['alert_type_key'] ?? 'generic_alert' ),
      'alert_type_label' => (string) ( $queue_row['alert_type_label'] ?? 'Alerte qualité' ),
      'default_transition' => (string) ( $queue_row['default_transition'] ?? 'take_in_charge' ),
      'default_action_label' => (string) ( $queue_row['default_action_label'] ?? 'Prendre en charge' ),
      'intake_status' => $intake,
      'treatment_status' => $treatment,
      'proof_stage' => $proof,
      'closure_status' => $closure,
      'reopen_status' => $reopen,
      'due_label' => (string) ( $queue_row['due_label'] ?? '—' ),
      'priority' => (string) ( $queue_row['priority'] ?? 'Moyenne' ),
      'priority_auto' => (string) ( $queue_row['priority_auto'] ?? $queue_row['priority'] ?? 'Normale' ),
      'take_in_charge_url' => $this->get_quality_loop_action_url( $alert_key, 'take_in_charge' ),
      'open_treatment_url' => $this->get_quality_loop_action_url( $alert_key, 'open_treatment' ),
      'deposit_proof_url' => $this->get_quality_loop_action_url( $alert_key, 'deposit_proof' ),
      'close_url' => $this->get_quality_loop_action_url( $alert_key, 'close' ),
      'validate_closure_url' => $this->get_quality_loop_action_url( $alert_key, 'validate_closure' ),
      'reject_closure_url' => $this->get_quality_loop_action_url( $alert_key, 'reject_closure' ),
      'reopen_url' => $this->get_quality_loop_action_url( $alert_key, 'reopen' ),
    );
  }

  private function get_quality_compliance_automatic_priority_label( $queue_row, $today = '' ) {
    $today = is_string( $today ) && '' !== $today ? $today : current_time( 'Y-m-d' );
    $score = 0;
    $severity = mb_strtolower( (string) ( $queue_row['severity'] ?? '' ) );
    $priority = mb_strtolower( (string) ( $queue_row['priority'] ?? '' ) );
    $scenario = mb_strtolower( (string) ( $queue_row['scenario'] ?? '' ) );
    $proof = mb_strtolower( (string) ( $queue_row['proof_status'] ?? '' ) );
    $intake = mb_strtolower( (string) ( $queue_row['intake_status'] ?? '' ) );
    $treatment = mb_strtolower( (string) ( $queue_row['treatment_status'] ?? '' ) );
    $due_date = $this->get_quality_compliance_due_date_from_label( (string) ( $queue_row['due_label'] ?? '' ) );

    if ( false !== strpos( $severity, 'élev' ) || false !== strpos( $severity, 'haut' ) ) { $score += 4; }
    elseif ( false !== strpos( $severity, 'moy' ) ) { $score += 2; }
    if ( false !== strpos( $priority, 'haut' ) ) { $score += 3; }
    elseif ( false !== strpos( $priority, 'moy' ) ) { $score += 1; }
    if ( false !== strpos( $scenario, 'réclamation' ) || false !== strpos( $scenario, 'reclamation' ) || false !== strpos( $scenario, 'réserve' ) || false !== strpos( $scenario, 'reserve' ) ) { $score += 4; }
    if ( false !== strpos( $scenario, 'difficult' ) || false !== strpos( $scenario, 'score' ) ) { $score += 2; }
    if ( '' === $intake || false !== strpos( $intake, 'à prendre' ) ) { $score += 3; }
    if ( '' !== $due_date ) {
      if ( $due_date < $today ) { $score += 4; }
      elseif ( ( strtotime( $due_date ) - strtotime( $today ) ) <= 2 * DAY_IN_SECONDS ) { $score += 2; }
    }
    if ( false !== strpos( $proof, 'détect' ) || false !== strpos( $proof, 'produire' ) ) { $score += 1; }
    if ( false !== strpos( $treatment, 'clôtur' ) || false !== strpos( $treatment, 'clotur' ) ) { $score -= 2; }

    if ( $score >= 10 ) { return 'Très haute priorité'; }
    if ( $score >= 7 ) { return 'Haute'; }
    if ( $score >= 4 ) { return 'Normale'; }
    return 'Basse';
  }


  private function get_quality_compliance_todo_hierarchy_data( $loop_row, $today = '' ) {
    $today = is_string( $today ) && '' !== $today ? $today : current_time( 'Y-m-d' );
    $intake_status = (string) ( $loop_row['intake_status'] ?? '' );
    $treatment_status = (string) ( $loop_row['treatment_status'] ?? '' );
    $proof_stage = (string) ( $loop_row['proof_stage'] ?? '' );
    $closure_status = (string) ( $loop_row['closure_status'] ?? '' );
    $due_label = (string) ( $loop_row['due_label'] ?? '' );
    $due_date = $this->get_quality_compliance_due_date_from_label( $due_label );

    if ( false !== stripos( $intake_status, 'À prendre' ) || false === stripos( $treatment_status, 'trait' ) && false === stripos( $treatment_status, 'clôtur' ) ) {
      return array(
        'order' => 0,
        'label' => 'Urgence',
        'detail' => 'Prise en charge ou ouverture de traitement prioritaire.',
      );
    }

    if ( '' !== $due_date ) {
      if ( $due_date < $today ) {
        return array(
          'order' => 1,
          'label' => 'Retard',
          'detail' => 'Échéance dépassée à résorber en priorité.',
        );
      }
      if ( ( strtotime( $due_date ) - strtotime( $today ) ) <= 2 * DAY_IN_SECONDS ) {
        return array(
          'order' => 1,
          'label' => 'Retard',
          'detail' => 'Échéance très proche à sécuriser immédiatement.',
        );
      }
    }

    if ( false !== stripos( $proof_stage, 'À produire' ) || false !== stripos( $proof_stage, 'Partielle' ) ) {
      return array(
        'order' => 2,
        'label' => 'Preuve manquante',
        'detail' => 'Preuve à déposer ou à consolider avant clôture.',
      );
    }

    if ( false !== stripos( $closure_status, 'À valider' ) || false !== stripos( $closure_status, 'À clôturer' ) ) {
      return array(
        'order' => 3,
        'label' => 'Validation de clôture',
        'detail' => 'Clôture prête ou quasi prête à arbitrer.',
      );
    }

    return array(
      'order' => 4,
      'label' => 'Suivi',
      'detail' => 'Traitement à poursuivre selon la file prioritaire.',
    );
  }


  private function get_quality_compliance_row_action_bundle( $loop_row, $context = 'todo' ) {
    $loop_row = is_array( $loop_row ) ? $loop_row : array();
    $context = is_string( $context ) && '' !== $context ? $context : 'todo';

    $primary_label = 'Suivre';
    $primary_url = '';
    $secondary_label = '';
    $secondary_url = '';

    $intake_status = (string) ( $loop_row['intake_status'] ?? '' );
    $treatment_status = (string) ( $loop_row['treatment_status'] ?? '' );
    $proof_stage = (string) ( $loop_row['proof_stage'] ?? '' );
    $closure_status = (string) ( $loop_row['closure_status'] ?? '' );
    $reopen_status = (string) ( $loop_row['reopen_status'] ?? '' );
    $default_transition = (string) ( $loop_row['default_transition'] ?? 'take_in_charge' );
    $take_in_charge_url = (string) ( $loop_row['take_in_charge_url'] ?? '' );
    $open_treatment_url = (string) ( $loop_row['open_treatment_url'] ?? '' );
    $deposit_proof_url = (string) ( $loop_row['deposit_proof_url'] ?? '' );
    $validate_closure_url = (string) ( $loop_row['validate_closure_url'] ?? '' );
    $reject_closure_url = (string) ( $loop_row['reject_closure_url'] ?? '' );
    $reopen_url = (string) ( $loop_row['reopen_url'] ?? '' );

    if ( false !== stripos( $intake_status, 'À prendre' ) ) {
      if ( 'open_treatment' === $default_transition && '' !== $open_treatment_url ) {
        $primary_label = (string) ( $loop_row['default_action_label'] ?? 'Ouvrir traitement' );
        $primary_url = $open_treatment_url;
        if ( '' !== $take_in_charge_url ) {
          $secondary_label = 'Prendre en charge';
          $secondary_url = $take_in_charge_url;
        }
      } else {
        $primary_label = (string) ( $loop_row['default_action_label'] ?? 'Prendre en charge' );
        $primary_url = $take_in_charge_url;
        if ( '' !== $open_treatment_url ) {
          $secondary_label = 'Ouvrir traitement';
          $secondary_url = $open_treatment_url;
        }
      }
    } elseif ( false !== stripos( $closure_status, 'À valider' ) || false !== stripos( $closure_status, 'À clôturer' ) || false !== stripos( $reopen_status, 'À réouvrir' ) ) {
      if ( false !== stripos( $reopen_status, 'À réouvrir' ) && '' !== $reject_closure_url ) {
        $primary_label = 'Refuser / réouvrir';
        $primary_url = $reject_closure_url;
        if ( '' !== $validate_closure_url ) {
          $secondary_label = 'Valider clôture';
          $secondary_url = $validate_closure_url;
        } elseif ( '' !== $reopen_url ) {
          $secondary_label = 'Réouvrir';
          $secondary_url = $reopen_url;
        }
      } else {
        $primary_label = 'Valider clôture';
        $primary_url = $validate_closure_url;
        if ( '' !== $reject_closure_url ) {
          $secondary_label = 'Refuser / réouvrir';
          $secondary_url = $reject_closure_url;
        }
      }
    } elseif ( false === stripos( $treatment_status, 'trait' ) && false === stripos( $treatment_status, 'clôtur' ) ) {
      $primary_label = 'Ouvrir traitement';
      $primary_url = $open_treatment_url;
      if ( '' !== $deposit_proof_url ) {
        $secondary_label = 'Déposer preuve';
        $secondary_url = $deposit_proof_url;
      }
    } elseif ( false !== stripos( $proof_stage, 'À produire' ) || false !== stripos( $proof_stage, 'Partielle' ) ) {
      $primary_label = 'Déposer preuve';
      $primary_url = $deposit_proof_url;
      if ( '' !== $validate_closure_url ) {
        $secondary_label = 'Valider clôture';
        $secondary_url = $validate_closure_url;
      } elseif ( '' !== $open_treatment_url ) {
        $secondary_label = 'Ouvrir traitement';
        $secondary_url = $open_treatment_url;
      }
    }

    if ( '' === $primary_url && '' !== $secondary_url ) {
      $primary_label = $secondary_label;
      $primary_url = $secondary_url;
      $secondary_label = '';
      $secondary_url = '';
    }

    if ( '' !== $primary_url && '' !== $secondary_url && $primary_url === $secondary_url ) {
      $secondary_label = '';
      $secondary_url = '';
    }

    if ( 'owner' === $context && '' !== $primary_url && '' !== $secondary_url && false !== stripos( $secondary_label, 'Valider' ) ) {
      // conserver la place pour une action réellement alternative dans les vues de synthèse.
      $secondary_label = '';
      $secondary_url = '';
    }

    return array(
      'primary_label'   => $primary_label,
      'primary_url'     => $primary_url,
      'secondary_label' => $secondary_label,
      'secondary_url'   => $secondary_url,
    );
  }

  private function get_quality_compliance_internal_reminder_rows( $loop_rows, $today = '' ) {
    $today = is_string( $today ) && '' !== $today ? $today : current_time( 'Y-m-d' );
    $rows = array();
    foreach ( (array) $loop_rows as $loop_row ) {
      if ( ! is_array( $loop_row ) ) {
        continue;
      }
      $reason = '';
      $event = '';
      $urgency = 'Normale';
      $action_label = 'Suivre';
      $action_url = '';
      $secondary_action_label = '';
      $secondary_action_url = '';
      $reminder_type = '';
      $reminder_scope = 'Suivi';
      $due_date = $this->get_quality_compliance_due_date_from_label( (string) ( $loop_row['due_label'] ?? '' ) );
      $proof_stage = (string) ( $loop_row['proof_stage'] ?? '' );
      $closure_status = (string) ( $loop_row['closure_status'] ?? '' );
      $reopen_status = (string) ( $loop_row['reopen_status'] ?? '' );
      $treatment_status = (string) ( $loop_row['treatment_status'] ?? '' );
      $intake_status = (string) ( $loop_row['intake_status'] ?? '' );

      if ( false !== stripos( $intake_status, 'À prendre' ) ) {
        $reason = 'Aucune prise en charge enregistrée.';
        $event = 'Relance interne · prise en charge';
        $urgency = 'Haute';
        $reminder_type = 'Relance de prise en charge';
        $reminder_scope = 'Prise en charge';
        $default_transition = (string) ( $loop_row['default_transition'] ?? 'take_in_charge' );
        if ( 'open_treatment' === $default_transition ) {
          $action_label = (string) ( $loop_row['default_action_label'] ?? 'Ouvrir traitement' );
          $action_url = (string) ( $loop_row['open_treatment_url'] ?? '' );
        } else {
          $action_label = (string) ( $loop_row['default_action_label'] ?? 'Prendre en charge' );
          $action_url = (string) ( $loop_row['take_in_charge_url'] ?? '' );
        }
      } elseif ( false !== stripos( $closure_status, 'À valider' ) || false !== stripos( $closure_status, 'À clôturer' ) || false !== stripos( $reopen_status, 'À réouvrir' ) ) {
        $reason = false !== stripos( $reopen_status, 'À réouvrir' ) ? 'Réouverture demandée avant validation finale.' : 'Clôture prête ou quasi prête à arbitrer.';
        $event = 'Relance interne · clôture';
        $urgency = false !== stripos( $reopen_status, 'À réouvrir' ) ? 'Haute' : 'Normale';
        $reminder_type = 'Relance de clôture';
        $reminder_scope = 'Clôture';
        if ( false !== stripos( $reopen_status, 'À réouvrir' ) ) {
          $action_label = 'Refuser / réouvrir';
          $action_url = (string) ( $loop_row['reject_closure_url'] ?? '' );
        } else {
          $action_label = 'Valider clôture';
          $action_url = (string) ( $loop_row['validate_closure_url'] ?? '' );
        }
      } elseif ( '' !== $due_date && $due_date < $today && false === stripos( $closure_status, 'Clôtur' ) ) {
        $reason = 'Échéance dépassée.';
        $event = 'Relance interne · échéance';
        $urgency = 'Très haute priorité';
        $reminder_type = 'Relance d’échéance';
        $reminder_scope = 'Échéance';
        if ( false === stripos( $treatment_status, 'trait' ) && false === stripos( $treatment_status, 'clôtur' ) ) {
          $action_label = 'Résorber le retard';
          $action_url = (string) ( $loop_row['open_treatment_url'] ?? '' );
        } elseif ( false !== stripos( $proof_stage, 'À produire' ) || false !== stripos( $proof_stage, 'Partielle' ) ) {
          $action_label = 'Sécuriser la preuve';
          $action_url = (string) ( $loop_row['deposit_proof_url'] ?? '' );
        } else {
          $action_label = 'Arbitrer la clôture';
          $action_url = ! empty( $loop_row['validate_closure_url'] ) ? (string) $loop_row['validate_closure_url'] : (string) ( $loop_row['open_treatment_url'] ?? '' );
        }
      } elseif ( '' !== $due_date && ( strtotime( $due_date ) - strtotime( $today ) ) <= 2 * DAY_IN_SECONDS && false === stripos( $closure_status, 'Clôtur' ) ) {
        $reason = 'Échéance proche.';
        $event = 'Relance interne · échéance proche';
        $urgency = 'Haute';
        $reminder_type = 'Relance d’échéance';
        $reminder_scope = 'Échéance';
        if ( false !== stripos( $proof_stage, 'À produire' ) || false !== stripos( $proof_stage, 'Partielle' ) ) {
          $action_label = 'Sécuriser la preuve';
          $action_url = (string) ( $loop_row['deposit_proof_url'] ?? '' );
        } else {
          $action_label = 'Sécuriser l’échéance';
          $action_url = ! empty( $loop_row['open_treatment_url'] ) ? (string) $loop_row['open_treatment_url'] : (string) ( $loop_row['validate_closure_url'] ?? '' );
        }
      } elseif ( ( false !== stripos( $proof_stage, 'À produire' ) || false !== stripos( $proof_stage, 'Partielle' ) ) && false !== stripos( $treatment_status, 'trait' ) ) {
        $reason = 'Preuve encore attendue.';
        $event = 'Relance interne · preuve attendue';
        $urgency = 'Normale';
        $reminder_type = 'Relance de preuve';
        $reminder_scope = 'Preuve';
        $action_label = 'Déposer preuve';
        $action_url = (string) ( $loop_row['deposit_proof_url'] ?? '' );
      }
      if ( '' === $reason ) {
        continue;
      }
      $action_bundle = $this->get_quality_compliance_row_action_bundle( $loop_row, 'reminder' );
      if ( ! empty( $action_bundle['primary_label'] ) ) {
        $action_label = (string) $action_bundle['primary_label'];
      }
      if ( ! empty( $action_bundle['primary_url'] ) ) {
        $action_url = (string) $action_bundle['primary_url'];
      }
      $secondary_action_label = (string) ( $action_bundle['secondary_label'] ?? '' );
      $secondary_action_url = (string) ( $action_bundle['secondary_url'] ?? '' );
      $rows[] = array(
        'alert_key' => (string) ( $loop_row['alert_key'] ?? '' ),
        'date' => current_time( 'mysql' ),
        'owner' => (string) ( $loop_row['owner'] ?? 'Pilotage qualité' ),
        'source' => (string) ( $loop_row['source'] ?? 'Enquête' ),
        'respondent' => (string) ( $loop_row['respondent'] ?? 'Session complète' ),
        'alert_type_label' => (string) ( $loop_row['alert_type_label'] ?? 'Alerte qualité' ),
        'reason' => $reason,
        'event' => $event,
        'status' => 'Relance interne',
        'urgency' => $urgency,
        'reminder_type' => $reminder_type,
        'reminder_scope' => $reminder_scope,
        'action_label' => $action_label,
        'action_url' => $action_url,
        'secondary_action_label' => $secondary_action_label,
        'secondary_action_url' => $secondary_action_url,
      );
    }
    return $rows;
  }


  private function get_quality_compliance_owner_load_label( $owner_row ) {
    $owner_row = is_array( $owner_row ) ? $owner_row : array();
    $critical = isset( $owner_row['critical'] ) ? (int) $owner_row['critical'] : 0;
    $overdue = isset( $owner_row['overdue'] ) ? (int) $owner_row['overdue'] : 0;
    $todo_now = isset( $owner_row['todo_now'] ) ? (int) $owner_row['todo_now'] : 0;
    $open = isset( $owner_row['actions_open'] ) ? (int) $owner_row['actions_open'] : 0;
    $closures_to_validate = isset( $owner_row['closures_to_validate'] ) ? (int) $owner_row['closures_to_validate'] : 0;
    $proof_to_secure = isset( $owner_row['proof_to_secure'] ) ? (int) $owner_row['proof_to_secure'] : 0;
    $arbitrations = isset( $owner_row['arbitrations'] ) ? (int) $owner_row['arbitrations'] : 0;

    $load_score = ( $critical * 3 ) + ( $overdue * 2 ) + $todo_now + $closures_to_validate + $proof_to_secure + $arbitrations + max( 0, $open - 1 );

    if ( $load_score >= 12 || $critical >= 3 || $overdue >= 3 || $todo_now >= 6 ) {
      return 'Très chargée';
    }
    if ( $load_score >= 6 || $critical >= 1 || $overdue >= 1 || $todo_now >= 3 || $open >= 5 ) {
      return 'Chargée';
    }
    if ( $open > 0 || $todo_now > 0 || $closures_to_validate > 0 || $proof_to_secure > 0 ) {
      return 'Sous contrôle';
    }
    return 'Stable';
  }

  private function get_quality_compliance_owner_delay_label( $owner_row ) {
    $owner_row = is_array( $owner_row ) ? $owner_row : array();
    $overdue = isset( $owner_row['overdue'] ) ? (int) $owner_row['overdue'] : 0;
    if ( $overdue >= 3 ) {
      return 'Retards critiques';
    }
    if ( $overdue >= 1 ) {
      return 'Retards à résorber';
    }
    return 'Maîtrisé';
  }

  private function get_quality_compliance_owner_arbitration_meta( $owner_row ) {
    $owner_row   = is_array( $owner_row ) ? $owner_row : array();
    $closures    = (int) ( $owner_row['closures_to_validate'] ?? 0 );
    $proofs      = (int) ( $owner_row['proof_to_secure'] ?? 0 );
    $overdue     = (int) ( $owner_row['overdue'] ?? 0 );
    $critical    = (int) ( $owner_row['critical'] ?? 0 );
    $todo        = (int) ( $owner_row['todo_now'] ?? 0 );
    $arbitration = (int) ( $owner_row['arbitrations'] ?? 0 );

    if ( $critical >= 2 || $overdue >= 3 ) {
      return array(
        'type'   => 'Arbitrage urgence',
        'focus'  => 'Urgence à arbitrer',
        'detail' => 'Décider immédiatement les prises en charge prioritaires et les retards critiques à absorber.',
        'action' => 'Arbitrer l’urgence',
        'rank'   => 1,
      );
    }
    if ( $closures > 0 && $proofs > 0 ) {
      return array(
        'type'   => 'Arbitrage clôture',
        'focus'  => 'Clôtures et preuves',
        'detail' => 'Décider quoi valider, quoi surveiller et quoi réouvrir selon le niveau de preuve disponible.',
        'action' => 'Arbitrer les clôtures',
        'rank'   => 2,
      );
    }
    if ( $closures > 0 ) {
      return array(
        'type'   => 'Arbitrage clôture',
        'focus'  => 'Clôtures à arbitrer',
        'detail' => 'Décider les validations, les surveillances renforcées et les refus de clôture.',
        'action' => 'Arbitrer les clôtures',
        'rank'   => 2,
      );
    }
    if ( $proofs > 0 ) {
      return array(
        'type'   => 'Arbitrage preuve',
        'focus'  => 'Preuves à sécuriser',
        'detail' => 'Décider les preuves à consolider, compléter ou exiger avant poursuite du traitement.',
        'action' => 'Arbitrer les preuves',
        'rank'   => 3,
      );
    }
    if ( $todo > 0 || $arbitration > 0 ) {
      return array(
        'type'   => 'Arbitrage charge',
        'focus'  => 'Charge à répartir',
        'detail' => 'Ajuster la répartition des traitements ouverts, des files prioritaires et des actions à absorber.',
        'action' => 'Arbitrer la charge',
        'rank'   => 4,
      );
    }

    return array(
      'type'   => 'Aucun arbitrage',
      'focus'  => 'Aucun arbitrage',
      'detail' => 'Aucun arbitrage transverse prioritaire à ce stade.',
      'action' => 'Aucun arbitrage',
      'rank'   => 99,
    );
  }

  private function get_quality_compliance_owner_arbitration_label( $owner_row ) {
    $meta = $this->get_quality_compliance_owner_arbitration_meta( $owner_row );
    return (string) ( $meta['type'] ?? 'Aucun arbitrage' );
  }


  private function get_quality_compliance_owner_arbitration_focus( $owner_row ) {
    $meta = $this->get_quality_compliance_owner_arbitration_meta( $owner_row );
    return array(
      'focus'  => (string) ( $meta['focus'] ?? 'Aucun arbitrage' ),
      'detail' => (string) ( $meta['detail'] ?? 'Aucun arbitrage transverse prioritaire à ce stade.' ),
      'action' => (string) ( $meta['action'] ?? 'Aucun arbitrage' ),
      'rank'   => (int) ( $meta['rank'] ?? 99 ),
    );
  }

  private function get_quality_compliance_owner_next_action_label( $owner_row ) {
    $owner_row = is_array( $owner_row ) ? $owner_row : array();
    if ( ! empty( $owner_row['overdue'] ) ) {
      return 'Résorber les retards';
    }
    if ( ! empty( $owner_row['closures_to_validate'] ) ) {
      return 'Arbitrer les clôtures';
    }
    if ( ! empty( $owner_row['proof_to_secure'] ) ) {
      return 'Sécuriser les preuves';
    }
    if ( ! empty( $owner_row['critical'] ) ) {
      return 'Prendre les alertes critiques';
    }
    if ( ! empty( $owner_row['todo_now'] ) ) {
      return 'Avancer sur la file prioritaire';
    }
    if ( ! empty( $owner_row['actions_open'] ) ) {
      return 'Finaliser les actions ouvertes';
    }
    return 'Aucune action urgente';
  }

  private function get_quality_compliance_supervision_data( $alert_rows, $gap_rows, $plan_rows, $audit_log_rows ) {
    $today = current_time( 'Y-m-d' );
    $queue_rows = array();
    $owner_index = array();
    $late_gaps = array();
    $late_actions = array();
    $closure_rows = array();
    $traceability_rows = array();
    $loop_rows = array();
    $loop_stats = array(
      'intake' => 0,
      'treatment' => 0,
      'proof' => 0,
      'closure' => 0,
      'reopen' => 0,
    );

    foreach ( (array) $alert_rows as $alert_row ) {
      if ( ! is_array( $alert_row ) ) {
        continue;
      }
      $queue_rows[] = array(
        'alert_key' => (string) ( $alert_row['alert_key'] ?? '' ),
        'source' => (string) ( $alert_row['source_label'] ?? 'Enquête' ),
        'session' => (string) ( $alert_row['session_title'] ?? '—' ),
        'respondent' => (string) ( $alert_row['participant_label'] ?? 'Session complète' ),
        'priority' => (string) ( $alert_row['priority'] ?? 'Moyenne' ),
        'severity' => (string) ( $alert_row['severity'] ?? 'Moyen' ),
        'scenario' => (string) ( $alert_row['scenario_label'] ?? 'Qualification qualité' ),
        'owner' => (string) ( $alert_row['assigned_owner'] ?? 'Pilotage qualité' ),
        'gap_status' => (string) ( $alert_row['gap_status'] ?? 'À qualifier' ),
        'action_status' => (string) ( $alert_row['action_status'] ?? 'À ouvrir' ),
        'proof_status' => (string) ( $alert_row['proof_status'] ?? 'Alerte détectée' ),
        'intake_status' => (string) ( $alert_row['intake_status'] ?? '' ),
        'treatment_status' => (string) ( $alert_row['treatment_status'] ?? '' ),
        'proof_stage' => (string) ( $alert_row['proof_stage'] ?? '' ),
        'closure_status' => (string) ( $alert_row['closure_status'] ?? '' ),
        'reopen_status' => (string) ( $alert_row['reopen_status'] ?? '' ),
        'due_label' => (string) ( $alert_row['action_due_label'] ?? $alert_row['gap_due_label'] ?? 'J+7' ),
      );
      $queue_rows[ count( $queue_rows ) - 1 ]['priority_auto'] = $this->get_quality_compliance_automatic_priority_label( $queue_rows[ count( $queue_rows ) - 1 ], $today );
    }

    $priority_order = array( 'Très haute priorité' => 0, 'Haute' => 1, 'Moyenne' => 2, 'Normale' => 2, 'Basse' => 3, 'Suivi' => 4 );
    usort( $queue_rows, static function( $a, $b ) use ( $priority_order ) {
      $pa = $priority_order[ $a['priority_auto'] ?? $a['priority'] ] ?? 9;
      $pb = $priority_order[ $b['priority_auto'] ?? $b['priority'] ] ?? 9;
      if ( $pa === $pb ) {
        return strcasecmp( (string) $a['owner'], (string) $b['owner'] );
      }
      return $pa <=> $pb;
    } );

    foreach ( (array) $plan_rows as $row ) {
      if ( ! is_array( $row ) ) {
        continue;
      }
      $owner = (string) ( $row['owner'] ?? 'Pilotage qualité' );
      if ( ! isset( $owner_index[ $owner ] ) ) {
        $owner_index[ $owner ] = array(
          'owner' => $owner,
          'alerts' => 0,
          'actions_open' => 0,
          'actions_closed' => 0,
          'overdue' => 0,
          'critical' => 0,
          'todo_now' => 0,
          'late_actions' => 0,
          'late_gaps' => 0,
          'closures_to_validate' => 0,
          'proof_to_secure' => 0,
          'arbitrations' => 0,
          'load_label' => 'Stable',
          'next_action' => 'Aucune action urgente',
          'delay_label' => 'Maîtrisé',
          'arbitration_label' => 'Aucun arbitrage',
        );
      }
      $status = mb_strtolower( (string) ( $row['status'] ?? '' ) );
      $priority = (string) ( $row['priority'] ?? 'Moyenne' );
      $is_closed = false !== strpos( $status, 'clos' ) || false !== strpos( $status, 'termin' ) || false !== strpos( $status, 'réalis' ) || false !== strpos( $status, 'realis' );
      if ( $is_closed ) {
        $owner_index[ $owner ]['actions_closed']++;
        $closure_rows[] = array(
          'type' => (string) ( $row['type'] ?? 'Qualité' ),
          'owner' => $owner,
          'objective' => (string) ( $row['objective'] ?? 'Action qualité' ),
          'status' => (string) ( $row['status'] ?? 'Clôturée' ),
          'proof_status' => (string) ( $row['proof_status'] ?? 'Trace consolidée' ),
        );
      } else {
        $owner_index[ $owner ]['actions_open']++;
      }
      if ( 'Haute' === $priority ) {
        $owner_index[ $owner ]['critical']++;
      }
      $due_date = $this->get_quality_compliance_due_date_from_label( (string) ( $row['due_label'] ?? '' ) );
      if ( '' !== $due_date && $due_date < $today && ! $is_closed ) {
        $owner_index[ $owner ]['overdue']++;
        $owner_index[ $owner ]['late_actions']++;
        $late_actions[] = array(
          'type' => (string) ( $row['type'] ?? 'Qualité' ),
          'owner' => $owner,
          'title' => (string) ( $row['objective'] ?? 'Action qualité' ),
          'status' => (string) ( $row['status'] ?? 'En cours' ),
          'due_label' => (string) ( $row['due_label'] ?? '—' ),
          'priority' => $priority,
        );
      }
    }

    foreach ( (array) $gap_rows as $row ) {
      if ( ! is_array( $row ) ) {
        continue;
      }
      $owner = (string) ( $row['owner'] ?? 'Pilotage qualité' );
      if ( ! isset( $owner_index[ $owner ] ) ) {
        $owner_index[ $owner ] = array(
          'owner' => $owner,
          'alerts' => 0,
          'actions_open' => 0,
          'actions_closed' => 0,
          'overdue' => 0,
          'critical' => 0,
          'todo_now' => 0,
          'late_actions' => 0,
          'late_gaps' => 0,
          'closures_to_validate' => 0,
          'proof_to_secure' => 0,
          'arbitrations' => 0,
          'load_label' => 'Stable',
          'next_action' => 'Aucune action urgente',
          'delay_label' => 'Maîtrisé',
          'arbitration_label' => 'Aucun arbitrage',
        );
      }
      $status = mb_strtolower( (string) ( $row['status'] ?? '' ) );
      $is_closed = false !== strpos( $status, 'clos' ) || false !== strpos( $status, 'résolu' ) || false !== strpos( $status, 'resolu' ) || false !== strpos( $status, 'maîtris' ) || false !== strpos( $status, 'maitris' );
      $due_date = $this->get_quality_compliance_due_date_from_label( (string) ( $row['due_label'] ?? '' ) );
      if ( '' !== $due_date && $due_date < $today && ! $is_closed ) {
        $owner_index[ $owner ]['overdue']++;
        $owner_index[ $owner ]['late_gaps']++;
        $late_gaps[] = array(
          'type' => (string) ( $row['type'] ?? 'Qualité' ),
          'owner' => $owner,
          'title' => (string) ( $row['gap'] ?? 'Écart qualité' ),
          'status' => (string) ( $row['status'] ?? 'Ouvert' ),
          'due_label' => (string) ( $row['due_label'] ?? '—' ),
          'priority' => (string) ( $row['priority'] ?? 'Moyenne' ),
        );
      }
    }

    foreach ( (array) $queue_rows as $queue_row ) {
      $loop_row = $this->build_quality_compliance_operational_loop_row( $queue_row );
      $loop_rows[] = $loop_row;
      if ( 'Prise en charge' === $loop_row['intake_status'] ) {
        $loop_stats['intake']++;
      }
      if ( in_array( $loop_row['treatment_status'], array( 'Prise en charge', 'En traitement', 'Clôturée' ), true ) ) {
        $loop_stats['treatment']++;
      }
      if ( in_array( $loop_row['proof_stage'], array( 'Partielle', 'Consolidée' ), true ) ) {
        $loop_stats['proof']++;
      }
      if ( 'Clôturée' === $loop_row['closure_status'] ) {
        $loop_stats['closure']++;
      }
      if ( 'Réouverture possible' === $loop_row['reopen_status'] || 'Sous surveillance' === $loop_row['reopen_status'] ) {
        $loop_stats['reopen']++;
      }

      $owner = (string) ( $queue_row['owner'] ?? 'Pilotage qualité' );
      if ( ! isset( $owner_index[ $owner ] ) ) {
        $owner_index[ $owner ] = array(
          'owner' => $owner,
          'alerts' => 0,
          'actions_open' => 0,
          'actions_closed' => 0,
          'overdue' => 0,
          'critical' => 0,
          'todo_now' => 0,
          'late_actions' => 0,
          'late_gaps' => 0,
          'closures_to_validate' => 0,
          'proof_to_secure' => 0,
          'arbitrations' => 0,
          'load_label' => 'Stable',
          'next_action' => 'Aucune action urgente',
          'delay_label' => 'Maîtrisé',
          'arbitration_label' => 'Aucun arbitrage',
        );
      }
      $owner_index[ $owner ]['alerts']++;
      if ( false !== stripos( (string) ( $loop_row['proof_stage'] ?? '' ), 'À produire' ) || false !== stripos( (string) ( $loop_row['proof_stage'] ?? '' ), 'Partielle' ) ) {
        $owner_index[ $owner ]['proof_to_secure']++;
      }
      if ( false !== stripos( (string) ( $loop_row['closure_status'] ?? '' ), 'À valider' ) || false !== stripos( (string) ( $loop_row['closure_status'] ?? '' ), 'À clôturer' ) ) {
        $owner_index[ $owner ]['closures_to_validate']++;
      }
      if ( 'Haute' === (string) ( $queue_row['priority'] ?? '' ) ) {
        $owner_index[ $owner ]['critical']++;
      }
    }

    $todo_rows = array();
    $closure_validation_rows = array();
    foreach ( (array) $loop_rows as $loop_row ) {
      if ( ! is_array( $loop_row ) ) {
        continue;
      }
      $action_bundle = $this->get_quality_compliance_row_action_bundle( $loop_row, 'todo' );
      $action_label = (string) ( $action_bundle['primary_label'] ?? 'Suivre' );
      $action_url = (string) ( $action_bundle['primary_url'] ?? '' );
      $secondary_action_label = (string) ( $action_bundle['secondary_label'] ?? '' );
      $secondary_action_url = (string) ( $action_bundle['secondary_url'] ?? '' );
      $todo_rows[] = array(
        'priority' => (string) ( $loop_row['priority_auto'] ?? $loop_row['priority'] ?? 'Normale' ),
        'type' => false !== stripos( (string) ( $loop_row['closure_status'] ?? '' ), 'À valider' ) ? 'Clôture' : (string) ( $loop_row['alert_type_label'] ?? ( false !== stripos( (string) ( $loop_row['intake_status'] ?? '' ), 'À prendre' ) ? 'Alerte' : 'Action' ) ),
        'scenario' => (string) ( $loop_row['source'] ?? 'Enquête' ) . ' · ' . (string) ( $loop_row['alert_type_label'] ?? 'Alerte qualité' ),
        'owner' => (string) ( $loop_row['owner'] ?? 'Pilotage qualité' ),
        'due_label' => (string) ( $loop_row['due_label'] ?? '—' ),
        'loop_status' => trim( (string) ( $loop_row['intake_status'] ?? '' ) . ' · ' . (string) ( $loop_row['treatment_status'] ?? '' ) ),
        'action_label' => $action_label,
        'action_url' => $action_url,
        'secondary_action_label' => $secondary_action_label,
        'secondary_action_url' => $secondary_action_url,
      );
      $owner = (string) ( $loop_row['owner'] ?? 'Pilotage qualité' );
      if ( ! isset( $owner_index[ $owner ] ) ) {
        $owner_index[ $owner ] = array(
          'owner' => $owner,
          'alerts' => 0,
          'actions_open' => 0,
          'actions_closed' => 0,
          'overdue' => 0,
          'critical' => 0,
          'todo_now' => 0,
          'late_actions' => 0,
          'late_gaps' => 0,
          'closures_to_validate' => 0,
          'proof_to_secure' => 0,
          'arbitrations' => 0,
          'load_label' => 'Stable',
          'next_action' => 'Aucune action urgente',
          'delay_label' => 'Maîtrisé',
          'arbitration_label' => 'Aucun arbitrage',
        );
      }
      $owner_index[ $owner ]['todo_now']++;
      if ( false !== stripos( (string) ( $loop_row['closure_status'] ?? '' ), 'À valider' ) || false !== stripos( (string) ( $loop_row['closure_status'] ?? '' ), 'À clôturer' ) || false !== stripos( (string) ( $loop_row['proof_stage'] ?? '' ), 'Partielle' ) || false !== stripos( (string) ( $loop_row['proof_stage'] ?? '' ), 'À produire' ) ) {
        $owner_index[ $owner ]['arbitrations']++;
      }
      if ( false !== stripos( (string) ( $loop_row['closure_status'] ?? '' ), 'À valider' ) || false !== stripos( (string) ( $loop_row['closure_status'] ?? '' ), 'À clôturer' ) ) {
        $reopen_status = (string) ( $loop_row['reopen_status'] ?? 'Sous surveillance' );
        $proof_stage   = (string) ( $loop_row['proof_stage'] ?? 'Consolidée' );
        $validation_recommendation = 'Valider';
        $validation_detail = 'Preuve consolidée et traitement terminé.';
        if ( false !== stripos( $reopen_status, 'surveillance' ) ) {
          $validation_recommendation = 'Valider avec surveillance';
          $validation_detail = 'Clôture possible, mais une surveillance post-clôture reste recommandée.';
        }
        if ( false !== stripos( $proof_stage, 'partielle' ) || false !== stripos( $proof_stage, 'produire' ) ) {
          $validation_recommendation = 'Réouvrir';
          $validation_detail = 'Preuve insuffisante ou incomplète : rouvrir le traitement avant validation.';
        }
        $closure_validation_rows[] = array(
          'source' => (string) ( $loop_row['source'] ?? 'Enquête' ),
          'respondent' => (string) ( $loop_row['respondent'] ?? 'Session complète' ),
          'owner' => (string) ( $loop_row['owner'] ?? 'Pilotage qualité' ),
          'proof_stage' => $proof_stage,
          'closure_status' => (string) ( $loop_row['closure_status'] ?? 'À valider' ),
          'reopen_status' => $reopen_status,
          'due_label' => (string) ( $loop_row['due_label'] ?? '—' ),
          'recommendation' => $validation_recommendation,
          'recommendation_detail' => $validation_detail,
          'validate_url' => (string) ( $loop_row['validate_closure_url'] ?? '' ),
          'reject_url' => (string) ( $loop_row['reject_closure_url'] ?? '' ),
        );
      }
    }

    $reminder_rows = $this->get_quality_compliance_internal_reminder_rows( $loop_rows, $today );
    foreach ( (array) $audit_log_rows as $row ) {
      if ( ! is_array( $row ) ) {
        continue;
      }
      $traceability_rows[] = array(
        'date' => (string) ( $row['date'] ?? '' ),
        'scope' => (string) ( $row['scope'] ?? 'Qualité' ),
        'event' => (string) ( $row['event'] ?? 'Événement' ),
        'status' => (string) ( $row['status'] ?? 'Enregistré' ),
        'detail' => (string) ( $row['detail'] ?? '' ),
      );
    }
    foreach ( $reminder_rows as $reminder_row ) {
      $traceability_rows[] = array(
        'date' => (string) ( $reminder_row['date'] ?? current_time( 'mysql' ) ),
        'scope' => 'Relances internes',
        'event' => (string) ( $reminder_row['event'] ?? 'Relance interne' ),
        'status' => (string) ( $reminder_row['status'] ?? 'Relance interne' ),
        'detail' => trim( (string) ( $reminder_row['owner'] ?? 'Pilotage qualité' ) . ' · ' . (string) ( $reminder_row['reason'] ?? '' ) ),
      );
    }

    usort( $late_actions, static function( $a, $b ) use ( $priority_order ) {
      $pa = $priority_order[ $a['priority'] ] ?? 9;
      $pb = $priority_order[ $b['priority'] ] ?? 9;
      return $pa <=> $pb;
    } );
    usort( $late_gaps, static function( $a, $b ) use ( $priority_order ) {
      $pa = $priority_order[ $a['priority'] ] ?? 9;
      $pb = $priority_order[ $b['priority'] ] ?? 9;
      return $pa <=> $pb;
    } );
    usort( $closure_rows, static function( $a, $b ) {
      return strcasecmp( (string) $a['owner'], (string) $b['owner'] );
    } );
    usort( $traceability_rows, static function( $a, $b ) {
      return strcmp( (string) $b['date'], (string) $a['date'] );
    } );

    usort( $todo_rows, static function( $a, $b ) use ( $priority_order ) {
      $ha = isset( $a['hierarchy_order'] ) ? (int) $a['hierarchy_order'] : 9;
      $hb = isset( $b['hierarchy_order'] ) ? (int) $b['hierarchy_order'] : 9;
      if ( $ha === $hb ) {
        $pa = $priority_order[ $a['priority'] ?? 'Normale' ] ?? 9;
        $pb = $priority_order[ $b['priority'] ?? 'Normale' ] ?? 9;
        if ( $pa === $pb ) {
          return strcasecmp( (string) ( $a['owner'] ?? '' ), (string) ( $b['owner'] ?? '' ) );
        }
        return $pa <=> $pb;
      }
      return $ha <=> $hb;
    } );

    $owner_rows = array_values( $owner_index );
    foreach ( $owner_rows as $owner_index_key => $owner_row ) {
      $owner_rows[ $owner_index_key ]['load_label'] = $this->get_quality_compliance_owner_load_label( $owner_row );
      $owner_rows[ $owner_index_key ]['delay_label'] = $this->get_quality_compliance_owner_delay_label( $owner_row );
      $owner_rows[ $owner_index_key ]['arbitration_label'] = $this->get_quality_compliance_owner_arbitration_label( $owner_row );
      $owner_rows[ $owner_index_key ]['next_action'] = $this->get_quality_compliance_owner_next_action_label( $owner_row );
      $owner_rows[ $owner_index_key ]['arbitration_focus_meta'] = $this->get_quality_compliance_owner_arbitration_focus( $owner_row );
      $owner_rows[ $owner_index_key ]['arbitration_focus'] = (string) ( $owner_rows[ $owner_index_key ]['arbitration_focus_meta']['focus'] ?? 'Aucun arbitrage' );
      $owner_rows[ $owner_index_key ]['arbitration_detail'] = (string) ( $owner_rows[ $owner_index_key ]['arbitration_focus_meta']['detail'] ?? '' );
      $owner_rows[ $owner_index_key ]['arbitration_action'] = (string) ( $owner_rows[ $owner_index_key ]['arbitration_focus_meta']['action'] ?? 'Aucun arbitrage' );
      $owner_rows[ $owner_index_key ]['arbitration_rank'] = (int) ( $owner_rows[ $owner_index_key ]['arbitration_focus_meta']['rank'] ?? 99 );
    }
    usort( $owner_rows, static function( $a, $b ) {
      $ra = (int) ( $a['arbitration_rank'] ?? 99 );
      $rb = (int) ( $b['arbitration_rank'] ?? 99 );
      if ( $ra === $rb ) {
        if ( $a['critical'] === $b['critical'] ) {
          if ( $a['overdue'] === $b['overdue'] ) {
            if ( $a['arbitrations'] === $b['arbitrations'] ) {
              return strcasecmp( (string) $a['owner'], (string) $b['owner'] );
            }
            return $b['arbitrations'] <=> $a['arbitrations'];
          }
          return $b['overdue'] <=> $a['overdue'];
        }
        return $b['critical'] <=> $a['critical'];
      }
      return $ra <=> $rb;
    } );

    $arbitration_rows = array_values( array_filter( $owner_rows, static function( $owner_row ) {
      return ! empty( $owner_row['arbitration_focus'] ) && 'Aucun arbitrage' !== (string) $owner_row['arbitration_focus'];
    } ) );
    usort( $arbitration_rows, static function( $a, $b ) {
      $ra = (int) ( $a['arbitration_rank'] ?? 99 );
      $rb = (int) ( $b['arbitration_rank'] ?? 99 );
      if ( $ra === $rb ) {
        if ( (int) ( $a['critical'] ?? 0 ) === (int) ( $b['critical'] ?? 0 ) ) {
          if ( (int) ( $a['overdue'] ?? 0 ) === (int) ( $b['overdue'] ?? 0 ) ) {
            if ( (int) ( $a['arbitrations'] ?? 0 ) === (int) ( $b['arbitrations'] ?? 0 ) ) {
              return strcasecmp( (string) ( $a['owner'] ?? '' ), (string) ( $b['owner'] ?? '' ) );
            }
            return (int) ( $b['arbitrations'] ?? 0 ) <=> (int) ( $a['arbitrations'] ?? 0 );
          }
          return (int) ( $b['overdue'] ?? 0 ) <=> (int) ( $a['overdue'] ?? 0 );
        }
        return (int) ( $b['critical'] ?? 0 ) <=> (int) ( $a['critical'] ?? 0 );
      }
      return $ra <=> $rb;
    } );

    return array(
      'queue_rows' => array_slice( $queue_rows, 0, 12 ),
      'owner_rows' => $owner_rows,
      'arbitration_rows' => array_slice( $arbitration_rows, 0, 10 ),
      'late_gap_rows' => array_slice( $late_gaps, 0, 10 ),
      'late_action_rows' => array_slice( $late_actions, 0, 10 ),
      'closure_rows' => array_slice( $closure_rows, 0, 10 ),
      'traceability_rows' => array_slice( $traceability_rows, 0, 12 ),
      'loop_rows' => array_slice( $loop_rows, 0, 12 ),
      'todo_rows' => array_slice( $todo_rows, 0, 12 ),
      'reminder_rows' => array_slice( $reminder_rows, 0, 12 ),
      'closure_validation_rows' => array_slice( $closure_validation_rows, 0, 10 ),
      'loop_stats' => $loop_stats,
      'stats' => array(
        'queue_total' => count( $queue_rows ),
        'owners' => count( $owner_rows ),
        'late_gaps' => count( $late_gaps ),
        'late_actions' => count( $late_actions ),
        'closed_actions' => count( $closure_rows ),
        'traceability' => count( $traceability_rows ),
        'loop_intake' => (int) $loop_stats['intake'],
        'loop_treatment' => (int) $loop_stats['treatment'],
        'loop_proof' => (int) $loop_stats['proof'],
        'loop_closure' => (int) $loop_stats['closure'],
        'loop_reopen' => (int) $loop_stats['reopen'],
        'closures_to_reopen' => count( array_filter( $closure_validation_rows, static function( $row ) { return isset( $row['recommendation'] ) && 'Réouvrir' === $row['recommendation']; } ) ),
        'closures_with_surveillance' => count( array_filter( $closure_validation_rows, static function( $row ) { return isset( $row['recommendation'] ) && 'Valider avec surveillance' === $row['recommendation']; } ) ),
        'todo_now' => count( $todo_rows ),
        'internal_reminders' => count( $reminder_rows ),
        'closures_to_validate' => count( $closure_validation_rows ),
        'arbitrations' => count( $arbitration_rows ),
      ),
    );
  }

  private function get_quality_compliance_audit_log_rows( $foundation_data ) {
    $overall  = ! empty( $foundation_data['overall'] ) && is_array( $foundation_data['overall'] ) ? $foundation_data['overall'] : array();
    $gap_rows = $this->get_quality_compliance_gap_register_rows( $foundation_data );
    $plan_rows = $this->get_quality_compliance_full_action_plan_rows( $foundation_data );

    $alert_bridge_rows = $this->get_quality_alert_bridge_rows();
    $alert_bridge_stats = $this->get_quality_alert_bridge_stats( $alert_bridge_rows );

    $rows = array(
      array(
        'date'   => current_time( 'mysql' ),
        'scope'  => 'Qualité & conformité',
        'event'  => 'Consolidation automatique des indicateurs',
        'detail' => sprintf( '%d types suivis, %d réponses, %d actions ouvertes.', (int) ( $overall['survey_types'] ?? 0 ), (int) ( $overall['responses'] ?? 0 ), (int) ( $overall['actions_to_process'] ?? 0 ) ),
        'status' => 'Consolidé',
      ),
      array(
        'date'   => current_time( 'mysql' ),
        'scope'  => 'Enquêtes → alertes',
        'event'  => 'Détection transversale des signaux faibles',
        'detail' => sprintf( '%d alerte(s) consolidée(s), dont %d en niveau élevé, sur %d type(s) d’enquêtes.', (int) ( $alert_bridge_stats['total'] ?? 0 ), (int) ( $alert_bridge_stats['high'] ?? 0 ), (int) ( $alert_bridge_stats['types'] ?? 0 ) ),
        'status' => ! empty( $alert_bridge_stats['total'] ) ? 'À traiter' : 'Maîtrisé',
      ),
      array(
        'date'   => current_time( 'mysql' ),
        'scope'  => 'Alertes → écarts',
        'event'  => 'Transformation des alertes en registre d’écarts',
        'detail' => sprintf( '%d écart(s) automatiques issus des alertes sont visibles dans le registre.', (int) ( $alert_bridge_stats['total'] ?? 0 ) ),
        'status' => ! empty( $alert_bridge_stats['total'] ) ? 'En cours' : 'Maîtrisé',
      ),
      array(
        'date'   => current_time( 'mysql' ),
        'scope'  => 'Écarts → actions',
        'event'  => 'Préparation du plan d’action qualité',
        'detail' => sprintf( '%d action(s) potentielles liées aux alertes sont proposées dans le plan.', (int) ( $alert_bridge_stats['total'] ?? 0 ) ),
        'status' => ! empty( $alert_bridge_stats['total'] ) ? 'À ouvrir' : 'Sous contrôle',
      ),
      array(
        'date'   => current_time( 'mysql' ),
        'scope'  => 'Registre des écarts',
        'event'  => 'Mise à jour des écarts potentiels',
        'detail' => sprintf( '%d écarts suivis dans le registre.', count( $gap_rows ) ),
        'status' => count( $gap_rows ) > 0 ? 'À surveiller' : 'Maîtrisé',
      ),
      array(
        'date'   => current_time( 'mysql' ),
        'scope'  => 'Plan d’action',
        'event'  => 'Synchronisation des actions qualité',
        'detail' => sprintf( '%d lignes de plan d’action consolidées.', count( $plan_rows ) ),
        'status' => 'En cours',
      ),
    );

    foreach ( $alert_bridge_rows as $alert_row ) {
      if ( ! is_array( $alert_row ) ) {
        continue;
      }
      $rows[] = array(
        'date'   => current_time( 'mysql' ),
        'scope'  => 'Scénario automatique',
        'event'  => (string) ( $alert_row['scenario_label'] ?? 'Chaînage qualité' ),
        'detail' => sprintf(
          '%s · %s · %s',
          (string) ( $alert_row['source_label'] ?? 'Enquête' ),
          (string) ( $alert_row['participant_label'] ?? 'Session complète' ),
          (string) ( $alert_row['journal_detail'] ?? 'Alerte détectée puis journalisée.' )
        ),
        'status' => (string) ( $alert_row['action_status'] ?? 'En cours' ),
      );
    }

    return array_merge( $this->get_quality_audit_log_records(), $rows );
  }



  private function get_quality_loop_records_option_key() {
    return 'acdc_of_quality_loop_records';
  }

  private function get_quality_loop_records() {
    $rows = get_option( $this->get_quality_loop_records_option_key(), array() );
    return is_array( $rows ) ? array_values( $rows ) : array();
  }

  private function save_quality_loop_records( $rows ) {
    update_option( $this->get_quality_loop_records_option_key(), array_values( is_array( $rows ) ? $rows : array() ), false );
  }

  private function get_quality_loop_record_by_alert_key( $alert_key ) {
    $alert_key = sanitize_key( (string) $alert_key );
    if ( '' === $alert_key ) {
      return null;
    }
    foreach ( $this->get_quality_loop_records() as $row ) {
      if ( isset( $row['alert_key'] ) && sanitize_key( (string) $row['alert_key'] ) === $alert_key ) {
        return is_array( $row ) ? $row : null;
      }
    }
    return null;
  }

  private function save_quality_loop_record( $record ) {
    if ( ! is_array( $record ) ) {
      return;
    }
    $alert_key = isset( $record['alert_key'] ) ? sanitize_key( (string) $record['alert_key'] ) : '';
    if ( '' === $alert_key ) {
      return;
    }
    $record['alert_key'] = $alert_key;
    $rows = $this->get_quality_loop_records();
    $saved = false;
    foreach ( $rows as $index => $row ) {
      if ( isset( $row['alert_key'] ) && sanitize_key( (string) $row['alert_key'] ) === $alert_key ) {
        $rows[ $index ] = $record;
        $saved = true;
        break;
      }
    }
    if ( ! $saved ) {
      $rows[] = $record;
    }
    $this->save_quality_loop_records( $rows );
  }

  private function get_quality_current_actor_label() {
    $user = wp_get_current_user();
    if ( $user instanceof WP_User ) {
      if ( '' !== trim( (string) $user->display_name ) ) {
        return (string) $user->display_name;
      }
      if ( '' !== trim( (string) $user->user_login ) ) {
        return (string) $user->user_login;
      }
    }
    return 'Pilotage qualité';
  }

  private function get_quality_alert_bridge_row_by_key( $alert_key ) {
    $alert_key = sanitize_key( (string) $alert_key );
    if ( '' === $alert_key ) {
      return null;
    }
    foreach ( $this->get_quality_alert_bridge_rows() as $row ) {
      if ( isset( $row['alert_key'] ) && sanitize_key( (string) $row['alert_key'] ) === $alert_key ) {
        return is_array( $row ) ? $row : null;
      }
    }
    return null;
  }

  private function apply_quality_loop_overrides_to_alert_row( $row ) {
    if ( ! is_array( $row ) || empty( $row['alert_key'] ) ) {
      return $row;
    }
    $record = $this->get_quality_loop_record_by_alert_key( (string) $row['alert_key'] );
    if ( empty( $record ) || ! is_array( $record ) ) {
      return $row;
    }

    if ( ! empty( $record['owner'] ) ) {
      $row['assigned_owner'] = (string) $record['owner'];
    }
    if ( ! empty( $record['priority'] ) ) {
      $row['priority'] = (string) $record['priority'];
    }
    if ( ! empty( $record['due_label'] ) ) {
      $row['gap_due_label'] = (string) $record['due_label'];
      $row['action_due_label'] = (string) $record['due_label'];
    }
    if ( ! empty( $record['gap_status'] ) ) {
      $row['gap_status'] = (string) $record['gap_status'];
      $row['status_label'] = (string) $record['gap_status'];
    }
    if ( ! empty( $record['action_status'] ) ) {
      $row['action_status'] = (string) $record['action_status'];
    }
    if ( ! empty( $record['proof_status'] ) ) {
      $row['proof_status'] = (string) $record['proof_status'];
    }
    if ( ! empty( $record['intake_status'] ) ) {
      $row['intake_status'] = (string) $record['intake_status'];
    }
    if ( ! empty( $record['treatment_status'] ) ) {
      $row['treatment_status'] = (string) $record['treatment_status'];
    }
    if ( ! empty( $record['proof_stage'] ) ) {
      $row['proof_stage'] = (string) $record['proof_stage'];
    }
    if ( ! empty( $record['closure_status'] ) ) {
      $row['closure_status'] = (string) $record['closure_status'];
    }
    if ( ! empty( $record['reopen_status'] ) ) {
      $row['reopen_status'] = (string) $record['reopen_status'];
    }
    if ( ! empty( $record['gap_record_id'] ) ) {
      $row['gap_record_id'] = (string) $record['gap_record_id'];
    }
    if ( ! empty( $record['action_record_id'] ) ) {
      $row['action_record_id'] = (string) $record['action_record_id'];
    }

    return $row;
  }

  private function get_quality_loop_action_url( $alert_key, $transition, $force_context = 'auto' ) {
    $alert_key = sanitize_key( (string) $alert_key );
    $transition = sanitize_key( (string) $transition );
    if ( '' === $alert_key || '' === $transition ) {
      return '';
    }
    $action = 'acdc_handle_quality_loop_transition';
    $args = array(
      'action' => $action,
      'alert_key' => $alert_key,
      'transition' => $transition,
      'qc_context' => in_array( $force_context, array( 'front', 'admin' ), true ) ? $force_context : ( is_admin() ? 'admin' : 'front' ),
      '_wpnonce' => wp_create_nonce( $action . '_' . $alert_key . '_' . $transition ),
    );
    return add_query_arg( $args, admin_url( 'admin-post.php' ) );
  }

  private function ensure_quality_gap_record_from_alert( $alert_row, $owner = '' ) {
    if ( ! is_array( $alert_row ) ) {
      return '';
    }
    $loop_record = $this->get_quality_loop_record_by_alert_key( (string) ( $alert_row['alert_key'] ?? '' ) );
    $linked_id = isset( $loop_record['gap_record_id'] ) ? sanitize_text_field( (string) $loop_record['gap_record_id'] ) : '';
    $records = $this->get_quality_gap_records();
    if ( '' !== $linked_id ) {
      foreach ( $records as $index => $record ) {
        if ( isset( $record['id'] ) && (string) $record['id'] === $linked_id ) {
          $records[ $index ]['owner'] = '' !== $owner ? $owner : ( $records[ $index ]['owner'] ?? 'Pilotage qualité' );
          $records[ $index ]['status'] = $records[ $index ]['status'] ?? 'Prise en charge';
          $this->save_quality_gap_records( $records );
          return $linked_id;
        }
      }
    }
    $new_id = $this->generate_quality_record_id( 'QC-E', $records );
    $records[] = array(
      'id' => $new_id,
      'type' => (string) ( $alert_row['source_label'] ?? 'Enquête' ),
      'gap' => (string) ( $alert_row['gap_title'] ?? 'Alerte qualité à traiter' ),
      'severity' => (string) ( $alert_row['severity'] ?? 'Moyen' ),
      'status' => 'Prise en charge',
      'priority' => (string) ( $alert_row['priority'] ?? 'Moyenne' ),
      'owner' => '' !== $owner ? $owner : (string) ( $alert_row['assigned_owner'] ?? 'Pilotage qualité' ),
      'due_label' => (string) ( $alert_row['gap_due_label'] ?? 'J+7' ),
      'next_step' => (string) ( $alert_row['gap_next_step'] ?? 'Qualifier puis traiter.' ),
      'impact' => (string) ( $alert_row['gap_impact'] ?? 'Impact à confirmer.' ),
      'source_proof' => 'Console front · ' . (string) ( $alert_row['session_title'] ?? '' ) . ( ! empty( $alert_row['participant_label'] ) ? ' · ' . (string) $alert_row['participant_label'] : '' ),
    );
    $this->save_quality_gap_records( $records );
    return $new_id;
  }

  private function ensure_quality_action_record_from_alert( $alert_row, $owner = '' ) {
    if ( ! is_array( $alert_row ) ) {
      return '';
    }
    $loop_record = $this->get_quality_loop_record_by_alert_key( (string) ( $alert_row['alert_key'] ?? '' ) );
    $linked_id = isset( $loop_record['action_record_id'] ) ? sanitize_text_field( (string) $loop_record['action_record_id'] ) : '';
    $records = $this->get_quality_action_records();
    if ( '' !== $linked_id ) {
      foreach ( $records as $index => $record ) {
        if ( isset( $record['id'] ) && (string) $record['id'] === $linked_id ) {
          $records[ $index ]['owner'] = '' !== $owner ? $owner : ( $records[ $index ]['owner'] ?? 'Pilotage qualité' );
          $records[ $index ]['status'] = 'En traitement';
          $this->save_quality_action_records( $records );
          return $linked_id;
        }
      }
    }
    $new_id = $this->generate_quality_record_id( 'QC-A', $records );
    $records[] = array(
      'id' => $new_id,
      'type' => (string) ( $alert_row['source_label'] ?? 'Enquête' ),
      'objective' => (string) ( $alert_row['action_objective'] ?? 'Traiter le signal faible détecté' ),
      'priority' => (string) ( $alert_row['priority'] ?? 'Moyenne' ),
      'status' => 'En traitement',
      'owner' => '' !== $owner ? $owner : (string) ( $alert_row['assigned_owner'] ?? 'Pilotage qualité' ),
      'due_label' => (string) ( $alert_row['action_due_label'] ?? 'J+7' ),
      'success' => 'Traitement mené, preuve déposée et clôture tracée.',
      'proof_status' => 'À produire',
      'actions_open' => 1,
      'evidence' => 'Console front · ' . (string) ( $alert_row['action_evidence'] ?? '' ),
    );
    $this->save_quality_action_records( $records );
    return $new_id;
  }

  public function handle_quality_loop_transition() {
    $this->require_quality_permission( 'manage', 'Accès refusé au traitement qualité direct.' );
    $alert_key = isset( $_GET['alert_key'] ) ? sanitize_key( wp_unslash( $_GET['alert_key'] ) ) : '';
    $transition = isset( $_GET['transition'] ) ? sanitize_key( wp_unslash( $_GET['transition'] ) ) : '';
    check_admin_referer( 'acdc_handle_quality_loop_transition_' . $alert_key . '_' . $transition );

    $alert_row = $this->get_quality_alert_bridge_row_by_key( $alert_key );
    if ( empty( $alert_row ) || ! is_array( $alert_row ) ) {
      wp_safe_redirect( $this->get_quality_redirect_url_from_request( 'quality_loop_missing_alert' ) );
      exit;
    }

    $owner = $this->get_quality_current_actor_label();
    $record = $this->get_quality_loop_record_by_alert_key( $alert_key );
    if ( empty( $record ) || ! is_array( $record ) ) {
      $record = array(
        'alert_key' => $alert_key,
        'owner' => $owner,
        'priority' => (string) ( $alert_row['priority'] ?? 'Moyenne' ),
        'due_label' => (string) ( $alert_row['action_due_label'] ?? $alert_row['gap_due_label'] ?? 'J+7' ),
        'gap_status' => (string) ( $alert_row['gap_status'] ?? 'À qualifier' ),
        'action_status' => (string) ( $alert_row['action_status'] ?? 'À ouvrir' ),
        'proof_status' => (string) ( $alert_row['proof_status'] ?? 'Alerte détectée' ),
      );
    }
    $record['owner'] = $owner;

    $event = 'Transition qualité';
    $detail = 'Mise à jour directe depuis la console front.';
    $status = 'Traçable';

    if ( 'take_in_charge' === $transition ) {
      $record['gap_record_id'] = $this->ensure_quality_gap_record_from_alert( $alert_row, $owner );
      $record['intake_status'] = 'Prise en charge';
      $record['treatment_status'] = 'Prise en charge';
      $record['proof_stage'] = 'À produire';
      $record['closure_status'] = 'Ouverte';
      $record['reopen_status'] = 'Non';
      $record['gap_status'] = 'Prise en charge';
      $record['action_status'] = 'À ouvrir';
      $record['proof_status'] = 'À produire';
      $event = 'Prise en charge qualité';
      $detail = 'Alerte ' . $alert_key . ' prise en charge depuis la console front.';
    } elseif ( 'open_treatment' === $transition ) {
      $record['gap_record_id'] = $this->ensure_quality_gap_record_from_alert( $alert_row, $owner );
      $record['action_record_id'] = $this->ensure_quality_action_record_from_alert( $alert_row, $owner );
      $record['intake_status'] = 'Prise en charge';
      $record['treatment_status'] = 'En traitement';
      $record['proof_stage'] = 'À produire';
      $record['closure_status'] = 'Ouverte';
      $record['reopen_status'] = 'Non';
      $record['gap_status'] = 'En traitement';
      $record['action_status'] = 'En traitement';
      $record['proof_status'] = 'À produire';
      $event = 'Ouverture du traitement';
      $detail = 'Traitement ouvert pour l’alerte ' . $alert_key . ' depuis la console front.';
    } elseif ( 'deposit_proof' === $transition ) {
      $record['action_record_id'] = $this->ensure_quality_action_record_from_alert( $alert_row, $owner );
      $record['intake_status'] = $record['intake_status'] ?? 'Prise en charge';
      $record['treatment_status'] = 'En traitement';
      $record['proof_stage'] = 'Partielle';
      $record['closure_status'] = 'Ouverte';
      $record['reopen_status'] = 'Non';
      $record['gap_status'] = $record['gap_status'] ?? 'En traitement';
      $record['action_status'] = 'En traitement';
      $record['proof_status'] = 'Preuve déposée';
      $event = 'Dépôt de preuve';
      $detail = 'Preuve déposée pour l’alerte ' . $alert_key . ' depuis la console front.';
      $action_records = $this->get_quality_action_records();
      foreach ( $action_records as $index => $action_record ) {
        if ( isset( $record['action_record_id'], $action_record['id'] ) && (string) $action_record['id'] === (string) $record['action_record_id'] ) {
          $action_records[ $index ]['proof_status'] = 'Preuve déposée';
          $action_records[ $index ]['evidence'] = trim( (string) ( $action_records[ $index ]['evidence'] ?? '' ) . ' | Preuve déposée depuis la console front le ' . current_time( 'mysql' ) );
          break;
        }
      }
      $this->save_quality_action_records( $action_records );
    } elseif ( 'close' === $transition ) {
      $record['gap_record_id'] = $this->ensure_quality_gap_record_from_alert( $alert_row, $owner );
      $record['action_record_id'] = $this->ensure_quality_action_record_from_alert( $alert_row, $owner );
      $record['intake_status'] = 'Prise en charge';
      $record['treatment_status'] = 'Traitement terminé';
      $record['proof_stage'] = 'Consolidée';
      $record['closure_status'] = 'À valider';
      $record['reopen_status'] = 'Sous surveillance';
      $record['gap_status'] = 'À valider';
      $record['action_status'] = 'À valider';
      $record['proof_status'] = 'Preuve consolidée';
      $event = 'Demande de clôture';
      $detail = 'Clôture proposée pour validation sur l’alerte ' . $alert_key . ' depuis la console front.';
      $gap_records = $this->get_quality_gap_records();
      foreach ( $gap_records as $index => $gap_record ) {
        if ( isset( $record['gap_record_id'], $gap_record['id'] ) && (string) $gap_record['id'] === (string) $record['gap_record_id'] ) {
          $gap_records[ $index ]['status'] = 'À valider';
          break;
        }
      }
      $this->save_quality_gap_records( $gap_records );
      $action_records = $this->get_quality_action_records();
      foreach ( $action_records as $index => $action_record ) {
        if ( isset( $record['action_record_id'], $action_record['id'] ) && (string) $action_record['id'] === (string) $record['action_record_id'] ) {
          $action_records[ $index ]['status'] = 'À valider';
          $action_records[ $index ]['proof_status'] = 'Preuve consolidée';
          break;
        }
      }
      $this->save_quality_action_records( $action_records );
    } elseif ( 'validate_closure' === $transition ) {
      $record['intake_status'] = 'Prise en charge';
      $record['treatment_status'] = 'Clôturée';
      $record['proof_stage'] = 'Consolidée';
      $record['closure_status'] = 'Clôturée';
      $record['reopen_status'] = 'Stable';
      $record['gap_status'] = 'Clôturée';
      $record['action_status'] = 'Clôturée';
      $record['proof_status'] = 'Preuve consolidée';
      $event = 'Validation de clôture';
      $detail = 'Clôture validée pour l’alerte ' . $alert_key . ' depuis la console front.';
      $gap_records = $this->get_quality_gap_records();
      foreach ( $gap_records as $index => $gap_record ) {
        if ( isset( $record['gap_record_id'], $gap_record['id'] ) && (string) $gap_record['id'] === (string) $record['gap_record_id'] ) {
          $gap_records[ $index ]['status'] = 'Clôturée';
          break;
        }
      }
      $this->save_quality_gap_records( $gap_records );
      $action_records = $this->get_quality_action_records();
      foreach ( $action_records as $index => $action_record ) {
        if ( isset( $record['action_record_id'], $action_record['id'] ) && (string) $action_record['id'] === (string) $record['action_record_id'] ) {
          $action_records[ $index ]['status'] = 'Clôturée';
          $action_records[ $index ]['proof_status'] = 'Preuve consolidée';
          break;
        }
      }
      $this->save_quality_action_records( $action_records );
    } elseif ( 'reject_closure' === $transition ) {
      $record['intake_status'] = 'Prise en charge';
      $record['treatment_status'] = 'En traitement';
      $record['proof_stage'] = 'À produire';
      $record['closure_status'] = 'Refusée';
      $record['reopen_status'] = 'Réouverture possible';
      $record['gap_status'] = 'Réouverte';
      $record['action_status'] = 'Réouverte';
      $record['proof_status'] = 'À compléter';
      $event = 'Refus de clôture';
      $detail = 'Clôture refusée et boucle réouverte pour l’alerte ' . $alert_key . ' depuis la console front.';
      $gap_records = $this->get_quality_gap_records();
      foreach ( $gap_records as $index => $gap_record ) {
        if ( isset( $record['gap_record_id'], $gap_record['id'] ) && (string) $gap_record['id'] === (string) $record['gap_record_id'] ) {
          $gap_records[ $index ]['status'] = 'Réouverte';
          break;
        }
      }
      $this->save_quality_gap_records( $gap_records );
      $action_records = $this->get_quality_action_records();
      foreach ( $action_records as $index => $action_record ) {
        if ( isset( $record['action_record_id'], $action_record['id'] ) && (string) $action_record['id'] === (string) $record['action_record_id'] ) {
          $action_records[ $index ]['status'] = 'Réouverte';
          $action_records[ $index ]['proof_status'] = 'À compléter';
          break;
        }
      }
      $this->save_quality_action_records( $action_records );
    } elseif ( 'reopen' === $transition ) {
      $record['gap_record_id'] = $this->ensure_quality_gap_record_from_alert( $alert_row, $owner );
      $record['action_record_id'] = $this->ensure_quality_action_record_from_alert( $alert_row, $owner );
      $record['intake_status'] = 'Prise en charge';
      $record['treatment_status'] = 'Prise en charge';
      $record['proof_stage'] = 'À produire';
      $record['closure_status'] = 'Ouverte';
      $record['reopen_status'] = 'Réouverture possible';
      $record['gap_status'] = 'Réouverte';
      $record['action_status'] = 'Réouverte';
      $record['proof_status'] = 'À compléter';
      $event = 'Réouverture qualité';
      $detail = 'Boucle rouverte pour l’alerte ' . $alert_key . ' depuis la console front.';
    }

    $this->save_quality_loop_record( $record );
    $this->append_quality_audit_log_event( 'Boucle de traitement', $event, $detail, $status );
    wp_safe_redirect( $this->get_quality_redirect_url_from_request( 'quality_loop_updated' ) );
    exit;
  }

  private function get_quality_gap_records_option_key() {
    return 'acdc_of_quality_gap_records';
  }

  private function get_quality_action_records_option_key() {
    return 'acdc_of_quality_action_records';
  }


  private function get_quality_audit_log_records_option_key() {
    return 'acdc_of_quality_audit_log_records';
  }

  private function get_quality_audit_log_records() {
    $rows = get_option( $this->get_quality_audit_log_records_option_key(), array() );
    return is_array( $rows ) ? $rows : array();
  }

  private function save_quality_audit_log_records( $rows ) {
    update_option( $this->get_quality_audit_log_records_option_key(), array_values( is_array( $rows ) ? $rows : array() ), false );
  }

  private function append_quality_audit_log_event( $scope, $event, $detail, $status = 'Enregistré' ) {
    /* ACDC 3.25.299 — LE PONT VERS LE JOURNAL QUE L'EXPLOITANT PEUT OUVRIR.
       Sept journaux coexistaient dans ce plugin ; l'écran « Journal des actions »
       n'en montrait qu'un. La traçabilité existait donc en morceaux, et l'écran
       qui prétendait la donner en montrait un septième — sans le dire. Chaque
       journal garde son usage propre ; il verse en plus au journal commun. */
    if ( method_exists( $this, 'log_action_event' ) ) {
      $this->log_action_event( 'qualite_' . sanitize_key( (string) $scope ), 'quality', 0, 'success', array( 'evenement' => $event, 'detail' => $detail, 'etat' => $status ) );
    }

    $rows = $this->get_quality_audit_log_records();
    array_unshift( $rows, array(
      'date'   => current_time( 'mysql' ),
      'scope'  => sanitize_text_field( (string) $scope ),
      'event'  => sanitize_text_field( (string) $event ),
      'detail' => sanitize_textarea_field( (string) $detail ),
      'status' => sanitize_text_field( (string) $status ),
    ) );
    if ( count( $rows ) > 150 ) {
      $rows = array_slice( $rows, 0, 150 );
    }
    $this->save_quality_audit_log_records( $rows );
  }

  private function get_quality_compliance_filters_from_request() {
    return array(
      'type' => isset( $_GET['qc_filter_type'] ) ? sanitize_text_field( wp_unslash( $_GET['qc_filter_type'] ) ) : '',
      'status' => isset( $_GET['qc_filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['qc_filter_status'] ) ) : '',
      'priority' => isset( $_GET['qc_filter_priority'] ) ? sanitize_text_field( wp_unslash( $_GET['qc_filter_priority'] ) ) : '',
      'owner' => isset( $_GET['qc_filter_owner'] ) ? sanitize_text_field( wp_unslash( $_GET['qc_filter_owner'] ) ) : '',
      'search' => isset( $_GET['qc_filter_search'] ) ? sanitize_text_field( wp_unslash( $_GET['qc_filter_search'] ) ) : '',
    );
  }

  private function filter_quality_compliance_rows( $rows, $filters, $keys = array() ) {
    $filters = is_array( $filters ) ? $filters : array();
    $keys = is_array( $keys ) ? $keys : array();
    return array_values( array_filter( (array) $rows, function( $row ) use ( $filters, $keys ) {
      if ( ! is_array( $row ) ) {
        return false;
      }
      if ( '' !== (string) ( $filters['type'] ?? '' ) && 0 !== strcasecmp( (string) ( $row['type'] ?? '' ), (string) $filters['type'] ) ) {
        return false;
      }
      if ( '' !== (string) ( $filters['status'] ?? '' ) && 0 !== strcasecmp( (string) ( $row['status'] ?? '' ), (string) $filters['status'] ) ) {
        return false;
      }
      if ( '' !== (string) ( $filters['priority'] ?? '' ) && 0 !== strcasecmp( (string) ( $row['priority'] ?? '' ), (string) $filters['priority'] ) ) {
        return false;
      }
      if ( '' !== (string) ( $filters['owner'] ?? '' ) && false === stripos( (string) ( $row['owner'] ?? '' ), (string) $filters['owner'] ) ) {
        return false;
      }
      $search = (string) ( $filters['search'] ?? '' );
      if ( '' !== $search ) {
        $haystack = '';
        foreach ( $keys as $key ) {
          $haystack .= ' ' . (string) ( $row[ $key ] ?? '' );
        }
        if ( false === stripos( $haystack, $search ) ) {
          return false;
        }
      }
      return true;
    } ) );
  }

  private function get_quality_permission_capabilities() {
    $caps = array(
      'view'   => 'acdc_quality_view',
      'export' => 'acdc_quality_export',
      'manage' => 'acdc_quality_manage',
      'delete' => 'acdc_quality_delete',
    );

    if ( function_exists( 'apply_filters' ) ) {
      $caps = apply_filters( 'acdc_of_quality_permission_capabilities', $caps, $this );
    }

    foreach ( $caps as $key => $cap ) {
      $caps[ $key ] = sanitize_key( (string) $cap );
    }

    return $caps;
  }

  private function current_user_can_quality_permission( $permission = 'view' ) {
    if ( ! is_user_logged_in() ) {
      return false;
    }

    if ( current_user_can( 'manage_options' ) ) {
      return true;
    }

    $caps = $this->get_quality_permission_capabilities();
    $cap  = isset( $caps[ $permission ] ) ? (string) $caps[ $permission ] : '';

    return '' !== $cap && current_user_can( $cap );
  }

  private function require_quality_permission( $permission = 'view', $message = 'Accès refusé.' ) {
    if ( ! $this->current_user_can_quality_permission( $permission ) ) {
      wp_die( esc_html( $message ) );
    }
  }

  private function get_quality_compliance_export_url( $dataset, $format, $filters = array() ) {
    if ( ! $this->current_user_can_quality_permission( 'export' ) ) {
      return '';
    }

    if ( 'gap_register' === $dataset ) {
      $action = 'csv' === $format ? 'acdc_export_quality_gap_register_csv' : 'acdc_export_quality_gap_register_excel';
    } elseif ( 'audit_log' === $dataset ) {
      $action = 'csv' === $format ? 'acdc_export_quality_audit_log_csv' : 'acdc_export_quality_audit_log_excel';
    } else {
      $action = 'csv' === $format ? 'acdc_export_quality_action_plan_csv' : 'acdc_export_quality_action_plan_excel';
    }

    $args = array(
      'action' => $action,
      '_wpnonce' => wp_create_nonce( $action ),
      'qc_filter_type' => (string) ( $filters['type'] ?? '' ),
      'qc_filter_status' => (string) ( $filters['status'] ?? '' ),
      'qc_filter_priority' => (string) ( $filters['priority'] ?? '' ),
      'qc_filter_owner' => (string) ( $filters['owner'] ?? '' ),
      'qc_filter_search' => (string) ( $filters['search'] ?? '' ),
    );

    return add_query_arg( $args, admin_url( 'admin-post.php' ) );
  }

  private function get_quality_compliance_export_rows( $dataset, $filters = array() ) {
    $foundation_data = $this->get_quality_compliance_foundations_data();
    if ( 'gap_register' === $dataset ) {
      $rows = $this->get_quality_compliance_gap_register_rows( $foundation_data );
      $rows = $this->filter_quality_compliance_rows( $rows, $filters, array( 'id', 'type', 'gap', 'severity', 'status', 'priority', 'owner', 'due_label', 'next_step', 'impact', 'source_proof' ) );
      $export_rows = array();
      foreach ( $rows as $row ) {
        $export_rows[] = array(
          'ID' => (string) ( $row['id'] ?? '' ),
          'Type' => (string) ( $row['type'] ?? '' ),
          'Écart' => (string) ( $row['gap'] ?? '' ),
          'Niveau' => (string) ( $row['severity'] ?? '' ),
          'Statut' => (string) ( $row['status'] ?? '' ),
          'Priorité' => (string) ( $row['priority'] ?? '' ),
          'Pilote' => (string) ( $row['owner'] ?? '' ),
          'Échéance' => (string) ( $row['due_label'] ?? '' ),
          'Prochaine étape' => (string) ( $row['next_step'] ?? '' ),
          'Impact' => (string) ( $row['impact'] ?? '' ),
          'Source de preuve' => (string) ( $row['source_proof'] ?? '' ),
        );
      }
      return $export_rows;
    }

    if ( 'audit_log' === $dataset ) {
      $rows = $this->get_quality_compliance_audit_log_rows( $foundation_data );
      $rows = $this->filter_quality_compliance_rows( $rows, $filters, array( 'scope', 'event', 'detail', 'status' ) );
      $export_rows = array();
      foreach ( $rows as $row ) {
        $export_rows[] = array(
          'Date' => (string) ( ! empty( $row['date'] ) ? mysql2date( 'Y-m-d H:i:s', $row['date'] ) : '' ),
          'Périmètre' => (string) ( $row['scope'] ?? '' ),
          'Événement' => (string) ( $row['event'] ?? '' ),
          'Détail' => (string) ( $row['detail'] ?? '' ),
          'Statut' => (string) ( $row['status'] ?? '' ),
        );
      }
      return $export_rows;
    }

    $rows = $this->get_quality_compliance_full_action_plan_rows( $foundation_data );
    $rows = $this->filter_quality_compliance_rows( $rows, $filters, array( 'id', 'type', 'objective', 'priority', 'status', 'owner', 'due_label', 'proof_status', 'evidence', 'success' ) );
    $export_rows = array();
    foreach ( $rows as $row ) {
      $export_rows[] = array(
        'ID' => (string) ( $row['id'] ?? '' ),
        'Type' => (string) ( $row['type'] ?? '' ),
        'Objectif' => (string) ( $row['objective'] ?? '' ),
        'Priorité' => (string) ( $row['priority'] ?? '' ),
        'Statut' => (string) ( $row['status'] ?? '' ),
        'Pilote' => (string) ( $row['owner'] ?? '' ),
        'Échéance' => (string) ( $row['due_label'] ?? '' ),
        'Statut de preuve' => (string) ( $row['proof_status'] ?? '' ),
        'Actions ouvertes' => (string) ( $row['actions_open'] ?? '' ),
        'Évidence' => (string) ( $row['evidence'] ?? '' ),
        'Critère de succès' => (string) ( $row['success'] ?? '' ),
      );
    }
    return $export_rows;
  }

  private function output_quality_compliance_export( $dataset, $format = 'csv' ) {
    $this->require_quality_permission( 'export', 'Accès refusé à l’export qualité.' );
    if ( 'gap_register' === $dataset ) {
      $action = 'csv' === $format ? 'acdc_export_quality_gap_register_csv' : 'acdc_export_quality_gap_register_excel';
    } elseif ( 'audit_log' === $dataset ) {
      $action = 'csv' === $format ? 'acdc_export_quality_audit_log_csv' : 'acdc_export_quality_audit_log_excel';
    } else {
      $action = 'csv' === $format ? 'acdc_export_quality_action_plan_csv' : 'acdc_export_quality_action_plan_excel';
    }
    check_admin_referer( $action );
    $filters = $this->get_quality_compliance_filters_from_request();
    $rows = $this->get_quality_compliance_export_rows( $dataset, $filters );
    $slug = 'gap_register' === $dataset ? 'registre-ecarts-qualite' : ( 'audit_log' === $dataset ? 'journal-audit-qualite' : 'plan-action-qualite' );
    nocache_headers();
    if ( 'csv' === $format ) {
      header( 'Content-Type: text/csv; charset=utf-8' );
      header( 'Content-Disposition: attachment; filename=' . $slug . '-' . gmdate( 'Ymd-His' ) . '.csv' );
      $out = fopen( 'php://output', 'w' );
      if ( ! empty( $rows ) ) {
        fputcsv( $out, array_keys( $rows[0] ), ';' );
        foreach ( $rows as $row ) {
          fputcsv( $out, array_values( $row ), ';' );
        }
      } else {
        fputcsv( $out, array( 'Aucune donnée' ), ';' );
      }
      fclose( $out );
      exit;
    }
    header( 'Content-Type: application/vnd.ms-excel; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=' . $slug . '-' . gmdate( 'Ymd-His' ) . '.xls' );
    echo "ï»¿";
    if ( ! empty( $rows ) ) {
      echo implode( "	", array_keys( $rows[0] ) ) . "
";
      foreach ( $rows as $row ) {
        $line = array();
        foreach ( array_values( $row ) as $value ) {
          $line[] = str_replace( array( "	", "
", "
" ), ' ', (string) $value );
        }
        echo implode( "	", $line ) . "
";
      }
    } else {
      echo "Aucune donnée
";
    }
    exit;
  }

  public function export_quality_gap_register_csv() { $this->output_quality_compliance_export( 'gap_register', 'csv' ); }
  public function export_quality_gap_register_excel() { $this->output_quality_compliance_export( 'gap_register', 'excel' ); }
  public function export_quality_action_plan_csv() { $this->output_quality_compliance_export( 'action_plan', 'csv' ); }
  public function export_quality_action_plan_excel() { $this->output_quality_compliance_export( 'action_plan', 'excel' ); }
  public function export_quality_audit_log_csv() { $this->output_quality_compliance_export( 'audit_log', 'csv' ); }
  public function export_quality_audit_log_excel() { $this->output_quality_compliance_export( 'audit_log', 'excel' ); }

  private function get_quality_gap_records() {
    $rows = get_option( $this->get_quality_gap_records_option_key(), array() );
    return is_array( $rows ) ? $rows : array();
  }

  private function get_quality_action_records() {
    $rows = get_option( $this->get_quality_action_records_option_key(), array() );
    return is_array( $rows ) ? $rows : array();
  }

  private function save_quality_gap_records( $rows ) {
    update_option( $this->get_quality_gap_records_option_key(), array_values( is_array( $rows ) ? $rows : array() ), false );
  }

  private function save_quality_action_records( $rows ) {
    update_option( $this->get_quality_action_records_option_key(), array_values( is_array( $rows ) ? $rows : array() ), false );
  }

  private function generate_quality_record_id( $prefix, $rows ) {
    $max = 0;
    foreach ( (array) $rows as $row ) {
      if ( empty( $row['id'] ) ) {
        continue;
      }
      if ( preg_match( '/^' . preg_quote( $prefix, '/' ) . '(\d+)$/', (string) $row['id'], $matches ) ) {
        $max = max( $max, (int) $matches[1] );
      }
    }
    return sprintf( '%s%02d', $prefix, $max + 1 );
  }

  private function get_quality_gap_record_by_id( $record_id ) {
    foreach ( $this->get_quality_gap_records() as $row ) {
      if ( isset( $row['id'] ) && (string) $row['id'] === (string) $record_id ) {
        return $row;
      }
    }
    return null;
  }

  private function get_quality_action_record_by_id( $record_id ) {
    foreach ( $this->get_quality_action_records() as $row ) {
      if ( isset( $row['id'] ) && (string) $row['id'] === (string) $record_id ) {
        return $row;
      }
    }
    return null;
  }

  private function get_quality_context_from_request() {
    $context = isset( $_REQUEST['qc_context'] ) ? sanitize_key( wp_unslash( $_REQUEST['qc_context'] ) ) : '';
    return in_array( $context, array( 'front', 'admin' ), true ) ? $context : '';
  }

  private function get_quality_management_url( $view = '', $force_context = 'auto' ) {
    $context = in_array( $force_context, array( 'front', 'admin' ), true ) ? $force_context : ( is_admin() ? 'admin' : 'front' );
    if ( 'admin' === $context ) {
      $args = array( 'page' => 'acdc-of-improvement-pilotage' );
      if ( '' !== $view ) {
        $args['qc_view'] = $view;
      }
      return add_query_arg( $args, admin_url( 'admin.php' ) );
    }

    $args = array( 'tab' => 'improvement_pilotage' );
    if ( '' !== $view ) {
      $args['qc_view'] = $view;
    }
    return $this->quality_compliance_page_url( $args );
  }

  private function get_quality_record_edit_url( $view, $record_id, $force_context = 'auto' ) {
    return add_query_arg(
      array( 'record_id' => rawurlencode( $record_id ) ),
      $this->get_quality_management_url( $view, $force_context )
    );
  }

  private function get_quality_delete_record_url( $record_type, $record_id, $force_context = 'auto' ) {
    $record_type = 'action' === $record_type ? 'action' : 'gap';
    $action = 'action' === $record_type ? 'acdc_delete_quality_action_record' : 'acdc_delete_quality_gap_record';
    $nonce_action = 'action' === $record_type ? 'acdc_delete_quality_action_record_' . $record_id : 'acdc_delete_quality_gap_record_' . $record_id;
    $args = array(
      'record_id'  => $record_id,
      'qc_context' => in_array( $force_context, array( 'front', 'admin' ), true ) ? $force_context : ( is_admin() ? 'admin' : 'front' ),
    );
    return $this->secure_admin_post_url( $action, $args, $nonce_action );
  }

  private function get_quality_redirect_url_from_request( $notice = '', $view = '', $record_id = '' ) {
    $context = $this->get_quality_context_from_request();
    if ( '' === $context ) {
      $context = is_admin() ? 'admin' : 'front';
    }
    $url = $this->get_quality_management_url( $view, $context );
    $args = array();
    if ( '' !== $record_id ) {
      $args['record_id'] = $record_id;
    }
    if ( '' !== $notice ) {
      $args['quality_notice'] = $notice;
    }
    return add_query_arg( $args, $url );
  }

  private function get_quality_gap_form_defaults( $record = null ) {
    $record = is_array( $record ) ? $record : array();
    return array(
      'id' => isset( $record['id'] ) ? (string) $record['id'] : '',
      'type' => isset( $record['type'] ) ? (string) $record['type'] : '',
      'gap' => isset( $record['gap'] ) ? (string) $record['gap'] : '',
      'severity' => isset( $record['severity'] ) ? (string) $record['severity'] : 'Moyen',
      'status' => isset( $record['status'] ) ? (string) $record['status'] : 'Ouvert',
      'priority' => isset( $record['priority'] ) ? (string) $record['priority'] : 'Moyenne',
      'owner' => isset( $record['owner'] ) ? (string) $record['owner'] : 'Pilotage qualité',
      'due_label' => isset( $record['due_label'] ) ? (string) $record['due_label'] : 'J+30',
      'next_step' => isset( $record['next_step'] ) ? (string) $record['next_step'] : '',
      'impact' => isset( $record['impact'] ) ? (string) $record['impact'] : '',
      'source_proof' => isset( $record['source_proof'] ) ? (string) $record['source_proof'] : '',
    );
  }

  private function get_quality_action_form_defaults( $record = null ) {
    $record = is_array( $record ) ? $record : array();
    return array(
      'id' => isset( $record['id'] ) ? (string) $record['id'] : '',
      'type' => isset( $record['type'] ) ? (string) $record['type'] : '',
      'objective' => isset( $record['objective'] ) ? (string) $record['objective'] : '',
      'priority' => isset( $record['priority'] ) ? (string) $record['priority'] : 'Moyenne',
      'status' => isset( $record['status'] ) ? (string) $record['status'] : 'À ouvrir',
      'owner' => isset( $record['owner'] ) ? (string) $record['owner'] : 'Pilotage qualité',
      'due_label' => isset( $record['due_label'] ) ? (string) $record['due_label'] : 'J+30',
      'success' => isset( $record['success'] ) ? (string) $record['success'] : '',
      'proof_status' => isset( $record['proof_status'] ) ? (string) $record['proof_status'] : 'À qualifier',
      'actions_open' => isset( $record['actions_open'] ) ? (int) $record['actions_open'] : 0,
      'evidence' => isset( $record['evidence'] ) ? (string) $record['evidence'] : '',
    );
  }

  private function get_quality_management_state() {
    $view = isset( $_GET['qc_view'] ) ? sanitize_key( wp_unslash( $_GET['qc_view'] ) ) : '';
    $record_id = isset( $_GET['record_id'] ) ? sanitize_text_field( wp_unslash( $_GET['record_id'] ) ) : '';
    $can_manage = $this->current_user_can_quality_permission( 'manage' );

    return array(
      'view' => $view,
      'record_id' => $record_id,
      'can_manage' => $can_manage,
      'can_delete' => $this->current_user_can_quality_permission( 'delete' ),
      'can_export' => $this->current_user_can_quality_permission( 'export' ),
      'editing_gap' => ( $can_manage && 'edit_gap' === $view && '' !== $record_id ) ? $this->get_quality_gap_record_by_id( $record_id ) : null,
      'editing_action' => ( $can_manage && 'edit_action' === $view && '' !== $record_id ) ? $this->get_quality_action_record_by_id( $record_id ) : null,
    );
  }

  public function handle_save_quality_gap_record() {
    $this->require_quality_permission( 'manage', 'Accès refusé à la modification du registre qualité.' );
    check_admin_referer( 'acdc_save_quality_gap_record' );

    $records = $this->get_quality_gap_records();
    $record_id = isset( $_POST['record_id'] ) ? sanitize_text_field( wp_unslash( $_POST['record_id'] ) ) : '';

    $data = array(
      'id' => $record_id,
      'type' => isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '',
      'gap' => isset( $_POST['gap'] ) ? sanitize_text_field( wp_unslash( $_POST['gap'] ) ) : '',
      'severity' => isset( $_POST['severity'] ) ? sanitize_text_field( wp_unslash( $_POST['severity'] ) ) : 'Moyen',
      'status' => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Ouvert',
      'priority' => isset( $_POST['priority'] ) ? sanitize_text_field( wp_unslash( $_POST['priority'] ) ) : 'Moyenne',
      'owner' => isset( $_POST['owner'] ) ? sanitize_text_field( wp_unslash( $_POST['owner'] ) ) : 'Pilotage qualité',
      'due_label' => isset( $_POST['due_label'] ) ? sanitize_text_field( wp_unslash( $_POST['due_label'] ) ) : 'J+30',
      'next_step' => isset( $_POST['next_step'] ) ? sanitize_textarea_field( wp_unslash( $_POST['next_step'] ) ) : '',
      'impact' => isset( $_POST['impact'] ) ? sanitize_textarea_field( wp_unslash( $_POST['impact'] ) ) : '',
      'source_proof' => isset( $_POST['source_proof'] ) ? sanitize_text_field( wp_unslash( $_POST['source_proof'] ) ) : '',
    );

    if ( '' === $data['type'] || '' === $data['gap'] ) {
      wp_safe_redirect( $this->get_quality_redirect_url_from_request( 'missing_gap_fields', '' === $record_id ? 'new_gap' : 'edit_gap', $record_id ) );
      exit;
    }

    if ( '' === $record_id ) {
      $data['id'] = $this->generate_quality_record_id( 'QC-E', $records );
      $records[] = $data;
    } else {
      $updated = false;
      foreach ( $records as $index => $row ) {
        if ( isset( $row['id'] ) && (string) $row['id'] === $record_id ) {
          $records[ $index ] = $data;
          $updated = true;
          break;
        }
      }
      if ( ! $updated ) {
        $records[] = $data;
      }
    }

    $this->save_quality_gap_records( $records );
    $this->append_quality_audit_log_event( 'Registre des écarts', '' === $record_id ? 'Création d’un écart qualité' : 'Mise à jour d’un écart qualité', 'Écart ' . $data['id'] . ' enregistré dans le registre.', 'Traçable' );
    wp_safe_redirect( $this->get_quality_redirect_url_from_request( 'gap_saved' ) );
    exit;
  }

  public function handle_delete_quality_gap_record() {
    $this->require_quality_permission( 'delete', 'Accès refusé à la suppression du registre qualité.' );
    $record_id = isset( $_GET['record_id'] ) ? sanitize_text_field( wp_unslash( $_GET['record_id'] ) ) : '';
    check_admin_referer( 'acdc_delete_quality_gap_record_' . $record_id );

    $records = array_values( array_filter( $this->get_quality_gap_records(), function( $row ) use ( $record_id ) {
      return ! isset( $row['id'] ) || (string) $row['id'] !== $record_id;
    } ) );
    $this->save_quality_gap_records( $records );
    /* ACDC 3.25.291 — Ce journal appartient au gestionnaire de SUPPRESSION, et
       il annonçait une « création » ou une « mise à jour » en lisant $data['id'],
       une variable qui n'existe pas ici. La trace d'une suppression décrivait
       donc l'inverse de ce qui venait de se passer — et c'est justement cette
       trace-là qu'on relit après coup. */
    $this->append_quality_audit_log_event( 'Registre des écarts', 'Suppression d’un écart qualité', 'Écart ' . $record_id . ' supprimé du registre.', 'Traçable' );
    wp_safe_redirect( $this->get_quality_redirect_url_from_request( 'gap_deleted' ) );
    exit;
  }

  public function handle_save_quality_action_record() {
    $this->require_quality_permission( 'manage', 'Accès refusé à la modification du plan d’action qualité.' );
    check_admin_referer( 'acdc_save_quality_action_record' );

    $records = $this->get_quality_action_records();
    $record_id = isset( $_POST['record_id'] ) ? sanitize_text_field( wp_unslash( $_POST['record_id'] ) ) : '';

    $data = array(
      'id' => $record_id,
      'type' => isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '',
      'objective' => isset( $_POST['objective'] ) ? sanitize_textarea_field( wp_unslash( $_POST['objective'] ) ) : '',
      'priority' => isset( $_POST['priority'] ) ? sanitize_text_field( wp_unslash( $_POST['priority'] ) ) : 'Moyenne',
      'status' => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'À ouvrir',
      'owner' => isset( $_POST['owner'] ) ? sanitize_text_field( wp_unslash( $_POST['owner'] ) ) : 'Pilotage qualité',
      'due_label' => isset( $_POST['due_label'] ) ? sanitize_text_field( wp_unslash( $_POST['due_label'] ) ) : 'J+30',
      'success' => isset( $_POST['success'] ) ? sanitize_textarea_field( wp_unslash( $_POST['success'] ) ) : '',
      'proof_status' => isset( $_POST['proof_status'] ) ? sanitize_text_field( wp_unslash( $_POST['proof_status'] ) ) : 'À qualifier',
      'actions_open' => isset( $_POST['actions_open'] ) ? absint( wp_unslash( $_POST['actions_open'] ) ) : 0,
      'evidence' => isset( $_POST['evidence'] ) ? sanitize_textarea_field( wp_unslash( $_POST['evidence'] ) ) : '',
    );

    if ( '' === $data['type'] || '' === $data['objective'] ) {
      wp_safe_redirect( $this->get_quality_redirect_url_from_request( 'missing_action_fields', '' === $record_id ? 'new_action' : 'edit_action', $record_id ) );
      exit;
    }

    if ( '' === $record_id ) {
      $data['id'] = $this->generate_quality_record_id( 'QC-A', $records );
      $records[] = $data;
    } else {
      $updated = false;
      foreach ( $records as $index => $row ) {
        if ( isset( $row['id'] ) && (string) $row['id'] === $record_id ) {
          $records[ $index ] = $data;
          $updated = true;
          break;
        }
      }
      if ( ! $updated ) {
        $records[] = $data;
      }
    }

    $this->save_quality_action_records( $records );
    $this->append_quality_audit_log_event( 'Plan d’action', '' === $record_id ? 'Création d’une action qualité' : 'Mise à jour d’une action qualité', 'Action ' . $data['id'] . ' enregistrée dans le plan.', 'Traçable' );
    wp_safe_redirect( $this->get_quality_redirect_url_from_request( 'action_saved' ) );
    exit;
  }

  public function handle_delete_quality_action_record() {
    $this->require_quality_permission( 'delete', 'Accès refusé à la suppression du plan d’action qualité.' );
    $record_id = isset( $_GET['record_id'] ) ? sanitize_text_field( wp_unslash( $_GET['record_id'] ) ) : '';
    check_admin_referer( 'acdc_delete_quality_action_record_' . $record_id );

    $records = array_values( array_filter( $this->get_quality_action_records(), function( $row ) use ( $record_id ) {
      return ! isset( $row['id'] ) || (string) $row['id'] !== $record_id;
    } ) );
    $this->save_quality_action_records( $records );
    /* ACDC 3.25.291 — Même défaut sur la suppression d'une action qualité. */
    $this->append_quality_audit_log_event( 'Plan d’action', 'Suppression d’une action qualité', 'Action ' . $record_id . ' supprimée du plan.', 'Traçable' );
    wp_safe_redirect( $this->get_quality_redirect_url_from_request( 'action_deleted' ) );
    exit;
  }

  private function get_improvement_pilotage_filters_from_request() {
    $source_type    = isset( $_GET['source_type'] ) ? sanitize_key( wp_unslash( $_GET['source_type'] ) ) : '';
    $assigned_to    = isset( $_GET['assigned_to'] ) ? absint( wp_unslash( $_GET['assigned_to'] ) ) : 0;
    $action_status  = isset( $_GET['action_status'] ) ? sanitize_key( wp_unslash( $_GET['action_status'] ) ) : '';
    $priority_level = isset( $_GET['priority_level'] ) ? sanitize_key( wp_unslash( $_GET['priority_level'] ) ) : '';
    $due_state      = isset( $_GET['due_state'] ) ? sanitize_key( wp_unslash( $_GET['due_state'] ) ) : '';
    $search         = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';

    $source_map      = $this->get_questionnaire_source_label_map();
    $status_map      = $this->get_questionnaire_action_statuses_map();
    $priority_levels = $this->get_questionnaire_action_priority_levels();
    $due_map         = $this->get_questionnaire_action_due_state_filter_labels();

    if ( '' !== $source_type && ! isset( $source_map[ $source_type ] ) ) {
      $source_type = '';
    }
    if ( '' !== $action_status && ! isset( $status_map[ $action_status ] ) ) {
      $action_status = '';
    }
    if ( '' !== $priority_level && ! isset( $priority_levels[ $priority_level ] ) ) {
      $priority_level = '';
    }
    if ( '' !== $due_state && ! isset( $due_map[ $due_state ] ) ) {
      $due_state = '';
    }

    return array(
      'source_type'    => $source_type,
      'assigned_to'    => $assigned_to,
      'action_status'  => $action_status,
      'priority_level' => $priority_level,
      'due_state'      => $due_state,
      'search'         => $search,
    );
  }


  private function get_questionnaire_action_due_state_filter_labels() {
    return array(
      'en_retard'       => 'En retard',
      'aujourd_hui'     => 'Échéance aujourd’hui',
      'echeance_proche' => 'Échéance proche',
      'a_traiter'       => 'À traiter',
    );
  }


  private function get_improvement_pilotage_url( $extra = array() ) {
    if ( is_admin() ) {
      return admin_url( 'admin.php?' . http_build_query( array_merge( array( 'page' => 'acdc-of-improvement-pilotage' ), $extra ) ) );
    }
    return $this->portal_page_url( array_merge( array( 'tab' => 'improvement_pilotage' ), $extra ) );
  }


  private function get_improvement_pilotage_export_url( $format, $filters = array() ) {
    $action = 'csv' === $format ? 'acdc_export_improvement_pilotage_csv' : 'acdc_export_improvement_pilotage_excel';
    $args = array( 'action' => $action );
    if ( ! empty( $filters['source_type'] ) ) {
      $args['source_type'] = (string) $filters['source_type'];
    }
    if ( ! empty( $filters['assigned_to'] ) ) {
      $args['assigned_to'] = absint( $filters['assigned_to'] );
    }
    if ( ! empty( $filters['action_status'] ) ) {
      $args['action_status'] = (string) $filters['action_status'];
    }
    if ( ! empty( $filters['priority_level'] ) ) {
      $args['priority_level'] = (string) $filters['priority_level'];
    }
    if ( ! empty( $filters['due_state'] ) ) {
      $args['due_state'] = (string) $filters['due_state'];
    }
    if ( ! empty( $filters['search'] ) ) {
      $args['search'] = (string) $filters['search'];
    }
    return wp_nonce_url( admin_url( 'admin-post.php?' . http_build_query( $args ) ), $action );
  }


  private function get_improvement_pilotage_rows( $filters = array(), $limit = 300 ) {
    global $wpdb;

    if ( empty( $this->questionnaire_action_table ) || empty( $this->questionnaire_session_table ) ) {
      return array();
    }

    $source_label_map = $this->get_questionnaire_source_label_map();
    $survey_types = $this->get_survey_questionnaire_source_types();
    $survey_placeholders = implode( ',', array_fill( 0, count( $survey_types ), '%s' ) );
    $where  = array( "a.action_type = 'improvement'", "s.source_type IN ({$survey_placeholders})" );
    $params = $survey_types;

    if ( ! empty( $filters['source_type'] ) ) {
      $where[]  = 's.source_type = %s';
      $params[] = (string) $filters['source_type'];
    }
    if ( ! empty( $filters['assigned_to'] ) ) {
      $where[]  = 'a.assigned_to = %d';
      $params[] = absint( $filters['assigned_to'] );
    }
    if ( ! empty( $filters['action_status'] ) ) {
      $where[]  = 'a.action_status = %s';
      $params[] = (string) $filters['action_status'];
    }
    if ( ! empty( $filters['priority_level'] ) ) {
      $priority_condition = $this->get_questionnaire_action_priority_filter_condition( $filters['priority_level'], 'a' );
      if ( ! empty( $priority_condition['sql'] ) ) {
        $where[] = $priority_condition['sql'];
        $params  = array_merge( $params, $priority_condition['params'] );
      }
    }
    if ( ! empty( $filters['search'] ) ) {
      $like     = '%' . $wpdb->esc_like( (string) $filters['search'] ) . '%';
      $where[]  = '(a.action_label LIKE %s OR a.notes LIKE %s OR s.session_title LIKE %s)';
      $params[] = $like;
      $params[] = $like;
      $params[] = $like;
    }

    $limit = max( 1, absint( $limit ) );

    $sql = "SELECT a.*, s.source_type, s.source_id, s.session_title, s.status AS questionnaire_session_status
            FROM {$this->questionnaire_action_table} a
            INNER JOIN {$this->questionnaire_session_table} s ON s.id = a.questionnaire_session_id
            WHERE " . implode( ' AND ', $where ) . "
            ORDER BY CASE
              WHEN a.priority_level IN ('Haute','Critique') THEN 0
              WHEN a.priority_level IN ('Moyenne','Normale') THEN 1
              ELSE 2
            END ASC,
            CASE WHEN a.due_at IS NULL THEN 1 ELSE 0 END ASC,
            a.due_at ASC,
            a.id DESC
            LIMIT %d";
    $params[] = $limit;
    $sql = $wpdb->prepare( $sql, $params );

    $rows = $wpdb->get_results( $sql );
    if ( ! is_array( $rows ) ) {
      return array();
    }

    foreach ( $rows as $row ) {
      $row->due_state = $this->get_questionnaire_action_due_state( $row );
      if ( ! empty( $filters['due_state'] ) ) {
        $matches = false;
        if ( 'a_traiter' === $filters['due_state'] ) {
          $matches = in_array( ! empty( $row->action_status ) ? (string) $row->action_status : 'ouverte', array( 'ouverte', 'en_cours' ), true );
        } else {
          $matches = ! empty( $row->due_state['state_key'] ) && (string) $row->due_state['state_key'] === (string) $filters['due_state'];
        }
        if ( ! $matches ) {
          $row->skip_from_filter = true;
          continue;
        }
      }
      $row->priority_badge    = $this->get_questionnaire_action_priority_badge( ! empty( $row->priority_level ) ? (string) $row->priority_level : 'Moyenne' );
      $row->source            = ( ! empty( $row->source_type ) && ! empty( $row->source_id ) ) ? $this->get_questionnaire_source_data( (string) $row->source_type, (int) $row->source_id ) : null;
      $row->participant_label = '—';
      if ( ! empty( $row->participant_id ) ) {
        $participant = $wpdb->get_row(
          $wpdb->prepare(
            "SELECT p.*, l.first_name, l.last_name FROM {$this->questionnaire_participant_table} p LEFT JOIN {$this->learner_table} l ON l.id = p.apprenant_id WHERE p.id = %d",
            (int) $row->participant_id
          )
        );
        if ( $participant ) {
          $row->participant_label = $this->get_questionnaire_session_participant_display_name( $participant );
        }
      }
      $row->source_label       = isset( $source_label_map[ $row->source_type ] ) ? $source_label_map[ $row->source_type ] : (string) $row->source_type;
      $row->questionnaire_title = ! empty( $row->source['title'] ) ? (string) $row->source['title'] : 'Questionnaire supprimé';
      $row->followup_url = $this->get_questionnaire_results_url(
        ! empty( $row->source_type ) ? (string) $row->source_type : '',
        ! empty( $row->questionnaire_session_id ) ? (int) $row->questionnaire_session_id : 0,
        ! empty( $row->source_id ) ? (int) $row->source_id : 0,
        ! empty( $row->participant_id ) ? (int) $row->participant_id : 0
      );
    }

    return array_values( array_filter( $rows, function( $row ) {
      return empty( $row->skip_from_filter );
    } ) );
  }


  private function get_improvement_pilotage_stats( $rows ) {
    $source_label_map = $this->get_questionnaire_source_label_map();
    $stats = array(
      'total'           => 0,
      'a_traiter'       => 0,
      'ouverte'         => 0,
      'en_cours'        => 0,
      'traitee'         => 0,
      'annulee'         => 0,
      'en_retard'       => 0,
      'aujourd_hui'     => 0,
      'echeance_proche' => 0,
      'priority_high'   => 0,
      'priority_medium' => 0,
      'priority_low'    => 0,
      'assigned'        => array(),
      'survey_types'    => array(),
    );

    foreach ( (array) $rows as $row ) {
      $status = ! empty( $row->action_status ) ? sanitize_key( (string) $row->action_status ) : 'ouverte';
      $priority_label = $this->normalize_questionnaire_action_priority_level( ! empty( $row->priority_level ) ? (string) $row->priority_level : 'Moyenne' );
      $due_state = ! empty( $row->due_state ) && is_array( $row->due_state ) ? $row->due_state : $this->get_questionnaire_action_due_state( $row );
      $source_type = ! empty( $row->source_type ) ? (string) $row->source_type : '';
      $assignee_label = $this->get_questionnaire_action_assignee_label( ! empty( $row->assigned_to ) ? (int) $row->assigned_to : 0 );

      $stats['total']++;
      if ( isset( $stats[ $status ] ) ) {
        $stats[ $status ]++;
      }
      if ( in_array( $status, array( 'ouverte', 'en_cours' ), true ) ) {
        $stats['a_traiter']++;
      }
      if ( 'Haute' === $priority_label ) {
        $stats['priority_high']++;
      } elseif ( 'Basse' === $priority_label ) {
        $stats['priority_low']++;
      } else {
        $stats['priority_medium']++;
      }
      if ( 'en_retard' === $due_state['state_key'] ) {
        $stats['en_retard']++;
      } elseif ( 'aujourd_hui' === $due_state['state_key'] ) {
        $stats['aujourd_hui']++;
      } elseif ( 'echeance_proche' === $due_state['state_key'] ) {
        $stats['echeance_proche']++;
      }
      if ( ! isset( $stats['assigned'][ $assignee_label ] ) ) {
        $stats['assigned'][ $assignee_label ] = 0;
      }
      $stats['assigned'][ $assignee_label ]++;
      if ( '' !== $source_type ) {
        if ( ! isset( $stats['survey_types'][ $source_type ] ) ) {
          $stats['survey_types'][ $source_type ] = array(
            'label'         => isset( $source_label_map[ $source_type ] ) ? $source_label_map[ $source_type ] : $source_type,
            'total'         => 0,
            'a_traiter'     => 0,
            'traitee'       => 0,
            'en_retard'     => 0,
            'priority_high' => 0,
          );
        }
        $stats['survey_types'][ $source_type ]['total']++;
        if ( in_array( $status, array( 'ouverte', 'en_cours' ), true ) ) {
          $stats['survey_types'][ $source_type ]['a_traiter']++;
        }
        if ( 'traitee' === $status ) {
          $stats['survey_types'][ $source_type ]['traitee']++;
        }
        if ( 'en_retard' === $due_state['state_key'] ) {
          $stats['survey_types'][ $source_type ]['en_retard']++;
        }
        if ( 'Haute' === $priority_label ) {
          $stats['survey_types'][ $source_type ]['priority_high']++;
        }
      }
    }

    arsort( $stats['assigned'] );
    uasort( $stats['survey_types'], function( $a, $b ) {
      if ( $a['total'] === $b['total'] ) {
        return strcmp( (string) $a['label'], (string) $b['label'] );
      }
      return $b['total'] <=> $a['total'];
    } );

    return $stats;
  }


  private function get_improvement_pilotage_export_rows( $rows ) {
    $export_rows = array();
    foreach ( (array) $rows as $row ) {
      $due_state = ! empty( $row->due_state ) && is_array( $row->due_state ) ? $row->due_state : $this->get_questionnaire_action_due_state( $row );
      $export_rows[] = array(
        'Type d’enquête'      => ! empty( $row->source_label ) ? (string) $row->source_label : '—',
        'Questionnaire'       => ! empty( $row->questionnaire_title ) ? (string) $row->questionnaire_title : '—',
        'Session'             => ! empty( $row->session_title ) ? (string) $row->session_title : '—',
        'Action'              => ! empty( $row->action_label ) ? (string) $row->action_label : '—',
        'Statut'              => $this->get_questionnaire_action_status_label( ! empty( $row->action_status ) ? (string) $row->action_status : 'ouverte' ),
        'Priorité'            => ! empty( $row->priority_badge['label'] ) ? (string) $row->priority_badge['label'] : $this->normalize_questionnaire_action_priority_level( ! empty( $row->priority_level ) ? (string) $row->priority_level : 'Moyenne' ),
        'Responsable'         => $this->get_questionnaire_action_assignee_label( ! empty( $row->assigned_to ) ? (int) $row->assigned_to : 0 ),
        'Retard / urgence'    => ! empty( $due_state['badge_label'] ) ? (string) $due_state['badge_label'] : 'À venir',
        'Échéance'            => ! empty( $due_state['label'] ) ? (string) $due_state['label'] : '—',
        'Destinataire'        => ! empty( $row->participant_label ) ? (string) $row->participant_label : '—',
        'Dernière relance'    => ! empty( $row->internal_followup_last_sent_at ) ? mysql2date( 'd/m/Y H:i', $row->internal_followup_last_sent_at ) : '—',
        'Nombre de relances'  => ! empty( $row->internal_followup_count ) ? (int) $row->internal_followup_count : 0,
        'Ouverte le'          => ! empty( $row->opened_at ) ? mysql2date( 'd/m/Y H:i', $row->opened_at ) : '—',
        'Clôturée le'         => ! empty( $row->closed_at ) ? mysql2date( 'd/m/Y H:i', $row->closed_at ) : '—',
        'Notes'               => ! empty( $row->notes ) ? trim( wp_strip_all_tags( (string) $row->notes ) ) : '',
      );
    }
    return $export_rows;
  }


  private function output_improvement_pilotage_delimited_export( $format = 'csv' ) {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( 'Accès refusé.' );
    }

    $action = 'csv' === $format ? 'acdc_export_improvement_pilotage_csv' : 'acdc_export_improvement_pilotage_excel';
    check_admin_referer( $action );

    $filters = array(
      'source_type'    => isset( $_GET['source_type'] ) ? sanitize_key( wp_unslash( $_GET['source_type'] ) ) : '',
      'assigned_to'    => isset( $_GET['assigned_to'] ) ? absint( wp_unslash( $_GET['assigned_to'] ) ) : 0,
      'action_status'  => isset( $_GET['action_status'] ) ? sanitize_key( wp_unslash( $_GET['action_status'] ) ) : '',
      'priority_level' => isset( $_GET['priority_level'] ) ? sanitize_key( wp_unslash( $_GET['priority_level'] ) ) : '',
      'due_state'      => isset( $_GET['due_state'] ) ? sanitize_key( wp_unslash( $_GET['due_state'] ) ) : '',
      'search'         => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
    );

    $rows = $this->get_improvement_pilotage_rows( $filters, 1000 );
    $export_rows = $this->get_improvement_pilotage_export_rows( $rows );

    if ( 'csv' === $format ) {
      header( 'Content-Type: text/csv; charset=utf-8' );
      header( 'Content-Disposition: attachment; filename=pilotage-amelioration-' . gmdate( 'Ymd-His' ) . '.csv' );
      $handle = fopen( 'php://output', 'w' );
      if ( ! empty( $export_rows ) ) {
        fputcsv( $handle, array_keys( $export_rows[0] ), ';' );
        foreach ( $export_rows as $export_row ) {
          fputcsv( $handle, array_values( $export_row ), ';' );
        }
      } else {
        fputcsv( $handle, array( 'Aucune donnée' ), ';' );
      }
      fclose( $handle );
      exit;
    }

    header( 'Content-Type: application/vnd.ms-excel; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=pilotage-amelioration-' . gmdate( 'Ymd-His' ) . '.xls' );
    echo "\xEF\xBB\xBF";
    if ( ! empty( $export_rows ) ) {
      echo implode( "\t", array_map( 'strval', array_keys( $export_rows[0] ) ) ) . "\n";
      foreach ( $export_rows as $export_row ) {
        $line = array();
        foreach ( array_values( $export_row ) as $value ) {
          $line[] = str_replace( array( "\t", "\r", "\n" ), ' ', (string) $value );
        }
        echo implode( "\t", $line ) . "\n";
      }
    } else {
      echo "Aucune donnée\n";
    }
    exit;
  }


  public function export_improvement_pilotage_csv() {
    $this->output_improvement_pilotage_delimited_export( 'csv' );
  }


  public function export_improvement_pilotage_excel() {
    $this->output_improvement_pilotage_delimited_export( 'excel' );
  }


  private function update_questionnaire_session_status_action( $status, $nonce_action, $force_index = null, $set_end = false ) {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    $session_id = isset( $_POST['questionnaire_session_id'] ) ? absint( wp_unslash( $_POST['questionnaire_session_id'] ) ) : 0;
    check_admin_referer( $nonce_action . '_' . $session_id );
    global $wpdb;
    $data = array( 'status' => $status, 'updated_at' => $this->now_mysql() );
    if ( null !== $force_index ) { $data['current_question_index'] = (int) $force_index; }
    if ( 'en_cours' === $status ) { $data['started_at'] = $this->now_mysql(); }
    if ( $set_end ) { $data['ended_at'] = $this->now_mysql(); }
    $wpdb->update( $this->questionnaire_session_table, $data, array( 'id' => $session_id ) );
    if ( $set_end ) {
      $finished_session = $this->get_questionnaire_session( $session_id );
      if ( $finished_session ) { $this->finalize_scored_questionnaire_session_participants( $finished_session ); }
    }
    $redirect_args = array( 'action' => 'animate', 'item_id' => $session_id, 'notice' => rawurlencode( 'Session mise à jour.' ), 'notice_type' => 'success' );
    if ( ! empty( $_POST['source_type'] ) ) { $redirect_args['source_type'] = sanitize_text_field( wp_unslash( $_POST['source_type'] ) ); }
    if ( ! empty( $_POST['source_id'] ) ) { $redirect_args['source_id'] = absint( wp_unslash( $_POST['source_id'] ) ); }
    wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php?page=acdc-of-questionnaire-sessions' ) ) ); exit;
  }


  /**
   * Finalise les participants d'une session évaluée (évaluation / test de positionnement / quiz live)
   * à la clôture de session : calcule et persiste final_score en POURCENTAGE (0-100), marque le
   * participant « termine » et renseigne responded_at/finished_at.
   *
   * Les enquêtes de satisfaction sont exclues : leur final_score (moyenne de notes 1-5) est déjà
   * calculé au fil de l'eau lors de la soumission et s'affiche sur une échelle « /N ».
   */
  private function finalize_scored_questionnaire_session_participants( $session ) {
    global $wpdb;
    if ( ! $session || $this->is_survey_questionnaire_source_type( (string) $session->source_type ) ) {
      return;
    }
    $source = $this->get_questionnaire_source_data( $session->source_type, $session->source_id );
    $questions = ( $source && ! empty( $source['questions'] ) && is_array( $source['questions'] ) ) ? array_values( $source['questions'] ) : array();
    $scored_total = 0;
    foreach ( $questions as $question ) {
      foreach ( $this->parse_question_choices( $question ) as $choice ) {
        if ( ! empty( $choice['is_correct'] ) ) { $scored_total++; break; }
      }
    }
    $participants = $this->get_questionnaire_session_participants( (int) $session->id );
    foreach ( (array) $participants as $participant ) {
      if ( in_array( (string) ( $participant->participant_status ?? '' ), array( 'repondu', 'termine' ), true ) ) {
        continue;
      }
      $answered = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->questionnaire_answer_table} WHERE session_id = %d AND participant_id = %d", (int) $session->id, (int) $participant->id ) );
      if ( $answered < 1 ) {
        continue;
      }
      $correct = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(points_awarded),0) FROM {$this->questionnaire_answer_table} WHERE session_id = %d AND participant_id = %d", (int) $session->id, (int) $participant->id ) );
      $final_score = $scored_total > 0 ? round( ( $correct / $scored_total ) * 100, 2 ) : null;
      $update = array( 'participant_status' => 'termine', 'final_score' => $final_score );
      $formats = array( '%s', '%f' );
      if ( empty( $participant->responded_at ) ) {
        $update['responded_at'] = $this->now_mysql();
        $formats[] = '%s';
      }
      if ( property_exists( $participant, 'finished_at' ) && empty( $participant->finished_at ) ) {
        $update['finished_at'] = $this->now_mysql();
        $formats[] = '%s';
      }
      $wpdb->update( $this->questionnaire_participant_table, $update, array( 'id' => (int) $participant->id ), $formats, array( '%d' ) );
    }
  }


  private function move_questionnaire_session_question_index( $delta, $nonce_action ) {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    $session_id = isset( $_POST['questionnaire_session_id'] ) ? absint( wp_unslash( $_POST['questionnaire_session_id'] ) ) : 0;
    check_admin_referer( $nonce_action . '_' . $session_id );
    global $wpdb;
    $session = $this->get_questionnaire_session( $session_id );
    if ( ! $session ) { wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-questionnaire-sessions' ) ); exit; }
    $source = $this->get_questionnaire_source_data( $session->source_type, $session->source_id );
    $max_index = max( 0, count( $source ? $source['questions'] : array() ) - 1 );
    $new_index = max( 0, min( $max_index, (int) $session->current_question_index + (int) $delta ) );
    $wpdb->update( $this->questionnaire_session_table, array( 'current_question_index' => $new_index, 'status' => 'en_cours', 'updated_at' => $this->now_mysql() ), array( 'id' => $session_id ) );
    $redirect_args = array( 'action' => 'animate', 'item_id' => $session_id );
    if ( ! empty( $_POST['source_type'] ) ) { $redirect_args['source_type'] = sanitize_text_field( wp_unslash( $_POST['source_type'] ) ); }
    if ( ! empty( $_POST['source_id'] ) ) { $redirect_args['source_id'] = absint( wp_unslash( $_POST['source_id'] ) ); }
    wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php?page=acdc-of-questionnaire-sessions' ) ) ); exit;
  }


  public function export_questionnaire_session_results_csv() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    $session_id = isset( $_GET['questionnaire_session_id'] ) ? absint( wp_unslash( $_GET['questionnaire_session_id'] ) ) : 0;
    check_admin_referer( 'acdc_export_questionnaire_session_results_csv_' . $session_id );
    $session = $this->get_questionnaire_session( $session_id );
    if ( ! $session ) { wp_die( esc_html( 'Session introuvable.' ) ); }
    $participants = $this->get_questionnaire_session_participants( $session_id );
    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=session-questionnaire-' . $session_id . '.csv' );
    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, array( 'Session', 'Participant', 'Pseudo', 'Score' ), ';' );
    foreach ( $participants as $participant ) {
      fputcsv( $out, array( $session->session_title, trim( ( $participant->first_name ?? '' ) . ' ' . ( $participant->last_name ?? '' ) ), $participant->pseudo, $this->get_questionnaire_session_participant_score( $session_id, $participant->id ) ), ';' );
    }
    fclose( $out );
    exit;
  }


  private function get_questionnaire_session_answer_map_for_participant( $session_id, $participant_id ) {
    global $wpdb;
    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT * FROM {$this->questionnaire_answer_table} WHERE session_id = %d AND participant_id = %d ORDER BY question_index ASC, id ASC",
        absint( $session_id ),
        absint( $participant_id )
      )
    );
    $map = array();
    foreach ( (array) $rows as $row ) {
      $map[ (int) $row->question_index ] = $row;
    }
    return $map;
  }


  private function is_questionnaire_session_expired( $session ) {
    if ( ! $session || empty( $session->deadline_at ) ) {
      return false;
    }
    $deadline = strtotime( (string) $session->deadline_at );
    if ( ! $deadline ) {
      return false;
    }
    return $deadline < current_time( 'timestamp' );
  }


  private function get_public_survey_redirect_url( $session_token, $participant_token = '', $notice = '', $type = 'info' ) {
    $args = array( 'token' => rawurlencode( (string) $session_token ) );
    if ( '' !== (string) $participant_token ) {
      $args['participant'] = rawurlencode( (string) $participant_token );
    }
    if ( '' !== (string) $notice ) {
      $args['q_notice'] = rawurlencode( (string) $notice );
      $args['q_notice_type'] = sanitize_key( (string) $type );
    }
    return add_query_arg( $args, $this->get_questionnaire_public_base_url() );
  }


  private function maybe_mark_survey_session_completed( $session_id ) {
    global $wpdb;
    $session_id = absint( $session_id );
    if ( ! $session_id ) {
      return;
    }
    $remaining = (int) $wpdb->get_var(
      $wpdb->prepare(
        "SELECT COUNT(*) FROM {$this->questionnaire_participant_table} WHERE session_id = %d AND participant_status NOT IN ('repondu','termine')",
        $session_id
      )
    );
    $status = $remaining > 0 ? 'partielle' : 'repondue';
    $wpdb->update(
      $this->questionnaire_session_table,
      array(
        'status' => $status,
        'updated_at' => $this->now_mysql(),
      ),
      array( 'id' => $session_id ),
      array( '%s', '%s' ),
      array( '%d' )
    );

    // ACDC 3.21.62 — Recalcul automatique des taux de la formation après chaque réponse d'enquête
    $session_row = $wpdb->get_row( $wpdb->prepare( "SELECT formation_id, is_survey_session FROM {$this->questionnaire_session_table} WHERE id = %d", $session_id ) );
    if ( $session_row && ! empty( $session_row->is_survey_session ) && ! empty( $session_row->formation_id ) ) {
      $formation_id = (int) $session_row->formation_id;
      $this->recalculate_formation_taux( $formation_id );
      if ( method_exists( $this, 'push_indicators_to_manager' ) ) {
        $this->push_indicators_to_manager( $formation_id );
      }
    }
  }


  /**
   * ACDC 3.21.62 — Calcule les taux réels depuis les réponses d'enquêtes en BDD
   * et met à jour formation_table (taux_satisfaction, taux_recommandation, taux_reussite).
   *
   * Règles de calcul :
   * - taux_satisfaction  : moyenne des numeric_score (type notation, échelle /5)
   *                        des enquêtes hot_survey, mid_survey, cold_survey → converti en %
   * - taux_recommandation: idem sur enquêtes company_survey, funder_survey
   * - taux_reussite      : % participants ayant final_score >= seuil (70 %) dans les
   *                        sessions d'évaluation (source_type = 'evaluation') liées à la formation
   *
   * @param int $formation_id  ID formation dans formation_table
   */
  private function recalculate_formation_taux( $formation_id ) {
    global $wpdb;
    $formation_id = absint( $formation_id );
    if ( ! $formation_id ) {
      return;
    }

    // --- taux_satisfaction : enquêtes apprenants (hot, mid, cold) ---
    $sat_source_types = "'hot_survey','mid_survey','cold_survey'";
    $sat_row = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT AVG(a.numeric_score) AS avg_score, COUNT(a.id) AS nb
         FROM {$this->questionnaire_answer_table} a
         INNER JOIN {$this->questionnaire_session_table} s ON s.id = a.session_id
         WHERE s.formation_id = %d
           AND s.is_survey_session = 1
           AND s.source_type IN ({$sat_source_types})
           AND a.answer_type = 'notation'
           AND a.numeric_score IS NOT NULL
           AND a.numeric_score > 0",
        $formation_id
      )
    );
    $taux_satisfaction = 0;
    if ( $sat_row && $sat_row->nb > 0 && $sat_row->avg_score > 0 ) {
      // Échelle /5 → %
      $taux_satisfaction = (int) round( ( (float) $sat_row->avg_score / 5.0 ) * 100 );
      $taux_satisfaction = max( 0, min( 100, $taux_satisfaction ) );
    }

    // --- taux_recommandation : enquêtes entreprises et financeurs ---
    $rec_source_types = "'company_survey','funder_survey'";
    $rec_row = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT AVG(a.numeric_score) AS avg_score, COUNT(a.id) AS nb
         FROM {$this->questionnaire_answer_table} a
         INNER JOIN {$this->questionnaire_session_table} s ON s.id = a.session_id
         WHERE s.formation_id = %d
           AND s.is_survey_session = 1
           AND s.source_type IN ({$rec_source_types})
           AND a.answer_type = 'notation'
           AND a.numeric_score IS NOT NULL
           AND a.numeric_score > 0",
        $formation_id
      )
    );
    $taux_recommandation = 0;
    if ( $rec_row && $rec_row->nb > 0 && $rec_row->avg_score > 0 ) {
      $taux_recommandation = (int) round( ( (float) $rec_row->avg_score / 5.0 ) * 100 );
      $taux_recommandation = max( 0, min( 100, $taux_recommandation ) );
    }

    // --- taux_reussite : évaluations des acquis liées à la formation ---
    // Chercher les sessions d'évaluation liées à la formation (via session_table)
    $eval_sessions = $wpdb->get_col(
      $wpdb->prepare(
        "SELECT qs.id FROM {$this->questionnaire_session_table} qs WHERE qs.formation_id = %d AND qs.source_type = 'evaluation'",
        $formation_id
      )
    );
    $taux_reussite = 0;
    if ( ! empty( $eval_sessions ) ) {
      $ids_placeholders = implode( ',', array_map( 'intval', $eval_sessions ) );
      $eval_totals = $wpdb->get_row(
        "SELECT COUNT(*) AS total,
                SUM( CASE WHEN final_score IS NOT NULL AND final_score >= 70 THEN 1 ELSE 0 END ) AS reussi
         FROM {$this->questionnaire_participant_table}
         WHERE session_id IN ({$ids_placeholders})
           AND responded_at IS NOT NULL"
      );
      if ( $eval_totals && (int) $eval_totals->total > 0 ) {
        $taux_reussite = (int) round( ( (float) $eval_totals->reussi / (float) $eval_totals->total ) * 100 );
        $taux_reussite = max( 0, min( 100, $taux_reussite ) );
      }
    }

    // Mise à jour uniquement si au moins un taux est non nul (évite d'écraser les saisies manuelles si aucune enquête)
    $update_fields = array();
    $update_formats = array();
    if ( $taux_satisfaction > 0 ) {
      $update_fields['taux_satisfaction'] = $taux_satisfaction;
      $update_formats[] = '%d';
    }
    if ( $taux_recommandation > 0 ) {
      $update_fields['taux_recommandation'] = $taux_recommandation;
      $update_formats[] = '%d';
    }
    if ( $taux_reussite > 0 ) {
      $update_fields['taux_reussite'] = $taux_reussite;
      $update_formats[] = '%d';
    }
    if ( ! empty( $update_fields ) ) {
      $wpdb->update(
        $this->formation_table,
        $update_fields,
        array( 'id' => $formation_id ),
        $update_formats,
        array( '%d' )
      );
    }
  }


  private function get_questionnaire_session_participant_display_name( $participant ) {
    if ( ! empty( $participant->full_name ) ) {
      return trim( (string) $participant->full_name );
    }
    $name = trim( (string) ( $participant->first_name ?? '' ) . ' ' . (string) ( $participant->last_name ?? '' ) );
    if ( $name ) {
      return $name;
    }
    if ( ! empty( $participant->pseudo ) ) {
      return (string) $participant->pseudo;
    }
    return 'Participant';
  }


  private function get_questionnaire_participant_answers( $participant_id ) {
    global $wpdb;
    $participant = $participant_id ? $this->get_questionnaire_session_participant( $participant_id ) : null;
    if ( ! $participant ) {
      return array();
    }
    return $wpdb->get_results(
      $wpdb->prepare(
        "SELECT * FROM {$this->questionnaire_answer_table} WHERE participant_id = %d ORDER BY question_index ASC, id ASC",
        absint( $participant_id )
      )
    );
  }


  private function get_questionnaire_participant_contact_email( $participant ) {
    if ( ! empty( $participant->email ) && is_email( $participant->email ) ) {
      return sanitize_email( $participant->email );
    }
    if ( ! empty( $participant->apprenant_id ) ) {
      $learner = $this->get_learner( (int) $participant->apprenant_id );
      if ( $learner && ! empty( $learner->email ) && is_email( $learner->email ) ) {
        return sanitize_email( $learner->email );
      }
    }
    return '';
  }


  private function build_questionnaire_results_export_rows( $session_id ) {
    $rows = array();
    $session = $this->get_questionnaire_session( $session_id );
    if ( ! $session ) {
      return $rows;
    }
    $participants = $this->get_questionnaire_session_participants( $session_id );
    foreach ( (array) $participants as $participant ) {
      $rows[] = array(
        'session' => (string) $session->session_title,
        'participant' => $this->get_questionnaire_session_participant_display_name( $participant ),
        'email' => $this->get_questionnaire_participant_contact_email( $participant ),
        'pseudo' => isset( $participant->pseudo ) ? (string) $participant->pseudo : '',
        'status' => isset( $participant->participant_status ) ? (string) $participant->participant_status : '',
        'joined_at' => isset( $participant->joined_at ) ? (string) $participant->joined_at : '',
        'opened_at' => isset( $participant->opened_at ) ? (string) $participant->opened_at : '',
        'started_at' => isset( $participant->started_at ) ? (string) $participant->started_at : '',
        'responded_at' => isset( $participant->responded_at ) ? (string) $participant->responded_at : ( isset( $participant->finished_at ) ? (string) $participant->finished_at : '' ),
        'reminder_count' => isset( $participant->reminder_count ) ? (int) $participant->reminder_count : 0,
        'final_score' => isset( $participant->final_score ) && '' !== (string) $participant->final_score ? $participant->final_score : $this->get_questionnaire_session_participant_score( $session_id, $participant->id ),
        'alert_flag' => ! empty( $participant->alert_flag ) ? 'oui' : 'non',
      );
    }
    return $rows;
  }


  public function export_questionnaire_session_results_excel() {
    $this->require_manage_options();
    $session_id = isset( $_GET['questionnaire_session_id'] ) ? absint( wp_unslash( $_GET['questionnaire_session_id'] ) ) : 0;
    $this->verify_nonce_or_die( 'acdc_export_questionnaire_session_results_excel_' . $session_id );
    $session = $this->get_questionnaire_session( $session_id );
    if ( ! $session ) {
      wp_die( esc_html( 'Session introuvable.' ) );
    }
    $rows = $this->build_questionnaire_results_export_rows( $session_id );
    nocache_headers();
    header( 'Content-Type: application/vnd.ms-excel; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=session-questionnaire-' . $session_id . '.xls' );
    echo "<table border='1'><thead><tr>";
    foreach ( array( 'Session', 'Participant', 'E-mail', 'Pseudo', 'Statut', 'Date de participation', 'Date ouverture', 'Date début', 'Date réponse', 'Relances', 'Score final', 'Alerte' ) as $header ) {
      echo '<th>' . esc_html( $header ) . '</th>';
    }
    echo "</tr></thead><tbody>";
    foreach ( $rows as $row ) {
      echo '<tr>';
      foreach ( array( 'session', 'participant', 'email', 'pseudo', 'status', 'joined_at', 'opened_at', 'started_at', 'responded_at', 'reminder_count', 'final_score', 'alert_flag' ) as $key ) {
        echo '<td>' . esc_html( (string) ( $row[ $key ] ?? '' ) ) . '</td>';
      }
      echo '</tr>';
    }
    echo '</tbody></table>';
    exit;
  }


  public function export_questionnaire_session_results_pdf() {
    $this->require_manage_options();
    $session_id = isset( $_GET['questionnaire_session_id'] ) ? absint( wp_unslash( $_GET['questionnaire_session_id'] ) ) : 0;
    $this->verify_nonce_or_die( 'acdc_export_questionnaire_session_results_pdf_' . $session_id );
    $session = $this->get_questionnaire_session( $session_id );
    if ( ! $session ) {
      wp_die( esc_html( 'Session introuvable.' ) );
    }
    $rows = $this->build_questionnaire_results_export_rows( $session_id );
    nocache_headers();
    header( 'Content-Type: text/html; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename=session-questionnaire-' . $session_id . '-print.html' );
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . esc_html( $session->session_title ) . '</title>';
    echo '<style>body{font-family:Arial,sans-serif;padding:20px;color:#1f2937}table{width:100%;border-collapse:collapse;margin-top:16px}th,td{border:1px solid #d1d5db;padding:8px;text-align:left}th{background:#f3f4f6}h1{font-size:22px;margin:0 0 12px}p{margin:0 0 6px}</style>';
    echo '</head><body>';
    echo '<h1>' . esc_html( $session->session_title ) . '</h1>';
    echo '<p>Export de résultats de session questionnaire</p>';
    echo '<p>Généré le ' . esc_html( mysql2date( 'd/m/Y H:i', $this->now_mysql() ) ) . '</p>';
    echo '<table><thead><tr>';
    foreach ( array( 'Participant', 'E-mail', 'Pseudo', 'Statut', 'Réponse', 'Relances', 'Score final', 'Alerte' ) as $header ) {
      echo '<th>' . esc_html( $header ) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ( $rows as $row ) {
      echo '<tr>';
      foreach ( array( 'participant', 'email', 'pseudo', 'status', 'responded_at', 'reminder_count', 'final_score', 'alert_flag' ) as $key ) {
        echo '<td>' . esc_html( (string) ( $row[ $key ] ?? '' ) ) . '</td>';
      }
      echo '</tr>';
    }
    echo '</tbody></table></body></html>';
    exit;
  }


  public function export_questionnaire_context_results_csv() {
    $this->require_manage_options();
    $source_type = isset( $_GET['source_type'] ) ? sanitize_text_field( wp_unslash( $_GET['source_type'] ) ) : '';
    $source_id   = isset( $_GET['source_id'] ) ? absint( wp_unslash( $_GET['source_id'] ) ) : 0;
    $this->verify_nonce_or_die( 'acdc_export_questionnaire_context_results_csv_' . $source_type . '_' . $source_id );
    $rows = $this->get_questionnaire_context_export_rows( array_filter( array( 'source_type' => $source_type, 'source_id' => $source_id ) ) );
    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=resultats-questionnaires-' . sanitize_file_name( $source_type ? $source_type : 'tous' ) . '.csv' );
    $out = fopen( 'php://output', 'w' );
    fputcsv( $out, array( 'Type', 'Questionnaire', 'Session', 'Participant', 'E-mail', 'Pseudo', 'Statut', 'Date ouverture', 'Date réponse', 'Relances', 'Score final', 'Alerte' ), ';' );
    foreach ( $rows as $row ) {
      fputcsv( $out, array( $row['source_type'] ?? '', $row['source_title'] ?? '', $row['session'] ?? '', $row['participant'] ?? '', $row['email'] ?? '', $row['pseudo'] ?? '', $row['status'] ?? '', $row['opened_at'] ?? '', $row['responded_at'] ?? '', $row['reminder_count'] ?? 0, $row['final_score'] ?? '', $row['alert_flag'] ?? '' ), ';' );
    }
    fclose( $out );
    exit;
  }


  public function export_questionnaire_context_results_excel() {
    $this->require_manage_options();
    $source_type = isset( $_GET['source_type'] ) ? sanitize_text_field( wp_unslash( $_GET['source_type'] ) ) : '';
    $source_id   = isset( $_GET['source_id'] ) ? absint( wp_unslash( $_GET['source_id'] ) ) : 0;
    $this->verify_nonce_or_die( 'acdc_export_questionnaire_context_results_excel_' . $source_type . '_' . $source_id );
    $rows = $this->get_questionnaire_context_export_rows( array_filter( array( 'source_type' => $source_type, 'source_id' => $source_id ) ) );
    nocache_headers();
    header( 'Content-Type: application/vnd.ms-excel; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=resultats-questionnaires-' . sanitize_file_name( $source_type ? $source_type : 'tous' ) . '.xls' );
    echo "<table border='1'><thead><tr>";
    foreach ( array( 'Type', 'Questionnaire', 'Session', 'Participant', 'E-mail', 'Pseudo', 'Statut', 'Date ouverture', 'Date réponse', 'Relances', 'Score final', 'Alerte' ) as $header ) {
      echo '<th>' . esc_html( $header ) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ( $rows as $row ) {
      echo '<tr>';
      foreach ( array( 'source_type', 'source_title', 'session', 'participant', 'email', 'pseudo', 'status', 'opened_at', 'responded_at', 'reminder_count', 'final_score', 'alert_flag' ) as $key ) {
        echo '<td>' . esc_html( (string) ( $row[ $key ] ?? '' ) ) . '</td>';
      }
      echo '</tr>';
    }
    echo '</tbody></table>';
    exit;
  }


  public function export_questionnaire_context_results_pdf() {
    $this->require_manage_options();
    $source_type = isset( $_GET['source_type'] ) ? sanitize_text_field( wp_unslash( $_GET['source_type'] ) ) : '';
    $source_id   = isset( $_GET['source_id'] ) ? absint( wp_unslash( $_GET['source_id'] ) ) : 0;
    $this->verify_nonce_or_die( 'acdc_export_questionnaire_context_results_pdf_' . $source_type . '_' . $source_id );
    $rows = $this->get_questionnaire_context_export_rows( array_filter( array( 'source_type' => $source_type, 'source_id' => $source_id ) ) );
    $stats = $this->get_questionnaire_session_stats( array_filter( array( 'source_type' => $source_type, 'source_id' => $source_id ) ) );
    nocache_headers();
    header( 'Content-Type: text/html; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename=resultats-questionnaires-' . sanitize_file_name( $source_type ? $source_type : 'tous' ) . '-print.html' );
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Résultats questionnaires</title>';
    echo '<style>body{font-family:Arial,sans-serif;padding:20px;color:#1f2937}table{width:100%;border-collapse:collapse;margin-top:16px}th,td{border:1px solid #d1d5db;padding:8px;text-align:left}th{background:#f3f4f6}h1{font-size:22px;margin:0 0 12px}.stats{display:flex;gap:12px;flex-wrap:wrap}.card{border:1px solid #d1d5db;padding:12px 14px;border-radius:10px;background:#fff}</style>';
    echo '</head><body>';
    echo '<h1>Résultats questionnaires</h1>';
    echo '<div class="stats">';
    echo '<div class="card"><strong>Sessions :</strong> ' . esc_html( $stats['total'] ) . '</div>';
    echo '<div class="card"><strong>Participants :</strong> ' . esc_html( $stats['participants'] ) . '</div>';
    echo '<div class="card"><strong>Réponses :</strong> ' . esc_html( $stats['responses'] ) . '</div>';
    echo '<div class="card"><strong>Taux de réponse :</strong> ' . esc_html( null !== $stats['response_rate'] ? $stats['response_rate'] . ' %' : '—' ) . '</div>';
    echo '<div class="card"><strong>Score moyen :</strong> ' . esc_html( null !== $stats['average_score'] ? $stats['average_score'] : '—' ) . '</div>';
    echo '</div>';
    echo '<table><thead><tr>';
    foreach ( array( 'Type', 'Questionnaire', 'Session', 'Participant', 'E-mail', 'Pseudo', 'Statut', 'Date réponse', 'Relances', 'Score final', 'Alerte' ) as $header ) {
      echo '<th>' . esc_html( $header ) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ( $rows as $row ) {
      echo '<tr>';
      foreach ( array( 'source_type', 'source_title', 'session', 'participant', 'email', 'pseudo', 'status', 'responded_at', 'reminder_count', 'final_score', 'alert_flag' ) as $key ) {
        echo '<td>' . esc_html( (string) ( $row[ $key ] ?? '' ) ) . '</td>';
      }
      echo '</tr>';
    }
    echo '</tbody></table></body></html>';
    exit;
  }


  private function log_questionnaire_event( $data ) {
    /* ACDC 3.25.299 — LE PONT VERS LE JOURNAL QUE L'EXPLOITANT PEUT OUVRIR.
       Sept journaux coexistaient dans ce plugin ; l'écran « Journal des actions »
       n'en montrait qu'un. La traçabilité existait donc en morceaux, et l'écran
       qui prétendait la donner en montrait un septième — sans le dire. Chaque
       journal garde son usage propre ; il verse en plus au journal commun. */
    if ( method_exists( $this, 'log_action_event' ) && is_array( $data ) ) {
      $this->log_action_event( 'questionnaire_' . (string) ( $data['event_type'] ?? 'evenement' ), 'questionnaire', (int) ( $data['session_id'] ?? 0 ), 'success', $data );
    }

    global $wpdb;
    if ( empty( $this->questionnaire_log_table ) ) {
      return false;
    }
    $payload = array(
      'questionnaire_session_id' => isset( $data['questionnaire_session_id'] ) ? absint( $data['questionnaire_session_id'] ) : null,
      'participant_id' => isset( $data['participant_id'] ) ? absint( $data['participant_id'] ) : null,
      'event_type' => isset( $data['event_type'] ) ? sanitize_key( $data['event_type'] ) : 'event',
      'event_label' => isset( $data['event_label'] ) ? sanitize_text_field( $data['event_label'] ) : 'Événement',
      'event_payload' => isset( $data['event_payload'] ) ? wp_json_encode( $data['event_payload'] ) : null,
      'created_by' => isset( $data['created_by'] ) ? absint( $data['created_by'] ) : get_current_user_id(),
      'created_at' => $this->now_mysql(),
    );
    $wpdb->insert(
      $this->questionnaire_log_table,
      $payload,
      array( '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
    );
    return (int) $wpdb->insert_id;
  }


  private function get_mid_survey_automation_candidate_sessions() {
    global $wpdb;
    if ( empty( $this->session_table ) || empty( $this->formation_table ) ) {
      return array();
    }
    return $wpdb->get_results( "SELECT s.*, f.title AS formation_title, f.intermediate_survey_enabled FROM {$this->session_table} s LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id WHERE s.is_draft = 0 AND COALESCE(f.intermediate_survey_enabled,0) = 1 AND ( s.start_at IS NOT NULL OR s.start_date IS NOT NULL ) AND ( s.end_at IS NOT NULL OR s.end_date IS NOT NULL ) AND COALESCE(s.status,'') NOT IN ('Annulée','Annulee','Brouillon') ORDER BY COALESCE(s.start_at, CONCAT(s.start_date,' 08:00:00')) ASC, s.id ASC" );
  }

  private function get_mid_survey_session_window( $training_session ) {
    $start_ref = '';
    $end_ref = '';
    if ( $training_session ) {
      $start_ref = ! empty( $training_session->start_at ) ? (string) $training_session->start_at : ( ! empty( $training_session->start_date ) ? (string) $training_session->start_date . ' 08:00:00' : '' );
      $end_ref   = ! empty( $training_session->end_at ) ? (string) $training_session->end_at : ( ! empty( $training_session->end_date ) ? (string) $training_session->end_date . ' 17:00:00' : '' );
    }
    $start_ts = $start_ref ? strtotime( $start_ref ) : 0;
    $end_ts   = $end_ref ? strtotime( $end_ref ) : 0;
    if ( ! $start_ts || ! $end_ts || $end_ts < $start_ts ) {
      return array();
    }
    return array(
      'start_ref' => $start_ref,
      'end_ref'   => $end_ref,
      'start_ts'  => $start_ts,
      'end_ts'    => $end_ts,
    );
  }

  private function compute_mid_survey_automation_trigger_at( $training_session, $survey_settings ) {
    $window = $this->get_mid_survey_session_window( $training_session );
    if ( empty( $window ) ) {
      return '';
    }
    $trigger_rule = isset( $survey_settings['trigger_rule'] ) ? sanitize_key( (string) $survey_settings['trigger_rule'] ) : 'midpoint';
    $engine_mid = method_exists( $this, 'get_survey_engine_type_settings' ) ? $this->get_survey_engine_type_settings( 'mid' ) : array();
    $delay_days = isset( $engine_mid['trigger_delay_days'] ) ? absint( $engine_mid['trigger_delay_days'] ) : 0;
    $trigger_ts = 0;
    switch ( $trigger_rule ) {
      case 'days_after_start':
        $trigger_ts = strtotime( '+' . max( 0, $delay_days ) . ' days', (int) $window['start_ts'] );
        break;
      case 'hours_percent':
        $duration = max( 1, (int) $window['end_ts'] - (int) $window['start_ts'] );
        $trigger_ts = (int) $window['start_ts'] + (int) floor( $duration * 0.5 );
        break;
      case 'manual':
        return '';
      case 'midpoint':
      default:
        $trigger_ts = (int) $window['start_ts'] + (int) floor( ( (int) $window['end_ts'] - (int) $window['start_ts'] ) / 2 );
        break;
    }
    if ( ! $trigger_ts ) {
      return '';
    }
    return gmdate( 'Y-m-d H:i:s', $trigger_ts + (int) ( get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) );
  }

  private function has_existing_mid_survey_automated_session( $survey_id, $training_session_id ) {
    global $wpdb;
    if ( empty( $this->questionnaire_session_table ) ) {
      return false;
    }
    $found = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->questionnaire_session_table} WHERE source_type = %s AND source_id = %d AND seance_id = %d LIMIT 1", 'mid_survey', absint( $survey_id ), absint( $training_session_id ) ) );
    return ! empty( $found );
  }

  private function create_mid_survey_automated_session( $survey, $training_session, $survey_settings, $scheduled_at ) {
    global $wpdb;
    if ( empty( $scheduled_at ) || empty( $survey ) || empty( $training_session ) ) {
      return 0;
    }
    $session_title = trim( (string) ( $survey->title ?? 'Enquête intermédiaire' ) . ' — ' . (string) ( $training_session->title ?? 'Session' ) );
    $token = $this->generate_questionnaire_session_token();
    $public_url = $this->build_questionnaire_session_public_url( $token );
    $settings_payload = array(
      'automation_origin' => 'mid_survey_rule',
      'automation_rule' => isset( $survey_settings['trigger_rule'] ) ? (string) $survey_settings['trigger_rule'] : 'midpoint',
      'automation_scheduled_from' => $scheduled_at,
      'automation_session_label' => (string) ( $training_session->title ?? '' ),
      'survey_trigger_mode' => isset( $survey_settings['trigger_mode'] ) ? (string) $survey_settings['trigger_mode'] : 'manual_auto',
      'survey_trigger_rule' => isset( $survey_settings['trigger_rule'] ) ? (string) $survey_settings['trigger_rule'] : 'midpoint',
      'survey_deadline_days' => isset( $survey_settings['deadline_days'] ) ? absint( $survey_settings['deadline_days'] ) : 15,
      'survey_internal_comment' => isset( $survey_settings['internal_comment'] ) ? (string) $survey_settings['internal_comment'] : '',
      'reminder_days' => isset( $survey_settings['reminder_days_array'] ) && is_array( $survey_settings['reminder_days_array'] ) && ! empty( $survey_settings['reminder_days_array'] ) ? array_values( $survey_settings['reminder_days_array'] ) : array( 5, 10, 15 ),
      'target_learner_ids' => array(),
    );
    $deadline_days = max( 1, absint( $settings_payload['survey_deadline_days'] ) );
    $deadline_at = gmdate( 'Y-m-d H:i:s', strtotime( '+' . $deadline_days . ' days', strtotime( $scheduled_at ) ) + (int) ( get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) );
    $data = array(
      'source_type' => 'mid_survey',
      'source_id' => absint( $survey->id ?? 0 ),
      'survey_type' => 'mid_survey',
      'survey_subtype' => '',
      'survey_model_id' => absint( $survey->id ?? 0 ),
      'session_title' => $session_title,
      'formation_id' => ! empty( $training_session->formation_id ) ? absint( $training_session->formation_id ) : 0,
      'seance_id' => ! empty( $training_session->id ) ? absint( $training_session->id ) : 0,
      'formateur_id' => 0,
      'company_id' => ! empty( $training_session->company_id ) ? absint( $training_session->company_id ) : null,
      'company_contact_id' => null,
      'funder_id' => null,
      'funder_contact_id' => null,
      'annual_campaign_year' => null,
      'send_mode' => 'scheduled',
      'session_date' => $scheduled_at,
      'scheduled_at' => $scheduled_at,
      'deadline_at' => $deadline_at,
      'reminder_days_json' => wp_json_encode( $settings_payload['reminder_days'] ),
      'status' => 'planifiee',
      'pseudo_required' => 0,
      'pseudo_editable' => 1,
      'restrict_to_registered_learners' => 1,
      'show_final_score' => 1,
      'is_survey_session' => 1,
      'public_token' => $token,
      'public_url' => $public_url,
      'qr_code_url' => $this->generate_questionnaire_session_qrcode_url( $public_url ),
      'session_settings_json' => wp_json_encode( $settings_payload ),
      'created_at' => $this->now_mysql(),
      'updated_at' => $this->now_mysql(),
    );
    $wpdb->insert( $this->questionnaire_session_table, $data );
    $new_id = (int) $wpdb->insert_id;
    if ( $new_id > 0 ) {
      $this->log_questionnaire_event( array(
        'questionnaire_session_id' => $new_id,
        'event_type' => 'automation_created',
        'event_label' => 'Session créée automatiquement pour enquête intermédiaire',
        'event_payload' => array(
          'training_session_id' => (int) $training_session->id,
          'rule' => $settings_payload['automation_rule'],
          'scheduled_at' => $scheduled_at,
        ),
      ) );
    }
    return $new_id;
  }

  public function ensure_mid_survey_automation_sessions() {
    $models = $this->get_mid_surveys( '', true );
    $training_sessions = $this->get_mid_survey_automation_candidate_sessions();
    if ( empty( $models ) || empty( $training_sessions ) ) {
      return 0;
    }
    $created = 0;
    foreach ( (array) $models as $model ) {
      $survey = is_object( $model ) ? $model : (object) $model;
      if ( empty( $survey->id ) ) {
        continue;
      }
      $survey_settings = $this->get_survey_settings_from_source_data( array(
        'type' => 'mid_survey',
        'record' => $survey,
      ) );
      $trigger_mode = isset( $survey_settings['trigger_mode'] ) ? (string) $survey_settings['trigger_mode'] : 'manual_auto';
      if ( ! in_array( $trigger_mode, array( 'automatic', 'manual_auto' ), true ) ) {
        continue;
      }
      if ( isset( $survey_settings['is_active'] ) && '0' === (string) $survey_settings['is_active'] ) {
        continue;
      }
      foreach ( (array) $training_sessions as $training_session ) {
        if ( empty( $training_session->id ) || $this->has_existing_mid_survey_automated_session( (int) $survey->id, (int) $training_session->id ) ) {
          continue;
        }
        $scheduled_at = $this->compute_mid_survey_automation_trigger_at( $training_session, $survey_settings );
        if ( empty( $scheduled_at ) ) {
          continue;
        }
        $created += $this->create_mid_survey_automated_session( $survey, $training_session, $survey_settings, $scheduled_at ) > 0 ? 1 : 0;
      }
    }
    return $created;
  }

  private function get_mid_survey_automation_overview() {
    global $wpdb;
    $overview = array(
      'planned' => 0,
      'sent' => 0,
      'upcoming' => 0,
      'reminders_due' => 0,
      'last_trigger_at' => '',
    );
    if ( empty( $this->questionnaire_session_table ) ) {
      return $overview;
    }
    $overview['planned'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->questionnaire_session_table} WHERE source_type = 'mid_survey' AND is_survey_session = 1 AND status = 'planifiee'" );
    $overview['sent'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->questionnaire_session_table} WHERE source_type = 'mid_survey' AND is_survey_session = 1 AND status IN ('envoyee','ouverte','commencee','partielle','repondue')" );
    $overview['upcoming'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->questionnaire_session_table} WHERE source_type = %s AND is_survey_session = 1 AND status = 'planifiee' AND scheduled_at IS NOT NULL AND scheduled_at > %s", 'mid_survey', $this->now_mysql() ) );
    $overview['last_trigger_at'] = (string) $wpdb->get_var( "SELECT MAX(created_at) FROM {$this->questionnaire_session_table} WHERE source_type = 'mid_survey' AND is_survey_session = 1" );
    $participants = $wpdb->get_results( "SELECT p.reminder_count, s.dispatch_sent_at, s.session_settings_json FROM {$this->questionnaire_participant_table} p INNER JOIN {$this->questionnaire_session_table} s ON s.id = p.session_id WHERE s.source_type = 'mid_survey' AND s.is_survey_session = 1 AND s.status IN ('envoyee','ouverte','commencee','partielle') AND p.participant_status NOT IN ('repondu','termine')" );
    $now_ts = current_time( 'timestamp' );
    foreach ( (array) $participants as $participant ) {
      $settings = json_decode( (string) $participant->session_settings_json, true );
      $reminders = isset( $settings['reminder_days'] ) && is_array( $settings['reminder_days'] ) ? array_values( array_map( 'absint', $settings['reminder_days'] ) ) : array( 5, 10, 15 );
      sort( $reminders );
      $count = isset( $participant->reminder_count ) ? (int) $participant->reminder_count : 0;
      if ( ! isset( $reminders[ $count ] ) ) {
        continue;
      }
      $sent_ts = ! empty( $participant->dispatch_sent_at ) ? strtotime( (string) $participant->dispatch_sent_at ) : 0;
      if ( ! $sent_ts ) {
        continue;
      }
      $days_since = (int) floor( ( $now_ts - $sent_ts ) / DAY_IN_SECONDS );
      if ( $days_since >= (int) $reminders[ $count ] ) {
        $overview['reminders_due']++;
      }
    }
    return $overview;
  }

  private function get_hot_survey_automation_candidate_sessions() {
    global $wpdb;
    if ( empty( $this->session_table ) || empty( $this->formation_table ) ) {
      return array();
    }
    return $wpdb->get_results( "SELECT s.*, f.title AS formation_title, f.hot_survey_enabled FROM {$this->session_table} s LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id WHERE s.is_draft = 0 AND COALESCE(f.hot_survey_enabled,0) = 1 AND ( s.end_at IS NOT NULL OR s.end_date IS NOT NULL OR s.start_at IS NOT NULL OR s.start_date IS NOT NULL ) AND COALESCE(s.status,'') NOT IN ('Annulée','Annulee','Brouillon') ORDER BY COALESCE(s.end_at, CONCAT(s.end_date,' 17:00:00'), s.start_at, CONCAT(s.start_date,' 17:00:00')) ASC, s.id ASC" );
  }

  private function compute_hot_survey_automation_trigger_at( $training_session, $survey_settings ) {
    $window = $this->get_mid_survey_session_window( $training_session );
    if ( empty( $window ) ) {
      return '';
    }
    $trigger_rule = isset( $survey_settings['trigger_rule'] ) ? sanitize_key( (string) $survey_settings['trigger_rule'] ) : 'end_of_training';
    $engine_hot = method_exists( $this, 'get_survey_engine_type_settings' ) ? $this->get_survey_engine_type_settings( 'hot' ) : array();
    $delay_days = isset( $engine_hot['trigger_delay_days'] ) ? absint( $engine_hot['trigger_delay_days'] ) : 0;
    $trigger_ts = 0;
    switch ( $trigger_rule ) {
      case 'manual':
        return '';
      case 'custom_delay':
        $trigger_ts = strtotime( '+' . max( 0, $delay_days ) . ' days', (int) $window['end_ts'] );
        break;
      case 'last_session':
      case 'end_of_training':
      default:
        $trigger_ts = (int) $window['end_ts'];
        break;
    }
    if ( ! $trigger_ts ) {
      return '';
    }
    return gmdate( 'Y-m-d H:i:s', $trigger_ts + (int) ( get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) );
  }

  private function has_existing_hot_survey_automated_session( $survey_id, $training_session_id ) {
    global $wpdb;
    if ( empty( $this->questionnaire_session_table ) ) {
      return false;
    }
    $found = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->questionnaire_session_table} WHERE source_type = %s AND source_id = %d AND seance_id = %d LIMIT 1", 'hot_survey', absint( $survey_id ), absint( $training_session_id ) ) );
    return ! empty( $found );
  }

  private function create_hot_survey_automated_session( $survey, $training_session, $survey_settings, $scheduled_at ) {
    global $wpdb;
    if ( empty( $scheduled_at ) || empty( $survey ) || empty( $training_session ) ) {
      return 0;
    }
    $session_title = trim( (string) ( $survey->title ?? 'Enquête à chaud' ) . ' — ' . (string) ( $training_session->title ?? 'Session' ) );
    $token = $this->generate_questionnaire_session_token();
    $public_url = $this->build_questionnaire_session_public_url( $token );
    $settings_payload = array(
      'automation_origin' => 'hot_survey_rule',
      'automation_rule' => isset( $survey_settings['trigger_rule'] ) ? (string) $survey_settings['trigger_rule'] : 'end_of_training',
      'automation_scheduled_from' => $scheduled_at,
      'automation_session_label' => (string) ( $training_session->title ?? '' ),
      'survey_trigger_mode' => isset( $survey_settings['trigger_mode'] ) ? (string) $survey_settings['trigger_mode'] : 'manual_auto',
      'survey_trigger_rule' => isset( $survey_settings['trigger_rule'] ) ? (string) $survey_settings['trigger_rule'] : 'end_of_training',
      'survey_deadline_days' => isset( $survey_settings['deadline_days'] ) ? absint( $survey_settings['deadline_days'] ) : 15,
      'survey_internal_comment' => isset( $survey_settings['internal_comment'] ) ? (string) $survey_settings['internal_comment'] : '',
      'reminder_days' => isset( $survey_settings['reminder_days_array'] ) && is_array( $survey_settings['reminder_days_array'] ) && ! empty( $survey_settings['reminder_days_array'] ) ? array_values( $survey_settings['reminder_days_array'] ) : array( 5, 10, 15 ),
      'target_learner_ids' => array(),
    );
    $deadline_days = max( 1, absint( $settings_payload['survey_deadline_days'] ) );
    $deadline_at = gmdate( 'Y-m-d H:i:s', strtotime( '+' . $deadline_days . ' days', strtotime( $scheduled_at ) ) + (int) ( get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) );
    $data = array(
      'source_type' => 'hot_survey',
      'source_id' => absint( $survey->id ?? 0 ),
      'survey_type' => 'hot_survey',
      'survey_subtype' => '',
      'survey_model_id' => absint( $survey->id ?? 0 ),
      'session_title' => $session_title,
      'formation_id' => ! empty( $training_session->formation_id ) ? absint( $training_session->formation_id ) : 0,
      'seance_id' => ! empty( $training_session->id ) ? absint( $training_session->id ) : 0,
      'formateur_id' => 0,
      'company_id' => ! empty( $training_session->company_id ) ? absint( $training_session->company_id ) : null,
      'company_contact_id' => null,
      'funder_id' => null,
      'funder_contact_id' => null,
      'annual_campaign_year' => null,
      'send_mode' => 'scheduled',
      'session_date' => $scheduled_at,
      'scheduled_at' => $scheduled_at,
      'deadline_at' => $deadline_at,
      'reminder_days_json' => wp_json_encode( $settings_payload['reminder_days'] ),
      'status' => 'planifiee',
      'pseudo_required' => 0,
      'pseudo_editable' => 1,
      'restrict_to_registered_learners' => 1,
      'show_final_score' => 1,
      'is_survey_session' => 1,
      'public_token' => $token,
      'public_url' => $public_url,
      'qr_code_url' => $this->generate_questionnaire_session_qrcode_url( $public_url ),
      'session_settings_json' => wp_json_encode( $settings_payload ),
      'created_at' => $this->now_mysql(),
      'updated_at' => $this->now_mysql(),
    );
    $wpdb->insert( $this->questionnaire_session_table, $data );
    $new_id = (int) $wpdb->insert_id;
    if ( $new_id > 0 ) {
      $this->log_questionnaire_event( array(
        'questionnaire_session_id' => $new_id,
        'event_type' => 'automation_created',
        'event_label' => 'Session créée automatiquement pour enquête à chaud',
        'event_payload' => array(
          'training_session_id' => (int) $training_session->id,
          'rule' => $settings_payload['automation_rule'],
          'scheduled_at' => $scheduled_at,
        ),
      ) );
    }
    return $new_id;
  }

  public function ensure_hot_survey_automation_sessions() {
    $models = $this->get_hot_surveys( '', true );
    $training_sessions = $this->get_hot_survey_automation_candidate_sessions();
    if ( empty( $models ) || empty( $training_sessions ) ) {
      return 0;
    }
    $created = 0;
    foreach ( (array) $models as $model ) {
      $survey = is_object( $model ) ? $model : (object) $model;
      if ( empty( $survey->id ) ) {
        continue;
      }
      $survey_settings = $this->get_survey_settings_from_source_data( array(
        'type' => 'hot_survey',
        'record' => $survey,
      ) );
      $trigger_mode = isset( $survey_settings['trigger_mode'] ) ? (string) $survey_settings['trigger_mode'] : 'manual_auto';
      if ( ! in_array( $trigger_mode, array( 'automatic', 'manual_auto' ), true ) ) {
        continue;
      }
      if ( isset( $survey_settings['is_active'] ) && '0' === (string) $survey_settings['is_active'] ) {
        continue;
      }
      foreach ( (array) $training_sessions as $training_session ) {
        if ( empty( $training_session->id ) || $this->has_existing_hot_survey_automated_session( (int) $survey->id, (int) $training_session->id ) ) {
          continue;
        }
        $scheduled_at = $this->compute_hot_survey_automation_trigger_at( $training_session, $survey_settings );
        if ( empty( $scheduled_at ) ) {
          continue;
        }
        $created += $this->create_hot_survey_automated_session( $survey, $training_session, $survey_settings, $scheduled_at ) > 0 ? 1 : 0;
      }
    }
    return $created;
  }

  private function get_hot_survey_automation_overview() {
    global $wpdb;
    $overview = array(
      'planned' => 0,
      'sent' => 0,
      'upcoming' => 0,
      'reminders_due' => 0,
      'last_trigger_at' => '',
    );
    if ( empty( $this->questionnaire_session_table ) ) {
      return $overview;
    }
    $overview['planned'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->questionnaire_session_table} WHERE source_type = 'hot_survey' AND is_survey_session = 1 AND status = 'planifiee'" );
    $overview['sent'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->questionnaire_session_table} WHERE source_type = 'hot_survey' AND is_survey_session = 1 AND status IN ('envoyee','ouverte','commencee','partielle','repondue')" );
    $overview['upcoming'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->questionnaire_session_table} WHERE source_type = %s AND is_survey_session = 1 AND status = 'planifiee' AND scheduled_at IS NOT NULL AND scheduled_at > %s", 'hot_survey', $this->now_mysql() ) );
    $overview['last_trigger_at'] = (string) $wpdb->get_var( "SELECT MAX(created_at) FROM {$this->questionnaire_session_table} WHERE source_type = 'hot_survey' AND is_survey_session = 1" );
    $participants = $wpdb->get_results( "SELECT p.reminder_count, s.dispatch_sent_at, s.session_settings_json FROM {$this->questionnaire_participant_table} p INNER JOIN {$this->questionnaire_session_table} s ON s.id = p.session_id WHERE s.source_type = 'hot_survey' AND s.is_survey_session = 1 AND s.status IN ('envoyee','ouverte','commencee','partielle') AND p.participant_status NOT IN ('repondu','termine')" );
    $now_ts = current_time( 'timestamp' );
    foreach ( (array) $participants as $participant ) {
      $settings = json_decode( (string) $participant->session_settings_json, true );
      $reminders = isset( $settings['reminder_days'] ) && is_array( $settings['reminder_days'] ) ? array_values( array_map( 'absint', $settings['reminder_days'] ) ) : array( 5, 10, 15 );
      sort( $reminders );
      $count = isset( $participant->reminder_count ) ? (int) $participant->reminder_count : 0;
      if ( ! isset( $reminders[ $count ] ) ) {
        continue;
      }
      $sent_ts = ! empty( $participant->dispatch_sent_at ) ? strtotime( (string) $participant->dispatch_sent_at ) : 0;
      if ( ! $sent_ts ) {
        continue;
      }
      $days_since = (int) floor( ( $now_ts - $sent_ts ) / DAY_IN_SECONDS );
      if ( $days_since >= (int) $reminders[ $count ] ) {
        $overview['reminders_due']++;
      }
    }
    return $overview;
  }

  private function get_cold_survey_automation_candidate_sessions() {
    global $wpdb;
    if ( empty( $this->session_table ) || empty( $this->formation_table ) ) {
      return array();
    }
    return $wpdb->get_results( "SELECT s.*, f.title AS formation_title, f.cold_survey_enabled FROM {$this->session_table} s LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id WHERE s.is_draft = 0 AND COALESCE(f.cold_survey_enabled,0) = 1 AND ( s.end_at IS NOT NULL OR s.end_date IS NOT NULL OR s.start_at IS NOT NULL OR s.start_date IS NOT NULL ) AND COALESCE(s.status,'') NOT IN ('Annulée','Annulee','Brouillon') ORDER BY COALESCE(s.end_at, CONCAT(s.end_date,' 17:00:00'), s.start_at, CONCAT(s.start_date,' 17:00:00')) ASC, s.id ASC" );
  }

  private function compute_cold_survey_automation_trigger_at( $training_session, $survey_settings ) {
    $window = $this->get_mid_survey_session_window( $training_session );
    if ( empty( $window ) ) {
      return '';
    }
    $trigger_rule = isset( $survey_settings['trigger_rule'] ) ? sanitize_key( (string) $survey_settings['trigger_rule'] ) : 'days_after_end';
    $engine_cold = method_exists( $this, 'get_survey_engine_type_settings' ) ? $this->get_survey_engine_type_settings( 'cold' ) : array();
    $delay_days = isset( $engine_cold['trigger_delay_days'] ) ? absint( $engine_cold['trigger_delay_days'] ) : 30;
    $trigger_ts = 0;
    switch ( $trigger_rule ) {
      case 'manual':
        return '';
      case 'custom_delay':
      case 'days_after_end':
      default:
        $trigger_ts = strtotime( '+' . max( 0, $delay_days ) . ' days', (int) $window['end_ts'] );
        break;
    }
    if ( ! $trigger_ts ) {
      return '';
    }
    return gmdate( 'Y-m-d H:i:s', $trigger_ts + (int) ( get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) );
  }

  private function compute_trainer_survey_automation_trigger_at( $training_session, $survey_settings ) {
    $window = $this->get_mid_survey_session_window( $training_session );
    if ( empty( $window ) ) { return ''; }
    $engine_trainer = method_exists( $this, 'get_survey_engine_type_settings' ) ? $this->get_survey_engine_type_settings( 'trainer' ) : array();
    $delay_days = isset( $engine_trainer['trigger_delay_days'] ) ? absint( $engine_trainer['trigger_delay_days'] ) : 1;
    $trigger_ts = strtotime( '+' . max( 0, $delay_days ) . ' days', (int) $window['end_ts'] );
    if ( ! $trigger_ts ) { return ''; }
    return gmdate( 'Y-m-d H:i:s', $trigger_ts + (int) ( get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) );
  }

  private function compute_company_survey_automation_trigger_at( $training_session, $survey_settings ) {
    $window = $this->get_mid_survey_session_window( $training_session );
    if ( empty( $window ) ) { return ''; }
    $engine_company = method_exists( $this, 'get_survey_engine_type_settings' ) ? $this->get_survey_engine_type_settings( 'company' ) : array();
    $delay_days = isset( $engine_company['trigger_delay_days'] ) ? absint( $engine_company['trigger_delay_days'] ) : 30;
    $trigger_ts = strtotime( '+' . max( 0, $delay_days ) . ' days', (int) $window['end_ts'] );
    if ( ! $trigger_ts ) { return ''; }
    return gmdate( 'Y-m-d H:i:s', $trigger_ts + (int) ( get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) );
  }

  private function has_existing_cold_survey_automated_session( $survey_id, $training_session_id ) {
    global $wpdb;
    if ( empty( $this->questionnaire_session_table ) ) {
      return false;
    }
    $found = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->questionnaire_session_table} WHERE source_type = %s AND source_id = %d AND seance_id = %d LIMIT 1", 'cold_survey', absint( $survey_id ), absint( $training_session_id ) ) );
    return ! empty( $found );
  }

  private function create_cold_survey_automated_session( $survey, $training_session, $survey_settings, $scheduled_at ) {
    global $wpdb;
    if ( empty( $scheduled_at ) || empty( $survey ) || empty( $training_session ) ) {
      return 0;
    }
    $session_title = trim( (string) ( $survey->title ?? 'Enquête à froid' ) . ' — ' . (string) ( $training_session->title ?? 'Session' ) );
    $token = $this->generate_questionnaire_session_token();
    $public_url = $this->build_questionnaire_session_public_url( $token );
    $settings_payload = array(
      'automation_origin' => 'cold_survey_rule',
      'automation_rule' => isset( $survey_settings['trigger_rule'] ) ? (string) $survey_settings['trigger_rule'] : 'days_after_end',
      'automation_scheduled_from' => $scheduled_at,
      'automation_session_label' => (string) ( $training_session->title ?? '' ),
      'survey_trigger_mode' => isset( $survey_settings['trigger_mode'] ) ? (string) $survey_settings['trigger_mode'] : 'manual_auto',
      'survey_trigger_rule' => isset( $survey_settings['trigger_rule'] ) ? (string) $survey_settings['trigger_rule'] : 'days_after_end',
      'survey_deadline_days' => isset( $survey_settings['deadline_days'] ) ? absint( $survey_settings['deadline_days'] ) : 15,
      'survey_internal_comment' => isset( $survey_settings['internal_comment'] ) ? (string) $survey_settings['internal_comment'] : '',
      'reminder_days' => isset( $survey_settings['reminder_days_array'] ) && is_array( $survey_settings['reminder_days_array'] ) && ! empty( $survey_settings['reminder_days_array'] ) ? array_values( $survey_settings['reminder_days_array'] ) : array( 5, 10, 15 ),
      'target_learner_ids' => array(),
    );
    $deadline_days = max( 1, absint( $settings_payload['survey_deadline_days'] ) );
    $deadline_at = gmdate( 'Y-m-d H:i:s', strtotime( '+' . $deadline_days . ' days', strtotime( $scheduled_at ) ) + (int) ( get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) );
    $data = array(
      'source_type' => 'cold_survey',
      'source_id' => absint( $survey->id ?? 0 ),
      'survey_type' => 'cold_survey',
      'survey_subtype' => '',
      'survey_model_id' => absint( $survey->id ?? 0 ),
      'session_title' => $session_title,
      'formation_id' => ! empty( $training_session->formation_id ) ? absint( $training_session->formation_id ) : 0,
      'seance_id' => ! empty( $training_session->id ) ? absint( $training_session->id ) : 0,
      'formateur_id' => 0,
      'company_id' => ! empty( $training_session->company_id ) ? absint( $training_session->company_id ) : null,
      'company_contact_id' => null,
      'funder_id' => null,
      'funder_contact_id' => null,
      'annual_campaign_year' => null,
      'send_mode' => 'scheduled',
      'session_date' => $scheduled_at,
      'scheduled_at' => $scheduled_at,
      'deadline_at' => $deadline_at,
      'reminder_days_json' => wp_json_encode( $settings_payload['reminder_days'] ),
      'status' => 'planifiee',
      'pseudo_required' => 0,
      'pseudo_editable' => 1,
      'restrict_to_registered_learners' => 1,
      'show_final_score' => 1,
      'is_survey_session' => 1,
      'public_token' => $token,
      'public_url' => $public_url,
      'qr_code_url' => $this->generate_questionnaire_session_qrcode_url( $public_url ),
      'session_settings_json' => wp_json_encode( $settings_payload ),
      'created_at' => $this->now_mysql(),
      'updated_at' => $this->now_mysql(),
    );
    $wpdb->insert( $this->questionnaire_session_table, $data );
    $new_id = (int) $wpdb->insert_id;
    if ( $new_id > 0 ) {
      $this->log_questionnaire_event( array(
        'questionnaire_session_id' => $new_id,
        'event_type' => 'automation_created',
        'event_label' => 'Session créée automatiquement pour enquête à froid',
        'event_payload' => array(
          'training_session_id' => (int) $training_session->id,
          'rule' => $settings_payload['automation_rule'],
          'scheduled_at' => $scheduled_at,
        ),
      ) );
    }
    return $new_id;
  }

  public function ensure_cold_survey_automation_sessions() {
    $models = $this->get_cold_surveys( '', true );
    $training_sessions = $this->get_cold_survey_automation_candidate_sessions();
    if ( empty( $models ) || empty( $training_sessions ) ) {
      return 0;
    }
    $created = 0;
    foreach ( (array) $models as $model ) {
      $survey = is_object( $model ) ? $model : (object) $model;
      if ( empty( $survey->id ) ) {
        continue;
      }
      $survey_settings = $this->get_survey_settings_from_source_data( array(
        'type' => 'cold_survey',
        'record' => $survey,
      ) );
      $trigger_mode = isset( $survey_settings['trigger_mode'] ) ? (string) $survey_settings['trigger_mode'] : 'manual_auto';
      if ( ! in_array( $trigger_mode, array( 'automatic', 'manual_auto' ), true ) ) {
        continue;
      }
      if ( isset( $survey_settings['is_active'] ) && '0' === (string) $survey_settings['is_active'] ) {
        continue;
      }
      foreach ( (array) $training_sessions as $training_session ) {
        if ( empty( $training_session->id ) || $this->has_existing_cold_survey_automated_session( (int) $survey->id, (int) $training_session->id ) ) {
          continue;
        }
        $scheduled_at = $this->compute_cold_survey_automation_trigger_at( $training_session, $survey_settings );
        if ( empty( $scheduled_at ) ) {
          continue;
        }
        $created += $this->create_cold_survey_automated_session( $survey, $training_session, $survey_settings, $scheduled_at ) > 0 ? 1 : 0;
      }
    }
    return $created;
  }

  /* ─────────────────────────────────────────────────────────────────────────
     ACDC 3.21.49 — Automatisation enquêtes formateur, entreprise, financeur
     Même pattern que hot/cold/mid — filtrées par le toggle dédié sur la formation.
  ───────────────────────────────────────────────────────────────────────────── */

  private function get_trainer_survey_automation_candidate_sessions() {
    global $wpdb;
    if ( empty( $this->session_table ) || empty( $this->formation_table ) ) {
      return array();
    }
    return $wpdb->get_results( "SELECT s.*, f.title AS formation_title, f.trainer_survey_enabled FROM {$this->session_table} s LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id WHERE s.is_draft = 0 AND COALESCE(f.trainer_survey_enabled,0) = 1 AND ( s.end_at IS NOT NULL OR s.end_date IS NOT NULL OR s.start_at IS NOT NULL OR s.start_date IS NOT NULL ) AND COALESCE(s.status,'') NOT IN ('Annulée','Annulee','Brouillon') ORDER BY COALESCE(s.end_at, CONCAT(s.end_date,' 17:00:00'), s.start_at, CONCAT(s.start_date,' 17:00:00')) ASC, s.id ASC" );
  }

  private function get_company_survey_automation_candidate_sessions() {
    global $wpdb;
    if ( empty( $this->session_table ) || empty( $this->formation_table ) ) {
      return array();
    }
    return $wpdb->get_results( "SELECT s.*, f.title AS formation_title, f.company_survey_enabled FROM {$this->session_table} s LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id WHERE s.is_draft = 0 AND COALESCE(f.company_survey_enabled,0) = 1 AND ( s.end_at IS NOT NULL OR s.end_date IS NOT NULL OR s.start_at IS NOT NULL OR s.start_date IS NOT NULL ) AND COALESCE(s.status,'') NOT IN ('Annulée','Annulee','Brouillon') ORDER BY COALESCE(s.end_at, CONCAT(s.end_date,' 17:00:00'), s.start_at, CONCAT(s.start_date,' 17:00:00')) ASC, s.id ASC" );
  }

  private function get_funder_survey_automation_candidate_sessions() {
    global $wpdb;
    if ( empty( $this->session_table ) || empty( $this->formation_table ) ) {
      return array();
    }
    return $wpdb->get_results( "SELECT s.*, f.title AS formation_title, f.funder_survey_enabled FROM {$this->session_table} s LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id WHERE s.is_draft = 0 AND COALESCE(f.funder_survey_enabled,0) = 1 AND ( s.end_at IS NOT NULL OR s.end_date IS NOT NULL OR s.start_at IS NOT NULL OR s.start_date IS NOT NULL ) AND COALESCE(s.status,'') NOT IN ('Annulée','Annulee','Brouillon') ORDER BY COALESCE(s.end_at, CONCAT(s.end_date,' 17:00:00'), s.start_at, CONCAT(s.start_date,' 17:00:00')) ASC, s.id ASC" );
  }

  /**
   * ACDC 3.25.226 — UNE ENQUÊTE PAR ACTION DE FORMATION, PAS PAR DEMI-JOURNÉE.
   *
   * La recette a reçu DEUX « Enquête formateur » pour un même dossier, dès la
   * création des séances. La cause n'est pas un double envoi : c'est la
   * granularité. La déduplication se fait par SÉANCE, et une formation de deux
   * journées découpées en matin/après-midi compte quatre séances — donc quatre
   * enquêtes au même formateur, sur la même action, à quelques jours
   * d'intervalle.
   *
   * Or ces trois enquêtes-là — formateur, entreprise, financeur — portent sur
   * l'ACTION DE FORMATION, pas sur une demi-journée : personne n'évalue quatre
   * fois la même formation. On ne garde donc qu'une séance par formation, la
   * dernière, et l'on vérifie qu'aucune enquête n'existe déjà pour l'une
   * quelconque des séances de cette formation.
   *
   * Les enquêtes à chaud et à froid, elles, restent par séance : ce sont les
   * apprenants qui les remplissent, et le découpage leur est propre.
   *
   * @param object[] $sessions Séances candidates, ordonnées par fin croissante.
   * @return object[] Une séance par formation — la dernière.
   */
  private function acdc_survey_one_session_per_formation( $sessions ) {
    $kept = array();
    foreach ( (array) $sessions as $session ) {
      if ( empty( $session->id ) ) {
        continue;
      }
      /* Une séance sans formation reste traitée pour elle-même : on ne peut pas
         la regrouper avec quoi que ce soit. */
      $key = ! empty( $session->formation_id ) ? 'f' . (int) $session->formation_id : 's' . (int) $session->id;
      $kept[ $key ] = $session; // La liste est ordonnée par fin croissante : la dernière écrase.
    }
    return array_values( $kept );
  }

  /** Une enquête existe-t-elle déjà pour UNE QUELCONQUE séance de cette formation ? */
  private function acdc_survey_exists_for_formation( $source_type, $survey_id, $training_session ) {
    global $wpdb;

    if ( empty( $this->questionnaire_session_table ) ) {
      return false;
    }
    if ( empty( $training_session->formation_id ) ) {
      return false;
    }

    return ! empty( $wpdb->get_var( $wpdb->prepare(
      "SELECT q.id
         FROM {$this->questionnaire_session_table} q
         INNER JOIN {$this->session_table} s ON s.id = q.seance_id
        WHERE q.source_type = %s AND q.source_id = %d AND s.formation_id = %d
        LIMIT 1",
      (string) $source_type,
      absint( $survey_id ),
      (int) $training_session->formation_id
    ) ) );
  }

  private function has_existing_trainer_survey_automated_session( $survey_id, $training_session_id ) {
    global $wpdb;
    if ( empty( $this->questionnaire_session_table ) ) { return false; }
    return ! empty( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->questionnaire_session_table} WHERE source_type = %s AND source_id = %d AND seance_id = %d LIMIT 1", 'trainer_survey', absint( $survey_id ), absint( $training_session_id ) ) ) );
  }

  private function has_existing_company_survey_automated_session( $survey_id, $training_session_id ) {
    global $wpdb;
    if ( empty( $this->questionnaire_session_table ) ) { return false; }
    return ! empty( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->questionnaire_session_table} WHERE source_type = %s AND source_id = %d AND seance_id = %d LIMIT 1", 'company_survey', absint( $survey_id ), absint( $training_session_id ) ) ) );
  }

  private function has_existing_funder_survey_automated_session( $survey_id, $training_session_id ) {
    global $wpdb;
    if ( empty( $this->questionnaire_session_table ) ) { return false; }
    return ! empty( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->questionnaire_session_table} WHERE source_type = %s AND source_id = %d AND seance_id = %d LIMIT 1", 'funder_survey', absint( $survey_id ), absint( $training_session_id ) ) ) );
  }

  /**
   * ACDC 3.25.176 — Formateur d'une séance, pour le ciblage d'une enquête.
   *
   * @param object $training_session Séance de formation.
   *
   * @return int 0 si aucun formateur n'est rattaché.
   */
  /**
   * ACDC 3.25.176 — Répare le ciblage des sessions d'enquête déjà créées sans cible.
   *
   * Ne touche NI au statut NI aux dates : une session expirée reste expirée et
   * n'enverra rien. On se contente de renseigner le formateur et l'entreprise qui
   * auraient dû l'être, pour que le gestionnaire voie enfin ce qui était visé et
   * puisse décider lui-même d'un nouvel envoi. Relancer automatiquement six enquêtes
   * périmées enverrait du courrier à des tiers sans que personne ne l'ait demandé.
   *
   * @return int Nombre de sessions réparées.
   */
  public function acdc_backfill_survey_session_targets() {
    global $wpdb;
    /* ACDC 3.25.177 — Mêmes garde-fous que la reprise des parts de réussite : jamais
       sur une page publique, réservée à l'administration, et par petits lots. Cette
       routine était accrochée à « init », donc exécutée pour chaque visiteur. */
    if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
      return 0;
    }
    if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
      return 0;
    }
    if ( 'done' === get_option( 'acdc_of_survey_targets_backfilled', '' ) ) {
      return 0;
    }
    $rows = $wpdb->get_results(
      "SELECT id, seance_id, formation_id, formateur_id, company_id
         FROM {$this->questionnaire_session_table}
        WHERE is_survey_session = 1
          AND source_type IN ('company_survey','trainer_survey','funder_survey')
          AND ( COALESCE(formateur_id,0) = 0 OR COALESCE(company_id,0) = 0 )
        LIMIT 50"
    );
    if ( empty( $rows ) ) {
      update_option( 'acdc_of_survey_targets_backfilled', 'done', false );
      return 0;
    }
    $fixed = 0;
    foreach ( (array) $rows as $row ) {
      $seance = null;
      if ( ! empty( $row->seance_id ) ) {
        $seance = $wpdb->get_row( $wpdb->prepare(
          "SELECT * FROM {$this->session_table} WHERE id = %d LIMIT 1", absint( $row->seance_id )
        ) );
      }
      if ( ! $seance ) {
        $seance = (object) array( 'id' => 0, 'formation_id' => absint( $row->formation_id ), 'company_id' => null, 'trainer_id' => null );
      }
      $update = array();
      if ( empty( $row->formateur_id ) ) {
        $trainer_id = $this->acdc_resolve_survey_trainer_id( $seance );
        if ( $trainer_id > 0 ) { $update['formateur_id'] = $trainer_id; }
      }
      if ( empty( $row->company_id ) ) {
        $company_id = $this->acdc_resolve_survey_company_id( $seance );
        if ( ! empty( $company_id ) ) { $update['company_id'] = absint( $company_id ); }
      }
      if ( empty( $update ) ) {
        continue;
      }
      $update['updated_at'] = $this->now_mysql();
      $wpdb->update( $this->questionnaire_session_table, $update, array( 'id' => absint( $row->id ) ) );
      $fixed++;
    }
    return $fixed;
  }

  /**
   * ACDC 3.25.181 — Nom du formateur d'une session d'enquête, avec repli sur la séance.
   *
   * La colonne « Formateur » des listes d'enquêtes ne lisait que formateur_id, champ
   * qui valait ZÉRO sur toutes les sessions créées automatiquement — c'est le défaut
   * corrigé en 3.25.176. Résultat : un tiret partout, y compris sur des séances
   * pourtant animées. Corriger la création ne suffit pas, car les sessions déjà
   * enregistrées gardent leur zéro : on retombe donc sur le formateur de la séance
   * au moment de l'affichage.
   *
   * @param object $entry Ligne de session d'enquête.
   *
   * @return string Nom du formateur, ou chaîne vide.
   */
  private function acdc_survey_session_trainer_name( $entry ) {
    global $wpdb;
    $trainer_id = ! empty( $entry->formateur_id ) ? absint( $entry->formateur_id ) : 0;

    if ( $trainer_id <= 0 && ! empty( $entry->seance_id ) ) {
      $trainer_id = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT trainer_id FROM {$this->session_table} WHERE id = %d LIMIT 1",
        absint( $entry->seance_id )
      ) );
    }
    if ( $trainer_id <= 0 ) {
      return '';
    }
    $trainer = $this->get_trainer( $trainer_id );
    if ( ! $trainer ) {
      return '';
    }
    return trim( (string) $trainer->first_name . ' ' . (string) $trainer->last_name );
  }

  private function acdc_resolve_survey_trainer_id( $training_session ) {
    if ( ! empty( $training_session->trainer_id ) ) {
      return absint( $training_session->trainer_id );
    }
    return 0;
  }

  /**
   * ACDC 3.25.176 — Entreprise cliente d'une séance, pour le ciblage d'une enquête.
   *
   * La séance porte une colonne company_id, mais elle n'est presque jamais remplie :
   * l'entreprise est portée par les dossiers d'inscription. On retombe donc sur
   * l'entreprise des inscriptions non brouillonnes rattachées à cette séance, puis à
   * défaut à cette formation.
   *
   * @param object $training_session Séance de formation.
   *
   * @return int|null
   */
  /**
   * ACDC 3.25.302 — LE FINANCEUR N'ÉTAIT JAMAIS PORTÉ SUR LA SESSION D'ENQUÊTE.
   *
   * create_generic_survey_automated_session() renseignait formation_id,
   * seance_id, formateur_id, company_id — et jamais funder_id. Or la résolution
   * des destinataires d'une enquête financeur commence par « si funder_id est
   * vide, aucun destinataire ». La liste était donc vide pour TOUS les dossiers,
   * depuis toujours, et l'enquête se déclarait quand même envoyée.
   */
  private function acdc_resolve_survey_funder_id( $training_session ) {
    global $wpdb;
    if ( ! empty( $training_session->funder_id ) ) {
      return absint( $training_session->funder_id );
    }
    $seance_id = ! empty( $training_session->id ) ? absint( $training_session->id ) : 0;
    if ( $seance_id > 0 && ! empty( $this->training_registration_table ) && ! empty( $this->learner_table ) ) {
      $funder_id = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT r.funder_id
           FROM {$this->training_registration_table} r
           INNER JOIN {$this->learner_table} l ON l.id = r.learner_id
          WHERE l.session_id = %d AND r.is_draft = 0 AND r.funder_id IS NOT NULL AND r.funder_id > 0
          ORDER BY r.updated_at DESC, r.id DESC LIMIT 1",
        $seance_id
      ) );
      if ( $funder_id > 0 ) {
        return $funder_id;
      }
    }
    return 0;
  }

  /**
   * ACDC 3.25.302 — La boucle du financeur appelait le calcul de l'enquête À
   * CHAUD : un reste de copier-coller, il n'existait aucun calcul propre au
   * financeur. Sa date de départ suivait donc les réglages d'une autre enquête.
   */
  private function compute_funder_survey_automation_trigger_at( $training_session, $survey_settings ) {
    $window = $this->get_mid_survey_session_window( $training_session );
    if ( empty( $window ) ) { return ''; }
    $engine = method_exists( $this, 'get_survey_engine_type_settings' ) ? $this->get_survey_engine_type_settings( 'funder' ) : array();
    $delay_days = isset( $engine['trigger_delay_days'] ) ? absint( $engine['trigger_delay_days'] ) : 30;
    $trigger_ts = strtotime( '+' . max( 0, $delay_days ) . ' days', (int) $window['end_ts'] );
    if ( ! $trigger_ts ) { return ''; }
    return gmdate( 'Y-m-d H:i:s', $trigger_ts + (int) ( get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) );
  }

  private function acdc_resolve_survey_company_id( $training_session ) {
    global $wpdb;
    if ( ! empty( $training_session->company_id ) ) {
      return absint( $training_session->company_id );
    }
    $seance_id    = ! empty( $training_session->id ) ? absint( $training_session->id ) : 0;
    $formation_id = ! empty( $training_session->formation_id ) ? absint( $training_session->formation_id ) : 0;

    if ( $seance_id > 0 ) {
      $company_id = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT r.company_id
           FROM {$this->training_registration_table} r
           INNER JOIN {$this->learner_table} l ON l.id = r.learner_id
          WHERE l.session_id = %d AND r.is_draft = 0 AND r.company_id IS NOT NULL AND r.company_id > 0
          ORDER BY r.updated_at DESC, r.id DESC LIMIT 1",
        $seance_id
      ) );
      if ( $company_id > 0 ) {
        return $company_id;
      }
    }
    if ( $formation_id > 0 ) {
      $company_id = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT company_id FROM {$this->training_registration_table}
          WHERE formation_id = %d AND is_draft = 0 AND company_id IS NOT NULL AND company_id > 0
          ORDER BY updated_at DESC, id DESC LIMIT 1",
        $formation_id
      ) );
      if ( $company_id > 0 ) {
        return $company_id;
      }
    }
    return null;
  }

  private function create_generic_survey_automated_session( $source_type, $survey, $training_session, $survey_settings, $scheduled_at ) {
    global $wpdb;
    if ( empty( $scheduled_at ) || empty( $survey ) || empty( $training_session ) ) { return 0; }
    $session_title   = trim( (string) ( $survey->title ?? $source_type ) . ' — ' . (string) ( $training_session->title ?? 'Session' ) );
    $token           = $this->generate_questionnaire_session_token();
    $public_url      = $this->build_questionnaire_session_public_url( $token );
    $deadline_days   = isset( $survey_settings['deadline_days'] ) ? max( 1, absint( $survey_settings['deadline_days'] ) ) : 15;
    $deadline_at     = gmdate( 'Y-m-d H:i:s', strtotime( '+' . $deadline_days . ' days', strtotime( $scheduled_at ) ) + (int) ( get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) );
    $reminder_days   = isset( $survey_settings['reminder_days_array'] ) && is_array( $survey_settings['reminder_days_array'] ) ? array_values( $survey_settings['reminder_days_array'] ) : array( 5, 10, 15 );
    $settings_payload = array(
      'automation_origin'   => $source_type . '_rule',
      'automation_rule'     => isset( $survey_settings['trigger_rule'] ) ? (string) $survey_settings['trigger_rule'] : 'end_of_training',
      'reminder_days'       => $reminder_days,
      'survey_deadline_days'=> $deadline_days,
    );
    $data = array(
      'source_type'         => $source_type,
      'source_id'           => absint( $survey->id ?? 0 ),
      'survey_type'         => $source_type,
      'survey_subtype'      => '',
      'survey_model_id'     => absint( $survey->id ?? 0 ),
      'session_title'       => $session_title,
      'formation_id'        => ! empty( $training_session->formation_id ) ? absint( $training_session->formation_id ) : 0,
      'seance_id'           => ! empty( $training_session->id ) ? absint( $training_session->id ) : 0,
      /* ACDC 3.25.176 — LA CAUSE DES ENQUÊTES SANS DESTINATAIRE.
         formateur_id était écrit en dur à zéro, alors que la séance porte bien un
         trainer_id ; et company_id n'était lu que sur la séance, où il est rarement
         renseigné — c'est le DOSSIER D'INSCRIPTION qui porte l'entreprise. Les sessions
         d'enquête formateurs et entreprises naissaient donc sans cible, expiraient en
         silence sans qu'un seul courriel ne parte, et l'écran affichait pourtant un
         bandeau « Traçabilité Qualiopi : chaque envoi est horodaté ». Une preuve était
         réputée collectée alors que rien n'était jamais parti.
         Les enquêtes à chaud, à froid et intermédiaires n'étaient pas touchées : elles
         se résolvent sur la liste des apprenants inscrits, pas sur une entité tierce. */
      'formateur_id'        => $this->acdc_resolve_survey_trainer_id( $training_session ),
      'company_id'          => $this->acdc_resolve_survey_company_id( $training_session ),
      'funder_id'           => $this->acdc_resolve_survey_funder_id( $training_session ),
      'send_mode'           => 'scheduled',
      'session_date'        => $scheduled_at,
      'scheduled_at'        => $scheduled_at,
      'deadline_at'         => $deadline_at,
      'reminder_days_json'  => wp_json_encode( $reminder_days ),
      'status'              => 'planifiee',
      'pseudo_required'     => 0,
      'pseudo_editable'     => 1,
      'restrict_to_registered_learners' => 0,
      'show_final_score'    => 0,
      'is_survey_session'   => 1,
      'public_token'        => $token,
      'public_url'          => $public_url,
      'qr_code_url'         => $this->generate_questionnaire_session_qrcode_url( $public_url ),
      'session_settings_json' => wp_json_encode( $settings_payload ),
      'created_at'          => $this->now_mysql(),
      'updated_at'          => $this->now_mysql(),
    );
    $wpdb->insert( $this->questionnaire_session_table, $data );
    $new_id = (int) $wpdb->insert_id;
    if ( $new_id > 0 ) {
      $this->log_questionnaire_event( array(
        'questionnaire_session_id' => $new_id,
        'event_type'  => 'automation_created',
        'event_label' => 'Session créée automatiquement pour ' . $source_type,
        'event_payload' => array( 'training_session_id' => (int) $training_session->id, 'scheduled_at' => $scheduled_at ),
      ) );
    }
    return $new_id;
  }

  public function ensure_trainer_survey_automation_sessions() {
    $models = method_exists( $this, 'get_trainer_surveys' ) ? $this->get_trainer_surveys( '', true ) : array();
    $training_sessions = $this->acdc_survey_one_session_per_formation( $this->get_trainer_survey_automation_candidate_sessions() );
    if ( empty( $models ) || empty( $training_sessions ) ) { return 0; }
    $created = 0;
    foreach ( (array) $models as $model ) {
      $survey = is_object( $model ) ? $model : (object) $model;
      if ( empty( $survey->id ) ) { continue; }
      $survey_settings = $this->get_survey_settings_from_source_data( array( 'type' => 'trainer_survey', 'record' => $survey ) );
      $trigger_mode = isset( $survey_settings['trigger_mode'] ) ? (string) $survey_settings['trigger_mode'] : 'manual_auto';
      if ( ! in_array( $trigger_mode, array( 'automatic', 'manual_auto' ), true ) ) { continue; }
      if ( isset( $survey_settings['is_active'] ) && '0' === (string) $survey_settings['is_active'] ) { continue; }
      foreach ( (array) $training_sessions as $ts ) {
        if ( empty( $ts->id ) || $this->has_existing_trainer_survey_automated_session( (int) $survey->id, (int) $ts->id ) ) { continue; }
        if ( $this->acdc_survey_exists_for_formation( 'trainer_survey', (int) $survey->id, $ts ) ) { continue; }
        $scheduled_at = $this->compute_trainer_survey_automation_trigger_at( $ts, $survey_settings );
        if ( empty( $scheduled_at ) ) { continue; }
        $created += $this->create_generic_survey_automated_session( 'trainer_survey', $survey, $ts, $survey_settings, $scheduled_at ) > 0 ? 1 : 0;
      }
    }
    return $created;
  }

  public function ensure_company_survey_automation_sessions() {
    $models = method_exists( $this, 'get_company_surveys' ) ? $this->get_company_surveys( '', true ) : array();
    $training_sessions = $this->acdc_survey_one_session_per_formation( $this->get_company_survey_automation_candidate_sessions() );
    if ( empty( $models ) || empty( $training_sessions ) ) { return 0; }
    $created = 0;
    foreach ( (array) $models as $model ) {
      $survey = is_object( $model ) ? $model : (object) $model;
      if ( empty( $survey->id ) ) { continue; }
      $survey_settings = $this->get_survey_settings_from_source_data( array( 'type' => 'company_survey', 'record' => $survey ) );
      $trigger_mode = isset( $survey_settings['trigger_mode'] ) ? (string) $survey_settings['trigger_mode'] : 'manual_auto';
      if ( ! in_array( $trigger_mode, array( 'automatic', 'manual_auto' ), true ) ) { continue; }
      if ( isset( $survey_settings['is_active'] ) && '0' === (string) $survey_settings['is_active'] ) { continue; }
      foreach ( (array) $training_sessions as $ts ) {
        if ( empty( $ts->id ) || $this->has_existing_company_survey_automated_session( (int) $survey->id, (int) $ts->id ) ) { continue; }
        if ( $this->acdc_survey_exists_for_formation( 'company_survey', (int) $survey->id, $ts ) ) { continue; }
        $scheduled_at = $this->compute_company_survey_automation_trigger_at( $ts, $survey_settings );
        if ( empty( $scheduled_at ) ) { continue; }
        $created += $this->create_generic_survey_automated_session( 'company_survey', $survey, $ts, $survey_settings, $scheduled_at ) > 0 ? 1 : 0;
      }
    }
    return $created;
  }

  public function ensure_funder_survey_automation_sessions() {
    $models = method_exists( $this, 'get_funder_surveys' ) ? $this->get_funder_surveys( '', true ) : array();
    $training_sessions = $this->acdc_survey_one_session_per_formation( $this->get_funder_survey_automation_candidate_sessions() );
    if ( empty( $models ) || empty( $training_sessions ) ) { return 0; }
    $created = 0;
    foreach ( (array) $models as $model ) {
      $survey = is_object( $model ) ? $model : (object) $model;
      if ( empty( $survey->id ) ) { continue; }
      $survey_settings = $this->get_survey_settings_from_source_data( array( 'type' => 'funder_survey', 'record' => $survey ) );
      $trigger_mode = isset( $survey_settings['trigger_mode'] ) ? (string) $survey_settings['trigger_mode'] : 'manual_auto';
      if ( ! in_array( $trigger_mode, array( 'automatic', 'manual_auto' ), true ) ) { continue; }
      if ( isset( $survey_settings['is_active'] ) && '0' === (string) $survey_settings['is_active'] ) { continue; }
      foreach ( (array) $training_sessions as $ts ) {
        if ( empty( $ts->id ) || $this->has_existing_funder_survey_automated_session( (int) $survey->id, (int) $ts->id ) ) { continue; }
        if ( $this->acdc_survey_exists_for_formation( 'funder_survey', (int) $survey->id, $ts ) ) { continue; }
        $scheduled_at = $this->compute_funder_survey_automation_trigger_at( $ts, $survey_settings );
        if ( empty( $scheduled_at ) ) { continue; }
        $created += $this->create_generic_survey_automated_session( 'funder_survey', $survey, $ts, $survey_settings, $scheduled_at ) > 0 ? 1 : 0;
      }
    }
    return $created;
  }

  private function get_cold_survey_automation_overview() {
    global $wpdb;
    $overview = array(
      'planned' => 0,
      'sent' => 0,
      'upcoming' => 0,
      'reminders_due' => 0,
      'last_trigger_at' => '',
    );
    if ( empty( $this->questionnaire_session_table ) ) {
      return $overview;
    }
    $overview['planned'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->questionnaire_session_table} WHERE source_type = 'cold_survey' AND is_survey_session = 1 AND status = 'planifiee'" );
    $overview['sent'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->questionnaire_session_table} WHERE source_type = 'cold_survey' AND is_survey_session = 1 AND status IN ('envoyee','ouverte','commencee','partielle','repondue')" );
    $overview['upcoming'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->questionnaire_session_table} WHERE source_type = %s AND is_survey_session = 1 AND status = 'planifiee' AND scheduled_at IS NOT NULL AND scheduled_at > %s", 'cold_survey', $this->now_mysql() ) );
    $overview['last_trigger_at'] = (string) $wpdb->get_var( "SELECT MAX(created_at) FROM {$this->questionnaire_session_table} WHERE source_type = 'cold_survey' AND is_survey_session = 1" );
    $participants = $wpdb->get_results( "SELECT p.reminder_count, s.dispatch_sent_at, s.session_settings_json FROM {$this->questionnaire_participant_table} p INNER JOIN {$this->questionnaire_session_table} s ON s.id = p.session_id WHERE s.source_type = 'cold_survey' AND s.is_survey_session = 1 AND s.status IN ('envoyee','ouverte','commencee','partielle') AND p.participant_status NOT IN ('repondu','termine')" );
    $now_ts = current_time( 'timestamp' );
    foreach ( (array) $participants as $participant ) {
      $settings = json_decode( (string) $participant->session_settings_json, true );
      $reminders = isset( $settings['reminder_days'] ) && is_array( $settings['reminder_days'] ) ? array_values( array_map( 'absint', $settings['reminder_days'] ) ) : array( 5, 10, 15 );
      sort( $reminders );
      $count = isset( $participant->reminder_count ) ? (int) $participant->reminder_count : 0;
      if ( ! isset( $reminders[ $count ] ) ) {
        continue;
      }
      $sent_ts = ! empty( $participant->dispatch_sent_at ) ? strtotime( (string) $participant->dispatch_sent_at ) : 0;
      if ( ! $sent_ts ) {
        continue;
      }
      $days_since = (int) floor( ( $now_ts - $sent_ts ) / DAY_IN_SECONDS );
      if ( $days_since >= (int) $reminders[ $count ] ) {
        $overview['reminders_due']++;
      }
    }
    return $overview;
  }


  /**
   * Pourquoi cette enquête n'a trouvé personne — en toutes lettres.
   *
   * « Aucun destinataire » n'est pas une cause, c'est un constat. Un financeur
   * absent du dossier ne se corrige pas au même endroit qu'un contact sans
   * adresse : la première se règle sur la convention, la seconde sur la fiche.
   */
  private function acdc_motif_sans_destinataire( $session ) {
    $type = isset( $session->source_type ) ? (string) $session->source_type : '';
    if ( 'funder_survey' === $type ) {
      return empty( $session->funder_id )
        ? 'Aucun financeur n’est rattaché au dossier : l’enquête financeur n’a personne à qui s’adresser.'
        : 'Le financeur rattaché n’a pas d’adresse e-mail valide sur sa fiche.';
    }
    if ( 'company_survey' === $type ) {
      return empty( $session->company_id )
        ? 'Aucune entreprise n’est rattachée au dossier : l’enquête entreprise n’a personne à qui s’adresser.'
        : 'Aucun contact de cette entreprise n’a d’adresse e-mail valide.';
    }
    if ( 'trainer_survey' === $type ) {
      return 'Le formateur rattaché n’a pas d’adresse e-mail valide sur sa fiche.';
    }
    return 'Aucun apprenant de cette séance n’a d’adresse e-mail valide.';
  }

  /**
   * ACDC 3.25.312 — LES DATES D'UNE SÉANCE ONT BOUGÉ : LE PLAN EST REFAIT.
   *
   * LE DÉFAUT QU'ELLE FERME. Une échéance d'enquête est calculée UNE FOIS, à la
   * création, à partir des dates de la séance — et plus jamais relue. Le code le
   * disait sans détour : si une échéance existe déjà pour cette séance, on
   * passe. Une formation créée avec des dates de mai gardait donc des rendez-vous
   * de mai, quoi qu'il advienne ensuite de ses dates. C'est la famille que nous
   * traquons depuis le début, à l'envers : une valeur CONSERVÉE là où elle
   * devait être RECALCULÉE.
   *
   * ON NE RECALCULE PAS, ON REFAIT. Les six fabriques d'enquêtes savent déjà
   * calculer juste, chacune selon sa règle. Plutôt que de recopier ces six
   * calculs ici — et de les voir diverger un jour — on retire le plan périmé :
   * les fabriques le reconstruisent au passage suivant, avec les nouvelles
   * dates.
   *
   * CE QU'ON NE TOUCHE JAMAIS : une enquête DÉJÀ ENVOYÉE, ou créée à la main.
   * Seules disparaissent les échéances automatiques encore en attente — celles
   * qui n'ont rien produit et que personne n'a vues. La suppression est tracée,
   * comme toutes les autres.
   *
   * @param int $seance_id La séance dont les dates viennent de changer.
   * @return int Nombre d'échéances retirées.
   */
  public function acdc_replanifier_enquetes_de_seance( $seance_id ) {
    global $wpdb;
    $seance_id = (int) $seance_id;
    if ( ! $seance_id || empty( $this->questionnaire_session_table ) ) {
      return 0;
    }
    $lignes = (array) $wpdb->get_results( $wpdb->prepare(
      "SELECT id, session_settings_json FROM {$this->questionnaire_session_table}
        WHERE seance_id = %d AND is_survey_session = 1 AND status = %s",
      $seance_id,
      'planifiee'
    ) );
    $retirees = 0;
    foreach ( $lignes as $ligne ) {
      $reglages = json_decode( (string) $ligne->session_settings_json, true );
      /* Une enquête posée à la main n'a pas d'origine d'automatisation : elle
         représente une décision, et une décision ne se défait pas toute seule. */
      if ( ! is_array( $reglages ) || empty( $reglages['automation_origin'] ) ) {
        continue;
      }
      $supprime = $wpdb->delete( $this->questionnaire_session_table, array( 'id' => (int) $ligne->id ), array( '%d' ) );
      if ( $supprime ) {
        $retirees++;
        $this->log_action_event( 'enquete_replanifiee', 'questionnaire_session', (int) $ligne->id, 'success', array(
          'seance_id' => $seance_id,
          'origine'   => (string) $reglages['automation_origin'],
          'motif'     => 'dates de la séance modifiées — échéance à recalculer',
        ) );
      }
    }
    return $retirees;
  }

  /**
   * ACDC 3.25.312 — Les adresses réellement visées par une enquête.
   *
   * Sert à ne pas servir deux fois la même personne dans un même passage. On
   * normalise en minuscules : « David@… » et « david@… » sont la même boîte, et
   * une comparaison sensible à la casse laisserait passer la rafale.
   */
  private function acdc_adresses_des_cibles( $targets ) {
    $out = array();
    foreach ( (array) $targets as $cible ) {
      $adresse = '';
      if ( is_array( $cible ) ) {
        $adresse = isset( $cible['email'] ) ? (string) $cible['email'] : '';
      } elseif ( is_object( $cible ) ) {
        $adresse = isset( $cible->email ) ? (string) $cible->email : '';
      } elseif ( is_string( $cible ) ) {
        $adresse = $cible;
      }
      $adresse = strtolower( trim( $adresse ) );
      if ( '' !== $adresse ) {
        $out[] = $adresse;
      }
    }
    return array_values( array_unique( $out ) );
  }

  /**
   * ACDC 3.25.312 — Cette enquête a-t-elle trop attendu pour être crédible ?
   *
   * On mesure le retard sur la date d'envoi PRÉVUE, pas sur la date de la
   * formation : c'est bien le rendez-vous manqué qu'on juge.
   */
  private function acdc_enquete_trop_tardive( $session, $retard_max_jours ) {
    $retard_max_jours = (int) $retard_max_jours;
    if ( $retard_max_jours <= 0 || empty( $session->scheduled_at ) ) {
      return false;
    }
    $prevu = strtotime( (string) $session->scheduled_at . ' UTC' );
    $now   = strtotime( (string) $this->now_mysql() . ' UTC' );
    if ( ! $prevu || ! $now ) {
      return false;
    }
    return ( $now - $prevu ) > ( $retard_max_jours * DAY_IN_SECONDS );
  }

  /**
   * ACDC 3.25.312 — Le statut visible d'une enquête écartée pour retard.
   *
   * Elle n'est ni envoyée ni supprimée : elle attend une décision, et elle dit
   * pourquoi. Même principe que « sans destinataire » posé en 3.25.302 — un
   * dossier qu'on peut vérifier plutôt qu'un dossier faussement rassurant.
   */
  private function acdc_marquer_enquete_tardive( $session, $retard_max_jours ) {
    global $wpdb;
    $settings = $this->get_questionnaire_session_settings_array( $session );
    $jours    = (int) floor( ( strtotime( (string) $this->now_mysql() . ' UTC' ) - strtotime( (string) $session->scheduled_at . ' UTC' ) ) / DAY_IN_SECONDS );
    $settings['dispatch_motif'] = sprintf(
      'Envoi prévu le %s, soit %d jours de retard — au-delà des %d jours admis. La question posée ne correspondrait plus à ce que la personne a vécu : à vous de décider de l’envoyer ou de l’abandonner.',
      mysql2date( 'd/m/Y', (string) $session->scheduled_at ),
      $jours,
      (int) $retard_max_jours
    );
    $wpdb->update(
      $this->questionnaire_session_table,
      array(
        'status' => 'trop_tardive',
        'session_settings_json' => wp_json_encode( $settings ),
        'updated_at' => $this->now_mysql(),
      ),
      array( 'id' => (int) $session->id ),
      array( '%s', '%s', '%s' ),
      array( '%d' )
    );
    $this->log_action_event( 'enquete_trop_tardive', 'questionnaire_session', (int) $session->id, 'error', array(
      'type'  => (string) $session->source_type,
      'jours' => $jours,
    ) );
  }

  /**
   * @param int $lot              Nombre maximal d'enquêtes traitées par passage.
   * @param int $retard_max_jours Au-delà, l'enquête est signalée et non envoyée.
   */
  public function process_scheduled_survey_dispatches( $lot = 10, $retard_max_jours = 7 ) {
    $this->ensure_mid_survey_automation_sessions();
    $this->ensure_hot_survey_automation_sessions();
    $this->ensure_cold_survey_automation_sessions();
    $this->ensure_trainer_survey_automation_sessions();
    $this->ensure_company_survey_automation_sessions();
    $this->ensure_funder_survey_automation_sessions();
    global $wpdb;
    $sessions = $wpdb->get_results( $wpdb->prepare(
      /* ACDC 3.25.312 — UN PLAFOND, LÀ OÙ IL N'Y EN AVAIT AUCUN.
         Cette requête ramassait TOUT ce qui était en retard, sans limite, et la
         boucle vidait la pile d'un trait. Le 16 août, une formation dont les
         dates étaient restées en mai a fait naître six échéances déjà périmées
         de trois mois : elles sont parties ensemble, à 17h00, DOUZE MESSAGES EN
         UNE SECONDE vers trois adresses du même domaine. Authentification
         parfaite — SPF, DKIM et DMARC au vert, 9,6/10 chez mail-tester — et
         pourtant tout en indésirables. Un filtre ne juge pas un message, il juge
         un motif, et douze messages quasi identiques en une seconde EST le
         motif qu'il cherche.
         Les étapes du workflow avaient déjà un lot de 30 ; les enquêtes, rien. */
      "SELECT * FROM {$this->questionnaire_session_table} WHERE is_survey_session = %d AND status = %s AND scheduled_at IS NOT NULL AND scheduled_at <= %s ORDER BY scheduled_at ASC LIMIT %d",
      1,
      'planifiee',
      $this->now_mysql(),
      (int) $lot
    ) );
    /* Les destinataires déjà servis pendant CE passage. Vide à chaque appel. */
    $__servis = array();
    foreach ( (array) $sessions as $session ) {
      /* ACDC 3.25.312 — UNE ENQUÊTE TROP EN RETARD NE PART PAS TOUTE SEULE.
         Une enquête « à chaud » expédiée trois mois après la formation ne pose
         pas une question en retard : elle pose une question fausse. La personne
         ne se souvient plus, et le document qu'on en tire ne prouve rien.
         Elle n'est donc pas envoyée, ni perdue : elle prend un statut visible à
         l'écran, et l'exploitant décide. Un envoi qu'on choisit vaut mieux qu'un
         envoi qu'on subit. */
      if ( $this->acdc_enquete_trop_tardive( $session, $retard_max_jours ) ) {
        $this->acdc_marquer_enquete_tardive( $session, $retard_max_jours );
        continue;
      }
      $source = $this->get_questionnaire_source_data( $session->source_type, $session->source_id );
      $targets = $this->get_questionnaire_delivery_targets( $session );
      /* ACDC 3.25.312 — UN SEUL MESSAGE PAR DESTINATAIRE ET PAR PASSAGE.
         C'est la règle qui casse la rafale, et elle ne coûte rien : en marche
         normale un apprenant n'a jamais deux enquêtes dues au même instant, donc
         elle ne se déclenche jamais. Elle ne mord qu'au rattrapage — exactement
         là où il faut. L'enquête écartée reste « planifiée » et partira au
         passage suivant. */
      $__adresses = $this->acdc_adresses_des_cibles( $targets );
      if ( array_intersect( $__adresses, $__servis ) ) {
        continue;
      }
      $result = $this->send_questionnaire_session_emails( $session, $source, $targets );
      if ( (int) ( $result['sent'] ?? 0 ) > 0 ) {
        $__servis = array_merge( $__servis, $__adresses );
      }
      $settings = $this->get_questionnaire_session_settings_array( $session );
      $settings['last_email_sent_at'] = $this->now_mysql();
      $settings['last_email_sent_count'] = (int) ( $result['sent'] ?? 0 );
      $settings['last_email_failed_count'] = (int) ( $result['failed'] ?? 0 );
      $settings['last_email_target_count'] = (int) ( $result['target'] ?? 0 );
      /* ACDC 3.25.302 — « ENVOYÉE » ÉTAIT ÉCRIT QUEL QUE SOIT LE RÉSULTAT.
         Zéro destinataire, zéro e-mail parti : la ligne passait quand même à
         « envoyée », le parcours l'annonçait, et les relances s'enchaînaient sur
         ce mensonge. L'archive des e-mails, elle, restait vide — et c'est en la
         regardant que David a vu qu'aucune enquête financeur ni entreprise
         n'était jamais partie. Depuis toujours, et pour tous les dossiers.
         Une enquête qui n'a trouvé personne n'est pas envoyée : elle est SANS
         DESTINATAIRE, et elle dit pourquoi. C'est la différence entre un dossier
         qu'on peut vérifier et un dossier faussement rassurant. */
      $__acdc_partis = (int) ( $result['sent'] ?? 0 );
      $__acdc_vises  = (int) ( $result['target'] ?? 0 );
      $__acdc_statut = 'envoyee';
      $__acdc_motif  = '';
      if ( 0 === $__acdc_vises ) {
        $__acdc_statut = 'sans_destinataire';
        $__acdc_motif  = $this->acdc_motif_sans_destinataire( $session );
      } elseif ( 0 === $__acdc_partis ) {
        $__acdc_statut = 'echec_envoi';
        $__acdc_motif  = sprintf( '%d destinataire(s) visé(s), aucun e-mail n’est parti.', $__acdc_vises );
      }
      $settings['dispatch_motif'] = $__acdc_motif;

      $wpdb->update(
        $this->questionnaire_session_table,
        array(
          'status' => $__acdc_statut,
          'dispatch_sent_at' => 'envoyee' === $__acdc_statut ? $this->now_mysql() : null,
          'session_settings_json' => wp_json_encode( $settings ),
          'updated_at' => $this->now_mysql(),
        ),
        array( 'id' => (int) $session->id ),
        array( '%s', '%s', '%s', '%s' ),
        array( '%d' )
      );
      if ( 'envoyee' !== $__acdc_statut ) {
        $this->log_action_event( 'enquete_sans_destinataire', 'questionnaire_session', (int) $session->id, 'error', array(
          'type'   => (string) $session->source_type,
          'motif'  => $__acdc_motif,
          'vises'  => $__acdc_vises,
          'partis' => $__acdc_partis,
        ) );
      }
      $this->log_questionnaire_event( array(
        'questionnaire_session_id' => (int) $session->id,
        'event_type' => 'dispatch_sent',
        'event_label' => 'Envoi planifié exécuté',
      ) );
    }
  }


  /**
   * ACDC 3.25.313 — LES RELANCES N'AVAIENT REÇU AUCUN DES TROIS GARDE-FOUS.
   *
   * La 3.25.312 a fermé la rafale du 16 août — douze messages en une seconde,
   * tous tombés en indésirables malgré une authentification parfaite — en posant
   * trois règles sur l'ENVOI INITIAL des enquêtes : un lot par passage, un seul
   * message par destinataire et par passage, et un refus d'envoyer au-delà de
   * sept jours de retard. Cette fonction-ci, sa voisine de vingt lignes, n'en a
   * reçu aucune : elle ramassait toutes les enquêtes ouvertes sans limite et
   * envoyait un e-mail à chaque tour de boucle.
   *
   * C'était même la moitié la plus dangereuse : les relances sont, par nature,
   * des messages presque identiques envoyés plusieurs fois aux mêmes personnes.
   *
   * CE QUI EST VRAIMENT EN JEU n'est pas le sort des enquêtes. Un filtre juge un
   * motif, pas un message : quelques envois semblables en quelques secondes, et
   * c'est le DOMAINE qui est déclassé. Ce qui tombe ensuite en indésirables, ce
   * sont les convocations, les demandes de signature et les liens d'émargement —
   * c'est-à-dire la preuve Qualiopi.
   *
   * @param int $lot              Nombre maximal d'enquêtes examinées par passage.
   * @param int $retard_max_jours Au-delà, on ne relance plus : la question posée
   *                              ne correspondrait plus à ce que la personne a vécu.
   */
  public function process_scheduled_survey_reminders( $lot = 20, $retard_max_jours = 7 ) {
    global $wpdb;
    $sessions = $wpdb->get_results( $wpdb->prepare(
      "SELECT * FROM {$this->questionnaire_session_table}
        WHERE is_survey_session = 1
          AND status IN ('envoyee','ouverte','commencee','partielle')
        ORDER BY dispatch_sent_at ASC
        LIMIT %d",
      (int) $lot
    ) );
    /* Les destinataires déjà servis pendant CE passage. Vide à chaque appel. */
    $__servis = array();
    foreach ( (array) $sessions as $session ) {
      $settings = $this->get_questionnaire_session_settings_array( $session );
      $reminder_days = isset( $settings['reminder_days'] ) && is_array( $settings['reminder_days'] ) ? $settings['reminder_days'] : array( 5, 10, 15 );
      $sent_at = ! empty( $session->dispatch_sent_at ) ? strtotime( (string) $session->dispatch_sent_at ) : ( ! empty( $settings['last_email_sent_at'] ) ? strtotime( (string) $settings['last_email_sent_at'] ) : 0 );
      if ( ! $sent_at ) {
        continue;
      }
      $days_since = (int) floor( ( current_time( 'timestamp' ) - $sent_at ) / DAY_IN_SECONDS );
      /* Le troisième garde-fou : une enquête dont la dernière relance prévue est
         elle-même dépassée depuis plus d'une semaine ne se rattrape plus. */
      $__derniere = ! empty( $reminder_days ) ? absint( max( $reminder_days ) ) : 15;
      if ( $days_since > ( $__derniere + (int) $retard_max_jours ) ) {
        continue;
      }
      $participants = $this->get_questionnaire_session_participants( $session->id );
      foreach ( (array) $participants as $participant ) {
        if ( in_array( (string) $participant->participant_status, array( 'repondu', 'termine' ), true ) ) {
          continue;
        }
        $count = isset( $participant->reminder_count ) ? (int) $participant->reminder_count : 0;
        if ( ! isset( $reminder_days[ $count ] ) ) {
          continue;
        }
        $target_day = absint( $reminder_days[ $count ] );
        if ( $days_since >= $target_day ) {
          $email = $this->get_questionnaire_participant_contact_email( $participant );
          /* Le deuxième garde-fou : une personne ne reçoit qu'un message par
             passage, quelle que soit le nombre d'enquêtes qui la visent. Les
             autres attendront le passage suivant — elles ne sont pas perdues. */
          if ( $email && in_array( strtolower( trim( (string) $email ) ), $__servis, true ) ) {
            continue;
          }
          if ( $email ) {
            $__servis[] = strtolower( trim( (string) $email ) );
            $settings_mail = $this->get_questionnaire_mail_settings();
            $sender_name = sanitize_text_field( (string) ( $settings_mail['sender_name'] ?? '' ) );
            $sender_email = sanitize_email( (string) ( $settings_mail['sender_email'] ?? '' ) );
            $reply_to = sanitize_email( ! empty( $settings_mail['reply_to'] ) ? (string) $settings_mail['reply_to'] : $sender_email );
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
            $body .= '<p>Nous vous rappelons que le questionnaire <strong>' . esc_html( $session->session_title ) . '</strong> est toujours disponible.</p>';
            $body .= '<p><a href="' . esc_url( $url ) . '">Répondre au questionnaire</a></p>';
            if ( $this->acdc_send_transactional_email( $email, $session->session_title . ' — rappel', array( 'greeting_name' => $this->get_questionnaire_session_participant_display_name( $participant ), 'intro_html' => '', 'body_html' => $body, 'footer_notice' => 'Cet e-mail a été envoyé dans le cadre du suivi de votre questionnaire. Vos données sont traitées conformément au RGPD.' ), array( 'source_module' => 'questionnaires', 'source_action' => 'questionnaire_reminder', 'email_category' => 'questionnaire', 'email_audience' => 'destinataire' ) ) ) {
              $wpdb->update(
                $this->questionnaire_participant_table,
                array(
                  'reminder_count' => $count + 1,
                  'last_reminder_at' => $this->now_mysql(),
                ),
                array( 'id' => (int) $participant->id ),
                array( '%d', '%s' ),
                array( '%d' )
              );
            }
          }
        }
      }
    }
  }


  public function process_expired_survey_sessions() {
    global $wpdb;
    $sessions = $wpdb->get_results( $wpdb->prepare(
      "SELECT * FROM {$this->questionnaire_session_table} WHERE is_survey_session = %d AND deadline_at IS NOT NULL AND deadline_at < %s AND status NOT IN ('expiree','annulee','repondue')",
      1,
      $this->now_mysql()
    ) );
    foreach ( (array) $sessions as $session ) {
      $wpdb->update(
        $this->questionnaire_session_table,
        array( 'status' => 'expiree', 'updated_at' => $this->now_mysql() ),
        array( 'id' => (int) $session->id ),
        array( '%s', '%s' ),
        array( '%d' )
      );
      $wpdb->query( $wpdb->prepare(
        "UPDATE {$this->questionnaire_participant_table} SET participant_status = %s WHERE session_id = %d AND participant_status NOT IN ('repondu','termine')",
        'expire',
        (int) $session->id
      ) );
      $this->log_questionnaire_event( array(
        'questionnaire_session_id' => (int) $session->id,
        'event_type' => 'session_expired',
        'event_label' => 'Session questionnaire expirée',
      ) );
    }
  }


}
