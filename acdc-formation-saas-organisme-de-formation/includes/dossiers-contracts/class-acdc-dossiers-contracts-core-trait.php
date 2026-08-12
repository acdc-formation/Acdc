<?php
/**
 * ACDC Dossiers / Inscriptions / Conventions-contrats — ACDC_Dossiers_Contracts_Core_Trait.
 *
 * Extraction micro-incrémentale du sous-bloc métier :
 * dossiers / inscription / conventions-contrats.
 *
 * @since 3.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Dossiers_Contracts_Core_Trait {


  private function get_contract_params_defaults() {
    /* B8 — Textes par défaut des clauses obligatoires. Ils étaient vides, obligeant à tout
       ressaisir à la main sur chaque convention. Ces valeurs génériques (alignées Qualiopi)
       restent modifiables dans « Paramètres de convention » et sont écrasées par toute
       valeur enregistrée par l'organisme. */
    return array(
      'attach_program_rules' => '1',
      'legal_reference' => 'Articles L6353-1 et L.6353-2 du Code du travail et Décret n°2018-1341 du 28 décembre 2018',
      'accessibility_handicap' => "Nos formations sont accessibles aux personnes en situation de handicap. Pour toute situation particulière, notre référent handicap étudie avec vous, avant l'entrée en formation, les adaptations pédagogiques, matérielles et organisationnelles nécessaires afin de garantir l'accès à la formation.",
      'material_environment' => "La formation se déroule dans une salle adaptée équipée du matériel pédagogique nécessaire (vidéoprojecteur, paperboard, connexion internet). Les supports pédagogiques sont remis à chaque participant. En distanciel, une plateforme de visioconférence et les accès nécessaires sont fournis avant le démarrage.",
      'implementation_followup_evaluation' => "La formation est mise en œuvre conformément au programme annexé. Le suivi de l'exécution est assuré par des feuilles d'émargement signées par demi-journée. Les acquis sont évalués tout au long de la formation (évaluation formative) et à son terme (évaluation sommative). Une évaluation de satisfaction est recueillie auprès des participants à l'issue de la formation.",
      'financial_provisions' => "Le coût de la formation est indiqué dans les conditions financières de la présente convention. En cas de prise en charge par un financeur (OPCO, France Travail, etc.), l'organisme adresse la facture au financeur ; en l'absence de prise en charge totale, le reliquat reste dû par le commanditaire.",
      'payment_terms' => "Le règlement s'effectue par virement bancaire à réception de facture, selon l'échéancier convenu entre les parties. Les coordonnées bancaires de l'organisme figurent sur la facture.",
      'cancellation_terms' => "Toute annulation ou report doit être notifié par écrit. En cas d'annulation par le commanditaire à moins de 10 jours ouvrés du début de la formation, une indemnité de dédit pourra être facturée conformément à l'article L.6354-1 du Code du travail. En cas d'annulation ou de report du fait de l'organisme, les sommes déjà versées sont intégralement remboursées.",
      'possible_disputes' => "En cas de différend relatif à l'interprétation ou à l'exécution de la présente convention, les parties s'efforceront de trouver une solution amiable. À défaut d'accord, le litige sera porté devant les tribunaux compétents du ressort du siège social de l'organisme de formation.",
      'withdrawal_delay_days' => '14',
      'preview_label' => 'Convention de formation',
      'additional_sections' => array(
        array(
          'title' => 'Engagements de l\'Apprenant',
          'content' => "L'apprenant s'engage à suivre l'intégralité de la formation avec assiduité, à signer les feuilles d'émargement, à respecter le règlement intérieur de l'organisme et à participer activement aux activités et aux évaluations prévues.",
        ),
        array(
          'title' => 'Engagements de l\'Organisme de Formation',
          'content' => "L'organisme de formation s'engage à dispenser la formation conformément au programme convenu, à mettre à disposition les moyens pédagogiques, techniques et humains nécessaires, à assurer le suivi administratif de l'action et à délivrer les attestations de fin de formation.",
        ),
        array(
          'title' => 'Discipline et sanctions',
          'content' => "L'apprenant est tenu de respecter le règlement intérieur de l'organisme de formation. Tout manquement peut donner lieu à une sanction dans les conditions prévues par ce règlement et par les articles R.6352-3 et suivants du Code du travail.",
        ),
        array(
          'title' => 'Données personnelles et confidentialité',
          'content' => "Les données personnelles collectées sont traitées conformément au Règlement général sur la protection des données (RGPD), pour les seules finalités liées à la gestion de la formation, et conservées pendant la durée légale. Chaque personne concernée dispose d'un droit d'accès, de rectification et de suppression de ses données.",
        ),
        array(
          'title' => 'Litiges',
          'content' => "Tout litige relatif à l'interprétation ou à l'exécution de la présente convention relève, à défaut d'accord amiable entre les parties, de la compétence des tribunaux du ressort du siège social de l'organisme de formation.",
        ),
      ),
    );
  }


  private function get_contract_params_options() {
    $defaults = $this->get_contract_params_defaults();
    $saved = get_option( 'acdc_of_contract_params', array() );
    if ( ! is_array( $saved ) ) {
      $saved = array();
    }
    $options = wp_parse_args( $saved, $defaults );
    if ( empty( $options['additional_sections'] ) || ! is_array( $options['additional_sections'] ) ) {
      $options['additional_sections'] = $defaults['additional_sections'];
    }
    /* B8 — Une clause enregistrée vide retombe sur le texte par défaut (clauses obligatoires) :
       sans cela, un enregistrement antérieur avec champs vides masquerait les défauts. */
    foreach ( array( 'legal_reference', 'accessibility_handicap', 'material_environment', 'implementation_followup_evaluation', 'financial_provisions', 'payment_terms', 'cancellation_terms', 'possible_disputes' ) as $clause_key ) {
      if ( ( ! isset( $options[ $clause_key ] ) || '' === trim( (string) $options[ $clause_key ] ) ) && ! empty( $defaults[ $clause_key ] ) ) {
        $options[ $clause_key ] = $defaults[ $clause_key ];
      }
    }
    return $options;
  }


  private function get_subcontract_params_defaults() {
    return array(
      'article_3_trainer_obligations' => "Le formateur s'engage à : Dispenser la formation selon les objectifs fixés ; Respecter les règles légales en matière de formation professionnelle ; Transmettre à l'organisme de formation l'ensemble des documents attestant de son activité, notamment l'extrait K-bis et le numéro de déclaration d'activité. Justifier de ses compétences (CV, expériences, certifications, etc.) ; Avoir déposé auprès de l'administration fiscale, l'ensemble des déclarations fiscales obligatoires, être à jour de ses obligations sociales et être en règle en matière d'assurance et de disposer d'un contrat d'assurance en responsabilité civile pour son activité ; Respecter la confidentialité des informations communiquées par l'organisme de formation et les participants.",
      'article_4_of_obligations' => "L'organisme de formation s'engage à : Fournir au formateur les informations nécessaires à la bonne exécution de la mission ; Assurer la gestion administrative des prestations ; Assurer la gestion logistique des prestations ; Respecter ses obligations légales vis-à-vis des financeurs (OPCO, CPF, etc.).",
      'article_5_status_liability' => "Le formateur agit en toute indépendance, sans lien de subordination. Le présent contrat ne constitue ni un contrat de travail, ni un mandat social. Le formateur reste responsable de ses charges sociales, fiscales et de sa couverture professionnelle (assurance responsabilité civile professionnelle obligatoire).",
      'article_7_payment_terms' => "Le règlement sera effectué sur présentation d'une facture détaillée, adressée par le formateur à l'organisme de formation en début de chaque mois, récapitulant les prestations réalisées au cours du mois précédent.",
      'article_8_duration_termination' => "Le présent contrat est conclu pour une durée d'un an, à compter de sa date de signature par les deux parties. Il sera renouvelé par tacite reconduction pour des périodes successives d'un an, sauf résiliation par l'une ou l'autre des parties moyennant un préavis de 30 jours, notifié par lettre recommandée avec accusé de réception.",
      'article_9_intellectual_property' => "Les supports pédagogiques réalisés par le formateur demeurent sa propriété intellectuelle, sauf cession expresse stipulée par avenant.",
      'article_10_confidentiality' => "Les parties s'engagent à ne divulguer aucune information confidentielle obtenue dans le cadre du présent contrat.",
      'article_11_disputes' => "En cas de différend, les parties s'efforceront de trouver une solution amiable. À défaut, compétence est attribuée aux tribunaux du ressort du siège social de l'organisme de formation.",
      'preview_label' => 'Contrat de sous-traitance',
      'additional_sections' => array(),
    );
  }


  private function get_subcontract_params_options() {
    $defaults = $this->get_subcontract_params_defaults();
    $saved = get_option( 'acdc_of_subcontract_params', array() );
    if ( ! is_array( $saved ) ) {
      $saved = array();
    }
    $options = wp_parse_args( $saved, $defaults );
    if ( ! isset( $options['additional_sections'] ) || ! is_array( $options['additional_sections'] ) ) {
      $options['additional_sections'] = $defaults['additional_sections'];
    }
    return $options;
  }



  private function get_registration_contract_modes() {
    return array(
      'generate_blank' => 'Générer la convention (vierge) / le contrat (vierge)',
      'generate_blank_email' => 'Générer la convention (vierge) / le contrat (vierge) + envoi e-mail',
      'generate_blank_esign' => 'Générer la convention (vierge) / le contrat (vierge) + envoi e-mail signature électronique',
      'import_signed' => 'Importer une convention (signée) / un contrat (signé)',
      'import_blank_esign' => 'Importer une convention (vierge) / un contrat (vierge) + envoi e-mail signature électronique',
    );
  }


  private function get_registration_contract_funding_options() {
    return array(
      '' => 'Choisir une option',
      'Non' => 'Non',
      'Oui' => 'Oui',
    );
  }


  private function get_registration_contract_additional_sections_defaults() {
    $params = $this->get_contract_params_options();
    $sections = array();
    if ( ! empty( $params['additional_sections'] ) && is_array( $params['additional_sections'] ) ) {
      foreach ( $params['additional_sections'] as $section ) {
        $title = isset( $section['title'] ) ? sanitize_text_field( (string) $section['title'] ) : '';
        $content = isset( $section['content'] ) ? wp_kses_post( (string) $section['content'] ) : '';
        if ( '' === $title && '' === trim( wp_strip_all_tags( $content ) ) ) {
          continue;
        }
        $sections[] = array(
          'title' => $title,
          'content' => $content,
        );
      }
    }
    if ( empty( $sections ) ) {
      $sections = array(
        array( 'title' => 'Engagements de l\'Apprenant', 'content' => '' ),
        array( 'title' => 'Engagements de l\'Organisme de Formation', 'content' => '' ),
        array( 'title' => 'Discipline et sanctions', 'content' => '' ),
        array( 'title' => 'Données personnelles et confidentialité', 'content' => '' ),
        array( 'title' => 'Litiges', 'content' => '' ),
      );
    }
    return $sections;
  }


  private function normalize_registration_contract_sections( $sections_in ) {
    $sections = array();
    if ( ! is_array( $sections_in ) ) {
      return $sections;
    }
    foreach ( $sections_in as $section ) {
      $title = isset( $section['title'] ) ? sanitize_text_field( wp_unslash( $section['title'] ) ) : '';
      $content = isset( $section['content'] ) ? wp_kses_post( wp_unslash( $section['content'] ) ) : '';
      if ( '' === $title && '' === trim( wp_strip_all_tags( $content ) ) ) {
        continue;
      }
      $sections[] = array(
        'title' => $title,
        'content' => $content,
      );
    }
    return $sections;
  }



  private function acdc_ensure_company_from_prospect( $prospect ) {
    if ( ! $prospect || $this->is_individual_prospect_profile( isset( $prospect->profile_type ) ? (string) $prospect->profile_type : '' ) ) {
      return 0;
    }

    $existing_company_id = (int) $this->acdc_find_company_id_from_prospect( $prospect );
    if ( $existing_company_id ) {
      return $existing_company_id;
    }

    $company_name = isset( $prospect->company_name ) ? sanitize_text_field( (string) $prospect->company_name ) : '';
    if ( '' === $company_name ) {
      return 0;
    }

    global $wpdb;

    $data = array(
      'name'               => $company_name,
      'legal_form'         => isset( $prospect->signer_quality ) ? sanitize_text_field( (string) $prospect->signer_quality ) : '',
      'siret'              => isset( $prospect->siret ) ? sanitize_text_field( (string) $prospect->siret ) : '',
      'naf_code'           => isset( $prospect->naf_code ) ? sanitize_text_field( (string) $prospect->naf_code ) : '',
      'email'              => ! empty( $prospect->signer_email ) ? sanitize_email( (string) $prospect->signer_email ) : sanitize_email( isset( $prospect->email ) ? (string) $prospect->email : '' ),
      'phone'              => ! empty( $prospect->company_phone ) ? sanitize_text_field( (string) $prospect->company_phone ) : sanitize_text_field( isset( $prospect->signer_phone ) ? (string) $prospect->signer_phone : '' ),
      'website'            => '',
      'address'            => isset( $prospect->address ) ? sanitize_text_field( (string) $prospect->address ) : '',
      'postal_code'        => isset( $prospect->postal_code ) ? sanitize_text_field( (string) $prospect->postal_code ) : '',
      'city'               => isset( $prospect->city ) ? sanitize_text_field( (string) $prospect->city ) : '',
      'signer_first_name'  => isset( $prospect->signer_first_name ) ? sanitize_text_field( (string) $prospect->signer_first_name ) : '',
      'signer_last_name'   => isset( $prospect->signer_last_name ) ? sanitize_text_field( (string) $prospect->signer_last_name ) : '',
      'signer_quality'     => isset( $prospect->signer_quality ) ? sanitize_text_field( (string) $prospect->signer_quality ) : '',
      'notes'              => isset( $prospect->comment_text ) ? sanitize_textarea_field( (string) $prospect->comment_text ) : '',
      'created_at'         => $this->now_mysql(),
      'updated_at'         => $this->now_mysql(),
    );

    $inserted = $wpdb->insert( $this->company_table, $data );
    if ( false === $inserted ) {
      return 0;
    }

    $new_company_id = (int) $wpdb->insert_id;

    /* Attacher les documents Qualiopi à la fiche commanditaire */
    $this->acdc_attach_prospect_documents_to_company( $new_company_id, $prospect );

    return $new_company_id;
  }

  private function acdc_attach_prospect_documents_to_company( $company_id, $prospect ) {
    if ( ! $company_id || ! $prospect ) {
      return;
    }

    global $wpdb;
    $now = $this->now_mysql();

    /* 1 — Recueil des besoins : lier le PDF tokenisé du need lié au prospect */
    $need = null;
    if ( ! empty( $prospect->id ) ) {
      $need = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$this->need_table} WHERE source_prospect_id = %d ORDER BY id DESC LIMIT 1",
        (int) $prospect->id
      ) );
    }

    if ( $need && ! empty( $need->id ) && method_exists( $this, 'acdc_get_need_pdf_download_url' ) ) {
      $need_pdf_url = $this->acdc_get_need_pdf_download_url( (int) $need->id, (int) $prospect->id );
      if ( $need_pdf_url ) {
        $need_doc_exists = (int) $wpdb->get_var( $wpdb->prepare(
          "SELECT COUNT(*) FROM {$this->document_table} WHERE company_id = %d AND document_type = %s AND title = %s",
          $company_id, 'Qualiopi', 'Recueil des besoins'
        ) );
        if ( ! $need_doc_exists ) {
          $wpdb->insert( $this->document_table, array(
            'company_id'    => $company_id,
            'contact_id'    => null,
            'title'         => 'Recueil des besoins',
            'document_type' => 'Qualiopi',
            'file_url'      => esc_url_raw( $need_pdf_url ),
            'file_path'     => '',
            'mime_type'     => 'application/pdf',
            'uploaded_by'   => get_current_user_id(),
            'created_at'    => $now,
          ) );
        }
      }
    }

    /* 2 — Proposition commerciale : lier le fichier HTML de la proposition */
    if ( ! empty( $prospect->id ) ) {
      $proposal_table = $wpdb->prefix . 'acdc_of_proposals';
      $proposal = $wpdb->get_row( $wpdb->prepare(
        "SELECT p.* FROM {$proposal_table} p
         INNER JOIN {$this->need_table} n ON n.id = p.need_id
         WHERE n.source_prospect_id = %d
         AND p.pdf_url != ''
         ORDER BY p.updated_at DESC LIMIT 1",
        (int) $prospect->id
      ) );
      if ( $proposal && ! empty( $proposal->pdf_url ) ) {
        $prop_doc_exists = (int) $wpdb->get_var( $wpdb->prepare(
          "SELECT COUNT(*) FROM {$this->document_table} WHERE company_id = %d AND document_type = %s AND title = %s",
          $company_id, 'Qualiopi', 'Proposition commerciale'
        ) );
        if ( ! $prop_doc_exists ) {
          $wpdb->insert( $this->document_table, array(
            'company_id'    => $company_id,
            'contact_id'    => null,
            'title'         => 'Proposition commerciale',
            'document_type' => 'Qualiopi',
            'file_url'      => esc_url_raw( (string) $proposal->pdf_url ),
            'file_path'     => '',
            'mime_type'     => 'text/html',
            'uploaded_by'   => get_current_user_id(),
            'created_at'    => $now,
          ) );
        }
      }
    }
  }

  private function acdc_get_contract_prefill_from_prospect( $prospect, $formations = array(), $proposal = null ) {
    $prefill = array(
      'commanditaire_type' => '',
      'company_id'         => 0,
      'formation_id'       => 0,
      'objectives_text'    => '',
      'price_ht'           => '',
      'start_date'         => '',
      'end_date'           => '',
      'public_funding'     => '',
    );

    if ( ! $prospect ) {
      return $prefill;
    }

    $profile_type = isset( $prospect->profile_type ) ? (string) $prospect->profile_type : '';
    $prefill['commanditaire_type'] = $this->is_individual_prospect_profile( $profile_type ) ? 'Particulier' : 'Entreprise';
    /* ACDC 3.25.157 — Ce préremplissage est un CHEMIN DE LECTURE : il alimente un
       formulaire et un sélecteur, il ne doit rien écrire. Il appelait
       acdc_ensure_company_from_prospect(), qui CRÉE la fiche commanditaire. Or le
       sélecteur de prospect de la convention boucle sur TOUS les prospects et
       appelle ce préremplissage pour chacun : ouvrir « Créer une convention »
       créait donc, en une seule page, un commanditaire par prospect existant.
       C'est l'origine des 191 commanditaires pour une seule convention signée.
       La création n'a plus lieu qu'à la SIGNATURE de la convention : c'est la
       signature qui fait le client. Voir
       handle_registration_contract_signed_company(). */
    /* Le sélecteur appelle ce préremplissage une fois par prospect. Sans cette
       mémorisation, chaque tour relirait toute la table des commanditaires.
       Cette fonction n'insérant plus rien, la liste ne peut pas se périmer. */
    static $companies_cache = null;
    if ( null === $companies_cache ) {
      $companies_cache = $this->get_companies();
    }
    $prefill['company_id'] = (int) $this->acdc_find_company_id_from_prospect( $prospect, $companies_cache );

    /* Si une proposition est fournie, elle est prioritaire sur la formation catalogue */
    if ( $proposal && ! empty( $proposal->id ) ) {
      /* formation_id depuis la proposition */
      if ( ! empty( $proposal->formation_id ) ) {
        $prefill['formation_id'] = (int) $proposal->formation_id;
      } elseif ( ! empty( $proposal->formation_title ) ) {
        /* fallback : match par titre */
        $p_title = trim( (string) $proposal->formation_title );
        foreach ( (array) $formations as $formation ) {
          $fid = ! empty( $formation->id ) ? (int) $formation->id : 0;
          $ftitle = isset( $formation->title ) ? trim( (string) $formation->title ) : '';
          if ( $fid && $ftitle && ( 0 === strcasecmp( $ftitle, $p_title ) || false !== stripos( $ftitle, $p_title ) ) ) {
            $prefill['formation_id'] = $fid;
            break;
          }
        }
      }
      /* Objectifs : custom_objectives de la proposition en priorité */
      if ( ! empty( $proposal->custom_objectives ) ) {
        $prefill['objectives_text'] = wp_strip_all_tags( (string) $proposal->custom_objectives );
      }
      /* Montant total HT depuis la proposition */
      if ( ! empty( $proposal->formation_total ) ) {
        $prefill['price_ht'] = number_format( (float) $proposal->formation_total, 2, ',', '' );
      }
      /* Financement */
      if ( ! empty( $proposal->formation_funding ) ) {
        $prefill['public_funding'] = sanitize_text_field( (string) $proposal->formation_funding );
      }
    } else {
      /* Priorité : formation liée au prospect par IDENTIFIANT exact (desired_formation_id).
         Le match par titre en fuzzy (stripos) présélectionnait la mauvaise formation quand
         deux intitulés se ressemblent (un titre préfixe de l'autre) ou que le catalogue
         contient des doublons — d'où une formation et un tarif erronés sur la convention. */
      $desired_formation_id = isset( $prospect->desired_formation_id ) ? (int) $prospect->desired_formation_id : 0;
      if ( $desired_formation_id ) {
        /* ACDC 3.25.163 — Le catalogue est DÉJÀ chargé et passé en argument : le
           réinterroger ici déclenchait une requête par appel. Or l'écran des
           conventions appelle ce préremplissage une fois par prospect pour alimenter
           son sélecteur — plusieurs centaines de requêtes pour un seul affichage,
           d'où la lenteur constatée à l'ouverture. On lit d'abord la liste en
           mémoire, la requête ne servant plus que de repli. */
        $linked_formation = null;
        foreach ( (array) $formations as $catalog_formation ) {
          if ( isset( $catalog_formation->id ) && (int) $catalog_formation->id === $desired_formation_id ) {
            $linked_formation = $catalog_formation;
            break;
          }
        }
        if ( ! $linked_formation ) {
          $linked_formation = $this->get_formation( $desired_formation_id );
        }
        if ( $linked_formation ) {
          $prefill['formation_id']    = $desired_formation_id;
          $prefill['objectives_text'] = isset( $linked_formation->objectives ) ? wp_strip_all_tags( (string) $linked_formation->objectives ) : '';
          $prefill['price_ht']        = isset( $linked_formation->price_ht ) ? (string) $linked_formation->price_ht : '';
        }
      }
      /* Fallback sur la formation catalogue via desired_training (titre) si aucun id exact. */
      if ( empty( $prefill['formation_id'] ) ) {
        $desired_training = isset( $prospect->desired_training ) ? trim( (string) $prospect->desired_training ) : '';
        if ( '' !== $desired_training ) {
          foreach ( (array) $formations as $formation ) {
            $formation_id    = ! empty( $formation->id ) ? (int) $formation->id : 0;
            $formation_title = isset( $formation->title ) ? trim( (string) $formation->title ) : '';
            if ( ! $formation_id || '' === $formation_title ) {
              continue;
            }
            /* Exact d'abord (toutes les formations), pour éviter qu'un titre préfixe
               ne l'emporte via le fuzzy ci-dessous. */
            if ( 0 === strcasecmp( $formation_title, $desired_training ) ) {
              $prefill['formation_id']    = $formation_id;
              $prefill['objectives_text'] = isset( $formation->objectives ) ? wp_strip_all_tags( (string) $formation->objectives ) : '';
              $prefill['price_ht']        = isset( $formation->price_ht ) ? (string) $formation->price_ht : '';
              break;
            }
          }
        }
      }
      if ( empty( $prefill['formation_id'] ) ) {
        $desired_training = isset( $prospect->desired_training ) ? trim( (string) $prospect->desired_training ) : '';
        if ( '' !== $desired_training ) {
          foreach ( (array) $formations as $formation ) {
            $formation_id    = ! empty( $formation->id ) ? (int) $formation->id : 0;
            $formation_title = isset( $formation->title ) ? trim( (string) $formation->title ) : '';
            if ( ! $formation_id || '' === $formation_title ) {
              continue;
            }
            if ( false !== stripos( $formation_title, $desired_training ) || false !== stripos( $desired_training, $formation_title ) ) {
              $prefill['formation_id']    = $formation_id;
              $prefill['objectives_text'] = isset( $formation->objectives ) ? wp_strip_all_tags( (string) $formation->objectives ) : '';
              $prefill['price_ht']        = isset( $formation->price_ht ) ? (string) $formation->price_ht : '';
              break;
            }
          }
        }
      }
    }

    return $prefill;
  }

  private function get_registration_contract_default_record() {
    $params = $this->get_contract_params_options();
    return (object) array(
      'id' => 0,
      'title' => '',
      'generate_mode' => '',
      'commanditaire_type' => 'Entreprise',
      'source_prospect_id' => 0,
      'company_id' => 0,
      'learner_ids' => '',
      'formation_id' => 0,
      'formation_title' => '',
      'start_date' => '',
      'end_date' => '',
      'legal_reference' => isset( $params['legal_reference'] ) ? (string) $params['legal_reference'] : '',
      'objectives_text' => '',
      'accessibility_handicap' => isset( $params['accessibility_handicap'] ) ? (string) $params['accessibility_handicap'] : '',
      'material_environment' => isset( $params['material_environment'] ) ? (string) $params['material_environment'] : '',
      'implementation_followup_evaluation' => isset( $params['implementation_followup_evaluation'] ) ? (string) $params['implementation_followup_evaluation'] : '',
      'cancellation_terms' => isset( $params['cancellation_terms'] ) ? (string) $params['cancellation_terms'] : '',
      'price_ht' => '',
      'vat_rate' => $this->get_default_vat_rate_for_contract(),
      'deposit_enabled' => 0,
      'deposit_amount_ht' => '',
      'public_funding' => '',
      'transport_fees_enabled' => 0,
      'transport_fees_amount_ht' => '',
      'meal_fees_enabled' => 0,
      'meal_fees_amount_ht' => '',
      'financial_provisions' => isset( $params['financial_provisions'] ) ? (string) $params['financial_provisions'] : '',
      'payment_terms' => isset( $params['payment_terms'] ) ? (string) $params['payment_terms'] : '',
      'disputes_terms' => isset( $params['possible_disputes'] ) ? (string) $params['possible_disputes'] : '',
      'withdrawal_delay_days' => isset( $params['withdrawal_delay_days'] ) ? (string) $params['withdrawal_delay_days'] : '14',
      'additional_sections' => wp_json_encode( $this->get_registration_contract_additional_sections_defaults() ),
      'document_url' => '',
      'document_path' => '',
      'signature_request_id' => 0,
      'signature_status' => '',
      'signature_sent_at' => '',
      'signature_completed_at' => '',
      'signed_document_url' => '',
      'signed_document_path' => '',
      'created_at' => '',
      'updated_at' => '',
    );
  }


  private function get_default_vat_rate_for_contract() {
    $profile  = $this->get_company_profile_options();
    $vat_raw  = isset( $profile['vat_rate'] ) ? (string) $profile['vat_rate'] : '20';
    $vat_clean = str_replace( array( '%', ' ' ), '', $vat_raw );
    $vat_clean = str_replace( '.', ',', $vat_clean );
    return '' !== $vat_clean ? $vat_clean : '20,00';
  }

  private function get_registration_contract_upload_file_path_from_url( $url ) {
    $url = is_scalar( $url ) ? trim( (string) $url ) : '';
    if ( '' === $url ) {
      return '';
    }

    $uploads = wp_get_upload_dir();
    if ( ! empty( $uploads['baseurl'] ) && ! empty( $uploads['basedir'] ) && 0 === strpos( $url, $uploads['baseurl'] ) ) {
      $relative = ltrim( substr( $url, strlen( $uploads['baseurl'] ) ), '/' );
      $path = trailingslashit( $uploads['basedir'] ) . $relative;
      return ( file_exists( $path ) && is_readable( $path ) ) ? $path : '';
    }

    $home = home_url( '/' );
    if ( 0 === strpos( $url, $home ) ) {
      $relative = ltrim( substr( (string) wp_parse_url( $url, PHP_URL_PATH ), 1 ), '/' );
      $path = trailingslashit( ABSPATH ) . $relative;
      return ( file_exists( $path ) && is_readable( $path ) ) ? $path : '';
    }

    return '';
  }


  /**
   * ACDC 3.25.237 — UN DÉFAUT DE PDF NE DOIT PLUS TUER LA PAGE.
   *
   * Toutes les sorties d'échec de la fabrication rendent déjà un triplet vide,
   * et les appelants savent le lire. Une seule chose leur échappait : l'erreur
   * fatale. Un simple `global $wpdb;` oublié suffisait à faire mourir
   * `admin-post.php` en plein enregistrement — la convention était bien écrite
   * en base, mais l'utilisateur voyait un écran blanc et ne pouvait pas savoir
   * si son travail avait été sauvegardé.
   *
   * On enveloppe donc la fabrication : l'erreur est journalisée, remontée en
   * clair dans le message de retour, et la page vit. Ce n'est pas masquer le
   * défaut — c'est le dire au lieu de disparaître.
   */
  private function generate_registration_contract_pdf_file( $contract_id, $handwritten_sig_path = '' ) {
    try {
      return $this->build_registration_contract_pdf_file( $contract_id, $handwritten_sig_path );
    } catch ( Throwable $e ) {
      $message = sprintf(
        'ACDC — génération du PDF de convention #%d impossible : %s (%s ligne %d)',
        absint( $contract_id ),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
      );
      error_log( $message );
      return array(
        'url'      => '',
        'path'     => '',
        'filename' => '',
        'error'    => sprintf(
          'Le PDF de la convention n’a pas pu être généré (%s). La convention, elle, est bien enregistrée.',
          $e->getMessage()
        ),
      );
    }
  }

  private function build_registration_contract_pdf_file( $contract_id, $handwritten_sig_path = '' ) {
    $contract_id = absint( $contract_id );
    if ( ! $contract_id ) {
      return array( 'url' => '', 'path' => '', 'filename' => '' );
    }

    $contract = $this->get_registration_contract( $contract_id );
    if ( ! $contract ) {
      return array( 'url' => '', 'path' => '', 'filename' => '' );
    }

    $context = $this->get_contract_pdf_context( array( 'contract_id' => $contract_id ) );
    // ACDC 3.21.08 — Signature manuscrite du commanditaire à apposer
    if ( '' !== $handwritten_sig_path ) {
      $context['handwritten_sig_path'] = sanitize_text_field( $handwritten_sig_path );
    }
    $pages = $this->build_contract_pdf_pages( $context );
    if ( empty( $pages ) || ! is_array( $pages ) ) {
      return array( 'url' => '', 'path' => '', 'filename' => '' );
    }

    if ( ! class_exists( 'ACDC_Sig_Core' ) || ! class_exists( 'ACDC_Sig_PDF' ) ) {
      return array( 'url' => '', 'path' => '', 'filename' => '' );
    }

    $uploads = wp_upload_dir();
    if ( empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
      return array( 'url' => '', 'path' => '', 'filename' => '' );
    }

    $dir_path = trailingslashit( $uploads['basedir'] ) . 'acdc-contracts/' . $contract_id . '/';
    $dir_url  = trailingslashit( $uploads['baseurl'] ) . 'acdc-contracts/' . $contract_id . '/';
    if ( ! wp_mkdir_p( $dir_path ) ) {
      return array( 'url' => '', 'path' => '', 'filename' => '' );
    }

    $filename = $this->get_registration_contract_display_file_name( $contract, array() );
    $filename = preg_replace( '/\.pdf$/i', '', sanitize_file_name( $filename ) ) . '.pdf';
    $filepath = $dir_path . $filename;
    $fileurl  = $dir_url . rawurlencode( $filename );

    $sig_core = new ACDC_Sig_Core();
    $sig_core->init_tables();
    $sig_pdf = new ACDC_Sig_PDF( $sig_core );
    $ref = new ReflectionClass( $sig_pdf );
    $method = $ref->getMethod( 'render_to_string' );
    $method->setAccessible( true );
    $pdf_raw = $method->invoke( $sig_pdf, $pages );
    if ( ! is_string( $pdf_raw ) || '' === $pdf_raw ) {
      return array( 'url' => '', 'path' => '', 'filename' => '' );
    }

    if ( ! $sig_core->safe_file_write( $filepath, $pdf_raw, 'convention / contrat PDF' ) ) {
      return array( 'url' => '', 'path' => '', 'filename' => '' );
    }

    global $wpdb;
    $wpdb->update(
      $this->registration_contract_table,
      array(
        'document_url' => esc_url_raw( $fileurl ),
        'document_path' => sanitize_text_field( $filepath ),
        'updated_at' => $this->now_mysql(),
      ),
      array( 'id' => $contract_id )
    );

    return array( 'url' => $fileurl, 'path' => $filepath, 'filename' => $filename );
  }


  private function get_registration_contract_recipient_data( $contract, $context = array() ) {
    $recipient = array(
      'to' => '',
      'to_name' => '',
      'cc' => array(),
      'prospect' => null,
      'company' => null,
      'signatory_name' => '',
      'is_company' => false,
    );

    if ( ! $contract ) {
      return $recipient;
    }

    $prospect = null;
    if ( ! empty( $contract->source_prospect_id ) ) {
      $prospect = $this->get_prospect( (int) $contract->source_prospect_id );
    }
    $recipient['prospect'] = $prospect;

    $company = isset( $context['company'] ) ? $context['company'] : null;
    if ( ! $company && ! empty( $contract->company_id ) ) {
      $company = $this->get_company( (int) $contract->company_id );
    }
    $recipient['company'] = $company;

    $is_company = 'Entreprise' === ( isset( $contract->commanditaire_type ) ? (string) $contract->commanditaire_type : '' );
    $recipient['is_company'] = $is_company;

    if ( $is_company ) {
      $to = '';
      $to_name = '';
      if ( $company ) {
        $to = ! empty( $company->signer_email ) ? sanitize_email( (string) $company->signer_email ) : '';
        $to_name = trim( (string) ( ( $company->signer_first_name ?? '' ) . ' ' . ( $company->signer_last_name ?? '' ) ) );
        if ( '' === $to && ! empty( $company->email ) ) {
          $to = sanitize_email( (string) $company->email );
        }
      }
      if ( '' === $to_name && $prospect ) {
        $to_name = trim( (string) ( ( $prospect->signer_first_name ?? '' ) . ' ' . ( $prospect->signer_last_name ?? '' ) ) );
      }
      if ( '' === $to && $prospect ) {
        $to = $this->get_prospect_primary_email( $prospect );
        $to_name = $this->get_prospect_display_name( $prospect );
      }
      $recipient['to'] = $to;
      $recipient['to_name'] = $to_name;
      $recipient['signatory_name'] = $to_name;
    } else {
      if ( $prospect ) {
        $recipient['to'] = $this->get_prospect_primary_email( $prospect );
        $recipient['to_name'] = $this->get_prospect_display_name( $prospect );
      } else {
        $learners = $this->get_registration_contract_learners( $contract );
        if ( ! empty( $learners[0] ) ) {
          $learner = $learners[0];
          $recipient['to'] = ! empty( $learner->email ) ? sanitize_email( (string) $learner->email ) : '';
          $recipient['to_name'] = trim( (string) ( ( $learner->first_name ?? '' ) . ' ' . ( $learner->usage_last_name ?? $learner->last_name ?? '' ) ) );
        }
      }
      $recipient['signatory_name'] = $recipient['to_name'];
    }

    if ( $prospect ) {
      $recipient['cc'] = $this->get_prospect_copy_recipients( $prospect );
    }

    return $recipient;
  }


  private function get_registration_contract_mail_package( $contract, $context = array() ) {
    $package = array(
      'attachments' => array(),
      'links' => array(),
      'contract_file' => array( 'path' => '', 'url' => '', 'filename' => '' ),
      'program_file' => array( 'path' => '', 'url' => '', 'label' => 'Programme de formation' ),
      'rules_file' => array( 'path' => '', 'url' => '', 'label' => 'Règlement intérieur de la formation' ),
    );

    if ( ! $contract ) {
      return $package;
    }

    $pdf_file = $this->generate_registration_contract_pdf_file( (int) $contract->id );
    if ( ! empty( $pdf_file['path'] ) ) {
      $package['attachments'][] = $pdf_file['path'];
      $package['contract_file'] = $pdf_file;
    }
    if ( ! empty( $pdf_file['url'] ) ) {
      $package['links'][] = array( 'label' => 'Convention / contrat de formation', 'url' => $pdf_file['url'] );
    }

    $formation = isset( $context['formation'] ) ? $context['formation'] : null;
    if ( ! $formation && ! empty( $contract->formation_id ) ) {
      $formation = $this->get_formation( (int) $contract->formation_id );
    }
    if ( $formation && ! empty( $formation->program_file_url ) ) {
      $program_url = esc_url_raw( (string) $formation->program_file_url );
      $program_path = $this->get_registration_contract_upload_file_path_from_url( $program_url );
      $package['program_file'] = array( 'path' => $program_path, 'url' => $program_url, 'label' => 'Programme de formation' );
      if ( $program_path ) {
        $package['attachments'][] = $program_path;
      }
      $package['links'][] = array( 'label' => 'Programme de formation', 'url' => $program_url );
    }

    if ( empty( $package['program_file']['url'] ) && ! empty( $contract->formation_title ) ) {
      global $wpdb;
      $program_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->formation_table} WHERE title = %s ORDER BY id DESC LIMIT 1", (string) $contract->formation_title ) );
      if ( $program_row && ! empty( $program_row->program_file_url ) ) {
        $program_url = esc_url_raw( (string) $program_row->program_file_url );
        $program_path = $this->get_registration_contract_upload_file_path_from_url( $program_url );
        $package['program_file'] = array( 'path' => $program_path, 'url' => $program_url, 'label' => 'Programme de formation' );
        if ( $program_path ) {
          $package['attachments'][] = $program_path;
        }
        $package['links'][] = array( 'label' => 'Programme de formation', 'url' => $program_url );
      }
    }

    $profile = $this->get_company_profile_options();
    $rules_url = ! empty( $profile['training_rules_url'] ) ? esc_url_raw( (string) $profile['training_rules_url'] ) : '';
    if ( $rules_url ) {
      $rules_path = $this->get_registration_contract_upload_file_path_from_url( $rules_url );
      $package['rules_file'] = array( 'path' => $rules_path, 'url' => $rules_url, 'label' => 'Règlement intérieur de la formation' );
      if ( $rules_path ) {
        $package['attachments'][] = $rules_path;
      }
      $package['links'][] = array( 'label' => 'Règlement intérieur de la formation', 'url' => $rules_url );
    }

    $package['attachments'] = array_values( array_unique( array_filter( $package['attachments'] ) ) );
    return $package;
  }


  private function maybe_send_registration_contract_bundle_email( $contract_id ) {
    $contract = $this->get_registration_contract( $contract_id );
    if ( ! $contract ) {
      return array( 'sent' => false, 'message' => 'Convention introuvable.', 'recipient' => '' );
    }

    $context = $this->get_registration_contract_related_context( $contract );
    $recipient = $this->get_registration_contract_recipient_data( $contract, $context );
    $to = ! empty( $recipient['to'] ) ? sanitize_email( (string) $recipient['to'] ) : '';
    if ( '' === $to || ! is_email( $to ) ) {
      return array( 'sent' => false, 'message' => 'Aucun destinataire e-mail exploitable pour cette convention / ce contrat.', 'recipient' => '' );
    }

    $package = $this->get_registration_contract_mail_package( $contract, $context );
    if ( empty( $package['contract_file']['path'] ) ) {
      return array( 'sent' => false, 'message' => 'Le PDF de la convention / du contrat n\'a pas pu être généré.', 'recipient' => $to );
    }

    $branding = $this->get_branding_options();
    $company_name = ! empty( $branding['company_name'] ) ? (string) $branding['company_name'] : 'ACDC Formation';
    $formation_title = ! empty( $contract->formation_title ) ? (string) $contract->formation_title : ( ! empty( $context['formation']->title ) ? (string) $context['formation']->title : 'Formation' );
    $subject = 'Convention / contrat de formation — ' . $formation_title;

    $links_html = '';
    if ( ! empty( $package['links'] ) ) {
      $items = array();
      foreach ( $package['links'] as $link ) {
        if ( empty( $link['url'] ) ) {
          continue;
        }
        $items[] = '<li><a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . '</a></li>';
      }
      if ( ! empty( $items ) ) {
        $links_html = '<p>Les documents suivants sont également disponibles via les liens ci-dessous :</p><ul>' . implode( '', $items ) . '</ul>';
      }
    }

    $intro = '<p style="font-size:18px;line-height:1.65;margin:0 0 22px;">Veuillez trouver ci-joint votre convention / contrat de formation';
    if ( ! empty( $package['program_file']['path'] ) || ! empty( $package['program_file']['url'] ) || ! empty( $package['rules_file']['path'] ) || ! empty( $package['rules_file']['url'] ) ) {
      $intro .= ', accompagnée du programme de formation et du règlement intérieur';
    }
    $intro .= '.</p>';

    $summary_rows = array(
      array( 'label' => 'Formation', 'value' => $formation_title ),
      array( 'label' => 'Commanditaire', 'value' => ! empty( $this->get_registration_contract_commanditaire_display_data( $contract, $context )['summary'] ) ? $this->get_registration_contract_commanditaire_display_data( $contract, $context )['summary'] : $this->get_registration_contract_display_commanditaire_name( $contract, $context ) ),
      array( 'label' => 'Dates', 'value' => trim( ( ! empty( $contract->start_date ) ? mysql2date( 'd/m/Y', $contract->start_date ) : '—' ) . ' au ' . ( ! empty( $contract->end_date ) ? mysql2date( 'd/m/Y', $contract->end_date ) : '—' ) ) ),
      array( 'label' => 'Organisme', 'value' => $company_name ),
    );

    $header_args = array(
      'source_module' => 'dossiers-contracts',
      'source_action' => 'registration_contract_bundle',
      'related_entity_type' => 'contract',
      'related_entity_id' => (int) $contract->id,
      'email_category' => 'administratif',
      'email_audience' => 'prospect',
    );
    if ( ! empty( $recipient['cc'] ) ) {
      $header_args['extra_headers'] = array( 'Cc: ' . implode( ',', array_map( 'sanitize_email', $recipient['cc'] ) ) );
    }

    $html = $this->acdc_build_transactional_email_html( array(
      'greeting_name' => ! empty( $recipient['to_name'] ) ? $recipient['to_name'] : 'Bonjour',
      'intro_html' => $intro,
      'summary_title' => 'RÉCAPITULATIF DE VOTRE CONVENTION / CONTRAT',
      'summary_rows' => $summary_rows,
      'body_html' => $links_html,
      'footer_notice' => 'Cet e-mail contient des documents contractuels relatifs à votre formation.',
    ) );
    $headers = $this->acdc_get_transactional_email_headers( $header_args );
        /* ACDC 3.25.161 — Attribution d'archive : sans ces en-têtes, l'envoi
           s'affiche « plugin / wp_mail » dans l'archive, sans module identifiable. */
        $headers[] = 'X-ACDC-Source-Module: dossiers';
        $headers[] = 'X-ACDC-Source-Action: contract_bundle';
        $headers[] = 'X-ACDC-Email-Category: dossiers';
    $sent = wp_mail( $to, wp_strip_all_tags( $subject ), $html, $headers, $package['attachments'] );

    return array(
      'sent' => (bool) $sent,
      'message' => $sent ? 'E-mail de convention / contrat envoyé.' : 'L\'e-mail de convention / contrat n\'a pas pu être envoyé.',
      'recipient' => $to,
      'cc' => $recipient['cc'],
      'attachments' => $package['attachments'],
    );
  }


  private function maybe_create_registration_contract_signature_request( $contract_id ) {
    $contract = $this->get_registration_contract( $contract_id );
    if ( ! $contract ) {
      return array( 'created' => false, 'message' => 'Convention introuvable.', 'request_id' => 0 );
    }

    $context = $this->get_registration_contract_related_context( $contract );
    $recipient = $this->get_registration_contract_recipient_data( $contract, $context );
    $to = ! empty( $recipient['to'] ) ? sanitize_email( (string) $recipient['to'] ) : '';
    if ( '' === $to || ! is_email( $to ) ) {
      return array( 'created' => false, 'message' => 'Aucun destinataire exploitable pour la signature électronique.', 'request_id' => 0 );
    }

    $package = $this->get_registration_contract_mail_package( $contract, $context );
    if ( empty( $package['contract_file']['path'] ) || empty( $package['contract_file']['url'] ) ) {
      return array( 'created' => false, 'message' => 'Le PDF à signer n\'a pas pu être préparé.', 'request_id' => 0 );
    }

    if ( ! class_exists( 'ACDC_Sig_Core' ) || ! class_exists( 'ACDC_Sig_Email' ) ) {
      return array( 'created' => false, 'message' => 'Le module de signature électronique n\'est pas disponible.', 'request_id' => 0 );
    }

    $doc_type = 'Entreprise' === ( isset( $contract->commanditaire_type ) ? (string) $contract->commanditaire_type : '' ) ? 'convention' : 'contrat';
    $sig_core = new ACDC_Sig_Core();
    $sig_core->init_tables();
    $request_id = $sig_core->create_signature_request( array(
      'signer_name' => ! empty( $recipient['signatory_name'] ) ? $recipient['signatory_name'] : $recipient['to_name'],
      'signer_email' => $to,
      'signer_role' => 'Signataire convention / contrat',
      'doc_type' => $doc_type,
      'sig_level' => ACDC_Sig_Core::LEVEL_RENFORCE,
      'doc_url' => $package['contract_file']['url'],
      'doc_path' => $package['contract_file']['path'],
      'notes' => wp_json_encode( array(
        'entity_type' => 'registration_contract',
        'contract_id' => (int) $contract->id,
        'signed_delivery_email' => 'contact@davidcontal.com',
      ) ),
    ) );

    if ( ! $request_id ) {
      return array( 'created' => false, 'message' => 'La demande de signature électronique n\'a pas pu être créée.', 'request_id' => 0 );
    }

    $sig_email = new ACDC_Sig_Email( $sig_core );
    $sig_email->send_signature_email( $request_id );

    global $wpdb;
    $wpdb->update(
      $this->registration_contract_table,
      array(
        'signature_request_id' => (int) $request_id,
        'signature_status' => 'envoyée',
        'signature_sent_at' => $this->now_mysql(),
        'updated_at' => $this->now_mysql(),
      ),
      array( 'id' => (int) $contract->id )
    );

    return array( 'created' => true, 'message' => 'Demande de signature électronique envoyée.', 'request_id' => (int) $request_id );
  }


  public function handle_registration_contract_signature_completed( $request_id, $request = null ) {
    $request_id = absint( $request_id );
    if ( ! $request_id || ! class_exists( 'ACDC_Sig_Core' ) ) {
      return;
    }

    global $wpdb;
    if ( ! $request || empty( $request->signed_doc_url ) ) {
      $sig_core = new ACDC_Sig_Core();
      $sig_core->init_tables();
      $request = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$sig_core->table_requests} WHERE id = %d", $request_id ) );
    }
    if ( ! $request ) {
      return;
    }

    $notes = json_decode( isset( $request->notes ) ? (string) $request->notes : '', true );
    if ( ! is_array( $notes ) || 'registration_contract' !== ( $notes['entity_type'] ?? '' ) ) {
      return;
    }

    $contract_id = ! empty( $notes['contract_id'] ) ? absint( $notes['contract_id'] ) : 0;
    if ( ! $contract_id ) {
      return;
    }

    $signed_url = ! empty( $request->signed_doc_url ) ? esc_url_raw( (string) $request->signed_doc_url ) : '';
    $signed_path = ! empty( $request->signed_doc_path ) ? sanitize_text_field( (string) $request->signed_doc_path ) : '';

    $wpdb->update(
      $this->registration_contract_table,
      array(
        'signature_request_id' => (int) $request_id,
        'signature_status' => 'completed',
        'signature_completed_at' => $this->now_mysql(),
        'signed_document_url' => $signed_url,
        'signed_document_path' => $signed_path,
        'document_url' => $signed_url ? $signed_url : null,
        'document_path' => $signed_path ? $signed_path : null,
        'updated_at' => $this->now_mysql(),
      ),
      array( 'id' => $contract_id )
    );

    // ACDC 3.23.2 — Convention signée → dossier lié passe à confirme.
    $reg_id = $this->find_registration_id_for_contract( $contract_id );
    if ( $reg_id ) {
      $this->advance_registration_workflow( $reg_id, 'confirme' );
    }

    $delivery_email = ! empty( $notes['signed_delivery_email'] ) ? sanitize_email( (string) $notes['signed_delivery_email'] ) : '';
    if ( '' !== $delivery_email && is_email( $delivery_email ) && '' !== $signed_path && file_exists( $signed_path ) ) {
      $branding = $this->get_branding_options();
      $from_name = ! empty( $branding['company_name'] ) ? (string) $branding['company_name'] : get_bloginfo( 'name' );
      $subject = 'Convention / contrat signé — #' . $contract_id;
      $body = '<p>Bonjour,</p><p>Vous trouverez en pièce jointe la convention / le contrat signé.</p>';
      $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . sanitize_text_field( $from_name ) . ' <' . sanitize_email( get_option( 'admin_email' ) ) . '>',
      );
        /* ACDC 3.25.161 — Attribution d'archive : sans ces en-têtes, l'envoi
           s'affiche « plugin / wp_mail » dans l'archive, sans module identifiable. */
        $headers[] = 'X-ACDC-Source-Module: dossiers';
        $headers[] = 'X-ACDC-Source-Action: signed_delivery';
        $headers[] = 'X-ACDC-Email-Category: dossiers';
      wp_mail( $delivery_email, $subject, $body, $headers, array( $signed_path ) );
    }
  }


  private function get_registration_contracts( $search = '' ) {
    global $wpdb;
    $where = '1=1';
    $args = array();
    if ( '' !== trim( (string) $search ) ) {
      $where .= ' AND (title LIKE %s OR formation_title LIKE %s OR commanditaire_type LIKE %s)';
      $like = '%' . $wpdb->esc_like( trim( (string) $search ) ) . '%';
      $args = array( $like, $like, $like );
    }
    $sql = "SELECT * FROM {$this->registration_contract_table} WHERE {$where} ORDER BY updated_at DESC, id DESC";
    return empty( $args ) ? $wpdb->get_results( $sql ) : $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
  }


  private function get_registration_contract( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->registration_contract_table} WHERE id = %d", $id ) );
  }



  private function get_registration_contract_related_registration( $contract ) {
    if ( ! $contract || empty( $contract->id ) ) {
      return null;
    }
    global $wpdb;
    $registration = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->training_registration_table} WHERE autofill_contract_id = %d ORDER BY updated_at DESC, id DESC LIMIT 1", (int) $contract->id ) );
    if ( $registration ) {
      return $registration;
    }
    /* ACDC 3.25.159 — REPLI SUPPRIMÉ, ET C'ÉTAIT LE PLUS CONTAMINANT DE TOUS.
       À défaut d'inscription propre, cette fonction reprenait LA DERNIÈRE
       INSCRIPTION DE LA MÊME FORMATION, quel que soit son client. Une seule ligne
       étrangère suffisait à empoisonner tout l'affichage de la convention : son
       company_id devenait le commanditaire (raison sociale, siège, code postal,
       ville, représentant) et son learner_id devenait l'apprenant à former — d'où
       un apprenant listé sur une convention qui n'en comportait aucun.
       L'écriture, elle, était correcte : seule la lecture était fausse, ce qui
       explique que la fiche en édition affichait le bon nom et la liste un autre.
       Une convention n'a d'inscription que la sienne. */
    return null;
  }


  private function get_registration_contract_learners( $contract, $registration = null ) {
    global $wpdb;
    $learners = array();
    $ids = array();
    if ( $registration ) {
      if ( ! empty( $registration->learner_id ) ) {
        $ids[] = (int) $registration->learner_id;
      }
      if ( ! empty( $registration->learner_ids ) ) {
        foreach ( explode( ',', (string) $registration->learner_ids ) as $raw_id ) {
          $id = absint( trim( $raw_id ) );
          if ( $id ) {
            $ids[] = $id;
          }
        }
      }
    }
    if ( $contract && ! empty( $contract->learner_ids ) ) {
      foreach ( explode( ',', (string) $contract->learner_ids ) as $raw_id ) {
        $id = absint( trim( $raw_id ) );
        if ( $id ) {
          $ids[] = $id;
        }
      }
    }
    $ids = array_values( array_unique( array_filter( $ids ) ) );
    foreach ( $ids as $learner_id ) {
      $learner = $this->get_learner( $learner_id );
      if ( $learner ) {
        $learners[] = $learner;
      }
    }
    $fallback_company_id = 0;
    if ( $registration && ! empty( $registration->company_id ) ) {
      $fallback_company_id = (int) $registration->company_id;
    } elseif ( $contract && ! empty( $contract->company_id ) ) {
      $fallback_company_id = (int) $contract->company_id;
    }
    /* ACDC 3.25.159 — REPLI SUPPRIMÉ. Faute d'apprenant nommé, la convention
       listait jusqu'à DOUZE apprenants du commanditaire comme « apprenants à
       former ». Sur un document contractuel, ces noms ne sont pas une suggestion :
       ils engagent. Une convention ne liste que les apprenants qu'elle nomme ;
       aucun apprenant nommé signifie aucun apprenant, pas « tous ceux de la
       maison ». Ce repli restait sans effet ici — la convention de recette n'avait
       pas de commanditaire — mais il produisait la même erreur dès qu'un
       commanditaire était rattaché. */
    unset( $fallback_company_id );
    return $learners;
  }


  private function get_registration_contract_related_context( $contract ) {
    $registration = $this->get_registration_contract_related_registration( $contract );
    $prospect = null;
    if ( $contract && ! empty( $contract->source_prospect_id ) ) {
      $prospect = $this->get_prospect( (int) $contract->source_prospect_id );
    }
    $company = null;
    if ( $registration && ! empty( $registration->company_id ) ) {
      $company = $this->get_company( (int) $registration->company_id );
    }
    if ( ! $company && $contract && ! empty( $contract->company_id ) ) {
      $company = $this->get_company( (int) $contract->company_id );
    }
    $formation = null;
    if ( ! empty( $contract->formation_id ) ) {
      $formation = $this->get_formation( (int) $contract->formation_id );
    }
    if ( ! $formation && $registration && ! empty( $registration->formation_id ) ) {
      $formation = $this->get_formation( (int) $registration->formation_id );
    }

    $session = null;
    if ( $formation ) {
      global $wpdb;
      if ( ! empty( $contract->start_date ) && ! empty( $contract->end_date ) ) {
        $session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->session_table} WHERE formation_id = %d AND start_date = %s AND end_date = %s ORDER BY id DESC LIMIT 1", (int) $formation->id, (string) $contract->start_date, (string) $contract->end_date ) );
      }
      /* ACDC 3.25.160 — Repli supprimé : à défaut de séance aux dates de la
         convention, il prenait LA DERNIÈRE SÉANCE DE LA FORMATION, celle de
         n'importe quel client. Cette séance étrangère alimentait ensuite un
         apprenant étranger, dont le company_id redevenait le commanditaire — c'est
         la seconde chaîne d'emprunt, celle qui subsistait après la première série
         de correctifs et qui expliquait le siège, le SIRET, le représentant et la
         qualité encore fuités. */
    }
    $learners = $this->get_registration_contract_learners( $contract, $registration );

    // Proposition commerciale liée — pour récupérer formation_location
    $proposal = null;
    if ( $contract && ! empty( $contract->source_prospect_id ) ) {
      global $wpdb;
      $proposal_table = $wpdb->prefix . 'acdc_of_proposals';
      $proposal = $wpdb->get_row( $wpdb->prepare(
        "SELECT formation_location FROM {$proposal_table} WHERE source_prospect_id = %d AND formation_location IS NOT NULL AND formation_location <> '' ORDER BY id DESC LIMIT 1",
        (int) $contract->source_prospect_id
      ) );
    }

    $learners = $this->get_registration_contract_learners( $contract, $registration );
    return array(
      'registration' => $registration,
      'prospect'     => $prospect,
      'company'      => $company,
      'formation'    => $formation,
      'session'      => $session,
      'learners'     => $learners,
      'proposal'     => $proposal,
    );
  }


  private function get_registration_contract_display_company_name( $contract, $context = array() ) {
    $registration = isset( $context['registration'] ) ? $context['registration'] : null;
    $company = isset( $context['company'] ) ? $context['company'] : null;
    $prospect = isset( $context['prospect'] ) ? $context['prospect'] : null;
    if ( $company && ! empty( $company->name ) ) {
      return trim( (string) $company->name );
    }
    if ( $registration && ! empty( $registration->company_label ) ) {
      return trim( (string) $registration->company_label );
    }
    if ( $prospect && ! empty( $prospect->company_name ) ) {
      return trim( (string) $prospect->company_name );
    }
    return '';
  }


  private function get_registration_contract_display_person_name( $contract, $context = array() ) {
    $learners = isset( $context['learners'] ) && is_array( $context['learners'] ) ? $context['learners'] : array();
    $learner_names = array();
    foreach ( $learners as $learner ) {
      $first_name = trim( isset( $learner->first_name ) ? (string) $learner->first_name : '' );
      $last_name = trim( isset( $learner->usage_last_name ) && '' !== trim( (string) $learner->usage_last_name ) ? (string) $learner->usage_last_name : ( isset( $learner->last_name ) ? (string) $learner->last_name : '' ) );
      $name = trim( $first_name . ' ' . $last_name );
      if ( '' !== $name ) {
        $learner_names[] = $name;
      }
    }
    $learner_names = array_values( array_unique( $learner_names ) );
    if ( ! empty( $learner_names ) ) {
      if ( 1 === count( $learner_names ) ) {
        return $learner_names[0];
      }
      return $learner_names[0] . ' +' . ( count( $learner_names ) - 1 );
    }

    $registration = isset( $context['registration'] ) ? $context['registration'] : null;
    if ( $registration ) {
      foreach ( array( 'learner_name', 'learner_label', 'contact_name' ) as $field ) {
        if ( ! empty( $registration->{$field} ) ) {
          return trim( (string) $registration->{$field} );
        }
      }
    }

    return '';
  }


  private function get_registration_contract_display_signatory_name( $contract, $context = array() ) {
    $company = isset( $context['company'] ) ? $context['company'] : null;
    if ( $company ) {
      $contact_bits = array_filter(
        array(
          trim( isset( $company->signer_first_name ) ? (string) $company->signer_first_name : ( isset( $company->signatory_first_name ) ? (string) $company->signatory_first_name : '' ) ),
          trim( isset( $company->signer_last_name ) ? (string) $company->signer_last_name : ( isset( $company->signatory_last_name ) ? (string) $company->signatory_last_name : '' ) ),
        ),
        static function( $value ) {
          return '' !== $value;
        }
      );
      if ( ! empty( $contact_bits ) ) {
        return trim( implode( ' ', $contact_bits ) );
      }
      if ( ! empty( $company->contact_name ) ) {
        return trim( (string) $company->contact_name );
      }
    }

    $prospect = isset( $context['prospect'] ) ? $context['prospect'] : null;
    if ( $prospect ) {
      $contact_bits = array_filter(
        array(
          trim( isset( $prospect->signer_first_name ) ? (string) $prospect->signer_first_name : '' ),
          trim( isset( $prospect->signer_last_name ) ? (string) $prospect->signer_last_name : '' ),
        ),
        static function( $value ) {
          return '' !== $value;
        }
      );
      if ( ! empty( $contact_bits ) ) {
        return trim( implode( ' ', $contact_bits ) );
      }
    }

    $registration = isset( $context['registration'] ) ? $context['registration'] : null;
    if ( $registration && ! empty( $registration->contact_name ) ) {
      return trim( (string) $registration->contact_name );
    }

    return $this->get_registration_contract_display_person_name( $contract, $context );
  }


  private function get_registration_contract_commanditaire_display_data( $contract, $context = array() ) {
    $to_scalar_text = static function( $value ) {
      if ( is_array( $value ) ) {
        if ( isset( $value['text'] ) ) {
          $value = $value['text'];
        } else {
          $chunks = array();
          array_walk_recursive(
            $value,
            static function( $item ) use ( &$chunks ) {
              if ( is_scalar( $item ) ) {
                $text = trim( wp_strip_all_tags( (string) $item ) );
                if ( '' !== $text ) {
                  $chunks[] = $text;
                }
              }
            }
          );
          $value = implode( ' ', $chunks );
        }
      }
      if ( is_object( $value ) ) {
        if ( method_exists( $value, '__toString' ) ) {
          $value = (string) $value;
        } else {
          $value = '';
        }
      }
      return trim( wp_strip_all_tags( is_scalar( $value ) ? (string) $value : '' ) );
    };

    $commanditaire_type = isset( $contract->commanditaire_type ) ? $to_scalar_text( $contract->commanditaire_type ) : '';
    $company_name = $to_scalar_text( $this->get_registration_contract_display_company_name( $contract, $context ) );
    $signatory_name = $to_scalar_text( $this->get_registration_contract_display_signatory_name( $contract, $context ) );
    $person_name = $to_scalar_text( $this->get_registration_contract_display_person_name( $contract, $context ) );

    $is_person = 'Particulier' === $commanditaire_type || '' === $company_name;
    $primary = $is_person ? $person_name : $company_name;
    $secondary = $is_person ? '' : $signatory_name;

    if ( '' === $primary ) {
      $primary = '' !== $person_name ? $person_name : ( '' !== $company_name ? $company_name : ( '' !== $signatory_name ? $signatory_name : ( '' !== $commanditaire_type ? $commanditaire_type : '—' ) ) );
    }

    if ( $primary === $secondary ) {
      $secondary = '';
    }

    $pdf_lines = array(
      array(
        'text'  => $to_scalar_text( $primary ),
        'font'  => 'Helvetica-Bold',
        'size'  => 8.5,
        'color' => '#1f2937',
      ),
    );

    if ( '' !== $secondary ) {
      $pdf_lines[] = array(
        'text'  => $to_scalar_text( $secondary ),
        'font'  => 'Helvetica',
        'size'  => 7.4,
        'color' => '#4b5563',
      );
    }

    $primary = $to_scalar_text( $primary );
    $secondary = $to_scalar_text( $secondary );

    return array(
      'is_person'       => $is_person,
      'primary'         => $primary,
      'secondary'       => $secondary,
      'flat'            => '' !== $secondary ? $primary . ' — ' . $secondary : $primary,
      'summary'         => $primary,
      'pdf_lines'       => $pdf_lines,
      'signature_label' => '' !== $secondary ? $primary . ' - ' . $secondary : $primary,
    );
  }


  private function get_registration_contract_display_commanditaire_name( $contract, $context = array() ) {
    $display = $this->get_registration_contract_commanditaire_display_data( $contract, $context );
    return ! empty( $display['flat'] ) ? (string) $display['flat'] : '—';
  }


  private function get_registration_contract_display_file_name( $contract, $document = array() ) {
    if ( ! empty( $document['path'] ) ) {
      return basename( (string) $document['path'] );
    }
    if ( ! empty( $document['url'] ) ) {
      $path = wp_parse_url( (string) $document['url'], PHP_URL_PATH );
      if ( $path ) {
        return basename( $path );
      }
    }
    if ( ! empty( $contract->title ) ) {
      return sanitize_file_name( remove_accents( (string) $contract->title ) ) . '.pdf';
    }
    return 'convention-de-formation.pdf';
  }


  private function get_registration_contract_document_info( $contract ) {
    $info = array(
      'url'  => '',
      'path' => '',
      'size' => '',
      'label' => 'Convention de formation',
    );
    if ( ! $contract ) {
      return $info;
    }
    $url = isset( $contract->document_url ) ? trim( (string) $contract->document_url ) : '';
    $path = isset( $contract->document_path ) ? trim( (string) $contract->document_path ) : '';
    if ( '' === $path && '' !== $url ) {
      $path = $this->get_local_path_from_upload_url( $url );
    }
    if ( '' === $url ) {
      global $wpdb;
      $like_title = '%' . $wpdb->esc_like( (string) $contract->formation_title ) . '%';
      $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->document_table} WHERE (document_type LIKE %s OR title LIKE %s) ORDER BY id DESC LIMIT 10", '%Convention%', '%Convention%' ) );
      /* ACDC 3.25.159 — Ce repli retrouve un document par RESSEMBLANCE DE TITRE.
         Deux conventions portant la même formation pour deux clients différents se
         ressemblent forcément : sans contrôle du commanditaire, on risquait de
         rattacher à ce dossier la convention de quelqu'un d'autre. On exige donc
         que le document appartienne au même commanditaire — ou à aucun, si le
         dossier n'en a pas encore. */
      $doc_company_id = isset( $contract->company_id ) ? (int) $contract->company_id : 0;
      foreach ( (array) $rows as $row ) {
        $row_company_id = isset( $row->company_id ) ? (int) $row->company_id : 0;
        if ( $row_company_id !== $doc_company_id ) {
          continue;
        }
        $match = false;
        if ( ! empty( $contract->formation_title ) && false !== stripos( (string) $row->title, (string) $contract->formation_title ) ) {
          $match = true;
        }
        if ( ! $match && ! empty( $contract->title ) && false !== stripos( (string) $row->title, (string) $contract->title ) ) {
          $match = true;
        }
        if ( ! $match ) {
          continue;
        }
        $url = ! empty( $row->file_url ) ? (string) $row->file_url : '';
        $path = ! empty( $row->file_path ) ? (string) $row->file_path : '';
        break;
      }
      unset($like_title);
    }
    $info['url'] = $url;
    $info['path'] = $path;
    if ( '' !== $path && file_exists( $path ) ) {
      $size = filesize( $path );
      if ( $size ) {
        if ( $size >= 1048576 ) {
          $info['size'] = number_format_i18n( $size / 1048576, 1 ) . ' MB';
        } elseif ( $size >= 1024 ) {
          $info['size'] = number_format_i18n( $size / 1024, 0 ) . ' KB';
        } else {
          $info['size'] = $size . ' octets';
        }
      }
    }
    return $info;
  }


  private function get_registration_contract_download_url( $contract, $mode = 'attachment' ) {
    if ( ! $contract || empty( $contract->id ) ) {
      return '';
    }
    $mode = 'inline' === $mode ? 'inline' : 'attachment';
    return wp_nonce_url( admin_url( 'admin-post.php?action=acdc_download_registration_contract_document&contract_id=' . (int) $contract->id . '&mode=' . rawurlencode( $mode ) ), 'acdc_download_registration_contract_document_' . (int) $contract->id );
  }


  private function get_registration_contract_edit_modal_title( $contract, $context = array() ) {
    $company = $this->get_registration_contract_display_company_name( $contract, $context );
    return 'Modifier une convention / un contrat de formation : ' . $contract->commanditaire_type . ' : ' . $company;
  }


  private function get_registration_contract_view_modal_title( $contract, $context = array() ) {
    $company = $this->get_registration_contract_display_company_name( $contract, $context );
    return 'Voir une convention / un contrat de formation : ' . $contract->commanditaire_type . ' : ' . $company;
  }


  private function get_registration_contract_signature_stack( $contract, $document = array() ) {
    $lines      = array();
    $sig_status = isset( $contract->signature_status ) ? (string) $contract->signature_status : '';
    $has_doc    = ! empty( $document['url'] );

    // Ligne 1 : statut réel de signature
    if ( in_array( $sig_status, array( 'completed', 'signed' ), true ) ) {
      $lines[] = '• Signé';
    } elseif ( in_array( $sig_status, array( 'pending', 'sent', 'envoyée' ), true ) ) {
      $lines[] = '• En attente';
    } elseif ( $has_doc ) {
      $lines[] = '• Vierge';
    } else {
      $lines[] = '—';
    }

    // Ligne 2 : mode électronique vs manuel
    // Source de vérité prioritaire : signature_request_id renseigné = électronique
    $has_sig_request = ! empty( $contract->signature_request_id ) && (int) $contract->signature_request_id > 0;
    $mode = isset( $contract->generate_mode ) ? strtolower( (string) $contract->generate_mode ) : '';
    $is_electronic = $has_sig_request
      || false !== strpos( $mode, 'esign' )
      || false !== strpos( $mode, 'electron' );
    $lines[] = $is_electronic ? 'Électronique' : 'Manuelle';

    // Ligne 3 : date de signature ou de mise à jour
    $date_src = ! empty( $contract->signature_completed_at ) ? $contract->signature_completed_at
              : ( ! empty( $contract->updated_at ) ? $contract->updated_at : '' );
    if ( $date_src ) {
      /* +2h — La date est stockée en heure locale (current_time). wp_date(strtotime()) la
         relisait comme de l'UTC et ré-appliquait l'offset (+2h → bascule au lendemain pour
         un événement après 22h). mysql2date() la traite correctement comme locale. */
      $lines[] = mysql2date( 'j F Y à H\hi', (string) $date_src );
    }
    return $lines;
  }



  /**
   * Retourne le badge HTML de statut de signature d'une convention.
   * Vert = signé, Bleu = en attente, Rouge = délai dépassé (>7j sans réponse), Gris = vierge.
   */
  private function render_registration_contract_signature_badge( $contract ) {
    $sig_status = isset( $contract->signature_status ) ? (string) $contract->signature_status : '';
    $document   = $this->get_registration_contract_document_info( $contract );
    $has_doc    = ! empty( $document['url'] );

    // Déterminer le mode (électronique vs manuel)
    $has_sig_req   = ! empty( $contract->signature_request_id ) && (int) $contract->signature_request_id > 0;
    $mode          = isset( $contract->generate_mode ) ? strtolower( (string) $contract->generate_mode ) : '';
    $is_electronic = $has_sig_req || false !== strpos( $mode, 'esign' ) || false !== strpos( $mode, 'electron' );
    $mode_label    = $is_electronic ? 'Électronique' : 'Manuelle';

    // Date
    $date_src = ! empty( $contract->signature_completed_at ) ? $contract->signature_completed_at
              : ( ! empty( $contract->updated_at ) ? $contract->updated_at : '' );
    $date_label = $date_src ? mysql2date( 'j M Y', (string) $date_src ) : ''; // +2h : voir note ci-dessus (heure locale, pas d'offset ré-appliqué).

    $sent_at = ! empty( $contract->signature_sent_at ) ? $contract->signature_sent_at : '';
    $is_overdue = false;
    if ( in_array( $sig_status, array( 'pending', 'sent', 'envoyée' ), true ) && $sent_at ) {
      $is_overdue = ( current_time( 'timestamp' ) - strtotime( $sent_at ) ) > ( 7 * DAY_IN_SECONDS ); // ACDC 3.25.113 — base temps WP homogène.
    }

    // Badge principal
    if ( in_array( $sig_status, array( 'completed', 'signed' ), true ) ) {
      $badge_style = 'background:#d1fae5;color:#065f46;';
      $label       = '✓ Signé';
    } elseif ( $is_overdue ) {
      $badge_style = 'background:#fee2e2;color:#991b1b;';
      $label       = '⚠ Délai dépassé';
    } elseif ( in_array( $sig_status, array( 'pending', 'sent', 'envoyée' ), true ) ) {
      $badge_style = 'background:#dbeafe;color:#1e40af;';
      $label       = '⏳ En attente';
    } elseif ( $has_doc ) {
      $badge_style = 'background:#f3f4f6;color:#4b5563;';
      $label       = '— Vierge';
    } else {
      return '<span style="color:#9ca3af;">—</span>';
    }

    $out  = '<span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;' . $badge_style . '">' . esc_html( $label ) . '</span>';
    $out .= '<div style="font-size:11px;color:#6b7280;margin-top:3px;">' . esc_html( $mode_label ) . '</div>';
    if ( $date_label ) {
      $out .= '<div style="font-size:11px;color:#9ca3af;">' . esc_html( $date_label ) . '</div>';
    }
    return $out;
  }

  /**
   * Retourne les infos d'analyse du besoin liée à une convention.
   * Retourne array( 'html' => string, 'sent' => bool, 'sent_at' => string )
   */
  private function get_registration_contract_nad_info( $contract_id ) {
    global $wpdb;
    $contract_id = absint( $contract_id );
    if ( ! $contract_id ) {
      return array( 'html' => '<span style="color:#9ca3af;">—</span>', 'sent' => false, 'sent_at' => '' );
    }

    // ACDC 3.25.00 — Élargissement : dossier_id OU apprenant_id des apprenants liés OU entreprise_id
    $contract = $this->get_registration_contract( $contract_id );
    $learner_ids_raw = ( $contract && ! empty( $contract->learner_ids ) ) ? (string) $contract->learner_ids : '';
    $company_id_raw  = ( $contract && ! empty( $contract->company_id ) ) ? (int) $contract->company_id : 0;
    $learner_ids = array();
    foreach ( explode( ',', $learner_ids_raw ) as $raw ) {
      $id = absint( trim( $raw ) );
      if ( $id ) { $learner_ids[] = $id; }
    }
    $learner_ids = array_values( array_unique( array_filter( $learner_ids ) ) );

    // Construire la clause WHERE élargie
    $clauses = array( $wpdb->prepare( 'dossier_id = %d', $contract_id ) );
    if ( ! empty( $learner_ids ) ) {
      $placeholders = implode( ',', array_fill( 0, count( $learner_ids ), '%d' ) );
      $clauses[] = $wpdb->prepare( "apprenant_id IN ($placeholders)", ...$learner_ids );
    }
    if ( $company_id_raw ) {
      $clauses[] = $wpdb->prepare( 'entreprise_id = %d', $company_id_raw );
    }
    $where = implode( ' OR ', $clauses );

    $analyses = $wpdb->get_results(
      "SELECT sent_at, statut FROM {$this->need_analysis_table} WHERE ( $where ) AND is_model = 0 ORDER BY id ASC"
    );

    if ( empty( $analyses ) ) {
      return array( 'html' => '<span style="color:#9ca3af;">—</span>', 'sent' => false, 'sent_at' => '' );
    }

    // Au moins une NAD envoyée ?
    $sent_one = false;
    $first_sent_at = '';
    foreach ( $analyses as $a ) {
      if ( ! empty( $a->sent_at ) ) {
        $sent_one = true;
        if ( '' === $first_sent_at ) { $first_sent_at = $a->sent_at; }
      }
    }

    if ( $sent_one ) {
      $date_f = wp_date( 'j M Y', strtotime( $first_sent_at ) );
      $count  = count( $analyses );
      $html   = '<span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;background:#d1fae5;color:#065f46;white-space:nowrap;">✓ Envoyé</span>'
              . '<div style="font-size:11px;color:#6b7280;margin-top:3px;">' . esc_html( $date_f ) . ( $count > 1 ? ' (' . $count . ')' : '' ) . '</div>';
    } else {
      // NAD créée mais pas encore envoyée (délai programmé)
      $html = '<span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;background:#fef3c7;color:#92400e;white-space:nowrap;">⏳ Programmé</span>';
    }

    return array( 'html' => $html, 'sent' => $sent_one, 'sent_at' => $first_sent_at );
  }

  private function get_registration_contract_kind_label( $commanditaire_type ) {
    return 'Particulier' === (string) $commanditaire_type ? 'Contrat' : 'Convention';
  }


  private function get_registration_contract_title( $commanditaire_type, $formation_title, $start_date = '' ) {
    $kind = $this->get_registration_contract_kind_label( $commanditaire_type );
    $parts = array( $kind );
    if ( '' !== trim( (string) $formation_title ) ) {
      $parts[] = trim( (string) $formation_title );
    }
    if ( ! empty( $start_date ) ) {
      $ts = strtotime( (string) $start_date );
      if ( $ts ) {
        $parts[] = wp_date( 'd/m/Y', $ts );
      }
    }
    return implode( ' - ', $parts );
  }


  private function get_training_registration_default_record() {
    return (object) array(
      'id' => 0,
      'title' => '',
      'belongs_to_group' => '',
      'autofill_contract_id' => 0,
      'autofill_contract_label' => '',
      'group_id' => 0,
      'group_label' => '',
      'learner_id' => 0,
      'learner_label' => '',
      'learner_ids' => '',
      'learners_label' => '',
      'company_id' => 0,
      'company_label' => '',
      'formation_id' => 0,
      'formation_title' => '',
      'extranet_access' => 1,
      'price_ht' => '',
      'transport_fees_enabled' => 0,
      'meal_fees_enabled' => 0,
      'trainer_id' => 0,
      'trainer_label' => '',
      'convocation_document_url' => '',
      'convocation_document_path' => '',
      'positioning_result_document_url' => '',
      'positioning_result_document_path' => '',
      'mid_survey_document_url' => '',
      'mid_survey_document_path' => '',
      'hot_survey_document_url' => '',
      'hot_survey_document_path' => '',
      'cold_survey_document_url' => '',
      'cold_survey_document_path' => '',
      'evaluation_result_document_url' => '',
      'evaluation_result_document_path' => '',
      'completion_certificate_document_url' => '',
      'completion_certificate_document_path' => '',
      'end_training_certificate_document_url' => '',
      'end_training_certificate_document_path' => '',
      'is_draft' => 0,
    );
  }


  private function get_training_registration_subject_label( $belongs_to_group, $group_label, $learner_label, $learners_label ) {
    if ( 'Oui' === $belongs_to_group ) {
      if ( $group_label ) {
        return $group_label;
      }
      return $learners_label ? $learners_label : 'Groupe';
    }
    return $learner_label ? $learner_label : 'Apprenant';
  }


  private function get_training_registration_title( $belongs_to_group, $group_label, $learner_label, $learners_label, $formation_title ) {
    $subject = $this->get_training_registration_subject_label( $belongs_to_group, $group_label, $learner_label, $learners_label );
    $formation_title = $formation_title ? $formation_title : 'Sans formation';
    return sprintf( 'Inscription — %s — %s', $subject, $formation_title );
  }


  private function get_training_registrations( $is_draft = null ) {
    global $wpdb;
    $sql = "SELECT * FROM {$this->training_registration_table}";
    if ( null !== $is_draft ) {
      $sql .= $wpdb->prepare( ' WHERE is_draft = %d', $is_draft ? 1 : 0 );
    }
    $sql .= ' ORDER BY updated_at DESC, id DESC';
    return $wpdb->get_results( $sql );
  }


  private function get_training_registration( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->training_registration_table} WHERE id = %d", $id ) );
  }



private function get_contract_pdf_context( $request ) {
  global $wpdb;

  $contract_id = isset( $request['contract_id'] ) ? absint( $request['contract_id'] ) : 0;
  $company_id  = isset( $request['contract_company_id'] ) ? absint( $request['contract_company_id'] ) : 0;
  $formation_id = isset( $request['contract_formation_id'] ) ? absint( $request['contract_formation_id'] ) : 0;
  $session_id  = isset( $request['contract_session_id'] ) ? absint( $request['contract_session_id'] ) : 0;
  $learner_id  = isset( $request['contract_learner_id'] ) ? absint( $request['contract_learner_id'] ) : 0;

  $contract = $contract_id ? $this->get_registration_contract( $contract_id ) : null;
  $related = array();
  if ( $contract ) {
    $related = $this->get_registration_contract_related_context( $contract );
    if ( ! $company_id && ! empty( $related['company']->id ) ) {
      $company_id = (int) $related['company']->id;
    }
    if ( ! $formation_id && ! empty( $related['formation']->id ) ) {
      $formation_id = (int) $related['formation']->id;
    }
    if ( ! $session_id && ! empty( $related['session']->id ) ) {
      $session_id = (int) $related['session']->id;
    }
    if ( ! $learner_id && ! empty( $related['learners'][0]->id ) ) {
      $learner_id = (int) $related['learners'][0]->id;
    }
  }

  $learner = $learner_id ? $this->get_learner( $learner_id ) : null;
  $session = $session_id ? $this->get_session( $session_id ) : null;
  if ( ! $session && $learner && ! empty( $learner->session_id ) ) {
    $session = $this->get_session( (int) $learner->session_id );
  }

  if ( ! $company_id && $session && ! empty( $session->company_id ) ) {
    $company_id = (int) $session->company_id;
  }
  if ( ! $company_id && $learner && ! empty( $learner->company_id ) ) {
    $company_id = (int) $learner->company_id;
  }

  if ( ! $formation_id && $session && ! empty( $session->formation_id ) ) {
    $formation_id = (int) $session->formation_id;
  }

  $company  = $company_id ? $this->get_company( $company_id ) : null;
  $formation = $formation_id ? $this->get_formation( $formation_id ) : null;

  /* ACDC 3.25.158 — REPLIS SUPPRIMÉS : ils prenaient la PREMIÈRE LIGNE du
     répertoire quand le lien manquait. Une convention sans commanditaire rattaché
     portait donc le siège social, le SIRET et le représentant légal d'un AUTRE
     client — seule la raison sociale venait du bon dossier. Ce n'est pas un défaut
     d'affichage : le document est faux et divulgue les données d'un tiers.
     Laisser ces variables à null est la bonne réponse : les replis légitimes plus
     bas (prospect, puis apprenant) reprennent la main, et tous les usages en aval
     sont protégés par ?? / isset(). Un champ vide est récupérable ; un champ
     rempli avec les données de quelqu'un d'autre ne l'est pas. */
  /* ACDC 3.25.160 — Même repli, second exemplaire : cette boucle retenait la
     première séance de la même formation, sans exiger qu'elle appartienne au
     dossier. Elle servait ensuite à désigner un apprenant, puis un commanditaire.
     Une convention n'emprunte pas la séance d'un autre dossier. */
  if ( ! $learner ) {
    global $wpdb;
    if ( $session ) {
      $learner = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->learner_table} WHERE session_id = %d ORDER BY created_at DESC, id DESC LIMIT 1", $session->id ) );
      if ( $learner ) {
        $learner = $this->get_learner( $learner->id );
      }
    }
    if ( ! $learner && $company ) {
      $learner = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->learner_table} WHERE company_id = %d ORDER BY created_at DESC, id DESC LIMIT 1", $company->id ) );
      if ( $learner ) {
        $learner = $this->get_learner( $learner->id );
      }
    }
    /* ACDC 3.25.158 — Même repli : il désignait un apprenant réel pris au hasard
       comme bénéficiaire de la convention. */
  }

  $learners = array();
  if ( $contract ) {
    $learners = ! empty( $related['learners'] ) && is_array( $related['learners'] ) ? $related['learners'] : array();
  }
  if ( empty( $learners ) && $learner ) {
    $learners = array( $learner );
  }

  $profile = $this->get_company_profile_options();
  $params  = $this->get_contract_params_options();
  $registration = isset( $related['registration'] ) ? $related['registration'] : null;
  $prospect = isset( $related['prospect'] ) ? $related['prospect'] : null;

  /* ACDC 3.25.160 — Le commanditaire ne se déduit que d'un apprenant RATTACHÉ à
     cette convention. Auparavant, n'importe quel apprenant retrouvé au fil des
     replis suffisait à réintroduire son entreprise comme bénéficiaire du document. */
  $learner_is_attached = false;
  if ( $learner && ! empty( $related['learners'] ) && is_array( $related['learners'] ) ) {
    foreach ( $related['learners'] as $attached ) {
      if ( ! empty( $attached->id ) && (int) $attached->id === (int) $learner->id ) {
        $learner_is_attached = true;
        break;
      }
    }
  }
  if ( ! $company && $learner_is_attached && ! empty( $learner->company_id ) ) {
    $company = $this->get_company( (int) $learner->company_id );
  }

  $registration_company_name = $registration && ! empty( $registration->company_label ) ? trim( (string) $registration->company_label ) : '';
  $registration_contact_name = $registration && ! empty( $registration->contact_name ) ? trim( (string) $registration->contact_name ) : '';
  $prospect_company_name = $prospect && ! empty( $prospect->company_name ) ? trim( (string) $prospect->company_name ) : '';
  $prospect_signer_name = $prospect ? trim( (string) ( ( $prospect->signer_first_name ?? '' ) . ' ' . ( $prospect->signer_last_name ?? '' ) ) ) : '';
  $learner_full_name = $learner ? trim( (string) $learner->first_name . ' ' . (string) ( ! empty( $learner->usage_last_name ) ? $learner->usage_last_name : $learner->last_name ) ) : '';

  if ( $company && ! empty( $company->name ) ) {
    $commanditaire_name = trim( (string) $company->name );
  } elseif ( '' !== $prospect_company_name ) {
    $commanditaire_name = $prospect_company_name;
  } elseif ( '' !== $registration_company_name ) {
    $commanditaire_name = $registration_company_name;
  } elseif ( '' !== $learner_full_name ) {
    $commanditaire_name = $learner_full_name;
  } else {
    $commanditaire_name = 'Commanditaire non renseigné';
  }

  // ACDC 3.21.08 : pour les types individuels (Apprenant/Particulier/Salarié),
  // l'adresse de la fiche apprenant prime sur celle du prospect source
  $cmd_type_for_addr = isset( $contract->commanditaire_type ) ? (string) $contract->commanditaire_type : '';
  $is_individual_cmd = in_array( $cmd_type_for_addr, array( 'Particulier', 'Apprenant', 'Salarié' ), true );
  if ( $company ) {
    $commanditaire_address = trim( (string) ( $company->address ?? '' ) );
  } elseif ( $is_individual_cmd ) {
    $commanditaire_address = trim( (string) ( $learner->address ?? ( $prospect->address ?? '' ) ) );
  } else {
    $commanditaire_address = trim( (string) ( $prospect->address ?? ( $learner->address ?? '' ) ) );
  }
  if ( '' === $commanditaire_address && $company && ! empty( $company->street ) ) {
    $commanditaire_address = trim( (string) $company->street );
  }
  $commanditaire_code_postal = trim( (string) ( $company->postal_code ?? '' ) );
  if ( '' === $commanditaire_code_postal && $company && ! empty( $company->zip ) ) {
    $commanditaire_code_postal = trim( (string) $company->zip );
  }
  if ( '' === $commanditaire_code_postal && $prospect && ! empty( $prospect->postal_code ) ) {
    $commanditaire_code_postal = trim( (string) $prospect->postal_code );
  }
  if ( '' === $commanditaire_code_postal && $learner && ! empty( $learner->postal_code ) ) {
    $commanditaire_code_postal = trim( (string) $learner->postal_code );
  }
  if ( '' === $commanditaire_code_postal && $learner && ! empty( $learner->zip ) ) {
    $commanditaire_code_postal = trim( (string) $learner->zip );
  }
  $commanditaire_ville = trim( (string) ( $company->city ?? '' ) );
  if ( '' === $commanditaire_ville && $prospect && ! empty( $prospect->city ) ) {
    $commanditaire_ville = trim( (string) $prospect->city );
  }
  if ( '' === $commanditaire_ville && $learner && ! empty( $learner->city ) ) {
    $commanditaire_ville = trim( (string) $learner->city );
  }
  $commanditaire_identification = $company->siret ?? '';
  if ( '' === $commanditaire_identification && $prospect && ! empty( $prospect->siret ) ) {
    $commanditaire_identification = trim( (string) $prospect->siret );
  }
  $commanditaire_representant = '';
  $commanditaire_qualite = '';
  if ( $company ) {
    $contact_bits = array_filter( array( $company->signer_first_name ?? ( $company->signatory_first_name ?? '' ), $company->signer_last_name ?? ( $company->signatory_last_name ?? '' ) ) );
    if ( empty( $contact_bits ) && ! empty( $company->contact_name ) ) {
      $contact_bits = array( $company->contact_name );
    }
    $commanditaire_representant = trim( implode( ' ', $contact_bits ) );
    $commanditaire_qualite = $company->legal_form ?? '';
  }
  if ( '' === $commanditaire_representant && '' !== $prospect_signer_name ) {
    $commanditaire_representant = $prospect_signer_name;
  }
  if ( '' === $commanditaire_representant && '' !== $registration_contact_name ) {
    $commanditaire_representant = $registration_contact_name;
  }
  if ( '' === $commanditaire_representant && '' !== $learner_full_name ) {
    $commanditaire_representant = $learner_full_name;
  }
  /* ACDC 3.25.158 — Sans fiche commanditaire, la qualité du signataire doit venir
     du prospect, comme son nom : sans cela le document affichait « Entreprise » là
     où il faut « Gérant », « Président », etc. */
  if ( '' === $commanditaire_qualite && $prospect && ! empty( $prospect->signer_quality ) ) {
    $commanditaire_qualite = trim( (string) $prospect->signer_quality );
  }
  if ( '' === $commanditaire_qualite && $contract && ! empty( $contract->commanditaire_type ) ) {
    $commanditaire_qualite = (string) $contract->commanditaire_type;
  }

  $learner_names = array();
  foreach ( $learners as $item ) {
    $name = trim( (string) ( $item->first_name ?? '' ) . ' ' . (string) ( ! empty( $item->usage_last_name ) ? $item->usage_last_name : ( $item->last_name ?? '' ) ) );
    if ( '' !== $name ) {
      $learner_names[] = $name;
    }
  }
  $learner_names = array_values( array_unique( $learner_names ) );
  $apprenants_count = count( $learner_names );
  $apprenants_list = implode( ', ', $learner_names );

  $raw_contract_price_ht = $contract && isset( $contract->price_ht ) ? trim( (string) $contract->price_ht ) : '';
  $raw_formation_price_ht = isset( $formation->price_ht ) ? trim( (string) $formation->price_ht ) : ( isset( $formation->price ) ? trim( (string) $formation->price ) : '' );
  $contract_price_num = (float) $this->normalize_price_number( $raw_contract_price_ht, 2 );
  $formation_price_num = (float) $this->normalize_price_number( $raw_formation_price_ht, 2 );
  $price_ht = ( '' !== $raw_contract_price_ht && $contract_price_num > 0 ) ? $raw_contract_price_ht : $raw_formation_price_ht;
  $vat_rate = $contract && '' !== (string) $contract->vat_rate ? (string) $contract->vat_rate : ( $profile['vat_rate'] ?? '20.00%' );
  $price_ht_num = (float) $this->normalize_price_number( $price_ht, 2 );
  $vat_rate_num = (float) $this->normalize_price_number( $vat_rate, 2 );
  $transport_num = $contract && ! empty( $contract->transport_fees_enabled ) ? (float) $this->normalize_price_number( $contract->transport_fees_amount_ht, 2 ) : 0.0;
  $meal_num = $contract && ! empty( $contract->meal_fees_enabled ) ? (float) $this->normalize_price_number( $contract->meal_fees_amount_ht, 2 ) : 0.0;
  $deposit_num = $contract && ! empty( $contract->deposit_enabled ) ? (float) $this->normalize_price_number( $contract->deposit_amount_ht, 2 ) : 0.0;
  $total_ht = $price_ht_num + $transport_num + $meal_num;
  $total_ttc = $total_ht * ( 1 + ( $vat_rate_num / 100 ) );
  $signed_city = ! empty( $profile['city'] ) ? (string) $profile['city'] : 'Cogolin';
  $signed_date = date_i18n( 'd/m/Y' );
  $commanditaire_display = $this->get_registration_contract_commanditaire_display_data( $contract, $related );

  return array(
    'contract'  => $contract,
    'company'  => $company,
    'formation' => $formation,
    'session'  => $session,
    'learner'  => $learner,
    'learners' => $learners,
    'profile'  => $profile,
    'params'  => $params,
    'convention_contrat' => array(
      'commanditaire_nom' => $commanditaire_name,
      'commanditaire_display' => $commanditaire_display,
      'commanditaire_adresse' => $commanditaire_address,
      'commanditaire_code_postal' => $commanditaire_code_postal,
      'commanditaire_ville' => $commanditaire_ville,
      'commanditaire_identification' => $commanditaire_identification,
      'commanditaire_representant' => $commanditaire_representant,
      'commanditaire_qualite' => $commanditaire_qualite,
      'apprenants_count' => (string) $apprenants_count,
      'apprenants_list' => $apprenants_list,
      'contract_vat_rate' => $vat_rate,
      'contract_price_ht' => number_format( $total_ht, 2, '.', '' ),
      'contract_price_ttc' => number_format( $total_ttc, 2, '.', '' ),
      'contract_total_general' => number_format( $total_ttc, 2, '.', '' ),
      'contract_deposit_amount_ht' => number_format( $deposit_num, 2, '.', '' ),
      'contract_transport_fees_amount_ht' => number_format( $transport_num, 2, '.', '' ),
      'contract_meal_fees_amount_ht' => number_format( $meal_num, 2, '.', '' ),
      'contract_signed_city_date' => 'Fait à ' . $signed_city . ', le ' . $signed_date,
    ),
  );
}


private function build_contract_pdf_pages( $context ) {
  /* ACDC 3.25.237 — `global $wpdb;` OUBLIÉ, ET LA CONVENTION MOURAIT.
     La cascade de recherche du lieu ajoutée en 3.25.231 interroge la base,
     mais cette méthode n'avait jamais eu besoin de $wpdb : la variable n'était
     donc pas importée, et valait null. Le chemin ne s'emprunte que lorsque ni
     la séance ni la proposition ne portent de lieu — c'est-à-dire exactement le
     cas normal d'une convention signée avant que les séances n'existent, celui
     que la 3.25.231 prétendait servir. D'où une erreur fatale à la génération
     du PDF de convention, et un site en écran blanc. */
  global $wpdb;

  $contract  = isset( $context['contract'] ) ? $context['contract'] : null;
  $company   = isset( $context['company'] ) ? $context['company'] : null;
  $formation = isset( $context['formation'] ) ? $context['formation'] : null;
  $session   = isset( $context['session'] ) ? $context['session'] : null;
  $profile   = isset( $context['profile'] ) ? $context['profile'] : array();
  $params    = isset( $context['params'] ) ? $context['params'] : array();
  $contract_vars = isset( $context['convention_contrat'] ) && is_array( $context['convention_contrat'] ) ? $context['convention_contrat'] : array();
  $handwritten_sig_path = isset( $context['handwritten_sig_path'] ) ? (string) $context['handwritten_sig_path'] : '';

  $navy = '#0C2D52';
  $gold = '#C5A253';
  $ink  = '#1f2937';
  $muted = '#6b7280';
  $line = '#d7dde6';
  $soft = '#f8fafc';

  $page_w = 595;
  $page_h = 842;
  $left = 35.43;
  $right = 35.43;
  $content_w = $page_w - $left - $right;

  $normalize = function( $value, $fallback = '' ) {
    $value = is_scalar( $value ) ? trim( wp_strip_all_tags( (string) $value ) ) : '';
    return '' !== $value ? $value : $fallback;
  };

  $build_address = function( $parts ) {
    $parts = array_filter( array_map( static function( $item ) {
      return is_scalar( $item ) ? trim( (string) $item ) : '';
    }, (array) $parts ) );
    return implode( ' ', $parts );
  };

  $training_org_name = $normalize( $profile['enterprise'] ?? '', 'ACDC-Formation' );
  $training_org_address      = $normalize( $profile['address'] ?? '7 avenue Paul Cézanne' );
  $training_org_address_cp   = trim( ( $profile['postal_code'] ?? '83310' ) . ' ' . ( $profile['city'] ?? 'Cogolin - France' ) );
  $training_org_siret = $normalize( $profile['siret_identification'] ?? '', '405109901 00042' );
  $training_org_representant = trim( $normalize( $profile['first_name'] ?? '' ) . ' ' . $normalize( $profile['last_name'] ?? '' ) );
  if ( '' === trim( $training_org_representant ) ) {
    $training_org_representant = 'David Contal';
  }
  $training_org_role = $normalize( $profile['signatory_role'] ?? '', 'Gérant' );
  if ( $training_org_role === $training_org_representant ) {
    $training_org_role = 'Gérant';
  }

  $formation_title = $normalize( $formation->title ?? '', $normalize( $contract->formation_title ?? '', 'Formation non renseignée' ) );
  /* ACDC 3.25.206 — La convention nomme la formation avec son repère de famille.
     « 1.0 » et « 1.1 » portent le même intitulé et sont deux offres distinctes,
     l'une en présentiel, l'autre à distance, avec leur durée et leur tarif. Une
     convention qui ne dit que l'intitulé n'engage pas sur la modalité contractée
     — et c'est précisément ce que l'auditeur vient vérifier.
     On ne pose le repère que si l'on connaît réellement la formation : sur un
     contrat qui ne porte qu'un intitulé libre, l'aide renvoie le titre inchangé
     plutôt que d'inventer un numéro. */
  $formation_ref_id = ! empty( $formation->id ) ? (int) $formation->id : (int) ( $contract->formation_id ?? 0 );
  if ( $formation_ref_id > 0 && 'Formation non renseignée' !== $formation_title ) {
    $formation_title = $this->acdc_formation_labelled( $formation_ref_id, $formation_title );
  }
  $duration = $normalize( $formation->duration ?? '', 'A définir' );
  $duration = preg_replace( '/\s*heures?\s*$/iu', '', (string) $duration );
  $format = $normalize( $formation->modality ?? '', $normalize( $session->format ?? '', 'À définir' ) );
  $proposal_location = isset( $context['proposal']->formation_location ) ? trim( (string) $context['proposal']->formation_location ) : '';
  /* ACDC 3.25.231 — « LIEU : À DÉFINIR » SUR UNE CONVENTION SIGNÉE.
     Le lieu ne se lisait que sur LA séance rattachée, ou sur la proposition.
     Or une convention se signe le plus souvent AVANT que les séances
     n'existent — c'est même l'ordre normal du parcours — et l'on imprimait
     alors « À définir » sur une pièce contractuelle, pendant que le devis, lui,
     portait l'adresse complète. La donnée existait, personne ne la lisait.
     On étend donc la cascade : la séance, la proposition, puis TOUTE séance de
     la formation, puis la fiche formation, puis l'adresse du commanditaire. Le
     lieu d'une formation en entreprise, c'est l'entreprise — c'est la règle que
     David a rappelée. On ne renonce qu'après. */
  $location = '' !== ( $session->location ?? '' ) ? $normalize( (string) $session->location, '' ) : '';

  if ( '' === $location && '' !== $proposal_location ) {
    $location = $proposal_location;
  }

  if ( '' === $location && ! empty( $formation->id ) ) {
    $any_session_location = $wpdb->get_var( $wpdb->prepare(
      "SELECT location FROM {$this->session_table}
        WHERE formation_id = %d AND location IS NOT NULL AND location <> ''
        ORDER BY COALESCE(start_at, CONCAT(start_date,' 00:00:00')) ASC LIMIT 1",
      (int) $formation->id
    ) );
    if ( ! empty( $any_session_location ) ) {
      $location = $normalize( (string) $any_session_location, '' );
    }
  }

  if ( '' === $location && ! empty( $formation ) ) {
    $formation_place = trim( implode( ' ', array_filter( array(
      (string) ( $formation->address ?? '' ),
      (string) ( $formation->postal_code ?? '' ),
      (string) ( $formation->city ?? '' ),
    ) ) ) );
    if ( '' !== $formation_place ) {
      $location = $formation_place;
    }
  }

  if ( '' === $location && ! empty( $company ) ) {
    $company_place = trim( implode( ' ', array_filter( array(
      (string) ( $company->address ?? '' ),
      (string) ( $company->postal_code ?? '' ),
      (string) ( $company->city ?? '' ),
    ) ) ) );
    if ( '' !== $company_place ) {
      $location = $company_place;
    }
  }

  if ( '' === $location ) {
    $location = 'À définir';
  }
  $start_date = ! empty( $contract->start_date ) ? (string) $contract->start_date : ( $session->start_date ?? '' );
  $end_date   = ! empty( $contract->end_date )   ? (string) $contract->end_date   : ( $session->end_date   ?? '' );
  $dates = 'Non renseignées';

  // Priorité : liste des séances si disponible
  if ( ! empty( $contract->seances_dates ) ) {
    $seances_list = array();
    $parts = array_values( array_filter( array_map( 'trim', explode( ',', (string) $contract->seances_dates ) ) ) );
    sort( $parts );
    foreach ( $parts as $i => $d ) {
      if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
        $seances_list[] = 'Séance ' . ( $i + 1 ) . ' : ' . $this->format_pdf_date( $d );
      }
    }
    if ( ! empty( $seances_list ) ) {
      $dates = implode( "\n", $seances_list );
    }
  } elseif ( '' !== $start_date || '' !== $end_date ) {
    $dates = trim( $this->format_pdf_date( $start_date ) . ' au ' . $this->format_pdf_date( $end_date ) );
  }

  // Garde-fou : commanditaire_nom peut être un tableau (désérialisation ancienne version)
  $_raw_commanditaire_nom = $contract_vars['commanditaire_nom'] ?? '';
  if ( is_array( $_raw_commanditaire_nom ) ) {
    $_raw_commanditaire_nom = isset( $_raw_commanditaire_nom['primary'] ) ? (string) $_raw_commanditaire_nom['primary']
      : ( isset( $_raw_commanditaire_nom['flat'] ) ? (string) $_raw_commanditaire_nom['flat']
      : ( isset( $_raw_commanditaire_nom['summary'] ) ? (string) $_raw_commanditaire_nom['summary'] : '' ) );
  }
  $commanditaire_nom = $normalize( $_raw_commanditaire_nom, 'Commanditaire non renseigné' );
  // Reconstruction systématique depuis les données fraîches (contourne les données corrompues en base)
  $_raw_disp = isset( $contract_vars['commanditaire_display'] ) && is_array( $contract_vars['commanditaire_display'] ) ? $contract_vars['commanditaire_display'] : array();
  if ( empty( $_raw_disp['pdf_lines'] ) || ! is_array( $_raw_disp['pdf_lines'] ) ) {
    $_raw_disp = $this->get_registration_contract_commanditaire_display_data( $contract, $context );
  }
  // Si pdf_lines est vide malgré tout, construire un fallback minimal depuis commanditaire_nom
  if ( empty( $_raw_disp['pdf_lines'] ) ) {
    $_raw_disp['pdf_lines'] = array( array( 'text' => $commanditaire_nom, 'font' => 'Helvetica-Bold', 'size' => 8.5, 'color' => '#1f2937' ) );
  }
  $commanditaire_display = $_raw_disp;
  $commanditaire_adresse = $normalize( $contract_vars['commanditaire_adresse'] ?? '', 'Adresse non renseignée' );
  $commanditaire_identification = $normalize( $contract_vars['commanditaire_identification'] ?? '', 'Non renseigné' );
  $commanditaire_code_postal = $normalize( $contract_vars['commanditaire_code_postal'] ?? '', '' );
  $commanditaire_ville = $normalize( $contract_vars['commanditaire_ville'] ?? '', '' );
  $commanditaire_representant = $normalize( $contract_vars['commanditaire_representant'] ?? '', $commanditaire_nom );
  $commanditaire_qualite = $normalize( $contract_vars['commanditaire_qualite'] ?? '', 'Non renseignée' );
  $apprenants_count = $normalize( $contract_vars['apprenants_count'] ?? '', '0' );
  $apprenants_list = $normalize( $contract_vars['apprenants_list'] ?? '', 'Non renseigné' );
  $vat_rate = $normalize( $contract_vars['contract_vat_rate'] ?? '', $normalize( $profile['vat_rate'] ?? '', '20,00' ) );
  $vat_rate = str_replace( '%', '', $vat_rate );
  $price_ht = $normalize( $contract_vars['contract_price_ht'] ?? '', '0.00' );
  $price_ttc = $normalize( $contract_vars['contract_price_ttc'] ?? '', '0.00' );
  $total_general = $normalize( $contract_vars['contract_total_general'] ?? '', $price_ttc );
  $signed_city_date = $normalize( $contract_vars['contract_signed_city_date'] ?? '', 'Fait à Cogolin, le ' . date_i18n( 'd/m/Y' ) );

  $article1_accessibility = $normalize(
    ! empty( $contract->accessibility_handicap ) ? $contract->accessibility_handicap : ( $params['accessibility_handicap'] ?? '' ),
    "ACDC - Formation est engagée dans une démarche inclusive visant à garantir l'accès à la formation pour tous. Accueil des publics en situation de handicap : nos formations sont ouvertes à toute personne, sous réserve de la compatibilité entre le handicap et les exigences de la formation. Chaque situation est étudiée individuellement afin de mettre en place les adaptations nécessaires. Accessibilité des locaux : la salle principale de formation ne dispose pas d'un accès pour les personnes à mobilité réduite. Dans ce cas, ACDC - Formation met à disposition, sur demande, une salle extérieure conforme aux normes d'accessibilité pour les handicaps moteurs. Adaptation pédagogique : tous les supports de formation sont conçus selon le principe du FALC (Facile à Lire et à Comprendre). Les formateurs sont sensibilisés à la prise en compte des handicaps invisibles (dyslexie, dyspraxie, troubles de l'attention, etc.). Des aménagements spécifiques peuvent être proposés : temps supplémentaire, supports adaptés, accompagnement individualisé. Pour toute demande particulière, les référents handicap d'ACDC - Formation étudieront les besoins et coordonneront les adaptations nécessaires avant le démarrage de la formation."
  );
  $article1_environment = $normalize(
    ! empty( $contract->material_environment ) ? $contract->material_environment : ( $params['material_environment'] ?? '' ),
    "Les formations sont dispensées dans un environnement professionnel conforme aux exigences de qualité et de confort nécessaires au bon déroulement des apprentissages. En présentiel : salle équipée d'un vidéoprojecteur ou d'un écran grand format, connexion Internet haut débit, paperboard, supports de cours imprimés ou numériques, espace respectant les normes de sécurité, d'accessibilité et d'hygiène en vigueur. En distanciel (visioconférence) : plateforme de visioconférence sécurisée (Teams, Zoom ou équivalent), accès à un espace numérique partagé (documents, ressources pédagogiques, évaluations), assistance technique en cas de difficulté de connexion, support pédagogique projeté à l'écran et remis en version numérique. Environnement pédagogique : les formations s'appuient sur des supports interactifs, des études de cas, des mises en situation et des outils numériques adaptés. Chaque participant bénéficie d'un accompagnement personnalisé pour favoriser l'acquisition des compétences et l'application concrète des contenus dans son activité professionnelle."
  );
  $article2_objectifs = 'Objectifs de la formation : ' . $normalize( ! empty( $contract->objectives_text ) ? $contract->objectives_text : wp_strip_all_tags( (string) ( $formation->objectives ?? '' ) ), 'Non renseignés.' );
  $article2_programme = 'Programme de la formation : Annexe à la présente convention.';
  $article2_modalites = "Modalités de mise en œuvre, de suivi et d'évaluation : " . $normalize( ! empty( $contract->implementation_followup_evaluation ) ? $contract->implementation_followup_evaluation : ( $params['implementation_followup_evaluation'] ?? '' ), "Modalités de mise en œuvre : Des formateurs expérimentés ; des supports de formation remis aux participants par voie dématérialisée ou en main propre ; des temps d'échanges s'appuyant notamment sur des cas pratiques exposés par le bénéficiaire. Modalités de suivi : feuilles d'émargement signées par les apprenants et formateurs par demi-journée de formation ; récapitulatif complet de l'ensemble des feuilles d'émargement, signé par les apprenants et formateurs ; certificat de réalisation de la formation. Évaluation de la formation : évaluation intermédiaire à mi-formation, évaluation à chaud à la fin de la formation et évaluation à froid un mois après la fin de la formation ; test de positionnement avant la formation et évaluation des acquis à la fin de la formation ; évaluation de la formation par le formateur." );
  $article3 = "Le bénéficiaire s'engage à assurer la présence du ou des participants aux dates, lieux et heures prévues pour la formation.";
  $article5_intro = "En contrepartie de cette action de formation, le bénéficiaire s'acquittera des coûts suivants :";
  $article5_tail = $normalize( ! empty( $contract->financial_provisions ) ? $contract->financial_provisions : ( $params['financial_provisions'] ?? '' ), "En cas de financement par un tiers (OPCO, Pôle emploi, CPF...), l'Apprenant reste personnellement responsable du règlement de la totalité du prix si le financeur refuse ou interrompt sa prise en charge. Dans le cas d'une subrogation de paiement par un financeur, celle-ci devra nous être communiquée par écrit avant le début de la formation. En cas de refus par le financeur de la subrogation et/ou du règlement de la formation ou d'une partie de la formation, le solde de la prestation sera dû par le bénéficiaire à réception de facture." );
  $article6 = $normalize( ! empty( $contract->payment_terms ) ? $contract->payment_terms : ( $params['payment_terms'] ?? '' ), "Ce prix couvre l'intégralité des frais pédagogiques engagés par l'Organisme. Le règlement est exigible à réception de facture. Les modalités sont définies dans les Conditions Générales de Vente annexées." );
  $article7 = $normalize( ! empty( $contract->cancellation_terms ) ? $contract->cancellation_terms : ( $params['cancellation_terms'] ?? '' ), "Toute demande d'annulation ou de report d'une inscription doit être notifiée par écrit, par lettre ou par courriel, et réceptionnée par ACDC - Formation pour être recevable. À défaut, aucune demande ne sera prise en considération. En cas d'annulation imputable au Client, les sommes déjà versées ou facturées demeurent acquises, en tout ou partie, selon la date de notification par rapport à la date prévue de début de la formation. En cas d'abandon en cours de formation par le ou les apprenants désignés, aucune restitution ne sera due au Client, sauf en cas de survenance d'un événement de force majeure dûment caractérisé. Le Prestataire se réserve par ailleurs le droit d'annuler ou de reporter une session de formation en cas de force majeure ou en cas de nombre de participants insuffisant." );
  // ACDC 3.25.113 — priorité aux valeurs saisies sur le contrat (perte de données doc juridique).
  $article8 = 'La présente convention prend effet à compter de sa date de signature. Le délai de rétractation est de ' . $normalize( ! empty( $contract->withdrawal_delay_days ) ? $contract->withdrawal_delay_days : ( $params['withdrawal_delay_days'] ?? '' ), '14' ) . ' jours.';
  $article9 = $normalize( ! empty( $contract->disputes_terms ) ? $contract->disputes_terms : ( $params['possible_disputes'] ?? '' ), "Si une contestation ou un différend ne peuvent être réglés à l'amiable, le Tribunal le plus proche géographiquement du siège social du défendeur sera seul compétent pour régler le litige." );

  // ACDC 3.25.113 — priorité aux valeurs saisies sur le contrat (perte de données doc juridique).
  $contract_additional_sections = ! empty( $contract->additional_sections ) ? json_decode( (string) $contract->additional_sections, true ) : null;
  if ( is_array( $contract_additional_sections ) && ! empty( $contract_additional_sections ) ) {
    $additional_sections = $contract_additional_sections;
  } else {
    $additional_sections = ! empty( $params['additional_sections'] ) && is_array( $params['additional_sections'] ) ? $params['additional_sections'] : array();
  }
  $article10 = $normalize( $additional_sections[0]['content'] ?? '', "L'Apprenant s'engage à suivre la formation avec assiduité et ponctualité, à respecter les consignes pédagogiques et organisationnelles données par les formateurs, ainsi que le Règlement Intérieur annexé à la présente convention. L'Apprenant s'interdit toute reproduction, enregistrement ou diffusion non autorisée des supports, outils ou contenus pédagogiques. Toute inexécution de ces obligations pourra entraîner son exclusion immédiate de la formation, sans remboursement ni indemnité, et engager sa responsabilité." );
  $article11 = $normalize( $additional_sections[1]['content'] ?? '', "L'Organisme de formation s'engage à mettre en œuvre les moyens pédagogiques, techniques et humains nécessaires au bon déroulement de la formation, et à délivrer à l'Apprenant, à l'issue de celle-ci, une attestation mentionnant les objectifs, la nature et la durée de l'action suivie." );
  $article12 = $normalize( $additional_sections[2]['content'] ?? '', "Tout manquement de l'Apprenant aux obligations du présent contrat ou au règlement intérieur pourra donner lieu à l'application des sanctions disciplinaires prévues par ce règlement, pouvant aller jusqu'à l'exclusion définitive de la formation, sans remboursement ni indemnité." );
  $article13 = $normalize( $additional_sections[3]['content'] ?? '', "Les données personnelles de l'Apprenant sont collectées et traitées par l'Organisme conformément au RGPD et à la loi Informatique et Libertés modifiée. L'Apprenant bénéficie d'un droit d'accès, de rectification, de suppression et d'opposition. La politique de confidentialité d'ACDC - Formation est publique, accessible sur le site internet de l'Organisme et communiquée à l'apprenant au moment de l'inscription." );
  $article14 = $normalize( $additional_sections[4]['content'] ?? '', "Tout litige relatif à l'interprétation ou à l'exécution de la présente convention, non résolu amiablement, sera soumis à la compétence exclusive des juridictions du ressort de la Cour d'appel de DRAGUIGNAN." );

  /* ACDC 3.25.232 — La convention avait, comme chaque PDF, son propre repli
     écrit en dur — vers un fichier absent de la médiathèque. Une seule
     résolution pour tous les documents désormais. */
  $logo_url   = $this->acdc_resolve_pdf_logo_url();
  $logo_image = $this->prepare_pdf_jpeg_image( $logo_url, 48, 48 );
  $signature_image    = null;
  $handwritten_image  = null;
  // Chargement direct depuis le chemin fichier (prepare_pdf_jpeg_image attend une URL, pas un path)
  if ( '' !== $handwritten_sig_path && file_exists( $handwritten_sig_path ) && function_exists( 'imagecreatefromstring' ) ) {
    @error_log( '[ACDC] build_contract_pdf_pages — chargement signature manuscrite : ' . $handwritten_sig_path );
    $hw_raw = @file_get_contents( $handwritten_sig_path );
    if ( $hw_raw ) {
      $hw_gd = @imagecreatefromstring( $hw_raw );
      if ( $hw_gd ) {
        $hw_w = imagesx( $hw_gd );
        $hw_h = imagesy( $hw_gd );
        $hw_ratio = ( $hw_w && $hw_h ) ? min( 220 / $hw_w, 100 / $hw_h, 1 ) : 1;
        $hw_dw    = max( 1, (int) round( $hw_w * $hw_ratio ) );
        $hw_dh    = max( 1, (int) round( $hw_h * $hw_ratio ) );
        $hw_flat  = imagecreatetruecolor( $hw_w, $hw_h );
        imagefilledrectangle( $hw_flat, 0, 0, $hw_w, $hw_h, imagecolorallocate( $hw_flat, 255, 255, 255 ) );
        imagealphablending( $hw_flat, true );
        imagecopy( $hw_flat, $hw_gd, 0, 0, 0, 0, $hw_w, $hw_h );
        ob_start(); imagejpeg( $hw_flat, null, 92 ); $hw_jpeg = ob_get_clean();
        imagedestroy( $hw_flat ); imagedestroy( $hw_gd );
        if ( $hw_jpeg ) {
          @error_log( '[ACDC] build_contract_pdf_pages — signature JPEG OK, dims=' . $hw_w . 'x' . $hw_h );
          $handwritten_image = array(
            'key'            => 'hw_sig_' . md5( $handwritten_sig_path ),
            'data'           => $hw_jpeg,
            'width'          => $hw_w,
            'height'         => $hw_h,
            'display_width'  => $hw_dw,
            'display_height' => $hw_dh,
          );
        }
      }
    }
  }
  $signature_candidates = array(
    ! empty( $profile['stamp_url'] ) ? (string) $profile['stamp_url'] : '',
    ! empty( $profile['signature_url'] ) ? (string) $profile['signature_url'] : '',
    ! empty( $profile['stamp_only_url'] ) ? (string) $profile['stamp_only_url'] : '',
  );
  foreach ( $signature_candidates as $signature_candidate ) {
    if ( '' === $signature_candidate ) {
      continue;
    }
    $signature_image = $this->prepare_pdf_jpeg_image( $signature_candidate, 170, 170 );
    if ( $signature_image ) {
      break;
    }
  }

  $create_page = function( $title_line ) use ( $page_w, $page_h, $logo_image, $training_org_name, $training_org_siret, $left, $right, $navy, $gold, $ink, $muted, $line ) {
    $page = array(
      array( 'type' => 'page_meta', 'width' => $page_w, 'height' => $page_h ),
    );
    $header_top = 811;
    $header_bottom = 754;
    $page[] = array( 'type' => 'rect', 'x' => $left, 'y' => $header_bottom, 'width' => $page_w - $left - $right, 'height' => 1.2, 'fill_color' => $gold );
    if ( $logo_image ) {
      $page[] = array(
        'type' => 'image',
        'image_key' => $logo_image['key'],
        'image_data' => $logo_image['data'],
        'image_width' => $logo_image['width'],
        'image_height' => $logo_image['height'],
        'display_width' => $logo_image['display_width'],
        'display_height' => $logo_image['display_height'],
        'x' => $left,
        'y' => 782,
      );
    }
    $page[] = array( 'text' => 'ACDC-Formation', 'x' => $left + 62, 'y' => 806, 'size' => 13.6, 'font' => 'Helvetica-Bold', 'color' => $navy );
    $page[] = array( 'text' => 'Azur - Compétences - Développement - Conseils', 'x' => $left + 62, 'y' => 792, 'size' => 8.4, 'font' => 'Helvetica', 'color' => $gold );
    $company_x = $page_w - $right - 130;
    $company_lines = array(
      $training_org_name,
      '7 avenue Paul Cézanne',
      '83310 Cogolin - France',
      'Siret : ' . $training_org_siret,
      'NDA : 93 83 08347 83',
    );
    $cy = 806;
    foreach ( $company_lines as $index => $line_text ) {
      $page[] = array( 'text' => $line_text, 'x' => $company_x, 'y' => $cy, 'size' => 8, 'font' => 0 === $index ? 'Helvetica-Bold' : 'Helvetica', 'color' => '#374151' );
      $cy -= 10;
    }
    $page[] = array( 'text' => $title_line, 'x' => $left, 'y' => 736, 'size' => 15.6, 'font' => 'Helvetica-Bold', 'color' => $navy );
    return $page;
  };

  $add_footer = function( &$page ) use ( $page_w, $left, $right, $line, $muted ) {
    $page[] = array( 'type' => 'rect', 'x' => $left - 5, 'y' => 30, 'width' => $page_w - ( 2 * ( $left - 5 ) ), 'height' => 1, 'fill_color' => $line );
    $page[] = array( 'text' => '7 avenue Paul Cézanne - 83310 Cogolin - France - Siret : 405109901 00042 - NDA : 93 83 08347 83', 'x' => 0, 'y' => 21, 'size' => 6.8, 'font' => 'Helvetica', 'color' => '#4b5563', 'center' => true, 'page_w' => $page_w );
    $page[] = array( 'text' => 'e-mail : contact@acdc-formation.com - Tél : 06 78 26 91 10 - site web : acdc-formation.com', 'x' => 0, 'y' => 12, 'size' => 6.8, 'font' => 'Helvetica', 'color' => '#4b5563', 'center' => true, 'page_w' => $page_w );
  };

  $add_box = function( &$page, &$cursor_y, $title, $paragraphs, $opts = array() ) use ( $left, $content_w, $line, $gold, $ink ) {
    $x = isset( $opts['x'] ) ? (float) $opts['x'] : $left;
    $w = isset( $opts['width'] ) ? (float) $opts['width'] : $content_w;
    $padding_left = 12.76;
    $padding_right = 12.76;
    $padding_top = 11.34;
    $padding_bottom = 11.34;
    $title_gap = 15.0;
    $line_gap = 11.8;
    $paragraph_gap = 5.2;
    $min_height = isset( $opts['min_height'] ) ? (float) $opts['min_height'] : 0;
    $usable_w = max( 40, $w - $padding_left - $padding_right );
    // Wrap et justification basés sur métriques AFM Helvetica 8.3pt réelles.
    // $max_chars est conservé comme garde-fou mais le wrap réel utilise $helv_w.
    $max_chars = max( 22, (int) floor( $usable_w / 3.6 ) );
    // Métriques AFM Helvetica (largeurs/1000 unités)
    $helv_w = array(32=>278,33=>278,34=>355,35=>556,36=>556,37=>889,38=>667,39=>222,40=>333,41=>333,42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,48=>556,49=>556,50=>556,51=>556,52=>556,53=>556,54=>556,55=>556,56=>556,57=>556,58=>278,59=>278,60=>584,61=>584,62=>584,63=>556,64=>1015,65=>667,66=>667,67=>722,68=>722,69=>667,70=>611,71=>778,72=>722,73=>278,74=>500,75=>667,76=>556,77=>833,78=>722,79=>778,80=>667,81=>778,82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,89=>667,90=>611,91=>278,92=>278,93=>278,94=>469,95=>556,96=>222,97=>556,98=>556,99=>500,100=>556,101=>556,102=>278,103=>556,104=>556,105=>222,106=>222,107=>500,108=>222,109=>833,110=>556,111=>556,112=>556,113=>556,114=>333,115=>500,116=>278,117=>556,118=>500,119=>722,120=>500,121=>500,122=>500,192=>667,193=>667,194=>667,195=>667,196=>667,197=>667,198=>1000,199=>722,200=>667,201=>667,202=>667,203=>667,204=>278,205=>278,206=>278,207=>278,208=>722,209=>722,210=>778,211=>778,212=>778,213=>778,214=>778,216=>778,217=>722,218=>722,219=>722,220=>722,221=>667,222=>667,223=>611,224=>556,225=>556,226=>556,227=>556,228=>556,229=>556,230=>889,231=>500,232=>556,233=>556,234=>556,235=>556,236=>278,237=>278,238=>278,239=>278,240=>556,241=>556,242=>556,243=>556,244=>556,245=>556,246=>556,248=>611,249=>556,250=>556,251=>556,252=>556,253=>500,254=>556,255=>500);
    $font_size_body = 8.3;
    // Calcule la largeur réelle en pts d'un texte en Helvetica
    $afm_w = static function( $text ) use ( $helv_w, $font_size_body ) {
      $w = 0; $len = mb_strlen( $text );
      for ( $i = 0; $i < $len; $i++ ) {
        $cp = mb_ord( mb_substr( $text, $i, 1 ), 'UTF-8' );
        $w += isset( $helv_w[ $cp ] ) ? $helv_w[ $cp ] : 556;
      }
      return $w * $font_size_body / 1000.0;
    };
    // Wrap précis basé sur métriques AFM : remplace pdf_wrap_text pour les articles.
    // Remplit chaque ligne au maximum de $usable_w sans dépasser.
    $afm_wrap = static function( $text ) use ( $afm_w, $usable_w ) {
      $text = trim( (string) $text );
      if ( '' === $text ) { return array(); }
      $words = preg_split( '/\s+/u', $text );
      $lines = array();
      $current = '';
      $space_w = $afm_w( ' ' );
      foreach ( $words as $word ) {
        $test = '' === $current ? $word : $current . ' ' . $word;
        if ( $afm_w( $test ) <= $usable_w + 0.5 ) {
          $current = $test;
        } else {
          if ( '' !== $current ) { $lines[] = $current; }
          $current = $word;
        }
      }
      if ( '' !== $current ) { $lines[] = $current; }
      return $lines;
    };
    // justify_line calcule Tw précis pour aligner le bord droit exactement
    $justify_line = static function( $line_text, $target_chars ) use ( $afm_w, $usable_w ) {
      $line_text = trim( (string) $line_text );
      if ( '' === $line_text ) { return ''; }
      $words = preg_split( '/\s+/u', $line_text );
      if ( ! is_array( $words ) || count( $words ) < 2 ) { return $line_text; }
      $gaps    = count( $words ) - 1;
      $text_w  = $afm_w( $line_text );
      $space_w = $afm_w( ' ' );
      $extra_w = $usable_w - $text_w;
      if ( $extra_w <= 0 ) { return $line_text; }
      $tw = ( $extra_w - $space_w * $gaps ) / max( 1, $gaps );
      $tw = min( max( $tw, 0.0 ), 12.0 );
      return sprintf( '[[TW:%.4f]]', $tw ) . $line_text;
    };
    $lines = array();
    foreach ( (array) $paragraphs as $paragraph ) {
      $paragraph = trim( (string) $paragraph );
      if ( '' === $paragraph ) {
        continue;
      }
      $wrapped = $afm_wrap( $paragraph );
      $wrapped_count = count( $wrapped );
      foreach ( $wrapped as $idx => $wrapped_line ) {
        $is_last = ( $idx === ( $wrapped_count - 1 ) );
        $lines[] = array(
          'text' => $is_last ? $wrapped_line : $justify_line( $wrapped_line, $max_chars ),
          'blank' => false,
        );
      }
      $lines[] = array( 'text' => '', 'blank' => true );
    }
    if ( ! empty( $lines ) && ! empty( $lines[ count( $lines ) - 1 ]['blank'] ) ) {
      array_pop( $lines );
    }
    $height = $padding_top + $title_gap + $padding_bottom;
    foreach ( $lines as $line_item ) {
      $height += ! empty( $line_item['blank'] ) ? $paragraph_gap : $line_gap;
    }
    if ( $min_height > $height ) {
      $height = $min_height;
    }
    $bottom_y = $cursor_y - $height;
    $page[] = array( 'type' => 'rect', 'x' => $x, 'y' => $bottom_y, 'width' => $w, 'height' => $height, 'stroke_color' => $line, 'fill_color' => '#ffffff', 'line_width' => 1 );
    $page[] = array( 'text' => strtoupper( $title ), 'x' => $x + $padding_left, 'y' => $cursor_y - 16.2, 'size' => 8.4, 'font' => 'Helvetica-Bold', 'color' => $gold );
    $ty = $cursor_y - 31.8;
    foreach ( $lines as $line_item ) {
      if ( ! empty( $line_item['blank'] ) ) {
        $ty -= $paragraph_gap;
        continue;
      }
      $page[] = array( 'text' => $line_item['text'], 'x' => $x + $padding_left, 'y' => $ty, 'size' => 8.3, 'font' => 'Helvetica', 'color' => $ink );
      $ty -= $line_gap;
    }
    $cursor_y = $bottom_y - 14.17;
  };

  $add_two_col_intro = function( &$page, &$cursor_y ) use ( $left, $content_w, $line, $gold, $navy, $ink, $training_org_name, $training_org_address, $training_org_address_cp, $training_org_siret, $training_org_representant, $training_org_role, $commanditaire_nom, $commanditaire_display, $commanditaire_adresse, $commanditaire_code_postal, $commanditaire_ville, $commanditaire_identification, $commanditaire_representant, $commanditaire_qualite ) {
    $x = $left;
    $w = $content_w;
    $padding_x = 12.76;
    $padding_top = 11.34;
    $title_gap = 15.0;
    $grid_gap = 17.0;
    $field_gap = 13.2;
    $small_gap = 9.8;
    $label_w = 84.0;
    $inner_w = $w - ( 2 * $padding_x );
    $col_w = ( $inner_w - $grid_gap ) / 2;
    $bottom = $cursor_y - 147.5;
    $page[] = array( 'type' => 'rect', 'x' => $x, 'y' => $bottom, 'width' => $w, 'height' => 147.5, 'stroke_color' => $line, 'fill_color' => '#ffffff', 'line_width' => 1 );
    $page[] = array( 'text' => 'ENTRE LES SOUSSIGNÉS', 'x' => $x + $padding_x, 'y' => $cursor_y - 16.2, 'size' => 8.4, 'font' => 'Helvetica-Bold', 'color' => $gold );

    $col1x = $x + $padding_x;
    $col2x = $col1x + $col_w + $grid_gap;
    $value_offset = $label_w + 6.0;

    $render_field_block = function( &$page, $start_x, $start_y, $rows, $wrap_default = 28 ) use ( $navy, $ink, $label_w, $value_offset, $field_gap, $small_gap ) {
      $y = $start_y;
      foreach ( $rows as $row ) {
        $label = isset( $row['label'] ) ? (string) $row['label'] : '';
        $value = isset( $row['value'] ) ? (string) $row['value'] : '';
        $wrap = isset( $row['wrap'] ) ? (int) $row['wrap'] : $wrap_default;
        $value_lines = array();
        if ( isset( $row['value_lines'] ) && is_array( $row['value_lines'] ) ) {
          foreach ( $row['value_lines'] as $value_line ) {
            if ( is_array( $value_line ) ) {
              $text_value = isset( $value_line['text'] ) ? trim( (string) $value_line['text'] ) : '';
              if ( '' !== $text_value ) {
                $value_lines[] = $value_line;
              }
            } else {
              $text_value = trim( (string) $value_line );
              if ( '' !== $text_value ) {
                $value_lines[] = $text_value;
              }
            }
          }
        }
        if ( '' !== $label ) {
          $page[] = array( 'text' => $label, 'x' => $start_x, 'y' => $y, 'size' => 8.5, 'font' => 'Helvetica-Bold', 'color' => $navy );
          if ( empty( $value_lines ) ) {
            $value_lines = $this->pdf_wrap_text( $value, $wrap );
          }
          foreach ( $value_lines as $idx => $wrap_line ) {
            $line_text = is_array( $wrap_line ) ? (string) ( $wrap_line['text'] ?? '' ) : (string) $wrap_line;
            if ( '' === $line_text ) {
              continue;
            }
            $page[] = array(
              'text' => $line_text,
              'x' => $start_x + $value_offset,
              'y' => $y - ( $idx * $small_gap ),
              'size' => is_array( $wrap_line ) && isset( $wrap_line['size'] ) ? (float) $wrap_line['size'] : 8.5,
              'font' => is_array( $wrap_line ) && ! empty( $wrap_line['font'] ) ? (string) $wrap_line['font'] : 'Helvetica',
              'color' => is_array( $wrap_line ) && ! empty( $wrap_line['color'] ) ? (string) $wrap_line['color'] : $ink,
            );
          }
          $y -= max( $field_gap, count( $value_lines ) * $small_gap + 3.5 );
        } else {
          $lines = empty( $value_lines ) ? $this->pdf_wrap_text( $value, $wrap ) : $value_lines;
          foreach ( $lines as $idx => $wrap_line ) {
            $line_text = is_array( $wrap_line ) ? (string) ( $wrap_line['text'] ?? '' ) : (string) $wrap_line;
            if ( '' === $line_text ) {
              continue;
            }
            $page[] = array(
              'text' => $line_text,
              'x' => $start_x,
              'y' => $y - ( $idx * $small_gap ),
              'size' => is_array( $wrap_line ) && isset( $wrap_line['size'] ) ? (float) $wrap_line['size'] : 8.3,
              'font' => is_array( $wrap_line ) && ! empty( $wrap_line['font'] ) ? (string) $wrap_line['font'] : 'Helvetica',
              'color' => is_array( $wrap_line ) && ! empty( $wrap_line['color'] ) ? (string) $wrap_line['color'] : $ink,
            );
          }
          $y -= max( 11.0, count( $lines ) * $small_gap + 1.5 );
        }
      }
      return $y;
    };

    $left_rows = array(
      array( 'label' => 'Organisme :', 'value' => $training_org_name, 'wrap' => 26 ),
      array( 'label' => 'Siège social :', 'value_lines' => array( $training_org_address, $training_org_address_cp ), 'wrap' => 28 ),
      array( 'label' => 'SIRET / identification :', 'value' => $training_org_siret, 'wrap' => 26 ),
      array( 'label' => 'Représenté par :', 'value' => $training_org_representant, 'wrap' => 26 ),
      array( 'label' => 'Qualité :', 'value' => $training_org_role, 'wrap' => 26 ),
      array( 'label' => '', 'value' => "Ci-après nommé l'organisme de formation.", 'wrap' => 37 ),
    );
    $benef_address_line1 = '' !== trim( $commanditaire_adresse ) ? trim( $commanditaire_adresse ) : '';
    $benef_address_line2 = trim( $commanditaire_code_postal . ' ' . $commanditaire_ville );
    if ( '' === $benef_address_line1 && '' === $benef_address_line2 ) {
      $benef_address_lines = array( 'Adresse non renseignée' );
    } elseif ( '' === $benef_address_line1 ) {
      $benef_address_lines = array( $benef_address_line2 );
    } elseif ( '' === $benef_address_line2 ) {
      $benef_address_lines = array( $benef_address_line1 );
    } else {
      $benef_address_lines = array( $benef_address_line1, $benef_address_line2 );
    }
    $right_rows = array(
      array( 'label' => 'Bénéficiaire :', 'value_lines' => ! empty( $commanditaire_display['pdf_lines'] ) ? $commanditaire_display['pdf_lines'] : array( $commanditaire_nom ), 'wrap' => 27 ),
      array( 'label' => 'Siège social :', 'value_lines' => $benef_address_lines, 'wrap' => 27 ),
      array( 'label' => 'SIRET / identification :', 'value' => $commanditaire_identification, 'wrap' => 24 ),
      array( 'label' => 'Représenté par :', 'value' => $commanditaire_representant, 'wrap' => 24 ),
      array( 'label' => 'Qualité :', 'value' => $commanditaire_qualite, 'wrap' => 24 ),
      array( 'label' => '', 'value' => 'Ci-après nommé le bénéficiaire.', 'wrap' => 31 ),
    );

    $render_field_block( $page, $col1x, $cursor_y - 33.0, $left_rows, 32 );
    $render_field_block( $page, $col2x, $cursor_y - 33.0, $right_rows, 32 );
    $cursor_y = $bottom - 14.2;
  };

  $pages = array();

  $page1 = $create_page( 'Convention de formation professionnelle' );
  $page1[] = array( 'text' => 'Articles L6353-1 et L.6353-2 du Code du travail et Décret n°2018-1341 du 28 décembre 2018', 'x' => $left, 'y' => 718, 'size' => 8.1, 'font' => 'Helvetica', 'color' => '#4b5563' );
  $cursor = 690;
  $add_box( $page1, $cursor, 'Formation', array( $formation_title ), array( 'width' => $content_w ) );
  $add_two_col_intro( $page1, $cursor );
  $article1_lines = array(
    "L'organisme de formation organisera l'action de formation suivante : " . $formation_title . '.',
    'Dates : ' . $dates,
    'Durée : ' . $duration . ' heures',
    'Format : ' . $format,
    'Lieu de la formation : ' . $location,
    $article1_accessibility,
    $article1_environment,
  );
  $add_box( $page1, $cursor, 'Article 1 - Objet de la convention', $article1_lines, array( 'width' => $content_w ) );
  $add_footer( $page1 );
  $pages[] = $page1;

  $page2 = $create_page( 'Convention de formation professionnelle - suite' );
  $cursor = 706;
  $add_box( $page2, $cursor, "Article 2 - Nature et caractéristiques de l'action de formation", array( $article2_objectifs, $article2_programme, $article2_modalites ) );
  $add_box( $page2, $cursor, 'Article 3 - Engagement de participation', array( $article3 ) );
  $add_box( $page2, $cursor, 'Article 4 - Effectif formé', array( "Nombre d'apprenants : " . $apprenants_count, 'Apprenant(s) : ' . $apprenants_list ), array( 'min_height' => 64 ) );
  $add_box( $page2, $cursor, 'Article 5 - Dispositions financières', array( $article5_intro, 'Frais pédagogiques : ' . $price_ht . ' €', 'Taux de TVA : ' . $vat_rate . ' %', 'Prix total TTC : ' . $price_ttc . ' €', 'TOTAL GÉNÉRAL : ' . $total_general . ' €', $article5_tail ) );
  $add_footer( $page2 );
  $pages[] = $page2;

  $page3 = $create_page( 'Convention de formation professionnelle - suite' );
  $cursor = 706;
  $add_box( $page3, $cursor, 'Article 6 - Modalités de règlement', array( $article6 ) );
  $add_box( $page3, $cursor, 'Article 7 - Annulation / Report / Dédommagement / Réparation / Dédit', array( $article7 ) );
  $add_box( $page3, $cursor, "Article 8 - Date d'effet de la convention", array( $article8 ) );
  $add_box( $page3, $cursor, 'Article 9 - Différends éventuels', array( $article9 ) );
  $add_box( $page3, $cursor, "Article 10 - Engagements de l'Apprenant", array( $article10 ) );
  $add_footer( $page3 );
  $pages[] = $page3;

  $page4 = $create_page( 'Convention de formation professionnelle - Fin' );
  $cursor = 706;
  $add_box( $page4, $cursor, "Article 11 - Engagements de l'Organisme de Formation", array( $article11 ) );
  $add_box( $page4, $cursor, 'Article 12 - Discipline et sanctions', array( $article12 ) );
  $add_box( $page4, $cursor, 'Article 13 - Données personnelles et confidentialité', array( $article13 ) );
  $add_box( $page4, $cursor, 'Article 14 - Litiges', array( $article14 ) );
  $add_box( $page4, $cursor, preg_replace( '/^Fait à /', 'Fait à ', $signed_city_date ), array( 'Pour le bénéficiaire : ' . ( ! empty( $commanditaire_display['signature_label'] ) ? $commanditaire_display['signature_label'] : $commanditaire_nom ), "Pour l'organisme de formation : " . $training_org_name . ' - ' . $training_org_representant ) );

  $sign_top = 135;
  $sign_box_width = 220;
  $sign_box_height = 34;
  $page4[] = array( 'type' => 'rect', 'x' => $left, 'y' => $sign_top, 'width' => $sign_box_width, 'height' => 1, 'fill_color' => $line );
  $page4[] = array( 'type' => 'rect', 'x' => $page_w - $right - $sign_box_width, 'y' => $sign_top, 'width' => $sign_box_width, 'height' => 1, 'fill_color' => $line );
  $page4[] = array( 'text' => 'Signature du bénéficiaire', 'x' => $left + 42, 'y' => $sign_top - 14, 'size' => 7.9, 'font' => 'Helvetica', 'color' => '#4b5563' );
  // ACDC 3.21.08 — Signature manuscrite du commanditaire dans la boîte gauche
  if ( $handwritten_image ) {
    $hw_dw = min( 180, max( 1, (float) $handwritten_image['display_width'] ) );
    $hw_dh = min( 60,  max( 1, (float) $handwritten_image['display_height'] ) );
    $page4[] = array(
      'type'           => 'image',
      'image_key'      => $handwritten_image['key'],
      'image_data'     => $handwritten_image['data'],
      'image_width'    => $handwritten_image['width'],
      'image_height'   => $handwritten_image['height'],
      'display_width'  => $hw_dw,
      'display_height' => $hw_dh,
      'x'              => $left,
      'y'              => 142,
    );
  }
  $page4[] = array( 'text' => "Signature de l'organisme de formation", 'x' => $page_w - $right - $sign_box_width + 36, 'y' => $sign_top - 14, 'size' => 7.9, 'font' => 'Helvetica', 'color' => '#4b5563' );
  if ( $signature_image ) {
    $signature_display_width = min( 390, max( 1, (float) $signature_image['display_width'] * 1.5 ) );
    $signature_display_height = min( 255, max( 1, (float) $signature_image['display_height'] * 1.5 ) );
    $page4[] = array(
      'type' => 'image',
      'image_key' => $signature_image['key'],
      'image_data' => $signature_image['data'],
      'image_width' => $signature_image['width'],
      'image_height' => $signature_image['height'],
      'display_width' => $signature_display_width,
      'display_height' => $signature_display_height,
      'x' => $page_w - $right - $signature_display_width - 18,
      'y' => 78,
    );
  }
  $add_footer( $page4 );
  $pages[] = $page4;

  return $pages;
}


  /**
   * ACDC 3.25.00 — Agrège toutes les pièces produites pour une entité donnée.
   * @param string $entity_type  'learner' | 'company' | 'formation' | 'session'
   * @param int    $entity_id
   * @return array  [ ['type'=>string, 'label'=>string, 'date'=>string, 'url'=>string, 'status'=>string], ... ]
   */
  private function get_dossier_pieces_for_entity( $entity_type, $entity_id ) {
    global $wpdb;
    $entity_id   = absint( $entity_id );
    $entity_type = sanitize_key( (string) $entity_type );
    if ( ! $entity_id || ! $entity_type ) return array();

    $pieces = array();

    // ── 1. Conventions / Contrats ─────────────────────────────────────────
    if ( 'learner' === $entity_type ) {
      $contracts = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, title, start_date, signature_status, document_url, signed_document_url FROM {$this->registration_contract_table}
         WHERE FIND_IN_SET(%d, REPLACE(learner_ids, ' ', '')) ORDER BY id DESC",
        $entity_id
      ) );
    } elseif ( 'company' === $entity_type ) {
      $contracts = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, title, start_date, signature_status, document_url, signed_document_url FROM {$this->registration_contract_table}
         WHERE company_id = %d ORDER BY id DESC",
        $entity_id
      ) );
    } elseif ( 'formation' === $entity_type ) {
      $contracts = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, title, start_date, signature_status, document_url, signed_document_url FROM {$this->registration_contract_table}
         WHERE formation_id = %d ORDER BY id DESC",
        $entity_id
      ) );
    } elseif ( 'session' === $entity_type ) {
      $contracts = $wpdb->get_results( $wpdb->prepare(
        "SELECT rc.id, rc.title, rc.start_date, rc.signature_status, rc.document_url, rc.signed_document_url
         FROM {$this->registration_contract_table} rc
         INNER JOIN {$this->training_registration_table} tr ON tr.contract_id = rc.id
         WHERE tr.session_id = %d ORDER BY rc.id DESC",
        $entity_id
      ) );
    } else {
      $contracts = array();
    }
    foreach ( (array) $contracts as $c ) {
      $doc_url = ! empty( $c->signed_document_url ) ? (string) $c->signed_document_url
               : ( ! empty( $c->document_url ) ? (string) $c->document_url : '' );
      $sig_label = '';
      if ( 'signe' === (string) $c->signature_status ) {
        $sig_label = ' ✅';
      } elseif ( ! empty( $c->signature_status ) ) {
        $sig_label = ' ⏳';
      }
      $pieces[] = array(
        'type'   => 'convention',
        'label'  => sanitize_text_field( (string) $c->title ) . $sig_label,
        'date'   => ! empty( $c->start_date ) ? wp_date( 'd/m/Y', strtotime( (string) $c->start_date ) ) : '—',
        'url'    => $doc_url,
        'status' => (string) $c->signature_status,
        'id'     => (int) $c->id,
      );
    }

    // ── 2. Analyses du besoin ─────────────────────────────────────────────
    if ( 'learner' === $entity_type ) {
      $nads = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, title, sent_at, statut, document_url_apprenant, document_url_commanditaire FROM {$this->need_analysis_table}
         WHERE apprenant_id = %d AND is_model = 0 ORDER BY id DESC",
        $entity_id
      ) );
    } elseif ( 'company' === $entity_type ) {
      $nads = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, title, sent_at, statut, document_url_apprenant, document_url_commanditaire FROM {$this->need_analysis_table}
         WHERE entreprise_id = %d AND is_model = 0 ORDER BY id DESC",
        $entity_id
      ) );
    } elseif ( 'formation' === $entity_type ) {
      $nads = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, title, sent_at, statut, document_url_apprenant, document_url_commanditaire FROM {$this->need_analysis_table}
         WHERE formation_id = %d AND is_model = 0 ORDER BY id DESC",
        $entity_id
      ) );
    } elseif ( 'session' === $entity_type ) {
      // NAD liées aux apprenants d'une séance
      $learner_rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT id FROM {$this->learner_table} WHERE session_id = %d",
        $entity_id
      ) );
      $l_ids = array_values( array_map( function( $r ) { return (int) $r->id; }, (array) $learner_rows ) );
      if ( ! empty( $l_ids ) ) {
        $ph = implode( ',', array_fill( 0, count( $l_ids ), '%d' ) );
        $nads = $wpdb->get_results( $wpdb->prepare(
          "SELECT id, title, sent_at, statut, document_url_apprenant, document_url_commanditaire FROM {$this->need_analysis_table}
           WHERE apprenant_id IN ($ph) AND is_model = 0 ORDER BY id DESC",
          ...$l_ids
        ) );
      } else {
        $nads = array();
      }
    } else {
      $nads = array();
    }
    foreach ( (array) $nads as $n ) {
      $doc_url = ! empty( $n->document_url_apprenant ) ? (string) $n->document_url_apprenant
               : ( ! empty( $n->document_url_commanditaire ) ? (string) $n->document_url_commanditaire : '' );
      $statut = (string) $n->statut;
      $st_label = 'soumise' === $statut ? ' ✅' : ( 'envoyee' === $statut ? ' ⏳' : '' );
      $date_val = ! empty( $n->sent_at ) ? wp_date( 'd/m/Y', strtotime( (string) $n->sent_at ) ) : '—';
      $pieces[] = array(
        'type'   => 'nad',
        'label'  => 'Analyse du besoin : ' . sanitize_text_field( (string) $n->title ) . $st_label,
        'date'   => $date_val,
        'url'    => $doc_url,
        'status' => $statut,
        'id'     => (int) $n->id,
      );
    }

    // ── 3. Devis ──────────────────────────────────────────────────────────
    if ( 'learner' === $entity_type ) {
      $devis_list = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, number, emission_date, status, html_url, signed_document_url FROM {$this->quote_table}
         WHERE apprenant_id = %d ORDER BY id DESC",
        $entity_id
      ) );
    } elseif ( 'company' === $entity_type ) {
      $devis_list = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, number, emission_date, status, html_url, signed_document_url FROM {$this->quote_table}
         WHERE company_id = %d ORDER BY id DESC",
        $entity_id
      ) );
    } elseif ( 'formation' === $entity_type ) {
      $devis_list = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, number, emission_date, status, html_url, signed_document_url FROM {$this->quote_table}
         WHERE formation_id = %d ORDER BY id DESC",
        $entity_id
      ) );
    } else {
      $devis_list = array();
    }
    foreach ( (array) $devis_list as $d ) {
      $doc_url = ! empty( $d->signed_document_url ) ? (string) $d->signed_document_url
               : ( ! empty( $d->html_url ) ? (string) $d->html_url : '' );
      $st = (string) $d->status;
      $st_label = 'signe' === $st ? ' ✅' : ( 'a_signer' === $st ? ' ⏳' : '' );
      $pieces[] = array(
        'type'   => 'devis',
        'label'  => 'Devis ' . esc_html( (string) $d->number ) . $st_label,
        'date'   => ! empty( $d->emission_date ) ? wp_date( 'd/m/Y', strtotime( (string) $d->emission_date ) ) : '—',
        'url'    => $doc_url,
        'status' => $st,
        'id'     => (int) $d->id,
      );
    }

    // ── 4. Feuilles d'émargement (sig_requests type emargement) ──────────
    $sig_table = $GLOBALS['wpdb']->prefix . 'acdc_sig_requests';
    if ( 'learner' === $entity_type ) {
      $sigs = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, doc_type, signer_name, signed_at, status, signed_doc_url, doc_url FROM $sig_table
         WHERE learner_id = %d AND doc_type = 'emargement' ORDER BY id DESC",
        $entity_id
      ) );
    } elseif ( 'session' === $entity_type ) {
      $sigs = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, doc_type, signer_name, signed_at, status, signed_doc_url, doc_url FROM $sig_table
         WHERE session_id = %d AND doc_type = 'emargement' ORDER BY id DESC",
        $entity_id
      ) );
    } else {
      $sigs = array();
    }
    foreach ( (array) $sigs as $s ) {
      $doc_url = ! empty( $s->signed_doc_url ) ? (string) $s->signed_doc_url : ( ! empty( $s->doc_url ) ? (string) $s->doc_url : '' );
      $st = (string) $s->status;
      $st_label = 'signe' === $st ? ' ✅' : ' ⏳';
      $pieces[] = array(
        'type'   => 'emargement',
        'label'  => "Émargement — " . sanitize_text_field( (string) $s->signer_name ) . $st_label,
        'date'   => ! empty( $s->signed_at ) ? wp_date( 'd/m/Y', strtotime( (string) $s->signed_at ) ) : '—',
        'url'    => $doc_url,
        'status' => $st,
        'id'     => (int) $s->id,
      );
    }

    // ── 5. Enquêtes (questionnaire_sessions) ──────────────────────────────
    if ( 'formation' === $entity_type ) {
      $enquetes = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, session_title, session_date, status, public_url FROM {$this->questionnaire_session_table}
         WHERE formation_id = %d ORDER BY id DESC",
        $entity_id
      ) );
    } elseif ( 'session' === $entity_type ) {
      $enquetes = $wpdb->get_results( $wpdb->prepare(
        "SELECT id, session_title, session_date, status, public_url FROM {$this->questionnaire_session_table}
         WHERE seance_id = %d ORDER BY id DESC",
        $entity_id
      ) );
    } else {
      $enquetes = array();
    }
    foreach ( (array) $enquetes as $e ) {
      $st = (string) $e->status;
      $st_label = 'cloture' === $st ? ' ✅' : ( 'en_cours' === $st ? ' 🟢' : ' ⏳' );
      $pieces[] = array(
        'type'   => 'enquete',
        'label'  => 'Enquête : ' . sanitize_text_field( (string) $e->session_title ) . $st_label,
        'date'   => ! empty( $e->session_date ) ? wp_date( 'd/m/Y', strtotime( (string) $e->session_date ) ) : '—',
        'url'    => (string) $e->public_url,
        'status' => $st,
        'id'     => (int) $e->id,
      );
    }

    return $pieces;
  }

}

