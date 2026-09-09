<?php

namespace ACDC\Tests\Support;

use ACDC\Support\FacturX;
use PHPUnit\Framework\TestCase;

final class FacturXTest extends TestCase {

	private function sampleData() {
		return array(
			'number'      => 'FA-2026-42',
			'issue_date'  => '2026-03-13',
			'currency'    => 'EUR',
			'seller'      => array( 'name' => 'ACDC Formation', 'siret' => '12345678900012', 'vat' => 'FR12345678900', 'country' => 'FR' ),
			'buyer'       => array( 'name' => 'Entreprise Cliente & Fils' ),
			'tax_basis'   => 1200.00,
			'tax_total'   => 240.00,
			'grand_total' => 1440.00,
		);
	}

	private function xml() {
		return FacturX::buildMinimumXml( $this->sampleData() );
	}

	public function test_xml_bien_forme() {
		$dom = new \DOMDocument();
		$this->assertTrue( $dom->loadXML( $this->xml() ), 'Le XML Factur-X doit être bien formé.' );
	}

	public function test_profil_minimum() {
		$this->assertStringContainsString( 'urn:factur-x.eu:1p0:minimum', $this->xml() );
	}

	public function test_numero_et_type() {
		$xml = $this->xml();
		$this->assertStringContainsString( '<ram:ID>FA-2026-42</ram:ID>', $xml );
		$this->assertStringContainsString( '<ram:TypeCode>380</ram:TypeCode>', $xml );
	}

	public function test_type_avoir() {
		$d = $this->sampleData();
		$d['type_code'] = '381';
		$this->assertStringContainsString( '<ram:TypeCode>381</ram:TypeCode>', FacturX::buildMinimumXml( $d ) );
	}

	public function test_date_format_102() {
		$this->assertStringContainsString( 'format="102">20260313<', $this->xml() );
	}

	public function test_totaux_a_deux_decimales() {
		$xml = $this->xml();
		$this->assertStringContainsString( '<ram:TaxBasisTotalAmount>1200.00</ram:TaxBasisTotalAmount>', $xml );
		$this->assertStringContainsString( '<ram:TaxTotalAmount currencyID="EUR">240.00</ram:TaxTotalAmount>', $xml );
		$this->assertStringContainsString( '<ram:GrandTotalAmount>1440.00</ram:GrandTotalAmount>', $xml );
		$this->assertStringContainsString( '<ram:DuePayableAmount>1440.00</ram:DuePayableAmount>', $xml );
	}

	public function test_vendeur_siret_et_tva() {
		$xml = $this->xml();
		$this->assertStringContainsString( '<ram:ID schemeID="0002">12345678900012</ram:ID>', $xml );
		$this->assertStringContainsString( '<ram:ID schemeID="VA">FR12345678900</ram:ID>', $xml );
		$this->assertStringContainsString( '<ram:CountryID>FR</ram:CountryID>', $xml );
	}

	/** L'échappement XML doit protéger les caractères spéciaux (& → &amp;). */
	public function test_echappement_xml() {
		$xml = $this->xml();
		$this->assertStringContainsString( 'Entreprise Cliente &amp; Fils', $xml );
		$this->assertStringNotContainsString( 'Fils </ram:Name></ram:BuyerTradeParty> & ', $xml );
	}

	public function test_siret_absent_pas_de_bloc_legal() {
		$d = $this->sampleData();
		unset( $d['seller']['siret'] );
		$xml = FacturX::buildMinimumXml( $d );
		$this->assertStringNotContainsString( 'SpecifiedLegalOrganization', $xml );
		$this->assertTrue( ( new \DOMDocument() )->loadXML( $xml ) );
	}

	public function test_format_date_variantes() {
		$this->assertSame( '20260313', FacturX::formatDate( '2026-03-13' ) );
		$this->assertSame( '20260313', FacturX::formatDate( '13/03/2026' ) );
		$this->assertSame( '20260313', FacturX::formatDate( '20260313' ) );
		$this->assertSame( '', FacturX::formatDate( 'invalide' ) );
	}

	public function test_amount() {
		$this->assertSame( '1200.00', FacturX::amount( 1200 ) );
		$this->assertSame( '352.80', FacturX::amount( 352.8 ) );
		$this->assertSame( '0.00', FacturX::amount( 'x' ) );
	}
}
