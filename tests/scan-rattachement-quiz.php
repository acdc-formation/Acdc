<?php
/**
 * ACDC 3.25.327 — Un quiz se rattache toujours à quelqu'un.
 *
 * CE QUI EST ARRIVÉ LE 19 AOÛT. Le formateur lance l'ÉVALUATION DES ACQUIS
 * depuis son extranet. Les trois apprenantes rejoignent avec le code PIN,
 * répondent, le podium s'affiche — et les trois lignes de résultats portent
 * « ⚠ non rattaché à un apprenant ». Une évaluation des acquis qui ne se
 * rattache à personne ne valide rien : c'est elle qui décide si les acquis le
 * sont, et elle est opposable.
 *
 * LA RACINE, ET C'EST LA TROISIÈME FOIS. L'écran où l'apprenant choisit son nom
 * n'affichait la liste que si la partie portait une SÉANCE :
 *
 *     if ( empty( $session->formation_session_id ) ) {
 *         wp_send_json_success( array( 'learners' => array() ) );  // liste vide
 *     }
 *
 * Or le bouton « Lancer en live » de l'extranet formateur ne demande aucune
 * séance et n'en transmet aucune. La liste était donc vide, personne ne pouvait
 * se désigner, aucun identifiant n'était envoyé — et le contrôle
 * d'appartenance, corrigé en 3.25.266 puis en 3.25.271, n'avait toujours rien à
 * vérifier. Deux corrections sur la serrure, et la porte n'était pas montrée.
 *
 * CE QUE CE BALAYAGE DÉFEND — quatre couches, pour qu'aucune ne puisse échouer
 * en silence :
 *   1. la LISTE descend à la formation quand il n'y a pas de séance ;
 *   2. le LANCEMENT rattache la séance du jour quand elle ne fait aucun doute ;
 *   3. le REPLI en invité s'annonce, à l'apprenant comme au formateur ;
 *   4. ce qui est passé sans rattachement se RATTRAPE à la main.
 *
 * Éprouvé en sabotant : chaque règle échoue quand on défait la correction.
 */

$racine = dirname( __DIR__ ) . '/acdc-formation-saas-organisme-de-formation';

$code_nu = function ( $relatif ) use ( $racine ) {
    $chemin = $racine . '/' . $relatif;
    if ( ! is_readable( $chemin ) ) {
        fwrite( STDERR, "Fichier introuvable : $chemin\n" );
        exit( 1 );
    }
    $brut = (string) file_get_contents( $chemin );
    if ( '.php' !== substr( $relatif, -4 ) ) {
        return $brut;
    }
    $sans = '';
    foreach ( token_get_all( $brut ) as $jeton ) {
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

$noyau    = $code_nu( 'includes/quizzes/class-acdc-quizzes-core-trait.php' );
$actions  = $code_nu( 'includes/quizzes/class-acdc-quizzes-actions-trait.php' );
$moteur   = $code_nu( 'includes/quizzes/class-acdc-quizzes-engine-trait.php' );
$resultats= $code_nu( 'includes/quizzes/class-acdc-quizzes-render-results-trait.php' );
$joueur   = $code_nu( 'assets/js/quizzes-live-player.js' );
$hote     = $code_nu( 'assets/js/quizzes-live-host.js' );

$echecs = array();
$verifs = 0;
$exiger = function ( $condition, $message ) use ( &$echecs, &$verifs ) {
    $verifs++;
    if ( ! $condition ) {
        $echecs[] = $message;
    }
};

/* --- 1. UNE SEULE PORTE POUR « QUI PEUT REJOINDRE » --- */
$exiger(
    false !== strpos( $noyau, 'private function acdc_qz_apprenants_de_la_partie(' ),
    'Le résolveur « qui peut rejoindre cette partie » a disparu : la liste proposée à l’apprenant et le contrôle de son choix redeviennent deux codes différents.'
);
$exiger(
    (bool) preg_match( '/acdc_qz_apprenants_de_la_partie.{0,4000}acdc_learners_for_formations\(\s*array\(\s*\$formation_id\s*\)\s*\)/s', $noyau ),
    'Le repli sur la FORMATION a disparu : une partie lancée depuis l’extranet formateur ne porte aucune séance, et l’apprenant n’aurait de nouveau aucun nom à choisir.'
);
$exiger(
    ! preg_match( "/empty\(\s*\\\$session->formation_session_id\s*\)\s*\)\s*\{\s*wp_send_json_success\(\s*array\(\s*'learners'\s*=>\s*array\(\)/s", $actions ),
    'La liste des apprenants se vide de nouveau dès qu’aucune séance n’est rattachée : c’est la ligne exacte qui a laissé les trois évaluations du 19 août sans rattachement.'
);
$exiger(
    (bool) preg_match( '/\$learners = \$this->acdc_qz_apprenants_de_la_partie\( \$session \)/', $actions ),
    'Le point d’entrée qui sert la liste à l’écran n’appelle plus le résolveur commun.'
);

/* --- 2. CE QUI EST PROPOSÉ ET CE QUI EST ACCEPTÉ SONT LA MÊME CHOSE --- */
$debut_join = strpos( $actions, 'public function ajax_acdc_of_qz_player_join()' );
$join = false !== $debut_join ? substr( $actions, $debut_join, 5000 ) : '';
$exiger(
    '' !== $join && false !== strpos( $join, 'acdc_qz_apprenants_de_la_partie( $session )' ),
    'L’entrée dans la partie ne vérifie plus l’appartenance sur la même liste que celle affichée : un apprenant pourrait être proposé à l’écran puis refusé en silence.'
);
$exiger(
    '' !== $join && ! preg_match( '/if\s*\(\s*\$learner_id\s*>\s*0\s*&&\s*!\s*empty\(\s*\$session->formation_session_id\s*\)\s*\)/', $join ),
    'Le contrôle d’appartenance redevient conditionné à l’existence d’une séance : sans séance, le rattachement ne serait de nouveau jamais confirmé.'
);
$exiger(
    '' !== $join && (bool) preg_match( '/\$is_traceable = 1;/', $join ),
    'Le drapeau de traçabilité Qualiopi n’est plus posé : le quiz ne compterait comme preuve pour aucun dossier.'
);
$exiger(
    '' !== $join && (bool) preg_match( '/acdc_qz_apprenant_depuis_pseudo_dans\(\s*\$inscrits/', $join ),
    'Le rattrapage par le pseudo a disparu : un apprenant qui tape son prénom sans choisir son nom resterait anonyme.'
);

/* --- 3. LE LANCEMENT RATTACHE LA SÉANCE DU JOUR --- */
$exiger(
    (bool) preg_match( '/\$formation_session_id <= 0 && \$formation_id > 0.{0,900}BETWEEN COALESCE\(start_date/s', $actions ),
    'Le lancement en salle ne rattache plus la séance du jour : la partie repart sans journée de formation, et la traçabilité Qualiopi avec elle.'
);

/* --- 4. LE REPLI EN INVITÉ NE PASSE PLUS INAPERÇU --- */
$exiger(
    false !== strpos( $joueur, 'acdc-qz-player-guest-note' ),
    'L’avertissement « vous rejoindrez en invité » a disparu de l’écran apprenant : rien ne distinguerait plus un rattachement réussi d’un échec.'
);
$exiger(
    (bool) preg_match( "/'rattache'\s*=>\s*!\s*empty\(\s*\\\$p->learner_id\s*\)/", $moteur ),
    'L’état de la partie ne dit plus si un joueur est rattaché : l’écran du formateur ne peut plus le signaler.'
);
$exiger(
    false !== strpos( $hote, 'acdc-qz-unlinked-badge' ),
    'Le formateur ne voit plus, dans le salon, qu’un joueur n’est rattaché à personne — il ne le découvrirait qu’après l’épreuve, quand il est trop tard.'
);

/* --- 5. CE QUI EST PASSÉ SANS RATTACHEMENT SE RATTRAPE --- */
$exiger(
    false !== strpos( $actions, 'public function handle_acdc_of_qz_attach_participant()' ),
    'Le rattachement manuel a disparu : une épreuve déjà passée sans rattachement serait perdue, ou à refaire passer.'
);
$debut_attach = strpos( $actions, 'public function handle_acdc_of_qz_attach_participant()' );
$attach = false !== $debut_attach ? substr( $actions, $debut_attach, 3000 ) : '';
$exiger(
    '' !== $attach && (bool) preg_match( '/\$learner_id <= 0 \|\| ! in_array\(\s*\$learner_id,\s*\$ids_inscrits,\s*true\s*\)/', $attach ),
    'Le rattachement manuel n’exige plus que l’apprenant appartienne à la partie : on offrirait un moyen commode de verser les réponses de n’importe qui dans n’importe quel dossier.'
);
$exiger(
    false !== strpos( $actions, "add_action( 'admin_post_acdc_of_qz_attach_participant'" ),
    'Le rattachement manuel n’est plus branché : le bouton existerait sans rien déclencher.'
);
$exiger(
    (bool) preg_match( '/name="action" value="acdc_of_qz_attach_participant"/', $resultats ),
    'L’écran de résultats ne propose plus de rattacher un participant resté anonyme.'
);

if ( $echecs ) {
    foreach ( $echecs as $e ) {
        fwrite( STDERR, "ÉCHEC — $e\n" );
    }
    fwrite( STDERR, sprintf( "\n%d/%d vérifications passées.\n", $verifs - count( $echecs ), $verifs ) );
    exit( 1 );
}

echo sprintf( "Rattachement des quiz : la liste, le contrôle et le rattrapage — %d/%d vertes.\n", $verifs, $verifs );
exit( 0 );
