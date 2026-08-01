<?php
/**
 * ACDC Questionnaires / Enquêtes — chargeur du module extrait.
 *
 * Charge les traits du moteur questionnaires / enquêtes avant la définition
 * de la classe principale du plugin.
 *
 * @since 3.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$acdc_questionnaires_dir = __DIR__ . '/questionnaires/';
$acdc_questionnaires_manifest = array(
    'class-acdc-questionnaires-core-trait.php'    => 'ACDC_Questionnaires_Core_Trait',
    'class-acdc-questionnaires-engine-trait.php'  => 'ACDC_Questionnaires_Engine_Trait',
    'class-acdc-questionnaires-actions-trait.php' => 'ACDC_Questionnaires_Actions_Trait',
    'class-acdc-questionnaires-render-trait.php'  => 'ACDC_Questionnaires_Render_Trait',
);

foreach ( $acdc_questionnaires_manifest as $file => $trait_name ) {
    $path = $acdc_questionnaires_dir . $file;
    if ( ! file_exists( $path ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module questionnaires manquant : ' . $file );
        }
        return;
    }

    require_once $path;

    if ( ! trait_exists( $trait_name ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module questionnaires invalide : trait ' . $trait_name . ' absent.' );
        }
        return;
    }
}
