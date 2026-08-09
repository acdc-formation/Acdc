<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class ACDC_Formation_SAAS_Plugin {

  use Acdc_Proposals_Core_Trait;
  use Acdc_Proposals_Actions_Trait;
  use Acdc_Proposals_Render_Trait;
  use ACDC_Auth_Portal_Core_Trait;
  use ACDC_Auth_Portal_Actions_Trait;
  use ACDC_Auth_Portal_Render_Trait;
  use ACDC_Marketing_Core_Trait;
  use ACDC_Marketing_Actions_Trait;
  use ACDC_Marketing_Render_Trait;
  use ACDC_Questionnaires_Core_Trait;
  use ACDC_Questionnaires_Engine_Trait;
  use ACDC_Questionnaires_Actions_Trait;
  use ACDC_Questionnaires_Render_Trait;
  use ACDC_Settings_Catalog_Core_Trait;
  use ACDC_Settings_Catalog_Actions_Trait;
  use ACDC_Settings_Catalog_Render_Trait;
  use ACDC_Settings_Catalog_Programme_PDF_Trait;
  use ACDC_Compliance_Quality_Core_Trait;
  use ACDC_Compliance_Quality_Actions_Trait;
  use ACDC_Compliance_Quality_Render_Trait;
  use ACDC_Learners_Contacts_Core_Trait;
  use ACDC_Learners_Contacts_Actions_Trait;
  use ACDC_Learners_Contacts_Render_Trait;
  use ACDC_Evaluations_Core_Trait;
  use ACDC_Evaluations_Actions_Trait;
  use ACDC_Evaluations_Render_Trait;
  /* ACDC 3.21.00 — Socle commun aux campagnes asynchrones (token + email + relance). */
  use ACDC_Async_Dispatcher_Trait;
  /* ACDC 3.21.00 — Module Quizzes (moteur unifié Quiz live + Tests de positionnement + Évaluations des acquis). */
  use ACDC_Quizzes_Core_Trait;
  use ACDC_Quizzes_Actions_Trait;
  use ACDC_Quizzes_Engine_Trait;
  use ACDC_Quizzes_Render_Trait;
  use ACDC_Quizzes_Render_Results_Trait;
  use ACDC_Quizzes_Render_Live_Trait;
  use ACDC_Crm_Commercial_Core_Trait;
  use ACDC_Crm_Commercial_Actions_Trait;
  use ACDC_Crm_Commercial_Render_Trait;
  use ACDC_Crm_Commercial_Activity_Trait;
  use ACDC_Sessions_Core_Trait;
  use ACDC_Sessions_Actions_Trait;
  use ACDC_Sessions_Render_Trait;
  use ACDC_Dossiers_Contracts_Core_Trait;
  use ACDC_Dossiers_Contracts_Actions_Trait;
  use ACDC_Dossiers_Contracts_Render_Trait;
  use ACDC_Documents_Billing_Core_Trait;
  use ACDC_Documents_Billing_Actions_Trait;
  use ACDC_Documents_Billing_Render_Trait;
  use ACDC_Agent_Audit_Core_Trait;
  use ACDC_Agent_Audit_Actions_Trait;
  use ACDC_Learner_Portal_Core_Trait;
  use ACDC_Learner_Portal_Actions_Trait;
  use ACDC_Learner_Portal_Render_Trait;
  /* ACDC 3.20.83 — Module portail formateur. */
  use ACDC_Trainer_Portal_Core_Trait;
  use ACDC_Trainer_Portal_Actions_Trait;
  use ACDC_Trainer_Portal_Render_Trait;
  use ACDC_Trainer_Portal_Quizzes_Render_Trait;
  use ACDC_Trainer_Portal_Results_Render_Trait;
  use ACDC_Kernel_Core_Trait;
  use ACDC_Kernel_Actions_Trait;
  /* ACDC 3.25.144 — Module MCP : abilities exposées via mcp-adapter. */
  use ACDC_Mcp_Abilities_Trait;
  /* ACDC 3.23.11 — Module veille automatisée IA (V1→V6). */
  /* ACDC 3.25.185 — Orchestration du parcours. */
  use ACDC_Workflow_Core_Trait;
  use ACDC_Workflow_Engine_Trait;
  use ACDC_Workflow_Handlers_Trait;
  use ACDC_Workflow_Actions_Trait;
  use ACDC_Workflow_Render_Trait;
  use ACDC_Watch_Core_Trait;
  use ACDC_Watch_AI_Trait;
  use ACDC_Watch_Actions_Trait;
  use ACDC_Watch_Render_Trait;
  use ACDC_Kernel_Modular_Render_Trait;
  use ACDC_Export_CSV_Trait;
  use ACDC_Excel_Prospects_Trait;
  use ACDC_Agent_Audit_Render_Trait, ACDC_Kernel_Render_Trait {
    ACDC_Agent_Audit_Render_Trait::render_admin_agent_audit_page insteadof ACDC_Kernel_Render_Trait;
    ACDC_Kernel_Render_Trait::render_front_rating_alerts_tab insteadof ACDC_Agent_Audit_Render_Trait;
  }

  private static $instance = null;

  /* ACDC 3.22.18 — Registre des réclamations transverse (H4 — ind. 31 Qualiopi). */
  private $complaint_table;
  /* ACDC 3.23.11 — Module veille automatisée IA (V1→V6). */
  private $watch_items_table;
  /* ACDC 3.25.185 — Workflow : le parcours et ses étapes. */
  private $workflow_run_table;
  private $workflow_step_table;
  private $company_table;
  private $contact_table;
  private $document_table;
  private $formation_table;
  private $session_table;
  private $learner_table;
  private $need_table;
  private $prospect_table;
  private $group_table;
  private $funder_table;
  private $trainer_table;
  /* ACDC 3.22.1 — Table missions/contrats formateurs. */
  private $trainer_contract_table;
  /* ACDC 3.24.11 — Table bilans compétences formateurs (ind. 21). */
  private $trainer_evaluation_table;
  /* ACDC 3.20.82 — Module portail formateur. */
  private $trainer_document_table;
  private $trainer_resource_table;
  /* ACDC 3.20.83 — Auth custom du portail formateur (calquée sur l'apprenant). */
  private $trainer_portal_account_table;
  private $trainer_portal_token_table;
  private $trainer_portal_session_table;
  private $trainer_portal_log_table;
  private $quiz_table;
  private $positioning_test_table;
  private $evaluation_table;
  private $need_analysis_table;
  /* ACDC 3.21.10 — Module Analyse du besoin — bibliothèque modulaire. */
  private $need_block_table;
  private $need_question_table;
  /* ACDC 3.21.13 — Page publique formulaire analyse du besoin. */
  private $nad_public_page_id;
  /* ACDC 3.21.15 — Table thématiques de formation. */
  private $thematique_table;
  private $quote_table;
  /* ACDC 3.24.20 — Table factures réelles (activation facturation M1). */
  private $invoice_table;
  private $registration_contract_table;
  private $training_registration_table;
  private $prospect_rdv_table;
  private $prospect_activity_table;
  private $pre_meeting_table;
  private $questionnaire_session_table;
  private $questionnaire_participant_table;
  private $questionnaire_answer_table;
  private $questionnaire_model_table;
  private $questionnaire_action_table;
  private $questionnaire_log_table;
  private $learner_portal_account_table;
  private $learner_portal_token_table;
  private $learner_portal_session_table;
  private $learner_portal_log_table;
  private $system_log_table;
  /* ACDC 3.24.28 — Cahier de texte formateur (M8b). */
  private $trainer_logbook_table;
  private $acdc_last_wp_mail_args = null;

  public static function get_instance() {
    if ( null === self::$instance ) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public static function activate() {
    // ARCH-03 : utilisation du singleton pour éviter la double instanciation
    $plugin = self::get_instance();
    $plugin->install_or_update();
    $plugin->ensure_default_pages();
    /* ACDC 3.25.144 — Provisionne les rôles/capacités MCP dédiés. */
    if ( method_exists( $plugin, 'acdc_mcp_setup_roles' ) ) {
      $plugin->acdc_mcp_setup_roles();
    }
    flush_rewrite_rules();
  }

  public static function deactivate() {
    if ( function_exists( 'delete_transient' ) ) {
      delete_transient( 'acdc_of_saas_runtime_notice' );
    }
    /* ACDC 3.25.144 — Suppression propre des rôles/capacités MCP dédiés. */
    $plugin = self::get_instance();
    if ( method_exists( $plugin, 'acdc_mcp_remove_roles' ) ) {
      $plugin->acdc_mcp_remove_roles();
    }
    flush_rewrite_rules();
  }

  private function __construct() {
    global $wpdb;

    $this->complaint_table = $wpdb->prefix . 'acdc_of_complaints';
    /* ACDC 3.23.11 — Module veille automatisée IA. */
    $this->watch_items_table = $wpdb->prefix . 'acdc_of_watch_items';
    $this->company_table  = $wpdb->prefix . 'acdc_of_companies';
    $this->contact_table  = $wpdb->prefix . 'acdc_of_contacts';
    $this->document_table = $wpdb->prefix . 'acdc_of_documents';
    $this->formation_table = $wpdb->prefix . 'acdc_of_formations';
    $this->session_table  = $wpdb->prefix . 'acdc_of_sessions';
    $this->learner_table  = $wpdb->prefix . 'acdc_of_learners';
    $this->need_table   = $wpdb->prefix . 'acdc_of_needs';
    $this->prospect_table = $wpdb->prefix . 'acdc_of_prospects';
    $this->group_table   = $wpdb->prefix . 'acdc_of_groups';
    $this->funder_table  = $wpdb->prefix . 'acdc_of_funders';
    $this->trainer_table  = $wpdb->prefix . 'acdc_of_trainers';
    /* ACDC 3.22.1 — Table missions/contrats formateurs. */
    $this->trainer_contract_table = $wpdb->prefix . 'acdc_of_trainer_contracts';
    /* ACDC 3.24.11 — Table bilans compétences formateurs (ind. 21). */
    $this->trainer_evaluation_table = $wpdb->prefix . 'acdc_of_trainer_evaluations';
    /* ACDC 3.24.28 — Table cahier de texte formateur (M8b). */
    $this->trainer_logbook_table = $wpdb->prefix . 'acdc_of_trainer_logbook';
    /* ACDC 3.20.82 — Module portail formateur. */
    $this->trainer_document_table = $wpdb->prefix . 'acdc_of_trainer_documents';
    $this->trainer_resource_table = $wpdb->prefix . 'acdc_of_trainer_resources';
    /* ACDC 3.20.83 — Auth custom du portail formateur. */
    $this->trainer_portal_account_table = $wpdb->prefix . 'acdc_of_trainer_portal_accounts';
    $this->trainer_portal_token_table   = $wpdb->prefix . 'acdc_of_trainer_portal_tokens';
    $this->trainer_portal_session_table = $wpdb->prefix . 'acdc_of_trainer_portal_sessions';
    $this->trainer_portal_log_table     = $wpdb->prefix . 'acdc_of_trainer_portal_logs';
    $this->quiz_table   = $wpdb->prefix . 'acdc_of_quizzes';
    $this->positioning_test_table = $wpdb->prefix . 'acdc_of_positioning_tests';
    $this->evaluation_table = $wpdb->prefix . 'acdc_of_evaluations';
    $this->need_analysis_table  = $wpdb->prefix . 'acdc_of_need_analyses';
    $this->need_block_table     = $wpdb->prefix . 'acdc_of_need_blocks';
    $this->need_question_table  = $wpdb->prefix . 'acdc_of_need_questions';
    $this->nad_public_page_id   = (int) get_option( 'acdc_of_nad_public_page_id', 0 );
    $this->thematique_table     = $wpdb->prefix . 'acdc_of_thematiques';
    $this->quote_table          = $wpdb->prefix . 'acdc_of_quotes';
    $this->invoice_table        = $wpdb->prefix . 'acdc_of_invoices';
    $this->registration_contract_table = $wpdb->prefix . 'acdc_of_registration_contracts';
    $this->training_registration_table = $wpdb->prefix . 'acdc_of_training_registrations';
    $this->prospect_rdv_table = $wpdb->prefix . 'acdc_of_prospect_rdvs';
    $this->prospect_activity_table = $wpdb->prefix . 'acdc_of_prospect_activities';
    $this->pre_meeting_table = $wpdb->prefix . 'acdc_of_pre_meetings';
    $this->questionnaire_session_table = $wpdb->prefix . 'acdc_of_questionnaire_sessions';
    $this->questionnaire_participant_table = $wpdb->prefix . 'acdc_of_questionnaire_session_participants';
    $this->questionnaire_answer_table = $wpdb->prefix . 'acdc_of_questionnaire_session_answers';
    $this->questionnaire_model_table  = $wpdb->prefix . 'acdc_of_questionnaire_models';
    $this->questionnaire_action_table = $wpdb->prefix . 'acdc_of_questionnaire_actions';
    $this->questionnaire_log_table    = $wpdb->prefix . 'acdc_of_questionnaire_logs';
    $this->learner_portal_account_table = $wpdb->prefix . 'acdc_of_learner_portal_accounts';
    $this->learner_portal_token_table   = $wpdb->prefix . 'acdc_of_learner_portal_tokens';
    $this->learner_portal_session_table = $wpdb->prefix . 'acdc_of_learner_portal_sessions';
    $this->learner_portal_log_table     = $wpdb->prefix . 'acdc_of_learner_portal_logs';
    /* ACDC 3.25.185 — Workflow : le parcours et ses étapes. */
    $this->workflow_run_table  = $wpdb->prefix . 'acdc_of_workflow_runs';
    $this->workflow_step_table = $wpdb->prefix . 'acdc_of_workflow_steps';

    add_action( 'init', array( $this, 'maybe_upgrade' ) );
    /* ACDC 3.25.157 — Les pages wp-admin du plugin sont enregistrées puis retirées
       du menu : y rediriger produit « Vous n'avez pas l'autorisation » alors que
       l'action a réussi. Ce filtre réoriente ces redirections vers l'onglet
       extranet équivalent quand la demande ne vient pas de wp-admin. Il couvre
       les 126 redirections existantes et celles à venir. */
    add_filter( 'wp_redirect', array( $this, 'acdc_redirect_hidden_admin_page_to_front' ), 5, 1 );
    add_action( 'admin_init', array( $this, 'enforce_plugin_request_permissions' ), 1 );
    add_action( 'init', array( $this, 'maybe_repair_runtime_state' ), 6 );
    add_action( 'init', array( $this, 'maybe_repair_learner_portal_runtime_state' ), 7 );
    add_action( 'init', array( $this, 'maybe_restore_internal_admin_role' ), 1 );
    add_action( 'init', array( $this, 'maybe_disable_expired_agent_audit_users' ), 2 );
    add_action( 'init', array( $this, 'register_shortcodes' ) );
    /* ACDC 3.23.11 — Récurrences cron custom pour la veille IA. */
    add_filter( 'cron_schedules', array( $this, 'acdc_register_cron_schedules' ) );
    add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_assets' ) );
    /* ACDC 3.20.74 — autoriser font-size, color, etc. via wp_kses pour l'éditeur riche centralisé. */
    add_filter( 'safe_style_css', array( $this, 'extend_safe_style_css' ) );
    add_filter( 'template_include', array( $this, 'use_blank_template_for_acdc_pages' ) );
    add_action( 'template_redirect', array( $this, 'maybe_redirect_legacy_pages' ) );
    add_action( 'template_redirect', array( $this, 'handle_programme_pdf_request' ), 1 );
    // ACDC 3.25.135 — Vérification publique d'authenticité des attestations (?acdc_verify=…).
    add_action( 'template_redirect', array( $this, 'maybe_handle_attestation_verify' ), 1 );
    add_action( 'template_redirect', array( $this, 'handle_front_trf_actions' ), 2 );
    /* ACDC 3.24.43 — Envoi e-mail direct depuis le front (contourne WAF admin-post.php) */
    add_action( 'template_redirect', array( $this, 'maybe_handle_front_direct_email' ), 5 );
    add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
    add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    add_action( 'admin_footer', array( $this, 'print_prospect_ui_patch_assets' ) );
    add_action( 'wp_footer', array( $this, 'print_prospect_ui_patch_assets' ) );
    add_action( 'admin_head', array( $this, 'print_plugin_favicon' ) );
    add_action( 'admin_notices', array( $this, 'render_runtime_state_notice' ) );
    add_action( 'admin_notices', array( $this, 'render_agent_audit_mode_notice' ) );
    /* ACDC 3.25.184 — Migration de schéma abandonnée après trois tentatives : le
       site reste en ligne, mais l'administration doit le dire, et proposer une
       relance manuelle. */
    add_action( 'admin_notices', array( $this, 'acdc_render_upgrade_blocked_notice' ) );
    add_action( 'admin_post_acdc_retry_upgrade', array( $this, 'handle_retry_upgrade' ) );
    /* ACDC 3.25.185 — Workflow : cron d'orchestration et écrans de pilotage. */
    add_action( 'acdc_of_workflow_cron', array( $this, 'acdc_wf_cron' ) );
    add_action( 'admin_post_acdc_wf_save_settings', array( $this, 'acdc_wf_handle_save_settings' ) );
    add_action( 'admin_post_acdc_wf_dismiss_task', array( $this, 'acdc_wf_handle_dismiss_task' ) );
    add_action( 'admin_post_acdc_wf_purge_orphan_emargements', array( $this, 'acdc_wf_handle_purge_orphan_emargements' ) );
    add_action( 'wp_ajax_acdc_save_global_column_width', array( $this, 'ajax_save_global_column_width' ) );
    add_action( 'wp_ajax_acdc_save_global_column_widths', array( $this, 'ajax_save_global_column_widths' ) );
    /* ACDC 3.20.67 — Verrouillage des largeurs par tableau métier. */
    add_action( 'wp_ajax_acdc_save_table_lock', array( $this, 'ajax_save_table_lock' ) );
    /* ACDC 3.23.2 — Forçage manuel du statut workflow dossier. */
    add_action( 'wp_ajax_acdc_set_registration_workflow_status', array( $this, 'ajax_set_registration_workflow_status' ) );

    add_action( 'admin_post_nopriv_acdc_front_login', array( $this, 'handle_front_login' ) );
    add_action( 'admin_post_nopriv_acdc_learner_login', array( $this, 'handle_learner_portal_login' ) );
    add_action( 'admin_post_acdc_learner_login', array( $this, 'handle_learner_portal_login' ) );
    add_action( 'admin_post_acdc_learner_logout', array( $this, 'handle_learner_portal_logout' ) );
    add_action( 'admin_post_nopriv_acdc_learner_activate', array( $this, 'handle_learner_portal_activation' ) );
    add_action( 'admin_post_acdc_learner_activate', array( $this, 'handle_learner_portal_activation' ) );
    add_action( 'admin_post_nopriv_acdc_learner_request_reset', array( $this, 'handle_learner_portal_request_reset' ) );
    add_action( 'admin_post_acdc_learner_request_reset', array( $this, 'handle_learner_portal_request_reset' ) );
    add_action( 'admin_post_nopriv_acdc_learner_reset_password', array( $this, 'handle_learner_portal_reset_password' ) );
    add_action( 'admin_post_acdc_learner_reset_password', array( $this, 'handle_learner_portal_reset_password' ) );
    add_action( 'admin_post_nopriv_acdc_learner_expired_contact', array( $this, 'handle_learner_portal_expired_contact' ) );
    add_action( 'admin_post_acdc_learner_expired_contact', array( $this, 'handle_learner_portal_expired_contact' ) );
    add_action( 'admin_post_acdc_learner_change_password', array( $this, 'handle_learner_portal_change_password' ) );
    add_action( 'admin_post_acdc_learner_update_profile', array( $this, 'handle_learner_portal_update_profile' ) );
    add_action( 'admin_post_nopriv_acdc_learner_download_document', array( $this, 'handle_learner_portal_download_document' ) );
    add_action( 'admin_post_acdc_learner_download_document', array( $this, 'handle_learner_portal_download_document' ) );
    add_action( 'admin_post_nopriv_acdc_learner_open_resource', array( $this, 'handle_learner_portal_open_resource' ) );
    add_action( 'admin_post_acdc_learner_open_resource', array( $this, 'handle_learner_portal_open_resource' ) );
    /* ACDC 3.20.83 — Actions du portail formateur (auth custom). */
    add_action( 'admin_post_nopriv_acdc_trainer_login', array( $this, 'handle_trainer_login' ) );
    add_action( 'admin_post_acdc_trainer_login', array( $this, 'handle_trainer_login' ) );
    add_action( 'admin_post_acdc_trainer_logout', array( $this, 'handle_trainer_logout' ) );
    add_action( 'admin_post_nopriv_acdc_trainer_request_reset', array( $this, 'handle_trainer_request_reset' ) );
    add_action( 'admin_post_acdc_trainer_request_reset', array( $this, 'handle_trainer_request_reset' ) );
    add_action( 'admin_post_nopriv_acdc_trainer_reset_password', array( $this, 'handle_trainer_reset_password' ) );
    add_action( 'admin_post_acdc_trainer_reset_password', array( $this, 'handle_trainer_reset_password' ) );
    add_action( 'admin_post_nopriv_acdc_trainer_activate', array( $this, 'handle_trainer_activate' ) );
    add_action( 'admin_post_acdc_trainer_activate', array( $this, 'handle_trainer_activate' ) );
    /* ACDC 3.25.99 — Portail formateur : auto-réparation runtime + maintenance planifiée (alignement sur le scénario du portail apprenant). */
    add_action( 'init', array( $this, 'maybe_repair_trainer_portal_runtime_state' ), 7 );
    add_action( 'acdc_of_trainer_portal_cron_maintenance', array( $this, 'handle_trainer_portal_cron_maintenance' ) );
    /* ACDC 3.20.87 — Bibliothèque côté formateur (auth custom). */
    add_action( 'admin_post_acdc_trainer_upload_own_document', array( $this, 'handle_trainer_upload_own_document' ) );
    add_action( 'admin_post_acdc_trainer_delete_own_document', array( $this, 'handle_trainer_delete_own_document' ) );
    add_action( 'admin_post_acdc_trainer_download_own_document', array( $this, 'handle_trainer_download_own_document' ) );
    /* ACDC 3.20.89 — Profil formateur (édition par le formateur lui-même). */
    add_action( 'admin_post_acdc_trainer_update_own_profile', array( $this, 'handle_trainer_update_own_profile' ) );
    /* ACDC 3.20.90 — Calendrier annuel des disponibilités. */
    add_action( 'admin_post_acdc_trainer_update_weekly_schedule', array( $this, 'handle_trainer_update_weekly_schedule' ) );
    add_action( 'admin_post_acdc_trainer_toggle_availability', array( $this, 'handle_trainer_toggle_availability' ) );
    /* ACDC 3.24.28 — M8b : Bilan post-formation + cahier de texte. */
    add_action( 'admin_post_acdc_trainer_save_session_report', array( $this, 'handle_trainer_save_session_report' ) );
    add_action( 'admin_post_acdc_trainer_save_logbook_entry', array( $this, 'handle_trainer_save_logbook_entry' ) );
    add_shortcode( 'acdc_trainer_portal_login', array( $this, 'render_trainer_portal_login_shortcode' ) );
    add_action( 'admin_post_acdc_front_login', array( $this, 'handle_front_login' ) );
    add_action( 'admin_post_acdc_front_logout', array( $this, 'handle_front_logout' ) );
    add_filter( 'authenticate', array( $this, 'maybe_block_agent_audit_login' ), 30, 3 );
    add_filter( 'pre_wp_mail', array( $this, 'maybe_block_mail_during_agent_audit' ), 10, 2 );
    add_filter( 'wp_mail', array( $this, 'capture_wp_mail_args_for_archive' ), 5, 1 );
    add_action( 'wp_mail_succeeded', array( $this, 'handle_wp_mail_succeeded_for_archive' ), 10, 1 );
    add_action( 'wp_mail_failed', array( $this, 'handle_wp_mail_failed_for_archive' ), 10, 1 );

    add_action( 'admin_post_acdc_save_company', array( $this, 'handle_save_company' ) );
    add_action( 'admin_post_acdc_delete_company', array( $this, 'handle_delete_company' ) );
    add_action( 'admin_post_acdc_save_contact', array( $this, 'handle_save_contact' ) );
    add_action( 'admin_post_acdc_delete_contact', array( $this, 'handle_delete_contact' ) );
    add_action( 'admin_post_acdc_save_document', array( $this, 'handle_save_document' ) );
    add_action( 'admin_post_acdc_delete_document', array( $this, 'handle_delete_document' ) );
    add_action( 'admin_post_acdc_save_formation', array( $this, 'handle_save_formation' ) );
    add_action( 'admin_post_acdc_delete_formation', array( $this, 'handle_delete_formation' ) );
    add_action( 'admin_post_acdc_duplicate_formation', array( $this, 'handle_duplicate_formation' ) );
    add_action( 'admin_post_acdc_toggle_formation_archive', array( $this, 'handle_toggle_formation_archive' ) );
    add_action( 'admin_post_acdc_import_formations', array( $this, 'handle_import_formations' ) );
    add_action( 'admin_post_acdc_export_formations', array( $this, 'handle_export_formations' ) );
    /* ACDC 3.21.69 — Export CSV répertoires */
    add_action( 'admin_post_acdc_export_learners_csv',  array( $this, 'handle_export_learners_csv' ) );
    add_action( 'admin_post_acdc_export_prospects_csv', array( $this, 'handle_export_prospects_csv' ) );
    /* ACDC 3.25.83 — Import / Export Excel prospects */
    add_action( 'admin_post_acdc_export_prospects_xlsx',   array( $this, 'handle_export_prospects_xlsx' ) );
    add_action( 'admin_post_acdc_import_prospects_xlsx',   array( $this, 'handle_import_prospects_xlsx' ) );
    add_action( 'admin_post_acdc_prospects_xlsx_template', array( $this, 'handle_prospects_xlsx_template' ) );
    add_action( 'admin_post_acdc_export_trainers_csv',  array( $this, 'handle_export_trainers_csv' ) );
    add_action( 'admin_post_acdc_export_sessions_csv',  array( $this, 'handle_export_sessions_csv' ) );
    /* ACDC 3.24.30 — RGPD : export et anonymisation apprenant */
    add_action( 'admin_post_acdc_rgpd_export_learner',    array( $this, 'handle_acdc_rgpd_export_learner' ) );
    add_action( 'admin_post_acdc_rgpd_anonymize_learner', array( $this, 'handle_acdc_rgpd_anonymize_learner' ) );
    // ACDC 3.21.36 — Sync formations
    add_action( 'admin_post_acdc_manual_sync_formations', array( $this, 'handle_manual_sync_formations' ) );
    add_action( 'admin_post_acdc_save_sync_settings',     array( $this, 'handle_save_sync_settings' ) );
    add_action( 'rest_api_init', array( $this, 'register_saas_rest_routes' ) );
    add_action( 'admin_init',    array( $this, 'acdc_of_ensure_rest_api_key' ) );
    add_action( 'admin_post_acdc_save_session', array( $this, 'handle_save_session' ) );
    add_action( 'admin_post_acdc_save_session_builder', array( $this, 'handle_save_session_builder' ) );
    add_action( 'admin_post_acdc_delete_session', array( $this, 'handle_delete_session' ) );
    add_action( 'admin_post_acdc_save_learner', array( $this, 'handle_save_learner' ) );
    add_action( 'admin_post_acdc_delete_learner', array( $this, 'handle_delete_learner' ) );
    /* ACDC 3.24.40 - E-mail direct + RGPD */
    add_action( 'wp_ajax_acdc_send_direct_email',    array( $this, 'handle_send_direct_email_ajax' ) );
    add_action( 'admin_post_acdc_rgpd_export_learner',      array( $this, 'handle_acdc_rgpd_export_learner' ) );
    add_action( 'admin_post_acdc_rgpd_anonymize_learner',   array( $this, 'handle_acdc_rgpd_anonymize_learner' ) );
    add_action( 'admin_post_acdc_save_need', array( $this, 'handle_save_need' ) );
    add_action( 'admin_post_acdc_delete_need', array( $this, 'handle_delete_need' ) );
    add_action( 'admin_post_acdc_resend_need_email', array( $this, 'handle_resend_need_email' ) );
    add_action( 'admin_post_acdc_download_need_pdf',        array( $this, 'handle_download_need_pdf' ) );
    add_action( 'admin_post_nopriv_acdc_download_need_pdf', array( $this, 'handle_download_need_pdf' ) );
    add_action( 'admin_post_acdc_download_proposal_pdf',        array( $this, 'handle_download_proposal_pdf' ) );
    add_action( 'admin_post_nopriv_acdc_download_proposal_pdf', array( $this, 'handle_download_proposal_pdf' ) );
    add_action( 'admin_post_acdc_save_prospect', array( $this, 'handle_save_prospect' ) );
    add_action( 'admin_post_acdc_quick_update_prospect_field', array( $this, 'handle_quick_update_prospect_field' ) );
    add_action( 'admin_post_acdc_save_prospect_rdv', array( $this, 'handle_save_prospect_rdv' ) );
    add_action( 'admin_post_acdc_cancel_prospect_rdv', array( $this, 'handle_cancel_prospect_rdv' ) );
    add_action( 'admin_post_acdc_save_prospect_rdv_post_comment', array( $this, 'handle_save_prospect_rdv_post_comment' ) );
    /* ACDC 3.25.86 — Journal d'activité commerciale */
    add_action( 'admin_post_acdc_log_prospect_activity',    array( $this, 'handle_log_prospect_activity' ) );
    add_action( 'admin_post_acdc_delete_prospect_activity', array( $this, 'handle_delete_prospect_activity' ) );
    add_action( 'admin_post_acdc_save_pre_meeting', array( $this, 'handle_save_pre_meeting' ) );
    add_action( 'admin_post_acdc_delete_pre_meeting', array( $this, 'handle_delete_pre_meeting' ) );
    add_action( 'admin_post_acdc_delete_prospect', array( $this, 'handle_delete_prospect' ) );
    add_action( 'admin_post_acdc_bulk_prospect_action', array( $this, 'handle_bulk_prospect_action' ) );
    add_action( 'admin_post_acdc_save_group', array( $this, 'handle_save_group' ) );
    add_action( 'admin_post_acdc_delete_group', array( $this, 'handle_delete_group' ) );
    add_action( 'admin_post_acdc_interrupt_group', array( $this, 'handle_interrupt_group' ) );
    add_action( 'admin_post_acdc_save_funder', array( $this, 'handle_save_funder' ) );
    add_action( 'admin_post_acdc_delete_funder', array( $this, 'handle_delete_funder' ) );
    add_action( 'admin_post_acdc_save_trainer', array( $this, 'handle_save_trainer' ) );
    add_action( 'admin_post_acdc_delete_trainer', array( $this, 'handle_delete_trainer' ) );
    /* ACDC 3.22.1 — Contrats/missions formateurs. */
    add_action( 'admin_post_acdc_save_trainer_contract',         array( $this, 'handle_save_trainer_contract' ) );
    add_action( 'admin_post_acdc_delete_trainer_contract',       array( $this, 'handle_delete_trainer_contract' ) );
    add_action( 'admin_post_acdc_generate_trainer_contract_pdf', array( $this, 'handle_generate_trainer_contract_pdf' ) );
    /* ACDC 3.25.148 — G4 : envoi du contrat au formateur, action séparée du téléchargement. */
    add_action( 'admin_post_acdc_send_trainer_contract_email', array( $this, 'handle_send_trainer_contract_email' ) );
    /* ACDC 3.25.150 — F11 : consultation authentifiée des contrats (dossier /uploads interdit). */
    add_action( 'admin_post_acdc_serve_trainer_contract', array( $this, 'handle_serve_trainer_contract' ) );
    /* ACDC 3.25.157 — Archivage explicite d'un contrat signé : le seul chemin qui
       lève la protection de suppression posée en 3.25.155. */
    add_action( 'admin_post_acdc_archive_trainer_contract', array( $this, 'handle_archive_trainer_contract' ) );
    /* ACDC 3.25.155 — Inventaire et nettoyage MANUEL des contrats orphelins.
       ACDC 3.25.156 — L'écran a été déplacé dans l'extranet (Paramètres →
       Documents orphelins) : plus de page wp-admin, seule l'action subsiste. */
    add_action( 'admin_post_acdc_delete_orphan_contract_file', array( $this, 'handle_delete_orphan_contract_file' ) );
    /* ACDC 3.24.11 — Bilans compétences formateurs (ind. 21). */
    add_action( 'admin_post_acdc_save_trainer_evaluation',   array( $this, 'handle_save_trainer_evaluation' ) );
    add_action( 'admin_post_acdc_delete_trainer_evaluation', array( $this, 'handle_delete_trainer_evaluation' ) );
    /* ACDC 3.22.3 — Signature électronique contrat formateur. */
    add_action( 'admin_post_acdc_send_trainer_contract_signature', array( $this, 'handle_send_trainer_contract_signature' ) );
    add_action( 'acdc_sig_request_signed', array( $this, 'handle_trainer_contract_signed' ), 30, 2 );
    /* ACDC 3.20.82 — Module portail formateur. */
    add_action( 'admin_post_acdc_invite_trainer', array( $this, 'handle_invite_trainer' ) );
    /* ACDC 3.20.93 — Bouton « Tester l'envoi » des alertes Qualiopi sur la fiche formateur admin. */
    add_action( 'admin_post_acdc_qualiopi_test_alert', array( $this, 'handle_qualiopi_test_alert' ) );
    /* ACDC 3.20.86 — Bibliothèque personnelle du formateur (côté admin). */
    add_action( 'admin_post_acdc_upload_trainer_document', array( $this, 'handle_admin_upload_trainer_document' ) );
    add_action( 'admin_post_acdc_delete_trainer_document', array( $this, 'handle_admin_delete_trainer_document' ) );
    add_action( 'admin_post_acdc_download_trainer_document', array( $this, 'handle_admin_download_trainer_document' ) );
    /* ACDC 3.20.94 — Édition d'un document existant (catégorie, libellé, dates, Qualiopi, notes admin). */
    add_action( 'admin_post_acdc_update_trainer_document', array( $this, 'handle_admin_update_trainer_document' ) );
    add_action( 'admin_post_acdc_save_portal_user', array( $this, 'handle_save_portal_user' ) );
    add_action( 'admin_post_acdc_save_portal_user_color', array( $this, 'handle_save_portal_user_color' ) );
    add_action( 'admin_post_acdc_delete_portal_user', array( $this, 'handle_delete_portal_user' ) );
    add_action( 'admin_post_acdc_agent_audit_save_settings', array( $this, 'handle_agent_audit_save_settings' ) );
    add_action( 'admin_post_acdc_admin_learner_portal_sync', array( $this, 'handle_admin_learner_portal_sync' ) );
    add_action( 'admin_post_acdc_admin_learner_portal_status', array( $this, 'handle_admin_learner_portal_status' ) );
    add_action( 'admin_post_acdc_agent_audit_create_user', array( $this, 'handle_agent_audit_create_user' ) );
    add_action( 'admin_post_acdc_agent_audit_delete_user', array( $this, 'handle_agent_audit_delete_user' ) );
    add_action( 'admin_post_acdc_purge_plugin_data', array( $this, 'handle_purge_plugin_data' ) );
    add_action( 'admin_post_acdc_create_manual_backup', array( $this, 'handle_create_manual_backup' ) );
    add_action( 'admin_post_acdc_download_backup', array( $this, 'handle_download_backup' ) );
    add_action( 'admin_post_acdc_restore_backup_import', array( $this, 'handle_restore_backup_import' ) );
    add_action( 'admin_post_acdc_save_branding', array( $this, 'handle_save_branding' ) );
    add_action( 'admin_post_acdc_export_branding', array( $this, 'handle_export_branding' ) );
    add_action( 'admin_post_acdc_restore_branding_history', array( $this, 'handle_restore_branding_history' ) );
    add_action( 'admin_post_acdc_save_company_profile', array( $this, 'handle_save_company_profile' ) );
    add_action( 'admin_post_acdc_save_contract_params', array( $this, 'handle_save_contract_params' ) );
    add_action( 'admin_post_acdc_save_convocation_params', array( $this, 'handle_save_convocation_params' ) );
    add_action( 'admin_post_acdc_save_subcontract_params', array( $this, 'handle_save_subcontract_params' ) );
    add_action( 'admin_post_acdc_save_attestation_params', array( $this, 'handle_save_attestation_params' ) );
    add_action( 'admin_post_acdc_save_billing_settings', array( $this, 'handle_save_billing_settings' ) );
    add_action( 'admin_post_acdc_save_catalog_settings', array( $this, 'handle_save_catalog_settings' ) );
    add_action( 'admin_post_acdc_set_catalog_order', array( $this, 'handle_set_catalog_order' ) );
    add_action( 'admin_post_acdc_remove_from_catalog', array( $this, 'handle_remove_from_catalog' ) );
    add_action( 'admin_post_nopriv_acdc_catalog_register', array( $this, 'handle_catalog_register' ) );
    add_action( 'admin_post_acdc_catalog_register', array( $this, 'handle_catalog_register' ) );
    add_action( 'admin_post_acdc_generate_bpf_prefilled', array( $this, 'handle_generate_bpf_prefilled' ) );
    add_action( 'admin_post_acdc_import_bpf', array( $this, 'handle_import_bpf' ) );
    /* ACDC 3.21.75 — Prestations extérieures */
    add_action( 'admin_post_acdc_save_external_mission',          array( $this, 'handle_save_external_mission' ) );
    add_action( 'admin_post_acdc_delete_external_mission',        array( $this, 'handle_delete_external_mission' ) );
    add_action( 'admin_post_acdc_import_external_missions_csv',   array( $this, 'handle_import_external_missions_csv' ) );
    add_action( 'init', array( $this, 'maybe_import_notion_external_missions' ), 20 );
    add_action( 'init', array( $this, 'maybe_clean_bpf_andrea_titles' ), 21 );
    add_action( 'init', array( $this, 'maybe_fix_bpf_accounting_dates' ), 22 );
    add_action( 'admin_post_acdc_save_learner_deadline', array( $this, 'handle_save_learner_deadline' ) );
    add_action( 'admin_post_acdc_save_ancillary_service', array( $this, 'handle_save_ancillary_service' ) );
    add_action( 'admin_post_acdc_save_continuous_improvement', array( $this, 'handle_save_continuous_improvement' ) );
    /* ACDC 3.24.6 — Conseil de perfectionnement (ind. 20) */
    add_action( 'admin_post_acdc_save_perfectionnement_member',   array( $this, 'handle_save_perfectionnement_member' ) );
    add_action( 'admin_post_acdc_delete_perfectionnement_member', array( $this, 'handle_delete_perfectionnement_member' ) );
    add_action( 'admin_post_acdc_save_perfectionnement_meeting',   array( $this, 'handle_save_perfectionnement_meeting' ) );
    add_action( 'admin_post_acdc_delete_perfectionnement_meeting', array( $this, 'handle_delete_perfectionnement_meeting' ) );
    /* ACDC 3.24.9 — Partenaires PSH (ind. 26) */
    add_action( 'admin_post_acdc_save_psh_partner',   array( $this, 'handle_save_psh_partner' ) );
    add_action( 'admin_post_acdc_delete_psh_partner', array( $this, 'handle_delete_psh_partner' ) );
    /* ACDC 3.24.13 — R-17 : Locaux & équipements (indicateur 17 Qualiopi). */
    add_action( 'admin_post_acdc_save_training_site',   array( $this, 'handle_save_training_site' ) );
    add_action( 'admin_post_acdc_delete_training_site', array( $this, 'handle_delete_training_site' ) );
    /* ACDC 3.24.14 — R-28 : Sous-traitants formels (indicateur 28 Qualiopi). */
    add_action( 'admin_post_acdc_save_subcontractor',   array( $this, 'handle_save_subcontractor' ) );
    add_action( 'admin_post_acdc_delete_subcontractor', array( $this, 'handle_delete_subcontractor' ) );
    add_action( 'init', array( $this, 'maybe_import_psh_default_partners' ), 25 );
    add_action( 'admin_post_acdc_generate_contract_pdf', array( $this, 'handle_generate_contract_pdf' ) );
    add_action( 'admin_post_acdc_generate_nad_apprenant_pdf', array( $this, 'handle_generate_nad_apprenant_pdf' ) );
    add_action( 'admin_post_acdc_delete_training_file', array( $this, 'handle_delete_training_file' ) );
    add_action( 'admin_post_acdc_download_registration_contract_document', array( $this, 'handle_download_registration_contract_document' ) );
    add_action( 'admin_post_acdc_update_registration_contract_document', array( $this, 'handle_update_registration_contract_document' ) );
    add_action( 'admin_post_acdc_download_training_convocation_document', array( $this, 'handle_download_training_convocation_document' ) );
    add_action( 'admin_post_acdc_update_training_convocation_document', array( $this, 'handle_update_training_convocation_document' ) );
    add_action( 'admin_post_acdc_download_positioning_result_document', array( $this, 'handle_download_positioning_result_document' ) );
    add_action( 'admin_post_acdc_update_positioning_result_document', array( $this, 'handle_update_positioning_result_document' ) );
    add_action( 'admin_post_acdc_export_positioning_qcm_details', array( $this, 'handle_export_positioning_qcm_details' ) );
    add_action( 'admin_post_acdc_download_mid_survey_document', array( $this, 'handle_download_mid_survey_document' ) );
    add_action( 'admin_post_acdc_update_mid_survey_document', array( $this, 'handle_update_mid_survey_document' ) );
    add_action( 'admin_post_acdc_download_hot_survey_document', array( $this, 'handle_download_hot_survey_document' ) );
    add_action( 'admin_post_acdc_update_hot_survey_document', array( $this, 'handle_update_hot_survey_document' ) );
    add_action( 'admin_post_acdc_export_hot_survey_qcm_details', array( $this, 'handle_export_hot_survey_qcm_details' ) );
    add_action( 'admin_post_acdc_download_cold_survey_document', array( $this, 'handle_download_cold_survey_document' ) );
    add_action( 'admin_post_acdc_update_cold_survey_document', array( $this, 'handle_update_cold_survey_document' ) );
    add_action( 'admin_post_acdc_export_cold_survey_qcm_details', array( $this, 'handle_export_cold_survey_qcm_details' ) );
    add_action( 'acdc_of_learner_portal_cron_maintenance', array( $this, 'handle_learner_portal_cron_maintenance' ) );
    add_action( 'admin_post_acdc_download_evaluation_result_document', array( $this, 'handle_download_evaluation_result_document' ) );
    add_action( 'admin_post_acdc_update_evaluation_result_document', array( $this, 'handle_update_evaluation_result_document' ) );
    add_action( 'admin_post_acdc_export_evaluation_qcm_details', array( $this, 'handle_export_evaluation_qcm_details' ) );
    add_action( 'admin_post_acdc_download_completion_certificate_document', array( $this, 'handle_download_completion_certificate_document' ) );
    add_action( 'admin_post_acdc_update_completion_certificate_document', array( $this, 'handle_update_completion_certificate_document' ) );
    add_action( 'admin_post_acdc_download_end_training_certificate_document', array( $this, 'handle_download_end_training_certificate_document' ) );
    add_action( 'admin_post_acdc_update_end_training_certificate_document', array( $this, 'handle_update_end_training_certificate_document' ) );
    add_action( 'admin_post_acdc_export_end_training_certificate_qcm_details', array( $this, 'handle_export_end_training_certificate_qcm_details' ) );
    add_action( 'admin_post_acdc_create_blank_attendance_sheet', array( $this, 'handle_create_blank_attendance_sheet' ) );
    add_action( 'admin_post_acdc_save_quiz', array( $this, 'handle_save_quiz' ) );
    add_action( 'admin_post_acdc_delete_quiz', array( $this, 'handle_delete_quiz' ) );
    add_action( 'admin_post_acdc_send_quiz', array( $this, 'handle_send_quiz' ) );
    add_action( 'admin_post_acdc_save_positioning_test', array( $this, 'handle_save_positioning_test' ) );
    add_action( 'admin_post_acdc_delete_positioning_test', array( $this, 'handle_delete_positioning_test' ) );
    add_action( 'admin_post_acdc_save_evaluation', array( $this, 'handle_save_evaluation' ) );
    add_action( 'admin_post_acdc_delete_evaluation', array( $this, 'handle_delete_evaluation' ) );
    add_action( 'admin_post_acdc_save_need_analysis', array( $this, 'handle_save_need_analysis' ) );
    add_action( 'admin_post_acdc_delete_need_analysis', array( $this, 'handle_delete_need_analysis' ) );
    add_action( 'admin_post_acdc_create_need_analysis_from_model', array( $this, 'handle_create_need_analysis_from_model' ) );
    /* ACDC 3.21.17 — Cron envoi/relance analyses du besoin. */
    add_action( 'admin_post_acdc_nad_manual_relance',        array( $this, 'handle_nad_manual_relance' ) );
    add_action( 'admin_post_nopriv_acdc_nad_manual_relance', array( $this, 'handle_nad_manual_relance' ) );
    // ACDC 3.21.29-hotfix4 — Renvoi manuel depuis la liste principale.
    add_action( 'admin_post_acdc_nad_resend',        array( $this, 'handle_nad_resend' ) );
    add_action( 'admin_post_nopriv_acdc_nad_resend', array( $this, 'handle_nad_resend' ) );

    /* ACDC 3.21.17 — Cron envoi/relance analyses du besoin — hook d'exécution uniquement. */
    add_action( 'acdc_nad_cron_send_and_relance', array( $this, 'cron_nad_send_and_relance' ) );

    /* ACDC 3.21.58 — Cron nuit push indicateurs SAAS → Manager — hook d'exécution uniquement. */
    add_action( 'acdc_of_cron_push_indicators', array( $this, 'cron_push_all_indicators' ) );

    /* ACDC 3.24.21 — Cron nuit 3h sync formations Manager → SAAS — hook d'exécution uniquement. */
    add_action( 'acdc_of_cron_sync_formations', array( $this, 'cron_sync_formations_from_manager' ) );

    /* ACDC 3.24.21 — Planification propre des crons sur hook 'wp' (jamais dans __construct). */
    add_action( 'wp', array( $this, 'register_cron_events' ) );

    /* ACDC 3.21.15 — CRUD thématiques de formation. */
    add_action( 'admin_post_acdc_save_thematique',   array( $this, 'handle_save_thematique' ) );
    add_action( 'admin_post_acdc_delete_thematique', array( $this, 'handle_delete_thematique' ) );

    /* ACDC 3.21.10 — CRUD bibliothèque blocs et questions. */
    add_action( 'admin_post_acdc_save_need_block',      array( $this, 'handle_save_need_block' ) );
    add_action( 'admin_post_acdc_delete_need_block',    array( $this, 'handle_delete_need_block' ) );
    add_action( 'admin_post_acdc_save_need_question',   array( $this, 'handle_save_need_question' ) );
    add_action( 'admin_post_acdc_toggle_need_question', array( $this, 'handle_toggle_need_question' ) );
    add_action( 'admin_post_acdc_delete_need_question', array( $this, 'handle_delete_need_question' ) );
    add_action( 'admin_post_acdc_save_registration_contract', array( $this, 'handle_save_registration_contract' ) );
    add_action( 'admin_post_acdc_delete_registration_contract', array( $this, 'handle_delete_registration_contract' ) );
    add_action( 'admin_post_acdc_send_contract_for_signature', array( $this, 'handle_send_contract_for_signature' ) );
    // ACDC 3.21.08 — Régénération convention avec signature manuscrite après e-signature
    add_action( 'acdc_sig_request_signed', array( $this, 'handle_contract_signed_by_learner' ), 10, 2 );
    /* ACDC 3.21.13 — Auto-création analyses du besoin après signature convention. */
    add_action( 'acdc_sig_request_signed', array( $this, 'handle_nad_auto_create_from_signature' ), 20, 2 );
    /* ACDC 3.25.157 — Un prospect ne devient commanditaire qu'à la SIGNATURE de sa
       convention : c'est la signature qui fait le client, pas l'ouverture d'un
       formulaire ni l'enregistrement d'un brouillon. */
    add_action( 'acdc_sig_request_signed', array( $this, 'handle_registration_contract_signed_company' ), 25, 2 );
    add_action( 'wp_ajax_acdc_nad_public_submit',        array( $this, 'handle_nad_public_submit' ) );
    add_action( 'wp_ajax_nopriv_acdc_nad_public_submit', array( $this, 'handle_nad_public_submit' ) );
    add_filter( 'template_include', array( $this, 'nad_maybe_override_template' ) );
    add_filter( 'template_include', array( $this, 'survey_maybe_override_template' ) );
    add_action( 'admin_post_acdc_save_training_registration', array( $this, 'handle_save_training_registration' ) );
    add_action( 'admin_post_acdc_bulk_enroll_from_contract',  array( $this, 'handle_bulk_enroll_from_contract' ) );
    /* ACDC 3.21.05-hotfix4 — Renvoi e-mail ouverture extranet depuis la fiche inscription */
    add_action( 'admin_post_acdc_resend_learner_extranet_email', array( $this, 'handle_resend_learner_extranet_email' ) );
    // ACDC 3.25.169 — Lien d'activation formateur affiché à l'écran, sans e-mail.
    add_action( 'admin_post_acdc_open_trainer_access', array( $this, 'handle_open_trainer_extranet_access' ) );
    add_action( 'admin_post_acdc_delete_training_registration', array( $this, 'handle_delete_training_registration' ) );
    add_action( 'admin_post_acdc_save_training_file_profile', array( $this, 'handle_save_training_file_profile' ) );
    /* ACDC 3.24.16 — R-15 : Sous-traitants par dossier (indicateur 15 Qualiopi). */
    add_action( 'admin_post_acdc_save_dossier_subcontractor',   array( $this, 'handle_save_dossier_subcontractor' ) );
    add_action( 'admin_post_acdc_delete_dossier_subcontractor', array( $this, 'handle_delete_dossier_subcontractor' ) );
    add_action( 'admin_post_acdc_save_mid_survey', array( $this, 'handle_save_mid_survey' ) );
    add_action( 'admin_post_acdc_delete_mid_survey', array( $this, 'handle_delete_mid_survey' ) );
    add_action( 'admin_post_acdc_create_mid_survey_from_model', array( $this, 'handle_create_mid_survey_from_model' ) );
    add_action( 'admin_post_acdc_save_hot_survey', array( $this, 'handle_save_hot_survey' ) );
    add_action( 'admin_post_acdc_delete_hot_survey', array( $this, 'handle_delete_hot_survey' ) );
    add_action( 'admin_post_acdc_create_hot_survey_from_model', array( $this, 'handle_create_hot_survey_from_model' ) );
    add_action( 'admin_post_acdc_save_cold_survey', array( $this, 'handle_save_cold_survey' ) );
    add_action( 'admin_post_acdc_delete_cold_survey', array( $this, 'handle_delete_cold_survey' ) );
    add_action( 'admin_post_acdc_create_cold_survey_from_model', array( $this, 'handle_create_cold_survey_from_model' ) );
    add_action( 'admin_post_acdc_save_trainer_survey', array( $this, 'handle_save_trainer_survey' ) );
    add_action( 'admin_post_acdc_delete_trainer_survey', array( $this, 'handle_delete_trainer_survey' ) );
    add_action( 'admin_post_acdc_create_trainer_survey_from_model', array( $this, 'handle_create_trainer_survey_from_model' ) );
    add_action( 'admin_post_acdc_save_company_survey', array( $this, 'handle_save_company_survey' ) );
    add_action( 'admin_post_acdc_delete_company_survey', array( $this, 'handle_delete_company_survey' ) );
    add_action( 'admin_post_acdc_create_company_survey_from_model', array( $this, 'handle_create_company_survey_from_model' ) );
    add_action( 'admin_post_acdc_save_funder_survey', array( $this, 'handle_save_funder_survey' ) );
    add_action( 'admin_post_acdc_delete_funder_survey', array( $this, 'handle_delete_funder_survey' ) );
    add_action( 'admin_post_acdc_create_funder_survey_from_model', array( $this, 'handle_create_funder_survey_from_model' ) );
    /* ACDC 3.25.147 — L'enregistrement des abilities MCP (catégorie + abilities)
       est armé le PLUS TÔT possible dans includes/class-acdc-mcp.php, au chargement
       du module, indépendamment de la classe et du serveur MCP (voir ce fichier).
       Le seul hook du core est wp_abilities_api_init (+ wp_abilities_api_categories_init) :
       le hook fantôme « abilities_api_init » a été retiré. */
    /* Provisionne les rôles/capacités MCP au boot si la version diffère (sans réactivation). */
    add_action( 'init', array( $this, 'acdc_mcp_maybe_setup_roles' ) );
    /* Page de diagnostic Réglages → ACDC MCP (admins uniquement). */
    add_action( 'admin_menu', array( $this, 'register_mcp_admin_page' ) );

    add_action( 'admin_post_acdc_download_quote_document', array( $this, 'handle_download_quote_document' ) );
    add_action( 'admin_post_acdc_download_quote_pdf',      array( $this, 'handle_download_quote_pdf' ) );
    add_action( 'admin_post_acdc_save_quote',             array( $this, 'handle_save_quote' ) );
    add_action( 'admin_post_acdc_send_quote_email',       array( $this, 'handle_send_quote_email' ) );
    add_action( 'admin_post_acdc_delete_quote',           array( $this, 'handle_delete_quote' ) );
    add_action( 'admin_post_acdc_set_quote_status',       array( $this, 'handle_set_quote_status' ) );
    add_action( 'admin_post_acdc_download_invoice_document', array( $this, 'handle_download_invoice_document' ) );
    add_action( 'admin_post_acdc_download_invoice_facturx',  array( $this, 'handle_download_invoice_facturx' ) );
    add_action( 'admin_post_acdc_download_invoice_facturx_pdf', array( $this, 'handle_download_invoice_facturx_pdf' ) );
    /* ACDC 3.24.20 — Facturation réelle : convert devis→facture, save, delete. */
    add_action( 'admin_post_acdc_convert_quote_to_invoice', array( $this, 'handle_convert_quote_to_invoice' ) );
    add_action( 'admin_post_acdc_send_quote_for_signature', array( $this, 'handle_send_quote_for_signature' ) );
    add_action( 'acdc_sig_request_signed', array( $this, 'handle_quote_signed' ), 20, 2 );
    // ACDC 3.25.135 — Preuve d'horodatage scellé enregistrée après signature (non intrusif).
    add_action( 'acdc_sig_request_signed', array( $this, 'handle_sig_timestamp_proof' ), 30, 2 );
    add_action( 'admin_post_acdc_save_invoice',             array( $this, 'handle_save_invoice' ) );
    add_action( 'admin_post_acdc_delete_invoice',           array( $this, 'handle_delete_invoice' ) );
    // ACDC 3.25.116 — Facturation réelle : marquer payée + envoyer par email.
    add_action( 'admin_post_acdc_mark_invoice_paid',        array( $this, 'handle_mark_invoice_paid' ) );
    add_action( 'admin_post_acdc_send_invoice_email',       array( $this, 'handle_send_invoice_email' ) );
    // ACDC 3.25.133 — Export comptable CSV de toutes les factures (comptable / Tiime).
    add_action( 'admin_post_acdc_export_accounting_csv',    array( $this, 'handle_export_accounting_csv' ) );
    add_action( 'admin_post_acdc_download_credit_note_document', array( $this, 'handle_download_credit_note_document' ) );
    // ACDC 3.25.118 — Aval facturation : relance impayés + émission d'avoir persistant.
    add_action( 'admin_post_acdc_relance_invoice',          array( $this, 'handle_relance_invoice' ) );
    add_action( 'admin_post_acdc_emit_credit_note',         array( $this, 'handle_emit_credit_note' ) );
    add_action( 'admin_post_acdc_marketing_save_entity', array( $this, 'handle_marketing_save_entity' ) );
    add_action( 'admin_post_acdc_marketing_delete_entity', array( $this, 'handle_marketing_delete_entity' ) );
    add_action( 'admin_post_acdc_marketing_save_settings', array( $this, 'handle_marketing_save_settings' ) );
    add_action( 'admin_post_acdc_marketing_toggle_contact', array( $this, 'handle_marketing_toggle_contact' ) );
    add_action( 'admin_post_acdc_marketing_save_contact', array( $this, 'handle_marketing_save_contact' ) );
    add_action( 'admin_post_acdc_marketing_import_contacts', array( $this, 'handle_marketing_import_contacts' ) );
    add_action( 'admin_post_acdc_marketing_resend_archived_email', array( $this, 'handle_marketing_resend_archived_email' ) );
    /* ACDC 3.24.40 - Signatures e-mail */
    add_action( 'admin_post_acdc_marketing_save_signature',   array( $this, 'handle_marketing_save_signature' ) );
    add_action( 'admin_post_acdc_marketing_delete_signature', array( $this, 'handle_marketing_delete_signature' ) );
    /* ACDC 3.24.32 — Signatures e-mail */
    add_action( 'admin_post_acdc_marketing_save_signature',   array( $this, 'handle_marketing_save_signature' ) );
    add_action( 'admin_post_acdc_marketing_delete_signature', array( $this, 'handle_marketing_delete_signature' ) );
    add_action( 'admin_post_nopriv_acdc_marketing_public_submit', array( $this, 'handle_marketing_public_submit' ) );
    add_action( 'admin_post_acdc_marketing_public_submit', array( $this, 'handle_marketing_public_submit' ) );
    add_action( 'admin_post_acdc_save_questionnaire_session', array( $this, 'handle_save_questionnaire_session' ) );
    add_action( 'admin_post_acdc_delete_questionnaire_session', array( $this, 'handle_delete_questionnaire_session' ) );
    add_action( 'admin_post_acdc_duplicate_questionnaire_session', array( $this, 'handle_duplicate_questionnaire_session' ) );
    add_action( 'admin_post_acdc_start_questionnaire_session', array( $this, 'handle_start_questionnaire_session' ) );
    add_action( 'admin_post_acdc_pause_questionnaire_session', array( $this, 'handle_pause_questionnaire_session' ) );
    add_action( 'admin_post_acdc_next_questionnaire_session_question', array( $this, 'handle_next_questionnaire_session_question' ) );
    add_action( 'admin_post_acdc_previous_questionnaire_session_question', array( $this, 'handle_previous_questionnaire_session_question' ) );
    add_action( 'admin_post_acdc_finish_questionnaire_session', array( $this, 'handle_finish_questionnaire_session' ) );
    add_action( 'admin_post_acdc_export_questionnaire_session_results_csv', array( $this, 'export_questionnaire_session_results_csv' ) );
    add_action( 'admin_post_acdc_send_questionnaire_session_emails', array( $this, 'handle_send_questionnaire_session_emails' ) );
    add_action( 'admin_post_nopriv_acdc_join_questionnaire_session', array( $this, 'handle_join_questionnaire_session' ) );
    add_action( 'admin_post_acdc_join_questionnaire_session', array( $this, 'handle_join_questionnaire_session' ) );
    add_action( 'admin_post_nopriv_acdc_submit_questionnaire_answer', array( $this, 'handle_submit_questionnaire_answer' ) );
    add_action( 'admin_post_acdc_submit_questionnaire_answer', array( $this, 'handle_submit_questionnaire_answer' ) );
    add_action( 'admin_post_acdc_save_questionnaire_settings', array( $this, 'handle_save_questionnaire_settings' ) );
    add_action( 'admin_post_acdc_save_survey_engine_settings', array( $this, 'handle_save_survey_engine_settings' ) );
    add_action( 'admin_post_acdc_export_questionnaire_session_results_excel', array( $this, 'export_questionnaire_session_results_excel' ) );
    add_action( 'admin_post_acdc_export_questionnaire_session_results_pdf', array( $this, 'export_questionnaire_session_results_pdf' ) );
    add_action( 'admin_post_acdc_export_questionnaire_context_results_csv', array( $this, 'export_questionnaire_context_results_csv' ) );
    add_action( 'admin_post_acdc_export_questionnaire_context_results_excel', array( $this, 'export_questionnaire_context_results_excel' ) );
    add_action( 'admin_post_acdc_export_questionnaire_context_results_pdf', array( $this, 'export_questionnaire_context_results_pdf' ) );
    add_action( 'admin_post_acdc_export_improvement_pilotage_csv', array( $this, 'export_improvement_pilotage_csv' ) );
    add_action( 'admin_post_acdc_export_improvement_pilotage_excel', array( $this, 'export_improvement_pilotage_excel' ) );
    add_action( 'admin_post_acdc_send_questionnaire_session_reminder', array( $this, 'handle_send_questionnaire_session_reminder' ) );
    add_action( 'admin_post_acdc_send_questionnaire_session_bulk_reminders', array( $this, 'handle_send_questionnaire_session_bulk_reminders' ) );
    add_action( 'admin_post_acdc_reopen_questionnaire_participant', array( $this, 'handle_reopen_questionnaire_participant' ) );
    add_action( 'admin_post_acdc_create_questionnaire_action', array( $this, 'handle_create_questionnaire_action' ) );
    add_action( 'admin_post_acdc_update_questionnaire_action', array( $this, 'handle_update_questionnaire_action' ) );
    add_action( 'admin_post_acdc_save_quality_gap_record', array( $this, 'handle_save_quality_gap_record' ) );
    add_action( 'admin_post_acdc_delete_quality_gap_record', array( $this, 'handle_delete_quality_gap_record' ) );
    add_action( 'admin_post_acdc_save_quality_action_record', array( $this, 'handle_save_quality_action_record' ) );
    add_action( 'admin_post_acdc_delete_quality_action_record', array( $this, 'handle_delete_quality_action_record' ) );
    add_action( 'admin_post_acdc_handle_quality_loop_transition', array( $this, 'handle_quality_loop_transition' ) );
    add_action( 'admin_post_acdc_export_quality_gap_register_csv', array( $this, 'export_quality_gap_register_csv' ) );
    add_action( 'admin_post_acdc_export_quality_gap_register_excel', array( $this, 'export_quality_gap_register_excel' ) );
    add_action( 'admin_post_acdc_export_quality_action_plan_csv', array( $this, 'export_quality_action_plan_csv' ) );
    add_action( 'admin_post_acdc_export_quality_action_plan_excel', array( $this, 'export_quality_action_plan_excel' ) );
    add_action( 'admin_post_acdc_export_quality_audit_log_csv', array( $this, 'export_quality_audit_log_csv' ) );
    add_action( 'admin_post_acdc_export_quality_audit_log_excel', array( $this, 'export_quality_audit_log_excel' ) );
    /* ACDC 3.22.18 — H4/H5 : Registre des réclamations. */
    add_action( 'admin_post_acdc_save_complaint', array( $this, 'handle_save_complaint' ) );
    add_action( 'admin_post_acdc_delete_complaint', array( $this, 'handle_delete_complaint' ) );
    add_action( 'admin_post_acdc_acknowledge_complaint', array( $this, 'handle_acknowledge_complaint' ) );
    add_action( 'admin_post_acdc_close_complaint', array( $this, 'handle_close_complaint' ) );
    add_action( 'admin_post_acdc_delete_improvement', array( $this, 'handle_delete_improvement' ) );
    add_action( 'admin_post_acdc_mark_improvement_done', array( $this, 'handle_mark_improvement_done' ) );
    add_action( 'admin_post_acdc_export_complaints_pdf', array( $this, 'handle_export_complaints_pdf' ) );
    add_action( 'admin_post_nopriv_acdc_submit_public_questionnaire_session', array( $this, 'handle_submit_public_questionnaire_session' ) );
    add_action( 'admin_post_acdc_submit_public_questionnaire_session', array( $this, 'handle_submit_public_questionnaire_session' ) );
    add_action( 'acdc_of_surveys_cron_dispatches', array( $this, 'process_scheduled_survey_dispatches' ) );
    add_action( 'acdc_of_surveys_cron_reminders', array( $this, 'process_scheduled_survey_reminders' ) );
    add_action( 'acdc_of_surveys_cron_expirations', array( $this, 'process_expired_survey_sessions' ) );
    add_action( 'acdc_of_surveys_cron_action_followups', array( $this, 'process_questionnaire_action_internal_followups' ) );
    add_action( 'init', array( $this, 'maybe_process_questionnaire_action_internal_followups' ), 20 );
    /* ACDC 3.20.93 — Cron quotidien des alertes Qualiopi (URSSAF, RC pro, justificatifs Qualiopi). */
    add_action( 'acdc_of_qualiopi_alerts_cron', array( $this, 'process_qualiopi_expiration_alerts' ) );

    /* ACDC 3.21.05 — Cron quotidien de clôture automatique des sessions OF terminées. */
    add_action( 'acdc_of_session_close_cron', array( $this, 'process_of_session_auto_close' ) );
    add_action( 'acdc_of_convocation_cron',   array( $this, 'process_convocation_cron' ) );
    add_action( 'acdc_of_positioning_test_cron', array( $this, 'process_positioning_test_cron' ) );
    /* ACDC 3.24.12 — R-12 : Cron alerte absences / ruptures de parcours (indicateur 12 Qualiopi). */
    add_action( 'acdc_of_absence_alert_cron', array( $this, 'process_absence_alert_cron' ) );
    // ACDC 3.25.118 — Cron quotidien : passage automatique des factures impayées en retard.
    add_action( 'acdc_of_invoices_overdue_cron', array( $this, 'process_invoices_overdue_cron' ) );
    // ACDC 3.25.130 — Cron quotidien : rapport de rétention RGPD (lecture seule, non destructif).
    add_action( 'acdc_of_retention_scan_cron', array( $this, 'cron_retention_scan' ) );
    // ACDC 3.25.132 — Encart tableau de bord : rapport de rétention RGPD (admins).
    add_action( 'wp_dashboard_setup', array( $this, 'register_retention_dashboard_widget' ) );
    // ACDC 3.25.134 — Encart tableau de bord : chiffre d'affaires (net d'avoirs).
    add_action( 'wp_dashboard_setup', array( $this, 'register_ca_dashboard_widget' ) );
    /* ACDC 3.23.11 — Crons veille IA. */
    add_action( 'acdc_of_watch_collect_cron',  array( $this, 'process_watch_collect_cron' ) );
    add_action( 'acdc_of_watch_analyze_cron',  array( $this, 'process_watch_analyze_cron' ) );
    add_action( 'acdc_of_watch_digest_cron',   array( $this, 'process_watch_digest_cron' ) );
    add_action( 'acdc_of_watch_reminder_cron', array( $this, 'process_watch_reminder_cron' ) );
    $this->register_watch_module_hooks();
    add_shortcode( 'acdc_questionnaire_public', array( $this, 'render_questionnaire_public_shortcode' ) );

    /* ACDC 3.21.00 — Initialisation du moteur Quizzes (mode parallèle au legacy). */
    $this->init_quizzes_module_tables();
    add_action( 'init', array( $this, 'maybe_run_qz_db_upgrade' ), 9 );
    /* ACDC 3.25.177 — Les deux reprises de données passent de « init » à
       « admin_init ». Accrochées à « init », elles s'exécutaient pour CHAQUE visiteur,
       page publique comprise : c'est ce qui a mis le site à genoux en 3.25.176. Une
       migration se fait dans l'administration, par lots bornés, jamais sur le chemin
       d'une page servie au public. */
    add_action( 'admin_init', array( $this, 'maybe_run_qz_score_ratio_backfill' ) );
    add_action( 'admin_init', array( $this, 'acdc_backfill_survey_session_targets' ) );
    add_action( 'init', array( $this, 'ensure_quiz_async_public_page' ), 11 );
    // hotfix52 — Migration rétroactive PDF positionnement / évaluation
    add_action( 'init', array( $this, 'maybe_run_qz_retroactive_pdf_migration' ), 12 );
    add_action( 'init', array( $this, 'ensure_quiz_live_public_page' ), 11 );
    add_action( 'init', array( $this, 'ensure_quiz_live_host_page' ), 11 );
    add_action( 'init', array( $this, 'register_qz_live_shortcodes' ), 5 );
    add_action( 'init', array( $this, 'ensure_quiz_cron_events' ), 12 );
    $this->register_quizzes_module_hooks();
    $this->register_proposal_hooks();
  }


  /**
   * Prépare la configuration JavaScript du noyau UI.
   * Les largeurs sont stockées en option WordPress afin d'être communes à tous les utilisateurs.
   */
  public function localize_ui_kernel_settings() {
    if ( ! wp_script_is( 'acdc-of-ui-kernel', 'enqueued' ) ) {
      return;
    }

    $widths = get_option( 'acdc_of_global_column_widths', array() );
    if ( ! is_array( $widths ) ) {
      $widths = array();
    }

    /* ACDC 3.20.66 — Nettoyage automatique des sauvegardes polluées par
     * l'ancien bug array_merge. Si un tableau-clé contient plus d'entrées
     * que de colonnes raisonnablement attendues, on garde uniquement les
     * dernières valeurs (qui correspondent au réglage le plus récent), car
     * le bug ajoutait en queue de tableau à chaque sauvegarde successive.
     * On réécrit ensuite l'option nettoyée en base pour purger durablement.
     */
    $widths_changed = false;
    foreach ( $widths as $table_key => $columns ) {
      if ( ! is_array( $columns ) ) {
        continue;
      }
      $count = count( $columns );
      if ( $count <= 30 ) {
        continue;
      }
      /* Heuristique : on cherche le motif répétitif. Si le bug a ajouté
       * N cycles de C colonnes, le total est un multiple de C. On essaie
       * des tailles de cycle plausibles (5 à 24 colonnes) et on retient
       * la première taille qui divise exactement le total et dont les
       * cycles sont identiques. À défaut, on prend les 24 dernières
       * valeurs comme repli sécuritaire.
       */
      $values = array_values( $columns );
      $detected_cycle = 0;
      for ( $cycle = 5; $cycle <= 24; $cycle++ ) {
        if ( 0 !== $count % $cycle ) {
          continue;
        }
        $is_cyclic = true;
        for ( $i = $cycle; $i < $count; $i++ ) {
          if ( $values[ $i ] !== $values[ $i % $cycle ] ) {
            $is_cyclic = false;
            break;
          }
        }
        if ( $is_cyclic ) {
          $detected_cycle = $cycle;
          break;
        }
      }
      if ( $detected_cycle > 0 ) {
        /* On prend le dernier cycle complet (= les valeurs les plus récentes). */
        $last_cycle_values = array_slice( $values, -$detected_cycle );
      } else {
        /* Repli : on garde les 24 dernières valeurs. */
        $last_cycle_values = array_slice( $values, -24 );
      }
      $cleaned = array();
      foreach ( $last_cycle_values as $i => $v ) {
        $cleaned[ (string) $i ] = absint( $v );
      }
      $widths[ $table_key ] = $cleaned;
      $widths_changed = true;
    }
    if ( $widths_changed ) {
      update_option( 'acdc_of_global_column_widths', $widths, false );
    }

    /* ACDC 3.20.67 — Lecture des verrous de tableau (cadenas par tableau métier). */
    $locks = get_option( 'acdc_of_table_locks', array() );
    if ( ! is_array( $locks ) ) {
      $locks = array();
    }

    wp_localize_script(
      'acdc-of-ui-kernel',
      'AcdcUiKernelSettings',
      array(
        'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
        'nonce'         => wp_create_nonce( 'acdc_global_column_widths' ),
        'lockNonce'     => wp_create_nonce( 'acdc_table_locks' ),
        'columnWidths'  => $widths,
        'tableLocks'    => $locks,
        'canSaveGlobal' => is_user_logged_in(),
      )
    );
  }

  /**
   * Sauvegarde AJAX d'une largeur de colonne globale.
   * Réservé aux utilisateurs connectés afin de permettre une sauvegarde globale depuis le front office.
   */
  public function ajax_save_global_column_width() {
    if ( ! is_user_logged_in() ) {
      wp_send_json_error( array( 'message' => 'Accès refusé.' ), 403 );
    }

    check_ajax_referer( 'acdc_global_column_widths', 'nonce' );

    $table_key = isset( $_POST['table_key'] ) ? sanitize_text_field( wp_unslash( $_POST['table_key'] ) ) : '';
    $column_index = isset( $_POST['column_index'] ) ? absint( $_POST['column_index'] ) : 0;
    $width = isset( $_POST['width'] ) ? absint( $_POST['width'] ) : 0;

    $table_key = preg_replace( '/[^a-zA-Z0-9_\-:.]/', '', $table_key );
    if ( '' === $table_key || strlen( $table_key ) > 220 ) {
      wp_send_json_error( array( 'message' => 'Identifiant de tableau invalide.' ), 400 );
    }

    if ( $width < 56 ) {
      $width = 56;
    }
    if ( $width > 600 ) {
      $width = 600;
    }

    $widths = get_option( 'acdc_of_global_column_widths', array() );
    if ( ! is_array( $widths ) ) {
      $widths = array();
    }
    if ( ! isset( $widths[ $table_key ] ) || ! is_array( $widths[ $table_key ] ) ) {
      $widths[ $table_key ] = array();
    }

    $widths[ $table_key ][ (string) $column_index ] = $width;

    update_option( 'acdc_of_global_column_widths', $widths, false );

    wp_send_json_success(
      array(
        'table_key'    => $table_key,
        'column_index' => $column_index,
        'width'        => $width,
      )
    );
  }

  public function ajax_save_global_column_widths() {
    if ( ! is_user_logged_in() ) {
      wp_send_json_error( array( 'message' => 'Accès refusé.' ), 403 );
    }

    check_ajax_referer( 'acdc_global_column_widths', 'nonce' );

    $table_key = isset( $_POST['table_key'] ) ? sanitize_text_field( wp_unslash( $_POST['table_key'] ) ) : '';
    $table_key = preg_replace( '/[^a-zA-Z0-9_\-:.]/', '', $table_key );

    if ( '' === $table_key || strlen( $table_key ) > 220 ) {
      wp_send_json_error( array( 'message' => 'Identifiant de tableau invalide.' ), 400 );
    }

    $raw_widths = isset( $_POST['widths'] ) ? wp_unslash( $_POST['widths'] ) : '';
    $decoded_widths = json_decode( $raw_widths, true );

    if ( ! is_array( $decoded_widths ) ) {
      wp_send_json_error( array( 'message' => 'Largeurs invalides.' ), 400 );
    }

    $clean_widths = array();

    foreach ( $decoded_widths as $index => $width ) {
      $column_index = absint( $index );
      $column_width = absint( $width );

      if ( $column_width < 56 ) {
        $column_width = 56;
      }
      if ( $column_width > 600 ) {
        $column_width = 600;
      }

      $clean_widths[ (string) $column_index ] = $column_width;
    }

    if ( empty( $clean_widths ) ) {
      wp_send_json_error( array( 'message' => 'Aucune largeur à enregistrer.' ), 400 );
    }

    $widths = get_option( 'acdc_of_global_column_widths', array() );
    if ( ! is_array( $widths ) ) {
      $widths = array();
    }
    if ( ! isset( $widths[ $table_key ] ) || ! is_array( $widths[ $table_key ] ) ) {
      $widths[ $table_key ] = array();
    }

    /* ACDC 3.20.66 — Correction du bug d'accumulation des largeurs.
     * Avant : $widths[ $table_key ] = array_merge( $widths[ $table_key ], $clean_widths );
     * array_merge() en PHP, sur des tableaux à clés numériques (même castées en
     * string), renumérote en cascade au lieu de remplacer par clé. Résultat :
     * à chaque sauvegarde, le tableau s'allongeait au lieu d'écraser les
     * anciennes valeurs. Après 8 séances de redimensionnement sur un tableau
     * de 12 colonnes, on arrivait à 96 entrées polluées et la lecture renvoyait
     * des largeurs aléatoires.
     * Correction : écriture explicite clé par clé qui garantit le remplacement.
     */
    foreach ( $clean_widths as $column_index => $column_width ) {
      $widths[ $table_key ][ (string) $column_index ] = $column_width;
    }

    update_option( 'acdc_of_global_column_widths', $widths, false );

    wp_send_json_success(
      array(
        'table_key' => $table_key,
        'widths'    => $clean_widths,
      )
    );
  }

  /**
   * ACDC 3.20.67 — Sauvegarde AJAX de l'état de verrouillage d'un tableau métier.
   * Quand un tableau est verrouillé, les poignées de redimensionnement sont
   * désactivées et les largeurs ne peuvent plus être modifiées par l'interface.
   * L'état est stocké dans une option WordPress dédiée pour rester cohérent
   * avec le système de stockage des largeurs.
   */
  public function ajax_save_table_lock() {
    if ( ! is_user_logged_in() ) {
      wp_send_json_error( array( 'message' => 'Accès refusé.' ), 403 );
    }

    check_ajax_referer( 'acdc_table_locks', 'nonce' );

    $table_key = isset( $_POST['table_key'] ) ? sanitize_text_field( wp_unslash( $_POST['table_key'] ) ) : '';
    $table_key = preg_replace( '/[^a-zA-Z0-9_\-:.]/', '', $table_key );

    if ( '' === $table_key || strlen( $table_key ) > 220 ) {
      wp_send_json_error( array( 'message' => 'Identifiant de tableau invalide.' ), 400 );
    }

    $locked = isset( $_POST['locked'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['locked'] ) );

    $locks = get_option( 'acdc_of_table_locks', array() );
    if ( ! is_array( $locks ) ) {
      $locks = array();
    }

    if ( $locked ) {
      $locks[ $table_key ] = true;
    } else {
      unset( $locks[ $table_key ] );
    }

    update_option( 'acdc_of_table_locks', $locks, false );

    wp_send_json_success(
      array(
        'table_key' => $table_key,
        'locked'    => $locked,
      )
    );
  }

  // ACDC 3.23.2 — Forçage manuel du statut workflow dossier de formation.
  public function ajax_set_registration_workflow_status() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      wp_send_json_error( 'Accès refusé.', 403 );
    }
    check_ajax_referer( 'acdc_wf_status', 'nonce' );
    $registration_id = isset( $_POST['registration_id'] ) ? absint( $_POST['registration_id'] ) : 0;
    $status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
    if ( ! $registration_id || ! in_array( $status, $this->get_workflow_status_list(), true ) ) {
      wp_send_json_error( 'Paramètres invalides.' );
    }
    global $wpdb;
    $result = $wpdb->update( $this->training_registration_table, array( 'workflow_status' => $status, 'updated_at' => $this->now_mysql() ), array( 'id' => $registration_id ) );
    if ( false === $result ) {
      wp_send_json_error( 'Erreur lors de la mise à jour.' );
    }
    wp_send_json_success( array( 'registration_id' => $registration_id, 'status' => $status ) );
  }


  public function acdc_of_ensure_rest_api_key() {
    if ( '' === get_option( 'acdc_of_rest_api_key', '' ) ) {
      update_option( 'acdc_of_rest_api_key', wp_generate_password( 48, false ), false );
    }
  }

}
