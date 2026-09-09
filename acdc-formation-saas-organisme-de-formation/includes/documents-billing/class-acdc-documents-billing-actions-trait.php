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

    /* Désignation : si le champ est vide ou reste le gabarit « à définir », on le recompose
       à partir des dates saisies pour ne pas figer « Dates … : à définir » sur le document. */
    $designation_in = sanitize_textarea_field( $input['designation'] ?? '' );
    if ( '' === trim( $designation_in ) || false !== mb_stripos( $designation_in, 'à définir' ) ) {
      $d_start = $start_date ? mysql2date( 'd/m/Y', $start_date ) : '';
      $d_end   = $end_date ? mysql2date( 'd/m/Y', $end_date ) : '';
      if ( $d_start && $d_end && $d_end !== $d_start ) {
        $designation_in = "Dates de l'action de formation : du " . $d_start . ' au ' . $d_end;
      } elseif ( $d_start ) {
        $designation_in = "Dates de l'action de formation : le " . $d_start;
      }
    }

    /* ACDC 3.25.259 — LE DEVIS PERDAIT SON PROSPECT.
       Le champ n'était rempli que si l'URL portait ?prospect_id=, ce qui n'est
       pas le cas quand on arrive depuis une proposition commerciale. L'écran
       CONNAISSAIT pourtant le prospect — il venait de s'en servir pour
       préremplir l'adresse — puis il le jetait.
       Sans ce lien, aucun envoi de devis ne fait avancer le prospect à
       « Devis envoyé », et surtout aucune signature ne le fait passer à
       « Converti » : le code qui le fait lit exactement cette colonne.
       On répare la porte, pas seulement le formulaire : le rattachement est
       redéduit ici de la proposition d'origine, puis de son recueil. */
    $posted_prospect_id = ! empty( $input['source_prospect_id'] ) ? absint( $input['source_prospect_id'] ) : 0;
    $posted_proposal_id = ! empty( $input['proposal_id'] ) ? absint( $input['proposal_id'] ) : 0;
    if ( ! $posted_prospect_id && $quote_id ) {
      /* Modification d'un devis existant : un formulaire qui ne renvoie pas le
         rattachement ne demande pas de le supprimer. */
      $existing_quote     = $this->get_quote( $quote_id );
      $posted_prospect_id = ( $existing_quote && ! empty( $existing_quote->source_prospect_id ) ) ? (int) $existing_quote->source_prospect_id : 0;
      if ( ! $posted_proposal_id && $existing_quote && ! empty( $existing_quote->proposal_id ) ) {
        $posted_proposal_id = (int) $existing_quote->proposal_id;
      }
    }
    if ( ! $posted_prospect_id && $posted_proposal_id ) {
      $posted_prospect_id = $this->acdc_prospect_id_from_proposal( $posted_proposal_id );
    }

    $data = array(
      'id'                      => $quote_id,
      'scope'                   => $scope,
      'number'                  => $number,
      'commanditaire_type'      => sanitize_text_field( $input['commanditaire_type'] ?? 'Particulier' ),
      'source_prospect_id'      => $posted_prospect_id ?: null,
      'proposal_id'             => $posted_proposal_id ?: null,
      'company_id'              => ! empty( $input['company_id'] ) ? absint( $input['company_id'] ) : null,
      'formation_id'            => ! empty( $input['formation_id'] ) ? absint( $input['formation_id'] ) : null,
      'formation_title'         => sanitize_text_field( $input['formation_title'] ?? '' ),
      'apprenant_name'          => sanitize_text_field( $input['apprenant_name'] ?? '' ),
      'apprenant_email'         => sanitize_email( $input['apprenant_email'] ?? '' ),
      'client_company'          => sanitize_text_field( $input['client_company'] ?? '' ),
      'client_siret'            => sanitize_text_field( $input['client_siret'] ?? '' ),
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
      'designation'             => $designation_in,
      'quantity'                => sanitize_text_field( $input['quantity'] ?? '1,00' ),
      'tarif_ht'                => $tarif_ht,
      /* ACDC 3.25.309 — LE TAUX N'EST PLUS UNE SAISIE NI UNE CONSTANTE.
         « ?? '20' » écrivait 20 % sur tout devis dont le formulaire ne portait
         pas le champ, quel que soit le régime de l'organisme. Le régime vient
         désormais du profil, une seule fois, dans save_quote() ; les deux
         colonnes y sont écrites ensemble et n'en bougent plus. */
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
      // ACDC 3.25.115 — avancer le prospect à « Devis envoyé ».
      if ( ! empty( $quote->source_prospect_id ) && method_exists( $this, 'maybe_advance_prospect_status' ) ) {
        $this->maybe_advance_prospect_status( (int) $quote->source_prospect_id, 'Devis envoyé' );
      }
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
    $__acdc_supprime = $wpdb->delete( $this->quote_table, array( 'id' => $quote_id ) );
    /* ACDC 3.25.300 — La trace est posée sur la suppression, pas sur la
       redirection : une page qui annonce « supprimé » ne prouve pas qu'une
       ligne l'ait été. Le résultat écrit ici est celui de la base. */
    $this->log_action_event( 'suppression_quote', 'quote', (int) $quote_id, $__acdc_supprime ? 'success' : 'error', array( 'lignes' => (int) $__acdc_supprime ) );
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

    /* Le statut du devis pilote le cycle de vie du prospect : « Envoyé » → « Devis envoyé »,
       « Signé » → « Converti » (terminal). Aligne le changement manuel sur le flux automatique. */
    if ( ! empty( $quote->source_prospect_id ) && method_exists( $this, 'maybe_advance_prospect_status' ) ) {
      if ( 'envoye' === $new_status ) {
        $this->maybe_advance_prospect_status( (int) $quote->source_prospect_id, 'Devis envoyé' );
      } elseif ( 'signe' === $new_status ) {
        $this->maybe_advance_prospect_status( (int) $quote->source_prospect_id, 'Converti', true );
      }
    }

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
    $this->log_action_event( 'preview', 'quote_document', $quote_id );

    /* ACDC 3.25.256 — « VERSION IMPRIMABLE » APERÇOIT, ELLE NE TÉLÉCHARGE PLUS.
       Ce bouton posait un en-tête « Content-Disposition: attachment » : il
       enregistrait donc un fichier .html sur le disque, alors que le bouton
       voisin — celui qui doit enregistrer — plantait. Les deux faisaient
       l'inverse de ce qu'ils annonçaient. */
    while ( ob_get_level() ) { ob_end_clean(); }
    nocache_headers();
    header( 'X-Content-Type-Options: nosniff' );
    header( 'X-Frame-Options: SAMEORIGIN' );
    header( 'Referrer-Policy: same-origin' );
    header( 'Content-Type: text/html; charset=UTF-8' );
    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- gabarit de document déjà échappé.
    exit;
  }

  /** Téléchargement d'un vrai fichier PDF du devis (gabarit mPDF dédié). */
  public function handle_download_quote_pdf() {
    $this->require_manage_options();
    $quote_id = isset( $_GET['quote_id'] ) ? absint( $_GET['quote_id'] ) : 0;
    $this->verify_nonce_or_die( 'acdc_download_quote_pdf_' . $quote_id );
    $quote = $quote_id ? $this->get_quote( $quote_id ) : null;
    if ( ! $quote ) {
      wp_die( esc_html( 'Devis introuvable.' ) );
    }
    $row = $this->build_quote_row_from_record( $quote );
    $filename = 'devis-' . sanitize_file_name( $row['number'] ) . '.pdf';
    $this->log_action_event( 'download', 'quote_pdf', $quote_id );

    /* On passe par render_html_pdf() : c'est le moteur mPDF déjà utilisé (et validé
       en production) pour la convention et les autres documents. Il diffuse un vrai
       fichier PDF puis exit ; il n'y a plus de repli silencieux en HTML. */
    $autoload = dirname( dirname( dirname( dirname( plugin_dir_path( __FILE__ ) ) ) ) ) . '/acdc-libs/vendor/autoload.php';
    if ( method_exists( $this, 'render_html_pdf' ) && file_exists( $autoload ) ) {
      /* ACDC 3.25.256 — UN DÉFAUT DE PDF NE DOIT PAS TUER LA PAGE.
         Ce bouton rendait « une erreur critique sur ce site » — un écran blanc,
         sans document et sans explication. La fabrication d'un PDF dépend de
         mPDF, de la mémoire disponible et des images du document : elle peut
         échouer, et cela ne doit jamais coûter la page. On tente, on note
         l'erreur réelle dans le journal, et à défaut on sert la version
         imprimable : un document imparfait vaut mieux qu'un écran blanc.
         Même discipline que pour la convention en 3.25.237. */
      if ( function_exists( 'wp_raise_memory_limit' ) ) {
        wp_raise_memory_limit( 'image' );
      }
      try {
        $html = $this->get_quote_pdf_html( $row );
        $this->render_html_pdf( $html, $filename, 'attachment' ); // diffuse + exit
      } catch ( \Throwable $e ) {
        error_log( '[ACDC] PDF du devis n° ' . $quote_id . ' impossible : ' . $e->getMessage() );
        if ( method_exists( $this, 'log_action_event' ) ) {
          $this->log_action_event( 'error', 'quote_pdf', $quote_id, 'error', array( 'message' => $e->getMessage() ) );
        }
      }
    }

    /* mPDF absent ou en échec : dernier recours, la version imprimable, affichée
       et non téléchargée — et elle DIT pourquoi elle est là. */
    $html = $this->get_quote_document_html( $row );
    $avis = '<div style="max-width:800px;margin:12px auto;padding:10px 14px;border:1px solid #e8c97a;background:#fff8e8;'
          . 'border-radius:6px;font-family:sans-serif;font-size:13px;color:#7a5c00;">'
          . 'Le PDF n\'a pas pu être fabriqué : voici la version imprimable du devis. '
          . 'Utilisez « Imprimer » puis « Enregistrer au format PDF ».</div>';
    while ( ob_get_level() ) { ob_end_clean(); }
    nocache_headers();
    header( 'Content-Type: text/html; charset=UTF-8' );
    echo $avis . $html; // phpcs:ignore WordPress.Security.EscapeOutput
    exit;
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

  /**
   * Télécharge le XML Factur-X (profil MINIMUM) d'une facture réelle.
   */
  public function handle_download_invoice_facturx() {
    $this->require_manage_options();
    $invoice_id = isset( $_GET['invoice_id'] ) ? absint( $_GET['invoice_id'] ) : 0;
    $this->verify_nonce_or_die( 'acdc_download_invoice_facturx_' . $invoice_id );
    $xml = method_exists( $this, 'get_invoice_facturx_xml' ) ? $this->get_invoice_facturx_xml( $invoice_id ) : '';
    if ( '' === $xml ) {
      wp_die( esc_html( 'Facture introuvable ou Factur-X indisponible (mode démo ?).' ) );
    }
    $inv = $this->get_invoice( $invoice_id );
    $num = $inv ? sanitize_file_name( (string) $inv->number ) : (string) $invoice_id;
    $this->log_action_event( 'download', 'invoice_facturx', $invoice_id );
    nocache_headers();
    header( 'Content-Type: application/xml; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="factur-x-' . $num . '.xml"' );
    echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML normé, pas du HTML.
    exit;
  }

  /**
   * Télécharge la facture au format Factur-X : PDF/A-3 avec le XML factur-x.xml embarqué.
   */
  public function handle_download_invoice_facturx_pdf() {
    $this->require_manage_options();
    $invoice_id = isset( $_GET['invoice_id'] ) ? absint( $_GET['invoice_id'] ) : 0;
    $this->verify_nonce_or_die( 'acdc_download_invoice_facturx_pdf_' . $invoice_id );
    $pdf = method_exists( $this, 'build_invoice_facturx_pdf' ) ? $this->build_invoice_facturx_pdf( $invoice_id ) : '';
    if ( '' === $pdf ) {
      wp_die( esc_html( 'PDF/A-3 Factur-X indisponible : facture introuvable, librairie mPDF absente ou mode démo.' ) );
    }
    $inv = $this->get_invoice( $invoice_id );
    $num = $inv ? sanitize_file_name( (string) $inv->number ) : (string) $invoice_id;
    $this->log_action_event( 'download', 'invoice_facturx_pdf', $invoice_id );
    while ( ob_get_level() ) { ob_end_clean(); }
    nocache_headers();
    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: attachment; filename="facture-' . $num . '.pdf"' );
    header( 'Content-Length: ' . strlen( $pdf ) );
    echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binaire PDF.
    exit;
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
          // ACDC 3.25.110 — base HT de l'avoir = sous-total HT AVEC frais (tarif_ht_number),
          // cohérent avec la facture d'origine. Utiliser tarif_ht_value (tarif de base seul)
          // gonflait la TVA de l'avoir du montant des frais (transport/repas/lignes annexes).
          'tarif_ht_value'  => $row['tarif_ht_number'] ?? ( $row['tarif_ht_value'] ?? '0' ),
          /* ACDC 3.25.309 — L'avoir portait un repli à 20 % : l'avoir d'une
             facture exonérée aurait crédité une TVA qui n'avait jamais été
             facturée. Il reprend le taux de la facture, quel qu'il soit. */
          'vat_rate'        => $row['vat_rate'] ?? '0,00',
          'vat_regime'      => $row['vat_regime'] ?? '',
          'vat_mention'     => $row['vat_mention'] ?? '',
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
    global $wpdb;
    // ACDC 3.25.115 — garde anti double-conversion (évite 2 factures pour un même devis).
    $existing_invoice_id = (int) $wpdb->get_var( $wpdb->prepare(
      "SELECT id FROM {$this->invoice_table} WHERE quote_id = %d LIMIT 1",
      $quote_id
    ) );
    if ( $existing_invoice_id ) {
      $existing = $this->get_invoice( $existing_invoice_id );
      $scope    = $existing ? (string) $existing->scope : 'action';
      $redirect = $this->portal_page_url( array( 'tab' => 'invoices_credit_notes', 'scope' => $scope, 'invoice_action' => 'view', 'invoice_id' => $existing_invoice_id ) );
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Une facture existe déjà pour ce devis.' ), 'notice_type' => 'info' ), $redirect ) );
      exit;
    }
    /* ACDC 3.25.258 — La conversion rend désormais UNE OU DEUX factures selon
       la prise en charge inscrite sur la convention, ou un refus explicite si
       le montant pris en charge dépasse la prestation. */
    $created = $this->convert_quote_to_invoice( $quote_id );
    $quote   = $this->get_quote( $quote_id );
    $scope   = $quote ? (string) $quote->scope : 'action';

    if ( isset( $created['error'] ) ) {
      $redirect = $this->portal_page_url( array( 'tab' => 'quotes', 'scope' => $scope, 'quote_action' => 'view', 'quote_id' => $quote_id ) );
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( (string) $created['error'] ), 'notice_type' => 'error' ), $redirect ) );
      exit;
    }
    if ( empty( $created ) ) {
      $redirect = $this->portal_page_url( array( 'tab' => 'quotes', 'scope' => $scope, 'quote_action' => 'view', 'quote_id' => $quote_id ) );
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Impossible de créer la facture.' ), 'notice_type' => 'error' ), $redirect ) );
      exit;
    }

    $invoice_id = (int) $created[0];
    $inv        = $this->get_invoice( $invoice_id );
    $scope      = $inv ? (string) $inv->scope : $scope;
    $message    = 'Facture créée depuis le devis.';
    if ( count( $created ) > 1 ) {
      $second   = $this->get_invoice( (int) $created[1] );
      $message  = sprintf(
        'Prise en charge partielle : deux factures créées — %s au financeur, %s au client (reste à charge calculé).',
        $inv ? (string) $inv->number : '',
        $second ? (string) $second->number : ''
      );
    } elseif ( $inv && 'funder' === (string) $inv->billed_to ) {
      $message = 'Facture créée au nom du financeur ' . (string) $inv->financeur . '.';
    }
    $redirect = $this->portal_page_url( array( 'tab' => 'invoices_credit_notes', 'scope' => $scope, 'invoice_action' => 'view', 'invoice_id' => $invoice_id ) );
    wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ), $redirect ) );
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
      /* ACDC 3.25.309 — Idem devis : le taux est la projection du régime, écrite
         dans save_invoice(). Une facture émise ne change plus de taux. */
      'payment_methods'         => sanitize_textarea_field( $input['payment_methods'] ?? '' ),
      'iban'                    => sanitize_text_field( $input['iban'] ?? '' ),
      'bic'                     => sanitize_text_field( $input['bic'] ?? '' ),
      'emission_date'           => $emission,
      'due_date'                => $due,
      'financeur'               => sanitize_text_field( $input['financeur'] ?? '' ),
      'status'                  => in_array( $input['status'] ?? '', array_keys( $this->get_invoice_status_labels() ), true ) ? $input['status'] : 'emise',
    );

    /* ACDC 3.25.258 — LA FACTURE A LE DERNIER MOT SUR SON DESTINATAIRE.
       La convention propose, la facture dispose : un accord de prise en charge
       peut tomber, ou arriver, après la signature. Le choix se fait donc ici
       aussi. En revanche les COORDONNÉES du financeur ne se retapent pas :
       elles viennent de sa fiche. Une adresse recopiée à la main est une
       seconde vérité qui vieillit mal — et une facture d'OPCO envoyée à une
       adresse périmée n'est jamais payée. */
    $billed_to = ( isset( $input['billed_to'] ) && 'funder' === (string) $input['billed_to'] ) ? 'funder' : 'client';
    $funder_id = isset( $input['funder_id'] ) ? absint( $input['funder_id'] ) : 0;
    if ( 'funder' === $billed_to && ! $funder_id ) {
      /* Adresser au financeur sans dire lequel n'a pas de sens : on retombe
         sur le client plutôt que d'émettre une facture sans destinataire. */
      $billed_to = 'client';
    }
    $data['billed_to']         = $billed_to;
    $data['funder_id']         = $funder_id ?: null;
    $data['pec_reference']     = sanitize_text_field( $input['pec_reference'] ?? '' );
    $data['pec_subrogation']   = ! empty( $input['pec_subrogation'] ) ? 1 : 0;
    $data['beneficiary_label'] = sanitize_text_field( $input['beneficiary_label'] ?? '' );

    if ( 'funder' === $billed_to ) {
      $funder = $this->get_funder( $funder_id );
      if ( $funder ) {
        $data['financeur'] = (string) $funder->name;
      }
      if ( '' === trim( (string) $data['beneficiary_label'] ) ) {
        $data['beneficiary_label'] = (string) ( $data['client_company'] ?: $data['apprenant_name'] );
      }
    }

    $new_id = $this->save_invoice( $data );
    $redirect = $this->portal_page_url( array( 'tab' => 'invoices_credit_notes', 'scope' => $scope, 'invoice_action' => 'view', 'invoice_id' => $new_id ) );
    wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Facture enregistrée.' ), 'notice_type' => 'success' ), $redirect ) );
    exit;
  }

  /* ---------------------------------------------------------------
   * ACDC 3.25.258 — CHANGER LE DESTINATAIRE D'UNE FACTURE
   *
   * « C'est la facture qui a le dernier mot. » La convention propose, la
   * facture dispose : un accord de prise en charge peut tomber, ou arriver,
   * après la signature.
   *
   * Ce gestionnaire n'écrit que les quatre champs du destinataire. Il ne passe
   * PAS par le formulaire complet de la facture : un formulaire partiel envoyé
   * à un gestionnaire qui réécrit tout viderait les champs absents — c'est une
   * perte de données silencieuse, et sur une facture elle se découvre chez le
   * client.
   * --------------------------------------------------------------- */
  public function handle_set_invoice_recipient() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
    $invoice_id = isset( $_POST['invoice_id'] ) ? absint( $_POST['invoice_id'] ) : 0;
    if ( ! $invoice_id || ! check_admin_referer( 'acdc_set_invoice_recipient_' . $invoice_id ) ) { wp_die( 'Action invalide.' ); }

    $inv = $this->get_invoice( $invoice_id );
    if ( ! $inv ) { $this->redirect_to_portal( 'invoices_credit_notes', 'Facture introuvable.', 'error' ); }

    $billed_to = ( isset( $_POST['billed_to'] ) && 'funder' === (string) $_POST['billed_to'] ) ? 'funder' : 'client';
    $funder_id = isset( $_POST['funder_id'] ) ? absint( $_POST['funder_id'] ) : 0;
    $funder    = $funder_id ? $this->get_funder( $funder_id ) : null;

    /* Adresser au financeur sans dire lequel n'a pas de sens. */
    if ( 'funder' === $billed_to && ! $funder ) {
      $redirect = $this->portal_page_url( array( 'tab' => 'invoices_credit_notes', 'scope' => (string) $inv->scope, 'invoice_action' => 'view', 'invoice_id' => $invoice_id ) );
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Choisissez le financeur destinataire : une facture sans destinataire ne peut pas être émise.' ), 'notice_type' => 'error' ), $redirect ) );
      exit;
    }

    $data = array(
      'id'              => $invoice_id,
      'billed_to'       => $billed_to,
      'funder_id'       => ( 'funder' === $billed_to ) ? $funder_id : null,
      'pec_reference'   => sanitize_text_field( wp_unslash( $_POST['pec_reference'] ?? '' ) ),
      'pec_subrogation' => ! empty( $_POST['pec_subrogation'] ) ? 1 : 0,
    );
    if ( 'funder' === $billed_to ) {
      $data['financeur'] = (string) $funder->name;
      if ( empty( $inv->beneficiary_label ) ) {
        $data['beneficiary_label'] = (string) ( $inv->client_company ?: $inv->apprenant_name );
      }
    }
    $this->save_invoice( $data );

    $message  = ( 'funder' === $billed_to )
      ? 'Facture adressée à ' . (string) $funder->name . '.'
      : 'Facture adressée au client.';
    $redirect = $this->portal_page_url( array( 'tab' => 'invoices_credit_notes', 'scope' => (string) $inv->scope, 'invoice_action' => 'view', 'invoice_id' => $invoice_id ) );
    wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( $message ), 'notice_type' => 'success' ), $redirect ) );
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
    $__acdc_supprime = $wpdb->delete( $this->invoice_table, array( 'id' => $invoice_id ) );
    /* ACDC 3.25.300 — La trace est posée sur la suppression, pas sur la
       redirection : une page qui annonce « supprimé » ne prouve pas qu'une
       ligne l'ait été. Le résultat écrit ici est celui de la base. */
    $this->log_action_event( 'suppression_invoice', 'invoice', (int) $invoice_id, $__acdc_supprime ? 'success' : 'error', array( 'lignes' => (int) $__acdc_supprime ) );
    $this->redirect_to_portal( 'invoices_credit_notes', 'Facture supprimée.', 'success', array( 'scope' => $scope ) );
  }

  /* ---------------------------------------------------------------
   * ACDC 3.25.116 — Marquer une facture comme payée
   * Action : admin_post_acdc_mark_invoice_paid (POST + nonce)
   * Colonnes écrites : status='payee', paid_at, updated_at.
   * --------------------------------------------------------------- */
  public function handle_mark_invoice_paid() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); } // ACDC 3.25.116
    $invoice_id = isset( $_POST['invoice_id'] ) ? absint( $_POST['invoice_id'] ) : 0;
    if ( ! $invoice_id || ! check_admin_referer( 'acdc_mark_invoice_paid_' . $invoice_id ) ) { wp_die( 'Action invalide.' ); }
    if ( $this->is_documents_billing_demo_enabled() ) {
      $this->redirect_to_portal( 'invoices_credit_notes', 'Activez la facturation réelle dans les Réglages avant de modifier des factures.', 'error' );
    }
    global $wpdb;
    $inv = $this->get_invoice( $invoice_id );
    if ( ! $inv ) { $this->redirect_to_portal( 'invoices_credit_notes', 'Facture introuvable.', 'error' ); }
    $scope = (string) $inv->scope;
    if ( 'payee' !== (string) $inv->status ) {
      $now = current_time( 'mysql' );
      $wpdb->update( $this->invoice_table, array(
        'status'     => 'payee',
        'paid_at'    => $now,
        'updated_at' => $now,
      ), array( 'id' => $invoice_id ) );
    }
    $redirect = $this->portal_page_url( array( 'tab' => 'invoices_credit_notes', 'scope' => $scope, 'invoice_action' => 'view', 'invoice_id' => $invoice_id ) );
    wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Facture marquée payée.' ), 'notice_type' => 'success' ), $redirect ) );
    exit;
  }

  /* ---------------------------------------------------------------
   * ACDC 3.25.133 — Export comptable CSV de toutes les factures.
   * Action : admin_post_acdc_export_accounting_csv (GET + nonce)
   * Lecture seule. Chaque facture donne une ligne (+) ; une facture créditée
   * (statut « avoir ») ajoute une seconde ligne pour l'avoir (−) → CA net correct.
   * Sortie via ACDC\Support\AccountingExport (déjà échappée).
   * --------------------------------------------------------------- */
  public function handle_export_accounting_csv() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); }
    if ( ! check_admin_referer( 'acdc_export_accounting_csv' ) ) { wp_die( 'Action invalide.' ); }
    if ( ! class_exists( '\\ACDC\\Support\\AccountingExport' ) || ! class_exists( '\\ACDC\\Support\\Money' ) ) {
      wp_die( 'Module d\'export indisponible.' );
    }
    $collected = $this->build_accounting_rows();
    $rows      = $collected['rows'];
    $min_date  = $collected['min'];
    $max_date  = $collected['max'];

    $csv      = \ACDC\Support\AccountingExport::toCsv( $rows );
    $filename = \ACDC\Support\AccountingExport::filename( $min_date ?: gmdate( 'Y-m-d' ), $max_date ?: gmdate( 'Y-m-d' ) );

    while ( ob_get_level() ) {
      ob_end_clean();
    }
    nocache_headers();
    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Length: ' . strlen( $csv ) );
    echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV déjà construit/échappé par AccountingExport.
    exit;
  }

  /* ---------------------------------------------------------------
   * ACDC 3.25.134 — Construction des lignes comptables (partagé export + encart CA).
   * Lecture seule. Chaque facture → une ligne (+) ; une facture créditée → une
   * ligne d'avoir (−). Renvoie { rows, min, max } (dates ISO extrêmes).
   * --------------------------------------------------------------- */
  private function build_accounting_rows() {
    global $wpdb;
    $rows = array();
    $min  = '';
    $max  = '';
    if ( ! class_exists( '\\ACDC\\Support\\Money' ) ) {
      return array( 'rows' => $rows, 'min' => $min, 'max' => $max );
    }
    $invoices = $wpdb->get_results( "SELECT * FROM {$this->invoice_table} ORDER BY emission_date ASC, id ASC" );
    foreach ( (array) $invoices as $inv ) {
      $transport = (float) ( $inv->transport_fees_enabled ? $inv->transport_fees_ht : 0 );
      $meal      = (float) ( $inv->meal_fees_enabled ? $inv->meal_fees_ht : 0 );
      $extra     = 0.0;
      if ( ! empty( $inv->extra_lines_json ) ) {
        $decoded = json_decode( (string) $inv->extra_lines_json, true );
        if ( is_array( $decoded ) ) {
          foreach ( $decoded as $el ) { $extra += (float) ( $el['total_ht'] ?? 0 ); }
        }
      }
      $vat    = (float) $inv->vat_rate;
      $totals = \ACDC\Support\Money::invoiceTotals( (float) $inv->tarif_ht, $transport, $meal, $extra, $vat );
      $client = $inv->client_company ? (string) $inv->client_company : (string) $inv->apprenant_name;
      $date   = $inv->emission_date ? (string) $inv->emission_date : '';

      $rows[] = array(
        'number' => (string) $inv->number, 'date' => $date, 'client' => $client,
        'vat_rate' => $vat, 'ht' => $totals['ht'], 'tva' => $totals['tva'], 'ttc' => $totals['ttc'], 'kind' => 'invoice',
      );
      if ( ! empty( $inv->credit_note_number ) ) {
        $cn_date = $inv->credit_note_date ? (string) $inv->credit_note_date : $date;
        $rows[]  = array(
          'number' => (string) $inv->credit_note_number, 'date' => $cn_date, 'client' => $client,
          'vat_rate' => $vat, 'ht' => $totals['ht'], 'tva' => $totals['tva'], 'ttc' => $totals['ttc'], 'kind' => 'avoir',
        );
      }
      if ( $date ) {
        if ( '' === $min || $date < $min ) { $min = $date; }
        if ( '' === $max || $date > $max ) { $max = $date; }
      }
    }
    return array( 'rows' => $rows, 'min' => $min, 'max' => $max );
  }

  /* ---------------------------------------------------------------
   * ACDC 3.25.134 — Encart tableau de bord : chiffre d'affaires (net d'avoirs).
   * S'appuie sur ACDC\Support\Revenue. Lecture seule, réservé aux administrateurs.
   * --------------------------------------------------------------- */
  public function register_ca_dashboard_widget() {
    if ( ! function_exists( 'wp_add_dashboard_widget' ) || ! current_user_can( 'manage_options' ) ) {
      return;
    }
    wp_add_dashboard_widget( 'acdc_of_ca_widget', 'ACDC — Chiffre d’affaires', array( $this, 'render_ca_dashboard_widget' ) );
  }

  public function render_ca_dashboard_widget() {
    if ( ! class_exists( '\\ACDC\\Support\\Revenue' ) ) {
      echo '<p>Module de calcul indisponible.</p>';
      return;
    }
    $collected = $this->build_accounting_rows();
    $all_rows  = $collected['rows'];
    if ( empty( $all_rows ) ) {
      echo '<p>' . esc_html__( 'Aucune facture pour le moment.', 'acdc-formation-saas-organisme-de-formation' ) . '</p>';
      return;
    }
    $year      = gmdate( 'Y' );
    $year_rows = array();
    foreach ( $all_rows as $r ) {
      if ( isset( $r['date'] ) && 0 === strpos( (string) $r['date'], $year . '-' ) ) {
        $year_rows[] = $r;
      }
    }
    $summary = \ACDC\Support\Revenue::summarize( $year_rows );
    $by_month = \ACDC\Support\Revenue::byPeriod( $year_rows, 'month' );

    $fmt = static function ( $n ) {
      return number_format( (float) $n, 2, ',', ' ' ) . ' €';
    };

    echo '<p style="margin-top:0;"><strong>' . esc_html( 'Année ' . $year ) . '</strong> — net d’avoirs</p>';
    echo '<div style="display:flex;gap:18px;flex-wrap:wrap;margin-bottom:10px;">';
    echo '<div><div style="color:#666;font-size:12px;">CA HT</div><div style="font-size:20px;font-weight:700;">' . esc_html( $fmt( $summary['ht'] ) ) . '</div></div>';
    echo '<div><div style="color:#666;font-size:12px;">TVA</div><div style="font-size:20px;font-weight:700;">' . esc_html( $fmt( $summary['tva'] ) ) . '</div></div>';
    echo '<div><div style="color:#666;font-size:12px;">CA TTC</div><div style="font-size:20px;font-weight:700;">' . esc_html( $fmt( $summary['ttc'] ) ) . '</div></div>';
    echo '</div>';

    if ( ! empty( $by_month ) ) {
      echo '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
      echo '<thead><tr><th style="text-align:left;padding:3px 6px;border-bottom:1px solid #e0e0e0;">Mois</th><th style="text-align:right;padding:3px 6px;border-bottom:1px solid #e0e0e0;">HT</th><th style="text-align:right;padding:3px 6px;border-bottom:1px solid #e0e0e0;">TTC</th></tr></thead><tbody>';
      foreach ( $by_month as $period => $vals ) {
        echo '<tr><td style="padding:3px 6px;">' . esc_html( (string) $period ) . '</td>'
          . '<td style="text-align:right;padding:3px 6px;">' . esc_html( $fmt( $vals['ht'] ) ) . '</td>'
          . '<td style="text-align:right;padding:3px 6px;">' . esc_html( $fmt( $vals['ttc'] ) ) . '</td></tr>';
      }
      echo '</tbody></table>';
    }
    echo '<p style="color:#666;margin-bottom:0;">' . esc_html__( 'Basé sur les factures internes (facturation officielle : Tiime).', 'acdc-formation-saas-organisme-de-formation' ) . '</p>';
  }

  /* ---------------------------------------------------------------
   * ACDC 3.25.116 — Envoyer une facture par e-mail
   * Action : admin_post_acdc_send_invoice_email (POST + nonce)
   * Calqué sur handle_send_quote_email(). Colonnes écrites en cas de succès :
   * status='envoyee' (seulement si la facture était encore 'emise', pour ne pas
   * rétrograder une facture déjà payée), sent_at, updated_at, html_url.
   * --------------------------------------------------------------- */
  public function handle_send_invoice_email() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); } // ACDC 3.25.116
    $invoice_id = isset( $_POST['invoice_id'] ) ? absint( $_POST['invoice_id'] ) : 0;
    if ( ! $invoice_id || ! check_admin_referer( 'acdc_send_invoice_email_' . $invoice_id ) ) { wp_die( 'Action invalide.' ); }
    if ( $this->is_documents_billing_demo_enabled() ) {
      $this->redirect_to_portal( 'invoices_credit_notes', 'Activez la facturation réelle dans les Réglages avant d\'envoyer des factures.', 'error' );
    }
    global $wpdb;
    $inv = $this->get_invoice( $invoice_id );
    if ( ! $inv ) { $this->redirect_to_portal( 'invoices_credit_notes', 'Facture introuvable.', 'error' ); }

    $email = sanitize_email( (string) $inv->apprenant_email );
    if ( ! $email || ! is_email( $email ) ) {
      $redirect = $this->portal_page_url( array( 'tab' => 'invoices_credit_notes', 'scope' => $inv->scope, 'invoice_action' => 'view', 'invoice_id' => $invoice_id ) );
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Envoi impossible : aucun email renseigné sur cette facture.' ), 'notice_type' => 'error' ), $redirect ) );
      exit;
    }

    $row = $this->build_invoice_row_from_record( $inv );

    /* Régénérer le HTML de la facture et le stocker : aucune fonction generate_invoice_html
       n'existe (la colonne invoice.html_url n'est jamais peuplée ailleurs), on reproduit donc
       ici le pattern de generate_quote_html() pour disposer d'une URL fraîche à envoyer. */
    $html_url      = '';
    $document_html = $this->get_invoice_document_html( $row, 'invoice' );
    if ( '' !== (string) $document_html ) {
      $upload   = wp_upload_dir();
      $dir      = trailingslashit( $upload['basedir'] ) . 'acdc-invoices/';
      $url_base = trailingslashit( $upload['baseurl'] ) . 'acdc-invoices/';
      /* ACDC 3.25.313 — Dossier non listable : sans index.php, un serveur mal
         réglé donne l'inventaire de vos factures, donc la liste de vos clients. */
      $this->acdc_dossier_documents( $dir );
      if ( ! empty( $inv->html_url ) ) {
        $old_path = str_replace( $url_base, $dir, (string) $inv->html_url );
        if ( 0 === strpos( $old_path, $dir ) && file_exists( $old_path ) ) {
          @unlink( $old_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }
      }
      /* ACDC 3.25.313 — Jeton aléatoire : voir le devis. Une facture porte le
         nom du client et le montant. */
      $filename = 'facture-' . (int) $inv->id . '-' . time() . '-' . wp_generate_password( 20, false, false ) . '.html';
      if ( false !== file_put_contents( $dir . $filename, $document_html ) ) {
        $html_url = $url_base . $filename;
        $wpdb->update( $this->invoice_table, array( 'html_url' => $html_url ), array( 'id' => $invoice_id ) );
      }
    }

    $branding  = $this->get_branding_options();
    $org_name  = $branding['company_name'] ?? 'ACDC Formation';
    $formation = $row['formation'] ?: 'Votre formation';
    $recipient = $row['apprenant'] ?: $row['client_company'];

    $cta = '';
    if ( '' !== $html_url ) {
      $cta = '<p style="text-align:center;margin:28px 0;">'
        . '<a href="' . esc_url( $html_url ) . '" style="display:inline-block;background:#d6a353;color:#0f2c52;font-weight:800;font-size:16px;text-decoration:none;padding:14px 32px;border-radius:10px;">'
        . '&#128196;&nbsp; Consulter votre facture'
        . '</a></p>'
        . '<p style="font-size:13px;color:#6b7280;text-align:center;">Ou copiez ce lien : <a href="' . esc_url( $html_url ) . '" style="color:#5579bf;">' . esc_html( $html_url ) . '</a></p>';
    }

    $subject = 'Votre facture — ' . $row['number'] . ' — ' . $org_name;

    $sent = $this->acdc_send_transactional_email( $email, $subject, array(
      'title'    => 'Votre facture de formation',
      'intro'    => 'Bonjour ' . esc_html( $recipient ) . ',<br><br>Veuillez trouver ci-dessous votre facture <strong>' . esc_html( $row['number'] ) . '</strong> pour la formation <strong>' . esc_html( $formation ) . '</strong>.',
      'cta_html' => $cta,
      'footer'   => 'Pour toute question, contactez-nous à <a href="mailto:' . esc_attr( $branding['email'] ?? '' ) . '">' . esc_html( $branding['email'] ?? '' ) . '</a>.',
    ), array( 'from_name' => $org_name, 'from_email' => $branding['email'] ?? '' ) );

    if ( $sent ) {
      $now  = current_time( 'mysql' );
      $data = array( 'sent_at' => $now, 'updated_at' => $now );
      // Ne pas rétrograder une facture déjà payée/en retard/litige : on ne passe à
      // 'envoyee' que si elle était encore au statut initial 'emise'.
      if ( 'emise' === (string) $inv->status ) {
        $data['status'] = 'envoyee';
      }
      $wpdb->update( $this->invoice_table, $data, array( 'id' => $invoice_id ) );
    }

    $msg  = $sent ? 'Facture envoyée par email.' : 'Envoi impossible — vérifiez la configuration email.';
    $type = $sent ? 'success' : 'error';
    $redirect = $this->portal_page_url( array( 'tab' => 'invoices_credit_notes', 'scope' => $inv->scope, 'invoice_action' => 'view', 'invoice_id' => $invoice_id ) );
    wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( $msg ), 'notice_type' => $type ), $redirect ) );
    exit;
  }

  /* ---------------------------------------------------------------
   * ACDC 3.25.118 — Relancer une facture impayée
   * Action : admin_post_acdc_relance_invoice (POST + nonce)
   * Calqué sur handle_mark_invoice_paid()/handle_send_invoice_email().
   * Colonnes écrites : relance_count (+1), last_relance_at, updated_at.
   * Envoie un e-mail de relance via acdc_send_transactional_email().
   * --------------------------------------------------------------- */
  public function handle_relance_invoice() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); } // ACDC 3.25.118
    $invoice_id = isset( $_POST['invoice_id'] ) ? absint( $_POST['invoice_id'] ) : 0;
    if ( ! $invoice_id || ! check_admin_referer( 'acdc_relance_invoice_' . $invoice_id ) ) { wp_die( 'Action invalide.' ); }
    if ( $this->is_documents_billing_demo_enabled() ) {
      $this->redirect_to_portal( 'invoices_credit_notes', 'Activez la facturation réelle dans les Réglages avant de relancer des factures.', 'error' );
    }
    global $wpdb;
    $inv = $this->get_invoice( $invoice_id );
    if ( ! $inv ) { $this->redirect_to_portal( 'invoices_credit_notes', 'Facture introuvable.', 'error' ); }
    $scope = (string) $inv->scope;

    /* Une relance ne concerne qu'une facture émise/envoyée/en retard (impayée). */
    if ( ! in_array( (string) $inv->status, array( 'emise', 'envoyee', 'en_retard' ), true ) ) {
      $redirect = $this->portal_page_url( array( 'tab' => 'invoices_credit_notes', 'scope' => $scope, 'invoice_action' => 'view', 'invoice_id' => $invoice_id ) );
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Relance impossible : cette facture n\'est pas en attente de paiement.' ), 'notice_type' => 'error' ), $redirect ) );
      exit;
    }

    $email = sanitize_email( (string) $inv->apprenant_email );
    if ( ! $email || ! is_email( $email ) ) {
      $redirect = $this->portal_page_url( array( 'tab' => 'invoices_credit_notes', 'scope' => $scope, 'invoice_action' => 'view', 'invoice_id' => $invoice_id ) );
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Relance impossible : aucun email renseigné sur cette facture.' ), 'notice_type' => 'error' ), $redirect ) );
      exit;
    }

    $row       = $this->build_invoice_row_from_record( $inv );
    $branding  = $this->get_branding_options();
    $org_name  = $branding['company_name'] ?? 'ACDC Formation';
    $formation = $row['formation'] ?: 'Votre formation';
    $recipient = $row['apprenant'] ?: $row['client_company'];

    $cta = '';
    if ( ! empty( $inv->html_url ) ) {
      $cta = '<p style="text-align:center;margin:28px 0;">'
        . '<a href="' . esc_url( (string) $inv->html_url ) . '" style="display:inline-block;background:#d6a353;color:#0f2c52;font-weight:800;font-size:16px;text-decoration:none;padding:14px 32px;border-radius:10px;">'
        . '&#128196;&nbsp; Consulter votre facture'
        . '</a></p>';
    }

    $subject = 'Relance — Facture ' . $row['number'] . ' — ' . $org_name;

    $sent = $this->acdc_send_transactional_email( $email, $subject, array(
      'title'    => 'Relance de paiement',
      'intro'    => 'Bonjour ' . esc_html( $recipient ) . ',<br><br>Sauf erreur de notre part, votre facture <strong>' . esc_html( $row['number'] ) . '</strong> pour la formation <strong>' . esc_html( $formation ) . '</strong>'
        . ( ! empty( $row['due_date'] ) ? ' (échéance du ' . esc_html( $row['due_date'] ) . ')' : '' )
        . ' demeure impayée à ce jour. Nous vous remercions de bien vouloir procéder à son règlement dans les meilleurs délais.',
      'cta_html' => $cta,
      'footer'   => 'Si votre règlement a déjà été effectué, merci de ne pas tenir compte de ce message. Pour toute question, contactez-nous à <a href="mailto:' . esc_attr( $branding['email'] ?? '' ) . '">' . esc_html( $branding['email'] ?? '' ) . '</a>.',
    ), array( 'from_name' => $org_name, 'from_email' => $branding['email'] ?? '' ) );

    if ( $sent ) {
      $now = current_time( 'mysql' );
      $wpdb->update( $this->invoice_table, array(
        'relance_count'  => (int) $inv->relance_count + 1,
        'last_relance_at' => $now,
        'updated_at'     => $now,
      ), array( 'id' => $invoice_id ) );
    }

    $msg  = $sent ? 'Relance envoyée par email.' : 'Relance impossible — vérifiez la configuration email.';
    $type = $sent ? 'success' : 'error';
    $redirect = $this->portal_page_url( array( 'tab' => 'invoices_credit_notes', 'scope' => $scope, 'invoice_action' => 'view', 'invoice_id' => $invoice_id ) );
    wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( $msg ), 'notice_type' => $type ), $redirect ) );
    exit;
  }

  /* ---------------------------------------------------------------
   * ACDC 3.25.118 — Émettre un AVOIR persistant sur une facture
   * Action : admin_post_acdc_emit_credit_note (POST + nonce)
   * Réserve un numéro AV-{année}- SANS TROU via reserve_next_document_number()
   * sous verrou GET_LOCK (séquence distincte de FA-). Colonnes écrites :
   * credit_note_number, credit_note_date (today), credit_note_reason, status='avoir'.
   * Refuse si un avoir existe déjà (credit_note_number non vide).
   * --------------------------------------------------------------- */
  public function handle_emit_credit_note() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé.' ); } // ACDC 3.25.118
    $invoice_id = isset( $_POST['invoice_id'] ) ? absint( $_POST['invoice_id'] ) : 0;
    if ( ! $invoice_id || ! check_admin_referer( 'acdc_emit_credit_note_' . $invoice_id ) ) { wp_die( 'Action invalide.' ); }
    if ( $this->is_documents_billing_demo_enabled() ) {
      $this->redirect_to_portal( 'invoices_credit_notes', 'Activez la facturation réelle dans les Réglages avant d\'émettre des avoirs.', 'error' );
    }
    global $wpdb;
    $inv = $this->get_invoice( $invoice_id );
    if ( ! $inv ) { $this->redirect_to_portal( 'invoices_credit_notes', 'Facture introuvable.', 'error' ); }
    $scope = (string) $inv->scope;

    /* Garde anti double-avoir : un numéro d'avoir déjà attribué est définitif. */
    if ( ! empty( $inv->credit_note_number ) ) {
      $redirect = $this->portal_page_url( array( 'tab' => 'invoices_credit_notes', 'scope' => $scope, 'invoice_action' => 'view', 'invoice_id' => $invoice_id ) );
      wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Un avoir existe déjà pour cette facture (' . $inv->credit_note_number . ').' ), 'notice_type' => 'info' ), $redirect ) );
      exit;
    }

    $reason = isset( $_POST['credit_note_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['credit_note_reason'] ) ) : '';

    /* Numérotation atomique de l'avoir : verrou nommé + réservation persistée SANS TROU.
       Préfixe AV-{année}- => séquence distincte de FA- (compteur monotone dédié). */
    $lock_name = 'acdc_of_credit_note_num_' . $wpdb->prefix;
    $has_lock  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock_name ) );
    $cn_number = $this->reserve_next_document_number( 'AV-' . (int) wp_date( 'Y' ) . '-', $this->invoice_table );
    $now       = current_time( 'mysql' );
    $wpdb->update( $this->invoice_table, array(
      'credit_note_number' => $cn_number,
      'credit_note_date'   => wp_date( 'Y-m-d' ),
      'credit_note_reason' => $reason,
      'status'             => 'avoir',
      'updated_at'         => $now,
    ), array( 'id' => $invoice_id ) );
    if ( $has_lock ) {
      $wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
    }

    $redirect = $this->portal_page_url( array( 'tab' => 'invoices_credit_notes', 'scope' => $scope, 'invoice_action' => 'view', 'invoice_id' => $invoice_id ) );
    wp_safe_redirect( add_query_arg( array( 'notice' => rawurlencode( 'Avoir ' . $cn_number . ' émis.' ), 'notice_type' => 'success' ), $redirect ) );
    exit;
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

    $__acdc_supprime = $wpdb->delete( $this->document_table, array( 'id' => $document_id ) );
    /* ACDC 3.25.300 — La trace est posée sur la suppression, pas sur la
       redirection : une page qui annonce « supprimé » ne prouve pas qu'une
       ligne l'ait été. Le résultat écrit ici est celui de la base. */
    $this->log_action_event( 'suppression_document', 'document', (int) $document_id, $__acdc_supprime ? 'success' : 'error', array( 'lignes' => (int) $__acdc_supprime ) );
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
    // Une demande existe déjà : « Renvoyer » (resend=1) relance l'e-mail au lieu d'échouer.
    if ( ! empty( $quote->signature_request_id ) && (int) $quote->signature_request_id > 0 ) {
      $existing_req_id = (int) $quote->signature_request_id;
      if ( 'signée' === (string) $quote->signature_status ) {
        $this->redirect_to_portal( 'quotes', 'Ce devis est déjà signé.', 'error' );
        return;
      }
      $resend = isset( $_GET['resend'] ) && '1' === (string) $_GET['resend'];
      if ( ! $resend ) {
        $this->redirect_to_portal( 'quotes', 'Une demande de signature existe déjà pour ce devis. Utilisez « Renvoyer ».', 'error' );
        return;
      }
      if ( class_exists( 'ACDC_Sig_Core' ) && class_exists( 'ACDC_Sig_Email' ) ) {
        $sig_core_r = new ACDC_Sig_Core();
        $sig_core_r->init_tables();
        $sig_email_r = new ACDC_Sig_Email( $sig_core_r );
        $sig_email_r->send_signature_email( $existing_req_id );
        $this->log_action_event( 'resend_signature', 'quote', $quote_id );
        $this->redirect_to_portal( 'quotes', 'La demande de signature a été renvoyée.', 'success' );
        return;
      }
      $this->redirect_to_portal( 'quotes', 'Le module de signature électronique n\'est pas disponible.', 'error' );
      return;
    }
    // Vérifier l'adresse e-mail du destinataire
    $signer_email = sanitize_email( (string) $quote->apprenant_email );
    if ( '' === $signer_email || ! is_email( $signer_email ) ) {
      $this->redirect_to_portal( 'quotes', 'Le devis ne comporte pas d\'adresse e-mail valide pour le destinataire.', 'error' );
      return;
    }
    if ( ! class_exists( 'ACDC_Sig_Core' ) || ! class_exists( 'ACDC_Sig_Email' ) ) {
      $this->redirect_to_portal( 'quotes', 'Le module de signature électronique n\'est pas disponible.', 'error' );
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
      $this->redirect_to_portal( 'quotes', 'Impossible de générer le document du devis avant signature.', 'error' );
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
      /* ACDC 3.25.309 — L'entité pour qui le devis est établi, qui donnera son
         nom au certificat de signature. L'entreprise quand il y en a une, la
         personne sinon. */
      'entity_label' => (string) ( $quote->client_company ?: $quote->apprenant_name ),
      'doc_type'     => 'devis',
      'sig_level'    => ACDC_Sig_Core::LEVEL_RENFORCE,
      'doc_url'      => $doc_url,
      'doc_path'     => file_exists( $doc_path ) ? $doc_path : '',
      'notes'        => wp_json_encode( array(
        'entity_type'           => 'quote',
        'quote_id'              => (int) $quote_id,
        'doc_reference'         => (string) $quote->number,
        'signed_delivery_email' => sanitize_email( (string) get_option( 'admin_email' ) ),
      ) ),
    ) );
    if ( ! $request_id ) {
      $this->redirect_to_portal( 'quotes', 'La demande de signature n\'a pas pu être créée.', 'error' );
      return;
    }
    $sig_email = new ACDC_Sig_Email( $sig_core );
    $sig_email->send_signature_email( $request_id );
    $wpdb->update( $this->quote_table, array(
      'signature_request_id' => (int) $request_id,
      'signature_status'     => 'envoyée',
      'status'               => 'a_signer',
    ), array( 'id' => $quote_id ), array( '%d', '%s', '%s' ), array( '%d' ) );
    /* M23 — Envoi du devis en signature = « Devis envoyé » dans le pipeline prospect. */
    if ( ! empty( $quote->source_prospect_id ) && method_exists( $this, 'maybe_advance_prospect_status' ) ) {
      $this->maybe_advance_prospect_status( (int) $quote->source_prospect_id, 'Devis envoyé' );
    }
    /* M10 — Revenir sur la fiche du devis (et non sur l'écran hub qui perdait le contexte). */
    $this->redirect_to_portal( 'quotes', 'Demande de signature envoyée à ' . $signer_email . '.', 'success', array(
      'scope'        => $quote->scope ?: 'action',
      'quote_action' => 'view',
      'quote_id'     => (int) $quote_id,
    ) );
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

    /* Signature manuscrite du client : PNG capturé par le module de signature, comme la
       convention. On le mémorise sur le devis pour l'apposer côté « Bon pour accord » dans
       tous les rendus ultérieurs (PDF téléchargé + pièce jointe e-mail) → devis signé des
       DEUX parties (organisme via le cachet + client via sa signature manuscrite). */
    $client_sig_path = '';
    $upload_dir_q    = wp_upload_dir();
    $sig_dir_q       = trailingslashit( $upload_dir_q['basedir'] ) . 'acdc-signatures/' . $request_id . '/';
    $sig_files_q     = glob( $sig_dir_q . 'signature-*.png' );
    if ( ! empty( $sig_files_q ) ) {
      rsort( $sig_files_q );
      if ( file_exists( (string) $sig_files_q[0] ) ) { $client_sig_path = (string) $sig_files_q[0]; }
    }

    $signed_at = current_time( 'mysql' );

    $update_data    = array(
      'signature_status'     => 'signée',
      'status'               => 'signe',
      'signed_document_url'  => $signed_url,
      'client_signed_at'     => $signed_at,
    );
    $update_formats = array( '%s', '%s', '%s', '%s' );
    if ( '' !== $client_sig_path ) {
      $update_data['client_signature_path'] = $client_sig_path;
      $update_formats[] = '%s';
    }
    $wpdb->update( $this->quote_table, $update_data, array( 'id' => $quote_id ), $update_formats, array( '%d' ) );

    /* ACDC 3.25.210 — LE DOCUMENT EN LIGNE EST REFABRIQUÉ APRÈS LA SIGNATURE.
       C'est le défaut signalé par David : « la signature n'apparaît pas sur le
       devis signé, alors que la convention est parfaite ». Les deux flux ne se
       ressemblaient qu'en apparence. La convention REMPLACE son document par le
       PDF signé que produit le module de signature. Le devis, lui, est un
       fichier HTML écrit sur le disque au moment de l'envoi, puis jamais
       retouché : le lien reçu par le commanditaire — et le devis qu'il
       télécharge ensuite — pointait donc sur la version d'AVANT la signature.
       Le gabarit savait pourtant l'afficher depuis toujours ; personne ne lui
       redemandait de le faire.
       On relit donc le devis une fois la signature enregistrée, on regénère le
       fichier, et le lien « devis signé » désigne enfin un document signé.
       L'ordre compte : la signature est en base AVANT la regénération, sinon on
       réécrirait à l'identique. */
    $quote_after = $this->get_quote( $quote_id );
    if ( $quote_after && method_exists( $this, 'generate_quote_html' ) ) {
      $regenerated = $this->generate_quote_html( $quote_after );
      if ( $regenerated ) {
        $wpdb->update(
          $this->quote_table,
          array( 'html_url' => $regenerated, 'signed_document_url' => $regenerated ),
          array( 'id' => $quote_id ),
          array( '%s', '%s' ),
          array( '%d' )
        );
        $signed_url = $regenerated;
        $quote      = $this->get_quote( $quote_id ) ?: $quote;
      }
    }
    $profile_s   = $this->get_company_profile_options();
    /* ACDC 3.25.290 — « enterprise_contact_name » n'existe pas dans la fiche :
       l'accusé de devis signé partait au nom du site WordPress. */
    $from_name_s = $this->acdc_expediteur_organisme();
    $from_email_s= ! empty( $profile_s['enterprise_contact_email'] ) ? sanitize_email( (string) $profile_s['enterprise_contact_email'] )       : sanitize_email( (string) get_option( 'admin_email' ) );
    $signer_name = sanitize_text_field( (string) $quote->apprenant_name );
    $quote_num   = sanitize_text_field( (string) $quote->number );

    /* ACDC 3.25.225 — LE CHAÎNON MANQUANT : DEVIS SIGNÉ → CONVENTION.
       La recette l'a formulé sans détour : « il n'existe aucun chemin devis
       signé → convention ». Le devis signé porte pourtant déjà tout ce que la
       convention réclame — le commanditaire, la formation, les dates, le prix,
       la TVA, les frais annexes — et l'organisme devait ressaisir l'ensemble à
       la main, avec le risque d'écart entre ce qui a été signé et ce qui est
       conventionné. Deux documents contractuels qui se contredisent, c'est le
       genre de faute qu'un financeur relève.
       On crée donc la convention à la signature du devis, une seule fois, et
       en BROUILLON : l'application prépare, l'organisme relit et signe. Elle
       ne part à personne toute seule — une convention est un engagement, elle
       ne s'envoie jamais sans décision humaine. */
    $this->maybe_create_contract_from_signed_quote( $quote );

    /* M23 — Devis signé = conversion : faire passer le prospect à « Converti ». */
    if ( ! empty( $quote->source_prospect_id ) && method_exists( $this, 'maybe_advance_prospect_status' ) ) {
      $this->maybe_advance_prospect_status( (int) $quote->source_prospect_id, 'Converti', true );
    }

    /* Exemplaire client du devis signé — le flux devis ne l'envoyait pas (contrairement à la
       convention). La notification interne, elle, est déjà émise par le module de signature
       (notify_admin) : on ne la ré-émet plus ici, ce qui supprime aussi le doublon interne. */
    $client_email = sanitize_email( (string) $quote->apprenant_email );
    if ( '' !== $client_email && is_email( $client_email ) ) {
      $doc_link = $signed_url ?: ( ! empty( $quote->html_url ) ? esc_url_raw( (string) $quote->html_url ) : '' );
      /* Attribution d'archive : rattachement fiable au devis et au prospect. */
      $attr_client = array(
        'source_module'       => 'documents-billing',
        'source_action'       => 'quote_signed_client_copy',
        'related_entity_type' => 'quote',
        'related_entity_id'   => (string) (int) $quote_id,
        'email_category'      => 'commercial',
        'email_audience'      => 'prospect',
      );
      $subj_client = '📄 Votre exemplaire — Devis signé';
      $cta_client  = $doc_link
        ? '<p style="text-align:center;margin:24px 0;"><a href="' . esc_url( $doc_link ) . '" style="display:inline-block;padding:13px 26px;background:#C5A253;color:#0B0706;text-decoration:none;border-radius:8px;font-weight:700;">📄 Consulter votre devis signé</a></p>'
        : '';
      /* ACDC 3.25.246 — Cet e-mail était écrit à la main : ni logo, ni en-tête,
         ni pied de page, alors que le client venait d'en recevoir trois autres
         au gabarit de la maison. Le contenu ne change pas, il est simplement
         rangé dans les cases du gabarit commun. */
      $tpl_client = array(
        'greeting_name' => $signer_name,
        'intro_html'    => '<p>Nous confirmons la signature électronique de votre devis <strong>' . esc_html( $quote_num ) . '</strong>, le ' . esc_html( mysql2date( 'd/m/Y à H\hi', $signed_at ) ) . '.</p>',
        'body_html'     => $cta_client
                         . '<p>Vous trouverez en pièce jointe votre devis au format PDF, signé par les deux parties (organisme et client).</p>'
                         . '<p>Merci de votre confiance.</p>',
        'footer_notice' => 'Cet e-mail vous est adressé à la suite de la signature de votre devis. Vos données sont traitées conformément au RGPD.',
      );

      /* Pièce jointe : vrai fichier PDF du devis (gabarit mPDF dédié), signé des deux parties.
         On relit le devis pour que la ligne intègre la signature manuscrite du client. */
      $attachments = array();
      $tmp_pdf     = '';
      if ( method_exists( $this, 'build_quote_pdf' ) ) {
        $quote_signed = $this->get_quote( $quote_id ) ?: $quote;
        $row_pdf = $this->build_quote_row_from_record( $quote_signed );
        $row_pdf['client_signed_date'] = mysql2date( 'd/m/Y', $signed_at );
        $pdf_bin = $this->build_quote_pdf( $row_pdf );
        if ( '' !== (string) $pdf_bin ) {
          $tmp_pdf = trailingslashit( sys_get_temp_dir() ) . 'devis-' . sanitize_file_name( $quote_num ) . '.pdf';
          if ( false !== file_put_contents( $tmp_pdf, $pdf_bin ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            $attachments[] = $tmp_pdf;
          } else {
            $tmp_pdf = '';
          }
        }
      }

      /* ACDC 3.25.161 — Attribution d'archive : cet envoi client partait sans en-tête
         de module et s'affichait « plugin / wp_mail ». */
      $this->acdc_send_transactional_email(
        $client_email,
        $subj_client,
        $tpl_client,
        $attr_client,
        $attachments
      );

      if ( '' !== $tmp_pdf && file_exists( $tmp_pdf ) ) {
        @unlink( $tmp_pdf ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
      }
    }
  }

  /**
   * ACDC 3.25.225 — Crée la convention de formation à partir d'un devis signé.
   *
   * Trois précautions, et aucune n'est décorative :
   *   1. IDEMPOTENCE — un devis ne produit qu'une convention. Le module de
   *      signature peut rejouer son événement ; sans garde, chaque rejeu
   *      créerait un doublon contractuel.
   *   2. BROUILLON — la convention est préparée, jamais envoyée. Le statut de
   *      signature reste vide : c'est l'organisme qui décide de l'expédier.
   *   3. AUCUNE INVENTION — on ne recopie que ce que le devis porte. Un champ
   *      absent du devis reste vide dans la convention plutôt que d'être
   *      rempli d'un défaut qui aurait valeur contractuelle.
   *
   * @param object $quote Devis signé.
   * @return int Identifiant de la convention créée, 0 si aucune.
   */
  /**
   * ACDC 3.25.231 — Création de la convention à la main, depuis le devis signé.
   *
   * L'automatisme ne suffit pas : s'il n'a pas eu lieu — parce que le devis a
   * été signé sous une version antérieure, ou parce que l'écriture a échoué —
   * l'organisme se retrouvait sans convention et sans moyen de la reprendre
   * autrement qu'en ressaisissant tout. Un automatisme sans rattrapage est une
   * impasse dès qu'il rate une fois.
   */
  public function handle_create_contract_from_quote() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( 'Action non autorisée.' );
    }
    $quote_id = isset( $_POST['quote_id'] ) ? absint( wp_unslash( $_POST['quote_id'] ) ) : 0;
    check_admin_referer( 'acdc_create_contract_from_quote_' . $quote_id );

    $quote = $quote_id ? $this->get_quote( $quote_id ) : null;
    if ( ! $quote ) {
      $this->redirect_to_portal( 'quotes', 'Devis introuvable.', 'error' );
    }

    /* On repart d'une garde neuve : c'est une demande explicite. */
    delete_option( 'acdc_of_quote_contract_' . $quote_id );

    $contract_id = $this->maybe_create_contract_from_signed_quote( $quote );

    if ( $contract_id <= 0 ) {
      $this->redirect_to_portal(
        'quotes',
        'La convention n’a pas pu être créée depuis ce devis. La raison est journalisée dans le système.',
        'error'
      );
    }

    $this->redirect_to_portal(
      'registration_contract',
      'Convention n°' . (int) $contract_id . ' créée en brouillon depuis le devis. Relisez-la avant de l’envoyer en signature.',
      'success',
      array( 'action' => 'edit', 'item_id' => (int) $contract_id )
    );
  }

  private function maybe_create_contract_from_signed_quote( $quote ) {
    global $wpdb;

    if ( empty( $quote->id ) ) {
      return 0;
    }

    $quote_id = (int) $quote->id;
    $flag_key = 'acdc_of_quote_contract_' . $quote_id;

    /* Garde d'idempotence : posée AVANT le travail. Perdre une création est
       rattrapable à la main, en créer deux ne l'est pas. */
    if ( ! add_option( $flag_key, '1', '', false ) ) {
      return 0;
    }

    $title = 'Convention — ' . trim( (string) ( $quote->client_company ?: $quote->apprenant_name ) );
    if ( ! empty( $quote->formation_title ) ) {
      $title .= ' — ' . (string) $quote->formation_title;
    }

    /* ACDC 3.25.226 — Trois manques relevés en recette sur cette reprise.
       1. La formation n'était pas sélectionnée quand le devis ne portait que
          son TITRE : on la retrouve par le titre avant de renoncer.
       2. Le commanditaire n'était pas nommé : la convention affichait
          « Entreprise — » sans raison sociale. On reporte le libellé, et l'on
          rattache l'entreprise par son nom quand l'identifiant manque.
       3. Les frais annexes sortaient ACTIVÉS À ZÉRO EURO. Une case cochée sans
          montant n'est pas un frais, c'est une case cochée : on n'active que
          ce qui porte une somme. */
    $formation_id = ! empty( $quote->formation_id ) ? (int) $quote->formation_id : 0;
    if ( $formation_id <= 0 && ! empty( $quote->formation_title ) ) {
      $formation_id = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$this->formation_table} WHERE title = %s ORDER BY id DESC LIMIT 1",
        (string) $quote->formation_title
      ) );
    }

    $company_id = ! empty( $quote->company_id ) ? (int) $quote->company_id : 0;
    if ( $company_id <= 0 && ! empty( $quote->client_company ) ) {
      $company_id = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$this->company_table} WHERE name = %s ORDER BY id DESC LIMIT 1",
        (string) $quote->client_company
      ) );
    }

    $transport_amount = (float) str_replace( ',', '.', $this->normalize_price_number( $quote->transport_fees_ht ?? 0, 2 ) );
    $meal_amount      = (float) str_replace( ',', '.', $this->normalize_price_number( $quote->meal_fees_ht ?? 0, 2 ) );

    $now  = current_time( 'mysql' );
    $data = array(
      'title'              => sanitize_text_field( $title ),
      'generate_mode'      => 'devis_signe',
      /* ACDC 3.25.258 — Le devis d'origine. Sans lui, la facture ne sait pas
         quelle convention consulter pour connaître son destinataire. */
      'quote_id'           => $quote_id,
      'commanditaire_type' => ! empty( $quote->client_company ) ? 'Entreprise' : sanitize_text_field( (string) ( $quote->commanditaire_type ?? 'Particulier' ) ),
      'source_prospect_id' => ! empty( $quote->source_prospect_id ) ? (int) $quote->source_prospect_id : null,
      'company_id'         => $company_id > 0 ? $company_id : null,
      'formation_id'       => $formation_id > 0 ? $formation_id : null,
      'formation_title'    => sanitize_text_field( (string) ( $quote->formation_title ?? '' ) ),
      'start_date'         => ! empty( $quote->start_date ) ? substr( (string) $quote->start_date, 0, 10 ) : null,
      'end_date'           => ! empty( $quote->end_date ) ? substr( (string) $quote->end_date, 0, 10 ) : null,
      'price_ht'           => (string) ( $quote->tarif_ht ?? '' ),
      'vat_rate'           => (string) ( $quote->vat_rate ?? '' ),
      'objectives_text'    => (string) ( $quote->objectives ?? '' ),
      'transport_fees_enabled'   => ( ! empty( $quote->transport_fees_enabled ) && $transport_amount > 0 ) ? 1 : 0,
      'transport_fees_amount_ht' => $transport_amount > 0 ? (string) $transport_amount : '',
      'meal_fees_enabled'        => ( ! empty( $quote->meal_fees_enabled ) && $meal_amount > 0 ) ? 1 : 0,
      'meal_fees_amount_ht'      => $meal_amount > 0 ? (string) $meal_amount : '',
      'payment_terms'      => (string) ( $quote->payment_methods ?? '' ),
      'signature_status'   => '',
      'created_at'         => $now,
      'updated_at'         => $now,
    );

    $inserted = $wpdb->insert( $this->registration_contract_table, $data );
    if ( ! $inserted ) {
      /* ACDC 3.25.231 — UN ÉCHEC SILENCIEUX EST PIRE QU'UN ÉCHEC BRUYANT.
         La recette a constaté qu'aucune convention n'était créée à la signature
         du devis, sans qu'aucun écran ni aucun journal n'en dise la raison :
         la fonction rendait 0 et repartait. On ne pouvait donc pas distinguer
         « le code n'a pas été appelé » de « l'écriture a échoué », deux pannes
         qui ne se corrigent pas au même endroit.
         L'erreur de base de données est désormais journalisée nommément. */
      if ( method_exists( $this, 'log_error' ) ) {
        $this->log_error( 'documents-billing', 'Convention non créée depuis le devis signé : échec d’écriture.', array(
          'quote_id'  => $quote_id,
          'db_error'  => (string) $wpdb->last_error,
        ) );
      }
      /* La garde est levée : un échec d'écriture ne doit pas interdire la
         prochaine tentative. */
      delete_option( $flag_key );
      return 0;
    }

    $contract_id = (int) $wpdb->insert_id;

    if ( method_exists( $this, 'log_action_event' ) ) {
      $this->log_action_event( 'create', 'registration_contract', $contract_id, 'success', array(
        'origine'  => 'devis signé n°' . (string) ( $quote->number ?? $quote_id ),
        'quote_id' => $quote_id,
      ) );
    }

    return $contract_id;
  }
}

