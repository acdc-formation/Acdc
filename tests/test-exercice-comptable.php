<?php
/**
 * L'exercice comptable ne suit pas toujours l'année civile.
 *
 * Celui de la recette court du 23 avril au 22 avril : « l'exercice 2025 » va
 * donc du 23/04/2025 au 22/04/2026 et chevauche deux millésimes.
 *
 * CE QUE CE TEST PROTÈGE. Le générateur du BPF des prestations extérieures
 * bornait l'exercice au 1er janvier — 31 décembre. Sur cet exercice-là, une
 * intervention de novembre serait tombée dans le bon exercice par hasard, et
 * une intervention de mars dans le mauvais. Le PDF, lui, n'aurait rien signalé :
 * il aurait porté des dates justes en en-tête et des chiffres pris ailleurs.
 * Une déclaration fausse qui a l'air juste est le pire résultat possible ici.
 *
 * L'arithmétique vivait à l'intérieur de la fonction qui construit la liste des
 * BPF. Elle n'y était pas fausse, elle y était seule — et un second BPF ayant
 * besoin des mêmes bornes, les recalculer à côté aurait fini par diverger.
 */

define( 'ACDC_SUPPORT_TESTING', true );
require_once __DIR__ . '/../acdc-formation-saas-organisme-de-formation/src/Support/FiscalYear.php';

use ACDC\Support\FiscalYear;

$ko = 0;
$t  = function ( $label, $obtenu, $attendu ) use ( &$ko ) {
    if ( (string) $obtenu !== (string) $attendu ) {
        $ko++;
        printf( "ÉCHEC  %s : attendu « %s », obtenu « %s »\n", $label, $attendu, $obtenu );
    }
};

/* ── L'EXERCICE DE LA RECETTE : 23/04 → 22/04 ───────────────────────────── */
list( $d, $f ) = FiscalYear::displayBounds( '23/04', '22/04', 2025 );
$t( 'début affiché', $d, '23/04/2025' );
$t( 'fin affichée',  $f, '22/04/2026' );

list( $ds, $fs ) = FiscalYear::sqlBounds( '23/04', '22/04', 2025 );
$t( 'début comparable', $ds, '2025-04-23' );
$t( 'fin comparable',   $fs, '2026-04-22' );

/* L'exercice suivant s'enchaîne sans trou ni recouvrement : la veille de son
   ouverture est exactement la clôture du précédent. */
list( $ds26 ) = FiscalYear::sqlBounds( '23/04', '22/04', 2026 );
$t( 'les exercices s’enchaînent', $ds26, '2026-04-23' );
$t( 'sans recouvrement', $fs < $ds26 ? 'ok' : 'chevauchement', 'ok' );

/* ── L'ANNÉE CIVILE RESTE UN CAS PARTICULIER, PAS UNE EXCEPTION ─────────── */
list( $dc, $fc ) = FiscalYear::displayBounds( '01/01', '31/12', 2025 );
$t( 'année civile : début', $dc, '01/01/2025' );
$t( 'année civile : fin',   $fc, '31/12/2025' );

/* ── UN EXERCICE DE FIN DE MOIS ─────────────────────────────────────────── */
list( $dj, $fj ) = FiscalYear::displayBounds( '01/07', '30/06', 2024 );
$t( 'juillet à juin : début', $dj, '01/07/2024' );
$t( 'juillet à juin : fin',   $fj, '30/06/2025' );

/* Même mois, jour de fin après le jour de début : un seul millésime. */
list( , $fm ) = FiscalYear::displayBounds( '01/04', '30/04', 2025 );
$t( 'même mois, un seul millésime', $fm, '30/04/2025' );
/* Même mois, jour de fin avant : l'exercice court sur douze mois. */
list( , $fm2 ) = FiscalYear::displayBounds( '23/04', '22/04', 2025 );
$t( 'même mois, douze mois', $fm2, '22/04/2026' );

/* ── À QUEL EXERCICE APPARTIENT UNE DATE ────────────────────────────────── */
/* C'est ce qui décide du millésime proposé par défaut : au 15 mars 2026, on est
   ENCORE dans l'exercice 2025. Proposer 2026 ferait générer un BPF presque vide
   sans que rien ne le signale. */
$t( 'le 15/03/2026 relève de l’exercice', FiscalYear::yearOf( '23/04', '22/04', '2026-03-15' ), 2025 );
$t( 'le 23/04/2026 ouvre le suivant',     FiscalYear::yearOf( '23/04', '22/04', '2026-04-23' ), 2026 );
$t( 'le 22/04/2026 clôt le précédent',    FiscalYear::yearOf( '23/04', '22/04', '2026-04-22' ), 2025 );
$t( 'année civile : sans surprise',       FiscalYear::yearOf( '01/01', '31/12', '2025-03-15' ), 2025 );

/* ── LES SAISIES HUMAINES ───────────────────────────────────────────────── */
/* Le champ est libre : on y trouve « 23/04 », « 23/04/ » et « 23/04/2025 ». */
$t( 'barre finale',        FiscalYear::displayBounds( '23/04/', '22/04/', 2025 )[0], '23/04/2025' );
$t( 'année parasite',      FiscalYear::displayBounds( '23/04/2025', '22/04/2026', 2025 )[0], '23/04/2025' );
$t( 'un seul chiffre',     FiscalYear::displayBounds( '1/7', '30/6', 2025 )[0], '01/07/2025' );
/* Une saisie illisible retombe sur l'année civile plutôt que sur une date
   absurde : un exercice mal borné ne se voit pas sur le PDF, il se voit sur les
   chiffres, bien plus tard. */
$t( 'saisie vide',         FiscalYear::displayBounds( '', '', 2025 )[0], '01/01/2025' );
$t( 'saisie illisible',    FiscalYear::displayBounds( 'plus tard', 'jamais', 2025 )[1], '31/12/2025' );
$t( 'mois impossible',     FiscalYear::displayBounds( '23/13', '22/04', 2025 )[0], '01/01/2025' );

if ( $ko ) {
    printf( "%d échec(s)\n", $ko );
    exit( 1 );
}
echo "Exercice comptable : 21 cas vérifiés, un exercice à cheval sur deux années reste borné juste.\n";
exit( 0 );
