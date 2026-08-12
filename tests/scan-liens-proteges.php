<?php
/**
 * Certains dossiers d'uploads sont volontairement interdits d'accès direct :
 * les contrats formateurs (nom, e-mail et SIRET), les pièces d'identité de
 * signature, la bibliothèque formateur. Leurs fichiers ne doivent être servis
 * que par une route PHP qui vérifie l'identité ET l'appartenance.
 *
 * Ce balayage cherche les liens qui pointent DIRECTEMENT l'adresse stockée de
 * ces fichiers. Le symptôme, côté utilisateur, est une page 403 — c'est ce qui
 * s'est produit sur « Télécharger mon exemplaire signé » du portail formateur.
 *
 * Limite assumée : la détection est textuelle. Elle attrape le motif courant
 * — une colonne d'adresse passée à esc_url() dans un href — et ne prétend pas
 * à l'exhaustivité.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

/* PIÈGE À ÉVITER : deux dossiers aux noms voisins, deux régimes opposés.
   « acdc-of-contracts/ » (contrats FORMATEURS) est interdit d'accès direct ;
   « acdc-contracts/ » (conventions) ne l'est pas. La colonne
   `signed_document_url` existe des deux côtés : la signaler partout produirait
   des faux positifs sur les conventions, qui, elles, se lisent très bien par
   leur adresse.
   On ne surveille donc que ce qui touche AU CONTRAT FORMATEUR : la colonne
   `contract_pdf_url`, qui lui est propre, et tout lien émis par le portail
   formateur. */
$protected_columns = array( 'contract_pdf_url', 'signed_document_url', 'signed_document_path' );

$rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$hits = array();
foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $path = $f->getPathname();
    if ( false !== strpos( $path, '/vendor/' ) ) { continue; }
    /* On ne surveille que les écrans : c'est là que naissent les liens. */
    if ( false === strpos( $path, 'render' ) && false === strpos( $path, 'templates' ) ) { continue; }
    $is_trainer_portal = ( false !== strpos( $path, 'trainer-portal' ) );
    $rel = preg_replace( '#^.*/includes/#', '', $path );
    foreach ( explode( "\n", file_get_contents( $path ) ) as $i => $line ) {
        $t = ltrim( $line );
        if ( '' === $t || 0 === strpos( $t, '*' ) || 0 === strpos( $t, '//' ) || 0 === strpos( $t, '/*' ) ) { continue; }
        if ( false === strpos( $line, 'href' ) && false === strpos( $line, 'esc_url' ) ) { continue; }
        foreach ( $protected_columns as $col ) {
            /* Hors portail formateur, seule `contract_pdf_url` est concluante. */
            if ( ! $is_trainer_portal && 'contract_pdf_url' !== $col ) { continue; }
            if ( preg_match( '/->\s*' . preg_quote( $col, '/' ) . '\b/', $line ) ) {
                $hits[] = $rel . ':' . ( $i + 1 ) . '  (' . $col . ')';
            }
        }
    }
}
sort( $hits );
echo 'liens directs vers un dossier protégé : ' . count( $hits ) . "\n";
foreach ( $hits as $h ) { echo '   ' . $h . "\n"; }
exit( count( $hits ) === 0 ? 0 : 1 );
