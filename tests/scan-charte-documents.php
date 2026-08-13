<?php
/**
 * Les documents de la charte ne dessinent pas leur propre en-tête.
 *
 * Le contrat formateur, la convention et la convocation recopiaient chacun le
 * même en-tête — et avaient fini par diverger : panneaux bleutés contre boîtes
 * blanches, encre contre noir pur, et pour la convocation un bandeau beige qui
 * ne ressemblait à rien d'autre. Le pied de la convention, lui, était écrit en
 * dur : changer l'adresse de l'organisme dans les Réglages ne la changeait pas
 * sur la pièce contractuelle.
 *
 * Depuis la 3.25.254, ces documents appellent acdc_pdf_charte_header() et
 * acdc_pdf_charte_footer(). Ce balayage vérifie qu'aucun ne redessine sa propre
 * identité : logo posé à la main, raison sociale écrite en dur, bandeau pleine
 * largeur, ou coordonnées de l'organisme en clair dans le code.
 *
 * La liste ci-dessous s'allongera à chaque lot : attestation de fin de
 * formation, puis les autres pièces. Un document absent de la liste n'est pas
 * surveillé — c'est volontaire, on ne prétend pas qu'il est conforme.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

/* Les générateurs déjà passés à la charte. */
$surveilles = array(
    'build_trainer_contract_pdf_pages',
    'build_contract_pdf_pages',
    'build_training_convocation_pdf_pages',
);

/* Ce qu'un document ne doit plus faire lui-même. */
$interdits = array(
    "/'text'\s*=>\s*'ACDC-?\s?Formation'/i'"             => 'raison sociale écrite en dur',
    '/acdc-formation\.com/i'                             => 'coordonnées de l’organisme en dur',
    '/7 avenue Paul C/iu'                                => 'adresse de l’organisme en dur',
    '/405109901|93 83 08347/'                            => 'SIRET ou NDA en dur',
    '/header_footer_bg/'                                 => 'bandeau d’en-tête propre au document',
);

$rii  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits = array();
foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }
    $rel  = preg_replace( '#^.*/includes/#', '', $path );
    $src  = (string) file_get_contents( $path );

    foreach ( $surveilles as $fn ) {
        $start = strpos( $src, 'function ' . $fn . '(' );
        if ( false === $start ) { continue; }
        /* Le corps s'arrête à la déclaration suivante — approximation suffisante
           pour un balayage textuel, et volontairement large. */
        $next = strpos( $src, "\n  private function ", $start + 10 );
        $body = false === $next ? substr( $src, $start ) : substr( $src, $start, $next - $start );

        if ( false === strpos( $body, 'acdc_pdf_charte_header' ) ) {
            $hits[] = sprintf( '%s  %s() ne passe pas par acdc_pdf_charte_header()', $rel, $fn );
        }
        foreach ( $interdits as $motif => $quoi ) {
            $motif = rtrim( $motif, "'" );
            foreach ( explode( "\n", $body ) as $i => $line ) {
                $t = ltrim( $line );
                if ( '' === $t || 0 === strpos( $t, '*' ) || 0 === strpos( $t, '//' ) || 0 === strpos( $t, '/*' ) ) { continue; }
                /* On ne vise que ce qui DESSINE : une ligne de texte posée sur
                   la page, ou un bandeau. Une valeur de repli pour un réglage
                   vide — « ?? '7 avenue Paul Cézanne' » dans l'article qui
                   identifie les parties — n'imprime rien par elle-même et reste
                   légitime : une convention sans adresse d'organisme serait
                   pire qu'une convention avec une adresse par défaut. */
                $dessine = ( false !== strpos( $line, "'text'" ) ) || ( false !== strpos( $line, 'header_footer_bg' ) );
                if ( $dessine && @preg_match( $motif, $line ) ) {
                    $hits[] = sprintf( '%s  %s() : %s — %s', $rel, $fn, $quoi, trim( $line ) );
                }
            }
        }
    }
}

if ( $hits ) {
    echo "Documents de la charte qui dessinent leur propre identité :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
echo "0 occurrence — les documents de la charte passent tous par le gabarit commun.\n";
exit( 0 );
