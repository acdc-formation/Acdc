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
      echo '<div class="acdc-alert acdc-alert-warning"><strong>Le workflow est à l\'arrêt.</strong> Aucun parcours n\'est ouvert et rien n\'est planifié. Activez-le dans la configuration.</div>';
    } elseif ( ! empty( $settings['simulation'] ) ) {
      echo '<div class="acdc-alert acdc-alert-info"><strong>Mode simulation.</strong> Les parcours sont planifiés et le journal se remplit, mais <strong>aucun e-mail ne part</strong>. Regardez le plan, ajustez les délais, puis désactivez la simulation.</div>';
    } elseif ( ! empty( $settings['test_mode'] ) ) {
      echo '<div class="acdc-alert acdc-alert-warning"><strong>Mode recette.</strong> Les envois ne partent qu\'aux adresses déclarées dans la configuration. Tout autre destinataire est refusé et journalisé.</div>';
    } else {
      echo '<div class="acdc-alert acdc-alert-success"><strong>Workflow actif.</strong> Les envois partent réellement, à tous les destinataires.</div>';
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
          $label = (string) ( $run->prospect_company ? $run->prospect_company : $run->label );
          $late  = $next && ! empty( $next->scheduled_at ) && strtotime( (string) $next->scheduled_at ) < $this->acdc_wf_now();
          ?>
          <tr>
            <td>
              <strong><?php echo esc_html( $label ); ?></strong><br>
              <span class="description">Recueil n°<?php echo (int) $run->need_id; ?></span>
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
                <?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( (string) $next->scheduled_at ) ) ); ?>
                <?php if ( $late ) : ?><br><span style="color:#b32d2e">en retard</span><?php endif; ?>
              <?php else : ?>
                <span class="description">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ( 'active' === (string) $run->status ) : ?>
                En cours
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
            <td><?php echo ! empty( $step->scheduled_at ) ? esc_html( wp_date( 'd/m/Y H:i', strtotime( (string) $step->scheduled_at ) ) ) : '<span class="description">—</span>'; ?></td>
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
      "SELECT s.*, r.label AS run_label, r.need_id
         FROM {$this->workflow_step_table} s
         INNER JOIN {$this->workflow_run_table} r ON r.id = s.run_id
        WHERE s.mode IN ('task','alert') AND s.status = 'pending' AND r.status = 'active'
        ORDER BY s.is_alert DESC, s.scheduled_at ASC
        LIMIT 200"
    );

    if ( empty( $tasks ) ) {
      echo '<div class="acdc-panel"><p>Rien à faire pour l\'instant.</p></div>';
      return;
    }
    ?>
    <div class="acdc-panel">
      <table class="acdc-table">
        <thead><tr><th></th><th>À faire</th><th>Dossier</th><th>Depuis</th><th></th></tr></thead>
        <tbody>
        <?php foreach ( $tasks as $task ) : ?>
          <tr>
            <td><?php echo (int) $task->is_alert ? '<span title="Alerte" style="color:#b32d2e">&#9888;</span>' : ''; ?></td>
            <td><strong><?php echo esc_html( $task->label ); ?></strong></td>
            <td><?php echo esc_html( $task->run_label ); ?> <span class="description">(recueil n°<?php echo (int) $task->need_id; ?>)</span></td>
            <td><?php echo ! empty( $task->scheduled_at ) ? esc_html( wp_date( 'd/m/Y', strtotime( (string) $task->scheduled_at ) ) ) : '—'; ?></td>
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
      "SELECT s.*, r.label AS run_label
         FROM {$this->workflow_step_table} s
         INNER JOIN {$this->workflow_run_table} r ON r.id = s.run_id
        WHERE s.status IN ('done','failed','skipped','cancelled')
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
            <td><?php echo ! empty( $row->executed_at ) ? esc_html( wp_date( 'd/m/Y H:i', strtotime( (string) $row->executed_at ) ) ) : '—'; ?></td>
            <td><?php echo esc_html( $row->run_label ); ?></td>
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
        $this->acdc_wf_number_row( 'Rappel d’émargement, avant chaque demi-journée', 'emargement_lead_minutes', $d, 'minutes' );
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
