<?php
/**
 * Autoloader PSR-4 minimal pour la bibliothèque WordPress/mcp-adapter vendorisée.
 *
 * Versions épinglées (voir VERSION.md) :
 *   - wordpress/mcp-adapter     : v0.5.0
 *   - wordpress/php-mcp-schema   : v0.1.2  (dépendance runtime de mcp-adapter)
 *
 * Mappe les deux namespaces vers les sources vendorisées, sans dépendre de
 * Composer côté site. Défensif : n'enregistre l'autoloader qu'une seule fois.
 *
 * @package ACDC\Vendor\McpAdapter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'ACDC_MCP_ADAPTER_AUTOLOAD_REGISTERED' ) ) {
	return;
}
define( 'ACDC_MCP_ADAPTER_AUTOLOAD_REGISTERED', true );
define( 'ACDC_MCP_ADAPTER_VERSION', '0.5.0' );

spl_autoload_register(
	function ( $class ) {
		// Table des préfixes PSR-4 → répertoire de base (relatif à ce fichier).
		static $prefixes = array(
			'WP\\MCP\\'       => __DIR__ . '/mcp-adapter/includes/',
			'WP\\McpSchema\\' => __DIR__ . '/php-mcp-schema/src/',
		);

		foreach ( $prefixes as $prefix => $base_dir ) {
			$len = strlen( $prefix );
			if ( 0 !== strncmp( $class, $prefix, $len ) ) {
				continue;
			}
			$relative = substr( $class, $len );
			$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_file( $file ) ) {
				require_once $file;
			}
			return;
		}
	}
);
