<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * ACDC Quizzes — chargeur du module Quiz / Tests / Évaluations (moteur 3.21+).
 *
 * Charge les 4 traits du nouveau moteur de quiz unifié :
 *  - Core    : constantes, schéma BDD (tables `acdc_of_qz_*`), helpers,
 *              pages publiques, cron events.
 *  - Actions : enregistrement des hooks (admin-post, AJAX, cron, shortcodes,
 *              page admin Pilotage).
 *  - Engine  : moteur de jeu commun aux modes live et async (PIN, scoring,
 *              tokens).
 *  - Render  : onglet Quiz fiche formation, page Pilotage admin, shortcodes
 *              publics.
 *
 * Ce module est strictement parallèle aux embryons existants
 * (`acdc_of_quizzes`, `acdc_of_positioning_tests`, `acdc_of_evaluations`)
 * conservés intacts pour ne pas casser le code legacy. Le préfixe distinct
 * `acdc_of_qz_*` évite tout conflit BDD.
 *
 * @since 3.21.00
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$acdc_quizzes_dir = __DIR__ . '/quizzes/';
$acdc_quizzes_manifest = array(
    'class-acdc-quizzes-core-trait.php'           => 'ACDC_Quizzes_Core_Trait',
    'class-acdc-quizzes-actions-trait.php'        => 'ACDC_Quizzes_Actions_Trait',
    'class-acdc-quizzes-engine-trait.php'         => 'ACDC_Quizzes_Engine_Trait',
    /* 3.21.01 — Trait éditeur ; doit être chargé AVANT le trait Render principal
       car ce dernier le consomme via `use ACDC_Quizzes_Render_Editor_Trait`. */
    'class-acdc-quizzes-render-editor-trait.php'  => 'ACDC_Quizzes_Render_Editor_Trait',
    'class-acdc-quizzes-render-trait.php'         => 'ACDC_Quizzes_Render_Trait',
    /* ACDC 3.21.04.1 — Trait des écrans de résultats détaillés. */
    'class-acdc-quizzes-render-results-trait.php' => 'ACDC_Quizzes_Render_Results_Trait',
    /* ACDC 3.21.04.2-a — Trait des pages live (host + player). */
    'class-acdc-quizzes-render-live-trait.php'    => 'ACDC_Quizzes_Render_Live_Trait',
);

foreach ( $acdc_quizzes_manifest as $file => $trait_name ) {
    $path = $acdc_quizzes_dir . $file;
    if ( ! file_exists( $path ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module quizzes manquant : ' . $file );
        }
        return;
    }

    require_once $path;

    if ( ! trait_exists( $trait_name ) ) {
        if ( function_exists( 'acdc_of_saas_store_boot_error' ) ) {
            acdc_of_saas_store_boot_error( 'Sous-module quizzes invalide : trait ' . $trait_name . ' absent.' );
        }
        return;
    }
}
