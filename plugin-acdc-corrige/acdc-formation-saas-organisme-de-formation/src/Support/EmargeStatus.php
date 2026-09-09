<?php
/**
 * Dérivation des statuts de présence / signature d'une séance à partir des compteurs
 * d'émargement — logique pure et testable (utilisée après préchargement anti-N+1).
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class EmargeStatus {

	/**
	 * @param bool $has_emarg Une fiche d'émargement existe-t-elle pour la séance ?
	 * @param int  $total     Nombre d'apprenants sur la fiche.
	 * @param int  $signed    Nombre d'apprenants ayant signé (status = 'signe').
	 * @param int  $absent    Nombre d'apprenants marqués absents.
	 * @return array{signature:string,presence:string}
	 */
	public static function deriveLabels( $has_emarg, $total, $signed, $absent ) {
		if ( ! $has_emarg ) {
			return array(
				'signature' => 'Non générée',
				'presence'  => 'Non générée',
			);
		}
		$total  = (int) $total;
		$signed = (int) $signed;
		$absent = (int) $absent;

		if ( 0 === $total ) {
			return array(
				'signature' => 'En attente',
				'presence'  => 'En attente',
			);
		}
		if ( $signed === $total ) {
			return array(
				'signature' => 'Complète',
				'presence'  => 'Complète',
			);
		}
		if ( $signed > 0 ) {
			return array(
				'signature' => 'Partielle',
				'presence'  => 'Partielle',
			);
		}
		return array(
			'signature' => 'En attente',
			'presence'  => ( $absent > 0 ) ? 'Absences' : 'En attente',
		);
	}

	/**
	 * Compte signés / absents à partir d'une liste de lignes d'apprenants d'émargement.
	 *
	 * @param array $learners Objets/tableaux portant une propriété/clé 'status'.
	 * @return array{total:int,signed:int,absent:int}
	 */
	public static function countStatuses( $learners ) {
		$total = 0;
		$signed = 0;
		$absent = 0;
		foreach ( (array) $learners as $l ) {
			$status = is_object( $l ) ? ( $l->status ?? '' ) : ( is_array( $l ) ? ( $l['status'] ?? '' ) : '' );
			$total++;
			if ( 'signe' === $status ) {
				$signed++;
			} elseif ( 'absent' === $status ) {
				$absent++;
			}
		}
		return array(
			'total'  => $total,
			'signed' => $signed,
			'absent' => $absent,
		);
	}
}
