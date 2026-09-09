<?php
/**
 * SIRET / SIREN — validation et formatage à valeur légale.
 *
 * Le SIRET (14 chiffres = SIREN 9 + NIC 5) et le SIREN (9 chiffres) doivent figurer sur les
 * documents légaux d'un organisme de formation (factures, conventions). Cette brique garantit
 * qu'un numéro affiché est bien formé (clé de Luhn) et présenté de façon homogène — évitant
 * les coquilles sur des pièces à portée juridique/comptable.
 *
 * Gère l'exception historique de La Poste (SIREN 356000000), dont les SIRET ne satisfont pas
 * Luhn mais dont la somme des chiffres est un multiple de 5.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class Siret {

	const LA_POSTE_SIREN = '356000000';

	/**
	 * Ne conserve que les chiffres (retire espaces, points, etc.).
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function normalize( $value ) {
		if ( ! is_string( $value ) && ! is_int( $value ) ) {
			return '';
		}
		return preg_replace( '/\D/', '', (string) $value );
	}

	/**
	 * Vrai si $value est un SIRET valide (14 chiffres + clé de Luhn, ou exception La Poste).
	 *
	 * @param mixed $value
	 * @return bool
	 */
	public static function isValidSiret( $value ) {
		$d = self::normalize( $value );
		if ( 14 !== strlen( $d ) ) {
			return false;
		}
		if ( 0 === strpos( $d, self::LA_POSTE_SIREN ) ) {
			return 0 === self::digitSum( $d ) % 5;
		}
		return self::luhnValid( $d );
	}

	/**
	 * Vrai si $value est un SIREN valide (9 chiffres + clé de Luhn, ou exception La Poste).
	 *
	 * @param mixed $value
	 * @return bool
	 */
	public static function isValidSiren( $value ) {
		$d = self::normalize( $value );
		if ( 9 !== strlen( $d ) ) {
			return false;
		}
		if ( self::LA_POSTE_SIREN === $d ) {
			return true;
		}
		return self::luhnValid( $d );
	}

	/**
	 * SIREN (9 premiers chiffres) d'un SIRET.
	 *
	 * @param mixed $value
	 * @return string Chaîne vide si le SIRET n'a pas 14 chiffres.
	 */
	public static function siren( $value ) {
		$d = self::normalize( $value );
		return 14 === strlen( $d ) ? substr( $d, 0, 9 ) : '';
	}

	/**
	 * NIC (5 derniers chiffres, numéro d'établissement) d'un SIRET.
	 *
	 * @param mixed $value
	 * @return string Chaîne vide si le SIRET n'a pas 14 chiffres.
	 */
	public static function nic( $value ) {
		$d = self::normalize( $value );
		return 14 === strlen( $d ) ? substr( $d, 9 ) : '';
	}

	/**
	 * Formate un SIRET en « 405 109 901 00042 » (groupes 3-3-3-5).
	 * Renvoie l'entrée normalisée telle quelle si elle ne fait pas 14 chiffres.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function formatSiret( $value ) {
		$d = self::normalize( $value );
		if ( 14 !== strlen( $d ) ) {
			return $d;
		}
		return substr( $d, 0, 3 ) . ' ' . substr( $d, 3, 3 ) . ' ' . substr( $d, 6, 3 ) . ' ' . substr( $d, 9, 5 );
	}

	/**
	 * Formate un SIREN en « 405 109 901 » (groupes 3-3-3).
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function formatSiren( $value ) {
		$d = self::normalize( $value );
		if ( 9 !== strlen( $d ) ) {
			return $d;
		}
		return substr( $d, 0, 3 ) . ' ' . substr( $d, 3, 3 ) . ' ' . substr( $d, 6, 3 );
	}

	/**
	 * Validation de la clé de Luhn sur une chaîne de chiffres.
	 *
	 * @param string $digits
	 * @return bool
	 */
	private static function luhnValid( $digits ) {
		$sum = 0;
		$len = strlen( $digits );
		for ( $i = 0; $i < $len; $i++ ) {
			$n = (int) $digits[ $len - 1 - $i ];
			if ( 1 === $i % 2 ) {
				$n *= 2;
				if ( $n > 9 ) {
					$n -= 9;
				}
			}
			$sum += $n;
		}
		return 0 === $sum % 10;
	}

	/**
	 * Somme simple des chiffres (pour l'exception La Poste).
	 *
	 * @param string $digits
	 * @return int
	 */
	private static function digitSum( $digits ) {
		$sum = 0;
		$len = strlen( $digits );
		for ( $i = 0; $i < $len; $i++ ) {
			$sum += (int) $digits[ $i ];
		}
		return $sum;
	}
}
