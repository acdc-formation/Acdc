<?php
/**
 * ACDC Workflow — moteur : réconciliation, planification, exécution.
 *
 * Le cœur du module tient en deux gestes répétés à chaque passage du cron :
 *
 *   RÉCONCILIER — relire le dossier réel (recueil, proposition, devis,
 *   convention, séance) et en déduire l'état de chaque étape. C'est ce qui rend
 *   le moteur incassable : il ne mémorise aucune décision, il les recalcule.
 *   Un devis signé à la main hors parcours, une convention supprimée, une date
 *   de séance déplacée — tout est repris au passage suivant.
 *
 *   EXÉCUTER — prendre les étapes dont l'heure est venue et les jouer, ou les
 *   journaliser sans rien envoyer tant que le mode simulation est actif.
 *
 * @since 3.25.185
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

trait ACDC_Workflow_Engine_Trait {

  /* =====================================================================
   * Cron
   * ===================================================================== */

  public function acdc_wf_cron() {
    if ( ! $this->acdc_wf_is_enabled() ) {
      return;
    }
    $this->acdc_wf_open_runs_for_new_needs();
    $this->acdc_wf_reconcile_active_runs();
    $this->acdc_wf_execute_due_steps();
  }

  /* =====================================================================
   * Ouverture des parcours
   * ===================================================================== */

  /**
   * Le parcours s'arme à la création du recueil des besoins — pas du prospect.
   * C'est le choix de David, et il est juste : un prospect n'engage rien, un
   * recueil oui. On balaie donc les recueils sans parcours.
   *
   * Le balayage est borné : un lot par passage. Une base qui contiendrait mille
   * recueils anciens ne doit pas ouvrir mille parcours dans la même requête —
   * c'est exactement le genre de travail non borné qui a mis le site à terre le
   * 9 août.
   */
  private function acdc_wf_open_runs_for_new_needs( $batch = 25 ) {
    global $wpdb;

    $since = get_option( 'acdc_of_workflow_pivot_need_id', 0 );

    $needs = $wpdb->get_results( $wpdb->prepare(
      "SELECT n.id, n.source_prospect_id, n.company_id, n.theme
         FROM {$this->need_table} n
         LEFT JOIN {$this->workflow_run_table} r ON r.need_id = n.id
        WHERE r.id IS NULL AND n.id > %d
        ORDER BY n.id ASC
        LIMIT %d",
      (int) $since,
      (int) $batch
    ) );

    if ( empty( $needs ) ) {
      return;
    }

    $now = $this->acdc_wf_mysql( $this->acdc_wf_now() );
    foreach ( $needs as $need ) {
      $wpdb->insert( $this->workflow_run_table, array(
        'need_id'     => (int) $need->id,
        'prospect_id' => (int) $need->source_prospect_id,
        'company_id'  => (int) $need->company_id,
        'label'       => (string) ( $need->theme ? $need->theme : 'Recueil n°' . (int) $need->id ),
        'phase'       => 'commercial',
        'status'      => 'active',
        'started_at'  => $now,
        'updated_at'  => $now,
      ) );
      update_option( 'acdc_of_workflow_pivot_need_id', (int) $need->id, false );
    }
  }

  /**
   * Ouverture immédiate à l'enregistrement d'un recueil, sans attendre le cron :
   * David crée le recueil et voit le parcours dans la foulée.
   */
  public function acdc_wf_on_need_saved( $need_id ) {
    if ( ! $this->acdc_wf_is_enabled() ) {
      return;
    }
    $need_id = (int) $need_id;
    if ( $need_id <= 0 || $this->acdc_wf_get_run_by_need( $need_id ) ) {
      return;
    }
    global $wpdb;
    $need = $wpdb->get_row( $wpdb->prepare(
      "SELECT id, source_prospect_id, company_id, theme FROM {$this->need_table} WHERE id = %d",
      $need_id
    ) );
    if ( ! $need ) {
      return;
    }
    $now = $this->acdc_wf_mysql( $this->acdc_wf_now() );
    $wpdb->insert( $this->workflow_run_table, array(
      'need_id'     => $need_id,
      'prospect_id' => (int) $need->source_prospect_id,
      'company_id'  => (int) $need->company_id,
      'label'       => (string) ( $need->theme ? $need->theme : 'Recueil n°' . $need_id ),
      'phase'       => 'commercial',
      'status'      => 'active',
      'started_at'  => $now,
      'updated_at'  => $now,
    ) );
    $run = $this->acdc_wf_get_run_by_need( $need_id );
    if ( $run ) {
      $this->acdc_wf_reconcile_run( $run );
    }
  }

  /* =====================================================================
   * Réconciliation
   * ===================================================================== */

  private function acdc_wf_reconcile_active_runs( $batch = 40 ) {
    global $wpdb;
    $runs = $wpdb->get_results( $wpdb->prepare(
      "SELECT * FROM {$this->workflow_run_table}
        WHERE status = 'active'
        ORDER BY (last_reconciled_at IS NULL) DESC, last_reconciled_at ASC
        LIMIT %d",
      (int) $batch
    ) );
    foreach ( (array) $runs as $run ) {
      $this->acdc_wf_reconcile_run( $run );
    }
  }

  /**
   * Recalcule l'intégralité d'un parcours à partir des pièces du dossier.
   */
  private function acdc_wf_reconcile_run( $run ) {
    global $wpdb;

    $run_id = (int) $run->id;
    $now    = $this->acdc_wf_now();

    $need = $wpdb->get_row( $wpdb->prepare(
      "SELECT * FROM {$this->need_table} WHERE id = %d",
      (int) $run->need_id
    ) );
    if ( ! $need ) {
      $this->acdc_wf_close_run( $run_id, 'cancelled', 'Recueil des besoins supprimé.' );
      return;
    }

    $pieces  = $this->acdc_wf_resolve_pieces( $run, $need );
    $phase   = 'commercial';

    /* ---- Commercial : proposition ------------------------------------- */
    if ( empty( $pieces['proposal'] ) ) {
      $this->acdc_wf_upsert_step( $run_id, 'proposal_create', array( 'scheduled_at' => $now ) );
    } else {
      $this->acdc_wf_settle_step( $run_id, 'proposal_create', 'done', 'Proposition n°' . (int) $pieces['proposal']->id );
    }

    /* ---- Commercial : devis -------------------------------------------- */
    if ( ! empty( $pieces['proposal'] ) && empty( $pieces['quote'] ) ) {
      $this->acdc_wf_upsert_step( $run_id, 'quote_create', array( 'scheduled_at' => $now ) );
    } elseif ( ! empty( $pieces['quote'] ) ) {
      $this->acdc_wf_settle_step( $run_id, 'quote_create', 'done', 'Devis n°' . (int) $pieces['quote']->id );
    }

    $quote_signed = false;
    if ( ! empty( $pieces['quote'] ) ) {
      $quote        = $pieces['quote'];
      $quote_signed = $this->acdc_wf_is_signed( $quote->status ?? '', $quote->signature_status ?? '' );
      $sent_ts      = ! empty( $quote->sent_at ) ? strtotime( (string) $quote->sent_at ) : 0;

      if ( $quote_signed ) {
        $this->acdc_wf_settle_step( $run_id, 'quote_reminder', 'skipped', 'Devis signé : relance sans objet.' );
        $this->acdc_wf_settle_step( $run_id, 'quote_rdv', 'skipped', 'Devis signé.' );
      } elseif ( $sent_ts > 0 ) {
        $due = $this->acdc_wf_shift_to_business_day(
          $sent_ts + ( (int) $this->acdc_wf_delay( 'quote_reminder_days', 10 ) * DAY_IN_SECONDS )
        );
        $this->acdc_wf_upsert_step( $run_id, 'quote_reminder', array( 'scheduled_at' => $due ) );
        $this->acdc_wf_plan_rdv_after_reminder( $run_id, 'quote_reminder', 'quote_rdv', 'quote_rdv_after_days', 10 );
      }
    }

    /* ---- Commercial : convention ---------------------------------------- */
    $convention_signed = false;
    if ( $quote_signed && empty( $pieces['contract'] ) ) {
      $this->acdc_wf_upsert_step( $run_id, 'convention_create', array( 'scheduled_at' => $now ) );
    } elseif ( ! empty( $pieces['contract'] ) ) {
      $contract = $pieces['contract'];
      $this->acdc_wf_settle_step( $run_id, 'convention_create', 'done', 'Convention n°' . (int) $contract->id );

      $convention_signed = $this->acdc_wf_is_signed( '', $contract->signature_status ?? '' )
        || ! empty( $contract->signature_completed_at );
      $c_sent_ts = ! empty( $contract->signature_sent_at ) ? strtotime( (string) $contract->signature_sent_at ) : 0;

      if ( $convention_signed ) {
        $this->acdc_wf_settle_step( $run_id, 'convention_reminder', 'skipped', 'Convention signée : relance sans objet.' );
        $this->acdc_wf_settle_step( $run_id, 'convention_rdv', 'skipped', 'Convention signée.' );
      } elseif ( $c_sent_ts > 0 ) {
        $due = $this->acdc_wf_shift_to_business_day(
          $c_sent_ts + ( (int) $this->acdc_wf_delay( 'convention_reminder_days', 3 ) * DAY_IN_SECONDS )
        );
        $this->acdc_wf_upsert_step( $run_id, 'convention_reminder', array( 'scheduled_at' => $due ) );
        $this->acdc_wf_plan_rdv_after_reminder( $run_id, 'convention_reminder', 'convention_rdv', 'convention_rdv_after_days', 3 );
      }
    }

    /* ---- Préparation, animation, évaluation ------------------------------ */
    if ( $convention_signed ) {
      $phase = 'preparation';
      $this->acdc_wf_plan_preparation( $run_id, $pieces );

      $start_ts = $this->acdc_wf_run_start_ts( $pieces );
      $end_ts   = $this->acdc_wf_run_end_ts( $pieces );

      if ( $start_ts > 0 ) {
        $this->acdc_wf_plan_animation( $run_id, $pieces, $start_ts, $end_ts );
        if ( $now >= $start_ts ) {
          $phase = 'animation';
        }
      }
      if ( $end_ts > 0 ) {
        $this->acdc_wf_plan_evaluation( $run_id, $pieces, $end_ts );
        if ( $now >= $end_ts ) {
          $phase = 'evaluation';
        }
      }
    }

    $wpdb->update(
      $this->workflow_run_table,
      array(
        'proposal_id'        => ! empty( $pieces['proposal'] ) ? (int) $pieces['proposal']->id : null,
        'quote_id'           => ! empty( $pieces['quote'] ) ? (int) $pieces['quote']->id : null,
        'contract_id'        => ! empty( $pieces['contract'] ) ? (int) $pieces['contract']->id : null,
        'formation_id'       => (int) $pieces['formation_id'],
        'session_id'         => (int) $pieces['session_id'],
        'funder_id'          => (int) $pieces['funder_id'],
        'company_id'         => (int) $pieces['company_id'],
        'phase'              => $phase,
        'last_reconciled_at' => $this->acdc_wf_mysql( $now ),
        'updated_at'         => $this->acdc_wf_mysql( $now ),
      ),
      array( 'id' => $run_id )
    );
  }

  /**
   * Le rendez-vous ne se planifie qu'APRÈS une relance réellement partie.
   * Tant que la relance n'a pas eu lieu, proposer un rendez-vous n'aurait aucun
   * sens : on demanderait à David de rattraper une signature qu'on n'a pas
   * encore relancée.
   */
  private function acdc_wf_plan_rdv_after_reminder( $run_id, $reminder_key, $rdv_key, $delay_key, $fallback_days ) {
    global $wpdb;
    $reminder = $wpdb->get_row( $wpdb->prepare(
      "SELECT * FROM {$this->workflow_step_table}
        WHERE run_id = %d AND dedupe_key = %s LIMIT 1",
      (int) $run_id,
      $reminder_key
    ) );
    if ( ! $reminder || 'done' !== (string) $reminder->status || empty( $reminder->executed_at ) ) {
      return;
    }
    $due = strtotime( (string) $reminder->executed_at )
      + ( (int) $this->acdc_wf_delay( $delay_key, $fallback_days ) * DAY_IN_SECONDS );
    $this->acdc_wf_upsert_step( $run_id, $rdv_key, array(
      'scheduled_at' => $this->acdc_wf_shift_to_business_day( $due ),
    ) );
  }

  /* =====================================================================
   * Planification par phase
   * ===================================================================== */

  private function acdc_wf_plan_preparation( $run_id, $pieces ) {
    $now      = $this->acdc_wf_now();
    $start_ts = $this->acdc_wf_run_start_ts( $pieces );

    $this->acdc_wf_upsert_step( $run_id, 'registration', array( 'scheduled_at' => $now ) );

    /* Le délai d'envoi de l'analyse des besoins est saisi DANS la convention —
       c'est la règle du schéma. À défaut, le réglage général s'applique. */
    $nad_days = ! empty( $pieces['contract']->nad_delay_days )
      ? (int) $pieces['contract']->nad_delay_days
      : (int) $this->acdc_wf_delay( 'nad_days_before_start', 15 );

    $nad_ts = $start_ts > 0 ? ( $start_ts - ( $nad_days * DAY_IN_SECONDS ) ) : $now;
    $nad_ts = max( $nad_ts, $now );

    $pack_ts = $start_ts > 0
      ? max( $now, $start_ts - ( (int) $this->acdc_wf_delay( 'trainer_pack_days_before', 15 ) * DAY_IN_SECONDS ) )
      : $now;

    $this->acdc_wf_upsert_step( $run_id, 'trainer_pack', array( 'scheduled_at' => $pack_ts ) );

    foreach ( $pieces['learners'] as $learner ) {
      $this->acdc_wf_upsert_step( $run_id, 'learner_invite', array(
        'scheduled_at' => $now,
        'target_type'  => 'learner',
        'target_id'    => (int) $learner['id'],
        'target_label' => (string) $learner['name'],
        'dedupe_key'   => 'learner_invite:' . (int) $learner['id'],
      ) );
      $this->acdc_wf_upsert_step( $run_id, 'nad_send', array(
        'scheduled_at' => $nad_ts,
        'target_type'  => 'learner',
        'target_id'    => (int) $learner['id'],
        'target_label' => (string) $learner['name'],
        'dedupe_key'   => 'nad_send:' . (int) $learner['id'],
      ) );
    }

    /* Convocation : la veille du PREMIER jour, à 17 h. Une seule, même pour une
       formation de trois jours — David a été explicite. */
    if ( $start_ts > 0 ) {
      $hour = max( 0, min( 23, (int) $this->acdc_wf_delay( 'convocation_hour', 17 ) ) );
      $veille = strtotime( wp_date( 'Y-m-d', $start_ts - DAY_IN_SECONDS ) . sprintf( ' %02d:00:00', $hour ) );
      $this->acdc_wf_upsert_step( $run_id, 'convocation', array( 'scheduled_at' => $veille ) );
    }
  }

  /**
   * Un rappel d'émargement par demi-journée, trente minutes avant son début.
   * Les créneaux sont lus dans la séance ; à défaut d'horaires renseignés, on
   * retient 9 h et 14 h et on le DIT dans la note de l'étape — une hypothèse
   * silencieuse serait pire qu'une absence de rappel.
   */
  private function acdc_wf_plan_animation( $run_id, $pieces, $start_ts, $end_ts ) {
    $lead  = (int) $this->acdc_wf_delay( 'emargement_lead_minutes', 30 ) * MINUTE_IN_SECONDS;
    $slots = $this->acdc_wf_session_slots( $pieces, $start_ts, $end_ts );

    foreach ( $slots as $slot ) {
      $step_key = ( 'pm' === $slot['half'] ) ? 'emargement_pm' : 'emargement_am';
      $this->acdc_wf_upsert_step( $run_id, $step_key, array(
        'scheduled_at' => $slot['ts'] - $lead,
        'target_type'  => 'trainer',
        'target_id'    => (int) $pieces['trainer_id'],
        'target_label' => (string) $pieces['trainer_name'],
        'dedupe_key'   => $step_key . ':' . wp_date( 'Y-m-d', $slot['ts'] ),
        'payload'      => array( 'assumed_hours' => ! empty( $slot['assumed'] ) ),
      ) );
    }
  }

  private function acdc_wf_plan_evaluation( $run_id, $pieces, $end_ts ) {
    $plan = array(
      'survey_hot' => array(
        'offset'    => (int) $this->acdc_wf_delay( 'survey_hot_offset_hours', 0 ) * HOUR_IN_SECONDS,
        'reminders' => $this->acdc_wf_delay( 'survey_hot_reminder_hours', array( 24, 48, 72 ) ),
        'unit'      => HOUR_IN_SECONDS,
      ),
      'survey_company' => array(
        'offset'    => (int) $this->acdc_wf_delay( 'survey_company_offset_hours', 24 ) * HOUR_IN_SECONDS,
        'reminders' => $this->acdc_wf_delay( 'survey_company_reminder_days', array( 3, 5, 7 ) ),
        'unit'      => DAY_IN_SECONDS,
      ),
      'survey_funder' => array(
        'offset'    => (int) $this->acdc_wf_delay( 'survey_funder_offset_hours', 24 ) * HOUR_IN_SECONDS,
        'reminders' => $this->acdc_wf_delay( 'survey_funder_reminder_days', array( 3, 5, 7 ) ),
        'unit'      => DAY_IN_SECONDS,
      ),
      'survey_trainer' => array(
        'offset'    => (int) $this->acdc_wf_delay( 'survey_trainer_offset_hours', 24 ) * HOUR_IN_SECONDS,
        'reminders' => $this->acdc_wf_delay( 'survey_trainer_reminder_days', array( 3, 5, 7 ) ),
        'unit'      => DAY_IN_SECONDS,
      ),
      /* L'enquête à froid n'est pas dans le schéma ; David a demandé qu'elle y
         entre avec ses propres relances. Elle reste obligatoire au titre de
         l'indicateur 11. */
      'survey_cold' => array(
        'offset'    => (int) $this->acdc_wf_delay( 'survey_cold_offset_days', 90 ) * DAY_IN_SECONDS,
        'reminders' => $this->acdc_wf_delay( 'survey_cold_reminder_days', array( 3, 5, 7 ) ),
        'unit'      => DAY_IN_SECONDS,
      ),
    );

    foreach ( $plan as $step_key => $spec ) {
      /* Pas de financeur au dossier : la branche entière sort du parcours. */
      if ( 'survey_funder' === $step_key && empty( $pieces['funder_id'] ) ) {
        $this->acdc_wf_settle_step( $run_id, 'survey_funder', 'skipped', 'Aucun financeur rattaché au dossier.' );
        for ( $r = 1; $r <= 3; $r++ ) {
          $this->acdc_wf_settle_step( $run_id, 'survey_funder_r' . $r, 'skipped', 'Aucun financeur rattaché au dossier.' );
        }
        continue;
      }

      $send_ts = $end_ts + (int) $spec['offset'];
      $this->acdc_wf_upsert_step( $run_id, $step_key, array( 'scheduled_at' => $send_ts ) );

      /* Relances CUMULATIVES : chaque délai part de la relance précédente.
         Pour l'entreprise, 3 / 5 / 7 donne donc J+3, J+8, J+15 — c'est la
         lecture que David a explicitement retenue. */
      $cursor    = $send_ts;
      $reminders = is_array( $spec['reminders'] ) ? array_values( $spec['reminders'] ) : array();
      foreach ( $reminders as $index => $delay ) {
        $rank    = $index + 1;
        $cursor += (int) $delay * (int) $spec['unit'];
        $this->acdc_wf_upsert_step( $run_id, $step_key . '_r' . $rank, array(
          'scheduled_at' => $this->acdc_wf_shift_to_business_day( $cursor ),
          'parent_key'   => $step_key,
        ) );
      }
    }
  }

  /* =====================================================================
   * Résolution des pièces du dossier
   * ===================================================================== */

  private function acdc_wf_resolve_pieces( $run, $need ) {
    global $wpdb;

    $prospect_id = (int) $need->source_prospect_id;
    $company_id  = (int) $need->company_id;

    $proposal_table = $wpdb->prefix . 'acdc_of_proposals';
    $proposal = $wpdb->get_row( $wpdb->prepare(
      "SELECT * FROM {$proposal_table} WHERE need_id = %d ORDER BY id DESC LIMIT 1",
      (int) $need->id
    ) );

    $quote = null;
    if ( $proposal ) {
      $quote = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$this->quote_table} WHERE proposal_id = %d ORDER BY id DESC LIMIT 1",
        (int) $proposal->id
      ) );
    }
    if ( ! $quote && $prospect_id > 0 ) {
      $quote = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$this->quote_table} WHERE source_prospect_id = %d ORDER BY id DESC LIMIT 1",
        $prospect_id
      ) );
    }

    $contract = null;
    if ( $prospect_id > 0 ) {
      $contract = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$this->registration_contract_table} WHERE source_prospect_id = %d ORDER BY id DESC LIMIT 1",
        $prospect_id
      ) );
    }
    if ( ! $contract && $company_id > 0 && $quote && ! empty( $quote->formation_id ) ) {
      $contract = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$this->registration_contract_table}
          WHERE company_id = %d AND formation_id = %d ORDER BY id DESC LIMIT 1",
        $company_id,
        (int) $quote->formation_id
      ) );
    }

    $formation_id = 0;
    if ( $contract && ! empty( $contract->formation_id ) ) {
      $formation_id = (int) $contract->formation_id;
    } elseif ( $quote && ! empty( $quote->formation_id ) ) {
      $formation_id = (int) $quote->formation_id;
    }
    if ( $contract && ! empty( $contract->company_id ) ) {
      $company_id = (int) $contract->company_id;
    }

    $session = null;
    if ( $formation_id > 0 ) {
      $session = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$this->session_table}
          WHERE formation_id = %d AND is_draft = 0
            AND COALESCE(status,'') NOT IN ('Annulée','Annulee')
            AND ( %d = 0 OR company_id = %d )
          ORDER BY COALESCE(start_date, DATE(start_at)) ASC, id ASC
          LIMIT 1",
        $formation_id,
        $company_id,
        $company_id
      ) );
    }

    return array(
      'need'         => $need,
      'proposal'     => $proposal,
      'quote'        => $quote,
      'contract'     => $contract,
      'session'      => $session,
      'session_id'   => $session ? (int) $session->id : 0,
      'formation_id' => $formation_id,
      'company_id'   => $company_id,
      'prospect_id'  => $prospect_id,
      'funder_id'    => $this->acdc_wf_resolve_funder_id( $contract, $company_id ),
      'trainer_id'   => $session && ! empty( $session->trainer_id ) ? (int) $session->trainer_id : 0,
      'trainer_name' => $this->acdc_wf_trainer_name( $session ),
      'learners'     => $this->acdc_wf_contract_learners( $contract ),
    );
  }

  private function acdc_wf_resolve_funder_id( $contract, $company_id ) {
    global $wpdb;
    if ( $contract && ! empty( $contract->funder_id ) ) {
      return (int) $contract->funder_id;
    }
    if ( $company_id > 0 && $this->acdc_schema_has_column( $this->company_table, 'funder_id' ) ) {
      return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT funder_id FROM {$this->company_table} WHERE id = %d",
        (int) $company_id
      ) );
    }
    return 0;
  }

  private function acdc_wf_trainer_name( $session ) {
    global $wpdb;
    if ( ! $session || empty( $session->trainer_id ) ) {
      return '';
    }
    $row = $wpdb->get_row( $wpdb->prepare(
      "SELECT first_name, last_name FROM {$this->trainer_table} WHERE id = %d",
      (int) $session->trainer_id
    ) );
    return $row ? trim( (string) $row->first_name . ' ' . (string) $row->last_name ) : '';
  }

  /**
   * Les apprenants NOMMÉS dans la convention : c'est eux, et eux seuls, que le
   * parcours inscrit et sollicite. La colonne est un JSON d'identifiants.
   */
  private function acdc_wf_contract_learners( $contract ) {
    global $wpdb;
    if ( ! $contract || empty( $contract->learner_ids ) ) {
      return array();
    }
    $ids = json_decode( (string) $contract->learner_ids, true );
    if ( ! is_array( $ids ) ) {
      $ids = array_map( 'trim', explode( ',', (string) $contract->learner_ids ) );
    }
    $ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
    if ( empty( $ids ) ) {
      return array();
    }
    $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
    $rows = $wpdb->get_results( $wpdb->prepare(
      "SELECT id, first_name, last_name, usage_last_name, email
         FROM {$this->learner_table} WHERE id IN ({$placeholders})",
      $ids
    ) );

    $learners = array();
    foreach ( (array) $rows as $row ) {
      $last = ! empty( $row->usage_last_name ) ? $row->usage_last_name : $row->last_name;
      $learners[] = array(
        'id'    => (int) $row->id,
        'name'  => trim( (string) $row->first_name . ' ' . (string) $last ),
        'email' => (string) $row->email,
      );
    }
    return $learners;
  }

  /* =====================================================================
   * Dates
   * ===================================================================== */

  private function acdc_wf_run_start_ts( $pieces ) {
    if ( ! empty( $pieces['session'] ) ) {
      $s = $pieces['session'];
      if ( ! empty( $s->start_at ) ) {
        return (int) strtotime( (string) $s->start_at );
      }
      if ( ! empty( $s->start_date ) ) {
        return (int) strtotime( (string) $s->start_date . ' 09:00:00' );
      }
    }
    if ( ! empty( $pieces['contract'] ) && ! empty( $pieces['contract']->start_date ) ) {
      return (int) strtotime( (string) $pieces['contract']->start_date . ' 09:00:00' );
    }
    return 0;
  }

  private function acdc_wf_run_end_ts( $pieces ) {
    if ( ! empty( $pieces['session'] ) ) {
      $s = $pieces['session'];
      if ( ! empty( $s->end_at ) ) {
        return (int) strtotime( (string) $s->end_at );
      }
      if ( ! empty( $s->end_date ) ) {
        return (int) strtotime( (string) $s->end_date . ' 17:00:00' );
      }
    }
    if ( ! empty( $pieces['contract'] ) && ! empty( $pieces['contract']->end_date ) ) {
      return (int) strtotime( (string) $pieces['contract']->end_date . ' 17:00:00' );
    }
    return 0;
  }

  /**
   * Les demi-journées de la formation. On lit d'abord le planning détaillé de la
   * séance ; sinon on couvre chaque jour ouvré de la période avec deux créneaux
   * présumés, marqués comme tels.
   */
  private function acdc_wf_session_slots( $pieces, $start_ts, $end_ts ) {
    $slots = array();

    if ( ! empty( $pieces['session']->schedule_json ) ) {
      $schedule = json_decode( (string) $pieces['session']->schedule_json, true );
      if ( is_array( $schedule ) ) {
        foreach ( $schedule as $entry ) {
          if ( ! is_array( $entry ) || empty( $entry['date'] ) ) {
            continue;
          }
          foreach ( array( 'am' => array( 'start_am', 'morning_start' ), 'pm' => array( 'start_pm', 'afternoon_start' ) ) as $half => $keys ) {
            foreach ( $keys as $key ) {
              if ( ! empty( $entry[ $key ] ) ) {
                $ts = strtotime( (string) $entry['date'] . ' ' . (string) $entry[ $key ] );
                if ( $ts ) {
                  $slots[] = array( 'ts' => $ts, 'half' => $half, 'assumed' => false );
                }
                break;
              }
            }
          }
        }
      }
    }

    if ( ! empty( $slots ) ) {
      return $slots;
    }

    if ( $start_ts <= 0 ) {
      return array();
    }
    $last  = $end_ts > 0 ? $end_ts : $start_ts;
    $day   = strtotime( wp_date( 'Y-m-d', $start_ts ) );
    $guard = 0;
    while ( $day <= $last && $guard < 60 ) {
      $date = wp_date( 'Y-m-d', $day );
      $slots[] = array( 'ts' => strtotime( $date . ' 09:00:00' ), 'half' => 'am', 'assumed' => true );
      $slots[] = array( 'ts' => strtotime( $date . ' 14:00:00' ), 'half' => 'pm', 'assumed' => true );
      $day += DAY_IN_SECONDS;
      $guard++;
    }
    return $slots;
  }

  private function acdc_wf_is_signed( $status, $signature_status ) {
    return 'signe' === (string) $status || 'signe' === (string) $signature_status;
  }

  /* =====================================================================
   * Écriture des étapes
   * ===================================================================== */

  /**
   * Pose une étape si elle n'existe pas, met à jour sa date si elle est encore
   * à venir, et ne touche JAMAIS une étape déjà jouée. C'est cette dernière
   * règle qui autorise la réconciliation à tourner en boucle sans jamais
   * réémettre un envoi.
   */
  private function acdc_wf_upsert_step( $run_id, $step_key, $args = array() ) {
    global $wpdb;

    $config = $this->acdc_wf_step_config( $step_key );
    if ( empty( $config ) ) {
      return;
    }

    $dedupe = isset( $args['dedupe_key'] ) ? (string) $args['dedupe_key'] : (string) $step_key;
    $now    = $this->acdc_wf_mysql( $this->acdc_wf_now() );

    $existing = $wpdb->get_row( $wpdb->prepare(
      "SELECT * FROM {$this->workflow_step_table} WHERE run_id = %d AND dedupe_key = %s LIMIT 1",
      (int) $run_id,
      $dedupe
    ) );

    $scheduled = isset( $args['scheduled_at'] ) && $args['scheduled_at']
      ? $this->acdc_wf_mysql( (int) $args['scheduled_at'] )
      : null;

    if ( $existing ) {
      if ( in_array( (string) $existing->status, array( 'done', 'failed', 'skipped', 'cancelled' ), true ) ) {
        return;
      }
      $wpdb->update(
        $this->workflow_step_table,
        array(
          'scheduled_at' => $scheduled,
          'status'       => null === $scheduled ? 'waiting' : 'pending',
          'label'        => (string) $config['label'],
          'updated_at'   => $now,
        ),
        array( 'id' => (int) $existing->id )
      );
      return;
    }

    $wpdb->insert( $this->workflow_step_table, array(
      'run_id'       => (int) $run_id,
      'step_key'     => (string) $step_key,
      'dedupe_key'   => $dedupe,
      'parent_key'   => isset( $args['parent_key'] ) ? (string) $args['parent_key'] : ( isset( $config['parent_key'] ) ? (string) $config['parent_key'] : '' ),
      'phase'        => (string) $config['phase'],
      'mode'         => (string) $config['mode'],
      'label'        => (string) $config['label'],
      'target_type'  => isset( $args['target_type'] ) ? (string) $args['target_type'] : '',
      'target_id'    => isset( $args['target_id'] ) ? (int) $args['target_id'] : null,
      'target_label' => isset( $args['target_label'] ) ? (string) $args['target_label'] : '',
      'scheduled_at' => $scheduled,
      'status'       => null === $scheduled ? 'waiting' : 'pending',
      'is_alert'     => ( 'alert' === (string) $config['mode'] ) ? 1 : 0,
      'payload_json' => isset( $args['payload'] ) ? wp_json_encode( $args['payload'] ) : null,
      'created_at'   => $now,
      'updated_at'   => $now,
    ) );
  }

  /** Clôt une étape encore ouverte. Une étape déjà jouée n'est jamais réécrite. */
  private function acdc_wf_settle_step( $run_id, $dedupe_key, $status, $note = '' ) {
    global $wpdb;
    $now = $this->acdc_wf_mysql( $this->acdc_wf_now() );
    $wpdb->query( $wpdb->prepare(
      "UPDATE {$this->workflow_step_table}
          SET status = %s, result_note = %s, executed_at = COALESCE(executed_at, %s), updated_at = %s
        WHERE run_id = %d AND dedupe_key = %s AND status IN ('pending','waiting')",
      (string) $status,
      (string) $note,
      $now,
      $now,
      (int) $run_id,
      (string) $dedupe_key
    ) );
  }

  private function acdc_wf_close_run( $run_id, $status, $reason ) {
    global $wpdb;
    $now = $this->acdc_wf_mysql( $this->acdc_wf_now() );
    $wpdb->update(
      $this->workflow_run_table,
      array( 'status' => (string) $status, 'close_reason' => (string) $reason, 'closed_at' => $now, 'updated_at' => $now ),
      array( 'id' => (int) $run_id )
    );
    $wpdb->query( $wpdb->prepare(
      "UPDATE {$this->workflow_step_table}
          SET status = 'cancelled', result_note = %s, updated_at = %s
        WHERE run_id = %d AND status IN ('pending','waiting')",
      (string) $reason,
      $now,
      (int) $run_id
    ) );
  }

  /* =====================================================================
   * Exécution
   * ===================================================================== */

  private function acdc_wf_execute_due_steps( $batch = 30 ) {
    global $wpdb;

    $now  = $this->acdc_wf_mysql( $this->acdc_wf_now() );
    $due  = $wpdb->get_results( $wpdb->prepare(
      "SELECT s.* FROM {$this->workflow_step_table} s
         INNER JOIN {$this->workflow_run_table} r ON r.id = s.run_id
        WHERE s.status = 'pending' AND s.mode = 'auto'
          AND s.scheduled_at IS NOT NULL AND s.scheduled_at <= %s
          AND r.status = 'active'
        ORDER BY s.scheduled_at ASC
        LIMIT %d",
      $now,
      (int) $batch
    ) );

    foreach ( (array) $due as $step ) {
      $this->acdc_wf_execute_step( $step );
    }
  }

  /**
   * Joue une étape.
   *
   * En mode simulation — l'état de départ — rien ne part : l'étape est marquée
   * faite avec la mention de ce qui AURAIT été envoyé. C'est le seul moyen de
   * regarder un parcours complet, daté, avant de laisser partir un seul e-mail.
   */
  private function acdc_wf_execute_step( $step ) {
    global $wpdb;

    $now      = $this->acdc_wf_mysql( $this->acdc_wf_now() );
    $handler  = 'acdc_wf_handle_' . (string) $step->step_key;
    $target   = (string) $step->target_label;
    $suffix   = '' !== $target ? ' — ' . $target : '';

    if ( $this->acdc_wf_is_simulation() ) {
      $wpdb->update(
        $this->workflow_step_table,
        array(
          'status'      => 'done',
          'executed_at' => $now,
          'result_note' => 'Simulation : aucun envoi. Action prévue « ' . (string) $step->label . ' »' . $suffix . '.',
          'attempts'    => (int) $step->attempts + 1,
          'updated_at'  => $now,
        ),
        array( 'id' => (int) $step->id )
      );
      return;
    }

    if ( ! method_exists( $this, $handler ) ) {
      $wpdb->update(
        $this->workflow_step_table,
        array(
          'status'      => 'failed',
          'last_error'  => 'Aucun traitement branché pour cette étape (' . (string) $step->step_key . ').',
          'attempts'    => (int) $step->attempts + 1,
          'updated_at'  => $now,
        ),
        array( 'id' => (int) $step->id )
      );
      return;
    }

    try {
      $result = $this->{$handler}( $step );
      $ok     = ! empty( $result['success'] );
      $wpdb->update(
        $this->workflow_step_table,
        array(
          'status'      => $ok ? 'done' : 'failed',
          'executed_at' => $ok ? $now : null,
          'result_note' => isset( $result['note'] ) ? (string) $result['note'] : '',
          'last_error'  => $ok ? '' : ( isset( $result['error'] ) ? (string) $result['error'] : 'Échec non détaillé.' ),
          'attempts'    => (int) $step->attempts + 1,
          'updated_at'  => $now,
        ),
        array( 'id' => (int) $step->id )
      );
    } catch ( Throwable $e ) {
      $wpdb->update(
        $this->workflow_step_table,
        array(
          'status'     => 'failed',
          'last_error' => $e->getMessage(),
          'attempts'   => (int) $step->attempts + 1,
          'updated_at' => $now,
        ),
        array( 'id' => (int) $step->id )
      );
    }
  }
}
