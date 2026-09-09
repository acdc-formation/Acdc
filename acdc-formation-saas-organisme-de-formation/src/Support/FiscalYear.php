<?php
/**
 * L'exercice comptable, quand il ne suit pas l'année civile.
 *
 * Le BPF se déclare sur l'exercice de la structure, pas sur l'année du
 * calendrier. Un exercice qui court du 23 avril au 22 avril chevauche donc deux
 * millésimes : « l'exercice 2025 » va du 23/04/2025 au 22/04/2026.
 *
 * Cette arithmétique était écrite à l'intérieur d'une fonction qui construit la
 * liste des BPF. Elle n'y était pas fausse — elle y était SEULE, et le jour où
 * un second BPF a eu besoin des mêmes bornes, la tentation naturelle était de
 * les recalculer à côté. Deux calculs de la même chose finissent toujours par
 * diverger, et celui-ci décide de ce qui entre dans une déclaration
 * administrative.
 *
 * Elle vit donc ici, seule et vérifiable : ni base de données, ni WordPress,
 * juste des jours et des mois.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class FiscalYear {

	/** À défaut de réglage, l'année civile. */
	const DEBUT_DEFAUT = '01/01';
	const FIN_DEFAUT   = '31/12';

	/**
	 * Les bornes d'un exercice, au format d'affichage JJ/MM/AAAA.
	 *
	 * L'année de fin se déduit de la position de la date de fin par rapport à
	 * celle de début : une fin qui tombe AVANT le début dans l'année tombe donc
	 * l'année suivante. Un exercice qui va du 01/01 au 31/12 reste sur une seule
	 * année ; du 23/04 au 22/04, il en chevauche deux.
	 *
	 * @param string $debut_jj_mm Début de l'exercice, « JJ/MM ».
	 * @param string $fin_jj_mm   Fin de l'exercice, « JJ/MM ».
	 * @param int    $annee       Millésime de l'exercice.
	 * @return array{0:string,1:string} Début et fin, en JJ/MM/AAAA.
	 */
	public static function displayBounds( $debut_jj_mm, $fin_jj_mm, $annee ) {
		$debut = self::normalize( $debut_jj_mm, self::DEBUT_DEFAUT );
		$fin   = self::normalize( $fin_jj_mm, self::FIN_DEFAUT );
		$annee = (int) $annee;

		$jd = (int) substr( $debut, 0, 2 );
		$md = (int) substr( $debut, 3, 2 );
		$jf = (int) substr( $fin, 0, 2 );
		$mf = (int) substr( $fin, 3, 2 );

		$annee_fin = ( $mf < $md || ( $mf === $md && $jf < $jd ) ) ? $annee + 1 : $annee;

		return array( $debut . '/' . $annee, $fin . '/' . $annee_fin );
	}

	/**
	 * Les mêmes bornes, au format des comparaisons de dates : AAAA-MM-JJ.
	 *
	 * C'est celui-là qu'attendent les requêtes et les tris. Le faire dériver du
	 * précédent garantit qu'un exercice affiché et un exercice calculé
	 * désignent toujours la même période — la divergence entre les deux est
	 * exactement le genre d'écart que personne ne remarque avant l'audit.
	 *
	 * @param string $debut_jj_mm
	 * @param string $fin_jj_mm
	 * @param int    $annee
	 * @return array{0:string,1:string}
	 */
	public static function sqlBounds( $debut_jj_mm, $fin_jj_mm, $annee ) {
		list( $d, $f ) = self::displayBounds( $debut_jj_mm, $fin_jj_mm, $annee );
		return array( self::toSql( $d ), self::toSql( $f ) );
	}

	/**
	 * L'exercice auquel appartient une date.
	 *
	 * Sert à proposer le bon millésime par défaut : au 15 mars 2026, avec un
	 * exercice ouvrant le 23 avril, on est encore dans l'exercice 2025. Proposer
	 * 2026 ferait générer un BPF presque vide sans que rien ne le signale.
	 *
	 * @param string $debut_jj_mm
	 * @param string $fin_jj_mm
	 * @param string $date_sql AAAA-MM-JJ
	 * @return int
	 */
	public static function yearOf( $debut_jj_mm, $fin_jj_mm, $date_sql ) {
		$annee = (int) substr( (string) $date_sql, 0, 4 );
		if ( $annee <= 0 ) {
			return 0;
		}
		list( $d, $f ) = self::sqlBounds( $debut_jj_mm, $fin_jj_mm, $annee );
		if ( $date_sql >= $d && $date_sql <= $f ) {
			return $annee;
		}
		/* Avant l'ouverture : on est encore dans l'exercice précédent. */
		return $annee - 1;
	}

	/* ── Outils ─────────────────────────────────────────────────────────── */

	/**
	 * Ramène une saisie à « JJ/MM ».
	 *
	 * Le champ est libre : on y trouve « 23/04 », « 23/04/ » et « 23/04/2025 ».
	 * Une valeur qu'on ne sait pas lire rend le défaut plutôt qu'une date
	 * absurde — un exercice mal borné ne se voit pas sur le PDF, il se voit sur
	 * les chiffres, bien plus tard.
	 */
	private static function normalize( $valeur, $defaut ) {
		$v = trim( (string) $valeur );
		if ( preg_match( '#^(\d{1,2})/(\d{1,2})#', $v, $m ) ) {
			$j = (int) $m[1];
			$mo = (int) $m[2];
			if ( $j >= 1 && $j <= 31 && $mo >= 1 && $mo <= 12 ) {
				return sprintf( '%02d/%02d', $j, $mo );
			}
		}
		return $defaut;
	}

	/** JJ/MM/AAAA → AAAA-MM-JJ. */
	private static function toSql( $affichage ) {
		if ( preg_match( '#^(\d{2})/(\d{2})/(\d{4})$#', (string) $affichage, $m ) ) {
			return $m[3] . '-' . $m[2] . '-' . $m[1];
		}
		return '';
	}
}
