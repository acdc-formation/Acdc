<?php

namespace ACDC\Tests\Support;

use ACDC\Support\Paginator;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du bornage de pagination (logique pure, sans WordPress).
 */
final class PaginatorTest extends TestCase {

	/** Total nul : une seule page, page 1, offset 0, aucune navigation. */
	public function test_total_zero() {
		$r = Paginator::normalize( 1, 25, 0 );
		$this->assertSame( 1, $r['page'] );
		$this->assertSame( 25, $r['per_page'] );
		$this->assertSame( 0, $r['total'] );
		$this->assertSame( 1, $r['pages'] );
		$this->assertSame( 0, $r['offset'] );
		$this->assertFalse( $r['has_prev'] );
		$this->assertFalse( $r['has_next'] );
	}

	/** Total nul même si l'on demande une page lointaine : ramené à la page 1. */
	public function test_total_zero_page_lointaine() {
		$r = Paginator::normalize( 9, 25, 0 );
		$this->assertSame( 1, $r['page'] );
		$this->assertSame( 1, $r['pages'] );
		$this->assertSame( 0, $r['offset'] );
		$this->assertFalse( $r['has_next'] );
	}

	/** Une page pleine : exactement per_page éléments tiennent sur une seule page. */
	public function test_une_page_pleine() {
		$r = Paginator::normalize( 1, 25, 25 );
		$this->assertSame( 1, $r['pages'] );
		$this->assertSame( 1, $r['page'] );
		$this->assertSame( 0, $r['offset'] );
		$this->assertFalse( $r['has_prev'] );
		$this->assertFalse( $r['has_next'] );
	}

	/** Un élément de plus qu'une page pleine crée une seconde page. */
	public function test_deux_pages() {
		$r = Paginator::normalize( 1, 25, 26 );
		$this->assertSame( 2, $r['pages'] );
		$this->assertFalse( $r['has_prev'] );
		$this->assertTrue( $r['has_next'] );
	}

	/** Page au-delà du maximum : ramenée à la dernière page réelle. */
	public function test_page_au_dela_du_max_ramenee() {
		$r = Paginator::normalize( 99, 25, 60 );
		$this->assertSame( 3, $r['pages'], '60 / 25 → 3 pages.' );
		$this->assertSame( 3, $r['page'], 'La page demandée (99) doit être ramenée à 3.' );
		$this->assertSame( 50, $r['offset'], 'Offset de la page 3 = (3-1)*25.' );
		$this->assertTrue( $r['has_prev'] );
		$this->assertFalse( $r['has_next'] );
	}

	/** per_page trop grand : borné à 200. */
	public function test_per_page_trop_grand_clamp() {
		$r = Paginator::normalize( 1, 1000, 500 );
		$this->assertSame( 200, $r['per_page'], 'per_page doit être borné à 200.' );
		$this->assertSame( 3, $r['pages'], '500 / 200 → 3 pages.' );
	}

	/** per_page nul ou négatif : retombe sur le défaut (25). */
	public function test_per_page_hors_bornes_defaut() {
		$this->assertSame( 25, Paginator::normalize( 1, 0, 10 )['per_page'], 'per_page 0 → défaut 25.' );
		$this->assertSame( 25, Paginator::normalize( 1, -5, 10 )['per_page'], 'per_page négatif → défaut 25.' );
		$this->assertSame( 25, Paginator::normalize( 1, 'abc', 10 )['per_page'], 'per_page non numérique → défaut 25.' );
	}

	/** per_page valide dans les bornes : conservé tel quel. */
	public function test_per_page_valide_conserve() {
		$this->assertSame( 1, Paginator::normalize( 1, 1, 10 )['per_page'], 'Borne basse valide = 1.' );
		$this->assertSame( 50, Paginator::normalize( 1, 50, 500 )['per_page'] );
		$this->assertSame( 200, Paginator::normalize( 1, 200, 500 )['per_page'], 'Borne haute valide = 200.' );
	}

	/** Calcul de l'offset pour plusieurs pages. */
	public function test_calcul_offset() {
		$this->assertSame( 0, Paginator::normalize( 1, 25, 100 )['offset'] );
		$this->assertSame( 25, Paginator::normalize( 2, 25, 100 )['offset'] );
		$this->assertSame( 75, Paginator::normalize( 4, 25, 100 )['offset'] );
		$this->assertSame( 20, Paginator::normalize( 3, 10, 100 )['offset'] );
	}

	/** has_prev / has_next sur une page intermédiaire. */
	public function test_has_prev_has_next_page_intermediaire() {
		$r = Paginator::normalize( 2, 25, 100 );
		$this->assertSame( 4, $r['pages'] );
		$this->assertTrue( $r['has_prev'], 'La page 2 a une précédente.' );
		$this->assertTrue( $r['has_next'], 'La page 2 a une suivante.' );
	}

	/** has_prev / has_next sur la dernière page. */
	public function test_has_prev_has_next_derniere_page() {
		$r = Paginator::normalize( 4, 25, 100 );
		$this->assertSame( 4, $r['page'] );
		$this->assertTrue( $r['has_prev'] );
		$this->assertFalse( $r['has_next'], 'La dernière page n\'a pas de suivante.' );
		$this->assertSame( 75, $r['offset'] );
	}

	/** Page négative ou nulle : ramenée à 1. */
	public function test_page_inferieure_a_un() {
		$this->assertSame( 1, Paginator::normalize( 0, 25, 100 )['page'] );
		$this->assertSame( 1, Paginator::normalize( -3, 25, 100 )['page'] );
	}

	/** Total négatif : traité comme 0 (une page, aucune navigation). */
	public function test_total_negatif() {
		$r = Paginator::normalize( 1, 25, -10 );
		$this->assertSame( 0, $r['total'] );
		$this->assertSame( 1, $r['pages'] );
		$this->assertFalse( $r['has_next'] );
	}
}
