<?php
/**
 * Horodatage à valeur probante — jeton scellé, prêt pour la qualification eIDAS.
 *
 * Produit dès maintenant un jeton d'horodatage **simple** (horloge serveur) qui scelle
 * l'empreinte SHA-256 d'un document ou d'une écriture : le jeton est lui-même inviolable
 * (toute retouche casse son propre sceau). C'est suffisant pour dater de façon fiable et
 * vérifiable un émargement, une signature ou une facture.
 *
 * Point de branchement « officiel » : la méthode create() applique le filtre WordPress
 * `acdc_timestamp_token`. Le jour où l'on contractualise avec un tiers d'horodatage
 * **qualifié** (jeton RFC 3161 / eIDAS), il suffira de brancher un fournisseur sur ce
 * filtre — AUCUN appelant existant n'a à changer. Tant qu'aucun fournisseur qualifié n'est
 * branché, l'horodatage local (marqué `qualified => false`) s'applique par défaut.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class Timestamp {

	const ALGO    = 'sha256';
	const VERSION = 1;

	/**
	 * Crée un jeton d'horodatage pour une empreinte donnée.
	 *
	 * Applique le filtre `acdc_timestamp_token` (point de branchement d'un tiers qualifié).
	 * Si le filtre renvoie un jeton valide (scellé + qualified), il prime ; sinon on retombe
	 * sur l'horodatage local scellé.
	 *
	 * @param string $data_hash Empreinte SHA-256 (64 hex) du contenu à horodater.
	 * @return array Jeton d'horodatage, ou tableau vide si $data_hash invalide.
	 */
	public static function create( $data_hash ) {
		if ( ! self::isSha256( $data_hash ) ) {
			return array();
		}
		$local = self::localToken( $data_hash );

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'acdc_timestamp_token', $local, $data_hash );
			// On n'accepte le jeton branché que s'il est cohérent et scellé.
			if ( is_array( $filtered ) && self::verify( $filtered, $data_hash ) ) {
				return $filtered;
			}
		}
		return $local;
	}

	/**
	 * Jeton d'horodatage local (non qualifié), scellé sur lui-même.
	 *
	 * @param string   $data_hash Empreinte SHA-256 à horodater.
	 * @param int|null $unix_time Horodatage UNIX (par défaut : maintenant). Injectable pour les tests.
	 * @param string|null $nonce  Aléa anti-rejeu (par défaut : 16 octets). Injectable pour les tests.
	 * @return array
	 */
	public static function localToken( $data_hash, $unix_time = null, $nonce = null ) {
		if ( ! self::isSha256( $data_hash ) ) {
			return array();
		}
		$unix_time = ( null === $unix_time ) ? self::now() : (int) $unix_time;
		$nonce     = ( null === $nonce ) ? self::randomNonce() : (string) $nonce;

		$token = array(
			'version'   => self::VERSION,
			'algo'      => self::ALGO,
			'data_hash' => $data_hash,
			'time_unix' => $unix_time,
			'time_utc'  => gmdate( 'Y-m-d\TH:i:s\Z', $unix_time ),
			'provider'  => 'local',
			'qualified' => false,
			'nonce'     => $nonce,
		);
		$token['seal'] = self::seal( $token );
		return $token;
	}

	/**
	 * Vérifie qu'un jeton est intègre (sceau valide) et se rapporte bien à l'empreinte attendue.
	 *
	 * @param array  $token
	 * @param string $expected_hash Empreinte que le jeton est censé horodater.
	 * @return bool
	 */
	public static function verify( $token, $expected_hash ) {
		if ( ! is_array( $token ) || ! isset( $token['seal'], $token['data_hash'] ) ) {
			return false;
		}
		if ( ! self::isSha256( (string) $token['data_hash'] ) ) {
			return false;
		}
		if ( ! self::isSha256( $expected_hash ) || ! hash_equals( (string) $token['data_hash'], $expected_hash ) ) {
			return false;
		}
		$expected_seal = self::seal( $token );
		return '' !== $expected_seal && hash_equals( $expected_seal, (string) $token['seal'] );
	}

	/**
	 * Vrai si le jeton provient d'un tiers d'horodatage qualifié (eIDAS).
	 *
	 * @param array $token
	 * @return bool
	 */
	public static function isQualified( $token ) {
		return is_array( $token ) && ! empty( $token['qualified'] );
	}

	/**
	 * Libellé lisible du niveau de preuve (pour l'affichage / les PDF).
	 *
	 * @param array $token
	 * @return string
	 */
	public static function levelLabel( $token ) {
		if ( self::isQualified( $token ) ) {
			return 'Horodatage qualifié (eIDAS)';
		}
		return 'Horodatage simple (horloge serveur, non qualifié)';
	}

	/**
	 * Calcule le sceau d'un jeton : SHA-256 du contenu canonique (hors sceau lui-même).
	 *
	 * @param array $token
	 * @return string
	 */
	public static function seal( array $token ) {
		unset( $token['seal'] );
		ksort( $token );
		$json = function_exists( 'wp_json_encode' )
			? wp_json_encode( $token )
			: json_encode( $token, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return is_string( $json ) ? hash( self::ALGO, $json ) : '';
	}

	/**
	 * @param mixed $h
	 * @return bool
	 */
	private static function isSha256( $h ) {
		return is_string( $h ) && (bool) preg_match( '/^[0-9a-f]{64}$/', $h );
	}

	/**
	 * Heure courante (UNIX, UTC). Isolée pour la testabilité.
	 *
	 * @return int
	 */
	private static function now() {
		return time();
	}

	/**
	 * Aléa hexadécimal (16 octets), avec repli si random_bytes indisponible.
	 *
	 * @return string
	 */
	private static function randomNonce() {
		try {
			return bin2hex( random_bytes( 16 ) );
		} catch ( \Exception $e ) {
			return hash( self::ALGO, uniqid( 'acdc_ts_', true ) );
		}
	}
}
