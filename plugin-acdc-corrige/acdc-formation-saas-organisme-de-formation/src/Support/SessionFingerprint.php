<?php
/**
 * Empreinte de session (anti-vol de cookie) — logique pure et testable.
 *
 * Lie une session à une empreinte IP + User-Agent. La comparaison est tolérante :
 *  - IP : on ne compare que le préfixe réseau (/24 en IPv4, 4 premiers groupes en
 *    IPv6) pour absorber les IP dynamiques d'un même opérateur/box.
 *  - User-Agent : comparaison d'un hash tronqué (le UA complet n'a pas à circuler).
 * Une session « legacy » (créée avant l'introduction de l'empreinte, donc sans
 * ip/ua stockés) est toujours acceptée : le durcissement ne casse pas l'existant.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class SessionFingerprint {

	/**
	 * Préfixe réseau discriminant d'une adresse IP.
	 *
	 * - IPv4 : les 3 premiers octets (/24), ex. « 192.168.1.42 » → « 192.168.1 ».
	 * - IPv6 : les 4 premiers groupes (normalisés en hexadécimal), ex.
	 *   « 2001:db8:85a3::1 » → « 2001:db8:85a3:0 ».
	 * - Autre (chaîne non-IP, ex. « inconnue » ou vide) : la chaîne telle quelle.
	 *
	 * @param string $ip
	 * @return string
	 */
	public static function ipPrefix( string $ip ): string {
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts = explode( '.', $ip );
			return $parts[0] . '.' . $parts[1] . '.' . $parts[2];
		}

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$bin = @inet_pton( $ip );
			if ( false === $bin || strlen( $bin ) < 8 ) {
				return $ip;
			}
			// 4 premiers groupes = 8 premiers octets, normalisés en hexadécimal.
			$groups = unpack( 'n4', substr( $bin, 0, 8 ) );
			if ( ! is_array( $groups ) ) {
				return $ip;
			}
			return implode( ':', array_map( 'dechex', $groups ) );
		}

		return $ip;
	}

	/**
	 * Hash tronqué (16 hexits) d'un User-Agent.
	 *
	 * @param string $ua
	 * @return string
	 */
	public static function uaHash( string $ua ): string {
		return substr( sha1( $ua ), 0, 16 );
	}

	/**
	 * La requête courante correspond-elle à l'empreinte stockée de la session ?
	 *
	 * Vrai si le préfixe IP ET le hash UA coïncident. Vrai aussi (non régressif)
	 * si l'empreinte stockée est incomplète (ip OU ua vide) : session legacy.
	 *
	 * @param string $stored_ip   IP enregistrée à la création de la session.
	 * @param string $stored_ua   User-Agent enregistré à la création de la session.
	 * @param string $current_ip  IP de la requête courante.
	 * @param string $current_ua  User-Agent de la requête courante.
	 * @return bool
	 */
	public static function matches( string $stored_ip, string $stored_ua, string $current_ip, string $current_ua ): bool {
		// Session legacy sans empreinte : on ne casse pas les sessions existantes.
		if ( '' === $stored_ip || '' === $stored_ua ) {
			return true;
		}

		return self::ipPrefix( $stored_ip ) === self::ipPrefix( $current_ip )
			&& self::uaHash( $stored_ua ) === self::uaHash( $current_ua );
	}
}
