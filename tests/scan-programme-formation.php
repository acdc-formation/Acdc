<?php
/**
 * `program_file_url` ne se lit pas en direct.
 *
 * Cette colonne peut contenir une adresse héritée de l'ancien plugin Manager,
 * qui porte un NONCE — un jeton valable vingt-quatre heures et lié à
 * l'utilisateur qui l'a créé. Rangée dans une donnée, une telle adresse est
 * morte dès le lendemain. Les écrans qui l'affichaient telle quelle
 * proposaient donc un lien qui répond « Lien invalide » : la liste des
 * programmes, la fiche formation, le catalogue public, et surtout l'e-mail de
 * convention envoyé au commanditaire.
 *
 * Un seul écran le savait — le portail apprenant, qui écartait ces adresses
 * dans son coin. C'est la forme même du défaut : une connaissance détenue par
 * un écran et ignorée des autres.
 *
 * La règle vit maintenant dans le noyau. Ce balayage vérifie que personne ne
 * la contourne :
 *
 *   acdc_formation_programme_url()   pour afficher un lien
 *   acdc_formation_programme_file()  pour joindre un fichier
 *
 * Sont tolérés : la définition du schéma, l'enregistrement du formulaire (qui
 * écrit la colonne), le résolveur lui-même et la purge des adresses mortes.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

/* Fichiers autorisés à toucher la colonne directement, et pourquoi. */
$allowed = array(
    'kernel/class-acdc-kernel-core-trait.php'      => 'schéma, résolveur et purge',
    'kernel/class-acdc-kernel-actions-trait.php'   => 'enregistrement du formulaire formation',
);

$rii  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits = array();
foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }
    $rel = preg_replace( '#^.*/includes/#', '', $path );
    foreach ( $allowed as $ok => $_why ) {
        if ( $rel === $ok ) { continue 2; }
    }

    foreach ( explode( "\n", (string) file_get_contents( $path ) ) as $i => $line ) {
        /* Une lecture de la colonne sur un objet : ->program_file_url */
        if ( ! preg_match( '/->program_file_url\b/', $line ) ) { continue; }
        /* Les commentaires expliquent, ils n'affichent rien. */
        $t = ltrim( $line );
        if ( '' === $t || 0 === strpos( $t, '*' ) || 0 === strpos( $t, '//' ) || 0 === strpos( $t, '/*' ) ) { continue; }
        /* Une requête SQL qui sélectionne la colonne ne l'affiche pas non plus. */
        if ( preg_match( '/\bAS\b|\bSELECT\b/i', $line ) ) { continue; }
        /* Le champ « URL directe du programme » du formulaire formation affiche
           la valeur stockée : c'est son rôle, il l'édite. */
        if ( false !== strpos( $line, 'program_file_url_direct' ) ) { continue; }
        /* Signaler l'absence de programme est légitime : c'est ce que fait
           l'avertissement « l'adresse enregistrée ne mène à aucun fichier ». */
        if ( false !== strpos( $line, 'acdc_formation_programme' ) ) { continue; }
        if ( preg_match( "/elseif \( \\\$formation && ! empty\( \\\$formation->program_file_url \)/", $line ) ) { continue; }

        $hits[] = sprintf( '%s:%d  %s', $rel, $i + 1, trim( $line ) );
    }
}

if ( $hits ) {
    echo "Lecture directe de program_file_url (utiliser le résolveur du noyau) :\n";
    foreach ( $hits as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( $hits ) );
    exit( 1 );
}
echo "0 occurrence — le programme se lit partout par le résolveur.\n";
exit( 0 );
