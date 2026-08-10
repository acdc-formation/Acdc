<?php
/**
 * ACDC Séances — chargeur du module extrait.
 *
 * Charge les traits du sous-bloc métier séances
 * avant la définition de la classe principale du plugin.
 *
 * @since 3.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$acdc_sessions_dir = __DIR__ . '/sessions/';
$acdc_sessions_manifest = array(
    'class-acdc-sessions-core-trait.php'    => 'ACDC_Sessions_Core_Trait',
    'class-acdc-sessions-actions-trait.php' => 'ACDC_Sessions_Actions_Trait',
    'class-acdc-sessions-render-trait.php'  => 'ACDC_Sessions_Render_Trait',
    /* ACDC 3.25.207 — Documents de séance et verrou de l'extranet apprenant. */
    'class-acdc-session-documents-trait.php' => 'ACDC_Session_Documents_Trait',
);

foreach ( $acdc_sessions_manifest as $file => $trait_name ) {
    $path = $acdc_sessions_dir . $file;
    if ( ! file_exists( $path ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module séances manquant : ' . $file );
        }
        return;
    }

    require_once $path;

    if ( ! trait_exists( $trait_name ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module séances invalide : trait ' . $trait_name . ' absent.' );
        }
        return;
    }
}
