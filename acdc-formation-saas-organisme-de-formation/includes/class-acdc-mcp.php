<?php
/**
 * ACDC MCP — chargeur du module d'abilities MCP.
 *
 * Charge le trait exposant une sélection d'actions métier via l'API
 * WordPress Abilities (namespace mcp/ une fois mcp-adapter actif).
 *
 * @since 3.25.144
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$acdc_mcp_dir = __DIR__ . '/mcp/';

/* Trait des abilities (composé dans la classe principale). */
$acdc_mcp_abilities = $acdc_mcp_dir . 'class-acdc-mcp-abilities-trait.php';
if ( file_exists( $acdc_mcp_abilities ) ) {
	require_once $acdc_mcp_abilities;
}

/*
 * Armement PRÉCOCE de l'enregistrement des abilities, dès le chargement du module
 * (avant tout accès au registre Abilities, donc avant rest_api_init), et de façon
 * DÉCOUPLÉE du serveur MCP. Contrat du core WordPress 6.9/7.0 :
 *   - la catégorie se déclare sur wp_abilities_api_categories_init ;
 *   - les abilities se déclarent sur wp_abilities_api_init.
 * Les deux hooks du core sont paresseux et ne se déclenchent qu'une seule fois ;
 * il faut donc que ces add_action soient posés le plus tôt possible. Les callbacks
 * résolvent l'instance du plugin au moment du déclenchement (la classe existe alors).
 */
if ( function_exists( 'add_action' ) ) {
	add_action(
		'wp_abilities_api_categories_init',
		function () {
			if ( class_exists( 'ACDC_Formation_SAAS_Plugin' ) ) {
				$p = ACDC_Formation_SAAS_Plugin::get_instance();
				if ( is_object( $p ) && method_exists( $p, 'register_mcp_ability_categories' ) ) {
					$p->register_mcp_ability_categories();
				}
			}
		}
	);
	add_action(
		'wp_abilities_api_init',
		function () {
			if ( class_exists( 'ACDC_Formation_SAAS_Plugin' ) ) {
				$p = ACDC_Formation_SAAS_Plugin::get_instance();
				if ( is_object( $p ) && method_exists( $p, 'register_mcp_abilities' ) ) {
					$p->register_mcp_abilities();
				}
			}
		}
	);
}

/* Serveur MCP intégré (bibliothèque mcp-adapter vendorisée). Entièrement défensif :
   s'auto-désactive si la lib ou l'API Abilities sont absentes. Amorcé tardivement
   pour laisser toutes les extensions (et l'API Abilities) se charger d'abord. */
$acdc_mcp_server = $acdc_mcp_dir . 'class-acdc-mcp-server.php';
if ( file_exists( $acdc_mcp_server ) ) {
	require_once $acdc_mcp_server;
	if ( class_exists( 'ACDC_Mcp_Server' ) && function_exists( 'add_action' ) ) {
		add_action( 'plugins_loaded', array( 'ACDC_Mcp_Server', 'boot' ), 20 );
	}
}
