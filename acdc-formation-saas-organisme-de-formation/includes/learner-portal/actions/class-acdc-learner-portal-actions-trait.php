<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Learner_Portal_Actions_Trait {

  public function handle_learner_portal_login() {
    $nonce = isset( $_POST['_wpnonce'] ) ? wp_unslash( $_POST['_wpnonce'] ) : '';
    if ( ! wp_verify_nonce( $nonce, 'acdc_learner_login' ) ) {
      $this->learner_portal_redirect( 'login', 'Jeton de sécurité invalide.', 'error' );
    }

    $email    = isset( $_POST['learner_email'] ) ? sanitize_email( wp_unslash( $_POST['learner_email'] ) ) : '';
    $password = isset( $_POST['learner_password'] ) ? (string) wp_unslash( $_POST['learner_password'] ) : '';

    $this->learner_portal_sync_accounts();

    $account = $this->learner_portal_get_account_by_email( $email );
    if ( ! $account ) {
      $this->learner_portal_redirect( 'login', 'Connexion impossible. Vérifiez vos identifiants.', 'error' );
    }

    if ( 'never_activated' === $account->status ) {
      $this->learner_portal_log_event( $account->id, 'activation_required_login_refused' );
      $this->learner_portal_redirect( 'login', 'Votre accès doit d’abord être activé depuis le lien reçu par e-mail.', 'error' );
    }

    $now_ts = current_time( 'timestamp' );
    $blocked_until = $this->learner_portal_parse_mysql_time( $account->blocked_until );
    if ( $blocked_until && $blocked_until > $now_ts ) {
      $minutes = max( 1, (int) ceil( ( $blocked_until - $now_ts ) / 60 ) );
      $this->learner_portal_log_event( $account->id, 'login_blocked_attempt', array( 'remaining_minutes' => $minutes ) );
      $this->learner_portal_redirect( 'login', 'Compte temporairement bloqué. Temps restant : ' . $minutes . ' minute(s). Contact : ' . $this->learner_portal_contact_email(), 'error' );
    }

    if ( in_array( $account->status, array( 'disabled', 'expired' ), true ) ) {
      $this->learner_portal_log_event( $account->id, 'access_refused_expired' );
      $view = 'expired';
      $message = 'Votre accès extranet n’est plus actif.';
      $this->learner_portal_redirect( 'login', $message, 'error', array( 'view' => $view, 'email' => rawurlencode( $email ) ) );
    }

    if ( empty( $password ) || ! wp_check_password( $password, $account->password_hash ) ) {
      global $wpdb;
      $failed_count = max( 0, absint( $account->failed_login_count ) ) + 1;
      $new_status   = $failed_count >= 5 ? 'blocked' : $account->status;
      $blocked_at   = $failed_count >= 5 ? wp_date( 'Y-m-d H:i:s', strtotime( '+30 minutes', $now_ts ) ) : null;
      $wpdb->update(
        $this->learner_portal_account_table,
        array(
          'failed_login_count' => $failed_count,
          'blocked_until'      => $blocked_at,
          'status'             => $new_status,
          'updated_at'         => $this->learner_portal_now_mysql(),
        ),
        array( 'id' => (int) $account->id ),
        array( '%d', '%s', '%s', '%s' ),
        array( '%d' )
      );
      $this->learner_portal_log_event( $account->id, 'login_failed', array( 'failed_login_count' => $failed_count ) );
      if ( $failed_count >= 5 ) {
        $this->learner_portal_log_event( $account->id, 'temporary_blocked', array( 'blocked_until' => $blocked_at ) );
        $this->learner_portal_notify_admin(
          'Blocage temporaire d’un accès extranet apprenant',
          '<p>Un accès apprenant a été bloqué temporairement après 5 échecs de connexion.</p>'
          . '<p><strong>E-mail :</strong> ' . esc_html( $account->email ) . '</p>'
          . '<p><strong>Blocage jusqu’au :</strong> ' . esc_html( $this->learner_portal_format_date( $blocked_at, true ) ) . '</p>'
        );
      }
      $this->learner_portal_redirect( 'login', $failed_count >= 5 ? 'Compte bloqué pendant 30 minutes après 5 échecs de connexion. Contact : ' . $this->learner_portal_contact_email() : 'Connexion impossible. Vérifiez vos identifiants.', 'error' );
    }

    global $wpdb;
    $new_status = ! empty( $account->must_change_password ) || in_array( $account->status, array( 'never_activated', 'password_to_change' ), true ) ? 'password_to_change' : 'active';

    $wpdb->update(
      $this->learner_portal_account_table,
      array(
        'failed_login_count' => 0,
        'blocked_until'      => null,
        'status'             => $new_status,
        'last_login_at'      => $this->learner_portal_now_mysql(),
        'updated_at'         => $this->learner_portal_now_mysql(),
      ),
      array( 'id' => (int) $account->id ),
      array( '%d', '%s', '%s', '%s', '%s' ),
      array( '%d' )
    );

    $this->learner_portal_create_session( $account->id );
    $this->learner_portal_log_event( $account->id, 'login_success' );

    if ( 'password_to_change' === $new_status ) {
      $this->learner_portal_redirect( 'profile', 'Veuillez définir votre mot de passe personnel avant de continuer.', 'success', array( 'panel' => 'password' ) );
    }

    $this->learner_portal_redirect( 'dashboard', 'Connexion réussie.', 'success' );
  }

  public function handle_learner_portal_logout() {
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'acdc_learner_logout' ) ) {
      $this->learner_portal_redirect( 'login', 'Jeton de sécurité invalide.', 'error' );
    }

    $account = $this->learner_portal_get_current_account();
    if ( ! $account ) {
      $this->learner_portal_redirect( 'login', 'Vous êtes déjà déconnecté.', 'success' );
    }

    $this->learner_portal_delete_active_sessions( $account->id );
    $this->learner_portal_clear_cookie();
    $this->learner_portal_log_event( $account->id, 'logout' );

    $this->learner_portal_redirect( 'login', 'Vous êtes déconnecté.', 'success' );
  }

  public function handle_learner_portal_activation() {
    $nonce = isset( $_POST['_wpnonce'] ) ? wp_unslash( $_POST['_wpnonce'] ) : '';
    if ( ! wp_verify_nonce( $nonce, 'acdc_learner_activate' ) ) {
      $this->learner_portal_redirect( 'login', 'Jeton de sécurité invalide.', 'error' );
    }

    $token      = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
    $password_1 = isset( $_POST['password_1'] ) ? (string) wp_unslash( $_POST['password_1'] ) : '';
    $password_2 = isset( $_POST['password_2'] ) ? (string) wp_unslash( $_POST['password_2'] ) : '';

    if ( '' === $token || '' === $password_1 || $password_1 !== $password_2 || strlen( $password_1 ) < 8 ) {
      $this->learner_portal_redirect( 'login', 'Veuillez saisir deux mots de passe identiques de 8 caractères minimum.', 'error', array( 'view' => 'activate', 'token' => rawurlencode( $token ) ) );
    }

    $token_row = $this->learner_portal_get_token_row( $token, 'activation' );
    if ( ! $token_row || $this->learner_portal_parse_mysql_time( $token_row->expires_at ) < current_time( 'timestamp' ) ) {
      $this->learner_portal_redirect( 'login', 'Lien d’activation invalide ou expiré.', 'error' );
    }

    global $wpdb;
    $account = $this->learner_portal_get_account( $token_row->account_id );
    if ( ! $account ) {
      $this->learner_portal_redirect( 'login', 'Compte introuvable.', 'error' );
    }

    $wpdb->update(
      $this->learner_portal_account_table,
      array(
        'password_hash'        => wp_hash_password( $password_1 ),
        'status'               => 'active',
        'must_change_password' => 0,
        'first_activated_at'   => $this->learner_portal_now_mysql(),
        'updated_at'           => $this->learner_portal_now_mysql(),
      ),
      array( 'id' => (int) $account->id ),
      array( '%s', '%s', '%d', '%s', '%s' ),
      array( '%d' )
    );

    $wpdb->update(
      $this->learner_portal_token_table,
      array( 'consumed_at' => $this->learner_portal_now_mysql() ),
      array( 'id' => (int) $token_row->id ),
      array( '%s' ),
      array( '%d' )
    );

    $this->learner_portal_create_session( $account->id );
    $this->learner_portal_log_event( $account->id, 'activation_completed' );
    // Prénom de l'administrateur pour la salutation
    $admin_email_for_notif = $this->learner_portal_admin_notification_email();
    $admin_wp_user         = get_user_by( 'email', $admin_email_for_notif );
    $admin_first_name      = ( $admin_wp_user && ! empty( $admin_wp_user->first_name ) )
      ? $admin_wp_user->first_name
      : 'administrateur';
    // Nom de l'apprenant qui vient d'activer
    $activated_profile    = $this->learner_portal_get_primary_learner_profile( $account->email );
    $activated_full_name  = $activated_profile
      ? trim( $activated_profile->first_name . ' ' . $activated_profile->usage_last_name )
      : '';
    $activated_name_html  = '' !== $activated_full_name
      ? '<strong>' . esc_html( $activated_full_name ) . '</strong>'
      : esc_html( $account->email );
    $this->learner_portal_notify_admin(
      'Première activation réussie d’un accès extranet apprenant',
      '<p>Un apprenant a activé avec succès son accès extranet.</p>'
      . '<p>' . $activated_name_html . '</p>'
      . '<p><strong>E-mail :</strong> ' . esc_html( $account->email ) . '</p>',
      $admin_first_name
    );
    $this->learner_portal_redirect( 'dashboard', 'Votre accès est activé.', 'success' );
  }

  public function handle_learner_portal_request_reset() {
    $nonce = isset( $_POST['_wpnonce'] ) ? wp_unslash( $_POST['_wpnonce'] ) : '';
    if ( ! wp_verify_nonce( $nonce, 'acdc_learner_request_reset' ) ) {
      $this->learner_portal_redirect( 'login', 'Jeton de sécurité invalide.', 'error' );
    }

    /* ACDC 3.25.291 — Cette demande n'était pas limitée : on pouvait la rejouer
       en boucle sur une adresse. */
    if ( $this->acdc_trop_de_tentatives( 'reinit_apprenant' ) ) {
      $this->learner_portal_redirect( 'login', 'Trop de demandes depuis ce réseau. Patientez un quart d’heure avant de réessayer.', 'error' );
    }

    $email   = isset( $_POST['learner_email'] ) ? sanitize_email( wp_unslash( $_POST['learner_email'] ) ) : '';
    $account = $this->learner_portal_get_account_by_email( $email );

    if ( $account && in_array( $account->status, array( 'expired', 'disabled' ), true ) ) {
      $this->learner_portal_log_event( $account->id, 'reset_refused_expired' );
      $this->learner_portal_redirect( 'login', 'Votre accès extranet est expiré ou désactivé. Utilisez le formulaire de réactivation.', 'error', array( 'view' => 'expired', 'email' => rawurlencode( $email ) ) );
    }

    if ( $account ) {
      $this->learner_portal_send_reset_email( $account );
      $this->learner_portal_log_event( $account->id, 'password_reset_requested' );
      $this->learner_portal_notify_admin(
        'Demande de réinitialisation du mot de passe apprenant',
        '<p>Une demande de réinitialisation du mot de passe apprenant a été enregistrée.</p>'
        . '<p><strong>E-mail :</strong> ' . esc_html( $account->email ) . '</p>'
      );
    }

    $this->learner_portal_redirect( 'login', 'Si un accès existe pour cette adresse, un lien de réinitialisation a été envoyé.', 'success' );
  }

  public function handle_learner_portal_reset_password() {
    $nonce = isset( $_POST['_wpnonce'] ) ? wp_unslash( $_POST['_wpnonce'] ) : '';
    if ( ! wp_verify_nonce( $nonce, 'acdc_learner_reset_password' ) ) {
      $this->learner_portal_redirect( 'login', 'Jeton de sécurité invalide.', 'error' );
    }

    $token      = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
    $password_1 = isset( $_POST['password_1'] ) ? (string) wp_unslash( $_POST['password_1'] ) : '';
    $password_2 = isset( $_POST['password_2'] ) ? (string) wp_unslash( $_POST['password_2'] ) : '';

    if ( '' === $token || '' === $password_1 || $password_1 !== $password_2 || strlen( $password_1 ) < 8 ) {
      $this->learner_portal_redirect( 'login', 'Veuillez saisir deux mots de passe identiques de 8 caractères minimum.', 'error', array( 'view' => 'reset', 'token' => rawurlencode( $token ) ) );
    }

    $token_row = $this->learner_portal_get_token_row( $token, 'reset' );
    if ( ! $token_row || $this->learner_portal_parse_mysql_time( $token_row->expires_at ) < current_time( 'timestamp' ) ) {
      $this->learner_portal_redirect( 'login', 'Lien de réinitialisation invalide ou expiré.', 'error' );
    }

    global $wpdb;
    $account = $this->learner_portal_get_account( $token_row->account_id );
    if ( ! $account ) {
      $this->learner_portal_redirect( 'login', 'Compte introuvable.', 'error' );
    }

    $wpdb->update(
      $this->learner_portal_account_table,
      array(
        'password_hash'        => wp_hash_password( $password_1 ),
        'status'               => 'active',
        'must_change_password' => 0,
        'updated_at'           => $this->learner_portal_now_mysql(),
      ),
      array( 'id' => (int) $account->id ),
      array( '%s', '%s', '%d', '%s' ),
      array( '%d' )
    );

    $wpdb->update(
      $this->learner_portal_token_table,
      array( 'consumed_at' => $this->learner_portal_now_mysql() ),
      array( 'id' => (int) $token_row->id ),
      array( '%s' ),
      array( '%d' )
    );

    $this->learner_portal_create_session( $account->id );
    $this->learner_portal_log_event( $account->id, 'password_reset_completed' );

    $this->learner_portal_redirect( 'dashboard', 'Votre mot de passe a été réinitialisé.', 'success' );
  }

  public function handle_learner_portal_change_password() {
    $account = $this->learner_portal_require_auth();
    check_admin_referer( 'acdc_learner_change_password' );

    $password_1 = isset( $_POST['password_1'] ) ? (string) wp_unslash( $_POST['password_1'] ) : '';
    $password_2 = isset( $_POST['password_2'] ) ? (string) wp_unslash( $_POST['password_2'] ) : '';

    if ( '' === $password_1 || $password_1 !== $password_2 || strlen( $password_1 ) < 8 ) {
      $this->learner_portal_redirect( 'profile', 'Veuillez saisir deux mots de passe identiques de 8 caractères minimum.', 'error', array( 'panel' => 'password' ) );
    }

    global $wpdb;
    $wpdb->update(
      $this->learner_portal_account_table,
      array(
        'password_hash'        => wp_hash_password( $password_1 ),
        'must_change_password' => 0,
        'status'               => 'active',
        'updated_at'           => $this->learner_portal_now_mysql(),
      ),
      array( 'id' => (int) $account->id ),
      array( '%s', '%d', '%s', '%s' ),
      array( '%d' )
    );

    $this->learner_portal_log_event( $account->id, 'password_changed' );
    $this->learner_portal_redirect( 'profile', 'Votre mot de passe a été mis à jour.', 'success' );
  }

  public function handle_learner_portal_update_profile() {
    $account = $this->learner_portal_require_auth();
    check_admin_referer( 'acdc_learner_update_profile' );

    $photo_url = $this->handle_optional_upload( 'portal_user_photo' );
    if ( '' === $photo_url ) {
      $photo_url = isset( $_POST['existing_photo_url'] ) ? esc_url_raw( wp_unslash( $_POST['existing_photo_url'] ) ) : '';
    }

    global $wpdb;
    $wpdb->update(
      $this->learner_portal_account_table,
      array(
        'photo_url'   => $photo_url,
        'updated_at'  => $this->learner_portal_now_mysql(),
      ),
      array( 'id' => (int) $account->id ),
      array( '%s', '%s' ),
      array( '%d' )
    );

    $this->learner_portal_log_event( $account->id, 'profile_updated' );
    $this->learner_portal_redirect( 'profile', 'Votre photo de profil a été mise à jour.', 'success' );
  }

  public function handle_learner_portal_download_document() {
    $account = $this->learner_portal_require_auth();

    $registration_id = isset( $_GET['registration_id'] ) ? absint( wp_unslash( $_GET['registration_id'] ) ) : 0;
    $document_type   = isset( $_GET['document_type'] ) ? sanitize_key( wp_unslash( $_GET['document_type'] ) ) : '';
    $doc_index       = isset( $_GET['doc_index'] ) ? absint( wp_unslash( $_GET['doc_index'] ) ) : 0;

    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'acdc_learner_download_document_' . $registration_id . '_' . $document_type . '_' . $doc_index ) ) {
      $this->learner_portal_redirect( 'documents', 'Jeton de téléchargement invalide.', 'error' );
    }

    $url = $this->learner_portal_get_document_source_url( $account->email, $registration_id, $document_type, $doc_index );
    if ( '' === $url ) {
      $this->learner_portal_redirect( 'documents', 'Document indisponible ou accès non autorisé.', 'error' );
    }

    $this->learner_portal_log_event( $account->id, 'document_downloaded', array(
      'registration_id' => $registration_id,
      'document_type'   => $document_type,
      'doc_index'       => $doc_index,
    ) );

    // Programme PDF généré à la volée — redirection directe (pas de streaming)
    if ( 'program' === $document_type && false !== strpos( $url, 'fm_action=programme_pdf' ) ) {
      wp_safe_redirect( $url );
      exit;
    }

    $this->learner_portal_stream_file( $url, $this->learner_portal_safe_file_label( $url ) );
  }


  public function handle_learner_portal_open_resource() {
    $account = $this->learner_portal_require_auth();

    $registration_id = isset( $_GET['registration_id'] ) ? absint( wp_unslash( $_GET['registration_id'] ) ) : 0;
    $resource_type   = isset( $_GET['resource_type'] ) ? sanitize_key( wp_unslash( $_GET['resource_type'] ) ) : '';
    $resource_index  = isset( $_GET['resource_index'] ) ? absint( wp_unslash( $_GET['resource_index'] ) ) : 0;

    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'acdc_learner_open_resource_' . $registration_id . '_' . $resource_type . '_' . $resource_index ) ) {
      $this->learner_portal_redirect( 'library', 'Jeton de sécurité invalide.', 'error' );
    }

    $url = $this->learner_portal_get_resource_source_url( $account->email, $registration_id, $resource_type, $resource_index );
    if ( '' === $url ) {
      $this->learner_portal_redirect( 'library', 'Ressource indisponible ou accès non autorisé.', 'error' );
    }

    $safe_url = esc_url_raw( $url, array( 'http', 'https' ) );
    if ( '' === $safe_url ) {
      $this->learner_portal_redirect( 'library', 'Ressource indisponible ou URL non autorisée.', 'error' );
    }

    $this->learner_portal_log_event( $account->id, 'resource_opened', array(
      'registration_id' => $registration_id,
      'resource_type'   => $resource_type,
      'resource_index'  => $resource_index,
    ) );

    wp_safe_redirect( $safe_url );
    exit;
  }

  public function handle_learner_portal_expired_contact() {
    $nonce = isset( $_POST['_wpnonce'] ) ? wp_unslash( $_POST['_wpnonce'] ) : '';
    if ( ! wp_verify_nonce( $nonce, 'acdc_learner_expired_contact' ) ) {
      $this->learner_portal_redirect( 'login', 'Jeton de sécurité invalide.', 'error' );
    }

    $email   = isset( $_POST['learner_email'] ) ? sanitize_email( wp_unslash( $_POST['learner_email'] ) ) : '';
    $message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
    $account = $this->learner_portal_get_account_by_email( $email );

    $body  = '<p>Demande de réactivation d’accès extranet apprenant.</p>';
    $body .= '<p><strong>E-mail :</strong> ' . esc_html( $email ) . '</p>';
    $body .= '<p><strong>Message :</strong><br>' . nl2br( esc_html( $message ) ) . '</p>';

    $this->learner_portal_send_email( get_option( 'admin_email' ), 'Demande de réactivation d’accès extranet', $body );
    if ( $account ) {
      $this->learner_portal_log_event( $account->id, 'expired_access_contact_sent', array( 'message' => $message ) );
    }

    $this->learner_portal_redirect( 'login', 'Votre demande a bien été envoyée.', 'success', array( 'view' => 'expired' ) );
  }

  public function handle_admin_learner_portal_sync() {
    $this->require_manage_options();
    check_admin_referer( 'acdc_admin_learner_portal_sync' );
    $this->learner_portal_sync_accounts( true );
    wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-learner-portal&notice=' . rawurlencode( 'Synchronisation des accès apprenants effectuée.' ) . '&notice_type=success' ) );
    exit;
  }

  public function handle_admin_learner_portal_status() {
    $this->require_manage_options();
    $account_id = isset( $_GET['account_id'] ) ? absint( wp_unslash( $_GET['account_id'] ) ) : 0;
    $do        = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
    check_admin_referer( 'acdc_admin_learner_portal_status_' . $account_id . '_' . $do );

    global $wpdb;
    $account = $this->learner_portal_get_account( $account_id );
    if ( ! $account ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-learner-portal&notice=' . rawurlencode( 'Compte introuvable.' ) . '&notice_type=error' ) );
      exit;
    }

    $message = 'Action effectuée.';
    switch ( $do ) {
      case 'disable':
        $wpdb->update( $this->learner_portal_account_table, array( 'status' => 'disabled', 'updated_at' => $this->learner_portal_now_mysql() ), array( 'id' => $account_id ), array( '%s', '%s' ), array( '%d' ) );
        $message = 'Accès désactivé.';
        $this->learner_portal_log_event( $account_id, 'admin_disabled_access' );
        break;
      case 'enable':
        $reactivation_expiry = $this->learner_portal_parse_mysql_time( $account->access_expires_at ) > current_time( 'timestamp' )
          ? $account->access_expires_at
          : gmdate( 'Y-m-d H:i:s', strtotime( '+30 days', current_time( 'timestamp' ) ) );
        $wpdb->update( $this->learner_portal_account_table, array( 'status' => 'active', 'access_expires_at' => $reactivation_expiry, 'last_access_notice_key' => null, 'last_access_notice_at' => null, 'last_access_notice_expiry_at' => null, 'updated_at' => $this->learner_portal_now_mysql() ), array( 'id' => $account_id ), array( '%s', '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
        $message = 'Accès réactivé.';
        $this->learner_portal_log_event( $account_id, 'admin_enabled_access', array( 'new_expiry' => $reactivation_expiry ) );
        $this->learner_portal_notify_admin( 'Réactivation manuelle d’un accès extranet apprenant', '<p>Un accès extranet apprenant a été réactivé manuellement.</p><p><strong>E-mail :</strong> ' . esc_html( $account->email ) . '</p><p><strong>Nouvelle expiration :</strong> ' . esc_html( $this->learner_portal_format_date( $reactivation_expiry, true ) ) . '</p>' );
        break;
      case 'prolong':
        $new_expiry = gmdate( 'Y-m-d H:i:s', strtotime( '+30 days', max( current_time( 'timestamp' ), $this->learner_portal_parse_mysql_time( $account->access_expires_at ) ) ) );
        $wpdb->update( $this->learner_portal_account_table, array( 'status' => 'active', 'access_expires_at' => $new_expiry, 'last_access_notice_key' => null, 'last_access_notice_at' => null, 'last_access_notice_expiry_at' => null, 'updated_at' => $this->learner_portal_now_mysql() ), array( 'id' => $account_id ), array( '%s', '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
        $message = 'Accès prolongé de 30 jours.';
        $this->learner_portal_log_event( $account_id, 'admin_prolonged_access', array( 'new_expiry' => $new_expiry ) );
        $this->learner_portal_notify_admin( 'Prolongation manuelle d’un accès extranet apprenant', '<p>Un accès extranet apprenant a été prolongé de 30 jours.</p><p><strong>E-mail :</strong> ' . esc_html( $account->email ) . '</p><p><strong>Nouvelle expiration :</strong> ' . esc_html( $this->learner_portal_format_date( $new_expiry, true ) ) . '</p>' );
        break;
      case 'reset':
        $this->learner_portal_send_reset_email( $account );
        $message = 'E-mail de réinitialisation envoyé.';
        $this->learner_portal_log_event( $account_id, 'admin_sent_reset_email' );
        break;
      case 'send_activation':
        /* ACDC 3.25.260 — Renvoi demandé explicitement : il force le verrou
           anti-doublon, c'est exactement ce qu'on lui demande. */
        $this->learner_portal_send_activation_email( $account, '', true );
        $message = 'E-mail d’ouverture d’accès envoyé.';
        $this->learner_portal_log_event( $account_id, 'admin_sent_activation_email' );
        break;
    }

    wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-learner-portal&notice=' . rawurlencode( $message ) . '&notice_type=success' ) );
    exit;
  }

}
