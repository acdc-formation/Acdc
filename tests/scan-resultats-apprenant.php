<?php
/**
 * ACDC 3.25.328 — L'apprenant lit ses résultats, et peut les emporter.
 *
 * DEUX SILENCES, LA MÊME FAMILLE : un écran qui interroge une donnée que
 * personne n'écrit.
 *
 * 1. LES RÉSULTATS NE REMONTAIENT PAS. Les trois évaluations des acquis du
 *    19 août, rattachées à la main à leur apprenante, ne sont apparues dans
 *    aucun extranet. Tout le portail retrouve ses données par l'ADRESSE E-MAIL
 *    du compte ; or un participant rattaché en salle porte « learner_id » et
 *    rien d'autre — ni adresse recopiée sur sa ligne, ni garantie que la fiche
 *    apprenant en ait une, ni qu'elle soit celle du compte. L'écran restait
 *    vide, sans rien dire.
 *
 * 2. « VOIR LE DÉTAIL » N'AFFICHAIT AUCUNE QUESTION. La vue appelle
 *    get_qz_questions_for_quiz( $p->quiz_id ) — une colonne que la requête ne
 *    rendait pas : la table des participants ne la porte pas, et seul le TITRE
 *    du quiz était joint. Quel que soit le quiz, l'apprenant lisait « Les
 *    questions de ce quiz ne sont plus disponibles ».
 *
 * 3. LE BOUTON PDF N'EXISTAIT POUR PERSONNE. Il était conditionné à une colonne
 *    « result_document_url » que RIEN dans le plugin n'écrit — et qu'aucune
 *    migration ne crée. Trois endroits le testaient, aucun ne pouvait
 *    l'afficher. La pièce se fabrique désormais à la demande, à partir des
 *    mêmes données que l'écran : ce qui est lu est ce qui est imprimé.
 *
 * Éprouvé en sabotant : chaque règle échoue quand on défait la correction.
 */

$racine = dirname( __DIR__ ) . '/acdc-formation-saas-organisme-de-formation';

$lire = function ( $relatif ) use ( $racine ) {
    $chemin = $racine . '/' . $relatif;
    if ( ! is_readable( $chemin ) ) {
        fwrite( STDERR, "Fichier introuvable : $chemin\n" );
        exit( 1 );
    }
    return (string) file_get_contents( $chemin );
};

$noyau  = $lire( 'includes/learner-portal/core/class-acdc-learner-portal-core-trait.php' );
$rendu  = $lire( 'includes/learner-portal/render/class-acdc-learner-portal-render-trait.php' );
$plugin = $lire( 'includes/class-acdc-plugin.php' );

$echecs = array();
$verifs = 0;
$exiger = function ( $condition, $message ) use ( &$echecs, &$verifs ) {
    $verifs++;
    if ( ! $condition ) {
        $echecs[] = $message;
    }
};

/* --- 1. L'ADRESSE N'EST PLUS LE SEUL CHEMIN --- */
$exiger(
    false !== strpos( $noyau, 'private function learner_portal_learner_ids(' ),
    'Le résolveur des apprenants d’un compte a disparu : le portail redevient dépendant de la seule adresse e-mail, la clé la plus fragile qui soit.'
);
$exiger(
    (bool) preg_match( '/\$ids\[\]\s*=\s*\(int\)\s*\$account->primary_learner_id;/', $noyau ),
    'Le compte de portail ne s’appuie plus sur « primary_learner_id » : une fiche apprenant sans adresse, ou dont l’adresse a été corrigée, cesserait d’être reconnue.'
);
$exiger(
    3 === substr_count( $noyau, '$ou_app  = empty( $ids_app )' ),
    'Les trois listes de quiz de l’extranet — à faire, terminés, détail — ne reconnaissent plus toutes l’apprenant par son identifiant : celle qui ne le fait plus restera vide pour un participant rattaché en salle.'
);
$exiger(
    3 === substr_count( $noyau, '{$ou_app}' ),
    'Le chemin par identifiant d’apprenant n’est plus injecté dans les trois requêtes.'
);

/* --- 2. « VOIR LE DÉTAIL » A DE QUOI TRAVAILLER --- */
/* On isole la requête du DÉTAIL : les deux listes voisines sélectionnaient déjà
   « quiz_id », et une règle posée sur tout le fichier passerait au vert alors
   que la seule requête concernée l'aurait perdu. */
$debut_detail = strpos( $noyau, 'public function get_learner_quiz_participant(' );
$detail = false !== $debut_detail ? substr( $noyau, $debut_detail, 2600 ) : '';
$exiger(
    '' !== $detail && (bool) preg_match( '/q\.id AS quiz_id/', $detail ),
    'La requête du détail ne rend plus « quiz_id » : la vue appelle get_qz_questions_for_quiz() avec une valeur vide et affiche « Les questions de ce quiz ne sont plus disponibles ».'
);
$exiger(
    '' !== $detail && (bool) preg_match( '/AS learner_full_name/', $detail ),
    'Le nom de l’apprenant n’est plus joint : le PDF ne saurait plus dire qui a passé l’épreuve.'
);

/* --- 3. LE PDF EXISTE VRAIMENT --- */
$exiger(
    false !== strpos( $noyau, 'private function acdc_learner_quiz_result_pdf_html(' ),
    'La fabrication du PDF de résultat a disparu.'
);
$exiger(
    false !== strpos( $noyau, 'public function handle_learner_download_quiz_result()' ),
    'Le point d’entrée de téléchargement du résultat a disparu.'
);
$exiger(
    (bool) preg_match( "/wp_verify_nonce\(.{0,120}'acdc_learner_download_quiz_result_'/s", $noyau ),
    'Le téléchargement du résultat ne vérifie plus son jeton.'
);
$exiger(
    (bool) preg_match( '/handle_learner_download_quiz_result.{0,1200}get_learner_quiz_participant\( \$participant_id, \$account->email \)/s', $noyau ),
    'Le téléchargement ne repasse plus par la porte qui vérifie que ce résultat est bien celui de l’apprenant connecté : n’importe quel identifiant rendrait le résultat d’autrui.'
);
$exiger(
    false !== strpos( $plugin, "admin_post_nopriv_acdc_learner_download_quiz_result" ),
    'L’action n’est plus ouverte aux visiteurs non connectés à WordPress : un compte de portail n’étant pas un compte WordPress, le téléchargement échouerait pour tous les apprenants.'
);
$exiger(
    is_readable( $racine . '/includes/pdf-templates/resultat-quiz.php' ),
    'Le gabarit du résultat en PDF a disparu.'
);

/* --- 4. PLUS AUCUN BOUTON SUSPENDU À UNE COLONNE MORTE --- */
$exiger(
    false === strpos( $rendu, '$has_pdf' ),
    'Le bouton PDF est de nouveau conditionné à « result_document_url », une colonne que rien n’écrit et qu’aucune migration ne crée : il ne s’affichera pour personne.'
);
$exiger(
    2 === substr_count( $rendu, 'acdc_learner_quiz_result_pdf_url(' )
        && 3 === substr_count( $rendu, 'esc_url( $pdf_url )' ),
    'Les trois emplacements du bouton PDF — la liste et les deux de la vue détaillée — ne pointent plus tous vers l’adresse de fabrication à la demande.'
);

/* --- 5. LE DOCUMENT RESPECTE LA CHARTE COMMUNE --- */
$gabarit = $lire( 'includes/pdf-templates/resultat-quiz.php' );
$exiger(
    false !== strpos( $gabarit, "\$d( 'org_addr' )" ),
    'Le gabarit du résultat n’affiche plus l’adresse issue de la fiche identité : il finirait par porter une adresse recopiée, qui survivrait à un déménagement.'
);
$exiger(
    ! preg_match( '/\b(?:83310|Cogolin|acdc-formation\.com)\b/', $gabarit ),
    'Une coordonnée de l’organisme est écrite en dur dans le gabarit du résultat.'
);

if ( $echecs ) {
    foreach ( $echecs as $e ) {
        fwrite( STDERR, "ÉCHEC — $e\n" );
    }
    fwrite( STDERR, sprintf( "\n%d/%d vérifications passées.\n", $verifs - count( $echecs ), $verifs ) );
    exit( 1 );
}

echo sprintf( "Résultats de l’apprenant : lus, détaillés, téléchargeables — %d/%d vertes.\n", $verifs, $verifs );
exit( 0 );
