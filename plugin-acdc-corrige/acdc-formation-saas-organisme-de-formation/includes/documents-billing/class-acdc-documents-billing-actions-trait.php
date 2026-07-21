<?php
/**
 * ACDC Documents / devis / factures — ACDC_Documents_Billing_Actions_Trait.
 *
 * Extraction micro-incrémentale du sous-bloc métier :
 * documents / devis / factures.
 *
 * @since 3.15.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Documents_Billing_Actions_Trait {

  /* ---------------------------------------------------------------
   * DEVIS RÉELS — Sauvegarder
   * --------------------------------------------------------------- */
  public function handle_save_quote() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
    check_admin_referer( 'acdc_save_quote' );
    global $wpdb;
    $input    = isset( $_POST['quote'] ) && is_array( $_POST['quote'] ) ? wp_unslash( $_POST['quote'] ) : array();
    $quote_id = isset( $_POST['quote_id'] ) ? absint( $_POST['quote_id'] ) : 0;
    $scope    = in_array( $input['scope'] ?? '', array( 'action', 'ancillary' ), true ) ? $input['scope'] : 'action';

    /* Numéro auto si nouveau */
    $number = isset( $input['number'] ) ? sanitize_text_field( $input['number'] ) : '';
    if ( ! $number ) {
      $number = $this->get_quote_next_number( $scope );
    }

    /* Tarif HT */
    $tarif_ht_raw = isset( $input['tarif_ht'] ) ? str_replace( ',', '.', preg_replace( '/[^0-9,.]/', '', $input['tarif_ht'] ) ) : '0';
    $tarif_ht     = round( (float) $tarif_ht_raw, 2 );

    /* Dates */
    $emission_date    = ! empty( $input['emission_date'] ) ? $this->datetime_from_local( $input['emission_date'] ) : current_time( 'mysql' );
    $expiration_date  = ! empty( $input['expiration_date'] ) ? $this->datetime_from_local( $input['expiration_date'] ) : null;
    $start_date       = ! empty( $input['start_date'] ) ? $this->datetime_from_local( $input['start_date'] ) : null;
    $end_date         = ! empty( $input['end_date'] ) ? $this->datetime_from_local( $input['end_date'] ) : null;

    $data = array(
      'id'                      => $quote_id,
      'scope'                   => $scope,
      'number'                  => $number,
      'commanditaire_type'      => sanitize_text_field( $input['commanditaire_type'] ?? 'Particulier' ),
      'source_prospect_id'      => ! empty( $input['source_prospect_id'] ) ? absint( $input['source_prospect_id'] ) : null,
      'proposal_id'             => ! empty( $input['proposal_id'] ) ? absint( $input['proposal_id'] ) : null,
      'company_id'              => ! empty( $input['company_id'] ) ? absint( $input['company_id'] ) : null,
      'formation_id'            => ! empty( $input['formation_id'] ) ? absint( $input['formation_id'] ) : null,
      'formation_title'         => sanitize_text_field( $input['formation_title'] ?? '' ),
      'apprenant_name'          => sanitize_text_field( $input['apprenant_name'] ?? '' ),
      'apprenant_email'         => sanitize_email( $input['apprenant_email'] ?? '' ),
      'client_company'          => sanitize_text_field( $input['client_company'] ?? '' ),
      'client_address'          => sanitize_text_field( $input['client_address'] ?? '' ),
      'client_address_complement' => sanitize_text_field( $input['client_address_complement'] ?? '' ),
      'client_postal_code'      => sanitize_text_field( $input['client_postal_code'] ?? '' ),
      'client_city'             => sanitize_text_field( $input['client_city'] ?? '' ),
      'format'                  => sanitize_text_field( $input['format'] ?? 'Présentiel' ),
      'formation_address'       => sanitize_text_field( $input['formation_address'] ?? '' ),
      'formation_postal_code'   => sanitize_text_field( $input['formation_postal_code'] ?? '' ),
      'formation_city'          => sanitize_text_field( $input['formation_city'] ?? '' ),
      'start_date'              => $start_date,
      'end_date'                => $end_date,
      'trained_headcount'       => sanitize_text_field( $input['trained_headcount'] ?? '' ),
      'duration_label'          => sanitize_text_field( $input['duration_label'] ?? '' ),
      'objectives'              => sanitize_textarea_field( $input['objectives'] ?? '' ),
      'designation'             => sanitize_textarea_field( $input['designation'] ?? '' ),
      'quantity'                => sanitize_text_field( $input['quantity'] ?? '1,00' ),
      'tarif_ht'                => $tarif_ht,
      'vat_rate'                => (float) str_replace( ',', '.', preg_replace( '/[^0-9,.]/', '', $input['vat_rate'] ?? '20' ) ),
      'transport_fees_enabled'  => ! empty( $input['transport_fees_enabled'] ) ? 1 : 0,
      'transport_fees_ht'       => round( (float) str_replace( ',', '.', preg_replace( '/[^0-9,.]/', '', $input['transport_fees_ht'] ?? '0' ) ), 2 ),
      'meal_fees_enabled'       => ! empty( $input['meal_fees_enabled'] ) ? 1 : 0,
      'meal_fees_ht'            => round( (float) str_replace( ',', '.', preg_replace( '/[^0-9,.]/', '', $input['meal_fees_ht'] ?? '0' ) ), 2 ),
      'extra_lines_json'        => ! empty( $input['extra_lines'] ) && is_array( $input['extra_lines'] ) ? wp_json_encode( $input['extra_lines'] ) : null,
      'payment_methods'         => sanitize_textarea_field( $input['payment_methods'] ?? '' ),
      'iban'                    => sanitize_text_field( $input['iban'] ?? '' ),
      'bic'                     => sanitize_text_field( $input['bic'] ?? '' ),
      'validity_days'           => absint( $input['validity_days'] ?? 30 ),
      'emission_date'           => $emission_date,
      'expiration_date'         => $expiration_date,
      'status'                  => in_array( $input['status'] ?? '', array_keys( $this->get_quote_status_labels() ), true ) ? $input['status'] : 'brouillon',
    );

    $new_id = $this->save_quote( $data );

    /* Régénérer le HTML après sauvegarde */
    if ( $new_id ) {
      $saved_quote = $this->get_quote( $new_id );
      if ( $saved_quote ) {
        $html_url = $this->generate_quote_html( $saved_quote );
        if ( $html_url ) {
          $wpdb->update( $this->quote_table, array( 'html_url' => $html_url ), array( 'id' => $new_id ) );
        }
      }
    }

    $redirect = $this->portal_page_url( array( 'tab' => 'quotes', 'scope' => $scope, 'quote_action' => 'view', 'quote_id' => $new_id ) );
    wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Devis enregistré.' ), 'notice_type' => 'success' ), $redirect ) );
    exit;
  }

  /* ---------------------------------------------------------------
   * DEVIS RÉELS — Envoyer par email
   * --------------------------------------------------------------- */
  public function handle_send_quote_email() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
    $quote_id = isset( $_POST['quote_id'] ) ? absint( $_POST['quote_id'] ) : 0;
    if ( ! $quote_id || ! check_admin_referer( 'acdc_send_quote_email_' . $quote_id ) ) { wp_die( 'Action invalide.' ); }
    global $wpdb;
    $quote = $this->get_quote( $quote_id );
    if ( ! $quote ) { $this->redirect_to_portal( 'quotes', 'Devis introuvable.', 'error' ); }

    $email = sanitize_email( (string) $quote->apprenant_email );
    if ( ! $email || ! is_email( $email ) ) {
      $redirect = $this->portal_page_url( array( 'tab' => 'quotes', 'scope' => $quote->scope, 'quote_action' => 'view', 'quote_id' => $quote_id ) );
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Envoi impossible : aucun email renseigné sur ce devis.' ), 'notice_type' => 'error' ), $redirect ) );
      exit;
    }

    /* Régénérer le HTML pour avoir l'URL fraîche */
    $html_url = $this->generate_quote_html( $quote );
    if ( $html_url ) {
      $wpdb->update( $this->quote_table, array( 'html_url' => $html_url ), array( 'id' => $quote_id ) );
    }

    $row         = $this->build_quote_row_from_record( $quote );
    $branding    = $this->get_branding_options();
    $org_name    = $branding['company_name'] ?? 'ACDC Formation';
    $formation   = $row['formation'] ?: 'Votre formation';
    $recipient   = $row['apprenant'] ?: $row['client_company'];

    $cta = '<p style="text-align:center;margin:28px 0;">'
      . '<a href="' . esc_url( $html_url ) . '" style="display:inline-block;background:#d6a353;color:#0f2c52;font-weight:800;font-size:16px;text-decoration:none;padding:14px 32px;border-radius:10px;">'
      . '&#128196;&nbsp; Consulter votre devis'
      . '</a></p>'
      . '<p style="font-size:13px;color:#6b7280;text-align:center;">Ou copiez ce lien : <a href="' . esc_url( $html_url ) . '" style="color:#5579bf;">' . esc_html( $html_url ) . '</a></p>';

    $subject = 'Votre devis de formation — ' . $formation . ' — ' . $org_name;

    $sent = $this->acdc_send_transactional_email( $email, $subject, array(
      'title'    => 'Votre devis de formation',
      'intro'    => 'Bonjour ' . esc_html( $recipient ) . ',<br><br>Veuillez trouver ci-dessous votre devis pour la formation <strong>' . esc_html( $formation ) . '</strong>.',
      'cta_html' => $cta,
      'footer'   => 'Ce devis est valable ' . (int) $quote->validity_days . ' jours. Pour toute question, contactez-nous à <a href="mailto:' . esc_attr( $branding['email'] ?? '' ) . '">' . esc_html( $branding['email'] ?? '' ) . '</a>.',
    ), array( 'from_name' => $org_name, 'from_email' => $branding['email'] ?? '' ) );

    if ( $sent ) {
      $wpdb->update( $this->quote_table, array(
        'status'    => 'envoye',
        'sent_at'   => current_time( 'mysql' ),
        'updated_at' => current_time( 'mysql' ),
      ), array( 'id' => $quote_id ) );
    }

    $msg = $sent ? 'Devis envoyé par email.' : 'Envoi impossible — vérifiez la configuration email.';
    $type = $sent ? 'success' : 'error';
    $redirect = $this->portal_page_url( array( 'tab' => 'quotes', 'scope' => $quote->scope, 'quote_action' => 'view', 'quote_id' => $quote_id ) );
    wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( $msg ), 'notice_type' => $type ), $redirect ) );
    exit;
  }

  /* ---------------------------------------------------------------
   * DEVIS RÉELS — Supprimer (avec verrou convention)
   * --------------------------------------------------------------- */
  public function handle_delete_quote() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
    $quote_id = isset( $_GET['quote_id'] ) ? absint( $_GET['quote_id'] ) : 0;
    if ( ! $quote_id || ! check_admin_referer( 'acdc_delete_quote_' . $quote_id ) ) { wp_die( 'Action invalide.' ); }
    global $wpdb;
    $quote = $this->get_quote( $quote_id );
    if ( ! $quote ) { $this->redirect_to_portal( 'quotes', 'Devis introuvable.', 'error' ); }

    /* Verrou : devis lié à un prospect qui a une convention */
    if ( ! empty( $quote->source_prospect_id ) ) {
      $convention_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$this->registration_contract_table} WHERE source_prospect_id = %d",
        (int) $quote->source_prospect_id
      ) );
      if ( $convention_count ) {
        $this->redirect_to_portal( 'quotes', 'Suppression impossible : ce devis est lié à une convention ou un contrat de formation.', 'error' );
      }
    }

    /* Supprimer le fichier HTML */
    if ( ! empty( $quote->html_url ) ) {
      $upload   = wp_upload_dir();
      $url_base = trailingslashit( $upload['baseurl'] ) . 'acdc-quotes/';
      $dir      = trailingslashit( $upload['basedir'] ) . 'acdc-quotes/';
      $old_path = str_replace( $url_base, $dir, (string) $quote->html_url );
      if ( 0 === strpos( $old_path, $dir ) && file_exists( $old_path ) ) {
        @unlink( $old_path );
      }
    }

    $scope = (string) $quote->scope;
    $wpdb->delete( $this->quote_table, array( 'id' => $quote_id ) );
    $this->redirect_to_portal( 'quotes', 'Devis supprimé.', 'success', array( 'scope' => $scope ) );
  }

  public function handle_set_quote_status() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
    $quote_id  = isset( $_GET['quote_id'] ) ? absint( $_GET['quote_id'] ) : 0;
    $new_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
    if ( ! $quote_id || ! $new_status || ! check_admin_referer( 'acdc_set_quote_status_' . $quote_id . '_' . $new_status ) ) {
      wp_die( 'Action invalide.' );
    }
    if ( ! array_key_exists( $new_status, $this->get_quote_status_labels() ) ) {
      wp_die( 'Statut invalide.' );
    }
    global $wpdb;
    $quote = $this->get_quote( $quote_id );
    if ( ! $quote ) { $this->redirect_to_portal( 'quotes', 'Devis introuvable.', 'error' ); }
    $scope = (string) $quote->scope;
    $wpdb->update( $this->quote_table, array( 'status' => $new_status ), array( 'id' => $quote_id ) );
    $this->redirect_to_portal( 'quotes', 'Statut mis à jour.', 'success', array( 'scope' => $scope ) );
  }

  public function handle_download_quote_document() {
    $this->require_manage_options();

    $quote_id = isset( $_GET['quote_id'] ) ? absint( $_GET['quote_id'] ) : 0;
    $scope = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : 'action';
    if ( ! in_array( $scope, array( 'action', 'ancillary' ), true ) ) {
      $scope = 'action';
    }
    $this->verify_nonce_or_die( 'acdc_download_quote_document_' . $quote_id );
    $quote = $quote_id ? $this->get_quote( $quote_id ) : null;
    if ( ! $quote ) {
      wp_die( esc_html( 'Devis introuvable.' ) );
    }
    $row  = $this->build_quote_row_from_record( $quote );
    $html = $this->get_quote_document_html( $row );
    $this->log_action_event( 'download', 'quote_document', $quote_id );
    $this->send_html_download_response( 'devis-' . sanitize_file_name( $row['number'] ) . '.html', $html );
  }

  public function handle_download_invoice_document() {
    $this->require_manage_options();
    $invoice_id = isset( $_GET['invoice_id'] ) ? absint( $_GET['invoice_id'] ) : 0;
    $scope = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : 'action';
    if ( ! in_array( $scope, array( 'action', 'ancillary' ), true ) ) { $scope = 'action'; }
    $this->verify_nonce_or_die( 'acdc_download_invoice_document_' . $invoice_id );
    /* Mode réel : lecture depuis la vraie BDD. */
    if ( ! $this->is_documents_billing_demo_enabled() && $invoice_id ) {
      $inv = $this->get_invoice( $invoice_id );
      if ( $inv ) {
        $row  = $this->build_invoice_row_from_record( $inv );
        $html = $this->get_invoice_document_html( $row, 'invoice' );
        $this->log_action_event( 'download', 'invoice_document', $invoice_id );
        $this->send_html_download_response( 'facture-' . sanitize_file_name( $row['number'] ) . '.html', $html );
        return;
      }
    }
    /* Mode démo : fallback sur les données fictives. */
    $row = $this->get_mock_invoice_record( $invoice_id, $scope );
    $html = $this->get_invoice_document_html( $row, 'invoice' );
    $this->log_action_event( 'download', 'invoice_document', $invoice_id, 'success', array( 'scope' => $scope ) );
    $this->send_html_download_response( 'facture-' . sanitize_file_name( $row['number'] ) . '.html', $html );
  }

  public function handle_download_credit_note_document() {
    $this->require_manage_options();
    $invoice_id = isset( $_GET['invoice_id'] ) ? absint( $_GET['invoice_id'] ) : 1;
    $scope = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : 'action';
    if ( ! in_array( $scope, array( 'action', 'ancillary' ), true ) ) { $scope = 'action'; }
    $this->verify_nonce_or_die( 'acdc_download_credit_note_document_' . $invoice_id );
    /* Mode réel : lecture depuis la vraie BDD. */
    if ( ! $this->is_documents_billing_demo_enabled() && $invoice_id ) {
      $inv = $this->get_invoice( $invoice_id );
      if ( $inv ) {
        $row = $this->build_invoice_row_from_record( $inv );
        $cn_number = ! empty( $inv->credit_note_number ) ? (string) $inv->credit_note_number : 'AV-' . wp_date( 'Y' ) . '-' . (int) $inv->id;
        $row['credit_note'] = array(
          'id'              => $row['id'],
          'number'          => $cn_number,
          'emission_date'   => $row['credit_note_date'] ?? '',
          'due_date'        => '',
          'designation'     => $row['designation'] ?? '',
          'quantity'        => $row['quantity_number'] ?? '1,00',
          'tarif_ht_value'  => $row['tarif_ht_value'] ?? '0',
          'vat_rate'        => $row['vat_rate'] ?? '20,00',
          'tarif_ttc_value' => $row['tarif_ttc_value'] ?? '0',
          'payment_methods' => $row['payment_methods'] ?? '',
        );
        $html = $this->get_invoice_document_html( $row, 'credit_note' );
        $this->log_action_event( 'download', 'credit_note_document', $invoice_id );
        $this->send_html_download_response( 'avoir-' . sanitize_file_name( $row['credit_note']['number'] ) . '.html', $html );
        return;
      }
    }
    /* Mode démo : fallback sur les données fictives. */
    $row = $this->get_mock_invoice_record( $invoice_id, $scope );
    $html = $this->get_invoice_document_html( $row, 'credit_note' );
    $this->log_action_event( 'download', 'credit_note_document', $invoice_id, 'success', array( 'scope' => $scope ) );
    $this->send_html_download_response( 'avoir-' . sanitize_file_name( $row['credit_note']['number'] ) . '.html', $html );
  }

  /* ---------------------------------------------------------------
   * ACDC 3.24.20 — Convertir un devis en facture
   * --------------------------------------------------------------- */
  public function handle_convert_quote_to_invoice() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
    $quote_id = isset( $_POST['quote_id'] ) ? absint( $_POST['quote_id'] ) : 0;
    if ( ! $quote_id || ! check_admin_referer( 'acdc_convert_quote_to_invoice_' . $quote_id ) ) { wp_die( 'Action invalide.' ); }
    if ( $this->is_documents_billing_demo_enabled() ) {
      $this->redirect_to_portal( 'invoices_credit_notes', 'Activez la facturation réelle dans les Réglages avant de créer des factures.', 'error' );
    }
    $invoice_id = $this->convert_quote_to_invoice( $quote_id );
    if ( ! $invoice_id ) {
      $quote = $this->get_quote( $quote_id );
      $scope = $quote ? (string) $quote->scope : 'action';
      $redirect = $this->portal_page_url( array( 'tab' => 'quotes', 'scope' => $scope, 'quote_action' => 'view', 'quote_id' => $quote_id ) );
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Impossible de créer la facture.' ), 'notice_type' => 'error' ), $redirect ) );
      exit;
    }
    $inv   = $this->get_invoice( $invoice_id );
    $scope = $inv ? (string) $inv->scope : 'action';
    $redirect = $this->portal_page_url( array( 'tab' => 'invoices_credit_notes', 'scope' => $scope, 'invoice_action' => 'view', 'invoice_id' => $invoice_id ) );
    wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Facture créée depuis le devis.' ), 'notice_type' => 'success' ), $redirect ) );
    exit;
  }

  /* ---------------------------------------------------------------
   * ACDC 3.24.20 — Enregistrer une facture
   * --------------------------------------------------------------- */
  public function handle_save_invoice() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
    check_admin_referer( 'acdc_save_invoice' );
    if ( $this->is_documents_billing_demo_enabled() ) {
      $this->redirect_to_portal( 'invoices_credit_notes', 'Activez la facturation réelle dans les Réglages avant de modifier des factures.', 'error' );
    }
    global $wpdb;
    $input      = isset( $_POST['invoice'] ) && is_array( $_POST['invoice'] ) ? wp_unslash( $_POST['invoice'] ) : array();
    $invoice_id = isset( $_POST['invoice_id'] ) ? absint( $_POST['invoice_id'] ) : 0;
    $scope      = in_array( $input['scope'] ?? '', array( 'action', 'ancillary' ), true ) ? $input['scope'] : 'action';
    $tarif_raw  = isset( $input['tarif_ht'] ) ? str_replace( ',', '.', preg_replace( '/[^0-9,.]/', '', $input['tarif_ht'] ) ) : '0';
    $tarif_ht   = round( (float) $tarif_raw, 2 );
    $emission   = ! empty( $input['emission_date'] ) ? $this->datetime_from_local( $input['emission_date'] ) : null;
    $due        = ! empty( $input['due_date'] ) ? $this->datetime_from_local( $input['due_date'] ) : null;
    $start      = ! empty( $input['start_date'] ) ? $this->datetime_from_local( $input['start_date'] ) : null;
    $end        = ! empty( $input['end_date'] ) ? $this->datetime_from_local( $input['end_date'] ) : null;
    $data = array(
      'id'                      => $invoice_id,
      'scope'                   => $scope,
      'registration_id'         => ! empty( $input['registration_id'] ) ? absint( $input['registration_id'] ) : null,
      'commanditaire_type'      => sanitize_text_field( $input['commanditaire_type'] ?? 'Particulier' ),
      'formation_title'         => sanitize_text_field( $input['formation_title'] ?? '' ),
      'apprenant_name'          => sanitize_text_field( $input['apprenant_name'] ?? '' ),
      'apprenant_email'         => sanitize_email( $input['apprenant_email'] ?? '' ),
      'client_company'          => sanitize_text_field( $input['client_company'] ?? '' ),
      'client_siren'            => sanitize_text_field( $input['client_siren'] ?? '' ),
      'client_address'          => sanitize_text_field( $input['client_address'] ?? '' ),
      'client_address_complement' => sanitize_text_field( $input['client_address_complement'] ?? '' ),
      'client_postal_code'      => sanitize_text_field( $input['client_postal_code'] ?? '' ),
      'client_city'             => sanitize_text_field( $input['client_city'] ?? '' ),
      'delivery_address'        => sanitize_text_field( $input['delivery_address'] ?? '' ),
      'delivery_postal_code'    => sanitize_text_field( $input['delivery_postal_code'] ?? '' ),
      'delivery_city'           => sanitize_text_field( $input['delivery_city'] ?? '' ),
      'nature_operation'        => sanitize_text_field( $input['nature_operation'] ?? 'Prestation de formation professionnelle continue' ),
      'vat_option_debit'        => ! empty( $input['vat_option_debit'] ) ? 1 : 0,
      'format'                  => sanitize_text_field( $input['format'] ?? 'Présentiel' ),
      'formation_address'       => sanitize_text_field( $input['formation_address'] ?? '' ),
      'formation_postal_code'   => sanitize_text_field( $input['formation_postal_code'] ?? '' ),
      'formation_city'          => sanitize_text_field( $input['formation_city'] ?? '' ),
      'start_date'              => $start,
      'end_date'                => $end,
      'trained_headcount'       => sanitize_text_field( $input['trained_headcount'] ?? '' ),
      'duration_label'          => sanitize_text_field( $input['duration_label'] ?? '' ),
      'objectives'              => sanitize_textarea_field( $input['objectives'] ?? '' ),
      'designation'             => sanitize_textarea_field( $input['designation'] ?? '' ),
      'quantity'                => sanitize_text_field( $input['quantity'] ?? '1,00' ),
      'tarif_ht'                => $tarif_ht,
      'vat_rate'                => (float) str_replace( ',', '.', preg_replace( '/[^0-9,.]/', '', $input['vat_rate'] ?? '20' ) ),
      'payment_methods'         => sanitize_textarea_field( $input['payment_methods'] ?? '' ),
      'iban'                    => sanitize_text_field( $input['iban'] ?? '' ),
      'bic'                     => sanitize_text_field( $input['bic'] ?? '' ),
      'emission_date'           => $emission,
      'due_date'                => $due,
      'financeur'               => sanitize_text_field( $input['financeur'] ?? '' ),
      'status'                  => in_array( $input['status'] ?? '', array_keys( $this->get_invoice_status_labels() ), true ) ? $input['status'] : 'emise',
    );
    $new_id = $this->save_invoice( $data );
    $redirect = $this->portal_page_url( array( 'tab' => 'invoices_credit_notes', 'scope' => $scope, 'invoice_action' => 'view', 'invoice_id' => $new_id ) );
    wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Facture enregistrée.' ), 'notice_type' => 'success' ), $redirect ) );
    exit;
  }

  /* ---------------------------------------------------------------
   * ACDC 3.24.20 — Supprimer une facture
   * --------------------------------------------------------------- */
  public function handle_delete_invoice() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
    $invoice_id = isset( $_GET['invoice_id'] ) ? absint( $_GET['invoice_id'] ) : 0;
    if ( ! $invoice_id ) { $this->redirect_to_portal( 'invoices_credit_notes', 'Facture introuvable.', 'error' ); }
    $this->verify_nonce_or_die( 'acdc_delete_invoice_' . $invoice_id );
    global $wpdb;
    $inv = $this->get_invoice( $invoice_id );
    if ( ! $inv ) { $this->redirect_to_portal( 'invoices_credit_notes', 'Facture introuvable.', 'error' ); }
    $scope = (string) $inv->scope;
    /* B20 — Une facture émise ne peut être supprimée ; seul un brouillon peut l'être. */
    $issued_statuses = array( 'emise', 'envoyee', 'envoyée', 'payee', 'payée' );
    if ( in_array( (string) $inv->status, $issued_statuses, true ) ) {
      $this->redirect_to_portal( 'invoices_credit_notes', 'Une facture émise ne peut être supprimée ; créez un avoir.', 'error', array( 'scope' => $scope ) );
    }
    $wpdb->delete( $this->invoice_table, array( 'id' => $invoice_id ) );
    $this->redirect_to_portal( 'invoices_credit_notes', 'Facture supprimée.', 'success', array( 'scope' => $scope ) );
  }

  public function handle_save_document() {
    $this->require_admin_manager_nonce( 'acdc_save_document' );

    if ( empty( $_FILES['document_file']['name'] ) ) {
      $this->redirect_to_portal( 'documents', 'Aucun fichier reçu.', 'error', array( 'action' => 'new' ) );
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';

    $validated = $this->validate_uploaded_file_array( $_FILES['document_file'], 'document_file' );
    if ( is_wp_error( $validated ) ) {
      $this->redirect_to_portal( 'documents', $validated->get_error_message(), 'error', array( 'action' => 'new' ) );
    }

    $overrides = array(
      'test_form' => false,
      'mimes'     => $this->get_allowed_mimes_for_upload_key( 'document_file' ),
    );
    $upload  = wp_handle_upload( $validated, $overrides );

    if ( isset( $upload['error'] ) ) {
      $this->redirect_to_portal( 'documents', 'Téléversement impossible : ' . $upload['error'], 'error', array( 'action' => 'new' ) );
    }

    global $wpdb;
    $result = $wpdb->insert(
      $this->document_table,
      array(
        'company_id'   => isset( $_POST['company_id'] ) && absint( wp_unslash( $_POST['company_id'] ) ) ? absint( wp_unslash( $_POST['company_id'] ) ) : null,
        'contact_id'   => isset( $_POST['contact_id'] ) && absint( wp_unslash( $_POST['contact_id'] ) ) ? absint( wp_unslash( $_POST['contact_id'] ) ) : null,
        'title'        => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : basename( $upload['file'] ),
        'document_type' => isset( $_POST['document_type'] ) ? sanitize_text_field( wp_unslash( $_POST['document_type'] ) ) : 'Autre',
        'file_url'     => esc_url_raw( $upload['url'] ),
        'file_path'    => sanitize_text_field( $upload['file'] ),
        'mime_type'    => isset( $upload['type'] ) ? sanitize_text_field( $upload['type'] ) : '',
        'uploaded_by'  => get_current_user_id(),
        'created_at'   => $this->now_mysql(),
      )
    );

    if ( false === $result ) {
      $this->redirect_to_portal( 'documents', 'Erreur lors de l\'enregistrement du document. Veuillez réessayer.', 'error', array( 'action' => 'new' ) );
    }

    $this->redirect_to_portal( 'documents', 'Document téléversé.', 'success' );
  }

  public function handle_delete_document() {
    $this->require_admin_manager_access();

    $document_id = isset( $_GET['document_id'] ) ? absint( wp_unslash( $_GET['document_id'] ) ) : 0;
    if ( ! $document_id ) {
      $this->redirect_to_portal( 'documents', 'Document introuvable.', 'error' );
    }

    $this->verify_nonce_or_die( 'acdc_delete_document_' . $document_id );

    global $wpdb;
    $document = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->document_table} WHERE id = %d", $document_id ) );
    if ( ! $document ) {
      $this->redirect_to_portal( 'documents', 'Document introuvable.', 'error' );
    }

    /* Bloquer la suppression des documents Qualiopi liés à un commanditaire avec convention */
    if ( 'Qualiopi' === (string) $document->document_type && ! empty( $document->company_id ) ) {
      $convention_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$this->registration_contract_table} WHERE company_id = %d",
        (int) $document->company_id
      ) );
      if ( $convention_count ) {
        $this->redirect_to_portal( 'documents', 'Suppression impossible : ce document Qualiopi est lié à une convention ou un contrat de formation (preuve obligatoire).', 'error' );
      }
    }

    if ( ! empty( $document->file_path ) && file_exists( $document->file_path ) ) {
      wp_delete_file( $document->file_path );
    }

    $wpdb->delete( $this->document_table, array( 'id' => $document_id ) );
    $this->redirect_to_portal( 'documents', 'Document supprimé.', 'success' );
  }

  /**
   * ACDC 3.24.98 — Envoyer un devis en signature électronique OTP.
   * Action : admin_post_acdc_send_quote_for_signature (GET + nonce)
   */
  public function handle_send_quote_for_signature() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $quote_id = isset( $_GET['quote_id'] ) ? absint( wp_unslash( $_GET['quote_id'] ) ) : 0;
    if ( ! $quote_id || ! check_admin_referer( 'acdc_send_quote_for_signature_' . $quote_id ) ) {
      wp_die( esc_html( 'Action invalide.' ) );
    }
    global $wpdb;
    $quote = $this->get_quote( $quote_id );
    if ( ! $quote ) {
      $this->redirect_to_portal( 'quotes', 'Devis introuvable.', 'error' );
      return;
    }
    // Vérifier qu'une demande n'est pas déjà active
    if ( ! empty( $quote->signature_request_id ) && (int) $quote->signature_request_id > 0 ) {
      $this->redirect_to_portal( 'quotes', 'Une demande de signature existe d\xc3\xa9j\xc3\xa0 pour ce devis.', 'error' );
      return;
    }
    // Vérifier l'adresse e-mail du destinataire
    $signer_email = sanitize_email( (string) $quote->apprenant_email );
    if ( '' === $signer_email || ! is_email( $signer_email ) ) {
      $this->redirect_to_portal( 'quotes', 'Le devis ne comporte pas d\'adresse e-mail valide pour le destinataire.', 'error' );
      return;
    }
    if ( ! class_exists( 'ACDC_Sig_Core' ) || ! class_exists( 'ACDC_Sig_Email' ) ) {
      $this->redirect_to_portal( 'quotes', 'Le module de signature \xc3\xa9lectronique n\'est pas disponible.', 'error' );
      return;
    }
    // Générer ou récupérer le HTML du devis comme doc signable
    $doc_url = ! empty( $quote->html_url ) ? esc_url_raw( (string) $quote->html_url ) : '';
    if ( '' === $doc_url ) {
      $doc_url = $this->generate_quote_html( $quote );
      if ( $doc_url ) {
        $wpdb->update( $this->quote_table, array( 'html_url' => $doc_url ), array( 'id' => $quote_id ) );
      }
    }
    if ( '' === $doc_url ) {
      $this->redirect_to_portal( 'quotes', 'Impossible de g\xc3\xa9n\xc3\xa9rer le document du devis avant signature.', 'error' );
      return;
    }
    $upload_dir = wp_upload_dir();
    $doc_path   = str_replace( trailingslashit( $upload_dir['baseurl'] ), trailingslashit( $upload_dir['basedir'] ), $doc_url );
    $signer_name = sanitize_text_field( (string) $quote->apprenant_name );
    if ( '' === $signer_name ) {
      $signer_name = sanitize_text_field( (string) $quote->client_company );
    }
    $sig_core   = new ACDC_Sig_Core();
    $sig_core->init_tables();
    $request_id = $sig_core->create_signature_request( array(
      'signer_name'  => $signer_name,
      'signer_email' => $signer_email,
      'signer_role'  => 'Commanditaire — devis de formation',
      'doc_type'     => 'devis',
      'sig_level'    => ACDC_Sig_Core::LEVEL_RENFORCE,
      'doc_url'      => $doc_url,
      'doc_path'     => file_exists( $doc_path ) ? $doc_path : '',
      'notes'        => wp_json_encode( array(
        'entity_type'           => 'quote',
        'quote_id'              => (int) $quote_id,
        'signed_delivery_email' => sanitize_email( (string) get_option( 'admin_email' ) ),
      ) ),
    ) );
    if ( ! $request_id ) {
      $this->redirect_to_portal( 'quotes', 'La demande de signature n\'a pas pu \xc3\xaatre cr\xc3\xa9\xc3\xa9e.', 'error' );
      return;
    }
    $sig_email = new ACDC_Sig_Email( $sig_core );
    $sig_email->send_signature_email( $request_id );
    $wpdb->update( $this->quote_table, array(
      'signature_request_id' => (int) $request_id,
      'signature_status'     => 'envoyée',
      'status'               => 'a_signer',
    ), array( 'id' => $quote_id ), array( '%d', '%s', '%s' ), array( '%d' ) );
    $this->redirect_to_portal( 'quotes', 'Demande de signature envoy\xc3\xa9e \xc3\xa0 ' . $signer_email . '.', 'success' );
  }

  /**
   * ACDC 3.24.98 — Callback déclenché par do_action('acdc_sig_request_signed', $request_id, $request).
   * Met à jour le statut du devis et notifie l'organisme.
   */
  public function handle_quote_signed( $request_id, $request ) {
    $request_id = absint( $request_id );
    if ( ! $request_id ) {
      return;
    }
    global $wpdb;
    $notes = json_decode( isset( $request->notes ) ? (string) $request->notes : '', true );
    if ( ! is_array( $notes ) || 'quote' !== ( isset( $notes['entity_type'] ) ? $notes['entity_type'] : '' ) ) {
      return;
    }
    $quote_id = ! empty( $notes['quote_id'] ) ? absint( $notes['quote_id'] ) : 0;
    if ( ! $quote_id ) {
      return;
    }
    $quote = $this->get_quote( $quote_id );
    if ( ! $quote ) {
      return;
    }
    // Récupérer l'URL du document signé (le HTML du devis, inchangé — la preuve est dans sig_requests)
    $signed_url = ! empty( $quote->html_url ) ? esc_url_raw( (string) $quote->html_url ) : '';
    $wpdb->update( $this->quote_table, array(
      'signature_status'     => 'signée',
      'status'               => 'signe',
      'signed_document_url'  => $signed_url,
    ), array( 'id' => $quote_id ), array( '%s', '%s', '%s' ), array( '%d' ) );
    // Notifier l'organisme
    $signed_at   = current_time( 'mysql' );
    $profile_s   = $this->get_company_profile_options();
    $from_name_s = ! empty( $profile_s['enterprise_contact_name'] )  ? sanitize_text_field( (string) $profile_s['enterprise_contact_name'] )  : get_bloginfo( 'name' );
    $from_email_s= ! empty( $profile_s['enterprise_contact_email'] ) ? sanitize_email( (string) $profile_s['enterprise_contact_email'] )       : sanitize_email( (string) get_option( 'admin_email' ) );
    $admin_email = sanitize_email( (string) get_option( 'admin_email' ) );
    $signer_name = sanitize_text_field( (string) $quote->apprenant_name );
    $quote_num   = sanitize_text_field( (string) $quote->number );
    if ( '' !== $admin_email && is_email( $admin_email ) ) {
      $headers_s = array( 'Content-Type: text/html; charset=UTF-8', 'From: ' . sanitize_text_field( $from_name_s ) . ' <' . $from_email_s . '>' );
      $subj_admin = '✅ Devis signé — ' . $quote_num . ' — ' . $signer_name;
      $body_admin = '<div style="font-family:Arial,sans-serif;color:#24324a;max-width:600px;margin:0 auto;">'
                  . '<h2 style="color:#1f335d;">✅ Devis signé électroniquement</h2>'
                  . '<p>Le devis <strong>' . esc_html( $quote_num ) . '</strong> de <strong>' . esc_html( $signer_name ) . '</strong> a été signé électroniquement.</p>'
                  . '<p>La preuve de signature est disponible dans le module <strong>Archives des signatures</strong>.</p>'
                  . '</div>';
      wp_mail( $admin_email, $subj_admin, $body_admin, $headers_s );
    }
  }
}

