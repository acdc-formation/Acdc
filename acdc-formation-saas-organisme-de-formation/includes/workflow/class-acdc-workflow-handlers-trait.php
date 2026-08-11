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
   * ACDC 3.25.193 — Le workflow DÉCLENCHE l'émargement, il ne le réécrit pas.
   *
   * La 3.25.190 composait son propre message : « pensez à faire émarger depuis
   * votre extranet ». C'était doublement faux. Le module d'émargement possède
   * déjà son envoi, et il fait bien plus qu'un rappel : il ouvre la séance,
   * porte le lien de signature du formateur, et affiche le QR code que chaque
   * apprenant scanne pour signer depuis son téléphone, le formateur voyant les
   * présences arriver en temps réel. Envoyer un rappel à côté de ce dispositif,
   * c'était ajouter un e-mail sans lien à un e-mail qui contient tout.
   *
   * Le rôle du workflow se réduit donc à ce qu'il sait faire de mieux : décider
   * du MOMENT. Trente minutes avant la demi-journée, il ouvre la feuille si elle
   * n'existe pas encore, et demande au module d'émargement d'envoyer le sien.
   *
   * Le garde-fou du mode recette est vérifié AVANT l'appel : le module envoie
   * par ses propres moyens et ne connaît pas nos adresses autorisées.
   */
  private function acdc_wf_send_emargement_reminder( $step, $half ) {
    if ( ! class_exists( 'ACDC_Emargement' ) ) {
      return array( 'success' => false, 'error' => 'Module d’émargement indisponible.' );
    }

    $pieces = $this->acdc_wf_step_context( $step );
    if ( ! $pieces ) {
      return array( 'success' => false, 'error' => 'Dossier introuvable au moment de l’envoi.' );
    }

    $trainer = $this->acdc_wf_trainer_record( $pieces );
    if ( ! $trainer || '' === (string) ( $trainer->email ?? '' ) ) {
      return array( 'success' => false, 'error' => 'Aucun formateur joignable pour ouvrir la feuille d’émargement.' );
    }

    $payload      = json_decode( (string) ( $step->payload_json ?? '' ), true );
    $session_id   = is_array( $payload ) && ! empty( $payload['session_id'] ) ? (int) $payload['session_id'] : (int) $pieces['session_id'];
    $seance_index = is_array( $payload ) && isset( $payload['seance_index'] ) ? (int) $payload['seance_index'] : 0;

    if ( $session_id <= 0 ) {
      return array( 'success' => false, 'error' => 'Séance introuvable pour cette feuille d’émargement.' );
    }

    $email = sanitize_email( (string) $trainer->email );
    if ( ! $this->acdc_wf_may_send_to( $email ) ) {
      return array(
        'success' => true,
        'note'    => 'Mode recette : envoi retenu. La feuille d’émargement (' . $half . ') aurait été ouverte et adressée à ' . $email . ', qui ne figure pas dans les adresses autorisées.',
      );
    }

    global $wpdb;
    $emargement = ACDC_Emargement::get_instance();
    $core       = $emargement->core;

    $sheet = $core->get_by_session_id( $session_id, $seance_index );

    if ( ! $sheet ) {
      $learners = array();
      foreach ( $pieces['learners'] as $learner ) {
        $learners[] = array(
          'id'    => (int) $learner['id'],
          'name'  => (string) $learner['name'],
          'email' => (string) $learner['email'],
        );
      }
      /* ACDC 3.25.223 — Le contexte du parcours n'est pas la seule source.
         Quand il ne connaît personne, on interroge le dossier de séance —
         inscriptions directes, groupes, conventions couvrant la date — avant
         de renoncer. Renoncer était le comportement le plus coûteux : le
         formateur n'était pas prévenu du tout, et la demi-journée passait. */
      if ( empty( $learners ) ) {
        $learners = $core->resolve_session_learners( $session_id );
      }
      if ( empty( $learners ) ) {
        return array( 'success' => false, 'error' => 'Aucun apprenant rattaché à cette séance : la feuille d’émargement serait vide.' );
      }

      $seance_meta = array();
      $session_row = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$this->session_table} WHERE id = %d",
        $session_id
      ) );
      if ( $session_row && ! empty( $session_row->schedule_json ) ) {
        $slots = json_decode( (string) $session_row->schedule_json, true );
        if ( is_array( $slots ) ) {
          $slots = array_values( $slots );
          if ( isset( $slots[ $seance_index ] ) ) {
            $seance_meta = $core->slot_to_meta( $slots[ $seance_index ], $seance_index, max( 1, count( $slots ) ) );
          }
        }
      }

      $sheet_id = $core->create_emarg_session(
        $session_id,
        (int) $trainer->id,
        trim( (string) $trainer->first_name . ' ' . (string) $trainer->last_name ),
        $email,
        $learners,
        $seance_index,
        $seance_meta
      );
      if ( ! $sheet_id ) {
        return array( 'success' => false, 'error' => 'La feuille d’émargement n’a pas pu être créée.' );
      }
      $sheet = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$core->table_sessions} WHERE id = %d",
        (int) $sheet_id
      ) );
    }

    if ( ! $sheet ) {
      return array( 'success' => false, 'error' => 'Feuille d’émargement introuvable après création.' );
    }

    $emargement->email->send_trainer_email( $sheet );

    return array(
      'success' => true,
      'note'    => 'Séance à ouvrir (' . $half . ') envoyée à ' . $email . ' — lien de signature et QR code apprenants inclus.',
    );
  }

  /* =====================================================================
   * La convocation
   * ===================================================================== */

  /**
   * ACDC 3.25.194 — La convocation part la veille à 17 h, et une seule fois.
   *
   * Le cron historique l'envoyait à J-7 puis un rappel à J-1. Il cède désormais
   * la main sur les dossiers pilotés ; c'est ici que la règle du schéma
   * s'applique. Le contenu reste rigoureusement celui du module — même modèle,
   * même en-têtes d'archivage — pour qu'un apprenant reçoive la même convocation
   * qu'il soit dans un parcours ou non.
   *
   * La séance est ensuite horodatée comme convoquée : si le workflow est mis en
   * pause un jour, l'ancien cron reprendra la main sans réexpédier ce qui est
   * déjà parti.
   */
  private function acdc_wf_handle_convocation( $step ) {
    global $wpdb;

    $pieces = $this->acdc_wf_step_context( $step );
    if ( ! $pieces ) {
      return array( 'success' => false, 'error' => 'Dossier introuvable au moment de l’envoi.' );
    }
    if ( empty( $pieces['learners'] ) ) {
      return array( 'success' => false, 'error' => 'Aucun apprenant nommé : aucune convocation à envoyer.' );
    }

    $session   = $pieces['session'];
    $dates     = $this->acdc_wf_resolve_dates( $pieces );
    $formation = $this->acdc_wf_formation_title( $pieces );
    $portal_id = (int) get_option( 'acdc_of_learner_portal_page_id', 0 );
    $portal    = $portal_id ? get_permalink( $portal_id ) : home_url( '/' );
    $lieu      = ! empty( $session->location )
      ? (string) $session->location
      : ( ! empty( $session->remote_link ) ? 'Distanciel' : '—' );

    $rows = array(
      array( 'label' => 'Formation',     'value' => $formation ),
      array( 'label' => 'Date de début', 'value' => $dates['start'] > 0 ? ucfirst( wp_date( 'l d F Y', $dates['start'] ) ) : 'À préciser' ),
      array( 'label' => 'Lieu / format', 'value' => $lieu ),
    );

    $body = '<p style="font-size:19px;line-height:1.7;margin:0 0 20px;">Vous êtes convoqué(e) à la formation indiquée ci-dessous. Merci de vous présenter à l\'heure et muni(e) des documents nécessaires.</p>';
    if ( ! empty( $session->remote_link ) ) {
      $body .= '<p style="font-size:18px;line-height:1.7;margin:0 0 20px;">Lien de connexion : <a href="' . esc_url( (string) $session->remote_link ) . '">' . esc_html( (string) $session->remote_link ) . '</a></p>';
    }
    $body .= '<p style="margin:24px 0;text-align:center;"><a href="' . esc_url( $portal ) . '" style="display:inline-block;padding:14px 28px;background:#C5A253;color:#0B0706;text-decoration:none;border-radius:8px;font-weight:700;font-size:16px;">Accéder à mon espace apprenant</a></p>';

    $sent = array();
    $held = array();
    $failed = array();

    foreach ( $pieces['learners'] as $learner ) {
      $email = sanitize_email( (string) $learner['email'] );
      if ( '' === $email || ! is_email( $email ) ) {
        $failed[] = (string) $learner['name'] . ' (adresse absente ou invalide)';
        continue;
      }
      if ( ! $this->acdc_wf_may_send_to( $email ) ) {
        $held[] = (string) $learner['name'] . ' <' . $email . '>';
        continue;
      }
      $ok = $this->acdc_send_transactional_email(
        $email,
        'Convocation — ' . $formation,
        array(
          'greeting_name' => (string) $learner['name'],
          'intro_html'    => '',
          'summary_title' => 'DÉTAILS DE VOTRE CONVOCATION',
          'summary_rows'  => $rows,
          'body_html'     => $body,
          'footer_notice' => 'Cet e-mail est votre convocation officielle. Conservez-le pour vos dossiers.',
        ),
        array(
          'source_module'       => 'workflow',
          'source_action'       => 'convocation',
          'related_entity_type' => 'session',
          'related_entity_id'   => (int) $pieces['session_id'],
          'email_category'      => 'convocation',
          'email_audience'      => 'apprenant',
        )
      );
      if ( $ok ) {
        $sent[] = (string) $learner['name'];
      } else {
        $failed[] = (string) $learner['name'] . ' (échec du serveur de messagerie)';
      }
    }

    if ( ! empty( $sent ) && (int) $pieces['session_id'] > 0 ) {
      $wpdb->update(
        $this->session_table,
        array( 'convocation_sent_at' => $this->acdc_wf_mysql( $this->acdc_wf_now() ) ),
        array( 'id' => (int) $pieces['session_id'] )
      );
    }

    return $this->acdc_wf_recipient_report( 'Convocation', $sent, $held, $failed );
  }

  /* =====================================================================
   * L'ouverture de l'extranet apprenant
   * ===================================================================== */

  /**
   * ACDC 3.25.194 — Le workflow déclenche l'ouverture, il ne la refait pas.
   *
   * Le portail apprenant possède déjà sa synchronisation des comptes et son
   * e-mail d'activation. Le workflow se contente d'appeler l'un puis l'autre,
   * au moment prévu par le parcours.
   */
  private function acdc_wf_handle_learner_invite( $step ) {
    $pieces = $this->acdc_wf_step_context( $step );
    if ( ! $pieces ) {
      return array( 'success' => false, 'error' => 'Dossier introuvable au moment de l’envoi.' );
    }

    $learner_id = (int) $step->target_id;
    $learner    = null;
    foreach ( $pieces['learners'] as $candidate ) {
      if ( (int) $candidate['id'] === $learner_id ) {
        $learner = $candidate;
        break;
      }
    }
    if ( ! $learner ) {
      return array( 'success' => false, 'error' => 'Cet apprenant n’est plus nommé dans la convention.' );
    }

    $email = sanitize_email( (string) $learner['email'] );
    if ( '' === $email || ! is_email( $email ) ) {
      return array( 'success' => false, 'error' => 'Aucune adresse e-mail exploitable pour ' . $learner['name'] . '.' );
    }
    if ( ! $this->acdc_wf_may_send_to( $email ) ) {
      return array(
        'success' => true,
        'note'    => 'Mode recette : envoi retenu. L’ouverture d’extranet de ' . $learner['name'] . ' aurait été adressée à ' . $email . ', qui ne figure pas dans les adresses autorisées.',
      );
    }

    if ( method_exists( $this, 'learner_portal_sync_accounts' ) ) {
      $this->learner_portal_sync_accounts( true );
    }
    if ( ! method_exists( $this, 'learner_portal_get_account_by_email' ) || ! method_exists( $this, 'learner_portal_send_activation_email' ) ) {
      return array( 'success' => false, 'error' => 'Module extranet apprenant indisponible.' );
    }

    $account = $this->learner_portal_get_account_by_email( $email );
    if ( ! $account ) {
      return array( 'success' => false, 'error' => 'Aucun compte extranet pour ' . $email . ' — l’accès extranet est-il activé sur le dossier ?' );
    }

    $ok = $this->learner_portal_send_activation_email( $account );

    return $ok
      ? array( 'success' => true, 'note' => 'Ouverture d’extranet envoyée à ' . $learner['name'] . ' <' . $email . '>.' )
      : array( 'success' => false, 'error' => 'L’e-mail d’ouverture à ' . $email . ' n’a pas pu être envoyé.' );
  }

  /* =====================================================================
   * Utilitaires partagés par les traitements
   * ===================================================================== */

  /**
   * Le compte rendu d'un envoi collectif : qui a reçu, qui a été retenu par le
   * mode recette, qui a échoué. Un « 3 envoyés » ne dit rien le jour où il n'y
   * en avait que deux.
   */
  private function acdc_wf_recipient_report( $label, $sent, $held, $failed ) {
    $parts = array();
    if ( ! empty( $sent ) ) {
      $parts[] = 'envoyée à ' . implode( ', ', $sent );
    }
    if ( ! empty( $held ) ) {
      $parts[] = 'retenue par le mode recette pour ' . implode( ', ', $held );
    }
    if ( ! empty( $failed ) ) {
      $parts[] = 'en échec pour ' . implode( ', ', $failed );
    }

    $note = $label . ' : ' . ( empty( $parts ) ? 'aucun destinataire.' : implode( ' ; ', $parts ) . '.' );

    /* Un échec partiel reste un échec : l'étape ne doit pas se refermer sur un
       apprenant qui n'a rien reçu. */
    if ( ! empty( $failed ) ) {
      return array( 'success' => false, 'error' => $note );
    }
    return array( 'success' => ! empty( $sent ) || ! empty( $held ), 'note' => $note, 'error' => 'Aucun destinataire joignable.' );
  }

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
