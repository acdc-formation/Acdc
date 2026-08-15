<?php
defined( 'ABSPATH' ) || exit;

/**
 * ACDC Indicateurs — API
 * Consomme GET /wp-json/acdc-of/v1/indicators avec mise en cache transient.
 */
class ACDC_Ind_Api {

    /**
     * Retourne les indicateurs globaux.
     * @param bool $force_refresh  Si true, ignore le cache et refait l'appel.
     * @return array|null  Tableau de données ou null en cas d'erreur.
     */
    public function fetch_global( $force_refresh = false ) {
        /* ACDC 1.0.8 — LA CLÉ DE CACHE PORTE LA VERSION DU PLUGIN.
           Relevé en recette : après la mise à jour, la page affichait toujours
           les anciens chiffres. Le plugin était bien à jour, le SAAS renvoyait
           bien les nouvelles données — mais le cache de vingt-quatre heures
           tenait encore la réponse d'avant, et rien à l'écran ne le disait.
           « J'ai installé et rien ne change » est la pire forme d'échec : elle
           ressemble à un défaut du code, et fait chercher là où il n'y a rien.
           En attachant la version à la clé, toute mise à jour repart d'un cache
           vide, sans le moindre geste. */
        $cache_key   = 'acdc_ind_global_' . ( defined( 'ACDC_IND_VERSION' ) ? str_replace( '.', '_', ACDC_IND_VERSION ) : '0' );
        $cache_hours = (int) get_option( 'acdc_ind_cache_hours', 24 );

        if ( ! $force_refresh ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached && is_array( $cached ) ) {
                return $cached;
            }
        }

        $saas_url = trim( (string) get_option( 'acdc_ind_saas_url', '' ) );
        if ( '' === $saas_url ) {
            return null;
        }

        $endpoint = rtrim( $saas_url, '/' ) . '/wp-json/acdc-of/v1/indicators';

        $response = wp_remote_get( $endpoint, array(
            'timeout'   => 10,
            'sslverify' => false,
            'headers'   => array(
                'Accept'     => 'application/json',
                'User-Agent' => 'ACDC-Indicateurs/1.0',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( (int) $code !== 200 ) {
            return null;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) || empty( $body['success'] ) || ! isset( $body['data'] ) ) {
            return null;
        }

        $data = $body['data'];
        set_transient( $cache_key, $data, $cache_hours * HOUR_IN_SECONDS );

        return $data;
    }
}
