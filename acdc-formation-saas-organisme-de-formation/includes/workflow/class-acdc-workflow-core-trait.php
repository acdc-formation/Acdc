<?php
/**
 * ACDC Workflow — socle du module d'orchestration du parcours.
 *
 * Ce module ne remplace aucun module métier : il les ORDONNE. Il tient, pour
 * chaque dossier, la liste des étapes du parcours, leur date prévue et leur
 * état, puis déclenche au bon moment l'action correspondante.
 *
 * Deux principes gouvernent tout le reste :
 *
 *   1. LA SOURCE DE VÉRITÉ RESTE LA DONNÉE MÉTIER. Le moteur ne mémorise pas
 *      « le devis a été signé » : il relit le devis. Un parcours se recalcule
 *      donc à chaque passage du cron à partir de l'état réel du dossier. C'est
 *      plus robuste qu'une chaîne d'événements : si un module oublie de prévenir,
 *      ou si David modifie une pièce à la main, le parcours se remet d'aplomb au
 *      passage suivant au lieu de rester bloqué.
 *
 *   2. RIEN NE PART TANT QUE L'ON N'A PAS VU LE PLAN. Le moteur démarre en mode
 *      simulation : il planifie tout, journalise ce qu'il AURAIT envoyé, et
 *      n'envoie rien. On regarde, on corrige les délais, puis on ouvre le
 *      robinet.
 *
 * @since 3.25.185
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

trait ACDC_Workflow_Core_Trait {

  /* =====================================================================
   * Schéma
   * ===================================================================== */

  /**
   * Deux tables seulement : le parcours (« run ») et ses étapes.
   *
   * Un parcours porte les identifiants des pièces du dossier au fur et à mesure
   * qu'elles apparaissent — recueil, proposition, devis, convention, séance.
   * Ces colonnes ne sont pas la vérité, elles sont un raccourci de lecture :
   * la réconciliation les réécrit à chaque passage.
   */
  private function acdc_wf_install_schema() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset_collate = $wpdb->get_charset_collate();

    $sql_runs = "CREATE TABLE {$this->workflow_run_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      need_id BIGINT UNSIGNED DEFAULT NULL,
      prospect_id BIGINT UNSIGNED DEFAULT NULL,
      company_id BIGINT UNSIGNED DEFAULT NULL,
      proposal_id BIGINT UNSIGNED DEFAULT NULL,
      quote_id BIGINT UNSIGNED DEFAULT NULL,
      contract_id BIGINT UNSIGNED DEFAULT NULL,
      formation_id BIGINT UNSIGNED DEFAULT NULL,
      session_id BIGINT UNSIGNED DEFAULT NULL,
      funder_id BIGINT UNSIGNED DEFAULT NULL,
      label VARCHAR(190) NOT NULL DEFAULT '',
      phase VARCHAR(40) NOT NULL DEFAULT 'commercial',
      status VARCHAR(20) NOT NULL DEFAULT 'active',
      close_reason VARCHAR(190) NOT NULL DEFAULT '',
      formation_start_at DATETIME DEFAULT NULL,
      formation_end_at DATETIME DEFAULT NULL,
      dates_source VARCHAR(120) NOT NULL DEFAULT '',
      last_reconciled_at DATETIME DEFAULT NULL,
      started_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      closed_at DATETIME DEFAULT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY need_id (need_id),
      KEY status (status),
      KEY phase (phase),
      KEY session_id (session_id)
    ) {$charset_collate};";
    dbDelta( $sql_runs );

    /* `dedupe_key` porte l'unicité fonctionnelle d'une étape : une même étape
       pour une même cible ne peut exister qu'une fois par parcours. C'est ce qui
       rend la réconciliation idempotente — elle peut tourner mille fois sans
       jamais planifier deux fois le même envoi. */
    $sql_steps = "CREATE TABLE {$this->workflow_step_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      run_id BIGINT UNSIGNED NOT NULL,
      step_key VARCHAR(60) NOT NULL DEFAULT '',
      dedupe_key VARCHAR(120) NOT NULL DEFAULT '',
      parent_key VARCHAR(60) NOT NULL DEFAULT '',
      phase VARCHAR(40) NOT NULL DEFAULT '',
      mode VARCHAR(12) NOT NULL DEFAULT 'auto',
      label VARCHAR(190) NOT NULL DEFAULT '',
      target_type VARCHAR(30) NOT NULL DEFAULT '',
      target_id BIGINT UNSIGNED DEFAULT NULL,
      target_label VARCHAR(190) NOT NULL DEFAULT '',
      scheduled_at DATETIME DEFAULT NULL,
      executed_at DATETIME DEFAULT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'pending',
      is_alert TINYINT(1) NOT NULL DEFAULT 0,
      attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
      result_note TEXT,
      last_error TEXT,
      payload_json LONGTEXT,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY run_dedupe (run_id, dedupe_key),
      KEY run_id (run_id),
      KEY status (status),
      KEY scheduled_at (scheduled_at),
      KEY mode (mode)
    ) {$charset_collate};";
    dbDelta( $sql_steps );

    $this->acdc_wf_migrate_replan_after_timezone_fix();
  }

  /**
   * ACDC 3.25.186 — Remise à plat des plans établis par la 3.25.185.
   *
   * Cette version-là planifiait avec un décalage horaire de deux fois l'offset
   * — 17 h devenait 21 h — et, faute d'horaires de séance, semait jusqu'à 120
   * rappels d'émargement par dossier, week-ends compris. Corriger le calcul ne
   * suffit pas : les lignes déjà écrites gardent leurs mauvaises heures, et
   * celles qui ont été « jouées » en simulation ne sont plus jamais recalculées.
   *
   * On efface donc les ÉTAPES, jamais les parcours. La table des étapes ne
   * contient aucune donnée métier : c'est un plan, et un plan faux se refait.
   * La réconciliation suivante le reconstruit intégralement à partir des
   * dossiers réels.
   *
   * Le drapeau est posé AVANT le travail : perdre une remise à plat est
   * rattrapable, la rejouer en boucle ne l'est pas.
   */
  private function acdc_wf_migrate_replan_after_timezone_fix() {
    global $wpdb;

    if ( '1' === (string) get_option( 'acdc_of_workflow_replan_3_25_186', '' ) ) {
      return;
    }
    update_option( 'acdc_of_workflow_replan_3_25_186', '1', false );

    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->workflow_step_table ) );
    if ( $exists === $this->workflow_step_table ) {
      $wpdb->query( "DELETE FROM {$this->workflow_step_table}" );
    }
  }

  /* =====================================================================
   * Registre des étapes — la traduction fidèle du schéma de David
   * ===================================================================== */

  private function acdc_wf_phases() {
    return array(
      'commercial'  => 'Commercial',
      'preparation' => 'Préparation',
      'animation'   => 'Animation',
      'evaluation'  => 'Évaluation',
    );
  }

  /**
   * Chaque nœud du schéma devient une entrée ici.
   *
   * `mode` vaut :
   *   - `auto`  : le moteur exécute, personne n'intervient (envois, relances) ;
   *   - `task`  : le moteur inscrit une tâche dans la liste « À faire » et
   *               attend que la pièce apparaisse — c'est le choix de David pour
   *               tout document qui engage l'organisme ;
   *   - `alert` : une tâche doublée d'un signalement au tableau de bord.
   */
  private function acdc_wf_step_registry() {
    return array(
      /* ---- Commercial ------------------------------------------------- */
      'proposal_create' => array(
        'phase' => 'commercial', 'mode' => 'task',
        'label' => 'Créer la proposition commerciale',
      ),
      'quote_create' => array(
        'phase' => 'commercial', 'mode' => 'task',
        'label' => 'Créer le devis',
      ),
      'quote_reminder' => array(
        'phase' => 'commercial', 'mode' => 'auto',
        'label' => 'Relancer la signature du devis',
      ),
      'quote_rdv' => array(
        'phase' => 'commercial', 'mode' => 'task',
        'label' => 'Devis toujours non signé : créer un rendez-vous',
      ),
      'convention_create' => array(
        'phase' => 'commercial', 'mode' => 'task',
        'label' => 'Créer la convention / le contrat',
      ),
      'convention_reminder' => array(
        'phase' => 'commercial', 'mode' => 'auto',
        'label' => 'Relancer la signature de la convention',
      ),
      /* Le schéma laisse cette sortie sans suite. David a tranché : rendez-vous
         ET alerte. C'est le cas où un dossier se perd sans que personne ne le
         sache — il ne pouvait pas rester muet. */
      'convention_rdv' => array(
        'phase' => 'commercial', 'mode' => 'alert',
        'label' => 'Convention non signée : créer un rendez-vous',
      ),

      /* ---- Préparation ------------------------------------------------- */
      'registration' => array(
        'phase' => 'preparation', 'mode' => 'auto',
        'label' => 'Inscrire les apprenants nommés dans la convention',
      ),
      'nad_send' => array(
        'phase' => 'preparation', 'mode' => 'auto',
        'label' => 'Envoyer l’analyse des besoins',
        'per_learner' => true,
      ),
      'trainer_pack' => array(
        'phase' => 'preparation', 'mode' => 'auto',
        'label' => 'Déposer le dossier de formation dans l’extranet formateur',
      ),
      'learner_invite' => array(
        'phase' => 'preparation', 'mode' => 'auto',
        'label' => 'Ouvrir l’extranet apprenant',
        'per_learner' => true,
      ),
      'convocation' => array(
        'phase' => 'preparation', 'mode' => 'auto',
        'label' => 'Envoyer la convocation',
      ),

      /* ---- Animation ---------------------------------------------------- */
      /* ACDC 3.25.186 — Quand la séance ne porte ni horaires ni demi-journées,
         le moteur ne devine plus : il le dit. La 3.25.185 balayait la plage
         calendaire entière et produisait 120 rappels sur deux mois, week-ends
         compris, jusqu'à buter sur son propre garde-fou. Un plan faux et
         volumineux est pire qu'un plan absent : il noie les vraies lignes. */
      'session_hours_missing' => array(
        'phase' => 'animation', 'mode' => 'alert',
        'label' => 'Horaires de la séance non renseignés : aucun rappel d’émargement planifiable',
      ),
      'emargement_am' => array(
        'phase' => 'animation', 'mode' => 'auto',
        'label' => 'Rappel d’émargement au formateur — séance du matin',
      ),
      'emargement_pm' => array(
        'phase' => 'animation', 'mode' => 'auto',
        'label' => 'Rappel d’émargement au formateur — séance de l’après-midi',
      ),

      /* ---- Évaluation ---------------------------------------------------- */
      'survey_hot' => array(
        'phase' => 'evaluation', 'mode' => 'auto',
        'label' => 'Envoyer l’enquête à chaud aux apprenants',
        'survey_type' => 'hot',
      ),
      'survey_company' => array(
        'phase' => 'evaluation', 'mode' => 'auto',
        'label' => 'Envoyer l’enquête entreprise',
        'survey_type' => 'company',
      ),
      'survey_funder' => array(
        'phase' => 'evaluation', 'mode' => 'auto',
        'label' => 'Envoyer l’enquête financeur',
        'survey_type' => 'funder',
      ),
      'survey_trainer' => array(
        'phase' => 'evaluation', 'mode' => 'auto',
        'label' => 'Envoyer l’enquête formateur',
        'survey_type' => 'trainer',
      ),
      'survey_cold' => array(
        'phase' => 'evaluation', 'mode' => 'auto',
        'label' => 'Envoyer l’enquête à froid aux apprenants',
        'survey_type' => 'cold',
      ),
    );
  }

  private function acdc_wf_step_config( $step_key ) {
    $registry = $this->acdc_wf_step_registry();
    $step_key = (string) $step_key;
    /* Les relances portent la clé de leur enquête suivie de _r1, _r2, _r3. */
    if ( preg_match( '/^(.+)_r([123])$/', $step_key, $m ) && isset( $registry[ $m[1] ] ) ) {
      $parent = $registry[ $m[1] ];
      $rank   = (int) $m[2];
      return array(
        'phase'       => $parent['phase'],
        'mode'        => 'auto',
        'label'       => sprintf( '%s — relance %d', $this->acdc_wf_survey_label( $m[1] ), $rank ),
        'survey_type' => isset( $parent['survey_type'] ) ? $parent['survey_type'] : '',
        'parent_key'  => $m[1],
        'reminder'    => $rank,
      );
    }
    return isset( $registry[ $step_key ] ) ? $registry[ $step_key ] : array();
  }

  private function acdc_wf_survey_label( $step_key ) {
    $labels = array(
      'survey_hot'     => 'Enquête à chaud',
      'survey_company' => 'Enquête entreprise',
      'survey_funder'  => 'Enquête financeur',
      'survey_trainer' => 'Enquête formateur',
      'survey_cold'    => 'Enquête à froid',
    );
    return isset( $labels[ $step_key ] ) ? $labels[ $step_key ] : (string) $step_key;
  }

  /* =====================================================================
   * Réglages
   * ===================================================================== */

  /**
   * Les valeurs du schéma de David servent de défauts. Tout est réglable :
   * il ne doit plus avoir à me demander un zip pour passer 10 jours à 7.
   */
  private function acdc_wf_default_settings() {
    return array(
      'enabled'                  => 0,
      'simulation'               => 1,
      'test_mode'                => 1,
      'allowed_recipients'       => '',
      'business_days_reminders'  => 1,
      'delays' => array(
        /* Commercial — en jours */
        'quote_reminder_days'        => 10,
        'quote_rdv_after_days'       => 10,
        'convention_reminder_days'   => 3,
        'convention_rdv_after_days'  => 3,

        /* Préparation */
        'nad_days_before_start'      => 15,
        'trainer_pack_days_before'   => 15,
        'convocation_hour'           => 17,
        'emargement_lead_minutes'    => 30,

        /* Évaluation — décalage du premier envoi après la fin de la formation */
        'survey_hot_offset_hours'     => 0,
        'survey_company_offset_hours' => 24,
        'survey_funder_offset_hours'  => 24,
        'survey_trainer_offset_hours' => 24,
        'survey_cold_offset_days'     => 90,

        /* Évaluation — relances, CUMULATIVES : chaque délai part de la relance
           précédente, conformément à la réponse de David (J+3, J+8, J+15). */
        'survey_hot_reminder_hours'     => array( 24, 48, 72 ),
        'survey_company_reminder_days'  => array( 3, 5, 7 ),
        'survey_funder_reminder_days'   => array( 3, 5, 7 ),
        'survey_trainer_reminder_days'  => array( 3, 5, 7 ),
        'survey_cold_reminder_days'     => array( 3, 5, 7 ),
      ),
    );
  }

  private function acdc_wf_settings() {
    $saved    = get_option( 'acdc_of_workflow_settings', array() );
    $defaults = $this->acdc_wf_default_settings();
    if ( ! is_array( $saved ) ) {
      return $defaults;
    }
    $merged           = wp_parse_args( $saved, $defaults );
    $merged['delays'] = wp_parse_args(
      isset( $saved['delays'] ) && is_array( $saved['delays'] ) ? $saved['delays'] : array(),
      $defaults['delays']
    );
    return $merged;
  }

  private function acdc_wf_delay( $key, $fallback = 0 ) {
    $settings = $this->acdc_wf_settings();
    return isset( $settings['delays'][ $key ] ) ? $settings['delays'][ $key ] : $fallback;
  }

  private function acdc_wf_is_enabled() {
    $settings = $this->acdc_wf_settings();
    return ! empty( $settings['enabled'] );
  }

  private function acdc_wf_is_simulation() {
    $settings = $this->acdc_wf_settings();
    return ! empty( $settings['simulation'] );
  }

  /* =====================================================================
   * Garde-fou d'envoi
   * ===================================================================== */

  /**
   * Le mode recette. Tant qu'il est actif, seule une adresse explicitement
   * déclarée peut recevoir quoi que ce soit.
   *
   * Ce n'est pas une précaution théorique : la base contient onze financeurs
   * OPCO réels, et une automatisation qui part seule écrit à de vraies
   * personnes. Le refus est journalisé — un envoi bloqué doit se voir.
   */
  private function acdc_wf_may_send_to( $email ) {
    $email    = sanitize_email( (string) $email );
    $settings = $this->acdc_wf_settings();

    if ( '' === $email || ! is_email( $email ) ) {
      return false;
    }
    if ( empty( $settings['test_mode'] ) ) {
      return true;
    }

    $allowed = array_filter( array_map(
      'strtolower',
      array_map( 'trim', preg_split( '/[\s,;]+/', (string) $settings['allowed_recipients'] ) )
    ) );

    return in_array( strtolower( $email ), $allowed, true );
  }

  /* =====================================================================
   * Calendrier
   * ===================================================================== */

  /**
   * Décalage au jour ouvré suivant — pour les RELANCES uniquement.
   *
   * David a été précis là-dessus : l'envoi initial part quand il doit partir,
   * y compris un samedi, parce qu'il suit un fait (la fin de la formation).
   * Une relance, elle, est une sollicitation : elle attend le lundi.
   */
  private function acdc_wf_shift_to_business_day( $timestamp ) {
    $settings = $this->acdc_wf_settings();
    if ( empty( $settings['business_days_reminders'] ) ) {
      return (int) $timestamp;
    }
    $ts    = (int) $timestamp;
    $guard = 0;
    while ( in_array( (int) wp_date( 'N', $ts ), array( 6, 7 ), true ) && $guard < 7 ) {
      $ts += DAY_IN_SECONDS;
      $guard++;
    }
    return $ts;
  }

  /**
   * ACDC 3.25.186 — Une seule représentation du temps, et elle est explicite.
   *
   * La 3.25.185 mélangeait deux conventions et payait le prix classique : toutes
   * les heures planifiées étaient décalées du double du décalage horaire — 17 h
   * devenait 21 h en été, 19 h en hiver. L'erreur venait de current_time
   * ('timestamp'), qui rend un horodatage DÉJÀ décalé en heure locale, puis de
   * wp_date() qui rajoutait ce même décalage à l'affichage.
   *
   * La règle, désormais, tient en trois lignes :
   *   - en mémoire, un horodatage est TOUJOURS un vrai timestamp UTC ;
   *   - en base, une date-heure est TOUJOURS de l'heure locale (convention du
   *     plugin, héritée de current_time('mysql')) ;
   *   - on ne franchit la frontière qu'avec acdc_wf_mysql() dans un sens et
   *     acdc_wf_ts() dans l'autre. Jamais strtotime() nu sur une valeur lue en
   *     base : c'est lui qui a introduit le décalage.
   */
  private function acdc_wf_mysql( $timestamp ) {
    return wp_date( 'Y-m-d H:i:s', (int) $timestamp );
  }

  private function acdc_wf_now() {
    return time();
  }

  /** Heure locale stockée en base → horodatage UTC. */
  private function acdc_wf_ts( $mysql_local ) {
    $mysql_local = trim( (string) $mysql_local );
    if ( '' === $mysql_local || 0 === strpos( $mysql_local, '0000-00-00' ) ) {
      return 0;
    }
    try {
      $date = new DateTimeImmutable( $mysql_local, wp_timezone() );
    } catch ( Exception $e ) {
      return 0;
    }
    return (int) $date->getTimestamp();
  }

  /** Date locale + heure locale → horodatage UTC. */
  private function acdc_wf_local_ts( $date, $time = '00:00:00' ) {
    return $this->acdc_wf_ts( trim( (string) $date ) . ' ' . trim( (string) $time ) );
  }

  /* =====================================================================
   * Lecture
   * ===================================================================== */

  private function acdc_wf_get_run( $run_id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare(
      "SELECT * FROM {$this->workflow_run_table} WHERE id = %d",
      (int) $run_id
    ) );
  }

  private function acdc_wf_get_run_by_need( $need_id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare(
      "SELECT * FROM {$this->workflow_run_table} WHERE need_id = %d",
      (int) $need_id
    ) );
  }

  private function acdc_wf_get_steps( $run_id, $statuses = array() ) {
    global $wpdb;
    $sql    = "SELECT * FROM {$this->workflow_step_table} WHERE run_id = %d";
    $values = array( (int) $run_id );
    if ( ! empty( $statuses ) ) {
      $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
      $sql         .= " AND status IN ({$placeholders})";
      $values       = array_merge( $values, array_map( 'strval', $statuses ) );
    }
    $sql .= " ORDER BY COALESCE(scheduled_at, '9999-12-31') ASC, id ASC";
    return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
  }

  /**
   * La prochaine action d'un parcours : la première étape encore à venir.
   * C'est la colonne qui compte dans l'écran de suivi — celle qui répond à
   * « et maintenant, il se passe quoi, et quand ? ».
   */
  private function acdc_wf_next_step( $run_id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare(
      "SELECT * FROM {$this->workflow_step_table}
        WHERE run_id = %d AND status IN ('pending','waiting')
        ORDER BY (scheduled_at IS NULL) ASC, scheduled_at ASC, id ASC
        LIMIT 1",
      (int) $run_id
    ) );
  }

  private function acdc_wf_status_labels() {
    return array(
      'waiting'   => 'En attente d’un préalable',
      'pending'   => 'Planifiée',
      'done'      => 'Faite',
      /* ACDC 3.25.186 — En simulation, « Faite » était un mensonge : rien n'avait
         été fait. L'état porte désormais lui-même la nuance, sans dépendre de la
         colonne Observation que l'œil saute. */
      'simulated' => 'Simulée — aucun envoi',
      'skipped'   => 'Sans objet',
      'cancelled' => 'Annulée',
      'failed'    => 'En échec',
    );
  }

  /**
   * ACDC 3.25.186 — Le libellé d'un dossier, identique partout.
   *
   * Le journal affichait le thème du recueil — « Intelligence artificielle »
   * pour quatre parcours différents. Un journal qui ne dit pas de quel dossier
   * il parle n'est pas un journal.
   */
  private function acdc_wf_run_display_label( $row ) {
    $company = isset( $row->prospect_company ) ? trim( (string) $row->prospect_company ) : '';
    $base    = '' !== $company ? $company : (string) ( $row->run_label ?? $row->label ?? '' );
    $need_id = (int) ( $row->need_id ?? 0 );
    if ( '' === $base ) {
      $base = 'Dossier';
    }
    return $need_id > 0 ? $base . ' (recueil n°' . $need_id . ')' : $base;
  }

  /** Les états qui signifient « cette étape est derrière nous ». */
  private function acdc_wf_settled_statuses() {
    return array( 'done', 'simulated', 'failed', 'skipped', 'cancelled' );
  }

  /** Mode recette armé mais aucune adresse déclarée : plus rien ne peut partir. */
  private function acdc_wf_test_mode_is_mute() {
    $settings = $this->acdc_wf_settings();
    if ( empty( $settings['test_mode'] ) ) {
      return false;
    }
    return '' === trim( (string) $settings['allowed_recipients'] );
  }

  private function acdc_wf_status_label( $status ) {
    $labels = $this->acdc_wf_status_labels();
    $status = (string) $status;
    return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
  }
}
