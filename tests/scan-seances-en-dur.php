<?php
/**
 * Les horaires d'une séance ne s'écrivent pas en dur.
 *
 * « Ne mets pas des dates en dur : 09h00–12h30 / 13h30–17h00 sont les horaires
 * TYPE, mais ils peuvent changer » — la consigne est explicite. Les horaires
 * réels sont saisis journée par journée sur la convention, et relus par
 * acdc_seance_day_settings(). Les valeurs par défaut, elles, vivent dans les
 * réglages des conventions (default_am_start, default_pm_end, …).
 *
 * Une seconde fabrique de séances, dans l'inscription en masse, les écrivait
 * pourtant en dur : « 09:00:00 », « 12:30:00 », « 13:30:00 », « 17:00:00 ». Elle
 * créait deux séances par jour en ignorant le déroulé de la convention. Elle a
 * été supprimée en 3.25.253 ; ce balayage empêche qu'elle revienne.
 *
 * Sont tolérés : les réglages eux-mêmes (qui DÉFINISSENT les horaires type),
 * les gabarits de documents, et le module de signature.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

/* Le motif exact de la fabrique supprimée : une VALEUR de tableau composée
   d'une date et d'un horaire écrit en toutes lettres —

     'start'  => $date . ' 09:00:00',
     'end'    => $date . ' 12:30:00',

   Les bornes de journée utilisées pour trier ou planifier (COALESCE SQL,
   repli d'affichage) ne sont pas visées : elles ne créent aucune séance. */
$motif = '#=>\s*\$[A-Za-z0-9_>\-\[\]]+\s*\.\s*.\s?(09:00|12:30|13:30|17:00):00#';

$rii  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits = array();
foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }
    $rel = preg_replace( '#^.*/includes/#', '', $path );
    /* Les réglages définissent les horaires type : c'est leur rôle. */
    if ( false !== strpos( $rel, 'settings' ) || false !== strpos( $rel, 'templates/' ) ) { continue; }

    foreach ( explode( "\n", (string) file_get_contents( $path ) ) as $i => $line ) {
        $t = ltrim( $line );
        if ( '' === $t || 0 === strpos( $t, '*' ) || 0 === strpos( $t, '//' ) || 0 === strpos( $t, '/*' ) ) { continue; }
        if ( ! preg_match( $motif, $line ) ) { continue; }
        /* Une valeur de repli explicite, lue depuis les réglages, est légitime :
           get_contract_params_options() la fournit, on ne fait que la nommer. */
        if ( false !== strpos( $line, 'default_am_' ) || false !== strpos( $line, 'default_pm_' ) ) { continue; }
        $hits[] = sprintf( '%s:%d  %s', $rel, $i + 1, trim( $line ) );
    }
}

if ( $hits ) {
    echo "Horaires de séance écrits en dur (utiliser le déroulé de la convention) :\n";
    foreach ( $hits as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( $hits ) );
    exit( 1 );
}
echo "0 occurrence — les horaires viennent de la convention ou des réglages.\n";
exit( 0 );
