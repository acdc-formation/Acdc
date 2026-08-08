<?php
/**
 * ACDC Dossiers / Inscriptions / Conventions-contrats — ACDC_Dossiers_Contracts_Render_Trait.
 *
 * Extraction micro-incrémentale du sous-bloc métier :
 * dossiers / inscription / conventions-contrats.
 *
 * @since 3.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Dossiers_Contracts_Render_Trait {


  private function render_front_contract_parameters_tab() {
    $params = $this->get_contract_params_options();
    $is_admin_context = is_admin();
    $action_url = admin_url( 'admin-post.php' );
    $cancel_url = $is_admin_context ? $this->admin_tab_url( 'company_profile' ) : $this->portal_page_url( array( 'tab' => 'company_profile' ) );
    if ( isset( $_GET['updated'] ) ) {
      echo '<div class="acdc-alert acdc-alert-success">Paramètres de la convention enregistrés.</div>';
    }
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Paramètres de la convention / du contrat</h2>
        <p>Configurez le contenu standard de vos conventions et contrats.</p>
      </div>
    </section>
    <form class="acdc-company-profile-form acdc-contract-builder-form" method="post" action="<?php echo esc_url( $action_url ); ?>">
      <?php wp_nonce_field( 'acdc_save_contract_params' ); ?>
      <input type="hidden" name="action" value="acdc_save_contract_params">
      <input type="hidden" name="return_context" value="<?php echo $is_admin_context ? 'admin' : 'front'; ?>">

      <div class="acdc-panel acdc-profile-section">
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Annexer le programme et le règlement intérieur à la convention / au contrat</div>
          <div>
            <label class="acdc-switch">
              <input type="checkbox" name="contract_params[attach_program_rules]" value="1" <?php checked( ! empty( $params['attach_program_rules'] ) ); ?>>
              <span class="acdc-switch-slider"></span>
            </label>
            <p class="acdc-help">Par défaut, le programme et le règlement intérieur sont envoyés en pièce jointe du mail contenant la convention / le contrat.</p>
          </div>
        </div>
        <?php
        $fields = array(
          'legal_reference' => array( 'label' => 'Référence légale à afficher', 'type' => 'text' ),
          'accessibility_handicap' => array( 'label' => 'Accessibilité et handicap', 'type' => 'textarea' ),
          'material_environment' => array( 'label' => 'Moyens matériels et environnement de la formation', 'type' => 'textarea' ),
          'implementation_followup_evaluation' => array( 'label' => 'Modalité de mise en œuvre, de suivi et d’évaluation de la formation', 'type' => 'textarea' ),
          'financial_provisions' => array( 'label' => 'Dispositions financières', 'type' => 'textarea' ),
          'payment_terms' => array( 'label' => 'Modalités de règlement', 'type' => 'textarea' ),
          'cancellation_terms' => array( 'label' => 'Annulation/Report, Dédommagement, Réparation ou Dédit', 'type' => 'textarea' ),
          'possible_disputes' => array( 'label' => 'Différends éventuels', 'type' => 'textarea' ),
          'withdrawal_delay_days' => array( 'label' => 'Délai de rétractation (jours)', 'type' => 'number' ),
        );
        foreach ( $fields as $key => $field ) : ?>
          <div class="acdc-contract-grid">
            <div class="acdc-contract-label"><?php echo esc_html( $field['label'] ); ?></div>
            <div>
              <?php if ( 'textarea' === $field['type'] ) : ?>
                <textarea name="contract_params[<?php echo esc_attr( $key ); ?>]" rows="4"><?php echo esc_textarea( $params[ $key ] ); ?></textarea>
              <?php elseif ( 'number' === $field['type'] ) : ?>
                <input type="number" name="contract_params[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $params[ $key ] ); ?>">
                <p class="acdc-help">Ne s’applique que pour les contrats de formation (commanditaire Particulier).</p>
              <?php else : ?>
                <input type="text" name="contract_params[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $params[ $key ] ); ?>">
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="acdc-panel acdc-profile-section">
        <h3>Sections supplémentaires</h3>
        <?php
        $this->acdc_render_repeatable_simple_sections(
          array(
            'container_id'     => 'acdc-contract-sections',
            'button_id'        => 'acdc-add-contract-section',
            'sections'         => $params['additional_sections'],
            'input_prefix'     => 'contract_params[additional_sections]',
            'title_label'      => 'Titre de la section',
            'content_label'    => 'Contenu de la section',
            'add_button_label' => 'Ajouter une section',
            'min_items'        => 1,
          )
        );
        ?>
      </div>

      <div class="acdc-panel acdc-profile-section">
        <h3>Aperçu PDF</h3>
        <p class="acdc-help">Sélectionnez les éléments à injecter dans l’aperçu PDF de la convention.</p>
        <?php
        $companies = $this->get_companies();
        $formations = $this->get_formations();
    $companies = $this->get_companies();
    $learners = $this->get_learners();
        $sessions = $this->get_sessions();
        $learners = $this->get_learners();
        ?>
        <div class="acdc-grid-2cols" style="margin-bottom:16px;">
          <div>
            <label class="acdc-contract-label" for="acdc-contract-company">Entreprise bénéficiaire</label>
            <select id="acdc-contract-company" name="contract_company_id">
              <option value="">Sélectionner une entreprise</option>
              <?php foreach ( $companies as $company ) : ?>
                <option value="<?php echo esc_attr( $company->id ); ?>"><?php echo esc_html( $company->name ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="acdc-contract-label" for="acdc-contract-formation">Formation</label>
            <select id="acdc-contract-formation" name="contract_formation_id">
              <option value="">Sélectionner une formation</option>
              <?php foreach ( $formations as $formation ) : ?>
                <option value="<?php echo esc_attr( $formation->id ); ?>"><?php echo esc_html( $this->format_formation_option_label( $formation ) ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="acdc-contract-label" for="acdc-contract-session">Session</label>
            <select id="acdc-contract-session" name="contract_session_id">
              <option value="">Sélectionner une session</option>
              <?php foreach ( $sessions as $session ) : ?>
                <option value="<?php echo esc_attr( $session->id ); ?>"><?php echo esc_html( $session->title ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="acdc-contract-label" for="acdc-contract-learner">Apprenant</label>
            <select id="acdc-contract-learner" name="contract_learner_id">
              <option value="">Sélectionner un apprenant</option>
              <?php foreach ( $learners as $learner_item ) : ?>
                <option value="<?php echo esc_attr( $learner_item->id ); ?>"><?php echo esc_html( trim( $learner_item->first_name . ' ' . ( $learner_item->usage_last_name ? $learner_item->usage_last_name : $learner_item->last_name ) ) ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="acdc-profile-preview-row">
          <span class="acdc-preview-chip"><?php echo esc_html( $params['preview_label'] ); ?></span>
        </div>
        <p style="margin-top:16px;"><button type="button" id="acdc-generate-contract-pdf" class="acdc-button acdc-button-primary" data-base-url="<?php echo esc_url( admin_url( 'admin-post.php?action=acdc_generate_contract_pdf&_wpnonce=' . wp_create_nonce( 'acdc_generate_contract_pdf' ) ) ); ?>"><?php echo esc_html( $params['preview_label'] ); ?></button></p>
      </div>

      <div class="acdc-form-actions">
        <a class="acdc-button" href="<?php echo esc_url( $cancel_url ); ?>">Retour</a>
        <button type="submit" class="acdc-button acdc-button-primary">Enregistrer les paramètres</button>
      </div>
    </form>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
      var pdfBtn = document.getElementById('acdc-generate-contract-pdf');
      if(pdfBtn){
        pdfBtn.addEventListener('click', function(){
          var url = new URL(pdfBtn.getAttribute('data-base-url'), window.location.origin);
          ['company','formation','session','learner'].forEach(function(key){
            var field = document.getElementById('acdc-contract-' + key);
            if(field && field.value){
              url.searchParams.set('contract_' + key + '_id', field.value);
            }
          });
          window.open(url.toString(), '_blank');
        });
      }
    });
    </script>
    <?php
  }


  private function render_front_subcontract_parameters_tab() {
    $params = $this->get_subcontract_params_options();
    $is_admin_context = is_admin();
    $action_url = admin_url( 'admin-post.php' );
    $cancel_url = $is_admin_context ? $this->admin_tab_url( 'company_profile' ) : $this->portal_page_url( array( 'tab' => 'company_profile' ) );
    if ( isset( $_GET['updated'] ) ) {
      echo '<div class="acdc-alert acdc-alert-success">Paramètres du contrat de sous-traitance enregistrés.</div>';
    }
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Créer les paramètres du contrat de sous-traitance</h2>
        <p>Configurez les clauses standard du contrat de sous-traitance formateur.</p>
      </div>
    </section>
    <form class="acdc-company-profile-form acdc-contract-builder-form" method="post" action="<?php echo esc_url( $action_url ); ?>">
      <?php wp_nonce_field( 'acdc_save_subcontract_params' ); ?>
      <input type="hidden" name="action" value="acdc_save_subcontract_params">
      <input type="hidden" name="return_context" value="<?php echo $is_admin_context ? 'admin' : 'front'; ?>">
      <div class="acdc-panel acdc-profile-section">
        <?php
        $fields = array(
          'article_3_trainer_obligations' => 'Article 3 - Obligations du formateur',
          'article_4_of_obligations' => "Article 4 - Obligations de l'organisme de formation",
          'article_5_status_liability' => 'Article 5 - Statut juridique et responsabilité',
          'article_7_payment_terms' => 'Article 7 - Modalités de paiement',
          'article_8_duration_termination' => 'Article 8 - Durée et résiliation',
          'article_9_intellectual_property' => 'Article 9 - Propriété intellectuelle',
          'article_10_confidentiality' => 'Article 10 - Confidentialité',
          'article_11_disputes' => 'Article 11 - Différends éventuels',
        );
        foreach ( $fields as $key => $label ) : ?>
          <div class="acdc-contract-grid">
            <div class="acdc-contract-label"><?php echo esc_html( $label ); ?></div>
            <div><textarea name="subcontract_params[<?php echo esc_attr( $key ); ?>]" rows="4"><?php echo esc_textarea( $params[ $key ] ); ?></textarea></div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="acdc-panel acdc-profile-section">
        <h3>Sections supplémentaires</h3>
        <?php
        $this->acdc_render_repeatable_simple_sections(
          array(
            'container_id'     => 'acdc-subcontract-sections',
            'button_id'        => 'acdc-add-subcontract-section',
            'sections'         => $params['additional_sections'],
            'input_prefix'     => 'subcontract_params[additional_sections]',
            'title_label'      => 'Titre de la section',
            'content_label'    => 'Contenu de la section',
            'add_button_label' => 'Ajouter une section',
          )
        );
        ?>
      </div>
      <div class="acdc-panel acdc-profile-section">
        <h3>Aperçu</h3>
        <div class="acdc-profile-preview-row"><span class="acdc-preview-chip"><?php echo esc_html( $params['preview_label'] ); ?></span></div>
      </div>
      <div class="acdc-form-actions">
        <a class="acdc-button" href="<?php echo esc_url( $cancel_url ); ?>">Retour</a>
        <button type="submit" class="acdc-button acdc-button-primary">Enregistrer les paramètres</button>
      </div>
    </form>
    <?php
  }


  private function render_front_training_registrations_list_tab( $is_draft = false ) {
    $entries = $this->get_training_registrations( $is_draft );
    $title = $is_draft ? 'En cours d\'inscription' : 'Apprenants inscrits';
    $empty = $is_draft ? 'Aucun brouillon enregistré.' : 'Aucune inscription enregistrée.';
    $tab_name = $is_draft ? 'registrations_pending' : 'registrations';
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-register-training&tab=' . $tab_name ) : $this->portal_page_url( array( 'tab' => $tab_name ) );
    ?>
    <section class="acdc-section-head">
      <div>
        <h2><?php echo esc_html( $title ); ?></h2>
        <p><?php echo esc_html( $is_draft ? 'Retrouvez ici les inscriptions sauvegardées en brouillon.' : 'Retrouvez ici les inscriptions validées.' ); ?></p>
      </div>
      <div class="acdc-inline-wrap">
        <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-register-training&action=new' ) : $this->portal_page_url( array( 'tab' => 'register_training', 'action' => 'new' ) ) ); ?>">Créer une inscription</a>
      </div>
    </section>
    <div class="acdc-panel">
      <div class="acdc-table-wrap">
        <table class="acdc-table">
          <thead>
            <tr>
              <th>Intitulé</th>
              <th>Formation</th>
              <th>Inscription</th>
              <th>Commanditaire</th>
              <th>Statut</th>
              <th>Modifié le</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php
          $wf_labels = $this->get_workflow_status_labels();
          $wf_colors = $this->get_workflow_status_colors();
          if ( ! empty( $entries ) ) : foreach ( $entries as $entry ) :
            $wf_status = ! empty( $entry->workflow_status ) ? (string) $entry->workflow_status : 'prospect_cree';
            $wf_label  = isset( $wf_labels[ $wf_status ] ) ? $wf_labels[ $wf_status ] : $wf_status;
            $wf_color  = isset( $wf_colors[ $wf_status ] ) ? $wf_colors[ $wf_status ] : '#6b7280';
            $comp      = $this->compute_registration_completude_score( $entry );
            $comp_pct  = $comp['percent'];
            $comp_dot  = $comp_pct >= 75 ? '#35b37e' : ( $comp_pct >= 40 ? '#f0b45e' : '#e06d6d' );
            /* Badge PSH — lecture réponses NAD liées au dossier */
            $psh_badge_level = 'none';
            if ( ! empty( $entry->id ) ) {
              global $wpdb;
              $psh_nad_row = $wpdb->get_row( $wpdb->prepare(
                "SELECT reponses FROM {$this->need_analysis_table} WHERE dossier_id = %d AND statut = 'traite' ORDER BY id DESC LIMIT 1",
                (int) $entry->id
              ) );
              if ( $psh_nad_row && ! empty( $psh_nad_row->reponses ) ) {
                $psh_rep = json_decode( $psh_nad_row->reponses, true );
                if ( is_array( $psh_rep ) ) {
                  if ( isset( $psh_rep['com_handicap'] ) && 'Oui' === $psh_rep['com_handicap'] ) {
                    $psh_badge_level = 'high';
                  } elseif ( ! empty( $psh_rep['app_difficultes'] ) && '' !== trim( $psh_rep['app_difficultes'] ) ) {
                    $psh_badge_level = 'medium';
                  }
                }
              }
            }
          ?>
            <tr>
              <td><?php echo esc_html( $entry->title ); ?></td>
              <td><?php echo esc_html( $entry->formation_title ); ?></td>
              <td><?php echo esc_html( $this->get_training_registration_subject_label( $entry->belongs_to_group, $entry->group_label, $entry->learner_label, $entry->learners_label ) ); ?></td>
              <td><?php echo esc_html( $entry->company_label ? $entry->company_label : '—' ); ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                  <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;color:#fff;background:<?php echo esc_attr( $wf_color ); ?>;white-space:nowrap;"><?php echo esc_html( $wf_label ); ?></span>
                  <span title="Complétude : <?php echo (int) $comp_pct; ?>%" style="display:inline-flex;align-items:center;gap:3px;font-size:11px;font-weight:700;color:<?php echo esc_attr( $comp_dot ); ?>;white-space:nowrap;">
                    <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?php echo esc_attr( $comp_dot ); ?>;"></span><?php echo (int) $comp_pct; ?>%
                  </span>
                  <?php if ( 'high' === $psh_badge_level ) : ?>
                  <span title="Situation de handicap déclarée dans le recueil des besoins" style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;background:#e06d6d;color:#fff;white-space:nowrap;">♿ PSH</span>
                  <?php elseif ( 'medium' === $psh_badge_level ) : ?>
                  <span title="Difficultés spécifiques (Dys/TDAH) signalées dans le recueil des besoins" style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;background:#f0b45e;color:#fff;white-space:nowrap;">⚠ Dys</span>
                  <?php endif; ?>
                </div>
              </td>
              <td><?php echo esc_html( mysql2date( 'j F Y \\à H\\hi', $entry->updated_at ) ); ?></td>
              <td class="acdc-actions-cell-icons">
                <?php $view_url   = is_admin() ? admin_url( 'admin.php?page=acdc-of-register-training&action=view&item_id=' . (int) $entry->id ) : $this->portal_page_url( array( 'tab' => 'register_training', 'action' => 'view', 'item_id' => (int) $entry->id ) ); ?>
                <?php $edit_url   = is_admin() ? admin_url( 'admin.php?page=acdc-of-register-training&action=edit&item_id=' . (int) $entry->id ) : $this->portal_page_url( array( 'tab' => 'register_training', 'action' => 'edit', 'item_id' => (int) $entry->id ) ); ?>
                <?php $delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_delete_training_registration&registration_id=' . (int) $entry->id . ( is_admin() ? '&page=acdc-of-register-training&return_tab=' . $tab_name : '' ) ), 'acdc_delete_training_registration_' . (int) $entry->id ); ?>
                <div class="acdc-groups-actions-inline">
                  <a href="<?php echo esc_url( $view_url ); ?>"
                     class="acdc-row-action-icon acdc-row-view-link"
                     data-acdc-iconized="1"
                     title="Voir le dossier"
                     aria-label="Voir">
                    <?php echo $this->render_inline_icon( 'view', 25 ); ?>
                    <span class="acdc-action-hub-sr screen-reader-text">Voir</span>
                  </a>
                  <a href="<?php echo esc_url( $edit_url ); ?>"
                     class="acdc-row-action-icon acdc-row-edit-link"
                     data-acdc-iconized="1"
                     title="Modifier l'inscription"
                     aria-label="Modifier">
                    <?php echo $this->render_inline_icon( 'edit-pencil', 25 ); ?>
                    <span class="acdc-action-hub-sr screen-reader-text">Modifier</span>
                  </a>
                  <a href="<?php echo esc_url( $delete_url ); ?>"
                     class="acdc-row-action-icon acdc-row-delete-link"
                     data-acdc-iconized="1"
                     title="Supprimer l'inscription"
                     aria-label="Supprimer"
                     onclick="return confirm('Supprimer cette inscription ?');">
                    <?php echo $this->render_inline_icon( 'trash', 25 ); ?>
                    <span class="acdc-action-hub-sr screen-reader-text">Supprimer</span>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; else : ?>
            <tr><td colspan="7"><?php echo esc_html( $empty ); ?></td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
  }


  private function render_front_training_registration_form( $registration = null, $readonly = false ) {
    $registration = $registration ?: $this->get_training_registration_default_record();
    $state = ! $readonly ? $this->acdc_consume_form_state( 'training_registration' ) : array();
    $registration = $this->acdc_apply_form_state_to_record( $registration, $state );
    $required_fields = $this->acdc_get_form_required_fields( $state );
    $is_existing = ! empty( $registration->id );
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-register-training' ) : $this->portal_page_url( array( 'tab' => 'register_training' ) );
    $groups = $this->get_groups();
    $learners = $this->get_learners();
    $companies = $this->get_companies();
    $formations = $this->get_formations( array( 'archived' => false ) );
    $contracts = $this->get_registration_contracts();
    $group_map = array();
    foreach ( $groups as $group ) {
      $group_map[ (int) $group->id ] = array(
        'formation_id' => isset( $group->formation_id ) ? (int) $group->formation_id : 0,
        'learners' => implode( ', ', $this->get_group_learner_names( $group ) ),
      );
    }
    $contract_map = array();
    foreach ( $contracts as $contract ) {
      $contract_map[ (int) $contract->id ] = array(
        'formation_id' => isset( $contract->formation_id ) ? (int) $contract->formation_id : 0,
        'price_ht' => isset( $contract->price_ht ) ? (string) $contract->price_ht : '',
      );
    }
    $learner_map = array();
    foreach ( $learners as $learner ) {
      $label = trim( $learner->first_name . ' ' . $learner->usage_last_name );
      $learner_map[ (int) $learner->id ] = array(
        'company_id' => isset( $learner->company_id ) ? (int) $learner->company_id : 0,
        'label' => $label,
      );
    }
    $group_learners_display = isset( $registration->learners_label ) ? (string) $registration->learners_label : '';
    if ( ! $group_learners_display && ! empty( $registration->group_id ) && isset( $group_map[ (int) $registration->group_id ] ) ) {
      $group_learners_display = $group_map[ (int) $registration->group_id ]['learners'];
    }
    $selected_source_prospect_id = isset( $registration->source_prospect_id ) ? (int) $registration->source_prospect_id : ( isset( $_GET['prospect_id'] ) ? absint( wp_unslash( $_GET['prospect_id'] ) ) : 0 );
    $this->acdc_render_form_validation_style( '.acdc-training-registration-form' );
    $cancel_url = $base_url;
    if ( $selected_source_prospect_id ) {
      $cancel_url = is_admin()
        ? admin_url( 'admin.php?page=acdc-of-prospects&action=edit&item_id=' . (int) $selected_source_prospect_id )
        : $this->portal_page_url( array( 'tab' => 'prospects', 'action' => 'edit', 'item_id' => (int) $selected_source_prospect_id ) );
    }
    ?>
    <section class="acdc-section-head">
      <div><h2><?php echo esc_html( $is_existing ? 'Modifier une inscription en formation' : 'Créer une inscription en formation' ); ?></h2></div>
    </section>
    <?php if ( $readonly && $is_existing ) :
      $score = $this->compute_registration_completude_score( $registration );
      $score_pct = $score['percent'];
      $score_color = $score_pct >= 75 ? '#35b37e' : ( $score_pct >= 40 ? '#f0b45e' : '#e06d6d' );
      $score_label = $score_pct >= 75 ? 'Dossier complet' : ( $score_pct >= 40 ? 'En cours' : 'Incomplet' );
    ?>
    <div class="acdc-panel acdc-mb-18" style="border-left:4px solid <?php echo esc_attr( $score_color ); ?>;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
        <div style="font-size:13px;font-weight:700;color:#0f2c52;text-transform:uppercase;letter-spacing:.04em;">Complétude du dossier</div>
        <div style="display:flex;align-items:center;gap:10px;">
          <span style="font-size:22px;font-weight:800;color:<?php echo esc_attr( $score_color ); ?>;"><?php echo (int) $score_pct; ?>%</span>
          <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;color:#fff;background:<?php echo esc_attr( $score_color ); ?>;"><?php echo esc_html( $score_label ); ?></span>
        </div>
      </div>
      <div style="background:#f4f5f7;border-radius:999px;height:8px;overflow:hidden;margin-bottom:14px;">
        <div style="height:100%;width:<?php echo (int) $score_pct; ?>%;background:<?php echo esc_attr( $score_color ); ?>;border-radius:999px;transition:width .4s ease;"></div>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <?php foreach ( $score['criteria'] as $c ) : ?>
        <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:<?php echo $c['ok'] ? '#d9f3e5' : '#f4f5f7'; ?>;color:<?php echo $c['ok'] ? '#1a7a50' : '#6b7280'; ?>;">
          <?php echo $c['ok'] ? '✓' : '○'; ?> <?php echo esc_html( $c['label'] ); ?>
        </span>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <form class="acdc-form acdc-contract-builder-form acdc-training-registration-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
      <?php wp_nonce_field( 'acdc_save_training_registration' ); ?>
      <input type="hidden" name="action" value="acdc_save_training_registration">
      <input type="hidden" name="registration_id" value="<?php echo esc_attr( (int) $registration->id ); ?>">
      <input type="hidden" name="registration[source_prospect_id]" value="<?php echo esc_attr( $selected_source_prospect_id ); ?>">
      <input type="hidden" name="return_after_save" value="edit">
      <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-register-training"><?php endif; ?>
      <div class="acdc-panel acdc-mb-18">
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">&nbsp;</div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">L'apprenant appartient à un groupe <span class="acdc-required">*</span></div>
          <div>
            <select name="registration[belongs_to_group]" id="acdc-registration-belongs-to-group" <?php echo $readonly ? 'disabled' : ''; ?> required<?php echo $this->acdc_get_invalid_field_class( 'belongs_to_group', $required_fields ); ?>><?php echo $this->acdc_get_invalid_field_note( 'belongs_to_group', $required_fields ); ?>
              <option value="">Choisir une option</option>
              <option value="Oui" <?php selected( isset( $registration->belongs_to_group ) ? $registration->belongs_to_group : '', 'Oui' ); ?>>Oui</option>
              <option value="Non" <?php selected( isset( $registration->belongs_to_group ) ? $registration->belongs_to_group : '', 'Non' ); ?>>Non</option>
            </select>
            <?php echo $this->acdc_get_invalid_field_note( 'formation_id', $required_fields ); ?>
          </div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Auto-remplissage (facultatif)</div>
          <div>
            <select name="registration[autofill_contract_id]" id="acdc-registration-autofill" <?php echo $readonly ? 'disabled' : ''; ?>>
              <option value="">Cliquez pour choisir</option>
              <?php foreach ( $contracts as $contract ) : ?>
                <option value="<?php echo esc_attr( $contract->id ); ?>" <?php selected( (int) $registration->autofill_contract_id, (int) $contract->id ); ?>><?php echo esc_html( $contract->title ); ?></option>
              <?php endforeach; ?>
            </select>
            <p class="acdc-registration-help">Si vous avez importé ou fait signer une convention, renseignez le nom de l'entreprise ou du particulier pour accélérer l'inscription.</p>
          </div>
        </div>
        <div class="acdc-registration-mode-single">
          <div class="acdc-contract-grid">
            <div class="acdc-contract-label">Apprenant <span class="acdc-required">*</span></div>
            <div>
              <?php
              // ACDC 3.25.75 — Afficher les apprenants de la convention si plusieurs
              $contract_learner_ids = ! empty( $registration->learner_ids )
                ? array_values( array_filter( array_map( 'absint', explode( ',', (string) $registration->learner_ids ) ) ) )
                : array();
              if ( ! $readonly && count( $contract_learner_ids ) > 1 ) :
                global $wpdb;
                ?>
                <div style="background:#f0f4f8;border-radius:8px;padding:10px 14px;margin-bottom:10px;font-size:13px;">
                  <strong style="color:#0f2c52;">Apprenants de la convention (<?php echo count( $contract_learner_ids ); ?>) :</strong>
                  <ul style="margin:6px 0 0 16px;padding:0;list-style:disc;">
                  <?php foreach ( $contract_learner_ids as $clid ) :
                    $cl = $wpdb->get_row( $wpdb->prepare( "SELECT first_name, usage_last_name, last_name FROM {$this->learner_table} WHERE id = %d", $clid ) );
                    if ( ! $cl ) continue;
                    $cl_label = trim( (string) $cl->first_name . ' ' . ( ! empty( $cl->usage_last_name ) ? $cl->usage_last_name : $cl->last_name ) );
                  ?>
                    <li><?php echo esc_html( $cl_label ); ?></li>
                  <?php endforeach; ?>
                  </ul>
                  <p style="margin:6px 0 0;color:#6b7280;font-size:12px;">Un dossier d'inscription sera créé automatiquement pour chaque apprenant à l'enregistrement.</p>
                </div>
                <?php /* Champs cachés pour passer tous les IDs — le select est masqué */ ?>
                <?php foreach ( $contract_learner_ids as $clid ) : ?>
                  <input type="hidden" name="registration[all_learner_ids][]" value="<?php echo esc_attr( $clid ); ?>">
                <?php endforeach; ?>
                <input type="hidden" name="registration[learner_id]" value="<?php echo esc_attr( $registration->learner_id ?: $contract_learner_ids[0] ); ?>">
              <?php else : ?>
              <div class="acdc-registration-with-action">
                <select name="registration[learner_id]" id="acdc-registration-learner" <?php echo $readonly ? 'disabled' : ''; ?><?php echo $this->acdc_get_invalid_field_class( 'learner_id', $required_fields ); ?>>
                  <option value="">Cliquez pour choisir</option>
                  <?php foreach ( $learners as $learner ) : ?>
                    <?php $label = trim( $learner->first_name . ' ' . $learner->usage_last_name ); ?>
                    <option value="<?php echo esc_attr( $learner->id ); ?>" <?php selected( (int) $registration->learner_id, (int) $learner->id ); ?>><?php echo esc_html( $label ); ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if ( ! $readonly ) : ?><a class="acdc-registration-action-link" href="<?php echo esc_url( admin_url( 'admin.php?page=acdc-of-learners&action=new' ) ); ?>" title="Créer un apprenant"><?php echo $this->render_inline_icon( 'create', 16 ); ?></a><?php endif; ?>
              </div>
              <?php echo $this->acdc_get_invalid_field_note( 'learner_id', $required_fields ); ?>
              <p class="acdc-registration-help">Renseignez le nom ou prénom de l'apprenant à inscrire. Les apprenants disponibles sont ceux qui ont été créés dans le répertoire “Apprenants”. Vous souhaitez directement créer votre apprenant à inscrire ? Cliquez sur “+” en bout de ligne.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="acdc-registration-mode-group">
          <div class="acdc-contract-grid">
            <div class="acdc-contract-label">Groupe <span class="acdc-required">*</span></div>
            <div>
              <div class="acdc-registration-with-action">
                <select name="registration[group_id]" id="acdc-registration-group" <?php echo $readonly ? 'disabled' : ''; ?><?php echo $this->acdc_get_invalid_field_class( 'group_id', $required_fields ); ?>>
                  <option value="">Cliquez pour choisir</option>
                  <?php foreach ( $groups as $group ) : ?>
                    <option value="<?php echo esc_attr( $group->id ); ?>" <?php selected( (int) $registration->group_id, (int) $group->id ); ?>><?php echo esc_html( $group->name ); ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if ( ! $readonly ) : ?><a class="acdc-registration-action-link" href="<?php echo esc_url( admin_url( 'admin.php?page=acdc-of-groups&action=new' ) ); ?>" title="Créer un groupe"><?php echo $this->render_inline_icon( 'create', 16 ); ?></a><?php endif; ?>
              </div>
              <?php echo $this->acdc_get_invalid_field_note( 'group_id', $required_fields ); ?>
              <p class="acdc-registration-help">Renseignez un des groupes créés en amont dans le répertoire “Groupes”. En associant un apprenant à un groupe, les informations liées à la formation seront automatiquement renseignées pour les autres apprenants ajoutés au groupe.</p>
            </div>
          </div>
          <div class="acdc-contract-grid">
            <div class="acdc-contract-label">Apprenants <span class="acdc-required">*</span></div>
            <div>
              <div class="acdc-registration-with-action">
                <input type="text" id="acdc-registration-group-learners" value="<?php echo esc_attr( $group_learners_display ); ?>" placeholder="Rechercher" readonly>
                <?php if ( ! $readonly ) : ?><a class="acdc-registration-action-link" href="<?php echo esc_url( admin_url( 'admin.php?page=acdc-of-learners&action=new' ) ); ?>" title="Créer un apprenant"><?php echo $this->render_inline_icon( 'create', 16 ); ?></a><?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Commandité par une entreprise (facultatif)</div>
          <div>
            <div class="acdc-registration-with-action">
              <select name="registration[company_id]" id="acdc-registration-company" <?php echo $readonly ? 'disabled' : ''; ?>>
                <option value="0">La formation n'est pas commanditée par une entreprise</option>
                <?php foreach ( $companies as $company ) : ?>
                  <option value="<?php echo esc_attr( $company->id ); ?>" <?php selected( (int) $registration->company_id, (int) $company->id ); ?>><?php echo esc_html( $company->name ); ?></option>
                <?php endforeach; ?>
              </select>
              <?php if ( ! $readonly ) : ?><a class="acdc-registration-action-link" href="<?php echo esc_url( admin_url( 'admin.php?page=acdc-of-companies&action=new' ) ); ?>" title="Créer une entreprise"><?php echo $this->render_inline_icon( 'create', 16 ); ?></a><?php endif; ?>
            </div>
            <p class="acdc-registration-help">Renseignez une des entreprises que vous avez créées en amont dans le répertoire “Entreprises”. Vous souhaitez directement créer une entreprise à associer ? Cliquez sur “+” en bout de ligne.</p>
          </div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Commanditaire entreprise</div>
          <div>
            <div class="acdc-registration-with-action">
              <select name="registration_contract[company_id]" id="acdc-registration-contract-company"<?php echo $readonly ? ' disabled' : ''; ?>>
                <option value="0">Cliquez pour choisir</option>
                <?php foreach ( $companies as $company ) : ?>
                  <option value="<?php echo esc_attr( $company->id ); ?>" <?php selected( (int) ( $contract->company_id ?? 0 ), (int) $company->id ); ?>><?php echo esc_html( $company->name ); ?></option>
                <?php endforeach; ?>
              </select>
              <?php if ( ! $readonly ) : ?><a class="acdc-registration-action-link" href="<?php echo esc_url( admin_url( 'admin.php?page=acdc-of-companies&action=new' ) ); ?>" title="Créer une entreprise" target="_blank" rel="noopener"><?php echo $this->render_inline_icon( 'create', 16 ); ?></a><?php endif; ?>
            </div>
          </div>
        </div>

        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Formation <span class="acdc-required">*</span></div>
          <div>
            <select name="registration[formation_id]" id="acdc-registration-formation" <?php echo $readonly ? 'disabled' : ''; ?> required>
              <option value="">—</option>
              <?php foreach ( $formations as $formation ) : ?>
                <option value="<?php echo esc_attr( $formation->id ); ?>" <?php selected( (int) $registration->formation_id, (int) $formation->id ); ?>><?php echo esc_html( $this->format_formation_option_label( $formation ) ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <?php
        // ACDC 3.25.74 — Séances de la convention (affichage informatif)
        $contract_seances = array();
        if ( ! empty( $registration->autofill_contract_id ) ) {
          $linked_contract = $this->get_registration_contract( (int) $registration->autofill_contract_id );
          if ( $linked_contract && ! empty( $linked_contract->seances_dates ) ) {
            foreach ( array_filter( array_map( 'trim', explode( ',', (string) $linked_contract->seances_dates ) ) ) as $d ) {
              if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) { $contract_seances[] = $d; }
            }
          }
        }
        if ( ! empty( $contract_seances ) ) : ?>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Dates des séances <span style="font-size:11px;color:#6b7280;">(depuis la convention)</span></div>
          <div style="padding:8px 0;">
            <input type="hidden" name="registration[seances_dates]" value="<?php echo esc_attr( implode( ',', $contract_seances ) ); ?>">
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
              <?php foreach ( $contract_seances as $i => $sd ) : ?>
                <span style="display:inline-block;padding:4px 10px;background:#f0e6dc;color:#8b5b23;border-radius:20px;font-size:13px;font-weight:600;">
                  J<?php echo ($i + 1); ?> — <?php echo esc_html( wp_date( 'd/m/Y', strtotime( $sd ) ) ); ?>
                </span>
              <?php endforeach; ?>
            </div>
            <p style="margin:6px 0 0;font-size:12px;color:#6b7280;">Les séances seront créées automatiquement à l'enregistrement du dossier.</p>
          </div>
        </div>
        <?php endif; ?>
        <?php
        // ACDC 3.25.72 — Formateur désigné
        $all_trainers = $this->get_trainers();
        $current_trainer_id = ! empty( $registration->trainer_id ) ? (int) $registration->trainer_id : 0;
        ?>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Formateur désigné</div>
          <div>
            <?php if ( $readonly ) : ?>
              <p style="margin:0;padding:10px 0;font-size:14px;"><?php echo ! empty( $registration->trainer_label ) ? esc_html( $registration->trainer_label ) : '—'; ?></p>
            <?php else : ?>
              <select name="registration[trainer_id]" class="acdc-input">
                <option value="0">— Aucun formateur désigné —</option>
                <?php foreach ( $all_trainers as $tr ) :
                  $tr_label = trim( (string) $tr->first_name . ' ' . (string) $tr->last_name );
                ?>
                  <option value="<?php echo esc_attr( $tr->id ); ?>" <?php selected( $current_trainer_id, (int) $tr->id ); ?>><?php echo esc_html( $tr_label ); ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Accès extranet</div>
          <div>
            <p class="acdc-checkbox-line"><label class="acdc-switch"><input type="checkbox" name="registration[extranet_access]" value="1" <?php checked( ! isset( $registration->extranet_access ) || (int) $registration->extranet_access === 1 ); ?> <?php echo $readonly ? 'disabled' : ''; ?>><span class="acdc-switch-slider"></span></label></p>
            <p class="acdc-registration-help">L'accès extranet est ouvert automatiquement dès que le dossier est validé (non-brouillon). L'e-mail d'activation est envoyé à l'apprenant dans l'heure suivant l'enregistrement.</p>
            <?php
            // Bouton "Renvoyer l'e-mail d'ouverture" si un compte extranet existe déjà pour cet apprenant
            if ( $registration && ! empty( $registration->id ) && isset( $registration->extranet_access ) && (int) $registration->extranet_access === 1 ) :
              // Chercher l'email de l'apprenant principal
              $renvoi_email = '';
              if ( ! empty( $registration->learner_id ) ) {
                global $wpdb;
                $renvoi_email = (string) $wpdb->get_var( $wpdb->prepare( "SELECT email FROM {$this->learner_table} WHERE id = %d LIMIT 1", (int) $registration->learner_id ) );
              }
              if ( '' !== $renvoi_email ) {
                $portal_account = $this->learner_portal_get_account_by_email( $renvoi_email );
                if ( $portal_account ) :
                  if ( is_admin() ) {
                    $renvoi_url = wp_nonce_url(
                      admin_url( 'admin-post.php?action=acdc_resend_learner_extranet_email&registration_id=' . (int) $registration->id ),
                      'acdc_resend_learner_extranet_email_' . (int) $registration->id
                    );
                  } else {
                    /* ACDC 3.25.169 — L'URL ne portait aucun onglet : l'extranet
                       retombait sur le tableau de bord et l'action de renvoi, qui
                       n'était branchée qu'à l'intérieur d'un onglet précis, n'était
                       jamais exécutée. Aucun e-mail, aucun message. On transmet
                       l'onglet courant, pour partir ET pour revenir au bon endroit. */
                    $cur_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'registrations';
                    $renvoi_url = wp_nonce_url(
                      $this->portal_page_url( array( 'tab' => $cur_tab, 'trf_action' => 'resend_extranet', 'registration_id' => (int) $registration->id, 'return_tab' => $cur_tab ) ),
                      'acdc_resend_learner_extranet_email_' . (int) $registration->id
                    );
                  }
                  $cur_tab_open = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'registrations';
                  $open_url = wp_nonce_url(
                    $this->portal_page_url( array( 'tab' => $cur_tab_open, 'trf_action' => 'open_extranet_access', 'registration_id' => (int) $registration->id, 'return_tab' => $cur_tab_open ) ),
                    'acdc_open_learner_extranet_access_' . (int) $registration->id
                  );
            ?>
              <p style="margin-top:8px;">
                <a href="<?php echo esc_url( $renvoi_url ); ?>" class="acdc-button acdc-button-soft" style="height:32px;padding:0 14px;font-size:12px;display:inline-flex;align-items:center;border-radius:8px;" onclick="return confirm('Renvoyer l\'e-mail d\'ouverture à <?php echo esc_js( $renvoi_email ); ?> ?');">
                  ✉ Renvoyer l'e-mail d'ouverture
                </a>
                <span style="margin-left:10px;font-size:12px;color:#5a6577;">Statut compte : <strong><?php echo esc_html( $portal_account->status ); ?></strong></span>
              </p>
              <?php /* ACDC 3.25.169 — Sortie de secours quand l'e-mail n'arrive pas :
                       le compte reste « jamais activé », et l'apprenant lit « votre accès
                       doit d'abord être activé depuis le lien reçu par e-mail » alors
                       qu'aucun e-mail n'existe. Ce bouton affiche ce lien à l'écran. */ ?>
              <p style="margin-top:6px;">
                <a href="<?php echo esc_url( $open_url ); ?>" class="acdc-button acdc-button-soft" style="height:32px;padding:0 14px;font-size:12px;display:inline-flex;align-items:center;border-radius:8px;">
                  🔑 Obtenir le lien d'activation
                </a>
                <span style="margin-left:10px;font-size:12px;color:#5a6577;">Sans passer par la messagerie — le lien s'affiche à l'écran.</span>
              </p>
            <?php
                endif;
              }
            endif;
            ?>
          </div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Tarif de l'action de formation (€ HT) <span class="acdc-required">*</span></div>
          <div>
            <input type="text" name="registration[price_ht]" id="acdc-registration-price" value="<?php echo esc_attr( isset( $registration->price_ht ) ? $registration->price_ht : '' ); ?>" placeholder="Tarif de l'action de formation (€ HT)" <?php echo $readonly ? 'readonly' : ''; ?>>
            <p class="acdc-registration-help">Indiquez le tarif de la formation si nécessaire pour l'automatisation de la facturation.</p>
          </div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Frais de transport</div>
          <div><p class="acdc-checkbox-line"><label class="acdc-switch"><input type="checkbox" name="registration[transport_fees_enabled]" value="1" <?php checked( ! empty( $registration->transport_fees_enabled ) ); ?> <?php echo $readonly ? 'disabled' : ''; ?>><span class="acdc-switch-slider"></span></label></p></div>
        </div>
        <div class="acdc-contract-grid acdc-contract-conditional-row" data-acdc-toggle-row="transport_fees_enabled">
          <div class="acdc-contract-label">Montant des frais de transport (€ HT)</div>
          <div><input type="text" name="registration_contract[transport_fees_amount_ht]" value="<?php echo esc_attr( isset( $contract->transport_fees_amount_ht ) ? $contract->transport_fees_amount_ht : '' ); ?>" placeholder="Montant des frais de transport (€ HT)"<?php echo $readonly ? ' readonly' : ''; ?>></div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Frais de restauration et / ou hébergement</div>
          <div><p class="acdc-checkbox-line"><label class="acdc-switch"><input type="checkbox" name="registration[meal_fees_enabled]" value="1" <?php checked( ! empty( $registration->meal_fees_enabled ) ); ?> <?php echo $readonly ? 'disabled' : ''; ?>><span class="acdc-switch-slider"></span></label></p></div>
        </div>
      </div>
      <div class="acdc-panel acdc-mb-18">
        <h3>Brouillon</h3>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Sauvegarder en tant que brouillon</div>
          <div>
            <p class="acdc-checkbox-line"><label class="acdc-switch"><input type="checkbox" name="registration[is_draft]" value="1" <?php checked( ! empty( $registration->is_draft ) ); ?> <?php echo $readonly ? 'disabled' : ''; ?>><span class="acdc-switch-slider"></span></label></p>
            <p class="acdc-registration-help">Activez cette option si vous souhaitez sauvegarder les champs déjà remplis et reprendre la création de l'apprenant plus tard. Vous retrouverez votre brouillon dans le sous-menu “En cours d'inscription”.</p>
          </div>
        </div>
      </div>
      <?php if ( ! $readonly ) : ?>
      <p class="acdc-actions-end">
        <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $cancel_url ); ?>">Annuler</a>
        <?php if ( ! $is_existing ) : ?><button type="submit" name="save_and_add" value="1" class="acdc-button acdc-button-primary">Créer & ajouter un autre</button><?php endif; ?>
        <button type="submit" class="acdc-button acdc-button-primary"><?php echo $is_existing ? 'Valider' : 'Valider'; ?></button>
      </p>
      <?php else : ?>
      <p class="acdc-actions-end"><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'item_id' => (int) $registration->id ), $base_url ) ); ?>">Modifier cette inscription</a></p>
      <?php endif; ?>
    </form>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
      var belongs = document.getElementById('acdc-registration-belongs-to-group');
      var groupWrap = document.querySelector('.acdc-registration-mode-group');
      var singleWrap = document.querySelector('.acdc-registration-mode-single');
      var groupField = document.getElementById('acdc-registration-group');
      var groupLearnersField = document.getElementById('acdc-registration-group-learners');
      var learnerField = document.getElementById('acdc-registration-learner');
      var companyField = document.getElementById('acdc-registration-company');
      var formationField = document.getElementById('acdc-registration-formation');
      var priceField = document.getElementById('acdc-registration-price');
      var autofillField = document.getElementById('acdc-registration-autofill');
      var groupMap = <?php echo wp_json_encode( $group_map ); ?> || {};
      var contractMap = <?php echo wp_json_encode( $contract_map ); ?> || {};
      var learnerMap = <?php echo wp_json_encode( $learner_map ); ?> || {};
      function toggleMode(){
        if(!belongs){return;}
        var yes = belongs.value === 'Oui';
        if(groupWrap){groupWrap.style.display = yes ? '' : 'none';}
        if(singleWrap){singleWrap.style.display = yes ? 'none' : '';}
      }
      function syncGroup(){
        if(!groupField){return;}
        var groupData = groupMap[groupField.value] || null;
        if(groupLearnersField){ groupLearnersField.value = groupData && groupData.learners ? groupData.learners : ''; }
        if(groupData && formationField && groupData.formation_id){ formationField.value = String(groupData.formation_id); }
      }
      function syncAutofill(){
        if(!autofillField){return;}
        var contractData = contractMap[autofillField.value] || null;
        if(contractData && formationField && contractData.formation_id){ formationField.value = String(contractData.formation_id); }
        if(contractData && priceField && contractData.price_ht && !priceField.value){ priceField.value = contractData.price_ht; }
      }
      function syncLearner(){
        if(!learnerField){return;}
        var learnerData = learnerMap[learnerField.value] || null;
        if(learnerData && companyField && learnerData.company_id && companyField.value === '0'){ companyField.value = String(learnerData.company_id); }
      }
      if(belongs){ belongs.addEventListener('change', toggleMode); toggleMode(); }
      if(groupField){ groupField.addEventListener('change', syncGroup); syncGroup(); }
      if(autofillField){ autofillField.addEventListener('change', syncAutofill); syncAutofill(); }
      if(learnerField){ learnerField.addEventListener('change', syncLearner); syncLearner(); }
    });
    </script>
    <?php
  }


  /* -----------------------------------------------------------------------
   * Helper — Formatage des dates de séances pour l'affichage liste/fiche
   * Priorité : seances_dates ISO → liste séances
   * Fallback : start_date / end_date
   * -------------------------------------------------------------------- */
  private function acdc_format_seances_dates_for_display( $entry ) {
    if ( ! empty( $entry->seances_dates ) ) {
      $parts = array_values( array_filter( array_map( 'trim', explode( ',', (string) $entry->seances_dates ) ) ) );
      sort( $parts );
      $lines = array();
      foreach ( $parts as $i => $d ) {
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
          $lines[] = 'Séance ' . ( $i + 1 ) . ' : ' . $this->format_pdf_date( $d );
        }
      }
      if ( ! empty( $lines ) ) {
        return implode( "\n", $lines );
      }
    }
    if ( ! empty( $entry->start_date ) || ! empty( $entry->end_date ) ) {
      return 'Début : ' . $this->format_pdf_date( $entry->start_date ?? '' ) . "\nFin : " . $this->format_pdf_date( $entry->end_date ?? '' );
    }
    return '—';
  }

  private function render_registration_contract_rich_field( $name, $value, $readonly = false, $rows = 5, $label = '' ) {
    $editor_id = 'acdc-editor-' . wp_generate_password( 8, false, false );
    ?>
    <div class="acdc-contract-rich-editor" data-acdc-rich-editor>
      <div class="acdc-contract-rich-toolbar"<?php echo $readonly ? ' hidden' : ''; ?>>
        <button type="button" data-acdc-rich-tag="strong" title="Gras">B</button>
        <button type="button" data-acdc-rich-tag="em" title="Italique">I</button>
        <button type="button" data-acdc-rich-tag="s" title="Barré">S</button>
        <button type="button" data-acdc-rich-link="1" title="Lien">🔗</button>
      </div>
      <textarea id="<?php echo esc_attr( $editor_id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="<?php echo esc_attr( $rows ); ?>"<?php echo $readonly ? ' readonly' : ''; ?> aria-label="<?php echo esc_attr( $label ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
    </div>
    <?php
  }



  private function render_registration_contract_commanditaire_markup( $display ) {
    $primary = isset( $display['primary'] ) ? trim( (string) $display['primary'] ) : '';
    $secondary = isset( $display['secondary'] ) ? trim( (string) $display['secondary'] ) : '';
    if ( '' === $primary ) {
      $primary = '—';
    }
    ?>
    <div class="acdc-commanditaire-stack">
      <strong class="acdc-commanditaire-primary"><?php echo esc_html( $primary ); ?></strong>
      <?php if ( '' !== $secondary ) : ?>
        <span class="acdc-commanditaire-secondary"><?php echo esc_html( $secondary ); ?></span>
      <?php endif; ?>
    </div>
    <?php
  }


  private function render_front_registration_contract_tab( $action, $item_id ) {
    $search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
    $contracts = $this->get_registration_contracts( $search );
    $contract = $item_id ? $this->get_registration_contract( $item_id ) : null;
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-registration-contract' ) : $this->portal_page_url( array( 'tab' => 'registration_contract' ) );
    $rows = array();
    foreach ( $contracts as $entry ) {
      $entry_context = $this->get_registration_contract_related_context( $entry );
      $formation = isset( $entry_context['formation'] ) ? $entry_context['formation'] : null;
      $registration = isset( $entry_context['registration'] ) ? $entry_context['registration'] : null;
      $document = $this->get_registration_contract_document_info( $entry );
      $duration = $formation && ! empty( $formation->duration ) ? (string) $formation->duration : '—';
      if ( preg_match( '/^(\d{1,2}):(\d{2})$/', $duration, $m ) ) {
        $duration = sprintf( '%02dh%02d', (int) $m[1], (int) $m[2] );
      }
      $entry_session = isset( $entry_context['session'] ) ? $entry_context['session'] : null;
      $entry_format_val = ( $entry_session && ! empty( $entry_session->session_format ) ) ? (string) $entry_session->session_format
                        : ( ( $entry_session && ! empty( $entry_session->format ) ) ? (string) $entry_session->format
                        : ( ( $formation && ! empty( $formation->modality ) ) ? (string) $formation->modality : '—' ) );
      $rows[] = array(
        'entry' => $entry,
        'company_name' => $this->get_registration_contract_display_company_name( $entry, $entry_context ),
        'commanditaire_display' => $this->get_registration_contract_commanditaire_display_data( $entry, $entry_context ),
        'signature_lines' => $this->get_registration_contract_signature_stack( $entry, $document ),
        'formation_label' => ! empty( $entry->formation_title ) ? $entry->formation_title : ( $formation && ! empty( $formation->title ) ? $formation->title : '—' ),
        'contract_kind' => $this->get_registration_contract_kind_label( isset( $entry->commanditaire_type ) ? $entry->commanditaire_type : '' ),
        'dates_formation' => $this->acdc_format_seances_dates_for_display( $entry ),
        'format' => $entry_format_val,
        'duration' => $duration,
        'nad_info' => $this->get_registration_contract_nad_info( (int) $entry->id ),
      );
    }
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Convention/Contrat</h2>
        <p>Gestion des conventions et contrats de formation dans la section Inscription / Suivi.</p>
      </div>
      <div class="acdc-inline-wrap">
        <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( add_query_arg( array( 'action' => 'new' ), $base_url ) ); ?>">Créer une convention / un contrat</a>
      </div>
    </section>
    <?php if ( in_array( $action, array( 'new', 'edit', 'view' ), true ) ) : ?>
      <?php $this->render_front_registration_contract_form( $contract, 'view' === $action ); ?>
    <?php endif; ?>
    <div style="height:32px;"></div>
    <section class="acdc-section-head" style="margin-bottom:0;">
      <div><h3 style="margin:0;font-size:16px;color:#0f2c52;">Conventions et contrats enregistrés</h3></div>
    </section>
    <div class="acdc-panel acdc-mb-18">
      <form method="get" action="">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-registration-contract"><?php endif; ?>
        <input type="hidden" name="tab" value="registration_contract">
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
              <th>Commanditaire</th>
              <th>Signature</th>
              <th>Type</th>
              <th>Formation</th>
              <th>Dates de formation</th>
              <th>Format</th>
              <th>Durée (H)</th>
              <th>Analyse du besoin</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $rows as $row ) : ?>
              <?php $entry = $row['entry']; ?>
              <tr>
                <td>
                  <?php $this->render_registration_contract_commanditaire_markup( $row['commanditaire_display'] ); ?>
                </td>
                <td><?php echo $this->render_registration_contract_signature_badge( $entry ); ?></td>
                <?php
                $kind_label = $row['contract_kind'];
                $kind_color = 'Convention' === $kind_label ? '#1e4777' : '#6b21a8';
                $kind_bg    = 'Convention' === $kind_label ? '#e8f0fa' : '#f3e8ff';
                ?>
                <td><span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;color:<?php echo esc_attr($kind_color); ?>;background:<?php echo esc_attr($kind_bg); ?>;white-space:nowrap;"><?php echo esc_html( $kind_label ); ?></span></td>
                <td><?php echo esc_html( $row['formation_label'] ); ?></td>
                <td><?php echo nl2br( esc_html( $row['dates_formation'] ) ); ?></td>
                <td><?php echo esc_html( $row['format'] ); ?></td>
                <td><?php echo esc_html( $row['duration'] ); ?></td>
                <td><?php echo wp_kses_post( $row['nad_info']['html'] ); ?></td>
                <td class="acdc-actions-cell-icons">
                  <div class="acdc-groups-actions-inline">
                    <?php
                    $has_sig_req_f = ! empty( $entry->signature_request_id ) && (int) $entry->signature_request_id > 0;
                    /* Ne pas proposer « Renvoyer pour signature » sur une convention déjà signée. */
                    $is_signed_f   = ! empty( $entry->signed_document_url ) || ! empty( $entry->signed_document_path ) || in_array( (string) ( $entry->signature_status ?? '' ), array( 'completed', 'signe', 'signée' ), true );
                    $resend_url_f  = ( $has_sig_req_f && ! $is_signed_f ) ? wp_nonce_url( admin_url( 'admin-post.php?action=acdc_sig_portal_resend&request_id=' . (int) $entry->signature_request_id ), 'acdc_sig_portal_resend_' . (int) $entry->signature_request_id ) : '';
                    $send_sig_url_f = ! $has_sig_req_f ? wp_nonce_url( admin_url( 'admin-post.php?action=acdc_send_contract_for_signature&contract_id=' . (int) $entry->id ), 'acdc_send_contract_for_signature_' . (int) $entry->id ) : '';
                    $reg_url_f     = $this->portal_page_url( array( 'tab' => 'register_training', 'action' => 'bulk_from_contract', 'contract_id' => (int) $entry->id ) );
                    $delete_url_f  = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_delete_registration_contract&contract_id=' . (int) $entry->id . ( is_admin() ? '&page=acdc-of-registration-contract' : '' ) ), 'acdc_delete_registration_contract_' . (int) $entry->id );
                    ?>
                    <div class="acdc-row-menu" data-acdc-row-menu>
                      <button type="button" class="acdc-row-action-icon acdc-prospect-action-trigger" data-acdc-row-menu-toggle data-acdc-iconized="1" aria-haspopup="true" aria-expanded="false" title="Plus d'actions"><?php echo $this->render_inline_icon( 'more-horizontal', 25 ); ?><span class="acdc-action-hub-sr screen-reader-text">Plus d'actions</span></button>
                      <div class="acdc-row-menu-dropdown" data-acdc-row-menu-dropdown hidden>
                        <?php if ( $send_sig_url_f ) : ?><a href="<?php echo esc_url( $send_sig_url_f ); ?>">✍ Envoyer pour signature</a><?php endif; ?>
                        <?php if ( $resend_url_f ) : ?><a href="<?php echo esc_url( $resend_url_f ); ?>">✉ Renvoyer pour signature</a><?php endif; ?>
                        <a href="<?php echo esc_url( $reg_url_f ); ?>">➕ Inscrire en formation</a>
                      </div>
                    </div>
                    <a href="<?php echo esc_url( add_query_arg( array( 'action' => 'view', 'item_id' => (int) $entry->id ), $base_url ) ); ?>" class="acdc-row-action-icon acdc-row-view-link" data-acdc-iconized="1" title="Voir" aria-label="Voir"><?php echo $this->render_inline_icon( 'view', 25 ); ?><span class="acdc-action-hub-sr screen-reader-text">Voir</span></a>
                    <a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'item_id' => (int) $entry->id ), $base_url ) ); ?>" class="acdc-row-action-icon acdc-row-edit-link" data-acdc-iconized="1" title="Modifier" aria-label="Modifier"><?php echo $this->render_inline_icon( 'edit-pencil', 25 ); ?><span class="acdc-action-hub-sr screen-reader-text">Modifier</span></a>
                    <a href="<?php echo esc_url( $delete_url_f ); ?>" onclick="return confirm('Supprimer cette convention / ce contrat ?');" class="acdc-row-action-icon acdc-row-delete-link" data-acdc-iconized="1" title="Supprimer" aria-label="Supprimer"><?php echo $this->render_inline_icon( 'trash-bin', 25 ); ?><span class="acdc-action-hub-sr screen-reader-text">Supprimer</span></a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if ( empty( $rows ) ) : ?>
              <tr><td colspan="9">Aucune convention / aucun contrat enregistré.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
  }


  private function render_front_registration_contract_form( $contract = null, $readonly = false ) {
    $contract = $contract ?: $this->get_registration_contract_default_record();
    $state = ! $readonly ? $this->acdc_consume_form_state( 'registration_contract' ) : array();
    $contract = $this->acdc_apply_form_state_to_record( $contract, $state );
    $required_fields = $this->acdc_get_form_required_fields( $state );
    if ( isset( $contract->learner_ids ) && is_string( $contract->learner_ids ) ) {
      $contract->learner_ids = array_filter( array_map( 'absint', explode( ',', (string) $contract->learner_ids ) ) );
    }
    $params = $this->get_contract_params_options();
    $formations = $this->get_formations();
    $companies = $this->get_companies();
    $learners = $this->get_learners();
    $prospects = $this->get_prospects();
    $modes = $this->get_registration_contract_modes();
    $funding_options = $this->get_registration_contract_funding_options();
    $sections = json_decode( isset( $contract->additional_sections ) ? (string) $contract->additional_sections : '', true );
    if ( ! is_array( $sections ) || empty( $sections ) ) {
      $sections = $this->get_registration_contract_additional_sections_defaults();
    }
    $is_edit = ! empty( $contract->id );
    $is_admin_context = is_admin();
    $base_page = $is_admin_context ? 'acdc-of-registration-contract' : '';
    $cancel_url = $is_admin_context ? admin_url( 'admin.php?page=' . $base_page ) : $this->portal_page_url( array( 'tab' => 'registration_contract' ) );
    $params_url = $is_admin_context ? $this->admin_tab_url( 'contract_parameters' ) : $this->portal_page_url( array( 'tab' => 'contract_parameters' ) );
    $heading = $readonly ? 'Voir une convention / un contrat' : ( $is_edit ? 'Modifier une convention / un contrat' : 'Créer une convention / un contrat' );
    $this->acdc_render_form_validation_style( '.acdc-registration-contract-form' );
    $selected_source_prospect_id = $is_edit ? (int) ( $contract->source_prospect_id ?? 0 ) : ( isset( $_GET['prospect_id'] ) ? absint( wp_unslash( $_GET['prospect_id'] ) ) : 0 );

    // Charger la proposition depuis le GET dès que possible (indépendamment de prospect_id)
    $get_proposal_id = isset( $_GET['proposal_id'] ) ? absint( wp_unslash( $_GET['proposal_id'] ) ) : 0;
    $source_proposal = null;
    if ( ! $is_edit && $get_proposal_id && method_exists( $this, 'get_proposal' ) ) {
      $source_proposal = $this->get_proposal( $get_proposal_id );
      if ( $source_proposal ) {
        // Remonter prospect_id depuis la proposition si absent de l'URL
        if ( ! $selected_source_prospect_id && ! empty( $source_proposal->source_prospect_id ) ) {
          $selected_source_prospect_id = (int) $source_proposal->source_prospect_id;
        }
        // Injecter les dates de séances (toujours, même si prospect_id était déjà défini)
        if ( ! empty( $source_proposal->formation_dates ) && empty( $contract->seances_dates ) ) {
          $contract->seances_dates = (string) $source_proposal->formation_dates;
        }
      }
    }
    if ( ! $is_edit && $selected_source_prospect_id ) {
      $source_prospect = $this->get_prospect( $selected_source_prospect_id );
      /* $source_proposal est déjà chargé ci-dessus si proposal_id présent en GET */
      if ( $source_prospect ) {
        $contract_prefill = $this->acdc_get_contract_prefill_from_prospect( $source_prospect, $formations, $source_proposal );
        if ( ! empty( $contract_prefill['commanditaire_type'] ) ) {
          $contract->commanditaire_type = $contract_prefill['commanditaire_type'];
        }
        if ( ! empty( $contract_prefill['company_id'] ) ) {
          $contract->company_id = (int) $contract_prefill['company_id'];
          $companies = $this->get_companies();
        }
        if ( ! empty( $contract_prefill['formation_id'] ) ) {
          $contract->formation_id = (int) $contract_prefill['formation_id'];
        }
        if ( '' === (string) $contract->objectives_text && ! empty( $contract_prefill['objectives_text'] ) ) {
          $contract->objectives_text = $contract_prefill['objectives_text'];
        }
        if ( '' === (string) $contract->price_ht && ! empty( $contract_prefill['price_ht'] ) ) {
          $contract->price_ht = $contract_prefill['price_ht'];
        }
        if ( '' === (string) $contract->public_funding && ! empty( $contract_prefill['public_funding'] ) ) {
          $contract->public_funding = $contract_prefill['public_funding'];
        }

        // Fix pré-sélection : source_prospect_id → fait correspondre le select Entreprise/Indépendant
        if ( empty( $contract->source_prospect_id ) ) {
          $contract->source_prospect_id = $selected_source_prospect_id;
        }

        // Fix noms commanditaire pour prospects individuels (Particulier, Salarié)
        $is_indiv_prospect = $this->is_individual_prospect_profile( (string) ( $source_prospect->profile_type ?? '' ) );
        if ( $is_indiv_prospect ) {
          if ( '' === (string) ( $contract->commanditaire_last_name ?? '' ) ) {
            $contract->commanditaire_last_name = trim( (string) ( $source_prospect->last_name ?? '' ) );
          }
          if ( '' === (string) ( $contract->commanditaire_first_name ?? '' ) ) {
            $contract->commanditaire_first_name = trim( (string) ( $source_prospect->first_name ?? '' ) );
          }
        }

        // Fix signataire entreprise : injecter depuis le prospect si non rempli
        if ( ! $is_indiv_prospect ) {
          if ( '' === (string) ( $contract->commanditaire_signer_last_name ?? '' ) ) {
            $signer_last = trim( (string) ( $source_prospect->signer_last_name ?? ( $source_prospect->last_name ?? '' ) ) );
            $contract->commanditaire_signer_last_name = $signer_last;
          }
          if ( '' === (string) ( $contract->commanditaire_signer_first_name ?? '' ) ) {
            $signer_first = trim( (string) ( $source_prospect->signer_first_name ?? ( $source_prospect->first_name ?? '' ) ) );
            $contract->commanditaire_signer_first_name = $signer_first;
          }
        }
      }
    }
    $formation_map = array();
    foreach ( $formations as $formation ) {
      $formation_map[ (int) $formation->id ] = array(
        'objectives' => isset( $formation->objectives ) ? wp_strip_all_tags( (string) $formation->objectives ) : '',
        'price_ht'   => isset( $formation->price_ht ) ? (string) $formation->price_ht : '',
      );
    }
    $companies_by_name = array();
    foreach ( $companies as $company ) {
      $company_name_key = sanitize_title( isset( $company->name ) ? (string) $company->name : '' );
      if ( '' !== $company_name_key && ! isset( $companies_by_name[ $company_name_key ] ) ) {
        $companies_by_name[ $company_name_key ] = (int) $company->id;
      }
    }
    $formations_by_title = array();
    foreach ( $formations as $formation ) {
      $formation_title_key = sanitize_title( isset( $formation->title ) ? (string) $formation->title : '' );
      if ( '' !== $formation_title_key && ! isset( $formations_by_title[ $formation_title_key ] ) ) {
        $formations_by_title[ $formation_title_key ] = (int) $formation->id;
      }
    }
    $prospect_map = array();
    foreach ( $prospects as $prospect ) {
      $prospect_id = ! empty( $prospect->id ) ? (int) $prospect->id : 0;
      if ( ! $prospect_id ) {
        continue;
      }
      $profile_type = isset( $prospect->profile_type ) ? (string) $prospect->profile_type : '';
      $is_individual = $this->is_individual_prospect_profile( $profile_type );
      $company_name = isset( $prospect->company_name ) ? trim( (string) $prospect->company_name ) : '';
      $desired_training = isset( $prospect->desired_training ) ? trim( (string) $prospect->desired_training ) : '';
      $prospect_prefill = $this->acdc_get_contract_prefill_from_prospect( $prospect, $formations, null );
      $prospect_map[ $prospect_id ] = array(
        'label' => $this->get_prospect_display_name( $prospect ),
        'commanditaire_type' => $is_individual ? 'Particulier' : 'Entreprise',
        'company_id' => ! empty( $prospect_prefill['company_id'] ) ? (int) $prospect_prefill['company_id'] : ( ( ! $is_individual && '' !== $company_name && isset( $companies_by_name[ sanitize_title( $company_name ) ] ) ) ? (int) $companies_by_name[ sanitize_title( $company_name ) ] : 0 ),
        'formation_id' => ! empty( $prospect_prefill['formation_id'] ) ? (int) $prospect_prefill['formation_id'] : ( ( '' !== $desired_training && isset( $formations_by_title[ sanitize_title( $desired_training ) ] ) ) ? (int) $formations_by_title[ sanitize_title( $desired_training ) ] : 0 ),
        'objectives_text' => ! empty( $prospect_prefill['objectives_text'] ) ? (string) $prospect_prefill['objectives_text'] : '',
        'price_ht' => ! empty( $prospect_prefill['price_ht'] ) ? (string) $prospect_prefill['price_ht'] : '',
      );
    }
    /* B10 — learner_ids a pu être normalisé en TABLEAU plus haut (ligne ~965) : le relire
       via (string) donnait « Array » et vidait la sélection à l'édition (« Aucun apprenant
       sélectionné » alors que les apprenants étaient bien enregistrés). On gère les deux cas. */
    $selected_learner_ids = array();
    $raw_learner_ids = is_array( $contract->learner_ids )
      ? $contract->learner_ids
      : explode( ',', (string) $contract->learner_ids );
    foreach ( (array) $raw_learner_ids as $raw_learner_id ) {
      $learner_id = absint( trim( (string) $raw_learner_id ) );
      if ( $learner_id ) {
        $selected_learner_ids[] = $learner_id;
      }
    }
    ?>
    <form id="acdc-rc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acdc-company-profile-form acdc-contract-builder-form acdc-registration-contract-form" data-acdc-contract-form="1">
      <?php wp_nonce_field( 'acdc_save_registration_contract' ); ?>
      <input type="hidden" name="action" value="acdc_save_registration_contract">
      <?php if ( $is_admin_context ) : ?><input type="hidden" name="page" value="<?php echo esc_attr( $base_page ); ?>"><?php endif; ?>
      <input type="hidden" name="return_context" value="<?php echo $is_admin_context ? 'admin' : 'front'; ?>">
      <input type="hidden" name="return_after_save" value="edit">
      <input type="hidden" name="contract_id" value="<?php echo esc_attr( isset( $contract->id ) ? (int) $contract->id : 0 ); ?>">

      <section class="acdc-section-head">
        <div>
          <h2><?php echo esc_html( $heading ); ?></h2>
        </div>
        <?php if ( ! $readonly ) : ?>
        <div class="acdc-inline-wrap">
          <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $cancel_url ); ?>">Annuler</a>
          <button type="submit" form="acdc-rc-form" class="acdc-button acdc-button-primary"><?php echo $is_edit ? 'Modifier' : 'Valider'; ?></button>
        </div>
        <?php endif; ?>
      </section>

      <div class="acdc-panel acdc-profile-section">
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Paramètres de la convention</div>
          <div><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $params_url ); ?>">Accéder aux paramètres de la convention</a></div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Choix de la convention <span class="acdc-required">*</span></div>
          <div>
            <select name="registration_contract[generate_mode]"<?php echo $readonly ? ' disabled' : ''; ?> required>
              <option value="">Choisir une option</option>
              <?php foreach ( $modes as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( isset( $contract->generate_mode ) ? $contract->generate_mode : '', $value ); ?>><?php echo esc_html( $label ); ?></option>
              <?php endforeach; ?>
            </select>
            <?php echo $this->acdc_get_invalid_field_note( 'generate_mode', $required_fields ); ?>
          </div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Commanditaire <span class="acdc-required">*</span></div>
          <div>
            <?php
            $cmd_val      = isset( $contract->commanditaire_type ) ? $contract->commanditaire_type : '';
            $is_company   = in_array( $cmd_val, array( 'Entreprise', 'Indépendant' ), true );
            $is_indiv     = in_array( $cmd_val, array( 'Particulier', 'Apprenant', 'Salarié' ), true );
            $is_learner   = 'Apprenant' === $cmd_val;
            // Map prospects entreprise/indépendant : id → {name, signer_last, signer_first}
            // On utilise les PROSPECTS (pas les entreprises) car c'est là que sont les signataires
            $company_prospects = array_filter( $prospects, function( $p ) {
              return in_array( (string) ( $p->profile_type ?? '' ), array( 'Entreprise', 'Indépendant', 'Entreprise / indépendant' ), true );
            } );
            $company_map = array();
            foreach ( $company_prospects as $co ) {
              $co_name = trim( (string) ( $co->company_name ?? ( $co->first_name ?? '' ) . ' ' . ( $co->last_name ?? '' ) ) );
              $company_map[ (int) $co->id ] = array(
                'name'  => $co_name,
                'last'  => trim( (string) ( ( isset( $co->signer_last_name )  && '' !== trim( (string) $co->signer_last_name  ) ) ? $co->signer_last_name  : ( $co->last_name  ?? '' ) ) ),
                'first' => trim( (string) ( ( isset( $co->signer_first_name ) && '' !== trim( (string) $co->signer_first_name ) ) ? $co->signer_first_name : ( $co->first_name ?? '' ) ) ),
                'pid'   => (int) $co->id,
              );
            }
            // Map apprenants : id → {last, first}
            $learner_map = array();
            foreach ( $learners as $lr ) {
              $learner_map[ (int) $lr->id ] = array(
                'last'  => isset( $lr->usage_last_name ) && '' !== trim( (string) $lr->usage_last_name ) ? trim( (string) $lr->usage_last_name ) : trim( (string) ( $lr->last_name ?? '' ) ),
                'first' => trim( (string) ( $lr->first_name ?? '' ) ),
              );
            }
            // Map prospects : id → {last, first, type, company_id}
            $prospect_map_full = array();
            foreach ( $prospects as $pr ) {
              $prospect_map_full[ (int) $pr->id ] = array(
                'last'       => trim( (string) ( $pr->last_name ?? '' ) ),
                'first'      => trim( (string) ( $pr->first_name ?? '' ) ),
                'type'       => trim( (string) ( $pr->profile_type ?? '' ) ),
                'company_id' => (int) ( $pr->company_id ?? 0 ),
              );
            }
            ?>
            <select name="registration_contract[commanditaire_type]" id="acdc-rc-cmd-type"<?php echo $readonly ? ' disabled' : ''; ?> required>
              <option value="">Choisir une option</option>
              <option value="Particulier" <?php selected( $cmd_val, 'Particulier' ); ?>>Particulier</option>
              <option value="Apprenant"   <?php selected( $cmd_val, 'Apprenant' ); ?>>Apprenant</option>
              <option value="Salarié"     <?php selected( $cmd_val, 'Salarié' ); ?>>Salarié</option>
              <option value="Entreprise"  <?php selected( $cmd_val, 'Entreprise' ); ?>>Entreprise</option>
              <option value="Indépendant" <?php selected( $cmd_val, 'Indépendant' ); ?>>Indépendant</option>
            </select>

            <?php /* ── Individuel : sélecteur de prospect ou apprenant ──────── */ ?>
            <?php /* ── Individuel : sélecteur filtré par type ─────────────── */ ?>
            <div id="acdc-cmd-indiv-wrap" style="<?php echo $is_indiv ? '' : 'display:none;'; ?>margin-top:10px;">
              <div id="acdc-cmd-indiv-label" class="acdc-contract-label" style="margin-bottom:4px;">Prospect / Personne</div>

              <?php /* Select Apprenants — affiché uniquement pour type Apprenant */ ?>
              <select id="acdc-cmd-learner-select" style="<?php echo $is_learner ? '' : 'display:none;'; ?>margin-bottom:10px;"<?php echo $readonly ? ' disabled' : ''; ?>>
                <option value="">— Sélectionner un apprenant</option>
                <?php foreach ( $learners as $lr ) :
                  $lr_label = trim( ( $lr->first_name ?? '' ) . ' ' . ( isset( $lr->usage_last_name ) && '' !== trim( (string) $lr->usage_last_name ) ? $lr->usage_last_name : ( $lr->last_name ?? '' ) ) );
                  $lr_last  = isset( $lr->usage_last_name ) && '' !== trim( (string) $lr->usage_last_name ) ? $lr->usage_last_name : ( $lr->last_name ?? '' );
                ?>
                  <option value="<?php echo esc_attr( $lr->id ); ?>"><?php echo esc_html( $lr_label ); ?></option>
                <?php endforeach; ?>
              </select>

              <?php /* Select Prospects (Particulier / Salarié) — avec data-profile pour filtrage JS */ ?>
              <select id="acdc-cmd-prospect-select" style="<?php echo ( $is_indiv && ! $is_learner ) ? '' : 'display:none;'; ?>margin-bottom:10px;"<?php echo $readonly ? ' disabled' : ''; ?>>
                <option value="">— Sélectionner</option>
                <?php foreach ( $prospects as $pr ) :
                  $pr_type  = (string) ( $pr->profile_type ?? '' );
                  $pr_label = $this->get_prospect_display_name( $pr );
                ?>
                  <option value="<?php echo esc_attr( $pr->id ); ?>"
                    data-profile="<?php echo esc_attr( $pr_type ); ?>"
                    style="<?php echo in_array( $pr_type, array( 'Particulier', 'Salarié' ), true ) ? '' : 'display:none;'; ?>"
                    <?php echo in_array( $pr_type, array( 'Particulier', 'Salarié' ), true ) ? '' : 'disabled'; ?>
                    <?php selected( $selected_source_prospect_id, (int) $pr->id ); ?>>
                    <?php echo esc_html( $pr_label ); ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div>
                  <div class="acdc-contract-label" style="margin-bottom:4px;">Nom <span class="acdc-required">*</span></div>
                  <input type="text" id="acdc-cmd-last" name="registration_contract[commanditaire_last_name]"
                    value="<?php echo esc_attr( isset( $contract->commanditaire_last_name ) ? $contract->commanditaire_last_name : '' ); ?>"
                    <?php echo $readonly ? ' readonly' : ''; ?> placeholder="Nom">
                </div>
                <div>
                  <div class="acdc-contract-label" style="margin-bottom:4px;">Prénom <span class="acdc-required">*</span></div>
                  <input type="text" id="acdc-cmd-first" name="registration_contract[commanditaire_first_name]"
                    value="<?php echo esc_attr( isset( $contract->commanditaire_first_name ) ? $contract->commanditaire_first_name : '' ); ?>"
                    <?php echo $readonly ? ' readonly' : ''; ?> placeholder="Prénom">
                </div>
              </div>
            </div>
            <div id="acdc-cmd-company-wrap" style="<?php echo $is_company ? '' : 'display:none;'; ?>margin-top:10px;">
              <div id="acdc-cmd-company-label" class="acdc-contract-label" style="margin-bottom:4px;">Entreprise / Organisation <span class="acdc-required">*</span></div>
              <?php
              // Valeur sélectionnée : source_prospect_id si entreprise/indépendant
              $selected_company_prospect_id = (int) ( $contract->source_prospect_id ?? 0 );
              ?>
              <select id="acdc-cmd-company-select" name="registration_contract[source_prospect_id]"<?php echo $readonly ? ' disabled' : ''; ?> style="margin-bottom:10px;">
                <option value="0">— Sélectionner une entreprise / organisation</option>
                <?php foreach ( $company_prospects as $co ) : ?>
                  <?php
                  $co_name = trim( (string) ( $co->company_name ?? ( ( $co->first_name ?? '' ) . ' ' . ( $co->last_name ?? '' ) ) ) );
                  ?>
                  <option value="<?php echo esc_attr( $co->id ); ?>"
                    data-profile="<?php echo esc_attr( $co->profile_type ?? '' ); ?>"
                    <?php selected( $selected_company_prospect_id, (int) $co->id ); ?>>
                    <?php echo esc_html( $co_name ); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div>
                  <div class="acdc-contract-label" style="margin-bottom:4px;">Nom du signataire <span class="acdc-required">*</span></div>
                  <input type="text" id="acdc-cmd-signer-last" name="registration_contract[commanditaire_signer_last_name]"
                    value="<?php echo esc_attr( isset( $contract->commanditaire_signer_last_name ) ? $contract->commanditaire_signer_last_name : '' ); ?>"
                    <?php echo $readonly ? ' readonly' : ''; ?> placeholder="Nom">
                </div>
                <div>
                  <div class="acdc-contract-label" style="margin-bottom:4px;">Prénom du signataire <span class="acdc-required">*</span></div>
                  <input type="text" id="acdc-cmd-signer-first" name="registration_contract[commanditaire_signer_first_name]"
                    value="<?php echo esc_attr( isset( $contract->commanditaire_signer_first_name ) ? $contract->commanditaire_signer_first_name : '' ); ?>"
                    <?php echo $readonly ? ' readonly' : ''; ?> placeholder="Prénom">
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php /* ── Prospect source : synchronisé automatiquement ───────────── */ ?>
        <?php /* ACDC 3.21.08 — source_prospect_id alimenté automatiquement depuis Commanditaire */ ?>
        <input type="hidden" id="acdc-source-prospect-id" name="registration_contract[source_prospect_id]" value="<?php echo esc_attr( $selected_source_prospect_id ?: 0 ); ?>">
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Apprenants à ajouter</div>
          <div>
            <div class="acdc-registration-with-action" style="align-items:flex-start;gap:10px;">
              <div style="flex:1;min-width:0;">
                <?php if ( ! empty( $learners ) ) : ?>
                  <div class="acdc-contract-learners-search" data-acdc-learners-search>
                    <input type="text" class="acdc-contract-learners-search-input" placeholder="Rechercher un apprenant par prénom ou nom" data-acdc-learners-search-input<?php echo $readonly ? ' readonly' : ''; ?>>
                    <div class="acdc-contract-selected-learners" data-acdc-selected-learners></div>
                    <div class="acdc-contract-learners-picker" data-acdc-learners-results style="border:1px solid #d7dbe3;border-radius:10px;padding:8px 12px;max-height:220px;overflow:auto;background:#fff;display:none;">
                      <?php foreach ( $learners as $learner ) : ?>
                        <?php $learner_label = trim( $learner->first_name . ' ' . $learner->usage_last_name ); ?>
                        <label class="acdc-contract-learner-option" data-acdc-learner-option data-search-text="<?php echo esc_attr( strtolower( $learner_label ) ); ?>" style="display:flex;align-items:center;gap:8px;padding:6px 0;<?php echo in_array( (int) $learner->id, $selected_learner_ids, true ) ? '' : 'display:none;'; ?>">
                          <input type="checkbox" name="registration_contract[learner_ids][]" value="<?php echo esc_attr( $learner->id ); ?>" data-acdc-learner-checkbox data-learner-label="<?php echo esc_attr( $learner_label ); ?>" <?php checked( in_array( (int) $learner->id, $selected_learner_ids, true ) ); ?><?php echo $readonly ? ' disabled' : ''; ?>>
                          <span><?php echo esc_html( $learner_label ); ?></span>
                        </label>
                      <?php endforeach; ?>
                      <p class="acdc-help" data-acdc-no-learner-match style="margin:0;display:none;">Aucun apprenant ne correspond à votre recherche.</p>
                    </div>
                  </div>
                  <script>
                  document.addEventListener('DOMContentLoaded', function(){
                    document.querySelectorAll('[data-acdc-learners-search]').forEach(function(wrapper){
                      if (wrapper.dataset.acdcBound === '1') { return; }
                      wrapper.dataset.acdcBound = '1';
                      var input = wrapper.querySelector('[data-acdc-learners-search-input]');
                      var results = wrapper.querySelector('[data-acdc-learners-results]');
                      var chips = wrapper.querySelector('[data-acdc-selected-learners]');
                      var options = Array.prototype.slice.call(wrapper.querySelectorAll('[data-acdc-learner-option]'));
                      var noMatch = wrapper.querySelector('[data-acdc-no-learner-match]');
                      function refreshSelected(){
                        chips.innerHTML = '';
                        var selected = 0;
                        options.forEach(function(option){
                          var checkbox = option.querySelector('[data-acdc-learner-checkbox]');
                          if (!checkbox) { return; }
                          if (checkbox.checked) {
                            selected++;
                            var chip = document.createElement('button');
                            chip.type = 'button';
                            chip.className = 'acdc-contract-learner-chip';
                            chip.textContent = checkbox.getAttribute('data-learner-label') + ' ×';
                            chip.disabled = checkbox.disabled;
                            chip.addEventListener('click', function(){
                              if (checkbox.disabled) { return; }
                              checkbox.checked = false;
                              refreshSelected();
                              filterOptions(input.value || '');
                            });
                            chips.appendChild(chip);
                          }
                        });
                        if (!selected) {
                          var empty = document.createElement('div');
                          empty.className = 'acdc-help';
                          empty.textContent = 'Aucun apprenant sélectionné pour le moment.';
                          chips.appendChild(empty);
                        }
                      }
                      function filterOptions(term){
                        term = (term || '').toLowerCase().trim();
                        var visible = 0;
                        options.forEach(function(option){
                          var checkbox = option.querySelector('[data-acdc-learner-checkbox]');
                          var text = option.getAttribute('data-search-text') || '';
                          var show = checkbox.checked || (term.length >= 1 && text.indexOf(term) !== -1);
                          option.style.display = show ? 'flex' : 'none';
                          if (show) { visible++; }
                        });
                        results.style.display = (term.length >= 1 || visible > 0) ? 'block' : 'none';
                        if (noMatch) {
                          noMatch.style.display = (term.length >= 1 && visible === 0) ? 'block' : 'none';
                        }
                      }
                      options.forEach(function(option){
                        var checkbox = option.querySelector('[data-acdc-learner-checkbox]');
                        if (!checkbox) { return; }
                        checkbox.addEventListener('change', function(){
                          refreshSelected();
                          filterOptions(input.value || '');
                        });
                      });
                      input.addEventListener('input', function(){
                        filterOptions(input.value || '');
                      });
                      refreshSelected();
                      filterOptions('');
                    });
                  });
                  </script>
                <?php else : ?>
                  <div class="acdc-contract-learners-picker" style="border:1px solid #d7dbe3;border-radius:10px;padding:10px 12px;background:#fff;">
                    <p class="acdc-help" style="margin:0;">Aucun apprenant n’est encore disponible dans le répertoire.</p>
                  </div>
                <?php endif; ?>
              </div>
              <?php if ( ! $readonly ) : ?><?php $new_learner_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-learners&action=new' ) : $this->portal_page_url( array( 'tab' => 'learners', 'action' => 'new' ) ); ?><a class="acdc-registration-action-link" href="<?php echo esc_url( $new_learner_url ); ?>" title="Créer un apprenant" target="_blank" rel="noopener"><?php echo $this->render_inline_icon( 'create', 16 ); ?></a><?php endif; ?>
            </div>
            <p class="acdc-help">Recherchez un apprenant, puis sélectionnez-le. Les apprenants déjà choisis restent visibles sous le champ. Si aucun apprenant n’existe encore, utilisez le bouton “+” pour en créer un.</p>
            <style>
              .acdc-contract-learners-search-input{width:100%;height:40px;border:1px solid #d7dbe3;border-radius:10px;padding:0 12px;margin-bottom:10px;}
              .acdc-contract-selected-learners{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px;}
              .acdc-contract-learner-chip{border:1px solid #d7dbe3;background:#fff;border-radius:999px;padding:6px 10px;cursor:pointer;}
            </style>
          </div>
        </div>



        <?php if ( ! $readonly ) : ?>
        <div class="acdc-contract-grid" id="acdc-new-learners-section">
          <div class="acdc-contract-label" style="padding-top:8px;">
            Nouveaux apprenants &agrave; cr&eacute;er
            <span style="display:block;font-size:11px;font-weight:400;color:#4b5d76;margin-top:2px;">Ces apprenants seront cr&eacute;&eacute;s dans le r&eacute;pertoire Apprenants et li&eacute;s &agrave; cette convention.</span>
          </div>
          <div>
            <div id="acdc-new-learners-rows">
              <?php
              $nl_state = ! empty( $contract->_new_learners_state ) ? $contract->_new_learners_state : array();
              if ( ! empty( $nl_state ) ) : foreach ( $nl_state as $nli => $nll ) : ?>
              <div class="acdc-new-learner-row" style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:8px;align-items:center;margin-bottom:8px;">
                <input type="text"  name="registration_contract[new_learners][<?php echo (int) $nli; ?>][first_name]" value="<?php echo esc_attr( $nll['first_name'] ?? '' ); ?>" placeholder="Pr&eacute;nom *">
                <input type="text"  name="registration_contract[new_learners][<?php echo (int) $nli; ?>][last_name]"  value="<?php echo esc_attr( $nll['last_name']  ?? '' ); ?>" placeholder="Nom *">
                <input type="email" name="registration_contract[new_learners][<?php echo (int) $nli; ?>][email]"      value="<?php echo esc_attr( $nll['email']      ?? '' ); ?>" placeholder="Email">
                <button type="button" class="acdc-button acdc-button-soft" style="padding:0 10px;min-width:36px;" onclick="this.closest('.acdc-new-learner-row').remove();" title="Supprimer">&times;</button>
              </div>
              <?php endforeach; endif; ?>
            </div>
            <button type="button" class="acdc-button acdc-button-soft" id="acdc-add-new-learner" style="margin-top:4px;">+ Ajouter un apprenant</button>
            <script>
            document.addEventListener('DOMContentLoaded', function(){
              var btn = document.getElementById('acdc-add-new-learner');
              var rows = document.getElementById('acdc-new-learners-rows');
              if (!btn || !rows) { return; }
              function addRow(){
                var idx = rows.querySelectorAll('.acdc-new-learner-row').length;
                var div = document.createElement('div');
                div.className = 'acdc-new-learner-row';
                div.style.cssText = 'display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:8px;align-items:center;margin-bottom:8px;';
                div.innerHTML = '<input type="text" name="registration_contract[new_learners]['+idx+'][first_name]" placeholder="Prénom *">'
                  + '<input type="text" name="registration_contract[new_learners]['+idx+'][last_name]" placeholder="Nom *">'
                  + '<input type="email" name="registration_contract[new_learners]['+idx+'][email]" placeholder="Email">'
                  + '<button type="button" class="acdc-button acdc-button-soft" style="padding:0 10px;min-width:36px;" title="Supprimer">×</button>';
                div.querySelector('button').addEventListener('click', function(){ div.remove(); });
                rows.appendChild(div);
                div.querySelector('input').focus();
              }
              btn.addEventListener('click', addRow);
            });
            </script>
          </div>
        </div>
        <?php endif; ?>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Formation <span class="acdc-required">*</span></div>
          <div>
            <select name="registration_contract[formation_id]" id="acdc-registration-contract-formation"<?php echo $readonly ? ' disabled' : ''; ?> required>
              <option value="0">—</option>
              <?php foreach ( $formations as $formation ) : ?>
                <option value="<?php echo esc_attr( $formation->id ); ?>" <?php selected( (int) ( $contract->formation_id ?? 0 ), (int) $formation->id ); ?>><?php echo esc_html( $this->format_formation_option_label( $formation ) ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="acdc-contract-inline-pair">
          <div class="acdc-contract-grid">
            <?php
            // ── Widget multi-dates séances ───────────────────────────────
            $raw_seances = isset( $contract->seances_dates ) ? (string) $contract->seances_dates : '';
            // Fallback : si seances_dates vide mais start_date/end_date renseignées → pré-remplir avec start_date
            if ( '' === $raw_seances && ! empty( $contract->start_date ) ) {
              $raw_seances = $contract->start_date;
            }
            $seances_arr = array();
            foreach ( array_filter( array_map( 'trim', explode( ',', $raw_seances ) ) ) as $d ) {
              if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
                $seances_arr[] = $d;
              }
            }
            $seances_json = wp_json_encode( $seances_arr );
            ?>
            <div class="acdc-contract-label">Dates des séances <span class="acdc-required">*</span></div>
            <div>
              <input type="hidden" name="registration_contract[seances_dates]" id="acdc-contract-seances-hidden" value="<?php echo esc_attr( implode( ',', $seances_arr ) ); ?>">
              <span id="acdc-contract-seances-chips" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;min-height:0;"></span>
              <?php if ( ! $readonly ) : ?>
              <span style="display:flex;gap:6px;align-items:center;">
                <input type="date" id="acdc-contract-seance-picker"
                       style="height:40px;border-radius:10px;border:1.5px solid #dce4ec;padding:0 12px;font-size:13px;color:#0f2c52;">
                <button type="button" id="acdc-contract-seance-add"
                        style="height:40px;padding:0 16px;background:#0f2c52;color:#fff;border:none;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">
                  + Ajouter
                </button>
              </span>
              <?php endif; ?>
              <p class="acdc-help" style="margin-top:6px;">
                La date de début et de fin de la convention seront calculées automatiquement à partir de ces séances.
              </p>
            </div>
          </div>
          <!-- ACDC 3.21.17 — Délai envoi analyses du besoin -->
          <div class="acdc-contract-grid" style="background:#fbf8f7;border:1px solid #f0e6dc;border-radius:8px;padding:10px 14px;margin-bottom:4px;">
            <div class="acdc-contract-label" style="font-weight:700;color:#0f2c52;">
              Délai d'envoi des analyses du besoin
              <span style="display:block;font-size:11px;font-weight:400;color:#4b5d76;margin-top:2px;">Nombre de jours après la signature avant l'envoi des emails. 0 = envoi immédiat.</span>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
              <input type="number" name="registration_contract[nad_send_delay_days]"
                value="<?php echo esc_attr( isset( $contract->nad_send_delay_days ) ? (int) $contract->nad_send_delay_days : 0 ); ?>"
                min="0" max="365" style="width:80px;height:40px;border:1.5px solid #dce4ec;border-radius:8px;padding:0 10px;font-size:15px;"
                <?php echo $readonly ? 'readonly' : ''; ?>>
              <span style="font-size:14px;color:#4b5d76;">jour(s) après la signature</span>
            </div>
          </div>
        </div>
        <script>
        (function(){
          var initialDates = <?php echo $seances_json; ?>;
          var dates = initialDates.slice();
          var chipsEl  = document.getElementById('acdc-contract-seances-chips');
          var hiddenEl = document.getElementById('acdc-contract-seances-hidden');
          var picker   = document.getElementById('acdc-contract-seance-picker');
          var addBtn   = document.getElementById('acdc-contract-seance-add');
          if(!chipsEl || !hiddenEl){return;}
          var months = ['jan.','fév.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'];
          function fmtDate(iso){var p=iso.split('-');return p.length===3?p[2]+' '+months[parseInt(p[1],10)-1]+' '+p[0]:iso;}
          function render(){
            chipsEl.innerHTML='';
            dates.forEach(function(d,i){
              var chip=document.createElement('span');
              chip.style.cssText='display:inline-flex;align-items:center;gap:5px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:20px;padding:3px 10px 3px 12px;font-size:12px;font-weight:600;color:#1e3a8a;white-space:nowrap;';
              chip.innerHTML='<span>Séance '+(i+1)+' — '+fmtDate(d)+'</span>';
              <?php if(!$readonly):?>
              var rm=document.createElement('button');rm.type='button';rm.innerHTML='×';
              rm.style.cssText='background:none;border:none;font-size:15px;cursor:pointer;color:#6366f1;padding:0 0 1px;';
              rm.addEventListener('click',function(){dates.splice(i,1);render();});
              chip.appendChild(rm);
              <?php endif;?>
              chipsEl.appendChild(chip);
            });
            hiddenEl.value=dates.join(',');
          }
          if(addBtn&&picker){
            addBtn.addEventListener('click',function(){var v=picker.value;if(!v)return;if(dates.indexOf(v)===-1){dates.push(v);dates.sort();}render();picker.value='';picker.focus();});
            picker.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();addBtn.click();}});
          }
          render();
        })();
        </script>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Référence légale à afficher <span class="acdc-required">*</span></div>
          <div><textarea name="registration_contract[legal_reference]" rows="4"<?php echo $readonly ? ' readonly' : ''; ?>><?php echo esc_textarea( isset( $contract->legal_reference ) ? $contract->legal_reference : $params['legal_reference'] ); ?></textarea></div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Objectifs de la formation <span class="acdc-required">*</span></div>
          <div>
            <textarea name="registration_contract[objectives_text]" id="acdc-registration-contract-objectives" rows="5" placeholder="Objectifs de la formation"<?php echo $readonly ? ' readonly' : ''; ?>><?php echo esc_textarea( isset( $contract->objectives_text ) ? $contract->objectives_text : '' ); ?></textarea>
            <p class="acdc-help">Un objectif de formation se définit par les compétences à maîtriser après la formation.<br>Pour les CFA et l’alternance, ces objectifs doivent être formulés en termes de compétences professionnelles, capacités à acquérir ou certifications visées.</p>
          </div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Accessibilité et handicap <span class="acdc-required">*</span></div>
          <div><?php $this->render_registration_contract_rich_field( 'registration_contract[accessibility_handicap]', isset( $contract->accessibility_handicap ) ? $contract->accessibility_handicap : '', $readonly, 6, 'Accessibilité et handicap' ); ?></div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Moyens matériels et environnement de la formation <span class="acdc-required">*</span></div>
          <div><?php $this->render_registration_contract_rich_field( 'registration_contract[material_environment]', isset( $contract->material_environment ) ? $contract->material_environment : '', $readonly, 5, 'Moyens matériels et environnement de la formation' ); ?></div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Modalité de mise en œuvre, de suivi et d’évaluation de la formation <span class="acdc-required">*</span></div>
          <div><?php $this->render_registration_contract_rich_field( 'registration_contract[implementation_followup_evaluation]', isset( $contract->implementation_followup_evaluation ) ? $contract->implementation_followup_evaluation : '', $readonly, 5, 'Modalité de mise en œuvre, de suivi et d’évaluation de la formation' ); ?></div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Annulation/Report, Dédommagement, Réparation ou Dédit <span class="acdc-required">*</span></div>
          <div><?php $this->render_registration_contract_rich_field( 'registration_contract[cancellation_terms]', isset( $contract->cancellation_terms ) ? $contract->cancellation_terms : '', $readonly, 6, 'Annulation' ); ?></div>
        </div>
        <div class="acdc-contract-inline-pair">
          <div class="acdc-contract-grid">
            <div class="acdc-contract-label">Tarif de l'action de formation (€ HT) <span class="acdc-required">*</span></div>
            <div><input type="text" name="registration_contract[price_ht]" id="acdc-registration-contract-price" value="<?php echo esc_attr( isset( $contract->price_ht ) ? $contract->price_ht : '' ); ?>" placeholder="Tarif HT"<?php echo $readonly ? ' readonly' : ''; ?>></div>
          </div>
          <div class="acdc-contract-grid">
            <div class="acdc-contract-label">Taux de TVA associé (%) <span class="acdc-required">*</span></div>
            <div><input type="text" name="registration_contract[vat_rate]" value="<?php echo esc_attr( isset( $contract->vat_rate ) ? $contract->vat_rate : $this->get_default_vat_rate_for_contract() ); ?>"<?php echo $readonly ? ' readonly' : ''; ?>></div>
          </div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Acompte</div>
          <div>
            <label class="acdc-switch">
              <input type="checkbox" name="registration_contract[deposit_enabled]" value="1" <?php checked( ! empty( $contract->deposit_enabled ) ); ?><?php echo $readonly ? ' disabled' : ''; ?>>
              <span class="acdc-switch-slider"></span>
            </label>
          </div>
        </div>
        <div class="acdc-contract-grid acdc-contract-conditional-row" data-acdc-toggle-row="deposit_enabled">
          <div class="acdc-contract-label">Montant de l'acompte (€ HT)</div>
          <div><input type="text" name="registration_contract[deposit_amount_ht]" value="<?php echo esc_attr( isset( $contract->deposit_amount_ht ) ? $contract->deposit_amount_ht : '' ); ?>" placeholder="Montant de l'acompte (€ HT)"<?php echo $readonly ? ' readonly' : ''; ?>></div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Financement public</div>
          <div>
            <select name="registration_contract[public_funding]" id="acdc-rc-public-funding"<?php echo $readonly ? ' disabled' : ''; ?>>
              <?php foreach ( $funding_options as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( isset( $contract->public_funding ) ? $contract->public_funding : '', $value ); ?>><?php echo esc_html( $label ); ?></option>
              <?php endforeach; ?>
            </select>
            <?php $show_opco = ( isset( $contract->public_funding ) && 'Oui' === $contract->public_funding ); ?>
            <div id="acdc-rc-opco-wrap" style="<?php echo $show_opco ? '' : 'display:none;'; ?>margin-top:10px;">
              <div class="acdc-contract-label" style="margin-bottom:4px;">Nom du financeur (OPCO, CPIR…)</div>
              <select name="registration_contract[public_funding_name]" id="acdc-rc-opco-select"<?php echo $readonly ? ' disabled' : ''; ?>>
                <option value="">— Sélectionner un financeur</option>
                <?php
                $opco_list = array(
                  'AFDAS'           => 'AFDAS – Culture, Communication, Médias, Loisirs',
                  'Atlas'           => 'ATLAS – Banque, Finance, Conseil',
                  'Constructys'     => 'Constructys – BTP',
                  'AKTO'            => 'AKTO – Tertiaire de proximité',
                  'EP'              => 'EP – Entreprises de Proximité (artisanat)',
                  'OCAPIAT'         => 'OCAPIAT – Agriculture, Pêche, Industrie alimentaire',
                  'Opco2i'          => 'Opco 2i – Industrie',
                  'Opco Mobilités'  => 'Opco Mobilités – Transport, Logistique',
                  'Opco Santé'      => 'Opco Santé – Santé, Social, Médico-social',
                  'Uniformation'    => 'Uniformation – Économie sociale et solidaire',
                  'CPF'             => 'CPF – Compte Personnel de Formation',
                  'Pôle emploi'     => 'France Travail (ex-Pôle emploi)',
                  'Région'          => 'Conseil Régional',
                  'AGEFIPH'         => 'AGEFIPH – Handicap',
                  'Autre'           => 'Autre financeur',
                );
                $saved_opco = isset( $contract->public_funding_name ) ? (string) $contract->public_funding_name : '';
                foreach ( $opco_list as $key => $lbl ) :
                ?>
                  <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $saved_opco, $key ); ?>><?php echo esc_html( $lbl ); ?></option>
                <?php endforeach; ?>
              </select>
              <?php if ( 'Autre' === $saved_opco || '' === $saved_opco ) : ?>
              <input type="text" name="registration_contract[public_funding_name_custom]" id="acdc-rc-opco-custom"
                value="<?php echo esc_attr( isset( $contract->public_funding_name_custom ) ? $contract->public_funding_name_custom : '' ); ?>"
                placeholder="Préciser le nom du financeur"
                style="margin-top:8px;<?php echo ( 'Autre' === $saved_opco ) ? '' : 'display:none;'; ?>"
                <?php echo $readonly ? ' readonly' : ''; ?>>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Frais de transport</div>
          <div>
            <label class="acdc-switch">
              <input type="checkbox" name="registration_contract[transport_fees_enabled]" value="1" <?php checked( ! empty( $contract->transport_fees_enabled ) ); ?><?php echo $readonly ? ' disabled' : ''; ?>>
              <span class="acdc-switch-slider"></span>
            </label>
          </div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Frais de restauration et / ou hébergement</div>
          <div>
            <label class="acdc-switch">
              <input type="checkbox" name="registration_contract[meal_fees_enabled]" value="1" <?php checked( ! empty( $contract->meal_fees_enabled ) ); ?><?php echo $readonly ? ' disabled' : ''; ?>>
              <span class="acdc-switch-slider"></span>
            </label>
          </div>
        </div>
        <div class="acdc-contract-grid acdc-contract-conditional-row" data-acdc-toggle-row="meal_fees_enabled">
          <div class="acdc-contract-label">Montant des frais de restauration et / ou hébergement (€ HT)</div>
          <div><input type="text" name="registration_contract[meal_fees_amount_ht]" value="<?php echo esc_attr( isset( $contract->meal_fees_amount_ht ) ? $contract->meal_fees_amount_ht : '' ); ?>" placeholder="Montant des frais de restauration et / ou hébergement (€ HT)"<?php echo $readonly ? ' readonly' : ''; ?>></div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Dispositions financières <span class="acdc-required">*</span></div>
          <div><?php $this->render_registration_contract_rich_field( 'registration_contract[financial_provisions]', isset( $contract->financial_provisions ) ? $contract->financial_provisions : '', $readonly, 4, 'Dispositions financières' ); ?></div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Modalités de règlement <span class="acdc-required">*</span></div>
          <div><?php $this->render_registration_contract_rich_field( 'registration_contract[payment_terms]', isset( $contract->payment_terms ) ? $contract->payment_terms : '', $readonly, 4, 'Modalités de règlement' ); ?></div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Différends éventuels <span class="acdc-required">*</span></div>
          <div><?php $this->render_registration_contract_rich_field( 'registration_contract[disputes_terms]', isset( $contract->disputes_terms ) ? $contract->disputes_terms : '', $readonly, 4, 'Différends éventuels' ); ?></div>
        </div>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Délai de rétractation (jours)</div>
          <div>
            <input type="number" name="registration_contract[withdrawal_delay_days]" value="<?php echo esc_attr( isset( $contract->withdrawal_delay_days ) ? $contract->withdrawal_delay_days : '14' ); ?>"<?php echo $readonly ? ' readonly' : ''; ?>>
            <p class="acdc-help">Ne s’applique que pour les contrats de formation (commanditaire Particulier)</p>
          </div>
        </div>
      </div>

      <div class="acdc-panel acdc-profile-section">
        <h3>Sections supplémentaires</h3>
        <div id="acdc-registration-contract-sections">
          <?php foreach ( $sections as $index => $section ) : ?>
            <div class="acdc-contract-section-card">
              <div class="acdc-contract-section-head">
                <span>#<?php echo esc_html( (string) ( $index + 1 ) ); ?> Section</span>
                <?php if ( ! $readonly ) : ?><button type="button" class="acdc-contract-remove-section">Supprimer</button><?php endif; ?>
              </div>
              <div class="acdc-contract-section-body">
                <div class="acdc-contract-grid">
                  <div class="acdc-contract-label">Titre de la section <span class="acdc-required">*</span></div>
                  <div><input type="text" name="registration_contract[additional_sections][<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( isset( $section['title'] ) ? $section['title'] : '' ); ?>"<?php echo $readonly ? ' readonly' : ''; ?>></div>
                </div>
                <div class="acdc-contract-grid">
                  <div class="acdc-contract-label">Contenu de la section <span class="acdc-required">*</span></div>
                  <div><?php $this->render_registration_contract_rich_field( 'registration_contract[additional_sections][' . $index . '][content]', isset( $section['content'] ) ? $section['content'] : '', $readonly, 4, 'Contenu de la section' ); ?></div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if ( ! $readonly ) : ?><p><button type="button" class="acdc-button acdc-button-soft" id="acdc-add-registration-contract-section">Ajouter une section</button></p><?php endif; ?>
      </div>

      <div class="acdc-form-actions">
        <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $cancel_url ); ?>">Annuler</a>
        <?php if ( ! $readonly ) : ?><button type="submit" class="acdc-button acdc-button-primary"><?php echo $is_edit ? 'Modifier' : 'Valider'; ?></button><?php endif; ?>
      </div>
    </form>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
      document.querySelectorAll('.acdc-contract-rich-toolbar button').forEach(function(btn){
        btn.addEventListener('click', function(){
          var wrap = btn.closest('[data-acdc-rich-editor]');
          if(!wrap){ return; }
          var area = wrap.querySelector('textarea');
          if(!area || area.hasAttribute('readonly')){ return; }
          var start = area.selectionStart || 0;
          var end = area.selectionEnd || 0;
          var selected = area.value.substring(start, end);
          var replacement = selected;
          if(btn.hasAttribute('data-acdc-rich-link')){
            var url = window.prompt('URL du lien :', 'https://');
            if(!url){ return; }
            replacement = '<a href="' + url + '">' + (selected || 'Texte du lien') + '</a>';
          } else {
            var tag = btn.getAttribute('data-acdc-rich-tag');
            replacement = '<' + tag + '>' + (selected || 'Texte') + '</' + tag + '>';
          }
          area.setRangeText(replacement, start, end, 'end');
          area.focus();
        });
      });

      // ── ACDC 3.21.08 — Dynamique Commanditaire / Prospect source ──────────────
      var acdcCompanySigners  = <?php echo wp_json_encode( $company_map ); ?>;
      var acdcLearnerData     = <?php echo wp_json_encode( $learner_map ); ?>;
      var acdcProspectData    = <?php echo wp_json_encode( $prospect_map_full ); ?>;
      (function() {
        var typeSelect = document.getElementById('acdc-rc-cmd-type');
        if (!typeSelect) { return; }
        var COMPANY_TYPES = ['Entreprise', 'Indépendant'];
        var INDIV_TYPES   = ['Particulier', 'Apprenant', 'Salarié'];

        function toggle() {
          var v = typeSelect.value;
          var isCompany = COMPANY_TYPES.indexOf(v) !== -1;
          var isIndiv   = INDIV_TYPES.indexOf(v) !== -1;
          var indivWrap   = document.getElementById('acdc-cmd-indiv-wrap');
          var companyWrap = document.getElementById('acdc-cmd-company-wrap');
          var proIndiv    = document.getElementById('acdc-prospect-indiv');
          var proCompInfo = document.getElementById('acdc-prospect-company-info');
          if (indivWrap)   { indivWrap.style.display   = isIndiv ? '' : 'none'; }
          if (companyWrap) { companyWrap.style.display = isCompany ? '' : 'none'; }
          if (proIndiv)    { proIndiv.style.display    = isCompany ? 'none' : ''; }
          if (proCompInfo) { proCompInfo.style.display = isCompany ? '' : 'none'; }
          // Gérer les deux selects individuel selon le type
          var learnerSel  = document.getElementById('acdc-cmd-learner-select');
          var prospectSel = document.getElementById('acdc-cmd-prospect-select');
          var indivLbl    = document.getElementById('acdc-cmd-indiv-label');
          if (learnerSel)  { learnerSel.style.display  = (v === 'Apprenant') ? '' : 'none'; learnerSel.disabled = (v !== 'Apprenant'); }
          if (prospectSel) { prospectSel.style.display = (isIndiv && v !== 'Apprenant') ? '' : 'none'; prospectSel.disabled = !(isIndiv && v !== 'Apprenant'); }
          if (indivLbl) {
            if (v === 'Apprenant') { indivLbl.textContent = 'Apprenant concerné'; }
            else if (v === 'Salarié') { indivLbl.textContent = 'Salarié concerné'; }
            else { indivLbl.textContent = 'Prospect / Personne'; }
          }
          // Filtrer les options du select prospects par profile_type
          if (prospectSel) {
            var pOpts = prospectSel.querySelectorAll('option[data-profile]');
            pOpts.forEach(function(opt) {
              var prof  = opt.getAttribute('data-profile') || '';
              var match = (v === 'Particulier' && prof === 'Particulier') || (v === 'Salarié' && prof === 'Salarié');
              opt.style.display = match ? '' : 'none';
              opt.disabled      = !match;
            });
            // Réinitialiser si sélection incompatible
            var selP = prospectSel.options[prospectSel.selectedIndex];
            if (selP && selP.disabled) { prospectSel.value = ''; fillIndivName('', false); }
          }
          // Filtrer les options du dropdown entreprise selon le type sélectionné
          var compSel  = document.getElementById('acdc-cmd-company-select');
          var compLbl  = document.getElementById('acdc-cmd-company-label');
          if (compSel) {
            var opts = compSel.querySelectorAll('option[data-profile]');
            opts.forEach(function(opt) {
              var prof = opt.getAttribute('data-profile') || '';
              var show = false;
              if (v === 'Entreprise') {
                show = prof === 'Entreprise' || prof === 'Entreprise / indépendant';
              } else if (v === 'Indépendant') {
                show = prof === 'Indépendant' || prof === 'Entreprise / indépendant';
              } else {
                show = true;
              }
              opt.style.display = show ? '' : 'none';
              opt.disabled = !show;
            });
            // Réinitialiser la sélection si l'option actuelle est masquée (pas au premier chargement)
            var selOpt = compSel.options[compSel.selectedIndex];
            if (selOpt && selOpt.disabled && _toggleInitDone) {
              compSel.value = '0';
              fillCompanySigner(0);
            }
          }
          if (compLbl) {
            compLbl.childNodes[0].textContent = v === 'Indépendant' ? 'Indépendant / Auto-entrepreneur ' : 'Entreprise / Organisation ';
          }
        }

        // Auto-remplissage Nom/Prénom depuis JSON PHP
        function fillIndivName(id, isLearner) {
          var map = isLearner
            ? (typeof acdcLearnerData !== 'undefined'  ? acdcLearnerData  : {})
            : (typeof acdcProspectData !== 'undefined' ? acdcProspectData : {});
          var data  = map[String(id)] || null;
          var lf = document.getElementById('acdc-cmd-last');
          var ff = document.getElementById('acdc-cmd-first');
          if (lf && data) { lf.value = data.last  || ''; }
          if (ff && data) { ff.value = data.first || ''; }
          // Alimenter le champ hidden source_prospect_id (prospects uniquement, pas les apprenants)
          if (!isLearner) {
            var hid = document.getElementById('acdc-source-prospect-id');
            if (hid) { hid.value = id || 0; }
          }
        }
        // Select apprenants
        var learnerSelect = document.getElementById('acdc-cmd-learner-select');
        if (learnerSelect) {
          learnerSelect.addEventListener('change', function() {
            fillIndivName(this.value, true);
          });
        }
        // Select prospects (Particulier / Salarié)
        var prospectSelect = document.getElementById('acdc-cmd-prospect-select');
        if (prospectSelect) {
          prospectSelect.addEventListener('change', function() {
            fillIndivName(this.value, false);
          });
          // Au chargement : déclencher si déjà pré-sélectionné (arrivée depuis menu 3 points)
          if (prospectSelect.value && prospectSelect.value !== '0') {
            fillIndivName(prospectSelect.value, false);
          }
        }

        // Auto-remplissage signataire depuis JSON PHP (fiable, sans data-attribute)
        var compSelect = document.getElementById('acdc-cmd-company-select');
        function fillCompanySigner(pid) {
          var data = (typeof acdcCompanySigners !== 'undefined') ? (acdcCompanySigners[String(pid)] || null) : null;
          var last  = data ? (data.last  || '') : '';
          var first = data ? (data.first || '') : '';
          var sf = document.getElementById('acdc-cmd-signer-last');
          var ff = document.getElementById('acdc-cmd-signer-first');
          if (sf) { sf.value = last; }
          if (ff) { ff.value = first; }
          // Synchroniser le prospect source (champ caché) avec le prospect entreprise sélectionné
          // Alimenter source_prospect_id depuis la sélection entreprise
          var hidSrc = document.getElementById('acdc-source-prospect-id');
          if (hidSrc) { hidSrc.value = pid || 0; }
        }
        if (compSelect) {
          compSelect.addEventListener('change', function() {
            fillCompanySigner(this.value);
          });
          // Au chargement : remplir si déjà sélectionné (déclenché après toggle)
          if (compSelect.value && compSelect.value !== '0') {
            fillCompanySigner(compSelect.value);
          }
        }

        var _toggleInitDone = false;
        typeSelect.addEventListener('change', toggle);
        // Au chargement : appliquer toggle PUIS déclencher fillCompanySigner si select déjà rempli
        toggle();
        _toggleInitDone = true;
        if (compSelect && compSelect.value && compSelect.value !== '0') {
          fillCompanySigner(compSelect.value);
        }
      })();

      // ── Financement public : toggle liste OPCO ──────────────────────────────
      (function() {
        var fundingSelect = document.getElementById('acdc-rc-public-funding');
        var opcoWrap      = document.getElementById('acdc-rc-opco-wrap');
        var opcoSelect    = document.getElementById('acdc-rc-opco-select');
        var opcoCustom    = document.getElementById('acdc-rc-opco-custom');
        if (!fundingSelect || !opcoWrap) { return; }
        function toggleOpco() {
          opcoWrap.style.display = (fundingSelect.value === 'Oui') ? '' : 'none';
        }
        fundingSelect.addEventListener('change', toggleOpco);
        toggleOpco();
        // Afficher le champ texte libre si 'Autre' est sélectionné
        if (opcoSelect) {
          function toggleCustom() {
            if (opcoCustom) {
              opcoCustom.style.display = (opcoSelect.value === 'Autre') ? '' : 'none';
            }
          }
          opcoSelect.addEventListener('change', toggleCustom);
          toggleCustom();
        }
      })();
      // ── Fin dynamique commanditaire ──────────────────────────────────────────

            var commanditaireField = document.getElementById('acdc-rc-cmd-type');
      var prospectField = document.getElementById('acdc-registration-contract-prospect');
      var companyField = document.getElementById('acdc-registration-contract-company');
      var formationField = document.getElementById('acdc-registration-contract-formation');
      var objectivesField = document.getElementById('acdc-registration-contract-objectives');
      var priceField = document.getElementById('acdc-registration-contract-price');
      var formationMap = <?php echo wp_json_encode( $formation_map ); ?>;
      var prospectMap = <?php echo wp_json_encode( $prospect_map ); ?>;
      function applyFormationPrefill(){
        if(!formationField){ return; }
        var formationData = formationMap[String(formationField.value || '')] || null;
        if(!formationData){ return; }
        if(objectivesField && !String(objectivesField.value || '').trim() && formationData.objectives){
          objectivesField.value = formationData.objectives;
        }
        if(priceField && !String(priceField.value || '').trim() && formationData.price_ht){
          priceField.value = formationData.price_ht;
        }
      }
      function applyProspectPrefill(){
        if(!prospectField){ return; }
        var prospectData = prospectMap[String(prospectField.value || '')] || null;
        if(!prospectData){ return; }
        if(commanditaireField && prospectData.commanditaire_type){
          commanditaireField.value = prospectData.commanditaire_type;
        }
        if(companyField && prospectData.company_id){
          companyField.value = String(prospectData.company_id);
        }
        if(formationField && prospectData.formation_id){
          formationField.value = String(prospectData.formation_id);
        }
        if(objectivesField && !String(objectivesField.value || '').trim() && prospectData.objectives_text){
          objectivesField.value = prospectData.objectives_text;
        }
        if(priceField && !String(priceField.value || '').trim() && prospectData.price_ht){
          priceField.value = prospectData.price_ht;
        }
        applyFormationPrefill();
      }
      if(prospectField){
        prospectField.addEventListener('change', applyProspectPrefill);
        applyProspectPrefill();
      }
      if(formationField){
        formationField.addEventListener('change', applyFormationPrefill);
        applyFormationPrefill();
      }

      var wrap = document.getElementById('acdc-registration-contract-sections');
      var addBtn = document.getElementById('acdc-add-registration-contract-section');
      function reindex(){
        if(!wrap){ return; }
        wrap.querySelectorAll('.acdc-contract-section-card').forEach(function(card, index){
          var badge = card.querySelector('.acdc-contract-section-head span');
          if(badge){ badge.textContent = '#' + (index + 1) + ' Section'; }
          card.querySelectorAll('input, textarea').forEach(function(field){
            var name = field.getAttribute('name') || '';
            field.setAttribute('name', name.replace(/additional_sections\]\[[0-9]+\]/, 'additional_sections][' + index + ']'));
          });
        });
      }
      if(wrap){
        wrap.addEventListener('click', function(e){
          if(e.target.classList.contains('acdc-contract-remove-section')){
            var cards = wrap.querySelectorAll('.acdc-contract-section-card');
            if(cards.length <= 1){ return; }
            e.target.closest('.acdc-contract-section-card').remove();
            reindex();
          }
        });
      }
      if(addBtn && wrap){
        addBtn.addEventListener('click', function(){
          var index = wrap.querySelectorAll('.acdc-contract-section-card').length;
          var card = document.createElement('div');
          card.className = 'acdc-contract-section-card';
          card.innerHTML = '<div class="acdc-contract-section-head"><span>#' + (index + 1) + ' Section</span><button type="button" class="acdc-contract-remove-section">Supprimer</button></div><div class="acdc-contract-section-body"><div class="acdc-contract-grid"><div class="acdc-contract-label">Titre de la section <span class="acdc-required">*</span></div><div><input type="text" name="registration_contract[additional_sections][' + index + '][title]" value=""></div></div><div class="acdc-contract-grid"><div class="acdc-contract-label">Contenu de la section <span class="acdc-required">*</span></div><div><div class="acdc-contract-rich-editor" data-acdc-rich-editor><div class="acdc-contract-rich-toolbar"><button type="button" data-acdc-rich-tag="strong" title="Gras">B</button><button type="button" data-acdc-rich-tag="em" title="Italique">I</button><button type="button" data-acdc-rich-tag="s" title="Barré">S</button><button type="button" data-acdc-rich-link="1" title="Lien">🔗</button></div><textarea name="registration_contract[additional_sections][' + index + '][content]" rows="4"></textarea></div></div></div></div>';
          wrap.appendChild(card);
        });
      }
    });
    </script>
    <?php
  }


  private function render_front_contracts_documents_tab() {
    $search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
    $per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 25;
    if ( ! in_array( $per_page, array( 25, 50, 100 ), true ) ) {
      $per_page = 25;
    }
    $paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
    $base_tab = 'contracts_documents';
    $base_url = is_admin() ? $this->admin_tab_url( $base_tab ) : $this->portal_page_url( array( 'tab' => $base_tab ) );

    // ACDC 3.25.00 — Sous-onglets Dossiers de formation
    $crd_view = isset( $_GET['crd_view'] ) ? sanitize_key( wp_unslash( $_GET['crd_view'] ) ) : 'conventions';
    $allowed_views = array( 'conventions', 'apprenants', 'commanditaires', 'formations', 'seances' );
    if ( ! in_array( $crd_view, $allowed_views, true ) ) { $crd_view = 'conventions'; }

    $tab_labels = array(
      'conventions'    => 'Conventions',
      'apprenants'     => 'Par apprenant',
      'commanditaires' => 'Par commanditaire',
      'formations'     => 'Par formation',
      'seances'        => 'Par séance',
    );
    ?>
    <style>
      .acdc-dossier-subtabs { display:flex; gap:0; border-bottom:2px solid #d7dde6; margin-bottom:20px; flex-wrap:wrap; }
      .acdc-dossier-subtabs a { display:inline-block; padding:9px 18px; font-size:13px; font-weight:600; color:#4b5563; text-decoration:none; border-bottom:3px solid transparent; margin-bottom:-2px; transition:color .15s,border-color .15s; white-space:nowrap; }
      .acdc-dossier-subtabs a:hover { color:#0f2c52; }
      .acdc-dossier-subtabs a.is-active { color:#0f2c52; border-bottom-color:#d6a353; }
      .acdc-dossier-entity-list { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:14px; }
      .acdc-dossier-entity-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; cursor:pointer; transition:box-shadow .15s,border-color .15s; }
      .acdc-dossier-entity-card:hover { box-shadow:0 4px 14px rgba(15,44,82,.1); border-color:#b0bdce; }
      .acdc-dossier-entity-card .acdc-dec-name { font-weight:700; color:#0f2c52; font-size:13.5px; margin-bottom:4px; }
      .acdc-dossier-entity-card .acdc-dec-meta { font-size:11.5px; color:#6b7280; }
      .acdc-dossier-pieces-panel { background:#f9fafb; border:1px solid #e2e8f0; border-radius:10px; padding:16px; margin-top:16px; }
      .acdc-dossier-pieces-panel h4 { margin:0 0 12px; font-size:14px; color:#0f2c52; }
      .acdc-dossier-piece-row { display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid #edf2f7; font-size:12.5px; }
      .acdc-dossier-piece-row:last-child { border-bottom:none; }
      .acdc-dossier-piece-type { display:inline-block; padding:2px 8px; border-radius:12px; font-size:10.5px; font-weight:700; min-width:72px; text-align:center; white-space:nowrap; }
      .acdc-dossier-piece-type.t-convention { background:#dbeafe; color:#1e40af; }
      .acdc-dossier-piece-type.t-nad { background:#ede9fe; color:#5b21b6; }
      .acdc-dossier-piece-type.t-devis { background:#fef3c7; color:#92400e; }
      .acdc-dossier-piece-type.t-emargement { background:#d1fae5; color:#065f46; }
      .acdc-dossier-piece-type.t-enquete { background:#fce7f3; color:#9d174d; }
      .acdc-dossier-piece-label { flex:1; color:#1f2937; }
      .acdc-dossier-piece-date { color:#9ca3af; font-size:11px; white-space:nowrap; }
      .acdc-dossier-empty { padding:40px 20px; text-align:center; color:#9ca3af; font-size:13px; }
      .acdc-dossier-search-bar { margin-bottom:16px; }
      .acdc-dossier-search-bar input { padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:13px; width:260px; max-width:100%; }
    </style>
    <div class="acdc-dossier-subtabs">
      <?php foreach ( $tab_labels as $v => $lbl ) : ?>
        <a href="<?php echo esc_url( add_query_arg( array( 'crd_view' => $v, 'q' => '' ), $base_url ) ); ?>"
           class="<?php echo $crd_view === $v ? 'is-active' : ''; ?>">
          <?php echo esc_html( $lbl ); ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php

    // ── Vues "Par entité" ────────────────────────────────────────────────
    if ( 'apprenants' === $crd_view ) {
      $this->render_dossiers_by_entity( 'learner', $search, $base_url, $paged, $per_page );
      return;
    }
    if ( 'commanditaires' === $crd_view ) {
      $this->render_dossiers_by_entity( 'company', $search, $base_url, $paged, $per_page );
      return;
    }
    if ( 'formations' === $crd_view ) {
      $this->render_dossiers_by_entity( 'formation', $search, $base_url, $paged, $per_page );
      return;
    }
    if ( 'seances' === $crd_view ) {
      $this->render_dossiers_by_entity( 'session', $search, $base_url, $paged, $per_page );
      return;
    }
    // ── Vue "Conventions" (code original intact) ─────────────────────────

    $items = $this->get_registration_contracts( $search );
    $enriched = array();
    foreach ( $items as $contract ) {
      $context = $this->get_registration_contract_related_context( $contract );
      $document = $this->get_registration_contract_document_info( $contract );
      $formation = isset( $context['formation'] ) ? $context['formation'] : null;
      $registration = isset( $context['registration'] ) ? $context['registration'] : null;
      $company_name = $this->get_registration_contract_display_company_name( $contract, $context );
      $duration = $formation && ! empty( $formation->duration ) ? (string) $formation->duration : '—';
      if ( preg_match( '/^(\d{1,2}):(\d{2})$/', $duration, $m ) ) {
        $duration = sprintf( '%02dh%02d', (int) $m[1], (int) $m[2] );
      }
      $session = isset( $context['session'] ) ? $context['session'] : null;
      $format_val = ( $session && ! empty( $session->session_format ) ) ? (string) $session->session_format
                  : ( ( $session && ! empty( $session->format ) ) ? (string) $session->format
                  : ( ( $formation && ! empty( $formation->modality ) ) ? (string) $formation->modality : '—' ) );
      $enriched[] = array(
        'contract' => $contract,
        'context' => $context,
        'document' => $document,
        'company_name' => $company_name,
        'commanditaire_display' => $this->get_registration_contract_commanditaire_display_data( $contract, $context ),
        'commanditaire_name' => $this->get_registration_contract_display_commanditaire_name( $contract, $context ),
        'signature_lines' => $this->get_registration_contract_signature_stack( $contract, $document ),
        'formation_label' => ! empty( $contract->formation_title ) ? $contract->formation_title : ( $formation && ! empty( $formation->title ) ? $formation->title : '—' ),
        'contract_kind' => $this->get_registration_contract_kind_label( isset( $contract->commanditaire_type ) ? $contract->commanditaire_type : '' ),
        'dates_formation' => $this->acdc_format_seances_dates_for_display( $contract ),
        'format' => $format_val,
        'duration' => $duration,
        'nad_info' => $this->get_registration_contract_nad_info( (int) $contract->id ),
      );
    }

    $total = count( $enriched );
    $total_pages = max( 1, (int) ceil( $total / $per_page ) );
    if ( $paged > $total_pages ) {
      $paged = $total_pages;
    }
    $offset = ( $paged - 1 ) * $per_page;
    $page_items = array_slice( $enriched, $offset, $per_page );
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Conventions de formation</h2>
      </div>
    </section>
    <div class="acdc-panel acdc-mb-18">
      <form class="acdc-search-bar" method="get" action="<?php echo esc_url( $base_url ); ?>">
        <?php if ( is_admin() ) : ?>
          <input type="hidden" name="page" value="acdc-of-dashboard">
        <?php endif; ?>
        <input type="hidden" name="tab" value="contracts_documents">
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
      <?php if ( empty( $page_items ) ) : ?>
        <div class="acdc-empty-state" style="padding:72px 24px;text-align:center;">
          <div class="acdc-empty-state-icon" aria-hidden="true"><?php echo $this->render_inline_icon( 'contract', 54 ); ?></div>
          <p style="margin:0;color:#1E4777;">Aucune donnée ne correspond aux critères demandés.</p>
        </div>
      <?php else : ?>
        <div class="acdc-table-wrap">
          <table class="acdc-table acdc-table-contracts-documents">
            <thead>
              <tr>
                <th><input type="checkbox" aria-label="Sélectionner"></th>
                <th><span class="acdc-contracts-colhead">Commanditaire <span class="acdc-contracts-sort" aria-hidden="true">↕</span></span></th>
                <th><span class="acdc-contracts-colhead">Signature</span></th>
                <th><span class="acdc-contracts-colhead">Type</span></th>
                <th><span class="acdc-contracts-colhead">Formation <span class="acdc-contracts-sort" aria-hidden="true">↕</span></span></th>
                <th><span class="acdc-contracts-colhead">Dates de formation <span class="acdc-contracts-sort" aria-hidden="true">↕</span></span></th>
                <th><span class="acdc-contracts-colhead">Format</span></th>
                <th><span class="acdc-contracts-colhead">Durée (H)</span></th>
                <th><span class="acdc-contracts-colhead">Analyse du besoin</span></th>
                <th><span class="acdc-contracts-colhead">Actions</span></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ( $page_items as $row ) : ?>
              <?php
              $entry = $row['contract'];
              $context = $row['context'];
              $document = $row['document'];
              $download_url = $this->get_registration_contract_download_url( $entry, 'attachment' );
              $view_pdf_url = $this->get_registration_contract_download_url( $entry, 'inline' );
              $delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_delete_registration_contract&contract_id=' . (int) $entry->id . ( is_admin() ? '&page=acdc-of-dashboard&tab=contracts_documents' : '' ) ), 'acdc_delete_registration_contract_' . (int) $entry->id );
              $view_modal_id = 'acdc-contract-view-' . (int) $entry->id;
              $edit_modal_id = 'acdc-contract-edit-' . (int) $entry->id;
              $file_name = $this->get_registration_contract_display_file_name( $entry, $document );
              ?>
              <tr>
                <td><input type="checkbox" aria-label="Sélectionner cette convention"></td>
                <td>
                  <?php $this->render_registration_contract_commanditaire_markup( $row['commanditaire_display'] ); ?>
                </td>
                <td><?php echo $this->render_registration_contract_signature_badge( $entry ); ?></td>
                <?php
                $kind_label = $row['contract_kind'];
                $kind_color = 'Convention' === $kind_label ? '#1e4777' : '#6b21a8';
                $kind_bg    = 'Convention' === $kind_label ? '#e8f0fa' : '#f3e8ff';
                ?>
                <td><span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;color:<?php echo esc_attr($kind_color); ?>;background:<?php echo esc_attr($kind_bg); ?>;white-space:nowrap;"><?php echo esc_html( $kind_label ); ?></span></td>
                <td><?php echo esc_html( $row['formation_label'] ); ?></td>
                <td><?php echo nl2br( esc_html( $row['dates_formation'] ) ); ?></td>
                <td><?php echo esc_html( $row['format'] ); ?></td>
                <td><?php echo esc_html( $row['duration'] ); ?></td>
                <td><?php echo wp_kses_post( $row['nad_info']['html'] ); ?></td>
                <td class="acdc-actions-cell-icons">
                  <div class="acdc-groups-actions-inline">
                    <?php
                    $has_sig_req = ! empty( $entry->signature_request_id ) && (int) $entry->signature_request_id > 0;
                    /* Ne pas proposer « Renvoyer pour signature » sur une convention déjà signée. */
                    $is_signed_row = ! empty( $entry->signed_document_url ) || ! empty( $entry->signed_document_path ) || in_array( (string) ( $entry->signature_status ?? '' ), array( 'completed', 'signe', 'signée' ), true );
                    $resend_url  = ( $has_sig_req && ! $is_signed_row ) ? wp_nonce_url( admin_url( 'admin-post.php?action=acdc_sig_resend_request&request_id=' . (int) $entry->signature_request_id ), 'acdc_sig_resend_' . (int) $entry->signature_request_id ) : '';
                    $reg_url     = $this->portal_page_url( array( 'tab' => 'register_training', 'action' => 'bulk_from_contract', 'contract_id' => (int) $entry->id ) );
                    ?>
                    <div class="acdc-row-menu" data-acdc-row-menu>
                      <button type="button" class="acdc-row-action-icon acdc-prospect-action-trigger" data-acdc-row-menu-toggle data-acdc-iconized="1" aria-haspopup="true" aria-expanded="false" title="Plus d'actions"><?php echo $this->render_inline_icon( 'more-horizontal', 25 ); ?><span class="acdc-action-hub-sr screen-reader-text">Plus d'actions</span></button>
                      <div class="acdc-row-menu-dropdown" data-acdc-row-menu-dropdown hidden>
                        <?php if ( $resend_url ) : ?><a href="<?php echo esc_url( $resend_url ); ?>">✉ Renvoyer pour signature</a><?php endif; ?>
                        <a href="<?php echo esc_url( $download_url ); ?>">⬇ Télécharger le document</a>
                        <a href="<?php echo esc_url( $reg_url ); ?>">➕ Inscrire en formation</a>
                      </div>
                    </div>
                    <button type="button" class="acdc-row-action-icon acdc-row-view-link" data-acdc-modal-open="<?php echo esc_attr( $view_modal_id ); ?>" data-acdc-iconized="1" title="Voir" aria-label="Voir"><?php echo $this->render_inline_icon( 'view', 25 ); ?><span class="acdc-action-hub-sr screen-reader-text">Voir</span></button>
                    <button type="button" class="acdc-row-action-icon acdc-row-edit-link" data-acdc-modal-open="<?php echo esc_attr( $edit_modal_id ); ?>" data-acdc-iconized="1" title="Modifier" aria-label="Modifier"><?php echo $this->render_inline_icon( 'edit-pencil', 25 ); ?><span class="acdc-action-hub-sr screen-reader-text">Modifier</span></button>
                    <a href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('Supprimer cette convention ?');" class="acdc-row-action-icon acdc-row-delete-link" data-acdc-iconized="1" title="Supprimer" aria-label="Supprimer"><?php echo $this->render_inline_icon( 'trash-bin', 25 ); ?><span class="acdc-action-hub-sr screen-reader-text">Supprimer</span></a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ( $total_pages > 1 ) : ?>
          <div class="acdc-pagination-wrap">
            <div class="acdc-pagination">
              <?php for ( $page = 1; $page <= $total_pages; $page++ ) : ?>
                <?php $url = add_query_arg( array( 'paged' => $page, 'per_page' => $per_page, 'q' => $search ), $base_url ); ?>
                <a href="<?php echo esc_url( $url ); ?>" class="<?php echo $page === $paged ? 'is-active' : ''; ?>"><?php echo esc_html( $page ); ?></a>
              <?php endfor; ?>
            </div>
            <div class="acdc-pagination-summary"><?php echo esc_html( sprintf( '%d-%d de %d', $total ? $offset + 1 : 0, min( $offset + $per_page, $total ), $total ) ); ?></div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php foreach ( $page_items as $row ) : ?>
      <?php
      $entry = $row['contract'];
      $context = $row['context'];
      $document = $row['document'];
      $file_name = $this->get_registration_contract_display_file_name( $entry, $document );
      $view_modal_id = 'acdc-contract-view-' . (int) $entry->id;
      $edit_modal_id = 'acdc-contract-edit-' . (int) $entry->id;
      $view_pdf_url = $this->get_registration_contract_download_url( $entry, 'inline' );
      $company = isset( $context['company'] ) ? $context['company'] : null;
      $registration = isset( $context['registration'] ) ? $context['registration'] : null;
      $formation = isset( $context['formation'] ) ? $context['formation'] : null;
      $learners = isset( $context['learners'] ) ? $context['learners'] : array();
      ?>
      <div class="acdc-modal-shell" id="<?php echo esc_attr( $view_modal_id ); ?>" hidden>
        <div class="acdc-modal-backdrop" data-acdc-modal-close></div>
        <div class="acdc-modal-dialog acdc-modal-dialog-contract">
          <div class="acdc-modal-header"><h4><?php echo esc_html( $this->get_registration_contract_view_modal_title( $entry, $context ) ); ?></h4><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button></div>
          <div class="acdc-modal-body">
            <div class="acdc-contract-doc-topbar"><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $view_pdf_url ); ?>" target="_blank" rel="noopener">Voir le document</a></div>
            <div class="acdc-contract-file-card">
              <div class="acdc-contract-file-meta">
                <strong><?php echo esc_html( $file_name ); ?></strong>
                <?php if ( ! empty( $document['size'] ) ) : ?><span><?php echo esc_html( $document['size'] ); ?></span><?php endif; ?>
              </div>
            </div>
            <div class="acdc-contract-details-grid">
              <div class="acdc-contract-detail-label">Programme de formation utilisé</div><div><?php echo esc_html( ! empty( $formation->title ) ? $formation->title : 'Programme par défaut' ); ?></div>
              <div class="acdc-contract-detail-label">Commanditaire</div><div><?php $this->render_registration_contract_commanditaire_markup( $row['commanditaire_display'] ); ?></div>
              <div class="acdc-contract-detail-label">Formation</div><div><?php echo esc_html( $row['formation_label'] ); ?></div>
              <?php
              /* ACDC 3.25.160 — Ces trois champs ne lisaient QUE la fiche commanditaire.
                 Depuis que la convention n'en emprunte plus une au hasard, une convention
                 non signée n'en a plus : les valeurs du prospect source existent pourtant
                 et doivent prendre le relais, sinon l'écran affiche trois tirets là où
                 l'information est connue. */
              $cmd_prospect = isset( $row['context']['prospect'] ) ? $row['context']['prospect'] : null;
              $cmd_addr = $company && ! empty( $company->address ) ? $company->address : ( $cmd_prospect && ! empty( $cmd_prospect->address ) ? $cmd_prospect->address : '' );
              $cmd_cp   = $company && ! empty( $company->postal_code ) ? $company->postal_code : ( $cmd_prospect && ! empty( $cmd_prospect->postal_code ) ? $cmd_prospect->postal_code : '' );
              $cmd_city = $company && ! empty( $company->city ) ? $company->city : ( $cmd_prospect && ! empty( $cmd_prospect->city ) ? $cmd_prospect->city : '' );
              ?>
              <div class="acdc-contract-detail-label">Adresse</div><div><?php echo esc_html( '' !== $cmd_addr ? $cmd_addr : '—' ); ?></div>
              <div class="acdc-contract-detail-label">Code postal</div><div><?php echo esc_html( '' !== $cmd_cp ? $cmd_cp : '—' ); ?></div>
              <div class="acdc-contract-detail-label">Ville</div><div><?php echo esc_html( '' !== $cmd_city ? $cmd_city : '—' ); ?></div>
              <div class="acdc-contract-detail-label">Dates des séances</div><div><?php echo nl2br( esc_html( $this->acdc_format_seances_dates_for_display( $entry ) ) ); ?></div>
              <div class="acdc-contract-detail-label">Nombre d'apprenants à former</div><div><?php echo esc_html( count( $learners ) ); ?></div>
              <div class="acdc-contract-detail-label">Apprenants</div><div><?php if ( ! empty( $learners ) ) { foreach ( $learners as $learner ) { echo esc_html( trim( $learner->first_name . ' ' . ( ! empty( $learner->usage_last_name ) ? $learner->usage_last_name : $learner->last_name ) ) ) . '<br>'; } } else { echo '—'; } ?></div>
              <div class="acdc-contract-detail-label">Tarif de l'action de formation (€ HT)</div><div><?php echo esc_html( ! empty( $registration->price_ht ) ? $registration->price_ht . ' €' : ( ! empty( $entry->price_ht ) ? $entry->price_ht . ' €' : '—' ) ); ?></div>
              <div class="acdc-contract-detail-label">Taux de TVA associé (%)</div><div><?php echo esc_html( ! empty( $entry->vat_rate ) ? $entry->vat_rate . '%' : '—' ); ?></div>
              <div class="acdc-contract-detail-label">Frais de transport</div><div><?php echo ! empty( $registration->transport_fees_enabled ) || ! empty( $entry->transport_fees_enabled ) ? 'Oui' : 'Non'; ?></div>
              <div class="acdc-contract-detail-label">Frais de restauration et / ou hébergement</div><div><?php echo ! empty( $registration->meal_fees_enabled ) || ! empty( $entry->meal_fees_enabled ) ? 'Oui' : 'Non'; ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="acdc-modal-shell" id="<?php echo esc_attr( $edit_modal_id ); ?>" hidden>
        <div class="acdc-modal-backdrop" data-acdc-modal-close></div>
        <div class="acdc-modal-dialog acdc-modal-dialog-contract">
          <div class="acdc-modal-header"><h4><?php echo esc_html( $this->get_registration_contract_edit_modal_title( $entry, $context ) ); ?></h4><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button></div>
          <div class="acdc-modal-body">
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
              <?php wp_nonce_field( 'acdc_update_registration_contract_document_' . (int) $entry->id ); ?>
              <input type="hidden" name="action" value="acdc_update_registration_contract_document">
              <input type="hidden" name="contract_id" value="<?php echo esc_attr( (int) $entry->id ); ?>">
              <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
              <input type="hidden" name="tab" value="contracts_documents">
              <div class="acdc-contract-grid">
                <div class="acdc-contract-label">Convention de formation</div>
                <div>
                  <div class="acdc-contract-file-preview">
                    <div class="acdc-contract-file-preview-thumb"><?php echo $this->render_inline_icon( 'document', 30 ); ?></div>
                    <div class="acdc-contract-file-preview-name"><?php echo esc_html( $file_name ); ?></div>
                  </div>
                  <label class="acdc-upload-dropzone">
                    <span class="acdc-button acdc-button-primary">Choisir le fichier</span>
                    <span>Déposez le fichier ou cliquez pour choisir</span>
                    <input type="file" name="contract_document_file" accept="application/pdf" required>
                  </label>
                </div>
              </div>
              <p class="acdc-actions-end">
                <button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-close>Annuler</button>
                <button type="submit" class="acdc-button acdc-button-primary">Modifier Convention de formation</button>
              </p>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

    <style>
      .acdc-table-contracts-documents thead th{background:#F6F8FB;color:#8B98AC;font-size:11px;line-height:14px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;vertical-align:middle}.acdc-table-contracts-documents thead th .acdc-contracts-colhead{display:inline-flex;align-items:center;gap:6px;white-space:nowrap}.acdc-table-contracts-documents thead th .acdc-contracts-sort{font-size:11px;line-height:1;color:#B7C1CF;font-weight:700}.acdc-table-contracts-documents .acdc-row-view-link,.acdc-table-contracts-documents .acdc-row-edit-link,.acdc-table-contracts-documents .acdc-row-delete-link{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;color:#1E4777;text-decoration:none;border:none;background:transparent;cursor:pointer}
      .acdc-table-contracts-documents .acdc-row-delete-link:hover,.acdc-table-contracts-documents .acdc-row-view-link:hover,.acdc-table-contracts-documents .acdc-row-edit-link:hover{color:#0C2D52}
      .acdc-table-contracts-documents small{display:block;margin-top:4px;color:#1E4777}.acdc-modal-dialog-contract{width:min(1160px,94vw)}
      .acdc-contract-doc-topbar{display:flex;justify-content:flex-start;margin-bottom:18px}.acdc-contract-file-card{display:flex;align-items:center;padding:12px 16px;border:1px solid var(--acdc-border);border-radius:10px;background:#fbf8f7;margin-bottom:18px}.acdc-contract-file-meta{display:flex;flex-direction:column;gap:4px;color:#1E4777}.acdc-contract-file-meta strong{font-size:14px;color:#1E4777}.acdc-contract-details-grid{display:grid;grid-template-columns:minmax(180px,280px) minmax(0,1fr);gap:0;border-top:1px solid #DCE4EC}.acdc-contract-detail-label{padding:14px 12px;color:#1E4777;border-bottom:1px solid #DCE4EC}.acdc-contract-details-grid>div:nth-child(2n){padding:14px 12px;border-bottom:1px solid #DCE4EC;color:#1E4777}.acdc-contract-file-preview{display:flex;flex-direction:column;gap:8px;width:280px;max-width:100%;margin-bottom:12px}.acdc-contract-file-preview-thumb{display:flex;align-items:center;justify-content:center;width:280px;height:220px;border:1px solid var(--acdc-border);border-radius:10px;background:#F9FAFB;color:#3C3C3C}.acdc-contract-file-preview-name{font-size:12px;color:#1E4777;word-break:break-all}.acdc-upload-dropzone{display:flex;align-items:center;gap:14px;padding:14px;border:2px dashed #DCE4EC;border-radius:10px;cursor:pointer}.acdc-upload-dropzone input{display:none}
      @media (max-width:900px){.acdc-contract-details-grid{grid-template-columns:1fr}.acdc-contract-detail-label{border-bottom:none;padding-bottom:4px}.acdc-contract-details-grid>div:nth-child(2n){padding-top:0}}
    </style>
    <script>
      (function(){
        if(window.__acdcContractsDocumentsInit){return;} window.__acdcContractsDocumentsInit = true;
        var filterToggle = document.querySelector('[data-acdc-filter-toggle]');
        var filterPanel = document.querySelector('[data-acdc-filters-panel]');
        if(filterToggle && filterPanel){
          filterToggle.addEventListener('click', function(){
            var expanded = filterToggle.getAttribute('aria-expanded') === 'true';
            filterToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            filterPanel.hidden = expanded;
          });
        }
        document.querySelectorAll('[data-acdc-row-menu-toggle]').forEach(function(btn){
          btn.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); var wrapper=btn.closest('[data-acdc-row-menu]'); var dropdown=wrapper?wrapper.querySelector('[data-acdc-row-menu-dropdown]'):null; if(!dropdown){return;} var expanded=btn.getAttribute('aria-expanded')==='true'; document.querySelectorAll('[data-acdc-row-menu-dropdown]').forEach(function(menu){ menu.hidden=true; }); document.querySelectorAll('[data-acdc-row-menu-toggle]').forEach(function(other){ other.setAttribute('aria-expanded','false'); }); dropdown.hidden=expanded; btn.setAttribute('aria-expanded', expanded ? 'false' : 'true'); });
        });
        document.addEventListener('click', function(){ document.querySelectorAll('[data-acdc-row-menu-dropdown]').forEach(function(menu){ menu.hidden=true; }); document.querySelectorAll('[data-acdc-row-menu-toggle]').forEach(function(btn){ btn.setAttribute('aria-expanded','false'); }); });
      })();
    </script>
    <?php
  }

public function render_admin_registration_contract_page() { $this->render_admin_portal_wrapper( 'registration_contract' ); }


  /**
   * ACDC 3.25.00 — Rendu d'un sous-onglet "dossiers par entité" (apprenant, commanditaire, formation, séance).
   */
  private function render_dossiers_by_entity( $entity_type, $search, $base_url, $paged, $per_page ) {
    global $wpdb;

    // Paramètre de navigation : trf_id pour la fiche, trf_view pour le sous-onglet
    $trf_id      = isset( $_GET['trf_id'] )   ? absint( wp_unslash( $_GET['trf_id'] ) )         : 0;
    $trf_view    = isset( $_GET['trf_view'] )  ? sanitize_key( wp_unslash( $_GET['trf_view'] ) ) : ( $entity_type . 's' );
    $entity_subtab = isset( $_GET['entity_subtab'] ) ? sanitize_key( wp_unslash( $_GET['entity_subtab'] ) ) : 'synthesis';
    $allowed_entity_subtabs = array( 'synthesis', 'need', 'objectives', 'positioning', 'contract', 'welcome', 'sessions', 'support', 'evaluations', 'documents', 'complaints', 'compliance', 'quality', 'public_info', 'accounting', 'history' );
    if ( ! in_array( $entity_subtab, $allowed_entity_subtabs, true ) ) { $entity_subtab = 'synthesis'; }

    $entity_labels = array(
      'learner'   => 'Apprenant',
      'company'   => 'Commanditaire',
      'formation' => 'Formation',
      'session'   => 'Séance',
    );
    $entity_label = isset( $entity_labels[ $entity_type ] ) ? $entity_labels[ $entity_type ] : '';

    // ── MODE FICHE ────────────────────────────────────────────────────────
    if ( $trf_id ) {
      $this->render_dossier_entity_fiche( $entity_type, $trf_id, $entity_subtab, $base_url, $trf_view );
      return;
    }

    // ── MODE LISTE ────────────────────────────────────────────────────────
    switch ( $entity_type ) {
      case 'learner':
        // Uniquement les apprenants ayant au moins une inscription
        $rows = $wpdb->get_results(
          "SELECT DISTINCT l.id,
             CONCAT(COALESCE(l.first_name,''), ' ', COALESCE(l.last_name,'')) AS name,
             l.email,
             COALESCE(c.name, cc.name, '') AS company_name
           FROM {$this->learner_table} l
           INNER JOIN {$this->training_registration_table} tr ON tr.learner_id = l.id
           LEFT JOIN {$this->company_table} c ON c.id = l.company_id
           /* ACDC 3.25.158 — Colonne ENTREPRISE vide alors que le dossier était bien
              rattaché : l'apprenant ne porte pas toujours company_id, le lien vit sur
              l'inscription ou sur la convention. On remonte la chaîne. */
           LEFT JOIN {$this->registration_contract_table} rc ON rc.id = tr.autofill_contract_id
           LEFT JOIN {$this->company_table} cc ON cc.id = COALESCE( NULLIF( tr.company_id, 0 ), rc.company_id )
           ORDER BY l.last_name ASC, l.first_name ASC
           LIMIT 500"
        );
        break;
      case 'company':
        /* ACDC 3.25.158 — Le rattachement ne se lit pas uniquement sur l'inscription :
           quand une inscription est créée depuis une convention, c'est la CONVENTION
           qui porte company_id, et tr.company_id reste vide. La jointure stricte
           renvoyait alors « Aucun commanditaire trouvé » alors que des conventions
           existaient bel et bien — l'écran Dossiers de formation, lui, affichait le
           bon nom parce qu'il remonte à la convention. On accepte les deux chemins. */
        /* ACDC 3.25.163 — Cet écran s'intitule « Conventions / Contrats » : il doit
           donc partir des CONVENTIONS, pas des inscriptions. Le regroupement par
           inscription masquait toute convention sans apprenant rattaché — la recette
           en a produit une, signée électroniquement, invisible ici. Un commanditaire
           apparaît désormais s'il porte une convention OU une inscription. */
        $rows = $wpdb->get_results(
          "SELECT DISTINCT co.id, co.name, COALESCE(co.city,'') AS city
           FROM {$this->company_table} co
           LEFT JOIN {$this->training_registration_table} tr
             ON tr.company_id = co.id
             OR ( ( tr.company_id IS NULL OR tr.company_id = 0 )
                  AND EXISTS ( SELECT 1 FROM {$this->registration_contract_table} rc
                               WHERE rc.id = tr.autofill_contract_id AND rc.company_id = co.id ) )
           WHERE ( tr.id IS NOT NULL
                   OR EXISTS ( SELECT 1 FROM {$this->registration_contract_table} rc2
                               WHERE rc2.company_id = co.id ) )
           /* ACDC 3.25.159 — La clause « WHERE co.is_archived = 0 OR co.is_archived
              IS NULL » a été retirée : cette colonne N'EXISTE PAS sur la table des
              commanditaires. MySQL rejetait donc la requête entière, get_results()
              renvoyait un tableau vide, et l'écran affichait « Aucun commanditaire
              trouvé » — une erreur SQL silencieuse, présentée comme une absence de
              données. C'est ce qui explique que la vue « Par apprenant », dépourvue
              de cette clause, se soit corrigée alors que celle-ci non. */
           ORDER BY co.name ASC
           LIMIT 500"
        );
        break;
      case 'formation':
        // Formations effectivement utilisées dans au moins une inscription
        $rows = $wpdb->get_results(
          "SELECT DISTINCT f.id, f.title AS name, COALESCE(f.code,'') AS code
           FROM {$this->formation_table} f
           INNER JOIN {$this->training_registration_table} tr ON tr.formation_id = f.id
           ORDER BY f.title ASC
           LIMIT 500"
        );
        break;
      case 'session':
        $rows = $wpdb->get_results(
          "SELECT DISTINCT s.id,
             CONCAT(COALESCE(f.title,'?'), ' — ', COALESCE(DATE_FORMAT(s.start_date,'%d/%m/%Y'),'?')) AS name,
             s.status
           FROM {$this->session_table} s
           LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id
           INNER JOIN {$this->learner_table} l ON l.session_id = s.id
           ORDER BY s.start_date DESC
           LIMIT 500"
        );
        break;
      default:
        $rows = array();
    }

    if ( '' !== $search ) {
      $rows = array_values( array_filter( (array) $rows, function( $r ) use ( $search ) {
        return false !== stripos( (string) $r->name, $search );
      } ) );
    }

    $total       = count( $rows );
    $total_pages = max( 1, (int) ceil( $total / $per_page ) );
    if ( $paged > $total_pages ) { $paged = $total_pages; }
    $offset    = ( $paged - 1 ) * $per_page;
    $page_rows = array_slice( $rows, $offset, $per_page );
    ?>
    <div class="acdc-panel acdc-mb-18">
      <form method="get" action="<?php echo esc_url( $base_url ); ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
        <input type="hidden" name="tab" value="training_files">
        <input type="hidden" name="trf_view" value="<?php echo esc_attr( $trf_view ); ?>">
        <input type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="Rechercher un <?php echo esc_attr( strtolower( $entity_label ) ); ?>..." style="padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;width:280px;max-width:100%;">
        <button type="submit" class="acdc-button acdc-button-primary">Rechercher</button>
        <?php if ( $search ) : ?>
          <a href="<?php echo esc_url( add_query_arg( array( 'trf_view' => $trf_view, 'q' => '' ), $base_url ) ); ?>" class="acdc-button acdc-button-soft">✕ Réinitialiser</a>
        <?php endif; ?>
      </form>
    </div>

    <?php if ( empty( $page_rows ) ) : ?>
      <div class="acdc-panel" style="padding:48px 24px;text-align:center;color:#9ca3af;">
        Aucun <?php echo esc_html( strtolower( $entity_label ) ); ?> trouvé<?php if ( $search ) : ?> pour "<?php echo esc_html( $search ); ?>"<?php endif; ?>.
      </div>
    <?php else : ?>
      <div class="acdc-panel">
        <div class="acdc-table-wrap">
          <table class="acdc-table">
            <thead>
              <tr>
                <th><?php echo esc_html( $entity_label ); ?></th>
                <?php if ( 'learner' === $entity_type ) : ?>
                  <th>Entreprise</th><th>E-mail</th>
                <?php elseif ( 'company' === $entity_type ) : ?>
                  <th>Ville</th>
                <?php elseif ( 'formation' === $entity_type ) : ?>
                  <th>Code</th>
                <?php elseif ( 'session' === $entity_type ) : ?>
                  <th>Statut</th>
                <?php endif; ?>
                <th>Inscriptions</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( $page_rows as $row ) :
                $row_id    = (int) $row->id;
                $fiche_url = add_query_arg( array( 'trf_view' => $trf_view, 'trf_id' => $row_id, 'entity_subtab' => 'synthesis', 'q' => $search ), $base_url );
                // Compter les inscriptions liées
                if ( 'learner' === $entity_type ) {
                  $nb_inscriptions = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->training_registration_table} WHERE learner_id = %d", $row_id ) );
                } elseif ( 'company' === $entity_type ) {
                  $nb_inscriptions = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->training_registration_table} WHERE company_id = %d", $row_id ) );
                } elseif ( 'formation' === $entity_type ) {
                  $nb_inscriptions = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->training_registration_table} WHERE formation_id = %d", $row_id ) );
                } elseif ( 'session' === $entity_type ) {
                  $nb_inscriptions = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->learner_table} WHERE session_id = %d", $row_id ) );
                } else {
                  $nb_inscriptions = 0;
                }
              ?>
              <tr>
                <td style="font-weight:600;color:#0f2c52;"><?php echo esc_html( trim( (string) $row->name ) ); ?></td>
                <?php if ( 'learner' === $entity_type ) : ?>
                  <td><?php echo esc_html( (string) $row->company_name ); ?></td>
                  <td style="color:#6b7280;"><?php echo esc_html( (string) $row->email ); ?></td>
                <?php elseif ( 'company' === $entity_type ) : ?>
                  <td><?php echo esc_html( (string) $row->city ); ?></td>
                <?php elseif ( 'formation' === $entity_type ) : ?>
                  <td><?php echo esc_html( (string) $row->code ); ?></td>
                <?php elseif ( 'session' === $entity_type ) : ?>
                  <td><span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700;background:#dbeafe;color:#1e40af;"><?php echo esc_html( ucfirst( (string) $row->status ) ); ?></span></td>
                <?php endif; ?>
                <td style="color:#6b7280;"><?php echo esc_html( $nb_inscriptions ); ?></td>
                <td>
                  <a href="<?php echo esc_url( $fiche_url ); ?>" class="acdc-button acdc-button-soft" style="font-size:12px;padding:5px 12px;">Ouvrir le dossier →</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ( $total_pages > 1 ) : ?>
          <div class="acdc-pagination-wrap" style="margin-top:16px;">
            <div class="acdc-pagination">
              <?php for ( $page = 1; $page <= $total_pages; $page++ ) :
                $url = add_query_arg( array( 'trf_view' => $trf_view, 'paged' => $page, 'per_page' => $per_page, 'q' => $search ), $base_url );
              ?>
                <a href="<?php echo esc_url( $url ); ?>" class="<?php echo $page === $paged ? 'is-active' : ''; ?>"><?php echo esc_html( $page ); ?></a>
              <?php endfor; ?>
            </div>
            <div class="acdc-pagination-summary"><?php echo esc_html( sprintf( '%d-%d de %d', $total ? $offset + 1 : 0, min( $offset + $per_page, $total ), $total ) ); ?></div>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php
  }

  /**
   * ACDC 3.25.03 — Fiche dossier par entité avec tous les onglets filtrés.
   */
  private function render_dossier_entity_fiche( $entity_type, $entity_id, $subtab, $base_url, $trf_view ) {
    global $wpdb;

    $entity_id = absint( $entity_id );
    $list_url  = add_query_arg( array( 'trf_view' => $trf_view ), $base_url );

    // ── Charger l'entité ──────────────────────────────────────────────────
    switch ( $entity_type ) {
      case 'learner':
        $entity = $this->get_learner( $entity_id );
        $entity_title = $entity ? trim( (string) $entity->first_name . ' ' . (string) $entity->last_name ) : '#' . $entity_id;
        $entity_meta  = $entity ? esc_html( (string) $entity->company_name ) : '';
        break;
      case 'company':
        $entity = $this->get_company( $entity_id );
        $entity_title = $entity ? (string) $entity->name : '#' . $entity_id;
        $entity_meta  = $entity ? esc_html( (string) ( $entity->city ?? '' ) ) : '';
        break;
      case 'formation':
        $entity = $this->get_formation( $entity_id );
        $entity_title = $entity ? (string) $entity->title : '#' . $entity_id;
        $entity_meta  = $entity ? esc_html( (string) ( $entity->code ?? '' ) ) : '';
        break;
      case 'session':
        $entity = $this->get_session( $entity_id );
        if ( $entity ) {
          $f_row = $entity->formation_id ? $this->get_formation( (int) $entity->formation_id ) : null;
          $entity_title = ( $f_row ? (string) $f_row->title : 'Séance' ) . ' — ' . ( ! empty( $entity->start_date ) ? wp_date( 'd/m/Y', strtotime( (string) $entity->start_date ) ) : '?' );
        } else {
          $entity_title = '#' . $entity_id;
        }
        $entity_meta = $entity ? ucfirst( (string) $entity->status ) : '';
        break;
      default:
        $entity = null; $entity_title = '#' . $entity_id; $entity_meta = '';
    }

    // ── Récupérer toutes les inscriptions liées ───────────────────────────
    if ( 'learner' === $entity_type ) {
      $registrations = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$this->training_registration_table} WHERE learner_id = %d ORDER BY created_at DESC", $entity_id
      ) );
    } elseif ( 'company' === $entity_type ) {
      $registrations = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$this->training_registration_table} WHERE company_id = %d ORDER BY created_at DESC", $entity_id
      ) );
    } elseif ( 'formation' === $entity_type ) {
      $registrations = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$this->training_registration_table} WHERE formation_id = %d ORDER BY created_at DESC", $entity_id
      ) );
    } elseif ( 'session' === $entity_type ) {
      // Apprenants de la séance → leurs inscriptions
      $learner_ids_sess = $wpdb->get_col( $wpdb->prepare(
        "SELECT id FROM {$this->learner_table} WHERE session_id = %d", $entity_id
      ) );
      $registrations = array();
      if ( ! empty( $learner_ids_sess ) ) {
        $ph = implode( ',', array_fill( 0, count( $learner_ids_sess ), '%d' ) );
        $registrations = $wpdb->get_results( $wpdb->prepare(
          "SELECT * FROM {$this->training_registration_table} WHERE learner_id IN ($ph) ORDER BY created_at DESC",
          ...$learner_ids_sess
        ) );
      }
    } else {
      $registrations = array();
    }

    // IDs utiles pour les requêtes croisées
    $all_learner_ids  = array();
    $all_company_ids  = array();
    $all_formation_ids = array();
    foreach ( $registrations as $r ) {
      if ( ! empty( $r->learner_id ) )  { $all_learner_ids[]   = (int) $r->learner_id; }
      if ( ! empty( $r->company_id ) )  { $all_company_ids[]   = (int) $r->company_id; }
      if ( ! empty( $r->formation_id ) ){ $all_formation_ids[] = (int) $r->formation_id; }
    }
    $all_learner_ids   = array_values( array_unique( array_filter( $all_learner_ids ) ) );
    $all_company_ids   = array_values( array_unique( array_filter( $all_company_ids ) ) );
    $all_formation_ids = array_values( array_unique( array_filter( $all_formation_ids ) ) );
    if ( 'learner' === $entity_type )   { $all_learner_ids[]   = $entity_id; $all_learner_ids = array_unique( $all_learner_ids ); }
    if ( 'company' === $entity_type )   { $all_company_ids[]   = $entity_id; $all_company_ids = array_unique( $all_company_ids ); }
    if ( 'formation' === $entity_type ) { $all_formation_ids[] = $entity_id; $all_formation_ids = array_unique( $all_formation_ids ); }

    $subtab_labels = array(
      'synthesis'   => 'Synthèse',
      'need'        => 'Analyse du besoin',
      'objectives'  => 'Objectifs et programme',
      'positioning' => 'Positionnement initial',
      'contract'    => 'Contractualisation',
      'welcome'     => 'Convocation / accueil',
      'sessions'    => 'Séances',
      'support'     => 'Suivi et accompagnement',
      'evaluations' => 'Évaluations et enquêtes',
      'documents'   => 'Documents et preuves',
      'complaints'  => 'Réclamations / incidents / amélioration continue',
      'compliance'  => 'Handicap / sous-traitance / intervenants',
      'quality'     => 'Veilles / indicateurs qualité / audit interne',
      'public_info' => 'Information publique et publication',
      'accounting'  => 'Comptabilité et reporting',
      'history'     => 'Historique',
    );

    $mk_url = function( $st ) use ( $base_url, $trf_view, $entity_id ) {
      return add_query_arg( array( 'trf_view' => $trf_view, 'trf_id' => $entity_id, 'entity_subtab' => $st ), $base_url );
    };
    ?>
    <div class="acdc-panel acdc-mb-18">
      <div class="acdc-inline-wrap acdc-inline-wrap-between" style="justify-content:space-between;align-items:flex-start;gap:18px;">
        <div>
          <p class="acdc-kicker">Dossier <?php echo esc_html( ucfirst( $entity_type ) ); ?></p>
          <h3 style="margin:4px 0 6px;"><?php echo esc_html( $entity_title ); ?></h3>
          <?php if ( $entity_meta ) : ?><p style="margin:0;color:#5b6472;"><?php echo esc_html( $entity_meta ); ?></p><?php endif; ?>
          <p style="margin:4px 0 0;color:#9ca3af;font-size:12px;"><?php echo esc_html( count( $registrations ) ); ?> inscription(s) liée(s)</p>
        </div>
        <div>
          <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $list_url ); ?>">← Retour à la liste</a>
        </div>
      </div>
      <div class="acdc-inline-wrap" style="margin-top:16px;gap:8px;flex-wrap:wrap;">
        <?php foreach ( $subtab_labels as $st => $lbl ) : ?>
          <a class="acdc-button <?php echo $subtab === $st ? 'acdc-button-primary' : 'acdc-button-soft'; ?>"
             href="<?php echo esc_url( $mk_url( $st ) ); ?>">
            <?php echo esc_html( $lbl ); ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <style>
      .acdc-def-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 20px;}
      .acdc-def-row{display:flex;flex-direction:column;gap:3px;padding:8px 0;border-bottom:1px solid #f1f5f9;}
      .acdc-def-row:last-child{border-bottom:none;}
      .acdc-def-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#9ca3af;}
      .acdc-def-value{font-size:13px;color:#1f2937;}
      .acdc-piece-table{width:100%;border-collapse:collapse;font-size:12.5px;}
      .acdc-piece-table th{padding:7px 10px;text-align:left;color:#0f2c52;font-weight:700;border-bottom:2px solid #e2e8f0;background:#f8fafc;}
      .acdc-piece-table td{padding:9px 10px;border-bottom:1px solid #f1f5f9;vertical-align:top;}
      .acdc-piece-table tr:last-child td{border-bottom:none;}
      .acdc-badge-sm{display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700;}
      .acdc-empty-tab{padding:32px 20px;text-align:center;color:#9ca3af;font-size:13px;}
    </style>

    <?php
    // ═══════════════════════════════════════════════════════════════════
    // ONGLET : ANALYSES DU BESOIN
    // ═══════════════════════════════════════════════════════════════════
    if ( 'need' === $subtab ) :
      // NAD des apprenants liés
      $nads_app = array();
      if ( ! empty( $all_learner_ids ) ) {
        $ph = implode( ',', array_fill( 0, count( $all_learner_ids ), '%d' ) );
        $nads_app = $wpdb->get_results( $wpdb->prepare(
          "SELECT n.*, CONCAT(COALESCE(l.first_name,''),' ',COALESCE(l.last_name,'')) AS learner_name
           FROM {$this->need_analysis_table} n
           LEFT JOIN {$this->learner_table} l ON l.id = n.apprenant_id
           WHERE n.apprenant_id IN ($ph) AND n.is_model = 0
           ORDER BY n.apprenant_id, n.id DESC",
          ...$all_learner_ids
        ) );
      }
      // NAD entreprise (commanditaire) liées
      $nads_cdt = array();
      if ( ! empty( $all_company_ids ) ) {
        $ph = implode( ',', array_fill( 0, count( $all_company_ids ), '%d' ) );
        $nads_cdt = $wpdb->get_results( $wpdb->prepare(
          "SELECT n.*, COALESCE(c.name,'') AS company_name
           FROM {$this->need_analysis_table} n
           LEFT JOIN {$this->company_table} c ON c.id = n.entreprise_id
           WHERE n.entreprise_id IN ($ph) AND n.is_model = 0
           AND (n.apprenant_id IS NULL OR n.apprenant_id = 0)
           ORDER BY n.entreprise_id, n.id DESC",
          ...$all_company_ids
        ) );
      }
      $all_nads = array_merge( $nads_cdt, $nads_app );
      $statut_map = array(
        'soumise'  => array( '#d1fae5','#065f46','✅ Soumise' ),
        'envoyee'  => array( '#dbeafe','#1e40af','📨 Envoyée' ),
        'expire'   => array( '#fee2e2','#991b1b','⛔ Expirée' ),
        'brouillon'=> array( '#f3f4f6','#6b7280','📝 Brouillon' ),
      );
    ?>
      <div class="acdc-panel">
        <h4 style="margin:0 0 14px;color:#0f2c52;">Analyses du besoin</h4>
        <?php if ( empty( $all_nads ) ) : ?>
          <div class="acdc-empty-tab">Aucune analyse du besoin liée.</div>
        <?php else : ?>
          <table class="acdc-piece-table">
            <thead><tr>
              <th>Qui</th><th>Analyse</th><th>Type</th><th>Statut</th><th>Date</th><th>Document</th>
            </tr></thead>
            <tbody>
            <?php foreach ( $all_nads as $n ) :
              $is_app = ! empty( $n->apprenant_id );
              $who    = $is_app ? trim( (string) $n->learner_name ) : ( isset( $n->company_name ) ? (string) $n->company_name : '—' );
              if ( '' === $who ) { $who = ! empty( $n->repondant_prenom ) ? trim( $n->repondant_prenom . ' ' . $n->repondant_nom ) : '—'; }
              $st  = (string) $n->statut;
              $sc  = isset( $statut_map[$st] ) ? $statut_map[$st] : array('#f3f4f6','#6b7280',ucfirst($st));
              $doc = ! empty( $n->document_url_apprenant ) ? $n->document_url_apprenant : ( ! empty( $n->document_url_commanditaire ) ? $n->document_url_commanditaire : '' );
              $date_n = ! empty( $n->sent_at ) ? wp_date( 'd/m/Y', strtotime( $n->sent_at ) ) : '—';
            ?>
              <tr>
                <td style="font-weight:600;"><?php echo esc_html( $who ); ?></td>
                <td><?php echo esc_html( (string) $n->title ); ?></td>
                <td><span class="acdc-badge-sm" style="background:<?php echo $is_app ? '#ede9fe' : '#dbeafe'; ?>;color:<?php echo $is_app ? '#5b21b6' : '#1e40af'; ?>;"><?php echo $is_app ? 'Apprenant' : 'Commanditaire'; ?></span></td>
                <td><span class="acdc-badge-sm" style="background:<?php echo esc_attr($sc[0]); ?>;color:<?php echo esc_attr($sc[1]); ?>;"><?php echo esc_html($sc[2]); ?></span></td>
                <td style="color:#6b7280;"><?php echo esc_html( $date_n ); ?></td>
                <td><?php if ( $doc ) : ?><a href="<?php echo esc_url($doc); ?>" target="_blank" style="color:#8b5b23;font-weight:600;">📄</a><?php else: ?>—<?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    <?php
    // ═══════════════════════════════════════════════════════════════════
    // ONGLET : CONVENTIONS / CONTRATS
    // ═══════════════════════════════════════════════════════════════════
    elseif ( 'contract' === $subtab ) :
      if ( 'learner' === $entity_type ) {
        $contracts = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->registration_contract_table} WHERE FIND_IN_SET(%d, REPLACE(learner_ids,' ','')) ORDER BY id DESC", $entity_id ) );
      } elseif ( 'company' === $entity_type ) {
        $contracts = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->registration_contract_table} WHERE company_id = %d ORDER BY id DESC", $entity_id ) );
      } elseif ( 'formation' === $entity_type ) {
        $contracts = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->registration_contract_table} WHERE formation_id = %d ORDER BY id DESC", $entity_id ) );
      } elseif ( 'session' === $entity_type && ! empty( $learner_ids_sess ) ) {
        $ph = implode( ',', array_fill( 0, count( $learner_ids_sess ), '%d' ) );
        $contracts = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT rc.* FROM {$this->registration_contract_table} rc INNER JOIN {$this->training_registration_table} tr ON tr.contract_id = rc.id WHERE tr.learner_id IN ($ph) ORDER BY rc.id DESC", ...$learner_ids_sess ) );
      } else { $contracts = array(); }
    ?>
      <div class="acdc-panel">
        <h4 style="margin:0 0 14px;color:#0f2c52;">Conventions et contrats</h4>
        <?php if ( empty( $contracts ) ) : ?>
          <div class="acdc-empty-tab">Aucune convention liée.</div>
        <?php else : ?>
          <table class="acdc-piece-table">
            <thead><tr><th>Référence</th><th>Titre</th><th>Date début</th><th>Signature</th><th>Document</th></tr></thead>
            <tbody>
            <?php foreach ( $contracts as $c ) :
              $sig = (string) $c->signature_status;
              $sig_colors = array( 'signe'=>array('#d1fae5','#065f46','✅ Signé'), 'envoye'=>array('#dbeafe','#1e40af','⏳ Envoyé'), '' => array('#f3f4f6','#6b7280','—') );
              $sc = isset($sig_colors[$sig]) ? $sig_colors[$sig] : array('#fef3c7','#92400e',$sig);
              $doc = ! empty($c->signed_document_url) ? $c->signed_document_url : (! empty($c->document_url) ? $c->document_url : '');
            ?>
              <tr>
                <td style="color:#9ca3af;">#<?php echo (int)$c->id; ?></td>
                <td style="font-weight:600;"><?php echo esc_html((string)$c->title); ?></td>
                <td><?php echo ! empty($c->start_date) ? esc_html(wp_date('d/m/Y',strtotime($c->start_date))) : '—'; ?></td>
                <td><span class="acdc-badge-sm" style="background:<?php echo esc_attr($sc[0]); ?>;color:<?php echo esc_attr($sc[1]); ?>;"><?php echo esc_html($sc[2]); ?></span></td>
                <td><?php if($doc): ?><a href="<?php echo esc_url($doc); ?>" target="_blank" style="color:#8b5b23;font-weight:600;">📄</a><?php else: ?>—<?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    <?php
    // ═══════════════════════════════════════════════════════════════════
    // ONGLET : CONVOCATIONS
    // ═══════════════════════════════════════════════════════════════════
    elseif ( 'convocation' === $subtab ) :
      $convocations = array();
      foreach ( $registrations as $r ) {
        if ( ! empty( $r->convocation_document_url ) ) {
          $l_name = '';
          if ( ! empty( $r->learner_id ) ) {
            $l = $this->get_learner( (int) $r->learner_id );
            if ( $l ) { $l_name = trim( $l->first_name . ' ' . $l->last_name ); }
          }
          $convocations[] = array(
            'registration_id' => (int) $r->id,
            'learner'         => $l_name ?: sanitize_text_field( (string) $r->learner_label ),
            'formation'       => sanitize_text_field( (string) $r->formation_title ),
            'url'             => (string) $r->convocation_document_url,
          );
        }
      }
    ?>
      <div class="acdc-panel">
        <h4 style="margin:0 0 14px;color:#0f2c52;">Convocations</h4>
        <?php if ( empty( $convocations ) ) : ?>
          <div class="acdc-empty-tab">Aucune convocation produite.</div>
        <?php else : ?>
          <table class="acdc-piece-table">
            <thead><tr><th>Apprenant</th><th>Formation</th><th>Document</th></tr></thead>
            <tbody>
            <?php foreach ( $convocations as $cv ) : ?>
              <tr>
                <td style="font-weight:600;"><?php echo esc_html($cv['learner']); ?></td>
                <td><?php echo esc_html($cv['formation']); ?></td>
                <td><a href="<?php echo esc_url($cv['url']); ?>" target="_blank" style="color:#8b5b23;font-weight:600;">📄 Voir</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    <?php
    // ═══════════════════════════════════════════════════════════════════
    // ONGLET : SÉANCES
    // ═══════════════════════════════════════════════════════════════════
    elseif ( 'sessions' === $subtab ) :
      $session_ids = array();
      if ( 'session' === $entity_type ) {
        $session_ids = array( $entity_id );
      } elseif ( ! empty( $all_learner_ids ) ) {
        $ph = implode( ',', array_fill( 0, count( $all_learner_ids ), '%d' ) );
        $rows_sess = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT session_id FROM {$this->learner_table} WHERE id IN ($ph) AND session_id IS NOT NULL", ...$all_learner_ids ) );
        foreach ( $rows_sess as $rs ) { if ( $rs->session_id ) { $session_ids[] = (int)$rs->session_id; } }
      } elseif ( ! empty( $all_formation_ids ) ) {
        $ph = implode( ',', array_fill( 0, count( $all_formation_ids ), '%d' ) );
        $rows_sess = $wpdb->get_results( $wpdb->prepare( "SELECT id AS session_id FROM {$this->session_table} WHERE formation_id IN ($ph)", ...$all_formation_ids ) );
        foreach ( $rows_sess as $rs ) { if ( $rs->session_id ) { $session_ids[] = (int)$rs->session_id; } }
      }
      $session_ids = array_values( array_unique( array_filter( $session_ids ) ) );
      $sessions_data = array();
      foreach ( $session_ids as $sid ) {
        $s = $this->get_session( $sid );
        if ( $s ) {
          $f_s = ! empty( $s->formation_id ) ? $this->get_formation( (int)$s->formation_id ) : null;
          $nb_l = (int)$wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->learner_table} WHERE session_id = %d", $sid ) );
          $sessions_data[] = array( 'session' => $s, 'formation' => $f_s, 'nb_learners' => $nb_l );
        }
      }
    ?>
      <div class="acdc-panel">
        <h4 style="margin:0 0 14px;color:#0f2c52;">Séances</h4>
        <?php if ( empty( $sessions_data ) ) : ?>
          <div class="acdc-empty-tab">Aucune séance liée.</div>
        <?php else : ?>
          <table class="acdc-piece-table">
            <thead><tr><th>Formation</th><th>Date début</th><th>Statut</th><th>Apprenants</th></tr></thead>
            <tbody>
            <?php foreach ( $sessions_data as $sd ) :
              $s = $sd['session']; $f_s = $sd['formation'];
              $stat_sess = (string)$s->status;
              $scolors = array('planifiee'=>array('#dbeafe','#1e40af'),'en_cours'=>array('#d1fae5','#065f46'),'termine'=>array('#f3f4f6','#6b7280'));
              $sc_s = isset($scolors[$stat_sess]) ? $scolors[$stat_sess] : array('#f3f4f6','#6b7280');
            ?>
              <tr>
                <td style="font-weight:600;"><?php echo esc_html( $f_s ? (string)$f_s->title : '—' ); ?></td>
                <td><?php echo ! empty($s->start_date) ? esc_html(wp_date('d/m/Y',strtotime($s->start_date))) : '—'; ?></td>
                <td><span class="acdc-badge-sm" style="background:<?php echo esc_attr($sc_s[0]); ?>;color:<?php echo esc_attr($sc_s[1]); ?>;"><?php echo esc_html(ucfirst($stat_sess)); ?></span></td>
                <td><?php echo esc_html($sd['nb_learners']); ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    <?php
    // ═══════════════════════════════════════════════════════════════════
    // ONGLET : ÉVALUATIONS ET ENQUÊTES
    // ═══════════════════════════════════════════════════════════════════
    elseif ( 'evaluations' === $subtab ) :
      $eval_items = array();
      $doc_types_eval = array(
        'positioning_result_document_url' => 'Test de positionnement',
        'mid_survey_document_url'         => 'Enquête intermédiaire',
        'hot_survey_document_url'         => 'Enquête à chaud',
        'cold_survey_document_url'        => 'Enquête à froid',
        'evaluation_result_document_url'  => 'Résultat évaluation',
      );
      foreach ( $registrations as $r ) {
        $l = null;
        if ( ! empty( $r->learner_id ) ) { $l = $this->get_learner( (int)$r->learner_id ); }
        $l_name = $l ? trim($l->first_name.' '.$l->last_name) : sanitize_text_field((string)$r->learner_label);
        foreach ( $doc_types_eval as $col => $type_label ) {
          if ( ! empty( $r->$col ) ) {
            $eval_items[] = array( 'learner'=>$l_name, 'formation'=>(string)$r->formation_title, 'type'=>$type_label, 'url'=>(string)$r->$col );
          }
        }
      }
      // + enquêtes questionnaire liées aux formations/séances
      if ( ! empty( $all_formation_ids ) ) {
        $ph = implode(',', array_fill(0,count($all_formation_ids),'%d'));
        $qs = $wpdb->get_results($wpdb->prepare("SELECT session_title, session_date, status, public_url FROM {$this->questionnaire_session_table} WHERE formation_id IN ($ph) ORDER BY id DESC",...$all_formation_ids));
        foreach((array)$qs as $q){ $eval_items[] = array('learner'=>'—','formation'=>'','type'=>'Enquête : '.$q->session_title,'url'=>(string)$q->public_url); }
      }
    ?>
      <div class="acdc-panel">
        <h4 style="margin:0 0 14px;color:#0f2c52;">Évaluations et enquêtes</h4>
        <?php if ( empty( $eval_items ) ) : ?>
          <div class="acdc-empty-tab">Aucune évaluation ou enquête produite.</div>
        <?php else : ?>
          <table class="acdc-piece-table">
            <thead><tr><th>Apprenant</th><th>Type</th><th>Formation</th><th>Document</th></tr></thead>
            <tbody>
            <?php foreach ($eval_items as $ei) : ?>
              <tr>
                <td style="font-weight:600;"><?php echo esc_html($ei['learner']); ?></td>
                <td><span class="acdc-badge-sm" style="background:#fce7f3;color:#9d174d;"><?php echo esc_html($ei['type']); ?></span></td>
                <td style="color:#6b7280;"><?php echo esc_html($ei['formation']); ?></td>
                <td><?php if(!empty($ei['url'])): ?><a href="<?php echo esc_url($ei['url']); ?>" target="_blank" style="color:#8b5b23;font-weight:600;">📄</a><?php else: ?>—<?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    <?php
    // ═══════════════════════════════════════════════════════════════════
    // ONGLET : DOCUMENTS ET PREUVES
    // ═══════════════════════════════════════════════════════════════════
    elseif ( 'documents' === $subtab ) :
      $doc_items = array();
      $all_doc_types = array(
        'convocation_document_url'              => 'Convocation',
        'positioning_result_document_url'       => 'Positionnement',
        'mid_survey_document_url'               => 'Enquête inter.',
        'hot_survey_document_url'               => 'Enquête chaud',
        'cold_survey_document_url'              => 'Enquête froid',
        'evaluation_result_document_url'        => 'Évaluation',
        'completion_certificate_document_url'   => 'Attestation présentiel',
        'end_training_certificate_document_url' => 'Attestation fin',
      );
      foreach ( $registrations as $r ) {
        $l = null;
        if ( ! empty( $r->learner_id ) ) { $l = $this->get_learner( (int)$r->learner_id ); }
        $l_name = $l ? trim($l->first_name.' '.$l->last_name) : sanitize_text_field((string)$r->learner_label);
        foreach ( $all_doc_types as $col => $type_label ) {
          if ( ! empty( $r->$col ) ) {
            $doc_items[] = array( 'learner'=>$l_name, 'type'=>$type_label, 'formation'=>(string)$r->formation_title, 'url'=>(string)$r->$col );
          }
        }
      }
    ?>
      <div class="acdc-panel">
        <h4 style="margin:0 0 14px;color:#0f2c52;">Tous les documents</h4>
        <?php if ( empty( $doc_items ) ) : ?>
          <div class="acdc-empty-tab">Aucun document produit.</div>
        <?php else : ?>
          <table class="acdc-piece-table">
            <thead><tr><th>Apprenant</th><th>Type</th><th>Formation</th><th>Document</th></tr></thead>
            <tbody>
            <?php foreach ( $doc_items as $di ) : ?>
              <tr>
                <td style="font-weight:600;"><?php echo esc_html($di['learner']); ?></td>
                <td><span class="acdc-badge-sm" style="background:#f3f4f6;color:#374151;"><?php echo esc_html($di['type']); ?></span></td>
                <td style="color:#6b7280;"><?php echo esc_html($di['formation']); ?></td>
                <td><a href="<?php echo esc_url($di['url']); ?>" target="_blank" style="color:#8b5b23;font-weight:600;">📄</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    <?php
    // ═══════════════════════════════════════════════════════════════════
    // ONGLET : ÉMARGEMENTS
    // ═══════════════════════════════════════════════════════════════════
    elseif ( 'emargement' === $subtab ) :
      $sig_table = $wpdb->prefix . 'acdc_sig_requests';
      $emargements = array();
      if ( ! empty( $all_learner_ids ) ) {
        $ph = implode(',', array_fill(0,count($all_learner_ids),'%d'));
        $emargements = $wpdb->get_results( $wpdb->prepare(
          "SELECT sr.*, CONCAT(COALESCE(l.first_name,''),' ',COALESCE(l.last_name,'')) AS learner_name
           FROM $sig_table sr
           LEFT JOIN {$this->learner_table} l ON l.id = sr.learner_id
           WHERE sr.learner_id IN ($ph) AND sr.doc_type = 'emargement'
           ORDER BY sr.id DESC",
          ...$all_learner_ids
        ) );
      } elseif ( 'session' === $entity_type ) {
        $emargements = $wpdb->get_results( $wpdb->prepare(
          "SELECT sr.*, CONCAT(COALESCE(l.first_name,''),' ',COALESCE(l.last_name,'')) AS learner_name
           FROM $sig_table sr
           LEFT JOIN {$this->learner_table} l ON l.id = sr.learner_id
           WHERE sr.session_id = %d AND sr.doc_type = 'emargement'
           ORDER BY sr.id DESC",
          $entity_id
        ) );
      }
    ?>
      <div class="acdc-panel">
        <h4 style="margin:0 0 14px;color:#0f2c52;">Feuilles d'émargement</h4>
        <?php if ( empty( $emargements ) ) : ?>
          <div class="acdc-empty-tab">Aucun émargement enregistré.</div>
        <?php else : ?>
          <table class="acdc-piece-table">
            <thead><tr><th>Apprenant</th><th>Statut</th><th>Signé le</th><th>Document</th></tr></thead>
            <tbody>
            <?php foreach ( $emargements as $em ) :
              $st = (string)$em->status;
              $sc_em = 'signe' === $st ? array('#d1fae5','#065f46','✅ Signé') : array('#fef3c7','#92400e','⏳ En attente');
              $doc_em = ! empty($em->signed_doc_url) ? $em->signed_doc_url : (! empty($em->doc_url) ? $em->doc_url : '');
            ?>
              <tr>
                <td style="font-weight:600;"><?php echo esc_html(trim((string)$em->learner_name)); ?></td>
                <td><span class="acdc-badge-sm" style="background:<?php echo esc_attr($sc_em[0]); ?>;color:<?php echo esc_attr($sc_em[1]); ?>;"><?php echo esc_html($sc_em[2]); ?></span></td>
                <td style="color:#6b7280;"><?php echo !empty($em->signed_at) ? esc_html(mysql2date('d/m/Y H:i',$em->signed_at)) : '—'; ?></td>
                <td><?php if($doc_em): ?><a href="<?php echo esc_url($doc_em); ?>" target="_blank" style="color:#8b5b23;font-weight:600;">📄</a><?php else: ?>—<?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    <?php
    // ═══════════════════════════════════════════════════════════════════
    // ONGLET SYNTHÈSE — rendu identique à la liste "Inscriptions"
    // filtré sur les inscriptions déjà chargées pour cette entité
    // ═══════════════════════════════════════════════════════════════════
    elseif ( 'synthesis' === $subtab ) :
      $wf_labels_syn = $this->get_workflow_status_labels();
      $wf_colors_syn = $this->get_workflow_status_colors();
      $nonce_syn     = wp_create_nonce( 'acdc_wf_status' );
      $ajax_url_syn  = esc_js( admin_url( 'admin-ajax.php' ) );
    ?>
      <div class="acdc-panel">
        <div class="acdc-table-wrap">
          <table class="acdc-table">
            <thead>
              <tr>
                <th>Intitulé</th>
                <th>Formation</th>
                <th>Inscription</th>
                <th>Commanditaire</th>
                <th>Statut</th>
                <th>Modifié le</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php if ( ! empty( $registrations ) ) : foreach ( $registrations as $entry ) :
              $wf_status_syn = ! empty( $entry->workflow_status ) ? (string) $entry->workflow_status : 'prospect_cree';
              $wf_label_syn  = isset( $wf_labels_syn[ $wf_status_syn ] ) ? $wf_labels_syn[ $wf_status_syn ] : $wf_status_syn;
              $wf_color_syn  = isset( $wf_colors_syn[ $wf_status_syn ] ) ? $wf_colors_syn[ $wf_status_syn ] : '#6b7280';
              $comp_syn      = $this->compute_registration_completude_score( $entry );
              $comp_pct_syn  = $comp_syn['percent'];
              $comp_dot_syn  = $comp_pct_syn >= 75 ? '#35b37e' : ( $comp_pct_syn >= 40 ? '#f0b45e' : '#e06d6d' );
              /* Badge PSH */
              $psh_syn = 'none';
              if ( ! empty( $entry->id ) ) {
                $psh_nad_syn = $wpdb->get_row( $wpdb->prepare(
                  "SELECT reponses FROM {$this->need_analysis_table} WHERE dossier_id = %d AND statut = 'traite' ORDER BY id DESC LIMIT 1",
                  (int) $entry->id
                ) );
                if ( $psh_nad_syn && ! empty( $psh_nad_syn->reponses ) ) {
                  $psh_rep_syn = json_decode( $psh_nad_syn->reponses, true );
                  if ( is_array( $psh_rep_syn ) ) {
                    if ( isset( $psh_rep_syn['com_handicap'] ) && 'Oui' === $psh_rep_syn['com_handicap'] ) {
                      $psh_syn = 'high';
                    } elseif ( ! empty( $psh_rep_syn['app_difficultes'] ) && '' !== trim( $psh_rep_syn['app_difficultes'] ) ) {
                      $psh_syn = 'medium';
                    }
                  }
                }
              }
              $view_url_syn   = is_admin() ? admin_url( 'admin.php?page=acdc-of-register-training&action=view&item_id=' . (int) $entry->id ) : $this->portal_page_url( array( 'tab' => 'register_training', 'action' => 'view', 'item_id' => (int) $entry->id ) );
              $edit_url_syn   = is_admin() ? admin_url( 'admin.php?page=acdc-of-register-training&action=edit&item_id=' . (int) $entry->id ) : $this->portal_page_url( array( 'tab' => 'register_training', 'action' => 'edit', 'item_id' => (int) $entry->id ) );
              $delete_url_syn = admin_url( 'admin-post.php?action=acdc_delete_training_registration&registration_id=' . (int) $entry->id );
            ?>
              <tr>
                <td><?php echo esc_html( $entry->title ); ?></td>
                <td><?php echo esc_html( $entry->formation_title ); ?></td>
                <td><?php echo esc_html( $this->get_training_registration_subject_label( $entry->belongs_to_group, $entry->group_label, $entry->learner_label, $entry->learners_label ) ); ?></td>
                <td><?php echo esc_html( $entry->company_label ? $entry->company_label : '—' ); ?></td>
                <td>
                  <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                    <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;color:#fff;background:<?php echo esc_attr( $wf_color_syn ); ?>;white-space:nowrap;"><?php echo esc_html( $wf_label_syn ); ?></span>
                    <span title="Complétude : <?php echo (int) $comp_pct_syn; ?>%" style="display:inline-flex;align-items:center;gap:3px;font-size:11px;font-weight:700;color:<?php echo esc_attr( $comp_dot_syn ); ?>;white-space:nowrap;">
                      <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?php echo esc_attr( $comp_dot_syn ); ?>;"></span><?php echo (int) $comp_pct_syn; ?>%
                    </span>
                    <?php if ( 'high' === $psh_syn ) : ?>
                    <span title="Situation de handicap déclarée dans le recueil des besoins" style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;background:#e06d6d;color:#fff;white-space:nowrap;">♿ PSH</span>
                    <?php elseif ( 'medium' === $psh_syn ) : ?>
                    <span title="Difficultés spécifiques (Dys/TDAH) signalées dans le recueil des besoins" style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;background:#f0b45e;color:#fff;white-space:nowrap;">⚠ Dys</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td><?php echo esc_html( mysql2date( 'j F Y \\à H\\hi', $entry->updated_at ) ); ?></td>
                <td>
                  <div class="acdc-3dots-wrap" style="position:relative;display:inline-block;">
                    <button type="button" class="acdc-3dots-btn" aria-label="Actions" style="background:none;border:none;cursor:pointer;padding:4px 8px;color:#1e4777;">&#8943;</button>
                    <div class="acdc-3dots-menu" hidden style="position:absolute;right:0;top:100%;z-index:9999;background:#fff;border:1px solid #dfe5ee;border-radius:10px;box-shadow:0 4px 14px rgba(28,44,64,.10);min-width:210px;padding:6px 0;">
                      <a href="<?php echo esc_url( $view_url_syn ); ?>" style="display:block;padding:8px 16px;font-size:13px;color:#0f2c52;text-decoration:none;white-space:nowrap;">Voir</a>
                      <a href="<?php echo esc_url( $edit_url_syn ); ?>" style="display:block;padding:8px 16px;font-size:13px;color:#0f2c52;text-decoration:none;white-space:nowrap;">Modifier</a>
                      <div style="border-top:1px solid #f0e6dc;margin:4px 0;"></div>
                      <div style="padding:4px 16px 2px;font-size:11px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.05em;">Changer le statut</div>
                      <?php foreach ( $this->get_workflow_status_list() as $st_syn ) :
                        $st_syn_label   = isset( $wf_labels_syn[ $st_syn ] ) ? $wf_labels_syn[ $st_syn ] : $st_syn;
                        $st_syn_color   = isset( $wf_colors_syn[ $st_syn ] ) ? $wf_colors_syn[ $st_syn ] : '#6b7280';
                        $is_current_syn = $st_syn === $wf_status_syn;
                      ?>
                      <button type="button" class="acdc-wf-set-status"
                        data-registration-id="<?php echo esc_attr( (int) $entry->id ); ?>"
                        data-status="<?php echo esc_attr( $st_syn ); ?>"
                        style="display:block;width:100%;text-align:left;padding:6px 16px;font-size:12px;color:<?php echo esc_attr( $is_current_syn ? '#fff' : '#0f2c52' ); ?>;background:<?php echo esc_attr( $is_current_syn ? $st_syn_color : 'transparent' ); ?>;border:none;cursor:pointer;white-space:nowrap;<?php echo $is_current_syn ? 'font-weight:700;' : ''; ?>">
                        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?php echo esc_attr( $st_syn_color ); ?>;margin-right:8px;vertical-align:middle;"></span><?php echo esc_html( $st_syn_label ); ?>
                      </button>
                      <?php endforeach; ?>
                      <div style="border-top:1px solid #f0e6dc;margin:4px 0;"></div>
                      <a href="<?php echo esc_url( wp_nonce_url( $delete_url_syn, 'acdc_delete_training_registration_' . (int) $entry->id ) ); ?>" onclick="return confirm('Supprimer cette inscription ?');" style="display:block;padding:8px 16px;font-size:13px;color:#e06d6d;text-decoration:none;white-space:nowrap;">Supprimer</a>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; else : ?>
              <tr><td colspan="7">Aucune inscription liée à ce dossier.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <script>
      (function(){
        document.addEventListener('click', function(e){
          var btn = e.target.closest('.acdc-3dots-btn');
          if (btn) {
            e.stopPropagation();
            var menu = btn.nextElementSibling;
            if (!menu) return;
            document.querySelectorAll('.acdc-3dots-menu').forEach(function(m){ if (m !== menu) m.hidden = true; });
            menu.hidden = !menu.hidden;
            return;
          }
          document.querySelectorAll('.acdc-3dots-menu').forEach(function(m){ m.hidden = true; });
        });
        document.addEventListener('click', function(e){
          var btn = e.target.closest('.acdc-wf-set-status');
          if (!btn) return;
          var registrationId = btn.getAttribute('data-registration-id');
          var status = btn.getAttribute('data-status');
          if (!registrationId || !status) return;
          var menu = btn.closest('.acdc-3dots-menu');
          if (menu) menu.hidden = true;
          var data = new FormData();
          data.append('action', 'acdc_set_registration_workflow_status');
          data.append('registration_id', registrationId);
          data.append('status', status);
          data.append('nonce', '<?php echo $nonce_syn; ?>');
          fetch('<?php echo $ajax_url_syn; ?>', { method: 'POST', body: data })
            .then(function(r){ return r.json(); })
            .then(function(res){
              if (res && res.success) { window.location.reload(); }
              else { alert(res && res.data ? res.data : 'Erreur lors du changement de statut.'); }
            });
        });
      })();
      </script>

    <?php
    // ═══════════════════════════════════════════════════════════════════
    // ONGLETS EN COURS DE CONFIGURATION
    // Objectifs, Positionnement, Accueil, Suivi, Réclamations,
    // Handicap, Qualité, Info publique, Comptabilité, Historique
    // ═══════════════════════════════════════════════════════════════════
    else :
      $onglet_label = isset( $subtab_labels[ $entity_subtab ] ) ? $subtab_labels[ $entity_subtab ] : $entity_subtab;
      $nb_reg = count( $registrations );
    ?>
      <div class="acdc-panel">
        <h4 style="margin:0 0 16px;color:#0f2c52;"><?php echo esc_html( $onglet_label ); ?></h4>
        <?php if ( $nb_reg > 0 ) : ?>
          <div style="background:#fffbf0;border:1px solid #f0e6dc;border-left:4px solid #d6a353;border-radius:10px;padding:14px 18px;margin-bottom:16px;font-size:13px;color:#4b5d76;">
            <strong style="color:#8b5b23;">⚙️ Cet onglet est en cours de configuration.</strong><br>
            Il sera alimenté automatiquement par les pièces produites pour ce dossier.
            Ce dossier regroupe <strong><?php echo esc_html( $nb_reg ); ?> inscription(s)</strong> liée(s).
          </div>
          <table class="acdc-piece-table">
            <thead><tr><th>#</th><th>Inscription</th><th>Formation</th><th>Statut</th></tr></thead>
            <tbody>
            <?php foreach ( $registrations as $r ) :
              $view_url = add_query_arg( array( 'action' => 'view', 'item_id' => (int)$r->id ), is_admin() ? admin_url( 'admin.php?page=acdc-of-register-training' ) : $this->portal_page_url( array( 'tab' => 'training_files' ) ) );
              $st_label = $this->get_training_file_status_label( $r );
            ?>
              <tr>
                <td style="color:#9ca3af;">#<?php echo (int)$r->id; ?></td>
                <td style="font-weight:600;">
                  <a href="<?php echo esc_url( $view_url ); ?>" style="color:#0f2c52;text-decoration:none;">
                    <?php echo esc_html( sanitize_text_field( (string)$r->learner_label ?: $r->learners_label ?: $r->title ) ); ?> ↗
                  </a>
                </td>
                <td style="color:#374151;"><?php echo esc_html( (string)$r->formation_title ); ?></td>
                <td><span class="acdc-badge-sm" style="background:#f3f4f6;color:#374151;"><?php echo esc_html( $st_label ); ?></span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php else : ?>
          <div class="acdc-empty-tab">Aucune inscription liée à ce dossier.</div>
        <?php endif; ?>
      </div>
    <?php endif; // fin des onglets ?>
    <?php
  }




}
