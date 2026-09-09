<?php
/**
 * Un parcours s'ancre à un recueil OU à une convention. Jamais au seul recueil.
 *
 * Le moteur le savait depuis la 3.25.226 : un parcours peut naître d'une
 * convention signée, sans recueil des besoins, et il fabrique alors une ancre
 * minimale à partir des colonnes du parcours. Les GESTIONNAIRES d'étapes, eux,
 * ne l'avaient jamais appris : leur contexte interrogeait la table des recueils
 * et rendait null quand il n'y en avait pas.
 *
 * Conséquence relevée en recette, et elle est spectaculaire : la planification
 * fonctionnait — les étapes s'affichaient dans le journal — mais TOUTES celles
 * qui doivent envoyer quelque chose échouaient sur « Dossier introuvable au
 * moment de l'envoi ». Le dossier du formateur, les trois ouvertures
 * d'extranet apprenant et la convocation, sur un dossier parfaitement en règle.
 *
 * Deux endroits calculaient la même chose, un seul le savait. Ce balayage
 * vérifie qu'ils n'en font toujours qu'un.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

$rii      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits     = array();
$fonction = false;

foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }
    if ( false === strpos( $path, '/workflow/' ) ) { continue; }

    $src   = (string) file_get_contents( $path );
    $rel   = preg_replace( '#^.*/includes/#', '', $path );
    $lines = explode( "\n", $src );

    if ( false !== strpos( $src, 'function acdc_wf_need_for_run' ) ) {
        $fonction = true;
    }

    foreach ( $lines as $i => $line ) {
        $t = ltrim( $line );
        if ( 0 === strpos( $t, '*' ) || 0 === strpos( $t, '//' ) || 0 === strpos( $t, '/*' ) ) { continue; }
        /* Ce qui est visé, c'est le recueil lu À PARTIR DU PARCOURS — celui
           qui sert d'ancre. Lire un recueil qu'on vient de recevoir, pour
           créer le parcours, est un autre geste : il n'y a rien à retomber. */
        if ( preg_match( '/FROM\s+\{\$this->need_table\}\s+WHERE\s+id\s*=\s*%d/i', $line ) ) {
            $voisinage = implode( "\n", array_slice( $lines, max( 0, $i - 3 ), 7 ) );
            if ( false === strpos( $voisinage, '$run->need_id' ) ) {
                continue;
            }
            /* La fonction commune a le droit de le faire — c'est son travail. */
            $avant = implode( "\n", array_slice( $lines, max( 0, $i - 20 ), 20 ) );
            if ( false !== strpos( $avant, 'function acdc_wf_need_for_run' ) ) {
                continue;
            }
            $hits[] = sprintf( '%s:%d  ancre du parcours lue hors de acdc_wf_need_for_run() — un parcours né d’une convention la perdra.  %s', $rel, $i + 1, trim( $line ) );
        }
    }
}

if ( ! $fonction ) {
    $hits[] = 'acdc_wf_need_for_run() a disparu : moteur et gestionnaires vont de nouveau décider chacun de leur côté de ce qui ancre un parcours.';
}

if ( $hits ) {
    echo "Ancre du parcours — anomalies :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
echo "0 occurrence — moteur et gestionnaires s’ancrent au même endroit.\n";
exit( 0 );
