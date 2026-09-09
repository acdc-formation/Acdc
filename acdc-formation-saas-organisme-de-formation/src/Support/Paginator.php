<?php
/**
 * Pagination pure et testable des listes (bornage par page).
 *
 * Source unique de vérité pour le calcul des bornes de pagination : page courante,
 * nombre d'éléments par page, nombre total de pages et décalage (OFFSET) SQL.
 * Centraliser ce calcul évite de charger des tables entières et garantit un
 * comportement identique quelle que soit la liste (prospects, apprenants, devis…).
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class Paginator {

	/**
	 * Nombre d'éléments par page utilisé lorsqu'aucune valeur valable n'est fournie.
	 */
	const DEFAULT_PER_PAGE = 25;

	/**
	 * Nombre minimal d'éléments par page.
	 */
	const MIN_PER_PAGE = 1;

	/**
	 * Nombre maximal d'éléments par page (garde-fou anti-« charger toute la table »).
	 */
	const MAX_PER_PAGE = 200;

	/**
	 * Normalise et borne les paramètres de pagination.
	 *
	 * Règles :
	 *  - page      : entier >= 1, ramené à max( 1, pages ) si trop grand.
	 *  - per_page  : entier borné dans [1, 200] ; toute valeur < 1 (ou non numérique)
	 *                retombe sur le défaut (25).
	 *  - total     : entier >= 0.
	 *  - pages     : max( 1, ceil( total / per_page ) ) — vaut toujours au moins 1,
	 *                y compris quand total vaut 0.
	 *  - offset    : ( page - 1 ) * per_page.
	 *  - has_prev  : vrai s'il existe une page précédente.
	 *  - has_next  : vrai s'il existe une page suivante.
	 *
	 * @param int $page     Page demandée (1-indexée).
	 * @param int $per_page Éléments par page souhaités.
	 * @param int $total    Nombre total d'éléments à paginer.
	 * @return array{page:int,per_page:int,total:int,pages:int,offset:int,has_prev:bool,has_next:bool}
	 */
	public static function normalize( $page, $per_page, $total ) {
		$per_page = (int) $per_page;
		if ( $per_page < self::MIN_PER_PAGE ) {
			$per_page = self::DEFAULT_PER_PAGE;
		}
		if ( $per_page > self::MAX_PER_PAGE ) {
			$per_page = self::MAX_PER_PAGE;
		}

		$total = (int) $total;
		if ( $total < 0 ) {
			$total = 0;
		}

		$pages = (int) max( 1, (int) ceil( $total / $per_page ) );

		$page = (int) $page;
		if ( $page < 1 ) {
			$page = 1;
		}
		if ( $page > $pages ) {
			$page = $pages;
		}

		$offset = ( $page - 1 ) * $per_page;

		return array(
			'page'     => $page,
			'per_page' => $per_page,
			'total'    => $total,
			'pages'    => $pages,
			'offset'   => $offset,
			'has_prev' => $page > 1,
			'has_next' => $page < $pages,
		);
	}
}
