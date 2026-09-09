<?php
/**
 * Les indicateurs de résultats : ce qu'on additionne, et ce qu'on ne moyenne pas.
 *
 * Ces chiffres ne restent pas dans l'application : le SAAS les pousse vers le
 * site commercial, où ils s'affichent en gros sur la page d'accueil et sur
 * chaque page formation. Ils engagent l'organisme — l'indicateur 2 du
 * référentiel Qualiopi demande des résultats publiés et étayés.
 *
 * Trois erreurs à éviter, et cette classe existe pour les rendre impossibles.
 *
 * LA PREMIÈRE : moyenner des moyennes. Une formation notée 5/5 par une personne
 * et une autre notée 3/5 par cinquante ne font pas 4/5. On ne transporte donc
 * jamais de pourcentages : on transporte des COMPTEURS BRUTS — une somme de
 * notes et un nombre de notes — et le pourcentage ne se calcule qu'à la toute
 * fin, une seule fois.
 *
 * LA DEUXIÈME : compter deux fois la même personne. Une même formation existe
 * en plusieurs lignes dans le SAAS — une par modalité, présentiel et distanciel
 * — et un apprenant peut figurer dans les deux. Les compteurs transportent donc
 * des IDENTIFIANTS, pas des totaux, et la réunion se fait sur les identifiants.
 *
 * LA TROISIÈME, la plus grave : afficher zéro quand on ne sait pas. Zéro pour
 * cent de satisfaction est une affirmation, pas une absence. Un indicateur sans
 * mesure rend null, et c'est à l'appelant de décider s'il se tait ou s'il
 * affiche la valeur déclarée à la main — mais il le fait en le sachant.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class Indicators {

	/** Un bloc de compteurs vide, prêt à être rempli puis réuni. */
	public static function emptyCounters() {
		return array(
			'notes_sum'    => 0.0,  // Somme des notes de satisfaction (échelle 1-5).
			'notes_count'  => 0,    // Nombre de ces notes.
			'reco_sum'     => 0.0,  // Somme des notes de recommandation.
			'reco_count'   => 0,
			'eval_passed'  => 0,    // Évaluations des acquis réussies.
			'eval_total'   => 0,    // Évaluations des acquis répondues.
			'learner_ids'  => array(),
		);
	}

	/**
	 * Réunit plusieurs blocs de compteurs.
	 *
	 * C'est ici qu'on additionne présentiel et distanciel : deux lignes du SAAS,
	 * une seule formation aux yeux du site commercial et de l'apprenant.
	 *
	 * @param array[] $blocs Blocs de compteurs.
	 * @return array Bloc réuni.
	 */
	public static function merge( array $blocs ) {
		$total = self::emptyCounters();
		$ids   = array();
		foreach ( $blocs as $bloc ) {
			if ( ! is_array( $bloc ) ) { continue; }
			$total['notes_sum']   += isset( $bloc['notes_sum'] ) ? (float) $bloc['notes_sum'] : 0.0;
			$total['notes_count'] += isset( $bloc['notes_count'] ) ? (int) $bloc['notes_count'] : 0;
			$total['reco_sum']    += isset( $bloc['reco_sum'] ) ? (float) $bloc['reco_sum'] : 0.0;
			$total['reco_count']  += isset( $bloc['reco_count'] ) ? (int) $bloc['reco_count'] : 0;
			$total['eval_passed'] += isset( $bloc['eval_passed'] ) ? (int) $bloc['eval_passed'] : 0;
			$total['eval_total']  += isset( $bloc['eval_total'] ) ? (int) $bloc['eval_total'] : 0;
			foreach ( (array) ( $bloc['learner_ids'] ?? array() ) as $id ) {
				$ids[ (int) $id ] = true;
			}
		}
		$total['learner_ids'] = array_map( 'intval', array_keys( $ids ) );
		return $total;
	}

	/**
	 * Les taux, calculés une seule fois, à la fin.
	 *
	 * @param array $counters Bloc de compteurs.
	 * @return array{taux_satisfaction:?int,taux_recommandation:?int,taux_reussite:?int,nb_apprenants:int}
	 */
	public static function rates( array $counters ) {
		return array(
			'taux_satisfaction'   => self::noteToPercent(
				$counters['notes_sum'] ?? 0.0,
				$counters['notes_count'] ?? 0
			),
			'taux_recommandation' => self::noteToPercent(
				$counters['reco_sum'] ?? 0.0,
				$counters['reco_count'] ?? 0
			),
			'taux_reussite'       => self::percent(
				$counters['eval_passed'] ?? 0,
				$counters['eval_total'] ?? 0
			),
			'nb_apprenants'       => count( (array) ( $counters['learner_ids'] ?? array() ) ),
		);
	}

	/**
	 * Une moyenne de notes sur 5, ramenée en pourcentage.
	 *
	 * @param float $sum   Somme des notes.
	 * @param int   $count Nombre de notes.
	 * @return int|null Pourcentage, ou null si personne n'a répondu.
	 */
	public static function noteToPercent( $sum, $count ) {
		$count = (int) $count;
		if ( $count < 1 ) {
			return null;
		}
		$moyenne = (float) $sum / $count;
		/* Une note hors échelle est une donnée abîmée, pas un résultat : on la
		   ramène plutôt que de publier 140 % de satisfaction. */
		$moyenne = max( 0.0, min( 5.0, $moyenne ) );
		return (int) round( ( $moyenne / 5.0 ) * 100 );
	}

	/**
	 * Une part rapportée à un total.
	 *
	 * @param int $part  Numérateur.
	 * @param int $total Dénominateur.
	 * @return int|null Pourcentage, ou null si le total est nul.
	 */
	public static function percent( $part, $total ) {
		$total = (int) $total;
		if ( $total < 1 ) {
			return null;
		}
		$pct = ( (int) $part / $total ) * 100;
		return (int) round( max( 0.0, min( 100.0, $pct ) ) );
	}

	/**
	 * Ce qu'on publie, et d'où ça vient.
	 *
	 * Quand la mesure existe, elle gagne toujours. Quand elle n'existe pas
	 * encore — un organisme qui démarre, une formation jamais évaluée — on
	 * retombe sur la valeur saisie à la main, mais on le DIT : « déclaré »
	 * n'est pas « mesuré », et un chiffre publié doit pouvoir être défendu
	 * devant un auditeur.
	 *
	 * @param int|null $mesure  Valeur calculée depuis les enquêtes.
	 * @param mixed    $declare Valeur saisie sur la fiche formation.
	 * @return array{value:int,source:string}
	 */
	public static function publish( $mesure, $declare ) {
		if ( null !== $mesure ) {
			return array( 'value' => (int) $mesure, 'source' => 'mesuré' );
		}
		$declare = (int) $declare;
		if ( $declare > 0 ) {
			return array( 'value' => $declare, 'source' => 'déclaré' );
		}
		return array( 'value' => 0, 'source' => 'aucune mesure' );
	}
}
