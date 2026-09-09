<?php
/**
 * ACDC 3.25.329 — Les constats du 23 août, un par un.
 *
 * CE QUE COUVRE CE BALAYAGE. Les points de la recette du 23/08 qui portent
 * sur des écrans plutôt que sur des documents (les documents ont leurs deux
 * balayages : scan-cachet-transparent et scan-certificat-normalise).
 *
 * LA RÈGLE DE RÉDACTION DES CONTRÔLES, apprise à nos dépens. Vérifier qu'une
 * chaîne EXISTE dans un fichier ne prouve rien : elle peut vivre dans un
 * commentaire, dans une autre fonction, ou dans le commentaire même qui
 * explique la correction. Chaque règle ci-dessous vise donc soit une forme
 * d'affectation ou d'appel exacte, soit le CORPS d'une fonction nommée, et
 * les sources sont lues sans leurs commentaires quand le risque existe.
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

/* Le code SANS ses commentaires : indispensable dès qu'un commentaire de
   correction cite le nom ou la chaîne que la règle recherche. */
$sans_commentaires = function ( $source ) {
    $sortie = '';
    foreach ( token_get_all( $source ) as $jeton ) {
        if ( is_array( $jeton ) ) {
            if ( in_array( $jeton[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
                $sortie .= "\n";
                continue;
            }
            $sortie .= $jeton[1];
            continue;
        }
        $sortie .= $jeton;
    }
    return $sortie;
};

/* Le corps d'une fonction, borné par la DÉCLARATION SUIVANTE — et non par un
   nombre de caractères. Un plafond en caractères laisse déborder la fonction
   d'à côté : un sabotage a montré qu'une règle passait encore alors que
   l'appel qu'elle cherchait avait été retiré, parce qu'un appel identique
   vivait quelques lignes plus loin, dans une AUTRE fonction. Une règle qui
   ne peut pas échouer ne prouve rien. */
$corps = function ( $source, $signature, $longueur = 9000 ) {
    $d = strpos( $source, $signature );
    if ( false === $d ) {
        return '';
    }
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
    if ( ! $condition ) {
        $echecs[] = $message;
    }
};

/* ══ 1. RÉPERTOIRES — « COMMANDITAIRE », ET PLUS « ENTREPRISE » ══════════ */
$rendu  = $lire( 'includes/kernel/class-acdc-kernel-render-trait.php' );
$noyau  = $lire( 'includes/kernel/class-acdc-kernel-core-trait.php' );
$onglet = $corps( $rendu, 'private function render_front_companies_tab(', 14000 );

$exiger( false !== strpos( $onglet, "acdc_get_action_page_title( \$action, 'Commanditaires', 'Créer un commanditaire'" ), '1. Le titre de la page des commanditaires n\'a pas été repris.' );
$exiger( false !== strpos( $onglet, '<th>Commanditaire</th>' ), '2. L\'en-tête de colonne dit encore « Entreprise ».' );
$exiger( false === strpos( $onglet, '<th>Prospect</th>' ), '3. La colonne Prospect est toujours là.' );
$exiger( false === strpos( $onglet, 'acdc-company-prospect-cell' ), '4. La cellule Prospect est toujours rendue.' );
$exiger( false !== strpos( $noyau, "add_submenu_page( 'acdc-of-dashboard', 'Commanditaires', 'Commanditaires'" ), '5. Le menu d\'administration dit encore « Entreprises ».' );

/* ══ 2. DEVIS ET FACTURES — LES DEUX PANNEAUX VIDES ══════════════════════ */
$fact = $lire( 'includes/documents-billing/class-acdc-documents-billing-render-trait.php' );
$hub_devis    = $corps( $fact, 'private function render_front_quotes_hub()', 2600 );
$hub_factures = $corps( $fact, 'private function render_front_invoices_hub()', 1200 );

$exiger( false !== strpos( $hub_devis, "\$this->render_front_quotes_list( 'action', true );" )
      && false !== strpos( $hub_devis, "\$this->render_front_quotes_list( 'ancillary', true );" ), '6. L\'onglet Devis n\'affiche pas les deux listes.' );
$exiger( false !== strpos( $hub_factures, "\$this->render_front_invoices_list( 'action', true );" )
      && false !== strpos( $hub_factures, "\$this->render_front_invoices_list( 'ancillary', true );" ), '7. L\'onglet Factures n\'affiche pas les deux listes.' );
$exiger( false !== strpos( $fact, "private function render_front_quotes_list( \$scope, \$combinee = false )" ), '8. La liste des devis n\'accepte pas le mode combiné.' );
$exiger( false !== strpos( $fact, "private function render_front_invoices_list( \$scope, \$combinee = false )" ), '9. La liste des factures n\'accepte pas le mode combiné.' );

/* ══ 3. LA COLONNE ACTIONS DES FACTURES ══════════════════════════════════ */
$fact_nu = $sans_commentaires( $fact );
$exiger( false === strpos( $fact_nu, 'acdc-row-actions-menu-cell' ), '10. Le tableau des factures utilise encore sa propre cellule d\'actions.' );
$exiger( false !== strpos( $fact_nu, 'acdc-actions-cell-icons acdc-invoices-actions-cell' ), '11. La cellule d\'actions des factures n\'utilise pas le balisage commun.' );
$exiger( false !== strpos( $fact_nu, 'data-acdc-row-menu-dropdown' ), '12. Le menu des factures n\'expose pas le panneau attendu par le mécanisme commun.' );
$exiger( false === strpos( $fact_nu, '.acdc-row-menu{position:absolute' ), '13. La feuille de style locale repose le menu en absolu dans le tableau.' );
$exiger( false !== strpos( $fact_nu, '<th>ACTIONS</th>' ), '14. Les deux colonnes sans en-tête n\'ont pas été réunies en une colonne ACTIONS.' );

$catalogue = $sans_commentaires( $lire( 'includes/settings-catalog/class-acdc-settings-catalog-render-trait.php' ) );
$exiger( false === strpos( $catalogue, '.acdc-row-menu-dropdown{position:absolute' ), '15. Le catalogue redéfinit encore la position du menu pour toute la page.' );
$exiger( false === strpos( $catalogue, 'function closeCatalogMenus' ), '16. Le script du catalogue referme encore les menus des autres écrans.' );

/* ══ 4. QUALITÉ & CONFORMITÉ, ET L'AUDIT QUALIOPI ════════════════════════ */
$exiger( false !== strpos( $rendu, "'type' => 'section',\n        'label' => 'Qualité & conformité'," ), '17. Qualité & conformité n\'est pas devenue une section principale.' );
$pos_qualite = strpos( $rendu, "'label' => 'Qualité & conformité'," );
$pos_compta  = strpos( $rendu, "'label' => 'Comptabilité'," );
$pos_outils  = strpos( $rendu, "'label' => 'Outils et statistiques'," );
$exiger( $pos_compta && $pos_qualite && $pos_outils && $pos_compta < $pos_qualite && $pos_qualite < $pos_outils, '18. La section Qualité & conformité n\'est pas placée entre Comptabilité et Outils et statistiques.' );
$exiger( 1 === substr_count( $rendu, "array( 'tab' => 'audit', 'label' => 'Audit Qualiopi'" ), '19. L\'entrée Audit Qualiopi est absente ou dupliquée.' );
$pos_audit    = strpos( $rendu, "array( 'tab' => 'audit', 'label' => 'Audit Qualiopi'" );
$pos_reglages = strpos( $rendu, "array( 'tab' => 'settings', 'label' => 'Réglages'" );
$exiger( $pos_audit && $pos_reglages && $pos_audit < $pos_reglages, '20. L\'audit Qualiopi est resté rangé sous Paramètres.' );

/* ══ 5. LE CALENDRIER ET LA LISTE DES SÉANCES ════════════════════════════ */
$seances_noyau = $lire( 'includes/sessions/class-acdc-sessions-core-trait.php' );
$cal = $corps( $seances_noyau, 'private function get_sessions_for_calendar( $year, $month )', 3000 );
$exiger( false !== strpos( $cal, 'LEFT JOIN {$this->thematique_table} t ON t.code = f.thematique' ), '21. Le calendrier ne joint pas la thématique : aucune couleur ne peut arriver à l\'écran.' );
$exiger( false !== strpos( $cal, 't.couleur_hex AS thematique_couleur' ), '22. La couleur de la thématique n\'est pas sélectionnée.' );
$liste = $corps( $seances_noyau, 'private function get_sessions() {', 1600 );
$exiger( false !== strpos( $liste, 'LEFT JOIN {$this->thematique_table} t ON t.code = f.thematique' ), '23. La liste des séances ne joint pas la thématique.' );

$seances_rendu = $lire( 'includes/sessions/class-acdc-sessions-render-trait.php' );
$exiger( false !== strpos( $seances_rendu, "\$est_passee = ( \$day_key < \$today_key );" ), '24. Le calendrier ne calcule pas si la journée est passée.' );
$exiger( false !== strpos( $seances_rendu, "\$classes[] = 'is-passee';" ), '25. La journée passée ne reçoit pas sa classe.' );
$exiger( false !== strpos( $seances_rendu, '--acdc-cal-teinte:' ), '26. La teinte de la thématique n\'est pas transmise à l\'élément.' );
$exiger( false === strpos( $seances_rendu, '..acdc-table-sessions-validated' ), '27. Le sélecteur invalide à double point est toujours là.' );

$css_cal = $lire( 'assets/css/calendar.css' );
$exiger( false !== strpos( $css_cal, '.acdc-calendar-day.is-passee' ), '28. Aucune règle n\'habille la journée passée.' );
$exiger( false !== strpos( $css_cal, 'repeating-linear-gradient' ) && false !== strpos( $css_cal, '.acdc-calendar-event.has-teinte' ), '29. Le hachurage ou la teinte manque à la feuille de style.' );
$exiger( false !== strpos( $css_cal, '.acdc-calendar-day.is-passee:hover .acdc-calendar-events' ), '30. Le passé estompé ne redevient jamais lisible : c\'est cacher, pas estomper.' );

/* ══ 6. « FORMATION TERMINÉE » DANS LA COLONNE STATUT ════════════════════ */
$noyau_nu = $sans_commentaires( $noyau );
$exiger( false !== strpos( $noyau_nu, 'private function acdc_registration_formation_terminee(' ), '31. Le lecteur de fin de parcours n\'existe pas.' );
$fin = $corps( $noyau_nu, 'private function acdc_registration_formation_terminee(', 2600 );
$exiger( false !== strpos( $fin, 'AND is_draft = 0' ), '32. Les séances en brouillon compteraient comme des séances réelles.' );
$exiger( false !== strpos( $fin, 'company_id = %d' ), '33. Une séance de la même formation animée pour une autre entreprise compterait dans ce parcours.' );
$dossiers = $lire( 'includes/dossiers-contracts/class-acdc-dossiers-contracts-render-trait.php' );
$exiger( false !== strpos( $dossiers, "\$fin_parcours = \$this->acdc_registration_formation_terminee( \$entry );" ), '34. La liste des apprenants inscrits n\'appelle pas le lecteur.' );
$exiger( false !== strpos( $dossiers, '>Formation terminée</span>' ), '35. Le statut « Formation terminée » ne s\'affiche pas.' );
$exiger( false === strpos( $noyau, 'Formation terminée - ---' ), '36. Le libellé traîne encore son « - --- » orphelin.' );

/* ══ 7. LES PASTILLES LISENT UN FAIT, PLUS SEULEMENT UN PDF ══════════════ */
$score = $corps( $noyau_nu, 'private function compute_registration_completude_score(', 4200 );
$exiger( false !== strpos( $score, "\$this->acdc_registration_quiz_passe( \$registration, 'assessment' )" ), '37. La pastille « Évaluation des acquis » n\'attend toujours qu\'un PDF.' );
$exiger( false !== strpos( $score, "\$this->acdc_registration_quiz_passe( \$registration, 'diagnostic' )" ), '38. La pastille « Évaluation diagnostique » n\'attend toujours qu\'un PDF.' );
$exiger( false !== strpos( $score, "\$this->acdc_registration_enquete_repondue( \$registration, 'hot_survey' )" ), '39. La pastille « Enquête à chaud » n\'attend toujours qu\'un PDF.' );
$exiger( false !== strpos( $score, "\$this->acdc_registration_enquete_repondue( \$registration, 'cold_survey' )" ), '40. La pastille « Enquête à froid » n\'attend toujours qu\'un PDF.' );
$quiz_passe = $corps( $noyau_nu, 'private function acdc_registration_quiz_passe(', 2400 );
$exiger( false !== strpos( $quiz_passe, "'qp.registration_id = %d'" ) && false !== strpos( $quiz_passe, "'qp.learner_id = %d'" ) && false !== strpos( $quiz_passe, "implode( ' OR ', \$cles )" ), '41. Le lecteur de quiz n\'interroge pas les deux clés réunies par un OU.' );
$enq = $corps( $noyau_nu, 'private function acdc_registration_enquete_repondue(', 2400 );
$exiger( false !== strpos( $enq, "'p.registration_id = %d'" ) && false !== strpos( $enq, "'p.apprenant_id = %d'" ) && false !== strpos( $enq, "implode( ' OR ', \$cles )" ), '42. Le lecteur d\'enquête n\'interroge pas les deux clés réunies par un OU.' );
$exiger( false !== strpos( $enq, 'p.finished_at IS NOT NULL' ), '43. Une enquête ouverte mais non terminée compterait comme répondue.' );

/* ══ 8. LE RATTACHEMENT D'UN PARTICIPANT ÉCRIT AUSSI LE DOSSIER ══════════ */
$quiz_actions = $sans_commentaires( $lire( 'includes/quizzes/class-acdc-quizzes-actions-trait.php' ) );
$attache = $corps( $quiz_actions, 'public function handle_acdc_of_qz_attach_participant()', 3400 );
/* La forme d'APPEL exacte, et non le simple nom : un sabotage a montré
   qu'une garde method_exists() portant le même nom suffisait à faire passer
   la règle alors que l'appel avait disparu. */
$exiger( false !== strpos( $attache, "\$this->qz_resolve_registration_id_and_generate_pdf( \$participant," ), '44. Le rattachement n\'écrit toujours que learner_id : le moteur des pièces de fin, qui interroge registration_id, ne verrait rien.' );
$exiger( false !== strpos( $attache, "in_array( \$learner_id, \$ids_inscrits, true )" ), '45. Le rattachement ne vérifie plus que l\'apprenant appartient à la formation.' );

$completion = $sans_commentaires( $lire( 'includes/sessions/class-acdc-completion-documents-trait.php' ) );
$assess = $corps( $completion, 'private function acdc_completion_assessment_result(', 2600 );
$exiger( false !== strpos( $assess, "implode( ' OR ', \$cles )" ), '46. Le moteur des pièces de fin cherche encore par le dossier SEUL.' );
$exiger( false !== strpos( $assess, "'qp.learner_id = %d'" ), '47. La clé « apprenant » a disparu du moteur des pièces de fin.' );

/* ══ 9. L'ENQUÊTE ENTREPRISE AVAIT UN REPLI QUI NE POUVAIT PAS SERVIR ════
   Il lisait « enterprise_contact_email » sur la fiche du commanditaire. Cette
   clé existe dans le plugin — mais c'est celle de la fiche de L'ORGANISME DE
   FORMATION. La table des commanditaires range l'adresse dans « email ». La
   condition était donc toujours fausse, et l'enquête entreprise partait sans
   destinataire dès qu'aucun contact rattaché ne portait d'adresse. */
$quest = $sans_commentaires( $lire( 'includes/questionnaires/class-acdc-questionnaires-core-trait.php' ) );
$cibles = $corps( $quest, 'private function get_questionnaire_delivery_targets( $session )', 9000 );
$exiger( '' !== $cibles && false === strpos( $cibles, 'enterprise_contact_email' ), '48. Le repli de l\'enquête entreprise lit encore une clé de la fiche de l\'organisme.' );
$exiger( false !== strpos( $cibles, "\$company->email" ), '49. Le repli de l\'enquête entreprise ne lit pas la colonne que l\'écran remplit.' );
/* La moitié qu'on emporterait : l'ABSENCE de destinataire doit rester
   visible. Une enquête qui ne trouve personne ne doit surtout pas être
   déclarée envoyée — c'est ce que la 3.25.302 avait corrigé. */
$exiger( false !== strpos( $quest, "\$__acdc_statut = 'sans_destinataire';" ), '50. Une enquête sans destinataire redeviendrait « envoyée » : le silence reviendrait avec.' );

if ( $echecs ) {
    fwrite( STDERR, "ÉCHEC — scan-recette-23-aout (" . count( $echecs ) . "/$verifs)\n" );
    foreach ( $echecs as $e ) { fwrite( STDERR, "  - $e\n" ); }
    exit( 1 );
}
fwrite( STDOUT, "OK — scan-recette-23-aout : $verifs vérifications\n" );
exit( 0 );
