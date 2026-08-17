<?php
/**
 * ACDC 3.25.317 — Aucun « From: » ne se compose avec une adresse de contact.
 *
 * CE QUE CE BALAYAGE DÉFEND. Le 17 août, TOUS les e-mails du plugin sont partis
 * en indésirables — pas quelques-uns, tous, y compris ceux qui arrivaient la
 * veille. L'authentification du domaine était intacte : SPF, DKIM, DMARC,
 * 9,6/10 chez mail-tester, serveur hors listes noires.
 *
 * La cause tenait à un raisonnement juste appliqué au mauvais endroit. La
 * 3.25.290 a retiré les adresses écrites en dur pour que tout suive la fiche de
 * l'organisme : c'était juste pour les conventions, les factures et les
 * coordonnées AFFICHÉES. Ça ne l'était pas pour l'en-tête « From: », dont le
 * DOMAINE est précisément ce que DMARC vérifie.
 *
 * Le lot a fait la même substitution à quatre endroits, avec quatre replis
 * différents, tous hors du domaine signé :
 *
 *   — l'en-tête commun          → l'adresse de contact de la fiche identité ;
 *   — le module de signature    → « admin_email » ;
 *   — l'expéditeur de recours   → « admin_email » ;
 *   — les questionnaires        → rien, « WordPress décidera » — c'est-à-dire
 *                                 « wordpress@ » suivi du domaine du SITE.
 *
 * Ce dernier cas dit tout : le site est « acdcformation.com », le domaine signé
 * est « acdc-formation.com ». Un tiret d'écart, et plus rien n'est aligné.
 *
 * LA RÈGLE QU'ON TIENT ICI. Une adresse de CONTACT est une préférence : elle
 * s'affiche et change avec la fiche. Une adresse d'EXPÉDITION est une donnée
 * d'infrastructure, liée au DNS. Elles ne se mélangent plus, et toute
 * composition de « From: » passe par la porte unique.
 *
 * Éprouvé en sabotant : chaque règle échoue quand on défait la correction.
 */

define( 'ACDC_SUPPORT_TESTING', true );

$racine = dirname( __DIR__ ) . '/acdc-formation-saas-organisme-de-formation';
require_once $racine . '/src/AdresseExpedition.php';
use ACDC\AdresseExpedition as AE;

$echecs = array();
$verifs = 0;
$exiger = function ( $condition, $message ) use ( &$echecs, &$verifs ) {
    $verifs++;
    if ( ! $condition ) {
        $echecs[] = $message;
    }
};

/* ------------------------------------------------------------------------
 * 1. La porte elle-même
 * --------------------------------------------------------------------- */
$exiger(
    (bool) filter_var( AE::DEFAUT, FILTER_VALIDATE_EMAIL ),
    'L’adresse d’expédition par défaut n’est pas une adresse valide : le « From: » serait vide et rien ne partirait.'
);
$exiger(
    AE::DEFAUT === AE::resoudre( array() ),
    'Sans réglage, la porte ne rend plus l’adresse du domaine signé.'
);
$exiger(
    AE::DEFAUT === AE::resoudre( array( 'email' => 'contact@autre-domaine.fr' ) ),
    'Une adresse de CONTACT devient à nouveau un « From: » : c’est exactement la faute du 17 août.'
);

/* ------------------------------------------------------------------------
 * 2. Chaque composition de « From: » dans le plugin
 * --------------------------------------------------------------------- */

/* Les deux seules exceptions, et pourquoi elles en sont :
   — la file marketing rejoue un message DÉJÀ composé : son expéditeur a été
     décidé en amont, la relecture ne le choisit pas ;
   — les e-mails de contact partent « au nom de » une personne, avec sa
     signature choisie à l'écran. C'est une fonction voulue, pas un oubli.
     Elle reste exposée au même défaut d'alignement : c'est signalé à David,
     pas corrigé en douce ici. */
$exceptions = array(
    'includes/marketing/class-acdc-marketing-actions-trait.php',
    'includes/learners-contacts/class-acdc-learners-contacts-actions-trait.php',
);

$fichiers = array();
$iterateur = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $racine . '/includes' ) );
foreach ( $iterateur as $fichier ) {
    if ( $fichier->isDir() || 'php' !== strtolower( $fichier->getExtension() ) ) {
        continue;
    }
    $fichiers[] = $fichier->getPathname();
}
sort( $fichiers );

$sites = 0;
foreach ( $fichiers as $chemin ) {
    $relatif = str_replace( $racine . '/', '', $chemin );
    if ( in_array( $relatif, $exceptions, true ) ) {
        continue;
    }
    foreach ( file( $chemin ) as $numero => $ligne ) {
        if ( ! preg_match( "/['\"]From: /", $ligne ) ) {
            continue;
        }
        /* Une ligne de commentaire qui PARLE du « From: » n'en compose pas un.
           On a déjà pris un commentaire pour du code deux fois aujourd'hui. */
        $nu = ltrim( $ligne );
        if ( '' === $nu || '*' === $nu[0] || 0 === strpos( $nu, '//' ) || 0 === strpos( $nu, '/*' ) ) {
            continue;
        }
        $sites++;
        $exiger(
            false !== strpos( $ligne, 'sender_email' ),
            sprintf(
                '%s ligne %d compose un « From: » sans passer par une adresse d’expédition (« sender_email »). Si elle y met une adresse de contact, tous les envois de ce module partiront en indésirables.',
                $relatif,
                $numero + 1
            )
        );
    }
}
$exiger( $sites >= 4, sprintf( 'Seulement %d compositions de « From: » examinées : le balayage ne trouve plus le code qu’il surveille.', $sites ) );

/* ------------------------------------------------------------------------
 * 3. Aucun repli sur « admin_email » pour un expéditeur
 * --------------------------------------------------------------------- */
foreach ( array(
    'includes/signature/class-acdc-sig-email.php',
    'includes/kernel/class-acdc-kernel-actions-trait.php',
) as $relatif ) {
    $src = (string) file_get_contents( $racine . '/' . $relatif );
    $exiger(
        ! preg_match( '/\$sender_email\s*=[^;]*admin_email/s', $src ),
        sprintf( '%s fait retomber l’adresse d’EXPÉDITION sur « admin_email » — très souvent une adresse personnelle chez un fournisseur grand public, donc jamais alignée avec le domaine signé.', $relatif )
    );
}

/* ------------------------------------------------------------------------
 * 4. L'en-tête commun distingue les deux adresses
 * --------------------------------------------------------------------- */
$noyau = (string) file_get_contents( $racine . '/includes/kernel/class-acdc-kernel-core-trait.php' );
$exiger(
    (bool) preg_match( "/'sender_email'\s*=>\s*\\\$sender_email/", $noyau ),
    'La marque commune n’expose plus « sender_email » : les modules retomberaient sur l’adresse de contact.'
);
$exiger(
    false !== strpos( $noyau, '\\ACDC\\AdresseExpedition::resoudre' ),
    'L’en-tête commun ne passe plus par la porte unique de l’adresse d’expédition.'
);

/* ------------------------------------------------------------------------
 * 5. Les questionnaires ne laissent plus « WordPress décider »
 * --------------------------------------------------------------------- */
$quest = (string) file_get_contents( $racine . '/includes/questionnaires/class-acdc-questionnaires-core-trait.php' );
$exiger(
    (bool) preg_match( '/empty\(\s*\$settings\[.sender_email.\]\s*\).{0,400}AdresseExpedition::resoudre/s', $quest ),
    'Les réglages des questionnaires peuvent à nouveau rendre une adresse d’expédition vide : WordPress y remet « wordpress@ » suivi du domaine du site, qui n’est pas le domaine signé. Ce sont ces envois-là qui partaient en indésirables.'
);

if ( $echecs ) {
    foreach ( $echecs as $e ) {
        fwrite( STDERR, "ÉCHEC — $e\n" );
    }
    fwrite( STDERR, sprintf( "\n%d/%d vérifications passées.\n", $verifs - count( $echecs ), $verifs ) );
    exit( 1 );
}

echo sprintf( "Expéditeur aligné : %d vérifications vertes, %d compositions de « From: » examinées.\n", $verifs, $sites );
exit( 0 );
