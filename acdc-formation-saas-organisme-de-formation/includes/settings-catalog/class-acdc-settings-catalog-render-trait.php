<?php
/**
 * ACDC Réglages OF / Profil organisme / Catalogue — rendus et écrans
 *
 * Extraction incrémentale du module réglages OF, profil organisme
 * et catalogue.
 * Version : 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Settings_Catalog_Render_Trait {

  private function render_company_profile_row( $label, $value, $type = 'text' ) {
    echo '<div class="acdc-profile-row">';
    echo '<div class="acdc-profile-label">' . esc_html( $label ) . '</div>';
    echo '<div class="acdc-profile-value">';
    if ( 'yesno' === $type ) {
      if ( $value ) {
        echo '<span class="acdc-profile-bool acdc-profile-bool-yes"><span class="acdc-dot"></span> Oui</span>';
      } else {
        echo '<span class="acdc-profile-bool acdc-profile-bool-no"><span class="acdc-dot"></span> Non</span>';
      }
    } elseif ( 'url' === $type ) {
      if ( ! empty( $value ) ) {
        $display_url = (string) $value;
        echo '<div class="acdc-profile-link-wrap">';
        echo '<a href="' . esc_url( $value ) . '" target="_blank" rel="noopener noreferrer">Ouvrir le lien</a>';
        echo '<div class="acdc-profile-meta"><code>' . esc_html( $display_url ) . '</code></div>';
        echo '</div>';
      } else {
        echo '—';
      }
    } elseif ( 'website' === $type ) {
      if ( ! empty( $value ) ) {
        $display_url = preg_replace( '#^https?://#i', '', (string) $value );
        echo '<div class="acdc-profile-link-wrap">';
        echo '<a href="' . esc_url( $value ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $display_url ) . '</a>';
        echo '<div class="acdc-profile-meta"><code>' . esc_html( (string) $value ) . '</code></div>';
        echo '</div>';
      } else {
        echo '—';
      }
    } elseif ( 'modal_page' === $type ) {
      $url = is_array( $value ) && isset( $value['url'] ) ? (string) $value['url'] : '';
      $title = is_array( $value ) && isset( $value['title'] ) ? (string) $value['title'] : $label;
      $slug = is_array( $value ) && isset( $value['slug'] ) ? (string) $value['slug'] : '';
      $shortcode = is_array( $value ) && isset( $value['shortcode'] ) ? (string) $value['shortcode'] : '';
      if ( ! empty( $url ) ) {
        echo '<div class="acdc-profile-modal-link-wrap">';
        echo '<button type="button" class="acdc-button acdc-button-primary acdc-open-site-modal" data-url="' . esc_url( $url ) . '" data-title="' . esc_attr( $title ) . '">Ouvrir la fenêtre</button>';
        if ( ! empty( $slug ) ) {
          echo '<div class="acdc-profile-meta"><strong>Slug :</strong> ' . esc_html( $slug ) . '</div>';
        }
        if ( ! empty( $shortcode ) ) {
          echo '<div class="acdc-profile-meta"><strong>Shortcode :</strong> <code>' . esc_html( $shortcode ) . '</code></div>';
        }
        echo '</div>';
      } else {
        echo '—';
      }
    } elseif ( 'image' === $type ) {
      if ( ! empty( $value ) ) {
        echo '<div class="acdc-profile-media">';
        echo '<div class="acdc-profile-media-preview" style="display:inline-flex;align-items:center;justify-content:center;padding:6px;border:1px solid var(--acdc-border);border-radius:12px;background:#fff;width:120px;min-width:120px;height:74px;min-height:74px;overflow:hidden;vertical-align:top;"><img src="' . esc_url( $value ) . '" alt="" style="display:block;max-width:104px !important;max-height:58px !important;width:auto !important;height:auto !important;object-fit:contain;"></div>';
        echo '<a href="' . esc_url( $value ) . '" target="_blank" rel="noopener noreferrer">Télécharger</a>';
        echo '</div>';
      } else {
        echo '—';
      }
    } elseif ( 'color' === $type ) {
      $color_value = sanitize_hex_color( (string) $value );
      if ( ! $color_value ) {
        echo '—';
      } else {
        echo '<div class="acdc-color-display" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">';
        echo '<span class="acdc-color-swatch" aria-hidden="true" style="display:inline-block;width:84px;height:34px;min-width:84px;border:2px solid rgba(12,45,82,.35);border-radius:8px;background:' . esc_attr( $color_value ) . ';box-shadow:inset 0 0 0 1px rgba(255,255,255,.35);"></span>';
        echo '<code style="display:inline-block;background:#f5f7fb;border:1px solid var(--acdc-border);padding:6px 10px;border-radius:8px;font-size:13px;color:var(--acdc-text);">' . esc_html( strtoupper( $color_value ) ) . '</code>';
        echo '</div>';
      }
    } else {
      echo '' !== trim( (string) $value ) ? nl2br( esc_html( (string) $value ) ) : '—';
    }
    echo '</div>';
    echo '</div>';
  }


  private function render_company_profile_modal_shell() {
    ?>
    <div class="acdc-modal-shell" id="acdc-company-doc-modal" hidden>
      <div class="acdc-modal-backdrop" data-close="1"></div>
      <div class="acdc-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="acdc-company-doc-modal-title">
        <div class="acdc-modal-header">
          <h4 id="acdc-company-doc-modal-title">Document</h4>
          <button type="button" class="acdc-modal-close" aria-label="Fermer" data-close="1">×</button>
        </div>
        <div class="acdc-modal-body">
          <iframe src="about:blank" title="Document entreprise" loading="lazy"></iframe>
        </div>
      </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
      var modal = document.getElementById('acdc-company-doc-modal');
      if(!modal){ return; }
      var frame = modal.querySelector('iframe');
      var title = modal.querySelector('#acdc-company-doc-modal-title');
      document.querySelectorAll('.acdc-open-site-modal').forEach(function(btn){
        btn.addEventListener('click', function(){
          var url = btn.getAttribute('data-url') || '';
          var label = btn.getAttribute('data-title') || 'Document';
          if(!url){ return; }
          title.textContent = label;
          frame.setAttribute('src', url);
          modal.hidden = false;
          document.body.classList.add('acdc-modal-open');
        });
      });
      modal.addEventListener('click', function(e){
        if(e.target && e.target.getAttribute('data-close') === '1'){
          modal.hidden = true;
          frame.setAttribute('src', 'about:blank');
          document.body.classList.remove('acdc-modal-open');
        }
      });
      document.addEventListener('keydown', function(e){
        if(e.key === 'Escape' && !modal.hidden){
          modal.hidden = true;
          frame.setAttribute('src', 'about:blank');
          document.body.classList.remove('acdc-modal-open');
        }
      });
    });
    </script>
    <?php
  }


  private function render_company_profile_field( $key, $label, $value, $type = 'text' ) {
    echo '<p><label><strong>' . esc_html( $label ) . '</strong><br>';
    if ( 'textarea' === $type ) {
      echo '<textarea name="company_profile[' . esc_attr( $key ) . ']" rows="3">' . esc_textarea( $value ) . '</textarea>';
    } elseif ( 'checkbox' === $type ) {
      echo '<select name="company_profile[' . esc_attr( $key ) . ']">';
      echo '<option value="1"' . selected( $value, '1', false ) . '>Oui</option>';
      echo '<option value="0"' . selected( $value, '0', false ) . '>Non</option>';
      echo '</select>';
    } elseif ( 'color' === $type ) {
      $color_value = sanitize_hex_color( (string) $value );
      echo '<input type="color" class="acdc-color-input" value="' . esc_attr( $color_value ? $color_value : '#000000' ) . '" oninput="this.nextElementSibling.value=this.value">';
      echo '<input type="text" name="company_profile[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '">';
    } elseif ( 'upload_image_url' === $type || 'upload_pdf_url' === $type ) {
      echo '<input type="url" name="company_profile[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" placeholder="https://...">';
      if ( ! empty( $value ) ) {
        if ( 'upload_image_url' === $type ) {
          echo '<span class="acdc-inline-upload-preview" style="display:inline-flex;align-items:center;justify-content:center;width:120px;min-width:120px;height:74px;min-height:74px;overflow:hidden;border:1px solid var(--acdc-border);border-radius:10px;background:#fff;padding:4px;vertical-align:top;"><img src="' . esc_url( $value ) . '" alt="" style="display:block;max-width:104px !important;max-height:58px !important;width:auto !important;height:auto !important;object-fit:contain;"></span>';
        } else {
          echo '<span class="acdc-inline-upload-file">Fichier actuel enregistré.</span>';
        }
      }
      echo '<input type="file" name="company_profile_uploads[' . esc_attr( $key ) . ']" accept="' . ( 'upload_pdf_url' === $type ? 'application/pdf' : 'image/*' ) . '">';
    } else {
      echo '<input type="text" name="company_profile[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '">';
    }
    echo '</label></p>';
  }

  private function render_company_profile_doc_action_row( $label, $settings_url, $button_label, $pdf_url = '' ) {
    echo '<div class="acdc-profile-cta-row">';
    echo '<span>' . esc_html( $label ) . '</span>';
    echo '<div class="acdc-profile-cta-actions">';
    if ( ! empty( $pdf_url ) ) {
      echo '<a class="acdc-button acdc-button-soft" href="' . esc_url( $pdf_url ) . '" target="_blank" rel="noopener noreferrer">Voir le PDF</a>';
    }
    echo '<a class="acdc-button acdc-button-primary" href="' . esc_url( $settings_url ) . '">' . esc_html( $button_label ) . '</a>';
    echo '</div>';
    echo '</div>';
  }


  private function render_front_company_profile_tab( $action = 'view' ) {
    $profile = $this->get_company_profile_options();
    $is_edit = 'edit' === $action;
    if ( isset( $_GET['updated'] ) ) {
      echo '<div class="acdc-alert acdc-alert-success"><p>Profil de l’entreprise enregistré.</p></div>';
    }
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Profil de l’entreprise</h2>
        <p>Informations structurelles, personnalisation documentaire et paramètres de diffusion.</p>
      </div>
      <?php if ( ! $is_edit ) : ?>
        <a class="acdc-icon-link" href="<?php echo esc_url( is_admin() ? $this->admin_tab_url( 'company_profile', array( 'action' => 'edit' ) ) : $this->portal_page_url( array( 'tab' => 'company_profile', 'action' => 'edit' ) ) ); ?>" title="Modifier le profil de l’entreprise" aria-label="Modifier le profil de l’entreprise">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25Z" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M14.06 4.94 17.81 8.69" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </a>
      <?php endif; ?>
    </section>
    <?php if ( $is_edit ) : ?>
      <form class="acdc-form acdc-company-profile-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'acdc_save_company_profile' ); ?>
        <input type="hidden" name="action" value="acdc_save_company_profile">
        <input type="hidden" name="return_tab" value="company_profile">
        <input type="hidden" name="return_context" value="<?php echo is_admin() ? 'admin' : 'front'; ?>">
        <div class="acdc-panel">
          <h3>Informations sur l’entreprise</h3>
          <div class="acdc-grid-2cols">
            <?php
            foreach ( array(
              'offer' => 'Offre', 'siret_identification' => 'SIRET / N° d’identification', 'enterprise' => 'Entreprise', 'client_creation_date' => 'Date création client', 'signatory_role' => 'Qualité du signataire', 'first_name' => 'Prénom du représentant', 'last_name' => 'Nom du représentant', 'vat_number' => 'N° TVA intracommunautaire', 'activity_declaration_number' => 'Numéro de déclaration d’activité', 'inrs_number' => 'Numéro INRS', 'draaf_number' => 'Numéro DRAAF', 'apr_number' => 'Numéro APR', 'uai_number' => 'Numéro UAI', 'naf_code' => 'Code NAF', 'legal_form' => 'Forme juridique', 'accounting_start' => 'Début de l’exercice comptable', 'accounting_end' => 'Fin de l’exercice comptable', 'employees_count' => 'Nombre de salariés', 'address' => 'Adresse', 'postal_code' => 'Code postal', 'city' => 'Ville', 'country' => 'Pays', 'enterprise_contact_email' => 'E-mail contact entreprise', 'enterprise_contact_phone' => 'Tél. contact entreprise', 'referent' => 'Référent', 'acceptance_date' => 'Date d’acceptation CGV/CGU', 'access_start_date' => 'Date de début d’accès', 'sepa_creation_date' => 'Date de création SEPA', 'sepa_validation_date' => 'Date de validation SEPA', 'sepa_setup_deadline' => 'Date d’échéance de mise en place SEPA', 'sepa_invalidation_date' => 'Date d’invalidation SEPA', 'trial_end_date' => 'Date de fin de période d’essai', 'rdv1_date' => 'Date RDV1', 'next_billing_date' => 'Date prochaine facturation', 'last_billing_date' => 'Date dernière facturation', 'termination_date' => 'Date de résiliation', 'access_end_date' => 'Date de fin d’accès' ) as $key => $label ) {
              $this->render_company_profile_field( $key, $label, isset( $profile[ $key ] ) ? $profile[ $key ] : '' );
            }
            $this->render_company_profile_field( 'access_enabled', 'Accès', isset( $profile['access_enabled'] ) && in_array( strtolower( (string) $profile['access_enabled'] ), array( '1', 'oui', 'yes', 'true' ), true ) ? '1' : '0', 'checkbox' );
            ?>
          </div>
        </div>
        <div class="acdc-panel">
          <h3>Informations sur l’entreprise</h3>
          <div class="acdc-grid-2cols">
            <?php
            foreach ( array(
              'logo_url' => array( 'label' => 'Logo', 'type' => 'upload_image_url' ),
              'stamp_url' => array( 'label' => 'Cachet de l’entreprise', 'type' => 'upload_image_url' ),
              'signature_url' => array( 'label' => 'Signature seule', 'type' => 'upload_image_url' ),
              'stamp_only_url' => array( 'label' => 'Cachet seul', 'type' => 'upload_image_url' ),
              'website_url' => array( 'label' => 'Site Web', 'type' => 'text' ),
              'email_subject' => array( 'label' => 'Objet e-mail', 'type' => 'text' ),
              'timezone_label' => array( 'label' => 'Fuseau horaire', 'type' => 'text' ),
              'vat_rate' => array( 'label' => 'Taux de TVA (%)', 'type' => 'text' ),
              'training_rules_url' => array( 'label' => 'Règlement intérieur de la formation (URL ou PDF)', 'type' => 'upload_pdf_url' ),
              'welcome_booklet_url' => array( 'label' => 'Livret d’accueil (URL ou PDF)', 'type' => 'upload_pdf_url' ),
              'cgv_url' => array( 'label' => 'Conditions générales de vente (URL)', 'type' => 'text' ),
              'cgu_url' => array( 'label' => 'Conditions générales d’utilisation (URL)', 'type' => 'text' ),
              'mentions_legales_url' => array( 'label' => 'Mentions légales (URL)', 'type' => 'text' ),
              'privacy_policy_url' => array( 'label' => 'Politique de confidentialité (URL)', 'type' => 'text' ),
              'other_document_url' => array( 'label' => 'Autre document (URL)', 'type' => 'text' ),
              'sponsorship_code' => array( 'label' => 'Code de parrainage', 'type' => 'text' ),
              'header_footer_bg' => array( 'label' => 'Arrière-plan entêtes/pieds de page', 'type' => 'color' ),
              'header_footer_text' => array( 'label' => 'Texte entêtes/pieds de page', 'type' => 'color' ),
              'body_text_color' => array( 'label' => 'Texte corps', 'type' => 'color' ),
              'footer_logo_url' => array( 'label' => 'Logo(s) du pied de page', 'type' => 'upload_image_url' ) ) as $key => $field ) {
              $this->render_company_profile_field( $key, $field['label'], isset( $profile[ $key ] ) ? $profile[ $key ] : '', $field['type'] );
            }
            ?>
          </div>
        </div>
        <div class="acdc-panel">
          <h3>Paramètres complémentaires</h3>
          <div class="acdc-grid-2cols">
            <?php
            foreach ( array(
              'contract_params_label' => 'Paramètres de la convention / du contrat', 'convocation_params_label' => 'Paramètres des convocations de début de formation', 'attestation_params_label' => 'Paramètres des attestations de fin de formation', 'subcontract_params_label' => 'Paramètres du contrat de sous-traitance formateur', 'learner_extranet_start' => 'Date de début d’accès extranet apprenants', 'learner_extranet_end' => 'Fin d’accès suite à la formation', 'attendance_cutoff_hour' => 'Heure de coupure matin / après-midi' ) as $key => $label ) {
              $this->render_company_profile_field( $key, $label, isset( $profile[ $key ] ) ? $profile[ $key ] : '' );
            }
            foreach ( array( 'cold_survey_delay' => 'Enquête à froid — délai (jours)', 'company_survey_delay' => 'Enquête entreprise — délai (jours)', 'trainer_survey_delay' => 'Enquête formateur — délai (jours)' ) as $dk => $dl ) {
              $dval = max( 1, absint( isset( $profile[$dk] ) ? $profile[$dk] : 1 ) );
              echo '<p><label><strong>' . esc_html( $dl ) . '</strong><br>';
              echo '<input type="number" min="1" step="1" name="company_profile[' . esc_attr( $dk ) . ']" value="' . esc_attr( $dval ) . '" style="width:100px;"> ';
              echo '<span style="color:#6b7e96;font-size:13px;">jour(s) après la fin de la formation</span>';
              echo '</label></p>';
            }
            $this->render_company_profile_field( 'auto_attendance_send', 'Envoi émargements automatique', isset( $profile['auto_attendance_send'] ) && in_array( strtolower( (string) $profile['auto_attendance_send'] ), array( '1', 'oui', 'yes', 'true' ), true ) ? '1' : '0', 'checkbox' );
            $this->render_company_profile_field( 'show_france_travail_id', 'Affichage Identifiant France Travail', isset( $profile['show_france_travail_id'] ) && in_array( strtolower( (string) $profile['show_france_travail_id'] ), array( '1', 'oui', 'yes', 'true' ), true ) ? '1' : '0', 'checkbox' );
            ?>
          </div>
        </div>
        <div class="acdc-panel">
          <h3>Coordonnées bancaires</h3>
          <p style="font-size:13px;color:#6b7e96;margin-bottom:12px;">Utilisées pour préremplir les devis (IBAN/BIC ACDC, pour recevoir les paiements).</p>
          <div class="acdc-grid-2cols">
            <?php $this->render_company_profile_field( 'bank_name', 'Nom de la banque', isset( $profile['bank_name'] ) ? $profile['bank_name'] : '' ); ?>
            <?php $this->render_company_profile_field( 'bank_domiciliation', 'Domiciliation', isset( $profile['bank_domiciliation'] ) ? $profile['bank_domiciliation'] : '' ); ?>
            <?php $this->render_company_profile_field( 'bank_iban', 'IBAN', isset( $profile['bank_iban'] ) ? $profile['bank_iban'] : '' ); ?>
            <?php $this->render_company_profile_field( 'bank_bic', 'BIC', isset( $profile['bank_bic'] ) ? $profile['bank_bic'] : '' ); ?>
            <div class="acdc-span-2">
              <p><label style="font-weight:600;">Régime de TVA</label>
              <select name="company_profile[vat_rate_default]">
                <option value="0" <?php selected( isset( $profile['vat_rate_default'] ) ? (string) $profile['vat_rate_default'] : '0', '0' ); ?>>0 % — Exonéré (article 293 B du CGI — net de TVA)</option>
                <option value="20" <?php selected( isset( $profile['vat_rate_default'] ) ? (string) $profile['vat_rate_default'] : '0', '20' ); ?>>20 % — Assujetti (affichage HT et TTC)</option>
              </select></p>
            </div>
          </div>
          <div class="acdc-form-actions">
            <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( is_admin() ? $this->admin_tab_url( 'company_profile' ) : $this->portal_page_url( array( 'tab' => 'company_profile' ) ) ); ?>">Annuler</a>
            <button class="acdc-button acdc-button-primary" type="submit">Enregistrer le profil</button>
          </div>
        </div>
      </form>
    <?php else : ?>
      <div class="acdc-panel acdc-profile-section">
        <h3>Informations sur l’entreprise</h3>
        <div class="acdc-profile-table">
          <?php
          foreach ( array(
            'Offre' => $profile['offer'],
            'SIRET / N° d’identification' => $profile['siret_identification'],
            'Entreprise' => $profile['enterprise'],
            'Date création client' => $profile['client_creation_date'],
            'Qualité du signataire' => $profile['signatory_role'],
            'Prénom du représentant' => isset( $profile['first_name'] ) ? $profile['first_name'] : '',
            'Nom du représentant' => isset( $profile['last_name'] ) ? $profile['last_name'] : '',
            'N° TVA intracommunautaire' => $profile['vat_number'],
            'Numéro de déclaration d’activité' => $profile['activity_declaration_number'],
            'Numéro INRS' => $profile['inrs_number'],
            'Numéro DRAAF' => $profile['draaf_number'],
            'Numéro APR' => $profile['apr_number'],
            'Numéro UAI' => $profile['uai_number'],
            'Code NAF' => $profile['naf_code'],
            'Forme juridique' => $profile['legal_form'],
            'Début de l’exercice comptable' => $profile['accounting_start'],
            'Fin de l’exercice comptable' => $profile['accounting_end'],
            'Nombre de salariés' => $profile['employees_count'],
            'Adresse' => $profile['address'],
            'Code postal' => $profile['postal_code'],
            'Ville' => $profile['city'],
            'Pays' => $profile['country'],
            'E-mail contact entreprise' => $profile['enterprise_contact_email'],
            'Tél. contact entreprise' => $profile['enterprise_contact_phone'],
            'Référent' => $profile['referent'],
            'Date d’acceptation CGV/CGU' => $profile['acceptance_date'],
            'Date de début d’accès' => $profile['access_start_date'],
            'Date de création SEPA' => $profile['sepa_creation_date'],
            'Date de validation SEPA' => $profile['sepa_validation_date'],
            'Date d’échéance de mise en place SEPA' => $profile['sepa_setup_deadline'],
            'Date d’invalidation SEPA' => $profile['sepa_invalidation_date'],
            'Date de fin de période d’essai' => $profile['trial_end_date'],
            'Date RDV1' => $profile['rdv1_date'],
            'Date prochaine facturation' => $profile['next_billing_date'],
            'Date dernière facturation' => $profile['last_billing_date'],
            'Date de résiliation' => $profile['termination_date'],
            'Date de fin d’accès' => $profile['access_end_date'],
          ) as $label => $value ) {
            $this->render_company_profile_row( $label, $value );
          }
          $this->render_company_profile_row( 'Accès', isset( $profile['access_enabled'] ) && ! in_array( strtolower( (string) $profile['access_enabled'] ), array( '0', 'non', 'no', 'false' ), true ), 'yesno' );
          ?>
        </div>
      </div>
      <div class="acdc-panel acdc-profile-section">
        <h3>Informations sur l’entreprise</h3>
        <div class="acdc-profile-table">
          <?php
          $this->render_company_profile_row( 'Logo', $profile['logo_url'], 'image' );
          $this->render_company_profile_row( 'Cachet de l’entreprise', $profile['stamp_url'], 'image' );
          $this->render_company_profile_row( 'Signature seule', isset( $profile['signature_url'] ) ? $profile['signature_url'] : '', 'image' );
          $this->render_company_profile_row( 'Cachet seul', isset( $profile['stamp_only_url'] ) ? $profile['stamp_only_url'] : '', 'image' );
          $this->render_company_profile_row( 'Site Web', $profile['website_url'], 'website' );
          $this->render_company_profile_row( 'Objet e-mail', $profile['email_subject'] );
          $this->render_company_profile_row( 'Fuseau horaire', $profile['timezone_label'] );
          $this->render_company_profile_row( 'Taux de TVA (%)', $profile['vat_rate'] );
          $this->render_company_profile_row( 'Règlement intérieur de la formation', array(
            'url' => $profile['training_rules_url'],
            'title' => 'Règlement intérieur',
            'slug' => 'reglement-interieur',
            'shortcode' => '[acdc_of_company_static_doc doc="reglement-interieur"]',
          ), 'modal_page' );
          $this->render_company_profile_row( 'Livret d’accueil', $profile['welcome_booklet_url'], 'url' );
          $this->render_company_profile_row( 'Conditions générales de vente', array(
            'url' => $profile['cgv_url'],
            'title' => 'Conditions Générales de Vente (CGV)',
            'slug' => 'conditions-generales-de-vente-cgv',
            'shortcode' => '[acdc_of_company_static_doc doc="conditions-generales-de-vente-cgv"]',
          ), 'modal_page' );
          $this->render_company_profile_row( 'Conditions générales d’utilisation', array(
            'url' => $profile['cgu_url'],
            'title' => 'Conditions Générales d’Utilisation (CGU)',
            'slug' => 'conditions-generales-dutilisation-cgu',
            'shortcode' => '[acdc_of_company_static_doc doc="conditions-generales-dutilisation-cgu"]',
          ), 'modal_page' );
          $this->render_company_profile_row( 'Mentions légales', array(
            'url' => isset( $profile['mentions_legales_url'] ) ? $profile['mentions_legales_url'] : home_url( '/mentions-legales/' ),
            'title' => 'Mentions légales',
            'slug' => 'mentions-legales',
            'shortcode' => '[acdc_of_company_static_doc doc="mentions-legales"]',
          ), 'modal_page' );
          $this->render_company_profile_row( 'Politique de confidentialité', array(
            'url' => isset( $profile['privacy_policy_url'] ) ? $profile['privacy_policy_url'] : home_url( '/politique-de-confidentialite/' ),
            'title' => 'Politique de confidentialité',
            'slug' => 'politique-de-confidentialite',
            'shortcode' => '[acdc_of_company_static_doc doc="politique-de-confidentialite"]',
          ), 'modal_page' );
          $this->render_company_profile_row( 'Autre document', $profile['other_document_url'], 'url' );
          $this->render_company_profile_row( 'Code de parrainage', $profile['sponsorship_code'] );
          ?>
        </div>
      </div>
      <div class="acdc-panel acdc-profile-section">
        <h3>Personnalisation : Documents / Plateforme Quiz / Enquêtes / Éval. / Extranet</h3>
        <div class="acdc-profile-table">
          <?php
          $this->render_company_profile_row( 'Arrière-plan : en-têtes / pieds de page', $profile['header_footer_bg'], 'color' );
          $this->render_company_profile_row( 'Texte : en-têtes / pieds de page', $profile['header_footer_text'], 'color' );
          $this->render_company_profile_row( 'Texte : corps', $profile['body_text_color'], 'color' );
          $this->render_company_profile_row( 'Logo(s) du pied de page', $profile['footer_logo_url'], 'image' );
          ?>
        </div>
        <div class="acdc-profile-preview-row">
          <span class="acdc-preview-label">Aperçus :</span>
          <span class="acdc-preview-chip">Document personnalisé</span>
          <span class="acdc-preview-chip">Plateforme Quiz / Enquêtes / Éval.</span>
          <span class="acdc-preview-chip">Extranet</span>
          <span class="acdc-preview-chip">Catalogue</span>
        </div>
      </div>
      <div class="acdc-panel acdc-profile-section">
        <h3>Coordonnées bancaires</h3>
        <div class="acdc-profile-table">
          <?php
          $this->render_company_profile_row( 'Nom de la banque', isset( $profile['bank_name'] ) ? $profile['bank_name'] : '' );
          $this->render_company_profile_row( 'Domiciliation', isset( $profile['bank_domiciliation'] ) ? $profile['bank_domiciliation'] : '' );
          $this->render_company_profile_row( 'IBAN', isset( $profile['bank_iban'] ) ? $profile['bank_iban'] : '' );
          $this->render_company_profile_row( 'BIC', isset( $profile['bank_bic'] ) ? $profile['bank_bic'] : '' );
          $vat_label = ( isset( $profile['vat_rate_default'] ) && '20' === (string) $profile['vat_rate_default'] ) ? '20 % — Assujetti' : '0 % — Exonéré (net de TVA)';
          $this->render_company_profile_row( 'Régime de TVA', $vat_label );
          ?>
        </div>
      </div>
      <div class="acdc-panel acdc-profile-section">
        <h3>Paramètres de la convention / du contrat</h3>
        <?php $contract_params_url = is_admin() ? $this->admin_tab_url( 'contract_parameters' ) : $this->portal_page_url( array( 'tab' => 'contract_parameters' ) ); ?>
        <?php $this->render_company_profile_doc_action_row( 'Paramètres de la convention / du contrat', $contract_params_url, $profile['contract_params_label'], $this->get_company_model_pdf_asset_url( 'modele-convention-formation' ) ); ?>
      </div>
      <div class="acdc-panel acdc-profile-section">
        <h3>Paramètres des convocations de début de formation</h3>
        <?php $convocation_params_url = is_admin() ? $this->admin_tab_url( 'convocation_parameters' ) : $this->portal_page_url( array( 'tab' => 'convocation_parameters' ) ); ?>
        <?php $this->render_company_profile_doc_action_row( 'Paramètres des convocations de début de formation', $convocation_params_url, $profile['convocation_params_label'], $this->get_company_model_pdf_asset_url( 'modele-convocation' ) ); ?>
      </div>
      <div class="acdc-panel acdc-profile-section">
        <h3>Paramètres des attestations de fin de formation</h3>
        <?php $attestation_params_url = is_admin() ? $this->admin_tab_url( 'attestation_parameters' ) : $this->portal_page_url( array( 'tab' => 'attestation_parameters' ) ); ?>
        <?php $this->render_company_profile_doc_action_row( 'Paramètres des attestations de fin de formation', $attestation_params_url, $profile['attestation_params_label'], $this->get_company_model_pdf_asset_url( 'modele-attestation-fin-formation' ) ); ?>
      </div>
      <div class="acdc-panel acdc-profile-section">
        <h3>Paramètres du contrat de sous-traitance formateur</h3>
        <?php $subcontract_params_url = is_admin() ? $this->admin_tab_url( 'subcontract_parameters' ) : $this->portal_page_url( array( 'tab' => 'subcontract_parameters' ) ); ?>
        <?php $this->render_company_profile_doc_action_row( 'Paramètres du contrat de sous-traitance formateur', $subcontract_params_url, $profile['subcontract_params_label'], $this->get_company_model_pdf_asset_url( 'modele-contrat-sous-traitance-formateur' ) ); ?>
      </div>
      <div class="acdc-panel acdc-profile-section">
        <h3>Paramètres des enquêtes</h3>
        <div class="acdc-profile-table">
          <?php
          foreach ( array( 'Enquête à froid' => 'cold_survey_delay', 'Enquête entreprise' => 'company_survey_delay', 'Enquête formateur' => 'trainer_survey_delay' ) as $dlabel => $dkey ) {
            $ddays = max( 1, absint( isset( $profile[$dkey] ) ? $profile[$dkey] : 1 ) );
            $this->render_company_profile_row( $dlabel, $ddays . ( 1 === $ddays ? ' jour après la fin de la formation' : ' jours après la fin de la formation' ) );
          }
          ?>
        </div>
      </div>
      <div class="acdc-panel acdc-profile-section">
        <h3>Paramètres accès extranet apprenants</h3>
        <div class="acdc-profile-table">
          <?php
          $this->render_company_profile_row( 'Date de début d’accès', $profile['learner_extranet_start'] );
          $this->render_company_profile_row( 'Fin d’accès suite à la formation', $profile['learner_extranet_end'] );
          ?>
        </div>
      </div>
      <div class="acdc-panel acdc-profile-section">
        <h3>Paramètres des émargements</h3>
        <div class="acdc-profile-table">
          <?php
          $this->render_company_profile_row( 'Envoi émargements automatique', isset( $profile['auto_attendance_send'] ) && ! in_array( strtolower( (string) $profile['auto_attendance_send'] ), array( '0', 'non', 'no', 'false' ), true ), 'yesno' );
          $this->render_company_profile_row( 'Affichage Identifiant France Travail', isset( $profile['show_france_travail_id'] ) && ! in_array( strtolower( (string) $profile['show_france_travail_id'] ), array( '0', 'non', 'no', 'false' ), true ), 'yesno' );
          $this->render_company_profile_row( 'Heure de coupure matin / après-midi', $profile['attendance_cutoff_hour'] );
          ?>
        </div>
      </div>
      <?php $this->render_company_profile_modal_shell(); ?>
    <?php endif; ?>
    <?php
  }



  private function render_front_billing_settings_tab() {
    $settings = $this->get_billing_settings_options();
    $is_admin_context = is_admin();
    $action_url = admin_url( 'admin-post.php' );
    if ( isset( $_GET['updated'] ) ) {
      echo '<div class="acdc-alert acdc-alert-success">Paramètres de facturation enregistrés.</div>';
    }
    ?>
    <section class="acdc-section-head"><div><h2>Modifier les paramètres de facturation : <?php echo esc_html( $settings['billing']['company_name'] ); ?></h2></div></section>
    <form class="acdc-company-profile-form acdc-contract-builder-form" method="post" action="<?php echo esc_url( $action_url ); ?>">
      <?php wp_nonce_field( 'acdc_save_billing_settings' ); ?>
      <input type="hidden" name="action" value="acdc_save_billing_settings">
      <input type="hidden" name="return_context" value="<?php echo $is_admin_context ? 'admin' : 'front'; ?>">
      <!-- ACDC 3.24.20 — Toggle activation facturation réelle -->
      <?php $is_real = ! $this->is_documents_billing_demo_enabled(); ?>
      <div class="acdc-panel acdc-profile-section" style="border-left:4px solid <?php echo $is_real ? '#27ae60' : '#d6a353'; ?>;">
        <h3>Activation de la facturation réelle</h3>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Activer la facturation réelle</div>
          <div>
            <label class="acdc-switch">
              <input type="checkbox" name="billing_real_enabled" value="1" <?php checked( $is_real ); ?>>
              <span class="acdc-switch-slider"></span>
            </label>
            <p class="acdc-help">
              <?php if ( $is_real ) : ?>
              ✅ Facturation réelle <strong>activée</strong>. Les données fictives sont masquées. Vous pouvez créer de vraies factures depuis vos devis.
              <?php else : ?>
              ⚠️ Module en <strong>mode démonstration</strong>. Activez pour créer de vraies factures et masquer les données fictives.
              <?php endif; ?>
            </p>
          </div>
        </div>
      </div>
      <div class="acdc-panel acdc-profile-section">
        <h3>Paramètres de facturation</h3>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Adresse d'émission <span class="acdc-required">*</span></div>
          <div><textarea name="billing_settings[billing][issue_address]" rows="4"><?php echo esc_textarea( $settings['billing']['issue_address'] ); ?></textarea><p class="acdc-help">Indiquez ici l'adresse d'émission de vos devis, factures et avoirs. Vous pouvez également ajouter d'autres détails comme une adresse e-mail par exemple.</p></div>
          <div class="acdc-contract-label">Mention TVA 0% (facultatif)</div>
          <div><textarea name="billing_settings[billing][vat_0_mention]" rows="4"><?php echo esc_textarea( $settings['billing']['vat_0_mention'] ); ?></textarea><p class="acdc-help">Cette mention apparaîtra sur les devis et les factures uniquement si votre TVA est à 0%.</p></div>
          <div class="acdc-contract-label">Cachet de signature</div>
          <div><label class="acdc-switch"><input type="checkbox" name="billing_settings[billing][signature_stamp]" value="1" <?php checked( ! empty( $settings['billing']['signature_stamp'] ) ); ?>><span class="acdc-switch-slider"></span></label><p class="acdc-help">En activant cette option, votre cachet de signature sera apposé aux devis et factures générés.</p></div>
        </div>
      </div>
      <div class="acdc-panel acdc-profile-section">
        <h3>Devis</h3>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Préfixe des numéros des devis <span class="acdc-required">*</span></div>
          <div><input type="text" name="billing_settings[quotes][prefix]" value="<?php echo esc_attr( $settings['quotes']['prefix'] ); ?>"><div class="acdc-inline-wrap" style="margin-top:10px;gap:8px;"><span class="acdc-chip"><?php echo esc_html( $settings['quotes']['year_tag'] ); ?></span><span class="acdc-chip"><?php echo esc_html( $settings['quotes']['month_tag'] ); ?></span><span class="acdc-chip"><?php echo esc_html( $settings['quotes']['day_tag'] ); ?></span></div></div>
          <div class="acdc-contract-label">Numéro du prochain devis <span class="acdc-required">*</span></div>
          <div><input type="text" name="billing_settings[quotes][next_number]" value="<?php echo esc_attr( $settings['quotes']['next_number'] ); ?>"><p class="acdc-help">Votre prochain devis sera numéroté : DE-2026-<?php echo esc_html( $settings['quotes']['next_number'] ); ?></p></div>
          <div class="acdc-contract-label">Validité du devis (jours)</div>
          <div><input type="number" name="billing_settings[quotes][validity_days]" value="<?php echo esc_attr( isset( $settings['quotes']['validity_days'] ) ? (int) $settings['quotes']['validity_days'] : 30 ); ?>" min="1" style="width:100px;"><p class="acdc-help">Date d'expiration = date d'émission + ce nombre de jours.</p></div>
          <div class="acdc-contract-label">Recommencer la numérotation à chaque nouvelle année</div>
          <div><label class="acdc-switch"><input type="checkbox" name="billing_settings[quotes][restart_each_year]" value="1" <?php checked( ! empty( $settings['quotes']['restart_each_year'] ) ); ?>><span class="acdc-switch-slider"></span></label></div>
          <div class="acdc-contract-label">Conditions de règlement des devis</div>
          <div><textarea name="billing_settings[quotes][payment_terms]" rows="4"><?php echo esc_textarea( $settings['quotes']['payment_terms'] ); ?></textarea></div>
          <div class="acdc-contract-label">Mention spéciale sur les devis</div>
          <div><textarea name="billing_settings[quotes][special_mention]" rows="5"><?php echo esc_textarea( $settings['quotes']['special_mention'] ); ?></textarea><p class="acdc-help">Indiquez ici toute autre information que vous souhaitez faire apparaître au bas de vos devis</p></div>
          <div class="acdc-contract-label">Pièce jointe par défaut</div>
          <div><div class="acdc-upload-dropzone acdc-upload-dropzone-static"><button type="button" class="acdc-button acdc-button-primary">Choisir le fichier</button><span>Déposez le fichier ou cliquez pour choisir</span></div><p class="acdc-help">Ce document sera ajouté en pièce jointe lors de l'envoi par e-mail du devis.</p></div>
        </div>
      </div>
      <div class="acdc-panel acdc-profile-section">
        <h3>Factures</h3>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Préfixe des numéros des factures <span class="acdc-required">*</span></div>
          <div><input type="text" name="billing_settings[invoices][prefix]" value="<?php echo esc_attr( $settings['invoices']['prefix'] ); ?>"><div class="acdc-inline-wrap" style="margin-top:10px;gap:8px;"><span class="acdc-chip"><?php echo esc_html( $settings['invoices']['year_tag'] ); ?></span><span class="acdc-chip"><?php echo esc_html( $settings['invoices']['month_tag'] ); ?></span><span class="acdc-chip"><?php echo esc_html( $settings['invoices']['day_tag'] ); ?></span></div></div>
          <div class="acdc-contract-label">Numéro de la prochaine facture <span class="acdc-required">*</span></div>
          <div><input type="text" name="billing_settings[invoices][next_number]" value="<?php echo esc_attr( $settings['invoices']['next_number'] ); ?>"><p class="acdc-help">Votre prochaine facture sera numérotée : FA-2026-<?php echo esc_html( $settings['invoices']['next_number'] ); ?></p></div>
          <div class="acdc-contract-label">Recommencer la numérotation à chaque nouvelle année</div>
          <div><label class="acdc-switch"><input type="checkbox" name="billing_settings[invoices][restart_each_year]" value="1" <?php checked( ! empty( $settings['invoices']['restart_each_year'] ) ); ?>><span class="acdc-switch-slider"></span></label></div>
          <div class="acdc-contract-label">Conditions de règlement des factures <span class="acdc-required">*</span></div>
          <div><textarea name="billing_settings[invoices][payment_terms]" rows="4"><?php echo esc_textarea( $settings['invoices']['payment_terms'] ); ?></textarea><p class="acdc-help">Indiquez ici les méthodes de règlement des factures que vous acceptez.</p></div>
          <div class="acdc-contract-label">Mention spéciale sur les factures</div>
          <div><textarea name="billing_settings[invoices][special_mention]" rows="4"><?php echo esc_textarea( $settings['invoices']['special_mention'] ); ?></textarea><p class="acdc-help">Indiquez ici toute autre information que vous souhaitez faire apparaître au bas de vos factures</p></div>
        </div>
      </div>
      <div class="acdc-panel acdc-profile-section">
        <h3>Avoirs</h3>
        <div class="acdc-contract-grid">
          <div class="acdc-contract-label">Préfixe des numéros des avoirs <span class="acdc-required">*</span></div>
          <div><input type="text" name="billing_settings[credit_notes][prefix]" value="<?php echo esc_attr( $settings['credit_notes']['prefix'] ); ?>"><div class="acdc-inline-wrap" style="margin-top:10px;gap:8px;"><span class="acdc-chip"><?php echo esc_html( $settings['credit_notes']['year_tag'] ); ?></span><span class="acdc-chip"><?php echo esc_html( $settings['credit_notes']['month_tag'] ); ?></span><span class="acdc-chip"><?php echo esc_html( $settings['credit_notes']['day_tag'] ); ?></span></div></div>
          <div class="acdc-contract-label">Conditions de règlement des avoirs <span class="acdc-required">*</span></div>
          <div><textarea name="billing_settings[credit_notes][payment_terms]" rows="4"><?php echo esc_textarea( $settings['credit_notes']['payment_terms'] ); ?></textarea><p class="acdc-help">Indiquez ici les méthodes de règlement que vous pratiquez concernant les avoirs.</p></div>
          <div class="acdc-contract-label">Mention spéciale sur les avoirs</div>
          <div><textarea name="billing_settings[credit_notes][special_mention]" rows="4"><?php echo esc_textarea( $settings['credit_notes']['special_mention'] ); ?></textarea><p class="acdc-help">Indiquez ici toute autre information que vous souhaitez faire apparaître au bas de vos avoirs</p></div>
        </div>
      </div>
      <div class="acdc-actions-end acdc-actions-gap-top"><button type="button" class="acdc-button acdc-button-soft" onclick="window.history.back();return false;">Annuler</button><button type="submit" class="acdc-button acdc-button-primary">Valider</button></div>
    </form>
    
    <?php
  }




  private function render_front_catalog_tab() {
    $view = isset( $_GET['catalog_view'] ) ? sanitize_key( wp_unslash( $_GET['catalog_view'] ) ) : 'list';
    if ( 'settings' === $view ) {
      $this->render_front_catalog_settings_page();
      return;
    }
    $this->render_front_catalog_list_page();
  }


  private function render_front_catalog_list_page() {
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'catalog' ) );
    $rows = $this->get_catalog_formations( true );
    ?>
    <section class="acdc-section-head"><div><h2>Catalogue</h2></div></section>
    <div class="acdc-panel acdc-mb-18">
      <form method="get" action="">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
        <input type="hidden" name="tab" value="catalog">
        <div class="acdc-inline-wrap" style="justify-content:space-between;align-items:center;gap:16px;">
          <input type="search" name="q" value="" placeholder="Rechercher" style="max-width:420px;">
          <div class="acdc-inline-wrap" style="gap:10px;">
            <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'catalog', 'catalog_view' => 'settings' ), $base_url ) ); ?>" class="acdc-button acdc-button-primary">Paramètres du catalogue</a>
            <a href="<?php echo esc_url( $this->get_catalog_page_url() ); ?>" class="acdc-button acdc-button-primary" target="_blank" rel="noopener">Accéder au catalogue</a>
          </div>
        </div>
      </form>
    </div>
    <div class="acdc-panel acdc-table-panel">
      <div style="display:flex;justify-content:flex-end;align-items:center;gap:12px;padding:8px 0 18px;">
        <button type="button" class="acdc-filter-toggle-icons-only" aria-label="Tri"><?php echo $this->render_inline_icon( 'settings', 16 ); ?></button>
        <button type="button" class="acdc-filter-toggle-icons-only" aria-label="Filtres"><?php echo $this->render_inline_icon( 'filter', 18 ); ?></button>
      </div>
      <div class="acdc-table-wrap">
        <table class="acdc-table acdc-catalog-table" data-acdc-table-id="catalog-list"><thead><tr><th></th><th>ID</th><th>INTITULÉ</th><th>FORMAT</th><th>DURÉE</th><th>TARIF (€ HT)</th><th>PAGE CATALOGUE</th><th>QR CODE PAGE</th><th>IMAGE</th><th>ORDRE D'AFFICHAGE</th><th></th></tr></thead><tbody>
        <?php if ( empty( $rows ) ) : ?><tr><td colspan="11"><?php $this->render_empty_table_state( 'Aucune donnée ne correspond aux critères demandés.' ); ?></td></tr><?php endif; ?>
        <?php foreach ( $rows as $row ) :
          $catalog_link = $this->get_catalog_page_url( array( 'formation_id' => $this->get_catalog_public_param( $row ) ) );
        ?>
          <tr>
            <td><input type="checkbox" disabled></td>
            <?php /* ACDC 3.25.206 — La colonne ID du catalogue porte le repère de
                     famille, comme le répertoire des formations : deux lignes au
                     même intitulé s'y suivaient sous deux numéros sans rapport
                     l'un avec l'autre. Le code propre à la formation, quand il
                     existe, garde la priorité — c'est la référence que David a
                     lui-même saisie. */ ?>
            <td><?php echo esc_html( ! empty( $row->code ) ? $row->code : $this->acdc_formation_reference( $row->id ) ); ?></td>
            <td><span style="color:#C5A253;font-weight:600;"><?php echo esc_html( $row->title ); ?></span></td>
            <td><?php echo esc_html( $row->modality ); ?></td>
            <td><?php echo esc_html( $row->duration ); ?></td>
            <td><?php echo esc_html( $this->format_currency_eur( $row->price_ht ) ); ?></td>
            <td><a href="<?php echo esc_url( $catalog_link ); ?>" target="_blank" rel="noopener">Lien</a></td>
            <td><div style="width:56px;height:56px;border:1px solid #dce4ec;border-radius:4px;background:repeating-linear-gradient(45deg,#111 0,#111 2px,#fff 2px,#fff 4px);"></div></td>
            <td><?php if ( ! empty( $row->catalog_image_url ) ) : ?><img src="<?php echo esc_url( $row->catalog_image_url ); ?>" alt="" style="width:36px;height:22px;object-fit:cover;border-radius:2px;"><?php else : ?>—<?php endif; ?></td>
            <td><?php echo esc_html( (int) $row->catalog_order ); ?></td>
            <td class="acdc-row-actions-menu-cell">
              <div class="acdc-row-menu" data-acdc-row-menu>
                <button type="button" class="acdc-row-menu-toggle" data-acdc-row-menu-toggle aria-expanded="false" aria-label="Actions"><?php echo $this->render_inline_icon( 'more-horizontal', 25 ); ?></button>
                <div class="acdc-row-menu-dropdown" data-acdc-row-menu-dropdown hidden>
                  <a href="#" data-acdc-modal-open="acdc-catalog-order-modal" data-formation-id="<?php echo esc_attr( $row->id ); ?>" data-formation-title="<?php echo esc_attr( $row->title ); ?>" data-catalog-order="<?php echo esc_attr( (int) $row->catalog_order ); ?>">Définir l'ordre d'affichage</a>
                  <a href="#" data-acdc-modal-open="acdc-catalog-remove-modal" data-formation-id="<?php echo esc_attr( $row->id ); ?>" data-formation-title="<?php echo esc_attr( $row->title ); ?>">Supprimer du catalogue</a>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody></table>
      </div>
      <div class="acdc-quotes-footer"><div>«</div><div>‹</div><div class="acdc-page-current">1</div><div>›</div><div>»</div><div class="acdc-page-meta"><?php echo count( $rows ); ?>-<?php echo count( $rows ); ?> de <?php echo count( $rows ); ?></div></div>
    </div>
    <?php $this->render_catalog_order_modal(); ?>
    <?php $this->render_catalog_remove_modal(); ?>
    <style>.acdc-catalog-table td,.acdc-catalog-table th{vertical-align:middle}.acdc-row-actions-menu-cell{position:relative}.acdc-row-menu{position:relative;display:inline-flex;align-items:center;justify-content:center}.acdc-row-menu-toggle{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border:none;background:transparent;color:#1E4777;cursor:pointer}.acdc-row-menu-dropdown{position:absolute;top:38px;right:0;min-width:240px;padding:10px 0;background:#fff;border:1px solid #dce4ec;border-radius:10px;box-shadow:0 10px 30px rgba(28,44,64,.12);z-index:100}.acdc-row-menu-dropdown a{display:block;padding:14px 18px;color:#1E4777;text-decoration:none;font-size:15px;font-weight:600}.acdc-row-menu-dropdown a:hover{background:#F6F8FB}</style>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
      function closeCatalogMenus(){
        document.querySelectorAll('[data-acdc-row-menu-dropdown]').forEach(function(menu){ menu.hidden = true; });
        document.querySelectorAll('[data-acdc-row-menu-toggle]').forEach(function(btn){ btn.setAttribute('aria-expanded','false'); });
      }
      document.querySelectorAll('[data-acdc-row-menu-toggle]').forEach(function(btn){
        btn.addEventListener('click', function(e){
          e.preventDefault();
          e.stopPropagation();
          var wrapper = btn.closest('[data-acdc-row-menu]');
          var dropdown = wrapper ? wrapper.querySelector('[data-acdc-row-menu-dropdown]') : null;
          var open = btn.getAttribute('aria-expanded') === 'true';
          closeCatalogMenus();
          if (dropdown && !open) {
            dropdown.hidden = false;
            btn.setAttribute('aria-expanded','true');
          }
        });
      });
      document.addEventListener('click', function(e){
        if (!e.target.closest('[data-acdc-row-menu]')) {
          closeCatalogMenus();
        }
        var trigger=e.target.closest('[data-acdc-modal-open="acdc-catalog-order-modal"]');
        if(trigger){
          var modal=document.getElementById('acdc-catalog-order-modal');
          if(modal){
            modal.querySelector('input[name="formation_id"]').value=trigger.getAttribute('data-formation-id')||'';
            modal.querySelector('input[name="catalog_order"]').value=trigger.getAttribute('data-catalog-order')||'1';
          }
          closeCatalogMenus();
        }
        var trigger2=e.target.closest('[data-acdc-modal-open="acdc-catalog-remove-modal"]');
        if(trigger2){
          var modal2=document.getElementById('acdc-catalog-remove-modal');
          if(modal2){ modal2.querySelector('input[name="formation_id"]').value=trigger2.getAttribute('data-formation-id')||''; }
          closeCatalogMenus();
        }
      });
    });
    </script>
    <?php
  }


  private function render_catalog_order_modal() {
    ?>
    <div class="acdc-modal-shell" id="acdc-catalog-order-modal" hidden><div class="acdc-modal-backdrop" data-acdc-modal-close></div><div class="acdc-modal-dialog"><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button><div class="acdc-modal-header"><h3>DÉFINIR L'ORDRE D'AFFICHAGE</h3></div><div class="acdc-modal-body"><p class="acdc-modal-lead">Êtes-vous sûr·e de vouloir exécuter cette action ?</p><form class="acdc-form acdc-contract-builder-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="acdc_set_catalog_order"><?php wp_nonce_field( 'acdc_set_catalog_order' ); ?><input type="hidden" name="formation_id" value=""><div class="acdc-contract-grid acdc-contract-grid-compact"><div class="acdc-contract-label">Ordre d'affichage <span class="acdc-required">*</span></div><div><input type="number" name="catalog_order" min="1" value="1"><p class="acdc-help">Entrez la position où cette formation doit apparaître dans le catalogue. Les autres formations seront automatiquement décalées.</p></div></div><div class="acdc-actions-end acdc-actions-gap-top"><button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-close>Annuler</button><button type="submit" class="acdc-button acdc-button-primary">Exécuter l'action</button></div></form></div></div></div>
    <?php
  }


  private function render_catalog_remove_modal() {
    ?>
    <div class="acdc-modal-shell" id="acdc-catalog-remove-modal" hidden><div class="acdc-modal-backdrop" data-acdc-modal-close></div><div class="acdc-modal-dialog"><button type="button" class="acdc-modal-close" data-acdc-modal-close aria-label="Fermer">×</button><div class="acdc-modal-header"><h3>SUPPRIMER DU CATALOGUE</h3></div><div class="acdc-modal-body"><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="acdc_remove_from_catalog"><?php wp_nonce_field( 'acdc_remove_from_catalog' ); ?><input type="hidden" name="formation_id" value=""><p style="font-size:18px;color:#1E4777;line-height:1.45;">Cette action permet de supprimer la formation du catalogue. Pour réactiver son affichage, rendez-vous dans le répertoire “Formations”.</p><div class="acdc-actions-end acdc-actions-gap-top-lg"><button type="button" class="acdc-button acdc-button-soft" data-acdc-modal-close>Annuler</button><button type="submit" class="acdc-button acdc-button-primary">Exécuter l'action</button></div></form></div></div></div>
    <?php
  }


  private function render_front_catalog_settings_page() {
    $settings = $this->get_catalog_settings();
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'catalog' ) );
    ?>
    <section class="acdc-section-head"><div><h2>Modifier les paramètres du catalogue : ACDC FORMATION</h2></div></section>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acdc-form">
      <input type="hidden" name="action" value="acdc_save_catalog_settings"><?php wp_nonce_field( 'acdc_save_catalog_settings' ); ?>
      <div class="acdc-panel acdc-profile-section"><div class="acdc-contract-grid">
        <div class="acdc-contract-label">Type de domaine <span class="acdc-required">*</span></div><div><select name="catalog_settings[domain_type]"><option value="catalogue.teetche.com" <?php selected($settings['domain_type'],'catalogue.teetche.com'); ?>>catalogue.teetche.com</option></select></div>
        <div class="acdc-contract-label">Nom de domaine <span class="acdc-required">*</span></div><div><div class="acdc-input-suffix-wrap"><input type="text" name="catalog_settings[domain_name]" value="<?php echo esc_attr( $settings['domain_name'] ); ?>" class="acdc-input-suffix-field"><span class="acdc-input-suffix-addon">.catalogue.teetche.com</span></div></div>
        <div class="acdc-contract-label">Texte d'accroche</div><div><textarea name="catalog_settings[hook_text]" rows="3"><?php echo esc_textarea( $settings['hook_text'] ); ?></textarea></div>
        <div class="acdc-contract-label">Bouton d'appel à l'action <span class="acdc-required">*</span></div><div><input type="text" name="catalog_settings[cta_label]" value="<?php echo esc_attr( $settings['cta_label'] ); ?>"></div>
        <div class="acdc-contract-label">Afficher les tarifs des formations</div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[show_prices]" value="1" <?php checked(!empty($settings['show_prices'])); ?>><span class="acdc-switch-slider"></span></label></div>
        <div class="acdc-contract-label">Afficher le taux de satisfaction des formations</div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[show_satisfaction]" value="1" <?php checked(!empty($settings['show_satisfaction'])); ?>><span class="acdc-switch-slider"></span></label><p class="acdc-help">En activant cette option, les prospects pourront consulter, formation par formation, la note moyenne issue des enquêtes à chaud effectuées sur la plateforme.</p></div>
        <div class="acdc-contract-label">E-mail nouvelle inscription</div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[notify_new_registration]" value="1" <?php checked(!empty($settings['notify_new_registration'])); ?>><span class="acdc-switch-slider"></span></label></div>
        <div class="acdc-contract-label">Politique de confidentialité</div><div><input type="url" name="catalog_settings[privacy_policy_url]" value="<?php echo esc_attr( $settings['privacy_policy_url'] ); ?>" placeholder="https://..."><p class="acdc-help">Lien utilisé dans la case RGPD du formulaire catalogue.</p></div>
      </div></div>
      <div class="acdc-panel acdc-profile-section"><h3>Pied de page</h3><div class="acdc-contract-grid"><div class="acdc-contract-label">Afficher l'adresse</div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[footer_show_address]" value="1" <?php checked(!empty($settings['footer_show_address'])); ?>><span class="acdc-switch-slider"></span></label></div><div class="acdc-contract-label">Afficher l'e-mail de contact</div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[footer_show_email]" value="1" <?php checked(!empty($settings['footer_show_email'])); ?>><span class="acdc-switch-slider"></span></label></div><div class="acdc-contract-label">Afficher le tél. de contact</div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[footer_show_phone]" value="1" <?php checked(!empty($settings['footer_show_phone'])); ?>><span class="acdc-switch-slider"></span></label></div><div class="acdc-contract-label">Afficher le site web</div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[footer_show_website]" value="1" <?php checked(!empty($settings['footer_show_website'])); ?>><span class="acdc-switch-slider"></span></label></div><div class="acdc-contract-label">Afficher les CGV</div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[footer_show_cgv]" value="1" <?php checked(!empty($settings['footer_show_cgv'])); ?>><span class="acdc-switch-slider"></span></label></div><div class="acdc-contract-label">Certificat Qualiopi</div><div><div class="acdc-upload-dropzone acdc-upload-dropzone-static"><button type="button" class="acdc-button acdc-button-primary">Choisir le fichier</button><span>Déposez le fichier ou cliquez pour choisir</span></div></div><div class="acdc-contract-label">Image de pied de page</div><div><div class="acdc-upload-dropzone acdc-upload-dropzone-static"><button type="button" class="acdc-button acdc-button-primary">Choisir le fichier</button><span>Déposez le fichier ou cliquez pour choisir</span></div></div></div></div>
      <div class="acdc-panel acdc-profile-section"><h3>Champs obligatoires</h3><div class="acdc-contract-grid"><div class="acdc-contract-label">Profil</div><div><select name="catalog_settings[profile_default]"><option value="Particulier" <?php selected($settings['profile_default'],'Particulier'); ?>>Particulier</option><option value="Entreprise" <?php selected($settings['profile_default'],'Entreprise'); ?>>Entreprise</option></select></div>
      <div class="acdc-contract-label">Genre <span class="acdc-required">*</span></div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[required_gender]" value="1" <?php checked(!empty($settings['required_gender'])); ?>><span class="acdc-switch-slider"></span></label></div>
      <div class="acdc-contract-label">Prénom <span class="acdc-required">*</span></div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[required_first_name]" value="1" <?php checked(!empty($settings['required_first_name'])); ?>><span class="acdc-switch-slider"></span></label></div>
      <div class="acdc-contract-label">Nom <span class="acdc-required">*</span></div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[required_last_name]" value="1" <?php checked(!empty($settings['required_last_name'])); ?>><span class="acdc-switch-slider"></span></label></div>
      <div class="acdc-contract-label">E-mail <span class="acdc-required">*</span></div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[required_email]" value="1" <?php checked(!empty($settings['required_email'])); ?>><span class="acdc-switch-slider"></span></label></div>
      <div class="acdc-contract-label">Téléphone <span class="acdc-required">*</span></div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[required_phone]" value="1" <?php checked(!empty($settings['required_phone'])); ?>><span class="acdc-switch-slider"></span></label></div>
      <div class="acdc-contract-label">Formation souhaitée <span class="acdc-required">*</span></div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[required_desired_training]" value="1" <?php checked(!empty($settings['required_desired_training'])); ?>><span class="acdc-switch-slider"></span></label></div>
      <div class="acdc-contract-label">RGPD (case à cocher) <span class="acdc-required">*</span></div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[required_rgpd]" value="1" <?php checked(!empty($settings['required_rgpd'])); ?>><span class="acdc-switch-slider"></span></label><p class="acdc-help">J'accepte le traitement de mes données selon la politique de confidentialité de l'organisme de formation.</p></div></div></div>
      <div class="acdc-panel acdc-profile-section"><h3>Champs facultatifs</h3><div class="acdc-contract-grid"><div class="acdc-contract-label">Adresse</div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[optional_address]" value="1" <?php checked(!empty($settings['optional_address'])); ?>><span class="acdc-switch-slider"></span></label></div><div class="acdc-contract-label">Code postal</div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[optional_postal_code]" value="1" <?php checked(!empty($settings['optional_postal_code'])); ?>><span class="acdc-switch-slider"></span></label></div><div class="acdc-contract-label">Ville</div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[optional_city]" value="1" <?php checked(!empty($settings['optional_city'])); ?>><span class="acdc-switch-slider"></span></label></div><div class="acdc-contract-label">Niveau d'études</div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[optional_education_level]" value="1" <?php checked(!empty($settings['optional_education_level'])); ?>><span class="acdc-switch-slider"></span></label></div><div class="acdc-contract-label">Commentaire</div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[optional_comment]" value="1" <?php checked(!empty($settings['optional_comment'])); ?>><span class="acdc-switch-slider"></span></label></div><div class="acdc-contract-label">Postuler aux sessions à venir</div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[optional_future_sessions]" value="1" <?php checked(!empty($settings['optional_future_sessions'])); ?>><span class="acdc-switch-slider"></span></label><p class="acdc-help">En activant cette option, les prospects pourront sélectionner la session à laquelle ils souhaitent s'inscrire.</p></div></div></div>
      <div class="acdc-panel acdc-profile-section"><h3>Statut</h3><div class="acdc-contract-grid"><div class="acdc-contract-label">Activé</div><div><label class="acdc-switch"><input type="checkbox" name="catalog_settings[active]" value="1" <?php checked(!empty($settings['active'])); ?>><span class="acdc-switch-slider"></span></label><p class="acdc-help">En désactivant cette option, l'accès à votre catalogue sera suspendu.</p></div></div></div>
      <div class="acdc-actions-end" style="margin-top:18px;"><a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'catalog' ), $base_url ) ); ?>" class="acdc-button acdc-button-soft">Annuler</a><button type="submit" class="acdc-button acdc-button-primary">Valider</button></div>
    </form>
    <?php
  }


  public function render_catalog_shortcode() {
    $settings = $this->get_catalog_settings();
    if ( empty( $settings['active'] ) ) {
      return '<div class="acdc-public-catalog-shell"><div class="acdc-public-catalog-empty">Le catalogue est momentanément indisponible.</div></div>';
    }
    /* ACDC 3.20.79 — Le paramètre formation_id accepte soit un ID auto
       (ex. "11"), soit un code variante (ex. "1.1"). On valide le format
       puis on délègue la résolution à get_catalog_formation. */
    $formation_param = isset( $_GET['formation_id'] ) ? trim( wp_unslash( (string) $_GET['formation_id'] ) ) : '';
    if ( '' !== $formation_param && ! preg_match( '/^[0-9]+(\.[0-9]+)?$/', $formation_param ) ) {
      $formation_param = '';
    }
    ob_start();
    if ( '' !== $formation_param ) {
      $this->render_public_catalog_detail( $formation_param, $settings );
    } else {
      $this->render_public_catalog_list( $settings );
    }
    return (string) ob_get_clean();
  }


  private function render_public_catalog_list( $settings ) {
    $rows = $this->get_catalog_formations( false );
    $default_catalog_image_url = trailingslashit( ACDC_OF_SAAS_URL ) . 'assets/img/acdc-logo.png';
    ?>
    <div class="acdc-catalog-public-wrap">
      <div class="acdc-catalog-public-inner">
        <section class="acdc-catalog-hero-panel">
          <span class="acdc-catalog-kicker">Catalogue public ACDC</span>
          <h1>Catalogue de formations</h1>
          <p class="acdc-catalog-public-hook"><?php echo esc_html( $settings['hook_text'] ); ?></p>
          <form class="acdc-catalog-search" action="" method="get" onsubmit="return false;">
            <span class="acdc-catalog-search-icon"><?php echo $this->render_inline_icon( 'search', 18 ); ?></span>
            <input type="search" id="acdc-catalog-search-input" placeholder="Rechercher une formation par nom, description, durée ou modalité..." aria-label="Rechercher une formation dans le catalogue">
          </form>
          <div class="acdc-catalog-chips" id="acdc-catalog-chips">
            <button type="button" class="acdc-catalog-chip" data-modality="Présentiel">Présentiel</button>
            <button type="button" class="acdc-catalog-chip" data-modality="Distanciel">Distanciel</button>
            <button type="button" class="acdc-catalog-chip" data-modality="Hybride">Hybride</button>
            <button type="button" class="acdc-catalog-chip" data-modality="E-learning">E-learning</button>
            <button type="button" class="acdc-catalog-chip-reset" id="acdc-catalog-chip-reset" hidden>✕ Afficher toutes les formations</button>
          </div>
        </section>

        <div class="acdc-catalog-grid" id="acdc-catalog-grid">
          <?php foreach ( $rows as $row ) :
            $detail_url = $this->get_catalog_page_url( array( 'formation_id' => $this->get_catalog_public_param( $row ) ) );
            $search_parts = array_filter(
              array(
                $row->title,
                $row->description_text,
                $row->duration,
                $row->modality,
                trim( $row->address . ' ' . $row->postal_code . ' ' . $row->city ),
              )
            );
            $search_blob = strtolower( trim( wp_strip_all_tags( implode( ' ', $search_parts ) ) ) );
          ?>
            <article class="acdc-catalog-card" data-modality="<?php echo esc_attr( (string) $row->modality ); ?>" data-search="<?php echo esc_attr( $search_blob ); ?>">
              <a href="<?php echo esc_url( $detail_url ); ?>" class="acdc-catalog-card-image">
                <img src="<?php echo esc_url( ! empty( $row->catalog_image_url ) ? $row->catalog_image_url : $default_catalog_image_url ); ?>" alt="<?php echo esc_attr( $row->title ); ?>">
              </a>
              <div class="acdc-catalog-card-head">
                <div class="acdc-catalog-card-topline">
                  <span class="acdc-catalog-card-badge"><?php echo esc_html( $row->modality ?: 'Formation' ); ?></span>
                  <?php if ( ! empty( $settings['show_prices'] ) ) : ?>
                    <span class="acdc-catalog-price"><?php echo esc_html( $this->format_currency_eur( $row->price_ht ) ); ?></span>
                  <?php endif; ?>
                </div>
                <h3><?php echo esc_html( $row->title ); ?></h3>
              </div>
              <div class="acdc-catalog-card-body">
                <p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( (string) $row->description_text ), 20 ) ); ?></p>
                <ul>
                  <li><strong>Durée :</strong> <?php echo esc_html( $row->duration ?: 'À définir' ); ?></li>
                  <li><strong>Modalité :</strong> <?php echo esc_html( $row->modality ?: 'À définir' ); ?></li>
                  <?php echo $this->render_catalog_location_lines( $row ); ?>
                </ul>
              </div>
              <div class="acdc-catalog-card-footer">
                <a href="<?php echo esc_url( $detail_url ); ?>" class="acdc-catalog-cta"><?php echo esc_html( $settings['cta_label'] ); ?></a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <div class="acdc-public-catalog-empty acdc-public-catalog-search-empty" id="acdc-catalog-empty" hidden>Aucune formation ne correspond à votre recherche.</div>
      </div>
    </div>
    <?php $this->render_public_catalog_styles();
  }


  private function render_public_catalog_detail( $formation_id, $settings ) {
    $formation = $this->get_catalog_formation( $formation_id );
    $default_catalog_image_url = trailingslashit( ACDC_OF_SAAS_URL ) . 'assets/img/acdc-logo.png';
    if ( ! $formation || (int) $formation->catalog_public !== 1 ) {
      echo '<div class="acdc-catalog-public-wrap"><div class="acdc-public-catalog-empty">Formation introuvable.</div></div>';
      $this->render_public_catalog_styles();
      return;
    }
    $notice = isset( $_GET['catalog_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['catalog_notice'] ) ) : '';
    ?>
    <div class="acdc-catalog-detail-wrap">
      <div class="acdc-catalog-detail-inner">
        <?php if ( $notice ) : ?>
          <div class="acdc-alert acdc-alert-success"><?php echo esc_html( $notice ); ?></div>
        <?php endif; ?>

        <div class="acdc-breadcrumb"><a href="<?php echo esc_url( $this->get_catalog_page_url() ); ?>">Accueil</a> / <span><?php echo esc_html( $formation->title ); ?></span></div>

        <div class="acdc-catalog-detail-grid">
          <div class="acdc-catalog-detail-left">
            <div class="acdc-catalog-hero"><img src="<?php echo esc_url( ! empty( $formation->catalog_image_url ) ? $formation->catalog_image_url : $default_catalog_image_url ); ?>" alt="<?php echo esc_attr( $formation->title ); ?>"></div>

            <div class="acdc-catalog-info-card acdc-catalog-main-card">
              <div class="acdc-catalog-main-topline">
                <span class="acdc-catalog-card-badge"><?php echo esc_html( $formation->modality ?: 'Formation' ); ?></span>
                <?php if ( ! empty( $settings['show_prices'] ) ) : ?>
                  <div class="acdc-catalog-price"><?php echo esc_html( $this->format_currency_eur( $formation->price_ht ) ); ?></div>
                <?php endif; ?>
              </div>
              <h1><?php echo esc_html( $formation->title ); ?></h1>
              <ul>
                <li><strong>Durée :</strong> <?php echo esc_html( $formation->duration ?: 'À définir' ); ?></li>
                <li><strong>Modalité :</strong> <?php echo esc_html( $formation->modality ?: 'À définir' ); ?></li>
                <?php echo $this->render_catalog_location_lines( $formation ); ?>
              </ul>
            </div>

            <div class="acdc-catalog-info-card">
              <h3>Description</h3>
              <div><?php echo wp_kses_post( wpautop( (string) $formation->description_text ) ); ?></div>
            </div>

            <div class="acdc-catalog-info-card">
              <h3>Objectifs de la formation</h3>
              <div><?php echo wp_kses_post( wpautop( (string) $formation->objectives ) ); ?></div>
            </div>

            <div class="acdc-catalog-info-card">
              <h3>Public cible</h3>
              <div><?php echo wp_kses_post( wpautop( (string) $formation->catalog_audience ) ); ?></div>
            </div>

            <div class="acdc-catalog-info-card">
              <h3>Prérequis</h3>
              <div><?php echo wp_kses_post( wpautop( (string) $formation->prerequisites ) ); ?></div>
            </div>

            <div class="acdc-catalog-info-card">
              <h3>Programme de formation</h3>
              <?php $catalog_program_url = $this->acdc_formation_programme_file( $formation )['url']; ?>
              <?php /* ACDC 3.25.251 — Catalogue PUBLIC : uniquement un fichier déposé.
                       La page programme du portail exige une connexion ; l'offrir ici
                       enverrait le visiteur sur un écran de refus. */ ?>
              <?php if ( '' !== $catalog_program_url ) : ?>
                <a class="acdc-catalog-cta acdc-catalog-download" href="<?php echo esc_url( $catalog_program_url ); ?>" target="_blank" rel="noopener">Télécharger le programme</a>
              <?php else : ?>
                <p>Aucun programme disponible.</p>
              <?php endif; ?>
            </div>
          </div>

          <div class="acdc-catalog-detail-right"><?php $this->render_public_catalog_signup_form( $formation, $settings ); ?></div>
        </div>
      </div>
    </div>
    <?php $this->render_public_catalog_styles();
  }


  private function render_public_catalog_signup_form( $formation, $settings ) {
    ?>
    <div class="acdc-catalog-signup-card">
      <div class="acdc-catalog-signup-head">
        <span class="acdc-catalog-kicker">Inscription rapide</span>
        <h2><?php echo esc_html( $settings['cta_label'] ); ?></h2>
        <p>Renseignez vos coordonnées. Nous vous recontactons pour finaliser votre demande d'inscription.</p>
      </div>
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acdc-catalog-signup-form">
        <?php wp_nonce_field( 'acdc_catalog_register' ); ?>
        <input type="hidden" name="action" value="acdc_catalog_register">
        <input type="hidden" name="formation_id" value="<?php echo esc_attr( $formation->id ); ?>">
        <div style="position:absolute;left:-9999px;top:-9999px;width:0;height:0;overflow:hidden;" aria-hidden="true"><label for="acdc_hp_field">Ne pas remplir</label><input type="text" id="acdc_hp_field" name="acdc_hp" value="" tabindex="-1" autocomplete="off"></div>

        <div class="acdc-catalog-form-section">
          <div class="acdc-catalog-field-row acdc-catalog-field-row-single">
            <div>
              <label>Profil</label>
              <select name="catalog[profile_type]">
                <option value="Particulier">Particulier</option>
                <option value="Entreprise">Entreprise</option>
              </select>
            </div>
          </div>

          <div class="acdc-catalog-form-grid">
            <div>
              <label>Prénom <span>*</span></label>
              <input type="text" name="catalog[first_name]" placeholder="Votre prénom" required>
            </div>
            <div>
              <label>Nom <span>*</span></label>
              <input type="text" name="catalog[last_name]" placeholder="Votre nom" required>
            </div>
            <div>
              <label>E-mail <span>*</span></label>
              <input type="email" name="catalog[email]" placeholder="nom@entreprise.fr" required>
            </div>
            <div>
              <label>Téléphone <span>*</span></label>
              <input type="text" name="catalog[phone]" placeholder="06 00 00 00 00" required>
            </div>
          </div>
        </div>

        <div class="acdc-catalog-form-section">
          <div class="acdc-catalog-form-grid">
            <div class="acdc-catalog-field-col-span-2">
              <label>Adresse</label>
              <input type="text" name="catalog[address]" placeholder="Adresse complète">
            </div>
            <div>
              <label>Code postal</label>
              <input type="text" name="catalog[postal_code]" placeholder="83310">
            </div>
            <div>
              <label>Ville</label>
              <input type="text" name="catalog[city]" placeholder="Cogolin">
            </div>
          </div>
        </div>

        <div class="acdc-catalog-form-section">
          <label>Commentaire</label>
          <textarea name="catalog[comment_text]" rows="4" placeholder="Précisez votre contexte, votre besoin ou vos attentes."></textarea>
        </div>

        <label class="acdc-catalog-consent-line">
          <input type="checkbox" name="catalog[rgpd]" value="1" required>
          <span>J'accepte le traitement de mes données selon la <a href="<?php echo esc_url( ! empty( $settings['privacy_policy_url'] ) ? $settings['privacy_policy_url'] : $this->get_default_catalog_privacy_policy_url() ); ?>" target="_blank" rel="noopener">politique de confidentialité</a>.</span>
        </label>

        <div class="acdc-catalog-form-foot">
          <p>Les champs marqués d'un <strong>*</strong> sont obligatoires.</p>
          <button type="submit" class="acdc-catalog-cta"><?php echo esc_html( $settings['cta_label'] ); ?></button>
        </div>
      </form>
    </div>
    <?php
  }


  private function render_public_catalog_styles() {
    ?>
    <style>
      .acdc-catalog-public-wrap,.acdc-catalog-detail-wrap{--acdc-font:Rubik,Arial,system-ui,sans-serif;--acdc-bg:#F6F8FB;--acdc-surface:#F9FAFB;--acdc-text:#0C2D52;--acdc-text-muted:#1E4777;--acdc-text-light:#3C3C3C;--acdc-primary:#C5A253;--acdc-primary-dark:#D7A24B;--acdc-primary-soft:#F3E3BF;--acdc-border:#DCE4EC;--acdc-shadow-panel:0 4px 14px rgba(28,44,64,.05);--acdc-shadow-modal:0 10px 30px rgba(28,44,64,.12);--acdc-button-gradient:linear-gradient(90deg,#8A5A2B 0%,#C28A3A 17%,#D7A24B 34%,#E9C77C 50%,#D7A24B 67%,#B7772E 84%,#6B3F1D 100%);background:linear-gradient(180deg,#fbf8f7 0%,#f6f8fb 100%);padding:32px 0 72px;min-height:100vh;color:var(--acdc-text);font-family:var(--acdc-font)}
      .acdc-catalog-public-wrap *, .acdc-catalog-detail-wrap *{box-sizing:border-box;font-family:var(--acdc-font)}
      .acdc-catalog-public-inner,.acdc-catalog-detail-inner{width:min(1360px,92vw);margin:0 auto}
      .acdc-catalog-hero-panel{margin:0 auto 28px;max-width:900px;text-align:center}
      .acdc-catalog-kicker{display:inline-flex;align-items:center;justify-content:center;min-height:32px;padding:0 12px;border-radius:999px;background:rgba(197,162,83,.14);border:1px solid rgba(197,162,83,.35);color:var(--acdc-text);font-size:12px;font-weight:700;letter-spacing:.02em;text-transform:uppercase}
      .acdc-catalog-public-inner h1{font-size:clamp(38px,5vw,62px);line-height:1.05;text-align:center;margin:14px 0 12px;color:#0B0706}
      .acdc-catalog-public-hook{max-width:760px;margin:0 auto 24px;text-align:center;font-size:18px;line-height:1.55;color:var(--acdc-text-light)}
      .acdc-catalog-search{display:flex;align-items:center;gap:12px;max-width:760px;margin:0 auto 18px;background:var(--acdc-surface);border:1px solid var(--acdc-border);border-radius:14px;padding:0 18px;height:58px;box-shadow:var(--acdc-shadow-panel)}
      .acdc-catalog-search-icon{display:inline-flex;align-items:center;justify-content:center;color:var(--acdc-primary)}
      .acdc-catalog-search-icon svg,.acdc-catalog-search-icon svg *{width:18px;height:18px;color:var(--acdc-primary)!important;stroke:currentColor!important;fill:none!important}
      .acdc-catalog-search input{border:none;outline:none;flex:1;height:100%;background:transparent;color:var(--acdc-text);font-size:14px}
      .acdc-catalog-search input::placeholder{color:#6c7786}
      .acdc-catalog-chips{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin:0 0 34px;align-items:center}
      .acdc-catalog-chip{padding:10px 16px;border:1px solid rgba(197,162,83,.35);border-radius:999px;background:var(--acdc-primary-soft);color:var(--acdc-text);font-size:13px;font-weight:600;cursor:pointer;user-select:none;transition:all .15s ease;font-family:inherit}
      .acdc-catalog-chip:hover{border-color:var(--acdc-primary);background:var(--acdc-primary-soft)}
      .acdc-catalog-chip.is-active{background:var(--acdc-primary);color:#fff;border-color:var(--acdc-primary)}
      .acdc-catalog-chip-reset{padding:10px 16px;border:1px solid #d4d8df;border-radius:999px;background:transparent;color:#5a6577;font-size:13px;font-weight:500;cursor:pointer;user-select:none;font-family:inherit;transition:all .15s ease}
      .acdc-catalog-chip-reset:hover{border-color:#1E4777;color:#1E4777}
      .acdc-catalog-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px}
      .acdc-catalog-card,.acdc-catalog-info-card,.acdc-catalog-signup-card{background:var(--acdc-surface);border-radius:18px;border:1px solid var(--acdc-border);box-shadow:var(--acdc-shadow-panel);overflow:hidden}
      .acdc-catalog-card{display:flex;flex-direction:column;min-height:100%}
      /* ACDC 3.20.80 — Force le masquage des cards quand l'attribut [hidden] est appliqué par le JS de filtre.
         Sans cette règle, .acdc-catalog-card{display:flex} (spécificité plus élevée) annule le display:none
         implicite de [hidden] et les cards restent visibles malgré le filtre. */
      .acdc-catalog-card[hidden]{display:none!important}
      .acdc-catalog-card-image{display:block;height:220px;background:#ebeef2}
      .acdc-catalog-card-image img,.acdc-catalog-hero img{width:100%;height:100%;object-fit:cover}
      .acdc-catalog-card-head{background:linear-gradient(180deg,rgba(243,227,191,.92) 0%,rgba(249,250,251,.96) 100%);padding:18px 20px 14px;border-bottom:1px solid rgba(197,162,83,.18)}
      .acdc-catalog-card-topline,.acdc-catalog-main-topline{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}
      .acdc-catalog-card-badge{display:inline-flex;align-items:center;justify-content:center;min-height:30px;padding:0 12px;border-radius:999px;background:#fff;border:1px solid rgba(197,162,83,.35);color:var(--acdc-text);font-size:12px;font-weight:700}
      .acdc-catalog-card-head h3{font-size:28px;line-height:1.15;color:var(--acdc-text);margin:0;font-weight:700}
      .acdc-catalog-price{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:0 14px;border-radius:999px;background:#fff;border:1px solid rgba(197,162,83,.35);color:var(--acdc-text);font-size:13px;font-weight:700}
      .acdc-catalog-card-body{padding:18px 20px 10px;color:var(--acdc-text-light);display:flex;flex-direction:column;gap:14px;flex:1}
      .acdc-catalog-card-body p{margin:0;line-height:1.6}
      .acdc-catalog-card-body ul,.acdc-catalog-main-card ul{list-style:none;margin:0;padding:0;display:grid;gap:10px;color:var(--acdc-text-light)}
      .acdc-catalog-card-body li,.acdc-catalog-main-card li{padding-left:0;line-height:1.55}
      /* ACDC 3.20.81 — Note explicative pour modalités distancielles / hybrides / e-learning */
      .acdc-catalog-location-note{font-style:italic;color:#5a6577;font-size:13px}
      .acdc-catalog-card-body strong,.acdc-catalog-main-card strong{color:var(--acdc-text)}
      .acdc-catalog-card-footer{padding:0 20px 20px}
      .acdc-catalog-cta{display:inline-flex;align-items:center;justify-content:center;min-height:42px;width:100%;padding:10px 18px;border-radius:10px;border:1px solid #6F441A;background:var(--acdc-button-gradient);color:#0B0706!important;text-decoration:none;font-size:13px;font-weight:700;line-height:16px;box-shadow:var(--acdc-shadow-panel);transition:transform .18s ease,filter .18s ease}
      .acdc-catalog-cta:hover,.acdc-catalog-cta:focus-visible{transform:translateY(-1px);filter:saturate(1.03) brightness(1.02)}
      .acdc-breadcrumb{margin:4px 0 18px;color:#687387;font-size:13px}
      .acdc-breadcrumb a{color:var(--acdc-primary);text-decoration:none;font-weight:700}
      .acdc-catalog-detail-grid{display:grid;grid-template-columns:minmax(0,1fr) 420px;gap:28px;align-items:start}
      .acdc-catalog-hero{height:280px;border-radius:18px;overflow:hidden;margin-bottom:18px;background:#ebeef2;border:1px solid var(--acdc-border);box-shadow:var(--acdc-shadow-panel)}
      .acdc-catalog-main-card{background:linear-gradient(180deg,rgba(243,227,191,.92) 0%,rgba(249,250,251,.96) 100%);padding:24px;border:1px solid rgba(197,162,83,.25)}
      .acdc-catalog-main-card h1{font-size:36px;line-height:1.15;color:var(--acdc-text);margin:0 0 14px}
      .acdc-catalog-info-card{padding:24px;margin-bottom:18px}
      .acdc-catalog-info-card h3{font-size:24px;line-height:1.2;margin:0 0 14px;color:#0B0706}
      .acdc-catalog-info-card p,.acdc-catalog-info-card li{font-size:14px;line-height:1.65;color:var(--acdc-text-light)}
      .acdc-catalog-signup-card{padding:24px;position:sticky;top:24px;background:linear-gradient(180deg,#fff 0%,#fbf8f7 100%)}
      .acdc-catalog-signup-head{margin-bottom:18px}
      .acdc-catalog-signup-head h2{margin:10px 0 8px;font-size:34px;line-height:1.1;color:#0B0706}
      .acdc-catalog-signup-head p{margin:0;color:var(--acdc-text-light);font-size:14px;line-height:1.6}
      .acdc-catalog-signup-form{display:grid;gap:16px}
      .acdc-catalog-form-section{padding:16px;border:1px solid rgba(197,162,83,.16);border-radius:14px;background:#fff}
      .acdc-catalog-signup-card label{display:block;margin:0 0 8px;color:var(--acdc-text);font-size:13px;font-weight:700}
      .acdc-catalog-signup-card label span{color:#8A5A2B}
      .acdc-catalog-signup-card input,.acdc-catalog-signup-card select,.acdc-catalog-signup-card textarea{width:100%;min-height:44px;border:1px solid var(--acdc-border);border-radius:10px;padding:0 14px;background:#fff;color:var(--acdc-text);font-size:14px;outline:none;transition:border-color .18s ease, box-shadow .18s ease, background .18s ease}
      .acdc-catalog-signup-card input:focus,.acdc-catalog-signup-card select:focus,.acdc-catalog-signup-card textarea:focus{border-color:var(--acdc-primary);box-shadow:0 0 0 3px rgba(197,162,83,.18)}
      .acdc-catalog-signup-card textarea{height:auto;min-height:112px;padding:12px 14px;resize:vertical}
      .acdc-catalog-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
      .acdc-catalog-field-col-span-2{grid-column:1/-1}
      .acdc-catalog-field-row-single{grid-template-columns:1fr}
      .acdc-catalog-consent-line{display:flex!important;align-items:flex-start;gap:10px;margin:0;color:var(--acdc-text-light)!important;font-size:13px;line-height:1.55;font-weight:500!important}
      .acdc-catalog-consent-line input{width:18px!important;min-width:18px;height:18px!important;min-height:18px;margin:2px 0 0;accent-color:#8A5A2B}
      .acdc-catalog-consent-line a{color:var(--acdc-text);font-weight:700}
      .acdc-catalog-form-foot{display:grid;gap:10px}
      .acdc-catalog-form-foot p{margin:0;color:#687387;font-size:12px;line-height:1.5}
      .acdc-catalog-download{display:inline-flex;margin:8px 0 0;width:auto}
      .acdc-public-catalog-empty{width:min(860px,92vw);margin:40px auto;background:#fff;padding:24px;border-radius:14px;border:1px solid var(--acdc-border);box-shadow:var(--acdc-shadow-panel);text-align:center;color:var(--acdc-text)}
      .acdc-public-catalog-search-empty{margin-top:24px}
      @media (max-width: 1180px){.acdc-catalog-grid{grid-template-columns:1fr 1fr}.acdc-catalog-detail-grid{grid-template-columns:1fr}.acdc-catalog-signup-card{position:static}}
      @media (max-width: 720px){.acdc-catalog-public-wrap,.acdc-catalog-detail-wrap{padding:24px 0 56px}.acdc-catalog-public-inner h1{font-size:36px}.acdc-catalog-grid,.acdc-catalog-form-grid{grid-template-columns:1fr}.acdc-catalog-signup-head h2,.acdc-catalog-main-card h1{font-size:28px}.acdc-catalog-info-card h3{font-size:22px}}
    </style>
    <script>
      (function(){
        var input  = document.getElementById('acdc-catalog-search-input');
        var grid   = document.getElementById('acdc-catalog-grid');
        var empty  = document.getElementById('acdc-catalog-empty');
        var chips  = document.getElementById('acdc-catalog-chips');
        var reset  = document.getElementById('acdc-catalog-chip-reset');
        if(!grid){ return; }
        var cards = Array.prototype.slice.call(grid.querySelectorAll('.acdc-catalog-card'));
        var activeModality = '';

        function refresh(){
          var query = input ? (input.value || '').toLowerCase().trim() : '';
          var visible = 0;
          cards.forEach(function(card){
            var haystack = (card.getAttribute('data-search') || '').toLowerCase();
            var modality = (card.getAttribute('data-modality') || '').toLowerCase();
            var matchesText = !query || haystack.indexOf(query) !== -1;
            var matchesModality = !activeModality || modality === activeModality.toLowerCase();
            var show = matchesText && matchesModality;
            card.hidden = !show;
            if(show){ visible += 1; }
          });
          if(empty){ empty.hidden = visible !== 0; }
          if(reset){ reset.hidden = !activeModality; }
        }

        if(input){ input.addEventListener('input', refresh); }

        if(chips){
          chips.addEventListener('click', function(e){
            var btn = e.target.closest('.acdc-catalog-chip');
            if(!btn){ return; }
            var modality = btn.getAttribute('data-modality') || '';
            // Toggle : si on reclique sur le chip déjà actif, on désactive le filtre.
            if(activeModality === modality){
              activeModality = '';
            } else {
              activeModality = modality;
            }
            // Mise à jour visuelle des chips.
            Array.prototype.forEach.call(chips.querySelectorAll('.acdc-catalog-chip'), function(c){
              if(c.getAttribute('data-modality') === activeModality && activeModality !== ''){
                c.classList.add('is-active');
              } else {
                c.classList.remove('is-active');
              }
            });
            refresh();
          });
        }

        if(reset){
          reset.addEventListener('click', function(){
            activeModality = '';
            Array.prototype.forEach.call(chips.querySelectorAll('.acdc-catalog-chip'), function(c){
              c.classList.remove('is-active');
            });
            refresh();
          });
        }
      })();
    </script>
    <?php
  }

private function get_front_settings_sections() {
  return array(
    'general'        => 'Réglages',
    'variables'      => 'Variables',
    'integrations'   => 'Intégrations IA',
    'propositions'   => 'Propositions commerciales',
    /* ACDC 3.25.156 — Inventaire des contrats formateurs orphelins, déplacé depuis
       wp-admin vers l'extranet pour rester dans l'outil de travail du gestionnaire. */
    'orphan_docs'    => 'Documents orphelins',
  );
}

private function get_current_front_settings_section() {
  $section = isset( $_GET['settings_section'] ) ? sanitize_key( wp_unslash( $_GET['settings_section'] ) ) : 'general';
  $sections = $this->get_front_settings_sections();
  return isset( $sections[ $section ] ) ? $section : 'general';
}

private function render_front_settings_section_nav( $current_section ) {
  $sections = $this->get_front_settings_sections();
  echo '<div class="acdc-subtabs" style="margin:0 0 18px;display:flex;gap:10px;flex-wrap:wrap;">';
  foreach ( $sections as $section_key => $section_label ) {
    $url = esc_url( $this->portal_page_url( array( 'tab' => 'settings', 'settings_section' => $section_key ) ) );
    $class = 'acdc-button' . ( $current_section === $section_key ? ' acdc-button-primary' : ' acdc-button-soft' );
    echo '<a class="' . esc_attr( $class ) . '" href="' . $url . '">' . esc_html( $section_label ) . '</a>';
  }
  echo '</div>';
}

private function render_front_settings_variables_section() {
  if ( ! function_exists( 'acdc_of_get_variables_registry' ) ) {
    echo '<div class="acdc-panel"><h3>Variables</h3><p>Le registre des variables n\'est pas disponible.</p></div>';
    return;
  }
  $registry = acdc_of_get_variables_registry();
  $groups_count = is_array( $registry ) ? count( $registry ) : 0;
  $variables_count = 0;
  if ( is_array( $registry ) ) {
    foreach ( $registry as $group ) {
      if ( ! empty( $group['variables'] ) && is_array( $group['variables'] ) ) {
        $variables_count += count( $group['variables'] );
      }
    }
  }
  echo '<div class="acdc-grid-2cols">';
  echo '<div class="acdc-panel"><h3>Référentiel des variables</h3><p>Ce registre est alimenté automatiquement par le moteur central du plugin. Toute nouvelle variable ajoutée dans les prochaines versions apparaîtra ici sans recréer l’écran.</p><p><strong>Objets métiers :</strong> ' . esc_html( (string) $groups_count ) . '</p><p><strong>Variables disponibles :</strong> ' . esc_html( (string) $variables_count ) . '</p><p><strong>Syntaxe :</strong> <code>{{objet_champ}}</code></p></div>';
  echo '<div class="acdc-panel"><h3>Règles d’usage</h3><ul class="acdc-simple-list"><li>Une variable = une donnée métier stable</li><li>Cette liste est automatiquement enrichie au fur et à mesure des évolutions du plugin</li><li>Les variables calculées sont affichées avec le même niveau de visibilité que les champs simples</li><li>Une variable inconnue reste visible tant qu’aucune valeur n’est disponible</li></ul></div>';
  echo '</div>';
  foreach ( $registry as $group_key => $group ) {
    $group_label = ! empty( $group['label'] ) ? $group['label'] : ucfirst( (string) $group_key );
    $variables = ! empty( $group['variables'] ) && is_array( $group['variables'] ) ? $group['variables'] : array();
    echo '<div class="acdc-panel" style="margin-top:18px;">';
    echo '<h3>' . esc_html( $group_label ) . '</h3>';
    echo '<div class="acdc-table-wrap"><table class="acdc-table"><thead><tr><th>Variable</th><th>Libellé</th><th>Clé technique</th><th>Objet</th><th>Type</th></tr></thead><tbody>';
    if ( empty( $variables ) ) {
      echo '<tr><td colspan="5">Aucune variable enregistrée.</td></tr>';
    } else {
      foreach ( $variables as $variable_key => $definition ) {
        $label = ! empty( $definition['label'] ) ? (string) $definition['label'] : $variable_key;
        $field = ! empty( $definition['field'] ) ? (string) $definition['field'] : 'calculé';
        $object = ! empty( $definition['object'] ) ? (string) $definition['object'] : (string) $group_key;
        $type = ! empty( $definition['callback'] ) ? 'Calculée' : 'Champ';
        echo '<tr>';
        echo '<td><code>{{' . esc_html( $variable_key ) . '}}</code></td>';
        echo '<td>' . esc_html( $label ) . '</td>';
        echo '<td><code>' . esc_html( $field ) . '</code></td>';
        echo '<td>' . esc_html( $object ) . '</td>';
        echo '<td>' . esc_html( $type ) . '</td>';
        echo '</tr>';
      }
    }
    echo '</tbody></table></div>';
    echo '</div>';
  }
}

private function render_front_settings_general_section() {
  ?>
  <?php $keep_data = get_option( 'acdc_of_keep_data_on_uninstall', 'yes' ); $db_version = get_option( 'acdc_of_db_version', '3.0.0' ); $last_backup_at = get_option( 'acdc_of_last_backup_at', '' ); $last_backup_file = get_option( 'acdc_of_last_backup_file', '' ); $last_manual_backup_at = get_option( 'acdc_of_last_manual_backup_at', '' ); $last_manual_backup_file = get_option( 'acdc_of_last_manual_backup_file', '' ); $last_safety_backup_at = get_option( 'acdc_of_last_safety_backup_at', '' ); $last_safety_backup_file = get_option( 'acdc_of_last_safety_backup_file', '' ); $backup_retention = absint( get_option( 'acdc_of_backup_retention_count', 30 ) ); $purge_prod = get_option( 'acdc_of_purge_allowed_in_production', 'no' ); $is_prod = $this->is_production_environment(); $manual_manifest_path = $last_manual_backup_file ? $this->get_backup_absolute_path( $last_manual_backup_file ) : ''; $manual_manifest = $manual_manifest_path ? json_decode( (string) file_get_contents( $manual_manifest_path ), true ) : array(); $manual_archive_relative = ! empty( $manual_manifest['archive'] ) ? dirname( $last_manual_backup_file ) . '/' . $manual_manifest['archive'] : ''; $manual_download_url = $manual_archive_relative ? wp_nonce_url( admin_url( 'admin-post.php?action=acdc_download_backup&file=' . rawurlencode( $manual_archive_relative ) ), 'acdc_download_backup' ) : ''; ?>
  <div class="acdc-grid-2cols">
    <div class="acdc-panel">
      <h3>Pages extranet</h3>
      <ul class="acdc-simple-list">
        <li><strong>Connexion :</strong> <a href="<?php echo esc_url( $this->login_page_url() ); ?>" target="_blank" rel="noopener noreferrer">Ouvrir</a></li>
        <li><strong>Tableau de bord :</strong> <a href="<?php echo esc_url( $this->portal_page_url() ); ?>" target="_blank" rel="noopener noreferrer">Ouvrir</a></li>
      </ul>
    </div>
    <div class="acdc-panel">
      <h3>Configuration</h3>
      <p>Personnalisez le logo, les couleurs, la typographie et les informations de votre structure.</p>
      <p><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=acdc-of-configuration' ) ); ?>">Ouvrir la configuration</a></p>
    </div>
  </div>
  <div class="acdc-panel" style="margin-top:18px;">
    <h3>Sauvegarde et restauration</h3>
    <p>Créez une sauvegarde manuelle complète des tables métier et des réglages du plugin, puis téléchargez-la ou restaurez-la depuis un fichier ZIP.</p>
    <div class="acdc-grid-2cols" style="align-items:start;">
      <div>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <?php wp_nonce_field( 'acdc_create_manual_backup' ); ?>
          <input type="hidden" name="action" value="acdc_create_manual_backup">
          <button type="submit" class="acdc-button acdc-button-primary">Créer une sauvegarde</button>
        </form>
        <p style="margin-top:12px;"><strong>Dernière sauvegarde manuelle :</strong> <?php echo esc_html( $last_manual_backup_at ? $last_manual_backup_at : 'Aucune' ); ?></p>
        <?php if ( $last_manual_backup_file ) : ?><p><strong>Manifeste :</strong> <?php echo esc_html( $last_manual_backup_file ); ?></p><?php endif; ?>
        <?php if ( $manual_download_url ) : ?><p><a class="acdc-button" href="<?php echo esc_url( $manual_download_url ); ?>">Télécharger la dernière sauvegarde</a></p><?php endif; ?>
      </div>
      <div>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" onsubmit="return confirm('Confirmez la restauration de la sauvegarde importée. Les données actuelles du plugin seront remplacées.');">
          <?php wp_nonce_field( 'acdc_restore_backup_import' ); ?>
          <input type="hidden" name="action" value="acdc_restore_backup_import">
          <label><strong>Importer une sauvegarde ZIP</strong><br><input type="file" name="acdc_backup_import_file" accept=".zip" required></label>
          <p class="acdc-help" style="margin-top:8px;">La restauration crée d’abord une sauvegarde de sécurité avant de remplacer les tables métier du plugin.</p>
          <p style="margin-top:12px;"><button type="submit" class="acdc-button">Importer et restaurer</button></p>
        </form>
      </div>
    </div>
  </div>
  <div class="acdc-grid-2cols" style="margin-top:18px;">
    <div class="acdc-panel">
      <h3>Protection des données</h3>
      <p><strong>Désinstallation non destructive :</strong> <?php echo 'yes' === $keep_data ? 'activée' : 'désactivée'; ?></p>
      <p><strong>Version du schéma :</strong> <?php echo esc_html( $db_version ); ?></p>
      <p><strong>Dernière sauvegarde avant mise à jour :</strong> <?php echo esc_html( $last_backup_at ? $last_backup_at : 'Aucune' ); ?></p>
      <?php if ( $last_backup_file ) : ?><p><strong>Fichier manifeste :</strong> <?php echo esc_html( $last_backup_file ); ?></p><?php endif; ?>
      <p><strong>Dernière sauvegarde de sécurité :</strong> <?php echo esc_html( $last_safety_backup_at ? $last_safety_backup_at : 'Aucune' ); ?></p>
      <?php if ( $last_safety_backup_file ) : ?><p><strong>Fichier manifeste sécurité :</strong> <?php echo esc_html( $last_safety_backup_file ); ?></p><?php endif; ?>
      <p><strong>Rétention des sauvegardes :</strong> <?php echo esc_html( (string) $backup_retention ); ?> version(s)</p>
      <p><strong>Purge autorisée en production :</strong> <?php echo 'yes' === $purge_prod ? 'oui' : 'non'; ?></p>
      <p><strong>Environnement détecté :</strong> <?php echo $is_prod ? 'production' : 'hors production'; ?></p>
      <p>Le réglage complet se fait dans l’écran de configuration.</p>
    </div>
    <div class="acdc-panel">
      <h3>Recommandation</h3>
      <p>Conservez cette protection activée en production. La suppression des données ne doit jamais être implicite.</p><p>La purge est bloquée par défaut en production et nécessite une double confirmation.</p>
    </div>
  </div>
  <?php
  /* ACDC 3.25.209 — La remise à zéro sélective précède la suppression totale.
     L'ordre n'est pas neutre : pendant une recette on veut presque toujours
     vider un ensemble précis, et presque jamais tout. Placer l'outil fin avant
     l'outil brutal, c'est proposer le bon geste en premier. */
  $this->render_selective_purge_panel();
  ?>
  <div class="acdc-panel" style="margin-top:18px;border-color:#E06D6D;background:#fff7f7;">
    <h3 style="color:#8f1d1d;">Suppression totale des entrées du plugin</h3>
    <p>Ce bouton vide toutes les données métier du plugin : prospects, apprenants, entreprises, financeurs, formateurs, formations, séances, inscriptions, conventions, devis, factures, évaluations, enquêtes, campagnes, journaux, files d’attente et documents générés.</p>
    <p><strong>Conservé :</strong> la structure du plugin, les pages WordPress du plugin et les réglages généraux pour que l’outil reste utilisable juste après la purge.</p>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Confirmez la suppression totale des données du plugin. Cette action est irréversible.');">
      <?php wp_nonce_field( 'acdc_purge_plugin_data' ); ?>
      <input type="hidden" name="action" value="acdc_purge_plugin_data">
      <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>
      <div class="acdc-grid-2cols" style="align-items:end;">
        <div>
          <label for="acdc-purge-confirm"><strong>Saisissez <?php echo esc_html( $this->get_plugin_data_purge_confirmation_phrase() ); ?></strong></label>
          <input id="acdc-purge-confirm" type="text" name="acdc_purge_confirm" value="" placeholder="<?php echo esc_attr( $this->get_plugin_data_purge_confirmation_phrase() ); ?>" required>
          <p style="margin-top:10px;"><label><input type="checkbox" name="acdc_purge_acknowledge" value="yes" required> Je confirme avoir vérifié la sauvegarde de sécurité avant purge.</label></p>
        </div>
        <div class="acdc-actions-end">
          <?php if ( $is_prod && 'yes' !== $purge_prod ) : ?><p style="margin-bottom:10px;color:#8f1d1d;font-weight:600;">Purge bloquée en production.</p><?php endif; ?>
          <button type="submit" class="acdc-button" style="background:#fff;border:1px solid #E06D6D;color:#8f1d1d;" <?php disabled( $is_prod && 'yes' !== $purge_prod ); ?>>Supprimer toutes les entrées</button>
        </div>
      </div>
    </form>
  </div>
  <?php
}

private function render_front_settings_tab() {
  $current_section = $this->get_current_front_settings_section();
  ?>
  <section class="acdc-section-head">
    <div>
      <h2>Paramètres</h2>
      <p>Réglages généraux et accès rapides.</p>
    </div>
  </section>
  <?php
  $this->render_front_settings_section_nav( $current_section );
  if ( 'variables' === $current_section ) {
    $this->render_front_settings_variables_section();
    return;
  }
  if ( 'integrations' === $current_section ) {
    $this->render_front_settings_integrations_section();
    return;
  }
  if ( 'propositions' === $current_section ) {
    $this->render_front_settings_propositions_section();
    return;
  }
  if ( 'orphan_docs' === $current_section ) {
    $this->render_front_settings_orphan_docs_section();
    return;
  }
  $this->render_front_settings_general_section();
}

/**
 * ACDC 3.25.156 — Contrats formateurs orphelins (extranet).
 *
 * Fichiers PDF encore sur le disque alors que leur mission n'existe plus en base.
 * Aucune suppression automatique : un contrat signé est une pièce comptable
 * (conservation 10 ans, art. L123-22 du Code de commerce). On inventorie, et
 * l'organisme décide fichier par fichier.
 */
private function render_front_settings_orphan_docs_section() {
  if ( ! current_user_can( 'manage_options' ) ) {
    echo '<div class="acdc-panel"><h3>Documents orphelins</h3><p>Accès réservé aux administrateurs.</p></div>';
    return;
  }
  $files  = method_exists( $this, 'acdc_scan_orphan_contract_files' ) ? $this->acdc_scan_orphan_contract_files() : array();
  $signed = array_filter( $files, function ( $f ) { return ! empty( $f['signed'] ); } );

  echo '<div class="acdc-panel acdc-profile-section"><h3>Contrats formateurs orphelins</h3>';
  echo '<p>Fichiers PDF présents sur le disque alors que la mission correspondante n\'existe plus. Ils ne sont <strong>pas accessibles publiquement</strong> (dossier protégé), mais ils constituent des données personnelles conservées.</p>';
  echo '<p><strong>Aucune suppression automatique n\'est effectuée.</strong> Un contrat signé est une pièce comptable à conserver 10 ans (art. L123-22 du Code de commerce) : archivez-le hors du serveur avant toute suppression.</p>';

  if ( empty( $files ) ) {
    echo '<p style="color:#1a7f37;font-weight:600;">Aucun fichier orphelin. Rien à faire.</p></div>';
    return;
  }

  printf(
    '<p>%d fichier(s) orphelin(s), dont <strong>%d signé(s)</strong>.</p>',
    count( $files ),
    count( $signed )
  );
  echo '<table class="acdc-table" style="width:100%;"><thead><tr><th>Fichier</th><th>Type</th><th>Taille</th><th>Date</th><th></th></tr></thead><tbody>';
  foreach ( $files as $f ) {
    $del = wp_nonce_url(
      add_query_arg(
        array(
          'action' => 'acdc_delete_orphan_contract_file',
          'file'   => rawurlencode( $f['name'] ),
          'ctx'    => 'front',
        ),
        admin_url( 'admin-post.php' )
      ),
      'acdc_delete_orphan_contract_' . $f['name']
    );
    $confirm = ! empty( $f['signed'] )
      ? "Ce contrat est SIGNÉ : c'est une pièce comptable à conserver 10 ans. L'avez-vous archivé hors du serveur ? Cette suppression est définitive."
      : 'Supprimer définitivement ce contrat non signé ?';
    printf(
      '<tr><td><code style="font-size:12px;">%s</code></td><td>%s</td><td>%s</td><td>%s</td><td><a href="%s" class="acdc-button acdc-button-soft" onclick="return confirm(%s);">Supprimer</a></td></tr>',
      esc_html( $f['name'] ),
      ! empty( $f['signed'] ) ? '<strong style="color:#b32d2e;">Signé — à conserver</strong>' : 'Non signé',
      esc_html( size_format( $f['size'] ) ),
      esc_html( date_i18n( 'd/m/Y H:i', $f['mtime'] ) ),
      esc_url( $del ),
      esc_attr( wp_json_encode( $confirm ) )
    );
  }
  echo '</tbody></table></div>';
}

  private function render_front_settings_propositions_section() {
    $saved     = isset( $_GET['saved'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['saved'] ) );
    $about     = get_option( 'acdc_of_proposal_about_acdc',         '' );
    $app_bef   = get_option( 'acdc_of_proposal_approach_before',    "Entretien préparatoire avec la personne en charge du projet formation pour affiner les objectifs et recueillir des cas métiers.
Test de positionnement en ligne et recueil des attentes participants." );
    $app_dur   = get_option( 'acdc_of_proposal_approach_during',    "Formateurs expérimentés et certifiés.
Supports de cours et fiche mémo.
Livret de l'apprenant.
20% de théorie - 80% de mise en pratique.
Évaluation de la satisfaction.
Évaluation des acquis." );
    $app_aft   = get_option( 'acdc_of_proposal_approach_after',     "Bibliothèque de prompts
Évaluation à froid
Évaluation de la satisfaction.
Évaluation des acquis." );
    $acc_res   = get_option( 'acdc_of_proposal_access_resources',   "Une salle équipée d'une connexion Wifi à Haut débit
Un poste de travail connecté par apprenant
Option : paper board" );
    $acc_cond  = get_option( 'acdc_of_proposal_access_conditions',  "Si la date de formation n'est pas encore arrêtée ou si elle évolue, des entrées restent possibles tout au long de l'année, sous réserve de disponibilités.
Délai indicatif entre la demande et l'entrée en formation : 30 jours.
Inscription : via le formulaire de contact du site ou par téléphone." );
    $cgv       = get_option( 'acdc_of_proposal_cgv',                '' );
    $save_url  = admin_url( 'admin-post.php' );
    $ta_style  = 'width:100%;border-radius:10px;border:1px solid #dfe5ee;padding:10px 12px;font-size:13px;line-height:1.55;resize:vertical;';
    ?>
    <form method="post" action="<?php echo esc_url( $save_url ); ?>">
      <?php wp_nonce_field( 'acdc_save_proposal_global_settings' ); ?>
      <input type="hidden" name="action" value="acdc_save_proposal_global_settings">

      <?php if ( $saved ) : ?>
        <div style="background:#d4edda;border:1px solid #c3e6cb;border-radius:8px;padding:10px 14px;font-size:13px;color:#155724;margin-bottom:14px;">&#10003; R&eacute;glages enregistr&eacute;s.</div>
      <?php endif; ?>

      <div class="acdc-panel acdc-mb-18">
        <div class="acdc-panel-heading" style="padding:14px 20px;border-bottom:1px solid #f0e6dc;">
          <h3 style="margin:0;font-size:13px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.06em;">Page 4 &mdash; &laquo;&nbsp;Nous&nbsp;&raquo; (ACDC Formation)</h3>
        </div>
        <div style="padding:20px;">
          <p style="font-size:12px;color:#4b5d76;margin-bottom:10px;">Description d&apos;ACDC Formation affich&eacute;e page 4. Laissez vide pour utiliser le texte par d&eacute;faut.</p>
          <textarea name="acdc_of_proposal_about_acdc" rows="8" style="<?php echo $ta_style; ?>"><?php echo esc_textarea( $about ); ?></textarea>
        </div>
      </div>

      <div class="acdc-panel acdc-mb-18">
        <div class="acdc-panel-heading" style="padding:14px 20px;border-bottom:1px solid #f0e6dc;">
          <h3 style="margin:0;font-size:13px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.06em;">Page 6 &amp; 12 &mdash; Approche Avant / Pendant / Apr&egrave;s</h3>
        </div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:16px;">
          <p style="font-size:12px;color:#4b5d76;">Un &eacute;l&eacute;ment par ligne. Affich&eacute; en liste &agrave; puces dans le document.</p>
          <div>
            <label style="font-size:12px;font-weight:600;color:#0f2c52;display:block;margin-bottom:5px;">&#9658; Avant la formation</label>
            <textarea name="acdc_of_proposal_approach_before" rows="4" style="<?php echo $ta_style; ?>"><?php echo esc_textarea( $app_bef ); ?></textarea>
          </div>
          <div>
            <label style="font-size:12px;font-weight:600;color:#0f2c52;display:block;margin-bottom:5px;">&#9658; Pendant la formation</label>
            <textarea name="acdc_of_proposal_approach_during" rows="5" style="<?php echo $ta_style; ?>"><?php echo esc_textarea( $app_dur ); ?></textarea>
          </div>
          <div>
            <label style="font-size:12px;font-weight:600;color:#0f2c52;display:block;margin-bottom:5px;">&#9658; Apr&egrave;s la formation (Avant &amp; Apr&egrave;s)</label>
            <textarea name="acdc_of_proposal_approach_after" rows="4" style="<?php echo $ta_style; ?>"><?php echo esc_textarea( $app_aft ); ?></textarea>
          </div>
        </div>
      </div>

      <div class="acdc-panel acdc-mb-18">
        <div class="acdc-panel-heading" style="padding:14px 20px;border-bottom:1px solid #f0e6dc;">
          <h3 style="margin:0;font-size:13px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.06em;">Page 14 &mdash; Modalit&eacute;s et d&eacute;lais d&apos;acc&egrave;s</h3>
        </div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:16px;">
          <div>
            <label style="font-size:12px;font-weight:600;color:#0f2c52;display:block;margin-bottom:5px;">Ressources &agrave; pr&eacute;voir (un &eacute;l&eacute;ment par ligne)</label>
            <textarea name="acdc_of_proposal_access_resources" rows="4" style="<?php echo $ta_style; ?>"><?php echo esc_textarea( $acc_res ); ?></textarea>
          </div>
          <div>
            <label style="font-size:12px;font-weight:600;color:#0f2c52;display:block;margin-bottom:5px;">Modalit&eacute;s et d&eacute;lais d&apos;acc&egrave;s (un &eacute;l&eacute;ment par ligne)</label>
            <textarea name="acdc_of_proposal_access_conditions" rows="5" style="<?php echo $ta_style; ?>"><?php echo esc_textarea( $acc_cond ); ?></textarea>
          </div>
        </div>
      </div>

      <div class="acdc-panel acdc-mb-18">
        <div class="acdc-panel-heading" style="padding:14px 20px;border-bottom:1px solid #f0e6dc;">
          <h3 style="margin:0;font-size:13px;font-weight:700;color:#8a6d2a;text-transform:uppercase;letter-spacing:.06em;">Pages 14-15 &mdash; Conditions G&eacute;n&eacute;rales de Vente</h3>
        </div>
        <div style="padding:20px;">
          <p style="font-size:12px;color:#4b5d76;margin-bottom:10px;">HTML accept&eacute;. Laissez vide pour utiliser les CGV int&eacute;gr&eacute;es par d&eacute;faut.</p>
          <textarea name="acdc_of_proposal_cgv" rows="10" style="<?php echo $ta_style; ?>"><?php echo esc_textarea( $cgv ); ?></textarea>
        </div>
      </div>

      <p><button type="submit" class="acdc-button acdc-button-primary">Enregistrer les r&eacute;glages propositions</button></p>
    </form>
    <?php
  }

  private function render_front_settings_integrations_section() {
    /* ACDC 3.25.257 — La clé n'est plus lue ici, seulement son existence :
       l'imprimer dans l'attribut value revenait à la publier dans le code
       source de la page, le type="password" ne masquant qu'à l'œil. */
    $has_key = $this->has_openai_api_key();
    $saved   = isset( $_GET['saved'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['saved'] ) );
    ?>
    <div class="acdc-panel">
      <h3 style="font-size:15px;font-weight:600;color:#0f2c52;margin-bottom:6px;">Int&#233;gration OpenAI</h3>
      <p style="font-size:13px;color:#4b5d76;margin-bottom:16px;">Cl&#233; API OpenAI utilis&#233;e pour la g&#233;n&#233;ration automatique de la section &laquo; &#192; propos de vous &raquo; dans les propositions commerciales.</p>
      <?php if ( $saved ) : ?>
        <div style="background:#d4edda;border:1px solid #c3e6cb;border-radius:8px;padding:10px 14px;font-size:13px;color:#155724;margin-bottom:14px;">&#10003; Cl&#233; enregistr&#233;e avec succ&#232;s.</div>
      <?php endif; ?>
      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'acdc_save_openai_key' ); ?>
        <input type="hidden" name="action" value="acdc_save_openai_key">
        <input type="hidden" name="return_context" value="<?php echo is_admin() ? 'admin' : 'front'; ?>">
        <p>
          <label style="font-size:13px;font-weight:500;color:#0f2c52;display:block;margin-bottom:5px;">Cl&#233; API OpenAI (sk-proj-…)</label>
          <input type="password"
                 name="openai_api_key"
                 value=""
                 autocomplete="new-password"
                 placeholder="<?php echo $has_key ? '•••••••• (cl&#233; enregistr&#233;e)' : 'sk-proj-…'; ?>"
                 style="width:100%;max-width:520px;height:40px;border-radius:10px;border:1px solid #dfe5ee;padding:0 12px;font-size:13px;">
          <span style="display:block;font-size:11px;color:#4b5d76;margin-top:5px;">
            Cr&#233;ez votre cl&#233; sur <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com/api-keys</a>.
            Elle est stock&#233;e chiffr&#233;e dans votre base de donn&#233;es et n'est jamais r&#233;affich&#233;e.
            <?php if ( $has_key ) : ?>Laissez le champ vide pour conserver la cl&#233; enregistr&#233;e.<?php endif; ?>
          </span>
        </p>
        <?php if ( $has_key ) : ?>
        <p style="font-size:12px;color:#4b5d76;">
          <label><input type="checkbox" name="openai_api_key_clear" value="1"> Effacer la cl&#233; enregistr&#233;e</label>
        </p>
        <?php endif; ?>
        <p><button type="submit" class="acdc-button acdc-button-primary">Enregistrer la cl&#233;</button></p>
      </form>
    </div>
    <?php
  }


}