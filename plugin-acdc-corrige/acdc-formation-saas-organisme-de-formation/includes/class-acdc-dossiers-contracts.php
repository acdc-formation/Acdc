<?php
/**
 * ACDC Dossiers / Inscriptions / Conventions-contrats — chargeur du module extrait.
 *
 * Charge les traits du sous-bloc métier dossiers / inscription / conventions-contrats
 * avant la définition de la classe principale du plugin.
 *
 * @since 3.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$acdc_dossiers_contracts_dir = __DIR__ . '/dossiers-contracts/';
$acdc_dossiers_contracts_manifest = array(
    'class-acdc-dossiers-contracts-core-trait.php'    => 'ACDC_Dossiers_Contracts_Core_Trait',
    'class-acdc-dossiers-contracts-actions-trait.php' => 'ACDC_Dossiers_Contracts_Actions_Trait',
    'class-acdc-dossiers-contracts-render-trait.php'  => 'ACDC_Dossiers_Contracts_Render_Trait',
);

foreach ( $acdc_dossiers_contracts_manifest as $file => $trait_name ) {
    $path = $acdc_dossiers_contracts_dir . $file;
    if ( ! file_exists( $path ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module dossiers / conventions-contrats manquant : ' . $file );
        }
        return;
    }

    require_once $path;

    if ( ! trait_exists( $trait_name ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module dossiers / conventions-contrats invalide : trait ' . $trait_name . ' absent.' );
        }
        return;
    }
}
