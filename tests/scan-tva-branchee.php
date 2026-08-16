<?php
/**
 * La TVA : un seul réglage, figé sur le document, et jamais écrite en dur.
 *
 * CE QUE CE BALAYAGE EMPÊCHE DE REVENIR. Avant la 3.25.309, le plugin portait
 * QUATRE réglages de TVA — « vat_rate », « vat_rate_default », « vat_regime » et
 * une « Mention TVA 0% » — dont AUCUN n'était lu par un document. Ce qui
 * s'imprimait venait de « 20 » écrit en dur à huit endroits, et la référence
 * légale de « article 293 B », écrite en dur à quatre autres — l'article de la
 * franchise en base, pas celui de l'exonération des organismes de formation que
 * David va demander.
 *
 * Trois règles, donc :
 *   1. aucun repli « ?? '20' » ne réinvente un taux dans le module facturation ;
 *   2. aucune référence légale n'est écrite en dur dans un document ;
 *   3. les trois portes d'écriture gèlent bien le régime sur les documents émis.
 *
 * Les jeux de démonstration sont hors sujet : ce sont des données fictives
 * affichées telles quelles, pas des replis qui inventent un taux.
 */

$racine = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : 'acdc-formation-saas-organisme-de-formation';
if ( ! is_dir( $racine ) ) {
    fwrite( STDERR, "Répertoire introuvable : {$racine}\n" );
    exit( 2 );
}

$alertes = array();
$signale = function ( $fichier, $ligne, $texte ) use ( &$alertes ) {
    $alertes[] = sprintf( '%s:%d — %s', $fichier, $ligne, $texte );
};

/**
 * Le CODE d'un fichier, ligne par ligne, commentaires retirés.
 *
 * Un commentaire qui EXPLIQUE le défaut n'est pas le défaut : ce balayage
 * décrit lui-même « ?? '20' » et « article 293 B » dans ses propres remarques,
 * et les fichiers corrigés portent le récit de ce qu'on leur a retiré. Découper
 * les commentaires à la main — « la ligne commence-t-elle par une étoile ? » —
 * rate les lignes du milieu d'un bloc. On laisse PHP le faire.
 *
 * @return array numéro de ligne => code de cette ligne, sans commentaire.
 */
function acdc_lignes_de_code( $chemin ) {
    $out = array();
    $jetons = @token_get_all( file_get_contents( $chemin ) );
    if ( ! is_array( $jetons ) ) {
        return $out;
    }
    $ligne = 1;
    foreach ( $jetons as $jeton ) {
        if ( is_array( $jeton ) ) {
            $ligne = $jeton[2];
            if ( T_COMMENT === $jeton[0] || T_DOC_COMMENT === $jeton[0] ) {
                /* On avance le compteur sans rien retenir. */
                $ligne += substr_count( $jeton[1], "\n" );
                continue;
            }
            $texte = $jeton[1];
        } else {
            $texte = $jeton;
        }
        foreach ( explode( "\n", $texte ) as $k => $morceau ) {
            $n = $ligne + $k;
            if ( ! isset( $out[ $n ] ) ) { $out[ $n ] = ''; }
            $out[ $n ] .= $morceau;
        }
        $ligne += substr_count( $texte, "\n" );
    }
    return $out;
}

/* --------------------------------------------------------------------------
 * 1. AUCUN REPLI QUI RÉINVENTE UN TAUX
 * ----------------------------------------------------------------------- */
$module = $racine . '/includes/documents-billing';
foreach ( glob( $module . '/*.php' ) as $chemin ) {
    $court  = substr( $chemin, strlen( $racine ) + 1 );
    foreach ( acdc_lignes_de_code( $chemin ) as $n => $ligne ) {
        /* La forme dangereuse est le REPLI : « ?? '20' », « ?? '20,00' »,
           « ?: '20,00' ». Un « '20,00' » isolé dans un jeu de démonstration ne
           décide de rien. */
        if ( preg_match( "/(\?\?|\?:)\s*'20([,.]00)?'/", $ligne ) ) {
            $signale( $court, $n, "un repli réinvente un taux de TVA de 20 % : c'est exactement ce qui masquait l'absence de réglage." );
        }
    }
}

/* --------------------------------------------------------------------------
 * 2. AUCUNE RÉFÉRENCE LÉGALE ÉCRITE EN DUR DANS UN DOCUMENT
 * ----------------------------------------------------------------------- */
$rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $racine . '/includes' ) );
foreach ( $rii as $f ) {
    if ( ! $f->isFile() || 'php' !== strtolower( $f->getExtension() ) ) { continue; }
    $chemin = $f->getPathname();
    $court  = substr( $chemin, strlen( $racine ) + 1 );
    foreach ( acdc_lignes_de_code( $chemin ) as $n => $ligne ) {
        if ( preg_match( '/article\s*293\s*B|art\.\s*293\s*B|261-4-4/i', $ligne ) ) {
            $signale( $court, $n, "une référence légale de TVA est écrite en dur : elle doit venir de VatRegime, sans quoi elle survivra au changement de régime." );
        }
    }
}

/* --------------------------------------------------------------------------
 * 3. LES TROIS PORTES D'ÉCRITURE GÈLENT LE RÉGIME
 * ----------------------------------------------------------------------- */
$portes = array(
    array(
        'fichier'  => 'includes/documents-billing/class-acdc-documents-billing-core-trait.php',
        'fonction' => 'save_quote',
        'quoi'     => 'un devis déjà émis',
    ),
    array(
        'fichier'  => 'includes/documents-billing/class-acdc-documents-billing-core-trait.php',
        'fonction' => 'save_invoice',
        'quoi'     => 'une facture déjà envoyée',
    ),
);
foreach ( $portes as $porte ) {
    $chemin = $racine . '/' . $porte['fichier'];
    $src    = file_get_contents( $chemin );
    /* On isole le corps de la fonction : de sa signature à la fonction suivante. */
    $debut = strpos( $src, 'function ' . $porte['fonction'] . '(' );
    if ( false === $debut ) {
        $signale( $porte['fichier'], 0, "la porte d'écriture « {$porte['fonction']} » a disparu : le gel du régime n'a plus de gardien." );
        continue;
    }
    $suite = strpos( $src, "\n  private function ", $debut + 10 );
    if ( false === $suite ) { $suite = strlen( $src ); }
    $corps = substr( $src, $debut, $suite - $debut );

    if ( ! preg_match( "/unset\(\s*\\\$data\['vat_regime'\]\s*,\s*\\\$data\['vat_rate'\]\s*\)/", $corps ) ) {
        $signale( $porte['fichier'], 0, "« {$porte['fonction']} » ne retire plus le régime et le taux à la mise à jour : " . $porte['quoi'] . " peut changer de TVA." );
    }
    if ( false === strpos( $corps, 'acdc_figer_regime_tva' ) ) {
        $signale( $porte['fichier'], 0, "« {$porte['fonction']} » ne fige plus le régime à la création : " . $porte['quoi'] . " suivrait le profil, et se réécrirait au prochain changement." );
    }
}

/* La convention a sa propre porte, dans le module dossiers. */
$conv = $racine . '/includes/dossiers-contracts/class-acdc-dossiers-contracts-actions-trait.php';
$src  = file_get_contents( $conv );
if ( ! preg_match( "/unset\(\s*\\\$data\['vat_regime'\]\s*,\s*\\\$data\['vat_rate'\]\s*\)/", $src ) ) {
    $signale( 'includes/dossiers-contracts/class-acdc-dossiers-contracts-actions-trait.php', 0,
        "la convention ne gèle plus son régime : une convention signée pourrait changer de TVA à la prochaine modification." );
}
if ( false === strpos( $src, "'vat_regime' => \$this->acdc_regime_tva_profil()" ) ) {
    $signale( 'includes/dossiers-contracts/class-acdc-dossiers-contracts-actions-trait.php', 0,
        "la convention neuve n'inscrit plus le régime de l'organisme." );
}

/* --------------------------------------------------------------------------
 * 4. LA COLONNE EXISTE SUR LES TROIS TABLES
 * ----------------------------------------------------------------------- */
$tables = array(
    'includes/documents-billing/class-acdc-documents-billing-core-trait.php' => 2,
    'includes/kernel/class-acdc-kernel-core-trait.php'                       => 1,
);
foreach ( $tables as $fichier => $attendu ) {
    $src   = file_get_contents( $racine . '/' . $fichier );
    $vues  = substr_count( $src, 'vat_regime VARCHAR(32)' );
    if ( $vues < $attendu ) {
        $signale( $fichier, 0, "la colonne « vat_regime » manque sur " . ( $attendu - $vues ) . " table(s) : le régime n'aurait nulle part où être conservé." );
    }
}

/* --------------------------------------------------------------------------
 * VERDICT
 * ----------------------------------------------------------------------- */
echo "\n";
if ( $alertes ) {
    foreach ( $alertes as $a ) { echo "ALERTE  {$a}\n"; }
    printf( "%d alerte(s)\n", count( $alertes ) );
    exit( 1 );
}
echo "TVA : un seul réglage, figé sur chaque document, et aucune référence légale écrite en dur.\n";
exit( 0 );
