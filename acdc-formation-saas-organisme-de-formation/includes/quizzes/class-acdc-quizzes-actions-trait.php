<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * ACDC Formation SAAS — Module Quizzes (Actions trait)
 *
 * Couvre :
 *  - Enregistrement des hooks WordPress du module (admin-post, AJAX, cron,
 *    shortcodes).
 *  - 3.21.00 : stubs sécurisés.
 *  - 3.21.01 : implémentation des handlers CRUD (save/duplicate/archive/delete)
 *              et endpoints AJAX de l'éditeur.
 *  - 3.21.01.1 : Bascule de l'intégration de wp-admin vers l'extranet via 2
 *                hooks ajoutés dans le kernel :
 *                  - acdc_portal_navigation_groups (ajout d'une entrée sidebar)
 *                  - acdc_portal_render_unknown_tab (rendu des nouveaux tabs)
 *                Plus aucune surcharge insteadof, plus aucune dépendance aux
 *                callbacks wp-admin du kernel.
 *
 * @package ACDC_OF_SAAS
 * @since   3.21.00
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Quizzes_Actions_Trait {

    /**
     * Slugs des nouveaux tabs extranet introduits par le moteur Quizzes.
     */
    const ACDC_OF_QZ_TAB_LIVE        = 'qz_live';
    const ACDC_OF_QZ_TAB_POSITIONING = 'qz_positioning';
    const ACDC_OF_QZ_TAB_ASSESSMENT  = 'qz_assessment';
    /* ACDC 3.25.165 — Évaluation diagnostique : création et résultats. */
    const ACDC_OF_QZ_TAB_DIAGNOSTIC  = 'qz_diagnostic';
    /* ACDC 3.21.04.1-hotfix1 — Onglet transverse Résultats */
    const ACDC_OF_QZ_TAB_RESULTS     = 'qz_results';
    /* ACDC 3.21.04.1-hotfix2 — Trois onglets Résultats spécifiques par finalité */
    const ACDC_OF_QZ_TAB_RESULTS_LIVE        = 'qz_results_live';
    const ACDC_OF_QZ_TAB_RESULTS_POSITIONING = 'qz_results_positioning';
    const ACDC_OF_QZ_TAB_RESULTS_ASSESSMENT  = 'qz_results_assessment';
    const ACDC_OF_QZ_TAB_RESULTS_DIAGNOSTIC  = 'qz_results_diagnostic';

    /**
     * Enregistre l'ensemble des hooks WordPress du module Quizzes.
     *
     * @return void
     */
    public function register_quizzes_module_hooks() {

        /* Cron */
        add_action( 'acdc_of_qz_cron_dispatches',              array( $this, 'process_qz_scheduled_dispatches' ) );
        add_action( 'acdc_of_qz_cron_reminders',               array( $this, 'process_qz_scheduled_reminders' ) );
        add_action( 'acdc_of_qz_cron_expirations',             array( $this, 'process_qz_expired_tokens' ) );
        add_action( 'acdc_of_qz_cron_close_inactive_sessions', array( $this, 'process_qz_close_inactive_sessions' ) );
        add_action( 'acdc_of_qz_cron_rgpd_purge',              array( $this, 'process_qz_rgpd_purge' ) );

        /* ACDC 3.21.03.2-a — Branchement de la vue Mes quiz dans le portail formateur.
           Cet appel délègue l'enregistrement des filtres acdc_trainer_portal_navigation_tabs,
           acdc_trainer_portal_internal_views et acdc_trainer_portal_render_unknown_view au trait
           dédié ACDC_Trainer_Portal_Quizzes_Render_Trait. */
        if ( method_exists( $this, 'register_trainer_portal_quizzes_hooks' ) ) {
            $this->register_trainer_portal_quizzes_hooks();
        }
        /* ACDC 3.21.04.1 — Hooks portail formateur pour les écrans de résultats */
        if ( method_exists( $this, 'register_trainer_portal_results_hooks' ) ) {
            $this->register_trainer_portal_results_hooks();
        }

        /* Admin-post — Quiz CRUD (3.21.01 implémenté) */
        add_action( 'admin_post_acdc_of_qz_save_quiz',           array( $this, 'handle_acdc_of_qz_save_quiz' ) );
        add_action( 'admin_post_acdc_of_qz_duplicate_quiz',        array( $this, 'handle_acdc_of_qz_duplicate_quiz' ) );
        add_action( 'admin_post_acdc_of_qz_duplicate_quiz_direct', array( $this, 'handle_acdc_of_qz_duplicate_quiz_direct' ) );
        add_action( 'admin_post_acdc_of_qz_create_new_version',  array( $this, 'handle_acdc_of_qz_create_new_version' ) );
        add_action( 'admin_post_acdc_of_qz_activate_version',    array( $this, 'handle_acdc_of_qz_activate_version' ) );
        add_action( 'admin_post_acdc_of_qz_archive_quiz',        array( $this, 'handle_acdc_of_qz_archive_quiz' ) );
        add_action( 'admin_post_acdc_of_qz_delete_quiz',         array( $this, 'handle_acdc_of_qz_delete_quiz' ) );
        // ACDC 3.25.78 — Handlers wp_ajax pour les actions quiz depuis le front office (WAF bloque admin-post.php)
        add_action( 'wp_ajax_acdc_of_qz_delete_quiz',            array( $this, 'ajax_front_qz_delete_quiz' ) );
        add_action( 'wp_ajax_acdc_of_qz_archive_quiz',           array( $this, 'ajax_front_qz_archive_quiz' ) );
        add_action( 'wp_ajax_acdc_of_qz_duplicate_quiz_direct',  array( $this, 'ajax_front_qz_duplicate_quiz' ) );
        add_action( 'wp_ajax_acdc_of_qz_create_new_version',     array( $this, 'ajax_front_qz_create_new_version' ) );
        // ACDC 3.25.113 — Migration WAF complétée : les actions cycle de vie (activate/unpublish/lock)
        // sont postées par quizzes-editor.js via admin-ajax.php et exigent donc un handler wp_ajax.
        add_action( 'wp_ajax_acdc_of_qz_activate_quiz',          array( $this, 'ajax_front_qz_activate_quiz' ) );
        add_action( 'wp_ajax_acdc_of_qz_unpublish_quiz',         array( $this, 'ajax_front_qz_unpublish_quiz' ) );
        add_action( 'wp_ajax_acdc_of_qz_lock_quiz',              array( $this, 'ajax_front_qz_lock_quiz' ) );
        add_action( 'wp_ajax_acdc_of_qz_get_formation_sessions',    array( $this, 'ajax_acdc_of_qz_get_formation_sessions' ) );
        add_action( 'wp_ajax_acdc_of_qz_get_live_launch_sessions',  array( $this, 'ajax_acdc_of_qz_get_live_launch_sessions' ) );
        // hotfix48 — Recherche d'apprenants pour la modale d'envoi
        add_action( 'wp_ajax_acdc_of_qz_search_learners', array( $this, 'ajax_acdc_of_qz_search_learners' ) );
        add_action( 'admin_post_acdc_of_qz_delete_session',      array( $this, 'handle_acdc_of_qz_delete_session' ) );
        add_action( 'wp_ajax_acdc_of_qz_delete_session',          array( $this, 'ajax_acdc_of_qz_delete_session' ) );
        /* Admin-post — Lifecycle (3.21.02 implémenté) */
        add_action( 'admin_post_acdc_of_qz_activate_quiz',       array( $this, 'handle_acdc_of_qz_activate_quiz' ) );
        add_action( 'admin_post_acdc_of_qz_unpublish_quiz',      array( $this, 'handle_acdc_of_qz_unpublish_quiz' ) );
        add_action( 'admin_post_acdc_of_qz_lock_quiz',           array( $this, 'handle_acdc_of_qz_lock_quiz' ) );

        /* Admin-post — Envoi async (3.21.03.1 implémenté) */
        add_action( 'admin_post_acdc_of_qz_extend_participant',  array( $this, 'handle_acdc_of_qz_extend_participant' ) );
        add_action( 'admin_post_acdc_of_qz_cancel_participant',  array( $this, 'handle_acdc_of_qz_cancel_participant' ) );
        add_action( 'admin_post_acdc_of_qz_resend_participant',  array( $this, 'handle_acdc_of_qz_resend_participant' ) );
        // hotfix57 — Relance manuelle individuelle (bouton 🔔 par participant)
        add_action( 'admin_post_acdc_of_qz_remind_participant',  array( $this, 'handle_acdc_of_qz_remind_participant' ) );
        // hotfix58 — Relance de session (relance tous les participants non complétés d'une session)
        add_action( 'admin_post_acdc_of_qz_remind_session',      array( $this, 'handle_acdc_of_qz_remind_session' ) );

        /* Admin-post — Lancement et envoi (stubs) */
        add_action( 'admin_post_acdc_of_qz_launch_live',         array( $this, 'handle_acdc_of_qz_launch_live' ) );
        add_action( 'admin_post_acdc_of_qz_send_async',          array( $this, 'handle_acdc_of_qz_send_async' ) );
        add_action( 'admin_post_acdc_of_qz_send_reminder',       array( $this, 'handle_acdc_of_qz_send_reminder' ) );
        add_action( 'admin_post_acdc_of_qz_cancel_session',      array( $this, 'handle_acdc_of_qz_cancel_session' ) );

        /* Admin-post — Outils (stubs) */
        add_action( 'admin_post_acdc_of_qz_save_paper_copy',      array( $this, 'handle_acdc_of_qz_save_paper_copy' ) );
        add_action( 'admin_post_acdc_of_qz_reset_attempt',        array( $this, 'handle_acdc_of_qz_reset_attempt' ) );
        add_action( 'admin_post_acdc_of_qz_anonymize_participant',array( $this, 'handle_acdc_of_qz_anonymize_participant' ) );

        /* ACDC 3.21.04.1 — Résultats : correction manuelle + export CSV */
        add_action( 'admin_post_acdc_of_qz_grade_open_answer',     array( $this, 'handle_acdc_of_qz_grade_open_answer' ) );
        add_action( 'admin_post_acdc_of_qz_export_results',        array( $this, 'handle_acdc_of_qz_export_results' ) );

        /* ACDC 3.21.65 — Export JSON quiz (réimport IA) */
        add_action( 'admin_post_acdc_of_qz_export_json',          array( $this, 'handle_acdc_of_qz_export_json' ) );

        /* Admin-post — Exports (stubs) */
        add_action( 'admin_post_acdc_of_qz_export_csv',          array( $this, 'handle_acdc_of_qz_export_csv' ) );
        add_action( 'admin_post_acdc_of_qz_export_excel',        array( $this, 'handle_acdc_of_qz_export_excel' ) );
        add_action( 'admin_post_acdc_of_qz_export_pdf',          array( $this, 'handle_acdc_of_qz_export_pdf' ) );
        add_action( 'admin_post_acdc_of_qz_download_attestation',array( $this, 'handle_acdc_of_qz_download_attestation' ) );

        /* Admin-post — Soumission async publique */
        add_action( 'admin_post_acdc_of_qz_async_submit',        array( $this, 'handle_acdc_of_qz_async_submit' ) );
        add_action( 'admin_post_nopriv_acdc_of_qz_async_submit', array( $this, 'handle_acdc_of_qz_async_submit' ) );

        /* AJAX — Host (stubs) */
        add_action( 'wp_ajax_acdc_of_qz_host_lobby_state',       array( $this, 'ajax_acdc_of_qz_host_lobby_state' ) );
        add_action( 'wp_ajax_acdc_of_qz_host_start_quiz',        array( $this, 'ajax_acdc_of_qz_host_start_quiz' ) );
        add_action( 'wp_ajax_acdc_of_qz_host_next_question',     array( $this, 'ajax_acdc_of_qz_host_next_question' ) );
        add_action( 'wp_ajax_acdc_of_qz_host_show_results',      array( $this, 'ajax_acdc_of_qz_host_show_results' ) );
        add_action( 'wp_ajax_acdc_of_qz_host_kick_participant',  array( $this, 'ajax_acdc_of_qz_host_kick_participant' ) );
        add_action( 'wp_ajax_acdc_of_qz_host_end_session',       array( $this, 'ajax_acdc_of_qz_host_end_session' ) );

        /* AJAX — Player (stubs) */
        add_action( 'wp_ajax_acdc_of_qz_player_join',                  array( $this, 'ajax_acdc_of_qz_player_join' ) );
        add_action( 'wp_ajax_nopriv_acdc_of_qz_player_join',           array( $this, 'ajax_acdc_of_qz_player_join' ) );
        add_action( 'wp_ajax_acdc_of_qz_get_session_learners',         array( $this, 'ajax_acdc_of_qz_get_session_learners' ) );
        add_action( 'wp_ajax_nopriv_acdc_of_qz_get_session_learners',  array( $this, 'ajax_acdc_of_qz_get_session_learners' ) );
        add_action( 'wp_ajax_acdc_of_qz_player_poll',                  array( $this, 'ajax_acdc_of_qz_player_poll' ) );
        add_action( 'wp_ajax_nopriv_acdc_of_qz_player_poll',           array( $this, 'ajax_acdc_of_qz_player_poll' ) );
        add_action( 'wp_ajax_acdc_of_qz_player_submit_answer',         array( $this, 'ajax_acdc_of_qz_player_submit_answer' ) );
        add_action( 'wp_ajax_nopriv_acdc_of_qz_player_submit_answer', array( $this, 'ajax_acdc_of_qz_player_submit_answer' ) );
        add_action( 'wp_ajax_acdc_of_qz_player_tab_switch',            array( $this, 'ajax_acdc_of_qz_player_tab_switch' ) );
        add_action( 'wp_ajax_nopriv_acdc_of_qz_player_tab_switch',     array( $this, 'ajax_acdc_of_qz_player_tab_switch' ) );

        /* AJAX — Éditeur (3.21.01 implémenté) */
        add_action( 'wp_ajax_acdc_of_qz_editor_autosave_question', array( $this, 'ajax_acdc_of_qz_editor_autosave_question' ) );
        add_action( 'wp_ajax_acdc_of_qz_editor_delete_question',   array( $this, 'ajax_acdc_of_qz_editor_delete_question' ) );
        add_action( 'wp_ajax_acdc_of_qz_editor_reorder_questions', array( $this, 'ajax_acdc_of_qz_editor_reorder_questions' ) );
        add_action( 'wp_ajax_acdc_of_qz_editor_save_objective',    array( $this, 'ajax_acdc_of_qz_editor_save_objective' ) );
        add_action( 'wp_ajax_acdc_of_qz_editor_delete_objective',  array( $this, 'ajax_acdc_of_qz_editor_delete_objective' ) );

        /* AJAX — Import IA (3.21.30) */
        add_action( 'wp_ajax_acdc_of_qz_import_preview', array( $this, 'ajax_acdc_of_qz_import_preview' ) );
        add_action( 'wp_ajax_acdc_of_qz_import_confirm', array( $this, 'ajax_acdc_of_qz_import_confirm' ) );

        /* Shortcodes publics */
        add_shortcode( 'acdc_qz_async_public', array( $this, 'render_qz_async_public_shortcode' ) );
        add_shortcode( 'acdc_qz_live_player',  array( $this, 'render_qz_live_player_shortcode' ) );

        /* 3.21.01.1 — Intégration dans la sidebar et le rendu de l'extranet
           via les hooks ajoutés dans le kernel. */
        add_filter( 'acdc_portal_navigation_groups', array( $this, 'inject_qz_sidebar_entry' ), 20, 2 );
        add_filter( 'acdc_portal_render_unknown_tab', array( $this, 'render_qz_unknown_tab' ), 10, 4 );

        /* Assets de l'éditeur côté front-end (extranet) */
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_qz_extranet_assets' ), 30 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_qz_live_assets' ), 30 );

        /* 3.21.03.1-hotfix2 — Pages techniques (passation publique de quiz) :
         * - Exclues du menu auto et des listes wp_list_pages.
         * - Exclues de la recherche WordPress.
         * - Exclues de l'indexation moteurs de recherche (noindex, nofollow).
         * Ces pages restent publiquement accessibles via leur URL directe (avec token). */
        add_filter( 'wp_list_pages_excludes',          array( $this, 'qz_exclude_technical_pages_from_menus' ), 10, 1 );
        add_filter( 'get_pages',                       array( $this, 'qz_filter_get_pages' ), 10, 2 );
        add_action( 'pre_get_posts',                   array( $this, 'qz_exclude_technical_pages_from_search' ) );
        add_filter( 'wp_robots',                       array( $this, 'qz_robots_noindex_technical_pages' ) );

        /* 3.21.03.1-hotfix3 — Rendu pleine page (sans header/footer du thème)
         * pour la page publique de passation. Le thème ne charge pas, on sert
         * un template autonome qui inclut le shortcode. */
        add_filter( 'template_include',                array( $this, 'qz_full_page_template_for_public_page' ) );
    }

    /* ==================================================================== */
    /*  3.21.01.1 — Intégration dans la sidebar extranet                    */
    /* ==================================================================== */

    /**
     * Ajoute l'entrée parente « Quiz / Test / Évaluation » à la sidebar de
     * l'extranet, juste après le groupe « Quiz / Enquêtes / Éval. » legacy
     * (qui reste visible le temps de la phase de mise au point).
     *
     * @param array  $groups
     * @param string $current_tab
     *
     * @return array
     */
    public function inject_qz_sidebar_entry( $groups, $current_tab ) {
        // ACDC 3.21.18 — Menu qz_* intégré directement dans la sidebar kernel — injection désactivée.
        return $groups;
        /* ACDC 3.21.04.1-hotfix2 — Une entrée Résultats par finalité, juste après son écran de gestion. */
        $new_entry = array(
            'type'  => 'group',
            'label' => __( 'Quiz / Test / Évaluation', 'acdc-formation-saas' ),
            'icon'  => 'evaluations',
            'items' => array(
                array(
                    'tab'   => self::ACDC_OF_QZ_TAB_LIVE,
                    'label' => __( 'Quiz live', 'acdc-formation-saas' ),
                    'icon'  => 'quiz',
                ),
                array(
                    'tab'   => self::ACDC_OF_QZ_TAB_RESULTS_LIVE,
                    'label' => __( 'Résultats — Quiz live', 'acdc-formation-saas' ),
                    'icon'  => 'evaluation_result',
                ),
                array(
                    'tab'   => self::ACDC_OF_QZ_TAB_POSITIONING,
                    'label' => __( 'Tests de positionnement', 'acdc-formation-saas' ),
                    'icon'  => 'positioning',
                ),
                array(
                    'tab'   => self::ACDC_OF_QZ_TAB_RESULTS_POSITIONING,
                    'label' => __( 'Résultats — Positionnement', 'acdc-formation-saas' ),
                    'icon'  => 'evaluation_result',
                ),
                array(
                    'tab'   => self::ACDC_OF_QZ_TAB_DIAGNOSTIC,
                    'label' => __( 'Évaluations diagnostiques', 'acdc-formation-saas' ),
                    'icon'  => 'evaluation_acquired',
                ),
                array(
                    'tab'   => self::ACDC_OF_QZ_TAB_RESULTS_DIAGNOSTIC,
                    'label' => __( 'Résultats — Diagnostiques', 'acdc-formation-saas' ),
                    'icon'  => 'evaluation_result',
                ),
                array(
                    'tab'   => self::ACDC_OF_QZ_TAB_ASSESSMENT,
                    'label' => __( 'Évaluations des acquis', 'acdc-formation-saas' ),
                    'icon'  => 'evaluation_acquired',
                ),
                array(
                    'tab'   => self::ACDC_OF_QZ_TAB_RESULTS_ASSESSMENT,
                    'label' => __( 'Résultats — Évaluations', 'acdc-formation-saas' ),
                    'icon'  => 'evaluation_result',
                ),
            ),
        );

        // On insère notre entrée juste APRÈS le groupe legacy
        // « Quiz / Enquêtes / Éval. » pour que les deux soient lisibles côte à côte.
        $inserted = false;
        $output = array();
        foreach ( $groups as $g ) {
            $output[] = $g;
            if ( ! $inserted
                 && isset( $g['type'], $g['label'] )
                 && 'group' === $g['type']
                 && false !== mb_stripos( (string) $g['label'], 'Quiz' )
                 && false !== mb_stripos( (string) $g['label'], 'Enqu' ) ) {
                $output[] = $new_entry;
                $inserted = true;
            }
        }
        // Sécurité : si on n'a pas trouvé le groupe legacy, on ajoute en fin.
        if ( ! $inserted ) {
            $output[] = $new_entry;
        }

        return $output;
    }

    /**
     * Prend en charge le rendu des 3 nouveaux tabs extranet (qz_live,
     * qz_positioning, qz_assessment). Pour tout autre tab, retourne false
     * pour laisser le kernel afficher son tableau de bord par défaut.
     *
     * @param bool   $handled
     * @param string $tab
     * @param string $action
     * @param int    $item_id
     *
     * @return bool true si on a rendu, false sinon.
     */
    public function render_qz_unknown_tab( $handled, $tab, $action, $item_id ) {
        // Si quelqu'un d'autre a déjà rendu, on ne fait rien.
        if ( $handled ) {
            return $handled;
        }

        /* ACDC 3.21.04.1-hotfix1 — Tab transverse Résultats : pas de purpose unique. */
        if ( self::ACDC_OF_QZ_TAB_RESULTS === $tab ) {
            $this->render_qz_extranet_screen( '' ); // chaîne vide = pas de filtre purpose
            return true;
        }

        /* ACDC 3.21.04.1-hotfix2 — Trois onglets Résultats par finalité.
           Chacun route vers la vue results avec le purpose imposé. */
        if ( self::ACDC_OF_QZ_TAB_RESULTS_LIVE === $tab ) {
            $this->render_qz_extranet_screen( self::ACDC_OF_QZ_PURPOSE_LIVE );
            return true;
        }
        if ( self::ACDC_OF_QZ_TAB_RESULTS_POSITIONING === $tab ) {
            $this->render_qz_extranet_screen( self::ACDC_OF_QZ_PURPOSE_POSITIONING );
            return true;
        }
        if ( self::ACDC_OF_QZ_TAB_RESULTS_DIAGNOSTIC === $tab ) {
            $this->render_qz_extranet_screen( self::ACDC_OF_QZ_PURPOSE_DIAGNOSTIC );
            return true;
        }
        if ( self::ACDC_OF_QZ_TAB_RESULTS_ASSESSMENT === $tab ) {
            $this->render_qz_extranet_screen( self::ACDC_OF_QZ_PURPOSE_ASSESSMENT );
            return true;
        }

        /* 3.21.33 — Vue consolidée tous les résultats */
        if ( 'qz_results_all' === $tab ) {
            $this->render_qz_results_consolidated_screen();
            return true;
        }

        $purpose_for_tab = $this->qz_purpose_for_tab( $tab );
        if ( null === $purpose_for_tab ) {
            return false;
        }

        // On délègue au trait Render (méthode publique commune).
        $this->render_qz_extranet_screen( $purpose_for_tab );
        return true;
    }

    /**
     * Mappe un tab extranet du nouveau moteur sur la finalité associée.
     *
     * @param string $tab
     *
     * @return string|null
     */
    private function qz_purpose_for_tab( $tab ) {
        if ( self::ACDC_OF_QZ_TAB_LIVE === $tab ) {
            return self::ACDC_OF_QZ_PURPOSE_LIVE;
        }
        if ( self::ACDC_OF_QZ_TAB_POSITIONING === $tab ) {
            return self::ACDC_OF_QZ_PURPOSE_POSITIONING;
        }
        if ( self::ACDC_OF_QZ_TAB_DIAGNOSTIC === $tab ) {
            return self::ACDC_OF_QZ_PURPOSE_DIAGNOSTIC;
        }
        if ( self::ACDC_OF_QZ_TAB_ASSESSMENT === $tab ) {
            return self::ACDC_OF_QZ_PURPOSE_ASSESSMENT;
        }
        return null;
    }

    /**
     * Mappe une finalité sur le slug de tab extranet correspondant.
     *
     * @param string $purpose
     *
     * @return string
     */
    private function qz_tab_for_purpose( $purpose ) {
        if ( self::ACDC_OF_QZ_PURPOSE_POSITIONING === $purpose ) {
            return self::ACDC_OF_QZ_TAB_POSITIONING;
        }
        if ( self::ACDC_OF_QZ_PURPOSE_DIAGNOSTIC === $purpose ) {
            return self::ACDC_OF_QZ_TAB_DIAGNOSTIC;
        }
        if ( self::ACDC_OF_QZ_PURPOSE_ASSESSMENT === $purpose ) {
            return self::ACDC_OF_QZ_TAB_ASSESSMENT;
        }
        return self::ACDC_OF_QZ_TAB_LIVE;
    }

    /* ==================================================================== */
    /*  3.21.01.1 — Helpers internes                                        */
    /* ==================================================================== */

    /**
     * Construit l'URL extranet du tab correspondant à une finalité.
     *
     * @param string $purpose
     * @param array  $args
     *
     * @return string
     */
    private function qz_admin_url( $purpose, $args = array() ) {
        $base_args = array_merge( array( 'tab' => $this->qz_tab_for_purpose( $purpose ) ), $args );
        // On accède à portal_page_url() du kernel par fusion de traits.
        return $this->portal_page_url( $base_args );
    }

    /**
     * Redirige vers une page extranet du moteur quizzes avec un avis.
     *
     * @param string $purpose
     * @param array  $args
     * @param string $notice
     * @param string $notice_type
     *
     * @return void
     */
    private function qz_redirect( $purpose, $args = array(), $notice = '', $notice_type = 'success' ) {
        if ( '' !== $notice ) {
            $args['acdc_qz_notice']      = rawurlencode( $notice );
            $args['acdc_qz_notice_type'] = $notice_type;
        }
        // ACDC 3.21.03.2-b — Redirection contextuelle selon l'origine (admin ou portail formateur).
        wp_safe_redirect( $this->qz_redirect_url_for_origin( $purpose, $args ) );
        exit;
    }

    /**
     * Vérification capability + nonce pour les requêtes admin-post.
     *
     * @param string $nonce_action
     * @param string $nonce_field
     *
     * @return void
     */
    private function qz_check_admin_request( $nonce_action, $nonce_field = '_acdc_qz_nonce' ) {
        /* ACDC 3.21.03.2-b — Autorisation polyvalente.
           Accepte (1) un admin WordPress avec manage_options, OU (2) un formateur connecté
           via le portail formateur disposant de la permission requise pour l'action en cours.
           Le mapping action → permission est centralisé dans qz_required_trainer_permission(). */
        if ( ! $this->qz_request_is_authorized( $nonce_action ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'acdc-formation-saas' ) );
        }
        check_admin_referer( $nonce_action, $nonce_field );
    }

    /**
     * Vérification capability + nonce pour les requêtes AJAX.
     *
     * @param string $nonce_action
     * @param string $nonce_field
     *
     * @return void
     */
    private function qz_check_ajax_request( $nonce_action, $nonce_field = 'nonce' ) {
        if ( ! $this->qz_request_is_authorized( $nonce_action ) ) {
            wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'acdc-formation-saas' ) ), 403 );
        }
        check_ajax_referer( $nonce_action, $nonce_field );
    }

    /**
     * ACDC 3.21.03.2-b — Vérifie qu'un utilisateur peut exécuter une action quiz donnée.
     * Soit c'est un admin (manage_options), soit c'est un formateur connecté via le portail
     * formateur avec la bonne permission selon l'action.
     *
     * @param string $nonce_action Nom du nonce/action en cours (ex. 'acdc_of_qz_save_quiz').
     * @return bool
     */
    private function qz_request_is_authorized( $nonce_action ) {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }
        // Si l'action n'est pas dans la table, seul un admin peut l'exécuter.
        $required_perm = $this->qz_required_trainer_permission( $nonce_action );
        if ( '' === $required_perm ) {
            return false;
        }
        // L'utilisateur doit être un formateur connecté avec la permission demandée.
        if ( ! method_exists( $this, 'trainer_portal_get_current_account' ) ) {
            return false;
        }
        $account = $this->trainer_portal_get_current_account();
        if ( ! $account || empty( $account->trainer_id ) ) {
            return false;
        }
        return $this->trainer_can( (int) $account->trainer_id, $required_perm );
    }

    /**
     * ACDC 3.21.03.2-b — Mapping action → permission formateur requise.
     * Retourne '' si l'action est réservée admin (pas de fallback formateur possible).
     */
    private function qz_required_trainer_permission( $nonce_action ) {
        $map = array(
            'acdc_of_qz_save_quiz'           => 'create_quiz',
            'acdc_of_qz_create_new_version'  => 'create_quiz',
            'acdc_of_qz_duplicate_quiz'        => 'create_quiz',
            'acdc_of_qz_duplicate_quiz_direct' => 'create_quiz',
            'acdc_of_qz_archive_quiz'        => 'create_quiz',
            'acdc_of_qz_unpublish_quiz'      => 'create_quiz',
            'acdc_of_qz_activate_quiz'       => 'create_quiz',
            'acdc_of_qz_lock_quiz'           => 'create_quiz',
            'acdc_of_qz_send_async'          => 'dispatch_quiz',
            'acdc_of_qz_extend_participant'  => 'dispatch_quiz',
            'acdc_of_qz_cancel_participant'  => 'dispatch_quiz',
            'acdc_of_qz_resend_participant'  => 'dispatch_quiz',
            'acdc_of_qz_search_learners'     => 'dispatch_quiz',
            'acdc_of_qz_remind_participant'  => 'dispatch_quiz',
            'acdc_of_qz_remind_session'      => 'dispatch_quiz',
            /* ACDC 3.21.04.1 — Actions résultats */
            'acdc_of_qz_grade_open_answer'   => 'dispatch_quiz',
            'acdc_of_qz_export_results'      => 'dispatch_quiz',
            // delete_quiz volontairement absent : seul un admin doit pouvoir supprimer définitivement.
        );
        return isset( $map[ $nonce_action ] ) ? $map[ $nonce_action ] : '';
    }

    /**
     * ACDC 3.21.03.2-b — Détecte l'origine d'une requête quiz (admin ou portail formateur).
     * Le portail formateur poste un champ caché _acdc_qz_origin=trainer_portal pour signaler.
     */
    private function qz_request_origin_is_trainer_portal() {
        return isset( $_REQUEST['_acdc_qz_origin'] ) && 'trainer_portal' === $_REQUEST['_acdc_qz_origin'];
    }

    /**
     * ACDC 3.21.03.2-b — URL de retour après une action, contextuelle à l'origine.
     * Si la requête vient du portail formateur, on retourne vers la vue Mes quiz.
     * Sinon, fallback sur l'URL admin classique du module.
     *
     * @param string $purpose Finalité du quiz (live/positioning/assessment).
     * @param array  $args    Args additionnels (ex. notice, tab, item).
     * @return string
     */
    private function qz_redirect_url_for_origin( $purpose, $args = array() ) {
        if ( $this->qz_request_origin_is_trainer_portal() && method_exists( $this, 'trainer_portal_page_url' ) ) {
            $portal_args = array();
            // Mappe la finalité vers le filtre de la liste Mes quiz
            $portal_purpose_map = array(
                self::ACDC_OF_QZ_PURPOSE_LIVE        => 'live',
                self::ACDC_OF_QZ_PURPOSE_POSITIONING => 'positioning',
                self::ACDC_OF_QZ_PURPOSE_DIAGNOSTIC  => 'diagnostic',
                self::ACDC_OF_QZ_PURPOSE_ASSESSMENT  => 'assessment',
            );
            if ( isset( $portal_purpose_map[ $purpose ] ) ) {
                $portal_args['purpose'] = $portal_purpose_map[ $purpose ];
            }
            // Retransmet les notices et l'item s'ils sont posés
            foreach ( array( 'acdc_qz_notice', 'item' ) as $k ) {
                if ( isset( $args[ $k ] ) ) {
                    $portal_args[ $k ] = $args[ $k ];
                }
            }
            return add_query_arg( $portal_args, $this->trainer_portal_page_url( 'quizzes' ) );
        }
        return $this->qz_admin_url( $purpose, $args );
    }

    /* ==================================================================== */
    /*  Quiz CRUD (implémenté en 3.21.01)                                   */
    /* ==================================================================== */

    /**
     * Crée ou met à jour un quiz.
     *
     * @return void
     */
    public function handle_acdc_of_qz_save_quiz() {
        $this->qz_check_admin_request( 'acdc_of_qz_save_quiz' );

        $quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
        $is_new  = ( 0 === $quiz_id );

        $title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $description = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';

        if ( '' === trim( $title ) ) {
            $this->qz_redirect(
                self::ACDC_OF_QZ_PURPOSE_LIVE,
                array(),
                __( 'Le titre est obligatoire.', 'acdc-formation-saas' ),
                'error'
            );
        }

        if ( $is_new ) {
            $purpose = isset( $_POST['quiz_purpose'] ) ? sanitize_key( wp_unslash( $_POST['quiz_purpose'] ) ) : '';
            if ( ! $this->is_valid_quiz_purpose( $purpose ) ) {
                wp_die( esc_html__( 'Finalité de quiz invalide.', 'acdc-formation-saas' ) );
            }
            $formation_id = isset( $_POST['formation_id'] ) ? absint( wp_unslash( $_POST['formation_id'] ) ) : 0;
            if ( $formation_id <= 0 ) {
                $this->qz_redirect(
                    $purpose,
                    array(),
                    __( 'La formation rattachée est obligatoire.', 'acdc-formation-saas' ),
                    'error'
                );
            }

            /* ACDC 3.25.276 — LE FORMATEUR NE CRÉE QUE DES QUIZ LIVE.
               Décision de David. Les trois autres finalités — positionnement,
               diagnostique, acquis — sont des pièces du dossier de formation :
               une par action, préparées automatiquement depuis la 3.25.271, et
               décidées par l'organisme. Le quiz live, lui, est un outil
               d'animation : brise-glace, contrôle de compréhension en cours de
               journée, autant que le formateur en veut.
               La règle est posée ICI, au serveur, et pas seulement dans le menu
               déroulant. Retirer un choix d'un formulaire n'empêche rien : la
               requête peut être rejouée telle quelle avec une autre valeur. Un
               écran qui restreint sans que le serveur restreigne est un décor. */
            if ( $this->qz_request_origin_is_trainer_portal()
                && self::ACDC_OF_QZ_PURPOSE_LIVE !== $purpose ) {
                $this->qz_redirect(
                    self::ACDC_OF_QZ_PURPOSE_LIVE,
                    array(),
                    __( 'Depuis votre espace, vous pouvez créer des quiz live. Les tests de positionnement et les évaluations sont préparés par l’organisme pour chaque action de formation.', 'acdc-formation-saas' ),
                    'error'
                );
            }

            /* Et pas sur n'importe quelle formation : seulement celles qu'il
               anime. Sans ce contrôle, un identifiant modifié suffirait à
               accrocher un quiz à la formation d'un collègue. */
            if ( $this->qz_request_origin_is_trainer_portal() ) {
                $compte = method_exists( $this, 'trainer_portal_get_current_account' )
                    ? $this->trainer_portal_get_current_account()
                    : null;
                $siennes = ( $compte && ! empty( $compte->trainer_id ) && method_exists( $this, 'get_qz_formations_for_trainer' ) )
                    ? (array) $this->get_qz_formations_for_trainer( (int) $compte->trainer_id )
                    : array();
                $ids = array();
                foreach ( $siennes as $f_sienne ) {
                    if ( ! empty( $f_sienne->id ) ) { $ids[] = (int) $f_sienne->id; }
                }
                if ( ! in_array( $formation_id, $ids, true ) ) {
                    $this->qz_redirect(
                        self::ACDC_OF_QZ_PURPOSE_LIVE,
                        array(),
                        __( 'Vous ne pouvez créer un quiz que sur une formation que vous animez.', 'acdc-formation-saas' ),
                        'error'
                    );
                }
            }
        } else {
            $existing = $this->get_qz_quiz( $quiz_id );
            if ( ! $existing ) {
                wp_die( esc_html__( 'Quiz introuvable.', 'acdc-formation-saas' ) );
            }
            if ( $this->is_qz_quiz_locked( $quiz_id ) ) {
                $this->qz_redirect(
                    (string) $existing->quiz_purpose,
                    array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => $quiz_id ),
                    __( 'Ce quiz est verrouillé : créez une nouvelle version pour le modifier.', 'acdc-formation-saas' ),
                    'warning'
                );
            }
            $purpose      = (string) $existing->quiz_purpose;
            $formation_id = (int) $existing->formation_id;
        }

        /* ACDC 3.25.165 — L'évaluation diagnostique et l'évaluation des acquis se
           passent en salle : le mode SYNCHRONE est leur défaut, au même titre que le
           quiz live. Il reste modifiable — un apprenant absent doit pouvoir rattraper
           à distance — contrairement au quiz live, dont la modalité EST la finalité.
           Seul le test de positionnement garde l'asynchrone par défaut : il se passe
           avant l'entrée en formation, donc à distance. */
        $delivery_default = in_array( $purpose, array(
            self::ACDC_OF_QZ_PURPOSE_LIVE,
            self::ACDC_OF_QZ_PURPOSE_DIAGNOSTIC,
            self::ACDC_OF_QZ_PURPOSE_ASSESSMENT,
        ), true )
            ? self::ACDC_OF_QZ_DELIVERY_LIVE_SYNC
            : self::ACDC_OF_QZ_DELIVERY_ASYNC_TOKEN;
        $delivery_mode = isset( $_POST['delivery_mode'] ) ? sanitize_key( wp_unslash( $_POST['delivery_mode'] ) ) : $delivery_default;
        if ( ! $this->is_valid_quiz_delivery_mode( $delivery_mode ) ) {
            $delivery_mode = $delivery_default;
        }
        if ( self::ACDC_OF_QZ_PURPOSE_LIVE === $purpose ) {
            $delivery_mode = self::ACDC_OF_QZ_DELIVERY_LIVE_SYNC;
        }

        $antifraud_time_limit       = ! empty( $_POST['antifraud_time_limit'] ) ? 1 : 0;
        $antifraud_no_back          = ! empty( $_POST['antifraud_no_back'] ) ? 1 : 0;
        $antifraud_visibility_check = ! empty( $_POST['antifraud_visibility_check'] ) ? 1 : 0;
        $antifraud_fullscreen       = ! empty( $_POST['antifraud_fullscreen'] ) ? 1 : 0;

        $allowed_scoring = array( 'percentage', 'absolute', 'level_detection' );
        $scoring_mode = isset( $_POST['scoring_mode'] ) ? sanitize_key( wp_unslash( $_POST['scoring_mode'] ) ) : 'percentage';
        if ( ! in_array( $scoring_mode, $allowed_scoring, true ) ) {
            $scoring_mode = 'percentage';
        }
        if ( self::ACDC_OF_QZ_PURPOSE_POSITIONING === $purpose && 'percentage' === $scoring_mode ) {
            $scoring_mode = 'level_detection';
        }

        $pass_threshold = isset( $_POST['pass_threshold'] ) && '' !== trim( (string) $_POST['pass_threshold'] )
            ? max( 0, min( 100, (float) wp_unslash( $_POST['pass_threshold'] ) ) )
            : null;

        $level_thresholds_raw  = isset( $_POST['level_thresholds_json'] ) ? wp_unslash( $_POST['level_thresholds_json'] ) : '';
        $level_thresholds_json = null;
        if ( '' !== trim( (string) $level_thresholds_raw ) ) {
            $decoded = json_decode( (string) $level_thresholds_raw, true );
            if ( is_array( $decoded ) ) {
                $level_thresholds_json = wp_json_encode( $decoded );
            }
        }

        $allowed_failure = array( 'none', 'retake_allowed', 'recommend_complement', 'attest_presence_only' );
        $failure_action = isset( $_POST['failure_action'] ) ? sanitize_key( wp_unslash( $_POST['failure_action'] ) ) : 'none';
        if ( ! in_array( $failure_action, $allowed_failure, true ) ) {
            $failure_action = 'none';
        }

        $max_attempts        = isset( $_POST['max_attempts'] ) ? max( 1, min( 255, (int) wp_unslash( $_POST['max_attempts'] ) ) ) : 1;
        $data_retention_days = isset( $_POST['data_retention_days'] ) ? max( 1, min( 3650, (int) wp_unslash( $_POST['data_retention_days'] ) ) ) : 1095;

        $language = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : 'fr';
        $language = mb_substr( $language, 0, 10 );

        $cover_image_id = isset( $_POST['cover_image_id'] ) ? absint( wp_unslash( $_POST['cover_image_id'] ) ) : 0;
        $cover_image_id = $cover_image_id > 0 ? $cover_image_id : null;

        $data = array(
            'title'                      => $title,
            'description'                => $description,
            'language'                   => $language,
            'cover_image_id'             => $cover_image_id,
            'delivery_mode'              => $delivery_mode,
            'scoring_mode'               => $scoring_mode,
            'pass_threshold'             => $pass_threshold,
            'level_thresholds_json'      => $level_thresholds_json,
            'failure_action'             => $failure_action,
            'max_attempts'               => $max_attempts,
            'antifraud_time_limit'       => $antifraud_time_limit,
            'antifraud_no_back'          => $antifraud_no_back,
            'antifraud_visibility_check' => $antifraud_visibility_check,
            'antifraud_fullscreen'       => $antifraud_fullscreen,
            'data_retention_days'        => $data_retention_days,
        );

        if ( $is_new ) {
            $data['formation_id'] = $formation_id;
            $data['quiz_purpose'] = $purpose;
            $data['status']       = self::ACDC_OF_QZ_STATUS_DRAFT;
            $data['created_by']   = get_current_user_id();
            $new_id = $this->insert_qz_quiz( $data );
            if ( ! $new_id ) {
                $this->qz_redirect(
                    $purpose,
                    array(),
                    __( "Erreur lors de la création du quiz.", 'acdc-formation-saas' ),
                    'error'
                );
            }
            $this->log_qz_event( array(
                'quiz_id'     => $new_id,
                'event_type'  => 'quiz_created',
                'event_label' => sprintf( 'Quiz créé : %s', $title ),
            ) );
            $this->qz_redirect(
                $purpose,
                array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => $new_id ),
                __( 'Quiz créé. Vous pouvez maintenant ajouter des questions.', 'acdc-formation-saas' ),
                'success'
            );
        }

        $ok = $this->update_qz_quiz( $quiz_id, $data );
        $this->log_qz_event( array(
            'quiz_id'     => $quiz_id,
            'event_type'  => 'quiz_updated',
            'event_label' => sprintf( 'Quiz mis à jour : %s', $title ),
        ) );
        $this->qz_redirect(
            $purpose,
            array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => $quiz_id ),
            $ok ? __( 'Quiz mis à jour.', 'acdc-formation-saas' ) : __( "Aucun changement à enregistrer.", 'acdc-formation-saas' ),
            $ok ? 'success' : 'info'
        );
    }

    /**
     * Duplique un quiz.
     *
     * @return void
     */
    public function handle_acdc_of_qz_duplicate_quiz() {
        $this->qz_check_admin_request( 'acdc_of_qz_duplicate_quiz' );

        $source_id = isset( $_POST['source_quiz_id'] ) ? absint( wp_unslash( $_POST['source_quiz_id'] ) ) : 0;
        $target_formation_id = isset( $_POST['target_formation_id'] ) ? absint( wp_unslash( $_POST['target_formation_id'] ) ) : 0;

        $source = $this->get_qz_quiz( $source_id );
        if ( ! $source ) {
            wp_die( esc_html__( 'Quiz source introuvable.', 'acdc-formation-saas' ) );
        }
        if ( $target_formation_id <= 0 ) {
            $target_formation_id = (int) $source->formation_id;
        }

        $new_id = $this->duplicate_qz_quiz( $source_id, $target_formation_id );
        if ( ! $new_id ) {
            $this->qz_redirect(
                (string) $source->quiz_purpose,
                array(),
                __( "Erreur lors de la duplication du quiz.", 'acdc-formation-saas' ),
                'error'
            );
        }

        $this->log_qz_event( array(
            'quiz_id'       => $new_id,
            'event_type'    => 'quiz_duplicated',
            'event_label'   => sprintf( 'Quiz dupliqué (depuis #%d)', $source_id ),
            'event_payload' => array(
                'source_quiz_id'      => $source_id,
                'target_formation_id' => $target_formation_id,
            ),
        ) );

        $this->qz_redirect(
            (string) $source->quiz_purpose,
            array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => $new_id ),
            __( 'Quiz dupliqué. Vous éditez maintenant la copie.', 'acdc-formation-saas' ),
            'success'
        );
    }

    /**
     * Duplication directe (en un clic) — copie dans la même formation (ou formation_id=0).
     * Accessible depuis le menu ⋯ de la liste des quiz.
     */
    public function handle_acdc_of_qz_duplicate_quiz_direct() {
        $this->qz_check_admin_request( 'acdc_of_qz_duplicate_quiz_direct' );

        $quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
        $source  = $this->get_qz_quiz( $quiz_id );
        if ( ! $source ) {
            wp_die( esc_html__( 'Quiz introuvable.', 'acdc-formation-saas' ) );
        }

        $new_id = $this->duplicate_qz_quiz( $quiz_id, (int) $source->formation_id );
        if ( ! $new_id ) {
            $this->qz_redirect(
                (string) $source->quiz_purpose,
                array(),
                __( 'Erreur lors de la duplication.', 'acdc-formation-saas' ),
                'error'
            );
        }

        $this->log_qz_event( array(
            'quiz_id'       => $new_id,
            'event_type'    => 'quiz_duplicated',
            'event_label'   => sprintf( 'Quiz dupliqué directement (depuis #%d)', $quiz_id ),
            'event_payload' => array( 'source_quiz_id' => $quiz_id ),
        ) );

        $this->qz_redirect(
            (string) $source->quiz_purpose,
            array(),
            __( 'Quiz dupliqué. La copie est en brouillon dans la liste.', 'acdc-formation-saas' ),
            'success'
        );
    }

    /**
     *
     * @return void
     */
    public function handle_acdc_of_qz_archive_quiz() {
        $this->qz_check_admin_request( 'acdc_of_qz_archive_quiz' );

        $quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) {
            wp_die( esc_html__( 'Quiz introuvable.', 'acdc-formation-saas' ) );
        }

        $ok = $this->archive_qz_quiz( $quiz_id );
        $this->log_qz_event( array(
            'quiz_id'     => $quiz_id,
            'event_type'  => 'quiz_archived',
            'event_label' => sprintf( 'Quiz archivé : %s', $quiz->title ),
        ) );

        $this->qz_redirect(
            (string) $quiz->quiz_purpose,
            array(),
            $ok ? __( 'Quiz archivé.', 'acdc-formation-saas' ) : __( "Erreur lors de l'archivage.", 'acdc-formation-saas' ),
            $ok ? 'success' : 'error'
        );
    }

    /**
     * Supprime un quiz définitivement (sauf s'il est verrouillé).
     *
     * @return void
     */
    public function handle_acdc_of_qz_delete_quiz() {
        $this->qz_check_admin_request( 'acdc_of_qz_delete_quiz' );

        $quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) {
            wp_die( esc_html__( 'Quiz introuvable.', 'acdc-formation-saas' ) );
        }

        if ( $this->is_qz_quiz_locked( $quiz_id ) ) {
            $this->qz_redirect(
                (string) $quiz->quiz_purpose,
                array(),
                __( 'Quiz verrouillé : suppression interdite. Utilisez « Archiver ».', 'acdc-formation-saas' ),
                'warning'
            );
        }

        $ok = $this->delete_qz_quiz( $quiz_id );
        $this->log_qz_event( array(
            'quiz_id'     => $quiz_id,
            'event_type'  => 'quiz_deleted',
            'event_label' => sprintf( 'Quiz supprimé : %s', $quiz->title ),
        ) );

        $this->qz_redirect(
            (string) $quiz->quiz_purpose,
            array(),
            $ok ? __( 'Quiz supprimé.', 'acdc-formation-saas' ) : __( "Erreur lors de la suppression.", 'acdc-formation-saas' ),
            $ok ? 'success' : 'error'
        );
    }

    /* ==================================================================== */
    /*  Endpoints AJAX (Éditeur)                                            */
    /* ==================================================================== */

    /**
     * Suppression d'une session de dispatch (quiz live, positionnement, évaluation).
     * Supprime la session, ses participants et leurs réponses.
     */
    public function handle_acdc_of_qz_delete_session() {
        $session_id = isset( $_GET['session_id'] ) ? absint( wp_unslash( $_GET['session_id'] ) ) : 0;
        $tab        = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
        $nonce      = isset( $_GET['_wpnonce'] ) ? (string) $_GET['_wpnonce'] : '';

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'acdc-formation-saas' ) );
        }
        if ( ! wp_verify_nonce( $nonce, 'acdc_of_qz_delete_session_' . $session_id ) ) {
            wp_die( esc_html__( 'Nonce invalide.', 'acdc-formation-saas' ) );
        }
        if ( $session_id <= 0 ) {
            wp_die( esc_html__( 'Session invalide.', 'acdc-formation-saas' ) );
        }

        global $wpdb;
        $tbl_s  = $this->get_qz_table( 'sessions' );
        $tbl_p  = $this->get_qz_table( 'participants' );
        $tbl_pa = $this->get_qz_table( 'player_answers' );

        // Supprimer les réponses des participants
        $wpdb->delete( $tbl_pa, array( 'session_id' => $session_id ), array( '%d' ) );
        // Supprimer les participants
        $wpdb->delete( $tbl_p, array( 'session_id' => $session_id ), array( '%d' ) );
        // Supprimer la session
        $ok = (bool) $wpdb->delete( $tbl_s, array( 'id' => $session_id ), array( '%d' ) );

        // Rediriger vers la liste
        $redirect_url = $this->portal_page_url( array( 'tab' => $tab ?: 'qz_results_live' ) );
        wp_redirect( add_query_arg(
            array( 'acdc_notice' => $ok ? 'session_deleted' : 'session_delete_error' ),
            $redirect_url
        ) );
        exit;
    }

    /**
     * AJAX — retourne les séances d'une formation pour la modale d'envoi.
     */
    /**
     * ACDC hotfix48 — Recherche d'apprenants dans acdc_of_learners.
     * Retourne au maximum 30 résultats correspondant à la saisie (nom, prénom, email).
     * Utilisé par l'autocomplete "Un apprenant spécifique" de la modale d'envoi.
     */
    public function ajax_acdc_of_qz_search_learners() {
        if ( ! check_ajax_referer( 'acdc_of_qz_search_learners', '_wpnonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Nonce invalide' ), 403 );
        }
        if ( ! $this->qz_request_is_authorized( 'acdc_of_qz_search_learners' ) ) {
            wp_send_json_error( array( 'message' => 'Accès refusé' ), 403 );
        }
        $q = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
        if ( mb_strlen( $q ) < 2 ) {
            wp_send_json_success( array() );
        }
        global $wpdb;
        $tbl  = $wpdb->prefix . 'acdc_of_learners';
        $like = '%' . $wpdb->esc_like( $q ) . '%';
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, first_name, last_name, usage_last_name, email
             FROM {$tbl}
             WHERE email != ''
               AND ( first_name LIKE %s
                  OR last_name  LIKE %s
                  OR usage_last_name LIKE %s
                  OR email      LIKE %s )
             ORDER BY first_name ASC, last_name ASC
             LIMIT 30",
            $like, $like, $like, $like
        ) );
        $out = array();
        foreach ( (array) $rows as $r ) {
            $last  = ! empty( $r->usage_last_name ) ? $r->usage_last_name : $r->last_name;
            $label = trim( $r->first_name . ' ' . $last );
            if ( '' === $label ) { $label = $r->email; }
            $out[] = array(
                'id'    => (int) $r->id,
                'label' => $label,
                'email' => $r->email,
            );
        }
        wp_send_json_success( $out );
    }

    /**
     * ACDC 3.21.06 — Retourne les séances du jour (+ J-1/J+1) pour la modale de lancement live.
     * Filtre sur la formation liée au quiz.
     */
    public function ajax_acdc_of_qz_get_live_launch_sessions() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Non connecté' ), 401 );
        }
        $quiz_id = isset( $_POST['quiz_id'] ) ? absint( $_POST['quiz_id'] ) : 0;
        if ( ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? (string) $_POST['_wpnonce'] : '', 'acdc_of_qz_live_launch_sessions_' . $quiz_id ) ) {
            wp_send_json_error( array( 'message' => 'Nonce invalide' ), 403 );
        }
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) {
            wp_send_json_error( array( 'message' => 'Quiz introuvable' ), 404 );
        }
        global $wpdb;
        $tbl = $wpdb->prefix . 'acdc_of_sessions';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) !== $tbl ) {
            wp_send_json_success( array( 'sessions' => array() ) );
        }
        // Séances du jour — toutes formations confondues (le formateur choisit la bonne)
        $today  = current_time( 'Y-m-d' );
        $params = array( $today, $today, $today );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT s.id, s.formation_id, s.start_date, s.end_date, s.location, s.status,
                    f.title AS formation_title, f.modality AS formation_modality
             FROM {$tbl} s
             LEFT JOIN {$wpdb->prefix}acdc_of_formations f ON f.id = s.formation_id
             WHERE (
                 DATE(COALESCE(s.start_at, CONCAT(s.start_date,' 00:00:00'))) = %s
                 OR (
                     s.start_date <= %s
                     AND (s.end_date IS NULL OR s.end_date >= %s)
                 )
             )
             AND COALESCE(s.is_draft, 0) = 0
             AND COALESCE(s.status,'') NOT IN ('Annulée','Terminée')
             ORDER BY s.start_date ASC, s.id ASC
             LIMIT 50",
            $params
        ) );
        $out = array();
        foreach ( (array) $rows as $s ) {
            $label = '';
            if ( ! empty( $s->formation_title ) ) {
                $label = $s->formation_title . ' — ';
            }
            if ( ! empty( $s->start_date ) ) {
                $label .= date_i18n( 'd/m/Y', strtotime( $s->start_date ) );
                if ( ! empty( $s->end_date ) && $s->end_date !== $s->start_date ) {
                    $label .= ' → ' . date_i18n( 'd/m/Y', strtotime( $s->end_date ) );
                }
            }
            if ( ! empty( $s->location ) ) {
                $label .= ' — ' . $s->location;
            }
            if ( empty( $label ) ) {
                $label = 'Séance #' . (int) $s->id;
            }
            $out[] = array( 'id' => (int) $s->id, 'label' => $label );
        }
        wp_send_json_success( array( 'sessions' => $out ) );
    }

            public function ajax_acdc_of_qz_get_formation_sessions() {
        if ( ! check_ajax_referer( 'acdc_of_qz_get_formation_sessions', '_wpnonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Nonce invalide' ), 403 );
        }

        $formation_id = isset( $_POST['formation_id'] ) ? absint( $_POST['formation_id'] ) : 0;

        // hotfix43 — Accepter quiz_id comme alternative : on résout automatiquement
        // la formation liée au quiz, sans demander à l'utilisateur de la re-sélectionner.
        if ( $formation_id <= 0 && ! empty( $_POST['quiz_id'] ) ) {
            $quiz = $this->get_qz_quiz( absint( $_POST['quiz_id'] ) );
            if ( $quiz && ! empty( $quiz->formation_id ) ) {
                $formation_id = (int) $quiz->formation_id;
            }
        }

        if ( $formation_id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Formation invalide' ), 400 );
        }
        $sessions = $this->get_qz_formation_sessions( $formation_id );
        $out = array();
        foreach ( $sessions as $s ) {
            $label = '';
            if ( ! empty( $s->start_date ) ) {
                $label = date_i18n( 'd/m/Y', strtotime( $s->start_date ) );
                if ( ! empty( $s->end_date ) && $s->end_date !== $s->start_date ) {
                    $label .= ' → ' . date_i18n( 'd/m/Y', strtotime( $s->end_date ) );
                }
            }
            if ( ! empty( $s->location ) ) {
                $label .= ' — ' . $s->location;
            }
            if ( empty( $label ) ) {
                $label = 'Séance #' . (int) $s->id;
            }
            $out[] = array( 'id' => (int) $s->id, 'label' => $label );
        }
        wp_send_json_success( $out );
    }

    /**
     * Handler AJAX — suppression de session (appelé sans rechargement de page).
     */
    public function ajax_acdc_of_qz_delete_session() {
        $session_id = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;
        $nonce      = isset( $_POST['_wpnonce'] ) ? (string) $_POST['_wpnonce'] : '';

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Accès refusé' ), 403 );
        }
        if ( ! wp_verify_nonce( $nonce, 'acdc_of_qz_delete_session_' . $session_id ) ) {
            wp_send_json_error( array( 'message' => 'Nonce invalide' ), 403 );
        }
        if ( $session_id <= 0 ) {
            wp_send_json_error( array( 'message' => 'Session invalide' ), 400 );
        }

        global $wpdb;
        $tbl_s  = $this->get_qz_table( 'sessions' );
        $tbl_p  = $this->get_qz_table( 'participants' );
        $tbl_pa = $this->get_qz_table( 'player_answers' );

        $wpdb->delete( $tbl_pa, array( 'session_id' => $session_id ), array( '%d' ) );
        $wpdb->delete( $tbl_p,  array( 'session_id' => $session_id ), array( '%d' ) );
        $ok = (bool) $wpdb->delete( $tbl_s, array( 'id' => $session_id ), array( '%d' ) );

        if ( $ok ) {
            wp_send_json_success( array( 'deleted' => $session_id ) );
        } else {
            wp_send_json_error( array( 'message' => 'Erreur lors de la suppression' ) );
        }
    }

    /**
     * Sauvegarde AJAX d'une question.
     *
     * @return void
     */
    public function ajax_acdc_of_qz_editor_autosave_question() {
        $this->qz_check_ajax_request( 'acdc_of_qz_editor' );

        $quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) {
            wp_send_json_error( array( 'message' => __( 'Quiz introuvable.', 'acdc-formation-saas' ) ) );
        }
        if ( $this->is_qz_quiz_locked( $quiz_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Quiz verrouillé : créez une nouvelle version pour modifier.', 'acdc-formation-saas' ) ) );
        }

        $question_id = isset( $_POST['question_id'] ) ? absint( wp_unslash( $_POST['question_id'] ) ) : 0;
        $type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : self::ACDC_OF_QZ_QTYPE_QCM_SINGLE;
        if ( ! $this->is_valid_quiz_question_type( $type ) ) {
            $type = self::ACDC_OF_QZ_QTYPE_QCM_SINGLE;
        }

        $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $description = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
        $time_limit = isset( $_POST['time_limit'] ) ? max( 5, min( 600, (int) wp_unslash( $_POST['time_limit'] ) ) ) : 20;
        /* ACDC 3.25.168 — Une réponse à rédiger ne se chronomètre pas. Le champ était
           servi avec le même défaut de 20 secondes que les QCM : le temps était écoulé
           avant que l'apprenant ait fini sa première phrase, et sa réponse partait vide.
           Zéro signifie « pas de limite » partout dans le moteur. */
        if ( self::ACDC_OF_QZ_QTYPE_OPEN_TEXT === $type ) {
            $time_limit = 0;
        }

        $allowed_pts = array( 'standard', 'double', 'none' );
        $points_type = isset( $_POST['points_type'] ) ? sanitize_key( wp_unslash( $_POST['points_type'] ) ) : 'standard';
        if ( ! in_array( $points_type, $allowed_pts, true ) ) {
            $points_type = 'standard';
        }
        $points_value = isset( $_POST['points_value'] ) ? max( 0, min( 65535, (int) wp_unslash( $_POST['points_value'] ) ) ) : 1000;
        $is_scored = isset( $_POST['is_scored'] ) ? ( ! empty( $_POST['is_scored'] ) ? 1 : 0 ) : 1;
        if ( in_array( $type, array( self::ACDC_OF_QZ_QTYPE_POLL, self::ACDC_OF_QZ_QTYPE_OPEN_TEXT ), true ) ) {
            $is_scored = 0;
        }

        $objective_id = isset( $_POST['objective_id'] ) ? absint( wp_unslash( $_POST['objective_id'] ) ) : 0;
        $objective_id = $objective_id > 0 ? $objective_id : null;

        $media_id = isset( $_POST['media_id'] ) ? absint( wp_unslash( $_POST['media_id'] ) ) : 0;
        $media_id = $media_id > 0 ? $media_id : null;

        $answers_in = isset( $_POST['answers'] ) && is_array( $_POST['answers'] ) ? wp_unslash( $_POST['answers'] ) : array();
        $answers = array();
        foreach ( $answers_in as $idx => $ans ) {
            if ( ! is_array( $ans ) ) {
                continue;
            }
            $atext = isset( $ans['text'] ) ? sanitize_text_field( $ans['text'] ) : '';
            if ( '' === trim( $atext ) && self::ACDC_OF_QZ_QTYPE_OPEN_TEXT !== $type ) {
                continue;
            }
            $answers[] = array(
                'id'            => isset( $ans['id'] ) ? absint( $ans['id'] ) : 0,
                'text'          => $atext,
                'is_correct'    => ! empty( $ans['is_correct'] ) ? 1 : 0,
                'sort_order'    => isset( $ans['sort_order'] ) ? max( 0, (int) $ans['sort_order'] ) : (int) $idx,
                'feedback_text' => isset( $ans['feedback_text'] ) ? wp_kses_post( $ans['feedback_text'] ) : null,
                'media_id'      => isset( $ans['media_id'] ) ? absint( $ans['media_id'] ) : null,
            );
        }

        /* ACDC 3.25.174 — Une remise en ordre demande autant de gestes qu'elle a
           d'éléments, sur un écran tactile et souvent debout. Le réglage par défaut de
           20 secondes est celui d'un QCM : la recette a mesuré qu'à 18 secondes pour
           cinq éléments, PERSONNE ne finit — la question s'est soldée par zéro
           répondant. On garantit donc un minimum de 15 secondes par élément, sans
           jamais raccourcir un réglage volontairement plus long. */
        if ( self::ACDC_OF_QZ_QTYPE_PUZZLE === $type && $time_limit > 0 ) {
            $items_count = max( 1, count( $answers ) );
            $time_limit  = max( $time_limit, min( 600, 15 * $items_count ) );
        }

        $payload = array(
            'id'           => $question_id,
            'type'         => $type,
            'title'        => $title,
            'description'  => $description,
            'media_id'     => $media_id,
            'time_limit'   => $time_limit,
            'points_type'  => $points_type,
            'points_value' => $points_value,
            'is_scored'    => $is_scored,
            'objective_id' => $objective_id,
            'answers'      => $answers,
        );

        $saved_id = $this->upsert_qz_question( $quiz_id, $payload );
        if ( ! $saved_id ) {
            wp_send_json_error( array( 'message' => __( "Erreur lors de l'enregistrement de la question.", 'acdc-formation-saas' ) ) );
        }

        wp_send_json_success( array(
            'question_id' => (int) $saved_id,
            'saved_at'    => current_time( 'mysql' ),
        ) );
    }

    public function ajax_acdc_of_qz_editor_delete_question() {
        $this->qz_check_ajax_request( 'acdc_of_qz_editor' );

        $quiz_id     = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
        $question_id = isset( $_POST['question_id'] ) ? absint( wp_unslash( $_POST['question_id'] ) ) : 0;

        if ( ! $this->get_qz_quiz( $quiz_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Quiz introuvable.', 'acdc-formation-saas' ) ) );
        }
        if ( $this->is_qz_quiz_locked( $quiz_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Quiz verrouillé.', 'acdc-formation-saas' ) ) );
        }

        $ok = $this->delete_qz_question( $quiz_id, $question_id );
        if ( ! $ok ) {
            wp_send_json_error( array( 'message' => __( "Suppression impossible.", 'acdc-formation-saas' ) ) );
        }
        wp_send_json_success();
    }

    public function ajax_acdc_of_qz_editor_reorder_questions() {
        $this->qz_check_ajax_request( 'acdc_of_qz_editor' );

        $quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
        if ( ! $this->get_qz_quiz( $quiz_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Quiz introuvable.', 'acdc-formation-saas' ) ) );
        }
        if ( $this->is_qz_quiz_locked( $quiz_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Quiz verrouillé.', 'acdc-formation-saas' ) ) );
        }

        $ordered = isset( $_POST['ordered_ids'] ) && is_array( $_POST['ordered_ids'] )
            ? array_map( 'absint', wp_unslash( $_POST['ordered_ids'] ) )
            : array();

        $ok = $this->reorder_qz_questions( $quiz_id, $ordered );
        if ( ! $ok ) {
            wp_send_json_error( array( 'message' => __( "Réordonnancement impossible.", 'acdc-formation-saas' ) ) );
        }
        wp_send_json_success();
    }

    public function ajax_acdc_of_qz_editor_save_objective() {
        $this->qz_check_ajax_request( 'acdc_of_qz_editor' );

        $quiz_id      = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
        $objective_id = isset( $_POST['objective_id'] ) ? absint( wp_unslash( $_POST['objective_id'] ) ) : 0;
        $label        = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
        $description  = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
        $pass         = isset( $_POST['pass_threshold'] ) && '' !== trim( (string) $_POST['pass_threshold'] )
            ? max( 0, min( 100, (float) wp_unslash( $_POST['pass_threshold'] ) ) )
            : null;

        if ( ! $this->get_qz_quiz( $quiz_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Quiz introuvable.', 'acdc-formation-saas' ) ) );
        }
        if ( '' === trim( $label ) ) {
            wp_send_json_error( array( 'message' => __( "L'intitulé de l'objectif est obligatoire.", 'acdc-formation-saas' ) ) );
        }

        $saved_id = $this->upsert_qz_objective( $quiz_id, array(
            'id'             => $objective_id,
            'label'          => $label,
            'description'    => $description,
            'pass_threshold' => $pass,
        ) );
        if ( ! $saved_id ) {
            wp_send_json_error( array( 'message' => __( "Erreur de sauvegarde de l'objectif.", 'acdc-formation-saas' ) ) );
        }
        wp_send_json_success( array( 'objective_id' => (int) $saved_id ) );
    }

    public function ajax_acdc_of_qz_editor_delete_objective() {
        $this->qz_check_ajax_request( 'acdc_of_qz_editor' );

        $quiz_id      = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
        $objective_id = isset( $_POST['objective_id'] ) ? absint( wp_unslash( $_POST['objective_id'] ) ) : 0;

        if ( ! $this->get_qz_quiz( $quiz_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Quiz introuvable.', 'acdc-formation-saas' ) ) );
        }

        $ok = $this->delete_qz_objective( $quiz_id, $objective_id );
        if ( ! $ok ) {
            wp_send_json_error( array( 'message' => __( "Suppression impossible.", 'acdc-formation-saas' ) ) );
        }
        wp_send_json_success();
    }

    /* ==================================================================== */
    /*  Import IA — 3.21.30                                                 */
    /* ==================================================================== */

    /**
     * AJAX — Parse le JSON uploadé et retourne l'aperçu de validation.
     * Ne touche pas à la base de données.
     */
    public function ajax_acdc_of_qz_import_preview() {
        if ( ! check_ajax_referer( 'acdc_of_qz_import', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce invalide.', 'acdc-formation-saas' ) ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'acdc-formation-saas' ) ) );
        }

        $quiz_id      = isset( $_POST['quiz_id'] )      ? absint( wp_unslash( $_POST['quiz_id'] ) )      : 0;
        $timer_global = isset( $_POST['timer_global'] ) ? absint( wp_unslash( $_POST['timer_global'] ) ) : 20;

        if ( ! isset( $_FILES['json_file'] ) || UPLOAD_ERR_OK !== $_FILES['json_file']['error'] ) {
            wp_send_json_error( array( 'message' => __( 'Fichier non reçu ou erreur d\'upload.', 'acdc-formation-saas' ) ) );
        }

        $file = $_FILES['json_file'];

        // Limite 512 Ko
        if ( $file['size'] > 524288 ) {
            wp_send_json_error( array( 'message' => __( 'Fichier trop volumineux (max 512 Ko).', 'acdc-formation-saas' ) ) );
        }

        // Lire le contenu
        $raw = file_get_contents( $file['tmp_name'] );
        if ( false === $raw ) {
            wp_send_json_error( array( 'message' => __( 'Impossible de lire le fichier.', 'acdc-formation-saas' ) ) );
        }

        $data = json_decode( $raw, true, 10 );
        if ( null === $data || ! is_array( $data ) ) {
            wp_send_json_error( array( 'message' => __( 'Le fichier n\'est pas un JSON valide.', 'acdc-formation-saas' ) ) );
        }

        $preview = $this->import_qz_from_json( $data, $quiz_id, 0, '', $timer_global, true );

        wp_send_json_success( array(
            'questions'  => $preview['questions'],
            'warnings'   => $preview['warnings'],
            'skipped'    => $preview['skipped'],
            'timer'      => (int) $timer_global,
            'json_b64'   => base64_encode( $raw ),
        ) );
    }

    /**
     * AJAX — Insère les questions en base après confirmation de l'aperçu.
     */
    public function ajax_acdc_of_qz_import_confirm() {
        if ( ! check_ajax_referer( 'acdc_of_qz_import', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce invalide.', 'acdc-formation-saas' ) ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'acdc-formation-saas' ) ) );
        }

        $quiz_id      = isset( $_POST['quiz_id'] )      ? absint( wp_unslash( $_POST['quiz_id'] ) )                         : 0;
        $formation_id = isset( $_POST['formation_id'] ) ? absint( wp_unslash( $_POST['formation_id'] ) )                    : 0;
        $purpose      = isset( $_POST['purpose'] )      ? sanitize_key( wp_unslash( $_POST['purpose'] ) )                   : '';
        $timer_global = isset( $_POST['timer_global'] ) ? absint( wp_unslash( $_POST['timer_global'] ) )                    : 20;
        $json_b64     = isset( $_POST['json_b64'] )     ? sanitize_text_field( wp_unslash( $_POST['json_b64'] ) )           : '';

        if ( '' === $json_b64 ) {
            wp_send_json_error( array( 'message' => __( 'Données manquantes.', 'acdc-formation-saas' ) ) );
        }

        $raw = base64_decode( $json_b64 );
        if ( false === $raw ) {
            wp_send_json_error( array( 'message' => __( 'Données corrompues.', 'acdc-formation-saas' ) ) );
        }

        $data = json_decode( $raw, true, 10 );
        if ( null === $data || ! is_array( $data ) ) {
            wp_send_json_error( array( 'message' => __( 'JSON invalide.', 'acdc-formation-saas' ) ) );
        }

        // Mode B : quiz_id = 0, formation_id et purpose obligatoires
        $result = $this->import_qz_from_json( $data, $quiz_id, $formation_id, $purpose, $timer_global, false );

        if ( ! $result['success'] ) {
            $msg = ! empty( $result['warnings'] ) ? implode( ' ', $result['warnings'] ) : __( 'Erreur d\'import.', 'acdc-formation-saas' );
            wp_send_json_error( array( 'message' => $msg ) );
        }

        wp_send_json_success( array(
            'imported'   => (int) $result['imported'],
            'skipped'    => (int) $result['skipped'],
            'objectives' => (int) $result['objectives'],
            'warnings'   => $result['warnings'],
            'quiz_id'    => (int) $result['quiz_id'],
        ) );
    }

    /* ==================================================================== */
    /*  Stubs (à venir)                                                     */
    /* ==================================================================== */

    private function qz_stub_admin_post( $action_label ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'acdc-formation-saas' ) );
        }
        $this->log_qz_event( array(
            'event_type'  => 'stub_called',
            'event_label' => $action_label,
        ) );

        $referer = wp_get_referer();
        if ( ! $referer ) {
            $referer = home_url( '/' );
        }
        wp_safe_redirect( add_query_arg( 'acdc_qz_notice', 'feature_pending', $referer ) );
        exit;
    }

    /* ACDC 3.25.113 — Wrappers AJAX du cycle de vie quiz (postés via admin-ajax.php par
       quizzes-editor.js). Réutilisent la logique métier des méthodes core et répondent en JSON. */
    public function ajax_front_qz_activate_quiz() {
        $this->qz_check_ajax_request( 'acdc_of_qz_activate_quiz', '_acdc_qz_nonce' );
        $quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) { wp_send_json_error( array( 'message' => __( 'Quiz introuvable.', 'acdc-formation-saas' ) ) ); }
        $result = $this->activate_qz_quiz( $quiz_id );
        if ( empty( $result['success'] ) ) {
            $message = __( 'Activation impossible : ', 'acdc-formation-saas' ) . implode( ' • ', (array) ( $result['errors'] ?? array() ) );
            wp_send_json_error( array( 'message' => $message ) );
        }
        $redirect = $this->qz_admin_url( (string) $quiz->quiz_purpose, array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => $quiz_id ) );
        wp_send_json_success( array( 'redirect' => $redirect, 'message' => __( 'Quiz activé.', 'acdc-formation-saas' ) ) );
    }

    public function ajax_front_qz_unpublish_quiz() {
        $this->qz_check_ajax_request( 'acdc_of_qz_unpublish_quiz', '_acdc_qz_nonce' );
        $quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) { wp_send_json_error( array( 'message' => __( 'Quiz introuvable.', 'acdc-formation-saas' ) ) ); }
        if ( (int) $quiz->is_locked === 1 ) {
            wp_send_json_error( array( 'message' => __( 'Ce quiz est verrouillé : la dépublication est impossible. Pour modifier, créez une nouvelle version.', 'acdc-formation-saas' ) ) );
        }
        $ok = $this->unpublish_qz_quiz( $quiz_id );
        if ( ! $ok ) {
            wp_send_json_error( array( 'message' => __( 'Dépublication impossible.', 'acdc-formation-saas' ) ) );
        }
        $redirect = $this->qz_admin_url( (string) $quiz->quiz_purpose, array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => $quiz_id ) );
        wp_send_json_success( array( 'redirect' => $redirect, 'message' => __( 'Quiz repassé en brouillon.', 'acdc-formation-saas' ) ) );
    }

    public function ajax_front_qz_lock_quiz() {
        $this->qz_check_ajax_request( 'acdc_of_qz_lock_quiz', '_acdc_qz_nonce' );
        $quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) { wp_send_json_error( array( 'message' => __( 'Quiz introuvable.', 'acdc-formation-saas' ) ) ); }
        if ( self::ACDC_OF_QZ_STATUS_ACTIVE !== $quiz->status ) {
            wp_send_json_error( array( 'message' => __( "Le verrouillage manuel n'est possible que sur un quiz actif. Activez-le d'abord.", 'acdc-formation-saas' ) ) );
        }
        $ok = $this->lock_qz_quiz_on_first_use( $quiz_id, 'manual_lock' );
        $redirect = $this->qz_admin_url( (string) $quiz->quiz_purpose, array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => $quiz_id ) );
        wp_send_json_success( array( 'redirect' => $redirect, 'message' => $ok ? __( 'Quiz verrouillé.', 'acdc-formation-saas' ) : __( 'Ce quiz était déjà verrouillé.', 'acdc-formation-saas' ) ) );
    }

    private function qz_stub_ajax() {
        wp_send_json_error(
            array(
                'message' => __( 'Fonctionnalité à venir dans une prochaine sous-version 3.21.', 'acdc-formation-saas' ),
                'code'    => 'feature_pending',
            ),
            501
        );
    }

    /**
     * 3.21.02 — Activation manuelle d'un quiz (`draft` → `active`).
     *
     * @return void
     */
    public function handle_acdc_of_qz_activate_quiz() {
        $this->qz_check_admin_request( 'acdc_of_qz_activate_quiz' );

        $quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) {
            wp_die( esc_html__( 'Quiz introuvable.', 'acdc-formation-saas' ) );
        }

        $result = $this->activate_qz_quiz( $quiz_id );

        if ( ! $result['success'] ) {
            // On concatène les erreurs pour les afficher dans la notice.
            $message = __( "Activation impossible : ", 'acdc-formation-saas' ) . implode( ' • ', $result['errors'] );
            $this->qz_redirect(
                (string) $quiz->quiz_purpose,
                array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => $quiz_id ),
                $message,
                'error'
            );
        }

        $this->log_qz_event( array(
            'quiz_id'     => $quiz_id,
            'event_type'  => 'quiz_activated',
            'event_label' => sprintf( 'Quiz activé : %s', $quiz->title ),
        ) );

        $message = __( 'Quiz activé. Il peut maintenant être lancé en session.', 'acdc-formation-saas' );
        if ( ! empty( $result['warnings'] ) ) {
            $message .= ' ' . __( '(Avertissements : ', 'acdc-formation-saas' )
                     . implode( ' • ', $result['warnings'] ) . ')';
        }

        $this->qz_redirect(
            (string) $quiz->quiz_purpose,
            array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => $quiz_id ),
            $message,
            'success'
        );
    }

    /**
     * 3.21.02 — Dépublication d'un quiz (`active` → `draft`). Refuse si verrouillé.
     *
     * @return void
     */
    public function handle_acdc_of_qz_unpublish_quiz() {
        $this->qz_check_admin_request( 'acdc_of_qz_unpublish_quiz' );

        $quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) {
            wp_die( esc_html__( 'Quiz introuvable.', 'acdc-formation-saas' ) );
        }

        if ( (int) $quiz->is_locked === 1 ) {
            $this->qz_redirect(
                (string) $quiz->quiz_purpose,
                array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => $quiz_id ),
                __( "Ce quiz est verrouillé : la dépublication est impossible. Pour modifier, créez une nouvelle version.", 'acdc-formation-saas' ),
                'warning'
            );
        }

        $ok = $this->unpublish_qz_quiz( $quiz_id );
        $this->log_qz_event( array(
            'quiz_id'     => $quiz_id,
            'event_type'  => 'quiz_unpublished',
            'event_label' => sprintf( 'Quiz dépublié : %s', $quiz->title ),
        ) );

        $this->qz_redirect(
            (string) $quiz->quiz_purpose,
            array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => $quiz_id ),
            $ok ? __( 'Quiz dépublié, repassé en brouillon.', 'acdc-formation-saas' ) : __( "Aucun changement appliqué.", 'acdc-formation-saas' ),
            $ok ? 'success' : 'info'
        );
    }

    /**
     * 3.21.02 — Verrouillage manuel d'un quiz (active → locked).
     * Utile pour figer une version pour archive sans attendre une session
     * réelle. Une fois verrouillé, plus aucune modification possible : il faut
     * créer une nouvelle version.
     *
     * @return void
     */
    public function handle_acdc_of_qz_lock_quiz() {
        $this->qz_check_admin_request( 'acdc_of_qz_lock_quiz' );

        $quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) {
            wp_die( esc_html__( 'Quiz introuvable.', 'acdc-formation-saas' ) );
        }

        if ( self::ACDC_OF_QZ_STATUS_ACTIVE !== $quiz->status ) {
            $this->qz_redirect(
                (string) $quiz->quiz_purpose,
                array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => $quiz_id ),
                __( "Le verrouillage manuel n'est possible que sur un quiz actif. Activez-le d'abord.", 'acdc-formation-saas' ),
                'warning'
            );
        }

        $ok = $this->lock_qz_quiz_on_first_use( $quiz_id, 'manual_lock' );

        $this->qz_redirect(
            (string) $quiz->quiz_purpose,
            array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => $quiz_id ),
            $ok
                ? __( 'Quiz verrouillé. Toute modification ultérieure devra passer par une nouvelle version.', 'acdc-formation-saas' )
                : __( 'Ce quiz était déjà verrouillé.', 'acdc-formation-saas' ),
            $ok ? 'success' : 'info'
        );
    }

    /**
     * 3.21.02 — Création d'une nouvelle version d'un quiz.
     * Disponible aussi bien pour quiz verrouillés (cas usage principal) que
     * pour quiz non verrouillés (versionnement volontaire).
     *
     * @return void
     */
    public function handle_acdc_of_qz_create_new_version() {
        $this->qz_check_admin_request( 'acdc_of_qz_create_new_version' );

        $source_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
        $source = $this->get_qz_quiz( $source_id );
        if ( ! $source ) {
            wp_die( esc_html__( 'Quiz introuvable.', 'acdc-formation-saas' ) );
        }

        $new_id = $this->create_qz_quiz_new_version( $source_id );
        if ( ! $new_id ) {
            $this->qz_redirect(
                (string) $source->quiz_purpose,
                array(),
                __( "Erreur lors de la création de la nouvelle version.", 'acdc-formation-saas' ),
                'error'
            );
        }

        $this->qz_redirect(
            (string) $source->quiz_purpose,
            array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => $new_id ),
            __( 'Nouvelle version créée. Vous éditez maintenant la nouvelle version (l\'ancienne reste consultable en lecture seule).', 'acdc-formation-saas' ),
            'success'
        );
    }
    public function handle_acdc_of_qz_activate_version()    { $this->qz_stub_admin_post( 'activate_version' ); }
    public function handle_acdc_of_qz_launch_live() {
        if ( ! is_user_logged_in() ) {
            wp_die( 'Non connecté', 'Erreur', array( 'response' => 401 ) );
        }
        $quiz_id = isset( $_REQUEST['quiz_id'] ) ? (int) $_REQUEST['quiz_id'] : 0;
        $nonce   = isset( $_REQUEST['_wpnonce'] ) ? (string) $_REQUEST['_wpnonce'] : '';
        if ( ! wp_verify_nonce( $nonce, 'acdc_of_qz_launch_live_' . $quiz_id ) ) {
            wp_die( 'Nonce invalide', 'Erreur', array( 'response' => 403 ) );
        }
        $formation_session_id = isset( $_REQUEST['formation_session_id'] ) ? absint( wp_unslash( $_REQUEST['formation_session_id'] ) ) : 0;
        // Résoudre la formation depuis le quiz si non transmise
        $formation_id = 0;
        $quiz_obj = $this->get_qz_quiz( $quiz_id );
        if ( $quiz_obj && ! empty( $quiz_obj->formation_id ) ) {
            $formation_id = (int) $quiz_obj->formation_id;
        }
        $session_id = $this->create_qz_live_session( $quiz_id, get_current_user_id(), $formation_id, $formation_session_id );
        if ( is_wp_error( $session_id ) ) {
            wp_die( esc_html( $session_id->get_error_message() ), 'Erreur', array( 'response' => 400 ) );
        }
        // Rediriger vers la page host
        $host_page_id = (int) get_option( 'acdc_of_qz_live_host_page_id', 0 );
        $host_url = $host_page_id ? get_permalink( $host_page_id ) : home_url( '/quiz-live-host/' );
        $url = add_query_arg( array( 'session' => $session_id ), $host_url );
        wp_safe_redirect( $url );
        exit;
    }
    /**
     * 3.21.03.1 — Envoi asynchrone d'un quiz par e-mail.
     *
     * @return void
     */
    public function handle_acdc_of_qz_send_async() {
        $this->qz_check_admin_request( 'acdc_of_qz_send_async' );

        $quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) {
            wp_die( esc_html__( 'Quiz introuvable.', 'acdc-formation-saas' ) );
        }

        // Le quiz doit être actif. Si brouillon, on tente une activation préalable.
        if ( self::ACDC_OF_QZ_STATUS_DRAFT === $quiz->status ) {
            $activation = $this->activate_qz_quiz( $quiz_id );
            if ( ! $activation['success'] ) {
                $this->qz_redirect(
                    (string) $quiz->quiz_purpose,
                    array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => $quiz_id ),
                    __( "Avant d'envoyer, le quiz doit être complet : ", 'acdc-formation-saas' ) . implode( ' • ', $activation['errors'] ),
                    'error'
                );
            }
            // Recharger l'objet quiz avec son nouveau statut
            $quiz = $this->get_qz_quiz( $quiz_id );
        }

        // Récupération des destinataires selon le mode choisi.
        // Supporte les deux interfaces : ancienne (recipients_mode) et nouvelle modale liste (send_mode)
        $send_mode       = isset( $_POST['send_mode'] ) ? sanitize_key( wp_unslash( $_POST['send_mode'] ) ) : '';
        $recipients_mode = isset( $_POST['recipients_mode'] ) ? sanitize_key( wp_unslash( $_POST['recipients_mode'] ) ) : 'formation';
        // Unifier les deux interfaces
        if ( '' !== $send_mode ) {
            // Nouvelle modale liste
            if ( 'learner' === $send_mode ) {
                // hotfix48 — Priorité au learner_id sélectionné dans l'autocomplete.
                // Fallback sur email/prénom libre si learner_id absent (rétrocompat).
                $learner_id_direct = isset( $_POST['learner_id'] ) ? absint( wp_unslash( $_POST['learner_id'] ) ) : 0;
                if ( $learner_id_direct > 0 ) {
                    $recipients_mode = 'learner_by_id';
                } else {
                    $recipients_mode = 'custom';
                    $learner_email = isset( $_POST['learner_email'] ) ? sanitize_email( wp_unslash( $_POST['learner_email'] ) ) : '';
                    $learner_fname = isset( $_POST['learner_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['learner_first_name'] ) ) : '';
                    $_POST['custom_emails'] = $learner_email . ( $learner_fname ? ',' . $learner_fname : '' );
                }
            } elseif ( 'session' === $send_mode ) {
                $recipients_mode = 'session';
                $_POST['formation_session_id'] = isset( $_POST['session_id'] ) ? absint( wp_unslash( $_POST['session_id'] ) ) : 0;
            } elseif ( 'formation' === $send_mode ) {
                $recipients_mode = 'formation_all'; // tous les apprenants, sans filtre par ID
            } elseif ( 'group' === $send_mode ) {
                // hotfix45 — Vrai groupe d’apprenants (acdc_of_groups)
                $recipients_mode = 'group';
            }
        }
        $recipients = array();

        if ( 'formation' === $recipients_mode ) {
            $selected_ids = isset( $_POST['learner_ids'] ) && is_array( $_POST['learner_ids'] )
                ? array_map( 'absint', wp_unslash( $_POST['learner_ids'] ) )
                : array();
            if ( empty( $selected_ids ) ) {
                $this->qz_redirect(
                    (string) $quiz->quiz_purpose,
                    array(),
                    __( "Aucun apprenant sélectionné.", 'acdc-formation-saas' ),
                    'warning'
                );
            }
            $formation_id = isset( $_POST['formation_id'] ) ? absint( $_POST['formation_id'] ) : (int) $quiz->formation_id;
            $all_learners = $this->get_qz_learners_for_formation( $formation_id );
            foreach ( $all_learners as $l ) {
                if ( in_array( (int) $l->id, $selected_ids, true ) ) {
                    $recipients[] = array(
                        'email'      => $l->email,
                        'first_name' => $l->first_name,
                        'last_name'  => $l->last_name,
                        'learner_id' => (int) $l->id,
                    );
                }
            }
        } elseif ( 'formation_all' === $recipients_mode ) {
            // Tous les apprenants d'une formation sans filtre
            $formation_id = isset( $_POST['formation_id'] ) ? absint( $_POST['formation_id'] ) : (int) $quiz->formation_id;
            if ( $formation_id <= 0 ) {
                $this->qz_redirect( (string) $quiz->quiz_purpose, array(), __( "Veuillez sélectionner une formation.", 'acdc-formation-saas' ), 'warning' );
            }
            $all_learners = $this->get_qz_learners_for_formation( $formation_id );
            foreach ( $all_learners as $l ) {
                $recipients[] = array(
                    'email'      => $l->email,
                    'first_name' => $l->first_name,
                    'last_name'  => $l->last_name,
                    'learner_id' => (int) $l->id,
                );
            }
        } elseif ( 'group' === $recipients_mode ) {
            // hotfix45 — Destinataires via un groupe (acdc_of_groups).
            // Les apprenants sont liés au groupe via acdc_of_training_registrations.group_id,
            // PAS via acdc_of_groups.learner_ids (champ non peuplé par l’UI).
            $group_id = isset( $_POST['group_id'] ) ? absint( wp_unslash( $_POST['group_id'] ) ) : 0;
            if ( $group_id <= 0 ) {
                $this->qz_redirect( (string) $quiz->quiz_purpose, array(), __( "Veuillez sélectionner un groupe.", 'acdc-formation-saas' ), 'warning' );
            }
            global $wpdb;
            // Vérifier que le groupe existe
            $tbl_g = $wpdb->prefix . 'acdc_of_groups';
            $group_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tbl_g} WHERE id = %d LIMIT 1", $group_id ) );
            if ( ! $group_exists ) {
                $this->qz_redirect( (string) $quiz->quiz_purpose, array(), __( "Groupe introuvable.", 'acdc-formation-saas' ), 'warning' );
            }
            // Récupérer les IDs apprenants via les inscriptions liées à ce groupe
            $tbl_r = $wpdb->prefix . 'acdc_of_training_registrations';
            $learner_id_rows = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT learner_id FROM {$tbl_r}
                 WHERE group_id = %d AND learner_id IS NOT NULL AND learner_id > 0",
                $group_id
            ) );
            $learner_ids = array_values( array_filter( array_map( 'absint', (array) $learner_id_rows ) ) );
            if ( empty( $learner_ids ) ) {
                $this->qz_redirect( (string) $quiz->quiz_purpose, array(),
                    __( "Ce groupe ne contient aucun apprenant inscrit.", 'acdc-formation-saas' ), 'warning' );
            }
            $tbl_l        = $wpdb->prefix . 'acdc_of_learners';
            $placeholders = implode( ',', array_fill( 0, count( $learner_ids ), '%d' ) );
            // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $learners = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, email, first_name, last_name, usage_last_name
                 FROM {$tbl_l}
                 WHERE id IN ({$placeholders}) AND email != '' AND email IS NOT NULL
                 ORDER BY first_name ASC",
                $learner_ids
            ) );
            foreach ( $learners as $l ) {
                $last = ! empty( $l->usage_last_name ) ? $l->usage_last_name : $l->last_name;
                $recipients[] = array(
                    'email'      => $l->email,
                    'first_name' => $l->first_name,
                    'last_name'  => $last,
                    'learner_id' => (int) $l->id,
                );
            }
        } elseif ( 'session' === $recipients_mode ) {
            $session_id = isset( $_POST['formation_session_id'] ) ? absint( wp_unslash( $_POST['formation_session_id'] ) ) : 0;
            if ( $session_id <= 0 ) {
                $this->qz_redirect( (string) $quiz->quiz_purpose, array(), __( "Veuillez sélectionner une séance.", 'acdc-formation-saas' ), 'warning' );
            }
            $all_learners = $this->get_qz_learners_for_formation( (int) $quiz->formation_id, array( 'session_id' => $session_id ) );
            if ( empty( $all_learners ) ) {
                // Essai sans formation_id (la séance peut appartenir à n'importe quelle formation)
                global $wpdb;
                $tbl_s = $wpdb->prefix . 'acdc_of_sessions';
                $fid_from_session = (int) $wpdb->get_var( $wpdb->prepare( "SELECT formation_id FROM {$tbl_s} WHERE id=%d LIMIT 1", $session_id ) );
                if ( $fid_from_session > 0 ) {
                    $all_learners = $this->get_qz_learners_for_formation( $fid_from_session, array( 'session_id' => $session_id ) );
                }
            }
            foreach ( $all_learners as $l ) {
                $recipients[] = array(
                    'email'      => $l->email,
                    'first_name' => $l->first_name,
                    'last_name'  => $l->last_name,
                    'learner_id' => (int) $l->id,
                );
            }
        } elseif ( 'learner_by_id' === $recipients_mode ) {
            // hotfix48 — Apprenant choisi via l'autocomplete (learner_id connu).
            $learner_id_direct = isset( $_POST['learner_id'] ) ? absint( wp_unslash( $_POST['learner_id'] ) ) : 0;
            global $wpdb;
            $tbl_l = $wpdb->prefix . 'acdc_of_learners';
            $l = $wpdb->get_row( $wpdb->prepare(
                "SELECT id, email, first_name, last_name, usage_last_name FROM {$tbl_l} WHERE id = %d LIMIT 1",
                $learner_id_direct
            ) );
            if ( $l && ! empty( $l->email ) ) {
                $last = ! empty( $l->usage_last_name ) ? $l->usage_last_name : $l->last_name;
                $recipients[] = array(
                    'email'      => $l->email,
                    'first_name' => $l->first_name,
                    'last_name'  => $last,
                    'learner_id' => (int) $l->id,
                );
            }
        } else {
            // Saisie libre : un texte multi-lignes au format "email" ou "email,Prénom Nom"
            $raw = isset( $_POST['custom_emails'] ) ? wp_unslash( $_POST['custom_emails'] ) : '';
            $lines = array_filter( array_map( 'trim', explode( "\n", (string) $raw ) ) );
            foreach ( $lines as $line ) {
                $parts = array_map( 'trim', explode( ',', $line, 2 ) );
                $email = sanitize_email( $parts[0] );
                if ( ! is_email( $email ) ) {
                    continue;
                }
                $name = isset( $parts[1] ) ? $parts[1] : '';
                $name_parts = explode( ' ', $name, 2 );
                $recipients[] = array(
                    'email'      => $email,
                    'first_name' => isset( $name_parts[0] ) ? sanitize_text_field( $name_parts[0] ) : '',
                    'last_name'  => isset( $name_parts[1] ) ? sanitize_text_field( $name_parts[1] ) : '',
                    'learner_id' => null,
                );
            }
        }

        if ( empty( $recipients ) ) {
            $this->qz_redirect(
                (string) $quiz->quiz_purpose,
                array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => $quiz_id ),
                __( "Aucun destinataire valide n'a été identifié.", 'acdc-formation-saas' ),
                'warning'
            );
        }

        $expires_in_days = 7;
        if ( ! empty( $_POST['deadline_date'] ) ) {
            $deadline = sanitize_text_field( wp_unslash( $_POST['deadline_date'] ) );
            $diff = ( strtotime( $deadline ) - time() ) / 86400;
            $expires_in_days = max( 1, (int) ceil( $diff ) );
        } elseif ( ! empty( $_POST['expires_in_days'] ) ) {
            $expires_in_days = max( 1, (int) $_POST['expires_in_days'] );
        }
        $args = array(
            'expires_in_days'      => $expires_in_days,
            'reminders_enabled'    => ! empty( $_POST['reminders_enabled'] ),
            'custom_message'       => isset( $_POST['custom_message'] ) ? wp_kses_post( wp_unslash( $_POST['custom_message'] ) ) : '',
            'formation_session_id' => isset( $_POST['formation_session_id'] ) ? absint( wp_unslash( $_POST['formation_session_id'] ) ) : 0,
        );

        $result = $this->create_qz_async_dispatch( $quiz_id, $recipients, $args );

        if ( is_wp_error( $result ) ) {
            $this->qz_redirect(
                (string) $quiz->quiz_purpose,
                array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => $quiz_id ),
                $result->get_error_message(),
                'error'
            );
        }

        // ACDC hotfix43 — create_qz_async_dispatch retourne désormais un array avec compteurs.
        // Avant : $sent_count / $failed_count / $send_errors étaient indéfinis dans ce scope
        // → implode(', ', null) → PHP 8 TypeError → écran \"erreur critique\" WordPress.
        $sent_count   = isset( $result['sent_count'] )   ? (int)   $result['sent_count']   : 0;
        $failed_count = isset( $result['failed_count'] ) ? (int)   $result['failed_count'] : 0;
        $send_errors  = isset( $result['send_errors'] )  ? (array) $result['send_errors']  : array();

        $this->qz_redirect(
            (string) $quiz->quiz_purpose,
            array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => $quiz_id ),
            $sent_count > 0
                ? sprintf(
                    _n( '%d invitation envoyée avec succès.',
                        '%d invitations envoyées avec succès.',
                        $sent_count, 'acdc-formation-saas' ),
                    $sent_count
                ) . ( $failed_count > 0 ? ' — ⚠ ' . $failed_count . ' échec(s) : ' . implode( ', ', $send_errors ) : '' )
                : '⚠ Aucun e-mail n\'a pu être envoyé. Vérifiez la configuration SMTP du site (plugin WP Mail SMTP recommandé) et les adresses e-mail des apprenants. Échecs : ' . implode( ', ', $send_errors ),
            $sent_count > 0 ? 'success' : 'error'
        );
    }


    /**
     * 3.21.03.1 — Prolonger le délai d'expiration d'un participant (max 7 jours).
     *
     * @return void
     */
    public function handle_acdc_of_qz_extend_participant() {
        $this->qz_check_admin_request( 'acdc_of_qz_extend_participant' );

        $participant_id = isset( $_POST['participant_id'] ) ? absint( wp_unslash( $_POST['participant_id'] ) ) : 0;
        $extra_days     = isset( $_POST['extra_days'] ) ? max( 1, min( 7, (int) $_POST['extra_days'] ) ) : 3;

        $p = $this->get_qz_participant( $participant_id );
        if ( ! $p ) {
            wp_die( esc_html__( 'Participant introuvable.', 'acdc-formation-saas' ) );
        }

        $new_expires = $this->extend_qz_async_token( $participant_id, $extra_days );

        // Identifier le quiz pour la redirection
        global $wpdb;
        $tbl_s = $this->get_qz_table( 'sessions' );
        $session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl_s} WHERE id = %d", (int) $p->session_id ) );
        $quiz = $session ? $this->get_qz_quiz( (int) $session->quiz_id ) : null;

        if ( ! $quiz ) {
            wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
            exit;
        }

        $this->qz_redirect(
            (string) $quiz->quiz_purpose,
            array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => (int) $quiz->id ),
            $new_expires
                ? sprintf(
                    /* translators: 1: extra days, 2: new expiration date */
                    __( 'Délai prolongé de %1$d jour(s). Nouvelle date limite : %2$s.', 'acdc-formation-saas' ),
                    $extra_days,
                    mysql2date( 'j F Y', $new_expires, true )
                )
                : __( 'Prolongation impossible.', 'acdc-formation-saas' ),
            $new_expires ? 'success' : 'warning'
        );
    }

    /**
     * 3.21.03.1 — Annuler un participant (le lien devient inactif).
     *
     * @return void
     */
    public function handle_acdc_of_qz_cancel_participant() {
        $this->qz_check_admin_request( 'acdc_of_qz_cancel_participant' );

        $participant_id = isset( $_POST['participant_id'] ) ? absint( wp_unslash( $_POST['participant_id'] ) ) : 0;
        $p = $this->get_qz_participant( $participant_id );
        if ( ! $p ) {
            wp_die( esc_html__( 'Participant introuvable.', 'acdc-formation-saas' ) );
        }

        $this->cancel_qz_async_participant( $participant_id );

        global $wpdb;
        $tbl_s = $this->get_qz_table( 'sessions' );
        $session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl_s} WHERE id = %d", (int) $p->session_id ) );
        $quiz = $session ? $this->get_qz_quiz( (int) $session->quiz_id ) : null;

        if ( ! $quiz ) {
            wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
            exit;
        }

        $this->qz_redirect(
            (string) $quiz->quiz_purpose,
            array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => (int) $quiz->id ),
            __( 'Invitation annulée.', 'acdc-formation-saas' ),
            'success'
        );
    }

    /**
     * 3.21.03.1 — Renvoyer un nouveau lien à un participant (réinitialisation du token).
     *
     * @return void
     */
    /**
     * ACDC hotfix57 — Relance manuelle d’un participant non complété.
     *
     * Actions :
     *   1. Envoie l’e-mail de relance (template email-async-relance.php).
     *   2. Met à jour last_reminder_sent_at sur le participant (snooze 24h).
     *   3. Journalise l’événement dans acdc_of_qz_logs (preuve Qualiopi).
     */
    /**
     * ACDC hotfix58 — Relance de session : envoie une relance à tous les participants
     * non complétés d'une session d'envoi (invited | opened | in_progress).
     */
    public function handle_acdc_of_qz_remind_session() {
        global $wpdb;
        $this->qz_check_admin_request( 'acdc_of_qz_remind_session' );

        $session_id = isset( $_POST['session_id'] ) ? absint( wp_unslash( $_POST['session_id'] ) ) : 0;
        if ( $session_id <= 0 ) { wp_die( 'Session invalide.' ); }

        $session = $this->get_qz_dispatch_session( $session_id );
        if ( ! $session ) { wp_die( 'Session introuvable.' ); }
        $quiz = $this->get_qz_quiz( (int) $session->quiz_id );
        if ( ! $quiz ) { wp_die( 'Quiz introuvable.' ); }

        $sender_name = '';
        $u = wp_get_current_user();
        if ( $u && $u->ID ) {
            $n = trim( $u->first_name . ' ' . $u->last_name );
            $sender_name = $n ?: $u->display_name;
        }

        $tbl_p = $this->get_qz_table( 'participants' );
        // Créer la colonne si absente
        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$tbl_p} LIKE 'last_reminder_sent_at'" );
        if ( empty( $cols ) ) {
            $wpdb->query( "ALTER TABLE {$tbl_p} ADD COLUMN last_reminder_sent_at DATETIME NULL" );
        }

        $pending = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$tbl_p}
             WHERE session_id = %d AND status IN ('invited', 'opened', 'in_progress')",
            $session_id
        ) );

        $sent_count = 0;
        foreach ( (array) $pending as $p ) {
            // Nouveau token (le hash seul est stocké, le brut est nécessaire pour l'URL)
            $tk_r = $this->generate_qz_secure_token( (string) $quiz->quiz_purpose );
            $wpdb->update( $tbl_p,
                array( 'secure_token' => $tk_r['hash'], 'updated_at' => current_time( 'mysql' ) ),
                array( 'id' => (int) $p->id ), array( '%s', '%s' ), array( '%d' )
            );
            $passation_url = $this->build_qz_async_public_url( $tk_r['token'] );
            $sent = $this->send_qz_manual_reminder_email( array(
                'email'         => $p->email,
                'first_name'    => trim( explode( ' ', (string) $p->full_name )[0] ),
                'last_name'     => '',
                'quiz'          => $quiz,
                'token'          => $tk_r['token'],  // render_qz_email_template construit passation_url à partir de token
                'expires_at'    => (string) ( $p->token_expires_at ?? '' ),
                'participant_id'=> (int) $p->id,
                'sender_name'   => $sender_name,
            ) );
            if ( $sent ) {
                $sent_count++;
                $wpdb->update( $tbl_p,
                    array( 'last_reminder_sent_at' => current_time( 'mysql' ) ),
                    array( 'id' => (int) $p->id ), array( '%s' ), array( '%d' )
                );
                $this->log_qz_event( array(
                    'quiz_id'       => (int) $quiz->id,
                    'event_type'    => 'reminder_manual',
                    'event_label'   => sprintf( 'Relance session envoyée à %s <%s> — quiz : %s — par : %s',
                        $p->full_name, $p->email, $quiz->title, $sender_name ),
                    'event_payload' => array(
                        'participant_id' => (int) $p->id,
                        'session_id'     => $session_id,
                        'email'          => $p->email,
                        'sent_by'        => $sender_name,
                        'sent_at'        => current_time( 'mysql' ),
                        'quiz_purpose'   => (string) $quiz->quiz_purpose,
                    ),
                ) );
            }
        }

        $tab = isset( $_POST['current_tab'] ) ? sanitize_key( wp_unslash( $_POST['current_tab'] ) ) : 'qz_results_positioning';
        $redirect_url = $this->portal_page_url( array( 'tab' => $tab, 'view' => 'results', 'session' => $session_id ) );
        wp_safe_redirect( add_query_arg(
            array( 'acdc_qz_notice' => rawurlencode( $sent_count . ' relance(s) envoyée(s).' ), 'acdc_qz_notice_type' => 'success' ),
            $redirect_url
        ) );
        exit;
    }

        public function handle_acdc_of_qz_remind_participant() {
        global $wpdb;
        $this->qz_check_admin_request( 'acdc_of_qz_remind_participant' );

        $participant_id = isset( $_POST['participant_id'] ) ? absint( wp_unslash( $_POST['participant_id'] ) ) : 0;
        $p = $this->get_qz_participant( $participant_id );
        if ( ! $p ) {
            wp_die( esc_html__( 'Participant introuvable.', 'acdc-formation-saas' ) );
        }
        if ( 'completed' === $p->status || 'cancelled' === $p->status ) {
            wp_die( esc_html__( 'Ce participant a déjà terminé ou a été annulé.', 'acdc-formation-saas' ) );
        }

        $tbl_s = $this->get_qz_table( 'sessions' );
        $session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl_s} WHERE id = %d", (int) $p->session_id ) );
        $quiz = $session ? $this->get_qz_quiz( (int) $session->quiz_id ) : null;
        if ( ! $quiz ) {
            wp_die( esc_html__( 'Quiz introuvable.', 'acdc-formation-saas' ) );
        }

        // Nom de l’expéditeur (formateur ou admin)
        $sender_name = '';
        $u = wp_get_current_user();
        if ( $u && $u->ID ) {
            $n = trim( $u->first_name . ' ' . $u->last_name );
            $sender_name = $n ?: $u->display_name;
        }

        // Le secure_token en base est un hash SHA-256 — le token brut n'est pas stocké.
        // On génère un nouveau token silencieusement (sans réinitialiser la progression).
        $tk = $this->generate_qz_secure_token( (string) $quiz->quiz_purpose );
        $tbl_p_remind = $this->get_qz_table( 'participants' );
        $wpdb->update( $tbl_p_remind,
            array( 'secure_token' => $tk['hash'], 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => (int) $participant_id ), array( '%s', '%s' ), array( '%d' )
        );
        $passation_url = $this->build_qz_async_public_url( $tk['token'] );

        $sent = $this->send_qz_manual_reminder_email( array(
            'email'          => $p->email,
            'first_name'     => trim( explode( ' ', (string) $p->full_name )[0] ),
            'last_name'      => '',
            'token'          => $tk['token'],  // render_qz_email_template construit passation_url à partir de token
            'quiz'           => $quiz,
            'expires_at'     => (string) ( $p->token_expires_at ?? '' ),
            'participant_id' => (int) $participant_id,
            'sender_name'    => $sender_name,
        ) );

        if ( $sent ) {
            // Mettre à jour last_reminder_sent_at (créer la colonne si absente)
            $tbl_p = $this->get_qz_table( 'participants' );
            $cols  = $wpdb->get_col( "SHOW COLUMNS FROM {$tbl_p} LIKE 'last_reminder_sent_at'" );
            if ( empty( $cols ) ) {
                $wpdb->query( "ALTER TABLE {$tbl_p} ADD COLUMN last_reminder_sent_at DATETIME NULL" );
            }
            $wpdb->update( $tbl_p,
                array( 'last_reminder_sent_at' => current_time( 'mysql' ) ),
                array( 'id' => (int) $participant_id ),
                array( '%s' ), array( '%d' )
            );

            // Log Qualiopi : preuve d’envoi de la relance
            $this->log_qz_event( array(
                'quiz_id'       => (int) $quiz->id,
                'event_type'    => 'reminder_manual',
                'event_label'   => sprintf(
                    'Relance manuelle envoyée à %s <%s> — quiz : %s — par : %s',
                    (string) $p->full_name,
                    (string) $p->email,
                    (string) $quiz->title,
                    $sender_name ?: 'inconnu'
                ),
                'event_payload' => array(
                    'participant_id' => (int) $participant_id,
                    'session_id'     => (int) $p->session_id,
                    'email'          => (string) $p->email,
                    'sent_by'        => $sender_name,
                    'sent_at'        => current_time( 'mysql' ),
                    'quiz_purpose'   => (string) $quiz->quiz_purpose,
                ),
            ) );
        }

        $this->qz_redirect(
            (string) $quiz->quiz_purpose,
            array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => (int) $quiz->id ),
            $sent
                ? sprintf( 'Relance envoyée à %s.', $p->full_name )
                : __( 'Erreur lors de l\'envoi de la relance.', 'acdc-formation-saas' ),
            $sent ? 'success' : 'error'
        );
    }

        public function handle_acdc_of_qz_resend_participant() {
        global $wpdb;
        $this->qz_check_admin_request( 'acdc_of_qz_resend_participant' );

        $participant_id = isset( $_POST['participant_id'] ) ? absint( wp_unslash( $_POST['participant_id'] ) ) : 0;
        $p = $this->get_qz_participant( $participant_id );
        if ( ! $p ) {
            wp_die( esc_html__( 'Participant introuvable.', 'acdc-formation-saas' ) );
        }
        if ( 'completed' === $p->status ) {
            wp_die( esc_html__( "Ce participant a déjà terminé.", 'acdc-formation-saas' ) );
        }

        $tbl_s = $this->get_qz_table( 'sessions' );
        $session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl_s} WHERE id = %d", (int) $p->session_id ) );
        $quiz = $session ? $this->get_qz_quiz( (int) $session->quiz_id ) : null;
        if ( ! $quiz ) {
            wp_die( esc_html__( 'Quiz introuvable.', 'acdc-formation-saas' ) );
        }

        // Génère un nouveau token (le précédent est invalidé puisque le hash change)
        $tk = $this->generate_qz_secure_token( (string) $quiz->quiz_purpose );
        $tbl_p = $this->get_qz_table( 'participants' );
        $now = current_time( 'mysql' );
        $wpdb->update( $tbl_p, array(
            'secure_token' => $tk['hash'],
            'status'       => 'invited',
            'opened_at'    => null,
            'started_at'   => null,
            'updated_at'   => $now,
        ), array( 'id' => (int) $participant_id ) );

        // Renvoie un mail
        $name_parts = explode( ' ', (string) $p->full_name, 2 );
        $sent = $this->send_qz_async_invitation_email( array(
            'email'           => $p->email,
            'first_name'      => isset( $name_parts[0] ) ? $name_parts[0] : '',
            'last_name'       => isset( $name_parts[1] ) ? $name_parts[1] : '',
            'token'           => $tk['token'],
            'quiz'            => $quiz,
            'expires_at'      => $p->token_expires_at,
            'custom_message'  => '',
            'participant_id'  => (int) $participant_id,
        ) );

        $this->qz_redirect(
            (string) $quiz->quiz_purpose,
            array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => (int) $quiz->id ),
            $sent
                ? __( 'Nouveau lien envoyé.', 'acdc-formation-saas' )
                : __( "Échec de l'envoi du nouveau lien.", 'acdc-formation-saas' ),
            $sent ? 'success' : 'error'
        );
    }

    public function handle_acdc_of_qz_send_reminder()       { $this->qz_stub_admin_post( 'send_reminder' ); }

    /* ====================================================================
     *  3.21.03.1-hotfix2 — Cacher les pages techniques (passation publique)
     *  des menus auto, des listes de pages, de la recherche et des moteurs.
     * ==================================================================== */

    /**
     * Renvoie les IDs des pages techniques du module quiz à cacher des menus
     * et listes publiques. Ces pages doivent rester accessibles directement
     * par leur URL (avec token), mais ne doivent pas apparaître dans la
     * navigation publique du site.
     *
     * @since 3.21.03.1-hotfix2
     *
     * @return int[]
     */
    private function qz_get_technical_page_ids() {
        $ids = array();
        $async = (int) get_option( 'acdc_of_qz_async_public_page_id', 0 );
        $live  = (int) get_option( 'acdc_of_qz_live_public_page_id', 0 );
        if ( $async > 0 ) { $ids[] = $async; }
        if ( $live  > 0 ) { $ids[] = $live; }
        return $ids;
    }

    /**
     * Filtre wp_list_pages_excludes : ajoute les pages techniques aux IDs
     * exclus quand WordPress liste les pages dans le menu auto.
     *
     * @since 3.21.03.1-hotfix2
     *
     * @param array $exclude_array
     *
     * @return array
     */
    public function qz_exclude_technical_pages_from_menus( $exclude_array ) {
        if ( ! is_array( $exclude_array ) ) {
            $exclude_array = array();
        }
        return array_merge( $exclude_array, $this->qz_get_technical_page_ids() );
    }

    /**
     * Filtre get_pages : retire nos pages techniques du tableau renvoyé.
     * Couvre Twenty Twenty-Five qui utilise get_pages() pour son menu auto.
     *
     * @since 3.21.03.1-hotfix2
     *
     * @param array $pages
     * @param array $args
     *
     * @return array
     */
    public function qz_filter_get_pages( $pages, $args ) {
        $excluded = $this->qz_get_technical_page_ids();
        if ( empty( $excluded ) || empty( $pages ) ) {
            return $pages;
        }
        // Ne pas filtrer dans wp-admin pour ne pas masquer la page dans Pages →
        // Toutes les pages, sinon impossible à gérer manuellement.
        if ( is_admin() ) {
            return $pages;
        }
        $filtered = array();
        foreach ( $pages as $page ) {
            $id = is_object( $page ) ? (int) $page->ID : 0;
            if ( ! in_array( $id, $excluded, true ) ) {
                $filtered[] = $page;
            }
        }
        return $filtered;
    }

    /**
     * Exclut les pages techniques de la recherche WordPress.
     *
     * @since 3.21.03.1-hotfix2
     *
     * @param WP_Query $query
     *
     * @return void
     */
    public function qz_exclude_technical_pages_from_search( $query ) {
        if ( is_admin() || ! $query->is_main_query() ) {
            return;
        }
        if ( ! $query->is_search() ) {
            return;
        }
        $excluded = $this->qz_get_technical_page_ids();
        if ( empty( $excluded ) ) {
            return;
        }
        $existing = (array) $query->get( 'post__not_in' );
        $query->set( 'post__not_in', array_merge( $existing, $excluded ) );
    }

    /**
     * Ajoute noindex/nofollow aux pages techniques pour éviter qu'elles
     * soient indexées par Google et apparaissent dans les SERP.
     *
     * @since 3.21.03.1-hotfix2
     *
     * @param array $robots
     *
     * @return array
     */
    public function qz_robots_noindex_technical_pages( $robots ) {
        if ( is_admin() ) {
            return $robots;
        }
        if ( ! is_page() ) {
            return $robots;
        }
        $current_id = (int) get_queried_object_id();
        if ( $current_id > 0 && in_array( $current_id, $this->qz_get_technical_page_ids(), true ) ) {
            $robots['noindex']  = true;
            $robots['nofollow'] = true;
        }
        return $robots;
    }

    /**
     * 3.21.03.1-hotfix3 — Sert un template autonome (sans header/footer du thème)
     * pour la page de passation publique. L'apprenant arrive via un mail, il ne
     * doit voir que la carte de passation, sans nav du site qui distrait.
     *
     * @param string $template
     *
     * @return string
     */
    public function qz_full_page_template_for_public_page( $template ) {
        if ( is_admin() || ! is_page() ) {
            return $template;
        }
        $current_id = (int) get_queried_object_id();
        if ( $current_id <= 0 ) {
            return $template;
        }
        $async_id = (int) get_option( 'acdc_of_qz_async_public_page_id', 0 );
        $live_id  = (int) get_option( 'acdc_of_qz_live_public_page_id', 0 );
        $host_id  = (int) get_option( 'acdc_of_qz_live_host_page_id', 0 );
        if ( $current_id === $async_id || $current_id === $live_id || $current_id === $host_id ) {
            $standalone = ACDC_OF_SAAS_DIR . 'includes/quizzes/templates/standalone-public-page.php';
            if ( file_exists( $standalone ) ) {
                return $standalone;
            }
        }
        return $template;
    }
    public function handle_acdc_of_qz_cancel_session()      { $this->qz_stub_admin_post( 'cancel_session' ); }
    public function handle_acdc_of_qz_save_paper_copy()       { $this->qz_stub_admin_post( 'save_paper_copy' ); }
    public function handle_acdc_of_qz_reset_attempt()         { $this->qz_stub_admin_post( 'reset_attempt' ); }
    public function handle_acdc_of_qz_anonymize_participant() { $this->qz_stub_admin_post( 'anonymize_participant' ); }

    /**
     * ACDC 3.21.65 — Export JSON d'un quiz existant au format import IA (v1.0).
     * Permet de télécharger le quiz, de le modifier via ChatGPT, puis de le réimporter.
     */
    public function handle_acdc_of_qz_export_json() {
        if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
            wp_die( esc_html( 'Accès refusé.' ) );
        }
        $quiz_id = isset( $_GET['quiz_id'] ) ? absint( wp_unslash( $_GET['quiz_id'] ) ) : 0;
        if ( ! $quiz_id ) {
            wp_die( esc_html( 'Quiz introuvable.' ) );
        }
        check_admin_referer( 'acdc_of_qz_export_json_' . $quiz_id );

        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) {
            wp_die( esc_html( 'Quiz introuvable.' ) );
        }

        $objectives_raw = method_exists( $this, 'get_qz_objectives_for_quiz' )
            ? $this->get_qz_objectives_for_quiz( $quiz_id )
            : array();

        $questions_raw = $this->get_qz_questions_for_quiz( $quiz_id );

        // Construction du bloc "objectives" avec ref obj_N
        $objectives_out = array();
        $obj_id_to_ref  = array();
        $obj_idx = 1;
        foreach ( (array) $objectives_raw as $obj ) {
            $ref = 'obj_' . $obj_idx;
            $obj_id_to_ref[ (int) $obj->id ] = $ref;
            $objectives_out[] = array(
                'ref'         => $ref,
                'label'       => (string) $obj->label,
                'description' => ! empty( $obj->description ) ? (string) $obj->description : '',
            );
            $obj_idx++;
        }

        // Construction du bloc "questions"
        $questions_out = array();
        foreach ( (array) $questions_raw as $q ) {
            $type = (string) $q->type;
            $answers_out = array();
            foreach ( (array) $q->answers as $a ) {
                $answer_item = array(
                    'text'       => (string) $a->text,
                    'is_correct' => ! empty( $a->is_correct ),
                );
                if ( ! empty( $a->feedback_text ) ) {
                    $answer_item['feedback_text'] = (string) $a->feedback_text;
                } else {
                    $answer_item['feedback_text'] = null;
                }
                $answers_out[] = $answer_item;
            }

            $q_out = array(
                'type'          => $type,
                'title'         => (string) $q->title,
                'description'   => ! empty( $q->description ) ? (string) $q->description : null,
            );

            if ( 'open_text' === $type && ! empty( $q->expected_answer ) ) {
                $q_out['expected_answer'] = (string) $q->expected_answer;
            }

            $obj_ref = '';
            if ( ! empty( $q->objective_id ) && isset( $obj_id_to_ref[ (int) $q->objective_id ] ) ) {
                $obj_ref = $obj_id_to_ref[ (int) $q->objective_id ];
            }
            if ( '' !== $obj_ref ) {
                $q_out['objective_ref'] = $obj_ref;
            }

            if ( 'open_text' !== $type ) {
                $q_out['answers'] = $answers_out;
            }

            $questions_out[] = $q_out;
        }

        // Document final au format import IA v1.0
        $export = array(
            'acdc_quiz_import_version' => '1.0',
            'quiz' => array(
                'title'       => (string) $quiz->title,
                'description' => ! empty( $quiz->description ) ? (string) $quiz->description : '',
                'quiz_purpose' => (string) $quiz->quiz_purpose,
                'language'    => 'fr',
            ),
            'objectives' => $objectives_out,
            'questions'  => $questions_out,
        );

        $json     = wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        $filename = sanitize_file_name( 'quiz-' . $quiz_id . '-' . sanitize_title( $quiz->title ) . '.json' );

        while ( ob_get_level() ) { ob_end_clean(); }
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $json ) );
        echo $json;
        exit;
    }

    public function handle_acdc_of_qz_export_csv()           { $this->qz_stub_admin_post( 'export_csv' ); }
    public function handle_acdc_of_qz_export_excel()         { $this->qz_stub_admin_post( 'export_excel' ); }
    public function handle_acdc_of_qz_export_pdf()           { $this->qz_stub_admin_post( 'export_pdf' ); }
    public function handle_acdc_of_qz_download_attestation() { $this->qz_stub_admin_post( 'download_attestation' ); }

    public function handle_acdc_of_qz_async_submit() {
        $page_id = (int) get_option( 'acdc_of_qz_async_public_page_id', 0 );
        $url     = $page_id ? get_permalink( $page_id ) : home_url( '/' );
        $url     = $url ? add_query_arg( 'acdc_qz_notice', 'feature_pending', $url ) : home_url( '/' );

        $this->log_qz_event( array(
            'event_type'  => 'stub_called',
            'event_label' => 'async_submit',
        ) );

        wp_safe_redirect( $url );
        exit;
    }

    /* ====================================================================
     * 3.21.04.2-a — HANDLERS AJAX MOTEUR LIVE
     * ==================================================================== */

    /**
     * Helper : vérifie l'autorisation host (utilisateur connecté qui possède la session).
     */
    private function qz_check_host_auth( $session_id, $nonce ) {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Non connecté' ), 401 );
        }
        if ( ! wp_verify_nonce( $nonce, 'acdc_of_qz_live_host_' . (int) $session_id ) ) {
            wp_send_json_error( array( 'message' => 'Nonce invalide' ), 403 );
        }
        $session = $this->get_qz_dispatch_session( (int) $session_id );
        if ( ! $session || 'live' !== $session->delivery_mode ) {
            wp_send_json_error( array( 'message' => 'Session live introuvable' ), 404 );
        }
        $current_user_id = get_current_user_id();
        if ( (int) $session->host_id !== $current_user_id && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Non autorisé' ), 403 );
        }
        return $session;
    }

    /**
     * Helper : vérifie l'autorisation player (token sécurisé).
     */
    private function qz_check_player_auth( $participant_id, $token ) {
        global $wpdb;
        $tbl_p = $this->get_qz_table( 'participants' );
        $p = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$tbl_p} WHERE id=%d AND secure_token=%s LIMIT 1",
            (int) $participant_id, (string) $token
        ) );
        if ( ! $p ) {
            wp_send_json_error( array( 'message' => 'Participant invalide' ), 403 );
        }
        return $p;
    }

    public function ajax_acdc_of_qz_host_lobby_state() {
        $session_id = isset( $_REQUEST['session_id'] ) ? (int) $_REQUEST['session_id'] : 0;
        $nonce      = isset( $_REQUEST['_wpnonce'] ) ? (string) $_REQUEST['_wpnonce'] : '';
        $session = $this->qz_check_host_auth( $session_id, $nonce );
        $state = $this->get_qz_live_state( (int) $session->id );
        wp_send_json_success( $state );
    }

    public function ajax_acdc_of_qz_host_start_quiz() {
        $session_id = isset( $_REQUEST['session_id'] ) ? (int) $_REQUEST['session_id'] : 0;
        $nonce      = isset( $_REQUEST['_wpnonce'] ) ? (string) $_REQUEST['_wpnonce'] : '';
        $session = $this->qz_check_host_auth( $session_id, $nonce );
        $r = $this->start_qz_live_session( (int) $session->id );
        if ( is_wp_error( $r ) ) {
            wp_send_json_error( array( 'message' => $r->get_error_message() ), 400 );
        }
        wp_send_json_success( array( 'started' => true ) );
    }

    public function ajax_acdc_of_qz_host_next_question() {
        $session_id = isset( $_REQUEST['session_id'] ) ? (int) $_REQUEST['session_id'] : 0;
        $nonce      = isset( $_REQUEST['_wpnonce'] ) ? (string) $_REQUEST['_wpnonce'] : '';
        $session = $this->qz_check_host_auth( $session_id, $nonce );
        $r = $this->advance_qz_live_question( (int) $session->id );
        if ( is_wp_error( $r ) ) {
            wp_send_json_error( array( 'message' => $r->get_error_message() ), 400 );
        }
        wp_send_json_success( array( 'next_question_id' => is_int( $r ) ? $r : null, 'ended' => true === $r ) );
    }

    public function ajax_acdc_of_qz_host_show_results() {
        // Réservé pour la révélation manuelle anticipée — la révélation auto est gérée par le client
        // côté player en se basant sur all_answered. Ce handler ne fait rien de plus pour l'instant.
        wp_send_json_success( array( 'noop' => true ) );
    }

    public function ajax_acdc_of_qz_host_kick_participant() {
        global $wpdb;
        $session_id     = isset( $_REQUEST['session_id'] ) ? (int) $_REQUEST['session_id'] : 0;
        $participant_id = isset( $_REQUEST['participant_id'] ) ? (int) $_REQUEST['participant_id'] : 0;
        $nonce          = isset( $_REQUEST['_wpnonce'] ) ? (string) $_REQUEST['_wpnonce'] : '';
        $session = $this->qz_check_host_auth( $session_id, $nonce );
        $tbl_p = $this->get_qz_table( 'participants' );
        $wpdb->update( $tbl_p, array(
            'status'     => 'cancelled',
            'updated_at' => current_time( 'mysql' ),
        ), array( 'id' => $participant_id, 'session_id' => (int) $session->id ), array( '%s','%s' ), array( '%d','%d' ) );
        wp_send_json_success( array( 'kicked' => true ) );
    }

    public function ajax_acdc_of_qz_host_end_session() {
        $session_id = isset( $_REQUEST['session_id'] ) ? (int) $_REQUEST['session_id'] : 0;
        $nonce      = isset( $_REQUEST['_wpnonce'] ) ? (string) $_REQUEST['_wpnonce'] : '';
        $session = $this->qz_check_host_auth( $session_id, $nonce );
        $this->end_qz_live_session( (int) $session->id );
        wp_send_json_success( array( 'ended' => true ) );
    }

    /**
     * ACDC 3.21.06 — Retourne la liste des apprenants d'une séance à partir du PIN.
     * Utilisé par le player pour afficher les noms avant le join (Option B identification).
     */
    public function ajax_acdc_of_qz_get_session_learners() {
        global $wpdb;
        $pin = isset( $_REQUEST['pin'] ) ? preg_replace( '/[^0-9]/', '', (string) wp_unslash( $_REQUEST['pin'] ) ) : '';
        if ( strlen( $pin ) < 4 ) {
            wp_send_json_error( array( 'message' => 'PIN invalide' ), 400 );
        }
        // C01 (audit 3.25.90) — anti brute-force d'énumération des PIN, par IP. On ne compte que les échecs de résolution : un apprenant légitime (bon PIN affiché par le formateur) ne consomme jamais le quota, même derrière une IP partagée d'établissement.
        $qz_ip     = sanitize_text_field( wp_unslash( isset( $_SERVER["REMOTE_ADDR"] ) ? $_SERVER["REMOTE_ADDR"] : "" ) );
        $qz_rl_key = "acdc_qz_pinrl_" . md5( $qz_ip );
        $qz_rl_cnt = (int) get_transient( $qz_rl_key );
        if ( $qz_rl_cnt >= 10 ) {
            wp_send_json_error( array( "message" => "Trop de tentatives. Réessayez dans quelques minutes." ), 429 );
        }
        $tbl_s = $this->get_qz_table( 'sessions' );
        $session = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, formation_session_id FROM {$tbl_s}
             WHERE pin_code = %s AND delivery_mode = 'live' AND status IN ('lobby','in_progress') LIMIT 1",
            $pin
        ) );
        if ( ! $session ) {
            set_transient( $qz_rl_key, $qz_rl_cnt + 1, 10 * MINUTE_IN_SECONDS );
            wp_send_json_error( array( 'message' => 'PIN invalide ou partie terminée' ), 404 );
        }
        // Pas de formation_session_id → pas d'apprenants identifiables
        if ( empty( $session->formation_session_id ) ) {
            wp_send_json_success( array( 'learners' => array() ) );
        }
        /* ACDC 3.25.271 — J'AVAIS RÉPARÉ LA SERRURE, PAS LA SONNETTE.
         *
         * La 3.25.266 a corrigé le CONTRÔLE d'appartenance : quand un
         * identifiant d'apprenant arrive, il est reconnu par le résolveur à
         * trois chemins. Mais la LISTE dans laquelle l'apprenant choisit son
         * nom en rejoignant la partie interrogeait toujours
         * `apprenants.session_id`. Sur une séance née d'une convention, cette
         * colonne est vide : la liste était vide, l'apprenant ne pouvait pas se
         * désigner, aucun identifiant n'était envoyé — et mon contrôle n'avait
         * jamais rien à vérifier. Le participant restait un pseudo, avec
         * qualiopi_traceable = 0.
         *
         * Le filtre sur l'adresse e-mail disparaît aussi. Il excluait de la
         * liste un apprenant qui n'en a pas — alors qu'en salle, jouer ne
         * demande pas d'adresse. Un quiz ne doit pas dépendre d'une donnée dont
         * il n'a pas besoin.
         */
        $formation_session = method_exists( $this, 'get_session' )
            ? $this->get_session( (int) $session->formation_session_id )
            : null;
        $learners = array();
        if ( $formation_session && method_exists( $this, 'acdc_session_learners' ) ) {
            foreach ( (array) $this->acdc_session_learners( $formation_session ) as $sl ) {
                $learners[] = (object) array(
                    'id'         => (int) $sl->id,
                    'first_name' => (string) $sl->first_name,
                    'last_name'  => (string) ( ! empty( $sl->usage_last_name ) ? $sl->usage_last_name : $sl->last_name ),
                );
            }
        }
        wp_send_json_success( array( 'learners' => $learners ) );
    }

        public function ajax_acdc_of_qz_player_join() {
        global $wpdb;
        $pin      = isset( $_REQUEST['pin'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['pin'] ) ) : '';
        $nickname = isset( $_REQUEST['nickname'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['nickname'] ) ) : '';
        // ACDC 3.25.173 — « neutre » devient le défaut : on n'exige plus une donnée de
        // genre pour afficher un pictogramme.
        $avatar   = isset( $_REQUEST['avatar'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['avatar'] ) ) : 'neutre';
        $pin = preg_replace( '/[^0-9]/', '', $pin );
        if ( strlen( $pin ) < 4 || ! in_array( $avatar, array( 'femme', 'homme', 'neutre' ), true ) ) {
            wp_send_json_error( array( 'message' => 'Données invalides' ), 400 );
        }
        if ( strlen( $nickname ) < 1 || strlen( $nickname ) > 30 ) {
            wp_send_json_error( array( 'message' => 'Pseudo entre 1 et 30 caractères' ), 400 );
        }
        // C04 (audit 3.25.90) — anti-abus : limite la création de participants par IP. Seuil large calibré pour absorber une classe entière derrière une IP partagée (NAT d'une salle), tout en bloquant la création massive automatisée. Chaque tentative est comptée (un join légitime crée un participant).
        $qz_join_ip  = sanitize_text_field( wp_unslash( isset( $_SERVER["REMOTE_ADDR"] ) ? $_SERVER["REMOTE_ADDR"] : "" ) );
        $qz_join_key = "acdc_qz_joinrl_" . md5( $qz_join_ip );
        $qz_join_cnt = (int) get_transient( $qz_join_key );
        if ( $qz_join_cnt >= 100 ) {
            wp_send_json_error( array( "message" => "Trop de connexions depuis ce réseau. Patientez quelques minutes." ), 429 );
        }
        set_transient( $qz_join_key, $qz_join_cnt + 1, 10 * MINUTE_IN_SECONDS );
        $tbl_s = $this->get_qz_table( 'sessions' );
        $session = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$tbl_s} WHERE pin_code=%s AND delivery_mode='live' AND status IN ('lobby','in_progress') LIMIT 1",
            $pin
        ) );
        if ( ! $session ) {
            wp_send_json_error( array( 'message' => 'PIN invalide ou partie terminée' ), 404 );
        }
        // Vérifier unicité du pseudo dans la session
        $tbl_p = $this->get_qz_table( 'participants' );
        $exists = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tbl_p} WHERE session_id=%d AND nickname=%s AND status NOT IN ('cancelled')",
            (int) $session->id, $nickname
        ) );
        if ( $exists > 0 ) {
            wp_send_json_error( array( 'message' => 'Ce pseudo est déjà pris dans cette partie' ), 409 );
        }
        // Création du participant
        $token      = wp_generate_password( 32, false );
        $now        = current_time( 'mysql' );
        $is_late    = ( 'in_progress' === $session->status );
        $learner_id = isset( $_REQUEST['learner_id'] ) ? absint( wp_unslash( $_REQUEST['learner_id'] ) ) : 0;
        /* ACDC 3.25.266 — LE QUIZ PERDAIT L'APPRENANT EN SILENCE.
         *
         * Le contrôle d'appartenance interrogeait « apprenants.session_id », le
         * rattachement DIRECT. Depuis que la convention crée les séances, cette
         * colonne reste vide : l'apprenant n'était pas reconnu, le code posait
         * learner_id = 0 sans un mot, et le participant était enregistré non
         * rattaché — avec qualiopi_traceable = 0.
         *
         * La conséquence dépasse l'affichage : un quiz diagnostique rattaché à
         * personne ne remonte dans aucun dossier et ne vaut rien comme preuve
         * Qualiopi. Et rien ne le signalait, ni à l'apprenant, ni au formateur.
         *
         * On interroge le résolveur, qui réunit les trois rattachements —
         * direct, groupe, convention — comme partout ailleurs depuis la
         * 3.25.265.
         */
        $is_traceable = 0;
        if ( $learner_id > 0 && ! empty( $session->formation_session_id ) ) {
            $formation_session = method_exists( $this, 'get_session' )
                ? $this->get_session( (int) $session->formation_session_id )
                : null;
            $session_learner_ids = array();
            if ( $formation_session && method_exists( $this, 'acdc_session_learners' ) ) {
                foreach ( (array) $this->acdc_session_learners( $formation_session ) as $sl ) {
                    if ( ! empty( $sl->id ) ) {
                        $session_learner_ids[] = (int) $sl->id;
                    }
                }
            }
            if ( ! in_array( $learner_id, $session_learner_ids, true ) ) {
                $learner_id = 0; // Apprenant non reconnu sur cette séance
            } else {
                $is_traceable = 1;
            }
        }
        $wpdb->insert( $tbl_p, array(
            'session_id'        => (int) $session->id,
            'learner_id'        => $learner_id > 0 ? $learner_id : null,
            'nickname'          => $nickname,
            'avatar'            => $avatar,
            'secure_token'      => $token,
            'status'            => $is_late ? 'in_progress' : 'invited',
            'qualiopi_traceable'=> $is_traceable,
            'total_score'       => 0,
            'joined_at'         => $now,
            'started_at'        => $is_late ? $now : null,
            'updated_at'        => $now,
        ), array( '%d','%d','%s','%s','%s','%s','%d','%f','%s','%s','%s' ) );
        $participant_id = (int) $wpdb->insert_id;
        wp_send_json_success( array(
            'participant_id' => $participant_id,
            'token'          => $token,
            'session_id'     => (int) $session->id,
            'late'           => $is_late,
        ) );
    }

    public function ajax_acdc_of_qz_player_poll() {
        $participant_id = isset( $_REQUEST['participant_id'] ) ? (int) $_REQUEST['participant_id'] : 0;
        $token          = isset( $_REQUEST['token'] ) ? (string) $_REQUEST['token'] : '';
        $p = $this->qz_check_player_auth( $participant_id, $token );
        $state = $this->get_qz_live_state( (int) $p->session_id );
        // Personnalise pour le player : on inclut son score et sa position
        if ( $state ) {
            global $wpdb;
            $tbl_p = $this->get_qz_table( 'participants' );
            $me = $wpdb->get_row( $wpdb->prepare(
                "SELECT id, total_score, status FROM {$tbl_p} WHERE id=%d", (int) $p->id
            ) );
            $state['me'] = array(
                'id'     => (int) $me->id,
                'score'  => (float) $me->total_score,
                'status' => (string) $me->status,
            );
            // Si la session est terminée, joindre le leaderboard pour podium
            if ( 'ended' === $state['status'] ) {
                $state['leaderboard'] = $this->get_qz_live_leaderboard( (int) $p->session_id );
            }
        }
        wp_send_json_success( $state );
    }

    public function ajax_acdc_of_qz_player_submit_answer() {
        global $wpdb;
        $participant_id = isset( $_REQUEST['participant_id'] ) ? (int) $_REQUEST['participant_id'] : 0;
        $token          = isset( $_REQUEST['token'] ) ? (string) $_REQUEST['token'] : '';
        $question_id    = isset( $_REQUEST['question_id'] ) ? (int) $_REQUEST['question_id'] : 0;
        $answer_ids_raw = isset( $_REQUEST['answer_ids'] ) ? (string) $_REQUEST['answer_ids'] : '';
        $answer_text    = isset( $_REQUEST['answer_text'] ) ? sanitize_textarea_field( wp_unslash( (string) $_REQUEST['answer_text'] ) ) : '';
        $response_ms    = isset( $_REQUEST['response_ms'] ) ? (int) $_REQUEST['response_ms'] : 0;
        $p = $this->qz_check_player_auth( $participant_id, $token );
        $session = $this->get_qz_dispatch_session( (int) $p->session_id );
        if ( ! $session || 'in_progress' !== $session->status || (int) $session->current_question_id !== $question_id ) {
            wp_send_json_error( array( 'message' => 'Question non active' ), 409 );
        }
        $question = $this->get_qz_question( $question_id );
        if ( ! $question ) {
            wp_send_json_error( array( 'message' => 'Question introuvable' ), 404 );
        }
        /* ACDC 3.25.168 — Correction déléguée à qz_grade_answer_set(), qui rend une PART
           de réussite et non plus un simple oui/non : sur un QCM à réponses multiples,
           deux bonnes cases sur trois valent désormais deux tiers des points. */
        $answer_ids = array_values( array_filter( array_map( 'intval', explode( ',', $answer_ids_raw ) ) ) );
        $verdict     = $this->qz_grade_answer_set( $question, $answer_ids );
        $is_correct  = $verdict['is_correct'];
        /* ACDC 3.25.171 — NULL, et non zéro, quand la réponse n'est pas corrigeable
           automatiquement. Une réponse rédigée était enregistrée avec une part de zéro ;
           après correction manuelle du formateur, les écrans qui lisent cette part
           continuaient d'afficher 0 % à côté d'un « juste ». */
        $score_ratio = ( null === $is_correct ) ? null : $verdict['ratio'];
        // Sécurité anti-triche : le temps serveur (non falsifiable) est autoritatif dès
        // qu'il est connu. Un min(client, serveur) serait inefficace (response_ms=0 → 0).
        // Voir \ACDC\Support\QuizScore::clampResponseMs (couvert par PHPUnit).
        $server_elapsed_ms = 0;
        if ( ! empty( $session->current_question_started_at ) ) {
            // ACDC 3.25.113 — base de temps homogène : current_question_started_at est écrit en
            // heure murale locale WP (current_time('mysql')), donc comparer avec current_time('timestamp')
            // et NON time() (UTC). Sinon l'anti-triche est contourné (offset+) ou tous les scores faux (offset-).
            $server_elapsed_ms = max( 0, ( current_time( 'timestamp' ) - strtotime( $session->current_question_started_at ) ) * 1000 );
        }
        $response_ms = \ACDC\Support\QuizScore::clampResponseMs( $response_ms, $server_elapsed_ms );

        /* ACDC 3.25.173 — Le serveur ne ferme jamais une question : il acceptait donc
           une réponse arrivée bien après la fin du temps imparti, et la notait. Sur une
           évaluation, dont le barème ne dépend pas de la vitesse depuis la 3.25.168,
           une réponse hors délai empochait même la totalité des points — l'anti-triche
           du chronomètre ne servait à rien. Deux secondes de tolérance couvrent la
           latence réseau et l'écart entre l'horloge du navigateur et celle du serveur.
           Une question sans limite de temps n'est évidemment jamais concernée. */
        $time_limit_ms = (int) $question->time_limit * 1000;
        if ( $time_limit_ms > 0 && $server_elapsed_ms > ( $time_limit_ms + 2000 ) ) {
            wp_send_json_error( array(
                'code'    => 'qz_time_over',
                'message' => 'Temps écoulé : cette réponse n\'a pas été prise en compte.',
            ), 409 );
        }

        /* ACDC 3.25.168 — Le barème dépend de la FINALITÉ, pas de la modalité. Le quiz
           live est un jeu : la prime à la rapidité y a du sens. Une évaluation passée
           dans la même salle, avec les mêmes écrans, reste une évaluation : elle doit
           rendre un score pédagogique sur les points de la question, sans bonus de
           vitesse — sinon le pourcentage d'acquisition ne veut rien dire, et le verdict
           acquis / non acquis récompense les doigts rapides. */
        if ( null === $is_correct ) {
            $score = 0;
        } elseif ( self::ACDC_OF_QZ_PURPOSE_LIVE === (string) $session->quiz_purpose ) {
            $score = $this->qz_calculate_kahoot_score(
                (bool) $is_correct, $response_ms, (int) $question->time_limit
            ) * (float) $score_ratio;
        } else {
            $score = (float) $question->points_value * (float) $score_ratio;
        }
        // Verrou souple : UPSERT (un participant peut corriger sa réponse tant que la question est ouverte)
        $tbl_pa = $this->get_qz_table( 'player_answers' );
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$tbl_pa} WHERE participant_id=%d AND question_id=%d AND session_id=%d LIMIT 1",
            (int) $p->id, $question_id, (int) $p->session_id
        ) );
        $now = current_time( 'mysql' );
        $data = array(
            'session_id'      => (int) $p->session_id,
            'participant_id'  => (int) $p->id,
            'question_id'     => $question_id,
            'answer_ids_json' => wp_json_encode( $answer_ids ),
            'answer_text'     => $answer_text,
            'is_correct'      => $is_correct,
            'score_ratio'     => $score_ratio,
            'score_earned'    => $score,
            'response_time'   => round( $response_ms / 1000.0, 3 ),
            'answered_at'     => $now,
        );
        if ( $existing ) {
            $wpdb->update( $tbl_pa, $data, array( 'id' => (int) $existing ), array( '%d','%d','%d','%s','%s','%d','%f','%f','%f','%s' ), array( '%d' ) );
        } else {
            $wpdb->insert( $tbl_pa, $data, array( '%d','%d','%d','%s','%s','%d','%f','%f','%f','%s' ) );
        }
        // Pour les sessions live (Kahoot), le score est la somme directe de score_earned
        // (les questions live ont is_scored=0, recompute_qz_participant_score retournerait 0)
        $tbl_p = $this->get_qz_table( 'participants' );
        if ( self::ACDC_OF_QZ_PURPOSE_LIVE === (string) $session->quiz_purpose ) {
            $total_live = (float) $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(score_earned),0) FROM {$tbl_pa} WHERE participant_id=%d AND session_id=%d",
                (int) $p->id, (int) $p->session_id
            ) );
            $wpdb->update(
                $tbl_p,
                array( 'total_score' => $total_live, 'updated_at' => $now ),
                array( 'id' => (int) $p->id ),
                array( '%f', '%s' ),
                array( '%d' )
            );
        } else {
            /* ACDC 3.25.168 — Pour une évaluation, on tient le pourcentage à jour à
               chaque réponse : le formateur consulte souvent l'écran de résultats sans
               avoir cliqué « Terminer », et il y lisait un score vide. */
            $this->recompute_qz_participant_score( (int) $p->id );
        }
        wp_send_json_success( array(
            'recorded'    => true,
            'is_correct'  => $is_correct,
            'score_ratio' => $score_ratio,
            'partial'     => ! empty( $verdict['partial'] ),
            'score'       => $score,
        ) );
    }

    /**
     * 3.21.04.3 — Enregistre un changement d'onglet pour un participant.
     * Crée la colonne tab_switch_count si elle n'existe pas encore.
     */
    public function ajax_acdc_of_qz_player_tab_switch() {
        global $wpdb;
        $participant_id = isset( $_REQUEST['participant_id'] ) ? (int) $_REQUEST['participant_id'] : 0;
        $token          = isset( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['token'] ) ) : '';
        if ( ! $participant_id || ! $token ) { wp_send_json_success(); }
        $tbl_p = $this->get_qz_table( 'participants' );
        $p = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$tbl_p} WHERE id=%d AND secure_token=%s LIMIT 1",
            $participant_id, $token
        ) );
        if ( ! $p ) { wp_send_json_success(); }
        // Créer la colonne si absente
        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$tbl_p} LIKE 'tab_switch_count'" );
        if ( empty( $cols ) ) {
            $wpdb->query( "ALTER TABLE {$tbl_p} ADD COLUMN tab_switch_count SMALLINT UNSIGNED NOT NULL DEFAULT 0" );
        }
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$tbl_p} SET tab_switch_count = tab_switch_count + 1 WHERE id=%d",
            $participant_id
        ) );
        wp_send_json_success();
    }

    /**
     * Cron horaire — Envoie automatiquement le test de positionnement
     * aux apprenants dont la séance commence dans les 48 prochaines heures.
     *
     * Conditions d'envoi :
     *  - La session OF doit avoir une date de début dans [NOW, NOW + 48h].
     *  - La formation associée doit avoir un quiz de positionnement actif (is_current = 1, status = 'active').
     *  - L'apprenant ne doit pas déjà avoir un participant actif pour ce quiz
     *    (protection anti-doublon : le cron tourne toutes les heures).
     *
     * @since 3.21.05
     */
    public function process_qz_scheduled_dispatches() {
        global $wpdb;

        if ( empty( $this->qz_tables ) ) {
            return;
        }

        $tbl_of_sessions = $wpdb->prefix . 'acdc_of_sessions';
        $tbl_qz_sessions = $this->get_qz_table( 'sessions' );
        $tbl_qz_quizzes  = $this->get_qz_table( 'quizzes' );
        $tbl_qz_parts    = $this->get_qz_table( 'participants' );

        // Vérifier l'existence des tables (sécurité défensive)
        foreach ( array( $tbl_of_sessions, $tbl_qz_sessions, $tbl_qz_quizzes, $tbl_qz_parts ) as $tbl ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) !== $tbl ) {
                return;
            }
        }

        // 1. Séances qui débutent dans les 48 prochaines heures
        // ACDC 3.25.113 — bornes calculées en heure murale WP (current_time('mysql')) au lieu de
        // NOW()/DATE_ADD(NOW()) : start_at est stocké en heure locale WP, or NOW() renvoie l'heure
        // du serveur MySQL (souvent UTC, non synchronisée) → fenêtre décalée de l'offset GMT.
        $now_wp   = current_time( 'mysql' );
        $in_48h   = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + 48 * HOUR_IN_SECONDS );
        $upcoming = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, formation_id
             FROM {$tbl_of_sessions}
             WHERE COALESCE(start_at, CONCAT(start_date, ' 09:00:00')) >= %s
               AND COALESCE(start_at, CONCAT(start_date, ' 09:00:00')) <= %s
               AND COALESCE(is_draft, 0) = 0
               AND COALESCE(status, '') NOT IN ('Brouillon', 'Annulée', 'Terminée')",
                $now_wp,
                $in_48h
            )
        );

        if ( empty( $upcoming ) ) {
            return;
        }

        foreach ( $upcoming as $session ) {
            $session_id   = (int) $session->id;
            $formation_id = (int) $session->formation_id;

            if ( $formation_id <= 0 ) {
                continue;
            }

            // 2. Quiz de positionnement actif pour cette formation
            $quiz = $wpdb->get_row( $wpdb->prepare(
                "SELECT id FROM {$tbl_qz_quizzes}
                 WHERE formation_id = %d
                   AND quiz_purpose = %s
                   AND is_current   = 1
                   AND status       = %s
                 LIMIT 1",
                $formation_id,
                self::ACDC_OF_QZ_PURPOSE_POSITIONING,
                self::ACDC_OF_QZ_STATUS_ACTIVE
            ) );

            if ( ! $quiz ) {
                continue; // Pas de quiz de positionnement actif pour cette formation
            }

            $quiz_id = (int) $quiz->id;

            // 3. Apprenants inscrits à cette séance
            $learners = $this->get_qz_learners_for_formation( $formation_id, array( 'session_id' => $session_id ) );

            if ( empty( $learners ) ) {
                continue;
            }

            // 4. Filtrer les apprenants déjà envoyés (anti-doublon cron horaire)
            $recipients = array();
            foreach ( $learners as $l ) {
                $learner_id = (int) $l->id;
                if ( $learner_id <= 0 || empty( $l->email ) ) {
                    continue;
                }

                $already = $wpdb->get_var( $wpdb->prepare(
                    "SELECT p.id
                     FROM {$tbl_qz_parts} p
                     INNER JOIN {$tbl_qz_sessions} qs ON qs.id = p.session_id
                     WHERE qs.quiz_id     = %d
                       AND p.learner_id   = %d
                       AND p.status NOT IN ('expired', 'cancelled')
                     LIMIT 1",
                    $quiz_id,
                    $learner_id
                ) );

                if ( $already ) {
                    continue; // Déjà dispatché (ou en cours) — ne pas renvoyer
                }

                $recipients[] = array(
                    'email'      => $l->email,
                    'first_name' => $l->first_name,
                    'last_name'  => $l->last_name,
                    'learner_id' => $learner_id,
                );
            }

            if ( empty( $recipients ) ) {
                continue;
            }

            // 5. Envoi
            $result = $this->create_qz_async_dispatch( $quiz_id, $recipients, array(
                'expires_in_days'      => 7,
                'reminders_enabled'    => true,
                'formation_session_id' => $session_id,
            ) );

            if ( is_wp_error( $result ) ) {
                $this->log_qz_event( array(
                    'quiz_id'     => $quiz_id,
                    'event_type'  => 'auto_dispatch_error',
                    'event_label' => sprintf(
                        'Erreur envoi auto 48h : séance OF #%d — %s',
                        $session_id,
                        $result->get_error_message()
                    ),
                ) );
            } else {
                $this->log_qz_event( array(
                    'quiz_id'       => $quiz_id,
                    'event_type'    => 'auto_dispatch_sent',
                    'event_label'   => sprintf(
                        'Envoi automatique 48h avant séance OF #%d : %d destinataire(s)',
                        $session_id,
                        count( $recipients )
                    ),
                    'event_payload' => array(
                        'formation_session_id' => $session_id,
                        'recipients_count'     => count( $recipients ),
                    ),
                ) );
            }
        }
    }
    public function process_qz_scheduled_reminders()       { $this->process_qz_async_reminders(); }
    public function process_qz_expired_tokens()            { $this->process_qz_async_expirations(); }
    /**
     * ACDC 3.21.05 — Cron horaire : ferme les sessions live quiz bloquées depuis plus de 24h.
     *
     * Une session live peut rester bloquée en statut 'lobby' ou 'in_progress' si
     * le formateur ferme son navigateur sans clore la session. Ce cron les marque
     * 'ended' proprement pour libérer le PIN et ne pas polluer les compteurs actifs.
     */
    public function process_qz_close_inactive_sessions() {
        global $wpdb;

        if ( empty( $this->qz_tables ) ) {
            return;
        }

        $tbl_s = $this->get_qz_table( 'sessions' );
        if ( ! $tbl_s ) {
            return;
        }

        $stale = $wpdb->get_results(
            "SELECT id FROM {$tbl_s}
             WHERE delivery_mode = 'live'
               AND status IN ('lobby', 'in_progress')
               AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        if ( empty( $stale ) ) {
            return;
        }

        $now = current_time( 'mysql' );
        foreach ( $stale as $row ) {
            $wpdb->update(
                $tbl_s,
                array( 'status' => 'ended', 'ended_at' => $now, 'updated_at' => $now ),
                array( 'id' => (int) $row->id ),
                array( '%s', '%s', '%s' ),
                array( '%d' )
            );
            $this->log_qz_event( array(
                'session_id'  => (int) $row->id,
                'event_type'  => 'session_auto_closed',
                'event_label' => sprintf( 'Session live #%d fermée automatiquement (inactive > 24h)', (int) $row->id ),
            ) );
        }
    }
    /**
     * ACDC 3.24.30 — Cron RGPD quotidien : anonymise les participants quiz
     * dont la date de complétion dépasse la rétention définie sur le quiz
     * (champ data_retention_days, défaut 1095 jours / 3 ans).
     * Seules les lignes is_anonymized = 0 et status = 'completed' sont traitées.
     * Les player_answers correspondants sont supprimés (données de réponse).
     */
    public function process_qz_rgpd_purge() {
        global $wpdb;
        $tbl_q  = $this->get_qz_table( 'quizzes' );
        $tbl_s  = $this->get_qz_table( 'sessions' );
        $tbl_p  = $this->get_qz_table( 'participants' );
        $tbl_pa = $this->get_qz_table( 'player_answers' );
        if ( ! $tbl_q || ! $tbl_p ) {
            return;
        }

        // Récupère les participants éligibles à l'anonymisation
        $rows = $wpdb->get_results(
            "SELECT p.id AS participant_id, p.learner_id
             FROM {$tbl_p} p
             INNER JOIN {$tbl_s} qs ON qs.id = p.session_id
             INNER JOIN {$tbl_q} q  ON q.id  = qs.quiz_id
             WHERE p.is_anonymized = 0
               AND p.status        = 'completed'
               AND p.completed_at IS NOT NULL
               AND DATE_ADD( p.completed_at, INTERVAL q.data_retention_days DAY ) < NOW()"
        );

        if ( empty( $rows ) ) {
            return;
        }

        $now   = current_time( 'mysql' );
        $count = 0;
        foreach ( $rows as $row ) {
            $token = 'ANONYME-' . strtoupper( substr( md5( 'qz_' . (int) $row->participant_id ), 0, 8 ) );
            $wpdb->update(
                $tbl_p,
                array(
                    'full_name'      => $token,
                    'email'          => $token . '@anonyme.local',
                    'nickname'       => $token,
                    'is_anonymized'  => 1,
                    'anonymized_at'  => $now,
                    'updated_at'     => $now,
                ),
                array( 'id' => (int) $row->participant_id ),
                null,
                array( '%d' )
            );
            // Suppression des réponses individuelles (agrégats de score conservés sur participants)
            if ( $tbl_pa ) {
                $wpdb->delete( $tbl_pa, array( 'participant_id' => (int) $row->participant_id ), array( '%d' ) );
            }
            $count++;
        }

        if ( $count > 0 ) {
            $this->log_qz_event( array(
                'event_type'  => 'rgpd_purge',
                'event_label' => sprintf( 'RGPD : %d participant(s) anonymisé(s) automatiquement (rétention dépassée)', $count ),
            ) );
        }
    }

    /* ==================================================================== */
    /*  3.21.01.1 — Enqueue assets sur les pages extranet du moteur         */
    /* ==================================================================== */

    /**
     * Détecte si on est sur une page extranet du nouveau moteur.
     * On regarde le query var `tab` pour identifier les 3 nouveaux slugs.
     *
     * @return bool
     */
    private function is_qz_extranet_screen() {
        if ( is_admin() ) {
            return false;
        }
        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
        return in_array( $tab, array(
            self::ACDC_OF_QZ_TAB_LIVE,
            self::ACDC_OF_QZ_TAB_POSITIONING,
            self::ACDC_OF_QZ_TAB_ASSESSMENT,
            self::ACDC_OF_QZ_TAB_DIAGNOSTIC,
            self::ACDC_OF_QZ_TAB_RESULTS_DIAGNOSTIC,
            /* ACDC 3.21.04.1-hotfix2 — Onglets Résultats (transverse + 3 par finalité) */
            self::ACDC_OF_QZ_TAB_RESULTS,
            self::ACDC_OF_QZ_TAB_RESULTS_LIVE,
            self::ACDC_OF_QZ_TAB_RESULTS_POSITIONING,
            self::ACDC_OF_QZ_TAB_RESULTS_ASSESSMENT,
            /* 3.21.33 — Vue consolidée */
            'qz_results_all',
        ), true );
    }

    /**
     * Charge CSS et JS de l'éditeur sur les pages extranet du moteur.
     *
     * @return void
     */
    public function enqueue_qz_extranet_assets() {
        if ( ! $this->is_qz_extranet_screen() ) {
            return;
        }

        $ver = ACDC_OF_SAAS_VERSION;

        wp_enqueue_style(
            'acdc-quizzes-admin',
            ACDC_OF_SAAS_URL . 'assets/css/quizzes-admin.css',
            array(),
            $ver
        );

        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_media();

        wp_enqueue_script(
            'acdc-quizzes-editor',
            ACDC_OF_SAAS_URL . 'assets/js/quizzes-editor.js',
            array( 'jquery', 'jquery-ui-sortable' ),
            $ver,
            true
        );

        wp_localize_script(
            'acdc-quizzes-editor',
            'acdcQzEditor',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'acdc_of_qz_editor' ),
                'nonceImport' => wp_create_nonce( 'acdc_of_qz_import' ),
                'i18n'    => array(
                    'saving'              => __( 'Enregistrement…', 'acdc-formation-saas' ),
                    'saved'               => __( 'Enregistré', 'acdc-formation-saas' ),
                    'error'               => __( 'Erreur', 'acdc-formation-saas' ),
                    'confirmDel'          => __( 'Supprimer cette question ?', 'acdc-formation-saas' ),
                    'confirmDelObjective' => __( 'Supprimer cet objectif ?', 'acdc-formation-saas' ),
                    'newAnswer'           => __( 'Nouvelle réponse', 'acdc-formation-saas' ),
                    'addImage'            => __( 'Ajouter une image', 'acdc-formation-saas' ),
                    'replaceImage'        => __( "Remplacer l'image", 'acdc-formation-saas' ),
                ),
            )
        );
    }

    /* ====================================================================
     * 3.21.04.2-a — Enqueue assets sur les pages live (host + player)
     * ==================================================================== */

    /**
     * Détecte si on est sur la page host live (/acdc-quiz-live-host/).
     */
    private function is_qz_live_host_page() {
        if ( ! is_singular( 'page' ) ) { return false; }
        $page_id = (int) get_queried_object_id();
        if ( $page_id <= 0 ) { return false; }
        $host_page_id = (int) get_option( 'acdc_of_qz_live_host_page_id', 0 );
        if ( $host_page_id && $page_id === $host_page_id ) { return true; }
        // Fallback par slug si l'option est absente ou désynchronisée
        $post = get_post( $page_id );
        return $post && 'acdc-quiz-live-host' === $post->post_name;
    }

    /**
     * Détecte si on est sur la page player live (/acdc-quiz-live/).
     */
    private function is_qz_live_player_page() {
        if ( ! is_singular( 'page' ) ) { return false; }
        $page_id = (int) get_queried_object_id();
        if ( $page_id <= 0 ) { return false; }
        $player_page_id = (int) get_option( 'acdc_of_qz_live_public_page_id', 0 );
        if ( $player_page_id && $page_id === $player_page_id ) { return true; }
        // Fallback par slug si l'option est absente ou désynchronisée
        $post = get_post( $page_id );
        return $post && 'acdc-quiz-live' === $post->post_name;
    }

    /**
     * Construit le mapping des URLs des illustrations podium pour le JS.
     */
    private function get_qz_podium_assets_map() {
        /* ACDC 3.25.266 — LE PODIUM NE DÉPEND PLUS DE LA MÉDIATHÈQUE.
         *
         * Les sept illustrations étaient appelées par des URL écrites en dur sur
         * acdcformation.com/wp-content/uploads/2026/04/. Le jour où l'un de ces
         * fichiers a été déplacé, renommé ou purgé, le podium a disparu : le
         * navigateur affichait « Podium », le texte alternatif de l'image, et
         * les noms des trois premiers — positionnés en absolu par-dessus le
         * décor — se sont dispersés sur la page.
         *
         * Or le plugin EMBARQUE ces sept images depuis toujours, dans
         * assets/images/podium/. Il allait les chercher ailleurs alors qu'il les
         * avait sous la main. Un décor de fin de quiz ne doit dépendre d'aucun
         * fichier qu'une manipulation de médiathèque peut faire disparaître.
         */
        $base_url = trailingslashit( ACDC_OF_SAAS_URL ) . 'assets/images/podium/';
        return array(
            'base'    => $base_url . 'podium-base.png',
            '1-femme' => $base_url . '1ere-femme.png',
            '2-femme' => $base_url . '2eme-femme.png',
            '3-femme' => $base_url . '3eme-femme.png',
            '1-homme' => $base_url . '1ere-homme.png',
            '2-homme' => $base_url . '2eme-homme.png',
            '3-homme' => $base_url . '3eme-homme.png',
        );
    }

    /**
     * Enqueue assets pour les pages live (host + player).
     * Hook : wp_enqueue_scripts (priorité standard).
     */
    public function enqueue_qz_live_assets() {
        $is_host   = $this->is_qz_live_host_page();
        $is_player = $this->is_qz_live_player_page();
        if ( ! $is_host && ! $is_player ) {
            return;
        }

        $ver = ACDC_OF_SAAS_VERSION;

        // CSS commun
        wp_enqueue_style(
            'acdc-ui-system',
            ACDC_OF_SAAS_URL . 'assets/css/acdc-ui-system.css',
            array(),
            $ver
        );
        wp_enqueue_style(
            'acdc-quizzes-live',
            ACDC_OF_SAAS_URL . 'assets/css/quizzes-live.css',
            array( 'acdc-ui-system' ),
            $ver
        );

        // canvas-confetti (commun aux deux écrans pour le podium)
        wp_enqueue_script(
            'acdc-canvas-confetti',
            ACDC_OF_SAAS_URL . 'assets/js/vendor/canvas-confetti.min.js',
            array(),
            '1.9.3',
            true
        );

        // Mapping podium (commun)
        wp_register_script( 'acdc-qz-live-bootstrap', '', array(), $ver, true );
        wp_enqueue_script( 'acdc-qz-live-bootstrap' );
        wp_add_inline_script(
            'acdc-qz-live-bootstrap',
            'window.acdcQzPodiumAssets = ' . wp_json_encode( $this->get_qz_podium_assets_map() ) . ';',
            'before'
        );

        if ( $is_host ) {
            // QR code generator (uniquement host)
            wp_enqueue_script(
                'acdc-qrcode-generator',
                ACDC_OF_SAAS_URL . 'assets/js/vendor/qrcode-generator.min.js',
                array(),
                '1.4.4',
                true
            );
            wp_enqueue_script(
                'acdc-quizzes-live-host',
                ACDC_OF_SAAS_URL . 'assets/js/quizzes-live-host.js',
                array( 'acdc-canvas-confetti', 'acdc-qrcode-generator', 'acdc-qz-live-bootstrap' ),
                $ver,
                true
            );
        }
        if ( $is_player ) {
            wp_enqueue_script(
                'acdc-quizzes-live-player',
                ACDC_OF_SAAS_URL . 'assets/js/quizzes-live-player.js',
                array( 'acdc-canvas-confetti', 'acdc-qz-live-bootstrap' ),
                $ver,
                true
            );
        }
    }
    /* ====================================================================
     * ACDC 3.21.04.1 — Handlers résultats
     * ==================================================================== */

    /**
     * Correction manuelle d'une réponse libre (textarea).
     * Mis à jour : is_correct + score_earned, puis recalcul du total du participant.
     */
    public function handle_acdc_of_qz_grade_open_answer() {
        $this->qz_check_admin_request( 'acdc_of_qz_grade_open_answer' );

        $participant_id = isset( $_POST['participant_id'] ) ? absint( wp_unslash( $_POST['participant_id'] ) ) : 0;
        $question_id    = isset( $_POST['question_id'] ) ? absint( wp_unslash( $_POST['question_id'] ) ) : 0;
        $is_correct     = isset( $_POST['is_correct'] ) ? (int) $_POST['is_correct'] : 0;
        $score_earned   = isset( $_POST['score_earned'] ) ? (float) $_POST['score_earned'] : 0;
        $grader_comment = isset( $_POST['grader_comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['grader_comment'] ) ) : '';

        if ( $participant_id <= 0 || $question_id <= 0 ) {
            wp_die( esc_html__( 'Identifiants manquants.', 'acdc-formation-saas' ) );
        }

        // Identifiant lisible du correcteur pour la trace Qualiopi
        $grader_label = '';
        if ( current_user_can( 'manage_options' ) ) {
            $u = wp_get_current_user();
            $grader_label = $u && $u->user_login ? 'admin:' . $u->user_login : 'admin';
        } elseif ( method_exists( $this, 'trainer_portal_get_current_account' ) ) {
            $account = $this->trainer_portal_get_current_account();
            if ( $account && ! empty( $account->trainer_id ) ) {
                $grader_label = 'trainer:' . (int) $account->trainer_id;
            }
        }

        $result = $this->update_qz_manual_grading( $participant_id, $question_id, $is_correct, $score_earned, $grader_label, $grader_comment );

        $participant = $this->get_qz_participant( $participant_id );
        $session     = $participant ? $this->get_qz_dispatch_session( (int) $participant->session_id ) : null;
        $purpose     = $session ? (string) $session->quiz_purpose : self::ACDC_OF_QZ_PURPOSE_ASSESSMENT;

        if ( is_wp_error( $result ) ) {
            $this->qz_redirect( $purpose,
                array( 'view' => 'results', 'participant' => $participant_id ),
                $result->get_error_message(),
                'error'
            );
        }

        // Tenter la génération PDF — résoudre registration_id si absent
        if ( $participant && (int) $participant->learner_id > 0 ) {
            $this->qz_resolve_registration_id_and_generate_pdf( $participant, (int) $participant->session_id );
        }

        $this->qz_redirect( $purpose,
            array( 'view' => 'results', 'participant' => $participant_id ),
            __( 'Correction enregistrée et score recalculé.', 'acdc-formation-saas' ),
            'success'
        );
    }

    /**
     * Export CSV des résultats d'une session d'envoi.
     * Renvoie un fichier CSV en téléchargement direct (pas de redirection).
     */
    public function handle_acdc_of_qz_export_results() {
        // Le nonce est passé en GET pour cet endpoint (téléchargement direct depuis un lien)
        if ( ! $this->qz_request_is_authorized( 'acdc_of_qz_export_results' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'acdc-formation-saas' ) );
        }
        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'acdc_of_qz_export_results' ) ) {
            wp_die( esc_html__( 'Lien expiré ou invalide. Recharge la page et réessaye.', 'acdc-formation-saas' ) );
        }

        $session_id = isset( $_GET['session_id'] ) ? absint( wp_unslash( $_GET['session_id'] ) ) : 0;
        if ( $session_id <= 0 ) {
            wp_die( esc_html__( 'Session invalide.', 'acdc-formation-saas' ) );
        }

        // Si formateur, vérifier qu'il a le droit d'accéder à cette session
        if ( ! current_user_can( 'manage_options' ) && method_exists( $this, 'trainer_portal_get_current_account' ) ) {
            $account = $this->trainer_portal_get_current_account();
            if ( $account && ! empty( $account->trainer_id ) ) {
                if ( ! $this->can_qz_trainer_view_dispatch_session( $session_id, (int) $account->trainer_id ) ) {
                    wp_die( esc_html__( "Vous n'avez pas accès à cette session.", 'acdc-formation-saas' ) );
                }
            }
        }

        $session = $this->get_qz_dispatch_session( $session_id );
        if ( ! $session ) {
            wp_die( esc_html__( 'Session introuvable.', 'acdc-formation-saas' ) );
        }

        $xlsx = $this->export_qz_session_results_excel( $session_id );
        if ( '' === $xlsx ) {
            wp_die( esc_html__( 'Aucune donnée à exporter.', 'acdc-formation-saas' ) );
        }

        // Nom de fichier
        $safe_quiz = sanitize_title( (string) $session->quiz_title );
        $date_part = ! empty( $session->sent_at ) ? date( 'Y-m-d', strtotime( $session->sent_at ) ) : date( 'Y-m-d' );
        $filename  = sprintf( 'resultats-%s-%s.xlsx', $safe_quiz, $date_part );

        nocache_headers();
        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $xlsx ) );
        echo $xlsx;
        exit;
    }

    // ── ACDC 3.25.78 — Handlers wp_ajax front pour les actions quiz (WAF-safe) ──

    public function ajax_front_qz_delete_quiz() {
        $this->qz_check_ajax_request( 'acdc_of_qz_delete_quiz', '_acdc_qz_nonce' );
        $quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
        $force   = isset( $_POST['force'] ) && '1' === (string) wp_unslash( $_POST['force'] );
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) { wp_send_json_error( array( 'message' => __( 'Quiz introuvable.', 'acdc-formation-saas' ) ) ); }
        if ( ! $force && $this->is_qz_quiz_locked( $quiz_id ) ) {
            wp_send_json_error( array(
                'message' => __( "Ce test est verrouillé pour la traçabilité Qualiopi : des réponses d'apprenants y sont rattachées.", 'acdc-formation-saas' ),
                'locked'  => true,
            ) );
        }
        $ok = $this->delete_qz_quiz( $quiz_id, $force );
        $this->log_qz_event( array( 'quiz_id' => $quiz_id, 'event_type' => 'quiz_deleted', 'event_label' => ( $force ? 'Quiz supprimé (forcé) : ' : 'Quiz supprimé : ' ) . $quiz->title ) );
        $redirect = $this->qz_admin_url( (string) $quiz->quiz_purpose );
        $ok ? wp_send_json_success( array( 'redirect' => $redirect, 'message' => __( 'Quiz supprimé.', 'acdc-formation-saas' ) ) )
            : wp_send_json_error( array( 'message' => __( 'Erreur lors de la suppression.', 'acdc-formation-saas' ) ) );
    }

    public function ajax_front_qz_archive_quiz() {
        $this->qz_check_ajax_request( 'acdc_of_qz_archive_quiz', '_acdc_qz_nonce' );
        $quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) { wp_send_json_error( array( 'message' => __( 'Quiz introuvable.', 'acdc-formation-saas' ) ) ); }
        global $wpdb;
        // ACDC 3.25.113 — archivage sur la table du MODULE quiz (acdc_of_qz_quizzes, qui possède
        // status/archived_at), pas la table legacy $this->quiz_table (acdc_of_quizzes, sans status).
        $wpdb->update( $this->get_qz_table( 'quizzes' ), array( 'status' => 'archived', 'archived_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $quiz_id ) );
        $redirect = $this->qz_admin_url( (string) $quiz->quiz_purpose );
        wp_send_json_success( array( 'redirect' => $redirect, 'message' => __( 'Quiz archivé.', 'acdc-formation-saas' ) ) );
    }

    public function ajax_front_qz_duplicate_quiz() {
        $this->qz_check_ajax_request( 'acdc_of_qz_duplicate_quiz_direct', '_acdc_qz_nonce' );
        $quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) { wp_send_json_error( array( 'message' => __( 'Quiz introuvable.', 'acdc-formation-saas' ) ) ); }
        $new_id = $this->duplicate_qz_quiz( $quiz_id );
        $redirect = $new_id ? $this->qz_admin_url( (string) $quiz->quiz_purpose, array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => $new_id ) ) : '';
        $new_id ? wp_send_json_success( array( 'redirect' => $redirect, 'message' => __( 'Quiz dupliqué.', 'acdc-formation-saas' ) ) )
                : wp_send_json_error( array( 'message' => __( 'Erreur lors de la duplication.', 'acdc-formation-saas' ) ) );
    }

    public function ajax_front_qz_create_new_version() {
        $this->qz_check_ajax_request( 'acdc_of_qz_create_new_version', '_acdc_qz_nonce' );
        $quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
        $quiz = $this->get_qz_quiz( $quiz_id );
        if ( ! $quiz ) { wp_send_json_error( array( 'message' => __( 'Quiz introuvable.', 'acdc-formation-saas' ) ) ); }
        $new_id = method_exists( $this, 'create_qz_quiz_new_version' ) ? $this->create_qz_quiz_new_version( $quiz_id ) : 0;
        $redirect = $new_id ? $this->qz_admin_url( (string) $quiz->quiz_purpose, array( 'view' => self::ACDC_OF_QZ_VIEW_EDIT, 'quiz_id' => $new_id ) ) : '';
        $new_id ? wp_send_json_success( array( 'redirect' => $redirect, 'message' => __( 'Nouvelle version créée.', 'acdc-formation-saas' ) ) )
                : wp_send_json_error( array( 'message' => __( 'Erreur lors de la création de version.', 'acdc-formation-saas' ) ) );
    }
}
