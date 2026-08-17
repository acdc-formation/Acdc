<?php
/**
 * L'adresse qui expédie — le 17 août, tous les e-mails en indésirables.
 *
 * Pas quelques-uns : TOUS, d'un coup, y compris ceux qui arrivaient la veille.
 * L'authentification du domaine était pourtant intacte — SPF, DKIM, DMARC,
 * 9,6/10 chez mail-tester, serveur hors listes noires.
 *
 * La cause : en 3.25.290, un lot a fait lire la fiche identité partout où une
 * adresse était écrite en dur. C'était juste pour les conventions et les
 * factures. Ça ne l'était pas pour l'en-tête « From: », dont le DOMAINE est ce
 * que DMARC vérifie. Le repli supprimé — « contact@acdc-formation.com » —
 * n'était pas une négligence : c'était l'adresse du domaine signé.
 *
 * Ces cas fixent la règle : une adresse de CONTACT ne devient jamais un
 * « From: ». Seul un réglage explicite d'adresse d'ENVOI peut le remplacer.
 */
define( 'ACDC_SUPPORT_TESTING', true );
require_once __DIR__ . '/../acdc-formation-saas-organisme-de-formation/src/AdresseExpedition.php';

use ACDC\AdresseExpedition as AE;

$echecs = array();
function verifie( $titre, $attendu, $obtenu ) {
    global $echecs;
    $ok = ( $attendu === $obtenu );
    printf( "  %-62s %s\n", $titre, $ok ? 'oui' : '>>> NON — ' . var_export( $obtenu, true ) );
    if ( ! $ok ) { $echecs[] = $titre; }
}

/* 1. Sans réglage, on retombe sur le domaine signé — jamais ailleurs. */
verifie( 'aucun réglage : l’adresse du domaine signé', AE::DEFAUT, AE::resoudre( array() ) );
verifie( 'réglage vide : idem', AE::DEFAUT, AE::resoudre( array( 'sender_email' => '' ) ) );
verifie( 'réglage en espaces : idem', AE::DEFAUT, AE::resoudre( array( 'sender_email' => '   ' ) ) );

/* 2. LE CAS DU 17 AOÛT — une adresse de contact ne doit pas devenir un From.
      Le tableau reçu ne contient QUE des clés de contact : rien ne doit s'y
      substituer, quelle que soit leur ressemblance avec une adresse d'envoi. */
$contact_seul = array( 'email' => 'davidcontal@gmail.com', 'reply_to' => 'contact@autre-domaine.fr' );
verifie(
    'adresses de contact seules : le From reste sur le domaine signé',
    AE::DEFAUT,
    AE::resoudre( $contact_seul )
);

/* 3. Un réglage explicite d'ENVOI prime : c'est le seul qui le peut. */
verifie(
    'sender_email explicite : il prime',
    'no-reply@acdc-formation.com',
    AE::resoudre( array( 'sender_email' => 'no-reply@acdc-formation.com' ) )
);
verifie(
    'sender_email entouré d’espaces : nettoyé, et il prime',
    'envoi@acdc-formation.com',
    AE::resoudre( array( 'sender_email' => '  envoi@acdc-formation.com  ' ) )
);

/* 4. Une saisie invalide ne casse pas l'envoi : on retombe sur le domaine signé
      plutôt que de composer un « From: » impossible. */
foreach ( array( 'pas-une-adresse', '@acdc-formation.com', 'a@', 'deux@@arobases.fr' ) as $mauvaise ) {
    verifie(
        sprintf( 'saisie invalide « %s » : repli sur le domaine signé', $mauvaise ),
        AE::DEFAUT,
        AE::resoudre( array( 'sender_email' => $mauvaise ) )
    );
}

/* 5. Un autre domaine d'envoi reste possible — la règle n'est pas un verrou. */
verifie(
    'autre domaine passé en défaut : respecté',
    'bonjour@exemple.fr',
    AE::resoudre( array(), 'bonjour@exemple.fr' )
);
verifie(
    'défaut invalide et aucun réglage : chaîne vide, jamais une adresse inventée',
    '',
    AE::resoudre( array(), 'n’importe quoi' )
);

/* 6. L'alignement : ce qui distingue « arrive » de « indésirable ». */
verifie( 'même domaine : aligné', true, AE::alignee( 'contact@acdc-formation.com', 'acdc-formation.com' ) );
verifie( 'sous-domaine d’envoi : aligné (DMARC relâché)', true, AE::alignee( 'no-reply@mail.acdc-formation.com', 'acdc-formation.com' ) );
verifie( 'LE PIÈGE : le domaine sans tiret n’est PAS le domaine signé', false, AE::alignee( 'contact@acdcformation.com', 'acdc-formation.com' ) );
verifie( 'fournisseur grand public : non aligné', false, AE::alignee( 'davidcontal@gmail.com', 'acdc-formation.com' ) );
verifie( 'domaine qui se termine pareil sans en être un : non aligné', false, AE::alignee( 'x@faux-acdc-formation.com', 'acdc-formation.com' ) );
verifie( 'adresse vide : non aligné, et pas d’erreur', false, AE::alignee( '', 'acdc-formation.com' ) );

/* 7. Le domaine se lit sur la DERNIÈRE arobase. */
verifie( 'domaine extrait', 'acdc-formation.com', AE::domaine( 'Contact@ACDC-Formation.com' ) );
verifie( 'sans arobase : rien', '', AE::domaine( 'pas-une-adresse' ) );

echo "\n";
if ( $echecs ) {
    printf( "%d cas en échec : %s\n", count( $echecs ), implode( ' | ', $echecs ) );
    exit( 1 );
}
echo "Adresse d’expédition : une adresse de contact ne devient jamais un « From: ».\n";
exit( 0 );
