<?php

namespace ACDC\Tests\Support;

use ACDC\Support\Timestamp;
use PHPUnit\Framework\TestCase;

/**
 * Tests de l'horodatage scellé (jeton local, prêt pour la qualification eIDAS).
 */
final class TimestampTest extends TestCase {

	/** Empreinte SHA-256 d'exemple (vecteur NIST de "abc"). */
	const H = 'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad';

	public function test_local_token_structure_et_champs() {
		$t = Timestamp::localToken( self::H, 1753257600, 'aabbcc' );
		$this->assertSame( 1, $t['version'] );
		$this->assertSame( 'sha256', $t['algo'] );
		$this->assertSame( self::H, $t['data_hash'] );
		$this->assertSame( 1753257600, $t['time_unix'] );
		$this->assertSame( '2025-07-23T08:00:00Z', $t['time_utc'] );
		$this->assertFalse( $t['qualified'] );
		$this->assertSame( 'local', $t['provider'] );
		$this->assertNotEmpty( $t['seal'] );
	}

	public function test_token_valide_est_verifie() {
		$t = Timestamp::localToken( self::H, 1753257600, 'aabbcc' );
		$this->assertTrue( Timestamp::verify( $t, self::H ) );
	}

	public function test_seal_est_deterministe() {
		$a = Timestamp::localToken( self::H, 1753257600, 'aabbcc' );
		$b = Timestamp::localToken( self::H, 1753257600, 'aabbcc' );
		$this->assertSame( $a['seal'], $b['seal'] );
	}

	public function test_retouche_de_l_heure_casse_le_sceau() {
		$t = Timestamp::localToken( self::H, 1753257600, 'aabbcc' );
		// Un fraudeur antidate le jeton mais garde l'ancien sceau.
		$t['time_unix'] = 1700000000;
		$t['time_utc']  = '2023-11-14T22:13:20Z';
		$this->assertFalse( Timestamp::verify( $t, self::H ) );
	}

	public function test_jeton_pour_une_autre_empreinte_est_rejete() {
		$t     = Timestamp::localToken( self::H, 1753257600, 'aabbcc' );
		$other = str_repeat( '0', 64 );
		$this->assertFalse( Timestamp::verify( $t, $other ) );
	}

	public function test_empreinte_invalide_ne_produit_pas_de_jeton() {
		$this->assertSame( array(), Timestamp::localToken( 'pas-un-hash' ) );
		$this->assertSame( array(), Timestamp::create( 'xyz' ) );
	}

	public function test_create_sans_wordpress_retombe_sur_local() {
		// En contexte de test (apply_filters absent), create() = horodatage local scellé.
		$t = Timestamp::create( self::H );
		$this->assertTrue( Timestamp::verify( $t, self::H ) );
		$this->assertFalse( Timestamp::isQualified( $t ) );
	}

	public function test_libelle_de_niveau() {
		$local = Timestamp::localToken( self::H, 1753257600, 'aabbcc' );
		$this->assertStringContainsString( 'non qualifié', Timestamp::levelLabel( $local ) );

		$qualif = $local;
		$qualif['qualified'] = true;
		$this->assertStringContainsString( 'qualifié (eIDAS)', Timestamp::levelLabel( $qualif ) );
	}
}
