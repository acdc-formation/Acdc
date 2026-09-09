<?php
/**
 * Les indicateurs publiés sur le site commercial.
 *
 * Ces chiffres sortent de l'application : le SAAS les pousse vers
 * acdc-formation.com, où ils s'affichent en gros sur la page d'accueil et sur
 * chaque page formation. Relevé en direct avant correction : « 2 apprenants
 * formés », « 16 heures dispensées », et trois taux — 97 %, 95 %, 100 % —
 * saisis à la main sur les fiches formation, que rien n'étayait. L'indicateur 2
 * du référentiel Qualiopi demande des résultats publiés ET défendables.
 *
 * Trois règles sont vérifiées ici, chacune protège d'une erreur qui ne se voit
 * pas à l'œil nu — un chiffre faux reste plausible.
 */

define( 'ACDC_SUPPORT_TESTING', true );
require_once __DIR__ . '/../acdc-formation-saas-organisme-de-formation/src/Support/Indicators.php';

use ACDC\Support\Indicators;

$ko = 0;
$t  = function ( $label, $obtenu, $attendu ) use ( &$ko ) {
    if ( (string) $obtenu !== (string) $attendu ) {
        $ko++;
        printf( "ÉCHEC  %s : attendu « %s », obtenu « %s »\n", $label, $attendu, $obtenu );
    }
};

/* ── ON NE MOYENNE PAS DES MOYENNES ──────────────────────────────────────
   Une formation notée 5/5 par UNE personne et une autre notée 3/5 par
   CINQUANTE ne font pas 4/5. C'est l'erreur que faisait le calcul global :
   AVG() sur une colonne de taux, chaque formation pesant le même poids
   qu'elle ait été évaluée une fois ou cent. */
$petite = array_merge( Indicators::emptyCounters(), array( 'notes_sum' => 5.0,   'notes_count' => 1 ) );
$grande = array_merge( Indicators::emptyCounters(), array( 'notes_sum' => 150.0, 'notes_count' => 50 ) );

$reunis = Indicators::merge( array( $petite, $grande ) );
$taux   = Indicators::rates( $reunis );
$t( 'la formation la plus évaluée pèse le plus', $taux['taux_satisfaction'], 61 );

/* La moyenne naïve des deux taux (100 % et 60 %) donnerait 80 % : c'est ce
   qu'affichait le site. La vraie moyenne pondérée est 61 %. */
$t( 'et ce n’est pas la moyenne des taux', $taux['taux_satisfaction'] === 80 ? 'oui' : 'non', 'non' );

/* ── PRÉSENTIEL + DISTANCIEL, C'EST LA MÊME FORMATION ────────────────────
   Le SAAS crée une ligne par modalité. Les indicateurs étaient poussés ligne
   par ligne vers le même identifiant côté site : le présentiel écrivait, le
   distanciel écrasait. Et un apprenant présent dans les deux ne doit compter
   qu'une fois. */
$presentiel = array_merge( Indicators::emptyCounters(), array(
    'notes_sum' => 40.0, 'notes_count' => 10,
    'eval_passed' => 8, 'eval_total' => 10,
    'learner_ids' => array( 1, 2, 3 ),
) );
$distanciel = array_merge( Indicators::emptyCounters(), array(
    'notes_sum' => 18.0, 'notes_count' => 5,
    'eval_passed' => 3, 'eval_total' => 5,
    'learner_ids' => array( 3, 4 ),
) );

$formation = Indicators::merge( array( $presentiel, $distanciel ) );
$taux      = Indicators::rates( $formation );
$t( 'les deux modalités s’additionnent',        $formation['notes_count'], 15 );
$t( 'satisfaction de la formation entière',     $taux['taux_satisfaction'], 77 );
$t( 'réussite de la formation entière',         $taux['taux_reussite'], 73 );
/* L'apprenant n° 3 suit les deux modalités : il reste une personne. */
$t( 'un apprenant des deux modalités compte une fois', $taux['nb_apprenants'], 4 );

/* ── ZÉRO N'EST PAS « JE NE SAIS PAS » ───────────────────────────────────
   « 0 % de satisfaction » est une affirmation. Sans réponse, la mesure
   n'existe pas, et c'est l'appelant qui décide de se taire. */
$vide = Indicators::rates( Indicators::emptyCounters() );
$t( 'aucune réponse : pas de taux de satisfaction', null === $vide['taux_satisfaction'] ? 'null' : $vide['taux_satisfaction'], 'null' );
$t( 'aucune évaluation : pas de taux de réussite',  null === $vide['taux_reussite'] ? 'null' : $vide['taux_reussite'], 'null' );
$t( 'aucun apprenant : zéro, et c’est un fait',     $vide['nb_apprenants'], 0 );

/* ── CE QU'ON PUBLIE, ET D'OÙ ÇA VIENT ──────────────────────────────────── */
$p = Indicators::publish( 92, 97 );
$t( 'la mesure l’emporte sur la saisie', $p['value'], 92 );
$t( 'et elle se présente comme mesurée', $p['source'], 'mesuré' );

$p = Indicators::publish( null, 97 );
$t( 'sans mesure, la valeur saisie sert de repli', $p['value'], 97 );
$t( 'mais elle se présente comme déclarée',        $p['source'], 'déclaré' );

$p = Indicators::publish( null, 0 );
$t( 'ni mesure ni saisie : zéro, dit comme tel', $p['value'], 0 );
$t( 'et la source le reconnaît',                 $p['source'], 'aucune mesure' );

/* Une mesure de 0 % reste une mesure : un taux de réussite réellement nul ne
   doit pas être remplacé par la valeur flatteuse saisie à la main. */
$p = Indicators::publish( 0, 95 );
$t( 'une mesure nulle n’est pas une absence de mesure', $p['value'], 0 );
$t( 'et elle reste mesurée',                            $p['source'], 'mesuré' );

/* ── DES DONNÉES ABÎMÉES NE PRODUISENT PAS 140 % ─────────────────────────── */
$t( 'note hors échelle ramenée', Indicators::noteToPercent( 70.0, 10 ), 100 );
$t( 'note négative ramenée',     Indicators::noteToPercent( -10.0, 10 ), 0 );
$t( 'part supérieure au total',  Indicators::percent( 12, 10 ), 100 );
$t( 'division par zéro évitée',  null === Indicators::percent( 5, 0 ) ? 'null' : 'non', 'null' );

printf( "%d contrôles, %d échec(s)\n", 22, $ko );
exit( 0 === $ko ? 0 : 1 );
