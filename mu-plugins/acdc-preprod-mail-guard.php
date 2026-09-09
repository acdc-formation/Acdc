<?php
/**
 * Plugin Name: ACDC Preprod Mail Guard
 * Description: Garde-fou e-mail de PRÉPRODUCTION. Si la constante ACDC_PREPROD est
 *              définie (true), TOUS les e-mails sortants sont redirigés vers une
 *              adresse unique et le(s) destinataire(s) d'origine sont journalisés.
 *              But : empêcher tout e-mail de test de partir vers un destinataire réel
 *              (en particulier un FINANCEUR réel).
 * Version:     1.0.0
 * Author:      ACDC
 *
 * ============================ NON ACTIVÉ PAR DÉFAUT ============================
 * Ce fichier vit dans le dépôt mais n'a AUCUN effet tant qu'il n'est pas déployé
 * comme « must-use plugin » ET que la constante ACDC_PREPROD n'est pas définie.
 *
 * DÉPLOIEMENT (sur le site de préproduction UNIQUEMENT) :
 *   1. Copier ce fichier dans :  wp-content/mu-plugins/acdc-preprod-mail-guard.php
 *      (créer le dossier mu-plugins s'il n'existe pas — les mu-plugins sont
 *       chargés automatiquement et ne peuvent pas être désactivés par erreur).
 *   2. Dans wp-config.php, AU-DESSUS de « That's all, stop editing » :
 *          define( 'ACDC_PREPROD', true );
 *          define( 'ACDC_PREPROD_MAIL_REDIRECT', 'proprietaire@example.com' ); // adresse unique
 *   3. Vérifier : envoyer un e-mail de test → il doit arriver UNIQUEMENT sur
 *      l'adresse de redirection, et le log doit mentionner le destinataire d'origine.
 *
 * NE JAMAIS déployer ce garde-fou en PRODUCTION (il capterait tous les envois).
 * Tant que ACDC_PREPROD n'est pas défini, le filtre se retire de lui-même.
 * ============================================================================
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ACDC_PREPROD' ) || ! ACDC_PREPROD ) {
	return; // Inerte hors préproduction.
}

/**
 * Adresse unique de redirection. Ordre de résolution :
 *   1. Constante ACDC_PREPROD_MAIL_REDIRECT (recommandé).
 *   2. Option admin_email (repli sûr — jamais un destinataire métier).
 */
function acdc_preprod_mail_redirect_target() {
	$target = defined( 'ACDC_PREPROD_MAIL_REDIRECT' ) ? (string) ACDC_PREPROD_MAIL_REDIRECT : '';
	$target = sanitize_email( $target );
	if ( '' === $target || ! is_email( $target ) ) {
		$target = sanitize_email( (string) get_option( 'admin_email' ) );
	}
	return $target;
}

/**
 * Intercepte wp_mail() : réécrit le(s) destinataire(s) vers l'adresse unique,
 * purge Cc/Bcc, et journalise les destinataires d'origine.
 *
 * @param array $args { to, subject, message, headers, attachments }
 * @return array
 */
function acdc_preprod_mail_guard_filter( $args ) {
	$target = acdc_preprod_mail_redirect_target();

	// Destinataires d'origine (To).
	$orig_to = isset( $args['to'] ) ? $args['to'] : array();
	if ( ! is_array( $orig_to ) ) {
		$orig_to = preg_split( '/[,;]\s*/', (string) $orig_to );
	}
	$orig_to = array_values( array_filter( array_map( 'trim', (array) $orig_to ) ) );

	// Repérage + purge des Cc/Bcc éventuels dans les en-têtes.
	$orig_cc_bcc = array();
	$headers     = isset( $args['headers'] ) ? $args['headers'] : array();
	if ( is_string( $headers ) ) {
		$headers = preg_split( '/\r\n|\r|\n/', $headers );
	}
	$clean_headers = array();
	foreach ( (array) $headers as $header ) {
		$line = trim( (string) $header );
		if ( '' === $line ) {
			continue;
		}
		if ( preg_match( '/^\s*(cc|bcc)\s*:/i', $line ) ) {
			$orig_cc_bcc[] = $line; // on journalise puis on supprime la ligne.
			continue;
		}
		$clean_headers[] = $header;
	}

	// Journalisation du détournement (destinataires réels préservés dans le log seulement).
	$subject = isset( $args['subject'] ) ? (string) $args['subject'] : '';
	error_log(
		'[ACDC_PREPROD Mail Guard] Redirigé vers ' . $target
		. ' | To d\'origine : ' . ( $orig_to ? implode( ', ', $orig_to ) : '(vide)' )
		. ( $orig_cc_bcc ? ' | Cc/Bcc supprimés : ' . implode( ' ; ', $orig_cc_bcc ) : '' )
		. ' | Sujet : ' . $subject
	);

	// Réécriture stricte : un seul destinataire, aucun Cc/Bcc.
	$args['to']      = $target;
	$args['headers'] = $clean_headers;

	// Trace visible dans le sujet pour lever toute ambiguïté en test.
	if ( false === strpos( $subject, '[PREPROD]' ) ) {
		$args['subject'] = '[PREPROD] ' . $subject;
	}

	return $args;
}
add_filter( 'wp_mail', 'acdc_preprod_mail_guard_filter', 999 );
