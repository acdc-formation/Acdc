<?php
/**
 * Scellement de documents — empreinte SHA-256 à valeur probante.
 *
 * Permet de lier cryptographiquement une signature électronique au contenu exact
 * du document présenté au signataire : toute modification ultérieure du fichier
 * change l'empreinte et rend la falsification détectable.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class DocumentSeal {

	const ALGO = 'sha256';

	/**
	 * Empreinte SHA-256 d'une chaîne binaire.
	 *
	 * @param string $data
	 * @return string 64 caractères hexadécimaux (chaîne vide si $data n'est pas une chaîne).
	 */
	public static function hashString( $data ) {
		if ( ! is_string( $data ) ) {
			return '';
		}
		return hash( self::ALGO, $data );
	}

	/**
	 * Empreinte SHA-256 du contenu d'un fichier.
	 *
	 * @param string $path Chemin absolu du fichier.
	 * @return string 64 caractères hexadécimaux, ou chaîne vide si illisible.
	 */
	public static function hashFile( $path ) {
		if ( ! is_string( $path ) || '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
			return '';
		}
		$hash = @hash_file( self::ALGO, $path );
		return is_string( $hash ) ? $hash : '';
	}

	/**
	 * Compare deux empreintes en temps constant (anti timing-attack).
	 *
	 * @param string $expected
	 * @param string $actual
	 * @return bool
	 */
	public static function matches( $expected, $actual ) {
		if ( ! is_string( $expected ) || ! is_string( $actual ) || '' === $expected ) {
			return false;
		}
		return hash_equals( $expected, $actual );
	}

	/**
	 * Format court lisible pour affichage (empreinte groupée).
	 *
	 * @param string $hash
	 * @return string
	 */
	public static function pretty( $hash ) {
		$hash = (string) $hash;
		if ( 64 !== strlen( $hash ) ) {
			return $hash;
		}
		return strtoupper( trim( chunk_split( $hash, 8, ' ' ) ) );
	}
}
