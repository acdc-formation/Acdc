<?php
/**
 * En-têtes de sécurité HTTP pour les pages publiques (signature, émargement).
 *
 * Ces pages exposent des documents (parfois en iframe) sans authentification : elles
 * doivent se protéger du clickjacking (frame-ancestors / X-Frame-Options), du MIME
 * sniffing et des fuites de référent (URL/token du document).
 *
 * Classe PURE (construit la liste d'en-têtes) — l'émission via header() est faite par
 * un wrapper côté plugin. Facilite les tests unitaires.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class SecurityHeaders {

	/**
	 * Content-Security-Policy adaptée aux pages publiques de document.
	 *
	 * `'unsafe-inline'` reste nécessaire tant que les pages embarquent styles et scripts
	 * en ligne (à retirer une fois ceux-ci externalisés). `frame-ancestors 'self'` est le
	 * verrou anti-clickjacking ; `frame-src 'self'` autorise l'iframe du document (même origine).
	 *
	 * @return string
	 */
	public static function contentSecurityPolicy() {
		$directives = array(
			"default-src 'self'",
			"img-src 'self' data: https:",
			"style-src 'self' 'unsafe-inline'",
			"script-src 'self' 'unsafe-inline'",
			"font-src 'self' data:",
			"frame-src 'self'",
			"frame-ancestors 'self'",
			"base-uri 'self'",
			"form-action 'self'",
			"object-src 'none'",
		);
		return implode( '; ', $directives );
	}

	/**
	 * Liste des en-têtes de sécurité à émettre pour une page publique de document.
	 *
	 * @param bool $is_ssl      La requête est-elle en HTTPS ?
	 * @param bool $enable_hsts Émettre Strict-Transport-Security (opt-in, HTTPS uniquement) ?
	 * @return array<string,string> [nom => valeur]
	 */
	public static function forPublicDocument( $is_ssl = false, $enable_hsts = false ) {
		$headers = array(
			'Content-Security-Policy' => self::contentSecurityPolicy(),
			'X-Frame-Options'         => 'SAMEORIGIN',
			'X-Content-Type-Options'  => 'nosniff',
			'Referrer-Policy'         => 'no-referrer',
		);
		if ( $is_ssl && $enable_hsts ) {
			$headers['Strict-Transport-Security'] = 'max-age=15552000';
		}
		return $headers;
	}
}
