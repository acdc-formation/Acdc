<?php
/**
 * Le cachet et la signature ne doivent jamais être déformés.
 *
 * Le contrat formateur les plafonnait séparément :
 *
 *     $w = min( 280, largeur * 1.5 );
 *     $h = min( 160, hauteur * 1.5 );
 *
 * Deux plafonds indépendants : dès que l'un mord et pas l'autre, l'image est
 * écrasée. Le cachet d'ACDC fait 1600 × 1200 — il sortait à 255 × 160 au lieu
 * de 255 × 191, soit 16 % d'aplatissement. Un cachet rond devient un ovale, et
 * c'est le tampon d'un organisme certifié qui part chez un financeur.
 *
 * La règle est simple et sans exception : UN SEUL rapport de réduction,
 * appliqué aux deux dimensions.
 */

/** L'ancienne méthode, conservée pour montrer ce qu'elle produisait. */
function echelle_deux_plafonds( $w, $h, $max_w, $max_h, $facteur = 1.5 ) {
    return array( min( $max_w, $w * $facteur ), min( $max_h, $h * $facteur ) );
}

/** La règle retenue : un rapport unique. */
function echelle_rapport_unique( $w, $h, $max_w, $max_h ) {
    $ratio = min( $max_w / $w, $max_h / $h, 1 );
    return array( round( $w * $ratio, 2 ), round( $h * $ratio, 2 ) );
}

/** Écart de proportion entre l'original et le rendu, en pourcentage. */
function deformation( $w, $h, $dw, $dh ) {
    if ( $w <= 0 || $h <= 0 || $dw <= 0 || $dh <= 0 ) { return 100.0; }
    return round( abs( 1 - ( ( $dw / $dh ) / ( $w / $h ) ) ) * 100, 2 );
}

$ko = 0;
$t  = function ( $label, $obtenu, $attendu ) use ( &$ko ) {
    if ( (string) $obtenu !== (string) $attendu ) {
        $ko++;
        printf( "ÉCHEC  %s : attendu « %s », obtenu « %s »\n", $label, $attendu, $obtenu );
    }
};

/* LE CAS RÉEL : le cachet d'ACDC, 1600 × 1200, sur le contrat formateur. */
list( $old_w, $old_h ) = echelle_deux_plafonds( 170, 127.5, 280, 160 );
$t( 'l’ancien calcul écrasait bien le cachet',
    deformation( 1600, 1200, $old_w, $old_h ) > 10 ? 'déformé' : 'juste', 'déformé' );

list( $new_w, $new_h ) = echelle_rapport_unique( 1600, 1200, 255, 191 );
$t( 'le nouveau calcul respecte les proportions',
    deformation( 1600, 1200, $new_w, $new_h ) <= 0.5 ? 'juste' : 'déformé', 'juste' );
$t( 'largeur du cachet',  $new_w, 254.67 );
$t( 'hauteur du cachet',  $new_h, 191 );

/* La largeur peut être la contrainte : une signature très large et plate. */
list( $sw, $sh ) = echelle_rapport_unique( 968, 320, 255, 191 );
$t( 'une signature large est bornée par sa largeur', $sw, 255 );
$t( 'et sa hauteur suit le même rapport',            $sh, 84.3 );
$t( 'sans déformation',
    deformation( 968, 320, $sw, $sh ) <= 0.5 ? 'juste' : 'déformé', 'juste' );

/* La hauteur peut être la contrainte : une image plus haute que large. */
list( $hw, $hh ) = echelle_rapport_unique( 600, 1200, 255, 191 );
$t( 'une image haute est bornée par sa hauteur', $hh, 191 );
$t( 'et sa largeur suit',                        $hw, 95.5 );

/* Une image déjà plus petite que la place disponible n'est JAMAIS agrandie :
   un cachet de 120 pt étiré à 255 deviendrait flou. */
list( $pw, $ph ) = echelle_rapport_unique( 120, 90, 255, 191 );
$t( 'une petite image n’est pas agrandie (largeur)', $pw, 120 );
$t( 'une petite image n’est pas agrandie (hauteur)', $ph, 90 );

/* Un carré reste carré — c'est le cas du cachet rond. */
list( $cw, $ch ) = echelle_rapport_unique( 1000, 1000, 255, 191 );
$t( 'un carré reste carré', $cw === $ch ? 'oui' : 'non', 'oui' );
$t( 'et tient dans la hauteur disponible', $ch, 191 );

printf( "12 contrôles, %d échec(s)\n", $ko );
exit( 0 === $ko ? 0 : 1 );
