<?php
/**
 * ACDC 3.25.324 — Le bas d'un formulaire n'est pas une colonne d'actions.
 *
 * Le script qui « iconise » les zones d'action traitait « .acdc-form-actions »
 * comme une colonne de tableau : les boutons « Annuler » et « Valider » de tous
 * les formulaires du plugin étaient remplacés par une croix et une coche.
 *
 * Sur la convention, cela donnait deux petites icônes au bas d'un formulaire de
 * mille lignes — pendant que la barre du HAUT de la même page gardait ses mots.
 * Deux traitements pour les deux mêmes actions, sur un seul écran.
 *
 * LA DISTINCTION QU'ON TIENT ICI. L'iconisation a un sens dans une colonne
 * d'actions : la place est comptée, l'utilisateur balaie des lignes, et le
 * contexte donne le sens. Elle n'en a aucun sur le geste qui ENGAGE le
 * formulaire — une coche ne dit pas ce qu'elle valide, une croix ne dit pas ce
 * qu'elle annule, et c'est le dernier endroit où l'on veut hésiter.
 *
 * Les colonnes d'actions restent iconisées. C'est aussi ce que ce balayage
 * vérifie : la correction ne doit pas emporter avec elle ce qui fonctionnait.
 *
 * Éprouvé en sabotant : chaque règle échoue quand on défait la correction.
 */

$racine  = dirname( __DIR__ ) . '/acdc-formation-saas-organisme-de-formation';
$fichier = $racine . '/assets/js/admin.js';

if ( ! is_readable( $fichier ) ) {
    fwrite( STDERR, "Fichier introuvable : $fichier\n" );
    exit( 1 );
}

$src = (string) file_get_contents( $fichier );

/* Le JS sans ses commentaires : la phrase qui EXPLIQUE la correction cite la
   classe qu'on vient d'écarter, et la trouverait à sa place. On a déjà pris un
   commentaire pour du code trois fois cette semaine. */
$sans = preg_replace( '#/\*.*?\*/#s', '', $src );
$sans = preg_replace( '#^\s*//.*$#m', '', (string) $sans );

$echecs = array();
$verifs = 0;
$exiger = function ( $condition, $message ) use ( &$echecs, &$verifs ) {
    $verifs++;
    if ( ! $condition ) {
        $echecs[] = $message;
    }
};

/* La liste des zones iconisées, telle qu'elle est réellement exécutée. */
$liste = '';
if ( preg_match( '/var selectors = \[(.*?)\];/s', (string) $sans, $m ) ) {
    $liste = $m[1];
}
$exiger( '' !== $liste, 'La liste des zones à iconiser est introuvable : le script a été réécrit, ce balayage ne surveille plus rien.' );

/* 1. LA RÈGLE — les zones de formulaire sont hors de la liste. */
$exiger(
    false === strpos( $liste, 'acdc-form-actions' ),
    'Les zones « .acdc-form-actions » sont de nouveau iconisées : « Annuler » et « Valider » redeviennent une croix et une coche au bas de chaque formulaire, alors que la barre du haut garde ses mots.'
);

/* 2. CE QUI DOIT RESTER — les colonnes d'actions gardent leurs icônes.
      Une correction qui emporte ce qui fonctionnait n'est pas une correction. */
foreach ( array( 'acdc-actions-cell', 'acdc-record-actions', 'acdc-page-actions', 'acdc-toolbar-actions', 'acdc-card-actions' ) as $zone ) {
    $exiger(
        false !== strpos( $liste, $zone ),
        sprintf( 'La zone « .%s » n’est plus iconisée : les colonnes d’actions perdent leur mise en forme compacte, ce qui n’était pas demandé.', $zone )
    );
}

/* 3. L'échappatoire par attribut reste disponible : un écran qui veut sortir de
      l'iconisation doit pouvoir le dire sans modifier ce script. */
$exiger(
    false !== strpos( (string) $sans, 'data-acdc-no-iconize' ),
    'L’échappatoire « data-acdc-no-iconize » a disparu : un écran ne peut plus refuser l’iconisation sans toucher au script commun.'
);

if ( $echecs ) {
    foreach ( $echecs as $e ) {
        fwrite( STDERR, "ÉCHEC — $e\n" );
    }
    fwrite( STDERR, sprintf( "\n%d/%d vérifications passées.\n", $verifs - count( $echecs ), $verifs ) );
    exit( 1 );
}

echo sprintf( "Actions de formulaire : les mots restent des mots — %d/%d vertes.\n", $verifs, $verifs );
exit( 0 );
