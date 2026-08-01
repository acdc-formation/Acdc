<?php
/**
 * ACDC CRM commercial — chargeur du module extrait.
 *
 * Charge les traits du sous-bloc métier prospects et suivi commercial
 * avant la définition de la classe principale du plugin.
 *
 * @since 3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$acdc_crm_commercial_dir = __DIR__ . '/crm-commercial/';
$acdc_crm_commercial_manifest = array(
    'class-acdc-crm-commercial-core-trait.php'    => 'ACDC_Crm_Commercial_Core_Trait',
    'class-acdc-crm-commercial-actions-trait.php' => 'ACDC_Crm_Commercial_Actions_Trait',
    'class-acdc-crm-commercial-render-trait.php'  => 'ACDC_Crm_Commercial_Render_Trait',
    'class-acdc-crm-commercial-activity-trait.php' => 'ACDC_Crm_Commercial_Activity_Trait',
);

foreach ( $acdc_crm_commercial_manifest as $file => $trait_name ) {
    $path = $acdc_crm_commercial_dir . $file;
    if ( ! file_exists( $path ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module CRM commercial manquant : ' . $file );
        }
        return;
    }

    require_once $path;

    if ( ! trait_exists( $trait_name ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module CRM commercial invalide : trait ' . $trait_name . ' absent.' );
        }
        return;
    }
}
