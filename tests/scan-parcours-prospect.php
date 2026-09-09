<?php
/**
 * Le parcours d'un prospect ne doit se perdre ni en route, ni à l'écran.
 *
 * Deux pannes constatées le même jour, sur le même écran de suivi commercial.
 *
 * 1. LE DEVIS PERDAIT SON PROSPECT. Le champ source_prospect_id n'était rempli
 *    que si l'URL portait ?prospect_id=, ce qui n'est pas le cas quand on
 *    ouvre le devis depuis une proposition commerciale. L'écran connaissait
 *    pourtant le prospect — il venait de s'en servir pour préremplir l'adresse.
 *    Sans ce lien, aucun devis envoyé ne fait avancer le prospect, et aucune
 *    signature ne le fait passer à « Converti ».
 *
 * 2. LES PASTILLES ET LE FILTRE DOIVENT LIRE LA MÊME VÉRITÉ. Les compteurs
 *    comptent les jalons franchis ; si le filtre, lui, compare au seul statut
 *    courant, une pastille annonce « 1 » et le clic n'affiche rien. C'est la
 *    famille de pannes la plus fréquente de ce plugin : deux listes de la même
 *    vérité qui divergent.
 */
$root = $argv[1] ?? 'acdc-formation-saas-organisme-de-formation';

$rii      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
$fichiers = array();
foreach ( $rii as $f ) {
    if ( $f->isDir() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    if ( false !== strpos( $f->getPathname(), '/vendor/' ) ) { continue; }
    $fichiers[ $f->getPathname() ] = (string) file_get_contents( $f->getPathname() );
}

$hits = array();

/* ── 1. Le devis garde-t-il son prospect ? ──────────────────────────────── */
$repli_devis = false;
foreach ( $fichiers as $src ) {
    if ( false !== strpos( $src, 'function handle_save_quote' )
      && false !== strpos( $src, 'acdc_prospect_id_from_proposal' ) ) {
        $repli_devis = true;
    }
}
if ( ! $repli_devis ) {
    $hits[] = 'handle_save_quote() ne retrouve plus le prospect depuis la proposition : un devis ouvert depuis une proposition repart sans rattachement, et sa signature ne convertira jamais le prospect.';
}

/* ── 2. Compteurs et filtre lisent-ils la même vérité ? ─────────────────── */
$compteurs_jalons = false;
$ligne_jalons     = false;
$filtre_jalons    = false;
foreach ( $fichiers as $path => $src ) {
    if ( false === strpos( $src, 'data-acdc-fu-status-tabs' ) ) { continue; }
    $compteurs_jalons = ( false !== strpos( $src, 'get_prospect_milestones' ) );
    $ligne_jalons     = ( false !== strpos( $src, 'data-fu-jalons=' ) );
    /* Le filtre doit chercher le jalon dans la liste de la ligne, et non
       comparer le seul statut courant. */
    $filtre_jalons    = ( false !== strpos( $src, "getAttribute('data-fu-jalons')" ) )
                     && ( false === strpos( $src, "r.getAttribute('data-fu-status')===filter" ) );
}
if ( ! $compteurs_jalons ) {
    $hits[] = 'Le suivi commercial ne compte plus les jalons franchis : les pastilles retombent sur le seul statut courant, et « Recueil envoyé » repasse à 0 dès que le prospect avance.';
}
if ( ! $ligne_jalons ) {
    $hits[] = 'Les lignes du suivi commercial ne portent plus leurs jalons (data-fu-jalons) : le filtre ne peut plus retrouver ce que les pastilles annoncent.';
}
if ( ! $filtre_jalons ) {
    $hits[] = 'Le filtre du suivi commercial compare au statut courant au lieu des jalons : une pastille annoncera des lignes que le clic ne montrera pas.';
}

if ( $hits ) {
    echo "Parcours prospect — anomalies :\n";
    foreach ( array_unique( $hits ) as $h ) { echo '  ' . $h . "\n"; }
    printf( "%d anomalie(s)\n", count( array_unique( $hits ) ) );
    exit( 1 );
}
echo "0 anomalie — le devis garde son prospect, et les pastilles disent ce que le filtre montre.\n";
exit( 0 );
