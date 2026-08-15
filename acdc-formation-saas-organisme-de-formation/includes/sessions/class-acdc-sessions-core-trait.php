<?php
/**
 * ACDC Séances — ACDC_Sessions_Core_Trait.
 *
 * Extraction micro-incrémentale du sous-bloc métier :
 * séances.
 *
 * @since 3.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Sessions_Core_Trait {

  private function get_sessions() {
    global $wpdb;
    $sql = "SELECT s.*, f.title AS formation_title, e.name AS company_name
        FROM {$this->session_table} s
        LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id
        LEFT JOIN {$this->company_table} e ON e.id = s.company_id
        ORDER BY s.start_date DESC, s.id DESC";
    return $wpdb->get_results( $sql );
  }

  private function get_pending_sessions( $search = '' ) {
    global $wpdb;

    $where = 'WHERE ' . $this->acdc_pending_sessions_where();
    if ( '' !== trim( (string) $search ) ) {
      $like = '%' . $wpdb->esc_like( trim( (string) $search ) ) . '%';
      $where .= $wpdb->prepare( " AND ( s.title LIKE %s OR f.title LIKE %s OR e.name LIKE %s OR s.session_type LIKE %s OR s.session_format LIKE %s )", $like, $like, $like, $like, $like );
    }

    $sql = "SELECT s.*, f.title AS formation_title, e.name AS company_name
      FROM {$this->session_table} s
      LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id
      LEFT JOIN {$this->company_table} e ON e.id = s.company_id
      {$where}
      ORDER BY COALESCE(s.start_at, CONCAT(s.start_date,' 00:00:00'), s.created_at) DESC, s.id DESC";

    return $wpdb->get_results( $sql );
  }

  /**
   * ACDC 3.25.268 — Combien de séances attendent d'être validées.
   *
   * Le même « où » que la liste, pour que la pastille du menu et l'écran ne
   * puissent pas se contredire — deux comptages de la même vérité finissent
   * toujours par diverger.
   *
   * @return int
   */
  private function acdc_pending_sessions_count() {
    global $wpdb;
    return (int) $wpdb->get_var(
      "SELECT COUNT(*) FROM {$this->session_table} s WHERE " . $this->acdc_pending_sessions_where()
    );
  }

  /**
   * Le « où » unique des séances en attente de validation.
   *
   * Une séance annulée n'attend rien : elle sortait pourtant de la liste par
   * hasard, faute d'y être nommée. Elle est écartée ici, une fois, pour les
   * deux lectures.
   *
   * @return string
   */
  private function acdc_pending_sessions_where() {
    return "( COALESCE(s.is_draft,0) = 1 OR COALESCE(s.status,'') = 'Brouillon' )"
      . " AND COALESCE(s.status,'') NOT IN ('Annulée','Annulee')";
  }

  private function get_validated_sessions( $filters = array() ) {
    global $wpdb;

    $defaults = array(
      'search' => '',
      'id' => '',
      'learner' => '',
      'group' => '',
      'format' => '',
      'type' => '',
      'attendance_method' => '',
      'learner_signature_status' => '',
      'presence_status' => '',
      'session_date' => '',
      'formation' => '',
      'trainer' => '',
    );
    $filters = wp_parse_args( is_array( $filters ) ? $filters : array(), $defaults );

    $where = array(
      "COALESCE(s.is_draft,0) = 0",
      "COALESCE(s.status,'') <> 'Brouillon'",
      "COALESCE(s.status,'') <> 'Annulée'",
    );
    $values = array();

    if ( '' !== trim( (string) $filters['search'] ) ) {
      $like = '%' . $wpdb->esc_like( trim( (string) $filters['search'] ) ) . '%';
      $where[] = "(s.title LIKE %s OR f.title LIKE %s OR COALESCE(g.name,'') LIKE %s OR COALESCE(g.trainer_name,'') LIKE %s OR COALESCE(t.first_name,'') LIKE %s OR COALESCE(t.last_name,'') LIKE %s OR CONCAT_WS(' ', t.first_name, t.last_name) LIKE %s OR COALESCE(s.location,'') LIKE %s)";
      array_push( $values, $like, $like, $like, $like, $like, $like, $like, $like );
    }
    if ( '' !== trim( (string) $filters['id'] ) ) {
      $where[] = 's.id = %d';
      $values[] = absint( $filters['id'] );
    }
    if ( '' !== trim( (string) $filters['learner'] ) ) {
      $like = '%' . $wpdb->esc_like( trim( (string) $filters['learner'] ) ) . '%';
      $where[] = "(COALESCE(l.first_name,'') LIKE %s OR COALESCE(l.usage_last_name,'') LIKE %s OR COALESCE(l.last_name,'') LIKE %s)";
      array_push( $values, $like, $like, $like );
    }
    if ( '' !== trim( (string) $filters['group'] ) ) {
      $like = '%' . $wpdb->esc_like( trim( (string) $filters['group'] ) ) . '%';
      $where[] = "COALESCE(g.name,'') LIKE %s";
      $values[] = $like;
    }
    if ( '' !== trim( (string) $filters['format'] ) ) {
      $where[] = 'COALESCE(s.session_format,\'\') = %s';
      $values[] = sanitize_text_field( $filters['format'] );
    }
    if ( '' !== trim( (string) $filters['type'] ) ) {
      $where[] = 'COALESCE(s.session_type,\'\') = %s';
      $values[] = sanitize_text_field( $filters['type'] );
    }
    if ( '' !== trim( (string) $filters['attendance_method'] ) ) {
      $where[] = 'COALESCE(s.attendance_method,\'\') = %s';
      $values[] = sanitize_text_field( $filters['attendance_method'] );
    }
    if ( '' !== trim( (string) $filters['session_date'] ) ) {
      $where[] = 'DATE(COALESCE(s.start_at, CONCAT(s.start_date,\' 00:00:00\'))) = %s';
      $values[] = sanitize_text_field( $filters['session_date'] );
    }
    if ( '' !== trim( (string) $filters['formation'] ) ) {
      $like = '%' . $wpdb->esc_like( trim( (string) $filters['formation'] ) ) . '%';
      $where[] = "COALESCE(f.title,'') LIKE %s";
      $values[] = $like;
    }
    if ( '' !== trim( (string) $filters['trainer'] ) ) {
      $like = '%' . $wpdb->esc_like( trim( (string) $filters['trainer'] ) ) . '%';
      $where[] = "(COALESCE(g.trainer_name,'') LIKE %s OR COALESCE(t.first_name,'') LIKE %s OR COALESCE(t.last_name,'') LIKE %s OR CONCAT_WS(' ', t.first_name, t.last_name) LIKE %s)";
      array_push( $values, $like, $like, $like, $like );
    }

    $sql = "SELECT s.*, f.title AS formation_title, f.code AS formation_code, c.city AS formation_city,\n        MAX(g.id) AS group_id, MAX(g.name) AS group_name, MAX(g.trainer_name) AS group_trainer_name, MAX(g.learner_ids) AS group_learner_ids,\n        COUNT(DISTINCT l.id) AS learner_count, MAX(l.first_name) AS learner_first_name, MAX(l.usage_last_name) AS learner_usage_last_name, MAX(l.last_name) AS learner_last_name,\n        MAX(t.first_name) AS trainer_first_name, MAX(t.last_name) AS trainer_last_name\n      FROM {$this->session_table} s\n      LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id\n      LEFT JOIN {$this->company_table} c ON c.id = s.company_id\n      LEFT JOIN {$this->group_table} g ON g.session_id = s.id\n      LEFT JOIN {$this->learner_table} l ON l.session_id = s.id\n      LEFT JOIN {$this->trainer_table} t ON t.id = s.trainer_id\n      WHERE " . implode( ' AND ', $where ) . "\n      GROUP BY s.id\n      ORDER BY COALESCE(s.start_at, CONCAT(s.start_date,' 00:00:00'), s.created_at) DESC, s.id DESC";

    $rows = ! empty( $values ) ? $wpdb->get_results( $wpdb->prepare( $sql, $values ) ) : $wpdb->get_results( $sql );

    if ( ! empty( $rows ) ) {
      // Anti N+1 : préchargement de l'émargement de TOUTES les séances de la page en 2 requêtes.
      $emarg_by_session = array();
      $learners_by_emarg = array();
      if ( class_exists( 'ACDC_Emargement' ) ) {
        $emarg_core = ACDC_Emargement::get_instance()->core;
        $session_ids = array();
        foreach ( $rows as $row ) { $session_ids[] = (int) $row->id; }
        $emarg_by_session  = $emarg_core->get_by_session_ids( $session_ids );
        if ( ! empty( $emarg_by_session ) ) {
          $emarg_ids = array();
          foreach ( $emarg_by_session as $em ) { $emarg_ids[] = (int) $em->id; }
          $learners_by_emarg = $emarg_core->get_learners_for_emarg_ids( $emarg_ids );
        }
      }
      foreach ( $rows as $row ) {
        /* ACDC 3.25.212 — Le nombre d'apprenants et le commanditaire se
           déduisent du dossier, comme dans le portail formateur.
           La requête ci-dessus compte les apprenants par `learner.session_id` :
           sur une formation de deux jours, elle en trouvait deux le premier jour
           et aucun le second, alors que trois personnes suivaient les deux. La
           colonne ne peut pas désigner plus d'une séance ; on ne lui demande
           donc plus de le faire. */
        $resolved_learners = $this->acdc_session_learners( $row );
        if ( count( $resolved_learners ) > (int) $row->learner_count ) {
          $row->learner_count = count( $resolved_learners );
          if ( 1 === count( $resolved_learners ) ) {
            $only = $resolved_learners[0];
            $row->learner_first_name      = (string) $only->first_name;
            $row->learner_last_name       = (string) $only->last_name;
            $row->learner_usage_last_name = (string) $only->usage_last_name;
          }
        }
        $resolved_company = $this->acdc_session_company_name( $row );
        if ( '' !== $resolved_company ) {
          $row->company_name = $resolved_company;
        }

        $row->learner_or_group_label = $this->get_session_validated_learner_or_group_label( $row );
        $row->trainer_display_name = $this->get_session_validated_trainer_label( $row );
        $row->location_display = $this->get_session_validated_location_label( $row );
        /* ACDC 3.25.200 — La LISTE lisait le même mot en dur que la carte.
           Corriger le détail sans corriger le tableau laissait la contradiction
           entière : le détail disait « aucune feuille ouverte » pendant que la
           ligne affichait SIGNÉE — et c'est la liste que l'on imprime et que
           l'on parcourt. Une pièce d'audit ne s'affirme jamais, elle se lit. */
        $row->trainer_signature_label = $this->get_attendance_sheet_trainer_signature_label( $row );
        // Statuts réels dérivés de l'émargement préchargé (affichage + filtres opérants).
        $emarg = isset( $emarg_by_session[ (int) $row->id ] ) ? $emarg_by_session[ (int) $row->id ] : null;
        $emarg_learners = ( $emarg && isset( $learners_by_emarg[ (int) $emarg->id ] ) ) ? $learners_by_emarg[ (int) $emarg->id ] : array();
        $counts = \ACDC\Support\EmargeStatus::countStatuses( $emarg_learners );
        $labels = \ACDC\Support\EmargeStatus::deriveLabels( (bool) $emarg, $counts['total'], $counts['signed'], $counts['absent'] );
        $row->learner_signature_label = $labels['signature'];
        $row->presence_status_label = $labels['presence'];
        // ACDC 3.25.111 — compteurs bruts d'émargement exposés pour le calcul du
        // taux d'occupation (présents / inscrits) dans les statistiques formateurs.
        $row->presence_signed_count = (int) $counts['signed'];
        $row->presence_absent_count = (int) $counts['absent'];
        $row->presence_total_count  = (int) $counts['total'];
      }
    }

    if ( '' !== trim( (string) $filters['learner_signature_status'] ) || '' !== trim( (string) $filters['presence_status'] ) ) {
      $rows = array_values( array_filter( $rows, function( $row ) use ( $filters ) {
        if ( '' !== trim( (string) $filters['learner_signature_status'] ) ) {
          if ( sanitize_text_field( $filters['learner_signature_status'] ) !== ( isset( $row->learner_signature_label ) ? $row->learner_signature_label : '' ) ) {
            return false;
          }
        }
        if ( '' !== trim( (string) $filters['presence_status'] ) ) {
          if ( sanitize_text_field( $filters['presence_status'] ) !== ( isset( $row->presence_status_label ) ? $row->presence_status_label : '' ) ) {
            return false;
          }
        }
        return true;
      } ) );
    }

    return $rows;
  }

  private function get_session_validated_learner_or_group_label( $session ) {
    $group_name = ! empty( $session->group_name ) ? trim( (string) $session->group_name ) : '';
    if ( '' !== $group_name ) {
      return $group_name;
    }
    /* ACDC 3.25.203 — Un MAX() par colonne fabriquait une personne qui n'existe
       pas.
       La requête agrégeait indépendamment MAX(prénom), MAX(nom d'usage) et
       MAX(nom de naissance) sur TOUTES les apprenantes de la séance. Avec
       Bérengère Valeriano, Ilona Rossa et Léandra Rossa, le plus grand prénom
       est « Léandra » et le plus grand nom « Valeriano » : la séance s'intitulait
       donc « Léandra Valeriano », une personne qui n'a jamais existé. Chaque
       colonne était juste, prise isolément ; c'est leur assemblage qui mentait.
       Le défaut n'est visible qu'à partir de deux apprenants, ce qui explique
       qu'il ait traversé toute la recette sans se faire voir.
       Dès qu'il y a plusieurs apprenants, on ne fabrique plus de nom : on les
       compte. Un nom propre n'est affiché que lorsqu'une seule personne est
       rattachée, cas où l'agrégat porte forcément sur une seule ligne. */
    $learner_count = isset( $session->learner_count ) ? (int) $session->learner_count : 0;
    if ( $learner_count > 1 ) {
      return $learner_count . ' apprenants';
    }

    /* ACDC 3.25.201 — Le nom d'usage REMPLACE le nom de naissance, il ne s'y
       ajoute pas.
       Cette ligne concaténait les deux, produisant « Valeriano Valeriano » pour
       une personne dont les deux colonnes portent la même valeur — et, sur une
       feuille d'émargement, un nom qui n'existe dans aucun répertoire. Partout
       ailleurs dans le plugin la règle est celle-ci : nom d'usage s'il est
       renseigné, nom de naissance sinon. Elle s'applique désormais ici aussi. */
    $learner_last = isset( $session->learner_usage_last_name ) ? trim( (string) $session->learner_usage_last_name ) : '';
    if ( '' === $learner_last ) {
      $learner_last = isset( $session->learner_last_name ) ? trim( (string) $session->learner_last_name ) : '';
    }
    $learner_name = trim( implode( ' ', array_filter( array(
      isset( $session->learner_first_name ) ? trim( (string) $session->learner_first_name ) : '',
      $learner_last,
    ) ) ) );
    if ( '' !== $learner_name ) {
      return $learner_name;
    }
    if ( ! empty( $session->title ) ) {
      return (string) $session->title;
    }
    return '—';
  }

  private function get_session_validated_trainer_label( $session ) {
    // 1. Nom via le groupe (chemin historique)
    if ( ! empty( $session->group_trainer_name ) ) {
      return (string) $session->group_trainer_name;
    }
    // 2. Fallback : formateur lié directement à la session (nouveau formulaire Créer séance)
    $first = isset( $session->trainer_first_name ) ? trim( (string) $session->trainer_first_name ) : '';
    $last  = isset( $session->trainer_last_name )  ? trim( (string) $session->trainer_last_name )  : '';
    $name  = trim( $first . ' ' . $last );
    if ( '' !== $name ) {
      return $name;
    }
    return '—';
  }

  /**
   * ACDC 3.25.212 — Le lieu de la séance, et RIEN d'autre.
   *
   * Cette fonction collait deux informations sans rapport : l'adresse où se
   * tient la séance, et la ville du COMMANDITAIRE. D'où l'affichage relevé par
   * David — « 7 avenue Paul Cézanne, Cogolin — SIX-FOURS-LES-PLAGES » — qui
   * annonce deux villes pour un même lieu. Un formateur qui lit cette ligne ne
   * sait pas où se rendre, et c'est la seule chose qu'elle devait lui dire.
   *
   * La ville de l'entreprise n'est ajoutée que lorsque la séance n'a AUCUNE
   * adresse : elle vaut alors mieux que rien, mais elle ne complète jamais une
   * adresse existante.
   */
  private function get_session_validated_location_label( $session ) {
    $location = isset( $session->location ) ? trim( (string) $session->location ) : '';
    if ( '' !== $location ) {
      return $location;
    }
    if ( ! empty( $session->formation_city ) ) {
      return strtoupper( (string) $session->formation_city );
    }
    return '—';
  }

  private function get_session_validated_filter_options( $items, $field ) {
    $values = array();
    foreach ( $items as $item ) {
      $value = isset( $item->{$field} ) ? trim( (string) $item->{$field} ) : '';
      if ( '' === $value ) {
        continue;
      }
      $values[ $value ] = $value;
    }
    ksort( $values, SORT_NATURAL | SORT_FLAG_CASE );
    return $values;
  }

  /**
   * ACDC 3.25.249 — Les horaires d'une séance, en demi-journées.
   *
   * La convocation annonçait la date mais pas les heures : l'apprenant devait
   * ouvrir la pièce jointe pour savoir quand se présenter. Les demi-journées
   * sont pourtant écrites sur la séance depuis que la convention porte son
   * déroulé.
   *
   * `schedule_json` contient des datetimes LOCALES : on les lit avec
   * mysql2date, jamais avec strtotime — c'est la règle de ce plugin, et s'en
   * écarter décale l'affichage d'un fuseau.
   */
  private function acdc_session_hours_label( $session ) {
    if ( ! $session ) {
      return '—';
    }
    $slots = array();
    if ( ! empty( $session->schedule_json ) ) {
      $decoded = json_decode( (string) $session->schedule_json, true );
      if ( is_array( $decoded ) ) {
        foreach ( $decoded as $slot ) {
          if ( empty( $slot['start_at'] ) || empty( $slot['end_at'] ) ) {
            continue;
          }
          $slots[] = mysql2date( 'H\hi', (string) $slot['start_at'] ) . '–' . mysql2date( 'H\hi', (string) $slot['end_at'] );
        }
      }
    }
    if ( ! empty( $slots ) ) {
      return implode( ' et ', $slots );
    }
    /* Pas de découpage : on retombe sur les heures de la séance entière plutôt
       que de laisser croire qu'aucun horaire n'est prévu. */
    if ( ! empty( $session->start_at ) && ! empty( $session->end_at ) ) {
      return mysql2date( 'H\hi', (string) $session->start_at ) . '–' . mysql2date( 'H\hi', (string) $session->end_at );
    }
    return '—';
  }

  private function get_session_datetime_label( $session ) {
    if ( ! $session ) {
      return '—';
    }

    $start_at = isset( $session->start_at ) ? $session->start_at : '';
    $end_at   = isset( $session->end_at ) ? $session->end_at : '';
    if ( ! empty( $start_at ) && ! empty( $end_at ) ) {
      $start_ts = strtotime( $start_at );
      $end_ts   = strtotime( $end_at );
      if ( $start_ts && $end_ts ) {
        if ( gmdate( 'Y-m-d', $start_ts ) === gmdate( 'Y-m-d', $end_ts ) ) {
          return mysql2date( 'd/m/Y H\hi', $start_at ) . ' → ' . mysql2date( 'H\hi', $end_at );
        }
        return mysql2date( 'd/m/Y H\hi', $start_at ) . ' → ' . mysql2date( 'd/m/Y H\hi', $end_at );
      }
    }

    $start_date = isset( $session->start_date ) ? $session->start_date : '';
    $end_date   = isset( $session->end_date ) ? $session->end_date : '';
    if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
      if ( $start_date === $end_date ) {
        return mysql2date( 'd/m/Y', $start_date );
      }
      return mysql2date( 'd/m/Y', $start_date ) . ' → ' . mysql2date( 'd/m/Y', $end_date );
    }
    if ( ! empty( $start_date ) ) {
      /* ACDC 3.25.212 — La colonne s'intitule « date et heures » ; quand les
         heures manquent, on le DIT au lieu de laisser croire qu'elles n'ont
         pas d'importance. C'est aussi ce qui empêche de planifier l'émargement
         et la convocation : le savoir en lisant la ligne évite d'aller le
         chercher dans le journal. */
      return mysql2date( 'd/m/Y', $start_date ) . ' — horaires non renseignés';
    }
    if ( ! empty( $start_at ) ) {
      return mysql2date( 'd/m/Y H\hi', $start_at ) . ' — heure de fin non renseignée';
    }

    return '—';
  }

  private function get_session_status_badge_label( $session ) {
    $status = isset( $session->status ) ? (string) $session->status : '';
    if ( ! empty( $status ) ) {
      return $status;
    }
    return ! empty( $session->is_draft ) ? 'Brouillon' : 'Planifiée';
  }

  private function get_session_row_action_links( $entry, $base_tab = 'sessions_pending' ) {
    $item_id = isset( $entry->id ) ? (int) $entry->id : 0;
    if ( ! $item_id ) {
      return array();
    }

    if ( is_admin() ) {
      $view_url = $this->admin_tab_url( $base_tab, array( 'action' => 'view', 'item_id' => $item_id ) );
      $edit_url = $this->admin_tab_url( 'sessions', array( 'action' => 'edit', 'item_id' => $item_id ) );
      $delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_delete_session&session_id=' . $item_id . '&return_tab=' . rawurlencode( $base_tab ) . '&page=acdc-of-dashboard' ), 'acdc_delete_session_' . $item_id );
    } else {
      $view_url = $this->portal_page_url( array( 'tab' => $base_tab, 'action' => 'view', 'item_id' => $item_id ) );
      $edit_url = $this->portal_page_url( array( 'tab' => 'sessions', 'action' => 'edit', 'item_id' => $item_id ) );
      $delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=acdc_delete_session&session_id=' . $item_id . '&return_tab=' . rawurlencode( $base_tab ) ), 'acdc_delete_session_' . $item_id );
    }

    return array( $view_url, $edit_url, $delete_url );
  }

  private function get_session_detail_sections( $session ) {
    $sections = array();
    $formation_title = ! empty( $session->formation_title ) ? $session->formation_title : '—';
    $company_name = ! empty( $session->company_name ) ? $session->company_name : '—';
    /* ACDC 3.25.212 — La fiche disait trois fois la même chose et taisait
       l'essentiel. « Titre », « Formation » et « Intitulé de la séance »
       portaient la même chaîne dans trois blocs différents, pendant qu'il
       fallait quitter l'écran pour savoir QUI anime et QUI suit la séance.
       On ne répète plus l'intitulé, et l'on répond d'abord aux questions
       qu'on se pose devant une séance. */
    $learners = $this->acdc_session_learners( $session );
    $learner_names = array();
    foreach ( $learners as $learner ) {
      $last = ! empty( $learner->usage_last_name ) ? $learner->usage_last_name : $learner->last_name;
      $learner_names[] = trim( (string) $learner->first_name . ' ' . (string) $last );
    }

    $sections[] = array(
      'title' => 'Informations principales',
      'items' => array(
        'Intitulé' => ! empty( $session->title ) ? $session->title : '—',
        'Formation' => $formation_title,
        'Commanditaire' => $company_name,
        'Formateur' => $this->get_session_validated_trainer_label( $session ),
        'Type de séance' => ! empty( $session->session_type ) ? $session->session_type : 'Non renseigné',
      ),
    );
    $sections[] = array(
      'title' => 'Planification',
      'items' => array(
        'Date et horaires' => $this->get_session_datetime_label( $session ),
        'Durée' => $this->get_session_duration_label( $session ),
        'Lieu' => $this->get_session_validated_location_label( $session ),
        'Format de la séance' => ! empty( $session->session_format ) ? $session->session_format : '—',
        'Méthode d’émargement' => ! empty( $session->attendance_method ) ? $session->attendance_method : 'Non renseignée',
        'Statut' => $this->get_session_status_badge_label( $session ),
      ),
    );
    $sections[] = array(
      'title' => 'Apprenants (' . count( $learners ) . ')',
      'items' => array(
        'Inscrits' => ! empty( $learner_names ) ? implode( "\n", $learner_names ) : 'Aucun apprenant rattaché à cette séance.',
      ),
    );
    /* ACDC 3.25.271 — CE QUI MANQUE SE DIT AVANT LE JOUR J.
       Les trois quiz d'une action — positionnement, diagnostique, acquis — sont
       préparés d'office à la création de la séance. Encore faut-il que la
       formation les possède. « Sinon on découvre le trou le matin même » : la
       fiche de la séance nomme donc précisément ce qui manque, plutôt que
       d'afficher un compteur qu'il faudrait aller interpréter ailleurs. */
    if ( method_exists( $this, 'acdc_qz_missing_purposes' ) && ! empty( $session->formation_id ) ) {
      $quiz_manquants = $this->acdc_qz_missing_purposes( (int) $session->formation_id );
      $sections[] = array(
        'title' => 'Quiz de la formation',
        'items' => array(
          'Préparés pour cette action' => empty( $quiz_manquants )
            ? 'Les trois quiz sont en place : test de positionnement, évaluation diagnostique, évaluation des acquis. Le formateur les déclenche depuis son extranet.'
            : 'MANQUANT : ' . implode( ', ', $quiz_manquants ) . ".\nCette formation ne pourra pas les faire passer tant qu'ils n'existent pas dans le module Quiz.",
        ),
      );
    }

    $sections[] = array(
      'title' => 'Notes',
      'items' => array(
        'Note formateur / admin' => ! empty( $session->notes ) ? $session->notes : '—',
      ),
    );
    // ACDC 3.23.1 — Traçabilité des convocations automatiques (preuve Qualiopi).
    $conv_appr = ! empty( $session->convocation_sent_at )          ? mysql2date( 'd/m/Y \à H\hi', $session->convocation_sent_at )          : 'Non envoyée';
    $conv_rapp = ! empty( $session->convocation_reminder_sent_at ) ? mysql2date( 'd/m/Y \à H\hi', $session->convocation_reminder_sent_at ) : 'Non envoyé';
    /* ACDC 3.25.225 — LA CONVOCATION DU COMMANDITAIRE N'EXISTE PAS.
       « Le commanditaire n'a pas lieu d'avoir de convocation, ce sont
       uniquement les apprenants. » La carte de séance annonçait pourtant une
       ligne « Convocation commanditaire — Non envoyée », c'est-à-dire une
       pièce manquante à un dossier qui n'en attend aucune. Un écran de suivi
       Qualiopi qui signale un manque imaginaire pousse à envoyer un document
       qui n'a pas lieu d'être. */
    $sections[] = array(
      'title' => 'Convocations automatiques',
      'items' => array(
        'Convocation apprenants (J-7)' => $conv_appr,
        'Rappel apprenants (J-1)'      => $conv_rapp,
      ),
    );
    return $sections;
  }

  private function get_session( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->session_table} WHERE id = %d", $id ) );
  }

  private function get_sessions_for_calendar( $year, $month ) {
    global $wpdb;

    $year  = absint( $year );
    $month = absint( $month );
    if ( $month < 1 || $month > 12 ) {
      $month = (int) current_time( 'n' );
    }
    if ( $year < 2000 || $year > 2100 ) {
      $year = (int) current_time( 'Y' );
    }

    $month_start = sprintf( '%04d-%02d-01', $year, $month );
    $month_end   = gmdate( 'Y-m-t', gmmktime( 12, 0, 0, $month, 1, $year ) );

    $sql = "SELECT s.*, f.title AS formation_title, e.name AS company_name
      FROM {$this->session_table} s
      LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id
      LEFT JOIN {$this->company_table} e ON e.id = s.company_id
      WHERE COALESCE(DATE(s.start_at), s.start_date, DATE(s.end_at), s.end_date) IS NOT NULL
        AND COALESCE(DATE(s.start_at), s.start_date, DATE(s.end_at), s.end_date) <= %s
        AND COALESCE(DATE(s.end_at), s.end_date, DATE(s.start_at), s.start_date) >= %s
        AND COALESCE(s.is_draft,0) = 0
        AND COALESCE(s.status,'') <> 'Annulée'
      ORDER BY COALESCE(DATE(s.start_at), s.start_date, DATE(s.end_at), s.end_date) ASC, s.id ASC";

    return $wpdb->get_results( $wpdb->prepare( $sql, $month_end, $month_start ) );
  }

  private function get_session_dates_grouped_by_formation() {
    $sessions = $this->get_sessions();
    $map = array();
    foreach ( $sessions as $session ) {
      if ( ! empty( $session->is_draft ) ) {
        continue;
      }
      $formation_id = isset( $session->formation_id ) ? (int) $session->formation_id : 0;
      if ( $formation_id <= 0 ) {
        continue;
      }
      $start = '';
      $end = '';
      if ( ! empty( $session->start_at ) ) {
        $start = gmdate( 'Y-m-d', strtotime( $session->start_at ) );
      } elseif ( ! empty( $session->start_date ) ) {
        $start = $session->start_date;
      }
      if ( ! empty( $session->end_at ) ) {
        $end = gmdate( 'Y-m-d', strtotime( $session->end_at ) );
      } elseif ( ! empty( $session->end_date ) ) {
        $end = $session->end_date;
      }
      if ( empty( $start ) ) {
        continue;
      }
      if ( empty( $end ) || $end < $start ) {
        $end = $start;
      }
      if ( ! isset( $map[ $formation_id ] ) ) {
        $map[ $formation_id ] = array();
      }
      for ( $ts = strtotime( $start . ' 00:00:00' ); $ts <= strtotime( $end . ' 00:00:00' ); $ts = strtotime( '+1 day', $ts ) ) {
        $date = gmdate( 'Y-m-d', $ts );
        $map[ $formation_id ][ $date ] = $date;
      }
    }
    foreach ( $map as $formation_id => $dates ) {
      $map[ $formation_id ] = array_values( $dates );
      sort( $map[ $formation_id ] );
    }
    return $map;
  }

  /**
   * ACDC 3.25.198 — L'état réel de l'émargement d'une séance.
   *
   * Deux écrans se contredisaient sur la même feuille : « Feuilles
   * d'émargement » la déclarait SIGNÉE, « Séances validées » proposait encore
   * de l'envoyer. La raison était simple et grave : le mot SIGNÉE était écrit en
   * dur dans le gabarit, sans la moindre lecture de la base. Sur une pièce
   * d'audit, un écran qui affirme une signature sans la vérifier ne se contente
   * pas de se tromper — il fabrique une preuve.
   *
   * Une seule source désormais : la feuille d'émargement elle-même.
   */
  private function get_attendance_sheet_state( $session ) {
    global $wpdb;

    $empty = array( 'signed' => false, 'signed_at' => '', 'sheets' => 0, 'signed_sheets' => 0 );

    $session_id = isset( $session->id ) ? (int) $session->id : 0;
    if ( $session_id <= 0 ) {
      return $empty;
    }

    $table  = $wpdb->prefix . 'acdc_of_emarg_sessions';
    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    if ( $exists !== $table ) {
      return $empty;
    }

    $rows = $wpdb->get_results( $wpdb->prepare(
      "SELECT trainer_status, trainer_signed_at FROM {$table} WHERE session_id = %d",
      $session_id
    ) );
    if ( empty( $rows ) ) {
      return $empty;
    }

    $signed    = 0;
    $latest    = '';
    foreach ( $rows as $row ) {
      $is_signed = ( 'signe' === (string) $row->trainer_status ) || ! empty( $row->trainer_signed_at );
      if ( ! $is_signed ) {
        continue;
      }
      $signed++;
      if ( ! empty( $row->trainer_signed_at ) && ( '' === $latest || $row->trainer_signed_at > $latest ) ) {
        $latest = (string) $row->trainer_signed_at;
      }
    }

    return array(
      'signed'        => ( $signed > 0 && $signed === count( $rows ) ),
      'signed_at'     => $latest,
      'sheets'        => count( $rows ),
      'signed_sheets' => $signed,
    );
  }

  private function get_attendance_sheet_trainer_signature_label( $session ) {
    $state = $this->get_attendance_sheet_state( $session );

    if ( 0 === $state['sheets'] ) {
      return 'Aucune feuille ouverte';
    }
    if ( $state['signed'] ) {
      return 1 === $state['sheets'] ? 'Signée' : 'Signées (' . $state['signed_sheets'] . '/' . $state['sheets'] . ')';
    }
    if ( $state['signed_sheets'] > 0 ) {
      return 'Partiellement signée (' . $state['signed_sheets'] . '/' . $state['sheets'] . ')';
    }
    return 'Non signée';
  }

  /**
   * ACDC 3.25.198 — La date de signature est celle de la SIGNATURE.
   *
   * Cette fonction rendait `updated_at` de la SÉANCE sous l'intitulé « Date
   * ajout / signature » : modifier le lieu d'une séance déplaçait donc la date
   * de sa feuille signée. Une date d'audit qui bouge quand on corrige une
   * adresse n'est pas une date d'audit.
   *
   * Quand rien n'est signé, on le dit — plutôt que d'afficher une date qui n'en
   * est pas une.
   */
  private function get_attendance_sheet_signature_date_label( $session ) {
    $state = $this->get_attendance_sheet_state( $session );

    if ( '' !== $state['signed_at'] ) {
      return mysql2date( 'd F Y \\à H\\hi', $state['signed_at'] );
    }
    if ( $state['sheets'] > 0 ) {
      return 'Non signée à ce jour';
    }
    return '—';
  }

  private function get_session_duration_label( $session ) {
    if ( empty( $session->start_at ) || empty( $session->end_at ) ) {
      return '—';
    }
    $start = strtotime( (string) $session->start_at );
    $end = strtotime( (string) $session->end_at );
    if ( ! $start || ! $end || $end <= $start ) {
      return '—';
    }
    $minutes = (int) round( ( $end - $start ) / 60 );
    $hours = (int) floor( $minutes / 60 );
    $rest = $minutes % 60;
    if ( $hours > 0 && $rest > 0 ) {
      /* ACDC 3.25.207 — « 3h30 min » doublait l'unité : les minutes étaient
         déjà dites par le « 30 ». On écrit « 3 h 30 », comme un horaire. */
      return sprintf( '%d h %02d', $hours, $rest );
    }
    if ( $hours > 0 ) {
      return sprintf( '%dh', $hours );
    }
    return sprintf( '%d min', $rest );
  }

  private function get_session_type_options() {
    return array(
      '' => 'Choisir une option',
      'Individuelle' => 'Individuelle',
      'Groupe' => 'Groupe',
      'Créneau réservé' => 'Créneau réservé (sans apprenant/groupe)',
    );
  }

  private function get_session_attendance_options() {
    return array(
      '' => 'Choisir une option',
      'Pas d’émargement' => 'Pas d’émargement',
      'Manuelle' => 'Manuelle',
      'Électronique' => 'Électronique',
    );
  }

  private function get_session_format_options() {
    return array(
      '' => 'Choisir une option',
      'Présentiel' => 'Présentiel',
      'Distanciel' => 'Distanciel',
    );
  }
}
