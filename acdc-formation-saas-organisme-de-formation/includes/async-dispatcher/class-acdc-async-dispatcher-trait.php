<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * ACDC Formation SAAS — Async Dispatcher (Trait socle)
 *
 * Couvre le socle commun aux mécanismes asynchrones (token + email + relance
 * + expiration), partagé par :
 *   - le module Quizzes (3.21+) pour les tests de positionnement et
 *     les évaluations des acquis envoyés par email ;
 *   - le futur module Surveys (3.22+) pour les enquêtes Qualiopi.
 *
 * En 3.21.00, ce trait est volontairement minimal : seules les méthodes
 * utilitaires de génération/validation de token sont déjà finalisées,
 * le reste est des stubs documentés pour cadrer l'API future sans la coder.
 *
 * @package ACDC_OF_SAAS
 * @since   3.21.00
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Async_Dispatcher_Trait {

    /**
     * Génère un token aléatoire sécurisé pour un participant.
     *
     * @param int $length Longueur de la base aléatoire (avant suffixe).
     *
     * @return string Token (longueur ~52 à 60 caractères).
     */
    public function async_dispatcher_generate_token( $length = 48 ) {
        $length = max( 24, (int) $length );
        return wp_generate_password( $length, false, false ) . dechex( time() );
    }

    /**
     * Calcule l'horodatage d'expiration d'un token à partir d'un nombre de jours.
     *
     * @param int $days Nombre de jours avant expiration (par défaut 30).
     *
     * @return string Date au format MySQL.
     */
    public function async_dispatcher_compute_expiry( $days = 30 ) {
        $days = max( 1, (int) $days );
        return gmdate( 'Y-m-d H:i:s', time() + ( $days * DAY_IN_SECONDS ) );
    }

    /**
     * Indique si une date d'expiration est dépassée.
     *
     * @param string|null $expires_at Date MySQL (UTC).
     *
     * @return bool
     */
    public function async_dispatcher_is_expired( $expires_at ) {
        if ( empty( $expires_at ) ) {
            return false;
        }
        $ts = strtotime( (string) $expires_at . ' UTC' );
        if ( ! $ts ) {
            return false;
        }
        return ( $ts < time() );
    }

    /* ====================================================================
     *  Stubs documentés (à finaliser en 3.21.03+ et 3.22+)
     * ==================================================================== */

    /**
     * Envoi d'un email à un destinataire d'une campagne async.
     *
     * Signature stable, logique à implémenter dans une sous-version ultérieure.
     *
     * @param array $context {
     *   @type string $to
     *   @type string $subject
     *   @type string $body_html
     *   @type array  $headers
     * }
     *
     * @return bool
     */
    public function async_dispatcher_send_email( $context ) {
        // 3.21.00 : non implémenté ; les modules continuent d'utiliser wp_mail() directement.
        return false;
    }

    /**
     * Programme une relance pour un destinataire d'une campagne async.
     *
     * @param int    $recipient_id
     * @param string $send_at      Date MySQL (UTC).
     *
     * @return bool
     */
    public function async_dispatcher_schedule_reminder( $recipient_id, $send_at ) {
        return false;
    }
}
