<?php
/**
 * ACDC 3.25.325 — Une analyse du besoin est nominative.
 *
 * CE QUE CE BALAYAGE DÉFEND. Le 18 août, chaque apprenant de la session voyait,
 * dans son extranet, les analyses du besoin des DEUX AUTRES — et celle du
 * commanditaire par-dessus le marché. Trois personnes, une seule liste.
 *
 * LA RACINE, ET C'EST MOI QUI L'AI POSÉE. En 3.25.306, constatant qu'aucune
 * analyse n'apparaissait, j'ai écrit que la colonne « apprenant_id » n'était
 * jamais renseignée et je me suis rabattu sur « dossier_id ». La colonne EST
 * renseignée : nad_auto_create_from_contract() l'écrit sur chaque analyse
 * d'apprenant qu'elle fabrique à la signature de la convention. Mais le
 * dossier, lui, est COMMUN aux trois apprenants — le filtre que j'avais choisi
 * ne cloisonnait donc rien. J'ai remplacé une absence par une fuite.
 *
 * CE QU'UNE ANALYSE CONTIENT. Ce que la personne a déclaré d'elle-même : son
 * niveau, ses difficultés, ses attentes, parfois son poste et ses contraintes.
 * Elle ne se partage pas entre camarades de promotion. Celle du commanditaire
 * porte en plus le contexte d'entreprise, et n'appartient à aucun apprenant.
 *
 * LA RÈGLE. Le seul rattachement qui vaut, côté extranet apprenant, est
 * « apprenant_id ». Le dossier ne doit jamais servir de clé de lecture : c'est
 * une clé de RANGEMENT, partagée par construction.
 *
 * Éprouvé en sabotant : chaque règle échoue quand on défait la correction.
 */

$racine    = dirname( __DIR__ ) . '/acdc-formation-saas-organisme-de-formation';
$portail   = $racine . '/includes/learner-portal/core/class-acdc-learner-portal-core-trait.php';
$fabrique  = $racine . '/includes/kernel/class-acdc-kernel-core-trait.php';

/* Le code débarrassé de ses commentaires : chercher une requête dans le source
   brut trouve aussi la phrase qui EXPLIQUE la correction. */
$sans_commentaires = function ( $fichier ) {
    if ( ! is_readable( $fichier ) ) {
        fwrite( STDERR, "Fichier introuvable : $fichier\n" );
        exit( 1 );
    }
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
    return $sans;
};

$code_portail  = $sans_commentaires( $portail );
$code_fabrique = $sans_commentaires( $fabrique );

/* La requête d'analyses de l'extranet apprenant, isolée de son voisinage. */
$requete = '';
if ( preg_match( '/\$nads_apprenant\s*=\s*\$wpdb->get_results\(.*?\)\s*\);/su', $code_portail, $m ) ) {
    $requete = $m[0];
}

$echecs = array();
$verifs = 0;
$exiger = function ( $condition, $message ) use ( &$echecs, &$verifs ) {
    $verifs++;
    if ( ! $condition ) {
        $echecs[] = $message;
    }
};

/* 1. La requête existe encore : sans elle, l'apprenant ne voit plus rien. */
$exiger(
    '' !== $requete,
    'La requête $nads_apprenant a disparu de l’extranet apprenant : plus aucune analyse du besoin ne s’y affiche.'
);

/* 2. Elle ne lit JAMAIS le dossier. C'est la fuite constatée le 18 août. */
$exiger(
    '' !== $requete && false === strpos( $requete, 'dossier_id' ),
    'L’extranet apprenant filtre à nouveau les analyses du besoin sur « dossier_id » : le dossier est commun aux apprenants d’une même session, chacun verrait donc les réponses des autres et celles du commanditaire.'
);

/* 3. Elle lit « apprenant_id », et uniquement lui. */
$exiger(
    '' !== $requete && (bool) preg_match( '/apprenant_id\s*=\s*%d/', $requete ),
    'L’extranet apprenant ne filtre plus sur « apprenant_id » : la cloison entre apprenants n’est plus posée.'
);

/* 4. Une analyse sans apprenant — celle du commanditaire — reste exclue.
      « apprenant_id = NULL » ne rapproche rien en SQL, mais on exige la
      condition explicite pour que l'intention reste lisible à la relecture. */
$exiger(
    '' !== $requete && false !== strpos( $requete, 'apprenant_id IS NOT NULL' ),
    'La condition « apprenant_id IS NOT NULL » a disparu : l’analyse du commanditaire, qui n’a pas d’apprenant, pourrait remonter dans un extranet apprenant.'
);

/* 5. Le garde-fou porte sur l'apprenant, pas sur le dossier : interroger le
      dossier pour décider s'il faut chercher, c'est rouvrir la porte. */
$exiger(
    (bool) preg_match( '/if\s*\(\s*!\s*empty\(\s*\$registration->learner_id\s*\)\s*\)\s*\{\s*global \$wpdb;\s*\$nads_apprenant/s', $code_portail ),
    'La recherche des analyses n’est plus conditionnée au seul apprenant : elle repartirait du dossier.'
);

/* 6. En face, la fabrique doit CONTINUER d'écrire apprenant_id sur l'analyse
      de chaque apprenant. Sans elle, la cloison ci-dessus ne montre plus rien
      à personne — le défaut de 3.25.306, à l'envers. */
$bloc_app  = '';
$debut_app = strpos( $code_fabrique, "'title'            => 'Analyse du besoin — ' . trim( \$learner->first_name" );
if ( false !== $debut_app ) {
    $bloc_app = substr( $code_fabrique, $debut_app, 1400 );
}
$exiger(
    '' !== $bloc_app && (bool) preg_match( "/'apprenant_id'\s*=>\s*\\\$learner_id/", $bloc_app ),
    'nad_auto_create_from_contract() n’écrit plus « apprenant_id » sur les analyses d’apprenant : cloisonnées sur une colonne vide, elles n’apparaîtraient dans AUCUN extranet.'
);

/* 7. Et l'analyse du commanditaire ne doit jamais recevoir d'apprenant_id. */
$bloc_cmd = '';
$debut_cmd = strpos( $code_fabrique, "'title'            => 'Analyse du besoin — ' . \$cmd_label_title" );
if ( false !== $debut_cmd ) {
    $bloc_cmd = substr( $code_fabrique, $debut_cmd, 1400 );
}
$exiger(
    '' !== $bloc_cmd && false === strpos( $bloc_cmd, 'apprenant_id' ),
    'L’analyse du commanditaire se voit attribuer un « apprenant_id » : elle atterrirait dans l’extranet d’un apprenant, avec le contexte d’entreprise qu’elle contient.'
);

/* 8. Et elle porte le nom de QUI répond. L'analyse du commanditaire s'appelait
      « Analyse du besoin — Valeriano Berengere » parce que la fiche société
      n'était chargée que si la convention portait déjà un « company_id » — or
      il n'est résolu depuis le prospect que plus bas. Deux analyses au nom de
      la même personne, dont une qui ne lui appartenait pas : c'est ce qui a
      rendu la fuite visible. */
$exiger(
    (bool) preg_match( '/empty\( \$company \) && \$company_id \) \{\s*\$company = \$wpdb->get_row/s', $code_fabrique ),
    'La fiche société n’est plus rechargée une fois son identifiant résolu depuis le prospect : l’analyse du commanditaire reprendrait le nom du signataire au lieu de la raison sociale.'
);
$exiger(
    (bool) preg_match( "/\\\$cmd_label_title = \\\$cmd_company_hint;/", $code_fabrique ),
    'Le repli sur la raison sociale lue dans le libellé du signataire a disparu : sans fiche société, le titre retomberait sur le nom d’une personne.'
);

if ( $echecs ) {
    foreach ( $echecs as $e ) {
        fwrite( STDERR, "ÉCHEC — $e\n" );
    }
    fwrite( STDERR, sprintf( "\n%d/%d vérifications passées.\n", $verifs - count( $echecs ), $verifs ) );
    exit( 1 );
}

echo sprintf( "Cloison des analyses du besoin : chacun ne voit que la sienne — %d/%d vertes.\n", $verifs, $verifs );
exit( 0 );
