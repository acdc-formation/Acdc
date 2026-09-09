<?php
/**
 * La barre de complétude d'un dossier. Deux règles y sont non négociables :
 *
 *  — le test de positionnement ne compte QUE s'il est exigé. Sinon il disparaît
 *    du tableau ET du calcul : une exigence qui ne s'applique pas ne doit pas
 *    peser sur un score ;
 *  — l'émargement n'est vert que si TOUTES les demi-journées sont réglées. Vert
 *    à la première signature serait plus flatteur et faux devant un financeur.
 */
function completude( array $etat ) {
    $criteria = array(
        array( 'Apprenant / groupe',     $etat['apprenant']    ?? false, 10 ),
        array( 'Formation',              $etat['formation']    ?? false, 10 ),
        array( 'Convention',             $etat['convention']   ?? false, 10 ),
        array( 'Convocation',            $etat['convocation']  ?? false, 10 ),
        array( 'Ouverture intranet',     $etat['intranet']     ?? false, 5 ),
    );
    if ( ! empty( $etat['positionnement_exige'] ) ) {
        $criteria[] = array( 'Test de positionnement', $etat['positionnement'] ?? false, 10 );
    }
    $criteria[] = array( 'Émargement',               $etat['emargement']   ?? false, 10 );
    $criteria[] = array( 'Évaluation diagnostique',  $etat['diagnostique'] ?? false, 5 );
    $criteria[] = array( 'Évaluation des acquis',    $etat['acquis']       ?? false, 10 );
    $criteria[] = array( 'Enquête à chaud',          $etat['chaud']        ?? false, 10 );
    /* ACDC 3.25.264 — Ces deux lignes portaient des libellés INVERSÉS : la
       première cochait le certificat de réalisation sous le nom d'attestation,
       la seconde l'attestation sous le nom de certificat. Deux documents, deux
       destinataires — le financeur et l'apprenant — et deux poids différents :
       le score allait au mauvais document. Les noms sont ceux de la loi. */
    $criteria[] = array( 'Certificat de réalisation',       $etat['certificat_realisation'] ?? false, 10 );
    $criteria[] = array( 'Attestation de fin de formation', $etat['attestation_fin']        ?? false, 5 );
    $criteria[] = array( 'Enquête à froid',          $etat['froid']        ?? false, 5 );

    $total = 0; $earned = 0; $labels = array();
    foreach ( $criteria as $c ) {
        $total += $c[2]; $labels[] = $c[0];
        if ( $c[1] ) { $earned += $c[2]; }
    }
    return array( 'percent' => $total > 0 ? (int) round( $earned / $total * 100 ) : 0, 'labels' => $labels );
}

/** L'émargement : toutes les demi-journées réglées (signées ou absence déclarée). */
function emargement_complet( $total, $signees, $absences ) {
    if ( $total <= 0 ) { return false; }   // rien à prouver, donc rien de prouvé
    return ( $signees + $absences ) >= $total;
}

$ko = 0;
$check = function( $label, $got, $expected ) use ( &$ko ) {
    if ( $got !== $expected ) { $ko++; printf("ÉCHEC  %s : attendu %s, obtenu %s\n", $label,
        var_export($expected,true), var_export($got,true)); }
};

/* L'ordre dicté par David, positionnement exigé : 13 étapes. */
$r = completude( array( 'positionnement_exige' => true ) );
$check('13 étapes quand le positionnement est exigé', count($r['labels']), 13);
$check('ordre respecté', array_slice($r['labels'], 0, 6), array(
  'Apprenant / groupe','Formation','Convention','Convocation','Ouverture intranet','Test de positionnement' ));
$check('dernière étape', end($r['labels']), 'Enquête à froid');

/* Sans positionnement exigé : la pastille disparaît. */
$r = completude( array() );
$check('12 étapes sans positionnement', count($r['labels']), 12);
$check('positionnement absent', in_array('Test de positionnement', $r['labels'], true), false);

/* Et il ne plombe plus le score : un dossier identique vaut PLUS sans lui. */
$base = array( 'apprenant'=>true,'formation'=>true,'convention'=>true,'convocation'=>true,'intranet'=>true );
$avec = completude( $base + array( 'positionnement_exige'=>true ) )['percent'];
$sans = completude( $base )['percent'];
if ( $sans <= $avec ) { $ko++; printf("ÉCHEC  le positionnement non exigé plombe encore le score (%d%% vs %d%%)\n", $sans, $avec); }

/* Un dossier vierge vaut 0, un dossier complet vaut 100. */
$check('dossier vierge', completude(array())['percent'], 0);
$tout = array_fill_keys( array('apprenant','formation','convention','convocation','intranet',
  'emargement','diagnostique','acquis','chaud','certificat_realisation','attestation_fin','froid'), true );
$check('dossier complet', completude($tout)['percent'], 100);

/* La convocation compte pour de bon. */
$check('convocation seule', completude(array('convocation'=>true))['percent'] > 0, true);

/* Émargement : la règle de David. */
$check('aucune feuille',            emargement_complet(0, 0, 0), false);
$check('une seule signée sur quatre', emargement_complet(4, 1, 0), false);
$check('toutes signées',            emargement_complet(4, 4, 0), true);
$check('signées + absence déclarée', emargement_complet(4, 3, 1), true);
$check('une demi-journée oubliée',  emargement_complet(4, 3, 0), false);

printf("13 contrôles, %d échec(s)\n", $ko);
exit($ko === 0 ? 0 : 1);
