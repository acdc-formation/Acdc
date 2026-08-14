<?php
/**
 * Suivi commercial : les pastilles comptent les étapes franchies.
 *
 * Le cas rapporté en recette : un prospect dont le recueil est parti, la
 * proposition envoyée et le devis émis n'apparaissait qu'à « Proposition
 * envoyée 1 ». « Recueil envoyé 0 », « Devis envoyé 0 » — alors que les deux
 * avaient bien eu lieu. Les pastilles comptaient le STATUT COURANT, qui ne
 * retient qu'une étape.
 *
 * Deux règles se complètent ici :
 *   — un jalon est franchi quand un fait l'établit ;
 *   — le statut courant est toujours un jalon, même sans fait derrière lui,
 *     sinon la ligne afficherait « Proposition envoyée » dans sa colonne
 *     Statut sans être joignable par la pastille du même nom.
 *
 * Et une exception : « Perdu » ferme le dossier.
 */

define( 'ACDC_SUPPORT_TESTING', true );
require_once __DIR__ . '/../acdc-formation-saas-organisme-de-formation/src/Support/ProspectMilestones.php';

use ACDC\Support\ProspectMilestones;

/* L'ordre du parcours, tel que le plugin le définit. */
$ordre = array(
    'À traiter' => 1, 'Premier contact' => 2, 'À relancer' => 3,
    'Rendez-vous planifié' => 4, 'Recueil envoyé' => 5, 'Proposition envoyée' => 6,
    'Devis envoyé' => 7, 'Converti' => 8, 'Perdu' => 9,
);
$libelles = array_keys( $ordre );

$ko = 0;
$t  = function ( $label, $obtenu, $attendu ) use ( &$ko ) {
    if ( (string) $obtenu !== (string) $attendu ) {
        $ko++;
        printf( "ÉCHEC  %s : attendu « %s », obtenu « %s »\n", $label, $attendu, $obtenu );
    }
};

/* ── LE CAS RÉEL : Skill Conseil ───────────────────────────────────────────
   Recueil parti, proposition envoyée, devis émis ; statut resté à
   « Proposition envoyée ». Les trois pastilles doivent compter. */
$skill = ProspectMilestones::forProspect(
    array( 'Recueil envoyé', 'Proposition envoyée', 'Devis envoyé' ),
    'Proposition envoyée',
    $ordre
);
$t( 'les trois étapes sont franchies', implode( ' + ', $skill ), 'Recueil envoyé + Proposition envoyée + Devis envoyé' );

$counts = ProspectMilestones::counts( array( $skill ), $libelles );
$t( 'Recueil envoyé compte 1',      $counts['Recueil envoyé'], 1 );
$t( 'Proposition envoyée compte 1', $counts['Proposition envoyée'], 1 );
$t( 'Devis envoyé compte 1',        $counts['Devis envoyé'], 1 );

/* Et surtout : les étapes NON franchies restent à zéro. C'est ce qui
   distingue « jalons établis par un fait » de « toutes les étapes de rang
   inférieur » — un prospect passé à l'étape 6 n'a pas forcément eu de
   rendez-vous, ni de relance. */
$t( 'À traiter reste à 0',            $counts['À traiter'], 0 );
$t( 'Premier contact reste à 0',      $counts['Premier contact'], 0 );
$t( 'À relancer reste à 0',           $counts['À relancer'], 0 );
$t( 'Rendez-vous planifié reste à 0', $counts['Rendez-vous planifié'], 0 );
$t( 'Converti reste à 0',             $counts['Converti'], 0 );

/* ── Le statut courant est toujours un jalon ──────────────────────────────
   Un prospect que l'on vient de créer n'a aucun fait : il compte quand même. */
$neuf = ProspectMilestones::forProspect( array(), 'À traiter', $ordre );
$t( 'un prospect neuf compte à « À traiter »', implode( '+', $neuf ), 'À traiter' );

/* Statut posé à la main sans objet derrière : la ligne l'affiche, la pastille
   doit donc la trouver. */
$manuel = ProspectMilestones::forProspect( array(), 'Premier contact', $ordre );
$t( 'un statut saisi à la main compte', implode( '+', $manuel ), 'Premier contact' );

/* Un statut vide vaut « À traiter » — jamais une pastille sans nom. */
$vide = ProspectMilestones::forProspect( array(), '', $ordre );
$t( 'un statut vide retombe sur À traiter', implode( '+', $vide ), 'À traiter' );

/* ── Le statut n'est jamais compté deux fois ──────────────────────────────── */
$double = ProspectMilestones::forProspect( array( 'Devis envoyé' ), 'Devis envoyé', $ordre );
$t( 'un jalon déjà établi n’est pas doublé', count( $double ), 1 );
$counts_d = ProspectMilestones::counts( array( $double ), $libelles );
$t( 'et il ne compte qu’une fois', $counts_d['Devis envoyé'], 1 );

/* ── Les jalons sortent dans l'ordre du parcours ──────────────────────────── */
$desordre = ProspectMilestones::forProspect(
    array( 'Devis envoyé', 'Rendez-vous planifié', 'Recueil envoyé' ),
    'Devis envoyé',
    $ordre
);
$t( 'les jalons suivent l’ordre du parcours',
    implode( ' → ', $desordre ), 'Rendez-vous planifié → Recueil envoyé → Devis envoyé' );

/* ── « Perdu » ferme le dossier ───────────────────────────────────────────── */
$perdu = ProspectMilestones::forProspect(
    array( 'Recueil envoyé', 'Proposition envoyée', 'Devis envoyé' ),
    'Perdu',
    $ordre
);
$t( 'un prospect perdu ne compte que comme perdu', implode( '+', $perdu ), 'Perdu' );

/* Un devis signé archivé sur un dossier perdu ne ressuscite pas l'affaire. */
$counts_p = ProspectMilestones::counts( array( $perdu ), $libelles );
$t( 'et il ne gonfle plus les étapes précédentes', $counts_p['Devis envoyé'], 0 );
$t( 'Perdu compte 1',                              $counts_p['Perdu'], 1 );

/* ── La somme des pastilles dépasse le nombre de prospects : c'est voulu ──── */
$tous = array(
    ProspectMilestones::forProspect( array( 'Recueil envoyé', 'Proposition envoyée', 'Devis envoyé' ), 'Proposition envoyée', $ordre ),
    ProspectMilestones::forProspect( array(), 'À traiter', $ordre ),
);
$counts_t = ProspectMilestones::counts( $tous, $libelles );
$somme    = array_sum( $counts_t );
$t( 'deux prospects, quatre coches', $somme, 4 );
$t( 'dont le prospect neuf',         $counts_t['À traiter'], 1 );

/* ── Un fait « Perdu » venu d'ailleurs ne clôt pas un dossier vivant ──────── */
$vivant = ProspectMilestones::forProspect( array( 'Perdu', 'Devis envoyé' ), 'Devis envoyé', $ordre );
$t( 'seul le statut peut déclarer perdu', implode( '+', $vivant ), 'Devis envoyé' );

/* ── Les libellés attendus existent même à zéro ───────────────────────────── */
$counts_vides = ProspectMilestones::counts( array(), $libelles );
$t( 'toutes les pastilles existent', count( $counts_vides ), 9 );
$t( 'et valent zéro',                array_sum( $counts_vides ), 0 );

printf( "%d contrôles, %d échec(s)\n", 22, $ko );
exit( 0 === $ko ? 0 : 1 );
