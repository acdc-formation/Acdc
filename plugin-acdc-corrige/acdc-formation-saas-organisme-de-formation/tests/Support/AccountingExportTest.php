<?php

namespace ACDC\Tests\Support;

use ACDC\Support\AccountingExport;
use PHPUnit\Framework\TestCase;

/**
 * Tests de l'export comptable CSV (factures + avoirs, totaux, échappement).
 */
final class AccountingExportTest extends TestCase {

	private function invoices() {
		return array(
			array( 'number' => 'F2026-001', 'date' => '2026-01-15', 'client' => 'Knowell', 'vat_rate' => 20.0, 'ht' => 1000.0, 'tva' => 200.0, 'ttc' => 1200.0, 'kind' => 'invoice' ),
			array( 'number' => 'A2026-001', 'date' => '2026-02-01', 'client' => 'Knowell', 'vat_rate' => 20.0, 'ht' => 200.0, 'tva' => 40.0, 'ttc' => 240.0, 'kind' => 'avoir' ),
		);
	}

	public function test_entetes_presentes() {
		$csv = AccountingExport::toCsv( $this->invoices(), false );
		$first = strtok( $csv, "\r\n" );
		$this->assertSame( 'Numéro;Date;Type;Client;Taux TVA;HT;TVA;TTC', $first );
	}

	public function test_facture_et_avoir_en_negatif() {
		$csv   = AccountingExport::toCsv( $this->invoices(), false );
		$lines = explode( "\r\n", trim( $csv ) );
		// Ligne facture.
		$this->assertSame( 'F2026-001;2026-01-15;Facture;Knowell;20,00;1000,00;200,00;1200,00', $lines[1] );
		// Ligne avoir (montants négatifs).
		$this->assertSame( 'A2026-001;2026-02-01;Avoir;Knowell;20,00;-200,00;-40,00;-240,00', $lines[2] );
	}

	public function test_ligne_de_totaux_nets() {
		$csv   = AccountingExport::toCsv( $this->invoices(), false );
		$lines = explode( "\r\n", trim( $csv ) );
		$this->assertSame( 'TOTAL;;;;;800,00;160,00;960,00', end( $lines ) );
	}

	public function test_bom_utf8_present_par_defaut() {
		$csv = AccountingExport::toCsv( $this->invoices() );
		$this->assertSame( "\xEF\xBB\xBF", substr( $csv, 0, 3 ) );
	}

	public function test_echappement_client_avec_separateur() {
		$csv = AccountingExport::toCsv(
			array( array( 'number' => 'F1', 'date' => '2026-03-01', 'client' => 'Dupont; & Fils', 'vat_rate' => 0.0, 'ht' => 100.0, 'tva' => 0.0, 'ttc' => 100.0 ) ),
			false
		);
		$this->assertStringContainsString( '"Dupont; & Fils"', $csv );
	}

	public function test_nom_de_fichier() {
		$this->assertSame( 'export-comptable-2026-01-01_2026-12-31.csv', AccountingExport::filename( '2026-01-01', '2026-12-31' ) );
		$this->assertSame( 'export-comptable-na_na.csv', AccountingExport::filename( 'x', 'y' ) );
	}
}
