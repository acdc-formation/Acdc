<?php
/**
 * ACDC — Pièces de fin de formation : ce qui est VRAI avant ce qui est BEAU.
 *
 * Trois documents sortent à la fin d'une formation, et ils ne disent pas la
 * même chose. Les confondre serait la faute grave de ce module.
 *
 *   • CERTIFICAT DE RÉALISATION — le document des HEURES. Il atteste que la
 *     personne a suivi l'action. C'est la pièce réglementaire que réclament
 *     les OPCO et les financeurs publics pour solder un dossier : elle doit
 *     donc sortir dès qu'il y a eu présence, sans dépendre d'aucun résultat.
 *
 *   • ATTESTATION DE FIN DE FORMATION — le document des ACQUIS. Elle reprend
 *     le résultat de l'évaluation des acquis. Elle n'est due que si cette
 *     évaluation a été passée ET réussie ; sinon la décision revient à
 *     l'organisme, jamais à une machine.
 *
 *   • ATTESTATION D'ABSENCE — pour qui n'a rien signé du tout. Elle part au
 *     COMMANDITAIRE, pas à l'apprenant : c'est lui qui a commandé et payé.
 *
 * Ce fichier ne fabrique aucun PDF. Il répond d'abord aux deux questions dont
 * tout le reste dépend, et dont aucune n'avait de réponse dans le plugin :
 * combien d'heures cette personne a-t-elle RÉELLEMENT signées, et où en est
 * son évaluation des acquis. Un gabarit qui se tromperait là-dessus produirait
 * une fausse attestation — c'est-à-dire un faux.
 *
 * LA RÈGLE QUI GOUVERNE TOUT : on compte ce qui est SIGNÉ, jamais ce qui était
 * prévu. Une demi-journée planifiée mais non émargée ne compte pas. C'est la
 * seule lecture qui tienne devant un auditeur, et c'est aussi la seule qui
 * rende l'absence visible sans qu'on ait à la déclarer.
 *
 * @since 3.25.220
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

trait ACDC_Completion_Documents_Trait {

  /** Les statuts d'émargement qui valent PRÉSENCE. Tout le reste est absence. */
  private function acdc_completion_present_statuses() {
    return array( 'signed', 'signe', 'present', 'présent' );
  }

  /**
   * Les heures réellement émargées par un apprenant sur une formation.
   *
   * On additionne la durée des demi-journées qu'il a SIGNÉES, en lisant
   * l'horaire porté par la feuille elle-même — pas celui de la séance. Les
   * deux peuvent différer : une matinée qui s'est terminée plus tôt est une
   * matinée plus courte, et c'est la feuille qui en porte la trace.
   *
   * @param int   $learner_id
   * @param int[] $session_ids Les séances du dossier.
   * @return array{minutes:int,half_days:int,label:string,first_at:string,last_at:string}
   */
  private function acdc_completion_signed_time( $learner_id, $session_ids ) {
    global $wpdb;

    $result = array(
      'minutes'   => 0,
      'half_days' => 0,
      'label'     => '0 h',
      'first_at'  => '',
      'last_at'   => '',
    );

    $learner_id  = (int) $learner_id;
    $session_ids = array_values( array_filter( array_map( 'absint', (array) $session_ids ) ) );
    if ( $learner_id <= 0 || empty( $session_ids ) ) {
      return $result;
    }

    $emarg_sessions = $wpdb->prefix . 'acdc_of_emarg_sessions';
    $emarg_learners = $wpdb->prefix . 'acdc_of_emarg_learners';

    $placeholders = implode( ',', array_fill( 0, count( $session_ids ), '%d' ) );
    $statuses     = $this->acdc_completion_present_statuses();
    $status_ph    = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

    $rows = $wpdb->get_results( $wpdb->prepare(
      "SELECT es.seance_start_at, es.seance_end_at
         FROM {$emarg_learners} el
         INNER JOIN {$emarg_sessions} es ON es.id = el.emarg_session_id
        WHERE el.learner_id = %d
          AND el.session_id IN ({$placeholders})
          AND LOWER( el.status ) IN ({$status_ph})
        ORDER BY es.seance_start_at ASC",
      array_merge( array( $learner_id ), $session_ids, $statuses )
    ) );

    if ( empty( $rows ) ) {
      return $result;
    }

    $minutes = 0;
    foreach ( $rows as $row ) {
      $result['half_days']++;

      if ( '' === $result['first_at'] && ! empty( $row->seance_start_at ) ) {
        $result['first_at'] = (string) $row->seance_start_at;
      }
      if ( ! empty( $row->seance_start_at ) ) {
        $result['last_at'] = (string) $row->seance_start_at;
      }

      /* Une demi-journée sans horaire complet ne vaut pas zéro : elle a bien
         été suivie. On lui compte la demi-journée type de l'organisme plutôt
         que de sous-estimer les heures d'un apprenant présent — une pièce qui
         minore le temps suivi dessert autant l'apprenant que l'organisme. */
      if ( empty( $row->seance_start_at ) || empty( $row->seance_end_at ) ) {
        $minutes += 210; // 3 h 30, la demi-journée de référence.
        continue;
      }

      $span = ( strtotime( (string) $row->seance_end_at ) - strtotime( (string) $row->seance_start_at ) ) / 60;
      $minutes += ( $span > 0 ) ? (int) round( $span ) : 210;
    }

    $result['minutes'] = $minutes;
    $result['label']   = $this->acdc_completion_minutes_label( $minutes );

    return $result;
  }

  /**
   * ACDC 3.25.224 — Les heures PRÉVUES, quand rien n'est encore signé.
   *
   * La colonne « Durée (H) » des trois écrans de documents affichait « — » sur
   * un dossier de quatre demi-journées : elle lisait la durée saisie sur la
   * fiche formation, un champ libre que personne ne remplit, au lieu de
   * regarder les séances réellement planifiées. Une pièce de fin de formation
   * qui ne sait pas dire combien d'heures elle couvre ne vaut rien devant un
   * financeur.
   *
   * On additionne donc les créneaux du planning. C'est la valeur d'attente ;
   * dès qu'un émargement existe, ce sont les heures SIGNÉES qui priment —
   * jamais l'inverse.
   *
   * @param int[] $session_ids
   * @return array{minutes:int,half_days:int,label:string,first_at:string,last_at:string}
   */
  private function acdc_completion_planned_time( $session_ids ) {
    global $wpdb;

    $out = array(
      'minutes'   => 0,
      'half_days' => 0,
      'label'     => '',
      'first_at'  => '',
      'last_at'   => '',
    );

    $session_ids = array_values( array_filter( array_map( 'absint', (array) $session_ids ) ) );
    if ( empty( $session_ids ) ) {
      return $out;
    }

    $placeholders = implode( ',', array_fill( 0, count( $session_ids ), '%d' ) );
    $rows = $wpdb->get_results( $wpdb->prepare(
      "SELECT id, start_at, end_at, start_date, end_date, schedule_json
         FROM {$this->session_table}
        WHERE id IN ({$placeholders})",
      $session_ids
    ) );

    $slots = array();
    foreach ( (array) $rows as $row ) {
      $decoded = ! empty( $row->schedule_json ) ? json_decode( (string) $row->schedule_json, true ) : null;
      if ( is_array( $decoded ) && ! empty( $decoded ) ) {
        foreach ( array_values( $decoded ) as $slot ) {
          $slot  = (array) $slot;
          $start = ! empty( $slot['start_at'] ) ? (string) $slot['start_at'] : '';
          $end   = ! empty( $slot['end_at'] ) ? (string) $slot['end_at'] : '';
          if ( '' !== $start && '' !== $end ) {
            $slots[] = array( $start, $end );
          }
        }
        continue;
      }
      if ( ! empty( $row->start_at ) && ! empty( $row->end_at ) ) {
        $slots[] = array( (string) $row->start_at, (string) $row->end_at );
      }
    }

    if ( empty( $slots ) ) {
      return $out;
    }

    usort( $slots, static function( $a, $b ) {
      return strcmp( $a[0], $b[0] );
    } );

    $minutes = 0;
    foreach ( $slots as $slot ) {
      $span = ( strtotime( $slot[1] ) - strtotime( $slot[0] ) ) / 60;
      if ( $span > 0 ) {
        $minutes += (int) round( $span );
      }
    }

    $out['minutes']   = $minutes;
    $out['half_days'] = count( $slots );
    $out['label']     = $minutes > 0 ? $this->acdc_completion_minutes_label( $minutes ) : '';
    $out['first_at']  = $slots[0][0];
    $out['last_at']   = $slots[ count( $slots ) - 1 ][1];

    return $out;
  }

  /** « 14 h », « 10 h 30 » — jamais « 10h30 min », qui double l'unité. */
  private function acdc_completion_minutes_label( $minutes ) {
    $minutes = max( 0, (int) $minutes );
    $hours   = (int) floor( $minutes / 60 );
    $rest    = $minutes % 60;

    if ( $hours > 0 && $rest > 0 ) {
      return sprintf( '%d h %02d', $hours, $rest );
    }
    if ( $hours > 0 ) {
      return $hours . ' h';
    }
    return $rest . ' min';
  }

  /**
   * Le résultat de l'évaluation des acquis d'un apprenant.
   *
   * On retient la passation TERMINÉE la plus récente. Une évaluation repassée
   * remplace la précédente : c'est le dernier état des acquis qui fait foi.
   *
   * @return array{passed:bool|null,score:float|null,taken:bool,completed_at:string,quiz_title:string}
   */
  private function acdc_completion_assessment_result( $learner_id, $registration_ids = array() ) {
    global $wpdb;

    $out = array(
      'passed'       => null,
      'score'        => null,
      'taken'        => false,
      'completed_at' => '',
      'quiz_title'   => '',
    );

    $qz_p = $wpdb->prefix . 'acdc_of_qz_participants';
    $qz_s = $wpdb->prefix . 'acdc_of_qz_sessions';
    $qz_q = $wpdb->prefix . 'acdc_of_qz_quizzes';

    $registration_ids = array_values( array_filter( array_map( 'absint', (array) $registration_ids ) ) );

    $where  = array( "qq.quiz_purpose = 'assessment'", 'qp.completed_at IS NOT NULL' );
    $params = array();

    if ( ! empty( $registration_ids ) ) {
      $ph      = implode( ',', array_fill( 0, count( $registration_ids ), '%d' ) );
      $where[] = "qp.registration_id IN ({$ph})";
      $params  = array_merge( $params, $registration_ids );
    } else {
      $where[]  = 'qp.learner_id = %d';
      $params[] = (int) $learner_id;
    }

    $sql = "SELECT qp.total_score_percentage, qp.is_passed, qp.completed_at, qq.title
              FROM {$qz_p} qp
              INNER JOIN {$qz_s} qs ON qs.id = qp.session_id
              INNER JOIN {$qz_q} qq ON qq.id = qs.quiz_id
             WHERE " . implode( ' AND ', $where ) . "
             ORDER BY qp.completed_at DESC
             LIMIT 1";

    $row = $wpdb->get_row( $wpdb->prepare( $sql, $params ) );
    if ( ! $row ) {
      return $out;
    }

    $out['taken']        = true;
    $out['score']        = ( null !== $row->total_score_percentage ) ? (float) $row->total_score_percentage : null;
    $out['passed']       = ( null !== $row->is_passed ) ? ( 1 === (int) $row->is_passed ) : null;
    $out['completed_at'] = (string) $row->completed_at;
    $out['quiz_title']   = (string) $row->title;

    return $out;
  }

  /**
   * Quelles pièces sont dues à cet apprenant, et pourquoi.
   *
   * Cette fonction ne produit rien : elle DÉCIDE. Elle est volontairement le
   * seul endroit où la règle est écrite, pour qu'un gabarit ne puisse jamais
   * la contredire.
   *
   * @return array{
   *   certificat:bool, attestation:bool, absence:bool,
   *   time:array, assessment:array, reason:string
   * }
   */
  private function acdc_completion_eligibility( $learner_id, $session_ids, $registration_ids = array() ) {
    $time       = $this->acdc_completion_signed_time( $learner_id, $session_ids );
    $assessment = $this->acdc_completion_assessment_result( $learner_id, $registration_ids );

    $eligibility = array(
      'certificat'  => false,
      'attestation' => false,
      'absence'     => false,
      'time'        => $time,
      'assessment'  => $assessment,
      'reason'      => '',
    );

    /* AUCUNE signature : la personne n'est pas venue. Ni certificat ni
       attestation — les deux seraient des faux — mais une pièce qui le dit,
       adressée au commanditaire. */
    if ( $time['half_days'] <= 0 ) {
      $eligibility['absence'] = true;
      $eligibility['reason']  = 'Aucun émargement signé : absence totale.';
      return $eligibility;
    }

    /* Présence, même partielle : le certificat de réalisation est dû, avec les
       heures réelles. Elles diront d'elles-mêmes ce qui s'est passé. */
    $eligibility['certificat'] = true;

    if ( ! $assessment['taken'] ) {
      $eligibility['reason'] = 'Certificat dû. Attestation en attente : évaluation des acquis non passée.';
      return $eligibility;
    }
    if ( true !== $assessment['passed'] ) {
      $eligibility['reason'] = 'Certificat dû. Attestation non délivrée automatiquement : évaluation des acquis non réussie — décision à l’organisme.';
      return $eligibility;
    }

    $eligibility['attestation'] = true;
    $eligibility['reason']      = 'Certificat et attestation dus : présence émargée et acquis validés.';

    return $eligibility;
  }

  /**
   * ACDC 3.25.220 — Le pont entre un dossier d'inscription et la réalité.
   *
   * Les écrans travaillent avec un dossier ; le calcul, lui, a besoin d'un
   * apprenant et des séances qu'il a pu suivre. Cette fonction fait la
   * traduction, une fois, pour que les deux contextes de document n'aient pas
   * chacun leur version.
   */
  private function acdc_completion_state_for_registration( $registration ) {
    global $wpdb;

    $empty = $this->acdc_completion_eligibility( 0, array(), array() );
    if ( empty( $registration->learner_id ) ) {
      return $empty;
    }

    $formation_id = isset( $registration->formation_id ) ? (int) $registration->formation_id : 0;
    $session_ids  = array();
    if ( $formation_id > 0 ) {
      $session_ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT id FROM {$this->session_table} WHERE formation_id = %d",
        $formation_id
      ) );
    }

    $state = $this->acdc_completion_eligibility(
      (int) $registration->learner_id,
      $session_ids,
      array( (int) $registration->id )
    );

    /* ACDC 3.25.224 — Le prévu accompagne le réel, il ne le remplace pas.
       Les écrans ont besoin des deux : ce qui était planifié tant que rien
       n'est signé, ce qui a été signé dès qu'il y a une signature. Les
       confondre serait rouvrir la porte au certificat qui atteste des heures
       que personne n'a suivies. */
    $state['planned'] = $this->acdc_completion_planned_time( $session_ids );

    return $state;
  }
}
