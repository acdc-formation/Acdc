<?php
/**
 * Le BPF des prestations extérieures : deux entités, deux déclarations.
 *
 * L'organisme géré par l'application a son numéro de déclaration d'activité.
 * Le formateur qui intervient pour le compte d'AUTRES organismes a le sien.
 * Deux personnes morales, deux BPF : mélanger leurs chiffres ferait déclarer à
 * l'une l'activité de l'autre — et, pour les stagiaires venus d'un autre
 * organisme, les ferait compter deux fois au niveau national.
 *
 * TROIS PIÈGES QUE CE TEST SURVEILLE.
 *
 * Les DEUX UNITÉS D'HEURES. Une journée de sept heures devant douze personnes
 * fait sept heures au cadre E — celles qu'on a animées — et quatre-vingt-quatre
 * au cadre F — celles qu'on a suivies. Les confondre est l'erreur la plus
 * courante du BPF, et elle ne se voit pas : les deux nombres sont plausibles.
 *
 * Le CADRE G N'EST PAS UN AJOUT. C'est un sous-ensemble du cadre F, destiné à
 * retrancher les stagiaires que le donneur d'ordre déclare aussi. L'additionner
 * doublerait ce qu'il sert précisément à dédoubler.
 *
 * RIEN NE SE PERD. Une origine de financement ou une catégorie de public non
 * reconnue tombe dans « autres », jamais à la poubelle : un total qui ne
 * retombe pas sur ses pieds est un BPF refusé.
 */

define( 'ACDC_SUPPORT_TESTING', true );
require_once __DIR__ . '/../acdc-formation-saas-organisme-de-formation/src/Support/ExternalBpf.php';

use ACDC\Support\ExternalBpf;

$ko = 0;
$t  = function ( $label, $obtenu, $attendu ) use ( &$ko ) {
    if ( (string) $obtenu !== (string) $attendu ) {
        $ko++;
        printf( "ÉCHEC  %s : attendu « %s », obtenu « %s »\n", $label, $attendu, $obtenu );
    }
};

/* Des prestations réalistes, calquées sur celles de la recette. */
$fiches = array(
    array( 'start_date' => '2025-07-25', 'nb_stagiaires' => 2,  'heures_par_stagiaire' => 7,    'heures_total' => 14,    'ca_ht' => 320,     'type_public' => 'Indépendant',      'financement' => 'Organisme de formation' ),
    array( 'start_date' => '2025-10-13', 'nb_stagiaires' => 9,  'heures_par_stagiaire' => 73.5, 'heures_total' => 661.5, 'ca_ht' => 2572.50, 'type_public' => 'Indépendant',      'financement' => 'Organisme de formation' ),
    array( 'start_date' => '2025-06-17', 'nb_stagiaires' => 1,  'heures_par_stagiaire' => 35,   'heures_total' => 35,    'ca_ht' => 1200,    'type_public' => 'Salarié',          'financement' => 'Entreprise' ),
    array( 'start_date' => '2026-05-05', 'nb_stagiaires' => 11, 'heures_par_stagiaire' => 3.5,  'heures_total' => 38.5,  'ca_ht' => 157.50,  'type_public' => 'CFA',              'financement' => 'Organisme de formation' ),
);

/* ── L'EXERCICE : UNE PRESTATION APPARTIENT À CELUI OÙ ELLE COMMENCE ─────── */
$e2025 = ExternalBpf::forPeriod( $fiches, '2025-01-01', '2025-12-31' );
$t( 'exercice 2025', count( $e2025 ), 3 );
$t( 'exercice 2026', count( ExternalBpf::forPeriod( $fiches, '2026-01-01', '2026-12-31' ) ), 1 );
$t( 'exercice vide', count( ExternalBpf::forPeriod( $fiches, '2020-01-01', '2020-12-31' ) ), 0 );
/* Une prestation à cheval reste sur son exercice de début, entière. */
$t(
    'prestation à cheval : comptée une seule fois',
    count( ExternalBpf::forPeriod(
        array( array( 'start_date' => '2025-10-23', 'end_date' => '2026-06-18', 'nb_stagiaires' => 8 ) ),
        '2025-01-01',
        '2025-12-31'
    ) ),
    1
);

/* ── CADRE C — LES PRODUITS PAR ORIGINE ─────────────────────────────────── */
$c = ExternalBpf::frameC( $e2025 );
$t( 'C10 sous-traitance reçue', $c['c10'], 2892.5 );
$t( 'C1 vente à une entreprise', $c['c1'], 1200 );
$t( 'C total',                   $c['total'], 4092.5 );
/* Le total est bien la somme des lignes : c'est ce que l'administration vérifie
   en premier. */
$t(
    'aucun produit ne se perd',
    round( $c['c1'] + $c['c7'] + $c['c9'] + $c['c10'] + $c['c11'], 2 ),
    $c['total']
);
$t(
    'financement inconnu : en autres produits, pas perdu',
    ExternalBpf::frameC( array( array( 'ca_ht' => 500, 'financement' => 'Mécénat' ) ) )['c11'],
    500
);

/* ── CADRE E — LES HEURES ANIMÉES ───────────────────────────────────────── */
$e = ExternalBpf::frameE( $e2025 );
$t( 'E : une seule personne',  $e['nb_internes'],     1 );
$t( 'E : heures dispensées',   $e['heures_internes'], 115.5 );
$t( 'E : aucun formateur externe', $e['nb_externes'], 0 );
/* Sans prestation, personne : déclarer un formateur qui n'a rien animé
   fausserait le ratio heures/personne que l'administration lit. */
$t( 'E : exercice vide', ExternalBpf::frameE( array() )['nb_internes'], 0 );

/* ── CADRE F — LES STAGIAIRES ET LES HEURES SUIVIES ─────────────────────── */
$f = ExternalBpf::frameF( $e2025 );
$t( 'F : stagiaires',    $f['total'],        12 );
$t( 'F : indépendants',  $f['independants'], 11 );
$t( 'F : salariés',      $f['salaries'],     1 );
$t( 'F : heures suivies', $f['heures'],      710.5 );
/* Un stagiaire de CFA est un apprenti : c'est la case du Cerfa qui l'attend. */
$t( 'F : CFA classé en apprentis', ExternalBpf::frameF( ExternalBpf::forPeriod( $fiches, '2026-01-01', '2026-12-31' ) )['apprentis'], 11 );
$t(
    'F : catégorie inconnue en autres',
    ExternalBpf::frameF( array( array( 'nb_stagiaires' => 3, 'type_public' => 'Bénévole' ) ) )['autres'],
    3
);

/* LES DEUX UNITÉS NE SE CONFONDENT JAMAIS : 115,5 animées, 710,5 suivies. */
$t(
    'cadres E et F portent bien deux nombres différents',
    $e['heures_internes'] === $f['heures'] ? 'confondus' : 'distincts',
    'distincts'
);

/* ── CADRE G — UN SOUS-ENSEMBLE, JAMAIS UN AJOUT ────────────────────────── */
$g = ExternalBpf::frameG( $e2025 );
$t( 'G : stagiaires confiés', $g['nb_stagiaires'], 11 );
$t( 'G : heures',             $g['heures'],        675.5 );
$t(
    'G ne dépasse jamais F',
    $g['nb_stagiaires'] <= $f['total'] ? 'inclus' : 'dépasse',
    'inclus'
);
/* Sans donneur d'ordre, le cadre G est vide — et non égal au cadre F. */
$t(
    'G vide quand rien n’est sous-traité',
    ExternalBpf::frameG( array( array( 'nb_stagiaires' => 5, 'financement' => 'Entreprise' ) ) )['nb_stagiaires'],
    0
);

/* ── LES SAISIES HUMAINES ───────────────────────────────────────────────── */
$t( 'accents et casse',  ExternalBpf::frameF( array( array( 'nb_stagiaires' => 2, 'type_public' => 'SALARIE' ) ) )['salaries'], 2 );
$t( 'virgule décimale',  ExternalBpf::frameE( array( array( 'heures_par_stagiaire' => '7,5' ) ) )['heures_internes'], 7.5 );
$t(
    'total absent : déduit du produit',
    ExternalBpf::attendedHours( array( 'nb_stagiaires' => 4, 'heures_par_stagiaire' => 7 ) ),
    28
);
$t(
    'total constaté : conservé tel quel',
    ExternalBpf::attendedHours( array( 'nb_stagiaires' => 10, 'heures_par_stagiaire' => 7, 'heures_total' => 52 ) ),
    52
);

if ( $ko ) {
    printf( "%d échec(s)\n", $ko );
    exit( 1 );
}
echo "BPF des prestations : 26 cas vérifiés, les deux unités d'heures et le cadre G restent à leur place.\n";
exit( 0 );
