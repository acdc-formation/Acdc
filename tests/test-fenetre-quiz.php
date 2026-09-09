<?php
/**
 * Les quiz visibles chez le formateur : ni trop tôt, ni pour toujours.
 *
 * Demandé en recette : « les quiz correspondant aux formations qu'il fait
 * doivent être visibles pendant la formation, mais disparaître quand la
 * formation est terminée — un ou deux jours de battement. »
 *
 * Ce que ce test protège n'est pas un confort d'affichage. Sans borne, la liste
 * du formateur ne fait que grossir : au bout d'un an, trouver le quiz du jour
 * tient de la fouille, et un quiz qu'on cherche est un quiz qu'on ne lance pas.
 * À l'inverse, une fenêtre trop serrée ferait disparaître un quiz le matin même
 * de la formation — la panne la plus coûteuse possible, puisqu'elle se découvre
 * devant les apprenants.
 */

define( 'ACDC_SUPPORT_TESTING', true );
require_once __DIR__ . '/../acdc-formation-saas-organisme-de-formation/src/Support/QuizWindow.php';

use ACDC\Support\QuizWindow;

$ko = 0;
$t  = function ( $label, $obtenu, $attendu ) use ( &$ko ) {
    if ( (string) $obtenu !== (string) $attendu ) {
        $ko++;
        printf( "ÉCHEC  %s : attendu « %s », obtenu « %s »\n", $label, $attendu, $obtenu );
    }
};
$oui = function ( $b ) { return $b ? 'oui' : 'non'; };

/* Réglage retenu par David : visible dès J-1, retiré à J+2. */
$AVANT = 1;
$APRES = 2;

/* ── UNE FORMATION D'UNE JOURNÉE, LE 15 ─────────────────────────────────── */
$t( 'la veille : visible',      $oui( QuizWindow::isVisible( '2026-08-15', '2026-08-15', '2026-08-14', $AVANT, $APRES ) ), 'oui' );
$t( 'le jour même : visible',   $oui( QuizWindow::isVisible( '2026-08-15', '2026-08-15', '2026-08-15', $AVANT, $APRES ) ), 'oui' );
$t( 'deux jours avant : non',   $oui( QuizWindow::isVisible( '2026-08-15', '2026-08-15', '2026-08-13', $AVANT, $APRES ) ), 'non' );
$t( 'le lendemain : visible',   $oui( QuizWindow::isVisible( '2026-08-15', '2026-08-15', '2026-08-16', $AVANT, $APRES ) ), 'oui' );
$t( 'deux jours après : visible', $oui( QuizWindow::isVisible( '2026-08-15', '2026-08-15', '2026-08-17', $AVANT, $APRES ) ), 'oui' );
$t( 'trois jours après : retiré', $oui( QuizWindow::isVisible( '2026-08-15', '2026-08-15', '2026-08-18', $AVANT, $APRES ) ), 'non' );

/* ── UNE FORMATION DE SEPT JOURS ─────────────────────────────────────────
   Elle doit rester visible du premier au dernier jour, sans cas particulier :
   c'est tout l'intérêt de raisonner en chevauchement plutôt qu'en durée. */
$debut = '2026-09-07';
$fin   = '2026-09-13';
$t( 'sept jours, la veille',        $oui( QuizWindow::isVisible( $debut, $fin, '2026-09-06', $AVANT, $APRES ) ), 'oui' );
$t( 'sept jours, au milieu',        $oui( QuizWindow::isVisible( $debut, $fin, '2026-09-10', $AVANT, $APRES ) ), 'oui' );
$t( 'sept jours, dernier jour',     $oui( QuizWindow::isVisible( $debut, $fin, '2026-09-13', $AVANT, $APRES ) ), 'oui' );
$t( 'sept jours, J+2 après la fin', $oui( QuizWindow::isVisible( $debut, $fin, '2026-09-15', $AVANT, $APRES ) ), 'oui' );
$t( 'sept jours, J+3 : retiré',     $oui( QuizWindow::isVisible( $debut, $fin, '2026-09-16', $AVANT, $APRES ) ), 'non' );
$t( 'sept jours, une semaine avant : non', $oui( QuizWindow::isVisible( $debut, $fin, '2026-08-31', $AVANT, $APRES ) ), 'non' );

/* ── LES DONNÉES INCOMPLÈTES NE FONT PAS DISPARAÎTRE UN QUIZ ─────────────
   Une séance sans date de fin dure une journée. Une séance sans AUCUNE date
   est un défaut à corriger : la cacher n'aiderait personne, et le formateur
   devant sa salle ne peut pas la corriger. */
$t( 'sans date de fin : traitée comme une journée', $oui( QuizWindow::isVisible( '2026-08-15', '', '2026-08-15', $AVANT, $APRES ) ), 'oui' );
$t( 'sans date de fin, J+3 : retiré',               $oui( QuizWindow::isVisible( '2026-08-15', '', '2026-08-18', $AVANT, $APRES ) ), 'non' );
$t( 'fin antérieure au début : la fin est ignorée', $oui( QuizWindow::isVisible( '2026-08-15', '2026-08-01', '2026-08-15', $AVANT, $APRES ) ), 'oui' );
$t( 'aucune date : on n’efface pas',                $oui( QuizWindow::isVisible( '', '', '2026-08-15', $AVANT, $APRES ) ), 'oui' );
$t( 'date à zéro : on n’efface pas',                $oui( QuizWindow::isVisible( '0000-00-00', '', '2026-08-15', $AVANT, $APRES ) ), 'oui' );

/* ── LES BORNES SONT CALCULÉES ICI, PAS EN SQL ──────────────────────────── */
$b = QuizWindow::bounds( '2026-08-15', 1, 2 );
$t( 'borne de début', $b['debut_au_plus_tard'], '2026-08-16' );
$t( 'borne de fin',   $b['fin_au_plus_tot'],    '2026-08-13' );

/* Un battement à zéro est légitime : visible le jour même, retiré le lendemain. */
$t( 'battement nul : le jour même',   $oui( QuizWindow::isVisible( '2026-08-15', '2026-08-15', '2026-08-15', 0, 0 ) ), 'oui' );
$t( 'battement nul : la veille, non', $oui( QuizWindow::isVisible( '2026-08-15', '2026-08-15', '2026-08-14', 0, 0 ) ), 'non' );

/* ── UN RÉGLAGE ABERRANT NE RAMÈNE PAS TROIS ANS D'ARCHIVES ─────────────── */
$t( 'négatif ramené à zéro',      QuizWindow::clamp( -5 ), 0 );
$t( 'valeur folle plafonnée',     QuizWindow::clamp( 9999 ), 60 );
$t( 'vide : valeur par défaut',   QuizWindow::clamp( '', 2 ), 2 );
$t( 'texte : valeur par défaut',  QuizWindow::clamp( 'deux', 1 ), 1 );
$t( 'valeur normale conservée',   QuizWindow::clamp( 3 ), 3 );

printf( "%d contrôles, %d échec(s)\n", 26, $ko );
exit( 0 === $ko ? 0 : 1 );
