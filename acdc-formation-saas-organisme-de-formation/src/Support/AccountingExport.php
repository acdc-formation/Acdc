<?php
/**
 * Export comptable CSV — factures internes prêtes à transmettre (comptable / Tiime).
 *
 * Produit un fichier CSV propre et déterministe à partir des factures internes (au format
 * français : séparateur « ; », décimale « , », encodage UTF-8). Sert à remettre le détail du
 * chiffre d'affaires au comptable ou à alimenter un outil tiers (Tiime) en attendant un
 * connecteur dédié. Les **avoirs** sont exportés en négatif ; une ligne de **totaux** clôt le fichier.
 *
 * Logique pure, sans dépendance à WordPress, testable isolément.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class AccountingExport {

	const SEP = ';';
	const EOL = "\r\n";

	/**
	 * En-têtes de colonnes (ordre stable).
	 *
	 * @return string[]
	 */
	public static function headers() {
		return array( 'Numéro', 'Date', 'Type', 'Client', 'Taux TVA', 'HT', 'TVA', 'TTC' );
	}

	/**
	 * Construit le contenu CSV à partir d'une liste de factures.
	 *
	 * Chaque facture attendue :
	 *   [ 'number'=>string, 'date'=>'YYYY-MM-DD', 'client'=>string, 'vat_rate'=>float,
	 *     'ht'=>float, 'tva'=>float, 'ttc'=>float, 'kind'=>'invoice'|'avoir' ]
	 *
	 * @param array $invoices
	 * @param bool  $with_bom Ajoute un BOM UTF-8 (recommandé pour Excel). Défaut true.
	 * @return string
	 */
	public static function toCsv( array $invoices, $with_bom = true ) {
		$lines   = array();
		$lines[] = self::row( self::headers() );

		$t_ht = 0.0; $t_tva = 0.0; $t_ttc = 0.0;
		foreach ( $invoices as $inv ) {
			if ( ! is_array( $inv ) ) {
				continue;
			}
			$sign = self::sign( $inv );
			$ht   = $sign * self::amount( $inv, 'ht' );
			$tva  = $sign * self::amount( $inv, 'tva' );
			$ttc  = $sign * self::amount( $inv, 'ttc' );
			$t_ht += $ht; $t_tva += $tva; $t_ttc += $ttc;

			$lines[] = self::row(
				array(
					isset( $inv['number'] ) ? (string) $inv['number'] : '',
					isset( $inv['date'] ) ? (string) $inv['date'] : '',
					( -1 === $sign ) ? 'Avoir' : 'Facture',
					isset( $inv['client'] ) ? (string) $inv['client'] : '',
					self::money( isset( $inv['vat_rate'] ) ? (float) $inv['vat_rate'] : 0.0 ),
					self::money( $ht ),
					self::money( $tva ),
					self::money( $ttc ),
				)
			);
		}

		// Ligne de totaux nets.
		$lines[] = self::row(
			array( 'TOTAL', '', '', '', '', self::money( $t_ht ), self::money( $t_tva ), self::money( $t_ttc ) )
		);

		$csv = implode( self::EOL, $lines ) . self::EOL;
		return $with_bom ? "\xEF\xBB\xBF" . $csv : $csv;
	}

	/**
	 * Nom de fichier suggéré pour une période.
	 *
	 * @param string $start YYYY-MM-DD
	 * @param string $end   YYYY-MM-DD
	 * @return string
	 */
	public static function filename( $start, $end ) {
		$clean = static function ( $d ) {
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $d ) ? (string) $d : 'na';
		};
		return 'export-comptable-' . $clean( $start ) . '_' . $clean( $end ) . '.csv';
	}

	/**
	 * Assemble une ligne CSV (avec échappement).
	 *
	 * @param string[] $fields
	 * @return string
	 */
	private static function row( array $fields ) {
		$escaped = array_map( array( __CLASS__, 'escape' ), $fields );
		return implode( self::SEP, $escaped );
	}

	/**
	 * Échappement CSV : entoure de guillemets si le champ contient séparateur, guillemet ou saut de ligne.
	 *
	 * @param string $field
	 * @return string
	 */
	private static function escape( $field ) {
		$field = (string) $field;
		if ( false !== strpos( $field, self::SEP ) || false !== strpos( $field, '"' )
			|| false !== strpos( $field, "\n" ) || false !== strpos( $field, "\r" ) ) {
			return '"' . str_replace( '"', '""', $field ) . '"';
		}
		return $field;
	}

	/**
	 * Formate un montant en décimale française sans séparateur de milliers (« 1234,50 »).
	 *
	 * @param float $v
	 * @return string
	 */
	private static function money( $v ) {
		return number_format( (float) $v, 2, ',', '' );
	}

	/**
	 * @param array $inv
	 * @return int +1 facture, -1 avoir.
	 */
	private static function sign( array $inv ) {
		$kind = isset( $inv['kind'] ) ? strtolower( (string) $inv['kind'] ) : 'invoice';
		return ( 'avoir' === $kind || 'credit' === $kind || 'credit_note' === $kind ) ? -1 : 1;
	}

	/**
	 * @param array  $inv
	 * @param string $field
	 * @return float
	 */
	private static function amount( array $inv, $field ) {
		return isset( $inv[ $field ] ) ? abs( (float) $inv[ $field ] ) : 0.0;
	}
}
