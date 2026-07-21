<?php
/**
 * ACDC Learners / Contacts — chargeur du module extrait.
 *
 * Charge les traits du sous-bloc métier apprenants + contacts liés
 * avant la définition de la classe principale du plugin.
 *
 * @since 3.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$acdc_learners_contacts_dir = __DIR__ . '/learners-contacts/';
$acdc_learners_contacts_manifest = array(
    'class-acdc-learners-contacts-core-trait.php'    => 'ACDC_Learners_Contacts_Core_Trait',
    'class-acdc-learners-contacts-actions-trait.php' => 'ACDC_Learners_Contacts_Actions_Trait',
    'class-acdc-learners-contacts-render-trait.php'  => 'ACDC_Learners_Contacts_Render_Trait',
);

foreach ( $acdc_learners_contacts_manifest as $file => $trait_name ) {
    $path = $acdc_learners_contacts_dir . $file;
    if ( ! file_exists( $path ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module apprenants/contacts manquant : ' . $file );
        }
        return;
    }

    require_once $path;

    if ( ! trait_exists( $trait_name ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module apprenants/contacts invalide : trait ' . $trait_name . ' absent.' );
        }
        return;
    }
}
