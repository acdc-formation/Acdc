<?php

namespace ACDC\Tests\Support;

use ACDC\Support\FacturXPdf;
use PHPUnit\Framework\TestCase;

final class FacturXPdfTest extends TestCase {

	public function test_xmp_bien_forme() {
		$dom = new \DOMDocument();
		$this->assertTrue( $dom->loadXML( FacturXPdf::xmpMetadata() ), 'Le bloc XMP Factur-X doit être du XML/RDF bien formé.' );
	}

	public function test_xmp_contenu_facturx() {
		$xmp = FacturXPdf::xmpMetadata();
		$this->assertStringContainsString( '<fx:DocumentType>INVOICE</fx:DocumentType>', $xmp );
		$this->assertStringContainsString( '<fx:DocumentFileName>factur-x.xml</fx:DocumentFileName>', $xmp );
		$this->assertStringContainsString( '<fx:Version>1.0</fx:Version>', $xmp );
		$this->assertStringContainsString( '<fx:ConformanceLevel>MINIMUM</fx:ConformanceLevel>', $xmp );
		$this->assertStringContainsString( 'urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#', $xmp );
	}

	public function test_xmp_schema_extension_pdfa() {
		$xmp = FacturXPdf::xmpMetadata();
		$this->assertStringContainsString( 'pdfaExtension:schemas', $xmp );
		$this->assertStringContainsString( '<pdfaSchema:prefix>fx</pdfaSchema:prefix>', $xmp );
	}

	public function test_xmp_profil_inconnu_retombe_sur_minimum() {
		$this->assertStringContainsString( '<fx:ConformanceLevel>MINIMUM</fx:ConformanceLevel>', FacturXPdf::xmpMetadata( 'PROFIL_INCONNU' ) );
	}

	public function test_xmp_profil_en16931() {
		$xmp = FacturXPdf::xmpMetadata( 'EN 16931' );
		$this->assertStringContainsString( '<fx:ConformanceLevel>EN 16931</fx:ConformanceLevel>', $xmp );
		$this->assertTrue( ( new \DOMDocument() )->loadXML( $xmp ) );
	}

	public function test_file_spec_cles() {
		$spec = FacturXPdf::associatedFileSpec();
		$this->assertSame( 'factur-x.xml', $spec['name'] );
		$this->assertSame( 'text/xml', $spec['mime'] );
		$this->assertArrayHasKey( 'description', $spec );
		$this->assertNotSame( '', $spec['description'] );
	}

	public function test_file_spec_relationship_data_en_minimum() {
		$this->assertSame( 'Data', FacturXPdf::associatedFileSpec()['relationship'] );
		$this->assertSame( 'Data', FacturXPdf::associatedFileSpec( 'BASIC WL' )['relationship'] );
	}

	public function test_file_spec_relationship_alternative_pour_profils_complets() {
		$this->assertSame( 'Alternative', FacturXPdf::associatedFileSpec( 'EN 16931' )['relationship'] );
		$this->assertSame( 'Alternative', FacturXPdf::associatedFileSpec( 'EXTENDED' )['relationship'] );
	}
}
