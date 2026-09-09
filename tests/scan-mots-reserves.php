<?php
/**
 * Un écran n'emploie jamais un mot que WordPress s'est réservé.
 *
 * Relevé en recette : « le calendrier s'arrête en décembre 2026 ». Il n'avait
 * aucune borne. La flèche « mois suivant » fabriquait simplement
 * `?tab=sessions_calendar&month=1&year=2027`, et `year` est le paramètre des
 * archives par date de WordPress : l'adresse ne demandait plus la page du
 * tableau de bord, elle demandait « la page publiée en 2027 ». La page datant
 * de 2026, la requête principale ne trouvait plus rien.
 *
 * Ce défaut a trois propriétés qui le rendent redoutable, et qui justifient un
 * balayage plutôt qu'une correction ponctuelle :
 *
 *   — il est INVISIBLE à la relecture : le code est correct, c'est le mot qui
 *     est confisqué ;
 *   — il ne se manifeste qu'en PRODUCTION, sur une vraie page WordPress ;
 *   — il attend : `year` a cassé au premier clic sortant de l'année en cours,
 *     `paged` casse à la deuxième page d'une liste, `s` transformerait une
 *     recherche interne en recherche du site.
 *
 * Un an peut passer entre l'écriture et la panne. C'est exactement le genre de
 * faute qu'aucune relecture n'attrape et qu'un balayage attrape toujours.
 *
 * Ne sont visées que les adresses du FRONT — celles construites sur une page
 * WordPress. Dans l'administration, ces mots ne détournent rien, et `page` y
 * est même obligatoire.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

/* La liste vit dans ScreenQuery : une seule source, sinon les deux dérivent. */
$support = $root . '/src/Support/ScreenQuery.php';
if ( ! is_readable( $support ) ) {
    echo "ScreenQuery.php introuvable : la liste des mots réservés n'existe plus.\n";
    exit( 1 );
}
if ( ! defined( 'ACDC_SUPPORT_TESTING' ) ) { define( 'ACDC_SUPPORT_TESTING', true ); }
require_once $support;
$reserves = \ACDC\Support\ScreenQuery::RESERVES;

/* `page` et `post_type` sont la monnaie courante de wp-admin, et `title`,
   `name`, `order` sont des noms de colonnes qu'on retrouve dans mille
   tableaux qui ne sont pas des URL. On ne garde que les mots qui détournent
   vraiment une page du front et qu'un écran pourrait employer sans y penser. */
$vises = array_values( array_intersect(
    $reserves,
    array( 'year', 'monthnum', 'day', 'hour', 'm', 'w', 's', 'p', 'cat', 'tag', 'author', 'paged', 'attachment', 'feed', 'preview', 'embed' )
) );

$rii  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits = array();

foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }
    if ( false !== strpos( $path, '/src/Support/' ) ) { continue; }

    $src   = (string) file_get_contents( $path );
    $rel   = preg_replace( '#^.*/(includes|src)/#', '$1/', $path );
    $lines = explode( "\n", $src );

    foreach ( $lines as $i => $line ) {
        $t = ltrim( $line );
        if ( 0 === strpos( $t, '*' ) || 0 === strpos( $t, '//' ) || 0 === strpos( $t, '/*' ) ) { continue; }

        /* Une adresse du front, c'est portal_page_url() ou un add_query_arg()
           posé sur une base qui n'est pas admin_url().
           On regarde un VOISINAGE, pas la seule ligne : le tableau des
           paramètres est souvent construit deux ou trois lignes avant l'appel
           qui le consomme. Se limiter à la ligne laissait passer exactement
           cette forme-là — et un balayage qui ne voit pas le défaut qu'il
           prétend garder est pire qu'aucun balayage. */
        $fenetre = implode( "\n", array_slice( $lines, max( 0, $i - 4 ), 9 ) );
        $front = false !== strpos( $fenetre, 'portal_page_url(' )
            || ( false !== strpos( $fenetre, 'add_query_arg(' ) && false === strpos( $fenetre, 'admin_url(' ) );
        if ( ! $front ) { continue; }

        foreach ( $vises as $mot ) {
            if ( ! preg_match( "/'" . preg_quote( $mot, '/' ) . "'\s*=>/", $line ) ) { continue; }
            $hits[] = sprintf(
                '%s:%d  « %s » est un paramètre réservé de WordPress : posé sur l’adresse d’une page, il détourne la requête principale et l’écran disparaît.  %s',
                $rel,
                $i + 1,
                $mot,
                trim( mb_substr( trim( $line ), 0, 120 ) )
            );
        }
    }
}

if ( $hits ) {
    echo "Mots réservés de WordPress dans une adresse d’écran :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
printf( "0 occurrence — aucune adresse d’écran n’emploie l’un des %d mots réservés surveillés.\n", count( $vises ) );
exit( 0 );
