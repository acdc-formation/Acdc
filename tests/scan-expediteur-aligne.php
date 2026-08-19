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
/* ACDC 3.25.326 — Le seuil passe de 4 à 3 : les questionnaires n'en composent
   plus aucun. Ils en composaient trois, dans un tableau d'en-têtes que personne
   ne passait à l'envoi — du code mort qui donnait à croire qu'un réglage de ce
   module gouvernait l'expédition. Voir la règle 5. */
$exiger( $sites >= 3, sprintf( 'Seulement %d compositions de « From: » examinées : le balayage ne trouve plus le code qu’il surveille.', $sites ) );

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
 * 5. Les questionnaires ne composent plus d'expéditeur du tout
 *
 * ACDC 3.25.326 — La règle précédente exigeait que leur réglage ne puisse pas
 * rendre une adresse vide. Elle défendait un chemin qui n'existe plus : ces
 * en-têtes n'étaient jamais passés à l'envoi. Un réglage qui ne commande rien
 * n'a pas besoin d'être juste, il a besoin de disparaître — sinon c'est là
 * qu'on cherchera la panne le jour où un e-mail partira de travers.
 * --------------------------------------------------------------------- */
/* Le code SEUL : la note qui explique le retrait cite forcément le nom de la
   fonction retirée. Prendre un commentaire pour du code est la faute que ce
   dépôt a déjà commise trois fois — on laisse PHP découper. */
$acdc_code_nu = function ( $chemin ) {
    $sans = '';
    foreach ( token_get_all( (string) file_get_contents( $chemin ) ) as $jeton ) {
        if ( is_array( $jeton ) ) {
            if ( T_COMMENT === $jeton[0] || T_DOC_COMMENT === $jeton[0] ) {
                $sans .= str_repeat( "\n", substr_count( $jeton[1], "\n" ) );
                continue;
            }
            $sans .= $jeton[1];
            continue;
        }
        $sans .= $jeton;
    }
    return $sans;
};
foreach ( array(
    'includes/questionnaires/class-acdc-questionnaires-core-trait.php',
    'includes/questionnaires/class-acdc-questionnaires-actions-trait.php',
) as $relatif ) {
    $quest = $acdc_code_nu( $racine . '/' . $relatif );
    $exiger(
        ! preg_match( "/\\\$headers\[\]\s*=\s*'From: /", $quest ),
        sprintf( '%s recompose un « From: » pour les enquêtes. Il ne sera pas transmis à l’envoi — la porte commune décide — mais il fera croire qu’un réglage de ce module gouverne l’expédition.', $relatif )
    );
    $exiger(
        false === strpos( $quest, 'get_questionnaire_mail_settings' ),
        sprintf( '%s rappelle un réglage d’expéditeur propre aux questionnaires : l’adresse d’expédition se décide à un seul endroit, src/AdresseExpedition.php.', $relatif )
    );
}

if ( $echecs ) {
    foreach ( $echecs as $e ) {
        fwrite( STDERR, "ÉCHEC — $e\n" );
    }
    fwrite( STDERR, sprintf( "\n%d/%d vérifications passées.\n", $verifs - count( $echecs ), $verifs ) );
    exit( 1 );
}

echo sprintf( "Expéditeur aligné : %d vérifications vertes, %d compositions de « From: » examinées.\n", $verifs, $sites );
exit( 0 );
