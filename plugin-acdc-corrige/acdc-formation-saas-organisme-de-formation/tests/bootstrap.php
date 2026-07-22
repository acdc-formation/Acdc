<?php
/**
 * Bootstrap des tests unitaires (logique pure, sans WordPress).
 */

define( 'ACDC_SUPPORT_TESTING', true );

// Autoload Composer si disponible…
$autoload = __DIR__ . '/../vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require $autoload;
}

// …sinon autoloader PSR-4 minimal pour le namespace ACDC\ → src/.
spl_autoload_register(
	function ( $class ) {
		$prefix = 'ACDC\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = __DIR__ . '/../src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);
