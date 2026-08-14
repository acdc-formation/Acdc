<?php
/**
 * Heures, taux horaire, montant : le calcul doit aller dans les deux sens.
 *
 * Relevé en recette : « pour les tarifs horaires, je veux que quand on met
 * seulement le montant HT, ça calcule le tarif horaire — que ça fonctionne
 * dans les deux sens. »
 *
 * Le calcul n'allait que dans un sens, à l'écran comme au serveur : heures ×
 * taux → montant. Une mission négociée au forfait — « 800 € pour deux jours »,
 * le cas le plus courant — s'enregistrait donc avec un taux horaire à ZÉRO, et
 * c'est ce que le contrat de sous-traitance imprimait.
 *
 * La règle testée ici est celle qu'appliquent l'écran ET le serveur : le
 * montant saisi fait foi, le taux s'en déduit. Ce n'est pas un détail
 * d'arrondi — 14 h à 57,14 €/H font 799,96 €, pas 800. C'est la somme due qui
 * est contractuelle ; le taux n'en est que la lecture ramenée à l'heure.
 */

define( 'ACDC_SUPPORT_TESTING', true );
require_once __DIR__ . '/../acdc-formation-saas-organisme-de-formation/src/Support/MissionAmount.php';

use ACDC\Support\MissionAmount;

$ko = 0;
$t  = function ( $label, $obtenu, $attendu ) use ( &$ko ) {
    if ( (string) $obtenu !== (string) $attendu ) {
        $ko++;
        printf( "ÉCHEC  %s : attendu « %s », obtenu « %s »\n", $label, $attendu, $obtenu );
    }
};

/* ── LE CAS RÉEL : le forfait de deux jours ───────────────────────────────── */
$m = MissionAmount::reconcile( 14, '', 800 );
$t( 'forfait : le montant est conservé', $m['amount'], 800 );
$t( 'et le taux horaire se déduit',      $m['rate'],   57.14 );
$t( 'les heures ne bougent pas',         $m['hours'],  14 );

/* Sans cette règle, le taux partait à zéro sur le contrat. */
$t( 'le taux n’est jamais nul quand le forfait est connu',
    $m['rate'] > 0 ? 'oui' : 'non', 'oui' );

/* ── Le sens historique fonctionne toujours ───────────────────────────────── */
$m = MissionAmount::reconcile( 14, 57.15, '' );
$t( 'heures × taux : le montant se calcule', $m['amount'], 800.1 );
$t( 'et le taux est conservé tel quel',      $m['rate'],   57.15 );

/* ── LE MONTANT FAIT FOI ──────────────────────────────────────────────────
   Quand les trois sont fournis et qu'ils ne tombent pas juste, c'est la somme
   due qui gagne : elle est contractuelle, le taux ne l'est pas. */
$m = MissionAmount::reconcile( 14, 57.15, 800 );
$t( 'les trois fournis : le montant est intact', $m['amount'], 800 );
$t( 'et le taux est ramené à la réalité',        $m['rate'],   57.14 );

/* La règle est idempotente : relire une mission déjà cohérente ne la déforme
   pas — sinon chaque enregistrement ferait dériver le taux. */
$m1 = MissionAmount::reconcile( 10, 0, 500 );
$m2 = MissionAmount::reconcile( $m1['hours'], $m1['rate'], $m1['amount'] );
$t( 'deuxième passage : montant inchangé', $m2['amount'], $m1['amount'] );
$t( 'deuxième passage : taux inchangé',    $m2['rate'],   $m1['rate'] );

/* ── Ce qu'on tape vraiment ───────────────────────────────────────────────── */
$m = MissionAmount::reconcile( '14', '', '800,00 €' );
$t( 'montant écrit à la française', $m['amount'], 800 );
$m = MissionAmount::reconcile( '14', '', "1\xc2\xa0200,50" );
$t( 'espace insécable des milliers', $m['amount'], 1200.5 );

/* ── Les cas dégradés ─────────────────────────────────────────────────────── */

/* Aucune heure : un montant reste un montant. On ne l'efface pas sous
   prétexte qu'on ne sait pas le ramener à l'heure. */
$m = MissionAmount::reconcile( 0, 0, 800 );
$t( 'montant sans heures : conservé', $m['amount'], 800 );
$t( 'et le taux reste à zéro',        $m['rate'],   0 );

/* Rien du tout : rien d'inventé. */
$m = MissionAmount::reconcile( '', '', '' );
$t( 'formulaire vide : montant nul', $m['amount'], 0 );
$t( 'formulaire vide : taux nul',    $m['rate'],   0 );

/* Des valeurs négatives ne produisent pas une mission qui rembourse. */
$m = MissionAmount::reconcile( -14, -50, -800 );
$t( 'heures négatives ramenées à zéro',  $m['hours'],  0 );
$t( 'montant négatif ramené à zéro',     $m['amount'], 0 );

/* Un demi-jour : les heures décimales tombent juste. */
$m = MissionAmount::reconcile( 3.5, '', 245 );
$t( 'heures décimales : taux exact', $m['rate'], 70 );

printf( "%d contrôles, %d échec(s)\n", 20, $ko );
exit( 0 === $ko ? 0 : 1 );
