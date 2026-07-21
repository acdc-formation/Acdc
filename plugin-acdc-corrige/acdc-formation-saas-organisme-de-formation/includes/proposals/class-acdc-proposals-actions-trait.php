<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * ACDC 3.21.22-hotfix7 — Module Propositions Commerciales
 * Handlers AJAX, actions POST, hooks sidebar
 */
trait Acdc_Proposals_Actions_Trait {

  /* ---------------------------------------------------------------
   * Enregistrement des hooks
   * --------------------------------------------------------------- */
  private function register_proposal_hooks() {
    add_action( 'wp_ajax_acdc_proposal_generate_about',        array( $this, 'ajax_proposal_generate_about' ) );
    add_action( 'wp_ajax_acdc_proposal_generate_objectives',   array( $this, 'ajax_proposal_generate_objectives' ) );
    add_action( 'wp_ajax_acdc_proposal_generate_program_day',  array( $this, 'ajax_proposal_generate_program_day' ) );
    add_action( 'wp_ajax_acdc_proposal_save_draft',            array( $this, 'ajax_proposal_save_draft' ) );
    add_action( 'wp_ajax_acdc_proposal_generate_pdf',   array( $this, 'ajax_proposal_generate_pdf' ) );
    add_action( 'wp_ajax_acdc_proposal_save_full',      array( $this, 'ajax_proposal_save_full' ) );
    add_action( 'admin_post_acdc_delete_proposal',             array( $this, 'handle_delete_proposal' ) );
    add_action( 'admin_post_acdc_save_openai_key',             array( $this, 'handle_save_openai_key' ) );
    add_action( 'admin_post_acdc_proposal_resend_email',       array( $this, 'handle_proposal_resend_email' ) );

    /* Sidebar CRM — onglet Propositions commerciales */
    add_filter( 'acdc_portal_navigation_groups', array( $this, 'inject_proposals_sidebar_entry' ), 15, 2 );

    /* Rendu de l'onglet proposals */
    add_filter( 'acdc_portal_render_unknown_tab', array( $this, 'render_proposals_unknown_tab' ), 10, 4 );

    add_action( 'admin_post_acdc_save_proposal_global_settings', array( $this, 'handle_save_proposal_global_settings' ) );

    /* Migration one-time : descriptions formateurs par défaut */
    add_action( 'init', array( $this, 'maybe_seed_trainer_descriptions' ), 20 );
    add_action( 'init', array( $this, 'maybe_clear_accidental_global_overrides' ), 21 );
  }

  /* ---------------------------------------------------------------
   * Migration one-time — textes biographiques formateurs ACDC
   * Ne s'exécute qu'une seule fois (flag wp_option).
   * Ne modifie QUE les champs description_text actuellement vides.
   * --------------------------------------------------------------- */
  public function maybe_seed_trainer_descriptions() {
    if ( get_option( 'acdc_of_trainer_desc_seeded_v1' ) ) {
      return;
    }
    global $wpdb;
    $table = $this->trainer_table;

    $descriptions = array(
      array(
        'first_name' => 'David',
        'last_name'  => 'Contal',
        'text'       => "David est celui qui murmure à l'oreille des algorithmes pour les mettre au service de votre quotidien. Cofondateur d'ACDC Formation et formateur certifié, il injecte plus de 10 ans d'expérience terrain dans chaque session pour garantir une montée en compétences qui ne s'évapore pas une fois l'ordinateur éteint. Son terrain de jeu est vaste : IA générative appliquée, automatisation de processus (Make, Notion, Airtable), outils No-Code et marketing web. Il ne se contente pas de montrer comment utiliser ChatGPT ; il structure des ateliers de prompt engineering pour que vous soyez capables de créer des résultats percutants dès la première heure. Son approche est radicalement \"pratico-pratique\" : chaque formation WordPress ou marketing digital intègre un projet réel ou un audit, assurant un impact métier mesurable et une adoption éthique de ces nouvelles technologies.",
      ),
      array(
        'first_name' => 'Ann-Cécile',
        'last_name'  => 'Joucher',
        'text'       => "Ann-Cécile est l'architecte de nos dispositifs pédagogiques et la garante de l'équilibre entre exigence et bienveillance. Consultante-formatrice certifiée et cofondatrice, elle s'appuie sur plus de 15 ans d'expérience pour placer l'humain au cœur de la transformation durable des entreprises. Elle pilote nos pôles Management (standard et avancé) et Soft Skills. Son expertise en ingénierie pédagogique lui permet de concevoir des parcours multimodaux (présentiel, distanciel, e-learning) qui ne se contentent pas d'informer, mais qui transforment réellement les pratiques. Veillant scrupuleusement au respect des référentiels, elle assure un suivi post-formation rigoureux pour que chaque apprentissage devienne un réflexe durable.",
      ),
    );

    foreach ( $descriptions as $d ) {
      $wpdb->query( $wpdb->prepare(
        "UPDATE {$table}
         SET description_text = %s
         WHERE first_name = %s
           AND last_name  = %s
           AND ( description_text IS NULL OR description_text = '' )",
        $d['text'],
        $d['first_name'],
        $d['last_name']
      ) );
    }

    update_option( 'acdc_of_trainer_desc_seeded_v1', 1 );
  }

  /* ---------------------------------------------------------------
   * Migration one-time — vider les champs override copiés
   * accidentellement depuis get_option() lors de la sauvegarde
   * (hotfix18 — corrige le bug de pré-remplissage permanent)
   * --------------------------------------------------------------- */
  public function maybe_clear_accidental_global_overrides() {
    if ( get_option( 'acdc_of_proposal_overrides_cleared_v1' ) ) {
      return;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'acdc_of_proposals';

    $cols = array( 'about_acdc_text', 'approach_before', 'approach_during',
                   'approach_after', 'access_resources', 'access_conditions' );

    foreach ( $cols as $col ) {
      /* Vérifie que la colonne existe avant de modifier */
      $exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = %s AND COLUMN_NAME = %s",
        $table, $col
      ) );
      if ( $exists ) {
        $wpdb->query( "UPDATE {$table} SET {$col} = '' WHERE {$col} IS NOT NULL" );
      }
    }

    update_option( 'acdc_of_proposal_overrides_cleared_v1', 1 );
  }

  /* ---------------------------------------------------------------
   * Sidebar — injection de l'entrée CRM
   * --------------------------------------------------------------- */
  public function inject_proposals_sidebar_entry( $groups, $current_tab ) {
    foreach ( $groups as &$group ) {
      if (
        isset( $group['type'], $group['label'] ) &&
        'group' === $group['type'] &&
        'CRM' === $group['label']
      ) {
        $group['items'][] = array(
          'tab'   => 'proposals',
          'label' => 'Propositions commerciales',
          'icon'  => 'file-text',
        );
        break;
      }
    }
    unset( $group );
    return $groups;
  }

  /* ---------------------------------------------------------------
   * Rendu onglet proposals via filtre portal
   * --------------------------------------------------------------- */
  public function render_proposals_unknown_tab( $handled, $tab, $action, $item_id ) {
    if ( 'proposals' !== $tab ) {
      return $handled;
    }
    $this->render_proposals_tab( $tab, $action, $item_id );
    return true;
  }

  /* ---------------------------------------------------------------
   * AJAX : Générer "À propos" via OpenAI
   * --------------------------------------------------------------- */
  public function ajax_proposal_generate_about() {
    check_ajax_referer( 'acdc_proposal_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) {
      wp_send_json_error( array( 'message' => 'Permission refus&#233;e.' ) );
    }
    $client_company  = sanitize_text_field( wp_unslash( $_POST['client_company'] ?? '' ) );
    $client_website  = esc_url_raw( wp_unslash( $_POST['client_website'] ?? '' ) );
    $client_activity = sanitize_textarea_field( wp_unslash( $_POST['client_activity'] ?? '' ) );
    $need_id         = absint( $_POST['need_id'] ?? 0 );

    /* Récupérer les champs du recueil des besoins si disponible */
    $need_fields = $need_id ? $this->get_need_fields_for_proposal( $need_id ) : array();

    $result = $this->generate_proposal_about_via_openai( $client_company, $client_website, $client_activity, $need_fields );
    if ( $result['success'] ) {
      wp_send_json_success( array(
        'about'   => $result['about'],
        'project' => $result['project'],
        /* Rétrocompatibilité — ancienne clé 'text' */
        'text'    => $result['about'],
      ) );
    } else {
      wp_send_json_error( array( 'message' => $result['error'] ) );
    }
  }

  /* ---------------------------------------------------------------
   * AJAX : Générer les objectifs pédagogiques (taxonomie de Bloom)
   * --------------------------------------------------------------- */
  public function ajax_proposal_generate_objectives() {
    check_ajax_referer( 'acdc_proposal_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) {
      wp_send_json_error( array( 'message' => 'Permission refus&#233;e.' ) );
    }
    $formation_id = absint( $_POST['formation_id'] ?? 0 );
    if ( ! $formation_id ) {
      wp_send_json_error( array( 'message' => 'ID formation manquant.' ) );
    }
    $result = $this->generate_proposal_objectives_via_openai( $formation_id );
    if ( $result['success'] ) {
      wp_send_json_success( array( 'objectives' => $result['objectives'] ) );
    } else {
      wp_send_json_error( array( 'message' => $result['error'] ) );
    }
  }

  /* ---------------------------------------------------------------
   * AJAX : Générer le résumé programme d'une journée via IA
   * --------------------------------------------------------------- */
  public function ajax_proposal_generate_program_day() {
    check_ajax_referer( 'acdc_proposal_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) {
      wp_send_json_error( array( 'message' => 'Permission refusée.' ) );
    }
    $formation_id = absint( $_POST['formation_id'] ?? 0 );
    $day          = absint( $_POST['day'] ?? 0 );
    if ( ! $formation_id || ! $day || $day > 10 ) {
      wp_send_json_error( array( 'message' => 'Paramètres manquants.' ) );
    }
    $result = $this->generate_proposal_program_day_via_openai( $formation_id, $day );
    if ( $result['success'] ) {
      wp_send_json_success( array( 'program' => $result['program'], 'day' => $day ) );
    } else {
      wp_send_json_error( array( 'message' => $result['error'] ) );
    }
  }

  /* ---------------------------------------------------------------
   * AJAX : Sauvegarder le brouillon (modale 3 étapes)
   * --------------------------------------------------------------- */
  public function ajax_proposal_save_draft() {
    check_ajax_referer( 'acdc_proposal_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) {
      wp_send_json_error( array( 'message' => 'Permission refus&#233;e.' ) );
    }
    $raw  = isset( $_POST['proposal'] ) && is_array( $_POST['proposal'] ) ? $_POST['proposal'] : array();
    $data = $this->sanitize_proposal_input( $raw );
    $id   = $this->save_proposal( $data );
    if ( $id ) {
      wp_send_json_success( array( 'id' => $id, 'message' => 'Brouillon sauvegard&#233;.' ) );
    } else {
      wp_send_json_error( array( 'message' => 'Erreur lors de la sauvegarde.' ) );
    }
  }

  /* ---------------------------------------------------------------
   * AJAX : Sauvegarder formulaire complet (page Propositions)
   * --------------------------------------------------------------- */
  public function ajax_proposal_save_full() {
    check_ajax_referer( 'acdc_proposal_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) {
      wp_send_json_error( array( 'message' => 'Permission refus&#233;e.' ) );
    }
    $raw  = isset( $_POST['proposal'] ) && is_array( $_POST['proposal'] ) ? $_POST['proposal'] : array();
    $data = $this->sanitize_proposal_full_input( $raw );
    // Propager source_prospect_id depuis le recueil si pas déjà fourni
    if ( empty( $data['source_prospect_id'] ) && ! empty( $data['need_id'] ) ) {
      global $wpdb;
      $need_pid = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT source_prospect_id FROM {$this->need_table} WHERE id = %d LIMIT 1",
        (int) $data['need_id']
      ) );
      if ( $need_pid ) { $data['source_prospect_id'] = $need_pid; }
    }
    $id   = $this->save_proposal( $data );
    if ( $id ) {
      // Auto-advance prospect status → Proposition envoyée
      if ( ! empty( $data['need_id'] ) ) {
        global $wpdb;
        $need = $wpdb->get_row( $wpdb->prepare( "SELECT source_prospect_id FROM {$this->need_table} WHERE id = %d", (int) $data['need_id'] ) );
        if ( $need && ! empty( $need->source_prospect_id ) ) {
          $this->maybe_advance_prospect_status( (int) $need->source_prospect_id, 'Proposition envoyée' );
        }
      }
      $redirect = $this->portal_page_url( array( 'tab' => 'proposals', 'action' => 'view', 'item_id' => $id ) );
      wp_send_json_success( array( 'id' => $id, 'redirect' => $redirect ) );
    } else {
      wp_send_json_error( array( 'message' => 'Erreur lors de la sauvegarde.' ) );
    }
  }

  /* ---------------------------------------------------------------
   * AJAX : Générer le PDF
   * --------------------------------------------------------------- */
  public function ajax_proposal_generate_pdf() {
    check_ajax_referer( 'acdc_proposal_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) {
      wp_send_json_error( array( 'message' => 'Permission refus&#233;e.' ) );
    }
    $proposal_id = absint( $_POST['proposal_id'] ?? 0 );
    if ( ! $proposal_id ) {
      wp_send_json_error( array( 'message' => 'ID proposition manquant.' ) );
    }
    $proposal = $this->get_proposal( $proposal_id );
    if ( ! $proposal ) {
      wp_send_json_error( array( 'message' => 'Proposition introuvable.' ) );
    }
    /* Génération HTML (prioritaire — meilleur rendu visuel) */
    $html_url = method_exists( $this, 'generate_proposal_html' ) ? $this->generate_proposal_html( $proposal ) : '';
    if ( $html_url ) {
      global $wpdb;
      $wpdb->update(
        $this->get_proposal_table(),
        array( 'pdf_url' => $html_url, 'status' => 'envoyee', 'updated_at' => current_time( 'mysql' ) ),
        array( 'id' => $proposal_id )
      );
      wp_send_json_success( array( 'pdf_url' => $html_url, 'type' => 'html' ) );
      return;
    }
    /* Fallback PDF natif */
    $pdf_url = $this->generate_proposal_pdf( $proposal );
    if ( $pdf_url ) {
      global $wpdb;
      $wpdb->update(
        $this->get_proposal_table(),
        array( 'pdf_url' => $pdf_url, 'status' => 'envoyee', 'updated_at' => current_time( 'mysql' ) ),
        array( 'id' => $proposal_id )
      );
      wp_send_json_success( array( 'pdf_url' => $pdf_url, 'type' => 'pdf' ) );
      return;
    }
    wp_send_json_error( array( 'message' => 'Impossible de g&#233;n&#233;rer le document.' ) );
  }

  /* ---------------------------------------------------------------
   * POST : Supprimer une proposition
   * --------------------------------------------------------------- */
  public function handle_delete_proposal() {
    $proposal_id = absint( $_GET['proposal_id'] ?? 0 );
    if ( ! $proposal_id || ! check_admin_referer( 'acdc_delete_proposal_' . $proposal_id ) ) {
      wp_die( 'Action invalide.' );
    }
    if ( ! current_user_can( 'edit_posts' ) ) {
      wp_die( 'Permission refus&#233;e.' );
    }
    global $wpdb;
    /* Vérifier si la proposition est liée à une convention via son need */
    $proposal = $this->get_proposal( $proposal_id );
    if ( $proposal && ! empty( $proposal->need_id ) ) {
      $need = $wpdb->get_row( $wpdb->prepare(
        "SELECT source_prospect_id FROM {$this->need_table} WHERE id = %d",
        (int) $proposal->need_id
      ) );
      if ( $need && ! empty( $need->source_prospect_id ) ) {
        $convention_count = (int) $wpdb->get_var( $wpdb->prepare(
          "SELECT COUNT(*) FROM {$this->registration_contract_table} WHERE source_prospect_id = %d",
          (int) $need->source_prospect_id
        ) );
        if ( $convention_count ) {
          $redirect = isset( $_GET['redirect'] ) ? esc_url_raw( wp_unslash( $_GET['redirect'] ) ) : $this->portal_page_url( array( 'tab' => 'proposals' ) );
          wp_safe_redirect( add_query_arg( array(
            'notice'      => rawurlencode( 'Suppression impossible : cette proposition est liée à une convention ou un contrat de formation (preuve Qualiopi).' ),
            'notice_type' => 'error',
          ), $redirect ) );
          exit;
        }
      }
    }
    $this->delete_proposal( $proposal_id );
    $redirect = isset( $_GET['redirect'] ) ? esc_url_raw( wp_unslash( $_GET['redirect'] ) ) : '';
    if ( ! $redirect ) {
      $redirect = $this->portal_page_url( array( 'tab' => 'proposals' ) );
    }
    wp_safe_redirect( $redirect );
    exit;
  }

  /* ---------------------------------------------------------------
   * POST : Sauvegarder la clé API OpenAI
   * --------------------------------------------------------------- */
  public function handle_save_openai_key() {
    if ( ! check_admin_referer( 'acdc_save_openai_key' ) || ! current_user_can( 'manage_options' ) ) {
      wp_die( 'Permission refus&#233;e.' );
    }
    $key            = sanitize_text_field( wp_unslash( isset( $_POST['openai_api_key'] ) ? $_POST['openai_api_key'] : '' ) );
    $return_context = ( isset( $_POST['return_context'] ) && 'front' === $_POST['return_context'] ) ? 'front' : 'admin';
    $this->save_openai_api_key( $key );
    if ( 'front' === $return_context ) {
      wp_safe_redirect( $this->portal_page_url( array( 'tab' => 'settings', 'section' => 'integrations', 'saved' => '1' ) ) );
    } else {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-settings&tab=integrations&saved=1' ) );
    }
    exit;
  }

  /* ---------------------------------------------------------------
   * POST : Sauvegarder les réglages globaux propositions
   * --------------------------------------------------------------- */
  public function handle_save_proposal_global_settings() {
    if ( ! check_admin_referer( 'acdc_save_proposal_global_settings' ) || ! current_user_can( 'manage_options' ) ) {
      wp_die( 'Permission refusée.' );
    }
    $fields = array( 'acdc_of_proposal_about_acdc', 'acdc_of_proposal_approach_before',
                     'acdc_of_proposal_approach_during', 'acdc_of_proposal_approach_after',
                     'acdc_of_proposal_access_resources', 'acdc_of_proposal_access_conditions',
                     'acdc_of_proposal_cgv' );
    foreach ( $fields as $opt ) {
      $val = wp_unslash( $_POST[ $opt ] ?? '' );
      update_option( $opt, wp_kses_post( (string) $val ) );
    }
    $redirect = $this->portal_page_url( array( 'tab' => 'settings', 'settings_section' => 'propositions', 'saved' => '1' ) );
    wp_safe_redirect( $redirect );
    exit;
  }

  /* ---------------------------------------------------------------
   * Sanitisation saisie modale 3 étapes (existant)
   * --------------------------------------------------------------- */
  private function sanitize_proposal_input( $raw ) {
    $int_fields    = array( 'id', 'need_id', 'formation_id', 'formation_days', 'formation_hours_per_day',
                           'formation_learners_count', 'proposal_validity_months', 'source_prospect_id' );
    $float_fields  = array( 'formation_price_per_day', 'formation_total' );
    $text_fields   = array( 'formation_title', 'formation_duration', 'formation_funding', 'formation_location',
                            'formation_dates', 'trainer_ids', 'client_name', 'client_title', 'client_company',
                            'client_siret', 'client_website', 'client_activity', 'client_postal_code', 'client_city',
                            'status', 'thematique',
                            'formation_public', 'proposal_deadline', 'formation_discount',
                            'extra_resources_label', 'travel_costs_label' );
    $longtext_fields = array( 'client_address', 'client_about_text', 'formation_objectives', 'formation_program', 'formation_methods' );
    $clean = array();
    foreach ( $int_fields as $f ) {
      if ( isset( $raw[ $f ] ) ) { $clean[ $f ] = (int) $raw[ $f ]; }
    }
    foreach ( $float_fields as $f ) {
      if ( isset( $raw[ $f ] ) ) { $clean[ $f ] = (float) $raw[ $f ]; }
    }
    foreach ( $text_fields as $f ) {
      if ( isset( $raw[ $f ] ) ) { $clean[ $f ] = sanitize_text_field( wp_unslash( (string) $raw[ $f ] ) ); }
    }
    foreach ( $longtext_fields as $f ) {
      if ( isset( $raw[ $f ] ) ) { $clean[ $f ] = sanitize_textarea_field( wp_unslash( (string) $raw[ $f ] ) ); }
    }
    /* hotfix50 — email signataire : sanitize_email pour garantir un format propre */
    if ( isset( $raw['client_email'] ) ) {
      $clean['client_email'] = sanitize_email( wp_unslash( (string) $raw['client_email'] ) );
    }
    return $clean;
  }

  /* ---------------------------------------------------------------
   * Sanitisation saisie formulaire complet (hotfix7)
   * --------------------------------------------------------------- */
  private function sanitize_proposal_full_input( $raw ) {
    $base = $this->sanitize_proposal_input( $raw );
    $extra_longtext = array( 'about_project', 'custom_objectives', 'custom_methods', 'custom_evaluation',
                             'program_j1', 'program_j2', 'program_j3', 'program_j4', 'program_j5',
                             'program_j6', 'program_j7', 'program_j8', 'program_j9', 'program_j10',
                             'extra_resources',
                             'about_acdc_text', 'approach_before', 'approach_during', 'approach_after',
                             'access_resources', 'access_conditions' );
    $extra_text     = array( 'title', 'custom_prerequisites' );
    foreach ( $extra_longtext as $f ) {
      if ( isset( $raw[ $f ] ) ) { $base[ $f ] = sanitize_textarea_field( wp_unslash( (string) $raw[ $f ] ) ); }
    }
    foreach ( $extra_text as $f ) {
      if ( isset( $raw[ $f ] ) ) { $base[ $f ] = sanitize_text_field( wp_unslash( (string) $raw[ $f ] ) ); }
    }
    /* trainer_bios_json : valider que c'est du JSON bien formé, puis stocker tel quel */
    if ( isset( $raw['trainer_bios_json'] ) ) {
      $decoded = json_decode( wp_unslash( (string) $raw['trainer_bios_json'] ), true );
      if ( is_array( $decoded ) ) {
        $clean_bios = array();
        foreach ( $decoded as $tid => $bio ) {
          $clean_bios[ (int) $tid ] = sanitize_textarea_field( (string) $bio );
        }
        $base['trainer_bios_json'] = wp_json_encode( $clean_bios );
      } else {
        $base['trainer_bios_json'] = '';
      }
    }
    return $base;
  }

  /* ---------------------------------------------------------------
   * Envoi email proposition commerciale avec PDF en pièce jointe
   * Retourne true ou WP_Error.
   * --------------------------------------------------------------- */
  public function send_proposal_email( $proposal_id ) {
    $proposal = $this->get_proposal( (int) $proposal_id );
    if ( ! $proposal ) {
      return new WP_Error( 'not_found', 'Proposition introuvable.' );
    }

    /* Résolution de l'email destinataire */
    $recipient_email = '';
    $recipient_name  = trim( (string) $proposal->client_name ) ?: (string) $proposal->client_company;

    if ( ! empty( $proposal->client_email ) ) {
      $recipient_email = sanitize_email( (string) $proposal->client_email );
    }
    if ( ! $recipient_email && ! empty( $proposal->contact_id ) ) {
      $contact = $this->get_contact( (int) $proposal->contact_id );
      if ( $contact && ! empty( $contact->email ) ) {
        $recipient_email = sanitize_email( (string) $contact->email );
        if ( ! $recipient_name ) {
          $recipient_name = trim( $contact->first_name . ' ' . $contact->last_name );
        }
      }
    }
    if ( ! $recipient_email && ! empty( $proposal->need_id ) ) {
      global $wpdb;
      $need_row = $wpdb->get_row( $wpdb->prepare(
        "SELECT source_prospect_id FROM {$this->need_table} WHERE id = %d LIMIT 1",
        (int) $proposal->need_id
      ) );
      if ( $need_row && ! empty( $need_row->source_prospect_id ) ) {
        $prospect = $this->get_prospect( (int) $need_row->source_prospect_id );
        if ( $prospect ) {
          $recipient_email = $this->get_prospect_primary_email( $prospect );
        }
      }
    }

    if ( ! $recipient_email || ! is_email( $recipient_email ) ) {
      return new WP_Error( 'no_email', 'Aucun email valide trouvé. Renseignez le champ "Email du signataire" dans la fiche proposition.' );
    }

    /* Génération du fichier HTML de la proposition (lien dans le mail) */
    $proposal_html_url = $this->generate_proposal_html( $proposal );
    if ( ! $proposal_html_url ) {
      return new WP_Error( 'html_failed', 'La génération de la proposition HTML a échoué.' );
    }

    $upload_dir = wp_upload_dir();

    /* Pièces jointes : programme de formation + CGV (pas le HTML) */
    $attachments = array();
    if ( ! empty( $proposal->formation_id ) ) {
      $formation = $this->get_formation( (int) $proposal->formation_id );
      if ( $formation && ! empty( $formation->program_file_url ) ) {
        $prog_url_norm       = set_url_scheme( (string) $formation->program_file_url );
        $upload_baseurl_norm = set_url_scheme( trailingslashit( $upload_dir['baseurl'] ) );
        $prog_path = str_replace( $upload_baseurl_norm, trailingslashit( $upload_dir['basedir'] ), $prog_url_norm );
        if ( file_exists( $prog_path ) ) {
          $attachments[] = $prog_path;
        }
      }
    }
    $company_profile_attach = get_option( 'acdc_of_company_profile', array() );
    $cgu_url = ! empty( $company_profile_attach['cgu_url'] ) ? (string) $company_profile_attach['cgu_url'] : '';
    if ( $cgu_url ) {
      $cgu_url_norm        = set_url_scheme( $cgu_url );
      $upload_baseurl_norm = set_url_scheme( trailingslashit( $upload_dir['baseurl'] ) );
      $cgu_path = str_replace( $upload_baseurl_norm, trailingslashit( $upload_dir['basedir'] ), $cgu_url_norm );
      if ( file_exists( $cgu_path ) ) {
        $attachments[] = $cgu_path;
      }
    }

    /* Corps email */
    $formation_title = $proposal->formation_title ?: 'Formation professionnelle';
    $total_hours     = (int) $proposal->formation_days * (int) $proposal->formation_hours_per_day;
    $company_profile = get_option( 'acdc_of_company_profile', array() );
    $acdc_name       = ! empty( $company_profile['company_name'] ) ? $company_profile['company_name'] : 'ACDC Formation';

    /* Bouton d'accès à la proposition HTML */
    $cta_html = '<p style="text-align:center;margin:28px 0;">'
      . '<a href="' . esc_url( $proposal_html_url ) . '" '
      . 'style="display:inline-block;background:#d6a353;color:#0f2c52;font-weight:800;font-size:18px;'
      . 'text-decoration:none;padding:16px 36px;border-radius:10px;letter-spacing:0.03em;">'
      . '&#128196;&nbsp; Consulter votre proposition commerciale'
      . '</a></p>'
      . '<p style="font-size:14px;color:#6b7280;text-align:center;margin:0 0 24px;">'
      . 'Ou copiez ce lien dans votre navigateur&nbsp;: <a href="' . esc_url( $proposal_html_url ) . '" style="color:#5579bf;">'
      . esc_html( $proposal_html_url ) . '</a></p>';

    $intro_html = '<p style="font-size:19px;line-height:1.7;margin:0 0 16px;">'
      . 'Veuillez trouver ci-dessous notre <strong>proposition commerciale</strong> pour la formation '
      . '<strong>' . esc_html( $formation_title ) . '</strong>.</p>'
      . $cta_html;

    if ( ! empty( $attachments ) ) {
      $intro_html .= '<p style="font-size:18px;line-height:1.7;margin:0 0 24px;">'
        . 'Le programme de formation et les conditions générales sont joints à cet e-mail.</p>';
    }

    $summary_rows = array(
      array( 'label' => 'Formation',  'value' => $formation_title ),
      array( 'label' => 'Entreprise', 'value' => $proposal->client_company ?: '—' ),
      array( 'label' => 'Durée',      'value' => (int) $proposal->formation_days . ' jour' . ( (int) $proposal->formation_days > 1 ? 's' : '' ) . ' — ' . $total_hours . 'h' ),
      array( 'label' => 'Apprenants', 'value' => $proposal->formation_learners_count ? (int) $proposal->formation_learners_count . ' participant' . ( (int) $proposal->formation_learners_count > 1 ? 's' : '' ) : '—' ),
      array( 'label' => 'Dates',      'value' => $proposal->formation_dates ?: '—' ),
      array( 'label' => 'Total HT',   'value' => $proposal->formation_total ? number_format( (float) $proposal->formation_total, 0, ',', ' ' ) . ' € net de TVA' : '—' ),
    );

    $subject = 'Votre proposition commerciale — ' . $formation_title . ' — ' . $acdc_name;

    $html = $this->acdc_build_transactional_email_html( array(
      'greeting_name' => $recipient_name ?: 'Client',
      'intro_html'    => $intro_html,
      'summary_title' => 'RÉCAPITULATIF DE VOTRE PROPOSITION',
      'summary_rows'  => $summary_rows,
      'body_html'     => '',
      'footer_notice' => 'Cet e-mail a été envoyé dans le cadre de votre demande de formation. Vos données sont traitées conformément au RGPD.',
    ) );
    $headers = $this->acdc_get_transactional_email_headers( array(
      'source_module'       => 'proposals',
      'source_action'       => 'send_proposal',
      'email_category'      => 'commercial',
      'email_audience'      => 'prospect',
      'related_entity_type' => 'proposal',
      'related_entity_id'   => (int) $proposal_id,
    ) );

    $sent = wp_mail( $recipient_email, wp_strip_all_tags( $subject ), $html, $headers, $attachments );

    if ( $sent ) {
      global $wpdb;
      $wpdb->update(
        $this->get_proposal_table(),
        array( 'status' => 'envoyee', 'last_sent_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ),
        array( 'id' => (int) $proposal_id )
      );
      if ( ! empty( $proposal->need_id ) ) {
        $nrow = $wpdb->get_row( $wpdb->prepare(
          "SELECT source_prospect_id FROM {$this->need_table} WHERE id = %d LIMIT 1",
          (int) $proposal->need_id
        ) );
        if ( $nrow && ! empty( $nrow->source_prospect_id ) ) {
          $this->maybe_advance_prospect_status( (int) $nrow->source_prospect_id, 'Proposition envoyée' );
        }
      }
    }

    return $sent
      ? true
      : new WP_Error( 'mail_failed', "L'envoi de l'email a échoué (wp_mail=false)." );
  }

  /* ---------------------------------------------------------------
   * Handler admin-post : renvoi manuel depuis la fiche Voir
   * --------------------------------------------------------------- */
  public function handle_proposal_resend_email() {
    $proposal_id = isset( $_GET['proposal_id'] ) ? absint( $_GET['proposal_id'] ) : 0;
    if ( ! $proposal_id || ! check_admin_referer( 'acdc_proposal_resend_email_' . $proposal_id ) ) {
      wp_die( 'Action invalide.' );
    }
    if ( ! current_user_can( 'edit_posts' ) ) {
      wp_die( 'Permission refusée.' );
    }
    $result   = $this->send_proposal_email( $proposal_id );
    $view_url = $this->portal_page_url( array( 'tab' => 'proposals', 'action' => 'view', 'item_id' => $proposal_id ) );
    if ( is_wp_error( $result ) ) {
      wp_safe_redirect( add_query_arg( array(
        'resend' => 'error',
        'msg'    => rawurlencode( $result->get_error_message() ),
      ), $view_url ) );
    } else {
      wp_safe_redirect( add_query_arg( 'resend', 'ok', $view_url ) );
    }
    exit;
  }

}
