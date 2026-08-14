<?php
/**
 * « À chaque fois j'oublie le contrat du formateur. »
 *
 * Une convention signée engage une intervention ; l'intervention suppose un
 * formateur ; le formateur suppose un contrat. Le troisième maillon était le
 * seul que rien ne réclamait : la convention partait signée, la session se
 * planifiait, les convocations partaient — et le contrat manquait sans qu'aucun
 * écran ne s'en émeuve.
 *
 * Le rappel se DÉDUIT des données, il ne se coche pas. Il s'éteint tout seul le
 * jour où le contrat existe, et personne n'a à se souvenir de l'éteindre — un
 * rappel qu'il faut penser à fermer a le même défaut que l'oubli qu'il corrige.
 *
 * Trois états, deux niveaux :
 *
 *   formateur non désigné → rouge, on ne peut même pas commencer ;
 *   aucun contrat         → rouge, c'est l'oubli visé ;
 *   contrat non signé     → orange, la pièce existe, elle attend le formateur.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class TrainerContractReminder {

	public const RIEN        = 'none';
	public const SANS_FORMATEUR = 'no_trainer';
	public const SANS_CONTRAT   = 'no_contract';
	public const NON_SIGNE      = 'unsigned';

	/** Au-delà, une formation terminée n'a plus à clignoter tous les matins. */
	public const JOURS_APRES_FIN = 30;

	/**
	 * Que faut-il rappeler pour cette convention ?
	 *
	 * @param int  $trainer_id      Formateur désigné sur les séances ; 0 si aucun.
	 * @param bool $has_contract    Un contrat couvre-t-il cette formation sur cette période ?
	 * @param bool $contract_signed Est-il signé ?
	 * @return array{reason:string,level:string,label:string}
	 */
	public static function evaluate( $trainer_id, $has_contract, $contract_signed ) {
		if ( (int) $trainer_id < 1 ) {
			return array(
				'reason' => self::SANS_FORMATEUR,
				'level'  => 'crit',
				'label'  => 'Formateur non désigné — désignez-le puis établissez le contrat',
			);
		}
		if ( ! $has_contract ) {
			return array(
				'reason' => self::SANS_CONTRAT,
				'level'  => 'crit',
				'label'  => 'Contrat formateur à établir',
			);
		}
		if ( ! $contract_signed ) {
			return array(
				'reason' => self::NON_SIGNE,
				'level'  => 'warn',
				'label'  => 'Contrat formateur en attente de signature',
			);
		}
		/* Contrat établi ET signé : plus rien à rappeler. C'est la sortie
		   normale, et elle survient sans que personne n'ait rien fermé. */
		return array( 'reason' => self::RIEN, 'level' => '', 'label' => '' );
	}

	/** Le rappel a-t-il encore un sens pour cette convention ? */
	public static function isRelevant( $signed, $formation_id, $end_date, $today ) {
		if ( ! $signed ) {
			return false;
		}
		/* Sans formation, il n'y a pas d'intervention à contractualiser. */
		if ( (int) $formation_id < 1 ) {
			return false;
		}
		$end_date = trim( (string) $end_date );
		if ( '' === $end_date ) {
			/* Pas de date de fin : la convention est encore ouverte. */
			return true;
		}
		$fin  = strtotime( $end_date . ' 00:00:00' );
		$jour = strtotime( trim( (string) $today ) . ' 00:00:00' );
		if ( ! $fin || ! $jour ) {
			return true;
		}
		return ( $jour - $fin ) <= ( self::JOURS_APRES_FIN * DAY_IN_SECONDS );
	}
}
