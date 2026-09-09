<?php
/**
 * Une seule convocation, composée à un seul endroit.
 *
 * Il y avait trois expéditeurs — l'envoi manuel, l'envoi automatique d'une
 * séance, et le moteur qui expédie la veille à 17 h — et chacun écrivait son
 * propre récapitulatif. Résultat : selon le chemin emprunté par l'organisme,
 * la même personne recevait pour la même formation un courrier différent. Le
 * moteur, qui en envoie le plus, était le plus pauvre : ni horaires, ni lieu,
 * ni document joint.
 *
 * Ce balayage cherche les blocs qui composent un récapitulatif de convocation
 * ailleurs que dans le composeur commun : un tableau de lignes
 * (`'label' => …`) dans les parages d'un « DÉTAILS DE VOTRE CONVOCATION ».
 *
 * Le composeur lui-même — acdc_convocation_email_parts() dans le noyau — est
 * évidemment autorisé : c'est lui, l'endroit unique.
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
        if ( false === strpos( $line, 'DÉTAILS DE VOTRE CONVOCATION' ) ) { continue; }

        /* Le composeur commun est le seul autorisé à bâtir ces lignes ; on
           regarde les vingt lignes qui précèdent l'envoi, là où le
           récapitulatif est assemblé. */
        $window = implode( "\n", array_slice( $lines, max( 0, $i - 20 ), 22 ) );
        if ( false !== strpos( $window, 'acdc_convocation_email_parts' ) ) { continue; }
        if ( false !== strpos( $rel, 'kernel/class-acdc-kernel-core-trait.php' ) ) { continue; }

        $hits[] = sprintf( '%s:%d  récapitulatif de convocation composé hors du composeur commun', $rel, $i + 1 );
    }
}

if ( $hits ) {
    echo "Convocations composées ailleurs que dans acdc_convocation_email_parts() :\n";
    foreach ( $hits as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( $hits ) );
    exit( 1 );
}
echo "0 occurrence — la convocation ne se compose qu'à un seul endroit.\n";
exit( 0 );
