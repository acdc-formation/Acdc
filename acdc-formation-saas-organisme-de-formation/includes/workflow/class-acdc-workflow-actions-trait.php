<?php
/**
 * ACDC Workflow — actions d'administration.
 *
 * @since 3.25.185
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

trait ACDC_Workflow_Actions_Trait {

  public function acdc_wf_handle_save_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( 'Action non autorisée.' );
    }
    check_admin_referer( 'acdc_wf_save_settings' );

    $raw      = isset( $_POST['wf'] ) && is_array( $_POST['wf'] ) ? wp_unslash( $_POST['wf'] ) : array();
    $defaults = $this->acdc_wf_default_settings();
    $current  = $this->acdc_wf_settings();

    $settings = array(
      'enabled'                 => empty( $raw['enabled'] ) ? 0 : 1,
      'simulation'              => empty( $raw['simulation'] ) ? 0 : 1,
      'test_mode'               => empty( $raw['test_mode'] ) ? 0 : 1,
      'business_days_reminders' => empty( $raw['business_days_reminders'] ) ? 0 : 1,
      'allowed_recipients'      => isset( $raw['allowed_recipients'] ) ? sanitize_textarea_field( (string) $raw['allowed_recipients'] ) : '',
      'delays'                  => $current['delays'],
    );

    $posted = isset( $raw['delays'] ) && is_array( $raw['delays'] ) ? $raw['delays'] : array();
    foreach ( $defaults['delays'] as $key => $default ) {
      if ( ! array_key_exists( $key, $posted ) ) {
        continue;
      }
      if ( is_array( $default ) ) {
        /* Une liste de relances : on ne garde que des entiers strictement
           positifs, dans l'ordre saisi. Une case vide veut dire « ne relance
           pas » — c'est une réponse valable, pas une erreur de saisie. */
        $parts = preg_split( '/[\s,;]+/', (string) $posted[ $key ] );
        $list  = array();
        foreach ( (array) $parts as $part ) {
          $n = absint( $part );
          if ( $n > 0 ) {
            $list[] = $n;
          }
        }
        $settings['delays'][ $key ] = $list;
      } else {
        $settings['delays'][ $key ] = absint( $posted[ $key ] );
      }
    }

    update_option( 'acdc_of_workflow_settings', $settings, false );

    /* Passer le workflow d'« arrêté » à « actif » demande de rattraper les
       recueils déjà en base : sans cela, l'écran de suivi resterait vide et
       David croirait le moteur cassé. On ouvre les parcours et on réconcilie
       tout de suite, mais par lots bornés — jamais de travail illimité dans
       une requête. */
    if ( ! empty( $settings['enabled'] ) ) {
      $this->acdc_wf_cron();
    }

    $this->redirect_to_portal( 'workflow', 'Configuration du workflow enregistrée.', 'success', array( 'view' => 'settings' ) );
  }

  /**
   * ACDC 3.25.202 — Suppression des signatures orphelines.
   *
   * Trois précautions, dans cet ordre, et aucune n'est facultative :
   *   1. on RELIT la liste au moment d'agir, sans faire confiance à ce que le
   *      formulaire transporte — l'écran a pu être ouvert il y a une heure ;
   *   2. on prend une SAUVEGARDE avant de toucher quoi que ce soit, et l'on
   *      renonce si elle échoue : une pièce Qualiopi ne se supprime pas sans
   *      copie ;
   *   3. on journalise ce qui a été supprimé, nommément.
   */
  public function acdc_wf_handle_purge_orphan_emargements() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( 'Action non autorisée.' );
    }
    check_admin_referer( 'acdc_wf_purge_orphan_emargements' );

    if ( empty( $_POST['confirm'] ) ) {
      $this->redirect_to_portal( 'workflow', 'Suppression annulée : confirmation absente.', 'error', array( 'view' => 'emargements' ) );
    }

    $orphans = $this->acdc_wf_orphan_emargement_rows();
    if ( empty( $orphans ) ) {
      $this->redirect_to_portal( 'workflow', 'Aucune signature orpheline à supprimer.', 'info', array( 'view' => 'emargements' ) );
    }

    $backup = $this->create_safety_backup_snapshot( 'purge_orphan_emargements', array( 'user_id' => get_current_user_id() ) );
    if ( empty( $backup ) ) {
      $this->redirect_to_portal( 'workflow', 'Suppression abandonnée : la sauvegarde préalable a échoué. Rien n’a été supprimé.', 'error', array( 'view' => 'emargements' ) );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'acdc_of_emarg_learners';

    $ids   = array();
    $names = array();
    foreach ( $orphans as $row ) {
      $ids[]   = (int) $row->id;
      $names[] = (string) $row->learner_name;
    }

    $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
    $deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids ) );

    $this->log_error( 'emargement', 'Signatures orphelines supprimées.', array(
      'count'      => (int) $deleted,
      'signataires' => implode( ', ', array_unique( $names ) ),
    ) );

    $this->redirect_to_portal(
      'workflow',
      sprintf( '%d signature(s) orpheline(s) supprimée(s). Une sauvegarde a été prise juste avant.', (int) $deleted ),
      'success',
      array( 'view' => 'emargements' )
    );
  }

  /**
   * ACDC 3.25.223 — Rattrapage des étapes jouées en simulation.
   *
   * La recette a mis le doigt sur une perte silencieuse : un dossier ouvert
   * pendant que la simulation était armée voyait tout son parcours se dérouler
   * à l'écran sans qu'un seul e-mail ne parte, et rien ne le rattrapait ensuite.
   * Ce bouton existe pour cela, et il n'est proposé que lorsque la simulation
   * est levée — rattraper pendant qu'elle tourne ne ferait que resimuler.
   */
  public function acdc_wf_handle_replay_simulated() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( 'Action non autorisée.' );
    }
    check_admin_referer( 'acdc_wf_replay_simulated' );

    $settings = $this->acdc_wf_settings();
    if ( ! empty( $settings['simulation'] ) ) {
      $this->redirect_to_portal(
        'workflow',
        'Rattrapage refusé : le mode simulation est toujours actif — les étapes seraient simplement resimulées. Levez la simulation d’abord.',
        'error',
        array( 'view' => 'runs' )
      );
    }

    $count = $this->acdc_wf_replay_simulated_steps();

    if ( $count <= 0 ) {
      $this->redirect_to_portal( 'workflow', 'Aucune étape simulée à rattraper.', 'info', array( 'view' => 'runs' ) );
    }

    $this->log_error( 'workflow', 'Rattrapage des étapes simulées.', array(
      'count'   => (int) $count,
      'user_id' => get_current_user_id(),
    ) );

    /* On réconcilie tout de suite : le balayage effacera les étapes que le
       dossier ne justifie plus, et l'exécution prendra les autres. Sans cet
       appel, David resterait devant un écran inchangé jusqu'au prochain cron. */
    $this->acdc_wf_cron();

    $this->redirect_to_portal(
      'workflow',
      sprintf( '%d étape(s) remise(s) au plan. Celles que le dossier ne justifie plus seront écartées à la réconciliation ; les autres partiront à leur tour.', (int) $count ),
      'success',
      array( 'view' => 'runs' )
    );
  }

  /**
   * ACDC 3.25.226 — Lancer une passe du moteur à la main.
   *
   * Le moteur est un cron au quart d'heure. Quand rien ne bouge — et la recette
   * l'a vécu : dix étapes échues, un journal vide, aucun envoi — l'organisme
   * n'avait AUCUN moyen de savoir si le plan était bloqué ou si le cron du site
   * ne tournait pas. Or ce sont deux pannes très différentes : l'une se corrige
   * dans le plugin, l'autre chez l'hébergeur.
   *
   * Ce bouton lève le doute en une seconde. Il ne force rien : il exécute
   * exactement la passe que le cron aurait faite, avec les mêmes gardes — mode
   * recette compris. Si les étapes partent, le plan allait bien et c'est la
   * planification système qu'il faut regarder.
   */
  public function acdc_wf_handle_run_now() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( 'Action non autorisée.' );
    }
    check_admin_referer( 'acdc_wf_run_now' );

    if ( ! $this->acdc_wf_is_enabled() ) {
      $this->redirect_to_portal( 'workflow', 'Le moteur est en pause : rien n’a été joué. Réactivez-le dans la configuration.', 'error', array( 'view' => 'tasks' ) );
    }

    global $wpdb;
    $before = (int) $wpdb->get_var(
      "SELECT COUNT(*) FROM {$this->workflow_step_table} s
         INNER JOIN {$this->workflow_run_table} r ON r.id = s.run_id
        WHERE s.status = 'pending' AND s.mode = 'auto' AND r.status = 'active'"
    );

    $this->acdc_wf_cron();

    $after = (int) $wpdb->get_var(
      "SELECT COUNT(*) FROM {$this->workflow_step_table} s
         INNER JOIN {$this->workflow_run_table} r ON r.id = s.run_id
        WHERE s.status = 'pending' AND s.mode = 'auto' AND r.status = 'active'"
    );

    $played = max( 0, $before - $after );

    if ( $played > 0 ) {
      $message = sprintf(
        '%d étape(s) jouée(s). Le plan n’était donc pas bloqué : si elles étaient en retard, c’est la planification système (WP-Cron) qu’il faut regarder.',
        $played
      );
      $type = 'success';
    } else {
      $message = 'Passe exécutée, aucune étape jouée. Soit rien n’est échu, soit les étapes en attente dépendent d’un préalable — ouvrez le détail du parcours pour voir laquelle et pourquoi.';
      $type = 'info';
    }

    $this->redirect_to_portal( 'workflow', $message, $type, array( 'view' => 'tasks' ) );
  }

  /**
   * « Sans objet » sur une tâche : David écarte une étape que le dossier ne
   * justifie pas. On ne supprime pas la ligne, on la classe — le journal doit
   * garder trace d'une décision humaine.
   */
  public function acdc_wf_handle_dismiss_task() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( 'Action non autorisée.' );
    }
    check_admin_referer( 'acdc_wf_dismiss_task' );

    global $wpdb;
    $step_id = isset( $_POST['step_id'] ) ? absint( wp_unslash( $_POST['step_id'] ) ) : 0;
    if ( $step_id > 0 ) {
      $now = $this->acdc_wf_mysql( $this->acdc_wf_now() );
      $wpdb->update(
        $this->workflow_step_table,
        array(
          'status'      => 'skipped',
          'result_note' => 'Écartée manuellement.',
          /* Marque la décision comme HUMAINE : le moteur ne rouvrira jamais
             cette étape, quoi que dise le dossier par la suite. */
          'settled_by'  => 'human',
          'executed_at' => $now,
          'updated_at'  => $now,
        ),
        array( 'id' => $step_id )
      );
    }

    $this->redirect_to_portal( 'workflow', 'Tâche écartée.', 'success', array( 'view' => 'tasks' ) );
  }
}
