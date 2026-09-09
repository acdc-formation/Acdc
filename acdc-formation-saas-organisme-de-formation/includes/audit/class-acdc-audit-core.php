<?php
/**
 * ACDC Audit Qualiopi — Core
 *
 * Agrège tous les documents justificatifs du plugin par formation/séance/apprenant.
 * Gère les tokens d'accès temporaires pour les auditeurs.
 *
 * @package ACDC_Formation_SAAS
 * @since   3.21.09-hotfix25
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ACDC_Audit_Core {

    /** @var string */
    public $token_table;

    public function __construct() {
        global $wpdb;
        $this->token_table = $wpdb->prefix . 'acdc_of_audit_tokens';
    }

    /* -----------------------------------------------------------------------
     * Installation BDD
     * -------------------------------------------------------------------- */
    public function install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->token_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            token VARCHAR(64) NOT NULL,
            label VARCHAR(255) DEFAULT '',
            expires_at DATETIME NOT NULL,
            created_by BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            last_accessed_at DATETIME DEFAULT NULL,
            access_count INT UNSIGNED NOT NULL DEFAULT 0,
            is_revoked TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY expires_at (expires_at)
        ) {$charset_collate};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /* -----------------------------------------------------------------------
     * Catalogue des types de documents (extensible)
     * -------------------------------------------------------------------- */
    public function get_document_types() {
        return array(
            'convention'        => array( 'label' => 'Convention de formation',       'icon' => '' ),
            'convention_signee' => array( 'label' => 'Convention signée',             'icon' => '' ),
            'emargement'        => array( 'label' => 'Feuille d\'émargement',        'icon' => '' ),
            'convocation'       => array( 'label' => 'Convocation',                  'icon' => '' ),
            'positionnement'    => array( 'label' => 'Test de positionnement',        'icon' => '' ),
            /* ACDC 3.25.280 — L'évaluation diagnostique manquait au catalogue de
               l'audit : son résultat n'aurait porté aucun libellé devant
               l'auditeur, ce qui revient à ne pas la produire. */
            'diagnostic'        => array( 'label' => 'Évaluation diagnostique',       'icon' => '' ),
            'evaluation'        => array( 'label' => 'Évaluation des acquis',        'icon' => '' ),
            'satisfaction_mi'   => array( 'label' => 'Questionnaire satisfaction (mi-parcours)', 'icon' => '' ),
            'satisfaction_fin'  => array( 'label' => 'Questionnaire satisfaction (fin)',         'icon' => '' ),
            'satisfaction_froid'=> array( 'label' => 'Questionnaire satisfaction (à froid)',     'icon' => '' ),
            /* ACDC 3.25.330 — « Certificat » tout court n'est pas un nom légal, et
               c'est devant un auditeur que ce document est produit. Les deux
               pièces portent maintenant le nom que la loi leur donne. */
            'certificat'        => array( 'label' => 'Certificat de réalisation',     'icon' => '' ),
            'attestation'       => array( 'label' => 'Attestation de fin de formation', 'icon' => '' ),
            'programme'         => array( 'label' => 'Programme de formation',        'icon' => '' ),
            /* ACDC 3.21.10 — Qualiopi C1/I1 — Analyses du besoin. */
            'analyse_besoin_commanditaire' => array( 'label' => 'Analyse du besoin — Commanditaire', 'icon' => '' ),
            'analyse_besoin_apprenant'     => array( 'label' => 'Analyse du besoin — Apprenant',     'icon' => '' ),
        );
    }

    /* -----------------------------------------------------------------------
     * Agrégation principale — renvoie les docs groupés par formation
     * -------------------------------------------------------------------- */
    public function get_documents( $filters = array() ) {
        global $wpdb;

        $ft  = $wpdb->prefix . 'acdc_of_formations';
        $st  = $wpdb->prefix . 'acdc_of_sessions';
        $lt  = $wpdb->prefix . 'acdc_of_learners';
        $ct  = $wpdb->prefix . 'acdc_of_companies';
        $rct = $wpdb->prefix . 'acdc_of_registration_contracts';
        $trt = $wpdb->prefix . 'acdc_of_training_registrations';
        $est = $wpdb->prefix . 'acdc_of_emarg_sessions';
        $elt = $wpdb->prefix . 'acdc_of_emarg_learners';

        $date_from  = ! empty( $filters['date_from'] )    ? sanitize_text_field( $filters['date_from'] )    : '';
        $date_to    = ! empty( $filters['date_to'] )      ? sanitize_text_field( $filters['date_to'] )      : '';
        $formation_id = ! empty( $filters['formation_id'] ) ? absint( $filters['formation_id'] )            : 0;
        $learner_id   = ! empty( $filters['learner_id'] )   ? absint( $filters['learner_id'] )              : 0;
        $company_id   = ! empty( $filters['company_id'] )   ? absint( $filters['company_id'] )              : 0;
        $doc_type     = ! empty( $filters['doc_type'] )     ? sanitize_key( $filters['doc_type'] )          : '';

        $result = array(); // [ formation_id => [ 'formation' => obj, 'sessions' => [], 'learners' => [] ] ]

        /* ---- 1. Conventions ---- */
        $contracts = $wpdb->get_results(
            "SELECT rc.id, rc.title, rc.commanditaire_type, rc.company_id,
                    rc.formation_id, rc.formation_title, rc.start_date, rc.end_date,
                    rc.document_url, rc.signed_document_url, rc.created_at,
                    rc.learner_ids, c.name AS company_name
             FROM {$rct} rc
             LEFT JOIN {$ct} c ON c.id = rc.company_id
             WHERE (rc.document_url IS NOT NULL AND rc.document_url != '')
                OR (rc.signed_document_url IS NOT NULL AND rc.signed_document_url != '')"
        );
        foreach ( $contracts as $c ) {
            if ( $formation_id && (int) $c->formation_id !== $formation_id ) continue;
            if ( $date_from && $c->start_date && $c->start_date < $date_from ) continue;
            if ( $date_to   && $c->start_date && $c->start_date > $date_to )   continue;
            $fid = (int) $c->formation_id;
            $this->ensure_formation( $result, $fid, $c->formation_title, null );
            if ( ! empty( $c->document_url ) && ( ! $doc_type || 'convention' === $doc_type ) ) {
                $this->add_doc( $result, $fid, 'formation', 0, 'convention', array(
                    'label'        => $c->title ?: 'Convention #' . $c->id,
                    'url'          => $c->document_url,
                    'meta'         => trim( implode( ' | ', array_filter( array( $c->company_name, $c->formation_title, $this->format_date_range( $c->start_date, $c->end_date ) ) ) ) ),
                    'created_at'   => $c->created_at,
                    'company_name' => $c->company_name,
                    'source_id'    => $c->id,
                    'source_type'  => 'contract',
                ) );
            }
            if ( ! empty( $c->signed_document_url ) && ( ! $doc_type || 'convention_signee' === $doc_type ) ) {
                $this->add_doc( $result, $fid, 'formation', 0, 'convention_signee', array(
                    'label'        => ( $c->title ?: 'Convention' ) . ' — signée',
                    'url'          => $c->signed_document_url,
                    'meta'         => trim( implode( ' | ', array_filter( array( $c->company_name, $c->formation_title ) ) ) ),
                    'created_at'   => $c->created_at,
                    'company_id'   => (int) $c->company_id,
                    'company_name' => $c->company_name,
                    'source_id'    => $c->id,
                    'source_type'  => 'contract',
                ) );
            }
        }

        /* ---- 2. Séances + émargements ---- */
        $sessions_sql = "SELECT s.id AS session_id, s.title AS session_title,
                                s.start_at, s.end_at, s.location, s.session_format,
                                s.formation_id, f.title AS formation_title,
                                es.id AS emarg_id, es.trainer_name, es.trainer_status,
                                es.trainer_sig_url, es.trainer_signed_at, es.list_token
                         FROM {$st} s
                         LEFT JOIN {$ft} f ON f.id = s.formation_id
                         LEFT JOIN {$est} es ON es.session_id = s.id AND es.seance_index = 0
                         WHERE COALESCE(s.is_draft,0) = 0
                           AND COALESCE(s.status,'') NOT IN ('Brouillon','Annulée')";
        if ( $formation_id ) $sessions_sql .= $wpdb->prepare( " AND s.formation_id = %d", $formation_id );
        if ( $date_from )    $sessions_sql .= $wpdb->prepare( " AND DATE(COALESCE(s.start_at, s.start_date)) >= %s", $date_from );
        if ( $date_to )      $sessions_sql .= $wpdb->prepare( " AND DATE(COALESCE(s.start_at, s.start_date)) <= %s", $date_to );
        $sessions_sql .= " GROUP BY s.id ORDER BY s.start_at DESC";

        $sessions = $wpdb->get_results( $sessions_sql );

        foreach ( $sessions as $sess ) {
            $fid = (int) $sess->formation_id;
            $sid = (int) $sess->session_id;
            $date_label = $sess->start_at ? wp_date( 'd/m/Y', strtotime( $sess->start_at ) ) : '—';
            $this->ensure_formation( $result, $fid, $sess->formation_title, null );
            $this->ensure_session( $result, $fid, $sid, $sess->session_title, $date_label, $sess->start_at );

            /* Feuille d'émargement PDF (générée à la volée via lien) */
            if ( $sess->emarg_id && 'signe' === $sess->trainer_status ) {
                if ( ! $doc_type || 'emargement' === $doc_type ) {
                    $this->add_doc( $result, $fid, 'session', $sid, 'emargement', array(
                        'label'      => 'Feuille d\'émargement — ' . $date_label,
                        'url'          => wp_nonce_url( admin_url( 'admin-post.php?action=acdc_emarg_download_pdf&session_id=' . $sid . '&preview=1' ), 'acdc_emarg_pdf_' . $sid ),
                        'url_download' => wp_nonce_url( admin_url( 'admin-post.php?action=acdc_emarg_download_pdf&session_id=' . $sid ), 'acdc_emarg_pdf_' . $sid ),
                        'is_admin_url' => true,
                        'meta'       => $sess->trainer_name . ' | ' . $date_label . ( $sess->location ? ' | ' . $sess->location : '' ),
                        'created_at' => $sess->trainer_signed_at,
                        'source_id'  => $sid,
                        'source_type'=> 'session',
                    ) );
                }
            }
        }

        /* ---- 3. Dossiers formation (training_registrations) — convocations, enquêtes, évals, certificats ---- */
        $type_map = array(
            'convocation_document_url'              => 'convocation',
            'positioning_result_document_url'       => 'positionnement',
            'mid_survey_document_url'               => 'satisfaction_mi',
            'hot_survey_document_url'               => 'satisfaction_fin',
            'cold_survey_document_url'              => 'satisfaction_froid',
            'diagnostic_result_document_url'        => 'diagnostic',
            'evaluation_result_document_url'        => 'evaluation',
            /* ACDC 3.25.330 — CES DEUX LIGNES ÉTAIENT INVERSÉES, ICI AUSSI.
               « completion_certificate » est le CERTIFICAT DE RÉALISATION, et
               « end_training_certificate » l'ATTESTATION DE FIN DE FORMATION.
               L'agrégat d'audit présentait donc chacune des deux sous le nom de
               l'autre. La 3.25.264 avait corrigé exactement la même inversion
               dans le score de complétude ; elle avait survécu ici — c'est-à-dire
               sur le seul écran qu'on ouvre devant un auditeur, et où une pièce
               mal nommée est une pièce qu'on ne retrouve pas. */
            'completion_certificate_document_url'   => 'certificat',
            'end_training_certificate_document_url' => 'attestation',
        );

        $tr_where = "WHERE is_draft = 0";
        $tr_values = array();
        if ( $formation_id ) { $tr_where .= " AND formation_id = %d"; $tr_values[] = $formation_id; }
        if ( $learner_id )   { $tr_where .= " AND (learner_id = %d OR learner_ids LIKE %s)"; $tr_values[] = $learner_id; $tr_values[] = '%' . $wpdb->esc_like( '"' . $learner_id . '"' ) . '%'; }
        if ( $company_id )   { $tr_where .= " AND company_id = %d"; $tr_values[] = $company_id; }

        $tr_sql = "SELECT * FROM {$trt} {$tr_where} ORDER BY created_at DESC";
        $trs = ! empty( $tr_values ) ? $wpdb->get_results( $wpdb->prepare( $tr_sql, $tr_values ) ) : $wpdb->get_results( $tr_sql );

        foreach ( $trs as $tr ) {
            $fid = (int) $tr->formation_id;
            $this->ensure_formation( $result, $fid, $tr->formation_title, null );

            foreach ( $type_map as $col => $dtype ) {
                if ( $doc_type && $doc_type !== $dtype ) continue;
                $url = isset( $tr->$col ) ? trim( (string) $tr->$col ) : '';
                if ( '' === $url ) continue;

                // Filtre apprenant
                if ( $learner_id ) {
                    $lids = array_filter( array_map( 'absint', explode( ',', (string) ( $tr->learner_ids ?? '' ) ) ) );
                    if ( (int) $tr->learner_id !== $learner_id && ! in_array( $learner_id, $lids, true ) ) continue;
                }

                $doc_types = $this->get_document_types();
                $type_label = isset( $doc_types[ $dtype ] ) ? $doc_types[ $dtype ]['label'] : $dtype;
                $learner_name = '';
                if ( $tr->learner_label ) { $learner_name = $tr->learner_label; }
                elseif ( $tr->learners_label ) { $learner_name = $tr->learners_label; }
                elseif ( $tr->group_label ) { $learner_name = $tr->group_label; }

                $this->add_doc( $result, $fid, 'learner', (int) $tr->learner_id, $dtype, array(
                    'label'        => $type_label . ( $learner_name ? ' — ' . $learner_name : '' ),
                    'url'          => $url,
                    'meta'         => implode( ' | ', array_filter( array( $learner_name, $tr->company_label, $tr->formation_title ) ) ),
                    'created_at'   => $tr->created_at,
                    'learner_name' => $learner_name,
                    'company_name' => $tr->company_label,
                    'source_id'    => $tr->id,
                    'source_type'  => 'training_registration',
                ) );
            }
        }

        /* ---- 4. Analyses du besoin (ACDC 3.21.13) ---- */
        $nad_table = $wpdb->prefix . 'acdc_of_need_analyses';
        $nad_where = "WHERE is_model = 0 AND statut = 'traite' AND (document_url_commanditaire IS NOT NULL OR document_url_apprenant IS NOT NULL OR reponses IS NOT NULL)";
        $nad_args  = array();
        if ( $formation_id ) { $nad_where .= " AND formation_id = %d"; $nad_args[] = $formation_id; }
        if ( $learner_id )   { $nad_where .= " AND apprenant_id = %d"; $nad_args[] = $learner_id; }
        if ( $company_id )   { $nad_where .= " AND entreprise_id = %d"; $nad_args[] = $company_id; }
        if ( $doc_type && ! in_array( $doc_type, array( 'analyse_besoin_commanditaire', 'analyse_besoin_apprenant' ), true ) ) {
          $nad_where .= " AND 1=0"; // type filtré — ne rien retourner
        }
        $nad_sql  = "SELECT * FROM {$nad_table} {$nad_where} ORDER BY updated_at DESC";
        $nad_rows = ! empty( $nad_args ) ? $wpdb->get_results( $wpdb->prepare( $nad_sql, $nad_args ) ) : $wpdb->get_results( $nad_sql );
        foreach ( $nad_rows as $nad ) {
          $fid = (int) ( $nad->formation_id ?: 0 );
          $this->ensure_formation( $result, $fid, $nad->title, null );
          if ( ( ! $doc_type || 'analyse_besoin_commanditaire' === $doc_type ) && ! empty( $nad->reponses ) ) {
            $this->add_doc( $result, $fid, 'formation', 0, 'analyse_besoin_commanditaire', array(
              'label'      => 'Analyse du besoin — ' . ( ! empty( $nad->repondant_nom ) ? trim( $nad->repondant_prenom . ' ' . $nad->repondant_nom ) : 'Commanditaire' ),
              'url'        => ! empty( $nad->document_url_commanditaire ) ? $nad->document_url_commanditaire : admin_url( 'admin.php?page=acdc-of-need-analyses&action=view&item_id=' . (int) $nad->id ),
              'meta'       => mysql2date( 'd/m/Y', $nad->updated_at ),
              'created_at' => $nad->sent_at ?: $nad->updated_at,
              'source_id'  => (int) $nad->id,
              'source_type'=> 'need_analysis',
            ) );
          }
          if ( ( ! $doc_type || 'analyse_besoin_apprenant' === $doc_type ) && 'apprenant' === ( $nad->profil ?? '' ) && ! empty( $nad->reponses ) ) {
            $lid = (int) ( $nad->apprenant_id ?: 0 );
            $this->add_doc( $result, $fid, 'learner', $lid, 'analyse_besoin_apprenant', array(
              'label'      => 'Analyse du besoin — ' . trim( $nad->repondant_prenom . ' ' . $nad->repondant_nom ),
              'url'        => ! empty( $nad->document_url_apprenant ) ? $nad->document_url_apprenant : admin_url( 'admin.php?page=acdc-of-need-analyses&action=view&item_id=' . (int) $nad->id ),
              'meta'       => mysql2date( 'd/m/Y', $nad->updated_at ),
              'created_at' => $nad->sent_at ?: $nad->updated_at,
              'source_id'  => (int) $nad->id,
              'source_type'=> 'need_analysis',
            ) );
          }
        }

        /* ---- Tri final ---- */
        foreach ( $result as &$formation ) {
            uasort( $formation['docs_formation'], array( $this, 'sort_by_date' ) );
            foreach ( $formation['sessions'] as &$session ) {
                uasort( $session['docs'], array( $this, 'sort_by_date' ) );
            }
            uasort( $formation['docs_learners'], array( $this, 'sort_by_date' ) );
        }

        return $result;
    }

    /* -----------------------------------------------------------------------
     * Helpers internes
     * -------------------------------------------------------------------- */
    private function ensure_formation( &$result, $fid, $title, $obj ) {
        if ( ! isset( $result[ $fid ] ) ) {
            $result[ $fid ] = array(
                'title'         => $title ?: '(Formation inconnue)',
                'docs_formation'=> array(),
                'sessions'      => array(),
                'docs_learners' => array(),
            );
        }
    }

    private function ensure_session( &$result, $fid, $sid, $title, $date_label, $start_at ) {
        if ( ! isset( $result[ $fid ]['sessions'][ $sid ] ) ) {
            $result[ $fid ]['sessions'][ $sid ] = array(
                'title'      => $title ?: 'Séance ' . $date_label,
                'date_label' => $date_label,
                'start_at'   => $start_at,
                'docs'       => array(),
            );
        }
    }

    /**
     * ACDC 3.25.330 — DEUX FOIS LA MÊME PIÈCE N'EN FAIT PAS DEUX.
     *
     * L'agrégat empilait sans jamais regarder ce qu'il empilait. Or plusieurs
     * chemins mènent au même fichier — un dossier de groupe et le dossier de
     * chacun de ses apprenants, une convention retrouvée par deux jointures —
     * et la même pièce revenait donc plusieurs fois dans la liste. Devant un
     * auditeur, un dossier qui montre trois fois la même feuille d'émargement
     * ne paraît pas mieux tenu : il paraît approximatif.
     *
     * La clé de dédoublonnage est le COUPLE type + adresse du fichier. Deux
     * entrées qui désignent le même fichier pour le même type sont la même
     * preuve, quelle que soit la requête qui les a trouvées. On ne se fie pas
     * au libellé, qui varie d'un chemin à l'autre.
     *
     * Une entrée SANS adresse n'est jamais écartée : on ne peut pas prouver
     * qu'elle fait doublon, et perdre une preuve serait pire que la répéter.
     */
    private function add_doc( &$result, $fid, $scope, $scope_id, $type, $data ) {
        $entry = array_merge( array( 'type' => $type, 'scope' => $scope ), $data );

        if ( 'session' === $scope ) {
            $panier = &$result[ $fid ]['sessions'][ $scope_id ]['docs'];
        } elseif ( 'learner' === $scope ) {
            $panier = &$result[ $fid ]['docs_learners'];
        } else {
            $panier = &$result[ $fid ]['docs_formation'];
        }

        $adresse = isset( $entry['url'] ) ? trim( (string) $entry['url'] ) : '';
        if ( '' !== $adresse ) {
            foreach ( (array) $panier as $deja ) {
                if ( (string) ( $deja['type'] ?? '' ) === (string) $type
                  && trim( (string) ( $deja['url'] ?? '' ) ) === $adresse ) {
                    unset( $panier );
                    return;
                }
            }
        }

        $panier[] = $entry;
        unset( $panier );
    }

    private function sort_by_date( $a, $b ) {
        return strcmp( (string) ( $b['created_at'] ?? '' ), (string) ( $a['created_at'] ?? '' ) );
    }

    private function format_date_range( $start, $end ) {
        if ( ! $start ) return '';
        $s = wp_date( 'd/m/Y', strtotime( $start ) );
        $e = $end ? wp_date( 'd/m/Y', strtotime( $end ) ) : '';
        return $e && $e !== $s ? $s . ' - ' . $e : $s;
    }

    /* -----------------------------------------------------------------------
     * Filtres disponibles
     * -------------------------------------------------------------------- */
    public function get_filter_formations() {
        global $wpdb;
        /* ACDC 3.25.277 — La modalité et le code sont ramenés avec le titre :
           sans eux, deux fiches du même nom (présentiel / distanciel) donnent
           deux lignes identiques dans le filtre, et on ne sait pas laquelle on
           a choisie. */
        return $wpdb->get_results( "SELECT id, title, code, modality FROM {$wpdb->prefix}acdc_of_formations ORDER BY title ASC, modality ASC" );
    }

    public function get_filter_learners() {
        global $wpdb;
        return $wpdb->get_results( "SELECT id, CONCAT(first_name,' ',COALESCE(NULLIF(usage_last_name,''),last_name)) AS full_name FROM {$wpdb->prefix}acdc_of_learners ORDER BY full_name ASC" );
    }

    public function get_filter_companies() {
        global $wpdb;
        return $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}acdc_of_companies ORDER BY name ASC" );
    }

    /* -----------------------------------------------------------------------
     * Tokens d'accès auditeur
     * -------------------------------------------------------------------- */
    public function create_token( $label = '', $days = 30 ) {
        global $wpdb;
        $token     = wp_generate_password( 48, false );
        $expires   = gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS );
        $wpdb->insert( $this->token_table, array(
            'token'      => $token,
            'label'      => sanitize_text_field( $label ),
            'expires_at' => $expires,
            'created_by' => get_current_user_id(),
            'created_at' => current_time( 'mysql' ),
        ) );
        return $token;
    }

    public function get_tokens() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM {$this->token_table} ORDER BY created_at DESC" );
    }

    public function revoke_token( $token_id ) {
        global $wpdb;
        $wpdb->update( $this->token_table, array( 'is_revoked' => 1 ), array( 'id' => absint( $token_id ) ) );
    }

    public function validate_token( $token ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->token_table} WHERE token = %s AND is_revoked = 0 AND expires_at > %s",
            $token, current_time( 'mysql', true )
        ) );
        if ( $row ) {
            $wpdb->update( $this->token_table, array(
                'last_accessed_at' => current_time( 'mysql' ),
                'access_count'     => (int) $row->access_count + 1,
            ), array( 'id' => $row->id ) );
        }
        return $row;
    }

    public function get_public_url( $token ) {
        return add_query_arg( array( 'acdc_audit' => 'view', 'tok' => $token ), home_url( '/' ) );
    }
}
