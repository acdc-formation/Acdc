<?php
/**
 * Deux documents, deux destinataires : les mots ne doivent pas s'échanger.
 *
 * Le CERTIFICAT DE RÉALISATION atteste qu'une action a eu lieu — dates, durée,
 * assiduité. C'est la pièce que le FINANCEUR réclame pour solder un dossier
 * (décret du 21 décembre 2018, modèle du ministère).
 *
 * L'ATTESTATION DE FIN DE FORMATION porte les objectifs, la nature et la durée
 * de l'action et les RÉSULTATS DE L'ÉVALUATION DES ACQUIS. C'est la pièce de
 * l'APPRENANT (article L6353-1 du code du travail).
 *
 * La barre de complétude du dossier — l'écran qu'on regarde tous les jours —
 * les nommait à l'envers, sous deux libellés qui ne sont ni l'un ni l'autre un
 * nom légal : « Attestation de formation » cochait la présence du certificat
 * de réalisation, et « Certificat de formation » celle de l'attestation. Elles
 * ne valent pas le même nombre de points : le score allait au mauvais document.
 *
 * Ce balayage refuse les deux noms ambigus, et vérifie qu'aucun libellé n'est
 * accroché à la mauvaise colonne.
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
        $t = ltrim( $line );
        if ( 0 === strpos( $t, '*' ) || 0 === strpos( $t, '//' ) || 0 === strpos( $t, '/*' ) ) { continue; }

        /* 1. Les deux noms ambigus, qui ne désignent rien de légal. */
        if ( preg_match( '/[\'"](Attestation de formation|Certificat de formation)[\'"]/', $line, $m ) ) {
            $hits[] = sprintf( '%s:%d  « %s » ne désigne aucun document légal : dites « certificat de réalisation » ou « attestation de fin de formation ».  %s', $rel, $i + 1, $m[1], trim( $line ) );
            continue;
        }

        /* 2. Un libellé accroché à la mauvaise colonne, sur la même ligne. */
        $dit_realisation = (bool) preg_match( '/(certificat de r[ée]alisation)/i', $line );
        $dit_attestation = (bool) preg_match( '/(attestation de fin de formation)/i', $line );
        $col_realisation = ( false !== strpos( $line, 'completion_certificate' ) );
        $col_attestation = ( false !== strpos( $line, 'end_training_certificate' ) );

        if ( $dit_realisation && $col_attestation ) {
            $hits[] = sprintf( '%s:%d  « certificat de réalisation » accroché à la colonne de l’ATTESTATION.  %s', $rel, $i + 1, trim( $line ) );
        }
        if ( $dit_attestation && $col_realisation ) {
            $hits[] = sprintf( '%s:%d  « attestation de fin de formation » accrochée à la colonne du CERTIFICAT.  %s', $rel, $i + 1, trim( $line ) );
        }
    }
}

if ( $hits ) {
    echo "Noms des documents de fin de formation — anomalies :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
echo "0 occurrence — chaque document porte son nom légal, sur la bonne colonne.\n";
exit( 0 );
