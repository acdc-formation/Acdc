<?php
/**
 * ACDC Learners / Contacts — ACDC_Learners_Contacts_Render_Trait.
 *
 * Extraction micro-incrémentale du sous-bloc métier :
 * apprenants + contacts liés.
 *
 * @since 3.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Learners_Contacts_Render_Trait {


  private function render_front_contacts_tab( $action, $item_id ) {
    $contacts = $this->get_contacts();
    $companies = $this->get_companies();
    $contact  = $item_id ? $this->get_contact( $item_id ) : null;
    $prefill_company_id = isset( $_GET['company_id'] ) ? absint( wp_unslash( $_GET['company_id'] ) ) : 0;
    $page_title = $this->acdc_get_action_page_title( $action, 'Contacts', 'Créer un contact', 'Modifier un contact', 'Voir un contact' );
    ?>
    <section class="acdc-section-head">
      <div>
        <h2><?php echo esc_html( $page_title ); ?></h2>
        <p>Personnes rattachées aux entreprises.</p>
      </div>
      <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $this->portal_page_url( array( 'tab' => 'contacts', 'action' => 'new' ) ) ); ?>">Ajouter un contact</a>
    </section>
    <?php $this->render_front_contact_form( $contact, $action, $companies, $prefill_company_id ); ?>
    <div class="acdc-panel">
      <div class="acdc-table-wrap">
        <table class="acdc-table">
          <thead>
            <tr>
              <th>Nom</th>
              <th>Entreprise</th>
              <th>Fonction</th>
              <th>E-mail</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if ( ! empty( $contacts ) ) : ?>
            <?php foreach ( $contacts as $entry ) : ?>
              <tr>
                <td><?php echo esc_html( trim( $entry->first_name . ' ' . $entry->last_name ) ); ?></td>
                <td><?php echo esc_html( $entry->company_name ); ?></td>
                <td><?php echo esc_html( $entry->job_title ); ?></td>
                <td><?php echo esc_html( $entry->email ); ?></td>
                <td>
                  <?php /* ACDC 3.20.104 — Conversion liens texte → icônes inline 25px. */ ?>
                  <div class="acdc-groups-actions-inline">
                    <a class="acdc-row-action-icon acdc-row-edit-link" href="<?php echo esc_url( $this->portal_page_url( array( 'tab' => 'contacts', 'action' => 'edit', 'item_id' => $entry->id ) ) ); ?>" title="Modifier" aria-label="Modifier le contact">
                      <?php echo $this->render_inline_icon( 'edit', 25 ); ?>
                    </a>
                    <a class="acdc-row-action-icon acdc-row-delete-link" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_delete_contact&contact_id=' . $entry->id ), 'acdc_delete_contact_' . $entry->id ) ); ?>" title="Supprimer" aria-label="Supprimer le contact" onclick="return confirm('Supprimer ce contact ?');">
                      <?php echo $this->render_inline_icon( 'trash', 25 ); ?>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else : ?>
            <tr><td colspan="5">Aucun contact enregistré.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
  }


  private function render_front_contact_form( $contact, $action, $companies, $prefill_company_id ) {
    if ( ! in_array( $action, array( 'new', 'edit' ), true ) ) {
      return;
    }
    $state = $this->acdc_consume_form_state( 'contact' );
    $state_input = ( ! empty( $state['input'] ) && is_array( $state['input'] ) ) ? $state['input'] : array();
    $required_fields = $this->acdc_get_form_required_fields( $state );
    $value = function( $key, $default = '' ) use ( $contact, $state_input ) {
      if ( array_key_exists( $key, $state_input ) ) {
        return $state_input[ $key ];
      }
      return $contact && isset( $contact->$key ) ? $contact->$key : $default;
    };
    $selected_company = array_key_exists( 'company_id', $state_input ) ? (int) $state_input['company_id'] : ( $contact ? (int) $contact->company_id : $prefill_company_id );
    ?>
    <?php $this->acdc_render_form_validation_style( '.acdc-form' ); ?>
    <div class="acdc-panel acdc-form-panel">
      <h3>Informations du contact</h3>
      <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'acdc_save_contact' ); ?>
        <input type="hidden" name="action" value="acdc_save_contact">
        <input type="hidden" name="contact_id" value="<?php echo $contact ? esc_attr( $contact->id ) : 0; ?>">
        <div class="acdc-grid-2cols">
          <p>
            <label>Entreprise</label>
            <select name="company_id">
              <option value="0">— Aucune —</option>
              <?php foreach ( $companies as $company ) : ?>
                <option value="<?php echo esc_attr( $company->id ); ?>" <?php selected( $selected_company, $company->id ); ?>><?php echo esc_html( $company->name ); ?></option>
              <?php endforeach; ?>
            </select>
          </p>
          <p><label>Fonction</label><input type="text" name="job_title" value="<?php echo esc_attr( $value( 'job_title' ) ); ?>"></p>
          <p><label>Prénom</label><input type="text" name="first_name" required value="<?php echo esc_attr( $value( 'first_name' ) ); ?>"<?php echo $this->acdc_get_invalid_field_class( 'first_name', $required_fields ); ?>><?php echo $this->acdc_get_invalid_field_note( 'first_name', $required_fields ); ?></p>
          <p><label>Nom</label><input type="text" name="last_name" required value="<?php echo esc_attr( $value( 'last_name' ) ); ?>"<?php echo $this->acdc_get_invalid_field_class( 'last_name', $required_fields ); ?>><?php echo $this->acdc_get_invalid_field_note( 'last_name', $required_fields ); ?></p>
          <p><label>E-mail</label><input type="email" name="email" value="<?php echo esc_attr( $value( 'email' ) ); ?>"></p>
          <p><label>Téléphone</label><input type="text" name="phone" value="<?php echo esc_attr( $value( 'phone' ) ); ?>"></p>
        </div>
        <p><label>Notes</label><textarea name="notes" rows="4"><?php echo esc_textarea( $value( 'notes' ) ); ?></textarea></p>
        <p><button type="submit" class="acdc-button acdc-button-primary"><?php echo $contact ? 'Modifier' : 'Enregistrer'; ?></button></p>
      </form>
    </div>
    <?php
  }


  private function render_front_learners_tab( $action, $item_id ) {
    $search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
    $learners = $this->get_learners( $search );
    $learner  = $item_id ? $this->get_learner( $item_id ) : null;
    $companies = $this->get_companies();
    $sessions = $this->get_sessions();
    $prospects = $this->get_prospects();
    $genders  = $this->get_learner_gender_options();
    $yes_no  = $this->get_learner_yes_no_options();
    $socio   = $this->get_learner_socio_options();
    $levels  = $this->get_learner_education_options();
    $prefill_session_id = isset( $_GET['session_id'] ) ? absint( wp_unslash( $_GET['session_id'] ) ) : 0;
    $is_view = ( 'view' === $action );
    $is_edit = ( 'edit' === $action );
    $state = ! $is_view ? $this->acdc_consume_form_state( 'learner' ) : array();
    $state_input = ( ! empty( $state['input'] ) && is_array( $state['input'] ) ) ? $state['input'] : array();
    $required_fields = $this->acdc_get_form_required_fields( $state );
    $value = function( $key, $default = '' ) use ( $learner, $state_input ) {
      if ( array_key_exists( $key, $state_input ) && ! is_array( $state_input[ $key ] ) ) {
        return $state_input[ $key ];
      }
      return $learner && isset( $learner->$key ) ? $learner->$key : $default;
    };
    $page_title = $this->acdc_get_action_page_title( $action, 'Apprenants', 'Créer un apprenant', 'Modifier un apprenant', 'Voir un apprenant' );
    ?>
    <section class="acdc-section-head">
      <div>
        <h2><?php echo esc_html( $page_title ); ?></h2>
        <p>Gestion détaillée des apprenants et de leurs informations administratives.</p>
      </div>
      <div class="acdc-inline-wrap">
        <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_export_learners_csv' ), 'acdc_export_learners_csv' ) ); ?>">⬇ Exporter CSV</a>
        <button type="button" class="acdc-button acdc-button-accent acdc-button-faded" onclick="window.alert('Import apprenants : fonctionnalité à finaliser dans une prochaine étape.');">Importer apprenants</button>
        <?php if ( $is_view && $learner && ! empty( $learner->email ) ) : ?>
        <button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-open="acdc-direct-email-modal-learner-<?php echo (int) $learner->id; ?>">&#x2709; Envoyer un e-mail</button>
        <?php endif; ?>
        <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-learners&action=new' ) : $this->portal_page_url( array( 'tab' => 'learners', 'action' => 'new' ) ) ); ?>">Créer un apprenant</a>
      </div>
    </section>
    <?php if ( in_array( $action, array( 'new', 'edit', 'view' ), true ) ) : ?>
      <?php $this->acdc_render_form_validation_style( '.acdc-learner-form' ); ?>
      <div class="acdc-panel acdc-needs-section acdc-learner-form-panel">
        <div class="acdc-needs-section-title">Informations</div>
        <form class="acdc-form acdc-needs-form acdc-learner-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <?php wp_nonce_field( 'acdc_save_learner' ); ?>
          <input type="hidden" name="action" value="acdc_save_learner">
          <input type="hidden" name="learner_id" value="<?php echo $learner ? esc_attr( $learner->id ) : 0; ?>">
          <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-learners"><?php endif; ?>
          <?php if ( 'new' === $action ) : ?>
          <?php endif; ?>
          <div class="acdc-grid-2cols">
            <p><label>Genre *</label><select name="learner[gender]" required <?php disabled( $is_view ); ?>><?php foreach ( $genders as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) $value( 'gender' ), (string) $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
            <p><label>Commanditaire</label><select name="learner[prospect_id]" <?php disabled( $is_view ); ?>><option value="0">—</option><?php foreach ( $prospects as $prospect ) : $label = trim( $prospect->first_name . ' ' . $prospect->last_name ); if ( '' === $label ) { $label = $prospect->company_name ?: 'Prospect #' . $prospect->id; } ?><option value="<?php echo esc_attr( $prospect->id ); ?>" <?php selected( (int) $value( 'prospect_id' ), (int) $prospect->id ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
            <p><label>Prénom *</label><input type="text" name="learner[first_name]" required value="<?php echo esc_attr( $value( 'first_name' ) ); ?>" <?php echo $is_view ? 'readonly' : ''; ?><?php echo $this->acdc_get_invalid_field_class( 'first_name', $required_fields ); ?>><?php echo $this->acdc_get_invalid_field_note( 'first_name', $required_fields ); ?></p>
            <p><label>Nom d’usage *</label><input type="text" name="learner[usage_last_name]" required value="<?php echo esc_attr( $value( 'usage_last_name', $value( 'last_name' ) ) ); ?>" <?php echo $is_view ? 'readonly' : ''; ?><?php echo $this->acdc_get_invalid_field_class( 'usage_last_name', $required_fields ); ?>><?php echo $this->acdc_get_invalid_field_note( 'usage_last_name', $required_fields ); ?></p>
            <p><label>Nom de naissance</label><input type="text" name="learner[birth_last_name]" value="<?php echo esc_attr( $value( 'birth_last_name' ) ); ?>" <?php echo $is_view ? 'readonly' : ''; ?>></p>
            <p><label>E-mail</label><input type="email" name="learner[email]" value="<?php echo esc_attr( $value( 'email' ) ); ?>" <?php echo $is_view ? 'readonly' : ''; ?>></p>
            <p><label>Téléphone</label><input type="text" name="learner[phone]" value="<?php echo esc_attr( $value( 'phone' ) ); ?>" <?php echo $is_view ? 'readonly' : ''; ?>></p>
            <p><label>Date de naissance</label><input type="date" name="learner[birth_date]" value="<?php echo esc_attr( $value( 'birth_date' ) ); ?>" <?php echo $is_view ? 'readonly' : ''; ?>></p>
            <p><label>Lieu de naissance</label><input type="text" name="learner[birth_place]" value="<?php echo esc_attr( $value( 'birth_place' ) ); ?>" <?php echo $is_view ? 'readonly' : ''; ?>></p>
            <p><label>Adresse</label><input type="text" name="learner[address]" value="<?php echo esc_attr( $value( 'address' ) ); ?>" <?php echo $is_view ? 'readonly' : ''; ?>></p>
            <p><label>Complément d’adresse</label><input type="text" name="learner[address_extra]" value="<?php echo esc_attr( $value( 'address_extra' ) ); ?>" <?php echo $is_view ? 'readonly' : ''; ?>></p>
            <p><label>Code postal</label><input type="text" name="learner[postal_code]" value="<?php echo esc_attr( $value( 'postal_code' ) ); ?>" <?php echo $is_view ? 'readonly' : ''; ?>></p>
            <p><label>Ville</label><input type="text" name="learner[city]" value="<?php echo esc_attr( $value( 'city' ) ); ?>" <?php echo $is_view ? 'readonly' : ''; ?>></p>
            <p><label>Entreprise</label><select name="learner[company_id]" <?php disabled( $is_view ); ?>><option value="0">—</option><?php foreach ( $companies as $company ) : ?><option value="<?php echo esc_attr( $company->id ); ?>" <?php selected( (int) $value( 'company_id' ), (int) $company->id ); ?>><?php echo esc_html( $company->name ); ?></option><?php endforeach; ?></select></p>
            <p><label>Session</label><select name="learner[session_id]" <?php disabled( $is_view ); ?>><option value="0">—</option><?php $selected_session = array_key_exists( 'session_id', $state_input ) ? (int) $state_input['session_id'] : ( $learner ? (int) $learner->session_id : $prefill_session_id ); foreach ( $sessions as $session ) : ?><option value="<?php echo esc_attr( $session->id ); ?>" <?php selected( $selected_session, (int) $session->id ); ?>><?php echo esc_html( $session->title ); ?></option><?php endforeach; ?></select></p>
            <p><label>Inscrit à France Travail</label><select name="learner[is_france_travail]" <?php disabled( $is_view ); ?>><?php foreach ( $yes_no as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) $value( 'is_france_travail' ), (string) $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
            <p><label>Catégorie socioprofessionnelle</label><select name="learner[socio_category]" <?php disabled( $is_view ); ?>><?php foreach ( $socio as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) $value( 'socio_category' ), (string) $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
            <p><label>Niveau d’études</label><select name="learner[education_level]" <?php disabled( $is_view ); ?>><?php foreach ( $levels as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) $value( 'education_level' ), (string) $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
            <p><label>Poste / Fonction</label><input type="text" name="learner[job_title]" value="<?php echo esc_attr( $value( 'job_title' ) ); ?>" <?php echo $is_view ? 'readonly' : ''; ?>></p>
            <p><label>Alerte contact</label><input type="datetime-local" name="learner[contact_alert_at]" value="<?php echo esc_attr( $this->datetime_local_value( $value( 'contact_alert_at' ) ) ); ?>" <?php echo $is_view ? 'readonly' : ''; ?>></p>
            <p><label>Statut</label><select name="learner[status]" <?php disabled( $is_view ); ?>><?php foreach ( array( 'Pré-inscrit', 'Inscrit', 'Confirmé', 'Abandonné', 'Terminé' ) as $status ) : ?><option value="<?php echo esc_attr( $status ); ?>" <?php selected( (string) $value( 'status', 'Pré-inscrit' ), (string) $status ); ?>><?php echo esc_html( $status ); ?></option><?php endforeach; ?></select></p>
            <p class="acdc-grid-full acdc-learner-wide-field"><label>Besoins d’accessibilité</label><textarea name="learner[accessibility_needs]" rows="4" <?php echo $is_view ? 'readonly' : ''; ?>><?php echo esc_textarea( $value( 'accessibility_needs' ) ); ?></textarea></p>
            <p class="acdc-grid-full acdc-learner-wide-field"><label>Commentaire</label><textarea name="learner[comment_text]" rows="4" <?php echo $is_view ? 'readonly' : ''; ?>><?php echo esc_textarea( $value( 'comment_text', $value( 'notes' ) ) ); ?></textarea></p>
          </div>
          <?php if ( ! $is_view ) : ?>
            <p class="acdc-actions-end-wrap">
              <a class="acdc-button" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-learners' ) : $this->portal_page_url( array( 'tab' => 'learners' ) ) ); ?>" class="acdc-button acdc-button-soft">Annuler</a>
              <button type="submit" class="acdc-button acdc-button-primary"><?php echo $learner ? 'Modifier un apprenant' : 'Créer un apprenant'; ?></button>
            </p>
          <?php else : ?>
            <p class="acdc-actions-end-wrap">
              <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-learners&action=edit&item_id=' . (int) $learner->id ) : $this->portal_page_url( array( 'tab' => 'learners', 'action' => 'edit', 'item_id' => (int) $learner->id ) ) ); ?>">Modifier cet apprenant</a>
              <?php if ( $learner ) : ?>
              <?php if ( ! empty( $learner->email ) && empty( $learner->is_anonymized ) ) : ?>

              <?php endif; ?>
              <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_rgpd_export_learner&learner_id=' . (int) $learner->id ), 'acdc_rgpd_export_learner_' . (int) $learner->id ) ); ?>" title="Télécharger toutes les données personnelles de cet apprenant (RGPD art. 15)">⬇ Export RGPD</a>
              <?php $is_anon = ! empty( $learner->is_anonymized ); ?>
              <?php if ( ! $is_anon ) : ?>
              <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('Anonymiser définitivement cet apprenant ? Cette action est irréversible.');">
                <?php wp_nonce_field( 'acdc_rgpd_anonymize_learner_' . (int) $learner->id ); ?>
                <input type="hidden" name="action" value="acdc_rgpd_anonymize_learner">
                <input type="hidden" name="learner_id" value="<?php echo (int) $learner->id; ?>">
                <button type="submit" class="acdc-button acdc-button-danger" title="Anonymiser les données personnelles (RGPD art. 17 — irréversible)">Anonymiser (RGPD)</button>
              </form>
              <?php else : ?>
              <span class="acdc-pill is-muted" style="display:inline-flex;align-items:center;padding:0 12px;height:40px;border-radius:10px;">Anonymisé le <?php echo esc_html( $learner->anonymized_at ? mysql2date( 'd/m/Y', $learner->anonymized_at ) : '—' ); ?></span>
              <?php endif; ?>
              <?php endif; ?>
            </p>
            <?php if ( $is_view && $learner && ! empty( $learner->email ) && empty( $learner->is_anonymized ) ) :
              $to_name_l = trim( $learner->first_name . ' ' . ( $learner->usage_last_name ?: $learner->last_name ) );
              $this->render_direct_email_modal( $learner, 'learner', $learner->email, $to_name_l, 'learners' );
            endif; ?>
          <?php endif; ?>
        </form>
        <?php if ( $is_view && $learner ) :
          // ACDC 3.21.13 — Analyses du besoin liées à cet apprenant
          global $wpdb;
          $learner_analyses = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}acdc_of_need_analyses WHERE apprenant_id = %d AND is_model = 0 ORDER BY updated_at DESC",
            (int) $learner->id
          ) );
          if ( ! empty( $learner_analyses ) ) :
            $statuts = array( 'brouillon' => 'Brouillon', 'nouveau' => 'Nouveau', 'a_traiter' => 'À traiter', 'traite' => 'Traité', 'archive' => 'Archivé', 'expire' => 'Expiré' );
            $base_nad = is_admin() ? admin_url( 'admin.php?page=acdc-of-need-analyses' ) : $this->portal_page_url( array( 'tab' => 'need_analyses' ) );
        ?>
        <div class="acdc-panel acdc-mt-18" style="border:1px solid #f0e6dc;">
          <div class="acdc-panel-header" style="background:#fbf2e3;"><h3 style="margin:0;color:#8a6d2a;font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.05em;">Analyses du besoin (<?php echo count($learner_analyses); ?>)</h3></div>
          <div class="acdc-table-wrap">
            <table class="acdc-table">
              <thead><tr><th>Intitulé</th><th>Thématique</th><th>Statut</th><th>Date</th><th><span class="screen-reader-text">Actions</span></th></tr></thead>
              <tbody>
              <?php foreach ( $learner_analyses as $na ) :
                $them_map = array( 'ia' => 'IA', 'automatisation_nocode' => 'No-code', 'marketing_wordpress' => 'Marketing', 'management_leadership' => 'Management', 'management_restauration' => 'Mgmt Resto', 'hygiene_alimentaire' => 'Hygiène', 'softskills' => 'Soft skills' );
                $st = $statuts[ $na->statut ?? 'brouillon' ] ?? ucfirst( $na->statut ?? '' );
                $dot_map = array( 'brouillon' => 'is-draft', 'nouveau' => 'is-info', 'a_traiter' => 'is-warning', 'traite' => 'is-success', 'archive' => 'is-muted', 'expire' => 'is-error' );
                $dot = $dot_map[ $na->statut ?? 'brouillon' ] ?? 'is-draft';
                ?>
                <tr>
                  <td><?php echo esc_html( $na->title ); ?></td>
                  <td><?php echo ! empty( $na->thematique ) ? esc_html( $them_map[ $na->thematique ] ?? $na->thematique ) : '—'; ?></td>
                  <td><span class="acdc-doc-dot <?php echo esc_attr($dot); ?>"></span><?php echo esc_html($st); ?></td>
                  <td><?php echo esc_html( mysql2date( 'j/m/Y', $na->updated_at ) ); ?></td>
                  <td>
                    <a class="acdc-row-action-icon" href="<?php echo esc_url( add_query_arg( array( 'action' => 'view', 'item_id' => (int)$na->id ), $base_nad ) ); ?>" title="Voir" data-acdc-iconized="1"><?php echo $this->render_inline_icon('eye',25); ?></a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; endif; // end $is_view ?>
      </div>
    <?php endif; ?>
    <?php if ( ! $is_view && ! $is_edit ) : ?>
    <div class="acdc-panel">
      <form method="get" class="acdc-list-toolbar" style="display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;align-items:center;margin-bottom:18px;">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-learners"><?php else : ?><input type="hidden" name="tab" value="learners"><?php endif; ?>
        <div style="flex:1 1 420px;"><input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Rechercher" style="width:100%;"></div>
      </form>
      <div class="acdc-table-wrap acdc-learners-table-wrap">
        <table class="acdc-table acdc-learners-table" data-acdc-table-id="learners-list">
          <thead>
            <tr>
              <th>Genre</th>
              <th>Prénom</th>
              <th>Nom d’usage</th>
              <th>Commanditaire</th>
              <th>E-mail</th>
              <th>Téléphone</th>
              <th>Date de naissance</th>
              <th>Adresse</th>
              <th>Code postal</th>
              <th>Ville</th>
              <th>Commentaire</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if ( ! empty( $learners ) ) : ?>
            <?php foreach ( $learners as $entry ) : ?>
              <?php
                $base = is_admin() ? admin_url( 'admin.php?page=acdc-of-learners' ) : $this->portal_page_url( array( 'tab' => 'learners' ) );
                $need_analysis_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-need-analyses&action=new&learner_id=' . (int) $entry->id ) : $this->portal_page_url( array( 'tab' => 'need_analyses', 'action' => 'new', 'learner_id' => (int) $entry->id ) );
                $quote_url = is_admin() ? $this->admin_tab_url( 'quotes', array( 'scope' => 'action', 'quote_action' => 'create', 'learner_id' => (int) $entry->id ) ) : $this->portal_page_url( array( 'tab' => 'quotes', 'scope' => 'action', 'quote_action' => 'create', 'learner_id' => (int) $entry->id ) );
                $contract_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-registration-contract&action=new&learner_id=' . (int) $entry->id ) : $this->portal_page_url( array( 'tab' => 'registration_contract', 'action' => 'new', 'learner_id' => (int) $entry->id ) );
                $register_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-register-training&action=new&learner_id=' . (int) $entry->id ) : $this->portal_page_url( array( 'tab' => 'register_training', 'action' => 'new', 'learner_id' => (int) $entry->id ) );
              ?>
              <tr>
                <td><?php echo esc_html( $entry->gender ); ?></td>
                <td><?php echo esc_html( $entry->first_name ); ?></td>
                <td><?php echo esc_html( $entry->usage_last_name ?: $entry->last_name ); ?><?php if ( ! empty( $entry->absence_count ) && (int) $entry->absence_count > 0 ) : ?><span title="<?php echo esc_attr( (int) $entry->absence_count . ' absence(s) — ind. 12 Qualiopi' ); ?>" style="display:inline-flex;align-items:center;margin-left:6px;padding:2px 7px;background:#fff3cd;border:1px solid #f0c038;border-radius:999px;font-size:11px;font-weight:700;color:#856404;white-space:nowrap;vertical-align:middle;">&#9888; <?php echo (int) $entry->absence_count; ?> abs.</span><?php endif; ?></td>
                <td class="acdc-learner-prospect-cell"><?php
                  if ( $entry->company_id && ! empty( $entry->company_name ) ) {
                    echo '<span style="font-size:12px;color:#0f2c52;font-weight:500;">' . esc_html( $entry->company_name ) . '</span>';
                  } elseif ( $entry->prospect_id ) {
                    $p_label = trim( (string) $entry->prospect_first_name . ' ' . (string) $entry->prospect_last_name );
                    echo '<span style="font-size:12px;color:#4b5d76;" title="Lié à un prospect">' . esc_html( $p_label ?: '—' ) . '</span>';
                  } else {
                    echo '<span style="font-size:12px;color:#9ca3af;" title="Sans commanditaire">—</span>';
                  }
                ?></td>
                <td><?php echo esc_html( $entry->email ); ?></td>
                <td><?php echo esc_html( $entry->phone ); ?></td>
                <td><?php echo esc_html( $entry->birth_date ? mysql2date( 'd/m/Y', $entry->birth_date ) : '—' ); ?></td>
                <td><?php echo esc_html( $entry->address ); ?></td>
                <td><?php echo esc_html( $entry->postal_code ); ?></td>
                <td><?php echo esc_html( $entry->city ); ?></td>
                <td><?php echo esc_html( wp_trim_words( $entry->comment_text ?: $entry->notes, 8, '…' ) ); ?></td>
                <td class="acdc-actions-cell-icons acdc-learners-actions-cell">
                  <div class="acdc-learners-actions-inline">
                    <div class="acdc-row-menu" data-acdc-row-menu>
                    <button type="button" class="acdc-row-action-icon acdc-row-menu-toggle" data-acdc-row-menu-toggle aria-expanded="false" aria-label="Actions complémentaires">
                      <?php echo $this->render_inline_icon( 'more-horizontal', 25 ); ?>
                    </button>
                    <div class="acdc-row-menu-dropdown" data-acdc-row-menu-dropdown hidden>
                      <a href="<?php echo esc_url( $need_analysis_url ); ?>">Analyse du besoin</a>
                      <a href="<?php echo esc_url( $quote_url ); ?>">Devis</a>
                      <a href="<?php echo esc_url( $contract_url ); ?>">Convention / contrat</a>
                      <a href="<?php echo esc_url( $register_url ); ?>">Inscrire en formation</a>
                    </div>
                  </div>
                  <a class="acdc-row-action-icon acdc-row-view-link" href="<?php echo esc_url( add_query_arg( array( 'action' => 'view', 'item_id' => $entry->id ), $base ) ); ?>" title="Voir" aria-label="Voir l’apprenant">
                    <?php echo $this->render_inline_icon( 'eye', 25 ); ?>
                  </a>
                  <a class="acdc-row-action-icon acdc-row-edit-link" href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'item_id' => $entry->id ), $base ) ); ?>" title="Modifier" aria-label="Modifier l’apprenant">
                    <?php echo $this->render_inline_icon( 'edit', 25 ); ?>
                  </a>
                  <a class="acdc-row-action-icon acdc-row-delete-link" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_delete_learner&learner_id=' . $entry->id . ( is_admin() ? '&page=acdc-of-learners' : '' ) ), 'acdc_delete_learner_' . $entry->id ) ); ?>" title="Supprimer" aria-label="Supprimer l’apprenant" onclick="return confirm('Supprimer cet apprenant ?');">
                    <?php echo $this->render_inline_icon( 'trash', 25 ); ?>
                  </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else : ?>
            <tr><td colspan="12">Aucun apprenant enregistré.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
    <?php
  }

public function render_admin_learners_page() { $this->render_admin_portal_wrapper( 'learners' ); }

public function render_admin_contacts_page() { $this->render_admin_portal_wrapper( 'contacts' ); }

}
