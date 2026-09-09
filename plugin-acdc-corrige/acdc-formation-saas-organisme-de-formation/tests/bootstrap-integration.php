<?php
/**
 * Bootstrap de la suite « integration » (WordPress réel, base JETABLE).
 *
 * Sûreté « fail-closed » RENFORCÉE : les valeurs de test sont des LITTÉRAUX
 * IMMUABLES codés ici. On refuse de démarrer tant que :
 *   (a) les variables ACDC_TEST_DB_* ne sont pas STRICTEMENT égales à ces littéraux ;
 *   (b) wp-tests-config.php (lu SANS connexion) ne définit pas STRICTEMENT ces
 *       mêmes DB_NAME / DB_HOST / $table_prefix.
 * Ces deux gardes s'exécutent AVANT le bootstrap WordPress (donc avant toute
 * création/initialisation de schéma par la bibliothèque de tests). Une 3e garde
 * post-bootstrap revérifie les valeurs effectives. Aucune activation ni DDL
 * plugin tant que les gardes pré-bootstrap ne sont pas passées.
 *
 * Réseau sortant et e-mails sont neutralisés avant tout chargement de plugin.
 * Ce fichier ne s'exécute qu'en test. Aucune donnée de production n'est touchée.
 */

// -----------------------------------------------------------------------------
// 0. Littéraux de sûreté IMMUABLES approuvés.
// -----------------------------------------------------------------------------
const ACDC_SAFE_DB_NAME = 'wordpress_test';
const ACDC_SAFE_PREFIX  = 'wptests_';
const ACDC_SAFE_DB_HOST = '127.0.0.1:3306';

function _acdc_int_die( $msg, $code = 2 ) {
	fwrite( STDERR, "REFUS fail-closed (intégration) : {$msg}\n" );
	exit( $code );
}

// -----------------------------------------------------------------------------
// 1. GARDE PRÉ-BOOTSTRAP #1 — variables d'environnement STRICTEMENT égales aux littéraux.
// -----------------------------------------------------------------------------
if ( (string) getenv( 'ACDC_TEST_DB_NAME' ) !== ACDC_SAFE_DB_NAME ) {
	_acdc_int_die( "ACDC_TEST_DB_NAME doit valoir exactement '" . ACDC_SAFE_DB_NAME . "'." );
}
if ( (string) getenv( 'ACDC_TEST_DB_PREFIX' ) !== ACDC_SAFE_PREFIX ) {
	_acdc_int_die( "ACDC_TEST_DB_PREFIX doit valoir exactement '" . ACDC_SAFE_PREFIX . "'." );
}
if ( (string) getenv( 'ACDC_TEST_DB_HOST' ) !== ACDC_SAFE_DB_HOST ) {
	_acdc_int_die( "ACDC_TEST_DB_HOST doit valoir exactement '" . ACDC_SAFE_DB_HOST . "'." );
}

// -----------------------------------------------------------------------------
// 2. Localiser la bibliothèque de tests + le fichier wp-tests-config.php réel.
// -----------------------------------------------------------------------------
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	_acdc_int_die( "bibliothèque de tests WordPress introuvable dans {$_tests_dir} (lancez bin/install-wp-tests.sh)." );
}
$_config = getenv( 'WP_TESTS_CONFIG_FILE_PATH' );
if ( ! $_config || ! file_exists( $_config ) ) {
	$_config = $_tests_dir . '/wp-tests-config.php';
}
if ( ! file_exists( $_config ) ) {
	_acdc_int_die( "wp-tests-config.php introuvable ({$_config})." );
}

// -----------------------------------------------------------------------------
// 3. GARDE PRÉ-BOOTSTRAP #2 — lire wp-tests-config.php SANS l'exécuter (aucune
//    connexion, aucune redéfinition de constante) et vérifier les littéraux.
// -----------------------------------------------------------------------------
$_cfg     = (string) file_get_contents( $_config );
$_extract = function ( $pattern ) use ( $_cfg ) {
	return preg_match( $pattern, $_cfg, $m ) ? $m[1] : null;
};
$_cfg_db     = $_extract( "/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]*)['\"]/" );
$_cfg_host   = $_extract( "/define\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]*)['\"]/" );
$_cfg_prefix = $_extract( "/\\\$table_prefix\s*=\s*['\"]([^'\"]*)['\"]/" );
if ( ACDC_SAFE_DB_NAME !== $_cfg_db ) {
	_acdc_int_die( "wp-tests-config DB_NAME (" . var_export( $_cfg_db, true ) . ") != '" . ACDC_SAFE_DB_NAME . "'." );
}
if ( ACDC_SAFE_DB_HOST !== $_cfg_host ) {
	_acdc_int_die( "wp-tests-config DB_HOST (" . var_export( $_cfg_host, true ) . ") != '" . ACDC_SAFE_DB_HOST . "'." );
}
if ( ACDC_SAFE_PREFIX !== $_cfg_prefix ) {
	_acdc_int_die( "wp-tests-config \$table_prefix (" . var_export( $_cfg_prefix, true ) . ") != '" . ACDC_SAFE_PREFIX . "'." );
}

// -----------------------------------------------------------------------------
// 4. Charger la bibliothèque de tests ; brancher le plugin SANS activation, en
//    ayant d'abord neutralisé réseau (pre_http_request) et e-mail (pre_wp_mail).
// -----------------------------------------------------------------------------
require_once $_tests_dir . '/includes/functions.php';

function _acdc_int_load_plugin() {
	// Bloque TOUT appel HTTP sortant (IA OpenAI/Anthropic/Perplexity + RSS via wp_remote_*).
	add_filter(
		'pre_http_request',
		function () {
			return new WP_Error( 'acdc_test_no_http', 'Réseau sortant bloqué pendant les tests.' );
		},
		0
	);
	// Court-circuite tout envoi d'e-mail réel.
	add_filter( 'pre_wp_mail', '__return_true', 0 );

	require dirname( __DIR__ ) . '/acdc-formation-saas-organisme-de-formation.php';
}
tests_add_filter( 'muplugins_loaded', '_acdc_int_load_plugin' );

// -----------------------------------------------------------------------------
// 5. Démarrer WordPress de test (gardes pré-bootstrap franchies).
// -----------------------------------------------------------------------------
require $_tests_dir . '/includes/bootstrap.php';

// -----------------------------------------------------------------------------
// 6. GARDE POST-BOOTSTRAP — revérifier les valeurs EFFECTIVES.
// -----------------------------------------------------------------------------
global $wpdb;
if ( ! defined( 'DB_NAME' ) || DB_NAME !== ACDC_SAFE_DB_NAME ) {
	_acdc_int_die( "DB_NAME effectif != '" . ACDC_SAFE_DB_NAME . "'." );
}
if ( ! defined( 'DB_HOST' ) || DB_HOST !== ACDC_SAFE_DB_HOST ) {
	_acdc_int_die( "DB_HOST effectif != '" . ACDC_SAFE_DB_HOST . "'." );
}
if ( $wpdb->prefix !== ACDC_SAFE_PREFIX ) {
	_acdc_int_die( "préfixe effectif ('{$wpdb->prefix}') != '" . ACDC_SAFE_PREFIX . "'." );
}

// -----------------------------------------------------------------------------
// 7. Installation canonique du schéma ACDC (idempotente) — APRÈS les gardes.
// -----------------------------------------------------------------------------
if ( ! function_exists( 'dbDelta' ) ) {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
}
if ( class_exists( 'ACDC_Formation_SAAS_Plugin' ) && method_exists( 'ACDC_Formation_SAAS_Plugin', 'activate' ) ) {
	ACDC_Formation_SAAS_Plugin::activate();
}

// -----------------------------------------------------------------------------
// 8. Gate MOTEUR : l'isolation inter-tests (rollback WP_UnitTestCase) exige InnoDB.
// -----------------------------------------------------------------------------
$acdc_non_innodb = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT TABLE_NAME FROM information_schema.TABLES
		 WHERE TABLE_SCHEMA = %s AND TABLE_NAME LIKE %s AND ENGINE <> 'InnoDB'",
		DB_NAME,
		$wpdb->esc_like( $wpdb->prefix . 'acdc_of_' ) . '%'
	)
);
if ( ! empty( $acdc_non_innodb ) ) {
	_acdc_int_die( "tables acdc_of_* NON InnoDB (isolation par rollback impossible) : " . implode( ', ', $acdc_non_innodb ), 3 );
}
