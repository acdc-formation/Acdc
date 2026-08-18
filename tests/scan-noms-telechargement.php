<?php
/**
 * ACDC 3.25.320 — Un nom de fichier doit dire DE QUI il s'agit.
 *
 * Relevé de recette du 18/08. Cinq documents se téléchargeaient sous un nom qui
 * ne désignait personne :
 *
 *   recueil-des-besoins-2.pdf ............ le « 2 » est un identifiant de base
 *   Modèle de devis - ACDC-Formation ..... le nom du GABARIT, identique pour tous
 *   Convention-LIntelligence-…-14082026 .. le titre de la formation, identique
 *                                          pour tous les clients
 *   convocation-3-<jeton>.pdf ............ le « 3 » est un numéro de ligne
 *
 * Dans un dossier de téléchargements, dix conventions de dix entreprises
 * portaient dix noms identiques à la date près. Le nom d'un document est ce qui
 * permet de le retrouver un an plus tard, devant un auditeur.
 *
 * DEUX RÈGLES QUI NE SE VOIENT PAS ET QU'ON PROTÈGE ICI :
 *
 *  — Le JETON de la convocation reste. Il ne sert pas à nommer mais à rendre
 *    l'adresse indevinable : le retirer rouvrirait l'énumération des
 *    convocations, qui portent le nom et les coordonnées de l'apprenant.
 *  — Le titre HTML du devis EST son nom de fichier. Servi en HTML, le devis est
 *    enregistré par le navigateur sous le titre de la page, jamais sous
 *    l'adresse. Le fichier stocké portait déjà le bon nom depuis la 3.25.310 :
 *    seul l'enregistrement manuel restait en arrière.
 *
 * Éprouvé en sabotant : chaque règle échoue quand on défait la correction.
 */

$racine = dirname( __DIR__ ) . '/acdc-formation-saas-organisme-de-formation';

$echecs = array();
$verifs = 0;
$exiger = function ( $condition, $message ) use ( &$echecs, &$verifs ) {
    $verifs++;
    if ( ! $condition ) {
        $echecs[] = $message;
    }
};

/** Le code d'un fichier, débarrassé de ses commentaires. */
$code = function ( $relatif ) use ( $racine ) {
    $chemin = $racine . '/' . $relatif;
    if ( ! is_readable( $chemin ) ) {
        return '';
    }
    $sans = '';
    foreach ( token_get_all( (string) file_get_contents( $chemin ) ) as $jeton ) {
        if ( is_array( $jeton ) ) {
            if ( T_COMMENT === $jeton[0] || T_DOC_COMMENT === $jeton[0] ) {
                $sans .= str_repeat( "\n", substr_count( $jeton[1], "\n" ) );
                continue;
            }
            $sans .= $jeton[1];
            continue;
        }
        $sans .= $jeton;
    }
    return $sans;
};

/* 1. LE RECUEIL — plus jamais son identifiant seul. */
$noyau_actions = $code( 'includes/kernel/class-acdc-kernel-actions-trait.php' );
$exiger( '' !== $noyau_actions, 'Le fichier des actions du noyau est introuvable.' );
$exiger(
    ! preg_match( "/'recueil-des-besoins-'\s*\.\s*\(int\)/", $noyau_actions ),
    'Le recueil se télécharge de nouveau sous son identifiant de base (« recueil-des-besoins-2 ») : le nom ne désigne plus le client.'
);
$exiger(
    (bool) preg_match( "/NomDocument::composer\(\s*'recueil des besoins'/", $noyau_actions ),
    'Le recueil ne passe plus par la porte commune des noms de documents.'
);

/* 1 bis. LE RECUEIL — l'entité vient d'où l'écran l'écrit, et la date suit.
      Le 18 août le fichier s'appelait encore « recueil-des-besoins.pdf » : je
      cherchais l'entreprise sur « company_id », que la saisie d'un recueil ne
      remplit pas, puis sur « company_name », qui n'existe pas dans cette table.
      Un recueil se rattache à un PROSPECT. */
$exiger(
    (bool) preg_match( '/\$need->source_prospect_id.{0,200}get_prospect\(/s', $noyau_actions ),
    'Le nom du recueil ne descend plus jusqu’au prospect : sans fiche entreprise — le cas courant à la saisie — le fichier redevient « recueil-des-besoins.pdf ».'
);
$exiger(
    (bool) preg_match( "/NomDocument::composer\(\s*'recueil des besoins',\s*\\\$__entite_recueil,\s*\\\$__date_recueil/", $noyau_actions ),
    'La date a disparu du nom du recueil : deux recueils du même client ne se distinguent plus.'
);

/* 2. LA CONVOCATION — le nom de la personne, et le jeton conservé. */
$noyau_core = $code( 'includes/kernel/class-acdc-kernel-core-trait.php' );
$exiger(
    ! preg_match( "/'convocation-'\s*\.\s*\(int\)\s*\\\$registration->id/", $noyau_core ),
    'La convocation reprend le numéro de ligne de l’inscription : trois convocations d’une même séance ne se distinguent plus que par un chiffre interne.'
);
$exiger(
    (bool) preg_match( "/NomDocument::composer\(\s*'convocation'/", $noyau_core ),
    'La convocation ne porte plus le nom de l’apprenant.'
);
$exiger(
    (bool) preg_match( '/\$__base\s*\.\s*\'-\'\s*\.\s*\$token/', $noyau_core ),
    'Le jeton a disparu du nom de la convocation : son adresse redevient devinable, et avec elle le nom et les coordonnées de l’apprenant.'
);

/* 3. LE DEVIS — le titre HTML est le nom du fichier. */
$devis = $code( 'includes/documents-billing/class-acdc-documents-billing-core-trait.php' );
$exiger(
    false === strpos( $devis, '<title>Modèle de devis' ),
    'Le devis reprend le titre du gabarit : tous les devis de tous les clients s’enregistrent sous le même nom.'
);
$exiger(
    false !== strpos( $devis, '$__titre_devis' ),
    'Le titre du devis n’est plus composé à partir du client et du numéro.'
);

/* 4. LA CONVENTION — le NOM du commanditaire, pas son type, et la version dite.
      Ces règles remplacent celles de 3.25.320, qui n'exigeaient qu'un préfixe :
      il était bien posé, mais composé à partir d'un nom que personne ne
      résolvait, et le fichier du 18 août s'appelait « entreprise-Convention-… ».
      Un balayage qui vérifie qu'une fonction est appelée ne dit rien de ce
      qu'elle reçoit — on exige donc ici le contexte, faute duquel le nom
      retombe sur le TYPE de commanditaire. */
$conv = $code( 'includes/dossiers-contracts/class-acdc-dossiers-contracts-core-trait.php' );
$deb_nom = strpos( $conv, 'private function get_registration_contract_display_file_name(' );
$corps_nom = false !== $deb_nom ? substr( $conv, $deb_nom, 2200 ) : '';
$exiger(
    '' !== $corps_nom,
    'La composition du nom de la convention a disparu.'
);
$exiger(
    '' !== $corps_nom && false !== strpos( $corps_nom, 'get_registration_contract_related_context( $contract )' ),
    'Le nom de la convention se compose à nouveau sans contexte : privée de la fiche entreprise, du prospect et des apprenants, la résolution du commanditaire retombe sur son TYPE et le fichier s’appelle « entreprise-… ».'
);
$exiger(
    '' !== $corps_nom && (bool) preg_match( "/NomDocument::composer\(\s*\\\$nature/", $corps_nom ),
    'Le nom de la convention ne passe plus par la porte commune des noms de documents.'
);
$exiger(
    '' !== $corps_nom && false !== strpos( $corps_nom, "'Convention signée'" ),
    'La convention signée ne se distingue plus de la version vierge : c’est pourtant la seule que réclame un financeur.'
);
$exiger(
    '' !== $corps_nom && (bool) preg_match( '/in_array\(\s*mb_strtolower\(\s*\$qui\s*\),\s*\$type_seul,\s*true\s*\)\s*\)\s*\{\s*\$qui = \'\';/s', $corps_nom ),
    'Le garde-fou qui écarte un TYPE de commanditaire (« Entreprise », « Particulier ») a disparu du nom de fichier.'
);
$exiger(
    (bool) preg_match( '/private function acdc_convention_signee\(/', $conv ),
    'La reconnaissance de la version signée a disparu.'
);

/* 5. Aucune flèche brute ne subsiste dans le rendu : elles sortaient en
      « â Retour » selon l'encodage servi. */
$flechees = array();
$iterateur = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $racine . '/includes' ) );
foreach ( $iterateur as $fichier ) {
    if ( $fichier->isDir() || 'php' !== strtolower( $fichier->getExtension() ) ) {
        continue;
    }
    if ( false !== strpos( (string) file_get_contents( $fichier->getPathname() ), "\xe2\x86\x90" ) ) {
        $flechees[] = str_replace( $racine . '/', '', $fichier->getPathname() );
    }
}
$exiger(
    empty( $flechees ),
    'Des flèches « ← » brutes sont revenues dans le rendu (' . implode( ', ', array_slice( $flechees, 0, 3 ) ) . ') : elles s’affichent « â Retour » dès que la page n’est pas servie en UTF-8. Utiliser &larr;.'
);

if ( $echecs ) {
    foreach ( $echecs as $e ) {
        fwrite( STDERR, "ÉCHEC — $e\n" );
    }
    fwrite( STDERR, sprintf( "\n%d/%d vérifications passées.\n", $verifs - count( $echecs ), $verifs ) );
    exit( 1 );
}

echo sprintf( "Noms de téléchargement : chaque document dit de qui il s’agit — %d/%d vertes.\n", $verifs, $verifs );
exit( 0 );
