<?php
/**
 * ACDC 3.25.330 — Les quatre refontes d'écran de la recette du 23 août.
 *
 * CE QUE COUVRE CE BALAYAGE. Les points que la 3.25.329 avait laissés de côté
 * parce qu'ils demandaient une refonte et non un correctif : l'explorateur de
 * dossiers, la simplification des résultats d'enquêtes, l'audit Qualiopi et
 * les statistiques. Plus la navigation par thématique des quiz.
 *
 * CE QUI REVIENT DANS TROIS DE CES QUATRE POINTS. Aucun de ces écrans n'avait
 * besoin d'être réécrit : il leur manquait une donnée, un branchement ou une
 * soustraction.
 *   — L'explorateur par commanditaire EXISTAIT, branché sur le seul écran
 *     Conventions ; Dossiers de formation rendait cinq fois la même liste.
 *   — Le taux de conversion cherchait le mot « devis » dans un libellé au lieu
 *     de compter les devis.
 *   — L'audit inversait certificat et attestation, comme le score de
 *     complétude avant la 3.25.264.
 * Les règles ci-dessous visent des formes d'appel ou d'affectation exactes, ou
 * le corps d'une fonction nommée : une chaîne qui existe quelque part dans un
 * fichier ne prouve rien.
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

$sans_commentaires = function ( $source ) {
    $sortie = '';
    foreach ( token_get_all( $source ) as $jeton ) {
        if ( is_array( $jeton ) ) {
            if ( in_array( $jeton[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) { $sortie .= "\n"; continue; }
            $sortie .= $jeton[1];
            continue;
        }
        $sortie .= $jeton;
    }
    return $sortie;
};

/* Borné par la déclaration suivante : un plafond en caractères laisse
   déborder la fonction d'à côté, et une règle qui lit le voisin ne peut pas
   échouer. */
$corps = function ( $source, $signature, $longueur = 12000 ) {
    $d = strpos( $source, $signature );
    if ( false === $d ) { return ''; }
    $bloc  = substr( $source, $d, $longueur );
    $reste = substr( $bloc, strlen( $signature ) );
    if ( preg_match( '/\n\s*(?:(?:public|private|protected)\s+)?(?:static\s+)?function\s/', $reste, $m, PREG_OFFSET_CAPTURE ) ) {
        $bloc = $signature . substr( $reste, 0, $m[0][1] );
    }
    return $bloc;
};

$echecs = array();
$verifs = 0;
$exiger = function ( $condition, $message ) use ( &$echecs, &$verifs ) {
    $verifs++;
    if ( ! $condition ) { $echecs[] = $message; }
};

/* ══ 1. LES DOSSIERS DE FORMATION : L'EXPLORATEUR EST BRANCHÉ ════════════ */
$rendu    = $lire( 'includes/kernel/class-acdc-kernel-render-trait.php' );
$rendu_nu = $sans_commentaires( $rendu );
$dossiers_nu = $sans_commentaires( $lire( 'includes/dossiers-contracts/class-acdc-dossiers-contracts-render-trait.php' ) );

$onglet_trf = $corps( $rendu_nu, 'private function render_front_training_files_tab(', 30000 );
$exiger( false !== strpos( $onglet_trf, "\$this->render_dossiers_by_entity( \$trf_entites[ \$trf_view ]" ), '1. Les vues par entité ne sont pas branchées sur l\'explorateur : les cinq onglets rendraient toujours la même liste.' );
$exiger( false !== strpos( $onglet_trf, "'commanditaires' => 'company'" ), '2. La vue « Par commanditaire » n\'est pas rattachée à l\'entité commanditaire.' );
/* La moitié qu'on emporterait sans y penser : le nom de la vue doit voyager,
   sinon les liens des dossiers repartent avec « companys », valeur que cet
   écran ne reconnaît pas — et un clic ramène à la liste plate. */
$exiger( false !== strpos( $onglet_trf, '$trf_page, 25, $trf_view )' ), '3. Le nom de la vue n\'est pas transmis à l\'explorateur : un clic sur un dossier retomberait sur la liste des inscriptions.' );
$exiger( false !== strpos( $dossiers_nu, 'private function render_dossiers_by_entity( $entity_type, $search, $base_url, $paged, $per_page, $vue_slug = null )' ), '4. L\'explorateur n\'accepte pas le nom de vue de son appelant.' );

/* Le menu : une section propre, et plus une entrée noyée dans Inscription / Suivi. */
$exiger( false !== strpos( $rendu, "'type' => 'section',\n        'label' => 'Dossiers de formation'," ), '5. Dossiers de formation n\'est pas devenue une section principale.' );
$exiger( false !== strpos( $rendu, "array( 'tab' => 'trf_commanditaires', 'label' => 'Par commanditaire'" ), '6. La vue par commanditaire n\'a pas d\'entrée de menu.' );
/* On regarde le MENU, pas le fichier : « trf_inscriptions » apparaît aussi
   dans une redirection sans rapport, et une règle qui compte les deux ne peut
   pas échouer quand l'entrée revient au mauvais endroit. */
$menu_inscription = $corps( $rendu, "'label' => 'Inscription / Suivi',", 1400 );
$exiger( '' !== $menu_inscription && false === strpos( $menu_inscription, "'label' => 'Dossiers de formation'" ), '7. L\'entrée « Dossiers de formation » est restée dans le groupe Inscription / Suivi.' );

/* ══ 2. LES RÉSULTATS D'ENQUÊTES : DIX TUILES À ZÉRO EN MOINS ════════════ */
$quest    = $lire( 'includes/questionnaires/class-acdc-questionnaires-render-trait.php' );
$quest_nu = $sans_commentaires( $quest );
$page_res = $corps( $quest_nu, 'private function render_front_questionnaire_results_tab()', 26000 );
$exiger( '' !== $page_res, '8. La page des résultats d\'enquêtes est introuvable.' );
$exiger( false === strpos( $page_res, 'acdc-priority-stat-moyenne' ) && false === strpos( $page_res, 'acdc-urgency-stat-soon' ), '9. Les tuiles de priorité et d\'échéance sont toujours empilées sur cette page.' );
/* On compte les tuiles du BANDEAU de tête, celles que la recette montre
   empilées sur cinq rangées — pas les compteurs des blocs de détail plus bas,
   qui ne s'affichent que sur demande. */
$__fin_bandeau = strpos( $page_res, '$actions_total' );
$bandeau = substr( $page_res, 0, $__fin_bandeau ? $__fin_bandeau : 4000 );
$exiger( substr_count( $bandeau, 'acdc-centered-stat' ) <= 4, '10. Il reste plus de quatre tuiles dans le bandeau des résultats (' . substr_count( $bandeau, 'acdc-centered-stat' ) . ').' );
/* Ce qu'on ne doit PAS avoir perdu : les chiffres eux-mêmes restent lisibles,
   et les alertes doivent rester visibles — c'est le seul compteur qui doit
   attraper l'œil. */
$exiger( false !== strpos( $page_res, "\$stats['response_rate']" ) && false !== strpos( $page_res, "\$stats['average_score']" ), '11. Le taux de retour ou le score moyen a disparu de la page.' );
$exiger( false !== strpos( $page_res, "\$alertes = (int) \$stats['alerts'];" ) && false !== strpos( $page_res, "\$alertes > 0" ), '12. Les alertes ne sont plus signalées : le seul compteur qui devait rester voyant a été perdu avec les autres.' );
$exiger( false !== strpos( $page_res, "\$action_stats['en_retard']" ), '13. Les actions en retard ne sont plus reportées nulle part.' );

/* ══ 3. L'AUDIT QUALIOPI ═════════════════════════════════════════════════ */
$audit_nu = $sans_commentaires( $lire( 'includes/audit/class-acdc-audit-core.php' ) );
$exiger( false !== strpos( $audit_nu, "'completion_certificate_document_url'   => 'certificat'," ), '14. L\'audit présente encore le certificat de réalisation sous le nom d\'attestation.' );
$exiger( false !== strpos( $audit_nu, "'end_training_certificate_document_url' => 'attestation'," ), '15. L\'audit présente encore l\'attestation sous le nom de certificat.' );
$exiger( false !== strpos( $audit_nu, "'certificat'        => array( 'label' => 'Certificat de réalisation'" ), '16. Le libellé « Certificat » sans qualificatif est resté : ce n\'est pas un nom légal.' );

$ajout = $corps( $audit_nu, 'private function add_doc( &$result, $fid, $scope, $scope_id, $type, $data )', 3000 );
$exiger( false !== strpos( $ajout, 'foreach ( (array) $panier as $deja )' ), '17. L\'agrégat empile encore sans dédoublonner.' );
$exiger( false !== strpos( $ajout, "trim( (string) ( \$deja['url'] ?? '' ) ) === \$adresse" ), '18. Le dédoublonnage ne se fait pas sur l\'adresse du fichier.' );
/* Le garde-fou : une entrée sans adresse ne doit JAMAIS être écartée —
   perdre une preuve serait pire que la répéter. */
$exiger( false !== strpos( $ajout, "if ( '' !== \$adresse ) {" ), '19. Une pièce sans adresse pourrait être écartée comme doublon : on perdrait une preuve.' );

$audit_rendu = $lire( 'includes/audit/class-acdc-audit-render.php' );
$exiger( false !== strpos( $audit_rendu, 'audit-couverture' ) && false !== strpos( $audit_rendu, 'audit-chip' ), '20. L\'écran d\'audit ne dit toujours pas ce qui MANQUE : une pièce absente y ressemble à une pièce non cherchée.' );
$exiger( false !== strpos( $audit_rendu, "\$types_presents[ (string) \$__d['type'] ] = true;" ), '21. La couverture n\'est pas calculée sur les pièces réellement trouvées.' );

/* ══ 4. LES STATISTIQUES ═════════════════════════════════════════════════ */
$noyau_nu = $sans_commentaires( $lire( 'includes/kernel/class-acdc-kernel-core-trait.php' ) );
$stats_tab = $corps( $rendu_nu, 'private function render_front_statistics_tab()', 26000 );
$exiger( false !== strpos( $stats_tab, '$this->acdc_prospect_a_devis( $pid_stat )' ), '22. Le taux de devis se déduit encore du libellé du statut.' );
$exiger( false !== strpos( $stats_tab, '$this->acdc_prospect_a_convention( $pid_stat )' ), '23. Le taux de convention se déduit encore du libellé du statut.' );
$exiger( false === strpos( $stats_tab, "strpos( \$status_lc, 'devis' )" ) && false === strpos( $stats_tab, "strpos( \$status_lc, 'convention' )" ), '24. La recherche du mot dans le libellé subsiste.' );
/* L'annulation, elle, N'A pas de pièce : elle doit rester lue sur le statut.
   Une correction qui l'emporterait ferait disparaître le taux d'annulation. */
$exiger( false !== strpos( $stats_tab, "strpos( \$status_lc, 'annul' )" ), '25. Le taux d\'annulation a été emporté : aucune pièce ne matérialise une annulation, le statut est bien sa source.' );

$devis = $corps( $noyau_nu, 'private function acdc_prospect_a_devis(', 2000 );
$exiger( false !== strpos( $devis, 'WHERE source_prospect_id = %d' ), '26. Le lecteur de devis n\'interroge pas la colonne qui relie une pièce à son prospect.' );
$conv = $corps( $noyau_nu, 'private function acdc_prospect_a_convention(', 2000 );
$exiger( false !== strpos( $conv, 'WHERE source_prospect_id = %d' ), '27. Le lecteur de convention n\'interroge pas source_prospect_id.' );

/* Les faux graphiques et les faux points d'aide. */
/* Les trois écrans de statistiques portaient le même faux camembert. Aucun
   ne doit rester : on cherche le motif, pas une occurrence. */
$exiger( false === strpos( $rendu_nu, 'conic-gradient(' ), '28. Un anneau peint en dur subsiste dans les statistiques : un graphique qui ne lit pas ses données est une affirmation fausse.' );
$exiger( false === strpos( $rendu_nu, '<div class="acdc-ped-ring"></div>' ) && false === strpos( $rendu_nu, '<div class="acdc-trainer-ring"></div>' ), '28 bis. Les faux anneaux des statistiques pédagogiques ou formateurs sont toujours rendus.' );
/* Un « ? » n'est retiré que s'il n'expliquait RIEN. Ceux qui portent une
   infobulle sont de la vraie aide et doivent rester : la règle vérifie donc
   qu'aucun point d'aide MUET ne subsiste, pas qu'ils ont tous disparu. */
$exiger( 0 === preg_match( '/<span class="acdc-[a-z-]*help-dot">\?<\/span>/', $rendu_nu ), '29. Un point d\'aide « ? » sans infobulle est toujours affiché : il promet une explication qui n\'existe pas.' );
$exiger( false !== strpos( $rendu_nu, 'acdc-ped-help-dot" title=' ), '29 bis. Les points d\'aide qui portaient une vraie explication ont été emportés avec les muets.' );
$barre = $corps( $rendu_nu, 'private function acdc_barre_repartition( $serie )', 3000 );
$exiger( false !== strpos( $barre, '( $effectif / $total )' ), '30. La barre de répartition ne calcule pas ses parts sur les effectifs réels.' );
$exiger( false !== strpos( $barre, 'if ( $total < 1 ) {' ), '31. Sans données, la barre serait tout de même dessinée — un dessin qui ment sur ce qu\'il montre.' );
$exiger( 1 === substr_count( $stats_tab, "'title' => \"Taux d'inscription\"" ) && false === strpos( $stats_tab, 'Taux de génération de convention' ), '32. La carte qui affichait le même nombre que « Taux d\'inscription » sous un autre nom est toujours là.' );

/* ══ 5. LES QUIZ, PAR THÉMATIQUE ═════════════════════════════════════════ */
$qz_nu = $sans_commentaires( $lire( 'includes/quizzes/class-acdc-quizzes-render-trait.php' ) );
$liste_qz = $corps( $qz_nu, 'public function render_qz_list_screen( $purpose )', 22000 );
$exiger( false !== strpos( $liste_qz, "\$thematique_filter = isset( \$_GET['filter_thematique'] )" ), '33. Le filtre par thématique n\'existe pas sur la liste des quiz.' );
$exiger( false !== strpos( $liste_qz, 'name="filter_thematique"' ), '34. Le filtre thématique n\'est pas affiché.' );
/* Le filtre doit agir sur les DEUX listes — formations proposées ET quiz
   affichés — sans quoi l'écran se contredit lui-même. */
$exiger( false !== strpos( $liste_qz, '$ids_thematique' ) && false !== strpos( $liste_qz, 'in_array( (int) ( $q->formation_id ?? 0 ), $ids_thematique, true )' ), '35. Le filtre thématique ne restreint pas les quiz affichés.' );
$exiger( false !== strpos( $qz_nu, 'acdc_thematiques_par_code' ), '36. Les thématiques seraient proposées sous leur code interne, pas sous leur libellé.' );
$qz_core = $sans_commentaires( $lire( 'includes/quizzes/class-acdc-quizzes-core-trait.php' ) );
$exiger( false !== strpos( $qz_core, "'id, title, code, modality, thematique, is_active, is_draft'" ), '37. La thématique n\'est pas ramenée avec les formations : aucun filtre ne pourrait fonctionner.' );

if ( $echecs ) {
    fwrite( STDERR, "ÉCHEC — scan-refontes-23-aout (" . count( $echecs ) . "/$verifs)\n" );
    foreach ( $echecs as $e ) { fwrite( STDERR, "  - $e\n" ); }
    exit( 1 );
}
fwrite( STDOUT, "OK — scan-refontes-23-aout : $verifs vérifications\n" );
exit( 0 );
