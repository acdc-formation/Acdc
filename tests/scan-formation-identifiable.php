<?php
/**
 * Une formation ne se propose jamais sans dire laquelle elle est.
 *
 * Relevé en recette sur la modale de création de quiz : « il y a aucun moyen de
 * différencier deux formations avec le même nom ». Une même formation existe en
 * présentiel ET en distanciel — deux fiches, deux tarifs, deux programmes, le
 * même intitulé. Le menu déroulant proposait donc deux lignes rigoureusement
 * identiques, et celui qui choisissait tirait à pile ou face.
 *
 * La conséquence n'est pas un inconfort : rattacher un quiz, une séance ou une
 * convention à la mauvaise fiche produit une preuve Qualiopi qui désigne une
 * modalité que l'apprenant n'a pas suivie.
 *
 * LE DÉFAUT DE FOND ÉTAIT LA DISPERSION. Le plugin savait écrire la modalité —
 * il le faisait à cinq endroits. Mais quatre fonctions concurrentes
 * fabriquaient ce libellé, chacune avec sa règle, et trois sélecteurs de plus
 * n'écrivaient que le titre nu. Une même question posée à sept endroits
 * recevait sept réponses. C'est la famille de défauts la plus tenace ici : une
 * règle recopiée au lieu d'être appelée, qui diverge sans que personne ne le
 * voie.
 *
 * Ce balayage tient trois promesses :
 *   1. aucun menu déroulant de formations n'écrit un titre nu ;
 *   2. la règle commune reste unique — les façades ne se remettent pas à
 *      composer elles-mêmes ;
 *   3. la liste des formations du formateur ne se rabat plus sur le catalogue.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

$rii  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits = array();

/* Les libellés autorisés : la règle commune, ou l'une de ses façades. */
$regle_commune = '/FormationLabel|acdc_formation_choice_label|acdc_formation_cell|format_formation_option_label|format_qz_formation_label|build_formation_option_label|acdc_formation_labelled/';

foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }

    $src = (string) file_get_contents( $path );
    if ( false === strpos( $src, '<option' ) ) { continue; }
    $rel   = preg_replace( '#^.*/(includes|src)/#', '$1/', $path );
    $lines = explode( "\n", $src );

    foreach ( $lines as $i => $line ) {
        if ( false === strpos( $line, '<option' ) ) { continue; }

        /* Le voisinage dit de QUOI cette liste est faite. On ne s'intéresse
           qu'aux formations : les entreprises et les formateurs portent un
           `name`, pas un `title`, et n'ont pas de jumelle par modalité. */
        $voisinage = implode( "\n", array_slice( $lines, max( 0, $i - 6 ), 10 ) );
        if ( ! preg_match( '/\$(formations?|f|entry)\b/', $voisinage )
            || false === stripos( $voisinage, 'formation' ) ) {
            continue;
        }

        /* Le libellé peut s'écrire sur la ligne du <option> ou plus bas — la
           modale d'import du module quiz l'écrit sur trois lignes, celle des
           propositions commerciales trente lignes plus bas, après une volée
           d'attributs `data-`. On lit donc l'option jusqu'à sa fermeture. */
        $bloc = $line;
        for ( $j = $i + 1; $j < min( count( $lines ), $i + 40 ); $j++ ) {
            $bloc .= "\n" . $lines[ $j ];
            if ( false !== strpos( $lines[ $j ], '</option>' ) || false !== strpos( $lines[ $j ], '<option' ) ) { break; }
        }

        /* Un titre imprimé tel quel, sans passer par la règle commune.
           On ne regarde que `esc_html` : c'est le texte VU par l'utilisateur.
           Un `esc_attr` remplit un attribut — `value`, `data-title` — que le
           JavaScript relit et que personne ne lit à l'écran ; y exiger la
           modalité changerait la valeur transmise, pas l'affichage. */
        if ( ! preg_match( '/esc_html\(\s*\$\w+(\[[^\]]*\])?->(title|formation_title)\s*\)/', $bloc )
            && ! preg_match( '/esc_html\(\s*\$\w*title\s*\)/', $bloc ) ) {
            continue;
        }
        if ( preg_match( $regle_commune, $bloc ) ) { continue; }

        $hits[] = sprintf(
            '%s:%d  une formation est proposée sous son seul intitulé : la même formation existe en présentiel et en distanciel, la liste offre donc deux lignes identiques.  %s',
            $rel,
            $i + 1,
            trim( mb_substr( trim( $line ), 0, 90 ) )
        );
    }
}

/* ── LA RÈGLE RESTE UNIQUE ────────────────────────────────────────────────
   Les quatre fabricants de libellés sont désormais des façades. Si l'un se
   remet à composer son propre format — concaténer un titre, une parenthèse et
   une modalité — la dispersion recommence, et c'est elle le vrai défaut. */
$facades = array(
    'includes/kernel/class-acdc-kernel-core-trait.php'   => array( 'format_formation_option_label', 'build_formation_option_label', 'acdc_formation_choice_label', 'acdc_formation_cell' ),
    'includes/quizzes/class-acdc-quizzes-core-trait.php' => array( 'format_qz_formation_label' ),
);
foreach ( $facades as $fichier => $fonctions ) {
    $chemin = $root . '/' . $fichier;
    if ( ! is_readable( $chemin ) ) { continue; }
    $src = (string) file_get_contents( $chemin );
    foreach ( $fonctions as $fonction ) {
        $debut = strpos( $src, 'function ' . $fonction . '(' );
        if ( false === $debut ) {
            $hits[] = sprintf( '%s() a disparu : les écrans qui l’appellent afficheront une erreur fatale.', $fonction );
            continue;
        }
        $fin  = preg_match( '/\n\s{0,4}(?:public|private|protected)\s+function\s/', $src, $m, PREG_OFFSET_CAPTURE, $debut + 10 )
            ? $m[0][1]
            : strlen( $src );
        $bloc = substr( $src, $debut, $fin - $debut );
        if ( false === strpos( $bloc, 'FormationLabel' ) && false === strpos( $bloc, 'format_formation_option_label' ) ) {
            $hits[] = sprintf(
                '%s() ne passe plus par la règle commune : le libellé des formations se remet à diverger d’un écran à l’autre, comme avant la 3.25.277.',
                $fonction
            );
        }
    }
}

/* ── LES FORMATIONS DU FORMATEUR ──────────────────────────────────────────
   Deux fautes s'y cachaient l'une derrière l'autre : la règle de rattachement
   n'était lue qu'à moitié (la séance directe, jamais le groupe), et un repli
   affichait tout le catalogue pour compenser le vide ainsi produit. */
$portail = $root . '/includes/trainer-portal/render/class-acdc-trainer-portal-quizzes-render-trait.php';
if ( is_readable( $portail ) ) {
    $src   = (string) file_get_contents( $portail );
    $debut = strpos( $src, 'function get_qz_formations_for_trainer(' );
    if ( false === $debut ) {
        $hits[] = 'get_qz_formations_for_trainer() a disparu : la modale de création de quiz n’a plus de liste à proposer.';
    } else {
        $fin  = preg_match( '/\n\s{0,4}(?:public|private|protected)\s+function\s/', $src, $m, PREG_OFFSET_CAPTURE, $debut + 10 )
            ? $m[0][1]
            : strlen( $src );
        $bloc = substr( $src, $debut, $fin - $debut );

        if ( false !== strpos( $bloc, 'get_qz_available_formations' ) ) {
            $hits[] = 'La liste des formations du formateur se rabat de nouveau sur le catalogue entier : un rattachement mal lu redevient une autorisation universelle, et la barrière « pas de formation, pas de quiz » ne peut plus tomber.';
        }
        if ( false === strpos( $bloc, 'g.trainer_id' ) ) {
            $hits[] = 'La liste des formations du formateur ne lit plus le rattachement par GROUPE : un formateur désigné par son groupe — le cas le plus courant en multi-formateurs — ne verra aucune formation et ne pourra créer aucun quiz.';
        }
    }
}

if ( $hits ) {
    echo "Formations impossibles à distinguer :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
echo "0 occurrence — toute formation proposée dit sa modalité, et le formateur ne voit que les siennes.\n";
exit( 0 );
