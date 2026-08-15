<?php
/**
 * Ce que le site vitrine annonce, et d'où chaque chiffre vient.
 *
 * Deux activités coexistent et ne se mélangent pas :
 *
 *   L'ORGANISME — ses formations, ses séances, ses apprenants. C'est lui que
 *   mesure le BPF, et lui seul que mesurent les taux : satisfaction, réussite
 *   et recommandation reposent sur des enquêtes, et il n'y a d'enquête que
 *   pour ses propres apprenants.
 *
 *   LES PRESTATIONS EXTÉRIEURES — les interventions animées pour le compte
 *   d'autres organismes. Elles appartiennent au formateur, pas à l'organisme.
 *   Elles ne portent aucune enquête, donc aucun taux.
 *
 * Le site vitrine veut l'expérience TOTALE du formateur ; le BPF veut
 * l'activité de l'organisme SEUL. Les deux ont raison, et c'est pourquoi on
 * publie les deux plutôt que d'en écraser une : jusqu'ici les chiffres de la
 * page d'accueil étaient recopiés à la main dans les réglages du site vitrine,
 * précisément parce qu'aucun des deux totaux ne convenait à lui seul.
 *
 * DEUX UNITÉS D'HEURES, QU'IL NE FAUT JAMAIS ADDITIONNER À L'AVEUGLE :
 *
 *   heures DISPENSÉES — celles passées à animer. Une journée de 7 h devant
 *   douze personnes en fait 7.
 *   heures SUIVIES (dites « heures-stagiaires ») — la durée multipliée par le
 *   nombre de participants. La même journée en fait 84.
 *
 * Les deux sont vraies, aucune n'est « la bonne », et les confondre produit un
 * chiffre qui ne veut rien dire. Le site affiche les heures suivies, sous ce
 * nom-là ; le cadre E du BPF veut les heures dispensées.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class PublicIndicators {

	/**
	 * Le total des prestations extérieures, lu sur les fiches enregistrées.
	 *
	 * Chaque fiche porte un nombre de stagiaires, une durée par stagiaire et
	 * une durée totale. On ne recalcule pas la seconde à partir des deux
	 * autres : une saisie peut légitimement s'en écarter — une session où tout
	 * le monde n'assiste pas à tout — et recalculer effacerait ce que quelqu'un
	 * a constaté. On ne complète que ce qui manque.
	 *
	 * @param array<int,array<string,mixed>> $records Fiches de prestation.
	 * @return array{stagiaires:int,heures_dispensees:float,heures_suivies:float,ca_ht:float,nb:int}
	 */
	public static function externalTotals( $records ) {
		$totaux = array(
			'stagiaires'        => 0,
			'heures_dispensees' => 0.0,
			'heures_suivies'    => 0.0,
			'ca_ht'             => 0.0,
			'nb'                => 0,
		);
		if ( ! is_array( $records ) ) {
			return $totaux;
		}

		foreach ( $records as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$stagiaires = max( 0, (int) self::num( $r, 'nb_stagiaires' ) );
			$par_stag   = max( 0.0, self::num( $r, 'heures_par_stagiaire' ) );
			$suivies    = max( 0.0, self::num( $r, 'heures_total' ) );

			/* Une fiche sans total de suivies mais qui porte les deux autres
			   valeurs se complète d'elle-même. L'inverse ne se déduit pas :
			   sans le nombre de stagiaires, on ne sait pas diviser. */
			if ( 0.0 === $suivies && $par_stag > 0 && $stagiaires > 0 ) {
				$suivies = $par_stag * $stagiaires;
			}

			$totaux['stagiaires']        += $stagiaires;
			$totaux['heures_dispensees'] += $par_stag;
			$totaux['heures_suivies']    += $suivies;
			$totaux['ca_ht']             += self::num( $r, 'ca_ht' );
			$totaux['nb']++;
		}

		$totaux['heures_dispensees'] = round( $totaux['heures_dispensees'], 1 );
		$totaux['heures_suivies']    = round( $totaux['heures_suivies'], 1 );
		$totaux['ca_ht']             = round( $totaux['ca_ht'], 2 );

		return $totaux;
	}

	/**
	 * Les heures SUIVIES de l'organisme : la durée de chaque séance multipliée
	 * par le nombre d'apprenants qui la suivent.
	 *
	 * L'organisme compte ses heures dispensées depuis toujours — c'est ce que
	 * le tableau de bord affiche. Les heures suivies s'en déduisent séance par
	 * séance, jamais globalement : multiplier un total d'heures par un total
	 * d'apprenants donnerait un nombre sans rapport avec la réalité dès que
	 * deux séances n'ont pas le même effectif.
	 *
	 * @param array<int,array{minutes:int|float,apprenants:int}> $seances
	 * @return float
	 */
	public static function attendedHours( $seances ) {
		$total = 0.0;
		foreach ( (array) $seances as $s ) {
			$minutes    = isset( $s['minutes'] ) ? (float) $s['minutes'] : 0.0;
			$apprenants = isset( $s['apprenants'] ) ? (int) $s['apprenants'] : 0;
			if ( $minutes <= 0 || $apprenants <= 0 ) {
				continue;
			}
			$total += ( $minutes / 60 ) * $apprenants;
		}
		return round( $total, 1 );
	}

	/**
	 * Les chiffres publiés, organisme seul et tout compris.
	 *
	 * Les clés d'origine gardent leur sens : `total_apprenants` et
	 * `total_heures` restent l'organisme seul. Un site vitrine qui n'aurait pas
	 * été mis à jour continue donc d'afficher ce qu'il affichait, sans jamais
	 * gonfler ses chiffres à l'insu de qui les publie.
	 *
	 * @param array{apprenants:int,heures_dispensees:float,heures_suivies:float} $organisme
	 * @param array{stagiaires:int,heures_dispensees:float,heures_suivies:float,ca_ht:float,nb:int} $externes
	 * @return array<string,mixed>
	 */
	public static function publishable( $organisme, $externes ) {
		$org_appr    = isset( $organisme['apprenants'] ) ? (int) $organisme['apprenants'] : 0;
		$org_disp    = isset( $organisme['heures_dispensees'] ) ? (float) $organisme['heures_dispensees'] : 0.0;
		$org_suivies = isset( $organisme['heures_suivies'] ) ? (float) $organisme['heures_suivies'] : 0.0;

		$ext_stag    = isset( $externes['stagiaires'] ) ? (int) $externes['stagiaires'] : 0;
		$ext_disp    = isset( $externes['heures_dispensees'] ) ? (float) $externes['heures_dispensees'] : 0.0;
		$ext_suivies = isset( $externes['heures_suivies'] ) ? (float) $externes['heures_suivies'] : 0.0;

		return array(
			'total_apprenants'           => $org_appr,
			'total_heures'               => round( $org_disp, 1 ),
			'total_apprenants_tous'      => $org_appr + $ext_stag,
			'total_heures_dispensees_tous' => round( $org_disp + $ext_disp, 1 ),
			'total_heures_suivies_tous'  => round( $org_suivies + $ext_suivies, 1 ),
			'detail'                     => array(
				'organisme'   => array(
					'apprenants'        => $org_appr,
					'heures_dispensees' => round( $org_disp, 1 ),
					'heures_suivies'    => round( $org_suivies, 1 ),
				),
				'prestations' => array(
					'nb'                => isset( $externes['nb'] ) ? (int) $externes['nb'] : 0,
					'stagiaires'        => $ext_stag,
					'heures_dispensees' => round( $ext_disp, 1 ),
					'heures_suivies'    => round( $ext_suivies, 1 ),
				),
			),
		);
	}

	/** @param array<string,mixed> $row */
	private static function num( array $row, $cle ) {
		if ( ! isset( $row[ $cle ] ) || ! is_scalar( $row[ $cle ] ) ) {
			return 0.0;
		}
		/* Les saisies passent par un formulaire : la virgule décimale y est
		   naturelle, et (float) sur « 7,5 » rendrait 7. */
		return (float) str_replace( ',', '.', (string) $row[ $cle ] );
	}
}
