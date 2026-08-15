<?php
/**
 * Changer le SIRET, le NDA ou le dirigeant doit changer TOUS les documents.
 *
 * CE QUE CE TEST PROTÈGE. Le plugin portait deux fiches d'identité —
 * « acdc_of_branding » et « acdc_of_company_profile » — et une cinquantaine de
 * lectures qui demandaient une clé inexistante : « siret » quand la fiche
 * enregistre « siret_identification », « nda_number » quand elle enregistre
 * « activity_declaration_number ». La condition échouait toujours, et le
 * document affichait la valeur de repli écrite en dur. Une facture, une
 * proposition commerciale ou un contrat de formateur pouvaient donc porter
 * l'ancienne identité APRÈS le changement, sans que rien ne le signale.
 *
 * Deux propriétés se vérifient ici, et elles suffisent :
 *   — CE QUI EST SAISI EST CE QUI SORT. Aucune valeur historique ne survit à
 *     une saisie, quel que soit le nom de la clé sous laquelle elle dort ;
 *   — UN CHAMP VIDE NE LAISSE RIEN. Ni ancienne valeur, ni étiquette creuse.
 *     « Siret : » suivi de rien, sur une facture, est pire qu'une mention
 *     absente : la seconde se corrige, la première se discute.
 */

define( 'ACDC_SUPPORT_TESTING', true );
require_once __DIR__ . '/../acdc-formation-saas-organisme-de-formation/src/Support/OrgIdentity.php';

use ACDC\Support\OrgIdentity;

$ko = 0;
$t  = function ( $label, $obtenu, $attendu ) use ( &$ko ) {
    if ( (string) $obtenu !== (string) $attendu ) {
        $ko++;
        printf( "ÉCHEC  %s\n   attendu « %s »\n   obtenu  « %s »\n", $label, $attendu, $obtenu );
    }
};

/* Une fiche entreprise telle que l'écran de réglages l'enregistre. */
$fiche = array(
    'enterprise'                  => 'ACDC Formation SASU',
    'legal_form'                  => 'SASU',
    'siret_identification'        => '111 222 333 00044',
    'activity_declaration_number' => '93 83 99999 83',
    'naf_code'                    => '8559A',
    'vat_number'                  => 'FR00111222333',
    'address'                     => '12 rue Neuve',
    'postal_code'                 => '83990',
    'city'                        => 'Saint-Tropez',
    'country'                     => 'France',
    'enterprise_contact_email'    => 'contact@exemple.test',
    'enterprise_contact_phone'    => '04 00 00 00 00',
    'website_url'                 => 'https://exemple.test/',
    'first_name'                  => 'Ann-Cécile',
    'last_name'                   => 'Joucher',
    'signatory_role'              => 'Présidente',
);

/* Une fiche marque restée sur l'ancienne identité : elle ne doit JAMAIS
   l'emporter sur la fiche entreprise. C'est exactement le scénario du
   changement d'entité — l'ancienne valeur dort quelque part et ne doit pas
   ressortir sur un document. */
$marque = array(
    'company_name' => 'ACDC Formation',
    'siret'        => '405 109 901 00042',
    'nda'          => '93 83 08347 83',
    'address'      => 'ancienne adresse',
    'postal_code'  => '83310',
    'city'         => 'Cogolin',
    'phone'        => '06 00 00 00 00',
    'email'        => 'ancien@exemple.test',
);

$id = OrgIdentity::fromOptions( $fiche, $marque );

/* ── CE QUI EST SAISI EST CE QUI SORT ───────────────────────────────────── */
$t( 'raison sociale',   $id['raison_sociale'], 'ACDC Formation SASU' );
$t( 'SIRET',            $id['siret'], '111 222 333 00044' );
$t( 'NDA',              $id['nda'], '93 83 99999 83' );
$t( 'forme juridique',  $id['forme_juridique'], 'SASU' );
$t( 'code NAF',         $id['naf'], '8559A' );
$t( 'TVA',              $id['tva'], 'FR00111222333' );
$t( 'e-mail',           $id['email'], 'contact@exemple.test' );
$t( 'téléphone',        $id['telephone'], '04 00 00 00 00' );
$t( 'ville',            $id['ville'], 'Saint-Tropez' );
$t( 'signataire',       $id['signataire'], 'Ann-Cécile Joucher' );
$t( 'rôle du signataire', $id['signataire_role'], 'Présidente' );

/* ── LA MARQUE NE SERT QUE DE SECOURS ───────────────────────────────────── */
/* Champ absent de la fiche : la marque prend le relais. */
$id2 = OrgIdentity::fromOptions( array( 'enterprise' => 'ACDC Formation SASU' ), $marque );
$t( 'secours : SIRET repris de la marque', $id2['siret'], '405 109 901 00042' );
$t( 'secours : la fiche garde la main sur ce qu’elle dit', $id2['raison_sociale'], 'ACDC Formation SASU' );

/* Champ PRÉSENT mais vide dans la fiche : c'est une absence, pas un refus —
   la marque prend le relais. On ne devine pas l'intention d'un champ vide au
   milieu d'une fiche à moitié remplie. */
$id3 = OrgIdentity::fromOptions( array( 'siret_identification' => '' ), $marque );
$t( 'champ vide : la marque prend le relais', $id3['siret'], '405 109 901 00042' );

/* Une clé héritée dans la fiche l'emporte quand même sur la marque : une fiche
   qui dit quelque chose, même sous un vieux nom, reste la fiche. */
$id4 = OrgIdentity::fromOptions( array( 'nda_number' => '93 83 77777 83' ), $marque );
$t( 'clé héritée lue dans la fiche', $id4['nda'], '93 83 77777 83' );

/* ── UN CHAMP VIDE NE LAISSE RIEN ───────────────────────────────────────── */
$vide = OrgIdentity::fromOptions( array(), array() );
$t( 'tout vide : SIRET',        $vide['siret'], '' );
$t( 'tout vide : NDA',          $vide['nda'], '' );
$t( 'tout vide : signataire',   $vide['signataire'], '' );
$t( 'tout vide : aucune ligne légale',   OrgIdentity::legalLine( $vide ), '' );
$t( 'tout vide : aucun pied de page',    OrgIdentity::footerLine( $vide ), '' );
$t( 'tout vide : aucune ligne contact',  OrgIdentity::contactLine( $vide ), '' );
$t( 'tout vide : aucun bloc société',    implode( '|', OrgIdentity::blockLines( $vide ) ), '' );

/* Sans SIRET, l'étiquette « Siret : » disparaît avec lui — et le NDA reste. */
$sans_siret = OrgIdentity::fromOptions( array( 'activity_declaration_number' => '93 83 99999 83' ), array() );
$t( 'sans SIRET : pas d’étiquette orpheline', OrgIdentity::legalLine( $sans_siret ), 'NDA : 93 83 99999 83' );

/* Sans ville, l'adresse ne produit pas de séparateur en trop. */
$sans_ville = OrgIdentity::fromOptions( array( 'address' => '12 rue Neuve', 'country' => 'France' ), array() );
$t( 'sans ville : pas de séparateur orphelin', OrgIdentity::addressLine( $sans_ville ), '12 rue Neuve - France' );

/* ── LES MISES EN FORME PARTAGÉES ───────────────────────────────────────── */
$t(
    'adresse sur une ligne',
    OrgIdentity::addressLine( $id ),
    '12 rue Neuve - 83990 Saint-Tropez - France'
);
$t(
    'mentions légales',
    OrgIdentity::legalLine( $id ),
    'Siret : 111 222 333 00044 - NDA : 93 83 99999 83'
);
$t(
    'pied de page complet',
    OrgIdentity::footerLine( $id ),
    '12 rue Neuve - 83990 Saint-Tropez - France - Siret : 111 222 333 00044 - NDA : 93 83 99999 83'
);
$t(
    'ligne de contact',
    OrgIdentity::contactLine( $id ),
    'e-mail : contact@exemple.test - Tél : 04 00 00 00 00 - site web : exemple.test'
);
$t(
    'bloc société',
    implode( ' | ', OrgIdentity::blockLines( $id ) ),
    'ACDC Formation SASU | 12 rue Neuve | 83990 Saint-Tropez — France | Siret : 111 222 333 00044 | NDA : 93 83 99999 83'
);
$t( 'séparateur choisi par l’appelant', OrgIdentity::legalLine( $id, ' — ' ), 'Siret : 111 222 333 00044 — NDA : 93 83 99999 83' );
$t( 'site affiché sans protocole ni barre finale', OrgIdentity::siteAffiche( $id ), 'exemple.test' );

/* ── LE SIGNATAIRE ──────────────────────────────────────────────────────── */
/* Un prénom seul reste un prénom seul : pas d'espace parasite en fin de nom. */
$prenom_seul = OrgIdentity::fromOptions( array( 'first_name' => 'David' ), array() );
$t( 'prénom seul', $prenom_seul['signataire'], 'David' );
$nom_seul = OrgIdentity::fromOptions( array( 'last_name' => 'Contal' ), array() );
$t( 'nom seul', $nom_seul['signataire'], 'Contal' );

/* ── LES SAISIES HUMAINES ───────────────────────────────────────────────── */
$espaces = OrgIdentity::fromOptions( array( 'siret_identification' => '  111 222 333 00044  ' ), array() );
$t( 'espaces autour de la saisie', $espaces['siret'], '111 222 333 00044' );
$blanc = OrgIdentity::fromOptions( array( 'siret_identification' => '   ' ), $marque );
$t( 'saisie faite d’espaces = saisie vide', $blanc['siret'], '405 109 901 00042' );
/* Un champ qui contiendrait un tableau (fiche corrompue) ne fait pas tomber
   la génération d'une facture. */
$tableau = OrgIdentity::fromOptions( array( 'siret_identification' => array( 'x' ) ), array() );
$t( 'valeur aberrante ignorée', $tableau['siret'], '' );
$t( 'fiche absente', OrgIdentity::fromOptions( null, null )['siret'], '' );

if ( $ko ) {
    printf( "%d échec(s)\n", $ko );
    exit( 1 );
}
echo "Identité de l’organisme : 33 cas vérifiés, ce qui est saisi est ce qui sort, un champ vide ne laisse rien.\n";
exit( 0 );
