<?php

namespace ACDC\Tests\Support;

use ACDC\Support\SignatureProof;
use PHPUnit\Framework\TestCase;

/**
 * Tests de la preuve de signature (sceau document + horodatage, vérifiable).
 */
final class SignatureProofTest extends TestCase {

	const H = 'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad';

	private function proof() {
		return SignatureProof::build(
			self::H,
			array( 'name' => 'Jean Dupont', 'email' => 'jean@example.fr', 'role' => 'Apprenant', 'extra' => 'ignoré' ),
			1753257600,
			'aabbcc'
		);
	}

	public function test_construction_et_champs() {
		$p = $this->proof();
		$this->assertSame( self::H, $p['doc_hash'] );
		$this->assertSame( 'Jean Dupont', $p['signer']['name'] );
		// Les clés non prévues sont écartées.
		$this->assertArrayNotHasKey( 'extra', $p['signer'] );
		$this->assertNotEmpty( $p['seal'] );
		$this->assertSame( 1753257600, $p['timestamp']['time_unix'] );
	}

	public function test_preuve_valide() {
		$this->assertTrue( SignatureProof::verify( $this->proof(), self::H ) );
	}

	public function test_preuve_non_qualifiee_par_defaut() {
		$this->assertFalse( SignatureProof::isQualified( $this->proof() ) );
	}

	public function test_alteration_du_signataire_detectee() {
		$p = $this->proof();
		$p['signer']['name'] = 'Pirate';
		$this->assertFalse( SignatureProof::verify( $p, self::H ) );
	}

	public function test_alteration_de_l_horodatage_detectee() {
		$p = $this->proof();
		$p['timestamp']['time_unix'] = 1700000000;
		$this->assertFalse( SignatureProof::verify( $p, self::H ) );
	}

	public function test_preuve_pour_un_autre_document_rejetee() {
		$p     = $this->proof();
		$other = str_repeat( 'a', 64 );
		$this->assertFalse( SignatureProof::verify( $p, $other ) );
	}

	public function test_empreinte_invalide_ne_produit_pas_de_preuve() {
		$this->assertSame( array(), SignatureProof::build( 'pas-un-hash' ) );
	}

	public function test_determinisme() {
		$a = $this->proof();
		$b = $this->proof();
		$this->assertSame( $a['seal'], $b['seal'] );
	}
}
