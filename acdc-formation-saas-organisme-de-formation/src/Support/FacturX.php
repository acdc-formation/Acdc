<?php
/**
 * Génération du XML Factur-X (Cross Industry Invoice UN/CEFACT), profil MINIMUM.
 *
 * Le profil MINIMUM est reconnu par l'administration fiscale française dans le cadre de
 * la facturation électronique. Ce XML est destiné à être embarqué dans le PDF/A-3 de la
 * facture (fichier « factur-x.xml »).
 *
 * Classe PURE (produit une chaîne XML) — testable sans WordPress.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class FacturX {

	const PROFILE_MINIMUM = 'urn:factur-x.eu:1p0:minimum';
	const NS_RSM = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';
	const NS_RAM = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';
	const NS_UDT = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

	/**
	 * Construit le XML Factur-X (profil MINIMUM) d'une facture.
	 *
	 * @param array $d {
	 *   @type string $number       Numéro de facture.
	 *   @type string $issue_date   Date d'émission (Y-m-d ou Ymd).
	 *   @type string $currency     Code devise (défaut EUR).
	 *   @type string $type_code    Code type document (défaut 380 = facture ; 381 = avoir).
	 *   @type array  $seller       [name, siret, vat, country].
	 *   @type array  $buyer        [name, country].
	 *   @type float  $tax_basis    Total HT (base d'imposition).
	 *   @type float  $tax_total    Montant total de TVA.
	 *   @type float  $grand_total  Total TTC.
	 *   @type float  $due_payable  Net à payer (défaut = grand_total).
	 * }
	 * @return string XML (chaîne UTF-8).
	 */
	public static function buildMinimumXml( array $d ) {
		$currency  = ! empty( $d['currency'] ) ? (string) $d['currency'] : 'EUR';
		$type_code = ! empty( $d['type_code'] ) ? (string) $d['type_code'] : '380';
		$seller    = isset( $d['seller'] ) && is_array( $d['seller'] ) ? $d['seller'] : array();
		$buyer     = isset( $d['buyer'] ) && is_array( $d['buyer'] ) ? $d['buyer'] : array();

		$dom = new \DOMDocument( '1.0', 'UTF-8' );
		$dom->formatOutput = true;

		$root = $dom->createElementNS( self::NS_RSM, 'rsm:CrossIndustryInvoice' );
		$root->setAttributeNS( 'http://www.w3.org/2000/xmlns/', 'xmlns:ram', self::NS_RAM );
		$root->setAttributeNS( 'http://www.w3.org/2000/xmlns/', 'xmlns:udt', self::NS_UDT );
		$dom->appendChild( $root );

		// --- Contexte : profil MINIMUM ---
		$ctx = $dom->createElement( 'rsm:ExchangedDocumentContext' );
		$guideline = $dom->createElement( 'ram:GuidelineSpecifiedDocumentContextParameter' );
		$guideline->appendChild( $dom->createElement( 'ram:ID', self::PROFILE_MINIMUM ) );
		$ctx->appendChild( $guideline );
		$root->appendChild( $ctx );

		// --- En-tête document ---
		$doc = $dom->createElement( 'rsm:ExchangedDocument' );
		$doc->appendChild( self::el( $dom, 'ram:ID', (string) ( $d['number'] ?? '' ) ) );
		$doc->appendChild( $dom->createElement( 'ram:TypeCode', $type_code ) );
		$issue = $dom->createElement( 'ram:IssueDateTime' );
		$dts = $dom->createElement( 'udt:DateTimeString', self::formatDate( $d['issue_date'] ?? '' ) );
		$dts->setAttribute( 'format', '102' );
		$issue->appendChild( $dts );
		$doc->appendChild( $issue );
		$root->appendChild( $doc );

		// --- Transaction ---
		$tx = $dom->createElement( 'rsm:SupplyChainTradeTransaction' );

		// Agreement : vendeur + acheteur
		$agr = $dom->createElement( 'ram:ApplicableHeaderTradeAgreement' );

		$sellerEl = $dom->createElement( 'ram:SellerTradeParty' );
		$sellerEl->appendChild( self::el( $dom, 'ram:Name', (string) ( $seller['name'] ?? '' ) ) );
		if ( ! empty( $seller['siret'] ) ) {
			$legal = $dom->createElement( 'ram:SpecifiedLegalOrganization' );
			$legalId = self::el( $dom, 'ram:ID', (string) $seller['siret'] );
			$legalId->setAttribute( 'schemeID', '0002' ); // SIRENE.
			$legal->appendChild( $legalId );
			$sellerEl->appendChild( $legal );
		}
		$addr = $dom->createElement( 'ram:PostalTradeAddress' );
		$addr->appendChild( $dom->createElement( 'ram:CountryID', ! empty( $seller['country'] ) ? (string) $seller['country'] : 'FR' ) );
		$sellerEl->appendChild( $addr );
		if ( ! empty( $seller['vat'] ) ) {
			$taxReg = $dom->createElement( 'ram:SpecifiedTaxRegistration' );
			$taxRegId = self::el( $dom, 'ram:ID', (string) $seller['vat'] );
			$taxRegId->setAttribute( 'schemeID', 'VA' );
			$taxReg->appendChild( $taxRegId );
			$sellerEl->appendChild( $taxReg );
		}
		$agr->appendChild( $sellerEl );

		$buyerEl = $dom->createElement( 'ram:BuyerTradeParty' );
		$buyerEl->appendChild( self::el( $dom, 'ram:Name', (string) ( $buyer['name'] ?? '' ) ) );
		$agr->appendChild( $buyerEl );

		$tx->appendChild( $agr );

		// Delivery (vide en MINIMUM)
		$tx->appendChild( $dom->createElement( 'ram:ApplicableHeaderTradeDelivery' ) );

		// Settlement : devise + totaux
		$set = $dom->createElement( 'ram:ApplicableHeaderTradeSettlement' );
		$set->appendChild( $dom->createElement( 'ram:InvoiceCurrencyCode', $currency ) );
		$sum = $dom->createElement( 'ram:SpecifiedTradeSettlementHeaderMonetarySummation' );
		$sum->appendChild( $dom->createElement( 'ram:TaxBasisTotalAmount', self::amount( $d['tax_basis'] ?? 0 ) ) );
		$taxTotal = $dom->createElement( 'ram:TaxTotalAmount', self::amount( $d['tax_total'] ?? 0 ) );
		$taxTotal->setAttribute( 'currencyID', $currency );
		$sum->appendChild( $taxTotal );
		$sum->appendChild( $dom->createElement( 'ram:GrandTotalAmount', self::amount( $d['grand_total'] ?? 0 ) ) );
		$due = isset( $d['due_payable'] ) ? $d['due_payable'] : ( $d['grand_total'] ?? 0 );
		$sum->appendChild( $dom->createElement( 'ram:DuePayableAmount', self::amount( $due ) ) );
		$set->appendChild( $sum );
		$tx->appendChild( $set );

		$root->appendChild( $tx );

		return $dom->saveXML();
	}

	/** Élément texte avec échappement XML correct (via nœud texte). */
	private static function el( \DOMDocument $dom, $name, $text ) {
		$el = $dom->createElement( $name );
		$el->appendChild( $dom->createTextNode( (string) $text ) );
		return $el;
	}

	/** Date au format 102 (AAAAMMJJ). Accepte Y-m-d, Ymd ou d/m/Y. */
	public static function formatDate( $date ) {
		$date = trim( (string) $date );
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $date, $m ) ) {
			return $m[1] . $m[2] . $m[3];
		}
		if ( preg_match( '#^(\d{2})/(\d{2})/(\d{4})$#', $date, $m ) ) {
			return $m[3] . $m[2] . $m[1];
		}
		if ( preg_match( '/^\d{8}$/', $date ) ) {
			return $date;
		}
		return '';
	}

	/** Montant à 2 décimales, séparateur point (norme CII). */
	public static function amount( $value ) {
		return number_format( (float) $value, 2, '.', '' );
	}
}
