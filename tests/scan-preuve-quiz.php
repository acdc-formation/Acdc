<?php
/**
 * Un résultat de quiz qui n'arrive sur aucun dossier n'est pas une preuve.
 *
 * Trois quiz structurels accompagnent chaque action de formation : le test de
 * positionnement, l'évaluation diagnostique et l'évaluation des acquis. Les
 * deux premiers mesurent l'entrée, le dernier mesure la sortie — c'est
 * exactement ce qu'un auditeur Qualiopi vient vérifier.
 *
 * DEUX D'ENTRE EUX DÉPOSAIENT LEUR RÉSULTAT SUR LA FICHE D'INSCRIPTION. Le
 * troisième, l'évaluation diagnostique, ne déposait rien. Son résultat existait
 * bel et bien : il était consultable dans le module quiz, avec son score et son
 * PDF. Mais il ne rejoignait jamais le dossier de l'apprenant — l'endroit où
 * l'on va chercher ses pièces le jour où on les demande.
 *
 * Ce n'est pas une donnée perdue, c'est une donnée introuvable. La différence
 * ne se voit pas tant que personne ne cherche, et elle est totale le jour où
 * quelqu'un cherche.
 *
 * Ce balayage exige, pour chacun des trois usages :
 *   1. que la propagation vers la fiche d'inscription le nomme ;
 *   2. que la colonne visée existe dans le schéma ;
 *   3. qu'un écran de dossier l'affiche — une colonne que personne ne lit ne
 *      vaut pas mieux qu'une colonne absente.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

/* usage du quiz => colonne attendue sur la fiche d'inscription */
$attendus = array(
    'ACDC_OF_QZ_PURPOSE_POSITIONING' => 'positioning_result_document_url',
    'ACDC_OF_QZ_PURPOSE_DIAGNOSTIC'  => 'diagnostic_result_document_url',
    'ACDC_OF_QZ_PURPOSE_ASSESSMENT'  => 'evaluation_result_document_url',
);

$hits = array();

/* ── 1. LA PROPAGATION ───────────────────────────────────────────────────── */
$quiz_core = $root . '/includes/quizzes/class-acdc-quizzes-core-trait.php';
if ( ! is_readable( $quiz_core ) ) {
    echo "class-acdc-quizzes-core-trait.php introuvable.\n";
    exit( 1 );
}
$src   = (string) file_get_contents( $quiz_core );
$debut = strpos( $src, 'function qz_propagate_result_url_to_registration(' );
if ( false === $debut ) {
    echo "qz_propagate_result_url_to_registration() a disparu : aucun résultat de quiz ne rejoindra plus le dossier d’un apprenant.\n";
    exit( 1 );
}
$fin  = preg_match( '/\n\s{0,4}(?:public|private|protected)\s+function\s/', $src, $m, PREG_OFFSET_CAPTURE, $debut + 30 )
    ? $m[0][1]
    : strlen( $src );
$bloc = substr( $src, $debut, $fin - $debut );

foreach ( $attendus as $constante => $colonne ) {
    if ( false === strpos( $bloc, $constante ) && false === strpos( $bloc, "'" . strtolower( str_replace( 'ACDC_OF_QZ_PURPOSE_', '', $constante ) ) . "'" ) ) {
        $hits[] = sprintf(
            'La propagation ne traite plus %s : le résultat restera dans le module quiz et n’apparaîtra sur aucun dossier.',
            $constante
        );
        continue;
    }
    if ( false === strpos( $bloc, $colonne ) ) {
        $hits[] = sprintf(
            '%s ne désigne plus la colonne %s : le résultat n’a plus où se déposer.',
            $constante,
            $colonne
        );
    }
}

/* ── 2. LES COLONNES EXISTENT ────────────────────────────────────────────── */
$kernel = $root . '/includes/kernel/class-acdc-kernel-core-trait.php';
$ksrc   = is_readable( $kernel ) ? (string) file_get_contents( $kernel ) : '';
foreach ( $attendus as $constante => $colonne ) {
    /* Soit dans la création de table, soit ajoutée après coup sur les
       installations existantes — les deux comptent, et il en faut au moins une :
       une colonne présente à la création mais jamais ajoutée aux bases
       existantes ne servirait qu'aux nouveaux sites. */
    $dans_schema = false !== strpos( $ksrc, $colonne . ' TEXT' );
    $ajoutee     = false !== strpos( $ksrc, "'" . $colonne . "'" );
    if ( ! $dans_schema || ! $ajoutee ) {
        $hits[] = sprintf(
            'La colonne %s manque %s : le résultat sera écrit dans le vide, sans erreur ni message.',
            $colonne,
            ! $dans_schema ? 'à la création de la table des inscriptions' : 'à la mise à niveau des bases existantes'
        );
    }
}

/* ── 3. UN ÉCRAN L'AFFICHE ───────────────────────────────────────────────── */
$ecrans = '';
foreach ( array( '/includes/kernel/class-acdc-kernel-render-trait.php', '/includes/dossiers-contracts/', '/includes/learner-portal/' ) as $cible ) {
    $chemin = $root . $cible;
    if ( is_file( $chemin ) ) {
        $ecrans .= "\n" . (string) file_get_contents( $chemin );
        continue;
    }
    if ( ! is_dir( $chemin ) ) { continue; }
    $rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $chemin ) );
    foreach ( $rii as $f ) {
        if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
        $ecrans .= "\n" . (string) file_get_contents( $f->getPathname() );
    }
}
foreach ( $attendus as $constante => $colonne ) {
    if ( false === strpos( $ecrans, $colonne ) ) {
        $hits[] = sprintf(
            'Aucun écran de dossier n’affiche %s : la preuve est enregistrée mais introuvable, ce qui revient au même le jour de l’audit.',
            $colonne
        );
    }
}

if ( $hits ) {
    echo "Preuves de quiz sans dossier :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
printf( "0 occurrence — les %d quiz structurels déposent leur résultat sur le dossier, et un écran l’y montre.\n", count( $attendus ) );
exit( 0 );
