<?php
/**
 * ACDC Conformité / Qualité annexe — chargeur du module extrait.
 *
 * Charge les traits du module BPF, amélioration continue,
 * échéances apprenants, veille et prestations annexes
 * avant la définition de la classe principale du plugin.
 *
 * @since 3.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$acdc_compliance_quality_dir = __DIR__ . '/compliance-quality/';
$acdc_compliance_quality_manifest = array(
    'class-acdc-compliance-quality-core-trait.php'    => 'ACDC_Compliance_Quality_Core_Trait',
    'class-acdc-compliance-quality-actions-trait.php' => 'ACDC_Compliance_Quality_Actions_Trait',
    'class-acdc-compliance-quality-render-trait.php'  => 'ACDC_Compliance_Quality_Render_Trait',
);

foreach ( $acdc_compliance_quality_manifest as $file => $trait_name ) {
    $path = $acdc_compliance_quality_dir . $file;
    if ( ! file_exists( $path ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module conformité/qualité manquant : ' . $file );
        }
        return;
    }

    require_once $path;

    if ( ! trait_exists( $trait_name ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module conformité/qualité invalide : trait ' . $trait_name . ' absent.' );
        }
        return;
    }
}
