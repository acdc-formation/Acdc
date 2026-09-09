<?php
/**
 * Attestations vérifiables — référence à clé de contrôle + signature d'authenticité.
 *
 * Donne à chaque attestation/certificat de formation une **référence unique** dotée d'une clé de
 * contrôle (détection des fautes de frappe, façon IBAN) et une **signature** courte (HMAC) qui
 * permet à une page de vérification publique (accessible par QR code) de confirmer que le document
 * a bien été émis par l'organisme — sans exposer de secret. Fonctionnalité différenciante
 * (certificats infalsifiables, vérifiables par un tiers).
 *
 * Logique pure, sans dépendance à WordPress ; le secret de signature (sel WP) est fourni à l'appel.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class CertificateCode {

	const PREFIX      = 'ACDC';
	const TOKEN_BYTES = 12; // Longueur (hex) de la signature tronquée.

	/**
	 * Référence lisible sans clé de contrôle, ex. « ACDC-2026-000123 ».
	 *
	 * @param int    $year
	 * @param int    $sequence
	 * @param string $prefix
	 * @return string
	 */
	public static function reference( $year, $sequence, $prefix = self::PREFIX ) {
		$prefix = preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $prefix ) );
		if ( '' === $prefix ) {
			$prefix = self::PREFIX;
		}
		return sprintf( '%s-%04d-%06d', $prefix, (int) $year, (int) $sequence );
	}

	/**
	 * Référence complète avec clé de contrôle à 2 chiffres, ex. « ACDC-2026-000123-42 ».
	 *
	 * @param int    $year
	 * @param int    $sequence
	 * @param string $prefix
	 * @return string
	 */
	public static function format( $year, $sequence, $prefix = self::PREFIX ) {
		$ref = self::reference( $year, $sequence, $prefix );
		return $ref . '-' . sprintf( '%02d', self::checkDigits( (int) $year, (int) $sequence ) );
	}

	/**
	 * Clé de contrôle IBAN-like (2 chiffres, 01..97) sur l'année et la séquence.
	 *
	 * @param int $year
	 * @param int $sequence
	 * @return int
	 */
	public static function checkDigits( $year, $sequence ) {
		$n = (int) $year * 1000000 + (int) $sequence;
		// 98 - ((n * 100) mod 97) → clé telle que (n*100 + clé) mod 97 == 1.
		$key = 98 - self::mod97( $n * 100 );
		if ( $key < 1 ) {
			$key += 97;
		}
		return $key;
	}

	/**
	 * Vérifie qu'une référence complète est bien formée et que sa clé de contrôle est correcte.
	 *
	 * @param string $code
	 * @return bool
	 */
	public static function isWellFormed( $code ) {
		if ( ! preg_match( '/^[A-Z0-9]+-(\d{4})-(\d{6})-(\d{2})$/', (string) $code, $m ) ) {
			return false;
		}
		$year = (int) $m[1];
		$seq  = (int) $m[2];
		$key  = (int) $m[3];
		return $key === self::checkDigits( $year, $seq );
	}

	/**
	 * Signature courte d'authenticité (HMAC-SHA256 tronquée) pour l'URL/QR de vérification.
	 *
	 * @param string $reference Référence complète (avec clé) ou nue.
	 * @param string $secret    Secret serveur (ex. sel WordPress). Ne doit jamais être exposé.
	 * @return string Chaîne vide si secret manquant.
	 */
	public static function sign( $reference, $secret ) {
		$reference = (string) $reference;
		$secret    = (string) $secret;
		if ( '' === $secret || '' === $reference ) {
			return '';
		}
		return substr( hash_hmac( 'sha256', $reference, $secret ), 0, self::TOKEN_BYTES );
	}

	/**
	 * Vérifie une signature en temps constant.
	 *
	 * @param string $reference
	 * @param string $token
	 * @param string $secret
	 * @return bool
	 */
	public static function verify( $reference, $token, $secret ) {
		$expected = self::sign( $reference, $secret );
		if ( '' === $expected || ! is_string( $token ) || '' === $token ) {
			return false;
		}
		return hash_equals( $expected, $token );
	}

	/**
	 * Calcule n mod 97 sur un entier potentiellement grand, par blocs (robuste 32/64 bits).
	 *
	 * @param int $n
	 * @return int
	 */
	private static function mod97( $n ) {
		$s = (string) $n;
		$rem = 0;
		$len = strlen( $s );
		for ( $i = 0; $i < $len; $i++ ) {
			$rem = ( $rem * 10 + (int) $s[ $i ] ) % 97;
		}
		return $rem;
	}
}
