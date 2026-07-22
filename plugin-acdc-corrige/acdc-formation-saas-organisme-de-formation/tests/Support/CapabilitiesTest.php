<?php

namespace ACDC\Tests\Support;

use ACDC\Support\Capabilities;
use PHPUnit\Framework\TestCase;

final class CapabilitiesTest extends TestCase {

	public function test_all_contient_le_pivot() {
		$this->assertContains( Capabilities::PRIMARY, Capabilities::all() );
	}

	public function test_all_sans_doublon() {
		$all = Capabilities::all();
		$this->assertSame( array_values( array_unique( $all ) ), $all );
	}

	public function test_all_non_vide_et_pivot_en_tete() {
		$all = Capabilities::all();
		$this->assertNotEmpty( $all );
		$this->assertSame( Capabilities::PRIMARY, $all[0] );
	}

	public function test_map_administrator_contient_toutes_les_caps() {
		$map = Capabilities::map();
		$this->assertArrayHasKey( 'administrator', $map );
		foreach ( Capabilities::all() as $cap ) {
			$this->assertContains( $cap, $map['administrator'] );
		}
	}

	public function test_pivot_present_dans_chaque_role() {
		foreach ( Capabilities::map() as $role => $caps ) {
			$this->assertContains(
				Capabilities::PRIMARY,
				$caps,
				sprintf( 'Le pivot doit être présent pour le rôle "%s".', $role )
			);
		}
	}

	public function test_methodes_pures_deterministes() {
		$this->assertSame( Capabilities::all(), Capabilities::all() );
		$this->assertSame( Capabilities::map(), Capabilities::map() );
	}
}
