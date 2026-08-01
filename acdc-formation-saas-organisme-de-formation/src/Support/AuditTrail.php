<?php
/**
 * Journal d'audit infalsifiable — chaînage cryptographique (WORM logique).
 *
 * Chaque écriture du journal est liée à la précédente par une empreinte SHA-256
 * qui inclut l'empreinte de la ligne antérieure. Toute modification, insertion ou
 * suppression a posteriori d'une ligne casse la chaîne à partir de ce point et
 * devient donc détectable : on obtient un registre en ajout-seul à valeur probante
 * (piste d'audit fiable au sens de l'art. L102 B / RGPD art. 5-2 « accountability »).
 *
 * Volontairement sans dépendance à WordPress : logique pure, testable isolément.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class AuditTrail {

	const ALGO = 'sha256';

	/**
	 * Empreinte de genèse (racine de la chaîne, avant toute écriture).
	 *
	 * @return string 64 caractères hexadécimaux.
	 */
	public static function genesis() {
		return hash( self::ALGO, 'ACDC-AUDIT-GENESIS-v1' );
	}

	/**
	 * Représentation canonique déterministe d'une entrée (clés triées récursivement).
	 *
	 * Deux tableaux équivalents produisent toujours la même chaîne, quel que soit
	 * l'ordre d'insertion des clés — condition nécessaire à un hash reproductible.
	 *
	 * @param array $entry
	 * @return string
	 */
	public static function canonicalize( array $entry ) {
		$sorted = self::deepKsort( $entry );
		$json   = wp_json_encode_compat( $sorted );
		return is_string( $json ) ? $json : '';
	}

	/**
	 * Calcule l'empreinte d'une nouvelle ligne à partir de la précédente.
	 *
	 * @param string $prev_hash Empreinte de la ligne antérieure (ou genesis() pour la première).
	 * @param array  $entry     Données métier de la ligne (acteur, action, cible, horodatage…).
	 * @return string 64 caractères hexadécimaux (chaîne vide si prev_hash invalide).
	 */
	public static function chain( $prev_hash, array $entry ) {
		if ( ! is_string( $prev_hash ) || 64 !== strlen( $prev_hash ) ) {
			return '';
		}
		return hash( self::ALGO, $prev_hash . '|' . self::canonicalize( $entry ) );
	}

	/**
	 * Vérifie l'intégrité complète d'une chaîne d'écritures.
	 *
	 * Chaque élément de $entries doit être un tableau contenant au moins :
	 *   - 'data' : array   → charge métier ayant servi au calcul,
	 *   - 'hash' : string  → empreinte stockée pour cette ligne.
	 * La clé 'prev' (empreinte antérieure stockée) est vérifiée si présente.
	 *
	 * @param array $entries Liste ordonnée des écritures (de la plus ancienne à la plus récente).
	 * @return int Index (0-based) de la première ligne altérée, ou -1 si la chaîne est intègre.
	 */
	public static function firstTamperedIndex( array $entries ) {
		$prev = self::genesis();
		$i    = 0;
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['data'] ) || ! is_array( $entry['data'] ) || ! isset( $entry['hash'] ) ) {
				return $i;
			}
			// Si la ligne mémorise l'empreinte antérieure, elle doit correspondre à la chaîne réelle.
			if ( isset( $entry['prev'] ) && ! hash_equals( $prev, (string) $entry['prev'] ) ) {
				return $i;
			}
			$expected = self::chain( $prev, $entry['data'] );
			if ( '' === $expected || ! hash_equals( $expected, (string) $entry['hash'] ) ) {
				return $i;
			}
			$prev = $expected;
			$i++;
		}
		return -1;
	}

	/**
	 * Vrai si la chaîne entière est intègre.
	 *
	 * @param array $entries
	 * @return bool
	 */
	public static function verifyChain( array $entries ) {
		return -1 === self::firstTamperedIndex( $entries );
	}

	/**
	 * Construit une chaîne complète à partir de charges métier brutes.
	 *
	 * Utilitaire pratique (tests, ré-indexation) : renvoie une liste d'écritures
	 * { data, prev, hash } prêtes à être persistées.
	 *
	 * @param array[] $payloads Liste ordonnée de tableaux métier.
	 * @return array[]
	 */
	public static function build( array $payloads ) {
		$prev = self::genesis();
		$out  = array();
		foreach ( $payloads as $data ) {
			$data = is_array( $data ) ? $data : array();
			$hash = self::chain( $prev, $data );
			$out[] = array(
				'data' => $data,
				'prev' => $prev,
				'hash' => $hash,
			);
			$prev = $hash;
		}
		return $out;
	}

	/**
	 * Tri récursif par clés (stable, insensible à l'ordre d'insertion).
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	private static function deepKsort( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$sorted = array();
		foreach ( $value as $k => $v ) {
			$sorted[ $k ] = self::deepKsort( $v );
		}
		// Tri par clés uniquement pour les tableaux associatifs ; les listes gardent leur ordre.
		if ( ! self::isList( $sorted ) ) {
			ksort( $sorted );
		}
		return $sorted;
	}

	/**
	 * Détecte une liste séquentielle (clés 0..n-1) — équivalent array_is_list (PHP 8.1+),
	 * réécrit pour compatibilité et testabilité.
	 *
	 * @param array $arr
	 * @return bool
	 */
	private static function isList( array $arr ) {
		if ( array() === $arr ) {
			return true;
		}
		$i = 0;
		foreach ( $arr as $k => $_ ) {
			if ( $k !== $i ) {
				return false;
			}
			$i++;
		}
		return true;
	}
}

if ( ! function_exists( 'ACDC\\Support\\wp_json_encode_compat' ) ) {
	/**
	 * Encodage JSON déterministe, indépendant de WordPress (réutilise wp_json_encode si dispo).
	 *
	 * @param mixed $data
	 * @return string|false
	 */
	function wp_json_encode_compat( $data ) {
		$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
		if ( function_exists( 'wp_json_encode' ) ) {
			return wp_json_encode( $data, $flags );
		}
		return json_encode( $data, $flags );
	}
}
