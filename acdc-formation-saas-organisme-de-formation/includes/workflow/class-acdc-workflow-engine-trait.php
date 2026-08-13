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

  /**
   * ACDC 3.25.195 — Les clés d'étapes affirmées pendant la réconciliation en
   * cours. Ce qui n'y figure pas et reste ouvert n'a plus lieu d'être.
   */
  private $acdc_wf_touched_keys = array();

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
   * ACDC 3.25.226 — UN DOSSIER COMPLET SANS AUCUN PARCOURS.
   *
   * La recette a produit le cas limite qu'aucun de nous n'avait vu : prospect,
   * proposition, devis signé, convention signée, trois inscriptions, quatre
   * séances — et pas un parcours ouvert, donc pas une convocation, pas une
   * enquête, pas un document de fin. Le moteur n'avait rien manqué : il n'avait
   * jamais été armé, parce que son unique déclencheur est le recueil des
   * besoins, et que ce parcours-là n'est pas passé par le recueil.
   *
   * Le choix de David reste intact — un prospect n'engage rien, un recueil oui.
   * Mais une CONVENTION SIGNÉE engage bien davantage qu'un recueil : c'est le
   * document par lequel l'organisme s'oblige. Qu'elle n'arme pas le moteur est
   * une faute de conception, pas une règle.
   *
   * On ouvre donc aussi sur convention signée, sans recueil, et seulement si
   * aucun parcours ne couvre déjà ce dossier — la réconciliation fait le reste.
   *
   * @param int $contract_id Convention signée.
   * @return int Identifiant du parcours, 0 si aucun.
   */
  public function acdc_wf_open_run_for_contract( $contract_id ) {
    global $wpdb;

    if ( ! $this->acdc_wf_is_enabled() ) {
      return 0;
    }

    $contract_id = (int) $contract_id;
    if ( $contract_id <= 0 ) {
      return 0;
    }

    $contract = $wpdb->get_row( $wpdb->prepare(
      "SELECT * FROM {$this->registration_contract_table} WHERE id = %d",
      $contract_id
    ) );
    if ( ! $contract ) {
      return 0;
    }

    /* Déjà couvert ? Deux lectures : un parcours pointant sur cette convention,
       ou un parcours du même prospect. Ouvrir un second parcours sur un dossier
       déjà piloté doublerait tous les envois. */
    $existing = $wpdb->get_var( $wpdb->prepare(
      "SELECT id FROM {$this->workflow_run_table}
        WHERE status = 'active' AND ( contract_id = %d OR ( prospect_id > 0 AND prospect_id = %d ) )
        LIMIT 1",
      $contract_id,
      (int) $contract->source_prospect_id
    ) );
    if ( $existing ) {
      return (int) $existing;
    }

    $label = trim( (string) ( $contract->title ?? '' ) );
    if ( '' === $label ) {
      $label = 'Convention n°' . $contract_id;
    }

    $now = $this->acdc_wf_mysql( $this->acdc_wf_now() );
    $wpdb->insert( $this->workflow_run_table, array(
      'need_id'      => null,
      'prospect_id'  => (int) $contract->source_prospect_id,
      'company_id'   => (int) $contract->company_id,
      'contract_id'  => $contract_id,
      'formation_id' => (int) $contract->formation_id,
      'label'        => $label,
      'phase'        => 'preparation',
      'status'       => 'active',
      'started_at'   => $now,
      'updated_at'   => $now,
    ) );

    $run_id = (int) $wpdb->insert_id;
    if ( $run_id > 0 ) {
      $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->workflow_run_table} WHERE id = %d", $run_id ) );
      if ( $row ) {
        $this->acdc_wf_reconcile_run( $row );
      }
    }

    return $run_id;
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
    /* Le lot est SÉLECTIONNÉ par ancienneté de réconciliation — c'est ce qui
       garantit que tous les parcours finissent par passer — mais il est TRAITÉ
       par identifiant croissant. Cet ordre n'est pas cosmétique : c'est lui qui
       fait qu'un parcours partageant sa séance avec un plus ancien voit toujours
       ce dernier avoir déjà inscrit la séance, et lui cède la main dès le
       premier passage plutôt qu'au suivant. Entre-temps, des envois auraient pu
       partir en double. */
    $runs = (array) $runs;
    usort( $runs, static function( $a, $b ) { return (int) $a->id <=> (int) $b->id; } );

    foreach ( $runs as $run ) {
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

    $need = ! empty( $run->need_id ) ? $wpdb->get_row( $wpdb->prepare(
      "SELECT * FROM {$this->need_table} WHERE id = %d",
      (int) $run->need_id
    ) ) : null;

    if ( ! $need ) {
      /* ACDC 3.25.226 — Un parcours peut naître d'une CONVENTION SIGNÉE, sans
         recueil des besoins. Fermer un tel parcours au motif que « le recueil a
         été supprimé » reviendrait à annuler, à la première réconciliation, le
         seul dossier qui avait de quoi s'armer. On ne ferme donc que si le
         parcours n'a plus AUCUNE ancre : ni recueil, ni convention. */
      if ( empty( $run->contract_id ) ) {
        $this->acdc_wf_close_run( $run_id, 'cancelled', 'Recueil des besoins supprimé.' );
        return;
      }
      /* Un objet minimal, pour que la résolution des pièces trouve les mêmes
         clés qu'avec un recueil. Ce qu'il ne sait pas, il le laisse vide — il
         ne l'invente pas. */
      $need = (object) array(
        'id'                 => 0,
        'source_prospect_id' => (int) $run->prospect_id,
        'company_id'         => (int) $run->company_id,
        'formation_id'       => (int) $run->formation_id,
        'dossier_id'         => (int) $run->contract_id,
        'theme'              => (string) $run->label,
      );
    }

    $this->acdc_wf_touched_keys = array();

    $pieces  = $this->acdc_wf_resolve_pieces( $run, $need );
    $dates   = $this->acdc_wf_resolve_dates( $pieces );
    $phase   = 'commercial';

    /* La séance est inscrite tout de suite, avant la moindre planification :
       c'est elle qui sert d'arbitre entre parcours concurrents. */
    if ( (int) $pieces['session_id'] > 0 && (int) $run->session_id !== (int) $pieces['session_id'] ) {
      $wpdb->update(
        $this->workflow_run_table,
        array( 'session_id' => (int) $pieces['session_id'] ),
        array( 'id' => $run_id )
      );
    }

    /* ACDC 3.25.187 — Une convention signée rend caduques les tâches amont.
       Quatre dossiers affichaient « Créer le devis — en retard » alors que leur
       formation était terminée depuis août : le parcours réclamait une pièce
       que la suite avait rendue inutile. Réclamer un devis après la formation
       n'est pas un rappel, c'est du bruit. */
    $engaged = ! empty( $pieces['contract'] )
      && ( $this->acdc_wf_is_signed( '', $pieces['contract']->signature_status ?? '' )
        || ! empty( $pieces['contract']->signature_completed_at ) );

    /* ---- Commercial : proposition ------------------------------------- */
    if ( $engaged ) {
      $this->acdc_wf_settle_step( $run_id, 'proposal_create', 'skipped', 'Dossier engagé : la convention est signée.' );
      $this->acdc_wf_settle_step( $run_id, 'quote_create', 'skipped', 'Dossier engagé : la convention est signée.' );
    }
    if ( empty( $pieces['proposal'] ) && ! $engaged ) {
      $this->acdc_wf_upsert_step( $run_id, 'proposal_create', array( 'scheduled_at' => $now ) );
    } else {
      $this->acdc_wf_settle_step( $run_id, 'proposal_create', 'done', 'Proposition n°' . (int) $pieces['proposal']->id );
    }

    /* ---- Commercial : devis -------------------------------------------- */
    if ( ! empty( $pieces['proposal'] ) && empty( $pieces['quote'] ) && ! $engaged ) {
      $this->acdc_wf_upsert_step( $run_id, 'quote_create', array( 'scheduled_at' => $now ) );
    } elseif ( ! empty( $pieces['quote'] ) ) {
      $this->acdc_wf_settle_step( $run_id, 'quote_create', 'done', 'Devis n°' . (int) $pieces['quote']->id );
    }

    $quote_signed = false;
    if ( ! empty( $pieces['quote'] ) ) {
      $quote        = $pieces['quote'];
      $quote_signed = $this->acdc_wf_is_signed( $quote->status ?? '', $quote->signature_status ?? '' );
      $sent_ts      = $this->acdc_wf_quote_sent_ts( $quote );

      if ( $quote_signed ) {
        $this->acdc_wf_settle_step( $run_id, 'quote_reminder', 'skipped', 'Devis signé : relance sans objet.' );
        $this->acdc_wf_settle_step( $run_id, 'quote_rdv', 'skipped', 'Devis signé.' );
      } elseif ( $sent_ts > 0 ) {
        $due = $this->acdc_wf_shift_to_business_day(
          $this->acdc_wf_add_days( $sent_ts, (int) $this->acdc_wf_delay( 'quote_reminder_days', 10 ) )
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
          $this->acdc_wf_add_days( $c_sent_ts, (int) $this->acdc_wf_delay( 'convention_reminder_days', 3 ) )
        );
        $this->acdc_wf_upsert_step( $run_id, 'convention_reminder', array( 'scheduled_at' => $due ) );
        $this->acdc_wf_plan_rdv_after_reminder( $run_id, 'convention_reminder', 'convention_rdv', 'convention_rdv_after_days', 3 );
      }
    }

    /* ACDC 3.25.187 — Une séance n'est pilotée que par UN parcours.
       La recette a mis en évidence quatre recueils pointant vers la même
       convention et la même séance : le moteur produisait quatre plans
       identiques, donc quatre enquêtes à chaud aux mêmes apprenants et quatre
       enquêtes entreprise au même commanditaire. Le moteur faisait ce qu'on lui
       demandait ; c'est la donnée qui piège. Un envoi en quadruple exemplaire
       n'est pas un désagrément d'affichage, c'est ce qui décrédibilise un
       organisme auprès de ses stagiaires. Le parcours le plus ancien garde la
       main, les autres le disent et s'arrêtent après la phase commerciale. */
    $owner_run_id = $this->acdc_wf_session_owner_run_id( $pieces['session_id'], $run_id );

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

    /* ACDC 3.25.201 — La neutralisation vient APRÈS la planification.
       En sautant purement et simplement la phase aval, le parcours non pilote
       n'écrivait plus ses cibles : ses étapes gardaient « Formateur non
       rattaché » et « Aucune séance rattachée » alors que l'en-tête du même
       écran, deux lignes plus haut, nommait la séance n°7 et son formateur. Le
       chemin d'exclusion perdait les entités portées par la séance.
       On planifie donc normalement — le plan reste juste et lisible — puis on
       neutralise ce qui ferait doublon, en le disant. */
    if ( $owner_run_id > 0 ) {
      $this->acdc_wf_release_downstream_steps( $run_id, $owner_run_id );
      $phase = 'commercial';
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

    $this->acdc_wf_sweep_orphan_steps( $run_id );
  }

  /**
   * ACDC 3.25.195 — Ce que la réconciliation n'a pas réaffirmé n'existe plus.
   *
   * Le plan doit refléter EXACTEMENT la lecture du dossier, sinon il n'est plus
   * un plan mais une sédimentation. Le cas s'est présenté dès le premier
   * changement de clé d'unicité : les rappels d'émargement, désormais indexés
   * par séance et par demi-journée au lieu de la date, ont laissé derrière eux
   * les six lignes de l'ancienne indexation. Douze rappels pour six
   * demi-journées, deux à deux à la même minute — donc, le jour de l'ouverture
   * du robinet, deux e-mails identiques au formateur.
   *
   * Le balayage ne touche que ce qui est encore à venir et que le moteur a lui
   * même posé : le passé est un fait, et un écartement humain est un arbitrage.
   */
  private function acdc_wf_sweep_orphan_steps( $run_id ) {
    global $wpdb;

    $open = $wpdb->get_results( $wpdb->prepare(
      "SELECT id, dedupe_key, executed_at FROM {$this->workflow_step_table}
        WHERE run_id = %d AND status IN ('pending','waiting') AND settled_by <> 'human'",
      (int) $run_id
    ) );

    if ( empty( $open ) ) {
      return;
    }

    $now = $this->acdc_wf_mysql( $this->acdc_wf_now() );
    foreach ( $open as $row ) {
      if ( isset( $this->acdc_wf_touched_keys[ (string) $row->dedupe_key ] ) ) {
        continue;
      }

      /* ACDC 3.25.205 — Une étape JAMAIS JOUÉE et sortie du plan est effacée,
         pas classée.
         La conserver « Annulée » créait deux torts. À l'écran, l'ancienne ligne
         « Rappel d'émargement au formateur » cohabitait à la minute près avec la
         nouvelle « Ouvrir la feuille d'émargement » — deux libellés pour la même
         action, l'un annulé, l'autre planifié : la recette en a conclu, très
         logiquement, que le formateur ne serait plus rappelé. Et au journal, ces
         lignes n'avaient pas de date, puisqu'elles n'avaient jamais été jouées :
         un journal d'audit ne peut pas porter d'entrée sans horodatage.
         Le journal doit dire ce que le moteur A FAIT. Une étape planifiée puis
         déplanifiée sans jamais partir n'appartient pas à cette histoire. */
      if ( empty( $row->executed_at ) ) {
        $wpdb->delete( $this->workflow_step_table, array( 'id' => (int) $row->id ) );
        continue;
      }

      $wpdb->update(
        $this->workflow_step_table,
        array(
          'status'      => 'cancelled',
          'result_note' => 'Étape sans objet dans le plan actuel du dossier.',
          'settled_by'  => 'engine',
          'updated_at'  => $now,
        ),
        array( 'id' => (int) $row->id )
      );
    }
  }

  /**
   * ACDC 3.25.187 — Le rendez-vous se montre dès que la relance est planifiée.
   *
   * La 3.25.185 ne le posait qu'une fois la relance réellement partie, au motif
   * qu'il n'y a rien à rattraper avant. Le raisonnement était juste et le
   * résultat mauvais : sur une convention envoyée et non signée, l'écran ne
   * montrait aucune suite, et c'est précisément le dossier qui se perd — celui
   * pour lequel David a demandé un rendez-vous ET une alerte. Un écran de suivi
   * sert à voir venir, pas à constater.
   *
   * L'échéance s'ancre donc sur la date PRÉVUE de la relance, puis se recale sur
   * sa date réelle une fois qu'elle est partie.
   */
  private function acdc_wf_plan_rdv_after_reminder( $run_id, $reminder_key, $rdv_key, $delay_key, $fallback_days ) {
    global $wpdb;
    $reminder = $wpdb->get_row( $wpdb->prepare(
      "SELECT * FROM {$this->workflow_step_table}
        WHERE run_id = %d AND dedupe_key = %s LIMIT 1",
      (int) $run_id,
      $reminder_key
    ) );
    if ( ! $reminder ) {
      return;
    }

    /* Une relance simulée compte comme jouée : le plan doit continuer à se
       dérouler en simulation, sinon on ne verrait jamais la suite du parcours. */
    $played = in_array( (string) $reminder->status, array( 'done', 'simulated' ), true ) && ! empty( $reminder->executed_at );
    $anchor = $played
      ? $this->acdc_wf_ts( $reminder->executed_at )
      : $this->acdc_wf_ts( $reminder->scheduled_at ?? '' );

    if ( $anchor <= 0 ) {
      return;
    }

    $due = $this->acdc_wf_add_days( $anchor, (int) $this->acdc_wf_delay( $delay_key, $fallback_days ) );
    $this->acdc_wf_upsert_step( $run_id, $rdv_key, array(
      'scheduled_at' => $this->acdc_wf_shift_to_business_day( $due ),
    ) );
  }

  /**
   * Le parcours qui pilote réellement une séance : le plus ancien de ceux qui
   * la partagent. Renvoie 0 lorsque le parcours courant est ce pilote — donc
   * qu'il doit dérouler la suite normalement.
   */
  private function acdc_wf_session_owner_run_id( $session_id, $run_id ) {
    global $wpdb;
    $session_id = (int) $session_id;
    if ( $session_id <= 0 ) {
      return 0;
    }
    $owner = (int) $wpdb->get_var( $wpdb->prepare(
      "SELECT MIN(id) FROM {$this->workflow_run_table}
        WHERE session_id = %d AND status = 'active'",
      $session_id
    ) );
    return ( $owner > 0 && $owner !== (int) $run_id ) ? $owner : 0;
  }

  /**
   * Le parcours n'est pas le pilote de sa séance : tout ce qui suit la phase
   * commerciale lui est retiré, et il le DIT. Un dossier muet laisserait croire
   * à un oubli du moteur ; un dossier qui affiche « séance pilotée par le
   * parcours n°X » se comprend d'un coup d'œil.
   */
  private function acdc_wf_release_downstream_steps( $run_id, $owner_run_id ) {
    global $wpdb;
    $note = 'Séance pilotée par le parcours n°' . (int) $owner_run_id . ' : pas de second envoi aux mêmes destinataires.';
    $now  = $this->acdc_wf_mysql( $this->acdc_wf_now() );

    /* ACDC 3.25.201 — La branche FINANCEUR échappe à la neutralisation.
       Le motif « pas de second envoi aux mêmes destinataires » est vrai pour les
       apprenants, le formateur et l'entreprise : ils sont portés par la séance,
       donc partagés. Il est faux pour le financeur, qui se déclare sur le
       RECUEIL : deux dossiers partageant une séance peuvent être financés par
       deux OPCO différents. La recette l'a montré — un dossier pilote sans
       financeur faisait supprimer deux enquêtes financeur bien distinctes.
       Un garde-fou qui supprime un envoi légitime coûte plus cher que le
       doublon qu'il évite. */
    $wpdb->query( $wpdb->prepare(
      "UPDATE {$this->workflow_step_table}
          SET status = 'skipped', result_note = %s, settled_by = 'engine',
              executed_at = COALESCE(executed_at, %s), updated_at = %s
        WHERE run_id = %d AND status IN ('pending','waiting')
          AND phase IN ('preparation','animation','evaluation')
          AND step_key NOT LIKE 'survey_funder%%'",
      $note,
      $now,
      $now,
      (int) $run_id
    ) );
  }

  /* =====================================================================
   * Planification par phase
   * ===================================================================== */

  private function acdc_wf_plan_preparation( $run_id, $pieces ) {
    global $wpdb;

    $now      = $this->acdc_wf_now();
    $start_ts = $this->acdc_wf_run_start_ts( $pieces );

    /* ACDC 3.25.228 — « INSCRIRE LES APPRENANTS » S'AFFICHAIT EN ÉCHEC
       ALORS QUE LES INSCRIPTIONS EXISTAIENT.
       Cette étape n'a jamais eu d'automatisation, et n'en aura pas : inscrire
       quelqu'un en formation est un acte de gestion, pas un envoi. Le moteur
       la déclarait pourtant « auto », tentait de la jouer, ne trouvait aucun
       gestionnaire, et la peignait en rouge — un état alarmant sur une étape
       déjà faite, sur un dossier qui allait bien.
       On la traite pour ce qu'elle est : un constat. Si les apprenants sont
       inscrits, elle est FAITE, et l'on dit combien. Sinon, elle reste à faire,
       entre les mains de l'organisme. */
    /* ACDC 3.25.253 — CETTE ÉTAPE COMPTAIT DES NOMS ET ANNONÇAIT DES DOSSIERS.
       `$pieces['learners']`, ce sont les apprenants NOMMÉS DANS LA CONVENTION.
       L'étape se peignait en vert — « 2 apprenant(s) inscrit(s) au dossier » —
       sans qu'aucun dossier n'existe. Verte, elle n'était jamais faite ; et
       comme la liste des inscrits, l'extranet apprenant et la barre de
       complétude pendent tous au dossier, ils restaient vides. La convocation,
       elle, partait : elle lit la convention, pas les dossiers. C'est ainsi que
       des apprenants ont reçu leur convocation sans figurer nulle part.

       On compte désormais les VRAIS dossiers. Et puisque la signature de la
       convention est l'engagement, une convention signée dont les dossiers
       manquent les fait créer ici — le moteur re-dérive à chaque passe, c'est sa
       règle : les conventions signées avant cette version se rattrapent seules. */
    $learner_count = is_array( $pieces['learners'] ?? null ) ? count( $pieces['learners'] ) : 0;
    $contract_wf   = $pieces['contract'] ?? null;

    /* La même lecture de « signée » que partout ailleurs dans ce moteur : le
       statut, ou l'horodatage de signature. */
    $contract_signed_wf = $contract_wf
      && ( $this->acdc_wf_is_signed( '', $contract_wf->signature_status ?? '' )
        || ! empty( $contract_wf->signature_completed_at ) );

    if ( $learner_count > 0 && $contract_signed_wf
      && method_exists( $this, 'acdc_enroll_learners_from_contract' ) ) {
      $this->acdc_enroll_learners_from_contract( $contract_wf );
      if ( method_exists( $this, 'acdc_create_sessions_from_contract' ) ) {
        $this->acdc_create_sessions_from_contract( $contract_wf );
      }
    }

    $enrolled_count = 0;
    if ( $contract_wf && ! empty( $contract_wf->id ) ) {
      $enrolled_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$this->training_registration_table}
          WHERE autofill_contract_id = %d AND is_draft = 0",
        (int) $contract_wf->id
      ) );
    }

    if ( $enrolled_count > 0 ) {
      $this->acdc_wf_settle_step(
        $run_id,
        'registration',
        'done',
        sprintf(
          '%d dossier(s) d\'inscription créé(s) pour %d apprenant(s) nommé(s) dans la convention.',
          $enrolled_count,
          $learner_count
        )
      );
    } else {
      $this->acdc_wf_upsert_step( $run_id, 'registration', array( 'scheduled_at' => $now ) );
      /* Les parcours ouverts par une version antérieure portent encore
         mode='auto' sur cette ligne : sans cette remise à niveau, ils
         continueraient d'échouer indéfiniment. */
      $wpdb->update(
        $this->workflow_step_table,
        array( 'mode' => 'task', 'is_alert' => 0 ),
        array( 'run_id' => (int) $run_id, 'step_key' => 'registration' )
      );
    }

    /* Le délai d'envoi de l'analyse des besoins est saisi DANS la convention —
       c'est la règle du schéma. À défaut, le réglage général s'applique. */
    $nad_days = ! empty( $pieces['contract']->nad_delay_days )
      ? (int) $pieces['contract']->nad_delay_days
      : (int) $this->acdc_wf_delay( 'nad_days_before_start', 15 );

    $nad_ts = $start_ts > 0 ? $this->acdc_wf_add_days( $start_ts, -$nad_days ) : $now;
    $nad_ts = max( $nad_ts, $now );

    $pack_ts = $start_ts > 0
      ? max( $now, $this->acdc_wf_add_days( $start_ts, -(int) $this->acdc_wf_delay( 'trainer_pack_days_before', 15 ) ) )
      : $now;

    $this->acdc_wf_upsert_step( $run_id, 'trainer_pack', array(
      'scheduled_at' => $pack_ts,
      'target_type'  => 'trainer',
      'target_id'    => (int) $pieces['trainer_id'],
      'target_label' => '' !== (string) $pieces['trainer_name'] ? (string) $pieces['trainer_name'] : 'Formateur non rattaché',
    ) );

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
      $veille = $this->acdc_wf_local_ts( wp_date( 'Y-m-d', $this->acdc_wf_add_days( $start_ts, -1 ) ), sprintf( '%02d:00:00', $hour ) );
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
      /* ACDC 3.25.200 — Demander les horaires d'une séance qui n'existe pas
         n'aide personne : quand aucune séance n'est rattachée, l'action à faire
         est de la rattacher. */
      $this->acdc_wf_upsert_step( $run_id, $pieces['session_id'] ? 'session_hours_missing' : 'session_missing', array(
        'scheduled_at' => $this->acdc_wf_now(),
        'target_type'  => 'session',
        'target_id'    => (int) $pieces['session_id'],
        'target_label' => $pieces['session_id'] ? 'Séance n°' . (int) $pieces['session_id'] : $this->acdc_wf_formation_title( $pieces ),
      ) );
      return;
    }

    $this->acdc_wf_settle_step( $run_id, 'session_hours_missing', 'skipped', 'Les demi-journées de la séance sont renseignées.' );
    $this->acdc_wf_settle_step( $run_id, 'session_missing', 'skipped', 'Une séance est rattachée au dossier.' );

    foreach ( $slots as $slot ) {
      $step_key = ( 'pm' === $slot['half'] ) ? 'emargement_pm' : 'emargement_am';
      $this->acdc_wf_upsert_step( $run_id, $step_key, array(
        'scheduled_at' => $slot['ts'] - $lead,
        'target_type'  => 'trainer',
        'target_id'    => (int) $pieces['trainer_id'],
        'target_label' => '' !== (string) $pieces['trainer_name'] ? (string) $pieces['trainer_name'] : 'Formateur non rattaché',
        /* La clé porte la séance ET la demi-journée : deux créneaux du même jour
           sur deux séances distinctes sont deux feuilles d'émargement. */
        'dedupe_key'   => $step_key . ':' . (int) $slot['session_id'] . ':' . (int) $slot['seance_index'],
        'payload'      => array(
          'session_id'   => (int) $slot['session_id'],
          'seance_index' => (int) $slot['seance_index'],
        ),
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
        'offset'      => 0,
        'offset_days' => (int) $this->acdc_wf_delay( 'survey_cold_offset_days', 90 ),
        'reminders'   => $this->acdc_wf_delay( 'survey_cold_reminder_days', array( 3, 5, 7 ) ),
        'unit'        => DAY_IN_SECONDS,
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
      if ( ! empty( $spec['offset_days'] ) ) {
        $send_ts = $this->acdc_wf_add_days( $send_ts, (int) $spec['offset_days'] );
      }
      /* ACDC 3.25.226 — LE PREMIER ENVOI D'ENQUÊTE N'ÉTAIT PAS REPORTÉ.
         Les RELANCES passaient bien par le report au jour ouvré, corrigé en
         3.25.207 après qu'une relance fut partie un samedi. L'ENVOI INITIAL,
         lui, ne l'a jamais été : il se pose à la fin de formation plus le
         décalage, et rien de plus. Une formation qui se termine le vendredi à
         17 h avec un décalage de 24 heures place donc l'enquête le SAMEDI —
         c'est exactement ce que la recette a relevé, trois envois le
         08/08/2026, entreprise, financeur et formateur.
         J'avais corrigé la moitié du chemin en croyant l'avoir corrigé en
         entier : la règle de David — « on décale au jour ouvré suivant » — ne
         distingue pas un premier envoi d'une relance. */
      $send_ts = $this->acdc_wf_shift_to_business_day( $send_ts );
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
        $rank   = $index + 1;
        $cursor = ( DAY_IN_SECONDS === (int) $spec['unit'] )
          ? $this->acdc_wf_add_days( $cursor, (int) $delay )
          : $cursor + ( (int) $delay * (int) $spec['unit'] );
        /* ACDC 3.25.207 — RETOUR EN ARRIÈRE ASSUMÉ sur la 3.25.205.
           J'avais retiré le report au jour ouvré des chaînes horaires pour
           supprimer un effet de bord cosmétique : deux relances séparées d'une
           heure après avoir été rassemblées sur le lundi. La recette a mesuré le
           coût de ce raccourci — une enquête à chaud relancée le SAMEDI. J'avais
           payé une règle explicite de David (« on décale au jour ouvré suivant »,
           sans distinguer les heures des jours) pour régler une gêne d'affichage.
           La bonne cible était la collision, pas le report ; elle est traitée
           quelques lignes plus bas. */
        $due    = $this->acdc_wf_shift_to_business_day( $cursor );
        /* ACDC 3.25.196 — L'écart minimal se mesure dans l'UNITÉ de la chaîne.
           Deux relances ne doivent jamais tomber à la même minute : quand le
           report au jour ouvré de la précédente vient occuper l'horodatage de
           celle-ci, il faut les séparer. Mais séparer d'un JOUR une chaîne
           réglée en HEURES — l'enquête à chaud, 24 puis 48 puis 72 — déplace
           l'échéance bien au-delà de ce que le réglage promet. On sépare donc
           d'une heure une chaîne horaire, et d'un jour ouvré une chaîne
           journalière. L'écart reste minimal dans les deux cas ; c'est la
           promesse du réglage qui est préservée. */
        $spaced   = false;
        $min_next = $this->acdc_wf_add_days( $previous, 1 );

        if ( $previous > 0 && $due <= $min_next ) {
          /* ACDC 3.25.207 — Deux relances rassemblées par le report se séparent
             d'un JOUR OUVRÉ, y compris sur une chaîne horaire.
             La 3.25.196 les séparait d'une heure pour ne pas trahir un réglage
             exprimé en heures. L'intention était juste, le résultat ne l'était
             pas : deux messages à soixante minutes d'intervalle ne relancent
             personne, ils agacent. Et le cas ne se produit JAMAIS en semaine —
             il naît uniquement du report du week-end, qui vient d'empiler deux
             échéances sur le même lundi. Les écarter d'un jour ouvré rend à la
             chaîne la cadence que le réglage promettait, sans jamais reculer une
             relance avant sa date théorique. */
          $due    = $this->acdc_wf_shift_to_business_day( $min_next );
          $spaced = true;
        }
        $previous = $due;
        $this->acdc_wf_upsert_step( $run_id, $step_key . '_r' . $rank, array(
          'scheduled_at' => $due,
          'parent_key'   => $step_key,
          'target_type'  => $target[0],
          'target_label' => $target[1],
          /* Le report au jour ouvré de la relance précédente a pu rapprocher
             celle-ci au point de la coller ; on l'a repoussée d'un jour ouvré.
             L'écart avec le délai nominal doit se lire, pas se deviner. */
          'payload'      => $spaced ? array( 'spaced_after_shift' => true ) : null,
        ) );
      }

      /* ACDC 3.25.187 — Une relance retirée de la configuration doit disparaître
         du plan. La 3.25.185 n'ajoutait que les étapes manquantes : vider le
         champ des relances laissait les anciennes en place, et le réglage
         semblait sans effet. Le moteur reprend maintenant ce qu'il a posé —
         seulement ce qui est encore à venir, jamais ce qui est derrière nous. */
      for ( $rank = count( $reminders ) + 1; $rank <= 6; $rank++ ) {
        $this->acdc_wf_cancel_open_step( $run_id, $step_key . '_r' . $rank, 'Relance retirée de la configuration.' );
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

    /* ACDC 3.25.231 — LE PARCOURS RÉCLAMAIT UN DEVIS DÉJÀ SIGNÉ.
       Un parcours ouvert par une convention signée porte l'identifiant de cette
       convention. Ce résolveur ne le lisait jamais : il cherchait la convention
       par le prospect, ou par le couple entreprise + formation d'un devis. Sur
       une convention créée à la main, sans prospect source, il ne trouvait donc
       RIEN — le dossier passait pour non engagé, et le moteur planifiait « créer
       le devis » et « relancer la signature de la convention » sur un dossier
       dont le devis et la convention étaient signés depuis une demi-heure.
       C'est le défaut le plus grave de cette campagne : un utilisateur qui croit
       cet écran relance un client qui a déjà signé.
       L'ancre propre du parcours passe donc en premier. Les recherches par
       déduction ne servent plus qu'à défaut. */
    if ( ! empty( $run->contract_id ) ) {
      $contract = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$this->registration_contract_table} WHERE id = %d",
        (int) $run->contract_id
      ) );
    }
    if ( ! $contract && ! empty( $need->dossier_id ) ) {
      $contract = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$this->registration_contract_table} WHERE id = %d",
        (int) $need->dossier_id
      ) );
    }
    if ( ! $contract && $prospect_id > 0 ) {
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

    $sessions = $this->acdc_wf_resolve_sessions( $formation_id, $company_id, $contract );
    $session  = ! empty( $sessions ) ? $sessions[0] : null;

    $funder_id = $this->acdc_wf_resolve_funder_id( $need, $contract, $company_id );

    return array(
      'need'         => $need,
      'proposal'     => $proposal,
      'quote'        => $quote,
      'contract'     => $contract,
      'session'      => $session,
      'sessions'     => $sessions,
      'session_id'   => $session ? (int) $session->id : 0,
      'formation_id' => $formation_id,
      'company_id'   => $company_id,
      'prospect_id'  => $prospect_id,
      'funder_id'    => $funder_id,
      'funder_name'  => $this->acdc_wf_entity_name( $this->funder_table, $funder_id ),
      'company_name' => $this->acdc_wf_entity_name( $this->company_table, $company_id ),
      'trainer_id'   => $this->acdc_wf_resolve_trainer_id( $sessions ),
      'trainer_name' => $this->acdc_wf_trainer_name( $this->acdc_wf_resolve_trainer_id( $sessions ) ),
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

  /**
   * ACDC 3.25.191 — Le financeur se déclare sur le RECUEIL DES BESOINS.
   *
   * C'est le seul endroit du plugin qui porte une colonne `funder_id`. Le moteur
   * interrogeait la convention et l'entreprise, où cette colonne n'existe pas :
   * la branche financeur ne pouvait donc JAMAIS s'activer, quel que soit le
   * dossier. Le message « Aucun financeur rattaché » était exact du point de vue
   * du code, et faux du point de vue de l'utilisateur qui venait d'en désigner
   * un. Les deux autres sources restent interrogées si elles existent un jour.
   */
  private function acdc_wf_resolve_funder_id( $need, $contract, $company_id ) {
    global $wpdb;

    if ( $need && ! empty( $need->funder_id ) ) {
      return (int) $need->funder_id;
    }
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

  /**
   * ACDC 3.25.191 — Une formation de trois jours, ce sont TROIS séances.
   *
   * Le moteur n'en retenait qu'une, la première, et en déduisait une fin de
   * formation au premier jour : toutes les enquêtes de fin partaient deux jours
   * trop tôt, avant même que la formation soit terminée. C'est le genre de
   * défaut qu'aucun destinataire ne signale — il répond juste à une enquête sur
   * une formation qu'il n'a pas finie.
   *
   * Le filtre par entreprise posait un second piège : une séance créée depuis
   * une proposition naît SANS entreprise, donc invisible pour un dossier qui en
   * porte une. On accepte désormais ces séances orphelines, mais uniquement dans
   * la fenêtre de dates de la convention — sans quoi le dossier d'un client
   * ramasserait les séances d'un autre sur la même formation.
   */
  private function acdc_wf_resolve_sessions( $formation_id, $company_id, $contract ) {
    global $wpdb;

    $formation_id = (int) $formation_id;
    if ( $formation_id <= 0 ) {
      return array();
    }

    $sql = "SELECT * FROM {$this->session_table}
             WHERE formation_id = %d AND is_draft = 0
               AND COALESCE(status,'') NOT IN ('Annulée','Annulee')";
    $values = array( $formation_id );

    $company_id = (int) $company_id;
    $bounded    = $contract && ! empty( $contract->start_date ) && ! empty( $contract->end_date );

    if ( $company_id > 0 ) {
      /* ACDC 3.25.222 — UNE SÉANCE SANS ENTREPRISE PORTE 0, PAS NULL.
         La recette a monté un dossier complet — quatre séances valides, bonne
         formation, bon formateur, bonnes dates — et le moteur n'en a vu
         aucune : il réclamait « Rattacher une séance au dossier » pendant que
         les quatre étaient sous ses yeux. Ni convocation ni émargement ne
         pouvaient donc être planifiés.
         La cause tient à une valeur : les séances créées depuis l'écran
         « Créer une séance » n'ont pas d'entreprise, et le formulaire y écrit
         ZÉRO là où cette condition n'acceptait que NULL. Zéro n'est ni
         l'identifiant du client, ni NULL : la séance tombait entre les deux et
         disparaissait du dossier.
         On traite désormais 0 et NULL pour ce qu'ils sont l'un comme l'autre —
         l'absence de rattachement — au lieu de faire dépendre un dossier entier
         de la façon dont un formulaire note « rien ». */
      $sql     .= $bounded ? ' AND ( company_id = %d OR company_id IS NULL OR company_id = 0 )' : ' AND company_id = %d';
      $values[] = $company_id;
    }
    if ( $bounded ) {
      $sql     .= ' AND COALESCE(start_date, DATE(start_at)) BETWEEN %s AND %s';
      $values[] = (string) $contract->start_date;
      $values[] = (string) $contract->end_date;
    }

    $sql .= ' ORDER BY COALESCE(start_date, DATE(start_at)) ASC, COALESCE(start_at, "") ASC, id ASC LIMIT 200';

    $rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
    if ( ! empty( $rows ) ) {
      return $rows;
    }

    /* ACDC 3.25.200 — La borne de dates ne doit jamais faire disparaître un
       dossier.
       Elle avait été posée pour empêcher qu'un dossier ramasse les séances d'un
       autre client sur la même formation. Mais quand les dates de la convention
       ne recouvrent pas celles de ses séances — parce qu'elles n'ont pas été
       saisies, ou qu'une session a été déplacée — la requête ne rend plus RIEN,
       et le dossier perd d'un coup ses séances, son formateur et ses rappels
       d'émargement. C'est ce qu'a montré la recette : un parcours dont les
       étapes déjà jouées nommaient le formateur et dont les étapes à venir ne le
       nommaient plus.
       Une borne trop stricte qui vide un dossier est pire que le risque qu'elle
       prévient. Si elle ne rend rien, on la retire et l'on garde le seul filtre
       qui identifie vraiment le client : son entreprise. */
    if ( ! $bounded ) {
      return array();
    }

    $sql = "SELECT * FROM {$this->session_table}
             WHERE formation_id = %d AND is_draft = 0
               AND COALESCE(status,'') NOT IN ('Annulée','Annulee')";
    $values = array( $formation_id );
    if ( $company_id > 0 ) {
      /* Le repli hérite de la même lecture : ici la borne de dates a déjà été
         retirée, une séance non rattachée doit donc pouvoir revenir au dossier
         plutôt que de le laisser vide. */
      $sql     .= ' AND ( company_id = %d OR company_id IS NULL OR company_id = 0 )';
      $values[] = $company_id;
    }
    $sql .= ' ORDER BY COALESCE(start_date, DATE(start_at)) ASC, COALESCE(start_at, "") ASC, id ASC LIMIT 200';

    $rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
    return is_array( $rows ) ? $rows : array();
  }

  /**
   * ACDC 3.25.199 — Le formateur du dossier, cherché sur TOUTES ses séances.
   *
   * Régression de la 3.25.191, que la recette a localisée avec précision : la
   * frontière passait exactement entre les étapes déjà jouées, qui nommaient
   * bien le formateur, et les étapes encore à venir, qui le disaient « non
   * rattaché ». Autrement dit le formateur était résolu hier et ne l'était plus
   * aujourd'hui — ce n'était donc pas un défaut d'affichage.
   *
   * En passant d'une séance unique à l'ENSEMBLE des séances du dossier, j'ai
   * continué à lire le formateur sur la seule première d'entre elles. Or
   * l'élargissement du filtre a fait entrer des séances sans entreprise, donc
   * parfois sans formateur, et l'une d'elles est devenue la première. Le dossier
   * avait toujours son formateur ; c'est la question qui était mal posée.
   *
   * On retient donc le premier formateur trouvé sur l'ensemble des séances.
   */
  private function acdc_wf_resolve_trainer_id( $sessions ) {
    foreach ( (array) $sessions as $session ) {
      if ( ! empty( $session->trainer_id ) ) {
        return (int) $session->trainer_id;
      }
    }
    return 0;
  }

  private function acdc_wf_trainer_name( $trainer_id ) {
    global $wpdb;
    $trainer_id = (int) $trainer_id;
    if ( $trainer_id <= 0 ) {
      return '';
    }
    $row = $wpdb->get_row( $wpdb->prepare(
      "SELECT first_name, last_name FROM {$this->trainer_table} WHERE id = %d",
      $trainer_id
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

    /* La formation commence à la première journée du dossier et se termine à la
       DERNIÈRE. Retenir une seule séance faisait partir les enquêtes de fin avant
       la fin réelle de la formation. */
    $sessions = ! empty( $pieces['sessions'] ) ? $pieces['sessions'] : array();
    $count    = count( $sessions );

    if ( $count > 0 ) {
      $first = $sessions[0];
      $last  = $sessions[ $count - 1 ];

      list( $start, $start_source ) = $this->acdc_wf_session_moment( $first, 'start', (int) $first->id, '09:00:00' );
      list( $end,   $end_source )   = $this->acdc_wf_session_moment( $last,  'end',   (int) $last->id,  '17:00:00' );

      /* Une séance sans heure de fin borne quand même la journée. */
      if ( $end <= 0 ) {
        list( $end, $end_source ) = $this->acdc_wf_session_moment( $last, 'start', (int) $last->id, '17:00:00' );
        if ( $end > 0 ) {
          $end        = $this->acdc_wf_local_ts( wp_date( 'Y-m-d', $end ), '17:00:00' );
          $end_source = 'Fin : dernière journée du dossier, séance n°' . (int) $last->id . ' (heure de fin non renseignée).';
        }
      }
      if ( $count > 1 ) {
        $end_source .= ' ' . $count . ' séances au dossier.';
      }
    }

    $contract = $pieces['contract'] ?? null;
    if ( $contract ) {
      if ( $start <= 0 && ! empty( $contract->start_date ) ) {
        $start        = $this->acdc_wf_local_ts( $contract->start_date, '09:00:00' );
        $start_source = 'Début : date de la convention n°' . (int) $contract->id . '.';
      }
      if ( $end <= 0 && ! empty( $contract->end_date ) ) {
        $end        = $this->acdc_wf_local_ts( $contract->end_date, '17:00:00' );
        $end_source = 'Fin : date de fin de la convention n°' . (int) $contract->id . '.';
      }
    }

    $source = trim( ( '' !== $start_source ? $start_source : 'Début : non déterminé.' )
      . ' ' . ( '' !== $end_source ? $end_source : 'Fin : non déterminée.' ) );

    return array( 'start' => $start, 'end' => $end, 'source' => $source );
  }

  private function acdc_wf_run_end_ts( $pieces ) {
    $resolved = $this->acdc_wf_resolve_dates( $pieces );
    return (int) $resolved['end'];
  }

  /**
   * ACDC 3.25.190 — Le JOUR vient de la date, l'HEURE vient de l'horaire.
   *
   * Une séance porte deux représentations de son début : `start_date`, que
   * l'humain saisit, et `start_at`, qui ajoute l'heure. La 3.25.186 préférait
   * `start_at` sans réserve. Reporter une formation en ne changeant que la date
   * laissait donc le parcours calé sur l'ancien jour, et — plus grave — l'écran
   * affichait fièrement « Début : séance n°9 » en citant une valeur que la
   * séance ne portait plus. Une provenance qui contredit la donnée qu'elle
   * prétend citer est pire qu'une provenance absente.
   *
   * Quand les deux divergent, la DATE fait foi pour le jour et l'horaire ne
   * fournit plus que l'heure. L'écran le dit explicitement, pour que la
   * divergence se corrige au lieu de se propager en silence.
   */
  private function acdc_wf_session_moment( $session, $prefix, $session_id, $default_time ) {
    $date_col = $prefix . '_date';
    $at_col   = $prefix . '_at';

    $date = ! empty( $session->$date_col ) ? substr( (string) $session->$date_col, 0, 10 ) : '';
    $at   = ! empty( $session->$at_col ) ? (string) $session->$at_col : '';

    if ( '' === $date && '' === $at ) {
      return array( 0, '' );
    }

    $label = ( 'start' === $prefix ) ? 'Début' : 'Fin';

    if ( '' === $date ) {
      return array( $this->acdc_wf_ts( $at ), $label . ' : horaire de la séance n°' . $session_id . '.' );
    }
    if ( '' === $at ) {
      return array( $this->acdc_wf_local_ts( $date, $default_time ), $label . ' : date de la séance n°' . $session_id . '.' );
    }

    $time    = substr( $at, 11, 8 );
    $at_date = substr( $at, 0, 10 );
    if ( '' === $time || '00:00:00' === $time ) {
      $time = $default_time;
    }

    if ( $at_date === $date ) {
      return array( $this->acdc_wf_local_ts( $date, $time ), $label . ' : séance n°' . $session_id . '.' );
    }

    return array(
      $this->acdc_wf_local_ts( $date, $time ),
      $label . ' : date de la séance n°' . $session_id . ' (son horaire indique encore le ' . $at_date . ' — à corriger).',
    );
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

    foreach ( ( ! empty( $pieces['sessions'] ) ? $pieces['sessions'] : array() ) as $session ) {
      foreach ( $this->acdc_wf_slots_for_session( $session ) as $index => $slot ) {
        $slot['session_id']   = (int) $session->id;
        $slot['seance_index'] = (int) $index;
        $slots[] = $slot;
      }
    }
    /* Garde-fou de volume : un planning réel ne dépasse pas cet ordre de
       grandeur. Au-delà, la donnée est suspecte et l'on préfère ne rien
       planifier plutôt que d'inonder le parcours. */
    return count( $slots ) > 120 ? array() : $slots;
  }

  /** Les demi-journées d'UNE séance : planning détaillé, sinon ses horaires. */
  private function acdc_wf_slots_for_session( $session ) {
    $slots = array();

    if ( ! empty( $session->schedule_json ) ) {
      $schedule = json_decode( (string) $session->schedule_json, true );
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
      return $slots;
    }

    /* Séance d'un seul tenant portant de vraies heures : deux demi-journées si
       elle enjambe midi, une seule sinon. */
    if ( empty( $session->start_at ) ) {
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

  /**
   * ACDC 3.25.187 — Quand un devis est-il RÉELLEMENT parti au client ?
   *
   * La 3.25.185 ne regardait que la colonne `sent_at`, qui n'est renseignée que
   * par l'envoi par e-mail classique. Un devis expédié en SIGNATURE
   * ÉLECTRONIQUE — le chemin du schéma de David, et le seul que la recette
   * emprunte — laissait cette colonne vide : la relance à dix jours n'était donc
   * jamais planifiée. Le défaut ne se voyait pas, puisqu'il se manifestait par
   * l'ABSENCE d'une ligne.
   *
   * On interroge donc trois sources, de la plus fiable à la plus approximative,
   * et l'on retient la première renseignée.
   */
  private function acdc_wf_quote_sent_ts( $quote ) {
    global $wpdb;

    $sent = $this->acdc_wf_ts( $quote->sent_at ?? '' );
    if ( $sent > 0 ) {
      return $sent;
    }

    if ( ! empty( $quote->signature_request_id ) ) {
      $requests = $wpdb->prefix . 'acdc_sig_requests';
      $exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $requests ) );
      if ( $exists === $requests ) {
        $created = $wpdb->get_var( $wpdb->prepare(
          "SELECT created_at FROM {$requests} WHERE id = %d",
          (int) $quote->signature_request_id
        ) );
        $sent = $this->acdc_wf_ts( (string) $created );
        if ( $sent > 0 ) {
          return $sent;
        }
      }
    }

    /* Dernier recours : le devis porte un statut qui prouve qu'il est sorti,
       sans qu'aucune date d'envoi n'ait été conservée. La date de dernière
       modification vaut alors mieux que rien — une relance approximative reste
       préférable à pas de relance du tout. */
    $status = (string) ( $quote->status ?? '' );
    if ( in_array( $status, array( 'envoye', 'a_signer' ), true ) ) {
      return $this->acdc_wf_ts( $quote->updated_at ?? '' );
    }

    return 0;
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

    $this->acdc_wf_touched_keys[ $dedupe ] = true;

    $existing = $wpdb->get_row( $wpdb->prepare(
      "SELECT * FROM {$this->workflow_step_table} WHERE run_id = %d AND dedupe_key = %s LIMIT 1",
      (int) $run_id,
      $dedupe
    ) );

    $scheduled = isset( $args['scheduled_at'] ) && $args['scheduled_at']
      ? $this->acdc_wf_mysql( (int) $args['scheduled_at'] )
      : null;

    if ( $existing ) {
      /* ACDC 3.25.191 — Une décision de MACHINE se révise, une décision
         d'HOMME ne se révise pas.
         Le moteur écartait une branche — « aucun financeur rattaché » — et la
         figeait pour toujours : rattacher un financeur ensuite ne la rouvrait
         jamais. Or ce « sans objet » n'était qu'une lecture de l'état du dossier
         à un instant donné, et l'état a changé. À l'inverse, le « sans objet »
         que David clique lui-même est un arbitrage : le rouvrir au tour suivant
         reviendrait à lui redemander sans fin ce qu'il vient de trancher.
         C'est cette distinction, et elle seule, qui autorise à rouvrir. */
      $status    = (string) $existing->status;
      $reopenable = in_array( $status, array( 'skipped', 'cancelled' ), true )
        && 'human' !== (string) ( $existing->settled_by ?? '' );

      if ( in_array( $status, $this->acdc_wf_settled_statuses(), true ) && ! $reopenable ) {
        return;
      }
      if ( $reopenable ) {
        $wpdb->update(
          $this->workflow_step_table,
          array( 'executed_at' => null, 'result_note' => '', 'settled_by' => '' ),
          array( 'id' => (int) $existing->id )
        );
      }

      /* ACDC 3.25.195 — La cible se rafraîchit à chaque passage.
         Une étape rouverte gardait le destinataire qu'elle avait au moment où
         elle avait été écartée — c'est-à-dire aucun. Les cinq étapes de la
         branche financeur se réactivaient donc avec une colonne Destinataire
         vide, alors que le financeur venait précisément d'être rattaché. Une
         étape qui ne sait pas à qui elle s'adresse ne doit jamais atteindre le
         moment de l'envoi. */
      $target_update = array();
      foreach ( array( 'target_type', 'target_label' ) as $key ) {
        if ( isset( $args[ $key ] ) ) {
          $target_update[ $key ] = (string) $args[ $key ];
        }
      }
      if ( isset( $args['target_id'] ) ) {
        $target_update['target_id'] = (int) $args['target_id'];
      }
      if ( ! empty( $target_update ) ) {
        $wpdb->update( $this->workflow_step_table, $target_update, array( 'id' => (int) $existing->id ) );
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

  /** Retire du plan une étape encore à venir, sans toucher à ce qui est joué. */
  private function acdc_wf_cancel_open_step( $run_id, $dedupe_key, $note ) {
    global $wpdb;
    $now = $this->acdc_wf_mysql( $this->acdc_wf_now() );
    $wpdb->query( $wpdb->prepare(
      "UPDATE {$this->workflow_step_table}
          SET status = 'cancelled', result_note = %s, settled_by = 'engine', updated_at = %s
        WHERE run_id = %d AND dedupe_key = %s AND status IN ('pending','waiting')",
      (string) $note,
      $now,
      (int) $run_id,
      (string) $dedupe_key
    ) );
  }

  /** Clôt une étape encore ouverte. Une étape déjà jouée n'est jamais réécrite. */
  private function acdc_wf_settle_step( $run_id, $dedupe_key, $status, $note = '' ) {
    global $wpdb;
    $now = $this->acdc_wf_mysql( $this->acdc_wf_now() );
    $wpdb->query( $wpdb->prepare(
      "UPDATE {$this->workflow_step_table}
          SET status = %s, result_note = %s, settled_by = 'engine',
              executed_at = COALESCE(executed_at, %s), updated_at = %s
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
    /* ACDC 3.25.207 — La clôture d'un parcours horodate ce qu'elle annule.
       C'était la SECONDE source des lignes de journal sans date : le balayage a
       été corrigé en 3.25.205, mais la fermeture d'un parcours annulait ses
       étapes restantes sans jamais écrire `executed_at`. La purge unique
       nettoierait le passé et cette ligne aurait refabriqué le défaut au premier
       recueil supprimé. Un journal d'audit ne porte pas d'entrée sans date. */
    $wpdb->query( $wpdb->prepare(
      "UPDATE {$this->workflow_step_table}
          SET status = 'cancelled', result_note = %s,
              executed_at = COALESCE(executed_at, %s), updated_at = %s
        WHERE run_id = %d AND status IN ('pending','waiting')",
      (string) $reason,
      $now,
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
  /**
   * Le module qui porte réellement une étape, quand ce n'est pas le moteur.
   *
   * La clé d'une relance porte un suffixe de rang — « survey_hot_r2 » — que
   * l'on retire avant la recherche : une relance est portée par le même module
   * que l'envoi qu'elle relance.
   *
   * @return string Nom du module, ou chaîne vide si l'étape devrait être jouée ici.
   */
  private function acdc_wf_step_delegate( $step_key ) {
    $base = preg_replace( '/_r\d+$/', '', (string) $step_key );

    $delegated = array(
      'nad_send'       => 'module Analyse du besoin',
      'survey_hot'     => 'module Enquêtes',
      'survey_cold'    => 'module Enquêtes',
      'survey_mid'     => 'module Enquêtes',
      'survey_company' => 'module Enquêtes',
      'survey_funder'  => 'module Enquêtes',
      'survey_trainer' => 'module Enquêtes',
    );

    return isset( $delegated[ $base ] ) ? $delegated[ $base ] : '';
  }

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
      /* ACDC 3.25.219 — UNE ÉTAPE DÉLÉGUÉE N'EST PAS UNE ÉTAPE EN ÉCHEC.
         Quatorze étapes automatiques, cinq gestionnaires : toutes les autres
         tombaient ici et s'affichaient « En échec — aucun traitement branché »
         alors que leur envoi avait bien eu lieu, porté par son propre module.
         C'est le choix d'architecture de David lui-même — « garde le cron de
         l'analyse des besoins et intègre-le au workflow » : le moteur ORDONNE,
         il ne réexécute pas ce qu'un module fait déjà.
         La recette a mesuré le coût de ce mensonge : elle a conclu, une fois,
         qu'une phase entière était hors d'atteinte. Un utilisateur aurait
         renoncé à des étapes qui fonctionnent.
         On distingue donc deux cas. L'étape DÉLÉGUÉE se clôt normalement, en
         nommant le module qui la porte. L'étape réellement non branchée reste
         signalée — mais en disant ce qui manque, l'automatisation, et non en
         laissant croire que l'action métier a échoué. */
      $delegate = $this->acdc_wf_step_delegate( (string) $step->step_key );

      if ( '' !== $delegate ) {
        /* ACDC 3.25.221 — CORRECTION D'UNE RÉGRESSION QUE J'AI INTRODUITE.
           La 3.25.219 marquait ces étapes « Faite ». Je remplaçais un faux
           négatif — « En échec » sur une étape qui avait réussi — par un faux
           POSITIF : le parcours déclarait faites cinq enquêtes dont aucune
           n'était partie, l'écran du module affichant 0 envoi et l'archive
           aucun e-mail. Pour un journal d'audit, l'erreur inverse est pire :
           un dossier alarmiste se vérifie, un dossier faussement rassurant ne
           se vérifie jamais.
           Le moteur n'a pas les moyens de constater l'envoi d'un module tiers.
           Il ne prétend donc plus le savoir : l'étape reste EN ATTENTE du
           module, ni réussie ni échouée, et le dit. Elle ne sera pas rejouée —
           ce n'est pas au moteur d'exécuter — mais elle reste visible tant que
           personne n'a confirmé, ce qui est exactement l'état de la réalité. */
        $wpdb->update(
          $this->workflow_step_table,
          array(
            'status'      => 'waiting',
            'result_note' => 'En attente du ' . $delegate . ' : le moteur ordonne, le module exécute. L’envoi n’est pas confirmé par le moteur — vérifiez l’écran du module.',
            'attempts'    => (int) $step->attempts + 1,
            'updated_at'  => $now,
          ),
          array( 'id' => (int) $step->id )
        );
        return;
      }

      $wpdb->update(
        $this->workflow_step_table,
        array(
          'status'      => 'failed',
          'last_error'  => 'Automatisation manquante : aucun envoi automatique n’est rattaché à cette étape (' . (string) $step->step_key . '). L’action métier, elle, peut avoir été faite à la main.',
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
