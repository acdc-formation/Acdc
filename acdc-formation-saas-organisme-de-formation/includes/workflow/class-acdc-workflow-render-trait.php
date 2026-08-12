<?php
/**
 * ACDC Workflow — écrans : suivi, à faire, journal, configuration.
 *
 * L'écran de suivi n'est pas un confort. Une automatisation que l'on ne peut
 * pas regarder est une boîte noire : on ne sait ni ce qu'elle a fait, ni ce
 * qu'elle s'apprête à faire, et le jour où elle se trompe on l'apprend par le
 * destinataire. Ces quatre vues répondent chacune à une question précise :
 * où en est chaque dossier, que dois-je faire moi, qu'a-t-elle fait, et selon
 * quels délais.
 *
 * @since 3.25.185
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

trait ACDC_Workflow_Render_Trait {

  private function render_front_workflow_tab( $action = 'list', $item_id = 0 ) {
    $view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'runs';

    $this->acdc_wf_render_header( $view );

    switch ( $view ) {
      case 'tasks':
        $this->acdc_wf_render_tasks();
        break;
      case 'journal':
        $this->acdc_wf_render_journal();
        break;
      case 'settings':
        $this->acdc_wf_render_settings();
        break;
      case 'emargements':
        $this->acdc_wf_render_orphan_emargements();
        break;
      case 'run':
        $this->acdc_wf_render_run_detail( (int) $item_id );
        break;
      default:
        $this->acdc_wf_render_runs();
        break;
    }
  }

  private function acdc_wf_tab_url( $view, $extra = array() ) {
    $args = array_merge( array( 'tab' => 'workflow', 'view' => $view ), $extra );
    return is_admin()
      ? add_query_arg( $args, admin_url( 'admin.php?page=acdc-of-dashboard' ) )
      : $this->portal_page_url( $args );
  }

  private function acdc_wf_render_header( $view ) {
    $settings = $this->acdc_wf_settings();
    $views = array(
      'runs'     => 'Suivi des parcours',
      'tasks'    => 'À faire',
      'journal'  => 'Journal',
      'emargements' => 'Émargements orphelins',
      'settings' => 'Configuration',
    );
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Workflow</h2>
        <p>Orchestration du parcours, du recueil des besoins jusqu'aux enquêtes de fin de formation.</p>
      </div>
    </section>
    <?php
    if ( empty( $settings['enabled'] ) ) {
      /* ACDC 3.25.188 — Le bandeau annonçait « aucun parcours n'est ouvert et
         rien n'est planifié » pendant que l'onglet voisin en listait cinq avec
         leurs échéances. Deux phrases qui se contredisent à l'écran font douter
         de l'ensemble : à l'arrêt, le moteur est en PAUSE, il ne perd rien. */
      echo '<div class="acdc-alert acdc-alert-warning"><strong>Le moteur est en pause.</strong> Les parcours et leurs plans sont conservés, mais plus rien n\'est réévalué, planifié ni envoyé tant que vous ne l\'avez pas réactivé dans la configuration.</div>';
    } elseif ( ! empty( $settings['simulation'] ) ) {
      echo '<div class="acdc-alert acdc-alert-info"><strong>Mode simulation.</strong> Les parcours sont planifiés et le journal se remplit, mais <strong>aucun e-mail ne part</strong>. Regardez le plan, ajustez les délais, puis désactivez la simulation.</div>';
    } elseif ( $this->acdc_wf_test_mode_is_mute() ) {
      /* ACDC 3.25.186 — Mode recette armé sans aucune adresse déclarée : le
         garde-fou refuse tout le monde. C'est le comportement sûr, mais il était
         silencieux, et un garde-fou muet ne rassure personne. */
      echo '<div class="acdc-alert acdc-alert-warning"><strong>Mode recette actif, aucune adresse déclarée.</strong> Dans cet état <strong>aucun envoi ne peut partir, à personne</strong>. Renseignez les adresses autorisées, ou désactivez le mode recette.</div>';
    } elseif ( ! empty( $settings['test_mode'] ) ) {
      echo '<div class="acdc-alert acdc-alert-warning"><strong>Mode recette.</strong> Les envois ne partent qu\'aux adresses déclarées dans la configuration. Tout autre destinataire est refusé et journalisé.</div>';
    } else {
      echo '<div class="acdc-alert acdc-alert-success"><strong>Workflow actif.</strong> Les envois partent réellement, à tous les destinataires.</div>';
    }

    /* ACDC 3.25.223 — Ce que la simulation a consommé ne doit pas rester muet.
       Simulation levée, mais des étapes portent encore « Simulée — aucun
       envoi » sur des parcours actifs : ces dossiers ont un plan déroulé et
       zéro e-mail parti. Le moteur ne les rejouera pas de lui-même — ce serait
       décider à la place de l'organisme — mais il ne peut pas non plus laisser
       croire que tout est fait. */
    if ( empty( $settings['simulation'] ) && ! empty( $settings['enabled'] ) ) {
      $backlog = $this->acdc_wf_simulated_backlog_count();
      if ( $backlog > 0 ) {
        ?>
        <div class="acdc-alert acdc-alert-warning">
          <strong><?php echo (int) $backlog; ?> étape(s) jouée(s) en simulation, donc jamais envoyée(s).</strong>
          Elles appartiennent à des parcours encore actifs et le moteur ne les reprendra pas tout seul : pour lui, elles sont derrière nous.
          <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px">
            <?php wp_nonce_field( 'acdc_wf_replay_simulated' ); ?>
            <input type="hidden" name="action" value="acdc_wf_replay_simulated">
            <button type="submit" class="acdc-button acdc-button-primary">Remettre ces étapes au plan</button>
            <span class="description" style="margin-left:10px">Les étapes que le dossier ne justifie plus seront écartées à la réconciliation. Celles dont l'heure est passée partiront dès la prochaine passe.</span>
          </form>
        </div>
        <?php
      }
    }
    ?>
    <nav class="acdc-subtabs">
      <?php foreach ( $views as $key => $label ) : ?>
        <a class="acdc-button <?php echo $view === $key ? 'acdc-button-primary' : 'acdc-button-soft'; ?>"
           href="<?php echo esc_url( $this->acdc_wf_tab_url( $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
      <?php endforeach; ?>
    </nav>
    <?php
  }

  /* =====================================================================
   * Suivi des parcours
   * ===================================================================== */

  private function acdc_wf_render_runs() {
    global $wpdb;

    $runs = $wpdb->get_results(
      "SELECT r.*, n.theme AS need_theme, p.company_name AS prospect_company
         FROM {$this->workflow_run_table} r
         LEFT JOIN {$this->need_table} n ON n.id = r.need_id
         LEFT JOIN {$this->prospect_table} p ON p.id = r.prospect_id
        ORDER BY FIELD(r.status,'active') DESC, r.updated_at DESC
        LIMIT 200"
    );

    if ( empty( $runs ) ) {
      echo '<div class="acdc-panel"><p>Aucun parcours ouvert. Un parcours s\'ouvre automatiquement à la création d\'un recueil des besoins.</p></div>';
      return;
    }

    $phases = $this->acdc_wf_phases();
    ?>
    <div class="acdc-panel">
      <table class="acdc-table">
        <thead>
          <tr>
            <th>Dossier</th>
            <th>Phase</th>
            <th>Prochaine action</th>
            <th>Prévue le</th>
            <th>État</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ( $runs as $run ) :
          $next  = $this->acdc_wf_next_step( (int) $run->id );
          $label = $this->acdc_wf_run_display_label( $run );
          $late  = $next && ! empty( $next->scheduled_at ) && $this->acdc_wf_ts( $next->scheduled_at ) < $this->acdc_wf_now();
          ?>
          <tr>
            <td>
              <strong><?php echo esc_html( $label ); ?></strong>
            </td>
            <td><?php echo esc_html( $phases[ $run->phase ] ?? $run->phase ); ?></td>
            <td>
              <?php if ( $next ) : ?>
                <?php echo esc_html( $next->label ); ?>
                <?php if ( '' !== (string) $next->target_label ) : ?>
                  <span class="description">— <?php echo esc_html( $next->target_label ); ?></span>
                <?php endif; ?>
              <?php else : ?>
                <span class="description">Rien en attente</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ( $next && ! empty( $next->scheduled_at ) ) : ?>
                <?php echo esc_html( wp_date( 'd/m/Y H:i', $this->acdc_wf_ts( $next->scheduled_at ) ) ); ?>
                <?php if ( $late ) : ?><br><span style="color:#b32d2e">en retard</span><?php endif; ?>
              <?php else : ?>
                <span class="description">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ( 'active' === (string) $run->status ) : ?>
                <?php /* ACDC 3.25.207 — Un parcours actif dont plus aucune étape
                         n'attend n'est pas « en cours » : il n'y a plus rien à
                         faire. C'est le cas des dossiers dont toutes les étapes
                         ont été neutralisées parce qu'un autre parcours pilote
                         la même séance. On le dit.
                         Le parcours reste ACTIF en base, volontairement : s'il
                         gagne demain une séance ou un devis, le moteur doit
                         pouvoir replanifier. Fermer le dossier pour faire joli à
                         l'écran le rendrait sourd à la suite. */ ?>
                <?php if ( ! $next ) : ?>
                  Réglé<br><span class="description">Plus aucune étape en attente.</span>
                <?php else : ?>
                  En cours
                <?php endif; ?>
              <?php else : ?>
                <?php echo esc_html( ucfirst( (string) $run->status ) ); ?>
                <?php if ( '' !== (string) $run->close_reason ) : ?>
                  <br><span class="description"><?php echo esc_html( $run->close_reason ); ?></span>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td>
              <a class="acdc-button acdc-button-soft"
                 href="<?php echo esc_url( $this->acdc_wf_tab_url( 'run', array( 'item_id' => (int) $run->id ) ) ); ?>">Détail</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
  }

  private function acdc_wf_render_run_detail( $run_id ) {
    $run = $this->acdc_wf_get_run( $run_id );
    if ( ! $run ) {
      echo '<div class="acdc-alert acdc-alert-error">Parcours introuvable.</div>';
      return;
    }
    $steps  = $this->acdc_wf_get_steps( (int) $run->id );
    $phases = $this->acdc_wf_phases();
    ?>
    <div class="acdc-panel">
      <h3><?php echo esc_html( $run->label ); ?></h3>
      <p class="description">
        Recueil n°<?php echo (int) $run->need_id; ?>
        <?php if ( $run->quote_id ) : ?> · Devis n°<?php echo (int) $run->quote_id; ?><?php endif; ?>
        <?php if ( $run->contract_id ) : ?> · Convention n°<?php echo (int) $run->contract_id; ?><?php endif; ?>
        <?php if ( $run->session_id ) : ?> · Séance n°<?php echo (int) $run->session_id; ?><?php endif; ?>
      </p>
      <?php
      /* ACDC 3.25.186 — L'ancre des enquêtes s'affiche, avec sa provenance. Cinq
         enquêtes et douze relances se calculent depuis cette date : la lire ne
         doit pas demander une requête en base. */
      $start_ts = $this->acdc_wf_ts( $run->formation_start_at ?? '' );
      $end_ts   = $this->acdc_wf_ts( $run->formation_end_at ?? '' );
      ?>
      <?php
      /* ACDC 3.25.234 — La même information s'écrivait deux fois : la ligne des
         dates, puis la ligne de provenance qui redit « Début : non déterminé.
         Fin : non déterminée. » quand justement il n'y a rien à expliquer. Une
         provenance ne se lit que lorsqu'il y a une date dont on veut connaître
         l'origine. */
      $dates_source = trim( (string) ( $run->dates_source ?? '' ) );
      $has_dates    = ( $start_ts > 0 || $end_ts > 0 );
      ?>
      <p class="description">
        <strong>Dates de formation retenues</strong> —
        début : <?php echo $start_ts > 0 ? esc_html( wp_date( 'd/m/Y H:i', $start_ts ) ) : 'non déterminé'; ?> ·
        fin : <?php echo $end_ts > 0 ? esc_html( wp_date( 'd/m/Y H:i', $end_ts ) ) : 'non déterminée'; ?>
        <?php if ( $has_dates && '' !== $dates_source ) : ?>
          <br><?php echo esc_html( $dates_source ); ?>
        <?php elseif ( ! $has_dates ) : ?>
          <br>Aucune séance n'est encore rattachée au dossier : les convocations, les rappels d'émargement et les enquêtes se planifieront dès que les dates seront connues.
        <?php endif; ?>
      </p>
      <table class="acdc-table">
        <thead>
          <tr><th>Phase</th><th>Étape</th><th>Destinataire</th><th>Prévue le</th><th>État</th><th>Observation</th></tr>
        </thead>
        <tbody>
        <?php foreach ( $steps as $step ) : ?>
          <tr>
            <td><?php echo esc_html( $phases[ $step->phase ] ?? $step->phase ); ?></td>
            <td><?php echo esc_html( $step->label ); ?></td>
            <td><?php echo '' !== (string) $step->target_label ? esc_html( $step->target_label ) : '<span class="description">—</span>'; ?></td>
            <td><?php echo ! empty( $step->scheduled_at ) ? esc_html( wp_date( 'd/m/Y H:i', $this->acdc_wf_ts( $step->scheduled_at ) ) ) : '<span class="description">—</span>'; ?></td>
            <td><?php echo esc_html( $this->acdc_wf_status_label( $step->status ) ); ?></td>
            <td>
              <?php echo '' !== (string) $step->result_note ? esc_html( $step->result_note ) : ''; ?>
              <?php if ( '' !== (string) $step->last_error ) : ?>
                <span style="color:#b32d2e"><?php echo esc_html( $step->last_error ); ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php
      /* ACDC 3.25.234 — UN PARCOURS D'UNE SEULE LIGNE NE RESSEMBLE PAS À UN
         PARCOURS. Le moteur ne planifie que ce que le dossier justifie : au
         lendemain d'un recueil, il n'y a effectivement qu'une étape à faire.
         Mais l'écran laissait croire que le parcours se résumait à cela, alors
         qu'une trentaine d'étapes suivront. On annonce donc la suite, sans la
         planifier : ce sont deux choses différentes, et les confondre serait
         retomber dans l'écran qui affirme au lieu de lire. */
      $phase_order = array( 'commercial', 'preparation', 'animation', 'evaluation' );
      $reached     = array();
      foreach ( $steps as $step ) {
        $reached[ (string) $step->phase ] = true;
      }
      $upcoming = array();
      foreach ( $phase_order as $phase_key ) {
        if ( ! isset( $reached[ $phase_key ] ) && isset( $phases[ $phase_key ] ) ) {
          $upcoming[] = $phases[ $phase_key ];
        }
      }
      ?>
      <?php if ( ! empty( $upcoming ) ) : ?>
      <p class="description" style="margin-top:12px;">
        <strong>La suite du parcours n'est pas encore planifiée</strong> — phases à venir :
        <?php echo esc_html( implode( ', ', $upcoming ) ); ?>.
        Le moteur ajoute chaque étape au moment où le dossier la justifie : la proposition ouvre le devis,
        le devis signé ouvre la convention, et les séances déclenchent convocations, émargements et enquêtes.
      </p>
      <?php endif; ?>
      <p><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->acdc_wf_tab_url( 'runs' ) ); ?>">&#8592; Retour au suivi</a></p>
    </div>
    <?php
  }

  /* =====================================================================
   * À faire
   * ===================================================================== */

  private function acdc_wf_render_tasks() {
    global $wpdb;

    $tasks = $wpdb->get_results(
      "SELECT s.*, r.label AS run_label, r.need_id, p.company_name AS prospect_company
         FROM {$this->workflow_step_table} s
         INNER JOIN {$this->workflow_run_table} r ON r.id = s.run_id
         LEFT JOIN {$this->prospect_table} p ON p.id = r.prospect_id
        WHERE s.mode IN ('task','alert') AND s.status = 'pending' AND r.status = 'active'
        ORDER BY s.is_alert DESC, s.scheduled_at ASC
        LIMIT 200"
    );

    /* ACDC 3.25.226 — « RIEN À FAIRE » PENDANT QUE DIX ÉTAPES SONT EN RETARD.
       La recette a buté sur une contradiction interne : l'onglet Suivi affichait
       « en retard » sur un parcours dont dix étapes étaient échues, et cette
       file répondait « Rien à faire pour l'instant. » Les deux disaient vrai
       dans leur périmètre — cette file ne liste que les étapes CONFIÉES À
       L'HUMAIN — mais l'écran, lui, mentait : ce qui bloquait, ce sont les
       étapes AUTOMATIQUES que le cron n'avait pas encore jouées.
       Une file d'attente qui ne montre pas ce qui est en retard n'est pas une
       file d'attente. On les montre, on dit qui doit les jouer, et on donne le
       moyen de les jouer tout de suite. */
    $overdue = $wpdb->get_results( $wpdb->prepare(
      "SELECT s.*, r.label AS run_label, r.need_id, p.company_name AS prospect_company
         FROM {$this->workflow_step_table} s
         INNER JOIN {$this->workflow_run_table} r ON r.id = s.run_id
         LEFT JOIN {$this->prospect_table} p ON p.id = r.prospect_id
        WHERE s.mode = 'auto' AND s.status = 'pending' AND r.status = 'active'
          AND s.scheduled_at IS NOT NULL AND s.scheduled_at <= %s
        ORDER BY s.scheduled_at ASC
        LIMIT 200",
      $this->acdc_wf_mysql( $this->acdc_wf_now() )
    ) );

    if ( ! empty( $overdue ) ) {
      ?>
      <div class="acdc-alert acdc-alert-warning">
        <strong><?php echo (int) count( $overdue ); ?> étape(s) automatique(s) sont échues et n'ont pas encore été jouées.</strong>
        Elles ne demandent aucune action de votre part : c'est le moteur qui les exécute, à sa cadence — un quart d'heure au plus.
        Si elles ne bougent pas d'une passe à l'autre, le cron du site ne tourne pas ; lancez-en une à la main pour le vérifier.
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px">
          <?php wp_nonce_field( 'acdc_wf_run_now' ); ?>
          <input type="hidden" name="action" value="acdc_wf_run_now">
          <button type="submit" class="acdc-button acdc-button-primary">Lancer une passe maintenant</button>
        </form>
      </div>
      <div class="acdc-panel acdc-mb-18">
        <h3 style="margin:0 0 10px;">Étapes automatiques en retard</h3>
        <table class="acdc-table">
          <thead><tr><th>Étape</th><th>Dossier</th><th>Prévue le</th></tr></thead>
          <tbody>
          <?php foreach ( $overdue as $step ) : ?>
            <tr>
              <td><strong><?php echo esc_html( $step->label ); ?></strong>
                <?php if ( '' !== (string) $step->target_label ) : ?><br><span class="description"><?php echo esc_html( $step->target_label ); ?></span><?php endif; ?>
              </td>
              <td><?php echo esc_html( $this->acdc_wf_run_display_label( $step ) ); ?></td>
              <td><?php echo esc_html( wp_date( 'd/m/Y H:i', $this->acdc_wf_ts( $step->scheduled_at ) ) ); ?><br><span style="color:#b32d2e">en retard</span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php
    }

    /* ACDC 3.25.228 — Le bouton de passe manuelle n'apparaissait QU'EN CAS DE
       RETARD : la recette l'a cherché sans le trouver, précisément parce que
       le moteur venait de tourner. Or c'est un outil de diagnostic — il sert à
       savoir si le cron du site fonctionne — et un outil de diagnostic qui ne
       se montre que pendant la panne ne sert à rien : on ne peut plus établir
       la référence quand tout va bien. Il est donc toujours là, discret. */
    ?>
    <div class="acdc-panel acdc-mb-18">
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:0;">
        <?php wp_nonce_field( 'acdc_wf_run_now' ); ?>
        <input type="hidden" name="action" value="acdc_wf_run_now">
        <button type="submit" class="acdc-button acdc-button-soft">Lancer une passe maintenant</button>
        <span class="description">Le moteur tourne seul tous les quarts d'heure. Ce bouton joue la même passe immédiatement — utile pour vérifier que la planification du site fonctionne.</span>
      </form>
    </div>
    <?php

    if ( empty( $tasks ) ) {
      echo '<div class="acdc-panel"><p>' . ( empty( $overdue )
        ? 'Rien à faire pour l\'instant.'
        : 'Aucune tâche ne vous est confiée : les étapes ci-dessus sont automatiques.' ) . '</p></div>';
      return;
    }
    ?>
    <div class="acdc-panel">
      <table class="acdc-table">
        <?php /* ACDC 3.25.207 — « Depuis » affirmait qu'une tâche traînait
                 depuis une date à venir. La colonne dit maintenant ce qu'elle
                 montre : une échéance passée se lit « depuis le », une échéance
                 future « prévu le ».
                 La tâche future reste affichée, et c'est délibéré : le
                 rendez-vous de relance se montre dès qu'il est planifié (voir la
                 note de la 3.25.187), parce que le dossier qui se perd est
                 justement celui qu'on ne voit pas venir. Ce qu'il fallait
                 corriger, c'est le mot, pas la présence. */ ?>
        <thead><tr><th></th><th>À faire</th><th>Dossier</th><th>Échéance</th><th></th></tr></thead>
        <tbody>
        <?php foreach ( $tasks as $task ) : ?>
          <tr>
            <td><?php echo (int) $task->is_alert ? '<span title="Alerte" style="color:#b32d2e">&#9888;</span>' : ''; ?></td>
            <td><strong><?php echo esc_html( $task->label ); ?></strong></td>
            <td><?php echo esc_html( $this->acdc_wf_run_display_label( $task ) ); ?></td>
            <td>
              <?php
              if ( empty( $task->scheduled_at ) ) {
                echo '—';
              } else {
                $due_ts = $this->acdc_wf_ts( $task->scheduled_at );
                $word   = ( $due_ts > $this->acdc_wf_now() ) ? 'prévu le' : 'depuis le';
                echo esc_html( $word . ' ' . wp_date( 'd/m/Y', $due_ts ) );
              }
              ?>
            </td>
            <td>
              <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'acdc_wf_dismiss_task' ); ?>
                <input type="hidden" name="action" value="acdc_wf_dismiss_task">
                <input type="hidden" name="step_id" value="<?php echo (int) $task->id; ?>">
                <button type="submit" class="acdc-button acdc-button-soft">Sans objet</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="description">Une tâche disparaît d'elle-même dès que la pièce attendue existe : créez la proposition, le devis ou la convention, et la ligne se referme au passage suivant du moteur.</p>
    </div>
    <?php
  }

  /* =====================================================================
   * Journal
   * ===================================================================== */

  private function acdc_wf_render_journal() {
    global $wpdb;

    $rows = $wpdb->get_results(
      "SELECT s.*, r.label AS run_label, r.need_id, p.company_name AS prospect_company
         FROM {$this->workflow_step_table} s
         INNER JOIN {$this->workflow_run_table} r ON r.id = s.run_id
         LEFT JOIN {$this->prospect_table} p ON p.id = r.prospect_id
        WHERE s.status IN ('done','simulated','failed','skipped','cancelled')
        ORDER BY s.executed_at DESC, s.updated_at DESC
        LIMIT 300"
    );

    if ( empty( $rows ) ) {
      echo '<div class="acdc-panel"><p>Le journal est vide : aucune étape n\'a encore été jouée.</p></div>';
      return;
    }
    ?>
    <div class="acdc-panel">
      <table class="acdc-table">
        <thead><tr><th>Quand</th><th>Dossier</th><th>Étape</th><th>Destinataire</th><th>État</th><th>Observation</th></tr></thead>
        <tbody>
        <?php foreach ( $rows as $row ) : ?>
          <tr>
            <td><?php echo ! empty( $row->executed_at ) ? esc_html( wp_date( 'd/m/Y H:i', $this->acdc_wf_ts( $row->executed_at ) ) ) : '—'; ?></td>
            <td><?php echo esc_html( $this->acdc_wf_run_display_label( $row ) ); ?></td>
            <td><?php echo esc_html( $row->label ); ?></td>
            <td><?php echo '' !== (string) $row->target_label ? esc_html( $row->target_label ) : '—'; ?></td>
            <td><?php echo esc_html( $this->acdc_wf_status_label( $row->status ) ); ?></td>
            <td>
              <?php echo esc_html( (string) $row->result_note ); ?>
              <?php if ( '' !== (string) $row->last_error ) : ?>
                <span style="color:#b32d2e"><?php echo esc_html( $row->last_error ); ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
  }

  /* =====================================================================
   * Configuration
   * ===================================================================== */

  private function acdc_wf_render_settings() {
    $s = $this->acdc_wf_settings();
    $d = $s['delays'];
    ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'acdc_wf_save_settings' ); ?>
      <input type="hidden" name="action" value="acdc_wf_save_settings">

      <div class="acdc-panel acdc-profile-section">
        <h3>Mise en service</h3>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Activer le workflow</div>
          <div><label class="acdc-switch"><input type="checkbox" name="wf[enabled]" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?>><span class="acdc-switch-slider"></span></label>
            <p class="description">À l'arrêt, aucun parcours n'est ouvert et rien n'est planifié.</p></div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Mode simulation</div>
          <div><label class="acdc-switch"><input type="checkbox" name="wf[simulation]" value="1" <?php checked( ! empty( $s['simulation'] ) ); ?>><span class="acdc-switch-slider"></span></label>
            <p class="description">Tout est planifié et journalisé, <strong>rien n'est envoyé</strong>. À laisser actif jusqu'à ce que le plan vous convienne.</p></div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Mode recette</div>
          <div><label class="acdc-switch"><input type="checkbox" name="wf[test_mode]" value="1" <?php checked( ! empty( $s['test_mode'] ) ); ?>><span class="acdc-switch-slider"></span></label>
            <p class="description">Seules les adresses ci-dessous peuvent recevoir un envoi. Tout autre destinataire est refusé et journalisé — c'est ce qui protège les financeurs réels.</p></div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Adresses autorisées</div>
          <div><textarea name="wf[allowed_recipients]" rows="3" placeholder="une adresse par ligne"><?php echo esc_textarea( (string) $s['allowed_recipients'] ); ?></textarea></div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Décaler les relances au jour ouvré</div>
          <div><label class="acdc-switch"><input type="checkbox" name="wf[business_days_reminders]" value="1" <?php checked( ! empty( $s['business_days_reminders'] ) ); ?>><span class="acdc-switch-slider"></span></label>
            <p class="description">Ne s'applique qu'aux relances. Un envoi initial suit un fait daté et part quand il doit partir.</p></div>
        </div>
      </div>

      <div class="acdc-panel acdc-profile-section">
        <h3>Phase commerciale</h3>
        <?php
        $this->acdc_wf_number_row( 'Relance du devis, après envoi', 'quote_reminder_days', $d, 'jours' );
        $this->acdc_wf_number_row( 'Devis toujours non signé après la relance', 'quote_rdv_after_days', $d, 'jours' );
        $this->acdc_wf_number_row( 'Relance de la convention, après envoi', 'convention_reminder_days', $d, 'jours' );
        $this->acdc_wf_number_row( 'Convention toujours non signée après la relance', 'convention_rdv_after_days', $d, 'jours' );
        ?>
      </div>

      <div class="acdc-panel acdc-profile-section">
        <h3>Préparation et animation</h3>
        <?php
        $this->acdc_wf_number_row( 'Analyse des besoins, avant le début (défaut si la convention ne le précise pas)', 'nad_days_before_start', $d, 'jours' );
        $this->acdc_wf_number_row( 'Dossier au formateur, avant le début', 'trainer_pack_days_before', $d, 'jours' );
        $this->acdc_wf_number_row( 'Heure d’envoi de la convocation, la veille', 'convocation_hour', $d, 'h' );
        $this->acdc_wf_number_row( 'Ouverture de la feuille d’émargement, avant chaque demi-journée', 'emargement_lead_minutes', $d, 'minutes' );
        ?>
      </div>

      <div class="acdc-panel acdc-profile-section">
        <h3>Enquêtes</h3>
        <p class="description">Les relances sont <strong>cumulatives</strong> : chaque délai part de la relance précédente. Trois relances réglées à 3, 5 et 7 jours tombent donc à J+3, J+8 puis J+15 après l'envoi.</p>
        <?php
        $this->acdc_wf_number_row( 'Enquête à chaud — envoi après la fin', 'survey_hot_offset_hours', $d, 'heures' );
        $this->acdc_wf_list_row( 'Enquête à chaud — relances', 'survey_hot_reminder_hours', $d, 'heures' );
        $this->acdc_wf_number_row( 'Enquête entreprise — envoi après la fin', 'survey_company_offset_hours', $d, 'heures' );
        $this->acdc_wf_list_row( 'Enquête entreprise — relances', 'survey_company_reminder_days', $d, 'jours' );
        $this->acdc_wf_number_row( 'Enquête financeur — envoi après la fin', 'survey_funder_offset_hours', $d, 'heures' );
        $this->acdc_wf_list_row( 'Enquête financeur — relances', 'survey_funder_reminder_days', $d, 'jours' );
        $this->acdc_wf_number_row( 'Enquête formateur — envoi après la fin', 'survey_trainer_offset_hours', $d, 'heures' );
        $this->acdc_wf_list_row( 'Enquête formateur — relances', 'survey_trainer_reminder_days', $d, 'jours' );
        $this->acdc_wf_number_row( 'Enquête à froid — envoi après la fin', 'survey_cold_offset_days', $d, 'jours' );
        $this->acdc_wf_list_row( 'Enquête à froid — relances', 'survey_cold_reminder_days', $d, 'jours' );
        ?>
        <p class="description">Un dossier sans financeur sort automatiquement de la branche financeur : aucune enquête, aucune relance.</p>
      </div>

      <p><button type="submit" class="acdc-button acdc-button-primary">Enregistrer</button></p>
    </form>
    <?php
  }

  /**
   * ACDC 3.25.202 — Voir avant de supprimer.
   *
   * L'écran montre chaque ligne suspecte avec son motif, sa séance et ses deux
   * dates — celle de la signature et celle de la création de la séance. C'est la
   * confrontation de ces deux dates qui prouve l'anomalie, et elle doit être
   * lisible par David avant qu'il ne décide, pas après.
   *
   * La suppression prend une sauvegarde AVANT d'agir. Les tables d'émargement
   * sont entrées dans le périmètre de sauvegarde en 3.25.192 : c'est ce qui rend
   * cette suppression réversible, et c'est la seule raison pour laquelle elle est
   * proposée ici.
   */
  private function acdc_wf_render_orphan_emargements() {
    $orphans = $this->acdc_wf_orphan_emargement_rows();

    if ( empty( $orphans ) ) {
      echo '<div class="acdc-panel"><p><strong>Aucune signature orpheline détectée.</strong> '
        . 'Sont recherchées les signatures antérieures à la création de leur séance, et celles rattachées '
        . 'à un apprenant qui n\'existe plus au répertoire.</p></div>';
      return;
    }
    ?>
    <div class="acdc-alert acdc-alert-warning">
      <strong><?php echo count( $orphans ); ?> signature(s) orpheline(s).</strong>
      Ces lignes portent une signature impossible : antérieure à la création de la séance, ou attribuée
      à un apprenant absent du répertoire. Elles proviennent d'identifiants de séance réutilisés après une
      suppression totale — un cas que la version 3.25.192 empêche désormais, mais qui laisse ces traces
      derrière lui.
    </div>
    <div class="acdc-panel">
      <table class="acdc-table">
        <thead>
          <tr><th>Signataire</th><th>Séance</th><th>Date de la séance</th><th>Séance créée le</th><th>Signée le</th><th>Motif</th></tr>
        </thead>
        <tbody>
        <?php foreach ( $orphans as $row ) : ?>
          <tr>
            <td><strong><?php echo esc_html( (string) $row->learner_name ); ?></strong></td>
            <td><?php echo esc_html( (string) ( $row->session_title ?: 'Séance n°' . (int) $row->session_id ) ); ?></td>
            <td><?php echo $row->session_date ? esc_html( mysql2date( 'd/m/Y', $row->session_date ) ) : '—'; ?></td>
            <td><?php echo $row->session_created_at ? esc_html( mysql2date( 'd/m/Y H:i', $row->session_created_at ) ) : '—'; ?></td>
            <td><?php echo $row->signed_at ? esc_html( mysql2date( 'd/m/Y H:i', $row->signed_at ) ) : '—'; ?></td>
            <td><?php echo esc_html( implode( ' ; ', (array) $row->orphan_reasons ) ); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px">
        <?php wp_nonce_field( 'acdc_wf_purge_orphan_emargements' ); ?>
        <input type="hidden" name="action" value="acdc_wf_purge_orphan_emargements">
        <p>
          <label>
            <input type="checkbox" name="confirm" value="1" required>
            Je confirme la suppression définitive de ces <?php echo count( $orphans ); ?> ligne(s) de signature.
          </label>
        </p>
        <p class="description">Une sauvegarde complète est prise automatiquement juste avant la suppression. Seules les lignes listées ci-dessus sont supprimées ; les feuilles et les séances ne sont pas touchées.</p>
        <p><button type="submit" class="acdc-button acdc-button-primary">Supprimer les signatures orphelines</button></p>
      </form>
    </div>
    <?php
  }

  private function acdc_wf_number_row( $label, $key, $delays, $unit ) {
    $value = isset( $delays[ $key ] ) ? $delays[ $key ] : 0;
    ?>
    <div class="acdc-contract-grid">
      <div class="acdc-contract-label"><?php echo esc_html( $label ); ?></div>
      <div>
        <input type="number" min="0" step="1" name="wf[delays][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $value ); ?>" style="max-width:8rem">
        <span class="description"><?php echo esc_html( $unit ); ?></span>
      </div>
    </div>
    <?php
  }

  private function acdc_wf_list_row( $label, $key, $delays, $unit ) {
    $value = isset( $delays[ $key ] ) && is_array( $delays[ $key ] ) ? implode( ', ', $delays[ $key ] ) : '';
    ?>
    <div class="acdc-contract-grid">
      <div class="acdc-contract-label"><?php echo esc_html( $label ); ?></div>
      <div>
        <input type="text" name="wf[delays][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" style="max-width:12rem">
        <span class="description"><?php echo esc_html( $unit ); ?>, séparés par des virgules — laisser vide pour ne pas relancer</span>
      </div>
    </div>
    <?php
  }
}
