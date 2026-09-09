<?php

namespace ACDC\Tests\Support;

use ACDC\Support\Revenue;
use PHPUnit\Framework\TestCase;

/**
 * Tests du calcul du chiffre d'affaires (net d'avoirs, par TVA, par période).
 */
final class RevenueTest extends TestCase {

	private function rows() {
		return array(
			array( 'ht' => 1000.0, 'tva' => 200.0, 'ttc' => 1200.0, 'kind' => 'invoice', 'vat_rate' => 20.0, 'date' => '2026-01-15' ),
			array( 'ht' => 500.0,  'tva' => 27.5,  'ttc' => 527.5,  'kind' => 'invoice', 'vat_rate' => 5.5,  'date' => '2026-02-10' ),
			array( 'ht' => 300.0,  'tva' => 60.0,  'ttc' => 360.0,  'kind' => 'invoice', 'vat_rate' => 20.0, 'date' => '2026-04-01' ),
			// Avoir : se retranche (magnitude positive, signe donné par kind).
			array( 'ht' => 200.0,  'tva' => 40.0,  'ttc' => 240.0,  'kind' => 'avoir',   'vat_rate' => 20.0, 'date' => '2026-04-20' ),
		);
	}

	public function test_totaux_nets_d_avoirs() {
		$s = Revenue::summarize( $this->rows() );
		$this->assertSame( 1600.0, $s['ht'] );   // 1000 + 500 + 300 - 200
		$this->assertSame( 247.5, $s['tva'] );   // 200 + 27.5 + 60 - 40
		$this->assertSame( 1847.5, $s['ttc'] );  // 1200 + 527.5 + 360 - 240
		$this->assertSame( 3, $s['count_invoices'] );
		$this->assertSame( 1, $s['count_avoirs'] );
	}

	public function test_ventilation_par_taux_de_tva() {
		$b = Revenue::byVatRate( $this->rows() );
		$this->assertSame( 1100.0, $b['20']['ht'] ); // 1000 + 300 - 200
		$this->assertSame( 500.0, $b['5.5']['ht'] );
		$this->assertSame( 220.0, $b['20']['tva'] ); // 200 + 60 - 40
	}

	public function test_ventilation_par_mois() {
		$m = Revenue::byPeriod( $this->rows(), 'month' );
		$this->assertSame( array( '2026-01', '2026-02', '2026-04' ), array_keys( $m ) );
		$this->assertSame( 1000.0, $m['2026-01']['ht'] );
		$this->assertSame( 100.0, $m['2026-04']['ht'] ); // 300 - 200
	}

	public function test_ventilation_par_trimestre_et_annee() {
		$q = Revenue::byPeriod( $this->rows(), 'quarter' );
		$this->assertSame( 1500.0, $q['2026-Q1']['ht'] ); // 1000 + 500
		$this->assertSame( 100.0, $q['2026-Q2']['ht'] );  // 300 - 200

		$y = Revenue::byPeriod( $this->rows(), 'year' );
		$this->assertSame( 1600.0, $y['2026']['ht'] );
	}

	public function test_cle_de_periode() {
		$this->assertSame( '2026-07', Revenue::periodKey( '2026-07-23', 'month' ) );
		$this->assertSame( '2026-Q3', Revenue::periodKey( '2026-07-23', 'quarter' ) );
		$this->assertSame( '2026', Revenue::periodKey( '2026-07-23', 'year' ) );
		$this->assertSame( '', Revenue::periodKey( 'date-invalide' ) );
		$this->assertSame( '2026-Q4', Revenue::periodKey( '2026-12-31', 'quarter' ) );
		$this->assertSame( '2026-Q1', Revenue::periodKey( '2026-01-01', 'quarter' ) );
	}

	public function test_entrees_invalides_ignorees() {
		// 'pas-un-tableau' est ignorée (non-tableau) ; la ligne vide compte 0 ; la 3e compte 100.
		$s = Revenue::summarize( array( 'pas-un-tableau', array(), array( 'ht' => 100.0, 'ttc' => 100.0 ) ) );
		$this->assertSame( 100.0, $s['ht'] );
		$this->assertSame( 2, $s['count_invoices'] ); // la ligne vide + la 3e (toutes deux traitées comme factures)
	}
}
