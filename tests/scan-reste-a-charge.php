<?php
/**
 * Le reste à charge se calcule ; il ne se saisit pas.
 *
 * Quand un OPCO prend en charge une partie de la formation, deux montants
 * existent : la part accordée et le reste. Les faire saisir tous les deux,
 * c'est créer deux listes de la même vérité — la panne qui revient le plus
 * souvent dans ce plugin. Le jour où le tarif change, l'un des deux ment, et
 * la somme des deux factures ne fait plus le total de la prestation.
 *
 * La règle : le reste est une SOUSTRACTION, faite à un seul endroit
 * (ACDC\Support\FundingSplit), en centimes entiers. Ce balayage vérifie deux
 * choses :
 *
 *   1. aucun champ de formulaire ne propose de taper un reste à charge ;
 *   2. aucun autre fichier ne refait la soustraction dans son coin.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

$rii   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits  = array();
$vu_split = false;

foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }

    $src = (string) file_get_contents( $path );
    $rel = preg_replace( '#^.*/(includes|src)/#', '', $path );

    /* Le calculateur officiel : c'est le seul autorisé à soustraire. */
    if ( false !== strpos( $path, 'Support/FundingSplit.php' ) ) {
        $vu_split = ( false !== strpos( $src, '$total_c - $pec_c' ) );
        continue;
    }

    foreach ( explode( "\n", $src ) as $i => $line ) {
        $t = ltrim( $line );
        if ( 0 === strpos( $t, '*' ) || 0 === strpos( $t, '//' ) || 0 === strpos( $t, '/*' ) ) { continue; }

        /* 1. Un champ de saisie pour le reste à charge. */
        if ( preg_match( '/name=(["\'])[^"\']*(reste_a_charge|remaining_amount|reliquat)[^"\']*\1/i', $line ) ) {
            $hits[] = sprintf( '%s:%d  champ de saisie du reste à charge — il doit être calculé.  %s', $rel, $i + 1, trim( $line ) );
        }

        /* 2. Une seconde soustraction : total moins prise en charge. */
        if ( preg_match( '/\$\w*(total|price|tarif)\w*\s*-\s*\$\w*(pec|prise_en_charge|funder|financeur)\w*/i', $line ) ) {
            $hits[] = sprintf( '%s:%d  répartition refaite hors de FundingSplit.  %s', $rel, $i + 1, trim( $line ) );
        }
    }
}

if ( ! $vu_split ) {
    $hits[] = 'ACDC\Support\FundingSplit ne fait plus la soustraction en centimes : le calcul du reste à charge a changé de main.';
}

if ( $hits ) {
    echo "Reste à charge — anomalies :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
echo "0 occurrence — le reste à charge est calculé, à un seul endroit.\n";
exit( 0 );
