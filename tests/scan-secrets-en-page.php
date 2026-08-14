<?php
/**
 * Un secret ne doit jamais être écrit dans la page.
 *
 * La fiche financeur enregistre désormais le mot de passe de notre espace
 * OPCO. La tentation naturelle est de le réafficher dans le champ, masqué par
 * `type="password"` — mais un champ masqué n'est masqué qu'à l'œil : la valeur
 * est en clair dans le code source, lisible par n'importe quelle extension du
 * navigateur, par le cache d'un proxy, par un « Enregistrer la page ». Le
 * masque n'est alors qu'un décor.
 *
 * La règle : un secret n'est déchiffré qu'à la demande explicite, par une
 * requête authentifiée et journalisée. Il n'apparaît jamais dans le HTML rendu.
 *
 * Ce balayage cherche les secrets imprimés dans une page : clé d'API, mot de
 * passe, jeton — passés à esc_attr/esc_html/esc_textarea ou echo direct.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

/* Ce qui compte comme secret dans ce plugin. `password_hash` et
   `password_reset` n'en sont pas : l'un est déjà haché, l'autre est un jeton
   à usage unique conçu pour circuler. */
$secrets = array( 'portal_password', 'api_key', 'api_secret', 'smtp_password', 'client_secret' );
$exempts = array( 'password_hash', 'password_reset', 'password_clear', 'has_password' );

$rii  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits = array();
foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }
    $rel   = preg_replace( '#^.*/(includes|src)/#', '', $path );
    $lines = explode( "\n", (string) file_get_contents( $path ) );

    foreach ( $lines as $i => $line ) {
        $t = ltrim( $line );
        if ( 0 === strpos( $t, '*' ) || 0 === strpos( $t, '//' ) || 0 === strpos( $t, '/*' ) ) { continue; }

        /* Les échappements d'affichage et l'echo direct : tout ce qui finit
           dans le HTML. On isole l'expression imprimée. */
        if ( ! preg_match_all( '/(?:esc_attr|esc_html|esc_textarea|esc_js)\(([^;]{0,120})|echo\s+([^;]{0,120})/', $line, $m, PREG_SET_ORDER ) ) {
            continue;
        }
        foreach ( $m as $hit ) {
            $expr = ( '' !== ( $hit[1] ?? '' ) ) ? $hit[1] : ( $hit[2] ?? '' );
            foreach ( $exempts as $ok ) {
                $expr = str_replace( $ok, '', $expr );
            }
            foreach ( $secrets as $mot ) {
                if ( false !== strpos( $expr, $mot ) ) {
                    $hits[] = sprintf( '%s:%d  %s', $rel, $i + 1, trim( $line ) );
                    break 2;
                }
            }
        }
    }
}

if ( $hits ) {
    echo "Secrets imprimés dans une page (le masque n’est qu’un décor) :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
echo "0 occurrence — aucun secret n’est écrit dans le HTML rendu.\n";
exit( 0 );
