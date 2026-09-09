<?php
/**
 * Test fonctionnel HORS WordPress : simule le contrat réel de l'API Abilities
 * (WordPress 6.9/7.0) et vérifie que nos 11 abilities s'enregistrent RÉELLEMENT
 * une fois la catégorie déclarée.
 *
 * Reproduit fidèlement les validations du core :
 *   - wp_register_ability_category() : slug, label, description ;
 *   - wp_register_ability() : nom (regex namespace), category OBLIGATOIRE et
 *     pré-enregistrée, label, description, execute_callback, permission_callback.
 *
 * Sortie : "SMOKE OK: 11/11" (exit 0) ou détail des échecs (exit 1).
 */

define( 'ABSPATH', '/tmp/' );
define( 'WP_DEBUG', false );

$GLOBALS['__abilities']   = array();
$GLOBALS['__categories']  = array();

// ---- Stubs du core WordPress strictement nécessaires ----
function doing_action( $h = null ) { return true; }
function _doing_it_wrong( $f, $m, $v ) { /* silencieux comme en prod */ }
function esc_html( $s ) { return $s; }
function esc_html__( $s, $d = null ) { return $s; }
function __( $s, $d = null ) { return $s; }
function add_action( $h, $cb, $p = 10, $a = 1 ) {}
function add_filter( $h, $cb, $p = 10, $a = 1 ) {}
function add_options_page() {}
function current_user_can( $c ) { return true; }
function wp_parse_args( $a, $d = array() ) { return array_merge( (array) $d, (array) $a ); }

function wp_register_ability_category( $slug, $args ) {
	if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) ) { return null; }
	if ( empty( $args['label'] ) || ! is_string( $args['label'] ) ) { return null; }
	if ( empty( $args['description'] ) || ! is_string( $args['description'] ) ) { return null; }
	$GLOBALS['__categories'][ $slug ] = true;
	return (object) array( 'slug' => $slug );
}
function wp_has_ability_category( $slug ) { return isset( $GLOBALS['__categories'][ $slug ] ); }

function wp_register_ability( $name, $args ) {
	// 1) Format du nom (regex core).
	if ( ! preg_match( '#^[a-z0-9-]+/[a-z0-9-]+$#', $name ) ) { return null; }
	// 2) Champs requis + types.
	foreach ( array( 'label', 'description', 'category' ) as $req ) {
		if ( empty( $args[ $req ] ) || ! is_string( $args[ $req ] ) ) { return null; }
	}
	// 3) Catégorie OBLIGATOIREMENT pré-enregistrée (cause racine du bug).
	if ( ! wp_has_ability_category( $args['category'] ) ) { return null; }
	// 4) Callbacks valides.
	if ( empty( $args['execute_callback'] ) || ! is_callable( $args['execute_callback'] ) ) { return null; }
	if ( empty( $args['permission_callback'] ) || ! is_callable( $args['permission_callback'] ) ) { return null; }
	// 5) Schémas optionnels : tableaux si présents.
	if ( isset( $args['input_schema'] ) && ! is_array( $args['input_schema'] ) ) { return null; }
	if ( isset( $args['output_schema'] ) && ! is_array( $args['output_schema'] ) ) { return null; }
	if ( isset( $args['meta'] ) && ! is_array( $args['meta'] ) ) { return null; }
	if ( isset( $args['meta']['public'] ) && ! is_bool( $args['meta']['public'] ) ) { return null; }
	if ( isset( $args['meta']['show_in_rest'] ) && ! is_bool( $args['meta']['show_in_rest'] ) ) { return null; }
	$GLOBALS['__abilities'][ $name ] = true;
	return (object) array( 'name' => $name );
}
function wp_has_ability( $name ) { return isset( $GLOBALS['__abilities'][ $name ] ); }

// ---- Charge le trait et le compose dans une classe de test ----
$trait_file = dirname( __DIR__ ) . '/acdc-formation-saas-organisme-de-formation/includes/mcp/class-acdc-mcp-abilities-trait.php';
require $trait_file;

class ACDC_MCP_Smoke_Host {
	use ACDC_Mcp_Abilities_Trait;
	// Seule dépendance appelée pendant l'enregistrement (schéma des devis).
	public function get_quote_status_labels() {
		return array(
			'brouillon' => 'Brouillon', 'envoye' => 'Envoyé', 'a_signer' => 'À signer',
			'signe' => 'Signé', 'refuse' => 'Refusé', 'expire' => 'Expiré',
		);
	}
}

$host = new ACDC_MCP_Smoke_Host();
$host->register_mcp_ability_categories(); // déclare la catégorie
$host->register_mcp_abilities();          // déclare les abilities

$expected  = $host->acdc_mcp_expected_ability_names();
$missing   = array();
foreach ( $expected as $name ) {
	if ( ! wp_has_ability( $name ) ) { $missing[] = $name; }
}
$ok  = count( $expected ) - count( $missing );
$cat = wp_has_ability_category( 'acdc-of' ) ? 'oui' : 'NON';

if ( empty( $missing ) && count( $expected ) === 11 ) {
	echo "SMOKE OK: {$ok}/11 abilities enregistrées (catégorie acdc-of: {$cat}).\n";
	exit( 0 );
}
echo "SMOKE FAIL: {$ok}/11 enregistrées, catégorie acdc-of: {$cat}.\n";
if ( $missing ) { echo 'Manquantes : ' . implode( ', ', $missing ) . "\n"; }
exit( 1 );
