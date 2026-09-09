<?php
/**
 * Deux réglages de TVA dans le profil, aucun lu, et « 20 » écrit en dur.
 *
 * Le devis affichait 20 % non pas parce qu'il lisait le profil, mais parce que
 * 20 était codé à huit endroits. David facturera d'abord avec TVA, puis
 * demandera l'exonération au titre de la formation professionnelle continue :
 * ce jour-là, il changerait le réglage et rien ne bougerait.
 *
 * Ces cas fixent trois choses :
 *   — la liste complète des régimes, chacun avec la mention que la loi impose ;
 *   — que l'exonération 261-4-4°a et la franchise 293 B ne se confondent pas ;
 *   — QUE LE PASSÉ NE SE RÉÉCRIT PAS : une facture émise à 20 % garde son taux
 *     et sa mention le jour où l'organisme change de régime.
 */
define( 'ACDC_SUPPORT_TESTING', true );
require_once __DIR__ . '/../acdc-formation-saas-organisme-de-formation/src/Support/VatRegime.php';

use ACDC\Support\VatRegime as V;

$echecs = array();
function verifie( $titre, $attendu, $obtenu ) {
    global $echecs;
    $ok = ( $attendu === $obtenu );
    printf( "  %-58s %s\n", $titre, $ok ? 'oui' : '>>> NON — ' . var_export( $obtenu, true ) );
    if ( ! $ok ) { $echecs[] = $titre; }
}

/* --- La liste : aucune porte fermée --- */
$options = V::options();
verifie( 'sept régimes proposés', 7, count( $options ) );
foreach ( array( 'tva_20', 'tva_10', 'tva_5_5', 'tva_2_1', 'autoliquidation', 'exoneration_261', 'franchise_293b' ) as $cle ) {
    verifie( '  « ' . $cle .' » existe', true, V::existe( $cle ) );
}

/* --- Les taux --- */
verifie( 'taux normal', 20.0, V::taux( 'tva_20' ) );
verifie( 'taux intermédiaire', 10.0, V::taux( 'tva_10' ) );
verifie( 'taux réduit', 5.5, V::taux( 'tva_5_5' ) );
verifie( 'taux particulier', 2.1, V::taux( 'tva_2_1' ) );

/* --- LES DEUX EXONÉRATIONS NE SE CONFONDENT PAS --- */
verifie( 'exonération OF : la mention cite le 261-4-4°a',
    'Exonération de TVA — article 261-4-4°a du CGI', V::mention( 'exoneration_261' ) );
verifie( 'franchise : la mention cite le 293 B',
    'TVA non applicable, article 293 B du CGI', V::mention( 'franchise_293b' ) );
verifie( 'les deux mentions diffèrent', true, V::mention( 'exoneration_261' ) !== V::mention( 'franchise_293b' ) );
verifie( 'autoliquidation : sa mention à elle',
    'Autoliquidation — article 283-2 du CGI', V::mention( 'autoliquidation' ) );
verifie( 'un taux normal n’impose aucune mention', '', V::mention( 'tva_20' ) );

/* --- Aucun de ces trois ne fait apparaître de ligne de TVA --- */
foreach ( array( 'autoliquidation', 'exoneration_261', 'franchise_293b' ) as $cle ) {
    verifie( '  « ' . $cle . ' » : pas de ligne de TVA', false, V::avecTva( $cle ) );
}
verifie( 'un taux normal fait apparaître la TVA', true, V::avecTva( 'tva_20' ) );

/* --- Un régime inconnu ne rend jamais rien --- */
verifie( 'régime inconnu : on retombe sur le défaut', 'tva_20', V::get( 'nimporte_quoi' )['cle'] );
verifie( 'régime vide : on retombe sur le défaut', 20.0, V::taux( '' ) );

/* --- LE PASSÉ NE SE RÉÉCRIT PAS --- */
verifie( 'facture émise à 20 %, organisme passé en exonération : elle garde 20 %',
    20.0, (float) V::pourDocument( 'tva_20', 'exoneration_261' )['taux'] );
verifie( '  et elle garde son absence de mention',
    '', (string) V::pourDocument( 'tva_20', 'exoneration_261' )['mention'] );
verifie( 'document neuf : il prend le régime du profil',
    'Exonération de TVA — article 261-4-4°a du CGI',
    (string) V::pourDocument( '', 'exoneration_261' )['mention'] );
verifie( 'régime figé devenu inconnu : le profil reprend la main',
    'exoneration_261', V::pourDocument( 'regime_supprime', 'exoneration_261' )['cle'] );

/* --- LA REPRISE DES DOCUMENTS ANTÉRIEURS ---
   Ils ne portent qu'un nombre. On n'inscrit un régime que lorsque le nombre
   n'en désigne qu'un seul. */
verifie( 'un document à 20 % : régime certain', 'tva_20', V::parTaux( '20.00' ) );
verifie( 'écrit « 20,00 » : même régime', 'tva_20', V::parTaux( '20,00' ) );
verifie( 'un document à 5,5 %', 'tva_5_5', V::parTaux( 5.5 ) );
verifie( 'un document à 2,1 %', 'tva_2_1', V::parTaux( '2,10' ) );
verifie( 'UN DOCUMENT À 0 % : ON NE DEVINE PAS', '', V::parTaux( '0,00' ) );
verifie( '  ni pour un champ vide', '', V::parTaux( '' ) );
verifie( 'un taux qui ne correspond à rien : aucun régime', '', V::parTaux( '17,50' ) );

/* --- Le calcul, arrondi une seule fois --- */
$c = V::calculer( 2400, 'tva_20' );
verifie( 'HT 2400 à 20 % : TVA', 480.0, $c['tva'] );
verifie( 'HT 2400 à 20 % : TTC', 2880.0, $c['ttc'] );
$e = V::calculer( 2400, 'exoneration_261' );
verifie( 'exonéré : aucune TVA', 0.0, $e['tva'] );
verifie( 'exonéré : TTC égal au HT', 2400.0, $e['ttc'] );
verifie( 'exonéré : la mention voyage avec le calcul',
    'Exonération de TVA — article 261-4-4°a du CGI', $e['mention'] );
$r = V::calculer( 833.33, 'tva_5_5' );
verifie( 'arrondi au centime, une seule fois', 45.83, $r['tva'] );
verifie( '  et le total suit', 879.16, $r['ttc'] );

echo "\n";
if ( $echecs ) {
    printf( "%d cas en échec : %s\n", count( $echecs ), implode( ' | ', $echecs ) );
    exit( 1 );
}
echo "Régimes de TVA : la liste est complète, les mentions ne se confondent pas, le passé ne se réécrit pas.\n";
exit( 0 );
