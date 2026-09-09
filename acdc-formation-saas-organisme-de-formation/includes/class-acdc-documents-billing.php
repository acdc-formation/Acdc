<?php
/**
 * ACDC Documents / devis / factures — chargeur du module extrait.
 *
 * Charge les traits du sous-bloc métier documents / devis / factures
 * avant la définition de la classe principale du plugin.
 *
 * @since 3.15.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$acdc_documents_billing_dir = __DIR__ . '/documents-billing/';
$acdc_documents_billing_manifest = array(
    'class-acdc-documents-billing-core-trait.php'    => 'ACDC_Documents_Billing_Core_Trait',
    'class-acdc-documents-billing-actions-trait.php' => 'ACDC_Documents_Billing_Actions_Trait',
    'class-acdc-documents-billing-render-trait.php'  => 'ACDC_Documents_Billing_Render_Trait',
);

foreach ( $acdc_documents_billing_manifest as $file => $trait_name ) {
    $path = $acdc_documents_billing_dir . $file;
    if ( ! file_exists( $path ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module documents / devis / factures manquant : ' . $file );
        }
        return;
    }

    require_once $path;

    if ( ! trait_exists( $trait_name ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module documents / devis / factures invalide : trait ' . $trait_name . ' absent.' );
        }
        return;
    }
}
