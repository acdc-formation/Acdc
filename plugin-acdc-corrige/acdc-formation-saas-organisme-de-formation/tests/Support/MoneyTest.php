<?php

namespace ACDC\Tests\Support;

use ACDC\Support\Money;
use PHPUnit\Framework\TestCase;

/**
 * Tests de non-régression du calcul HT/TVA/TTC (bug H1 corrigé en 3.25.101 :
 * le Total HT devait inclure les frais annexes).
 */
final class MoneyTest extends TestCase {

	public function test_totaux_sans_frais() {
		$r = Money::invoiceTotals( 1000, 0, 0, 0, 20 );
		$this->assertSame( 1000.0, $r['ht'] );
		$this->assertSame( 200.0, $r['tva'] );
		$this->assertSame( 1200.0, $r['ttc'] );
	}

	/** Cœur du bug H1 : les frais doivent être dans le HT, et HT + TVA == TTC. */
	public function test_le_ht_inclut_les_frais() {
		$r = Money::invoiceTotals( 1000, 200, 0, 0, 20 );
		$this->assertSame( 1200.0, $r['ht'], 'Le HT doit inclure les frais de transport.' );
		$this->assertSame( 240.0, $r['tva'], 'La TVA doit porter sur le HT frais inclus.' );
		$this->assertSame( 1440.0, $r['ttc'] );
		$this->assertEqualsWithDelta( $r['ttc'], $r['ht'] + $r['tva'], 0.001, 'HT + TVA doit égaler TTC.' );
	}

	public function test_tous_les_frais_cumules() {
		$r = Money::invoiceTotals( 1000, 200, 150, 50, 20 );
		$this->assertSame( 1400.0, $r['ht'] );
		$this->assertSame( 280.0, $r['tva'] );
		$this->assertSame( 1680.0, $r['ttc'] );
	}

	public function test_tva_zero() {
		$r = Money::invoiceTotals( 588, 0, 0, 0, 0 );
		$this->assertSame( 588.0, $r['ht'] );
		$this->assertSame( 0.0, $r['tva'] );
		$this->assertSame( 588.0, $r['ttc'] );
	}

	public function test_arrondi_deux_decimales() {
		$r = Money::invoiceTotals( 294.00, 0, 0, 0, 20 );
		$this->assertSame( 58.80, $r['tva'] );
		$this->assertSame( 352.80, $r['ttc'] );
	}

	public function test_coherence_invariante_ht_tva_ttc() {
		foreach ( array( array( 333.33, 66.66, 12.5, 3 ), array( 999.99, 0, 0, 5.5 ), array( 1234.56, 78.9, 0, 20 ) ) as $c ) {
			$r = Money::invoiceTotals( $c[0], $c[1], $c[2], $c[3], $c[4] ?? 20 );
			$this->assertEqualsWithDelta( $r['ttc'], round( $r['ht'] + $r['tva'], 2 ), 0.011 );
		}
	}

	public function test_extra_lines_total() {
		$this->assertSame( 150.0, Money::extraLinesTotal( array( array( 'total_ht' => 100 ), array( 'total_ht' => 50 ) ) ) );
		$this->assertSame( 0.0, Money::extraLinesTotal( 'invalide' ) );
		$this->assertSame( 0.0, Money::extraLinesTotal( array( array( 'x' => 1 ) ) ) );
	}
}
