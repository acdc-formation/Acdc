<?php
/**
 * ACDC Documents / devis / factures — ACDC_Documents_Billing_Core_Trait.
 *
 * Extraction micro-incrémentale du sous-bloc métier :
 * documents / devis / factures.
 *
 * @since 3.15.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Documents_Billing_Core_Trait {

  /* ---------------------------------------------------------------
   * TABLE DEVIS — création + CRUD réels
   * --------------------------------------------------------------- */

  private function maybe_create_quotes_table() {
    global $wpdb;
    $table           = $this->quote_table;
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      scope VARCHAR(20) NOT NULL DEFAULT 'action',
      number VARCHAR(60) NOT NULL DEFAULT '',
      commanditaire_type VARCHAR(50) NOT NULL DEFAULT 'Particulier',
      source_prospect_id BIGINT UNSIGNED DEFAULT NULL,
      proposal_id BIGINT UNSIGNED DEFAULT NULL,
      company_id BIGINT UNSIGNED DEFAULT NULL,
      formation_id BIGINT UNSIGNED DEFAULT NULL,
      formation_title VARCHAR(255) DEFAULT '',
      apprenant_name VARCHAR(190) DEFAULT '',
      apprenant_email VARCHAR(190) DEFAULT '',
      client_company VARCHAR(190) DEFAULT '',
      client_address TEXT,
      client_address_complement TEXT,
      client_postal_code VARCHAR(20) DEFAULT '',
      client_city VARCHAR(120) DEFAULT '',
      format VARCHAR(60) DEFAULT 'Présentiel',
      formation_address TEXT,
      formation_postal_code VARCHAR(20) DEFAULT '',
      formation_city VARCHAR(120) DEFAULT '',
      start_date DATE DEFAULT NULL,
      end_date DATE DEFAULT NULL,
      trained_headcount VARCHAR(20) DEFAULT '',
      duration_label VARCHAR(20) DEFAULT '',
      objectives LONGTEXT,
      designation LONGTEXT,
      quantity VARCHAR(20) DEFAULT '1,00',
      tarif_ht DECIMAL(12,2) NOT NULL DEFAULT 0,
      vat_rate DECIMAL(5,2) NOT NULL DEFAULT 20.00,
      vat_regime VARCHAR(32) NOT NULL DEFAULT '',
      transport_fees_enabled TINYINT(1) NOT NULL DEFAULT 0,
      transport_fees_ht DECIMAL(12,2) NOT NULL DEFAULT 0,
      meal_fees_enabled TINYINT(1) NOT NULL DEFAULT 0,
      meal_fees_ht DECIMAL(12,2) NOT NULL DEFAULT 0,
      extra_lines_json LONGTEXT,
      payment_methods LONGTEXT,
      iban VARCHAR(60) DEFAULT '',
      bic VARCHAR(20) DEFAULT '',
      validity_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
      emission_date DATE DEFAULT NULL,
      expiration_date DATE DEFAULT NULL,
      status VARCHAR(30) NOT NULL DEFAULT 'brouillon',
      sent_at DATETIME DEFAULT NULL,
      last_relance_at DATETIME DEFAULT NULL,
      relance_count INT UNSIGNED NOT NULL DEFAULT 0,
      html_url TEXT,
      signed_document_url TEXT,
      created_by BIGINT UNSIGNED DEFAULT NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY scope (scope),
      KEY status (status),
      KEY company_id (company_id),
      KEY source_prospect_id (source_prospect_id),
      KEY proposal_id (proposal_id),
      KEY emission_date (emission_date)
    ) {$charset_collate};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
    // ACDC 3.24.98 — Colonnes e-signature devis
    $this->maybe_add_table_column( $this->quote_table, 'signature_request_id', 'BIGINT UNSIGNED DEFAULT NULL' );
    $this->maybe_add_table_column( $this->quote_table, 'signature_status',     "VARCHAR(30) NOT NULL DEFAULT ''" );
    // SIRET du client (commanditaire) — pour l'afficher sur le devis.
    $this->maybe_add_table_column( $this->quote_table, 'client_siret',         "VARCHAR(20) NOT NULL DEFAULT ''" );
    $this->maybe_add_table_column( $this->quote_table, 'client_signature_path', "VARCHAR(255) NOT NULL DEFAULT ''" );
    /* ACDC 3.25.210 — La DATE de la signature du client manquait en base.
       Elle n'existait que dans la variable locale du gabarit d'e-mail : la pièce
       jointe de l'instant portait « signé le … », et toute relecture ultérieure
       affichait une signature sans date. Sur un document commercial, une
       signature non datée vaut beaucoup moins que la même signature datée. */
    $this->maybe_add_table_column( $this->quote_table, 'client_signed_at', 'DATETIME NULL' );
  }

  /**
   * ACDC 3.25.259 — LE PROSPECT DERRIÈRE UNE PROPOSITION COMMERCIALE.
   *
   * Deux rattachements coexistent : la colonne native de la proposition, et
   * celui de son recueil des besoins. Les propositions anciennes n'ont que le
   * second — ne lire que le premier reviendrait à perdre le prospect pour
   * elles. C'est la même cascade que celle du préremplissage d'adresse.
   */
  private function acdc_prospect_id_from_proposal( $proposal_id ) {
    global $wpdb;
    $proposal_id = (int) $proposal_id;
    if ( ! $proposal_id ) {
      return 0;
    }
    $proposal_table = $wpdb->prefix . 'acdc_of_proposals';
    return (int) $wpdb->get_var( $wpdb->prepare(
      "SELECT COALESCE(NULLIF(p.source_prospect_id, 0), n.source_prospect_id)
         FROM {$proposal_table} p
         LEFT JOIN {$this->need_table} n ON n.id = p.need_id
        WHERE p.id = %d
        LIMIT 1",
      $proposal_id
    ) );
  }

  /**
   * ACDC 3.25.259 — RATTRAPAGE DES DEVIS DÉJÀ ORPHELINS.
   *
   * Réparer la porte ne répare pas ce qui est déjà passé au travers. Les devis
   * enregistrés sans prospect mais avec leur proposition d'origine retrouvent
   * ici leur rattachement — sans quoi un devis signé aujourd'hui laisserait
   * encore son prospect à « Proposition envoyée ».
   *
   * Passage unique, marqué par une option : on ne réécrit pas à chaque chargement.
   */
  private function acdc_backfill_quote_prospect_links() {
    if ( '1' === (string) get_option( 'acdc_of_quote_prospect_backfill_v1', '' ) ) {
      return;
    }
    global $wpdb;
    $proposal_table = $wpdb->prefix . 'acdc_of_proposals';
    $wpdb->query(
      "UPDATE {$this->quote_table} q
         JOIN {$proposal_table} p ON p.id = q.proposal_id
         LEFT JOIN {$this->need_table} n ON n.id = p.need_id
          SET q.source_prospect_id = COALESCE(NULLIF(p.source_prospect_id, 0), n.source_prospect_id)
        WHERE (q.source_prospect_id IS NULL OR q.source_prospect_id = 0)
          AND COALESCE(NULLIF(p.source_prospect_id, 0), n.source_prospect_id) > 0"
    );

    /* Le lien retrouvé ne suffit pas : les statuts n'ont pas avancé pendant
       qu'il manquait. On les redérive des faits — un devis envoyé fait
       « Devis envoyé », un devis signé fait « Converti ». maybe_advance
       n'autorise que la progression : rien ne peut reculer ici. */
    if ( method_exists( $this, 'maybe_advance_prospect_status' ) ) {
      $rows = $wpdb->get_results(
        "SELECT source_prospect_id, status FROM {$this->quote_table}
          WHERE source_prospect_id > 0 AND status IN ('envoye','signe')"
      );
      foreach ( (array) $rows as $row ) {
        $this->maybe_advance_prospect_status( (int) $row->source_prospect_id, 'Devis envoyé' );
        if ( 'signe' === (string) $row->status ) {
          $this->maybe_advance_prospect_status( (int) $row->source_prospect_id, 'Converti', true );
        }
      }
    }

    update_option( 'acdc_of_quote_prospect_backfill_v1', '1', false );
  }

  private function get_quotes( $args = array() ) {
    global $wpdb;
    $t      = $this->quote_table;
    $where  = '1=1';
    $values = array();
    if ( ! empty( $args['scope'] ) ) {
      $where   .= ' AND scope = %s';
      $values[] = (string) $args['scope'];
    }
    if ( ! empty( $args['status'] ) ) {
      $where   .= ' AND status = %s';
      $values[] = (string) $args['status'];
    }
    if ( ! empty( $args['search'] ) ) {
      $like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
      $where   .= ' AND (number LIKE %s OR apprenant_name LIKE %s OR client_company LIKE %s OR formation_title LIKE %s)';
      $values[] = $like; $values[] = $like; $values[] = $like; $values[] = $like;
    }
    $limit = ! empty( $args['limit'] ) ? (int) $args['limit'] : 200;
    $sql   = "SELECT * FROM {$t} WHERE {$where} ORDER BY created_at DESC LIMIT {$limit}";
    return $values ? $wpdb->get_results( $wpdb->prepare( $sql, $values ) ) : $wpdb->get_results( $sql );
  }

  private function get_quote( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->quote_table} WHERE id = %d", (int) $id ) );
  }

  private function save_quote( $data ) {
    global $wpdb;
    $now = current_time( 'mysql' );
    $id  = ! empty( $data['id'] ) ? (int) $data['id'] : 0;
    unset( $data['id'] );
    $data['updated_at'] = $now;
    if ( $id ) {
      /* ACDC 3.25.309 — LE RÉGIME ET SON TAUX NE SE RÉÉCRIVENT PAS.
         Un devis émis à 20 % garde son taux et sa mention. La règle est posée
         ICI, dans l'unique porte d'écriture, et non chez les appelants : une
         règle recopiée à cinq endroits finit toujours par diverger. */
      unset( $data['vat_regime'], $data['vat_rate'] );
      $wpdb->update( $this->quote_table, $data, array( 'id' => $id ) );
      return $id;
    }
    $data = $this->acdc_figer_regime_tva( $data );
    $data['created_at']  = $now;
    $data['created_by']  = get_current_user_id();
    // Numérotation atomique : verrou nommé + réattribution du numéro juste avant
    // l'insertion pour empêcher deux devis d'obtenir le même numéro (race condition).
    $lock_name = 'acdc_of_quote_num_' . $wpdb->prefix;
    $has_lock  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock_name ) );
    if ( isset( $data['number'] ) && preg_match( '/^DE-\d{4}-/', (string) $data['number'] ) ) {
      // Réservation persistée UNE seule fois, sous verrou.
      $data['number'] = $this->reserve_next_document_number( 'DE-' . (int) wp_date( 'Y' ) . '-', $this->quote_table );
    }
    $wpdb->insert( $this->quote_table, $data );
    $new_id = (int) $wpdb->insert_id;
    if ( $has_lock ) {
      $wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
    }
    return $new_id;
  }

  /**
   * ACDC 3.25.114 — Prochain numéro séquentiel, calculé mais NON persisté (aperçu/affichage).
   * Combine le MAX présent en base avec le compteur monotone persistant, sans l'incrémenter.
   * À utiliser partout SAUF au point de réservation atomique sous verrou.
   */
  private function preview_next_document_number( $prefix, $table ) {
    global $wpdb;
    $db_max = (int) $wpdb->get_var( $wpdb->prepare(
      "SELECT MAX(CAST(SUBSTRING(number, %d) AS UNSIGNED)) FROM {$table} WHERE number LIKE %s",
      strlen( $prefix ) + 1,
      $prefix . '%'
    ) );
    $counters = get_option( 'acdc_of_document_number_counters', array() );
    if ( ! is_array( $counters ) ) { $counters = array(); }
    $stored = isset( $counters[ $prefix ] ) ? (int) $counters[ $prefix ] : 0;
    return $prefix . ( max( $db_max, $stored ) + 1 );
  }

  /**
   * ACDC 3.25.114 — Réserve (et PERSISTE) le prochain numéro SANS TROU : pas de réutilisation
   * après suppression, exigence de numérotation chronologique continue (factures/devis).
   * DOIT être appelée UNE SEULE FOIS par document, sous le verrou GET_LOCK déjà en place.
   */
  private function reserve_next_document_number( $prefix, $table ) {
    global $wpdb;
    $db_max = (int) $wpdb->get_var( $wpdb->prepare(
      "SELECT MAX(CAST(SUBSTRING(number, %d) AS UNSIGNED)) FROM {$table} WHERE number LIKE %s",
      strlen( $prefix ) + 1,
      $prefix . '%'
    ) );
    $counters = get_option( 'acdc_of_document_number_counters', array() );
    if ( ! is_array( $counters ) ) { $counters = array(); }
    $stored = isset( $counters[ $prefix ] ) ? (int) $counters[ $prefix ] : 0;
    $next   = max( $db_max, $stored ) + 1;
    $counters[ $prefix ] = $next;
    update_option( 'acdc_of_document_number_counters', $counters, false );
    return $prefix . $next;
  }

  private function get_quote_next_number( $scope = 'action' ) {
    // Aperçu (non persisté) — la réservation définitive a lieu sous verrou dans save_quote().
    return $this->preview_next_document_number( 'DE-' . (int) wp_date( 'Y' ) . '-', $this->quote_table );
  }

  private function get_quote_status_labels() {
    return array(
      'brouillon' => 'Brouillon',
      'envoye'    => 'Envoyé',
      'a_signer'  => 'À signer',
      'signe'     => 'Signé',
      'refuse'    => 'Refusé',
      'expire'    => 'Expiré',
    );
  }

  private function build_quote_row_from_record( $q ) {
    if ( ! $q ) return array();
    $branding  = $this->get_branding_options();
    $params    = method_exists( $this, 'get_contract_params_options' ) ? $this->get_contract_params_options() : array();
    $company_profile = get_option( 'acdc_of_company_profile', array() );
    /* ACDC 3.25.290 — Le NDA venait de la fiche, le SIRET et la raison sociale
       de la marque, et les trois avaient un repli écrit en dur : une facture
       pouvait donc mélanger deux identités, et continuer d'afficher l'ancienne
       après un changement. Une seule source désormais, et rien quand c'est
       vide — une mention légale creuse sur une facture se discute, une mention
       absente se corrige. */
    $identite  = $this->acdc_org_identity();
    $nda       = $identite['nda'];
    $siret     = $identite['siret'];
    $org_name  = $identite['raison_sociale'];
    $org_addr  = \ACDC\Support\OrgIdentity::addressLine( $identite, ', ' );
    $logo_url  = ! empty( $branding['logo_url'] ) ? (string) $branding['logo_url'] : '';

    $tarif_ht  = (float) $q->tarif_ht;
    /* ACDC 3.25.309 — Le taux vient du régime figé sur le devis ; à défaut de
       régime (devis antérieurs), du taux enregistré, sans aucune mention. Le
       profil n'est jamais consulté ici : un devis déjà envoyé ne se réécrit pas. */
    $__regime  = $this->acdc_regime_tva_document( isset( $q->vat_regime ) ? $q->vat_regime : '', $q->vat_rate );
    $vat_rate  = (float) $__regime['taux'];
    $transport = (float) ( $q->transport_fees_enabled ? $q->transport_fees_ht : 0 );
    $meal      = (float) ( $q->meal_fees_enabled ? $q->meal_fees_ht : 0 );
    $extra_total = 0;
    $extra_lines = array();
    if ( ! empty( $q->extra_lines_json ) ) {
      $decoded = json_decode( $q->extra_lines_json, true );
      if ( is_array( $decoded ) ) {
        $extra_lines = $decoded;
        foreach ( $extra_lines as $el ) {
          $extra_total += (float) ( $el['total_ht'] ?? 0 );
        }
      }
    }
    $__totals      = \ACDC\Support\Money::invoiceTotals( $tarif_ht, $transport, $meal, $extra_total, $vat_rate );
    $sous_total_ht = $__totals['ht'];
    $tva_amount    = $__totals['tva'];
    $total_ttc     = $__totals['ttc'];

    $payment_methods = ! empty( $q->payment_methods ) ? (string) $q->payment_methods
      : "Règlement par virement bancaire à l'édition de la facture. En cas de retard, pénalités au taux légal majoré et indemnité forfaitaire de 40 € pour frais de recouvrement.";
    $iban = ! empty( $q->iban ) ? (string) $q->iban : '';
    $bic  = ! empty( $q->bic ) ? (string) $q->bic : '';

    return array(
      'id'                  => (int) $q->id,
      'scope'               => (string) $q->scope,
      'number'              => (string) $q->number,
      'commanditaire_type'  => (string) $q->commanditaire_type,
      'commanditaire_name'  => $q->client_company ?: $q->apprenant_name,
      'apprenant'           => (string) $q->apprenant_name,
      'client_company'      => (string) $q->client_company,
      'client_siret'        => isset( $q->client_siret ) ? (string) $q->client_siret : '',
      'client_signature_uri' => ( ! empty( $q->client_signature_path ) && file_exists( (string) $q->client_signature_path ) ) ? $this->quote_file_to_data_uri( (string) $q->client_signature_path ) : '',
      /* ACDC 3.25.236 — La mention d'acceptation recopiée par le signataire.
         Le cadre « Bon pour accord » du devis existait, vide : il portait un
         titre et aucune mention. C'est pourtant l'écrit qui vaut acceptation
         de l'offre. */
      'accord_mention'       => (string) ( $q->accord_mention ?? '' ),
      'accord_signature_uri' => ( ! empty( $q->accord_signature_path ) && file_exists( (string) $q->accord_signature_path ) ) ? $this->quote_file_to_data_uri( (string) $q->accord_signature_path ) : '',
      /* ACDC 3.25.210 — La date accompagne la signature dans TOUS les rendus.
         Elle n'était posée qu'à la main, dans le seul envoi qui suit la
         signature ; partout ailleurs le devis montrait un paraphe sans date. */
      'client_signed_date'  => ! empty( $q->client_signed_at ) ? mysql2date( 'd/m/Y', (string) $q->client_signed_at ) : '',
      'client_contact'      => (string) $q->apprenant_name,
      'client_address_full' => trim( (string) $q->client_address . ( ! empty( $q->client_address_complement ) ? ' ' . $q->client_address_complement : '' ) ),
      'client_postal_city'  => trim( $q->client_postal_code . ' ' . $q->client_city ),
      'address'             => (string) $q->client_address,
      'address_complement'  => (string) $q->client_address_complement,
      'postal_code'         => (string) $q->client_postal_code,
      'city'                => (string) $q->client_city,
      'status'              => (string) $q->status,
      'status_label'        => $this->get_quote_status_labels()[ $q->status ] ?? ucfirst( (string) $q->status ),
      'relance_date'        => $q->last_relance_at ? mysql2date( 'j F Y H:i', $q->last_relance_at ) : 'Aucune relance',
      'relance_count'       => (int) $q->relance_count,
      /* ACDC 3.25.204 — Le devis nomme la formation avec son repère de famille :
         « 1.0 » et « 1.1 » sont la même formation en deux modalités et à deux
         tarifs. Sans lui, deux devis voisins semblent porter la même offre. */
      'formation'           => $this->acdc_formation_labelled( $q->formation_id ?? 0, $q->formation_title ),
      'formation_full'      => $this->acdc_formation_labelled( $q->formation_id ?? 0, $q->formation_title ),
      'format'              => (string) $q->format,
      'formation_address'   => (string) $q->formation_address,
      'formation_postal_code' => (string) $q->formation_postal_code,
      'formation_city'      => (string) $q->formation_city,
      'location'            => trim( $q->formation_address . ', ' . $q->formation_postal_code . ' ' . $q->formation_city ),
      'start_date'          => $q->start_date ? mysql2date( 'd/m/Y', $q->start_date ) : '',
      'end_date'            => $q->end_date ? mysql2date( 'd/m/Y', $q->end_date ) : '',
      'trained_headcount'   => (string) $q->trained_headcount,
      'duration'            => (string) $q->duration_label,
      'objectives'          => (string) $q->objectives,
      'designation'         => (string) $q->designation,
      'quantity'            => (string) $q->quantity,
      'tarif_ht'            => $this->format_quote_money_value( $tarif_ht ) . ' €',
      'tarif_ht_value'      => $this->format_quote_money_value( $tarif_ht ),
      'tarif_ht_number'     => $this->format_quote_money_value( $sous_total_ht ),
      'montant_tva'         => $this->format_quote_money_value( $tva_amount ) . ' €',
      'tarif_ttc'           => $this->format_quote_money_value( $total_ttc ) . ' €',
      'tarif_ttc_value'     => $this->format_quote_money_value( $total_ttc ),
      'tarif_ttc_number'    => $this->format_quote_money_value( $total_ttc ),
      'tva_total_number'    => $this->format_quote_money_value( $tva_amount ),
      'vat_rate'            => $this->format_quote_money_value( $vat_rate ),
      'vat_rate_number'     => $this->format_quote_money_value( $vat_rate ),
      'vat_regime'          => (string) $__regime['cle'],
      'vat_regime_label'    => (string) $__regime['libelle'],
      'vat_mention'         => (string) $__regime['mention'],
      'quantity_number'     => $this->normalize_price_number( $q->quantity ?: '1,00' ),
      'transport_fees_enabled' => (int) $q->transport_fees_enabled,
      'transport_fees_ht'   => $this->format_quote_money_value( $transport ),
      'meal_fees_enabled'   => (int) $q->meal_fees_enabled,
      'meal_fees_ht'        => $this->format_quote_money_value( $meal ),
      'extra_lines'         => $extra_lines,
      'emission_date'       => $q->emission_date ? mysql2date( 'd/m/Y', $q->emission_date ) : '',
      'expiration_date'     => $q->expiration_date ? mysql2date( 'd/m/Y', $q->expiration_date ) : '',
      'payment_methods'     => $payment_methods,
      'iban'                => $iban,
      'bic'                 => $bic,
      'validity_days'       => (string) $q->validity_days,
      'scope_label'         => 'ancillary' === $q->scope ? 'Prestations annexes' : 'Actions de formation',
      'description_modalite' => ( 'ancillary' === $q->scope ? 'Prestations annexes' : 'Actions de formation' ) . ' — ' . $q->format,
      'prefix'              => 'DE-' . wp_date( 'Y', strtotime( $q->emission_date ?: $q->created_at ) ) . '-',
      'num'                 => preg_replace( '/[^0-9]/', '', (string) $q->number ),
      'html_url'            => (string) $q->html_url,
      /* Données organisme depuis branding */
      '_org_name'           => $org_name,
      '_org_address'        => $org_addr,
      '_org_siret'          => $siret,
      '_org_nda'            => $nda,
      '_org_logo'           => $logo_url,
      '_org_email'          => $branding['email'] ?? '',
      '_org_phone'          => $branding['phone'] ?? '',
      '_org_website'        => $branding['website'] ?? '',
      '_org_signature'      => ! empty( $company_profile['signature_url'] ) ? (string) $company_profile['signature_url'] : '',
      '_org_stamp'          => ! empty( $company_profile['stamp_url'] ) ? (string) $company_profile['stamp_url'] : '',
    );
  }

  private function generate_quote_html( $quote ) {
    if ( ! $quote ) return '';
    $row = $this->build_quote_row_from_record( $quote );
    if ( empty( $row ) ) return '';
    $upload     = wp_upload_dir();
    $dir        = trailingslashit( $upload['basedir'] ) . 'acdc-quotes/';
    $url_base   = trailingslashit( $upload['baseurl'] ) . 'acdc-quotes/';
    /* ACDC 3.25.313 — Voir acdc_dossier_documents() : l'adresse d'un devis part
       par e-mail au client, on ne peut donc pas la fermer — mais on peut
       empêcher qu'on lise la liste de tous les autres. */
    $this->acdc_dossier_documents( $dir );
    /* Supprimer l'ancien fichier si présent */
    if ( ! empty( $quote->html_url ) ) {
      $old_path = str_replace( $url_base, $dir, (string) $quote->html_url );
      if ( 0 === strpos( $old_path, $dir ) && file_exists( $old_path ) ) {
        @unlink( $old_path );
      }
    }
    /* ACDC 3.25.310 — « devis-12-1755374400.html » : le second nombre était un
       horodatage Unix, présent pour forcer le navigateur à recharger. Il reste
       nécessaire — deux versions du même devis doivent avoir deux URL — mais le
       numéro du devis et le client passent devant, pour que le fichier se
       reconnaisse dans une liste. */
    $filename = \ACDC\Support\NomDocument::composer(
      'devis ' . ( $quote->number ?? '' ),
      (string) ( $quote->client_company ?: $quote->apprenant_name ),
      /* ACDC 3.25.313 — UN JETON, PAS SEULEMENT UN HORODATAGE.
         L'horodatage forçait le rechargement mais se devinait : une plage de
         quelques heures suffit à retrouver un devis, qui porte le nom du client
         et le prix négocié. Le jeton aléatoire ferme cela — même procédé que les
         propositions commerciales depuis la 3.25.291. Les devis déjà produits
         gardent leur adresse : on ne casse aucun lien déjà envoyé. */
      time() . '-' . wp_generate_password( 20, false, false ),
      'html'
    );
    $filepath = $dir . $filename;
    $fileurl  = $url_base . $filename;
    $html     = $this->get_quote_document_html( $row );
    file_put_contents( $filepath, $html );
    return $fileurl;
  }

  private function is_documents_billing_demo_enabled() {
    $flag = get_option( 'acdc_of_documents_billing_demo_enabled', '1' );
    return '0' !== (string) $flag;
  }

  /* ---------------------------------------------------------------
   * ACDC 3.24.20 — TABLE FACTURES RÉELLES
   * --------------------------------------------------------------- */

  private function maybe_create_invoices_table() {
    global $wpdb;
    $table           = $this->invoice_table;
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      scope VARCHAR(20) NOT NULL DEFAULT 'action',
      number VARCHAR(60) NOT NULL DEFAULT '',
      quote_id BIGINT UNSIGNED DEFAULT NULL,
      registration_id BIGINT UNSIGNED DEFAULT NULL,
      commanditaire_type VARCHAR(50) NOT NULL DEFAULT 'Particulier',
      source_prospect_id BIGINT UNSIGNED DEFAULT NULL,
      company_id BIGINT UNSIGNED DEFAULT NULL,
      formation_id BIGINT UNSIGNED DEFAULT NULL,
      formation_title VARCHAR(255) DEFAULT '',
      apprenant_name VARCHAR(190) DEFAULT '',
      apprenant_email VARCHAR(190) DEFAULT '',
      client_company VARCHAR(190) DEFAULT '',
      client_siren VARCHAR(30) DEFAULT '',
      client_address TEXT,
      client_address_complement TEXT,
      client_postal_code VARCHAR(20) DEFAULT '',
      client_city VARCHAR(120) DEFAULT '',
      delivery_address TEXT,
      delivery_postal_code VARCHAR(20) DEFAULT '',
      delivery_city VARCHAR(120) DEFAULT '',
      format VARCHAR(60) DEFAULT 'Présentiel',
      formation_address TEXT,
      formation_postal_code VARCHAR(20) DEFAULT '',
      formation_city VARCHAR(120) DEFAULT '',
      start_date DATE DEFAULT NULL,
      end_date DATE DEFAULT NULL,
      trained_headcount VARCHAR(20) DEFAULT '',
      duration_label VARCHAR(20) DEFAULT '',
      objectives LONGTEXT,
      designation LONGTEXT,
      nature_operation VARCHAR(255) DEFAULT '',
      vat_option_debit TINYINT(1) NOT NULL DEFAULT 0,
      quantity VARCHAR(20) DEFAULT '1,00',
      tarif_ht DECIMAL(12,2) NOT NULL DEFAULT 0,
      vat_rate DECIMAL(5,2) NOT NULL DEFAULT 20.00,
      vat_regime VARCHAR(32) NOT NULL DEFAULT '',
      transport_fees_enabled TINYINT(1) NOT NULL DEFAULT 0,
      transport_fees_ht DECIMAL(12,2) NOT NULL DEFAULT 0,
      meal_fees_enabled TINYINT(1) NOT NULL DEFAULT 0,
      meal_fees_ht DECIMAL(12,2) NOT NULL DEFAULT 0,
      extra_lines_json LONGTEXT,
      payment_methods LONGTEXT,
      iban VARCHAR(60) DEFAULT '',
      bic VARCHAR(20) DEFAULT '',
      emission_date DATE DEFAULT NULL,
      due_date DATE DEFAULT NULL,
      status VARCHAR(30) NOT NULL DEFAULT 'emise',
      paid_at DATETIME DEFAULT NULL,
      sent_at DATETIME DEFAULT NULL,
      last_relance_at DATETIME DEFAULT NULL,
      relance_count INT UNSIGNED NOT NULL DEFAULT 0,
      html_url TEXT,
      financeur VARCHAR(120) DEFAULT '',
      credit_note_number VARCHAR(60) DEFAULT '',
      credit_note_date DATE DEFAULT NULL,
      credit_note_reason LONGTEXT,
      created_by BIGINT UNSIGNED DEFAULT NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY scope (scope),
      KEY status (status),
      KEY quote_id (quote_id),
      KEY registration_id (registration_id),
      KEY company_id (company_id),
      KEY emission_date (emission_date)
    ) {$charset_collate};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
  }

  private function get_invoices( $args = array() ) {
    global $wpdb;
    $t      = $this->invoice_table;
    $where  = '1=1';
    $values = array();
    if ( ! empty( $args['scope'] ) ) {
      $where   .= ' AND scope = %s';
      $values[] = (string) $args['scope'];
    }
    if ( ! empty( $args['status'] ) ) {
      $where   .= ' AND status = %s';
      $values[] = (string) $args['status'];
    }
    if ( ! empty( $args['registration_id'] ) ) {
      $where   .= ' AND registration_id = %d';
      $values[] = (int) $args['registration_id'];
    }
    if ( ! empty( $args['search'] ) ) {
      $like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
      $where   .= ' AND (number LIKE %s OR apprenant_name LIKE %s OR client_company LIKE %s OR formation_title LIKE %s)';
      $values[] = $like; $values[] = $like; $values[] = $like; $values[] = $like;
    }
    $limit = ! empty( $args['limit'] ) ? (int) $args['limit'] : 200;
    $sql   = "SELECT * FROM {$t} WHERE {$where} ORDER BY created_at DESC LIMIT {$limit}";
    return $values ? $wpdb->get_results( $wpdb->prepare( $sql, $values ) ) : $wpdb->get_results( $sql );
  }

  private function get_invoice( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->invoice_table} WHERE id = %d", (int) $id ) );
  }

  /**
   * ACDC 3.25.309 — LA MENTION LÉGALE D'UN DOCUMENT, TELLE QU'IL LA PORTE.
   *
   * LE DÉFAUT QU'ELLE FERME. Les documents écrivaient « TVA non applicable,
   * art. 293 B CGI » EN DUR, à quatre endroits, dès que le taux tombait à zéro.
   * L'article 293 B est la franchise en base, qui dépend du chiffre d'affaires.
   * David va demander l'exonération de l'article 261-4-4°a, qui dépend de son
   * activité d'organisme de formation. Le jour où elle lui est accordée, ses
   * devis et ses factures auraient porté la mauvaise référence légale — et une
   * facture qui cite le mauvais article est une facture fausse.
   *
   * DOCUMENT SANS RÉGIME (antérieur) : aucune mention. On ne devine pas laquelle
   * des trois exonérations à 0 % s'appliquait.
   */
  private function acdc_mention_tva_document( $row ) {
    if ( ! empty( $row['vat_mention'] ) ) {
      return (string) $row['vat_mention'];
    }
    if ( ! empty( $row['vat_regime'] ) ) {
      return \ACDC\Support\VatRegime::mention( $row['vat_regime'] );
    }
    return '';
  }

  /**
   * ACDC 3.25.309 — LE RÉGIME DE TVA, POSÉ UNE FOIS SUR UN DOCUMENT NEUF.
   *
   * Le taux n'est plus une saisie ni une constante : il est la projection
   * chiffrée du régime, écrite au même instant que lui. Deux colonnes, une
   * seule vérité — le taux sert à l'arithmétique, le régime porte la mention.
   */
  private function acdc_figer_regime_tva( $data ) {
    $cle = isset( $data['vat_regime'] ) ? trim( (string) $data['vat_regime'] ) : '';
    if ( '' !== $cle && \ACDC\Support\VatRegime::existe( $cle ) ) {
      $data['vat_regime'] = $cle;
      $data['vat_rate']   = \ACDC\Support\VatRegime::taux( $cle );
      return $data;
    }
    /* ACDC 3.25.311 — UN TAUX HÉRITÉ NE SE FAIT PAS ÉCRASER PAR LE PROFIL.
       Le cas qui coûtait cher : un devis ANCIEN à 0 %, sans régime — parce que
       zéro ne dit pas laquelle des trois exonérations s'applique, et qu'on
       refuse de le deviner. Converti en facture, il tombait sur le profil, donc
       sur 20 %, et le client recevait une facture 20 % plus chère que le devis
       qu'il avait accepté.
       Quand l'appelant transmet un taux, ce taux fait foi : il vient d'une
       pièce que quelqu'un a signée. Le régime reste vide — on ne sait toujours
       pas lequel c'était — et le document n'affichera donc aucune mention, ce
       qui est exactement ce qu'il affichait avant. */
    if ( isset( $data['vat_rate'] ) && '' !== (string) $data['vat_rate'] ) {
      $data['vat_regime'] = '';
      return $data;
    }
    $cle = $this->acdc_regime_tva_profil();
    $data['vat_regime'] = $cle;
    $data['vat_rate']   = \ACDC\Support\VatRegime::taux( $cle );
    return $data;
  }

  private function save_invoice( $data ) {
    global $wpdb;
    $now = current_time( 'mysql' );
    $id  = ! empty( $data['id'] ) ? (int) $data['id'] : 0;
    unset( $data['id'] );
    $data['updated_at'] = $now;
    if ( $id ) {
      /* ACDC 3.25.309 — Même règle que pour le devis : une facture envoyée ne
         change plus de taux ni de mention. Voir save_quote(). */
      unset( $data['vat_regime'], $data['vat_rate'] );
      $wpdb->update( $this->invoice_table, $data, array( 'id' => $id ) );
      return $id;
    }
    $data = $this->acdc_figer_regime_tva( $data );
    $data['created_at'] = $now;
    $data['created_by'] = get_current_user_id();
    // Numérotation atomique : verrou nommé + réattribution du numéro juste avant
    // l'insertion pour empêcher deux factures d'obtenir le même numéro (race condition).
    $lock_name = 'acdc_of_invoice_num_' . $wpdb->prefix;
    $has_lock  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock_name ) );
    if ( isset( $data['number'] ) && preg_match( '/^FA-\d{4}-/', (string) $data['number'] ) ) {
      // Réservation persistée UNE seule fois, sous verrou.
      $data['number'] = $this->reserve_next_document_number( 'FA-' . (int) wp_date( 'Y' ) . '-', $this->invoice_table );
    }
    $wpdb->insert( $this->invoice_table, $data );
    $new_id = (int) $wpdb->insert_id;
    if ( $has_lock ) {
      $wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
    }
    return $new_id;
  }

  private function get_invoice_next_number() {
    // Aperçu (non persisté) — la réservation définitive a lieu sous verrou dans save_invoice().
    return $this->preview_next_document_number( 'FA-' . (int) wp_date( 'Y' ) . '-', $this->invoice_table );
  }

  private function get_invoice_status_labels() {
    return array(
      'emise'    => 'Émise',
      'envoyee'  => 'Envoyée',
      'payee'    => 'Payée',
      'en_retard' => 'En retard',
      'litige'   => 'Litige',
      'avoir'    => 'Avoir émis',
    );
  }

  /* ---------------------------------------------------------------
   * ACDC 3.25.118 — Passage automatique en retard des factures impayées
   * Callback du cron quotidien `acdc_of_invoices_overdue_cron` (récurrence daily,
   * planté dans maybe_repair_runtime_state, câblé dans plugin.php).
   * Passe en `en_retard` les factures status IN ('emise','envoyee') dont la
   * due_date est strictement antérieure à aujourd'hui (comparaison en heure WP,
   * PAS NOW()). Idempotent (ne retouche pas payee/litige/avoir/en_retard).
   * --------------------------------------------------------------- */
  public function process_invoices_overdue_cron() {
    if ( $this->is_documents_billing_demo_enabled() ) { return; } // ACDC 3.25.118 — garde démo.
    if ( empty( $this->invoice_table ) ) { return; }
    global $wpdb;
    $today = wp_date( 'Y-m-d' ); // Date « aujourd'hui » en fuseau WordPress.
    $now   = current_time( 'mysql' );
    $wpdb->query( $wpdb->prepare(
      "UPDATE {$this->invoice_table}
         SET status = 'en_retard', updated_at = %s
       WHERE status IN ( 'emise', 'envoyee' )
         AND due_date IS NOT NULL
         AND due_date < %s",
      $now,
      $today
    ) );
  }

  private function get_invoice_status_key( $status ) {
    $map = array(
      'emise'     => 'draft',
      'envoyee'   => 'sent',
      'payee'     => 'paid',
      'en_retard' => 'late',
      'litige'    => 'litige',
      'avoir'     => 'credit',
    );
    return $map[ $status ] ?? 'draft';
  }

  /**
   * Génère le XML Factur-X (profil MINIMUM, reconnu par l'administration fiscale) d'une
   * facture réelle, prêt à être embarqué dans le PDF/A-3 (fichier « factur-x.xml »).
   *
   * @param int    $invoice_id
   * @param string $type_code  380 (facture) ou 381 (avoir).
   * @return string XML, ou '' si facture introuvable / méthode indisponible.
   */
  public function get_invoice_facturx_xml( $invoice_id, $type_code = '380' ) {
    if ( ! method_exists( $this, 'get_invoice' ) ) { return ''; }
    $inv = $this->get_invoice( (int) $invoice_id );
    if ( ! $inv ) { return ''; }
    $branding        = $this->get_branding_options();
    $company_profile = get_option( 'acdc_of_company_profile', array() );

    $transport = (float) ( $inv->transport_fees_enabled ? $inv->transport_fees_ht : 0 );
    $meal      = (float) ( $inv->meal_fees_enabled ? $inv->meal_fees_ht : 0 );
    $extra     = 0.0;
    if ( ! empty( $inv->extra_lines_json ) ) {
      $decoded = json_decode( $inv->extra_lines_json, true );
      $extra   = \ACDC\Support\Money::extraLinesTotal( is_array( $decoded ) ? $decoded : array() );
    }
    $totals = \ACDC\Support\Money::invoiceTotals( (float) $inv->tarif_ht, $transport, $meal, $extra, (float) $inv->vat_rate );

    $vat = '';
    foreach ( array( 'vat_number', 'vat_intra', 'tva_intra', 'numero_tva' ) as $k ) {
      if ( ! empty( $branding[ $k ] ) ) { $vat = (string) $branding[ $k ]; break; }
      if ( is_array( $company_profile ) && ! empty( $company_profile[ $k ] ) ) { $vat = (string) $company_profile[ $k ]; break; }
    }

    $issue = ! empty( $inv->emission_date ) ? (string) $inv->emission_date : ( ! empty( $inv->created_at ) ? (string) $inv->created_at : '' );

    $data = array(
      'number'      => (string) $inv->number,
      'issue_date'  => $issue,
      'currency'    => 'EUR',
      'type_code'   => in_array( (string) $type_code, array( '380', '381' ), true ) ? (string) $type_code : '380',
      'seller'      => array(
        /* ACDC 3.25.290 — Le vendeur d'une facture électronique lisait la fiche
           marque avec un nom en dur en repli : la mention transmise à
           l'administration pouvait donc être l'ancienne entité. */
        'name'    => $this->acdc_org_identity()['raison_sociale'],
        'siret'   => preg_replace( '/\D/', '', $this->acdc_org_identity()['siret'] ),
        'vat'     => $vat,
        'country' => 'FR',
      ),
      /* ACDC 3.25.258 — L'acheteur au sens de la facture électronique est
         celui qui la reçoit et la paie. Quand elle est adressée au financeur,
         le XML doit nommer le financeur : un Factur-X qui désigne le client
         alors que le papier désigne l'OPCO, ce sont deux vérités dans un même
         document, et c'est le XML qui fait foi dans les échanges. */
      'buyer'       => array(
        'name' => $this->acdc_invoice_addressee( $inv )['name'],
      ),
      'tax_basis'   => $totals['ht'],
      'tax_total'   => $totals['tva'],
      'grand_total' => $totals['ttc'],
    );
    return \ACDC\Support\FacturX::buildMinimumXml( $data );
  }

  /**
   * Construit la facture Factur-X : PDF/A-3 (mPDF) avec le XML « factur-x.xml » embarqué
   * en pièce jointe et le bloc XMP Factur-X.
   *
   * @param int $invoice_id
   * @return string Binaire PDF, ou '' si indisponible (facture introuvable, mPDF absent, erreur).
   */
  public function build_invoice_facturx_pdf( $invoice_id ) {
    $invoice_id = (int) $invoice_id;
    if ( ! $invoice_id || ! method_exists( $this, 'get_invoice' ) ) { return ''; }
    $inv = $this->get_invoice( $invoice_id );
    if ( ! $inv ) { return ''; }

    /* HTML de la facture (même rendu que le téléchargement classique) + XML Factur-X. */
    $row  = $this->build_invoice_row_from_record( $inv );
    $html = $this->get_invoice_document_html( $row, 'invoice' );
    $xml  = $this->get_invoice_facturx_xml( $invoice_id );
    if ( '' === (string) $html || '' === (string) $xml ) { return ''; }

    /* Chargement mPDF — même chemin que le kernel (wp-content/acdc-libs). */
    $autoload = dirname( dirname( dirname( dirname( plugin_dir_path( __FILE__ ) ) ) ) ) . '/acdc-libs/vendor/autoload.php';
    if ( file_exists( $autoload ) ) { require_once $autoload; }
    if ( ! class_exists( '\Mpdf\Mpdf' ) ) { return ''; }

    $tmp_xml = '';
    try {
      $mpdf = new \Mpdf\Mpdf( array(
        'format'        => 'A4',
        'margin_top'    => 14,
        'margin_bottom' => 14,
        'margin_left'   => 14,
        'margin_right'  => 14,
        'tempDir'       => sys_get_temp_dir(),
        'PDFA'          => true,
        'PDFAauto'      => true,
      ) );
      $mpdf->SetTitle( 'Facture ' . sanitize_file_name( (string) $inv->number ) );

      /* Pièce jointe PDF/A-3 : mPDF lit le fichier joint depuis un chemin disque. */
      $tmp_xml = function_exists( 'wp_tempnam' ) ? wp_tempnam( 'factur-x.xml' ) : tempnam( sys_get_temp_dir(), 'fxml' );
      if ( ! $tmp_xml || false === file_put_contents( $tmp_xml, $xml ) ) { return ''; }
      $spec = \ACDC\Support\FacturXPdf::associatedFileSpec();
      $mpdf->SetAssociatedFiles( array( array(
        'name'           => $spec['name'],
        'mime'           => $spec['mime'],
        'description'    => $spec['description'],
        'AFRelationship' => $spec['relationship'],
        'path'           => $tmp_xml,
      ) ) );

      /* Bloc XMP Factur-X (fx:DocumentType, fx:DocumentFileName, fx:Version, fx:ConformanceLevel). */
      if ( method_exists( $mpdf, 'SetAdditionalXmpRdf' ) ) {
        $xmp = \ACDC\Support\FacturXPdf::xmpMetadata();
        /* mPDF ré-enveloppe le fragment dans son propre <rdf:RDF> : on retire la nôtre. */
        $mpdf->SetAdditionalXmpRdf( trim( (string) preg_replace( '#</?rdf:RDF[^>]*>#', '', $xmp ) ) );
      }
      /* NB : si SetAdditionalXmpRdf est absente (mPDF < 8.0), le bloc XMP Factur-X devrait
         être injecté par post-traitement du binaire PDF — non pris en charge ici. */

      $mpdf->WriteHTML( $html );
      $pdf = $mpdf->Output( '', 'S' );
      return is_string( $pdf ) ? $pdf : '';
    } catch ( \Throwable $e ) {
      /* Ne jamais casser le flux appelant : fallback géré par l'appelant. */
      return '';
    } finally {
      if ( $tmp_xml && file_exists( $tmp_xml ) ) { @unlink( $tmp_xml ); } // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
    }
  }

  /**
   * ACDC 3.25.258 — À QUI CETTE FACTURE EST-ELLE ADRESSÉE ?
   *
   * Une seule fonction répond, et tout le monde lui demande : l'écran, le
   * document, l'e-mail. La facture ne recopie jamais les coordonnées du
   * financeur — elle le DÉSIGNE, et son adresse est lue sur sa fiche au moment
   * d'imprimer. Corriger l'adresse d'un OPCO corrige donc toutes ses factures,
   * au lieu d'en laisser dix figées sur une adresse périmée.
   *
   * Un financeur désigné mais introuvable (fiche supprimée) ne fait pas
   * disparaître le destinataire : on retombe sur le nom conservé dans la
   * colonne « financeur », puis sur le client. Une facture sans destinataire
   * n'est pas une facture.
   */
  private function acdc_invoice_addressee( $inv ) {
    $client_name  = (string) ( $inv->client_company ?: $inv->apprenant_name );
    $client_addr  = trim( (string) $inv->client_address . ( ! empty( $inv->client_address_complement ) ? ' ' . $inv->client_address_complement : '' ) );
    $client_ville = trim( (string) $inv->client_postal_code . ' ' . (string) $inv->client_city );
    $vers_client  = array(
      'billed_to'    => 'client',
      'name'         => $client_name,
      'address_full' => $client_addr,
      'postal_city'  => $client_ville,
    );

    if ( 'funder' !== (string) ( $inv->billed_to ?? 'client' ) ) {
      return $vers_client;
    }

    $funder = ! empty( $inv->funder_id ) ? $this->get_funder( (int) $inv->funder_id ) : null;
    if ( $funder && ! empty( $funder->name ) ) {
      return array(
        'billed_to'    => 'funder',
        'name'         => (string) $funder->name,
        'address_full' => trim( (string) ( $funder->address ?? '' ) ),
        'postal_city'  => trim( (string) ( $funder->postal_code ?? '' ) . ' ' . (string) ( $funder->city ?? '' ) ),
      );
    }
    if ( ! empty( $inv->financeur ) ) {
      return array(
        'billed_to'    => 'funder',
        'name'         => (string) $inv->financeur,
        'address_full' => '',
        'postal_city'  => '',
      );
    }
    return $vers_client;
  }

  private function build_invoice_row_from_record( $inv ) {
    if ( ! $inv ) return array();
    $branding  = $this->get_branding_options();
    $org_name  = ! empty( $branding['company_name'] ) ? (string) $branding['company_name'] : 'ACDC Formation';
    $tarif_ht  = (float) $inv->tarif_ht;
    /* ACDC 3.25.309 — Voir build_quote_row_from_record() : le régime figé fait
       foi, le profil n'entre jamais ici. */
    $__regime  = $this->acdc_regime_tva_document( isset( $inv->vat_regime ) ? $inv->vat_regime : '', $inv->vat_rate );
    $vat_rate  = (float) $__regime['taux'];
    $transport = (float) ( $inv->transport_fees_enabled ? $inv->transport_fees_ht : 0 );
    $meal      = (float) ( $inv->meal_fees_enabled ? $inv->meal_fees_ht : 0 );
    $extra_total = 0;
    if ( ! empty( $inv->extra_lines_json ) ) {
      $decoded = json_decode( $inv->extra_lines_json, true );
      if ( is_array( $decoded ) ) {
        foreach ( $decoded as $el ) { $extra_total += (float) ( $el['total_ht'] ?? 0 ); }
      }
    }
    $__totals      = \ACDC\Support\Money::invoiceTotals( $tarif_ht, $transport, $meal, $extra_total, $vat_rate );
    $sous_total_ht = $__totals['ht'];
    $tva_amount    = $__totals['tva'];
    $total_ttc     = $__totals['ttc'];
    $status_labels = $this->get_invoice_status_labels();
    $addressee     = $this->acdc_invoice_addressee( $inv );
    return array(
      'id'                  => (int) $inv->id,
      'scope'               => (string) $inv->scope,
      'number'              => (string) $inv->number,
      'quote_id'            => (int) $inv->quote_id,
      'registration_id'     => (int) $inv->registration_id,
      'commanditaire_type'  => (string) $inv->commanditaire_type,
      'commanditaire_name'  => $inv->client_company ?: $inv->apprenant_name,
      'apprenant'           => (string) $inv->apprenant_name,
      'apprenant_email'     => (string) $inv->apprenant_email,
      'client_company'      => (string) $inv->client_company,
      'client_siren'        => (string) $inv->client_siren,
      'client_contact'      => (string) $inv->apprenant_name,
      'client_address_full' => trim( (string) $inv->client_address . ( ! empty( $inv->client_address_complement ) ? ' ' . $inv->client_address_complement : '' ) ),
      'client_postal_city'  => trim( $inv->client_postal_code . ' ' . $inv->client_city ),
      'address'             => (string) $inv->client_address,
      'address_complement'  => (string) $inv->client_address_complement,
      'postal_code'         => (string) $inv->client_postal_code,
      'city'                => (string) $inv->client_city,
      'delivery_address'    => (string) $inv->delivery_address,
      'delivery_postal_code' => (string) $inv->delivery_postal_code,
      'delivery_city'       => (string) $inv->delivery_city,
      'nature_operation'    => (string) $inv->nature_operation,
      'vat_option_debit'    => (int) $inv->vat_option_debit,
      'formation'           => strlen( (string) $inv->formation_title ) > 40 ? substr( (string) $inv->formation_title, 0, 40 ) . '...' : (string) $inv->formation_title,
      'formation_full'      => (string) $inv->formation_title,
      'format'              => (string) $inv->format,
      'formation_address'   => (string) $inv->formation_address,
      'formation_postal_code' => (string) $inv->formation_postal_code,
      'formation_city'      => (string) $inv->formation_city,
      'location'            => trim( (string) $inv->formation_address . ', ' . $inv->formation_postal_code . ' ' . $inv->formation_city ),
      'start_date'          => $inv->start_date ? mysql2date( 'd/m/Y', $inv->start_date ) : '',
      'end_date'            => $inv->end_date ? mysql2date( 'd/m/Y', $inv->end_date ) : '',
      'trained_headcount'   => (string) $inv->trained_headcount,
      'duration'            => (string) $inv->duration_label,
      'objectives'          => (string) $inv->objectives,
      'designation'         => (string) $inv->designation,
      'quantity'            => (string) $inv->quantity,
      'tarif_ht'            => $this->format_quote_money_value( $tarif_ht ) . ' €',
      'tarif_ht_number'     => $this->format_quote_money_value( $sous_total_ht ),
      'montant_tva'         => $this->format_quote_money_value( $tva_amount ) . ' €',
      'tva_total_number'    => $this->format_quote_money_value( $tva_amount ),
      'tarif_ttc'           => $this->format_quote_money_value( $total_ttc ) . ' €',
      'tarif_ttc_number'    => $this->format_quote_money_value( $total_ttc ),
      'vat_rate'            => $this->format_quote_money_value( $vat_rate ),
      'vat_rate_number'     => $this->format_quote_money_value( $vat_rate ),
      'vat_regime'          => (string) $__regime['cle'],
      'vat_regime_label'    => (string) $__regime['libelle'],
      'vat_mention'         => (string) $__regime['mention'],
      'quantity_number'     => $this->normalize_price_number( $inv->quantity ?: '1,00', 2 ),
      'tarif_ht_value'      => $this->format_quote_money_value( $tarif_ht ),
      'tarif_ttc_value'     => $this->format_quote_money_value( $total_ttc ),
      'tarif_ht_raw'        => $tarif_ht,
      'vat_rate_raw'        => $vat_rate,
      'payment_methods'     => (string) $inv->payment_methods,
      'iban'                => (string) $inv->iban,
      'bic'                 => (string) $inv->bic,
      'emission_date'       => $inv->emission_date ? mysql2date( 'd/m/Y', $inv->emission_date ) : '',
      'due_date'            => $inv->due_date ? mysql2date( 'd/m/Y', $inv->due_date ) : '',
      'status'              => (string) $inv->status,
      'status_label'        => $status_labels[ $inv->status ] ?? ucfirst( (string) $inv->status ),
      'status_key'          => $this->get_invoice_status_key( (string) $inv->status ),
      'financeur'           => (string) $inv->financeur,
      /* ACDC 3.25.258 — Le financement, tel qu'il a été décidé sur la convention. */
      'billed_to'           => $addressee['billed_to'],
      'addressee_name'      => $addressee['name'],
      'addressee_address_full' => $addressee['address_full'],
      'addressee_postal_city'  => $addressee['postal_city'],
      'funder_id'           => isset( $inv->funder_id ) ? (int) $inv->funder_id : 0,
      'pec_reference'       => isset( $inv->pec_reference ) ? (string) $inv->pec_reference : '',
      'pec_subrogation'     => ! empty( $inv->pec_subrogation ) ? 1 : 0,
      'pec_total_ht'        => isset( $inv->pec_total_ht ) ? (float) $inv->pec_total_ht : 0.0,
      'sibling_invoice_id'  => isset( $inv->sibling_invoice_id ) ? (int) $inv->sibling_invoice_id : 0,
      'beneficiary_label'   => isset( $inv->beneficiary_label ) ? (string) $inv->beneficiary_label : '',
      'contract_id'         => isset( $inv->contract_id ) ? (int) $inv->contract_id : 0,
      'relance_date'        => $inv->last_relance_at ? mysql2date( 'j F Y H:i', $inv->last_relance_at ) : 'Aucune relance',
      'relance_count'       => (int) $inv->relance_count,
      'paid_at'             => $inv->paid_at ? mysql2date( 'd/m/Y H:i', $inv->paid_at ) : '',
      'credit_note_number'  => (string) $inv->credit_note_number,
      'credit_note_date'    => $inv->credit_note_date ? mysql2date( 'd/m/Y', $inv->credit_note_date ) : '',
      'credit_note_reason'  => (string) $inv->credit_note_reason,
      'scope_label'         => 'ancillary' === (string) $inv->scope ? 'Prestations annexes' : 'Actions de formation',
      'org_name'            => $org_name,
    );
  }

  /**
   * ACDC 3.25.258 — LA CONVENTION QUI DÉCIDE DU DESTINATAIRE DE LA FACTURE.
   *
   * Elle est retrouvée par le devis dont elle est née. Les conventions créées
   * avant cette version ne portent pas cette référence : on retombe alors sur
   * le rapprochement par commanditaire et formation, la même paire que celle
   * qui a servi à la créer. À défaut, on ne devine pas — sans convention, la
   * facture va au client, ce qui est le cas ordinaire.
   */
  private function acdc_find_contract_for_quote( $quote ) {
    global $wpdb;
    if ( empty( $quote->id ) ) {
      return null;
    }
    $t   = $this->registration_contract_table;
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE quote_id = %d ORDER BY id DESC LIMIT 1", (int) $quote->id ) );
    if ( $row ) {
      return $row;
    }
    if ( ! empty( $quote->company_id ) && ! empty( $quote->formation_id ) ) {
      return $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$t} WHERE company_id = %d AND formation_id = %d ORDER BY id DESC LIMIT 1",
        (int) $quote->company_id,
        (int) $quote->formation_id
      ) );
    }
    return null;
  }

  /** Le total HT réellement facturable d'un devis : tarif, frais et lignes. */
  private function acdc_quote_total_ht( $quote ) {
    $extra = 0;
    if ( ! empty( $quote->extra_lines_json ) ) {
      $decoded = json_decode( (string) $quote->extra_lines_json, true );
      $extra   = \ACDC\Support\Money::extraLinesTotal( is_array( $decoded ) ? $decoded : array() );
    }
    $totals = \ACDC\Support\Money::invoiceTotals(
      (float) $quote->tarif_ht,
      (float) ( ! empty( $quote->transport_fees_enabled ) ? $quote->transport_fees_ht : 0 ),
      (float) ( ! empty( $quote->meal_fees_enabled ) ? $quote->meal_fees_ht : 0 ),
      (float) $extra,
      (float) $quote->vat_rate
    );
    return (float) $totals['ht'];
  }

  /**
   * ACDC 3.25.258 — TRANSFORMER UN DEVIS EN FACTURE, OPCO COMPRIS.
   *
   * Trois issues, décidées une seule fois dans ACDC\Support\FundingSplit :
   * pas de prise en charge → une facture au client ; prise en charge totale →
   * une facture au financeur, qui nomme le bénéficiaire ; prise en charge
   * partielle → deux factures liées, la part accordée et le RESTE, calculé.
   *
   * Dans le cas partiel, chaque facture porte une ligne unique : additionner
   * deux fois les frais annexes ferait un total supérieur à la prestation. Le
   * détail reste sur le devis et sur la convention, et la désignation dit d'où
   * vient le montant.
   *
   * @return array Identifiants des factures créées, dans l'ordre d'émission.
   */
  private function convert_quote_to_invoice( $quote_id ) {
    $quote = $this->get_quote( $quote_id );
    if ( ! $quote ) return array();
    $billing = $this->get_billing_settings_options();
    $number  = $this->get_invoice_next_number();
    $now_date = wp_date( 'Y-m-d' );
    $due_days = ! empty( $billing['invoices']['due_days'] ) ? (int) $billing['invoices']['due_days'] : 30;
    $due_date = wp_date( 'Y-m-d', strtotime( '+' . $due_days . ' days', strtotime( $now_date ) ) );
    $data = array(
      'scope'                  => (string) $quote->scope,
      'number'                 => $number,
      'quote_id'               => (int) $quote_id,
      'registration_id'        => 0,
      'commanditaire_type'     => (string) $quote->commanditaire_type,
      'source_prospect_id'     => (int) $quote->source_prospect_id,
      'company_id'             => (int) $quote->company_id,
      'formation_id'           => (int) $quote->formation_id,
      'formation_title'        => (string) $quote->formation_title,
      'apprenant_name'         => (string) $quote->apprenant_name,
      'apprenant_email'        => (string) $quote->apprenant_email,
      'client_company'         => (string) $quote->client_company,
      'client_siren'           => '',
      'client_address'         => (string) $quote->client_address,
      'client_address_complement' => (string) $quote->client_address_complement,
      'client_postal_code'     => (string) $quote->client_postal_code,
      'client_city'            => (string) $quote->client_city,
      'delivery_address'       => (string) $quote->client_address,
      'delivery_postal_code'   => (string) $quote->client_postal_code,
      'delivery_city'          => (string) $quote->client_city,
      'format'                 => (string) $quote->format,
      'formation_address'      => (string) $quote->formation_address,
      'formation_postal_code'  => (string) $quote->formation_postal_code,
      'formation_city'         => (string) $quote->formation_city,
      'start_date'             => $quote->start_date,
      'end_date'               => $quote->end_date,
      'trained_headcount'      => (string) $quote->trained_headcount,
      'duration_label'         => (string) $quote->duration_label,
      'objectives'             => (string) $quote->objectives,
      'designation'            => (string) $quote->designation,
      'nature_operation'       => 'Prestation de formation professionnelle continue',
      'vat_option_debit'       => 0,
      'quantity'               => (string) $quote->quantity,
      'tarif_ht'               => (float) $quote->tarif_ht,
      'vat_rate'               => (float) $quote->vat_rate,
      /* ACDC 3.25.309 — LA FACTURE HÉRITE DU RÉGIME DU DEVIS, PAS DU PROFIL.
         Un devis signé en mars sous un régime, converti en facture en octobre
         après un changement de régime, doit facturer ce qui a été accepté. */
      'vat_regime'             => isset( $quote->vat_regime ) && '' !== (string) $quote->vat_regime
        ? (string) $quote->vat_regime
        : \ACDC\Support\VatRegime::parTaux( $quote->vat_rate ),
      'transport_fees_enabled' => (int) $quote->transport_fees_enabled,
      'transport_fees_ht'      => (float) $quote->transport_fees_ht,
      'meal_fees_enabled'      => (int) $quote->meal_fees_enabled,
      'meal_fees_ht'           => (float) $quote->meal_fees_ht,
      'extra_lines_json'       => (string) $quote->extra_lines_json,
      'payment_methods'        => (string) $quote->payment_methods,
      'iban'                   => (string) $quote->iban,
      'bic'                    => (string) $quote->bic,
      'emission_date'          => $now_date,
      'due_date'               => $due_date,
      'status'                 => 'emise',
      'financeur'              => '',
    );

    /* ── Qui paie, et pour quelle part ─────────────────────────────────── */
    $contract = $this->acdc_find_contract_for_quote( $quote );
    $total_ht = $this->acdc_quote_total_ht( $quote );
    $plan     = $this->acdc_contract_funding_context( $contract, $total_ht );

    $beneficiaire = trim( (string) ( $quote->client_company ?: $quote->apprenant_name ) );
    $data['contract_id']       = $contract ? (int) $contract->id : null;
    $data['beneficiary_label'] = $beneficiaire;
    $data['pec_total_ht']      = (float) $total_ht;

    /* Une prise en charge impossible ne fabrique aucune facture. Le refus
       remonte à l'écran plutôt que d'émettre un document faux. */
    if ( empty( $plan['ok'] ) ) {
      return array( 'error' => \ACDC\Support\FundingSplit::errorMessage( $plan['error'], $plan['total_ht'] ) );
    }

    /* Cas ordinaire : personne d'autre que le client. La facture est celle
       d'avant cette version, au détail près. */
    if ( \ACDC\Support\FundingSplit::CLIENT_SEUL === $plan['case'] ) {
      $id = $this->save_invoice( $data );
      return $id ? array( $id ) : array();
    }

    $funder_row  = $plan['funder_id'] ? $this->get_funder( (int) $plan['funder_id'] ) : null;
    $funder_name = $funder_row && ! empty( $funder_row->name ) ? (string) $funder_row->name : (string) $plan['funder_label'];
    $reference   = (string) ( $plan['reference'] ?? '' );
    $subrogation = ! empty( $plan['subrogation'] ) ? 1 : 0;

    /* Le destinataire est DÉSIGNÉ, jamais recopié. Les colonnes client_*
       gardent le client ; l'adresse du financeur reste dans sa fiche et n'est
       lue qu'au moment d'imprimer. Deux bénéfices : basculer d'un destinataire
       à l'autre ne perd rien, et corriger une adresse d'OPCO sur sa fiche
       corrige toutes ses factures — plutôt que d'en laisser dix figées sur une
       adresse périmée. */
    $vers_financeur = function ( $base ) use ( $funder_name, $reference, $subrogation, $plan, $beneficiaire ) {
      $base['billed_to']         = 'funder';
      $base['funder_id']         = $plan['funder_id'] ?: null;
      $base['financeur']         = $funder_name;
      $base['pec_reference']     = $reference;
      $base['pec_subrogation']   = $subrogation;
      $base['beneficiary_label'] = $beneficiaire;
      return $base;
    };

    if ( \ACDC\Support\FundingSplit::FINANCEUR_SEUL === $plan['case'] ) {
      $id = $this->save_invoice( $vers_financeur( $data ) );
      return $id ? array( $id ) : array();
    }

    /* ── Prise en charge partielle : deux factures liées ────────────────── */
    $part_financeur = (float) $plan['funder_ht'];
    $reste          = (float) $plan['client_ht'];
    $intitule       = (string) ( $quote->formation_title ?: 'Prestation de formation' );

    $facture_financeur = $vers_financeur( $data );
    $facture_financeur['tarif_ht']               = $part_financeur;
    $facture_financeur['quantity']               = '1,00';
    $facture_financeur['transport_fees_enabled'] = 0;
    $facture_financeur['transport_fees_ht']      = 0;
    $facture_financeur['meal_fees_enabled']      = 0;
    $facture_financeur['meal_fees_ht']           = 0;
    $facture_financeur['extra_lines_json']       = '';
    $facture_financeur['designation']            = sprintf(
      "%s — part prise en charge par %s%s.\nTotal de la prestation : %s € HT. Reste à la charge du bénéficiaire : %s € HT (facturé séparément).",
      $intitule,
      $funder_name,
      '' !== $reference ? ' (accord ' . $reference . ')' : '',
      number_format( (float) $plan['total_ht'], 2, ',', ' ' ),
      number_format( $reste, 2, ',', ' ' )
    );

    $id_financeur = $this->save_invoice( $facture_financeur );
    if ( ! $id_financeur ) {
      return array();
    }

    $facture_client = $data;
    $facture_client['billed_to']               = 'client';
    $facture_client['funder_id']               = $plan['funder_id'] ?: null;
    $facture_client['financeur']               = $funder_name;
    $facture_client['pec_reference']           = $reference;
    $facture_client['pec_subrogation']         = $subrogation;
    $facture_client['sibling_invoice_id']      = $id_financeur;
    $facture_client['tarif_ht']                = $reste;
    $facture_client['quantity']                = '1,00';
    $facture_client['transport_fees_enabled']  = 0;
    $facture_client['transport_fees_ht']       = 0;
    $facture_client['meal_fees_enabled']       = 0;
    $facture_client['meal_fees_ht']            = 0;
    $facture_client['extra_lines_json']        = '';
    $facture_client['number']                  = $this->get_invoice_next_number();
    $facture_client['designation']             = sprintf(
      "%s — reste à charge après prise en charge de %s € HT par %s%s.\nTotal de la prestation : %s € HT.",
      $intitule,
      number_format( $part_financeur, 2, ',', ' ' ),
      $funder_name,
      '' !== $reference ? ' (accord ' . $reference . ')' : '',
      number_format( (float) $plan['total_ht'], 2, ',', ' ' )
    );

    $id_client = $this->save_invoice( $facture_client );
    if ( $id_client ) {
      /* Le lien va dans les deux sens : depuis n'importe laquelle des deux
         factures, on retrouve l'autre. */
      $this->save_invoice( array( 'id' => $id_financeur, 'sibling_invoice_id' => $id_client ) );
      return array( $id_financeur, $id_client );
    }
    return array( $id_financeur );
  }

  private function get_documents() {
    global $wpdb;
    $sql = "SELECT d.*, e.name AS company_name,
            CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) AS contact_name
        FROM {$this->document_table} d
        LEFT JOIN {$this->company_table} e ON e.id = d.company_id
        LEFT JOIN {$this->contact_table} c ON c.id = d.contact_id
        ORDER BY d.created_at DESC";
    return $wpdb->get_results( $sql );
  }

  private function get_related_documents( $company_id ) {
    global $wpdb;
    return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->document_table} WHERE company_id = %d ORDER BY created_at DESC", $company_id ) );
  }

  private function get_mock_quotes_data( $scope = 'action' ) {
    if ( ! $this->is_documents_billing_demo_enabled() ) {
      return array();
    }
    $scope_label = 'ancillary' === $scope ? 'Prestations annexes' : 'Actions de formation';
    $formation_label = '2 | Statuts du couple et successions (Présentiel) 2026';
    $rows = array(
      array(
        'id' => 1,
        'number' => 'DE-2026-1',
        'commanditaire_type' => 'Particulier',
        'commanditaire_name' => 'Chloé ARNAUD',
        'status' => 'À SIGNER',
        'relance_date' => '7 janvier 2026 16:27',
        'relance_count' => 1,
        'formation' => $formation_label,
        'formation_full' => 'Statuts du couple et successions (Présentiel) 2026 (Présentiel | 14h00 | 588.00€)',
        'emission_date' => '06/01/2026',
        'expiration_date' => '06/02/2026',
        'quantity' => '1.00',
        'tarif_ht' => '588.00 €',
        'montant_tva' => '117.60 €',
        'tarif_ttc' => '705.60 €',
        'apprenant' => 'Chloé ARNAUD',
        'address' => '13 AVENUE DES ROSES TREMIERES',
        'address_complement' => '—',
        'postal_code' => '83520',
        'city' => 'Roquebrune-sur-Argens',
        'format' => 'Présentiel',
        'formation_address' => '150 rue de la tuilerie',
        'formation_postal_code' => '83520',
        'formation_city' => 'Roquebrune-sur-Argens',
        'duration' => '14h00',
        'start_date' => '14/01/2026',
        'end_date' => '12/02/2026',
        'trained_headcount' => '1',
        'prefix' => 'DE-2026-',
        'num' => '1',
        'designation' => "Client : Chloé ARNAUD\nFormation : Statuts du couple et successions (Collectif - Présentiel)\nObjectifs pédagogiques : voir programme\nDates de la prestation : 14/01/2026 - 12/02/2026\nDurée : 14h00\nFormat - Présentiel",
        'tarif_ht_value' => '588,00',
        'vat_rate' => '20,00',
        'tarif_ttc_value' => '705,6',
      ),
      array('id'=>2,'number'=>'DE-2025-27','commanditaire_type'=>'Entreprise','commanditaire_name'=>'Arnaud GUEGAN','status'=>'À SIGNER','relance_date'=>'15 décembre 2025 10:53','relance_count'=>1,'formation'=>$formation_label,'formation_full'=>$formation_label,'emission_date'=>'05/12/2025','expiration_date'=>'05/01/2026','quantity'=>'1.00','tarif_ht'=>'588.00 €','montant_tva'=>'117.60 €','tarif_ttc'=>'705.60 €'),
      array('id'=>3,'number'=>'DE-2025-26','commanditaire_type'=>'Entreprise','commanditaire_name'=>'Maréva NAKAK','status'=>'À SIGNER','relance_date'=>'Aucune relance','relance_count'=>0,'formation'=>$formation_label,'formation_full'=>$formation_label,'emission_date'=>'05/12/2025','expiration_date'=>'05/01/2026','quantity'=>'1.00','tarif_ht'=>'588.00 €','montant_tva'=>'117.60 €','tarif_ttc'=>'705.60 €'),
      array('id'=>4,'number'=>'DE-2025-25','commanditaire_type'=>'Particulier','commanditaire_name'=>'Quentin CHANON','status'=>'À SIGNER','relance_date'=>'Aucune relance','relance_count'=>0,'formation'=>$formation_label,'formation_full'=>$formation_label,'emission_date'=>'05/12/2025','expiration_date'=>'05/01/2026','quantity'=>'1.00','tarif_ht'=>'588.00 €','montant_tva'=>'117.60 €','tarif_ttc'=>'705.60 €'),
      array('id'=>5,'number'=>'20251111-24','commanditaire_type'=>'Entreprise','commanditaire_name'=>'Quentin CHANON','status'=>'À SIGNER','relance_date'=>'Aucune relance','relance_count'=>0,'formation'=>'3 | La Société Civile Immobili...','formation_full'=>'3 | La Société Civile Immobilière','emission_date'=>'11/11/2025','expiration_date'=>'11/12/2025','quantity'=>'1.00','tarif_ht'=>'294.00 €','montant_tva'=>'58.80 €','tarif_ttc'=>'352.80 €'),
      array('id'=>6,'number'=>'20251111-23','commanditaire_type'=>'Entreprise','commanditaire_name'=>'Quentin CHANON','status'=>'À SIGNER','relance_date'=>'Aucune relance','relance_count'=>0,'formation'=>'3 | La Société Civile Immobili...','formation_full'=>'3 | La Société Civile Immobilière','emission_date'=>'11/11/2025','expiration_date'=>'18/11/2025','quantity'=>'1.00','tarif_ht'=>'294.00 €','montant_tva'=>'58.80 €','tarif_ttc'=>'352.80 €'),
      array('id'=>7,'number'=>'20251111-22','commanditaire_type'=>'Entreprise','commanditaire_name'=>'Stéphane THIBOULT','status'=>'À SIGNER','relance_date'=>'Aucune relance','relance_count'=>0,'formation'=>'3 | La Société Civile Immobili...','formation_full'=>'3 | La Société Civile Immobilière','emission_date'=>'11/11/2025','expiration_date'=>'18/11/2025','quantity'=>'1.00','tarif_ht'=>'294.00 €','montant_tva'=>'58.80 €','tarif_ttc'=>'352.80 €'),
      array('id'=>8,'number'=>'20251111-20','commanditaire_type'=>'Entreprise','commanditaire_name'=>'Maréva NAKAK','status'=>'À SIGNER','relance_date'=>'Aucune relance','relance_count'=>0,'formation'=>$formation_label,'formation_full'=>$formation_label,'emission_date'=>'11/11/2025','expiration_date'=>'11/12/2025','quantity'=>'1.00','tarif_ht'=>'588.00 €','montant_tva'=>'117.60 €','tarif_ttc'=>'705.60 €'),
      array('id'=>9,'number'=>'20251111-19','commanditaire_type'=>'Entreprise','commanditaire_name'=>'Maréva NAKAK','status'=>'À SIGNER','relance_date'=>'Aucune relance','relance_count'=>0,'formation'=>'3 | La Société Civile Immobili...','formation_full'=>'3 | La Société Civile Immobilière','emission_date'=>'11/11/2025','expiration_date'=>'18/11/2025','quantity'=>'1.00','tarif_ht'=>'294.00 €','montant_tva'=>'58.80 €','tarif_ttc'=>'352.80 €'),
      array('id'=>10,'number'=>'20251111-18','commanditaire_type'=>'Entreprise','commanditaire_name'=>'Arnaud GUEGAN','status'=>'À SIGNER','relance_date'=>'Aucune relance','relance_count'=>0,'formation'=>'3 | La Société Civile Immobili...','formation_full'=>'3 | La Société Civile Immobilière','emission_date'=>'11/11/2025','expiration_date'=>'18/11/2025','quantity'=>'1.00','tarif_ht'=>'294.00 €','montant_tva'=>'58.80 €','tarif_ttc'=>'352.80 €'),
      array('id'=>11,'number'=>'20251111-17','commanditaire_type'=>'Entreprise','commanditaire_name'=>'Arnaud GUEGAN','status'=>'À SIGNER','relance_date'=>'Aucune relance','relance_count'=>0,'formation'=>$formation_label,'formation_full'=>$formation_label,'emission_date'=>'11/11/2025','expiration_date'=>'11/12/2025','quantity'=>'1.00','tarif_ht'=>'588.00 €','montant_tva'=>'117.60 €','tarif_ttc'=>'705.60 €'),
    );

    foreach ( $rows as &$row ) {
      $row['scope_label'] = $scope_label;
      if ( empty( $row['apprenant'] ) ) { $row['apprenant'] = $row['commanditaire_name']; }
      if ( empty( $row['address'] ) ) { $row['address'] = '13 AVENUE DES ROSES TREMIERES'; }
      if ( empty( $row['address_complement'] ) ) { $row['address_complement'] = '—'; }
      if ( empty( $row['postal_code'] ) ) { $row['postal_code'] = '83520'; }
      if ( empty( $row['city'] ) ) { $row['city'] = 'Roquebrune-sur-Argens'; }
      if ( empty( $row['format'] ) ) { $row['format'] = 'Présentiel'; }
      if ( empty( $row['formation_address'] ) ) { $row['formation_address'] = '150 rue de la tuilerie'; }
      if ( empty( $row['formation_postal_code'] ) ) { $row['formation_postal_code'] = '83520'; }
      if ( empty( $row['formation_city'] ) ) { $row['formation_city'] = 'Roquebrune-sur-Argens'; }
      if ( empty( $row['duration'] ) ) { $row['duration'] = '14h00'; }
      if ( empty( $row['start_date'] ) ) { $row['start_date'] = '14/01/2026'; }
      if ( empty( $row['end_date'] ) ) { $row['end_date'] = '12/02/2026'; }
      if ( empty( $row['trained_headcount'] ) ) { $row['trained_headcount'] = '1'; }
      if ( empty( $row['prefix'] ) ) { $row['prefix'] = 'DE-2026-'; }
      if ( empty( $row['num'] ) ) { $row['num'] = preg_replace( '/[^0-9]/', '', $row['number'] ); }
      if ( empty( $row['designation'] ) ) { $row['designation'] = "Dates de l'action de formation : à définir"; }
      if ( empty( $row['tarif_ht_value'] ) ) { $row['tarif_ht_value'] = false !== strpos( $row['tarif_ht'], '294' ) ? '294,00' : '588,00'; }
      if ( empty( $row['vat_rate'] ) ) { $row['vat_rate'] = '20,00'; }
      if ( empty( $row['tarif_ttc_value'] ) ) { $row['tarif_ttc_value'] = false !== strpos( $row['tarif_ttc'], '352' ) ? '352,8' : '705,6'; }
      if ( empty( $row['client_company'] ) ) { $row['client_company'] = 'Entreprise' === $row['commanditaire_type'] ? $row['commanditaire_name'] : ''; }
      if ( empty( $row['client_contact'] ) ) { $row['client_contact'] = $row['apprenant']; }
      if ( empty( $row['client_address_full'] ) ) { $row['client_address_full'] = trim( $row['address'] . ( ! empty( $row['address_complement'] ) && '—' !== $row['address_complement'] ? ' ' . $row['address_complement'] : '' ) ); }
      if ( empty( $row['client_postal_city'] ) ) { $row['client_postal_city'] = trim( $row['postal_code'] . ' ' . $row['city'] ); }
      if ( empty( $row['objectives'] ) ) { $row['objectives'] = 'voir programme'; }
      if ( empty( $row['location'] ) ) { $row['location'] = trim( $row['formation_address'] . ', ' . $row['formation_postal_code'] . ' ' . $row['formation_city'] ); }
      if ( empty( $row['description_modalite'] ) ) { $row['description_modalite'] = $row['scope_label'] . ' — ' . $row['format']; }
      if ( empty( $row['payment_methods'] ) ) { $row['payment_methods'] = "Règlement par virement bancaire à l’édition de la facture. En cas de retard, pénalités au taux légal majoré et indemnité forfaitaire de 40 € pour frais de recouvrement."; }
      /* ACDC 3.25.290 — Ces deux replis écrivaient un compte bancaire en dur sur
         une facture dont les coordonnées n'étaient pas renseignées. On les prend
         désormais dans la fiche entreprise, et à défaut on n'affiche rien : un
         IBAN faux est infiniment pire qu'un IBAN manquant. */
      if ( empty( $row['iban'] ) || empty( $row['bic'] ) ) {
        $__fiche = get_option( 'acdc_of_company_profile', array() );
        if ( empty( $row['iban'] ) && ! empty( $__fiche['bank_iban'] ) ) { $row['iban'] = (string) $__fiche['bank_iban']; }
        if ( empty( $row['bic'] ) && ! empty( $__fiche['bank_bic'] ) )   { $row['bic']  = (string) $__fiche['bank_bic']; }
      }
      if ( empty( $row['validity_days'] ) ) { $row['validity_days'] = '30'; }
      $row['tarif_ht_number'] = $this->normalize_price_number( isset( $row['tarif_ht_value'] ) ? $row['tarif_ht_value'] : $row['tarif_ht'] );
      $row['tarif_ttc_number'] = $this->normalize_price_number( isset( $row['tarif_ttc_value'] ) ? $row['tarif_ttc_value'] : $row['tarif_ttc'] );
      $row['quantity_number'] = $this->normalize_price_number( isset( $row['quantity'] ) ? $row['quantity'] : '1,00', 2 );
      $row['vat_rate_number'] = $this->normalize_price_number( isset( $row['vat_rate'] ) ? $row['vat_rate'] : '20,00', 2 );
      $row['tva_total_number'] = $this->format_quote_money_value( ( (float) str_replace( ',', '.', $row['tarif_ttc_number'] ) ) - ( (float) str_replace( ',', '.', $row['tarif_ht_number'] ) ) );
    }
    unset( $row );

    return $rows;
  }

  private function get_mock_quote_record( $quote_id, $scope = 'action' ) {
    foreach ( $this->get_mock_quotes_data( $scope ) as $row ) {
      if ( (int) $row['id'] === (int) $quote_id ) {
        return $row;
      }
    }
    $rows = $this->get_mock_quotes_data( $scope );
    return reset( $rows );
  }

  private function get_quotes_scope() {
    $scope = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : '';
    return in_array( $scope, array( 'action', 'ancillary' ), true ) ? $scope : '';
  }

  private function get_quotes_action() {
    $action = isset( $_GET['quote_action'] ) ? sanitize_key( wp_unslash( $_GET['quote_action'] ) ) : '';
    return in_array( $action, array( 'view', 'edit', 'create', 'preview' ), true ) ? $action : '';
  }

  private function format_quote_money_value( $value ) {
    $number = is_numeric( $value ) ? (float) $value : 0.0;
    return number_format( $number, 2, ',', '' );
  }

  /**
   * ACDC 3.25.244 — Sépare une adresse écrite en une seule ligne.
   *
   * La proposition ne stocke le lieu de formation qu'en un bloc de texte, alors
   * que le devis porte trois colonnes et recompose l'adresse à partir des
   * trois. Sans découpage, le code postal et la ville s'écrivaient deux fois
   * sur le document, ou pas du tout.
   *
   * On ne reconnaît qu'un motif, celui du courrier français : cinq chiffres
   * suivis de la ville, en fin de ligne. Une adresse qui ne s'y conforme pas —
   * étrangère, incomplète, ou simplement écrite autrement — est rendue
   * inchangée sur la ligne d'adresse. Mieux vaut une adresse entière au mauvais
   * endroit qu'une adresse coupée au mauvais endroit : la première se relit,
   * la seconde se perd.
   *
   * Le séparateur optionnel avant le code postal couvre les deux écritures
   * courantes, avec et sans virgule.
   *
   * @param string $raw Adresse sur une seule ligne.
   * @return array{address:string,postal_code:string,city:string}
   */
  private function acdc_split_french_address( $raw ) {
    $raw = is_scalar( $raw ) ? trim( preg_replace( '/\s+/u', ' ', (string) $raw ) ) : '';
    $out = array( 'address' => $raw, 'postal_code' => '', 'city' => '' );
    if ( '' === $raw ) {
      return $out;
    }

    if ( ! preg_match( '/^(.*)[,\s]\s*(\d{5})\s+(.+)$/u', $raw, $m ) ) {
      return $out;
    }

    $street = trim( rtrim( trim( (string) $m[1] ), ',' ) );
    $city   = trim( (string) $m[3] );

    /* Une rue vide signifierait que la ligne ne portait que « 83140 Ville » :
       il n'y a alors rien à séparer, et vider la ligne d'adresse ferait perdre
       la seule information saisie. */
    if ( '' === $street || '' === $city ) {
      return $out;
    }

    return array(
      'address'     => $street,
      'postal_code' => (string) $m[2],
      'city'        => $city,
    );
  }


  private function quote_html( $value ) {
    return esc_html( (string) $value );
  }

  private function quote_html_with_breaks( $value ) {
    return nl2br( esc_html( (string) $value ) );
  }

  private function get_quote_document_html( $row ) {
    $number = ! empty( $row['number'] ) ? $row['number'] : trim( ( $row['prefix'] ?? 'DE-' ) . ( $row['num'] ?? '1' ) );
    $client_name = ! empty( $row['client_company'] ) ? $row['client_company'] : $row['commanditaire_name'];
    $client_contact = ! empty( $row['client_contact'] ) ? $row['client_contact'] : $row['apprenant'];
    $designation = ! empty( $row['designation'] ) ? $row['designation'] : '';
    $formation = ! empty( $row['formation_full'] ) ? $row['formation_full'] : $row['formation'];
    $description_modalite = ! empty( $row['description_modalite'] ) ? $row['description_modalite'] : $row['scope_label'];
    $tot_ht = ! empty( $row['tarif_ht_number'] ) ? $row['tarif_ht_number'] : $this->normalize_price_number( $row['tarif_ht'] ?? '0' );
    $tot_ttc = ! empty( $row['tarif_ttc_number'] ) ? $row['tarif_ttc_number'] : $this->normalize_price_number( $row['tarif_ttc'] ?? '0' );
    $vat_rate = ! empty( $row['vat_rate_number'] ) ? $row['vat_rate_number'] : $this->normalize_price_number( $row['vat_rate'] ?? '0,00' );
    $tva_total = ! empty( $row['tva_total_number'] ) ? $row['tva_total_number'] : $this->format_quote_money_value( (float) str_replace( ',', '.', $tot_ttc ) - (float) str_replace( ',', '.', $tot_ht ) );
    $quantity = ! empty( $row['quantity_number'] ) ? $row['quantity_number'] : $this->normalize_price_number( $row['quantity'] ?? '1,00' );
    $is_vat_exempt = ( (float) str_replace( ',', '.', $vat_rate ) == 0.0 );
    $vat_mention   = $this->acdc_mention_tva_document( $row );
    // ACDC 3.24.99 — Images inlinées en base64 pour HTML autoportant (iframe signature, client externe)
    $sig_img_url  = 'https://acdcformation.com/wp-content/uploads/2026/04/Cachet-et-signature.png';
    $sig2_img_url = 'https://acdcformation.com/wp-content/uploads/2026/04/Signature-seule-David-scaled.png';
    $logo_img_url = ! empty( $row['_org_logo'] ) ? (string) $row['_org_logo'] : 'https://acdcformation.com/wp-content/uploads/2026/03/Logo-ACDC.png';
    $sig_data_uri    = $this->quote_img_to_data_uri( $sig_img_url, 400 );
    $sig_data_uri_t2 = $this->quote_img_to_data_uri( $sig2_img_url, 400 );
    $logo_data_uri   = $this->quote_img_to_data_uri( $logo_img_url, 200 );
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php
  /* ACDC 3.25.320 — LE TITRE EST LE NOM DU FICHIER.
     Ce devis est servi en HTML : quand on l'enregistre, le navigateur ne
     regarde pas l'adresse mais le TITRE de la page. Il valait « Modèle de devis
     - ACDC-Formation » — le nom du gabarit, identique pour tous les clients et
     tous les devis. Le fichier stocké portait pourtant déjà le bon nom depuis
     la 3.25.310 : seul l'enregistrement manuel restait en arrière.
     On y met la même chose : la nature, le client, le numéro, la date. */
  $__titre_devis = trim( 'Devis — ' . ( '' !== (string) $client_name ? $client_name . ' — ' : '' ) . $number
    . ( ! empty( $row['emission_date'] ) ? ' — ' . $row['emission_date'] : '' ) );
  ?>
  <title><?php echo esc_html( $__titre_devis ); ?></title>
  <style>
    :root {--navy:#0C2D52;--gold:#C5A253;--gold-soft:#E8D8B1;--ink:#1f2937;--muted:#6b7280;--line:#d7dde6;--paper:#ffffff;}
    @page {size:A4 portrait;margin:0;}
    * {box-sizing:border-box;} body {margin:0;background:#F6F8FB;font-family:Rubik, Arial, Helvetica, sans-serif;color:var(--ink);} .page {width:210mm;min-height:297mm;height:297mm;margin:0 auto;background:var(--paper);box-shadow:0 10px 30px rgba(12,45,82,0.08);position:relative;padding:11mm 12.5mm 18mm;overflow:hidden;} .header {display:flex;justify-content:space-between;align-items:flex-start;gap:8mm;border-bottom:1.5px solid var(--gold);padding-bottom:3.5mm;margin-bottom:6mm;} .brand {display:flex;align-items:center;gap:4mm;min-width:0;} .brand img {height:16mm;width:auto;object-fit:contain;flex-shrink:0;} .brand-text strong {display:block;color:var(--navy);font-size:13.6pt;line-height:1.08;font-weight:700;} .brand-text span {display:block;margin-top:.8mm;color:var(--gold);font-size:7.7pt;font-weight:500;letter-spacing:.01em;} .company {text-align:right;font-size:8.2pt;line-height:1.32;color:#374151;max-width:64mm;} .top-grid {display:grid;grid-template-columns:1fr 72mm;gap:8mm;margin-bottom:7mm;} .box {border:1px solid var(--line);border-radius:10px;padding:4mm 4.5mm;background:#fff;} .box-title {font-size:8pt;text-transform:uppercase;letter-spacing:.05em;color:var(--gold);font-weight:700;margin-bottom:2mm;} .client-lines,.quote-lines {font-size:8.9pt;line-height:1.5;color:#1f2937;} h1 {margin:0 0 4mm;color:var(--navy);font-size:15.4pt;font-weight:700;letter-spacing:.01em;} .details {display:grid;grid-template-columns:repeat(2,1fr);gap:3.2mm 6.5mm;margin-bottom:7mm;font-size:8.9pt;} .detail {display:flex;gap:2mm;align-items:baseline;} .detail strong {color:var(--navy);font-weight:600;min-width:38mm;} .detail span:last-child {flex:1;} table {width:100%;border-collapse:collapse;margin-top:2.5mm;font-size:8.6pt;} thead th {background:var(--navy);color:white;font-weight:600;padding:2.8mm 2.2mm;text-align:left;border-right:1px solid rgba(255,255,255,0.15);line-height:1.2;} thead th:last-child {border-right:0;} tbody td {padding:3mm 2.2mm;border-bottom:1px solid var(--line);vertical-align:top;line-height:1.28;} .designation {min-height:18mm;line-height:1.28;} .totals {width:72mm;margin-left:auto;margin-top:7mm;border:1px solid var(--line);border-radius:10px;overflow:hidden;} .total-row {display:grid;grid-template-columns:1fr 28mm;font-size:8.8pt;} .total-row div {padding:3mm 3.2mm;border-bottom:1px solid var(--line);line-height:1.2;} .total-row:last-child div {border-bottom:0;} .total-row strong {color:var(--navy);} .total-row.grand div {background:rgba(12,45,82,0.06);font-weight:700;color:var(--navy);} .notes {position:absolute;left:12.5mm;right:12.5mm;bottom:32mm;margin:0;font-size:7.3pt;line-height:1.32;color:#374151;z-index:1;} .notes p {margin:0 0 2mm;text-align:justify;} .signature-zone {margin-top:0;display:grid;grid-template-columns:1fr 56mm;gap:5mm;align-items:end;position:absolute;left:12.5mm;right:12.5mm;bottom:12mm;} .bank {font-size:7.2pt;color:var(--navy);font-weight:500;} .sign-box {text-align:center;border-top:1px solid var(--line);padding-top:3.2mm;min-height:14mm;font-size:7.9pt;color:#4b5563;position:relative;overflow:visible;} .footer {position:absolute;left:10mm;right:10mm;bottom:4mm;border-top:1px solid var(--line);padding-top:2.2mm;text-align:center;font-size:6.8pt;line-height:1.22;color:#4b5563;} @media print {body {background:white;} .page {margin:0;box-shadow:none;}}
  </style>
</head>
<body>
  <div class="page">
    <div class="header">
      <div class="brand">
        <img src="<?php echo $logo_data_uri ?: esc_url( $logo_img_url ); ?>" alt="Logo" />
        <div class="brand-text">
          <strong><?php echo $this->quote_html( $row['_org_name'] ?? 'ACDC Formation' ); ?></strong>
          <span>Devis de formation professionnelle</span>
        </div>
      </div>
      <div class="company">
        <strong><?php echo $this->quote_html( $row['_org_name'] ?? 'ACDC Formation' ); ?></strong><br />
        <?php echo $this->quote_html( $row['_org_address'] ?? '' ); ?><br />
        <?php if ( ! empty( $row['_org_siret'] ) ) : ?>Siret : <?php echo $this->quote_html( $row['_org_siret'] ); ?><br /><?php endif; ?>
        <?php if ( ! empty( $row['_org_nda'] ) ) : ?>NDA : <?php echo $this->quote_html( $row['_org_nda'] ); ?><?php endif; ?>
      </div>
    </div>

    <div class="top-grid">
      <div class="box">
        <div class="box-title">Client</div>
        <div class="client-lines">
          Nom / Société : <?php echo $this->quote_html( $client_name ); ?><br />
          <?php if ( ! empty( $row['client_siret'] ) ) : ?>SIRET : <?php echo $this->quote_html( $row['client_siret'] ); ?><br /><?php endif; ?>
          Adresse : <?php echo $this->quote_html( $row['client_address_full'] ?? '' ); ?><br />
          Code postal / Ville : <?php echo $this->quote_html( $row['client_postal_city'] ?? '' ); ?><br />
          Contact : <?php echo $this->quote_html( $client_contact ); ?>
        </div>
      </div>

      <div class="box">
        <div class="box-title">Devis</div>
        <div class="quote-lines">
          N° devis : <?php echo $this->quote_html( $number ); ?><br />
          Date d’émission : <?php echo $this->quote_html( $row['emission_date'] ?? '' ); ?><br />
          Date de validité : <?php echo $this->quote_html( $row['expiration_date'] ?? '' ); ?>
        </div>
      </div>
    </div>

    <h1>Devis</h1>

    <table>
      <thead>
        <tr>
          <th style="width:42%;">Désignation</th>
          <th style="width:11%;">Quantité</th>
          <th style="width:15%;"><?php echo $is_vat_exempt ? 'Prix net (€)' : 'P.U (€ HT)'; ?></th>
          <?php if ( ! $is_vat_exempt ) : ?><th style="width:12%;">TVA (%)</th><?php endif; ?>
          <th style="width:20%;"><?php echo $is_vat_exempt ? 'Total net (€)' : 'Total (€ HT)'; ?></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="designation"><?php
            echo $this->quote_html( $formation );
            if ( ! empty( $row['start_date'] ) || ! empty( $row['end_date'] ) ) {
              $dates_str = trim( ( $row['start_date'] ?? '' ) . ( ! empty( $row['end_date'] ) && $row['end_date'] !== $row['start_date'] ? ' — ' . $row['end_date'] : '' ) );
              echo '<br />Dates : ' . $this->quote_html( $dates_str );
            }
            if ( ! empty( $row['duration'] ) )          { echo '<br />Durée : ' . $this->quote_html( $row['duration'] ); }
            if ( ! empty( $row['format'] ) )            { echo '<br />Format : ' . $this->quote_html( $row['format'] ); }
            if ( ! empty( $row['location'] ) )          { echo '<br />Lieu : ' . $this->quote_html( $row['location'] ); }
            if ( ! empty( $row['trained_headcount'] ) ) { echo '<br />Effectif : ' . $this->quote_html( $row['trained_headcount'] ); }
          ?></td>
          <td><?php echo $this->quote_html( $quantity ); ?></td>
          <td><?php echo $this->quote_html( $tot_ht ); ?></td>
          <?php if ( ! $is_vat_exempt ) : ?><td><?php echo $this->quote_html( $vat_rate ); ?></td><?php endif; ?>
          <td><?php echo $this->quote_html( $tot_ht ); ?></td>
        </tr>
      </tbody>
    </table>

    <div style="display:grid;grid-template-columns:1fr 72mm;gap:8mm;margin-top:7mm;align-items:start;">
      <div class="totals" style="width:100%;margin-left:0;margin-top:0;">
        <div class="total-row" style="font-size:7.3pt;line-height:1.32;">
          <div><?php echo $this->quote_html( $row['payment_methods'] ?? '' ); ?></div>
        </div>
        <div class="total-row" style="font-size:7.2pt;color:var(--navy);font-weight:500;">
          <div>IBAN : <?php echo $this->quote_html( $row['iban'] ?? '' ); ?></div>
          <div>BIC : <?php echo $this->quote_html( $row['bic'] ?? '' ); ?></div>
        </div>
        <div class="total-row" style="font-size:7.3pt;color:#374151;">
          <?php if ( $is_vat_exempt ) : ?>
          <div style="grid-column:1/-1;">Devis valable <?php echo $this->quote_html( $row['validity_days'] ?? '30' ); ?> jours. Prix nets de TVA.<?php echo '' !== $vat_mention ? ' ' . $this->quote_html( $vat_mention ) . '.' : ''; ?> Certifié Qualiopi.</div>
          <?php else : ?>
          <div style="grid-column:1/-1;">Devis valable <?php echo $this->quote_html( $row['validity_days'] ?? '30' ); ?> jours. Prix HT et TTC. TVA en vigueur. Certifié Qualiopi.</div>
          <?php endif; ?>
        </div>
      </div>
      <div class="totals" style="margin-left:0;margin-top:0;">
        <?php if ( $is_vat_exempt ) : ?>
        <div class="total-row grand"><div><strong>Total net (€)</strong></div><div><?php echo $this->quote_html( $tot_ht ); ?> €</div></div>
        <?php if ( '' !== $vat_mention ) : ?><div class="total-row"><div style="font-size:11px;color:#6b7280;"><?php echo $this->quote_html( $vat_mention ); ?></div><div style="font-size:11px;color:#6b7280;">Net de TVA</div></div><?php endif; ?>
        <?php else : ?>
        <div class="total-row"><div><strong>Total HT (€)</strong></div><div><?php echo $this->quote_html( $tot_ht ); ?> €</div></div>
        <div class="total-row"><div><strong>Total TVA (€)</strong></div><div><?php echo $this->quote_html( $tva_total ); ?> €</div></div>
        <div class="total-row grand"><div><strong>Total TTC (€)</strong></div><div><?php echo $this->quote_html( $tot_ttc ); ?> €</div></div>
        <?php endif; ?>
      </div>
    </div>

    <div style="position:absolute;left:21mm;bottom:22mm;width:calc(50% - 26mm);">
      <div style="font-size:7pt;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#C5A253;margin-bottom:5mm;">Signature ACDC Formation</div>
      <div style="display:flex;justify-content:center;align-items:center;min-height:60mm;">
        <img src="<?php echo $sig_data_uri ?: esc_url( $sig_img_url ); ?>" alt="Cachet et signature" style="max-width:100%;height:56mm;object-fit:contain;opacity:0.95;" />
      </div>
    </div>

    <div style="position:absolute;right:21mm;bottom:22mm;width:calc(50% - 26mm);">
      <div style="font-size:7pt;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#C5A253;margin-bottom:1mm;">Bon pour accord</div>
      <?php
      /* ACDC 3.25.236 — La mention recopiée par le signataire s'imprime ici,
         au-dessus de sa signature. Le cadre existait, vide de toute mention :
         il annonçait « Bon pour accord » sans que personne ne l'ait écrit. */
      ?>
      <?php if ( ! empty( $row['accord_signature_uri'] ) ) : ?>
      <div style="text-align:center;margin-bottom:3mm;">
        <img src="<?php echo $row['accord_signature_uri']; ?>" alt="Mention manuscrite" style="max-width:100%;height:14mm;object-fit:contain;" />
      </div>
      <?php elseif ( ! empty( $row['accord_mention'] ) ) : ?>
      <div style="text-align:center;font-size:10pt;font-style:italic;color:#1a2744;margin-bottom:3mm;">« <?php echo $this->quote_html( $row['accord_mention'] ); ?> »</div>
      <?php endif; ?>
      <div style="font-size:7pt;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#C5A253;margin-bottom:5mm;">Signature</div>
      <?php if ( ! empty( $row['client_signature_uri'] ) ) : ?>
      <div style="display:flex;flex-direction:column;justify-content:center;align-items:center;min-height:56mm;">
        <img src="<?php echo $row['client_signature_uri']; ?>" alt="Signature du client" style="max-width:100%;height:40mm;object-fit:contain;" />
        <div style="font-size:7pt;color:#4b5563;margin-top:2mm;"><?php echo $this->quote_html( $client_contact ?: $client_name ); ?><?php if ( ! empty( $row['client_signed_date'] ) ) : ?> — le <?php echo $this->quote_html( $row['client_signed_date'] ); ?><?php endif; ?></div>
      </div>
      <?php else : ?>
      <div style="min-height:60mm;"></div>
      <?php endif; ?>
    </div>

    <div class="footer">
      <?php echo $this->quote_html( $row['_org_address'] ?? '' ); ?> &mdash; Siret : <?php echo $this->quote_html( $row['_org_siret'] ?? '' ); ?><?php if ( ! empty( $row['_org_nda'] ) ) : ?> &mdash; NDA : <?php echo $this->quote_html( $row['_org_nda'] ); ?><?php endif; ?><br />
      <?php if ( ! empty( $row['_org_email'] ) ) : ?>e-mail : <?php echo $this->quote_html( $row['_org_email'] ); ?> &mdash; <?php endif; ?>Tél : <?php echo $this->quote_html( $row['_org_phone'] ?? '' ); ?> &mdash; site web : <?php echo $this->quote_html( $row['_org_website'] ?? '' ); ?>
    </div>
  </div>
</body>
</html>
    <?php
    $quote_doc_html = (string) ob_get_clean();
    /* Bouton « Enregistrer en PDF » : le gabarit est optimisé pour l'impression navigateur
       (@page A4 + @media print). window.print() produit donc un PDF fidèle (« Enregistrer au
       format PDF » dans la boîte d'impression). Le bouton est masqué dans le PDF lui-même. */
    $print_btn = '<style>@media print{.acdc-doc-print-btn{display:none !important;}}</style>'
      . '<div class="acdc-doc-print-btn" style="position:fixed;top:12px;right:12px;z-index:99999;">'
      . '<button type="button" onclick="window.print();return false;" style="background:#C5A253;color:#0B0706;border:none;border-radius:8px;padding:11px 20px;font-weight:700;font-size:14px;font-family:Arial,Helvetica,sans-serif;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.18);">&#128424;&#65039; Enregistrer en PDF</button>'
      . '</div>';
    if ( false !== strpos( $quote_doc_html, '<body>' ) ) {
      $quote_doc_html = $this->acdc_str_replace_first( '<body>', '<body>' . $print_btn, $quote_doc_html );
    }
    return $quote_doc_html;
  }

  /** Remplace uniquement la première occurrence de $search dans $subject. */
  private function acdc_str_replace_first( $search, $replace, $subject ) {
    $pos = strpos( $subject, $search );
    if ( false === $pos ) {
      return $subject;
    }
    return substr_replace( $subject, $replace, $pos, strlen( $search ) );
  }

  /**
   * Gabarit devis 100 % compatible mPDF (tables, pas de grid/flex/absolute).
   * Reprend exactement les mêmes valeurs calculées que get_quote_document_html
   * mais dans une mise en page à base de <table> que le moteur mPDF sait rendre
   * fidèlement pour produire un vrai fichier PDF téléchargeable.
   */
  private function get_quote_pdf_html( $row ) {
    $number         = ! empty( $row['number'] ) ? $row['number'] : trim( ( $row['prefix'] ?? 'DE-' ) . ( $row['num'] ?? '1' ) );
    $client_name    = ! empty( $row['client_company'] ) ? $row['client_company'] : $row['commanditaire_name'];
    $client_contact = ! empty( $row['client_contact'] ) ? $row['client_contact'] : $row['apprenant'];
    $formation      = ! empty( $row['formation_full'] ) ? $row['formation_full'] : $row['formation'];
    $tot_ht         = ! empty( $row['tarif_ht_number'] ) ? $row['tarif_ht_number'] : $this->normalize_price_number( $row['tarif_ht'] ?? '0' );
    $tot_ttc        = ! empty( $row['tarif_ttc_number'] ) ? $row['tarif_ttc_number'] : $this->normalize_price_number( $row['tarif_ttc'] ?? '0' );
    $vat_rate       = ! empty( $row['vat_rate_number'] ) ? $row['vat_rate_number'] : $this->normalize_price_number( $row['vat_rate'] ?? '0,00' );
    $tva_total      = ! empty( $row['tva_total_number'] ) ? $row['tva_total_number'] : $this->format_quote_money_value( (float) str_replace( ',', '.', $tot_ttc ) - (float) str_replace( ',', '.', $tot_ht ) );
    $quantity       = ! empty( $row['quantity_number'] ) ? $row['quantity_number'] : $this->normalize_price_number( $row['quantity'] ?? '1,00' );
    $is_vat_exempt  = ( (float) str_replace( ',', '.', $vat_rate ) == 0.0 );
    $vat_mention    = $this->acdc_mention_tva_document( $row );

    $sig_img_url   = 'https://acdcformation.com/wp-content/uploads/2026/04/Cachet-et-signature.png';
    $logo_img_url  = ! empty( $row['_org_logo'] ) ? (string) $row['_org_logo'] : 'https://acdcformation.com/wp-content/uploads/2026/03/Logo-ACDC.png';
    /* Affichés à 120 px et 48 px de haut : 400 et 200 px de large suffisent
       largement, y compris à l'impression. */
    $sig_data_uri  = $this->quote_img_to_data_uri( $sig_img_url, 400 );
    $logo_data_uri = $this->quote_img_to_data_uri( $logo_img_url, 200 );

    /* Désignation (formation + dates/modalités) sur plusieurs lignes. */
    $designation_lines = array( $this->quote_html( $formation ) );
    if ( ! empty( $row['start_date'] ) || ! empty( $row['end_date'] ) ) {
      $dates_str = trim( ( $row['start_date'] ?? '' ) . ( ! empty( $row['end_date'] ) && $row['end_date'] !== $row['start_date'] ? ' — ' . $row['end_date'] : '' ) );
      $designation_lines[] = 'Dates : ' . $this->quote_html( $dates_str );
    }
    if ( ! empty( $row['duration'] ) )          { $designation_lines[] = 'Durée : ' . $this->quote_html( $row['duration'] ); }
    if ( ! empty( $row['format'] ) )            { $designation_lines[] = 'Format : ' . $this->quote_html( $row['format'] ); }
    if ( ! empty( $row['location'] ) )          { $designation_lines[] = 'Lieu : ' . $this->quote_html( $row['location'] ); }
    if ( ! empty( $row['trained_headcount'] ) ) { $designation_lines[] = 'Effectif : ' . $this->quote_html( $row['trained_headcount'] ); }
    $designation_html = implode( '<br />', $designation_lines );

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <title>Devis <?php echo $this->quote_html( $number ); ?></title>
  <style>
    body { font-family: sans-serif; color:#1f2937; font-size:9pt; }
    .brand-name { color:#0C2D52; font-size:15pt; font-weight:bold; }
    .brand-sub { color:#C5A253; font-size:8pt; }
    .org-block { font-size:8pt; color:#374151; line-height:1.4; text-align:right; }
    .rule { border-bottom:1.5px solid #C5A253; height:0; line-height:0; font-size:0; }
    .box { border:1px solid #d7dde6; padding:6px 8px; }
    .box-title { font-size:8pt; color:#C5A253; font-weight:bold; text-transform:uppercase; padding-bottom:3px; }
    .box-lines { font-size:9pt; line-height:1.5; color:#1f2937; }
    h1.devis-title { color:#0C2D52; font-size:16pt; font-weight:bold; margin:14px 0 6px; }
    table.items { width:100%; border-collapse:collapse; font-size:8.5pt; }
    table.items thead th { background:#0C2D52; color:#ffffff; font-weight:bold; padding:6px 5px; text-align:left; }
    table.items tbody td { padding:7px 5px; border-bottom:1px solid #d7dde6; vertical-align:top; }
    table.totals { width:100%; border-collapse:collapse; font-size:9pt; border:1px solid #d7dde6; }
    table.totals td { padding:6px 8px; border-bottom:1px solid #d7dde6; }
    table.totals tr.grand td { background:#EDF1F6; color:#0C2D52; font-weight:bold; }
    .mentions { font-size:7.5pt; color:#374151; line-height:1.4; }
    .bank { font-size:7.5pt; color:#0C2D52; }
    .sig-title { font-size:7.5pt; font-weight:bold; text-transform:uppercase; color:#C5A253; padding-bottom:4px; }
    .sig-cell { border-top:1px solid #d7dde6; padding-top:6px; }
    .footer { border-top:1px solid #d7dde6; padding-top:6px; margin-top:10px; text-align:center; font-size:7pt; color:#4b5563; line-height:1.4; }
  </style>
</head>
<body>
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td width="55%" valign="middle">
        <table cellpadding="0" cellspacing="0"><tr>
          <?php if ( $logo_data_uri ) : ?><td valign="middle" style="padding-right:8px;"><img src="<?php echo $logo_data_uri; ?>" height="48" /></td><?php endif; ?>
          <td valign="middle">
            <span class="brand-name"><?php echo $this->quote_html( $row['_org_name'] ?? 'ACDC Formation' ); ?></span><br />
            <span class="brand-sub">Devis de formation professionnelle</span>
          </td>
        </tr></table>
      </td>
      <td width="45%" valign="top" class="org-block">
        <strong><?php echo $this->quote_html( $row['_org_name'] ?? 'ACDC Formation' ); ?></strong><br />
        <?php echo $this->quote_html( $row['_org_address'] ?? '' ); ?><br />
        <?php if ( ! empty( $row['_org_siret'] ) ) : ?>Siret : <?php echo $this->quote_html( $row['_org_siret'] ); ?><br /><?php endif; ?>
        <?php if ( ! empty( $row['_org_nda'] ) ) : ?>NDA : <?php echo $this->quote_html( $row['_org_nda'] ); ?><?php endif; ?>
      </td>
    </tr>
  </table>
  <div class="rule">&nbsp;</div>
  <br />

  <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td width="58%" valign="top" style="padding-right:8px;">
        <div class="box">
          <div class="box-title">Client</div>
          <div class="box-lines">
            Nom / Société : <?php echo $this->quote_html( $client_name ); ?><br />
            <?php if ( ! empty( $row['client_siret'] ) ) : ?>SIRET : <?php echo $this->quote_html( $row['client_siret'] ); ?><br /><?php endif; ?>
            Adresse : <?php echo $this->quote_html( $row['client_address_full'] ?? '' ); ?><br />
            Code postal / Ville : <?php echo $this->quote_html( $row['client_postal_city'] ?? '' ); ?><br />
            Contact : <?php echo $this->quote_html( $client_contact ); ?>
          </div>
        </div>
      </td>
      <td width="42%" valign="top">
        <div class="box">
          <div class="box-title">Devis</div>
          <div class="box-lines">
            N&deg; devis : <?php echo $this->quote_html( $number ); ?><br />
            Date d&rsquo;&eacute;mission : <?php echo $this->quote_html( $row['emission_date'] ?? '' ); ?><br />
            Date de validit&eacute; : <?php echo $this->quote_html( $row['expiration_date'] ?? '' ); ?>
          </div>
        </div>
      </td>
    </tr>
  </table>

  <h1 class="devis-title">Devis</h1>

  <table class="items">
    <thead>
      <tr>
        <th width="42%">Désignation</th>
        <th width="11%">Quantité</th>
        <th width="15%"><?php echo $is_vat_exempt ? 'Prix net (€)' : 'P.U (€ HT)'; ?></th>
        <?php if ( ! $is_vat_exempt ) : ?><th width="12%">TVA (%)</th><?php endif; ?>
        <th width="20%"><?php echo $is_vat_exempt ? 'Total net (€)' : 'Total (€ HT)'; ?></th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><?php echo $designation_html; ?></td>
        <td><?php echo $this->quote_html( $quantity ); ?></td>
        <td><?php echo $this->quote_html( $tot_ht ); ?></td>
        <?php if ( ! $is_vat_exempt ) : ?><td><?php echo $this->quote_html( $vat_rate ); ?></td><?php endif; ?>
        <td><?php echo $this->quote_html( $tot_ht ); ?></td>
      </tr>
    </tbody>
  </table>

  <br />
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td width="55%" valign="top" style="padding-right:8px;">
        <div class="mentions">
          <?php echo $this->quote_html( $row['payment_methods'] ?? '' ); ?>
        </div>
        <br />
        <div class="bank">IBAN : <?php echo $this->quote_html( $row['iban'] ?? '' ); ?> &mdash; BIC : <?php echo $this->quote_html( $row['bic'] ?? '' ); ?></div>
        <br />
        <div class="mentions">
          <?php if ( $is_vat_exempt ) : ?>
          Devis valable <?php echo $this->quote_html( $row['validity_days'] ?? '30' ); ?> jours. Prix nets de TVA.<?php echo '' !== $vat_mention ? ' ' . $this->quote_html( $vat_mention ) . '.' : ''; ?> Certifié Qualiopi.
          <?php else : ?>
          Devis valable <?php echo $this->quote_html( $row['validity_days'] ?? '30' ); ?> jours. Prix HT et TTC. TVA en vigueur. Certifié Qualiopi.
          <?php endif; ?>
        </div>
      </td>
      <td width="45%" valign="top">
        <table class="totals" cellpadding="0" cellspacing="0">
          <?php if ( $is_vat_exempt ) : ?>
          <tr class="grand"><td>Total net (€)</td><td align="right"><?php echo $this->quote_html( $tot_ht ); ?> €</td></tr>
          <?php if ( '' !== $vat_mention ) : ?><tr><td colspan="2" style="font-size:7.5pt;color:#6b7280;"><?php echo $this->quote_html( $vat_mention ); ?></td></tr><?php endif; ?>
          <?php else : ?>
          <tr><td><strong>Total HT (€)</strong></td><td align="right"><?php echo $this->quote_html( $tot_ht ); ?> €</td></tr>
          <tr><td><strong>Total TVA (€)</strong></td><td align="right"><?php echo $this->quote_html( $tva_total ); ?> €</td></tr>
          <tr class="grand"><td>Total TTC (€)</td><td align="right"><?php echo $this->quote_html( $tot_ttc ); ?> €</td></tr>
          <?php endif; ?>
        </table>
      </td>
    </tr>
  </table>

  <br /><br />
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td width="50%" valign="top" style="padding-right:10px;">
        <div class="sig-title">Signature ACDC Formation</div>
        <div class="sig-cell" style="text-align:center;">
          <?php if ( $sig_data_uri ) : ?><img src="<?php echo $sig_data_uri; ?>" height="120" /><?php else : ?>&nbsp;<?php endif; ?>
        </div>
      </td>
      <td width="50%" valign="top">
        <div class="sig-title">Bon pour accord</div>
        <div class="sig-cell" style="min-height:120px;text-align:center;">
          <?php if ( ! empty( $row['accord_signature_uri'] ) ) : ?>
            <img src="<?php echo $row['accord_signature_uri']; ?>" height="34" style="display:block;margin:0 auto 2mm;" /><br />
          <?php elseif ( ! empty( $row['accord_mention'] ) ) : ?>
            <div style="font-size:10pt;font-style:italic;color:#1a2744;margin-bottom:2mm;">« <?php echo $this->quote_html( $row['accord_mention'] ); ?> »</div>
          <?php endif; ?>
          <?php if ( ! empty( $row['client_signature_uri'] ) ) : ?>
            <img src="<?php echo $row['client_signature_uri']; ?>" height="90" /><br />
            <span style="font-size:7pt;color:#4b5563;"><?php echo $this->quote_html( $client_contact ?: $client_name ); ?><?php if ( ! empty( $row['client_signed_date'] ) ) : ?> — le <?php echo $this->quote_html( $row['client_signed_date'] ); ?><?php endif; ?></span>
          <?php elseif ( empty( $row['accord_signature_uri'] ) && empty( $row['accord_mention'] ) ) : ?>&nbsp;<?php endif; ?>
        </div>
      </td>
    </tr>
  </table>

  <div class="footer">
    <?php echo $this->quote_html( $row['_org_address'] ?? '' ); ?> &mdash; Siret : <?php echo $this->quote_html( $row['_org_siret'] ?? '' ); ?><?php if ( ! empty( $row['_org_nda'] ) ) : ?> &mdash; NDA : <?php echo $this->quote_html( $row['_org_nda'] ); ?><?php endif; ?><br />
    <?php if ( ! empty( $row['_org_email'] ) ) : ?>e-mail : <?php echo $this->quote_html( $row['_org_email'] ); ?> &mdash; <?php endif; ?>Tél : <?php echo $this->quote_html( $row['_org_phone'] ?? '' ); ?> &mdash; site web : <?php echo $this->quote_html( $row['_org_website'] ?? '' ); ?>
  </div>
</body>
</html>
    <?php
    return (string) ob_get_clean();
  }

  /**
   * Génère le binaire PDF du devis via mPDF (gabarit table-based).
   * @return string Binaire PDF, ou '' si mPDF indisponible / erreur.
   */
  private function build_quote_pdf( $row ) {
    $html = $this->get_quote_pdf_html( $row );
    if ( '' === (string) $html ) { return ''; }

    $autoload = dirname( dirname( dirname( dirname( plugin_dir_path( __FILE__ ) ) ) ) ) . '/acdc-libs/vendor/autoload.php';
    if ( file_exists( $autoload ) ) { require_once $autoload; }
    if ( ! class_exists( '\Mpdf\Mpdf' ) ) { return ''; }

    try {
      $mpdf = new \Mpdf\Mpdf( array(
        'format'        => 'A4',
        'margin_top'    => 14,
        'margin_bottom' => 14,
        'margin_left'   => 14,
        'margin_right'  => 14,
        'tempDir'       => sys_get_temp_dir(),
      ) );
      $mpdf->SetTitle( 'Devis ' . sanitize_file_name( (string) ( $row['number'] ?? '' ) ) );
      $mpdf->WriteHTML( $html );
      $pdf = $mpdf->Output( '', 'S' );
      return is_string( $pdf ) ? $pdf : '';
    } catch ( \Throwable $e ) {
      /* Ne jamais casser l'envoi de l'e-mail : on trace la cause pour diagnostic. */
      error_log( 'ACDC build_quote_pdf: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
      return '';
    }
  }

  /**
   * ACDC 3.25.225 — LES STATISTIQUES FINANCIÈRES NE CONNAISSAIENT QUE LA DÉMO.
   *
   * L'écran « Financières » affichait « Aucune donnée ne correspond aux
   * critères demandés » quel que soit le contenu du site : sa seule source
   * était `get_mock_invoices_data()`, qui rend un tableau VIDE dès que le mode
   * démonstration est désactivé — c'est-à-dire dès qu'on travaille pour de
   * vrai. Un organisme pouvait facturer toute l'année sans qu'une seule ligne
   * n'apparaisse, et rien ne le lui disait.
   *
   * On ne réécrit pas la conversion facture → ligne d'écran : l'onglet
   * Factures possède déjà la sienne, qui tient compte des frais de
   * déplacement, des repas, des lignes libres et de la TVA. En écrire une
   * seconde ici, c'est se garantir deux totaux différents pour la même
   * facture — la faute que ce plugin a déjà payée plusieurs fois.
   *
   * @param string $scope 'action' (actions de formation) ou 'ancillary'.
   * @return array Lignes au format attendu par l'écran.
   */
  private function get_financial_invoice_rows( $scope = 'action' ) {
    $scope = 'ancillary' === $scope ? 'ancillary' : 'action';

    $invoices = $this->get_invoices( array( 'scope' => $scope, 'limit' => 500 ) );
    if ( empty( $invoices ) ) {
      /* Les données de démonstration ne servent plus que de garniture quand le
         mode démo est armé et qu'aucune facture réelle n'existe. */
      return $this->get_mock_invoices_data( $scope );
    }

    $rows = array();
    foreach ( (array) $invoices as $inv ) {
      $row = $this->build_invoice_row_from_record( $inv );
      if ( empty( $row ) ) {
        continue;
      }
      /* L'écran des statistiques attend un libellé lisible et un type ; la
         ligne de l'onglet Factures porte le code de statut. */
      $row['type']   = ( 'avoir' === (string) $row['status'] ) ? 'Avoir' : 'Facture';
      $row['status'] = mb_strtoupper( (string) $row['status_label'] );
      $rows[] = $row;
    }

    return $rows;
  }

  private function get_mock_invoices_data( $scope = 'action' ) {
    if ( ! $this->is_documents_billing_demo_enabled() ) {
      return array();
    }
    static $cache = array();
    $scope = 'ancillary' === $scope ? 'ancillary' : 'action';
    if ( isset( $cache[ $scope ] ) ) {
      return $cache[ $scope ];
    }
    $rows = array(
      array(
        'id' => 1,
        'number' => 'FA-2026-4',
        'type' => 'Facture',
        'prefix' => 'FA-2026-',
        'num' => '4',
        'commanditaire_type' => 'Particulier',
        'commanditaire_name' => 'Chloé ARNAUD',
        'apprenant' => 'Chloé ARNAUD',
        'financeur' => '—',
        'status' => 'EN RETARD DE PAIEMENT',
        'status_key' => 'late',
        'relance_date' => 'Aucune relance',
        'formation' => '2 | Statuts du couple et succe...',
        'formation_full' => '2 | Statuts du couple et successions (Présentiel) 2026',
        'format' => 'Présentiel',
        'formation_address' => '150 rue de la tuilerie',
        'formation_postal_code' => '83520',
        'formation_city' => 'Roquebrune-sur-Argens',
        'duration' => '14h00',
        'start_date' => '14/01/2026',
        'end_date' => '12/02/2026',
        'trained_headcount' => '1',
        'emission_date' => '13/02/2026',
        'due_date' => '13/03/2026',
        'quantity' => '1.00',
        'tarif_ht' => '588.00 €',
        'tarif_ht_value' => '588,00',
        'montant_tva' => '117.60 €',
        'tarif_ttc' => '705.60 €',
        'tarif_ttc_value' => '705,60',
        'vat_rate' => '20,00',
        'address' => '13 AVENUE DES ROSES TREMIERES',
        'address_complement' => '—',
        'postal_code' => '83520',
        'city' => 'Roquebrune-sur-Argens',
        'client_contact' => 'Chloé ARNAUD',
        'client_company' => '',
        'reference' => 'Statuts du couple et successions (Présentiel) 2026',
        'designation' => "Formation : Statuts du couple et successions\nDescription / modalité : Présentiel\nDates de la prestation : 14/01/2026 - 12/02/2026",
        'payment_methods' => "Règlement par virement bancaire à l’édition de la facture. En cas de retard, pénalités au taux légal majoré et indemnité forfaitaire de 40 € pour frais de recouvrement.",
        'iban' => 'FR76 3000 4023 7500 0101 1397 203',
        'bic' => 'BNPAFRPPXXX',
        'created_at' => '13/02/2026',
        'updated_at' => '13/02/2026',
      ),
      array(
        'id' => 2,
        'number' => 'FA-2026-3','type'=>'Facture','prefix'=>'FA-2026-','num'=>'3','commanditaire_type'=>'Particulier','commanditaire_name'=>'Mareva NAKAK','apprenant'=>'Mareva NAKAK','financeur'=>'—','status'=>'EN RETARD DE PAIEMENT','status_key'=>'late','relance_date'=>'Aucune relance','formation'=>'2 | Statuts du couple et succe...','formation_full'=>'2 | Statuts du couple et successions (Présentiel) 2026','format'=>'Présentiel','formation_address'=>'150 rue de la tuilerie','formation_postal_code'=>'83520','formation_city'=>'Roquebrune-sur-Argens','duration'=>'14h00','start_date'=>'14/01/2026','end_date'=>'12/02/2026','trained_headcount'=>'1','emission_date'=>'13/02/2026','due_date'=>'13/03/2026','quantity'=>'1.00','tarif_ht'=>'588.00 €','tarif_ht_value'=>'588,00','montant_tva'=>'117.60 €','tarif_ttc'=>'705.60 €','tarif_ttc_value'=>'705,60','vat_rate'=>'20,00','address'=>'13 AVENUE DES ROSES TREMIERES','address_complement'=>'—','postal_code'=>'83520','city'=>'Roquebrune-sur-Argens','client_contact'=>'Mareva NAKAK','client_company'=>'','reference'=>'Statuts du couple et successions (Présentiel) 2026','designation'=>"Formation : Statuts du couple et successions\nDescription / modalité : Présentiel\nDates de la prestation : 14/01/2026 - 12/02/2026",'payment_methods'=>"Règlement par virement bancaire à l’édition de la facture. En cas de retard, pénalités au taux légal majoré et indemnité forfaitaire de 40 € pour frais de recouvrement.",'iban'=>'FR76 3000 4023 7500 0101 1397 203','bic'=>'BNPAFRPPXXX','created_at'=>'13/02/2026','updated_at'=>'13/02/2026'
      ),
      array(
        'id' => 3,'number'=>'FA-2026-2','type'=>'Facture','prefix'=>'FA-2026-','num'=>'2','commanditaire_type'=>'Particulier','commanditaire_name'=>'Quentin CHANON','apprenant'=>'Quentin CHANON','financeur'=>'—','status'=>'EN RETARD DE PAIEMENT','status_key'=>'late','relance_date'=>'Aucune relance','formation'=>'2 | Statuts du couple et succe...','formation_full'=>'2 | Statuts du couple et successions (Présentiel) 2026','format'=>'Présentiel','formation_address'=>'150 rue de la tuilerie','formation_postal_code'=>'83520','formation_city'=>'Roquebrune-sur-Argens','duration'=>'14h00','start_date'=>'14/01/2026','end_date'=>'12/02/2026','trained_headcount'=>'1','emission_date'=>'13/02/2026','due_date'=>'13/03/2026','quantity'=>'1.00','tarif_ht'=>'588.00 €','tarif_ht_value'=>'588,00','montant_tva'=>'117.60 €','tarif_ttc'=>'705.60 €','tarif_ttc_value'=>'705,60','vat_rate'=>'20,00','address'=>'13 AVENUE DES ROSES TREMIERES','address_complement'=>'—','postal_code'=>'83520','city'=>'Roquebrune-sur-Argens','client_contact'=>'Quentin CHANON','client_company'=>'','reference'=>'Statuts du couple et successions (Présentiel) 2026','designation'=>"Formation : Statuts du couple et successions\nDescription / modalité : Présentiel\nDates de la prestation : 14/01/2026 - 12/02/2026",'payment_methods'=>"Règlement par virement bancaire à l’édition de la facture. En cas de retard, pénalités au taux légal majoré et indemnité forfaitaire de 40 € pour frais de recouvrement.",'iban'=>'FR76 3000 4023 7500 0101 1397 203','bic'=>'BNPAFRPPXXX','created_at'=>'13/02/2026','updated_at'=>'13/02/2026'
      ),
      array(
        'id' => 4,'number'=>'FA-2026-1','type'=>'Facture','prefix'=>'FA-2026-','num'=>'1','commanditaire_type'=>'Particulier','commanditaire_name'=>'Arnaud GUEGAN','apprenant'=>'Arnaud GUEGAN','financeur'=>'—','status'=>'EN RETARD DE PAIEMENT','status_key'=>'late','relance_date'=>'Aucune relance','formation'=>'2 | Statuts du couple et succe...','formation_full'=>'2 | Statuts du couple et successions (Présentiel) 2026','format'=>'Présentiel','formation_address'=>'150 rue de la tuilerie','formation_postal_code'=>'83520','formation_city'=>'Roquebrune-sur-Argens','duration'=>'14h00','start_date'=>'14/01/2026','end_date'=>'12/02/2026','trained_headcount'=>'1','emission_date'=>'13/02/2026','due_date'=>'13/03/2026','quantity'=>'1.00','tarif_ht'=>'588.00 €','tarif_ht_value'=>'588,00','montant_tva'=>'117.60 €','tarif_ttc'=>'705.60 €','tarif_ttc_value'=>'705,60','vat_rate'=>'20,00','address'=>'13 AVENUE DES ROSES TREMIERES','address_complement'=>'—','postal_code'=>'83520','city'=>'Roquebrune-sur-Argens','client_contact'=>'Arnaud GUEGAN','client_company'=>'','reference'=>'Statuts du couple et successions (Présentiel) 2026','designation'=>"Formation : Statuts du couple et successions\nDescription / modalité : Présentiel\nDates de la prestation : 14/01/2026 - 12/02/2026",'payment_methods'=>"Règlement par virement bancaire à l’édition de la facture. En cas de retard, pénalités au taux légal majoré et indemnité forfaitaire de 40 € pour frais de recouvrement.",'iban'=>'FR76 3000 4023 7500 0101 1397 203','bic'=>'BNPAFRPPXXX','created_at'=>'13/02/2026','updated_at'=>'13/02/2026'
      ),
      array(
        'id' => 5,'number'=>'FA-2025-4','type'=>'Facture','prefix'=>'FA-2025-','num'=>'4','commanditaire_type'=>'Entreprise','commanditaire_name'=>'Quentin CHANON','apprenant'=>'Quentin CHANON','financeur'=>'—','status'=>'PAYÉE','status_key'=>'paid','relance_date'=>'Aucune relance','formation'=>'3 | La Société Civile Immobili...','formation_full'=>'3 | La Société Civile Immobilière (Présentiel) 2025','format'=>'Présentiel','formation_address'=>'150 rue de la tuilerie','formation_postal_code'=>'83520','formation_city'=>'Roquebrune-sur-Argens','duration'=>'7h00','start_date'=>'21/11/2025','end_date'=>'21/11/2025','trained_headcount'=>'1','emission_date'=>'26/11/2025','due_date'=>'26/12/2025','quantity'=>'1.00','tarif_ht'=>'294.00 €','tarif_ht_value'=>'294,00','montant_tva'=>'58.80 €','tarif_ttc'=>'352.80 €','tarif_ttc_value'=>'352,80','vat_rate'=>'20,00','address'=>'13 AVENUE DES ROSES TREMIERES','address_complement'=>'—','postal_code'=>'83520','city'=>'Roquebrune-sur-Argens','client_contact'=>'Quentin CHANON','client_company'=>'Quentin CHANON','reference'=>'La Société Civile Immobilière (Présentiel) 2025','designation'=>"Formation : La Société Civile Immobilière\nDescription / modalité : Présentiel\nDates de la prestation : 21/11/2025",'payment_methods'=>"Règlement par virement bancaire à l’édition de la facture. En cas de retard, pénalités au taux légal majoré et indemnité forfaitaire de 40 € pour frais de recouvrement.",'iban'=>'FR76 3000 4023 7500 0101 1397 203','bic'=>'BNPAFRPPXXX','created_at'=>'26/11/2025','updated_at'=>'26/11/2025'
      ),
      array(
        'id' => 6,'number'=>'FA-2025-3','type'=>'Facture','prefix'=>'FA-2025-','num'=>'3','commanditaire_type'=>'Entreprise','commanditaire_name'=>'Stéphane THIBOULT','apprenant'=>'Stéphane THIBOULT','financeur'=>'—','status'=>'PAYÉE','status_key'=>'paid','relance_date'=>'Aucune relance','formation'=>'3 | La Société Civile Immobili...','formation_full'=>'3 | La Société Civile Immobilière (Présentiel) 2025','format'=>'Présentiel','formation_address'=>'150 rue de la tuilerie','formation_postal_code'=>'83520','formation_city'=>'Roquebrune-sur-Argens','duration'=>'7h00','start_date'=>'15/12/2025','end_date'=>'15/12/2025','trained_headcount'=>'1','emission_date'=>'15/12/2025','due_date'=>'15/01/2026','quantity'=>'1.00','tarif_ht'=>'294.00 €','tarif_ht_value'=>'294,00','montant_tva'=>'58.80 €','tarif_ttc'=>'352.80 €','tarif_ttc_value'=>'352,80','vat_rate'=>'20,00','address'=>'13 AVENUE DES ROSES TREMIERES','address_complement'=>'—','postal_code'=>'83520','city'=>'Roquebrune-sur-Argens','client_contact'=>'Stéphane THIBOULT','client_company'=>'Stéphane THIBOULT','reference'=>'La Société Civile Immobilière (Présentiel) 2025','designation'=>"Formation : La Société Civile Immobilière\nDescription / modalité : Présentiel\nDates de la prestation : 15/12/2025",'payment_methods'=>"Règlement par virement bancaire à l’édition de la facture. En cas de retard, pénalités au taux légal majoré et indemnité forfaitaire de 40 € pour frais de recouvrement.",'iban'=>'FR76 3000 4023 7500 0101 1397 203','bic'=>'BNPAFRPPXXX','created_at'=>'15/12/2025','updated_at'=>'15/12/2025'
      ),
      array(
        'id' => 7,'number'=>'FA-2025-2','type'=>'Facture','prefix'=>'FA-2025-','num'=>'2','commanditaire_type'=>'Entreprise','commanditaire_name'=>'Arnaud GUEGAN','apprenant'=>'Arnaud GUEGAN','financeur'=>'—','status'=>'PAYÉE','status_key'=>'paid','relance_date'=>'Aucune relance','formation'=>'3 | La Société Civile Immobili...','formation_full'=>'3 | La Société Civile Immobilière (Présentiel) 2025','format'=>'Présentiel','formation_address'=>'150 rue de la tuilerie','formation_postal_code'=>'83520','formation_city'=>'Roquebrune-sur-Argens','duration'=>'7h00','start_date'=>'01/12/2025','end_date'=>'01/12/2025','trained_headcount'=>'1','emission_date'=>'01/12/2025','due_date'=>'01/01/2026','quantity'=>'1.00','tarif_ht'=>'294.00 €','tarif_ht_value'=>'294,00','montant_tva'=>'58.80 €','tarif_ttc'=>'352.80 €','tarif_ttc_value'=>'352,80','vat_rate'=>'20,00','address'=>'13 AVENUE DES ROSES TREMIERES','address_complement'=>'—','postal_code'=>'83520','city'=>'Roquebrune-sur-Argens','client_contact'=>'Arnaud GUEGAN','client_company'=>'Arnaud GUEGAN','reference'=>'La Société Civile Immobilière (Présentiel) 2025','designation'=>"Formation : La Société Civile Immobilière\nDescription / modalité : Présentiel\nDates de la prestation : 01/12/2025",'payment_methods'=>"Règlement par virement bancaire à l’édition de la facture. En cas de retard, pénalités au taux légal majoré et indemnité forfaitaire de 40 € pour frais de recouvrement.",'iban'=>'FR76 3000 4023 7500 0101 1397 203','bic'=>'BNPAFRPPXXX','created_at'=>'01/12/2025','updated_at'=>'01/12/2025'
      ),
      array(
        'id' => 8,'number'=>'FA-2025-1','type'=>'Facture','prefix'=>'FA-2025-','num'=>'1','commanditaire_type'=>'Entreprise','commanditaire_name'=>'Mareva NAKAK','apprenant'=>'Mareva NAKAK','financeur'=>'—','status'=>'PAYÉE','status_key'=>'paid','relance_date'=>'Aucune relance','formation'=>'3 | La Société Civile Immobili...','formation_full'=>'3 | La Société Civile Immobilière (Présentiel) 2025','format'=>'Présentiel','formation_address'=>'150 rue de la tuilerie','formation_postal_code'=>'83520','formation_city'=>'Roquebrune-sur-Argens','duration'=>'7h00','start_date'=>'21/11/2025','end_date'=>'21/11/2025','trained_headcount'=>'1','emission_date'=>'21/11/2025','due_date'=>'21/12/2025','quantity'=>'1.00','tarif_ht'=>'294.00 €','tarif_ht_value'=>'294,00','montant_tva'=>'58.80 €','tarif_ttc'=>'352.80 €','tarif_ttc_value'=>'352,80','vat_rate'=>'20,00','address'=>'13 AVENUE DES ROSES TREMIERES','address_complement'=>'—','postal_code'=>'83520','city'=>'Roquebrune-sur-Argens','client_contact'=>'Mareva NAKAK','client_company'=>'Mareva NAKAK','reference'=>'La Société Civile Immobilière (Présentiel) 2025','designation'=>"Formation : La Société Civile Immobilière\nDescription / modalité : Présentiel\nDates de la prestation : 21/11/2025",'payment_methods'=>"Règlement par virement bancaire à l’édition de la facture. En cas de retard, pénalités au taux légal majoré et indemnité forfaitaire de 40 € pour frais de recouvrement.",'iban'=>'FR76 3000 4023 7500 0101 1397 203','bic'=>'BNPAFRPPXXX','created_at'=>'21/11/2025','updated_at'=>'21/11/2025'
      ),
    );
    foreach ( $rows as &$row ) {
      $row['scope'] = $scope;
      if ( 'ancillary' === $scope ) {
        $row['formation'] = str_replace( 'Statuts du couple et succe...', 'Prestation annexe comptable', $row['formation'] );
        $row['formation_full'] = str_replace( 'Statuts du couple et successions (Présentiel) 2026', 'Prestation annexe comptable 2026', $row['formation_full'] );
        $row['reference'] = str_replace( 'Statuts du couple et successions (Présentiel) 2026', 'Prestation annexe comptable 2026', $row['reference'] );
        $row['designation'] = str_replace( 'Formation : Statuts du couple et successions', 'Prestation annexe : accompagnement complémentaire', $row['designation'] );
      }
      $row['client_address_full'] = trim( $row['address'] . ( ! empty( $row['address_complement'] ) && '—' !== $row['address_complement'] ? ' ' . $row['address_complement'] : '' ) );
      $row['client_postal_city'] = trim( $row['postal_code'] . ' ' . $row['city'] );
      $row['location'] = trim( $row['formation_address'] . ', ' . $row['formation_postal_code'] . ' ' . $row['formation_city'] );
      $row['tarif_ht_number'] = $this->normalize_price_number( $row['tarif_ht_value'] );
      $row['tarif_ttc_number'] = $this->normalize_price_number( $row['tarif_ttc_value'] );
      $row['quantity_number'] = $this->normalize_price_number( $row['quantity'], 2 );
      $row['vat_rate_number'] = $this->normalize_price_number( $row['vat_rate'], 2 );
      $row['tva_total_number'] = $this->normalize_price_number( str_replace( ' €', '', $row['montant_tva'] ) );
      $row['credit_note'] = array(
        'id' => $row['id'],
        'number' => 'AV-2026-5',
        'prefix' => 'AV-2026-',
        'num' => '5',
        'emission_date' => '',
        'due_date' => '',
        'designation' => "Commanditaire : {$row['commanditaire_name']}\nFormation : {$row['reference']}\nDates de la prestation : {$row['start_date']} - {$row['end_date']}\nDurée : {$row['duration']}\nFormat : {$row['format']}\nLieu : {$row['formation_address']} {$row['formation_postal_code']} {$row['formation_city']}",
        'quantity' => '1,00',
        'tarif_ht_value' => $row['tarif_ht_value'],
        'vat_rate' => $row['vat_rate'],
        'tarif_ttc_value' => $row['tarif_ttc_value'],
        'billing_address' => "68 Via Nova - Pôle d'excellence Jean Louis - Immeuble le Triangle\n83600, Fréjus, FR",
        'payment_methods' => "L’avoir sera imputé automatiquement sur la prochaine facture.\nEn cas d’impossibilité, le remboursement sera effectué par virement bancaire dans un délai de 30 jours à compter de son émission.",
        'comment' => '',
      );
    }
    unset( $row );
    $cache[ $scope ] = $rows;
    return $cache[ $scope ];
  }

  private function get_mock_invoice_record( $invoice_id, $scope = 'action' ) {
    foreach ( $this->get_mock_invoices_data( $scope ) as $row ) {
      if ( (int) $row['id'] === (int) $invoice_id ) {
        return $row;
      }
    }
    $rows = $this->get_mock_invoices_data( $scope );
    return reset( $rows );
  }

  private function get_invoices_scope() {
    $scope = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : '';
    return in_array( $scope, array( 'action', 'ancillary' ), true ) ? $scope : '';
  }

  private function get_invoices_action() {
    $action = isset( $_GET['invoice_action'] ) ? sanitize_key( wp_unslash( $_GET['invoice_action'] ) ) : '';
    return in_array( $action, array( 'view', 'create_credit_note', 'preview_invoice', 'preview_credit_note' ), true ) ? $action : '';
  }

  private function get_invoice_document_html( $row, $document_kind = 'invoice' ) {
    $is_credit = 'credit_note' === $document_kind;
    $doc_label = $is_credit ? 'Avoir' : 'Facture';
    $doc_span = $is_credit ? 'Modèle d’avoir de formation' : 'Modèle de facture de formation';
    $number = $is_credit ? ( $row['credit_note']['number'] ?? 'AV-2026-1' ) : ( $row['number'] ?? 'FA-2026-1' );
    $prefix_label = $is_credit ? 'N° avoir' : 'N° facture';
    $date_end_label = $is_credit ? 'Date d’échéance' : 'Date d’échéance';
    $date_end_value = $is_credit ? ( $row['credit_note']['due_date'] ?? '' ) : ( $row['due_date'] ?? '' );
    $client_name = ! empty( $row['client_company'] ) ? $row['client_company'] : $row['commanditaire_name'];
    $client_contact = ! empty( $row['client_contact'] ) ? $row['client_contact'] : $row['apprenant'];

    /* ACDC 3.25.258 — QUAND LA FACTURE EST ADRESSÉE AU FINANCEUR.
       Le pavé du haut porte alors le nom et l'adresse du financeur — c'est lui
       qui paie et lui qui la reçoit. Mais une facture d'OPCO qui ne nomme pas
       le bénéficiaire est inexploitable : l'organisme paie POUR quelqu'un, au
       titre d'un accord. Le bénéficiaire, la référence de prise en charge et
       la subrogation sont donc écrits, et le pavé s'intitule « Financeur »
       plutôt que « Client » — un pavé qui ment sur ce qu'il contient est pire
       qu'un pavé vide. */
    $billed_to_funder = ( ! $is_credit && 'funder' === (string) ( $row['billed_to'] ?? 'client' ) );
    $addressee_title  = $billed_to_funder ? 'Financeur' : 'Client';
    $addressee_name   = ! empty( $row['addressee_name'] ) ? (string) $row['addressee_name'] : $client_name;
    $addressee_addr   = isset( $row['addressee_address_full'] ) ? (string) $row['addressee_address_full'] : (string) ( $row['client_address_full'] ?? '' );
    $addressee_city   = isset( $row['addressee_postal_city'] ) ? (string) $row['addressee_postal_city'] : (string) ( $row['client_postal_city'] ?? '' );
    $beneficiary      = (string) ( $row['beneficiary_label'] ?? '' );
    if ( $billed_to_funder && '' === $beneficiary ) {
      $beneficiary = $client_name;
    }
    $pec_reference    = (string) ( $row['pec_reference'] ?? '' );
    if ( $billed_to_funder && '' !== $pec_reference && empty( $row['reference'] ) ) {
      $row['reference'] = $pec_reference;
    }
    $designation = $is_credit ? ( $row['credit_note']['designation'] ?? '' ) : ( $row['designation'] ?? '' );
    $formation = ! empty( $row['formation_full'] ) ? $row['formation_full'] : $row['formation'];
    $tot_ht = $is_credit ? $this->normalize_price_number( $row['credit_note']['tarif_ht_value'] ?? '0' ) : ( $row['tarif_ht_number'] ?? $this->normalize_price_number( $row['tarif_ht_value'] ?? '0' ) );
    $tot_ttc = $is_credit ? $this->normalize_price_number( $row['credit_note']['tarif_ttc_value'] ?? '0' ) : ( $row['tarif_ttc_number'] ?? $this->normalize_price_number( $row['tarif_ttc_value'] ?? '0' ) );
    /* ACDC 3.25.309 — L'avoir suit le régime de la facture qu'il annule : c'est
       la même opération, en sens inverse. Le repli à « 20,00 » facturait 20 % de
       TVA sur l'avoir d'une facture exonérée. */
    $vat_rate = $is_credit ? $this->normalize_price_number( $row['credit_note']['vat_rate'] ?? ( $row['vat_rate'] ?? '0,00' ) ) : ( $row['vat_rate_number'] ?? $this->normalize_price_number( $row['vat_rate'] ?? '0,00' ) );
    $vat_mention = $this->acdc_mention_tva_document( $row );
    $tva_total = $this->format_quote_money_value( (float) str_replace( ',', '.', $tot_ttc ) - (float) str_replace( ',', '.', $tot_ht ) );
    $quantity = $is_credit ? $this->normalize_price_number( $row['credit_note']['quantity'] ?? '1,00', 2 ) : ( $row['quantity_number'] ?? $this->normalize_price_number( $row['quantity'] ?? '1,00', 2 ) );
    $methods = $is_credit ? ( $row['credit_note']['payment_methods'] ?? '' ) : ( $row['payment_methods'] ?? '' );
    // ACDC — Images inlinées en base64 (mêmes sources que le devis)
    $logo_img_url    = ! empty( $row['_org_logo'] ) ? (string) $row['_org_logo'] : 'https://acdcformation.com/wp-content/uploads/2026/03/Logo-ACDC.png';
    $logo_data_uri   = $this->quote_img_to_data_uri( $logo_img_url, 200 );
    $sig_data_uri_t2 = $this->quote_img_to_data_uri( 'https://acdcformation.com/wp-content/uploads/2026/04/Signature-seule-David-scaled.png', 400 );
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Modèle de <?php echo esc_html( strtolower( $doc_label ) ); ?> - ACDC-Formation</title>
  <style>
    :root {--navy:#0C2D52;--gold:#C5A253;--gold-soft:#E8D8B1;--ink:#1f2937;--muted:#6b7280;--line:#d7dde6;--paper:#ffffff;}@page {size:A4 portrait;margin:0;}* { box-sizing: border-box; }body {margin:0;background:#F6F8FB;font-family:Rubik, Arial, Helvetica, sans-serif;color:var(--ink);} .page {width:210mm;min-height:297mm;height:297mm;margin:0 auto;background:var(--paper);box-shadow:0 10px 30px rgba(12,45,82,0.08);position:relative;padding:11mm 12.5mm 18mm;overflow:hidden;} .header {display:flex;justify-content:space-between;align-items:flex-start;gap:8mm;border-bottom:1.5px solid var(--gold);padding-bottom:3.5mm;margin-bottom:6mm;} .brand {display:flex;align-items:center;gap:4mm;min-width:0;} .brand img {height:16mm;width:auto;object-fit:contain;flex-shrink:0;} .brand-text strong {display:block;color:var(--navy);font-size:13.6pt;line-height:1.08;font-weight:700;} .brand-text span {display:block;margin-top:0.8mm;color:var(--gold);font-size:7.7pt;font-weight:500;letter-spacing:0.01em;} .company {text-align:right;font-size:8pt;line-height:1.32;color:#374151;max-width:64mm;} .top-grid {display:grid;grid-template-columns:1fr 69mm;gap:8mm;margin-bottom:7mm;} .box {border:1px solid var(--line);border-radius:10px;padding:4mm 4.5mm;background:#fff;} .box-title {font-size:8pt;text-transform:uppercase;letter-spacing:0.05em;color:var(--gold);font-weight:700;margin-bottom:2mm;} .client-lines, .quote-lines {font-size:8.9pt;line-height:1.5;color:#1f2937;} h1 {margin:0 0 4mm;color:var(--navy);font-size:15.4pt;font-weight:700;letter-spacing:0.01em;} .details {display:grid;grid-template-columns:repeat(2, 1fr);gap:3.2mm 6.5mm;margin-bottom:7mm;font-size:8.9pt;} .detail {display:flex;gap:2mm;align-items:baseline;} .detail strong {color:var(--navy);font-weight:600;min-width:38mm;} .detail span:last-child {flex:1;} table {width:100%;border-collapse:collapse;margin-top:2.5mm;font-size:8.6pt;} thead th {background:var(--navy);color:white;font-weight:600;padding:2.8mm 2.2mm;text-align:left;border-right:1px solid rgba(255,255,255,0.15);line-height:1.2;} thead th:last-child { border-right: 0; } tbody td {padding:3mm 2.2mm;border-bottom:1px solid var(--line);vertical-align:top;line-height:1.28;} .designation {min-height:18mm;line-height:1.28;} .totals {width:72mm;margin-left:auto;margin-top:7mm;border:1px solid var(--line);border-radius:10px;overflow:hidden;} .total-row {display:grid;grid-template-columns:1fr 28mm;font-size:8.8pt;} .total-row div {padding:3mm 3.2mm;border-bottom:1px solid var(--line);line-height:1.2;} .total-row:last-child div { border-bottom: 0; } .total-row strong {color:var(--navy);} .total-row.grand div {background:rgba(12,45,82,0.06);font-weight:700;color:var(--navy);} .notes {position:absolute;left:12.5mm;right:12.5mm;bottom:32mm;margin-top:0;margin-bottom:0;font-size:7.3pt;line-height:1.32;color:#374151;z-index:1;} .notes p {margin:0 0 2mm;text-align:justify;} .bank {font-size:7.2pt;color:var(--navy);font-weight:500;} .signature-zone {margin-top:0;display:grid;grid-template-columns:1fr 56mm;gap:5mm;align-items:end;position:absolute;left:12.5mm;right:12.5mm;bottom:12mm;} .sign-box {text-align:center;border-top:1px solid var(--line);padding-top:3.2mm;min-height:14mm;font-size:7.9pt;color:#4b5563;position:relative;overflow:visible;} .footer {position:absolute;left:10mm;right:10mm;bottom:4mm;border-top:1px solid var(--line);padding-top:2.2mm;text-align:center;font-size:6.8pt;line-height:1.22;color:#4b5563;}
  </style>
</head>
<body>
  <div class="page">
    <div class="header">
      <div class="brand">
        <img src="<?php echo $logo_data_uri ?: 'https://acdcformation.com/wp-content/uploads/2026/03/Logo-ACDC.png'; ?>" alt="Logo ACDC-Formation" />
        <div class="brand-text"><strong><?php echo esc_html( $this->acdc_org_identity()['raison_sociale'] ); ?></strong><span><?php echo esc_html( $doc_span ); ?></span></div>
      </div>
      <div class="company"><?php echo implode( '<br />', array_map( 'esc_html', \ACDC\Support\OrgIdentity::blockLines( $this->acdc_org_identity() ) ) ); ?></div>
    </div>
    <div class="top-grid">
      <div class="box"><div class="box-title"><?php echo esc_html( $addressee_title ); ?></div><div class="client-lines">Nom / Société : <?php echo esc_html( $addressee_name ); ?><br />Adresse : <?php echo esc_html( $addressee_addr ); ?><br />Code postal / Ville : <?php echo esc_html( $addressee_city ); ?><br /><?php if ( $billed_to_funder ) : ?>Bénéficiaire : <?php echo esc_html( $beneficiary ); ?><?php else : ?>Contact : <?php echo esc_html( $client_contact ); ?><?php endif; ?></div></div>
      <div class="box"><div class="box-title"><?php echo esc_html( $doc_label ); ?></div><div class="quote-lines"><?php echo esc_html( $prefix_label ); ?> : <?php echo esc_html( $number ); ?><br />Date d’émission : <?php echo esc_html( $is_credit ? ( $row['credit_note']['emission_date'] ?? '' ) : ( $row['emission_date'] ?? '' ) ); ?><br /><?php echo esc_html( $date_end_label ); ?> : <?php echo esc_html( $date_end_value ); ?></div></div>
    </div>
    <h1><?php echo esc_html( $doc_label ); ?></h1>
    <div class="details">
      <div class="detail"><strong>Formation :</strong><span><?php echo esc_html( $formation ); ?></span></div>
      <div class="detail"><strong>Commanditaire :</strong><span><?php echo esc_html( $billed_to_funder ? $beneficiary : $client_name ); ?></span></div>
      <?php if ( $billed_to_funder ) : ?>
      <div class="detail"><strong>Facturé au financeur :</strong><span><?php echo esc_html( $addressee_name ); ?></span></div>
      <?php if ( '' !== $pec_reference ) : ?><div class="detail"><strong>Accord de prise en charge :</strong><span><?php echo esc_html( $pec_reference ); ?></span></div><?php endif; ?>
      <div class="detail"><strong>Subrogation de paiement :</strong><span><?php echo ! empty( $row['pec_subrogation'] ) ? 'Oui — règlement direct par le financeur' : 'Non'; ?></span></div>
      <?php endif; ?>
      <div class="detail"><strong>Dates de la prestation :</strong><span><?php echo esc_html( trim( ( $row['start_date'] ?? '' ) . ' - ' . ( $row['end_date'] ?? '' ) ) ); ?></span></div>
      <div class="detail"><strong>Durée :</strong><span><?php echo esc_html( $row['duration'] ?? '' ); ?></span></div>
      <div class="detail"><strong>Format :</strong><span><?php echo esc_html( $row['format'] ?? '' ); ?></span></div>
      <div class="detail"><strong>Lieu :</strong><span><?php echo esc_html( $row['location'] ?? '' ); ?></span></div>
      <div class="detail"><strong>Effectif :</strong><span><?php echo esc_html( $row['trained_headcount'] ?? '' ); ?></span></div>
      <div class="detail"><strong>Référence :</strong><span><?php echo esc_html( $row['reference'] ?? '' ); ?></span></div>
      <?php if ( ! $is_credit ) : /* Mentions obligatoires réforme facturation 2026 */ ?>
      <?php if ( ! empty( $row['client_siren'] ) ) : ?><div class="detail"><strong>SIREN client :</strong><span><?php echo esc_html( $row['client_siren'] ); ?></span></div><?php endif; ?>
      <?php $dl = trim( ( $row['delivery_address'] ?? '' ) . ' ' . ( $row['delivery_postal_code'] ?? '' ) . ' ' . ( $row['delivery_city'] ?? '' ) ); if ( $dl ) : ?><div class="detail"><strong>Adresse de livraison :</strong><span><?php echo esc_html( $dl ); ?></span></div><?php endif; ?>
      <?php if ( ! empty( $row['nature_operation'] ) ) : ?><div class="detail"><strong>Nature de l'opération :</strong><span><?php echo esc_html( $row['nature_operation'] ); ?></span></div><?php endif; ?>
      <?php if ( ! empty( $row['vat_option_debit'] ) ) : ?><div class="detail" style="grid-column:1/-1;"><strong>Option TVA sur les débits :</strong><span>Oui — TVA exigible à la date d'encaissement</span></div><?php endif; ?>
      <?php endif; ?>
    </div>
    <table>
      <thead><tr><th style="width:42%;">Désignation</th><th style="width:11%;">Quantité</th><th style="width:15%;">P.U (€ HT)</th><th style="width:12%;">TVA (%)</th><th style="width:20%;">Total (€ HT)</th></tr></thead>
      <tbody><tr><td class="designation"><?php echo nl2br( esc_html( $designation ) ); ?></td><td><?php echo esc_html( $quantity ); ?></td><td><?php echo esc_html( $tot_ht ); ?></td><td><?php echo esc_html( $vat_rate ); ?></td><td><?php echo esc_html( $tot_ht ); ?></td></tr></tbody>
    </table>
    <div class="totals"><div class="total-row"><div><strong>Total HT (€)</strong></div><div><?php echo esc_html( $tot_ht ); ?> €</div></div><div class="total-row"><div><strong>Total TVA (€)</strong></div><div><?php echo esc_html( $tva_total ); ?> €</div></div><div class="total-row grand"><div><strong>Total TTC (€)</strong></div><div><?php echo esc_html( $tot_ttc ); ?> €</div></div></div>
    <div class="notes"><p><?php echo nl2br( esc_html( $methods ) ); ?></p><?php /* ACDC 3.25.290 — Un IBAN de repli écrit en dur sur une facture, c'est
         un virement qui part sur l'ancien compte le jour où l'entité change.
         Sans coordonnées bancaires renseignées, la ligne disparaît. */ ?>
    <?php if ( ! empty( $row['iban'] ) || ! empty( $row['bic'] ) ) : ?><p class="bank">IBAN : <?php echo esc_html( $row['iban'] ?? '' ); ?> &nbsp;&nbsp; BIC : <?php echo esc_html( $row['bic'] ?? '' ); ?></p><?php endif; ?><p>Prix exprimés en euros HT et TTC. TVA au taux en vigueur.<?php $__rs = $this->acdc_org_identity()['raison_sociale']; echo '' !== $__rs ? ' Organisme de formation ' . esc_html( $__rs ) . '.' : ''; ?> CGV applicables.</p></div>
    <div class="signature-zone"><div></div><div class="sign-box"><?php if ( ! empty( $sig_data_uri_t2 ) ) : ?><img src="<?php echo $sig_data_uri_t2; ?>" alt="Signature" style="position:absolute; right:2mm; bottom:-8mm; height:28mm; width:auto; object-fit:contain; opacity:0.95; z-index:3;" /><?php endif; ?>Signature / cachet</div></div>
    <div class="footer"><?php $__id = $this->acdc_org_identity(); echo esc_html( \ACDC\Support\OrgIdentity::footerLine( $__id ) ); ?><br /><?php echo esc_html( \ACDC\Support\OrgIdentity::contactLine( $__id ) ); ?></div>
  </div>
</body>
</html>
    <?php
    return (string) ob_get_clean();
  }

  /**
   * ACDC 3.24.99 — Convertit une URL d'image en data-URI base64 pour embed autonome dans le HTML.
   * Fallback sur l'URL originale si le fichier est inaccessible (réseau ou disque).
   */
  /**
   * ACDC 3.25.256 — LES IMAGES DU DEVIS ÉTAIENT INCORPORÉES EN TAILLE RÉELLE.
   *
   * Le cachet fait 1600 × 1200 et le logo 1563 × 1563 ; ils sont affichés à 120
   * et 48 pixels de haut. Les incorporer en base64 dans le HTML oblige mPDF à
   * décoder les images entières en mémoire — sur un hébergement mutualisé,
   * c'est le profil exact du dépassement de mémoire, et c'est le seul document
   * du plugin qui procède ainsi. On demande donc une copie réduite, celle-là
   * même que fabrique la proposition commerciale depuis la 3.25.250, avant
   * d'encoder.
   *
   * @param string $url       Adresse de l'image.
   * @param int    $max_width Largeur maximale en pixels ; 0 = taille d'origine.
   */
  private function quote_img_to_data_uri( $url, $max_width = 0 ) {
    if ( '' === (string) $url ) return '';
    if ( $max_width > 0 && method_exists( $this, 'acdc_pdf_image_src' ) ) {
      /* La copie réduite est mise en cache sur disque : l'original n'est jamais
         modifié, et le format peut changer (un PNG opaque devient un JPEG) —
         d'où la relecture de l'extension plus bas, sur l'adresse retournée. */
      $url = $this->acdc_pdf_image_src( $url, $max_width );
    }
    $upload_dir = wp_upload_dir();
    $local_path = str_replace( trailingslashit( $upload_dir['baseurl'] ), trailingslashit( $upload_dir['basedir'] ), $url );
    $content = '';
    if ( file_exists( $local_path ) ) {
      $content = @file_get_contents( $local_path );
    }
    if ( '' === $content ) {
      $response = wp_remote_get( $url, array( 'timeout' => 5, 'sslverify' => true ) );
      if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
        $content = wp_remote_retrieve_body( $response );
      }
    }
    if ( '' === $content ) return $url;
    $ext  = strtolower( pathinfo( parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
    $mime = 'image/png';
    if ( 'jpg' === $ext || 'jpeg' === $ext ) { $mime = 'image/jpeg'; }
    elseif ( 'svg' === $ext ) { $mime = 'image/svg+xml'; }
    elseif ( 'gif' === $ext ) { $mime = 'image/gif'; }
    elseif ( 'webp' === $ext ) { $mime = 'image/webp'; }
    return 'data:' . $mime . ';base64,' . base64_encode( $content );
  }

  /** Convertit un fichier image local (chemin disque) en data URI base64, ou '' si indisponible. */
  private function quote_file_to_data_uri( $path ) {
    $path = (string) $path;
    if ( '' === $path || ! file_exists( $path ) ) { return ''; }
    $content = @file_get_contents( $path ); // phpcs:ignore
    if ( false === $content || '' === $content ) { return ''; }
    $ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
    $mime = 'image/png';
    if ( 'jpg' === $ext || 'jpeg' === $ext ) { $mime = 'image/jpeg'; }
    elseif ( 'gif' === $ext ) { $mime = 'image/gif'; }
    elseif ( 'webp' === $ext ) { $mime = 'image/webp'; }
    return 'data:' . $mime . ';base64,' . base64_encode( $content );
  }
}
