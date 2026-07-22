<?php

namespace ACDC\Tests\Support;

use ACDC\Support\SessionFingerprint;
use PHPUnit\Framework\TestCase;

/**
 * Tests de l'empreinte de session (anti-vol de cookie) — liaison IP/User-Agent.
 */
final class SessionFingerprintTest extends TestCase {

	const UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36';

	public function test_meme_prefixe_ipv4_et_meme_ua_correspond() {
		// Même /24 (dernier octet différent) + même UA → session légitime.
		$this->assertTrue(
			SessionFingerprint::matches( '192.168.1.10', self::UA, '192.168.1.250', self::UA )
		);
	}

	public function test_prefixe_ipv4_different_ne_correspond_pas() {
		$this->assertFalse(
			SessionFingerprint::matches( '192.168.1.10', self::UA, '192.168.2.10', self::UA )
		);
	}

	public function test_user_agent_different_ne_correspond_pas() {
		$this->assertFalse(
			SessionFingerprint::matches( '192.168.1.10', self::UA, '192.168.1.10', 'curl/8.4.0' )
		);
	}

	public function test_session_legacy_sans_empreinte_est_acceptee() {
		// ip stockée vide → session créée avant l'empreinte : toujours acceptée.
		$this->assertTrue(
			SessionFingerprint::matches( '', '', '203.0.113.5', self::UA )
		);
		// ua stocké vide seul → également accepté.
		$this->assertTrue(
			SessionFingerprint::matches( '203.0.113.5', '', '203.0.113.5', self::UA )
		);
	}

	public function test_prefixe_ipv6() {
		// Les 4 premiers groupes identiques (suffixe différent) → même préfixe.
		$this->assertSame(
			SessionFingerprint::ipPrefix( '2001:db8:85a3:0:0:8a2e:370:7334' ),
			SessionFingerprint::ipPrefix( '2001:db8:85a3:0:ffff:1:2:3' )
		);
		$this->assertTrue(
			SessionFingerprint::matches(
				'2001:db8:85a3::8a2e:370:7334',
				self::UA,
				'2001:db8:85a3::1',
				self::UA
			)
		);
		// 4e groupe différent → préfixes distincts.
		$this->assertFalse(
			SessionFingerprint::matches(
				'2001:db8:85a3:1::1',
				self::UA,
				'2001:db8:85a3:2::1',
				self::UA
			)
		);
	}

	public function test_ip_prefix_ipv4() {
		$this->assertSame( '192.168.1', SessionFingerprint::ipPrefix( '192.168.1.42' ) );
	}

	public function test_ip_prefix_chaine_non_ip_inchangee() {
		$this->assertSame( 'inconnue', SessionFingerprint::ipPrefix( 'inconnue' ) );
		$this->assertSame( '', SessionFingerprint::ipPrefix( '' ) );
	}

	public function test_ua_hash_forme() {
		$this->assertSame( substr( sha1( self::UA ), 0, 16 ), SessionFingerprint::uaHash( self::UA ) );
		$this->assertSame( 16, strlen( SessionFingerprint::uaHash( self::UA ) ) );
	}
}
