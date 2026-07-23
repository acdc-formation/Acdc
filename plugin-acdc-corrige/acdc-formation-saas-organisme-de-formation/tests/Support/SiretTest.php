<?php

namespace ACDC\Tests\Support;

use ACDC\Support\Siret;
use PHPUnit\Framework\TestCase;

/**
 * Tests de validation/formatage SIRET & SIREN (mentions légales des documents).
 */
final class SiretTest extends TestCase {

	/** SIRET réel de l'organisme (ACDC Formation). */
	const SIRET = '40510990100042';

	public function test_siret_reel_est_valide() {
		$this->assertTrue( Siret::isValidSiret( self::SIRET ) );
		$this->assertTrue( Siret::isValidSiret( '405 109 901 00042' ) );
	}

	public function test_siren_extrait_est_valide() {
		$this->assertSame( '405109901', Siret::siren( self::SIRET ) );
		$this->assertSame( '00042', Siret::nic( self::SIRET ) );
		$this->assertTrue( Siret::isValidSiren( Siret::siren( self::SIRET ) ) );
	}

	public function test_formatage() {
		$this->assertSame( '405 109 901 00042', Siret::formatSiret( self::SIRET ) );
		$this->assertSame( '405 109 901', Siret::formatSiren( '405109901' ) );
	}

	public function test_normalisation() {
		$this->assertSame( self::SIRET, Siret::normalize( '405-109.901 00042' ) );
	}

	public function test_siret_invalide_est_rejete() {
		// Bonne longueur mais clé de Luhn fausse.
		$this->assertFalse( Siret::isValidSiret( '40510990100043' ) );
		// Mauvaise longueur.
		$this->assertFalse( Siret::isValidSiret( '405109901' ) );
		$this->assertFalse( Siret::isValidSiret( '' ) );
	}

	public function test_siren_invalide_est_rejete() {
		$this->assertFalse( Siret::isValidSiren( '405109902' ) );
		$this->assertFalse( Siret::isValidSiren( '4051099' ) );
	}

	public function test_exception_la_poste() {
		// SIREN La Poste : ne satisfait pas Luhn mais somme des chiffres multiple de 5.
		$this->assertTrue( Siret::isValidSiren( '356000000' ) );
		$this->assertTrue( Siret::isValidSiret( '35600000000010' ) );
	}

	public function test_format_renvoie_normalise_si_longueur_incorrecte() {
		$this->assertSame( '12345', Siret::formatSiret( '12345' ) );
		$this->assertSame( '', Siret::siren( '12345' ) );
	}
}
