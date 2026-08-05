<?php
/**
 * Script de vérification MANUELLE du module MCP ACDC (aucun WordPress requis).
 *
 * Usage :  php tools/verify-mcp-abilities.php
 *
 * Contrôle les invariants de sécurité imposés (préproduction, financeurs réels) :
 *   - lint PHP OK sur les fichiers du module ;
 *   - AUCUN wp_mail() dans les abilities ;
 *   - AUCUNE suppression (DELETE/DROP/->delete) ;
 *   - AUCUNE écriture financeur (funder_id/planned_funding jamais écrits) ;
 *   - chaque ability a permission_callback (jamais « true » en dur),
 *     input_schema, output_schema et meta.mcp.public ;
 *   - le mu-plugin garde-fou intercepte wp_mail et exige ACDC_PREPROD.
 *
 * Sortie : liste PASS/FAIL + code de sortie 0 (tout OK) ou 1 (au moins un échec).
 */

$root = dirname( __DIR__ );
$plugin = $root . '/acdc-formation-saas-organisme-de-formation';
$trait = $plugin . '/includes/mcp/class-acdc-mcp-abilities-trait.php';
$guard = $root . '/mu-plugins/acdc-preprod-mail-guard.php';

$failures = 0;
$checks   = 0;

function check( $label, $ok, &$failures, &$checks ) {
	$checks++;
	if ( ! $ok ) { $failures++; }
	printf( "[%s] %s\n", $ok ? 'PASS' : 'FAIL', $label );
}

/* --- 1. Lint PHP des fichiers du module --- */
$lint_targets = array(
	$trait,
	$guard,
	$plugin . '/includes/class-acdc-mcp.php',
	$plugin . '/includes/crm-commercial/class-acdc-crm-commercial-core-trait.php',
	$plugin . '/includes/crm-commercial/class-acdc-crm-commercial-actions-trait.php',
);
foreach ( $lint_targets as $file ) {
	$out = array(); $code = 0;
	exec( 'php -l ' . escapeshellarg( $file ) . ' 2>&1', $out, $code );
	check( 'Lint OK : ' . basename( $file ), 0 === $code, $failures, $checks );
}

if ( ! is_readable( $trait ) ) {
	echo "\nFATAL : trait MCP introuvable.\n";
	exit( 1 );
}
$src = file_get_contents( $trait );

/* --- 2. Aucun envoi d'e-mail dans les abilities --- */
check( 'Aucun wp_mail() dans le trait MCP', false === strpos( $src, 'wp_mail(' ), $failures, $checks );

/* --- 3. Aucune suppression --- */
$no_delete = ! preg_match( '/\b(DELETE\s+FROM|DROP\s+TABLE)\b/i', $src ) && false === strpos( $src, '->delete(' );
check( 'Aucune suppression (DELETE/DROP/->delete)', $no_delete, $failures, $checks );

/* --- 4. Aucune écriture financeur --- */
// funder_id/planned_funding ne doivent apparaître qu'en LECTURE (mapper) : chaque occurrence
// « 'funder_id' => X » / « 'planned_funding' => X » doit avoir X == isset(...) (lecture curée).
$funder_writes_only_read = true;
foreach ( array( 'funder_id', 'planned_funding' ) as $fkey ) {
	if ( preg_match_all( "/'" . $fkey . "'\s*=>\s*([a-zA-Z_]+)/", $src, $m ) ) {
		foreach ( $m[1] as $token ) {
			if ( 'isset' !== $token ) { $funder_writes_only_read = false; }
		}
	}
}
// Et ces clés ne doivent jamais figurer dans les tableaux d'écriture (create_prospect / build_quote_data).
$write_blocks = '';
if ( preg_match( '/function mcp_create_prospect[\s\S]*?\$data = array\(([\s\S]*?)\);/', $src, $mm ) ) { $write_blocks .= $mm[1]; }
if ( preg_match( '/function mcp_build_quote_data[\s\S]*?\}/', $src, $mm ) ) { $write_blocks .= $mm[0]; }
$no_funder_in_writes = ( false === strpos( $write_blocks, 'funder' ) && false === strpos( $write_blocks, 'planned_funding' ) );
check( 'Aucune écriture des données financeur', $funder_writes_only_read && $no_funder_in_writes, $failures, $checks );

/* --- 5. Cohérence d'enregistrement des abilities --- */
$n_register  = substr_count( $src, 'acdc_register_mcp_ability(' ) - 1; // -1 : la définition de la méthode
$n_permcb    = preg_match_all( '/array\(\s*\$this,\s*\'mcp_can_(read|write)\'\s*\)/', $src );
$n_public    = substr_count( $src, "'mcp' => array( 'public' => true )" );
$n_inschema  = substr_count( $src, "'input_schema'" );
$n_outschema = substr_count( $src, "'output_schema'" );

check( 'Au moins 8 abilities enregistrées (Lot 1+2)', $n_register >= 11, $failures, $checks );
check( 'Chaque enregistrement a un permission_callback mcp_can_read/write (' . $n_permcb . ')', $n_permcb >= $n_register, $failures, $checks );
check( 'meta.mcp.public présent une fois par ability (helper)', $n_public >= 1, $failures, $checks );
check( 'input_schema + output_schema présents dans le helper', $n_inschema >= 1 && $n_outschema >= 1, $failures, $checks );

/* --- 6. Aucun permission_callback « true » en dur --- */
$hardcoded_true = preg_match( "/'permission_callback'\s*=>\s*(true|'__return_true'|function[^)]*\)\s*\{\s*return\s+true)/", $src )
	|| false !== strpos( $src, '__return_true' );
check( 'Aucun permission_callback = true en dur', ! $hardcoded_true, $failures, $checks );

/* --- 7. Aucune dépendance à manage_options (capacités DÉDIÉES uniquement) --- */
// On cible la capacité en tant qu'argument (les mentions en commentaire « jamais manage_options » sont tolérées).
check( 'Aucune ability ne dépend de manage_options', false === strpos( $src, "'manage_options'" ), $failures, $checks );

/* --- 7b. Permissions adossées aux capacités dédiées acdc_mcp_read / acdc_mcp_write --- */
check( 'mcp_can_read → acdc_mcp_read', (bool) preg_match( "/function mcp_can_read[\s\S]{0,160}current_user_can\(\s*'acdc_mcp_read'\s*\)/", $src ), $failures, $checks );
check( 'mcp_can_write → acdc_mcp_write', (bool) preg_match( "/function mcp_can_write[\s\S]{0,160}current_user_can\(\s*'acdc_mcp_write'\s*\)/", $src ), $failures, $checks );

/* --- 7c. Rattachement par lot : 8 abilities en lecture, 3 en écriture --- */
$n_read_bind  = preg_match_all( "/array\(\s*\\\$this,\s*'mcp_can_read'\s*\)/", $src );
$n_write_bind = preg_match_all( "/array\(\s*\\\$this,\s*'mcp_can_write'\s*\)/", $src );
check( 'Lot 1 : 8 abilities rattachées à mcp_can_read (' . $n_read_bind . ')', 8 === $n_read_bind, $failures, $checks );
check( 'Lot 2 : 3 abilities rattachées à mcp_can_write (' . $n_write_bind . ')', 3 === $n_write_bind, $failures, $checks );
// Toutes les écritures internes vérifient acdc_mcp_write (jamais manage_options).
check( 'Écritures internes gardées par acdc_mcp_write', 3 <= substr_count( $src, "current_user_can( 'acdc_mcp_write' )" ), $failures, $checks );

/* --- 7d. Rôles : capacités correctement déclarées --- */
$has_setup = preg_match( '/function acdc_mcp_setup_roles[\s\S]*?update_option\(\s*\'acdc_mcp_roles_version\'/', $src, $sm );
$setup = $has_setup ? $sm[0] : '';
check( 'Rôle acdc_mcp_agent : read + acdc_mcp_read + acdc_mcp_write',
	false !== strpos( $setup, "'acdc_mcp_agent'" )
	&& (bool) preg_match( "/acdc_mcp_agent[\s\S]{0,260}'read', 'acdc_mcp_read', 'acdc_mcp_write'/", $setup ),
	$failures, $checks );
check( 'Rôle acdc_mcp_readonly : read + acdc_mcp_read (sans write)',
	false !== strpos( $setup, "'acdc_mcp_readonly'" )
	&& (bool) preg_match( "/acdc_mcp_readonly[\s\S]{0,320}'read', 'acdc_mcp_read'[\s\S]{0,200}remove_cap\(\s*'acdc_mcp_write'/", $setup ),
	$failures, $checks );
check( 'administrator reçoit acdc_mcp_read + acdc_mcp_write',
	(bool) preg_match( "/get_role\(\s*'administrator'\s*\)[\s\S]{0,200}add_cap\(\s*'acdc_mcp_read'\s*\)[\s\S]{0,120}add_cap\(\s*'acdc_mcp_write'\s*\)/", $setup ),
	$failures, $checks );
check( 'Désactivation : suppression des rôles + révocation admin',
	(bool) preg_match( "/function acdc_mcp_remove_roles[\s\S]*?remove_role\(\s*'acdc_mcp_agent'[\s\S]*?remove_role\(\s*'acdc_mcp_readonly'[\s\S]*?remove_cap\(\s*'acdc_mcp_read'[\s\S]*?remove_cap\(\s*'acdc_mcp_write'/", $src ),
	$failures, $checks );

/* --- 8. mu-plugin garde-fou --- */
if ( is_readable( $guard ) ) {
	$g = file_get_contents( $guard );
	check( 'Garde-fou : exige ACDC_PREPROD', false !== strpos( $g, "defined( 'ACDC_PREPROD' )" ), $failures, $checks );
	check( 'Garde-fou : intercepte wp_mail', false !== strpos( $g, "add_filter( 'wp_mail'" ), $failures, $checks );
	check( 'Garde-fou : réécrit le destinataire (to)', false !== strpos( $g, "\$args['to']" ), $failures, $checks );
	check( 'Garde-fou : journalise le destinataire d\'origine', false !== strpos( $g, 'error_log' ) && false !== strpos( $g, "d\\'origine" ), $failures, $checks );
} else {
	check( 'mu-plugin garde-fou présent', false, $failures, $checks );
}

printf( "\n%d contrôles, %d échec(s).\n", $checks, $failures );
exit( $failures > 0 ? 1 : 0 );
