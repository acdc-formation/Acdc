<?php
/**
 * La séance née d'une convention, et ce qu'elle autorise.
 *
 * Relevé en recette : la convention crée les séances — c'est ce qui a supprimé
 * une double saisie — mais elle ne permet pas toujours de désigner le
 * formateur, qui se décide souvent en dernier. La séance naissait pourtant
 * « Planifiée », complète aux yeux de l'application, et le moteur expédiait la
 * veille à 17 h une convocation annonçant une journée dont le formateur
 * n'existait pas.
 *
 * La règle testée ici est celle qu'appliquent la convention, le moteur, l'écran
 * de la séance et le bouton d'envoi manuel :
 *
 *   UNE SÉANCE SANS FORMATEUR EST UN BROUILLON,
 *   UN BROUILLON RETIENT LA CONVOCATION, PAS L'EXTRANET,
 *   ET LA QUESTION SE REFERME DÈS QUE LA RÉPONSE ARRIVE.
 */

define( 'ACDC_SUPPORT_TESTING', true );
require_once __DIR__ . '/../acdc-formation-saas-organisme-de-formation/src/Support/SessionDraftGate.php';

use ACDC\Support\SessionDraftGate;

$ko = 0;
$t  = function ( $label, $obtenu, $attendu ) use ( &$ko ) {
    if ( (string) $obtenu !== (string) $attendu ) {
        $ko++;
        printf( "ÉCHEC  %s : attendu « %s », obtenu « %s »\n", $label, $attendu, $obtenu );
    }
};

$seance = function ( $is_draft, $status, $trainer_id = 0 ) {
    return (object) array(
        'id'         => 42,
        'is_draft'   => $is_draft,
        'status'     => $status,
        'trainer_id' => $trainer_id,
    );
};

/* ── LA NAISSANCE ────────────────────────────────────────────────────────── */
$n = SessionDraftGate::forNewSession( 0 );
$t( 'sans formateur : brouillon',        $n['is_draft'], 1 );
$t( 'sans formateur : statut Brouillon', $n['status'],   'Brouillon' );

$n = SessionDraftGate::forNewSession( 7 );
$t( 'formateur désigné : séance validée', $n['is_draft'], 0 );
$t( 'formateur désigné : Planifiée',      $n['status'],   'Planifiée' );

/* Une chaîne venue d'un formulaire vaut un entier — le trou serait invisible. */
$t( 'un identifiant en chaîne compte',    SessionDraftGate::forNewSession( '7' )['is_draft'], 0 );
$t( 'une chaîne vide ne compte pas',      SessionDraftGate::forNewSession( '' )['is_draft'],  1 );

/* ── LES DEUX COLONNES DISENT LA MÊME VÉRITÉ ─────────────────────────────
   Le drapeau et le statut cohabitent depuis longtemps. Élire l'un et ignorer
   l'autre laisserait passer les séances où seul le second est posé. */
$t( 'drapeau seul : brouillon', SessionDraftGate::isDraft( $seance( 1, 'Planifiée' ) ) ? 'oui' : 'non', 'oui' );
$t( 'statut seul : brouillon',  SessionDraftGate::isDraft( $seance( 0, 'Brouillon' ) ) ? 'oui' : 'non', 'oui' );
$t( 'ni l’un ni l’autre',       SessionDraftGate::isDraft( $seance( 0, 'Planifiée' ) ) ? 'oui' : 'non', 'non' );

/* ── LA CONVOCATION EST RETENUE ──────────────────────────────────────────── */
$t( 'brouillon : convocation retenue',
    SessionDraftGate::holdsConvocation( $seance( 1, 'Brouillon' ) ) ? 'oui' : 'non', 'oui' );
$t( 'séance validée : convocation libre',
    SessionDraftGate::holdsConvocation( $seance( 0, 'Planifiée', 7 ) ) ? 'oui' : 'non', 'non' );

/* AUCUNE séance n'est un autre problème, que le moteur nomme déjà par son
   étape « séance manquante ». Cette porte-ci ne doit pas le confisquer, sans
   quoi un dossier sans séance se verrait reprocher un brouillon inexistant. */
$t( 'aucune séance : ce n’est pas à cette porte de trancher',
    SessionDraftGate::holdsConvocation( null ) ? 'oui' : 'non', 'non' );

/* ── LA QUESTION SE REFERME QUAND LA RÉPONSE ARRIVE ──────────────────────── */
$t( 'brouillon sans formateur + formateur désigné : libérée',
    SessionDraftGate::shouldRelease( 1, 0, 7 ) ? 'oui' : 'non', 'oui' );
$t( 'aucun formateur désigné : rien ne change',
    SessionDraftGate::shouldRelease( 1, 0, 0 ) ? 'oui' : 'non', 'non' );
$t( 'séance déjà validée : rien à libérer',
    SessionDraftGate::shouldRelease( 0, 0, 7 ) ? 'oui' : 'non', 'non' );

/* Un brouillon posé DÉLIBÉRÉMENT sur une séance qui a déjà son formateur n'est
   pas notre affaire : le lever reviendrait à publier à la place d'un humain. */
$t( 'brouillon voulu, formateur déjà là : on ne décide pas à sa place',
    SessionDraftGate::shouldRelease( 1, 3, 7 ) ? 'oui' : 'non', 'non' );

/* ── LA VALIDATION EXIGE UN FORMATEUR ────────────────────────────────────── */
$v = SessionDraftGate::canValidate( $seance( 1, 'Brouillon', 0 ) );
$t( 'valider sans formateur : refusé', $v['ok'] ? 'oui' : 'non', 'non' );
$t( 'et le refus dit quoi faire',      '' !== $v['error'] ? 'oui' : 'non', 'oui' );

$v = SessionDraftGate::canValidate( $seance( 1, 'Brouillon', 7 ) );
$t( 'valider avec formateur : accepté', $v['ok'] ? 'oui' : 'non', 'oui' );

$v = SessionDraftGate::canValidate( $seance( 0, 'Planifiée', 7 ) );
$t( 'valider une séance déjà validée : sans objet', $v['ok'] ? 'oui' : 'non', 'non' );

/* ── LE MOTIF S'AFFICHE, IL NE SE DEVINE PAS ─────────────────────────────── */
$t( 'motif : formateur manquant',
    SessionDraftGate::reason( $seance( 1, 'Brouillon', 0 ) ), 'Formateur à désigner' );
$t( 'motif : brouillon complet',
    SessionDraftGate::reason( $seance( 1, 'Brouillon', 7 ) ), 'À valider' );
$t( 'séance validée : aucun motif',
    SessionDraftGate::reason( $seance( 0, 'Planifiée', 7 ) ), '' );

printf( "%d contrôles, %d échec(s)\n", 24, $ko );
exit( 0 === $ko ? 0 : 1 );
