<?php
/**
 * ACDC 3.25.319 — Un bilan vidé n'est plus un bilan rendu.
 *
 * Il n'existe aucun bouton « supprimer » pour le bilan post-formation : la seule
 * façon d'effacer une saisie — un essai de recette, un bilan posé sur la
 * mauvaise séance — est de vider les champs et de réenregistrer.
 *
 * Or la date de dépôt était conservée dès qu'elle existait, quoi qu'on
 * enregistre ensuite. La séance restait donc marquée « bilan rendu » avec un
 * bilan vide, et l'indicateur 21 de Qualiopi comptait une pièce qui n'existait
 * plus. Un état qu'on ne peut pas défaire est un état faux.
 *
 * Éprouvé en sabotant : chaque règle échoue quand on défait la correction.
 */

$racine  = dirname( __DIR__ ) . '/acdc-formation-saas-organisme-de-formation';
$fichier = $racine . '/includes/trainer-portal/actions/class-acdc-trainer-portal-actions-trait.php';

if ( ! is_readable( $fichier ) ) {
    fwrite( STDERR, "Fichier introuvable : $fichier\n" );
    exit( 1 );
}

/* Le code sans ses commentaires : chercher un appel dans le source trouve aussi
   la phrase qui EXPLIQUE la correction. */
$sans = '';
foreach ( token_get_all( (string) file_get_contents( $fichier ) ) as $jeton ) {
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

$debut = strpos( $sans, 'report_group_level' );
if ( false === $debut ) {
    fwrite( STDERR, "ÉCHEC — l’enregistrement du bilan est introuvable.\n" );
    exit( 1 );
}
$corps = substr( $sans, max( 0, $debut - 600 ), 2200 );

$echecs = array();
$verifs = 0;
$exiger = function ( $condition, $message ) use ( &$echecs, &$verifs ) {
    $verifs++;
    if ( ! $condition ) {
        $echecs[] = $message;
    }
};

/* 1. Les quatre champs du bilan sont toujours enregistrés. */
foreach ( array( 'report_group_level', 'report_objectives_reached', 'report_incidents', 'report_recommendations' ) as $champ ) {
    $exiger(
        false !== strpos( $corps, $champ ),
        sprintf( 'Le champ « %s » n’est plus enregistré : le bilan serait amputé.', $champ )
    );
}

/* 2. La date de dépôt SUIT le contenu : elle ne peut plus être posée
      inconditionnellement. */
$exiger(
    ! preg_match( "/'report_submitted_at'\s*=>\s*\\\$submitted_at/", $corps ),
    'La date de dépôt est de nouveau posée sans regarder le contenu : un bilan vidé resterait « rendu », et il n’existe aucun bouton pour le supprimer.'
);
$exiger(
    (bool) preg_match( '/\$rempli\s*\?\s*\(.*?\)\s*:\s*null/s', $corps ),
    'La date de dépôt ne s’efface plus quand le bilan est vidé : l’indicateur 21 compterait une pièce inexistante.'
);

/* 3. Et elle ne se déplace pas non plus : un bilan corrigé garde sa date
      d'origine, sinon la chronologie du dossier ment. */
$exiger(
    (bool) preg_match( '/\$existing\s*\?\s*\$existing\s*:\s*current_time/', $corps ),
    'La date de dépôt est réécrite à chaque enregistrement : corriger une faute de frappe déplacerait la date de rendu du bilan.'
);

if ( $echecs ) {
    foreach ( $echecs as $e ) {
        fwrite( STDERR, "ÉCHEC — $e\n" );
    }
    fwrite( STDERR, sprintf( "\n%d/%d vérifications passées.\n", $verifs - count( $echecs ), $verifs ) );
    exit( 1 );
}

echo sprintf( "Bilan formateur : la date de dépôt suit le contenu — %d/%d vertes.\n", $verifs, $verifs );
exit( 0 );
