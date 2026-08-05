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
