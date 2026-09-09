<?php
/**
 * Cherche les fonctions qui utilisent $wpdb sans l'avoir importé :
 * ni "global $wpdb;", ni un paramètre $wpdb, ni une propriété.
 */
$root = $argv[1];
$rii  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits = array();

foreach ( $rii as $file ) {
    if ( $file->isDir() || 'php' !== strtolower( $file->getExtension() ) ) { continue; }
    $path   = $file->getPathname();
    $src    = file_get_contents( $path );
    if ( false === strpos( $src, '$wpdb' ) ) { continue; }
    $tokens = token_get_all( $src );
    $n      = count( $tokens );

    for ( $i = 0; $i < $n; $i++ ) {
        $t = $tokens[ $i ];
        if ( ! is_array( $t ) || T_FUNCTION !== $t[0] ) { continue; }

        // Nom de la fonction (peut être anonyme).
        $name = '(closure)';
        for ( $j = $i + 1; $j < $n; $j++ ) {
            if ( is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) { $name = $tokens[ $j ][1]; break; }
            if ( '(' === $tokens[ $j ] ) { break; }
        }
        $line = $t[2];

        // Signature : de '(' à ')'.
        $k = $i;
        while ( $k < $n && '(' !== $tokens[ $k ] ) { $k++; }
        $depth_p = 0; $sig = '';
        for ( ; $k < $n; $k++ ) {
            $tk = $tokens[ $k ];
            $txt = is_array( $tk ) ? $tk[1] : $tk;
            if ( '(' === $tk ) { $depth_p++; }
            if ( ')' === $tk ) { $depth_p--; }
            $sig .= $txt;
            if ( 0 === $depth_p && ')' === $tk ) { $k++; break; }
        }
        $param_wpdb = ( false !== strpos( $sig, '$wpdb' ) );

        // Corps : de '{' à sa fermeture. Une déclaration abstraite finit par ';'.
        while ( $k < $n && '{' !== $tokens[ $k ] && ';' !== $tokens[ $k ] ) { $k++; }
        if ( $k >= $n || ';' === $tokens[ $k ] ) { continue; }
        $depth = 0; $body = ''; $uses_at = 0;
        for ( ; $k < $n; $k++ ) {
            $tk = $tokens[ $k ];
            $txt = is_array( $tk ) ? $tk[1] : $tk;
            if ( '{' === $tk ) { $depth++; }
            if ( is_array( $tk ) && in_array( $tk[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) { $depth++; }
            if ( '}' === $tk ) { $depth--; }
            $body .= $txt;
            if ( is_array( $tk ) && T_VARIABLE === $tk[0] && '$wpdb' === $tk[1] && 0 === $uses_at ) { $uses_at = $tk[2]; }
            if ( 0 === $depth ) { break; }
        }

        if ( ! $uses_at ) { continue; }
        if ( $param_wpdb ) { continue; }
        if ( preg_match( '/\bglobal\s+[^;]*\$wpdb/', $body ) ) { continue; }
        if ( preg_match( '/\buse\s*\([^)]*\$wpdb/', $body ) ) { continue; }

        $hits[] = sprintf( '%s:%d  %s()  — premier usage ligne %d', str_replace( $root . '/', '', $path ), $line, $name, $uses_at );
    }
}

sort( $hits );
echo implode( "\n", $hits ) . "\n";
echo count( $hits ) . " occurrence(s)\n";
