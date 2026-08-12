<?php
/**
 * Tout e-mail doit passer par la porte commune — c'est elle qui applique le
 * gabarit de marque ET le contrôle du mode recette. Un envoi qui appelle
 * wp_mail() directement échappe aux deux.
 *
 * Ce balayage liste les envois directs restants, en excluant les deux endroits
 * légitimes : la porte elle-même, et les campagnes marketing, qui ont leur
 * propre mise en page voulue.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';
$exempt = array(
    'kernel/class-acdc-kernel-core-trait.php'        => 'la porte commune elle-même',
    'marketing/class-acdc-marketing-actions-trait.php' => 'campagnes : mise en page propre',
    'marketing/class-acdc-marketing-core-trait.php'    => 'campagnes : mise en page propre',
);
$rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits = array();
foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }
    $rel = preg_replace( '#^.*/includes/#', '', $path );
    $src = file_get_contents( $path );
    foreach ( explode( "\n", $src ) as $i => $line ) {
        $trimmed = ltrim( $line );
        /* Une mention dans un commentaire n'est pas un envoi. */
        if ( '' === $trimmed || 0 === strpos( $trimmed, '*' ) || 0 === strpos( $trimmed, '//' ) || 0 === strpos( $trimmed, '/*' ) ) { continue; }
        if ( ! preg_match( '/(?<!function )\bwp_mail\s*\(/', $line ) ) { continue; }
        if ( isset( $exempt[ $rel ] ) ) { continue; }
        $hits[] = $rel . ':' . ( $i + 1 );
    }
}
sort( $hits );
echo "envois qui contournent la porte commune : " . count( $hits ) . "\n";
foreach ( $hits as $h ) { echo '   ' . $h . "\n"; }
