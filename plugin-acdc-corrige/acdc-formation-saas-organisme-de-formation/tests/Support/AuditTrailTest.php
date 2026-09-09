<?php

namespace ACDC\Tests\Support;

use ACDC\Support\AuditTrail;
use PHPUnit\Framework\TestCase;

/**
 * Tests du journal d'audit infalsifiable (chaînage de hash, WORM logique).
 */
final class AuditTrailTest extends TestCase {

	public function test_genesis_est_stable_et_hexadecimal() {
		$g = AuditTrail::genesis();
		$this->assertSame( 64, strlen( $g ) );
		$this->assertSame( $g, AuditTrail::genesis() );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $g );
	}

	public function test_canonicalisation_insensible_a_l_ordre_des_cles() {
		$a = array( 'action' => 'sign', 'actor' => 42, 'target' => 'doc-7' );
		$b = array( 'target' => 'doc-7', 'actor' => 42, 'action' => 'sign' );
		$this->assertSame(
			AuditTrail::canonicalize( $a ),
			AuditTrail::canonicalize( $b )
		);
	}

	public function test_canonicalisation_preserve_l_ordre_des_listes() {
		$a = array( 'items' => array( 'x', 'y', 'z' ) );
		$b = array( 'items' => array( 'z', 'y', 'x' ) );
		$this->assertNotSame(
			AuditTrail::canonicalize( $a ),
			AuditTrail::canonicalize( $b )
		);
	}

	public function test_chain_refuse_un_prev_hash_invalide() {
		$this->assertSame( '', AuditTrail::chain( 'trop-court', array( 'a' => 1 ) ) );
		$this->assertSame( '', AuditTrail::chain( '', array( 'a' => 1 ) ) );
	}

	public function test_chaine_construite_est_integre() {
		$entries = AuditTrail::build(
			array(
				array( 'action' => 'create_session', 'actor' => 1 ),
				array( 'action' => 'sign_emargement', 'actor' => 2, 'session' => 7 ),
				array( 'action' => 'generate_invoice', 'actor' => 1, 'amount' => 1200 ),
			)
		);
		$this->assertCount( 3, $entries );
		$this->assertTrue( AuditTrail::verifyChain( $entries ) );
		$this->assertSame( -1, AuditTrail::firstTamperedIndex( $entries ) );
		// La première ligne pointe bien sur la genèse.
		$this->assertSame( AuditTrail::genesis(), $entries[0]['prev'] );
		// Chaque ligne pointe sur l'empreinte de la précédente.
		$this->assertSame( $entries[0]['hash'], $entries[1]['prev'] );
		$this->assertSame( $entries[1]['hash'], $entries[2]['prev'] );
	}

	public function test_modification_d_une_ligne_casse_la_chaine() {
		$entries = AuditTrail::build(
			array(
				array( 'action' => 'a', 'actor' => 1 ),
				array( 'action' => 'b', 'actor' => 2 ),
				array( 'action' => 'c', 'actor' => 3 ),
			)
		);
		// Un fraudeur modifie le montant/acteur de la 2e ligne mais garde l'ancien hash.
		$entries[1]['data']['actor'] = 999;
		$this->assertFalse( AuditTrail::verifyChain( $entries ) );
		$this->assertSame( 1, AuditTrail::firstTamperedIndex( $entries ) );
	}

	public function test_suppression_d_une_ligne_est_detectee() {
		$entries = AuditTrail::build(
			array(
				array( 'action' => 'a' ),
				array( 'action' => 'b' ),
				array( 'action' => 'c' ),
			)
		);
		// On retire la ligne du milieu → la 3e ne pointe plus sur la bonne empreinte antérieure.
		unset( $entries[1] );
		$entries = array_values( $entries );
		$this->assertFalse( AuditTrail::verifyChain( $entries ) );
		$this->assertSame( 1, AuditTrail::firstTamperedIndex( $entries ) );
	}

	public function test_entree_malformee_est_rejetee() {
		$this->assertSame( 0, AuditTrail::firstTamperedIndex( array( array( 'pasdedata' => true ) ) ) );
	}
}
