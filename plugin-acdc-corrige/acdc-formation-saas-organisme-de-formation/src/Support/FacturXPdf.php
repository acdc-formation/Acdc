<?php
/**
 * Métadonnées PDF/A-3 Factur-X : bloc XMP et descripteur de la pièce jointe factur-x.xml.
 *
 * La norme Factur-X impose, en plus de l'embarquement du fichier « factur-x.xml » en
 * pièce jointe PDF/A-3 (AFRelationship), un bloc XMP dédié (namespace fx) déclarant le
 * type de document, le nom du fichier embarqué, la version et le profil de conformité,
 * ainsi que le schéma d'extension PDF/A correspondant.
 *
 * Classe PURE (produit des chaînes / tableaux) — testable sans WordPress ni mPDF.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class FacturXPdf {

	/** Nom normé du fichier XML embarqué. */
	const XML_FILENAME = 'factur-x.xml';

	/** Namespace XMP Factur-X (préfixe fx). */
	const FX_NS = 'urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#';

	/** Version de la norme Factur-X. */
	const FX_VERSION = '1.0';

	/** Profils de conformité reconnus (valeur XMP fx:ConformanceLevel). */
	const PROFILES = array( 'MINIMUM', 'BASIC WL', 'BASIC', 'EN 16931', 'EXTENDED' );

	/**
	 * Bloc XMP Factur-X (RDF bien formé), racine <rdf:RDF>.
	 *
	 * Contient le schéma d'extension PDF/A (pdfaExtension) déclarant les propriétés fx,
	 * puis la description fx elle-même (DocumentType=INVOICE, DocumentFileName=factur-x.xml,
	 * Version=1.0, ConformanceLevel=<profil>).
	 *
	 * NB : pour une injection via mPDF SetAdditionalXmpRdf(), retirer l'enveloppe
	 * <rdf:RDF> (mPDF ré-enveloppe le fragment dans son propre élément rdf:RDF).
	 *
	 * @param string $profile Profil Factur-X (défaut MINIMUM ; retombe sur MINIMUM si inconnu).
	 * @return string XML/RDF bien formé.
	 */
	public static function xmpMetadata( string $profile = 'MINIMUM' ): string {
		$profile = strtoupper( trim( $profile ) );
		if ( ! in_array( $profile, self::PROFILES, true ) ) {
			$profile = 'MINIMUM';
		}
		$ns      = self::FX_NS;
		$file    = self::XML_FILENAME;
		$version = self::FX_VERSION;

		return <<<XMP
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:about="" xmlns:pdfaExtension="http://www.aiim.org/pdfa/ns/extension/" xmlns:pdfaSchema="http://www.aiim.org/pdfa/ns/schema#" xmlns:pdfaProperty="http://www.aiim.org/pdfa/ns/property#">
    <pdfaExtension:schemas>
      <rdf:Bag>
        <rdf:li rdf:parseType="Resource">
          <pdfaSchema:schema>Factur-X PDFA Extension Schema</pdfaSchema:schema>
          <pdfaSchema:namespaceURI>{$ns}</pdfaSchema:namespaceURI>
          <pdfaSchema:prefix>fx</pdfaSchema:prefix>
          <pdfaSchema:property>
            <rdf:Seq>
              <rdf:li rdf:parseType="Resource">
                <pdfaProperty:name>DocumentFileName</pdfaProperty:name>
                <pdfaProperty:valueType>Text</pdfaProperty:valueType>
                <pdfaProperty:category>external</pdfaProperty:category>
                <pdfaProperty:description>The name of the embedded XML document</pdfaProperty:description>
              </rdf:li>
              <rdf:li rdf:parseType="Resource">
                <pdfaProperty:name>DocumentType</pdfaProperty:name>
                <pdfaProperty:valueType>Text</pdfaProperty:valueType>
                <pdfaProperty:category>external</pdfaProperty:category>
                <pdfaProperty:description>The type of the hybrid document in capital letters, e.g. INVOICE or ORDER</pdfaProperty:description>
              </rdf:li>
              <rdf:li rdf:parseType="Resource">
                <pdfaProperty:name>Version</pdfaProperty:name>
                <pdfaProperty:valueType>Text</pdfaProperty:valueType>
                <pdfaProperty:category>external</pdfaProperty:category>
                <pdfaProperty:description>The actual version of the standard applying to the embedded XML document</pdfaProperty:description>
              </rdf:li>
              <rdf:li rdf:parseType="Resource">
                <pdfaProperty:name>ConformanceLevel</pdfaProperty:name>
                <pdfaProperty:valueType>Text</pdfaProperty:valueType>
                <pdfaProperty:category>external</pdfaProperty:category>
                <pdfaProperty:description>The conformance level of the embedded XML document</pdfaProperty:description>
              </rdf:li>
            </rdf:Seq>
          </pdfaSchema:property>
        </rdf:li>
      </rdf:Bag>
    </pdfaExtension:schemas>
  </rdf:Description>
  <rdf:Description rdf:about="" xmlns:fx="{$ns}">
    <fx:DocumentType>INVOICE</fx:DocumentType>
    <fx:DocumentFileName>{$file}</fx:DocumentFileName>
    <fx:Version>{$version}</fx:Version>
    <fx:ConformanceLevel>{$profile}</fx:ConformanceLevel>
  </rdf:Description>
</rdf:RDF>
XMP;
	}

	/**
	 * Métadonnées de la pièce jointe factur-x.xml (fileSpec PDF/A-3).
	 *
	 * En profils MINIMUM et BASIC WL, le XML n'est pas une facture complète lisible :
	 * la relation AFRelationship normée est « Data » ; « Alternative » pour les autres.
	 *
	 * @param string $profile Profil Factur-X (défaut MINIMUM).
	 * @return array{name:string, mime:string, description:string, relationship:string}
	 */
	public static function associatedFileSpec( string $profile = 'MINIMUM' ): array {
		$profile = strtoupper( trim( $profile ) );
		if ( ! in_array( $profile, self::PROFILES, true ) ) {
			$profile = 'MINIMUM';
		}
		$relationship = in_array( $profile, array( 'MINIMUM', 'BASIC WL' ), true ) ? 'Data' : 'Alternative';

		return array(
			'name'         => self::XML_FILENAME,
			'mime'         => 'text/xml',
			'description'  => 'Factur-X invoice data (profil ' . $profile . ')',
			'relationship' => $relationship,
		);
	}
}
