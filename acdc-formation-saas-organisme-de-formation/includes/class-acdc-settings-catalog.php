<?php
/**
 * ACDC Réglages OF / Profil organisme / Catalogue — chargeur du module extrait.
 *
 * Charge les traits du module réglages OF, profil organisme et catalogue
 * avant la définition de la classe principale du plugin.
 *
 * @since 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$acdc_settings_catalog_dir = __DIR__ . '/settings-catalog/';
$acdc_settings_catalog_manifest = array(
    'class-acdc-settings-catalog-core-trait.php'              => 'ACDC_Settings_Catalog_Core_Trait',
    'class-acdc-settings-catalog-actions-trait.php'           => 'ACDC_Settings_Catalog_Actions_Trait',
    'class-acdc-settings-catalog-render-trait.php'            => 'ACDC_Settings_Catalog_Render_Trait',
    'class-acdc-settings-catalog-programme-pdf-trait.php'     => 'ACDC_Settings_Catalog_Programme_PDF_Trait',
);

foreach ( $acdc_settings_catalog_manifest as $file => $trait_name ) {
    $path = $acdc_settings_catalog_dir . $file;
    if ( ! file_exists( $path ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module réglages/catalogue manquant : ' . $file );
        }
        return;
    }

    require_once $path;

    if ( ! trait_exists( $trait_name ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module réglages/catalogue invalide : trait ' . $trait_name . ' absent.' );
        }
        return;
    }
}
