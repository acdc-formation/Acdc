<?php
/**
 * ACDC_Mcp_Server
 *
 * Intègre la bibliothèque WordPress/mcp-adapter (vendorisée dans le plugin) pour
 * exposer nos abilities en MCP SANS installer ni activer de plugin séparé.
 *
 * - Charge l'autoloader vendorisé (défensif : rien ne casse si absent).
 * - Désactive le serveur MCP « par défaut » de la lib → PAS de découverte
 *   automatique de toutes les abilities du site.
 * - Crée UN serveur MCP en transport REST (HttpTransport), n'exposant QUE la
 *   liste blanche explicite de nos 11 abilities (8 lecture + 3 écritures sûres).
 * - Les permission_callback des abilities restent la seule autorité
 *   d'autorisation (acdc_mcp_read / acdc_mcp_write).
 *
 * Endpoint REST obtenu : /wp-json/acdc-mcp/v1/mcp
 *
 * @since 3.25.146
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ACDC_Mcp_Server {

	/** Identifiant interne du serveur MCP. */
	const SERVER_ID = 'acdc-mcp';

	/** Namespace REST (WordPress) du serveur. */
	const ROUTE_NAMESPACE = 'acdc-mcp/v1';

	/** Route REST du serveur. Endpoint final : /wp-json/{namespace}/{route}. */
	const ROUTE = 'mcp';

	/**
	 * LISTE BLANCHE EXPLICITE — exactement nos 11 abilities.
	 * Aucune découverte automatique. Aucune ability d'e-mail, de suppression,
	 * ni d'écriture financeur (voir class-acdc-mcp-abilities-trait.php).
	 */
	const ABILITIES = array(
		// Lot 1 — lecture seule (8).
		'acdc-of/list-formations',
		'acdc-of/get-formation',
		'acdc-of/list-prospects',
		'acdc-of/get-prospect',
		'acdc-of/list-leads',
		'acdc-of/list-quotes',
		'acdc-of/get-quote',
		'acdc-of/get-indicators',
		// Lot 2 — écritures sûres, sans e-mail (3).
		'acdc-of/create-quote',
		'acdc-of/update-quote',
		'acdc-of/create-prospect',
	);

	/**
	 * Amorce l'intégration. Idempotent et entièrement défensif.
	 * Appelé au chargement du module MCP (plugins_loaded tardif).
	 */
	public static function boot() {
		static $booted = false;
		if ( $booted ) {
			return;
		}
		$booted = true;

		// 1) Charger la bibliothèque vendorisée (autoloader PSR-4 maison).
		$autoload = __DIR__ . '/../vendor/mcp-adapter/autoload.php';
		if ( is_readable( $autoload ) ) {
			require_once $autoload;
		}

		// 2) Rien à faire si la lib est absente ou l'API a changé (aucun fatal).
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			return;
		}
		if ( ! method_exists( '\WP\MCP\Core\McpAdapter', 'instance' ) ) {
			return;
		}

		// 3) Pas de serveur « par défaut » : on n'expose QUE notre liste blanche.
		add_filter( 'mcp_adapter_create_default_server', '__return_false' );

		// 4) Instancier l'adaptateur : il s'accroche lui-même à rest_api_init.
		try {
			\WP\MCP\Core\McpAdapter::instance();
		} catch ( \Throwable $e ) {
			if ( function_exists( 'error_log' ) ) {
				error_log( 'ACDC MCP: instanciation McpAdapter impossible : ' . $e->getMessage() );
			}
			return;
		}

		// 5) Créer NOTRE serveur au hook documenté par la bibliothèque.
		add_action( 'mcp_adapter_init', array( __CLASS__, 'create_server' ) );
	}

	/**
	 * Crée le serveur MCP ACDC (transport REST) avec la liste blanche d'abilities.
	 * Appelé pendant l'action mcp_adapter_init (exigée par create_server()).
	 *
	 * @param object $adapter Instance \WP\MCP\Core\McpAdapter fournie par le hook.
	 */
	public static function create_server( $adapter ) {
		if ( ! is_object( $adapter ) || ! method_exists( $adapter, 'create_server' ) ) {
			return;
		}
		// Transport + gestionnaire d'erreurs : vérifs défensives de présence.
		if ( ! class_exists( '\WP\MCP\Transport\HttpTransport' ) ) {
			return;
		}
		if ( ! class_exists( '\WP\MCP\Infrastructure\ErrorHandling\NullMcpErrorHandler' ) ) {
			return;
		}

		$version = defined( 'ACDC_OF_SAAS_VERSION' ) ? (string) ACDC_OF_SAAS_VERSION : '1.0.0';

		try {
			$adapter->create_server(
				self::SERVER_ID,                                              // server_id
				self::ROUTE_NAMESPACE,                                        // server_route_namespace
				self::ROUTE,                                                  // server_route
				'ACDC Formation MCP',                                         // server_name
				'Serveur MCP ACDC — abilities CRM en liste blanche (lecture + écritures sûres, aucun e-mail).', // description
				$version,                                                     // server_version
				array( \WP\MCP\Transport\HttpTransport::class ),              // transports (REST)
				\WP\MCP\Infrastructure\ErrorHandling\NullMcpErrorHandler::class, // error_handler
				null,                                                         // observability_handler (défaut)
				self::ABILITIES                                               // tools = LISTE BLANCHE (11)
			);
		} catch ( \Throwable $e ) {
			if ( function_exists( 'error_log' ) ) {
				error_log( 'ACDC MCP: création du serveur impossible : ' . $e->getMessage() );
			}
		}
	}
}
