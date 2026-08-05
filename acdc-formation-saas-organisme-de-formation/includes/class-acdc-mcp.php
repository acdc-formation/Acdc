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

$acdc_mcp_dir      = __DIR__ . '/mcp/';
$acdc_mcp_manifest = array(
	'class-acdc-mcp-abilities-trait.php' => 'ACDC_Mcp_Abilities_Trait',
);

foreach ( $acdc_mcp_manifest as $file => $trait_name ) {
	$path = $acdc_mcp_dir . $file;
	if ( file_exists( $path ) ) {
		require_once $path;
	}
}
