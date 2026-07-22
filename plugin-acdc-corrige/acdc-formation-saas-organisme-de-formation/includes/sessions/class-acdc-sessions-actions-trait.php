<?php
/**
 * ACDC Séances — ACDC_Sessions_Actions_Trait.
 *
 * Extraction micro-incrémentale du sous-bloc métier :
 * séances.
 *
 * @since 3.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Sessions_Actions_Trait {

  public function handle_save_session() {
    $this->require_admin_manager_nonce( 'acdc_save_session' );

    global $wpdb;
    $session_id = isset( $_POST['session_id'] ) ? absint( wp_unslash( $_POST['session_id'] ) ) : 0;
    $title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';

    if ( '' === $title ) {
      $this->acdc_store_form_state( 'session', $_POST, array( 'title' ) );
      $this->redirect_to_portal( 'sessions', 'Le titre de la session est obligatoire.', 'error', array( 'action' => $session_id ? 'edit' : 'new', 'item_id' => $session_id ) );
    }

    /* ACDC 3.20.92 — trainer_id direct sur la session (alias indépendant de groups.trainer_id).
       Vérification d'existence pour éviter une référence orpheline ; sinon NULL. */
    $trainer_id_session_in = isset( $_POST['trainer_id'] ) ? absint( wp_unslash( $_POST['trainer_id'] ) ) : 0;
    $trainer_id_session = null;
    if ( $trainer_id_session_in > 0 ) {
      $trainer_check = $this->get_trainer( $trainer_id_session_in );
      if ( $trainer_check ) {
        $trainer_id_session = (int) $trainer_check->id;
      }
    }

    $data = array(
      'formation_id' => isset( $_POST['formation_id'] ) && absint( wp_unslash( $_POST['formation_id'] ) ) ? absint( wp_unslash( $_POST['formation_id'] ) ) : null,
      'company_id'  => isset( $_POST['company_id'] ) && absint( wp_unslash( $_POST['company_id'] ) ) ? absint( wp_unslash( $_POST['company_id'] ) ) : null,
      'trainer_id'  => $trainer_id_session,
      'title'    => $title,
      'session_type' => isset( $_POST['session_type'] ) ? sanitize_text_field( wp_unslash( $_POST['session_type'] ) ) : '',
      'attendance_method' => isset( $_POST['attendance_method'] ) ? sanitize_text_field( wp_unslash( $_POST['attendance_method'] ) ) : '',
      'session_format' => isset( $_POST['session_format'] ) ? sanitize_text_field( wp_unslash( $_POST['session_format'] ) ) : '',
      'start_at'   => isset( $_POST['start_at'] ) ? sanitize_text_field( wp_unslash( $_POST['start_at'] ) ) : null,
      'end_at'    => isset( $_POST['end_at'] ) ? sanitize_text_field( wp_unslash( $_POST['end_at'] ) ) : null,
      'start_date'  => isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : null,
      'end_date'   => isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : null,
      'location'   => isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '',
      'remote_link' => isset( $_POST['remote_link'] ) ? esc_url_raw( wp_unslash( $_POST['remote_link'] ) ) : '',
      'status'    => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Planifiée',
      'max_learners' => isset( $_POST['max_learners'] ) ? absint( wp_unslash( $_POST['max_learners'] ) ) : 0,
      'is_draft'  => isset( $_POST['is_draft'] ) ? absint( wp_unslash( $_POST['is_draft'] ) ) : 0,
      'schedule_json' => isset( $_POST['schedule_json'] ) ? wp_json_encode( wp_unslash( $_POST['schedule_json'] ) ) : null,
      'notes'    => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
      'updated_at'  => $this->now_mysql(),
    );

    if ( $session_id ) {
      $result = $wpdb->update( $this->session_table, $data, array( 'id' => $session_id ) );
      if ( false === $result ) {
        $this->acdc_store_form_state( 'session', $_POST );
        $this->redirect_to_portal( 'sessions', 'Erreur lors de la mise à jour. Veuillez réessayer.', 'error', array( 'action' => 'edit', 'item_id' => $session_id ) );
      }
      $message = 'Session mise à jour.';
    } else {
      $data['created_at'] = $this->now_mysql();
      $result = $wpdb->insert( $this->session_table, $data );
      if ( false === $result ) {
        $this->acdc_store_form_state( 'session', $_POST );
        $this->redirect_to_portal( 'sessions', 'Erreur lors de l\'enregistrement. Veuillez réessayer.', 'error', array( 'action' => 'new' ) );
      }
      $session_id = (int) $wpdb->insert_id;
      $message = 'Session enregistrée.';
    }

    if ( $session_id ) {
      $this->acdc_ensure_session_document_space( $session_id );
    }

    $return_after_save = $this->acdc_get_post_return_after_save( 'edit', array( 'edit', 'list', 'new' ) );
    $success_args = array( 'action' => $return_after_save );
    if ( 'edit' === $return_after_save ) {
      $success_args['item_id'] = $session_id;
    }

    $this->redirect_to_portal( 'sessions', $message, 'success', $success_args );
  }

  public function handle_save_session_builder() {
    $this->require_admin_manager_nonce( 'acdc_save_session_builder' );

    global $wpdb;

    $input = isset( $_POST['session_builder'] ) ? (array) wp_unslash( $_POST['session_builder'] ) : array();
    $session_type = isset( $input['session_type'] ) ? sanitize_text_field( $input['session_type'] ) : '';
    $attendance_method = isset( $input['attendance_method'] ) ? sanitize_text_field( $input['attendance_method'] ) : '';
    $session_format = isset( $input['session_format'] ) ? sanitize_text_field( $input['session_format'] ) : '';
    $title = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
    $notes = isset( $input['notes'] ) ? sanitize_textarea_field( $input['notes'] ) : '';
    $is_draft = ! empty( $input['is_draft'] ) ? 1 : 0;
    $formation_id_in = isset( $input['formation_id'] ) ? absint( $input['formation_id'] ) : 0;
    $trainer_id_in   = isset( $input['trainer_id'] )   ? absint( $input['trainer_id'] )   : 0;
    $learner_id_in   = isset( $input['learner_id'] )   ? absint( $input['learner_id'] )   : 0;
    $group_id_in     = isset( $input['group_id'] )     ? absint( $input['group_id'] )     : 0;
    $location_in     = isset( $input['location'] )     ? sanitize_text_field( $input['location'] ) : '';

    // Vérification existence formateur (évite une référence orpheline)
    $trainer_id_val = null;
    if ( $trainer_id_in > 0 ) {
      $tr_check = $this->get_trainer( $trainer_id_in );
      if ( $tr_check ) {
        $trainer_id_val = (int) $tr_check->id;
      }
    }
    $slots = isset( $input['slots'] ) && is_array( $input['slots'] ) ? $input['slots'] : array();

    $error_base = is_admin() && isset( $_POST['page'] ) && 'acdc-of-dashboard' === sanitize_key( wp_unslash( $_POST['page'] ) )
      ? admin_url( 'admin.php?page=acdc-of-dashboard&tab=create_session' )
      : $this->portal_page_url( array( 'tab' => 'create_session' ) );

    if ( '' === $session_type ) {
      wp_safe_redirect( add_query_arg( $this->acdc_append_notice_args( array(), 'Le type de séance est obligatoire.', 'error' ), $error_base ) );
      exit;
    }

    $prepared_slots = array();
    foreach ( $slots as $slot ) {
      if ( ! is_array( $slot ) ) {
        continue;
      }
      $start_local = isset( $slot['start_at'] ) ? sanitize_text_field( $slot['start_at'] ) : '';
      $end_local = isset( $slot['end_at'] ) ? sanitize_text_field( $slot['end_at'] ) : '';
      if ( '' === $start_local && '' === $end_local ) {
        continue;
      }
      if ( '' === $start_local || '' === $end_local ) {
        wp_safe_redirect( add_query_arg( $this->acdc_append_notice_args( array(), 'Chaque date personnalisée doit comporter un début et une fin.', 'error' ), $error_base ) );
        exit;
      }
      $start_at = $this->datetime_from_local( $start_local );
      $end_at = $this->datetime_from_local( $end_local );
      if ( empty( $start_at ) || empty( $end_at ) || strtotime( $end_at ) < strtotime( $start_at ) ) {
        wp_safe_redirect( add_query_arg( $this->acdc_append_notice_args( array(), 'La fin d’une date personnalisée doit être postérieure au début.', 'error' ), $error_base ) );
        exit;
      }
      $prepared_slots[] = array(
        'start_at' => $start_at,
        'end_at' => $end_at,
        'start_date' => gmdate( 'Y-m-d', strtotime( $start_at ) ),
        'end_date' => gmdate( 'Y-m-d', strtotime( $end_at ) ),
      );
    }

    if ( empty( $prepared_slots ) ) {
      wp_safe_redirect( add_query_arg( $this->acdc_append_notice_args( array(), 'Ajoutez au moins une date de séance.', 'error' ), $error_base ) );
      exit;
    }

    $created = 0;
    $now = $this->now_mysql();
    foreach ( $prepared_slots as $index => $slot ) {
      $resolved_title = $title;
      if ( '' === $resolved_title ) {
        $resolved_title = sprintf( 'Séance %s — %s', strtolower( $session_type ), mysql2date( 'd/m/Y H:i', $slot['start_at'] ) );
      }
      $data = array(
        'formation_id' => $formation_id_in > 0 ? $formation_id_in : null,
        'company_id' => null,
        'trainer_id'  => $trainer_id_val,
        'title' => $resolved_title,
        'session_type' => $session_type,
        'attendance_method' => $attendance_method,
        'session_format' => $session_format,
        'start_at' => $slot['start_at'],
        'end_at' => $slot['end_at'],
        'start_date' => $slot['start_date'],
        'end_date' => $slot['end_date'],
        'location' => '' !== $location_in ? $location_in : ( 'Présentiel' === $session_format ? 'Présentiel' : '' ),
        'remote_link' => isset( $input['remote_link'] ) ? esc_url_raw( $input['remote_link'] ) : '',
        'status' => $is_draft ? 'Brouillon' : 'Planifiée',
        'max_learners' => 0,
        'is_draft' => $is_draft,
        'schedule_json' => wp_json_encode( array( $slot ) ),
        'notes' => $notes,
        'created_at' => $now,
        'updated_at' => $now,
      );
      $result = $wpdb->insert( $this->session_table, $data );
      if ( false === $result ) {
        wp_safe_redirect( add_query_arg( $this->acdc_append_notice_args( array(), $this->get_safe_db_error_message( 'Une erreur technique est survenue.' ), 'error' ), $error_base ) );
        exit;
      }
      $new_session_id = (int) $wpdb->insert_id;
      // Rattachement apprenant (séance individuelle)
      if ( $learner_id_in > 0 && $new_session_id > 0 ) {
        $wpdb->update(
          $this->learner_table,
          array( 'session_id' => $new_session_id, 'updated_at' => $now ),
          array( 'id' => $learner_id_in ),
          array( '%d', '%s' ),
          array( '%d' )
        );
      }
      // Rattachement groupe (séance groupe)
      if ( $group_id_in > 0 && $new_session_id > 0 ) {
        $wpdb->update(
          $this->group_table,
          array( 'session_id' => $new_session_id, 'updated_at' => $now ),
          array( 'id' => $group_id_in ),
          array( '%d', '%s' ),
          array( '%d' )
        );
      }
      $created++;
    }

    $message = $created > 1 ? sprintf( '%d séances enregistrées.', $created ) : 'Séance enregistrée.';
    if ( $is_draft ) {
      $message = $created > 1 ? sprintf( '%d séances enregistrées en brouillon.', $created ) : 'Séance enregistrée en brouillon.';
    }

    if ( ! empty( $_POST['save_and_add'] ) ) {
      $target = is_admin() && isset( $_POST['page'] ) && 'acdc-of-dashboard' === sanitize_key( wp_unslash( $_POST['page'] ) )
        ? admin_url( 'admin.php?page=acdc-of-dashboard&tab=create_session' )
        : $this->portal_page_url( array( 'tab' => 'create_session' ) );
      wp_safe_redirect( add_query_arg( $this->acdc_append_notice_args( array(), $message, 'success' ), $target ) );
      exit;
    }

    $target_tab = $is_draft ? 'sessions_pending' : 'sessions_calendar';
    $target = is_admin() && isset( $_POST['page'] ) && 'acdc-of-dashboard' === sanitize_key( wp_unslash( $_POST['page'] ) )
      ? admin_url( 'admin.php?page=acdc-of-dashboard&tab=' . $target_tab )
      : $this->portal_page_url( array( 'tab' => $target_tab ) );
    wp_safe_redirect( add_query_arg( $this->acdc_append_notice_args( array(), $message, 'success' ), $target ) );
    exit;
  }

  public function handle_delete_session() {
    $this->require_admin_manager_access();

    $session_id = isset( $_GET['session_id'] ) ? absint( wp_unslash( $_GET['session_id'] ) ) : 0;
    $return_tab = isset( $_GET['return_tab'] ) ? sanitize_key( wp_unslash( $_GET['return_tab'] ) ) : 'sessions';
    if ( ! in_array( $return_tab, array( 'sessions', 'sessions_pending', 'sessions_calendar', 'sessions_validated' ), true ) ) {
      $return_tab = 'sessions_validated';
    }
    if ( ! $session_id ) {
      $this->redirect_to_portal( $return_tab, 'Session introuvable.', 'error' );
      exit;
    }

    $this->verify_nonce_or_die( 'acdc_delete_session_' . $session_id );

    global $wpdb;
    $learner_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->learner_table} WHERE session_id = %d", $session_id ) );
    if ( $learner_count ) {
      $this->redirect_to_portal( $return_tab, 'Suppression impossible : cette session est liée à des apprenants.', 'error' );
      exit;
    }

    $wpdb->delete( $this->session_table, array( 'id' => $session_id ) );
    $this->redirect_to_portal( $return_tab, 'Session supprimée.', 'success' );
  }

  /**
   * ACDC 3.21.05 — Cron quotidien : clôture automatique des sessions OF terminées.
   *
   * Pour chaque session dont la date de fin est passée et dont le statut
   * n'est pas encore "Terminée" ou "Annulée" :
   *  1. Marque la session "Terminée".
   *  2. Envoie l'évaluation des acquis aux apprenants (si un quiz assessment
   *     actif est rattaché à la formation et que l'apprenant n'a pas encore
   *     été dispatché).
   *
   * Note : l'enquête à chaud est déjà gérée automatiquement par
   * ensure_hot_survey_automation_sessions() sur le cron surveys.
   * La génération d'attestation PDF reste un déclenchement manuel.
   */
  public function process_convocation_cron() {
    global $wpdb;

    if ( empty( $this->session_table ) || empty( $this->formation_table ) || empty( $this->learner_table ) ) {
      return;
    }

    // ACDC 3.25.115 — base calendaire homogène (même fuseau/heure que $start_ts) plutôt que
    // de comparer current_time('timestamp') (heure locale WP) à strtotime() (fuseau serveur).
    $today_ts = strtotime( current_time( 'Y-m-d' ) . ' 08:00:00' );

    // Séances actives avec convocation_enabled = 1 et date de début future ou aujourd'hui
    $sessions = $wpdb->get_results(
      "SELECT s.*, f.title AS formation_title, f.convocation_enabled
       FROM {$this->session_table} s
       LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id
       WHERE s.is_draft = 0
         AND COALESCE(f.convocation_enabled, 0) = 1
         AND COALESCE(s.status, '') NOT IN ('Annulée','Annulee','Terminée','Terminee')
         AND ( s.start_date IS NOT NULL OR s.start_at IS NOT NULL )
       ORDER BY COALESCE(s.start_date, DATE(s.start_at)) ASC, s.id ASC"
    );

    if ( empty( $sessions ) ) {
      return;
    }

    foreach ( (array) $sessions as $session ) {
      $start_date = ! empty( $session->start_date )
        ? (string) $session->start_date
        : ( ! empty( $session->start_at ) ? substr( (string) $session->start_at, 0, 10 ) : '' );

      if ( ! $start_date ) {
        continue;
      }

      $start_ts   = strtotime( $start_date . ' 08:00:00' );
      // ACDC 3.25.115 — écart en jours calendaires (même base 08:00:00 que $today_ts).
      $days_until = (int) floor( ( $start_ts - $today_ts ) / DAY_IN_SECONDS );

      if ( $days_until < 0 ) {
        continue;
      }

      $session_id              = (int) $session->id;
      $convocation_sent_at     = ! empty( $session->convocation_sent_at )          ? (string) $session->convocation_sent_at          : '';
      $convocation_reminder_at = ! empty( $session->convocation_reminder_sent_at ) ? (string) $session->convocation_reminder_sent_at : '';

      $send_convocation = false;
      $send_reminder    = false;

      if ( '' === $convocation_sent_at && $days_until <= 7 ) {
        $send_convocation = true;
      } elseif ( '' !== $convocation_sent_at && '' === $convocation_reminder_at && $days_until <= 1 ) {
        $send_reminder = true;
      }

      if ( ! $send_convocation && ! $send_reminder ) {
        continue;
      }

      // Apprenants de cette séance
      $learners = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, first_name, last_name, usage_last_name, email
         FROM {$this->learner_table}
         WHERE session_id = %d AND email != '' AND email IS NOT NULL",
        $session_id
      ) );

      if ( empty( $learners ) ) {
        // Tracer quand même pour ne pas re-tenter indéfiniment
        if ( $send_convocation ) {
          $wpdb->update( $this->session_table, array( 'convocation_sent_at' => $this->now_mysql(), 'updated_at' => $this->now_mysql() ), array( 'id' => $session_id ) );
        } elseif ( $send_reminder ) {
          $wpdb->update( $this->session_table, array( 'convocation_reminder_sent_at' => $this->now_mysql(), 'updated_at' => $this->now_mysql() ), array( 'id' => $session_id ) );
        }
        continue;
      }

      $formation_title = ! empty( $session->formation_title ) ? (string) $session->formation_title : ( ! empty( $session->title ) ? (string) $session->title : 'votre formation' );
      $date_formatted  = wp_date( 'l d F Y', $start_ts );
      $portal_page_id  = (int) get_option( 'acdc_of_learner_portal_page_id', 0 );
      $portal_url      = $portal_page_id ? get_permalink( $portal_page_id ) : home_url( '/' );

      foreach ( (array) $learners as $learner ) {
        $email = sanitize_email( (string) $learner->email );
        if ( ! $email || ! is_email( $email ) ) {
          continue;
        }
        $prenom      = ! empty( $learner->first_name ) ? (string) $learner->first_name : '';
        $nom         = ! empty( $learner->usage_last_name ) ? (string) $learner->usage_last_name : ( ! empty( $learner->last_name ) ? (string) $learner->last_name : '' );
        $greeting    = trim( $prenom ) ?: trim( $prenom . ' ' . $nom );

        if ( $send_convocation ) {
          $summary_rows = array(
            array( 'label' => 'Formation',     'value' => $formation_title ),
            array( 'label' => 'Date de début', 'value' => ucfirst( $date_formatted ) ),
            array( 'label' => 'Lieu / format', 'value' => ! empty( $session->location ) ? (string) $session->location : ( ! empty( $session->remote_link ) ? 'Distanciel' : '—' ) ),
          );
          $body_html  = '<p style="font-size:19px;line-height:1.7;margin:0 0 20px;">Vous êtes convoqué(e) à la formation indiquée ci-dessous. Merci de vous présenter à l\'heure et muni(e) des documents nécessaires.</p>';
          if ( ! empty( $session->remote_link ) ) {
            $body_html .= '<p style="font-size:18px;line-height:1.7;margin:0 0 20px;">Lien de connexion : <a href="' . esc_url( (string) $session->remote_link ) . '" style="color:#C5A253;text-decoration:underline;">' . esc_html( (string) $session->remote_link ) . '</a></p>';
          }
          $body_html .= '<p style="margin:24px 0;text-align:center;"><a href="' . esc_url( $portal_url ) . '" style="display:inline-block;padding:14px 28px;background:#C5A253;color:#0B0706;text-decoration:none;border-radius:8px;font-weight:700;font-size:16px;">Accéder à mon espace apprenant</a></p>';

          $this->acdc_send_transactional_email(
            $email,
            'Convocation — ' . $formation_title,
            array(
              'greeting_name' => $greeting,
              'intro_html'    => '',
              'summary_title' => 'DÉTAILS DE VOTRE CONVOCATION',
              'summary_rows'  => $summary_rows,
              'body_html'     => $body_html,
              'footer_notice' => 'Cet e-mail est votre convocation officielle. Conservez-le pour vos dossiers.',
            ),
            array(
              'source_module'       => 'sessions',
              'source_action'       => 'convocation_auto',
              'related_entity_type' => 'session',
              'related_entity_id'   => $session_id,
              'email_category'      => 'convocation',
              'email_audience'      => 'apprenant',
            )
          );

          // ACDC 3.25.115 — persister l'URL de convocation pour l'affichage portail.
          // Reproduit le schéma de _auto_send_completion_certificate_for_session :
          // génération du PDF via build_training_convocation_pdf_pages + sauvegarde fichier,
          // puis écriture de l'URL/chemin sur le dossier (training_registration).
          if ( ! empty( $session->formation_id )
               && method_exists( $this, 'get_training_convocation_context' )
               && method_exists( $this, 'build_training_convocation_pdf_pages' )
               && method_exists( $this, '_build_simple_pdf_string' ) ) {
            $conv_reg = $wpdb->get_row( $wpdb->prepare(
              "SELECT * FROM {$this->training_registration_table} WHERE learner_id = %d AND formation_id = %d AND is_draft = 0 ORDER BY id DESC LIMIT 1",
              (int) $learner->id,
              (int) $session->formation_id
            ) );
            if ( $conv_reg && empty( $conv_reg->convocation_document_url ) ) {
              $conv_context = $this->get_training_convocation_context( $conv_reg );
              $conv_pages   = $this->build_training_convocation_pdf_pages( $conv_reg, $conv_context );
              $conv_pdf     = $this->_build_simple_pdf_string( $conv_pages );
              if ( ! empty( $conv_pdf ) ) {
                $conv_upload_dir = wp_upload_dir();
                $conv_subdir     = $conv_upload_dir['basedir'] . '/acdc-convocations';
                if ( ! file_exists( $conv_subdir ) ) {
                  wp_mkdir_p( $conv_subdir );
                }
                $conv_filename = sanitize_file_name( 'convocation-' . (int) $conv_reg->id . '.pdf' );
                $conv_filepath = $conv_subdir . '/' . $conv_filename;
                if ( file_put_contents( $conv_filepath, $conv_pdf ) !== false ) {
                  $conv_fileurl = $conv_upload_dir['baseurl'] . '/acdc-convocations/' . $conv_filename;
                  $wpdb->update(
                    $this->training_registration_table,
                    array(
                      'convocation_document_url'  => esc_url_raw( $conv_fileurl ),
                      'convocation_document_path' => sanitize_text_field( $conv_filepath ),
                      'updated_at'                => current_time( 'mysql' ),
                    ),
                    array( 'id' => (int) $conv_reg->id ),
                    array( '%s', '%s', '%s' ),
                    array( '%d' )
                  );
                }
              }
            }
          }

        } elseif ( $send_reminder ) {
          $body_html  = '<p style="font-size:19px;line-height:1.7;margin:0 0 20px;">Votre formation <strong>' . esc_html( $formation_title ) . '</strong> commence demain, le <strong>' . esc_html( ucfirst( $date_formatted ) ) . '</strong>.</p>';
          $body_html .= '<p style="font-size:18px;line-height:1.7;margin:0 0 20px;">Pensez à vous préparer et à avoir votre convocation avec vous.</p>';
          if ( ! empty( $session->remote_link ) ) {
            $body_html .= '<p style="font-size:18px;line-height:1.7;margin:0 0 20px;">Lien de connexion : <a href="' . esc_url( (string) $session->remote_link ) . '" style="color:#C5A253;text-decoration:underline;">' . esc_html( (string) $session->remote_link ) . '</a></p>';
          }
          $body_html .= '<p style="margin:24px 0;text-align:center;"><a href="' . esc_url( $portal_url ) . '" style="display:inline-block;padding:14px 28px;background:#C5A253;color:#0B0706;text-decoration:none;border-radius:8px;font-weight:700;font-size:16px;">Accéder à mon espace apprenant</a></p>';

          $this->acdc_send_transactional_email(
            $email,
            'Rappel — ' . $formation_title . ' démarre demain',
            array(
              'greeting_name' => $greeting,
              'intro_html'    => '',
              'body_html'     => $body_html,
              'footer_notice' => 'Cet e-mail est un rappel automatique. Vos données sont traitées conformément au RGPD.',
            ),
            array(
              'source_module'       => 'sessions',
              'source_action'       => 'convocation_reminder_auto',
              'related_entity_type' => 'session',
              'related_entity_id'   => $session_id,
              'email_category'      => 'convocation_rappel',
              'email_audience'      => 'apprenant',
            )
          );
        }
      }

      // ACDC 3.23.0 — Convocation commanditaire (entreprise) à J-7 uniquement.
      if ( $send_convocation && ! empty( $session->company_id ) ) {
        $conv_company_sent = ! empty( $session->convocation_company_sent_at ) ? (string) $session->convocation_company_sent_at : '';
        if ( '' === $conv_company_sent ) {
          $company = $this->get_company( (int) $session->company_id );
          $company_email = '';
          $company_name  = '';
          if ( $company ) {
            $company_name  = ! empty( $company->name ) ? (string) $company->name : '';
            $company_email = ! empty( $company->enterprise_contact_email ) ? sanitize_email( (string) $company->enterprise_contact_email ) : '';
            if ( ( '' === $company_email || ! is_email( $company_email ) ) && method_exists( $this, 'get_related_contacts' ) ) {
              $contacts = $this->get_related_contacts( (int) $session->company_id );
              foreach ( (array) $contacts as $ct ) {
                if ( ! empty( $ct->email ) && is_email( $ct->email ) ) {
                  $company_email = sanitize_email( (string) $ct->email );
                  break;
                }
              }
            }
          }
          if ( $company_email && is_email( $company_email ) ) {
            // Construire la liste des apprenants convoqués
            $learners_list_html = '';
            if ( ! empty( $learners ) ) {
              $learners_list_html .= '<ul style="margin:0 0 20px;padding-left:20px;font-size:17px;line-height:1.8;">';
              foreach ( (array) $learners as $lrn ) {
                $lrn_prenom = ! empty( $lrn->first_name ) ? (string) $lrn->first_name : '';
                $lrn_nom    = ! empty( $lrn->usage_last_name ) ? (string) $lrn->usage_last_name : ( ! empty( $lrn->last_name ) ? (string) $lrn->last_name : '' );
                $learners_list_html .= '<li style="color:#24324a;">' . esc_html( trim( $lrn_prenom . ' ' . $lrn_nom ) ) . '</li>';
              }
              $learners_list_html .= '</ul>';
            }
            $nb_apprenants = count( (array) $learners );
            $company_summary_rows = array(
              array( 'label' => 'Formation',                'value' => $formation_title ),
              array( 'label' => 'Date de début',            'value' => ucfirst( $date_formatted ) ),
              array( 'label' => 'Lieu / format',            'value' => ! empty( $session->location ) ? (string) $session->location : ( ! empty( $session->remote_link ) ? 'Distanciel' : '—' ) ),
              array( 'label' => 'Nombre de participant(s)', 'value' => (string) $nb_apprenants ),
            );
            $company_body_html  = '<p style="font-size:19px;line-height:1.7;margin:0 0 20px;">Nous vous informons que ' . ( $nb_apprenants > 1 ? 'les participants suivants sont convoqués' : 'le participant suivant est convoqué' ) . ' à la formation indiquée ci-dessus :</p>';
            $company_body_html .= $learners_list_html;
            $company_body_html .= '<p style="font-size:17px;line-height:1.7;margin:0 0 20px;color:#4b5d76;">Merci de vous assurer que ' . ( $nb_apprenants > 1 ? 'ces personnes sont disponibles' : 'cette personne est disponible' ) . ' et informées de cette convocation.</p>';
            if ( ! empty( $session->remote_link ) ) {
              $company_body_html .= '<p style="font-size:17px;line-height:1.7;margin:0 0 20px;">Lien de connexion distanciel : <a href="' . esc_url( (string) $session->remote_link ) . '" style="color:#8b5b23;text-decoration:underline;">' . esc_html( (string) $session->remote_link ) . '</a></p>';
            }
            $this->acdc_send_transactional_email(
              $company_email,
              'Convocation — ' . $formation_title . ' (' . ucfirst( $date_formatted ) . ')',
              array(
                'greeting_name' => $company_name ?: 'Madame, Monsieur',
                'intro_html'    => '',
                'summary_title' => 'DÉTAILS DE LA FORMATION',
                'summary_rows'  => $company_summary_rows,
                'body_html'     => $company_body_html,
                'footer_notice' => 'Cet e-mail est une notification automatique de convocation. Il est adressé au commanditaire de la formation.',
              ),
              array(
                'source_module'       => 'sessions',
                'source_action'       => 'convocation_company_auto',
                'related_entity_type' => 'session',
                'related_entity_id'   => $session_id,
                'email_category'      => 'convocation_commanditaire',
                'email_audience'      => 'entreprise',
              )
            );
            $wpdb->update( $this->session_table, array( 'convocation_company_sent_at' => $this->now_mysql(), 'updated_at' => $this->now_mysql() ), array( 'id' => $session_id ) );
          }
        }
      }

      // Traçabilité
      if ( $send_convocation ) {
        $wpdb->update( $this->session_table, array( 'convocation_sent_at' => $this->now_mysql(), 'updated_at' => $this->now_mysql() ), array( 'id' => $session_id ) );
        // ACDC 3.23.2 — Convocations envoyées → dossier(s) liés passent à convocations_envoyees.
        $formation_id_for_wf = ! empty( $session->formation_id ) ? (int) $session->formation_id : 0;
        if ( $formation_id_for_wf ) {
          $linked_regs_wf = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM {$this->training_registration_table} WHERE formation_id = %d AND is_draft = 0", $formation_id_for_wf ) );
          foreach ( (array) $linked_regs_wf as $lr_wf ) {
            $this->advance_registration_workflow( (int) $lr_wf->id, 'convocations_envoyees' );
          }
        }
      } elseif ( $send_reminder ) {
        $wpdb->update( $this->session_table, array( 'convocation_reminder_sent_at' => $this->now_mysql(), 'updated_at' => $this->now_mysql() ), array( 'id' => $session_id ) );
      }
    }
  }

  public function process_of_session_auto_close() {
    global $wpdb;

    // Vérifier que les tables existent (sécurité défensive)
    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->session_table ) );
    if ( $exists !== $this->session_table ) {
      return;
    }

    // 1. Sessions dont la fin est passée, non encore clôturées
    $sessions = $wpdb->get_results(
      "SELECT id, formation_id, status
       FROM {$this->session_table}
       WHERE COALESCE(
           end_at,
           CONCAT( COALESCE(end_date, '1970-01-01'), ' 23:59:59' )
       ) < NOW()
         AND ( end_at IS NOT NULL OR end_date IS NOT NULL )
         AND COALESCE(is_draft, 0) = 0
         AND COALESCE(status, '') NOT IN ('Terminée', 'Annulée', 'Brouillon')"
    );

    if ( empty( $sessions ) ) {
      return;
    }

    $now_mysql = current_time( 'mysql' );

    foreach ( $sessions as $session ) {
      $session_id   = (int) $session->id;
      $formation_id = (int) $session->formation_id;

      // 2. Marquer "Terminée"
      $wpdb->update(
        $this->session_table,
        array( 'status' => 'Terminée', 'updated_at' => $now_mysql ),
        array( 'id' => $session_id ),
        array( '%s', '%s' ),
        array( '%d' )
      );

      // 3. Envoi évaluation des acquis (si module quiz disponible)
      if ( $formation_id > 0 && method_exists( $this, 'get_qz_table' ) && method_exists( $this, 'create_qz_async_dispatch' ) ) {
        $this->_auto_dispatch_assessment_for_session( $session_id, $formation_id, $now_mysql );
      }

      // 4. ACDC 3.21.63 — Envoi automatique de l'attestation de réalisation aux apprenants
      if ( $formation_id > 0 ) {
        $this->_auto_send_completion_certificate_for_session( $session_id, $formation_id );
      }

      // 4b. ACDC 3.24.27 — Envoi automatique de l'attestation de fin de formation aux apprenants
      if ( $formation_id > 0 ) {
        $this->_auto_send_end_training_certificate_for_session( $session_id, $formation_id );
      }

      // 5. ACDC 3.23.2 — Session terminée → dossier(s) liés passent à formation_realisee.
      if ( $formation_id > 0 ) {
        $linked_regs = $wpdb->get_results( $wpdb->prepare(
          "SELECT id FROM {$this->training_registration_table} WHERE formation_id = %d AND is_draft = 0",
          $formation_id
        ) );
        foreach ( (array) $linked_regs as $lr ) {
          $this->advance_registration_workflow( (int) $lr->id, 'formation_realisee' );
        }
      }
    }
  }

  /**
   * Envoie l'évaluation des acquis aux apprenants d'une session OF clôturée.
   * Réutilise le même pattern anti-doublon que hotfix65 (positionnement 48h).
   *
   * @param int    $session_id
   * @param int    $formation_id
   * @param string $now_mysql
   */
  private function _auto_dispatch_assessment_for_session( $session_id, $formation_id, $now_mysql ) {
    global $wpdb;

    $tbl_qz_quizzes = $this->get_qz_table( 'quizzes' );
    $tbl_qz_sessions = $this->get_qz_table( 'sessions' );
    $tbl_qz_parts   = $this->get_qz_table( 'participants' );

    // Vérifier que les tables quiz existent
    foreach ( array( $tbl_qz_quizzes, $tbl_qz_sessions, $tbl_qz_parts ) as $tbl ) {
      if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) !== $tbl ) {
        return;
      }
    }

    // Quiz d'évaluation des acquis actif pour cette formation
    $quiz = $wpdb->get_row( $wpdb->prepare(
      "SELECT id FROM {$tbl_qz_quizzes}
       WHERE formation_id = %d
         AND quiz_purpose = 'assessment'
         AND is_current   = 1
         AND status       = 'active'
       LIMIT 1",
      $formation_id
    ) );

    if ( ! $quiz ) {
      return;
    }

    $quiz_id = (int) $quiz->id;

    // Apprenants de cette session
    $learners = $this->get_qz_learners_for_formation( $formation_id, array( 'session_id' => $session_id ) );
    if ( empty( $learners ) ) {
      return;
    }

    // Filtrage anti-doublon
    $recipients = array();
    foreach ( $learners as $l ) {
      $learner_id = (int) $l->id;
      if ( $learner_id <= 0 || empty( $l->email ) ) {
        continue;
      }

      $already = $wpdb->get_var( $wpdb->prepare(
        "SELECT p.id
         FROM {$tbl_qz_parts} p
         INNER JOIN {$tbl_qz_sessions} qs ON qs.id = p.session_id
         WHERE qs.quiz_id   = %d
           AND p.learner_id = %d
           AND p.status NOT IN ('expired', 'cancelled')
         LIMIT 1",
        $quiz_id, $learner_id
      ) );

      if ( $already ) {
        continue;
      }

      $recipients[] = array(
        'email'      => $l->email,
        'first_name' => $l->first_name,
        'last_name'  => $l->last_name,
        'learner_id' => $learner_id,
      );
    }

    if ( empty( $recipients ) ) {
      return;
    }

    $result = $this->create_qz_async_dispatch( $quiz_id, $recipients, array(
      'expires_in_days'      => 14,
      'reminders_enabled'    => true,
      'formation_session_id' => $session_id,
    ) );

    if ( method_exists( $this, 'log_qz_event' ) ) {
      if ( is_wp_error( $result ) ) {
        $this->log_qz_event( array(
          'quiz_id'     => $quiz_id,
          'event_type'  => 'auto_dispatch_error',
          'event_label' => sprintf(
            'Erreur envoi auto évaluation : session OF #%d — %s',
            $session_id,
            $result->get_error_message()
          ),
        ) );
      } else {
        $this->log_qz_event( array(
          'quiz_id'       => $quiz_id,
          'event_type'    => 'auto_dispatch_sent',
          'event_label'   => sprintf(
            'Envoi automatique évaluation acquis (clôture session OF #%d) : %d destinataire(s)',
            $session_id,
            count( $recipients )
          ),
          'event_payload' => array(
            'formation_session_id' => $session_id,
            'recipients_count'     => count( $recipients ),
            'trigger'              => 'session_auto_close',
          ),
        ) );
      }
    }
  }


  /**
   * ACDC 3.21.63 — Génère et envoie automatiquement l'attestation de réalisation
   * à chaque apprenant d'une session OF clôturée.
   *
   * Comportement :
   * - Anti-doublon : si completion_certificate_sent_at est déjà renseigné sur la session, ne rien faire
   * - Pour chaque apprenant avec e-mail : génère le PDF en mémoire via ob_start() sur render_simple_pdf(),
   *   le sauvegarde dans wp-uploads, stocke l'URL dans training_registration,
   *   puis envoie un e-mail de notification avec lien vers le portail apprenant
   * - Trace la date d'envoi dans session.completion_certificate_sent_at
   *
   * @param int $session_id   ID de la session OF
   * @param int $formation_id ID de la formation
   */
  private function _auto_send_completion_certificate_for_session( $session_id, $formation_id ) {
    global $wpdb;

    $session_id   = absint( $session_id );
    $formation_id = absint( $formation_id );
    if ( ! $session_id || ! $formation_id ) {
      return;
    }

    // Anti-doublon : vérifier si déjà envoyé pour cette session
    $already_sent = $wpdb->get_var( $wpdb->prepare(
      "SELECT completion_certificate_sent_at FROM {$this->session_table} WHERE id = %d",
      $session_id
    ) );
    if ( ! empty( $already_sent ) ) {
      return;
    }

    // Vérifier que build_completion_certificate_pdf_pages et _build_simple_pdf_string existent
    if ( ! method_exists( $this, 'build_completion_certificate_pdf_pages' ) || ! method_exists( $this, '_build_simple_pdf_string' ) ) {
      return;
    }

    // Vérifier que la formation a les documents de fin activés
    $formation = $wpdb->get_row( $wpdb->prepare( "SELECT end_documents_enabled FROM {$this->formation_table} WHERE id = %d", $formation_id ) );
    if ( ! $formation || empty( $formation->end_documents_enabled ) ) {
      return;
    }

    // Récupérer les apprenants de la session
    $learners = $wpdb->get_results( $wpdb->prepare(
      "SELECT id, first_name, last_name, usage_last_name, email FROM {$this->learner_table} WHERE session_id = %d AND email != '' AND email IS NOT NULL",
      $session_id
    ) );
    if ( empty( $learners ) ) {
      return;
    }

    // ACDC 3.25.115 — ne pas délivrer d'attestation à un apprenant marqué absent à l'émargement.
    $absent_learner_ids = array();
    if ( class_exists( 'ACDC_Emargement' ) && method_exists( 'ACDC_Emargement', 'get_instance' ) ) {
      $emarg_instance = ACDC_Emargement::get_instance();
      if ( $emarg_instance && isset( $emarg_instance->core ) && ! empty( $emarg_instance->core->table_learners ) ) {
        $emarg_learner_table = $emarg_instance->core->table_learners;
        $absent_ids = $wpdb->get_col( $wpdb->prepare(
          "SELECT learner_id FROM {$emarg_learner_table} WHERE session_id = %d AND is_absent = 1",
          $session_id
        ) );
        foreach ( (array) $absent_ids as $absent_id ) {
          $absent_id = (int) $absent_id;
          if ( $absent_id > 0 ) {
            $absent_learner_ids[ $absent_id ] = true;
          }
        }
      }
    }

    $portal_page_id = (int) get_option( 'acdc_of_learner_portal_page_id', 0 );
    $portal_url     = $portal_page_id ? get_permalink( $portal_page_id ) : home_url( '/' );
    $sent_count     = 0;
    $upload_dir     = wp_upload_dir();

    foreach ( (array) $learners as $learner ) {
      $email = sanitize_email( (string) $learner->email );
      if ( ! $email || ! is_email( $email ) ) {
        continue;
      }

      $learner_id = (int) $learner->id;

      // ACDC 3.25.115 — ne pas délivrer d'attestation à un apprenant marqué absent à l'émargement.
      if ( isset( $absent_learner_ids[ $learner_id ] ) ) {
        continue;
      }

      // Trouver la training_registration liée (learner_id + formation_id)
      $registration = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$this->training_registration_table} WHERE learner_id = %d AND formation_id = %d AND is_draft = 0 ORDER BY id DESC LIMIT 1",
        $learner_id,
        $formation_id
      ) );

      if ( ! $registration ) {
        continue;
      }

      // Générer le PDF en mémoire si pas encore existant
      $cert_url  = ! empty( $registration->completion_certificate_document_url )  ? (string) $registration->completion_certificate_document_url  : '';
      $cert_path = ! empty( $registration->completion_certificate_document_path ) ? (string) $registration->completion_certificate_document_path : '';

      if ( empty( $cert_url ) || empty( $cert_path ) || ! file_exists( $cert_path ) ) {
        // Générer le PDF en capturant l'output
        $context     = $this->get_completion_certificate_context( $registration );
        $pages       = $this->build_completion_certificate_pdf_pages( $registration, $context );
        $filename    = sanitize_file_name( "certificat-realisation-" . (int) $registration->id . ".pdf" );

        $pdf_content = $this->_build_simple_pdf_string( $pages );

        if ( empty( $pdf_content ) ) {
          continue;
        }

        // Sauvegarder le fichier dans wp-uploads
        $subdir    = $upload_dir['basedir'] . '/acdc-certificates';
        if ( ! file_exists( $subdir ) ) {
          wp_mkdir_p( $subdir );
        }
        $filepath = $subdir . '/' . $filename;
        if ( file_put_contents( $filepath, $pdf_content ) === false ) {
          continue;
        }
        $fileurl  = $upload_dir['baseurl'] . '/acdc-certificates/' . $filename;

        // Stocker l'URL et le path en BDD
        $wpdb->update(
          $this->training_registration_table,
          array(
            'completion_certificate_document_url'  => esc_url_raw( $fileurl ),
            'completion_certificate_document_path' => sanitize_text_field( $filepath ),
            'updated_at' => current_time( 'mysql' ),
          ),
          array( 'id' => (int) $registration->id ),
          array( '%s', '%s', '%s' ),
          array( '%d' )
        );

        $cert_url = $fileurl;
      }

      // Envoyer l'e-mail de notification avec lien vers le portail apprenant
      $prenom   = ! empty( $learner->first_name ) ? (string) $learner->first_name : '';
      $nom      = ! empty( $learner->usage_last_name ) ? (string) $learner->usage_last_name : ( ! empty( $learner->last_name ) ? (string) $learner->last_name : '' );
      $greeting = trim( $prenom ) ?: trim( $prenom . ' ' . $nom );

      $formation_title = $wpdb->get_var( $wpdb->prepare( "SELECT title FROM {$this->formation_table} WHERE id = %d", $formation_id ) );
      $formation_title = ! empty( $formation_title ) ? (string) $formation_title : "votre formation";

      $body_html  = "<p style=\"font-size:19px;line-height:1.7;margin:0 0 20px;\">Votre formation <strong>" . esc_html( $formation_title ) . "</strong> est maintenant terminée. Votre attestation de réalisation est disponible dans votre espace apprenant.</p>";
      $body_html .= "<p style=\"margin:24px 0;text-align:center;\"><a href=\"" . esc_url( $portal_url ) . "\" style=\"display:inline-block;padding:14px 28px;background:#C5A253;color:#0B0706;text-decoration:none;border-radius:8px;font-weight:700;font-size:16px;\">Télécharger mon attestation</a></p>";
      $body_html .= "<p style=\"font-size:15px;color:#667085;margin:16px 0 0;\">Vous trouverez votre attestation dans la rubrique <strong>Certificats et attestations</strong> de votre espace personnel.</p>";

      $this->acdc_send_transactional_email(
        $email,
        "Votre attestation de réalisation — " . $formation_title,
        array(
          'greeting_name' => $greeting,
          'intro_html'    => '',
          'body_html'     => $body_html,
          'footer_notice' => "Cet e-mail est envoyé automatiquement à l'issue de votre formation. Vos données sont traitées conformément au RGPD.",
        ),
        array(
          'source_module'       => 'sessions',
          'source_action'       => 'completion_certificate_auto',
          'related_entity_type' => 'session',
          'related_entity_id'   => $session_id,
          'email_category'      => 'attestation',
          'email_audience'      => 'apprenant',
        )
      );

      $sent_count++;
    }

    // Tracer la date d'envoi sur la session (même si 0 envoi, pour éviter les re-tentatives)
    $wpdb->update(
      $this->session_table,
      array( 'completion_certificate_sent_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ),
      array( 'id' => $session_id ),
      array( '%s', '%s' ),
      array( '%d' )
    );
  }


  private function _auto_send_end_training_certificate_for_session( $session_id, $formation_id ) {
    global $wpdb;
    $session_id   = absint( $session_id );
    $formation_id = absint( $formation_id );
    if ( ! $session_id || ! $formation_id ) { return; }
    $already_sent = $wpdb->get_var( $wpdb->prepare( "SELECT end_training_certificate_sent_at FROM {$this->session_table} WHERE id = %d", $session_id ) );
    if ( ! empty( $already_sent ) ) { return; }
    if ( ! method_exists( $this, 'build_end_training_certificate_pdf_pages' ) || ! method_exists( $this, '_build_simple_pdf_string' ) ) { return; }
    $formation = $wpdb->get_row( $wpdb->prepare( "SELECT end_documents_enabled FROM {$this->formation_table} WHERE id = %d", $formation_id ) );
    if ( ! $formation || empty( $formation->end_documents_enabled ) ) { return; }
    $learners = $wpdb->get_results( $wpdb->prepare( "SELECT id, first_name, last_name, usage_last_name, email FROM {$this->learner_table} WHERE session_id = %d AND email != '' AND email IS NOT NULL", $session_id ) );
    if ( empty( $learners ) ) { return; }
    // ACDC 3.25.115 — ne pas délivrer d'attestation de fin de formation à un apprenant
    // marqué absent à l'émargement (cohérent avec le certificat de réalisation).
    $absent_learner_ids = array();
    if ( class_exists( 'ACDC_Emargement' ) && method_exists( 'ACDC_Emargement', 'get_instance' ) ) {
      $emarg_instance = ACDC_Emargement::get_instance();
      if ( $emarg_instance && isset( $emarg_instance->core ) && ! empty( $emarg_instance->core->table_learners ) ) {
        $emarg_learner_table = $emarg_instance->core->table_learners;
        $absent_ids = $wpdb->get_col( $wpdb->prepare( "SELECT learner_id FROM {$emarg_learner_table} WHERE session_id = %d AND is_absent = 1", $session_id ) );
        foreach ( (array) $absent_ids as $aid ) { $absent_learner_ids[ (int) $aid ] = true; }
      }
    }
    $portal_page_id = (int) get_option( 'acdc_of_learner_portal_page_id', 0 );
    $portal_url     = $portal_page_id ? get_permalink( $portal_page_id ) : home_url( '/' );
    $upload_dir     = wp_upload_dir();
    $sent_count     = 0;
    foreach ( (array) $learners as $learner ) {
      $email = sanitize_email( (string) $learner->email );
      if ( ! $email || ! is_email( $email ) ) { continue; }
      $learner_id   = (int) $learner->id;
      if ( isset( $absent_learner_ids[ $learner_id ] ) ) { continue; }
      $registration = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->training_registration_table} WHERE learner_id = %d AND formation_id = %d AND is_draft = 0 ORDER BY id DESC LIMIT 1", $learner_id, $formation_id ) );
      if ( ! $registration ) { continue; }
      $cert_url  = ! empty( $registration->end_training_certificate_document_url )  ? (string) $registration->end_training_certificate_document_url  : '';
      $cert_path = ! empty( $registration->end_training_certificate_document_path ) ? (string) $registration->end_training_certificate_document_path : '';
      if ( empty( $cert_url ) || empty( $cert_path ) || ! file_exists( $cert_path ) ) {
        $context     = $this->get_end_training_certificate_context( $registration );
        $pages       = $this->build_end_training_certificate_pdf_pages( $registration, $context );
        $filename    = sanitize_file_name( 'attestation-fin-formation-' . (int) $registration->id . '.pdf' );
        $pdf_content = $this->_build_simple_pdf_string( $pages );
        if ( empty( $pdf_content ) ) { continue; }
        $subdir = $upload_dir['basedir'] . '/acdc-certificates';
        if ( ! file_exists( $subdir ) ) { wp_mkdir_p( $subdir ); }
        $filepath = $subdir . '/' . $filename;
        if ( file_put_contents( $filepath, $pdf_content ) === false ) { continue; }
        $fileurl = $upload_dir['baseurl'] . '/acdc-certificates/' . $filename;
        $wpdb->update( $this->training_registration_table, array( 'end_training_certificate_document_url' => esc_url_raw( $fileurl ), 'end_training_certificate_document_path' => sanitize_text_field( $filepath ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $registration->id ), array( '%s', '%s', '%s' ), array( '%d' ) );
        $cert_url = $fileurl;
      }
      $prenom  = ! empty( $learner->first_name ) ? (string) $learner->first_name : '';
      $nom     = ! empty( $learner->usage_last_name ) ? (string) $learner->usage_last_name : ( ! empty( $learner->last_name ) ? (string) $learner->last_name : '' );
      $greeting = trim( $prenom ) ?: trim( $prenom . ' ' . $nom );
      $formation_title = $wpdb->get_var( $wpdb->prepare( "SELECT title FROM {$this->formation_table} WHERE id = %d", $formation_id ) );
      $formation_title = ! empty( $formation_title ) ? (string) $formation_title : 'votre formation';
      $body_html  = '<p style="font-size:19px;line-height:1.7;margin:0 0 20px;">Votre attestation de fin de formation <strong>' . esc_html( $formation_title ) . '</strong> est disponible dans votre espace apprenant.</p>';
      $body_html .= '<p style="margin:24px 0;text-align:center;"><a href="' . esc_url( $portal_url ) . '" style="display:inline-block;padding:14px 28px;background:#C5A253;color:#0B0706;text-decoration:none;border-radius:8px;font-weight:700;font-size:16px;">Accéder à mon espace</a></p>';
      $body_html .= '<p style="font-size:15px;color:#667085;margin:16px 0 0;">Vous trouverez votre attestation dans la rubrique <strong>Certificats et attestations</strong> de votre espace personnel.</p>';
      $this->acdc_send_transactional_email( $email, 'Votre attestation de fin de formation — ' . $formation_title, array( 'greeting_name' => $greeting, 'intro_html' => '', 'body_html' => $body_html, 'footer_notice' => "Cet e-mail est envoyé automatiquement à l'issue de votre formation. Vos données sont traitées conformément au RGPD." ), array( 'source_module' => 'sessions', 'source_action' => 'end_training_certificate_auto', 'related_entity_type' => 'session', 'related_entity_id' => $session_id, 'email_category' => 'attestation', 'email_audience' => 'apprenant' ) );
      $sent_count++;
    }
    $wpdb->update( $this->session_table, array( 'end_training_certificate_sent_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $session_id ), array( '%s', '%s' ), array( '%d' ) );
  }

  /**
   * ACDC 3.21.64 — Cron quotidien : envoi du test de positionnement 48h avant la séance.
   *
   * Conditions de déclenchement pour chaque session :
   * - Formation avec positioning_test_enabled = 1
   * - Quiz de type 'positioning' actif lié à la formation
   * - Date de début dans 0 à 2 jours (days_until <= 2)
   * - positioning_sent_at vide (anti-doublon)
   * - Des apprenants avec e-mail dans la session
   */
  public function process_positioning_test_cron() {
    global $wpdb;

    if ( empty( $this->session_table ) || empty( $this->formation_table ) || empty( $this->learner_table ) ) {
      return;
    }
    if ( ! method_exists( $this, 'get_qz_table' ) || ! method_exists( $this, 'create_qz_async_dispatch' ) ) {
      return;
    }

    // ACDC 3.25.115 — base calendaire homogène (même fuseau/heure que $start_ts) plutôt que
    // de comparer current_time('timestamp') (heure locale WP) à strtotime() (fuseau serveur).
    $today_ts = strtotime( current_time( 'Y-m-d' ) . ' 08:00:00' );

    $sessions = $wpdb->get_results(
      "SELECT s.*, f.title AS formation_title, f.positioning_test_enabled
       FROM {$this->session_table} s
       LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id
       WHERE s.is_draft = 0
         AND COALESCE(f.positioning_test_enabled, 0) = 1
         AND COALESCE(s.status, '') NOT IN ('Annulée','Annulee','Terminée','Terminee')
         AND ( s.start_date IS NOT NULL OR s.start_at IS NOT NULL )
         AND ( s.positioning_sent_at IS NULL OR s.positioning_sent_at = '' )
       ORDER BY COALESCE(s.start_date, DATE(s.start_at)) ASC, s.id ASC"
    );

    if ( empty( $sessions ) ) {
      return;
    }

    foreach ( (array) $sessions as $session ) {
      $start_date = ! empty( $session->start_date )
        ? (string) $session->start_date
        : ( ! empty( $session->start_at ) ? substr( (string) $session->start_at, 0, 10 ) : '' );

      if ( ! $start_date ) {
        continue;
      }

      $start_ts   = strtotime( $start_date . ' 08:00:00' );
      // ACDC 3.25.115 — écart en jours calendaires (même base 08:00:00 que $today_ts).
      $days_until = (int) floor( ( $start_ts - $today_ts ) / DAY_IN_SECONDS );

      // Fenêtre : 0 à 2 jours avant le début (inclut aujourd'hui et dans 48h)
      if ( $days_until < 0 || $days_until > 2 ) {
        continue;
      }

      $session_id   = (int) $session->id;
      $formation_id = (int) $session->formation_id;

      $this->_auto_dispatch_positioning_for_session( $session_id, $formation_id );
    }
  }


  /**
   * ACDC 3.21.64 — Dispatche le quiz de positionnement aux apprenants d'une session.
   * Anti-doublon : vérifie positioning_sent_at + participants déjà existants.
   *
   * @param int $session_id
   * @param int $formation_id
   */
  private function _auto_dispatch_positioning_for_session( $session_id, $formation_id ) {
    global $wpdb;

    $session_id   = absint( $session_id );
    $formation_id = absint( $formation_id );
    if ( ! $session_id || ! $formation_id ) {
      return;
    }

    $tbl_qz_quizzes = $this->get_qz_table( 'quizzes' );
    $tbl_qz_sessions = $this->get_qz_table( 'sessions' );
    $tbl_qz_parts   = $this->get_qz_table( 'participants' );

    foreach ( array( $tbl_qz_quizzes, $tbl_qz_sessions, $tbl_qz_parts ) as $tbl ) {
      if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) !== $tbl ) {
        return;
      }
    }

    // Quiz de positionnement actif pour cette formation
    $quiz = $wpdb->get_row( $wpdb->prepare(
      "SELECT id FROM {$tbl_qz_quizzes}
       WHERE formation_id = %d
         AND quiz_purpose = 'positioning'
         AND is_current   = 1
         AND status       = 'active'
       LIMIT 1",
      $formation_id
    ) );

    if ( ! $quiz ) {
      // Pas de quiz de positionnement lié — tracer quand même pour ne pas re-tenter
      $wpdb->update(
        $this->session_table,
        array( 'positioning_sent_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ),
        array( 'id' => $session_id ),
        array( '%s', '%s' ),
        array( '%d' )
      );
      return;
    }

    $quiz_id = (int) $quiz->id;

    // Apprenants de cette session
    $learners = $this->get_qz_learners_for_formation( $formation_id, array( 'session_id' => $session_id ) );
    if ( empty( $learners ) ) {
      $wpdb->update(
        $this->session_table,
        array( 'positioning_sent_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ),
        array( 'id' => $session_id ),
        array( '%s', '%s' ),
        array( '%d' )
      );
      return;
    }

    // Filtrage anti-doublon
    $recipients = array();
    foreach ( $learners as $l ) {
      $learner_id = (int) $l->id;
      if ( $learner_id <= 0 || empty( $l->email ) ) {
        continue;
      }
      $already = $wpdb->get_var( $wpdb->prepare(
        "SELECT p.id
         FROM {$tbl_qz_parts} p
         INNER JOIN {$tbl_qz_sessions} qs ON qs.id = p.session_id
         WHERE qs.quiz_id   = %d
           AND p.learner_id = %d
           AND p.status NOT IN ('expired', 'cancelled')
         LIMIT 1",
        $quiz_id, $learner_id
      ) );
      if ( $already ) {
        continue;
      }
      $recipients[] = array(
        'email'      => $l->email,
        'first_name' => $l->first_name,
        'last_name'  => $l->last_name,
        'learner_id' => $learner_id,
      );
    }

    // Tracer dans tous les cas pour éviter les re-tentatives
    $wpdb->update(
      $this->session_table,
      array( 'positioning_sent_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ),
      array( 'id' => $session_id ),
      array( '%s', '%s' ),
      array( '%d' )
    );

    if ( empty( $recipients ) ) {
      return;
    }

    $result = $this->create_qz_async_dispatch( $quiz_id, $recipients, array(
      'expires_in_days'      => 3,
      'reminders_enabled'    => false,
      'formation_session_id' => $session_id,
    ) );

    if ( method_exists( $this, 'log_qz_event' ) ) {
      if ( is_wp_error( $result ) ) {
        $this->log_qz_event( array(
          'quiz_id'     => $quiz_id,
          'event_type'  => 'positioning_auto_dispatch_error',
          'event_label' => sprintf(
            'Erreur envoi auto positionnement : session OF #%d — %s',
            $session_id,
            $result->get_error_message()
          ),
        ) );
      } else {
        $this->log_qz_event( array(
          'quiz_id'       => $quiz_id,
          'event_type'    => 'positioning_auto_dispatch_sent',
          'event_label'   => sprintf(
            'Envoi automatique test de positionnement (session OF #%d) : %d destinataire(s)',
            $session_id,
            count( $recipients )
          ),
          'event_payload' => array(
            'formation_session_id' => $session_id,
            'recipients_count'     => count( $recipients ),
            'trigger'              => 'positioning_48h_cron',
          ),
        ) );
      }
    }
  }

  /**
   * ACDC 3.24.12 — R-12 : Cron alerte absences / ruptures de parcours (indicateur 12 Qualiopi).
   *
   * Tournée quotidienne. Pour chaque apprenant inscrit :
   * 1. Calcule le nombre de séances passées où il était absent (is_absent = 1 dans emarg_learners).
   * 2. Si ce nombre > seuil (option acdc_of_absence_alert_threshold, défaut 1) ET qu'aucune
   *    alerte n'a été envoyée depuis 7 jours (absence_alert_sent_at) :
   *    — Met à jour absence_count et absence_alert_sent_at dans la table learners.
   *    — Envoie un e-mail d'alerte à l'adresse admin (acdc_of_company_profile[email]).
   *
   * Anti-doublon : une alerte par apprenant tous les 7 jours maximum.
   * Chaînes PHP sur une ligne — interdit multi-lignes (Opcache LiteSpeed).
   */
  public function process_absence_alert_cron() {
    global $wpdb;

    if ( empty( $this->learner_table ) || empty( $this->session_table ) || empty( $this->formation_table ) ) {
      return;
    }

    $emarg_learner_table = $wpdb->prefix . 'acdc_of_emarg_learners';
    $emarg_session_table = $wpdb->prefix . 'acdc_of_emarg_sessions';

    // Vérifier que les tables d'émargement existent
    if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$emarg_learner_table}'" ) ) {
      return;
    }

    $threshold  = max( 1, (int) get_option( 'acdc_of_absence_alert_threshold', 1 ) );
    $now_ts     = current_time( 'timestamp' );
    $now_mysql  = current_time( 'mysql' );
    $lockout_ts = $now_ts - ( 7 * DAY_IN_SECONDS );

    $company_profile = get_option( 'acdc_of_company_profile', array() );
    $admin_email     = ! empty( $company_profile['email'] ) ? sanitize_email( (string) $company_profile['email'] ) : sanitize_email( (string) get_option( 'admin_email' ) );

    if ( ! $admin_email || ! is_email( $admin_email ) ) {
      return;
    }

    // Récupérer les apprenants ayant au moins $threshold absences dans des séances passées
    // via la table d'émargement (is_absent = 1 + séance passée)
    $learner_absence_rows = $wpdb->get_results( $wpdb->prepare(
      "SELECT el.learner_id, COUNT(el.id) AS nb_absences, MAX(l.first_name) AS first_name, MAX(l.last_name) AS last_name, MAX(l.usage_last_name) AS usage_last_name, MAX(l.email) AS learner_email, MAX(l.absence_alert_sent_at) AS absence_alert_sent_at, MAX(l.session_id) AS session_id, MAX(s.start_date) AS start_date, MAX(s.start_at) AS start_at, MAX(f.title) AS formation_title
       FROM {$emarg_learner_table} el
       INNER JOIN {$emarg_session_table} es ON es.id = el.emarg_session_id
       INNER JOIN {$this->session_table} s ON s.id = es.session_id
       INNER JOIN {$this->formation_table} f ON f.id = s.formation_id
       LEFT JOIN {$this->learner_table} l ON l.id = el.learner_id
       WHERE el.is_absent = 1
         AND el.learner_id IS NOT NULL
         AND el.learner_id > 0
         AND COALESCE(s.start_date, DATE(s.start_at)) < %s
       GROUP BY el.learner_id
       HAVING nb_absences >= %d",
      current_time( 'Y-m-d' ),
      $threshold
    ) );

    if ( empty( $learner_absence_rows ) ) {
      return;
    }

    foreach ( (array) $learner_absence_rows as $row ) {
      $learner_id  = (int) $row->learner_id;
      $nb_absences = (int) $row->nb_absences;

      if ( $learner_id <= 0 ) {
        continue;
      }

      // Anti-doublon : ne pas renvoyer si alerte déjà envoyée dans les 7 derniers jours
      if ( ! empty( $row->absence_alert_sent_at ) ) {
        $last_sent_ts = (int) strtotime( (string) $row->absence_alert_sent_at );
        if ( $last_sent_ts >= $lockout_ts ) {
          continue;
        }
      }

      $prenom           = ! empty( $row->first_name ) ? (string) $row->first_name : '';
      $nom              = ! empty( $row->usage_last_name ) ? (string) $row->usage_last_name : ( ! empty( $row->last_name ) ? (string) $row->last_name : '' );
      $learner_label    = trim( $prenom . ' ' . $nom ) ?: 'Apprenant #' . $learner_id;
      $formation_title  = ! empty( $row->formation_title ) ? (string) $row->formation_title : '—';
      $learner_email    = ! empty( $row->learner_email ) ? (string) $row->learner_email : '—';
      $alert_date       = wp_date( 'j/m/Y à H\hi', $now_ts );

      // URL directe vers la fiche apprenant dans le back-office
      $learner_url = admin_url( 'admin.php?page=acdc-of-learners&action=edit&item_id=' . $learner_id );

      $summary_rows = array(
        array( 'label' => 'Apprenant',      'value' => $learner_label ),
        array( 'label' => 'E-mail',         'value' => $learner_email ),
        array( 'label' => 'Formation',      'value' => $formation_title ),
        array( 'label' => 'Nb absences',    'value' => $nb_absences . ( 1 === $nb_absences ? ' séance' : ' séances' ) ),
        array( 'label' => 'Seuil alerte',   'value' => $threshold . ( 1 === $threshold ? ' absence' : ' absences' ) ),
        array( 'label' => 'Détecté le',     'value' => $alert_date ),
      );

      $body_html = '<p style="font-size:17px;line-height:1.7;margin:0 0 20px;">Un apprenant dépasse le seuil d\'absences configuré. Une action de suivi est recommandée pour prévenir la rupture de parcours.</p>';
      $body_html .= '<p style="margin:24px 0;text-align:center;"><a href="' . esc_url( $learner_url ) . '" style="display:inline-block;padding:12px 24px;background:#C5A253;color:#0B0706;text-decoration:none;border-radius:8px;font-weight:700;font-size:15px;">Ouvrir la fiche apprenant</a></p>';

      $this->acdc_send_transactional_email(
        $admin_email,
        'Alerte absence — ' . $learner_label . ' (' . $nb_absences . ' abs.)',
        array(
          'greeting_name' => 'ACDC Formation',
          'intro_html'    => '',
          'summary_title' => 'ALERTE RUPTURE DE PARCOURS (IND. 12)',
          'summary_rows'  => $summary_rows,
          'body_html'     => $body_html,
          'footer_notice' => 'Alerte automatique — indicateur 12 Qualiopi. Prévention des ruptures de parcours.',
        ),
        array(
          'source_module'       => 'sessions',
          'source_action'       => 'absence_alert_auto',
          'related_entity_type' => 'learner',
          'related_entity_id'   => $learner_id,
          'email_category'      => 'alerte_absence',
          'email_audience'      => 'interne',
        )
      );

      // Mettre à jour absence_alert_sent_at et absence_count dans la fiche apprenant
      $wpdb->update(
        $this->learner_table,
        array(
          'absence_alert_sent_at' => $now_mysql,
          'absence_count'         => $nb_absences,
          'updated_at'            => $now_mysql,
        ),
        array( 'id' => $learner_id )
      );
    }
  }
}
