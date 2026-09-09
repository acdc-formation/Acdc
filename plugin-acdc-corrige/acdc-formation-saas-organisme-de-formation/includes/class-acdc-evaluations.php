<?php
/**
 * ACDC Évaluations des acquis — chargeur du module extrait.
 *
 * Charge les traits du sous-bloc métier évaluations des acquis
 * avant la définition de la classe principale du plugin.
 *
 * @since 3.11.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$acdc_evaluations_dir = __DIR__ . '/evaluations/';
$acdc_evaluations_manifest = array(
    'class-acdc-evaluations-core-trait.php'    => 'ACDC_Evaluations_Core_Trait',
    'class-acdc-evaluations-actions-trait.php' => 'ACDC_Evaluations_Actions_Trait',
    'class-acdc-evaluations-render-trait.php'  => 'ACDC_Evaluations_Render_Trait',
);

foreach ( $acdc_evaluations_manifest as $file => $trait_name ) {
    $path = $acdc_evaluations_dir . $file;
    if ( ! file_exists( $path ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module évaluations des acquis manquant : ' . $file );
        }
        return;
    }

    require_once $path;

    if ( ! trait_exists( $trait_name ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module évaluations des acquis invalide : trait ' . $trait_name . ' absent.' );
        }
        return;
    }
}
