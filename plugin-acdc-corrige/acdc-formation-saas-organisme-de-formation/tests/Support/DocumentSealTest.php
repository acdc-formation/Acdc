<?php

namespace ACDC\Tests\Support;

use ACDC\Support\DocumentSeal;
use PHPUnit\Framework\TestCase;

/**
 * Tests du scellement SHA-256 des documents signés (valeur probante).
 */
final class DocumentSealTest extends TestCase {

	public function test_hash_string_vecteur_connu() {
		// SHA-256("abc") — vecteur de référence NIST.
		$this->assertSame(
			'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad',
			DocumentSeal::hashString( 'abc' )
		);
	}

	public function test_hash_string_type_invalide() {
		$this->assertSame( '', DocumentSeal::hashString( array( 'x' ) ) );
	}

	public function test_hash_file_et_detection_de_modification() {
		$tmp = tempnam( sys_get_temp_dir(), 'acdc_seal_' );
		file_put_contents( $tmp, 'Contrat de formation v1' );
		$h1 = DocumentSeal::hashFile( $tmp );
		$this->assertSame( 64, strlen( $h1 ) );

		// Toute modification du document change l'empreinte → falsification détectable.
		file_put_contents( $tmp, 'Contrat de formation v1 (modifié)' );
		$h2 = DocumentSeal::hashFile( $tmp );
		$this->assertNotSame( $h1, $h2 );
		unlink( $tmp );
	}

	public function test_hash_file_inexistant() {
		$this->assertSame( '', DocumentSeal::hashFile( '/chemin/inexistant/xyz.pdf' ) );
		$this->assertSame( '', DocumentSeal::hashFile( '' ) );
	}

	public function test_matches_temps_constant() {
		$h = DocumentSeal::hashString( 'convention' );
		$this->assertTrue( DocumentSeal::matches( $h, DocumentSeal::hashString( 'convention' ) ) );
		$this->assertFalse( DocumentSeal::matches( $h, DocumentSeal::hashString( 'convention altérée' ) ) );
		$this->assertFalse( DocumentSeal::matches( '', $h ) );
	}

	public function test_pretty() {
		$h = str_repeat( 'ab', 32 ); // 64 caractères.
		$this->assertStringContainsString( ' ', DocumentSeal::pretty( $h ) );
		$this->assertSame( 'court', DocumentSeal::pretty( 'court' ) );
	}
}
