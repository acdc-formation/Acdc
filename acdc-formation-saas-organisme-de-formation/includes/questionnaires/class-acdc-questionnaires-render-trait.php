<?php
/**
 * ACDC Questionnaires / Enquêtes — rendus extraits.
 *
 * @since 3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Questionnaires_Render_Trait {

  private function render_front_mid_survey_documents_tab() {
    $search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
    $per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 25;
    if ( ! in_array( $per_page, array( 25, 50, 100 ), true ) ) {
      $per_page = 25;
    }
    $paged = \ACDC\Support\ScreenQuery::readPaged( $_GET );
    $base_tab = 'mid_surveys_documents';
    $base_url = is_admin() ? $this->admin_tab_url( $base_tab ) : $this->portal_page_url( array( 'tab' => $base_tab ) );
    $rows = $this->get_mid_survey_rows( $search );
    $total = count( $rows );
    $total_pages = max( 1, (int) ceil( max( 1, $total ) / $per_page ) );
    if ( $paged > $total_pages ) { $paged = $total_pages; }
    $offset = ( $paged - 1 ) * $per_page;
    $page_rows = array_slice( $rows, $offset, $per_page );
    ?>
    <section class="acdc-section-head"><div><h2>Enquêtes intermédiaires</h2></div></section>
    <div class="acdc-panel acdc-mb-18">
      <form class="acdc-search-bar" method="get" action="<?php echo esc_url( $base_url ); ?>">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
        <input type="hidden" name="tab" value="mid_surveys_documents">
        <div class="acdc-search-row">
          <input type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="Rechercher">
          <button type="button" class="acdc-filter-toggle acdc-filter-toggle-icons-only" data-acdc-filter-toggle aria-expanded="false" title="Filtres"><?php echo $this->render_inline_icon( 'filter', 18 ); ?> <?php echo $this->render_inline_icon( 'chevron-down', 16 ); ?></button>
        </div>
        <div class="acdc-sessions-filters-panel acdc-documents-filters-panel" data-acdc-filters-panel hidden>
          <div class="acdc-documents-filter-card">
            <label><span>Par page</span>
              <select name="per_page">
                <?php foreach ( array( 25, 50, 100 ) as $size ) : ?>
                  <option value="<?php echo esc_attr( $size ); ?>" <?php selected( $per_page, $size ); ?>><?php echo esc_html( $size ); ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div class="acdc-inline-wrap" style="justify-content:flex-end;margin-top:14px;">
            <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $base_url ); ?>">Réinitialiser</a>
            <button type="submit" class="acdc-button acdc-button-primary">Appliquer</button>
          </div>
        </div>
      </form>
    </div>
    <div class="acdc-panel">
      <?php if ( empty( $page_rows ) ) : ?>
        <div class="acdc-empty-state" style="padding:72px 24px;text-align:center;"><div class="acdc-empty-state-icon" aria-hidden="true"><?php echo $this->render_inline_icon( 'survey', 54 ); ?></div><p style="margin:0;color:var(--acdc-text-muted);">Aucune donnée ne correspond aux critères demandés.</p></div>
      <?php else : ?>
        <div class="acdc-table-wrap">
          <table class="acdc-table acdc-table-mid-surveys-documents">
            <thead>
              <tr>
                <th><input type="checkbox" aria-label="Sélectionner"></th>
                <th>ID</th>
                <th>Apprenant</th>
                <th>Enquête</th>
                <th>Date ajout/passage</th>
                <th>Formation</th>
                <th>Dates de formation</th>
                <th>Durée (H)</th>
                <th>Format</th>
                <th>État dossier</th>
                <th><span class="screen-reader-text">Menu</span></th>
                <th><span class="screen-reader-text">Voir</span></th>
                <th><span class="screen-reader-text">Modifier</span></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ( $page_rows as $row ) : ?>
              <?php
              $entry = $row['registration'];
              $context = $row['context'];
              $document = $context['document'];
              $download_url = $this->get_mid_survey_download_url( $entry, 'attachment' );
              $view_pdf_url = $this->get_mid_survey_download_url( $entry, 'inline' );
              $file_name = $this->get_mid_survey_display_file_name( $entry, $context, $document );
              $view_modal_id = 'acdc-mid-survey-view-' . (int) $entry->id;
              $edit_modal_id = 'acdc-mid-survey-edit-' . (int) $entry->id;
              ?>
              <tr>
                <td><input type="checkbox" aria-label="Sélectionner cette enquête intermédiaire"></td>
                <td><?php echo esc_html( (int) $entry->id ); ?></td>
                <td><?php echo esc_html( $context['learner_name'] ); ?></td>
                <td><?php echo esc_html( $context['survey_source_label'] ); ?><small><?php echo esc_html( $context['survey_title'] ); ?></small></td>
                <td><?php echo esc_html( $this->format_pdf_date( $context['start_date'] ) ); ?></td>
                <td><a href="<?php echo esc_url( $view_pdf_url ); ?>" target="_blank" rel="noopener" class="acdc-program-link"><?php echo esc_html( $context['formation_title'] ); ?></a></td>
                <td><?php echo esc_html( 'Début : ' . $this->format_pdf_date( $context['start_date'] ) . "\nFin : " . $this->format_pdf_date( $context['end_date'] ) ); ?></td>
                <td><?php echo esc_html( $context['duration'] ); ?></td>
                <td><?php echo esc_html( $context['format'] ); ?></td>
                <td><?php echo esc_html( $row['state'] ); ?></td>
                <td>
                  <div class="acdc-row-menu" data-acdc-row-menu>
                    <button type="button" class="acdc-row-menu-toggle" data-acdc-row-menu-toggle aria-expanded="false" title="Actions"><?php echo $this->render_inline_icon( 'more-horizontal', 25 ); ?></button>
                    <div class="acdc-row-menu-dropdown" data-acdc-row-menu-dropdown hidden>
                      <a href="<?php echo esc_url( $download_url ); ?>">Résultats Enquêtes intermédiaires</a>
                    </div>
                  </div>
                </td>
                <td><button type="button" class="acdc-row-view-link" data-acdc-modal-open="<?php echo esc_attr( $view_modal_id ); ?>" title="Voir" aria-label="Voir"><?php echo $this->render_inline_icon( 'view', 25 ); ?></button></td>
                <td><button type="button" class="acdc-row-edit-link" data-acdc-modal-open="<?php echo esc_attr( $edit_modal_id ); ?>" title="Modifier" aria-label="Modifier"><?php echo $this->render_inline_icon( 'edit-pencil', 25 ); ?></button></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ( $total_pages > 1 ) : ?>
          <div class="acdc-pagination-wrap"><div class="acdc-pagination"><?php for ( $page = 1; $page <= $total_pages; $page++ ) : ?><a class="<?php echo $page === $paged ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'q' => $search, 'per_page' => $per_page, 'acdc_paged' => $page ), $base_url ) ); ?>"><?php echo esc_html( $page ); ?></a><?php endfor; ?></div><div class="acdc-pagination-summary"><?php echo esc_html( sprintf( '%d-%d de %d', $total ? $offset + 1 : 0, min( $offset + $per_page, $total ), $total ) ); ?></div></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php foreach ( $page_rows as $row ) : ?>
      <?php
      $entry = $row['registration'];
      $context = $row['context'];
      $document = $context['document'];
      $file_name = $this->get_mid_survey_display_file_name( $entry, $context, $document );
      $view_modal_id = 'acdc-mid-survey-view-' . (int) $entry->id;
      $edit_modal_id = 'acdc-mid-survey-edit-' . (int) $entry->id;
      $view_pdf_url = $this->get_mid_survey_download_url( $entry, 'inline' );
      ?>
      <div class="acdc-modal-shell" id="<?php echo esc_attr( $view_modal_id ); ?>" hidden>
        <div class="acdc-modal-backdrop" data-acdc-modal-close></div>
        <div class="acdc-modal-dialog acdc-modal-dialog-contract">
          <div class="acdc-modal-header"><h4>Voir les enquêtes intermédiaires :</h4><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button></div>
          <div class="acdc-modal-body">
            <div class="acdc-contract-doc-topbar"><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $view_pdf_url ); ?>" target="_blank" rel="noopener">Voir le document</a></div>
            <div class="acdc-contract-details-grid">
              <div class="acdc-contract-detail-label">ID</div><div><?php echo esc_html( (int) $entry->id ); ?></div>
              <div class="acdc-contract-detail-label">Apprenant</div><div><?php echo esc_html( $context['learner_name'] ); ?></div>
              <div class="acdc-contract-detail-label">Enquête</div><div><?php echo esc_html( $context['survey_source_label'] ); ?>
<?php echo esc_html( $context['survey_title'] ); ?></div>
              <div class="acdc-contract-detail-label">Date Ajout/Passage</div><div><?php echo esc_html( $this->format_pdf_date( $context['start_date'] ) ); ?></div>
              <div class="acdc-contract-detail-label">Adresse e-mail</div><div><?php echo esc_html( $context['email'] ); ?></div>
              <div class="acdc-contract-detail-label">Téléphone</div><div><?php echo esc_html( $context['phone'] ); ?></div>
              <div class="acdc-contract-detail-label">Formation</div><div><?php echo esc_html( $context['formation_title'] ); ?></div>
              <div class="acdc-contract-detail-label">Dates de formation</div><div><?php echo esc_html( 'Début : ' . $this->format_pdf_date( $context['start_date'] ) . "\nFin : " . $this->format_pdf_date( $context['end_date'] ) ); ?></div>
              <div class="acdc-contract-detail-label">Durée (h)</div><div><?php echo esc_html( $context['duration'] ); ?></div>
              <div class="acdc-contract-detail-label">Format</div><div><?php echo esc_html( $context['format'] ); ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="acdc-modal-shell" id="<?php echo esc_attr( $edit_modal_id ); ?>" hidden>
        <div class="acdc-modal-backdrop" data-acdc-modal-close></div>
        <div class="acdc-modal-dialog acdc-modal-dialog-contract">
          <div class="acdc-modal-header"><h4>Modifier les enquêtes intermédiaires</h4><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button></div>
          <div class="acdc-modal-body">
            <div class="acdc-convocation-edit-topbar"><div>Apprenant : <?php echo esc_html( $context['learner_name'] ); ?></div><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $this->get_mid_survey_download_url( $entry, 'attachment' ) ); ?>">Télécharger le document actuel</a></div>
            <div class="acdc-convocation-edit-warning">Attention, cela va écraser l’ancien document et le remplacer par le nouveau document.</div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
              <?php wp_nonce_field( 'acdc_update_mid_survey_document_' . (int) $entry->id ); ?>
              <input type="hidden" name="action" value="acdc_update_mid_survey_document">
              <input type="hidden" name="registration_id" value="<?php echo esc_attr( (int) $entry->id ); ?>">
              <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
              <input type="hidden" name="tab" value="mid_surveys_documents">
              <div class="acdc-contract-grid">
                <div class="acdc-contract-label">Enquêtes intermédiaires</div>
                <div>
                  <div class="acdc-contract-file-preview">
                    <div class="acdc-contract-file-preview-thumb"><?php echo $this->render_inline_icon( 'document', 30 ); ?></div>
                    <div class="acdc-contract-file-preview-name"><?php echo esc_html( $file_name ); ?></div>
                  </div>
                  <label class="acdc-upload-dropzone">
                    <span class="acdc-button acdc-button-primary">Choisir le fichier</span>
                    <span>Déposez le fichier ou cliquez pour choisir</span>
                    <input type="file" name="mid_survey_document_file" accept="application/pdf" required>
                  </label>
                </div>
              </div>
              <p class="acdc-actions-end"><button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-close>Annuler</button><button type="submit" class="acdc-button acdc-button-primary">Modifier les enquêtes intermédiaires</button></p>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <style>
      .acdc-table-mid-surveys-documents td,.acdc-table-mid-surveys-documents th{vertical-align:top;white-space:pre-line}.acdc-table-mid-surveys-documents small{display:block;margin-top:4px;color:var(--acdc-text-muted)}
      .acdc-filter-toggle-icons-only{padding:0;border:none;background:transparent;color:var(--acdc-text-muted);display:inline-flex;align-items:center;gap:8px;cursor:pointer}.acdc-documents-filters-panel{max-width:540px;margin-left:auto;margin-right:0}.acdc-documents-filter-card{background:#fff;border:1px solid var(--acdc-border);border-radius:10px;padding:14px 18px;box-shadow:0 10px 24px rgba(28,44,64,.08)}.acdc-documents-filter-card label{display:flex;flex-direction:column;gap:10px;font-size:12px;color:var(--acdc-text-muted);font-weight:700;letter-spacing:.03em;text-transform:uppercase}.acdc-empty-state-icon{display:inline-flex;color:#DCE4EC;line-height:1;margin-bottom:14px}
      .acdc-convocation-edit-topbar{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:16px 18px;border-radius:10px;background:var(--acdc-bg);color:var(--acdc-text-muted);margin-bottom:0}.acdc-convocation-edit-warning{background:linear-gradient(90deg,#C9A409 0%,#E9C77C 100%);color:#0B0706;text-align:center;padding:10px 14px;border-radius:0 0 6px 6px;margin-bottom:18px}
      .acdc-modal-dialog-contract{width:min(1160px,94vw)}
      .acdc-contract-doc-topbar{display:flex;justify-content:flex-start;margin-bottom:18px}.acdc-contract-details-grid{display:grid;grid-template-columns:minmax(180px,280px) minmax(0,1fr);gap:0;border-top:1px solid var(--acdc-border);white-space:pre-line}.acdc-contract-detail-label{padding:14px 12px;color:var(--acdc-text-muted);border-bottom:1px solid var(--acdc-border)}.acdc-contract-details-grid>div:nth-child(2n){padding:14px 12px;border-bottom:1px solid var(--acdc-border);color:var(--acdc-text-muted)}.acdc-contract-file-preview{display:flex;flex-direction:column;gap:8px;width:280px;max-width:100%;margin-bottom:12px}.acdc-contract-file-preview-thumb{display:flex;align-items:center;justify-content:center;width:280px;height:220px;border:1px solid var(--acdc-border);border-radius:10px;background:var(--acdc-bg);color:var(--acdc-text-light)}.acdc-contract-file-preview-name{font-size:12px;color:var(--acdc-text-muted);word-break:break-all}.acdc-upload-dropzone{display:flex;align-items:center;gap:14px;padding:14px;border:2px dashed var(--acdc-border);border-radius:10px;cursor:pointer}.acdc-upload-dropzone input{display:none}.acdc-row-menu{position:relative;display:inline-flex;align-items:center;justify-content:center}.acdc-row-menu-toggle{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:none;background:transparent;color:var(--acdc-text-muted);text-decoration:none;cursor:pointer}.acdc-row-menu-dropdown{position:absolute;top:34px;right:0;min-width:220px;padding:8px 0;background:#fff;border:1px solid var(--acdc-border);border-radius:10px;box-shadow:0 10px 24px rgba(28,44,64,.12);z-index:30}.acdc-row-menu-dropdown a{display:block;padding:10px 14px;color:var(--acdc-text);text-decoration:none}.acdc-row-menu-dropdown a:hover{background:var(--acdc-bg)}
      @media (max-width:900px){.acdc-contract-details-grid{grid-template-columns:1fr}.acdc-contract-detail-label{border-bottom:none;padding-bottom:4px}.acdc-contract-details-grid>div:nth-child(2n){padding-top:0}.acdc-convocation-edit-topbar{flex-direction:column;align-items:flex-start}}
    </style>
    <script>
      (function(){
        if(window.__acdcMidSurveyDocumentsInit){return;} window.__acdcMidSurveyDocumentsInit = true;
        function openModal(id){ var modal=document.getElementById(id); if(modal){ modal.hidden=false; document.body.classList.add('acdc-modal-open'); } }
        function closeModal(modal){ if(!modal){return;} modal.hidden=true; if(!document.querySelector('.acdc-modal-shell:not([hidden])')){ document.body.classList.remove('acdc-modal-open'); } }
        var filterToggle = document.querySelector('[data-acdc-filter-toggle]');
        var filterPanel = document.querySelector('[data-acdc-filters-panel]');
        if(filterToggle && filterPanel){ filterToggle.addEventListener('click', function(){ var expanded = filterToggle.getAttribute('aria-expanded') === 'true'; filterToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true'); filterPanel.hidden = expanded; }); }
        document.querySelectorAll('[data-acdc-modal-open]').forEach(function(btn){ btn.addEventListener('click', function(){ openModal(btn.getAttribute('data-acdc-modal-open')); }); });
        document.querySelectorAll('[data-acdc-modal-close]').forEach(function(btn){ btn.addEventListener('click', function(){ closeModal(btn.closest('.acdc-modal-shell')); }); });
        document.querySelectorAll('.acdc-modal-shell').forEach(function(modal){ modal.addEventListener('click', function(e){ if(e.target === modal || e.target.hasAttribute('data-acdc-modal-close')){ closeModal(modal); } }); });
        document.addEventListener('keydown', function(e){ if(e.key === 'Escape'){ document.querySelectorAll('.acdc-modal-shell:not([hidden])').forEach(closeModal); } });
        document.querySelectorAll('[data-acdc-row-menu-toggle]').forEach(function(btn){ btn.addEventListener('click', function(e){ e.stopPropagation(); var wrapper=btn.closest('[data-acdc-row-menu]'); var dropdown=wrapper?wrapper.querySelector('[data-acdc-row-menu-dropdown]'):null; var open=btn.getAttribute('aria-expanded')==='true'; document.querySelectorAll('[data-acdc-row-menu-dropdown]').forEach(function(menu){ menu.hidden=true; }); document.querySelectorAll('[data-acdc-row-menu-toggle]').forEach(function(other){ other.setAttribute('aria-expanded','false'); }); if(dropdown){ dropdown.hidden=open; } btn.setAttribute('aria-expanded', open ? 'false' : 'true'); }); });
        document.addEventListener('click', function(){ document.querySelectorAll('[data-acdc-row-menu-dropdown]').forEach(function(menu){ menu.hidden=true; }); document.querySelectorAll('[data-acdc-row-menu-toggle]').forEach(function(btn){ btn.setAttribute('aria-expanded','false'); }); });
      })();
    </script>
    <?php
  }


  private function render_front_hot_survey_documents_tab() {
    $search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
    $per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 25;
    if ( ! in_array( $per_page, array( 25, 50, 100 ), true ) ) {
      $per_page = 25;
    }
    $paged = \ACDC\Support\ScreenQuery::readPaged( $_GET );
    $base_tab = 'hot_surveys_documents';
    $base_url = is_admin() ? $this->admin_tab_url( $base_tab ) : $this->portal_page_url( array( 'tab' => $base_tab ) );
    $rows = $this->get_hot_survey_rows( $search );
    $total = count( $rows );
    $total_pages = max( 1, (int) ceil( max( 1, $total ) / $per_page ) );
    if ( $paged > $total_pages ) { $paged = $total_pages; }
    $offset = ( $paged - 1 ) * $per_page;
    $page_rows = array_slice( $rows, $offset, $per_page );
    ?>
    <section class="acdc-section-head"><div><h2>Enquêtes à chaud</h2></div></section>
    <div class="acdc-panel acdc-mb-18">
      <form class="acdc-search-bar" method="get" action="<?php echo esc_url( $base_url ); ?>">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
        <input type="hidden" name="tab" value="hot_surveys_documents">
        <div class="acdc-search-row">
          <input type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="Rechercher">
          <button type="button" class="acdc-filter-toggle acdc-filter-toggle-icons-only" data-acdc-filter-toggle aria-expanded="false" title="Filtres"><?php echo $this->render_inline_icon( 'filter', 18 ); ?> <?php echo $this->render_inline_icon( 'chevron-down', 16 ); ?></button>
        </div>
        <div class="acdc-sessions-filters-panel acdc-documents-filters-panel" data-acdc-filters-panel hidden>
          <div class="acdc-documents-filter-card">
            <label><span>Par page</span>
              <select name="per_page">
                <?php foreach ( array( 25, 50, 100 ) as $size ) : ?>
                  <option value="<?php echo esc_attr( $size ); ?>" <?php selected( $per_page, $size ); ?>><?php echo esc_html( $size ); ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div class="acdc-inline-wrap" style="justify-content:flex-end;margin-top:14px;">
            <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $base_url ); ?>">Réinitialiser</a>
            <button type="submit" class="acdc-button acdc-button-primary">Appliquer</button>
          </div>
        </div>
      </form>
    </div>
    <div class="acdc-panel">
      <?php if ( empty( $page_rows ) ) : ?>
        <div class="acdc-empty-state" style="padding:72px 24px;text-align:center;"><div class="acdc-empty-state-icon" aria-hidden="true"><?php echo $this->render_inline_icon( 'survey', 54 ); ?></div><p style="margin:0;color:var(--acdc-text-muted);">Aucune donnée ne correspond aux critères demandés.</p></div>
      <?php else : ?>
        <div class="acdc-table-wrap">
          <table class="acdc-table acdc-table-hot-surveys-documents">
            <thead>
              <tr>
                <th><input type="checkbox" aria-label="Sélectionner"></th>
                <th>ID</th>
                <th>Apprenant</th>
                <th>Enquête</th>
                <th>Date ajout/passage</th>
                <th>Formation</th>
                <th>Dates de formation</th>
                <th>Durée (H)</th>
                <th>Format</th>
                <th>État dossier</th>
                <th><span class="screen-reader-text">Menu</span></th>
                <th><span class="screen-reader-text">Voir</span></th>
                <th><span class="screen-reader-text">Modifier</span></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ( $page_rows as $row ) : ?>
              <?php
              $entry = $row['registration'];
              $context = $row['context'];
              $document = $context['document'];
              $download_url = $this->get_hot_survey_download_url( $entry, 'attachment' );
              $view_pdf_url = $this->get_hot_survey_download_url( $entry, 'inline' );
              $file_name = $this->get_hot_survey_display_file_name( $entry, $context, $document );
              $view_modal_id = 'acdc-hot-survey-view-' . (int) $entry->id;
              $edit_modal_id = 'acdc-hot-survey-edit-' . (int) $entry->id;
              $export_modal_id = 'acdc-hot-survey-export-' . (int) $entry->id;
              $default_filename = sanitize_file_name( 'details-qcm-enquete-chaud-' . ( ! empty( $context['learner_name'] ) ? $context['learner_name'] : 'apprenant' ) . '-' . date_i18n( 'Ymd' ) );
              ?>
              <tr>
                <td><input type="checkbox" aria-label="Sélectionner cette enquête"></td>
                <td><?php echo esc_html( (int) $entry->id ); ?></td>
                <td><?php echo esc_html( $context['learner_name'] ); ?></td>
                <td><?php echo esc_html( $context['survey_source_label'] ); ?><small><?php echo esc_html( $context['survey_title'] ); ?></small></td>
                <td><?php echo esc_html( ! empty( $context['completed_at_label'] ) ? $context['completed_at_label'] : $this->format_pdf_date( $context['end_date'] ) ); ?></td>
                <td><a href="<?php echo esc_url( $view_pdf_url ); ?>" target="_blank" rel="noopener" class="acdc-program-link"><?php echo esc_html( $context['formation_title'] ); ?></a></td>
                <td><?php echo esc_html( 'Début : ' . $this->format_pdf_date( $context['start_date'] ) . "
Fin : " . $this->format_pdf_date( $context['end_date'] ) ); ?></td>
                <td><?php echo esc_html( $context['duration'] ); ?></td>
                <td><?php echo esc_html( $context['format'] ); ?></td>
                <td><?php echo esc_html( $row['state'] ); ?></td>
                <td>
                  <div class="acdc-row-menu" data-acdc-row-menu>
                    <button type="button" class="acdc-row-menu-toggle" data-acdc-row-menu-toggle aria-expanded="false" title="Actions"><?php echo $this->render_inline_icon( 'more-horizontal', 25 ); ?></button>
                    <div class="acdc-row-menu-dropdown" data-acdc-row-menu-dropdown hidden>
                      <a href="<?php echo esc_url( $download_url ); ?>">Résultats Enquêtes de satisfaction à chaud</a>
                      <a href="#" data-acdc-modal-open="<?php echo esc_attr( $export_modal_id ); ?>">Exporter les détails des QCM</a>
                    </div>
                  </div>
                </td>
                <td><button type="button" class="acdc-row-view-link" data-acdc-modal-open="<?php echo esc_attr( $view_modal_id ); ?>" title="Voir" aria-label="Voir"><?php echo $this->render_inline_icon( 'view', 25 ); ?></button></td>
                <td><button type="button" class="acdc-row-edit-link" data-acdc-modal-open="<?php echo esc_attr( $edit_modal_id ); ?>" title="Modifier" aria-label="Modifier"><?php echo $this->render_inline_icon( 'edit-pencil', 25 ); ?></button></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ( $total_pages > 1 ) : ?>
          <div class="acdc-pagination-wrap"><div class="acdc-pagination"><?php for ( $page = 1; $page <= $total_pages; $page++ ) : ?><a class="<?php echo $page === $paged ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'q' => $search, 'per_page' => $per_page, 'acdc_paged' => $page ), $base_url ) ); ?>"><?php echo esc_html( $page ); ?></a><?php endfor; ?></div><div class="acdc-pagination-summary"><?php echo esc_html( sprintf( '%d-%d de %d', $total ? $offset + 1 : 0, min( $offset + $per_page, $total ), $total ) ); ?></div></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php foreach ( $page_rows as $row ) : ?>
      <?php
      $entry = $row['registration'];
      $context = $row['context'];
      $document = $context['document'];
      $file_name = $this->get_hot_survey_display_file_name( $entry, $context, $document );
      $view_modal_id = 'acdc-hot-survey-view-' . (int) $entry->id;
      $edit_modal_id = 'acdc-hot-survey-edit-' . (int) $entry->id;
      $export_modal_id = 'acdc-hot-survey-export-' . (int) $entry->id;
      $view_pdf_url = $this->get_hot_survey_download_url( $entry, 'inline' );
      $default_filename = sanitize_file_name( 'details-qcm-enquete-chaud-' . ( ! empty( $context['learner_name'] ) ? $context['learner_name'] : 'apprenant' ) . '-' . date_i18n( 'Ymd' ) );
      ?>
      <div class="acdc-modal-shell" id="<?php echo esc_attr( $view_modal_id ); ?>" hidden>
        <div class="acdc-modal-backdrop" data-acdc-modal-close></div>
        <div class="acdc-modal-dialog acdc-modal-dialog-contract">
          <div class="acdc-modal-header"><h4>Voir les enquêtes à chaud :</h4><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button></div>
          <div class="acdc-modal-body">
            <div class="acdc-contract-doc-topbar"><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $view_pdf_url ); ?>" target="_blank" rel="noopener">Voir le document</a></div>
            <div class="acdc-contract-details-grid">
              <div class="acdc-contract-detail-label">Document</div>
              <div><?php echo esc_html( (int) $entry->id ); ?></div>
              <div class="acdc-contract-detail-label">Apprenant</div>
              <div><?php echo esc_html( $context['learner_name'] ); ?></div>
              <div class="acdc-contract-detail-label">Enquête</div>
              <div><?php echo esc_html( $context['survey_source_label'] ); ?>
<?php echo esc_html( $context['survey_title'] ); ?></div>
              <div class="acdc-contract-detail-label">Date Ajout/Passage</div>
              <div><?php echo esc_html( ! empty( $context['completed_at_label'] ) ? $context['completed_at_label'] : $this->format_pdf_date( $context['end_date'] ) ); ?></div>
              <div class="acdc-contract-detail-label">Adresse e-mail</div>
              <div><?php echo esc_html( $context['learner_email'] ); ?></div>
              <div class="acdc-contract-detail-label">Téléphone</div>
              <div><?php echo esc_html( $context['learner_phone'] ); ?></div>
              <div class="acdc-contract-detail-label">Formation</div>
              <div><?php echo esc_html( $context['formation_title'] ); ?></div>
              <div class="acdc-contract-detail-label">Dates de formation</div>
              <div><?php echo esc_html( 'Début : ' . $this->format_pdf_date( $context['start_date'] ) . "
Fin : " . $this->format_pdf_date( $context['end_date'] ) ); ?></div>
              <div class="acdc-contract-detail-label">Durée (h)</div>
              <div><?php echo esc_html( $context['duration'] ); ?></div>
              <div class="acdc-contract-detail-label">Format</div>
              <div><?php echo esc_html( $context['format'] ); ?></div>
              <div class="acdc-contract-detail-label">Statut</div>
              <div><span style="color:#35B37E;">●</span> Complétée</div>
            </div>
          </div>
        </div>
      </div>

      <div class="acdc-modal-shell" id="<?php echo esc_attr( $export_modal_id ); ?>" hidden>
        <div class="acdc-modal-backdrop" data-acdc-modal-close></div>
        <div class="acdc-modal-dialog" style="width:min(720px,92vw)">
          <div class="acdc-modal-header"><h4>Exporter les détails des QCM</h4><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button></div>
          <div class="acdc-modal-body">
            <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
              <?php wp_nonce_field( 'acdc_export_hot_survey_qcm_details_' . (int) $entry->id ); ?>
              <input type="hidden" name="action" value="acdc_export_hot_survey_qcm_details">
              <input type="hidden" name="registration_id" value="<?php echo esc_attr( (int) $entry->id ); ?>">
              <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
              <input type="hidden" name="tab" value="hot_surveys_documents">
              <p style="font-size:20px;color:var(--acdc-text-muted);margin:0 0 26px;">Êtes-vous sûr·e de vouloir exécuter cette action ?</p>
              <div class="acdc-grid-form" style="grid-template-columns:200px minmax(0,1fr);gap:18px 18px;align-items:center;">
                <div><label style="margin:0;">Nom du fichier <span style="color:#ff6b6b;">*</span></label></div>
                <div><input type="text" name="export_filename" value="<?php echo esc_attr( $default_filename ); ?>" placeholder="Nom du fichier" required></div>
                <div><label style="margin:0;">Type <span style="color:#ff6b6b;">*</span></label></div>
                <div>
                  <select name="export_type" required>
                    <option value="excel">Excel</option>
                    <option value="csv">CSV</option>
                  </select>
                </div>
              </div>
              <p class="acdc-actions-end"><button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-close>Annuler</button><button type="submit" class="acdc-button acdc-button-primary">Exécuter l'action</button></p>
            </form>
          </div>
        </div>
      </div>

      <div class="acdc-modal-shell" id="<?php echo esc_attr( $edit_modal_id ); ?>" hidden>
        <div class="acdc-modal-backdrop" data-acdc-modal-close></div>
        <div class="acdc-modal-dialog acdc-modal-dialog-contract">
          <div class="acdc-modal-header"><h4>Modifier les enquêtes à chaud</h4><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button></div>
          <div class="acdc-modal-body">
            <div class="acdc-convocation-edit-topbar"><div>Apprenant : <?php echo esc_html( $context['learner_name'] ); ?></div><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $this->get_hot_survey_download_url( $entry, 'attachment' ) ); ?>">Télécharger le document actuel</a></div>
            <div class="acdc-convocation-edit-warning">Attention, cela va écraser l'ancien document et le remplacer par le nouveau document.</div>
            <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
              <?php wp_nonce_field( 'acdc_update_hot_survey_document_' . (int) $entry->id ); ?>
              <input type="hidden" name="action" value="acdc_update_hot_survey_document">
              <input type="hidden" name="registration_id" value="<?php echo esc_attr( (int) $entry->id ); ?>">
              <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
              <input type="hidden" name="tab" value="hot_surveys_documents">
              <div class="acdc-contract-grid">
                <div class="acdc-contract-label">Enquête de satisfaction à chaud</div>
                <div>
                  <div class="acdc-contract-file-preview">
                    <div class="acdc-contract-file-preview-thumb"><?php echo $this->render_inline_icon( 'document', 30 ); ?></div>
                    <div class="acdc-contract-file-preview-name"><?php echo esc_html( $file_name ); ?></div>
                  </div>
                  <label class="acdc-upload-dropzone">
                    <span class="acdc-button acdc-button-primary">Choisir le fichier</span>
                    <span>Déposez le fichier ou cliquez pour choisir</span>
                    <input type="file" name="hot_survey_document_file" accept="application/pdf" required>
                  </label>
                </div>
              </div>
              <p class="acdc-actions-end"><button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-close>Annuler</button><button type="submit" class="acdc-button acdc-button-primary">Modifier les enquêtes à chaud</button></p>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <style>
      .acdc-table-hot-surveys-documents td,.acdc-table-hot-surveys-documents th{vertical-align:top;white-space:pre-line}.acdc-table-hot-surveys-documents small{display:block;margin-top:4px;color:var(--acdc-text-muted)}
      .acdc-filter-toggle-icons-only{padding:0;border:none;background:transparent;color:var(--acdc-text-muted);display:inline-flex;align-items:center;gap:8px;cursor:pointer}.acdc-documents-filters-panel{max-width:540px;margin-left:auto;margin-right:0}.acdc-documents-filter-card{background:#fff;border:1px solid var(--acdc-border);border-radius:10px;padding:14px 18px;box-shadow:0 10px 24px rgba(28,44,64,.08)}.acdc-documents-filter-card label{display:flex;flex-direction:column;gap:10px;font-size:12px;color:var(--acdc-text-muted);font-weight:700;letter-spacing:.03em;text-transform:uppercase}.acdc-empty-state-icon{display:inline-flex;color:#DCE4EC;line-height:1;margin-bottom:14px}
      .acdc-convocation-edit-topbar{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:16px 18px;border-radius:10px;background:var(--acdc-bg);color:var(--acdc-text-muted);margin-bottom:0}.acdc-convocation-edit-warning{background:linear-gradient(90deg,#C9A409 0%,#E9C77C 100%);color:#0B0706;text-align:center;padding:10px 14px;border-radius:0 0 6px 6px;margin-bottom:18px}
      .acdc-modal-dialog-contract{width:min(1160px,94vw)}
      .acdc-contract-doc-topbar{display:flex;justify-content:flex-start;margin-bottom:18px}.acdc-contract-details-grid{display:grid;grid-template-columns:minmax(180px,280px) minmax(0,1fr);gap:0;border-top:1px solid var(--acdc-border);white-space:pre-line}.acdc-contract-detail-label{padding:14px 12px;color:var(--acdc-text-muted);border-bottom:1px solid var(--acdc-border)}.acdc-contract-details-grid>div:nth-child(2n){padding:14px 12px;border-bottom:1px solid var(--acdc-border);color:var(--acdc-text-muted)}.acdc-contract-file-preview{display:flex;flex-direction:column;gap:8px;width:280px;max-width:100%;margin-bottom:12px}.acdc-contract-file-preview-thumb{display:flex;align-items:center;justify-content:center;width:280px;height:220px;border:1px solid var(--acdc-border);border-radius:10px;background:var(--acdc-bg);color:var(--acdc-text-light)}.acdc-contract-file-preview-name{font-size:12px;color:var(--acdc-text-muted);word-break:break-all}.acdc-upload-dropzone{display:flex;align-items:center;gap:14px;padding:14px;border:2px dashed var(--acdc-border);border-radius:10px;cursor:pointer}.acdc-upload-dropzone input{display:none}.acdc-row-menu{position:relative;display:inline-flex;align-items:center;justify-content:center}.acdc-row-menu-toggle{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:none;background:transparent;color:var(--acdc-text-muted);text-decoration:none;cursor:pointer}.acdc-row-menu-dropdown{position:absolute;top:34px;right:0;min-width:260px;padding:8px 0;background:#fff;border:1px solid var(--acdc-border);border-radius:10px;box-shadow:0 10px 24px rgba(28,44,64,.12);z-index:30}.acdc-row-menu-dropdown a{display:block;padding:10px 14px;color:var(--acdc-text);text-decoration:none}.acdc-row-menu-dropdown a:hover{background:var(--acdc-bg)}
      @media (max-width:900px){.acdc-contract-details-grid{grid-template-columns:1fr}.acdc-contract-detail-label{border-bottom:none;padding-bottom:4px}.acdc-contract-details-grid>div:nth-child(2n){padding-top:0}.acdc-convocation-edit-topbar{flex-direction:column;align-items:flex-start}}
    </style>
    <script>
      (function(){
        if(window.__acdcHotSurveyDocumentsInit){return;} window.__acdcHotSurveyDocumentsInit = true;
        function openModal(id){ var modal=document.getElementById(id); if(modal){ modal.hidden=false; document.body.classList.add('acdc-modal-open'); } }
        function closeModal(modal){ if(!modal){return;} modal.hidden=true; if(!document.querySelector('.acdc-modal-shell:not([hidden])')){ document.body.classList.remove('acdc-modal-open'); } }
        var filterToggle = document.querySelector('[data-acdc-filter-toggle]');
        var filterPanel = document.querySelector('[data-acdc-filters-panel]');
        if(filterToggle && filterPanel){ filterToggle.addEventListener('click', function(){ var expanded = filterToggle.getAttribute('aria-expanded') === 'true'; filterToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true'); filterPanel.hidden = expanded; }); }
        document.querySelectorAll('[data-acdc-modal-open]').forEach(function(btn){ btn.addEventListener('click', function(e){ e.preventDefault(); openModal(btn.getAttribute('data-acdc-modal-open')); }); });
        document.querySelectorAll('[data-acdc-modal-close]').forEach(function(btn){ btn.addEventListener('click', function(){ closeModal(btn.closest('.acdc-modal-shell')); }); });
        document.querySelectorAll('.acdc-modal-shell').forEach(function(modal){ modal.addEventListener('click', function(e){ if(e.target === modal || e.target.hasAttribute('data-acdc-modal-close')){ closeModal(modal); } }); });
        document.addEventListener('keydown', function(e){ if(e.key === 'Escape'){ document.querySelectorAll('.acdc-modal-shell:not([hidden])').forEach(closeModal); } });
        document.querySelectorAll('[data-acdc-row-menu-toggle]').forEach(function(btn){ btn.addEventListener('click', function(e){ e.stopPropagation(); var wrapper=btn.closest('[data-acdc-row-menu]'); var dropdown=wrapper?wrapper.querySelector('[data-acdc-row-menu-dropdown]'):null; var open=btn.getAttribute('aria-expanded')==='true'; document.querySelectorAll('[data-acdc-row-menu-dropdown]').forEach(function(menu){ menu.hidden=true; }); document.querySelectorAll('[data-acdc-row-menu-toggle]').forEach(function(other){ other.setAttribute('aria-expanded','false'); }); if(dropdown){ dropdown.hidden=open; } btn.setAttribute('aria-expanded', open ? 'false' : 'true'); }); });
        document.addEventListener('click', function(){ document.querySelectorAll('[data-acdc-row-menu-dropdown]').forEach(function(menu){ menu.hidden=true; }); document.querySelectorAll('[data-acdc-row-menu-toggle]').forEach(function(btn){ btn.setAttribute('aria-expanded','false'); }); });
      })();
    </script>
    <?php
  }


  private function render_front_trainer_surveys_documents_tab() {
    $scope = $this->get_trainer_surveys_documents_scope();
    $state = $this->get_trainer_surveys_documents_state();

    if ( '' === $scope ) {
      $this->render_front_trainer_surveys_documents_hub();
      return;
    }

    if ( '' === $state ) {
      $this->render_front_trainer_surveys_documents_scope( $scope );
      return;
    }

    $this->render_front_trainer_surveys_documents_state( $scope, $state );
  }


  private function render_front_trainer_surveys_documents_hub() {
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'trainer_surveys_documents' ) );
    ?>
    <section class="acdc-section-head" style="align-items:flex-start;">
      <div>
        <h2>Enquêtes formateurs</h2>
      </div>
    </section>
    <div class="acdc-grid-2cols" style="gap:18px;">
      <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'trainer_surveys_documents', 'scope' => 'action' ), $base_url ) ); ?>" class="acdc-panel" style="min-height:98px;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;text-decoration:none;">
        <h3 style="margin:0 0 10px;color:#C5A253;font-size:18px;">Actions de formation</h3>
        <p style="margin:0;color:var(--acdc-text-muted);">Enquêtes formateurs</p>
      </a>
      <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'trainer_surveys_documents', 'scope' => 'annual' ), $base_url ) ); ?>" class="acdc-panel" style="min-height:98px;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;text-decoration:none;">
        <h3 style="margin:0 0 10px;color:#C5A253;font-size:18px;">Annuelles</h3>
        <p style="margin:0;color:var(--acdc-text-muted);">Enquêtes formateurs</p>
      </a>
    </div>
    <?php
  }


  private function render_front_trainer_surveys_documents_scope( $scope ) {
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'trainer_surveys_documents' ) );
    $heading  = 'annual' === $scope ? 'Enquêtes formateurs annuelles' : 'Enquêtes formateurs';
    ?>
    <div class="acdc-grid-2cols acdc-mb-18" style="gap:18px;">
      <div class="acdc-panel" style="min-height:46px;display:flex;align-items:center;justify-content:center;">
        <button type="button" class="acdc-button acdc-button-soft" style="pointer-events:none;">Guide d'utilisation</button>
      </div>
      <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'trainer_surveys_documents', 'scope' => $scope, 'state' => 'pending' ), $base_url ) ); ?>" class="acdc-panel" style="min-height:46px;display:flex;align-items:center;justify-content:center;text-decoration:none;">
        <span class="acdc-button acdc-button-soft">À compléter</span>
      </a>
    </div>
    <section class="acdc-section-head"><div><h2><?php echo esc_html( $heading ); ?></h2></div></section>
    <?php $this->render_front_trainer_surveys_documents_empty_table(); ?>
    <?php
  }


  private function render_front_trainer_surveys_documents_state( $scope, $state ) {
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'trainer_surveys_documents' ) );
    $back_url = add_query_arg( array( 'tab' => 'trainer_surveys_documents', 'scope' => $scope ), $base_url );
    $title = 'relance' === $state ? 'À Relancer' : 'À compléter';
    ?>
    <div class="acdc-mb-18">
      <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $back_url ); ?>" style="min-width:44px;padding:10px 14px;">←</a>
    </div>
    <?php if ( 'pending' === $state ) : ?>
      <div class="acdc-grid-2cols acdc-mb-18" style="gap:18px;">
        <div></div>
        <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'trainer_surveys_documents', 'scope' => $scope, 'state' => 'relance' ), $base_url ) ); ?>" class="acdc-panel" style="min-height:46px;display:flex;align-items:center;justify-content:center;text-decoration:none;">
          <span class="acdc-button acdc-button-soft">Relances</span>
        </a>
      </div>
    <?php endif; ?>
    <?php /* ACDC 3.25.291 — BLOC RETIRÉ : IL N'A JAMAIS PU S'AFFICHER.
             Un panneau « Supervision automatique — enquêtes à chaud » se trouvait
             ici, conditionné à $prefill_source_type — une variable qui n'existe
             pas dans cette fonction, dont les paramètres sont $scope et $state.
             La condition était donc fausse à chaque affichage, depuis toujours,
             et l'aperçu se calculait pour rien à chaque ouverture de l'écran.
             Le panneau reste dans l'historique du dépôt : il pourra être branché
             sur l'écran des enquêtes à chaud, là où il a du sens. */ ?>
    <section class="acdc-section-head"><div><h2><?php echo esc_html( $title ); ?></h2></div></section>
    <?php $this->render_front_trainer_surveys_documents_empty_table(); ?>
    <?php
  }


  private function render_front_trainer_surveys_documents_empty_table() {
    ?>
    <div class="acdc-panel acdc-mb-18">
      <form method="get" action="">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
        <input type="hidden" name="tab" value="trainer_surveys_documents">
        <?php if ( isset( $_GET['scope'] ) ) : ?><input type="hidden" name="scope" value="<?php echo esc_attr( sanitize_key( wp_unslash( $_GET['scope'] ) ) ); ?>"><?php endif; ?>
        <?php if ( isset( $_GET['state'] ) ) : ?><input type="hidden" name="state" value="<?php echo esc_attr( sanitize_key( wp_unslash( $_GET['state'] ) ) ); ?>"><?php endif; ?>
        <div class="acdc-inline-wrap acdc-inline-wrap-center">
          <input type="search" name="q" value="" placeholder="Rechercher" style="max-width:420px;">
        </div>
      </form>
    </div>
    <div class="acdc-panel">
      <div style="display:flex;justify-content:flex-end;align-items:center;padding:8px 0 18px;">
        <button type="button" class="acdc-filter-toggle-icons-only" aria-label="Filtres" title="Filtres"><?php echo $this->render_inline_icon( 'filter', 18 ); ?></button>
      </div>
      <div class="acdc-empty-state" style="padding:72px 24px;text-align:center;">
        <div class="acdc-empty-state-icon" aria-hidden="true"><?php echo $this->render_inline_icon( 'survey', 54 ); ?></div>
        <p style="margin:0;color:var(--acdc-text-muted);">Aucune donnée ne correspond aux critères demandés.</p>
      </div>
    </div>
    <style>
      .acdc-filter-toggle-icons-only{padding:0;border:none;background:transparent;color:var(--acdc-text-muted);display:inline-flex;align-items:center;gap:8px;cursor:pointer}
      .acdc-filter-toggle-icons-only:hover{color:var(--acdc-text)}
    </style>
    <?php
  }




  private function render_front_company_surveys_documents_tab() {
    $state = $this->get_company_surveys_documents_state();

    if ( '' === $state ) {
      $this->render_front_company_surveys_documents_home();
      return;
    }

    $this->render_front_company_surveys_documents_state( $state );
  }


  private function render_front_company_surveys_documents_home() {
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'company_surveys_documents' ) );
    ?>
    <div class="acdc-grid-2cols acdc-mb-18" style="gap:18px;">
      <div class="acdc-panel" style="min-height:46px;display:flex;align-items:center;justify-content:center;">
        <button type="button" class="acdc-button acdc-button-soft" style="pointer-events:none;">Guide d'utilisation</button>
      </div>
      <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'company_surveys_documents', 'state' => 'pending' ), $base_url ) ); ?>" class="acdc-panel" style="min-height:46px;display:flex;align-items:center;justify-content:center;text-decoration:none;">
        <span class="acdc-button acdc-button-soft">À compléter</span>
      </a>
    </div>
    <section class="acdc-section-head"><div><h2>Enquêtes entreprises</h2></div></section>
    <?php $this->render_front_company_surveys_documents_empty_table(); ?>
    <?php
  }


  private function render_front_company_surveys_documents_state( $state ) {
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'company_surveys_documents' ) );
    $title = 'relance' === $state ? 'À Relancer' : 'À compléter';
    ?>
    <div class="acdc-mb-18">
      <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $base_url ); ?>" style="min-width:44px;padding:10px 14px;">←</a>
    </div>
    <?php if ( 'pending' === $state ) : ?>
      <div class="acdc-grid-2cols acdc-mb-18" style="gap:18px;">
        <div></div>
        <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'company_surveys_documents', 'state' => 'relance' ), $base_url ) ); ?>" class="acdc-panel" style="min-height:46px;display:flex;align-items:center;justify-content:center;text-decoration:none;">
          <span class="acdc-button acdc-button-soft">À Relancer</span>
        </a>
      </div>
    <?php endif; ?>
    <section class="acdc-section-head"><div><h2><?php echo esc_html( $title ); ?></h2></div></section>
    <?php $this->render_front_company_surveys_documents_empty_table(); ?>
    <?php
  }


  private function render_front_company_surveys_documents_empty_table() {
    ?>
    <div class="acdc-panel acdc-mb-18">
      <form method="get" action="">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
        <input type="hidden" name="tab" value="company_surveys_documents">
        <?php if ( isset( $_GET['state'] ) ) : ?><input type="hidden" name="state" value="<?php echo esc_attr( sanitize_key( wp_unslash( $_GET['state'] ) ) ); ?>"><?php endif; ?>
        <div class="acdc-inline-wrap acdc-inline-wrap-center">
          <input type="search" name="q" value="" placeholder="Rechercher" style="max-width:420px;">
        </div>
      </form>
    </div>
    <div class="acdc-panel">
      <div style="display:flex;justify-content:flex-end;align-items:center;padding:8px 0 18px;">
        <button type="button" class="acdc-filter-toggle-icons-only" aria-label="Filtres" title="Filtres"><?php echo $this->render_inline_icon( 'filter', 18 ); ?></button>
      </div>
      <div class="acdc-empty-state" style="padding:72px 24px;text-align:center;">
        <div class="acdc-empty-state-icon" aria-hidden="true"><?php echo $this->render_inline_icon( 'survey', 54 ); ?></div>
        <p style="margin:0;color:var(--acdc-text-muted);">Aucune donnée ne correspond aux critères demandés.</p>
      </div>
    </div>
    <style>
      .acdc-filter-toggle-icons-only{padding:0;border:none;background:transparent;color:var(--acdc-text-muted);display:inline-flex;align-items:center;gap:8px;cursor:pointer}
      .acdc-filter-toggle-icons-only:hover{color:var(--acdc-text)}
    </style>
    <?php
  }




  private function render_front_funder_surveys_documents_tab() {
    $state = $this->get_funder_surveys_documents_state();

    if ( '' === $state ) {
      $this->render_front_funder_surveys_documents_home();
      return;
    }

    $this->render_front_funder_surveys_documents_state( $state );
  }


  private function render_front_funder_surveys_documents_home() {
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'funder_surveys_documents' ) );
    ?>
    <div class="acdc-grid-2cols acdc-mb-18" style="gap:18px;">
      <div class="acdc-panel" style="min-height:46px;display:flex;align-items:center;justify-content:center;">
        <button type="button" class="acdc-button acdc-button-soft" style="pointer-events:none;">Guide d'utilisation</button>
      </div>
      <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'funder_surveys_documents', 'state' => 'pending' ), $base_url ) ); ?>" class="acdc-panel" style="min-height:46px;display:flex;align-items:center;justify-content:center;text-decoration:none;">
        <span class="acdc-button acdc-button-soft">À compléter</span>
      </a>
    </div>
    <section class="acdc-section-head"><div><h2>Enquêtes financeurs</h2></div></section>
    <?php $this->render_front_funder_surveys_documents_empty_table(); ?>
    <?php
  }


  private function render_front_funder_surveys_documents_state( $state ) {
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'funder_surveys_documents' ) );
    $title = 'relance' === $state ? 'À Relancer' : 'À compléter';
    ?>
    <div class="acdc-mb-18">
      <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $base_url ); ?>" style="min-width:44px;padding:10px 14px;">←</a>
    </div>
    <?php if ( 'pending' === $state ) : ?>
      <div class="acdc-grid-2cols acdc-mb-18" style="gap:18px;">
        <div></div>
        <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'funder_surveys_documents', 'state' => 'relance' ), $base_url ) ); ?>" class="acdc-panel" style="min-height:46px;display:flex;align-items:center;justify-content:center;text-decoration:none;">
          <span class="acdc-button acdc-button-soft">À Relancer</span>
        </a>
      </div>
    <?php endif; ?>
    <section class="acdc-section-head"><div><h2><?php echo esc_html( $title ); ?></h2></div></section>
    <?php $this->render_front_funder_surveys_documents_empty_table(); ?>
    <?php
  }


  private function render_front_funder_surveys_documents_empty_table() {
    ?>
    <div class="acdc-panel acdc-mb-18">
      <form method="get" action="">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
        <input type="hidden" name="tab" value="funder_surveys_documents">
        <?php if ( isset( $_GET['state'] ) ) : ?><input type="hidden" name="state" value="<?php echo esc_attr( sanitize_key( wp_unslash( $_GET['state'] ) ) ); ?>"><?php endif; ?>
        <div class="acdc-inline-wrap acdc-inline-wrap-center">
          <input type="search" name="q" value="" placeholder="Rechercher" style="max-width:420px;">
        </div>
      </form>
    </div>
    <div class="acdc-panel">
      <div style="display:flex;justify-content:flex-end;align-items:center;padding:8px 0 18px;">
        <button type="button" class="acdc-filter-toggle-icons-only" aria-label="Filtres" title="Filtres"><?php echo $this->render_inline_icon( 'filter', 18 ); ?></button>
      </div>
      <div class="acdc-empty-state" style="padding:72px 24px;text-align:center;">
        <div class="acdc-empty-state-icon" aria-hidden="true"><?php echo $this->render_inline_icon( 'survey', 54 ); ?></div>
        <p style="margin:0;color:var(--acdc-text-muted);">Aucune donnée ne correspond aux critères demandés.</p>
      </div>
    </div>
    <style>
      .acdc-filter-toggle-icons-only{padding:0;border:none;background:transparent;color:var(--acdc-text-muted);display:inline-flex;align-items:center;gap:8px;cursor:pointer}
      .acdc-filter-toggle-icons-only:hover{color:var(--acdc-text)}
    </style>
    <?php
  }




  private function render_front_cold_survey_documents_tab() {
    $search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
    $per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 25;
    if ( ! in_array( $per_page, array( 25, 50, 100 ), true ) ) {
      $per_page = 25;
    }
    $paged = \ACDC\Support\ScreenQuery::readPaged( $_GET );
    $base_tab = 'cold_surveys_documents';
    $base_url = is_admin() ? $this->admin_tab_url( $base_tab ) : $this->portal_page_url( array( 'tab' => $base_tab ) );
    $rows = $this->get_cold_survey_rows( $search );
    $total = count( $rows );
    $total_pages = max( 1, (int) ceil( max( 1, $total ) / $per_page ) );
    if ( $paged > $total_pages ) { $paged = $total_pages; }
    $offset = ( $paged - 1 ) * $per_page;
    $page_rows = array_slice( $rows, $offset, $per_page );
    ?>
    <section class="acdc-section-head"><div><h2>Enquêtes à froid</h2></div></section>
    <div class="acdc-panel acdc-mb-18">
      <form class="acdc-search-bar" method="get" action="<?php echo esc_url( $base_url ); ?>">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
        <input type="hidden" name="tab" value="cold_surveys_documents">
        <div class="acdc-search-row">
          <input type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="Rechercher">
          <button type="button" class="acdc-filter-toggle acdc-filter-toggle-icons-only" data-acdc-filter-toggle aria-expanded="false" title="Filtres"><?php echo $this->render_inline_icon( 'filter', 18 ); ?> <?php echo $this->render_inline_icon( 'chevron-down', 16 ); ?></button>
        </div>
        <div class="acdc-sessions-filters-panel acdc-documents-filters-panel" data-acdc-filters-panel hidden>
          <div class="acdc-documents-filter-card">
            <label><span>Par page</span>
              <select name="per_page">
                <?php foreach ( array( 25, 50, 100 ) as $size ) : ?>
                  <option value="<?php echo esc_attr( $size ); ?>" <?php selected( $per_page, $size ); ?>><?php echo esc_html( $size ); ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <div class="acdc-inline-wrap" style="justify-content:flex-end;margin-top:14px;">
            <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $base_url ); ?>">Réinitialiser</a>
            <button type="submit" class="acdc-button acdc-button-primary">Appliquer</button>
          </div>
        </div>
      </form>
    </div>
    <div class="acdc-panel">
      <?php if ( empty( $page_rows ) ) : ?>
        <div class="acdc-empty-state" style="padding:72px 24px;text-align:center;"><div class="acdc-empty-state-icon" aria-hidden="true"><?php echo $this->render_inline_icon( 'survey', 54 ); ?></div><p style="margin:0;color:var(--acdc-text-muted);">Aucune donnée ne correspond aux critères demandés.</p></div>
      <?php else : ?>
        <div class="acdc-table-wrap">
          <table class="acdc-table acdc-table-cold-surveys-documents">
            <thead>
              <tr>
                <th><input type="checkbox" aria-label="Sélectionner"></th>
                <th>ID</th>
                <th>Apprenant</th>
                <th>Enquête</th>
                <th>Date ajout/passage</th>
                <th>Formation</th>
                <th>Dates de formation</th>
                <th>Durée (H)</th>
                <th>Format</th>
                <th>État dossier</th>
                <th><span class="screen-reader-text">Menu</span></th>
                <th><span class="screen-reader-text">Voir</span></th>
                <th><span class="screen-reader-text">Modifier</span></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ( $page_rows as $row ) : ?>
              <?php
              $entry = $row['registration'];
              $context = $row['context'];
              $document = $context['document'];
              $download_url = $this->get_cold_survey_download_url( $entry, 'attachment' );
              $view_pdf_url = $this->get_cold_survey_download_url( $entry, 'inline' );
              $file_name = $this->get_cold_survey_display_file_name( $entry, $context, $document );
              $view_modal_id = 'acdc-cold-survey-view-' . (int) $entry->id;
              $edit_modal_id = 'acdc-cold-survey-edit-' . (int) $entry->id;
              $export_modal_id = 'acdc-cold-survey-export-' . (int) $entry->id;
              $default_filename = sanitize_file_name( 'details-qcm-enquete-froid-' . ( ! empty( $context['learner_name'] ) ? $context['learner_name'] : 'apprenant' ) . '-' . date_i18n( 'Ymd' ) );
              ?>
              <tr>
                <td><input type="checkbox" aria-label="Sélectionner cette enquête"></td>
                <td><?php echo esc_html( (int) $entry->id ); ?></td>
                <td><?php echo esc_html( $context['learner_name'] ); ?></td>
                <td><?php echo esc_html( $context['survey_source_label'] ); ?><small><?php echo esc_html( $context['survey_title'] ); ?></small></td>
                <td><?php echo esc_html( ! empty( $context['completed_at_label'] ) ? $context['completed_at_label'] : $this->format_pdf_date( $context['end_date'] ) ); ?></td>
                <td><a href="<?php echo esc_url( $view_pdf_url ); ?>" target="_blank" rel="noopener" class="acdc-program-link"><?php echo esc_html( $context['formation_title'] ); ?></a></td>
                <td><?php echo esc_html( 'Début : ' . $this->format_pdf_date( $context['start_date'] ) . "
Fin : " . $this->format_pdf_date( $context['end_date'] ) ); ?></td>
                <td><?php echo esc_html( $context['duration'] ); ?></td>
                <td><?php echo esc_html( $context['format'] ); ?></td>
                <td><?php echo esc_html( $row['state'] ); ?></td>
                <td>
                  <div class="acdc-row-menu" data-acdc-row-menu>
                    <button type="button" class="acdc-row-menu-toggle" data-acdc-row-menu-toggle aria-expanded="false" title="Actions"><?php echo $this->render_inline_icon( 'more-horizontal', 25 ); ?></button>
                    <div class="acdc-row-menu-dropdown" data-acdc-row-menu-dropdown hidden>
                      <a href="<?php echo esc_url( $download_url ); ?>">Résultats Enquêtes de satisfaction à froid</a>
                      <a href="#" data-acdc-modal-open="<?php echo esc_attr( $export_modal_id ); ?>">Exporter les détails des QCM</a>
                    </div>
                  </div>
                </td>
                <td><button type="button" class="acdc-row-view-link" data-acdc-modal-open="<?php echo esc_attr( $view_modal_id ); ?>" title="Voir" aria-label="Voir"><?php echo $this->render_inline_icon( 'view', 25 ); ?></button></td>
                <td><button type="button" class="acdc-row-edit-link" data-acdc-modal-open="<?php echo esc_attr( $edit_modal_id ); ?>" title="Modifier" aria-label="Modifier"><?php echo $this->render_inline_icon( 'edit-pencil', 25 ); ?></button></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ( $total_pages > 1 ) : ?>
          <div class="acdc-pagination-wrap"><div class="acdc-pagination"><?php for ( $page = 1; $page <= $total_pages; $page++ ) : ?><a class="<?php echo $page === $paged ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'q' => $search, 'per_page' => $per_page, 'acdc_paged' => $page ), $base_url ) ); ?>"><?php echo esc_html( $page ); ?></a><?php endfor; ?></div><div class="acdc-pagination-summary"><?php echo esc_html( sprintf( '%d-%d de %d', $total ? $offset + 1 : 0, min( $offset + $per_page, $total ), $total ) ); ?></div></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php foreach ( $page_rows as $row ) : ?>
      <?php
      $entry = $row['registration'];
      $context = $row['context'];
      $document = $context['document'];
      $file_name = $this->get_cold_survey_display_file_name( $entry, $context, $document );
      $view_modal_id = 'acdc-cold-survey-view-' . (int) $entry->id;
      $edit_modal_id = 'acdc-cold-survey-edit-' . (int) $entry->id;
      $export_modal_id = 'acdc-cold-survey-export-' . (int) $entry->id;
      $view_pdf_url = $this->get_cold_survey_download_url( $entry, 'inline' );
      $default_filename = sanitize_file_name( 'details-qcm-enquete-froid-' . ( ! empty( $context['learner_name'] ) ? $context['learner_name'] : 'apprenant' ) . '-' . date_i18n( 'Ymd' ) );
      ?>
      <div class="acdc-modal-shell" id="<?php echo esc_attr( $view_modal_id ); ?>" hidden>
        <div class="acdc-modal-backdrop" data-acdc-modal-close></div>
        <div class="acdc-modal-dialog acdc-modal-dialog-contract">
          <div class="acdc-modal-header"><h4>Voir les enquêtes à froid :</h4><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button></div>
          <div class="acdc-modal-body">
            <div class="acdc-contract-doc-topbar"><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $view_pdf_url ); ?>" target="_blank" rel="noopener">Voir le document</a></div>
            <div class="acdc-contract-details-grid">
              <div class="acdc-contract-detail-label">Document</div>
              <div><?php echo esc_html( (int) $entry->id ); ?></div>
              <div class="acdc-contract-detail-label">Apprenant</div>
              <div><?php echo esc_html( $context['learner_name'] ); ?></div>
              <div class="acdc-contract-detail-label">Enquête</div>
              <div><?php echo esc_html( $context['survey_source_label'] ); ?>
<?php echo esc_html( $context['survey_title'] ); ?></div>
              <div class="acdc-contract-detail-label">Date Ajout/Passage</div>
              <div><?php echo esc_html( ! empty( $context['completed_at_label'] ) ? $context['completed_at_label'] : $this->format_pdf_date( $context['end_date'] ) ); ?></div>
              <div class="acdc-contract-detail-label">Adresse e-mail</div>
              <div><?php echo esc_html( $context['learner_email'] ); ?></div>
              <div class="acdc-contract-detail-label">Téléphone</div>
              <div><?php echo esc_html( $context['learner_phone'] ); ?></div>
              <div class="acdc-contract-detail-label">Formation</div>
              <div><?php echo esc_html( $context['formation_title'] ); ?></div>
              <div class="acdc-contract-detail-label">Dates de formation</div>
              <div><?php echo esc_html( 'Début : ' . $this->format_pdf_date( $context['start_date'] ) . "
Fin : " . $this->format_pdf_date( $context['end_date'] ) ); ?></div>
              <div class="acdc-contract-detail-label">Durée (h)</div>
              <div><?php echo esc_html( $context['duration'] ); ?></div>
              <div class="acdc-contract-detail-label">Format</div>
              <div><?php echo esc_html( $context['format'] ); ?></div>
              <div class="acdc-contract-detail-label">Statut</div>
              <div><span style="color:#35B37E;">●</span> Complétée</div>
            </div>
          </div>
        </div>
      </div>

      <div class="acdc-modal-shell" id="<?php echo esc_attr( $export_modal_id ); ?>" hidden>
        <div class="acdc-modal-backdrop" data-acdc-modal-close></div>
        <div class="acdc-modal-dialog" style="width:min(720px,92vw)">
          <div class="acdc-modal-header"><h4>Exporter les détails des QCM</h4><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button></div>
          <div class="acdc-modal-body">
            <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
              <?php wp_nonce_field( 'acdc_export_cold_survey_qcm_details_' . (int) $entry->id ); ?>
              <input type="hidden" name="action" value="acdc_export_cold_survey_qcm_details">
              <input type="hidden" name="registration_id" value="<?php echo esc_attr( (int) $entry->id ); ?>">
              <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
              <input type="hidden" name="tab" value="cold_surveys_documents">
              <p style="font-size:20px;color:var(--acdc-text-muted);margin:0 0 26px;">Êtes-vous sûr·e de vouloir exécuter cette action ?</p>
              <div class="acdc-grid-form" style="grid-template-columns:200px minmax(0,1fr);gap:18px 18px;align-items:center;">
                <div><label style="margin:0;">Nom du fichier <span style="color:#ff6b6b;">*</span></label></div>
                <div><input type="text" name="export_filename" value="<?php echo esc_attr( $default_filename ); ?>" placeholder="Nom du fichier" required></div>
                <div><label style="margin:0;">Type <span style="color:#ff6b6b;">*</span></label></div>
                <div>
                  <select name="export_type" required>
                    <option value="excel">Excel</option>
                    <option value="csv">CSV</option>
                  </select>
                </div>
              </div>
              <p class="acdc-actions-end"><button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-close>Annuler</button><button type="submit" class="acdc-button acdc-button-primary">Exécuter l'action</button></p>
            </form>
          </div>
        </div>
      </div>

      <div class="acdc-modal-shell" id="<?php echo esc_attr( $edit_modal_id ); ?>" hidden>
        <div class="acdc-modal-backdrop" data-acdc-modal-close></div>
        <div class="acdc-modal-dialog acdc-modal-dialog-contract">
          <div class="acdc-modal-header"><h4>Modifier les enquêtes à froid</h4><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button></div>
          <div class="acdc-modal-body">
            <div class="acdc-convocation-edit-topbar"><div>Apprenant : <?php echo esc_html( $context['learner_name'] ); ?></div><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $this->get_cold_survey_download_url( $entry, 'attachment' ) ); ?>">Télécharger le document actuel</a></div>
            <div class="acdc-convocation-edit-warning">Attention, cela va écraser l'ancien document et le remplacer par le nouveau document.</div>
            <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
              <?php wp_nonce_field( 'acdc_update_cold_survey_document_' . (int) $entry->id ); ?>
              <input type="hidden" name="action" value="acdc_update_cold_survey_document">
              <input type="hidden" name="registration_id" value="<?php echo esc_attr( (int) $entry->id ); ?>">
              <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
              <input type="hidden" name="tab" value="cold_surveys_documents">
              <div class="acdc-contract-grid">
                <div class="acdc-contract-label">Enquête de satisfaction à froid</div>
                <div>
                  <div class="acdc-contract-file-preview">
                    <div class="acdc-contract-file-preview-thumb"><?php echo $this->render_inline_icon( 'document', 30 ); ?></div>
                    <div class="acdc-contract-file-preview-name"><?php echo esc_html( $file_name ); ?></div>
                  </div>
                  <label class="acdc-upload-dropzone">
                    <span class="acdc-button acdc-button-primary">Choisir le fichier</span>
                    <span>Déposez le fichier ou cliquez pour choisir</span>
                    <input type="file" name="cold_survey_document_file" accept="application/pdf" required>
                  </label>
                </div>
              </div>
              <p class="acdc-actions-end"><button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-close>Annuler</button><button type="submit" class="acdc-button acdc-button-primary">Modifier les enquêtes à froid</button></p>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <style>
      .acdc-table-cold-surveys-documents td,.acdc-table-cold-surveys-documents th{vertical-align:top;white-space:pre-line}.acdc-table-cold-surveys-documents small{display:block;margin-top:4px;color:var(--acdc-text-muted)}
      .acdc-filter-toggle-icons-only{padding:0;border:none;background:transparent;color:var(--acdc-text-muted);display:inline-flex;align-items:center;gap:8px;cursor:pointer}.acdc-documents-filters-panel{max-width:540px;margin-left:auto;margin-right:0}.acdc-documents-filter-card{background:#fff;border:1px solid var(--acdc-border);border-radius:10px;padding:14px 18px;box-shadow:0 10px 24px rgba(28,44,64,.08)}.acdc-documents-filter-card label{display:flex;flex-direction:column;gap:10px;font-size:12px;color:var(--acdc-text-muted);font-weight:700;letter-spacing:.03em;text-transform:uppercase}.acdc-empty-state-icon{display:inline-flex;color:#DCE4EC;line-height:1;margin-bottom:14px}
      .acdc-convocation-edit-topbar{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:16px 18px;border-radius:10px;background:var(--acdc-bg);color:var(--acdc-text-muted);margin-bottom:0}.acdc-convocation-edit-warning{background:linear-gradient(90deg,#C9A409 0%,#E9C77C 100%);color:#0B0706;text-align:center;padding:10px 14px;border-radius:0 0 6px 6px;margin-bottom:18px}
      .acdc-modal-dialog-contract{width:min(1160px,94vw)}
      .acdc-contract-doc-topbar{display:flex;justify-content:flex-start;margin-bottom:18px}.acdc-contract-details-grid{display:grid;grid-template-columns:minmax(180px,280px) minmax(0,1fr);gap:0;border-top:1px solid var(--acdc-border);white-space:pre-line}.acdc-contract-detail-label{padding:14px 12px;color:var(--acdc-text-muted);border-bottom:1px solid var(--acdc-border)}.acdc-contract-details-grid>div:nth-child(2n){padding:14px 12px;border-bottom:1px solid var(--acdc-border);color:var(--acdc-text-muted)}.acdc-contract-file-preview{display:flex;flex-direction:column;gap:8px;width:280px;max-width:100%;margin-bottom:12px}.acdc-contract-file-preview-thumb{display:flex;align-items:center;justify-content:center;width:280px;height:220px;border:1px solid var(--acdc-border);border-radius:10px;background:var(--acdc-bg);color:var(--acdc-text-light)}.acdc-contract-file-preview-name{font-size:12px;color:var(--acdc-text-muted);word-break:break-all}.acdc-upload-dropzone{display:flex;align-items:center;gap:14px;padding:14px;border:2px dashed var(--acdc-border);border-radius:10px;cursor:pointer}.acdc-upload-dropzone input{display:none}.acdc-row-menu{position:relative;display:inline-flex;align-items:center;justify-content:center}.acdc-row-menu-toggle{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:none;background:transparent;color:var(--acdc-text-muted);text-decoration:none;cursor:pointer}.acdc-row-menu-dropdown{position:absolute;top:34px;right:0;min-width:260px;padding:8px 0;background:#fff;border:1px solid var(--acdc-border);border-radius:10px;box-shadow:0 10px 24px rgba(28,44,64,.12);z-index:30}.acdc-row-menu-dropdown a{display:block;padding:10px 14px;color:var(--acdc-text);text-decoration:none}.acdc-row-menu-dropdown a:hover{background:var(--acdc-bg)}
      @media (max-width:900px){.acdc-contract-details-grid{grid-template-columns:1fr}.acdc-contract-detail-label{border-bottom:none;padding-bottom:4px}.acdc-contract-details-grid>div:nth-child(2n){padding-top:0}.acdc-convocation-edit-topbar{flex-direction:column;align-items:flex-start}}
    </style>
    <script>
      (function(){
        if(window.__acdcColdSurveyDocumentsInit){return;} window.__acdcColdSurveyDocumentsInit = true;
        function openModal(id){ var modal=document.getElementById(id); if(modal){ modal.hidden=false; document.body.classList.add('acdc-modal-open'); } }
        function closeModal(modal){ if(!modal){return;} modal.hidden=true; if(!document.querySelector('.acdc-modal-shell:not([hidden])')){ document.body.classList.remove('acdc-modal-open'); } }
        var filterToggle = document.querySelector('[data-acdc-filter-toggle]');
        var filterPanel = document.querySelector('[data-acdc-filters-panel]');
        if(filterToggle && filterPanel){ filterToggle.addEventListener('click', function(){ var expanded = filterToggle.getAttribute('aria-expanded') === 'true'; filterToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true'); filterPanel.hidden = expanded; }); }
        document.querySelectorAll('[data-acdc-modal-open]').forEach(function(btn){ btn.addEventListener('click', function(e){ e.preventDefault(); openModal(btn.getAttribute('data-acdc-modal-open')); }); });
        document.querySelectorAll('[data-acdc-modal-close]').forEach(function(btn){ btn.addEventListener('click', function(){ closeModal(btn.closest('.acdc-modal-shell')); }); });
        document.querySelectorAll('.acdc-modal-shell').forEach(function(modal){ modal.addEventListener('click', function(e){ if(e.target === modal || e.target.hasAttribute('data-acdc-modal-close')){ closeModal(modal); } }); });
        document.addEventListener('keydown', function(e){ if(e.key === 'Escape'){ document.querySelectorAll('.acdc-modal-shell:not([hidden])').forEach(closeModal); } });
        document.querySelectorAll('[data-acdc-row-menu-toggle]').forEach(function(btn){ btn.addEventListener('click', function(e){ e.stopPropagation(); var wrapper=btn.closest('[data-acdc-row-menu]'); var dropdown=wrapper?wrapper.querySelector('[data-acdc-row-menu-dropdown]'):null; var open=btn.getAttribute('aria-expanded')==='true'; document.querySelectorAll('[data-acdc-row-menu-dropdown]').forEach(function(menu){ menu.hidden=true; }); document.querySelectorAll('[data-acdc-row-menu-toggle]').forEach(function(other){ other.setAttribute('aria-expanded','false'); }); if(dropdown){ dropdown.hidden=open; } btn.setAttribute('aria-expanded', open ? 'false' : 'true'); }); });
        document.addEventListener('click', function(){ document.querySelectorAll('[data-acdc-row-menu-dropdown]').forEach(function(menu){ menu.hidden=true; }); document.querySelectorAll('[data-acdc-row-menu-toggle]').forEach(function(btn){ btn.setAttribute('aria-expanded','false'); }); });
      })();
    </script>
    <?php
  }


public function render_admin_mid_surveys_page() { $this->render_admin_portal_wrapper( 'mid_surveys' ); }

public function render_admin_hot_surveys_page() { $this->render_admin_portal_wrapper( 'hot_surveys' ); }

public function render_admin_questionnaire_sessions_page() { $this->render_admin_portal_wrapper( 'questionnaire_sessions' ); }

public function render_admin_questionnaire_results_page() { $this->render_admin_portal_wrapper( 'questionnaire_results' ); }

public function render_admin_questionnaire_settings_page() { $this->render_admin_portal_wrapper( 'questionnaire_settings' ); }

public function render_admin_cold_surveys_page() { $this->render_admin_portal_wrapper( 'cold_surveys' ); }

public function render_admin_trainer_surveys_page() { $this->render_admin_portal_wrapper( 'trainer_surveys' ); }

public function render_admin_company_surveys_page() { $this->render_admin_portal_wrapper( 'company_surveys' ); }

public function render_admin_funder_surveys_page() { $this->render_admin_portal_wrapper( 'funder_surveys' ); }

public function render_admin_improvement_pilotage_page() { $this->render_admin_portal_wrapper( 'improvement_pilotage' ); }

public function render_quality_compliance_shortcode( $atts = array() ) {
  $atts = shortcode_atts( array( 'tab' => 'improvement_pilotage' ), $atts, 'acdc_of_quality_compliance' );
  $atts['tab'] = 'improvement_pilotage';
  return $this->render_portal_shortcode( $atts );
}

  private function render_questionnaire_source_dispatch_settings_panel( $source ) {
    $settings = $this->get_survey_settings_from_source_data( $source );
    if ( empty( $settings ) ) {
      return;
    }

    $config = $this->get_default_survey_settings_config( $settings['survey_type'], isset( $settings['scope'] ) ? $settings['scope'] : 'action' );
    $trigger_label = isset( $config['trigger_rules'][ $settings['trigger_rule'] ] ) ? $config['trigger_rules'][ $settings['trigger_rule'] ] : $settings['trigger_rule'];
    $trigger_mode_labels = array(
      'manual'      => 'Manuel',
      'automatic'   => 'Automatique',
      'manual_auto' => 'Manuel + automatique',
    );
    $trigger_mode_label = isset( $trigger_mode_labels[ $settings['trigger_mode'] ] ) ? $trigger_mode_labels[ $settings['trigger_mode'] ] : $settings['trigger_mode'];
    $reminder_days = ! empty( $settings['reminder_days_array'] ) ? implode( ', ', array_map( 'absint', $settings['reminder_days_array'] ) ) : 'Aucune';
    ?>
    <div class="acdc-panel acdc-mb-18">
      <h4 style="margin-top:0;">Règles de diffusion héritées du modèle</h4>
      <div class="acdc-grid-4cols acdc-mb-18">
        <div class="acdc-panel acdc-panel-block"><strong>Déclenchement</strong><br><span class="acdc-muted-note"><?php echo esc_html( $trigger_mode_label ); ?></span></div>
        <div class="acdc-panel acdc-panel-block"><strong>Règle automatique</strong><br><span class="acdc-muted-note"><?php echo esc_html( $trigger_label ); ?></span></div>
        <div class="acdc-panel acdc-panel-block"><strong>Date limite</strong><br><span class="acdc-muted-note"><?php echo esc_html( absint( $settings['deadline_days'] ) ); ?> jour(s)</span></div>
        <div class="acdc-panel acdc-panel-block"><strong>Relances</strong><br><span class="acdc-muted-note"><?php echo esc_html( $reminder_days ); ?></span></div>
      </div>
      <p class="acdc-muted-note"><strong>Création directe d’actions :</strong> <?php echo '1' === $settings['auto_create_actions'] ? 'activée' : 'désactivée'; ?></p>
      <?php if ( '1' === $settings['auto_create_actions'] ) : ?>
        <?php $automation_labels = $this->get_survey_action_automation_rule_labels(); $enabled_labels = array(); foreach ( (array) $settings['auto_action_rules'] as $rule_key ) { if ( isset( $automation_labels[ $rule_key ] ) ) { $enabled_labels[] = $automation_labels[ $rule_key ]; } } ?>
        <p class="acdc-muted-note"><strong>Règles automatiques actives :</strong> <?php echo ! empty( $enabled_labels ) ? esc_html( implode( ' · ', $enabled_labels ) ) : 'Aucune'; ?></p>
      <?php endif; ?>
      <?php if ( ! empty( $settings['internal_comment'] ) ) : ?>
        <p class="acdc-muted-note" style="margin-bottom:0;"><strong>Commentaire interne :</strong> <?php echo nl2br( esc_html( $settings['internal_comment'] ) ); ?></p>
      <?php endif; ?>
    </div>
    <?php
  }


  private function get_survey_context_dashboard_stats( $source_type, $source_id = 0 ) {
    $rows = $this->get_questionnaire_session_summary_rows( array_filter( array( 'source_type' => (string) $source_type, 'source_id' => absint( $source_id ) ) ) );
    $stats = $this->get_questionnaire_session_stats_from_rows( $rows );
    $actions = $this->get_questionnaire_action_context_stats( array_filter( array( 'source_type' => (string) $source_type, 'source_id' => absint( $source_id ) ) ) );
    return array(
      'sessions' => (int) ( $stats['total'] ?? 0 ),
      'participants' => (int) ( $stats['participants'] ?? 0 ),
      'responses' => (int) ( $stats['responses'] ?? 0 ),
      'response_rate' => isset( $stats['response_rate'] ) ? $stats['response_rate'] : null,
      'alerts' => (int) ( $stats['alerts'] ?? 0 ),
      'actions' => (int) ( $actions['a_traiter'] ?? 0 ),
      'average_score' => isset( $stats['average_score'] ) ? $stats['average_score'] : null,
    );
  }

  private function render_survey_dashboard_stats( $survey_type, $source_id = 0 ) {
    $source_type = $this->get_survey_questionnaire_source_type( $survey_type );
    if ( '' === $source_type ) {
      return;
    }
    $stats = $this->get_survey_context_dashboard_stats( $source_type, $source_id );
    $terms = $this->get_questionnaire_results_terms( $source_type );
    ?>
    <div class="acdc-grid-4cols acdc-mb-18" style="display:grid;gap:18px">
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $stats['sessions'] ); ?></div><div class="acdc-stat-label">Envois</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $stats['participants'] ); ?></div><div class="acdc-stat-label">Destinataires</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $stats['responses'] ); ?></div><div class="acdc-stat-label">Réponses</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( null !== $stats['response_rate'] ? $stats['response_rate'] . ' %' : '—' ); ?></div><div class="acdc-stat-label">Taux de réponse</div></div>
    </div>
    <div class="acdc-grid-3cols acdc-mb-18" style="display:grid;gap:18px">
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $stats['alerts'] ); ?></div><div class="acdc-stat-label">Alertes</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $stats['actions'] ); ?></div><div class="acdc-stat-label"><?php echo esc_html( $terms['actions'] . ' à traiter' ); ?></div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( null !== $stats['average_score'] ? $stats['average_score'] : '—' ); ?></div><div class="acdc-stat-label">Score moyen</div></div>
    </div>
    <?php
  }

  private function render_survey_level2_shortcuts( $survey_type, $source_id = 0 ) {
    $source_type = $this->get_survey_questionnaire_source_type( $survey_type );
    if ( '' === $source_type ) {
      return;
    }
    $session_new_url = $this->get_questionnaire_new_session_url( $source_type, $source_id );
    $results_url = $this->get_questionnaire_results_url( $source_type, 0, $source_id );
    $settings_url = is_admin()
      ? admin_url( 'admin.php?page=acdc-of-questionnaire-settings&source_type=' . rawurlencode( $source_type ) )
      : $this->portal_page_url( array( 'tab' => 'questionnaire_settings', 'source_type' => $source_type ) );
    ?>
    <div class="acdc-inline-wrap acdc-mb-18">
      <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $session_new_url ); ?>">Planifier un envoi</a>
      <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $results_url ); ?>">Voir les résultats globaux</a>
      <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $settings_url ); ?>">Paramètres du module</a>
    </div>
    <?php
  }



  private function render_survey_management_shortcuts( $survey_type ) {
    $source_type = $this->get_survey_questionnaire_source_type( $survey_type );
    if ( '' === $source_type ) {
      return;
    }
    ?>
    <div class="acdc-inline-wrap acdc-mb-18">
      <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->get_questionnaire_sessions_url( $source_type ) ); ?>">Sessions de questionnaires</a>
      <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->get_questionnaire_results_url( $source_type ) ); ?>">Résultats des sessions</a>
      <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-questionnaire-settings' ) : $this->portal_page_url( array( 'tab' => 'questionnaire_settings' ) ) ); ?>">Paramètres questionnaires</a>
    </div>
    <?php
  }


  private function render_survey_configuration_panel( $survey, $survey_type, $read_only = false, $scope = 'action' ) {
    $settings = $this->get_normalized_survey_settings( $survey, $survey_type, $scope );
    $config = $this->get_default_survey_settings_config( $survey_type, $scope );
    ?>
    <div class="acdc-panel acdc-mb-18">
      <h3>Paramètres et diffusion</h3>
      <div class="acdc-grid-2cols">
        <p><label>Déclenchement</label>
          <select name="survey[survey_settings][trigger_mode]" <?php disabled( $read_only ); ?>>
            <option value="manual" <?php selected( $settings['trigger_mode'], 'manual' ); ?>>Manuel</option>
            <option value="automatic" <?php selected( $settings['trigger_mode'], 'automatic' ); ?>>Automatique</option>
            <option value="manual_auto" <?php selected( $settings['trigger_mode'], 'manual_auto' ); ?>>Manuel + automatique</option>
          </select>
        </p>
        <p><label>Règle automatique</label>
          <select name="survey[survey_settings][trigger_rule]" <?php disabled( $read_only ); ?>>
            <?php foreach ( $config['trigger_rules'] as $trigger_key => $trigger_label ) : ?>
              <option value="<?php echo esc_attr( $trigger_key ); ?>" <?php selected( $settings['trigger_rule'], $trigger_key ); ?>><?php echo esc_html( $trigger_label ); ?></option>
            <?php endforeach; ?>
          </select>
        </p>
        <p><label>Date limite (jours)</label><input type="number" min="1" name="survey[survey_settings][deadline_days]" value="<?php echo esc_attr( $settings['deadline_days'] ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
        <p><label>Relances (jours après envoi)</label><input type="text" name="survey[survey_settings][reminder_days]" value="<?php echo esc_attr( $settings['reminder_days'] ); ?>" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="5,10,15"></p>
      </div>
      <p><label>Commentaire interne</label><textarea name="survey[survey_settings][internal_comment]" rows="3" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Note interne, règle métier, contexte d'envoi…"><?php echo esc_textarea( $settings['internal_comment'] ); ?></textarea></p>
      <?php $automation_labels = $this->get_survey_action_automation_rule_labels(); ?>
      <?php if ( ! $read_only ) : ?>
      <?php /* ACDC 3.25.157 — Les libellés de ce formulaire sont en display:block :
               la case se retrouvait centrée AU-DESSUS de son texte, et l'on ne pouvait
               plus dire quelle case allait avec quelle règle. Le DOM était correct, le
               défaut était purement visuel : on rend ces labels-là en ligne. */ ?>
      <style>
        label.acdc-check-inline{display:flex;align-items:center;gap:8px;text-align:left;font-weight:400;}
        label.acdc-check-inline input[type="checkbox"]{flex:0 0 auto;margin:0;width:auto;}
      </style>
      <p><label class="acdc-check-inline"><input type="checkbox" name="survey[survey_settings][is_active]" value="1" <?php checked( $settings['is_active'], '1' ); ?>> Activer ce modèle pour la diffusion</label></p>
      <p><label class="acdc-check-inline"><input type="checkbox" name="survey[survey_settings][auto_create_actions]" value="1" <?php checked( $settings['auto_create_actions'], '1' ); ?>> Créer automatiquement certaines actions dès la validation d’une réponse en alerte</label></p>
      <div class="acdc-panel acdc-panel-block" style="margin-top:12px;">
        <strong>Règles d’automatisation des actions</strong>
        <div class="acdc-grid-2cols" style="margin-top:10px;">
          <?php foreach ( $automation_labels as $rule_key => $rule_label ) : ?>
            <p style="margin:0;"><label class="acdc-check-inline"><input type="checkbox" name="survey[survey_settings][auto_action_rules][]" value="<?php echo esc_attr( $rule_key ); ?>" <?php checked( in_array( $rule_key, (array) $settings['auto_action_rules'], true ) ); ?>> <?php echo esc_html( $rule_label ); ?></label></p>
          <?php endforeach; ?>
        </div>
      </div>
      <p class="acdc-muted-note">Les règles proposées s’adaptent au type d’enquête. Vous pouvez affiner chaque modèle au cas par cas.</p>
      <div class="acdc-panel acdc-panel-block" style="margin-top:12px;">
        <strong>Délais cibles et échéances automatiques</strong>
        <div class="acdc-grid-2cols" style="margin-top:10px;">
          <p><label>Délai cible par défaut (jours)</label><input type="number" min="1" name="survey[survey_settings][action_default_due_days]" value="<?php echo esc_attr( $settings['action_default_due_days'] ); ?>"></p>
        </div>
        <div class="acdc-grid-2cols" style="margin-top:10px;">
          <?php foreach ( $automation_labels as $rule_key => $rule_label ) : ?>
            <p><label><?php echo esc_html( $rule_label ); ?></label><input type="number" min="1" name="survey[survey_settings][action_due_days][<?php echo esc_attr( $rule_key ); ?>]" value="<?php echo esc_attr( (int) ( $settings['action_due_days'][ $rule_key ] ?? $settings['action_default_due_days'] ) ); ?>"><span class="acdc-help">Délai cible en jours pour cette action automatique.</span></p>
          <?php endforeach; ?>
        </div>
      </div>
      <?php $assignment_labels = $this->get_survey_action_assignment_mode_labels(); $action_assignees = $this->get_questionnaire_action_assignees(); $formations = $this->get_survey_assignment_context_entity_labels( 'formation' ); $trainers = $this->get_survey_assignment_context_entity_labels( 'trainer' ); $companies = $this->get_survey_assignment_context_entity_labels( 'company' ); ?>
      <div class="acdc-panel acdc-panel-block" style="margin-top:12px;">
        <strong>Assignation automatique du responsable</strong>
        <div class="acdc-grid-2cols" style="margin-top:10px;">
          <p><label>Mode d’assignation</label><select name="survey[survey_settings][action_assignment_mode]">
            <?php foreach ( $assignment_labels as $assignment_key => $assignment_label ) : ?>
              <option value="<?php echo esc_attr( $assignment_key ); ?>" <?php selected( $settings['action_assignment_mode'], $assignment_key ); ?>><?php echo esc_html( $assignment_label ); ?></option>
            <?php endforeach; ?>
          </select></p>
          <p><label>Responsable fixe</label><select name="survey[survey_settings][action_assignment_fixed_user_id]"><option value="0">— Aucun —</option><?php foreach ( $action_assignees as $assignee ) : ?><option value="<?php echo esc_attr( (int) $assignee->ID ); ?>" <?php selected( (int) $settings['action_assignment_fixed_user_id'], (int) $assignee->ID ); ?>><?php echo esc_html( $assignee->display_name ); ?></option><?php endforeach; ?></select></p>
          <p><label>Responsable de secours</label><select name="survey[survey_settings][action_assignment_fallback_user_id]"><option value="0">Utilisateur courant</option><?php foreach ( $action_assignees as $assignee ) : ?><option value="<?php echo esc_attr( (int) $assignee->ID ); ?>" <?php selected( (int) $settings['action_assignment_fallback_user_id'], (int) $assignee->ID ); ?>><?php echo esc_html( $assignee->display_name ); ?></option><?php endforeach; ?></select></p>
        </div>
        <p class="acdc-muted-note">Les règles ci-dessous permettent de choisir automatiquement un responsable selon le contexte réel de l’enquête.</p>
        <div class="acdc-grid-3cols" style="gap:16px;align-items:flex-start;">
          <div>
            <strong>Par formation</strong>
            <div style="max-height:220px;overflow:auto;margin-top:8px;padding-right:6px;">
              <?php foreach ( $formations as $formation_id => $formation_label ) : ?>
                <p style="margin:0 0 8px 0;"><label><?php echo esc_html( $formation_label ); ?></label><select name="survey[survey_settings][action_assignment_by_formation][<?php echo esc_attr( $formation_id ); ?>]"><option value="0">—</option><?php foreach ( $action_assignees as $assignee ) : ?><option value="<?php echo esc_attr( (int) $assignee->ID ); ?>" <?php selected( (int) ( $settings['action_assignment_by_formation'][ $formation_id ] ?? 0 ), (int) $assignee->ID ); ?>><?php echo esc_html( $assignee->display_name ); ?></option><?php endforeach; ?></select></p>
              <?php endforeach; ?>
            </div>
          </div>
          <div>
            <strong>Par formateur</strong>
            <div style="max-height:220px;overflow:auto;margin-top:8px;padding-right:6px;">
              <?php foreach ( $trainers as $trainer_id => $trainer_label ) : ?>
                <p style="margin:0 0 8px 0;"><label><?php echo esc_html( $trainer_label ); ?></label><select name="survey[survey_settings][action_assignment_by_trainer][<?php echo esc_attr( $trainer_id ); ?>]"><option value="0">—</option><?php foreach ( $action_assignees as $assignee ) : ?><option value="<?php echo esc_attr( (int) $assignee->ID ); ?>" <?php selected( (int) ( $settings['action_assignment_by_trainer'][ $trainer_id ] ?? 0 ), (int) $assignee->ID ); ?>><?php echo esc_html( $assignee->display_name ); ?></option><?php endforeach; ?></select></p>
              <?php endforeach; ?>
            </div>
          </div>
          <div>
            <strong>Par entreprise</strong>
            <div style="max-height:220px;overflow:auto;margin-top:8px;padding-right:6px;">
              <?php foreach ( $companies as $company_id => $company_label ) : ?>
                <p style="margin:0 0 8px 0;"><label><?php echo esc_html( $company_label ); ?></label><select name="survey[survey_settings][action_assignment_by_company][<?php echo esc_attr( $company_id ); ?>]"><option value="0">—</option><?php foreach ( $action_assignees as $assignee ) : ?><option value="<?php echo esc_attr( (int) $assignee->ID ); ?>" <?php selected( (int) ( $settings['action_assignment_by_company'][ $company_id ] ?? 0 ), (int) $assignee->ID ); ?>><?php echo esc_html( $assignee->display_name ); ?></option><?php endforeach; ?></select></p>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
      <?php else : ?>
      <p class="acdc-muted-note">Statut de diffusion : <?php echo '1' === $settings['is_active'] ? 'actif' : 'inactif'; ?></p>
      <p class="acdc-muted-note">Création directe d’actions : <?php echo '1' === $settings['auto_create_actions'] ? 'activée' : 'désactivée'; ?></p>
      <?php if ( '1' === $settings['auto_create_actions'] ) : ?>
        <?php $enabled_labels = array(); foreach ( (array) $settings['auto_action_rules'] as $rule_key ) { if ( isset( $automation_labels[ $rule_key ] ) ) { $enabled_labels[] = $automation_labels[ $rule_key ] . ' (J+' . (int) ( $settings['action_due_days'][ $rule_key ] ?? $settings['action_default_due_days'] ) . ')'; } } ?>
        <p class="acdc-muted-note">Règles actives : <?php echo ! empty( $enabled_labels ) ? esc_html( implode( ' · ', $enabled_labels ) ) : 'Aucune'; ?></p>
      <?php endif; ?>
      <p class="acdc-muted-note">Délai cible par défaut : J+<?php echo esc_html( (string) $settings['action_default_due_days'] ); ?></p>
      <?php $assignment_labels = $this->get_survey_action_assignment_mode_labels(); $assignment_mode_label = isset( $assignment_labels[ $settings['action_assignment_mode'] ] ) ? $assignment_labels[ $settings['action_assignment_mode'] ] : $settings['action_assignment_mode']; ?>
      <p class="acdc-muted-note">Assignation automatique : <?php echo esc_html( $assignment_mode_label ); ?></p>
      <?php if ( ! empty( $settings['action_assignment_fixed_user_id'] ) ) : ?><p class="acdc-muted-note">Responsable fixe : <?php echo esc_html( $this->get_questionnaire_action_assignee_label( (int) $settings['action_assignment_fixed_user_id'] ) ); ?></p><?php endif; ?>
      <?php endif; ?>
    </div>
    <?php
  }


  private function render_survey_back_link( $url ) {
    ?>
    <div class="acdc-mb-18">
      <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $url ); ?>">← Retour à la liste</a>
    </div>
    <?php
  }


  private function render_survey_list_summary( $entries, $label = 'enquêtes' ) {
    $entries = is_array( $entries ) ? $entries : array();
    $total = count( $entries );
    $models = 0;
    $custom = 0;
    foreach ( $entries as $entry ) {
      if ( ! empty( $entry['is_model'] ) ) {
        $models++;
      } else {
        $custom++;
      }
    }
    ?>
    <div class="acdc-grid-3cols acdc-mb-18">
      <div class="acdc-panel"><strong>Total</strong><div style="margin-top:8px;font-size:24px;font-weight:700;color:var(--acdc-text);"><?php echo esc_html( $total ); ?></div><div class="acdc-muted-note"><?php echo esc_html( $label ); ?></div></div>
      <div class="acdc-panel"><strong>Modèles</strong><div style="margin-top:8px;font-size:24px;font-weight:700;color:var(--acdc-text);"><?php echo esc_html( $models ); ?></div><div class="acdc-muted-note">Prêts à réutiliser</div></div>
      <div class="acdc-panel"><strong>Enquêtes créées</strong><div style="margin-top:8px;font-size:24px;font-weight:700;color:var(--acdc-text);"><?php echo esc_html( $custom ); ?></div><div class="acdc-muted-note">Hors modèles</div></div>
    </div>
    <?php
  }



  private function render_survey_diffusion_cell( $survey_type, $survey_id ) {
    $summary = $this->get_survey_questionnaire_summary( $survey_type, $survey_id );
    $source_type = isset( $summary['source_type'] ) ? (string) $summary['source_type'] : '';
    $source_id = isset( $summary['source_id'] ) ? absint( $summary['source_id'] ) : 0;

    if ( '' === $source_type || $source_id <= 0 ) {
      echo '—';
      return;
    }

    $total = isset( $summary['total'] ) ? absint( $summary['total'] ) : 0;
    $participants = isset( $summary['participants'] ) ? absint( $summary['participants'] ) : 0;
    $label = $total > 1 ? 'sessions' : 'session';
    echo '<div><strong>' . esc_html( $total ) . ' ' . esc_html( $label ) . '</strong><br><small>' . esc_html( $participants ) . ' participant' . ( $participants > 1 ? 's' : '' ) . '</small></div>';
    echo '<div class="acdc-inline-wrap acdc-mt-8">';
    echo '<a class="acdc-button acdc-button-soft" href="' . esc_url( $this->get_questionnaire_new_session_url( $source_type, $source_id ) ) . '">Créer un envoi</a>';
    echo '<a class="acdc-button acdc-button-soft" href="' . esc_url( $this->get_questionnaire_sessions_url( $source_type, $source_id ) ) . '">Sessions</a>';
    echo '<a class="acdc-button acdc-button-soft" href="' . esc_url( $this->get_questionnaire_results_url( $source_type, 0, $source_id ) ) . '">Résultats</a>';
    echo '</div>';
  }



  /* ===================================================================
   * ACDC 3.21.20 — Refonte UI pages enquêtes (6 types)
   * Architecture : section-head + 4 KPIs + 3 srv_subtab
   * Pattern icônes certifié module Apprenants
   * =================================================================== */

  private function get_survey_page_base_url( $survey_type, $scope = 'action' ) {
    $page_map = array(
      'mid'     => 'acdc-of-mid-surveys',
      'hot'     => 'acdc-of-hot-surveys',
      'cold'    => 'acdc-of-cold-surveys',
      'trainer' => 'acdc-of-trainer-surveys',
      'company' => 'acdc-of-company-surveys',
      'funder'  => 'acdc-of-funder-surveys',
    );
    $tab_map = array(
      'mid'     => 'mid_surveys',
      'hot'     => 'hot_surveys',
      'cold'    => 'cold_surveys',
      'trainer' => 'trainer_surveys',
      'company' => 'company_surveys',
      'funder'  => 'funder_surveys',
    );
    $page = isset( $page_map[ $survey_type ] ) ? $page_map[ $survey_type ] : 'acdc-of-hot-surveys';
    $tab  = isset( $tab_map[ $survey_type ] )  ? $tab_map[ $survey_type ]  : 'hot_surveys';
    if ( is_admin() ) {
      $url = admin_url( 'admin.php?page=' . rawurlencode( $page ) );
    } else {
      $url = $this->portal_page_url( array( 'tab' => $tab ) );
    }
    if ( 'annual' === $scope ) {
      $url = add_query_arg( 'scope', 'annual', $url );
    }
    return $url;
  }

  private function get_surveys_unified( $survey_type, $scope = 'action', $search = '' ) {
    switch ( $survey_type ) {
      case 'mid':     return $this->get_mid_surveys( $search, true );
      case 'hot':     return $this->get_hot_surveys( $search, true );
      case 'cold':    return $this->get_cold_surveys( $search, true );
      case 'trainer': return $this->get_trainer_surveys( $scope, $search, true );
      case 'company': return $this->get_company_surveys( $search, true );
      case 'funder':  return $this->get_funder_surveys( $scope, $search, true );
      default:        return array();
    }
  }

  private function get_survey_unified( $survey_type, $id ) {
    switch ( $survey_type ) {
      case 'mid':     return $this->get_mid_survey( $id );
      case 'hot':     return $this->get_hot_survey( $id );
      case 'cold':    return $this->get_cold_survey( $id );
      case 'trainer': return $this->get_trainer_survey( $id );
      case 'company': return $this->get_company_survey( $id );
      case 'funder':  return $this->get_funder_survey( $id );
      default:        return null;
    }
  }

  private function render_survey_form_unified( $survey_type, $survey, $read_only, $scope = 'action' ) {
    switch ( $survey_type ) {
      case 'mid':     $this->render_front_mid_survey_form( $survey, $read_only ); break;
      case 'hot':     $this->render_front_hot_survey_form( $survey, $read_only ); break;
      case 'cold':    $this->render_front_cold_survey_form( $survey, $read_only ); break;
      case 'trainer': $this->render_front_trainer_survey_form( $survey, $read_only, $scope ); break;
      case 'company': $this->render_front_company_survey_form( $survey, $read_only ); break;
      case 'funder':  $this->render_front_funder_survey_form( $survey, $read_only, $scope ); break;
    }
  }

  private function get_survey_qualiopi_indicator( $survey_type ) {
    $map = array(
      'mid'     => 'C2.3',
      'hot'     => 'C2.3',
      'cold'    => 'C2.3',
      'trainer' => 'C3.1',
      'company' => 'C2.3',
      'funder'  => 'C3.2',
    );
    return isset( $map[ $survey_type ] ) ? $map[ $survey_type ] : 'Qualiopi';
  }

  private function get_survey_session_status_badge( $status ) {
    $map = array(
      'pending'    => array( 'label' => 'Planifi\u00e9e', 'class' => 'en_attente' ),
      'sent'       => array( 'label' => 'Envoy\u00e9e',  'class' => 'envoye'    ),
      'opened'     => array( 'label' => 'Ouverte',     'class' => 'ouvert'    ),
      'in_progress'=> array( 'label' => 'En cours',    'class' => 'envoye'    ),
      'completed'  => array( 'label' => 'R\u00e9pondue', 'class' => 'signe'    ),
      'expired'    => array( 'label' => 'Expir\u00e9e', 'class' => 'expire'    ),
      'cancelled'  => array( 'label' => 'Annul\u00e9e', 'class' => 'supprime'  ),
    );
    $s = isset( $map[ $status ] ) ? $map[ $status ] : array( 'label' => ucfirst( (string) $status ), 'class' => 'en_attente' );
    return '<span class="acdc-sig-badge ' . esc_attr( $s['class'] ) . '">' . esc_html( $s['label'] ) . '</span>';
  }

  private function render_survey_page_layout( $survey_type, $scope, $action, $item_id ) {
    $search      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
    $srv_subtab  = isset( $_GET['srv_subtab'] ) ? sanitize_text_field( wp_unslash( $_GET['srv_subtab'] ) ) : '';
    $source_type = $this->get_survey_questionnaire_source_type( $survey_type );
    $type_label  = $this->get_survey_type_display_label( $source_type );
    $indicator   = $this->get_survey_qualiopi_indicator( $survey_type );
    $base_url    = $this->get_survey_page_base_url( $survey_type, $scope );
    $surveys     = $this->get_surveys_unified( $survey_type, $scope, $search );
    $survey      = $item_id ? $this->get_survey_unified( $survey_type, $item_id ) : null;
    $stats       = $this->get_survey_context_dashboard_stats( $source_type, 0 );

    if ( $this->should_render_survey_form_only( $action ) ) {
      $srv_subtab = 'mes_enquetes';
    }
    if ( '' === $srv_subtab ) {
      $srv_subtab = 'mes_enquetes';
    }

    $sessions_url  = $this->get_questionnaire_sessions_url( $source_type );
    $results_url   = $this->get_questionnaire_results_url( $source_type );
    $new_send_url  = $this->get_questionnaire_new_session_url( $source_type, 0 );
    $modal_id      = 'acdc-create-' . esc_attr( $survey_type ) . '-survey-from-model-modal';
    ?>
    <section class="acdc-section-head">
      <div>
        <h2><?php echo esc_html( $type_label ); ?></h2>
        <p>Gestion, envoi et suivi des r&#233;sultats &#8212; conformit&#233; Qualiopi <?php echo esc_html( $indicator ); ?>.</p>
      </div>
      <div class="acdc-inline-wrap">
        <button type="button" class="acdc-button acdc-button-soft" data-acdc-open-modal="<?php echo $modal_id; ?>">Depuis le mod&#232;le</button>
        <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( add_query_arg( array( 'action' => 'new' ), $base_url ) ); ?>">+ Nouvelle enqu&#234;te</a>
        <a class="acdc-row-action-icon" style="width:40px;height:40px;border-radius:10px;" href="<?php echo esc_url( add_query_arg( array( 'srv_subtab' => 'parametres' ), $base_url ) ); ?>" title="Param&#232;tres du module" aria-label="Param&#232;tres du module" data-acdc-iconized="1">
          <?php echo $this->render_inline_icon( 'settings', 25 ); ?>
          <span class="acdc-action-hub-sr screen-reader-text">Param&#232;tres</span>
        </a>
      </div>
    </section>

    <?php /* KPIs */ ?>
    <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:0;padding:18px 0 0;background:#fff;border-bottom:1px solid #f0e6dc;">
      <?php
      $kpi_items = array(
        array( 'val' => $stats['sessions'],   'label' => 'Envois' ),
        array( 'val' => $stats['responses'],  'label' => 'R&#233;ponses' ),
        array( 'val' => null !== $stats['response_rate']  ? $stats['response_rate'] . '&#160;%' : '&#8212;', 'label' => 'Taux de retour' ),
        array( 'val' => null !== $stats['average_score']  ? $stats['average_score'] : '&#8212;',            'label' => 'Score moyen' ),
      );
      foreach ( $kpi_items as $k ) : ?>
        <div class="acdc-panel acdc-centered-stat" style="background:linear-gradient(135deg,#fef6e4 0%,#fbf8f7 100%);border:1px solid #f0e6dc;border-radius:12px;padding:14px 16px;text-align:center;">
          <div class="acdc-stat-number" style="font-size:24px;font-weight:700;color:var(--acdc-text);"><?php echo $k['val']; ?></div>
          <div class="acdc-stat-label" style="font-size:11px;font-weight:700;color:#8a6d2a;margin-top:5px;text-transform:uppercase;letter-spacing:.06em;"><?php echo $k['label']; ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php /* Sous-navigation srv_subtab */ ?>
    <div style="display:flex;padding:0;background:#fff;border-bottom:1px solid #f0e6dc;margin-bottom:18px;">
      <?php
      $tabs = array(
        'mes_enquetes' => 'Mes enqu&#234;tes',
        'envois'       => 'Envois &amp; suivi',
        'resultats'    => 'R&#233;sultats &amp; preuves',
      );
      foreach ( $tabs as $slug => $label ) :
        $is_active = ( $slug === $srv_subtab );
        $tab_url   = add_query_arg( array( 'srv_subtab' => $slug ), $base_url );
        ?>
        <a href="<?php echo esc_url( $tab_url ); ?>"
           style="padding:10px 18px;font-size:13px;font-weight:<?php echo $is_active ? '600' : '500'; ?>;color:<?php echo $is_active ? '#8b5b23' : '#4b5d76'; ?>;text-decoration:none;border-bottom:2px solid <?php echo $is_active ? '#8b5b23' : 'transparent'; ?>;margin-bottom:-1px;display:inline-block;">
          <?php echo $label; ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php
    if ( 'mes_enquetes' === $srv_subtab ) :
      $this->render_survey_subtab_mes_enquetes( $surveys, $survey, $survey_type, $scope, $action, $item_id, $base_url, $modal_id );
    elseif ( 'envois' === $srv_subtab ) :
      $this->render_survey_subtab_envois( $source_type, $survey_type, $base_url, $new_send_url );
    elseif ( 'resultats' === $srv_subtab ) :
      $this->render_survey_subtab_resultats( $source_type, $stats, $results_url, $sessions_url );
    endif;
    ?>
    <?php
  }


  private function render_survey_subtab_mes_enquetes( $surveys, $survey, $survey_type, $scope, $action, $item_id, $base_url, $modal_id ) {
    if ( $this->should_render_survey_form_only( $action ) ) {
      $this->render_survey_back_link( $base_url );
      $this->render_survey_form_unified( $survey_type, $survey, 'view' === $action, $scope );
      return;
    }

    $nonce_action_delete = 'acdc_delete_' . $survey_type . '_survey';
    $nonce_action_model  = 'acdc_create_' . $survey_type . '_survey_from_model';
    $page_param          = 'acdc-of-' . str_replace( '_', '-', $survey_type ) . '-surveys';
    $delete_action       = 'acdc_delete_' . $survey_type . '_survey';
    $type_label_singular = $this->get_survey_type_display_label( $this->get_survey_questionnaire_source_type( $survey_type ) );
    ?>
    <div class="acdc-panel acdc-mb-18">
      <div class="acdc-table-wrap">
        <table class="acdc-table">
          <thead>
            <tr>
              <th>Intitul&#233;</th>
              <th>Alerte notation</th>
              <th>Diffusion</th>
              <th>Modifi&#233; le</th>
              <th><span class="screen-reader-text">Actions</span></th>
            </tr>
          </thead>
          <tbody>
          <?php if ( ! empty( $surveys ) ) : foreach ( $surveys as $entry ) :
            $eid        = (int) ( $entry['id'] ?? 0 );
            $etitle     = (string) ( $entry['title'] ?? '' );
            $ealert     = (string) ( $entry['alert_notation'] ?? '&#8212;' );
            $eupdated   = isset( $entry['updated_at'] ) ? mysql2date( 'j F Y', $entry['updated_at'] ) : '&#8212;';
            $is_model   = ! empty( $entry['is_model'] );
            $view_url   = add_query_arg( array( 'action' => 'view', 'item_id' => $eid ), $base_url );
            $edit_url   = add_query_arg( array( 'action' => 'edit', 'item_id' => $eid ), $base_url );
            $results_u  = $this->get_questionnaire_results_url( $this->get_survey_questionnaire_source_type( $survey_type ), 0, $eid );
            $send_url   = $this->get_questionnaire_new_session_url( $this->get_survey_questionnaire_source_type( $survey_type ), $eid );
            $del_url    = wp_nonce_url( admin_url( 'admin-post.php?action=' . $delete_action . '&survey_id=' . $eid . ( is_admin() ? '&page=' . $page_param : '' ) ), $nonce_action_delete . '_' . $eid );
            $dup_url    = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_duplicate_' . $survey_type . '_survey&survey_id=' . $eid . ( is_admin() ? '&page=' . $page_param : '' ) ), 'acdc_duplicate_' . $survey_type . '_survey_' . $eid );
            $archive_url = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_archive_' . $survey_type . '_survey&survey_id=' . $eid . ( is_admin() ? '&page=' . $page_param : '' ) ), 'acdc_archive_' . $survey_type . '_survey_' . $eid );
            $menu_id    = 'acdc-srv-menu-' . $survey_type . '-' . $eid;
          ?>
            <tr>
              <td>
                <strong><?php echo esc_html( $etitle ); ?></strong>
                <?php if ( $is_model ) : ?><em style="font-size:11px;color:#8a6d2a;margin-left:6px;">(mod&#232;le)</em><?php endif; ?>
              </td>
              <td><?php echo esc_html( $ealert ); ?></td>
              <td><?php $this->render_survey_diffusion_cell( $survey_type, $eid ); ?></td>
              <td style="white-space:nowrap;font-size:12px;color:#4b5d76;"><?php echo $eupdated; ?></td>
              <td class="acdc-actions-cell-icons">
                <div class="acdc-groups-actions-inline">
                  <?php /* Menu 3 points — pattern certifié Apprenants */ ?>
                  <div class="acdc-row-menu" data-acdc-row-menu>
                    <button type="button" class="acdc-row-action-icon acdc-row-menu-toggle" data-acdc-row-menu-toggle aria-expanded="false" aria-label="Actions compl&#233;mentaires">
                      <?php echo $this->render_inline_icon( 'more-horizontal', 25 ); ?>
                    </button>
                    <div class="acdc-row-menu-dropdown" data-acdc-row-menu-dropdown hidden>
                      <a href="<?php echo esc_url( $results_u ); ?>">Voir les r&#233;sultats</a>
                      <a href="<?php echo esc_url( $send_url ); ?>">G&#233;rer l&#8217;envoi</a>
                      <?php /* ACDC 3.25.157 — Seule entrée du menu à ne transmettre AUCUN
                               identifiant : on quittait la ligne cliquée pour un onglet
                               générique, incapable de savoir quelle enquête relancer.
                               Constat vérifié sur tous les modules d'enquête, corrigé ici
                               une fois pour toutes puisque le menu est mutualisé. */ ?>
                      <a href="<?php echo esc_url( add_query_arg( array(
                        'srv_subtab'  => 'envois',
                        'source_type' => $this->get_survey_questionnaire_source_type( $survey_type ),
                        'source_id'   => (int) $eid,
                      ), $base_url ) ); ?>">Relancer manuellement</a>
                      <a href="<?php echo esc_url( $dup_url ); ?>">Dupliquer</a>
                      <a href="<?php echo esc_url( $results_u . '&export=csv' ); ?>">Exporter PDF / CSV</a>
                      <a href="<?php echo esc_url( $archive_url ); ?>">Archiver</a>
                      <a href="<?php echo esc_url( $del_url ); ?>" style="color:#e06d6d;" onclick="return confirm('Supprimer cette enqu&#234;te\u00a0?');">Supprimer</a>
                    </div>
                  </div>
                  <?php /* Voir */ ?>
                  <a class="acdc-row-action-icon acdc-row-view-link" href="<?php echo esc_url( $view_url ); ?>" title="Voir" aria-label="Voir l&#8217;enqu&#234;te" data-acdc-iconized="1">
                    <?php echo $this->render_inline_icon( 'eye', 25 ); ?>
                    <span class="acdc-action-hub-sr screen-reader-text">Voir</span>
                  </a>
                  <?php /* Modifier */ ?>
                  <a class="acdc-row-action-icon acdc-row-edit-link" href="<?php echo esc_url( $edit_url ); ?>" title="Modifier" aria-label="Modifier l&#8217;enqu&#234;te" data-acdc-iconized="1">
                    <?php echo $this->render_inline_icon( 'edit', 25 ); ?>
                    <span class="acdc-action-hub-sr screen-reader-text">Modifier</span>
                  </a>
                  <?php /* Supprimer */ ?>
                  <a class="acdc-row-action-icon acdc-row-delete-link" href="<?php echo esc_url( $del_url ); ?>" title="Supprimer" aria-label="Supprimer l&#8217;enqu&#234;te" onclick="return confirm('Supprimer cette enqu&#234;te\u00a0?');" data-acdc-iconized="1">
                    <?php echo $this->render_inline_icon( 'trash', 25 ); ?>
                    <span class="acdc-action-hub-sr screen-reader-text">Supprimer</span>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; else : ?>
            <tr><td colspan="5" style="text-align:center;padding:32px 16px;color:#4b5d76;">Aucune enqu&#234;te enregistr&#233;e.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php /* Modale depuis le modèle */ ?>
    <div class="acdc-modal-shell" id="<?php echo $modal_id; ?>" hidden>
      <div class="acdc-modal-backdrop" data-acdc-close-modal></div>
      <div class="acdc-modal-dialog acdc-modal-dialog-medium">
        <div class="acdc-modal-header">
          <h4>Cr&#233;er depuis le mod&#232;le</h4>
          <button type="button" class="acdc-modal-close" data-acdc-close-modal>&times;</button>
        </div>
        <div class="acdc-modal-body acdc-pad-24">
          <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( $nonce_action_model ); ?>
            <input type="hidden" name="action" value="<?php echo esc_attr( $nonce_action_model ); ?>">
            <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="<?php echo esc_attr( $page_param ); ?>"><?php endif; ?>
            <p>Cette action cr&#233;e une nouvelle enqu&#234;te bas&#233;e sur le mod&#232;le par d&#233;faut.</p>
            <p class="acdc-actions-end acdc-mt-18">
              <button type="button" class="acdc-button acdc-button-soft" data-acdc-close-modal>Annuler</button>
              <button type="submit" class="acdc-button acdc-button-primary">Cr&#233;er depuis le mod&#232;le</button>
            </p>
          </form>
        </div>
      </div>
    </div>
    <?php
  }


  private function render_survey_subtab_envois( $source_type, $survey_type, $base_url, $new_send_url ) {
    $sessions = $this->get_questionnaire_sessions( array( 'source_type' => $source_type ) );
    $results_base = $this->get_questionnaire_results_url( $source_type );
    ?>
    <?php /* Bandeau Qualiopi */ ?>
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:11px 16px;display:flex;align-items:center;gap:10px;margin-bottom:14px;font-size:12px;color:#1e40af;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      <div><strong>Tra&#231;abilit&#233; Qualiopi&#160;:</strong> chaque envoi est horodat&#233;, li&#233; &#224; la session et au destinataire. La r&#233;ponse g&#233;n&#232;re un PDF archivable pour l&#8217;auditeur.</div>
    </div>

    <div class="acdc-panel" style="padding:0;overflow:hidden;border:1px solid #f0e6dc;border-radius:12px;margin-bottom:14px;">
      <?php /* En-tête tableau avec cadenas */ ?>
      <div style="display:flex;align-items:center;justify-content:flex-end;padding:10px 14px 8px;border-bottom:1px solid #f0e6dc;background:var(--acdc-bg);">
        <button class="acdc-table-lock-toggle" id="acdc-srv-lock-<?php echo esc_attr( $survey_type ); ?>" title="Verrouiller / d&#233;verrouiller les colonnes" aria-label="Verrouiller les colonnes"
          onclick="var b=this;b.classList.toggle('is-locked');b.querySelector('svg use,svg path[data-lock-open]');">
          <?php echo $this->render_inline_icon( 'lock', 25 ); ?>
        </button>
      </div>
      <div class="acdc-table-wrap" style="overflow-x:auto;">
        <table class="acdc-table" style="min-width:800px;">
          <thead>
            <tr>
              <th>Enquête</th>
              <th>Destinataire</th>
              <th>Envoy&#233; le</th>
              <th>Statut</th>
              <th>R&#233;ponses</th>
              <th>Score</th>
              <th><span class="screen-reader-text">Actions</span></th>
            </tr>
          </thead>
          <tbody>
          <?php if ( ! empty( $sessions ) ) : foreach ( $sessions as $sess ) :
            $sid         = (int) $sess->id;
            $stitle      = ! empty( $sess->session_title ) ? (string) $sess->session_title : '&#8212;';
            $sstatus     = ! empty( $sess->status ) ? (string) $sess->status : 'sent';
            $screated    = ! empty( $sess->created_at ) ? mysql2date( 'd/m/Y', $sess->created_at ) : '&#8212;';
            $participants = $this->get_questionnaire_session_participants( $sid );
            $pcount      = count( $participants );
            $rcount      = $this->get_questionnaire_session_response_count( $sid );
            $avg_score   = $this->get_questionnaire_session_average_score( $sid );
            $sess_results_url = $this->get_questionnaire_results_url( $source_type, $sid, 0 );
            $relance_url = add_query_arg( array( 'srv_subtab' => 'envois', 'relance_session' => $sid ), $base_url );
          ?>
            <tr>
              <td><strong style="color:var(--acdc-text);"><?php echo esc_html( $stitle ); ?></strong></td>
              <td style="font-size:12px;color:#4b5d76;"><?php echo esc_html( $pcount ); ?> destinataire(s)</td>
              <td style="white-space:nowrap;font-size:12px;"><?php echo $screated; ?></td>
              <td><?php echo $this->get_survey_session_status_badge( $sstatus ); ?></td>
              <td style="font-size:13px;"><strong style="color:var(--acdc-text);"><?php echo esc_html( $rcount ); ?></strong> / <?php echo esc_html( $pcount ); ?></td>
              <td style="font-size:13px;">
                <?php if ( null !== $avg_score ) : ?>
                  <strong style="color:var(--acdc-text);"><?php echo esc_html( $avg_score ); ?> &#9733;</strong>
                <?php else : ?>
                  <span style="color:#b0b8c4;">&#8212;</span>
                <?php endif; ?>
              </td>
              <td class="acdc-actions-cell-icons">
                <div class="acdc-groups-actions-inline">
                  <a class="acdc-row-action-icon acdc-row-view-link" href="<?php echo esc_url( $sess_results_url ); ?>" title="Voir les r&#233;sultats" aria-label="Voir les r&#233;sultats de cette session" data-acdc-iconized="1">
                    <?php echo $this->render_inline_icon( 'eye', 25 ); ?>
                    <span class="acdc-action-hub-sr screen-reader-text">Voir</span>
                  </a>
                  <?php if ( in_array( $sstatus, array( 'sent', 'opened', 'in_progress' ), true ) ) : ?>
                    <a class="acdc-row-action-icon" href="<?php echo esc_url( $relance_url ); ?>" title="Relancer" aria-label="Relancer les destinataires" data-acdc-iconized="1">
                      <?php echo $this->render_inline_icon( 'send', 25 ); ?>
                      <span class="acdc-action-hub-sr screen-reader-text">Relancer</span>
                    </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; else : ?>
            <tr><td colspan="7" style="text-align:center;padding:32px 16px;color:#4b5d76;">Aucun envoi pour cette enqu&#234;te.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:8px;">
      <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->get_questionnaire_sessions_url( $source_type ) ); ?>">Voir toutes les sessions</a>
      <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $new_send_url ); ?>">+ Planifier un envoi</a>
    </div>
    <?php
  }


  private function render_survey_subtab_resultats( $source_type, $stats, $results_url, $sessions_url ) {
    $stats_url = is_admin()
      ? admin_url( 'admin.php?page=acdc-of-statistics' )
      : $this->portal_page_url( array( 'tab' => 'statistics' ) );
    $export_csv_url = add_query_arg( 'export', 'csv', $results_url );
    $export_pdf_url = add_query_arg( 'export', 'pdf', $results_url );
    ?>
    <?php /* Bandeau remontée statistiques */ ?>
    <div style="background:#fef6e4;border:1px solid #d6a353;border-radius:10px;padding:11px 16px;display:flex;align-items:center;gap:10px;margin-bottom:14px;font-size:12px;color:#8a6d2a;font-weight:500;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;color:#8b5b23;"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      <div>Ces scores alimentent automatiquement les <strong>Statistiques &#8212; Indicateurs de performance</strong>. <a href="<?php echo esc_url( $stats_url ); ?>" style="text-decoration:underline;color:#8a6d2a;">Voir le tableau de bord &#8594;</a></div>
    </div>

    <?php /* Bandeau Qualiopi */ ?>
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:11px 16px;display:flex;align-items:center;gap:10px;margin-bottom:18px;font-size:12px;color:#1e40af;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      <div><strong>Archive Qualiopi&#160;:</strong> scores et verbatims horodat&#233;s. Exportables en CSV ou PDF pour les audits de certification.</div>
    </div>

    <?php /* Synthèse chiffres */ ?>
    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:18px;">
      <div class="acdc-panel acdc-centered-stat" style="background:linear-gradient(135deg,#fef6e4 0%,#fbf8f7 100%);border:1px solid #f0e6dc;border-radius:12px;padding:14px 16px;text-align:center;">
        <div class="acdc-stat-number" style="font-size:28px;font-weight:700;color:var(--acdc-text);"><?php echo esc_html( null !== $stats['average_score'] ? $stats['average_score'] : '&#8212;' ); ?></div>
        <div class="acdc-stat-label" style="font-size:11px;font-weight:700;color:#8a6d2a;margin-top:5px;text-transform:uppercase;letter-spacing:.06em;">Score moyen global</div>
      </div>
      <div class="acdc-panel acdc-centered-stat" style="background:linear-gradient(135deg,#fef6e4 0%,#fbf8f7 100%);border:1px solid #f0e6dc;border-radius:12px;padding:14px 16px;text-align:center;">
        <div class="acdc-stat-number" style="font-size:28px;font-weight:700;color:var(--acdc-text);"><?php echo esc_html( null !== $stats['response_rate'] ? $stats['response_rate'] . "\u{00A0}%" : "\u{2014}" ); ?></div>
        <div class="acdc-stat-label" style="font-size:11px;font-weight:700;color:#8a6d2a;margin-top:5px;text-transform:uppercase;letter-spacing:.06em;">Taux de participation</div>
      </div>
      <div class="acdc-panel acdc-centered-stat" style="background:linear-gradient(135deg,#fef6e4 0%,#fbf8f7 100%);border:1px solid #f0e6dc;border-radius:12px;padding:14px 16px;text-align:center;">
        <div class="acdc-stat-number" style="font-size:28px;font-weight:700;<?php echo ( (int) $stats['alerts'] > 0 ) ? 'color:#c2410c;' : 'color:var(--acdc-text);'; ?>"><?php echo esc_html( $stats['alerts'] ); ?></div>
        <div class="acdc-stat-label" style="font-size:11px;font-weight:700;color:#8a6d2a;margin-top:5px;text-transform:uppercase;letter-spacing:.06em;">Alertes notation</div>
      </div>
    </div>

    <?php /* Lien résultats détaillés */ ?>
    <div class="acdc-panel" style="background:var(--acdc-bg);border:1px solid #f0e6dc;border-radius:12px;padding:18px 22px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:16px;">
      <div>
        <div style="font-size:14px;font-weight:600;color:var(--acdc-text);margin-bottom:4px;">R&#233;sultats d&#233;taill&#233;s par question</div>
        <div style="font-size:12px;color:#4b5d76;">Scores par question, verbatims, courbe de progression, r&#233;ponses individuelles.</div>
      </div>
      <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $results_url ); ?>" style="flex-shrink:0;">Voir les r&#233;sultats &#8594;</a>
    </div>

    <?php /* Export */ ?>
    <div style="display:flex;gap:8px;margin-top:4px;">
      <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $export_csv_url ); ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Exporter CSV
      </a>
      <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $export_pdf_url ); ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Export PDF Qualiopi
      </a>
    </div>
    <?php
  }



  private function render_front_mid_surveys_tab( $action, $item_id ) {
    $this->render_survey_page_layout( 'mid', 'action', $action, $item_id );
  }


  private function render_front_mid_survey_form( $survey = null, $read_only = false ) {
    $blocks = array();
    if ( $survey && ! empty( $survey->question_blocks ) && is_array( $survey->question_blocks ) ) {
      $blocks = $survey->question_blocks;
    } elseif ( $survey && ! empty( $survey->question_blocks ) && is_string( $survey->question_blocks ) ) {
      $decoded = json_decode( $survey->question_blocks, true );
      if ( is_array( $decoded ) ) {
        $blocks = $decoded;
      }
    }
    ?>
    <?php if ( $survey && ! empty( $survey->id ) ) { $this->render_questionnaire_source_visibility_panel( 'mid_survey', (int) $survey->id, (string) $survey->title ); } else { $this->render_questionnaire_source_pending_panel( 'l’enquête intermédiaire' ); } ?>
    <form class="acdc-form acdc-mid-survey-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'acdc_save_mid_survey' ); ?>
      <input type="hidden" name="action" value="acdc_save_mid_survey">
      <input type="hidden" name="survey_id" value="<?php echo $survey ? esc_attr( $survey->id ) : 0; ?>">
      <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-mid-surveys"><?php endif; ?>
      <div class="acdc-panel acdc-mb-18">
        <h3>Enquête</h3>
        <p><label>Intitulé *</label><input type="text" name="survey[title]" required value="<?php echo esc_attr( $survey ? $survey->title : '' ); ?>" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Intitulé"></p>
        <p><label>Description</label><textarea name="survey[description_text]" rows="5" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Description"><?php echo esc_textarea( $survey ? $survey->description_text : '' ); ?></textarea></p>
        <?php $this->render_survey_block_editor_ui( $blocks, $read_only, 'acdc-mid-survey-questions-wrap', 'acdc-add-mid-survey-question' ); ?>
      </div>
      <?php $this->render_survey_configuration_panel( $survey, 'mid', $read_only ); ?>
      <div class="acdc-panel acdc-mb-18">
        <h3>Seuils et alertes</h3>
        <p><label>Alerte notation</label><input type="text" name="survey[alert_notation]" value="<?php echo esc_attr( $survey ? $survey->alert_notation : '' ); ?>" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Alerte notation"></p>
        <p class="acdc-muted-note">Par question, en dessous de combien d’étoiles souhaitez-vous recevoir une alerte ?</p>
      </div>
      <p class="acdc-actions-end">
        <a class="acdc-button" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-mid-surveys' ) : $this->portal_page_url( array( 'tab' => 'mid_surveys' ) ) ); ?>" class="acdc-button acdc-button-soft">Annuler</a>
        <?php if ( ! $read_only ) : ?>
          <button type="submit" class="acdc-button acdc-button-accent" name="save_and_add" value="1">Créer & ajouter un autre</button>
          <button type="submit" class="acdc-button acdc-button-primary"><?php echo $survey ? 'Modifier une enquête de satisfaction intermédiaire' : 'Créer une enquête de satisfaction intermédiaire'; ?></button>
        <?php else : ?>
          <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-mid-surveys&action=edit&item_id=' . (int) $survey->id ) : $this->portal_page_url( array( 'tab' => 'mid_surveys', 'action' => 'edit', 'item_id' => (int) $survey->id ) ) ); ?>">Modifier cette enquête</a>
        <?php endif; ?>
      </p>
    </form>
    <?php
  }


  private function render_front_hot_surveys_tab( $action, $item_id ) {
    $this->render_survey_page_layout( 'hot', 'action', $action, $item_id );
  }


  private function render_front_hot_survey_form( $survey = null, $read_only = false ) {
    $blocks = array();
    if ( $survey && ! empty( $survey->question_blocks ) && is_array( $survey->question_blocks ) ) {
      $blocks = $survey->question_blocks;
    } elseif ( $survey && ! empty( $survey->question_blocks ) && is_string( $survey->question_blocks ) ) {
      $decoded = json_decode( $survey->question_blocks, true );
      if ( is_array( $decoded ) ) {
        $blocks = $decoded;
      }
    }
    ?>
    <?php if ( $survey && ! empty( $survey->id ) ) { $this->render_questionnaire_source_visibility_panel( 'hot_survey', (int) $survey->id, (string) $survey->title ); } else { $this->render_questionnaire_source_pending_panel( 'l’enquête à chaud' ); } ?>
    <form class="acdc-form acdc-hot-survey-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'acdc_save_hot_survey' ); ?>
      <input type="hidden" name="action" value="acdc_save_hot_survey">
      <input type="hidden" name="hot_survey_id" value="<?php echo $survey ? esc_attr( $survey->id ) : 0; ?>">
      <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-hot-surveys"><?php endif; ?>
      <div class="acdc-panel acdc-mb-18">
        <h3>Enquête</h3>
        <p><label>Intitulé *</label><input type="text" name="survey[title]" required value="<?php echo esc_attr( $survey ? $survey->title : '' ); ?>" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Intitulé"></p>
        <p><label>Description</label><textarea name="survey[description_text]" rows="5" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Description"><?php echo esc_textarea( $survey ? $survey->description_text : '' ); ?></textarea></p>
        <?php $this->render_survey_block_editor_ui( $blocks, $read_only, 'acdc-hot-survey-questions-wrap', 'acdc-add-hot-survey-question' ); ?>
      </div>
      <?php $this->render_survey_configuration_panel( $survey, 'hot', $read_only ); ?>
      <div class="acdc-panel acdc-mb-18">
        <h3>Seuils et alertes</h3>
        <p><label>Alerte notation</label><input type="text" name="survey[alert_notation]" value="<?php echo esc_attr( $survey ? $survey->alert_notation : '' ); ?>" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Alerte notation"></p>
        <p class="acdc-muted-note">Par question, en dessous de combien d’étoiles souhaitez-vous recevoir une alerte ?</p>
      </div>
      <p class="acdc-actions-end">
        <a class="acdc-button" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-hot-surveys' ) : $this->portal_page_url( array( 'tab' => 'hot_surveys' ) ) ); ?>" class="acdc-button acdc-button-soft">Annuler</a>
        <?php if ( ! $read_only ) : ?>
          <button type="submit" class="acdc-button acdc-button-accent" name="save_and_add" value="1">Créer & ajouter un autre</button>
          <button type="submit" class="acdc-button acdc-button-primary"><?php echo $survey ? 'Modifier une enquête de satisfaction à chaud' : 'Créer une enquête de satisfaction à chaud'; ?></button>
        <?php else : ?>
          <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-hot-surveys&action=edit&item_id=' . (int) $survey->id ) : $this->portal_page_url( array( 'tab' => 'hot_surveys', 'action' => 'edit', 'item_id' => (int) $survey->id ) ) ); ?>">Modifier cette enquête</a>
        <?php endif; ?>
      </p>
    </form>
    <?php
  }


  private function render_front_cold_surveys_tab( $action, $item_id ) {
    $this->render_survey_page_layout( 'cold', 'action', $action, $item_id );
  }


  private function render_front_cold_survey_form( $survey = null, $read_only = false ) {
    $blocks = array();
    if ( $survey && ! empty( $survey->question_blocks ) && is_array( $survey->question_blocks ) ) {
      $blocks = $survey->question_blocks;
    } elseif ( $survey && ! empty( $survey->question_blocks ) && is_string( $survey->question_blocks ) ) {
      $decoded = json_decode( $survey->question_blocks, true );
      if ( is_array( $decoded ) ) {
        $blocks = $decoded;
      }
    }
    ?>
    <?php if ( $survey && ! empty( $survey->id ) ) { $this->render_questionnaire_source_visibility_panel( 'cold_survey', (int) $survey->id, (string) $survey->title ); } else { $this->render_questionnaire_source_pending_panel( 'l’enquête à froid' ); } ?>
    <form class="acdc-form acdc-cold-survey-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'acdc_save_cold_survey' ); ?>
      <input type="hidden" name="action" value="acdc_save_cold_survey">
      <input type="hidden" name="cold_survey_id" value="<?php echo $survey ? esc_attr( $survey->id ) : 0; ?>">
      <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-cold-surveys"><?php endif; ?>
      <div class="acdc-panel acdc-mb-18">
        <h3>Enquête de satisfaction à froid</h3>
        <p><label>Intitulé *</label><input type="text" name="survey[title]" required value="<?php echo esc_attr( $survey ? $survey->title : '' ); ?>" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Intitulé"></p>
        <p><label>Description</label><textarea name="survey[description_text]" rows="5" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Description"><?php echo esc_textarea( $survey ? $survey->description_text : '' ); ?></textarea></p>
        <?php $this->render_survey_block_editor_ui( $blocks, $read_only, 'acdc-cold-survey-questions-wrap', 'acdc-add-cold-survey-question' ); ?>
      </div>
      <?php $this->render_survey_configuration_panel( $survey, 'cold', $read_only ); ?>
      <div class="acdc-panel acdc-mb-18">
        <h3>Seuils et alertes</h3>
        <p><label>Alerte notation</label><input type="text" name="survey[alert_notation]" value="<?php echo esc_attr( $survey ? $survey->alert_notation : '' ); ?>" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Alerte notation"></p>
        <p class="acdc-muted-note">Par question, en dessous de combien d’étoiles souhaitez-vous recevoir une alerte ?</p>
      </div>
      <p class="acdc-actions-end">
        <a class="acdc-button" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-cold-surveys' ) : $this->portal_page_url( array( 'tab' => 'cold_surveys' ) ) ); ?>" class="acdc-button acdc-button-soft">Annuler</a>
        <?php if ( ! $read_only ) : ?>
          <button type="submit" class="acdc-button acdc-button-accent" name="save_and_add" value="1">Créer & ajouter un autre</button>
          <button type="submit" class="acdc-button acdc-button-primary"><?php echo $survey ? 'Modifier une enquête de satisfaction à froid' : 'Créer une enquête de satisfaction à froid'; ?></button>
        <?php else : ?>
          <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-cold-surveys&action=edit&item_id=' . (int) $survey->id ) : $this->portal_page_url( array( 'tab' => 'cold_surveys', 'action' => 'edit', 'item_id' => (int) $survey->id ) ) ); ?>">Modifier cette enquête</a>
        <?php endif; ?>
      </p>
    </form>
    <?php
  }


  private function render_front_trainer_surveys_tab( $action, $item_id ) {
    $scope = isset( $_GET['scope'] ) && 'annual' === sanitize_text_field( wp_unslash( $_GET['scope'] ) ) ? 'annual' : 'action';
    $this->render_survey_page_layout( 'trainer', $scope, $action, $item_id );
  }


  private function render_front_trainer_surveys_hub() {
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-trainer-surveys' ) : $this->portal_page_url( array( 'tab' => 'trainer_surveys' ) );
    $dashboard_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'dashboard' ) );
    ?>
    <section class="acdc-section-head" style="align-items:flex-start;">
      <div>
        <h2>Enquêtes formateurs</h2>
        <p>Choisissez le périmètre des enquêtes formateurs.</p>
      </div>
    </section>
    <div class="acdc-mb-18">
      <a class="acdc-button" href="<?php echo esc_url( $dashboard_url ); ?>" class="acdc-button acdc-button-neutral" style="min-width:44px;padding:10px 14px;">←</a>
    </div>
    <div class="acdc-grid-2cols" style="gap:18px;">
      <a href="<?php echo esc_url( add_query_arg( array( 'scope' => 'action' ), $base_url ) ); ?>" class="acdc-panel" style="min-height:98px;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;text-decoration:none;">
        <h3 style="margin:0 0 10px;color:#C5A253;font-size:18px;">Actions de formation</h3>
        <p style="margin:0;color:var(--acdc-text-muted);">Enquêtes formateurs</p>
      </a>
      <a href="<?php echo esc_url( add_query_arg( array( 'scope' => 'annual' ), $base_url ) ); ?>" class="acdc-panel" style="min-height:98px;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;text-decoration:none;">
        <h3 style="margin:0 0 10px;color:#C5A253;font-size:18px;">Annuelles</h3>
        <p style="margin:0;color:var(--acdc-text-muted);">Enquêtes formateurs</p>
      </a>
    </div>
    <?php
  }


  private function render_front_trainer_surveys_scope( $scope, $action, $item_id ) {
    $search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
    $surveys = $this->get_trainer_surveys( $scope, $search, true );
    $survey = $item_id ? $this->get_trainer_survey( $item_id ) : null;
    if ( $survey && isset( $survey->scope ) && $scope !== $survey->scope ) {
      $scope = sanitize_key( (string) $survey->scope );
    }
    $scope_label = $this->get_trainer_survey_scope_label( $scope );
    $scope_heading = 'annual' === $scope ? 'Enquêtes formateurs annuelles' : 'Enquêtes formateurs';
    $create_label = 'annual' === $scope ? 'Créer une enquête formateur annuelle' : 'Créer une enquête formateur';
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-trainer-surveys' ) : $this->portal_page_url( array( 'tab' => 'trainer_surveys' ) );
    $scope_url = add_query_arg( array( 'scope' => $scope ), $base_url );
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Enquêtes formateurs</h2>
        <p><?php echo esc_html( $scope_label ); ?></p>
      </div>
      <div class="acdc-inline-wrap">
        <a class="acdc-button <?php echo 'action' === $scope ? 'acdc-button-primary' : 'acdc-button-soft'; ?>" href="<?php echo esc_url( add_query_arg( array( 'scope' => 'action' ), $base_url ) ); ?>">Actions de formation</a>
        <a class="acdc-button <?php echo 'annual' === $scope ? 'acdc-button-primary' : 'acdc-button-soft'; ?>" href="<?php echo esc_url( add_query_arg( array( 'scope' => 'annual' ), $base_url ) ); ?>">Annuelles</a>
        <button type="button" class="acdc-button acdc-button-primary" data-acdc-open-modal="acdc-create-trainer-survey-from-model-modal">Créer depuis le modèle</button>
        <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( add_query_arg( array( 'scope' => $scope, 'action' => 'new' ), $base_url ) ); ?>"><?php echo esc_html( $create_label ); ?></a>
      </div>
    </section>
    <?php $this->render_survey_management_shortcuts( 'trainer' ); ?>
    <?php
    if ( $this->should_render_survey_form_only( $action ) ) {
      $this->render_survey_back_link( $scope_url );
      $this->render_front_trainer_survey_form( $survey, 'view' === $action, $scope );
      return;
    }
    ?>
    <?php $this->render_survey_list_summary( $surveys, 'enquêtes formateurs' ); ?>
    <div class="acdc-panel acdc-mb-18">
      <form method="get" action="">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-trainer-surveys"><?php endif; ?>
        <input type="hidden" name="tab" value="trainer_surveys">
        <input type="hidden" name="scope" value="<?php echo esc_attr( $scope ); ?>">
        <div class="acdc-inline-wrap acdc-inline-wrap-center">
          <input type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="Rechercher" style="max-width:420px;">
          <button type="submit" class="acdc-button acdc-button-soft">Rechercher</button>
        </div>
      </form>
    </div>
    <div class="acdc-panel">
      <div class="acdc-table-wrap">
        <table class="acdc-table">
          <thead>
            <tr>
              <th>Intitulé</th>
              <th>Alerte notation</th>
              <th>Diffusion</th>
              <th>Modifié le</th>
              <th style="width:220px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $surveys as $entry ) : ?>
              <tr>
                <td><?php echo esc_html( $entry['title'] ?? '' ); ?></td>
                <td><?php echo '' !== trim( (string) ( $entry['alert_notation'] ?? '' ) ) ? esc_html( $entry['alert_notation'] ) : '—'; ?></td>
                <td><?php $this->render_survey_diffusion_cell( 'trainer', (int) ( $entry['id'] ?? 0 ) ); ?></td>
                <td><?php echo esc_html( ! empty( $entry['updated_at'] ) ? wp_date( 'j F Y à H\hi', strtotime( (string) $entry['updated_at'] ) ) : '' ); ?></td>
                <td>
                  <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;">
                    <a href="<?php echo esc_url( add_query_arg( array( 'scope' => $scope, 'action' => 'view', 'item_id' => (int) $entry['id'] ), $base_url ) ); ?>" title="Voir" aria-label="Voir" class="acdc-row-action-icon acdc-row-delete-link" data-acdc-iconized="1"><?php echo $this->render_inline_icon( 'view', 25 ); ?></a>
                    <a href="<?php echo esc_url( add_query_arg( array( 'scope' => $scope, 'action' => 'edit', 'item_id' => (int) $entry['id'] ), $base_url ) ); ?>" title="Modifier" aria-label="Modifier" class="acdc-row-action-icon acdc-row-delete-link" data-acdc-iconized="1"><?php echo $this->render_inline_icon( 'edit-pencil', 25 ); ?></a>
                    <a href="<?php echo esc_url( $this->get_questionnaire_sessions_url( 'trainer_survey', (int) $entry['id'] ) ); ?>" title="Sessions" aria-label="Sessions" class="acdc-row-action-icon acdc-row-delete-link" data-acdc-iconized="1"><?php echo $this->render_inline_icon( 'calendar', 18 ); ?></a>
                    <a href="<?php echo esc_url( $this->get_questionnaire_results_url( 'trainer_survey', 0, (int) $entry['id'] ) ); ?>" title="Résultats" aria-label="Résultats" class="acdc-row-action-icon acdc-row-delete-link" data-acdc-iconized="1"><?php echo $this->render_inline_icon( 'chart-bar', 18 ); ?></a>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_delete_trainer_survey&survey_id=' . (int) $entry['id'] . '&scope=' . rawurlencode( $scope ) . ( is_admin() ? '&page=acdc-of-trainer-surveys' : '' ) ), 'acdc_delete_trainer_survey_' . (int) $entry['id'] ) ); ?>" onclick="return confirm('Supprimer cette enquête formateur ?');" title="Supprimer" aria-label="Supprimer" class="acdc-row-action-icon acdc-row-delete-link" data-acdc-iconized="1"><?php echo $this->render_inline_icon( 'trash-bin', 25 ); ?></a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if ( empty( $surveys ) ) : ?>
            <tr><td colspan="5">Aucune enquête formateur enregistrée.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="acdc-modal-shell" id="acdc-create-trainer-survey-from-model-modal" hidden>
      <div class="acdc-modal-backdrop" data-acdc-close-modal></div>
      <div class="acdc-modal-dialog acdc-modal-dialog-medium">
        <div class="acdc-modal-header"><h4 id="acdc-create-trainer-survey-from-model-title">Créer depuis le modèle</h4><button type="button" class="acdc-modal-close" data-acdc-close-modal aria-label="Fermer">&times;</button></div>
        <div class="acdc-modal-body acdc-pad-24">
          <p>Êtes-vous sûr de vouloir exécuter cette action ?</p>
          <div style="display:flex;justify-content:flex-end;gap:12px;align-items:center;flex-wrap:wrap;">
            <button type="button" class="acdc-button acdc-button-soft" data-acdc-close-modal>Annuler</button>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
              <?php wp_nonce_field( 'acdc_create_trainer_survey_from_model' ); ?>
              <input type="hidden" name="action" value="acdc_create_trainer_survey_from_model">
              <input type="hidden" name="scope" value="<?php echo esc_attr( $scope ); ?>">
              <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-trainer-surveys"><?php endif; ?>
              <button type="submit" class="acdc-button acdc-button-primary">Exécuter l’action</button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
      document.querySelectorAll('[data-acdc-open-modal]').forEach(function(btn){
        btn.addEventListener('click', function(){
          var id = btn.getAttribute('data-acdc-open-modal');
          var modal = document.getElementById(id);
          if(modal){ modal.hidden = false; document.body.classList.add('acdc-modal-open'); }
        });
      });
      document.querySelectorAll('[data-acdc-close-modal]').forEach(function(btn){
        btn.addEventListener('click', function(){
          var modal = btn.closest('.acdc-modal-shell');
          if(modal){ modal.hidden = true; document.body.classList.remove('acdc-modal-open'); }
        });
      });
    });
    </script>
    <?php
  }


  private function render_front_trainer_survey_form( $survey = null, $read_only = false, $scope = 'action' ) {
    $blocks = array();
    if ( $survey && ! empty( $survey->question_blocks ) && is_array( $survey->question_blocks ) ) {
      $blocks = $survey->question_blocks;
    } elseif ( $survey && ! empty( $survey->question_blocks ) && is_string( $survey->question_blocks ) ) {
      $decoded = json_decode( $survey->question_blocks, true );
      if ( is_array( $decoded ) ) {
        $blocks = $decoded;
      }
    }
    $scope = $survey && ! empty( $survey->scope ) ? sanitize_key( (string) $survey->scope ) : $scope;
    $scope = 'annual' === $scope ? 'annual' : 'action';
    ?>
    <?php if ( $survey && ! empty( $survey->id ) ) { $this->render_questionnaire_source_visibility_panel( 'trainer_survey', (int) $survey->id, (string) $survey->title ); } else { $this->render_questionnaire_source_pending_panel( 'l’enquête formateur' ); } ?>
    <form id="acdc-trainer-survey-form" class="acdc-form acdc-trainer-survey-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'acdc_save_trainer_survey' ); ?>
      <input type="hidden" name="action" value="acdc_save_trainer_survey">
      <input type="hidden" name="trainer_survey_id" value="<?php echo $survey ? esc_attr( $survey->id ) : 0; ?>">
      <input type="hidden" name="survey[scope]" value="<?php echo esc_attr( $scope ); ?>">
      <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-trainer-surveys"><?php endif; ?>
      <div class="acdc-panel acdc-mb-18">
        <h3>Enquête formateur</h3>
        <p><label>Intitulé *</label><input type="text" name="survey[title]" required value="<?php echo esc_attr( $survey ? $survey->title : '' ); ?>" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Intitulé"></p>
        <p><label>Description</label><textarea name="survey[description_text]" rows="5" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Description"><?php echo esc_textarea( $survey ? $survey->description_text : '' ); ?></textarea></p>
        <?php $this->render_survey_block_editor_ui( $blocks, $read_only, 'acdc-trainer-survey-questions-wrap', 'acdc-add-trainer-survey-question' ); ?>
      </div>
      <?php $this->render_survey_configuration_panel( $survey, 'trainer', $read_only, $scope ); ?>
      <div class="acdc-panel acdc-mb-18">
        <h3>Seuils et alertes</h3>
        <p><label>Alerte notation</label><input type="text" name="survey[alert_notation]" value="<?php echo esc_attr( $survey ? $survey->alert_notation : ( 'annual' === $scope ? '' : '3' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Alerte notation"></p>
        <p class="acdc-muted-note-small">Par question, en dessous de combien d’étoiles souhaitez-vous recevoir une alerte ?</p>
      </div>
      <div class="acdc-actions-end-wrap">
        <a class="acdc-button" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-trainer-surveys&scope=' . $scope ) : $this->portal_page_url( array( 'tab' => 'trainer_surveys', 'scope' => $scope ) ) ); ?>" class="acdc-button acdc-button-soft">Annuler</a>
        <?php if ( ! $read_only ) : ?>
          <button type="submit" name="save_and_add" value="1" class="acdc-button acdc-button-primary">Créer & ajouter un autre</button>
          <button type="submit" class="acdc-button acdc-button-primary"><?php echo $survey ? ( 'annual' === $scope ? 'Modifier une enquête formateur annuelle' : 'Modifier une enquête formateur' ) : ( 'annual' === $scope ? 'Créer une enquête formateur annuelle' : 'Créer une enquête formateur' ); ?></button>
        <?php else : ?>
          <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-trainer-surveys&scope=' . $scope . '&action=edit&item_id=' . (int) $survey->id ) : $this->portal_page_url( array( 'tab' => 'trainer_surveys', 'scope' => $scope, 'action' => 'edit', 'item_id' => (int) $survey->id ) ) ); ?>">Modifier cette enquête</a>
        <?php endif; ?>
      </div>
    </form>
    <?php
  }


  private function render_front_company_surveys_tab( $action, $item_id ) {
    $this->render_survey_page_layout( 'company', 'action', $action, $item_id );
  }


  private function render_front_company_survey_form( $survey = null, $read_only = false ) {
    $blocks = array();
    if ( $survey && ! empty( $survey->question_blocks ) && is_array( $survey->question_blocks ) ) {
      $blocks = $survey->question_blocks;
    } elseif ( $survey && ! empty( $survey->question_blocks ) && is_string( $survey->question_blocks ) ) {
      $decoded = json_decode( $survey->question_blocks, true );
      if ( is_array( $decoded ) ) {
        $blocks = $decoded;
      }
    }
    ?>
    <?php if ( $survey && ! empty( $survey->id ) ) { $this->render_questionnaire_source_visibility_panel( 'company_survey', (int) $survey->id, (string) $survey->title ); } else { $this->render_questionnaire_source_pending_panel( 'l’enquête entreprise' ); } ?>
    <form class="acdc-form acdc-company-survey-form" id="acdc-company-survey-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'acdc_save_company_survey' ); ?>
      <input type="hidden" name="action" value="acdc_save_company_survey">
      <input type="hidden" name="company_survey_id" value="<?php echo $survey ? esc_attr( $survey->id ) : 0; ?>">
      <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-company-surveys"><?php endif; ?>
      <div class="acdc-panel acdc-mb-18">
        <h3>Enquête entreprise</h3>
        <p><label>Intitulé *</label><input type="text" name="survey[title]" required value="<?php echo esc_attr( $survey ? $survey->title : '' ); ?>" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Intitulé"></p>
        <p><label>Description</label><textarea name="survey[description_text]" rows="5" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Description"><?php echo esc_textarea( $survey ? $survey->description_text : '' ); ?></textarea></p>
        <?php $this->render_survey_block_editor_ui( $blocks, $read_only, 'acdc-company-survey-questions-wrap', 'acdc-add-company-survey-question' ); ?>
      </div>
      <?php $this->render_survey_configuration_panel( $survey, 'company', $read_only ); ?>
      <div class="acdc-panel acdc-mb-18">
        <h3>Seuils et alertes</h3>
        <p><label>Alerte notation</label><input type="text" name="survey[alert_notation]" value="<?php echo esc_attr( $survey ? $survey->alert_notation : '' ); ?>" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Alerte notation"></p>
        <p class="acdc-muted-note">Par question, en dessous de combien d’étoiles souhaitez-vous recevoir une alerte ?</p>
      </div>
      <p class="acdc-actions-end">
        <a class="acdc-button" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-company-surveys' ) : $this->portal_page_url( array( 'tab' => 'company_surveys' ) ) ); ?>" class="acdc-button acdc-button-soft">Annuler</a>
        <?php if ( ! $read_only ) : ?>
          <button type="submit" class="acdc-button acdc-button-accent" name="save_and_add" value="1">Créer & ajouter un autre</button>
          <button type="submit" class="acdc-button acdc-button-primary"><?php echo $survey ? 'Modifier une enquête entreprise' : 'Créer une enquête entreprise'; ?></button>
        <?php else : ?>
          <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-company-surveys&action=edit&item_id=' . (int) $survey->id ) : $this->portal_page_url( array( 'tab' => 'company_surveys', 'action' => 'edit', 'item_id' => (int) $survey->id ) ) ); ?>">Modifier cette enquête entreprise</a>
        <?php endif; ?>
      </p>
    </form>
    <?php
  }


  private function render_front_funder_surveys_tab( $action, $item_id ) {
    $scope = isset( $_GET['scope'] ) && 'annual' === sanitize_text_field( wp_unslash( $_GET['scope'] ) ) ? 'annual' : 'action';
    $this->render_survey_page_layout( 'funder', $scope, $action, $item_id );
  }


  private function render_front_funder_survey_form( $survey = null, $read_only = false, $scope = 'action' ) {
    $blocks = array();
    if ( $survey && ! empty( $survey->question_blocks ) && is_array( $survey->question_blocks ) ) {
      $blocks = $survey->question_blocks;
    } elseif ( $survey && ! empty( $survey->question_blocks ) && is_string( $survey->question_blocks ) ) {
      $decoded = json_decode( $survey->question_blocks, true );
      if ( is_array( $decoded ) ) {
        $blocks = $decoded;
      }
    }
    ?>
    <?php if ( $survey && ! empty( $survey->id ) ) { $this->render_questionnaire_source_visibility_panel( 'funder_survey', (int) $survey->id, (string) $survey->title ); } else { $this->render_questionnaire_source_pending_panel( 'l’enquête financeur' ); } ?>
    <form class="acdc-form acdc-funder-survey-form" id="acdc-funder-survey-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'acdc_save_funder_survey' ); ?>
      <input type="hidden" name="action" value="acdc_save_funder_survey">
      <input type="hidden" name="funder_survey_id" value="<?php echo $survey ? esc_attr( $survey->id ) : 0; ?>">
      <input type="hidden" name="survey_scope" value="<?php echo esc_attr( $scope ); ?>">
      <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-funder-surveys"><?php endif; ?>
      <div class="acdc-panel acdc-mb-18">
        <h3><?php echo 'annual' === $scope ? 'Enquête financeur annuelle' : 'Enquête financeur'; ?></h3>
        <p><label>Intitulé *</label><input type="text" name="survey[title]" required value="<?php echo esc_attr( $survey ? $survey->title : '' ); ?>" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Intitulé"></p>
        <p><label>Description</label><textarea name="survey[description_text]" rows="5" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Description"><?php echo esc_textarea( $survey ? $survey->description_text : '' ); ?></textarea></p>
        <?php $this->render_survey_block_editor_ui( $blocks, $read_only, 'acdc-funder-survey-questions-wrap', 'acdc-add-funder-survey-question' ); ?>
      </div>
      <?php $this->render_survey_configuration_panel( $survey, 'funder', $read_only, $scope ); ?>
      <div class="acdc-panel acdc-mb-18">
        <h3>Seuils et alertes</h3>
        <p><label>Alerte notation</label><input type="text" name="survey[alert_notation]" value="<?php echo esc_attr( $survey ? $survey->alert_notation : '' ); ?>" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Alerte notation"></p>
        <p class="acdc-muted-note">Par question, en dessous de combien d’étoiles souhaitez-vous recevoir une alerte ?</p>
      </div>
      <p class="acdc-actions-end">
        <a class="acdc-button" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-funder-surveys&scope=' . $scope ) : $this->portal_page_url( array( 'tab' => 'funder_surveys', 'scope' => $scope ) ) ); ?>" class="acdc-button acdc-button-soft">Annuler</a>
        <?php if ( ! $read_only ) : ?>
          <button type="submit" class="acdc-button acdc-button-accent" name="save_and_add" value="1">Créer & ajouter un autre</button>
          <button type="submit" class="acdc-button acdc-button-primary"><?php echo $survey ? 'Modifier une enquête financeur' : 'Créer une enquête financeur'; ?></button>
        <?php else : ?>
          <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-funder-surveys&scope=' . $scope . '&action=edit&item_id=' . (int) $survey->id ) : $this->portal_page_url( array( 'tab' => 'funder_surveys', 'scope' => $scope, 'action' => 'edit', 'item_id' => (int) $survey->id ) ) ); ?>">Modifier cette enquête financeur</a>
        <?php endif; ?>
      </p>
    </form>
    <?php
  }


  public function render_questionnaire_session_shortcode( $atts = array() ) {
    $token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
    return $this->render_questionnaire_session_public_page( $token );
  }


  private function render_questionnaire_source_pending_panel( $label ) {
    ?>
    <div class="acdc-panel acdc-mb-18">
      <div class="acdc-section-head">
        <div>
          <h3 style="margin:0;">Sessions et diffusion</h3>
          <p>Enregistrez d’abord <?php echo esc_html( (string) $label ); ?> pour pouvoir créer une session, générer un lien apprenant et afficher un QR code.</p>
        </div>
      </div>
    </div>
    <?php
  }


  private function render_questionnaire_source_visibility_panel( $source_type, $source_id, $title ) {
    $count = $this->get_questionnaire_session_count_for_source( $source_type, $source_id );
    ?>
    <div class="acdc-panel acdc-mb-18">
      <div class="acdc-section-head">
        <div>
          <h3 style="margin:0;">Sessions et diffusion</h3>
          <p><?php echo esc_html( $title ); ?> peut maintenant être utilisé dans une ou plusieurs diffusions avec lien sécurisé, suivi des réponses et résultats centralisés.</p>
        </div>
        <div class="acdc-inline-wrap">
          <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $this->get_questionnaire_new_session_url( $source_type, $source_id ) ); ?>">Créer un envoi</a>
          <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->get_questionnaire_sessions_url( $source_type, $source_id ) ); ?>">Voir les sessions (<?php echo esc_html( $count ); ?>)</a>
          <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->get_questionnaire_results_url( $source_type, 0, $source_id ) ); ?>">Résultats</a>
        </div>
      </div>
    </div>
    <?php
  }


  private function render_front_questionnaire_sessions_tab( $action, $item_id ) {
    global $wpdb;
    $session = $item_id ? $this->get_questionnaire_session( $item_id ) : null;
    $prefill_source_type = isset( $_GET['source_type'] ) ? sanitize_text_field( wp_unslash( $_GET['source_type'] ) ) : '';
    $prefill_source_id   = isset( $_GET['source_id'] ) ? absint( wp_unslash( $_GET['source_id'] ) ) : 0;
    $filter_status       = isset( $_GET['session_status'] ) ? sanitize_text_field( wp_unslash( $_GET['session_status'] ) ) : '';
    $filter_search       = isset( $_GET['session_search'] ) ? sanitize_text_field( wp_unslash( $_GET['session_search'] ) ) : '';
    $context             = $this->get_questionnaire_context_config( $prefill_source_type );
    $questionnaire_defaults = $this->get_questionnaire_default_settings_array();
    $source_map          = $this->get_questionnaire_source_label_map();
    $base_url            = is_admin() ? admin_url( 'admin.php?page=acdc-of-questionnaire-sessions' ) : $this->portal_page_url( array( 'tab' => 'questionnaire_sessions' ) );
    $formations          = $this->get_formations( array( 'archived' => false ) );
    if ( empty( $formations ) ) { $formations = $this->get_formations(); }
    $trainers            = $this->get_trainers();
    $companies           = $this->get_companies();
    $funders             = $this->get_funders();
    $selected_company_id = $session ? absint( $session->company_id ) : ( isset( $_GET['company_id'] ) ? absint( wp_unslash( $_GET['company_id'] ) ) : 0 );
    $selected_funder_id  = $session ? absint( $session->funder_id ) : ( isset( $_GET['funder_id'] ) ? absint( wp_unslash( $_GET['funder_id'] ) ) : 0 );
    $company_contacts    = $selected_company_id ? $this->get_related_contacts( $selected_company_id ) : array();
    $sessions_filters    = array();
    if ( '' !== $prefill_source_type ) { $sessions_filters['source_type'] = $prefill_source_type; }
    if ( $prefill_source_id > 0 ) { $sessions_filters['source_id'] = $prefill_source_id; }
    if ( '' !== $filter_status ) { $sessions_filters['status'] = $filter_status; }
    if ( '' !== $filter_search ) { $sessions_filters['search'] = $filter_search; }
    $sessions            = $this->get_questionnaire_sessions( $sessions_filters );
    $stats               = $this->get_questionnaire_session_stats( $sessions_filters );
    /* ACDC 3.25.291 — Les libellés des compteurs lisaient $terms, jamais chargé
       ici : les quatre tuiles de statistiques affichaient un nombre suivi d'un
       libellé vide. Le vocabulaire dépend du type d'enquête, comme ailleurs. */
    $terms               = $this->get_questionnaire_results_terms( $prefill_source_type );
    $header_title        = $context['session_title'];
    $header_description  = $context['session_description'];
    $create_label        = $context['create_session_label'];
    if ( 'mid_survey' === $prefill_source_type ) {
      $header_title = 'Envois — enquêtes intermédiaires';
      $header_description = 'Planifiez, envoyez et relancez les enquêtes intermédiaires à partir du modèle sélectionné.';
      $create_label = 'Planifier un envoi intermédiaire';
    } elseif ( 'hot_survey' === $prefill_source_type ) {
      $header_title = 'Envois — enquêtes à chaud';
      $header_description = 'Planifiez, envoyez et suivez les enquêtes à chaud à partir du modèle sélectionné.';
      $create_label = 'Planifier un envoi à chaud';
    } elseif ( 'cold_survey' === $prefill_source_type ) {
      $header_title = 'Envois — enquêtes à froid';
      $header_description = 'Planifiez, envoyez et suivez les enquêtes à froid à partir du modèle sélectionné.';
      $create_label = 'Planifier un envoi à froid';
    }
    $source_focus        = null;
    if ( '' !== $prefill_source_type && $prefill_source_id > 0 ) {
      $source_focus = $this->get_questionnaire_source_data( $prefill_source_type, $prefill_source_id );
      if ( $source_focus ) {
        $header_title       = 'Sessions — ' . $source_focus['title'];
        $header_description = 'Toutes les sessions créées à partir de ce ' . $context['label_singular'] . ' sont listées ici.';
      }
    }
    $editing_source = $source_focus;
    if ( $session ) {
      $editing_source = $this->get_questionnaire_source_data( $session->source_type, $session->source_id );
    }
    $preview_session = $session ? $session : (object) array(
      'formation_id' => isset( $_GET['formation_id'] ) ? absint( wp_unslash( $_GET['formation_id'] ) ) : 0,
      'seance_id' => 0,
    );
    $registered_learners = $editing_source ? $this->get_registered_learners_for_questionnaire_session( $preview_session ) : array();
    $selected_learner_ids = $session ? $this->get_questionnaire_selected_learner_ids_for_session( $session ) : array();
    $targeted_learners = $editing_source ? ( $session ? $this->get_questionnaire_targeted_learners_for_questionnaire_session( $session ) : $registered_learners ) : array();
    $is_survey_source = $editing_source && $this->is_survey_questionnaire_source_type( $editing_source['type'] );
    $is_learner_survey_source = $is_survey_source && $this->is_learner_questionnaire_source_type( $editing_source['type'] );
    $is_trainer_survey_source = $editing_source && 'trainer_survey' === $editing_source['type'];
    $is_company_survey_source = $editing_source && 'company_survey' === $editing_source['type'];
    $is_funder_survey_source = $editing_source && 'funder_survey' === $editing_source['type'];
    $is_annual_trainer_survey_source = $is_trainer_survey_source && $this->is_questionnaire_annual_campaign_source( $editing_source );
    $is_annual_funder_survey_source = $is_funder_survey_source && $this->is_questionnaire_annual_campaign_source( $editing_source );
    $delivery_targets = $session ? $this->get_questionnaire_delivery_targets( $session ) : array();
    $create_args = array( 'action' => 'new' );
    if ( '' !== $prefill_source_type ) { $create_args['source_type'] = $prefill_source_type; }
    if ( $prefill_source_id > 0 ) { $create_args['source_id'] = $prefill_source_id; }
    ?>
    <section class="acdc-section-head"><div><h2><?php echo esc_html( $header_title ); ?></h2><p><?php echo esc_html( $header_description ); ?></p></div><div class="acdc-inline-wrap"><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( add_query_arg( $create_args, $base_url ) ); ?>"><?php echo esc_html( $create_label ); ?></a></div></section>
    <div class="acdc-grid-4cols acdc-mb-18">
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $stats['total'] ); ?></div><div class="acdc-stat-label">Sessions</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $stats['active'] ); ?></div><div class="acdc-stat-label">En cours / ouvertes</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $stats['finished'] ); ?></div><div class="acdc-stat-label">Terminées</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $stats['participants'] ); ?></div><div class="acdc-stat-label"><?php echo esc_html( $terms['participants'] . ' cumulés' ); ?></div></div>
    </div>
    <?php if ( 'mid_survey' === $prefill_source_type ) :
      $responses_count = isset( $stats['responses'] ) ? (int) $stats['responses'] : 0;
      $pending_count = max( 0, (int) $stats['participants'] - $responses_count );
      $response_rate = isset( $stats['response_rate'] ) && null !== $stats['response_rate'] ? $stats['response_rate'] . ' %' : '—';
      $alerts_count = isset( $stats['alerts'] ) ? (int) $stats['alerts'] : 0;
    ?>
    <div class="acdc-grid-4cols acdc-mb-18">
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $responses_count ); ?></div><div class="acdc-stat-label">Réponses</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $pending_count ); ?></div><div class="acdc-stat-label">Relances potentielles</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $response_rate ); ?></div><div class="acdc-stat-label">Taux de réponse</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $alerts_count ); ?></div><div class="acdc-stat-label">Alertes</div></div>
    </div>
    <?php $automation_overview = method_exists( $this, 'get_mid_survey_automation_overview' ) ? $this->get_mid_survey_automation_overview() : array(); ?>
    <div class="acdc-panel acdc-mb-18">
      <div class="acdc-section-head">
        <div>
          <h3 style="margin:0;">Supervision automatique — enquêtes intermédiaires</h3>
          <p>Suivi des créations automatiques, des déclenchements à venir et des relances automatiques encore en attente d’exécution.</p>
        </div>
      </div>
      <div class="acdc-grid-4cols">
        <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( (int) ( $automation_overview['planned'] ?? 0 ) ); ?></div><div class="acdc-stat-label">Sessions auto planifiées</div></div>
        <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( (int) ( $automation_overview['upcoming'] ?? 0 ) ); ?></div><div class="acdc-stat-label">Déclenchements à venir</div></div>
        <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( (int) ( $automation_overview['sent'] ?? 0 ) ); ?></div><div class="acdc-stat-label">Envois auto déjà exécutés</div></div>
        <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( (int) ( $automation_overview['reminders_due'] ?? 0 ) ); ?></div><div class="acdc-stat-label">Relances auto à exécuter</div></div>
      </div>
      <p class="acdc-muted-note" style="margin-top:12px;">Dernière session créée automatiquement : <?php echo ! empty( $automation_overview['last_trigger_at'] ) ? esc_html( mysql2date( 'j F Y à H\hi', $automation_overview['last_trigger_at'] ) ) : '—'; ?>.</p>
    </div>
    <?php elseif ( 'hot_survey' === $prefill_source_type ) :
      $responses_count = isset( $stats['responses'] ) ? (int) $stats['responses'] : 0;
      $pending_count = max( 0, (int) $stats['participants'] - $responses_count );
      $response_rate = isset( $stats['response_rate'] ) && null !== $stats['response_rate'] ? $stats['response_rate'] . ' %' : '—';
      $alerts_count = isset( $stats['alerts'] ) ? (int) $stats['alerts'] : 0;
    ?>
    <div class="acdc-grid-4cols acdc-mb-18">
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $responses_count ); ?></div><div class="acdc-stat-label">Réponses</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $pending_count ); ?></div><div class="acdc-stat-label">Non-répondants</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $response_rate ); ?></div><div class="acdc-stat-label">Taux de réponse</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $alerts_count ); ?></div><div class="acdc-stat-label">Alertes qualité</div></div>
    </div>
    <div class="acdc-panel acdc-mb-18">
      <div class="acdc-section-head">
        <div>
          <h3 style="margin:0;">Fondations métier visibles — enquêtes à chaud</h3>
          <p>Le module à chaud dispose maintenant de ses accès rapides, de ses paramètres métier dédiés, de ses indicateurs visibles et d’un vocabulaire de résultats orienté satisfaction immédiate.</p>
        </div>
      </div>
      <div class="acdc-grid-4cols">
        <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( (int) $stats['total'] ); ?></div><div class="acdc-stat-label">Envois créés</div></div>
        <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( (int) $stats['active'] ); ?></div><div class="acdc-stat-label">Envois actifs</div></div>
        <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( (int) $stats['finished'] ); ?></div><div class="acdc-stat-label">Envois terminés</div></div>
        <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( isset( $stats['average_score'] ) && null !== $stats['average_score'] ? $stats['average_score'] : '—' ); ?></div><div class="acdc-stat-label">Satisfaction moyenne</div></div>
      </div>
    </div>
    <?php elseif ( 'cold_survey' === $prefill_source_type ) :
      $responses_count = isset( $stats['responses'] ) ? (int) $stats['responses'] : 0;
      $pending_count = max( 0, (int) $stats['participants'] - $responses_count );
      $response_rate = isset( $stats['response_rate'] ) && null !== $stats['response_rate'] ? $stats['response_rate'] . ' %' : '—';
      $alerts_count = isset( $stats['alerts'] ) ? (int) $stats['alerts'] : 0;
      $average_score = isset( $stats['average_score'] ) && null !== $stats['average_score'] ? $stats['average_score'] : '—';
    ?>
    <div class="acdc-grid-4cols acdc-mb-18">
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $responses_count ); ?></div><div class="acdc-stat-label">Réponses</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $pending_count ); ?></div><div class="acdc-stat-label">Retours attendus</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $response_rate ); ?></div><div class="acdc-stat-label">Taux de retour</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $alerts_count ); ?></div><div class="acdc-stat-label">Signaux faibles</div></div>
    </div>
    <div class="acdc-panel acdc-mb-18">
      <div class="acdc-section-head">
        <div>
          <h3 style="margin:0;">Flux visible — enquêtes à froid</h3>
          <p>Le module à froid affiche maintenant clairement la planification différée, les relances des non-répondants et la lecture des retours attendus après formation.</p>
        </div>
      </div>
      <div class="acdc-grid-4cols">
        <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( (int) $stats['total'] ); ?></div><div class="acdc-stat-label">Envois à froid créés</div></div>
        <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( (int) $stats['active'] ); ?></div><div class="acdc-stat-label">Envois à surveiller</div></div>
        <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( (int) $stats['finished'] ); ?></div><div class="acdc-stat-label">Envois terminés</div></div>
        <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $average_score ); ?></div><div class="acdc-stat-label">Utilité perçue moyenne</div></div>
      </div>
    </div>
    <?php $cold_automation_overview = method_exists( $this, 'get_cold_survey_automation_overview' ) ? $this->get_cold_survey_automation_overview() : array(); ?>
    <div class="acdc-panel acdc-mb-18">
      <div class="acdc-section-head">
        <div>
          <h3 style="margin:0;">Supervision automatique — enquêtes à froid</h3>
          <p>Suivi des créations automatiques à J+30, des déclenchements différés à venir et des relances automatiques encore en attente d’exécution.</p>
        </div>
      </div>
      <div class="acdc-grid-4cols">
        <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( (int) ( $cold_automation_overview['planned'] ?? 0 ) ); ?></div><div class="acdc-stat-label">Sessions auto planifiées</div></div>
        <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( (int) ( $cold_automation_overview['upcoming'] ?? 0 ) ); ?></div><div class="acdc-stat-label">Déclenchements à venir</div></div>
        <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( (int) ( $cold_automation_overview['sent'] ?? 0 ) ); ?></div><div class="acdc-stat-label">Envois auto exécutés</div></div>
        <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( (int) ( $cold_automation_overview['reminders_due'] ?? 0 ) ); ?></div><div class="acdc-stat-label">Relances auto à exécuter</div></div>
      </div>
      <p class="acdc-muted-note" style="margin-top:12px;">Dernière session créée automatiquement : <?php echo ! empty( $cold_automation_overview['last_trigger_at'] ) ? esc_html( mysql2date( 'j F Y à H\hi', $cold_automation_overview['last_trigger_at'] ) ) : '—'; ?>.</p>
    </div>
    <?php endif; ?>
    <div class="acdc-panel acdc-mb-18">
      <form method="get" action="<?php echo esc_url( is_admin() ? admin_url( 'admin.php' ) : $base_url ); ?>" class="acdc-filters-bar">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-questionnaire-sessions"><?php else : ?><input type="hidden" name="tab" value="questionnaire_sessions"><?php endif; ?>
        <?php if ( '' !== $prefill_source_type ) : ?><input type="hidden" name="source_type" value="<?php echo esc_attr( $prefill_source_type ); ?>"><?php endif; ?>
        <?php if ( $prefill_source_id > 0 ) : ?><input type="hidden" name="source_id" value="<?php echo (int) $prefill_source_id; ?>"><?php endif; ?>
        <input type="text" name="session_search" value="<?php echo esc_attr( $filter_search ); ?>" placeholder="Rechercher une session">
        <select name="session_status">
          <option value="">Tous les statuts</option>
          <?php foreach ( array( 'brouillon','planifiee','envoyee','prete','ouverte','en_cours','en_pause','terminee','expiree','annulee','archivee' ) as $status ) : ?>
            <option value="<?php echo esc_attr( $status ); ?>" <?php selected( $filter_status, $status ); ?>><?php echo esc_html( $this->questionnaire_session_status_label( $status ) ); ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="acdc-button acdc-button-soft">Filtrer</button>
      </form>
    </div>
    <?php if ( in_array( $action, array( 'new', 'edit', 'view', 'animate' ), true ) ) : ?>
      <?php if ( 'animate' === $action && $session ) : ?>
        <?php $this->render_questionnaire_session_animation_admin( $session ); ?>
      <?php else : ?>
      <div class="acdc-panel acdc-mb-18"><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acdc-form">
        <?php wp_nonce_field( 'acdc_save_questionnaire_session' ); ?><input type="hidden" name="action" value="acdc_save_questionnaire_session"><?php /* ACDC 3.25.157 — Origine explicite du formulaire. Le handler tourne sous admin-post.php, où is_admin() vaut TOUJOURS true : sans ce marqueur, un enregistrement lancé depuis l'extranet renvoyait vers wp-admin, page à laquelle le gestionnaire n'a pas accès — il voyait « Vous n'avez pas l'autorisation » alors que sa session était bien enregistrée. */ ?><input type="hidden" name="acdc_origin" value="<?php echo esc_attr( is_admin() ? 'admin' : 'front' ); ?>"><input type="hidden" name="questionnaire_session_id" value="<?php echo $session ? (int) $session->id : 0; ?>"><?php if ( '' !== $prefill_source_type ) : ?><input type="hidden" name="redirect_source_type" value="<?php echo esc_attr( $prefill_source_type ); ?>"><?php endif; ?><?php if ( $prefill_source_id > 0 ) : ?><input type="hidden" name="redirect_source_id" value="<?php echo (int) $prefill_source_id; ?>"><?php endif; ?>
        <?php
        /* ACDC 3.25.157 — Cette phrase promettait un lien transmissible aux apprenants
           y compris pour les enquêtes, qui sont nominatives : elle contredisait le bloc
           « Accès apprenant » situé plus bas et menait l'utilisateur vers un refus. */
        $sess_source_type = $session ? (string) $session->source_type : (string) $prefill_source_type;
        $is_nominative    = '' !== $sess_source_type && $this->is_survey_questionnaire_source_type( $sess_source_type );
        ?>
        <div class="acdc-section-head"><div><h3>Informations de session</h3><p><?php echo esc_html( $is_nominative
          ? 'Cette enquête est nominative : les accès sont des liens personnels, générés et envoyés depuis « Envoyer les accès ».'
          : 'Le lien et le QR code de session sont générés automatiquement et peuvent être transmis aux apprenants.' ); ?></p></div></div>
        <?php if ( $editing_source ) : ?>
          <div class="acdc-grid-3cols acdc-mb-18">
            <div class="acdc-panel acdc-panel-block"><p><strong>Questionnaire source</strong><br><?php echo esc_html( $editing_source['title'] ); ?></p><small><?php echo esc_html( isset( $source_map[ $editing_source['type'] ] ) ? $source_map[ $editing_source['type'] ] : $editing_source['type'] ); ?></small></div>
            <div class="acdc-panel acdc-panel-block"><p><strong>Durée</strong><br><?php echo esc_html( $this->get_questionnaire_source_duration_label( $editing_source ) ); ?></p><small><?php echo esc_html( count( $editing_source['questions'] ) ); ?> question(s)</small></div>
            <div class="acdc-panel acdc-panel-block"><p><strong>Sessions existantes</strong><br><?php echo esc_html( $this->get_questionnaire_session_count_for_source( $editing_source['type'], $editing_source['id'] ) ); ?></p><small>réutilisations déjà créées</small></div>
          </div>
          <input type="hidden" name="questionnaire_session[source_ref]" value="<?php echo esc_attr( $editing_source['type'] . ':' . $editing_source['id'] ); ?>">
          <?php $this->render_questionnaire_source_dispatch_settings_panel( $editing_source ); ?>
        <?php elseif ( ! $session ) : ?>
          <p><label>Questionnaire source *</label><select name="questionnaire_session[source_ref]" required <?php echo 'view' === $action ? 'disabled' : ''; ?>><option value="">Choisir</option>
          <?php foreach ( $this->get_questionnaire_source_options() as $source_option ) : ?>
            <option value="<?php echo esc_attr( $source_option['type'] . ':' . (int) $source_option['id'] ); ?>"><?php echo esc_html( $source_option['label'] ); ?></option>
          <?php endforeach; ?></select></p>
        <?php endif; ?>
        <div class="acdc-grid-2cols acdc-mb-18">
          <p><label>Intitulé de la session *</label><input type="text" name="questionnaire_session[session_title]" required value="<?php echo esc_attr( $session ? $session->session_title : ( $editing_source ? $editing_source['title'] : '' ) ); ?>" <?php echo 'view' === $action ? 'readonly' : ''; ?>></p>
          <p><label>Formation liée</label><select name="questionnaire_session[formation_id]" <?php echo 'view' === $action ? 'disabled' : ''; ?>><option value="">Choisir</option><?php foreach ( $formations as $formation ) : ?><option value="<?php echo (int) $formation->id; ?>" <?php selected( $session ? (int) $session->formation_id : 0, (int) $formation->id ); ?>><?php echo esc_html( $this->format_formation_option_label( $formation ) ); ?></option><?php endforeach; ?></select></p>
          <p><label><?php echo esc_html( $is_annual_trainer_survey_source ? 'Formateur ciblé (optionnel)' : 'Formateur' ); ?></label><select name="questionnaire_session[formateur_id]" <?php echo 'view' === $action ? 'disabled' : ''; ?>><option value="">Choisir</option><?php foreach ( $trainers as $trainer ) : ?><option value="<?php echo (int) $trainer->id; ?>" <?php selected( $session ? (int) $session->formateur_id : 0, (int) $trainer->id ); ?>><?php echo esc_html( trim( $trainer->first_name . ' ' . $trainer->last_name ) ); ?></option><?php endforeach; ?></select></p>
          <?php if ( $is_company_survey_source ) : ?><p><label>Entreprise ciblée</label><select name="questionnaire_session[company_id]" <?php echo 'view' === $action ? 'disabled' : ''; ?>><option value="">Choisir</option><?php foreach ( $companies as $company ) : ?><option value="<?php echo (int) $company->id; ?>" <?php selected( $selected_company_id, (int) $company->id ); ?>><?php echo esc_html( $company->name ); ?></option><?php endforeach; ?></select></p><?php endif; ?>
          <?php if ( $is_company_survey_source ) : ?><p><label>Contact entreprise</label><select name="questionnaire_session[company_contact_id]" <?php echo 'view' === $action ? 'disabled' : ''; ?>><option value="">Choisir</option><?php foreach ( $company_contacts as $contact ) : ?><option value="<?php echo (int) $contact->id; ?>" <?php selected( $session ? (int) $session->company_contact_id : 0, (int) $contact->id ); ?>><?php echo esc_html( trim( $contact->first_name . ' ' . $contact->last_name ) ); ?></option><?php endforeach; ?></select></p><?php endif; ?>
          <?php if ( $is_funder_survey_source ) : ?><p><label><?php echo esc_html( $is_annual_funder_survey_source ? 'Financeur ciblé (optionnel)' : 'Financeur ciblé' ); ?></label><select name="questionnaire_session[funder_id]" <?php echo 'view' === $action ? 'disabled' : ''; ?>><option value="">Choisir</option><?php foreach ( $funders as $funder ) : ?><option value="<?php echo (int) $funder->id; ?>" <?php selected( $selected_funder_id, (int) $funder->id ); ?>><?php echo esc_html( ! empty( $funder->name ) ? $funder->name : 'Financeur #' . (int) $funder->id ); ?></option><?php endforeach; ?></select></p><?php endif; ?>
          <?php if ( $is_annual_trainer_survey_source || $is_annual_funder_survey_source ) : ?><p><label>Année de campagne</label><input type="number" min="2024" max="2100" name="questionnaire_session[annual_campaign_year]" value="<?php echo esc_attr( $session && ! empty( $session->annual_campaign_year ) ? (int) $session->annual_campaign_year : (int) gmdate( 'Y' ) ); ?>" <?php echo 'view' === $action ? 'readonly' : ''; ?>></p><?php endif; ?>
          <p><label>Date / heure</label><input type="datetime-local" name="questionnaire_session[session_date]" value="<?php echo esc_attr( $session && ! empty( $session->session_date ) ? str_replace( ' ', 'T', substr( $session->session_date, 0, 16 ) ) : '' ); ?>" <?php echo 'view' === $action ? 'readonly' : ''; ?>></p>
          <p><label>Envoi planifié</label><input type="datetime-local" name="questionnaire_session[scheduled_at]" value="<?php echo esc_attr( $session && ! empty( $session->scheduled_at ) ? str_replace( ' ', 'T', substr( $session->scheduled_at, 0, 16 ) ) : '' ); ?>" <?php echo 'view' === $action ? 'readonly' : ''; ?>></p>
          <p><label>Statut</label><select name="questionnaire_session[status]" <?php echo 'view' === $action ? 'disabled' : ''; ?>><?php foreach ( array( 'brouillon','planifiee','envoyee','prete','ouverte','en_cours','en_pause','terminee','expiree','annulee','archivee' ) as $status ) : ?><option value="<?php echo esc_attr( $status ); ?>" <?php selected( $session ? $session->status : $questionnaire_defaults['new_session_status_default'], $status ); ?>><?php echo esc_html( $this->questionnaire_session_status_label( $status ) ); ?></option><?php endforeach; ?></select></p>
          <p><label>Pseudo obligatoire</label><select name="questionnaire_session[pseudo_required]" <?php echo 'view' === $action ? 'disabled' : ''; ?>><option value="1" <?php selected( $session ? (int) $session->pseudo_required : (int) $questionnaire_defaults['pseudo_required_default'], 1 ); ?>>Oui</option><option value="0" <?php selected( $session ? (int) $session->pseudo_required : (int) $questionnaire_defaults['pseudo_required_default'], 0 ); ?>>Non</option></select></p>
          <p><label>Pseudo modifiable</label><select name="questionnaire_session[pseudo_editable]" <?php echo 'view' === $action ? 'disabled' : ''; ?>><option value="1" <?php selected( $session ? (int) $session->pseudo_editable : 1, 1 ); ?>>Oui</option><option value="0" <?php selected( $session ? (int) $session->pseudo_editable : 1, 0 ); ?>>Non</option></select></p>
          <p><label>Réserver aux apprenants inscrits</label><select name="questionnaire_session[restrict_to_registered_learners]" <?php echo 'view' === $action ? 'disabled' : ''; ?>><option value="1" <?php selected( $session ? (int) $session->restrict_to_registered_learners : (int) $questionnaire_defaults['restrict_to_registered_default'], 1 ); ?>>Oui</option><option value="0" <?php selected( $session ? (int) $session->restrict_to_registered_learners : (int) $questionnaire_defaults['restrict_to_registered_default'], 0 ); ?>>Non</option></select></p>
          <p><label>Afficher le score final</label><select name="questionnaire_session[show_final_score]" <?php echo 'view' === $action ? 'disabled' : ''; ?>><option value="1" <?php selected( $session ? (int) $session->show_final_score : (int) $questionnaire_defaults['show_final_score_default'], 1 ); ?>>Oui</option><option value="0" <?php selected( $session ? (int) $session->show_final_score : (int) $questionnaire_defaults['show_final_score_default'], 0 ); ?>>Non</option></select></p>
        </div>
        <?php if ( $editing_source && ( $is_annual_trainer_survey_source || $is_annual_funder_survey_source ) ) : $annual_preview_count = $session ? count( $delivery_targets ) : $this->get_questionnaire_annual_campaign_preview_count( $editing_source['type'], null ); $annual_year_value = $this->get_questionnaire_annual_campaign_year_value( $session, $editing_source ); ?>
          <div class="acdc-panel acdc-panel-block acdc-mb-18"><h4 style="margin-top:0;">Campagne annuelle</h4>
            <p><?php echo esc_html( $is_annual_trainer_survey_source ? 'Cette session diffuse une enquête annuelle auprès des formateurs.' : 'Cette session diffuse une enquête annuelle auprès des financeurs.' ); ?></p>
            <div class="acdc-grid-3cols">
              <div><strong>Année</strong><br><?php echo esc_html( (string) $annual_year_value ); ?></div>
              <div><strong>Cible par défaut</strong><br><?php echo esc_html( $is_annual_trainer_survey_source ? 'Tous les formateurs actifs avec e-mail' : 'Tous les financeurs avec e-mail' ); ?></div>
              <div><strong>Destinataires estimés</strong><br><?php echo esc_html( (string) $annual_preview_count ); ?></div>
            </div>
            <p class="acdc-mt-12 acdc-mb-0"><small>Vous pouvez laisser le champ de ciblage vide pour diffuser à toute la base annuelle, ou sélectionner un destinataire précis pour une campagne annuelle ciblée.</small></p>
          </div>
        <?php endif; ?>

        <?php if ( $session ) : $share_message = $this->build_questionnaire_session_share_message( $session, $editing_source ); $mailto_url = $this->build_questionnaire_session_mailto_url( $session, $editing_source ); $send_stats = $this->get_questionnaire_session_send_stats( $session ); $send_url = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_send_questionnaire_session_emails&questionnaire_session_id=' . (int) $session->id . '&source_type=' . rawurlencode( (string) $prefill_source_type ) . '&source_id=' . (int) $prefill_source_id ), 'acdc_send_questionnaire_session_emails_' . (int) $session->id ); ?>
          <?php /* ACDC 3.25.157 — Lien public et QR code masqués pour les enquêtes : le moteur exige un jeton personnel, ce lien ne mène qu'au mur « lien personnel et nominatif ». */ ?>
          <?php if ( ! $this->is_survey_questionnaire_source_type( (string) $session->source_type ) ) : ?>
          <div class="acdc-grid-2cols acdc-mb-18">
            <div class="acdc-panel acdc-panel-block"><p><strong>Lien public</strong><br><a href="<?php echo esc_url( $session->public_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $session->public_url ); ?></a></p><p class="acdc-mb-0 acdc-inline-wrap"><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $session->public_url ); ?>" target="_blank" rel="noopener">Ouvrir la page apprenant</a><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $session->public_url ); ?>" target="_blank" rel="noopener" download>Télécharger le lien</a></p></div>
            <div class="acdc-panel acdc-panel-block"><?php if ( ! empty( $session->qr_code_url ) ) : ?><p><strong>QR code</strong><br><img src="<?php echo esc_url( $session->qr_code_url ); ?>" alt="QR code" style="max-width:180px;height:auto;"></p><p class="acdc-mb-0"><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $session->qr_code_url ); ?>" target="_blank" rel="noopener">Ouvrir / télécharger le QR code</a></p><?php else : ?><p><strong>QR code</strong><br>Non généré.</p><?php endif; ?></div>
          </div>
          <?php else : ?>
          <div class="acdc-panel acdc-panel-block acdc-mb-18"><p><strong>Accès apprenant</strong><br>Cette enquête est nominative : chaque apprenant reçoit un lien personnel, généré par « Envoyer les accès ». Il n'existe pas de lien public ni de QR code utilisable pour y répondre.</p></div>
          <?php endif; ?>
          <div class="acdc-panel acdc-panel-block acdc-mb-18"><h4 style="margin-top:0;">Diffusion de la session</h4>
            <p><?php echo esc_html( $session ? $this->get_questionnaire_delivery_targets_count( $session ) : count( $targeted_learners ) ); ?> destinataire(s) ciblé(s) pour la diffusion. Utilisez le message ci-dessous tel quel ou envoyez directement les accès depuis le plugin.</p>
            <?php if ( ! empty( $send_stats['last_sent_at'] ) ) : ?><p><strong>Dernier envoi</strong><br><?php echo esc_html( mysql2date( 'j F Y à H\hi', $send_stats['last_sent_at'] ) ); ?> — <?php echo esc_html( (string) $send_stats['sent_count'] ); ?> envoyé(s), <?php echo esc_html( (string) $send_stats['failed_count'] ); ?> en échec.</p><?php endif; ?>
            <?php if ( in_array( $session->source_type, array( 'mid_survey', 'hot_survey', 'cold_survey' ), true ) && ! empty( $session->scheduled_at ) ) : ?><p><strong>Planification active</strong><br><?php echo esc_html( mysql2date( 'j F Y à H\hi', $session->scheduled_at ) ); ?><?php if ( 'planifiee' !== $session->status ) : ?> — <small>statut actuel : <?php echo esc_html( $this->questionnaire_session_status_label( (string) $session->status ) ); ?></small><?php endif; ?></p><?php endif; ?>
            <div class="acdc-inline-wrap acdc-mb-12"><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $send_url ); ?>" onclick="return confirm('Envoyer les accès aux destinataires ciblés ?');">Envoyer les accès</a><?php if ( $mailto_url ) : ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $mailto_url ); ?>">Préparer un e-mail</a><?php endif; ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $session->public_url ); ?>" target="_blank" rel="noopener">Tester le lien apprenant</a><?php if ( in_array( $session->source_type, array( 'mid_survey', 'hot_survey', 'cold_survey' ), true ) ) : ?><?php $bulk_reminder_url = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_send_questionnaire_session_bulk_reminders&questionnaire_session_id=' . (int) $session->id . '&source_type=' . rawurlencode( (string) $prefill_source_type ) . '&source_id=' . (int) $prefill_source_id ), 'acdc_send_questionnaire_session_bulk_reminders_' . (int) $session->id ); ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $bulk_reminder_url ); ?>" onclick="return confirm('Envoyer une relance à tous les destinataires non répondants ?');">Relancer les non-répondants</a><?php endif; ?></div>
            <p><label>Message prêt à copier</label><textarea rows="8" readonly><?php echo esc_textarea( $share_message ); ?></textarea></p>
          </div>
        <?php endif; ?>
        <?php if ( $session && in_array( $session->source_type, array( 'mid_survey', 'hot_survey', 'cold_survey' ), true ) ) : $survey_settings = $this->get_questionnaire_session_settings_array( $session ); $survey_reminder_days = ! empty( $survey_settings['reminder_days'] ) && is_array( $survey_settings['reminder_days'] ) ? implode( ', ', array_map( 'absint', $survey_settings['reminder_days'] ) ) : '5, 10, 15'; $survey_response_count = $this->get_questionnaire_session_response_count( $session->id ); $survey_pending_count = max( 0, count( $this->get_questionnaire_session_participants( $session->id ) ) - $survey_response_count ); $survey_label = 'mid_survey' === $session->source_type ? 'Planification et relances — enquêtes intermédiaires' : ( 'hot_survey' === $session->source_type ? 'Planification et relances — enquêtes à chaud' : 'Planification et relances — enquêtes à froid' ); $survey_trigger_label = 'mid_survey' === $session->source_type ? 'En cours de formation' : ( 'hot_survey' === $session->source_type ? 'Fin de formation' : 'J+30 après la formation' ); if ( 'cold_survey' === $session->source_type && ! empty( $survey_settings['survey_trigger_rule'] ) && 'custom_delay' === $survey_settings['survey_trigger_rule'] && ! empty( $survey_settings['survey_deadline_days'] ) ) { $survey_trigger_label = 'Après formation (J+' . absint( $survey_settings['survey_deadline_days'] ) . ')'; } ?>
          <div class="acdc-panel acdc-panel-block acdc-mb-18"><h4 style="margin-top:0;"><?php echo esc_html( $survey_label ); ?></h4><div class="acdc-grid-4cols"><div><strong>Déclenchement</strong><br><?php echo ! empty( $session->scheduled_at ) ? 'Planifié (' . esc_html( $survey_trigger_label ) . ')' : 'Manuel'; ?></div><div><strong>Date limite</strong><br><?php echo ! empty( $session->deadline_at ) ? esc_html( mysql2date( 'j F Y à H\hi', $session->deadline_at ) ) : '—'; ?></div><div><strong>Relances</strong><br><?php echo esc_html( $survey_reminder_days ); ?> jour(s)</div><div><strong>Non-répondants</strong><br><?php echo esc_html( (string) $survey_pending_count ); ?></div></div></div>
        <?php endif; ?>
        <?php if ( $editing_source ) : ?>
          <div class="acdc-panel acdc-panel-block acdc-mb-18"><h4 style="margin-top:0;">Questions du questionnaire</h4><div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>#</th><th>Intitulé</th><th>Type</th></tr></thead><tbody><?php foreach ( $editing_source['questions'] as $q_index => $question ) : ?><tr><td><?php echo esc_html( $q_index + 1 ); ?></td><td><?php echo esc_html( isset( $question['label'] ) ? $question['label'] : 'Question' ); ?></td><td><?php echo esc_html( isset( $question['type'] ) ? $question['type'] : '—' ); ?></td></tr><?php endforeach; if ( empty( $editing_source['questions'] ) ) : ?><tr><td colspan="3">Aucune question enregistrée sur ce questionnaire.</td></tr><?php endif; ?></tbody></table></div></div>
        <?php endif; ?>
        <?php if ( ! $is_trainer_survey_source && ! $is_company_survey_source && ! $is_funder_survey_source ) : ?><div class="acdc-panel acdc-panel-block acdc-mb-18"><h4 style="margin-top:0;">Apprenants ciblés</h4>
          <?php if ( ! empty( $registered_learners ) ) : ?>
            <input type="hidden" name="questionnaire_session[target_learner_ids_present]" value="1">
            <?php /* ACDC 3.25.157 — La consigne annonçait « sans sélection spécifique, tous
                     les apprenants listés seront considérés comme ciblés » sans dire QUI se
                     trouve dans cette liste : sur une session sans périmètre, elle contenait
                     de vrais apprenants. Le périmètre est désormais borné à la séance ou à la
                     formation liée (voir get_registered_learners_for_questionnaire_session),
                     et la consigne le nomme explicitement. */ ?>
            <p><?php echo esc_html( count( $registered_learners ) ); ?> apprenant(s) inscrit(s) à la formation liée à cette session. Cochez ceux réellement ciblés ; sans sélection spécifique, l’envoi portera sur les <?php echo esc_html( count( $registered_learners ) ); ?> apprenant(s) listé(s) ci-dessous. Aucun autre apprenant du site ne peut être atteint depuis cet écran.</p>
            <div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><?php if ( 'view' !== $action ) : ?><th>Cibler</th><?php endif; ?><th>Apprenant</th><th>Email</th><th>Téléphone</th></tr></thead><tbody><?php foreach ( $registered_learners as $learner ) : $is_checked = empty( $selected_learner_ids ) || in_array( (int) $learner->id, $selected_learner_ids, true ); ?><tr><?php if ( 'view' !== $action ) : ?><td><input type="checkbox" name="questionnaire_session[target_learner_ids][]" value="<?php echo (int) $learner->id; ?>" <?php checked( $is_checked ); ?>></td><?php endif; ?><td><?php echo esc_html( trim( $learner->first_name . ' ' . $learner->last_name ) ); ?></td><td><?php echo esc_html( ! empty( $learner->email ) ? $learner->email : '—' ); ?></td><td><?php echo esc_html( ! empty( $learner->phone ) ? $learner->phone : '—' ); ?></td></tr><?php endforeach; ?></tbody></table></div>
            <?php /* ACDC 3.25.157 — Ce compteur était figé au rendu : décocher un apprenant
                     ne le faisait pas bouger, et l'écran affichait donc un nombre de
                     destinataires faux au moment même où l'on ajuste le ciblage. */ ?>
            <p class="acdc-mt-8"><small><strong id="acdc-qs-target-count"><?php echo esc_html( count( $targeted_learners ) ); ?></strong> apprenant(s) ciblé(s) actuellement pour la diffusion.</small></p>
            <script>
            (function(){
              var box = document.getElementById('acdc-qs-target-count');
              if ( ! box ) { return; }
              var boxes = document.querySelectorAll('input[name="questionnaire_session[target_learner_ids][]"]');
              if ( ! boxes.length ) { return; }
              function refresh(){
                var n = 0;
                for ( var i = 0; i < boxes.length; i++ ) { if ( boxes[i].checked ) { n++; } }
                /* Aucune case cochée = pas de restriction : la diffusion porte sur toute
                   la liste. On affiche ce qui partira réellement, pas le nombre de coches. */
                box.textContent = ( 0 === n ) ? boxes.length : n;
              }
              for ( var i = 0; i < boxes.length; i++ ) { boxes[i].addEventListener('change', refresh); }
              refresh();
            })();
            </script>
          <?php else : ?>
            <p>Aucun apprenant inscrit n’a été repéré pour cette formation. Enregistrez d’abord la session avec une formation liée, puis revenez ici pour cibler les apprenants et préparer la diffusion.</p>
          <?php endif; ?>
        </div><?php else : ?><div class="acdc-panel acdc-panel-block acdc-mb-18"><h4 style="margin-top:0;">Destinataires ciblés</h4><p>Cette session utilisera les destinataires déduits du type d’enquête et des champs ci-dessus. Enregistrez la session puis utilisez le bouton d’envoi pour générer les liens personnels et envoyer les accès.</p></div><?php endif; ?>
        <?php if ( $session ) : $joined_participants = $this->get_questionnaire_session_participants( $session->id ); ?>
          <div class="acdc-panel acdc-panel-block acdc-mb-18"><h4 style="margin-top:0;">Participants déjà connectés</h4>
            <div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Pseudo</th><th>Apprenant</th><th>Statut</th><th>Score</th></tr></thead><tbody><?php foreach ( $joined_participants as $participant ) : $participant_name = trim( ( $participant->first_name ?? '' ) . ' ' . ( $participant->last_name ?? '' ) ); ?><tr><td><?php echo esc_html( $participant->pseudo ); ?></td><td><?php echo esc_html( $participant_name ? $participant_name : '—' ); ?></td><td><?php echo esc_html( $this->questionnaire_session_status_label( $participant->participant_status ) ); ?></td><td><?php echo esc_html( $this->get_questionnaire_session_participant_score( $session->id, $participant->id ) ); ?></td></tr><?php endforeach; if ( empty( $joined_participants ) ) : ?><tr><td colspan="4">Aucun participant connecté pour le moment.</td></tr><?php endif; ?></tbody></table></div>
          </div>
        <?php endif; ?>
        <p class="acdc-actions-end-wrap"><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( add_query_arg( array_filter( array( 'source_type' => $prefill_source_type, 'source_id' => $prefill_source_id ) ), $base_url ) ); ?>">Annuler</a><?php if ( 'view' !== $action ) : ?><button type="submit" class="acdc-button acdc-button-primary"><?php echo $session ? 'Modifier une session' : $create_label; ?></button><button type="submit" class="acdc-button acdc-button-soft" name="save_and_animate" value="1"><?php echo $session ? 'Modifier et animer' : 'Créer et animer'; ?></button><button type="submit" class="acdc-button acdc-button-soft" name="save_and_open_public" value="1"><?php echo $session ? 'Modifier et ouvrir la page apprenant' : 'Créer et ouvrir la page apprenant'; ?></button><?php endif; ?><?php if ( $session ) : $send_url_footer = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_send_questionnaire_session_emails&questionnaire_session_id=' . (int) $session->id . '&source_type=' . rawurlencode( (string) $prefill_source_type ) . '&source_id=' . (int) $prefill_source_id ), 'acdc_send_questionnaire_session_emails_' . (int) $session->id ); ?><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( add_query_arg( array_merge( array( 'action' => 'animate', 'item_id' => (int) $session->id ), array_filter( array( 'source_type' => $prefill_source_type, 'source_id' => $prefill_source_id ) ) ), $base_url ) ); ?>">Animer</a><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $session->public_url ); ?>" target="_blank" rel="noopener">Page publique</a><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $send_url_footer ); ?>" onclick="return confirm('Envoyer les accès aux destinataires ciblés ?');">Envoyer les accès</a><?php if ( ! empty( $mailto_url ) ) : ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $mailto_url ); ?>">Préparer un e-mail</a><?php endif; ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_duplicate_questionnaire_session&questionnaire_session_id=' . (int) $session->id . '&source_type=' . rawurlencode( (string) $prefill_source_type ) . '&source_id=' . (int) $prefill_source_id ), 'acdc_duplicate_questionnaire_session_' . (int) $session->id ) ); ?>">Dupliquer la session</a><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->get_questionnaire_results_url( $session->source_type, (int) $session->id ) ); ?>">Voir les résultats</a><?php endif; ?></p>
      </form></div>
      <?php endif; ?>
    <?php endif; ?>
    <div class="acdc-panel"><div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Questionnaire</th><th>Type</th><th>Formation</th><th>Formateur</th><th>Participants</th><th>Statut</th><th>Lien / QR</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ( $sessions as $entry ) : $source = $this->get_questionnaire_source_data( $entry->source_type, $entry->source_id ); $participants = $this->get_questionnaire_session_participants( $entry->id ); $formation_title = ''; if ( ! empty( $entry->formation_id ) ) { $formation = $this->get_formation( $entry->formation_id ); /* ACDC 3.25.278 — modalité comprise : deux fiches du même nom sinon indistinguables */ $formation_title = $formation ? $this->acdc_formation_choice_label( $formation ) : ''; } $trainer_name = $this->acdc_survey_session_trainer_name( $entry ); ?>
      <tr><td><strong><?php echo esc_html( $entry->session_title ); ?></strong><br><small><?php echo esc_html( $source ? $source['title'] : 'Questionnaire supprimé' ); ?></small></td><td><?php echo esc_html( isset( $source_map[ $entry->source_type ] ) ? $source_map[ $entry->source_type ] : $entry->source_type ); ?></td><td><?php echo esc_html( $formation_title ? $formation_title : '—' ); ?></td><td><?php echo esc_html( $trainer_name ? $trainer_name : '—' ); ?></td><td><?php
        /* ACDC 3.25.176 — Une session sans aucun destinataire expirait en silence, sur
           un écran qui affiche par ailleurs « Traçabilité Qualiopi : chaque envoi est
           horodaté ». Rien ne signalait que la preuve annoncée n'existait pas. */
        $p_count = count( $participants );
        echo esc_html( $p_count );
        if ( 0 === $p_count ) {
          echo '<br><small style="color:#b91c1c;font-weight:700;">⚠ aucun destinataire</small>';
        }
      ?></td><td><?php echo esc_html( $this->questionnaire_session_status_label( $entry->status ) ); ?></td><td><?php /* ACDC 3.25.157 — Aucun lien public pour une enquête nominative : il ne mène qu'au refus. */
      $row_nominative = $this->is_survey_questionnaire_source_type( (string) $entry->source_type );
      if ( $row_nominative ) : ?><small>Liens personnels</small><?php elseif ( ! empty( $entry->public_url ) ) : ?><a href="<?php echo esc_url( $entry->public_url ); ?>" target="_blank" rel="noopener">Ouvrir</a><?php if ( ! empty( $entry->qr_code_url ) ) : ?><br><a href="<?php echo esc_url( $entry->qr_code_url ); ?>" target="_blank" rel="noopener">Voir le QR</a><?php else : ?><br><small>Sans QR</small><?php endif; ?><?php else : ?>—<?php endif; ?></td><td><a href="<?php echo esc_url( add_query_arg( array_merge( array( 'action' => 'view', 'item_id' => (int) $entry->id ), array_filter( array( 'source_type' => $prefill_source_type, 'source_id' => $prefill_source_id ) ) ), $base_url ) ); ?>">Voir</a> | <a href="<?php echo esc_url( add_query_arg( array_merge( array( 'action' => 'edit', 'item_id' => (int) $entry->id ), array_filter( array( 'source_type' => $prefill_source_type, 'source_id' => $prefill_source_id ) ) ), $base_url ) ); ?>">Modifier</a> | <a href="<?php echo esc_url( add_query_arg( array_merge( array( 'action' => 'animate', 'item_id' => (int) $entry->id ), array_filter( array( 'source_type' => $prefill_source_type, 'source_id' => $prefill_source_id ) ) ), $base_url ) ); ?>">Animer</a> | <?php if ( ! $row_nominative && ! empty( $entry->public_url ) ) : ?><a href="<?php echo esc_url( $entry->public_url ); ?>" target="_blank" rel="noopener">Page apprenant</a> | <?php endif; ?><?php $list_source = $this->get_questionnaire_source_data( $entry->source_type, $entry->source_id ); $list_mailto = $this->build_questionnaire_session_mailto_url( $entry, $list_source ); $list_send = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_send_questionnaire_session_emails&questionnaire_session_id=' . (int) $entry->id . '&source_type=' . rawurlencode( (string) $prefill_source_type ) . '&source_id=' . (int) $prefill_source_id ), 'acdc_send_questionnaire_session_emails_' . (int) $entry->id ); ?><a href="<?php echo esc_url( $list_send ); ?>" onclick="return confirm('Envoyer les accès aux destinataires ciblés ?');">Envoyer les accès</a> | <?php if ( ! empty( $list_mailto ) ) : ?><a href="<?php echo esc_url( $list_mailto ); ?>">Préparer un e-mail</a> | <?php endif; ?><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_duplicate_questionnaire_session&questionnaire_session_id=' . (int) $entry->id . '&source_type=' . rawurlencode( (string) $prefill_source_type ) . '&source_id=' . (int) $prefill_source_id ), 'acdc_duplicate_questionnaire_session_' . (int) $entry->id ) ); ?>">Dupliquer</a> | <a href="<?php echo esc_url( $this->get_questionnaire_results_url( $entry->source_type, (int) $entry->id ) ); ?>">Résultats</a> | <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_delete_questionnaire_session&questionnaire_session_id=' . (int) $entry->id . '&page=acdc-of-questionnaire-sessions&source_type=' . rawurlencode( (string) $prefill_source_type ) . '&source_id=' . (int) $prefill_source_id ), 'acdc_delete_questionnaire_session_' . (int) $entry->id ) ); ?>" onclick="return confirm('Supprimer cette session ?');">Supprimer</a></td></tr>
    <?php endforeach; if ( empty( $sessions ) ) : ?><tr><td colspan="9">Aucune session <?php echo esc_html( strtolower( $context['label_plural'] ) ); ?>. Créez d’abord un questionnaire puis une session.</td></tr><?php endif; ?>
    </tbody></table></div></div>
    <?php
  }


  private function render_questionnaire_session_animation_admin( $session ) {
    $source = $this->get_questionnaire_source_data( $session->source_type, $session->source_id );
    $questions = $source ? $source['questions'] : array();
    $index = max( 0, (int) $session->current_question_index );
    $current = isset( $questions[ $index ] ) ? $questions[ $index ] : null;
    $participants = $this->get_questionnaire_session_participants( $session->id );
    $recent_answers = $this->get_questionnaire_session_recent_answers( $session->id, $index, 10 );
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-questionnaire-sessions' ) : $this->portal_page_url( array( 'tab' => 'questionnaire_sessions' ) );
    echo '<div class="acdc-panel acdc-mb-18"><div class="acdc-section-head"><div><h2>Animation — ' . esc_html( $session->session_title ) . '</h2><p>Le formateur pilote l’ouverture des questions et le passage à la suivante.</p></div></div>';
    echo '<div class="acdc-grid-2cols"><div>';
    echo '<p><strong>Statut</strong><br>' . esc_html( $this->questionnaire_session_status_label( $session->status ) ) . '</p>';
    /* ACDC 3.25.157 — Le lien public et le QR code ne fonctionnent QUE pour les
       sessions de questionnaire à connexion par pseudo. Pour une enquête, le moteur
       exige un jeton personnel : le lien renvoyait donc systématiquement le mur
       « Cette enquête utilise un lien personnel et nominatif », alors que l'écran
       invitait à le transmettre aux apprenants. On ne propose plus ce qui ne peut
       pas marcher, et on nomme le bon chemin. */
    if ( $this->is_survey_questionnaire_source_type( (string) $session->source_type ) ) {
      echo '<p><strong>Accès apprenant</strong><br>Cette enquête est nominative : chaque apprenant reçoit un lien personnel. Utilisez « Envoyer les accès » pour les générer et les transmettre — un lien public ou un QR code ne permettrait pas de répondre.</p>';
    } else {
      echo '<p><strong>Lien public</strong><br><a href="' . esc_url( $session->public_url ) . '" target="_blank" rel="noopener">' . esc_html( $session->public_url ) . '</a></p>';
      echo ! empty( $session->qr_code_url ) ? '<p><img src="' . esc_url( $session->qr_code_url ) . '" alt="QR code" style="max-width:180px;height:auto;"></p>' : '';
    }
    echo '</div><div><p><strong>Participants connectés</strong><br>' . esc_html( count( $participants ) ) . '</p><p><strong>Réponses sur la question en cours</strong><br>' . esc_html( $this->count_questionnaire_session_answers_for_index( $session->id, $index ) ) . '</p></div></div>';
    echo '<div class="acdc-panel acdc-panel-block"><h3>Question en cours</h3>';
    if ( $current ) {
      echo '<p><strong>' . esc_html( isset( $current['label'] ) ? $current['label'] : 'Question' ) . '</strong></p>';
      if ( ! empty( $current['type'] ) ) {
        echo '<p><small>Type : ' . esc_html( $current['type'] ) . '</small></p>';
      }
      echo '<p>' . nl2br( esc_html( isset( $current['options'] ) ? $current['options'] : '' ) ) . '</p>';
      $choices = $this->parse_question_choices( $current );
      if ( ! empty( $choices ) ) {
        echo '<ul>';
        foreach ( $choices as $choice ) {
          echo '<li>' . esc_html( $choice['label'] ) . ( ! empty( $choice['is_correct'] ) ? ' — réponse juste' : '' ) . '</li>';
        }
        echo '</ul>';
      }
    } else {
      echo '<p>Aucune question disponible.</p>';
    }
    echo '</div>';
    echo '<div class="acdc-actions-end-wrap">';
    $actions = array(
      'acdc_start_questionnaire_session' => 'Démarrer',
      'acdc_pause_questionnaire_session' => 'Pause',
      'acdc_previous_questionnaire_session_question' => 'Question précédente',
      'acdc_next_questionnaire_session_question' => 'Question suivante',
      'acdc_finish_questionnaire_session' => 'Terminer',
    );
    foreach ( $actions as $action => $label ) {
      echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px;">';
      wp_nonce_field( $action . '_' . (int) $session->id );
      echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '"><input type="hidden" name="questionnaire_session_id" value="' . (int) $session->id . '"><input type="hidden" name="page" value="acdc-of-questionnaire-sessions"><input type="hidden" name="source_type" value="' . esc_attr( $session->source_type ) . '"><input type="hidden" name="source_id" value="' . (int) $session->source_id . '">';
      echo '<button type="submit" class="acdc-button acdc-button-primary">' . esc_html( $label ) . '</button></form>';
    }
    echo '<a class="acdc-button acdc-button-soft" href="' . esc_url( $base_url ) . '">Retour</a></div>';
    if ( ! empty( $recent_answers ) ) {
      echo '<div class="acdc-panel acdc-panel-block"><h3>Dernières réponses reçues</h3><table class="acdc-table"><thead><tr><th>Pseudo</th><th>Réponse</th><th>Score</th><th>Heure</th></tr></thead><tbody>';
      foreach ( $recent_answers as $answer ) {
        $answer_display = ! empty( $answer->answer_value ) ? $answer->answer_value : 'Réponse ouverte';
        echo '<tr><td>' . esc_html( $answer->pseudo ) . '</td><td>' . esc_html( $answer_display ) . '</td><td>' . esc_html( (int) $answer->points_awarded ) . '</td><td>' . esc_html( ! empty( $answer->answered_at ) ? mysql2date( 'H:i:s', $answer->answered_at ) : '—' ) . '</td></tr>';
      }
      echo '</tbody></table></div>';
    }
    if ( ! empty( $participants ) ) {
      echo '<div class="acdc-panel acdc-panel-block"><h3>Participants</h3><table class="acdc-table"><thead><tr><th>Pseudo</th><th>Nom</th><th>Statut</th><th>Score</th></tr></thead><tbody>';
      foreach ( $participants as $participant ) {
        $name = trim( ( isset( $participant->first_name ) ? $participant->first_name : '' ) . ' ' . ( isset( $participant->last_name ) ? $participant->last_name : '' ) );
        echo '<tr><td>' . esc_html( $participant->pseudo ) . '</td><td>' . esc_html( $name ? $name : '—' ) . '</td><td>' . esc_html( $participant->participant_status ) . '</td><td>' . esc_html( $this->get_questionnaire_session_participant_score( $session->id, $participant->id ) ) . '</td></tr>';
      }
      echo '</tbody></table></div>';
    }
    echo '</div>';
  }


  private function render_dashboard_questionnaire_action_followups() {
    $rows  = $this->get_questionnaire_action_followup_rows( 3, 12 );
    $stats = $this->get_questionnaire_action_followup_stats( $rows );
    ?>
    <div class="acdc-panel acdc-panel-large" style="margin-top:18px;">
      <div class="acdc-panel-heading">
        <div>
          <h3>Relances internes — actions d’amélioration</h3>
          <p style="margin:6px 0 0;color:var(--acdc-text-muted);">Retards à résorber ou proches d’échéance à piloter depuis les résultats des enquêtes.</p>
        </div>
        <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $this->portal_page_url( array( 'tab' => 'questionnaire_results' ) ) ); ?>">Voir les résultats →</a>
      </div>

      <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:14px;">
        <div class="acdc-kpi-card"><strong>Total à suivre</strong><div style="font-size:28px;font-weight:700"><?php echo esc_html( (string) $stats['total'] ); ?></div></div>
        <div class="acdc-kpi-card"><strong>En retard</strong><div style="font-size:28px;font-weight:700"><?php echo esc_html( (string) $stats['en_retard'] ); ?></div></div>
        <div class="acdc-kpi-card"><strong>À traiter aujourd’hui</strong><div style="font-size:28px;font-weight:700"><?php echo esc_html( (string) $stats['aujourd_hui'] ); ?></div></div>
        <div class="acdc-kpi-card"><strong>À venir sous 3 jours</strong><div style="font-size:28px;font-weight:700"><?php echo esc_html( (string) $stats['a_venir'] ); ?></div></div>
      </div>

      <?php if ( empty( $rows ) ) : ?>
        <div class="acdc-empty-state">Aucune relance interne à signaler pour les actions d’amélioration.</div>
      <?php else : ?>
        <div class="acdc-table-wrap">
          <table class="acdc-table">
            <thead>
              <tr>
                <th>État</th>
                <th>Action</th>
                <th>Contexte</th>
                <th>Responsable</th>
                <th>Échéance</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( $rows as $row ) :
                $badge = $this->get_questionnaire_action_followup_badge( $row );
                $source_title = ! empty( $row->source['title'] ) ? (string) $row->source['title'] : ( ! empty( $row->session_title ) ? (string) $row->session_title : 'Questionnaire' );
                $context_bits = array();
                $context_bits[] = $source_title;
                if ( ! empty( $row->participant_label ) && '—' !== $row->participant_label ) {
                  $context_bits[] = 'Destinataire : ' . $row->participant_label;
                }
                if ( ! empty( $row->session_title ) ) {
                  $context_bits[] = 'Session : ' . (string) $row->session_title;
                }
                ?>
                <tr>
                  <td><span style="<?php echo esc_attr( $badge['style'] ); ?>"><?php echo esc_html( $badge['label'] ); ?></span></td>
                  <td><strong><?php echo esc_html( $row->action_label ); ?></strong><br><small><?php echo esc_html( $this->get_questionnaire_action_type_label( ! empty( $row->action_type ) ? (string) $row->action_type : 'improvement' ) ); ?> · <?php echo esc_html( $this->get_questionnaire_action_status_label( ! empty( $row->action_status ) ? (string) $row->action_status : 'ouverte' ) ); ?></small></td>
                  <td><?php echo esc_html( implode( ' · ', array_filter( $context_bits ) ) ); ?></td>
                  <td><?php echo esc_html( $this->get_questionnaire_action_assignee_label( ! empty( $row->assigned_to ) ? (int) $row->assigned_to : 0 ) ); ?></td>
                  <td><?php echo esc_html( ! empty( $row->due_state['label'] ) ? (string) $row->due_state['label'] : '—' ); ?></td>
                  <td><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $row->followup_url ); ?>">Ouvrir</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <?php
  }


private function render_questionnaire_actions_panel( $session_id, $participant_id = 0 ) {
  $session_id = absint( $session_id );
  $participant_id = absint( $participant_id );
  if ( ! $session_id ) {
    return;
  }
  $actions = $this->get_questionnaire_actions_rows( $session_id, $participant_id );
  $stats = $this->get_questionnaire_action_stats( $actions );
  $status_map = $this->get_questionnaire_action_statuses_map();
  $assignees = $this->get_questionnaire_action_assignees();
  $current_source_type = isset( $_GET['source_type'] ) ? sanitize_text_field( wp_unslash( $_GET['source_type'] ) ) : '';
  $current_source_id = isset( $_GET['source_id'] ) ? absint( wp_unslash( $_GET['source_id'] ) ) : 0;
  $prefill = $this->get_questionnaire_action_prefill_from_request();
  $suggestions = $this->get_questionnaire_action_suggestions( $session_id, $participant_id );
  ?>
  <div class="acdc-panel acdc-mt-18" id="acdc-questionnaire-actions-panel">
    <div class="acdc-section-head">
      <div>
        <h4 style="margin:0;">Actions d’amélioration continue</h4>
        <p style="margin:6px 0 0;">Tracer, assigner, faire avancer et clôturer les actions issues des retours d’enquêtes.</p>
      </div>
    </div>
    <div class="acdc-grid-6cols acdc-mb-18">
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $stats['total'] ); ?></div><div class="acdc-stat-label">Total</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $stats['ouverte'] ); ?></div><div class="acdc-stat-label">Ouvertes</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $stats['en_cours'] ); ?></div><div class="acdc-stat-label">En cours</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $stats['traitee'] ); ?></div><div class="acdc-stat-label">Traitées</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $stats['annulee'] ); ?></div><div class="acdc-stat-label">Annulées</div></div>
      <div class="acdc-panel acdc-centered-stat acdc-urgency-stat acdc-urgency-stat-overdue"><div class="acdc-stat-number"><?php echo esc_html( $stats['en_retard'] ); ?></div><div class="acdc-stat-label">En retard</div></div>
    </div>
    <div class="acdc-grid-3cols acdc-mb-18">
      <div class="acdc-panel acdc-centered-stat acdc-priority-stat acdc-priority-stat-haute"><div class="acdc-stat-number"><?php echo esc_html( $stats['priority_high'] ); ?></div><div class="acdc-stat-label">Priorité haute</div></div>
      <div class="acdc-panel acdc-centered-stat acdc-priority-stat acdc-priority-stat-moyenne"><div class="acdc-stat-number"><?php echo esc_html( $stats['priority_medium'] ); ?></div><div class="acdc-stat-label">Priorité moyenne</div></div>
      <div class="acdc-panel acdc-centered-stat acdc-priority-stat acdc-priority-stat-basse"><div class="acdc-stat-number"><?php echo esc_html( $stats['priority_low'] ); ?></div><div class="acdc-stat-label">Priorité basse</div></div>
    </div>
    <div class="acdc-grid-3cols acdc-mb-18">
      <div class="acdc-panel acdc-centered-stat acdc-urgency-stat acdc-urgency-stat-overdue"><div class="acdc-stat-number"><?php echo esc_html( $stats['en_retard'] ); ?></div><div class="acdc-stat-label">En retard</div></div>
      <div class="acdc-panel acdc-centered-stat acdc-urgency-stat acdc-urgency-stat-today"><div class="acdc-stat-number"><?php echo esc_html( $stats['aujourd_hui'] ); ?></div><div class="acdc-stat-label">Échéance aujourd’hui</div></div>
      <div class="acdc-panel acdc-centered-stat acdc-urgency-stat acdc-urgency-stat-soon"><div class="acdc-stat-number"><?php echo esc_html( $stats['echeance_proche'] ); ?></div><div class="acdc-stat-label">Échéance proche</div></div>
    </div>
    <?php if ( ! empty( $suggestions ) ) : ?>
      <div class="acdc-panel acdc-mb-18">
        <h5 style="margin:0 0 10px;">Propositions d’actions préremplies</h5>
        <p style="margin:0 0 12px;">Ces suggestions sont construites à partir des alertes détectées pour accélérer le traitement opérationnel.</p>
        <div class="acdc-inline-wrap">
          <?php foreach ( $suggestions as $suggestion ) : ?>
            <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->build_questionnaire_action_suggestion_url( $session_id, $participant_id, $current_source_type, $current_source_id, $suggestion ) ); ?>"><?php echo esc_html( $suggestion['label'] ); ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acdc-form acdc-mb-18">
      <?php wp_nonce_field( 'acdc_create_questionnaire_action' ); ?>
      <input type="hidden" name="action" value="acdc_create_questionnaire_action">
      <input type="hidden" name="questionnaire_session_id" value="<?php echo esc_attr( $session_id ); ?>">
      <input type="hidden" name="return_source_type" value="<?php echo esc_attr( $current_source_type ); ?>">
      <input type="hidden" name="return_source_id" value="<?php echo esc_attr( $current_source_id ); ?>">
      <?php if ( $participant_id ) : ?><input type="hidden" name="participant_id" value="<?php echo esc_attr( $participant_id ); ?>"><?php endif; ?>
      <div class="acdc-grid-5cols">
        <p><label>Type d’action</label>
          <select name="action_type">
            <option value="improvement" <?php selected( 'improvement', $prefill['action_type'] ); ?>>Amélioration</option>
            <option value="alert" <?php selected( 'alert', $prefill['action_type'] ); ?>>Alerte</option>
            <option value="complaint" <?php selected( 'complaint', $prefill['action_type'] ); ?>>Réclamation</option>
            <option value="followup" <?php selected( 'followup', $prefill['action_type'] ); ?>>Suivi</option>
          </select>
        </p>
        <p><label>Statut initial</label>
          <select name="action_status">
            <option value="ouverte" <?php selected( 'ouverte', $prefill['action_status'] ); ?>>Ouverte</option>
            <option value="en_cours" <?php selected( 'en_cours', $prefill['action_status'] ); ?>>En cours</option>
          </select>
        </p>
        <p><label>Responsable</label>
          <select name="assigned_to">
            <option value="0">Non assigné</option>
            <?php foreach ( $assignees as $assignee ) : ?>
              <option value="<?php echo esc_attr( $assignee->ID ); ?>" <?php selected( (int) $prefill['assigned_to'], (int) $assignee->ID ); ?>><?php echo esc_html( $assignee->display_name ); ?></option>
            <?php endforeach; ?>
          </select>
        </p>
        <p><label>Priorité</label>
          <select name="priority_level">
            <?php foreach ( $this->get_questionnaire_action_priority_levels() as $priority_key => $priority_label ) : ?>
              <option value="<?php echo esc_attr( $priority_label ); ?>" <?php selected( $prefill['priority_level'], $priority_label ); ?>><?php echo esc_html( $priority_label ); ?></option>
            <?php endforeach; ?>
          </select>
        </p>
        <p><label>Contexte</label><input type="text" value="<?php echo esc_attr( $participant_id ? 'Action individuelle' : 'Action session' ); ?>" readonly></p>
      </div>
      <div class="acdc-grid-3cols">
        <p><label>Délai cible (jours)</label><input type="number" min="1" name="target_due_days" value="<?php echo esc_attr( (string) $prefill['target_due_days'] ); ?>"></p>
        <p><label>Échéance automatique</label><input type="datetime-local" name="due_at" value="<?php echo esc_attr( $this->format_questionnaire_action_due_at_for_input( $prefill['due_at'] ) ); ?>"></p>
        <p><label>Lecture</label><input type="text" value="<?php echo esc_attr( 'Laisser vide pour calcul automatique à partir du délai cible.' ); ?>" readonly></p>
      </div>
      <p><label>Intitulé</label><input type="text" name="action_label" required placeholder="Ex. Revoir le rythme, traiter une réserve, rappeler le financeur…" value="<?php echo esc_attr( $prefill['action_label'] ); ?>"></p>
      <p><label>Notes de suivi</label><textarea name="notes" rows="3" placeholder="Contexte, cause, action à mener, échéance, résultat attendu…"><?php echo esc_textarea( $prefill['notes'] ); ?></textarea></p>
      <p class="acdc-actions-end"><button type="submit" class="acdc-button acdc-button-primary">Créer l’action</button></p>
    </form>
    <div class="acdc-table-wrap">
      <table class="acdc-table">
        <thead><tr><th>Type</th><th>Intitulé</th><th>Priorité</th><th>Statut</th><th>Responsable</th><th>Cible</th><th>Signal</th><th>Échéance</th><th>Ouverte le</th><th>Clôturée le</th><th>Notes</th><th>Suivi</th></tr></thead>
        <tbody>
        <?php foreach ( (array) $actions as $entry ) : $due_state = $this->get_questionnaire_action_due_state( $entry ); $priority_badge = $this->get_questionnaire_action_priority_badge( ! empty( $entry->priority_level ) ? (string) $entry->priority_level : 'Moyenne' ); $row_classes = array( 'acdc-action-row', 'acdc-action-row-priority-' . $priority_badge['key'] ); if ( ! empty( $due_state['state_key'] ) ) { $row_classes[] = 'acdc-action-row-' . sanitize_html_class( $due_state['state_key'] ); } ?>
          <tr class="<?php echo esc_attr( implode( ' ', array_filter( $row_classes ) ) ); ?>">
            <td><?php echo esc_html( $this->get_questionnaire_action_type_label( ! empty( $entry->action_type ) ? $entry->action_type : 'improvement' ) ); ?></td>
            <td><strong><?php echo esc_html( ! empty( $entry->action_label ) ? $entry->action_label : '—' ); ?></strong></td>
            <td><span class="<?php echo esc_attr( $priority_badge['class'] ); ?>"><?php echo esc_html( $priority_badge['label'] ); ?></span></td>
            <td><?php echo esc_html( $this->get_questionnaire_action_status_label( ! empty( $entry->action_status ) ? $entry->action_status : 'ouverte' ) ); ?></td>
            <td><?php echo esc_html( $this->get_questionnaire_action_assignee_label( ! empty( $entry->assigned_to ) ? (int) $entry->assigned_to : 0 ) ); ?></td>
            <td><?php echo ! empty( $entry->target_due_days ) ? esc_html( 'J+' . (int) $entry->target_due_days ) : '—'; ?></td>
            <td><span class="<?php echo esc_attr( $due_state['class'] ); ?>"><?php echo esc_html( $due_state['badge_label'] ); ?></span></td>
            <td><?php echo esc_html( $due_state['label'] ); ?></td>
            <td><?php echo esc_html( ! empty( $entry->opened_at ) ? mysql2date( 'j F Y H:i', $entry->opened_at ) : '—' ); ?></td>
            <td><?php echo esc_html( ! empty( $entry->closed_at ) ? mysql2date( 'j F Y H:i', $entry->closed_at ) : '—' ); ?></td>
            <td><?php echo esc_html( ! empty( $entry->notes ) ? wp_trim_words( wp_strip_all_tags( (string) $entry->notes ), 18, '…' ) : '—' ); ?></td>
            <td>
              <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acdc-inline-form">
                <?php wp_nonce_field( 'acdc_update_questionnaire_action_' . (int) $entry->id ); ?>
                <input type="hidden" name="action" value="acdc_update_questionnaire_action">
                <input type="hidden" name="action_id" value="<?php echo esc_attr( (int) $entry->id ); ?>">
                <input type="hidden" name="questionnaire_session_id" value="<?php echo esc_attr( $session_id ); ?>">
                <input type="hidden" name="participant_id" value="<?php echo esc_attr( $participant_id ); ?>">
                <input type="hidden" name="return_source_type" value="<?php echo esc_attr( $current_source_type ); ?>">
                <input type="hidden" name="return_source_id" value="<?php echo esc_attr( $current_source_id ); ?>">
                <div class="acdc-inline-wrap">
                  <select name="action_status">
                    <?php foreach ( $status_map as $status_key => $status_label ) : ?>
                      <option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $status_key, ! empty( $entry->action_status ) ? $entry->action_status : 'ouverte' ); ?>><?php echo esc_html( $status_label ); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <select name="assigned_to">
                    <option value="0">Non assigné</option>
                    <?php foreach ( $assignees as $assignee ) : ?>
                      <option value="<?php echo esc_attr( $assignee->ID ); ?>" <?php selected( (int) $assignee->ID, ! empty( $entry->assigned_to ) ? (int) $entry->assigned_to : 0 ); ?>><?php echo esc_html( $assignee->display_name ); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <select name="priority_level">
                    <?php foreach ( $this->get_questionnaire_action_priority_levels() as $priority_key => $priority_label ) : ?>
                      <option value="<?php echo esc_attr( $priority_label ); ?>" <?php selected( $this->normalize_questionnaire_action_priority_level( ! empty( $entry->priority_level ) ? (string) $entry->priority_level : 'Moyenne' ), $priority_label ); ?>><?php echo esc_html( $priority_label ); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <input type="number" min="1" name="target_due_days" value="<?php echo esc_attr( ! empty( $entry->target_due_days ) ? (int) $entry->target_due_days : '' ); ?>" placeholder="J+">
                  <input type="datetime-local" name="due_at" value="<?php echo esc_attr( $this->format_questionnaire_action_due_at_for_input( ! empty( $entry->due_at ) ? (string) $entry->due_at : '' ) ); ?>">
                  <button type="submit" class="acdc-button acdc-button-soft">Modifier</button>
                </div>
                <textarea name="notes" rows="2" placeholder="Compléter le suivi, le résultat, la décision…"><?php echo esc_textarea( ! empty( $entry->notes ) ? (string) $entry->notes : '' ); ?></textarea>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ( empty( $actions ) ) : ?><tr><td colspan="12">Aucune action enregistrée pour ce contexte.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php
}






  private function get_quality_compliance_active_section() {
    $section = isset( $_GET['qc_section'] ) ? sanitize_key( wp_unslash( $_GET['qc_section'] ) ) : 'overview';
    $allowed = array( 'overview', 'indicators', 'gaps', 'plan', 'journal' );
    return in_array( $section, $allowed, true ) ? $section : 'overview';
  }

  private function get_quality_compliance_section_label_map() {
    return array(
      'overview'   => '1. Vue globale',
      'indicators' => '2. Indicateurs',
      'gaps'       => '3. Écarts',
      'plan'       => '4. Plan d’action',
      'journal'    => '5. Journal',
    );
  }

  private function get_quality_compliance_section_url( $section ) {
    $section = sanitize_key( (string) $section );
    $args = array( 'qc_section' => $section );
    if ( is_admin() ) {
      return add_query_arg( $args, admin_url( 'admin.php?page=acdc-of-improvement-pilotage' ) );
    }
    if ( method_exists( $this, 'quality_compliance_page_url' ) ) {
      return $this->quality_compliance_page_url( array_merge( array( 'tab' => 'improvement_pilotage' ), $args ) );
    }
    return $this->portal_page_url( array_merge( array( 'tab' => 'improvement_pilotage' ), $args ) );
  }


  private function get_quality_compliance_badge_data( $value ) {
    $raw = is_scalar( $value ) ? (string) $value : '';
    $normalized = trim( str_replace( array( '_', '-' ), ' ', $raw ) );
    $label = '' !== $normalized ? preg_replace_callback( '/\b([[:alpha:]][[:alpha:]\x{00C0}-\x{017F}]*)/u', function( $m ) {
      $word = (string) $m[1];
      if ( function_exists( 'mb_strtolower' ) && function_exists( 'mb_strtoupper' ) && function_exists( 'mb_substr' ) ) {
        return mb_strtoupper( mb_substr( $word, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_strtolower( mb_substr( $word, 1, null, 'UTF-8' ), 'UTF-8' );
      }
      return ucfirst( strtolower( $word ) );
    }, $normalized ) : '—';
    $lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $label, 'UTF-8' ) : strtolower( $label );

    $replacements = array(
      'a traiter' => 'À traiter',
      'a surveiller' => 'À surveiller',
      'a venir' => 'À venir',
      'cloture' => 'Clôture',
      'reouverture' => 'Réouverture',
      'echeance' => 'Échéance',
      'tres haute priorite' => 'Très haute priorité',
      'haute priorite' => 'Haute priorité',
      'prise en charge' => 'Prise en charge',
      'en cours' => 'En cours',
      'preuve manquante' => 'Preuve manquante',
      'preuve partielle' => 'Preuve partielle',
      'preuve consolidee' => 'Preuve consolidée',
      'non assigne' => 'Non assigné',
      'maitrise' => 'Maîtrisé',
      'maitrisee' => 'Maîtrisée',
      'retards a resorber' => 'Retards à résorber',
      'clotures a arbitrer' => 'Clôtures à arbitrer',
      'preuves a securiser' => 'Preuves à sécuriser',
      'urgences a arbitrer' => 'Urgences à arbitrer',
    );
    foreach ( $replacements as $from => $to ) {
      if ( $lower === $from ) {
        $label = $to;
        $lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $label, 'UTF-8' ) : strtolower( $label );
        break;
      }
    }

    $variant = 'quality-neutral';
    if ( false !== strpos( $lower, 'crit' ) || false !== strpos( $lower, 'urgence' ) || false !== strpos( $lower, 'retard' ) || false !== strpos( $lower, 'manquant' ) || false !== strpos( $lower, 'bloqu' ) || false !== strpos( $lower, 'très haute priorité' ) || false !== strpos( $lower, 'haute priorité' ) ) {
      $variant = 'quality-critical';
    } elseif ( false !== strpos( $lower, 'à traiter' ) || false !== strpos( $lower, 'à surveiller' ) || false !== strpos( $lower, 'partielle' ) || false !== strpos( $lower, 'ouverte' ) || false !== strpos( $lower, 'en cours' ) || false !== strpos( $lower, 'échéance' ) || false !== strpos( $lower, 'preuve' ) || false !== strpos( $lower, 'clôture' ) || false !== strpos( $lower, 'réouverture' ) || false !== strpos( $lower, 'arbitr' ) ) {
      $variant = 'quality-warning';
    } elseif ( false !== strpos( $lower, 'ok' ) || false !== strpos( $lower, 'clos' ) || false !== strpos( $lower, 'valid' ) || false !== strpos( $lower, 'résolu' ) || false !== strpos( $lower, 'resolu' ) || false !== strpos( $lower, 'compl' ) || false !== strpos( $lower, 'trait' ) || false !== strpos( $lower, 'maîtris' ) || false !== strpos( $lower, 'sous contrôle' ) || false !== strpos( $lower, 'sécuris' ) ) {
      $variant = 'quality-success';
    } elseif ( false !== strpos( $lower, 'à venir' ) || false !== strpos( $lower, 'suivi' ) || false !== strpos( $lower, 'info' ) || false !== strpos( $lower, 'lecture seule' ) ) {
      $variant = 'quality-info';
    }

    return array(
      'label' => $label,
      'class' => 'acdc-badge ' . $variant,
      'variant' => $variant,
    );
  }

  private function render_quality_compliance_badge( $value ) {
    $badge = $this->get_quality_compliance_badge_data( $value );
    return '<span class="' . esc_attr( $badge['class'] ) . '">' . esc_html( $badge['label'] ) . '</span>';
  }

  private function get_quality_compliance_variant_slug( $value ) {
    $badge = $this->get_quality_compliance_badge_data( $value );
    $variant = str_replace( 'quality-', '', (string) $badge['variant'] );
    if ( '' === $variant ) {
      $variant = 'neutral';
    }
    return sanitize_html_class( $variant );
  }

  private function get_quality_compliance_variant_class( $value, $prefix ) {
    $prefix = trim( (string) $prefix );
    if ( '' === $prefix ) {
      return '';
    }
    return sanitize_html_class( $prefix . '--' . $this->get_quality_compliance_variant_slug( $value ) );
  }

  private function get_quality_compliance_status_badge_class( $value ) {
    $badge = $this->get_quality_compliance_badge_data( $value );
    return $badge['class'];
  }

  private function get_quality_compliance_progress_percent( $status ) {
    $status = strtolower( trim( (string) $status ) );
    if ( false !== strpos( $status, 'clos' ) || false !== strpos( $status, 'termin' ) || false !== strpos( $status, 'réalis' ) || false !== strpos( $status, 'realis' ) || false !== strpos( $status, 'valid' ) ) {
      return 100;
    }
    if ( false !== strpos( $status, 'en cours' ) || false !== strpos( $status, 'ouvert' ) || false !== strpos( $status, 'ouverte' ) || false !== strpos( $status, 'trait' ) ) {
      return 55;
    }
    return 15;
  }

  private function render_quality_compliance_module_header( $context = 'admin' ) {
    $context = 'front' === $context ? 'front' : 'admin';
    if ( ! $this->current_user_can_quality_permission( 'view' ) ) {
      echo '<section class="acdc-panel acdc-mb-18" style="padding:18px;"><p>Accès réservé aux profils autorisés au module qualité.</p></section>';
      return;
    }
    $active_section = $this->get_quality_compliance_active_section();
    $sections = $this->get_quality_compliance_section_label_map();
    $front_url = method_exists( $this, 'quality_compliance_page_url' ) ? $this->quality_compliance_page_url( array( 'tab' => 'improvement_pilotage' ) ) : $this->portal_page_url( array( 'tab' => 'improvement_pilotage' ) );
    $back_url  = admin_url( 'admin.php?page=acdc-of-improvement-pilotage' );
    $export_filters = $this->get_improvement_pilotage_filters_from_request();
    $can_export = $this->current_user_can_quality_permission( 'export' );
    $can_manage = $this->current_user_can_quality_permission( 'manage' );
    ?>
    <section class="acdc-panel acdc-mb-18" style="padding:18px 18px 14px;">
      <div class="acdc-section-head" style="align-items:flex-start;gap:18px;">
        <div>
          <h2 style="margin-bottom:6px;">Qualité & conformité</h2>
          <p style="max-width:920px;">Pilotage des indicateurs, alertes, écarts, plan d’action et journal d’audit dans une interface structurée, lisible et exploitable en audit.</p>
        </div>
        <div class="acdc-inline-wrap" style="justify-content:flex-end;gap:10px;">
          <?php if ( 'admin' === $context ) : ?>
            <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $front_url ); ?>">Ouvrir le front office</a>
          <?php else : ?>
            <span class="acdc-badge success">Front sécurisé</span><span class="acdc-badge">Chaîne enquêtes → alertes → écarts → actions</span>
          <?php endif; ?>
          <?php if ( $can_export ) : ?>
            <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->get_improvement_pilotage_export_url( 'csv', $export_filters ) ); ?>">Exporter CSV</a>
            <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->get_improvement_pilotage_export_url( 'excel', $export_filters ) ); ?>">Exporter Excel</a>
          <?php endif; ?>
          <?php if ( $can_manage ) : ?>
            <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->get_quality_management_url( 'new_gap' ) ); ?>">Créer un écart</a>
            <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $this->get_quality_management_url( 'new_action' ) ); ?>">Créer une action qualité</a>
          <?php endif; ?>
        </div>
      </div>
    </section>
    <section class="acdc-panel acdc-mb-18" style="padding:14px 12px;">
      <div class="acdc-inline-wrap" style="gap:10px;flex-wrap:wrap;">
        <?php foreach ( $sections as $section_key => $section_label ) : ?>
          <?php $button_class = $section_key === $active_section ? 'acdc-button acdc-button-primary' : 'acdc-button acdc-button-soft'; ?>
          <a class="<?php echo esc_attr( $button_class ); ?>" href="<?php echo esc_url( $this->get_quality_compliance_section_url( $section_key ) ); ?>"><?php echo esc_html( $section_label ); ?></a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php
  }


  private function render_quality_compliance_foundations_dashboard() {
    if ( ! $this->current_user_can_quality_permission( 'view' ) ) {
      echo '<div class="notice notice-error"><p>Accès refusé au module qualité.</p></div>';
      return;
    }
    $foundation_data  = $this->get_quality_compliance_foundations_data();
    $overall          = $foundation_data['overall'];
    $by_type          = $foundation_data['by_type'];
    $qualiopi         = $this->get_quality_compliance_qualiopi_summary( $foundation_data );
    $management_state = $this->get_quality_management_state();
    $active_section   = $this->get_quality_compliance_active_section();
    $quality_notice   = isset( $_GET['quality_notice'] ) ? sanitize_key( wp_unslash( $_GET['quality_notice'] ) ) : '';
    $gap_register_rows = $this->get_quality_compliance_gap_register_rows( $foundation_data );
    $full_plan_rows    = $this->get_quality_compliance_full_action_plan_rows( $foundation_data );
    $audit_log_rows    = $this->get_quality_compliance_audit_log_rows( $foundation_data );
    $indicator_rows    = $this->get_quality_compliance_indicator_rows( $foundation_data );
    $gap_rows          = $this->get_quality_compliance_gap_rows( $foundation_data );
    $plan_rows         = $this->get_quality_compliance_action_plan_rows( $foundation_data );
    $alert_bridge_rows = $this->get_quality_alert_bridge_rows();
    $alert_bridge_stats = $this->get_quality_alert_bridge_stats( $alert_bridge_rows );
    $quality_supervision = $this->get_quality_compliance_supervision_data( $alert_bridge_rows, $gap_register_rows, $full_plan_rows, $audit_log_rows );
    $gap_form_defaults    = $this->get_quality_gap_form_defaults( $management_state['editing_gap'] );
    $action_form_defaults = $this->get_quality_action_form_defaults( $management_state['editing_action'] );
    $can_export_quality = ! empty( $management_state['can_export'] );
    $can_manage_quality = ! empty( $management_state['can_manage'] );
    $can_delete_quality = ! empty( $management_state['can_delete'] );
    $today_quality = current_time( 'Y-m-d' );
    $quality_open_gaps = 0; $quality_overdue_gaps = 0; $quality_open_actions = 0; $quality_overdue_actions = 0; $quality_closed_actions = 0;
    foreach ( $gap_register_rows as $row ) {
      $status = strtolower( (string) ( $row['status'] ?? '' ) );
      if ( false === strpos( $status, 'clos' ) && false === strpos( $status, 'résolu' ) && false === strpos( $status, 'resolu' ) ) { $quality_open_gaps++; }
      $due = (string) ( $row['due_date'] ?? '' );
      if ( '' === $due && ! empty( $row['due_label'] ) && preg_match( '/(\d{4}-\d{2}-\d{2})/', (string) $row['due_label'], $m ) ) { $due = $m[1]; }
      if ( '' !== $due && $due < $today_quality && false === strpos( $status, 'clos' ) && false === strpos( $status, 'résolu' ) && false === strpos( $status, 'resolu' ) ) { $quality_overdue_gaps++; }
    }
    foreach ( $full_plan_rows as $row ) {
      $status = strtolower( (string) ( $row['status'] ?? '' ) );
      if ( false === strpos( $status, 'clos' ) && false === strpos( $status, 'termin' ) && false === strpos( $status, 'réalis' ) && false === strpos( $status, 'realis' ) ) { $quality_open_actions++; } else { $quality_closed_actions++; }
      $due = (string) ( $row['due_date'] ?? '' );
      if ( '' === $due && ! empty( $row['due_label'] ) && preg_match( '/(\d{4}-\d{2}-\d{2})/', (string) $row['due_label'], $m ) ) { $due = $m[1]; }
      if ( '' !== $due && $due < $today_quality && false === strpos( $status, 'clos' ) && false === strpos( $status, 'termin' ) && false === strpos( $status, 'réalis' ) && false === strpos( $status, 'realis' ) ) { $quality_overdue_actions++; }
    }
    $resolution_rate = ( $quality_open_actions + $quality_closed_actions ) > 0 ? round( ( $quality_closed_actions / max( 1, $quality_open_actions + $quality_closed_actions ) ) * 100, 1 ) : null;

        if ( '' !== $quality_notice ) {
      $notice_map = array(
        'gap_saved' => 'Écart qualité enregistré.',
        'gap_deleted' => 'Écart qualité supprimé.',
        'action_saved' => 'Action qualité enregistrée.',
        'action_deleted' => 'Action qualité supprimée.',
        'missing_gap_fields' => 'Compléter au minimum le type et l’écart.',
        'missing_action_fields' => 'Compléter au minimum le type et l’objectif.',
      );
      if ( isset( $notice_map[ $quality_notice ] ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $notice_map[ $quality_notice ] ) . '</p></div>';
      }
    }
    ?>
    <style>
      .acdc-quality-shell{display:grid;gap:10px;max-width:none;width:100%}
      .acdc-quality-toolbar{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap;padding:18px 20px;background:linear-gradient(180deg,#fffdfa 0%,#ffffff 100%)}
      .acdc-quality-toolbar .acdc-inline-wrap{gap:8px;flex-wrap:wrap}
      .acdc-quality-toolbar .acdc-button{height:40px}
      .acdc-quality-header-copy{display:grid;gap:6px}
      .acdc-quality-header-copy h2{margin:0;font-size:29px;line-height:1.02;color:var(--acdc-text);letter-spacing:-.02em}
      .acdc-quality-header-copy p{margin:0;color:var(--acdc-text-muted);max-width:940px}
      .acdc-quality-header-meta{display:flex;gap:8px;flex-wrap:wrap}
      .acdc-quality-chip{display:inline-flex;align-items:center;gap:6px;min-height:28px;padding:0 10px;border-radius:999px;background:#f6efe0;border:1px solid #ead7ad;color:#6f441a;font-size:12px;font-weight:700;letter-spacing:.01em}
      .acdc-quality-chip strong{font-weight:800;color:var(--acdc-text)}
      .acdc-quality-tabs{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px;position:sticky;top:18px;z-index:6;padding:0;background:transparent}
      .acdc-quality-tabs .acdc-button{display:flex;justify-content:center;align-items:center;height:42px;padding:0 14px;border-radius:12px;font-weight:700;box-shadow:0 4px 12px rgba(28,44,64,.05)}
      .acdc-quality-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;align-items:stretch}
      .acdc-quality-kpi{position:relative;overflow:hidden;border:1px solid #dbe5ef;border-radius:12px;background:#fff;padding:12px 14px 11px;box-shadow:0 4px 14px rgba(28,44,64,.04)}
      .acdc-quality-kpi:before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:linear-gradient(180deg,#e9c77c 0%,#c5a253 100%)}
      .acdc-quality-kpi__value{font-size:28px;line-height:1;font-weight:800;color:var(--acdc-text);margin-bottom:5px}
      .acdc-quality-kpi__label{font-size:12px;font-weight:700;color:#1e4777;text-transform:uppercase;letter-spacing:.03em}
      .acdc-quality-grid-2{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(340px,.9fr);gap:10px}
      .acdc-quality-grid-2 > .acdc-quality-panel{min-width:0}
      .acdc-quality-panel{border:1px solid #dbe5ef;border-radius:12px;background:#fff;box-shadow:0 4px 14px rgba(28,44,64,.04);overflow:hidden}
      .acdc-quality-panel__head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;padding:12px 14px 9px;border-bottom:1px solid #edf1f5;background:#fcfcfd}
      .acdc-quality-panel__head h3{margin:0 0 2px;font-size:18px;line-height:1.15;color:var(--acdc-text)}
      .acdc-quality-panel__head p{margin:0;color:var(--acdc-text-muted);font-size:13px;line-height:1.4}
      .acdc-quality-panel__body{padding:12px 14px}
      .acdc-quality-tight .acdc-table-wrap{margin:0}
      .acdc-quality-tight table.acdc-table th,.acdc-quality-tight table.acdc-table td{padding-top:8px;padding-bottom:8px;vertical-align:middle}
      .acdc-quality-tight table.acdc-table th{font-size:11px;letter-spacing:.03em;text-transform:uppercase}
      .acdc-quality-tight table.acdc-table td{font-size:13px}
      .acdc-quality-tight .acdc-inline-wrap{gap:6px}
      .acdc-quality-tight .acdc-button{height:34px;padding:0 10px;font-size:12px}
      .acdc-quality-mini-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
      .acdc-quality-mini{border:1px solid #edf1f5;border-radius:12px;padding:11px 12px;background:#fafbfd}
      .acdc-quality-mini strong{display:block;font-size:22px;line-height:1;color:var(--acdc-text);margin-bottom:6px}
      .acdc-quality-mini span{font-size:12px;font-weight:700;color:#1e4777;text-transform:uppercase;letter-spacing:.02em}
      .acdc-quality-tab-panel{display:none;gap:10px}
      .acdc-quality-tab-panel.is-active{display:grid}
      .acdc-quality-tab-panels .acdc-quality-panel{display:block}
      .acdc-quality-status-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
      .acdc-quality-status-pill{display:inline-flex;align-items:center;gap:6px;min-height:30px;padding:0 10px;border-radius:999px;background:#f7f9fb;border:1px solid #dbe5ef;color:#1e4777;font-size:12px;font-weight:700}
      .acdc-quality-status-pill strong{color:var(--acdc-text)}
      .acdc-quality-scroll-x{overflow:auto hidden;padding-bottom:2px}
      .acdc-quality-scroll-x::-webkit-scrollbar{height:8px}
      .acdc-quality-scroll-x::-webkit-scrollbar-thumb{background:#d7dee8;border-radius:999px}
      .acdc-quality-shell .acdc-table-wrap{overflow:auto}
      .acdc-quality-shell .acdc-table{min-width:760px}
      .acdc-quality-shell .acdc-table td .acdc-button + .acdc-button{margin-left:4px}
      .acdc-quality-shell textarea,.acdc-quality-shell input,.acdc-quality-shell select{border-radius:10px}
      .acdc-quality-shell .notice{margin:0 0 4px}
      .acdc-quality-anchor-nav{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:4px 0 10px}
      .acdc-quality-anchor-nav .acdc-button{height:34px;padding:0 12px;font-size:12px}
      .acdc-quality-quick-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:0 0 12px}
      .acdc-quality-quick-actions .acdc-button{height:38px;padding:0 14px;font-size:12px;display:inline-flex;align-items:center;gap:8px}
      .acdc-quality-quick-actions .acdc-badge{font-size:11px;line-height:1.2;padding:5px 8px}
      .acdc-quality-panel[id]{scroll-margin-top:96px}
      .acdc-quality-kpi--critical,.acdc-quality-mini--critical{border-color:#f0c8c8;background:linear-gradient(180deg,#fff5f5 0%,#fffafb 100%)}
      .acdc-quality-kpi--warning,.acdc-quality-mini--warning{border-color:#ecd8a3;background:linear-gradient(180deg,#fffaf1 0%,#fffdf8 100%)}
      .acdc-quality-kpi--success,.acdc-quality-mini--success{border-color:#cfe7cf;background:linear-gradient(180deg,#f7fff7 0%,#fcfffc 100%)}
      .acdc-quality-kpi--info,.acdc-quality-mini--info{border-color:#cfe0f3;background:linear-gradient(180deg,#f8fbff 0%,#fcfdff 100%)}
      .acdc-quality-kpi--neutral,.acdc-quality-mini--neutral{border-color:#e6ebf1;background:linear-gradient(180deg,#fafbfd 0%,#ffffff 100%)}
      .acdc-quality-kpi--critical .acdc-quality-kpi__value,.acdc-quality-mini--critical strong{color:#a73d3d}
      .acdc-quality-kpi--warning .acdc-quality-kpi__value,.acdc-quality-mini--warning strong{color:#8b5b23}
      .acdc-quality-kpi--success .acdc-quality-kpi__value,.acdc-quality-mini--success strong{color:#2f7d4a}
      .acdc-quality-kpi--info .acdc-quality-kpi__value,.acdc-quality-mini--info strong{color:#1e4777}
      .acdc-quality-kpi--neutral .acdc-quality-kpi__value,.acdc-quality-mini--neutral strong{color:var(--acdc-text)}
      .acdc-quality-kpi--critical,.acdc-quality-kpi--warning,.acdc-quality-kpi--success,.acdc-quality-kpi--info,.acdc-quality-kpi--neutral,.acdc-quality-mini--critical,.acdc-quality-mini--warning,.acdc-quality-mini--success,.acdc-quality-mini--info,.acdc-quality-mini--neutral{box-shadow:0 6px 18px rgba(12,45,82,.05)}
      .acdc-quality-quick-action--critical{box-shadow:inset 0 0 0 1px #d97d7d}
      .acdc-quality-quick-action--warning{box-shadow:inset 0 0 0 1px #d7b45b}
      .acdc-quality-quick-action--success{box-shadow:inset 0 0 0 1px #7eb68c}
      .acdc-quality-quick-action--info{box-shadow:inset 0 0 0 1px #9db9d6}
      .acdc-quality-quick-action--neutral{box-shadow:inset 0 0 0 1px #dce4ec}
      .acdc-quality-panel--focus{border-color:#e6c98a;box-shadow:0 8px 22px rgba(139,91,35,.10)}
      .acdc-quality-panel--focus .acdc-quality-panel__head{background:linear-gradient(180deg,#fff8eb 0%,#fffdf8 100%)}
      .acdc-quality-panel--secondary{opacity:.96}
      .acdc-quality-panel--secondary .acdc-quality-panel__head h3{font-size:16px}
      .acdc-quality-panel--secondary .acdc-quality-panel__head p{font-size:12px;max-width:64ch}
      .acdc-quality-table-priority thead th,.acdc-quality-table-structured thead th{position:sticky;top:0;z-index:1;background:#f8fafc}
      .acdc-quality-table-priority td,.acdc-quality-table-priority th,.acdc-quality-table-structured td,.acdc-quality-table-structured th{vertical-align:top}
      .acdc-quality-action-stack{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
      .acdc-quality-action-stack .acdc-button{height:34px;padding:0 10px;font-size:12px;display:inline-flex;align-items:center;justify-content:center}
      .acdc-quality-action-stack .acdc-button-soft{background:#fff8eb}
      .acdc-quality-action-stack .acdc-quality-jump{font-size:12px;color:#8b5b23;font-weight:600;text-decoration:none;padding:0 2px}
      .acdc-quality-panel,.acdc-quality-kpi,.acdc-quality-mini,.acdc-quality-shell table.acdc-table tbody tr,.acdc-quality-shell .acdc-button,.acdc-quality-jump{transition:transform .16s ease,box-shadow .16s ease,background-color .16s ease,border-color .16s ease,color .16s ease,opacity .16s ease}
      .acdc-quality-panel--focus:hover{transform:translateY(-1px);box-shadow:0 12px 28px rgba(139,91,35,.12)}
      .acdc-quality-kpi:hover,.acdc-quality-mini:hover{transform:translateY(-1px);box-shadow:0 10px 22px rgba(12,45,82,.08)}
      .acdc-quality-shell table.acdc-table tbody tr.acdc-quality-row-interactive:hover{box-shadow:inset 3px 0 0 #c5a253;background:linear-gradient(90deg,rgba(233,199,124,.10) 0%,rgba(255,255,255,0) 72%)}
      .acdc-quality-shell table.acdc-table tbody tr.acdc-quality-row-interactive:hover td{background:transparent}
      .acdc-quality-shell table.acdc-table tbody tr.acdc-quality-row-interactive:focus-within{outline:2px solid rgba(139,91,35,.18);outline-offset:-2px}
      .acdc-quality-interactive:hover,.acdc-quality-interactive:focus-visible,.acdc-quality-jump:hover,.acdc-quality-jump:focus-visible{transform:translateY(-1px);box-shadow:0 8px 18px rgba(12,45,82,.10)}
      .acdc-quality-interactive.is-pressed,.acdc-quality-jump.is-pressed{transform:translateY(0) scale(.985);box-shadow:0 3px 10px rgba(12,45,82,.10)}
      .acdc-quality-interactive.is-activated,.acdc-quality-jump.is-activated{box-shadow:0 0 0 3px rgba(197,162,83,.20),0 8px 18px rgba(12,45,82,.10)}
      .acdc-quality-surface-interactive{cursor:default}
      .acdc-quality-jump{display:inline-flex;align-items:center;justify-content:center;min-height:30px;padding:0 10px;border-radius:999px;background:#fff8eb;border:1px solid #e6c98a}
      .acdc-quality-jump:hover,.acdc-quality-jump:focus-visible{text-decoration:none;color:#6f441a;background:#fff3d8}
      .acdc-quality-shell .acdc-button:focus-visible,.acdc-quality-jump:focus-visible{outline:none;box-shadow:0 0 0 3px rgba(197,162,83,.22),0 8px 18px rgba(12,45,82,.10)}
      .acdc-quality-quick-actions .acdc-button,.acdc-quality-action-stack .acdc-button{position:relative}
      .acdc-quality-quick-actions .acdc-button:hover,.acdc-quality-action-stack .acdc-button:hover{border-color:#8b5b23}
      .acdc-quality-tabs .acdc-button:hover,.acdc-quality-tabs .acdc-button:focus-visible{transform:translateY(-1px);box-shadow:0 8px 18px rgba(12,45,82,.10)}
      .acdc-quality-panel__head h3,.acdc-quality-panel__head p{transition:color .16s ease,opacity .16s ease}
      .acdc-quality-panel--focus:hover .acdc-quality-panel__head h3{color:#8b5b23}

      .acdc-quality-row-critical td:first-child,.acdc-quality-row-attention td:first-child,.acdc-quality-row-proof td:first-child,.acdc-quality-row-closure td:first-child{box-shadow:inset 4px 0 0 0 #8b5b23}
      .acdc-quality-row-critical td{background:#fff5f5}
      .acdc-quality-row-attention td{background:#fffaf1}
      .acdc-quality-row-proof td{background:#f8fbff}
      .acdc-quality-row-closure td{background:#f8fff8}
      .acdc-quality-col-priority,.acdc-quality-col-state,.acdc-quality-col-action{white-space:nowrap}
      .acdc-quality-col-action{text-align:right}
      .acdc-quality-col-action .acdc-button{min-width:150px;justify-content:center}
      .acdc-portal-content-extranet.acdc-page-improvement_pilotage{overflow:visible}
      .acdc-portal-content-extranet.acdc-page-improvement_pilotage .acdc-quality-shell{max-width:none!important;width:100%!important}
      @media (max-width: 1480px){.acdc-quality-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.acdc-quality-tabs{grid-template-columns:repeat(3,minmax(0,1fr))}.acdc-quality-grid-2{grid-template-columns:1fr}}
      @media (max-width: 980px){.acdc-quality-tabs{grid-template-columns:repeat(2,minmax(0,1fr));position:static}.acdc-quality-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.acdc-quality-mini-grid{grid-template-columns:1fr}}
      @media (max-width: 640px){.acdc-quality-toolbar{padding:14px}.acdc-quality-header-copy h2{font-size:24px}.acdc-quality-kpis{grid-template-columns:1fr}.acdc-quality-tabs{grid-template-columns:1fr}}
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
      document.querySelectorAll('[data-acdc-quality-tab]').forEach(function(btn){
        btn.addEventListener('click', function(e){
          e.preventDefault();
          var target = btn.getAttribute('data-acdc-quality-tab');
          document.querySelectorAll('[data-acdc-quality-tab]').forEach(function(item){
            item.classList.remove('acdc-button-primary');
            item.classList.add('acdc-button-soft');
            item.setAttribute('aria-pressed','false');
          });
          document.querySelectorAll('.acdc-quality-tab-panel').forEach(function(panel){ panel.classList.remove('is-active'); });
          btn.classList.add('acdc-button-primary');
          btn.classList.remove('acdc-button-soft');
          btn.setAttribute('aria-pressed','true');
          var panel = document.querySelector('.acdc-quality-tab-panel[data-acdc-quality-panel="'+target+'"]');
          if(panel){ panel.classList.add('is-active'); }
          var url = new URL(window.location.href);
          url.searchParams.set('qc_section', target);
          window.history.replaceState({}, '', url.toString());
        });
      });
    });
    </script>
    <section class="acdc-quality-shell acdc-mb-18" style="max-width:none;width:100%;">
      <div class="acdc-quality-panel">
        <div class="acdc-quality-panel__body acdc-quality-toolbar">
          <div class="acdc-quality-header-copy">
            <h2>Qualité & conformité</h2>
            <p>Pilotage des indicateurs, écarts, actions et journaux dans une console front dense, lisible et immédiatement exploitable.</p>
            <div class="acdc-quality-header-meta">
              <span class="acdc-quality-chip"><strong><?php echo esc_html( $overall['survey_types'] ); ?></strong> types suivis</span>
              <span class="acdc-quality-chip"><strong><?php echo esc_html( $quality_open_gaps ); ?></strong> écarts ouverts</span>
              <span class="acdc-quality-chip"><strong><?php echo esc_html( $quality_open_actions ); ?></strong> actions ouvertes</span>
              <span class="acdc-quality-chip"><strong><?php echo esc_html( null !== $resolution_rate ? $resolution_rate . ' %' : '—' ); ?></strong> résolution</span>
            </div>
          </div>
          <div class="acdc-inline-wrap">
            <?php if ( is_admin() ) : ?>
              <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( method_exists( $this, 'quality_compliance_page_url' ) ? $this->quality_compliance_page_url( array( 'tab' => 'improvement_pilotage' ) ) : $this->portal_page_url( array( 'tab' => 'improvement_pilotage' ) ) ); ?>">Ouvrir le front office</a>
            <?php endif; ?>
            <?php if ( $can_manage_quality ) : ?>
              <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $this->get_quality_management_url( 'new_gap' ) ); ?>">Créer un écart</a>
              <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $this->get_quality_management_url( 'new_action' ) ); ?>">Créer une action</a>
            <?php endif; ?>
            <?php if ( $can_export_quality ) : ?>
              <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->get_improvement_pilotage_export_url( 'csv', $this->get_improvement_pilotage_filters_from_request() ) ); ?>">Exporter CSV</a>
              <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->get_improvement_pilotage_export_url( 'excel', $this->get_improvement_pilotage_filters_from_request() ) ); ?>">Exporter Excel</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="acdc-quality-scroll-x">
        <div class="acdc-quality-tabs">
          <?php foreach ( $this->get_quality_compliance_section_label_map() as $section_key => $section_label ) : ?>
            <?php $btn_class = $section_key === $active_section ? 'acdc-button acdc-button-primary' : 'acdc-button acdc-button-soft'; ?>
            <a href="<?php echo esc_url( $this->get_quality_compliance_section_url( $section_key ) ); ?>" class="<?php echo esc_attr( $btn_class ); ?>" data-acdc-quality-tab="<?php echo esc_attr( $section_key ); ?>" aria-pressed="<?php echo $section_key === $active_section ? 'true' : 'false'; ?>"><?php echo esc_html( $section_label ); ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="acdc-quality-status-row">
        <span class="acdc-quality-status-pill"><strong>Section active</strong> <?php echo esc_html( $this->get_quality_compliance_section_label_map()[ $active_section ] ?? $active_section ); ?></span>
        <span class="acdc-quality-status-pill"><strong>Écarts</strong> <?php echo esc_html( $quality_open_gaps ); ?> ouverts / <?php echo esc_html( $quality_overdue_gaps ); ?> en retard</span>
        <span class="acdc-quality-status-pill"><strong>Actions</strong> <?php echo esc_html( $quality_open_actions ); ?> ouvertes / <?php echo esc_html( $quality_closed_actions ); ?> clôturées</span>
      </div>
      <?php if ( 'overview' === $active_section ) : ?>
      <div class="acdc-quality-anchor-nav">
        <a class="acdc-button acdc-button-soft" href="#acdc-quality-todo-now">À faire maintenant</a>
        <a class="acdc-button acdc-button-soft" href="#acdc-quality-reminders">Relances internes</a>
        <a class="acdc-button acdc-button-soft" href="#acdc-quality-arbitration">Arbitrage transverse</a>
        <a class="acdc-button acdc-button-soft" href="#acdc-quality-owner-load">Charge par pilote</a>
        <a class="acdc-button acdc-button-soft" href="#acdc-quality-late-closures">Retards & clôtures</a>
        <a class="acdc-button acdc-button-soft" href="#acdc-quality-traceability">Traçabilité</a>
      </div>
      <div class="acdc-quality-quick-actions">
        <a class="acdc-button acdc-button-primary <?php echo esc_attr( $this->get_quality_compliance_variant_class( 'Urgence', 'acdc-quality-quick-action' ) ); ?>" href="#acdc-quality-todo-now">Traiter la file prioritaire <?php echo $this->render_quality_compliance_badge( 'Urgence' ); ?></a>
        <a class="acdc-button acdc-button-primary <?php echo esc_attr( $this->get_quality_compliance_variant_class( 'À traiter', 'acdc-quality-quick-action' ) ); ?>" href="#acdc-quality-reminders">Ouvrir les relances utiles <?php echo $this->render_quality_compliance_badge( 'À traiter' ); ?></a>
        <a class="acdc-button acdc-button-primary <?php echo esc_attr( $this->get_quality_compliance_variant_class( 'Clôture', 'acdc-quality-quick-action' ) ); ?>" href="#acdc-quality-arbitration">Arbitrer les clôtures <?php echo $this->render_quality_compliance_badge( 'Clôture' ); ?></a>
        <?php if ( $can_manage_quality ) : ?>
          <a class="acdc-button acdc-button-soft <?php echo esc_attr( $this->get_quality_compliance_variant_class( 'En cours', 'acdc-quality-quick-action' ) ); ?>" href="<?php echo esc_url( $this->get_quality_management_url( 'new_action' ) ); ?>">Créer une action qualité <?php echo $this->render_quality_compliance_badge( 'En cours' ); ?></a>
          <a class="acdc-button acdc-button-soft <?php echo esc_attr( $this->get_quality_compliance_variant_class( 'Vigilance', 'acdc-quality-quick-action' ) ); ?>" href="<?php echo esc_url( $this->get_quality_management_url( 'new_gap' ) ); ?>">Créer un écart <?php echo $this->render_quality_compliance_badge( 'Vigilance' ); ?></a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="acdc-quality-kpis">
        <div class="acdc-quality-kpi <?php echo esc_attr( $this->get_quality_compliance_variant_class( 'Information', 'acdc-quality-kpi' ) ); ?>"><div class="acdc-quality-kpi__value"><?php echo esc_html( $overall['survey_types'] ); ?></div><div class="acdc-quality-kpi__label">Types couverts</div></div>
        <div class="acdc-quality-kpi <?php echo esc_attr( $this->get_quality_compliance_variant_class( null !== $overall['response_rate'] && $overall['response_rate'] >= 70 ? 'Maîtrisé' : ( null !== $overall['response_rate'] && $overall['response_rate'] >= 40 ? 'À surveiller' : 'Retard' ), 'acdc-quality-kpi' ) ); ?>"><div class="acdc-quality-kpi__value"><?php echo esc_html( null !== $overall['response_rate'] ? $overall['response_rate'] . ' %' : '—' ); ?></div><div class="acdc-quality-kpi__label">Taux de réponse</div></div>
        <div class="acdc-quality-kpi <?php echo esc_attr( $this->get_quality_compliance_variant_class( $quality_open_gaps > 0 ? 'À traiter' : 'Maîtrisé', 'acdc-quality-kpi' ) ); ?>"><div class="acdc-quality-kpi__value"><?php echo esc_html( $quality_open_gaps ); ?></div><div class="acdc-quality-kpi__label">Écarts ouverts</div></div>
        <div class="acdc-quality-kpi <?php echo esc_attr( $this->get_quality_compliance_variant_class( $quality_open_actions > 0 ? 'En cours' : 'Maîtrisé', 'acdc-quality-kpi' ) ); ?>"><div class="acdc-quality-kpi__value"><?php echo esc_html( $quality_open_actions ); ?></div><div class="acdc-quality-kpi__label">Actions ouvertes</div></div>
        <div class="acdc-quality-kpi <?php echo esc_attr( $this->get_quality_compliance_variant_class( $quality_overdue_actions > 0 ? 'Urgence' : 'Maîtrisé', 'acdc-quality-kpi' ) ); ?>"><div class="acdc-quality-kpi__value"><?php echo esc_html( $quality_overdue_actions ); ?></div><div class="acdc-quality-kpi__label">Retards à résorber</div></div>
        <div class="acdc-quality-kpi <?php echo esc_attr( $this->get_quality_compliance_variant_class( null !== $resolution_rate && $resolution_rate >= 70 ? 'Maîtrisé' : ( null !== $resolution_rate && $resolution_rate >= 40 ? 'À surveiller' : 'À traiter' ), 'acdc-quality-kpi' ) ); ?>"><div class="acdc-quality-kpi__value"><?php echo esc_html( null !== $resolution_rate ? $resolution_rate . ' %' : '—' ); ?></div><div class="acdc-quality-kpi__label">Résolution</div></div>
      </div>
      <div class="acdc-quality-tab-panels">
        <section class="acdc-quality-tab-panel acdc-quality-grid-2 <?php echo 'overview' === $active_section ? 'is-active' : ''; ?>" data-acdc-quality-panel="overview">
          <div class="acdc-quality-panel acdc-quality-panel--secondary">
            <div class="acdc-quality-panel__head"><h3>Vue globale</h3><p>Vue générale du module, à lire après les priorités opérationnelles.</p></div>
            <div class="acdc-quality-panel__body acdc-quality-tight">
              <div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Indicateur</th><th>État consolidé</th></tr></thead><tbody>
                <tr><td>Parties prenantes avec chaîne complète</td><td><?php echo esc_html( $qualiopi['parties_prenantes_couvertes'] . ' / ' . $overall['survey_types'] ); ?></td></tr>
                <tr><td>Parties prenantes avec réponses disponibles</td><td><?php echo esc_html( $qualiopi['parties_prenantes_repondu'] . ' / ' . $overall['survey_types'] ); ?></td></tr>
                <tr><td>Actions d’amélioration reliées aux enquêtes</td><td><?php echo esc_html( $qualiopi['preuves_actions'] ); ?></td></tr>
                <tr><td>Actions encore à traiter</td><td><?php echo esc_html( $qualiopi['preuves_a_traiter'] ); ?></td></tr>
              </tbody></table></div>
            </div>
          </div>
          <div class="acdc-quality-panel acdc-quality-panel--secondary">
            <div class="acdc-quality-panel__head"><h3>Supervision pilotage</h3><p>Lecture immédiate des urgences, retards, preuves et clôtures à engager.</p></div>
            <div class="acdc-quality-panel__body">
              <div class="acdc-quality-mini-grid">
                <div class="acdc-quality-mini <?php echo esc_attr( $this->get_quality_compliance_variant_class( ( $quality_supervision['stats']['queue_total'] ?? 0 ) > 0 ? 'À traiter' : 'Maîtrisé', 'acdc-quality-mini' ) ); ?>"><strong><?php echo esc_html( $quality_supervision['stats']['queue_total'] ?? 0 ); ?></strong><span>À traiter maintenant</span></div>
                <div class="acdc-quality-mini <?php echo esc_attr( $this->get_quality_compliance_variant_class( 'Information', 'acdc-quality-mini' ) ); ?>"><strong><?php echo esc_html( $quality_supervision['stats']['owners'] ?? 0 ); ?></strong><span>Pilotes mobilisés</span></div>
                <div class="acdc-quality-mini <?php echo esc_attr( $this->get_quality_compliance_variant_class( ( ( $quality_supervision['stats']['late_gaps'] ?? 0 ) + ( $quality_supervision['stats']['late_actions'] ?? 0 ) ) > 0 ? 'Urgence' : 'Maîtrisé', 'acdc-quality-mini' ) ); ?>"><strong><?php echo esc_html( ( $quality_supervision['stats']['late_gaps'] ?? 0 ) + ( $quality_supervision['stats']['late_actions'] ?? 0 ) ); ?></strong><span>Retards à résorber</span></div>
                <div class="acdc-quality-mini <?php echo esc_attr( $this->get_quality_compliance_variant_class( ( $quality_supervision['stats']['closed_actions'] ?? 0 ) > 0 ? 'Clôture' : 'Information', 'acdc-quality-mini' ) ); ?>"><strong><?php echo esc_html( $quality_supervision['stats']['closed_actions'] ?? 0 ); ?></strong><span>Clôtures à valider</span></div>
                <div class="acdc-quality-mini <?php echo esc_attr( $this->get_quality_compliance_variant_class( ( $quality_supervision['stats']['loop_proof'] ?? 0 ) > 0 ? 'Preuve consolidée' : 'Preuve manquante', 'acdc-quality-mini' ) ); ?>"><strong><?php echo esc_html( $quality_supervision['stats']['loop_proof'] ?? 0 ); ?></strong><span>Preuves à sécuriser</span></div>
                <div class="acdc-quality-mini <?php echo esc_attr( $this->get_quality_compliance_variant_class( ( $quality_supervision['stats']['loop_reopen'] ?? 0 ) > 0 ? 'À surveiller' : 'Maîtrisé', 'acdc-quality-mini' ) ); ?>"><strong><?php echo esc_html( $quality_supervision['stats']['loop_reopen'] ?? 0 ); ?></strong><span>Suivi à surveiller</span></div>
              </div>
            </div>
          </div>
          <div class="acdc-quality-panel acdc-quality-panel--secondary">
            <div class="acdc-quality-panel__head"><h3>Taux de réponse par type</h3><p>Indicateur secondaire de couverture par type d’enquête.</p></div>
            <div class="acdc-quality-panel__body acdc-quality-tight">
              <div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Type</th><th>Réponses</th><th>Taux</th><th>Statut</th></tr></thead><tbody>
                <?php foreach ( $by_type as $row ) : ?><tr><td><?php echo esc_html( $row['label'] ); ?></td><td><?php echo esc_html( $row['responses'] . ' / ' . $row['participants'] ); ?></td><td><?php echo esc_html( null !== $row['response_rate'] ? $row['response_rate'] . ' %' : '—' ); ?></td><td><?php echo $this->render_quality_compliance_badge( $row['proof_status'] ); ?></td></tr><?php endforeach; ?>
              </tbody></table></div>
            </div>
          </div>
          <div class="acdc-quality-panel acdc-quality-panel--secondary">
            <div class="acdc-quality-panel__head"><h3>Chaîne de preuve</h3><p>Lecture secondaire des preuves, envois et actions reliées.</p></div>
            <div class="acdc-quality-panel__body acdc-quality-tight">
              <div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Type</th><th>Envois</th><th>Alertes</th><th>Actions</th><th>À traiter</th></tr></thead><tbody>
                <?php foreach ( $by_type as $row ) : ?><tr><td><?php echo esc_html( $row['label'] ); ?></td><td><?php echo esc_html( $row['sessions'] ); ?></td><td><?php echo esc_html( $row['alerts'] ); ?></td><td><?php echo esc_html( $row['actions_total'] ); ?></td><td><?php echo esc_html( $row['actions_to_process'] ); ?></td></tr><?php endforeach; ?>
              </tbody></table></div>
            </div>
          </div>
          <div class="acdc-quality-panel">
            <div class="acdc-quality-panel__head"><h3>Chaîne transversale des alertes</h3><p>Lecture directe du passage enquêtes → alertes → écarts → actions → journal, avec différenciation métier et action automatique proposée.</p></div>
            <div class="acdc-quality-panel__body acdc-quality-tight"><div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Source</th><th>Session</th><th>Répondant</th><th>Type d’alerte</th><th>Alerte</th><th>Niveau</th><th>Scénario</th><th>Assignation auto</th><th>Action auto</th><th>Écart proposé</th></tr></thead><tbody><?php if ( ! empty( $alert_bridge_rows ) ) : ?><?php foreach ( $alert_bridge_rows as $alert_row ) : ?><tr><td><?php echo esc_html( $alert_row['source_label'] ); ?></td><td><?php echo esc_html( $alert_row['session_title'] ); ?></td><td><?php echo esc_html( $alert_row['participant_label'] ); ?></td><td><?php echo esc_html( $alert_row['alert_type_label'] ?? 'Alerte qualité' ); ?></td><td><?php echo esc_html( $alert_row['alert_reason'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $alert_row['severity'] ); ?></td><td><?php echo esc_html( $alert_row['scenario_label'] ?? 'Qualification qualité' ); ?></td><td><?php echo esc_html( $alert_row['assigned_owner'] ?? 'Pilotage qualité' ); ?></td><td><?php echo esc_html( $alert_row['default_action_label'] ?? 'Prendre en charge' ); ?></td><td><?php echo esc_html( $alert_row['gap_title'] ); ?></td></tr><?php endforeach; ?><?php else : ?><tr><td colspan="10"><span class="acdc-muted">Aucune alerte transverse détectée actuellement.</span></td></tr><?php endif; ?></tbody></table></div></div>
          </div>

          <div class="acdc-quality-panel">
            <div class="acdc-quality-panel__head"><h3>Boucles de traitement qualité</h3><p>Lecture opérationnelle : prise en charge, traitement, preuve, clôture et réouverture si nécessaire.</p></div>
            <div class="acdc-quality-panel__body acdc-quality-tight">
              <div class="acdc-quality-mini-grid" style="margin-bottom:12px;">
                <div class="acdc-quality-mini"><strong><?php echo esc_html( $quality_supervision['loop_stats']['intake'] ?? 0 ); ?></strong><span>Prises en charge</span></div>
                <div class="acdc-quality-mini"><strong><?php echo esc_html( $quality_supervision['loop_stats']['treatment'] ?? 0 ); ?></strong><span>Traitements engagés</span></div>
                <div class="acdc-quality-mini"><strong><?php echo esc_html( $quality_supervision['loop_stats']['proof'] ?? 0 ); ?></strong><span>Preuves en cours</span></div>
                <div class="acdc-quality-mini"><strong><?php echo esc_html( $quality_supervision['stats']['closures_to_validate'] ?? 0 ); ?></strong><span>Clôtures à valider</span></div>
                <div class="acdc-quality-mini"><strong><?php echo esc_html( $quality_supervision['stats']['closures_to_reopen'] ?? 0 ); ?></strong><span>À réouvrir</span></div>
                <div class="acdc-quality-mini"><strong><?php echo esc_html( $quality_supervision['stats']['closures_with_surveillance'] ?? 0 ); ?></strong><span>À surveiller</span></div>
                <div class="acdc-quality-mini"><strong><?php echo esc_html( $quality_supervision['stats']['internal_reminders'] ?? 0 ); ?></strong><span>Relances internes</span></div>
                <div class="acdc-quality-mini"><strong><?php echo esc_html( $quality_supervision['loop_stats']['reopen'] ?? 0 ); ?></strong><span>Suivi à surveiller</span></div>
              </div>
              <div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Source</th><th>Répondant</th><th>Pilote</th><th>Prise en charge</th><th>Traitement</th><th>Preuve</th><th>Clôture</th><th>Réouverture</th><th>Échéance</th><th>Actions directes</th></tr></thead><tbody><?php if ( ! empty( $quality_supervision['loop_rows'] ) ) : ?><?php foreach ( $quality_supervision['loop_rows'] as $loop_row ) : ?><tr><td><?php echo esc_html( $loop_row['source'] ); ?></td><td><?php echo esc_html( $loop_row['respondent'] ); ?></td><td><?php echo esc_html( $loop_row['owner'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $loop_row['intake_status'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $loop_row['treatment_status'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $loop_row['proof_stage'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $loop_row['closure_status'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $loop_row['reopen_status'] ); ?></td><td><?php echo esc_html( $loop_row['due_label'] ); ?></td><td class="acdc-inline-wrap"><?php if ( $can_manage_quality ) : ?><?php if ( ! empty( $loop_row['take_in_charge_url'] ) ) : ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $loop_row['take_in_charge_url'] ); ?>">Prendre en charge</a><?php endif; ?><?php if ( ! empty( $loop_row['open_treatment_url'] ) ) : ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $loop_row['open_treatment_url'] ); ?>">Ouvrir traitement</a><?php endif; ?><?php if ( ! empty( $loop_row['deposit_proof_url'] ) ) : ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $loop_row['deposit_proof_url'] ); ?>">Déposer preuve</a><?php endif; ?><?php if ( ! empty( $loop_row['close_url'] ) ) : ?><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $loop_row['close_url'] ); ?>">Clôturer</a><?php endif; ?><?php if ( ! empty( $loop_row['reopen_url'] ) ) : ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $loop_row['reopen_url'] ); ?>">Réouvrir</a><?php endif; ?><?php else : ?><span class="acdc-muted">Lecture seule</span><?php endif; ?></td></tr><?php endforeach; ?><?php else : ?><tr><td colspan="10"><span class="acdc-muted">Aucune boucle opérationnelle disponible pour le moment.</span></td></tr><?php endif; ?></tbody></table></div>
            </div>
          </div>
          <div class="acdc-quality-panel acdc-quality-panel--focus" id="acdc-quality-todo-now">
            <div class="acdc-quality-panel__head"><h3>À faire maintenant</h3><p>File opérationnelle immédiate hiérarchisée strictement : urgence, retard, preuve manquante, puis validation de clôture.</p></div>
            <div class="acdc-quality-panel__body acdc-quality-tight"><div class="acdc-table-wrap"><table class="acdc-table acdc-quality-table-priority"><thead><tr><th class="acdc-quality-col-state">Hiérarchie</th><th class="acdc-quality-col-priority">Priorité</th><th>Type d’alerte</th><th>Scénario</th><th>Pilote</th><th>Échéance</th><th class="acdc-quality-col-state">État de boucle</th><th class="acdc-quality-col-action">Action directe</th></tr></thead><tbody><?php if ( ! empty( $quality_supervision['todo_rows'] ) ) : ?><?php foreach ( $quality_supervision['todo_rows'] as $todo_row ) : ?><?php $todo_row_class = ""; if ( ! empty( $todo_row['hierarchy_label'] ) ) { if ( false !== strpos( (string) $todo_row['hierarchy_label'], "Urgence" ) ) { $todo_row_class = "acdc-quality-row-critical"; } elseif ( false !== strpos( (string) $todo_row['hierarchy_label'], "Retard" ) ) { $todo_row_class = "acdc-quality-row-attention"; } elseif ( false !== strpos( (string) $todo_row['hierarchy_label'], "Preuve" ) ) { $todo_row_class = "acdc-quality-row-proof"; } elseif ( false !== strpos( (string) $todo_row['hierarchy_label'], "clôture" ) || false !== strpos( (string) $todo_row['hierarchy_label'], "Clôture" ) || false !== strpos( (string) $todo_row['hierarchy_label'], "Validation" ) ) { $todo_row_class = "acdc-quality-row-closure"; } } ?><tr class="<?php echo esc_attr( $todo_row_class ); ?>"><td class="acdc-quality-col-state"><div style="display:flex;flex-direction:column;gap:4px;"><?php echo $this->render_quality_compliance_badge( $todo_row['hierarchy_label'] ?? 'Suivi' ); ?><?php if ( ! empty( $todo_row['hierarchy_detail'] ) ) : ?><span class="acdc-muted"><?php echo esc_html( $todo_row['hierarchy_detail'] ); ?></span><?php endif; ?></div></td><td class="acdc-quality-col-priority"><?php echo $this->render_quality_compliance_badge( $todo_row['priority'] ); ?></td><td><?php echo esc_html( $todo_row['type'] ); ?></td><td><?php echo esc_html( $todo_row['scenario'] ); ?></td><td><?php echo esc_html( $todo_row['owner'] ); ?></td><td><?php echo esc_html( $todo_row['due_label'] ); ?></td><td class="acdc-quality-col-state"><?php echo esc_html( $todo_row['loop_status'] ); ?></td><td class="acdc-quality-col-action"><div class="acdc-quality-action-stack"><?php if ( $can_manage_quality && ! empty( $todo_row['action_url'] ) ) : ?><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $todo_row['action_url'] ); ?>"><?php echo esc_html( $todo_row['action_label'] ); ?></a><?php if ( ! empty( $todo_row['secondary_action_url'] ) && ! empty( $todo_row['secondary_action_label'] ) ) : ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $todo_row['secondary_action_url'] ); ?>"><?php echo esc_html( $todo_row['secondary_action_label'] ); ?></a><?php endif; ?><?php else : ?><span class="acdc-muted"><?php echo esc_html( $todo_row['action_label'] ); ?></span><?php endif; ?><?php $todo_jump = '#acdc-quality-priority-queue'; if ( ! empty( $todo_row['hierarchy_label'] ) && false !== stripos( (string) $todo_row['hierarchy_label'], 'clôture' ) ) { $todo_jump = '#acdc-quality-closures-validation'; } elseif ( ! empty( $todo_row['hierarchy_label'] ) && false !== stripos( (string) $todo_row['hierarchy_label'], 'preuve' ) ) { $todo_jump = '#acdc-quality-reminders'; } ?><a class="acdc-quality-jump" href="<?php echo esc_url( $todo_jump ); ?>">Ouvrir</a></div></td></tr><?php endforeach; ?><?php else : ?><tr><td colspan="8"><span class="acdc-muted">Aucun traitement prioritaire immédiat.</span></td></tr><?php endif; ?></tbody></table></div></div>
          </div>
          <div class="acdc-quality-panel acdc-quality-panel--focus" id="acdc-quality-reminders">
            <div class="acdc-quality-panel__head"><h3>Relances internes</h3><p>Relances opérationnelles distinguées entre prise en charge, preuve, échéance et clôture, avec action directe adaptée.</p></div>
            <div class="acdc-quality-panel__body acdc-quality-tight"><div class="acdc-table-wrap"><table class="acdc-table acdc-quality-table-structured"><thead><tr><th>Date</th><th class="acdc-quality-col-priority">Priorité</th><th>Pilote</th><th>Source</th><th>Répondant</th><th class="acdc-quality-col-state">Type de relance</th><th>Type d’alerte</th><th>Motif</th><th class="acdc-quality-col-action">Action</th><th class="acdc-quality-col-state">Statut</th></tr></thead><tbody><?php if ( ! empty( $quality_supervision['reminder_rows'] ) ) : ?><?php foreach ( $quality_supervision['reminder_rows'] as $reminder_row ) : ?><?php $reminder_row_class = ""; $reminder_type_label = (string) ( $reminder_row['reminder_type'] ?? "" ); if ( false !== strpos( $reminder_type_label, "preuve" ) || false !== strpos( $reminder_type_label, "Preuve" ) ) { $reminder_row_class = "acdc-quality-row-proof"; } elseif ( false !== strpos( $reminder_type_label, "clôture" ) || false !== strpos( $reminder_type_label, "Clôture" ) ) { $reminder_row_class = "acdc-quality-row-closure"; } elseif ( false !== strpos( (string) ( $reminder_row['urgency'] ?? "" ), "Très haute" ) || false !== strpos( (string) ( $reminder_row['urgency'] ?? "" ), "Haute" ) ) { $reminder_row_class = "acdc-quality-row-critical"; } else { $reminder_row_class = "acdc-quality-row-attention"; } ?><tr class="<?php echo esc_attr( $reminder_row_class ); ?>"><td><?php echo esc_html( ! empty( $reminder_row['date'] ) ? mysql2date( 'j F Y H:i', $reminder_row['date'] ) : '—' ); ?></td><td class="acdc-quality-col-priority"><?php echo $this->render_quality_compliance_badge( $reminder_row['urgency'] ?? 'Normale' ); ?></td><td><?php echo esc_html( $reminder_row['owner'] ); ?></td><td><?php echo esc_html( $reminder_row['source'] ); ?></td><td><?php echo esc_html( $reminder_row['respondent'] ); ?></td><td class="acdc-quality-col-state"><div style="display:flex;flex-direction:column;gap:4px;"><?php echo $this->render_quality_compliance_badge( $reminder_row['reminder_type'] ?? 'Relance interne' ); ?><span class="acdc-muted"><?php echo esc_html( $reminder_row['reminder_scope'] ?? 'Suivi' ); ?></span></div></td><td><?php echo esc_html( $reminder_row['alert_type_label'] ?? 'Alerte qualité' ); ?></td><td><?php echo esc_html( $reminder_row['reason'] ); ?></td><td class="acdc-quality-col-action"><div class="acdc-quality-action-stack"><?php if ( $can_manage_quality && ! empty( $reminder_row['action_url'] ) ) : ?><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $reminder_row['action_url'] ); ?>"><?php echo esc_html( $reminder_row['action_label'] ?? 'Traiter' ); ?></a><?php if ( ! empty( $reminder_row['secondary_action_url'] ) && ! empty( $reminder_row['secondary_action_label'] ) ) : ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $reminder_row['secondary_action_url'] ); ?>"><?php echo esc_html( $reminder_row['secondary_action_label'] ); ?></a><?php endif; ?><?php else : ?><span class="acdc-muted"><?php echo esc_html( $reminder_row['action_label'] ?? 'Traiter' ); ?></span><?php endif; ?><?php $reminder_jump = '#acdc-quality-todo-now'; if ( ! empty( $reminder_row['reminder_type'] ) && false !== stripos( (string) $reminder_row['reminder_type'], 'clôture' ) ) { $reminder_jump = '#acdc-quality-closures-validation'; } ?><a class="acdc-quality-jump" href="<?php echo esc_url( $reminder_jump ); ?>">Ouvrir</a></div></td><td class="acdc-quality-col-state"><?php echo $this->render_quality_compliance_badge( $reminder_row['status'] ?? 'Relance interne' ); ?></td></tr><?php endforeach; ?><?php else : ?><tr><td colspan="10"><span class="acdc-muted">Aucune relance interne nécessaire pour le moment.</span></td></tr><?php endif; ?></tbody></table></div></div>
          </div>
          <div class="acdc-quality-panel acdc-quality-panel--focus" id="acdc-quality-closures-validation">
            <div class="acdc-quality-panel__head"><h3>Clôtures à valider</h3><p>Preuves déposées et traitements terminés avec recommandation explicite : valider, valider sous surveillance ou réouvrir.</p></div>
            <div class="acdc-quality-panel__body acdc-quality-tight"><div class="acdc-table-wrap"><table class="acdc-table acdc-quality-table-structured"><thead><tr><th>Source</th><th>Répondant</th><th>Pilote</th><th class="acdc-quality-col-state">Preuve</th><th class="acdc-quality-col-state">Clôture</th><th class="acdc-quality-col-state">Réouverture</th><th>Échéance</th><th class="acdc-quality-col-state">Recommandation</th><th class="acdc-quality-col-action">Validation</th></tr></thead><tbody><?php if ( ! empty( $quality_supervision['closure_validation_rows'] ) ) : ?><?php foreach ( $quality_supervision['closure_validation_rows'] as $closure_validation_row ) : ?><?php $closure_row_class = "acdc-quality-row-closure"; if ( ! empty( $closure_validation_row['proof_stage'] ) && false !== strpos( (string) $closure_validation_row['proof_stage'], "Insuffis" ) ) { $closure_row_class = "acdc-quality-row-proof"; } elseif ( ! empty( $closure_validation_row['recommendation'] ) && false !== strpos( (string) $closure_validation_row['recommendation'], "Réouvrir" ) ) { $closure_row_class = "acdc-quality-row-attention"; } ?><tr class="<?php echo esc_attr( $closure_row_class ); ?>"><td><?php echo esc_html( $closure_validation_row['source'] ); ?></td><td><?php echo esc_html( $closure_validation_row['respondent'] ); ?></td><td><?php echo esc_html( $closure_validation_row['owner'] ); ?></td><td class="acdc-quality-col-state"><?php echo $this->render_quality_compliance_badge( $closure_validation_row['proof_stage'] ); ?></td><td class="acdc-quality-col-state"><?php echo $this->render_quality_compliance_badge( $closure_validation_row['closure_status'] ?? 'À valider' ); ?></td><td class="acdc-quality-col-state"><?php echo $this->render_quality_compliance_badge( $closure_validation_row['reopen_status'] ?? 'Non' ); ?></td><td><?php echo esc_html( $closure_validation_row['due_label'] ?? '—' ); ?></td><td class="acdc-quality-col-state"><div style="display:flex;flex-direction:column;gap:4px;"><?php echo $this->render_quality_compliance_badge( $closure_validation_row['recommendation'] ?? 'Valider' ); ?><span class="acdc-muted"><?php echo esc_html( $closure_validation_row['recommendation_detail'] ?? '' ); ?></span></div></td><td class="acdc-inline-wrap acdc-quality-col-action"><?php if ( $can_manage_quality && ! empty( $closure_validation_row['validate_url'] ) ) : ?><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $closure_validation_row['validate_url'] ); ?>">Valider la clôture</a><?php endif; ?><?php if ( $can_manage_quality && ! empty( $closure_validation_row['reject_url'] ) ) : ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $closure_validation_row['reject_url'] ); ?>">Refuser / réouvrir</a><?php endif; ?><?php if ( ! $can_manage_quality ) : ?><span class="acdc-muted">Lecture seule</span><?php endif; ?></td></tr><?php endforeach; ?><?php else : ?><tr><td colspan="9"><span class="acdc-muted">Aucune clôture en attente de validation.</span></td></tr><?php endif; ?></tbody></table></div></div>
          </div>
          <div class="acdc-quality-panel" id="acdc-quality-priority-queue">
            <div class="acdc-quality-panel__head"><h3>File d’alertes priorisées</h3><p>Ordre de traitement métier avec pilote, scénario et échéance initiale.</p></div>
            <div class="acdc-quality-panel__body acdc-quality-tight"><div class="acdc-table-wrap"><table class="acdc-table acdc-quality-table-structured"><thead><tr><th class="acdc-quality-col-priority">Priorité</th><th>Source</th><th>Répondant</th><th>Scénario</th><th>Pilote</th><th>Échéance</th><th class="acdc-quality-col-state">Preuve</th></tr></thead><tbody><?php if ( ! empty( $quality_supervision['queue_rows'] ) ) : ?><?php foreach ( $quality_supervision['queue_rows'] as $queue_row ) : ?><?php $queue_row_class = ! empty( $queue_row['proof_status'] ) && false !== strpos( (string) $queue_row['proof_status'], "Manqu" ) ? "acdc-quality-row-proof" : "acdc-quality-row-attention"; ?><tr class="<?php echo esc_attr( $queue_row_class ); ?>"><td class="acdc-quality-col-priority"><span class="<?php echo esc_attr( $this->get_quality_compliance_status_badge_class( $queue_row['priority_auto'] ) ); ?>"><?php echo esc_html( $queue_row['priority'] ); ?></span></td><td><?php echo esc_html( $queue_row['source'] ); ?></td><td><?php echo esc_html( $queue_row['respondent'] ); ?></td><td><?php echo esc_html( $queue_row['scenario'] ); ?></td><td><?php echo esc_html( $queue_row['owner'] ); ?></td><td><?php echo esc_html( $queue_row['due_label'] ); ?></td><td class="acdc-quality-col-state"><?php echo $this->render_quality_compliance_badge( $queue_row['proof_status'] ); ?></td></tr><?php endforeach; ?><?php else : ?><tr><td colspan="7"><span class="acdc-muted">Aucune file priorisée disponible actuellement.</span></td></tr><?php endif; ?></tbody></table></div></div>
          </div>
          <div class="acdc-quality-panel acdc-quality-panel--focus" id="acdc-quality-arbitration">
            <div class="acdc-quality-panel__head"><h3>Vue d’arbitrage transverse</h3><p>Décisions à prendre par pilote quand la charge, les retards, les preuves ou les clôtures demandent un arbitrage explicite.</p></div>
            <div class="acdc-quality-panel__body acdc-quality-tight">
              <div class="acdc-quality-mini-grid" style="margin-bottom:12px;">
                <div class="acdc-quality-mini"><strong><?php echo esc_html( $quality_supervision['stats']['arbitrations'] ?? 0 ); ?></strong><span>Arbitrages ouverts</span></div>
                <div class="acdc-quality-mini"><strong><?php echo esc_html( $quality_supervision['stats']['closures_to_validate'] ?? 0 ); ?></strong><span>Clôtures à arbitrer</span></div>
                <div class="acdc-quality-mini"><strong><?php echo esc_html( $quality_supervision['stats']['late_actions'] ?? 0 ); ?></strong><span>Retards à résorber</span></div>
                <div class="acdc-quality-mini"><strong><?php echo esc_html( $quality_supervision['stats']['late_gaps'] ?? 0 ); ?></strong><span>Écarts en retard</span></div>
              </div>
              <div class="acdc-table-wrap"><table class="acdc-table acdc-quality-table-structured"><thead><tr><th>Pilote</th><th class="acdc-quality-col-state">Type</th><th class="acdc-quality-col-state">Focus</th><th>Détail</th><th>Retards</th><th>Preuves</th><th>Clôtures</th><th class="acdc-quality-col-state">Charge</th><th class="acdc-quality-col-action">Décision à engager</th></tr></thead><tbody><?php if ( ! empty( $quality_supervision['arbitration_rows'] ) ) : ?><?php foreach ( $quality_supervision['arbitration_rows'] as $arbitration_row ) : ?><?php $arbitration_row_class = "acdc-quality-row-attention"; $arbitration_label = (string) ( $arbitration_row['arbitration_label'] ?? "" ); if ( false !== strpos( $arbitration_label, "Urgence" ) || false !== strpos( $arbitration_label, "urgence" ) ) { $arbitration_row_class = "acdc-quality-row-critical"; } elseif ( false !== strpos( $arbitration_label, "clôture" ) || false !== strpos( $arbitration_label, "Clôture" ) ) { $arbitration_row_class = "acdc-quality-row-closure"; } elseif ( false !== strpos( $arbitration_label, "preuve" ) || false !== strpos( $arbitration_label, "Preuve" ) ) { $arbitration_row_class = "acdc-quality-row-proof"; } ?><tr class="<?php echo esc_attr( $arbitration_row_class ); ?>"><td><?php echo esc_html( $arbitration_row['owner'] ); ?></td><td class="acdc-quality-col-state"><?php echo $this->render_quality_compliance_badge( $arbitration_row['arbitration_label'] ?? 'Aucun arbitrage' ); ?></td><td class="acdc-quality-col-state"><?php echo $this->render_quality_compliance_badge( $arbitration_row['arbitration_focus'] ?? 'Aucun arbitrage' ); ?></td><td><?php echo esc_html( $arbitration_row['arbitration_detail'] ?? '' ); ?></td><td><?php echo esc_html( $arbitration_row['overdue'] ?? 0 ); ?></td><td><?php echo esc_html( $arbitration_row['proof_to_secure'] ?? 0 ); ?></td><td><?php echo esc_html( $arbitration_row['closures_to_validate'] ?? 0 ); ?></td><td class="acdc-quality-col-state"><?php echo $this->render_quality_compliance_badge( $arbitration_row['load_label'] ?? 'Stable' ); ?></td><td class="acdc-quality-col-action"><div class="acdc-quality-action-stack"><span><?php echo esc_html( $arbitration_row['arbitration_action'] ?? 'Aucun arbitrage' ); ?></span><?php $arbitration_jump = '#acdc-quality-owner-load'; $arbitration_focus_value = (string) ( $arbitration_row['arbitration_label'] ?? '' ); if ( false !== stripos( $arbitration_focus_value, 'urgence' ) ) { $arbitration_jump = '#acdc-quality-todo-now'; } elseif ( false !== stripos( $arbitration_focus_value, 'clôture' ) ) { $arbitration_jump = '#acdc-quality-closures-validation'; } elseif ( false !== stripos( $arbitration_focus_value, 'preuve' ) ) { $arbitration_jump = '#acdc-quality-reminders'; } ?><a class="acdc-quality-jump" href="<?php echo esc_url( $arbitration_jump ); ?>">Ouvrir</a></div></td></tr><?php endforeach; ?><?php else : ?><tr><td colspan="9"><span class="acdc-muted">Aucun arbitrage transverse prioritaire à afficher.</span></td></tr><?php endif; ?></tbody></table></div>
            </div>
          </div>

          <div class="acdc-quality-panel acdc-quality-panel--focus" id="acdc-quality-owner-load">
            <div class="acdc-quality-panel__head"><h3>Charge par pilote</h3><p>Répartition des alertes, actions ouvertes, clôtures et retards par responsable.</p></div>
            <div class="acdc-quality-panel__body acdc-quality-tight"><div class="acdc-table-wrap"><table class="acdc-table acdc-quality-table-structured"><thead><tr><th>Pilote</th><th>À faire maintenant</th><th>Alertes</th><th>Actions ouvertes</th><th>Clôtures à valider</th><th>Preuves à sécuriser</th><th class="acdc-quality-col-state">Retards</th><th class="acdc-quality-col-state">Arbitrage</th><th class="acdc-quality-col-state">Charge</th><th class="acdc-quality-col-action">Prochaine action</th></tr></thead><tbody><?php if ( ! empty( $quality_supervision['owner_rows'] ) ) : ?><?php foreach ( $quality_supervision['owner_rows'] as $owner_row ) : ?><?php $owner_row_class = ""; if ( ! empty( $owner_row['overdue'] ) ) { $owner_row_class = ( (int) $owner_row['overdue'] > 2 ) ? "acdc-quality-row-critical" : "acdc-quality-row-attention"; } elseif ( ! empty( $owner_row['proof_to_secure'] ) ) { $owner_row_class = "acdc-quality-row-proof"; } elseif ( ! empty( $owner_row['closures_to_validate'] ) ) { $owner_row_class = "acdc-quality-row-closure"; } ?><tr class="<?php echo esc_attr( $owner_row_class ); ?>"><td><?php echo esc_html( $owner_row['owner'] ); ?></td><td><?php echo esc_html( $owner_row['todo_now'] ?? 0 ); ?></td><td><?php echo esc_html( $owner_row['alerts'] ); ?></td><td><?php echo esc_html( $owner_row['actions_open'] ); ?></td><td><?php echo esc_html( $owner_row['closures_to_validate'] ?? 0 ); ?></td><td><?php echo esc_html( $owner_row['proof_to_secure'] ?? 0 ); ?></td><td class="acdc-quality-col-state"><div style="display:flex;flex-direction:column;gap:4px;"><strong><?php echo esc_html( $owner_row['overdue'] ); ?></strong><span class="acdc-muted"><?php echo esc_html( $owner_row['delay_label'] ?? 'Maîtrisé' ); ?></span></div></td><td class="acdc-quality-col-state"><?php echo $this->render_quality_compliance_badge( $owner_row['arbitration_label'] ?? 'Aucun arbitrage' ); ?></td><td class="acdc-quality-col-state"><?php echo $this->render_quality_compliance_badge( $owner_row['load_label'] ?? 'Stable' ); ?></td><td class="acdc-quality-col-action"><div class="acdc-quality-action-stack"><span><?php echo esc_html( $owner_row['next_action'] ?? 'Aucune action urgente' ); ?></span><?php $owner_jump = '#acdc-quality-todo-now'; $owner_next_action_value = (string) ( $owner_row['next_action'] ?? '' ); if ( false !== stripos( $owner_next_action_value, 'clôture' ) ) { $owner_jump = '#acdc-quality-closures-validation'; } elseif ( false !== stripos( $owner_next_action_value, 'preuve' ) ) { $owner_jump = '#acdc-quality-reminders'; } elseif ( false !== stripos( $owner_next_action_value, 'arbitrer' ) ) { $owner_jump = '#acdc-quality-arbitration'; } ?><a class="acdc-quality-jump" href="<?php echo esc_url( $owner_jump ); ?>">Ouvrir</a></div></td></tr><?php endforeach; ?><?php else : ?><tr><td colspan="10"><span class="acdc-muted">Aucune charge pilotée à afficher.</span></td></tr><?php endif; ?></tbody></table></div></div>
          </div>
          <div class="acdc-quality-panel acdc-quality-panel--secondary" id="acdc-quality-late-closures">
            <div class="acdc-quality-panel__head"><h3>Priorités de suivi</h3><p>Retards critiques, validations à engager et suivi prioritaire.</p></div>
            <div class="acdc-quality-panel__body acdc-quality-tight">
              <div class="acdc-table-wrap" style="margin-bottom:12px;"><table class="acdc-table"><thead><tr><th>Type</th><th>Objet</th><th>Pilote</th><th>Statut</th><th>Échéance</th><th>Priorité</th></tr></thead><tbody><?php if ( ! empty( $quality_supervision['late_action_rows'] ) ) : ?><?php foreach ( $quality_supervision['late_action_rows'] as $late_row ) : ?><tr><td><?php echo esc_html( $late_row['type'] ); ?></td><td><?php echo esc_html( $late_row['title'] ); ?></td><td><?php echo esc_html( $late_row['owner'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $late_row['status'] ); ?></td><td><?php echo esc_html( $late_row['due_label'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $late_row['priority'] ); ?></td></tr><?php endforeach; ?><?php elseif ( ! empty( $quality_supervision['late_gap_rows'] ) ) : ?><?php foreach ( $quality_supervision['late_gap_rows'] as $late_row ) : ?><tr><td><?php echo esc_html( $late_row['type'] ); ?></td><td><?php echo esc_html( $late_row['title'] ); ?></td><td><?php echo esc_html( $late_row['owner'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $late_row['status'] ); ?></td><td><?php echo esc_html( $late_row['due_label'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $late_row['priority'] ); ?></td></tr><?php endforeach; ?><?php else : ?><tr><td colspan="6"><span class="acdc-muted">Aucun retard détecté actuellement.</span></td></tr><?php endif; ?></tbody></table></div>
              <div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Type</th><th>Objectif</th><th>Pilote</th><th>Statut</th><th>Preuve</th></tr></thead><tbody><?php if ( ! empty( $quality_supervision['closure_rows'] ) ) : ?><?php foreach ( $quality_supervision['closure_rows'] as $closure_row ) : ?><tr><td><?php echo esc_html( $closure_row['type'] ); ?></td><td><?php echo esc_html( $closure_row['objective'] ); ?></td><td><?php echo esc_html( $closure_row['owner'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $closure_row['status'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $closure_row['proof_status'] ); ?></td></tr><?php endforeach; ?><?php else : ?><tr><td colspan="5"><span class="acdc-muted">Aucune clôture tracée pour le moment.</span></td></tr><?php endif; ?></tbody></table></div>
            </div>
          </div>
          <div class="acdc-quality-panel acdc-quality-panel--secondary" id="acdc-quality-traceability">
            <div class="acdc-quality-panel__head"><h3>Traçabilité de traitement</h3><p>Derniers événements consolidés pour prouver le traitement effectif.</p></div>
            <div class="acdc-quality-panel__body acdc-quality-tight"><div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Date</th><th>Périmètre</th><th>Événement</th><th>Statut</th><th>Détail</th></tr></thead><tbody><?php if ( ! empty( $quality_supervision['traceability_rows'] ) ) : ?><?php foreach ( $quality_supervision['traceability_rows'] as $trace_row ) : ?><tr><td><?php echo esc_html( ! empty( $trace_row['date'] ) ? mysql2date( 'j F Y H:i', $trace_row['date'] ) : '—' ); ?></td><td><?php echo esc_html( $trace_row['scope'] ); ?></td><td><?php echo esc_html( $trace_row['event'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $trace_row['status'] ); ?></td><td><?php echo esc_html( $trace_row['detail'] ); ?></td></tr><?php endforeach; ?><?php else : ?><tr><td colspan="5"><span class="acdc-muted">Aucune traçabilité consolidée pour le moment.</span></td></tr><?php endif; ?></tbody></table></div></div>
          </div>
        </section>
        <section class="acdc-quality-tab-panel <?php echo 'indicators' === $active_section ? 'is-active' : ''; ?>" data-acdc-quality-panel="indicators">
          <div class="acdc-quality-panel">
            <div class="acdc-quality-panel__head"><h3>Indicateurs</h3><p>Indicateurs structurés, niveau de preuve, couverture et impacts constatés.</p></div>
            <div class="acdc-quality-panel__body acdc-quality-tight">
              <div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Code</th><th>Indicateur</th><th>Mesure</th><th>Preuve</th><th>État</th></tr></thead><tbody>
              <?php foreach ( $indicator_rows as $row ) : ?><tr><td><?php echo esc_html( $row['code'] ); ?></td><td><?php echo esc_html( $row['label'] ); ?></td><td><?php echo esc_html( $row['measure'] ); ?></td><td><?php echo esc_html( $row['proof'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $row['state'] ); ?></td></tr><?php endforeach; ?>
              </tbody></table></div>
            </div>
          </div>
          <div class="acdc-quality-panel">
            <div class="acdc-quality-panel__head"><h3>Impacts constatés</h3><p>Lecture métier des écarts détectés et de leur effet sur la preuve audit.</p></div>
            <div class="acdc-quality-panel__body acdc-quality-tight"><div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Type</th><th>Écart</th><th>Niveau</th><th>Impact</th></tr></thead><tbody>
            <?php foreach ( $gap_rows as $row ) : ?><tr><td><?php echo esc_html( $row['type'] ); ?></td><td><?php echo esc_html( $row['gap'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $row['level'] ); ?></td><td><?php echo esc_html( $row['impact'] ); ?></td></tr><?php endforeach; ?>
            </tbody></table></div></div>
          </div>
        </section>
        <section class="acdc-quality-tab-panel <?php echo 'gaps' === $active_section ? 'is-active' : ''; ?>" data-acdc-quality-panel="gaps">
          <div class="acdc-quality-panel">
            <div class="acdc-quality-panel__head"><h3>Écarts qualité</h3><p>Registre lisible, dense et exploitable avec suivi et édition directe.</p></div>
            <div class="acdc-quality-panel__body acdc-quality-tight"><div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>ID</th><th>Type</th><th>Écart</th><th>Niveau</th><th>Statut</th><th>Priorité</th><th>Pilote</th><th>Échéance</th><th>Actions</th></tr></thead><tbody>
            <?php foreach ( $gap_register_rows as $row ) : ?><tr><td><?php echo esc_html( $row['id'] ); ?></td><td><?php echo esc_html( $row['type'] ); ?></td><td><?php echo esc_html( $row['gap'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $row['level'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $row['status'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $row['priority'] ); ?></td><td><?php echo esc_html( $row['owner'] ); ?></td><td><?php echo esc_html( $row['due_label'] ); ?></td><td class="acdc-inline-wrap"><?php if ( $can_manage_quality ) : ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->get_quality_record_edit_url( 'edit_gap', $row['id'], is_admin() ? 'admin' : 'front' ) ); ?>">Modifier</a><?php endif; ?><?php if ( $can_delete_quality && null !== $this->get_quality_gap_record_by_id( $row['id'] ) ) : ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->get_quality_delete_record_url( 'gap', $row['id'], is_admin() ? 'admin' : 'front' ) ); ?>">Supprimer</a><?php elseif ( null === $this->get_quality_gap_record_by_id( $row['id'] ) ) : ?><span class="acdc-muted">Automatique</span><?php else : ?><span class="acdc-muted">Lecture seule</span><?php endif; ?></td></tr><?php endforeach; ?>
            </tbody></table></div></div>
          </div>
          <div class="acdc-quality-grid-2">
            <div class="acdc-quality-panel">
              <div class="acdc-quality-panel__head"><h3><?php echo esc_html( $management_state['editing_gap'] ? 'Modifier un écart' : 'Créer un écart' ); ?></h3><p>Formulaire détaillé aligné avec le registre qualité.</p></div>
              <div class="acdc-quality-panel__body"><?php if ( $can_manage_quality ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acdc-form"><?php wp_nonce_field( 'acdc_save_quality_gap_record' ); ?><input type="hidden" name="action" value="acdc_save_quality_gap_record"><input type="hidden" name="qc_context" value="<?php echo esc_attr( is_admin() ? 'admin' : 'front' ); ?>"><input type="hidden" name="record_id" value="<?php echo esc_attr( $gap_form_defaults['id'] ); ?>"><div class="acdc-grid-2cols"><p><label>Type d’enquête</label><input type="text" name="type" value="<?php echo esc_attr( $gap_form_defaults['type'] ); ?>"></p><p><label>Niveau</label><input type="text" name="level" value="<?php echo esc_attr( $gap_form_defaults['level'] ); ?>"></p><p style="grid-column:1 / -1;"><label>Écart détecté</label><textarea name="gap" rows="3"><?php echo esc_textarea( $gap_form_defaults['gap'] ); ?></textarea></p><p><label>Statut</label><input type="text" name="status" value="<?php echo esc_attr( $gap_form_defaults['status'] ); ?>"></p><p><label>Priorité</label><input type="text" name="priority" value="<?php echo esc_attr( $gap_form_defaults['priority'] ); ?>"></p><p><label>Pilote</label><input type="text" name="owner" value="<?php echo esc_attr( $gap_form_defaults['owner'] ); ?>"></p><p><label>Échéance cible</label><input type="text" name="due_label" value="<?php echo esc_attr( $gap_form_defaults['due_label'] ); ?>"></p><p style="grid-column:1 / -1;"><label>Source de preuve</label><input type="text" name="source_proof" value="<?php echo esc_attr( $gap_form_defaults['source_proof'] ); ?>"></p><p style="grid-column:1 / -1;"><label>Impact</label><textarea name="impact" rows="3"><?php echo esc_textarea( $gap_form_defaults['impact'] ); ?></textarea></p><p style="grid-column:1 / -1;"><label>Prochaine étape</label><textarea name="next_step" rows="3"><?php echo esc_textarea( $gap_form_defaults['next_step'] ); ?></textarea></p></div><div class="acdc-inline-wrap"><button type="submit" class="acdc-button acdc-button-primary">Enregistrer l’écart</button><?php if ( $management_state['editing_gap'] ) : ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->get_quality_management_url() ); ?>">Fermer l’édition</a><?php endif; ?></div></form><?php else : ?><p class="acdc-muted">Profil en lecture seule sur le registre des écarts.</p><?php endif; ?></div>
            </div>
            <div class="acdc-quality-panel">
              <div class="acdc-quality-panel__head"><h3>Fiches de suivi</h3><p>Source de preuve, impact et traitement attendu pour chaque écart.</p></div>
              <div class="acdc-quality-panel__body acdc-quality-tight"><div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>ID</th><th>Source</th><th>Impact</th><th>Traitement</th></tr></thead><tbody><?php foreach ( $gap_register_rows as $row ) : ?><tr><td><?php echo esc_html( $row['id'] ); ?></td><td><?php echo esc_html( $row['source_proof'] ); ?></td><td><?php echo esc_html( $row['impact'] ); ?></td><td><?php echo esc_html( $row['next_step'] ); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
            </div>
          </div>
        </section>
        <section class="acdc-quality-tab-panel <?php echo 'plan' === $active_section ? 'is-active' : ''; ?>" data-acdc-quality-panel="plan">
          <div class="acdc-quality-panel">
            <div class="acdc-quality-panel__head"><h3>Plan d’action</h3><p>Suivi dense des objectifs, responsables, échéances et progression.</p></div>
            <div class="acdc-quality-panel__body acdc-quality-tight"><div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>ID</th><th>Type</th><th>Objectif</th><th>Pilote</th><th>Échéance</th><th>Statut</th><th>Progression</th><th>Actions</th></tr></thead><tbody>
            <?php foreach ( $full_plan_rows as $row ) : $progress = $this->get_quality_compliance_progress_percent( $row['status'] ); ?><tr><td><?php echo esc_html( $row['id'] ); ?></td><td><?php echo esc_html( $row['type'] ); ?></td><td><?php echo esc_html( $row['objective'] ); ?></td><td><?php echo esc_html( $row['owner'] ); ?></td><td><?php echo esc_html( $row['due_label'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $row['status'] ); ?></td><td><div style="min-width:150px;"><div style="height:8px;background:#edf1f5;border-radius:999px;overflow:hidden;"><div style="height:8px;width:<?php echo esc_attr( $progress ); ?>%;background:#c5a253;"></div></div><small style="display:block;margin-top:6px;color:var(--acdc-text-muted);"><?php echo esc_html( $progress ); ?> %</small></div></td><td class="acdc-inline-wrap"><?php if ( $can_manage_quality ) : ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->get_quality_record_edit_url( 'edit_action', $row['id'], is_admin() ? 'admin' : 'front' ) ); ?>">Modifier</a><?php endif; ?><?php if ( $can_delete_quality && null !== $this->get_quality_action_record_by_id( $row['id'] ) ) : ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->get_quality_delete_record_url( 'action', $row['id'], is_admin() ? 'admin' : 'front' ) ); ?>">Supprimer</a><?php elseif ( null === $this->get_quality_action_record_by_id( $row['id'] ) ) : ?><span class="acdc-muted">Automatique</span><?php else : ?><span class="acdc-muted">Lecture seule</span><?php endif; ?></td></tr><?php endforeach; ?>
            </tbody></table></div></div>
          </div>
          <div class="acdc-quality-grid-2">
            <div class="acdc-quality-panel">
              <div class="acdc-quality-panel__head"><h3><?php echo esc_html( $management_state['editing_action'] ? 'Modifier une action qualité' : 'Créer une action qualité' ); ?></h3><p>Formulaire détaillé avec priorité, preuve, échéance et critère de succès.</p></div>
              <div class="acdc-quality-panel__body"><?php if ( $can_manage_quality ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acdc-form"><?php wp_nonce_field( 'acdc_save_quality_action_record' ); ?><input type="hidden" name="action" value="acdc_save_quality_action_record"><input type="hidden" name="qc_context" value="<?php echo esc_attr( is_admin() ? 'admin' : 'front' ); ?>"><input type="hidden" name="record_id" value="<?php echo esc_attr( $action_form_defaults['id'] ); ?>"><div class="acdc-grid-2cols"><p><label>Type</label><input type="text" name="type" value="<?php echo esc_attr( $action_form_defaults['type'] ); ?>"></p><p><label>Priorité</label><input type="text" name="priority" value="<?php echo esc_attr( $action_form_defaults['priority'] ); ?>"></p><p style="grid-column:1 / -1;"><label>Objectif</label><textarea name="objective" rows="3"><?php echo esc_textarea( $action_form_defaults['objective'] ); ?></textarea></p><p><label>Statut</label><input type="text" name="status" value="<?php echo esc_attr( $action_form_defaults['status'] ); ?>"></p><p><label>Pilote</label><input type="text" name="owner" value="<?php echo esc_attr( $action_form_defaults['owner'] ); ?>"></p><p><label>Échéance</label><input type="text" name="due_label" value="<?php echo esc_attr( $action_form_defaults['due_label'] ); ?>"></p><p><label>Statut de preuve</label><input type="text" name="proof_status" value="<?php echo esc_attr( $action_form_defaults['proof_status'] ); ?>"></p><p><label>Actions ouvertes liées</label><input type="number" min="0" name="actions_open" value="<?php echo esc_attr( $action_form_defaults['actions_open'] ); ?>"></p><p style="grid-column:1 / -1;"><label>Évidence</label><textarea name="evidence" rows="3"><?php echo esc_textarea( $action_form_defaults['evidence'] ); ?></textarea></p><p style="grid-column:1 / -1;"><label>Critère de succès</label><textarea name="success" rows="3"><?php echo esc_textarea( $action_form_defaults['success'] ); ?></textarea></p></div><div class="acdc-inline-wrap"><button type="submit" class="acdc-button acdc-button-primary">Enregistrer l’action qualité</button><?php if ( $management_state['editing_action'] ) : ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->get_quality_management_url() ); ?>">Fermer l’édition</a><?php endif; ?></div></form><?php else : ?><p class="acdc-muted">Profil en lecture seule sur le plan d’action qualité.</p><?php endif; ?></div>
            </div>
            <div class="acdc-quality-panel">
              <div class="acdc-quality-panel__head"><h3>Plan synthétique lié aux enquêtes</h3><p>Objectifs prioritaires, preuve et nombre d’actions ouvertes par type.</p></div>
              <div class="acdc-quality-panel__body acdc-quality-tight"><div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Type</th><th>Objectif</th><th>Priorité</th><th>Preuve</th><th>Ouvertes</th><th>Pilote</th></tr></thead><tbody><?php foreach ( $plan_rows as $row ) : ?><tr><td><?php echo esc_html( $row['type'] ); ?></td><td><?php echo esc_html( $row['objective'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $row['priority'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $row['proof_status'] ); ?></td><td><?php echo esc_html( $row['actions_open'] ); ?></td><td><?php echo esc_html( $row['owner'] ); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
            </div>
          </div>
        </section>
        <section class="acdc-quality-tab-panel <?php echo 'journal' === $active_section ? 'is-active' : ''; ?>" data-acdc-quality-panel="journal">
          <div class="acdc-quality-grid-2">
            <div class="acdc-quality-panel">
              <div class="acdc-quality-panel__head"><h3>Journal d’audit</h3><p>Historique consolidé des événements qualité, écarts suivis et actions menées.</p></div>
              <div class="acdc-quality-panel__body acdc-quality-tight"><div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Date</th><th>Périmètre</th><th>Événement</th><th>Détail</th><th>Statut</th></tr></thead><tbody><?php foreach ( $audit_log_rows as $row ) : ?><tr><td><?php echo esc_html( ! empty( $row['date'] ) ? mysql2date( 'j F Y H:i', $row['date'] ) : '—' ); ?></td><td><?php echo esc_html( $row['scope'] ); ?></td><td><?php echo esc_html( $row['event'] ); ?></td><td><?php echo esc_html( $row['detail'] ); ?></td><td><?php echo $this->render_quality_compliance_badge( $row['status'] ); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
            </div>
            <div class="acdc-quality-panel">
              <div class="acdc-quality-panel__head"><h3>Exploitation</h3><p>Lecture rapide des derniers événements et de l’actualisation du module.</p></div>
              <div class="acdc-quality-panel__body"><div class="acdc-quality-mini-grid"><div class="acdc-quality-mini"><strong><?php echo esc_html( count( $audit_log_rows ) ); ?></strong><span>Événements visibles</span></div><div class="acdc-quality-mini"><strong><?php echo esc_html( $quality_closed_actions ); ?></strong><span>Actions clôturées</span></div><div class="acdc-quality-mini"><strong><?php echo esc_html( $quality_open_actions ); ?></strong><span>Actions ouvertes</span></div><div class="acdc-quality-mini"><strong><?php echo esc_html( $quality_overdue_actions ); ?></strong><span>Retards</span></div></div></div>
            </div>
          </div>
        </section>
      </div>
    </section>
    <?php
  }

  private function render_front_improvement_pilotage_tab() {
    $this->require_front_manager( 'improvement_pilotage' );
    nocache_headers();
    $this->render_quality_compliance_foundations_dashboard();
  }

  private function get_questionnaire_results_terms( $source_type ) {
    $source_type = (string) $source_type;
    $defaults = array(
      'session' => 'Session',
      'sessions' => 'Sessions',
      'participant' => 'Participant',
      'participants' => 'Participants',
      'responses' => 'Réponses',
      'actions' => 'Actions d’amélioration',
      'global_view_title' => 'Résultats globaux',
      'global_view_description' => 'Vue consolidée des réponses, alertes et actions à traiter.',
      'session_view_title' => 'Détail de session',
      'session_view_description' => 'Analyse détaillée des réponses, des questions et des alertes pour cette session.',
      'participant_view_title' => 'Vue individuelle',
      'participant_view_description' => 'Réponses détaillées, traçabilité et indicateurs individuels.',
      'question_weakness_title' => 'Questions les plus faibles',
      'question_weakness_description' => 'Repérez immédiatement les questions les moins bien notées pour déclencher une action.',
      'verbatim_title' => 'Verbatims récents',
      'verbatim_description' => 'Commentaires libres récemment enregistrés dans ce contexte.',
      'improvement_button' => 'Créer une action d’amélioration',
      'detail_button' => 'Voir le détail',
      'session_column' => 'Session',
      'participant_column' => 'Participant',
    );

    if ( 'mid_survey' === $source_type ) {
      return array_merge( $defaults, array(
        'session' => 'Envoi',
        'sessions' => 'Envois',
        'participant' => 'Destinataire',
        'participants' => 'Destinataires',
        'actions' => 'Actions d’amélioration',
        'global_view_title' => 'Résultats globaux des enquêtes intermédiaires',
        'global_view_description' => 'Vue consolidée des envois, réponses, alertes basses et actions d’amélioration du module intermédiaire.',
        'session_view_title' => 'Détail d’envoi',
        'session_view_description' => 'Analyse détaillée des réponses, des questions faibles et des alertes pour cet envoi intermédiaire.',
        'participant_view_title' => 'Réponse individuelle',
        'participant_view_description' => 'Lecture complète de la réponse d’un destinataire avec relances, alertes et action d’amélioration.',
        'question_weakness_title' => 'Questions les plus faibles de l’envoi',
        'question_weakness_description' => 'Repérez immédiatement les notes basses et les points sensibles pour engager une amélioration.',
        'verbatim_title' => 'Verbatims récents des enquêtes intermédiaires',
        'verbatim_description' => 'Commentaires libres récemment saisis par les apprenants sur ce module.',
        'improvement_button' => 'Créer une action d’amélioration',
        'detail_button' => 'Voir la réponse',
        'session_column' => 'Envoi',
        'participant_column' => 'Destinataire',
      ) );
    }

    if ( 'hot_survey' === $source_type ) {
      return array_merge( $defaults, array(
        'session' => 'Envoi',
        'sessions' => 'Envois',
        'participant' => 'Répondant',
        'participants' => 'Répondants',
        'actions' => 'Actions d’amélioration',
        'global_view_title' => 'Résultats globaux des enquêtes à chaud',
        'global_view_description' => 'Vue consolidée des envois à chaud, des réponses de satisfaction immédiate, des alertes et des actions d’amélioration.',
        'session_view_title' => 'Détail d’envoi à chaud',
        'session_view_description' => 'Analyse détaillée des réponses, des notes basses et des verbatims pour cet envoi à chaud.',
        'participant_view_title' => 'Réponse individuelle à chaud',
        'participant_view_description' => 'Lecture complète de la réponse d’un apprenant avec score, alertes et action d’amélioration.',
        'question_weakness_title' => 'Questions les plus faibles à chaud',
        'question_weakness_description' => 'Repérez immédiatement les points de satisfaction les moins bien notés en fin de formation.',
        'verbatim_title' => 'Verbatims récents des enquêtes à chaud',
        'verbatim_description' => 'Commentaires libres récemment saisis par les apprenants juste après la formation.',
        'improvement_button' => 'Créer une action d’amélioration',
        'detail_button' => 'Voir la réponse',
        'session_column' => 'Envoi',
        'participant_column' => 'Répondant',
      ) );
    }

    if ( 'cold_survey' === $source_type ) {
      return array_merge( $defaults, array(
        'session' => 'Envoi',
        'sessions' => 'Envois',
        'participant' => 'Répondant',
        'participants' => 'Répondants',
        'actions' => 'Actions d’amélioration',
        'global_view_title' => 'Résultats globaux des enquêtes à froid',
        'global_view_description' => 'Vue consolidée des envois à froid, des réponses différées, des alertes et des actions d’amélioration.',
        'session_view_title' => 'Détail d’envoi à froid',
        'session_view_description' => 'Analyse détaillée des réponses, des signaux faibles et des verbatims pour cet envoi à froid.',
        'participant_view_title' => 'Réponse individuelle à froid',
        'participant_view_description' => 'Lecture complète de la réponse d’un apprenant avec score, alertes et action d’amélioration.',
        'question_weakness_title' => 'Questions les plus faibles à froid',
        'question_weakness_description' => 'Repérez immédiatement les points d’utilité perçue ou de satisfaction différée les moins bien notés.',
        'verbatim_title' => 'Verbatims récents des enquêtes à froid',
        'verbatim_description' => 'Commentaires libres récemment saisis par les apprenants après retour à froid.',
        'improvement_button' => 'Créer une action d’amélioration',
        'detail_button' => 'Voir la réponse',
        'session_column' => 'Envoi',
        'participant_column' => 'Répondant',
      ) );
    }

    return $defaults;
  }

  private function get_questionnaire_session_weakness_rows( $session_id, $source, $limit = 5 ) {
    $rows = $this->get_questionnaire_session_question_rows( $session_id, $source );
    if ( empty( $rows ) || ! is_array( $rows ) ) {
      return array();
    }

    foreach ( $rows as &$row ) {
      $label = '';
      if ( isset( $row['question_label'] ) && '' !== (string) $row['question_label'] ) {
        $label = (string) $row['question_label'];
      } elseif ( isset( $row['label'] ) && '' !== (string) $row['label'] ) {
        $label = (string) $row['label'];
      }
      $row['question_label_resolved'] = $label;
      $score_value = null;
      if ( isset( $row['average_score'] ) && null !== $row['average_score'] && '' !== (string) $row['average_score'] ) {
        $score_value = (float) $row['average_score'];
      } elseif ( isset( $row['success_rate'] ) && null !== $row['success_rate'] && '' !== (string) $row['success_rate'] ) {
        $score_value = (float) $row['success_rate'];
      } elseif ( isset( $row['response_rate'] ) && null !== $row['response_rate'] && '' !== (string) $row['response_rate'] ) {
        $score_value = (float) $row['response_rate'];
      }
      $row['metric_value'] = $score_value;
      $row['responses_resolved'] = isset( $row['responses'] ) ? (int) $row['responses'] : ( isset( $row['total_answers'] ) ? (int) $row['total_answers'] : 0 );
      $row['question_type_resolved'] = isset( $row['question_type'] ) && '' !== (string) $row['question_type'] ? (string) $row['question_type'] : ( isset( $row['type'] ) ? (string) $row['type'] : '—' );
    }
    unset( $row );

    usort( $rows, function( $a, $b ) {
      $a_metric = isset( $a['metric_value'] ) && null !== $a['metric_value'] ? (float) $a['metric_value'] : 999999;
      $b_metric = isset( $b['metric_value'] ) && null !== $b['metric_value'] ? (float) $b['metric_value'] : 999999;
      if ( $a_metric === $b_metric ) {
        $a_responses = isset( $a['responses_resolved'] ) ? (int) $a['responses_resolved'] : 0;
        $b_responses = isset( $b['responses_resolved'] ) ? (int) $b['responses_resolved'] : 0;
        if ( $a_responses === $b_responses ) {
          return 0;
        }
        return ( $a_responses < $b_responses ) ? 1 : -1;
      }
      return ( $a_metric < $b_metric ) ? -1 : 1;
    } );

    $limit = max( 1, absint( $limit ) );
    return array_slice( $rows, 0, $limit );
  }


  private function get_questionnaire_participant_alert_answer_count( $participant_answers ) {
    if ( empty( $participant_answers ) || ! is_array( $participant_answers ) ) {
      return 0;
    }
    $count = 0;
    foreach ( $participant_answers as $answer ) {
      if ( ! empty( $answer->is_alert ) ) {
        $count++;
      }
    }
    return $count;
  }

  private function get_questionnaire_participant_verbatim_count( $participant_answers ) {
    if ( empty( $participant_answers ) || ! is_array( $participant_answers ) ) {
      return 0;
    }
    $count = 0;
    foreach ( $participant_answers as $answer ) {
      $value = isset( $answer->answer_value ) ? trim( (string) $answer->answer_value ) : '';
      $type  = isset( $answer->answer_type ) ? (string) $answer->answer_type : '';
      if ( '' === $value ) {
        continue;
      }
      if ( in_array( $type, array( 'textarea', 'text', 'champ_texte', 'texte', 'comment', 'commentaire', 'long_text', 'short_text' ), true ) || strlen( $value ) >= 20 ) {
        $count++;
      }
    }
    return $count;
  }

  private function get_questionnaire_participant_last_answered_at( $participant_answers ) {
    if ( empty( $participant_answers ) || ! is_array( $participant_answers ) ) {
      return '';
    }
    $last = '';
    foreach ( $participant_answers as $answer ) {
      $answered_at = isset( $answer->answered_at ) ? (string) $answer->answered_at : '';
      if ( '' !== $answered_at && ( '' === $last || strtotime( $answered_at ) > strtotime( $last ) ) ) {
        $last = $answered_at;
      }
    }
    return $last;
  }

  private function get_questionnaire_session_verbatim_rows( $session_id, $limit = 8 ) {
    global $wpdb;
    $session_id = absint( $session_id );
    $limit = max( 1, absint( $limit ) );
    if ( ! $session_id || empty( $this->questionnaire_answer_table ) || empty( $this->questionnaire_participant_table ) ) {
      return array();
    }

    $sql = $wpdb->prepare(
      "SELECT a.answer_value, a.answer_type, a.question_label, a.question_index, a.answered_at, p.id AS participant_id, p.pseudo, p.first_name, p.last_name
       FROM {$this->questionnaire_answer_table} a
       LEFT JOIN {$this->questionnaire_participant_table} p ON p.id = a.participant_id
       WHERE a.session_id = %d
         AND a.answer_value IS NOT NULL
         AND TRIM(a.answer_value) <> ''
         AND (a.answer_type IN ('textarea','text','champ_texte','texte','comment','commentaire','long_text','short_text')
              OR CHAR_LENGTH(TRIM(a.answer_value)) >= 20)
       ORDER BY a.answered_at DESC, a.id DESC
       LIMIT %d",
      $session_id,
      $limit
    );

    return $wpdb->get_results( $sql );
  }

  private function render_front_questionnaire_results_tab() {
    $current_source_type  = isset( $_GET['source_type'] ) ? sanitize_text_field( wp_unslash( $_GET['source_type'] ) ) : '';
    $current_source_id    = isset( $_GET['source_id'] ) ? absint( wp_unslash( $_GET['source_id'] ) ) : 0;
    $session_focus_id     = isset( $_GET['session_id'] ) ? absint( wp_unslash( $_GET['session_id'] ) ) : 0;
    $participant_focus_id = isset( $_GET['participant_id'] ) ? absint( wp_unslash( $_GET['participant_id'] ) ) : 0;
    $action_scope         = isset( $_GET['action_scope'] ) ? sanitize_key( wp_unslash( $_GET['action_scope'] ) ) : '';
    $priority_level       = isset( $_GET['priority_level'] ) ? sanitize_key( wp_unslash( $_GET['priority_level'] ) ) : '';
    $dashboard_focus      = ! empty( $_GET['dashboard_focus'] );

    $action_scope_labels = $this->get_questionnaire_action_scope_labels();
    $priority_levels = $this->get_questionnaire_action_priority_levels();
    if ( '' !== $action_scope && ! isset( $action_scope_labels[ $action_scope ] ) ) {
      $action_scope = '';
    }
    if ( '' !== $priority_level && ! isset( $priority_levels[ $priority_level ] ) ) {
      $priority_level = '';
    }

    $context         = $this->get_questionnaire_context_config( $current_source_type );
    $terms           = $this->get_questionnaire_results_terms( $current_source_type );
    $stat_labels     = array(
      'tracked'       => $terms['sessions'] . ' suivis',
      'participants'  => $terms['participants'] . ' cumulés',
      'responses'     => $terms['responses'] . ' enregistrées',
      'response_rate' => 'Taux de réponse global',
      'finished'      => 'Sessions terminées',
      'alerts'        => 'Alertes détectées',
      'average'       => 'Score moyen global',
    );
    if ( 'mid_survey' === $current_source_type ) {
      $stat_labels = array(
        'tracked'       => 'Envois intermédiaires suivis',
        'participants'  => 'Destinataires cumulés',
        'responses'     => 'Réponses intermédiaires',
        'response_rate' => 'Taux de retour intermédiaire',
        'finished'      => 'Envois terminés',
        'alerts'        => 'Alertes basses détectées',
        'average'       => 'Score moyen intermédiaire',
      );
    } elseif ( 'hot_survey' === $current_source_type ) {
      $stat_labels = array(
        'tracked'       => 'Envois à chaud suivis',
        'participants'  => 'Répondants ciblés',
        'responses'     => 'Réponses à chaud',
        'response_rate' => 'Taux de retour à chaud',
        'finished'      => 'Envois terminés',
        'alerts'        => 'Notes basses détectées',
        'average'       => 'Satisfaction moyenne à chaud',
      );
    } elseif ( 'cold_survey' === $current_source_type ) {
      $stat_labels = array(
        'tracked'       => 'Envois à froid suivis',
        'participants'  => 'Répondants ciblés',
        'responses'     => 'Réponses à froid',
        'response_rate' => 'Taux de retour à froid',
        'finished'      => 'Envois terminés',
        'alerts'        => 'Signaux faibles détectés',
        'average'       => 'Utilité perçue moyenne',
      );
    }
    $session_filters = array_filter( array( 'source_type' => $current_source_type, 'source_id' => $current_source_id ) );
    $action_filters  = $session_filters;
    if ( '' !== $priority_level ) {
      $action_filters['priority_level'] = $priority_level;
    }
    $rows            = $this->get_questionnaire_session_summary_rows( $session_filters );
    $action_counts   = $this->get_questionnaire_session_action_counts_map( $action_filters );

    if ( '' !== $action_scope || '' !== $priority_level ) {
      $rows = array_values( array_filter( $rows, function( $row ) use ( $action_scope, $action_counts ) {
        if ( empty( $row['session'] ) || ! is_object( $row['session'] ) ) {
          return false;
        }
        $session_id = (int) $row['session']->id;
        $counts = isset( $action_counts[ $session_id ] ) ? $action_counts[ $session_id ] : array();
        if ( '' !== $action_scope ) {
          return ! empty( $counts[ $action_scope ] );
        }
        return ! empty( $counts['total'] );
      } ) );
    }

    $stats             = $this->get_questionnaire_session_stats_from_rows( $rows );
    $action_stats      = $this->get_questionnaire_action_context_stats( $action_filters );
    $session_focus     = $session_focus_id ? $this->get_questionnaire_session( $session_focus_id ) : null;
    $participant_focus = $participant_focus_id ? $this->get_questionnaire_session_participant( $participant_focus_id ) : null;

    if ( $participant_focus && $session_focus && (int) $participant_focus->session_id !== (int) $session_focus->id ) {
      $participant_focus = null;
    }

    $treatment_rows = array();
    if ( $dashboard_focus || '' !== $action_scope || '' !== $priority_level ) {
      $treatment_rows = $this->get_questionnaire_action_treatment_rows(
        array_merge( $action_filters, array( 'action_scope' => '' !== $action_scope ? $action_scope : 'a_traiter' ) ),
        24
      );
    }

    $filter_form_url = is_admin() ? admin_url( 'admin.php' ) : $this->portal_page_url( array( 'tab' => 'questionnaire_results' ) );
    $reset_args = array();
    if ( '' !== $current_source_type ) {
      $reset_args['source_type'] = $current_source_type;
    }
    if ( $current_source_id > 0 ) {
      $reset_args['source_id'] = $current_source_id;
    }
    $reset_url = is_admin()
      ? $this->admin_tab_url( 'questionnaire_results', $reset_args )
      : $this->portal_page_url( array_merge( array( 'tab' => 'questionnaire_results' ), $reset_args ) );

    $detail_extra = array();
    if ( '' !== $action_scope ) {
      $detail_extra['action_scope'] = $action_scope;
    }
    if ( '' !== $priority_level ) {
      $detail_extra['priority_level'] = $priority_level;
    }
    if ( $dashboard_focus ) {
      $detail_extra['dashboard_focus'] = '1';
    }
    ?>
    <section class="acdc-section-head"><div><h2><?php echo esc_html( $context['result_title'] ); ?></h2><p><?php echo esc_html( $context['result_description'] ); ?></p></div>
      <div class="acdc-inline-wrap">
        <?php if ( '' !== $current_source_type ) : ?>
          <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->get_questionnaire_context_export_url( 'csv', $current_source_type, $current_source_id ) ); ?>">Exporter CSV</a>
          <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->get_questionnaire_context_export_url( 'excel', $current_source_type, $current_source_id ) ); ?>">Exporter Excel</a>
          <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->get_questionnaire_context_export_url( 'pdf', $current_source_type, $current_source_id ) ); ?>">Export imprimable</a>
        <?php endif; ?>
      </div>
    </section>

    <div class="acdc-panel acdc-mb-18">
      <form method="get" action="<?php echo esc_url( $filter_form_url ); ?>" class="acdc-filters-bar">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-questionnaire-results"><?php else : ?><input type="hidden" name="tab" value="questionnaire_results"><?php endif; ?>
        <?php if ( '' !== $current_source_type ) : ?><input type="hidden" name="source_type" value="<?php echo esc_attr( $current_source_type ); ?>"><?php endif; ?>
        <?php if ( $current_source_id > 0 ) : ?><input type="hidden" name="source_id" value="<?php echo (int) $current_source_id; ?>"><?php endif; ?>
        <?php if ( $dashboard_focus ) : ?><input type="hidden" name="dashboard_focus" value="1"><?php endif; ?>
        <select name="action_scope">
          <option value="">Toutes les actions</option>
          <?php foreach ( $action_scope_labels as $scope_key => $scope_label ) : ?>
            <option value="<?php echo esc_attr( $scope_key ); ?>" <?php selected( $action_scope, $scope_key ); ?>><?php echo esc_html( $scope_label ); ?></option>
          <?php endforeach; ?>
        </select>
        <select name="priority_level">
          <option value="">Toutes les priorités</option>
          <?php foreach ( $priority_levels as $priority_key => $priority_label ) : ?>
            <option value="<?php echo esc_attr( $priority_key ); ?>" <?php selected( $priority_level, $priority_key ); ?>><?php echo esc_html( 'Priorité ' . strtolower( $priority_label ) ); ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="acdc-button acdc-button-soft">Filtrer</button>
        <?php if ( '' !== $action_scope || '' !== $priority_level || $dashboard_focus ) : ?>
          <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $reset_url ); ?>">Réinitialiser</a>
        <?php endif; ?>
      </form>
      <?php if ( '' !== $action_scope || '' !== $priority_level || $dashboard_focus ) : ?>
        <p class="acdc-muted-note" style="margin:12px 0 0;">
          <strong>Vue de traitement :</strong>
          <?php echo esc_html( '' !== $action_scope && isset( $action_scope_labels[ $action_scope ] ) ? $action_scope_labels[ $action_scope ] : 'À traiter' ); ?>
          <?php if ( '' !== $priority_level && isset( $priority_levels[ $priority_level ] ) ) : ?> · Priorité <?php echo esc_html( strtolower( $priority_levels[ $priority_level ] ) ); ?><?php endif; ?>
          <?php if ( '' !== $current_source_type ) : ?> · <?php echo esc_html( $context['label_singular'] ); ?><?php endif; ?>
        </p>
      <?php endif; ?>
    </div>

    <div class="acdc-grid-4cols acdc-mb-18" style="display:grid;gap:18px">
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $stats['total'] ); ?></div><div class="acdc-stat-label"><?php echo esc_html( $stat_labels['tracked'] ); ?></div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $stats['participants'] ); ?></div><div class="acdc-stat-label"><?php echo esc_html( $stat_labels['participants'] ); ?></div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $stats['responses'] ); ?></div><div class="acdc-stat-label"><?php echo esc_html( $stat_labels['responses'] ); ?></div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( null !== $stats['response_rate'] ? $stats['response_rate'] . ' %' : '—' ); ?></div><div class="acdc-stat-label"><?php echo esc_html( $stat_labels['response_rate'] ); ?></div></div>
    </div>
    <div class="acdc-grid-4cols acdc-mb-18" style="display:grid;gap:18px">
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $stats['finished'] ); ?></div><div class="acdc-stat-label"><?php echo esc_html( $stat_labels['finished'] ); ?></div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $stats['alerts'] ); ?></div><div class="acdc-stat-label"><?php echo esc_html( $stat_labels['alerts'] ); ?></div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( null !== $stats['average_score'] ? $stats['average_score'] : '—' ); ?></div><div class="acdc-stat-label"><?php echo esc_html( $stat_labels['average'] ); ?></div></div>
    </div>

    <div class="acdc-grid-4cols acdc-mb-18" style="display:grid;gap:18px">
      <div class="acdc-panel acdc-centered-stat acdc-priority-stat acdc-priority-stat-haute"><div class="acdc-stat-number"><?php echo esc_html( $action_stats['priority_high'] ); ?></div><div class="acdc-stat-label">Priorité haute</div></div>
      <div class="acdc-panel acdc-centered-stat acdc-priority-stat acdc-priority-stat-moyenne"><div class="acdc-stat-number"><?php echo esc_html( $action_stats['priority_medium'] ); ?></div><div class="acdc-stat-label">Priorité moyenne</div></div>
      <div class="acdc-panel acdc-centered-stat acdc-priority-stat acdc-priority-stat-basse"><div class="acdc-stat-number"><?php echo esc_html( $action_stats['priority_low'] ); ?></div><div class="acdc-stat-label">Priorité basse</div></div>
    </div>

    <div class="acdc-grid-4cols acdc-mb-18" style="display:grid;gap:18px">
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $action_stats['a_traiter'] ); ?></div><div class="acdc-stat-label"><?php echo esc_html( $terms['actions'] . ' à traiter' ); ?></div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $action_stats['ouverte'] ); ?></div><div class="acdc-stat-label">Ouvertes</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $action_stats['en_cours'] ); ?></div><div class="acdc-stat-label">En cours</div></div>
      <div class="acdc-panel acdc-centered-stat acdc-urgency-stat acdc-urgency-stat-overdue"><div class="acdc-stat-number"><?php echo esc_html( $action_stats['en_retard'] ); ?></div><div class="acdc-stat-label">En retard</div></div>
    </div>
    <div class="acdc-grid-4cols acdc-mb-18" style="display:grid;gap:18px">
      <div class="acdc-panel acdc-centered-stat acdc-urgency-stat acdc-urgency-stat-today"><div class="acdc-stat-number"><?php echo esc_html( $action_stats['aujourd_hui'] ); ?></div><div class="acdc-stat-label">Échéance aujourd’hui</div></div>
      <div class="acdc-panel acdc-centered-stat acdc-urgency-stat acdc-urgency-stat-soon"><div class="acdc-stat-number"><?php echo esc_html( $action_stats['echeance_proche'] ); ?></div><div class="acdc-stat-label">Échéance proche</div></div>
      <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $action_stats['traitee'] ); ?></div><div class="acdc-stat-label">Traitées</div></div>
    </div>

    <?php if ( $dashboard_focus || '' !== $action_scope || '' !== $priority_level ) : ?>
      <div class="acdc-panel acdc-mb-18">
        <div class="acdc-section-head">
          <div>
            <h3><?php echo esc_html( $terms['actions'] . ' à traiter' ); ?></h3>
            <p>Vue directe des actions ouvertes ou en cours pour le type d’enquête sélectionné.</p>
          </div>
        </div>
        <div class="acdc-table-wrap">
          <table class="acdc-table">
            <thead>
              <tr>
                <th>État</th>
                <th>Action</th>
                <th>Priorité</th>
                <th>Contexte</th>
                <th>Responsable</th>
                <th>Échéance</th>
                <th>Ouvrir</th>
              </tr>
            </thead>
            <tbody>
              <?php if ( ! empty( $treatment_rows ) ) : foreach ( $treatment_rows as $row ) :
                $badge = $this->get_questionnaire_action_followup_badge( $row );
                $priority_badge = ! empty( $row->priority_badge ) ? $row->priority_badge : $this->get_questionnaire_action_priority_badge( ! empty( $row->priority_level ) ? (string) $row->priority_level : 'Moyenne' );
                $source_title = ! empty( $row->source['title'] ) ? (string) $row->source['title'] : ( ! empty( $row->session_title ) ? (string) $row->session_title : 'Questionnaire' );
                $context_bits = array( $source_title );
                if ( ! empty( $row->participant_label ) && '—' !== $row->participant_label ) {
                  $context_bits[] = 'Destinataire : ' . $row->participant_label;
                }
                if ( ! empty( $row->session_title ) ) {
                  $context_bits[] = 'Session : ' . (string) $row->session_title;
                }
                $open_url = add_query_arg( $detail_extra, $row->followup_url );
                ?>
                <tr class="acdc-action-row acdc-action-row-priority-<?php echo esc_attr( $priority_badge['key'] ); ?> acdc-action-row-<?php echo esc_attr( sanitize_html_class( $badge['key'] ) ); ?>">
                  <td><span class="<?php echo esc_attr( $badge['class'] ); ?>"><?php echo esc_html( $badge['label'] ); ?></span></td>
                  <td><strong><?php echo esc_html( $row->action_label ); ?></strong><br><small><?php echo esc_html( $this->get_questionnaire_action_type_label( ! empty( $row->action_type ) ? (string) $row->action_type : 'improvement' ) ); ?> · <?php echo esc_html( $this->get_questionnaire_action_status_label( ! empty( $row->action_status ) ? (string) $row->action_status : 'ouverte' ) ); ?></small></td>
                  <td><span class="<?php echo esc_attr( $priority_badge['class'] ); ?>"><?php echo esc_html( $priority_badge['label'] ); ?></span></td>
                  <td><?php echo esc_html( implode( ' · ', array_filter( $context_bits ) ) ); ?></td>
                  <td><?php echo esc_html( $this->get_questionnaire_action_assignee_label( ! empty( $row->assigned_to ) ? (int) $row->assigned_to : 0 ) ); ?></td>
                  <td><?php echo esc_html( ! empty( $row->due_state['label'] ) ? (string) $row->due_state['label'] : '—' ); ?></td>
                  <td><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $open_url ); ?>">Ouvrir</a></td>
                </tr>
              <?php endforeach; else : ?>
                <tr><td colspan="7">Aucune action ne correspond à ce filtre.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <div class="acdc-panel acdc-mb-18"><div class="acdc-section-head"><div><h3><?php echo esc_html( $terms['global_view_title'] ); ?></h3><p><?php echo esc_html( $terms['global_view_description'] ); ?></p></div></div><div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th><?php echo esc_html( $terms['session_column'] ); ?></th><th>Questionnaire</th><th><?php echo esc_html( $terms['participants'] ); ?></th><th><?php echo esc_html( $terms['responses'] ); ?></th><th>Score moyen</th><th>Alertes</th><th><?php echo esc_html( $terms['actions'] . ' à traiter' ); ?></th><th>Statut</th><th>Actions</th></tr></thead><tbody><?php foreach ( $rows as $row ) : $session = $row['session']; $source = $row['source']; $participant_count = count( $row['participants'] ); $response_count = $this->get_questionnaire_session_response_count( $session->id ); $average_score = $this->get_questionnaire_session_average_score( $session->id ); $alert_count = $this->get_questionnaire_session_alert_count( $session->id ); $row_counts = isset( $action_counts[ (int) $session->id ] ) ? $action_counts[ (int) $session->id ] : array( 'a_traiter' => 0, 'ouverte' => 0, 'en_cours' => 0 ); $detail_url = add_query_arg( $detail_extra, $this->get_questionnaire_results_url( $current_source_type, (int) $session->id, $current_source_id ) ); ?><tr><td><strong><?php echo esc_html( $session->session_title ); ?></strong><br><small><?php echo esc_html( ! empty( $session->session_date ) ? mysql2date( 'j F Y H:i', $session->session_date ) : '—' ); ?></small></td><td><?php echo esc_html( $source ? $source['title'] : 'Questionnaire supprimé' ); ?></td><td><?php echo esc_html( $participant_count ); ?></td><td><?php echo esc_html( $response_count ); ?></td><td><?php echo esc_html( null !== $average_score ? $average_score : '—' ); ?></td><td><?php echo esc_html( $alert_count ); ?></td><td><strong><?php echo esc_html( isset( $row_counts['a_traiter'] ) ? (int) $row_counts['a_traiter'] : 0 ); ?></strong><br><small>Ouvertes : <?php echo esc_html( isset( $row_counts['ouverte'] ) ? (int) $row_counts['ouverte'] : 0 ); ?> · En cours : <?php echo esc_html( isset( $row_counts['en_cours'] ) ? (int) $row_counts['en_cours'] : 0 ); ?></small></td><td><?php echo esc_html( $this->questionnaire_session_status_label( $session->status ) ); ?></td><td><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html( $terms['detail_button'] ); ?></a> <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_export_questionnaire_session_results_csv&questionnaire_session_id=' . (int) $session->id . '&page=acdc-of-questionnaire-results&source_type=' . rawurlencode( (string) $current_source_type ) ), 'acdc_export_questionnaire_session_results_csv_' . (int) $session->id ) ); ?>">CSV</a> <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_export_questionnaire_session_results_excel&questionnaire_session_id=' . (int) $session->id . '&page=acdc-of-questionnaire-results&source_type=' . rawurlencode( (string) $current_source_type ) ), 'acdc_export_questionnaire_session_results_excel_' . (int) $session->id ) ); ?>">Excel</a> <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_export_questionnaire_session_results_pdf&questionnaire_session_id=' . (int) $session->id . '&page=acdc-of-questionnaire-results&source_type=' . rawurlencode( (string) $current_source_type ) ), 'acdc_export_questionnaire_session_results_pdf_' . (int) $session->id ) ); ?>">Imprimable</a></td></tr><?php endforeach; if ( empty( $rows ) ) : ?><tr><td colspan="9">Aucun résultat disponible pour ce contexte.</td></tr><?php endif; ?></tbody></table></div></div>
    <?php if ( $session_focus ) : $participants = $this->get_questionnaire_session_participants( $session_focus->id ); $source_focus = $this->get_questionnaire_source_data( $session_focus->source_type, $session_focus->source_id ); $question_rows = $source_focus ? $this->get_questionnaire_session_question_rows( $session_focus->id, $source_focus ) : array(); $weakness_rows = $source_focus ? $this->get_questionnaire_session_weakness_rows( $session_focus->id, $source_focus, 5 ) : array(); $verbatim_rows = $this->get_questionnaire_session_verbatim_rows( $session_focus->id, 6 ); $session_response_count = $this->get_questionnaire_session_response_count( $session_focus->id ); $session_average_score = $this->get_questionnaire_session_average_score( $session_focus->id ); $session_alert_count = $this->get_questionnaire_session_alert_count( $session_focus->id ); $close_detail_url = add_query_arg( $detail_extra, $this->get_questionnaire_results_url( $current_source_type, 0, $current_source_id ) ); ?>
      <div class="acdc-panel acdc-mt-18"><div class="acdc-section-head"><div><h3><?php echo esc_html( $terms['session_view_title'] ); ?> — <?php echo esc_html( $session_focus->session_title ); ?></h3><p><?php echo esc_html( $terms['session_view_description'] ); ?></p></div><div class="acdc-inline-wrap"><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $close_detail_url ); ?>">Fermer le détail</a></div></div>
        <div class="acdc-grid-4cols acdc-mb-18">
          <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( count( $participants ) ); ?></div><div class="acdc-stat-label">Participants</div></div>
          <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $session_response_count ); ?></div><div class="acdc-stat-label">Réponses</div></div>
          <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( null !== $session_average_score ? $session_average_score : '—' ); ?></div><div class="acdc-stat-label">Score moyen</div></div>
          <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $session_alert_count ); ?></div><div class="acdc-stat-label">Alertes</div></div>
        </div>
        <?php if ( 'cold_survey' === $current_source_type ) : ?><div class="acdc-grid-3cols acdc-mb-18" style="display:grid;gap:18px"><div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( count( $weakness_rows ) ); ?></div><div class="acdc-stat-label">Questions sensibles</div></div><div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( count( $verbatim_rows ) ); ?></div><div class="acdc-stat-label">Verbatims récents</div></div><div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( max( 0, count( $participants ) - $session_response_count ) ); ?></div><div class="acdc-stat-label">Retours attendus</div></div></div><?php endif; ?>
        <div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Pseudo</th><th><?php echo esc_html( $terms['participant_column'] ); ?></th><th>Statut</th><th>Score</th><th><?php echo esc_html( $terms['responses'] ); ?></th><th>Alertes</th><th>Actions</th></tr></thead><tbody>
          <?php foreach ( $participants as $participant ) : $full_name = trim( ( $participant->first_name ?? '' ) . ' ' . ( $participant->last_name ?? '' ) ); $answers_count = $this->get_questionnaire_participant_answer_count( $session_focus->id, $participant->id ); $participant_detail_url = add_query_arg( $detail_extra, $this->get_questionnaire_results_url( $current_source_type, (int) $session_focus->id, $current_source_id, (int) $participant->id ) ); ?>
            <tr><td><?php echo esc_html( $participant->pseudo ); ?></td><td><?php echo esc_html( $full_name ? $full_name : '—' ); ?></td><td><?php echo esc_html( $this->questionnaire_session_status_label( $participant->participant_status ) ); ?></td><td><?php echo esc_html( $this->get_questionnaire_session_participant_score( $session_focus->id, $participant->id ) ); ?></td><td><?php echo esc_html( $answers_count ); ?></td><td><?php echo esc_html( ! empty( $participant->alert_flag ) ? 'Oui' : 'Non' ); ?></td><td><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $participant_detail_url ); ?>"><?php echo esc_html( $terms['detail_button'] ); ?></a><?php $reminder_url = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_send_questionnaire_session_reminder&participant_id=' . (int) $participant->id ), 'acdc_send_questionnaire_session_reminder_' . (int) $participant->id ); $reopen_url = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_reopen_questionnaire_participant&participant_id=' . (int) $participant->id ), 'acdc_reopen_questionnaire_participant_' . (int) $participant->id ); $improvement_url = add_query_arg( $detail_extra, $this->get_questionnaire_results_url( $current_source_type, (int) $session_focus->id, $current_source_id, (int) $participant->id ) ) . '#acdc-questionnaire-actions-panel'; ?> <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $reminder_url ); ?>">Relancer</a> <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $reopen_url ); ?>">Rouvrir</a> <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $improvement_url ); ?>"><?php echo esc_html( $terms['improvement_button'] ); ?></a></td></tr>
          <?php endforeach; if ( empty( $participants ) ) : ?><tr><td colspan="7">Aucun participant pour cette session.</td></tr><?php endif; ?></tbody></table></div>
        <div class="acdc-table-wrap acdc-mt-18"><table class="acdc-table"><thead><tr><th>#</th><th>Question</th><th>Type</th><th>Réponses</th><th>Bonnes réponses</th><th>Score / indicateur</th></tr></thead><tbody>
          <?php foreach ( $question_rows as $row ) : $question_label = isset( $row['question_label'] ) && '' !== (string) $row['question_label'] ? $row['question_label'] : ( isset( $row['label'] ) ? $row['label'] : 'Question' ); $question_type = isset( $row['question_type'] ) && '' !== (string) $row['question_type'] ? $row['question_type'] : ( isset( $row['type'] ) ? $row['type'] : '—' ); $question_responses = isset( $row['responses'] ) ? $row['responses'] : ( isset( $row['total_answers'] ) ? $row['total_answers'] : 0 ); $question_metric = isset( $row['average_score'] ) && null !== $row['average_score'] ? $row['average_score'] : ( isset( $row['response_rate'] ) && null !== $row['response_rate'] ? $row['response_rate'] . ' %' : ( isset( $row['success_rate'] ) && null !== $row['success_rate'] ? $row['success_rate'] . ' %' : '—' ) ); ?><tr><td><?php echo esc_html( $row['index'] + 1 ); ?></td><td><?php echo esc_html( $question_label ); ?></td><td><?php echo esc_html( $question_type ); ?></td><td><?php echo esc_html( $question_responses ); ?></td><td><?php echo esc_html( ! empty( $row['has_correct'] ) ? $row['correct_answers'] : '—' ); ?></td><td><?php echo esc_html( $question_metric ); ?></td></tr>
          <?php endforeach; if ( empty( $question_rows ) ) : ?><tr><td colspan="6">Aucune question disponible pour cette session.</td></tr><?php endif; ?></tbody></table></div><div class="acdc-grid-2cols acdc-mt-18" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;"><div class="acdc-panel"><div class="acdc-section-head"><div><h4><?php echo esc_html( $terms['question_weakness_title'] ); ?></h4><p><?php echo esc_html( $terms['question_weakness_description'] ); ?></p></div></div><div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>#</th><th>Question</th><th>Type</th><th>Réponses</th><th>Indicateur</th></tr></thead><tbody><?php foreach ( $weakness_rows as $weak_row ) : $weak_metric = isset( $weak_row['metric_value'] ) && null !== $weak_row['metric_value'] ? $weak_row['metric_value'] : null; ?><tr><td><?php echo esc_html( isset( $weak_row['index'] ) ? ( (int) $weak_row['index'] + 1 ) : '—' ); ?></td><td><?php echo esc_html( ! empty( $weak_row['question_label_resolved'] ) ? $weak_row['question_label_resolved'] : 'Question' ); ?></td><td><?php echo esc_html( ! empty( $weak_row['question_type_resolved'] ) ? $weak_row['question_type_resolved'] : '—' ); ?></td><td><?php echo esc_html( isset( $weak_row['responses_resolved'] ) ? (int) $weak_row['responses_resolved'] : 0 ); ?></td><td><?php echo esc_html( null !== $weak_metric ? number_format_i18n( (float) $weak_metric, 2 ) : '—' ); ?></td></tr><?php endforeach; if ( empty( $weakness_rows ) ) : ?><tr><td colspan="5">Aucune faiblesse détectable sur cet envoi.</td></tr><?php endif; ?></tbody></table></div></div><div class="acdc-panel"><div class="acdc-section-head"><div><h4><?php echo esc_html( $terms['verbatim_title'] ); ?></h4><p><?php echo esc_html( $terms['verbatim_description'] ); ?></p></div></div><div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th><?php echo esc_html( $terms['participant_column'] ); ?></th><th>Question</th><th>Réponse</th><th>Horodatage</th></tr></thead><tbody><?php foreach ( $verbatim_rows as $verbatim_row ) : $verbatim_name = trim( ( $verbatim_row->first_name ?? '' ) . ' ' . ( $verbatim_row->last_name ?? '' ) ); if ( '' === $verbatim_name ) { $verbatim_name = ! empty( $verbatim_row->pseudo ) ? (string) $verbatim_row->pseudo : '—'; } ?><tr><td><?php echo esc_html( $verbatim_name ); ?></td><td><?php echo esc_html( ! empty( $verbatim_row->question_label ) ? $verbatim_row->question_label : 'Question ' . ( (int) $verbatim_row->question_index + 1 ) ); ?></td><td><?php echo esc_html( wp_trim_words( (string) $verbatim_row->answer_value, 20, '…' ) ); ?></td><td><?php echo esc_html( ! empty( $verbatim_row->answered_at ) ? mysql2date( 'j F Y H:i', $verbatim_row->answered_at ) : '—' ); ?></td></tr><?php endforeach; if ( empty( $verbatim_rows ) ) : ?><tr><td colspan="4">Aucun verbatim récent pour cet envoi.</td></tr><?php endif; ?></tbody></table></div></div></div></div>
      <?php $this->render_questionnaire_actions_panel( (int) $session_focus->id ); ?>
      </div>
    <?php endif; ?>
    <?php if ( $session_focus && $participant_focus ) : $participant_answers = $this->get_questionnaire_participant_answers( (int) $participant_focus->id ); $participant_alert_answers = $this->get_questionnaire_participant_alert_answer_count( $participant_answers ); $participant_verbatims = $this->get_questionnaire_participant_verbatim_count( $participant_answers ); $participant_last_answered_at = $this->get_questionnaire_participant_last_answered_at( $participant_answers ); $close_participant_url = add_query_arg( $detail_extra, $this->get_questionnaire_results_url( $current_source_type, (int) $session_focus->id, $current_source_id ) ); ?>
      <div class="acdc-panel acdc-mt-18"><div class="acdc-section-head"><div><h3><?php echo esc_html( $terms['participant_view_title'] ); ?> — <?php echo esc_html( $this->get_questionnaire_session_participant_display_name( $participant_focus ) ); ?></h3><p><?php echo esc_html( $terms['participant_view_description'] ); ?></p></div><div class="acdc-inline-wrap"><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $close_participant_url ); ?>">Fermer la vue individuelle</a></div></div>
        <div class="acdc-grid-4cols acdc-mb-18">
          <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $this->get_questionnaire_session_participant_score( $session_focus->id, $participant_focus->id ) ); ?></div><div class="acdc-stat-label">Score</div></div>
          <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $this->get_questionnaire_participant_answer_count( $session_focus->id, $participant_focus->id ) ); ?></div><div class="acdc-stat-label">Réponses</div></div>
          <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( ! empty( $participant_focus->reminder_count ) ? (int) $participant_focus->reminder_count : 0 ); ?></div><div class="acdc-stat-label">Relances</div></div>
          <div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( ! empty( $participant_focus->alert_flag ) ? 'Oui' : 'Non' ); ?></div><div class="acdc-stat-label">Alerte</div></div>
        </div>
        <?php if ( 'cold_survey' === $current_source_type ) : ?><div class="acdc-grid-3cols acdc-mb-18" style="display:grid;gap:18px"><div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $participant_alert_answers ); ?></div><div class="acdc-stat-label">Réponses sensibles</div></div><div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( $participant_verbatims ); ?></div><div class="acdc-stat-label">Commentaires libres</div></div><div class="acdc-panel acdc-centered-stat"><div class="acdc-stat-number"><?php echo esc_html( '' !== $participant_last_answered_at ? mysql2date( 'j F Y', $participant_last_answered_at ) : '—' ); ?></div><div class="acdc-stat-label">Dernière réponse</div></div></div><?php endif; ?>
        <div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Question</th><th>Type</th><th>Réponse</th><th>Score</th><th>Alerte</th><th>Horodatage</th></tr></thead><tbody><?php foreach ( $participant_answers as $answer ) : ?><tr><td><?php echo esc_html( ! empty( $answer->question_label ) ? $answer->question_label : 'Question ' . ( (int) $answer->question_index + 1 ) ); ?></td><td><?php echo esc_html( ! empty( $answer->answer_type ) ? $answer->answer_type : '—' ); ?></td><td><?php echo esc_html( ! empty( $answer->answer_value ) ? $answer->answer_value : '—' ); ?></td><td><?php echo esc_html( '' !== (string) ( $answer->numeric_score ?? '' ) ? $answer->numeric_score : ( $answer->points_awarded ?? '—' ) ); ?></td><td><?php echo esc_html( ! empty( $answer->is_alert ) ? 'Oui' : 'Non' ); ?></td><td><?php echo esc_html( ! empty( $answer->answered_at ) ? mysql2date( 'j F Y H:i', $answer->answered_at ) : '—' ); ?></td></tr><?php endforeach; if ( empty( $participant_answers ) ) : ?><tr><td colspan="6">Aucune réponse détaillée disponible pour ce participant.</td></tr><?php endif; ?></tbody></table></div>
        <?php $this->render_questionnaire_actions_panel( (int) $session_focus->id, (int) $participant_focus->id ); ?>
      </div>
    <?php endif; ?>
    <?php
  }



  private function render_front_questionnaire_settings_tab() {

    $settings = get_option( 'acdc_of_questionnaire_settings', array() );
    $current_context = $this->get_questionnaire_current_context();
    $context = $this->get_questionnaire_context_config( $current_context );
    ?><section class="acdc-section-head"><div><h2><?php echo esc_html( $context['settings_title'] ); ?></h2><p><?php echo esc_html( $context['settings_description'] ); ?></p></div></section><?php
    if ( method_exists( $this, 'render_questionnaire_engine_foundation_panel' ) ) {
      $this->render_questionnaire_engine_foundation_panel();
    }
    ?>
    <div class="acdc-panel"><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acdc-form"><?php wp_nonce_field( 'acdc_save_questionnaire_settings' ); ?><input type="hidden" name="action" value="acdc_save_questionnaire_settings"><?php if ( '' !== $current_context ) : ?><input type="hidden" name="questionnaire_settings_context" value="<?php echo esc_attr( $current_context ); ?>"><?php endif; ?><div class="acdc-grid-2cols"><p><label>Score final affiché par défaut</label><select name="questionnaire_settings[show_final_score_default]"><option value="1" <?php selected( isset( $settings['show_final_score_default'] ) ? $settings['show_final_score_default'] : '1', '1' ); ?>>Oui</option><option value="0" <?php selected( isset( $settings['show_final_score_default'] ) ? $settings['show_final_score_default'] : '1', '0' ); ?>>Non</option></select></p><p><label>Pseudo obligatoire par défaut</label><select name="questionnaire_settings[pseudo_required_default]"><option value="1" <?php selected( isset( $settings['pseudo_required_default'] ) ? $settings['pseudo_required_default'] : '1', '1' ); ?>>Oui</option><option value="0" <?php selected( isset( $settings['pseudo_required_default'] ) ? $settings['pseudo_required_default'] : '1', '0' ); ?>>Non</option></select></p><p><label>Réserver aux apprenants inscrits par défaut</label><select name="questionnaire_settings[restrict_to_registered_default]"><option value="1" <?php selected( isset( $settings['restrict_to_registered_default'] ) ? $settings['restrict_to_registered_default'] : '1', '1' ); ?>>Oui</option><option value="0" <?php selected( isset( $settings['restrict_to_registered_default'] ) ? $settings['restrict_to_registered_default'] : '1', '0' ); ?>>Non</option></select></p><p><label>Statut par défaut d'une nouvelle session</label><select name="questionnaire_settings[new_session_status_default]"><option value="brouillon" <?php selected( isset( $settings['new_session_status_default'] ) ? $settings['new_session_status_default'] : 'brouillon', 'brouillon' ); ?>>Brouillon</option><option value="prete" <?php selected( isset( $settings['new_session_status_default'] ) ? $settings['new_session_status_default'] : 'brouillon', 'prete' ); ?>>Prête</option></select></p></div><p><button type="submit" class="acdc-button acdc-button-primary">Enregistrer</button></p></form></div><?php
    $survey_engine_type = method_exists( $this, 'get_survey_engine_type_key_from_source_type' ) ? $this->get_survey_engine_type_key_from_source_type( $current_context ) : '';
    if ( '' !== $survey_engine_type ) :
      $engine_config = method_exists( $this, 'get_survey_engine_type_config' ) ? $this->get_survey_engine_type_config( $survey_engine_type ) : array();
      $engine_settings = method_exists( $this, 'get_survey_engine_type_settings' ) ? $this->get_survey_engine_type_settings( $survey_engine_type ) : array();
      ?>
      <div class="acdc-panel acdc-mt-18">
        <h3>Paramètres métier du module</h3>
        <p class="acdc-muted-note">Ces réglages pilotent le moteur commun pour le module affiché : destinataire, caractère obligatoire, déclenchement, délai et relances.</p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acdc-form">
          <?php wp_nonce_field( 'acdc_save_survey_engine_settings' ); ?>
          <input type="hidden" name="action" value="acdc_save_survey_engine_settings">
          <input type="hidden" name="source_type" value="<?php echo esc_attr( $current_context ); ?>">
          <div class="acdc-grid-2cols">
            <p><label>Destinataire principal</label><input type="text" name="survey_engine[recipient_type]" value="<?php echo esc_attr( $engine_settings['recipient_type'] ?? ( $engine_config['recipient_type'] ?? '' ) ); ?>"></p>
            <p><label>Déclencheur affiché</label><input type="text" name="survey_engine[default_trigger]" value="<?php echo esc_attr( $engine_settings['default_trigger'] ?? ( $engine_config['default_trigger'] ?? '' ) ); ?>"></p>
            <p><label>Règle de déclenchement</label><input type="text" name="survey_engine[trigger_rule]" value="<?php echo esc_attr( $engine_settings['trigger_rule'] ?? '' ); ?>"></p>
            <p><label>Délai automatique complémentaire (jours)</label><input type="number" min="0" name="survey_engine[trigger_delay_days]" value="<?php echo esc_attr( (int) ( $engine_settings['trigger_delay_days'] ?? 0 ) ); ?>"></p>
            <p><label>Date limite par défaut (jours)</label><input type="number" min="1" name="survey_engine[deadline_days]" value="<?php echo esc_attr( (int) ( $engine_settings['deadline_days'] ?? 15 ) ); ?>"></p>
            <p><label>Relances par défaut</label><input type="text" name="survey_engine[reminder_days]" value="<?php echo esc_attr( implode( ',', (array) ( $engine_settings['reminder_days'] ?? array( 5, 10, 15 ) ) ) ); ?>" placeholder="5,10,15"></p>
          </div>
          <p><label><input type="checkbox" name="survey_engine[mandatory]" value="1" <?php checked( ! empty( $engine_settings['mandatory'] ) ); ?>> Module obligatoire par défaut</label></p>
          <p><button type="submit" class="acdc-button acdc-button-primary">Enregistrer les paramètres métier</button></p>
        </form>
      </div>
    <?php endif; ?><?php
  }


  private function render_questionnaire_session_public_page( $token ) {
    $session = $this->get_questionnaire_session_by_token( $token );
    $participant_token = isset( $_GET['participant'] ) ? sanitize_text_field( wp_unslash( $_GET['participant'] ) ) : '';
    $participant = $participant_token ? $this->get_questionnaire_participant_by_token( $participant_token ) : null;
    ob_start();
    echo '<div class="acdc-portal-shell"><div class="acdc-panel">';
    if ( isset( $_GET['q_notice'] ) ) {
      $type = isset( $_GET['q_notice_type'] ) ? sanitize_key( wp_unslash( $_GET['q_notice_type'] ) ) : 'info';
      echo '<div class="acdc-inline-notice acdc-inline-notice-' . esc_attr( $type ) . '">' . esc_html( wp_unslash( $_GET['q_notice'] ) ) . '</div>';
    }
    if ( ! $session ) {
      echo '<h2>Session introuvable</h2><p>Le lien communiqué n’est pas valide.</p></div></div>';
      return (string) ob_get_clean();
    }
    $source = $this->get_questionnaire_source_data( $session->source_type, $session->source_id );
    if ( $this->is_survey_questionnaire_source_type( (string) $session->source_type ) ) {
      /* ACDC 3.21.19-hotfix1 : vider le buffer (wrapper + h2 déjà émis) pour que le
         hero plein-écran parte de zéro sans divs parasites au-dessus. */
      ob_get_clean();
      ob_start();
      $this->render_public_survey_session_content( $session, $participant, $participant_token, $source, $token );
      return (string) ob_get_clean();
    }
    echo '<h2>' . esc_html( $session->session_title ) . '</h2><p>' . esc_html( $source ? $source['title'] : 'Questionnaire' ) . '</p>';
    if ( ! $participant ) {
      $learners = $this->get_questionnaire_targeted_learners_for_questionnaire_session( $session );
      echo '<p>Rejoignez la session depuis votre ordinateur ou smartphone, puis saisissez votre pseudo de session.</p>';
      if ( ! empty( $learners ) ) { echo '<p><small>' . esc_html( count( $learners ) ) . ' apprenant(s) ciblé(s) peuvent rejoindre cette session.</small></p>'; }
      echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="acdc-form">';
      echo wp_nonce_field( 'acdc_front_secure_action', '_wpnonce', true, false );
      echo '<input type="hidden" name="action" value="acdc_join_questionnaire_session"><input type="hidden" name="session_token" value="' . esc_attr( $token ) . '">';
      if ( ! empty( $learners ) ) {
        echo '<p><label>Votre nom</label><select name="apprenant_id" required><option value="">Choisir</option>';
        foreach ( $learners as $learner ) { echo '<option value="' . (int) $learner->id . '">' . esc_html( trim( $learner->first_name . ' ' . $learner->last_name ) ) . '</option>'; }
        echo '</select></p>';
      }
      echo '<p><label>Pseudo</label><input type="text" name="pseudo" required placeholder="Votre pseudo"></p>';
      echo '<p><button type="submit" class="acdc-button acdc-button-primary">Rejoindre la session</button></p></form></div></div>';
      return (string) ob_get_clean();
    }
    echo '<p><strong>Pseudo :</strong> ' . esc_html( $participant->pseudo ) . '</p>';
    if ( 'terminee' === $session->status ) {
      echo '<p>La session est terminée.</p>';
      if ( (int) $session->show_final_score ) {
        echo '<p><strong>Score final :</strong> ' . esc_html( $this->get_questionnaire_session_participant_score( $session->id, $participant->id ) ) . '</p>';
      }
      echo '</div></div>';
      return (string) ob_get_clean();
    }
    if ( in_array( $session->status, array( 'brouillon', 'prete', 'ouverte', 'en_pause' ), true ) ) {
      echo '<p>La session est en attente. Le formateur ouvrira la prochaine question.</p></div></div>';
      return (string) ob_get_clean();
    }
    $source_questions = $source ? $source['questions'] : array();
    $question_index = (int) $session->current_question_index;
    $question = isset( $source_questions[ $question_index ] ) ? $source_questions[ $question_index ] : null;
    if ( ! $question ) { echo '<p>Aucune question disponible.</p></div></div>'; return (string) ob_get_clean(); }
    $already_answered = $this->get_questionnaire_session_answer_for_participant( $session->id, $participant->id, $question_index );
    echo '<div class="acdc-panel acdc-panel-block"><p><small>Question ' . esc_html( $question_index + 1 ) . ' sur ' . esc_html( count( $source_questions ) ) . '</small></p><h3>' . esc_html( isset( $question['label'] ) ? $question['label'] : 'Question' ) . '</h3>';
    if ( ! empty( $question['options'] ) ) { echo '<p>' . nl2br( esc_html( $question['options'] ) ) . '</p>'; }
    if ( $already_answered ) {
      echo '<p>Votre réponse a bien été enregistrée. Attendez la question suivante.</p>';
    } else {
      $choices = $this->parse_question_choices( $question );
      echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="acdc-form">' . wp_nonce_field( 'acdc_front_secure_action', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="acdc_submit_questionnaire_answer"><input type="hidden" name="session_token" value="' . esc_attr( $token ) . '"><input type="hidden" name="participant_token" value="' . esc_attr( $participant_token ) . '">';
      if ( 'Question ouverte' === ( $question['type'] ?? '' ) ) {
        echo '<p><textarea name="answer_text" rows="4" required placeholder="Votre réponse"></textarea></p>';
      } elseif ( in_array( $question['type'] ?? '', array( 'Choix multiples', 'Cases à cocher' ), true ) ) {
        foreach ( $choices as $choice ) { echo '<p><label><input type="checkbox" name="answer_values[]" value="' . esc_attr( $choice['value'] ) . '"> ' . esc_html( $choice['label'] ) . '</label></p>'; }
      } else {
        foreach ( $choices as $choice ) { echo '<p><label><input type="radio" name="answer_value" required value="' . esc_attr( $choice['value'] ) . '"> ' . esc_html( $choice['label'] ) . '</label></p>'; }
      }
      echo '<p><button type="submit" class="acdc-button acdc-button-primary">Valider ma réponse</button></p></form>';
    }
    echo '</div></div></div>';
    return (string) ob_get_clean();
  }


  public function render_questionnaire_public_shortcode( $atts = array() ) {
    return $this->render_questionnaire_session_shortcode( $atts );
  }


  private function render_survey_block_editor_ui( $blocks, $read_only, $wrap_id, $add_btn_id ) {
    $block_types = $this->get_survey_block_types_map();
    ?>
    <?php if ( ! $read_only ) : ?>
    <p>
      <button type="button" class="acdc-button acdc-button-primary" id="<?php echo esc_attr( $add_btn_id ); ?>">+ Ajouter une question</button>
    </p>
    <?php endif; ?>
    <div id="<?php echo esc_attr( $wrap_id ); ?>">
      <?php foreach ( $blocks as $index => $block ) :
        $bt       = isset( $block['block_type'] ) ? (string) $block['block_type'] : 'notation';
        $q_label  = isset( $block['question'] ) ? (string) $block['question'] : '';
        $expl     = isset( $block['explanation'] ) ? (string) $block['explanation'] : '';
        $bt_label = isset( $block_types[ $bt ] ) ? $block_types[ $bt ] : $bt;
        $answers  = ( isset( $block['answers'] ) && is_array( $block['answers'] ) ) ? $block['answers'] : array();
        $choice_type = isset( $block['choice_type'] ) ? (string) $block['choice_type'] : 'choix_unique';
        $nps_min = isset( $block['nps_min'] ) ? (int) $block['nps_min'] : 0;
        $nps_max = isset( $block['nps_max'] ) ? (int) $block['nps_max'] : 10;
        $nps_min_label = isset( $block['nps_min_label'] ) ? (string) $block['nps_min_label'] : 'Pas du tout';
        $nps_max_label = isset( $block['nps_max_label'] ) ? (string) $block['nps_max_label'] : 'Tout à fait';
      ?>
      <div class="acdc-panel acdc-survey-block" data-index="<?php echo esc_attr( (string) $index ); ?>" style="margin-bottom:14px;padding:0;overflow:hidden;">
        <div class="acdc-block-header">
          <strong>#<?php echo esc_html( (string) ( $index + 1 ) ); ?> <?php echo esc_html( $bt_label ); ?></strong>
          <?php if ( ! $read_only ) : ?>
          <button type="button" class="acdc-button acdc-remove-survey-block acdc-block-header-action">Supprimer</button>
          <?php endif; ?>
        </div>
        <div class="acdc-pad-16">
          <p>
            <label>Question *</label>
            <input type="text" name="blocks[<?php echo esc_attr( (string) $index ); ?>][question]" value="<?php echo esc_attr( $q_label ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>>
          </p>
          <p>
            <label>Type de question *</label>
            <select class="acdc-survey-type-select" name="blocks[<?php echo esc_attr( (string) $index ); ?>][block_type]" <?php disabled( $read_only ); ?>>
              <?php foreach ( $block_types as $bk => $bl ) : ?>
              <option value="<?php echo esc_attr( $bk ); ?>" <?php selected( $bt, $bk ); ?>><?php echo esc_html( $bl ); ?></option>
              <?php endforeach; ?>
            </select>
          </p>
          <p>
            <label>Explication / sous-titre</label>
            <textarea name="blocks[<?php echo esc_attr( (string) $index ); ?>][explanation]" rows="2" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Texte d'aide affiché sous la question"><?php echo esc_textarea( $expl ); ?></textarea>
          </p>
          <?php /* NPS config */ ?>
          <div class="acdc-survey-nps-config" style="<?php echo 'nps' !== $bt ? 'display:none;' : ''; ?>">
            <p style="display:flex;gap:12px;flex-wrap:wrap;">
              <span style="flex:1;min-width:120px;"><label>Min</label><input type="number" name="blocks[<?php echo esc_attr( (string) $index ); ?>][nps_min]" value="<?php echo esc_attr( (string) $nps_min ); ?>" min="0" max="5" <?php echo $read_only ? 'readonly' : ''; ?>></span>
              <span style="flex:1;min-width:120px;"><label>Max</label><input type="number" name="blocks[<?php echo esc_attr( (string) $index ); ?>][nps_max]" value="<?php echo esc_attr( (string) $nps_max ); ?>" min="5" max="10" <?php echo $read_only ? 'readonly' : ''; ?>></span>
              <span style="flex:2;min-width:160px;"><label>Libellé min</label><input type="text" name="blocks[<?php echo esc_attr( (string) $index ); ?>][nps_min_label]" value="<?php echo esc_attr( $nps_min_label ); ?>" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Ex : Pas du tout"></span>
              <span style="flex:2;min-width:160px;"><label>Libellé max</label><input type="text" name="blocks[<?php echo esc_attr( (string) $index ); ?>][nps_max_label]" value="<?php echo esc_attr( $nps_max_label ); ?>" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Ex : Tout à fait"></span>
            </p>
          </div>
          <?php /* Answers config (cases_a_cocher + liste_deroulante + oui_non) */ ?>
          <div class="acdc-survey-answers-config" style="<?php echo ! in_array( $bt, array( 'cases_a_cocher', 'liste_deroulante', 'oui_non' ), true ) ? 'display:none;' : ''; ?>">
            <?php if ( 'cases_a_cocher' === $bt ) : ?>
            <p>
              <label>Type de sélection</label>
              <select name="blocks[<?php echo esc_attr( (string) $index ); ?>][choice_type]" <?php disabled( $read_only ); ?>>
                <option value="choix_unique" <?php selected( $choice_type, 'choix_unique' ); ?>>Choix unique (radio)</option>
                <option value="choix_multiple" <?php selected( $choice_type, 'choix_multiple' ); ?>>Choix multiple (cases)</option>
              </select>
            </p>
            <?php endif; ?>
            <?php if ( 'oui_non' !== $bt ) : ?>
            <p><label>Réponses (une par ligne) *</label></p>
            <div class="acdc-survey-answers-list">
              <?php foreach ( $answers as $ai => $answer_val ) : ?>
              <p class="acdc-survey-answer-row" style="display:flex;gap:8px;align-items:center;">
                <input type="text" name="blocks[<?php echo esc_attr( (string) $index ); ?>][answers][]" value="<?php echo esc_attr( $answer_val ); ?>" style="flex:1;" <?php echo $read_only ? 'readonly' : ''; ?> placeholder="Réponse">
                <?php if ( ! $read_only ) : ?><button type="button" class="acdc-remove-survey-answer" style="flex-shrink:0;">✕</button><?php endif; ?>
              </p>
              <?php endforeach; ?>
            </div>
            <?php if ( ! $read_only ) : ?>
            <p><button type="button" class="acdc-add-survey-answer acdc-button acdc-button-soft" style="font-size:13px;height:32px;padding:0 12px;">+ Ajouter une réponse</button></p>
            <?php endif; ?>
            <?php else : ?>
            <p class="acdc-muted-note">Deux boutons Oui / Non seront affichés automatiquement.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ( ! $read_only ) : ?>
    <script>
    (function(){
      var wrap = document.getElementById(<?php echo wp_json_encode( $wrap_id ); ?>);
      var addBtn = document.getElementById(<?php echo wp_json_encode( $add_btn_id ); ?>);
      var blockTypes = <?php echo wp_json_encode( $block_types ); ?>;

      function reIndex() {
        if(!wrap) return;
        wrap.querySelectorAll('.acdc-survey-block').forEach(function(blk, idx) {
          blk.setAttribute('data-index', idx);
          var sel = blk.querySelector('.acdc-survey-type-select');
          var bt = sel ? sel.value : 'notation';
          blk.querySelector('strong').textContent = '#'+(idx+1)+' '+(blockTypes[bt]||bt);
          blk.querySelectorAll('[name]').forEach(function(f){
            f.name = f.name.replace(/blocks\[\d+\]/, 'blocks['+idx+']');
          });
        });
      }

      function updateBlockVisibility(blk) {
        var sel = blk.querySelector('.acdc-survey-type-select');
        if(!sel) return;
        var bt = sel.value;
        var npsConf = blk.querySelector('.acdc-survey-nps-config');
        var ansConf = blk.querySelector('.acdc-survey-answers-config');
        var choiceTypeWrap = blk.querySelector('select[name*="[choice_type]"]');
        var answersList = blk.querySelector('.acdc-survey-answers-list');
        var addAnswerBtn = blk.querySelector('.acdc-add-survey-answer');
        var oui_non_note = blk.querySelector('.acdc-muted-note');

        if(npsConf) npsConf.style.display = (bt === 'nps') ? '' : 'none';
        if(ansConf) {
          var showAns = ['cases_a_cocher','liste_deroulante','oui_non'].indexOf(bt) !== -1;
          ansConf.style.display = showAns ? '' : 'none';
          var isOuiNon = (bt === 'oui_non');
          if(answersList) answersList.style.display = isOuiNon ? 'none' : '';
          if(addAnswerBtn) addAnswerBtn.style.display = isOuiNon ? 'none' : '';
          if(oui_non_note) oui_non_note.style.display = isOuiNon ? '' : 'none';
          if(choiceTypeWrap) {
            var ctRow = choiceTypeWrap.closest('p');
            if(ctRow) ctRow.style.display = (bt === 'cases_a_cocher') ? '' : 'none';
          }
        }
      }

      function buildNewBlock(idx) {
        var html = '<div class="acdc-panel acdc-survey-block" data-index="'+idx+'" style="margin-bottom:14px;padding:0;overflow:hidden;">';
        html += '<div class="acdc-block-header"><strong>#'+(idx+1)+' Étoiles (1 à 5 ★)</strong><button type="button" class="acdc-button acdc-remove-survey-block acdc-block-header-action">Supprimer</button></div>';
        html += '<div class="acdc-pad-16">';
        html += '<p><label>Question *</label><input type="text" name="blocks['+idx+'][question]"></p>';
        html += '<p><label>Type de question *</label><select class="acdc-survey-type-select" name="blocks['+idx+'][block_type]">';
        Object.keys(blockTypes).forEach(function(k){ html += '<option value="'+k+'">'+blockTypes[k]+'</option>'; });
        html += '</select></p>';
        html += '<p><label>Explication / sous-titre</label><textarea name="blocks['+idx+'][explanation]" rows="2" placeholder="Texte d\'aide affiché sous la question"></textarea></p>';
        html += '<div class="acdc-survey-nps-config" style="display:none;">';
        html += '<p style="display:flex;gap:12px;flex-wrap:wrap;">';
        html += '<span style="flex:1;min-width:120px;"><label>Min</label><input type="number" name="blocks['+idx+'][nps_min]" value="0" min="0" max="5"></span>';
        html += '<span style="flex:1;min-width:120px;"><label>Max</label><input type="number" name="blocks['+idx+'][nps_max]" value="10" min="5" max="10"></span>';
        html += '<span style="flex:2;min-width:160px;"><label>Libellé min</label><input type="text" name="blocks['+idx+'][nps_min_label]" value="Pas du tout" placeholder="Ex : Pas du tout"></span>';
        html += '<span style="flex:2;min-width:160px;"><label>Libellé max</label><input type="text" name="blocks['+idx+'][nps_max_label]" value="Tout à fait" placeholder="Ex : Tout à fait"></span>';
        html += '</p></div>';
        html += '<div class="acdc-survey-answers-config" style="display:none;">';
        html += '<p><select name="blocks['+idx+'][choice_type]" style="display:none;"><option value="choix_unique">Choix unique (radio)</option><option value="choix_multiple">Choix multiple (cases)</option></select></p>';
        html += '<p><label>Réponses (une par ligne) *</label></p>';
        html += '<div class="acdc-survey-answers-list"></div>';
        html += '<p><button type="button" class="acdc-add-survey-answer acdc-button acdc-button-soft" style="font-size:13px;height:32px;padding:0 12px;">+ Ajouter une réponse</button></p>';
        html += '<p class="acdc-muted-note" style="display:none;">Deux boutons Oui / Non seront affichés automatiquement.</p>';
        html += '</div>';
        html += '</div></div>';
        return html;
      }

      if(addBtn && wrap) {
        addBtn.addEventListener('click', function(){
          var idx = wrap.querySelectorAll('.acdc-survey-block').length;
          var tmp = document.createElement('div');
          tmp.innerHTML = buildNewBlock(idx);
          wrap.appendChild(tmp.firstChild);
          reIndex();
        });
      }

      if(wrap) {
        wrap.addEventListener('click', function(e){
          if(e.target.classList.contains('acdc-remove-survey-block')){
            e.target.closest('.acdc-survey-block').remove();
            reIndex();
          }
          if(e.target.classList.contains('acdc-add-survey-answer')){
            var blk = e.target.closest('.acdc-survey-block');
            var idx = blk.getAttribute('data-index');
            var list = blk.querySelector('.acdc-survey-answers-list');
            if(list){
              var row = document.createElement('p');
              row.className = 'acdc-survey-answer-row';
              row.style.cssText = 'display:flex;gap:8px;align-items:center;';
              row.innerHTML = '<input type="text" name="blocks['+idx+'][answers][]" style="flex:1;" placeholder="Réponse"><button type="button" class="acdc-remove-survey-answer" style="flex-shrink:0;">✕</button>';
              list.appendChild(row);
            }
          }
          if(e.target.classList.contains('acdc-remove-survey-answer')){
            e.target.closest('.acdc-survey-answer-row').remove();
          }
        });

        wrap.addEventListener('change', function(e){
          if(e.target.classList.contains('acdc-survey-type-select')){
            var blk = e.target.closest('.acdc-survey-block');
            updateBlockVisibility(blk);
            reIndex();
          }
        });

        wrap.querySelectorAll('.acdc-survey-block').forEach(function(blk){
          updateBlockVisibility(blk);
        });
      }
    })();
    </script>
    <?php endif; ?>
    <?php
  }


  private function render_public_survey_session_content( $session, $participant, $participant_token, $source, $token ) {
    $answer_map       = $participant ? $this->get_questionnaire_session_answer_map_for_participant( (int) $session->id, (int) $participant->id ) : array();
    $source_questions = ! empty( $source['questions'] ) && is_array( $source['questions'] ) ? $source['questions'] : array();
    $survey_type      = ! empty( $source['type'] ) ? (string) $source['type'] : 'hot_survey';
    $branding         = $this->acdc_get_transactional_email_branding();
    $org_name         = ! empty( $branding['company_name'] ) ? (string) $branding['company_name'] : 'ACDC Formation';
    $logo_url         = ! empty( $branding['logo_url'] ) ? (string) $branding['logo_url'] : '';
    $hero_url         = $this->get_survey_hero_image_url( $survey_type );
    $type_label       = $this->get_survey_type_display_label( $survey_type );
    $total_q          = count( $source_questions );

    /* ---- États terminaux ---- */
    if ( ! $participant ) {
      $this->render_survey_public_shell( $hero_url, $org_name, $logo_url, $type_label, $session->session_title, '', $total_q );
      echo '<div class="srv-body"><div class="srv-card srv-card-state">';
      echo '<div class="srv-state-icon">🔒</div>';
      echo '<p>' . esc_html( 'Cette enquête utilise un lien personnel et nominatif. Utilisez le lien reçu par e-mail pour répondre.' ) . '</p>';
      echo '</div></div></div>';
      return;
    }

    $dest_name = $this->get_questionnaire_session_participant_display_name( $participant );

    if ( $this->is_questionnaire_session_expired( $session ) || in_array( (string) $session->status, array( 'expiree', 'annulee' ), true ) ) {
      $this->render_survey_public_shell( $hero_url, $org_name, $logo_url, $type_label, $session->session_title, $dest_name, $total_q );
      echo '<div class="srv-body"><div class="srv-card srv-card-state">';
      echo '<div class="srv-state-icon">⏱️</div>';
      echo '<h3>' . esc_html( 'Lien expiré' ) . '</h3>';
      echo '<p>' . esc_html( 'Le lien de réponse n\'est plus disponible.' ) . '</p>';
      echo '</div></div></div>';
      return;
    }

    if ( in_array( (string) ( $participant->participant_status ?? '' ), array( 'repondu', 'termine' ), true ) ) {
      $this->render_survey_public_shell( $hero_url, $org_name, $logo_url, $type_label, $session->session_title, $dest_name, $total_q );
      echo '<div class="srv-body"><div class="srv-card srv-card-state srv-card-success">';
      echo '<div class="srv-state-icon">✅</div>';
      echo '<h3>' . esc_html( 'Merci pour votre réponse !' ) . '</h3>';
      echo '<p>' . esc_html( 'Votre réponse a bien été enregistrée. Elle contribue à l\'amélioration continue de nos formations.' ) . '</p>';
      echo '</div></div></div>';
      return;
    }

    if ( empty( $source_questions ) ) {
      $this->render_survey_public_shell( $hero_url, $org_name, $logo_url, $type_label, $session->session_title, $dest_name, $total_q );
      echo '<div class="srv-body"><div class="srv-card srv-card-state">';
      echo '<p>' . esc_html( 'Aucune question n\'est actuellement disponible pour cette enquête.' ) . '</p>';
      echo '</div></div></div>';
      return;
    }

    /* ---- Formulaire ---- */
    $this->render_survey_public_shell( $hero_url, $org_name, $logo_url, $type_label, $session->session_title, $dest_name, $total_q );
    echo '<div class="srv-body">';

    if ( ! empty( $source['record'] ) && ! empty( $source['record']->description_text ) ) {
      echo '<div class="srv-card srv-desc-card"><p>' . nl2br( esc_html( (string) $source['record']->description_text ) ) . '</p></div>';
    }
    if ( ! empty( $session->deadline_at ) ) {
      echo '<div class="srv-deadline">📅 Date limite : <strong>' . esc_html( mysql2date( 'j F Y', $session->deadline_at ) ) . '</strong></div>';
    }

    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="srv-form">';
    echo wp_nonce_field( 'acdc_front_secure_action', '_wpnonce', true, false );
    echo '<input type="hidden" name="action" value="acdc_submit_public_questionnaire_session">';
    echo '<input type="hidden" name="session_token" value="' . esc_attr( $token ) . '">';
    echo '<input type="hidden" name="participant_token" value="' . esc_attr( $participant_token ) . '">';

    foreach ( $source_questions as $index => $question ) {
      $label    = ! empty( $question['label'] ) ? (string) $question['label'] : 'Question ' . ( $index + 1 );
      $type     = ! empty( $question['type'] ) ? (string) $question['type'] : 'Notation';
      $expl_raw = ! empty( $question['options'] ) ? (string) $question['options'] : '';
      $existing = isset( $answer_map[ $index ] ) ? $answer_map[ $index ] : null;
      $ex_val   = $existing && isset( $existing->answer_value ) ? (string) $existing->answer_value : '';
      $ex_vals  = '' !== $ex_val ? explode( '||', $ex_val ) : array();

      echo '<div class="srv-card srv-question-card">';
      echo '<div class="srv-q-num">' . esc_html( (string) ( $index + 1 ) ) . '</div>';
      echo '<div class="srv-q-body">';
      echo '<div class="srv-q-label">' . esc_html( $label ) . '</div>';
      if ( '' !== $expl_raw ) {
        echo '<div class="srv-q-hint">' . nl2br( esc_html( $expl_raw ) ) . '</div>';
      }

      if ( 'Notation' === $type ) {
        /* Étoiles cliquables */
        echo '<div class="srv-stars" data-field="survey_answers[' . esc_attr( (string) $index ) . '][rating]">';
        for ( $i = 1; $i <= 5; $i++ ) {
          $active = ( '' !== $ex_val && (int) $ex_val >= $i ) ? ' srv-star-on' : '';
          echo '<button type="button" class="srv-star' . esc_attr( $active ) . '" data-val="' . esc_attr( (string) $i ) . '" aria-label="' . esc_attr( (string) $i ) . ' étoile(s)">★</button>';
        }
        echo '</div>';
        echo '<input type="hidden" class="srv-stars-hidden" name="survey_answers[' . esc_attr( (string) $index ) . '][rating]" value="' . esc_attr( $ex_val ) . '">';

      } elseif ( 'Smileys' === $type ) {
        /* Smileys cliquables 1-5 */
        $smileys = array( 1 => '😞', 2 => '😕', 3 => '😐', 4 => '🙂', 5 => '😄' );
        $labels  = array( 1 => 'Très insatisfait', 2 => 'Insatisfait', 3 => 'Neutre', 4 => 'Satisfait', 5 => 'Très satisfait' );
        echo '<div class="srv-smileys">';
        foreach ( $smileys as $sv => $emoji ) {
          $checked = ( (string) $sv === $ex_val ) ? ' checked' : '';
          echo '<label class="srv-smiley' . ( '' !== $checked ? ' srv-smiley-on' : '' ) . '" title="' . esc_attr( $labels[ $sv ] ) . '">';
          echo '<input type="radio" name="survey_answers[' . esc_attr( (string) $index ) . '][rating]" value="' . esc_attr( (string) $sv ) . '"' . $checked . '>';
          echo '<span class="srv-smiley-face">' . $emoji . '</span>';
          echo '<span class="srv-smiley-lbl">' . esc_html( (string) $sv ) . '</span>';
          echo '</label>';
        }
        echo '</div>';

      } elseif ( 'NPS' === $type ) {
        /* Échelle NPS */
        $nps_min       = isset( $question['nps_min'] ) ? (int) $question['nps_min'] : 0;
        $nps_max       = isset( $question['nps_max'] ) ? (int) $question['nps_max'] : 10;
        $nps_min_label = isset( $question['nps_min_label'] ) ? (string) $question['nps_min_label'] : 'Pas du tout';
        $nps_max_label = isset( $question['nps_max_label'] ) ? (string) $question['nps_max_label'] : 'Tout à fait';
        echo '<div class="srv-nps">';
        echo '<div class="srv-nps-scale">';
        for ( $i = $nps_min; $i <= $nps_max; $i++ ) {
          $active = ( (string) $i === $ex_val ) ? ' srv-nps-on' : '';
          echo '<label class="srv-nps-btn' . esc_attr( $active ) . '">';
          echo '<input type="radio" name="survey_answers[' . esc_attr( (string) $index ) . '][nps]" value="' . esc_attr( (string) $i ) . '"' . ( (string) $i === $ex_val ? ' checked' : '' ) . '>';
          echo esc_html( (string) $i );
          echo '</label>';
        }
        echo '</div>';
        echo '<div class="srv-nps-labels">';
        echo '<span>' . esc_html( $nps_min_label ) . '</span>';
        echo '<span>' . esc_html( $nps_max_label ) . '</span>';
        echo '</div>';
        echo '</div>';

      } elseif ( 'Question ouverte' === $type ) {
        echo '<textarea class="srv-textarea" name="survey_answers[' . esc_attr( (string) $index ) . '][text]" rows="4" placeholder="Votre réponse…">' . esc_textarea( $ex_val ) . '</textarea>';

      } elseif ( 'Réponse courte' === $type ) {
        echo '<input class="srv-input" type="text" name="survey_answers[' . esc_attr( (string) $index ) . '][text]" value="' . esc_attr( $ex_val ) . '" placeholder="Votre réponse…">';

      } elseif ( 'Oui / Non' === $type ) {
        echo '<div class="srv-oui-non">';
        foreach ( array( 'Oui', 'Non' ) as $on_val ) {
          $active = ( $on_val === $ex_val ) ? ' srv-pill-on' : '';
          echo '<label class="srv-pill' . esc_attr( $active ) . '">';
          echo '<input type="radio" name="survey_answers[' . esc_attr( (string) $index ) . '][value]" value="' . esc_attr( $on_val ) . '"' . ( $on_val === $ex_val ? ' checked' : '' ) . '>';
          echo esc_html( $on_val );
          echo '</label>';
        }
        echo '</div>';

      } elseif ( 'Liste déroulante' === $type ) {
        $choices = array_filter( array_map( 'trim', preg_split( '/\n|\r\n|\r/', $expl_raw ) ) );
        echo '<select class="srv-select" name="survey_answers[' . esc_attr( (string) $index ) . '][value]">';
        echo '<option value="">— Choisir —</option>';
        foreach ( $choices as $ch ) {
          echo '<option value="' . esc_attr( (string) $ch ) . '"' . selected( $ex_val, (string) $ch, false ) . '>' . esc_html( (string) $ch ) . '</option>';
        }
        echo '</select>';

      } elseif ( 'Choix multiples' === $type ) {
        $choices = array_filter( array_map( 'trim', preg_split( '/\n|\r\n|\r/', $expl_raw ) ) );
        foreach ( $choices as $ch ) {
          $ch = (string) $ch;
          echo '<label class="srv-check-label"><input type="checkbox" name="survey_answers[' . esc_attr( (string) $index ) . '][choices][]" value="' . esc_attr( $ch ) . '"' . ( in_array( $ch, $ex_vals, true ) ? ' checked' : '' ) . '> ' . esc_html( $ch ) . '</label>';
        }

      } else {
        /* Cases à cocher / Choix unique */
        $choices = array_filter( array_map( 'trim', preg_split( '/\n|\r\n|\r/', $expl_raw ) ) );
        foreach ( $choices as $ch ) {
          $ch = (string) $ch;
          echo '<label class="srv-radio-label"><input type="radio" name="survey_answers[' . esc_attr( (string) $index ) . '][value]" value="' . esc_attr( $ch ) . '"' . ( $ex_val === $ch ? ' checked' : '' ) . '> ' . esc_html( $ch ) . '</label>';
        }
      }

      echo '</div></div>'; /* .srv-q-body + .srv-question-card */
    }

    echo '<div class="srv-submit-wrap">';
    echo '<button type="submit" class="srv-submit-btn">Valider mes réponses</button>';
    echo '</div>';
    echo '</form>';
    echo '</div>'; /* .srv-body */
    echo '</div>'; /* .acdc-survey-public-wrap */

    /* Étoiles JS */
    ?>
    <script>
    (function(){
      document.querySelectorAll('.srv-stars').forEach(function(wrap){
        var hidden = wrap.parentNode.querySelector('.srv-stars-hidden');
        var stars  = wrap.querySelectorAll('.srv-star');
        function setVal(v){
          if(hidden) hidden.value = v;
          stars.forEach(function(s){
            s.classList.toggle('srv-star-on', parseInt(s.dataset.val) <= parseInt(v));
          });
        }
        stars.forEach(function(s){
          s.addEventListener('click',function(){ setVal(s.dataset.val); });
          s.addEventListener('mouseenter',function(){
            stars.forEach(function(t){ t.classList.toggle('srv-star-hover', parseInt(t.dataset.val) <= parseInt(s.dataset.val)); });
          });
        });
        wrap.addEventListener('mouseleave',function(){
          stars.forEach(function(t){ t.classList.remove('srv-star-hover'); });
        });
      });
      /* Smileys & pills — style actif via CSS sur radio:checked */
      document.querySelectorAll('.srv-smiley input, .srv-pill input, .srv-nps-btn input').forEach(function(inp){
        inp.addEventListener('change',function(){
          var grp = inp.closest('.srv-smileys, .srv-oui-non, .srv-nps-scale');
          if(!grp) return;
          grp.querySelectorAll('.srv-smiley, .srv-pill, .srv-nps-btn').forEach(function(lbl){
            lbl.classList.remove('srv-smiley-on','srv-pill-on','srv-nps-on');
          });
          inp.closest('.srv-smiley, .srv-pill, .srv-nps-btn').classList.add(
            grp.classList.contains('srv-smileys') ? 'srv-smiley-on' :
            grp.classList.contains('srv-oui-non') ? 'srv-pill-on' : 'srv-nps-on'
          );
        });
      });
    })();
    </script>
    <?php
  }


  private function render_survey_public_shell( $hero_url, $org_name, $logo_url, $type_label, $session_title, $dest_name, $total_q ) {
    ?>
    <div class="acdc-survey-public-wrap">
      <div class="srv-hero" style="<?php echo $hero_url ? 'background-image:url(' . esc_url( $hero_url ) . ');background-size:cover;background-repeat:no-repeat;background-position:center center;' : ''; ?>">
        <div class="srv-hero-overlay"></div>
        <div class="srv-hero-inner">
          <?php if ( $logo_url ) : ?>
          <div class="srv-hero-logo-wrap">
            <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $org_name ); ?>" class="srv-hero-logo">
          </div>
          <?php endif; ?>
          <div class="srv-hero-content">
            <div class="srv-hero-badge"><?php echo esc_html( $type_label ); ?></div>
            <div class="srv-hero-title"><?php echo esc_html( $session_title ); ?></div>
            <?php if ( '' !== $dest_name ) : ?>
            <div class="srv-hero-sub">Destinataire : <strong><?php echo esc_html( $dest_name ); ?></strong></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="srv-progress-wrap">
          <div class="srv-progress-bar-bg"><div class="srv-progress-bar-fill" style="width:0%"></div></div>
          <div class="srv-progress-label"><?php echo esc_html( (string) $total_q ); ?> question<?php echo $total_q > 1 ? 's' : ''; ?></div>
        </div>
      </div>
    <?php
  }


}
