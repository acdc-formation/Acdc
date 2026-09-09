<?php
    /**
     * Trait ACDC_Crm_Commercial_Render_Trait
     *
     * Sous-bloc CRM commercial extrait incrémentalement depuis class-acdc-plugin.php.
     *
     * @since 3.12.0
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    trait ACDC_Crm_Commercial_Render_Trait {

  private function render_front_prospects_tab( $action, $item_id ) {
    if ( 'followup' === $action ) {
      $this->render_front_prospect_followup_tab( 'view', $item_id );
      return;
    }

    $prospect = $item_id ? $this->get_prospect( $item_id ) : null;
    if ( ! $prospect && 'new' === $action ) {
      $prefill_profile = isset( $_GET['profile'] ) ? sanitize_text_field( wp_unslash( $_GET['profile'] ) ) : '';
      $duplicate_id = isset( $_GET['duplicate_id'] ) ? absint( wp_unslash( $_GET['duplicate_id'] ) ) : 0;
      if ( $duplicate_id ) {
        $duplicate_source = $this->get_prospect( $duplicate_id );
        if ( $duplicate_source ) {
          $duplicate_data = (array) $duplicate_source;
          unset( $duplicate_data['id'], $duplicate_data['created_at'], $duplicate_data['updated_at'], $duplicate_data['rdv_at'], $duplicate_data['rdv_notes'], $duplicate_data['last_followup'], $duplicate_data['session_label'] );
          $duplicate_data['status'] = 'À traiter';
          $prospect = (object) $duplicate_data;
          if ( empty( $prefill_profile ) && ! empty( $duplicate_data['profile_type'] ) ) {
            $prefill_profile = (string) $duplicate_data['profile_type'];
          }
        }
      }
      if ( ! $prospect && $prefill_profile ) {
        $prospect = (object) array( 'profile_type' => $prefill_profile );
      }
    }
    $search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

    // Liste complète : sert uniquement au sélecteur du modal « Ajouter RDV », qui doit
    // permettre de choisir n'importe quel prospect (hors bornage de la pagination).
    $prospects_all = $this->get_prospects();
    if ( '' !== $search ) {
      $prospects_all = array_values( array_filter( $prospects_all, function( $entry ) use ( $search ) {
        $haystack = implode( ' ', array(
          $entry->profile_type,
          isset( $entry->gender ) ? $entry->gender : '',
          $entry->first_name,
          $entry->last_name,
          isset( $entry->signer_first_name ) ? $entry->signer_first_name : '',
          isset( $entry->signer_last_name ) ? $entry->signer_last_name : '',
          $entry->company_name,
          isset( $entry->siret ) ? $entry->siret : '',
          isset( $entry->naf_code ) ? $entry->naf_code : '',
          $entry->phone,
          $entry->email,
          isset( $entry->signer_email ) ? $entry->signer_email : '',
          $entry->desired_training,
          $entry->assigned_to,
          $entry->source,
        ) );
        return false !== stripos( $haystack, $search );
      } ) );
    }

    // Liste paginée : alimente le tableau (bornage par page, SQL LIMIT/OFFSET). Sans
    // ?ppage on affiche la page 1 (25 prospects) puis la navigation sous le tableau.
    $prospect_page        = isset( $_GET['ppage'] ) ? absint( wp_unslash( $_GET['ppage'] ) ) : 1;
    $prospect_filters     = array();
    if ( '' !== $search ) {
      $prospect_filters['search'] = $search;
    }
    $prospect_page_result = $this->get_prospects_page( $prospect_page, 25, $prospect_filters );
    $prospects            = $prospect_page_result['items'];
    $prospect_pagination  = $prospect_page_result['pagination'];
    $new_prospect_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-prospects&action=new' ) : $this->portal_page_url( array( 'tab' => 'prospects', 'action' => 'new' ) );
    $followup_url = is_admin() ? $this->admin_prospect_followup_url() : $this->portal_page_url( array( 'tab' => 'prospect_followup' ) );
    $page_title = 'Prospect';
    if ( 'new' === $action ) {
      $page_title = 'Créer un prospect';
    } elseif ( 'edit' === $action ) {
      $page_title = 'Modifier un prospect';
    } elseif ( 'view' === $action ) {
      $page_title = 'Voir un prospect';
    }
    ?>
    <section class="acdc-section-head">
      <div>
        <h2><?php echo esc_html( $page_title ); ?></h2>
        <p>Suivi commercial des prospects, prise de contact et qualification du besoin avant recueil détaillé.</p>
      </div>
      <?php $current_prospect_id = isset( $_GET['prospect_id'] ) ? absint( $_GET['prospect_id'] ) : 0; ?>
      <?php if ( $current_prospect_id <= 0 && is_array( $prospect ) && ! empty( $prospect['id'] ) ) { $current_prospect_id = absint( $prospect['id'] ); } ?>
      <?php if ( $current_prospect_id <= 0 && is_object( $prospect ) && ! empty( $prospect->id ) ) { $current_prospect_id = absint( $prospect->id ); } ?>
      <?php $is_single_prospect_page = in_array( $action, array( 'view', 'edit' ), true ) && $current_prospect_id > 0; ?>
      <div class="acdc-inline-wrap">
        <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_export_prospects_csv' ), 'acdc_export_prospects_csv' ) ); ?>">⬇ Exporter CSV</a>
        <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_export_prospects_xlsx' ), 'acdc_export_prospects_xlsx' ) ); ?>">⬇ Exporter Excel</a>
        <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-prospects&action=import' ) : $this->portal_page_url( array( 'tab' => 'prospects', 'action' => 'import' ) ) ); ?>">⬆ Importer Excel</a>
        <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-calendar' ) : $this->portal_page_url( array( 'tab' => 'calendar' ) ) ); ?>">Calendrier RDV</a>
        <?php if ( $is_single_prospect_page ) : ?>
          <a class="acdc-button acdc-button-soft" href="#" data-acdc-prospect-rdv-open="1" data-prospect-id="<?php echo esc_attr( (string) $current_prospect_id ); ?>" onclick="if(window.acdcOpenProspectRdvModal){window.acdcOpenProspectRdvModal(this);return false;}">Nouveau rendez-vous</a>
        <?php else : ?>
          <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $followup_url ); ?>" data-acdc-prospect-list-rdv-open="1"<?php echo $current_prospect_id > 0 ? ' data-prospect-id="' . esc_attr( (string) $current_prospect_id ) . '"' : ''; ?>>Nouveau rendez-vous</a>
        <?php endif; ?>
        <?php if ( 'view' === $action && $prospect && ! empty( $prospect->email ) ) : ?>
        <button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-open="acdc-direct-email-modal-prospect-<?php echo (int) $prospect->id; ?>">&#x2709; Envoyer un e-mail</button>
        <?php endif; ?>
        <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $new_prospect_url ); ?>">Créer un prospect</a>
      </div>
    </section>
    <?php
    if ( 'import' === $action ) {
      $prospects_page_url   = is_admin() ? admin_url( 'admin.php?page=acdc-of-prospects' ) : $this->portal_page_url( array( 'tab' => 'prospects' ) );
      $prospects_tmpl_url   = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_prospects_xlsx_template' ), 'acdc_prospects_xlsx_template' );
      ?>
      <div class="acdc-panel acdc-form-panel">
        <h3>Importer des prospects (Excel)</h3>
        <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
          <?php wp_nonce_field( 'acdc_import_prospects_xlsx' ); ?>
          <input type="hidden" name="action" value="acdc_import_prospects_xlsx">
          <p><label>Fichier à importer</label><input type="file" name="prospects_file" accept=".xlsx"></p>
          <p class="acdc-help">Import Excel .xlsx uniquement. Les colonnes sont reconnues par leur en-tête (jamais par leur position) : ID prospect, Type de profil, Genre, Prénom, Nom, Entreprise, SIRET, E-mail, Téléphone, Adresse, Code postal, Ville, signataire, etc. Une colonne absente est ignorée sans erreur. Téléchargez le modèle ci-dessous pour le format exact.</p>
          <p class="acdc-help" style="margin-top:4px;">Clé anti-doublon : si la colonne <strong>ID prospect</strong> correspond à un prospect existant, il est mis à jour. Sinon l'<strong>e-mail</strong> exact est utilisé, puis le <strong>SIRET</strong> (entreprises). Si aucun ne correspond, un nouveau prospect est créé. Pour une entreprise, le SIRET doit contenir 14 chiffres.</p>
          <p class="acdc-checkbox-line" style="margin-top:10px;">
            <label class="acdc-switch">
              <input type="checkbox" name="overwrite_empty" value="1">
              <span class="acdc-switch-slider"></span>
            </label>
            <span>Écraser les champs vides — <small style="color:#8a6d2a;font-weight:500;">Décoché par défaut : les cellules vides ne remplaceront pas les valeurs déjà enregistrées.</small></span>
          </p>
          <p><a class="acdc-button acdc-button-secondary" href="<?php echo esc_url( $prospects_tmpl_url ); ?>">Télécharger le modèle Excel</a></p>
          <div class="acdc-inline-actions"><a class="acdc-button acdc-button-link" href="<?php echo esc_url( $prospects_page_url ); ?>">Annuler</a><button type="submit" class="acdc-button acdc-button-primary">Importer les prospects</button></div>
        </form>
      </div>
      <?php
      return;
    }
    if ( in_array( $action, array( 'new', 'edit', 'view' ), true ) ) {
      $this->render_front_prospect_form( $prospect, 'view' === $action );
    }

    if ( $is_single_prospect_page ) {
      $prospect_id_for_modal = $current_prospect_id;
      $timezone_label = wp_timezone_string() ? wp_timezone_string() : 'Europe/Paris';
      ?>

      <div class="acdc-modal-shell" id="acdc-prospect-rdv-modal" hidden>
        <div class="acdc-modal-backdrop" data-acdc-prospect-rdv-close></div>
        <div class="acdc-modal-dialog acdc-modal-dialog-medium">
          <div class="acdc-modal-header"><h4>AJOUTER RDV</h4><button type="button" class="acdc-modal-close" data-acdc-prospect-rdv-close aria-label="Fermer">&times;</button></div>
          <div class="acdc-modal-body acdc-modal-body-padding">
            <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
              <?php wp_nonce_field( 'acdc_save_prospect_rdv' ); ?>
              <input type="hidden" name="action" value="acdc_save_prospect_rdv">
              <input type="hidden" name="prospect_id" value="<?php echo (int) $prospect_id_for_modal; ?>">
              <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-prospects"><?php endif; ?>
              <div class="acdc-contract-grid">
                <div class="acdc-contract-label">RDV <span class="acdc-required">*</span></div>
                <div><div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;"><input type="datetime-local" name="rdv[rdv_at]" required><span><?php echo esc_html( $timezone_label ); ?></span></div></div>
              </div>
              <div class="acdc-contract-grid">
                <div class="acdc-contract-label">Compléments d’information</div>
                <div>
                  <div class="acdc-mini-editor-toolbar">
                    <button type="button" class="acdc-mini-editor-button" data-editor-action="bold"><strong>B</strong></button>
                    <button type="button" class="acdc-mini-editor-button" data-editor-action="italic"><em>I</em></button>
                    <button type="button" class="acdc-mini-editor-button" data-editor-action="strike"><span style="text-decoration:line-through;">S</span></button>
                    <button type="button" class="acdc-mini-editor-button" data-editor-action="link">🔗</button>
                    <span style="flex:1 1 auto;"></span>
                    <button type="button" class="acdc-mini-editor-button" data-editor-action="undo">↶</button>
                    <button type="button" class="acdc-mini-editor-button" data-editor-action="redo">↷</button>
                  </div>
                  <textarea rows="5" name="rdv[info_html]" data-acdc-prospect-rdv-editor></textarea>
                  <p class="acdc-field-help">Ce texte sera inséré dans les e-mails de confirmation de rendez-vous et de rappel envoyés au prospect.</p>
                </div>
              </div>
              <div class="acdc-contract-grid">
                <div class="acdc-contract-label">Notifier (e-mail)</div>
                <div><p class="acdc-checkbox-line"><label class="acdc-switch"><input type="checkbox" name="rdv[notify_email]" value="1" checked><span class="acdc-switch-slider"></span></label></p><p class="acdc-field-help">En désactivant cette option, aucun mail ne sera envoyé au prospect.</p></div>
              </div>
              <div class="acdc-contract-grid">
                <div class="acdc-contract-label">Type de rendez-vous</div>
                <div>
                  <select name="rdv[meeting_mode]" data-acdc-meeting-mode required>
                    <option value="">Sélectionner</option>
                    <option value="visioconference">Visioconférence</option>
                    <option value="telephonique">Téléphonique</option>
                    <option value="au_bureau">Au bureau</option>
                    <option value="chez_le_client">Chez le client</option>
                  </select>
                  <p class="acdc-field-help">Cette information détermine le message inséré dans l’e-mail de confirmation.</p>
                  <div class="acdc-meeting-link-wrap" data-acdc-meeting-link-wrap style="display:none;" hidden>
                    <input type="url" name="rdv[meeting_link]" placeholder="Lien de la visioconférence">
                    <p class="acdc-field-help">Ce lien sera inséré dans l’e-mail envoyé au prospect.</p>
                  </div>
                </div>
              </div>
              <p class="acdc-actions-end">
                <button type="button" class="acdc-button acdc-button-soft" data-acdc-prospect-rdv-close>Annuler</button>
                <button type="submit" class="acdc-button acdc-button-primary">Exécuter l'action</button>
              </p>
            </form>
          </div>
        </div>
      </div>
      <style>
        #acdc-prospect-rdv-modal{background:transparent!important;}
        #acdc-prospect-rdv-modal .acdc-modal-backdrop{position:fixed!important;inset:0!important;z-index:1!important;background:rgba(11,7,6,.45)!important;opacity:1!important;pointer-events:auto!important;cursor:pointer;}
        #acdc-prospect-rdv-modal .acdc-modal-dialog{position:relative!important;z-index:2!important;opacity:1!important;filter:none!important;background:#ffffff!important;pointer-events:auto!important;}
        #acdc-prospect-rdv-modal .acdc-modal-header,#acdc-prospect-rdv-modal .acdc-modal-body{opacity:1!important;background:#ffffff!important;pointer-events:auto!important;}
        #acdc-prospect-rdv-modal .acdc-modal-close,#acdc-prospect-rdv-modal [data-acdc-prospect-rdv-close]{pointer-events:auto!important;cursor:pointer;}
        #acdc-prospect-rdv-modal .acdc-modal-close *,#acdc-prospect-rdv-modal [data-acdc-prospect-rdv-close] *{pointer-events:none;}
        html.acdc-prospect-view-scroll-ready,
        body.acdc-prospect-view-scroll-ready:not(.acdc-modal-open){overflow:auto !important;height:auto !important;}
      </style>
      <script>
        (function(){
          var modal=document.getElementById('acdc-prospect-rdv-modal');
          if(!modal){return;}
          if(modal.getAttribute('data-acdc-rdv-modal-bound')==='1'){return;}
          modal.setAttribute('data-acdc-rdv-modal-bound','1');
          function openModal(){
            modal.hidden=false;
            document.body.classList.remove('acdc-prospect-view-scroll-ready');
            document.documentElement.classList.remove('acdc-prospect-view-scroll-ready');
            document.body.style.setProperty('overflow','hidden','important');
            document.documentElement.style.setProperty('overflow','hidden','important');
            document.body.classList.add('acdc-modal-open');
          }
          function closeModal(){
            modal.hidden=true;
            document.body.classList.remove('acdc-modal-open');
            document.body.classList.add('acdc-prospect-view-scroll-ready');
            document.documentElement.classList.add('acdc-prospect-view-scroll-ready');
            document.body.style.setProperty('overflow','auto','important');
            document.documentElement.style.setProperty('overflow','auto','important');
          }
          window.acdcOpenProspectRdvModal = function(trigger){
            openModal();
          };
          document.addEventListener('click', function(e){
            var target = e.target;
            if(!target || !target.closest){ return; }
            var opener = target.closest('[data-acdc-prospect-rdv-open]');
            if(opener){
              e.preventDefault();
              e.stopPropagation();
              openModal();
              return;
            }
            if(modal.hidden){ return; }
            var closer = target.closest('[data-acdc-prospect-rdv-close]');
            if(closer && modal.contains(closer)){
              e.preventDefault();
              e.stopPropagation();
              closeModal();
              return;
            }
            if(target === modal){
              e.preventDefault();
              closeModal();
            }
          }, true);
          document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && !modal.hidden){ closeModal(); } });
          if(window.location.search.indexOf('open_rdv=1')!==-1){ openModal(); }
        window.setTimeout(function(){
          document.querySelectorAll('.acdc-js-alert-pulse').forEach(function(node){
            node.classList.add('is-active');
          });
        }, 180);
          var editor=modal.querySelector('[data-acdc-prospect-rdv-editor]');
          var meetingMode=modal.querySelector('select[name="rdv[meeting_mode]"]');
          var meetingLinkWrap=modal.querySelector('[data-acdc-meeting-link-wrap]');
          var meetingLinkInput=modal.querySelector('input[name="rdv[meeting_link]"]');
          function syncMeetingMode(){
            if(!meetingMode || !meetingLinkWrap || !meetingLinkInput){ return; }
            var isVisio = meetingMode.value === 'visioconference';
            meetingLinkWrap.hidden = !isVisio;
            meetingLinkWrap.style.display = isVisio ? 'block' : 'none';
            meetingLinkInput.required = isVisio;
            if(!isVisio){ meetingLinkInput.value = ''; }
          }
          if(meetingMode){
            meetingMode.addEventListener('change', syncMeetingMode);
            syncMeetingMode();
          }
          function wrapSelection(before, after){ if(!editor){return;} var start=editor.selectionStart||0,end=editor.selectionEnd||0,value=editor.value||''; var selected=value.substring(start,end); var replacement=before + selected + after; editor.value=value.substring(0,start) + replacement + value.substring(end); editor.focus(); editor.setSelectionRange(start + before.length, start + replacement.length - after.length); }
          modal.querySelectorAll('[data-editor-action]').forEach(function(btn){ btn.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); var action=btn.getAttribute('data-editor-action'); if(action==='bold'){ wrapSelection('<strong>','</strong>'); } else if(action==='italic'){ wrapSelection('<em>','</em>'); } else if(action==='strike'){ wrapSelection('<span style="text-decoration:line-through;">','</span>'); } else if(action==='link'){ var url=window.prompt('URL du lien'); if(url){ wrapSelection('<a href="'+url.replace(/"/g,'')+'">','</a>'); } } else if(action==='undo'){ document.execCommand('undo'); editor.focus(); } else if(action==='redo'){ document.execCommand('redo'); editor.focus(); } }); });
  
        /* hotfix19 — Toggle CSS interactif */
        document.addEventListener('change', function(e) {
          var cb = e.target;
          if (!cb || cb.type !== 'checkbox') { return; }
          var wrap = cb.closest ? cb.closest('.acdc-toggle-wrap') : null;
          if (!wrap) { return; }
          var track = wrap.querySelector('.acdc-toggle-track');
          if (!track) { return; }
          var thumb = track.querySelector('span');
          var stateEl = wrap.querySelector('.acdc-toggle-state');
          if (cb.checked) {
            track.style.background = '#d6a353';
            if (thumb) { thumb.style.transform = 'translateX(20px)'; }
            if (stateEl) { stateEl.textContent = 'Oui'; stateEl.style.color = '#8b5b23'; }
          } else {
            track.style.background = '#d9dfe8';
            if (thumb) { thumb.style.transform = 'translateX(0)'; }
            if (stateEl) { stateEl.textContent = 'Non'; stateEl.style.color = '#4b5d76'; }
          }
        });
      })();
      </script>
      <?php
    }

    if ( in_array( $action, array( 'view', 'edit' ), true ) ) {
      return;
    }
    ?>
    <div class="acdc-panel acdc-mb-18">
      <form method="get" action="">
        <?php if ( is_admin() ) : ?>
          <input type="hidden" name="page" value="acdc-of-prospects">
        <?php endif; ?>
        <input type="hidden" name="tab" value="prospects">
        <div class="acdc-inline-wrap acdc-inline-wrap-center">
          <input type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="Rechercher" style="max-width:420px;">
          <button type="submit" class="acdc-button acdc-button-soft">Rechercher</button>
        </div>
      </form>
    </div>
    <div class="acdc-panel">
      <div class="acdc-bulk-toolbar" data-acdc-bulk-toolbar hidden>
        <span class="acdc-bulk-count-label"><strong data-acdc-bulk-count>0</strong> prospect(s) sélectionné(s)</span>
        <label class="acdc-bulk-field">
          <span>Action groupée</span>
          <select data-acdc-bulk-action>
            <option value="">Choisir une action…</option>
            <option value="status">Changer le statut</option>
            <option value="assign">Attribuer à</option>
            <option value="delete">Supprimer</option>
          </select>
        </label>
        <label class="acdc-bulk-field" data-acdc-bulk-status-field hidden>
          <span>Nouveau statut</span>
          <select data-acdc-bulk-status>
            <?php foreach ( $this->get_prospect_status_options() as $status_key => $status_label ) : ?>
              <option value="<?php echo esc_attr( $status_key ); ?>"><?php echo esc_html( $status_label ); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="acdc-bulk-field" data-acdc-bulk-assign-field hidden>
          <span>Responsable</span>
          <select data-acdc-bulk-assign>
            <?php foreach ( $this->get_assignment_options() as $assign_key => $assign_label ) : ?>
              <option value="<?php echo esc_attr( $assign_key ); ?>"><?php echo esc_html( '' === $assign_key ? 'Non attribué' : $assign_label ); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button type="button" class="acdc-button acdc-button-primary" data-acdc-bulk-apply disabled>Appliquer</button>
      </div>
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acdc-bulk-form" data-acdc-bulk-form hidden aria-hidden="true">
        <?php wp_nonce_field( 'acdc_bulk_prospect_action' ); ?>
        <input type="hidden" name="action" value="acdc_bulk_prospect_action">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-prospects"><?php endif; ?>
        <input type="hidden" name="return_tab" value="prospects">
        <input type="hidden" name="bulk_action" value="" data-acdc-bulk-form-action>
        <input type="hidden" name="bulk_value" value="" data-acdc-bulk-form-value>
        <span data-acdc-bulk-ids></span>
      </form>
      <div class="acdc-table-wrap">
        <table class="acdc-table acdc-table-prospects" data-acdc-table-id="crm-prospects-list">
          <thead>
            <tr>
              <th class="acdc-prospect-col-check"><input type="checkbox" data-acdc-bulk-select-all aria-label="Tout sélectionner"></th>
              <th>Profil</th>
              <th>Nom / prénom / entreprise</th>
              <th class="acdc-prospect-col-email">E-mail</th>
              <th class="acdc-prospect-col-phone">Téléphone</th>
              <th class="acdc-prospect-col-training">Formation souhaitée</th>
              <th>Assigné à</th>
              <th>Statut</th>
              <th>Rendez-vous</th>
              <th>Source</th>
              <th>Ajouté le</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if ( ! empty( $prospects ) ) : ?>
            <?php foreach ( $prospects as $entry ) : ?>
              <?php
              $base = is_admin() ? admin_url( 'admin.php?page=acdc-of-prospects' ) : $this->portal_page_url( array( 'tab' => 'prospects' ) );
              $follow_base = is_admin() ? $this->admin_prospect_followup_url() : $this->portal_page_url( array( 'tab' => 'prospect_followup' ) );
              $copy_list = $this->get_prospect_copy_recipients( $entry );
              ?>
              <?php
              $view_url = add_query_arg( array( 'action' => 'view', 'item_id' => $entry->id ), $base );
              $edit_url = add_query_arg( array( 'action' => 'edit', 'item_id' => $entry->id ), $base );
              $followup_view_url = add_query_arg( array( 'action' => 'view', 'item_id' => $entry->id ), $follow_base );
              $delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_delete_prospect&prospect_id=' . $entry->id . ( is_admin() ? '&page=acdc-of-prospects' : '' ) ), 'acdc_delete_prospect_' . $entry->id );
              $menu_urls = array(
                'Répliquer'            => add_query_arg( array( 'action' => 'new', 'duplicate_id' => $entry->id, 'profile' => $entry->profile_type ), $base ),
                'Ajouter un rendez-vous' => add_query_arg( array( 'action' => 'view', 'item_id' => $entry->id, 'open_rdv' => 1, 'open_from_prospect_list' => 1 ), $follow_base ),
                'Recueil des besoins' => is_admin() ? admin_url( 'admin.php?page=acdc-of-needs&action=new&prospect_id=' . (int) $entry->id ) : $this->portal_page_url( array( 'tab' => 'needs', 'action' => 'new', 'prospect_id' => (int) $entry->id ) ),
                /* ACDC 3.25.241 — DEVIS, CONVENTION ET INSCRIPTION RETIRÉS D'ICI.
                   Une fiche prospect ne porte ni dates, ni durée, ni effectif,
                   ni tarif : ces trois écrans s'ouvraient donc à moitié vides et
                   il fallait tout ressaisir à la main — avec le risque que la
                   ressaisie diverge de ce qui serait annoncé plus tard.
                   Ces données naissent à la proposition commerciale. Le seul
                   enchaînement qui mène quelque part depuis un prospect est le
                   recueil des besoins, qui reste juste au-dessus. */
              );
              ?>
              <tr>
                <td class="acdc-prospect-col-check"><input type="checkbox" data-acdc-bulk-checkbox value="<?php echo (int) $entry->id; ?>" aria-label="Sélectionner ce prospect"></td>
                <td><?php echo esc_html( $entry->profile_type ?: '—' ); ?></td>
                <td>
                  <strong><?php echo esc_html( $this->get_prospect_display_name( $entry ) ); ?></strong><br>
                  <?php
                  $company_display = $this->get_prospect_company_display_name( $entry );
                  $company_display_html = esc_html( $company_display );
                  if ( '' !== trim( (string) $company_display ) ) {
                    $company_display_html = preg_replace( '/\s*(\([^)]*\))\s*$/u', '<br><span class="acdc-prospect-company-parenthesis">$1</span>', $company_display_html );
                  }
                  ?>
                  <small><?php echo wp_kses( $company_display_html, array( 'br' => array(), 'span' => array( 'class' => array() ) ) ); ?></small>
                </td>
                <td class="acdc-prospect-col-email"><span class="acdc-prospect-nowrap"><?php echo esc_html( ( $this->is_individual_prospect_profile( $entry->profile_type ) ? $entry->email : ( ! empty( $entry->signer_email ) ? $entry->signer_email : $entry->email ) ) ?: '—' ); ?></span></td>
                <td class="acdc-prospect-col-phone"><span class="acdc-prospect-nowrap"><?php echo esc_html( ( $this->is_individual_prospect_profile( $entry->profile_type ) ? $entry->phone : ( ! empty( $entry->company_phone ) ? $entry->company_phone : ( ! empty( $entry->signer_phone ) ? $entry->signer_phone : $entry->phone ) ) ) ?: '—' ); ?></span></td>
                <td class="acdc-prospect-col-training"><span class="acdc-prospect-training-text"><?php
                  /* hotfix9 — Afficher thématique + formation si disponibles */
                  $disp_th = ! empty( $entry->desired_thematique ) ? (string) $entry->desired_thematique : '';
                  $disp_fo = ! empty( $entry->desired_training )   ? (string) $entry->desired_training   : '';
                  if ( $disp_th && $disp_fo ) {
                    echo esc_html( $disp_fo );
                  } elseif ( $disp_fo ) {
                    echo esc_html( $disp_fo );
                  } elseif ( $disp_th ) {
                    echo esc_html( $disp_th );
                  } else {
                    echo '&#192; d&#233;finir';
                  }
                ?></span></td>
                <td><?php echo esc_html( $entry->assigned_to ?: '—' ); ?></td>
                <td><?php echo $this->get_prospect_status_badge_html( $entry->status ?: 'À traiter' ); ?></td>
                <td><?php echo esc_html( $entry->rdv_at ? mysql2date( 'd/m/Y H\hi', $entry->rdv_at ) : '—' ); ?></td>
                <td><?php echo esc_html( $entry->source ?: '—' ); ?></td>
                <td><?php echo esc_html( $entry->created_at ? mysql2date( 'd/m/Y H:i', $entry->created_at ) : '—' ); ?></td>
                <td class="acdc-actions-cell-icons">
                  <div class="acdc-groups-actions-inline" data-acdc-prospect-actions>
                    <div class="acdc-prospect-action-menu">
                      <button type="button"
                              class="acdc-row-action-icon acdc-prospect-action-trigger"
                              data-acdc-prospect-menu-toggle
                              data-acdc-iconized="1"
                              aria-haspopup="true"
                              aria-expanded="false"
                              aria-label="Plus d'actions"
                              title="Plus d'actions">
                        <?php echo $this->render_inline_icon( 'more-horizontal', 25 ); ?>
                        <span class="acdc-action-hub-sr screen-reader-text">Plus d'actions</span>
                      </button>
                      <div class="acdc-prospect-action-dropdown" data-acdc-prospect-menu hidden>
                        <?php foreach ( $menu_urls as $menu_label => $menu_url ) : ?>
                          <a href="<?php echo esc_url( $menu_url ); ?>" class="acdc-prospect-action-item"><?php echo esc_html( $menu_label ); ?></a>
                        <?php endforeach; ?>
                      </div>
                    </div>
                    <a href="<?php echo esc_url( $view_url ); ?>"
                       class="acdc-row-action-icon acdc-row-view-link"
                       data-acdc-iconized="1"
                       title="Voir le prospect"
                       aria-label="Voir">
                      <?php echo $this->render_inline_icon( 'view', 25 ); ?>
                      <span class="acdc-action-hub-sr screen-reader-text">Voir</span>
                    </a>
                    <a href="<?php echo esc_url( $edit_url ); ?>"
                       class="acdc-row-action-icon acdc-row-edit-link"
                       data-acdc-iconized="1"
                       title="Modifier le prospect"
                       aria-label="Modifier">
                      <?php echo $this->render_inline_icon( 'edit-pencil', 25 ); ?>
                      <span class="acdc-action-hub-sr screen-reader-text">Modifier</span>
                    </a>
                    <a href="<?php echo esc_url( $followup_view_url ); ?>"
                       class="acdc-row-action-icon"
                       data-acdc-iconized="1"
                       title="Suivi commercial"
                       aria-label="Suivi commercial">
                      <?php echo $this->render_inline_icon( 'clipboard', 25 ); ?>
                      <span class="acdc-action-hub-sr screen-reader-text">Suivi commercial</span>
                    </a>
                    <a href="<?php echo esc_url( $delete_url ); ?>"
                       class="acdc-row-action-icon acdc-row-delete-link"
                       data-acdc-iconized="1"
                       title="Supprimer le prospect"
                       aria-label="Supprimer"
                       onclick="return confirm('Supprimer ce prospect ?');">
                      <?php echo $this->render_inline_icon( 'trash-bin', 25 ); ?>
                      <span class="acdc-action-hub-sr screen-reader-text">Supprimer</span>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else : ?>
            <tr><td colspan="12">Aucun prospect enregistré.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ( $prospect_pagination['pages'] > 1 ) : ?>
        <?php
        $prospect_prev_url = add_query_arg( 'ppage', max( 1, $prospect_pagination['page'] - 1 ) );
        $prospect_next_url = add_query_arg( 'ppage', min( $prospect_pagination['pages'], $prospect_pagination['page'] + 1 ) );
        ?>
        <nav class="acdc-pagination" aria-label="Pagination des prospects" style="display:flex;align-items:center;justify-content:center;gap:14px;margin-top:16px;flex-wrap:wrap;">
          <?php if ( $prospect_pagination['has_prev'] ) : ?>
            <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $prospect_prev_url ); ?>" rel="prev" aria-label="Page précédente">&lsaquo; Précédent</a>
          <?php else : ?>
            <span class="acdc-button acdc-button-soft" aria-disabled="true" style="opacity:.5;cursor:default;">&lsaquo; Précédent</span>
          <?php endif; ?>
          <span class="acdc-pagination-status" aria-label="<?php echo esc_attr( sprintf( 'Page %d sur %d', (int) $prospect_pagination['page'], (int) $prospect_pagination['pages'] ) ); ?>">Page <?php echo (int) $prospect_pagination['page']; ?> / <?php echo (int) $prospect_pagination['pages']; ?></span>
          <?php if ( $prospect_pagination['has_next'] ) : ?>
            <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $prospect_next_url ); ?>" rel="next" aria-label="Page suivante">Suivant &rsaquo;</a>
          <?php else : ?>
            <span class="acdc-button acdc-button-soft" aria-disabled="true" style="opacity:.5;cursor:default;">Suivant &rsaquo;</span>
          <?php endif; ?>
        </nav>
      <?php endif; ?>
    </div>

    <div class="acdc-modal-shell" id="acdc-prospect-list-rdv-modal" hidden>
      <div class="acdc-modal-backdrop" data-acdc-prospect-list-rdv-close></div>
      <div class="acdc-modal-dialog acdc-modal-dialog-medium">
        <div class="acdc-modal-header"><h4>AJOUTER RDV</h4><button type="button" class="acdc-modal-close" data-acdc-prospect-list-rdv-close aria-label="Fermer">&times;</button></div>
        <div class="acdc-modal-body acdc-modal-body-padding">
          <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'acdc_save_prospect_rdv' ); ?>
            <input type="hidden" name="action" value="acdc_save_prospect_rdv">
            <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-prospects"><?php endif; ?>
            <input type="hidden" name="return_tab" value="prospects">
            <div class="acdc-contract-grid">
              <div class="acdc-contract-label">Prospect <span class="acdc-required">*</span></div>
              <div data-acdc-quick-rdv-prospect-field>
                <select name="prospect_id" data-acdc-prospect-list-rdv-select required>
                  <option value="">Sélectionner</option>
                  <?php foreach ( $prospects_all as $entry ) : ?>
                    <option value="<?php echo (int) $entry->id; ?>"><?php echo esc_html( $this->get_prospect_contact_person_name( $entry ) . ' — ' . $this->get_prospect_company_display_name( $entry ) ); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="acdc-contract-grid">
              <div class="acdc-contract-label">RDV <span class="acdc-required">*</span></div>
              <div><div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;"><input type="datetime-local" name="rdv[rdv_at]" required><span><?php echo esc_html( wp_timezone_string() ? wp_timezone_string() : 'Europe/Paris' ); ?></span></div></div>
            </div>
            <div class="acdc-contract-grid">
              <div class="acdc-contract-label">Compléments d’information</div>
              <div>
                <div class="acdc-mini-editor-toolbar">
                  <button type="button" class="acdc-mini-editor-button" data-editor-action="bold"><strong>B</strong></button>
                  <button type="button" class="acdc-mini-editor-button" data-editor-action="italic"><em>I</em></button>
                  <button type="button" class="acdc-mini-editor-button" data-editor-action="strike"><span style="text-decoration:line-through;">S</span></button>
                  <button type="button" class="acdc-mini-editor-button" data-editor-action="link">🔗</button>
                  <span style="flex:1 1 auto;"></span>
                  <button type="button" class="acdc-mini-editor-button" data-editor-action="undo">↶</button>
                  <button type="button" class="acdc-mini-editor-button" data-editor-action="redo">↷</button>
                </div>
                <textarea rows="5" name="rdv[info_html]" data-acdc-prospect-list-rdv-editor></textarea>
                <p class="acdc-field-help">Ce texte sera inséré dans les e-mails de confirmation de rendez-vous et de rappel envoyés au prospect.</p>
              </div>
            </div>
            <div class="acdc-contract-grid">
              <div class="acdc-contract-label">Notifier (e-mail)</div>
              <div><p class="acdc-checkbox-line"><label class="acdc-switch"><input type="checkbox" name="rdv[notify_email]" value="1" checked><span class="acdc-switch-slider"></span></label></p><p class="acdc-field-help">En désactivant cette option, aucun mail ne sera envoyé au prospect.</p></div>
            </div>
            <div class="acdc-contract-grid">
              <div class="acdc-contract-label">Type de rendez-vous</div>
              <div>
                <select name="rdv[meeting_mode]" data-acdc-prospect-list-meeting-mode required>
                  <option value="">Sélectionner</option>
                  <option value="visioconference">Visioconférence</option>
                  <option value="telephonique">Téléphonique</option>
                  <option value="au_bureau">Au bureau</option>
                  <option value="chez_le_client">Chez le client</option>
                </select>
                <p class="acdc-field-help">Cette information détermine le message inséré dans l’e-mail de confirmation.</p>
                <div class="acdc-meeting-link-wrap" data-acdc-prospect-list-meeting-link-wrap style="display:none;" hidden>
                  <input type="url" name="rdv[meeting_link]" placeholder="Lien de la visioconférence">
                  <p class="acdc-field-help">Ce lien sera inséré dans l’e-mail envoyé au prospect.</p>
                </div>
              </div>
            </div>
            <p class="acdc-actions-end">
              <button type="button" class="acdc-button acdc-button-soft" data-acdc-prospect-list-rdv-close>Annuler</button>
              <button type="submit" class="acdc-button acdc-button-primary">Exécuter l'action</button>
            </p>
          </form>
        </div>
      </div>
    </div>
    <style>
      #acdc-prospect-list-rdv-modal{background:transparent!important;}
      #acdc-prospect-list-rdv-modal .acdc-modal-backdrop{position:fixed!important;inset:0!important;z-index:1!important;background:rgba(11,7,6,.45)!important;opacity:1!important;}
      #acdc-prospect-list-rdv-modal .acdc-modal-dialog{position:relative!important;z-index:2!important;opacity:1!important;filter:none!important;background:#ffffff!important;}
      #acdc-prospect-list-rdv-modal[hidden],#acdc-prospect-list-rdv-modal[hidden] .acdc-modal-backdrop,#acdc-prospect-list-rdv-modal[hidden] .acdc-modal-dialog{display:none!important;visibility:hidden!important;pointer-events:none!important;}
      #acdc-prospect-list-rdv-modal .acdc-modal-header,#acdc-prospect-list-rdv-modal .acdc-modal-body{opacity:1!important;background:#ffffff!important;}
      .acdc-table-prospects,.acdc-table-prospects tbody,.acdc-table-prospects tr,.acdc-table-prospects td{overflow:visible !important;}
      /* hotfix22 — acdc-prospect-icon-link supprimé, remplacé par acdc-row-action-icon standard */
      .acdc-groups-actions-inline .acdc-prospect-action-menu{display:inline-flex;align-items:center;position:relative;}
      .acdc-prospect-action-menu{position:relative;display:inline-flex;align-items:center;}
      .acdc-prospect-action-dropdown{position:fixed;top:0;left:0;min-width:250px;background:#fff;border:1px solid #dce4ec;border-radius:10px;box-shadow:0 10px 30px rgba(28,44,64,.12);padding:14px 0;z-index:99999;}
      .acdc-prospect-action-item{display:block;padding:12px 26px;color:#1E4777;text-decoration:none;font-size:16px;line-height:1.35;}
      .acdc-prospect-action-item:hover{background:#F6F8FB;color:#1e4777;}
            .acdc-table-prospects{table-layout:fixed;width:100%;min-width:2336px;}
      .acdc-table-prospects th,.acdc-table-prospects td{box-sizing:border-box;vertical-align:middle;overflow:hidden;text-overflow:clip;padding-left:12px;padding-right:12px;}
      /* Colonne case à cocher (sélection groupée) — insérée en tête, décale les nth-child suivants de +1. */
      .acdc-table-prospects th:nth-child(1),.acdc-table-prospects td:nth-child(1){width:46px;min-width:46px;max-width:46px;white-space:nowrap;text-align:center;padding-left:8px;padding-right:8px;}
      .acdc-table-prospects .acdc-prospect-col-check{text-align:center;}
      .acdc-table-prospects .acdc-prospect-col-check input[type="checkbox"]{width:17px;height:17px;cursor:pointer;accent-color:#c99a2d;margin:0;}
      .acdc-table-prospects th:nth-child(2),.acdc-table-prospects td:nth-child(2){width:108px;min-width:108px;max-width:108px;white-space:nowrap;}
      .acdc-table-prospects th:nth-child(3),.acdc-table-prospects td:nth-child(3){width:230px;min-width:230px;max-width:230px;white-space:normal;line-height:1.35;}
      .acdc-table-prospects .acdc-prospect-col-email{width:210px;min-width:210px;max-width:210px;white-space:nowrap;padding-left:8px;padding-right:10px;}
      .acdc-table-prospects .acdc-prospect-col-phone{width:168px;min-width:168px;max-width:168px;white-space:nowrap;padding-left:8px;padding-right:32px;}
      .acdc-table-prospects .acdc-prospect-col-training{width:235px;min-width:235px;max-width:235px;white-space:normal;line-height:1.35;word-break:normal;overflow-wrap:normal;padding-left:34px;padding-right:12px;}
      .acdc-table-prospects th:nth-child(8),.acdc-table-prospects td:nth-child(8){width:170px;min-width:170px;max-width:170px;white-space:nowrap;}
      .acdc-table-prospects th:nth-child(9),.acdc-table-prospects td:nth-child(9){width:178px;min-width:178px;max-width:178px;white-space:nowrap;}
      .acdc-table-prospects th:nth-child(10),.acdc-table-prospects td:nth-child(10){width:146px;min-width:146px;max-width:146px;white-space:nowrap;}
      .acdc-table-prospects th:nth-child(11),.acdc-table-prospects td:nth-child(11){width:146px;min-width:146px;max-width:146px;white-space:nowrap;}
      .acdc-table-prospects th:nth-child(12),.acdc-table-prospects td:nth-child(12){width:174px;min-width:174px;max-width:174px;white-space:nowrap;}
      .acdc-table-prospects th:nth-child(13),.acdc-table-prospects td:nth-child(13){width:150px;min-width:150px;max-width:150px;white-space:nowrap;}
      .acdc-table-prospects th:nth-child(14),.acdc-table-prospects td:nth-child(14){width:151px;min-width:151px;max-width:151px;white-space:nowrap;}
      /* Barre d'actions groupées. */
      .acdc-bulk-toolbar{display:flex;align-items:flex-end;flex-wrap:wrap;gap:16px;padding:14px 16px;margin-bottom:14px;background:#fbf7ef;border:1px solid #e7d8b6;border-radius:12px;}
      .acdc-bulk-toolbar[hidden]{display:none;}
      .acdc-bulk-count-label{align-self:center;color:#1E4777;font-size:14px;}
      .acdc-bulk-count-label strong{font-size:16px;}
      .acdc-bulk-field{display:flex;flex-direction:column;gap:4px;font-size:12px;color:#4b5d76;}
      .acdc-bulk-field[hidden]{display:none;}
      .acdc-bulk-field select{height:40px;min-width:210px;border:1px solid var(--acdc-border,#dce4ec);border-radius:10px;padding:0 12px;color:#1c2c40;background:#fff;}
      .acdc-bulk-toolbar .acdc-button[disabled]{opacity:.5;cursor:not-allowed;}
      .acdc-table-prospects .acdc-prospect-nowrap{display:inline-block;max-width:100%;white-space:nowrap;word-break:normal;overflow-wrap:normal;overflow:visible;text-overflow:clip;}
      .acdc-table-prospects .acdc-prospect-training-text{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;max-height:38px;overflow:hidden;white-space:normal;line-height:1.35;word-break:normal;overflow-wrap:break-word;}
      .acdc-table-prospects .acdc-prospect-copy-text{display:inline-block;max-width:100%;white-space:nowrap;overflow:visible;text-overflow:clip;}
      .acdc-table-prospects .acdc-prospect-company-parenthesis{display:inline-block;margin-top:2px;}
      .acdc-table-prospects td:last-child > a:not(.acdc-row-action-icon){display:none !important;}
    </style>
    <script>
      /* Actions groupées sur les prospects (sélection par case + tout sélectionner). */
      (function(){
        if(window.__acdcProspectBulkActions){ return; }
        window.__acdcProspectBulkActions = true;
        function q(sel, root){ return (root || document).querySelector(sel); }
        function qa(sel, root){ return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
        function checkboxes(){ return qa('[data-acdc-bulk-checkbox]'); }
        function selectedIds(){ return checkboxes().filter(function(c){ return c.checked; }).map(function(c){ return c.value; }); }
        function refresh(){
          var boxes = checkboxes();
          var checked = boxes.filter(function(c){ return c.checked; });
          var count = checked.length;
          var countEl = q('[data-acdc-bulk-count]');
          if(countEl){ countEl.textContent = String(count); }
          var toolbar = q('[data-acdc-bulk-toolbar]');
          if(toolbar){ toolbar.hidden = count === 0; }
          var actionSel = q('[data-acdc-bulk-action]');
          var applyBtn = q('[data-acdc-bulk-apply]');
          if(applyBtn){ applyBtn.disabled = count === 0 || !actionSel || !actionSel.value; }
          var selectAll = q('[data-acdc-bulk-select-all]');
          if(selectAll){
            selectAll.checked = boxes.length > 0 && count === boxes.length;
            selectAll.indeterminate = count > 0 && count < boxes.length;
          }
        }
        document.addEventListener('change', function(e){
          var t = e.target;
          if(!t || !t.matches){ return; }
          if(t.matches('[data-acdc-bulk-select-all]')){
            var on = t.checked;
            checkboxes().forEach(function(c){ c.checked = on; });
            refresh();
            return;
          }
          if(t.matches('[data-acdc-bulk-checkbox]')){ refresh(); return; }
          if(t.matches('[data-acdc-bulk-action]')){
            var statusField = q('[data-acdc-bulk-status-field]');
            var assignField = q('[data-acdc-bulk-assign-field]');
            if(statusField){ statusField.hidden = t.value !== 'status'; }
            if(assignField){ assignField.hidden = t.value !== 'assign'; }
            refresh();
            return;
          }
        });
        document.addEventListener('click', function(e){
          var btn = e.target.closest ? e.target.closest('[data-acdc-bulk-apply]') : null;
          if(!btn){ return; }
          e.preventDefault();
          var ids = selectedIds();
          if(!ids.length){ return; }
          var actionSel = q('[data-acdc-bulk-action]');
          var action = actionSel ? actionSel.value : '';
          if(!action){ return; }
          var value = '';
          if(action === 'status'){ var s = q('[data-acdc-bulk-status]'); value = s ? s.value : ''; }
          else if(action === 'assign'){ var a = q('[data-acdc-bulk-assign]'); value = a ? a.value : ''; }
          var msg = '';
          if(action === 'delete'){ msg = 'Supprimer les ' + ids.length + ' prospect(s) sélectionné(s) ? Cette action est irréversible.'; }
          else if(action === 'status'){ msg = 'Appliquer ce statut aux ' + ids.length + ' prospect(s) sélectionné(s) ?'; }
          else if(action === 'assign'){ msg = 'Modifier le responsable des ' + ids.length + ' prospect(s) sélectionné(s) ?'; }
          if(msg && !window.confirm(msg)){ return; }
          var form = q('[data-acdc-bulk-form]');
          if(!form){ return; }
          var idsWrap = q('[data-acdc-bulk-ids]', form);
          if(idsWrap){
            idsWrap.innerHTML = '';
            ids.forEach(function(id){
              var inp = document.createElement('input');
              inp.type = 'hidden';
              inp.name = 'prospect_ids[]';
              inp.value = id;
              idsWrap.appendChild(inp);
            });
          }
          var af = q('[data-acdc-bulk-form-action]', form); if(af){ af.value = action; }
          var vf = q('[data-acdc-bulk-form-value]', form); if(vf){ vf.value = value; }
          form.submit();
        });
        if(document.readyState === 'loading'){ document.addEventListener('DOMContentLoaded', refresh); } else { refresh(); }
      })();
    </script>
    <script>
      (function(){
        /* ACDC 3.20.61 — table de correspondance des types Prospects vers
         * les types AcdcActionHub utilisés par le back office UI.
         * Les Prospects appellent iconMarkup avec 'clipboard' pour le suivi
         * commercial alors que la config back office utilise la clef 'followup'.
         * Cette table fait le pont sans rien casser : si la config est
         * absente ou si le type n'a pas d'équivalent, on retombe sur les
         * SVG en dur historiques. */
        var PROSPECT_TYPE_TO_HUB = {
          view: 'view',
          edit: 'edit',
          clipboard: 'followup',
          trash: 'delete',
          more: 'more'
        };
        function configuredSvg(prospectType){
          var hubType = PROSPECT_TYPE_TO_HUB[prospectType];
          if(!hubType){ return null; }
          var cfg = (typeof window !== 'undefined') ? window.ACDC_ACTION_HUB_CONFIG : null;
          if(!cfg || !cfg.icons || !cfg.glyphs){ return null; }
          var glyphName = cfg.icons[hubType];
          if(!glyphName){ return null; }
          var svg = cfg.glyphs[glyphName];
          if(!svg){ return null; }
          return '<span aria-hidden="true">' + svg + '</span>';
        }
        function iconMarkup(type){
          var fromConfig = configuredSvg(type);
          if(fromConfig){ return fromConfig; }
          var color = 'currentColor';
          if(type==='more'){return '<span aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="'+color+'"><circle cx="5" cy="12" r="1.8"></circle><circle cx="12" cy="12" r="1.8"></circle><circle cx="19" cy="12" r="1.8"></circle></svg></span>';}
          if(type==='view'){return '<span aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="'+color+'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1.5 12s3.75-7 10.5-7 10.5 7 10.5 7-3.75 7-10.5 7S1.5 12 1.5 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg></span>';}
          if(type==='edit'){return '<span aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="'+color+'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"></path></svg></span>';}
          if(type==='phone'){return '<span aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="'+color+'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.35 1.78.68 2.61a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.47-1.25a2 2 0 0 1 2.11-.45c.83.33 1.71.56 2.61.68A2 2 0 0 1 22 16.92z"></path></svg></span>';}
          if(type==='trash'){return '<span aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="'+color+'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14H6L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M9 6V4h6v2"></path></svg></span>';}
          /* ACDC 3.20.61 — fallback clipboard si la config n'est pas chargée
           * et que le bouton de suivi commercial est rendu. */
          if(type==='clipboard'){return '<span aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="'+color+'" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="12" height="17" rx="2"></rect><path d="M9 4V3a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1"></path><path d="M9 10h6"></path><path d="M9 14h6"></path><path d="M9 18h4"></path></svg></span>';}
          return '';
        }
        function ensureMenuHost(menu){
          if(!menu){return null;}
          if(!menu.dataset.acdcOriginalParent){
            menu.dataset.acdcOriginalParent = '1';
            menu._acdcOriginalParent = menu.parentNode;
            menu._acdcOriginalNext = menu.nextSibling;
          }
          if(menu.parentNode !== document.body){
            document.body.appendChild(menu);
          }
          return menu;
        }
        function restoreMenu(menu){
          if(!menu || !menu._acdcOriginalParent){return;}
          if(menu.parentNode === menu._acdcOriginalParent){return;}
          if(menu._acdcOriginalNext && menu._acdcOriginalNext.parentNode === menu._acdcOriginalParent){
            menu._acdcOriginalParent.insertBefore(menu, menu._acdcOriginalNext);
          } else {
            menu._acdcOriginalParent.appendChild(menu);
          }
        }
        function positionMenu(trigger, menu){
          if(!trigger || !menu){return;}
          ensureMenuHost(menu);
          var rect = trigger.getBoundingClientRect();
          var top = rect.bottom + 10;
          var left = rect.left;
          menu.style.position = 'fixed';
          menu.style.top = top + 'px';
          menu.style.left = left + 'px';
          menu.style.right = 'auto';
          menu.style.bottom = 'auto';
          requestAnimationFrame(function(){
            var menuRect = menu.getBoundingClientRect();
            var maxLeft = Math.max(12, window.innerWidth - menuRect.width - 12);
            if(left > maxLeft){ menu.style.left = maxLeft + 'px'; }
            if(menuRect.bottom > window.innerHeight - 12){
              var adjustedTop = Math.max(12, rect.top - menuRect.height - 10);
              menu.style.top = adjustedTop + 'px';
            }
          });
        }
        function closeAll(exceptWrap){
          document.querySelectorAll('[data-acdc-prospect-actions]').forEach(function(wrap){
            if(exceptWrap && wrap===exceptWrap){return;}
            var trigger=wrap.querySelector('[data-acdc-prospect-menu-toggle]');
            var menu=wrap.querySelector('[data-acdc-prospect-menu]');
            if(menu){menu.hidden=true; restoreMenu(menu);} 
            if(trigger){trigger.setAttribute('aria-expanded','false');}
          });
        }
        function bindMenu(wrap){
          if(!wrap){return;}
          var trigger=wrap.querySelector('[data-acdc-prospect-menu-toggle]');
          var menu=wrap.querySelector('[data-acdc-prospect-menu]');
          if(!trigger || !menu || trigger.dataset.bound==='1'){return;}
          trigger.dataset.bound='1';
          menu._acdcTriggerButton = trigger;
          var row = wrap.closest('tr');
          if(row){
            var editLink = row.querySelector('a[href*="action=edit"], a[href*="item_id"]');
            var prospectId = '';
            if(editLink){ try{ var u=new URL(editLink.href,window.location.origin); prospectId=u.searchParams.get('item_id')||u.searchParams.get('prospect_id')||''; }catch(e){} }
            if(!prospectId){
              row.querySelectorAll('a').forEach(function(a){ if(prospectId){return;} try{ var u=new URL(a.href,window.location.origin); prospectId=u.searchParams.get('item_id')||u.searchParams.get('prospect_id')||''; }catch(e){} });
            }
            if(prospectId){ menu.setAttribute('data-acdc-prospect-id', prospectId); }
          }
          trigger.addEventListener('click', function(event){
            event.preventDefault();
            event.stopPropagation();
            var isOpen=!menu.hidden;
            closeAll(wrap);
            if(isOpen){
              menu.hidden=true;
              trigger.setAttribute('aria-expanded', 'false');
              return;
            }
            menu.hidden=false;
            positionMenu(trigger, menu);
            trigger.setAttribute('aria-expanded', 'true');
          });
          var inlineLabels = ['attribuer à', 'statut dossier', 'relancer'];
          menu.querySelectorAll('a').forEach(function(link){
            if(inlineLabels.indexOf((link.textContent || '').trim().toLowerCase()) === -1){
              link.addEventListener('click', function(){ closeAll(); });
            }
          });
        }
        function upgradeLegacyProspectActions(){
          document.querySelectorAll('.acdc-table-prospects tbody tr').forEach(function(row){
            var cell=row.querySelector('td:last-child');
            if(!cell){return;}
            if(cell.querySelector('[data-acdc-prospect-actions]')){ bindMenu(cell.querySelector('[data-acdc-prospect-actions]')); return; }
            var links=Array.prototype.slice.call(cell.querySelectorAll('a'));
            if(!links.length){return;}
            var mapping={};
            links.forEach(function(link){
              var label=(link.textContent||'').trim().toLowerCase();
              if(label==='voir'){mapping.view=link.href;}
              else if(label==='modifier'){mapping.edit=link.href;}
              else if(label==='suivi'){mapping.follow=link.href;}
              else if(label==='supprimer'){mapping.delete=link.href;}
            });
            if(!mapping.view && !mapping.edit && !mapping.follow && !mapping.delete){return;}
            cell.innerHTML='';
            var wrap=document.createElement('div');
            wrap.className='acdc-prospect-actions acdc-prospect-actions--legacy-ready';
            wrap.setAttribute('data-acdc-prospect-actions','1');
            var menuWrap=document.createElement('div');
            menuWrap.className='acdc-prospect-action-menu';
            var trigger=document.createElement('button');
            trigger.type='button';
            trigger.className='acdc-prospect-action-trigger';
            trigger.setAttribute('data-acdc-prospect-menu-toggle','1');
            trigger.setAttribute('aria-haspopup','true');
            trigger.setAttribute('aria-expanded','false');
            trigger.setAttribute('aria-label','Actions complémentaires');
            trigger.innerHTML=iconMarkup('more');
            var menu=document.createElement('div');
            menu.className='acdc-prospect-action-dropdown';
            menu.setAttribute('data-acdc-prospect-menu','1');
            menu.hidden=true;
            function setTab(url, tab, params){ try{ var u = new URL(url || window.location.href, window.location.origin); u.searchParams.set('tab', tab); ['action','item_id','focus_field','focus_section','open_rdv','duplicate_id','prospect_id'].forEach(function(key){ u.searchParams.delete(key); }); Object.keys(params || {}).forEach(function(key){ u.searchParams.set(key, params[key]); }); return u.toString(); }catch(e){ return '#'; } }
            var followBase = mapping.follow || mapping.view || '#';
            var editBase = mapping.edit || mapping.view || '#';
            var itemId = (function(url){ try { var u = new URL(url || window.location.href, window.location.origin); return u.searchParams.get('item_id') || u.searchParams.get('prospect_id') || ''; } catch(e){ return ''; } })(editBase);
            var menuItems=[
              ['Répliquer', editBase !== '#' ? setTab(editBase, 'prospects', { action:'new', duplicate_id:itemId }) : '#'],
              ['Ajouter un rendez-vous', followBase !== '#' ? setTab(followBase, 'prospect_followup', { action:'view', item_id:itemId, open_rdv:'1' }) : '#'],
              ['Recueil des besoins', editBase !== '#' ? setTab(editBase, 'needs', { action:'new', prospect_id:itemId }) : '#']
            ];
            var legacyInlineLabels = ['attribuer à', 'statut dossier', 'relancer'];
            menuItems.forEach(function(item){
              var a=document.createElement('a');
              a.className='acdc-prospect-action-item';
              a.href=item[1];
              a.textContent=item[0];
              if(legacyInlineLabels.indexOf(item[0].toLowerCase()) === -1){
                a.addEventListener('click', function(){ closeAll(); });
              }
              menu.appendChild(a);
            });
            menu._acdcTriggerButton = trigger;
            if(itemId){ menu.setAttribute('data-acdc-prospect-id', itemId); }
            menuWrap.appendChild(trigger); menuWrap.appendChild(menu); wrap.appendChild(menuWrap);
            [['view','Voir'],['edit','Modifier'],['follow','Suivi commercial'],['delete','Supprimer']].forEach(function(item){
              if(!mapping[item[0]]){return;}
              var a=document.createElement('a');
              var subCls={'view':'acdc-row-view-link','edit':'acdc-row-edit-link','delete':'acdc-row-delete-link','follow':''};
              a.href=mapping[item[0]]; a.title=item[1]; a.setAttribute('aria-label',item[1]);
              a.className='acdc-row-action-icon'+(subCls[item[0]]?' '+subCls[item[0]]:'');
              a.setAttribute('data-acdc-iconized','1');
              var iconKey=item[0]==='view'?'view':item[0]==='edit'?'edit':item[0]==='follow'?'clipboard':'trash';
              a.innerHTML=iconMarkup(iconKey)+'<span class="acdc-action-hub-sr screen-reader-text">'+item[1]+'</span>';
              if(item[0]==='delete'){ a.setAttribute('onclick', "return confirm('Supprimer ce prospect ?');"); }
              wrap.appendChild(a);
            });
            cell.appendChild(wrap);
            bindMenu(wrap);
          });
        }
        function init(){
          document.querySelectorAll('[data-acdc-prospect-actions]').forEach(bindMenu);
          upgradeLegacyProspectActions();
        }
        if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded', init);} else {init();}
        document.addEventListener('click', function(){ closeAll(); });
        document.addEventListener('keydown', function(event){ if(event.key==='Escape'){ closeAll(); } });
        window.addEventListener('resize', function(){ closeAll(); });
        window.addEventListener('scroll', function(){ closeAll(); }, true);
      })();
    </script>
    <?php
  }


  private function render_front_prospect_form( $prospect = null, $read_only = false ) {
    $state = ! $read_only ? $this->acdc_consume_form_state( 'prospect' ) : array();

    $state_input = ( ! empty( $state['input'] ) && is_array( $state['input'] ) ) ? $state['input'] : array();
    $required_fields = ( ! empty( $state['required_fields'] ) && is_array( $state['required_fields'] ) ) ? array_values( array_unique( array_map( 'sanitize_key', $state['required_fields'] ) ) ) : array();

    $value = function( $key, $default = '' ) use ( $prospect, $state_input ) {
      if ( array_key_exists( $key, $state_input ) && ! is_array( $state_input[ $key ] ) ) {
        return $state_input[ $key ];
      }
      return $prospect && isset( $prospect->$key ) ? $prospect->$key : $default;
    };
    $is_invalid = function( $key ) use ( $required_fields ) {
      return in_array( sanitize_key( $key ), $required_fields, true );
    };
    $field_class = function( $key, $class = '' ) use ( $is_invalid ) {
      $classes = trim( $class . ( $is_invalid( $key ) ? ' acdc-field-invalid' : '' ) );
      return $classes ? ' class="' . esc_attr( $classes ) . '"' : '';
    };
    $invalid_note = function( $key ) use ( $is_invalid ) {
      if ( ! $is_invalid( $key ) ) {
        return '';
      }
      return '<span class="acdc-field-help acdc-field-help-error">Champ obligatoire à renseigner.</span>';
    };
    $profile = $value( 'profile_type', isset( $_GET['profile'] ) ? sanitize_text_field( wp_unslash( $_GET['profile'] ) ) : '' );
    $read = $read_only ? 'readonly disabled' : '';
    $users = $this->get_assignment_options();
    $profiles = $this->get_prospect_profile_options();
    $trainings = $this->get_prospect_training_options(); /* conservé pour rétrocompat */
    $statuses = $this->get_prospect_status_options();
    $sources = $this->get_prospect_source_options();
    $yes_no = $this->get_learner_yes_no_options();
    $genders = $this->get_learner_gender_options();
    /* hotfix9 — thématiques et formations pour les nouveaux selects */
    $thematiques_list    = $this->get_thematiques();
    $formations_all = $this->get_formations( array( 'archived' => false ) ); /* hotfix11 — bases + variantes, triées */
    $desired_thematique_val   = $value( 'desired_thematique', '' );
    $desired_formation_id_val = (int) $value( 'desired_formation_id', 0 );
    $education_levels = $this->get_learner_education_options();
    $copy_recipients = ( ! empty( $state_input['copy_recipients_list'] ) && is_array( $state_input['copy_recipients_list'] ) ) ? array_values( array_filter( array_map( 'sanitize_email', $state_input['copy_recipients_list'] ) ) ) : $this->get_prospect_copy_recipients( $prospect );
    $additional_contacts = ( ! empty( $state_input['additional_contacts'] ) && is_array( $state_input['additional_contacts'] ) ) ? $state_input['additional_contacts'] : $this->get_prospect_additional_contacts( $prospect );
    $redirect_to = isset( $_GET['redirect_to'] ) ? sanitize_key( wp_unslash( $_GET['redirect_to'] ) ) : '';
    $return_tab = isset( $_GET['return_tab'] ) ? sanitize_key( wp_unslash( $_GET['return_tab'] ) ) : '';
    $cancel_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-prospects' ) : $this->portal_page_url( array( 'tab' => 'prospects' ) );
    if ( 'needs' === $redirect_to || 'recueil_besoins' === $redirect_to ) {
      $cancel_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-needs&action=new' ) : $this->portal_page_url( array( 'tab' => 'needs', 'action' => 'new' ) );
    }
    $followup_url = $prospect && ! empty( $prospect->id ) ? ( is_admin() ? $this->admin_prospect_followup_url( array( 'action' => 'view', 'item_id' => (int) $prospect->id ) ) : $this->portal_page_url( array( 'tab' => 'prospect_followup', 'action' => 'view', 'item_id' => (int) $prospect->id ) ) ) : '';
    $form_title = 'Créer un prospect';
    if ( ! empty( $profile ) && ! $prospect ) {
      $form_title = 'Créer un prospect';
    } elseif ( $prospect ) {
      $form_title = $read_only ? 'Voir un prospect' : 'Modifier un prospect';
    }
    ?>
    <style>
      .acdc-prospect-form .acdc-field-invalid{border-color:var(--acdc-danger)!important;box-shadow:0 0 0 2px rgba(224,109,109,.12);}
      .acdc-prospect-form .acdc-field-help-error{display:block;margin-top:6px;color:var(--acdc-danger)!important;font-size:12px;line-height:1.45;}
      .acdc-toggle-wrap input[type=checkbox]:checked + .acdc-toggle-track{background:#d6a353!important;}
      .acdc-toggle-wrap input[type=checkbox]:checked + .acdc-toggle-track span{transform:translateX(20px)!important;}
      </style>
    <form class="acdc-form acdc-needs-form acdc-prospect-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'acdc_save_prospect' ); ?>
      <input type="hidden" name="action" value="acdc_save_prospect">
      <input type="hidden" name="prospect_id" value="<?php echo $prospect ? esc_attr( $prospect->id ) : 0; ?>">
      <?php if ( 'needs' === $redirect_to || 'recueil_besoins' === $redirect_to ) : ?>
        <input type="hidden" name="redirect_to" value="needs">
        <input type="hidden" name="return_tab" value="needs">
      <?php endif; ?>
      <input type="hidden" name="return_after_save" value="<?php echo esc_attr( $prospect ? 'edit' : 'view' ); ?>">
      <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-prospects"><?php endif; ?>
      <div class="acdc-panel acdc-needs-section acdc-prospect-editor">
        <div class="acdc-grid-2cols">
          <p>
            <label>Profil *</label>
            <select name="prospect[profile_type]" required <?php echo $read; ?> data-acdc-prospect-profile<?php echo $field_class( 'profile_type' ); ?>>
              <?php foreach ( $profiles as $k => $label ) : ?>
                <option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) $profile, (string) $k ); ?>><?php echo esc_html( $label ); ?></option>
              <?php endforeach; ?>
            </select>
            <?php echo $invalid_note( 'profile_type' ); ?>
          </p>
          <p>
            <label>Th&#233;matique de formation souhait&#233;e</label>
            <select name="prospect[desired_thematique]" id="acdc-prospect-thematique" <?php echo $read; ?>>
              <option value="">&#8212; Toutes th&#233;matiques &#8212;</option>
              <?php foreach ( $thematiques_list as $th ) : ?>
                <option value="<?php echo esc_attr( $th->code ); ?>"
                        data-label="<?php echo esc_attr( $th->label ); ?>"
                        <?php selected( $desired_thematique_val, $th->code ); ?>>
                  <?php echo esc_html( ( $th->icon_emoji ? $th->icon_emoji . ' ' : '' ) . $th->label ); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </p>
          <!-- hotfix12 : spacer gauche — Formation reste en colonne droite sous Thématique -->
          <div></div>
          <p>
            <label>Formation</label>
            <select name="prospect[desired_formation_id]" id="acdc-prospect-formation-id" <?php echo $read; ?>>
              <option value="">&#8212; S&#233;lectionner une formation &#8212;</option>
              <?php foreach ( $formations_all as $f ) : ?>
                <option value="<?php echo esc_attr( $f->id ); ?>"
                        data-thematique="<?php echo esc_attr( $f->thematique ?: '' ); ?>"
                        <?php selected( $desired_formation_id_val, (int) $f->id ); ?>>
                  <?php echo esc_html( $this->build_formation_option_label( $f ) ); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <input type="hidden" name="prospect[desired_training]" id="acdc-prospect-desired-training" value="<?php echo esc_attr( $value( 'desired_training', '' ) ); ?>">
          </p>
        </div>

        <div class="acdc-prospect-profile-panels">
          <div class="acdc-prospect-profile-panel" data-prospect-panels="Particulier|Salarié" <?php echo in_array( $profile, array( 'Particulier', 'Salarié' ), true ) ? '' : 'hidden'; ?>>
            <div class="acdc-grid-2cols" style="align-items:stretch">
              <p><label>Genre *</label><select name="prospect[gender]" <?php echo $read; ?><?php echo $field_class( 'gender' ); ?>><option value="">Choisir une option</option><?php foreach ( $genders as $k => $label ) : if ( '' === $k ) { continue; } ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) $value( 'gender' ), (string) $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><?php echo $invalid_note( 'gender' ); ?></p>
              <p><label>Prénom *</label><input type="text" name="prospect[first_name]" value="<?php echo esc_attr( $value( 'first_name' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?><?php echo $field_class( 'first_name' ); ?>><?php echo $invalid_note( 'first_name' ); ?></p>
              <p><label>Nom *</label><input type="text" name="prospect[last_name]" value="<?php echo esc_attr( $value( 'last_name' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?><?php echo $field_class( 'last_name' ); ?>><?php echo $invalid_note( 'last_name' ); ?></p>
              <p><label>Adresse</label><input type="text" name="prospect[address]" value="<?php echo esc_attr( $value( 'address' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
              <p><label>Code postal</label><input type="text" name="prospect[postal_code]" value="<?php echo esc_attr( $value( 'postal_code' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
              <p><label>Ville</label><input type="text" name="prospect[city]" value="<?php echo esc_attr( $value( 'city' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
              <p><label>Téléphone</label><input type="text" name="prospect[phone]" value="<?php echo esc_attr( $value( 'phone' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
              <p><label>E-mail *</label><input type="email" name="prospect[email]" value="<?php echo esc_attr( $value( 'email' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?><?php echo $field_class( 'email' ); ?>><?php echo $invalid_note( 'email' ); ?></p>
              <p><label>Poste / Fonction</label><input type="text" name="prospect[job_title]" value="<?php echo esc_attr( $value( 'job_title' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
              <p><label>Entreprise</label><input type="text" name="prospect[employer_name]" value="<?php echo esc_attr( $value( 'employer_name', '' ) ); ?>" placeholder="Nom de l'employeur" <?php echo $read_only ? 'readonly' : ''; ?>></p>
              <p><label>Date de naissance</label><input type="date" name="prospect[birth_date]" value="<?php echo esc_attr( $value( 'birth_date' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
              <p style="grid-row:span 3;display:flex;flex-direction:column;"><label>Besoins d’accessibilité</label><textarea name="prospect[accessibility_needs]" style="flex:1;min-height:120px;resize:vertical;" <?php echo $read_only ? 'readonly' : ''; ?>><?php echo esc_textarea( $value( 'accessibility_needs' ) ); ?></textarea></p>
              <p><label>Inscrit à France Travail</label><select name="prospect[is_france_travail]" <?php echo $read; ?>><?php foreach ( $yes_no as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) $value( 'is_france_travail' ), (string) $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
              <p><label>Niveau d’études</label><select name="prospect[education_level]" <?php echo $read; ?>><?php foreach ( $education_levels as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) $value( 'education_level' ), (string) $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
            </div>
            <p class="acdc-field-help">Information obligatoire pour créer une analyse du besoin, un devis ou une convention.</p>
          </div>

          <div class="acdc-prospect-profile-panel" data-prospect-panels="Entreprise|Indépendant|Entreprise / indépendant" <?php echo in_array( $profile, array( 'Entreprise', 'Indépendant', 'Entreprise / indépendant' ), true ) ? '' : 'hidden'; ?>>
            <div class="acdc-grid-2cols">
              <p><label>Activité</label><input type="text" name="prospect[activity]" value="<?php echo esc_attr( $value( 'activity', '' ) ); ?>" placeholder="Organisme de formation, commerce…" <?php echo $read_only ? 'readonly' : ''; ?>></p>
              <div></div>
              <p><label>Entreprise *</label><input type="text" name="prospect[company_name]" value="<?php echo esc_attr( $value( 'company_name' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?><?php echo $field_class( 'company_name' ); ?>><?php echo $invalid_note( 'company_name' ); ?></p>
              <p><label>SIRET / N° d’identification *</label><input type="text" name="prospect[siret]" value="<?php echo esc_attr( $value( 'siret' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?><?php echo $field_class( 'siret' ); ?>><?php echo $invalid_note( 'siret' ); ?></p>
              <p><label>Code NAF</label><input type="text" name="prospect[naf_code]" value="<?php echo esc_attr( $value( 'naf_code' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
              <p><label>Adresse</label><input type="text" name="prospect[address]" value="<?php echo esc_attr( $value( 'address' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
              <p><label>Code postal</label><input type="text" name="prospect[postal_code]" value="<?php echo esc_attr( $value( 'postal_code' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
              <p><label>Ville</label><input type="text" name="prospect[city]" value="<?php echo esc_attr( $value( 'city' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
              <p><label>Téléphone de l’entreprise</label><input type="text" name="prospect[company_phone]" value="<?php echo esc_attr( $value( 'company_phone' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
              <p><label>Email de l’entreprise</label><input type="email" name="prospect[company_email]" value="<?php echo esc_attr( $value( 'company_email', '' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
              <p><label>Nom du signataire *</label><input type="text" name="prospect[signer_last_name]" value="<?php echo esc_attr( $value( 'signer_last_name' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?><?php echo $field_class( 'signer_last_name' ); ?>><?php echo $invalid_note( 'signer_last_name' ); ?></p>
              <p><label>Prénom du signataire *</label><input type="text" name="prospect[signer_first_name]" value="<?php echo esc_attr( $value( 'signer_first_name' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?><?php echo $field_class( 'signer_first_name' ); ?>><?php echo $invalid_note( 'signer_first_name' ); ?></p>
              <p><label>Qualité du signataire</label><input type="text" name="prospect[signer_quality]" value="<?php echo esc_attr( $value( 'signer_quality' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
              <p><label>E-mail du signataire</label><input type="email" name="prospect[signer_email]" value="<?php echo esc_attr( $value( 'signer_email' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
              <p><label>Site web</label><input type="url" name="prospect[website]" value="<?php echo esc_attr( $value( 'website', '' ) ); ?>" placeholder="https://…" <?php echo $read_only ? 'readonly' : ''; ?>></p>
              <p><label>Téléphone du signataire</label><input type="text" name="prospect[signer_phone]" value="<?php echo esc_attr( $value( 'signer_phone' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
            </div>
            <div class="acdc-prospect-repeaters-head">
              <strong>Contact annexe</strong>
              <?php if ( ! $read_only ) : ?><button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-open="acdc-prospect-contact-modal">Ajouter un contact</button><?php endif; ?>
            </div>
            <div data-acdc-contact-list>
              <?php foreach ( $additional_contacts as $idx => $contact ) :
                $is_copy = ! empty( $contact['is_copy_recipient'] ) && '1' === (string) $contact['is_copy_recipient'];
              ?>
              <div class="acdc-prospect-contact-card" data-acdc-contact-row style="background:#fbf8f7;border:1px solid #f0e6dc;border-radius:10px;padding:14px 16px;margin-bottom:10px;">
                <div class="acdc-grid-2cols" style="gap:8px">
                  <p style="margin:0"><label style="font-size:12px;color:#4b5d76;display:block;margin-bottom:4px">PrÃ©nom</label><input type="text" name="prospect[additional_contacts][<?php echo (int) $idx; ?>][first_name]" value="<?php echo esc_attr( $contact['first_name'] ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
                  <p style="margin:0"><label style="font-size:12px;color:#4b5d76;display:block;margin-bottom:4px">Nom</label><input type="text" name="prospect[additional_contacts][<?php echo (int) $idx; ?>][last_name]" value="<?php echo esc_attr( $contact['last_name'] ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
                  <p style="margin:0"><label style="font-size:12px;color:#4b5d76;display:block;margin-bottom:4px">Poste / Fonction</label><input type="text" name="prospect[additional_contacts][<?php echo (int) $idx; ?>][job_title]" value="<?php echo esc_attr( $contact['job_title'] ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
                  <p style="margin:0"><label style="font-size:12px;color:#4b5d76;display:block;margin-bottom:4px">E-mail</label><input type="email" name="prospect[additional_contacts][<?php echo (int) $idx; ?>][email]" value="<?php echo esc_attr( $contact['email'] ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
                  <p style="margin:0;grid-column:span 2"><label style="font-size:12px;color:#4b5d76;display:block;margin-bottom:4px">TÃ©lÃ©phone</label><input type="text" name="prospect[additional_contacts][<?php echo (int) $idx; ?>][phone]" value="<?php echo esc_attr( $contact['phone'] ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px;padding-top:10px;border-top:1px solid #f0e6dc;">
                  <label class="acdc-toggle-wrap" style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                    <input type="checkbox" name="prospect[additional_contacts][<?php echo (int) $idx; ?>][is_copy_recipient]" value="1" <?php echo $is_copy ? 'checked' : ''; ?> <?php echo $read_only ? 'disabled' : ''; ?> style="display:none">
                    <span class="acdc-toggle-track" style="position:relative;display:inline-block;width:44px;height:24px;border-radius:12px;background:<?php echo $is_copy ? '#d6a353' : '#d9dfe8'; ?>;transition:background .2s;flex-shrink:0;">
                      <span style="position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;transition:transform .2s;<?php echo $is_copy ? 'transform:translateX(20px);' : ''; ?>"></span>
                    </span>
                    <span style="font-size:13px;font-weight:500;color:<?php echo $is_copy ? '#8b5b23' : '#4b5d76'; ?>;">Destinataire en copie&nbsp;&nbsp;<strong class="acdc-toggle-state"><?php echo $is_copy ? 'Oui' : 'Non'; ?></strong></span>
                  </label>
                  <?php if ( ! $read_only ) : ?><button type="button" data-remove-contact style="background:none;border:none;color:#e06d6d;cursor:pointer;font-size:13px;padding:4px 8px;">× Retirer</button><?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <p class="acdc-field-help">Information obligatoire pour créer une analyse du besoin, un devis ou une convention.</p>
          </div>
        </div>

        <div class="acdc-prospect-full-row" style="margin-bottom:12px">
          <p><label>Commentaire</label><textarea name="prospect[comment_text]" rows="5" <?php echo $read_only ? 'readonly' : ''; ?>><?php echo esc_textarea( $value( 'comment_text' ) ); ?></textarea></p>
        </div>
        <div class="acdc-grid-2cols">
          <p><label>Source</label><select name="prospect[source]" <?php echo $read; ?>><?php foreach ( $sources as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) $value( 'source' ), (string) $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
          <p><label>Assigné à</label><select name="prospect[assigned_to]" <?php echo $read; ?>><?php foreach ( $users as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) $value( 'assigned_to' ), (string) $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
          <p><label>Statut</label><select name="prospect[status]" <?php echo $read; ?>><?php foreach ( $statuses as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) $value( 'status', 'À traiter' ), (string) $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
          <div>
            <p style="margin-bottom:6px;"><label>Financement envisagé</label>
              <?php $pf_val = (string) $value( 'planned_funding', '' ); $pf_is_opco = ( 'OPCO' === $pf_val || 0 === strpos( $pf_val, 'OPCO' ) ); ?>
              <select name="prospect[planned_funding]" id="acdc-prospect-funding-sel" <?php echo $read; ?>>
                <?php foreach ( $this->get_need_funding_options() as $fk => $fl ) : ?>
                  <option value="<?php echo esc_attr( $fk ); ?>" <?php selected( $pf_is_opco ? 'OPCO' : $pf_val, $fk ); ?>><?php echo esc_html( $fl ); ?></option>
                <?php endforeach; ?>
              </select>
            </p>
            <div id="acdc-prospect-opco-row" style="<?php echo $pf_is_opco ? '' : 'display:none;'; ?>">
              <label style="font-size:12px;color:#4b5d76;display:block;margin-bottom:3px;">OPCO / Financeur</label>
              <select name="prospect[funder_id]" id="acdc-prospect-funder-sel" <?php echo $read; ?>>
                <option value="0">— Sélectionner un OPCO —</option>
                <?php foreach ( $this->get_funders() as $funder ) : ?>
                  <option value="<?php echo esc_attr( $funder->id ); ?>" <?php selected( (int) $value( 'funder_id', 0 ), (int) $funder->id ); ?>>
                    <?php echo esc_html( $funder->name ); ?><?php if ( $funder->sector ) : ?> (<?php echo esc_html( $funder->sector ); ?>)<?php endif; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <script>
            (function(){
              var sel = document.getElementById('acdc-prospect-funding-sel');
              var row = document.getElementById('acdc-prospect-opco-row');
              if (!sel || !row) return;
              sel.addEventListener('change', function(){ row.style.display = sel.value === 'OPCO' ? '' : 'none'; });
            })();
            </script>
          </div>
        </div>


        <?php if ( ! $read_only ) : ?>
          <p class="acdc-actions-end-wrap">
            <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $cancel_url ); ?>">Annuler</a>
            <?php
            /* m3 — Le libellé dépend de l'existence d'un identifiant (édition), non de la simple
               présence de l'objet : en « Répliquer », $prospect contient les données dupliquées
               SANS id (nouveau prospect), et affichait « Modifier » à tort. */
            $prospect_has_id = ( is_object( $prospect ) && ! empty( $prospect->id ) ) || ( is_array( $prospect ) && ! empty( $prospect['id'] ) );
            ?>
            <button type="submit" class="acdc-button acdc-button-primary"><?php echo $prospect_has_id ? 'Modifier le prospect' : 'Créer le prospect'; ?></button>
          </p>
        <?php else : ?>
          <p class="acdc-actions-end-wrap">
            <?php if ( $followup_url ) : ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $followup_url ); ?>">Voir le suivi commercial</a><?php endif; ?>
            <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-prospects&action=edit&item_id=' . (int) $prospect->id ) : $this->portal_page_url( array( 'tab' => 'prospects', 'action' => 'edit', 'item_id' => (int) $prospect->id ) ) ); ?>">Modifier ce prospect</a>
          </p>
        <?php endif; ?>
      </div>

      <?php if ( ! $read_only ) : ?>
      <div class="acdc-modal-shell" id="acdc-prospect-contact-modal" hidden>
        <div class="acdc-modal-backdrop" data-acdc-modal-close></div>
        <div class="acdc-modal-dialog acdc-modal-dialog-compact">
          <div class="acdc-modal-header"><h4>Ajouter un contact</h4><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button></div>
          <div class="acdc-modal-body acdc-modal-body-padding">
            <div class="acdc-grid-2cols">
              <p><label>Prénom</label><input type="text" data-contact-first-name></p>
              <p><label>Nom</label><input type="text" data-contact-last-name></p>
              <p><label>Poste / Fonction</label><input type="text" data-contact-job-title></p>
              <p><label>E-mail</label><input type="email" data-contact-email></p>
              <p style="margin:0"><label>Téléphone</label><input type="text" data-contact-phone></p>
              <p style="margin:0;display:flex;flex-direction:column;justify-content:flex-end;">
                <label style="font-size:12px;color:#4b5d76;display:block;margin-bottom:4px">Destinataire en copie</label>
                <label class="acdc-toggle-wrap" style="display:flex;align-items:center;gap:10px;cursor:pointer;height:40px;">
                  <input type="checkbox" id="acdc-contact-is-copy" data-contact-is-copy style="display:none">
                  <span class="acdc-toggle-track" style="position:relative;display:inline-block;width:44px;height:24px;border-radius:12px;background:#d9dfe8;transition:background .2s;flex-shrink:0;">
                    <span class="acdc-toggle-thumb" style="position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;transition:transform .2s;"></span>
                  </span>
                  <span class="acdc-toggle-state" style="font-size:13px;font-weight:500;color:#4b5d76;">Non</span>
                </label>
              </p>
            </div>
            <p class="acdc-actions-end-wrap"><button type="button" class="acdc-button acdc-button-primary" data-save-contact>Enregistrer contact</button></p>
          </div>
        </div>
      </div>
      <script>
      (function(){
        var form=document.currentScript.closest('form');
        if(!form){return;}
        function q(sel,ctx){return (ctx||form).querySelector(sel);}
        function qa(sel,ctx){return Array.prototype.slice.call((ctx||form).querySelectorAll(sel));}

        var profileSelect=q('[data-acdc-prospect-profile]');
        var panels=qa('[data-prospect-panel],[data-prospect-panels]');
        function syncProfile(){var v=profileSelect ? profileSelect.value : ''; panels.forEach(function(panel){var values=(panel.getAttribute('data-prospect-panels')||panel.getAttribute('data-prospect-panel')||'').split('|'); panel.hidden = values.indexOf(v) === -1;});}
        if(profileSelect){profileSelect.addEventListener('change', syncProfile); syncProfile();}


        var contactList=q('[data-acdc-contact-list]');
        var contactIndex=contactList ? contactList.querySelectorAll('[data-acdc-contact-row]').length : 0;
        var saveContact=q('[data-save-contact]');
        if(saveContact && contactList){
          saveContact.addEventListener('click', function(){
            var firstName=(q('[data-contact-first-name]').value||'').trim();
            var lastName=(q('[data-contact-last-name]').value||'').trim();
            var jobTitle=(q('[data-contact-job-title]').value||'').trim();
            var email=(q('[data-contact-email]').value||'').trim();
            var phone=(q('[data-contact-phone]').value||'').trim();
            var isCopy=q('[data-contact-is-copy]').checked ? '1' : '0';
            if(!(lastName||firstName||jobTitle||email||phone)){return;}
            var idx=contactIndex;
            var card=document.createElement('div');
            card.className='acdc-prospect-contact-card';
            card.setAttribute('data-acdc-contact-row','1');
            card.style.cssText='background:#fbf8f7;border:1px solid #f0e6dc;border-radius:10px;padding:14px 16px;margin-bottom:10px;';
            card.innerHTML=
              '<div class="acdc-grid-2cols" style="gap:8px">'+
              '<p style="margin:0"><label style="font-size:12px;color:#4b5d76;display:block;margin-bottom:4px">Pr\u00e9nom</label><input type="text" name="prospect[additional_contacts]['+idx+'][first_name]" value="'+firstName.replace(/"/g,'&quot;')+'"></p>'+
              '<p style="margin:0"><label style="font-size:12px;color:#4b5d76;display:block;margin-bottom:4px">Nom</label><input type="text" name="prospect[additional_contacts]['+idx+'][last_name]" value="'+lastName.replace(/"/g,'&quot;')+'"></p>'+
              '<p style="margin:0"><label style="font-size:12px;color:#4b5d76;display:block;margin-bottom:4px">Poste / Fonction</label><input type="text" name="prospect[additional_contacts]['+idx+'][job_title]" value="'+jobTitle.replace(/"/g,'&quot;')+'"></p>'+
              '<p style="margin:0"><label style="font-size:12px;color:#4b5d76;display:block;margin-bottom:4px">E-mail</label><input type="email" name="prospect[additional_contacts]['+idx+'][email]" value="'+email.replace(/"/g,'&quot;')+'"></p>'+
              '<p style="margin:0;grid-column:span 2"><label style="font-size:12px;color:#4b5d76;display:block;margin-bottom:4px">T\u00e9l\u00e9phone</label><input type="text" name="prospect[additional_contacts]['+idx+'][phone]" value="'+phone.replace(/"/g,'&quot;')+'"></p>'+
              '</div>'+
              '<div style="display:flex;align-items:center;justify-content:space-between;margin-top:10px;padding-top:10px;border-top:1px solid #f0e6dc;">'+
              '<label class="acdc-toggle-wrap" style="display:flex;align-items:center;gap:10px;cursor:pointer;">'+
              '<input type="checkbox" name="prospect[additional_contacts]['+idx+'][is_copy_recipient]" value="1"'+(isCopy==='1' ? ' checked' : '')+' style="display:none">'+
              '<span class="acdc-toggle-track" style="position:relative;display:inline-block;width:44px;height:24px;border-radius:12px;background:'+(isCopy==='1'?'#d6a353':'#d9dfe8')+';transition:background .2s;flex-shrink:0;">'+
              '<span style="position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;transition:transform .2s;'+(isCopy==='1'?'transform:translateX(20px);':'')+'"></span>'+
              '</span>'+
              '<span style="font-size:13px;font-weight:500;color:'+(isCopy==='1'?'#8b5b23':'#4b5d76')+';">Destinataire en copie&nbsp;&nbsp;<strong>'+(isCopy==='1'?'Oui':'Non')+'</strong>'+
              '</span>'+
              '</label>'+
              '<button type="button" data-remove-contact style="background:none;border:none;color:#e06d6d;cursor:pointer;font-size:13px;padding:4px 8px;">× Retirer</button>'+
              '</div>';
            contactList.appendChild(card);
            contactIndex += 1;
            ['[data-contact-first-name]','[data-contact-last-name]','[data-contact-job-title]','[data-contact-email]','[data-contact-phone]'].forEach(function(sel){q(sel).value='';});
            q('[data-contact-is-copy]').checked=false;
            var contactModal = document.getElementById('acdc-prospect-contact-modal');
            if (contactModal && typeof closeModalElement === 'function') {
              closeModalElement(contactModal);
            } else if (contactModal) {
              contactModal.hidden = true;
              contactModal.setAttribute('aria-hidden', 'true');
              document.body.classList.remove('acdc-modal-open');
              document.documentElement.classList.remove('acdc-modal-open');
            }
          });
          contactList.addEventListener('click', function(e){var btn=e.target.closest('[data-remove-contact]'); if(btn){btn.parentNode.remove();}});
        }
      })();
      </script>
      <?php endif; ?>

      <?php if ( ! $read_only ) : ?>
      <script>
      /* hotfix9 — Cascade thématique → filtre formations */
      (function(){
        var selTh  = document.getElementById('acdc-prospect-thematique');
        var selFo  = document.getElementById('acdc-prospect-formation-id');
        var hidTr  = document.getElementById('acdc-prospect-desired-training');
        if ( ! selTh || ! selFo ) { return; }

        function filterFormations() {
          var code = selTh.value;
          var opts = selFo.querySelectorAll('option');
          var firstVisible = null;
          opts.forEach(function(opt) {
            if ( ! opt.value ) { return; } /* garder l'option vide */
            var th = opt.getAttribute('data-thematique') || '';
            var show = ! code || th === code;
            opt.style.display = show ? '' : 'none';
            if ( show && ! firstVisible ) { firstVisible = opt; }
          });
          /* Si la formation sélectionnée n'appartient plus à la thématique, réinitialiser */
          var current = selFo.options[selFo.selectedIndex];
          if ( current && current.value ) {
            var currentTh = current.getAttribute('data-thematique') || '';
            if ( code && currentTh !== code ) {
              selFo.value = '';
              if ( hidTr ) { hidTr.value = ''; }
            }
          }
        }

        selTh.addEventListener('change', filterFormations);

        selFo.addEventListener('change', function() {
          if ( hidTr ) {
            var opt = selFo.options[selFo.selectedIndex];
            hidTr.value = opt && opt.value ? opt.textContent.replace(/^\[\d+\]\s*/, '').split(' \u2014 ')[0].trim() : '';
          }
        });

        /* Appliquer au chargement */
        filterFormations();
      })();
      </script>
      <?php endif; ?>
    </form>
    <?php if ( isset( $prospect ) && $prospect && ! empty( $prospect->email ) && isset( $read_only ) && $read_only ) :
      $to_name_pr = trim( $prospect->first_name . ' ' . $prospect->last_name );
      if ( '' === $to_name_pr && ! empty( $prospect->company_name ) ) { $to_name_pr = $prospect->company_name; }
      $this->render_direct_email_modal( $prospect, 'prospect', $prospect->email, $to_name_pr, 'prospects' );
    endif; ?>
    <?php
  }


  private function render_front_prospect_followup_tab( $action, $item_id ) {
    $prospect = $item_id ? $this->get_prospect( $item_id ) : null;
    if ( 'view' === $action && ! $prospect ) {
      echo '<div class="acdc-panel"><p>Prospect introuvable.</p></div>';
      return;
    }

    if ( 'view' !== $action ) {
      $prospects = $this->get_prospects();
      $dashboard_alerts = isset( $_GET['dashboard_alerts'] ) && '1' === (string) wp_unslash( $_GET['dashboard_alerts'] );
      $prospect_rows = array();

      foreach ( $prospects as $prospect_entry ) {
        $entry_latest_rdv = $this->get_prospect_latest_rdv( $prospect_entry );
        $has_alert = method_exists( $this, 'prospect_has_dashboard_alert' ) ? $this->prospect_has_dashboard_alert( $prospect_entry, $entry_latest_rdv ) : false;
        $prospect_rows[] = array(
          'entry'        => $prospect_entry,
          'latest_rdv'   => $entry_latest_rdv,
          'has_alert'    => $has_alert,
          'alert_status' => $this->get_prospect_alert_status( $prospect_entry ),
          'alert_badge'  => $this->get_prospect_alert_status_badge_html( $prospect_entry, true ),
        );
      }

      if ( $dashboard_alerts && ! empty( $prospect_rows ) ) {
        usort(
          $prospect_rows,
          static function( $left, $right ) {
            if ( $left['has_alert'] !== $right['has_alert'] ) {
              return $left['has_alert'] ? -1 : 1;
            }

            $left_date = ! empty( $left['entry']->created_at ) ? strtotime( (string) $left['entry']->created_at ) : 0;
            $right_date = ! empty( $right['entry']->created_at ) ? strtotime( (string) $right['entry']->created_at ) : 0;

            if ( $left_date === $right_date ) {
              $left_id = ! empty( $left['entry']->id ) ? (int) $left['entry']->id : 0;
              $right_id = ! empty( $right['entry']->id ) ? (int) $right['entry']->id : 0;
              return $right_id <=> $left_id;
            }

            return $right_date <=> $left_date;
          }
        );
      }
      $activity_counts_map = $this->get_prospect_activity_counts_map();
      $fu_status_options = $this->get_prospect_status_options();
      $fu_status_counts  = array();
      foreach ( array_keys( $fu_status_options ) as $fu_sk ) { $fu_status_counts[ $fu_sk ] = 0; }
      $fu_total_count = 0;
      /* ACDC 3.25.259 — LES PASTILLES COMPTENT LES JALONS FRANCHIS.
         Elles comptaient le statut courant : un prospect à « Proposition
         envoyée » affichait « Recueil envoyé 0 » alors que son recueil était
         bien parti. Un statut ne retient qu'une étape ; le parcours en compte
         plusieurs, et ce sont les faits qui les établissent.
         La somme des pastilles ne fait donc plus le total — c'est voulu, et
         c'est dit sous la barre. */
      $fu_milestones_map = array();
      $fu_jalons_by_row  = array();
      if ( ! empty( $prospect_rows ) ) {
        $fu_ids = array();
        foreach ( $prospect_rows as $fu_pr ) {
          if ( ! empty( $fu_pr['entry']->id ) ) { $fu_ids[] = (int) $fu_pr['entry']->id; }
        }
        $fu_milestones_map = $this->get_prospect_milestones_map( $fu_ids );

        foreach ( $prospect_rows as $fu_idx => $fu_pr ) {
          $fu_jalons = $this->get_prospect_milestones( $fu_pr['entry'], $fu_milestones_map );
          $fu_jalons_by_row[ $fu_idx ] = $fu_jalons;
          foreach ( $fu_jalons as $fu_j ) {
            if ( ! isset( $fu_status_counts[ $fu_j ] ) ) { $fu_status_counts[ $fu_j ] = 0; }
            $fu_status_counts[ $fu_j ]++;
          }
          $fu_total_count++;
        }
      }
      ?>
      <section class="acdc-section-head">
        <div>
          <h2>Suivi commercial</h2>
          <p>Suivi commercial des prospects, prise de contact et qualification du besoin avant recueil détaillé.</p>
        </div>
        <div class="acdc-inline-wrap">
          <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-prospects' ) : $this->portal_page_url( array( 'tab' => 'prospects' ) ) ); ?>">Voir les prospects</a>
        </div>
      </section>
      <style>
        .acdc-followup-table{width:100%;table-layout:fixed;}
        .acdc-followup-table th,
        .acdc-followup-table td{vertical-align:top;overflow:hidden !important;word-break:break-word;overflow-wrap:anywhere;}
        .acdc-followup-table th:nth-child(1),
        .acdc-followup-table td:nth-child(1){width:80px;white-space:normal;word-break:normal;overflow-wrap:break-word;}
        .acdc-followup-table th:nth-child(2),
        .acdc-followup-table td:nth-child(2){width:210px;}
        .acdc-followup-table th:nth-child(3),
        .acdc-followup-table td:nth-child(3){width:190px;}
        .acdc-followup-table th:nth-child(4),
        .acdc-followup-table td:nth-child(4){width:150px;}
        .acdc-followup-table th:nth-child(5),
        .acdc-followup-table td:nth-child(5){width:95px;}
        .acdc-followup-table th:nth-child(6),
        .acdc-followup-table td:nth-child(6){width:170px;}
        .acdc-followup-table th:nth-child(7),
        .acdc-followup-table td:nth-child(7){width:130px;}
        .acdc-followup-table th:nth-child(8),
        .acdc-followup-table td:nth-child(8){width:145px;}
        .acdc-followup-table th:nth-child(9),
        .acdc-followup-table td:nth-child(9){width:110px;}
        .acdc-followup-table th:nth-child(10),
        .acdc-followup-table td:nth-child(10){width:250px;max-width:250px;white-space:normal;word-break:break-word;overflow-wrap:anywhere;line-height:1.35;}
        .acdc-followup-table th:nth-child(11),
        .acdc-followup-table td:nth-child(11){width:150px;white-space:normal;word-break:normal;overflow-wrap:break-word;}
        .acdc-followup-table th:nth-child(12),
        .acdc-followup-table td:nth-child(12){width:88px;white-space:nowrap;word-break:normal;overflow-wrap:normal;text-align:center;}
        .acdc-followup-table thead th:nth-child(7),
        .acdc-followup-table thead th:nth-child(8),
        .acdc-followup-table thead th:nth-child(10),
        .acdc-followup-table thead th:nth-child(11){white-space:normal;}
        .acdc-followup-table thead th:nth-child(12){white-space:nowrap;}
        .acdc-fu-interaction-count{display:inline-block;font-size:11px;font-weight:600;color:#8b5b23;background:#faf2e2;border:0.5px solid #e9d4a6;padding:2px 8px;border-radius:999px;}
        .acdc-fu-interaction-date{display:block;margin-top:4px;font-size:12px;color:#64748b;}
        .acdc-followup-actions{display:flex;align-items:center;gap:var(--acdc-action-icon-gap, 12px);white-space:nowrap;position:relative;}
        .acdc-followup-actions .acdc-action-icon.is-alert,
        .acdc-followup-actions .acdc-row-action-icon.is-alert{color:#dd2627 !important;}
        .acdc-followup-table tbody tr.acdc-followup-row-alert > td{background:#f6e6e9 !important;}
        .acdc-followup-table tbody tr.acdc-followup-row-alert:hover > td{background:#f6e6e9 !important;}
        .acdc-followup-table tbody tr.acdc-followup-row-alert td:first-child{box-shadow:inset 4px 0 0 #dd2627;}
        .acdc-followup-actions .acdc-action-icon.is-alert,
        .acdc-followup-actions .acdc-action-icon.is-alert:hover,
        .acdc-followup-actions .acdc-action-icon.is-alert:focus{color:#dd2627 !important;}
        .acdc-followup-dashboard-callout{margin-bottom:14px;padding:14px 16px;border:1px solid #f1c9cd;border-radius:12px;background:#fff7f8;color:#7d1d1d;display:flex;align-items:center;gap:10px;font-weight:600;flex-wrap:wrap;}
        .acdc-followup-dashboard-callout__detail{width:100%;padding-left:28px;font-size:12px;font-weight:500;color:#8b5b23;}
        .acdc-followup-dashboard-callout strong{color:#dd2627;}
        .acdc-followup-alert-badge{display:inline-flex;align-items:center;justify-content:center;margin-top:6px;padding:4px 9px;border-radius:999px;background:#dd2627;color:#ffffff;font-size:11px;font-weight:700;line-height:1;text-transform:uppercase;letter-spacing:.02em;}
        .acdc-alert-status-badge{display:inline-flex;align-items:center;gap:6px;min-height:28px;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;line-height:1.2;border:1px solid transparent;}
        .acdc-alert-status-badge.is-compact{padding:5px 9px;font-size:11px;}
        .acdc-alert-status-badge.is-none{background:#eff6ff;color:#1d4ed8;border-color:rgba(59,130,246,.18);}
        .acdc-alert-status-badge.is-upcoming{background:#fff7ed;color:#92400e;border-color:rgba(217,119,6,.22);}
        .acdc-alert-status-badge.is-soon{background:#fff1f1;color:#9a1515;border-color:rgba(220,38,38,.22);}
        .acdc-alert-status-badge.is-done{background:#ecfdf3;color:#166534;border-color:rgba(22,163,74,.18);}
        .acdc-alert-status-cell-note,.acdc-alert-status-hint{margin-top:6px;font-size:12px;line-height:1.45;color:#64748b;max-width:260px;}
        .acdc-followup-alert-summary__status{margin-top:8px;}
      </style>
      <script>
      </script>
      <?php if ( $dashboard_alerts ) :
        $dashboard_alert_total = 0;
        $dashboard_alert_status_counts = array(
          'none'     => 0,
          'upcoming' => 0,
          'soon'     => 0,
          'done'     => 0,
        );
        foreach ( $prospect_rows as $prospect_row ) {
          if ( ! empty( $prospect_row['has_alert'] ) ) {
            $dashboard_alert_total++;
          }
          $status_key = isset( $prospect_row['alert_status']['code'] ) ? (string) $prospect_row['alert_status']['code'] : 'none';
          if ( ! isset( $dashboard_alert_status_counts[ $status_key ] ) ) {
            $dashboard_alert_status_counts[ $status_key ] = 0;
          }
          $dashboard_alert_status_counts[ $status_key ]++;
        }
        $dashboard_alert_parts = array();
        if ( ! empty( $dashboard_alert_status_counts['soon'] ) ) {
          $dashboard_alert_parts[] = $dashboard_alert_status_counts['soon'] . ' RDV imminent(s)';
        }
        if ( ! empty( $dashboard_alert_status_counts['upcoming'] ) ) {
          $dashboard_alert_parts[] = $dashboard_alert_status_counts['upcoming'] . ' RDV planifié(s)';
        }
        if ( ! empty( $dashboard_alert_status_counts['done'] ) ) {
          $dashboard_alert_parts[] = $dashboard_alert_status_counts['done'] . ' terminé(s)';
        }
      ?>
      <div class="acdc-followup-dashboard-callout">
        <?php echo $this->render_inline_icon( 'bell', 18 ); ?>
        <span><strong><?php echo esc_html( (string) $dashboard_alert_total ); ?></strong> alerte(s) mise(s) en avant depuis le tableau de bord.</span>
        <?php if ( ! empty( $dashboard_alert_parts ) ) : ?>
          <div class="acdc-followup-dashboard-callout__detail"><?php echo esc_html( implode( ' • ', $dashboard_alert_parts ) ); ?></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="acdc-panel">
        <?php
        $fu_dot_colors = array(
          'À traiter' => '#3b5bdb', 'Premier contact' => '#0369a1', 'À relancer' => '#c2410c',
          'Rendez-vous planifié' => '#7e22ce', 'Recueil envoyé' => '#854d0e', 'Proposition envoyée' => '#9d174d',
          'Devis envoyé' => '#15803d', 'Converti' => '#065f46', 'Perdu' => '#991b1b',
        );
        ?>
        <div class="acdc-fu-status-tabs" data-acdc-fu-status-tabs="1">
          <button type="button" class="acdc-fu-tab is-active" data-fu-filter="__all__">Tous <span class="acdc-fu-tab-count"><?php echo (int) $fu_total_count; ?></span></button>
          <?php foreach ( $fu_status_options as $fu_key => $fu_label ) :
            $fu_c   = (int) ( isset( $fu_status_counts[ $fu_key ] ) ? $fu_status_counts[ $fu_key ] : 0 );
            $fu_dot = isset( $fu_dot_colors[ $fu_key ] ) ? $fu_dot_colors[ $fu_key ] : '#64748b';
            ?>
            <button type="button" class="acdc-fu-tab<?php echo ( 0 === $fu_c ) ? ' is-empty' : ''; ?>" data-fu-filter="<?php echo esc_attr( $fu_key ); ?>"><span class="acdc-fu-tab-dot" style="background:<?php echo esc_attr( $fu_dot ); ?>;"></span><?php echo esc_html( $fu_label ); ?> <span class="acdc-fu-tab-count"><?php echo $fu_c; ?></span></button>
          <?php endforeach; ?>
        </div>
        <p style="margin:-8px 0 14px;font-size:12px;color:#64748b;">
          Chaque pastille compte les prospects qui ont franchi cette étape — recueil parti, proposition envoyée, devis émis.
          Un prospect avancé en coche donc plusieurs : le total des pastilles dépasse normalement « Tous ».
        </p>
        <p class="acdc-fu-status-empty" data-acdc-fu-status-empty="1" style="display:none;color:#64748b;padding:12px 4px;">Aucun prospect pour cette étape.</p>
        <style>
          .acdc-fu-status-tabs{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 16px;}
          .acdc-fu-status-tabs .acdc-fu-tab{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:500;padding:7px 13px;border-radius:999px;background:#f1efe8;color:#3a3a3a;border:0.5px solid #e2ddd0;cursor:pointer;line-height:1;}
          .acdc-fu-status-tabs .acdc-fu-tab:hover{background:#ece7da;}
          .acdc-fu-status-tabs .acdc-fu-tab.is-active{background:#8b5b23;color:#fff;border-color:#8b5b23;}
          .acdc-fu-status-tabs .acdc-fu-tab.is-empty{opacity:.5;}
          .acdc-fu-status-tabs .acdc-fu-tab-dot{width:8px;height:8px;border-radius:50%;flex:0 0 auto;}
          .acdc-fu-status-tabs .acdc-fu-tab-count{background:#fff;color:#5f5e5a;padding:1px 8px;border-radius:999px;font-size:11px;font-weight:600;}
          .acdc-fu-status-tabs .acdc-fu-tab.is-active .acdc-fu-tab-count{background:rgba(255,255,255,.25);color:#fff;}
          .acdc-fu-status-tabs .acdc-fu-tab.is-active .acdc-fu-tab-dot{display:none;}
        </style>
        <div class="acdc-table-wrap">
          <table class="acdc-table acdc-table-prospects acdc-followup-table" data-acdc-table-id="crm-prospects-followup">
            <colgroup>
              <col><col><col><col><col><col><col><col><col><col><col><col>
            </colgroup>
            <thead>
              <tr>
                <th>Profil</th>
                <th>Entreprise / Contact</th>
                <th>Formation souhaitée</th>
                <th>Assigné à</th>
                <th>Statut</th>
                <th>Statut d’alerte</th>
                <th>Prochain RDV</th>
                <th>Source</th>
                <th>Dernière relance</th>
                <th>Session</th>
                <th>Dernière interaction</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php if ( ! empty( $prospect_rows ) ) : foreach ( $prospect_rows as $prospect_row_index => $prospect_row ) :
              $entry = $prospect_row['entry'];
              $entry_latest_rdv = $prospect_row['latest_rdv'];
              $row_has_alert = ! empty( $prospect_row['has_alert'] );
              /* ACDC 3.25.259 — La ligne porte TOUS ses jalons : le filtre suit
                 la même règle que les compteurs, sinon une pastille annoncerait
                 des lignes que le clic ne montrerait pas. */
              $row_jalons = isset( $fu_jalons_by_row[ $prospect_row_index ] )
                ? $fu_jalons_by_row[ $prospect_row_index ]
                : array( $entry->status ?: 'À traiter' );
            ?>
              <tr class="<?php echo $row_has_alert ? 'acdc-followup-row-alert' : ''; ?>" data-fu-status="<?php echo esc_attr( $entry->status ?: 'À traiter' ); ?>" data-fu-jalons="<?php echo esc_attr( '|' . implode( '|', $row_jalons ) . '|' ); ?>">
                <td><?php echo esc_html( $entry->profile_type ?: '—' ); ?></td>
                <td><strong><?php echo esc_html( $this->get_prospect_company_display_name( $entry ) ); ?></strong><br><small><?php echo esc_html( $this->get_prospect_contact_person_name( $entry ) ); ?></small></td>
                <td><?php echo esc_html( $entry->desired_training ?: 'À définir' ); ?></td>
                <td><?php echo esc_html( $entry->assigned_to ?: '—' ); ?></td>
                <td>
                  <?php echo $this->get_prospect_status_badge_html( $entry->status ?: 'À traiter', true ); ?>
                  <?php if ( $row_has_alert ) : ?>
                    <span class="acdc-followup-alert-badge">Alerte</span>
                  <?php endif; ?>
                  <?php
                    $_status_terminal = in_array( (string) ( $entry->status ?: '' ), array( 'Converti', 'Perdu' ), true );
                    if ( ! $_status_terminal && ! empty( $entry->updated_at ) ) {
                      $_days_inactive = (int) floor( ( current_time( 'timestamp' ) - strtotime( (string) $entry->updated_at ) ) / DAY_IN_SECONDS );
                      if ( $_days_inactive >= 10 ) {
                        echo '<div style="margin-top:5px;"><span style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:999px;background:#f1f5f9;color:#64748b;border:1px solid rgba(100,116,139,.20);font-size:11px;font-weight:600;line-height:1.2;white-space:nowrap;">⏳ En attente · ' . esc_html( (string) $_days_inactive ) . ' j</span></div>';
                      }
                    }
                  ?>
                </td>
                <td>
                  <?php echo wp_kses_post( $prospect_row['alert_badge'] ); ?>
                  <div class="acdc-alert-status-cell-note"><?php echo nl2br( esc_html( isset( $prospect_row['alert_status']['hint'] ) ? (string) $prospect_row['alert_status']['hint'] : '' ) ); ?></div>
                </td>
                <td><?php echo esc_html( ( $entry_latest_rdv && ! empty( $entry_latest_rdv->rdv_at ) ) ? mysql2date( 'j F Y à H\hi', $entry_latest_rdv->rdv_at ) : '—' ); ?></td>
                <td><?php echo esc_html( $entry->source ?: '—' ); ?></td>
                <td><?php
                  $lf_val = $entry->last_followup ?: '';
                  if ( $lf_val ) {
                    $lf_sep = mb_strpos( $lf_val, ' — ' );
                    if ( false !== $lf_sep ) {
                      echo esc_html( mb_substr( $lf_val, 0, $lf_sep ) ) . '<br>' . esc_html( mb_substr( $lf_val, $lf_sep + 3 ) );
                    } else {
                      echo esc_html( $lf_val );
                    }
                  } else {
                    echo '—';
                  }
                ?></td>
                <td><?php echo esc_html( $entry->session_label ?: '—' ); ?></td>
                <td>
                  <?php
                    $fu_act = isset( $activity_counts_map[ (int) $entry->id ] ) ? $activity_counts_map[ (int) $entry->id ] : array( 'count' => 0, 'last_at' => '' );
                  ?>
                  <?php if ( ! empty( $fu_act['count'] ) ) : ?>
                    <span class="acdc-fu-interaction-count"><?php echo (int) $fu_act['count']; ?> interaction<?php echo ( (int) $fu_act['count'] > 1 ) ? 's' : ''; ?></span>
                    <span class="acdc-fu-interaction-date"><?php echo esc_html( ! empty( $fu_act['last_at'] ) ? mysql2date( 'd/m/Y H\hi', $fu_act['last_at'] ) : '' ); ?></span>
                  <?php else : ?>
                    <span class="acdc-fu-interaction-date">—</span>
                  <?php endif; ?>
                </td>
                <td data-acdc-patched="1">
                  <?php
                    $prospect_id     = (int) $entry->id;
                    $menu_id         = 'acdc-followup-menu-' . $prospect_id;
                    $view_url        = is_admin() ? admin_url( 'admin.php?page=acdc-of-prospects&action=view&id=' . $prospect_id ) : $this->portal_page_url( array( 'tab' => 'prospects', 'action' => 'view', 'id' => $prospect_id ) );
                    $followup_url    = is_admin() ? $this->admin_prospect_followup_url( array( 'action' => 'view', 'item_id' => $prospect_id ) ) : $this->portal_page_url( array( 'tab' => 'prospect_followup', 'action' => 'view', 'item_id' => $prospect_id ) );
                    $edit_url        = is_admin() ? admin_url( 'admin.php?page=acdc-of-prospects&action=edit&item_id=' . $prospect_id ) : $this->portal_page_url( array( 'tab' => 'prospects', 'action' => 'edit', 'item_id' => $prospect_id ) );
                    $rdv_url         = is_admin() ? $this->admin_prospect_followup_url( array( 'action' => 'view', 'item_id' => $prospect_id, 'open_rdv' => 1, 'open_from_prospect_list' => 1 ) ) : $this->portal_page_url( array( 'tab' => 'prospect_followup', 'action' => 'view', 'item_id' => $prospect_id, 'open_rdv' => 1, 'open_from_prospect_list' => 1 ) );
                    $recueil_url     = is_admin() ? admin_url( 'admin.php?page=acdc-of-needs&action=new&prospect_id=' . $prospect_id ) : $this->portal_page_url( array( 'tab' => 'needs', 'action' => 'new', 'prospect_id' => $prospect_id ) );
                    $inscription_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-register-training&action=new&prospect_id=' . $prospect_id ) : $this->portal_page_url( array( 'tab' => 'register_training', 'action' => 'new', 'prospect_id' => $prospect_id ) );
                  ?>
                  <?php
                    $proposal_url = is_admin() ? $this->admin_tab_url( 'proposals', array( 'action' => 'new', 'prospect_id' => $prospect_id ) ) : $this->portal_page_url( array( 'tab' => 'proposals', 'action' => 'new', 'prospect_id' => $prospect_id ) );
                  ?>
                  <div class="acdc-prospect-actions" data-acdc-prospect-actions>
                    <div class="acdc-prospect-action-menu">
                      <button type="button"
                              class="acdc-prospect-action-trigger"
                              data-acdc-prospect-menu-toggle
                              aria-haspopup="true"
                              aria-expanded="false"
                              aria-label="Actions complémentaires"
                              title="Actions complémentaires">
                        <?php echo $this->render_inline_icon( 'more-horizontal', 25 ); ?>
                        <span class="acdc-action-hub-sr screen-reader-text">Actions complémentaires</span>
                      </button>
                      <div class="acdc-prospect-action-dropdown" data-acdc-prospect-menu hidden>
                        <a class="acdc-prospect-action-item" href="<?php echo esc_url( $edit_url ); ?>">Modifier le prospect</a>
                        <a class="acdc-prospect-action-item" href="<?php echo esc_url( $rdv_url ); ?>">Ajouter un rendez-vous</a>
                        <a class="acdc-prospect-action-item" href="<?php echo esc_url( $recueil_url ); ?>">Recueil des besoins</a>
                        <?php /* ACDC 3.25.241 — Devis et convention retirés : ils réclament des
                                 dates, une durée, un effectif et un tarif qu'un suivi commercial ne
                                 porte pas. La proposition commerciale est la suite. */ ?>
                        <a class="acdc-prospect-action-item" href="<?php echo esc_url( $proposal_url ); ?>">Proposition commerciale</a>
                      </div>
                    </div>
                    <a class="acdc-row-action-icon <?php echo $row_has_alert ? 'is-alert' : ''; ?>" href="<?php echo esc_url( $followup_url ); ?>" data-acdc-iconized="1" aria-label="Voir le suivi commercial" title="Voir le suivi commercial">
                      <?php echo $this->render_inline_icon( 'view', 25 ); ?>
                      <span class="acdc-action-hub-sr screen-reader-text">Voir le suivi commercial</span>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; else : ?>
              <tr><td colspan="12">Aucun prospect enregistré.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
        <script>
        (function(){
          var tabs=document.querySelector('[data-acdc-fu-status-tabs]');
          if(!tabs){return;}
          var table=document.querySelector('.acdc-followup-table');
          var empty=document.querySelector('[data-acdc-fu-status-empty]');
          if(!table){return;}
          tabs.addEventListener('click',function(e){
            var btn=e.target.closest('[data-fu-filter]');
            if(!btn){return;}
            var filter=btn.getAttribute('data-fu-filter');
            var all=tabs.querySelectorAll('[data-fu-filter]');
            for(var i=0;i<all.length;i++){all[i].classList.remove('is-active');}
            btn.classList.add('is-active');
            var rows=table.querySelectorAll('tbody > tr');
            var visible=0;
            for(var j=0;j<rows.length;j++){
              var r=rows[j];
              if(!r.hasAttribute('data-fu-status')){continue;}
              /* Un prospect avancé porte plusieurs jalons : on cherche le jalon
                 dans la liste de la ligne, pas l'égalité avec son statut. */
              var jalons=r.getAttribute('data-fu-jalons')||('|'+r.getAttribute('data-fu-status')+'|');
              var match=(filter==='__all__'||jalons.indexOf('|'+filter+'|')!==-1);
              r.style.display=match?'':'none';
              if(match){visible++;}
            }
            if(empty){empty.style.display=visible===0?'block':'none';}
          });
        })();
        </script>
      </div>
      <?php
      return;
    }

    $copy_recipients = $this->get_prospect_copy_recipients( $prospect );
    $additional_contacts = $this->get_prospect_additional_contacts( $prospect );
    $need_rows      = $this->get_prospect_related_needs( $prospect );
    $proposal_rows  = $this->get_prospect_related_proposals( $prospect );
    $contract_rows  = $this->get_prospect_related_contracts( $prospect );
    $quote_rows    = $this->get_prospect_related_quotes( $prospect );
    $rdv_rows = $this->get_prospect_rdv_rows( $prospect->id );
    $latest_rdv = $this->get_prospect_latest_rdv( $prospect, $rdv_rows );
    $latest_rdv_summary = $this->get_prospect_rdv_summary( $latest_rdv );
    $followup_has_alert = method_exists( $this, 'prospect_has_dashboard_alert' ) ? $this->prospect_has_dashboard_alert( $prospect, $latest_rdv ) : false;
    $alert_rdv_id = ( $followup_has_alert && $latest_rdv && ! empty( $latest_rdv->id ) ) ? (int) $latest_rdv->id : 0;
    $alert_anchor_id = $alert_rdv_id ? 'acdc-rdv-alert-row-' . $alert_rdv_id : 'acdc-rdv-block';
    $alert_summary_label = $latest_rdv_summary ? $latest_rdv_summary : 'Rendez-vous dépassé à traiter.';
    $alert_datetime_label = ( $latest_rdv && ! empty( $latest_rdv->rdv_at ) ) ? mysql2date( 'd/m/Y H\hi', $latest_rdv->rdv_at ) : '—';
    $alert_status = $this->get_prospect_alert_status( $prospect );
    $alert_status_badge_html = $this->get_prospect_alert_status_badge_html( $prospect );
    $alert_reschedule_url = $alert_rdv_id
      ? ( is_admin()
        ? $this->admin_prospect_followup_url( array( 'action' => 'view', 'item_id' => (int) $prospect->id, 'open_rdv' => 1, 'reschedule_rdv' => $alert_rdv_id ) )
        : $this->portal_page_url( array( 'tab' => 'prospect_followup', 'action' => 'view', 'item_id' => (int) $prospect->id, 'open_rdv' => 1, 'reschedule_rdv' => $alert_rdv_id ) ) )
      : '';
    $reschedule_rdv_id = isset( $_GET['reschedule_rdv'] ) ? absint( $_GET['reschedule_rdv'] ) : 0;
    $editing_rdv = null;
    if ( $reschedule_rdv_id ) {
      foreach ( $rdv_rows as $rdv_candidate ) {
        if ( (int) $rdv_candidate->id === $reschedule_rdv_id ) {
          $editing_rdv = $rdv_candidate;
          break;
        }
      }
    }
    $editing_rdv_at = ( $editing_rdv && ! empty( $editing_rdv->rdv_at ) ) ? mysql2date( 'Y-m-d\TH:i', $editing_rdv->rdv_at, false ) : '';
    $editing_info_html = $editing_rdv ? $this->get_prospect_rdv_comments_text( $editing_rdv ) : '';
    $editing_mode = $editing_rdv ? $this->get_prospect_rdv_mode( $editing_rdv ) : '';
    $editing_link = $editing_rdv ? $this->get_prospect_rdv_meeting_link( $editing_rdv ) : '';
    $edit_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-prospects&action=edit&item_id=' . (int) $prospect->id ) : $this->portal_page_url( array( 'tab' => 'prospects', 'action' => 'edit', 'item_id' => (int) $prospect->id ) );
    $timezone_label = wp_timezone_string() ? wp_timezone_string() : 'Europe/Paris';
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Modifier un suivi commercial</h2>
        <p>Prise de contact et qualification du besoin avant recueil détaillé.</p>
      </div>
      <div class="acdc-inline-wrap">
        <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( is_admin() ? $this->admin_prospect_followup_url() : $this->portal_page_url( array( 'tab' => 'prospect_followup' ) ) ); ?>">Retour à la liste</a>
        <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $edit_url ); ?>">Modifier le prospect</a>
        <button type="button" class="acdc-button acdc-button-primary" data-acdc-prospect-rdv-open data-prospect-id="<?php echo (int) $prospect->id; ?>" onclick="if(window.acdcOpenProspectRdvModal){window.acdcOpenProspectRdvModal(this);return false;}">Nouveau rendez-vous</button>
      </div>
    </section>

    <?php if ( $followup_has_alert ) : ?>
      <div class="acdc-panel acdc-mb-18 acdc-followup-alert-hero acdc-js-alert-pulse" id="acdc-followup-alert-hero">
        <div class="acdc-followup-alert-hero-head">
          <div>
            <span class="acdc-followup-alert-kicker">Alerte active</span>
            <h3>Un rendez-vous en retard déclenche une alerte sur ce suivi commercial.</h3>
            <p>L’alerte se trouve dans le bloc <strong>« Alerte / rendez-vous »</strong> ci-dessous et sur la ligne du rendez-vous concerné dans le tableau <strong>« Rendez-vous »</strong>.</p>
          </div>
          <div class="acdc-followup-alert-actions">
            <a class="acdc-button acdc-button-soft" href="#acdc-followup-alert-summary">Voir le bloc d’alerte</a>
            <a class="acdc-button acdc-button-soft" href="#<?php echo esc_attr( $alert_anchor_id ); ?>">Voir le rendez-vous concerné</a>
            <?php if ( $alert_reschedule_url ) : ?>
              <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $alert_reschedule_url ); ?>">Reporter ce rendez-vous</a>
            <?php endif; ?>
          </div>
        </div>
        <div class="acdc-followup-alert-hero-body">
          <div class="acdc-followup-alert-hero-card">
            <strong>Rendez-vous à l’origine de l’alerte</strong>
            <p><?php echo esc_html( $alert_datetime_label ); ?></p>
            <p><?php echo esc_html( $alert_summary_label ); ?></p>
          </div>
          <div class="acdc-followup-alert-hero-card">
            <strong>Comment lever l’alerte</strong>
            <ol>
              <li><strong>Reporter le rendez-vous</strong> vers une date future.</li>
              <li><strong>Créer un nouveau rendez-vous futur</strong> si un nouveau créneau est convenu.</li>
              <li><strong>Annuler le rendez-vous</strong> si ce suivi n’a plus lieu d’être.</li>
            </ol>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="acdc-panel acdc-prospect-followup-grid">
      <div class="acdc-prospect-followup-card"><h3>Profil / formation souhaitée</h3><p><strong>Profil</strong> : <?php echo esc_html( $prospect->profile_type ?: '—' ); ?></p><p><strong>Formation souhaitée</strong> : <?php echo esc_html( $prospect->desired_training ?: 'À définir' ); ?></p></div>
      <div class="acdc-prospect-followup-card"><h3>Nom / prénom / entreprise</h3><p><strong>Nom / prénom</strong> : <?php echo esc_html( $this->get_prospect_contact_person_name( $prospect ) ); ?></p><p><strong>Entreprise</strong> : <?php echo esc_html( $this->get_prospect_company_display_name( $prospect ) ); ?></p></div>
      <div class="acdc-prospect-followup-card"><h3>Contact</h3><p><strong>E-mail</strong> : <?php echo esc_html( ( $this->is_individual_prospect_profile( $prospect->profile_type ) ? $prospect->email : ( ! empty( $prospect->signer_email ) ? $prospect->signer_email : $prospect->email ) ) ?: '—' ); ?></p><p><strong>Téléphone</strong> : <?php echo esc_html( ( $this->is_individual_prospect_profile( $prospect->profile_type ) ? $prospect->phone : ( ! empty( $prospect->company_phone ) ? $prospect->company_phone : ( ! empty( $prospect->signer_phone ) ? $prospect->signer_phone : $prospect->phone ) ) ) ?: '—' ); ?></p><p></p></div>
      <div class="acdc-prospect-followup-card"><h3>Commentaire</h3><p><?php echo nl2br( esc_html( $prospect->comment_text ?: '—' ) ); ?></p></div>
      <div class="acdc-prospect-followup-card"><h3>Assigné / statut</h3><p><strong>Assigné à</strong> : <?php echo esc_html( $prospect->assigned_to ?: '—' ); ?></p><p><strong>Statut</strong> : <?php echo esc_html( $prospect->status ?: 'À traiter' ); ?></p></div>
      <div class="acdc-prospect-followup-card <?php echo $followup_has_alert ? 'acdc-followup-card-alert acdc-js-alert-pulse' : ''; ?>" id="acdc-followup-alert-summary"><h3>Alerte / rendez-vous <?php if ( $followup_has_alert ) : ?><span class="acdc-followup-alert-badge acdc-followup-alert-badge-inline">Alerte active</span><?php endif; ?></h3><?php if ( $followup_has_alert ) : ?><div class="acdc-followup-inline-alert-note"><strong>Où est l’alerte ?</strong> Le rendez-vous du <strong><?php echo esc_html( $alert_datetime_label ); ?></strong> est dépassé. Il est également mis en évidence dans le tableau <strong>« Rendez-vous »</strong> ci-dessous.</div><div class="acdc-followup-inline-alert-note acdc-followup-inline-alert-note-help"><strong>Comment lever l’alerte ?</strong> Reportez ce rendez-vous, créez un nouveau rendez-vous futur ou annulez-le s’il n’est plus nécessaire.</div><?php endif; ?><p><strong>Statut d’alerte</strong> : <?php echo wp_kses_post( $alert_status_badge_html ); ?></p><div class="acdc-alert-status-hint"><?php echo esc_html( isset( $alert_status['hint'] ) ? (string) $alert_status['hint'] : '' ); ?></div><p><strong>Alerte contact</strong> : <?php echo esc_html( $prospect->contact_alert_at ? mysql2date( 'd/m/Y H\hi', $prospect->contact_alert_at ) : '—' ); ?></p><p><strong>Date et heure du prochain rendez-vous</strong> : <?php echo esc_html( ( $latest_rdv && ! empty( $latest_rdv->rdv_at ) ) ? mysql2date( 'd/m/Y H\hi', $latest_rdv->rdv_at ) : '—' ); ?></p><p><strong>Résumé du prochain rendez-vous</strong> : <?php echo esc_html( $latest_rdv_summary ? $latest_rdv_summary : '—' ); ?></p></div>
      <div class="acdc-prospect-followup-card"><h3>Source / relance / session</h3><p><strong>Source</strong> : <?php echo esc_html( $prospect->source ?: '—' ); ?></p><p><strong>Dernière relance</strong> : <?php echo esc_html( $prospect->last_followup ?: '—' ); ?></p><p><strong>Session</strong> : <?php echo esc_html( $prospect->session_label ?: '—' ); ?></p><p><strong>Ajouté le</strong> : <?php echo esc_html( $prospect->created_at ? mysql2date( 'd/m/Y H:i', $prospect->created_at ) : '—' ); ?></p></div>
    </div>

    <?php if ( ! empty( $additional_contacts ) ) : ?>
      <div class="acdc-panel acdc-mb-18">
        <h3>Contacts annexes</h3>
        <div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Nom</th><th>Prénom</th><th>Poste</th><th>E-mail</th><th>Téléphone</th></tr></thead><tbody><?php foreach ( $additional_contacts as $contact ) : ?><tr><td><?php echo esc_html( $contact['last_name'] ?: '—' ); ?></td><td><?php echo esc_html( $contact['first_name'] ?: '—' ); ?></td><td><?php echo esc_html( $contact['job_title'] ?: '—' ); ?></td><td><?php echo esc_html( $contact['email'] ?: '—' ); ?></td><td><?php echo esc_html( $contact['phone'] ?: '—' ); ?></td></tr><?php endforeach; ?></tbody></table></div>
      </div>
    <?php endif; ?>

    <?php $this->render_front_prospect_activity_journal( $prospect ); ?>

    <div class="acdc-panel acdc-mb-18">
    <div class="acdc-panel acdc-mb-18" id="acdc-rdv-block">
      <h3>Rendez-vous</h3>
      <div class="acdc-table-wrap"><table class="acdc-table acdc-rdv-history-table" data-acdc-table-id="crm-rdv-history"><thead><tr><th>Dates</th><th>Détail du rendez-vous</th><th>Commentaires</th><th>Reporter le rendez-vous</th><th>Annuler le rendez-vous</th></tr></thead><tbody><?php if ( ! empty( $rdv_rows ) ) : foreach ( $rdv_rows as $row ) : ?><?php $rdv_comments = $this->get_prospect_rdv_comments_text( $row ); $rdv_post_comment = $this->get_prospect_rdv_post_comment_text( $row ); $is_cancelled = $this->is_prospect_rdv_cancelled( $row ); $is_alert_row = $followup_has_alert && $alert_rdv_id && (int) $row->id === $alert_rdv_id; $reschedule_url = is_admin()
                ? $this->admin_prospect_followup_url( array( 'action' => 'view', 'item_id' => (int) $prospect->id, 'open_rdv' => 1, 'reschedule_rdv' => (int) $row->id ) )
                : $this->portal_page_url( array( 'tab' => 'prospect_followup', 'action' => 'view', 'item_id' => (int) $prospect->id, 'open_rdv' => 1, 'reschedule_rdv' => (int) $row->id ) ); ?><tr id="<?php echo $is_alert_row ? esc_attr( 'acdc-rdv-alert-row-' . (int) $row->id ) : ''; ?>" class="<?php echo $is_alert_row ? 'acdc-rdv-alert-row acdc-js-alert-pulse' : ''; ?>"><td><?php echo esc_html( mysql2date( 'd/m/Y H\hi', $row->rdv_at ) . ( ! empty( $row->timezone_label ) ? ' — ' . $row->timezone_label : '' ) ); ?><?php if ( $is_alert_row ) : ?><span class="acdc-followup-alert-badge acdc-followup-alert-badge-inline">Alerte active</span><?php endif; ?></td><td><?php if ( $is_alert_row ) : ?><div class="acdc-rdv-alert-note"><strong>Ce rendez-vous est à l’origine de l’alerte.</strong><br>Pour la lever, reportez ce rendez-vous, créez un nouveau rendez-vous futur ou annulez-le si ce suivi n’est plus nécessaire.</div><?php endif; ?><?php echo wp_kses_post( $this->render_prospect_rdv_detail_html( $row ) ); ?></td><td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acdc-rdv-post-comment-form"><?php wp_nonce_field( 'acdc_save_prospect_rdv_post_comment_' . $row->id ); ?><input type="hidden" name="action" value="acdc_save_prospect_rdv_post_comment"><input type="hidden" name="prospect_id" value="<?php echo (int) $prospect->id; ?>"><input type="hidden" name="rdv_id" value="<?php echo (int) $row->id; ?>"><?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-prospect-followup"><?php else : ?><input type="hidden" name="return_tab" value="prospect_followup"><?php endif; ?><textarea name="post_comment_text" rows="4" placeholder="Ajouter un commentaire post rendez-vous"><?php echo esc_textarea( $rdv_post_comment ); ?></textarea><button type="submit" class="acdc-button acdc-button-soft">Enregistrer</button></form></td><td><?php if ( ! $is_cancelled ) : ?><div class="acdc-rdv-inline-action"><?php if ( $is_alert_row ) : ?><p class="acdc-rdv-inline-action-help">Action recommandée pour lever l’alerte.</p><?php endif; ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $reschedule_url ); ?>">Reporter</a></div><?php else : ?>—<?php endif; ?></td><td><?php if ( ! $is_cancelled ) : ?><div class="acdc-rdv-inline-action"><?php if ( $is_alert_row ) : ?><p class="acdc-rdv-inline-action-help">À utiliser si le rendez-vous n’a plus lieu d’être.</p><?php endif; ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-flex;"><?php wp_nonce_field( 'acdc_cancel_prospect_rdv_' . $row->id ); ?><input type="hidden" name="action" value="acdc_cancel_prospect_rdv"><input type="hidden" name="prospect_id" value="<?php echo (int) $prospect->id; ?>">
            <input type="hidden" name="rdv_id" value="<?php echo (int) $row->id; ?>"><?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-prospect-followup"><?php else : ?><input type="hidden" name="return_tab" value="prospect_followup"><?php endif; ?><input type="hidden" name="open_rdv" value="1"><button type="submit" class="acdc-button acdc-button-soft">Annuler</button></form></div><?php else : ?>Annulé<?php endif; ?></td></tr><?php endforeach; else : ?><tr><td colspan="5">Aucun rendez-vous enregistré.</td></tr><?php endif; ?></tbody></table></div>
    </div>

    <div class="acdc-panel acdc-mb-18">
      <h3>Recueil des besoins</h3>
      <div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Dates</th><th>Statut</th><th>Envoyé le</th><th>Prochaine action</th><th>Priorité</th><th>Actions</th></tr></thead><tbody><?php if ( ! empty( $need_rows ) ) : foreach ( $need_rows as $row ) : $need_date = ! empty( $row->collection_date ) ? mysql2date( 'd/m/Y', $row->collection_date ) : mysql2date( 'd/m/Y H:i', $row->created_at ); $need_status = ! empty( $row->status ) ? (string) $row->status : 'Nouveau'; $need_sent_at = ! empty( $row->sent_at ) ? mysql2date( 'd/m/Y H\hi', $row->sent_at ) : '—'; $need_next_action = ! empty( $row->next_action ) ? wp_trim_words( wp_strip_all_tags( (string) $row->next_action ), 12, '…' ) : '—'; $need_priority = ! empty( $row->priority_level ) ? (string) $row->priority_level : 'Normale'; $need_source_prospect_id = ! empty( $row->source_prospect_id ) ? (int) $row->source_prospect_id : ( ! empty( $prospect->id ) ? (int) $prospect->id : 0 ); $need_pid_qs = $need_source_prospect_id ? '&prospect_id=' . $need_source_prospect_id : ''; $need_view_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-needs&action=view&item_id=' . (int) $row->id . $need_pid_qs ) : $this->portal_page_url( array_filter( array( 'tab' => 'needs', 'action' => 'view', 'item_id' => (int) $row->id, 'prospect_id' => $need_source_prospect_id ) ) ); $need_edit_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-needs&action=edit&item_id=' . (int) $row->id . $need_pid_qs ) : $this->portal_page_url( array_filter( array( 'tab' => 'needs', 'action' => 'edit', 'item_id' => (int) $row->id, 'prospect_id' => $need_source_prospect_id ) ) ); ?><tr><td><?php echo esc_html( $need_date ); ?></td><td><?php echo esc_html( $need_status ); ?></td><td><?php echo esc_html( $need_sent_at ); ?></td><td><?php echo esc_html( $need_next_action ); ?></td><td><?php echo esc_html( $need_priority ); ?></td><td style="white-space:nowrap;"><a class="acdc-button acdc-button-soft" style="margin-right:6px;" href="<?php echo esc_url( $need_view_url ); ?>">Voir</a><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $need_edit_url ); ?>">Modifier</a></td></tr><?php endforeach; else : ?><tr><td colspan="6">Aucun recueil des besoins relié.</td></tr><?php endif; ?></tbody></table></div>
    </div>

    <div class="acdc-panel acdc-mb-18">
      <h3>Propositions commerciales</h3>
      <div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Date</th><th>Statut</th><th>Formation</th><th>Client</th><th>Dernier envoi</th><th>Visualiser</th></tr></thead><tbody><?php
      $proposal_status_labels = array(
        'brouillon' => 'Brouillon',
        'envoyee'   => 'Envoyée',
        'acceptee'  => 'Acceptée',
        'refusee'   => 'Refusée',
        'expiree'   => 'Expirée',
      );
      if ( ! empty( $proposal_rows ) ) : foreach ( $proposal_rows as $row ) :
        $prop_date      = ! empty( $row->created_at ) ? mysql2date( 'd/m/Y', $row->created_at ) : '—';
        $prop_status    = ! empty( $row->status ) && isset( $proposal_status_labels[ $row->status ] ) ? $proposal_status_labels[ $row->status ] : ( ! empty( $row->status ) ? (string) $row->status : 'Brouillon' );
        $prop_formation = ! empty( $row->formation_title ) ? (string) $row->formation_title : '—';
        $prop_client    = ! empty( $row->client_company ) ? (string) $row->client_company : '—';
        $prop_sent_at   = ! empty( $row->last_sent_at ) ? mysql2date( 'd/m/Y', $row->last_sent_at ) : '—';
        $prop_view_url  = is_admin() ? $this->admin_tab_url( 'proposals', array( 'action' => 'view', 'item_id' => (int) $row->id ) ) : $this->portal_page_url( array( 'tab' => 'proposals', 'action' => 'view', 'item_id' => (int) $row->id ) );
        $prop_pdf_url   = ! empty( $row->pdf_url ) ? (string) $row->pdf_url : '';
      ?><tr><td><?php echo esc_html( $prop_date ); ?></td><td><?php echo esc_html( $prop_status ); ?></td><td><?php echo esc_html( $prop_formation ); ?></td><td><?php echo esc_html( $prop_client ); ?></td><td><?php echo esc_html( $prop_sent_at ); ?></td><td><?php if ( $prop_pdf_url ) : ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $prop_pdf_url ); ?>" target="_blank" rel="noopener noreferrer">Visualiser</a><?php elseif ( $prop_view_url ) : ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $prop_view_url ); ?>">Voir</a><?php else : ?>—<?php endif; ?></td></tr><?php endforeach; else : ?><tr><td colspan="6">Aucune proposition commerciale reliée.</td></tr><?php endif; ?></tbody></table></div>
            <h3>Conventions / Contrats</h3>
      <div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Dates</th><th>Statut</th><th>Formation</th><th>Type</th><th>Visualiser la convention</th></tr></thead><tbody><?php if ( ! empty( $contract_rows ) ) : foreach ( $contract_rows as $row ) : $contract_date = ! empty( $row->created_at ) ? mysql2date( 'd/m/Y H:i', $row->created_at ) : '—'; $contract_status = ( ! empty( $row->signed_document_url ) || ! empty( $row->signed_document_path ) ) ? 'Signée' : ( ( ! empty( $row->document_url ) || ! empty( $row->document_path ) ) ? 'Générée' : 'À compléter' ); $contract_formation = ! empty( $row->formation_title ) ? (string) $row->formation_title : '—'; $contract_type = ! empty( $row->commanditaire_type ) ? (string) $row->commanditaire_type : '—'; $contract_view_url = ! empty( $row->id ) ? $this->get_registration_contract_download_url( $row, 'inline' ) : ''; // la méthode privilégie désormais le document signé si disponible ?><tr><td><?php echo esc_html( $contract_date ); ?></td><td><?php echo esc_html( $contract_status ); ?></td><td><?php echo esc_html( $contract_formation ); ?></td><td><?php echo esc_html( $contract_type ); ?></td><td><?php if ( $contract_view_url ) : ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $contract_view_url ); ?>" target="_blank" rel="noopener noreferrer">Visualiser</a><?php else : ?>—<?php endif; ?></td></tr><?php endforeach; else : ?><tr><td colspan="5">Aucune convention / aucun contrat relié.</td></tr><?php endif; ?></tbody></table></div>
    </div>

    <div class="acdc-panel">
      <h3>Devis</h3>
      <div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Numéro</th><th>Statut</th><th>Émis le</th><th>Expire le</th><th>Formation</th><th>Visualiser le devis</th></tr></thead><tbody><?php if ( ! empty( $quote_rows ) ) : foreach ( $quote_rows as $row ) : $quote_number = ! empty( $row->number ) ? (string) $row->number : '—'; $quote_status_labels = method_exists( $this, 'get_quote_status_labels' ) ? $this->get_quote_status_labels() : array(); $quote_status = ! empty( $row->status ) ? ( isset( $quote_status_labels[ $row->status ] ) ? $quote_status_labels[ $row->status ] : (string) $row->status ) : '—'; $quote_emission_date = ! empty( $row->emission_date ) ? mysql2date( 'd/m/Y', (string) $row->emission_date ) : '—'; $quote_expiration_date = ! empty( $row->expiration_date ) ? mysql2date( 'd/m/Y', (string) $row->expiration_date ) : '—'; $quote_formation = ! empty( $row->formation_full ) ? (string) $row->formation_full : ( ! empty( $row->formation ) ? (string) $row->formation : '—' ); $quote_view_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard&tab=quotes&scope=action&quote_action=preview&quote_id=' . (int) $row->id ) : $this->portal_page_url( array( 'tab' => 'quotes', 'scope' => 'action', 'quote_action' => 'preview', 'quote_id' => (int) $row->id ) ); ?><tr><td><?php echo esc_html( $quote_number ); ?></td><td><?php echo esc_html( $quote_status ); ?></td><td><?php echo esc_html( $quote_emission_date ); ?></td><td><?php echo esc_html( $quote_expiration_date ); ?></td><td><?php echo esc_html( $quote_formation ); ?></td><td><?php if ( ! empty( $row->id ) ) : ?><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $quote_view_url ); ?>" target="_blank" rel="noopener noreferrer">Visualiser</a><?php else : ?>—<?php endif; ?></td></tr><?php endforeach; else : ?><tr><td colspan="6">Aucun devis relié.</td></tr><?php endif; ?></tbody></table></div>
    </div>

    <div class="acdc-panel acdc-mb-18">
      <h3>E-mails envoyés</h3>
      <?php $archive_rows = $this->get_marketing_archive_for_prospect( $prospect ); ?>
      <div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Date et heure</th><th>Objet</th><th>Destinataire</th><th>Statut</th><th>Visualiser l’e-mail</th><th>Renvoyer l’e-mail</th><th>Date et heure de renvoi</th></tr></thead><tbody><?php if ( ! empty( $archive_rows ) ) : foreach ( $archive_rows as $entry ) : ?><tr><td><?php echo esc_html( ! empty( $entry['sent_at'] ) ? mysql2date( 'd/m/Y H\hi', $entry['sent_at'] ) : '—' ); ?></td><td><?php echo esc_html( ! empty( $entry['subject'] ) ? $entry['subject'] : 'Confirmation de rendez-vous' ); ?></td><td><?php echo esc_html( ! empty( $entry['to'] ) ? implode( ', ', (array) $entry['to'] ) : '—' ); ?></td><td><?php echo esc_html( ! empty( $entry['status'] ) ? $entry['status'] : '—' ); ?></td><td><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->get_archive_view_url_for_entry( $entry['id'] ) ); ?>">Visualiser</a></td><td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-flex;"><?php wp_nonce_field( 'acdc_marketing_resend_archived_email_' . $entry['id'] ); ?><input type="hidden" name="action" value="acdc_marketing_resend_archived_email"><input type="hidden" name="archive_id" value="<?php echo esc_attr( $entry['id'] ); ?>"><button type="submit" class="acdc-button acdc-button-soft">Renvoyer</button></form></td><td><?php echo esc_html( ! empty( $entry['last_resent_at'] ) ? mysql2date( 'd/m/Y H\hi', $entry['last_resent_at'] ) : '—' ); ?></td></tr><?php endforeach; else : ?><tr><td colspan="7">Aucun e-mail archivé pour ce prospect pour le moment.</td></tr><?php endif; ?></tbody></table></div>
    </div>

    <div class="acdc-modal-shell" id="acdc-prospect-rdv-modal" hidden>
      <div class="acdc-modal-backdrop" data-acdc-prospect-rdv-close></div>
      <div class="acdc-modal-dialog acdc-modal-dialog-medium">
        <div class="acdc-modal-header"><h4><?php echo $editing_rdv ? 'REPORTER LE RENDEZ-VOUS' : 'AJOUTER RDV'; ?></h4><button type="button" class="acdc-modal-close" data-acdc-prospect-rdv-close aria-label="Fermer">&times;</button></div>
        <div class="acdc-modal-body acdc-modal-body-padding">
          <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'acdc_save_prospect_rdv' ); ?>
            <input type="hidden" name="action" value="acdc_save_prospect_rdv">
            <input type="hidden" name="prospect_id" value="<?php echo (int) $prospect->id; ?>">
            <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-prospect-followup"><?php endif; ?>
            <div class="acdc-contract-grid">
              <div class="acdc-contract-label">RDV <span class="acdc-required">*</span></div>
              <div><div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;"><input type="datetime-local" name="rdv[rdv_at]" value="<?php echo esc_attr( $editing_rdv_at ); ?>" required><span><?php echo esc_html( $timezone_label ); ?></span></div></div>
            </div>
            <div class="acdc-contract-grid">
              <div class="acdc-contract-label">Compléments d’information</div>
              <div>
                <div class="acdc-mini-editor-toolbar">
                  <button type="button" class="acdc-mini-editor-button" data-editor-action="bold"><strong>B</strong></button>
                  <button type="button" class="acdc-mini-editor-button" data-editor-action="italic"><em>I</em></button>
                  <button type="button" class="acdc-mini-editor-button" data-editor-action="strike"><span style="text-decoration:line-through;">S</span></button>
                  <button type="button" class="acdc-mini-editor-button" data-editor-action="link">🔗</button>
                  <span style="flex:1 1 auto;"></span>
                  <button type="button" class="acdc-mini-editor-button" data-editor-action="undo">↶</button>
                  <button type="button" class="acdc-mini-editor-button" data-editor-action="redo">↷</button>
                </div>
                <textarea rows="5" name="rdv[info_html]" data-acdc-prospect-rdv-editor><?php echo esc_textarea( $editing_info_html ); ?></textarea>
                <p class="acdc-field-help">Ce texte sera inséré dans les e-mails de confirmation de rendez-vous et de rappel envoyés au prospect.</p>
              </div>
            </div>
            <div class="acdc-contract-grid">
              <div class="acdc-contract-label">Notifier (e-mail)</div>
              <div><p class="acdc-checkbox-line"><label class="acdc-switch"><input type="checkbox" name="rdv[notify_email]" value="1" checked><span class="acdc-switch-slider"></span></label></p><p class="acdc-field-help">En désactivant cette option, aucun mail ne sera envoyé au prospect.</p></div>
            </div>
            <div class="acdc-contract-grid">
              <div class="acdc-contract-label">Type de rendez-vous</div>
              <div>
                <select name="rdv[meeting_mode]" data-acdc-meeting-mode required>
                  <option value="">Sélectionner</option>
                  <option value="visioconference" <?php selected( $editing_mode, 'visioconference' ); ?>>Visioconférence</option>
                  <option value="telephonique" <?php selected( $editing_mode, 'telephonique' ); ?>>Téléphonique</option>
                  <option value="au_bureau" <?php selected( $editing_mode, 'au_bureau' ); ?>>Au bureau</option>
                  <option value="chez_le_client" <?php selected( $editing_mode, 'chez_le_client' ); ?>>Chez le client</option>
                </select>
                <p class="acdc-field-help">Cette information détermine le message inséré dans l’e-mail de confirmation.</p>
                <div class="acdc-meeting-link-wrap" data-acdc-meeting-link-wrap style="display:none;" hidden>
                  <input type="url" name="rdv[meeting_link]" value="<?php echo esc_attr( $editing_link ); ?>" placeholder="Lien de la visioconférence">
                  <p class="acdc-field-help">Ce lien sera inséré dans l’e-mail envoyé au prospect.</p>
                </div>
              </div>
            </div>
            <p class="acdc-actions-end">
              <button type="button" class="acdc-button acdc-button-soft" data-acdc-prospect-rdv-close>Annuler</button>
              <button type="submit" class="acdc-button acdc-button-primary">Exécuter l'action</button>
            </p>
          </form>
        </div>
      </div>
    </div>
    <style>
      .acdc-followup-alert-hero{border:1px solid rgba(221,38,39,.24);background:linear-gradient(180deg,#fff8f8 0%,#fff 100%);box-shadow:0 14px 34px rgba(221,38,39,.08);}
      .acdc-followup-alert-hero-head{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap;}
      .acdc-followup-alert-kicker{display:inline-flex;align-items:center;justify-content:center;margin-bottom:8px;padding:4px 10px;border-radius:999px;background:#dd2627;color:#fff;font-size:11px;font-weight:700;line-height:1;text-transform:uppercase;letter-spacing:.04em;}
      .acdc-followup-alert-hero h3{margin:0 0 10px;color:#1E4777;}
      .acdc-followup-alert-hero p{margin:0;color:#1E4777;}
      .acdc-followup-alert-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
      .acdc-followup-alert-hero-body{display:grid;grid-template-columns:minmax(260px,1fr) minmax(280px,1.15fr);gap:16px;margin-top:18px;}
      .acdc-followup-alert-hero-card{padding:16px 18px;border:1px solid #f0d1d1;border-radius:14px;background:#fff;}
      .acdc-followup-alert-hero-card strong{display:block;margin-bottom:8px;color:#1E4777;}
      .acdc-followup-alert-hero-card p{margin:0 0 8px;}
      .acdc-followup-alert-hero-card p:last-child{margin-bottom:0;}
      .acdc-followup-alert-hero-card ol{margin:0;padding-left:18px;color:#1E4777;}
      .acdc-followup-alert-hero-card li + li{margin-top:8px;}
      .acdc-followup-card-alert{border:1px solid rgba(221,38,39,.22);box-shadow:0 0 0 2px rgba(221,38,39,.06);}
      .acdc-followup-inline-alert-note{margin:0 0 12px;padding:10px 12px;border-radius:12px;background:#fff4f4;border:1px solid #f3cdcd;color:#8f1d1e;}
      .acdc-followup-inline-alert-note-help{background:#fff8ef;border-color:#f0dfbf;color:#7a5313;}
      .acdc-followup-alert-badge-inline{margin-left:10px;vertical-align:middle;}
      .acdc-rdv-alert-row > td{background:#fff4f4 !important;}
      .acdc-rdv-alert-row:hover > td{background:#fff1f1 !important;}
      .acdc-rdv-alert-row td:first-child{box-shadow:inset 4px 0 0 #dd2627;}
      .acdc-rdv-alert-note{margin-bottom:12px;padding:10px 12px;border-radius:12px;background:#fff8ef;border:1px solid #f0dfbf;color:#7a5313;}
      .acdc-rdv-inline-action{display:flex;flex-direction:column;align-items:flex-start;gap:8px;}
      .acdc-rdv-inline-action-help{margin:0;font-size:12px;line-height:1.4;color:#7a5313;max-width:160px;}
      .acdc-js-alert-pulse{scroll-margin-top:110px;position:relative;}
      .acdc-js-alert-pulse.is-active{animation:acdcAlertPulse 1.6s ease-in-out 2;}
      @keyframes acdcAlertPulse{0%{transform:none;box-shadow:0 0 0 0 rgba(221,38,39,.16);}50%{transform:translateY(-1px);box-shadow:0 0 0 6px rgba(221,38,39,.10);}100%{transform:none;box-shadow:0 0 0 0 rgba(221,38,39,0);}}
      .acdc-rdv-history-table{table-layout:fixed;width:100%;}
      .acdc-rdv-history-table th:nth-child(1),.acdc-rdv-history-table td:nth-child(1){width:14%;vertical-align:top;}
      .acdc-rdv-history-table th:nth-child(2),.acdc-rdv-history-table td:nth-child(2){width:38%;vertical-align:top;overflow-wrap:anywhere;word-break:break-word;}
      .acdc-rdv-history-table th:nth-child(2) a,.acdc-rdv-history-table td:nth-child(2) a{overflow-wrap:anywhere;word-break:break-word;font-size:inherit;line-height:inherit;color:#d6a353 !important;text-decoration:none;}
      .acdc-rdv-history-table th:nth-child(2) a:hover,.acdc-rdv-history-table td:nth-child(2) a:hover{color:#edcb91 !important;text-decoration:none;}
      .acdc-rdv-history-table th:nth-child(2) a:visited,.acdc-rdv-history-table td:nth-child(2) a:visited{color:#d6a353 !important;}
      .acdc-rdv-history-table th:nth-child(3),.acdc-rdv-history-table td:nth-child(3){width:32%;vertical-align:top;}
      .acdc-rdv-history-table th:nth-child(4),.acdc-rdv-history-table td:nth-child(4),.acdc-rdv-history-table th:nth-child(5),.acdc-rdv-history-table td:nth-child(5){width:8%;vertical-align:top;white-space:nowrap;}
      .acdc-rdv-post-comment-form{display:flex;flex-direction:column;gap:10px;min-width:0;width:100%;}
      .acdc-rdv-post-comment-form textarea{min-height:96px;resize:vertical;width:100%;box-sizing:border-box;}
      .acdc-rdv-post-comment-form .acdc-button{align-self:flex-start;}
      html.acdc-prospect-followup-scroll-ready,
      body.acdc-prospect-followup-scroll-ready:not(.acdc-modal-open){overflow:auto !important;height:auto !important;}
      #acdc-prospect-rdv-modal{background:transparent!important;pointer-events:auto!important;}
      #acdc-prospect-rdv-modal .acdc-modal-backdrop{position:fixed!important;inset:0!important;z-index:1!important;background:rgba(11,7,6,.45)!important;opacity:1!important;pointer-events:auto!important;cursor:pointer;}
      #acdc-prospect-rdv-modal .acdc-modal-dialog{position:relative!important;z-index:2!important;opacity:1!important;filter:none!important;background:#ffffff!important;pointer-events:auto!important;}
      #acdc-prospect-rdv-modal .acdc-modal-header,#acdc-prospect-rdv-modal .acdc-modal-body{opacity:1!important;background:#ffffff!important;pointer-events:auto!important;}
      #acdc-prospect-rdv-modal .acdc-modal-close,#acdc-prospect-rdv-modal [data-acdc-prospect-rdv-close]{pointer-events:auto!important;cursor:pointer;}
      #acdc-prospect-rdv-modal .acdc-modal-close *,#acdc-prospect-rdv-modal [data-acdc-prospect-rdv-close] *{pointer-events:none;}
    </style>
    <script>
      (function(){
        var modal=document.getElementById('acdc-prospect-rdv-modal');
        if(!modal){return;}
        if(modal.getAttribute('data-acdc-rdv-modal-bound')==='1'){return;}
        modal.setAttribute('data-acdc-rdv-modal-bound','1');
        function openModal(){
          modal.hidden=false;
          document.body.classList.remove('acdc-prospect-followup-scroll-ready');
          document.documentElement.classList.remove('acdc-prospect-followup-scroll-ready');
          document.body.style.setProperty('overflow','hidden','important');
          document.documentElement.style.setProperty('overflow','hidden','important');
          document.body.classList.add('acdc-modal-open');
        }
        function closeModal(){
          modal.hidden=true;
          document.body.classList.remove('acdc-modal-open');
          document.body.classList.add('acdc-prospect-followup-scroll-ready');
          document.documentElement.classList.add('acdc-prospect-followup-scroll-ready');
          document.body.style.setProperty('overflow','auto','important');
          document.documentElement.style.setProperty('overflow','auto','important');
        }
        window.acdcOpenProspectRdvModal = function(trigger){
          openModal();
        };
        document.addEventListener('click', function(e){
          var target = e.target;
          if(!target || !target.closest){ return; }
          var opener = target.closest('[data-acdc-prospect-rdv-open]');
          if(opener){
            e.preventDefault();
            e.stopPropagation();
            openModal();
            return;
          }
          if(modal.hidden){ return; }
          var closer = target.closest('[data-acdc-prospect-rdv-close]');
          if(closer && modal.contains(closer)){
            e.preventDefault();
            e.stopPropagation();
            closeModal();
            return;
          }
          if(target === modal){
            e.preventDefault();
            closeModal();
          }
        }, true);
        document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && !modal.hidden){ closeModal(); } });
        if(window.location.search.indexOf('open_rdv=1')!==-1){ openModal(); }
        window.setTimeout(function(){
          document.querySelectorAll('.acdc-js-alert-pulse').forEach(function(node){
            node.classList.add('is-active');
          });
        }, 180);
        var editor=modal.querySelector('[data-acdc-prospect-rdv-editor]');
        var meetingMode=modal.querySelector('select[name="rdv[meeting_mode]"]');
        var meetingLinkWrap=modal.querySelector('[data-acdc-meeting-link-wrap]');
        var meetingLinkInput=modal.querySelector('input[name="rdv[meeting_link]"]');
        function pruneLegacyRdvFields(scope){
          if(!scope){ return; }
          scope.querySelectorAll('.acdc-contract-grid').forEach(function(grid){
            var label = grid.querySelector('.acdc-contract-label');
            var txt = label ? (label.textContent || '').trim().toLowerCase() : '';
            var hasLegacyTextarea = !!grid.querySelector('textarea[name="rdv[comment_text]"], textarea[placeholder*="Résumé du rendez-vous"], textarea[placeholder*="resume du rendez-vous"]');
            var hasLegacyHelp = !!Array.from(grid.querySelectorAll('.acdc-field-help, p, div')).find(function(node){
              var content = (node.textContent || '').toLowerCase();
              return content.indexOf('ce commentaire sert de résumé du rendez-vous') > -1;
            });
            if(txt.indexOf('commentaire / résumé du rendez-vous') > -1 || txt.indexOf('commentaire / resume du rendez-vous') > -1 || hasLegacyTextarea || hasLegacyHelp){
              grid.remove();
            }
          });
        }
        function syncMeetingMode(){
          pruneLegacyRdvFields(modal);
          meetingMode = modal.querySelector('select[name="rdv[meeting_mode]"]');
          meetingLinkWrap = modal.querySelector('[data-acdc-meeting-link-wrap]');
          meetingLinkInput = modal.querySelector('input[name="rdv[meeting_link]"]');
          if(!meetingMode || !meetingLinkWrap || !meetingLinkInput){ return; }
          var isVisio = meetingMode.value === 'visioconference';
          meetingLinkWrap.hidden = !isVisio;
          meetingLinkWrap.style.display = isVisio ? 'block' : 'none';
          meetingLinkInput.required = isVisio;
          if(!isVisio){ meetingLinkInput.value = ''; }
        }
        pruneLegacyRdvFields(modal);
        if(meetingMode){
          meetingMode.addEventListener('change', syncMeetingMode);
          syncMeetingMode();
        }
        function wrapSelection(before, after){ if(!editor){return;} var start=editor.selectionStart||0,end=editor.selectionEnd||0,value=editor.value||''; var selected=value.substring(start,end); var replacement=before + selected + after; editor.value=value.substring(0,start) + replacement + value.substring(end); editor.focus(); editor.setSelectionRange(start + before.length, start + replacement.length - after.length); }
        modal.querySelectorAll('[data-editor-action]').forEach(function(btn){ btn.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); var action=btn.getAttribute('data-editor-action'); if(action==='bold'){ wrapSelection('<strong>','</strong>'); } else if(action==='italic'){ wrapSelection('<em>','</em>'); } else if(action==='strike'){ wrapSelection('<span style="text-decoration:line-through;">','</span>'); } else if(action==='link'){ var url=window.prompt('URL du lien'); if(url){ wrapSelection('<a href="'+url.replace(/"/g,'')+'">','</a>'); } } else if(action==='undo'){ document.execCommand('undo'); editor.focus(); } else if(action==='redo'){ document.execCommand('redo'); editor.focus(); } }); });
      })();
    </script>
    <?php
  }


  public function print_prospect_ui_patch_assets() {
    static $printed = false;
    if ( $printed || ! $this->is_prospect_ui_context() ) {
      return;
    }
    $printed = true;
    ?>
    <style id="acdc-prospect-ui-patch">
      .acdc-table-prospects,.acdc-table-prospects tbody,.acdc-table-prospects tr,.acdc-table-prospects td{overflow:visible !important;}
      .acdc-prospect-actions{display:flex;align-items:center;gap:var(--acdc-action-icon-gap, 12px);white-space:nowrap;position:relative;}
      /* hotfix22 — acdc-prospect-icon-link remplacé par acdc-row-action-icon standard */
      .acdc-prospect-action-trigger svg[data-fill="1"]{fill:currentColor;stroke:none;}
      .acdc-prospect-action-menu{position:relative;display:inline-flex;align-items:center;}
      .acdc-prospect-action-dropdown{position:absolute;top:calc(100% + 10px);left:0;min-width:260px;background:#fff;border:1px solid #dce4ec;border-radius:10px;box-shadow:0 10px 30px rgba(28,44,64,.12);padding:14px 0;z-index:999999;}
      .acdc-prospect-action-item{display:block;padding:12px 28px;color:#1E4777 !important;text-decoration:none !important;font-size:16px;line-height:1.35;white-space:nowrap;}
      .acdc-prospect-action-item:hover{background:#F6F8FB;color:#1e4777 !important;}
      .acdc-table-prospects td:last-child > a:not(.acdc-row-action-icon){display:none !important;}
      .acdc-table-prospects td:last-child{white-space:nowrap;}
      .acdc-meeting-link-wrap{margin-top:12px;}
      .acdc-meeting-link-wrap[hidden]{display:none !important;}
      #acdc-prospect-rdv-modal input[name="rdv[meeting_link]"]{width:100%;}
    </style>
    <script id="acdc-prospect-ui-patch-script">
      (function(){
        var COLOR = '#e9c77c';
        function escHtml(value){ return String(value || '').replace(/[&<>"']/g, function(s){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]); }); }
        /* ACDC 3.20.61 — table de correspondance des types Prospects vers
         * les types AcdcActionHub utilisés par le back office UI.
         * Identique à celle du premier script. Le but est unique : faire
         * que tout SVG injecté ici reflète le choix du back office, avec
         * fallback intégral sur les SVG en dur historiques. */
        var PROSPECT_TYPE_TO_HUB = {
          view: 'view',
          edit: 'edit',
          clipboard: 'followup',
          trash: 'delete',
          more: 'more'
        };
        function configuredSvg(prospectType){
          var hubType = PROSPECT_TYPE_TO_HUB[prospectType];
          if(!hubType){ return null; }
          var cfg = (typeof window !== 'undefined') ? window.ACDC_ACTION_HUB_CONFIG : null;
          if(!cfg || !cfg.icons || !cfg.glyphs){ return null; }
          var glyphName = cfg.icons[hubType];
          if(!glyphName){ return null; }
          var svg = cfg.glyphs[glyphName];
          if(!svg){ return null; }
          return svg;
        }
        function icon(type){
          var fromConfig = configuredSvg(type);
          if(fromConfig){ return fromConfig; }
          if(type==='more'){ return '<svg data-fill="1" viewBox="0 0 24 24" width="18" height="18"><circle cx="5" cy="12" r="1.8"></circle><circle cx="12" cy="12" r="1.8"></circle><circle cx="19" cy="12" r="1.8"></circle></svg>'; }
          if(type==='view'){ return '<svg viewBox="0 0 24 24" width="18" height="18"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"></path><circle cx="12" cy="12" r="3" stroke-width="1.9"></circle></svg>'; }
          if(type==='edit'){ return '<svg viewBox="0 0 24 24" width="18" height="18"><path d="M12 20h9" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"></path><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"></path></svg>'; }
          if(type==='phone'){ return '<svg viewBox="0 0 24 24" width="18" height="18"><path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.8 19.8 0 0 1 3.08 5.18 2 2 0 0 1 5.05 3h3a2 2 0 0 1 2 1.72l.35 2.47a2 2 0 0 1-.57 1.71L8.1 10.63a16 16 0 0 0 5.27 5.27l1.73-1.73a2 2 0 0 1 1.71-.57l2.47.35A2 2 0 0 1 22 16.92z" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"></path></svg>'; }
          if(type==='clipboard'){ return '<svg viewBox="0 0 24 24" width="18" height="18"><rect x="9" y="3" width="6" height="4" rx="1" ry="1" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"></rect><path d="M9 5H7a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"></path><path d="M9 12h6" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"></path><path d="M9 16h4" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"></path></svg>'; }
          if(type==='trash'){ return '<svg viewBox="0 0 24 24" width="18" height="18"><path d="M3 6h18" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"></path><path d="M8 6V4h8v2" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"></path><path d="M19 6l-1 14H6L5 6" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"></path><path d="M10 11v6M14 11v6" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"></path></svg>'; }
          return '';
        }
        function getItemIdFromUrl(url){
          try { var u = new URL(url, window.location.origin); return u.searchParams.get('item_id') || u.searchParams.get('prospect_id') || ''; } catch(e){ return ''; }
        }
        function appendParam(url, key, value){
          try { var u = new URL(url, window.location.origin); u.searchParams.set(key, value); return u.toString(); } catch(e){ return url + (url.indexOf('?')>-1 ? '&' : '?') + key + '=' + encodeURIComponent(value); }
        }
        function replaceAction(url, value){
          try { var u = new URL(url, window.location.origin); u.searchParams.set('action', value); return u.toString(); } catch(e){ return url; }
        }
        function setFrontTabUrl(url, tab, params){
          try {
            var u = new URL(url || window.location.href, window.location.origin);
            u.searchParams.set('tab', tab);
            ['action','item_id','focus_field','focus_section','open_rdv','duplicate_id','prospect_id'].forEach(function(key){ u.searchParams.delete(key); });
            Object.keys(params || {}).forEach(function(key){ u.searchParams.set(key, params[key]); });
            return u.toString();
          } catch(e) {
            return '#';
          }
        }
        function buildMenuUrls(mapping){
          var itemId = getItemIdFromUrl(mapping.view || mapping.edit || mapping.follow || mapping.delete || '');
          var isAdmin = window.location.href.indexOf('/wp-admin/') !== -1;
          var root = window.location.origin;
          var adminBase = root + '/wp-admin/admin.php';
          var followBase = mapping.follow || (adminBase + '?page=acdc-of-prospect-followup&action=view&item_id=' + encodeURIComponent(itemId));
          var editBase = mapping.edit || (isAdmin ? adminBase + '?page=acdc-of-prospects&action=edit&item_id=' + encodeURIComponent(itemId) : window.location.href);
          if ( isAdmin ) {
            return [
              ['Répliquer', replaceAction(editBase, 'new')],
              ['Ajouter un rendez-vous', appendParam(followBase, 'open_rdv', '1')],
              ['Recueil des besoins', adminBase + '?page=acdc-of-needs&action=new&prospect_id=' + encodeURIComponent(itemId)]
            ];
          }
          return [
            ['Répliquer', setFrontTabUrl(editBase, 'prospects', { action:'new', duplicate_id:itemId })],
            ['Ajouter un rendez-vous', setFrontTabUrl(followBase, 'prospect_followup', { action:'view', item_id:itemId, open_rdv:'1' })],
            ['Recueil des besoins', setFrontTabUrl(editBase, 'needs', { action:'new', prospect_id:itemId })]
          ];
        }
        function hideAllMenus(except){
          document.querySelectorAll('.acdc-prospect-action-dropdown').forEach(function(menu){
            if(menu!==except){
              menu.hidden = true;
              if(menu._acdcOriginalParent && menu.parentNode !== menu._acdcOriginalParent){
                if(menu._acdcOriginalNext && menu._acdcOriginalNext.parentNode === menu._acdcOriginalParent){
                  menu._acdcOriginalParent.insertBefore(menu, menu._acdcOriginalNext);
                } else {
                  menu._acdcOriginalParent.appendChild(menu);
                }
              }
            }
          });
          document.querySelectorAll('[data-acdc-prospect-menu-toggle]').forEach(function(btn){ if(!except || !btn.parentNode || btn.parentNode.querySelector('.acdc-prospect-action-dropdown') !== except){ btn.setAttribute('aria-expanded', 'false'); } });
        }
        function bindMenu(button, menu){
          if(!button || !menu || button.dataset.bound==='1'){ return; }
          button.dataset.bound='1';
          if(!menu._acdcOriginalParent){
            menu._acdcOriginalParent = menu.parentNode;
            menu._acdcOriginalNext = menu.nextSibling;
          }
          function ensureHost(){
            if(menu.parentNode !== document.body){ document.body.appendChild(menu); }
            menu._acdcTriggerButton = button;
          }
          function restoreMenu(){
            if(!menu._acdcOriginalParent || menu.parentNode === menu._acdcOriginalParent){ return; }
            if(menu._acdcOriginalNext && menu._acdcOriginalNext.parentNode === menu._acdcOriginalParent){
              menu._acdcOriginalParent.insertBefore(menu, menu._acdcOriginalNext);
            } else {
              menu._acdcOriginalParent.appendChild(menu);
            }
          }
          function placeMenu(){
            ensureHost();
            var rect = button.getBoundingClientRect();
            var left = rect.left;
            var top = rect.bottom + 10;
            menu.style.position = 'fixed';
            menu.style.left = left + 'px';
            menu.style.top = top + 'px';
            menu.style.right = 'auto';
            menu.style.bottom = 'auto';
            requestAnimationFrame(function(){
              var mr = menu.getBoundingClientRect();
              var maxLeft = Math.max(12, window.innerWidth - mr.width - 12);
              if(left > maxLeft){ menu.style.left = maxLeft + 'px'; }
              if(mr.bottom > window.innerHeight - 12){
                menu.style.top = Math.max(12, rect.top - mr.height - 10) + 'px';
              }
            });
          }
          button.addEventListener('click', function(ev){
            ev.preventDefault();
            ev.stopPropagation();
            var willOpen = !!menu.hidden;
            hideAllMenus();
            if(willOpen){
              menu.hidden = false;
              button.setAttribute('aria-expanded', 'true');
              placeMenu();
            } else {
              menu.hidden = true;
              button.setAttribute('aria-expanded', 'false');
              restoreMenu();
            }
          });
          menu.querySelectorAll('a').forEach(function(link){ link.addEventListener('click', function(ev){
            var inlineAction = (link.getAttribute('data-acdc-inline-action') || '').trim().toLowerCase();
            if(inlineAction === 'attribuer à' || inlineAction === 'statut dossier' || inlineAction === 'relancer'){
              return;
            }
            hideAllMenus();
            restoreMenu();
          }); });
          window.addEventListener('scroll', function(){ if(!menu.hidden){ placeMenu(); } }, true);
          window.addEventListener('resize', function(){ if(!menu.hidden){ placeMenu(); } });
        }
        function buildActionCell(cell){
          if(!cell){ return; }
          var links = Array.prototype.slice.call(cell.querySelectorAll('a'));
          if(!links.length){ return; }
          var mapping = {};
          links.forEach(function(link){
            var label = (link.textContent || '').trim().toLowerCase();
            if(label === 'voir'){ mapping.view = link.href; }
            else if(label === 'modifier'){ mapping.edit = link.href; }
            else if(label === 'suivi' || label === 'suivi commercial'){ mapping.follow = link.href; }
            else if(label === 'supprimer'){ mapping.delete = link.href; }
          });
          if(!(mapping.view || mapping.edit || mapping.follow || mapping.delete)){ return; }
          cell.innerHTML = '';
          var wrap = document.createElement('div');
          wrap.className = 'acdc-prospect-actions';
          wrap.setAttribute('data-acdc-prospect-actions','1');
          var menuWrap = document.createElement('div');
          menuWrap.className = 'acdc-prospect-action-menu';
          var trigger = document.createElement('button');
          trigger.type = 'button';
          trigger.className = 'acdc-prospect-action-trigger';
          trigger.setAttribute('aria-label', 'Actions complémentaires');
          trigger.setAttribute('aria-haspopup', 'true');
          trigger.setAttribute('aria-expanded', 'false');
          trigger.setAttribute('data-acdc-prospect-menu-toggle','1');
          trigger.innerHTML = icon('more');
          var menu = document.createElement('div');
          menu.className = 'acdc-prospect-action-dropdown';
          menu.hidden = true;
          var row = cell.closest('tr');
          if(row){
            var idCellLinks = row.querySelectorAll('a[href*="item_id="], a[href*="prospect_id="]');
            for(var idx=0; idx<idCellLinks.length; idx++){
              try {
                var parsed = new URL(idCellLinks[idx].href, window.location.origin);
                var rowId = parsed.searchParams.get('item_id') || parsed.searchParams.get('prospect_id');
                if(rowId){ menu.setAttribute('data-acdc-prospect-id', rowId); break; }
              } catch(err){}
            }
          }
          buildMenuUrls(mapping).forEach(function(item){
            var a = document.createElement('a');
            var normalized = (item[0] || '').trim().toLowerCase();
            a.className = 'acdc-prospect-action-item';
            a.href = item[1];
            a.textContent = item[0];
            if(normalized === 'attribuer à' || normalized === 'statut dossier' || normalized === 'relancer'){
              a.setAttribute('data-acdc-inline-action', normalized);
              a.setAttribute('role', 'button');
              a.setAttribute('href', 'javascript:void(0)');
              a.addEventListener('click', function(ev){
                ev.preventDefault();
                ev.stopPropagation();
                if (typeof ev.stopImmediatePropagation === 'function') { ev.stopImmediatePropagation(); }
                if(normalized === 'attribuer à'){
                  showInlineSelect(a, 'assigned_to');
                  return false;
                }
                if(normalized === 'statut dossier'){
                  showInlineSelect(a, 'status');
                  return false;
                }
                closeMenuForLink(a);
                openQuickRdvModal(a);
                return false;
              }, true);
              a.addEventListener('mousedown', function(ev){
                ev.preventDefault();
                ev.stopPropagation();
                if (typeof ev.stopImmediatePropagation === 'function') { ev.stopImmediatePropagation(); }
              }, true);
            }
            menu.appendChild(a);
          });
          menuWrap.appendChild(trigger);
          menuWrap.appendChild(menu);
          wrap.appendChild(menuWrap);
          [ ['view','Voir','view'], ['edit','Modifier','edit'], ['follow','Suivi commercial','clipboard'], ['delete','Supprimer','trash'] ].forEach(function(def){
            if(!mapping[def[0]]){ return; }
            var a = document.createElement('a');
            var subMap={'view':'acdc-row-view-link','edit':'acdc-row-edit-link','delete':'acdc-row-delete-link','follow':''};
            a.href = mapping[def[0]];
            a.className = 'acdc-row-action-icon'+(subMap[def[0]]?' '+subMap[def[0]]:'');
            a.setAttribute('data-acdc-iconized','1');
            a.setAttribute('title', def[1]);
            a.setAttribute('aria-label', def[1]);
            if(def[0] === 'delete'){ a.setAttribute('onclick', "return confirm('Supprimer ce prospect ?');"); }
            a.innerHTML = icon(def[2])+'<span class="acdc-action-hub-sr screen-reader-text">'+def[1]+'</span>';
            wrap.appendChild(a);
          });
          cell.appendChild(wrap);
          bindMenu(trigger, menu);
        }
        function patchProspectsTables(){
          document.querySelectorAll('.acdc-table-prospects tbody tr').forEach(function(row){
            var cell = row.querySelector('td:last-child');
            if(!cell){ return; }
            if(cell.querySelector('.acdc-prospect-actions')){
              var btn = cell.querySelector('[data-acdc-prospect-menu-toggle]');
              var menu = cell.querySelector('.acdc-prospect-action-dropdown');
              bindMenu(btn, menu);
              return;
            }
            buildActionCell(cell);
          });
        }
        function patchRdvModal(){
          return;
        }
        function init(){ patchProspectsTables(); patchRdvModal(); }
        if(document.readyState === 'loading'){ document.addEventListener('DOMContentLoaded', init); } else { init(); }
        document.addEventListener('click', function(ev){ if(!ev.target.closest('.acdc-prospect-action-menu')){ hideAllMenus(); } });
        document.addEventListener('keydown', function(ev){ if(ev.key === 'Escape'){ hideAllMenus(); } });
        var observer = new MutationObserver(function(){ patchProspectsTables(); patchRdvModal(); });
        observer.observe(document.documentElement || document.body, {childList:true, subtree:true});
      })();
    </script>
    <style id="acdc-prospect-inline-updates-style">
      .acdc-prospect-inline-select-wrap{padding:8px 18px 12px 18px;border-top:1px solid #edf2f7;}
      .acdc-prospect-inline-select{width:100%;height:40px;border:1px solid var(--acdc-border);border-radius:10px;padding:0 12px;color:var(--acdc-text);background:#fff;}
      .acdc-prospect-inline-feedback{display:block;margin-top:8px;font-size:12px;color:#1E4777;}
      .acdc-prospect-inline-feedback.is-error{color:#b42318;}
      .acdc-prospect-inline-loading{opacity:.65;pointer-events:none;}
      .acdc-prospect-quick-rdv .acdc-meeting-link-wrap{margin-top:12px;}
      .acdc-prospect-quick-rdv .acdc-meeting-link-wrap[hidden]{display:none !important;}
      .acdc-prospect-quick-rdv input[name="rdv[meeting_link]"]{width:100%;}
    </style>
    <script id="acdc-prospect-inline-updates-script">
      (function(){
        var cfg = {
          updateUrl: <?php echo wp_json_encode( admin_url( 'admin-post.php' ) ); ?>,
          updateNonce: <?php echo wp_json_encode( wp_create_nonce( 'acdc_quick_update_prospect_field' ) ); ?>,
          rdvAction: <?php echo wp_json_encode( admin_url( 'admin-post.php' ) ); ?>,
          rdvNonce: <?php echo wp_json_encode( wp_create_nonce( 'acdc_save_prospect_rdv' ) ); ?>,
          assignments: <?php echo wp_json_encode( $this->get_assignment_options() ); ?>,
          statuses: <?php echo wp_json_encode( $this->get_prospect_status_options() ); ?>,
          timezone: <?php echo wp_json_encode( wp_timezone_string() ? wp_timezone_string() : 'Europe/Paris' ); ?>,
          isAdmin: <?php echo is_admin() ? 'true' : 'false'; ?>
        };

        function getProspectRow(el){
          if(!el){ return null; }
          var directRow = el.closest('tr');
          if(directRow){ return directRow; }
          var menu = el.closest('.acdc-prospect-action-dropdown, .acdc-prospect-patch-menu');
          if(menu && menu._acdcTriggerButton){
            return menu._acdcTriggerButton.closest('tr');
          }
          return null;
        }
        function getProspectIdFromRow(row, sourceEl){
          // L'élément cliqué (ex. lien « Ajouter un rendez-vous ») porte déjà
          // item_id / prospect_id dans son propre href. On le lit en priorité :
          // le menu déroulant est repositionné dans <body> à l'ouverture, ce qui
          // détache le lien de sa ligne et empêchait la détection du prospect.
          if(sourceEl && sourceEl.getAttribute){
            var selfHref = sourceEl.getAttribute('href') || '';
            if(selfHref && selfHref.indexOf('javascript:') !== 0){
              try {
                var su = new URL(selfHref, window.location.origin);
                var selfId = su.searchParams.get('item_id') || su.searchParams.get('prospect_id');
                if(selfId){ return selfId; }
              } catch(e){}
            }
          }
          var actionLinks = row ? row.querySelectorAll('.acdc-prospect-actions a, .acdc-prospect-action-dropdown a') : [];
          for(var i=0;i<actionLinks.length;i++){
            try {
              var u = new URL(actionLinks[i].href, window.location.origin);
              var id = u.searchParams.get('item_id') || u.searchParams.get('prospect_id');
              if(id){ return id; }
            } catch(e){}
          }
          if(sourceEl){
            var menu = sourceEl.closest('.acdc-prospect-action-dropdown, .acdc-prospect-patch-menu');
            if(menu){
              var fallbackId = menu.getAttribute('data-acdc-prospect-id');
              if(fallbackId){ return fallbackId; }
            }
          }
          return '';
        }
        function getCell(row, index){ return row && row.children && row.children[index] ? row.children[index] : null; }
        function setCellText(row, index, value){ var cell = getCell(row,index); if(cell){ cell.textContent = value; } }
        function hardCloseProspectMenus(){
          document.querySelectorAll('.acdc-prospect-action-dropdown, .acdc-prospect-patch-menu, .acdc-row-menu-dropdown-floating, .acdc-table-action-menu').forEach(function(menu){
            menu.hidden = true;
            menu.setAttribute('aria-hidden', 'true');
            menu.style.display = 'none';
          });
          document.querySelectorAll('[data-acdc-prospect-menu-toggle], .acdc-prospect-patch-trigger, [data-acdc-row-menu-toggle], .acdc-row-menu-toggle, .acdc-row-menu-button, [data-acdc-bpf-row-menu-toggle], .acdc-bpf-action-button, .acdc-table-action-trigger').forEach(function(btn){
            btn.setAttribute('aria-expanded','false');
          });
          document.querySelectorAll('.acdc-row-actions-menu-cell.is-open').forEach(function(cell){
            cell.classList.remove('is-open');
          });
        }
        function closeMenuForLink(link){
          var menu = link ? link.closest('.acdc-prospect-action-dropdown, .acdc-prospect-patch-menu, .acdc-row-menu-dropdown-floating, .acdc-table-action-menu') : null;
          hardCloseProspectMenus();
          var trigger = null;
          if(menu && menu._acdcTriggerButton){
            trigger = menu._acdcTriggerButton;
          }
          var wrap = link ? link.closest('[data-acdc-prospect-actions], .acdc-prospect-patch-actions') : null;
          if(!trigger && wrap){
            trigger = wrap.querySelector('[data-acdc-prospect-menu-toggle], .acdc-prospect-patch-trigger');
          }
          if(menu && !trigger){
            trigger = menu._acdcProspectTrigger || menu._acdcFloatingTrigger || null;
          }
          if(trigger){ trigger.setAttribute('aria-expanded','false'); }
        }
        function removeInlineBlocks(menu){
          if(!menu){ return; }
          menu.querySelectorAll('.acdc-prospect-inline-select-wrap').forEach(function(node){ node.remove(); });
        }
        function showInlineSelect(link, field){
          var menu = link.closest('.acdc-prospect-action-dropdown, .acdc-prospect-patch-menu');
          var row = getProspectRow(link);
          var prospectId = getProspectIdFromRow(row, link);
          if(!menu || !prospectId){ return; }
          removeInlineBlocks(menu);
          var options = field === 'assigned_to' ? cfg.assignments : cfg.statuses;
          var currentCell = field === 'assigned_to' ? getCell(row, 6) : getCell(row, 7);
          var currentText = currentCell ? (currentCell.textContent || '').trim() : '';
          var wrap = document.createElement('div');
          wrap.className = 'acdc-prospect-inline-select-wrap';
          var select = document.createElement('select');
          select.className = 'acdc-prospect-inline-select';
          Object.keys(options).forEach(function(key){
            var opt = document.createElement('option');
            var label = options[key] || key;
            opt.value = key;
            opt.textContent = label;
            if((field === 'assigned_to' && ((currentText === '—' && key === '') || currentText === key || currentText === label)) || (field === 'status' && (currentText === key || currentText === label))){
              opt.selected = true;
            }
            select.appendChild(opt);
          });
          var feedback = document.createElement('small');
          feedback.className = 'acdc-prospect-inline-feedback';
          feedback.textContent = field === 'assigned_to' ? 'Sélectionnez un responsable.' : 'Sélectionnez un statut.';
          wrap.appendChild(select);
          wrap.appendChild(feedback);
          menu.appendChild(wrap);
          select.focus();
          select.addEventListener('change', function(){
            wrap.classList.add('acdc-prospect-inline-loading');
            feedback.classList.remove('is-error');
            feedback.textContent = 'Mise à jour en cours…';
            var body = new URLSearchParams();
            body.set('action', 'acdc_quick_update_prospect_field');
            body.set('_wpnonce', cfg.updateNonce);
            body.set('prospect_id', prospectId);
            body.set('field', field);
            body.set('value', select.value);
            fetch(cfg.updateUrl, {
              method:'POST',
              body:body.toString(),
              credentials:'same-origin',
              headers:{
                'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With':'XMLHttpRequest'
              }
            })
              .then(function(r){ return r.text(); })
              .then(function(raw){
                var data = null;
                try {
                  data = raw ? JSON.parse(raw) : null;
                } catch(parseError) {
                  throw new Error('La mise à jour n’a pas renvoyé de réponse exploitable.');
                }
                if(!data || !data.success){ throw new Error(data && data.data && data.data.message ? data.data.message : 'Mise à jour impossible.'); }
                setCellText(row, field === 'assigned_to' ? 6 : 7, data.data && data.data.label ? data.data.label : (select.options[select.selectedIndex] ? select.options[select.selectedIndex].textContent : (select.value || '—')));
                feedback.textContent = 'Mise à jour enregistrée.';
                setTimeout(function(){ removeInlineBlocks(menu); closeMenuForLink(link); }, 500);
              })
              .catch(function(err){
                feedback.textContent = err && err.message ? err.message : 'Erreur lors de la mise à jour.';
                feedback.classList.add('is-error');
              })
              .finally(function(){ wrap.classList.remove('acdc-prospect-inline-loading'); });
          });
        }
        function getProspectListRdvModal(){
          return document.getElementById('acdc-prospect-list-rdv-modal');
        }
        function syncQuickRdvModalState(modal, isOpen){
          if(!modal){ return; }
          var backdrop = modal.querySelector('.acdc-modal-backdrop');
          var dialog = modal.querySelector('.acdc-modal-dialog');
          modal.hidden = !isOpen;
          if(isOpen){
            modal.removeAttribute('hidden');
          } else {
            modal.setAttribute('hidden', 'hidden');
          }
          modal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
          modal.style.display = isOpen ? 'block' : 'none';
          modal.style.visibility = isOpen ? 'visible' : 'hidden';
          modal.style.pointerEvents = isOpen ? 'auto' : 'none';
          modal.style.zIndex = isOpen ? '' : '-1';
          [backdrop, dialog].forEach(function(node){
            if(!node){ return; }
            node.hidden = !isOpen;
            if(isOpen){
              node.removeAttribute('hidden');
            } else {
              node.setAttribute('hidden', 'hidden');
            }
            node.style.display = isOpen ? '' : 'none';
            node.style.visibility = isOpen ? 'visible' : 'hidden';
            node.style.pointerEvents = isOpen ? 'auto' : 'none';
          });
          document.body.classList.toggle('acdc-modal-open', !!isOpen);
          document.documentElement.classList.toggle('acdc-modal-open', !!isOpen);
          if(isOpen){
            document.body.style.setProperty('overflow','hidden','important');
            document.documentElement.style.setProperty('overflow','hidden','important');
          } else {
            document.body.style.removeProperty('overflow');
            document.documentElement.style.removeProperty('overflow');
          }
        }
        function closeQuickRdvModal(modal){
          if(!modal){ return; }
          syncQuickRdvModalState(modal, false);
          if(window.AcdcUiKernel && typeof window.AcdcUiKernel.closeFloatingUi === 'function'){
            window.AcdcUiKernel.closeFloatingUi({ exceptTrigger: null, exceptMenu: null });
          }
          hardCloseProspectMenus();
          window.setTimeout(function(){
            syncQuickRdvModalState(modal, false);
            hardCloseProspectMenus();
          }, 0);
          window.setTimeout(function(){ syncQuickRdvModalState(modal, false); }, 120);
          if(typeof requestAnimationFrame === 'function'){
            requestAnimationFrame(function(){
              syncQuickRdvModalState(modal, false);
              hardCloseProspectMenus();
            });
          }
          if(document.activeElement && typeof document.activeElement.blur === 'function'){
            document.activeElement.blur();
          }
          try {
            document.dispatchEvent(new CustomEvent('acdc:prospect-rdv-modal-closed', { detail: { modalId: modal.id || '' } }));
          } catch(err){}
        }
        function syncProspectListMeetingMode(modal){
          if(!modal){ return; }
          var modeSelect = modal.querySelector('select[data-acdc-prospect-list-meeting-mode]');
          var linkWrap = modal.querySelector('[data-acdc-prospect-list-meeting-link-wrap]');
          var linkInput = modal.querySelector('input[name="rdv[meeting_link]"]');
          if(!modeSelect || !linkWrap || !linkInput){ return; }
          var isVisio = modeSelect.value === 'visioconference';
          linkWrap.hidden = !isVisio;
          linkWrap.style.display = isVisio ? 'block' : 'none';
          linkInput.required = isVisio;
          if(!isVisio){ linkInput.value = ''; }
        }
        function initProspectListRdvModal(){
          var modal = getProspectListRdvModal();
          if(!modal || modal._acdcReady){ return modal; }
          modal._acdcReady = true;
          modal.querySelectorAll('[data-acdc-prospect-list-rdv-close]').forEach(function(btn){
            btn.addEventListener('click', function(ev){ ev.preventDefault(); ev.stopPropagation(); closeQuickRdvModal(modal); });
          });
          var backdrop = modal.querySelector('.acdc-modal-backdrop');
          if(backdrop){
            backdrop.addEventListener('click', function(ev){ ev.preventDefault(); ev.stopPropagation(); closeQuickRdvModal(modal); });
          }
          document.addEventListener('pointerdown', function(){
            if(modal.hidden || modal.getAttribute('aria-hidden') === 'true'){
              syncQuickRdvModalState(modal, false);
            }
          }, true);
          document.addEventListener('keydown', function(ev){ if(ev.key === 'Escape' && !modal.hidden){ closeQuickRdvModal(modal); } });
          var modeSelect = modal.querySelector('select[data-acdc-prospect-list-meeting-mode]');
          if(modeSelect){ modeSelect.addEventListener('change', function(){ syncProspectListMeetingMode(modal); }); }
          syncProspectListMeetingMode(modal);
          var editor = modal.querySelector('[data-acdc-prospect-list-rdv-editor]');
          function wrapSelection(before, after){ if(!editor){return;} var start=editor.selectionStart||0,end=editor.selectionEnd||0,value=editor.value||''; var selected=value.substring(start,end); var replacement=before + selected + after; editor.value=value.substring(0,start) + replacement + value.substring(end); editor.focus(); editor.setSelectionRange(start + before.length, start + replacement.length - after.length); }
          modal.querySelectorAll('[data-editor-action]').forEach(function(btn){ btn.addEventListener('click', function(){ var action=btn.getAttribute('data-editor-action'); if(action==='bold'){ wrapSelection('<strong>','</strong>'); } else if(action==='italic'){ wrapSelection('<em>','</em>'); } else if(action==='strike'){ wrapSelection('<span style="text-decoration:line-through;">','</span>'); } else if(action==='link'){ var url=window.prompt('URL du lien'); if(url){ wrapSelection('<a href="'+url.replace(/"/g,'')+'">','</a>'); } } else if(action==='undo'){ document.execCommand('undo'); if(editor){ editor.focus(); } } else if(action==='redo'){ document.execCommand('redo'); if(editor){ editor.focus(); } } }); });
          return modal;
        }
        function openQuickRdvModal(link, forcedProspectId){
          var modal = initProspectListRdvModal();
          if(!modal){ return; }
          var form = modal.querySelector('form');
          if(!form){ return; }
          form.reset();
          var prospectId = forcedProspectId || '';
          if(!prospectId && link){
            var row = getProspectRow(link);
            prospectId = getProspectIdFromRow(row, link);
          }
          var select = form.querySelector('select[name="prospect_id"]');
          var prospectField = form.querySelector('[data-acdc-quick-rdv-prospect-field]');
          if(select){
            select.value = prospectId ? String(prospectId) : '';
          }
          if(prospectField){
            // Le prospect de la ligne est pré-sélectionné, mais la liste reste
            // toujours affichée et modifiable (on ne masque plus le champ).
            prospectField.style.display = '';
          }
          syncProspectListMeetingMode(modal);
          if(modal.parentNode !== document.body){
            document.body.appendChild(modal);
          }
          syncQuickRdvModalState(modal, true);
          var focusField = form.querySelector('input[name="rdv[rdv_at]"]');
          if(focusField){ window.setTimeout(function(){ focusField.focus(); }, 0); }
        }
        document.addEventListener('click', function(e){
          var link = e.target.closest('.acdc-prospect-action-item, .acdc-prospect-patch-menu a');
          if(!link){ return; }
          var inlineAction = (link.getAttribute('data-acdc-inline-action') || '').trim().toLowerCase();
          var label = (link.textContent || '').trim().toLowerCase();
          if(inlineAction === 'attribuer à' || label === 'attribuer à'){
            e.preventDefault();
            e.stopPropagation();
            showInlineSelect(link, 'assigned_to');
            return;
          }
          if(inlineAction === 'statut dossier' || label === 'statut dossier'){
            e.preventDefault();
            e.stopPropagation();
            showInlineSelect(link, 'status');
            return;
          }
          var href = link.getAttribute('href') || '';
          var wantsRdvModal = inlineAction === 'relancer' || label === 'relancer' || label === 'ajouter rdv' || href.indexOf('open_rdv=1') !== -1;
          if(wantsRdvModal){
            e.preventDefault();
            closeMenuForLink(link);
            openQuickRdvModal(link);
            return;
          }
          if(label === 'analyse du besoin'){
            e.preventDefault();
            link.textContent = 'Recueil des besoins';
            window.location.href = link.href.replace('tab=need_analyses', 'tab=need_analyses').replace('page=acdc-of-need-analyses', 'page=acdc-of-need-analyses');
            return;
          }
        }, true);
        document.addEventListener('click', function(e){
          var openBtn = e.target.closest('[data-acdc-prospect-list-rdv-open]');
          if(openBtn){
            e.preventDefault();
            var forcedProspectId = openBtn.getAttribute('data-prospect-id') || '';
            openQuickRdvModal(null, forcedProspectId);
          }
        }, true);
        function renameMenuLabels(){
          document.querySelectorAll('.acdc-prospect-action-item, .acdc-prospect-patch-menu a').forEach(function(link){
            var text = (link.textContent || '').trim().toLowerCase();
            if(text === 'analyse du besoin'){ link.textContent = 'Recueil des besoins'; }
            if(text === 'convention/contrat'){ link.textContent = 'Convention / contrat'; }
          });
        }
        renameMenuLabels();
        var obs = new MutationObserver(renameMenuLabels);
        obs.observe(document.documentElement || document.body, {childList:true, subtree:true});
      })();
    </script>

      <script>
      (function(){
        if(window.__acdcProspectMenuFallback31977){ return; }
        window.__acdcProspectMenuFallback31977 = true;
        var triggerSelector = '[data-acdc-prospect-menu-toggle], .acdc-prospect-patch-trigger';
        var menuSelector = '.acdc-prospect-action-dropdown, .acdc-prospect-patch-menu';
        function getMenu(trigger){
          if(!trigger){ return null; }
          if(trigger._acdcProspectStableMenu && trigger._acdcProspectStableMenu.matches && trigger._acdcProspectStableMenu.matches(menuSelector)){ return trigger._acdcProspectStableMenu; }
          if(trigger._acdcFloatingMenu && trigger._acdcFloatingMenu.matches && trigger._acdcFloatingMenu.matches(menuSelector)){ return trigger._acdcFloatingMenu; }
          var targetId = trigger.getAttribute('data-acdc-menu-target');
          if(targetId){
            var byId = document.getElementById(targetId);
            if(byId && byId.matches(menuSelector)){ return byId; }
          }
          var wrapper = trigger.closest('.acdc-prospect-action-menu, [data-acdc-prospect-action-menu], .acdc-table-actions, [data-acdc-row-menu]');
          var local = wrapper ? wrapper.querySelector(menuSelector) : null;
          if(!local){
            var sibling = trigger.nextElementSibling;
            if(sibling && sibling.matches && sibling.matches(menuSelector)){ local = sibling; }
          }
          if(local){
            trigger._acdcProspectStableMenu = local;
            local._acdcTrigger = trigger;
          }
          return local || null;
        }
        function ensureOrigin(menu){
          if(!menu){ return; }
          if(!menu._acdcOriginalParent){ menu._acdcOriginalParent = menu.parentNode; }
          if(typeof menu._acdcOriginalNextSibling === 'undefined'){ menu._acdcOriginalNextSibling = menu.nextSibling || null; }
        }
        function restoreMenu(menu){
          if(!menu){ return; }
          ensureOrigin(menu);
          menu.hidden = true;
          menu.setAttribute('hidden','hidden');
          menu.setAttribute('aria-hidden','true');
          menu.style.display = 'none';
          menu.style.visibility = 'hidden';
          menu.style.pointerEvents = 'none';
          menu.style.position = '';
          menu.style.left = '';
          menu.style.top = '';
          menu.style.right = '';
          menu.style.bottom = '';
          menu.style.zIndex = '';
          var trigger = menu._acdcTrigger || menu._acdcFloatingTrigger || null;
          if(trigger){ trigger.setAttribute('aria-expanded','false'); trigger.classList.remove('is-open'); }
          if(menu._acdcOriginalParent && menu.parentNode !== menu._acdcOriginalParent){
            var ref = menu._acdcOriginalNextSibling;
            if(ref && ref.parentNode === menu._acdcOriginalParent){ menu._acdcOriginalParent.insertBefore(menu, ref); }
            else { menu._acdcOriginalParent.appendChild(menu); }
          }
        }
        function closeAllMenus(except){
          document.querySelectorAll(menuSelector).forEach(function(menu){ if(menu !== except){ restoreMenu(menu); } });
          document.querySelectorAll(triggerSelector).forEach(function(btn){
            var related = getMenu(btn);
            if(!except || related !== except){ btn.setAttribute('aria-expanded','false'); btn.classList.remove('is-open'); }
          });
        }
        function positionMenu(menu, trigger){
          if(!menu || !trigger){ return; }
          var rect = trigger.getBoundingClientRect();
          var viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
          var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
          var maxWidth = Math.max(220, viewportWidth - 24);
          menu.style.maxWidth = maxWidth + 'px';
          menu.style.position = 'fixed';
          menu.style.zIndex = '999999';
          menu.style.display = 'block';
          menu.style.visibility = 'hidden';
          menu.style.pointerEvents = 'none';
          var width = Math.min(menu.offsetWidth || 320, maxWidth);
          var height = menu.offsetHeight || 240;
          var left = rect.right - width;
          if(left < 12){ left = 12; }
          if(left + width > viewportWidth - 12){ left = Math.max(12, viewportWidth - width - 12); }
          var top = rect.bottom + 8;
          if(top + height > viewportHeight - 12){ top = Math.max(12, rect.top - height - 8); }
          menu.style.left = left + 'px';
          menu.style.top = top + 'px';
          menu.style.visibility = 'visible';
          menu.style.pointerEvents = 'auto';
        }
        function toggleMenu(trigger){
          var menu = getMenu(trigger);
          if(!menu){ return; }
          ensureOrigin(menu);
          var isOpen = !menu.hidden && menu.style.display !== 'none';
          if(isOpen){ restoreMenu(menu); return; }
          closeAllMenus(menu);
          if(menu.parentNode !== document.body){ document.body.appendChild(menu); }
          menu._acdcTrigger = trigger;
          trigger._acdcProspectStableMenu = menu;
          menu.hidden = false;
          menu.removeAttribute('hidden');
          menu.setAttribute('aria-hidden','false');
          trigger.setAttribute('aria-expanded','true');
          trigger.classList.add('is-open');
          positionMenu(menu, trigger);
        }
        function refreshOpenMenus(){
          document.querySelectorAll(menuSelector).forEach(function(menu){
            if(menu.hidden || menu.style.display === 'none'){ return; }
            var trigger = menu._acdcTrigger || menu._acdcFloatingTrigger || null;
            if(trigger){ positionMenu(menu, trigger); }
          });
        }
        document.addEventListener('click', function(e){
          var trigger = e.target.closest(triggerSelector);
          if(trigger){
            var menu = getMenu(trigger);
            if(menu){
              e.preventDefault();
              e.stopPropagation();
              if(e.stopImmediatePropagation){ e.stopImmediatePropagation(); }
              toggleMenu(trigger);
              return;
            }
          }
          var menuItem = e.target.closest('.acdc-prospect-action-item, .acdc-prospect-patch-menu a');
          if(menuItem){
            var owningMenu = menuItem.closest(menuSelector);
            if(owningMenu){ restoreMenu(owningMenu); }
            return;
          }
          if(!e.target.closest(menuSelector)){ closeAllMenus(); }
        }, true);
        document.addEventListener('keydown', function(e){ if(e.key === 'Escape'){ closeAllMenus(); } }, true);
        window.addEventListener('resize', refreshOpenMenus, true);
        window.addEventListener('scroll', refreshOpenMenus, true);
        document.addEventListener('acdc:prospect-rdv-modal-closed', function(){ closeAllMenus(); });
      })();
      </script>
    <?php
  }

public function render_admin_prospects_page() { $this->render_admin_portal_wrapper( 'prospects' ); }

public function render_admin_prospect_followup_page() { $this->render_admin_portal_wrapper( 'prospect_followup' ); }
    }

