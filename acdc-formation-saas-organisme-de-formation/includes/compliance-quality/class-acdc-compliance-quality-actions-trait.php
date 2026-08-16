<?php
/**
 * ACDC Conformité / Qualité annexe — actions et traitements
 *
 * Extraction incrémentale des traitements, sauvegardes et actions du module BPF, amélioration continue, échéances apprenants, veille et prestations annexes.
 * Version : 3.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Compliance_Quality_Actions_Trait {


  public function handle_generate_bpf_prefilled() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_generate_bpf_prefilled' );
    global $wpdb;

    $year  = isset( $_REQUEST['bpf_year'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['bpf_year'] ) ) : date_i18n( 'Y' );
    $start = isset( $_REQUEST['start_date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['start_date'] ) ) : '01/01/' . $year;
    $end   = isset( $_REQUEST['end_date'] )   ? sanitize_text_field( wp_unslash( $_REQUEST['end_date'] ) )   : '31/12/' . $year;

    // Convertir dd/mm/YYYY → YYYY-MM-DD pour les requêtes SQL
    $start_sql = '';
    $end_sql   = '';
    if ( preg_match( '#^(\d{2})/(\d{2})/(\d{4})$#', $start, $m ) ) {
      $start_sql = $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    if ( preg_match( '#^(\d{2})/(\d{2})/(\d{4})$#', $end, $m ) ) {
      $end_sql = $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    if ( ! $start_sql || ! $end_sql ) {
      $this->redirect_to_portal( 'bpf', 'Dates invalides. Format attendu : JJ/MM/AAAA.', 'error' );
      return;
    }

    // ── Cadre E — Formateurs et heures dispensées ──────────────────────────
    // ACDC 3.22.14 — BLOC D.1 : requête sans DISTINCT → une ligne par session → somme réelle des heures
    // Avant 3.22.14 : SELECT DISTINCT t.id → 1 seule occurrence par formateur → heures sous-estimées
    $trainers_on_period = (array) $wpdb->get_results( $wpdb->prepare(
      "SELECT t.id, t.is_self_trainer, COALESCE(f.duration, '') AS formation_duration FROM {$this->trainer_table} t INNER JOIN {$this->session_table} s ON s.trainer_id = t.id LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id WHERE COALESCE(s.status,'') NOT IN ('Annulee','Brouillon') AND s.is_draft = 0 AND COALESCE(s.start_date, DATE(s.start_at)) BETWEEN %s AND %s",
      $start_sql, $end_sql
    ) );

    $nb_internes  = 0; $nb_externes = 0;
    $h_internes   = 0.0; $h_externes = 0.0;
    // Tableaux de déduplication : compter chaque formateur une seule fois pour nb_, mais sommer toutes ses sessions pour h_
    $seen_internes = array(); $seen_externes = array();

    foreach ( $trainers_on_period as $t ) {
      $tid    = (int) $t->id;
      $is_int = (int) $t->is_self_trainer;
      // Extraire la durée en heures (ex. "14h", "14", "14 heures", "2 jours")
      $dur_raw = (string) $t->formation_duration;
      $dur_h = 0.0;
      if ( preg_match( '/(\d+(?:[.,]\d+)?)\s*(?:h(?:eure)?s?|heures?)/i', $dur_raw, $dm ) ) {
        $dur_h = (float) str_replace( ',', '.', $dm[1] );
      } elseif ( preg_match( '/(\d+(?:[.,]\d+)?)\s*j(?:ours?)?/i', $dur_raw, $dm ) ) {
        $dur_h = (float) str_replace( ',', '.', $dm[1] ) * 7.0;
      } elseif ( preg_match( '/^(\d+(?:[.,]\d+)?)$/', trim( $dur_raw ), $dm ) ) {
        $dur_h = (float) str_replace( ',', '.', $dm[1] );
      }
      if ( $is_int ) {
        if ( ! in_array( $tid, $seen_internes, true ) ) { $nb_internes++; $seen_internes[] = $tid; }
        $h_internes += $dur_h;
      } else {
        if ( ! in_array( $tid, $seen_externes, true ) ) { $nb_externes++; $seen_externes[] = $tid; }
        $h_externes += $dur_h;
      }
    }

    // ACDC 3.22.14 — BLOC D.2 : compléter Cadre E ligne 2 depuis table trainer_contracts (formateurs externes hors séances)
    $tc_table = property_exists( $this, 'trainer_contract_table' ) ? $this->trainer_contract_table : $wpdb->prefix . 'acdc_of_trainer_contracts';
    $tc_table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$tc_table}'" ) === $tc_table;
    if ( $tc_table_exists ) {
      $tc_ext_rows = (array) $wpdb->get_results( $wpdb->prepare(
        "SELECT tc.trainer_id, SUM(tc.nb_heures) AS total_heures FROM {$tc_table} tc INNER JOIN {$this->trainer_table} tr ON tr.id = tc.trainer_id WHERE tr.is_self_trainer = 0 AND tc.date_start BETWEEN %s AND %s GROUP BY tc.trainer_id",
        $start_sql, $end_sql
      ) );
      foreach ( $tc_ext_rows as $tcr ) {
        $tc_tid = (int) $tcr->trainer_id;
        // Ajouter seulement si ce formateur externe n'était PAS déjà dans une séance
        if ( ! in_array( $tc_tid, $seen_externes, true ) ) {
          $nb_externes++; $seen_externes[] = $tc_tid;
        }
        // Heures toujours additionnées (un formateur peut avoir séances + contrats hors séances)
        $h_externes += (float) $tcr->total_heures;
      }
    }
    /* ACDC 3.25.270 — LE BPF DÉCLARAIT ZÉRO STAGIAIRE.
       Cinq chiffres du bilan — nombre de stagiaires, heures-stagiaires,
       catégories socioprofessionnelles, objectifs, spécialités — étaient
       calculés par cinq requêtes indépendantes qui comptaient toutes les
       apprenants par `learner.session_id`. Cette colonne ne peut désigner
       qu'UNE séance, et la convention ne la renseigne jamais puisque c'est elle
       qui crée les séances : le bilan que l'on déclare à la DREETS ne voyait
       donc que les inscriptions faites à l'ancienne.
       Deux corrections en une. Le rattachement passe par le résolveur commun,
       celui qui connaît les trois chemins. Et le périmètre — les séances de la
       période — est lu UNE FOIS, les cinq chiffres en découlant : cinq requêtes
       séparées sur la même vérité finissent toujours par se contredire, et un
       bilan qui se contredit ne se corrige plus, il se refait.
       Le périmètre lui-même ne bouge pas : mêmes séances, mêmes exclusions.
       Seule change la façon de compter qui était là. */
    $bpf_sessions = (array) $wpdb->get_results( $wpdb->prepare(
      "SELECT s.id, s.formation_id, s.start_date, s.start_at,
              COALESCE(f.duration,'') AS formation_duration,
              COALESCE(f.service_objective,'') AS service_objective,
              COALESCE(f.specialty,'') AS specialty,
              COALESCE(f.include_bpf,0) AS include_bpf
         FROM {$this->session_table} s
         LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id
        WHERE s.is_draft = 0
          AND COALESCE(s.start_date, DATE(s.start_at)) BETWEEN %s AND %s",
      $start_sql, $end_sql
    ) );

    $bpf_duree_h = function( $raw ) {
      $raw = (string) $raw;
      if ( preg_match( '/(\d+(?:[.,]\d+)?)\s*(?:h(?:eure)?s?|heures?)/i', $raw, $dm ) ) {
        return (float) str_replace( ',', '.', $dm[1] );
      }
      if ( preg_match( '/(\d+(?:[.,]\d+)?)\s*j(?:ours?)?/i', $raw, $dm ) ) {
        return (float) str_replace( ',', '.', $dm[1] ) * 7.0;
      }
      if ( preg_match( '/^(\d+(?:[.,]\d+)?)$/', trim( $raw ), $dm ) ) {
        return (float) str_replace( ',', '.', $dm[1] );
      }
      return 0.0;
    };

    $bpf_learner_ids         = array();   // Tous les stagiaires de la période.
    $heures_stagiaires_total = 0.0;
    $bpf_par_objectif        = array();   // service_objective => [ id => true ]
    $bpf_par_specialite      = array();   // specialty => [ 'learners' => [], 'sessions' => 0 ]

    foreach ( $bpf_sessions as $bs ) {
      $ids = $this->acdc_session_learner_ids( $bs );
      if ( empty( $ids ) ) {
        continue;
      }
      foreach ( $ids as $lid ) {
        $bpf_learner_ids[ (int) $lid ] = true;
      }

      /* Heures-stagiaires : ce que les apprenants ont consommé sur CETTE
         séance. La durée est celle de la formation, comme avant. */
      $heures_stagiaires_total += count( $ids ) * $bpf_duree_h( $bs->formation_duration );

      /* Les cadres F3 et F4 ne portent que sur les formations déclarées. */
      if ( 1 !== (int) $bs->include_bpf ) {
        continue;
      }
      $obj = (string) $bs->service_objective;
      if ( ! isset( $bpf_par_objectif[ $obj ] ) ) { $bpf_par_objectif[ $obj ] = array(); }
      $spe = (string) $bs->specialty;
      if ( ! isset( $bpf_par_specialite[ $spe ] ) ) {
        $bpf_par_specialite[ $spe ] = array( 'learners' => array(), 'sessions' => 0 );
      }
      $bpf_par_specialite[ $spe ]['sessions']++;
      foreach ( $ids as $lid ) {
        $bpf_par_objectif[ $obj ][ (int) $lid ]                = true;
        $bpf_par_specialite[ $spe ]['learners'][ (int) $lid ]  = true;
      }
    }

    $nb_apprenants_total = count( $bpf_learner_ids );

    /* ACDC 3.25.283 — LES PRESTATIONS EXTÉRIEURES ENTRENT, OU N'ENTRENT PAS.
     *
     * Elles étaient agrégées d'office dans ce BPF depuis la 3.21.75, et c'était
     * juste : un seul numéro de déclaration d'activité, donc une seule
     * déclaration couvrant les deux activités.
     *
     * Ça cesse de l'être le jour où une seconde structure est immatriculée. Les
     * interventions faites pour d'autres organismes relèvent alors du numéro du
     * formateur, pas de celui de la structure — et les déclarer ici les ferait
     * compter deux fois.
     *
     * L'interrupteur est ALLUMÉ par défaut : installer cette version ne change
     * donc rien à un BPF déjà produit. Il s'éteint le jour de l'immatriculation,
     * d'un geste, sans qu'il ait fallu deviner une date à l'avance.
     *
     * Neutraliser la source plutôt que ses six usages : une agrégation à zéro
     * traverse tout le calcul sans qu'aucune ligne n'ait à connaître la règle,
     * et un septième usage ajouté demain sera couvert sans qu'on y pense. */
    $inclure_prestations = ( '0' !== (string) get_option( 'acdc_of_bpf_include_external', '1' ) );
    $ext_vide = array( 'heures' => 0, 'heures_formateur' => 0, 'ca' => 0, 'nb_stag' => 0, 'nb_missions' => 0, 'rows' => array() );
    $ext_summary = ( $inclure_prestations && method_exists( $this, 'get_external_missions_bpf_summary' ) )
      ? $this->get_external_missions_bpf_summary( $start_sql, $end_sql )
      : $ext_vide;

    // Cadre E : les prestations extérieures (formateur indépendant = vous-même dispensant hors OF)
    // Vous êtes "Personnes de votre organisme dispensant des heures" → nb_internes / h_internes
    // h_internes = heures DISPENSÉES par le formateur (heures_par_stagiaire × nb_missions)
    // pas heures_total qui compte les heures × nb_stagiaires
    $nb_internes   += $ext_summary['nb_missions'] > 0 ? 1 : 0;
    $h_internes    += isset( $ext_summary['heures_formateur'] ) ? $ext_summary['heures_formateur'] : $ext_summary['heures'];
    $nb_apprenants_total += $ext_summary['nb_stag'];

    // ── ACDC 3.22.14 — BLOC E : Cadre C lignes 1 et 9 depuis registration_contracts ──────────────
    // C1 = entreprises commanditaires (formation salariés) | C9 = particuliers à leurs frais
    $c1_val = 0.0; $c9_val = 0.0;
    if ( property_exists( $this, 'registration_contract_table' ) ) {
      $rc_table = $this->registration_contract_table;
      $rc_table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$rc_table}'" ) === $rc_table;
      if ( $rc_table_exists ) {
        $rc_rows = (array) $wpdb->get_results( $wpdb->prepare(
          "SELECT commanditaire_type, SUM(CAST(REPLACE(price_ht, ',', '.') AS DECIMAL(10,2))) AS total_ht FROM {$rc_table} WHERE start_date BETWEEN %s AND %s AND price_ht != '' AND price_ht IS NOT NULL GROUP BY commanditaire_type",
          $start_sql, $end_sql
        ) );
        foreach ( $rc_rows as $rcr ) {
          $ctype = strtolower( (string) $rcr->commanditaire_type );
          $amt   = (float) $rcr->total_ht;
          if ( false !== strpos( $ctype, 'entreprise' ) || false !== strpos( $ctype, 'opco' ) || false !== strpos( $ctype, 'salarié' ) || false !== strpos( $ctype, 'salarie' ) ) {
            $c1_val += $amt;
          } else {
            // Independant, particulier, autre → C9
            $c9_val += $amt;
          }
        }
      }
    }

    // ── ACDC 3.22.14 — BLOC F : Cadre D depuis trainer_contracts (honoraires formateurs externes) ──
    // d_achats = SUM(montant_ht) contrats formateurs externes sur la période
    $d_achats_val = 0.0; $d_salaires_val = 0.0;
    if ( $tc_table_exists ) {
      $d_row = $wpdb->get_row( $wpdb->prepare(
        "SELECT SUM(tc.montant_ht) AS total_achats FROM {$tc_table} tc INNER JOIN {$this->trainer_table} tr ON tr.id = tc.trainer_id WHERE tr.is_self_trainer = 0 AND tc.date_start BETWEEN %s AND %s",
        $start_sql, $end_sql
      ) );
      if ( $d_row && null !== $d_row->total_achats ) {
        $d_achats_val = (float) $d_row->total_achats;
      }
    }
    $d_total_val = $d_salaires_val + $d_achats_val;

    // ── Cadre F1 — Types de stagiaires ────────────────────────────────────
    /* Les mêmes stagiaires que ci-dessus, relus pour leur catégorie : le total
       du cadre F1 ne peut donc pas différer du nombre déclaré plus haut. */
    $learners_period = array();
    if ( ! empty( $bpf_learner_ids ) ) {
      $f1_ids = array_keys( $bpf_learner_ids );
      $f1_ph  = implode( ',', array_fill( 0, count( $f1_ids ), '%d' ) );
      $learners_period = (array) $wpdb->get_results( $wpdb->prepare(
        "SELECT socio_category, is_france_travail FROM {$this->learner_table} WHERE id IN ({$f1_ph})",
        $f1_ids
      ) );
    }

    $f1_salaries = 0; $f1_apprentis = 0; $f1_demandeurs = 0; $f1_independants = 0; $f1_particuliers = 0; $f1_autres = 0;
    foreach ( $learners_period as $l ) {
      $cat = strtolower( (string) $l->socio_category );
      $ft  = strtolower( (string) $l->is_france_travail );
      if ( false !== strpos( $ft, 'oui' ) || false !== strpos( $cat, 'france travail' ) || false !== strpos( $cat, 'demandeur' ) ) {
        $f1_demandeurs++;
      } elseif ( false !== strpos( $cat, 'apprenti' ) ) {
        $f1_apprentis++;
      } elseif ( false !== strpos( $cat, 'salari' ) ) {
        $f1_salaries++;
      } elseif ( false !== strpos( $cat, 'particulier' ) ) {
        $f1_particuliers++;
      } elseif ( false !== strpos( $cat, 'ind' ) || false !== strpos( $cat, 'dirigeant' ) || false !== strpos( $cat, 'artisan' ) ) {
        $f1_independants++;
      } else {
        $f1_autres++;
      }
    }

    // ── Cadre F3 — Objectif général des prestations ───────────────────────
    $obj_map = array(
      'rncp'               => 'Certification professionnelle (RNCP)',
      'rs'                 => 'Certification professionnelle (RS)',
      'cqp_non_inscrit'    => 'Acquisition de compétences (CQP non inscrit)',
      'autre_pro'          => 'Perfectionnement / maintien des compétences',
      'bilan_competences'  => 'Bilan de compétences',
      'vae'                => 'Validation des acquis (VAE)',
    );
    $f3_data = array();
    foreach ( $bpf_par_objectif as $objectif => $learner_set ) {
      $key = sanitize_key( (string) $objectif );
      $label = isset( $obj_map[ $key ] ) ? $obj_map[ $key ] : ( '' !== $key ? ucfirst( $key ) : 'Non renseigné' );
      $f3_data[ $label ] = isset( $f3_data[ $label ] ) ? $f3_data[ $label ] + count( $learner_set ) : count( $learner_set );
    }

    // ── Cadre F4 — Spécialités NSF ────────────────────────────────────────
    $f4_rows = array();
    foreach ( $bpf_par_specialite as $specialite => $bloc ) {
      $f4_rows[] = (object) array(
        'specialty'     => (string) $specialite,
        'nb_apprenants' => count( $bloc['learners'] ),
        'nb_sessions'   => (int) $bloc['sessions'],
      );
    }
    usort( $f4_rows, static function( $a, $b ) {
      return (int) $b->nb_apprenants <=> (int) $a->nb_apprenants;
    } );

    // ── Génération PDF récapitulatif ───────────────────────────────────────
    if ( method_exists( $this, '_build_simple_pdf_string' ) ) {
      $profile = $this->get_company_profile_options();
      $org_name = ! empty( $profile['enterprise'] ) ? (string) $profile['enterprise'] : 'ACDC-Formation';
      $pages = array();
      $pw = 595; $ph = 842; $ml = 40; $mr = 555; $cw = 515;
      $navy = '#0C2D52'; $gold = '#C5A253'; $ink = '#1f2937'; $muted = '#6b7280'; $line = '#d7dde6'; $white = '#FFFFFF';
      $green = '#22c55e'; $soft = '#f8fafc';
      $p = array();
      $p[] = array( 'type' => 'page_meta', 'width' => $pw, 'height' => $ph );
      $p[] = array( 'type' => 'rect', 'x' => 0, 'y' => $ph - 50, 'width' => $pw, 'height' => 50, 'fill_color' => '#0C2D52' );
      $p[] = array( 'text' => 'BILAN PÉDAGOGIQUE ET FINANCIER', 'x' => $ml, 'y' => $ph - 28, 'size' => 14, 'font' => 'Helvetica-Bold', 'color' => '#FFFFFF' );
      $p[] = array( 'text' => 'Exercice ' . $start . ' au ' . $end . ' — Généré le ' . date_i18n( 'd/m/Y' ), 'x' => $ml, 'y' => $ph - 42, 'size' => 8, 'font' => 'Helvetica', 'color' => '#C5A253' );
      $p[] = array( 'text' => $org_name, 'x' => 350, 'y' => $ph - 30, 'size' => 10, 'font' => 'Helvetica-Bold', 'color' => '#FFFFFF' );

      // Helper section
      $cy = $ph - 70;
      $draw_section = function( &$p_ref, $title, &$cy_ref ) use ( $ml, $cw, $navy, $gold, $line ) {
        $cy_ref -= 6;
        $p_ref[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $cy_ref - 2, 'width' => $cw, 'height' => 0.8, 'fill_color' => $gold );
        $p_ref[] = array( 'text' => $title, 'x' => $ml, 'y' => $cy_ref + 7, 'size' => 10, 'font' => 'Helvetica-Bold', 'color' => $navy );
        $cy_ref -= 22;
      };

      // Cadre E
      $draw_section( $p, 'CADRE E — FORMATEURS ET HEURES DISPENSÉES', $cy );
      foreach ( array(
        array( 'Formateurs internes (salarié·es)', (string) $nb_internes, number_format( $h_internes, 0 ) . ' h' ),
        array( 'Formateurs externes', (string) $nb_externes, number_format( $h_externes, 0 ) . ' h' ),
        array( 'TOTAL', (string) ( $nb_internes + $nb_externes ), number_format( $h_internes + $h_externes, 0 ) . ' h' ),
      ) as $row ) {
        $p[] = array( 'text' => $row[0], 'x' => $ml + 6, 'y' => $cy, 'size' => 9, 'font' => 'Helvetica', 'color' => $ink );
        $p[] = array( 'text' => $row[1] . ' formateurs', 'x' => $ml + 260, 'y' => $cy, 'size' => 9, 'font' => 'Helvetica-Bold', 'color' => $navy );
        $p[] = array( 'text' => $row[2], 'x' => $ml + 390, 'y' => $cy, 'size' => 9, 'font' => 'Helvetica-Bold', 'color' => $navy );
        $cy -= 16;
      }

      // Cadre F1
      $cy -= 4;
      $draw_section( $p, 'CADRE F1 — TYPES DE STAGIAIRES', $cy );
      $f1_total = $f1_salaries + $f1_apprentis + $f1_demandeurs + $f1_particuliers + $f1_independants + $f1_autres;
      foreach ( array(
        array( 'Salariés', $f1_salaries ),
        array( 'Apprentis', $f1_apprentis ),
        array( "Demandeurs d'emploi (France Travail)", $f1_demandeurs ),
        array( 'Particuliers à leurs frais', $f1_particuliers ),
        array( "Indépendants / Dirigeants", $f1_independants ),
        array( "Autres", $f1_autres ),
        array( "TOTAL STAGIAIRES", $f1_total ),
      ) as $row ) {
        $pct = $f1_total > 0 ? ' (' . number_format( $row[1] / $f1_total * 100, 0 ) . ' %)' : '';
        $p[] = array( 'text' => $row[0], 'x' => $ml + 6, 'y' => $cy, 'size' => 9, 'font' => 'Helvetica', 'color' => $ink );
        $p[] = array( 'text' => $row[1] . $pct, 'x' => $ml + 350, 'y' => $cy, 'size' => 9, 'font' => 'Helvetica-Bold', 'color' => $navy );
        $cy -= 16;
      }

      // Cadre F3
      $cy -= 4;
      $draw_section( $p, 'CADRE F3 — OBJECTIF GÉNÉRAL DES PRESTATIONS', $cy );
      foreach ( $f3_data as $label => $nb ) {
        $p[] = array( 'text' => $label, 'x' => $ml + 6, 'y' => $cy, 'size' => 9, 'font' => 'Helvetica', 'color' => $ink );
        $p[] = array( 'text' => $nb . ' stagiaire' . ( $nb > 1 ? 's' : '' ), 'x' => $ml + 350, 'y' => $cy, 'size' => 9, 'font' => 'Helvetica-Bold', 'color' => $navy );
        $cy -= 16;
      }
      if ( empty( $f3_data ) ) {
        $p[] = array( 'text' => 'Aucune donnée — renseignez "Objectif de prestation" sur les fiches formation.', 'x' => $ml + 6, 'y' => $cy, 'size' => 8.5, 'font' => 'Helvetica', 'color' => $muted );
        $cy -= 16;
      }

      // Cadre F4
      $cy -= 4;
      $draw_section( $p, 'CADRE F4 — SPÉCIALITÉS DE FORMATION (CODES NSF)', $cy );
      foreach ( $f4_rows as $r ) {
        $nsf = trim( (string) $r->specialty );
        if ( '' === $nsf ) { $nsf = 'Non renseigné'; }
        $p[] = array( 'text' => 'NSF ' . $nsf, 'x' => $ml + 6, 'y' => $cy, 'size' => 9, 'font' => 'Helvetica', 'color' => $ink );
        $p[] = array( 'text' => $r->nb_apprenants . ' stagiaire' . ( $r->nb_apprenants > 1 ? 's' : '' ) . ' — ' . $r->nb_sessions . ' session' . ( $r->nb_sessions > 1 ? 's' : '' ), 'x' => $ml + 250, 'y' => $cy, 'size' => 9, 'font' => 'Helvetica-Bold', 'color' => $navy );
        $cy -= 16;
      }
      if ( empty( $f4_rows ) ) {
        $p[] = array( 'text' => 'Aucune donnée — renseignez les codes NSF sur les fiches formation.', 'x' => $ml + 6, 'y' => $cy, 'size' => 8.5, 'font' => 'Helvetica', 'color' => $muted );
        $cy -= 16;
      }

      // ACDC 3.21.75 — Prestations extérieures (formateur indépendant)
      if ( ! empty( $ext_summary['rows'] ) ) {
        $cy -= 4;
        $draw_section( $p, "PRESTATIONS EXTÉRIEURES (formateur indépendant)", $cy );
        $p[] = array( 'text' => $ext_summary['nb_missions'] . " prestation(s) — " . number_format( $ext_summary['heures'], 1 ) . " h — " . number_format( $ext_summary['ca'], 2 ) . " € HT — " . $ext_summary['nb_stag'] . " stagiaire(s)", 'x' => $ml + 6, 'y' => $cy, 'size' => 9, 'font' => 'Helvetica-Bold', 'color' => $navy );
        $cy -= 18;
        foreach ( array_slice( (array) $ext_summary['rows'], 0, 8 ) as $er ) {
          $line = mb_strimwidth( (string) ( $er['commanditaire'] ?? '' ), 0, 30, "..." )
            . " — " . number_format( (float) ( $er['heures_total'] ?? 0 ), 1 ) . "h"
            . " — " . (int) ( $er['nb_stagiaires'] ?? 0 ) . " stag."
            . " — " . number_format( (float) ( $er['ca_ht'] ?? 0 ), 2 ) . " EUR";
          $p[] = array( 'text' => $line, 'x' => $ml + 6, 'y' => $cy, 'size' => 8, 'font' => 'Helvetica', 'color' => $ink );
          $cy -= 13;
        }
        if ( count( $ext_summary['rows'] ) > 8 ) {
          $p[] = array( 'text' => "... + " . ( count( $ext_summary['rows'] ) - 8 ) . " prestation(s) supplémentaire(s)", 'x' => $ml + 6, 'y' => $cy, 'size' => 8, 'font' => 'Helvetica', 'color' => $muted );
          $cy -= 13;
        }
      }

      // Footer
      $p[] = array( 'type' => 'rect', 'x' => $ml, 'y' => 28, 'width' => $cw, 'height' => 0.7, 'fill_color' => $line );
      $p[] = array( 'text' => 'Document généré automatiquement — données issues du plugin ACDC SAAS OF — Cadres C, D, G et H à compléter manuellement sur monactiviteformation.emploi.gouv.fr', 'x' => $ml, 'y' => 18, 'size' => 6.5, 'font' => 'Helvetica', 'color' => $muted );
      $pages[] = $p;

    }

    // ACDC 3.21.87 — Génération du vrai CERFA visuel via FPDI+FPDF (PHP pur, embarqués dans le plugin)
    $cerfa_url = '';
    $cerfa_generator_file = plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'assets/cerfa/cerfa_bpf_generator.php';
    $cerfa_blank_url  = method_exists( $this, 'get_bpf_cerfa_blank_url' ) ? $this->get_bpf_cerfa_blank_url() : '';
    $cerfa_blank_path = '';
    if ( ! empty( $cerfa_blank_url ) ) {
      $uploads_info    = wp_upload_dir();
      $uploads_base_u  = trailingslashit( $uploads_info['baseurl'] );
      $site_base_u     = trailingslashit( site_url() );
      if ( 0 === strpos( $cerfa_blank_url, $uploads_base_u ) ) {
        $cerfa_blank_path = $uploads_info['basedir'] . '/' . ltrim( substr( $cerfa_blank_url, strlen( $uploads_base_u ) ), '/' );
      } elseif ( 0 === strpos( $cerfa_blank_url, $site_base_u ) ) {
        $cerfa_blank_path = ABSPATH . ltrim( substr( $cerfa_blank_url, strlen( $site_base_u ) ), '/' );
      }
    }
    if ( ! $cerfa_blank_path ) {
      $cerfa_blank_path = ABSPATH . 'wp-content/uploads/2026/04/cerfa_10443-17.pdf';
    }
    $fpdi_ok = false;
    if ( file_exists( $cerfa_generator_file ) ) {
      require_once $cerfa_generator_file;
      if ( class_exists( 'ACDC_Cerfa_BPF_Generator' ) ) {
        $profile  = $this->get_company_profile_options();
        $f1_total = $f1_salaries + $f1_apprentis + $f1_demandeurs + $f1_particuliers + $f1_independants + $f1_autres;
        $f1_total_h_cerfa = round( $heures_stagiaires_total, 0 );
        $nsf_labels_fp = array( '326' => "Informatique, traitement de l'information", '413' => 'Capacités comportementales', '320' => 'Spécialités de la communication', '315' => 'RH, gestion du personnel', '321' => 'Journalisme, communication', '334' => 'Accueil, hôtellerie, tourisme', '221' => 'Agroalimentaire', '333' => 'Enseignement, formation', '414' => 'Organisation individuelle', '412' => 'Apprentissages de base' );
        $f4_cerfa_fp = array();
        foreach ( (array) $f4_rows as $r ) {
          $nsf_code = trim( (string) $r->specialty );
          $f4_cerfa_fp[] = array( 'specialty' => $nsf_code, 'specialty_label' => $nsf_labels_fp[ $nsf_code ] ?? $nsf_code, 'nb_apprenants' => (int) $r->nb_apprenants, 'heures_total' => (int) round( $r->nb_apprenants * 7 ) );
        }
        $bpf_payload = array(
          'org'    => array(
            'nda'               => (string) ( $profile['activity_declaration_number'] ?? '' ),
            'siret'             => (string) ( $profile['siret_identification'] ?? '' ),
            'naf_code'          => (string) ( $profile['naf_code'] ?? '' ),
            'legal_form'        => (string) ( $profile['legal_form'] ?? '' ),
            'enterprise'        => (string) ( $profile['enterprise'] ?? '' ),
            'address'           => trim( ( $profile['address'] ?? '' ) . ' ' . ( $profile['postal_code'] ?? '' ) . ' ' . ( $profile['city'] ?? '' ) ),
            'phone'             => (string) ( $profile['enterprise_contact_phone'] ?? '' ),
            'email'             => (string) ( $profile['enterprise_contact_email'] ?? '' ),
            'city'              => (string) ( $profile['city'] ?? '' ),
            'dirigeant_nom'     => (string) ( $profile['enterprise'] ?? '' ),
            'dirigeant_qualite' => (string) ( $profile['legal_form'] ?? 'Gérant' ),
          ),
          'period' => array( 'start' => $start, 'end' => $end, 'date_generation' => date_i18n( 'd/m/Y' ) ),
          'data'   => array(
            'has_distance' => true,
            'c1' => round( $c1_val, 0 ),'ca' => 0,'cb' => 0,'cc' => 0,'cd' => 0,'ce' => 0,'cf' => 0,'cg' => 0,'ch' => 0,'c2' => 0,
            'c3' => 0,'c4' => 0,'c5' => 0,'c6' => 0,'c7' => 0,'c8' => 0,'c9' => round( $c9_val, 0 ),
            'c10' => (int) $ext_summary['ca'],'c11' => 0,
            'c_total' => (int) ( $c1_val + $c9_val + $ext_summary['ca'] ),'pct_ca' => 100,
            'd_total' => round( $d_total_val, 0 ),'d_salaires' => round( $d_salaires_val, 0 ),'d_achats' => round( $d_achats_val, 0 ),
            'e_nb_internes' => $nb_internes,'e_h_internes' => round( $h_internes, 0 ),
            'e_nb_externes' => $nb_externes,'e_h_externes' => round( $h_externes, 0 ),
            'f1_salaries' => $f1_salaries,'f1_apprentis' => $f1_apprentis,'f1_demandeurs' => $f1_demandeurs,
            'f1_particuliers' => $f1_particuliers,'f1_independants' => $f1_independants,'f1_autres' => $f1_autres,
            'f1_total_heures' => $f1_total_h_cerfa,
            'f4' => $f4_cerfa_fp,
            'g_nb_stag' => $ext_summary['nb_stag'],'g_heures' => round( $ext_summary['heures'], 0 ),
          ),
        );
        $uploads_c  = wp_upload_dir();
        $dir_path_c = trailingslashit( $uploads_c['basedir'] ) . 'acdc-bpf/';
        $dir_url_c  = trailingslashit( $uploads_c['baseurl'] ) . 'acdc-bpf/';
        wp_mkdir_p( $dir_path_c );
        $cerfa_out_fp = $dir_path_c . 'cerfa-bpf-' . sanitize_file_name( $year ) . '-' . date_i18n( 'Ymd-His' ) . '.pdf';
        $gen = new ACDC_Cerfa_BPF_Generator();
        $gen->parent_instance = $this;
        if ( $gen->generate( $cerfa_blank_path, $bpf_payload, $cerfa_out_fp ) ) {
          $cerfa_url = $dir_url_c . basename( $cerfa_out_fp );
          $fpdi_ok   = true;
        }
      }
    }

    // ACDC 3.21.95 — Fallback supprimé : le générateur JPEG est la seule voie
    if ( false && ! $fpdi_ok && method_exists( $this, '_build_simple_pdf_string' ) ) {
      $profile  = $this->get_company_profile_options();
      $f1_total = $f1_salaries + $f1_apprentis + $f1_demandeurs + $f1_particuliers + $f1_independants + $f1_autres;
      $f1_total_h_cerfa = round( $heures_stagiaires_total, 0 );
      $nsf_labels = array( '326' => "Informatique, traitement de l'info.", '413' => 'Capacités comportementales', '320' => 'Communication', '315' => 'RH, gestion du personnel', '321' => 'Journalisme, communication', '334' => 'Accueil, hôtellerie, tourisme', '221' => 'Agroalimentaire', '333' => 'Enseignement, formation', '414' => 'Organisation individuelle', '412' => 'Apprentissages de base' );
      $navy = '#0C2D52'; $gold = '#C5A253'; $ink = '#1a1a2e'; $muted = '#6b7280'; $light = '#f0f4f8'; $white = '#FFFFFF'; $red = '#dc2626';
      $pw = 595; $ph = 842; $ml = 30; $cw = 535; $mr = $ml + $cw;
      $col_label = 380; $col_val = 385;

      $draw_row = function( &$p, $label, $val, &$cy, $bold_val = false ) use ( $ml, $col_label, $col_val, $ink, $navy, $muted ) {
        $p[] = array( 'text' => $label, 'x' => $ml + 4, 'y' => $cy, 'size' => 8, 'font' => 'Helvetica', 'color' => $ink );
        $p[] = array( 'text' => $val, 'x' => $col_val, 'y' => $cy, 'size' => 8, 'font' => $bold_val ? 'Helvetica-Bold' : 'Helvetica', 'color' => $bold_val ? $navy : $ink );
        $cy -= 13;
      };
      $draw_section = function( &$p, $code, $title, &$cy ) use ( $ml, $cw, $navy, $gold, $light ) {
        $cy -= 4;
        $p[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $cy - 2, 'width' => $cw, 'height' => 15, 'fill_color' => $navy );
        $p[] = array( 'text' => $code . ' — ' . $title, 'x' => $ml + 6, 'y' => $cy + 8, 'size' => 8.5, 'font' => 'Helvetica-Bold', 'color' => $gold );
        $cy -= 20;
      };
      $draw_sep = function( &$p, &$cy ) use ( $ml, $cw, $muted ) {
        $p[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $cy, 'width' => $cw, 'height' => 0.5, 'fill_color' => '#d1d5db' );
        $cy -= 5;
      };

      $pages_cerfa = array();

      // ── PAGE 1 ──────────────────────────────────────────────────────────────
      $p1 = array();
      $p1[] = array( 'type' => 'page_meta', 'width' => $pw, 'height' => $ph );
      // En-tête
      $p1[] = array( 'type' => 'rect', 'x' => 0, 'y' => $ph - 46, 'width' => $pw, 'height' => 46, 'fill_color' => $navy );
      $p1[] = array( 'text' => 'BILAN PÉDAGOGIQUE ET FINANCIER — Cerfa 10443*17', 'x' => $ml, 'y' => $ph - 18, 'size' => 12, 'font' => 'Helvetica-Bold', 'color' => $white );
      $p1[] = array( 'text' => 'Exercice du ' . $start . ' au ' . $end . ' — Généré le ' . date_i18n( 'd/m/Y' ), 'x' => $ml, 'y' => $ph - 34, 'size' => 8, 'font' => 'Helvetica', 'color' => $gold );
      $p1[] = array( 'text' => 'Données à saisir sur monactiviteformation.emploi.gouv.fr', 'x' => 310, 'y' => $ph - 34, 'size' => 7, 'font' => 'Helvetica', 'color' => '#94a3b8' );
      $cy1 = $ph - 58;

      // Cadre A
      $draw_section( $p1, 'A', 'IDENTIFICATION DE L\'ORGANISME', $cy1 );
      $draw_row( $p1, 'Numéro de déclaration (NDA)',  (string) ( $profile['activity_declaration_number'] ?? '—' ), $cy1 );
      $draw_row( $p1, 'Forme juridique',               (string) ( $profile['legal_form'] ?? '—' ), $cy1 );
      $draw_row( $p1, 'Numéro SIRET',                  (string) ( $profile['siret_identification'] ?? '—' ), $cy1 );
      $draw_row( $p1, 'Code NAF',                      (string) ( $profile['naf_code'] ?? '—' ), $cy1 );
      $draw_row( $p1, 'Dénomination',                  (string) ( $profile['enterprise'] ?? '—' ), $cy1 );
      $draw_row( $p1, 'Adresse',                       trim( ( $profile['address'] ?? '' ) . ' ' . ( $profile['postal_code'] ?? '' ) . ' ' . ( $profile['city'] ?? '' ) ) ?: '—', $cy1 );
      $draw_row( $p1, 'Email',                         (string) ( $profile['enterprise_contact_email'] ?? '—' ), $cy1 );
      $draw_row( $p1, 'Téléphone',                     (string) ( $profile['enterprise_contact_phone'] ?? '—' ), $cy1 );

      // Cadre B
      $draw_section( $p1, 'B', 'INFORMATIONS GÉNÉRALES', $cy1 );
      $draw_row( $p1, 'Exercice comptable du',  $start . ' au ' . $end, $cy1, true );
      $draw_row( $p1, 'Formations à distance',  'OUI', $cy1 );

      // Cadre C
      $draw_section( $p1, 'C', 'BILAN FINANCIER — ORIGINE DES PRODUITS (HT)', $cy1 );
      $draw_row( $p1, '1 — Entreprises (formation salariés)',             '0 €', $cy1 );
      $draw_row( $p1, 'a — Contrats d\'apprentissage',                   '0 €', $cy1 );
      $draw_row( $p1, 'b — Contrats de professionnalisation',             '0 €', $cy1 );
      $draw_row( $p1, 'c — Promotion / reconversion alternance',         '0 €', $cy1 );
      $draw_row( $p1, 'd — Projets transition professionnelle',           '0 €', $cy1 );
      $draw_row( $p1, 'e — Compte personnel de formation (CPF)',          '0 €', $cy1 );
      $draw_row( $p1, 'f — Dispositifs personnes en recherche d\'emploi', '0 €', $cy1 );
      $draw_row( $p1, 'g — Dispositifs travailleurs non-salariés',        '0 €', $cy1 );
      $draw_row( $p1, 'h — Plan développement des compétences',           '0 €', $cy1 );
      $draw_row( $p1, '2 — Total OPCO (a à h)',                          '0 €', $cy1, true );
      $draw_row( $p1, '3 — Pouvoirs publics (agents)',                    '0 €', $cy1 );
      $draw_row( $p1, '4 — Instances européennes',                        '0 €', $cy1 );
      $draw_row( $p1, '5 — État',                                         '0 €', $cy1 );
      $draw_row( $p1, '6 — Conseils régionaux',                           '0 €', $cy1 );
      $draw_row( $p1, '7 — France Travail (ex Pôle emploi)',              '0 €', $cy1 );
      $draw_row( $p1, '8 — Autres ressources publiques',                  '0 €', $cy1 );
      $draw_row( $p1, '9 — Particuliers à titre individuel',              '0 €', $cy1 );
      $c10_val = number_format( round( $ext_summary['ca'], 0 ), 0, ',', ' ' ) . ' €';
      $draw_row( $p1, '10 — Autres organismes de formation (sous-traitant)', $c10_val, $cy1, true );
      $draw_row( $p1, '11 — Autres produits',                             '0 €', $cy1 );
      $cy1 -= 2;
      $draw_row( $p1, 'TOTAL DES PRODUITS (lignes 1 à 11)',               $c10_val . ' — Part CA : 100 %', $cy1, true );

      // Cadre D
      $draw_section( $p1, 'D', 'BILAN FINANCIER — CHARGES (HT)', $cy1 );
      $draw_row( $p1, 'Total charges liées à la formation',               '0 € (à compléter)', $cy1 );
      $draw_row( $p1, 'dont Salaires des formateurs',                     '0 € (à compléter)', $cy1 );
      $draw_row( $p1, 'dont Achats de prestations de formation',          '0 € (à compléter)', $cy1 );

      // Footer page 1
      $p1[] = array( 'type' => 'rect', 'x' => $ml, 'y' => 20, 'width' => $cw, 'height' => 0.5, 'fill_color' => '#d1d5db' );
      $p1[] = array( 'text' => '1/2 — Cadres C (lignes 1-9, 11) et D : montants à 0 — les renseigner manuellement sur MAF si applicable', 'x' => $ml, 'y' => 10, 'size' => 6.5, 'font' => 'Helvetica', 'color' => $muted );
      $pages_cerfa[] = $p1;

      // ── PAGE 2 ──────────────────────────────────────────────────────────────
      $p2 = array();
      $p2[] = array( 'type' => 'page_meta', 'width' => $pw, 'height' => $ph );
      $p2[] = array( 'type' => 'rect', 'x' => 0, 'y' => $ph - 36, 'width' => $pw, 'height' => 36, 'fill_color' => $navy );
      $p2[] = array( 'text' => 'BPF — ' . ( $profile['enterprise'] ?? 'ACDC Formation' ) . ' — Exercice ' . $start . ' au ' . $end, 'x' => $ml, 'y' => $ph - 14, 'size' => 9, 'font' => 'Helvetica-Bold', 'color' => $white );
      $p2[] = array( 'text' => 'Page 2/2 — Cadres E, F, G, H', 'x' => $ml, 'y' => $ph - 26, 'size' => 7, 'font' => 'Helvetica', 'color' => $gold );
      $cy2 = $ph - 48;

      // Cadre E
      $draw_section( $p2, 'E', 'PERSONNES DISPENSANT DES HEURES DE FORMATION', $cy2 );
      $p2[] = array( 'text' => 'Catégorie', 'x' => $ml + 4, 'y' => $cy2, 'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => $muted );
      $p2[] = array( 'text' => 'Nombre', 'x' => $col_label + 10, 'y' => $cy2, 'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => $muted );
      $p2[] = array( 'text' => 'Heures dispensées', 'x' => $col_val, 'y' => $cy2, 'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => $muted );
      $cy2 -= 13;
      $draw_row( $p2, 'Personnes de votre organisme (internes)', (string) $nb_internes . ' — ' . number_format( $h_internes, 0 ) . ' h', $cy2, true );
      $draw_row( $p2, 'Personnes extérieures (sous-traitance)',  (string) $nb_externes . ' — ' . number_format( $h_externes, 0 ) . ' h', $cy2, true );

      // Cadre F1
      $draw_section( $p2, 'F-1', 'TYPE DE STAGIAIRES', $cy2 );
      $p2[] = array( 'text' => 'Type', 'x' => $ml + 4, 'y' => $cy2, 'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => $muted );
      $p2[] = array( 'text' => 'Nb stagiaires', 'x' => $col_label, 'y' => $cy2, 'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => $muted );
      $p2[] = array( 'text' => 'Nb heures', 'x' => $col_val, 'y' => $cy2, 'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => $muted );
      $cy2 -= 13;
      $p2[] = array( 'text' => 'a — Salariés employeurs privés (hors apprentis)', 'x' => $ml + 4, 'y' => $cy2, 'size' => 8, 'font' => 'Helvetica', 'color' => $ink );
      $p2[] = array( 'text' => (string) $f1_salaries, 'x' => $col_label, 'y' => $cy2, 'size' => 8, 'font' => 'Helvetica-Bold', 'color' => $navy );
      $p2[] = array( 'text' => '0', 'x' => $col_val, 'y' => $cy2, 'size' => 8, 'font' => 'Helvetica', 'color' => $muted );
      $cy2 -= 13;
      $p2[] = array( 'text' => 'b — Apprentis', 'x' => $ml + 4, 'y' => $cy2, 'size' => 8, 'font' => 'Helvetica', 'color' => $ink );
      $p2[] = array( 'text' => (string) $f1_apprentis, 'x' => $col_label, 'y' => $cy2, 'size' => 8, 'font' => 'Helvetica-Bold', 'color' => $navy );
      $p2[] = array( 'text' => '0', 'x' => $col_val, 'y' => $cy2, 'size' => 8, 'font' => 'Helvetica', 'color' => $muted );
      $cy2 -= 13;
      $p2[] = array( 'text' => 'c — Personnes en recherche d\'emploi', 'x' => $ml + 4, 'y' => $cy2, 'size' => 8, 'font' => 'Helvetica', 'color' => $ink );
      $p2[] = array( 'text' => (string) $f1_demandeurs, 'x' => $col_label, 'y' => $cy2, 'size' => 8, 'font' => 'Helvetica-Bold', 'color' => $navy );
      $p2[] = array( 'text' => '0', 'x' => $col_val, 'y' => $cy2, 'size' => 8, 'font' => 'Helvetica', 'color' => $muted );
      $cy2 -= 13;
      $p2[] = array( 'text' => 'd — Particuliers à leurs frais', 'x' => $ml + 4, 'y' => $cy2, 'size' => 8, 'font' => 'Helvetica', 'color' => $ink );
      $p2[] = array( 'text' => (string) $f1_particuliers, 'x' => $col_label, 'y' => $cy2, 'size' => 8, 'font' => 'Helvetica-Bold', 'color' => $navy );
      $p2[] = array( 'text' => '0', 'x' => $col_val, 'y' => $cy2, 'size' => 8, 'font' => 'Helvetica', 'color' => $muted );
      $cy2 -= 13;
      $p2[] = array( 'text' => 'e — Autres stagiaires', 'x' => $ml + 4, 'y' => $cy2, 'size' => 8, 'font' => 'Helvetica', 'color' => $ink );
      $p2[] = array( 'text' => (string) $f1_autres, 'x' => $col_label, 'y' => $cy2, 'size' => 8, 'font' => 'Helvetica-Bold', 'color' => $navy );
      $p2[] = array( 'text' => number_format( $f1_total_h_cerfa, 0 ) . ' h', 'x' => $col_val, 'y' => $cy2, 'size' => 8, 'font' => 'Helvetica-Bold', 'color' => $navy );
      $cy2 -= 13;
      $p2[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $cy2, 'width' => $cw, 'height' => 0.5, 'fill_color' => '#d1d5db' );
      $cy2 -= 6;
      $p2[] = array( 'text' => 'TOTAL (a+b+c+d+e) — (1)', 'x' => $ml + 4, 'y' => $cy2, 'size' => 8.5, 'font' => 'Helvetica-Bold', 'color' => $navy );
      $p2[] = array( 'text' => (string) $f1_total, 'x' => $col_label, 'y' => $cy2, 'size' => 8.5, 'font' => 'Helvetica-Bold', 'color' => $navy );
      $p2[] = array( 'text' => number_format( $f1_total_h_cerfa, 0 ) . ' h', 'x' => $col_val, 'y' => $cy2, 'size' => 8.5, 'font' => 'Helvetica-Bold', 'color' => $navy );
      $cy2 -= 14;

      // Cadre F2
      $draw_section( $p2, 'F-2', 'ACTIVITÉ SOUS-TRAITÉE PAR VOTRE ORGANISME', $cy2 );
      $draw_row( $p2, 'Stagiaires dont action confiée à un autre organisme — (2)', '0 — 0 h', $cy2 );

      // Cadre F3
      $draw_section( $p2, 'F-3', 'OBJECTIF GÉNÉRAL DES PRESTATIONS', $cy2 );
      $draw_row( $p2, 'a — Formations visant RNCP/CQP',      '0 — 0 h', $cy2 );
      $draw_row( $p2, 'b — Formations visant RS',             '0 — 0 h', $cy2 );
      $draw_row( $p2, 'c — CQP non enregistré',               '0 — 0 h', $cy2 );
      $draw_row( $p2, 'd — Autres formations professionnelles', $f1_total . ' — ' . number_format( $f1_total_h_cerfa, 0 ) . ' h', $cy2, true );
      $draw_row( $p2, 'e — Bilans de compétence',              '0 — 0 h', $cy2 );
      $draw_row( $p2, 'f — Actions VAE',                       '0 — 0 h', $cy2 );
      $p2[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $cy2, 'width' => $cw, 'height' => 0.5, 'fill_color' => '#d1d5db' );
      $cy2 -= 6;
      $p2[] = array( 'text' => 'TOTAL (a+b+c+d+e+f) — (3)', 'x' => $ml + 4, 'y' => $cy2, 'size' => 8.5, 'font' => 'Helvetica-Bold', 'color' => $navy );
      $p2[] = array( 'text' => $f1_total . ' — ' . number_format( $f1_total_h_cerfa, 0 ) . ' h', 'x' => $col_val, 'y' => $cy2, 'size' => 8.5, 'font' => 'Helvetica-Bold', 'color' => $navy );
      $cy2 -= 16;

      // Cadre F4
      $draw_section( $p2, 'F-4', 'SPÉCIALITÉS DE FORMATION (5 principales)', $cy2 );
      $f4_tot_nb = 0; $f4_tot_h = 0;
      foreach ( array_slice( (array) $f4_rows, 0, 5 ) as $r ) {
        $nsf = trim( (string) $r->specialty );
        $nsf_label = isset( $nsf_labels[ $nsf ] ) ? $nsf_labels[ $nsf ] : ( $nsf ?: 'Non renseigné' );
        $nb_r = (int) $r->nb_apprenants;
        $f4_tot_nb += $nb_r;
        $p2[] = array( 'text' => 'NSF ' . $nsf . ' — ' . $nsf_label, 'x' => $ml + 4, 'y' => $cy2, 'size' => 8, 'font' => 'Helvetica', 'color' => $ink );
        $p2[] = array( 'text' => $nb_r . ' stag.', 'x' => $col_val, 'y' => $cy2, 'size' => 8, 'font' => 'Helvetica-Bold', 'color' => $navy );
        $cy2 -= 13;
      }
      if ( empty( $f4_rows ) ) {
        $p2[] = array( 'text' => 'Aucune spécialité NSF renseignée sur les fiches formation', 'x' => $ml + 4, 'y' => $cy2, 'size' => 8, 'font' => 'Helvetica', 'color' => $muted );
        $cy2 -= 13;
      }
      $cy2 -= 2;
      $p2[] = array( 'text' => 'TOTAL — (4)', 'x' => $ml + 4, 'y' => $cy2, 'size' => 8.5, 'font' => 'Helvetica-Bold', 'color' => $navy );
      $p2[] = array( 'text' => $f1_total . ' stag.', 'x' => $col_val, 'y' => $cy2, 'size' => 8.5, 'font' => 'Helvetica-Bold', 'color' => $navy );
      $cy2 -= 16;

      // Cadre G
      $draw_section( $p2, 'G', 'STAGIAIRES DONT FORMATION CONFIÉE PAR UN AUTRE OF', $cy2 );
      $draw_row( $p2, 'Formations confiées par autres OF (sous-traitance reçue) — (5)', $ext_summary['nb_stag'] . ' stag. — ' . number_format( $ext_summary['heures'], 0 ) . ' h', $cy2, true );

      // Cadre H
      $draw_section( $p2, 'H', 'DIRIGEANT DÉCLARANT', $cy2 );
      $draw_row( $p2, 'Nom et prénom',  (string) ( $profile['enterprise'] ?? '—' ), $cy2 );
      $draw_row( $p2, 'Qualité',        (string) ( $profile['legal_form'] ?? '—' ), $cy2 );
      $draw_row( $p2, 'Lieu et date',   ( $profile['city'] ?? '' ) . ', le ' . date_i18n( 'd/m/Y' ), $cy2 );

      // Footer page 2
      $p2[] = array( 'type' => 'rect', 'x' => $ml, 'y' => 20, 'width' => $cw, 'height' => 0.5, 'fill_color' => '#d1d5db' );
      $p2[] = array( 'text' => '2/2 — Cadre F (heures par type) : à calculer si besoin — Cadres C (1-9,11) et D : à renseigner manuellement sur monactiviteformation.emploi.gouv.fr', 'x' => $ml, 'y' => 10, 'size' => 6.5, 'font' => 'Helvetica', 'color' => $muted );
      $pages_cerfa[] = $p2;

      $cerfa_content = $this->_build_simple_pdf_string( $pages_cerfa );
      if ( ! empty( $cerfa_content ) ) {
        $uploads_c    = wp_upload_dir();
        $dir_path_c   = trailingslashit( $uploads_c['basedir'] ) . 'acdc-bpf/';
        $dir_url_c    = trailingslashit( $uploads_c['baseurl'] ) . 'acdc-bpf/';
        wp_mkdir_p( $dir_path_c );
        $cerfa_file   = 'cerfa-bpf-' . sanitize_file_name( $year ) . '-' . date_i18n( 'Ymd-His' ) . '.pdf';
        file_put_contents( $dir_path_c . $cerfa_file, $cerfa_content );
        $cerfa_url = $dir_url_c . $cerfa_file;
      }
    }

    // Sauvegarder le record BPF avec les données calculées et l'URL du PDF
    $records = $this->get_bpf_records();
    if ( ! isset( $records[ $year ] ) ) { $records[ $year ] = array( 'year' => $year, 'title' => 'BPF ' . $year ); }
    $records[ $year ]['start_date'] = $start;
    $records[ $year ]['end_date']   = $end;
    $records[ $year ]['status']     = 'generated';
    /* ACDC 3.25.291 — $pdf_url n'était jamais défini : ce champ valait donc
       toujours la chaîne vide. Cette génération produit un seul document, le
       CERFA ; la fiche pointe désormais dessus au lieu de ne rien porter. */
    $records[ $year ]['pdf_url']    = $cerfa_url;
    $records[ $year ]['cerfa_url']  = $cerfa_url;
    $records[ $year ]['data'] = array(
      'c1'             => round( $c1_val, 0 ),
      'c9'             => round( $c9_val, 0 ),
      'c10'            => (int) $ext_summary['ca'],
      'c_total'        => (int) ( $c1_val + $c9_val + $ext_summary['ca'] ),
      'd_total'        => round( $d_total_val, 0 ),
      'd_salaires'     => round( $d_salaires_val, 0 ),
      'd_achats'       => round( $d_achats_val, 0 ),
      'e_nb_internes'  => $nb_internes,
      'e_nb_externes'  => $nb_externes,
      'e_h_internes'   => round( $h_internes, 1 ),
      'e_h_externes'   => round( $h_externes, 1 ),
      'f1_salaries'    => $f1_salaries,
      'f1_apprentis'   => $f1_apprentis,
      'f1_demandeurs'  => $f1_demandeurs,
      'f1_particuliers'=> $f1_particuliers,
      'f1_independants'=> $f1_independants,
      'f1_autres'      => $f1_autres,
      'f1_total'       => $nb_apprenants_total,
      'f3'             => $f3_data,
      'f4'             => $f4_rows,
      'generated_at'   => current_time( 'mysql' ),
    );
    $this->save_bpf_records( $records );

    // Rediriger vers la page de détail BPF avec téléchargement du PDF si disponible
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'bpf' ) );
    $redirect = add_query_arg( array( 'tab' => 'bpf', 'bpf_view' => 'detail', 'bpf_year' => $year ), $base_url );
    wp_safe_redirect( $redirect );
    exit;
  }


  public function handle_reset_bpf() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_reset_bpf' );
    delete_option( 'acdc_of_bpf_records' );
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'bpf' ) );
    $redirect = add_query_arg( array( 'tab' => 'bpf', 'notice' => rawurlencode( 'Records BPF réinitialisés.' ), 'notice_type' => 'success' ), $base_url );
    wp_safe_redirect( $redirect );
    exit;
  }

  public function handle_reset_bpf_year() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_reset_bpf_year' );
    $year = isset( $_REQUEST['bpf_year'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['bpf_year'] ) ) : '';
    if ( $year ) {
      $records = $this->get_bpf_records();
      if ( isset( $records[ $year ] ) ) {
        $records[ $year ]['status']            = 'pending';
        $records[ $year ]['pdf_url']           = '';
        $records[ $year ]['cerfa_url']         = '';
        $records[ $year ]['imported_file_url'] = '';
        unset( $records[ $year ]['data'] );
        $this->save_bpf_records( $records );
      }
    }
    $base_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-dashboard' ) : $this->portal_page_url( array( 'tab' => 'bpf' ) );
    $redirect = add_query_arg( array( 'tab' => 'bpf', 'notice' => rawurlencode( 'BPF ' . $year . ' réinitialisé.' ), 'notice_type' => 'success' ), $base_url );
    wp_safe_redirect( $redirect );
    exit;
  }



  public function handle_import_bpf() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_import_bpf' );
    $year = isset( $_POST['bpf_year'] ) ? sanitize_text_field( wp_unslash( $_POST['bpf_year'] ) ) : '2024';
    $start = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
    $end = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
    if ( empty( $_FILES['bpf_file']['name'] ) ) {
      $this->redirect_to_portal( 'bpf', 'Veuillez sélectionner un fichier.', 'error' );
    }
    $upload = wp_handle_upload( $_FILES['bpf_file'], array( 'test_form' => false, 'mimes' => array( 'pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) ) );
    if ( ! empty( $upload['error'] ) ) {
      $this->redirect_to_portal( 'bpf', 'Téléversement impossible : ' . $upload['error'], 'error' );
    }
    $records = $this->get_bpf_records();
    if ( ! isset( $records[ $year ] ) ) { $records[ $year ] = array( 'year' => $year, 'title' => 'BPF ' . $year ); }
    $records[ $year ]['start_date'] = $start;
    $records[ $year ]['end_date'] = $end;
    $records[ $year ]['imported_file_url'] = isset( $upload['url'] ) ? esc_url_raw( $upload['url'] ) : '';
    $records[ $year ]['status'] = 'generated';
    $this->save_bpf_records( $records );
    $this->redirect_to_portal( 'bpf', 'BPF importé.', 'success' );
  }



  public function handle_save_continuous_improvement() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    check_admin_referer( 'acdc_front_secure_action' );
    $payload = isset( $_POST['improvement'] ) && is_array( $_POST['improvement'] ) ? wp_unslash( $_POST['improvement'] ) : array();
    $title = sanitize_text_field( $payload['title'] ?? '' );
    $source = sanitize_text_field( $payload['source'] ?? '' );
    $finding = sanitize_textarea_field( $payload['finding'] ?? '' );
    $curative_action = sanitize_textarea_field( $payload['curative_action'] ?? '' );
    $curative_date = sanitize_text_field( $payload['curative_date'] ?? '' );
    $definitive_action = sanitize_textarea_field( $payload['definitive_action'] ?? '' );
    $definitive_date = sanitize_text_field( $payload['definitive_date'] ?? '' );
    $incident_type = sanitize_text_field( $payload['incident_type'] ?? '' );
    $status = sanitize_text_field( $payload['status'] ?? '' );

    if ( '' === $title || '' === $source || '' === $finding || '' === $incident_type ) {
      $this->redirect_to_portal( 'continuous_improvement', 'Veuillez renseigner tous les champs obligatoires.', 'error', array( 'action' => 'new' ) );
    }

    $records = $this->get_continuous_improvement_records();
    /* ACDC 3.22.30 — Anti-doublon : si lié à une réclamation déjà présente, mettre à jour au lieu de créer */
    $linked_complaint_id = isset( $payload['linked_complaint_id'] ) ? (int) $payload['linked_complaint_id'] : 0;
    if ( $linked_complaint_id > 0 ) {
      $existing_key = 'ci_complaint_' . $linked_complaint_id;
      foreach ( $records as $k => $r ) {
        if ( isset( $r['source'] ) && $r['source'] === 'complaint_' . $linked_complaint_id ) {
          /* Mettre à jour l'entrée existante plutôt que d'en créer une nouvelle */
          $records[ $k ]['title']             = $title;
          $records[ $k ]['finding']           = $finding;
          $records[ $k ]['curative_action']   = $curative_action;
          $records[ $k ]['curative_date']     = $curative_date;
          $records[ $k ]['definitive_action'] = $definitive_action;
          $records[ $k ]['definitive_date']   = $definitive_date;
          $records[ $k ]['incident_type']     = $incident_type;
          $records[ $k ]['status']            = $status ? $status : 'À traiter';
          $records[ $k ]['updated_at']        = current_time( 'mysql' );
          $this->save_continuous_improvement_records( $records );
          $submit_mode = isset( $_POST['submit_mode'] ) ? sanitize_key( wp_unslash( $_POST['submit_mode'] ) ) : 'save';
          $this->redirect_to_portal( 'continuous_improvement', 'Amélioration continue mise à jour.', 'success' );
          return;
        }
      }
    }
    $records[] = array(
      'id' => uniqid( 'aci_', true ),
      'title' => $title,
      'source' => $source,
      'finding' => $finding,
      'curative_action' => $curative_action,
      'curative_date' => $curative_date,
      'definitive_action' => $definitive_action,
      'definitive_date' => $definitive_date,
      'incident_type' => $incident_type,
      'status' => $status ? $status : 'À traiter',
      'created_at' => current_time( 'mysql' ),
    );
    $this->save_continuous_improvement_records( $records );

    $submit_mode = isset( $_POST['submit_mode'] ) ? sanitize_key( wp_unslash( $_POST['submit_mode'] ) ) : 'save';
    if ( 'add_new' === $submit_mode ) {
      $this->redirect_to_portal( 'continuous_improvement', 'Amélioration continue créée.', 'success', array( 'action' => 'new' ) );
    }
    $this->redirect_to_portal( 'continuous_improvement', 'Amélioration continue créée.', 'success' );
  }



  public function handle_save_learner_deadline() {
    $this->require_front_manager( 'learner_deadlines' );
    $input = isset( $_POST['deadline'] ) && is_array( $_POST['deadline'] ) ? wp_unslash( $_POST['deadline'] ) : array();
    $title = sanitize_text_field( $input['title'] ?? '' );
    $due_at = sanitize_text_field( $input['due_at'] ?? '' );
    $type = sanitize_text_field( $input['type'] ?? '' );
    $notes = sanitize_textarea_field( $input['notes'] ?? '' );
    $learner_id = absint( $input['learner_id'] ?? 0 );
    $group_id = absint( $input['group_id'] ?? 0 );
    $email_reminder = ! empty( $_POST['deadline']['email_reminder'] ) ? 1 : 0;
    if ( '' === $title || '' === $due_at || '' === $type ) {
      $this->redirect_to_portal( 'learner_deadlines', 'Veuillez compléter les champs obligatoires.', 'error', array( 'action' => 'new' ) );
    }
    $target_label = '';
    if ( 'Individuelle' === $type ) {
      if ( ! $learner_id ) {
        $this->redirect_to_portal( 'learner_deadlines', 'Veuillez sélectionner un apprenant.', 'error', array( 'action' => 'new' ) );
      }
      $learner = $this->get_learner( $learner_id );
      $target_label = $learner ? trim( (string) $learner->first_name . ' ' . (string) $learner->usage_last_name ) : '';
    } elseif ( 'Groupe' === $type ) {
      if ( ! $group_id ) {
        $this->redirect_to_portal( 'learner_deadlines', 'Veuillez sélectionner un groupe.', 'error', array( 'action' => 'new' ) );
      }
      $group = $this->get_group( $group_id );
      if ( $group ) {
        $group_bits = array();
        if ( ! empty( $group->formation_title ) ) {
          $group_bits[] = $group->formation_title;
        } elseif ( ! empty( $group->name ) ) {
          $group_bits[] = $group->name;
        }
        $date_range = trim( implode( ' et ', array_filter( array( $group->start_date ?? '', $group->end_date ?? '' ) ) ) );
        if ( '' !== $date_range ) {
          $group_bits[] = $date_range;
        }
        $target_label = implode( ' - ', array_filter( $group_bits ) );
      }
    }
    $records = $this->get_learner_deadline_records();
    $records[] = array(
      'id' => time(),
      'title' => $title,
      'due_at' => $due_at,
      'type' => $type,
      'notes' => $notes,
      'learner_id' => $learner_id,
      'group_id' => $group_id,
      'target_label' => $target_label,
      'email_reminder' => $email_reminder,
      'created_at' => current_time( 'mysql' ),
    );
    $this->save_learner_deadline_records( $records );
    $submit_mode = isset( $_POST['submit_mode'] ) ? sanitize_key( wp_unslash( $_POST['submit_mode'] ) ) : 'save';
    if ( 'add_new' === $submit_mode ) {
      $this->redirect_to_portal( 'learner_deadlines', 'Échéance créée.', 'success', array( 'action' => 'new' ) );
    }
    $this->redirect_to_portal( 'learner_deadlines', 'Échéance créée.', 'success' );
  }



  public function handle_save_ancillary_service() {
    $this->require_front_manager( 'ancillary_services' );
    $input = isset( $_POST['ancillary_service'] ) && is_array( $_POST['ancillary_service'] ) ? wp_unslash( $_POST['ancillary_service'] ) : array();
    $title = sanitize_text_field( $input['title'] ?? '' );
    $description = sanitize_textarea_field( $input['description'] ?? '' );
    $price_ht = sanitize_text_field( $input['price_ht'] ?? '' );
    $commanditaire_type = sanitize_text_field( $input['commanditaire_type'] ?? '' );
    $use_dates = ! empty( $input['use_dates'] ) ? 1 : 0;
    $start_at = sanitize_text_field( $input['start_at'] ?? '' );
    $end_at = sanitize_text_field( $input['end_at'] ?? '' );
    $show_in_calendar = ! empty( $input['show_in_calendar'] ) ? 1 : 0;
    $trainer_id = absint( $input['trainer_id'] ?? 0 );
    if ( '' === $title || '' === $commanditaire_type || '' === $start_at || '' === $end_at ) {
      $this->redirect_to_portal( 'ancillary_services', 'Veuillez compléter les champs obligatoires.', 'error', array( 'action' => 'new' ) );
    }
    $trainer = $trainer_id ? $this->get_trainer( $trainer_id ) : null;
    $trainer_name = $trainer ? trim( (string) $trainer->first_name . ' ' . (string) $trainer->last_name ) : '';
    $records = $this->get_ancillary_service_records();
    $records[] = array(
      'id' => time(),
      'title' => $title,
      'description' => $description,
      'price_ht' => $price_ht,
      'commanditaire_type' => $commanditaire_type,
      'use_dates' => $use_dates,
      'start_at' => $start_at,
      'end_at' => $end_at,
      'show_in_calendar' => $show_in_calendar,
      'trainer_id' => $trainer_id,
      'trainer_name' => $trainer_name,
      'created_at' => current_time( 'mysql' ),
    );
    $this->save_ancillary_service_records( $records );
    $submit_mode = isset( $_POST['submit_mode'] ) ? sanitize_key( wp_unslash( $_POST['submit_mode'] ) ) : 'save';
    if ( 'add_new' === $submit_mode ) {
      $this->redirect_to_portal( 'ancillary_services', 'Prestation annexe créée.', 'success', array( 'action' => 'new' ) );
    }
    $this->redirect_to_portal( 'ancillary_services', 'Prestation annexe créée.', 'success' );
  }
  // ACDC 3.21.75 — Prestations extérieures

  /**
   * L'identité sous laquelle le formateur déclare ses prestations extérieures.
   *
   * ACDC 3.25.283 — Elle est préremplie avec celle de l'organisme, parce
   * qu'aujourd'hui c'est la même personne. Elle est enregistrée SÉPARÉMENT
   * précisément pour survivre au jour où celle de l'organisme changera : c'est
   * ce jour-là qu'on en aura besoin, et il sera alors trop tard pour la
   * retrouver ailleurs.
   *
   * @return array<string,string>
   */
  public function get_external_declarant_identity() {
    $enregistre = get_option( 'acdc_of_external_declarant', array() );
    if ( ! is_array( $enregistre ) ) {
      $enregistre = array();
    }
    $organisme = method_exists( $this, 'get_company_profile_options' )
      ? (array) $this->get_company_profile_options()
      : array();

    $champs = array(
      'enterprise', 'siret_identification', 'activity_declaration_number',
      'legal_form', 'address', 'postal_code', 'city',
      'enterprise_contact_phone', 'enterprise_contact_email', 'naf_code',
    );
    $out = array();
    foreach ( $champs as $cle ) {
      /* Une valeur saisie ici prime toujours. Tant qu'elle est vide, on lit
         celle de l'organisme : c'est vrai aujourd'hui, et ça évite d'exiger une
         double saisie de coordonnées identiques. */
      $out[ $cle ] = ( isset( $enregistre[ $cle ] ) && '' !== trim( (string) $enregistre[ $cle ] ) )
        ? (string) $enregistre[ $cle ]
        : (string) ( $organisme[ $cle ] ?? '' );
    }
    return $out;
  }

  /**
   * ACDC 3.25.283 — Enregistre l'identité déclarante et la règle de
   * rattachement des prestations au BPF de l'organisme.
   */
  public function handle_save_external_identity() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_save_external_identity' );

    $saisi = isset( $_POST['declarant'] ) && is_array( $_POST['declarant'] )
      ? wp_unslash( $_POST['declarant'] )
      : array();

    $propre = array();
    foreach ( $saisi as $cle => $valeur ) {
      $propre[ sanitize_key( $cle ) ] = sanitize_text_field( (string) $valeur );
    }
    update_option( 'acdc_of_external_declarant', $propre, false );

    /* Une case décochée n'est pas transmise : c'est bien son absence qui vaut
       « non », et non une valeur qu'on irait chercher. */
    update_option( 'acdc_of_bpf_include_external', empty( $_POST['bpf_include_external'] ) ? '0' : '1', false );

    $this->redirect_to_portal(
      'external_missions',
      empty( $_POST['bpf_include_external'] )
        ? 'Enregistré. Ces prestations sont désormais hors du BPF de l’organisme et disposent du leur.'
        : 'Enregistré. Ces prestations restent intégrées au BPF de l’organisme.',
      'success'
    );
  }

  /**
   * ACDC 3.25.283 — LE BPF DU FORMATEUR, SUR LE MÊME CERFA QUE CELUI DE
   * L'ORGANISME.
   *
   * Deux numéros de déclaration d'activité, deux déclarations, un seul
   * formulaire officiel : on réutilise donc le générateur existant, avec une
   * autre identité et d'autres chiffres. Écrire un second composeur de PDF
   * ferait diverger deux rendus du même document dès la première correction.
   *
   * Le cadre D — les charges — reste vide : elles viennent de la comptabilité
   * du déclarant, que cette application ne tient pas. Une case vide se voit et
   * se complète ; une case remplie d'un chiffre inventé ne se voit pas.
   */
  public function handle_generate_external_bpf() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_generate_external_bpf' );

    /* ACDC 3.25.284 — L'EXERCICE NE SUIT PAS TOUJOURS L'ANNÉE CIVILE.
       Celui de la structure court du 23 avril au 22 avril : borner au 1er
       janvier aurait fait tomber une intervention de novembre dans le bon
       exercice par hasard, et une intervention de mars dans le mauvais. Le PDF
       n'aurait rien signalé — dates justes en en-tête, chiffres pris ailleurs.
       On lit donc les bornes réglées, par la même fonction que le BPF de
       l'organisme : deux exercices bornés différemment produiraient deux
       déclarations qui ne se recoupent pas, sans qu'aucune n'ait l'air fausse. */
    $annee = isset( $_REQUEST['bpf_year'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['bpf_year'] ) ) : '';
    if ( ! preg_match( '/^\d{4}$/', $annee ) ) {
      $annee = (string) $this->acdc_bpf_exercice_courant();
    }
    $bornes = $this->acdc_bpf_exercice_dates( (int) $annee );
    $debut  = $bornes['start_sql'];
    $fin    = $bornes['end_sql'];

    $records = \ACDC\Support\ExternalBpf::forPeriod(
      (array) get_option( 'acdc_of_external_mission_records', array() ),
      $debut,
      $fin
    );
    if ( empty( $records ) ) {
      $this->redirect_to_portal(
        'external_missions',
        'Aucune prestation sur l’exercice ' . $annee . ' (' . $bornes['start'] . ' au ' . $bornes['end'] . ') : rien à déclarer.',
        'error'
      );
      return;
    }

    $c = \ACDC\Support\ExternalBpf::frameC( $records );
    $e = \ACDC\Support\ExternalBpf::frameE( $records );
    $f = \ACDC\Support\ExternalBpf::frameF( $records );
    $g = \ACDC\Support\ExternalBpf::frameG( $records );

    $id = $this->get_external_declarant_identity();

    $payload = array(
      'org'    => array(
        'nda'               => (string) ( $id['activity_declaration_number'] ?? '' ),
        'siret'             => (string) ( $id['siret_identification'] ?? '' ),
        'naf_code'          => (string) ( $id['naf_code'] ?? '' ),
        'legal_form'        => (string) ( $id['legal_form'] ?? '' ),
        'enterprise'        => (string) ( $id['enterprise'] ?? '' ),
        'address'           => trim( ( $id['address'] ?? '' ) . ' ' . ( $id['postal_code'] ?? '' ) . ' ' . ( $id['city'] ?? '' ) ),
        'phone'             => (string) ( $id['enterprise_contact_phone'] ?? '' ),
        'email'             => (string) ( $id['enterprise_contact_email'] ?? '' ),
        'city'              => (string) ( $id['city'] ?? '' ),
        'dirigeant_nom'     => (string) ( $id['enterprise'] ?? '' ),
        'dirigeant_qualite' => (string) ( $id['legal_form'] ?? '' ),
      ),
      'period' => array(
        'start'           => $bornes['start'],
        'end'             => $bornes['end'],
        'date_generation' => date_i18n( 'd/m/Y' ),
      ),
      'data'   => array(
        'has_distance'    => true,
        'c1'  => round( $c['c1'], 0 ),  'ca' => 0, 'cb' => 0, 'cc' => 0, 'cd' => 0,
        'ce'  => 0, 'cf' => 0, 'cg' => 0, 'ch' => 0, 'c2' => 0, 'c3' => 0, 'c4' => 0,
        'c5'  => 0, 'c6' => 0,
        'c7'  => round( $c['c7'], 0 ),
        'c8'  => 0,
        'c9'  => round( $c['c9'], 0 ),
        'c10' => round( $c['c10'], 0 ),
        'c11' => round( $c['c11'], 0 ),
        'c_total' => round( $c['total'], 0 ),
        'pct_ca'  => 100,
        /* Le cadre D vient de la comptabilité du déclarant : on ne l'invente
           pas. Vide, il se voit et se complète à la main. */
        'd_total' => 0, 'd_salaires' => 0, 'd_achats' => 0,
        'e_nb_internes' => (int) $e['nb_internes'],
        'e_h_internes'  => round( $e['heures_internes'], 0 ),
        'e_nb_externes' => 0,
        'e_h_externes'  => 0,
        'f1_salaries'     => (int) $f['salaries'],
        'f1_apprentis'    => (int) $f['apprentis'],
        'f1_demandeurs'   => (int) $f['demandeurs'],
        'f1_particuliers' => (int) $f['particuliers'],
        'f1_independants' => (int) $f['independants'],
        'f1_autres'       => (int) $f['autres'],
        'f1_total_heures' => round( $f['heures'], 0 ),
        /* La ventilation par spécialité n'est pas tenue sur ces fiches : la
           case reste vide plutôt que de porter une spécialité supposée. */
        'f4' => array(),
        'g_nb_stag' => (int) $g['nb_stagiaires'],
        'g_heures'  => round( $g['heures'], 0 ),
      ),
    );

    $generateur = plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'assets/cerfa/cerfa_bpf_generator.php';
    if ( ! file_exists( $generateur ) ) {
      $this->redirect_to_portal( 'external_missions', 'Le composeur de Cerfa est introuvable.', 'error' );
      return;
    }
    require_once $generateur;

    $blank_url  = method_exists( $this, 'get_bpf_cerfa_blank_url' ) ? $this->get_bpf_cerfa_blank_url() : '';
    $blank_path = '';
    if ( ! empty( $blank_url ) ) {
      $uploads    = wp_upload_dir();
      $base_up    = trailingslashit( $uploads['baseurl'] );
      $base_site  = trailingslashit( site_url() );
      if ( 0 === strpos( $blank_url, $base_up ) ) {
        $blank_path = $uploads['basedir'] . '/' . ltrim( substr( $blank_url, strlen( $base_up ) ), '/' );
      } elseif ( 0 === strpos( $blank_url, $base_site ) ) {
        $blank_path = ABSPATH . ltrim( substr( $blank_url, strlen( $base_site ) ), '/' );
      }
    }
    if ( '' === $blank_path || ! file_exists( $blank_path ) ) {
      $this->redirect_to_portal( 'external_missions', 'Le Cerfa vierge est introuvable : déposez-le dans les réglages du BPF.', 'error' );
      return;
    }

    $uploads_c = wp_upload_dir();
    $dir_path  = trailingslashit( $uploads_c['basedir'] ) . 'acdc-bpf/';
    $dir_url   = trailingslashit( $uploads_c['baseurl'] ) . 'acdc-bpf/';
    wp_mkdir_p( $dir_path );
    $sortie = $dir_path . 'cerfa-bpf-prestations-' . sanitize_file_name( $annee ) . '-' . date_i18n( 'Ymd-His' ) . '.pdf';

    $gen = new ACDC_Cerfa_BPF_Generator();
    $gen->parent_instance = $this;
    if ( ! $gen->generate( $blank_path, $payload, $sortie ) ) {
      $this->redirect_to_portal( 'external_missions', 'La composition du Cerfa a échoué.', 'error' );
      return;
    }

    wp_safe_redirect( $dir_url . basename( $sortie ) );
    exit;
  }

  public function handle_save_external_mission() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_save_external_mission' );

    $input       = isset( $_POST['mission'] ) && is_array( $_POST['mission'] ) ? wp_unslash( $_POST['mission'] ) : array();
    $mission_id  = isset( $_POST['mission_id'] ) ? sanitize_text_field( wp_unslash( $_POST['mission_id'] ) ) : '';

    $commanditaire   = sanitize_text_field( $input['commanditaire'] ?? '' );
    $start_date      = sanitize_text_field( $input['start_date'] ?? '' );
    $end_date        = sanitize_text_field( $input['end_date'] ?? '' );
    $nb_stagiaires   = max( 0, (int) ( $input['nb_stagiaires'] ?? 0 ) );
    $heures_par_stag = (float) str_replace( ',', '.', $input['heures_par_stagiaire'] ?? '0' );
    $heures_total    = (float) str_replace( ',', '.', $input['heures_total'] ?? '0' );
    $ca_ht           = (float) str_replace( array( ',', ' ', "\xc2\xa0", '€' ), array( '.', '', '', '' ), $input['ca_ht'] ?? '0' );
    $tarif_horaire   = (float) str_replace( array( ',', ' ', "\xc2\xa0", '€' ), array( '.', '', '', '' ), $input['tarif_horaire'] ?? '0' );
    $type_public     = sanitize_text_field( $input['type_public'] ?? '' );
    $modalite        = sanitize_text_field( $input['modalite'] ?? '' );
    $financement     = sanitize_text_field( $input['financement'] ?? '' );
    $statut          = sanitize_text_field( $input['statut'] ?? 'OK' );
    $notes           = sanitize_textarea_field( $input['notes'] ?? '' );

    if ( '' === $commanditaire || '' === $start_date ) {
      $this->redirect_to_portal( 'external_missions', 'Commanditaire et date de début obligatoires.', 'error', array( 'action' => ( '' !== $mission_id ? 'edit' : 'new' ), 'item_id' => $mission_id ) );
      return;
    }

    // Convertir start/end → YYYY-MM-DD pour le tri et le BPF
    $to_sql_date = function( $d ) {
      if ( preg_match( '#^(\d{2})/(\d{2})/(\d{4})$#', $d, $m ) ) { return $m[3] . '-' . $m[2] . '-' . $m[1]; }
      if ( preg_match( '#^\d{4}-\d{2}-\d{2}$#', $d ) ) { return $d; }
      return '';
    };
    $start_sql = $to_sql_date( $start_date );
    $end_sql   = $to_sql_date( $end_date ) ?: $start_sql;

    $records = $this->get_external_mission_records();

    $record = array(
      'id'                  => '' !== $mission_id ? $mission_id : uniqid( 'em_', true ),
      'commanditaire'       => $commanditaire,
      'start_date'          => $start_sql,
      'end_date'            => $end_sql,
      'start_date_display'  => $start_date,
      'end_date_display'    => $end_date,
      'nb_stagiaires'       => $nb_stagiaires,
      'heures_par_stagiaire'=> $heures_par_stag,
      'heures_total'        => $heures_total > 0 ? $heures_total : round( $heures_par_stag * $nb_stagiaires, 2 ),
      'ca_ht'               => $ca_ht,
      'tarif_horaire'       => $tarif_horaire,
      'type_public'         => $type_public,
      'modalite'            => $modalite,
      'financement'         => $financement,
      'statut'              => $statut,
      'notes'               => $notes,
      'created_at'          => current_time( 'mysql' ),
    );

    if ( '' !== $mission_id ) {
      $updated = false;
      foreach ( $records as $i => $r ) {
        if ( (string) ( $r['id'] ?? '' ) === $mission_id ) {
          $record['created_at'] = $r['created_at'] ?? current_time( 'mysql' );
          $records[ $i ] = $record;
          $updated = true;
          break;
        }
      }
      if ( ! $updated ) { $records[] = $record; }
    } else {
      $records[] = $record;
    }

    // Trier par date décroissante
    usort( $records, function( $a, $b ) {
      return strcmp( (string) ( $b['start_date'] ?? '' ), (string) ( $a['start_date'] ?? '' ) );
    } );
    $this->save_external_mission_records( $records );

    $msg = '' !== $mission_id ? 'Prestation mise à jour.' : 'Prestation enregistrée.';
    $this->redirect_to_portal( 'external_missions', $msg, 'success' );
  }


  public function handle_delete_external_mission() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    $mission_id = isset( $_GET['mission_id'] ) ? sanitize_text_field( wp_unslash( $_GET['mission_id'] ) ) : '';
    check_admin_referer( 'acdc_delete_external_mission_' . $mission_id );
    $__acdc_avant = count( (array) $this->get_external_mission_records() );
    $records = array_values( array_filter( $this->get_external_mission_records(), function( $r ) use ( $mission_id ) {
      return (string) ( $r['id'] ?? '' ) !== $mission_id;
    } ) );
    $this->save_external_mission_records( $records );
    /* ACDC 3.25.301 — On compare le nombre avant et après : une fiche
       introuvable produit « error », et non une suppression imaginaire. */
    $this->log_action_event( 'suppression_external_mission', 'external_mission', 0, count( (array) $records ) < $__acdc_avant ? 'success' : 'error', array( 'reference' => (string) $mission_id ) );
    $this->redirect_to_portal( 'external_missions', 'Prestation supprimée.', 'success' );
  }


  public function handle_import_external_missions_csv() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_import_external_missions_csv' );

    if ( empty( $_FILES['csv_file']['name'] ) ) {
      $this->redirect_to_portal( 'external_missions', 'Veuillez sélectionner un fichier CSV.', 'error' );
      return;
    }

    $tmp = isset( $_FILES['csv_file']['tmp_name'] ) ? (string) $_FILES['csv_file']['tmp_name'] : '';
    if ( ! $tmp || ! file_exists( $tmp ) ) {
      $this->redirect_to_portal( 'external_missions', 'Fichier CSV inaccessible.', 'error' );
      return;
    }

    $handle = fopen( $tmp, 'r' );
    if ( ! $handle ) {
      $this->redirect_to_portal( 'external_missions', 'Impossible de lire le fichier CSV.', 'error' );
      return;
    }

    // Détecter séparateur
    $first_line = fgets( $handle );
    rewind( $handle );
    $sep = ( substr_count( $first_line, ';' ) > substr_count( $first_line, ',' ) ) ? ';' : ',';

    // Lire en-têtes
    $headers = fgetcsv( $handle, 0, $sep );
    if ( ! $headers ) { fclose( $handle ); $this->redirect_to_portal( 'external_missions', 'CSV vide ou invalide.', 'error' ); return; }
    $headers = array_map( function( $h ) { return strtolower( trim( str_replace( "\xef\xbb\xbf", '', $h ) ) ); }, $headers );

    // Mapping colonnes Notion → champs plugin
    $col_map = array(
      "nom de l'action" => 'commanditaire',
      'nom'             => 'commanditaire',
      'commanditaire'   => 'commanditaire',
      'période'         => 'periode_raw',
      'periode'         => 'periode_raw',
      'nombre de stagiaires' => 'nb_stagiaires',
      'stagiaires'      => 'nb_stagiaires',
      'heures de formation'  => 'heures_par_stagiaire',
      'heures de form.'      => 'heures_par_stagiaire',
      'heures total de formation' => 'heures_total',
      'heures total'    => 'heures_total',
      "ca généré (€)"   => 'ca_ht',
      "ca généré"       => 'ca_ht',
      'tarif horaire'   => 'tarif_horaire',
      'type de public'  => 'type_public',
      'type public'     => 'type_public',
      'modalité'        => 'modalite',
      'modalite'        => 'modalite',
      'financement'     => 'financement',
      'statut'          => 'statut',
      'données complètes ?' => 'statut',
      'notes'           => 'notes',
    );

    $records_existing = $this->get_external_mission_records();
    $imported = 0;

    while ( ( $row = fgetcsv( $handle, 0, $sep ) ) !== false ) {
      if ( empty( array_filter( $row ) ) ) { continue; }
      $data = array();
      foreach ( $headers as $i => $h ) {
        $field = $col_map[ $h ] ?? null;
        if ( $field ) { $data[ $field ] = isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : ''; }
      }
      if ( empty( $data['commanditaire'] ) ) { continue; }

      // Parser la période Notion (ex: "25 juillet 2025" ou "17 juin 2025 → 22 juillet 2025")
      $start_sql = ''; $end_sql = ''; $start_display = ''; $end_display = '';
      $periode_raw = $data['periode_raw'] ?? '';
      if ( '' !== $periode_raw ) {
        $mois_fr = array( 'janvier'=>'01','février'=>'02','fevrier'=>'02','mars'=>'03','avril'=>'04','mai'=>'05','juin'=>'06','juillet'=>'07','août'=>'08','aout'=>'08','septembre'=>'09','octobre'=>'10','novembre'=>'11','décembre'=>'12','decembre'=>'12' );
        $parse_fr_date = function( $s ) use ( $mois_fr ) {
          $s = trim( $s );
          if ( preg_match( '/^(\d{1,2})\s+(\S+)\s+(\d{4})$/', $s, $m ) ) {
            $month = $mois_fr[ strtolower( $m[2] ) ] ?? '01';
            $sql   = $m[3] . '-' . $month . '-' . str_pad( $m[1], 2, '0', STR_PAD_LEFT );
            $disp  = str_pad( $m[1], 2, '0', STR_PAD_LEFT ) . '/' . $month . '/' . $m[3];
            return array( $sql, $disp );
          }
          return array( '', '' );
        };
        if ( false !== strpos( $periode_raw, '→' ) ) {
          $parts = explode( '→', $periode_raw );
          list( $start_sql, $start_display ) = $parse_fr_date( $parts[0] );
          list( $end_sql,   $end_display   ) = $parse_fr_date( $parts[1] ?? '' );
        } else {
          list( $start_sql, $start_display ) = $parse_fr_date( $periode_raw );
          $end_sql = $start_sql; $end_display = $start_display;
        }
      }

      // Nettoyer les valeurs numériques (supprimer €, espaces insécables, virgules décimales)
      $clean_num = function( $v ) {
        return (float) str_replace( array( ',', ' ', "\xc2\xa0", '€', "\u{202F}" ), array( '.', '', '', '', '' ), $v );
      };

      $record = array(
        'id'                   => uniqid( 'em_', true ),
        'commanditaire'        => sanitize_text_field( $data['commanditaire'] ),
        'start_date'           => $start_sql,
        'end_date'             => $end_sql ?: $start_sql,
        'start_date_display'   => $start_display,
        'end_date_display'     => $end_display ?: $start_display,
        'nb_stagiaires'        => max( 0, (int) ( $data['nb_stagiaires'] ?? 0 ) ),
        'heures_par_stagiaire' => $clean_num( $data['heures_par_stagiaire'] ?? '0' ),
        'heures_total'         => $clean_num( $data['heures_total'] ?? '0' ),
        'ca_ht'                => $clean_num( $data['ca_ht'] ?? '0' ),
        'tarif_horaire'        => $clean_num( $data['tarif_horaire'] ?? '0' ),
        'type_public'          => sanitize_text_field( $data['type_public'] ?? '' ),
        'modalite'             => sanitize_text_field( $data['modalite'] ?? '' ),
        'financement'          => sanitize_text_field( $data['financement'] ?? '' ),
        'statut'               => sanitize_text_field( '' !== ( $data['statut'] ?? '' ) && false !== stripos( $data['statut'] ?? '', 'Incomplet' ) ? 'Incomplet' : 'OK' ),
        'notes'                => sanitize_textarea_field( $data['notes'] ?? '' ),
        'created_at'           => current_time( 'mysql' ),
      );
      if ( $record['heures_total'] <= 0 && $record['heures_par_stagiaire'] > 0 ) {
        $record['heures_total'] = round( $record['heures_par_stagiaire'] * $record['nb_stagiaires'], 2 );
      }
      $records_existing[] = $record;
      $imported++;
    }
    fclose( $handle );

    // Trier par date décroissante
    usort( $records_existing, function( $a, $b ) {
      return strcmp( (string) ( $b['start_date'] ?? '' ), (string) ( $a['start_date'] ?? '' ) );
    } );
    $this->save_external_mission_records( $records_existing );

    $this->redirect_to_portal( 'external_missions', $imported . ' prestation(s) import' . "\xc3\xa9" . 'e(s).', 'success' );
  }

  /* =========================================================
   * ACDC 3.22.18 - H4/H5 : Handlers registre reclamations
   * ========================================================= */

  public function handle_save_complaint() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Acces refus' . "\xc3\xa9" . '.' ); }
    check_admin_referer( 'acdc_save_complaint' );
    $id   = isset( $_POST['complaint_id'] ) ? (int) $_POST['complaint_id'] : 0;
    $data = isset( $_POST['complaint'] ) && is_array( $_POST['complaint'] ) ? wp_unslash( $_POST['complaint'] ) : array();
    $front_redirect = ! empty( $_POST['_front_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['_front_redirect'] ) ) : '';
    $complaint_id = $this->save_complaint( $data, $id );
    if ( $front_redirect ) {
      wp_safe_redirect( $complaint_id ? add_query_arg( 'cpl_updated', '1', $front_redirect ) : add_query_arg( 'cpl_error', '1', $front_redirect ) );
    } else {
      $base = admin_url( 'admin.php?page=acdc-of-complaints' );
      wp_safe_redirect( $complaint_id ? add_query_arg( array( 'action' => 'view', 'complaint_id' => $complaint_id, 'updated' => '1' ), $base ) : add_query_arg( 'error', '1', $base ) );
    }
    exit;
  }

  public function handle_delete_complaint() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Acces refus' . "\xc3\xa9" . '.' ); }
    check_admin_referer( 'acdc_delete_complaint' );
    $id             = isset( $_POST['complaint_id'] ) ? (int) $_POST['complaint_id'] : 0;
    $front_redirect = ! empty( $_POST['_front_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['_front_redirect'] ) ) : '';
    if ( $id > 0 ) {
      $__acdc_supprime = $this->delete_complaint( $id );
      /* ACDC 3.25.301 — delete_complaint() rend le résultat de la base : on le
         LIT au lieu de l'affirmer. Une première version écrivait « success »
         sans rien regarder — le défaut même que ce journal doit empêcher. */
      $this->log_action_event( 'suppression_complaint', 'complaint', (int) $id, $__acdc_supprime ? 'success' : 'error', array( 'lignes' => (int) $__acdc_supprime ) );
    }
    wp_safe_redirect( $front_redirect ? $front_redirect : admin_url( 'admin.php?page=acdc-of-complaints&deleted=1' ) );
    exit;
  }

  public function handle_acknowledge_complaint() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Acces refus' . "\xc3\xa9" . '.' ); }
    check_admin_referer( 'acdc_acknowledge_complaint' );
    $id             = isset( $_POST['complaint_id'] ) ? (int) $_POST['complaint_id'] : 0;
    $front_redirect = ! empty( $_POST['_front_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['_front_redirect'] ) ) : '';
    if ( $id > 0 ) { $this->acknowledge_complaint( $id ); }
    wp_safe_redirect( $front_redirect ? $front_redirect : add_query_arg( array( 'action' => 'view', 'complaint_id' => $id, 'ack' => '1' ), admin_url( 'admin.php?page=acdc-of-complaints' ) ) );
    exit;
  }

  public function handle_close_complaint() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Acces refus' . "\xc3\xa9" . '.' ); }
    check_admin_referer( 'acdc_close_complaint' );
    global $wpdb;
    $id             = isset( $_POST['complaint_id'] ) ? (int) $_POST['complaint_id'] : 0;
    $front_redirect = ! empty( $_POST['_front_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['_front_redirect'] ) ) : '';
    if ( $id > 0 ) {
      $now = current_time( 'mysql' );
      $wpdb->update( $this->complaint_table, array( 'status' => 'cloturee', 'closed_at' => $now, 'updated_at' => $now ), array( 'id' => $id ), null, array( '%d' ) );
    }
    $default_redirect = is_admin() ? admin_url( 'admin.php?page=acdc-of-complaints' ) : $this->portal_page_url( array( 'tab' => 'quality', 'cpl_closed' => '1' ) );
    wp_safe_redirect( $front_redirect ? $front_redirect : $default_redirect );
    exit;
  }

  public function handle_export_complaints_pdf() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_export_complaints_pdf' );
    $filters = array();
    if ( ! empty( $_GET['status'] ) )    { $filters['status']    = sanitize_text_field( wp_unslash( $_GET['status'] ) ); }
    if ( ! empty( $_GET['severity'] ) )  { $filters['severity']  = sanitize_text_field( wp_unslash( $_GET['severity'] ) ); }
    if ( ! empty( $_GET['date_from'] ) ) { $filters['date_from'] = sanitize_text_field( wp_unslash( $_GET['date_from'] ) ); }
    if ( ! empty( $_GET['date_to'] ) )   { $filters['date_to']   = sanitize_text_field( wp_unslash( $_GET['date_to'] ) ); }
    $complaints      = $this->get_complaints( $filters );
    $branding        = method_exists( $this, 'get_branding_options' ) ? $this->get_branding_options() : array();
    $org_name        = ! empty( $branding['display_name'] ) ? $branding['display_name'] : get_bloginfo( 'name' );
    $logo_url        = ! empty( $branding['logo_url'] ) ? $branding['logo_url'] : '';
    $date_export     = wp_date( 'd/m/Y' );
    $date_file       = wp_date( 'Y-m-d' );
    $type_labels     = $this->get_complaint_type_options();
    $source_labels   = $this->get_complaint_source_options();
    $severity_labels = $this->get_complaint_severity_options();
    $status_labels   = $this->get_complaint_status_options();
    $nb_total        = count( $complaints );
    $nb_unack        = 0;
    $nb_open         = 0;
    foreach ( $complaints as $c ) {
      if ( empty( $c->acknowledged_at ) ) { $nb_unack++; }
      if ( in_array( $c->status, array( 'ouverte', 'en_cours' ), true ) ) { $nb_open++; }
    }
    header( 'Content-Type: text/html; charset=utf-8' );
    header( 'Content-Disposition: inline; filename="registre-reclamations-' . $date_file . '.html"' );
    $rows_html = '';
    if ( empty( $complaints ) ) {
      $rows_html = '<tr><td colspan="9" style="text-align:center;padding:20px;font-style:italic;color:#666;">Aucune r&eacute;clamation sur la p&eacute;riode s&eacute;lectionn&eacute;e.</td></tr>';
    } else {
      foreach ( $complaints as $c ) {
        $sev_bg  = 'grave' === $c->severity ? '#fde8e8' : ( 'moderee' === $c->severity ? '#fef3cd' : '#e8f5e9' );
        $sev_col = 'grave' === $c->severity ? '#c0392b' : ( 'moderee' === $c->severity ? '#856404' : '#1a6b34' );
        $sta_bg  = 'ouverte' === $c->status ? '#dbeafe' : ( 'cloturee' === $c->status ? '#f3f4f6' : '#ede9fe' );
        $sta_col = 'ouverte' === $c->status ? '#1e40af' : ( 'cloturee' === $c->status ? '#6b7280' : '#5b21b6' );
        $sev_lbl = isset( $severity_labels[ $c->severity ] ) ? $severity_labels[ $c->severity ] : $c->severity;
        $sta_lbl = isset( $status_labels[ $c->status ] )     ? $status_labels[ $c->status ]     : $c->status;
        $src_lbl = isset( $source_labels[ $c->source ] )     ? $source_labels[ $c->source ]     : $c->source;
        $typ_lbl = isset( $type_labels[ $c->type ] )         ? $type_labels[ $c->type ]         : $c->type;
        $ack_html = ! empty( $c->acknowledged_at )
          ? '<span style="color:#1a6b34;font-weight:600;">&#10003; ' . esc_html( wp_date( 'd/m/Y', strtotime( $c->acknowledged_at ) ) ) . '</span>'
          : '<span style="color:#c0392b;font-weight:700;">&#10007; Manquant</span>';
        $badge_sev = '<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;background:' . $sev_bg . ';color:' . $sev_col . ';">' . esc_html( $sev_lbl ) . '</span>';
        $badge_sta = '<span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;background:' . $sta_bg . ';color:' . $sta_col . ';">' . esc_html( $sta_lbl ) . '</span>';
        $rows_html .= '<tr>';
        $rows_html .= '<td style="text-align:center;font-weight:700;color:#8b5b23;">#' . (int) $c->id . '</td>';
        $rows_html .= '<td>' . esc_html( wp_date( 'd/m/Y', strtotime( $c->opened_at ) ) ) . '</td>';
        $rows_html .= '<td>' . esc_html( $src_lbl ) . '</td>';
        $rows_html .= '<td>' . esc_html( $typ_lbl ) . '</td>';
        $rows_html .= '<td>' . $badge_sev . '</td>';
        $rows_html .= '<td style="max-width:200px;">' . esc_html( mb_substr( $c->summary, 0, 120 ) ) . ( mb_strlen( $c->summary ) > 120 ? '&hellip;' : '' ) . '</td>';
        $rows_html .= '<td>' . $badge_sta . '</td>';
        $rows_html .= '<td>' . $ack_html . '</td>';
        $rows_html .= '<td style="max-width:160px;font-size:10px;">' . esc_html( mb_substr( $c->corrective_action, 0, 100 ) ) . ( mb_strlen( $c->corrective_action ) > 100 ? '&hellip;' : '' ) . '</td>';
        $rows_html .= '</tr>';
      }
    }
    $logo_html = $logo_url ? '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $org_name ) . '" style="height:48px;max-width:180px;object-fit:contain;">' : '<div style="width:48px;height:48px;background:#0f2c52;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#d6a353;font-weight:900;font-size:18px;">A</div>';
    $stat_unack_color = $nb_unack > 0 ? '#c0392b' : '#1a6b34';
    $stat_open_color  = $nb_open  > 0 ? '#1e40af' : '#1a6b34';
    echo '<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Registre des r&eacute;clamations &mdash; ' . esc_html( $org_name ) . '</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#1a202c;background:#fff;padding:0;}
  .page{max-width:1100px;margin:0 auto;padding:24px 28px;}
  /* En-t\xc3\xaate */
  .header{display:flex;align-items:center;justify-content:space-between;padding-bottom:14px;border-bottom:3px solid #0f2c52;margin-bottom:16px;}
  .header-left{display:flex;align-items:center;gap:14px;}
  .header-org{font-size:15px;font-weight:700;color:#0f2c52;}
  .header-sub{font-size:11px;color:#4b5d76;margin-top:3px;}
  .header-right{text-align:right;font-size:10px;color:#4b5d76;line-height:1.6;}
  /* Stats */
  .stats{display:flex;gap:12px;margin-bottom:16px;}
  .stat{flex:1;border:1px solid #e2e8f0;border-radius:8px;padding:10px 14px;}
  .stat-val{font-size:20px;font-weight:700;color:#0f2c52;}
  .stat-lbl{font-size:10px;color:#4b5d76;margin-top:2px;}
  /* Tableau */
  table{width:100%;border-collapse:collapse;font-size:10.5px;}
  thead tr{background:#0f2c52;color:#fff;}
  thead th{padding:8px 10px;text-align:left;font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;}
  tbody tr:nth-child(even){background:#f8fafc;}
  tbody tr:hover{background:#fef6e4;}
  tbody td{padding:7px 10px;border-bottom:1px solid #e2e8f0;vertical-align:middle;}
  /* Pied */
  .footer{margin-top:20px;padding-top:10px;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;}
  .footer-left{font-size:9px;color:#888;}
  .footer-right{font-size:9px;color:#888;}
  /* Bouton impression */
  .print-bar{background:#0f2c52;padding:12px 28px;display:flex;align-items:center;justify-content:space-between;gap:12px;position:sticky;top:0;z-index:100;}
  .print-bar span{color:#d6a353;font-size:12px;font-weight:600;}
  .btn-print{background:#d6a353;color:#0f2c52;border:none;padding:9px 22px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;letter-spacing:.02em;}
  .btn-print:hover{background:#c49040;}
  /* Impression */
  @media print{
    @page{size:A4 landscape;margin:12mm 10mm;}
    .print-bar{display:none!important;}
    body{padding:0;}
    .page{max-width:100%;padding:0;}
    table{font-size:9px;}
    thead th,tbody td{padding:5px 6px;}
    .stats{gap:8px;}
    .stat{padding:7px 10px;}
  }
</style>
</head>
<body>
<div class="print-bar">
  <span>Registre des r&eacute;clamations &mdash; ' . esc_html( $org_name ) . '</span>
  <button class="btn-print" onclick="window.print()">&#128438; Imprimer / Enregistrer en PDF</button>
</div>
<div class="page">
  <div class="header">
    <div class="header-left">
      ' . $logo_html . '
      <div>
        <div class="header-org">' . esc_html( $org_name ) . '</div>
        <div class="header-sub">Registre officiel des r&eacute;clamations &mdash; Indicateur 31 Qualiopi</div>
      </div>
    </div>
    <div class="header-right">
      Export du ' . esc_html( $date_export ) . '<br>
      R&eacute;f&eacute;rentiel Qualiopi v9 &mdash; 8 janvier 2024<br>
      <strong>' . $nb_total . ' entr&eacute;e(s)</strong>
    </div>
  </div>
  <div class="stats">
    <div class="stat"><div class="stat-val">' . $nb_total . '</div><div class="stat-lbl">R&eacute;clamations totales</div></div>
    <div class="stat"><div class="stat-val" style="color:' . $stat_open_color . ';">' . $nb_open . '</div><div class="stat-lbl">Ouvertes / en cours</div></div>
    <div class="stat"><div class="stat-val" style="color:' . $stat_unack_color . ';">' . $nb_unack . '</div><div class="stat-lbl">Sans accus&eacute; de r&eacute;ception</div></div>
    <div class="stat"><div class="stat-val" style="color:#1a6b34;">' . ( $nb_total - $nb_open ) . '</div><div class="stat-lbl">Cl&ocirc;tur&eacute;es</div></div>
  </div>
  <table>
    <thead>
      <tr>
        <th style="width:40px;">#</th>
        <th style="width:76px;">Date</th>
        <th style="width:90px;">Source</th>
        <th style="width:90px;">Type</th>
        <th style="width:80px;">Gravit&eacute;</th>
        <th>R&eacute;sum&eacute;</th>
        <th style="width:80px;">Statut</th>
        <th style="width:110px;">Accus&eacute; r&eacute;ception</th>
        <th style="width:160px;">Mesure corrective</th>
      </tr>
    </thead>
    <tbody>' . $rows_html . '</tbody>
  </table>
  <div class="footer">
    <div class="footer-left">Document confidentiel &mdash; usage interne et audit Qualiopi uniquement &mdash; ' . esc_html( $org_name ) . '</div>
    <div class="footer-right">G&eacute;n&eacute;r&eacute; le ' . esc_html( $date_export ) . ' &mdash; ACDC Formation SAAS</div>
  </div>
</div>
</body>
</html>';
    exit;
  }

  public function handle_delete_improvement() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Acc' . "\xc3\xa8" . 's refus' . "\xc3\xa9" . '.' ); }
    check_admin_referer( 'acdc_delete_improvement' );
    $id             = isset( $_POST['improvement_id'] ) ? sanitize_text_field( wp_unslash( $_POST['improvement_id'] ) ) : '';
    $front_redirect = ! empty( $_POST['_front_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['_front_redirect'] ) ) : '';
    if ( $id !== '' ) {
      $records = $this->get_continuous_improvement_records();
      $__acdc_avant = count( (array) $records );
      /* Chercher par id ou par clé */
      foreach ( $records as $k => $r ) {
        if ( ( isset( $r['id'] ) && (string) $r['id'] === $id ) || (string) $k === $id ) {
          unset( $records[ $k ] );
          break;
        }
      }
      $this->save_continuous_improvement_records( $records );
      /* ACDC 3.25.300 — On compare le nombre avant et après : une fiche
         introuvable produit « error », pas une suppression imaginaire. */
      $this->log_action_event( 'suppression_improvement', 'improvement', 0, count( (array) $records ) < $__acdc_avant ? 'success' : 'error', array( 'reference' => (string) $id, 'restants' => count( (array) $records ) ) );
    }
    wp_safe_redirect( $front_redirect ? $front_redirect : $this->portal_page_url( array( 'tab' => 'continuous_improvement' ) ) );
    exit;
  }

  /* =========================================================
   * ACDC 3.24.6 - R1 : Conseil de perfectionnement (ind. 20)
   * ========================================================= */

  public function handle_save_perfectionnement_member() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
    check_admin_referer( 'acdc_save_cp_member' );
    $input  = isset( $_POST['cp_member'] ) && is_array( $_POST['cp_member'] ) ? wp_unslash( $_POST['cp_member'] ) : array();
    $name   = sanitize_text_field( $input['name'] ?? '' );
    $role   = sanitize_text_field( $input['role'] ?? '' );
    $email  = sanitize_email( $input['email'] ?? '' );
    $mid    = sanitize_text_field( $input['id'] ?? '' );
    if ( '' === $name || '' === $role ) {
      $this->redirect_to_portal( 'conseil_perfectionnement', 'Nom et rôle sont obligatoires.', 'error', array( 'cp_action' => 'add_member' ) );
    }
    $records = $this->get_perfectionnement_members();
    if ( '' !== $mid ) {
      $found = false;
      foreach ( $records as $k => $r ) {
        if ( (string) ( $r['id'] ?? '' ) === $mid ) {
          $records[ $k ] = array( 'id' => $mid, 'name' => $name, 'role' => $role, 'email' => $email );
          $found = true;
          break;
        }
      }
      if ( ! $found ) {
        $records[] = array( 'id' => (string) time(), 'name' => $name, 'role' => $role, 'email' => $email );
      }
    } else {
      $records[] = array( 'id' => (string) time(), 'name' => $name, 'role' => $role, 'email' => $email );
    }
    $this->save_perfectionnement_members( $records );
    $this->redirect_to_portal( 'conseil_perfectionnement', 'Membre enregistré.', 'success' );
  }

  public function handle_delete_perfectionnement_member() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
    $mid = isset( $_GET['cp_mid'] ) ? sanitize_text_field( wp_unslash( $_GET['cp_mid'] ) ) : '';
    check_admin_referer( 'acdc_delete_cp_member_' . $mid );
    if ( '' === $mid ) {
      wp_safe_redirect( $this->portal_page_url( array( 'tab' => 'conseil_perfectionnement' ) ) );
      exit;
    }
    $records = $this->get_perfectionnement_members();
    $__acdc_avant = count( (array) $records );
    $records = array_values( array_filter( $records, function( $r ) use ( $mid ) { return (string) ( $r['id'] ?? '' ) !== $mid; } ) );
    $this->save_perfectionnement_members( $records );
    /* ACDC 3.25.300 — On compare le nombre avant et après : une fiche
       introuvable produit « error », pas une suppression imaginaire. */
    $this->log_action_event( 'suppression_perfectionnement_member', 'perfectionnement_member', 0, count( (array) $records ) < $__acdc_avant ? 'success' : 'error', array( 'reference' => (string) $mid, 'restants' => count( (array) $records ) ) );
    $this->redirect_to_portal( 'conseil_perfectionnement', 'Membre supprimé.', 'success' );
  }

  public function handle_save_perfectionnement_meeting() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
    check_admin_referer( 'acdc_save_cp_meeting' );
    $input   = isset( $_POST['cp_meeting'] ) && is_array( $_POST['cp_meeting'] ) ? wp_unslash( $_POST['cp_meeting'] ) : array();
    $date    = sanitize_text_field( $input['date'] ?? '' );
    $agenda  = sanitize_textarea_field( $input['agenda'] ?? '' );
    $notes   = sanitize_textarea_field( $input['notes'] ?? '' );
    $mid     = sanitize_text_field( $input['id'] ?? '' );
    if ( '' === $date ) {
      $this->redirect_to_portal( 'conseil_perfectionnement', 'La date est obligatoire.', 'error', array( 'cp_action' => 'add_meeting' ) );
    }
    $records = $this->get_perfectionnement_meetings();
    $row = array( 'date' => $date, 'agenda' => $agenda, 'notes' => $notes );
    if ( '' !== $mid ) {
      $found = false;
      foreach ( $records as $k => $r ) {
        if ( (string) ( $r['id'] ?? '' ) === $mid ) {
          $row['id'] = $mid;
          $records[ $k ] = $row;
          $found = true;
          break;
        }
      }
      if ( ! $found ) {
        $row['id'] = (string) time();
        $row['created_at'] = current_time( 'mysql' );
        $records[] = $row;
      }
    } else {
      $row['id'] = (string) time();
      $row['created_at'] = current_time( 'mysql' );
      $records[] = $row;
    }
    $this->save_perfectionnement_meetings( $records );
    $this->redirect_to_portal( 'conseil_perfectionnement', 'Réunion enregistrée.', 'success' );
  }

  public function handle_delete_perfectionnement_meeting() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
    $rid = isset( $_GET['cp_rid'] ) ? sanitize_text_field( wp_unslash( $_GET['cp_rid'] ) ) : '';
    check_admin_referer( 'acdc_delete_cp_meeting_' . $rid );
    if ( '' === $rid ) {
      wp_safe_redirect( $this->portal_page_url( array( 'tab' => 'conseil_perfectionnement' ) ) );
      exit;
    }
    $records = $this->get_perfectionnement_meetings();
    $__acdc_avant = count( (array) $records );
    $records = array_values( array_filter( $records, function( $r ) use ( $rid ) { return (string) ( $r['id'] ?? '' ) !== $rid; } ) );
    $this->save_perfectionnement_meetings( $records );
    /* ACDC 3.25.300 — On compare le nombre avant et après : une fiche
       introuvable produit « error », pas une suppression imaginaire. */
    $this->log_action_event( 'suppression_perfectionnement_meeting', 'perfectionnement_meeting', 0, count( (array) $records ) < $__acdc_avant ? 'success' : 'error', array( 'reference' => (string) $rid, 'restants' => count( (array) $records ) ) );
    $this->redirect_to_portal( 'conseil_perfectionnement', 'Réunion supprimée.', 'success' );
  }

  /* =========================================================
   * ACDC 3.24.9 - R3 : Réseau partenaires PSH (ind. 26)
   * ========================================================= */

  public function handle_save_psh_partner() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
    check_admin_referer( 'acdc_save_psh_partner' );
    $input   = isset( $_POST['psh_partner'] ) && is_array( $_POST['psh_partner'] ) ? wp_unslash( $_POST['psh_partner'] ) : array();
    $name    = sanitize_text_field( $input['name'] ?? '' );
    $type    = sanitize_text_field( $input['type'] ?? '' );
    $scope   = in_array( $input['scope'] ?? '', array( 'national', 'local_var' ), true ) ? $input['scope'] : 'local_var';
    $region  = sanitize_text_field( $input['region'] ?? '' );
    $contact = sanitize_text_field( $input['contact'] ?? '' );
    $phone   = sanitize_text_field( $input['phone'] ?? '' );
    $email   = sanitize_email( $input['email'] ?? '' );
    $notes   = sanitize_textarea_field( $input['notes'] ?? '' );
    $pid     = sanitize_text_field( $input['id'] ?? '' );
    if ( '' === $name || '' === $type ) {
      $this->redirect_to_portal( 'psh_partners', 'Nom et type sont obligatoires.', 'error', array( 'psh_action' => 'add' ) );
    }
    $records = $this->get_psh_partners();
    $row = array( 'name' => $name, 'type' => $type, 'scope' => $scope, 'region' => $region, 'contact' => $contact, 'phone' => $phone, 'email' => $email, 'notes' => $notes );
    if ( '' !== $pid ) {
      $found = false;
      foreach ( $records as $k => $r ) {
        if ( (string) ( $r['id'] ?? '' ) === $pid ) {
          $row['id'] = $pid;
          $records[ $k ] = $row;
          $found = true;
          break;
        }
      }
      if ( ! $found ) {
        $row['id'] = (string) time();
        $records[] = $row;
      }
    } else {
      $row['id'] = (string) time();
      $records[] = $row;
    }
    $this->save_psh_partners( $records );
    $this->redirect_to_portal( 'psh_partners', 'Partenaire enregistré.', 'success' );
  }

  public function handle_delete_psh_partner() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
    $pid = isset( $_GET['psh_pid'] ) ? sanitize_text_field( wp_unslash( $_GET['psh_pid'] ) ) : '';
    check_admin_referer( 'acdc_delete_psh_partner_' . $pid );
    if ( '' === $pid ) {
      wp_safe_redirect( $this->portal_page_url( array( 'tab' => 'psh_partners' ) ) );
      exit;
    }
    $records = $this->get_psh_partners();
    $__acdc_avant = count( (array) $records );
    $records = array_values( array_filter( $records, function( $r ) use ( $pid ) { return (string) ( $r['id'] ?? '' ) !== $pid; } ) );
    $this->save_psh_partners( $records );
    /* ACDC 3.25.300 — On compare le nombre avant et après : une fiche
       introuvable produit « error », pas une suppression imaginaire. */
    $this->log_action_event( 'suppression_psh_partner', 'psh_partner', 0, count( (array) $records ) < $__acdc_avant ? 'success' : 'error', array( 'reference' => (string) $pid, 'restants' => count( (array) $records ) ) );
    $this->redirect_to_portal( 'psh_partners', 'Partenaire supprimé.', 'success' );
  }

  public function handle_mark_improvement_done() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Acc' . "\xc3\xa8" . 's refus' . "\xc3\xa9" . '.' ); }
    check_admin_referer( 'acdc_mark_improvement_done' );
    $id             = isset( $_POST['improvement_id'] ) ? sanitize_text_field( wp_unslash( $_POST['improvement_id'] ) ) : '';
    $front_redirect = ! empty( $_POST['_front_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['_front_redirect'] ) ) : '';
    if ( $id !== '' ) {
      $records = $this->get_continuous_improvement_records();
      foreach ( $records as $k => $r ) {
        if ( ( isset( $r['id'] ) && (string) $r['id'] === $id ) || (string) $k === $id ) {
          $records[ $k ]['status']     = 'Traité';
          $records[ $k ]['updated_at'] = current_time( 'mysql' );
          break;
        }
      }
      $this->save_continuous_improvement_records( $records );
    }
    wp_safe_redirect( $front_redirect ? $front_redirect : $this->portal_page_url( array( 'tab' => 'continuous_improvement' ) ) );
    exit;
  }

  /* -----------------------------------------------------------------------
   * ACDC 3.24.13 — R-17 : Handlers locaux & équipements (indicateur 17).
   * ----------------------------------------------------------------------- */

  public function handle_save_training_site() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_save_training_site' );
    $input = isset( $_POST['ts'] ) && is_array( $_POST['ts'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['ts'] ) ) : array();
    if ( empty( $input['nom'] ) ) {
      wp_safe_redirect( $this->portal_page_url( array( 'tab' => 'training_sites', 'notice' => rawurlencode( 'Le nom du local est requis.' ), 'notice_type' => 'error' ) ) );
      exit;
    }
    $sites  = $this->get_training_sites();
    $existing_id = ! empty( $input['id'] ) ? (string) $input['id'] : '';
    $site = array(
      'id'               => $existing_id ?: 'ts_' . wp_generate_uuid4(),
      'nom'              => sanitize_text_field( $input['nom'] ?? '' ),
      'adresse'          => sanitize_text_field( $input['adresse'] ?? '' ),
      'code_postal'      => sanitize_text_field( $input['code_postal'] ?? '' ),
      'ville'            => sanitize_text_field( $input['ville'] ?? '' ),
      'capacite'         => max( 0, (int) ( $input['capacite'] ?? 0 ) ),
      'accessibilite_pmr'=> in_array( $input['accessibilite_pmr'] ?? 'non', array( 'oui', 'partielle', 'non' ), true ) ? $input['accessibilite_pmr'] : 'non',
      'equipements'      => sanitize_textarea_field( $input['equipements'] ?? '' ),
      'notes'            => sanitize_textarea_field( $input['notes'] ?? '' ),
      'updated_at'       => current_time( 'mysql' ),
    );
    $found = false;
    foreach ( $sites as &$s ) {
      if ( ( $s['id'] ?? '' ) === $existing_id && $existing_id ) {
        $s = $site; $found = true; break;
      }
    }
    unset( $s );
    if ( ! $found ) { $sites[] = $site; }
    update_option( 'acdc_of_training_sites', $sites, false );
    $redirect = is_admin()
      ? admin_url( 'admin.php?page=acdc-of-dashboard&tab=training_sites&notice=' . rawurlencode( 'Local enregistré.' ) . '&notice_type=success' )
      : $this->portal_page_url( array( 'tab' => 'training_sites', 'notice' => rawurlencode( 'Local enregistré.' ), 'notice_type' => 'success' ) );
    wp_safe_redirect( $redirect );
    exit;
  }

  public function handle_delete_training_site() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    $sid = isset( $_GET['ts_pid'] ) ? sanitize_key( rawurldecode( wp_unslash( $_GET['ts_pid'] ) ) ) : '';
    if ( ! $sid ) {
      wp_safe_redirect( $this->portal_page_url( array( 'tab' => 'training_sites' ) ) ); exit;
    }
    check_admin_referer( 'acdc_delete_training_site_' . $sid );
    $sites = $this->get_training_sites();
    $sites = array_values( array_filter( $sites, function( $s ) use ( $sid ) { return ( $s['id'] ?? '' ) !== $sid; } ) );
    $__acdc_avant = count( (array) $this->get_training_sites() );
    update_option( 'acdc_of_training_sites', $sites, false );
    /* ACDC 3.25.301 — On compare le nombre avant et après : une fiche
       introuvable produit « error », et non une suppression imaginaire. */
    $this->log_action_event( 'suppression_training_site', 'training_site', 0, count( (array) $sites ) < $__acdc_avant ? 'success' : 'error', array( 'reference' => (string) $sid ) );
    wp_safe_redirect( $this->portal_page_url( array( 'tab' => 'training_sites', 'notice' => rawurlencode( 'Local supprimé.' ), 'notice_type' => 'success' ) ) );
    exit;
  }

  /* -----------------------------------------------------------------------
   * ACDC 3.24.14 — R-28 : Handlers sous-traitants formels (indicateur 28 Qualiopi).
   * ----------------------------------------------------------------------- */

  public function handle_save_subcontractor() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    check_admin_referer( 'acdc_save_subcontractor' );
    $input = isset( $_POST['sc'] ) && is_array( $_POST['sc'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['sc'] ) ) : array();
    if ( empty( $input['nom'] ) ) {
      wp_safe_redirect( $this->portal_page_url( array( 'tab' => 'subcontractors', 'notice' => rawurlencode( 'Le nom du sous-traitant est requis.' ), 'notice_type' => 'error' ) ) );
      exit;
    }
    $allowed_types = array( 'independant', 'cabinet', 'organisme', 'autre' );
    $items       = $this->get_subcontractors();
    $existing_id = ! empty( $input['id'] ) ? (string) $input['id'] : '';
    $item = array(
      'id'             => $existing_id ?: 'sc_' . wp_generate_uuid4(),
      'nom'            => sanitize_text_field( $input['nom'] ?? '' ),
      'siret'          => preg_replace( '/[^0-9]/', '', $input['siret'] ?? '' ),
      'type'           => in_array( $input['type'] ?? 'independant', $allowed_types, true ) ? $input['type'] : 'independant',
      'qualifications' => sanitize_textarea_field( $input['qualifications'] ?? '' ),
      'date_debut'     => sanitize_text_field( $input['date_debut'] ?? '' ),
      'date_fin'       => sanitize_text_field( $input['date_fin'] ?? '' ),
      'notes'          => sanitize_textarea_field( $input['notes'] ?? '' ),
      'updated_at'     => current_time( 'mysql' ),
    );
    $found = false;
    foreach ( $items as &$it ) {
      if ( ( $it['id'] ?? '' ) === $existing_id && $existing_id ) {
        $it = $item; $found = true; break;
      }
    }
    unset( $it );
    if ( ! $found ) { $items[] = $item; }
    update_option( 'acdc_of_subcontractors', array_values( $items ), false );
    $redirect = is_admin()
      ? admin_url( 'admin.php?page=acdc-of-dashboard&tab=subcontractors&notice=' . rawurlencode( 'Sous-traitant enregistré.' ) . '&notice_type=success' )
      : $this->portal_page_url( array( 'tab' => 'subcontractors', 'notice' => rawurlencode( 'Sous-traitant enregistré.' ), 'notice_type' => 'success' ) );
    wp_safe_redirect( $redirect );
    exit;
  }

  public function handle_delete_subcontractor() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html( 'Accès refusé.' ) ); }
    $iid = isset( $_GET['sc_pid'] ) ? sanitize_key( rawurldecode( wp_unslash( $_GET['sc_pid'] ) ) ) : '';
    if ( ! $iid ) {
      wp_safe_redirect( $this->portal_page_url( array( 'tab' => 'subcontractors' ) ) ); exit;
    }
    check_admin_referer( 'acdc_delete_subcontractor_' . $iid );
    $items = $this->get_subcontractors();
    $items = array_values( array_filter( $items, function( $it ) use ( $iid ) { return ( $it['id'] ?? '' ) !== $iid; } ) );
    $__acdc_avant = count( (array) $this->get_subcontractors() );
    update_option( 'acdc_of_subcontractors', $items, false );
    /* ACDC 3.25.301 — On compare le nombre avant et après : une fiche
       introuvable produit « error », et non une suppression imaginaire. */
    $this->log_action_event( 'suppression_subcontractor', 'subcontractor', 0, count( (array) $items ) < $__acdc_avant ? 'success' : 'error', array( 'reference' => (string) $iid ) );
    wp_safe_redirect( $this->portal_page_url( array( 'tab' => 'subcontractors', 'notice' => rawurlencode( 'Sous-traitant supprimé.' ), 'notice_type' => 'success' ) ) );
    exit;
  }
}
