<?php

namespace ACDC\Tests\Support;

use ACDC\Support\Tenant;
use PHPUnit\Framework\TestCase;

/**
 * Tests du contexte multi-organismes (isolation SaaS par tenant).
 */
final class TenantTest extends TestCase {

	public function test_normalisation() {
		$this->assertSame( 'acme', Tenant::normalizeId( 'ACME' ) );
		$this->assertSame( 'acme-of', Tenant::normalizeId( 'Acme  OF!' ) );
		$this->assertSame( 'main', Tenant::normalizeId( '' ) );
		$this->assertSame( 'main', Tenant::normalizeId( '---' ) );
		$this->assertSame( 'main', Tenant::normalizeId( array( 'x' ) ) );
	}

	public function test_longueur_bornee() {
		$long = str_repeat( 'a', 50 );
		$this->assertSame( 32, strlen( Tenant::normalizeId( $long ) ) );
	}

	public function test_validation() {
		$this->assertTrue( Tenant::isValidId( 'acme-of' ) );
		$this->assertFalse( Tenant::isValidId( 'Acme OF' ) );
		$this->assertTrue( Tenant::isMain( 'main' ) );
		$this->assertFalse( Tenant::isMain( 'acme' ) );
	}

	public function test_cle_option_retro_compatible_pour_main() {
		// Le tenant principal conserve exactement la clé d'origine (aucune régression).
		$this->assertSame( 'acdc_of_branding', Tenant::optionKey( 'acdc_of_branding', 'main' ) );
	}

	public function test_cle_option_isolee_pour_autre_tenant() {
		$this->assertSame( 'acdc_t_acme__acdc_of_branding', Tenant::optionKey( 'acdc_of_branding', 'acme' ) );
		// Deux tenants distincts → clés distinctes (pas de mélange).
		$this->assertNotSame(
			Tenant::optionKey( 'acdc_of_branding', 'acme' ),
			Tenant::optionKey( 'acdc_of_branding', 'globex' )
		);
	}

	public function test_prefixe_tables() {
		$this->assertSame( 'wp_', Tenant::tablePrefix( 'wp_', 'main' ) );
		$this->assertSame( 'wp_t_acme_', Tenant::tablePrefix( 'wp_', 'acme' ) );
		// Les tirets deviennent des underscores (compatibilité SQL).
		$this->assertSame( 'wp_t_acme_of_', Tenant::tablePrefix( 'wp_', 'acme-of' ) );
	}

	public function test_current_sans_wordpress_est_main() {
		$this->assertSame( 'main', Tenant::current() );
	}
}
