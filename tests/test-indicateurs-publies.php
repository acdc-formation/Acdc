<?php
/**
 * Ce que le site vitrine annonce : deux activités, deux unités d'heures.
 *
 * Relevé en recette : la page d'accueil annonçait « 73 apprenants formés » et
 * « 16 heures de formation dispensées ». Le 73 ne venait d'aucune mesure — il
 * était recopié à la main dans les réglages du site vitrine, parce que le SAAS
 * ne remontait que l'activité de l'organisme et que celle-ci ne représentait
 * qu'une petite part du travail réellement animé.
 *
 * DEUX UNITÉS QU'ON NE MÉLANGE PAS. Une journée de 7 h devant douze personnes
 * fait 7 heures DISPENSÉES et 84 heures SUIVIES. Les deux sont vraies. Les
 * confondre — afficher des heures-stagiaires sous le libellé « dispensées » —
 * produit un chiffre juste sous un nom faux, ce qui est plus trompeur qu'une
 * case vide : personne ne vient vérifier un chiffre qui a l'air renseigné.
 *
 * Ce test protège les deux séparations : l'organisme et les prestations d'un
 * côté, les heures dispensées et les heures suivies de l'autre.
 */

define( 'ACDC_SUPPORT_TESTING', true );
require_once __DIR__ . '/../acdc-formation-saas-organisme-de-formation/src/Support/PublicIndicators.php';

use ACDC\Support\PublicIndicators;

$ko = 0;
$t  = function ( $label, $obtenu, $attendu ) use ( &$ko ) {
    if ( (string) $obtenu !== (string) $attendu ) {
        $ko++;
        printf( "ÉCHEC  %s : attendu « %s », obtenu « %s »\n", $label, $attendu, $obtenu );
    }
};

/* ── LE TOTAL DES PRESTATIONS EXTÉRIEURES ────────────────────────────────── */
$fiches = array(
    array( 'nb_stagiaires' => 2,  'heures_par_stagiaire' => 7,   'heures_total' => 14,   'ca_ht' => 320 ),
    array( 'nb_stagiaires' => 11, 'heures_par_stagiaire' => 3.5, 'heures_total' => 38.5, 'ca_ht' => 157.50 ),
    array( 'nb_stagiaires' => 9,  'heures_par_stagiaire' => 73.5,'heures_total' => 661.5,'ca_ht' => 2572.50 ),
);
$ext = PublicIndicators::externalTotals( $fiches );
$t( 'stagiaires',        $ext['stagiaires'],        22 );
$t( 'heures dispensées', $ext['heures_dispensees'], 84 );
$t( 'heures suivies',    $ext['heures_suivies'],    714 );
$t( 'chiffre d’affaires', $ext['ca_ht'],            3050 );
$t( 'nombre de fiches',  $ext['nb'],                3 );

/* Les deux unités ne se confondent jamais : 84 heures animées, 714 suivies. */
$t(
    'dispensées et suivies restent distinctes',
    $ext['heures_dispensees'] === $ext['heures_suivies'] ? 'confondues' : 'distinctes',
    'distinctes'
);

/* ── CE QUI MANQUE SE COMPLÈTE, CE QUI NE SE DÉDUIT PAS RESTE VIDE ───────── */
$t(
    'total absent mais déductible',
    PublicIndicators::externalTotals( array( array( 'nb_stagiaires' => 4, 'heures_par_stagiaire' => 7 ) ) )['heures_suivies'],
    28
);
$t(
    'sans stagiaires, rien à déduire',
    PublicIndicators::externalTotals( array( array( 'heures_par_stagiaire' => 7 ) ) )['heures_suivies'],
    0
);
/* Un total saisi qui s'écarte du produit est CONSERVÉ : c'est un constat, pas
   une erreur — tout le monde n'assiste pas toujours à tout. */
$t(
    'un total constaté n’est pas recalculé',
    PublicIndicators::externalTotals( array( array( 'nb_stagiaires' => 10, 'heures_par_stagiaire' => 7, 'heures_total' => 52 ) ) )['heures_suivies'],
    52
);

/* ── LES SAISIES HUMAINES ────────────────────────────────────────────────── */
$t(
    'virgule décimale',
    PublicIndicators::externalTotals( array( array( 'nb_stagiaires' => '2', 'heures_par_stagiaire' => '7,5', 'heures_total' => '15' ) ) )['heures_dispensees'],
    7.5
);
$t( 'aucune fiche',      PublicIndicators::externalTotals( array() )['stagiaires'], 0 );
$t( 'entrée invalide',   PublicIndicators::externalTotals( 'rien' )['stagiaires'],  0 );

/* ── LES HEURES SUIVIES DE L'ORGANISME ───────────────────────────────────── */
/* Séance par séance, jamais globalement : deux séances de 7 h, l'une à 2
   apprenants et l'autre à 10, font 84 heures suivies — et non 14 × 12. */
$t(
    'séance par séance',
    PublicIndicators::attendedHours( array(
        array( 'minutes' => 420, 'apprenants' => 2 ),
        array( 'minutes' => 420, 'apprenants' => 10 ),
    ) ),
    84
);
$t( 'séance sans apprenant', PublicIndicators::attendedHours( array( array( 'minutes' => 420, 'apprenants' => 0 ) ) ), 0 );
$t( 'aucune séance',         PublicIndicators::attendedHours( array() ), 0 );

/* ── LA PUBLICATION : LES DEUX VÉRITÉS COHABITENT ───────────────────────── */
$publie = PublicIndicators::publishable(
    array( 'apprenants' => 12, 'heures_dispensees' => 16, 'heures_suivies' => 192 ),
    $ext
);
/* Les clés d'origine gardent leur sens : un site vitrine non mis à jour
   continue d'afficher l'organisme seul, et ne gonfle jamais ses chiffres tout
   seul. C'est la promesse la plus importante de cette structure. */
$t( 'clé d’origine : apprenants', $publie['total_apprenants'], 12 );
$t( 'clé d’origine : heures',     $publie['total_heures'],     16 );
/* Et les nouvelles clés portent le total. */
$t( 'tout compris : apprenants',  $publie['total_apprenants_tous'],        34 );
$t( 'tout compris : dispensées',  $publie['total_heures_dispensees_tous'], 100 );
$t( 'tout compris : suivies',     $publie['total_heures_suivies_tous'],    906 );
/* Le détail permet de défendre chaque chiffre devant qui le demande. */
$t( 'détail organisme',   $publie['detail']['organisme']['apprenants'],    12 );
$t( 'détail prestations', $publie['detail']['prestations']['stagiaires'],  22 );
$t( 'détail nb fiches',   $publie['detail']['prestations']['nb'],          3 );

if ( $ko ) {
    printf( "%d échec(s)\n", $ko );
    exit( 1 );
}
echo "Indicateurs publiés : 22 cas vérifiés, les deux activités et les deux unités d'heures restent distinctes.\n";
exit( 0 );
