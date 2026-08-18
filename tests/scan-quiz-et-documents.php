<?php
/**
 * ACDC 3.25.325 — Quatre écrans qui cherchaient au mauvais endroit.
 *
 * CE QUE CE BALAYAGE DÉFEND. Quatre défauts constatés le 18 août, un seul
 * mécanisme : du code qui interroge une source là où l'application en écrit une
 * autre. Aucun ne lève d'erreur — chacun rend une liste vide, ou une liste
 * incomplète, ce qui se lit comme « il n'y a rien ».
 *
 *   — « Documents › Quiz effectués » cherchait les quiz dans la table des
 *     DOCUMENTS, en filtrant sur les titres contenant « quiz ». Rien n'y dépose
 *     jamais de quiz : les passations vivent dans les tables du module. Écran
 *     vide en toutes circonstances.
 *
 *   — « Documents › Feuilles d'émargement » n'offrait AUCUN téléchargement,
 *     alors que « Séances › Séances validées », qui liste les mêmes séances, en
 *     propose une par demi-journée. L'exploitant devait changer d'écran pour
 *     obtenir la pièce que celui-ci nomme.
 *
 *   — « Documents › Résultats analyses complétées » affichait « Complétée » et
 *     « Oui » écrits en dur sur TOUTES les analyses, envoyées comme revenues, et
 *     ne donnait pas le PDF que l'apprenant, lui, a dans son extranet.
 *
 *   — L'extranet apprenant ne reconnaissait un quiz À FAIRE que par l'adresse
 *     portée par la ligne du participant, quand celle des quiz TERMINÉS savait
 *     déjà passer par la fiche apprenant. Deux définitions de « ses quiz » dans
 *     deux écrans voisins.
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

$echecs = array();
$verifs = 0;
$exiger = function ( $condition, $message ) use ( &$echecs, &$verifs ) {
    $verifs++;
    if ( ! $condition ) {
        $echecs[] = $message;
    }
};

/* --- 1. « QUIZ EFFECTUÉS » LIT LES TABLES DU MODULE QUIZ --- */
$noyau = $lire( 'includes/kernel/class-acdc-kernel-core-trait.php' );
$deb   = strpos( $noyau, 'private function get_completed_quiz_documents(' );
$corps = false !== $deb ? substr( $noyau, $deb, 4200 ) : '';
$exiger(
    '' !== $corps,
    'La source de l’écran « Quiz effectués » a disparu.'
);
$exiger(
    '' !== $corps && false === strpos( $corps, "LIKE '%quiz%'" ),
    'L’écran « Quiz effectués » cherche de nouveau les quiz dans la table des documents : rien n’y dépose jamais de quiz, l’écran serait vide en toutes circonstances.'
);
$exiger(
    '' !== $corps && (bool) preg_match( "/get_qz_table\(\s*'participants'\s*\)/", $corps ),
    'L’écran « Quiz effectués » n’interroge plus la table des participants.'
);
$exiger(
    '' !== $corps && false !== strpos( $corps, "p.status = 'completed'" ),
    'L’écran « Quiz effectués » ne se limite plus aux passations terminées : un quiz envoyé et jamais ouvert y figurerait comme un quiz effectué, ce qui gonfle une preuve.'
);

/* --- 2. LES FEUILLES D'ÉMARGEMENT SE TÉLÉCHARGENT DEPUIS LES DEUX ÉCRANS --- */
$seances = $lire( 'includes/sessions/class-acdc-sessions-render-trait.php' );
$rendu   = $lire( 'includes/kernel/class-acdc-kernel-render-trait.php' );
$exiger(
    (bool) preg_match( '/private function acdc_emarg_boutons_pdf\(/', $seances ),
    'La porte commune des boutons de téléchargement d’émargement a disparu.'
);
$exiger(
    substr_count( $seances, 'acdc_emarg_boutons_pdf(' ) >= 2,
    'L’écran « Séances validées » n’appelle plus la porte commune : les deux écrans vont redivergter.'
);
$exiger(
    substr_count( $rendu, 'acdc_emarg_boutons_pdf(' ) >= 2,
    'L’écran « Documents › Feuilles d’émargement » ne propose plus de téléchargement, ni dans la liste ni sur la fiche : il faut de nouveau changer d’écran pour obtenir la pièce qu’il nomme.'
);

/* --- 3. LES ANALYSES COMPLÉTÉES LE SONT VRAIMENT, ET SE TÉLÉCHARGENT --- */
$exiger(
    (bool) preg_match( "/in_array\(\s*\\\$statut,\s*array\(\s*'traite',\s*'soumise'\s*\),\s*true\s*\)/", $rendu ),
    'L’écran « Résultats analyses complétées » remontre les analyses jamais revenues sous un statut « Complétée » écrit en dur : il fait croire à une preuve qu’il n’a pas vérifiée.'
);
$exiger(
    (bool) preg_match( '/\$__pdf_nad = ! empty\( \$entry->document_url_apprenant \)/', $rendu )
        && (bool) preg_match( '/esc_url\( \$__pdf_nad \)/', $rendu ),
    'Le PDF de l’analyse du besoin n’est plus téléchargeable depuis l’administration : l’apprenant l’a dans son extranet, l’organisme devrait le régénérer à la main.'
);
$exiger(
    (bool) preg_match( "/acdc_generate_nad_apprenant_pdf&nad_id=/", $rendu ),
    'Le repli qui fabrique ET range le PDF manquant a disparu : une analyse sans pièce resterait sans pièce.'
);

/* --- 4. « SES QUIZ » A LA MÊME DÉFINITION DES DEUX CÔTÉS --- */
$portail = $lire( 'includes/learner-portal/core/class-acdc-learner-portal-core-trait.php' );
$deb_a   = strpos( $portail, 'public function get_learner_pending_quizzes(' );
$corps_a = false !== $deb_a ? substr( $portail, $deb_a, 2000 ) : '';
$exiger(
    '' !== $corps_a && false !== strpos( $corps_a, 'p.email = %s OR l.email = %s' ),
    'Les quiz à faire ne sont plus reconnus que par l’adresse portée par la ligne du participant : un apprenant inscrit depuis une séance, ou qui a rejoint en salle, ne verrait aucun quiz dans son extranet.'
);
$exiger(
    '' !== $corps_a && false !== strpos( $corps_a, 'LEFT  JOIN {$learner_table} l ON l.id = p.learner_id' ),
    'La jointure avec la fiche apprenant a disparu de la liste des quiz à faire.'
);

/* --- 5. UN PSEUDO SE RATTACHE, MAIS JAMAIS AU HASARD --- */
$quiz_noyau = $lire( 'includes/quizzes/class-acdc-quizzes-core-trait.php' );
$exiger(
    (bool) preg_match( '/private function acdc_qz_apprenant_depuis_pseudo\(/', $quiz_noyau ),
    'Le rapprochement d’un pseudo avec les apprenants de la séance a disparu : les résultats du quiz live redeviennent « non rattaché à un apprenant ».'
);
$exiger(
    (bool) preg_match( '/1 === count\( \$trouves \)/', $quiz_noyau ),
    'Le rattachement d’un pseudo n’exige plus une correspondance UNIQUE : deux homonymes dans la salle, et les réponses de l’une iraient au dossier de l’autre.'
);
$quiz_actions = $lire( 'includes/quizzes/class-acdc-quizzes-actions-trait.php' );
$exiger(
    (bool) preg_match( '/\$learner_id = \$this->acdc_qz_apprenant_depuis_pseudo\(/', $quiz_actions ),
    'Le rapprochement existe mais n’est plus appelé à l’entrée dans la partie.'
);

/* --- 6. UNE SEULE HORLOGE PAR QUESTION --- */
$exiger(
    (bool) preg_match( '/\$time_limit_ms = \(int\) \$this->qz_effective_time_limit\( \$question \) \* 1000/', $quiz_actions ),
    'Le serveur chronomètre de nouveau sur la colonne brute : une réponse rédigée, que l’écran n’a jamais chronométrée, sera refusée « Temps écoulé » à tous les coups.'
);

if ( $echecs ) {
    foreach ( $echecs as $e ) {
        fwrite( STDERR, "ÉCHEC — $e\n" );
    }
    fwrite( STDERR, sprintf( "\n%d/%d vérifications passées.\n", $verifs - count( $echecs ), $verifs ) );
    exit( 1 );
}

echo sprintf( "Quiz et documents : chaque écran lit la source qui est écrite — %d/%d vertes.\n", $verifs, $verifs );
exit( 0 );
