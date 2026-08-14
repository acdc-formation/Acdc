<?php
/**
 * Un filtre qui ne filtre pas est pire qu'un filtre absent.
 *
 * Relevé en recette, en même temps que les statistiques : « je pense qu'il y a
 * pas mal de liens qui ne se font pas ». Sur les cinq écrans de statistiques,
 * soixante et un champs de filtre et de recherche n'avaient aucun attribut
 * `name` — périodes, formations, commanditaires, types d'enquête, barres
 * « Rechercher », sélecteurs « Depuis le début ». Sans nom, un champ n'envoie
 * rien : ils ne pouvaient rien filtrer, ils n'avaient jamais rien filtré.
 *
 * C'est la même famille que tout ce qu'on corrige depuis des semaines. Un écran
 * vide dit « il n'y a rien » et on va vérifier. Un écran meublé de filtres dit
 * « voici le résultat de votre sélection » — et l'on croit avoir cherché.
 * L'utilisateur ne peut pas distinguer un filtre inopérant d'un filtre qui ne
 * ramène rien.
 *
 * Ce balayage refuse qu'un champ sans nom réapparaisse dans un bloc de filtres
 * ou une barre de recherche.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

$rii  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits = array();

foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }

    $src   = (string) file_get_contents( $path );
    $rel   = preg_replace( '#^.*/includes/#', '', $path );
    $lines = explode( "\n", $src );

    foreach ( $lines as $i => $line ) {
        $t = ltrim( $line );
        if ( 0 === strpos( $t, '*' ) || 0 === strpos( $t, '//' ) ) { continue; }

        /* Un contexte de filtre : l'en-tête « Filtres », une grille de filtres,
           une barre de recherche. Le reste du plugin emploie des champs sans
           nom à d'autres fins légitimes — un aperçu de charte graphique, par
           exemple — et n'est pas visé. */
        $fenetre = implode( "\n", array_slice( $lines, max( 0, $i - 6 ), 13 ) );
        $contexte = preg_match( '/filter-(head|grid|box)|>Filtres</', $fenetre )
            || preg_match( '/searchbar|acdc-perf-search|type="search"/', $line );
        if ( ! $contexte ) { continue; }

        foreach ( array( '<input', '<select' ) as $balise ) {
            $offset = 0;
            while ( false !== ( $pos = strpos( $line, $balise, $offset ) ) ) {
                $fin  = strpos( $line, '>', $pos );
                $offset = false === $fin ? strlen( $line ) : $fin + 1;
                $champ = false === $fin ? substr( $line, $pos ) : substr( $line, $pos, $fin - $pos + 1 );
                if ( false !== strpos( $champ, 'name=' ) ) { continue; }
                /* Un champ construit en PHP porte son nom ailleurs : on ne
                   prétend pas lire ce qui n'est pas écrit sur la ligne. */
                if ( false !== strpos( $champ, '<?php' ) ) { continue; }
                /* UN CHAMP PEUT FILTRER SANS FORMULAIRE. Plusieurs écrans
                   filtrent leur tableau en JavaScript, sur place : le champ n'a
                   pas de `name`, il a un `id` que le script lit. Refuser cette
                   forme reviendrait à réclamer un rechargement de page là où le
                   filtrage instantané fonctionne déjà. On vérifie donc que
                   l'identifiant est repris quelque part dans le fichier — sinon
                   il ne mène nulle part, et c'est bien un décor. */
                if ( preg_match( '/id="([^"]+)"/', $champ, $m_id ) ) {
                    if ( substr_count( $src, $m_id[1] ) > 1 ) { continue; }
                }
                $hits[] = sprintf(
                    '%s:%d  champ de filtre sans nom : il n’envoie rien, il ne filtre rien, et l’écran laisse croire le contraire.  %s',
                    $rel,
                    $i + 1,
                    trim( mb_substr( $champ, 0, 90 ) )
                );
            }
        }
    }
}

if ( $hits ) {
    echo "Filtres décoratifs :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
echo "0 occurrence — tout champ de filtre affiché sait où envoyer sa valeur.\n";
exit( 0 );
