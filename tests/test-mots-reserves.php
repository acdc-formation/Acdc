<?php
/**
 * Le calendrier ne s'arrête plus, et il ne s'arrêtera plus l'an prochain.
 *
 * Relevé en recette : « le calendrier des rendez-vous et des séances s'arrête
 * en décembre 2026 ; quand je clique sur le mois suivant j'ai cette page ».
 * Aucune borne n'était en cause — le calendrier acceptait de 2000 à 2100. La
 * flèche fabriquait `?tab=sessions_calendar&month=1&year=2027`, et `year` est
 * le paramètre des archives par date de WordPress : l'adresse ne demandait plus
 * la page du tableau de bord mais « la page publiée en 2027 ».
 *
 * Ce que ce test protège n'est donc pas une limite haute : c'est le FORMAT de
 * l'adresse. Un mois voyage en un seul paramètre à nous, et les anciennes
 * adresses restent lisibles — un favori ne doit pas mourir d'un correctif.
 */

define( 'ACDC_SUPPORT_TESTING', true );
require_once __DIR__ . '/../acdc-formation-saas-organisme-de-formation/src/Support/ScreenQuery.php';

use ACDC\Support\ScreenQuery;

$ko = 0;
$t  = function ( $label, $obtenu, $attendu ) use ( &$ko ) {
    if ( (string) $obtenu !== (string) $attendu ) {
        $ko++;
        printf( "ÉCHEC  %s : attendu « %s », obtenu « %s »\n", $label, $attendu, $obtenu );
    }
};

/* ── LES MOTS CONFISQUÉS ─────────────────────────────────────────────────── */
$t( 'year est réservé',   ScreenQuery::isReserved( 'year' ) ? 'oui' : 'non',   'oui' );
$t( 'paged est réservé',  ScreenQuery::isReserved( 'paged' ) ? 'oui' : 'non',  'oui' );
$t( 's est réservé',      ScreenQuery::isReserved( 's' ) ? 'oui' : 'non',      'oui' );
$t( 'YEAR en majuscules aussi', ScreenQuery::isReserved( 'YEAR' ) ? 'oui' : 'non', 'oui' );
/* `month` n'en est PAS un — WordPress dit `monthnum`. Le croire réservé
   ferait renommer un paramètre inoffensif et manquerait le vrai coupable. */
$t( 'month n’est pas réservé',      ScreenQuery::isReserved( 'month' ) ? 'oui' : 'non',      'non' );
$t( 'acdc_month n’est pas réservé', ScreenQuery::isReserved( 'acdc_month' ) ? 'oui' : 'non', 'non' );
$t( 'item_id n’est pas réservé',    ScreenQuery::isReserved( 'item_id' ) ? 'oui' : 'non',    'non' );

/* ── LE MOIS S'ÉCRIT EN UN SEUL MOT ──────────────────────────────────────── */
$t( 'janvier 2027',  ScreenQuery::monthParam( 2027, 1 ),  '2027-01' );
$t( 'décembre 2026', ScreenQuery::monthParam( 2026, 12 ), '2026-12' );
$t( 'un mois hors bornes est ramené', ScreenQuery::monthParam( 2027, 13 ), '2027-12' );

/* ── LA LECTURE ──────────────────────────────────────────────────────────── */
$r = ScreenQuery::readMonth( array( 'acdc_month' => '2027-01' ), 2026, 8 );
$t( 'lecture : année', $r['year'], 2027 );
$t( 'lecture : mois',  $r['month'], 1 );

/* LE PASSAGE D'ANNÉE, celui qui cassait. */
$r = ScreenQuery::readMonth( array( 'acdc_month' => '2031-07' ), 2026, 8 );
$t( 'cinq ans plus tard : année', $r['year'], 2031 );
$t( 'cinq ans plus tard : mois',  $r['month'], 7 );

/* Les adresses d'hier restent lisibles : un favori ne meurt pas d'un
   correctif, et c'est précisément l'adresse que David a dans son navigateur. */
$r = ScreenQuery::readMonth( array( 'month' => '1', 'year' => '2027' ), 2026, 8 );
$t( 'ancienne adresse : année', $r['year'], 2027 );
$t( 'ancienne adresse : mois',  $r['month'], 1 );

/* Le nouveau format l'emporte quand les deux sont là. */
$r = ScreenQuery::readMonth( array( 'acdc_month' => '2028-03', 'month' => '1', 'year' => '2027' ), 2026, 8 );
$t( 'le format d’aujourd’hui gagne', $r['year'] . '-' . $r['month'], '2028-3' );

/* ── CE QUI NE DOIT JAMAIS FAIRE DÉRAILLER L'ÉCRAN ───────────────────────── */
$r = ScreenQuery::readMonth( array(), 2026, 8 );
$t( 'rien de demandé : mois courant', $r['year'] . '-' . $r['month'], '2026-8' );

$r = ScreenQuery::readMonth( array( 'acdc_month' => 'n’importe quoi' ), 2026, 8 );
$t( 'valeur illisible : mois courant', $r['year'] . '-' . $r['month'], '2026-8' );

$r = ScreenQuery::readMonth( array( 'acdc_month' => '2027-13' ), 2026, 8 );
$t( 'mois impossible : mois courant', $r['year'] . '-' . $r['month'], '2026-8' );

/* Une adresse trafiquée ne fait pas calculer une grille absurde. Ce n'est pas
   une limite de gestion : le calendrier n'a pas de terme. */
$r = ScreenQuery::readMonth( array( 'acdc_month' => '900000-01' ), 2026, 8 );
$t( 'année absurde : mois courant', $r['year'], 2026 );
$t( '2199 reste acceptée', ScreenQuery::yearIsSane( 2199 ) ? 'oui' : 'non', 'oui' );
$t( '2201 non',            ScreenQuery::yearIsSane( 2201 ) ? 'oui' : 'non', 'non' );

/* ── LA PAGINATION ───────────────────────────────────────────────────────── */
$t( 'page demandée',            ScreenQuery::readPaged( array( 'acdc_paged' => '3' ) ), 3 );
$t( 'ancienne adresse paginée', ScreenQuery::readPaged( array( 'paged' => '4' ) ),      4 );
$t( 'rien : première page',     ScreenQuery::readPaged( array() ),                      1 );
$t( 'zéro : première page',     ScreenQuery::readPaged( array( 'acdc_paged' => '0' ) ), 1 );
$t( 'négatif : première page',  ScreenQuery::readPaged( array( 'acdc_paged' => '-7' ) ), 1 );

printf( "%d contrôles, %d échec(s)\n", 28, $ko );
exit( 0 === $ko ? 0 : 1 );
