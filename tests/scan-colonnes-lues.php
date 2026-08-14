<?php
/**
 * On ne peut pas conclure sur une colonne qu'on n'a pas chargée.
 *
 * L'extranet apprenant chargeait les inscriptions ainsi :
 *
 *     SELECT id, learner_id, learner_ids, formation_id, updated_at FROM …
 *
 * puis testait $registration->convocation_document_url,
 * $registration->evaluation_result_document_url, et une dizaine d'autres. Ces
 * propriétés n'existaient pas sur l'objet : elles étaient vides, toujours. Le
 * portail annonçait donc « Bientôt disponible » sur des pièces déjà produites,
 * et affichait zéro partout — à l'apprenant, qui n'a aucun moyen de savoir que
 * le document existe.
 *
 * C'est la forme la plus pure de la panne que ce plugin combat : un écran qui
 * affirme sans avoir lu. Et elle est invisible en PHP — lire une propriété
 * absente ne lève rien, elle vaut null.
 *
 * Ce balayage vérifie que les requêtes qui alimentent le portail apprenant ne
 * sélectionnent pas une liste fermée de colonnes sur la table des inscriptions.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

$rii  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits = array();

foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }
    /* Le portail apprenant : c'est lui qui lit des documents sur ces objets. */
    if ( false === strpos( $path, '/learner-portal/' ) ) { continue; }

    $rel   = preg_replace( '#^.*/includes/#', '', $path );
    $lines = explode( "\n", (string) file_get_contents( $path ) );

    foreach ( $lines as $i => $line ) {
        $t = ltrim( $line );
        if ( 0 === strpos( $t, '*' ) || 0 === strpos( $t, '//' ) || 0 === strpos( $t, '/*' ) ) { continue; }
        if ( false === strpos( $line, 'training_registration_table' ) ) { continue; }
        if ( false === stripos( $line, 'SELECT' ) ) { continue; }

        /* Une liste fermée de colonnes sur les inscriptions : le portail lira
           ensuite des documents qui n'y sont pas. */
        if ( preg_match( '/SELECT\s+(?!\*)([a-z_,\s]+)\s+FROM\s+\{\$this->training_registration_table\}/i', $line, $m ) ) {
            $colonnes = preg_replace( '/\s+/', ' ', trim( $m[1] ) );
            $hits[] = sprintf(
                '%s:%d  liste fermée de colonnes sur les inscriptions (%s) — tout document lu ensuite sera vide.  %s',
                $rel, $i + 1, $colonnes, trim( $line )
            );
        }
    }
}

if ( $hits ) {
    echo "Colonnes non chargées mais interrogées :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
echo "0 occurrence — le portail apprenant charge les inscriptions en entier avant de conclure.\n";
exit( 0 );
