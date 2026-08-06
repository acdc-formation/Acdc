<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * ACDC Signature — Orchestrateur
 *
 * Point d'entrée unique du module signature.
 * Charge les 6 sous-modules et les relie entre eux.
 *
 * Architecture v2.0 (3.4.1) :
 *  - class-acdc-sig-core.php     — Bootstrap, tables, tokens, audit, settings
 *  - class-acdc-sig-email.php    — Envois email
 *  - class-acdc-sig-pdf.php      — Moteur PDF natif
 *  - class-acdc-sig-public.php   — Shortcode + formulaire + submit
 *  - class-acdc-sig-admin.php    — Interface admin + handlers POST
 *  - class-acdc-sig-sessions.php — Phase 3 : sessions + cron relances
 *
 * Corrections audit intégrées :
 *  - Rate limiting sur l'endpoint public (5 tentatives / 10 min / IP+token)
 *  - Taille sig_data vérifiée côté serveur (600 Ko max)
 *  - date() remplacé par gmdate() sur les 4 occurrences
 *  - file_put_contents() vérifié avec log d'erreur
 *  - iframe src validée (domaine WordPress uniquement)
 *  - Cron nettoyé à la désactivation
 *  - arrow function fn() remplacée (compatibilité PHP 7.3+)
 *  - base64_decode vérifié via finfo avant écriture disque
 *
 * @package ACDC_Formation_SAAS
 * @since   3.4.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Chargement sécurisé des sous-modules
$sig_dir = __DIR__ . '/signature/';
$acdc_sig_manifest = array(
    'class-acdc-sig-core.php'     => 'ACDC_Sig_Core',
    'class-acdc-sig-email.php'    => 'ACDC_Sig_Email',
    'class-acdc-sig-pdf.php'      => 'ACDC_Sig_PDF',
    'class-acdc-sig-public.php'   => 'ACDC_Sig_Public',
    'class-acdc-sig-admin.php'    => 'ACDC_Sig_Admin',
    'class-acdc-sig-sessions.php' => 'ACDC_Sig_Sessions',
    'class-acdc-sig-portal.php'   => 'ACDC_Sig_Portal',
);

foreach ( $acdc_sig_manifest as $file => $class_name ) {
    $path = $sig_dir . $file;
    if ( ! file_exists( $path ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module signature manquant : ' . $file );
        }
        return;
    }

    require_once $path;

    if ( ! class_exists( $class_name ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module signature invalide : classe ' . $class_name . ' absente.' );
        }
        return;
    }
}

class ACDC_Signature {

    private static $instance = null;

    /** @var ACDC_Sig_Core */
    private $core;
    /** @var ACDC_Sig_Email */
    private $email;
    /** @var ACDC_Sig_PDF */
    private $pdf;
    /** @var ACDC_Sig_Public */
    private $pub;
    /** @var ACDC_Sig_Admin */
    private $admin;
    /** @var ACDC_Sig_Sessions */
    private $sessions;
    /** @var ACDC_Sig_Portal */
    private $portal;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->core     = new ACDC_Sig_Core();
        $this->core->init_tables();
        $this->email    = new ACDC_Sig_Email( $this->core );
        $this->pdf      = new ACDC_Sig_PDF( $this->core );
        $this->pub      = new ACDC_Sig_Public( $this->core, $this->pdf, $this->email );
        $this->admin    = new ACDC_Sig_Admin( $this->core, $this->pdf, $this->email );
        $this->sessions = new ACDC_Sig_Sessions( $this->core, $this->email );
        $this->portal   = new ACDC_Sig_Portal( $this->core, $this->email, $this->pdf );
        $this->register_hooks();
    }

    private function register_hooks() {
        add_action( 'init', array( $this->core, 'maybe_upgrade' ), 5 );

        add_shortcode( 'acdc_signature_page', array( $this->pub, 'render_shortcode' ) );
        add_filter( 'template_include', array( $this->pub, 'maybe_use_blank_template' ), 20 );

        add_action( 'admin_post_acdc_sig_send_request',      array( $this->admin, 'handle_send_request' ) );
        add_action( 'admin_post_acdc_sig_resend_request',    array( $this->admin, 'handle_resend_request' ) );
        add_action( 'admin_post_acdc_sig_delete_request',    array( $this->admin, 'handle_delete_request' ) );
        add_action( 'admin_post_acdc_sig_save_settings',     array( $this->admin, 'handle_save_settings' ) );

        add_action( 'admin_post_nopriv_acdc_sig_submit',     array( $this->pub, 'handle_submit' ) );
        add_action( 'admin_post_acdc_sig_submit',            array( $this->pub, 'handle_submit' ) );

        // OTP e-mail — niveau renforcé
        add_action( 'admin_post_nopriv_acdc_sig_otp_verify', array( $this->pub, 'handle_otp_verify' ) );
        add_action( 'admin_post_acdc_sig_otp_verify',        array( $this->pub, 'handle_otp_verify' ) );
        add_action( 'admin_post_nopriv_acdc_sig_otp_resend', array( $this->pub, 'handle_otp_resend' ) );
        add_action( 'admin_post_acdc_sig_otp_resend',        array( $this->pub, 'handle_otp_resend' ) );

        /* ACDC 3.25.151 — Service du document à signer (autorisé par le jeton de signature).
           Indispensable depuis le verrouillage du dossier des contrats : sans lui, l'aperçu
           de la page de signature renvoie 403 et le signataire ne peut pas lire le document. */
        add_action( 'admin_post_nopriv_acdc_sig_doc',        array( $this->pub, 'handle_serve_doc' ) );
        add_action( 'admin_post_acdc_sig_doc',               array( $this->pub, 'handle_serve_doc' ) );

        add_action( 'admin_menu', array( $this->admin, 'register_menu' ) );

        // Front-office portal
        $this->portal->register_hooks();

        $this->sessions->register_hooks();
    }

    public static function install() {
        $instance = self::get_instance();
        $instance->core->install();
    }

    /** Correction audit : cron nettoyé à la désactivation */
    public static function deactivate() {
        wp_clear_scheduled_hook( 'acdc_sig_cron_relances' );
    }
}
