<?php
/**
 * ACDC Workflow — les traitements réellement exécutés par le moteur.
 *
 * Chaque étape « auto » du registre cherche ici une méthode nommée
 * `acdc_wf_handle_<clé de l'étape>`. Tant que le mode simulation est actif, ces
 * méthodes ne sont JAMAIS appelées : le moteur journalise et s'arrête. Elles ne
 * servent donc qu'une fois le robinet ouvert, et chacune passe obligatoirement
 * par le garde-fou du mode recette avant d'écrire à qui que ce soit.
 *
 * Convention de retour, commune à tous les traitements :
 *   array( 'success' => bool, 'note' => string, 'error' => string )
 * La note est ce que David lira dans le journal. Elle doit dire QUI a reçu QUOI,
 * pas « OK ».
 *
 * @since 3.25.190
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

trait ACDC_Workflow_Handlers_Trait {

  /* =====================================================================
   * Contexte d'exécution
   * ===================================================================== */

  /**
   * Recharge le dossier complet d'une étape au moment de l'exécuter.
   *
   * On ne se fie pas à ce qui a été mémorisé lors de la planification : entre le
   * jour où l'étape a été posée et celui où elle part, la séance a pu être
   * déplacée, le formateur changé, un apprenant retiré. Le principe du module
   * vaut aussi à l'envoi — on relit la donnée.
   */
  private function acdc_wf_step_context( $step ) {
    global $wpdb;

    $run = $this->acdc_wf_get_run( (int) $step->run_id );
    if ( ! $run ) {
      return null;
    }
    $need = $wpdb->get_row( $wpdb->prepare(
      "SELECT * FROM {$this->need_table} WHERE id = %d",
      (int) $run->need_id
    ) );
    if ( ! $need ) {
      return null;
    }
    return $this->acdc_wf_resolve_pieces( $run, $need );
  }

  /**
   * Le garde-fou d'envoi, en un seul endroit.
   *
   * Un refus n'est pas un échec : c'est le mode recette qui fait son travail. Il
   * est journalisé comme tel, en nommant l'adresse écartée — sans quoi David
   * chercherait un e-mail qui n'est jamais parti sans jamais savoir pourquoi.
   */
  private function acdc_wf_guarded_send( $email, $subject, $template_args, $description ) {
    $email = sanitize_email( (string) $email );

    if ( '' === $email || ! is_email( $email ) ) {
      return array(
        'success' => false,
        'error'   => 'Aucune adresse e-mail exploitable pour ' . $description . '.',
      );
    }

    if ( ! $this->acdc_wf_may_send_to( $email ) ) {
      return array(
        'success' => true,
        'note'    => 'Mode recette : envoi retenu. ' . $description . ' aurait été adressé à ' . $email . ', qui ne figure pas dans les adresses autorisées.',
      );
    }

    $sent = $this->acdc_send_transactional_email( $email, $subject, $template_args );

    if ( ! $sent ) {
      return array(
        'success' => false,
        'error'   => 'L’envoi à ' . $email . ' a échoué (serveur de messagerie).',
      );
    }

    return array(
      'success' => true,
      'note'    => $description . ' envoyé à ' . $email . '.',
    );
  }

  /* =====================================================================
   * Le dossier de formation déposé au formateur
   * ===================================================================== */

  /**
   * Tout ce dont le formateur a besoin, en un seul envoi.
   *
   * David a été précis sur le contenu : la formation, les jours, la durée, le
   * nombre d'apprenants AVEC leurs noms, le lieu si la formation est en
   * présentiel, et — si elle est à distance — le rappel de créer le lien de
   * visioconférence et de le diffuser aux apprenants depuis son extranet.
   *
   * Ce dernier point est le seul du parcours que le plugin ne peut pas faire à
   * la place de l'humain : c'est donc le seul qu'il doit rappeler explicitement.
   */
  private function acdc_wf_handle_trainer_pack( $step ) {
    $pieces = $this->acdc_wf_step_context( $step );
    if ( ! $pieces ) {
      return array( 'success' => false, 'error' => 'Dossier introuvable au moment de l’envoi.' );
    }

    $trainer = $this->acdc_wf_trainer_record( $pieces );
    if ( ! $trainer ) {
      return array( 'success' => false, 'error' => 'Aucun formateur rattaché à la séance : le dossier ne peut être adressé.' );
    }

    $session   = $pieces['session'];
    $learners  = $pieces['learners'];
    $distance  = $this->acdc_wf_session_is_remote( $session );
    $slots     = $this->acdc_wf_session_slots( $pieces );
    $days      = $this->acdc_wf_session_day_count( $slots, $pieces );

    $rows = array(
      array( 'label' => 'Formation',   'value' => $this->acdc_wf_formation_title( $pieces ) ),
      array( 'label' => 'Entreprise',  'value' => (string) $pieces['company_name'] ),
      array( 'label' => 'Dates',       'value' => $this->acdc_wf_session_dates_label( $pieces ) ),
      array( 'label' => 'Durée',       'value' => $days > 0 ? sprintf( '%d jour%s (%d demi-journée%s)', $days, $days > 1 ? 's' : '', count( $slots ), count( $slots ) > 1 ? 's' : '' ) : 'À préciser' ),
      array( 'label' => 'Apprenants',  'value' => count( $learners ) . ( count( $learners ) > 1 ? ' inscrits' : ' inscrit' ) ),
      array( 'label' => 'Modalité',    'value' => $distance ? 'Distanciel' : 'Présentiel' ),
      array( 'label' => $distance ? 'Lien de connexion' : 'Lieu', 'value' => $distance
        ? ( ! empty( $session->remote_link ) ? (string) $session->remote_link : 'À créer par vos soins' )
        : ( ! empty( $session->location ) ? (string) $session->location : 'À préciser' ) ),
    );

    $body  = '<p>Le dossier de cette formation est disponible dans votre extranet formateur.</p>';
    $body .= '<p><strong>Apprenants inscrits :</strong></p><ul>';
    if ( empty( $learners ) ) {
      $body .= '<li>Aucun apprenant nommé à ce jour.</li>';
    } else {
      foreach ( $learners as $learner ) {
        $body .= '<li>' . esc_html( (string) $learner['name'] ) . '</li>';
      }
    }
    $body .= '</ul>';

    if ( $distance ) {
      $body .= '<p><strong>Formation à distance — action attendue de votre part :</strong> créez le lien de '
        . 'visioconférence (Teams) et diffusez-le aux apprenants depuis votre extranet, vers l’extranet de '
        . 'chacune des personnes concernées. C’est la seule étape du parcours que l’application ne peut pas '
        . 'réaliser à votre place.</p>';
    }

    return $this->acdc_wf_guarded_send(
      $trainer->email ?? '',
      'Votre dossier de formation — ' . $this->acdc_wf_formation_title( $pieces ),
      array(
        'greeting_name' => trim( (string) $trainer->first_name . ' ' . (string) $trainer->last_name ),
        'intro_html'    => '<p>Vous animez prochainement la formation ci-dessous.</p>',
        'summary_title' => 'Votre formation',
        'summary_rows'  => $rows,
        'body_html'     => $body,
      ),
      'Dossier de formation'
    );
  }

  /* =====================================================================
   * Les rappels d'émargement
   * ===================================================================== */

  private function acdc_wf_handle_emargement_am( $step ) {
    return $this->acdc_wf_send_emargement_reminder( $step, 'matin' );
  }

  private function acdc_wf_handle_emargement_pm( $step ) {
    return $this->acdc_wf_send_emargement_reminder( $step, 'après-midi' );
  }

  /**
   * Le rappel part trente minutes avant la demi-journée, au formateur seul.
   *
   * Il ne contient volontairement aucun lien de signature : l'émargement se fait
   * depuis l'extranet formateur, où l'identité de celui qui signe est établie.
   * Un lien d'émargement expédié par e-mail serait un lien transférable.
   */
  private function acdc_wf_send_emargement_reminder( $step, $half ) {
    $pieces = $this->acdc_wf_step_context( $step );
    if ( ! $pieces ) {
      return array( 'success' => false, 'error' => 'Dossier introuvable au moment de l’envoi.' );
    }

    $trainer = $this->acdc_wf_trainer_record( $pieces );
    if ( ! $trainer ) {
      return array( 'success' => false, 'error' => 'Aucun formateur rattaché à la séance.' );
    }

    $when = ! empty( $step->scheduled_at ) ? $this->acdc_wf_ts( $step->scheduled_at ) : $this->acdc_wf_now();
    $date = wp_date( 'd/m/Y', $when );

    return $this->acdc_wf_guarded_send(
      $trainer->email ?? '',
      'Émargement à recueillir — séance du ' . $date . ' (' . $half . ')',
      array(
        'greeting_name' => trim( (string) $trainer->first_name . ' ' . (string) $trainer->last_name ),
        'intro_html'    => '<p>Votre séance commence dans quelques minutes.</p>',
        'summary_title' => 'Séance concernée',
        'summary_rows'  => array(
          array( 'label' => 'Formation',   'value' => $this->acdc_wf_formation_title( $pieces ) ),
          array( 'label' => 'Date',        'value' => $date ),
          array( 'label' => 'Demi-journée','value' => ucfirst( $half ) ),
          array( 'label' => 'Apprenants',  'value' => count( $pieces['learners'] ) . ' attendu(s)' ),
        ),
        'body_html'     => '<p>Pensez à faire émarger les apprenants présents, et à signaler les absents, '
          . 'depuis votre extranet formateur. L’émargement est une pièce exigée par Qualiopi : une séance '
          . 'non émargée ne peut pas être justifiée a posteriori.</p>',
      ),
      'Rappel d’émargement (' . $half . ')'
    );
  }

  /* =====================================================================
   * Utilitaires partagés par les traitements
   * ===================================================================== */

  private function acdc_wf_trainer_record( $pieces ) {
    global $wpdb;
    $trainer_id = (int) $pieces['trainer_id'];
    if ( $trainer_id <= 0 ) {
      return null;
    }
    return $wpdb->get_row( $wpdb->prepare(
      "SELECT id, first_name, last_name, email FROM {$this->trainer_table} WHERE id = %d",
      $trainer_id
    ) );
  }

  private function acdc_wf_formation_title( $pieces ) {
    global $wpdb;
    if ( ! empty( $pieces['contract']->formation_title ) ) {
      return (string) $pieces['contract']->formation_title;
    }
    $formation_id = (int) $pieces['formation_id'];
    if ( $formation_id > 0 ) {
      $title = $wpdb->get_var( $wpdb->prepare(
        "SELECT title FROM {$this->formation_table} WHERE id = %d",
        $formation_id
      ) );
      if ( $title ) {
        return (string) $title;
      }
    }
    return ! empty( $pieces['session']->title ) ? (string) $pieces['session']->title : 'Formation';
  }

  /**
   * Présentiel ou distanciel ? On interroge la séance, jamais la formation :
   * le catalogue peut proposer les deux modalités, seule la séance tranche.
   */
  private function acdc_wf_session_is_remote( $session ) {
    if ( ! $session ) {
      return false;
    }
    $haystack = strtolower(
      (string) ( $session->attendance_method ?? '' ) . ' ' . (string) ( $session->session_format ?? '' )
    );
    if ( false !== strpos( $haystack, 'distanc' ) || false !== strpos( $haystack, 'visio' ) ) {
      return true;
    }
    if ( false !== strpos( $haystack, 'présentiel' ) || false !== strpos( $haystack, 'presentiel' ) ) {
      return false;
    }
    /* Ni l'un ni l'autre n'est déclaré : la présence d'un lien de visio et
       l'absence de lieu font pencher vers le distanciel. */
    return empty( $session->location ) && ! empty( $session->remote_link );
  }

  private function acdc_wf_session_day_count( $slots, $pieces ) {
    $days = array();
    foreach ( (array) $slots as $slot ) {
      $days[ wp_date( 'Y-m-d', (int) $slot['ts'] ) ] = true;
    }
    if ( ! empty( $days ) ) {
      return count( $days );
    }
    $dates = $this->acdc_wf_resolve_dates( $pieces );
    if ( $dates['start'] <= 0 || $dates['end'] <= 0 ) {
      return 0;
    }
    return 1 + (int) floor( ( strtotime( wp_date( 'Y-m-d', $dates['end'] ) ) - strtotime( wp_date( 'Y-m-d', $dates['start'] ) ) ) / DAY_IN_SECONDS );
  }

  private function acdc_wf_session_dates_label( $pieces ) {
    $dates = $this->acdc_wf_resolve_dates( $pieces );
    if ( $dates['start'] <= 0 ) {
      return 'À préciser';
    }
    $start = wp_date( 'd/m/Y', $dates['start'] );
    if ( $dates['end'] <= 0 ) {
      return $start;
    }
    $end = wp_date( 'd/m/Y', $dates['end'] );
    return ( $start === $end ) ? $start : ( 'du ' . $start . ' au ' . $end );
  }
}
