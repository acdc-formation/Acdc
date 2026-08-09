<?php
/**
 * ACDC Workflow — chargeur du module d'orchestration du parcours.
 *
 * @since 3.25.185
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$acdc_workflow_dir = __DIR__ . '/workflow/';
$acdc_workflow_manifest = array(
    'class-acdc-workflow-core-trait.php'    => 'ACDC_Workflow_Core_Trait',
    'class-acdc-workflow-engine-trait.php'  => 'ACDC_Workflow_Engine_Trait',
    'class-acdc-workflow-actions-trait.php' => 'ACDC_Workflow_Actions_Trait',
    'class-acdc-workflow-render-trait.php'  => 'ACDC_Workflow_Render_Trait',
);

foreach ( $acdc_workflow_manifest as $file => $trait_name ) {
    $path = $acdc_workflow_dir . $file;
    if ( ! file_exists( $path ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module workflow manquant : ' . $file );
        }
        return;
    }

    require_once $path;

    if ( ! trait_exists( $trait_name ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module workflow invalide : trait ' . $trait_name . ' absent.' );
        }
        return;
    }
}
