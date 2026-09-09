<?php
/**
 * ACDC Exploration contrôlée — chargeur du module.
 *
 * Compte temporaire dédié, mode exploration sécurisé
 * et prompt d'exploration prêt à copier.
 *
 * @since 3.16.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$acdc_agent_audit_dir = __DIR__ . '/agent-audit/';
$acdc_agent_audit_manifest = array(
    'class-acdc-agent-audit-core-trait.php'    => 'ACDC_Agent_Audit_Core_Trait',
    'class-acdc-agent-audit-actions-trait.php' => 'ACDC_Agent_Audit_Actions_Trait',
    'class-acdc-agent-audit-render-trait.php'  => 'ACDC_Agent_Audit_Render_Trait',
);

foreach ( $acdc_agent_audit_manifest as $file => $trait_name ) {
    $path = $acdc_agent_audit_dir . $file;
    if ( ! file_exists( $path ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module exploration contrôlée manquant : ' . $file );
        }
        return;
    }

    require_once $path;

    if ( ! trait_exists( $trait_name ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module exploration contrôlée invalide : trait ' . $trait_name . ' absent.' );
        }
        return;
    }
}
