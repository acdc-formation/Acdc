<?php
/**
 * ACDC Réglages OF / Profil organisme / Catalogue — noyau et référentiels
 *
 * Extraction incrémentale du module réglages OF, profil organisme
 * et catalogue.
 * Version : 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Settings_Catalog_Core_Trait {

  private function get_company_default_public_website() {
    return 'https://acdc-formation.com/';
  }

  private function get_company_profile_defaults() {
    $branding = $this->get_branding_options();
    $user = wp_get_current_user();
    return array(
      'offer' => '',
      'siret_identification' => $branding['siret'],
      'enterprise' => $branding['company_name'],
      'client_creation_date' => '',
      'signatory_role' => '',
      'first_name' => '',
      'last_name' => '',
      'vat_number' => '',
      /* ACDC 3.25.290 — Ce champ portait le numéro de déclaration d'activité
         en dur comme valeur par défaut : un champ vidé se remplissait tout
         seul à la lecture suivante et l'ancien NDA revenait sur les documents.
         La migration backfill_org_identity_from_code() a écrit la valeur dans
         la fiche ; un champ vide reste désormais vide. */
      'activity_declaration_number' => '',
      'inrs_number' => '',
      'draaf_number' => '',
      'apr_number' => '',
      'uai_number' => '',
      'naf_code' => '',
      'legal_form' => '',
      'accounting_start' => '01/01',
      'accounting_end' => '31/12',
      'employees_count' => '',
      'address' => $branding['address'],
      'postal_code' => $branding['postal_code'],
      'city' => $branding['city'],
      'country' => $branding['country'],
      'enterprise_contact_email' => $branding['email'],
      'enterprise_contact_phone' => $branding['phone'],
      'referent' => $user ? $user->display_name : '',
      'access_enabled' => 'Oui',
      'acceptance_date' => '',
      'access_start_date' => '',
      'sepa_creation_date' => '',
      'sepa_validation_date' => '',
      'sepa_setup_deadline' => '',
      'sepa_invalidation_date' => '',
      'trial_end_date' => '',
      'rdv1_date' => '',
      'next_billing_date' => '',
      'last_billing_date' => '',
      'termination_date' => '',
      'access_end_date' => '',
      'logo_url' => $branding['logo_url'],
      'stamp_url' => '',
      'signature_url' => '',
      'stamp_only_url' => '',
      'website_url' => $this->get_company_default_public_website(),
      'email_subject' => $branding['company_name'],
      'timezone_label' => ( wp_timezone_string() ? wp_timezone_string() : 'Europe/Paris' ),
      'vat_rate' => '20.00%',
      'training_rules_url' => home_url( '/reglement-interieur/' ),
      'welcome_booklet_url' => '',
      'cgv_url' => home_url( '/conditions-generales-de-vente-cgv/' ),
      'cgu_url' => home_url( '/conditions-generales-dutilisation-cgu/' ),
      'mentions_legales_url' => home_url( '/mentions-legales/' ),
      'privacy_policy_url' => home_url( '/politique-de-confidentialite/' ),
      'other_document_url' => '',
      'sponsorship_code' => '',
      'header_footer_bg' => '#F3E3BF',
      'header_footer_text' => '#1E4777',
      'body_text_color' => '#000000',
      'footer_logo_url' => '',
      'contract_params_label' => 'Accéder aux paramètres',
      'convocation_params_label' => 'Accéder aux paramètres',
      'attestation_params_label' => 'Accéder aux paramètres',
      'subcontract_params_label' => 'Accéder aux paramètres',
      'cold_survey_delay' => 90,
      'company_survey_delay' => 30,
      'trainer_survey_delay' => 1,
      'learner_extranet_start' => 'Jour J (premier jour de formation)',
      'learner_extranet_end' => '1 mois',
      'auto_attendance_send' => 'Non',
      'show_france_travail_id' => 'Non',
      'attendance_cutoff_hour' => '13',
      'bank_name'         => '',
      'bank_domiciliation' => '',
      'bank_iban'         => '',
      'bank_bic'          => '',
      'vat_rate_default'  => '0',
    );
  }


  private function get_company_profile_options() {
    $defaults = $this->get_company_profile_defaults();
    $saved = get_option( 'acdc_of_company_profile', array() );
    if ( ! is_array( $saved ) ) {
      $saved = array();
    }
    $profile = wp_parse_args( $saved, $defaults );
    $timezone = wp_timezone_string() ? wp_timezone_string() : 'Europe/Paris';
    if ( empty( $profile['timezone_label'] ) || false !== stripos( (string) $profile['timezone_label'], 'Corse' ) ) {
      $profile['timezone_label'] = $timezone;
    }
    if ( empty( $profile['website_url'] ) ) {
      $profile['website_url'] = $this->get_company_default_public_website();
    } else {
      $website_url = trim( (string) $profile['website_url'] );
      if ( ! preg_match( '#^https?://#i', $website_url ) ) {
        $website_url = 'https://' . ltrim( $website_url, '/' );
      }
      $profile['website_url'] = esc_url_raw( $website_url );
    }
    return $profile;
  }

  private function get_company_static_document_definitions() {
    return array(
      'reglement-interieur' => array( 'title' => 'Règlement intérieur de la formation', 'slug' => 'reglement-interieur', 'page_option' => 'acdc_of_company_doc_reglement_interieur_page_id' ),
      'conditions-generales-de-vente-cgv' => array( 'title' => 'Conditions Générales de Vente (CGV)', 'slug' => 'conditions-generales-de-vente-cgv', 'page_option' => 'acdc_of_company_doc_cgv_page_id' ),
      'conditions-generales-dutilisation-cgu' => array( 'title' => 'Conditions Générales d’Utilisation (CGU)', 'slug' => 'conditions-generales-dutilisation-cgu', 'page_option' => 'acdc_of_company_doc_cgu_page_id' ),
      'mentions-legales' => array( 'title' => 'Mentions légales', 'slug' => 'mentions-legales', 'page_option' => 'acdc_of_company_doc_mentions_legales_page_id' ),
      'politique-de-confidentialite' => array( 'title' => 'Politique de confidentialité', 'slug' => 'politique-de-confidentialite', 'page_option' => 'acdc_of_company_doc_politique_confidentialite_page_id' ),
    );
  }

  private function get_company_static_document_asset_url( $doc, $type = 'html' ) {
    $doc = sanitize_title( (string) $doc );
    $type = 'pdf' === $type ? 'pdf' : 'html';
    return trailingslashit( ACDC_OF_SAAS_URL ) . 'assets/company-docs/' . $type . '/' . rawurlencode( $doc ) . '.' . $type;
  }

  private function get_company_model_pdf_asset_url( $doc ) {
    $doc = sanitize_title( (string) $doc );
    return trailingslashit( ACDC_OF_SAAS_URL ) . 'assets/company-docs/pdf/' . rawurlencode( $doc ) . '.pdf';
  }

  public function ensure_company_static_document_pages() {
    foreach ( $this->get_company_static_document_definitions() as $doc => $config ) {
      $page_id = (int) get_option( $config['page_option'], 0 );
      $shortcode = '[acdc_of_company_static_doc doc="' . esc_attr( $doc ) . '"]';
      if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
        $existing = get_page_by_path( $config['slug'] );
        if ( $existing instanceof WP_Post ) {
          $page_id = (int) $existing->ID;
          wp_update_post( array( 'ID' => $page_id, 'post_title' => $config['title'], 'post_content' => $shortcode, 'post_status' => 'publish' ) );
        } else {
          $page_id = wp_insert_post( array( 'post_title' => $config['title'], 'post_name' => $config['slug'], 'post_content' => $shortcode, 'post_status' => 'publish', 'post_type' => 'page' ) );
        }
        update_option( $config['page_option'], (int) $page_id, false );
      }
    }
  }

  public function render_company_static_document_shortcode( $atts = array() ) {
    $atts = shortcode_atts( array( 'doc' => '' ), $atts, 'acdc_of_company_static_doc' );
    $doc = sanitize_title( (string) $atts['doc'] );
    $definitions = $this->get_company_static_document_definitions();
    if ( empty( $definitions[ $doc ] ) ) {
      return '';
    }
    $html_url = $this->get_company_static_document_asset_url( $doc, 'html' );
    $pdf_url  = $this->get_company_static_document_asset_url( $doc, 'pdf' );
    $title    = $definitions[ $doc ]['title'];
    ob_start();
    ?>
    <div class="acdc-company-static-doc-page">
      <div class="acdc-company-static-doc-actions">
        <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $html_url ); ?>" target="_blank" rel="noopener noreferrer">Ouvrir le document</a>
        <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener noreferrer">Télécharger le PDF</a>
      </div>
      <iframe src="<?php echo esc_url( $html_url ); ?>" title="<?php echo esc_attr( $title ); ?>" loading="lazy"></iframe>
    </div>
    <?php
    return (string) ob_get_clean();
  }

  private function get_billing_settings_options() {
    $defaults = array(
      'billing' => array(
        'company_name' => 'ACDC FORMATION',
        'guide_button_label' => "Afficher le guide d'utilisation",
        'issue_address' => "68 Via Nova - Pôle d'excellence Jean Louis - Immeuble le Triangle\n83600, Fréjus, FR",
        'vat_0_mention' => 'Par défaut : Exonération de TVA, article 261-4-4° du CGI',
        'signature_stamp' => 0,
      ),
      'quotes' => array(
        'prefix' => 'DE-(annee)',
        'year_tag' => 'Année',
        'month_tag' => 'Mois',
        'day_tag' => 'Jour',
        'next_number' => '2',
        'restart_each_year' => 1,
        'validity_days' => 30,
        'payment_terms' => "Règlement par virement bancaire, à l'édition de la facture.\nEn cas de retard de paiement, des pénalités seront exigibles au taux de 3 fois le taux d'intérêt légal.\nUne indemnité forfaitaire pour frais de recouvrement de 40 € sera due automatiquement (article L441-10 du Code de commerce).\nIBAN [IBAN] - BIC [BIC]",
        'special_mention' => "Ce devis est valable 30 jours à compter de sa date d'émission.\nLes prix indiqués sont exprimés en euros, hors taxes et toutes taxes comprises (HT et TTC).\nTVA au taux en vigueur.\nOrganisme de formation [RAISON SOCIALE], certifié Qualiopi au titre de ses actions de formation.\nDéclaration d'activité enregistrée sous le numéro [VOTRE NUMÉRO DE DÉCLARATION D'ACTIVITÉ] auprès du préfet de région Provence-Alpes-Côte d'Azur.\nNos conditions générales de vente s'appliquent.",
      ),
      'invoices' => array(
        'prefix' => 'FA-(annee)',
        'year_tag' => 'Année',
        'month_tag' => 'Mois',
        'day_tag' => 'Jour',
        'next_number' => '5',
        'restart_each_year' => 1,
        'payment_terms' => "Règlement par virement bancaire, à l'édition de la facture.\nEn cas de retard de paiement, des pénalités seront exigibles au taux de 3 fois le taux d'intérêt légal.\nUne indemnité forfaitaire pour frais de recouvrement de 40 € sera due automatiquement (article L441-10 du Code de commerce).\nIBAN [IBAN] - BIC [BIC]",
        'special_mention' => "Les prix indiqués sont exprimés en euros, hors taxes et toutes taxes comprises (HT et TTC).\nTVA au taux en vigueur.\nOrganisme de formation [RAISON SOCIALE] – Déclaration d'activité enregistrée sous le n° [VOTRE NUMÉRO DE DÉCLARATION D'ACTIVITÉ] auprès du préfet de région Provence-Alpes-Côte d'Azur.\nCertifié Qualiopi au titre des actions de formation.",
      ),
      'credit_notes' => array(
        'prefix' => 'AV-(annee)',
        'year_tag' => 'Année',
        'month_tag' => 'Mois',
        'day_tag' => 'Jour',
        'payment_terms' => "L'avoir sera imputé automatiquement sur la prochaine facture.\nEn cas d'impossibilité, le remboursement sera effectué par virement bancaire dans un délai de 30 jours à compter de son émission.",
        'special_mention' => "Montants exprimés HT et TTC avec application de la TVA au taux en vigueur.\nOrganisme de formation [RAISON SOCIALE] – Déclaration d'activité enregistrée sous le [VOTRE NUMÉRO DE DÉCLARATION D'ACTIVITÉ] auprès du préfet de région Provence-Alpes-Côte d'Azur.\nCertifié Qualiopi au titre des actions de formation.",
      ),
    );
    $stored   = get_option( 'acdc_of_billing_settings', array() );
    $settings = wp_parse_args( $stored, $defaults );

    // Substitution du gabarit NDA par le vrai numéro (source unique : profil organisme,
    // repli sur le NDA officiel). Corrige aussi les mentions déjà enregistrées avec le
    // placeholder « [VOTRE NUMÉRO DE DÉCLARATION D'ACTIVITÉ] ».
    /* ACDC 3.25.290 — Le repli écrivait le NDA en dur : la mention légale d'une
       facture aurait donc continué d'annoncer l'ancien numéro de déclaration
       d'activité après un changement d'entité. Sans NDA renseigné, la PHRASE
       ENTIÈRE qui le porte disparaît — une déclaration annoncée « sous le
       numéro » suivi de rien n'est pas une mention incomplète, c'est une
       mention fausse. */
    $identite  = $this->acdc_org_identity();
    /* ACDC 3.25.290 — Les conditions de règlement par défaut portaient l'IBAN et
       le BIC en toutes lettres. Un compte bancaire écrit dans le code, c'est un
       virement qui part sur l'ancien compte le jour où l'entité change ; sans
       coordonnées renseignées, la ligne disparaît. */
    $__fiche_banque = get_option( 'acdc_of_company_profile', array() );
    $marqueurs = array(
      "[VOTRE NUMÉRO DE DÉCLARATION D'ACTIVITÉ]" => $identite['nda'],
      '[VOTRE NUMÉRO DE DÉCLARATION D’ACTIVITÉ]' => $identite['nda'],
      '[RAISON SOCIALE]'                         => $identite['raison_sociale'],
      '[IBAN]'                                   => is_array( $__fiche_banque ) && ! empty( $__fiche_banque['bank_iban'] ) ? (string) $__fiche_banque['bank_iban'] : '',
      '[BIC]'                                    => is_array( $__fiche_banque ) && ! empty( $__fiche_banque['bank_bic'] ) ? (string) $__fiche_banque['bank_bic'] : '',
    );
    foreach ( array( 'quotes', 'invoices', 'credit_notes' ) as $scope_key ) {
      foreach ( array( 'special_mention', 'payment_terms' ) as $__champ ) {
        if ( isset( $settings[ $scope_key ][ $__champ ] ) && is_string( $settings[ $scope_key ][ $__champ ] ) ) {
          $settings[ $scope_key ][ $__champ ] = self::acdc_appliquer_marqueurs( $settings[ $scope_key ][ $__champ ], $marqueurs );
        }
      }
      if ( ! isset( $settings[ $scope_key ]['special_mention'] ) || ! is_string( $settings[ $scope_key ]['special_mention'] ) ) {
        continue;
      }
    }
    return $settings;
  }


  private function get_catalog_settings() {
    $defaults = array(
      'domain_type' => 'catalogue.teetche.com',
      'domain_name' => 'acdc-formation',
      'hook_text' => 'Découvrez notre sélection de formations professionnelles conçues pour développer vos compétences et faire évoluer votre carrière.',
      'cta_label' => "S'inscrire",
      'show_prices' => 1,
      'show_satisfaction' => 0,
      'notify_new_registration' => 0,
      'privacy_policy_url' => $this->get_default_catalog_privacy_policy_url(),
      'footer_show_address' => 1,
      'footer_show_email' => 1,
      'footer_show_phone' => 1,
      'footer_show_website' => 1,
      'footer_show_cgv' => 1,
      'profile_default' => 'Particulier',
      'required_gender' => 1,
      'required_first_name' => 1,
      'required_last_name' => 1,
      'required_email' => 1,
      'required_phone' => 1,
      'required_desired_training' => 1,
      'required_rgpd' => 1,
      'optional_address' => 1,
      'optional_postal_code' => 1,
      'optional_city' => 1,
      'optional_education_level' => 0,
      'optional_comment' => 1,
      'optional_future_sessions' => 0,
      'active' => 1,
    );
    $saved = get_option( 'acdc_of_catalog_settings', array() );
    return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
  }


  private function get_catalog_formations( $include_hidden = false ) {
    global $wpdb;
    $sql = "SELECT * FROM {$this->formation_table} WHERE is_active = 1";
    if ( ! $include_hidden ) {
      $sql .= ' AND COALESCE(catalog_public,1) = 1';
    }
    $sql .= ' ORDER BY CASE WHEN COALESCE(catalog_order,0)=0 THEN 999999 ELSE catalog_order END ASC, title ASC';
    return $wpdb->get_results( $sql );
  }


  /**
   * ACDC 3.20.79 — Résout le paramètre formation_id du catalogue public.
   * Accepte soit un ID auto (ex. "11"), soit un code variante (ex. "1.1").
   * Renvoie l'objet formation actif, ou null.
   */
  private function get_catalog_formation( $raw_id ) {
    global $wpdb;
    $raw = trim( (string) $raw_id );
    if ( '' === $raw ) {
      return null;
    }
    // Format attendu : entier simple OU code variante "X.Y" (chiffres avec un seul point).
    if ( ! preg_match( '/^[0-9]+(\.[0-9]+)?$/', $raw ) ) {
      return null;
    }
    if ( false !== strpos( $raw, '.' ) ) {
      // Code variante : recherche par champ "code" en BD.
      $formation = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$this->formation_table} WHERE code = %s", $raw )
      );
    } else {
      // ID auto : comportement historique.
      $formation = $this->get_formation( (int) $raw );
    }
    if ( ! $formation || (int) $formation->is_active !== 1 ) {
      return null;
    }
    return $formation;
  }


  /**
   * ACDC 3.20.79 — Retourne le paramètre public à utiliser dans l'URL catalogue
   * pour une formation : son code variante (ex. "1.1") si non-vide, sinon son ID auto.
   */
  private function get_catalog_public_param( $formation ) {
    if ( ! $formation ) {
      return '';
    }
    $code = isset( $formation->code ) ? trim( (string) $formation->code ) : '';
    if ( '' !== $code ) {
      return $code;
    }
    return (string) ( isset( $formation->id ) ? (int) $formation->id : 0 );
  }

  /**
   * ACDC 3.20.81 — Rendu centralisé des lignes "Lieu" et notes complémentaires
   * dans une carte ou fiche détail catalogue, selon la modalité de la formation.
   *
   * Règles :
   * - Présentiel : afficher l'adresse complète comme "Lieu :".
   * - Hybride    : afficher l'adresse + une note pour la partie distancielle.
   * - Distanciel : pas d'adresse, juste une note explicative.
   * - E-learning : pas d'adresse, juste une note d'accès plateforme.
   *
   * Renvoie le HTML brut des <li>, ou chaîne vide si rien à afficher.
   */
  private function render_catalog_location_lines( $formation ) {
    $modality = isset( $formation->modality ) ? (string) $formation->modality : '';
    $address  = trim(
      ( isset( $formation->address ) ? (string) $formation->address : '' ) . ' ' .
      ( isset( $formation->postal_code ) ? (string) $formation->postal_code : '' ) . ' ' .
      ( isset( $formation->city ) ? (string) $formation->city : '' )
    );
    $note_hybride    = "Le lien de connexion pour la partie à distance vous sera communiqué la veille de la session.";
    $note_distanciel = "Le lien de connexion vous sera communiqué la veille de la session.";
    $note_elearning  = "L’accès à la plateforme e-learning vous sera transmis dès votre inscription validée.";

    switch ( $modality ) {
      case 'Présentiel':
        return '<li><strong>Lieu :</strong> ' . esc_html( '' !== $address ? $address : 'À confirmer' ) . '</li>';
      case 'Hybride':
        $html  = '<li><strong>Lieu :</strong> ' . esc_html( '' !== $address ? $address : 'À confirmer' ) . '</li>';
        $html .= '<li class="acdc-catalog-location-note">' . esc_html( $note_hybride ) . '</li>';
        return $html;
      case 'Distanciel':
        return '<li class="acdc-catalog-location-note">' . esc_html( $note_distanciel ) . '</li>';
      case 'E-learning':
        return '<li class="acdc-catalog-location-note">' . esc_html( $note_elearning ) . '</li>';
      default:
        // Modalité non reconnue ou vide : fallback minimal sur l'adresse si présente.
        return '' !== $address
          ? '<li><strong>Lieu :</strong> ' . esc_html( $address ) . '</li>'
          : '';
    }
  }

  private function get_catalog_page_url( $args = array() ) {
    $page_id = (int) get_option( 'acdc_of_catalog_page_id', 0 );
    $url = $page_id ? get_permalink( $page_id ) : home_url( '/catalogue/' );
    return add_query_arg( $args, $url );
  }


  private function get_default_catalog_privacy_policy_url() {
    $profile = get_option( 'acdc_of_company_profile', array() );
    if ( ! empty( $profile['website_url'] ) ) {
      return trailingslashit( esc_url_raw( (string) $profile['website_url'] ) ) . 'politique-de-confidentialite/';
    }
    return home_url( '/politique-de-confidentialite/' );
  }


/**
 * Remplace les marqueurs d'un texte de réglage, et retire les lignes orphelines.
 *
 * ACDC 3.25.290. Une mention légale ou une condition de règlement qui annonce
 * « sous le numéro » ou « IBAN » suivi de rien n'est pas incomplète : elle est
 * fausse. La ligne entière disparaît donc avec sa valeur.
 *
 * @param string $texte
 * @param array<string,string> $marqueurs
 * @return string
 */
private static function acdc_appliquer_marqueurs( $texte, $marqueurs ) {
  $gardees = array();
  foreach ( explode( "\n", (string) $texte ) as $ligne ) {
    $abandon = false;
    foreach ( $marqueurs as $marqueur => $valeur ) {
      if ( false !== strpos( $ligne, $marqueur ) && '' === $valeur ) {
        $abandon = true;
        break;
      }
    }
    if ( ! $abandon ) {
      $gardees[] = str_replace( array_keys( $marqueurs ), array_values( $marqueurs ), $ligne );
    }
  }
  return implode( "\n", $gardees );
}

private function get_branding_defaults() {
  return array(
    'logo_url' => '',
    'favicon_url' => '',
    'display_name' => 'ACDC Formation SAAS',
    'sidebar_subtitle' => 'Organisme de formation',
    /* ACDC 3.25.290 — Ces huit valeurs par défaut étaient l'identité de
       l'organisme, écrite dans le code. Elles se réinjectaient à chaque lecture
       d'un champ vide, ce qui rendait impossible d'abandonner une ancienne
       adresse ou un ancien numéro. La migration backfill_org_identity_from_code()
       les a écrites une fois dans la fiche entreprise, où elles sont désormais
       modifiables — et effaçables. */
    'company_name' => '',
    'address' => '',
    'postal_code' => '',
    'city' => '',
    'country' => '',
    'phone' => '',
    'email' => '',
    'website' => '',
    /* ACDC 3.25.290 — Mêmes raisons : la fiche entreprise fait foi, la marque
       n'est qu'un secours, et un secours ne doit pas ressusciter une identité
       que l'on vient d'abandonner. */
    'siret' => '',
    'nda' => '',
    'font_family' => 'Rubik, Arial, system-ui, sans-serif',
    'font_weight' => '500',
    'line_height' => '1.5',
    'page_title_size' => '25',
    'page_title_weight' => '700',
    'h1_font_size' => '25',
    'h1_font_weight' => '700',
    'h2_font_size' => '20',
    'h2_font_weight' => '600',
    'h3_font_size' => '18',
    'h3_font_weight' => '600',
    'h4_font_size' => '16',
    'h4_font_weight' => '600',
    'section_title_size' => '20',
    'section_title_weight' => '600',
    'subtitle_size' => '18',
    'subtitle_weight' => '600',
    'body_font_size' => '14',
    'body_font_weight' => '400',
    'small_text_size' => '12',
    'small_text_weight' => '400',
    'label_font_size' => '13',
    'label_font_weight' => '500',
    'field_font_size' => '14',
    'field_font_weight' => '400',
    'button_font_size' => '13',
    'button_font_weight' => '500',
    'table_header_font_size' => '12',
    'table_header_font_weight' => '600',
    'table_row_font_size' => '13',
    'table_row_font_weight' => '400',
    'table_action_font_size' => '13',
    'badge_font_size' => '12',
    'badge_font_weight' => '600',
    'menu_font_weight' => '500',
    'tab_font_size' => '13',
    'tab_font_weight' => '600',
    'modal_title_size' => '18',
    'modal_title_weight' => '700',
    'modal_body_size' => '14',
    'help_text_size' => '12',
    'error_text_size' => '12',
    'kpi_label_size' => '12',
    'kpi_value_size' => '24',
    'kpi_value_weight' => '700',
    'sidebar_width' => '320',
    'menu_font_size' => '14',
    'radius' => '10',
    'button_height' => '40',
    'button_padding_x' => '14',
    'button_style_primary' => 'gradient',
    'button_style_secondary' => 'soft',
    'button_style_danger' => 'outline',
    'button_hover_mode' => 'brighten',
    'button_focus_ring' => '#C5A253',
    'button_disabled_opacity' => '0.55',
    'field_height' => '40',
    'textarea_height' => '96',
    'select_style' => 'standard',
    'checkbox_style' => 'rounded',
    'radio_style' => 'rounded',
    'field_bg' => '#FFFFFF',
    'field_border' => '#DCE4EC',
    'field_focus' => '#C5A253',
    'field_error' => '#E06D6D',
    'field_error_message' => 'Champ obligatoire à renseigner.',
    'icon_size' => '25',
    'icon_style' => 'outline',
    'icon_actions_family' => 'standard',
    'menu_dots_style' => 'filled',
    'action_icon_frame_size' => '30',
    'action_icon_glyph_size' => '16',
    'action_icon_gap' => '8',
    'action_icon_radius' => '10',
    'action_icon_border' => '#f1dcc0',
    'action_icon_bg' => '#ffffff',
    'action_icon_color' => '#d6a353',
    'action_icon_hover_bg' => '#fff7ec',
    'action_icon_more' => 'more-horizontal',
    'action_icon_view' => 'eye',
    'action_icon_edit' => 'edit-pencil',
    'action_icon_delete' => 'trash-bin',
    'action_icon_followup' => 'clipboard',
    'action_icon_archive' => 'archive',
    'action_icon_duplicate' => 'copy',
    /* ACDC 3.20.104 — 4 nouveaux defaults pour les pictogrammes d'action introduits. */
    'action_icon_create_session' => 'add-circle',
    'action_icon_sessions_list' => 'clock-list',
    'action_icon_results' => 'bar-chart',
    'action_icon_open' => 'external-link',
    'action_icon_send' => 'send',
    'action_icon_download' => 'download',
    'action_icon_print' => 'printer',
    'action_icon_calendar' => 'calendar',
    'action_icon_document' => 'file-text',
    'action_icon_register' => 'user-plus',
    'action_icon_default' => 'eye',
    'toggle_active_color' => '#8b5b23',
    'toggle_inactive_color' => '#d9dfe8',
    'toggle_width' => '44',
    'toggle_height' => '25',
    'table_header_bg' => '#FBF8F7',
    'table_header_text' => '#0C2D52',
    'table_cell_padding_y' => '12',
    'table_cell_padding_x' => '12',
    'table_row_height' => '48',
    'table_border' => '#DCE4EC',
    'table_density' => 'comfortable',
    'actions_column_style' => 'icons',
    'panel_bg' => '#FFFFFF',
    'panel_header_bg' => '#FBF8F7',
    'panel_footer_bg' => '#FFFFFF',
    'panel_padding' => '16',
    'modal_bg' => '#FFFFFF',
    'modal_header_bg' => '#FBF8F7',
    'modal_footer_bg' => '#FFFFFF',
    'modal_padding' => '14',
    'panel_shadow' => '0 4px 14px rgba(28, 44, 64, 0.05)',
    'modal_shadow' => '0 10px 30px rgba(28, 44, 64, 0.12)',
    'color_bg' => '#F6F8FB',
    'color_surface' => '#F9FAFB',
    'color_text' => '#0C2D52',
    'color_text_muted' => '#1E4777',
    'color_primary' => '#C5A253',
    'color_secondary' => '#F3E3BF',
    'color_button' => '#D7A24B',
    'color_button_text' => '#0B0706',
    'color_link' => '#0C2D52',
    'color_border' => '#DCE4EC',
    'admin_custom_css' => '',
    'portal_custom_css' => '',
    'pdf_logo_url' => '',
    'pdf_primary_color' => '#12325b',
    'pdf_secondary_color' => '#d6a353',
    'pdf_text_color' => '#1f2937',
    'pdf_muted_color' => '#6b7280',
    'pdf_border_color' => '#d7dbe3',
    'pdf_background_color' => '#ffffff',
    'pdf_header_background' => '#f5f7fb',
    'pdf_header_text_color' => '#12325b',
    'pdf_footer_background' => '#f5f7fb',
    'pdf_footer_text_color' => '#6b7280',
    'pdf_font_family' => 'Helvetica',
    'pdf_heading_font_family' => 'Helvetica-Bold',
    'pdf_title_font_size' => '20',
    'pdf_text_font_size' => '10',
    'pdf_line_height' => '1.45',
    'pdf_page_margin_top' => '20',
    'pdf_page_margin_right' => '15',
    'pdf_page_margin_bottom' => '18',
    'pdf_page_margin_left' => '15',
    'pdf_logo_max_width' => '62',
    'pdf_logo_max_height' => '26',
    'pdf_stamp_max_width' => '56',
    'pdf_signature_max_width' => '62',
    'pdf_header_title' => 'ACDC Formation',
    'pdf_footer_left' => 'Document généré par ACDC Formation',
    'pdf_footer_right' => 'Page {page}/{pages}',
    'pdf_color_map_json' => '',
    'pdf_style_overrides_json' => '',
    'email_logo_url' => '',
    'email_header_bg' => '#12325b',
    'email_header_text_color' => '#ffffff',
    'email_body_bg' => '#f5f7fb',
    'email_panel_bg' => '#ffffff',
    'email_text_color' => '#1f2937',
    'email_muted_color' => '#6b7280',
    'email_link_color' => '#12325b',
    'email_border_color' => '#d7dbe3',
    'email_button_bg' => '#d6a353',
    'email_button_text' => '#1f2937',
    'email_button_radius' => '10',
    'email_width' => '640',
    'email_font_family' => 'Arial, Helvetica, sans-serif',
    'email_title_size' => '24',
    'email_text_size' => '15',
    'email_spacing' => '24',
    'email_subject_prefix' => '',
    'email_footer_text' => 'ACDC Formation — Azur Compétences Développement & Conseil',
    'email_signature' => '',
    'email_custom_css' => '',
    'custom_css' => '',
  );
}


private function get_branding_options() {
  $defaults = $this->get_branding_defaults();
  $saved = get_option( 'acdc_of_branding', array() );
  if ( ! is_array( $saved ) ) {
    $saved = array();
  }
  return wp_parse_args( $saved, $defaults );
}

}
