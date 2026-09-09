<?php

namespace ACDC\Tests\Support;

use ACDC\Support\SecurityHeaders;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersTest extends TestCase {

	public function test_csp_contient_les_directives_cles() {
		$csp = SecurityHeaders::contentSecurityPolicy();
		$this->assertStringContainsString( "frame-ancestors 'self'", $csp, 'Anti-clickjacking requis.' );
		$this->assertStringContainsString( "object-src 'none'", $csp );
		$this->assertStringContainsString( "form-action 'self'", $csp );
		$this->assertStringContainsString( "default-src 'self'", $csp );
	}

	public function test_headers_de_base() {
		$h = SecurityHeaders::forPublicDocument( false, false );
		$this->assertSame( 'SAMEORIGIN', $h['X-Frame-Options'] );
		$this->assertSame( 'nosniff', $h['X-Content-Type-Options'] );
		$this->assertSame( 'no-referrer', $h['Referrer-Policy'] );
		$this->assertArrayHasKey( 'Content-Security-Policy', $h );
	}

	public function test_hsts_absent_par_defaut() {
		$h = SecurityHeaders::forPublicDocument( true, false );
		$this->assertArrayNotHasKey( 'Strict-Transport-Security', $h, 'HSTS opt-in uniquement.' );
	}

	public function test_hsts_absent_sans_ssl_meme_si_active() {
		$h = SecurityHeaders::forPublicDocument( false, true );
		$this->assertArrayNotHasKey( 'Strict-Transport-Security', $h, 'Pas de HSTS hors HTTPS.' );
	}

	public function test_hsts_present_si_ssl_et_active() {
		$h = SecurityHeaders::forPublicDocument( true, true );
		$this->assertArrayHasKey( 'Strict-Transport-Security', $h );
		$this->assertStringContainsString( 'max-age=', $h['Strict-Transport-Security'] );
	}
}
