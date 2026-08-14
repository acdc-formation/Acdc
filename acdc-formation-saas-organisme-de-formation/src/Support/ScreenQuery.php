<?php
/**
 * Les mots que WordPress s'est réservés, et qu'un écran ne doit jamais employer.
 *
 * Relevé en recette, et le symptôme méritait qu'on cherche loin : le calendrier
 * des séances s'arrêtait en décembre 2026. Le mois suivant renvoyait sur une
 * page sans rapport. Aucune borne de date n'était en cause — le calendrier
 * accepte de 2000 à 2100.
 *
 * La faute tenait à un mot. La flèche « mois suivant » fabriquait :
 *
 *   /extranet/tableau-de-bord/?tab=sessions_calendar&month=1&year=2027
 *
 * `year` est le paramètre des ARCHIVES PAR DATE de WordPress. En le posant sur
 * l'adresse d'une page, on ne demande plus « la page tableau de bord » : on
 * demande « la page tableau de bord publiée en 2027 ». La page a été publiée en
 * 2026 ; la requête principale ne trouve plus rien, et WordPress sert autre
 * chose.
 *
 * Ce qui explique la date : tant que la navigation reste dans l'année en cours,
 * `year` décrit la vraie année de publication de la page et tout fonctionne. Le
 * premier clic qui en sort casse. Ce n'était pas une échéance, c'était une
 * frontière mobile : en 2027, la panne se serait déplacée à décembre 2027.
 *
 * D'où deux règles tenues ici, en un seul endroit :
 *
 *   1. UN ÉCRAN N'EMPLOIE JAMAIS UN MOT RÉSERVÉ DE WORDPRESS.
 *   2. Un mois se transporte en UN paramètre, `acdc_month=AAAA-MM`. C'est déjà
 *      la forme retenue par le calendrier de disponibilités du portail
 *      formateur : on ne réinvente rien, on généralise ce qui marche.
 *
 * Les anciennes adresses continuent d'être lues — un favori, un lien collé dans
 * un courriel — mais on ne les fabrique plus.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class ScreenQuery {

	/**
	 * Les variables publiques de WordPress.
	 *
	 * Posées sur l'adresse d'une page, elles détournent la requête principale :
	 * `year` en fait une archive de date, `s` une recherche, `cat` une archive
	 * de catégorie, `p` un article. La liste vient de WP::$public_query_vars ;
	 * seuls les mots qu'un écran de gestion pourrait spontanément employer y
	 * figurent — inutile de se protéger de `sentence` ou de `robots`.
	 */
	const RESERVES = array(
		'year', 'monthnum', 'day', 'hour', 'minute', 'second', 'm', 'w',
		's', 'p', 'name', 'page_id', 'pagename', 'post_type', 'author',
		'cat', 'category_name', 'tag', 'taxonomy', 'term', 'feed',
		'paged', 'page', 'attachment', 'attachment_id', 'preview',
		'order', 'orderby', 'error', 'embed', 'static', 'title',
	);

	/**
	 * Ce mot est-il confisqué par WordPress ?
	 *
	 * @param string $name Nom du paramètre d'URL.
	 * @return bool
	 */
	public static function isReserved( $name ) {
		return in_array( strtolower( trim( (string) $name ) ), self::RESERVES, true );
	}

	/**
	 * Le mois transporté par une adresse, au format AAAA-MM.
	 *
	 * @param int|string $year  Année.
	 * @param int|string $month Mois (1 à 12).
	 * @return string
	 */
	public static function monthParam( $year, $month ) {
		$year  = (int) $year;
		$month = (int) $month;
		if ( $month < 1 )  { $month = 1; }
		if ( $month > 12 ) { $month = 12; }
		return sprintf( '%04d-%02d', $year, $month );
	}

	/**
	 * Relit le mois demandé par une adresse.
	 *
	 * Trois sources, dans cet ordre : le paramètre d'aujourd'hui, le couple
	 * d'hier (`month` + `year`) pour les liens déjà en circulation, et à défaut
	 * le mois courant. Une valeur illisible ne fait jamais dérailler l'écran :
	 * elle retombe sur le mois courant, ce qui est le seul repli qu'un
	 * utilisateur puisse comprendre.
	 *
	 * @param array      $get           Le tableau des paramètres reçus.
	 * @param int|string $default_year  Année par défaut.
	 * @param int|string $default_month Mois par défaut.
	 * @return array{year:int,month:int}
	 */
	public static function readMonth( $get, $default_year, $default_month ) {
		$get = is_array( $get ) ? $get : array();

		if ( isset( $get['acdc_month'] ) && is_string( $get['acdc_month'] )
			&& preg_match( '/^(\d{4})-(\d{2})$/', trim( $get['acdc_month'] ), $m ) ) {
			$annee = (int) $m[1];
			$mois  = (int) $m[2];
			if ( $mois >= 1 && $mois <= 12 && self::yearIsSane( $annee ) ) {
				return array( 'year' => $annee, 'month' => $mois );
			}
		}

		/* Les adresses d'avant la 3.25.269 : on les lit, on ne les écrit plus. */
		if ( isset( $get['year'] ) || isset( $get['month'] ) ) {
			$annee = isset( $get['year'] ) ? (int) $get['year'] : (int) $default_year;
			$mois  = isset( $get['month'] ) ? (int) $get['month'] : (int) $default_month;
			if ( $mois >= 1 && $mois <= 12 && self::yearIsSane( $annee ) ) {
				return array( 'year' => $annee, 'month' => $mois );
			}
		}

		return array( 'year' => (int) $default_year, 'month' => (int) $default_month );
	}

	/**
	 * Le numéro de page d'une liste.
	 *
	 * `paged` est le mot de WordPress pour la pagination de la requête
	 * principale. Sur l'adresse d'une page, demander `paged=2` revient à
	 * réclamer la deuxième page d'un contenu qui n'en a qu'une : selon le
	 * thème, c'est un 404. Personne ne l'avait signalé — on ne pagine pas tous
	 * les jours — mais c'est exactement la même mécanique que le calendrier, et
	 * elle attendait son tour.
	 *
	 * @param array $get Le tableau des paramètres reçus.
	 * @return int Numéro de page, 1 au minimum.
	 */
	public static function readPaged( $get ) {
		$get = is_array( $get ) ? $get : array();
		if ( isset( $get['acdc_paged'] ) ) {
			return max( 1, (int) $get['acdc_paged'] );
		}
		/* L'adresse d'avant la 3.25.269. */
		if ( isset( $get['paged'] ) ) {
			return max( 1, (int) $get['paged'] );
		}
		return 1;
	}

	/**
	 * Une année plausible.
	 *
	 * Le calendrier n'a pas de terme — David l'a demandé ainsi, et il a raison :
	 * une session se planifie parfois à deux ans. Cette borne-ci n'est pas une
	 * limite de gestion, c'est un garde-fou contre une adresse trafiquée qui
	 * demanderait l'an 900000 et ferait calculer une grille absurde.
	 *
	 * @param int $year Année.
	 * @return bool
	 */
	public static function yearIsSane( $year ) {
		$year = (int) $year;
		return $year >= 1900 && $year <= 2200;
	}
}
