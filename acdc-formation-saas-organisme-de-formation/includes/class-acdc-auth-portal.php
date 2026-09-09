<?php
/**
 * ACDC Auth Portal — chargeur du module extrait.
 *
 * Charge les traits du module authentification front et utilisateurs portail
 * avant la définition de la classe principale du plugin.
 *
 * @since 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$acdc_auth_portal_dir = __DIR__ . '/auth-portal/';
$acdc_auth_portal_manifest = array(
    'class-acdc-auth-portal-core-trait.php'    => 'ACDC_Auth_Portal_Core_Trait',
    'class-acdc-auth-portal-actions-trait.php' => 'ACDC_Auth_Portal_Actions_Trait',
    'class-acdc-auth-portal-render-trait.php'  => 'ACDC_Auth_Portal_Render_Trait',
);

foreach ( $acdc_auth_portal_manifest as $file => $trait_name ) {
    $path = $acdc_auth_portal_dir . $file;
    if ( ! file_exists( $path ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module auth portail manquant : ' . $file );
        }
        return;
    }

    require_once $path;

    if ( ! trait_exists( $trait_name ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module auth portail invalide : trait ' . $trait_name . ' absent.' );
        }
        return;
    }
}
