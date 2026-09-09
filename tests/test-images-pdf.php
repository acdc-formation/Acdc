<?php
/**
 * Les images embarquées dans la proposition commerciale.
 *
 * Une proposition pesait 19,5 Mo pour 13 pages, dont 19,1 Mo d'images : le
 * visuel de thématique, 2 à 3 Mo, repris en bandeau sur chaque page. Ces
 * pixels ne sont visibles nulle part — au-delà de 150 dpi sur une largeur A4,
 * ni l'écran ni une imprimante de bureau ne restituent la différence — mais le
 * poids, lui, se voit : une messagerie refuse un document de 19 Mo, et c'est
 * un document commercial dont le seul but est d'arriver chez le client.
 *
 * Trois décisions sont testées ici, celles où une erreur se verrait :
 *   — ne rien toucher en dessous du seuil (le travail coûterait plus qu'il ne
 *     rapporte, et une copie inutile est une copie à invalider) ;
 *   — ne JAMAIS agrandir (une image déjà petite deviendrait floue) ;
 *   — préserver la transparence (un logo aplati sur du blanc laisse un
 *     cartouche blanc sur les fonds colorés du document).
 */

const SEUIL = 153600;   // 150 Ko

/** Décide du format de sortie. Reproduit la lecture d'en-tête PNG de acdc_pdf_image_src(). */
function format_sortie( $entete, $debut_fichier = '' ) {
    if ( "\x89PNG" !== substr( $entete, 0, 4 ) ) {
        return 'jpg';
    }
    $type_couleur = isset( $entete[25] ) ? ord( $entete[25] ) : 0;
    $alpha = in_array( $type_couleur, array( 4, 6 ), true ) || false !== strpos( $debut_fichier, 'tRNS' );
    return $alpha ? 'png' : 'jpg';
}

/** Décide s'il faut fabriquer une copie réduite, et à quelle taille. */
function copie_reduite( $poids, $largeur, $hauteur, $max_w ) {
    if ( $poids <= SEUIL ) {
        return array( 'traiter' => false, 'w' => $largeur, 'h' => $hauteur );
    }
    $ratio = min( 1, $max_w / $largeur );
    return array(
        'traiter' => true,
        'w'       => max( 1, (int) round( $largeur * $ratio ) ),
        'h'       => max( 1, (int) round( $hauteur * $ratio ) ),
    );
}

/* Fabrique un en-tête PNG minimal avec le type de couleur demandé. */
function entete_png( $type_couleur ) {
    /* Signature (8) + longueur (4) + « IHDR » (4) + largeur (4) + hauteur (4)
       = 24 octets ; la profondeur de bits est à l'indice 24, le type de
       couleur à l'indice 25 — c'est celui-là que lit acdc_pdf_image_src(). */
    return "\x89PNG\r\n\x1a\n" . "\x00\x00\x00\x0dIHDR" . str_repeat( "\x00", 8 )
         . chr( 8 ) . chr( $type_couleur ) . str_repeat( "\x00", 8 );
}

$ko = 0;
$t  = function ( $label, $obtenu, $attendu ) use ( &$ko ) {
    if ( (string) $obtenu !== (string) $attendu ) {
        $ko++;
        printf( "ÉCHEC  %s : attendu « %s », obtenu « %s »\n", $label, $attendu, $obtenu );
    }
};

/* --- Format de sortie --- */
$t( 'un JPEG reste un JPEG',            format_sortie( "\xFF\xD8\xFF\xE0" ), 'jpg' );
$t( 'un PNG opaque devient un JPEG',    format_sortie( entete_png( 2 ) ), 'jpg' );          // truecolor sans alpha
$t( 'un PNG à canal alpha reste PNG',   format_sortie( entete_png( 6 ) ), 'png' );          // truecolor + alpha
$t( 'un gris + alpha reste PNG',        format_sortie( entete_png( 4 ) ), 'png' );
$t( 'une palette transparente reste PNG', format_sortie( entete_png( 3 ), 'xxtRNSxx' ), 'png' );
$t( 'une palette opaque devient JPEG',  format_sortie( entete_png( 3 ), 'xxIDATxx' ), 'jpg' );

/* --- Décision de réduction --- */
$petit = copie_reduite( 40 * 1024, 300, 200, 1240 );
$t( 'une image légère n’est pas touchée', $petit['traiter'] ? 'oui' : 'non', 'non' );

$logo = copie_reduite( 200 * 1024, 240, 90, 400 );
$t( 'une image lourde mais étroite n’est jamais agrandie (largeur)', $logo['w'], 240 );
$t( 'une image lourde mais étroite n’est jamais agrandie (hauteur)', $logo['h'], 90 );

$visuel = copie_reduite( 2600 * 1024, 3000, 1260, 1240 );
$t( 'le visuel de thématique descend à la largeur utile', $visuel['w'], 1240 );
$t( 'les proportions sont conservées',                    $visuel['h'], 521 );

$seuil_pile = copie_reduite( SEUIL, 4000, 3000, 1240 );
$t( 'pile au seuil, on ne traite pas', $seuil_pile['traiter'] ? 'oui' : 'non', 'non' );

$juste_au_dessus = copie_reduite( SEUIL + 1, 4000, 3000, 1240 );
$t( 'un octet au-dessus, on traite', $juste_au_dessus['traiter'] ? 'oui' : 'non', 'oui' );

/* Une image très haute et étroite : la contrainte porte sur la largeur, et
   une hauteur ne tombe jamais à zéro. */
$bandeau = copie_reduite( 500 * 1024, 6000, 40, 1240 );
$t( 'une image très plate garde au moins un pixel de haut', $bandeau['h'] >= 1 ? 'oui' : 'non', 'oui' );

printf( "14 cas, %d échec(s)\n", $ko );
exit( 0 === $ko ? 0 : 1 );
