<?php
/**
 * ACDC Noyau — purge sélective des données du plugin.
 *
 * Le bouton « Suppression totale » existe depuis longtemps et il est fait pour
 * un cas unique : tout jeter. Pendant une recette, ce n'est presque jamais ce
 * que l'on veut. On veut repartir d'un dossier commercial vierge en gardant le
 * catalogue de formations ; ou vider les séances sans perdre les apprenants ;
 * ou tout effacer SAUF les financeurs, qui sont, seuls dans cette base, des
 * données réelles.
 *
 * D'où cet écran : une case par ensemble, et la case des financeurs décochée,
 * isolée, et protégée par sa propre confirmation.
 *
 * TROIS PRINCIPES, et le troisième est celui qui compte le plus.
 *
 *  1. On ne coche pas des ÉCRANS, on coche des ENSEMBLES DE DONNÉES.
 *     Le menu compte une trentaine d'entrées « Documents » — convocations,
 *     certificats, attestations, résultats — qui sont toutes des VUES sur les
 *     mêmes lignes d'inscription. Offrir une case par entrée de menu aurait été
 *     fidèle au menu et mensonger sur l'effet : décocher « Convocations » tout
 *     en cochant « Dossiers de formation » ne peut rien vouloir dire, la
 *     convocation étant une colonne du dossier. Chaque groupe indique donc où
 *     il se trouve dans le menu, et ce qu'il emporte réellement.
 *
 *  2. Rien ne part sans copie. La sauvegarde de sécurité est prise avant, et
 *     son échec annule la purge — pas d'avertissement, pas de « continuer quand
 *     même ».
 *
 *  3. Ce qui est supprimé est COMPTÉ et JOURNALISÉ, ligne par ligne. Une purge
 *     qui ne dit pas ce qu'elle a emporté oblige à faire confiance ; on préfère
 *     pouvoir vérifier.
 *
 * @since 3.25.209
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

trait ACDC_Kernel_Purge_Trait {

  /**
   * Les ensembles proposés à la suppression, dans l'ordre du menu.
   *
   * `menu`      : où l'ensemble se trouve dans la navigation, pour que David
   *               retrouve à l'écran ce qu'il coche ici.
   * `emporte`   : ce qui disparaît, dit en français et sans euphémisme.
   * `garde`     : ce qui SURVIT, quand la nuance n'est pas évidente. C'est la
   *               ligne qui évite les mauvaises surprises.
   * `tables`    : les tables réellement vidées.
   * `options`   : motifs LIKE d'options supprimées.
   * `sensitive` : l'ensemble contient des données réelles ; il est décoché,
   *               isolé, et demande sa propre confirmation.
   */
  private function acdc_purge_groups() {
    global $wpdb;

    $qz = $wpdb->prefix . 'acdc_of_qz_';

    $groups = array(

      'crm' => array(
        'label'   => 'Prospects et suivi commercial',
        'menu'    => 'Commercial › CRM',
        'emporte' => 'Prospects, rendez-vous, activités commerciales et rendez-vous préalables.',
        'tables'  => array(
          $this->prospect_table,
          $this->prospect_rdv_table,
          $this->prospect_activity_table,
          $this->pre_meeting_table,
        ),
      ),

      'needs' => array(
        'label'   => 'Recueils des besoins',
        'menu'    => 'Commercial › CRM › Recueil des besoins',
        'emporte' => 'Les recueils des besoins.',
        'garde'   => 'La bibliothèque de blocs et de questions, qui est un paramétrage et non une donnée de dossier.',
        'tables'  => array( $this->need_table ),
      ),

      'workflow' => array(
        'label'   => 'Parcours du workflow',
        'menu'    => 'Commercial › Workflow',
        'emporte' => 'Les parcours et toutes leurs étapes planifiées ou jouées.',
        'garde'   => 'La configuration du workflow : délais, relances, mode simulation, destinataires autorisés.',
        'tables'  => array( $this->workflow_run_table, $this->workflow_step_table ),
      ),

      'proposals' => array(
        'label'   => 'Propositions, devis et factures',
        'menu'    => 'Comptabilité › Devis & Factures',
        'emporte' => 'Propositions commerciales, devis, factures et avoirs.',
        'garde'   => 'Les paramètres de facturation et les mentions légales.',
        'tables'  => array(
          $wpdb->prefix . 'acdc_of_proposals',
          $this->quote_table,
          $this->invoice_table,
        ),
      ),

      'contracts' => array(
        'sous_ensembles' => array(
          'conv'  => array( 'label' => 'Conventions et contrats', 'tables' => array( $this->registration_contract_table ) ),
          'files' => array( 'label' => 'Dossiers de formation et inscriptions', 'tables' => array( $this->training_registration_table ) ),
        ),
        'label'   => 'Conventions, contrats et dossiers de formation',
        'menu'    => 'Actions de formation › Inscription / Suivi',
        'emporte' => 'Conventions et contrats d’inscription, dossiers de formation, inscriptions en cours et validées.',
        'tables'  => array(
          $this->registration_contract_table,
          $this->training_registration_table,
        ),
      ),

      'sessions' => array(
        'sous_ensembles' => array(
          'seances' => array( 'label' => 'Séances et groupes', 'tables' => array( $this->session_table, $this->group_table ) ),
          'emarg'   => array( 'label' => 'Feuilles d’émargement et signatures', 'tables' => array( $wpdb->prefix . 'acdc_of_emarg_sessions', $wpdb->prefix . 'acdc_of_emarg_learners' ) ),
          'ressources' => array( 'label' => 'Ressources et cahier de texte du formateur', 'tables' => array( $this->trainer_resource_table, $this->trainer_logbook_table ) ),
        ),
        'label'   => 'Séances, groupes et émargements',
        'menu'    => 'Actions de formation › Séances',
        'emporte' => 'Séances, groupes, feuilles d’émargement et signatures de présence.',
        'tables'  => array(
          $this->session_table,
          $this->group_table,
          $wpdb->prefix . 'acdc_of_emarg_sessions',
          $wpdb->prefix . 'acdc_of_emarg_learners',
          /* Les documents déposés par le formateur sur une séance partent avec
             elle : ils n'ont aucun sens sans la séance qu'ils documentent. */
          $this->trainer_resource_table,
          $this->trainer_logbook_table,
        ),
      ),

      'learners' => array(
        'sous_ensembles' => array(
          'fiches'  => array( 'label' => 'Fiches apprenants', 'tables' => array( $this->learner_table ) ),
          'comptes' => array( 'label' => 'Comptes et sessions d’extranet', 'tables' => array( $this->learner_portal_account_table, $this->learner_portal_token_table, $this->learner_portal_session_table ) ),
          'journaux'=> array( 'label' => 'Journaux de connexion', 'tables' => array( $this->learner_portal_log_table ) ),
        ),
        'label'   => 'Apprenants et extranet apprenant',
        'menu'    => 'Config. pré-formation › Répertoires › Apprenants',
        'emporte' => 'Le répertoire des apprenants, leurs comptes d’extranet, leurs sessions de connexion et leurs journaux.',
        'tables'  => array(
          $this->learner_table,
          $this->learner_portal_account_table,
          $this->learner_portal_token_table,
          $this->learner_portal_session_table,
          $this->learner_portal_log_table,
        ),
      ),

      'companies' => array(
        'sous_ensembles' => array(
          'entreprises' => array( 'label' => 'Commanditaires', 'tables' => array( $this->company_table ) ),
          'contacts'    => array( 'label' => 'Contacts rattachés', 'tables' => array( $this->contact_table ) ),
        ),
        'label'   => 'Commanditaires et contacts',
        'menu'    => 'Config. pré-formation › Répertoires › Commanditaires',
        'emporte' => 'Entreprises commanditaires et contacts rattachés.',
        'tables'  => array( $this->company_table, $this->contact_table ),
      ),

      'trainers' => array(
        'sous_ensembles' => array(
          'fiches'      => array( 'label' => 'Fiches formateurs', 'tables' => array( $this->trainer_table ) ),
          'contrats'    => array( 'label' => 'Contrats de mission', 'tables' => array( $this->trainer_contract_table ) ),
          'bilans'      => array( 'label' => 'Bilans de fin de mission', 'tables' => array( $this->trainer_evaluation_table ) ),
          'bibliotheque'=> array( 'label' => 'Bibliothèque personnelle', 'tables' => array( $this->trainer_document_table ) ),
          'comptes'     => array( 'label' => 'Comptes et sessions de portail', 'tables' => array( $this->trainer_portal_account_table, $this->trainer_portal_token_table, $this->trainer_portal_session_table ) ),
          'journaux'    => array( 'label' => 'Journaux de connexion', 'tables' => array( $this->trainer_portal_log_table ) ),
        ),
        'label'   => 'Formateurs et portail formateur',
        'menu'    => 'Config. pré-formation › Répertoires › Formateurs',
        'emporte' => 'Le répertoire des formateurs, leurs contrats de mission, leurs bilans, leur bibliothèque personnelle et leurs comptes de portail.',
        'tables'  => array(
          $this->trainer_table,
          $this->trainer_contract_table,
          $this->trainer_evaluation_table,
          $this->trainer_document_table,
          $this->trainer_portal_account_table,
          $this->trainer_portal_token_table,
          $this->trainer_portal_session_table,
          $this->trainer_portal_log_table,
        ),
      ),

      'formations' => array(
        /* ACDC 3.25.322 — LE CATALOGUE N'EST PAS UNE DONNÉE DE DOSSIER.
           Les vingt formations et leurs thématiques sont un travail de fond,
           construit une fois et réutilisé à chaque dossier — exactement comme le
           répertoire des financeurs. Les effacer avec un parcours de test, c'est
           perdre des semaines de saisie pour repartir à zéro sur un essai.
           Ce bloc rejoint donc les financeurs : décoché par « tout cocher », et
           libéré seulement par un mot saisi exprès. */
        'sensitive' => true,
        'mot'       => 'FORMATIONS',
        'sous_ensembles' => array(
          'formations'  => array( 'label' => 'Formations du catalogue', 'tables' => array( $this->formation_table ) ),
          'thematiques' => array( 'label' => 'Thématiques', 'tables' => array( $this->thematique_table ) ),
        ),
        'label'   => 'Formations et thématiques',
        'menu'    => 'Config. pré-formation › Répertoires › Formations',
        'emporte' => 'Le catalogue des formations et les thématiques.',
        'garde'   => 'Les réglages du catalogue public et les pages WordPress.',
        'tables'  => array( $this->formation_table, $this->thematique_table ),
      ),

      'quizzes' => array(
        'sous_ensembles' => array(
          'modeles'       => array( 'label' => 'Quiz, questions et objectifs', 'tables' => array( $this->quiz_table, $this->evaluation_table, $qz . 'quizzes', $qz . 'questions', $qz . 'answers', $qz . 'objectives' ) ),
          'participations'=> array( 'label' => 'Passations, participants et réponses', 'tables' => array( $qz . 'sessions', $qz . 'participants', $qz . 'player_answers' ) ),
          'journaux'      => array( 'label' => 'Journaux du module', 'tables' => array( $qz . 'logs' ) ),
        ),
        'label'   => 'Quiz, tests de positionnement et évaluations',
        'menu'    => 'Évaluation & Enquêtes › Avant / Pendant la formation',
        'emporte' => 'Les quiz et leurs questions, les évaluations, ainsi que toutes les participations et réponses.',
        'tables'  => array(
          $this->quiz_table,
          $this->evaluation_table,
          $qz . 'quizzes',
          $qz . 'questions',
          $qz . 'answers',
          $qz . 'objectives',
          $qz . 'sessions',
          $qz . 'participants',
          $qz . 'player_answers',
          $qz . 'logs',
        ),
      ),

      'surveys' => array(
        'sous_ensembles' => array(
          'campagnes' => array( 'label' => 'Campagnes et participants', 'tables' => array( $this->questionnaire_session_table, $this->questionnaire_participant_table ) ),
          'reponses'  => array( 'label' => 'Réponses reçues', 'tables' => array( $this->questionnaire_answer_table ) ),
          'journaux'  => array( 'label' => 'Actions et journaux d’envoi', 'tables' => array( $this->questionnaire_action_table, $this->questionnaire_log_table ) ),
        ),
        'label'   => 'Enquêtes et questionnaires',
        'menu'    => 'Évaluation & Enquêtes › Après la formation, Enquêtes par public',
        'emporte' => 'Campagnes d’enquêtes, participants, réponses, actions et journaux d’envoi.',
        'garde'   => 'Les modèles de questionnaires ne sont supprimés que si vous cochez aussi « Modèles de questionnaires ».',
        'tables'  => array(
          $this->questionnaire_session_table,
          $this->questionnaire_participant_table,
          $this->questionnaire_answer_table,
          $this->questionnaire_action_table,
          $this->questionnaire_log_table,
        ),
      ),

      'survey_models' => array(
        'label'   => 'Modèles de questionnaires',
        'menu'    => 'Évaluation & Enquêtes',
        'emporte' => 'Les modèles de questionnaires — c’est un paramétrage, pas une donnée de dossier.',
        'tables'  => array( $this->questionnaire_model_table ),
      ),

      'need_analyses' => array(
        'label'   => 'Analyses du besoin',
        'menu'    => 'Actions de formation › Inscription / Suivi › Analyse du besoin',
        'emporte' => 'Les analyses du besoin renseignées.',
        'garde'   => 'Les blocs et questions de la bibliothèque, qui servent à en construire de nouvelles.',
        'tables'  => array( $this->need_analysis_table ),
      ),

      'need_library' => array(
        'label'   => 'Bibliothèque de blocs et de questions',
        'menu'    => 'Actions de formation › Analyse du besoin › Bibliothèque des blocs',
        'emporte' => 'Les blocs et les questions réutilisables.',
        'tables'  => array( $this->need_block_table, $this->need_question_table ),
      ),

      'signatures' => array(
        'label'   => 'Signature électronique',
        'menu'    => 'Transverse — devis, conventions, émargements',
        'emporte' => 'Les demandes de signature et leur piste d’audit.',
        'tables'  => array(
          $wpdb->prefix . 'acdc_sig_requests',
          $wpdb->prefix . 'acdc_sig_audit',
        ),
      ),

      'quality' => array(
        'label'   => 'Qualité et conformité',
        'menu'    => 'Évaluation & Enquêtes › Qualité & conformité',
        'emporte' => 'Réclamations, veille, améliorations continues, conseil de perfectionnement, partenaires PSH, locaux, sous-traitants, prestations et BPF.',
        'tables'  => array( $this->complaint_table, $this->watch_items_table ),
        'options' => array( 'acdc_of_quality_%', 'acdc_of_bpf_%', 'acdc_of_psh_%', 'acdc_of_improvement_%' ),
      ),

      'marketing' => array(
        'label'   => 'Communication et campagnes',
        'menu'    => 'Communication',
        'emporte' => 'Campagnes, scénarios, modèles d’e-mails, formulaires, contacts, listes, segments, étiquettes, imports, désinscriptions, journaux et file d’attente.',
        'garde'   => 'La page publique du module et l’identifiant qui la désigne.',
        'options' => array( 'acdc_of_marketing_%' ),
        'keep_options' => array( 'acdc_of_marketing_public_page_id' ),
      ),

      'documents' => array(
        'label'   => 'Documents générés et fichiers sur le disque',
        'menu'    => 'Actions de formation › Documents',
        'emporte' => 'Le répertoire des documents ET les fichiers produits par le plugin dans le dossier des téléversements — PDF de conventions, devis, attestations, programmes.',
        'tables'  => array( $this->document_table ),
        'files'   => true,
      ),

      'logs' => array(
        'label'   => 'Journaux système',
        'menu'    => 'Transverse',
        'emporte' => 'Le journal technique du plugin.',
        'garde'   => 'Rien d’autre : ce journal ne porte aucune donnée de dossier.',
        'tables'  => array( $this->system_log_table ),
      ),

      /* ─────────────────────────────────────────────────────────────────
         ET, TOUT SEUL EN BAS, L'ENSEMBLE QUI N'EST PAS FICTIF.
         ───────────────────────────────────────────────────────────────── */
      'funders' => array(
        'label'     => 'Financeurs (OPCO)',
        'menu'      => 'Config. pré-formation › Répertoires › Financeurs',
        'emporte'   => 'Le répertoire des financeurs.',
        'garde'     => '',
        'tables'    => array( $this->funder_table ),
        'sensitive' => true,
        'mot'       => 'FINANCEURS',
      ),
    );

    /* Une table dont la propriété n'est pas renseignée ne doit pas se glisser
       dans une requête sous forme de chaîne vide : on nettoie ici plutôt que
       d'y penser à chaque appel. */
    foreach ( $groups as $key => $group ) {
      $tables = array_filter( array_map( 'strval', (array) ( $group['tables'] ?? array() ) ) );

      /* ACDC 3.25.322 — LES SOUS-ENSEMBLES SONT LA VÉRITÉ, PAS UN DOUBLON.
         Un bloc qui en déclare voit sa liste de tables RECONSTRUITE à partir
         d'eux. Sans cette règle, les deux listes divergeraient au premier ajout
         de table : on cocherait un sous-ensemble en croyant tout tenir, pendant
         qu'une table oubliée partirait quand même — ou ne partirait jamais.
         Une seule source, et elle est celle que l'écran montre. */
      if ( ! empty( $group['sous_ensembles'] ) && is_array( $group['sous_ensembles'] ) ) {
        $depuis_sous = array();
        foreach ( $group['sous_ensembles'] as $sous_cle => $sous ) {
          $sous_tables = array_values( array_unique( array_filter( array_map( 'strval', (array) ( $sous['tables'] ?? array() ) ) ) ) );
          $groups[ $key ]['sous_ensembles'][ $sous_cle ]['tables'] = $sous_tables;
          $depuis_sous = array_merge( $depuis_sous, $sous_tables );
        }
        /* Une table du bloc qu'aucun sous-ensemble ne réclame serait
           silencieusement épargnée : on la rattache au premier plutôt que de la
           laisser en dehors de toute case. */
        $orphelines = array_diff( $tables, $depuis_sous );
        if ( ! empty( $orphelines ) ) {
          $premiere = array_key_first( $groups[ $key ]['sous_ensembles'] );
          $groups[ $key ]['sous_ensembles'][ $premiere ]['tables'] = array_values( array_unique(
            array_merge( $groups[ $key ]['sous_ensembles'][ $premiere ]['tables'], $orphelines )
          ) );
          $depuis_sous = array_merge( $depuis_sous, $orphelines );
        }
        $tables = $depuis_sous;
      }

      $groups[ $key ]['tables'] = array_values( array_unique( $tables ) );
    }

    return $groups;
  }

  /**
   * Le mot à saisir pour libérer la suppression des financeurs.
   *
   * Il est différent de celui de la purge générale, et c'est délibéré : deux
   * gestes distincts pour deux décisions distinctes. Recopier machinalement la
   * même phrase deux fois ne serait pas une confirmation.
   */
  private function acdc_purge_funders_confirmation_word() {
    return $this->acdc_purge_mot_confirmation( 'funders' );
  }

  /**
   * ACDC 3.25.322 — Le mot propre à un ensemble protégé.
   *
   * Il n'y en avait qu'un, pour les financeurs, écrit en dur. Le catalogue des
   * formations mérite la même protection, et d'autres suivront : la règle vaut
   * mieux qu'une seconde exception.
   */
  private function acdc_purge_mot_confirmation( $cle ) {
    $groups = $this->acdc_purge_groups();
    if ( ! empty( $groups[ $cle ]['mot'] ) ) {
      return strtoupper( (string) $groups[ $cle ]['mot'] );
    }
    return strtoupper( (string) $cle );
  }

  /* ═══════════════════════════════════════════════════════════════════
     L'ÉCRAN
     ═══════════════════════════════════════════════════════════════════ */

  /**
   * ACDC 3.25.322 — LES CASES À L'INTÉRIEUR D'UN BLOC.
   *
   * Un bloc effaçait tout ou rien. Or « Formateurs et portail formateur »
   * emporte d'un coup les fiches, les contrats de mission, les bilans, la
   * bibliothèque et les comptes de portail : on veut souvent remettre à zéro les
   * comptes d'un test sans perdre les fiches et les contrats signés.
   *
   * Chaque sous-ensemble a donc sa case. Elles sont cochées avec le bloc et
   * décochées avec lui — mais on peut en retirer une à la main, et c'est tout
   * l'intérêt. Un bloc dont on décoche TOUS les sous-ensembles ne supprime rien,
   * même s'il reste coché : c'est la lecture la moins surprenante.
   *
   * Les blocs sans sous-ensembles ne changent pas d'un pixel.
   */
  private function acdc_rendre_sous_ensembles( $cle, $group, $counts = array() ) {
    if ( empty( $group['sous_ensembles'] ) || ! is_array( $group['sous_ensembles'] ) ) {
      return;
    }
    ?>
    <span class="acdc-purge-sous">
      <?php foreach ( $group['sous_ensembles'] as $sous_cle => $sous ) : ?>
        <label class="acdc-purge-sous-item">
          <input type="checkbox"
                 name="acdc_purge_sous[<?php echo esc_attr( $cle ); ?>][]"
                 value="<?php echo esc_attr( $sous_cle ); ?>"
                 data-acdc-purge-sub="<?php echo esc_attr( $cle ); ?>"
                 checked>
          <span><?php echo esc_html( $sous['label'] ); ?></span>
        </label>
      <?php endforeach; ?>
    </span>
    <?php
  }

  private function render_selective_purge_panel() {
    $groups     = $this->acdc_purge_groups();
    /* Le blocage se lit par la MÊME fonction que celle qui refuse l'action.
       Un écran qui recalcule la règle de son côté finit toujours par afficher
       un bouton actif devant un serveur qui dit non. */
    $blocked    = $this->is_production_purge_blocked();
    $phrase     = $this->get_plugin_data_purge_confirmation_phrase();

    $counts = $this->acdc_purge_group_counts( $groups );
    ?>
    <div class="acdc-panel acdc-selective-purge" style="margin-top:18px;border-color:#E0B96D;background:#fffdf6;">
      <h3 style="color:#8a6300;">Remise à zéro sélective</h3>
      <p>
        Cochez ce que vous voulez effacer. Chaque ensemble indique où il se trouve dans le menu et ce qu’il emporte
        réellement. Une sauvegarde de sécurité est prise avant toute suppression : si elle échoue, rien n’est touché.
      </p>
      <p class="acdc-help">
        Les entrées de menu qui n’apparaissent pas ici sont des vues sur les données ci-dessous, et non des ensembles
        séparés : les vingt écrans « Documents » lisent tous les mêmes dossiers de formation, il n’y a donc rien à y
        supprimer indépendamment.
      </p>

      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'acdc_purge_selected_data' ); ?>
        <input type="hidden" name="action" value="acdc_purge_selected_data">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-dashboard"><?php endif; ?>

        <p style="margin:16px 0 10px;">
          <button type="button" class="acdc-button acdc-button-soft" data-acdc-purge-all>Tout cocher (sauf les ensembles protégés)</button>
          <button type="button" class="acdc-button acdc-button-soft" data-acdc-purge-none>Tout décocher</button>
        </p>

        <div class="acdc-purge-grid">
          <?php foreach ( $groups as $key => $group ) : ?>
            <?php if ( ! empty( $group['sensitive'] ) ) { continue; } ?>
            <label class="acdc-purge-item">
              <input type="checkbox" name="acdc_purge_groups[]" value="<?php echo esc_attr( $key ); ?>" data-acdc-purge-box>
              <span>
                <strong><?php echo esc_html( $group['label'] ); ?></strong>
                <?php if ( isset( $counts[ $key ] ) ) : ?>
                  <em class="acdc-purge-count"><?php echo esc_html( $counts[ $key ] ); ?> ligne<?php echo (int) $counts[ $key ] > 1 ? 's' : ''; ?></em>
                <?php endif; ?>
                <small class="acdc-purge-menu"><?php echo esc_html( $group['menu'] ); ?></small>
                <small><?php echo esc_html( $group['emporte'] ); ?></small>
                <?php if ( ! empty( $group['garde'] ) ) : ?>
                  <small class="acdc-purge-keep">Conservé : <?php echo esc_html( $group['garde'] ); ?></small>
                <?php endif; ?>
                <?php $this->acdc_rendre_sous_ensembles( $key, $group, $counts ); ?>
              </span>
            </label>
          <?php endforeach; ?>
        </div>

        <?php
        /* ACDC 3.25.322 — LA ZONE PROTÉGÉE N'EST PLUS RÉSERVÉE AUX FINANCEURS.
           Elle était écrite pour un seul ensemble. Le catalogue des formations
           est de la même nature : un travail de fond réutilisé à chaque dossier,
           qu'un essai ne doit pas emporter. On boucle donc sur tous les
           ensembles marqués sensibles. */
        foreach ( $groups as $__cle_s => $__grp_s ) :
          if ( empty( $__grp_s['sensitive'] ) ) { continue; }
          $__mot = $this->acdc_purge_mot_confirmation( $__cle_s );
        ?>
        <div class="acdc-purge-sensitive">
          <h4><?php echo esc_html( $__grp_s['label'] ); ?> — à ne pas effacer par mégarde</h4>
          <p><?php echo esc_html( $__grp_s['pourquoi_protege'] ?? 'Cet ensemble n’est pas une donnée de dossier : il est construit une fois et réutilisé. Il est exclu de « Tout cocher », et sa suppression demande sa propre confirmation.' ); ?></p>
          <label class="acdc-purge-item">
            <input type="checkbox" name="acdc_purge_groups[]" value="<?php echo esc_attr( $__cle_s ); ?>">
            <span>
              <strong><?php echo esc_html( $__grp_s['label'] ); ?></strong>
              <?php if ( isset( $counts[ $__cle_s ] ) ) : ?>
                <em class="acdc-purge-count"><?php echo esc_html( $counts[ $__cle_s ] ); ?> ligne<?php echo (int) $counts[ $__cle_s ] > 1 ? 's' : ''; ?></em>
              <?php endif; ?>
              <small class="acdc-purge-menu"><?php echo esc_html( $__grp_s['menu'] ); ?></small>
              <?php $this->acdc_rendre_sous_ensembles( $__cle_s, $__grp_s, $counts ); ?>
            </span>
          </label>
          <p style="margin-top:10px;">
            <label>
              Pour le supprimer, saisissez <strong><?php echo esc_html( $__mot ); ?></strong> :
              <input type="text" name="acdc_purge_confirm_<?php echo esc_attr( $__cle_s ); ?>" value="" placeholder="<?php echo esc_attr( $__mot ); ?>" autocomplete="off">
            </label>
          </p>
        </div>
        <?php endforeach; ?>

        <div class="acdc-purge-confirm">
          <p>
            <label for="acdc-selective-purge-confirm"><strong>Saisissez <?php echo esc_html( $phrase ); ?></strong></label><br>
            <input id="acdc-selective-purge-confirm" type="text" name="acdc_purge_confirm" value="" placeholder="<?php echo esc_attr( $phrase ); ?>" autocomplete="off" required>
          </p>
          <p>
            <label><input type="checkbox" name="acdc_purge_acknowledge" value="yes" required>
              Je comprends que les ensembles cochés seront supprimés définitivement.</label>
          </p>
          <?php if ( $blocked ) : ?>
            <p style="color:#8f1d1d;font-weight:600;">Purge bloquée : environnement de production détecté.</p>
            <p class="acdc-help" style="margin-top:-6px;">
              WordPress répond « production » tant qu’aucun environnement n’est déclaré : ce n’est pas un diagnostic, c’est
              son défaut. Pour débloquer, cochez « Autoriser la purge en production » dans
              <a href="<?php echo esc_url( admin_url( 'admin.php?page=acdc-of-configuration' ) ); ?>">ACDC → Configuration</a>,
              ou déclarez la vérité une fois pour toutes dans <code>wp-config.php</code> :
              <code>define( 'WP_ENVIRONMENT_TYPE', 'staging' );</code>
            </p>
          <?php endif; ?>
          <button type="submit" class="acdc-button" style="background:#fff;border:1px solid #E0B96D;color:#8a6300;" <?php disabled( $blocked ); ?>>
            Supprimer les ensembles cochés
          </button>
        </div>
      </form>
    </div>
    <style>
      .acdc-selective-purge .acdc-purge-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:6px}
      .acdc-selective-purge .acdc-purge-item{display:flex;gap:10px;align-items:flex-start;padding:12px 14px;background:#fff;border:1px solid #e6ddc4;border-radius:10px}
      .acdc-selective-purge .acdc-purge-item span{display:block}
      .acdc-selective-purge .acdc-purge-item small{display:block;margin-top:3px;color:#6b7280;font-size:12px;line-height:1.45}
      .acdc-selective-purge .acdc-purge-menu{color:#8a6300!important;font-weight:600}
      .acdc-selective-purge .acdc-purge-keep{color:#1a7d3b!important}
      .acdc-selective-purge .acdc-purge-count{display:inline-block;margin-left:8px;padding:1px 8px;border-radius:999px;background:#f2f4f8;color:#1E4777;font-style:normal;font-size:11px;font-weight:700}
      .acdc-selective-purge .acdc-purge-sous{display:block;margin-top:8px;padding-top:8px;border-top:1px dashed #e6ddc4}
      .acdc-selective-purge .acdc-purge-sous-item{display:flex;gap:7px;align-items:center;margin-top:4px;font-size:12px;color:#374151}
      .acdc-selective-purge .acdc-purge-sous-item input{margin:0}
      .acdc-selective-purge .acdc-purge-sensitive{margin-top:18px;padding:16px 18px;background:#fff7f7;border:1px solid #E06D6D;border-radius:10px}
      .acdc-selective-purge .acdc-purge-sensitive h4{margin:0 0 8px;color:#8f1d1d}
      .acdc-selective-purge .acdc-purge-confirm{margin-top:18px;padding-top:14px;border-top:1px solid #e6ddc4}
      @media(max-width:900px){.acdc-selective-purge .acdc-purge-grid{grid-template-columns:1fr}}
    </style>
    <script>
    (function(){
      var all = document.querySelector('[data-acdc-purge-all]');
      var none = document.querySelector('[data-acdc-purge-none]');

      /* Les sous-ensembles d'un bloc suivent sa case, dans les deux sens : on
         coche le bloc, tout part ; on le décoche, plus rien. Mais décocher UNE
         ligne à la main ne décoche pas le bloc — c'est exactement l'usage
         recherché : « tout ce bloc, sauf ça ». */
      function sousDe(cle){ return document.querySelectorAll('[data-acdc-purge-sub="' + cle + '"]'); }
      function suivre(box){
        var cle = box.value;
        sousDe(cle).forEach(function(s){ s.checked = box.checked; });
      }
      document.querySelectorAll('input[name="acdc_purge_groups[]"]').forEach(function(box){
        box.addEventListener('change', function(){ suivre(box); });
      });

      function setAll(v){
        document.querySelectorAll('[data-acdc-purge-box]').forEach(function(b){ b.checked = v; suivre(b); });
      }
      if (all)  { all.addEventListener('click', function(){ setAll(true); }); }
      if (none) { none.addEventListener('click', function(){
        document.querySelectorAll('input[name="acdc_purge_groups[]"]').forEach(function(b){ b.checked = false; suivre(b); });
      }); }
    })();
    </script>
    <?php
  }

  /**
   * Le nombre de lignes de chaque ensemble.
   *
   * Il change tout à l'usage : cocher « Séances » en sachant qu'il y en a 14 et
   * cocher « Séances » à l'aveugle ne sont pas le même geste. Un ensemble déjà
   * vide se voit aussi, et l'on n'a pas à se demander si la purge a fonctionné.
   */
  private function acdc_purge_group_counts( $groups ) {
    global $wpdb;

    $counts = array();
    foreach ( $groups as $key => $group ) {
      if ( empty( $group['tables'] ) ) {
        continue;
      }
      $total = 0;
      foreach ( $group['tables'] as $table ) {
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists !== $table ) {
          continue;
        }
        $total += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
      }
      $counts[ $key ] = $total;
    }
    return $counts;
  }

  /* ═══════════════════════════════════════════════════════════════════
     L'ACTION
     ═══════════════════════════════════════════════════════════════════ */

  public function handle_purge_selected_data() {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    check_admin_referer( 'acdc_purge_selected_data' );

    if ( $this->is_production_purge_blocked() ) {
      $this->redirect_to_portal(
        'settings',
        'Purge bloquée : environnement de production détecté. WordPress répond « production » tant qu’aucun environnement n’est déclaré. Cochez « Autoriser la purge en production » dans ACDC → Configuration, ou déclarez WP_ENVIRONMENT_TYPE dans wp-config.php.',
        'error'
      );
    }

    $ack = isset( $_POST['acdc_purge_acknowledge'] ) ? sanitize_text_field( wp_unslash( $_POST['acdc_purge_acknowledge'] ) ) : '';
    if ( 'yes' !== $ack ) {
      $this->redirect_to_portal( 'settings', 'Confirmation de sécurité incomplète.', 'error' );
    }

    $confirm  = isset( $_POST['acdc_purge_confirm'] ) ? strtoupper( trim( sanitize_text_field( wp_unslash( $_POST['acdc_purge_confirm'] ) ) ) ) : '';
    $expected = strtoupper( $this->get_plugin_data_purge_confirmation_phrase() );
    if ( $confirm !== $expected ) {
      $this->redirect_to_portal( 'settings', 'Confirmation incorrecte. Saisissez exactement « ' . $this->get_plugin_data_purge_confirmation_phrase() . ' ».', 'error' );
    }

    $groups    = $this->acdc_purge_groups();
    $requested = isset( $_POST['acdc_purge_groups'] ) && is_array( $_POST['acdc_purge_groups'] )
      ? array_map( 'sanitize_key', wp_unslash( $_POST['acdc_purge_groups'] ) )
      : array();
    $requested = array_values( array_intersect( $requested, array_keys( $groups ) ) );

    if ( empty( $requested ) ) {
      $this->redirect_to_portal( 'settings', 'Aucun ensemble coché : rien n’a été supprimé.', 'info' );
    }

    /* Les financeurs ne partent qu'avec leur propre mot, saisi exprès. Sans lui,
       on ne bloque PAS toute l'opération : on retire simplement les financeurs
       de la liste et on le dit. Faire échouer l'ensemble pour un mot oublié
       pousserait à tout recommencer, et c'est en recommençant que l'on coche
       trop vite. */
    $funders_refused = false;
    $refuses         = array();
    foreach ( $groups as $__cle_p => $__grp_p ) {
      if ( empty( $__grp_p['sensitive'] ) || ! in_array( $__cle_p, $requested, true ) ) {
        continue;
      }
      /* ACDC 3.25.322 — Chaque ensemble protégé a SON mot. L'ancien champ des
         financeurs est encore lu, pour ne pas casser un signet ou un formulaire
         en cache. */
      $champ = 'acdc_purge_confirm_' . $__cle_p;
      $saisi = isset( $_POST[ $champ ] ) ? $_POST[ $champ ] : ( 'funders' === $__cle_p && isset( $_POST['acdc_purge_funders_confirm'] ) ? $_POST['acdc_purge_funders_confirm'] : '' );
      $saisi = strtoupper( trim( sanitize_text_field( wp_unslash( (string) $saisi ) ) ) );
      if ( $saisi !== $this->acdc_purge_mot_confirmation( $__cle_p ) ) {
        $requested = array_values( array_diff( $requested, array( $__cle_p ) ) );
        $refuses[] = $__grp_p['label'];
        if ( 'funders' === $__cle_p ) {
          $funders_refused = true;
        }
      }
    }

    /* ACDC 3.25.322 — LES SOUS-ENSEMBLES DÉCIDENT DES TABLES.
       Un bloc coché dont on a décoché des lignes ne doit emporter que celles
       qui restent. Et un bloc dont on a TOUT décoché ne supprime rien, même
       coché : c'est la lecture la moins surprenante de l'écran.
       Le filtrage se fait ICI et non dans le navigateur : une case décochée à
       l'écran ne prouve rien, seul ce que le serveur reçoit fait foi. */
    $sous_recus = isset( $_POST['acdc_purge_sous'] ) && is_array( $_POST['acdc_purge_sous'] )
      ? wp_unslash( $_POST['acdc_purge_sous'] )
      : array();
    foreach ( $requested as $__i => $__cle_g ) {
      if ( empty( $groups[ $__cle_g ]['sous_ensembles'] ) ) {
        continue;
      }
      $choisis = isset( $sous_recus[ $__cle_g ] ) ? array_map( 'sanitize_key', (array) $sous_recus[ $__cle_g ] ) : array();
      $choisis = array_values( array_intersect( $choisis, array_keys( $groups[ $__cle_g ]['sous_ensembles'] ) ) );
      if ( empty( $choisis ) ) {
        unset( $requested[ $__i ] );
        continue;
      }
      $tables_retenues = array();
      foreach ( $choisis as $__sc ) {
        $tables_retenues = array_merge( $tables_retenues, (array) $groups[ $__cle_g ]['sous_ensembles'][ $__sc ]['tables'] );
      }
      $groups[ $__cle_g ]['tables'] = array_values( array_unique( array_filter( $tables_retenues ) ) );
    }
    $requested = array_values( $requested );

    if ( empty( $requested ) ) {
      /* ACDC 3.25.322 — Dire LEQUEL des deux refus s'est produit. Le message
         parlait des financeurs quoi qu'il arrive : devant un catalogue de
         formations refusé, ou devant un bloc dont toutes les lignes avaient été
         décochées, il envoyait chercher au mauvais endroit. */
      $__pourquoi = ! empty( $refuses )
        ? 'Suppression refusée pour : ' . implode( ', ', $refuses ) . '. Mot de confirmation absent ou incorrect.'
        : 'Aucune ligne cochée à l’intérieur des ensembles retenus.';
      $this->redirect_to_portal( 'settings', $__pourquoi . ' Rien n’a été supprimé.', 'error' );
    }

    $backup = $this->create_safety_backup_snapshot( 'purge_selected_data', array(
      'user_id' => get_current_user_id(),
      'groupes' => implode( ', ', $requested ),
    ) );
    if ( empty( $backup ) ) {
      $this->redirect_to_portal( 'settings', 'Purge annulée : la sauvegarde de sécurité n’a pas pu être créée. Aucune donnée n’a été touchée.', 'error' );
    }

    global $wpdb;
    $report  = array();
    $deleted = 0;

    foreach ( $requested as $key ) {
      $group = $groups[ $key ];

      foreach ( (array) $group['tables'] as $table ) {
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists !== $table ) {
          continue;
        }
        $rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $wpdb->query( "DELETE FROM {$table}" );
        $wpdb->query( "ALTER TABLE {$table} AUTO_INCREMENT = 1" );
        $deleted += $rows;
        if ( $rows > 0 ) {
          $report[] = $table . ' : ' . $rows;
        }
      }

      foreach ( (array) ( $group['options'] ?? array() ) as $pattern ) {
        $keep = (array) ( $group['keep_options'] ?? array() );
        $rows = $wpdb->get_col( $wpdb->prepare(
          "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
          $pattern
        ) );
        foreach ( (array) $rows as $option_name ) {
          if ( in_array( $option_name, $keep, true ) ) {
            continue;
          }
          delete_option( $option_name );
          $deleted++;
        }
      }

      if ( ! empty( $group['files'] ) ) {
        $paths = $this->collect_plugin_generated_file_paths();
        $files = $this->delete_plugin_generated_files( $paths );
        $count = is_array( $files ) ? count( $files ) : (int) $files;
        if ( $count > 0 ) {
          $report[] = 'fichiers générés : ' . $count;
        }
      }
    }

    /* Le décompte est journalisé nommément : une purge qui ne dit pas ce qu'elle
       a emporté oblige à la croire sur parole. */
    $this->insert_system_log( array(
      'log_level'   => 'warning',
      'event_type'  => 'selective_purge',
      'action_key'  => 'purge_selected_data',
      'object_type' => 'system',
      'object_id'   => 0,
      'message'     => 'Purge sélective : ' . implode( ', ', $requested ) . '.',
      'context_json' => array(
        'groupes' => $requested,
        'detail'  => $report,
        'total'   => $deleted,
        'backup'  => is_string( $backup ) ? $backup : '',
      ),
    ) );

    $message = sprintf(
      '%d élément(s) supprimé(s) sur %d ensemble(s). Une sauvegarde a été prise juste avant.',
      $deleted,
      count( $requested )
    );
    if ( $funders_refused ) {
      $message .= ' Les financeurs ont été ÉPARGNÉS : le mot de confirmation était absent ou incorrect.';
    }
    if ( ! empty( $refuses ) ) {
      $message .= ' Ensembles protégés épargnés faute de confirmation : ' . implode( ', ', $refuses ) . '.';
    }

    $this->redirect_to_portal( 'settings', $message, ( $funders_refused || ! empty( $refuses ) ) ? 'error' : 'success' );
  }
}
