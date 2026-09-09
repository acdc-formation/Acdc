<?php
/**
 * ACDC Conformité / Qualité annexe — noyau et référentiels
 *
 * Extraction incrémentale du module BPF, amélioration continue, échéances apprenants, veille et prestations annexes.
 * Version : 3.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Compliance_Quality_Core_Trait {


  /**
   * Les bornes de l'exercice comptable, telles que la structure les a réglées.
   *
   * ACDC 3.25.284 — Un seul endroit sait lire « 23/04 → 22/04 ». Le BPF de
   * l'organisme et celui des prestations extérieures l'appellent tous deux :
   * un exercice qui ne serait pas borné pareil dans les deux documents
   * produirait deux déclarations qui ne se recoupent pas, sans qu'aucune des
   * deux n'ait l'air fausse.
   *
   * @param int $annee Millésime de l'exercice.
   * @return array{start:string,end:string,start_sql:string,end_sql:string}
   */
  public function acdc_bpf_exercice_dates( $annee ) {
    $profile = method_exists( $this, 'get_company_profile_options' ) ? (array) $this->get_company_profile_options() : array();
    $debut   = ! empty( $profile['accounting_start'] ) ? (string) $profile['accounting_start'] : '01/01';
    $fin     = ! empty( $profile['accounting_end'] )   ? (string) $profile['accounting_end']   : '31/12';

    list( $d, $f )   = \ACDC\Support\FiscalYear::displayBounds( $debut, $fin, (int) $annee );
    list( $ds, $fs ) = \ACDC\Support\FiscalYear::sqlBounds( $debut, $fin, (int) $annee );

    return array( 'start' => $d, 'end' => $f, 'start_sql' => $ds, 'end_sql' => $fs );
  }

  /**
   * L'exercice en cours à une date donnée — celui qu'on propose par défaut.
   *
   * Au 15 mars, avec un exercice ouvrant le 23 avril, on est encore dans celui
   * de l'année précédente. Proposer l'année courante ferait générer un BPF
   * presque vide sans que rien ne le signale.
   *
   * @param string $date_sql AAAA-MM-JJ ; vide vaut aujourd'hui.
   * @return int
   */
  public function acdc_bpf_exercice_courant( $date_sql = '' ) {
    $profile = method_exists( $this, 'get_company_profile_options' ) ? (array) $this->get_company_profile_options() : array();
    $debut   = ! empty( $profile['accounting_start'] ) ? (string) $profile['accounting_start'] : '01/01';
    $fin     = ! empty( $profile['accounting_end'] )   ? (string) $profile['accounting_end']   : '31/12';
    $date    = '' !== $date_sql ? $date_sql : current_time( 'Y-m-d' );

    return \ACDC\Support\FiscalYear::yearOf( $debut, $fin, $date );
  }

  private function get_bpf_records() {
    $profile       = method_exists( $this, 'get_company_profile_options' ) ? $this->get_company_profile_options() : array();
    $acc_start_raw = ! empty( $profile['accounting_start'] ) ? rtrim( (string) $profile['accounting_start'], '/' ) : '01/01';
    $acc_end_raw   = ! empty( $profile['accounting_end'] )   ? rtrim( (string) $profile['accounting_end'],   '/' ) : '31/12';

    /* ACDC 3.25.284 — L'arithmétique de l'exercice a quitté cette fonction pour
       \ACDC\Support\FiscalYear. Elle n'y était pas fausse : elle y était
       SEULE, et un second BPF — celui des prestations extérieures — a eu besoin
       des mêmes bornes. Deux calculs de la même chose finissent toujours par
       diverger, et celui-ci décide de ce qui entre dans une déclaration
       administrative. */
    $build_dates = function( $year ) use ( $acc_start_raw, $acc_end_raw ) {
      return \ACDC\Support\FiscalYear::displayBounds( $acc_start_raw, $acc_end_raw, $year );
    };

    list( $start_2025, $end_2025 ) = $build_dates( 2025 );
    list( $start_2024, $end_2024 ) = $build_dates( 2024 );

    $defaults = array(
      '2025' => array(
        'year'              => '2025',
        'title'             => 'BPF 2025',
        'start_date'        => $start_2025,
        'end_date'          => $end_2025,
        'status'            => 'pending',
        'imported_file_url' => '',
      ),
      '2024' => array(
        'year'              => '2024',
        'title'             => 'BPF 2024',
        'start_date'        => $start_2024,
        'end_date'          => $end_2024,
        'status'            => 'pending',
        'imported_file_url' => '',
      ),
    );
    $saved = get_option( 'acdc_of_bpf_records', array() );
    $saved = is_array( $saved ) ? $saved : array();
    foreach ( $defaults as $key => $row ) {
      if ( isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ) {
        $merged = wp_parse_args( $saved[ $key ], $row );
        // Toujours recalculer les dates depuis le profil — si accounting_start/end change, les dates se mettent à jour
        $merged['start_date'] = $row['start_date'];
        $merged['end_date']   = $row['end_date'];
        $defaults[ $key ] = $merged;
      }
    }
    return $defaults;
  }



  private function save_bpf_records( $records ) {
    update_option( 'acdc_of_bpf_records', $records, false );
  }


  // ACDC 3.25.118 — Détail BPF par formation : actions de formation (conventions) sur la période
  /**
   * Retourne les actions de formation (conventions/contrats d'inscription) rattachées à une
   * formation donnée sur la période d'exercice. Source : table registration_contracts, jointe
   * aux sociétés commanditaires. Chaque ligne = une action commandée (une convention).
   *
   * @param int    $formation_id ID de la formation catalogue.
   * @param string $start_sql    Début période AAAA-MM-JJ.
   * @param string $end_sql      Fin période AAAA-MM-JJ.
   * @return array Objets ligne registration_contracts (+ company_name).
   */
  private function get_bpf_formation_actions( $formation_id, $start_sql, $end_sql ) {
    global $wpdb;
    $formation_id = (int) $formation_id;
    if ( $formation_id <= 0 || ! $start_sql || ! $end_sql ) { return array(); }
    if ( ! property_exists( $this, 'registration_contract_table' ) ) { return array(); }
    $rc = $this->registration_contract_table;
    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$rc}'" ) !== $rc ) { return array(); }
    return (array) $wpdb->get_results( $wpdb->prepare(
      "SELECT rc.*, c.name AS company_name
         FROM {$rc} rc
         LEFT JOIN {$this->company_table} c ON c.id = rc.company_id
        WHERE rc.formation_id = %d
          AND COALESCE(rc.start_date, rc.end_date) BETWEEN %s AND %s
        ORDER BY COALESCE(rc.start_date, rc.end_date) ASC",
      $formation_id, $start_sql, $end_sql
    ) );
  }


  // ACDC 3.25.118 — Détail BPF par formation : formateurs intervenant sur les sessions de la période
  /**
   * Retourne les formateurs distincts intervenus sur les sessions d'une formation sur la période.
   * Source : sessions.trainer_id → trainer_table. Le taux horaire HT des formateurs externes est
   * complété depuis trainer_contracts (matché sur la référence de formation) lorsqu'il est connu ;
   * sinon laissé à null (affiché « — ») pour ne rien inventer.
   *
   * @param int    $formation_id    ID de la formation catalogue.
   * @param string $start_sql       Début période AAAA-MM-JJ.
   * @param string $end_sql         Fin période AAAA-MM-JJ.
   * @param string $formation_title Titre de la formation (pour matcher trainer_contracts.formation_ref).
   * @param string $formation_code  Code de la formation (idem).
   * @return array Objets formateur (id, first_name, last_name, is_self_trainer, taux_ht|null).
   */
  private function get_bpf_formation_trainers( $formation_id, $start_sql, $end_sql, $formation_title = '', $formation_code = '' ) {
    global $wpdb;
    $formation_id = (int) $formation_id;
    if ( $formation_id <= 0 || ! $start_sql || ! $end_sql ) { return array(); }
    $trainers = (array) $wpdb->get_results( $wpdb->prepare(
      "SELECT DISTINCT t.id, t.first_name, t.last_name, t.is_self_trainer
         FROM {$this->trainer_table} t
         INNER JOIN {$this->session_table} s ON s.trainer_id = t.id
        WHERE s.formation_id = %d
          AND s.is_draft = 0
          AND COALESCE(s.status,'') NOT IN ('Annulee','Brouillon')
          AND COALESCE(s.start_date, DATE(s.start_at)) BETWEEN %s AND %s
        ORDER BY t.last_name ASC, t.first_name ASC",
      $formation_id, $start_sql, $end_sql
    ) );
    $tc = property_exists( $this, 'trainer_contract_table' ) ? $this->trainer_contract_table : $wpdb->prefix . 'acdc_of_trainer_contracts';
    $tc_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$tc}'" ) === $tc;
    $ftitle = (string) $formation_title;
    $fcode  = (string) $formation_code;
    foreach ( $trainers as $t ) {
      $t->taux_ht = null; // null = inconnu → affiché « — » (jamais 0 trompeur)
      if ( 0 === (int) $t->is_self_trainer && $tc_exists ) {
        $taux = $wpdb->get_var( $wpdb->prepare(
          "SELECT taux_ht FROM {$tc}
            WHERE trainer_id = %d AND taux_ht > 0
              AND COALESCE(date_start, date_end) BETWEEN %s AND %s
              AND ( formation_ref = %s OR ( %s <> '' AND formation_ref = %s ) OR ( %s <> '' AND formation_ref LIKE %s ) )
            ORDER BY date_start DESC LIMIT 1",
          (int) $t->id, $start_sql, $end_sql,
          $ftitle,
          $fcode, $fcode,
          $ftitle, '%' . $wpdb->esc_like( $ftitle ) . '%'
        ) );
        if ( null !== $taux ) { $t->taux_ht = (float) $taux; }
      }
    }
    return $trainers;
  }


  /**
   * ACDC 3.21.76 — Purge one-shot des titres "BPF ANDREA FORMATION" en BDD.
   */
  public function maybe_clean_bpf_andrea_titles() {
    if ( get_option( 'acdc_of_bpf_andrea_cleaned', false ) ) { return; }
    $saved = get_option( 'acdc_of_bpf_records', array() );
    if ( ! is_array( $saved ) ) { $saved = array(); }

    // Récupérer les vraies dates depuis le profil organisme
    $profile       = method_exists( $this, 'get_company_profile_options' ) ? $this->get_company_profile_options() : array();
    $acc_start_raw = ! empty( $profile['accounting_start'] ) ? rtrim( (string) $profile['accounting_start'], '/' ) : '';
    $acc_end_raw   = ! empty( $profile['accounting_end'] )   ? rtrim( (string) $profile['accounting_end'],   '/' ) : '';

    $changed = false;
    foreach ( $saved as $key => $row ) {
      if ( ! is_array( $row ) ) { continue; }
      // Nettoyer les titres Andrea Formation
      $title = (string) ( $row['title'] ?? '' );
      if ( false !== strpos( $title, 'ANDREA FORMATION' ) ) {
        $saved[ $key ]['title']  = 'BPF ' . ( $row['year'] ?? $key );
        $saved[ $key ]['status'] = 'pending';
        $changed = true;
      }
      // Corriger les dates si elles sont encore en 01/01 ou 31/12 et que le profil a d'autres dates
      if ( '' !== $acc_start_raw && '01/01' !== $acc_start_raw ) {
        $year = (string) ( $row['year'] ?? $key );
        $start_month   = (int) substr( $acc_start_raw, 3, 2 );
        $end_month     = (int) substr( $acc_end_raw, 3, 2 );
        $start_day     = (int) substr( $acc_start_raw, 0, 2 );
        $end_day       = (int) substr( $acc_end_raw, 0, 2 );
        $end_year      = ( $end_month < $start_month || ( $end_month === $start_month && $end_day < $start_day ) ) ? ( (int) $year + 1 ) : (int) $year;
        $saved[ $key ]['start_date'] = $acc_start_raw . '/' . $year;
        $saved[ $key ]['end_date']   = $acc_end_raw . '/' . $end_year;
        $changed = true;
      }
    }
    if ( $changed ) {
      update_option( 'acdc_of_bpf_records', $saved, false );
    }
    update_option( 'acdc_of_bpf_andrea_cleaned', true, false );
  }



  private function get_bpf_cerfa_blank_url() {
    return 'https://acdcformation.com/wp-content/uploads/2026/04/cerfa_10443-17.pdf';
  }


  /**
   * ACDC 3.21.78 — Correction one-shot des dates BPF depuis le profil organisme.
   */
  public function maybe_fix_bpf_accounting_dates() {
    if ( get_option( 'acdc_of_bpf_dates_fixed_v3', false ) ) { return; }
    $profile       = method_exists( $this, 'get_company_profile_options' ) ? $this->get_company_profile_options() : array();
    $acc_start_raw = ! empty( $profile['accounting_start'] ) ? rtrim( (string) $profile['accounting_start'], '/' ) : '';
    $acc_end_raw   = ! empty( $profile['accounting_end'] )   ? rtrim( (string) $profile['accounting_end'],   '/' ) : '';
    if ( '' === $acc_start_raw || '' === $acc_end_raw ) {
      update_option( 'acdc_of_bpf_dates_fixed_v2', true, false );
      return;
    }
    $saved = get_option( 'acdc_of_bpf_records', array() );
    if ( ! is_array( $saved ) ) { $saved = array(); }
    $start_month = (int) substr( $acc_start_raw, 3, 2 );
    $end_month   = (int) substr( $acc_end_raw, 3, 2 );
    $start_day   = (int) substr( $acc_start_raw, 0, 2 );
    $end_day     = (int) substr( $acc_end_raw, 0, 2 );
    foreach ( $saved as $key => $row ) {
      if ( ! is_array( $row ) ) { continue; }
      $year     = (int) ( $row['year'] ?? $key );
      $end_year = ( $end_month < $start_month || ( $end_month === $start_month && $end_day < $start_day ) ) ? $year + 1 : $year;
      $saved[ $key ]['start_date'] = $acc_start_raw . '/' . $year;
      $saved[ $key ]['end_date']   = $acc_end_raw . '/' . $end_year;
    }
    update_option( 'acdc_of_bpf_records', $saved, false );
    update_option( 'acdc_of_bpf_dates_fixed_v3', true, false );
  }




  private function get_continuous_improvement_records() {
    $saved = get_option( 'acdc_of_continuous_improvement_records', array() );
    return is_array( $saved ) ? $saved : array();
  }



  private function save_continuous_improvement_records( $records ) {
    update_option( 'acdc_of_continuous_improvement_records', $records, false );
  }




  private function get_learner_deadline_records() {
    $saved = get_option( 'acdc_of_learner_deadline_records', array() );
    return is_array( $saved ) ? $saved : array();
  }



  private function save_learner_deadline_records( $records ) {
    update_option( 'acdc_of_learner_deadline_records', $records, false );
  }




  private function get_ancillary_service_records() {
    $saved = get_option( 'acdc_of_ancillary_service_records', array() );
    return is_array( $saved ) ? $saved : array();
  }



  private function save_ancillary_service_records( $records ) {
    update_option( 'acdc_of_ancillary_service_records', $records, false );
  }
  // ACDC 3.21.75 — Prestations extérieures (journal formateur indépendant pour BPF)

  private function get_external_mission_records() {
    $saved = get_option( 'acdc_of_external_mission_records', array() );
    return is_array( $saved ) ? array_values( $saved ) : array();
  }

  private function save_external_mission_records( $records ) {
    update_option( 'acdc_of_external_mission_records', array_values( $records ), false );
    /* ACDC 3.25.282 — Les indicateurs publics comptent ces prestations depuis
       la 3.25.281 : leur réponse en cache doit tomber avec la saisie, sinon une
       prestation ajoutée n'apparaît sur le site qu'au bout d'une heure — et,
       le cache du site vitrine s'ajoutant au nôtre, d'un jour de plus. Deux
       caches en série transforment une attente acceptable en « ça ne marche
       pas ». */
    delete_transient( 'acdc_of_indicators_global' );
  }

  private function get_external_mission( $id ) {
    foreach ( $this->get_external_mission_records() as $r ) {
      if ( (string) ( $r['id'] ?? '' ) === (string) $id ) {
        return $r;
      }
    }
    return null;
  }

  /**
   * Somme des heures et CA des prestations extérieures sur une période BPF.
   *
   * @param string $start_sql YYYY-MM-DD
   * @param string $end_sql   YYYY-MM-DD
   * @return array { heures_total (stagiaires), heures_formateur (dispensées), ca, nb_stag, nb_missions, rows }
   */
  private function get_external_missions_bpf_summary( $start_sql, $end_sql ) {
    $records          = $this->get_external_mission_records();
    $heures           = 0.0; $heures_formateur = 0.0; $ca = 0.0; $nb_stag = 0; $nb_missions = 0;
    $rows             = array();
    foreach ( $records as $r ) {
      $date_ref = ! empty( $r['start_date'] ) ? (string) $r['start_date'] : ( ! empty( $r['end_date'] ) ? (string) $r['end_date'] : '' );
      if ( '' === $date_ref ) { continue; }
      if ( $date_ref < $start_sql || $date_ref > $end_sql ) { continue; }
      $heures           += (float) ( $r['heures_total'] ?? 0 );
      $heures_formateur += (float) ( $r['heures_par_stagiaire'] ?? 0 );
      $ca               += (float) ( $r['ca_ht'] ?? 0 );
      $nb_stag          += (int) ( $r['nb_stagiaires'] ?? 0 );
      $nb_missions++;
      $rows[] = $r;
    }
    return compact( 'heures', 'heures_formateur', 'ca', 'nb_stag', 'nb_missions', 'rows' );
  }

  /**
   * ACDC 3.21.75 — Import one-shot des 18 prestations Notion.
   * Exécuté une seule fois au premier chargement après 3.21.75.
   */
  public function maybe_import_notion_external_missions() {
    if ( get_option( 'acdc_of_ext_missions_notion_imported', false ) ) { return; }
    $records = array(
      array( 'id' => 'em_notion_001', 'commanditaire' => "K Formation - Cr\u00e9er une page Facebook", 'start_date' => '2025-07-25', 'end_date' => '2025-07-25', 'start_date_display' => '25/07/2025', 'end_date_display' => '25/07/2025', 'nb_stagiaires' => 2, 'heures_par_stagiaire' => 7.0, 'heures_total' => 14.0, 'ca_ht' => 320.0, 'tarif_horaire' => 45.71, 'type_public' => "Ind\u00e9pendant", 'modalite' => "Pr\u00e9sentiel", 'financement' => 'Organisme de formation', 'statut' => 'OK', 'notes' => '', 'created_at' => '2026-05-17 10:00:00' ),
      array( 'id' => 'em_notion_002', 'commanditaire' => "Knowell", 'start_date' => '2025-06-17', 'end_date' => '2025-07-22', 'start_date_display' => '17/06/2025', 'end_date_display' => '22/07/2025', 'nb_stagiaires' => 1, 'heures_par_stagiaire' => 35.0, 'heures_total' => 35.0, 'ca_ht' => 1200.0, 'tarif_horaire' => 34.29, 'type_public' => "Ind\u00e9pendant", 'modalite' => 'Mixte', 'financement' => 'Entreprise', 'statut' => 'OK', 'notes' => '', 'created_at' => '2026-05-17 10:00:00' ),
      array( 'id' => 'em_notion_003', 'commanditaire' => "IA Plus Formation", 'start_date' => '2025-09-02', 'end_date' => '2025-09-23', 'start_date_display' => '02/09/2025', 'end_date_display' => '23/09/2025', 'nb_stagiaires' => 1, 'heures_par_stagiaire' => 15.0, 'heures_total' => 15.0, 'ca_ht' => 814.2, 'tarif_horaire' => 54.28, 'type_public' => "Ind\u00e9pendant", 'modalite' => 'Distanciel', 'financement' => 'Organisme de formation', 'statut' => 'OK', 'notes' => '', 'created_at' => '2026-05-17 10:00:00' ),
      array( 'id' => 'em_notion_004', 'commanditaire' => "K Formation", 'start_date' => '2025-09-04', 'end_date' => '2025-09-04', 'start_date_display' => '04/09/2025', 'end_date_display' => '04/09/2025', 'nb_stagiaires' => 4, 'heures_par_stagiaire' => 3.5, 'heures_total' => 14.0, 'ca_ht' => 240.0, 'tarif_horaire' => 68.57, 'type_public' => "Ind\u00e9pendant", 'modalite' => "Pr\u00e9sentiel", 'financement' => 'Organisme de formation', 'statut' => 'OK', 'notes' => '', 'created_at' => '2026-05-17 10:00:00' ),
      array( 'id' => 'em_notion_005', 'commanditaire' => "SUP\'IPGV T25ASCOM2", 'start_date' => '2025-10-13', 'end_date' => '2026-01-29', 'start_date_display' => '13/10/2025', 'end_date_display' => '29/01/2026', 'nb_stagiaires' => 9, 'heures_par_stagiaire' => 73.5, 'heures_total' => 661.5, 'ca_ht' => 2572.5, 'tarif_horaire' => 35.0, 'type_public' => 'CFA', 'modalite' => "Pr\u00e9sentiel", 'financement' => 'Organisme de formation', 'statut' => 'OK', 'notes' => '', 'created_at' => '2026-05-17 10:00:00' ),
      array( 'id' => 'em_notion_006', 'commanditaire' => "SUP\'IPGV T25BTSCOM", 'start_date' => '2025-10-23', 'end_date' => '2026-06-18', 'start_date_display' => '23/10/2025', 'end_date_display' => '18/06/2026', 'nb_stagiaires' => 8, 'heures_par_stagiaire' => 28.0, 'heures_total' => 224.0, 'ca_ht' => 1260.0, 'tarif_horaire' => 45.0, 'type_public' => 'CFA', 'modalite' => "Pr\u00e9sentiel", 'financement' => 'Organisme de formation', 'statut' => 'OK', 'notes' => '', 'created_at' => '2026-05-17 10:00:00' ),
      array( 'id' => 'em_notion_007', 'commanditaire' => "SUP\'IPGV T25ASCA01", 'start_date' => '2025-12-09', 'end_date' => '2026-01-16', 'start_date_display' => '09/12/2025', 'end_date_display' => '16/01/2026', 'nb_stagiaires' => 8, 'heures_par_stagiaire' => 14.0, 'heures_total' => 112.0, 'ca_ht' => 490.0, 'tarif_horaire' => 35.0, 'type_public' => 'CFA', 'modalite' => "Pr\u00e9sentiel", 'financement' => 'Organisme de formation', 'statut' => 'OK', 'notes' => '', 'created_at' => '2026-05-17 10:00:00' ),
      array( 'id' => 'em_notion_008', 'commanditaire' => "Client exemple", 'start_date' => '2025-07-01', 'end_date' => '2025-07-31', 'start_date_display' => '01/07/2025', 'end_date_display' => '31/07/2025', 'nb_stagiaires' => 1, 'heures_par_stagiaire' => 0.0, 'heures_total' => 0.0, 'ca_ht' => 3400.0, 'tarif_horaire' => 0.0, 'type_public' => "Ind\u00e9pendant", 'modalite' => "Pr\u00e9sentiel", 'financement' => 'Entreprise', 'statut' => 'Incomplet', 'notes' => '', 'created_at' => '2026-05-17 10:00:00' ),
      array( 'id' => 'em_notion_009', 'commanditaire' => "Inelixia", 'start_date' => '2025-10-28', 'end_date' => '2025-11-12', 'start_date_display' => '28/10/2025', 'end_date_display' => '12/11/2025', 'nb_stagiaires' => 2, 'heures_par_stagiaire' => 14.0, 'heures_total' => 28.0, 'ca_ht' => 800.0, 'tarif_horaire' => 57.14, 'type_public' => "Ind\u00e9pendant", 'modalite' => 'Distanciel', 'financement' => 'Organisme de formation', 'statut' => 'OK', 'notes' => '', 'created_at' => '2026-05-17 10:00:00' ),
      array( 'id' => 'em_notion_010', 'commanditaire' => "Isek", 'start_date' => '2025-11-19', 'end_date' => '2025-11-19', 'start_date_display' => '19/11/2025', 'end_date_display' => '19/11/2025', 'nb_stagiaires' => 10, 'heures_par_stagiaire' => 7.0, 'heures_total' => 70.0, 'ca_ht' => 400.0, 'tarif_horaire' => 57.14, 'type_public' => "Ind\u00e9pendant", 'modalite' => 'Distanciel', 'financement' => 'Organisme de formation', 'statut' => 'OK', 'notes' => '', 'created_at' => '2026-05-17 10:00:00' ),
      array( 'id' => 'em_notion_011', 'commanditaire' => "Performa 83", 'start_date' => '2025-11-27', 'end_date' => '2025-11-28', 'start_date_display' => '27/11/2025', 'end_date_display' => '28/11/2025', 'nb_stagiaires' => 2, 'heures_par_stagiaire' => 14.0, 'heures_total' => 28.0, 'ca_ht' => 700.0, 'tarif_horaire' => 50.0, 'type_public' => "Ind\u00e9pendant", 'modalite' => "Pr\u00e9sentiel", 'financement' => 'Organisme de formation', 'statut' => 'OK', 'notes' => '', 'created_at' => '2026-05-17 10:00:00' ),
      array( 'id' => 'em_notion_012', 'commanditaire' => "Performa 83", 'start_date' => '2025-12-04', 'end_date' => '2025-12-05', 'start_date_display' => '04/12/2025', 'end_date_display' => '05/12/2025', 'nb_stagiaires' => 2, 'heures_par_stagiaire' => 14.0, 'heures_total' => 28.0, 'ca_ht' => 700.0, 'tarif_horaire' => 50.0, 'type_public' => "Ind\u00e9pendant", 'modalite' => "Pr\u00e9sentiel", 'financement' => 'Organisme de formation', 'statut' => 'OK', 'notes' => '', 'created_at' => '2026-05-17 10:00:00' ),
      array( 'id' => 'em_notion_013', 'commanditaire' => "Isek", 'start_date' => '2025-12-03', 'end_date' => '2025-12-03', 'start_date_display' => '03/12/2025', 'end_date_display' => '03/12/2025', 'nb_stagiaires' => 8, 'heures_par_stagiaire' => 7.0, 'heures_total' => 56.0, 'ca_ht' => 400.0, 'tarif_horaire' => 57.14, 'type_public' => "Ind\u00e9pendant", 'modalite' => 'Distanciel', 'financement' => 'Organisme de formation', 'statut' => 'OK', 'notes' => '', 'created_at' => '2026-05-17 10:00:00' ),
      array( 'id' => 'em_notion_014', 'commanditaire' => "IA Forma Plus", 'start_date' => '2025-12-15', 'end_date' => '2025-12-16', 'start_date_display' => '15/12/2025', 'end_date_display' => '16/12/2025', 'nb_stagiaires' => 2, 'heures_par_stagiaire' => 11.0, 'heures_total' => 22.0, 'ca_ht' => 597.08, 'tarif_horaire' => 54.28, 'type_public' => "Ind\u00e9pendant", 'modalite' => 'Distanciel', 'financement' => 'Organisme de formation', 'statut' => 'OK', 'notes' => '', 'created_at' => '2026-05-17 10:00:00' ),
      array( 'id' => 'em_notion_015', 'commanditaire' => "Isek", 'start_date' => '2026-03-09', 'end_date' => '2026-03-09', 'start_date_display' => '09/03/2026', 'end_date_display' => '09/03/2026', 'nb_stagiaires' => 3, 'heures_par_stagiaire' => 7.0, 'heures_total' => 21.0, 'ca_ht' => 400.0, 'tarif_horaire' => 57.14, 'type_public' => "Ind\u00e9pendant", 'modalite' => 'Distanciel', 'financement' => 'Organisme de formation', 'statut' => 'OK', 'notes' => '', 'created_at' => '2026-05-17 10:00:00' ),
      array( 'id' => 'em_notion_016', 'commanditaire' => "Isek", 'start_date' => '2026-03-17', 'end_date' => '2026-03-17', 'start_date_display' => '17/03/2026', 'end_date_display' => '17/03/2026', 'nb_stagiaires' => 4, 'heures_par_stagiaire' => 7.0, 'heures_total' => 28.0, 'ca_ht' => 400.0, 'tarif_horaire' => 57.14, 'type_public' => "Ind\u00e9pendant", 'modalite' => 'Distanciel', 'financement' => 'Organisme de formation', 'statut' => 'OK', 'notes' => '', 'created_at' => '2026-05-17 10:00:00' ),
      array( 'id' => 'em_notion_017', 'commanditaire' => "Isek", 'start_date' => '2026-04-09', 'end_date' => '2026-04-09', 'start_date_display' => '09/04/2026', 'end_date_display' => '09/04/2026', 'nb_stagiaires' => 3, 'heures_par_stagiaire' => 7.0, 'heures_total' => 21.0, 'ca_ht' => 400.0, 'tarif_horaire' => 57.14, 'type_public' => "Ind\u00e9pendant", 'modalite' => 'Distanciel', 'financement' => 'Organisme de formation', 'statut' => 'OK', 'notes' => '', 'created_at' => '2026-05-17 10:00:00' ),
      array( 'id' => 'em_notion_018', 'commanditaire' => "Skill Conseil", 'start_date' => '2026-05-13', 'end_date' => '2026-05-27', 'start_date_display' => '13/05/2026', 'end_date_display' => '27/05/2026', 'nb_stagiaires' => 3, 'heures_par_stagiaire' => 14.0, 'heures_total' => 42.0, 'ca_ht' => 1800.0, 'tarif_horaire' => 128.57, 'type_public' => '', 'modalite' => '', 'financement' => '', 'statut' => 'OK', 'notes' => '', 'created_at' => '2026-05-17 10:00:00' ),
    );
    usort( $records, function( $a, $b ) {
      return strcmp( (string) ( $b['start_date'] ?? '' ), (string) ( $a['start_date'] ?? '' ) );
    } );
    $this->save_external_mission_records( $records );
    update_option( 'acdc_of_ext_missions_notion_imported', true, false );
  }

  /* =========================================================
   * ACDC 3.22.18 - H4/H5 : Registre des reclamations
   * ========================================================= */

  private function get_complaints( $filters = array() ) {
    global $wpdb;
    $where = array( '1=1' );
    $params = array();
    if ( ! empty( $filters['status'] ) && 'all' !== $filters['status'] ) {
      $where[] = 'status = %s';
      $params[] = $filters['status'];
    }
    if ( ! empty( $filters['severity'] ) && 'all' !== $filters['severity'] ) {
      $where[] = 'severity = %s';
      $params[] = $filters['severity'];
    }
    if ( ! empty( $filters['type'] ) && 'all' !== $filters['type'] ) {
      $where[] = 'type = %s';
      $params[] = $filters['type'];
    }
    if ( ! empty( $filters['date_from'] ) ) {
      $where[] = 'opened_at >= %s';
      $params[] = $filters['date_from'] . ' 00:00:00';
    }
    if ( ! empty( $filters['date_to'] ) ) {
      $where[] = 'opened_at <= %s';
      $params[] = $filters['date_to'] . ' 23:59:59';
    }
    if ( ! empty( $filters['registration_id'] ) ) {
      $where[] = 'registration_id = %d';
      $params[] = (int) $filters['registration_id'];
    }
    $sql = 'SELECT * FROM ' . $this->complaint_table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY opened_at DESC';
    if ( ! empty( $params ) ) {
      return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
    }
    return $wpdb->get_results( $sql );
  }

  private function get_complaint_by_id( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . $this->complaint_table . ' WHERE id = %d', (int) $id ) );
  }

  private function count_open_complaints() {
    global $wpdb;
    return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->complaint_table . ' WHERE status IN (\'ouverte\',\'en_cours\')' );
  }

  private function save_complaint( $data, $id = 0 ) {
    global $wpdb;
    $now = current_time( 'mysql' );
    $row = array(
      'registration_id'    => ! empty( $data['registration_id'] ) ? (int) $data['registration_id'] : null,
      'session_id'         => ! empty( $data['session_id'] ) ? (int) $data['session_id'] : null,
      'source'             => sanitize_text_field( isset( $data['source'] ) ? $data['source'] : 'apprenant' ),
      'type'               => sanitize_text_field( isset( $data['type'] ) ? $data['type'] : 'reclamation' ),
      'summary'            => sanitize_textarea_field( isset( $data['summary'] ) ? $data['summary'] : '' ),
      'severity'           => sanitize_text_field( isset( $data['severity'] ) ? $data['severity'] : 'mineure' ),
      'status'             => sanitize_text_field( isset( $data['status'] ) ? $data['status'] : 'ouverte' ),
      'assigned_to'        => sanitize_text_field( isset( $data['assigned_to'] ) ? $data['assigned_to'] : '' ),
      'corrective_action'  => sanitize_textarea_field( isset( $data['corrective_action'] ) ? $data['corrective_action'] : '' ),
      'improvement_action' => sanitize_textarea_field( isset( $data['improvement_action'] ) ? $data['improvement_action'] : '' ),
      'improvement_deadline' => ! empty( $data['improvement_deadline'] ) ? sanitize_text_field( $data['improvement_deadline'] ) : null,
      'effectiveness_eval' => sanitize_textarea_field( isset( $data['effectiveness_eval'] ) ? $data['effectiveness_eval'] : '' ),
      'opened_at'          => ! empty( $data['opened_at'] ) ? sanitize_text_field( $data['opened_at'] ) : $now,
      'updated_at'         => $now,
    );
    if ( 'cloturee' === $row['status'] ) {
      $row['closed_at'] = $now;
    }
    if ( $id > 0 ) {
      $wpdb->update( $this->complaint_table, $row, array( 'id' => $id ), null, array( '%d' ) );
      $complaint_id = $id;
    } else {
      $row['created_at'] = $now;
      $wpdb->insert( $this->complaint_table, $row );
      $complaint_id = (int) $wpdb->insert_id;
    }
    if ( 'grave' === $row['severity'] && $complaint_id > 0 ) {
      $this->maybe_create_improvement_from_complaint( $complaint_id, $row );
    }
    return $complaint_id;
  }

  private function acknowledge_complaint( $id ) {
    global $wpdb;
    $now = current_time( 'mysql' );
    $existing = $this->get_complaint_by_id( $id );
    if ( ! $existing ) { return false; }
    if ( ! empty( $existing->acknowledged_at ) ) { return true; }
    $wpdb->update( $this->complaint_table, array( 'acknowledged_at' => $now, 'updated_at' => $now ), array( 'id' => (int) $id ), null, array( '%d' ) );
    return true;
  }

  private function delete_complaint( $id ) {
    global $wpdb;
    return $wpdb->delete( $this->complaint_table, array( 'id' => (int) $id ), array( '%d' ) );
  }

  private function maybe_create_improvement_from_complaint( $complaint_id, $row ) {
    global $wpdb;
    $linked = $wpdb->get_var( $wpdb->prepare( 'SELECT improvement_id FROM ' . $this->complaint_table . ' WHERE id = %d', (int) $complaint_id ) );
    if ( ! empty( $linked ) ) { return; }
    $records = $this->get_continuous_improvement_records();
    $new_id = 'ci_complaint_' . $complaint_id . '_' . time();
    $summary_short = ! empty( $row['summary'] ) ? mb_substr( $row['summary'], 0, 80 ) : '';
    $records[ $new_id ] = array(
      'id'            => $new_id,
      'title'         => 'Reclamation #' . $complaint_id . ( $summary_short ? ' - ' . $summary_short : '' ),
      'type'          => 'reclamation',
      'incident_type' => 'Reclamation',
      'source'        => 'complaint_' . $complaint_id,
      'status'        => 'A traiter',
      'priority'      => 'haute',
      'description'   => ! empty( $row['improvement_action'] ) ? $row['improvement_action'] : ( ! empty( $row['corrective_action'] ) ? $row['corrective_action'] : 'Action corrective suite a reclamation grave.' ),
      'deadline'      => ! empty( $row['improvement_deadline'] ) ? $row['improvement_deadline'] : '',
      'created_at'    => current_time( 'mysql' ),
    );
    $this->save_continuous_improvement_records( $records );
    /* Stocker le new_id (string) dans improvement_id via maybe_add_table_column text — fallback : stocker dans une option */
    update_option( 'acdc_of_complaint_improvement_' . $complaint_id, $new_id, false );
    $wpdb->update( $this->complaint_table, array( 'improvement_id' => (int) $complaint_id, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $complaint_id ), null, array( '%d' ) );
  }

  /* =========================================================
   * ACDC 3.24.6 - R1 : Conseil de perfectionnement (ind. 20)
   * ========================================================= */

  private function get_perfectionnement_members() {
    $saved = get_option( 'acdc_of_cp_members', array() );
    return is_array( $saved ) ? array_values( $saved ) : array();
  }

  private function save_perfectionnement_members( $records ) {
    update_option( 'acdc_of_cp_members', array_values( $records ), false );
  }

  private function get_perfectionnement_meetings() {
    $saved = get_option( 'acdc_of_cp_meetings', array() );
    return is_array( $saved ) ? array_values( $saved ) : array();
  }

  private function save_perfectionnement_meetings( $records ) {
    update_option( 'acdc_of_cp_meetings', array_values( $records ), false );
  }

  private function get_perfectionnement_meeting( $id ) {
    foreach ( $this->get_perfectionnement_meetings() as $m ) {
      if ( (string) ( $m['id'] ?? '' ) === (string) $id ) {
        return $m;
      }
    }
    return null;
  }

  /**
   * Retourne le statut de conformité indicateur 20.
   * 'green'  = dernière réunion < 12 mois
   * 'orange' = dernière réunion > 12 mois
   * 'red'    = aucune réunion enregistrée
   */
  private function get_perfectionnement_compliance_status() {
    $meetings = $this->get_perfectionnement_meetings();
    if ( empty( $meetings ) ) {
      return 'red';
    }
    $latest = '';
    foreach ( $meetings as $m ) {
      $d = (string) ( $m['date'] ?? '' );
      if ( $d > $latest ) {
        $latest = $d;
      }
    }
    if ( '' === $latest ) {
      return 'red';
    }
    $diff = ( current_time( 'timestamp' ) - strtotime( $latest ) ) / ( 365 * 24 * 3600 );
    return $diff <= 1 ? 'green' : 'orange';
  }

  /* =========================================================
   * ACDC 3.24.9 - R3 : Réseau partenaires PSH (ind. 26)
   * ========================================================= */

  private function get_psh_partners() {
    $saved = get_option( 'acdc_of_psh_partners', array() );
    return is_array( $saved ) ? array_values( $saved ) : array();
  }

  private function save_psh_partners( $records ) {
    update_option( 'acdc_of_psh_partners', array_values( $records ), false );
  }

  private function get_psh_partner( $id ) {
    foreach ( $this->get_psh_partners() as $p ) {
      if ( (string) ( $p['id'] ?? '' ) === (string) $id ) {
        return $p;
      }
    }
    return null;
  }

  private function get_psh_partner_types() {
    return array(
      'mdph'           => 'MDPH',
      'cap_emploi'     => 'Cap emploi',
      'agefiph'        => 'AGEFIPH',
      'fiphfp'         => 'FIPHFP',
      'france_travail' => 'France Travail',
      'mission_locale' => 'Mission Locale',
      'apec'           => 'APEC',
      'onisep'         => 'ONISEP',
      'esat'           => 'ESAT',
      'samsah'         => 'SAMSAH / SAVS',
      'association'    => 'Association',
      'autre'          => 'Autre',
    );
  }

  /**
   * Statut conformité indicateur 26.
   * 'green' = au moins 1 partenaire, 'red' = aucun.
   */
  private function get_psh_compliance_status() {
    return count( $this->get_psh_partners() ) > 0 ? 'green' : 'red';
  }

  /**
   * ACDC 3.24.10 — Import one-shot partenaires PSH nationaux + locaux Var.
   * Sources : monparcourshandicap.gouv.fr + esterelcotedazur-agglo.fr/annuaire-handicap
   */
  public function maybe_import_psh_default_partners() {
    if ( get_option( 'acdc_of_psh_partners_imported_v1', false ) ) { return; }
    $existing = $this->get_psh_partners();
    if ( ! empty( $existing ) ) {
      update_option( 'acdc_of_psh_partners_imported_v1', true, false );
      return;
    }
    $records = array(
      // ── NATIONAL ──────────────────────────────────────────────────────────
      array( 'id' => 'psh_nat_001', 'scope' => 'national', 'name' => 'AGEFIPH', 'type' => 'agefiph', 'region' => 'National', 'contact' => '', 'phone' => '0800 11 10 09', 'email' => '', 'notes' => 'Fonds pour l\'insertion professionnelle des personnes handicapées. Aides aux OF et aux employeurs. Site : agefiph.fr' ),
      array( 'id' => 'psh_nat_002', 'scope' => 'national', 'name' => 'FIPHFP', 'type' => 'fiphfp', 'region' => 'National', 'contact' => '', 'phone' => '', 'email' => '', 'notes' => 'Fonds pour l\'insertion des personnes handicapées dans la Fonction publique. Site : fiphfp.fr' ),
      array( 'id' => 'psh_nat_003', 'scope' => 'national', 'name' => 'Cap emploi (réseau Cheops)', 'type' => 'cap_emploi', 'region' => 'National', 'contact' => '', 'phone' => '', 'email' => '', 'notes' => 'Réseau de 98 organismes de placement spécialisés. Accompagnement vers l\'emploi et maintien en poste des personnes handicapées. Site : cheops-ops.org' ),
      array( 'id' => 'psh_nat_004', 'scope' => 'national', 'name' => 'France Travail', 'type' => 'france_travail', 'region' => 'National', 'contact' => '', 'phone' => '3949', 'email' => '', 'notes' => 'Opérateur du Service public de l\'emploi. Accompagnement et indemnisation des demandeurs d\'emploi, dont PSH. Site : francetravail.fr' ),
      array( 'id' => 'psh_nat_005', 'scope' => 'national', 'name' => 'APEC', 'type' => 'apec', 'region' => 'National', 'contact' => '', 'phone' => '', 'email' => '', 'notes' => 'Accompagnement des cadres et jeunes diplômés, dont PSH, dans leur projet professionnel. Site : apec.fr' ),
      array( 'id' => 'psh_nat_006', 'scope' => 'national', 'name' => 'ONISEP', 'type' => 'onisep', 'region' => 'National', 'contact' => '', 'phone' => '', 'email' => '', 'notes' => 'Information sur les formations et métiers, ressources handicap pour les parcours d\'orientation. Site : onisep.fr' ),
      // ── LOCAL VAR / ESTÉREL ───────────────────────────────────────────────
      array( 'id' => 'psh_loc_001', 'scope' => 'local_var', 'name' => 'MDPH du Var', 'type' => 'mdph', 'region' => 'Var (83)', 'contact' => '', 'phone' => '04 94 09 23 84', 'email' => 'mdph@var.fr', 'notes' => 'Maison Départementale des Personnes Handicapées du Var. Reconnaissance RQTH, dossiers compensation, orientation professionnelle.' ),
      array( 'id' => 'psh_loc_002', 'scope' => 'local_var', 'name' => 'Cap Emploi 83', 'type' => 'cap_emploi', 'region' => 'Var (83)', 'contact' => '', 'phone' => '', 'email' => '', 'notes' => 'Organisme de placement spécialisé du Var. Accompagnement vers l\'emploi et maintien en poste des travailleurs handicapés. Présent sur Fréjus / Saint-Raphaël.' ),
      array( 'id' => 'psh_loc_003', 'scope' => 'local_var', 'name' => 'France Travail — Agence Fréjus / Saint-Raphaël', 'type' => 'france_travail', 'region' => 'Var (83) — Estérel', 'contact' => '', 'phone' => '3949', 'email' => '', 'notes' => 'Agence locale France Travail sur le bassin Fréjus / Saint-Raphaël. Accompagnement demandeurs d\'emploi PSH.' ),
      array( 'id' => 'psh_loc_004', 'scope' => 'local_var', 'name' => 'Mission Locale Estérel', 'type' => 'mission_locale', 'region' => 'Var (83) — Estérel', 'contact' => '', 'phone' => '', 'email' => '', 'notes' => 'Accompagnement des jeunes 16-25 ans, dont PSH, vers l\'emploi et la formation. Territoire Estérel Côte d\'Azur.' ),
      array( 'id' => 'psh_loc_005', 'scope' => 'local_var', 'name' => 'Umane — DEA (Dispositif Emploi Accompagné)', 'type' => 'association', 'region' => 'Var (83)', 'contact' => '', 'phone' => '', 'email' => '', 'notes' => 'Dispositif Emploi Accompagné : soutien à l\'insertion et au maintien en emploi des personnes en situation de handicap psychique et cognitif.' ),
      array( 'id' => 'psh_loc_006', 'scope' => 'local_var', 'name' => 'Umane — ESAT Le Bercail', 'type' => 'esat', 'region' => 'Var (83)', 'contact' => '', 'phone' => '', 'email' => '', 'notes' => 'Établissement ou Service d\'Aide par le Travail. Accueil de travailleurs handicapés, activités professionnelles adaptées.' ),
      array( 'id' => 'psh_loc_007', 'scope' => 'local_var', 'name' => 'Itinova — ESAT Les Mimosas', 'type' => 'esat', 'region' => 'Var (83)', 'contact' => '', 'phone' => '', 'email' => '', 'notes' => 'ESAT sur le territoire Estérel. Accueil de travailleurs handicapés. Possibilité de partenariats formation.' ),
      array( 'id' => 'psh_loc_008', 'scope' => 'local_var', 'name' => 'ADSEAAV — ESAT Hors les Murs', 'type' => 'esat', 'region' => 'Var (83)', 'contact' => '', 'phone' => '', 'email' => '', 'notes' => 'Association Départementale pour la Sauvegarde de l\'Enfant et de l\'Adulte du Var. Dispositif ESAT Hors les Murs.' ),
      array( 'id' => 'psh_loc_009', 'scope' => 'local_var', 'name' => 'Isatis — SAMSAH', 'type' => 'samsah', 'region' => 'Var (83)', 'contact' => '', 'phone' => '', 'email' => '', 'notes' => 'Service d\'Accompagnement Médico-Social pour Adultes Handicapés. Suivi de personnes handicapées dans leur projet de vie et professionnel.' ),
      array( 'id' => 'psh_loc_010', 'scope' => 'local_var', 'name' => 'Parih 83', 'type' => 'association', 'region' => 'Var (83)', 'contact' => '', 'phone' => '', 'email' => '', 'notes' => 'Association locale d\'accompagnement et d\'information des personnes en situation de handicap dans le Var.' ),
      array( 'id' => 'psh_loc_011', 'scope' => 'local_var', 'name' => 'Réseau Passerelles', 'type' => 'association', 'region' => 'Var (83) — Estérel', 'contact' => '', 'phone' => '', 'email' => '', 'notes' => 'Réseau de structures d\'accompagnement et de transition vers l\'emploi sur le territoire Estérel Côte d\'Azur.' ),
    );
    $this->save_psh_partners( $records );
    update_option( 'acdc_of_psh_partners_imported_v1', true, false );
  }

  private function get_complaint_source_options() {
    return array( 'apprenant' => 'Apprenant', 'commanditaire' => 'Commanditaire', 'financeur' => 'Financeur', 'formateur' => 'Formateur', 'organisme' => 'Organisme' );
  }
  private function get_complaint_type_options() {
    return array( 'reclamation' => 'Reclamation', 'incident' => 'Incident', 'suggestion' => 'Suggestion', 'alea' => 'Alea' );
  }
  private function get_complaint_severity_options() {
    return array( 'mineure' => 'Mineure', 'moderee' => 'Moderee', 'grave' => 'Grave' );
  }
  private function get_complaint_status_options() {
    return array( 'ouverte' => 'Ouverte', 'en_cours' => 'En cours', 'cloturee' => 'Clôturée' );
  }

}
