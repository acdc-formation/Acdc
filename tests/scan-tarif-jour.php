<?php
/**
 * « Tarif jour » ne doit JAMAIS recevoir le tarif catalogue tel quel.
 *
 * Le tarif catalogue d'une formation est un montant TOTAL pour toute la durée.
 * L'assistant de création de proposition le recopiait dans le champ « Tarif
 * jour » : une formation à 1 800 € sur deux jours ressortait à 3 600 €, sur un
 * document commercial signé par le client. Le formulaire complet, lui, divise
 * par le nombre de jours depuis toujours.
 *
 * Le motif fautif ne tient pas sur une seule ligne — c'est ce qui l'a rendu
 * invisible :
 *
 *     var price = opt.getAttribute('data-price') || '900';
 *     document.getElementById('acdc-prop-price').value = price || 900;
 *
 * Le balayage suit donc, fichier par fichier, les variables qui reçoivent un
 * attribut de prix catalogue sans division, puis signale leur écriture dans un
 * champ de prix. Une division quelque part dans l'expression suffit à
 * disculper : c'est la règle attendue, le total réparti par jour.
 *
 * C'est textuel, donc approximatif : ce balayage attrape le motif qui a produit
 * le défaut, pas toutes ses variantes imaginables.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

/* Un attribut qui porte le tarif catalogue — c'est-à-dire un TOTAL. */
$catalogue = '/data-(total-)?price/i';

$rii  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits = array();
foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }
    $rel   = preg_replace( '#^.*/includes/#', '', $path );
    $lines = explode( "\n", (string) file_get_contents( $path ) );

    /* Passe 1 — les variables qui portent un total catalogue non réparti. */
    $totaux = array();
    foreach ( $lines as $line ) {
        if ( ! preg_match( '/(?:var\s+)?([A-Za-z_$][\w$]*)\s*=\s*([^;]+);/', $line, $m ) ) { continue; }
        if ( ! preg_match( $catalogue, $m[2] ) ) { continue; }
        if ( false !== strpos( $m[2], '/' ) ) { continue; }   // déjà réparti
        $totaux[ $m[1] ] = true;
    }

    /* Passe 2 — leur écriture dans un champ de prix. */
    foreach ( $lines as $i => $line ) {
        if ( ! preg_match( "/price[^=\n]*\)\s*\.value\s*=\s*([^;]+);/i", $line, $m ) ) { continue; }
        $rhs = $m[1];
        if ( false !== strpos( $rhs, '/' ) ) { continue; }    // réparti à l'écriture
        $coupable = preg_match( $catalogue, $rhs );
        if ( ! $coupable ) {
            foreach ( array_keys( $totaux ) as $var ) {
                if ( preg_match( '/\b' . preg_quote( $var, '/' ) . '\b/', $rhs ) ) { $coupable = true; break; }
            }
        }
        if ( $coupable ) {
            $hits[] = sprintf( '%s:%d  %s', $rel, $i + 1, trim( $line ) );
        }
    }
}

if ( $hits ) {
    echo "Tarif catalogue écrit tel quel dans un champ de prix journalier :\n";
    foreach ( $hits as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( $hits ) );
    exit( 1 );
}
echo "0 occurrence — aucun tarif catalogue injecté dans « Tarif jour ».\n";
exit( 0 );
