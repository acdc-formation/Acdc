<?php
/**
 * ACDC Learners / Contacts — ACDC_Learners_Contacts_Actions_Trait.
 *
 * Extraction micro-incrémentale du sous-bloc métier :
 * apprenants + contacts liés.
 *
 * @since 3.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Learners_Contacts_Actions_Trait {


  public function handle_save_contact() {
    $this->require_admin_manager_nonce( 'acdc_save_contact' );

    global $wpdb;
    $contact_id = isset( $_POST['contact_id'] ) ? absint( wp_unslash( $_POST['contact_id'] ) ) : 0;
    $form_input = array(
      'company_id' => isset( $_POST['company_id'] ) ? wp_unslash( $_POST['company_id'] ) : '',
      'first_name' => isset( $_POST['first_name'] ) ? wp_unslash( $_POST['first_name'] ) : '',
      'last_name'  => isset( $_POST['last_name'] ) ? wp_unslash( $_POST['last_name'] ) : '',
      'job_title'  => isset( $_POST['job_title'] ) ? wp_unslash( $_POST['job_title'] ) : '',
      'email'      => isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '',
      'phone'      => isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '',
      'notes'      => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
    );
    $first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
    $last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';

    if ( '' === $first_name || '' === $last_name ) {
      $required_fields = array();
      if ( '' === $first_name ) { $required_fields[] = 'first_name'; }
      if ( '' === $last_name ) { $required_fields[] = 'last_name'; }
      $this->acdc_store_form_state( 'contact', $form_input, $required_fields );
      $this->redirect_to_portal( 'contacts', 'Le prénom et le nom sont obligatoires.', 'error', array( 'action' => $contact_id ? 'edit' : 'new', 'item_id' => $contact_id ) );
    }

    $data = array(
      'company_id' => isset( $_POST['company_id'] ) ? absint( wp_unslash( $_POST['company_id'] ) ) : 0,
      'first_name' => $first_name,
      'last_name' => $last_name,
      'job_title' => isset( $_POST['job_title'] ) ? sanitize_text_field( wp_unslash( $_POST['job_title'] ) ) : '',
      'email'   => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
      'phone'   => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
      'notes'   => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
      'updated_at' => $this->now_mysql(),
    );

    if ( empty( $data['company_id'] ) ) {
      $data['company_id'] = null;
    }
    $linked_company_id = ! empty( $data['company_id'] ) ? (int) $data['company_id'] : 0;

    if ( $contact_id ) {
      $result = $wpdb->update( $this->contact_table, $data, array( 'id' => $contact_id ) );
      if ( false === $result ) {
        $this->acdc_store_form_state( 'contact', $form_input );
        $this->redirect_to_portal( 'contacts', 'Erreur lors de la mise à jour. Veuillez réessayer.', 'error', array( 'action' => 'edit', 'item_id' => $contact_id ) );
      }
      $message = 'Contact mis à jour.';
    } else {
      $data['created_at'] = $this->now_mysql();
      $result = $wpdb->insert( $this->contact_table, $data );
      if ( false === $result ) {
        $this->acdc_store_form_state( 'contact', $form_input );
        $this->redirect_to_portal( 'contacts', 'Erreur lors de l\'enregistrement. Veuillez réessayer.', 'error', array( 'action' => 'new' ) );
      }
      $message = 'Contact enregistré.';
    }

    if ( $linked_company_id ) {
      $this->redirect_to_portal( 'companies', $message, 'success', array( 'action' => 'view', 'item_id' => $linked_company_id ) );
    }
    $this->redirect_to_portal( 'contacts', $message, 'success' );
  }


  public function handle_delete_contact() {
    $this->require_admin_manager_access();

    $contact_id = isset( $_GET['contact_id'] ) ? absint( wp_unslash( $_GET['contact_id'] ) ) : 0;
    if ( ! $contact_id ) {
      $this->redirect_to_portal( 'contacts', 'Contact introuvable.', 'error' );
    }

    $this->verify_nonce_or_die( 'acdc_delete_contact_' . $contact_id );

    global $wpdb;
    $document_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->document_table} WHERE contact_id = %d", $contact_id ) );
    if ( $document_count ) {
      $this->redirect_to_portal( 'contacts', 'Suppression impossible : ce contact est lié à des documents.', 'error' );
    }

    $__acdc_supprime = $wpdb->delete( $this->contact_table, array( 'id' => $contact_id ) );
    /* ACDC 3.25.300 — La trace est posée sur la suppression, pas sur la
       redirection : une page qui annonce « supprimé » ne prouve pas qu'une
       ligne l'ait été. Le résultat écrit ici est celui de la base. */
    $this->log_action_event( 'suppression_contact', 'contact', (int) $contact_id, $__acdc_supprime ? 'success' : 'error', array( 'lignes' => (int) $__acdc_supprime ) );
    $this->redirect_to_portal( 'contacts', 'Contact supprimé.', 'success' );
  }


  public function handle_save_learner() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    check_admin_referer( 'acdc_save_learner' );

    $learner_id = isset( $_POST['learner_id'] ) ? absint( wp_unslash( $_POST['learner_id'] ) ) : 0;
    $input = isset( $_POST['learner'] ) && is_array( $_POST['learner'] ) ? wp_unslash( $_POST['learner'] ) : array();
    $first_name = isset( $input['first_name'] ) ? sanitize_text_field( $input['first_name'] ) : '';
    $usage_last_name = isset( $input['usage_last_name'] ) ? sanitize_text_field( $input['usage_last_name'] ) : '';
    if ( '' === $first_name || '' === $usage_last_name ) {
      $required_fields = array();
      if ( '' === $first_name ) { $required_fields[] = 'first_name'; }
      if ( '' === $usage_last_name ) { $required_fields[] = 'usage_last_name'; }
      $this->acdc_store_form_state( 'learner', $input, $required_fields );
      if ( is_admin() && isset( $_POST['page'] ) && 'acdc-of-learners' === $_POST['page'] ) {
        $this->redirect_to_admin_page( 'acdc-of-learners', 'Le prénom et le nom d’usage sont obligatoires.', 'error', array( 'action' => $learner_id ? 'edit' : 'new', 'item_id' => $learner_id ) );
      }
      $this->redirect_to_portal( 'learners', 'Le prénom et le nom d’usage sont obligatoires.', 'error', array( 'action' => $learner_id ? 'edit' : 'new', 'item_id' => $learner_id ) );
    }

    $data = array(
      'company_id'      => isset( $input['company_id'] ) ? absint( $input['company_id'] ) : 0,
      'session_id'      => isset( $input['session_id'] ) ? absint( $input['session_id'] ) : 0,
      'prospect_id'     => isset( $input['prospect_id'] ) ? absint( $input['prospect_id'] ) : 0,
      'gender'        => isset( $input['gender'] ) ? sanitize_text_field( $input['gender'] ) : '',
      'first_name'      => $first_name,
      'last_name'      => $usage_last_name,
      'usage_last_name'   => $usage_last_name,
      'birth_last_name'   => isset( $input['birth_last_name'] ) ? sanitize_text_field( $input['birth_last_name'] ) : '',
      'email'        => isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '',
      'phone'        => isset( $input['phone'] ) ? sanitize_text_field( $input['phone'] ) : '',
      'birth_date'      => isset( $input['birth_date'] ) ? sanitize_text_field( $input['birth_date'] ) : null,
      'birth_place'     => isset( $input['birth_place'] ) ? sanitize_text_field( $input['birth_place'] ) : '',
      'address'       => isset( $input['address'] ) ? sanitize_text_field( $input['address'] ) : '',
      'address_extra'    => isset( $input['address_extra'] ) ? sanitize_text_field( $input['address_extra'] ) : '',
      'postal_code'     => isset( $input['postal_code'] ) ? sanitize_text_field( $input['postal_code'] ) : '',
      'city'         => isset( $input['city'] ) ? sanitize_text_field( $input['city'] ) : '',
      'is_france_travail'  => isset( $input['is_france_travail'] ) ? sanitize_text_field( $input['is_france_travail'] ) : '',
      'socio_category'    => isset( $input['socio_category'] ) ? sanitize_text_field( $input['socio_category'] ) : '',
      'education_level'   => isset( $input['education_level'] ) ? sanitize_text_field( $input['education_level'] ) : '',
      'job_title'      => isset( $input['job_title'] ) ? sanitize_text_field( $input['job_title'] ) : '',
      'contact_alert_at'   => isset( $input['contact_alert_at'] ) ? $this->sanitize_datetime_input( $input['contact_alert_at'] ) : null,
      'accessibility_needs' => isset( $input['accessibility_needs'] ) ? sanitize_textarea_field( $input['accessibility_needs'] ) : '',
      'comment_text'     => isset( $input['comment_text'] ) ? sanitize_textarea_field( $input['comment_text'] ) : '',
      'status'        => isset( $input['status'] ) ? sanitize_text_field( $input['status'] ) : 'Pré-inscrit',
      'funding'       => isset( $input['funding'] ) ? sanitize_text_field( $input['funding'] ) : '',
      'notes'        => isset( $input['comment_text'] ) ? sanitize_textarea_field( $input['comment_text'] ) : '',
    );
    $data['company_id'] = $data['company_id'] ?: null;
    $data['session_id'] = $data['session_id'] ?: null;
    $data['prospect_id'] = $data['prospect_id'] ?: null;
    $data['birth_date'] = $data['birth_date'] ?: null;

    $this->ensure_storage_ready();
    global $wpdb;
    $now = current_time( 'mysql' );
    if ( $learner_id ) {
      $data['updated_at'] = $now;
      $result = $wpdb->update( $this->learner_table, $data, array( 'id' => $learner_id ) );
      $message = 'Apprenant mis à jour.';
      $error_context = 'Impossible de mettre à jour l’apprenant.';
    } else {
      $data['created_at'] = $now;
      $data['updated_at'] = $now;
      $result = $wpdb->insert( $this->learner_table, $data );
      $message = 'Apprenant créé.';
      $error_context = 'Impossible de créer l’apprenant.';
    }

    if ( false === $result ) {
      $this->acdc_store_form_state( 'learner', $input );
    }

    $this->redirect_with_db_result(
      $result,
      $message,
      $error_context,
      'learners',
      ( is_admin() && isset( $_POST['page'] ) && 'acdc-of-learners' === $_POST['page'] ),
      'acdc-of-learners'
    );
  }


  public function handle_delete_learner() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $learner_id = isset( $_GET['learner_id'] ) ? absint( wp_unslash( $_GET['learner_id'] ) ) : 0;
    if ( ! $learner_id ) {
      $this->redirect_to_portal( 'learners', 'Apprenant introuvable.', 'error' );
    }
    check_admin_referer( 'acdc_delete_learner_' . $learner_id );
    global $wpdb;
    $__acdc_supprime = $wpdb->delete( $this->learner_table, array( 'id' => $learner_id ) );
    /* ACDC 3.25.300 — La trace est posée sur la suppression, pas sur la
       redirection : une page qui annonce « supprimé » ne prouve pas qu'une
       ligne l'ait été. Le résultat écrit ici est celui de la base. */
    $this->log_action_event( 'suppression_learner', 'learner', (int) $learner_id, $__acdc_supprime ? 'success' : 'error', array( 'lignes' => (int) $__acdc_supprime ) );
    if ( is_admin() && isset( $_GET['page'] ) && 'acdc-of-learners' === $_GET['page'] ) {
      $this->redirect_to_admin_page( 'acdc-of-learners', 'Apprenant supprimé.', 'success' );
    }
    $this->redirect_to_portal( 'learners', 'Apprenant supprimé.', 'success' );
  }

  /* ====================================================================
   *  ACDC 3.24.33 — Envoi d’e-mail direct depuis une fiche (prospect / apprenant)
   * ==================================================================== */

  public function handle_send_direct_email() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( 'Accès refusé.' ) );
    }
    $entity_type = isset( $_POST['entity_type'] ) ? sanitize_key( wp_unslash( $_POST['entity_type'] ) ) : '';
    $entity_id   = isset( $_POST['entity_id'] )   ? absint( $_POST['entity_id'] )   : 0;

    $nonce_action = 'acdc_send_direct_email_' . $entity_type . '_' . $entity_id;
    $nonce_value  = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce_value, $nonce_action ) ) {
      wp_die( 'ACDC : jeton de sécurité invalide. Veuillez recharger la page et réessayer.' );
    }

    $to_email    = isset( $_POST['to_email'] )    ? sanitize_email( wp_unslash( $_POST['to_email'] ) )      : '';
    $cc_raw      = isset( $_POST['cc_email'] )    ? sanitize_text_field( wp_unslash( $_POST['cc_email'] ) ) : '';
    $subject     = isset( $_POST['subject'] )     ? sanitize_text_field( wp_unslash( $_POST['subject'] ) )  : '';
    $body_html   = isset( $_POST['body_html'] )   ? wp_kses_post( wp_unslash( $_POST['body_html'] ) )        : '';
    $sig_id      = isset( $_POST['signature_id'] )? sanitize_key( wp_unslash( $_POST['signature_id'] ) )    : '';
    $redirect_tab    = isset( $_POST['redirect_tab'] )     ? sanitize_key( wp_unslash( $_POST['redirect_tab'] ) )     : $entity_type . 's';
    $redirect_action = isset( $_POST['redirect_action'] )  ? sanitize_key( wp_unslash( $_POST['redirect_action'] ) )  : 'view';
    $redirect_item   = isset( $_POST['redirect_item_id'] ) ? absint( $_POST['redirect_item_id'] )                      : $entity_id;

    if ( ! $to_email || ! is_email( $to_email ) ) {
      $this->redirect_to_portal( $redirect_tab, 'Adresse e-mail destinataire invalide.', 'error', array( 'action' => $redirect_action, 'item_id' => $redirect_item ) );
    }
    if ( '' === trim( strip_tags( $body_html ) ) ) {
      $this->redirect_to_portal( $redirect_tab, 'Le corps du message est vide.', 'error', array( 'action' => $redirect_action, 'item_id' => $redirect_item ) );
    }

    // Récupérer la signature sélectionnée
    $signatures = method_exists( $this, 'get_marketing_store' ) ? $this->get_marketing_store( 'signatures', array() ) : array();
    $sig = null;
    foreach ( $signatures as $s ) {
      if ( isset( $s['id'] ) && $s['id'] === $sig_id ) { $sig = $s; break; }
    }
    // Fallback : signature par défaut
    if ( ! $sig ) {
      foreach ( $signatures as $s ) { if ( ! empty( $s['is_default'] ) ) { $sig = $s; break; } }
    }
    if ( ! $sig && ! empty( $signatures ) ) { $sig = $signatures[0]; }

    // Contexte pour la résolution des variables {{...}}
    $var_context = $this->build_direct_email_context( $entity_type, $entity_id );
    if ( function_exists( 'acdc_replace_variables' ) ) {
      $subject   = acdc_replace_variables( $subject,   $var_context );
      $body_html = acdc_replace_variables( $body_html, $var_context );
    }

    // Pièces jointes
    $attachments = array();
    if ( ! empty( $_FILES['attachments']['tmp_name'] ) ) {
      $files    = $_FILES['attachments'];
      $updir    = wp_upload_dir();
      $tmp_dir  = trailingslashit( $updir['basedir'] ) . 'acdc-mail-attachments/';
      if ( ! file_exists( $tmp_dir ) ) { wp_mkdir_p( $tmp_dir ); }
      $allowed  = array( 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip' );
      foreach ( (array) $files['tmp_name'] as $i => $tmp ) {
        if ( empty( $tmp ) || ! is_uploaded_file( $tmp ) ) { continue; }
        $orig  = isset( $files['name'][ $i ] ) ? sanitize_file_name( $files['name'][ $i ] ) : 'piece-jointe';
        $ext   = strtolower( pathinfo( $orig, PATHINFO_EXTENSION ) );
        $size  = isset( $files['size'][ $i ] ) ? (int) $files['size'][ $i ] : 0;
        if ( ! in_array( $ext, $allowed, true ) || $size > 10485760 ) { continue; }
        $dest  = $tmp_dir . uniqid( 'mail_' ) . '_' . $orig;
        if ( move_uploaded_file( $tmp, $dest ) ) { $attachments[] = $dest; }
      }
    }

    // HTML final — la signature est déjà dans $body_html (injectée par JS dans l'éditeur)
    $full_html  = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#222;max-width:680px;margin:0 auto;">';
    $full_html .= $body_html;
    $full_html .= '</div>';

    // Headers
    $headers = array( 'Content-Type: text/html; charset=UTF-8' );
    if ( $sig ) {
      $from_name  = ! empty( $sig['display_name'] ) ? $sig['display_name'] : 'ACDC Formation';
      $from_email = ! empty( $sig['email'] )        ? $sig['email']        : '';
      if ( $from_email ) { $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>'; }
      if ( $from_email ) { $headers[] = 'Reply-To: ' . $from_email; }
    }
    if ( $cc_raw && is_email( sanitize_email( $cc_raw ) ) ) {
      $headers[] = 'Cc: ' . sanitize_email( $cc_raw );
    }
    // Headers ACDC pour l’archivage automatique et le lien à la fiche
    $headers[] = 'X-ACDC-Source-Module: direct_email';
    $headers[] = 'X-ACDC-Source-Action: send_direct_email';
    $headers[] = 'X-ACDC-Related-Entity-Type: ' . $entity_type;
    $headers[] = 'X-ACDC-Related-Entity-Id: ' . $entity_id;
    $headers[] = 'X-ACDC-Email-Category: direct_message';

    /* ACDC 3.25.246 — Enveloppe commune. Ce message est composé librement dans
       l'interface : son corps est conservé tel quel, seule l'enveloppe change,
       pour que le destinataire reçoive un courrier de la même maison que les
       autres. L'expéditeur choisi (signature) et le Cc sont repassés en
       en-têtes supplémentaires : wp_mail retient le dernier From rencontré, ils
       l'emportent donc sur celui du gabarit. */
    $extra_from = array_values( array_filter( $headers, static function ( $h ) {
      return 0 === strpos( (string) $h, 'From:' ) || 0 === strpos( (string) $h, 'Reply-To:' ) || 0 === strpos( (string) $h, 'Cc:' );
    } ) );
    $sent = $this->acdc_send_transactional_email(
      $to_email,
      $subject,
      array( 'body_html' => $full_html ),
      array(
        'source_module'       => 'direct_email',
        'source_action'       => 'send_direct_email',
        'related_entity_type' => (string) $entity_type,
        'related_entity_id'   => (string) $entity_id,
        'email_category'      => 'direct_message',
        'extra_headers'       => $extra_from,
      ),
      $attachments
    );
    foreach ( $attachments as $f ) { if ( file_exists( $f ) ) { @unlink( $f ); } }

    if ( $sent ) {
      $this->redirect_to_portal( $redirect_tab, 'E-mail envoyé avec succès.', 'success', array( 'action' => $redirect_action, 'item_id' => $redirect_item ) );
    } else {
      $this->redirect_to_portal( $redirect_tab, 'L’envoi a échoué. Vérifiez la configuration SMTP.', 'error', array( 'action' => $redirect_action, 'item_id' => $redirect_item ) );
    }
  }


  /* ACDC 3.24.48 — Handler AJAX pour envoi e-mail direct (wp_ajax — pas de problème de referer) */
  public function handle_send_direct_email_ajax() {
    // Vérification permission
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( array( 'message' => 'Accès refusé.' ), 403 );
    }
    $entity_type  = isset( $_POST['entity_type'] ) ? sanitize_key( wp_unslash( $_POST['entity_type'] ) ) : '';
    $entity_id    = isset( $_POST['entity_id'] )   ? absint( $_POST['entity_id'] )                       : 0;
    $nonce_action = 'acdc_send_direct_email_' . $entity_type . '_' . $entity_id;
    $nonce_value  = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce_value, $nonce_action ) ) {
      wp_send_json_error( array( 'message' => 'Jeton de sécurité invalide. Recharger la page et réessayer.' ), 403 );
    }

    $to_email    = isset( $_POST['to_email'] )     ? sanitize_email( wp_unslash( $_POST['to_email'] ) )       : '';
    $cc_raw      = isset( $_POST['cc_email'] )     ? sanitize_text_field( wp_unslash( $_POST['cc_email'] ) )  : '';
    $subject     = isset( $_POST['subject'] )      ? sanitize_text_field( wp_unslash( $_POST['subject'] ) )   : '';
    $body_html   = isset( $_POST['body_html'] )    ? wp_kses_post( wp_unslash( $_POST['body_html'] ) )         : '';
    $sig_id      = isset( $_POST['signature_id'] ) ? sanitize_key( wp_unslash( $_POST['signature_id'] ) )     : '';
    $redirect_tab    = isset( $_POST['redirect_tab'] )     ? sanitize_key( wp_unslash( $_POST['redirect_tab'] ) )     : $entity_type . 's';
    $redirect_action = isset( $_POST['redirect_action'] )  ? sanitize_key( wp_unslash( $_POST['redirect_action'] ) )  : 'view';
    $redirect_item   = isset( $_POST['redirect_item_id'] ) ? absint( $_POST['redirect_item_id'] )                      : $entity_id;

    if ( ! $to_email || ! is_email( $to_email ) ) {
      wp_send_json_error( array( 'message' => 'Adresse e-mail destinataire invalide.' ) );
    }
    if ( '' === trim( strip_tags( $body_html ) ) ) {
      wp_send_json_error( array( 'message' => 'Le corps du message est vide.' ) );
    }

    // Signature
    $signatures = method_exists( $this, 'get_marketing_store' ) ? $this->get_marketing_store( 'signatures', array() ) : array();
    $sig = null;
    foreach ( $signatures as $s ) { if ( isset( $s['id'] ) && $s['id'] === $sig_id ) { $sig = $s; break; } }
    if ( ! $sig ) { foreach ( $signatures as $s ) { if ( ! empty( $s['is_default'] ) ) { $sig = $s; break; } } }
    if ( ! $sig && ! empty( $signatures ) ) { $sig = $signatures[0]; }

    // Variables
    $var_context = $this->build_direct_email_context( $entity_type, $entity_id );
    if ( function_exists( 'acdc_replace_variables' ) ) {
      $subject   = acdc_replace_variables( $subject,   $var_context );
      $body_html = acdc_replace_variables( $body_html, $var_context );
    }

    // Pièces jointes
    $attachments = array();
    if ( ! empty( $_FILES['attachments']['tmp_name'] ) ) {
      $files   = $_FILES['attachments'];
      $updir   = wp_upload_dir();
      $tmp_dir = trailingslashit( $updir['basedir'] ) . 'acdc-mail-attachments/';
      if ( ! file_exists( $tmp_dir ) ) { wp_mkdir_p( $tmp_dir ); }
      $allowed = array( 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip' );
      foreach ( (array) $files['tmp_name'] as $i => $tmp ) {
        if ( empty( $tmp ) || ! is_uploaded_file( $tmp ) ) { continue; }
        $orig = isset( $files['name'][ $i ] ) ? sanitize_file_name( $files['name'][ $i ] ) : 'piece-jointe';
        $ext  = strtolower( pathinfo( $orig, PATHINFO_EXTENSION ) );
        $size = isset( $files['size'][ $i ] ) ? (int) $files['size'][ $i ] : 0;
        if ( ! in_array( $ext, $allowed, true ) || $size > 10485760 ) { continue; }
        $dest = $tmp_dir . uniqid( 'mail_' ) . '_' . $orig;
        if ( move_uploaded_file( $tmp, $dest ) ) { $attachments[] = $dest; }
      }
    }

    // HTML final — la signature est déjà dans $body_html (injectée par JS dans l'éditeur)
    $full_html  = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#222;max-width:680px;margin:0 auto;">';
    $full_html .= $body_html;
    $full_html .= '</div>';

    // Headers
    $headers = array( 'Content-Type: text/html; charset=UTF-8' );
    if ( $sig ) {
      $from_name  = ! empty( $sig['display_name'] ) ? $sig['display_name'] : 'ACDC Formation';
      $from_email = ! empty( $sig['email'] )        ? $sig['email']        : '';
      if ( $from_email ) { $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>'; }
      if ( $from_email ) { $headers[] = 'Reply-To: ' . $from_email; }
    }
    if ( $cc_raw && is_email( sanitize_email( $cc_raw ) ) ) { $headers[] = 'Cc: ' . sanitize_email( $cc_raw ); }
    $headers[] = 'X-ACDC-Source-Module: direct_email';
    $headers[] = 'X-ACDC-Source-Action: send_direct_email';
    $headers[] = 'X-ACDC-Related-Entity-Type: ' . $entity_type;
    $headers[] = 'X-ACDC-Related-Entity-Id: ' . $entity_id;
    $headers[] = 'X-ACDC-Email-Category: direct_message';

    /* ACDC 3.25.246 — Enveloppe commune. Ce message est composé librement dans
       l'interface : son corps est conservé tel quel, seule l'enveloppe change,
       pour que le destinataire reçoive un courrier de la même maison que les
       autres. L'expéditeur choisi (signature) et le Cc sont repassés en
       en-têtes supplémentaires : wp_mail retient le dernier From rencontré, ils
       l'emportent donc sur celui du gabarit. */
    $extra_from = array_values( array_filter( $headers, static function ( $h ) {
      return 0 === strpos( (string) $h, 'From:' ) || 0 === strpos( (string) $h, 'Reply-To:' ) || 0 === strpos( (string) $h, 'Cc:' );
    } ) );
    $sent = $this->acdc_send_transactional_email(
      $to_email,
      $subject,
      array( 'body_html' => $full_html ),
      array(
        'source_module'       => 'direct_email',
        'source_action'       => 'send_direct_email',
        'related_entity_type' => (string) $entity_type,
        'related_entity_id'   => (string) $entity_id,
        'email_category'      => 'direct_message',
        'extra_headers'       => $extra_from,
      ),
      $attachments
    );
    foreach ( $attachments as $f ) { if ( file_exists( $f ) ) { @unlink( $f ); } }

    if ( $sent ) {
      $redirect_url = $this->portal_page_url( array( 'tab' => $redirect_tab, 'action' => $redirect_action, 'item_id' => $redirect_item, '_acdc_notice' => 'E-mail envoyé avec succès.', '_acdc_notice_type' => 'success' ) );
      wp_send_json_success( array( 'redirect' => $redirect_url ) );
    } else {
      wp_send_json_error( array( 'message' => 'Envoi échoué. Vérifiez la configuration SMTP.' ) );
    }
  }

  /* ACDC 3.24.43 — Intercepteur template_redirect pour envoi e-mail depuis le front (conservé pour admin) */
  public function maybe_handle_front_direct_email() {
    if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) { return; }
    if ( empty( $_POST['acdc_front_action'] ) || 'send_direct_email' !== $_POST['acdc_front_action'] ) { return; }
    $this->handle_send_direct_email();
  }

}
