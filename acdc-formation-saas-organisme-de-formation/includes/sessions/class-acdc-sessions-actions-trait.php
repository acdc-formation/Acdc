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

  /**
   * Les demi-journées d'une séance qui n'en déclare pas.
   *
   * ACDC 3.25.304. La règle elle-même vit dans src/Support/HalfDaySplit.php,
   * hors de WordPress et vérifiée par 22 cas : ici on ne fait que lui donner
   * les horaires de pause de l'organisme, et rendre du JSON.
   */
  private function acdc_deduire_demi_journees( $start_at, $end_at ) {
    $reglages = get_option( 'acdc_of_settings', array() );
    $pause_d  = is_array( $reglages ) && ! empty( $reglages['default_am_end'] )   ? (string) $reglages['default_am_end']   : \ACDC\Support\HalfDaySplit::PAUSE_DEBUT_DEFAUT;
    $pause_f  = is_array( $reglages ) && ! empty( $reglages['default_pm_start'] ) ? (string) $reglages['default_pm_start'] : \ACDC\Support\HalfDaySplit::PAUSE_FIN_DEFAUT;
    $creneaux = \ACDC\Support\HalfDaySplit::decouper( (string) $start_at, (string) $end_at, $pause_d, $pause_f );
    /* Aucun découpage possible — dates absentes ou incohérentes : on n'écrit
       rien plutôt qu'un planning vide, qui passerait pour une déclaration. */
    return $creneaux ? wp_json_encode( $creneaux ) : null;
  }

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

    /* ACDC 3.25.188 — On relit la séance AVANT de composer les données : les
       champs que le formulaire ne transmet pas doivent être conservés, jamais
       remis à NULL. */
    $acdc_existing_session = $session_id
      ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->session_table} WHERE id = %d", $session_id ) )
      : null;

    /* ACDC 3.25.268 — LE BROUILLON NE SE LÈVE PLUS PAR INADVERTANCE.
       Ce formulaire ne transmet pas `is_draft`, et son menu Statut ne propose
       pas « Brouillon » : ouvrir une séance en brouillon et l'enregistrer, même
       sans rien changer, la validait donc silencieusement — statut « Planifiée »
       compris. C'était sans conséquence tant que le brouillon n'était qu'une
       étagère. Depuis cette version il retient la convocation : le lever d'un
       enregistrement distrait ferait partir une convocation annonçant un
       formateur que personne n'a désigné.
       On applique donc ici la règle déjà écrite en 3.25.190 — un champ absent du
       formulaire n'est pas un champ vidé — et la validation devient un GESTE :
       le bouton « Valider la séance ». Il exige un formateur, parce qu'une
       séance validée est une séance dont on sait qui l'anime. */
    $acdc_etait_brouillon = $acdc_existing_session
      && \ACDC\Support\SessionDraftGate::isDraft( $acdc_existing_session );
    $acdc_veut_valider    = ! empty( $_POST['validate_session'] );

    if ( $acdc_etait_brouillon && $acdc_veut_valider && null === $trainer_id_session ) {
      $this->acdc_store_form_state( 'session', $_POST );
      $this->redirect_to_portal(
        'sessions',
        'Désignez le formateur avant de valider : la convocation annonce un intervenant, et un apprenant convoqué sans formateur se présente pour rien.',
        'error',
        array( 'action' => 'edit', 'item_id' => $session_id )
      );
    }

    $acdc_reste_brouillon = $acdc_etait_brouillon && ! $acdc_veut_valider;

    $acdc_start_at = $this->acdc_session_datetime_field( 'start', $acdc_existing_session );

    $acdc_end_at   = $this->acdc_session_datetime_field( 'end', $acdc_existing_session );

    $data = array(
      'formation_id' => isset( $_POST['formation_id'] ) && absint( wp_unslash( $_POST['formation_id'] ) ) ? absint( wp_unslash( $_POST['formation_id'] ) ) : null,
      'company_id'  => isset( $_POST['company_id'] ) && absint( wp_unslash( $_POST['company_id'] ) ) ? absint( wp_unslash( $_POST['company_id'] ) ) : null,
      'trainer_id'  => $trainer_id_session,
      'title'    => $title,
      /* ACDC 3.25.190 — Ces trois colonnes n'existent PAS dans l'écran de
         modification d'une séance. Les écrire avec une chaîne vide par défaut
         revenait à les effacer à chaque enregistrement : ouvrir la fiche d'une
         séance et cliquer « Modifier », sans rien toucher, lui faisait perdre son
         type, sa modalité et sa méthode d'émargement — donc sa feuille
         d'émargement. La règle est désormais générale : on ne réécrit que ce que
         le formulaire transmet réellement. */
      'session_type' => $this->acdc_session_preserved_field( 'session_type', $acdc_existing_session, '' ),
      'attendance_method' => $this->acdc_session_preserved_field( 'attendance_method', $acdc_existing_session, '' ),
      'session_format' => $this->acdc_session_preserved_field( 'session_format', $acdc_existing_session, '' ),
      'start_at'   => $acdc_start_at,
      'end_at'    => $acdc_end_at,
      /* ACDC 3.25.310 — LA RÈGLE ÉNONCÉE JUSTE AU-DESSUS ÉTAIT ENFREINTE ICI.
         Le formulaire de séance porte « Date de début ». Il ne porte PAS « Date
         de fin » : « end_date » était donc réécrite à NULL à chaque
         enregistrement, y compris sur une séance qui en avait une. Une séance
         de trois jours, rouverte et enregistrée sans rien changer, perdait sa
         date de fin — et disparaissait des vues qui bornent sur elle.
         Ces deux colonnes se déduisent de start_at / end_at, qui sont, eux,
         toujours transmis. On ne les efface plus jamais : à défaut de saisie, on
         les recalcule ; à défaut de tout, on garde ce qui était en base. */
      'start_date'  => $this->acdc_session_date_du_jour( $_POST['start_date'] ?? null, $acdc_start_at, $acdc_existing_session, 'start_date' ),
      'end_date'   => $this->acdc_session_date_du_jour( $_POST['end_date'] ?? null, $acdc_end_at, $acdc_existing_session, 'end_date' ),
      'location'   => isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '',
      'remote_link' => isset( $_POST['remote_link'] ) ? esc_url_raw( wp_unslash( $_POST['remote_link'] ) ) : '',
      'status'    => $acdc_reste_brouillon
        ? \ACDC\Support\SessionDraftGate::STATUT_BROUILLON
        : ( isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Planifiée' ),
      'max_learners' => isset( $_POST['max_learners'] ) ? absint( wp_unslash( $_POST['max_learners'] ) ) : 0,
      'is_draft'  => isset( $_POST['is_draft'] ) ? absint( wp_unslash( $_POST['is_draft'] ) ) : ( $acdc_reste_brouillon ? 1 : 0 ),
      /* ACDC 3.25.188 — Le planning détaillé n'est pas envoyé par le formulaire de
         modification : l'écraser par NULL détruisait les demi-journées d'une
         séance née d'une proposition. On ne remplace que ce qui est transmis. */
      /* ACDC 3.25.304 — UNE SÉANCE NÉE À LA MAIN NAISSAIT SANS SES DEMI-JOURNÉES.
         Aucun formulaire n'envoie schedule_json : seule une séance née d'une
         convention le recevait. Toutes les autres restaient à NULL — et tout ce
         qui compte des heures retombe alors sur « début → fin », PAUSE DÉJEUNER
         COMPRISE. D'où « Heures de formation dispensées : 16:00:00 » pour deux
         journées qui en font 14, et le même écart sur le BPF et les
         statistiques du formateur.
         On ne fabrique pas d'horaires : on coupe la plage annoncée là où
         l'organisme situe sa pause, et seulement si elle l'enjambe. Les bornes
         restent celles de la séance. */
      'schedule_json' => isset( $_POST['schedule_json'] )
        ? wp_json_encode( wp_unslash( $_POST['schedule_json'] ) )
        : ( ( $acdc_existing_session && ! empty( $acdc_existing_session->schedule_json ) )
            ? $acdc_existing_session->schedule_json
            : $this->acdc_deduire_demi_journees( $acdc_start_at, $acdc_end_at ) ),
      'notes'    => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
      'updated_at'  => $this->now_mysql(),
    );

    if ( $session_id ) {
      /* ACDC 3.25.312 — On retient les dates AVANT l'écriture : après, il est
         trop tard pour savoir si elles ont bougé. */
      $__acdc_ancien_debut = $acdc_existing_session && isset( $acdc_existing_session->start_at ) ? (string) $acdc_existing_session->start_at : '';
      $__acdc_ancienne_fin = $acdc_existing_session && isset( $acdc_existing_session->end_at ) ? (string) $acdc_existing_session->end_at : '';

      $result = $wpdb->update( $this->session_table, $data, array( 'id' => $session_id ) );
      if ( false === $result ) {
        $this->acdc_store_form_state( 'session', $_POST );
        $this->redirect_to_portal( 'sessions', 'Erreur lors de la mise à jour. Veuillez réessayer.', 'error', array( 'action' => 'edit', 'item_id' => $session_id ) );
      }
      $message = 'Session mise à jour.';

      /* ACDC 3.25.312 — LES ENQUÊTES SUIVENT LES DATES DE LA SÉANCE.
         Leurs échéances étaient calculées une fois, à la création, et plus
         jamais relues : déplacer une séance laissait des rendez-vous d'envoi
         accrochés à l'ancienne date. Le 16 août, six échéances nées avec des
         dates de mai sont parties ensemble en août, douze messages en une
         seconde, et tout est tombé en indésirables.
         On efface le plan périmé : les fabriques le refont, avec les bonnes
         dates. Rien n'est touché si les dates n'ont pas changé. */
      if ( ( $__acdc_ancien_debut !== (string) $acdc_start_at || $__acdc_ancienne_fin !== (string) $acdc_end_at )
        && method_exists( $this, 'acdc_replanifier_enquetes_de_seance' ) ) {
        $__acdc_replanifiees = (int) $this->acdc_replanifier_enquetes_de_seance( $session_id );
        if ( $__acdc_replanifiees > 0 ) {
          $message .= sprintf( ' %d enquête(s) automatique(s) replanifiée(s) sur les nouvelles dates.', $__acdc_replanifiees );
        }
      }
      if ( $acdc_etait_brouillon && $acdc_veut_valider ) {
        $message = 'Séance validée : elle rejoint le calendrier des séances, et la convocation retenue partira d’elle-même.';
      } elseif ( $acdc_reste_brouillon ) {
        $message = 'Séance enregistrée, toujours en brouillon. Cliquez « Valider la séance » quand elle est complète.';
      }
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
      /* ACDC 3.25.271 — Une séance saisie à la main est sa propre action : ses
         quiz se préparent comme ceux d'une séance née d'une convention.
         L'appel est idempotent, une modification ne double donc rien. */
      if ( method_exists( $this, 'acdc_prepare_action_quizzes' ) ) {
        $this->acdc_prepare_action_quizzes( array( $session_id ) );
      }
    }

    $return_after_save = $this->acdc_get_post_return_after_save( 'edit', array( 'edit', 'list', 'new' ) );
    $success_args = array( 'action' => $return_after_save );
    if ( 'edit' === $return_after_save ) {
      $success_args['item_id'] = $session_id;
    }

    $this->redirect_to_portal( 'sessions', $message, 'success', $success_args );
  }

  /**
   * ACDC 3.25.190 — Un champ absent du formulaire n'est pas un champ vidé.
   *
   * Ne remplace la valeur en base que si la requête la transmet. À la création,
   * faute de valeur existante, on retombe sur le défaut.
   */
  /**
   * ACDC 3.25.310 — LA DATE D'UN JOUR DE SÉANCE, DANS CET ORDRE DE PRÉFÉRENCE.
   *
   * 1. ce que le formulaire transmet, s'il transmet quelque chose ;
   * 2. sinon la journée de l'horaire correspondant, qui est toujours transmis ;
   * 3. sinon ce que la séance portait déjà.
   *
   * On ne rend jamais NULL quand une valeur existe quelque part : c'est
   * exactement ce qui effaçait la date de fin d'une séance de plusieurs jours à
   * chaque enregistrement.
   *
   * @param mixed       $poste    Valeur du formulaire, ou null s'il n'a pas ce champ.
   * @param string      $horaire  « AAAA-MM-JJ HH:MM:SS » correspondant.
   * @param object|null $existant La séance telle qu'elle est en base.
   * @param string      $colonne  Nom de la colonne, pour le repli.
   */
  private function acdc_session_date_du_jour( $poste, $horaire, $existant, $colonne ) {
    if ( null !== $poste && '' !== trim( (string) $poste ) ) {
      return sanitize_text_field( wp_unslash( $poste ) );
    }
    $ts = $horaire ? strtotime( (string) $horaire ) : false;
    if ( $ts ) {
      return gmdate( 'Y-m-d', $ts );
    }
    return ( $existant && ! empty( $existant->$colonne ) ) ? (string) $existant->$colonne : null;
  }

  private function acdc_session_preserved_field( $key, $existing, $default = '' ) {
    if ( isset( $_POST[ $key ] ) ) {
      return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
    }
    return ( $existing && isset( $existing->$key ) ) ? (string) $existing->$key : $default;
  }

  /**
   * ACDC 3.25.188 — Composition d'un horaire de séance à partir du formulaire.
   *
   * Deux défauts se corrigeaient ensemble ici. D'abord, l'écran de modification
   * n'offrait AUCUN champ d'heure : une séance dépourvue d'horaires ne pouvait
   * être corrigée que par suppression et recréation, ce qui est inacceptable sur
   * un dossier réel. Ensuite — et c'est le plus grave — comme le formulaire
   * n'envoyait ni start_at ni end_at, chaque enregistrement les remettait à
   * NULL : modifier le lieu d'une séance née d'une proposition lui effaçait ses
   * demi-journées, donc ses rappels d'émargement.
   *
   * Règle : on ne remplace que ce que le formulaire déclare transmettre. Le
   * marqueur `session_times_posted` distingue « le champ est vide, efface » de
   * « ce formulaire ne parle pas des heures, n'y touche pas ».
   */
  private function acdc_session_datetime_field( $prefix, $existing ) {
    $column = $prefix . '_at';

    $raw = isset( $_POST[ $column ] ) ? sanitize_text_field( wp_unslash( $_POST[ $column ] ) ) : '';
    if ( '' !== $raw ) {
      return $raw;
    }

    if ( ! isset( $_POST['session_times_posted'] ) ) {
      return ( $existing && ! empty( $existing->$column ) ) ? (string) $existing->$column : null;
    }

    $date = isset( $_POST[ $prefix . '_date' ] ) ? sanitize_text_field( wp_unslash( $_POST[ $prefix . '_date' ] ) ) : '';
    $time = isset( $_POST[ $prefix . '_time' ] ) ? sanitize_text_field( wp_unslash( $_POST[ $prefix . '_time' ] ) ) : '';

    if ( '' === $date || '' === $time ) {
      return null;
    }
    if ( 5 === strlen( $time ) ) {
      $time .= ':00';
    }
    return $date . ' ' . $time;
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
    $acdc_builder_session_ids = array();
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
      $acdc_builder_session_ids[] = $new_session_id;
    }

    /* ACDC 3.25.271 — Le constructeur crée les journées d'UNE action : les
       trois quiz structurels s'accrochent à la première, pas à chacune. Sept
       journées ne font pas vingt et un quiz. */
    if ( $acdc_builder_session_ids && method_exists( $this, 'acdc_prepare_action_quizzes' ) ) {
      $this->acdc_prepare_action_quizzes( $acdc_builder_session_ids );
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
    /* ACDC 3.25.265 — CE GARDE-FOU LAISSAIT SUPPRIMER DES SÉANCES PEUPLÉES.
       Il ne comptait que le rattachement direct (apprenant.session_id) et
       ignorait les groupes comme les CONVENTIONS — c'est-à-dire précisément le
       chemin par lequel arrivent les apprenants depuis que la convention crée
       les séances. Une séance suivie par trois personnes se supprimait donc
       sans un mot, et c'est la pièce à laquelle sont accrochés les émargements. */
    $session_for_count = $this->get_session( $session_id );
    $learner_count     = $session_for_count ? count( (array) $this->acdc_session_learners( $session_for_count ) ) : 0;
    if ( $learner_count ) {
      $this->redirect_to_portal( $return_tab, 'Suppression impossible : cette session est liée à des apprenants.', 'error' );
      exit;
    }

    $__acdc_supprime = $wpdb->delete( $this->session_table, array( 'id' => $session_id ) );
    /* ACDC 3.25.300 — La trace est posée sur la suppression, pas sur la
       redirection : une page qui annonce « supprimé » ne prouve pas qu'une
       ligne l'ait été. Le résultat écrit ici est celui de la base. */
    $this->log_action_event( 'suppression_session', 'session', (int) $session_id, $__acdc_supprime ? 'success' : 'error', array( 'lignes' => (int) $__acdc_supprime ) );
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

      /* ACDC 3.25.194 — Un seul chef d'orchestre. Sur une séance pilotée par un
         parcours, c'est le workflow qui décide du moment de la convocation — la
         veille à 17 h — et cette boucle-ci passe son tour. Sans cela, l'apprenant
         recevrait sa convocation deux fois : à J-7 par ce cron, la veille par le
         workflow. Les séances hors parcours gardent le comportement historique. */
      if ( method_exists( $this, 'acdc_wf_pilots_session' ) && $this->acdc_wf_pilots_session( $session_id ) ) {
        continue;
      }
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
          /* ACDC 3.25.249 — DEUX CONVOCATIONS, UNE SEULE FAISAIT LE TRAVAIL.
             Cet envoi-ci, automatique, annonçait la formation et renvoyait vers
             l'extranet sans joindre quoi que ce soit ; l'autre, manuel, fabrique
             le PDF détaillé. L'apprenant recevait donc, selon le chemin, deux
             courriers très différents pour la même chose.
             On fabrique et on ENREGISTRE ici la même convocation : elle part en
             pièce jointe, elle se télécharge d'un bouton, et elle apparaît dans
             l'extranet de l'apprenant — le rayon « Convocations » n'attendait
             que cette adresse. */
          $conv_registration = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->training_registration_table}
              WHERE learner_id = %d AND formation_id = %d AND is_draft = 0
              ORDER BY id DESC LIMIT 1",
            (int) $learner->id,
            (int) $session->formation_id
          ) );
          /* ACDC 3.25.252 — Le récapitulatif, le corps et la pièce jointe viennent
             maintenant du composeur commun : cet envoi-ci et celui du moteur ne
             peuvent plus décrire différemment la même convocation. */
          $conv_contract = null;
          if ( $conv_registration && ! empty( $conv_registration->autofill_contract_id ) ) {
            $conv_contract = $wpdb->get_row( $wpdb->prepare(
              "SELECT * FROM {$this->registration_contract_table} WHERE id = %d",
              (int) $conv_registration->autofill_contract_id
            ) );
          }
          $conv_formation = ! empty( $session->formation_id ) && method_exists( $this, 'get_formation' )
            ? $this->get_formation( (int) $session->formation_id )
            : null;

          $parts = $this->acdc_convocation_email_parts( array(
            'formation_title' => $formation_title,
            'session'         => $session,
            'contract'        => $conv_contract,
            'formation'       => $conv_formation,
            'registration'    => $conv_registration,
            'start'           => ! empty( $session->start_at ) ? (string) $session->start_at : (string) ( $session->start_date ?? '' ),
            'end'             => ! empty( $session->end_at ) ? (string) $session->end_at : (string) ( $session->end_date ?? '' ),
          ) );

          $this->acdc_send_transactional_email(
            $email,
            'Convocation — ' . $formation_title,
            array(
              'greeting_name' => $greeting,
              'intro_html'    => '',
              'summary_title' => 'DÉTAILS DE VOTRE CONVOCATION',
              'summary_rows'  => $parts['summary_rows'],
              'body_html'     => $parts['body_html'],
              'footer_notice' => 'Cet e-mail est votre convocation officielle. Conservez-le pour vos dossiers.',
            ),
            array(
              'source_module'       => 'sessions',
              'source_action'       => 'convocation_auto',
              'related_entity_type' => 'session',
              'related_entity_id'   => $session_id,
              'email_category'      => 'convocation',
              'email_audience'      => 'apprenant',
            ),
            $parts['attachments']
          );

          /* ACDC 3.25.249 — Le bloc de persistance qui se trouvait ici faisait le
             même travail, mais APRÈS l'envoi : l'e-mail ne pouvait donc pas
             contenir le lien, et le nom du fichier était devinable. Il est
             remplacé par l'appel unique fait plus haut, avant la composition du
             message. Deux codes pour une même écriture finissent toujours par
             diverger. */

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

      /* ACDC 3.25.225 — CE N'EST PAS UNE CONVOCATION, C'EST UNE INFORMATION.
         « Le commanditaire n'a pas lieu d'avoir de convocation, ce sont
         uniquement les apprenants. » L'e-mail envoyé à l'entreprise à J-7 ne
         convoque effectivement personne : il informe l'employeur de qui est
         convoqué, ce qui reste utile et légitime. C'est son INTITULÉ qui était
         faux, et un document mal nommé finit par être traité pour ce que son
         titre annonce — d'où la ligne « Convocation commanditaire » réclamée
         sur la carte de séance, et l'idée qu'une pièce manquait au dossier.
         On garde l'envoi, on lui rend son nom. */
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
              'Information — vos collaborateurs sont convoqués : ' . $formation_title . ' (' . ucfirst( $date_formatted ) . ')',
              array(
                'greeting_name' => $company_name ?: 'Madame, Monsieur',
                'intro_html'    => '',
                'summary_title' => 'DÉTAILS DE LA FORMATION',
                'summary_rows'  => $company_summary_rows,
                'body_html'     => $company_body_html,
                'footer_notice' => 'Cet e-mail est une information adressée au commanditaire de la formation. La convocation elle-même est adressée nominativement à chaque apprenant.',
              ),
              array(
                'source_module'       => 'sessions',
                'source_action'       => 'information_company_auto',
                'related_entity_type' => 'session',
                'related_entity_id'   => $session_id,
                'email_category'      => 'information_commanditaire',
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
          // ACDC 3.25.116 — scoper à LA session (via learner_table.session_id), sinon la clôture
          // d'une session contamine les dossiers des autres sessions de la même formation.
          /* ACDC 3.25.267 — CETTE SOUS-REQUÊTE NE RENVOYAIT PLUS RIEN.
             Elle scopait les dossiers « à la séance » par apprenants.session_id,
             le rattachement direct — vide depuis que la convention crée les
             séances. Aucun dossier n'avançait donc à « convocations envoyées ». */
          $linked_regs_wf = $this->acdc_registrations_for_session( $session, $formation_id_for_wf );
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
      // ACDC 3.25.116 — scoper à LA session (via learner_table.session_id), sinon la clôture d'une
      // session pousse « formation réalisée » aux inscrits d'autres sessions de la même formation.
      if ( $formation_id > 0 ) {
        /* ACDC 3.25.267 — MÊME SOUS-REQUÊTE, MÊME SILENCE, CONSÉQUENCE PIRE.
           À la clôture d'une séance, aucun dossier ne passait à « formation
           réalisée » : c'est l'étape qui déclenche les documents de fin de
           formation et l'enquête à froid. Tout le bas du cycle restait en
           attente sans que rien ne le signale. */
        $linked_regs = $this->acdc_registrations_for_session( $session, $formation_id );
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
  /**
   * ACDC 3.25.225 — LES PIÈCES DE FIN NE SORTAIENT POUR PERSONNE.
   *
   * Deux routines existaient — certificat de réalisation, attestation de fin —
   * et toutes deux commençaient par « SELECT ... FROM learners WHERE
   * session_id = %d ». C'est la colonne qui ne peut désigner qu'UNE séance :
   * sur une formation de deux journées découpées en quatre demi-journées, elle
   * n'est renseignée pour presque personne. La liste revenait vide, la fonction
   * repartait immédiatement, et AUCUN document n'était produit — sans la
   * moindre trace, puisque le `return` précédait même l'horodatage. D'où le
   * constat de David : « on doit avoir des documents dans tous les onglets du
   * menu document », et des onglets désespérément vides.
   *
   * Une seule routine remplace les deux. Elle part des DOSSIERS d'inscription,
   * pas d'une colonne de rattachement, et surtout elle ne décide plus toute
   * seule : elle demande à la couche de vérité — heures réellement émargées,
   * évaluation réellement passée — qui a droit à quoi. Trois issues possibles
   * par apprenant, et une seule est vraie à la fois :
   *
   *   — CERTIFICAT DE RÉALISATION dès qu'il y a présence émargée ;
   *   — ATTESTATION DE FIN DE FORMATION en plus, si les acquis sont validés ;
   *   — ATTESTATION D'ABSENCE si rien n'a été signé, adressée au
   *     COMMANDITAIRE : c'est lui qui a commandé et payé, pas l'absent.
   *
   * @param int $session_id
   * @param int $formation_id
   * @return void
   */
  private function acdc_completion_dispatch_for_session( $session_id, $formation_id ) {
    global $wpdb;

    $session_id   = absint( $session_id );
    $formation_id = absint( $formation_id );
    if ( ! $session_id || ! $formation_id ) {
      return;
    }

    /* Garde d'idempotence sur la séance : posée par la colonne existante, donc
       compatible avec les dossiers déjà traités par les versions précédentes. */
    $already = $wpdb->get_var( $wpdb->prepare(
      "SELECT completion_certificate_sent_at FROM {$this->session_table} WHERE id = %d",
      $session_id
    ) );
    if ( ! empty( $already ) ) {
      return;
    }

    if ( ! method_exists( $this, '_build_simple_pdf_string' ) ) {
      return;
    }

    $formation = $wpdb->get_row( $wpdb->prepare(
      "SELECT title, end_documents_enabled FROM {$this->formation_table} WHERE id = %d",
      $formation_id
    ) );
    if ( ! $formation || empty( $formation->end_documents_enabled ) ) {
      return;
    }
    $formation_title = ! empty( $formation->title ) ? (string) $formation->title : 'votre formation';

    /* Les dossiers de la formation, apprenant identifié uniquement : la ligne
       du commanditaire ne reçoit pas de pièce nominative. */
    $registrations = $wpdb->get_results( $wpdb->prepare(
      "SELECT * FROM {$this->training_registration_table}
        WHERE formation_id = %d AND is_draft = 0 AND learner_id IS NOT NULL AND learner_id > 0",
      $formation_id
    ) );

    $portal_page_id = (int) get_option( 'acdc_of_learner_portal_page_id', 0 );
    $portal_url     = $portal_page_id ? get_permalink( $portal_page_id ) : home_url( '/' );
    $upload_dir     = wp_upload_dir();
    $now            = current_time( 'mysql' );

    $produced = array( 'certificat' => 0, 'attestation' => 0, 'absence' => 0 );

    foreach ( (array) $registrations as $registration ) {
      $state = $this->acdc_completion_state_for_registration( $registration );

      $learner = $this->get_learner( (int) $registration->learner_id );
      $email   = $learner && ! empty( $learner->email ) ? sanitize_email( (string) $learner->email ) : '';
      $greeting = $learner ? trim( (string) $learner->first_name ) : '';

      /* --- 1. Le certificat de réalisation : les HEURES. ----------------- */
      if ( ! empty( $state['certificat'] ) ) {
        $created = false;
        $url = $this->acdc_completion_store_document(
          $registration,
          'completion_certificate',
          'certificat-realisation',
          $this->build_completion_certificate_pdf_pages( $registration, $this->get_completion_certificate_context( $registration ) ),
          $created
        );
        /* La notification ne part QUE si la pièce vient d'être produite. Une
           formation découpée en quatre demi-journées ferme quatre séances :
           sans cette condition, l'apprenant recevrait quatre fois le même
           certificat. */
        if ( '' !== $url && $created ) {
          $produced['certificat']++;
          if ( '' !== $email && is_email( $email ) ) {
            $this->acdc_completion_notify(
              $email,
              $greeting,
              'Votre certificat de réalisation — ' . $formation_title,
              '<p style="font-size:18px;line-height:1.7;margin:0 0 18px;">Votre formation <strong>' . esc_html( $formation_title ) . '</strong> est terminée. Votre <strong>certificat de réalisation</strong> atteste des heures que vous avez suivies : <strong>' . esc_html( $state['time']['label'] ) . '</strong>.</p>',
              $portal_url,
              $session_id,
              'completion_certificate_auto'
            );
          }
        }
      }

      /* --- 2. L'attestation de fin de formation : les ACQUIS. ------------ */
      if ( ! empty( $state['attestation'] ) ) {
        $created = false;
        $url = $this->acdc_completion_store_document(
          $registration,
          'end_training_certificate',
          'attestation-fin-formation',
          $this->build_end_training_certificate_pdf_pages( $registration, $this->get_end_training_certificate_context( $registration ) ),
          $created
        );
        if ( '' !== $url && $created ) {
          $produced['attestation']++;
          if ( '' !== $email && is_email( $email ) ) {
            $this->acdc_completion_notify(
              $email,
              $greeting,
              'Votre attestation de fin de formation — ' . $formation_title,
              '<p style="font-size:18px;line-height:1.7;margin:0 0 18px;">Vos acquis ont été évalués et validés à l\'issue de la formation <strong>' . esc_html( $formation_title ) . '</strong>. Votre <strong>attestation de fin de formation</strong> est disponible dans votre espace.</p>',
              $portal_url,
              $session_id,
              'end_training_certificate_auto'
            );
          }
        }
      }

      /* --- 3. L'absence : au COMMANDITAIRE, jamais à l'absent. ----------- */
      if ( ! empty( $state['absence'] ) ) {
        $created = false;
        $url = $this->acdc_completion_store_document(
          $registration,
          'absence_certificate',
          'attestation-absence',
          $this->build_absence_certificate_pdf_pages( $registration ),
          $created
        );
        if ( '' !== $url && $created ) {
          $produced['absence']++;
          $this->acdc_completion_notify_sponsor( $registration, $formation_title, $session_id );
        }
      }
    }

    $wpdb->update(
      $this->session_table,
      array(
        'completion_certificate_sent_at'   => $now,
        'end_training_certificate_sent_at' => $now,
        'updated_at'                       => $now,
      ),
      array( 'id' => $session_id ),
      array( '%s', '%s', '%s' ),
      array( '%d' )
    );

    if ( method_exists( $this, 'log_error' ) ) {
      $this->log_error( 'sessions', 'Pièces de fin de formation produites.', array(
        'session_id'  => $session_id,
        'certificats' => $produced['certificat'],
        'attestations' => $produced['attestation'],
        'absences'    => $produced['absence'],
      ) );
    }
  }

  /**
   * Écrit un PDF de fin de formation sur le disque et le rattache au dossier.
   *
   * Le dossier de dépôt est protégé : ces pièces nomment des personnes et
   * attestent de leur parcours ; elles ne doivent pas être lisibles par
   * quiconque devine une URL.
   *
   * @return string URL du document, ou chaîne vide.
   */
  private function acdc_completion_store_document( $registration, $column_prefix, $filename_base, $pages, &$created = false ) {
    global $wpdb;

    $created = false;

    if ( empty( $pages ) || ! is_array( $pages ) ) {
      return '';
    }

    $url_column  = $column_prefix . '_document_url';
    $path_column = $column_prefix . '_document_path';

    /* Le document existe déjà : on ne le refabrique pas. Une pièce probante
       régénérée à chaque passage changerait de contenu sous les pieds de qui
       l'a déjà téléchargée. */
    if ( ! empty( $registration->{$url_column} ) && ! empty( $registration->{$path_column} ) && file_exists( (string) $registration->{$path_column} ) ) {
      return (string) $registration->{$url_column};
    }

    $pdf = $this->_build_simple_pdf_string( $pages );
    if ( '' === (string) $pdf ) {
      return '';
    }

    $upload_dir = wp_upload_dir();
    $dir_path   = trailingslashit( $upload_dir['basedir'] ) . 'acdc-certificates/';
    /* ACDC 3.25.240 — NE PLUS INTERDIRE CE DOSSIER.
       En 3.25.225 je l'ai confié au protecteur des contrats, qui pose un
       « deny from all » sur le dossier ET sur son parent. Le parent étant ici
       la racine des téléversements, c'est toute la médiathèque du site qui est
       devenue inaccessible ; et l'attestation elle-même, dont on distribue
       pourtant l'adresse par e-mail, répondait 403.
       Le besoin réel n'était pas d'interdire l'accès : c'était d'empêcher qu'on
       devine l'adresse d'une attestation en énumérant les identifiants. On
       applique donc le procédé déjà retenu pour les contrats formateurs — un
       condensat dérivé des clés du site : déterministe, donc une régénération
       retrouve le même fichier, mais impossible à deviner de l'extérieur. */
    wp_mkdir_p( $dir_path );

    $token    = substr( wp_hash( 'acdc-certificate-' . $filename_base . '-' . (int) $registration->id ), 0, 20 );
    $filename = sanitize_file_name( $filename_base . '-' . (int) $registration->id . '-' . $token . '.pdf' );
    $filepath = $dir_path . $filename;
    if ( false === file_put_contents( $filepath, $pdf ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
      return '';
    }
    $fileurl = trailingslashit( $upload_dir['baseurl'] ) . 'acdc-certificates/' . $filename;
    $created = true;

    /* La colonne n'existe pas forcément (attestation d'absence, ajoutée en
       3.25.225) : on ne tente l'écriture que si la table la porte. */
    if ( $this->acdc_schema_has_column( $this->training_registration_table, $url_column ) ) {
      $wpdb->update(
        $this->training_registration_table,
        array(
          $url_column  => esc_url_raw( $fileurl ),
          $path_column => sanitize_text_field( $filepath ),
          'updated_at' => current_time( 'mysql' ),
        ),
        array( 'id' => (int) $registration->id ),
        array( '%s', '%s', '%s' ),
        array( '%d' )
      );
    }

    return $fileurl;
  }

  /** Notification d'une pièce de fin à l'apprenant. */
  private function acdc_completion_notify( $email, $greeting, $subject, $intro_html, $portal_url, $session_id, $action ) {
    $body  = $intro_html;
    $body .= '<p style="margin:24px 0;text-align:center;"><a href="' . esc_url( $portal_url ) . '" style="display:inline-block;padding:14px 28px;background:#C5A253;color:#0B0706;text-decoration:none;border-radius:8px;font-weight:700;font-size:16px;">Accéder à mes documents</a></p>';
    $body .= '<p style="font-size:15px;color:#667085;margin:16px 0 0;">Vous le retrouverez dans la rubrique <strong>Ma bibliothèque</strong> de votre espace personnel.</p>';

    $this->acdc_send_transactional_email(
      $email,
      $subject,
      array(
        'greeting_name' => $greeting,
        'intro_html'    => '',
        'body_html'     => $body,
        'footer_notice' => 'Cet e-mail est envoyé automatiquement à l’issue de votre formation. Vos données sont traitées conformément au RGPD.',
      ),
      array(
        'source_module'       => 'sessions',
        'source_action'       => $action,
        'related_entity_type' => 'session',
        'related_entity_id'   => (int) $session_id,
        'email_category'      => 'attestation',
        'email_audience'      => 'apprenant',
      )
    );
  }

  /** L'attestation d'absence part au commanditaire, pas à l'absent. */
  private function acdc_completion_notify_sponsor( $registration, $formation_title, $session_id ) {
    $email = '';
    $name  = '';

    if ( ! empty( $registration->company_id ) ) {
      $company = $this->get_company( (int) $registration->company_id );
      if ( $company ) {
        $name  = ! empty( $company->name ) ? (string) $company->name : '';
        $email = ! empty( $company->enterprise_contact_email ) ? sanitize_email( (string) $company->enterprise_contact_email ) : '';
      }
    }

    if ( '' === $email || ! is_email( $email ) ) {
      return;
    }

    $learner_name = '';
    if ( ! empty( $registration->learner_id ) ) {
      $learner = $this->get_learner( (int) $registration->learner_id );
      if ( $learner ) {
        $learner_name = trim( (string) $learner->first_name . ' ' . (string) ( ! empty( $learner->usage_last_name ) ? $learner->usage_last_name : $learner->last_name ) );
      }
    }

    $body  = '<p style="font-size:18px;line-height:1.7;margin:0 0 18px;">La formation <strong>' . esc_html( $formation_title ) . '</strong> est terminée.</p>';
    $body .= '<p style="font-size:17px;line-height:1.7;margin:0 0 18px;">Aucun émargement n’a été signé par <strong>' . esc_html( $learner_name ?: 'la personne inscrite' ) . '</strong>. Nous ne pouvons donc délivrer ni certificat de réalisation ni attestation de fin de formation : ce serait attester d’une présence qui n’a pas eu lieu.</p>';
    $body .= '<p style="font-size:17px;line-height:1.7;margin:0 0 18px;">Vous trouverez dans votre espace l’<strong>attestation d’absence</strong> correspondante, à joindre le cas échéant à votre dossier de financement.</p>';

    $this->acdc_send_transactional_email(
      $email,
      'Absence constatée — ' . $formation_title,
      array(
        'greeting_name' => $name ?: 'Madame, Monsieur',
        'intro_html'    => '',
        'body_html'     => $body,
        'footer_notice' => 'Cet e-mail est adressé au commanditaire de la formation.',
      ),
      array(
        'source_module'       => 'sessions',
        'source_action'       => 'absence_certificate_auto',
        'related_entity_type' => 'session',
        'related_entity_id'   => (int) $session_id,
        'email_category'      => 'attestation',
        'email_audience'      => 'entreprise',
      )
    );
  }

  private function _auto_send_completion_certificate_for_session( $session_id, $formation_id ) {
    $this->acdc_completion_dispatch_for_session( $session_id, $formation_id );
  }

  private function _auto_send_end_training_certificate_for_session( $session_id, $formation_id ) {
    /* Même routine : elle traite les trois pièces en une passe et se garde
       elle-même contre le double envoi. */
    $this->acdc_completion_dispatch_for_session( $session_id, $formation_id );
  }
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

    /* ACDC 3.25.290 — « email » n'existe pas dans la fiche entreprise : l'alerte
       d'absence partait donc toujours sur l'adresse d'administration de
       WordPress, jamais sur celle renseignée pour l'organisme. */
    $__id            = $this->acdc_org_identity();
    $admin_email     = '' !== $__id['email'] ? sanitize_email( $__id['email'] ) : sanitize_email( (string) get_option( 'admin_email' ) );

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
