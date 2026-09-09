<?php
/**
 * La séance née d'une convention, et ce qu'elle autorise.
 *
 * La convention signée crée les séances : c'est ce qui a permis de supprimer
 * une double saisie. Mais elle ne sait pas TOUT dire — on ne peut pas y
 * désigner le formateur au moment où on la rédige, puisque c'est souvent ce
 * qui se décide en dernier. La séance naissait donc validée, complète aux yeux
 * de l'application, et pourtant sans personne pour l'animer. L'apprenant
 * recevait la veille à 17 h une convocation annonçant une journée dont le
 * formateur n'existait pas.
 *
 * D'où cette porte, et sa règle unique :
 *
 *   UNE SÉANCE SANS FORMATEUR EST UN BROUILLON.
 *
 * Le brouillon n'est pas une file d'attente administrative de plus. C'est une
 * question posée — « qui anime ? » — et elle se referme d'elle-même dès que la
 * réponse arrive, d'où qu'elle vienne. Retenir la séance après ça n'ajouterait
 * qu'un clic à une application dont David dit déjà qu'elle en demande trop.
 *
 * Ce qu'un brouillon retient, et ce qu'il ne retient pas, tient en une phrase.
 * Il retient la CONVOCATION : elle annonce un lieu, des horaires et un
 * intervenant, et une convocation fausse est pire qu'une convocation tardive.
 * Il ne retient PAS l'extranet apprenant : l'apprenant est engagé, son espace
 * lui revient, et rien de ce qu'il y trouve ne dépend du formateur.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class SessionDraftGate {

	/** Statut porté par une séance en attente de son formateur. */
	const STATUT_BROUILLON = 'Brouillon';

	/** Statut d'une séance validée, celui qu'utilise déjà tout le module. */
	const STATUT_PLANIFIEE = 'Planifiée';

	/**
	 * L'état de naissance d'une séance créée par une convention signée.
	 *
	 * @param mixed $trainer_id Formateur désigné sur la convention, ou 0.
	 * @return array{is_draft:int,status:string}
	 */
	public static function forNewSession( $trainer_id ) {
		return self::hasTrainer( $trainer_id )
			? array( 'is_draft' => 0, 'status' => self::STATUT_PLANIFIEE )
			: array( 'is_draft' => 1, 'status' => self::STATUT_BROUILLON );
	}

	/**
	 * Une séance déjà en base doit-elle sortir du brouillon ?
	 *
	 * Vrai dans un seul cas : elle était en brouillon, elle n'avait pas de
	 * formateur, et on vient d'en désigner un. On ne libère jamais une séance
	 * qu'un humain a délibérément mise en brouillon depuis le constructeur de
	 * séances alors qu'elle avait déjà son formateur — ce serait décider à sa
	 * place.
	 *
	 * @param mixed $etait_brouillon    État actuel de la séance.
	 * @param mixed $formateur_actuel   Formateur déjà en place, ou 0.
	 * @param mixed $formateur_designe  Formateur que l'on vient de désigner.
	 * @return bool
	 */
	public static function shouldRelease( $etait_brouillon, $formateur_actuel, $formateur_designe ) {
		if ( ! self::isDraftFlag( $etait_brouillon ) ) {
			return false;
		}
		if ( self::hasTrainer( $formateur_actuel ) ) {
			return false;
		}
		return self::hasTrainer( $formateur_designe );
	}

	/**
	 * Une séance en brouillon retient-elle la convocation ?
	 *
	 * Rendre null n'est pas rendre « oui » : quand aucune séance n'est
	 * rattachée, ce n'est pas à cette porte de trancher — le moteur a déjà son
	 * étape « séance manquante » pour le dire.
	 *
	 * @param object|null $session Séance ancrant le parcours.
	 * @return bool
	 */
	public static function holdsConvocation( $session ) {
		if ( empty( $session ) || ! is_object( $session ) ) {
			return false;
		}
		return self::isDraft( $session );
	}

	/**
	 * La séance est-elle en brouillon ?
	 *
	 * Deux colonnes portent la même vérité depuis longtemps — le drapeau et le
	 * statut — et les écrans les lisent toujours ensemble. On fait pareil ici,
	 * plutôt que d'en élire une et de laisser l'autre mentir.
	 *
	 * @param object|null $session Séance.
	 * @return bool
	 */
	public static function isDraft( $session ) {
		if ( empty( $session ) || ! is_object( $session ) ) {
			return false;
		}
		if ( self::isDraftFlag( $session->is_draft ?? 0 ) ) {
			return true;
		}
		return self::STATUT_BROUILLON === (string) ( $session->status ?? '' );
	}

	/**
	 * Peut-on valider cette séance ?
	 *
	 * Une séance validée est une séance dont on sait qui l'anime. Valider sans
	 * formateur reviendrait à rouvrir exactement le trou que le brouillon
	 * bouche, et à le rouvrir d'un clic rassurant.
	 *
	 * @param object|null $session Séance à valider.
	 * @return array{ok:bool,error:string}
	 */
	public static function canValidate( $session ) {
		if ( empty( $session ) || ! is_object( $session ) ) {
			return array( 'ok' => false, 'error' => 'Séance introuvable.' );
		}
		if ( ! self::isDraft( $session ) ) {
			return array( 'ok' => false, 'error' => 'Cette séance est déjà validée.' );
		}
		if ( ! self::hasTrainer( $session->trainer_id ?? 0 ) ) {
			return array(
				'ok'    => false,
				'error' => 'Désignez le formateur avant de valider : la convocation annonce un intervenant, et un apprenant convoqué sans formateur se présente pour rien.',
			);
		}
		return array( 'ok' => true, 'error' => '' );
	}

	/**
	 * Le motif du brouillon, dit en une phrase à qui regarde l'écran.
	 *
	 * @param object|null $session Séance.
	 * @return string Chaîne vide si la séance n'est pas en brouillon.
	 */
	public static function reason( $session ) {
		if ( ! self::isDraft( $session ) ) {
			return '';
		}
		if ( ! self::hasTrainer( $session->trainer_id ?? 0 ) ) {
			return 'Formateur à désigner';
		}
		return 'À valider';
	}

	private static function hasTrainer( $trainer_id ) {
		return (int) $trainer_id > 0;
	}

	private static function isDraftFlag( $value ) {
		return 1 === (int) $value;
	}
}
