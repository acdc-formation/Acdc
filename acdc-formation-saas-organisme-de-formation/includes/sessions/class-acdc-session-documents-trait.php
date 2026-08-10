<?php
/**
 * ACDC Séances — documents de séance et verrou de l'extranet apprenant.
 *
 * Trois notions distinctes cohabitent dans ce fichier, et les confondre serait
 * l'erreur à ne pas commettre :
 *
 *   1. La BIBLIOTHÈQUE DE LA FORMATION — les documents fournis par l'organisme,
 *      rattachés à la formation elle-même. Ils sont les mêmes pour toutes les
 *      séances de cette formation.
 *
 *   2. Les DOCUMENTS DE SÉANCE — déposés par le formateur pour une séance
 *      précise, parfois pour un seul apprenant. Ils ne concernent que les gens
 *      qui étaient dans la salle. L'organisme doit malgré tout pouvoir les
 *      consulter : ce sont des preuves Qualiopi, et une preuve que l'OF ne peut
 *      pas produire n'en est pas une.
 *
 *   3. Le VERROU — la règle qui décide, à un instant donné, ce que l'apprenant
 *      voit. Les pièces officielles sont visibles dès l'ouverture de son espace ;
 *      le reste attend la fin de la formation, ou le déblocage anticipé par le
 *      formateur.
 *
 * Le verrou n'est pas un affichage. Il est appliqué au moment de servir le
 * fichier, pas seulement au moment de dessiner le lien : masquer un lien
 * n'interdit rien à qui connaît l'adresse.
 *
 * @since 3.25.207
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

trait ACDC_Session_Documents_Trait {

  /* ═══════════════════════════════════════════════════════════════════
     LE VERROU
     ═══════════════════════════════════════════════════════════════════ */

  /**
   * Les types de documents visibles dès l'ouverture de l'espace apprenant.
   *
   * La liste a été arrêtée avec David : les pièces officielles — celles qu'un
   * apprenant doit avoir AVANT d'entrer en formation, et qu'un auditeur
   * s'attend à trouver dans son espace dès le premier jour.
   *
   * Deux absences sont volontaires :
   *   — la CONVENTION, qui ne concerne que le commanditaire. Elle n'apparaît
   *     dans l'espace de l'apprenant que lorsque l'apprenant EST lui-même le
   *     commanditaire, cas du contrat de formation professionnelle conclu avec
   *     un particulier ;
   *   — l'ATTESTATION de fin, qui n'existe pas encore à ce moment-là.
   *
   * Le test de positionnement et l'évaluation diagnostique se passent avant la
   * formation : l'apprenant a le droit d'en connaître le résultat tout de
   * suite. L'évaluation des acquis et les quiz live, eux, attendent.
   */
  private function acdc_session_docs_immediate_types() {
    return array(
      'program',
      'convocation',
      'rulebook',
      'welcome_book',
      'positioning_result',
      'nad_apprenant',
      /* Le contrat n'entre dans cette liste que parce qu'il n'est proposé à
         l'apprenant que lorsqu'il en est lui-même le signataire — la convention
         d'entreprise, elle, ne lui est jamais présentée. */
      'contract',
    );
  }

  /**
   * L'état du verrou pour une séance.
   *
   * Deux façons d'être déverrouillé, et une seule règle de lecture : la date de
   * déblocage manuel prime, sinon c'est la fin de la séance qui décide.
   *
   * On compare des CHAÎNES au format MySQL, sans jamais convertir : en base une
   * date est en heure locale, `current_time( 'mysql' )` l'est aussi, et deux
   * dates locales se comparent directement. Passer par des timestamps ici
   * rouvrirait la porte à la double conversion qui nous a coûté deux heures
   * d'écart en été.
   *
   * @param object $session Ligne de la table des séances.
   * @return array{unlocked:bool,reason:string,end_at:string,unlocked_at:string}
   */
  private function acdc_session_docs_unlock_state( $session ) {
    $state = array(
      'unlocked'    => false,
      'reason'      => '',
      'end_at'      => '',
      'end_label'   => '',
      'unlocked_at' => '',
    );
    if ( empty( $session ) ) {
      return $state;
    }

    $manual = isset( $session->documents_unlocked_at ) ? trim( (string) $session->documents_unlocked_at ) : '';
    if ( '' !== $manual && '0000-00-00 00:00:00' !== $manual ) {
      $state['unlocked']    = true;
      $state['reason']      = 'manual';
      $state['unlocked_at'] = $manual;
    }

    /* Fin de la séance : l'horodatage précis s'il existe, sinon la fin du
       dernier jour. Une séance qui ne porte qu'une date de jour se termine à
       23:59:59 ce jour-là — pas à minuit, ce qui l'aurait terminée la veille. */
    $end = '';
    if ( ! empty( $session->end_at ) ) {
      $end = (string) $session->end_at;
    } elseif ( ! empty( $session->end_date ) ) {
      $end = substr( (string) $session->end_date, 0, 10 ) . ' 23:59:59';
    } elseif ( ! empty( $session->start_at ) ) {
      /* ACDC 3.25.212 — Faute de mon fait, relevée à l'écran : sans heure de
         fin, on retenait l'heure de DÉBUT, et la fiche annonçait « verrouillé
         jusqu'à la fin de la séance (12/08/2026 à 09:00) » — soit une fin
         placée au matin, avant la formation. Une séance sans horaire de fin se
         termine avec sa journée, pas à l'instant où elle commence. */
      $end = substr( (string) $session->start_at, 0, 10 ) . ' 23:59:59';
    } elseif ( ! empty( $session->start_date ) ) {
      $end = substr( (string) $session->start_date, 0, 10 ) . ' 23:59:59';
    }
    $state['end_at'] = $end;
    /* Le libellé dit la vérité sur ce qu'on sait : une heure quand la séance en
       porte une, la journée seulement quand elle n'en porte pas. Annoncer
       « 23:59 » à un formateur laisserait croire à un horaire décidé. */
    if ( '' !== $end ) {
      $has_real_end    = ! empty( $session->end_at );
      $state['end_label'] = $has_real_end
        ? mysql2date( 'd/m/Y \à H:i', $end )
        : 'fin de la journée du ' . mysql2date( 'd/m/Y', $end );
    }

    if ( ! $state['unlocked'] && '' !== $end && $end <= current_time( 'mysql' ) ) {
      $state['unlocked'] = true;
      $state['reason']   = 'end';
    }

    return $state;
  }

  /**
   * Le verrou d'un dossier d'inscription, vu depuis l'espace apprenant.
   *
   * L'apprenant peut suivre plusieurs séances pour une même inscription. Tant
   * qu'une seule n'est pas terminée, la formation ne l'est pas non plus : on
   * lit donc la DERNIÈRE. Le déblocage manuel d'une séance, lui, suffit —
   * c'est un geste explicite du formateur, il ne se discute pas.
   */
  private function acdc_session_docs_registration_unlocked( $item ) {
    $sessions = $this->acdc_session_docs_sessions_for_item( $item );
    if ( empty( $sessions ) ) {
      /* Aucune séance rattachée : rien ne permet de dater une fin. On ne
         déverrouille pas — un espace qui s'ouvre tout seul par défaut d'accroche
         serait la pire des réponses. */
      return false;
    }
    $all_done = true;
    foreach ( $sessions as $session ) {
      $state = $this->acdc_session_docs_unlock_state( $session );
      if ( 'manual' === $state['reason'] ) {
        return true;
      }
      if ( empty( $state['unlocked'] ) ) {
        $all_done = false;
      }
    }
    return $all_done;
  }

  /**
   * Les séances rattachées à un élément d'accès du portail apprenant.
   */
  private function acdc_session_docs_sessions_for_item( $item ) {
    global $wpdb;

    $learner_id   = ! empty( $item['learner']->id ) ? (int) $item['learner']->id : 0;
    $learner_mail = ! empty( $item['learner']->email ) ? (string) $item['learner']->email : '';
    $formation_id = ! empty( $item['formation']->id ) ? (int) $item['formation']->id : 0;
    $own_session  = ! empty( $item['session']->id ) ? (int) $item['session']->id : 0;

    /* Le verrou et la liste des documents interrogent tous deux cet ensemble,
       plusieurs fois par page. On le mémorise le temps de la requête HTTP :
       recalculer n'apprendrait rien de neuf et multiplierait les allers-retours
       en base sur l'écran le plus consulté du portail. */
    static $cache = array();
    $cache_key = $own_session . ':' . $learner_id . ':' . $formation_id;
    if ( isset( $cache[ $cache_key ] ) ) {
      return $cache[ $cache_key ];
    }

    $sessions = array();
    $seen     = array();

    if ( $own_session > 0 ) {
      $sessions[] = $item['session'];
      $seen[ $own_session ] = true;
    }

    /* Les autres séances du même apprenant sur la même formation : une
       formation de trois jours tient en trois séances, et la première ne dit
       rien de la fin. L'appariement se fait par e-mail — c'est déjà la clé
       d'identité du portail apprenant, une même personne pouvant porter
       plusieurs fiches d'un dossier à l'autre. */
    if ( '' !== $learner_mail && $formation_id > 0 ) {
      $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT DISTINCT s.* FROM {$this->session_table} s
          INNER JOIN {$this->learner_table} l ON l.session_id = s.id
          WHERE l.email = %s AND s.formation_id = %d",
        $learner_mail,
        $formation_id
      ) );
      foreach ( (array) $rows as $row ) {
        if ( isset( $seen[ (int) $row->id ] ) ) {
          continue;
        }
        $seen[ (int) $row->id ] = true;
        $sessions[] = $row;
      }
    }

    $cache[ $cache_key ] = $sessions;
    return $sessions;
  }

  /**
   * ACDC 3.25.211 — LES APPRENANTS D'UNE SÉANCE SE DÉDUISENT DU DOSSIER.
   *
   * Le portail formateur lisait `learner.session_id` : une colonne, donc UNE
   * séance par apprenant. Or une formation de deux jours tient en deux séances,
   * et l'inscription ne rattache l'apprenant qu'à la PREMIÈRE. Résultat observé
   * sur un dossier de trois apprenants : deux noms le premier jour, « aucun
   * apprenant inscrit » le second. Ce n'est pas un oubli de saisie — c'est une
   * question à laquelle la donnée ne peut pas répondre telle qu'elle est posée.
   *
   * On ne déplace pas la colonne, on cesse de lui demander ce qu'elle ne sait
   * pas. La liste se reconstruit à partir de trois sources qui, elles, savent :
   * le rattachement direct, les groupes de la séance, et les conventions qui
   * portent la même formation sur des dates couvrant ce jour-là.
   *
   * C'est le même principe que le moteur du workflow : relire le dossier plutôt
   * que se fier à un raccourci écrit une fois.
   */
  private function acdc_session_learners( $session ) {
    global $wpdb;

    if ( empty( $session->id ) ) {
      return array();
    }

    $session_id = (int) $session->id;
    $ids        = array();

    /* 1. Rattachement direct — le chemin historique, conservé tel quel. */
    $direct = $wpdb->get_col( $wpdb->prepare(
      "SELECT id FROM {$this->learner_table} WHERE session_id = %d",
      $session_id
    ) );
    foreach ( (array) $direct as $id ) {
      $ids[ (int) $id ] = true;
    }

    /* 2. Groupes de la séance. */
    $group_lists = $wpdb->get_col( $wpdb->prepare(
      "SELECT learner_ids FROM {$this->group_table} WHERE session_id = %d",
      $session_id
    ) );
    foreach ( (array) $group_lists as $list ) {
      foreach ( array_filter( array_map( 'absint', explode( ',', (string) $list ) ) ) as $id ) {
        $ids[ $id ] = true;
      }
    }

    /* 3. Conventions couvrant cette séance.
       La date de la séance est comparée au CRÉNEAU de la convention. Une
       convention sans dates couvre toute la formation : ne pas l'exclure vaut
       mieux que renvoyer une salle vide. */
    $formation_id = isset( $session->formation_id ) ? (int) $session->formation_id : 0;
    if ( $formation_id > 0 ) {
      $day = '';
      if ( ! empty( $session->start_date ) ) {
        $day = substr( (string) $session->start_date, 0, 10 );
      } elseif ( ! empty( $session->start_at ) ) {
        $day = substr( (string) $session->start_at, 0, 10 );
      }
      if ( '' !== $day ) {
        $contract_lists = $wpdb->get_col( $wpdb->prepare(
          "SELECT learner_ids FROM {$this->registration_contract_table}
            WHERE formation_id = %d
              AND learner_ids IS NOT NULL AND learner_ids <> ''
              AND ( start_date IS NULL OR start_date = '0000-00-00' OR start_date <= %s )
              AND ( end_date   IS NULL OR end_date   = '0000-00-00' OR end_date   >= %s )",
          $formation_id,
          $day,
          $day
        ) );
        foreach ( (array) $contract_lists as $list ) {
          foreach ( array_filter( array_map( 'absint', explode( ',', (string) $list ) ) ) as $id ) {
            $ids[ $id ] = true;
          }
        }
      }
    }

    $ids = array_keys( $ids );
    if ( empty( $ids ) ) {
      return array();
    }

    $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
    return (array) $wpdb->get_results( $wpdb->prepare(
      "SELECT id, first_name, last_name, usage_last_name, email, phone, status
        FROM {$this->learner_table}
        WHERE id IN ({$placeholders})
        ORDER BY last_name ASC, first_name ASC, id ASC",
      $ids
    ) );
  }

  /**
   * ACDC 3.25.212 — Le commanditaire d'une séance, déduit lui aussi du dossier.
   *
   * « Entreprise : — » sur toutes les fiches, alors que la formation est
   * commandée par Skill Conseil : la colonne `company_id` de la séance n'est
   * renseignée que lorsqu'on la saisit à la main, ce que le parcours
   * d'inscription ne fait pas. La convention, elle, sait qui commande.
   */
  private function acdc_session_company_name( $session ) {
    global $wpdb;

    if ( ! empty( $session->company_name ) ) {
      return (string) $session->company_name;
    }

    $formation_id = isset( $session->formation_id ) ? (int) $session->formation_id : 0;
    if ( $formation_id <= 0 ) {
      return '';
    }

    $day = '';
    if ( ! empty( $session->start_date ) ) {
      $day = substr( (string) $session->start_date, 0, 10 );
    } elseif ( ! empty( $session->start_at ) ) {
      $day = substr( (string) $session->start_at, 0, 10 );
    }
    if ( '' === $day ) {
      return '';
    }

    $company_id = (int) $wpdb->get_var( $wpdb->prepare(
      "SELECT company_id FROM {$this->registration_contract_table}
        WHERE formation_id = %d AND company_id IS NOT NULL AND company_id > 0
          AND ( start_date IS NULL OR start_date = '0000-00-00' OR start_date <= %s )
          AND ( end_date   IS NULL OR end_date   = '0000-00-00' OR end_date   >= %s )
        ORDER BY updated_at DESC, id DESC LIMIT 1",
      $formation_id,
      $day,
      $day
    ) );
    if ( $company_id <= 0 ) {
      return '';
    }

    $company = $this->get_company( $company_id );
    return ( $company && ! empty( $company->name ) ) ? (string) $company->name : '';
  }

  /* ═══════════════════════════════════════════════════════════════════
     STOCKAGE
     ═══════════════════════════════════════════════════════════════════ */

  /**
   * Le dossier privé des documents de séance.
   *
   * Privé au sens strict : interdit en accès direct par .htaccess, et servi
   * uniquement par nos points d'entrée, qui vérifient qui demande quoi. Un
   * support de cours déposé pour un apprenant nommé ne doit pas être lisible
   * par quiconque devine une URL.
   */
  protected function ensure_session_documents_upload_dir() {
    $uploads = wp_upload_dir();
    if ( empty( $uploads['basedir'] ) ) {
      return null;
    }
    $root = trailingslashit( $uploads['basedir'] ) . 'acdc-of/session-documents';
    if ( ! file_exists( $root ) ) {
      wp_mkdir_p( $root );
    }
    $ht = trailingslashit( $root ) . '.htaccess';
    if ( ! file_exists( $ht ) ) {
      $rules  = "# ACDC 3.25.207 — Documents de séance : accès direct interdit.\n";
      $rules .= "Order deny,allow\n";
      $rules .= "Deny from all\n";
      @file_put_contents( $ht, $rules );
    }
    $idx = trailingslashit( $root ) . 'index.php';
    if ( ! file_exists( $idx ) ) {
      @file_put_contents( $idx, "<?php\n// Silence is golden.\n" );
    }
    return array( 'base_dir' => $root );
  }

  /**
   * Chemin absolu sûr à partir du chemin relatif stocké en base.
   * Toute tentative de sortir du dossier renvoie null, sans exception.
   */
  protected function get_session_document_absolute_path( $relative_path ) {
    $relative_path = ltrim( (string) $relative_path, '/\\' );
    if ( '' === $relative_path || false !== strpos( $relative_path, '..' ) ) {
      return null;
    }
    $dirs = $this->ensure_session_documents_upload_dir();
    if ( ! $dirs ) {
      return null;
    }
    $absolute = trailingslashit( $dirs['base_dir'] ) . $relative_path;
    $real     = realpath( $absolute );
    if ( ! $real || 0 !== strpos( $real, (string) realpath( $dirs['base_dir'] ) ) ) {
      return null;
    }
    return $real;
  }

  /* ═══════════════════════════════════════════════════════════════════
     LECTURE
     ═══════════════════════════════════════════════════════════════════ */

  /**
   * Les documents déposés sur une séance.
   *
   * @param int $session_id
   * @param int $learner_id 0 pour tout voir (formateur, organisme) ; un
   *                        identifiant pour ne rendre que ce qui concerne cet
   *                        apprenant — le collectif et le nominatif qui lui est
   *                        adressé, jamais celui d'un camarade.
   */
  private function acdc_session_documents( $session_id, $learner_id = 0 ) {
    global $wpdb;

    $session_id = (int) $session_id;
    if ( $session_id <= 0 ) {
      return array();
    }

    if ( $learner_id > 0 ) {
      return (array) $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$this->trainer_resource_table}
          WHERE session_id = %d AND ( learner_id = 0 OR learner_id = %d )
          ORDER BY created_at DESC, id DESC",
        $session_id,
        (int) $learner_id
      ) );
    }

    return (array) $wpdb->get_results( $wpdb->prepare(
      "SELECT * FROM {$this->trainer_resource_table} WHERE session_id = %d ORDER BY created_at DESC, id DESC",
      $session_id
    ) );
  }

  private function acdc_session_document( $document_id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare(
      "SELECT * FROM {$this->trainer_resource_table} WHERE id = %d",
      (int) $document_id
    ) );
  }

  /**
   * Un document de séance est-il visible par l'apprenant maintenant ?
   *
   * Le dépôt porte sa propre réponse : le formateur a choisi, au moment de
   * déposer, entre « tout de suite » et « à la fin ». Une consigne pour le
   * lendemain n'attend pas ; un corrigé, si.
   */
  private function acdc_session_document_visible_to_learner( $document, $unlocked ) {
    if ( empty( $document ) ) {
      return false;
    }
    if ( isset( $document->is_visible_to_learners ) && ! (int) $document->is_visible_to_learners ) {
      return false;
    }
    $visibility = isset( $document->visibility ) ? (string) $document->visibility : 'unlock';
    if ( 'immediate' === $visibility ) {
      return true;
    }
    return (bool) $unlocked;
  }

  private function acdc_session_document_label( $document ) {
    $label = isset( $document->label ) ? trim( (string) $document->label ) : '';
    if ( '' !== $label ) {
      return $label;
    }
    $name = isset( $document->file_name ) ? trim( (string) $document->file_name ) : '';
    return '' !== $name ? $name : 'Document de séance';
  }

  /**
   * Le formateur peut-il encore retirer ce qu'il a déposé ?
   *
   * Règle arrêtée avec David : oui tant que la séance n'est pas terminée ;
   * après, plus personne — sauf l'organisme, qui garde la main en toutes
   * circonstances. Une preuve Qualiopi que le formateur peut effacer après coup
   * n'est pas une preuve.
   */
  private function acdc_session_document_trainer_may_delete( $document, $session ) {
    if ( empty( $document ) || empty( $session ) ) {
      return false;
    }
    if ( 'trainer' !== (string) $document->uploaded_by ) {
      return false;
    }
    $state = $this->acdc_session_docs_unlock_state( $session );
    return empty( $state['unlocked'] );
  }

  /**
   * Le formateur a-t-il bien la séance en charge ? Titulaire de la séance ou
   * d'un de ses groupes — la même condition que partout ailleurs dans son
   * portail.
   */
  private function acdc_session_docs_trainer_owns_session( $trainer_id, $session_id ) {
    global $wpdb;
    $trainer_id = (int) $trainer_id;
    $session_id = (int) $session_id;
    if ( $trainer_id <= 0 || $session_id <= 0 ) {
      return null;
    }
    return $wpdb->get_row( $wpdb->prepare(
      "SELECT s.* FROM {$this->session_table} s
        WHERE s.id = %d
          AND ( s.trainer_id = %d
                OR EXISTS ( SELECT 1 FROM {$this->group_table} g WHERE g.session_id = s.id AND g.trainer_id = %d ) )
        LIMIT 1",
      $session_id,
      $trainer_id,
      $trainer_id
    ) );
  }

  /**
   * L'apprenant a-t-il accès à ce document ? Une seule fonction, appelée aussi
   * bien pour dessiner la liste que pour servir le fichier.
   */
  private function acdc_session_docs_learner_may_read( $account_email, $document ) {
    if ( empty( $document ) ) {
      return false;
    }
    $session_id = (int) $document->session_id;
    if ( $session_id <= 0 ) {
      return false;
    }

    foreach ( $this->learner_portal_get_access_items_for_email( $account_email ) as $item ) {
      $learner_id = ! empty( $item['learner']->id ) ? (int) $item['learner']->id : 0;
      foreach ( $this->acdc_session_docs_sessions_for_item( $item ) as $session ) {
        if ( (int) $session->id !== $session_id ) {
          continue;
        }
        /* Un dépôt nominatif ne concerne que la personne nommée. */
        if ( (int) $document->learner_id > 0 && (int) $document->learner_id !== $learner_id ) {
          continue;
        }
        $unlocked = $this->acdc_session_docs_registration_unlocked( $item );
        return $this->acdc_session_document_visible_to_learner( $document, $unlocked );
      }
    }

    return false;
  }

  /* ═══════════════════════════════════════════════════════════════════
     ÉCRITURE — DÉPÔT, RETRAIT, DÉBLOCAGE
     ═══════════════════════════════════════════════════════════════════ */

  /**
   * Enregistre un fichier déposé sur une séance.
   *
   * Renvoie l'identifiant du document, ou un message d'erreur en clair. Les
   * contrôles reprennent, dans le même ordre, ceux de la bibliothèque
   * personnelle du formateur : taille, extension, puis correspondance réelle
   * entre le contenu et l'extension annoncée.
   *
   * @return array{ok:bool,id:int,error:string}
   */
  private function acdc_session_docs_store_upload( $file, $args ) {
    $args = wp_parse_args( $args, array(
      'session_id'  => 0,
      'learner_id'  => 0,
      'label'       => '',
      'visibility'  => 'unlock',
      'notify'      => 0,
      'trainer_id'  => 0,
      'uploaded_by' => 'trainer',
      'uploader_id' => 0,
    ) );

    $fail = function( $message ) {
      return array( 'ok' => false, 'id' => 0, 'error' => $message );
    };

    if ( empty( $file ) || empty( $file['name'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
      $code = isset( $file['error'] ) ? (int) $file['error'] : -1;
      return $fail(
        ( UPLOAD_ERR_INI_SIZE === $code || UPLOAD_ERR_FORM_SIZE === $code )
          ? 'Le fichier dépasse la taille maximale autorisée.'
          : 'Aucun fichier valide reçu.'
      );
    }

    $file_name_orig = sanitize_file_name( (string) $file['name'] );
    $file_tmp       = (string) $file['tmp_name'];

    if ( (int) $file['size'] > $this->get_acdc_trainer_document_max_size_bytes() ) {
      return $fail( 'Le fichier dépasse 100 Mo.' );
    }
    if ( ! is_uploaded_file( $file_tmp ) ) {
      return $fail( 'Erreur d’upload : fichier temporaire introuvable.' );
    }

    $allowed_mimes = $this->get_acdc_trainer_document_allowed_mimes();
    $ext           = strtolower( (string) pathinfo( $file_name_orig, PATHINFO_EXTENSION ) );
    if ( '' === $ext || ! isset( $allowed_mimes[ $ext ] ) ) {
      return $fail( 'Format de fichier non autorisé. Formats acceptés : PDF, JPG, PNG, DOC, DOCX, PPTX.' );
    }
    $check = wp_check_filetype_and_ext( $file_tmp, $file_name_orig, $allowed_mimes );
    if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
      return $fail( 'Le contenu du fichier ne correspond pas à son extension. Dépôt refusé.' );
    }

    $dirs = $this->ensure_session_documents_upload_dir();
    if ( ! $dirs ) {
      return $fail( 'Impossible de préparer le dossier de dépôt.' );
    }
    $session_dir = trailingslashit( $dirs['base_dir'] ) . (int) $args['session_id'];
    if ( ! file_exists( $session_dir ) ) {
      wp_mkdir_p( $session_dir );
    }

    $base_slug = (string) pathinfo( $file_name_orig, PATHINFO_FILENAME );
    $base_slug = $this->acdc_strip_accents( $base_slug );
    $base_slug = preg_replace( '/[^a-zA-Z0-9_-]+/', '-', $base_slug );
    $base_slug = trim( strtolower( (string) $base_slug ), '-' );
    if ( '' === $base_slug ) {
      $base_slug = 'doc';
    }
    $base_slug   = substr( $base_slug, 0, 80 );
    $stored_name = 'sd_' . time() . '_' . wp_generate_password( 6, false, false ) . '_' . $base_slug . '.' . $ext;
    $stored_full = trailingslashit( $session_dir ) . $stored_name;

    if ( ! @move_uploaded_file( $file_tmp, $stored_full ) ) {
      return $fail( 'Erreur lors du dépôt du fichier.' );
    }
    @chmod( $stored_full, 0644 );

    global $wpdb;
    $now   = current_time( 'mysql' );
    $label = trim( (string) $args['label'] );
    if ( '' === $label ) {
      $label = $file_name_orig;
    }

    $inserted = $wpdb->insert(
      $this->trainer_resource_table,
      array(
        'trainer_id'             => (int) $args['trainer_id'],
        'formation_id'           => 0,
        'session_id'             => (int) $args['session_id'],
        'learner_id'             => (int) $args['learner_id'],
        'label'                  => $label,
        'file_url'               => (int) $args['session_id'] . '/' . $stored_name,
        'file_name'              => $file_name_orig,
        'external_url'           => '',
        'description_text'       => '',
        'is_visible_to_learners' => 1,
        'visibility'             => 'immediate' === $args['visibility'] ? 'immediate' : 'unlock',
        'notify_learners'        => empty( $args['notify'] ) ? 0 : 1,
        'uploaded_by'            => 'admin' === $args['uploaded_by'] ? 'admin' : 'trainer',
        'uploader_user_id'       => (int) $args['uploader_id'],
        'published_at'           => $now,
        'created_at'             => $now,
        'updated_at'             => $now,
      )
    );

    if ( ! $inserted ) {
      /* La ligne n'existe pas : le fichier orphelin ne doit pas rester sur le
         disque, il ne serait plus jamais ni servi ni supprimé. */
      @unlink( $stored_full );
      return $fail( 'Le document n’a pas pu être enregistré.' );
    }

    return array( 'ok' => true, 'id' => (int) $wpdb->insert_id, 'error' => '' );
  }

  /**
   * Supprime un document de séance : la ligne ET le fichier.
   */
  private function acdc_session_docs_delete( $document ) {
    global $wpdb;
    if ( empty( $document ) ) {
      return false;
    }
    $abs = $this->get_session_document_absolute_path( (string) $document->file_url );
    if ( $abs && file_exists( $abs ) ) {
      @unlink( $abs );
    }
    return (bool) $wpdb->delete( $this->trainer_resource_table, array( 'id' => (int) $document->id ) );
  }

  /**
   * Débloque les documents d'une séance sans attendre sa fin.
   *
   * L'horodatage est écrit AVANT l'envoi des e-mails : si l'envoi échoue, le
   * déblocage tient quand même. L'inverse — prévenir des gens d'un déblocage
   * qui n'a pas eu lieu — serait bien pire.
   */
  private function acdc_session_docs_unlock( $session, $actor = 'trainer' ) {
    global $wpdb;
    if ( empty( $session->id ) ) {
      return 'error';
    }
    $state = $this->acdc_session_docs_unlock_state( $session );
    if ( 'manual' === $state['reason'] ) {
      return 'already'; // Déjà débloquée : on ne renvoie pas un second avis.
    }

    /* ACDC 3.25.208 — La colonne est vérifiée, et créée si elle manque.
       La recette a observé un déblocage « sans effet » : sans cette garde, une
       colonne absente — parce que la montée de schéma n'a pas encore eu lieu sur
       cette installation — fait échouer l'UPDATE en silence, et l'écran annonce
       quand même une réussite. Un écran qui affirme ce qu'il n'a pas vérifié
       vaut moins que pas d'écran du tout. */
    if ( ! $this->acdc_schema_has_column( $this->session_table, 'documents_unlocked_at' ) ) {
      $this->maybe_add_table_column( $this->session_table, 'documents_unlocked_at', 'DATETIME NULL' );
    }
    if ( ! $this->acdc_schema_has_column( $this->session_table, 'documents_unlocked_at' ) ) {
      $this->log_error( 'learner_portal', 'Déblocage impossible : colonne documents_unlocked_at absente.', array(
        'session_id' => (int) $session->id,
      ) );
      return 'error';
    }

    $now     = current_time( 'mysql' );
    $written = $wpdb->update(
      $this->session_table,
      array( 'documents_unlocked_at' => $now ),
      array( 'id' => (int) $session->id )
    );

    if ( false === $written ) {
      $this->log_error( 'learner_portal', 'Déblocage refusé par la base.', array(
        'session_id' => (int) $session->id,
        'db_error'   => (string) $wpdb->last_error,
      ) );
      return 'error';
    }

    $this->insert_system_log( array(
      'log_level'   => 'info',
      'event_type'  => 'session_documents_unlocked',
      'action_key'  => 'session_documents_unlock',
      'object_type' => 'session',
      'object_id'   => (int) $session->id,
      'message'     => 'Documents de séance débloqués par ' . $actor . '.',
    ) );

    /* ACDC 3.25.208 — L'AVIS AUX APPRENANTS SORT DE LA REQUÊTE.
       Il partait jusqu'ici en ligne, dans le clic. Sur cette séance-là il n'y
       avait aucun destinataire — l'envoi n'a donc pas pu être la cause du
       blocage observé — mais le principe reste faux : une action d'écran ne
       doit jamais dépendre du temps de réponse d'un serveur de messagerie. Dix
       apprenants et un SMTP lent suffisent à faire expirer la page, et
       l'utilisateur reclique, et la deuxième salve part.
       Le déblocage est écrit, la page rend la main, les e-mails suivent. */
    if ( ! wp_next_scheduled( 'acdc_of_session_documents_unlock_notice', array( (int) $session->id ) ) ) {
      wp_schedule_single_event( time() + 30, 'acdc_of_session_documents_unlock_notice', array( (int) $session->id ) );
    }

    return 'done';
  }

  /**
   * Le porteur de l'avis différé. Appelé par le cron, jamais par un écran.
   */
  public function handle_session_documents_unlock_notice( $session_id ) {
    $this->acdc_session_docs_notify_unlock( (int) $session_id );
  }

  /**
   * Un seul e-mail par apprenant, récapitulatif, au moment du déblocage.
   *
   * Pas un e-mail par document : c'est la règle arrêtée avec David. Le dépôt
   * d'un document ne prévient que si le formateur l'a demandé explicitement.
   */
  private function acdc_session_docs_notify_unlock( $session_id ) {
    global $wpdb;

    $learners = (array) $wpdb->get_results( $wpdb->prepare(
      "SELECT id, first_name, last_name, usage_last_name, email
        FROM {$this->learner_table}
        WHERE session_id = %d AND email <> ''",
      (int) $session_id
    ) );
    if ( empty( $learners ) ) {
      return;
    }

    $portal_url = $this->learner_portal_page_url( 'documents' );

    foreach ( $learners as $learner ) {
      $name = trim( (string) $learner->first_name );
      $body  = '<p>Bonjour ' . esc_html( $name ) . ',</p>';
      $body .= '<p>Les documents de votre formation sont désormais accessibles dans votre espace : supports, résultats d’évaluation et attestations, au fur et à mesure de leur mise à disposition.</p>';
      $body .= '<p><a href="' . esc_url( $portal_url ) . '">Ouvrir mon espace</a></p>';

      $this->acdc_session_docs_send_learner_email(
        (string) $learner->email,
        'Vos documents de formation sont disponibles',
        $body,
        '' !== $name ? $name : 'Apprenant'
      );
    }
  }

  /**
   * Envoi encadré vers un apprenant.
   *
   * Le workflow reste le chef d'orchestre : quand il pilote, c'est lui qui dit
   * qui peut recevoir un e-mail, et le mode simulation doit se faire respecter
   * ici comme partout ailleurs. Un écran qui contourne la simulation enverrait
   * de vrais messages pendant une recette.
   */
  private function acdc_session_docs_send_learner_email( $to, $subject, $body_html, $greeting_name ) {
    $to = sanitize_email( (string) $to );
    if ( '' === $to ) {
      return false;
    }
    if ( method_exists( $this, 'acdc_wf_is_piloting' ) && $this->acdc_wf_is_piloting() ) {
      if ( method_exists( $this, 'acdc_wf_may_send_to' ) && ! $this->acdc_wf_may_send_to( $to ) ) {
        return false;
      }
    }
    return $this->learner_portal_send_email( $to, $subject, $body_html, $greeting_name );
  }

  /* ═══════════════════════════════════════════════════════════════════
     SERVICE DU FICHIER
     ═══════════════════════════════════════════════════════════════════ */

  /**
   * Envoie le fichier au navigateur. Aucun contrôle d'accès ici : ils ont tous
   * été faits par l'appelant, qui seul sait au nom de qui il agit.
   */
  private function acdc_session_docs_stream( $document ) {
    $abs = $this->get_session_document_absolute_path( (string) $document->file_url );
    if ( ! $abs || ! file_exists( $abs ) ) {
      wp_die( esc_html( 'Fichier introuvable.' ) );
    }

    $name = ! empty( $document->file_name ) ? (string) $document->file_name : basename( $abs );
    $type = wp_check_filetype( $abs );
    $mime = ! empty( $type['type'] ) ? $type['type'] : 'application/octet-stream';

    nocache_headers();
    header( 'Content-Type: ' . $mime );
    header( 'Content-Disposition: attachment; filename="' . rawurldecode( $name ) . '"' );
    header( 'Content-Length: ' . (string) filesize( $abs ) );
    header( 'X-Content-Type-Options: nosniff' );
    readfile( $abs );
    exit;
  }

  /* ═══════════════════════════════════════════════════════════════════
     POINTS D'ENTRÉE
     ═══════════════════════════════════════════════════════════════════ */

  /** Dépôt par le formateur, depuis la fiche de séance de son portail. */
  public function handle_trainer_upload_session_document() {
    check_admin_referer( 'acdc_trainer_upload_session_document' );
    $account    = $this->trainer_portal_require_auth();
    $trainer_id = (int) $account->trainer_id;
    $session_id = isset( $_POST['session_id'] ) ? absint( wp_unslash( $_POST['session_id'] ) ) : 0;

    $session = $this->acdc_session_docs_trainer_owns_session( $trainer_id, $session_id );
    if ( ! $session ) {
      $this->trainer_portal_redirect( 'sessions', 'Séance introuvable, ou vous n’y êtes pas associé.', 'error' );
    }

    /* Un dépôt nominatif ne vaut que pour un apprenant DE CETTE SÉANCE. */
    $learner_id = isset( $_POST['learner_id'] ) ? absint( wp_unslash( $_POST['learner_id'] ) ) : 0;
    if ( $learner_id > 0 ) {
      /* Le contrôle interroge la MÊME liste que celle proposée à l'écran. Un
         garde plus étroit que le menu qu'il protège refuse des choix qu'on
         vient d'offrir : ici, tout apprenant venu de la convention aurait été
         rejeté après avoir été proposé. */
      $belongs = false;
      foreach ( $this->acdc_session_learners( $session ) as $candidate ) {
        if ( (int) $candidate->id === $learner_id ) {
          $belongs = true;
          break;
        }
      }
      if ( ! $belongs ) {
        $this->trainer_portal_redirect( 'sessions', 'Cet apprenant n’appartient pas à la séance.', 'error', array( 'session_id' => $session_id ) );
      }
    }

    $result = $this->acdc_session_docs_store_upload(
      isset( $_FILES['document_file'] ) ? $_FILES['document_file'] : array(),
      array(
        'session_id'  => $session_id,
        'learner_id'  => $learner_id,
        'label'       => isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '',
        'visibility'  => isset( $_POST['visibility'] ) ? sanitize_key( wp_unslash( $_POST['visibility'] ) ) : 'unlock',
        'notify'      => empty( $_POST['notify'] ) ? 0 : 1,
        'trainer_id'  => $trainer_id,
        'uploaded_by' => 'trainer',
        'uploader_id' => 0,
      )
    );

    if ( empty( $result['ok'] ) ) {
      $this->trainer_portal_redirect( 'sessions', $result['error'], 'error', array( 'session_id' => $session_id ) );
    }

    $this->trainer_portal_log_event( (int) $account->id, 'session_document_uploaded', array(
      'session_id'  => $session_id,
      'document_id' => (int) $result['id'],
      'learner_id'  => $learner_id,
    ), $trainer_id );

    if ( ! empty( $_POST['notify'] ) ) {
      $this->acdc_session_docs_notify_new_document( (int) $result['id'] );
    }

    $this->trainer_portal_redirect( 'sessions', 'Document déposé.', 'success', array( 'session_id' => $session_id ) );
  }

  /**
   * Avis de dépôt, uniquement si le formateur a coché « prévenir les
   * apprenants » — et seulement pour un document immédiatement visible :
   * annoncer un document que le destinataire ne peut pas encore ouvrir n'est
   * pas une information, c'est une frustration.
   */
  private function acdc_session_docs_notify_new_document( $document_id ) {
    global $wpdb;

    $document = $this->acdc_session_document( $document_id );
    if ( ! $document || 'immediate' !== (string) $document->visibility ) {
      return;
    }

    $where  = 'session_id = %d AND email <> \'\'';
    $params = array( (int) $document->session_id );
    if ( (int) $document->learner_id > 0 ) {
      $where   .= ' AND id = %d';
      $params[] = (int) $document->learner_id;
    }

    $learners = (array) $wpdb->get_results( $wpdb->prepare(
      "SELECT id, first_name, email FROM {$this->learner_table} WHERE {$where}",
      $params
    ) );

    $portal_url = $this->learner_portal_page_url( 'documents' );
    $label      = $this->acdc_session_document_label( $document );

    foreach ( $learners as $learner ) {
      $name  = trim( (string) $learner->first_name );
      $body  = '<p>Bonjour ' . esc_html( $name ) . ',</p>';
      $body .= '<p>Votre formateur a déposé un document dans votre espace : <strong>' . esc_html( $label ) . '</strong>.</p>';
      $body .= '<p><a href="' . esc_url( $portal_url ) . '">Ouvrir mon espace</a></p>';
      $this->acdc_session_docs_send_learner_email(
        (string) $learner->email,
        'Un nouveau document est disponible',
        $body,
        '' !== $name ? $name : 'Apprenant'
      );
    }
  }

  /** Retrait par le formateur — tant que la séance n'est pas terminée. */
  public function handle_trainer_delete_session_document() {
    $account     = $this->trainer_portal_require_auth();
    $document_id = isset( $_GET['document_id'] ) ? absint( wp_unslash( $_GET['document_id'] ) ) : 0;
    check_admin_referer( 'acdc_trainer_delete_session_document_' . $document_id );

    $document = $this->acdc_session_document( $document_id );
    if ( ! $document ) {
      $this->trainer_portal_redirect( 'sessions', 'Document introuvable.', 'error' );
    }
    $session = $this->acdc_session_docs_trainer_owns_session( (int) $account->trainer_id, (int) $document->session_id );
    if ( ! $session ) {
      $this->trainer_portal_redirect( 'sessions', 'Vous n’êtes pas associé à cette séance.', 'error' );
    }
    if ( (int) $document->trainer_id !== (int) $account->trainer_id ) {
      $this->trainer_portal_redirect( 'sessions', 'Vous ne pouvez retirer que vos propres dépôts.', 'error', array( 'session_id' => (int) $document->session_id ) );
    }
    if ( ! $this->acdc_session_document_trainer_may_delete( $document, $session ) ) {
      $this->trainer_portal_redirect(
        'sessions',
        'La séance est terminée : ce document est devenu une pièce du dossier et ne peut plus être retiré. Contactez l’organisme si nécessaire.',
        'error',
        array( 'session_id' => (int) $document->session_id )
      );
    }

    $this->acdc_session_docs_delete( $document );
    $this->trainer_portal_log_event( (int) $account->id, 'session_document_deleted', array(
      'session_id'  => (int) $document->session_id,
      'document_id' => (int) $document_id,
    ), (int) $account->trainer_id );

    $this->trainer_portal_redirect( 'sessions', 'Document retiré.', 'success', array( 'session_id' => (int) $document->session_id ) );
  }

  /** Téléchargement par le formateur de la séance. */
  public function handle_trainer_download_session_document() {
    $account     = $this->trainer_portal_require_auth();
    $document_id = isset( $_GET['document_id'] ) ? absint( wp_unslash( $_GET['document_id'] ) ) : 0;
    check_admin_referer( 'acdc_trainer_download_session_document_' . $document_id );

    $document = $this->acdc_session_document( $document_id );
    if ( ! $document ) {
      wp_die( esc_html( 'Document introuvable.' ) );
    }
    if ( ! $this->acdc_session_docs_trainer_owns_session( (int) $account->trainer_id, (int) $document->session_id ) ) {
      wp_die( esc_html( 'Accès non autorisé.' ) );
    }
    $this->acdc_session_docs_stream( $document );
  }

  /** Déblocage anticipé par le formateur. */
  public function handle_trainer_unlock_session_documents() {
    $session_id = isset( $_POST['session_id'] ) ? absint( wp_unslash( $_POST['session_id'] ) ) : 0;

    /* ACDC 3.25.208 — Une trace dès la PREMIÈRE ligne, avant toute vérification.
       La recette a observé une requête qui n'a jamais rendu la main et n'a rien
       appliqué. Les deux faits ensemble disent que l'écriture n'a pas eu lieu,
       donc que le handler n'est pas allé jusque-là — mais rien, dans les
       journaux, ne permettait de savoir s'il avait seulement été atteint.
       Cette ligne tranche la question au prochain essai : si elle apparaît, le
       blocage est ici ; si elle manque, il est en amont de nous. */
    $this->insert_system_log( array(
      'log_level'   => 'info',
      'event_type'  => 'session_documents_unlock_request',
      'action_key'  => 'session_documents_unlock',
      'object_type' => 'session',
      'object_id'   => $session_id,
      'message'     => 'Demande de déblocage reçue.',
    ) );

    check_admin_referer( 'acdc_trainer_unlock_session_documents' );
    $account = $this->trainer_portal_require_auth();

    $session = $this->acdc_session_docs_trainer_owns_session( (int) $account->trainer_id, $session_id );
    if ( ! $session ) {
      $this->trainer_portal_redirect( 'sessions', 'Séance introuvable, ou vous n’y êtes pas associé.', 'error' );
    }

    $result = $this->acdc_session_docs_unlock( $session, 'formateur #' . (int) $account->trainer_id );
    $this->trainer_portal_log_event( (int) $account->id, 'session_documents_unlocked', array(
      'session_id' => $session_id,
      'result'     => $result,
    ), (int) $account->trainer_id );

    $messages = array(
      'done'    => 'Documents débloqués : les apprenants y ont accès. L’avis par e-mail part dans la minute qui suit.',
      'already' => 'Ces documents étaient déjà débloqués.',
      'error'   => 'Le déblocage n’a pas pu être enregistré. Rien n’a changé — le détail est dans le journal système.',
    );
    $types = array( 'done' => 'success', 'already' => 'info', 'error' => 'error' );

    $this->trainer_portal_redirect(
      'sessions',
      $messages[ $result ] ?? $messages['error'],
      $types[ $result ] ?? 'error',
      array( 'session_id' => $session_id )
    );
  }

  /** Téléchargement par l'apprenant — le verrou s'applique ici, pas ailleurs. */
  public function handle_learner_download_session_document() {
    $account     = $this->learner_portal_require_auth();
    $document_id = isset( $_GET['document_id'] ) ? absint( wp_unslash( $_GET['document_id'] ) ) : 0;

    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'acdc_learner_download_session_document_' . $document_id ) ) {
      $this->learner_portal_redirect( 'documents', 'Jeton de téléchargement invalide.', 'error' );
    }

    $document = $this->acdc_session_document( $document_id );
    if ( ! $document || ! $this->acdc_session_docs_learner_may_read( $account->email, $document ) ) {
      $this->learner_portal_redirect( 'documents', 'Document indisponible ou accès non autorisé.', 'error' );
    }

    $this->learner_portal_log_event( $account->id, 'session_document_downloaded', array(
      'document_id' => $document_id,
      'session_id'  => (int) $document->session_id,
    ) );

    $this->acdc_session_docs_stream( $document );
  }

  /**
   * Téléchargement par l'organisme.
   *
   * Sans condition de verrou ni de date : l'OF doit pouvoir produire la pièce à
   * tout moment, c'est la raison d'être de cet accès.
   */
  public function handle_admin_download_session_document() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Action non autorisée.' ) );
    }
    $document_id = isset( $_GET['document_id'] ) ? absint( wp_unslash( $_GET['document_id'] ) ) : 0;
    check_admin_referer( 'acdc_admin_download_session_document_' . $document_id );

    $document = $this->acdc_session_document( $document_id );
    if ( ! $document ) {
      wp_die( esc_html( 'Document introuvable.' ) );
    }
    $this->acdc_session_docs_stream( $document );
  }

  /* ═══════════════════════════════════════════════════════════════════
     VUE ORGANISME
     ═══════════════════════════════════════════════════════════════════ */

  /**
   * Le panneau que voit l'organisme sur la fiche d'une séance.
   *
   * C'est la contrepartie indispensable du dépôt formateur : ce qui a été remis
   * aux apprenants d'une séance doit pouvoir être produit par l'OF le jour de
   * l'audit. Sans cet écran, le formateur déposerait des pièces que personne
   * chez l'organisme ne pourrait retrouver.
   */
  private function render_session_documents_panel( $session ) {
    if ( empty( $session->id ) ) {
      return;
    }

    $documents = $this->acdc_session_documents( (int) $session->id );
    $state     = $this->acdc_session_docs_unlock_state( $session );

    global $wpdb;
    $learners = (array) $wpdb->get_results( $wpdb->prepare(
      "SELECT id, first_name, last_name, usage_last_name FROM {$this->learner_table} WHERE session_id = %d",
      (int) $session->id
    ) );
    $learner_names = array();
    foreach ( $learners as $l ) {
      $last = ! empty( $l->usage_last_name ) ? $l->usage_last_name : $l->last_name;
      $learner_names[ (int) $l->id ] = trim( $l->first_name . ' ' . $last );
    }
    ?>
    <div class="acdc-panel acdc-mb-18">
      <h3>Documents de séance <span style="font-weight:400;color:#5a6577;font-size:13px;">— preuves Qualiopi</span></h3>
      <p style="margin:0 0 14px;color:#5a6577;font-size:13px;">
        Documents déposés par le formateur pour cette séance. Ils ne figurent pas dans la bibliothèque de la formation :
        ils ne concernent que les apprenants qui étaient présents.
        <?php if ( ! empty( $state['unlocked'] ) ) : ?>
          <br>Accès apprenant : <strong>ouvert</strong><?php echo 'manual' === $state['reason'] && ! empty( $state['unlocked_at'] ) ? esc_html( ' (débloqué le ' . mysql2date( 'd/m/Y à H:i', $state['unlocked_at'] ) . ')' ) : ' (séance terminée)'; ?>.
        <?php else : ?>
          <br>Accès apprenant : <strong>verrouillé</strong> jusqu’à la fin de la séance<?php echo ! empty( $state['end_label'] ) ? esc_html( ' (' . $state['end_label'] . ')' ) : ''; ?>.
        <?php endif; ?>
      </p>

      <?php if ( empty( $documents ) ) : ?>
        <p style="margin:0;color:#5a6577;">Aucun document déposé sur cette séance.</p>
      <?php else : ?>
        <table class="acdc-table">
          <thead><tr><th>Document</th><th>Destinataire</th><th>Visibilité</th><th>Déposé par</th><th>Déposé le</th><th></th></tr></thead>
          <tbody>
          <?php foreach ( $documents as $doc ) :
            $target = (int) $doc->learner_id > 0
              ? ( $learner_names[ (int) $doc->learner_id ] ?? 'Apprenant #' . (int) $doc->learner_id )
              : 'Toute la séance';
          ?>
            <tr>
              <td>
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_admin_download_session_document&document_id=' . (int) $doc->id ), 'acdc_admin_download_session_document_' . (int) $doc->id ) ); ?>">
                  <?php echo esc_html( $this->acdc_session_document_label( $doc ) ); ?>
                </a>
              </td>
              <td><?php echo esc_html( $target ); ?></td>
              <td><?php echo 'immediate' === (string) $doc->visibility ? 'Tout de suite' : 'À la fin de la formation'; ?></td>
              <td><?php echo 'admin' === (string) $doc->uploaded_by ? 'Organisme' : 'Formateur'; ?></td>
              <td><?php echo esc_html( ! empty( $doc->created_at ) ? mysql2date( 'd/m/Y à H:i', $doc->created_at ) : '—' ); ?></td>
              <td>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                      onsubmit="return confirm('Supprimer définitivement ce document ? Il s’agit d’une pièce du dossier de formation.');">
                  <input type="hidden" name="action" value="acdc_admin_delete_session_document">
                  <?php wp_nonce_field( 'acdc_admin_delete_session_document_' . (int) $doc->id ); ?>
                  <input type="hidden" name="document_id" value="<?php echo (int) $doc->id; ?>">
                  <button type="submit" class="acdc-button acdc-button-soft" style="padding:4px 12px;font-size:12px;">Supprimer</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <?php
  }

  /** Retrait par l'organisme — en toutes circonstances. */
  public function handle_admin_delete_session_document() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Action non autorisée.' ) );
    }
    $document_id = isset( $_POST['document_id'] ) ? absint( wp_unslash( $_POST['document_id'] ) ) : 0;
    check_admin_referer( 'acdc_admin_delete_session_document_' . $document_id );

    $document = $this->acdc_session_document( $document_id );
    if ( $document ) {
      $this->acdc_session_docs_delete( $document );
      $this->log_error( 'learner_portal', 'Document de séance supprimé par l’organisme.', array(
        'document_id' => $document_id,
        'session_id'  => (int) $document->session_id,
        'user_id'     => get_current_user_id(),
      ) );
    }

    $session_id = $document ? (int) $document->session_id : 0;
    $this->redirect_to_portal( 'sessions_validated', 'Document de séance supprimé.', 'success', array( 'action' => 'view', 'item_id' => $session_id ) );
  }
}
