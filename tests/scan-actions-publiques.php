<?php
/**
 * Ce qui est déclaré public doit être joignable par n'importe qui.
 *
 * Une garde du plugin — enforce_plugin_request_permissions(), accrochée à
 * admin_init, que admin-post.php déclenche — exige manage_options pour toute
 * action « acdc_ », sauf celles inscrites dans une liste blanche. Cette liste
 * en comptait NEUF quand le plugin déclarait TRENTE-ET-UNE actions publiques
 * par add_action( 'admin_post_nopriv_acdc_…' ).
 *
 * Vingt-deux actions publiques étaient donc refusées à tout visiteur non
 * connecté, avec « Action non autorisée. » — et invisibles pour un
 * administrateur, qui franchit la garde sans la voir. Conséquences réelles :
 * aucun signataire extérieur ne pouvait valider son code, ni consulter le
 * document ; aucun formateur ne pouvait activer son accès.
 *
 * Depuis la 3.25.255, la garde lit directement les déclarations
 * (has_action). Ce balayage vérifie qu'aucune régression ne réintroduit une
 * liste divergente, et surtout qu'aucune action déclarée publique n'exige
 * manage_options dans son propre gestionnaire — une déclaration qui ment est
 * aussi grave qu'une garde trop stricte, dans l'autre sens.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

/* ── 1. Les actions déclarées publiques ─────────────────────────────────── */
$publiques = array();   // action => gestionnaire
$rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$fichiers = array();
foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    if ( false !== strpos( $f->getPathname(), '/vendor/' ) ) { continue; }
    $fichiers[ $f->getPathname() ] = (string) file_get_contents( $f->getPathname() );
}
foreach ( $fichiers as $src ) {
    if ( preg_match_all( "/admin_post_nopriv_(\w+)'\s*,\s*array\(\s*\\\$[\w>\-]+\s*,\s*'(\w+)'/", $src, $m, PREG_SET_ORDER ) ) {
        foreach ( $m as $hit ) {
            $publiques[ $hit[1] ] = $hit[2];
        }
    }
}

/* ── 2. La garde lit-elle bien les déclarations ? ───────────────────────── */
$hits = array();
$garde_ok = false;
foreach ( $fichiers as $src ) {
    if ( false !== strpos( $src, 'function is_public_plugin_admin_post_action' )
      && false !== strpos( $src, "has_action( 'admin_post_nopriv_' . \$action )" ) ) {
        $garde_ok = true;
    }
}
if ( ! $garde_ok ) {
    $hits[] = 'La garde n’autorise plus les actions d’après leur déclaration publique (has_action) : la liste blanche redevient une seconde source de vérité.';
}

/* ── 3. Une action publique ne doit pas exiger manage_options ───────────── */
foreach ( $publiques as $action => $handler ) {
    foreach ( $fichiers as $path => $src ) {
        $pos = strpos( $src, 'function ' . $handler . '(' );
        if ( false === $pos ) { continue; }
        /* Les vingt premières lignes du gestionnaire : c'est là que se posent
           les contrôles d'accès. */
        $tete = implode( "\n", array_slice( explode( "\n", substr( $src, $pos ) ), 0, 20 ) );
        if ( preg_match( "/current_user_can\(\s*'(manage_options|edit_posts)'/", $tete, $m ) ) {
            $rel = preg_replace( '#^.*/includes/#', '', $path );
            $hits[] = sprintf(
                '%s : %s() est déclaré PUBLIC mais exige %s — la déclaration ment.',
                $rel, $handler, $m[1]
            );
        }
        break;
    }
}

printf( "%d action(s) déclarée(s) publique(s).\n", count( $publiques ) );
if ( $hits ) {
    echo "Anomalies :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d anomalie(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
echo "0 anomalie — tout ce qui est déclaré public est joignable sans être connecté.\n";
exit( 0 );
