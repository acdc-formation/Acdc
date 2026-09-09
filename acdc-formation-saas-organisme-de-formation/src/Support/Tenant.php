<?php
/**
 * Contexte multi-organismes (tenant) — isolation des données par client SaaS.
 *
 * Fondation de la commercialisation à plusieurs organismes : chaque « tenant » (organisme client)
 * doit voir ses propres réglages et ses propres données, sans mélange. Cette brique normalise un
 * identifiant de tenant et calcule de façon déterministe les **clés d'options** et **préfixes de
 * tables** isolés qui en découlent. Le tenant « main » correspond à l'installation mono-organisme
 * actuelle (rétro-compatibilité : aucune clé n'est préfixée pour lui).
 *
 * Logique pure, sans dépendance à WordPress ; le tenant courant est résolu via un filtre.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class Tenant {

	const MAIN     = 'main';
	const MAX_LEN  = 32;

	/**
	 * Normalise un identifiant de tenant : minuscules, alphanumérique + tiret, longueur bornée.
	 *
	 * @param mixed $id
	 * @return string Identifiant normalisé, ou 'main' si vide/invalide.
	 */
	public static function normalizeId( $id ) {
		if ( ! is_string( $id ) && ! is_int( $id ) ) {
			return self::MAIN;
		}
		$id = strtolower( (string) $id );
		$id = preg_replace( '/[^a-z0-9\-]/', '-', $id );
		$id = preg_replace( '/-+/', '-', $id );
		$id = trim( $id, '-' );
		if ( '' === $id ) {
			return self::MAIN;
		}
		return substr( $id, 0, self::MAX_LEN );
	}

	/**
	 * Vrai si l'identifiant est déjà sous forme normalisée valide.
	 *
	 * @param mixed $id
	 * @return bool
	 */
	public static function isValidId( $id ) {
		return is_string( $id ) && $id === self::normalizeId( $id );
	}

	/**
	 * Vrai s'il s'agit du tenant principal (installation mono-organisme).
	 *
	 * @param string $id
	 * @return bool
	 */
	public static function isMain( $id ) {
		return self::MAIN === self::normalizeId( $id );
	}

	/**
	 * Clé d'option isolée pour un tenant.
	 *
	 * Le tenant principal conserve la clé d'origine (rétro-compatibilité totale) ; les autres
	 * tenants obtiennent une clé préfixée déterministe.
	 *
	 * @param string $base_key Ex. « acdc_of_branding ».
	 * @param string $tenant_id
	 * @return string
	 */
	public static function optionKey( $base_key, $tenant_id ) {
		$base = (string) $base_key;
		$id   = self::normalizeId( $tenant_id );
		if ( self::MAIN === $id ) {
			return $base;
		}
		return 'acdc_t_' . $id . '__' . $base;
	}

	/**
	 * Préfixe de tables isolé pour un tenant.
	 *
	 * @param string $wp_prefix Préfixe WordPress (ex. « wp_ »).
	 * @param string $tenant_id
	 * @return string
	 */
	public static function tablePrefix( $wp_prefix, $tenant_id ) {
		$prefix = (string) $wp_prefix;
		$id     = self::normalizeId( $tenant_id );
		if ( self::MAIN === $id ) {
			return $prefix;
		}
		return $prefix . 't_' . str_replace( '-', '_', $id ) . '_';
	}

	/**
	 * Résout le tenant courant via le filtre `acdc_current_tenant` (défaut : principal).
	 *
	 * @return string
	 */
	public static function current() {
		$id = self::MAIN;
		if ( function_exists( 'apply_filters' ) ) {
			$id = apply_filters( 'acdc_current_tenant', self::MAIN );
		}
		return self::normalizeId( $id );
	}
}
