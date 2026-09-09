<?php
/**
 * Une fabrication de PDF ne doit jamais rendre une page blanche.
 *
 * Le bouton « Devis DE-2026-11.pdf » affichait « Il y a eu une erreur critique
 * sur ce site » : mPDF meurt quand la mémoire manque ou qu'un gabarit le
 * déroute, et personne ne rattrapait la chute. L'utilisateur n'avait ni
 * document, ni explication, ni trace — le message d'erreur n'était même pas
 * journalisé.
 *
 * La règle : tout appel à render_html_pdf() est entouré d'un filet, et l'échec
 * sert la version imprimable. C'est la même discipline que la 3.25.237 sur la
 * convention, appliquée aux quatre écrans qui fabriquent un PDF par mPDF : le
 * devis, le recueil des besoins, l'analyse du besoin et la veille Qualiopi.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

$rii  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits = array();
foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }
    $rel   = preg_replace( '#^.*/includes/#', '', $path );
    $lines = explode( "\n", (string) file_get_contents( $path ) );

    foreach ( $lines as $i => $line ) {
        if ( false === strpos( $line, '$this->render_html_pdf(' ) ) { continue; }
        $t = ltrim( $line );
        if ( 0 === strpos( $t, '*' ) || 0 === strpos( $t, '//' ) || 0 === strpos( $t, '/*' ) ) { continue; }

        /* Un « try {" dans les douze lignes qui précèdent : c'est le filet. */
        $avant = implode( "\n", array_slice( $lines, max( 0, $i - 12 ), 12 ) );
        if ( preg_match( '/\btry\s*\{/', $avant ) ) { continue; }

        $hits[] = sprintf( '%s:%d  %s', $rel, $i + 1, trim( $line ) );
    }
}

if ( $hits ) {
    echo "Fabrications de PDF sans filet (une panne y rend une page blanche) :\n";
    foreach ( $hits as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( $hits ) );
    exit( 1 );
}
echo "0 occurrence — toute fabrication de PDF a son filet.\n";
exit( 0 );
