<?php
/**
 * La fenêtre pendant laquelle un formateur voit les quiz de ses formations.
 *
 * Demandé en recette : « dans l'extranet du formateur, les quiz correspondant
 * aux formations qu'il fait doivent être visibles pendant la formation, mais
 * disparaître quand la formation est terminée — un ou deux jours de battement
 * pour la visibilité et l'effacement. »
 *
 * Sans borne, la liste ne fait que grossir. Au bout d'un an d'exercice, trouver
 * le quiz du jour tient de la fouille — et un quiz qu'on cherche est un quiz
 * qu'on ne lance pas. C'est le même défaut qu'un écran surchargé de lignes
 * mortes : il ne dit pas faux, il rend l'information introuvable.
 *
 * Ce qui disparaît est la liste d'ANIMATION. Les résultats des séances passées,
 * eux, restent consultables sans limite : ce sont les preuves du formateur pour
 * son bilan, et une preuve ne s'efface pas au bout de deux jours.
 *
 * La règle vit ici, en un seul endroit, et la requête ne fait que comparer deux
 * dates qu'on lui donne. Une règle écrite en SQL ne se teste pas, et celle-ci
 * doit pouvoir l'être : ses deux bornes sont réglables, donc modifiables par
 * quelqu'un qui ne relira pas le code.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class QuizWindow {

	/** Jours de visibilité avant la première journée, à défaut de réglage. */
	const AVANT_DEFAUT = 1;

	/** Jours de maintien après la dernière journée, à défaut de réglage. */
	const APRES_DEFAUT = 2;

	/**
	 * Les deux bornes à comparer aux dates d'une séance.
	 *
	 * Une séance entre dans la fenêtre si elle COMMENCE avant la première borne
	 * et se TERMINE après la seconde. Écrit ainsi, le chevauchement se teste
	 * sans se soucier de la durée : une formation de sept jours est visible du
	 * premier au dernier, plus le battement, sans cas particulier.
	 *
	 * @param string $today  Jour de référence (AAAA-MM-JJ).
	 * @param int    $avant  Jours avant le début.
	 * @param int    $apres  Jours après la fin.
	 * @return array{debut_au_plus_tard:string,fin_au_plus_tot:string}
	 */
	public static function bounds( $today, $avant = self::AVANT_DEFAUT, $apres = self::APRES_DEFAUT ) {
		$today = self::normalizeDate( $today );
		$avant = self::clamp( $avant, self::AVANT_DEFAUT );
		$apres = self::clamp( $apres, self::APRES_DEFAUT );

		$ts = strtotime( $today . ' 12:00:00' );

		return array(
			'debut_au_plus_tard' => gmdate( 'Y-m-d', strtotime( '+' . $avant . ' days', $ts ) ),
			'fin_au_plus_tot'    => gmdate( 'Y-m-d', strtotime( '-' . $apres . ' days', $ts ) ),
		);
	}

	/**
	 * Cette séance est-elle dans la fenêtre ?
	 *
	 * @param string $debut Première journée de la séance (AAAA-MM-JJ).
	 * @param string $fin   Dernière journée ; vide vaut « le même jour ».
	 * @param string $today Jour de référence.
	 * @param int    $avant Jours avant le début.
	 * @param int    $apres Jours après la fin.
	 * @return bool
	 */
	public static function isVisible( $debut, $fin, $today, $avant = self::AVANT_DEFAUT, $apres = self::APRES_DEFAUT ) {
		$debut = self::normalizeDate( $debut );
		if ( '' === $debut ) {
			/* Une séance sans date n'a pas de fenêtre : on ne la cache pas —
			   la corriger est une action, la faire disparaître n'en est pas une. */
			return true;
		}
		$fin = self::normalizeDate( $fin );
		if ( '' === $fin || $fin < $debut ) {
			$fin = $debut;
		}

		$bornes = self::bounds( $today, $avant, $apres );

		return $debut <= $bornes['debut_au_plus_tard'] && $fin >= $bornes['fin_au_plus_tot'];
	}

	/**
	 * Un réglage plausible.
	 *
	 * @param mixed $jours   Valeur saisie.
	 * @param int   $defaut  Valeur de repli si la saisie ne dit rien.
	 * @return int
	 */
	public static function clamp( $jours, $defaut = self::AVANT_DEFAUT ) {
		if ( '' === $jours || null === $jours || ! is_numeric( $jours ) ) {
			return (int) $defaut;
		}
		$jours = (int) $jours;
		if ( $jours < 0 ) {
			return 0;
		}
		/* Deux mois de battement n'est plus un battement, c'est une absence de
		   règle : on borne pour qu'un zéro de trop ne ramène pas trois ans
		   d'archives dans la liste du jour. */
		return min( 60, $jours );
	}

	private static function normalizeDate( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value || '0000-00-00' === substr( $value, 0, 10 ) ) {
			return '';
		}
		return substr( $value, 0, 10 );
	}
}
