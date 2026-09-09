<?php
/**
 * Un bouton branché sur une fonction qui n'existe pas.
 *
 * WordPress accepte qu'on lui déclare un gestionnaire sans vérifier qu'il
 * existe : `add_action( 'admin_post_x', array( $this, 'handle_x' ) )` s'écrit
 * aussi bien pour une méthode présente que pour une méthode jamais écrite. La
 * faute ne se voit ni à l'installation, ni au chargement, ni à la relecture —
 * elle se voit le jour où quelqu'un clique.
 *
 * DEUX CAS TROUVÉS EN 3.25.278, tous deux invisibles depuis des mois.
 *
 * Le premier était un bouton : « Dupliquer » sur une session de questionnaire.
 * Cliquer n'ouvrait rien, ne dupliquait rien, n'affichait aucune erreur.
 *
 * Le second était plus grave, parce qu'il n'attendait personne pour se
 * déclencher : un rendez-vous quotidien, `acdc_of_positioning_test_cron`,
 * programmé depuis des mois vers une fonction inexistante. Deux conséquences.
 * L'automatisme qu'il devait rendre n'a jamais rendu — l'interrupteur « Test de
 * positionnement » des fiches formation ne commandait donc rien. Et surtout,
 * chaque passage interrompait l'exécution des tâches planifiées : tout ce qui
 * était programmé DERRIÈRE lui ne tournait pas. Une panne qui ne se voit pas de
 * l'intérieur, puisque ce qui passe avant fonctionne, et que ce qui passe après
 * ne se plaint jamais.
 *
 * Ce balayage relit toutes les déclarations de gestionnaires du plugin et exige
 * que la méthode désignée existe quelque part dans le code.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

$rii     = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$sources = array();

foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }
    $sources[ $path ] = (string) file_get_contents( $path );
}

/* Toutes les méthodes déclarées, tous fichiers confondus : les traits sont
   composés dans une seule classe, une méthode déclarée dans l'un est appelable
   depuis l'autre. */
$methodes = array();
foreach ( $sources as $src ) {
    if ( preg_match_all( '/function\s+([a-zA-Z_0-9]+)\s*\(/', $src, $m ) ) {
        foreach ( $m[1] as $nom ) { $methodes[ $nom ] = true; }
    }
}

$hits = array();
foreach ( $sources as $path => $src ) {
    $rel = preg_replace( '#^.*/(includes|src)/#', '$1/', $path );
    $motif = "/add_(action|filter)\(\s*'([^']+)'\s*,\s*array\(\s*\\\$this\s*,\s*'([a-zA-Z_0-9]+)'/";
    if ( ! preg_match_all( $motif, $src, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) { continue; }
    foreach ( $m as $trouve ) {
        $hook    = $trouve[2][0];
        $methode = $trouve[3][0];
        if ( isset( $methodes[ $methode ] ) ) { continue; }

        $ligne = substr_count( substr( $src, 0, (int) $trouve[0][1] ), "\n" ) + 1;

        /* Un rendez-vous programmé est plus grave qu'un bouton : il part tout
           seul, et il emporte ce qui suit. On le dit. */
        $planifie = ( false !== strpos( $hook, '_cron' ) );
        $hits[]   = sprintf(
            '%s:%d  %s est branché sur %s(), une méthode qui n’existe nulle part. %s',
            $rel,
            $ligne,
            $hook,
            $methode,
            $planifie
                ? 'C’est un rendez-vous planifié : il se déclenche seul, l’exécution s’arrête là, et TOUT CE QUI EST PROGRAMMÉ DERRIÈRE ne tourne pas.'
                : 'Le bouton correspondant ne fera rien, sans message ni trace.'
        );
    }
}

if ( $hits ) {
    echo "Gestionnaires déclarés mais absents :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
echo "0 occurrence — chaque gestionnaire déclaré existe vraiment.\n";
exit( 0 );
