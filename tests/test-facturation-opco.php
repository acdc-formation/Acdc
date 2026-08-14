<?php
/**
 * Facturation OPCO : qui reçoit la facture, et pour combien.
 *
 * « Souvent la facture doit être au nom de l'OPCO et on doit ajouter une
 * référence de la prise en charge, mais ce n'est pas systématique. » La règle
 * retenue, en trois cas :
 *
 *   prise en charge vide ou 0  → une facture au client ;
 *   prise en charge = total    → une facture à l'OPCO ;
 *   prise en charge < total    → deux factures liées, le reste CALCULÉ.
 *
 * Deux pièges que ces contrôles verrouillent :
 *
 * 1. Le reste à charge saisi à la main. Deux montants tapés séparément
 *    divergent le jour où le total change — c'est la famille de pannes déjà
 *    vue ici : deux listes de la même vérité. Le reste est une soustraction.
 *
 * 2. L'arithmétique en euros flottants. 1200,00 − 799,99 vaut
 *    400,00999999999995 en virgule flottante : la somme des deux factures ne
 *    ferait plus le total, et un centime manquerait à l'appel chez le
 *    comptable. Tout est calculé en centimes entiers.
 */

define( 'ACDC_SUPPORT_TESTING', true );
require_once __DIR__ . '/../acdc-formation-saas-organisme-de-formation/src/Support/FundingSplit.php';

use ACDC\Support\FundingSplit;

$ko = 0;
$t  = function ( $label, $obtenu, $attendu ) use ( &$ko ) {
    if ( (string) $obtenu !== (string) $attendu ) {
        $ko++;
        printf( "ÉCHEC  %s : attendu « %s », obtenu « %s »\n", $label, $attendu, $obtenu );
    }
};

/* ── Lecture des montants tels qu'on les tape ───────────────────────────── */
$t( 'montant français avec espace insécable', FundingSplit::toCents( "1\xc2\xa0200,50" ), 120050 );
$t( 'montant avec espace et symbole euro',    FundingSplit::toCents( '1 200,50 €' ),      120050 );
$t( 'montant à l’anglaise',                   FundingSplit::toCents( '1200.50' ),          120050 );
$t( 'montant vide vaut zéro',                 FundingSplit::toCents( '' ),                 0 );
$t( 'champ absent vaut zéro',                 FundingSplit::toCents( null ),               0 );
$t( 'texte illisible vaut zéro',              FundingSplit::toCents( 'à voir' ),           0 );
$t( 'nombre flottant',                        FundingSplit::toCents( 1200.5 ),             120050 );

/* ── Cas 1 : pas de prise en charge → une facture au client ─────────────── */
$p = FundingSplit::plan( '1200,00', '', true );
$t( 'sans montant : une seule facture',      count( $p['invoices'] ), 1 );
$t( 'sans montant : elle va au client',      $p['invoices'][0]['billed_to'], 'client' );
$t( 'sans montant : pour la totalité',       $p['invoices'][0]['amount_ht'], 1200 );
$t( 'sans montant : cas nommé',              $p['case'], FundingSplit::CLIENT_SEUL );

$p = FundingSplit::plan( '1200,00', '0', true );
$t( 'montant zéro : facture au client',      $p['invoices'][0]['billed_to'], 'client' );

/* Un montant saisi SANS financeur rattaché ne désigne personne : le client
   paie tout. Un montant sans destinataire n'est pas une prise en charge. */
$p = FundingSplit::plan( '1200,00', '800,00', false );
$t( 'montant sans financeur : facture au client', $p['invoices'][0]['billed_to'], 'client' );
$t( 'montant sans financeur : totalité',          $p['invoices'][0]['amount_ht'], 1200 );

/* ── Cas 2 : prise en charge totale → une facture à l'OPCO ──────────────── */
$p = FundingSplit::plan( '1200,00', '1200,00', true );
$t( 'prise en charge totale : une seule facture', count( $p['invoices'] ), 1 );
$t( 'prise en charge totale : au financeur',      $p['invoices'][0]['billed_to'], 'funder' );
$t( 'prise en charge totale : montant complet',   $p['invoices'][0]['amount_ht'], 1200 );
$t( 'prise en charge totale : rien au client',    $p['client_ht'], 0 );

/* Écrit autrement, c'est le même montant : « 1 200,00 € » = 1200.00. */
$p = FundingSplit::plan( 1200.00, "1\xc2\xa0200,00 €", true );
$t( 'même montant écrit autrement : toujours total', $p['case'], FundingSplit::FINANCEUR_SEUL );

/* ── Cas 3 : prise en charge partielle → deux factures liées ────────────── */
$p = FundingSplit::plan( '1200,00', '800,00', true );
$t( 'prise en charge partielle : deux factures', count( $p['invoices'] ), 2 );
$t( 'la première est pour le financeur',         $p['invoices'][0]['billed_to'], 'funder' );
$t( 'pour le montant accordé',                   $p['invoices'][0]['amount_ht'], 800 );
$t( 'la seconde est pour le client',             $p['invoices'][1]['billed_to'], 'client' );
$t( 'pour le reste, calculé',                    $p['invoices'][1]['amount_ht'], 400 );
$t( 'et le reste est nommé comme tel',           $p['invoices'][1]['share'], 'reste_a_charge' );

/* LE CAS QUI CASSE EN VIRGULE FLOTTANTE : 1200 − 799,99. */
$p = FundingSplit::plan( '1200,00', '799,99', true );
$t( 'reste au centime près',                     $p['invoices'][1]['amount_ht'], 400.01 );
$somme = $p['invoices'][0]['amount_ht'] + $p['invoices'][1]['amount_ht'];
$t( 'les deux factures font exactement le total', number_format( $somme, 2, '.', '' ), '1200.00' );

/* Un centime de prise en charge reste une prise en charge partielle. */
$p = FundingSplit::plan( '1200,00', '0,01', true );
$t( 'un centime : deux factures quand même',     count( $p['invoices'] ), 2 );
$t( 'le client paie le reste',                   $p['invoices'][1]['amount_ht'], 1199.99 );

/* ── Le refus : une prise en charge supérieure au total ─────────────────── */
$p = FundingSplit::plan( '1200,00', '1500,00', true );
$t( 'prise en charge trop élevée : refusée',     $p['ok'] ? 'acceptée' : 'refusée', 'refusée' );
$t( 'aucune facture n’est émise',                count( $p['invoices'] ), 0 );
$t( 'la cause est nommée',                       $p['error'], 'pec_superieure_au_total' );
$t( 'le message rappelle le total',
    false !== strpos( FundingSplit::errorMessage( $p['error'], $p['total_ht'] ), '1 200,00' ) ? 'oui' : 'non', 'oui' );

$p = FundingSplit::plan( '1200,00', '-50', true );
$t( 'montant négatif : refusé',                  $p['ok'] ? 'acceptée' : 'refusée', 'refusée' );

/* Un dépassement d'UN CENTIME est un dépassement : pas d'arrondi complaisant. */
$p = FundingSplit::plan( '1200,00', '1200,01', true );
$t( 'un centime de trop est refusé',             $p['error'], 'pec_superieure_au_total' );

/* ── Le total lui-même peut être nul (prestation offerte) ───────────────── */
$p = FundingSplit::plan( '0', '', true );
$t( 'total nul : une facture au client',         $p['invoices'][0]['billed_to'], 'client' );
$t( 'total nul : montant nul',                   $p['invoices'][0]['amount_ht'], 0 );

printf( "%d contrôles, %d échec(s)\n", 34, $ko );
exit( 0 === $ko ? 0 : 1 );
