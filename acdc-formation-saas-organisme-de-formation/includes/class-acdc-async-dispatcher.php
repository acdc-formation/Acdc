<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * ACDC Async Dispatcher — chargeur du socle commun aux campagnes asynchrones.
 *
 * Trait technique partagé qui sera consommé par :
 *  - le module Quizzes (3.21+) pour les tests de positionnement et
 *    les évaluations des acquis envoyés par e-mail ;
 *  - le futur module Surveys (3.22+) pour les enquêtes Qualiopi.
 *
 * En 3.21.00, seules les méthodes utilitaires de génération/validation de
 * token sont finalisées ; les méthodes d'envoi e-mail et de relance sont
 * des stubs documentés pour cadrer l'API future.
 *
 * @since 3.21.00
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$acdc_async_dispatcher_dir = __DIR__ . '/async-dispatcher/';
$acdc_async_dispatcher_manifest = array(
    'class-acdc-async-dispatcher-trait.php' => 'ACDC_Async_Dispatcher_Trait',
);

foreach ( $acdc_async_dispatcher_manifest as $file => $trait_name ) {
    $path = $acdc_async_dispatcher_dir . $file;
    if ( ! file_exists( $path ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Socle async dispatcher manquant : ' . $file );
        }
        return;
    }

    require_once $path;

    if ( ! trait_exists( $trait_name ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Socle async dispatcher invalide : trait ' . $trait_name . ' absent.' );
        }
        return;
    }
}
