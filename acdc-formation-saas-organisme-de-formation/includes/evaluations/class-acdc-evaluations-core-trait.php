<?php
/**
 * ACDC Évaluations des acquis — ACDC_Evaluations_Core_Trait.
 *
 * Extraction micro-incrémentale du sous-bloc métier :
 * évaluations des acquis.
 *
 * @since 3.11.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Evaluations_Core_Trait {

  private function get_evaluations( $search = '' ) {
    global $wpdb;
    $where = '';
    if ( '' !== trim( (string) $search ) ) {
      $like = '%' . $wpdb->esc_like( trim( (string) $search ) ) . '%';
      $where = $wpdb->prepare( "WHERE title LIKE %s OR correction_type LIKE %s", $like, $like );
    }
    return $wpdb->get_results( "SELECT * FROM {$this->evaluation_table} {$where} ORDER BY updated_at DESC, id DESC" );
  }

  private function get_evaluation( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->evaluation_table} WHERE id = %d", $id ) );
  }

  private function get_evaluation_formation_ids( $evaluation ) {
    if ( ! $evaluation || empty( $evaluation->formation_ids ) ) {
      return array();
    }
    $decoded = json_decode( (string) $evaluation->formation_ids, true );
    if ( is_array( $decoded ) ) {
      return array_values( array_filter( array_map( 'absint', $decoded ) ) );
    }
    return array_values( array_filter( array_map( 'absint', explode( ',', (string) $evaluation->formation_ids ) ) ) );
  }

  private function get_evaluation_formation_titles( $evaluation ) {
    $ids = $this->get_evaluation_formation_ids( $evaluation );
    if ( empty( $ids ) ) {
      return array();
    }
    global $wpdb;
    $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT title FROM {$this->formation_table} WHERE id IN ($placeholders) ORDER BY title ASC", $ids ) );
    return array_map( static function( $row ) { return (string) $row->title; }, $rows );
  }


  private function get_evaluation_result_document_info( $registration, $context = array() ) {
    $info = array(
      'url'   => '',
      'path'  => '',
      'size'  => '',
      'label' => 'Résultat des évaluations des acquis',
    );
    if ( ! $registration ) {
      return $info;
    }
    $url  = isset( $registration->evaluation_result_document_url ) ? trim( (string) $registration->evaluation_result_document_url ) : '';
    $path = isset( $registration->evaluation_result_document_path ) ? trim( (string) $registration->evaluation_result_document_path ) : '';
    if ( '' === $path && '' !== $url ) {
      $path = $this->get_local_path_from_upload_url( $url );
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

  private function get_evaluation_result_display_file_name( $registration, $context = array(), $document = array() ) {
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
    return sanitize_file_name( 'resultat-evaluation-acquis-' . $learner . '-' . $formation . '.pdf' );
  }

  private function get_evaluation_for_formation( $formation_id ) {
    $formation_id = absint( $formation_id );
    if ( ! $formation_id ) {
      return null;
    }
    $evaluations = $this->get_evaluations( '' );
    foreach ( (array) $evaluations as $evaluation ) {
      $ids = $this->get_evaluation_formation_ids( $evaluation );
      if ( in_array( $formation_id, $ids, true ) ) {
        return $evaluation;
      }
    }
    return null;
  }

  private function get_evaluation_result_context( $registration ) {
    $base_context = $this->get_training_convocation_context( $registration );
    $formation = isset( $base_context['formation'] ) ? $base_context['formation'] : null;
    $evaluation = $this->get_evaluation_for_formation( ! empty( $registration->formation_id ) ? (int) $registration->formation_id : 0 );
    $questions = array();
    if ( $evaluation && ! empty( $evaluation->question_blocks ) ) {
      $decoded = json_decode( (string) $evaluation->question_blocks, true );
      if ( is_array( $decoded ) ) {
        $questions = $decoded;
      }
    }
    $scoring = array();
    if ( $evaluation && ! empty( $evaluation->scoring_blocks ) ) {
      $decoded = json_decode( (string) $evaluation->scoring_blocks, true );
      if ( is_array( $decoded ) ) {
        $scoring = $decoded;
      }
    }
    $document = $this->get_evaluation_result_document_info( $registration, $base_context );
    $result_label = '—';
    if ( ! empty( $document['url'] ) || ! empty( $document['path'] ) ) {
      $result_label = 'Complétée';
    } elseif ( ! empty( $scoring ) ) {
      foreach ( $scoring as $score_row ) {
        $label = isset( $score_row['label'] ) ? trim( (string) $score_row['label'] ) : '';
        if ( '' !== $label ) {
          $result_label = $label;
          break;
        }
      }
    } else {
      $result_label = 'Disponible';
    }
    $questions_details = array();
    foreach ( $questions as $idx => $question ) {
      $options = isset( $question['options'] ) ? trim( (string) $question['options'] ) : '';
      $options_lines = array_values( array_filter( array_map( 'trim', preg_split( '/
|
|
/', $options ) ) ) );
      $expected = ! empty( $options_lines ) ? $options_lines[0] : '';
      $questions_details[] = array(
        'number' => $idx + 1,
        'label' => isset( $question['label'] ) ? (string) $question['label'] : 'Question',
        'type' => isset( $question['type'] ) ? (string) $question['type'] : '',
        'options' => $options,
        'expected' => $expected,
      );
    }
    $base_context['evaluation'] = $evaluation;
    $base_context['evaluation_title'] = $evaluation && ! empty( $evaluation->title ) ? (string) $evaluation->title : 'Évaluation des acquis';
    $base_context['evaluation_source_label'] = 'Plateforme';
    $base_context['correction_type'] = $evaluation && ! empty( $evaluation->correction_type ) ? (string) $evaluation->correction_type : 'Correction automatique';
    $base_context['result_label'] = $result_label;
    $base_context['correct_answers'] = null;
    $base_context['total_questions'] = count( $questions );
    $base_context['questions_details'] = $questions_details;
    $base_context['document'] = $document;
    return $base_context;
  }

  private function get_evaluation_result_download_url( $registration, $mode = 'attachment' ) {
    if ( ! $registration || empty( $registration->id ) ) {
      return '';
    }
    $mode = 'inline' === $mode ? 'inline' : 'attachment';
    return wp_nonce_url( admin_url( 'admin-post.php?action=acdc_download_evaluation_result_document&registration_id=' . (int) $registration->id . '&mode=' . rawurlencode( $mode ) ), 'acdc_download_evaluation_result_document_' . (int) $registration->id );
  }

  private function get_evaluation_result_rows( $search = '' ) {
    $search = trim( (string) $search );
    $items = $this->get_training_registrations( false );
    $rows = array();
    foreach ( (array) $items as $entry ) {
      if ( empty( $entry->formation_id ) ) {
        continue;
      }
      $context = $this->get_evaluation_result_context( $entry );
      $formation_enabled = ! empty( $context['formation'] ) && ! empty( $context['formation']->evaluation_enabled );
      if ( ! $formation_enabled && empty( $context['evaluation'] ) && empty( $context['document']['url'] ) && empty( $context['document']['path'] ) ) {
        continue;
      }
      $haystack = strtolower( implode( ' ', array_filter( array(
        (string) $entry->id,
        (string) $context['learner_name'],
        (string) $context['evaluation_source_label'],
        (string) $context['evaluation_title'],
        (string) $context['formation_title'],
      ) ) ) );
      if ( '' !== $search && false === strpos( $haystack, strtolower( $search ) ) ) {
        continue;
      }
      $state = '—';
      if ( ! empty( $context['end_date'] ) ) {
        $end_ts = strtotime( (string) $context['end_date'] . ' 23:59:59' );
        if ( $end_ts && $end_ts < current_time( 'timestamp' ) ) {
          $state = 'Formation terminée';
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

  private function build_evaluation_result_pdf_pages( $registration, $context = array() ) {
    $context = is_array( $context ) ? $context : array();
    if ( empty( $context ) ) {
      $context = $this->get_evaluation_result_context( $registration );
    }
    $profile = $this->get_company_profile_options();
    $branding = $this->get_branding_options();
    $org_name = ! empty( $profile['enterprise'] ) ? $profile['enterprise'] : ( ! empty( $branding['company_name'] ) ? $branding['company_name'] : 'ACDC Formation' );
    $header_bg = ! empty( $profile['header_footer_bg'] ) ? $profile['header_footer_bg'] : '#F3E3BF';
    $title_color = '#0C2D52';
    $muted = '#1E4777';
    $logo = $this->prepare_pdf_jpeg_image( $this->acdc_resolve_pdf_logo_url(), 42, 42 );
    $footer_logo = ! empty( $profile['footer_logo_url'] ) ? $this->prepare_pdf_jpeg_image( $profile['footer_logo_url'], 120, 40 ) : null;

    $score_label = ( null !== $context['correct_answers'] && ! empty( $context['total_questions'] ) ) ? sprintf( '%d / %d', (int) $context['correct_answers'], (int) $context['total_questions'] ) : $context['result_label'];

    $pages = array();
    $page = array();
    $page[] = array( 'type' => 'rect', 'x' => 0, 'y' => 760, 'width' => 595, 'height' => 82, 'fill_color' => $header_bg );
    if ( $logo ) {
      $page[] = array(
        'type' => 'image', 'image_key' => $logo['key'], 'image_data' => $logo['data'], 'image_width' => $logo['width'], 'image_height' => $logo['height'], 'display_width' => $logo['display_width'], 'display_height' => $logo['display_height'], 'x' => 250, 'y' => 786,
      );
    }
    $page[] = array( 'text' => strtoupper( $org_name ), 'x' => 228, 'y' => 812, 'size' => 14, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    $page[] = array( 'text' => $context['learner_name'], 'x' => 230, 'y' => 782, 'size' => 18, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    $page[] = array( 'text' => 'Formation : ' . $context['formation_title'], 'x' => 105, 'y' => 760, 'size' => 8.5, 'font' => 'Helvetica', 'color' => $title_color );
    $page[] = array( 'text' => 'Résultat des évaluations des acquis', 'x' => 205, 'y' => 744, 'size' => 8.5, 'font' => 'Helvetica', 'color' => $title_color );
    $page[] = array( 'text' => 'Effectué le ' . $this->format_pdf_date( $context['start_date'] ), 'x' => 235, 'y' => 730, 'size' => 8.5, 'font' => 'Helvetica', 'color' => $muted );
    $page[] = array( 'text' => 'Résultat : ' . $score_label, 'x' => 238, 'y' => 700, 'size' => 14, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    $y = 664;
    foreach ( (array) $context['questions_details'] as $question ) {
      $block_height = 84;
      if ( $y - $block_height < 92 ) {
        if ( $footer_logo ) {
          $page[] = array(
            'type' => 'image', 'image_key' => $footer_logo['key'], 'image_data' => $footer_logo['data'], 'image_width' => $footer_logo['width'], 'image_height' => $footer_logo['height'], 'display_width' => $footer_logo['display_width'], 'display_height' => $footer_logo['display_height'], 'x' => 235, 'y' => 34,
          );
        }
        $pages[] = $page;
        $page = array();
        $y = 800;
      }
      $page[] = array( 'type' => 'rect', 'x' => 40, 'y' => $y - 70, 'width' => 515, 'height' => 70, 'stroke_color' => '#B8E0CF', 'line_width' => 1.5 );
      $page[] = array( 'text' => 'Question ' . (int) $question['number'] . ' : ' . $question['label'], 'x' => 54, 'y' => $y - 18, 'size' => 9.5, 'font' => 'Helvetica-Bold', 'color' => '#1F2937' );
      $page[] = array( 'text' => 'Résultat : ' . $context['result_label'], 'x' => 410, 'y' => $y - 18, 'size' => 8.5, 'font' => 'Helvetica-Bold', 'color' => '#35B37E' );
      $page[] = array( 'type' => 'rect', 'x' => 58, 'y' => $y - 52, 'width' => 460, 'height' => 24, 'fill_color' => '#F6F7F9', 'stroke_color' => '#E5E7EB', 'line_width' => 1 );
      $expected = ! empty( $question['expected'] ) ? $question['expected'] : 'Réponse non renseignée';
      $page[] = array( 'text' => $expected, 'x' => 74, 'y' => $y - 40, 'size' => 8.3, 'font' => 'Helvetica', 'color' => '#1F2937' );
      $y -= 92;
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
}
