<?php
/**
 * Chiffre d'affaires — agrégation fiable et testable des montants facturés.
 *
 * Centralise le calcul du CA à partir de lignes de facturation homogènes, avec une règle
 * unique et vérifiable : les **factures** s'ajoutent, les **avoirs** se retranchent. Fournit
 * le CA net (HT/TVA/TTC), la ventilation par **taux de TVA** et par **période** (mois,
 * trimestre, année). Objectif : que tous les écrans de statistiques s'appuient sur le même
 * calcul, sans divergence d'un tableau à l'autre.
 *
 * Chaque ligne attendue :
 *   [ 'ht' => float, 'tva' => float, 'ttc' => float,
 *     'kind' => 'invoice'|'avoir', 'vat_rate' => float, 'date' => 'YYYY-MM-DD' ]
 * Les montants sont fournis en **magnitude positive** ; le signe est déterminé par 'kind'.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class Revenue {

	/**
	 * Totaux nets sur un ensemble de lignes (factures – avoirs).
	 *
	 * @param array $rows
	 * @return array { ht, tva, ttc, count_invoices, count_avoirs }
	 */
	public static function summarize( array $rows ) {
		$ht = 0.0; $tva = 0.0; $ttc = 0.0; $ni = 0; $na = 0;
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$sign = self::sign( $row );
			$ht  += $sign * self::amount( $row, 'ht' );
			$tva += $sign * self::amount( $row, 'tva' );
			$ttc += $sign * self::amount( $row, 'ttc' );
			if ( $sign < 0 ) {
				$na++;
			} else {
				$ni++;
			}
		}
		return array(
			'ht'             => self::round2( $ht ),
			'tva'            => self::round2( $tva ),
			'ttc'            => self::round2( $ttc ),
			'count_invoices' => $ni,
			'count_avoirs'   => $na,
		);
	}

	/**
	 * Ventilation du CA net par taux de TVA (clé = taux formaté, ex. "20" ou "5.5").
	 *
	 * @param array $rows
	 * @return array<string, array{ht:float,tva:float,ttc:float}>
	 */
	public static function byVatRate( array $rows ) {
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key = self::rateKey( isset( $row['vat_rate'] ) ? (float) $row['vat_rate'] : 0.0 );
			self::accumulate( $out, $key, $row );
		}
		return self::round2Groups( $out );
	}

	/**
	 * Ventilation du CA net par période.
	 *
	 * @param array  $rows
	 * @param string $granularity 'month' (YYYY-MM), 'quarter' (YYYY-Qn) ou 'year' (YYYY).
	 * @return array<string, array{ht:float,tva:float,ttc:float}> Trié par clé de période croissante.
	 */
	public static function byPeriod( array $rows, $granularity = 'month' ) {
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key = self::periodKey( isset( $row['date'] ) ? (string) $row['date'] : '', $granularity );
			if ( '' === $key ) {
				continue;
			}
			self::accumulate( $out, $key, $row );
		}
		ksort( $out );
		return self::round2Groups( $out );
	}

	/**
	 * Clé de période à partir d'une date ISO (YYYY-MM-DD).
	 *
	 * @param string $date
	 * @param string $granularity
	 * @return string Chaîne vide si la date est invalide.
	 */
	public static function periodKey( $date, $granularity = 'month' ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', (string) $date, $m ) ) {
			return '';
		}
		$year  = $m[1];
		$month = (int) $m[2];
		if ( $month < 1 || $month > 12 ) {
			return '';
		}
		switch ( $granularity ) {
			case 'year':
				return $year;
			case 'quarter':
				return $year . '-Q' . (string) ( (int) ceil( $month / 3 ) );
			case 'month':
			default:
				return $year . '-' . $m[2];
		}
	}

	/**
	 * @param array $row
	 * @return int +1 pour une facture, -1 pour un avoir.
	 */
	private static function sign( array $row ) {
		$kind = isset( $row['kind'] ) ? strtolower( (string) $row['kind'] ) : 'invoice';
		return ( 'avoir' === $kind || 'credit' === $kind || 'credit_note' === $kind ) ? -1 : 1;
	}

	/**
	 * @param array  $row
	 * @param string $field
	 * @return float Magnitude positive du montant.
	 */
	private static function amount( array $row, $field ) {
		return isset( $row[ $field ] ) ? abs( (float) $row[ $field ] ) : 0.0;
	}

	/**
	 * @param array  $out
	 * @param string $key
	 * @param array  $row
	 * @return void
	 */
	private static function accumulate( array &$out, $key, array $row ) {
		if ( ! isset( $out[ $key ] ) ) {
			$out[ $key ] = array( 'ht' => 0.0, 'tva' => 0.0, 'ttc' => 0.0 );
		}
		$sign = self::sign( $row );
		$out[ $key ]['ht']  += $sign * self::amount( $row, 'ht' );
		$out[ $key ]['tva'] += $sign * self::amount( $row, 'tva' );
		$out[ $key ]['ttc'] += $sign * self::amount( $row, 'ttc' );
	}

	/**
	 * Normalise un taux de TVA en clé lisible ("20", "5.5", "0").
	 *
	 * @param float $rate
	 * @return string
	 */
	private static function rateKey( $rate ) {
		$rate = round( (float) $rate, 2 );
		if ( $rate === (float) (int) $rate ) {
			return (string) (int) $rate;
		}
		return rtrim( rtrim( number_format( $rate, 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * @param float $v
	 * @return float
	 */
	private static function round2( $v ) {
		return round( (float) $v, 2 );
	}

	/**
	 * @param array $groups
	 * @return array
	 */
	private static function round2Groups( array $groups ) {
		foreach ( $groups as $k => $g ) {
			$groups[ $k ] = array(
				'ht'  => self::round2( $g['ht'] ),
				'tva' => self::round2( $g['tva'] ),
				'ttc' => self::round2( $g['ttc'] ),
			);
		}
		return $groups;
	}
}
