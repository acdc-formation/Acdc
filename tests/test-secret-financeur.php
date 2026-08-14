<?php
/**
 * Le mot de passe d'un espace financeur : vide ne veut pas dire « efface ».
 *
 * La fiche OPCO garde désormais nos identifiants de connexion à l'espace du
 * financeur. Ce mot de passe doit pouvoir être RELU — on va s'en servir pour
 * se connecter — donc il est chiffré, pas haché. Et il n'est jamais réécrit
 * dans la page : l'imprimer masqué en HTML reviendrait à le publier dans le
 * code source, le masque ne serait qu'un décor.
 *
 * Conséquence : à chaque enregistrement de la fiche, le champ arrive VIDE.
 * Si vide signifiait « efface », changer le téléphone de l'interlocuteur
 * effacerait le mot de passe sans le dire. C'est exactement la famille de
 * pannes déjà vue ici — un écran qui affirme sans avoir lu.
 *
 * La règle testée ici : seule une case cochée efface.
 */

require_once __DIR__ . '/../acdc-formation-saas-organisme-de-formation/src/Support/StoredSecret.php';

use ACDC\Support\StoredSecret;

$ko = 0;
$t  = function ( $label, $obtenu, $attendu ) use ( &$ko ) {
    if ( (string) $obtenu !== (string) $attendu ) {
        $ko++;
        printf( "ÉCHEC  %s : attendu « %s », obtenu « %s »\n", $label, $attendu, $obtenu );
    }
};

/* LE CAS RÉEL : on modifie le téléphone de l'interlocuteur, le champ mot de
   passe reste vide parce que le formulaire ne le réaffiche jamais. */
$t( 'un champ vide conserve le mot de passe enregistré',
    StoredSecret::decide( '' ), StoredSecret::KEEP );

$t( 'des espaces seuls ne sont pas un mot de passe',
    StoredSecret::decide( '   ' ), StoredSecret::KEEP );

$t( 'un champ absent du formulaire conserve aussi',
    StoredSecret::decide( null ), StoredSecret::KEEP );

/* Une saisie réelle remplace. */
$t( 'une saisie enregistre le nouveau mot de passe',
    StoredSecret::decide( 'M0nMotDePasse!' ), StoredSecret::SET );

$t( 'un mot de passe qui commence par un espace reste un mot de passe',
    StoredSecret::decide( ' secret' ), StoredSecret::SET );

$t( 'le chiffre zéro est un mot de passe valable',
    StoredSecret::decide( '0' ), StoredSecret::SET );

/* La case « effacer » est la SEULE façon de supprimer. */
$t( 'la case cochée efface',
    StoredSecret::decide( '', true ), StoredSecret::CLEAR );

/* Cocher « effacer » ET taper un mot de passe : l'utilisateur demande un état
   final sans secret. La case l'emporte — le doute se tranche vers le moins
   conservé, jamais vers le plus. */
$t( 'la case cochée l’emporte sur une saisie',
    StoredSecret::decide( 'oups', true ), StoredSecret::CLEAR );

/* La case décochée est un non-événement, pas un ordre. */
$t( 'une case décochée ne déclenche rien',
    StoredSecret::decide( '', false ), StoredSecret::KEEP );

/* Ce que le formulaire envoie vraiment : '1' pour cochée, rien pour décochée.
   On rejoue la lecture du gestionnaire pour vérifier qu'elle est fidèle. */
$post_coche   = array( 'portal_password' => '', 'portal_password_clear' => '1' );
$post_normal  = array( 'portal_password' => '' );
$lire = function ( $post ) {
    return StoredSecret::decide(
        isset( $post['portal_password'] ) ? $post['portal_password'] : '',
        ! empty( $post['portal_password_clear'] )
    );
};
$t( 'formulaire avec case cochée   → efface',   $lire( $post_coche ),  StoredSecret::CLEAR );
$t( 'formulaire sans case          → conserve', $lire( $post_normal ), StoredSecret::KEEP );

/* Les trois décisions sont distinctes : aucune confusion possible en aval. */
$distinctes = count( array_unique( array( StoredSecret::KEEP, StoredSecret::CLEAR, StoredSecret::SET ) ) );
$t( 'les trois décisions sont distinctes', $distinctes, 3 );

printf( "12 contrôles, %d échec(s)\n", $ko );
exit( 0 === $ko ? 0 : 1 );
