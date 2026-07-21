<?php
/**
 * ACDC Marketing — noyau et référentiels
 *
 * Extraction incrémentale du module marketing.
 * Version : 3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Marketing_Core_Trait {




  private function init_marketing_module_defaults() {
    $defaults = array(
      'settings' => array(
        'sender_name' => 'ACDC-Formation',
        'sender_email' => 'contact@acdc-formation.com',
        'reply_to' => 'contact@acdc-formation.com',
        'queue_threshold' => 20,
        'limit_per_minute' => 30,
        'limit_per_hour' => 300,
        'retry_enabled' => 1,
        'retry_max' => 3,
        'manual_pause' => 1,
        'allowed_hours_start' => '08:00',
        'allowed_hours_end' => '19:00',
        'click_tracking_enabled' => 1,
        'unsubscribe_mode' => 'category',
        'smtp_mode' => 'wp_mail_smtp_status_only',
        'soft_bounce_limit' => 3,
        'hard_bounce_blacklist' => 1,
        'internal_notification_email' => 'contact@acdc-formation.com',
        'scheduler_enabled' => 1,
        'scheduler_retry_lag' => 15,
        'retention_mode' => 'archive_only',
        'retention_logs_days' => 0,
        'public_form_mode' => 'plugin_pages',
      ),
      'lists' => array(),
      'segments' => array(),
      'tags' => array(),
      'templates' => array(),
      'campaigns' => array(),
      'scenarios' => array(),
      'forms' => array(),
      'logs' => array(),
      'notifications' => array(),
      'unsubscribes' => array(),
      'queue' => array(),
      'fields' => array(),
      'webhooks' => array(),
      'contacts' => array(),
      'imports' => array(),
    );
    foreach ( $defaults as $key => $value ) {
      $option = 'acdc_of_marketing_' . $key;
      if ( false === get_option( $option, false ) ) {
        add_option( $option, $value, '', false );
      }
    }
    if ( empty( $this->get_marketing_store( 'templates', array() ) ) ) {
      $templates = array(
        array(
          'id' => uniqid( 'tpl_', true ),
          'name' => 'Modèle maître ACDC',
          'category' => 'marketing',
          'subject' => 'Votre message ACDC-Formation',
          'content' => "Bonjour {{prenom}},

{{contenu_principal}}

Cordialement,
ACDC-Formation",
          'status' => 'active',
          'created_at' => $this->now_mysql(),
          'updated_at' => $this->now_mysql(),
        ),
      );
      $this->update_marketing_store( 'templates', $templates );
    }
    $this->seed_marketing_system_repositories();
  }


  private function ensure_marketing_public_page() {
    $page_id = (int) get_option( 'acdc_of_marketing_public_page_id', 0 );
    if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
      return $page_id;
    }
    $existing = get_page_by_path( 'marketing-formulaire' );
    if ( $existing instanceof WP_Post ) {
      $page_id = (int) $existing->ID;
      wp_update_post( array( 'ID' => $page_id, 'post_content' => '[acdc_of_marketing_public]' ) );
    } else {
      $page_id = wp_insert_post( array(
        'post_title' => 'Formulaire marketing ACDC',
        'post_name' => 'marketing-formulaire',
        'post_content' => '[acdc_of_marketing_public]',
        'post_status' => 'publish',
        'post_type' => 'page',
      ) );
    }
    update_option( 'acdc_of_marketing_public_page_id', (int) $page_id, false );
    return (int) $page_id;
  }


  private function get_marketing_store( $key, $default = array() ) {
    $value = get_option( 'acdc_of_marketing_' . $key, null );
    if ( null === $value ) {
      $this->update_marketing_store( $key, $default );
      return $default;
    }
    return is_array( $value ) ? $value : $default;
  }


  private function update_marketing_store( $key, $value ) {
    update_option( 'acdc_of_marketing_' . $key, $value, false );
  }



  private function get_system_marketing_lists() {
    return array(
      array( 'id' => 'list_prospects', 'code' => 'prospects', 'name' => 'Prospects', 'description' => 'Contacts non encore transformés en client.', 'status' => 'active', 'category' => 'marketing', 'trigger_type' => '', 'is_system' => 1, 'sort_order' => 10 ),
      array( 'id' => 'list_clients', 'code' => 'clients', 'name' => 'Clients', 'description' => 'Clients actifs ou signés.', 'status' => 'active', 'category' => 'commercial', 'trigger_type' => '', 'is_system' => 1, 'sort_order' => 20 ),
      array( 'id' => 'list_apprenants', 'code' => 'apprenants', 'name' => 'Apprenants', 'description' => 'Personnes engagées dans une formation.', 'status' => 'active', 'category' => 'formation', 'trigger_type' => '', 'is_system' => 1, 'sort_order' => 30 ),
      array( 'id' => 'list_anciens_apprenants', 'code' => 'anciens-apprenants', 'name' => 'Anciens apprenants', 'description' => 'Participants ayant terminé une formation.', 'status' => 'active', 'category' => 'formation', 'trigger_type' => '', 'is_system' => 1, 'sort_order' => 40 ),
      array( 'id' => 'list_entreprises', 'code' => 'entreprises', 'name' => 'Entreprises', 'description' => 'Structures morales et comptes entreprises.', 'status' => 'active', 'category' => 'commercial', 'trigger_type' => '', 'is_system' => 1, 'sort_order' => 50 ),
      array( 'id' => 'list_financeurs', 'code' => 'financeurs', 'name' => 'Financeurs', 'description' => 'OPCO, employeurs payeurs et autres financeurs.', 'status' => 'active', 'category' => 'administratif', 'trigger_type' => '', 'is_system' => 1, 'sort_order' => 60 ),
      array( 'id' => 'list_formateurs', 'code' => 'formateurs', 'name' => 'Formateurs', 'description' => 'Intervenants internes et externes.', 'status' => 'active', 'category' => 'formation', 'trigger_type' => '', 'is_system' => 1, 'sort_order' => 70 ),
      array( 'id' => 'list_partenaires', 'code' => 'partenaires', 'name' => 'Partenaires', 'description' => 'Prescripteurs et partenaires.', 'status' => 'active', 'category' => 'marketing', 'trigger_type' => '', 'is_system' => 1, 'sort_order' => 80 ),
    );
  }


  private function get_system_marketing_tags() {
    return array(
      array( 'id' => 'tag_source_linkedin', 'code' => 'source-linkedin', 'family' => 'source', 'name' => 'Source : LinkedIn', 'description' => 'Contact venu de LinkedIn.', 'status' => 'active', 'category' => 'marketing', 'color' => '#C5A253', 'icon' => 'linkedin', 'is_system' => 1, 'sort_order' => 10 ),
      array( 'id' => 'tag_source_facebook', 'code' => 'source-facebook', 'family' => 'source', 'name' => 'Source : Facebook', 'description' => 'Contact venu de Facebook.', 'status' => 'active', 'category' => 'marketing', 'color' => '#C5A253', 'icon' => 'facebook', 'is_system' => 1, 'sort_order' => 20 ),
      array( 'id' => 'tag_source_site_web', 'code' => 'source-site-web', 'family' => 'source', 'name' => 'Source : site web', 'description' => 'Contact venu du site web.', 'status' => 'active', 'category' => 'marketing', 'color' => '#C5A253', 'icon' => 'globe', 'is_system' => 1, 'sort_order' => 30 ),
      array( 'id' => 'tag_source_email_entrant', 'code' => 'source-email-entrant', 'family' => 'source', 'name' => 'Source : e-mail entrant', 'description' => 'Contact venu d’un e-mail entrant.', 'status' => 'active', 'category' => 'marketing', 'color' => '#C5A253', 'icon' => 'mail', 'is_system' => 1, 'sort_order' => 40 ),
      array( 'id' => 'tag_source_appel_entrant', 'code' => 'source-appel-entrant', 'family' => 'source', 'name' => 'Source : appel entrant', 'description' => 'Contact venu d’un appel entrant.', 'status' => 'active', 'category' => 'marketing', 'color' => '#C5A253', 'icon' => 'phone', 'is_system' => 1, 'sort_order' => 50 ),
      array( 'id' => 'tag_source_recommandation', 'code' => 'source-recommandation', 'family' => 'source', 'name' => 'Source : recommandation', 'description' => 'Contact venu d’une recommandation.', 'status' => 'active', 'category' => 'marketing', 'color' => '#C5A253', 'icon' => 'users', 'is_system' => 1, 'sort_order' => 60 ),
      array( 'id' => 'tag_source_evenement', 'code' => 'source-evenement', 'family' => 'source', 'name' => 'Source : événement', 'description' => 'Contact venu d’un événement.', 'status' => 'active', 'category' => 'marketing', 'color' => '#C5A253', 'icon' => 'calendar', 'is_system' => 1, 'sort_order' => 70 ),
      array( 'id' => 'tag_source_prospection_terrain', 'code' => 'source-prospection-terrain', 'family' => 'source', 'name' => 'Source : prospection terrain', 'description' => 'Contact obtenu lors d’une visite prospect, porte-à-porte ou démarche terrain.', 'status' => 'active', 'category' => 'marketing', 'color' => '#C5A253', 'icon' => 'location', 'is_system' => 1, 'sort_order' => 80 ),
      array( 'id' => 'tag_source_import_csv', 'code' => 'source-import-csv', 'family' => 'source', 'name' => 'Source : import CSV', 'description' => 'Contact importé depuis un fichier CSV.', 'status' => 'active', 'category' => 'marketing', 'color' => '#C5A253', 'icon' => 'upload', 'is_system' => 1, 'sort_order' => 90 ),
      array( 'id' => 'tag_interet_ia', 'code' => 'interet-ia', 'family' => 'interet', 'name' => 'Intérêt : IA', 'description' => 'Intérêt pour l’intelligence artificielle.', 'status' => 'active', 'category' => 'marketing', 'color' => '#C5A253', 'icon' => 'sparkles', 'is_system' => 1, 'sort_order' => 100 ),
      array( 'id' => 'tag_interet_no_code', 'code' => 'interet-no-code', 'family' => 'interet', 'name' => 'Intérêt : no-code', 'description' => 'Intérêt pour le no-code.', 'status' => 'active', 'category' => 'marketing', 'color' => '#C5A253', 'icon' => 'workflow', 'is_system' => 1, 'sort_order' => 110 ),
      array( 'id' => 'tag_interet_wordpress', 'code' => 'interet-wordpress', 'family' => 'interet', 'name' => 'Intérêt : WordPress', 'description' => 'Intérêt pour WordPress.', 'status' => 'active', 'category' => 'marketing', 'color' => '#C5A253', 'icon' => 'layout', 'is_system' => 1, 'sort_order' => 120 ),
      array( 'id' => 'tag_interet_marketing_digital', 'code' => 'interet-marketing-digital', 'family' => 'interet', 'name' => 'Intérêt : marketing numérique', 'description' => 'Intérêt pour le marketing numérique.', 'status' => 'active', 'category' => 'marketing', 'color' => '#C5A253', 'icon' => 'megaphone', 'is_system' => 1, 'sort_order' => 130 ),
      array( 'id' => 'tag_interet_management', 'code' => 'interet-management', 'family' => 'interet', 'name' => 'Intérêt : management', 'description' => 'Intérêt pour le management.', 'status' => 'active', 'category' => 'marketing', 'color' => '#C5A253', 'icon' => 'briefcase', 'is_system' => 1, 'sort_order' => 140 ),
      array( 'id' => 'tag_interet_soft_skills', 'code' => 'interet-soft-skills', 'family' => 'interet', 'name' => 'Intérêt : soft skills', 'description' => 'Intérêt pour les compétences comportementales.', 'status' => 'active', 'category' => 'marketing', 'color' => '#C5A253', 'icon' => 'heart', 'is_system' => 1, 'sort_order' => 150 ),
      array( 'id' => 'tag_interet_loi_alur', 'code' => 'interet-loi-alur', 'family' => 'interet', 'name' => 'Intérêt : loi ALUR', 'description' => 'Intérêt pour la loi ALUR.', 'status' => 'active', 'category' => 'marketing', 'color' => '#C5A253', 'icon' => 'building', 'is_system' => 1, 'sort_order' => 160 ),
      array( 'id' => 'tag_interet_hygiene_alimentaire', 'code' => 'interet-hygiene-alimentaire', 'family' => 'interet', 'name' => 'Intérêt : hygiène alimentaire', 'description' => 'Intérêt pour l’hygiène alimentaire.', 'status' => 'active', 'category' => 'marketing', 'color' => '#C5A253', 'icon' => 'utensils', 'is_system' => 1, 'sort_order' => 170 ),
      array( 'id' => 'tag_statut_a_contacter', 'code' => 'statut-a-contacter', 'family' => 'statut', 'name' => 'Statut : à contacter', 'description' => 'Nouveau contact à traiter.', 'status' => 'active', 'category' => 'commercial', 'color' => '#C5A253', 'icon' => 'user-plus', 'is_system' => 1, 'sort_order' => 180 ),
      array( 'id' => 'tag_statut_devis_envoye', 'code' => 'statut-devis-envoye', 'family' => 'statut', 'name' => 'Statut : devis envoyé', 'description' => 'Devis transmis.', 'status' => 'active', 'category' => 'commercial', 'color' => '#C5A253', 'icon' => 'file-text', 'is_system' => 1, 'sort_order' => 190 ),
      array( 'id' => 'tag_statut_relance_a_faire', 'code' => 'statut-relance-a-faire', 'family' => 'statut', 'name' => 'Statut : relance à faire', 'description' => 'Relance à effectuer.', 'status' => 'active', 'category' => 'commercial', 'color' => '#C5A253', 'icon' => 'refresh-cw', 'is_system' => 1, 'sort_order' => 200 ),
      array( 'id' => 'tag_statut_chaud', 'code' => 'statut-chaud', 'family' => 'statut', 'name' => 'Statut : chaud', 'description' => 'Prospect à fort potentiel.', 'status' => 'active', 'category' => 'commercial', 'color' => '#C5A253', 'icon' => 'flame', 'is_system' => 1, 'sort_order' => 210 ),
      array( 'id' => 'tag_statut_tiede', 'code' => 'statut-tiede', 'family' => 'statut', 'name' => 'Statut : tiède', 'description' => 'Prospect intermédiaire.', 'status' => 'active', 'category' => 'commercial', 'color' => '#C5A253', 'icon' => 'thermometer', 'is_system' => 1, 'sort_order' => 220 ),
      array( 'id' => 'tag_statut_froid', 'code' => 'statut-froid', 'family' => 'statut', 'name' => 'Statut : froid', 'description' => 'Prospect peu engagé.', 'status' => 'active', 'category' => 'commercial', 'color' => '#C5A253', 'icon' => 'snowflake', 'is_system' => 1, 'sort_order' => 230 ),
      array( 'id' => 'tag_parcours_formation_en_cours', 'code' => 'parcours-formation-en-cours', 'family' => 'parcours', 'name' => 'Parcours : formation en cours', 'description' => 'Formation démarrée non terminée.', 'status' => 'active', 'category' => 'formation', 'color' => '#C5A253', 'icon' => 'play-circle', 'is_system' => 1, 'sort_order' => 240 ),
      array( 'id' => 'tag_parcours_formation_terminee', 'code' => 'parcours-formation-terminee', 'family' => 'parcours', 'name' => 'Parcours : formation terminée', 'description' => 'Formation terminée.', 'status' => 'active', 'category' => 'formation', 'color' => '#C5A253', 'icon' => 'check-circle', 'is_system' => 1, 'sort_order' => 250 ),
      array( 'id' => 'tag_comportement_inactif_90j', 'code' => 'comportement-inactif-90j', 'family' => 'comportement', 'name' => 'Comportement : inactif 90 jours', 'description' => 'Aucun engagement depuis 90 jours.', 'status' => 'active', 'category' => 'marketing', 'color' => '#C5A253', 'icon' => 'clock', 'is_system' => 1, 'sort_order' => 260 ),
      array( 'id' => 'tag_profil_dirigeant', 'code' => 'profil-dirigeant', 'family' => 'profil', 'name' => 'Profil : dirigeant', 'description' => 'Contact dirigeant.', 'status' => 'active', 'category' => 'marketing', 'color' => '#C5A253', 'icon' => 'user-check', 'is_system' => 1, 'sort_order' => 270 ),
      array( 'id' => 'tag_profil_rh', 'code' => 'profil-rh', 'family' => 'profil', 'name' => 'Profil : RH', 'description' => 'Contact RH.', 'status' => 'active', 'category' => 'marketing', 'color' => '#C5A253', 'icon' => 'users', 'is_system' => 1, 'sort_order' => 280 ),
      array( 'id' => 'tag_profil_independant', 'code' => 'profil-independant', 'family' => 'profil', 'name' => 'Profil : indépendant', 'description' => 'Contact indépendant.', 'status' => 'active', 'category' => 'marketing', 'color' => '#C5A253', 'icon' => 'user', 'is_system' => 1, 'sort_order' => 290 ),
      array( 'id' => 'tag_donnee_a_nettoyer', 'code' => 'donnee-a-nettoyer', 'family' => 'donnee', 'name' => 'Donnée : à nettoyer', 'description' => 'Fiche à requalifier ou nettoyer.', 'status' => 'active', 'category' => 'administratif', 'color' => '#C5A253', 'icon' => 'alert-circle', 'is_system' => 1, 'sort_order' => 300 ),
    );
  }


  private function get_system_marketing_segments() {
    return array(
      array( 'id' => 'segment_prospects_a_contacter', 'code' => 'prospects-a-contacter', 'name' => 'Prospects à contacter', 'description' => 'Prospects avec statut à contacter.', 'status' => 'active', 'segment_mode' => 'dynamic', 'contact_type' => 'prospect', 'enabled_only' => 0, 'status_filter' => '', 'engagement_filter' => '', 'rules' => 'Prospects avec statut à contacter.', 'rules_json' => array( 'logic' => 'AND', 'conditions' => array( array( 'field' => 'type', 'operator' => 'equals', 'value' => 'prospect' ), array( 'field' => 'tags', 'operator' => 'contains', 'value' => 'tag_statut_a_contacter' ) ) ), 'population_count' => 0, 'is_system' => 1, 'sort_order' => 10 ),
      array( 'id' => 'segment_prospects_chauds_relancer', 'code' => 'prospects-chauds-a-relancer', 'name' => 'Prospects chauds à relancer', 'description' => 'Prospects chauds avec relance à faire.', 'status' => 'active', 'segment_mode' => 'dynamic', 'contact_type' => 'prospect', 'enabled_only' => 1, 'status_filter' => '', 'engagement_filter' => '', 'rules' => 'Prospects activés, chauds, avec relance à faire.', 'rules_json' => array( 'logic' => 'AND', 'conditions' => array( array( 'field' => 'type', 'operator' => 'equals', 'value' => 'prospect' ), array( 'field' => 'enabled', 'operator' => 'equals', 'value' => 1 ), array( 'field' => 'tags', 'operator' => 'contains', 'value' => 'tag_statut_chaud' ), array( 'field' => 'tags', 'operator' => 'contains', 'value' => 'tag_statut_relance_a_faire' ) ) ), 'population_count' => 0, 'is_system' => 1, 'sort_order' => 20 ),
      array( 'id' => 'segment_prospects_interesses_ia', 'code' => 'prospects-interesses-ia', 'name' => 'Prospects intéressés par l’IA', 'description' => 'Prospects portant l’intérêt IA.', 'status' => 'active', 'segment_mode' => 'dynamic', 'contact_type' => 'prospect', 'enabled_only' => 0, 'status_filter' => '', 'engagement_filter' => '', 'rules' => 'Prospects avec intérêt IA.', 'rules_json' => array( 'logic' => 'AND', 'conditions' => array( array( 'field' => 'type', 'operator' => 'equals', 'value' => 'prospect' ), array( 'field' => 'tags', 'operator' => 'contains', 'value' => 'tag_interet_ia' ) ) ), 'population_count' => 0, 'is_system' => 1, 'sort_order' => 30 ),
      array( 'id' => 'segment_leads_site_web', 'code' => 'leads-site-web', 'name' => 'Leads site web', 'description' => 'Contacts issus du site web.', 'status' => 'active', 'segment_mode' => 'dynamic', 'contact_type' => '', 'enabled_only' => 0, 'status_filter' => '', 'engagement_filter' => '', 'rules' => 'Contacts avec source site web.', 'rules_json' => array( 'logic' => 'AND', 'conditions' => array( array( 'field' => 'tags', 'operator' => 'contains', 'value' => 'tag_source_site_web' ) ) ), 'population_count' => 0, 'is_system' => 1, 'sort_order' => 40 ),
      array( 'id' => 'segment_leads_prospection_terrain', 'code' => 'leads-prospection-terrain', 'name' => 'Leads prospection terrain', 'description' => 'Contacts issus de la prospection terrain.', 'status' => 'active', 'segment_mode' => 'dynamic', 'contact_type' => '', 'enabled_only' => 0, 'status_filter' => '', 'engagement_filter' => '', 'rules' => 'Contacts avec source prospection terrain.', 'rules_json' => array( 'logic' => 'AND', 'conditions' => array( array( 'field' => 'tags', 'operator' => 'contains', 'value' => 'tag_source_prospection_terrain' ) ) ), 'population_count' => 0, 'is_system' => 1, 'sort_order' => 50 ),
      array( 'id' => 'segment_contacts_autorises_marketing', 'code' => 'contacts-autorises-marketing', 'name' => 'Contacts autorisés marketing', 'description' => 'Contacts autorisés marketing.', 'status' => 'active', 'segment_mode' => 'dynamic', 'contact_type' => '', 'enabled_only' => 1, 'status_filter' => '', 'engagement_filter' => '', 'rules' => 'Contacts activés avec consentement marketing autorisé.', 'rules_json' => array( 'logic' => 'AND', 'conditions' => array( array( 'field' => 'enabled', 'operator' => 'equals', 'value' => 1 ), array( 'field' => 'statuses.marketing', 'operator' => 'equals', 'value' => 'allowed' ) ) ), 'population_count' => 0, 'is_system' => 1, 'sort_order' => 60 ),
      array( 'id' => 'segment_inactifs_90j', 'code' => 'inactifs-90-jours', 'name' => 'Inactifs 90 jours', 'description' => 'Contacts avec étiquette inactif 90 jours.', 'status' => 'active', 'segment_mode' => 'dynamic', 'contact_type' => '', 'enabled_only' => 0, 'status_filter' => '', 'engagement_filter' => '', 'rules' => 'Contacts inactifs 90 jours.', 'rules_json' => array( 'logic' => 'AND', 'conditions' => array( array( 'field' => 'tags', 'operator' => 'contains', 'value' => 'tag_comportement_inactif_90j' ) ) ), 'population_count' => 0, 'is_system' => 1, 'sort_order' => 70 ),
      array( 'id' => 'segment_apprenants_en_cours', 'code' => 'apprenants-en-cours', 'name' => 'Apprenants en cours', 'description' => 'Apprenants avec formation en cours.', 'status' => 'active', 'segment_mode' => 'dynamic', 'contact_type' => 'apprenant', 'enabled_only' => 0, 'status_filter' => '', 'engagement_filter' => '', 'rules' => 'Apprenants avec parcours formation en cours.', 'rules_json' => array( 'logic' => 'AND', 'conditions' => array( array( 'field' => 'type', 'operator' => 'equals', 'value' => 'apprenant' ), array( 'field' => 'tags', 'operator' => 'contains', 'value' => 'tag_parcours_formation_en_cours' ) ) ), 'population_count' => 0, 'is_system' => 1, 'sort_order' => 80 ),
      array( 'id' => 'segment_fiches_a_completer', 'code' => 'fiches-a-completer', 'name' => 'Fiches à compléter', 'description' => 'Fiches portant le marqueur à nettoyer.', 'status' => 'active', 'segment_mode' => 'dynamic', 'contact_type' => '', 'enabled_only' => 0, 'status_filter' => '', 'engagement_filter' => '', 'rules' => 'Contacts avec donnée à nettoyer.', 'rules_json' => array( 'logic' => 'AND', 'conditions' => array( array( 'field' => 'tags', 'operator' => 'contains', 'value' => 'tag_donnee_a_nettoyer' ) ) ), 'population_count' => 0, 'is_system' => 1, 'sort_order' => 90 ),
    );
  }


  private function normalize_marketing_rules_json( $rules_json ) {
    if ( is_string( $rules_json ) && '' !== trim( $rules_json ) ) {
      $decoded = json_decode( wp_unslash( $rules_json ), true );
      if ( is_array( $decoded ) ) {
        $rules_json = $decoded;
      }
    }
    if ( ! is_array( $rules_json ) ) {
      return array( 'logic' => 'AND', 'conditions' => array() );
    }
    $logic = isset( $rules_json['logic'] ) && 'OR' === strtoupper( (string) $rules_json['logic'] ) ? 'OR' : 'AND';
    $conditions = array();
    if ( ! empty( $rules_json['conditions'] ) && is_array( $rules_json['conditions'] ) ) {
      foreach ( $rules_json['conditions'] as $condition ) {
        if ( ! is_array( $condition ) ) {
          continue;
        }
        $conditions[] = array(
          'field' => isset( $condition['field'] ) ? sanitize_text_field( $condition['field'] ) : '',
          'operator' => isset( $condition['operator'] ) ? sanitize_key( $condition['operator'] ) : 'equals',
          'value' => isset( $condition['value'] ) ? $condition['value'] : '',
        );
      }
    }
    return array( 'logic' => $logic, 'conditions' => $conditions );
  }


  private function normalize_marketing_list_record( $item ) {
    $now = $this->now_mysql();
    return array_merge(
      array(
        'id' => uniqid( 'lists_', true ), 'code' => '', 'name' => '', 'description' => '', 'status' => 'draft', 'category' => 'marketing', 'trigger_type' => '', 'subject' => '', 'rules' => '', 'is_system' => 0, 'sort_order' => 100, 'created_at' => $now, 'updated_at' => $now,
      ),
      is_array( $item ) ? $item : array()
    );
  }


  private function normalize_marketing_tag_record( $item ) {
    $now = $this->now_mysql();
    return array_merge(
      array(
        'id' => uniqid( 'tags_', true ), 'code' => '', 'family' => '', 'name' => '', 'description' => '', 'status' => 'draft', 'category' => 'marketing', 'color' => '', 'icon' => '', 'subject' => '', 'rules' => '', 'is_system' => 0, 'sort_order' => 100, 'created_at' => $now, 'updated_at' => $now,
      ),
      is_array( $item ) ? $item : array()
    );
  }


  private function normalize_marketing_segment_record( $item ) {
    $now = $this->now_mysql();
    $item = array_merge(
      array(
        'id' => uniqid( 'segments_', true ), 'code' => '', 'name' => '', 'description' => '', 'status' => 'draft', 'category' => 'marketing', 'rules' => '', 'rules_json' => array( 'logic' => 'AND', 'conditions' => array() ), 'segment_mode' => 'dynamic', 'contact_type' => '', 'enabled_only' => 0, 'status_filter' => '', 'engagement_filter' => '', 'population_count' => 0, 'is_system' => 0, 'sort_order' => 100, 'created_at' => $now, 'updated_at' => $now,
      ),
      is_array( $item ) ? $item : array()
    );
    $item['rules_json'] = $this->normalize_marketing_rules_json( $item['rules_json'] );
    $item['population_count'] = isset( $item['population_count'] ) ? (int) $item['population_count'] : 0;
    return $item;
  }


  private function normalize_marketing_contact_record( $contact ) {
    $contact = array_merge(
      array(
        'source_key' => '', 'source_type' => '', 'source_id' => 0, 'name' => '', 'email' => '', 'phone' => '', 'company' => '', 'type' => 'prospect', 'enabled' => 0,
        'primary_list_id' => '', 'lists' => array(), 'tags' => array(), 'segments_cache' => array(), 'statuses' => array(), 'score' => 0, 'score_value' => 0,
        'last_engagement' => '', 'last_email_sent_at' => '', 'last_email_open_at' => '', 'last_email_click_at' => '', 'last_interaction_at' => '', 'notes' => '',
        'created_at' => $this->now_mysql(), 'updated_at' => $this->now_mysql(),
      ),
      is_array( $contact ) ? $contact : array()
    );
    $contact['lists'] = array_values( array_unique( array_filter( array_map( 'strval', (array) $contact['lists'] ) ) ) );
    $contact['tags'] = array_values( array_unique( array_filter( array_map( 'strval', (array) $contact['tags'] ) ) ) );
    $contact['segments_cache'] = array_values( array_unique( array_filter( array_map( 'strval', (array) $contact['segments_cache'] ) ) ) );
    $contact['statuses'] = $this->normalize_marketing_contact_statuses( isset( $contact['statuses'] ) && is_array( $contact['statuses'] ) ? $contact['statuses'] : array(), isset( $contact['type'] ) ? $contact['type'] : 'prospect' );
    $contact['score'] = max( 0, min( 999, (int) $contact['score'] ) );
    $contact['score_value'] = max( 0, min( 999, (int) $contact['score_value'] ) );
    $contact['enabled'] = ! empty( $contact['enabled'] ) ? 1 : 0;
    return $this->ensure_marketing_contact_primary_list( $contact );
  }


  private function ensure_marketing_contact_primary_list( $contact ) {
    $type_map = array(
      'prospect' => 'list_prospects',
      'client' => 'list_clients',
      'apprenant' => 'list_apprenants',
      'ancien_apprenant' => 'list_anciens_apprenants',
      'entreprise' => 'list_entreprises',
      'financeur' => 'list_financeurs',
      'formateur' => 'list_formateurs',
      'independant' => 'list_formateurs',
    );
    $primary = isset( $contact['primary_list_id'] ) ? (string) $contact['primary_list_id'] : '';
    if ( '' === $primary && ! empty( $contact['lists'][0] ) ) {
      $primary = (string) $contact['lists'][0];
    }
    if ( '' === $primary && isset( $type_map[ $contact['type'] ] ) ) {
      $primary = $type_map[ $contact['type'] ];
    }
    $contact['primary_list_id'] = $primary;
    if ( $primary && ! in_array( $primary, $contact['lists'], true ) ) {
      array_unshift( $contact['lists'], $primary );
    }
    $contact['lists'] = array_values( array_unique( array_filter( array_map( 'strval', (array) $contact['lists'] ) ) ) );
    return $contact;
  }


  private function add_marketing_tag_to_contact( $source_key, $tag_id ) {
    $contacts = $this->get_marketing_contacts_index();
    if ( ! isset( $contacts[ $source_key ] ) || ! $tag_id ) {
      return;
    }
    $contacts[ $source_key ]['tags'][] = (string) $tag_id;
    $contacts[ $source_key ] = $this->normalize_marketing_contact_record( $contacts[ $source_key ] );
    $contacts[ $source_key ]['updated_at'] = $this->now_mysql();
    $this->update_marketing_store( 'contacts', $contacts );
  }


  private function remove_marketing_tag_from_contact( $source_key, $tag_id ) {
    $contacts = $this->get_marketing_contacts_index();
    if ( ! isset( $contacts[ $source_key ] ) || ! $tag_id ) {
      return;
    }
    $contacts[ $source_key ]['tags'] = array_values( array_filter( (array) $contacts[ $source_key ]['tags'], function( $existing ) use ( $tag_id ) {
      return (string) $existing !== (string) $tag_id;
    } ) );
    $contacts[ $source_key ] = $this->normalize_marketing_contact_record( $contacts[ $source_key ] );
    $contacts[ $source_key ]['updated_at'] = $this->now_mysql();
    $this->update_marketing_store( 'contacts', $contacts );
  }


  private function set_marketing_contact_primary_list( $source_key, $list_id ) {
    $contacts = $this->get_marketing_contacts_index();
    if ( ! isset( $contacts[ $source_key ] ) ) {
      return;
    }
    $contacts[ $source_key ]['primary_list_id'] = (string) $list_id;
    $contacts[ $source_key ] = $this->normalize_marketing_contact_record( $contacts[ $source_key ] );
    $contacts[ $source_key ]['updated_at'] = $this->now_mysql();
    $this->update_marketing_store( 'contacts', $contacts );
  }


  private function get_marketing_contact_field_value( $contact, $field ) {
    if ( false !== strpos( $field, '.' ) ) {
      $parts = explode( '.', $field );
      $value = $contact;
      foreach ( $parts as $part ) {
        if ( is_array( $value ) && array_key_exists( $part, $value ) ) {
          $value = $value[ $part ];
        } else {
          return null;
        }
      }
      return $value;
    }
    return array_key_exists( $field, $contact ) ? $contact[ $field ] : null;
  }


  private function compare_marketing_segment_condition( $actual, $operator, $expected ) {
    switch ( $operator ) {
      case 'equals': return (string) $actual === (string) $expected || (is_numeric($actual) && is_numeric($expected) && (float)$actual === (float)$expected);
      case 'not_equals': return ! ( (string) $actual === (string) $expected || (is_numeric($actual) && is_numeric($expected) && (float)$actual === (float)$expected) );
      case 'contains':
        if ( is_array( $actual ) ) { return in_array( (string) $expected, array_map( 'strval', $actual ), true ); }
        return false !== strpos( strtolower( (string) $actual ), strtolower( (string) $expected ) );
      case 'not_contains':
        if ( is_array( $actual ) ) { return ! in_array( (string) $expected, array_map( 'strval', $actual ), true ); }
        return false === strpos( strtolower( (string) $actual ), strtolower( (string) $expected ) );
      case 'is_empty': return empty( $actual );
      case 'is_not_empty': return ! empty( $actual );
      case 'greater_than': return is_numeric( $actual ) && is_numeric( $expected ) && (float) $actual > (float) $expected;
      case 'less_than': return is_numeric( $actual ) && is_numeric( $expected ) && (float) $actual < (float) $expected;
      case 'days_since':
        if ( empty( $actual ) ) { return false; }
        $timestamp = strtotime( (string) $actual );
        if ( ! $timestamp ) { return false; }
        $days = floor( ( current_time( 'timestamp' ) - $timestamp ) / DAY_IN_SECONDS );
        return $days >= (int) $expected;
    }
    return false;
  }


  private function contact_matches_segment_rules( $contact, $rules_json ) {
    $rules_json = $this->normalize_marketing_rules_json( $rules_json );
    $logic = isset( $rules_json['logic'] ) ? $rules_json['logic'] : 'AND';
    $results = array();
    foreach ( (array) $rules_json['conditions'] as $condition ) {
      $actual = $this->get_marketing_contact_field_value( $contact, isset( $condition['field'] ) ? $condition['field'] : '' );
      $results[] = $this->compare_marketing_segment_condition( $actual, isset( $condition['operator'] ) ? $condition['operator'] : 'equals', isset( $condition['value'] ) ? $condition['value'] : '' );
    }
    if ( empty( $results ) ) {
      return true;
    }
    return 'OR' === $logic ? in_array( true, $results, true ) : ! in_array( false, $results, true );
  }


  private function compute_marketing_segment_population( $segment, $contacts = null ) {
    $contacts = is_array( $contacts ) ? $contacts : $this->get_marketing_contacts_index();
    $matched = array();
    foreach ( $contacts as $contact ) {
      if ( ! empty( $segment['contact_type'] ) && (string) $contact['type'] !== (string) $segment['contact_type'] ) {
        continue;
      }
      if ( ! empty( $segment['enabled_only'] ) && empty( $contact['enabled'] ) ) {
        continue;
      }
      if ( $this->contact_matches_segment_rules( $contact, isset( $segment['rules_json'] ) ? $segment['rules_json'] : array() ) ) {
        $matched[] = isset( $contact['source_key'] ) ? $contact['source_key'] : '';
      }
    }
    return array_values( array_filter( $matched ) );
  }


  private function refresh_marketing_segments() {
    $segments = $this->get_marketing_store( 'segments', array() );
    $contacts = $this->get_marketing_contacts_index();
    $contact_matches = array();
    foreach ( $segments as $index => $segment ) {
      $segment = $this->normalize_marketing_segment_record( $segment );
      if ( 'dynamic' === $segment['segment_mode'] ) {
        $matched = $this->compute_marketing_segment_population( $segment, $contacts );
        $segment['population_count'] = count( $matched );
        foreach ( $matched as $source_key ) {
          if ( ! isset( $contact_matches[ $source_key ] ) ) {
            $contact_matches[ $source_key ] = array();
          }
          $contact_matches[ $source_key ][] = $segment['id'];
        }
      }
      $segment['updated_at'] = $this->now_mysql();
      $segments[ $index ] = $segment;
    }
    foreach ( $contacts as $source_key => $contact ) {
      $contact['segments_cache'] = isset( $contact_matches[ $source_key ] ) ? array_values( array_unique( $contact_matches[ $source_key ] ) ) : array();
      $contacts[ $source_key ] = $this->normalize_marketing_contact_record( $contact );
    }
    $this->update_marketing_store( 'segments', array_values( $segments ) );
    $this->update_marketing_store( 'contacts', $contacts );
  }


  private function seed_marketing_system_repositories() {
    $definitions = array(
      'lists' => array( 'records' => $this->get_system_marketing_lists(), 'normalizer' => 'normalize_marketing_list_record' ),
      'tags' => array( 'records' => $this->get_system_marketing_tags(), 'normalizer' => 'normalize_marketing_tag_record' ),
      'segments' => array( 'records' => $this->get_system_marketing_segments(), 'normalizer' => 'normalize_marketing_segment_record' ),
    );
    foreach ( $definitions as $entity => $definition ) {
      $items = $this->get_marketing_store( $entity, array() );
      $map = array();
      foreach ( $items as $idx => $item ) {
        if ( ! empty( $item['id'] ) ) {
          $map[ $item['id'] ] = $idx;
        }
      }
      foreach ( $definition['records'] as $record ) {
        $record = $this->{$definition['normalizer']}( $record );
        if ( isset( $map[ $record['id'] ] ) ) {
          $existing = $items[ $map[ $record['id'] ] ];
          $record['created_at'] = ! empty( $existing['created_at'] ) ? $existing['created_at'] : $record['created_at'];
          $items[ $map[ $record['id'] ] ] = array_merge( $existing, $record );
        } else {
          $items[] = $record;
        }
      }
      usort( $items, function( $a, $b ) {
        return (int) ( isset( $a['sort_order'] ) ? $a['sort_order'] : 100 ) <=> (int) ( isset( $b['sort_order'] ) ? $b['sort_order'] : 100 );
      } );
      $this->update_marketing_store( $entity, array_values( $items ) );
    }
    $this->refresh_marketing_segments();
  }


  private function marketing_categories() {
    return array(
      'marketing' => 'Marketing / prospection',
      'commercial' => 'Informations commerciales liées à un devis ou une offre',
      'followup' => 'Suivi de formation / accompagnement',
      'administrative' => 'Informations administratives',
      'financeur' => 'Relances financeurs',
      'internal' => 'Notifications internes',
    );
  }


  private function marketing_category_statuses() {
    return array(
      'allowed' => 'Autorisé',
      'refused' => 'Refusé',
      'pending' => 'En attente',
      'not_requested' => 'Non demandé',
      'not_applicable' => 'Non applicable',
    );
  }


  private function marketing_default_contact_statuses( $type = 'prospect' ) {
    $statuses = array(
      'marketing' => 'not_requested',
      'commercial' => 'allowed',
      'followup' => 'not_applicable',
      'administrative' => 'not_applicable',
      'financeur' => 'not_applicable',
      'internal' => 'not_applicable',
    );
    if ( in_array( $type, array( 'apprenant', 'ancien_apprenant', 'formateur' ), true ) ) {
      $statuses['followup'] = 'allowed';
      $statuses['administrative'] = 'allowed';
    }
    if ( 'financeur' === $type ) {
      $statuses['financeur'] = 'allowed';
    }
    if ( in_array( $type, array( 'entreprise', 'client', 'prospect', 'independant' ), true ) ) {
      $statuses['commercial'] = 'allowed';
    }
    return $statuses;
  }


  private function marketing_type_labels() {
    return array(
      'prospect' => 'Prospect',
      'client' => 'Client',
      'apprenant' => 'Apprenant',
      'ancien_apprenant' => 'Ancien apprenant',
      'entreprise' => 'Entreprise',
      'financeur' => 'Financeur',
      'formateur' => 'Formateur',
      'independant' => 'Indépendant',
      'contact' => 'Contact',
    );
  }


  private function get_marketing_base_records() {
    global $wpdb;
    $rows = array();

    $prospects = $wpdb->get_results( "SELECT id, first_name, last_name, email, phone, company_name FROM {$this->prospect_table} ORDER BY id DESC LIMIT 500", ARRAY_A );
    foreach ( (array) $prospects as $row ) {
      $rows[ 'prospect:' . (int) $row['id'] ] = array(
        'source_key' => 'prospect:' . (int) $row['id'], 'source_id' => (int) $row['id'], 'source_type' => 'prospect', 'type' => 'prospect',
        'name' => trim( $row['first_name'] . ' ' . $row['last_name'] ), 'email' => (string) $row['email'], 'phone' => (string) $row['phone'], 'company' => (string) $row['company_name'],
      );
    }
    $learners = $wpdb->get_results( "SELECT id, first_name, last_name, email, phone, job_title FROM {$this->learner_table} ORDER BY id DESC LIMIT 500", ARRAY_A );
    foreach ( (array) $learners as $row ) {
      $rows[ 'learner:' . (int) $row['id'] ] = array(
        'source_key' => 'learner:' . (int) $row['id'], 'source_id' => (int) $row['id'], 'source_type' => 'learner', 'type' => 'apprenant',
        'name' => trim( $row['first_name'] . ' ' . $row['last_name'] ), 'email' => (string) $row['email'], 'phone' => (string) $row['phone'], 'company' => (string) $row['job_title'],
      );
    }
    $companies = $wpdb->get_results( "SELECT id, name, email, phone FROM {$this->company_table} ORDER BY id DESC LIMIT 500", ARRAY_A );
    foreach ( (array) $companies as $row ) {
      $rows[ 'company:' . (int) $row['id'] ] = array(
        'source_key' => 'company:' . (int) $row['id'], 'source_id' => (int) $row['id'], 'source_type' => 'company', 'type' => 'entreprise',
        'name' => (string) $row['name'], 'email' => (string) $row['email'], 'phone' => (string) $row['phone'], 'company' => (string) $row['name'],
      );
    }
    $funders = $wpdb->get_results( "SELECT id, name, email, phone FROM {$this->funder_table} ORDER BY id DESC LIMIT 500", ARRAY_A );
    foreach ( (array) $funders as $row ) {
      $rows[ 'funder:' . (int) $row['id'] ] = array(
        'source_key' => 'funder:' . (int) $row['id'], 'source_id' => (int) $row['id'], 'source_type' => 'funder', 'type' => 'financeur',
        'name' => (string) $row['name'], 'email' => (string) $row['email'], 'phone' => (string) $row['phone'], 'company' => (string) $row['name'],
      );
    }
    $trainers = $wpdb->get_results( "SELECT id, first_name, last_name, email, phone, trainer_type, is_self_trainer, siret FROM {$this->trainer_table} ORDER BY id DESC LIMIT 500", ARRAY_A );
    foreach ( (array) $trainers as $row ) {
      $type = ! empty( $row['is_self_trainer'] ) || 'Indépendant' === (string) $row['trainer_type'] ? 'independant' : 'formateur';
      $rows[ 'trainer:' . (int) $row['id'] ] = array(
        'source_key' => 'trainer:' . (int) $row['id'], 'source_id' => (int) $row['id'], 'source_type' => 'trainer', 'type' => $type,
        'name' => trim( $row['first_name'] . ' ' . $row['last_name'] ), 'email' => (string) $row['email'], 'phone' => (string) $row['phone'], 'company' => (string) $row['siret'],
      );
    }
    $contacts = $wpdb->get_results( "SELECT id, first_name, last_name, email, phone, company_id FROM {$this->contact_table} ORDER BY id DESC LIMIT 500", ARRAY_A );
    foreach ( (array) $contacts as $row ) {
      $rows[ 'contact:' . (int) $row['id'] ] = array(
        'source_key' => 'contact:' . (int) $row['id'], 'source_id' => (int) $row['id'], 'source_type' => 'contact', 'type' => 'client',
        'name' => trim( $row['first_name'] . ' ' . $row['last_name'] ), 'email' => (string) $row['email'], 'phone' => (string) $row['phone'], 'company' => '',
      );
    }
    return $rows;
  }


  private function get_marketing_contacts_index() {
    $stored = $this->get_marketing_store( 'contacts', array() );
    $base = $this->get_marketing_base_records();
    foreach ( $base as $key => $row ) {
      if ( ! isset( $stored[ $key ] ) ) {
        $stored[ $key ] = array(
          'source_key' => $row['source_key'],
          'source_type' => $row['source_type'],
          'source_id' => $row['source_id'],
          'name' => $row['name'],
          'email' => $row['email'],
          'phone' => $row['phone'],
          'company' => $row['company'],
          'type' => $row['type'],
          'enabled' => 0,
          'statuses' => $this->marketing_default_contact_statuses( $row['type'] ),
          'primary_list_id' => '',
          'lists' => array(),
          'tags' => array(),
          'segments_cache' => array(),
          'score' => 0,
          'score_value' => 0,
          'last_engagement' => '',
          'last_email_sent_at' => '',
          'last_email_open_at' => '',
          'last_email_click_at' => '',
          'last_interaction_at' => '',
          'notes' => '',
          'created_at' => $this->now_mysql(),
          'updated_at' => $this->now_mysql(),
        );
      } else {
        $stored[ $key ] = array_merge( $stored[ $key ], $row );
      }
      $stored[ $key ] = $this->normalize_marketing_contact_record( $stored[ $key ] );
    }
    return $stored;
  }



  private function marketing_source_labels() {
    return array(
      'prospect' => 'Prospect',
      'learner' => 'Apprenant',
      'company' => 'Entreprise',
      'funder' => 'Financeur',
      'trainer' => 'Formateur',
      'contact' => 'Contact',
      'public_form' => 'Formulaire public',
    );
  }


  private function normalize_marketing_contact_statuses( $input, $type = 'prospect' ) {
    $defaults = $this->marketing_default_contact_statuses( $type );
    $allowed = array_keys( $this->marketing_category_statuses() );
    $normalized = array();
    foreach ( $this->marketing_categories() as $key => $label ) {
      $value = isset( $input[ $key ] ) ? sanitize_key( $input[ $key ] ) : ( isset( $defaults[ $key ] ) ? $defaults[ $key ] : 'not_requested' );
      if ( ! in_array( $value, $allowed, true ) ) {
        $value = isset( $defaults[ $key ] ) ? $defaults[ $key ] : 'not_requested';
      }
      $normalized[ $key ] = $value;
    }
    return $normalized;
  }


  private function marketing_entity_name_map( $entity ) {
    $map = array();
    foreach ( $this->get_marketing_store( $entity, array() ) as $item ) {
      if ( isset( $item['id'] ) ) {
        $map[ (string) $item['id'] ] = isset( $item['name'] ) ? (string) $item['name'] : (string) $item['id'];
      }
    }
    return $map;
  }


  private function marketing_contact_related_labels( $ids, $entity ) {
    $ids = is_array( $ids ) ? $ids : array();
    if ( empty( $ids ) ) {
      return '';
    }
    $map = $this->marketing_entity_name_map( $entity );
    $labels = array();
    foreach ( $ids as $id ) {
      $id = (string) $id;
      $labels[] = isset( $map[ $id ] ) ? $map[ $id ] : $id;
    }
    return implode( ', ', array_filter( $labels ) );
  }


  private function get_marketing_smtp_status_label() {
    if ( defined( 'WPMS_PLUGIN_VER' ) ) {
      return 'WP Mail SMTP détecté';
    }
    if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'wp-mail-smtp/wp_mail_smtp.php' ) ) {
      return 'WP Mail SMTP détecté';
    }
    return 'WP Mail SMTP non détecté';
  }


  private function marketing_entity_labels() {
    return array(
      'lists' => array( 'single' => 'liste', 'plural' => 'Listes' ),
      'segments' => array( 'single' => 'segment', 'plural' => 'Segments' ),
      'tags' => array( 'single' => 'étiquette', 'plural' => 'Étiquettes' ),
      'templates' => array( 'single' => 'modèle', 'plural' => 'Modèles d’e-mails' ),
      'campaigns' => array( 'single' => 'campagne', 'plural' => 'Campagnes' ),
      'scenarios' => array( 'single' => 'scénario', 'plural' => 'Scénarios automatiques' ),
      'forms' => array( 'single' => 'formulaire', 'plural' => 'Formulaires' ),
      'fields' => array( 'single' => 'champ', 'plural' => 'Champs personnalisés' ),
      'webhooks' => array( 'single' => 'webhook', 'plural' => 'Webhooks / automatisations externes' ),
    );
  }


  private function normalize_marketing_csv_array( $value ) {
    if ( is_array( $value ) ) {
      return array_values( array_filter( array_map( 'sanitize_text_field', $value ) ) );
    }
    if ( '' === trim( (string) $value ) ) {
      return array();
    }
    return array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', (string) $value ) ) ) ) );
  }


  private function add_marketing_log( $message, $type = 'info', $context = array() ) {
    $logs = $this->get_marketing_store( 'logs', array() );
    array_unshift( $logs, array(
      'id' => uniqid( 'log_', true ),
      'date' => $this->now_mysql(),
      'type' => sanitize_key( $type ),
      'message' => sanitize_text_field( $message ),
      'context' => is_array( $context ) ? $context : array(),
    ) );
    $logs = array_slice( $logs, 0, 500 );
    $this->update_marketing_store( 'logs', $logs );
  }


  private function add_marketing_notification( $title, $message, $level = 'info' ) {
    $notifications = $this->get_marketing_store( 'notifications', array() );
    array_unshift( $notifications, array(
      'id' => uniqid( 'notif_', true ),
      'title' => sanitize_text_field( $title ),
      'message' => sanitize_text_field( $message ),
      'level' => sanitize_key( $level ),
      'created_at' => $this->now_mysql(),
    ) );
    $notifications = array_slice( $notifications, 0, 100 );
    $this->update_marketing_store( 'notifications', $notifications );
  }


  private function get_marketing_public_base_url() {
    $page_id = (int) get_option( 'acdc_of_marketing_public_page_id', 0 );
    if ( ! $page_id ) {
      $page_id = (int) $this->ensure_marketing_public_page();
    }
    return $page_id ? get_permalink( $page_id ) : home_url( '/' );
  }


  private function marketing_related_names_from_items( $ids, $items ) {
    $ids = is_array( $ids ) ? array_map( 'strval', $ids ) : array();
    if ( empty( $ids ) ) { return '—'; }
    $names = array();
    foreach ( $items as $item ) {
      if ( empty( $item['id'] ) || ! in_array( (string) $item['id'], $ids, true ) ) { continue; }
      $names[] = isset( $item['name'] ) ? $item['name'] : (string) $item['id'];
    }
    return empty( $names ) ? '—' : implode( ', ', $names );
  }


  private function marketing_related_name_by_id( $id, $items ) {
    if ( ! $id ) { return '—'; }
    foreach ( $items as $item ) {
      if ( isset( $item['id'] ) && (string) $item['id'] === (string) $id ) {
        return isset( $item['name'] ) ? $item['name'] : (string) $id;
      }
    }
    return '—';
  }


  private function get_marketing_email_archive() {
    $archive = $this->get_marketing_store( 'email_archive', array() );
    return is_array( $archive ) ? array_values( $archive ) : array();
  }

  private function update_marketing_email_archive( $archive ) {
    $this->update_marketing_store( 'email_archive', array_values( $archive ) );
  }

  private function parse_email_archive_headers( $headers ) {
    $parsed = array(
      'from_name' => '',
      'from_email' => '',
      'reply_to' => '',
      'source_module' => '',
      'source_action' => '',
      'related_entity_type' => '',
      'related_entity_id' => 0,
      'related_sub_id' => '',
      'category' => '',
      'email_audience' => '',
      'cc' => array(),
      'bcc' => array(),
      'clean_headers' => array(),
    );
    $header_lines = array();
    if ( is_array( $headers ) ) {
      $header_lines = $headers;
    } elseif ( is_string( $headers ) && '' !== trim( $headers ) ) {
      $header_lines = preg_split( '/
|
|
/', $headers );
    }
    foreach ( (array) $header_lines as $line ) {
      if ( ! is_string( $line ) ) {
        continue;
      }
      $line = trim( $line );
      if ( '' === $line || false === strpos( $line, ':' ) ) {
        if ( '' !== $line ) { $parsed['clean_headers'][] = $line; }
        continue;
      }
      list( $name, $value ) = array_map( 'trim', explode( ':', $line, 2 ) );
      $name_l = strtolower( $name );
      switch ( $name_l ) {
        case 'from':
          if ( preg_match( '/^(.*)<([^>]+)>$/', $value, $m ) ) {
            $parsed['from_name'] = trim( trim( $m[1] ), '"' );
            $parsed['from_email'] = sanitize_email( trim( $m[2] ) );
          } else {
            $parsed['from_email'] = sanitize_email( $value );
          }
          $parsed['clean_headers'][] = $line;
          break;
        case 'reply-to':
          $parsed['reply_to'] = sanitize_text_field( $value );
          $parsed['clean_headers'][] = $line;
          break;
        case 'cc':
          $parsed['cc'] = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $value ) ) ) );
          $parsed['clean_headers'][] = $line;
          break;
        case 'bcc':
          $parsed['bcc'] = array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $value ) ) ) );
          $parsed['clean_headers'][] = $line;
          break;
        case 'x-acdc-source-module':
          $parsed['source_module'] = sanitize_key( $value );
          break;
        case 'x-acdc-source-action':
          $parsed['source_action'] = sanitize_key( $value );
          break;
        case 'x-acdc-related-entity-type':
          $parsed['related_entity_type'] = sanitize_key( $value );
          break;
        case 'x-acdc-related-entity-id':
          $parsed['related_entity_id'] = absint( $value );
          break;
        case 'x-acdc-related-sub-id':
          $parsed['related_sub_id'] = sanitize_text_field( $value );
          break;
        case 'x-acdc-email-category':
          $parsed['category'] = sanitize_key( $value );
          break;
        case 'x-acdc-email-audience':
          $parsed['email_audience'] = sanitize_key( $value );
          break;
        default:
          $parsed['clean_headers'][] = $line;
          break;
      }
    }
    return $parsed;
  }

  public function capture_wp_mail_args_for_archive( $args ) {
    $this->acdc_last_wp_mail_args = is_array( $args ) ? $args : null;
    return $args;
  }

  private function archive_email_event( $args, $status = 'sent', $error_message = '', $mail_data = array() ) {
    if ( ! is_array( $args ) || empty( $args['to'] ) ) {
      return;
    }
    $headers_data = $this->parse_email_archive_headers( isset( $args['headers'] ) ? $args['headers'] : array() );
    $to = isset( $args['to'] ) ? $args['to'] : array();
    if ( ! is_array( $to ) ) {
      $to = array_map( 'trim', explode( ',', (string) $to ) );
    }
    $archive = $this->get_marketing_email_archive();
    $entry = array(
      'id' => uniqid( 'mail_', true ),
      'sent_at' => current_time( 'mysql' ),
      'subject' => isset( $args['subject'] ) ? sanitize_text_field( $args['subject'] ) : '',
      'to' => array_values( array_filter( array_map( 'sanitize_email', $to ) ) ),
      'cc' => array_values( $headers_data['cc'] ),
      'bcc' => array_values( $headers_data['bcc'] ),
      'from_name' => $headers_data['from_name'],
      'from_email' => $headers_data['from_email'],
      'reply_to' => $headers_data['reply_to'],
      'body' => isset( $args['message'] ) ? (string) $args['message'] : '',
      'headers' => array_values( $headers_data['clean_headers'] ),
      'attachments' => ! empty( $args['attachments'] ) ? array_values( (array) $args['attachments'] ) : array(),
      'status' => sanitize_key( $status ),
      'error_message' => sanitize_text_field( (string) $error_message ),
      'source_module' => $headers_data['source_module'] ? $headers_data['source_module'] : 'plugin',
      'source_action' => $headers_data['source_action'] ? $headers_data['source_action'] : 'wp_mail',
      'related_entity_type' => $headers_data['related_entity_type'],
      'related_entity_id' => (int) $headers_data['related_entity_id'],
      'related_sub_id' => $headers_data['related_sub_id'],
      'category' => $headers_data['category'] ? $headers_data['category'] : 'non_classee',
      'email_audience' => $headers_data['email_audience'],
      'triggered_by_user_id' => get_current_user_id(),
      'attempt_count' => ! empty( $mail_data['attempt_count'] ) ? absint( $mail_data['attempt_count'] ) : 1,
      'mailer' => ! empty( $mail_data['mailer'] ) ? sanitize_text_field( $mail_data['mailer'] ) : '',
    );
    array_unshift( $archive, $entry );
    $archive = array_slice( $archive, 0, 5000 );
    $this->update_marketing_email_archive( $archive );
    $this->add_marketing_log(
      'Archive e-mail mise à jour.',
      'info',
      array(
        'archive_id' => $entry['id'],
        'status' => $entry['status'],
        'subject' => $entry['subject'],
        'source_module' => $entry['source_module'],
      )
    );
  }

  public function handle_wp_mail_succeeded_for_archive( $mail_data ) {
    $args = is_array( $mail_data ) ? $mail_data : $this->acdc_last_wp_mail_args;
    $this->archive_email_event( $args, 'sent', '', is_array( $mail_data ) ? $mail_data : array() );
    $this->acdc_last_wp_mail_args = null;
  }

  public function handle_wp_mail_failed_for_archive( $wp_error ) {
    $args = $this->acdc_last_wp_mail_args;
    $message = '';
    if ( is_object( $wp_error ) && method_exists( $wp_error, 'get_error_message' ) ) {
      $message = $wp_error->get_error_message();
    }
    $this->archive_email_event( $args, 'failed', $message );
    $this->acdc_last_wp_mail_args = null;
  }

  private function get_marketing_archive_entry( $archive_id ) {
    foreach ( $this->get_marketing_email_archive() as $entry ) {
      if ( isset( $entry['id'] ) && (string) $entry['id'] === (string) $archive_id ) {
        return $entry;
      }
    }
    return null;
  }

  private function get_archive_recipient_label( $entry ) {
    global $wpdb;
    $type = isset( $entry['related_entity_type'] ) ? (string) $entry['related_entity_type'] : '';
    $id   = isset( $entry['related_entity_id'] ) ? (int) $entry['related_entity_id'] : 0;
    $name = '';
    $company = '';
    if ( $id > 0 ) {
      if ( 'prospect' === $type ) {
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT first_name, last_name, company_name FROM {$this->prospect_table} WHERE id = %d", $id ) );
        if ( $row ) {
          $name    = trim( $row->first_name . ' ' . $row->last_name );
          $company = (string) $row->company_name;
        }
      } elseif ( 'learner' === $type || 'apprenant' === $type ) {
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT first_name, last_name FROM {$this->learner_table} WHERE id = %d", $id ) );
        if ( $row ) {
          $name = trim( $row->first_name . ' ' . $row->last_name );
        }
      } elseif ( 'trainer' === $type || 'formateur' === $type ) {
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT first_name, last_name FROM {$this->trainer_table} WHERE id = %d", $id ) );
        if ( $row ) {
          $name = trim( $row->first_name . ' ' . $row->last_name );
        }
      } elseif ( 'funder' === $type || 'financeur' === $type ) {
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT name FROM {$this->funder_table} WHERE id = %d", $id ) );
        if ( $row ) {
          $name    = '';
          $company = (string) $row->name;
        }
      } elseif ( 'company' === $type || 'entreprise' === $type ) {
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT signer_first_name, signer_last_name, name FROM {$this->company_table} WHERE id = %d", $id ) );
        if ( $row ) {
          $name    = trim( $row->signer_first_name . ' ' . $row->signer_last_name );
          $company = (string) $row->name;
        }
      } elseif ( 'contact' === $type ) {
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT first_name, last_name FROM {$this->contact_table} WHERE id = %d", $id ) );
        if ( $row ) {
          $name = trim( $row->first_name . ' ' . $row->last_name );
        }
      }
    }
    $label = '';
    if ( '' !== trim( $name ) ) {
      $label = trim( $name );
      if ( '' !== trim( $company ) ) {
        $label .= ' — ' . trim( $company );
      }
    } elseif ( '' !== trim( $company ) ) {
      $label = trim( $company );
    }
    if ( '' === $label && ! empty( $entry['to'] ) ) {
      $email_raw = is_array( $entry['to'] ) ? $entry['to'][0] : (string) $entry['to'];
      $email_clean = sanitize_email( trim( $email_raw ) );
      // ACDC 3.25.77 — Fallback : chercher le nom par email dans toutes les tables
      if ( $email_clean && is_email( $email_clean ) ) {
        $found = null;
        // Prospect
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT first_name, last_name FROM {$this->prospect_table} WHERE email = %s LIMIT 1", $email_clean ) );
        if ( $row ) { $found = trim( $row->first_name . ' ' . $row->last_name ); }
        // Apprenant
        if ( ! $found ) {
          $row = $wpdb->get_row( $wpdb->prepare( "SELECT first_name, last_name FROM {$this->learner_table} WHERE email = %s LIMIT 1", $email_clean ) );
          if ( $row ) { $found = trim( $row->first_name . ' ' . $row->last_name ); }
        }
        // Formateur
        if ( ! $found ) {
          $row = $wpdb->get_row( $wpdb->prepare( "SELECT first_name, last_name FROM {$this->trainer_table} WHERE email = %s LIMIT 1", $email_clean ) );
          if ( $row ) { $found = trim( $row->first_name . ' ' . $row->last_name ); }
        }
        // Utilisateur WP (admins/gestionnaires)
        if ( ! $found ) {
          $wp_user = get_user_by( 'email', $email_clean );
          if ( $wp_user ) { $found = trim( $wp_user->first_name . ' ' . $wp_user->last_name ) ?: $wp_user->display_name; }
        }
        $label = ( $found && '' !== trim( $found ) ) ? trim( $found ) : $email_raw;
      } else {
        $label = $email_raw;
      }
    }
    return $label;
  }

  private function get_archive_email_status_label( $entry ) {
    $status  = isset( $entry['status'] ) ? (string) $entry['status'] : 'sent';
    $attempt = isset( $entry['attempt_count'] ) ? (int) $entry['attempt_count'] : 1;
    if ( 'failed' === $status ) {
      return 'Échec';
    }
    if ( $attempt <= 1 ) {
      return 'Envoyé';
    }
    $rappel = $attempt - 1;
    if ( $rappel >= 3 ) {
      return '3e rappel';
    }
    if ( 2 === $rappel ) {
      return '2e rappel';
    }
    return '1er rappel';
  }

  private function touch_marketing_archive_resend( $archive_id ) {
    $archive = $this->get_marketing_email_archive();
    $updated = false;
    foreach ( $archive as $index => $entry ) {
      if ( ! isset( $entry['id'] ) || (string) $entry['id'] !== (string) $archive_id ) {
        continue;
      }
      $entry['last_resent_at'] = current_time( 'mysql' );
      $entry['resent_count']   = ! empty( $entry['resent_count'] ) ? absint( $entry['resent_count'] ) + 1 : 1;
      $archive[ $index ] = $entry;
      $updated = true;
      break;
    }
    if ( $updated ) {
      $this->update_marketing_email_archive( $archive );
    }
    return $updated;
  }

  private function get_marketing_archive_for_entity( $entity_type, $entity_id ) {
    $rows = array();
    foreach ( $this->get_marketing_email_archive() as $entry ) {
      if ( (string) $entity_type === (string) ( isset( $entry['related_entity_type'] ) ? $entry['related_entity_type'] : '' ) && (int) $entity_id === (int) ( isset( $entry['related_entity_id'] ) ? $entry['related_entity_id'] : 0 ) ) {
        $rows[] = $entry;
      }
    }
    return $rows;
  }

}
