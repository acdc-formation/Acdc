<?php
/**
 * Un interrupteur qui ne commande rien.
 *
 * La fiche formation offre onze « Automatismes Qualiopi » : convocation, test
 * de positionnement, évaluation diagnostique, enquêtes, documents de fin.
 * L'écran annonce que cocher met le document dans le parcours et que décocher
 * l'en retire.
 *
 * CE N'ÉTAIT VRAI QU'À MOITIÉ. Les questionnaires et les documents lisaient bien
 * leur interrupteur. Les QUIZ, eux — positionnement, diagnostique, évaluation
 * des acquis — étaient préparés et rattachés aux apprenants sans que rien ne
 * soit consulté. Éteindre « Évaluation des acquis » n'empêchait pas le quiz
 * d'évaluation de partir. Et l'évaluation diagnostique n'avait même pas
 * d'interrupteur à éteindre.
 *
 * Ce n'est pas « un réglage sans effet ». L'écran AFFIRME que le document est
 * sorti du parcours ; on le croit, donc on ne vérifie plus, et il y reste. Un
 * écran qui affirme sans avoir lu est plus dangereux qu'un écran vide — c'est
 * la même famille que les soixante et un filtres décoratifs retirés en
 * 3.25.270.
 *
 * Ce balayage lit la liste commune des interrupteurs et exige, pour chacun,
 * qu'au moins un automatisme du plugin le consulte ailleurs que sur l'écran
 * qui l'affiche.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

$core = $root . '/includes/kernel/class-acdc-kernel-core-trait.php';
if ( ! is_readable( $core ) ) {
    echo "class-acdc-kernel-core-trait.php introuvable.\n";
    exit( 1 );
}

/* La liste commune, lue dans le code plutôt que recopiée ici : un interrupteur
   ajouté demain sera contrôlé sans qu'on ait à toucher ce fichier. */
$src = (string) file_get_contents( $core );
$deb = strpos( $src, 'function acdc_qualiopi_toggles(' );
if ( false === $deb ) {
    echo "acdc_qualiopi_toggles() a disparu : la liste des automatismes Qualiopi n’a plus de source unique, et les quatre copies vont recommencer à diverger.\n";
    exit( 1 );
}
$fin  = strpos( $src, "\n  }", $deb );
$bloc = substr( $src, $deb, ( false === $fin ? strlen( $src ) : $fin ) - $deb );
preg_match_all( "/'([a-z_]+_enabled)'\s*=>/", $bloc, $m );
$interrupteurs = array_unique( $m[1] );

if ( count( $interrupteurs ) < 5 ) {
    echo "La liste commune des automatismes Qualiopi semble vide ou illisible.\n";
    exit( 1 );
}

/* Tout le code, sauf l'écran qui dessine les interrupteurs : y apparaître ne
   prouve rien, c'est là qu'ils sont affichés. */
$rii      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$ailleurs = '';
foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }
    /* On écarte l'écran, la liste elle-même, la sauvegarde et l'import/export :
       ils manipulent les onze champs en bloc, sans rien commander. */
    if ( false !== strpos( $path, 'class-acdc-kernel-render-trait.php' )
        || false !== strpos( $path, 'class-acdc-kernel-core-trait.php' )
        || false !== strpos( $path, 'class-acdc-kernel-actions-trait.php' ) ) {
        continue;
    }
    $ailleurs .= "\n" . (string) file_get_contents( $path );
}

$hits = array();
foreach ( $interrupteurs as $champ ) {
    if ( false === strpos( (string) $ailleurs, $champ ) ) {
        $hits[] = sprintf(
            '%s est proposé sur la fiche formation mais aucun automatisme ne le consulte : l’écran annonce que le document sort du parcours, et il y reste.',
            $champ
        );
    }
}

/* Les trois quiz structurels doivent nommer leur interrupteur : c'est là que la
   règle manquait, et c'est le seul endroit où son absence ne se voit pas. */
$prep = $root . '/includes/quizzes/class-acdc-quizzes-preparation-trait.php';
if ( is_readable( $prep ) ) {
    $psrc = (string) file_get_contents( $prep );
    foreach ( array( 'positioning_test_enabled', 'diagnostic_evaluation_enabled', 'evaluation_enabled' ) as $attendu ) {
        if ( false === strpos( $psrc, $attendu ) ) {
            $hits[] = sprintf(
                'La préparation des quiz ne consulte plus %s : le quiz correspondant sera préparé et rattaché aux apprenants même si la formation l’a désactivé.',
                $attendu
            );
        }
    }
    if ( false === strpos( $psrc, 'acdc_qz_purpose_is_enabled' ) ) {
        $hits[] = 'La préparation des quiz n’interroge plus l’état des interrupteurs : les trois quiz structurels repartent quoi qu’on décide sur la fiche formation.';
    }
}

if ( $hits ) {
    echo "Interrupteurs décoratifs :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
printf( "0 occurrence — les %d automatismes Qualiopi commandent tous quelque chose.\n", count( $interrupteurs ) );
exit( 0 );
