<?php
/**
 * Une fonction qui protège un dossier ET son parent ne doit JAMAIS pouvoir
 * remonter jusqu'à la racine des téléversements : y écrire « deny from all »
 * rend 403 toute la médiathèque du site (images, logos d'e-mails, pages HTML
 * des propositions). C'est exactement ce qui s'est produit en 3.25.225.
 */
$BASE = '/var/www/wp-content/uploads/';

/** La règle telle qu'elle est écrite dans le plugin (3.25.240). */
function parent_a_proteger( $dir_path, $base_dir ) {
    $root = rtrim( dirname( rtrim( $dir_path, '/' ) ), '/' ) . '/';
    if ( '' !== $base_dir && ( $root === $base_dir || strlen( $root ) <= strlen( $base_dir ) ) ) {
        return null;                       // on ne remonte pas plus haut
    }
    return $root;
}

$cas = array(
  // dossier confié                                   parent attendu
  array( $BASE.'acdc-of-contracts/42/',  $BASE.'acdc-of-contracts/', 'sous-dossier de contrat : le parent est légitime' ),
  array( $BASE.'acdc-of-contracts/7/',   $BASE.'acdc-of-contracts/', 'autre sous-dossier de contrat' ),
  array( $BASE.'acdc-certificates/',     null,                       'attestations : le parent serait la racine — refusé' ),
  array( $BASE.'acdc-signatures/',       null,                       'signatures : idem' ),
  array( $BASE,                          null,                       'la racine elle-même — refusé' ),
  array( $BASE.'a/b/c/',                 $BASE.'a/b/',               'dossier profond : parent légitime' ),
);
$ko = 0;
foreach ( $cas as $c ) {
  $got = parent_a_proteger( $c[0], $BASE );
  if ( $got !== $c[1] ) {
    $ko++;
    printf( "ÉCHEC  %s\n       attendu %s\n       obtenu  %s\n", $c[2], var_export($c[1],true), var_export($got,true) );
  }
}
printf( "%d cas, %d échec(s)\n", count($cas), $ko );
exit( $ko === 0 ? 0 : 1 );
