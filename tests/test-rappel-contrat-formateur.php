<?php
/**
 * Le rappel du contrat formateur : ce qu'il dit, et quand il se tait.
 *
 * « À chaque fois j'oublie de faire le contrat du formateur. » Une convention
 * signée engage une intervention, l'intervention suppose un formateur, le
 * formateur suppose un contrat — et ce dernier maillon n'était réclamé par
 * personne.
 *
 * Ce qui est vérifié ici, c'est la propriété qui fait qu'un rappel reste utile
 * six mois plus tard : il se DÉDUIT des données. Il s'éteint le jour où le
 * contrat existe, sans que personne ne le ferme. Un rappel qu'il faut penser à
 * éteindre a exactement le défaut de l'oubli qu'il corrige.
 */

define( 'ACDC_SUPPORT_TESTING', true );
if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'DAY_IN_SECONDS', 86400 ); }
require_once __DIR__ . '/../acdc-formation-saas-organisme-de-formation/src/Support/TrainerContractReminder.php';

use ACDC\Support\TrainerContractReminder as Rappel;

$ko = 0;
$t  = function ( $label, $obtenu, $attendu ) use ( &$ko ) {
    if ( (string) $obtenu !== (string) $attendu ) {
        $ko++;
        printf( "ÉCHEC  %s : attendu « %s », obtenu « %s »\n", $label, $attendu, $obtenu );
    }
};

/* ── Les trois états qui appellent un rappel ──────────────────────────────── */

/* Aucun formateur désigné : on ne peut même pas commencer le contrat. */
$v = Rappel::evaluate( 0, false, false );
$t( 'sans formateur : rappel rouge',        $v['level'],  'crit' );
$t( 'et la cause est nommée',               $v['reason'], Rappel::SANS_FORMATEUR );
$t( 'le libellé dit quoi faire',
    false !== strpos( $v['label'], 'désignez-le' ) ? 'oui' : 'non', 'oui' );

/* Formateur désigné, aucun contrat : c'est l'oubli visé. */
$v = Rappel::evaluate( 12, false, false );
$t( 'sans contrat : rappel rouge', $v['level'],  'crit' );
$t( 'cause nommée',                $v['reason'], Rappel::SANS_CONTRAT );

/* Contrat établi mais pas signé : la pièce existe, elle attend le formateur.
   Ce n'est plus un oubli du gestionnaire — d'où l'orange. */
$v = Rappel::evaluate( 12, true, false );
$t( 'contrat non signé : rappel orange', $v['level'],  'warn' );
$t( 'cause nommée',                      $v['reason'], Rappel::NON_SIGNE );

/* ── LA PROPRIÉTÉ ESSENTIELLE : le rappel s'éteint tout seul ──────────────── */
$v = Rappel::evaluate( 12, true, true );
$t( 'contrat établi et signé : plus de rappel', $v['reason'], Rappel::RIEN );
$t( 'et aucun niveau d’alerte',                 $v['level'],  '' );

/* Un formateur désigné dont le contrat existe et est signé ne doit RIEN
   produire, même en repassant plusieurs fois : le rappel n'a pas d'état à
   lui, il n'est qu'une lecture des données. */
$deux_lectures = array(
    Rappel::evaluate( 12, true, true )['reason'],
    Rappel::evaluate( 12, true, true )['reason'],
);
$t( 'deux lectures donnent le même silence', count( array_unique( $deux_lectures ) ), 1 );

/* Un identifiant de formateur invalide vaut « non désigné », jamais « réglé ». */
$t( 'formateur négatif = non désigné',  Rappel::evaluate( -3, true, true )['reason'], Rappel::SANS_FORMATEUR );
$t( 'formateur en texte vide = idem',   Rappel::evaluate( '', true, true )['reason'], Rappel::SANS_FORMATEUR );

/* ── Quand la convention cesse d'être concernée ───────────────────────────── */

/* Une convention non signée n'engage encore rien. */
$t( 'convention non signée : pas de rappel',
    Rappel::isRelevant( false, 4, '2026-09-01', '2026-08-14' ) ? 'oui' : 'non', 'non' );

/* Sans formation, il n'y a pas d'intervention à contractualiser. */
$t( 'sans formation : pas de rappel',
    Rappel::isRelevant( true, 0, '2026-09-01', '2026-08-14' ) ? 'oui' : 'non', 'non' );

/* Le cas courant : formation à venir. */
$t( 'formation à venir : rappel actif',
    Rappel::isRelevant( true, 4, '2026-09-01', '2026-08-14' ) ? 'oui' : 'non', 'oui' );

/* Sans date de fin, la convention est encore ouverte : on rappelle. */
$t( 'sans date de fin : rappel actif',
    Rappel::isRelevant( true, 4, '', '2026-08-14' ) ? 'oui' : 'non', 'oui' );

/* Terminée hier : le contrat manque toujours, et il manque encore plus. */
$t( 'formation terminée hier : rappel actif',
    Rappel::isRelevant( true, 4, '2026-08-13', '2026-08-14' ) ? 'oui' : 'non', 'oui' );

/* Exactement trente jours après la fin : encore dans la fenêtre. */
$t( 'trente jours après la fin : encore actif',
    Rappel::isRelevant( true, 4, '2026-07-15', '2026-08-14' ) ? 'oui' : 'non', 'oui' );

/* Trente-et-un jours : le rappel se tait. Une alerte qui ne s'éteint jamais
   devient un fond d'écran, et plus personne ne la lit. */
$t( 'trente-et-un jours après : le rappel se tait',
    Rappel::isRelevant( true, 4, '2026-07-14', '2026-08-14' ) ? 'oui' : 'non', 'non' );

/* Une date illisible ne fait pas disparaître le rappel : dans le doute, on
   prévient. */
$t( 'date de fin illisible : rappel maintenu',
    Rappel::isRelevant( true, 4, 'à définir', '2026-08-14' ) ? 'oui' : 'non', 'oui' );

printf( "%d contrôles, %d échec(s)\n", 18, $ko );
exit( 0 === $ko ? 0 : 1 );
