<?php
/**
 * Une requête invalide ne dit rien : elle rend une liste vide.
 *
 * Relevé en recette : « sur le tableau de bord il y a une session à venir, et
 * rien dans les sessions ». Les deux écrans ne se contredisaient pas sur les
 * données — le second n'en recevait aucune. Sa requête se terminait ainsi :
 *
 *     SELECT s.*, f.title AS formation_title, f.duration AS formation_duration,
 *            c.name AS company_name,
 *     FROM …
 *
 * Une virgule, juste avant le FROM. La base refuse, $wpdb rend un tableau vide,
 * et rien ne le signale : aucune alerte, aucune trace à l'écran.
 *
 * C'EST LE PIRE SYMPTÔME POSSIBLE dans cette application, parce qu'une liste
 * vide est un état parfaitement normal. « Aucune session à venir » ne ressemble
 * pas à une panne : ça ressemble à un agenda libre. La page des sessions du
 * formateur n'a donc jamais rien affiché, pour aucun formateur, depuis qu'elle
 * a été écrite — et personne ne pouvait le deviner de l'intérieur.
 *
 * Ce balayage relit toutes les requêtes du plugin et refuse les fautes de
 * syntaxe qui produisent ce silence : une virgule orpheline avant FROM, avant
 * WHERE, avant ORDER BY ou avant un GROUP BY.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

$rii  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits = array();

/* Les mots-clés qui ne peuvent jamais suivre une virgule. */
$apres = array( 'FROM', 'WHERE', 'ORDER BY', 'GROUP BY', 'LIMIT', 'HAVING', 'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN' );

foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }

    $src   = (string) file_get_contents( $path );
    if ( false === stripos( $src, 'SELECT' ) ) { continue; }
    $rel   = preg_replace( '#^.*/(includes|src|assets)/#', '$1/', $path );
    $lines = explode( "\n", $src );

    foreach ( $lines as $i => $line ) {
        /* Une ligne de requête qui se termine par une virgule. On ignore le
           PHP : une virgule en fin de ligne y est parfaitement normale — c'est
           dans la CHAÎNE SQL qu'elle est fatale. */
        if ( ! preg_match( '/,\s*$/', $line ) ) { continue; }
        if ( ! preg_match( '/\bAS\b|\bSELECT\b|\.\w+\s*,\s*$|\bCOALESCE\b|\bCOUNT\b|\bMAX\b|\bMIN\b|\bSUM\b/i', $line ) ) { continue; }
        /* La ligne ne doit pas se terminer sur du PHP (fin d'argument). */
        if ( preg_match( '/(\)|\'|"|\$\w+)\s*,\s*$/', $line ) ) { continue; }

        /* La ligne suivante non vide. */
        $j = $i + 1;
        while ( $j < count( $lines ) && '' === trim( $lines[ $j ] ) ) { $j++; }
        if ( $j >= count( $lines ) ) { continue; }
        $suivante = ltrim( $lines[ $j ] );
        /* Un commentaire intercalé n'est pas la suite de la requête. */
        if ( 0 === strpos( $suivante, '/*' ) || 0 === strpos( $suivante, '*' ) || 0 === strpos( $suivante, '//' ) ) { continue; }

        foreach ( $apres as $mot ) {
            if ( 0 === stripos( $suivante, $mot ) ) {
                $hits[] = sprintf(
                    '%s:%d  virgule orpheline avant %s : la requête est invalide, la base la refuse, et l’écran affichera une liste vide sans le moindre avertissement.  %s',
                    $rel,
                    $i + 1,
                    $mot,
                    trim( mb_substr( trim( $line ), 0, 90 ) )
                );
                break;
            }
        }
    }
}

if ( $hits ) {
    echo "Requêtes invalides :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d occurrence(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
echo "0 occurrence — aucune requête ne se termine sur une virgule orpheline.\n";
exit( 0 );
