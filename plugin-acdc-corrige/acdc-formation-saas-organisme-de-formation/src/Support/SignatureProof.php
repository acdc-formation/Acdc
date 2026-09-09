<?php
/**
 * Preuve de signature — bundle vérifiable (sceau du document + horodatage scellé).
 *
 * Assemble en un seul objet portable les éléments probants d'une signature électronique :
 * l'empreinte SHA-256 du document présenté au signataire (cf. DocumentSeal) et un jeton
 * d'horodatage scellé (cf. Timestamp). L'objet se scelle lui-même : toute altération d'un
 * champ (document, signataire, date) est détectable via `verify()`. Prêt à être stocké tel
 * quel (JSON) à côté de la signature et à recevoir un horodatage qualifié le moment venu.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class SignatureProof {

	const VERSION = 1;
	const ALGO    = 'sha256';

	/**
	 * Construit une preuve pour un document signé.
	 *
	 * @param string      $document_hash Empreinte SHA-256 (64 hex) du document signé.
	 * @param array       $signer        Métadonnées du signataire (nom, e-mail, rôle…).
	 * @param int|null    $unix_time     Horodatage UNIX (défaut : maintenant). Injectable pour tests.
	 * @param string|null $nonce         Aléa (défaut : aléatoire). Injectable pour tests.
	 * @return array Preuve, ou tableau vide si l'empreinte est invalide.
	 */
	public static function build( $document_hash, array $signer = array(), $unix_time = null, $nonce = null ) {
		if ( ! self::isSha256( $document_hash ) ) {
			return array();
		}
		$timestamp = Timestamp::localToken( $document_hash, $unix_time, $nonce );
		if ( empty( $timestamp ) ) {
			return array();
		}

		$proof = array(
			'version'   => self::VERSION,
			'doc_hash'  => $document_hash,
			'signer'    => self::normalizeSigner( $signer ),
			'timestamp' => $timestamp,
		);
		$proof['seal'] = self::seal( $proof );
		return $proof;
	}

	/**
	 * Vérifie l'intégrité complète d'une preuve et sa correspondance au document attendu.
	 *
	 * @param array  $proof
	 * @param string $expected_document_hash
	 * @return bool
	 */
	public static function verify( $proof, $expected_document_hash ) {
		if ( ! is_array( $proof ) || ! isset( $proof['seal'], $proof['doc_hash'], $proof['timestamp'] ) ) {
			return false;
		}
		if ( ! self::isSha256( $expected_document_hash )
			|| ! hash_equals( (string) $proof['doc_hash'], $expected_document_hash ) ) {
			return false;
		}
		// L'horodatage doit être intègre et porter sur la même empreinte.
		if ( ! Timestamp::verify( $proof['timestamp'], (string) $proof['doc_hash'] ) ) {
			return false;
		}
		// Le sceau global de la preuve doit être intact.
		$expected_seal = self::seal( $proof );
		return '' !== $expected_seal && hash_equals( $expected_seal, (string) $proof['seal'] );
	}

	/**
	 * Vrai si la preuve repose sur un horodatage qualifié (eIDAS).
	 *
	 * @param array $proof
	 * @return bool
	 */
	public static function isQualified( $proof ) {
		return is_array( $proof ) && isset( $proof['timestamp'] ) && Timestamp::isQualified( $proof['timestamp'] );
	}

	/**
	 * Sceau SHA-256 du contenu canonique de la preuve (hors sceau lui-même).
	 *
	 * @param array $proof
	 * @return string
	 */
	public static function seal( array $proof ) {
		unset( $proof['seal'] );
		$json = function_exists( 'wp_json_encode' )
			? wp_json_encode( self::deepKsort( $proof ) )
			: json_encode( self::deepKsort( $proof ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return is_string( $json ) ? hash( self::ALGO, $json ) : '';
	}

	/**
	 * Normalise/borne les métadonnées du signataire (clés attendues seulement).
	 *
	 * @param array $signer
	 * @return array
	 */
	private static function normalizeSigner( array $signer ) {
		$out = array();
		foreach ( array( 'name', 'email', 'role', 'ip' ) as $k ) {
			if ( isset( $signer[ $k ] ) && is_scalar( $signer[ $k ] ) ) {
				$out[ $k ] = (string) $signer[ $k ];
			}
		}
		return $out;
	}

	/**
	 * @param mixed $h
	 * @return bool
	 */
	private static function isSha256( $h ) {
		return is_string( $h ) && (bool) preg_match( '/^[0-9a-f]{64}$/', $h );
	}

	/**
	 * Tri récursif par clés pour une empreinte reproductible.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	private static function deepKsort( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$out = array();
		foreach ( $value as $k => $v ) {
			$out[ $k ] = self::deepKsort( $v );
		}
		ksort( $out );
		return $out;
	}
}
