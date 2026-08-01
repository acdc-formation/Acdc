<?php
/**
 * Formules & options activables (feature-flags) — socle SaaS multi-formules.
 *
 * Décrit des **formules** (offres) associées chacune à un ensemble de **fonctionnalités** activées
 * et de **limites** chiffrées (nombre d'apprenants, de sessions…). Permet de répondre simplement à
 * « telle fonctionnalité est-elle incluse dans la formule du client ? » et « quelle est sa limite ? ».
 * C'est la fondation d'une offre commercialisable à plusieurs organismes (marque blanche, plans).
 *
 * Les formules par défaut sont un point de départ, surchargées via le filtre `acdc_feature_plans`.
 * Logique pure, testable ; aucune décision tarifaire figée dans le code.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class FeaturePlan {

	const DEFAULT_PLAN = 'solo';

	/**
	 * Catalogue des formules par défaut.
	 *
	 * @return array<string,array{label:string,features:string[],limits:array<string,int>}>
	 */
	public static function catalogDefaults() {
		return array(
			'solo' => array(
				'label'    => 'Solo',
				'features' => array( 'crm', 'sessions', 'quizzes', 'emargement', 'billing', 'evaluations' ),
				'limits'   => array( 'learners' => 200, 'sessions' => 100, 'trainers' => 1 ),
			),
			'pro' => array(
				'label'    => 'Pro',
				'features' => array( 'crm', 'sessions', 'quizzes', 'emargement', 'billing', 'evaluations', 'signature', 'watch', 'live_quiz', 'trainer_portal' ),
				'limits'   => array( 'learners' => 2000, 'sessions' => 1000, 'trainers' => 10 ),
			),
			'business' => array(
				'label'    => 'Business',
				'features' => array( 'crm', 'sessions', 'quizzes', 'emargement', 'billing', 'evaluations', 'signature', 'watch', 'live_quiz', 'trainer_portal', 'learner_portal', 'lms', 'api', 'white_label' ),
				'limits'   => array( 'learners' => -1, 'sessions' => -1, 'trainers' => -1 ), // -1 = illimité
			),
		);
	}

	/**
	 * Catalogue effectif (défauts + surcharge éventuelle via filtre WordPress).
	 *
	 * @return array
	 */
	public static function catalog() {
		$catalog = self::catalogDefaults();
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'acdc_feature_plans', $catalog );
			if ( is_array( $filtered ) && ! empty( $filtered ) ) {
				$catalog = $filtered;
			}
		}
		return $catalog;
	}

	/**
	 * Vrai si la formule existe.
	 *
	 * @param string $plan
	 * @return bool
	 */
	public static function exists( $plan ) {
		$catalog = self::catalog();
		return isset( $catalog[ (string) $plan ] );
	}

	/**
	 * Liste des fonctionnalités activées pour une formule (vide si formule inconnue).
	 *
	 * @param string $plan
	 * @return string[]
	 */
	public static function features( $plan ) {
		$catalog = self::catalog();
		$plan    = (string) $plan;
		if ( ! isset( $catalog[ $plan ]['features'] ) || ! is_array( $catalog[ $plan ]['features'] ) ) {
			return array();
		}
		return array_values( $catalog[ $plan ]['features'] );
	}

	/**
	 * Une fonctionnalité est-elle incluse dans la formule ?
	 *
	 * @param string $plan
	 * @param string $feature
	 * @return bool
	 */
	public static function can( $plan, $feature ) {
		return in_array( (string) $feature, self::features( $plan ), true );
	}

	/**
	 * Limite chiffrée d'une formule (-1 = illimité). Renvoie $default si non définie.
	 *
	 * @param string $plan
	 * @param string $key
	 * @param int    $default
	 * @return int
	 */
	public static function limit( $plan, $key, $default = 0 ) {
		$catalog = self::catalog();
		$plan    = (string) $plan;
		if ( isset( $catalog[ $plan ]['limits'][ (string) $key ] ) ) {
			return (int) $catalog[ $plan ]['limits'][ (string) $key ];
		}
		return (int) $default;
	}

	/**
	 * Vrai si l'usage courant reste dans la limite de la formule (toujours vrai si illimité).
	 *
	 * @param string $plan
	 * @param string $key
	 * @param int    $current_usage
	 * @return bool
	 */
	public static function withinLimit( $plan, $key, $current_usage ) {
		$limit = self::limit( $plan, $key, -1 );
		if ( $limit < 0 ) {
			return true; // illimité (ou limite non définie).
		}
		return (int) $current_usage <= $limit;
	}

	/**
	 * Libellé lisible d'une formule (clé brute si inconnue).
	 *
	 * @param string $plan
	 * @return string
	 */
	public static function label( $plan ) {
		$catalog = self::catalog();
		$plan    = (string) $plan;
		return isset( $catalog[ $plan ]['label'] ) ? (string) $catalog[ $plan ]['label'] : $plan;
	}
}
