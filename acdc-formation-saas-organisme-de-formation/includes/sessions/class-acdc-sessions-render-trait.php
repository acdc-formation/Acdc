<?php
/**
 * ACDC Séances — ACDC_Sessions_Render_Trait.
 *
 * Extraction micro-incrémentale du sous-bloc métier :
 * séances.
 *
 * @since 3.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Sessions_Render_Trait {

  private function render_front_sessions_validated_tab( $action, $item_id ) {
    if ( ! in_array( $action, array( 'list', 'view' ), true ) ) {
      $action = 'list';
    }

    $base_tab = 'sessions_validated';
    $base_create_url = is_admin() ? $this->admin_tab_url( 'create_session' ) : $this->portal_page_url( array( 'tab' => 'create_session' ) );
    $list_url = is_admin() ? $this->admin_tab_url( $base_tab ) : $this->portal_page_url( array( 'tab' => $base_tab ) );

    if ( 'view' === $action && $item_id ) {
      $session = $this->get_session( $item_id );
      if ( ! $session || ! empty( $session->is_draft ) || 'Brouillon' === (string) $session->status || 'Annulée' === (string) $session->status ) {
        $session = null;
      }
      if ( ! $session ) {
        wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Séance introuvable.' ), 'notice_type' => 'error' ), $list_url ) );
        exit;
      }
      global $wpdb;
      $session = $wpdb->get_row( $wpdb->prepare( "SELECT s.*, f.title AS formation_title, c.name AS company_name, g.name AS group_name, g.trainer_name AS group_trainer_name, COUNT(DISTINCT l.id) AS learner_count, MAX(l.first_name) AS learner_first_name, MAX(l.usage_last_name) AS learner_usage_last_name, MAX(l.last_name) AS learner_last_name FROM {$this->session_table} s LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id LEFT JOIN {$this->company_table} c ON c.id = s.company_id LEFT JOIN {$this->group_table} g ON g.session_id = s.id LEFT JOIN {$this->learner_table} l ON l.session_id = s.id WHERE s.id = %d GROUP BY s.id", $item_id ) );
      ?>
      <section class="acdc-section-head">
        <div>
          <h2>Séances validées</h2>
          <p>Consultez les séances validées, planifiées et rattachées aux formations.</p>
        </div>
        <div class="acdc-inline-wrap">
          <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $list_url ); ?>">Retour à la liste</a>
          <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $base_create_url ); ?>">Créer une séance</a>
        </div>
      </section>
      <div class="acdc-grid-2cols acdc-mb-18">
        <?php foreach ( $this->get_session_detail_sections( $session ) as $section ) : ?>
          <div class="acdc-panel">
            <h3><?php echo esc_html( $section['title'] ); ?></h3>
            <div class="acdc-list-details">
              <?php foreach ( $section['items'] as $label => $value ) : ?>
                <div><strong><?php echo esc_html( $label ); ?> :</strong> <?php echo nl2br( esc_html( $value ) ); ?></div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php
      return;
    }

    $filters = array(
      'search' => isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ( isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '' ),
      'id' => isset( $_GET['filter_id'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_id'] ) ) : '',
      'learner' => isset( $_GET['filter_learner'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_learner'] ) ) : '',
      'group' => isset( $_GET['filter_group'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_group'] ) ) : '',
      'format' => isset( $_GET['filter_format'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_format'] ) ) : '',
      'type' => isset( $_GET['filter_type'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_type'] ) ) : '',
      'attendance_method' => isset( $_GET['filter_attendance_method'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_attendance_method'] ) ) : '',
      'learner_signature_status' => isset( $_GET['filter_learner_signature_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_learner_signature_status'] ) ) : '',
      'presence_status' => isset( $_GET['filter_presence_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_presence_status'] ) ) : '',
      'session_date' => isset( $_GET['filter_session_date'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_session_date'] ) ) : '',
      'formation' => isset( $_GET['filter_formation'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_formation'] ) ) : '',
      'trainer' => isset( $_GET['filter_trainer'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_trainer'] ) ) : '',
    );

    $items = $this->get_validated_sessions( $filters );
    $all_items = $this->get_validated_sessions();
    $type_options = $this->get_session_type_options();
    unset( $type_options[''] );
    $format_options = $this->get_session_format_options();
    unset( $format_options[''] );
    $attendance_options = $this->get_session_attendance_options();
    unset( $attendance_options[''] );
    $signature_status_options = array( 'Complète' => 'Complète', 'Partielle' => 'Partielle', 'En attente' => 'En attente', 'Non générée' => 'Non générée' );
    $presence_status_options = array( 'Complète' => 'Complète', 'Partielle' => 'Partielle', 'Absences' => 'Absences', 'En attente' => 'En attente', 'Non générée' => 'Non générée' );
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Séances validées</h2>
      </div>
      <div class="acdc-inline-wrap">
        <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $base_create_url ); ?>">Créer une séance</a>
      </div>
    </section>
    <div class="acdc-panel acdc-mb-18">
      <form class="acdc-search-bar" method="get" action="<?php echo esc_url( $list_url ); ?>">
        <?php if ( is_admin() ) : ?>
          <input type="hidden" name="page" value="acdc-of-dashboard">
        <?php endif; ?>
        <input type="hidden" name="tab" value="sessions_validated">
        <div class="acdc-search-row">
          <input type="search" name="q" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="Rechercher">
          <button type="button" class="acdc-filter-toggle" data-acdc-filter-toggle aria-expanded="false">FILTRES <?php echo $this->render_inline_icon( 'chevron-down', 16 ); ?></button>
        </div>
        <div class="acdc-sessions-filters-panel" data-acdc-filters-panel hidden>
          <div class="acdc-sessions-filters-grid">
            <label><span>ID</span><input type="text" name="filter_id" value="<?php echo esc_attr( $filters['id'] ); ?>" placeholder="ID"></label>
            <label><span>Apprenant</span><input type="text" name="filter_learner" value="<?php echo esc_attr( $filters['learner'] ); ?>" placeholder="Apprenant"></label>
            <label><span>Groupe</span><input type="text" name="filter_group" value="<?php echo esc_attr( $filters['group'] ); ?>" placeholder="Groupe"></label>
            <label><span>Format</span><select name="filter_format"><option value="">—</option><?php foreach ( $format_options as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['format'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
            <label><span>Type de formation</span><select name="filter_type"><option value="">—</option><?php foreach ( $type_options as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['type'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
            <label><span>Méthode d’émargement</span><select name="filter_attendance_method"><option value="">—</option><?php foreach ( $attendance_options as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['attendance_method'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
            <label><span>Statut signatures apprenants</span><select name="filter_learner_signature_status"><option value="">—</option><?php foreach ( $signature_status_options as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['learner_signature_status'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
            <label><span>Statut présence</span><select name="filter_presence_status"><option value="">—</option><?php foreach ( $presence_status_options as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['presence_status'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
            <label><span>Date de la séance</span><input type="date" name="filter_session_date" value="<?php echo esc_attr( $filters['session_date'] ); ?>"></label>
            <label><span>Formations</span><input type="text" name="filter_formation" value="<?php echo esc_attr( $filters['formation'] ); ?>" placeholder="Rechercher une formation..."></label>
            <label><span>Formateurs</span><input type="text" name="filter_trainer" value="<?php echo esc_attr( $filters['trainer'] ); ?>" placeholder="Rechercher un formateur..."></label>
          </div>
          <div class="acdc-inline-wrap" style="justify-content:flex-end;margin-top:14px;">
            <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $list_url ); ?>">Réinitialiser</a>
            <button type="submit" class="acdc-button acdc-button-primary">Appliquer</button>
          </div>
        </div>
      </form>
    </div>
    <div class="acdc-panel">
      <?php if ( empty( $items ) ) : ?>
        <div class="acdc-empty-state" style="padding:72px 24px;text-align:center;">
          <div style="font-size:54px;line-height:1;color:#DCE4EC;margin-bottom:14px;">🗓️</div>
          <p style="margin:0 0 18px;color:#1E4777;">Aucune séance validée ne correspond aux critères demandés.</p>
          <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $base_create_url ); ?>">Créer une séance</a>
        </div>
      <?php else : ?>
        <div class="acdc-table-wrap">
          <table class="acdc-table acdc-table-sessions-validated" data-acdc-table-id="sessions-validated-list">
            <thead>
              <tr>
                <th><span class="screen-reader-text">Sélection</span></th>
                <th>Apprenant/Groupe</th>
                <th>Formation</th>
                <th>Formateur</th>
                <th>Format</th>
                <th>Méthode d’émargement</th>
                <th>Date et heures de la séance</th>
                <th>Lieu</th>
                <th>Signature formateur</th>
                <th>Signature(s) apprenant(s)</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php
            // Anti N+1 : préchargement de l'émargement de toutes les séances de la page (2 requêtes).
            $emarg_by_session_s = array();
            $emarg_lrns_by_id_s = array();
            if ( class_exists( 'ACDC_Emargement' ) ) {
                $emarg_core_s = ACDC_Emargement::get_instance()->core;
                $sess_ids_s = array();
                foreach ( $items as $entry ) { $sess_ids_s[] = (int) $entry->id; }
                $emarg_by_session_s = $emarg_core_s->get_by_session_ids( $sess_ids_s );
                if ( ! empty( $emarg_by_session_s ) ) {
                    $emarg_ids_s = array();
                    foreach ( $emarg_by_session_s as $em_s ) { $emarg_ids_s[] = (int) $em_s->id; }
                    $emarg_lrns_by_id_s = $emarg_core_s->get_learners_for_emarg_ids( $emarg_ids_s );
                }
            }
            ?>
            <?php foreach ( $items as $entry ) : ?>
              <?php list( $view_url, $edit_url, $delete_url ) = $this->get_session_row_action_links( $entry, $base_tab ); ?>
              <tr>
                <td><input type="checkbox" aria-label="Sélectionner cette séance"></td>
                <td>
                  <div><?php echo esc_html( $entry->learner_or_group_label ); ?></div>
                  <?php if ( ! empty( $entry->title ) ) : ?><small><?php echo esc_html( $entry->title ); ?></small><?php endif; ?>
                </td>
                <td>
                  <div><?php echo esc_html( ! empty( $entry->formation_title ) ? $entry->formation_title : '—' ); ?></div>
                  <?php if ( ! empty( $entry->formation_code ) ) : ?><small><?php echo esc_html( $entry->formation_code ); ?></small><?php endif; ?>
                </td>
                <td><?php echo esc_html( $entry->trainer_display_name ); ?></td>
                <td><?php echo esc_html( ! empty( $entry->session_format ) ? $entry->session_format : '—' ); ?></td>
                <td><?php echo esc_html( ! empty( $entry->attendance_method ) ? $entry->attendance_method : '—' ); ?></td>
                <td><?php echo esc_html( $this->get_session_datetime_label( $entry ) ); ?></td>
                <td><?php echo esc_html( $entry->location_display ); ?></td>
                <?php
                $emarg_ses_s = isset( $emarg_by_session_s[ (int) $entry->id ] ) ? $emarg_by_session_s[ (int) $entry->id ] : null;
                $t_status_s = $emarg_ses_s ? $emarg_ses_s->trainer_status : 'none';
                ?>
                <td>
                  <?php if ( 'signe' === $t_status_s ) : ?>
                    <div style="text-align:center">
                      <span class="acdc-status-pill acdc-status-pill-success">Signé</span>
                      <?php if ( $emarg_ses_s->trainer_signed_at ) : ?>
                      <div style="font-size:11px;color:#4b5d76;margin-top:2px"><?php echo esc_html( mysql2date( 'H\hi', $emarg_ses_s->trainer_signed_at ) ); ?></div>
                      <?php endif; ?>
                      <?php if ( $emarg_ses_s->trainer_sig_url ) : ?>
                      <img src="<?php echo esc_url( $emarg_ses_s->trainer_sig_url ); ?>" style="max-width:80px;max-height:36px;display:block;margin:4px auto 0;border:1px solid #e2e6ea;border-radius:4px" alt="Signature">
                      <?php endif; ?>
                      <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_emarg_download_pdf&session_id=' . (int) $entry->id ), 'acdc_emarg_pdf_' . (int) $entry->id ) ); ?>"
                         class="acdc-button acdc-button-soft acdc-button-sm" style="display:inline-block;margin-top:6px;font-size:11px;padding:3px 9px;text-decoration:none" title="Télécharger la feuille d'émargement PDF">
                        📄 PDF
                      </a>
                    </div>
                  <?php elseif ( 'none' === $t_status_s || ! $emarg_ses_s ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="margin:0">
                      <?php wp_nonce_field( 'acdc_emarg_send_trainer_' . (int) $entry->id ); ?>
                      <input type="hidden" name="action" value="acdc_emarg_send_trainer">
                      <input type="hidden" name="session_id" value="<?php echo esc_attr( $entry->id ); ?>">
                      <button type="submit" class="acdc-button acdc-button-soft acdc-button-sm" style="font-size:12px;padding:4px 10px">✉ Envoyer</button>
                    </form>
                  <?php else : ?>
                    <span class="acdc-status-pill" style="background:#cfe2ff;color:#084298">En attente</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php
                  if ( $emarg_ses_s ) :
                    $emarg_lrns_s = isset( $emarg_lrns_by_id_s[ (int) $emarg_ses_s->id ] ) ? $emarg_lrns_by_id_s[ (int) $emarg_ses_s->id ] : array();
                    foreach ( $emarg_lrns_s as $el_s ) :
                      $is_signed_s = 'signe' === $el_s->status;
                      $is_absent_s = 'absent' === $el_s->status;
                      $is_late_s   = $is_signed_s && (int) $el_s->late_minutes > 0;
                      if ( $is_absent_s ) { $pill_bg_s = '#f8d7da'; $pill_c_s = '#721c24'; $pill_txt_s = 'Absent'; }
                      elseif ( $is_late_s ) { $pill_bg_s = '#fff3cd'; $pill_c_s = '#856404'; $pill_txt_s = 'Retard ' . $el_s->late_minutes . 'min'; }
                      elseif ( $is_signed_s ) { $pill_bg_s = '#d4edda'; $pill_c_s = '#155724'; $pill_txt_s = 'Présent'; }
                      else { $pill_bg_s = '#e2e3e5'; $pill_c_s = '#383d41'; $pill_txt_s = 'En attente'; }
                  ?>
                  <div style="margin-bottom:4px">
                    <span style="font-size:11px;font-weight:600;color:#1a2744"><?php echo esc_html( $el_s->learner_name ); ?></span>
                    <span style="display:inline-block;padding:1px 7px;border-radius:10px;font-size:11px;font-weight:700;background:<?php echo esc_attr( $pill_bg_s ); ?>;color:<?php echo esc_attr( $pill_c_s ); ?>;margin-left:4px"><?php echo esc_html( $pill_txt_s ); ?></span>
                    <?php if ( $is_signed_s && $el_s->signed_at ) : ?>
                    <span style="font-size:10px;color:#6b7280;margin-left:4px"><?php echo esc_html( mysql2date( 'H\hi', $el_s->signed_at ) ); ?></span>
                    <?php endif; ?>
                    <?php if ( $is_signed_s && $el_s->sig_url ) : ?>
                    <img src="<?php echo esc_url( $el_s->sig_url ); ?>" style="max-width:60px;max-height:26px;vertical-align:middle;margin-left:4px;border:1px solid #e2e6ea;border-radius:3px" alt="">
                    <?php endif; ?>
                  </div>
                  <?php endforeach; ?>
                  <?php else : ?>
                  <a href="<?php echo esc_url( $view_url ); ?>" class="acdc-table-inline-link"><?php echo esc_html( ! empty( $entry->learner_signature_label ) ? $entry->learner_signature_label : 'Voir détails' ); ?></a>
                  <?php endif; ?>
                </td>
                <td class="acdc-actions-cell-icons acdc-sessions-actions-cell">
                  <div class="acdc-sessions-actions-inline">
                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="margin:0">
                      <?php wp_nonce_field( 'acdc_emarg_send_trainer_' . (int) $entry->id ); ?>
                      <input type="hidden" name="action" value="acdc_emarg_send_trainer">
                      <input type="hidden" name="session_id" value="<?php echo esc_attr( $entry->id ); ?>">
                      <button type="submit" class="acdc-row-action-icon" title="Envoyer l'email d'émargement au formateur" aria-label="Envoyer l'email d'émargement au formateur">
                        <?php echo $this->render_inline_icon( 'convocation', 25 ); ?>
                      </button>
                    </form>
                    <a class="acdc-row-action-icon acdc-row-view-link" href="<?php echo esc_url( $view_url ); ?>" title="Voir" aria-label="Voir la séance">
                      <?php echo $this->render_inline_icon( 'eye', 25 ); ?>
                    </a>
                    <a class="acdc-row-action-icon acdc-row-edit-link" href="<?php echo esc_url( $edit_url ); ?>" title="Modifier" aria-label="Modifier la séance">
                      <?php echo $this->render_inline_icon( 'edit', 25 ); ?>
                    </a>
                    <a class="acdc-row-action-icon acdc-row-delete-link" href="<?php echo esc_url( $delete_url ); ?>" title="Supprimer" aria-label="Supprimer la séance" onclick="return confirm('Supprimer cette séance ?');">
                      <?php echo $this->render_inline_icon( 'trash', 25 ); ?>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <style>
      .acdc-search-row{display:flex;align-items:center;justify-content:space-between;gap:18px}.acdc-filter-toggle{display:inline-flex;align-items:center;gap:8px;border:none;background:transparent;color:#1E4777;font-size:12px;font-weight:600;letter-spacing:.03em;cursor:pointer}.acdc-sessions-filters-panel{margin-top:18px;padding:18px 22px;background:#dfe8f0;border-radius:10px}.acdc-sessions-filters-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.acdc-sessions-filters-grid label{display:flex;flex-direction:column;gap:8px;font-size:12px;color:#1E4777;font-weight:600;text-transform:uppercase}.acdc-sessions-filters-grid input,.acdc-sessions-filters-grid select{width:100%}..acdc-table-sessions-validated th:last-child,.acdc-table-sessions-validated td:last-child{text-align:center}.acdc-table-sessions-validated th{white-space:normal!important;word-break:break-word!important;overflow:visible!important}.acdc-status-pill{display:inline-flex;align-items:center;justify-content:center;padding:6px 12px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:.03em}.acdc-status-pill-success{background:#d9f3e5;color:#35b37e}.acdc-table-inline-link{display:inline-flex;align-items:center;gap:6px;color:#1E4777;text-decoration:none}.acdc-sessions-actions-inline{display:inline-flex;align-items:center;gap:4px;flex-wrap:nowrap}.acdc-table-sessions-validated small{display:block;margin-top:4px;color:#1E4777}@media (max-width:1200px){.acdc-sessions-filters-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media (max-width:900px){.acdc-search-row{flex-direction:column;align-items:stretch}.acdc-sessions-filters-grid{grid-template-columns:1fr}}
    </style>
    <script>
      (function(){
        var toggle = document.querySelector('[data-acdc-filter-toggle]');
        var panel = document.querySelector('[data-acdc-filters-panel]');
        if(toggle && panel){
          toggle.addEventListener('click', function(){
            var open = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
            panel.hidden = open;
          });
          <?php if ( array_filter( $filters ) ) : ?>
          toggle.setAttribute('aria-expanded', 'true');
          panel.hidden = false;
          <?php endif; ?>
        }
      })();
    </script>
    <?php
  }

  private function render_front_sessions_calendar_tab() {
    $year = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : (int) current_time( 'Y' );
    $month = isset( $_GET['month'] ) ? absint( $_GET['month'] ) : (int) current_time( 'n' );
    if ( $month < 1 || $month > 12 ) {
      $month = (int) current_time( 'n' );
    }
    if ( $year < 2000 || $year > 2100 ) {
      $year = (int) current_time( 'Y' );
    }

    $first_day_ts = gmmktime( 12, 0, 0, $month, 1, $year );
    $days_in_month = (int) gmdate( 't', $first_day_ts );
    $first_weekday = (int) gmdate( 'N', $first_day_ts );
    $grid_start_ts = strtotime( '-' . ( $first_weekday - 1 ) . ' days', $first_day_ts );
    $grid_end_ts   = strtotime( '+41 days', $grid_start_ts );
    $today_key     = current_time( 'Y-m-d' );

    $sessions = $this->get_sessions_for_calendar( $year, $month );
    $events_by_day = array();
    foreach ( $sessions as $session ) {
      $start = ! empty( $session->start_at ) ? gmdate( 'Y-m-d', strtotime( $session->start_at ) ) : ( ! empty( $session->start_date ) ? $session->start_date : $session->end_date );
      $end   = ! empty( $session->end_at ) ? gmdate( 'Y-m-d', strtotime( $session->end_at ) ) : ( ! empty( $session->end_date ) ? $session->end_date : $session->start_date );
      if ( empty( $start ) ) {
        continue;
      }
      if ( empty( $end ) || $end < $start ) {
        $end = $start;
      }
      $loop_start = max( strtotime( $start . ' 00:00:00' ), strtotime( gmdate( 'Y-m-d 00:00:00', $grid_start_ts ) ) );
      $loop_end   = min( strtotime( $end . ' 00:00:00' ), strtotime( gmdate( 'Y-m-d 00:00:00', $grid_end_ts ) ) );
      for ( $day_ts = $loop_start; $day_ts <= $loop_end; $day_ts = strtotime( '+1 day', $day_ts ) ) {
        $day_key = gmdate( 'Y-m-d', $day_ts );
        if ( ! isset( $events_by_day[ $day_key ] ) ) {
          $events_by_day[ $day_key ] = array();
        }
        $events_by_day[ $day_key ][] = $session;
      }
    }

    $prev_ts  = strtotime( '-1 month', $first_day_ts );
    $next_ts  = strtotime( '+1 month', $first_day_ts );
    $prev_url = is_admin() ? $this->admin_tab_url( 'sessions_calendar', array( 'month' => gmdate( 'n', $prev_ts ), 'year' => gmdate( 'Y', $prev_ts ) ) ) : $this->portal_page_url( array( 'tab' => 'sessions_calendar', 'month' => gmdate( 'n', $prev_ts ), 'year' => gmdate( 'Y', $prev_ts ) ) );
    $next_url = is_admin() ? $this->admin_tab_url( 'sessions_calendar', array( 'month' => gmdate( 'n', $next_ts ), 'year' => gmdate( 'Y', $next_ts ) ) ) : $this->portal_page_url( array( 'tab' => 'sessions_calendar', 'month' => gmdate( 'n', $next_ts ), 'year' => gmdate( 'Y', $next_ts ) ) );
    $today_url = is_admin() ? $this->admin_tab_url( 'sessions_calendar', array( 'month' => current_time( 'n' ), 'year' => current_time( 'Y' ) ) ) : $this->portal_page_url( array( 'tab' => 'sessions_calendar', 'month' => current_time( 'n' ), 'year' => current_time( 'Y' ) ) );
    $sessions_url = is_admin() ? $this->admin_tab_url( 'sessions' ) : $this->portal_page_url( array( 'tab' => 'sessions' ) );
    ?>
    <section class="acdc-section-head acdc-calendar-head">
      <div>
        <h2>Calendrier des séances</h2>
        <p>Ce calendrier affiche uniquement les dates des séances de formation enregistrées.</p>
      </div>
      <div class="acdc-inline-wrap">
        <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $sessions_url ); ?>">Voir les séances</a>
        <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( is_admin() ? $this->admin_tab_url( 'create_session' ) : $this->portal_page_url( array( 'tab' => 'create_session' ) ) ); ?>">Créer une séance</a>
      </div>
    </section>

    <div class="acdc-calendar-toolbar acdc-panel">
      <div class="acdc-calendar-nav-buttons">
        <a class="acdc-calendar-nav-button" href="<?php echo esc_url( $prev_url ); ?>" aria-label="Mois précédent">&#171;</a>
        <a class="acdc-calendar-nav-button" href="<?php echo esc_url( $today_url ); ?>" aria-label="Mois en cours">•</a>
        <a class="acdc-calendar-nav-button" href="<?php echo esc_url( $next_url ); ?>" aria-label="Mois suivant">&#187;</a>
      </div>
      <h3 class="acdc-calendar-title">Calendrier des séances - <?php echo esc_html( $this->get_calendar_month_label( $first_day_ts ) ); ?></h3>
    </div>

    <div class="acdc-calendar-board acdc-panel">
      <div class="acdc-calendar-weekdays">
        <?php foreach ( $this->build_calendar_day_names() as $day_name ) : ?>
          <div class="acdc-calendar-weekday"><?php echo esc_html( $day_name ); ?></div>
        <?php endforeach; ?>
      </div>
      <div class="acdc-calendar-grid">
        <?php for ( $day_ts = $grid_start_ts; $day_ts <= $grid_end_ts; $day_ts = strtotime( '+1 day', $day_ts ) ) : ?>
          <?php
          $day_key = gmdate( 'Y-m-d', $day_ts );
          $is_current_month = (int) gmdate( 'n', $day_ts ) === $month;
          $is_today = $day_key === $today_key;
          $day_events = isset( $events_by_day[ $day_key ] ) ? $events_by_day[ $day_key ] : array();
          $classes = array( 'acdc-calendar-day', 'acdc-calendar-day-sessions' );
          if ( ! $is_current_month ) {
            $classes[] = 'is-outside';
          }
          if ( $is_today ) {
            $classes[] = 'is-today';
          }
          if ( ! empty( $day_events ) ) {
            $classes[] = 'has-events';
          }
          ?>
          <div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
            <div class="acdc-calendar-day-number"><?php echo esc_html( gmdate( 'j', $day_ts ) ); ?></div>
            <?php if ( ! empty( $day_events ) ) : ?>
              <div class="acdc-calendar-events">
                <?php foreach ( $day_events as $event ) : ?>
                  <?php
                  $event_url = is_admin() ? $this->admin_tab_url( 'sessions', array( 'action' => 'edit', 'item_id' => (int) $event->id ) ) : $this->portal_page_url( array( 'tab' => 'sessions', 'action' => 'edit', 'item_id' => (int) $event->id ) );
                  $event_title = ! empty( $event->title ) ? $event->title : ( ! empty( $event->formation_title ) ? $event->formation_title : 'Séance de formation' );
                  $meta_parts = array();
                  if ( ! empty( $event->formation_title ) && $event->formation_title !== $event_title ) {
                    $meta_parts[] = $event->formation_title;
                  }
                  if ( ! empty( $event->company_name ) ) {
                    $meta_parts[] = $event->company_name;
                  }
                  if ( ! empty( $event->location ) ) {
                    $meta_parts[] = $event->location;
                  }
                  ?>
                  <a class="acdc-calendar-event acdc-calendar-event-session" href="<?php echo esc_url( $event_url ); ?>">
                    <span class="acdc-calendar-event-time"><?php echo esc_html( ! empty( $event->start_date ) && ! empty( $event->end_date ) && $event->start_date !== $event->end_date ? mysql2date( 'd/m', $event->start_date ) . ' → ' . mysql2date( 'd/m', $event->end_date ) : mysql2date( 'd/m/Y', ! empty( $event->start_date ) ? $event->start_date : $event->end_date ) ); ?></span>
                    <span class="acdc-calendar-event-title"><?php echo esc_html( $event_title ); ?></span>
                    <?php if ( ! empty( $meta_parts ) ) : ?>
                      <span class="acdc-calendar-event-meta"><?php echo esc_html( implode( ' • ', $meta_parts ) ); ?></span>
                    <?php endif; ?>
                    <?php if ( ! empty( $event->status ) ) : ?>
                      <span class="acdc-calendar-event-meta"><?php echo esc_html( $event->status ); ?></span>
                    <?php endif; ?>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endfor; ?>
      </div>
    </div>
    <?php
  }

  private function render_front_create_session_tab() {
    global $wpdb;

    /* ── Pré-remplissage depuis une proposition ── */
    $from_proposal_id       = isset( $_GET['proposal_id'] ) ? absint( wp_unslash( $_GET['proposal_id'] ) ) : 0;
    $prefill_formation_id   = 0;
    $prefill_location       = '';
    $prefill_seance_dates   = array(); // tableau de dates ISO YYYY-MM-DD
    $prefill_proposal_label = '';

    if ( $from_proposal_id && method_exists( $this, 'get_proposal' ) ) {
      $src_proposal = $this->get_proposal( $from_proposal_id );
      if ( $src_proposal ) {
        $prefill_formation_id   = (int) ( $src_proposal->formation_id ?? 0 );
        $prefill_location       = sanitize_text_field( (string) ( $src_proposal->formation_location ?? '' ) );
        $prefill_proposal_label = sanitize_text_field( (string) ( $src_proposal->formation_title ?? '' ) );
        // Extraire les dates ISO depuis formation_dates
        $raw_dates = (string) ( $src_proposal->formation_dates ?? '' );
        foreach ( array_filter( array_map( 'trim', explode( ',', $raw_dates ) ) ) as $d ) {
          if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
            $prefill_seance_dates[] = $d;
          }
        }
      }
    }
    $prefill_dates_json = wp_json_encode( $prefill_seance_dates );
    $base_url = is_admin() ? $this->admin_tab_url( 'create_session' ) : $this->portal_page_url( array( 'tab' => 'create_session' ) );
    $type_options = $this->get_session_type_options();
    $attendance_options = $this->get_session_attendance_options();
    $format_options = $this->get_session_format_options();
    $formations   = $this->get_formations();
    $trainers     = $this->get_trainers();
    usort( $trainers, function( $a, $b ) {
      return strcmp( strtolower( $a->last_name . ' ' . $a->first_name ), strtolower( $b->last_name . ' ' . $b->first_name ) );
    });
    // Apprenants : id, first_name, last_name, usage_last_name, company_name
    $learners_raw = $wpdb->get_results(
      "SELECT l.id, l.first_name, l.last_name, l.usage_last_name, c.name AS company_name
       FROM {$this->learner_table} l
       LEFT JOIN {$this->company_table} c ON c.id = l.company_id
       ORDER BY l.last_name ASC, l.first_name ASC LIMIT 2000"
    );
    // Groupes : id, name, formation_title, learner_ids
    $groups_raw = $wpdb->get_results(
      "SELECT g.id, g.name, f.title AS formation_title, g.learner_ids
       FROM {$this->group_table} g
       LEFT JOIN {$this->formation_table} f ON f.id = g.formation_id
       ORDER BY g.name ASC LIMIT 500"
    );

    // Résolution noms apprenants par groupe pour le JS
    $groups_learners_map = array();
    if ( ! empty( $groups_raw ) ) {
      $all_learner_ids_needed = array();
      foreach ( $groups_raw as $g ) {
        if ( ! empty( $g->learner_ids ) ) {
          $ids = array_filter( array_map( 'absint', explode( ',', $g->learner_ids ) ) );
          foreach ( $ids as $id ) { $all_learner_ids_needed[ $id ] = true; }
        }
      }
      if ( ! empty( $all_learner_ids_needed ) ) {
        $placeholders = implode( ',', array_fill( 0, count( $all_learner_ids_needed ), '%d' ) );
        $learner_names_map = array();
        $rows = $wpdb->get_results( $wpdb->prepare(
          "SELECT id, first_name, usage_last_name FROM {$this->learner_table} WHERE id IN ($placeholders)",
          array_keys( $all_learner_ids_needed )
        ) );
        foreach ( $rows as $r ) {
          $learner_names_map[ (int) $r->id ] = trim( $r->first_name . ' ' . $r->usage_last_name );
        }
        foreach ( $groups_raw as $g ) {
          if ( ! empty( $g->learner_ids ) ) {
            $ids = array_filter( array_map( 'absint', explode( ',', $g->learner_ids ) ) );
            $names = array();
            foreach ( $ids as $id ) {
              if ( isset( $learner_names_map[ $id ] ) ) { $names[] = $learner_names_map[ $id ]; }
            }
            if ( ! empty( $names ) ) {
              $groups_learners_map[ (int) $g->id ] = $names;
            }
          }
        }
      }
    }
    ?>
    <section class="acdc-section-head">
      <div><h2>Créer Séance</h2></div>
    </section>
    <?php if ( $from_proposal_id && ! empty( $prefill_seance_dates ) ) : ?>
    <div class="acdc-alert acdc-alert-success" style="margin-bottom:16px;">
      📅 <strong><?php echo (int) count( $prefill_seance_dates ); ?> séance(s)</strong> pré-remplies depuis la proposition
      <?php if ( $prefill_proposal_label ) : ?>« <?php echo esc_html( $prefill_proposal_label ); ?> »<?php endif; ?>.
      Vérifiez les horaires avant de valider.
    </div>
    <?php endif; ?>
    <form class="acdc-form acdc-contract-builder-form acdc-create-session-builder" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'acdc_save_session_builder' ); ?>
      <input type="hidden" name="action" value="acdc_save_session_builder">
      <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
      <div class="acdc-panel acdc-mb-18">
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">&nbsp;</div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Intitulé de la séance</div>
          <div>
            <input type="text" name="session_builder[title]" value="" placeholder="Intitulé de la séance">
            <p class="acdc-registration-help">Titre facultatif pour la session. Il sera inclus dans les e-mails envoyés aux apprenants.</p>
          </div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Type de séance <span class="acdc-required">*</span></div>
          <div>
            <select name="session_builder[session_type]" required>
              <?php foreach ( $type_options as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Formation</div>
          <div>
            <select name="session_builder[formation_id]">
              <option value="0">— Aucune formation —</option>
              <?php foreach ( $formations as $f ) : ?>
                <option value="<?php echo esc_attr( $f->id ); ?>" <?php selected( (int) $f->id, $prefill_formation_id ); ?>><?php echo esc_html( $this->format_formation_option_label( $f ) ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Formateur</div>
          <div>
            <select name="session_builder[trainer_id]">
              <option value="0">— Aucun formateur désigné —</option>
              <?php foreach ( $trainers as $tr ) :
                $tr_label = trim( (string) $tr->first_name . ' ' . (string) $tr->last_name );
                if ( '' === $tr_label ) { $tr_label = '(sans nom)'; }
              ?>
                <option value="<?php echo esc_attr( $tr->id ); ?>"><?php echo esc_html( $tr_label ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="acdc-contract-grid acdc-session-field-individuelle" style="display:none">
          <div class="acdc-contract-label">Apprenant</div>
          <div>
            <select name="session_builder[learner_id]">
              <option value="0">— Sélectionner un apprenant —</option>
              <?php foreach ( $learners_raw as $l ) :
                $ln = ! empty( $l->usage_last_name ) ? $l->usage_last_name : $l->last_name;
                $label = trim( $l->first_name . ' ' . $ln );
                if ( ! empty( $l->company_name ) ) { $label .= ' — ' . $l->company_name; }
              ?>
                <option value="<?php echo esc_attr( $l->id ); ?>"><?php echo esc_html( $label ); ?></option>
              <?php endforeach; ?>
            </select>
            <p class="acdc-registration-help">L'apprenant sera automatiquement rattaché à la séance créée.</p>
          </div>
        </div>
        <div class="acdc-contract-grid acdc-session-field-groupe" style="display:none">
          <div class="acdc-contract-label">Groupe</div>
          <div>
            <select name="session_builder[group_id]" id="acdc-session-group-select">
              <option value="0">— Sélectionner un groupe —</option>
              <?php foreach ( $groups_raw as $g ) :
                $g_label = $g->name;
                if ( ! empty( $g->formation_title ) ) { $g_label .= ' — ' . $g->formation_title; }
              ?>
                <option value="<?php echo esc_attr( $g->id ); ?>"><?php echo esc_html( $g_label ); ?></option>
              <?php endforeach; ?>
            </select>
            <p class="acdc-registration-help">Le groupe sera automatiquement rattaché à la séance créée.</p>
            <div id="acdc-session-group-learners" style="display:none;margin-top:10px;padding:12px 14px;background:#f8f9fa;border:1px solid #e2e6ea;border-radius:8px;">
              <p style="font-size:12px;font-weight:700;color:#0f2c52;margin:0 0 8px">👥 Apprenants du groupe :</p>
              <ul id="acdc-session-group-learners-list" style="margin:0;padding:0 0 0 16px;font-size:13px;color:#1a2744;line-height:1.8"></ul>
            </div>
          </div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Dates personnalisées <span class="acdc-required">*</span></div>
          <div>
            <div class="acdc-session-dates-builder">
              <div class="acdc-session-dates-head">
                <span>AJOUTER UNE NOUVELLE DATE</span>
                <button type="button" class="acdc-session-add-date" data-add-session-date aria-label="Ajouter une date">+</button>
              </div>
              <div class="acdc-session-dates-list" data-session-dates-list></div>
            </div>
            <p class="acdc-registration-help">Chaque journée est divisée en <strong>Matin (09h00–12h30)</strong> et <strong>Après-midi (13h30–17h00)</strong> pour la conformité Qualiopi. Les horaires sont ajustables. Cliquez + pour ajouter d'autres journées.</p>
          </div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Méthode d'émargement</div>
          <div>
            <select name="session_builder[attendance_method]">
              <?php foreach ( $attendance_options as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, 'Électronique' ); ?>><?php echo esc_html( $label ); ?></option>
              <?php endforeach; ?>
            </select>
            <div class="acdc-session-help-block">
              <p><strong>Signature manuelle :</strong><br>Les feuilles d'émargement signées seront à ajouter pour chaque séance de formation.</p>
              <p><strong>Signature électronique :</strong><br>Les demandes de signature électronique des feuilles d'émargement seront à envoyer pour chaque séance de formation.</p>
              <p>Vous pouvez envoyer les demandes de signature électronique ou ajouter la feuille d'émargement signée à partir du début de la séance.</p>
              <p><strong>À noter :</strong> Les feuilles d'émargement sont à signer par demi-journée de formation (matin : 00h00 à 13h00, après-midi : 13h00 à 00h00).<br>Exemple : si vous déclarez une séance de cours de 11h00 à 14h00, vous devrez donc demander de signer une feuille d'émargement (ou ajouter une feuille d'émargement signée) pour le matin et une seconde pour l'après-midi.</p>
            </div>
          </div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Format de la séance</div>
          <div>
            <select name="session_builder[session_format]" id="acdc-session-format-select">
              <?php foreach ( $format_options as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, 'Présentiel' ); ?>><?php echo esc_html( $label ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="acdc-contract-grid acdc-session-field-presentiel">
          <div class="acdc-contract-label">Lieu</div>
          <div>
            <input type="text" name="session_builder[location]" value="<?php echo esc_attr( $prefill_location ); ?>" placeholder="ex. Salle 3, Centre de formation Cogolin">
            <p class="acdc-registration-help">Lieu de la séance — affiché dans les feuilles d'émargement et les convocations.</p>
          </div>
        </div>
        <div class="acdc-contract-grid acdc-session-field-distanciel" style="display:none">
          <div class="acdc-contract-label">Lien visio</div>
          <div>
            <input type="url" name="session_builder[remote_link]" value="" placeholder="https://teams.microsoft.com/... ou meet.google.com/... ou zoom.us/...">
            <p class="acdc-registration-help">Lien Teams, Google Meet ou Zoom — envoyé aux apprenants dans les convocations.</p>
          </div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Note formateur / admin</div>
          <div>
            <textarea name="session_builder[notes]" rows="5" placeholder="Note formateur / admin"></textarea>
            <p class="acdc-registration-help">Cette zone est réservée à l'équipe pédagogique et ne sera pas partagée aux apprenants.</p>
          </div>
        </div>
      </div>
      <div class="acdc-panel">
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label"><strong>Brouillon</strong></div>
          <div>
            <label class="acdc-session-toggle"><input type="checkbox" name="session_builder[is_draft]" value="1"><span class="acdc-session-toggle-ui" aria-hidden="true"></span><span class="screen-reader-text">Sauvegarder en tant que brouillon</span></label>
            <p class="acdc-registration-help">Activez cette option si vous souhaitez sauvegarder les champs déjà remplis et reprendre la création de la séance plus tard.<br>Vous retrouverez votre brouillon dans le sous-menu "En cours de validation".</p>
          </div>
        </div>
      </div>
      <p class="acdc-actions-end">
        <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( is_admin() ? $this->admin_tab_url( 'sessions_calendar' ) : $this->portal_page_url( array( 'tab' => 'sessions_calendar' ) ) ); ?>">Annuler</a>
        <button type="submit" name="save_and_add" value="1" class="acdc-button acdc-button-primary">Créer & ajouter un autre</button>
        <button type="submit" class="acdc-button acdc-button-primary">Créer une séance</button>
      </p>
    </form>
    <style>
      .acdc-create-session-builder .acdc-panel{overflow:visible}.acdc-session-dates-builder{display:flex;flex-direction:column;gap:12px}.acdc-session-dates-head{display:flex;align-items:center;justify-content:space-between;min-height:40px;background:#f4f7fb;border-radius:10px;padding:0 0 0 16px;color:#1E4777;font-size:12px;font-weight:700;letter-spacing:.02em}.acdc-session-add-date{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border:none;border-radius:999px;background:#C5A253;color:#0B0706;font-size:18px;line-height:1;cursor:pointer;margin-right:8px}.acdc-session-dates-list{display:flex;flex-direction:column;gap:16px}
      /* Groupe journée Qualiopi */
      .acdc-session-date-group{border:1px solid var(--acdc-border);border-radius:10px;overflow:hidden;background:#fff}
      .acdc-session-date-group-head{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#f4f7fb;gap:14px}
      .acdc-session-date-group-date-label{display:flex;flex-direction:column;gap:5px;font-size:12px;font-weight:700;color:#0f2c52;flex:1}
      .acdc-session-date-group-date-label input[type=date]{height:36px;border:1px solid var(--acdc-border);border-radius:8px;padding:0 10px;font-size:13px;color:#0f2c52;background:#fff;max-width:200px}
      .acdc-session-date-group-slots{display:grid;grid-template-columns:1fr 1fr;border-top:1px solid var(--acdc-border)}
      .acdc-session-date-half{padding:14px 16px;display:flex;flex-direction:column;gap:10px}
      .acdc-session-date-half-am{border-right:1px solid var(--acdc-border)}
      .acdc-session-half-badge{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;letter-spacing:.04em;padding:3px 10px;border-radius:20px;width:fit-content}
      .acdc-session-half-badge-am{background:#fff8e1;color:#7b5800}
      .acdc-session-half-badge-pm{background:#e8f0fe;color:#1a3a6b}
      .acdc-session-date-fields{display:grid;grid-template-columns:1fr 1fr;gap:10px}
      .acdc-session-date-half label{display:flex;flex-direction:column;gap:5px;font-size:12px;color:var(--acdc-text-muted);font-weight:500}
      .acdc-session-date-half input[type=time]{height:36px;border:1px solid var(--acdc-border);border-radius:8px;padding:0 10px;font-size:13px;color:#0f2c52;background:#fff;width:100%}
      .acdc-session-date-remove{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid var(--acdc-border);border-radius:8px;background:#fff;color:#0C2D52;cursor:pointer;font-size:16px;flex-shrink:0}
      .acdc-session-help-block{display:flex;flex-direction:column;gap:10px;margin-top:14px;color:#1E4777;font-size:12px;line-height:1.5}.acdc-session-help-block p{margin:0}.acdc-session-toggle{display:inline-flex;align-items:center;gap:10px}.acdc-session-toggle input{position:absolute;opacity:0;pointer-events:none}.acdc-session-toggle-ui{position:relative;display:inline-flex;width:44px;height:24px;background:#d7dee9;border-radius:999px;transition:background .2s ease}.acdc-session-toggle-ui::after{content:'';position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:999px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.18);transition:transform .2s ease}.acdc-session-toggle input:checked + .acdc-session-toggle-ui{background:#c9a84c}.acdc-session-toggle input:checked + .acdc-session-toggle-ui::after{transform:translateX(20px)}@media (max-width:900px){.acdc-session-date-group-slots{grid-template-columns:1fr}.acdc-session-date-half-am{border-right:none;border-bottom:1px solid var(--acdc-border)}.acdc-session-date-fields{grid-template-columns:1fr 1fr}}
    </style>
    <script>
      (function(){
        var form = document.querySelector('.acdc-create-session-builder');
        if(!form){return;}
        var addBtn = form.querySelector('[data-add-session-date]');
        var list = form.querySelector('[data-session-dates-list]');
        // Données apprenants par groupe
        var groupLearnersData = <?php echo wp_json_encode( $groups_learners_map ); ?>;
        // Show/hide champs contextuels selon le type de séance
        var sessionTypeSelect = form.querySelector('[name="session_builder[session_type]"]');
        function refreshContextFields() {
          var val = sessionTypeSelect ? sessionTypeSelect.value : '';
          form.querySelectorAll('.acdc-session-field-individuelle').forEach(function(el) {
            el.style.display = val === 'Individuelle' ? '' : 'none';
          });
          form.querySelectorAll('.acdc-session-field-groupe').forEach(function(el) {
            el.style.display = val === 'Groupe' ? '' : 'none';
          });
          if (val !== 'Groupe') {
            var lw = document.getElementById('acdc-session-group-learners');
            if (lw) lw.style.display = 'none';
          }
        }
        if (sessionTypeSelect) {
          sessionTypeSelect.addEventListener('change', refreshContextFields);
          refreshContextFields();
        }
        // Affichage apprenants du groupe sélectionné
        var groupSelect = document.getElementById('acdc-session-group-select');
        if (groupSelect) {
          groupSelect.addEventListener('change', function() {
            var gid = parseInt(this.value, 10);
            var wrap = document.getElementById('acdc-session-group-learners');
            var ul = document.getElementById('acdc-session-group-learners-list');
            if (!wrap || !ul) return;
            if (gid && groupLearnersData[gid] && groupLearnersData[gid].length) {
              ul.innerHTML = groupLearnersData[gid].map(function(n) {
                return '<li>' + n.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</li>';
              }).join('');
              wrap.style.display = '';
            } else {
              ul.innerHTML = '';
              wrap.style.display = 'none';
            }
          });
        }
        // Show/hide Lieu / Lien visio selon le format
        var formatSelect = document.getElementById('acdc-session-format-select');
        function refreshFormatFields() {
          var fmt = formatSelect ? formatSelect.value : '';
          form.querySelectorAll('.acdc-session-field-presentiel').forEach(function(el) {
            el.style.display = fmt === 'Distanciel' ? 'none' : '';
          });
          form.querySelectorAll('.acdc-session-field-distanciel').forEach(function(el) {
            el.style.display = fmt === 'Distanciel' ? '' : 'none';
          });
        }
        if (formatSelect) {
          formatSelect.addEventListener('change', refreshFormatFields);
          refreshFormatFields();
        }
        var slotCounter = 0;

        function createDateGroup(prefillDate, amStart, amEnd, pmStart, pmEnd) {
          amStart = amStart || '09:00';
          amEnd   = amEnd   || '12:30';
          pmStart = pmStart || '13:30';
          pmEnd   = pmEnd   || '17:00';
          var idxAm = slotCounter++;
          var idxPm = slotCounter++;
          var group = document.createElement('div');
          group.className = 'acdc-session-date-group';
          group.innerHTML =
            '<div class="acdc-session-date-group-head">'
            + '<label class="acdc-session-date-group-date-label">Date de la journ\u00e9e'
            + '<input type="date" class="acdc-session-date-picker" value="' + (prefillDate||'') + '" required>'
            + '</label>'
            + '<button type="button" class="acdc-session-date-remove" aria-label="Supprimer ce jour">&#215;</button>'
            + '</div>'
            + '<div class="acdc-session-date-group-slots">'
            +   '<div class="acdc-session-date-half acdc-session-date-half-am">'
            +     '<span class="acdc-session-half-badge acdc-session-half-badge-am">Matin</span>'
            +     '<div class="acdc-session-date-fields">'
            +       '<label>D\u00e9but<input type="time" class="acdc-slot-am-start" value="' + amStart + '" required></label>'
            +       '<label>Fin<input type="time" class="acdc-slot-am-end" value="' + amEnd + '" required></label>'
            +     '</div>'
            +   '</div>'
            +   '<div class="acdc-session-date-half acdc-session-date-half-pm">'
            +     '<span class="acdc-session-half-badge acdc-session-half-badge-pm">Apr\u00e8s-midi</span>'
            +     '<div class="acdc-session-date-fields">'
            +       '<label>D\u00e9but<input type="time" class="acdc-slot-pm-start" value="' + pmStart + '" required></label>'
            +       '<label>Fin<input type="time" class="acdc-slot-pm-end" value="' + pmEnd + '" required></label>'
            +     '</div>'
            +   '</div>'
            + '</div>'
            + '<input type="hidden" class="acdc-slot-hidden-start-am" name="session_builder[slots][' + idxAm + '][start_at]" value="">'
            + '<input type="hidden" class="acdc-slot-hidden-end-am"   name="session_builder[slots][' + idxAm + '][end_at]"   value="">'
            + '<input type="hidden" class="acdc-slot-hidden-start-pm" name="session_builder[slots][' + idxPm + '][start_at]" value="">'
            + '<input type="hidden" class="acdc-slot-hidden-end-pm"   name="session_builder[slots][' + idxPm + '][end_at]"   value="">';
          group.querySelector('.acdc-session-date-remove').addEventListener('click', function(){ group.remove(); });
          function syncHidden() {
            var d   = group.querySelector('.acdc-session-date-picker').value;
            var asT = group.querySelector('.acdc-slot-am-start').value;
            var aeT = group.querySelector('.acdc-slot-am-end').value;
            var psT = group.querySelector('.acdc-slot-pm-start').value;
            var peT = group.querySelector('.acdc-slot-pm-end').value;
            group.querySelector('.acdc-slot-hidden-start-am').value = (d && asT) ? d + 'T' + asT : '';
            group.querySelector('.acdc-slot-hidden-end-am').value   = (d && aeT) ? d + 'T' + aeT : '';
            group.querySelector('.acdc-slot-hidden-start-pm').value = (d && psT) ? d + 'T' + psT : '';
            group.querySelector('.acdc-slot-hidden-end-pm').value   = (d && peT) ? d + 'T' + peT : '';
          }
          group.querySelectorAll('.acdc-session-date-picker, .acdc-slot-am-start, .acdc-slot-am-end, .acdc-slot-pm-start, .acdc-slot-pm-end').forEach(function(inp) {
            inp.addEventListener('change', syncHidden);
            inp.addEventListener('input',  syncHidden);
          });
          syncHidden();
          return group;
        }

        if(addBtn && list){
          addBtn.addEventListener('click', function(){ list.appendChild(createDateGroup()); });
          var prefillDates = <?php echo $prefill_dates_json; ?>;
          if(prefillDates && prefillDates.length){
            prefillDates.forEach(function(iso){ list.appendChild(createDateGroup(iso)); });
          } else {
            list.appendChild(createDateGroup());
          }
        }

        form.addEventListener('submit', function(event){
          var groups = list ? list.querySelectorAll('.acdc-session-date-group') : [];
          if(!groups.length){ event.preventDefault(); window.alert('Ajoutez au moins une date de s\u00e9ance.'); return; }
          for(var i=0;i<groups.length;i++){
            var dp = groups[i].querySelector('.acdc-session-date-picker');
            if(!dp || !dp.value){ event.preventDefault(); window.alert('S\u00e9lectionnez une date pour chaque journ\u00e9e.'); return; }
            var hAmS = groups[i].querySelector('.acdc-slot-hidden-start-am').value;
            var hAmE = groups[i].querySelector('.acdc-slot-hidden-end-am').value;
            var hPmS = groups[i].querySelector('.acdc-slot-hidden-start-pm').value;
            var hPmE = groups[i].querySelector('.acdc-slot-hidden-end-pm').value;
            if(!hAmS||!hAmE||!hPmS||!hPmE){ event.preventDefault(); window.alert('V\u00e9rifiez les horaires de la journ\u00e9e du ' + dp.value + '.'); return; }
            if(hAmE <= hAmS){ event.preventDefault(); window.alert('La fin du matin doit \u00eatre post\u00e9rieure \u00e0 son d\u00e9but (' + dp.value + ').'); return; }
            if(hPmE <= hPmS){ event.preventDefault(); window.alert('La fin de l\'apr\u00e8s-midi doit \u00eatre post\u00e9rieure \u00e0 son d\u00e9but (' + dp.value + ').'); return; }
          }
        });
      })();
    </script>
    <?php
  }

  private function render_front_sessions_tab( $action, $item_id ) {
    $sessions  = $this->get_sessions();
    $session  = $item_id ? $this->get_session( $item_id ) : null;
    $formations = $this->get_formations();
    $companies = $this->get_companies();
    /* ACDC 3.20.92 — Liste des formateurs pour le <select> dédié sur la fiche session.
       Tri alphabétique nom/prénom pour faciliter la recherche dans le select. */
    $session_trainers = $this->get_trainers();
    usort( $session_trainers, function( $a, $b ) {
      $key_a = strtolower( (string) $a->last_name . ' ' . (string) $a->first_name );
      $key_b = strtolower( (string) $b->last_name . ' ' . (string) $b->first_name );
      return strcmp( $key_a, $key_b );
    } );
    $prefill_company_id = isset( $_GET['company_id'] ) ? absint( wp_unslash( $_GET['company_id'] ) ) : 0;
    $state = in_array( $action, array( 'new', 'edit' ), true ) ? $this->acdc_consume_form_state( 'session' ) : array();
    $state_input = ( ! empty( $state['input'] ) && is_array( $state['input'] ) ) ? $state['input'] : array();
    $required_fields = $this->acdc_get_form_required_fields( $state );
    $session_value = function( $key, $default = '' ) use ( $session, $state_input ) {
      if ( array_key_exists( $key, $state_input ) && ! is_array( $state_input[ $key ] ) ) {
        return $state_input[ $key ];
      }
      return $session && isset( $session->$key ) ? $session->$key : $default;
    };
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Sessions</h2>
        <p>Planification et suivi des sessions de formation.</p>
      </div>
      <div class="acdc-inline-wrap">
        <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_export_sessions_csv' ), 'acdc_export_sessions_csv' ) ); ?>">⬇ Exporter CSV</a>
        <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $this->portal_page_url( array( 'tab' => 'sessions', 'action' => 'new' ) ) ); ?>">Ajouter une session</a>
      </div>
    </section>
    <?php if ( in_array( $action, array( 'new', 'edit' ), true ) ) : ?>
      <?php $this->acdc_render_form_validation_style( '.acdc-form' ); ?>
      <div class="acdc-panel acdc-form-panel">
        <h3><?php echo $session ? 'Modifier une session' : 'Nouvelle session'; ?></h3>
        <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <?php wp_nonce_field( 'acdc_save_session' ); ?>
          <input type="hidden" name="action" value="acdc_save_session">
          <input type="hidden" name="session_id" value="<?php echo $session ? esc_attr( $session->id ) : 0; ?>">
          <input type="hidden" name="return_after_save" value="edit">
          <div class="acdc-grid-2cols">
            <p>
              <label>Formation</label>
              <select name="formation_id">
                <option value="0">— Aucune —</option>
                <?php $selected_formation = (int) $session_value( 'formation_id', 0 ); ?>
                <?php foreach ( $formations as $formation ) : ?>
                  <option value="<?php echo esc_attr( $formation->id ); ?>" <?php selected( $selected_formation, $formation->id ); ?>><?php echo esc_html( $this->format_formation_option_label( $formation ) ); ?></option>
                <?php endforeach; ?>
              </select>
            </p>
            <p>
              <label>Entreprise</label>
              <select name="company_id">
                <option value="0">— Aucune —</option>
                <?php $selected_company = (int) $session_value( 'company_id', $prefill_company_id ); ?>
                <?php foreach ( $companies as $company ) : ?>
                  <option value="<?php echo esc_attr( $company->id ); ?>" <?php selected( $selected_company, $company->id ); ?>><?php echo esc_html( $company->name ); ?></option>
                <?php endforeach; ?>
              </select>
            </p>
            <p>
              <?php /* ACDC 3.20.92 — Désignation directe du formateur sur la session.
                       Indépendante de groups.trainer_id : permet de couvrir les sessions sans groupe.
                       Le portail formateur fait l'union des deux sources pour « Mes sessions ». */ ?>
              <label>Formateur</label>
              <select name="trainer_id">
                <option value="0">— Aucun formateur désigné —</option>
                <?php $selected_trainer = (int) $session_value( 'trainer_id', 0 ); ?>
                <?php foreach ( $session_trainers as $tr ) :
                  $tr_label = trim( (string) $tr->first_name . ' ' . (string) $tr->last_name );
                  if ( '' === $tr_label ) { $tr_label = '(sans nom)'; }
                ?>
                  <option value="<?php echo esc_attr( $tr->id ); ?>" <?php selected( $selected_trainer, (int) $tr->id ); ?>><?php echo esc_html( $tr_label ); ?></option>
                <?php endforeach; ?>
              </select>
            </p>
            <p><label>Titre</label><input type="text" name="title" required value="<?php echo esc_attr( $session_value( 'title' ) ); ?>"<?php echo $this->acdc_get_invalid_field_class( 'title', $required_fields ); ?>><?php echo $this->acdc_get_invalid_field_note( 'title', $required_fields ); ?></p>
            <p>
              <label>Statut</label>
              <select name="status">
                <?php $current_status = (string) $session_value( 'status', 'Planifiée' ); ?>
                <?php foreach ( array( 'Planifiée', 'En cours', 'Terminée', 'Annulée' ) as $status ) : ?>
                  <option value="<?php echo esc_attr( $status ); ?>" <?php selected( $current_status, $status ); ?>><?php echo esc_html( $status ); ?></option>
                <?php endforeach; ?>
              </select>
            </p>
            <?php
            /* ACDC 3.25.186 — La date du jour ne sert de valeur par défaut qu'à
               la CRÉATION. Sur une séance existante dont la date de fin est vide,
               elle pré-remplissait le champ avec aujourd'hui : un enregistrement
               distrait inscrivait une fausse date de fin de formation. Ce n'est
               pas anodin — c'est cette date qui ancre les cinq enquêtes de fin de
               parcours et leurs douze relances. Un champ vide reste vide. */
            $acdc_session_date_default = $session ? '' : $this->current_date();
            ?>
            <p><label>Date de début</label><input type="date" name="start_date" value="<?php echo esc_attr( $session_value( 'start_date', $acdc_session_date_default ) ); ?>"></p>
            <p><label>Date de fin</label><input type="date" name="end_date" value="<?php echo esc_attr( $session_value( 'end_date', $acdc_session_date_default ) ); ?>"></p>
            <?php
            /* ACDC 3.25.195 — Trois champs manquaient à l'écran de modification
               alors qu'ils existent à la création : le type de séance, la méthode
               d'émargement et le format. La 3.25.190 a arrêté l'hémorragie — un
               champ absent n'est plus écrasé — mais elle laissait les séances déjà
               dégradées irréparables : aucun écran ne permettait de ressaisir ces
               valeurs. Pire, l'écran « Séances validées » AFFICHE les colonnes
               Format et Méthode d'émargement et propose un bouton « Modifier »
               vers un formulaire qui ne savait pas les éditer. */
            $acdc_type_options       = $this->get_session_type_options();
            $acdc_format_options     = $this->get_session_format_options();
            $acdc_attendance_options = $this->get_session_attendance_options();
            ?>
            <p><label>Type de séance</label>
              <select name="session_type">
                <option value="">— Non précisé —</option>
                <?php foreach ( $acdc_type_options as $value => $label ) : ?>
                  <option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) $session_value( 'session_type' ), (string) $value ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
              </select></p>
            <p><label>Format de la séance</label>
              <select name="session_format">
                <option value="">— Non précisé —</option>
                <?php foreach ( $acdc_format_options as $value => $label ) : ?>
                  <option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) $session_value( 'session_format' ), (string) $value ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
              </select></p>
            <p><label>Méthode d'émargement</label>
              <select name="attendance_method">
                <option value="">— Non précisé —</option>
                <?php foreach ( $acdc_attendance_options as $value => $label ) : ?>
                  <option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) $session_value( 'attendance_method' ), (string) $value ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
              </select>
              <span class="description">Ces trois valeurs pilotent la feuille d'émargement. Une séance qui les perd ne peut plus être justifiée.</span></p>
            <?php
            /* ACDC 3.25.188 — Les horaires n'existaient QUE dans l'écran de
               création atteint depuis une proposition. Une séance existante sans
               heures ne pouvait donc être corrigée que par suppression et
               recréation. Le workflow, qui refuse désormais d'inventer des
               demi-journées, signalait à juste titre l'absence d'horaires — mais
               l'alerte débouchait sur une impasse. Elle a maintenant sa sortie.

               Le marqueur caché dit au traitement que CE formulaire parle des
               heures : sans lui, un champ vide serait indiscernable d'un
               formulaire qui n'en parle pas, et l'effacerait. */
            $acdc_time_value = function( $column ) use ( $session, $state_input ) {
              $field = ( 'start_at' === $column ) ? 'start_time' : 'end_time';
              if ( array_key_exists( $field, $state_input ) && ! is_array( $state_input[ $field ] ) ) {
                return (string) $state_input[ $field ];
              }
              if ( $session && ! empty( $session->$column ) ) {
                return substr( (string) $session->$column, 11, 5 );
              }
              return '';
            };
            ?>
            <input type="hidden" name="session_times_posted" value="1">
            <p><label>Heure de début</label><input type="time" name="start_time" value="<?php echo esc_attr( $acdc_time_value( 'start_at' ) ); ?>"></p>
            <p><label>Heure de fin</label><input type="time" name="end_time" value="<?php echo esc_attr( $acdc_time_value( 'end_at' ) ); ?>">
              <span class="description">Renseignez les heures pour que les rappels d'émargement puissent être planifiés. Une séance couvrant matin et après-midi produit deux rappels.</span></p>
            <p><label>Lieu</label><input type="text" name="location" value="<?php echo esc_attr( $session_value( 'location' ) ); ?>"></p>
            <p><label>Capacité maximale</label><input type="number" min="0" name="max_learners" value="<?php echo esc_attr( (string) $session_value( 'max_learners', '0' ) ); ?>"></p>
          </div>
          <p><label>Lien visio</label><input type="url" name="remote_link" value="<?php echo esc_attr( $session_value( 'remote_link' ) ); ?>"></p>
          <p><label>Notes</label><textarea name="notes" rows="4"><?php echo esc_textarea( $session_value( 'notes' ) ); ?></textarea></p>
          <p><button type="submit" class="acdc-button acdc-button-primary"><?php echo $session ? 'Modifier' : 'Enregistrer'; ?></button></p>
        </form>
      </div>
    <?php endif; ?>
    <div class="acdc-panel">
      <div class="acdc-table-wrap">
        <table class="acdc-table">
          <thead>
            <tr>
              <th>Titre</th>
              <th>Formation</th>
              <th>Entreprise</th>
              <th>Dates</th>
              <th>Statut</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if ( ! empty( $sessions ) ) : ?>
            <?php foreach ( $sessions as $entry ) : ?>
              <tr>
                <td><?php echo esc_html( $entry->title ); ?></td>
                <td><?php echo esc_html( $entry->formation_title ); ?></td>
                <td><?php echo esc_html( $entry->company_name ); ?></td>
                <td><?php echo esc_html( $entry->start_date ); ?> → <?php echo esc_html( $entry->end_date ); ?></td>
                <td><?php echo esc_html( $entry->status ); ?></td>
                <td>
                  <?php /* ACDC 3.20.104 — Conversion liens texte → icônes inline 25px. */ ?>
                  <div class="acdc-groups-actions-inline">
                    <a class="acdc-row-action-icon acdc-row-edit-link" href="<?php echo esc_url( $this->portal_page_url( array( 'tab' => 'sessions', 'action' => 'edit', 'item_id' => $entry->id ) ) ); ?>" title="Modifier" aria-label="Modifier la session">
                      <?php echo $this->render_inline_icon( 'edit', 25 ); ?>
                    </a>
                    <a class="acdc-row-action-icon acdc-row-delete-link" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_delete_session&session_id=' . $entry->id ), 'acdc_delete_session_' . $entry->id ) ); ?>" title="Supprimer" aria-label="Supprimer la session" onclick="return confirm('Supprimer cette session ?');">
                      <?php echo $this->render_inline_icon( 'trash', 25 ); ?>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else : ?>
            <tr><td colspan="6">Aucune session enregistrée.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
  }

  private function render_front_sessions_pending_tab( $action, $item_id ) {
    if ( ! in_array( $action, array( 'list', 'view' ), true ) ) {
      $action = 'list';
    }

    $base_tab = 'sessions_pending';
    $base_create_url = is_admin() ? $this->admin_tab_url( 'create_session' ) : $this->portal_page_url( array( 'tab' => 'create_session' ) );
    $list_url = is_admin() ? $this->admin_tab_url( $base_tab ) : $this->portal_page_url( array( 'tab' => $base_tab ) );

    if ( 'view' === $action && $item_id ) {
      $session = $this->get_session( $item_id );
      if ( ! $session || ( empty( $session->is_draft ) && 'Brouillon' !== (string) $session->status ) ) {
        $session = null;
      }
      if ( ! $session ) {
        wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Séance introuvable.' ), 'notice_type' => 'error' ), $list_url ) );
        exit;
      }
      if ( empty( $session->formation_title ) || empty( $session->company_name ) ) {
        global $wpdb;
        $session = $wpdb->get_row( $wpdb->prepare( "SELECT s.*, f.title AS formation_title, e.name AS company_name FROM {$this->session_table} s LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id LEFT JOIN {$this->company_table} e ON e.id = s.company_id WHERE s.id = %d", $item_id ) );
      }
      ?>
      <section class="acdc-section-head">
        <div>
          <h2>Séances en cours de validation</h2>
          <p>Consultez les brouillons de séances avant leur validation et leur publication dans le calendrier.</p>
        </div>
        <div class="acdc-inline-wrap">
          <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $list_url ); ?>">Retour à la liste</a>
          <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $base_create_url ); ?>">Créer une séance</a>
        </div>
      </section>
      <div class="acdc-grid-2cols acdc-mb-18">
        <?php foreach ( $this->get_session_detail_sections( $session ) as $section ) : ?>
          <div class="acdc-panel">
            <h3><?php echo esc_html( $section['title'] ); ?></h3>
            <div class="acdc-list-details">
              <?php foreach ( $section['items'] as $label => $value ) : ?>
                <div><strong><?php echo esc_html( $label ); ?> :</strong> <?php echo nl2br( esc_html( $value ) ); ?></div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php
      return;
    }

    $search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ( isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '' );
    $items = $this->get_pending_sessions( $search );
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Séances en cours de validation</h2>
        <p>Brouillons de séances enregistrés avant validation et publication dans le calendrier des séances.</p>
      </div>
      <div class="acdc-inline-wrap">
        <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $base_create_url ); ?>">Créer une séance</a>
      </div>
    </section>
    <div class="acdc-panel acdc-mb-18">
      <form class="acdc-search-bar" method="get" action="<?php echo esc_url( $list_url ); ?>">
        <?php if ( is_admin() ) : ?>
          <input type="hidden" name="page" value="acdc-of-dashboard">
        <?php else : ?>
          <input type="hidden" name="tab" value="sessions_pending">
        <?php endif; ?>
        <input type="hidden" name="tab" value="sessions_pending">
        <input type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="Rechercher">
        <button type="submit" class="acdc-button acdc-button-soft">Rechercher</button>
      </form>
    </div>
    <div class="acdc-panel">
      <?php if ( empty( $items ) ) : ?>
        <div class="acdc-empty-state" style="padding:72px 24px;text-align:center;">
          <div style="font-size:54px;line-height:1;color:#DCE4EC;margin-bottom:14px;">🗓️</div>
          <p style="margin:0 0 18px;color:#1E4777;">Aucune donnée ne correspond aux critères demandés.</p>
          <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $base_create_url ); ?>">Créer une séance</a>
        </div>
      <?php else : ?>
        <div class="acdc-table-wrap">
          <table class="acdc-table acdc-table-sessions-pending" data-acdc-table-id="sessions-pending-list">
            <thead>
              <tr>
                <th>Type de séance</th>
                <th>Formation</th>
                <th>Dates personnalisées</th>
                <th>Méthode d’émargement</th>
                <th>Format</th>
                <th>Statut</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ( $items as $entry ) : ?>
              <?php list( $view_url, $edit_url, $delete_url ) = $this->get_session_row_action_links( $entry, $base_tab ); ?>
              <tr>
                <td><?php echo esc_html( ! empty( $entry->session_type ) ? $entry->session_type : '—' ); ?></td>
                <td>
                  <div><?php echo esc_html( ! empty( $entry->formation_title ) ? $entry->formation_title : '—' ); ?></div>
                  <?php if ( ! empty( $entry->title ) ) : ?><small><?php echo esc_html( $entry->title ); ?></small><?php endif; ?>
                </td>
                <td><?php echo esc_html( $this->get_session_datetime_label( $entry ) ); ?></td>
                <td><?php echo esc_html( ! empty( $entry->attendance_method ) ? $entry->attendance_method : '—' ); ?></td>
                <td><?php echo esc_html( ! empty( $entry->session_format ) ? $entry->session_format : '—' ); ?></td>
                <td><?php echo esc_html( $this->get_session_status_badge_label( $entry ) ); ?></td>
                <td class="acdc-actions-cell-icons acdc-sessions-actions-cell">
                  <div class="acdc-sessions-actions-inline">
                    <a class="acdc-row-action-icon acdc-row-view-link" href="<?php echo esc_url( $view_url ); ?>" title="Voir" aria-label="Voir la séance">
                      <?php echo $this->render_inline_icon( 'eye', 25 ); ?>
                    </a>
                    <a class="acdc-row-action-icon acdc-row-edit-link" href="<?php echo esc_url( $edit_url ); ?>" title="Modifier" aria-label="Modifier la séance">
                      <?php echo $this->render_inline_icon( 'edit', 25 ); ?>
                    </a>
                    <a class="acdc-row-action-icon acdc-row-delete-link" href="<?php echo esc_url( $delete_url ); ?>" title="Supprimer" aria-label="Supprimer la séance" onclick="return confirm('Supprimer cette séance ?');">
                      <?php echo $this->render_inline_icon( 'trash', 25 ); ?>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <?php
  }

public function render_admin_sessions_calendar_page() { $this->render_admin_portal_wrapper( 'sessions_calendar' ); }

public function render_admin_sessions_pending_page() { $this->render_admin_portal_wrapper( 'sessions_pending' ); }

public function render_admin_sessions_validated_page() { $this->render_admin_portal_wrapper( 'sessions_validated' ); }
}
