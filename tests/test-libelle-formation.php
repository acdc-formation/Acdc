<?php
/**
 * Deux formations du même nom ne se présentent jamais pareil.
 *
 * Relevé en recette, sur la modale de création de quiz : « il y a aucun moyen
 * de différencier deux formations avec le même nom ». Une même formation existe
 * en présentiel ET en distanciel — deux fiches, deux tarifs, deux programmes,
 * le même intitulé. Un écran qui n'écrit que l'intitulé propose deux fois la
 * même ligne, et celui qui choisit tire à pile ou face.
 *
 * Ce n'est pas une gêne d'ergonomie : rattacher un quiz, une séance ou une
 * convention à la mauvaise fiche produit une preuve Qualiopi qui désigne une
 * modalité que l'apprenant n'a pas suivie.
 *
 * Le piège que ce test surveille en premier n'est pas l'absence de modalité,
 * c'est le REPLI SUR LE MAUVAIS TITRE. Une ligne de séance porte deux noms : le
 * sien (« Groupe A — mars ») et celui de sa formation. Lire le premier dans une
 * colonne « Formation » ne laisse aucune trace visible — la case est remplie,
 * la valeur est plausible, elle est fausse.
 */

define( 'ACDC_SUPPORT_TESTING', true );
require_once __DIR__ . '/../acdc-formation-saas-organisme-de-formation/src/Support/FormationLabel.php';

use ACDC\Support\FormationLabel;

$ko = 0;
$t  = function ( $label, $obtenu, $attendu ) use ( &$ko ) {
    if ( (string) $obtenu !== (string) $attendu ) {
        $ko++;
        printf( "ÉCHEC  %s : attendu « %s », obtenu « %s »\n", $label, $attendu, $obtenu );
    }
};

/* ── LE CAS DE DAVID : DEUX FICHES, UN SEUL NOM ─────────────────────────── */
$t(
    'présentiel',
    FormationLabel::compose( 'Créer et animer une page Facebook', 'Présentiel' ),
    'Créer et animer une page Facebook (Présentiel)'
);
$t(
    'distanciel',
    FormationLabel::compose( 'Créer et animer une page Facebook', 'Distanciel' ),
    'Créer et animer une page Facebook (Distanciel)'
);
$t(
    'les deux libellés diffèrent',
    FormationLabel::compose( 'Même titre', 'Présentiel' ) === FormationLabel::compose( 'Même titre', 'Distanciel' ) ? 'identiques' : 'distincts',
    'distincts'
);

/* ── LES SEGMENTS VIDES NE LAISSENT PAS DE DÉCOR ────────────────────────── */
$t( 'sans modalité',        FormationLabel::compose( 'Bureautique' ), 'Bureautique' );
$t( 'modalité vide',        FormationLabel::compose( 'Bureautique', '   ' ), 'Bureautique' );
$t( 'code présent',         FormationLabel::compose( 'Bureautique', 'Présentiel', 'BUR-01' ), 'BUR-01 | Bureautique (Présentiel)' );
$t( 'code seul',            FormationLabel::compose( 'Bureautique', '', 'BUR-01' ), 'BUR-01 | Bureautique' );
$t( 'titre absent',         FormationLabel::compose( '', 'Distanciel' ), '(Distanciel)' );
$t( 'tout absent',          FormationLabel::compose( '', '', '' ), '' );

/* ── ON N'ÉCRIT JAMAIS DEUX FOIS LA MÊME CHOSE ──────────────────────────── */
$t(
    'la modalité déjà dans le titre',
    FormationLabel::compose( 'Bureautique en distanciel', 'Distanciel' ),
    'Bureautique en distanciel'
);
$t(
    'le code déjà en tête du titre',
    FormationLabel::compose( 'BUR-01 Bureautique', 'Présentiel', 'BUR-01' ),
    'BUR-01 Bureautique (Présentiel)'
);

/* ── LA MODALITÉ SE LIT PAREIL, QUELLE QUE SOIT SA SAISIE ───────────────── */
/* Le champ est libre en base : un import Excel crie, une saisie manuelle non.
   Deux orthographes de la même modalité donneraient l'illusion de deux
   modalités différentes — exactement la confusion qu'on cherche à lever. */
$t( 'import en capitales', FormationLabel::modality( 'PRESENTIEL' ), 'Presentiel' );
$t( 'saisie minuscule',    FormationLabel::modality( 'présentiel' ), 'Présentiel' );
$t( 'déjà correcte',       FormationLabel::modality( 'Distanciel' ), 'Distanciel' );
$t( 'espaces parasites',   FormationLabel::modality( '  Distanciel  ' ), 'Distanciel' );
$t( 'un sigle reste un sigle', FormationLabel::modality( 'FOAD' ), 'FOAD' );
$t( 'modalité vide',       FormationLabel::modality( '' ), '' );

/* ── LA LIGNE D'UNE FORMATION ───────────────────────────────────────────── */
$formation = (object) array(
    'id'       => 12,
    'title'    => 'Management d’équipe',
    'modality' => 'Présentiel',
    'code'     => '',
);
$t( 'depuis une fiche', FormationLabel::fromRow( $formation ), 'Management d’équipe (Présentiel)' );
$t( 'depuis un tableau associatif', FormationLabel::fromRow( (array) $formation ), 'Management d’équipe (Présentiel)' );
$t( 'ligne absente',    FormationLabel::fromRow( null ), '' );

/* ── LE PIÈGE : LA LIGNE D'UNE SÉANCE PORTE DEUX TITRES ─────────────────── */
$seance = (object) array(
    'id'                 => 88,
    'title'              => 'Groupe A — mars',      // le titre de la SÉANCE
    'formation_title'    => 'Management d’équipe',  // celui de la FORMATION
    'formation_modality' => 'Distanciel',
);
$t(
    'la colonne préfixée prime toujours',
    FormationLabel::fromRow( $seance ),
    'Management d’équipe (Distanciel)'
);
$t(
    'lecture stricte par jointure',
    FormationLabel::fromJoinedRow( $seance ),
    'Management d’équipe (Distanciel)'
);

/* La lecture stricte refuse de se rabattre sur le titre de la séance : mieux
   vaut une case vide, que l'appelant remplira d'un tiret, qu'un nom plausible
   et faux dans une colonne « Formation ». */
$sans_jointure = (object) array( 'id' => 88, 'title' => 'Groupe A — mars' );
$t( 'jointure vide : rien', FormationLabel::fromJoinedRow( $sans_jointure ), '' );
$t( 'jointure absente',     FormationLabel::fromJoinedRow( null ), '' );

/* ── LE REPÈRE DEVANT LE NOM ────────────────────────────────────────────── */
$t( 'repère et modalité',   FormationLabel::prefixed( '3.1', 'Bureautique', 'Distanciel' ), '3.1 Bureautique (Distanciel)' );
$t( 'repère sans modalité', FormationLabel::prefixed( '3', 'Bureautique' ), '3 Bureautique' );
$t( 'repère vide',          FormationLabel::prefixed( '', 'Bureautique', 'Présentiel' ), 'Bureautique (Présentiel)' );
$t( 'repère déjà en tête',  FormationLabel::prefixed( '3.1', '3.1 Bureautique', 'Distanciel' ), '3.1 Bureautique (Distanciel)' );
$t( 'repère seul',          FormationLabel::prefixed( '3.1', '' ), '3.1' );

/* ── LES ESPACES D'UN COPIER-COLLER NE CHANGENT RIEN ────────────────────── */
$t(
    'titre sur deux lignes',
    FormationLabel::compose( "Créer et animer\n  une page Facebook", 'Présentiel' ),
    'Créer et animer une page Facebook (Présentiel)'
);

if ( $ko ) {
    printf( "%d échec(s)\n", $ko );
    exit( 1 );
}
echo "Libellé des formations : 30 cas vérifiés, deux fiches du même nom ne se confondent jamais.\n";
exit( 0 );
