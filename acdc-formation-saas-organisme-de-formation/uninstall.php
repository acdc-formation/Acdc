<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
    // ACDC 3.25.115 — déprogrammer TOUS les crons (aligné sur la désactivation).
    wp_clear_scheduled_hook( 'acdc_sig_cron_relances' );
    wp_clear_scheduled_hook( 'acdc_of_surveys_cron_dispatches' );
    wp_clear_scheduled_hook( 'acdc_of_surveys_cron_reminders' );
    wp_clear_scheduled_hook( 'acdc_of_surveys_cron_expirations' );
    wp_clear_scheduled_hook( 'acdc_of_surveys_cron_action_followups' );
    wp_clear_scheduled_hook( 'acdc_of_learner_portal_cron_maintenance' );
    wp_clear_scheduled_hook( 'acdc_nad_cron_send_and_relance' );
    wp_clear_scheduled_hook( 'acdc_of_cron_push_indicators' );
    wp_clear_scheduled_hook( 'acdc_of_cron_sync_formations' );
    wp_clear_scheduled_hook( 'acdc_of_absence_alert_cron' );
    wp_clear_scheduled_hook( 'acdc_of_session_close_cron' );
    wp_clear_scheduled_hook( 'acdc_of_convocation_cron' );
    wp_clear_scheduled_hook( 'acdc_of_positioning_test_cron' );
    wp_clear_scheduled_hook( 'acdc_of_qualiopi_alerts_cron' );
    wp_clear_scheduled_hook( 'acdc_of_trainer_portal_cron_maintenance' );
    wp_clear_scheduled_hook( 'acdc_of_qz_cron_dispatches' );
    wp_clear_scheduled_hook( 'acdc_of_qz_cron_reminders' );
    wp_clear_scheduled_hook( 'acdc_of_qz_cron_expirations' );
    wp_clear_scheduled_hook( 'acdc_of_qz_cron_close_inactive_sessions' );
    wp_clear_scheduled_hook( 'acdc_of_qz_cron_rgpd_purge' );
    wp_clear_scheduled_hook( 'acdc_of_watch_collect_cron' );
    wp_clear_scheduled_hook( 'acdc_of_watch_analyze_cron' );
    wp_clear_scheduled_hook( 'acdc_of_watch_digest_cron' );
    wp_clear_scheduled_hook( 'acdc_of_watch_reminder_cron' );
}

if ( function_exists( 'delete_transient' ) ) {
    delete_transient( 'acdc_of_saas_runtime_notice' );
}

$keep_data = get_option( 'acdc_of_keep_data_on_uninstall', 'yes' );
if ( 'yes' === $keep_data ) {
    return;
}

$options = array(
    'acdc_of_saas_version',
    'acdc_of_saas_boot_error',
    'acdc_of_action_log',
    'acdc_of_attestation_params',
    'acdc_of_billing_settings',
    'acdc_of_branding',
    'acdc_of_catalog',
    'acdc_of_catalog_page_id',
    'acdc_of_catalog_settings',
    'acdc_of_company_logo_id',
    'acdc_of_company_profile',
    'acdc_of_continuous_improvement_records',
    'acdc_of_contract_params',
    'acdc_of_convocation_params',
    'acdc_of_extranet_companies_page_id',
    'acdc_of_extranet_contacts_page_id',
    'acdc_of_extranet_dashboard_page_id',
    'acdc_of_extranet_documents_page_id',
    'acdc_of_extranet_learners_page_id',
    'acdc_of_extranet_login_page_id',
    'acdc_of_extranet_needs_page_id',
    'acdc_of_extranet_parent_page_id',
    'acdc_of_extranet_prospects_page_id',
    'acdc_of_extranet_settings_page_id',
    'acdc_of_funder_surveys_last_id',
    'acdc_of_hot_surveys_last_id',
    'acdc_of_login',
    'acdc_of_login_page_id',
    'acdc_of_mid_surveys_last_id',
    'acdc_of_portal',
    'acdc_of_portal_page_id',
    'acdc_of_subcontract_params',
    'acdc_of_trainer_surveys_last_id',
    'acdc_of_company_surveys_last_id',
    'acdc_of_cold_surveys_last_id',
    'acdc_of_surveys_db_version',
    'acdc_of_questionnaire_public_page_id',
    'acdc_of_keep_data_on_uninstall',
    'acdc_of_global_column_widths',
    'acdc_of_db_version',
    'acdc_of_last_backup_file',
    'acdc_of_last_backup_at',
    'acdc_of_last_safety_backup_file',
    'acdc_of_last_safety_backup_at',
    'acdc_of_data_protection_level',
    'acdc_of_purge_allowed_in_production',
    'acdc_of_backup_retention_count',
    // ACDC 3.25.114 — options ajoutées par les vagues de correction.
    'acdc_emarg_db_version',
    'acdc_of_document_number_counters',
    'acdc_of_watch_deleted_default_ids',
    'acdc_of_watch_sources',
    'acdc_of_questionnaire_settings',
);

foreach ( $options as $option_name ) {
    delete_option( $option_name );
}

$tables = array(
    'acdc_of_companies',
    'acdc_of_contacts',
    'acdc_of_documents',
    'acdc_of_formations',
    'acdc_of_sessions',
    'acdc_of_learners',
    'acdc_of_needs',
    'acdc_of_prospects',
    'acdc_of_groups',
    'acdc_of_funders',
    'acdc_of_trainers',
    'acdc_of_quizzes',
    'acdc_of_positioning_tests',
    'acdc_of_evaluations',
    'acdc_of_need_analyses',
    'acdc_of_registration_contracts',
    'acdc_of_training_registrations',
    'acdc_of_prospect_rdvs',
    'acdc_of_pre_meetings',
    'acdc_of_mid_surveys',
    'acdc_of_hot_surveys',
    'acdc_of_cold_surveys',
    'acdc_of_trainer_surveys',
    'acdc_of_company_surveys',
    'acdc_of_funder_surveys',
    'acdc_of_watch_items',
    'acdc_of_complaints',
    'acdc_of_trainer_contracts',
    'acdc_of_trainer_evaluations',
    'acdc_of_trainer_logbook',
    'acdc_of_trainer_documents',
    'acdc_of_trainer_resources',
    'acdc_of_need_blocks',
    'acdc_of_need_questions',
    'acdc_of_thematiques',
    'acdc_of_quotes',
    'acdc_of_invoices',
    'acdc_of_prospect_activities',
    'acdc_of_audit_tokens',
    'acdc_of_questionnaire_models',
    'acdc_of_questionnaire_actions',
    'acdc_of_questionnaire_logs',
    'acdc_of_questionnaire_sessions',
    'acdc_of_questionnaire_session_participants',
    'acdc_of_questionnaire_session_answers',
    'acdc_of_system_logs',
);

foreach ( $tables as $table_name ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table_name}" );
}
