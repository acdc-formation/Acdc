<?php
/**
 * ACDC Audit Qualiopi — Bootstrap
 *
 * Charge les sous-modules, enregistre les hooks sidebar + handlers.
 *
 * @package ACDC_Formation_SAAS
 * @since   3.21.09-hotfix25
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$audit_dir = __DIR__ . '/audit/';
$audit_manifest = array(
    'class-acdc-audit-core.php'   => 'ACDC_Audit_Core',
    'class-acdc-audit-render.php' => 'ACDC_Audit_Render',
    'class-acdc-audit-zip.php'    => 'ACDC_Audit_Zip',
);
foreach ( $audit_manifest as $file => $class ) {
    $path = $audit_dir . $file;
    if ( ! file_exists( $path ) ) { return; }
    require_once $path;
    if ( ! class_exists( $class ) ) { return; }
}

class ACDC_Audit {

    private static $instance = null;

    /** @var ACDC_Audit_Core */
    public $core;
    /** @var ACDC_Audit_Render */
    private $render;
    /** @var ACDC_Audit_Zip */
    private $zip_exporter;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->core         = new ACDC_Audit_Core();
        $this->render       = new ACDC_Audit_Render( $this->core );
        $this->zip_exporter = new ACDC_Audit_Zip( $this->core );
        $this->register_hooks();
    }

    private function register_hooks() {
        // Installation BDD — garde de version pour éviter dbDelta à chaque requête.
        $core = $this->core;
        add_action( 'init', function() use ( $core ) {
            if ( get_option( 'acdc_of_audit_db_version' ) !== '1.0' ) {
                $core->install();
                update_option( 'acdc_of_audit_db_version', '1.0' );
            }
        }, 6 );

        // Page publique auditeur
        add_action( 'template_redirect', array( $this->render, 'maybe_handle_public' ), 2 );

        // Menu admin dédié (sous Qualité & conformité)
        add_action( 'acdc_portal_register_menu_items', array( $this, 'register_menu_item' ), 20 );

        // Rendu de l'onglet audit dans le portail
        add_filter( 'acdc_portal_render_unknown_tab', array( $this, 'render_portal_tab' ), 10, 1 );

        // Handlers POST admin
        add_action( 'admin_post_acdc_audit_create_token', array( $this, 'handle_create_token' ) );
        add_action( 'admin_post_acdc_audit_revoke_token', array( $this, 'handle_revoke_token' ) );
        add_action( 'admin_post_acdc_audit_export_zip',   array( $this, 'handle_export_zip' ) );
    }

    /* -----------------------------------------------------------------------
     * Menu sidebar
     * -------------------------------------------------------------------- */
    public function register_menu_item( $items ) {
        // Ajout dans le groupe "Qualité & conformité" si existant, sinon groupe autonome
        return $items;
    }

    public function render_portal_tab( $tab ) {
        if ( 'audit' !== $tab ) { return; }
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $this->render->render_admin_tab();
    }

    /* -----------------------------------------------------------------------
     * Handlers POST
     * -------------------------------------------------------------------- */
    public function handle_export_zip() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
        check_admin_referer( 'acdc_audit_export_zip' );
        $filters = array(
            'formation_id' => absint( $_GET['formation_id'] ?? 0 ),
            'learner_id'   => absint( $_GET['learner_id'] ?? 0 ),
            'company_id'   => absint( $_GET['company_id'] ?? 0 ),
            'doc_type'     => sanitize_key( $_GET['doc_type'] ?? '' ),
            'date_from'    => sanitize_text_field( $_GET['date_from'] ?? '' ),
            'date_to'      => sanitize_text_field( $_GET['date_to'] ?? '' ),
        );
        $this->zip_exporter->export( $filters );
    }

    public function handle_create_token() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
        check_admin_referer( 'acdc_audit_create_token' );
        $label = sanitize_text_field( wp_unslash( $_POST['token_label'] ?? '' ) );
        $days  = max( 1, min( 365, absint( $_POST['token_days'] ?? 30 ) ) );
        $this->core->create_token( $label, $days );
        wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-audit', 'tab' => 'audit', 'audit_sub' => 'tokens', 'token_created' => '1' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_revoke_token() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
        $token_id = absint( $_POST['token_id'] ?? 0 );
        check_admin_referer( 'acdc_audit_revoke_' . $token_id );
        $this->core->revoke_token( $token_id );
        wp_safe_redirect( add_query_arg( array( 'page' => 'acdc-of-audit', 'tab' => 'audit', 'audit_sub' => 'tokens' ), admin_url( 'admin.php' ) ) );
        exit;
    }
}

ACDC_Audit::get_instance();
