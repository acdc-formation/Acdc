<?php
/**
 * Scoring de quiz live (type Kahoot) — logique pure et testable.
 *
 * Extrait de l'ancien ACDC_Quizzes_Engine_Trait::qz_calculate_kahoot_score.
 * `clampResponseMs()` matérialise le correctif anti-triche (3.25.101) : le temps
 * de réponse fourni par le client est borné par le temps écoulé mesuré côté serveur.
 *
 * @package ACDC\Support
 */

namespace ACDC\Support;

if ( ! defined( 'ABSPATH' ) && ! defined( 'ACDC_SUPPORT_TESTING' ) ) {
	exit;
}

final class QuizScore {

	const MAX_SCORE      = 1000;
	const NO_LIMIT_SCORE = 1000;
	const SLOW_SCORE     = 500;

	/**
	 * Score Kahoot : 1000 pts pour une réponse instantanée et correcte, décroissant
	 * linéairement jusqu'à 500 pts à la limite de temps. 0 si la réponse est fausse.
	 *
	 * @param bool $is_correct         Réponse correcte ?
	 * @param int  $response_time_ms   Temps de réponse (ms), déjà borné côté serveur.
	 * @param int  $time_limit_seconds Limite de temps de la question (s).
	 * @return int
	 */
	public static function kahoot( $is_correct, $response_time_ms, $time_limit_seconds ) {
		if ( ! $is_correct ) {
			return 0;
		}
		$time_limit_seconds = (int) $time_limit_seconds;
		if ( $time_limit_seconds <= 0 ) {
			return self::NO_LIMIT_SCORE;
		}
		$response_seconds = max( 0.0, ( (int) $response_time_ms ) / 1000.0 );
		if ( $response_seconds >= $time_limit_seconds ) {
			return self::SLOW_SCORE;
		}
		$ratio = $response_seconds / (float) $time_limit_seconds;
		return (int) round( self::MAX_SCORE - ( self::SLOW_SCORE * $ratio ) );
	}

	/**
	 * Temps de réponse retenu pour le scoring, résistant à la triche.
	 *
	 * Le temps annoncé par le client n'est PAS fiable : un tricheur peut envoyer
	 * `response_ms = 0` pour maximiser son score. Lorsque le serveur connaît le temps
	 * réellement écoulé depuis l'affichage de la question, cette valeur (autoritative,
	 * non falsifiable) prime. On ne retombe sur la valeur client que si le serveur
	 * n'a pas pu mesurer l'écoulé (0 = inconnu).
	 *
	 * NB : un `min(client, serveur)` serait inefficace (min(0, écoulé) = 0 laisserait
	 * le tricheur au score maximum) — d'où l'usage de la valeur serveur comme référence.
	 *
	 * @param int $client_ms         Temps annoncé par le client (ms) — non fiable.
	 * @param int $server_elapsed_ms Temps écoulé mesuré côté serveur (ms). 0 = inconnu.
	 * @return int Temps de réponse retenu, jamais négatif.
	 */
	public static function clampResponseMs( $client_ms, $server_elapsed_ms ) {
		$server_elapsed_ms = (int) $server_elapsed_ms;
		if ( $server_elapsed_ms > 0 ) {
			return $server_elapsed_ms;
		}
		return max( 0, (int) $client_ms );
	}
}
