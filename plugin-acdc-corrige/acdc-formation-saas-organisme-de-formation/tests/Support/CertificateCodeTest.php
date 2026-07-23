<?php

namespace ACDC\Tests\Support;

use ACDC\Support\CertificateCode;
use PHPUnit\Framework\TestCase;

/**
 * Tests des attestations vérifiables (référence à clé de contrôle + signature).
 */
final class CertificateCodeTest extends TestCase {

	public function test_reference_et_format() {
		$this->assertSame( 'ACDC-2026-000123', CertificateCode::reference( 2026, 123 ) );
		$this->assertSame( 'ACDC-2026-000123-38', CertificateCode::format( 2026, 123 ) );
	}

	public function test_prefixe_personnalise_nettoye() {
		$this->assertSame( 'OF-2026-000001', CertificateCode::reference( 2026, 1, 'of!' ) );
	}

	public function test_reference_bien_formee_est_validee() {
		$this->assertTrue( CertificateCode::isWellFormed( 'ACDC-2026-000123-38' ) );
	}

	public function test_faute_de_frappe_detectee_par_la_cle() {
		// Un chiffre de la séquence changé, clé inchangée → invalide.
		$this->assertFalse( CertificateCode::isWellFormed( 'ACDC-2026-000124-38' ) );
		// Clé fausse.
		$this->assertFalse( CertificateCode::isWellFormed( 'ACDC-2026-000123-99' ) );
		// Format incorrect.
		$this->assertFalse( CertificateCode::isWellFormed( 'pas-un-code' ) );
	}

	public function test_cle_invariant_iban() {
		// Pour toute année/séquence, la référence formée est bien formée.
		foreach ( array( array( 2024, 1 ), array( 2025, 999999 ), array( 2026, 500000 ) ) as $c ) {
			$this->assertTrue( CertificateCode::isWellFormed( CertificateCode::format( $c[0], $c[1] ) ) );
		}
	}

	public function test_signature_et_verification() {
		$code = CertificateCode::format( 2026, 123 );
		$sig  = CertificateCode::sign( $code, 'secret-de-test' );
		$this->assertSame( '09758cfa2feb', $sig );
		$this->assertTrue( CertificateCode::verify( $code, $sig, 'secret-de-test' ) );
	}

	public function test_signature_rejette_mauvais_secret_ou_jeton() {
		$code = CertificateCode::format( 2026, 123 );
		$sig  = CertificateCode::sign( $code, 'secret-de-test' );
		$this->assertFalse( CertificateCode::verify( $code, $sig, 'autre-secret' ) );
		$this->assertFalse( CertificateCode::verify( $code, 'xxxx', 'secret-de-test' ) );
		$this->assertFalse( CertificateCode::verify( $code, '', 'secret-de-test' ) );
	}

	public function test_signature_vide_si_secret_absent() {
		$this->assertSame( '', CertificateCode::sign( 'ACDC-2026-000123-38', '' ) );
	}
}
