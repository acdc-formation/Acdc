<?php
/**
 * Deux plafonds indépendants sur une image, c'est une image déformée.
 *
 * La 3.25.254 avait corrigé le cachet de l'organisme, écrasé de 16 % sur le
 * contrat formateur. La correction a été posée là où je regardais — et la même
 * faute a survécu ailleurs : sur la convention, la signature du bénéficiaire
 * partait en 894 × 480 et sortait dessinée en 180 × 60, écrasée de 38 % en
 * hauteur. Trois autres endroits portaient le même motif.
 *
 * Le test unitaire test-cachet-proportions.php vérifie la RÈGLE ; il ne peut
 * pas voir le CODE qui ne l'applique pas. C'est ce balayage qui manquait.
 *
 * Le motif fautif :
 *
 *     $w = min( 180, ... );   // largeur
 *     $h = min( 60,  ... );   // hauteur
 *
 * Dès que l'un des deux plafonds mord et pas l'autre, le rapport change. La
 * règle est un rapport unique appliqué aux deux dimensions —
 * acdc_pdf_scaled_size() est le seul endroit où il se calcule.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

$rii  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits = array();
$helper_ok = false;

foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }

    $src   = (string) file_get_contents( $path );
    $rel   = preg_replace( '#^.*/includes/#', '', $path );
    $lines = explode( "\n", $src );

    if ( false !== strpos( $src, 'function acdc_pdf_scaled_size' )
      && false !== strpos( $src, '$ratio = min(' ) ) {
        $helper_ok = true;
    }

    foreach ( $lines as $i => $line ) {
        $t = ltrim( $line );
        if ( 0 === strpos( $t, '*' ) || 0 === strpos( $t, '//' ) || 0 === strpos( $t, '/*' ) ) { continue; }

        /* Une largeur plafonnée par un min() — sur la ligne, ou dans une
           variable qui finira en display_width. */
        $largeur = preg_match( '/display_width\D{0,12}min\s*\(/', $line )
                || preg_match( '/\$\w*(?:_dw|_w|width)\b\s*=\s*min\s*\(/i', $line );
        if ( ! $largeur ) { continue; }

        /* La hauteur plafonnée de son côté, sur la même ligne ou juste après :
           c'est la signature du défaut. */
        $voisinage = implode( "\n", array_slice( $lines, $i, 4 ) );
        $hauteur = preg_match( '/display_height\D{0,12}min\s*\(/', $voisinage )
                || preg_match( '/\$\w*(?:_dh|_h|height)\b\s*=\s*min\s*\(/i', $voisinage );
        if ( ! $hauteur ) { continue; }

        $hits[] = sprintf( '%s:%d  largeur et hauteur plafonnées séparément — l’image sortira déformée.  %s', $rel, $i + 1, trim( $line ) );
    }
}

if ( ! $helper_ok ) {
    $hits[] = 'acdc_pdf_scaled_size() ne calcule plus un rapport unique : la règle des proportions a changé de main.';
}

if ( $hits ) {
    echo "Images mises à l’échelle par deux plafonds indépendants :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
echo "0 occurrence — toute image est réduite par un rapport unique.\n";
exit( 0 );
