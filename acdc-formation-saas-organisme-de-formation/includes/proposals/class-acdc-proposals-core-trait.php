<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * ACDC 3.21.22-hotfix7 — Module Propositions Commerciales
 * Gestion des données : table, getters, helpers images thématique
 */
trait Acdc_Proposals_Core_Trait {

  /* ---------------------------------------------------------------
   * Table et initialisation
   * --------------------------------------------------------------- */
  private function get_proposal_table() {
    global $wpdb;
    return $wpdb->prefix . 'acdc_of_proposals';
  }

  private function maybe_create_proposal_table() {
    global $wpdb;
    $table = $this->get_proposal_table();
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      need_id BIGINT UNSIGNED DEFAULT NULL,
      formation_id BIGINT UNSIGNED DEFAULT NULL,
      company_id BIGINT UNSIGNED DEFAULT NULL,
      contact_id BIGINT UNSIGNED DEFAULT NULL,
      title VARCHAR(255) DEFAULT '',
      client_name VARCHAR(190) DEFAULT '',
      client_title VARCHAR(190) DEFAULT '',
      client_company VARCHAR(190) DEFAULT '',
      client_siret VARCHAR(50) DEFAULT '',
      client_address TEXT,
      client_activity TEXT,
      client_website VARCHAR(255) DEFAULT '',
      client_about_text LONGTEXT,
      formation_title VARCHAR(255) DEFAULT '',
      formation_duration VARCHAR(100) DEFAULT '',
      formation_days INT UNSIGNED DEFAULT 1,
      formation_hours_per_day INT UNSIGNED DEFAULT 7,
      formation_price_per_day DECIMAL(10,2) DEFAULT 0,
      formation_total DECIMAL(10,2) DEFAULT 0,
      formation_learners_count INT UNSIGNED DEFAULT 1,
      formation_funding VARCHAR(190) DEFAULT '',
      formation_dates TEXT,
      formation_location TEXT,
      formation_objectives LONGTEXT,
      formation_program LONGTEXT,
      formation_methods LONGTEXT,
      trainer_ids VARCHAR(255) DEFAULT '',
      status VARCHAR(50) DEFAULT 'brouillon',
      pdf_url TEXT,
      created_by BIGINT UNSIGNED DEFAULT NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY need_id (need_id),
      KEY formation_id (formation_id),
      KEY company_id (company_id),
      KEY status (status)
    ) {$charset_collate};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    /* --- Colonnes supplémentaires ajoutées en hotfix7 --- */
    $this->maybe_add_table_column( $table, 'thematique',           "VARCHAR(60) DEFAULT NULL" );
    $this->maybe_add_table_column( $table, 'about_project',        'LONGTEXT' );
    $this->maybe_add_table_column( $table, 'custom_objectives',    'LONGTEXT' );
    $this->maybe_add_table_column( $table, 'custom_methods',       'LONGTEXT' );
    $this->maybe_add_table_column( $table, 'custom_prerequisites', 'TEXT' );
    $this->maybe_add_table_column( $table, 'program_j1',           'LONGTEXT' );
    $this->maybe_add_table_column( $table, 'program_j2',           'LONGTEXT' );
    $this->maybe_add_table_column( $table, 'program_j3',           'LONGTEXT' );
    $this->maybe_add_table_column( $table, 'program_j4',           'LONGTEXT' );
    $this->maybe_add_table_column( $table, 'program_j5',           'LONGTEXT' );
    $this->maybe_add_table_column( $table, 'program_j6',           'LONGTEXT' );
    $this->maybe_add_table_column( $table, 'program_j7',           'LONGTEXT' );
    $this->maybe_add_table_column( $table, 'program_j8',           'LONGTEXT' );
    $this->maybe_add_table_column( $table, 'program_j9',           'LONGTEXT' );
    $this->maybe_add_table_column( $table, 'program_j10',          'LONGTEXT' );
    $this->maybe_add_table_column( $table, 'extra_resources',      'LONGTEXT' );
    /* 3.21.26 — bios formateurs surchargeables par proposition (JSON {id: bio}) */
    $this->maybe_add_table_column( $table, 'trainer_bios_json',       'LONGTEXT' );
    /* 3.21.26 — modalités d'évaluation surchargeables par proposition */
    $this->maybe_add_table_column( $table, 'custom_evaluation',       'LONGTEXT' );
    /* 3.21.26 — overrides par proposition (fallback sur Réglages si vide) */
    $this->maybe_add_table_column( $table, 'about_acdc_text',      'LONGTEXT' );
    $this->maybe_add_table_column( $table, 'approach_before',      'LONGTEXT' );
    $this->maybe_add_table_column( $table, 'approach_during',      'LONGTEXT' );
    $this->maybe_add_table_column( $table, 'approach_after',       'LONGTEXT' );
    $this->maybe_add_table_column( $table, 'access_resources',     'LONGTEXT' );
    $this->maybe_add_table_column( $table, 'access_conditions',    'LONGTEXT' );
    $this->maybe_add_table_column( $table, 'formation_public',        'TEXT' );
    $this->maybe_add_table_column( $table, 'proposal_deadline',       'TEXT' );
    $this->maybe_add_table_column( $table, 'formation_discount',      'TEXT' );
    $this->maybe_add_table_column( $table, 'extra_resources_label',   'TEXT' );
    $this->maybe_add_table_column( $table, 'travel_costs_label',      'TEXT' );
    $this->maybe_add_table_column( $table, 'proposal_validity_months','TINYINT UNSIGNED NOT NULL DEFAULT 3' );
    /* hotfix50 — email du signataire de la proposition */
    $this->maybe_add_table_column( $table, 'client_email', "VARCHAR(255) DEFAULT ''" );
    /* hotfix60 — date du dernier envoi email */
    $this->maybe_add_table_column( $table, 'last_sent_at', 'DATETIME DEFAULT NULL' );
    /* Colonne source_prospect_id native pour éviter la dépendance au JOIN need */
    $this->maybe_add_table_column( $table, 'source_prospect_id', 'BIGINT UNSIGNED DEFAULT NULL' );
    /* 3.24.65 — code postal et ville client */
    $this->maybe_add_table_column( $table, 'client_postal_code', "VARCHAR(20) DEFAULT ''" );
    $this->maybe_add_table_column( $table, 'client_city',        "VARCHAR(120) DEFAULT ''" );

    /* Backfill source_prospect_id pour les propositions existantes (via need_id → recueil) */
    $need_table = $wpdb->prefix . 'acdc_of_needs';
    $wpdb->query(
      "UPDATE {$table} p
       INNER JOIN {$need_table} n ON n.id = p.need_id
       SET p.source_prospect_id = n.source_prospect_id
       WHERE p.source_prospect_id IS NULL
         AND n.source_prospect_id IS NOT NULL"
    );
  }

  /* ---------------------------------------------------------------
   * CRUD
   * --------------------------------------------------------------- */
  private function get_proposals( $args = array() ) {
    global $wpdb;
    $table = $this->get_proposal_table();
    $where = '1=1';
    $values = array();
    if ( ! empty( $args['need_id'] ) ) {
      $where .= ' AND p.need_id = %d';
      $values[] = (int) $args['need_id'];
    }
    $sql = "SELECT p.* FROM {$table} p WHERE {$where} ORDER BY p.created_at DESC LIMIT 100";
    if ( $values ) {
      return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
    }
    return $wpdb->get_results( $sql );
  }

  private function get_proposals_all( $args = array() ) {
    global $wpdb;
    $table      = $this->get_proposal_table();
    $need_table = $this->need_table;
    $where  = '1=1';
    $values = array();
    if ( ! empty( $args['status'] ) ) {
      $where   .= ' AND p.status = %s';
      $values[] = (string) $args['status'];
    }
    if ( ! empty( $args['search'] ) ) {
      $like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
      $where   .= ' AND (p.formation_title LIKE %s OR p.client_company LIKE %s OR p.client_name LIKE %s)';
      $values[] = $like;
      $values[] = $like;
      $values[] = $like;
    }
    $limit = ! empty( $args['limit'] ) ? (int) $args['limit'] : 200;
    $sql   = "SELECT p.*, COALESCE( p.source_prospect_id, n.source_prospect_id ) AS source_prospect_id FROM {$table} p LEFT JOIN {$need_table} n ON n.id = p.need_id WHERE {$where} ORDER BY p.created_at DESC LIMIT {$limit}";
    if ( $values ) {
      return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
    }
    return $wpdb->get_results( $sql );
  }

  private function get_proposal( $id ) {
    global $wpdb;
    $table      = $this->get_proposal_table();
    $need_table = $wpdb->prefix . 'acdc_of_needs';
    $row = $wpdb->get_row( $wpdb->prepare(
      "SELECT p.*, n.source_prospect_id AS need_source_prospect_id
       FROM {$table} p
       LEFT JOIN {$need_table} n ON n.id = p.need_id
       WHERE p.id = %d
       LIMIT 1",
      (int) $id
    ) );
    if ( ! $row ) {
      return null;
    }
    // Priorité 1 : colonne native source_prospect_id sur la proposition
    // Priorité 2 : via le recueil lié
    if ( empty( $row->source_prospect_id ) && ! empty( $row->need_source_prospect_id ) ) {
      $row->source_prospect_id = (int) $row->need_source_prospect_id;
    }
    // Priorité 3 : via company_id → email de l'entreprise → prospect correspondant
    if ( empty( $row->source_prospect_id ) && ! empty( $row->company_id ) ) {
      $company_table  = $wpdb->prefix . 'acdc_of_companies';
      $prospect_table = $wpdb->prefix . 'acdc_of_prospects';
      $company = $wpdb->get_row( $wpdb->prepare(
        "SELECT name, email FROM {$company_table} WHERE id = %d LIMIT 1",
        (int) $row->company_id
      ) );
      if ( $company ) {
        $pid = 0;
        if ( ! empty( $company->email ) ) {
          $pid = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$prospect_table}
             WHERE email = %s
               AND profile_type IN ('Entreprise','Indépendant','Entreprise / indépendant')
             ORDER BY id DESC LIMIT 1",
            (string) $company->email
          ) );
        }
        if ( ! $pid && ! empty( $company->name ) ) {
          $pid = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$prospect_table}
             WHERE company_name = %s
               AND profile_type IN ('Entreprise','Indépendant','Entreprise / indépendant')
             ORDER BY id DESC LIMIT 1",
            (string) $company->name
          ) );
        }
        if ( $pid ) {
          $row->source_prospect_id = $pid;
        }
      }
    }
    return $row;
  }

  private function save_proposal( $data ) {
    global $wpdb;
    $table   = $this->get_proposal_table();
    $now     = current_time( 'mysql' );
    $id      = ! empty( $data['id'] ) ? (int) $data['id'] : 0;
    unset( $data['id'] );
    $data['updated_at'] = $now;
    if ( $id ) {
      $wpdb->update( $table, $data, array( 'id' => $id ) );
      return $id;
    }
    $data['created_at'] = $now;
    $data['created_by'] = get_current_user_id();
    $wpdb->insert( $table, $data );
    return (int) $wpdb->insert_id;
  }

  private function delete_proposal( $id ) {
    global $wpdb;
    return $wpdb->delete( $this->get_proposal_table(), array( 'id' => (int) $id ) );
  }

  /* ---------------------------------------------------------------
   * Helpers
   * --------------------------------------------------------------- */
  private function get_proposal_status_labels() {
    return array(
      'brouillon' => 'Brouillon',
      'envoyee'   => 'Envoy&#233;e',
      'acceptee'  => 'Accept&#233;e',
      'refusee'   => 'Refus&#233;e',
      'expiree'   => 'Expir&#233;e',
    );
  }

  /**
   * Mapping thématique → URLs images ACDC propositions.
   * Retourne un tableau ['cover' => url, 'programme' => url] ou tableau vide.
   */
  private function get_proposal_thematique_images( $thematique_code ) {
    $base = 'https://acdcformation.com/wp-content/uploads/2026/05/';
    $map = array(
      'intelligence_artificielle' => array(
        'cover'     => $base . 'Intelligence-artificielle-Propal.png',
        'programme' => $base . 'Programme-intelligence-arificielle-propal.png',
      ),
      'management_leadership' => array(
        'cover'     => $base . 'Management-leadership-Propal.png',
        'programme' => $base . 'Programme-Management-leadership-propal.png',
      ),
      'management_restauration' => array(
        'cover'     => $base . 'Management-en-restauration-Propal.png',
        'programme' => $base . 'Programme-Management-en-restauration-propal.png',
      ),
      'hygiene_alimentaire' => array(
        'cover'     => $base . 'Hygiene-alimentaire-Propal.png',
        'programme' => $base . 'Programme-Hygiene-alimentaire-propal.png',
      ),
      'marketing_digital' => array(
        'cover'     => $base . 'Marketing-digital-WordPress-Propal.png',
        'programme' => $base . 'Programme-Marketing-digital-WordPress-propal.png',
      ),
      'soft_skills' => array(
        'cover'     => $base . 'Soft-skills-Propal.png',
        'programme' => $base . 'Programme-Soft-skills-propal.png',
      ),
      'automatisation_no_code' => array(
        'cover'     => $base . 'Automatisation-No-code-Propal.png',
        'programme' => $base . 'Programme-Automatisation-No-code-propal.png',
      ),
    );
    $code = (string) $thematique_code;
    if ( isset( $map[ $code ] ) ) {
      return $map[ $code ];
    }
    /* Tentative de correspondance souple (tiret ↔ underscore) */
    $normalized = strtolower( str_replace( array( '-', ' ' ), '_', $code ) );
    if ( isset( $map[ $normalized ] ) ) {
      return $map[ $normalized ];
    }
    return array();
  }

  /** URLs images fixes communes à toutes les propositions */
  private function get_proposal_static_images() {
    $base = 'https://acdcformation.com/wp-content/uploads/2026/05/';
    return array(
      'votre_projet'        => $base . 'Votre-projet-vos-besoin-propal.png',
      'avant_pendant_apres' => $base . 'avant-pendant-apres-propal.png',
      'image_apropos'       => $base . 'image-a-propos-propal.png',
      'nous_formateurs'     => $base . 'Nous-formateurs-propal.png',
      'nous'                => $base . 'Nous-propal.png',
      'objectif'            => $base . 'Objectif-propal.png',
      'objectifs_methodes'  => $base . 'Objectifs-Methodes-propal.png',
      'ressources'          => $base . 'Ressources-complementaires-propal.png',
      'contact'             => $base . 'Contact-propal.png',
    );
  }

  private function build_proposal_from_need( $need_id ) {
    $need    = $this->get_need( $need_id );
    $data    = array(
      'need_id'                => (int) $need_id,
      'formation_days'         => 1,
      'formation_hours_per_day'=> 7,
      'formation_learners_count' => 1,
      'formation_funding'      => '',
      'status'                 => 'brouillon',
    );
    if ( ! $need ) {
      return $data;
    }
    $data['formation_learners_count'] = ! empty( $need->learners_count ) ? (int) $need->learners_count : 1;
    /* Financement : si OPCO + funder_id, récupérer le nom réel du financeur */
    $funding_label = ! empty( $need->planned_funding ) ? (string) $need->planned_funding : '';
    if ( 'OPCO' === $funding_label && ! empty( $need->funder_id ) ) {
      $funder_obj = method_exists( $this, 'get_funder' ) ? $this->get_funder( (int) $need->funder_id ) : null;
      if ( $funder_obj && ! empty( $funder_obj->name ) ) {
        $funding_label = 'OPCO — ' . (string) $funder_obj->name;
      }
    }
    $data['formation_funding'] = $funding_label;
    $data['formation_title']          = ! empty( $need->theme ) ? (string) $need->theme : '';
    $data['formation_public']         = ! empty( $need->target_audience ) ? strip_tags( (string) $need->target_audience ) : '';
    if ( ! empty( $need->company_id ) ) {
      $company = $this->get_company( (int) $need->company_id );
      if ( $company ) {
        $data['company_id']         = (int) $company->id;
        $data['client_company']     = (string) $company->name;
        $data['client_siret']       = ! empty( $company->siret ) ? (string) $company->siret : '';
        $data['client_address']     = ! empty( $company->address ) ? (string) $company->address : '';
        $data['client_postal_code'] = ! empty( $company->postal_code ) ? (string) $company->postal_code : '';
        $data['client_city']        = ! empty( $company->city ) ? (string) $company->city : '';
        $data['client_activity']    = ! empty( $company->activity ) ? (string) $company->activity : '';
        $data['client_website']     = ! empty( $company->website ) ? (string) $company->website : '';
      }
    }
    // ACDC 3.25.60 — Fallback : chercher l'entreprise via le prospect si company_id absent de la NAD
    if ( empty( $data['client_company'] ) && ! empty( $need->source_prospect_id ) ) {
      $prospect_for_company = $this->get_prospect( (int) $need->source_prospect_id );
      if ( $prospect_for_company && ! empty( $prospect_for_company->company_id ) ) {
        $company_from_prospect = $this->get_company( (int) $prospect_for_company->company_id );
        if ( $company_from_prospect ) {
          $data['company_id']         = (int) $company_from_prospect->id;
          $data['client_company']     = (string) $company_from_prospect->name;
          $data['client_siret']       = ! empty( $company_from_prospect->siret )       ? (string) $company_from_prospect->siret       : '';
          $data['client_address']     = ! empty( $company_from_prospect->address )     ? (string) $company_from_prospect->address     : '';
          $data['client_postal_code'] = ! empty( $company_from_prospect->postal_code ) ? (string) $company_from_prospect->postal_code : '';
          $data['client_city']        = ! empty( $company_from_prospect->city )        ? (string) $company_from_prospect->city        : '';
          $data['client_activity']    = ! empty( $company_from_prospect->activity )    ? (string) $company_from_prospect->activity    : '';
          $data['client_website']     = ! empty( $company_from_prospect->website )     ? (string) $company_from_prospect->website     : '';
        }
      }
      // Fallback 2 : chercher via le champ company (nom) du prospect
      if ( empty( $data['client_company'] ) && ! empty( $prospect_for_company ) && ! empty( $prospect_for_company->company ) ) {
        $data['client_company'] = (string) $prospect_for_company->company;
        if ( ! empty( $prospect_for_company->siret ) ) { $data['client_siret'] = (string) $prospect_for_company->siret; }
      }
    }
    if ( ! empty( $need->contact_id ) ) {
      $contact = $this->get_contact( (int) $need->contact_id );
      if ( $contact ) {
        $data['contact_id']   = (int) $contact->id;
        $data['client_name']  = trim( (string) $contact->first_name . ' ' . (string) $contact->last_name );
        $data['client_title'] = ! empty( $contact->job_title ) ? (string) $contact->job_title : '';
      }
    }
    /* Fallback signataire de l'entreprise si pas de contact lié */
    if ( empty( $data['client_name'] ) && ! empty( $need->company_id ) ) {
      if ( empty( $company ) ) {
        $company = $this->get_company( (int) $need->company_id );
      }
      if ( $company ) {
        $signer_name = trim( ( ! empty( $company->signer_first_name ) ? (string) $company->signer_first_name : '' ) . ' ' . ( ! empty( $company->signer_last_name ) ? (string) $company->signer_last_name : '' ) );
        if ( $signer_name ) {
          $data['client_name']  = $signer_name;
          $data['client_title'] = ! empty( $company->signer_quality ) ? (string) $company->signer_quality : '';
        }
      }
    }

    /* hotfix50 — importer l'email + données depuis le prospect lié au besoin */
    $client_email = '';
    if ( ! empty( $need->source_prospect_id ) ) {
      $prospect = $this->get_prospect( (int) $need->source_prospect_id );
      if ( $prospect ) {
        $client_email = $this->get_prospect_primary_email( $prospect );
        /* Signataire depuis le prospect (priorité sur contact et entreprise) */
        $prospect_signer = trim( ( ! empty( $prospect->signer_first_name ) ? (string) $prospect->signer_first_name : '' ) . ' ' . ( ! empty( $prospect->signer_last_name ) ? (string) $prospect->signer_last_name : '' ) );
        if ( $prospect_signer ) {
          $data['client_name']  = $prospect_signer;
          $data['client_title'] = ! empty( $prospect->signer_quality ) ? (string) $prospect->signer_quality : '';
        }
        /* Adresse prospect → proposition */
        if ( empty( $data['client_address'] )     && ! empty( $prospect->address ) )     { $data['client_address']     = (string) $prospect->address; }
        if ( empty( $data['client_postal_code'] ) && ! empty( $prospect->postal_code ) ) { $data['client_postal_code'] = (string) $prospect->postal_code; }
        if ( empty( $data['client_city'] )        && ! empty( $prospect->city ) )        { $data['client_city']        = (string) $prospect->city; }
        /* Site web et activité depuis le prospect */
        if ( empty( $data['client_website'] )  && ! empty( $prospect->website ) )  { $data['client_website']  = (string) $prospect->website; }
        if ( empty( $data['client_activity'] ) && ! empty( $prospect->activity ) ) { $data['client_activity'] = (string) $prospect->activity; }
      }
    }
    if ( ! $client_email && ! empty( $contact ) && ! empty( $contact->email ) ) {
      $client_email = sanitize_email( (string) $contact->email );
    }
    if ( $client_email ) {
      $data['client_email'] = $client_email;
    }
    return $data;
  }

  /**
   * ACDC 3.25.217 — L'adresse à qui part une proposition, en un seul endroit.
   *
   * Trois sources, dans cet ordre : l'adresse saisie sur la proposition, le
   * contact rattaché, puis le prospect du recueil d'origine. C'est exactement
   * la cascade qu'appliquait l'envoi ; elle est désormais lisible aussi par
   * l'écran, qui grisait son bouton faute de la connaître.
   */
  private function acdc_proposal_recipient_email( $proposal ) {
    if ( empty( $proposal ) ) {
      return '';
    }

    if ( ! empty( $proposal->client_email ) ) {
      $email = sanitize_email( (string) $proposal->client_email );
      if ( is_email( $email ) ) {
        return $email;
      }
    }

    if ( ! empty( $proposal->contact_id ) ) {
      $contact = $this->get_contact( (int) $proposal->contact_id );
      if ( $contact && ! empty( $contact->email ) ) {
        $email = sanitize_email( (string) $contact->email );
        if ( is_email( $email ) ) {
          return $email;
        }
      }
    }

    if ( ! empty( $proposal->need_id ) ) {
      global $wpdb;
      $need_row = $wpdb->get_row( $wpdb->prepare(
        "SELECT source_prospect_id FROM {$this->need_table} WHERE id = %d LIMIT 1",
        (int) $proposal->need_id
      ) );
      if ( $need_row && ! empty( $need_row->source_prospect_id ) ) {
        $prospect = $this->get_prospect( (int) $need_row->source_prospect_id );
        if ( $prospect ) {
          $email = sanitize_email( (string) $this->get_prospect_primary_email( $prospect ) );
          if ( is_email( $email ) ) {
            return $email;
          }
        }
      }
    }

    return '';
  }

  /* ---------------------------------------------------------------
   * Récupère les champs de besoin du recueil le plus récent lié au prospect
   * --------------------------------------------------------------- */
  private function get_need_fields_for_proposal( $need_id ) {
    if ( ! $need_id ) {
      return array();
    }
    global $wpdb;
    $table = $this->need_table;

    /* Récupérer le source_prospect_id du recueil passé en paramètre */
    $ref = $wpdb->get_row( $wpdb->prepare(
      "SELECT source_prospect_id FROM {$table} WHERE id = %d LIMIT 1",
      (int) $need_id
    ) );

    if ( $ref && ! empty( $ref->source_prospect_id ) ) {
      /* Recueil le plus récent du prospect */
      $need = $wpdb->get_row( $wpdb->prepare(
        "SELECT expressed_need, reframed_need, context_text, current_situation FROM {$table} WHERE source_prospect_id = %d ORDER BY created_at DESC LIMIT 1",
        (int) $ref->source_prospect_id
      ) );
    } else {
      /* Pas de prospect lié — utiliser le recueil lui-même */
      $need = $wpdb->get_row( $wpdb->prepare(
        "SELECT expressed_need, reframed_need, context_text, current_situation FROM {$table} WHERE id = %d LIMIT 1",
        (int) $need_id
      ) );
    }

    if ( ! $need ) {
      return array();
    }
    return array(
      'expressed_need'   => ! empty( $need->expressed_need )   ? (string) $need->expressed_need   : '',
      'reframed_need'    => ! empty( $need->reframed_need )    ? (string) $need->reframed_need    : '',
      'context_text'     => ! empty( $need->context_text )     ? (string) $need->context_text     : '',
      'current_situation'=> ! empty( $need->current_situation ) ? (string) $need->current_situation : '',
    );
  }

  /* ---------------------------------------------------------------
   * Génération IA — À propos du client + Projet & besoins
   * Retourne : ['success'=>true, 'about'=>'...', 'project'=>'...']
   * --------------------------------------------------------------- */
  private function generate_proposal_about_via_openai( $client_company, $client_website, $client_activity, $need_fields = array() ) {
    $api_key = $this->get_openai_api_key();
    if ( empty( $api_key ) ) {
      return array( 'success' => false, 'error' => 'Clé API OpenAI non configurée dans les réglages.' );
    }

    $url_hint = ! empty( $client_website ) ? ' Site web : ' . $client_website . '.' : '';

    /* Construire le contexte issu du recueil des besoins */
    $need_context = '';
    if ( ! empty( $need_fields ) ) {
      $parts = array();
      if ( ! empty( $need_fields['expressed_need'] ) )    { $parts[] = 'Besoin exprim\u00e9 : '    . $need_fields['expressed_need']; }
      if ( ! empty( $need_fields['reframed_need'] ) )     { $parts[] = 'Besoin reformul\u00e9 : '  . $need_fields['reframed_need']; }
      if ( ! empty( $need_fields['context_text'] ) )      { $parts[] = 'Contexte : '               . $need_fields['context_text']; }
      if ( ! empty( $need_fields['current_situation'] ) ) { $parts[] = 'Situation actuelle : '     . $need_fields['current_situation']; }
      if ( $parts ) {
        $need_context = "\n\nDonnees du recueil des besoins :\n" . implode( "\n", $parts );
      }
    }

    $prompt = 'Tu es un expert en redaction de propositions commerciales pour un organisme de formation.'
      . ' Reponds UNIQUEMENT en JSON valide avec exactement deux cles : "about" et "project".'
      . ' Entreprise cliente : ' . ( $client_company ?: 'non precisee' ) . '.'
      . $url_hint
      . ' Activite : ' . ( $client_activity ?: 'non precisee' ) . '.'
      . $need_context
      . "\n\n"
      . '"about" (150 a 220 mots, EXACTEMENT 3 paragraphes separes par \\n\\n, sans titre) :'
      . ' Paragraphe 1 : qui est cette entreprise, son activite, son positionnement.'
      . ' Paragraphe 2 : son public, ses clients, sa valeur ajoutee.'
      . ' Paragraphe 3 : le contexte dans lequel la formation s\'inscrit.'
      . ( $need_context ? ' Integre les elements du recueil des besoins.' : '' )
      . "\n"
      . '"project" (120 a 180 mots, 2 paragraphes separes par \\n\\n, sans titre) : Reformule le projet de formation et les besoins specifiques de cette entreprise'
      . ( $need_context ? ', en t\'appuyant sur les elements du recueil des besoins,' : ',' )
      . ' style "Suite a nos echanges, vous souhaitez...". Style professionnel.';

    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
      'timeout' => 45,
      'headers' => array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type'  => 'application/json',
      ),
      'body' => wp_json_encode( array(
        'model'           => 'gpt-4o-mini',
        'max_tokens'      => 800,
        'response_format' => array( 'type' => 'json_object' ),
        'messages'        => array(
          array( 'role' => 'user', 'content' => $prompt ),
        ),
      ) ),
    ) );

    if ( is_wp_error( $response ) ) {
      return array( 'success' => false, 'error' => 'Erreur réseau : ' . $response->get_error_message() );
    }
    $http_code = (int) wp_remote_retrieve_response_code( $response );
    $raw_body  = wp_remote_retrieve_body( $response );
    $body      = json_decode( $raw_body, true );

    if ( ! empty( $body['error']['message'] ) ) {
      return array( 'success' => false, 'error' => 'OpenAI : ' . sanitize_text_field( (string) $body['error']['message'] ) );
    }

    $content = isset( $body['choices'][0]['message']['content'] ) ? trim( (string) $body['choices'][0]['message']['content'] ) : '';
    if ( empty( $content ) ) {
      $hint = $http_code !== 200 ? ' (HTTP ' . $http_code . ')' : '';
      return array( 'success' => false, 'error' => "Réponse vide de l'API OpenAI" . $hint . '.' );
    }

    /* Parser le JSON retourné par le modèle */
    $parsed  = json_decode( $content, true );
    $about   = is_array( $parsed ) && ! empty( $parsed['about'] )   ? trim( (string) $parsed['about'] )   : '';
    $project = is_array( $parsed ) && ! empty( $parsed['project'] ) ? trim( (string) $parsed['project'] ) : '';

    if ( empty( $about ) ) {
      /* Fallback : le modèle n'a pas respecté le format JSON — utiliser le texte brut */
      $about = $content;
    }

    return array( 'success' => true, 'about' => $about, 'project' => $project );
  }

  /* ---------------------------------------------------------------
   * Clé API OpenAI
   * --------------------------------------------------------------- */
  private function get_openai_api_key() {
    $opts = get_option( 'acdc_of_integrations', array() );
    return ! empty( $opts['openai_api_key'] ) ? (string) $opts['openai_api_key'] : '';
  }

  private function save_openai_api_key( $key ) {
    $opts = get_option( 'acdc_of_integrations', array() );
    if ( ! is_array( $opts ) ) {
      $opts = array();
    }
    $opts['openai_api_key'] = sanitize_text_field( $key );
    update_option( 'acdc_of_integrations', $opts );
  }

  /* ---------------------------------------------------------------
   * Génération IA — Objectifs pédagogiques (taxonomie de Bloom)
   * Retourne : ['success'=>true, 'objectives'=>'...']
   * --------------------------------------------------------------- */
  private function generate_proposal_objectives_via_openai( $formation_id ) {
    $api_key = $this->get_openai_api_key();
    if ( empty( $api_key ) ) {
      return array( 'success' => false, 'error' => "Clé API OpenAI non configurée dans les réglages." );
    }
    $formation = $this->get_formation( (int) $formation_id );
    if ( ! $formation ) {
      return array( 'success' => false, 'error' => 'Formation introuvable.' );
    }
    /* Chercher le programme dans program, puis programme_detail en fallback */
    $program_raw = ! empty( $formation->program )
      ? strip_tags( (string) $formation->program )
      : ( ! empty( $formation->programme_detail ) ? strip_tags( (string) $formation->programme_detail ) : '' );
    if ( empty( trim( $program_raw ) ) ) {
      return array( 'success' => false, 'error' => "Le programme de cette formation est vide — remplissez le champ Programme dans la fiche formation." );
    }
    $titre = ! empty( $formation->title ) ? (string) $formation->title : 'Formation professionnelle';
    $prompt = 'Tu es un ingenieur pedagogique expert en conception de formations professionnelles.'
      . ' En te basant sur le programme de formation ci-dessous, redige entre 5 et 6 objectifs pedagogiques en francais'
      . ' en utilisant la taxonomie de Bloom (verbes d\'action : identifier, analyser, creer, evaluer, appliquer, distinguer, concevoir, etc.).'
      . ' Chaque objectif commence par le verbe d\'action directement (sans "A l\'issue de la formation").'
      . ' Un objectif par ligne. Pas de numerotation, pas de tirets, pas de puces. Texte brut uniquement.'
      . ' Formation : ' . $titre . '.'
      . "\n\nProgramme :\n" . mb_substr( $program_raw, 0, 3000 );

    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
      'timeout' => 30,
      'headers' => array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type'  => 'application/json',
      ),
      'body' => wp_json_encode( array(
        'model'      => 'gpt-4o-mini',
        'max_tokens' => 600,
        'messages'   => array(
          array( 'role' => 'user', 'content' => $prompt ),
        ),
      ) ),
    ) );

    if ( is_wp_error( $response ) ) {
      return array( 'success' => false, 'error' => 'Erreur réseau : ' . $response->get_error_message() );
    }
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! empty( $body['error']['message'] ) ) {
      return array( 'success' => false, 'error' => 'OpenAI : ' . sanitize_text_field( (string) $body['error']['message'] ) );
    }
    $text = isset( $body['choices'][0]['message']['content'] ) ? trim( (string) $body['choices'][0]['message']['content'] ) : '';
    if ( empty( $text ) ) {
      return array( 'success' => false, 'error' => "Réponse vide de l'API OpenAI." );
    }
    return array( 'success' => true, 'objectives' => $text );
  }

  /* ---------------------------------------------------------------
   * Génération IA — Résumé programme d'une journée (render_prog format)
   * --------------------------------------------------------------- */
  private function generate_proposal_program_day_via_openai( $formation_id, $day ) {
    $api_key = $this->get_openai_api_key();
    if ( empty( $api_key ) ) {
      return array( 'success' => false, 'error' => "Clé API OpenAI non configurée." );
    }
    $formation = $this->get_formation( (int) $formation_id );
    if ( ! $formation ) {
      return array( 'success' => false, 'error' => 'Formation introuvable.' );
    }
    /* Récupérer les blocs matin + apm du jour demandé dans programme_detail */
    $blocs = array();
    if ( ! empty( $formation->programme_detail ) ) {
      $dp = json_decode( (string) $formation->programme_detail, true );
      if ( is_array( $dp ) ) {
        foreach ( $dp as $b ) {
          if ( isset( $b['jour'] ) && (int) $b['jour'] === (int) $day ) {
            $blocs[ $b['moment'] ] = $b;
          }
        }
      }
    }
    /* Fallback sur program texte libre si programme_detail absent pour ce jour */
    if ( empty( $blocs ) ) {
      $prog_raw = ! empty( $formation->program ) ? strip_tags( (string) $formation->program ) : '';
      if ( empty( trim( $prog_raw ) ) ) {
        return array( 'success' => false, 'error' => "Aucun programme trouvé pour le jour {$day}. Remplissez le programme dans la fiche formation." );
      }
      $parts = preg_split( '/Jour\s+' . (int) $day . '\b/ui', $prog_raw, 2 );
      if ( isset( $parts[1] ) ) {
        $next = preg_split( '/Jour\s+' . ( (int) $day + 1 ) . '\b/ui', $parts[1], 2 );
        $source_text = "Programme Jour {$day} :\n" . trim( $next[0] );
      } else {
        $source_text = "Programme brut :\n" . trim( $prog_raw );
      }
    } else {
      $lines = array();
      foreach ( array( 'matin', 'apm' ) as $moment ) {
        if ( ! isset( $blocs[ $moment ] ) ) continue;
        $b     = $blocs[ $moment ];
        $label = $moment === 'matin' ? 'Matinée' : 'Après-midi';
        $lines[] = "{$label} :";
        if ( ! empty( $b['titre'] ) )       $lines[] = "Titre : " . $b['titre'];
        if ( ! empty( $b['contenus'] ) )    $lines[] = "Contenus : " . $b['contenus'];
        if ( ! empty( $b['opo'] ) )         $lines[] = "Objectifs : " . $b['opo'];
        if ( ! empty( $b['competences'] ) ) $lines[] = "Compétences : " . $b['competences'];
        $lines[] = '';
      }
      $source_text = implode( "\n", $lines );
    }
    $titre_formation = ! empty( $formation->title ) ? (string) $formation->title : 'Formation professionnelle';
    $prompt = 'Tu es un expert en ingénierie pédagogique.'
      . ' Résume le programme du Jour ' . (int) $day . ' de la formation "' . $titre_formation . '" en respectant EXACTEMENT ce format :'
      . "\n\nJour " . (int) $day . " — Matinée (3h30)\n[Titre exact de la session]\n• contenu 1\n• contenu 2\nCompétences développées : ...\n\nAprès-midi (3h30)\n[Titre exact de la session]\n• contenu 1\nCompétences développées : ...\n\nÉvaluation\n• item\n\n"
      . "Règles : maximum 2200 caractères, items de contenu commencent par •, "
      . "'Compétences développées :' sur la même ligne que le texte, pas d'introduction ni conclusion.\n\n"
      . "Source :\n" . mb_substr( $source_text, 0, 2500 );

    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
      'timeout' => 30,
      'headers' => array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type'  => 'application/json',
      ),
      'body' => wp_json_encode( array(
        'model'      => 'gpt-4o-mini',
        'max_tokens' => 700,
        'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
      ) ),
    ) );

    if ( is_wp_error( $response ) ) {
      return array( 'success' => false, 'error' => 'Erreur réseau : ' . $response->get_error_message() );
    }
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! empty( $body['error']['message'] ) ) {
      return array( 'success' => false, 'error' => 'OpenAI : ' . sanitize_text_field( (string) $body['error']['message'] ) );
    }
    $text = isset( $body['choices'][0]['message']['content'] ) ? trim( (string) $body['choices'][0]['message']['content'] ) : '';
    if ( empty( $text ) ) {
      return array( 'success' => false, 'error' => "Réponse vide de l'API OpenAI." );
    }
    return array( 'success' => true, 'program' => $text );
  }

}
