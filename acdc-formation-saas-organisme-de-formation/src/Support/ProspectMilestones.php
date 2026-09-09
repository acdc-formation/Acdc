<?php
/**
 * Les jalons franchis par un prospect — et non le seul statut qu'il porte.
 *
 * Le suivi commercial comptait le STATUT COURANT : un prospect, une pastille.
 * Un prospect à « Proposition envoyée » affichait donc « Recueil envoyé 0 »
 * alors que son recueil était bien parti, et « Devis envoyé 0 » alors que son
 * devis l'était aussi. Ce n'est pas une perte de donnée : un statut ne retient
 * qu'une étape, c'est sa nature. Mais le parcours en compte plusieurs.
 *
 * Deux règles, et elles se complètent :
 *
 *   — un jalon est franchi quand un FAIT l'établit (un recueil avec sa date
 *     d'envoi, une proposition envoyée, un devis émis, un rendez-vous non
 *     annulé, une convention signée) ;
 *   — le statut courant est TOUJOURS un jalon, même sans fait derrière lui.
 *     Sans quoi une ligne afficherait « Proposition envoyée » dans sa colonne
 *     Statut sans être joignable par la pastille du même nom : l'écran se
 *     contredirait lui-même.
 *
 * « Perdu » fait exception : il ferme le dossier. Continuer d'y compter les
 * étapes franchies laisserait croire à une affaire vivante.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class ProspectMilestones {

	/** Statut qui clôt le parcours : il efface les jalons antérieurs. */
	public const PERDU = 'Perdu';

	/**
	 * Les jalons d'un prospect, dans l'ordre du parcours.
	 *
	 * @param string[] $faits  Jalons établis par un objet réel.
	 * @param string   $statut Statut courant du prospect.
	 * @param array    $ordre  Libellé => rang, pour trier.
	 * @return string[]
	 */
	public static function forProspect( $faits, $statut, $ordre = array() ) {
		$statut = '' !== trim( (string) $statut ) ? (string) $statut : 'À traiter';

		if ( self::PERDU === $statut ) {
			return array( self::PERDU );
		}

		$jalons = array();
		foreach ( (array) $faits as $fait ) {
			$fait = (string) $fait;
			/* Un dossier perdu ne peut pas être un jalon d'un dossier vivant :
			   seul le statut courant peut le porter. */
			if ( '' === $fait || self::PERDU === $fait ) {
				continue;
			}
			$jalons[ $fait ] = true;
		}
		$jalons[ $statut ] = true;

		$jalons = array_keys( $jalons );
		usort( $jalons, static function ( $a, $b ) use ( $ordre ) {
			$ra = isset( $ordre[ $a ] ) ? (int) $ordre[ $a ] : 99;
			$rb = isset( $ordre[ $b ] ) ? (int) $ordre[ $b ] : 99;
			if ( $ra === $rb ) {
				return strcmp( (string) $a, (string) $b );
			}
			return $ra <=> $rb;
		} );
		return $jalons;
	}

	/**
	 * Compte les prospects par jalon.
	 *
	 * La somme des compteurs dépasse volontairement le nombre de prospects :
	 * un prospect avancé coche plusieurs étapes. C'est ce qu'on veut voir, et
	 * c'est écrit sous la barre pour que ce ne soit pas pris pour une erreur.
	 *
	 * @param array $jalons_par_prospect Liste de listes de jalons.
	 * @param array $libelles            Libellés attendus, pour que les vides existent à 0.
	 * @return array<string,int>
	 */
	public static function counts( $jalons_par_prospect, $libelles = array() ) {
		$counts = array();
		foreach ( (array) $libelles as $label ) {
			$counts[ (string) $label ] = 0;
		}
		foreach ( (array) $jalons_par_prospect as $jalons ) {
			foreach ( (array) $jalons as $jalon ) {
				$jalon = (string) $jalon;
				if ( ! isset( $counts[ $jalon ] ) ) {
					$counts[ $jalon ] = 0;
				}
				$counts[ $jalon ]++;
			}
		}
		return $counts;
	}
}
