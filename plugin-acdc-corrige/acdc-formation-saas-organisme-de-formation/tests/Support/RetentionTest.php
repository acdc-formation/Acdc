<?php

namespace ACDC\Tests\Support;

use ACDC\Support\Retention;
use PHPUnit\Framework\TestCase;

/**
 * Tests de la politique de rétention RGPD (durées + détection des données à purger).
 */
final class RetentionTest extends TestCase {

	public function test_durees_par_categorie() {
		$this->assertSame( 3, Retention::yearsFor( 'prospect' ) );
		$this->assertSame( 5, Retention::yearsFor( 'learner' ) );
		$this->assertSame( 10, Retention::yearsFor( 'accounting' ) );
	}

	public function test_categorie_inconnue_retient_la_duree_la_plus_longue() {
		// Prudence : ne pas purger trop tôt une catégorie non répertoriée.
		$this->assertSame( 10, Retention::yearsFor( 'inconnue' ) );
	}

	public function test_politique_personnalisee() {
		$this->assertSame( 1, Retention::yearsFor( 'prospect', array( 'prospect' => 1 ) ) );
	}

	public function test_date_de_coupure() {
		$this->assertSame( '2023-07-23', Retention::cutoffDate( 3, '2026-07-23' ) );
		$this->assertSame( '2016-07-23', Retention::cutoffDate( 10, '2026-07-23' ) );
	}

	public function test_coupure_annee_bissextile() {
		// 29 février -1 an : 2023 n'est pas bissextile → PHP recale au 1er mars (comportement natif stable).
		$this->assertSame( '2023-03-01', Retention::cutoffDate( 1, '2024-02-29' ) );
	}

	public function test_purge_selon_anciennete() {
		// Prospect (3 ans) au 2026-07-23 → coupure 2023-07-23.
		$this->assertTrue( Retention::isPurgeable( '2022-01-01', 'prospect', '2026-07-23' ) );
		$this->assertFalse( Retention::isPurgeable( '2025-01-01', 'prospect', '2026-07-23' ) );
		// Pièce comptable (10 ans) : la même date de 2022 est encore à conserver.
		$this->assertFalse( Retention::isPurgeable( '2022-01-01', 'accounting', '2026-07-23' ) );
	}

	public function test_borne_de_coupure_incluse() {
		// À la date de coupure exacte → purgeable.
		$this->assertTrue( Retention::isPurgeable( '2023-07-23', 'prospect', '2026-07-23' ) );
	}

	public function test_partition_keep_purge() {
		$records = array(
			array( 'id' => 1, 'date' => '2021-05-01' ), // > 3 ans → purge
			array( 'id' => 2, 'date' => '2025-05-01' ), // < 3 ans → keep
			array( 'id' => 3, 'date' => '2020-01-01' ), // purge
		);
		$res = Retention::partition( $records, 'prospect', '2026-07-23' );
		$this->assertCount( 2, $res['purge'] );
		$this->assertCount( 1, $res['keep'] );
		$this->assertSame( 2, $res['keep'][0]['id'] );
	}

	public function test_date_invalide_non_purgeable() {
		$this->assertFalse( Retention::isPurgeable( '2026-02-31', 'prospect', '2026-07-23' ) );
		$this->assertFalse( Retention::isPurgeable( 'n/a', 'prospect', '2026-07-23' ) );
	}
}
