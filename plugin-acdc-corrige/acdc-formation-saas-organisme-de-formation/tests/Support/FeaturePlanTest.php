<?php

namespace ACDC\Tests\Support;

use ACDC\Support\FeaturePlan;
use PHPUnit\Framework\TestCase;

/**
 * Tests des formules & options activables (socle SaaS multi-formules).
 */
final class FeaturePlanTest extends TestCase {

	public function test_formules_existent() {
		$this->assertTrue( FeaturePlan::exists( 'solo' ) );
		$this->assertTrue( FeaturePlan::exists( 'pro' ) );
		$this->assertTrue( FeaturePlan::exists( 'business' ) );
		$this->assertFalse( FeaturePlan::exists( 'inconnue' ) );
	}

	public function test_fonctionnalites_incluses() {
		$this->assertTrue( FeaturePlan::can( 'solo', 'billing' ) );
		$this->assertFalse( FeaturePlan::can( 'solo', 'signature' ) );
		$this->assertTrue( FeaturePlan::can( 'pro', 'signature' ) );
		$this->assertTrue( FeaturePlan::can( 'business', 'lms' ) );
		$this->assertFalse( FeaturePlan::can( 'pro', 'lms' ) );
	}

	public function test_montee_en_gamme_est_inclusive() {
		// Tout ce qu'a Solo est inclus dans Pro ; tout ce qu'a Pro est inclus dans Business.
		foreach ( FeaturePlan::features( 'solo' ) as $f ) {
			$this->assertTrue( FeaturePlan::can( 'pro', $f ), "pro devrait inclure $f" );
		}
		foreach ( FeaturePlan::features( 'pro' ) as $f ) {
			$this->assertTrue( FeaturePlan::can( 'business', $f ), "business devrait inclure $f" );
		}
	}

	public function test_formule_inconnue_sans_fonctionnalite() {
		$this->assertSame( array(), FeaturePlan::features( 'inconnue' ) );
		$this->assertFalse( FeaturePlan::can( 'inconnue', 'billing' ) );
	}

	public function test_limites() {
		$this->assertSame( 200, FeaturePlan::limit( 'solo', 'learners' ) );
		$this->assertSame( 2000, FeaturePlan::limit( 'pro', 'learners' ) );
		$this->assertSame( -1, FeaturePlan::limit( 'business', 'learners' ) );
		$this->assertSame( 42, FeaturePlan::limit( 'solo', 'inexistant', 42 ) );
	}

	public function test_within_limit() {
		$this->assertTrue( FeaturePlan::withinLimit( 'solo', 'learners', 200 ) );
		$this->assertFalse( FeaturePlan::withinLimit( 'solo', 'learners', 201 ) );
		// Business = illimité.
		$this->assertTrue( FeaturePlan::withinLimit( 'business', 'learners', 999999 ) );
	}

	public function test_libelle() {
		$this->assertSame( 'Pro', FeaturePlan::label( 'pro' ) );
		$this->assertSame( 'inconnue', FeaturePlan::label( 'inconnue' ) );
	}
}
