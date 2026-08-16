<?php
/**
 * « certificat-12-1755374400.pdf » : exact, et inexploitable.
 *
 * Un dossier de formation produit une dizaine de pièces téléchargeables. Elles
 * portaient toutes des identifiants internes et, pour certaines, un horodatage
 * Unix ou un jeton de sécurité de vingt caractères. Dans le dossier
 * « Téléchargements » de l'exploitant qui prépare un contrôle, il fallait les
 * ouvrir une à une pour savoir laquelle concernait quel client.
 *
 * Ces cas fixent la règle commune — nature, entité, date — et surtout ce qu'on
 * refuse de perdre en la simplifiant : les accents repliés plutôt que
 * supprimés, et jamais un nom vide.
 */
define( 'ACDC_SUPPORT_TESTING', true );
require_once __DIR__ . '/../acdc-formation-saas-organisme-de-formation/src/Support/NomDocument.php';
require_once __DIR__ . '/../acdc-formation-saas-organisme-de-formation/src/Support/Duree.php';

use ACDC\Support\NomDocument as N;
use ACDC\Support\Duree as D;

$echecs = array();
function verifie( $titre, $attendu, $obtenu ) {
    global $echecs;
    $ok = ( $attendu === $obtenu );
    printf( "  %-62s %s\n", $titre, $ok ? 'oui' : '>>> NON — ' . var_export( $obtenu, true ) );
    if ( ! $ok ) { $echecs[] = $titre; }
}

/* --- LA RÈGLE --- */
verifie( 'les trois certificats d’un dossier se distinguent',
    'certificat-convention-de-formation-acme-sarl-16-08-2026.pdf',
    N::composer( 'certificat Convention de formation', 'ACME SARL', '16-08-2026' ) );
verifie( '  celui du devis ne se confond pas avec lui',
    'certificat-devis-acme-sarl-16-08-2026.pdf',
    N::composer( 'certificat Devis', 'ACME SARL', '16-08-2026' ) );
verifie( '  ni celui du contrat formateur',
    'certificat-contrat-formateur-jean-dupont-16-08-2026.pdf',
    N::composer( 'certificat Contrat formateur', 'Jean DUPONT', '16-08-2026' ) );

/* --- LES ACCENTS SONT REPLIÉS, PAS SUPPRIMÉS ---
   Une raison sociale amputée de ses voyelles ne se reconnaît plus : c'est
   exactement ce que le nom de fichier devait rendre possible. */
verifie( 'société devient societe, pas socit',
    'devis-societe-generale-du-var-01-09-2026.pdf',
    N::composer( 'Devis', 'Société Générale du Var', '01-09-2026' ) );
verifie( 'les prénoms composés tiennent',
    'convocation-jean-eric-lefevre.pdf',
    N::composer( 'Convocation', 'Jean-Éric LEFÈVRE' ) );
verifie( 'une apostrophe ne colle pas deux mots',
    'convention-l-atelier-du-bois.pdf',
    N::composer( 'Convention', "L'Atelier du Bois" ) );
verifie( 'les ligatures survivent', 'devis-coeur-de-ville.pdf', N::composer( 'Devis', 'Cœur de Ville' ) );

/* --- ON NE REND JAMAIS UN NOM VIDE --- */
verifie( 'tout est vide : un nom quand même', 'document.pdf', N::composer( '', '', '' ) );
verifie( 'entité inconnue : la nature suffit', 'convocation.pdf', N::composer( 'Convocation', '', '' ) );
verifie( 'ponctuation seule : pas de nom en tirets', 'document.pdf', N::composer( '///', '???', '' ) );

/* --- LES MORCEAUX ABSENTS NE LAISSENT PAS DE TROU --- */
verifie( 'pas de date : pas de tiret orphelin',
    'devis-acme-sarl.pdf', N::composer( 'Devis', 'ACME SARL', '' ) );
verifie( 'pas d’entité : pas de double tiret',
    'devis-16-08-2026.pdf', N::composer( 'Devis', '', '16-08-2026' ) );

/* --- L'EXTENSION SUIT LA PIÈCE --- */
verifie( 'un aperçu de devis reste du HTML',
    'devis-de-2026-1-acme-sarl-1755374400.html',
    N::composer( 'Devis DE-2026-1', 'ACME SARL', '1755374400', 'html' ) );

/* --- UN NOM BORNÉ, ET COUPÉ ENTRE DEUX MOTS --- */
$long = N::composer( 'Convocation', str_repeat( 'Etablissement Public de Formation ', 8 ), '16-08-2026' );
verifie( 'un nom interminable est borné', true, strlen( $long ) <= 125 );
verifie( '  et il ne finit pas par un tiret', false, '-' === substr( $long, -5, 1 ) );
verifie( '  et il garde son extension', 'pdf', substr( $long, -3 ) );

/* =====================================================================
 * « Retard 138746min » — le nombre était juste, personne ne pouvait le lire.
 * ================================================================== */
verifie( 'les 138746 minutes de la recette', '96 j 8 h', D::lisible( 138746 ) );
verifie( 'moins d’une heure reste en minutes', '45 min', D::lisible( 45 ) );
verifie( 'une heure pile', '1 h', D::lisible( 60 ) );
verifie( 'deux heures et quart', '2 h 15', D::lisible( 135 ) );
verifie( '  les minutes gardent leur zéro de tête', '2 h 05', D::lisible( 125 ) );
verifie( 'deux heures pile : pas de « 2 h 00 »', '2 h', D::lisible( 120 ) );
verifie( 'un jour pile : pas de « 1 j 0 h »', '1 j', D::lisible( 1440 ) );
verifie( 'un jour et deux heures', '1 j 2 h', D::lisible( 1560 ) );

/* --- UNE PASTILLE QUI N'ALARME PAS POUR RIEN --- */
verifie( 'aucun retard : aucune pastille', '', D::retard( 0 ) );
verifie( 'un retard négatif n’existe pas', '', D::retard( -30 ) );
verifie( 'un retard réel se lit', 'Retard 96 j 8 h', D::retard( 138746 ) );

echo "\n";
if ( $echecs ) {
    printf( "%d cas en échec : %s\n", count( $echecs ), implode( ' | ', $echecs ) );
    exit( 1 );
}
echo "Noms de documents et durées : lisibles, jamais vides, jamais amputés.\n";
exit( 0 );
