<?php
/**
 * ACDC Marketing — chargeur du module extrait.
 *
 * Charge les traits du module marketing avant la définition
 * de la classe principale du plugin.
 *
 * @since 3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$acdc_marketing_dir = __DIR__ . '/marketing/';
$acdc_marketing_manifest = array(
    'class-acdc-marketing-core-trait.php'    => 'ACDC_Marketing_Core_Trait',
    'class-acdc-marketing-actions-trait.php' => 'ACDC_Marketing_Actions_Trait',
    'class-acdc-marketing-render-trait.php'  => 'ACDC_Marketing_Render_Trait',
);

foreach ( $acdc_marketing_manifest as $file => $trait_name ) {
    $path = $acdc_marketing_dir . $file;
    if ( ! file_exists( $path ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module marketing manquant : ' . $file );
        }
        return;
    }

    require_once $path;

    if ( ! trait_exists( $trait_name ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module marketing invalide : trait ' . $trait_name . ' absent.' );
        }
        return;
    }
}
