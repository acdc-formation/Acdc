<?php
/**
 * ACDC 3.25.325 — Une proposition annonce le prix qui sera facturé.
 *
 * CE QUE CE BALAYAGE DÉFEND. La page financière de la proposition commerciale
 * libellait TOUT « net de TVA », en dur, quel que soit le régime : montant
 * unitaire, total, en-têtes de colonnes. Le profil de l'organisme est à 20 % —
 * le devis du même dossier facture donc 20 % — et le client lisait sur la
 * proposition un prix inférieur d'un cinquième à celui qu'il paiera.
 *
 * Ce n'est pas une coquille d'affichage : une proposition est un ENGAGEMENT
 * commercial. Annoncer 3 600 € puis facturer 4 320 € est une négociation qui
 * recommence, ou un litige.
 *
 * LA RACINE. La proposition ne portait aucun régime — ni colonne, ni champ à
 * l'écran. En 3.25.309, j'avais branché la seule MENTION légale sur le profil,
 * et laissé les montants tels quels : le document disait le bon article de loi
 * et le mauvais total. Une correction à moitié faite est un défaut qui se
 * cache mieux.
 *
 * LES DATES. « 2026-08-18,2026-08-19 » s'imprimait tel quel sur un document
 * envoyé au client, alors que le reste du plugin met ces dates en forme depuis
 * toujours par acdc_format_seances_list(). Cette page ne l'appelait pas.
 *
 * Éprouvé en sabotant : chaque règle échoue quand on défait la correction.
 */

$racine = dirname( __DIR__ ) . '/acdc-formation-saas-organisme-de-formation';

$lire = function ( $relatif ) use ( $racine ) {
    $chemin = $racine . '/' . $relatif;
    if ( ! is_readable( $chemin ) ) {
        fwrite( STDERR, "Fichier introuvable : $chemin\n" );
        exit( 1 );
    }
    return (string) file_get_contents( $chemin );
};

$echecs = array();
$verifs = 0;
$exiger = function ( $condition, $message ) use ( &$echecs, &$verifs ) {
    $verifs++;
    if ( ! $condition ) {
        $echecs[] = $message;
    }
};

/* --- 1. LES DEUX VERSIONS DU DOCUMENT DISENT LA MÊME CHOSE ---
   Le PDF et la version HTML sont le même document pour le client. Une version
   qui annoncerait un autre total que l'autre serait pire que le défaut. */
/* Le PDF met les dates en forme à DEUX endroits — « Votre projet » et la page
   financière ; la version HTML n'en a qu'un. On compte, sinon corriger un seul
   des deux endroits passerait pour une correction complète. */
$gabarits = array( 'proposal-mpdf.php' => 2, 'proposal-html.php' => 1 );
foreach ( $gabarits as $gabarit => $dates_attendues ) {
    $src = $lire( 'includes/proposals/templates/' . $gabarit );
    $ou  = ' (' . $gabarit . ')';

    $exiger(
        false !== strpos( $src, '$p->vat_regime' ),
        'La page financière ne lit plus le régime de TVA de la proposition' . $ou . ' : les montants redeviennent « net de TVA » quel que soit le régime réel.'
    );
    $exiger(
        false !== strpos( $src, 'acdc_regime_tva_profil' ),
        'Le repli sur le régime du profil a disparu' . $ou . ' : une proposition ancienne, sans régime propre, n’afficherait plus aucune TVA.'
    );
    $exiger(
        (bool) preg_match( '/\$__mt_tva\s*=\s*\$__ht\s*\*\s*\$__taux\s*\/\s*100/', $src ),
        'Le montant de TVA n’est plus calculé' . $ou . '.'
    );
    $exiger(
        (bool) preg_match( '/\$__ttc\s*=\s*\$__ht\s*\+\s*\$__mt_tva/', $src ),
        'Le total TTC n’est plus calculé' . $ou . '.'
    );
    $exiger(
        (bool) preg_match( '/\$__avec_tva\s*=\s*\$__taux\s*>\s*0\.0/', $src )
            && (bool) preg_match( '/if\s*\(\s*\$__avec_tva\s*\)/', $src ),
        'La page financière n’aiguille plus selon que la TVA s’applique ou non' . $ou . ' : un organisme exonéré verrait une ligne de TVA à 0, un organisme assujetti n’en verrait aucune.'
    );
    $exiger(
        substr_count( $src, "'plage'" ) >= $dates_attendues,
        'Les dates de la formation ne sont plus mises en forme partout' . $ou . ' : le client reçoit « 2026-08-18,2026-08-19 » sur au moins une page.'
    );
    /* La mention légale suit le MÊME régime que les montants. Les faire diverger
       imprimerait un total avec TVA sous une mention d'exonération. */
    $exiger(
        (bool) preg_match( "/\\\$acdc_mention_tva\s*=\s*\(string\)\s*\\\$__tva\['mention'\]/", $src ),
        'La mention légale ne suit plus le régime retenu pour les montants' . $ou . ' : le document pourrait porter un total TTC sous une mention d’exonération.'
    );
}

/* --- 2. LE RÉGIME EXISTE, SE SAISIT, ET SE RANGE --- */
$coeur = $lire( 'includes/proposals/class-acdc-proposals-core-trait.php' );
$exiger(
    (bool) preg_match( "/maybe_add_table_column\(\s*\\\$table,\s*'vat_regime'/", $coeur ),
    'La colonne « vat_regime » n’est plus créée sur la table des propositions : le champ existerait à l’écran et ne serait jamais enregistré.'
);

$rendu = $lire( 'includes/proposals/class-acdc-proposals-render-trait.php' );
$exiger(
    false !== strpos( $rendu, 'name="proposal[vat_regime]"' ),
    'Le champ « Régime de TVA » a disparu du formulaire de proposition : c’est l’absence même que signalait la correction 3 du 18 août.'
);
$exiger(
    (bool) preg_match( '/\$__regime_prop\s*=.{0,200}acdc_regime_tva_profil\(\)/s', $rendu ),
    'Le champ n’est plus prérempli avec le régime du profil : chaque proposition repartirait du premier régime de la liste.'
);
$exiger(
    (bool) preg_match( "/'vat_regime'\s*=>\s*\\\$is_edit/", $rendu ),
    'Le régime enregistré n’est plus relu à la réouverture : modifier une proposition la ferait retomber sur le profil.'
);

$actions = $lire( 'includes/proposals/class-acdc-proposals-actions-trait.php' );
$exiger(
    (bool) preg_match( "/VatRegime::existe\(\s*\\\$__regime\s*\)\s*\?\s*\\\$__regime\s*:\s*''/", $actions ),
    'Le régime reçu du formulaire n’est plus validé : une clé inventée ferait imprimer un taux sans mention légale, ou une mention sans fondement.'
);

/* --- 3. LA PLAGE DE DATES NE MENT PAS ---
   Relier deux dates par un tiret annonce une période continue. Sur des séances
   espacées, ce serait annoncer au client des journées qui n'existent pas. */
$exiger(
    (bool) preg_match( '/DAY_IN_SECONDS/', $rendu ),
    'La mise en forme « plage » ne vérifie plus que les dates se suivent : deux séances espacées d’un mois s’annonceraient comme une formation continue.'
);
$exiger(
    (bool) preg_match( "/\\\$consecutives\s*\?\s*\\\$formatted\[0\]\s*\.\s*' – '/", $rendu ),
    'La plage de dates n’est plus composée : les dates reviennent en énumération.'
);

if ( $echecs ) {
    foreach ( $echecs as $e ) {
        fwrite( STDERR, "ÉCHEC — $e\n" );
    }
    fwrite( STDERR, sprintf( "\n%d/%d vérifications passées.\n", $verifs - count( $echecs ), $verifs ) );
    exit( 1 );
}

echo sprintf( "Proposition financière : le prix annoncé est celui qui sera facturé — %d/%d vertes.\n", $verifs, $verifs );
exit( 0 );
