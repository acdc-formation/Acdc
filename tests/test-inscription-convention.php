<?php
/**
 * Quand une convention signée devient des dossiers d'inscription.
 *
 * Le défaut corrigé en 3.25.253 : l'étape « Inscrire les apprenants nommés dans
 * la convention » comptait les NOMS portés par la convention et annonçait des
 * DOSSIERS. Elle affichait « 2 apprenant(s) inscrit(s) au dossier » en vert
 * alors qu'aucun dossier n'existait. Verte, elle n'était jamais faite — et tout
 * ce qui pend au dossier restait vide : la liste des apprenants inscrits,
 * l'extranet, la barre de complétude. La convocation, elle, partait : elle lit
 * la convention. D'où le constat de David — « ils ont reçu le mail et pourtant
 * il n'y a personne ».
 *
 * Deux règles sont testées :
 *   — l'étape ne se dit FAITE que sur des dossiers réellement créés ;
 *   — la création est idempotente : rejouée, elle ne duplique rien. C'est ce
 *     qui autorise le moteur à rattraper les conventions déjà signées à chaque
 *     passe.
 */

/** Reproduit acdc_enroll_learners_from_contract() sur une base en mémoire. */
function inscrire_depuis_convention( array $convention, array &$dossiers, array $apprenants_connus ) {
    $rapport = array( 'created' => 0, 'skipped' => 0 );
    $noms    = array_values( array_filter( array_map( 'intval', $convention['learner_ids'] ) ) );
    foreach ( $noms as $learner_id ) {
        $deja = false;
        foreach ( $dossiers as $d ) {
            if ( $d['learner_id'] === $learner_id
              && $d['formation_id'] === $convention['formation_id']
              && $d['contract_id'] === $convention['id'] ) {
                $deja = true;
                break;
            }
        }
        if ( $deja ) { $rapport['skipped']++; continue; }
        /* Un identifiant qui ne désigne personne ne fabrique pas de dossier. */
        if ( ! in_array( $learner_id, $apprenants_connus, true ) ) { continue; }
        $dossiers[] = array(
            'learner_id'      => $learner_id,
            'formation_id'    => $convention['formation_id'],
            'contract_id'     => $convention['id'],
            'extranet_access' => 1,
            'is_draft'        => 0,
        );
        $rapport['created']++;
    }
    return $rapport;
}

/** Reproduit la décision de l'étape « registration » du moteur. */
function etat_etape_inscription( array $convention, array $dossiers, $signee ) {
    $reels = 0;
    foreach ( $dossiers as $d ) {
        if ( $d['contract_id'] === $convention['id'] && 0 === (int) $d['is_draft'] ) { $reels++; }
    }
    return array(
        'etat'    => $reels > 0 ? 'done' : 'todo',
        'dossiers'=> $reels,
        'nommes'  => count( $convention['learner_ids'] ),
        'signee'  => (bool) $signee,
    );
}

$ko = 0;
$t  = function ( $label, $obtenu, $attendu ) use ( &$ko ) {
    if ( (string) $obtenu !== (string) $attendu ) {
        $ko++;
        printf( "ÉCHEC  %s : attendu « %s », obtenu « %s »\n", $label, $attendu, $obtenu );
    }
};

$convention = array( 'id' => 12, 'formation_id' => 7, 'learner_ids' => array( 31, 32 ) );
$connus     = array( 31, 32, 33 );

/* LE DÉFAUT : deux noms sur la convention, aucun dossier. L'étape ne doit PAS
   se dire faite. */
$dossiers = array();
$etat = etat_etape_inscription( $convention, $dossiers, true );
$t( 'deux noms mais zéro dossier : étape à faire', $etat['etat'], 'todo' );
$t( 'et le décompte de dossiers est zéro',         $etat['dossiers'], 0 );

/* La signature crée un dossier par apprenant nommé. */
$r = inscrire_depuis_convention( $convention, $dossiers, $connus );
$t( 'la signature crée deux dossiers', $r['created'], 2 );
$t( 'aucun doublon au premier passage', $r['skipped'], 0 );
$t( 'l’étape devient faite', etat_etape_inscription( $convention, $dossiers, true )['etat'], 'done' );

/* IDEMPOTENCE : le moteur rejoue à chaque passe. Rien ne doit se dupliquer. */
$r2 = inscrire_depuis_convention( $convention, $dossiers, $connus );
$t( 'seconde passe : aucun dossier créé', $r2['created'], 0 );
$t( 'seconde passe : deux doublons évités', $r2['skipped'], 2 );
$t( 'le nombre de dossiers ne bouge pas', count( $dossiers ), 2 );

/* Les dossiers créés ouvrent l'extranet : c'est ce que lit l'index d'accès. */
$t( 'le dossier ouvre l’extranet', $dossiers[0]['extranet_access'], 1 );
$t( 'et n’est pas un brouillon',   $dossiers[0]['is_draft'], 0 );

/* Un apprenant ajouté à la convention après coup est rattrapé à la passe suivante. */
$convention['learner_ids'][] = 33;
$r3 = inscrire_depuis_convention( $convention, $dossiers, $connus );
$t( 'l’apprenant ajouté est inscrit',        $r3['created'], 1 );
$t( 'les deux premiers ne sont pas refaits', $r3['skipped'], 2 );

/* Un identifiant qui ne désigne aucun apprenant ne fabrique rien. */
$convention['learner_ids'][] = 99;
$r4 = inscrire_depuis_convention( $convention, $dossiers, $connus );
$t( 'un apprenant inconnu ne crée pas de dossier', $r4['created'], 0 );
$t( 'le total reste à trois dossiers',             count( $dossiers ), 3 );

/* Une convention sans personne nommée ne crée rien et reste à faire. */
$vide = array( 'id' => 44, 'formation_id' => 7, 'learner_ids' => array() );
$d2   = array();
$r5   = inscrire_depuis_convention( $vide, $d2, $connus );
$t( 'convention sans apprenant : rien créé', $r5['created'], 0 );
$t( 'et l’étape reste à faire',              etat_etape_inscription( $vide, $d2, true )['etat'], 'todo' );

printf( "16 contrôles, %d échec(s)\n", $ko );
exit( 0 === $ko ? 0 : 1 );
