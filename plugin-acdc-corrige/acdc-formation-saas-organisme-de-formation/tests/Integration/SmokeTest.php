<?php
/**
 * LOT-1A — Test « fumée » NON MÉTIER.
 *
 * Objectif : prouver que le harnais d'intégration démarre, que WordPress est
 * chargé et que le plugin s'inclut sans erreur fatale. Aucune assertion métier
 * (le schéma et les comportements sont testés en LOT-1B).
 */

namespace ACDC\Tests\Integration;

use WP_UnitTestCase;

final class SmokeTest extends WP_UnitTestCase {

	/** WordPress est bien chargé dans l'environnement de test. */
	public function test_wordpress_est_charge() {
		$this->assertTrue( defined( 'ABSPATH' ), 'ABSPATH doit être défini (WordPress chargé).' );
		$this->assertTrue( function_exists( 'do_action' ), 'Les fonctions WordPress doivent être disponibles.' );
	}

	/** Le plugin s'est inclus : sa constante de version est définie. */
	public function test_plugin_charge_sans_fatal() {
		$this->assertTrue( defined( 'ACDC_OF_SAAS_VERSION' ), 'La constante ACDC_OF_SAAS_VERSION doit être définie.' );
		$this->assertNotEmpty( ACDC_OF_SAAS_VERSION, 'La version du plugin ne doit pas être vide.' );
	}

	/** La classe principale du plugin est disponible. */
	public function test_classe_principale_disponible() {
		$this->assertTrue(
			class_exists( 'ACDC_Formation_SAAS_Plugin' ),
			'La classe principale ACDC_Formation_SAAS_Plugin doit être chargée.'
		);
	}

	/** Garde-fou : le réseau sortant est bien neutralisé pendant les tests. */
	public function test_reseau_sortant_bloque() {
		$response = wp_remote_get( 'https://example.invalid/acdc-test' );
		$this->assertInstanceOf(
			'WP_Error',
			$response,
			'Tout appel HTTP sortant doit être bloqué (WP_Error) pendant les tests.'
		);
	}
}
