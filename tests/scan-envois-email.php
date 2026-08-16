<?php
/**
 * Tout e-mail doit passer par la porte commune — c'est elle qui applique le
 * gabarit de marque ET le contrôle du mode recette. Un envoi qui appelle
 * wp_mail() directement échappe aux deux.
 *
 * CE BALAYAGE LISTAIT, ET NE REFUSAIT RIEN. Il affichait un compte et sortait
 * toujours en réussite : un envoi direct ajouté demain serait passé sans un mot.
 * Et sa liste d'exemptions avait dérivé — « campagnes marketing, qui ont leur
 * propre mise en page » couvrait en réalité UN renvoi d'e-mail archivé, qui
 * n'est pas une campagne et n'avait pas de mise en page à préserver. Recette
 * cochée, un clic sur « Renvoyer » repartait pour de bon vers le destinataire
 * d'origine.
 *
 * Depuis la 3.25.246 la porte accepte un HTML déjà composé : plus aucune mise en
 * page ne justifie de la contourner. Il ne reste donc qu'une exemption, la porte
 * elle-même, et toute autre fait échouer le balayage.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';
$exempt = array(
    'kernel/class-acdc-kernel-core-trait.php' => 'la porte commune elle-même',
);
$rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits = array();
foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }
    $rel = preg_replace( '#^.*/includes/#', '', $path );
    $src = file_get_contents( $path );
    /* Une mention dans un commentaire n'est pas un envoi — et un commentaire de
       plusieurs lignes ne se reconnaît pas ligne par ligne : la deuxième ligne
       d'un bloc ne commence ni par « * » ni par « // ». Un contrôle qui l'oublie
       accuse une explication d'être un envoi. On suit donc l'état du bloc. */
    $dans_bloc = false;
    foreach ( explode( "\n", $src ) as $i => $line ) {
        $trimmed = ltrim( $line );
        $ouvre = strrpos( $line, '/*' );
        $ferme = strrpos( $line, '*/' );
        $etait_dans_bloc = $dans_bloc;
        if ( false !== $ouvre && ( false === $ferme || $ferme < $ouvre ) ) { $dans_bloc = true; }
        elseif ( false !== $ferme && ( false === $ouvre || $ouvre < $ferme ) ) { $dans_bloc = false; }
        if ( $etait_dans_bloc || $dans_bloc ) { continue; }
        if ( '' === $trimmed || 0 === strpos( $trimmed, '*' ) || 0 === strpos( $trimmed, '//' ) ) { continue; }
        if ( ! preg_match( '/(?<!function )\bwp_mail\s*\(/', $line ) ) { continue; }
        if ( isset( $exempt[ $rel ] ) ) { continue; }
        $hits[] = $rel . ':' . ( $i + 1 );
    }
}
sort( $hits );
if ( $hits ) {
    foreach ( $hits as $h ) {
        echo 'ALERTE  ' . $h . " appelle wp_mail() directement : cet envoi échappe au gabarit de marque ET au mode recette — il partira même quand la recette est censée retenir le courrier.\n";
    }
    printf( "%d envoi(s) contournent la porte commune\n", count( $hits ) );
    exit( 1 );
}
echo "Tout e-mail passe par la porte commune : gabarit et mode recette s'appliquent partout.\n";
exit( 0 );
