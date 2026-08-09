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
    $dates   = $this->acdc_wf_resolve_dates( $pieces );
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
      $sent_ts      = $this->acdc_wf_ts( $quote->sent_at ?? '' );

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
      $c_sent_ts = $this->acdc_wf_ts( $contract->signature_sent_at ?? '' );

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
        'formation_start_at' => $dates['start'] > 0 ? $this->acdc_wf_mysql( $dates['start'] ) : null,
        'formation_end_at'   => $dates['end'] > 0 ? $this->acdc_wf_mysql( $dates['end'] ) : null,
        'dates_source'       => (string) $dates['source'],
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
    /* Une relance simulée compte comme jouée : le plan doit continuer à se
       dérouler en simulation, sinon on ne verrait jamais la suite du parcours. */
    if ( ! $reminder || ! in_array( (string) $reminder->status, array( 'done', 'simulated' ), true ) || empty( $reminder->executed_at ) ) {
      return;
    }
    $due = $this->acdc_wf_ts( $reminder->executed_at )
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
      $veille = $this->acdc_wf_local_ts( wp_date( 'Y-m-d', $start_ts - DAY_IN_SECONDS ), sprintf( '%02d:00:00', $hour ) );
      $this->acdc_wf_upsert_step( $run_id, 'convocation', array(
        'scheduled_at' => $veille,
        'target_type'  => 'learners',
        'target_label' => $this->acdc_wf_learners_label( $pieces['learners'] ),
      ) );
    }
  }

  /**
   * Un rappel d'émargement par demi-journée, trente minutes avant son début.
   *
   * ACDC 3.25.186 — Le moteur n'invente plus les horaires. La 3.25.185, faute de
   * planning détaillé, balayait toute la plage calendaire du début à la fin en
   * posant deux créneaux par jour, week-ends compris : 120 rappels sur deux mois
   * pour une seule séance, et le garde-fou de 60 jours comme unique limite. Un
   * plan faux et volumineux est pire qu'un plan absent — il noie les vraies
   * lignes et personne ne les relit.
   *
   * Désormais : soit la séance porte ses demi-journées et on les suit, soit elle
   * n'en porte pas et le moteur pose UNE alerte disant qu'il ne peut pas
   * planifier. La donnée manquante se corrige ; une supposition, non.
   */
  private function acdc_wf_plan_animation( $run_id, $pieces, $start_ts, $end_ts ) {
    $lead  = (int) $this->acdc_wf_delay( 'emargement_lead_minutes', 30 ) * MINUTE_IN_SECONDS;
    $slots = $this->acdc_wf_session_slots( $pieces );

    if ( empty( $slots ) ) {
      $this->acdc_wf_upsert_step( $run_id, 'session_hours_missing', array(
        'scheduled_at' => $this->acdc_wf_now(),
        'target_type'  => 'session',
        'target_id'    => (int) $pieces['session_id'],
        'target_label' => $pieces['session_id'] ? 'Séance n°' . (int) $pieces['session_id'] : 'Aucune séance rattachée',
      ) );
      return;
    }

    $this->acdc_wf_settle_step( $run_id, 'session_hours_missing', 'skipped', 'Les demi-journées de la séance sont renseignées.' );

    foreach ( $slots as $slot ) {
      $step_key = ( 'pm' === $slot['half'] ) ? 'emargement_pm' : 'emargement_am';
      $this->acdc_wf_upsert_step( $run_id, $step_key, array(
        'scheduled_at' => $slot['ts'] - $lead,
        'target_type'  => 'trainer',
        'target_id'    => (int) $pieces['trainer_id'],
        'target_label' => '' !== (string) $pieces['trainer_name'] ? (string) $pieces['trainer_name'] : 'Formateur non rattaché',
        'dedupe_key'   => $step_key . ':' . wp_date( 'Y-m-d', $slot['ts'] ),
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

    $targets = array(
      'survey_hot'     => array( 'learners', $this->acdc_wf_learners_label( $pieces['learners'] ) ),
      'survey_cold'    => array( 'learners', $this->acdc_wf_learners_label( $pieces['learners'] ) ),
      'survey_company' => array( 'company', '' !== (string) $pieces['company_name'] ? (string) $pieces['company_name'] : 'Entreprise non rattachée' ),
      'survey_funder'  => array( 'funder', '' !== (string) $pieces['funder_name'] ? (string) $pieces['funder_name'] : 'Financeur non nommé' ),
      'survey_trainer' => array( 'trainer', '' !== (string) $pieces['trainer_name'] ? (string) $pieces['trainer_name'] : 'Formateur non rattaché' ),
    );

    foreach ( $plan as $step_key => $spec ) {
      $target = $targets[ $step_key ];

      /* Pas de financeur au dossier : la branche sort du parcours — mais elle
         doit le DIRE. En 3.25.185 elle disparaissait de l'écran, et rien ne
         distinguait plus « pas de financeur » d'un oubli du moteur. */
      if ( 'survey_funder' === $step_key && empty( $pieces['funder_id'] ) ) {
        $note = 'Aucun financeur rattaché au dossier.';
        $this->acdc_wf_mark_skipped( $run_id, 'survey_funder', $note );
        for ( $r = 1; $r <= 3; $r++ ) {
          $this->acdc_wf_mark_skipped( $run_id, 'survey_funder_r' . $r, $note );
        }
        continue;
      }

      $send_ts = $end_ts + (int) $spec['offset'];
      $this->acdc_wf_upsert_step( $run_id, $step_key, array(
        'scheduled_at' => $send_ts,
        'target_type'  => $target[0],
        'target_label' => $target[1],
      ) );

      /* Relances CUMULATIVES : chaque délai part de la relance précédente.
         Pour l'entreprise, 3 / 5 / 7 donne donc J+3, J+8, J+15 — c'est la
         lecture que David a explicitement retenue.
         Le CUMUL se calcule sur les dates théoriques, sans quoi chaque report de
         week-end décalerait toute la suite. Mais le report peut rapprocher deux
         relances au point de les coller : une relance repoussée au lundi suivie
         d'une autre le mardi n'est plus une relance, c'est du harcèlement. D'où
         l'écart minimal d'un jour ouvré, appliqué APRÈS report. */
      $cursor    = $send_ts;
      $previous  = 0;
      $reminders = is_array( $spec['reminders'] ) ? array_values( $spec['reminders'] ) : array();
      foreach ( $reminders as $index => $delay ) {
        $rank    = $index + 1;
        $cursor += (int) $delay * (int) $spec['unit'];
        $due     = $this->acdc_wf_shift_to_business_day( $cursor );
        if ( $previous > 0 && $due <= ( $previous + DAY_IN_SECONDS ) ) {
          $due = $this->acdc_wf_shift_to_business_day( $previous + DAY_IN_SECONDS );
        }
        $previous = $due;
        $this->acdc_wf_upsert_step( $run_id, $step_key . '_r' . $rank, array(
          'scheduled_at' => $due,
          'parent_key'   => $step_key,
          'target_type'  => $target[0],
          'target_label' => $target[1],
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

    $funder_id = $this->acdc_wf_resolve_funder_id( $contract, $company_id );

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
      'funder_id'    => $funder_id,
      'funder_name'  => $this->acdc_wf_entity_name( $this->funder_table, $funder_id ),
      'company_name' => $this->acdc_wf_entity_name( $this->company_table, $company_id ),
      'trainer_id'   => $session && ! empty( $session->trainer_id ) ? (int) $session->trainer_id : 0,
      'trainer_name' => $this->acdc_wf_trainer_name( $session ),
      'learners'     => $this->acdc_wf_contract_learners( $contract ),
    );
  }

  /** Le nom lisible d'une entreprise ou d'un financeur, pour la colonne Destinataire. */
  private function acdc_wf_entity_name( $table, $id ) {
    global $wpdb;
    $id = (int) $id;
    if ( $id <= 0 ) {
      return '';
    }
    $column = $this->acdc_schema_has_column( $table, 'name' ) ? 'name' : 'company_name';
    if ( ! $this->acdc_schema_has_column( $table, $column ) ) {
      return '';
    }
    return (string) $wpdb->get_var( $wpdb->prepare( "SELECT {$column} FROM {$table} WHERE id = %d", $id ) );
  }

  /**
   * ACDC 3.25.186 — Une convocation « à — » ne dit pas qui la reçoit.
   *
   * Les enquêtes et la convocation partent à un GROUPE, pas à une personne :
   * une seule étape, mais elle doit nommer sa cible. Au-delà de trois personnes
   * on compte plutôt que d'énumérer.
   */
  private function acdc_wf_learners_label( $learners ) {
    $names = array();
    foreach ( (array) $learners as $learner ) {
      if ( '' !== trim( (string) $learner['name'] ) ) {
        $names[] = (string) $learner['name'];
      }
    }
    if ( empty( $names ) ) {
      return 'Aucun apprenant nommé dans la convention';
    }
    if ( count( $names ) <= 3 ) {
      return implode( ', ', $names );
    }
    return count( $names ) . ' apprenants inscrits';
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
    $resolved = $this->acdc_wf_resolve_dates( $pieces );
    return (int) $resolved['start'];
  }

  /**
   * ACDC 3.25.186 — Les dates de formation retenues, ET leur provenance.
   *
   * L'agent de recette a buté sur une « fin de formation » au 05/08 pour une
   * séance commençant le 13/05 et sans date de fin : le moteur était retombé sur
   * la date de fin de la convention, ce qui est légitime mais invisible. Une
   * ancre qui décide de la date de cinq enquêtes doit dire d'où elle vient.
   */
  private function acdc_wf_resolve_dates( $pieces ) {
    $start        = 0;
    $end          = 0;
    $start_source = '';
    $end_source   = '';

    $session = $pieces['session'] ?? null;
    if ( $session ) {
      if ( ! empty( $session->start_at ) ) {
        $start        = $this->acdc_wf_ts( $session->start_at );
        $start_source = 'horaire de la séance n°' . (int) $session->id;
      } elseif ( ! empty( $session->start_date ) ) {
        $start        = $this->acdc_wf_local_ts( $session->start_date, '09:00:00' );
        $start_source = 'date de la séance n°' . (int) $session->id;
      }
      if ( ! empty( $session->end_at ) ) {
        $end        = $this->acdc_wf_ts( $session->end_at );
        $end_source = 'horaire de fin de la séance n°' . (int) $session->id;
      } elseif ( ! empty( $session->end_date ) ) {
        $end        = $this->acdc_wf_local_ts( $session->end_date, '17:00:00' );
        $end_source = 'date de fin de la séance n°' . (int) $session->id;
      }
    }

    $contract = $pieces['contract'] ?? null;
    if ( $contract ) {
      if ( $start <= 0 && ! empty( $contract->start_date ) ) {
        $start        = $this->acdc_wf_local_ts( $contract->start_date, '09:00:00' );
        $start_source = 'date de début de la convention n°' . (int) $contract->id;
      }
      if ( $end <= 0 && ! empty( $contract->end_date ) ) {
        $end        = $this->acdc_wf_local_ts( $contract->end_date, '17:00:00' );
        $end_source = 'date de fin de la convention n°' . (int) $contract->id;
      }
    }

    $source = trim( ( '' !== $start_source ? 'Début : ' . $start_source . '.' : 'Début : non déterminé.' )
      . ' ' . ( '' !== $end_source ? 'Fin : ' . $end_source . '.' : 'Fin : non déterminée.' ) );

    return array( 'start' => $start, 'end' => $end, 'source' => $source );
  }

  private function acdc_wf_run_end_ts( $pieces ) {
    $resolved = $this->acdc_wf_resolve_dates( $pieces );
    return (int) $resolved['end'];
  }

  /**
   * Les demi-journées RÉELLES de la séance, ou rien.
   *
   * Deux sources, dans cet ordre : le planning détaillé de la séance, puis les
   * horaires de début et de fin quand ils portent une heure. En l'absence des
   * deux, on renvoie un tableau vide et l'appelant pose une alerte. Aucune
   * troisième source : deviner 9 h et 14 h sur soixante jours n'était pas une
   * approximation, c'était une invention.
   */
  private function acdc_wf_session_slots( $pieces ) {
    $slots = array();

    if ( ! empty( $pieces['session']->schedule_json ) ) {
      $schedule = json_decode( (string) $pieces['session']->schedule_json, true );
      if ( is_array( $schedule ) ) {
        foreach ( $schedule as $entry ) {
          if ( ! is_array( $entry ) || empty( $entry['date'] ) ) {
            continue;
          }
          $halves = array(
            'am' => array( 'start_am', 'morning_start', 'am_start', 'start_morning' ),
            'pm' => array( 'start_pm', 'afternoon_start', 'pm_start', 'start_afternoon' ),
          );
          foreach ( $halves as $half => $keys ) {
            foreach ( $keys as $key ) {
              if ( empty( $entry[ $key ] ) ) {
                continue;
              }
              $ts = $this->acdc_wf_local_ts( $entry['date'], (string) $entry[ $key ] );
              if ( $ts > 0 ) {
                $slots[] = array( 'ts' => $ts, 'half' => $half );
              }
              break;
            }
          }
        }
      }
    }

    if ( ! empty( $slots ) ) {
      /* Garde-fou de volume : un planning réel ne dépasse pas cet ordre de
         grandeur. Au-delà, la donnée est suspecte et l'on préfère ne rien
         planifier plutôt que d'inonder le parcours. */
      return count( $slots ) > 120 ? array() : $slots;
    }

    /* Séance d'un seul tenant portant de vraies heures : deux demi-journées si
       elle enjambe midi, une seule sinon. */
    $session = $pieces['session'] ?? null;
    if ( ! $session || empty( $session->start_at ) ) {
      return array();
    }
    $start = $this->acdc_wf_ts( $session->start_at );
    if ( $start <= 0 || '00:00:00' === wp_date( 'H:i:s', $start ) ) {
      return array();
    }
    $end  = ! empty( $session->end_at ) ? $this->acdc_wf_ts( $session->end_at ) : 0;
    $date = wp_date( 'Y-m-d', $start );

    $slots[] = array( 'ts' => $start, 'half' => (int) wp_date( 'G', $start ) < 12 ? 'am' : 'pm' );

    if ( $end > $start && wp_date( 'Y-m-d', $end ) === $date && (int) wp_date( 'G', $start ) < 12 && (int) wp_date( 'G', $end ) > 13 ) {
      $slots[] = array( 'ts' => $this->acdc_wf_local_ts( $date, '14:00:00' ), 'half' => 'pm' );
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
      if ( in_array( (string) $existing->status, $this->acdc_wf_settled_statuses(), true ) ) {
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

  /**
   * Pose une étape DÉJÀ classée « sans objet ». Contrairement à settle_step, qui
   * ne touche que des lignes existantes, celle-ci crée la ligne si elle manque :
   * une branche écartée doit se voir à l'écran, pas s'évaporer.
   */
  private function acdc_wf_mark_skipped( $run_id, $step_key, $note ) {
    $this->acdc_wf_upsert_step( $run_id, $step_key, array( 'scheduled_at' => 0 ) );
    $this->acdc_wf_settle_step( $run_id, $step_key, 'skipped', $note );
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
          'status'      => 'simulated',
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
