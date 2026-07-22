<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Learner_Portal_Core_Trait {

  private function learner_portal_page_url( $page_key = 'dashboard', $args = array() ) {
    $option_map = array(
      'parent'       => 'acdc_of_learner_portal_parent_page_id',
      'login'        => 'acdc_of_learner_portal_login_page_id',
      'dashboard'    => 'acdc_of_learner_portal_dashboard_page_id',
      'formations'   => 'acdc_of_learner_portal_formations_page_id',
      'formation'    => 'acdc_of_learner_portal_formation_page_id',
      'planning'     => 'acdc_of_learner_portal_planning_page_id',
      'documents'    => 'acdc_of_learner_portal_documents_page_id',
      'library'      => 'acdc_of_learner_portal_library_page_id',
      'mes_quiz'      => 'acdc_of_learner_portal_mes_quiz_page_id',
      'mes_signatures' => 'acdc_of_learner_portal_mes_signatures_page_id',
      'profile'       => 'acdc_of_learner_portal_profile_page_id',
    );

    $option_key = isset( $option_map[ $page_key ] ) ? $option_map[ $page_key ] : $option_map['dashboard'];
    $page_id    = (int) get_option( $option_key, 0 );
    $url        = $page_id ? get_permalink( $page_id ) : home_url( '/' );

    return add_query_arg( $args, $url );
  }

  private function learner_portal_contact_email() {
    return 'contact@acdc-formation.com';
  }

  private function learner_portal_admin_notification_email() {
    $admin = sanitize_email( (string) get_option( 'admin_email' ) );
    return '' !== $admin ? $admin : $this->learner_portal_contact_email();
  }

  private function learner_portal_notify_admin( $subject, $body_html, $greeting_name = 'administrateur' ) {
    $to = $this->learner_portal_admin_notification_email();
    if ( '' === $to ) {
      return false;
    }
    return $this->learner_portal_send_email( $to, $subject, $body_html, $greeting_name );
  }

  private function learner_portal_cookie_name() {
    return 'acdc_learner_session';
  }

  private function learner_portal_get_client_ip() {
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
    return '' !== $ip ? $ip : 'inconnue';
  }

  private function learner_portal_get_user_agent() {
    $agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
    return '' !== $agent ? substr( $agent, 0, 255 ) : '';
  }

  private function learner_portal_now_mysql() {
    return current_time( 'mysql' );
  }

  private function learner_portal_hash_token( $token ) {
    return hash( 'sha256', (string) $token );
  }

  private function learner_portal_random_token( $length = 32 ) {
    try {
      return bin2hex( random_bytes( max( 16, (int) $length ) ) );
    } catch ( \Throwable $e ) {
      return wp_generate_password( max( 32, (int) $length * 2 ), false, false );
    }
  }

  private function learner_portal_parse_mysql_time( $value ) {
    if ( empty( $value ) ) {
      return 0;
    }
    $timestamp = strtotime( (string) $value );
    return $timestamp ? (int) $timestamp : 0;
  }

  private function learner_portal_add_months( $mysql_datetime, $months = 6 ) {
    $timestamp = $this->learner_portal_parse_mysql_time( $mysql_datetime );
    if ( ! $timestamp ) {
      $timestamp = current_time( 'timestamp' );
    }
    return wp_date( 'Y-m-d H:i:s', strtotime( '+' . absint( $months ) . ' months', $timestamp ) );
  }

  private function learner_portal_status_label( $status ) {
    $labels = array(
      'never_activated'    => 'Jamais activé',
      'active'             => 'Actif',
      'password_to_change' => 'Mot de passe à changer',
      'blocked'            => 'Bloqué temporairement',
      'expired'            => 'Expiré',
      'disabled'           => 'Désactivé manuellement',
    );
    return isset( $labels[ $status ] ) ? $labels[ $status ] : 'Inconnu';
  }

  private function learner_portal_log_event( $account_id, $event_type, $event_data = array(), $learner_id = 0 ) {
    global $wpdb;

    if ( empty( $this->learner_portal_log_table ) ) {
      return;
    }

    $wpdb->insert(
      $this->learner_portal_log_table,
      array(
        'account_id'  => absint( $account_id ),
        'learner_id'  => absint( $learner_id ),
        'event_type'  => sanitize_key( $event_type ),
        'ip_address'  => $this->learner_portal_get_client_ip(),
        'user_agent'  => $this->learner_portal_get_user_agent(),
        'event_data'  => ! empty( $event_data ) ? wp_json_encode( $event_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '',
        'created_at'  => $this->learner_portal_now_mysql(),
      ),
      array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
    );
  }

  private function learner_portal_redirect( $page_key = 'dashboard', $message = '', $type = 'success', $args = array() ) {
    if ( '' !== $message ) {
      $args['notice']      = rawurlencode( $message );
      $args['notice_type'] = sanitize_key( $type );
    }
    wp_safe_redirect( $this->learner_portal_page_url( $page_key, $args ) );
    exit;
  }

  private function learner_portal_login_url( $args = array() ) {
    return $this->learner_portal_page_url( 'login', $args );
  }

  private function learner_portal_require_auth() {
    $account = $this->learner_portal_get_current_account();
    if ( ! $account ) {
      $this->learner_portal_redirect( 'login', 'Veuillez vous connecter pour accéder à votre espace apprenant.', 'error' );
    }
    return $account;
  }

  private function learner_portal_get_account( $account_id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->learner_portal_account_table} WHERE id = %d", absint( $account_id ) ) );
  }

  private function learner_portal_get_account_by_email( $email ) {
    global $wpdb;
    $email = sanitize_email( $email );
    if ( '' === $email ) {
      return null;
    }
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->learner_portal_account_table} WHERE email = %s", $email ) );
  }

  private function learner_portal_get_token_row( $token, $type ) {
    global $wpdb;
    $token_hash = $this->learner_portal_hash_token( $token );
    return $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM {$this->learner_portal_token_table} WHERE token_hash = %s AND token_type = %s AND consumed_at IS NULL AND revoked_at IS NULL ORDER BY id DESC LIMIT 1",
        $token_hash,
        sanitize_key( $type )
      )
    );
  }

  private function learner_portal_revoke_tokens( $account_id, $type ) {
    global $wpdb;
    $wpdb->update(
      $this->learner_portal_token_table,
      array( 'revoked_at' => $this->learner_portal_now_mysql() ),
      array(
        'account_id' => absint( $account_id ),
        'token_type' => sanitize_key( $type ),
        'revoked_at' => null,
      ),
      array( '%s' ),
      array( '%d', '%s', '%s' )
    );
  }

  private function learner_portal_create_token( $account_id, $type, $expires_at, $meta = array() ) {
    global $wpdb;

    $this->learner_portal_revoke_tokens( $account_id, $type );

    $raw = $this->learner_portal_random_token( 24 );
    $wpdb->insert(
      $this->learner_portal_token_table,
      array(
        'account_id' => absint( $account_id ),
        'token_hash' => $this->learner_portal_hash_token( $raw ),
        'token_type' => sanitize_key( $type ),
        'expires_at' => $expires_at,
        'meta_json'  => ! empty( $meta ) ? wp_json_encode( $meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '',
        'created_at' => $this->learner_portal_now_mysql(),
      ),
      array( '%d', '%s', '%s', '%s', '%s', '%s' )
    );

    return $raw;
  }

  private function learner_portal_send_email( $to, $subject, $body_html, $greeting_name = 'Apprenant' ) {
    $to = sanitize_email( $to );
    if ( '' === $to || '' === trim( wp_strip_all_tags( $body_html ) ) ) {
      return false;
    }

    return $this->acdc_send_transactional_email(
      $to,
      $subject,
      array(
        'greeting_name' => '' !== $greeting_name ? $greeting_name : 'Apprenant',
        'intro_html' => '',
        'body_html' => $body_html,
        'footer_notice' => 'Cet e-mail a été envoyé dans le cadre de votre espace apprenant. Vos données sont traitées conformément au RGPD.',
      ),
      array(
        'source_module' => 'learner-portal',
        'source_action' => 'portal_email',
        'email_category' => 'suivi_formation',
        'email_audience' => 'apprenant',
      )
    );
  }

  private function learner_portal_send_activation_email( $account ) {
    if ( ! $account ) {
      return false;
    }

    $token = $this->learner_portal_create_token(
      $account->id,
      'activation',
      wp_date( 'Y-m-d H:i:s', strtotime( '+7 days', current_time( 'timestamp' ) ) ),
      array( 'purpose' => 'first_activation' )
    );

    $primary = $this->learner_portal_get_primary_learner_profile( $account->email );
    $display_name = $primary ? trim( $primary->first_name . ' ' . $primary->usage_last_name ) : $account->email;
    $activation_url = $this->learner_portal_login_url( array( 'view' => 'activate', 'token' => rawurlencode( $token ) ) );
    $temp_password = ! empty( $account->temp_password_plain ) ? $account->temp_password_plain : 'Mot de passe temporaire déjà généré';

    $body  = '<p>Votre accès à l’extranet apprenant ACDC Formation est ouvert.</p>';
    $body .= '<div style="margin:18px 0;padding:16px;border:1px solid #E9C77C;border-radius:10px;background:#fbf8f7;">';
    $body .= '<p style="margin:0 0 8px;"><strong>Identifiant de connexion :</strong> ' . esc_html( $account->email ) . '</p>';
    $body .= '<p style="margin:0 0 8px;"><strong>Mot de passe temporaire :</strong> ' . esc_html( $temp_password ) . '</p>';
    $body .= '<p style="margin:0;"><strong>Durée de validité de l’accès :</strong> jusqu’au ' . esc_html( $this->learner_portal_format_date( $account->access_expires_at, true ) ) . '</p>';
    $body .= '</div>';
    $body .= '<p><a href="' . esc_url( $activation_url ) . '" style="display:inline-block;padding:12px 18px;border-radius:10px;background:#D7A24B;color:#0B0706;text-decoration:none;font-weight:600;">Activer mon accès</a></p>';
    $body .= '<p>Le changement du mot de passe est obligatoire lors de la première connexion.</p>';
    $body .= '<p>Si vous rencontrez une difficulté, vous pouvez nous contacter à ' . esc_html( $this->learner_portal_contact_email() ) . '.</p>';

    return $this->learner_portal_send_email( $account->email, 'Ouverture de votre accès extranet apprenant', $body, $display_name );
  }

  private function learner_portal_send_reset_email( $account ) {
    if ( ! $account ) {
      return false;
    }

    $token = $this->learner_portal_create_token(
      $account->id,
      'reset',
      wp_date( 'Y-m-d H:i:s', strtotime( '+2 days', current_time( 'timestamp' ) ) ),
      array( 'purpose' => 'password_reset' )
    );

    $primary = $this->learner_portal_get_primary_learner_profile( $account->email );
    $display_name = $primary ? trim( $primary->first_name . ' ' . $primary->usage_last_name ) : $account->email;
    $reset_url = $this->learner_portal_login_url( array( 'view' => 'reset', 'token' => rawurlencode( $token ) ) );

    $body  = '<p>Bonjour ' . esc_html( $display_name ) . ',</p>';
    $body .= '<p>Vous avez demandé la réinitialisation de votre mot de passe.</p>';
    $body .= '<p><a href="' . esc_url( $reset_url ) . '" style="display:inline-block;padding:12px 18px;border-radius:10px;background:#D7A24B;color:#0B0706;text-decoration:none;font-weight:600;">Réinitialiser mon mot de passe</a></p>';
    $body .= '<p>Si vous n’êtes pas à l’origine de cette demande, ignorez simplement cet e-mail.</p>';
    $body .= '<p><a href="' . esc_url( $this->learner_portal_login_url() ) . '">Retour à la connexion apprenant</a></p>';
    $body .= '<p>Besoin d’aide ? Contactez ' . esc_html( $this->learner_portal_contact_email() ) . '.</p>';

    return $this->learner_portal_send_email( $account->email, 'Réinitialisation de votre mot de passe', $body );
  }

  private function learner_portal_set_cookie( $token, $expires_at ) {
    $expires = $this->learner_portal_parse_mysql_time( $expires_at );
    if ( ! $expires ) {
      $expires = current_time( 'timestamp' ) + WEEK_IN_SECONDS;
    }
    $name   = $this->learner_portal_cookie_name();
    $path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
    $domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
    $secure = is_ssl();

    if ( headers_sent() ) {
      $_COOKIE[ $name ] = $token;
      return;
    }

    $this->learner_portal_send_cookie( $name, $token, $expires, $path, $domain, $secure );
    $_COOKIE[ $name ] = $token;
  }

  private function learner_portal_clear_cookie() {
    $name   = $this->learner_portal_cookie_name();
    $path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
    $domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
    $secure = is_ssl();

    if ( ! headers_sent() ) {
      $this->learner_portal_send_cookie( $name, '', time() - HOUR_IN_SECONDS, $path, $domain, $secure );
    }

    unset( $_COOKIE[ $name ] );
  }

  private function learner_portal_send_cookie( $name, $value, $expires, $path, $domain, $secure ) {
    if ( PHP_VERSION_ID >= 70300 ) {
      return setcookie(
        $name,
        $value,
        array(
          'expires'  => $expires,
          'path'     => $path,
          'domain'   => $domain,
          'secure'   => $secure,
          'httponly' => true,
          'samesite' => 'Lax',
        )
      );
    }

    return setcookie( $name, $value, $expires, $path . '; samesite=Lax', $domain, $secure, true );
  }

  private function learner_portal_create_session( $account_id ) {
    global $wpdb;

    $this->learner_portal_delete_active_sessions( $account_id );

    $raw       = $this->learner_portal_random_token( 32 );
    $expires   = wp_date( 'Y-m-d H:i:s', strtotime( '+7 days', current_time( 'timestamp' ) ) );
    $now_mysql = $this->learner_portal_now_mysql();

    $wpdb->insert(
      $this->learner_portal_session_table,
      array(
        'account_id'       => absint( $account_id ),
        'session_hash'     => $this->learner_portal_hash_token( $raw ),
        'ip_address'       => $this->learner_portal_get_client_ip(),
        'user_agent'       => $this->learner_portal_get_user_agent(),
        'expires_at'       => $expires,
        'last_activity_at' => $now_mysql,
        'created_at'       => $now_mysql,
      ),
      array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
    );

    $this->learner_portal_set_cookie( $raw, $expires );
    return $raw;
  }

  private function learner_portal_delete_active_sessions( $account_id ) {
    global $wpdb;
    $wpdb->update(
      $this->learner_portal_session_table,
      array( 'revoked_at' => $this->learner_portal_now_mysql() ),
      array(
        'account_id' => absint( $account_id ),
        'revoked_at' => null,
      ),
      array( '%s' ),
      array( '%d', '%s' )
    );
  }

  private function learner_portal_revoke_session_by_hash( $session_hash ) {
    global $wpdb;
    $wpdb->update(
      $this->learner_portal_session_table,
      array( 'revoked_at' => $this->learner_portal_now_mysql() ),
      array(
        'session_hash' => $session_hash,
        'revoked_at'   => null,
      ),
      array( '%s' ),
      array( '%s', '%s' )
    );
  }

  private function learner_portal_get_current_account() {
    global $wpdb;

    $cookie_name = $this->learner_portal_cookie_name();
    $token       = isset( $_COOKIE[ $cookie_name ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) ) : '';
    if ( '' === $token ) {
      return null;
    }

    $session_hash = $this->learner_portal_hash_token( $token );
    $session      = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM {$this->learner_portal_session_table} WHERE session_hash = %s AND revoked_at IS NULL ORDER BY id DESC LIMIT 1",
        $session_hash
      )
    );

    if ( ! $session ) {
      $this->learner_portal_clear_cookie();
      return null;
    }

    // Durcissement OPT-IN (anti-vol de cookie) : lie la session à l'empreinte IP/User-Agent.
    // Activé uniquement si la constante ACDC_PORTAL_STRICT_SESSION est définie et vraie ;
    // sans elle, le comportement reste strictement inchangé. Les sessions legacy sans
    // empreinte (ip/ua vides) restent acceptées (cf. SessionFingerprint::matches).
    if ( defined( 'ACDC_PORTAL_STRICT_SESSION' ) && ACDC_PORTAL_STRICT_SESSION
      && ! \ACDC\Support\SessionFingerprint::matches(
        (string) $session->ip_address,
        (string) $session->user_agent,
        $this->learner_portal_get_client_ip(),
        $this->learner_portal_get_user_agent()
      )
    ) {
      $this->learner_portal_revoke_session_by_hash( $session_hash );
      $this->learner_portal_clear_cookie();
      return null;
    }

    $expires_ts       = $this->learner_portal_parse_mysql_time( $session->expires_at );
    $last_activity_ts = $this->learner_portal_parse_mysql_time( $session->last_activity_at );
    $now_ts           = current_time( 'timestamp' );

    if ( ( $expires_ts && $now_ts > $expires_ts ) || ( $last_activity_ts && ( $now_ts - $last_activity_ts ) > HOUR_IN_SECONDS ) ) {
      $this->learner_portal_revoke_session_by_hash( $session_hash );
      $this->learner_portal_clear_cookie();
      return null;
    }

    $account = $this->learner_portal_get_account( $session->account_id );
    if ( ! $account ) {
      $this->learner_portal_revoke_session_by_hash( $session_hash );
      $this->learner_portal_clear_cookie();
      return null;
    }

    $account_expiry_ts = $this->learner_portal_parse_mysql_time( $account->access_expires_at );
    $blocked_until_ts  = $this->learner_portal_parse_mysql_time( $account->blocked_until );
    if ( ( $account_expiry_ts && $now_ts > $account_expiry_ts ) || in_array( $account->status, array( 'expired', 'disabled' ), true ) ) {
      $wpdb->update(
        $this->learner_portal_account_table,
        array(
          'status'     => 'expired',
          'updated_at' => $this->learner_portal_now_mysql(),
        ),
        array( 'id' => absint( $account->id ) ),
        array( '%s', '%s' ),
        array( '%d' )
      );
      $this->learner_portal_log_event( $account->id, 'access_refused_expired' );
      $this->learner_portal_revoke_session_by_hash( $session_hash );
      $this->learner_portal_clear_cookie();
      return null;
    }

    if ( 'blocked' === $account->status && $blocked_until_ts && $blocked_until_ts > $now_ts ) {
      $this->learner_portal_revoke_session_by_hash( $session_hash );
      $this->learner_portal_clear_cookie();
      return null;
    }

    $wpdb->update(
      $this->learner_portal_session_table,
      array( 'last_activity_at' => $this->learner_portal_now_mysql() ),
      array( 'id' => absint( $session->id ) ),
      array( '%s' ),
      array( '%d' )
    );

    return $account;
  }

  private function learner_portal_format_date( $value, $with_time = false ) {
    if ( empty( $value ) ) {
      return 'Non renseignée';
    }
    $timestamp = strtotime( (string) $value );
    if ( ! $timestamp ) {
      return (string) $value;
    }
    return $with_time ? wp_date( 'd/m/Y H:i', $timestamp ) : wp_date( 'd/m/Y', $timestamp );
  }

  private function learner_portal_safe_file_label( $url, $fallback = 'Document' ) {
    $path = wp_parse_url( $url, PHP_URL_PATH );
    if ( ! empty( $path ) ) {
      return basename( $path );
    }
    return $fallback;
  }

  private function learner_portal_get_local_path_from_url( $url ) {
    $url = is_scalar( $url ) ? trim( (string) $url ) : '';
    if ( '' === $url ) {
      return '';
    }

    $uploads = wp_get_upload_dir();
    if ( ! empty( $uploads['baseurl'] ) && 0 === strpos( $url, $uploads['baseurl'] ) && ! empty( $uploads['basedir'] ) ) {
      $relative = ltrim( substr( $url, strlen( $uploads['baseurl'] ) ), '/' );
      return trailingslashit( $uploads['basedir'] ) . $relative;
    }

    if ( 0 === strpos( $url, home_url( '/' ) ) ) {
      $relative = ltrim( substr( $url, strlen( home_url( '/' ) ) ), '/' );
      return trailingslashit( ABSPATH ) . $relative;
    }

    return '';
  }

  private function learner_portal_stream_file( $url, $filename = '' ) {
    $path = $this->learner_portal_get_local_path_from_url( $url );
    if ( $path && file_exists( $path ) && is_readable( $path ) ) {
      $mime = function_exists( 'mime_content_type' ) ? mime_content_type( $path ) : 'application/octet-stream';
      nocache_headers();
      header( 'Content-Type: ' . $mime );
      header( 'Content-Length: ' . filesize( $path ) );
      header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ? $filename : basename( $path ) ) . '"' );
      readfile( $path );
      exit;
    }

    wp_safe_redirect( esc_url_raw( $url ) );
    exit;
  }

  private function learner_portal_parse_multiline_urls( $value ) {
    $items = array();
    $lines = preg_split( '/\r\n|\r|\n/', (string) $value );
    foreach ( $lines as $line ) {
      $line = trim( $line );
      if ( '' === $line ) {
        continue;
      }
      $items[] = esc_url_raw( $line );
    }
    return $items;
  }

  /**
   * ACDC 3.21.06 — Récupère les quiz en attente/en cours d'un apprenant (portail).
   * Statuts actifs : pending, invited, opened, started, partial.
   *
   * @param string $email  Adresse e-mail du compte apprenant.
   * @return array         Tableau de stdObjects enrichis.
   */
  public function get_learner_pending_quizzes( $email ) {
    global $wpdb;
    $email = sanitize_email( $email );
    if ( '' === $email ) {
      return array();
    }
    $tbl_p = $this->get_qz_table( 'participants' );
    $tbl_s = $this->get_qz_table( 'sessions' );
    $tbl_q = $this->get_qz_table( 'quizzes' );
    if ( ! $tbl_p || ! $tbl_s || ! $tbl_q ) {
      return array();
    }
    return $wpdb->get_results( $wpdb->prepare(
      "SELECT p.id AS participant_id, p.status AS participant_status,
              p.secure_token, p.token_expires_at, p.invited_at,
              p.total_score, p.total_score_percentage, p.is_passed,
              q.id AS quiz_id, q.title AS quiz_title, q.quiz_purpose,
              q.duration_seconds, q.pass_threshold AS passing_score,
              s.id AS session_id, s.expires_at AS session_expires_at
       FROM {$tbl_p} p
       INNER JOIN {$tbl_s} s ON s.id = p.session_id
       INNER JOIN {$tbl_q} q ON q.id = s.quiz_id
       WHERE p.email = %s
         AND p.status IN ('pending','invited','opened','started','partial')
         AND ( p.token_expires_at IS NULL OR p.token_expires_at > NOW() )
         AND p.is_anonymized = 0
       ORDER BY p.invited_at DESC",
      $email
    ) );
  }

  /**
   * ACDC 3.21.06 — Récupère les quiz complétés d'un apprenant (portail).
   *
   * @param string $email
   * @return array
   */
  public function get_learner_completed_quizzes( $email ) {
    global $wpdb;
    $email = sanitize_email( $email );
    if ( '' === $email ) {
      return array();
    }
    $tbl_p = $this->get_qz_table( 'participants' );
    $tbl_s = $this->get_qz_table( 'sessions' );
    $tbl_q = $this->get_qz_table( 'quizzes' );
    if ( ! $tbl_p || ! $tbl_s || ! $tbl_q ) {
      return array();
    }

    // Vérification préalable de la colonne result_document_url (optionnelle)
    $has_result_col = $this->acdc_schema_has_column( $tbl_p, 'result_document_url' );
    $result_col_sql = $has_result_col ? 'p.result_document_url,' : "'' AS result_document_url,";

    // Jointure via email direct OU via learner_id (couvre les participants live identifiés)
    $learner_table = $wpdb->prefix . 'acdc_of_learners';
    return $wpdb->get_results( $wpdb->prepare(
      "SELECT p.id AS participant_id, p.status AS participant_status,
              p.completed_at, p.total_score, p.total_score_percentage, p.is_passed,
              {$result_col_sql}
              q.id AS quiz_id, q.title AS quiz_title, q.quiz_purpose,
              q.pass_threshold AS passing_score
       FROM {$tbl_p} p
       INNER JOIN {$tbl_s} s ON s.id = p.session_id
       INNER JOIN {$tbl_q} q ON q.id = s.quiz_id
       LEFT  JOIN {$learner_table} l ON l.id = p.learner_id
       WHERE (p.email = %s OR l.email = %s)
         AND p.status = 'completed'
         AND p.is_anonymized = 0
       ORDER BY p.completed_at DESC
       LIMIT 50",
      $email, $email
    ) );
  }

  /**
   * Récupère un participant quiz et vérifie qu'il appartient bien à cet email.
   * Utilisé par la vue détaillée apprenant (sécurité : empêche l'accès aux résultats d'autrui).
   *
   * @param int    $participant_id
   * @param string $email
   *
   * @return object|null
   */
  public function get_learner_quiz_participant( $participant_id, $email ) {
    global $wpdb;
    $participant_id = (int) $participant_id;
    $email          = sanitize_email( $email );
    if ( $participant_id <= 0 || '' === $email ) {
      return null;
    }
    $tbl_p = $this->get_qz_table( 'participants' );
    $tbl_s = $this->get_qz_table( 'sessions' );
    $tbl_q = $this->get_qz_table( 'quizzes' );
    if ( ! $tbl_p || ! $tbl_s || ! $tbl_q ) {
      return null;
    }
    $learner_table  = $wpdb->prefix . 'acdc_of_learners';
    $has_result_col = $this->acdc_schema_has_column( $tbl_p, 'result_document_url' );
    $result_col_sql = $has_result_col ? 'p.result_document_url,' : "'' AS result_document_url,";

    return $wpdb->get_row( $wpdb->prepare(
      "SELECT p.*, {$result_col_sql}
              q.title AS quiz_title, q.quiz_purpose, q.pass_threshold AS passing_score,
              s.id AS session_id_ref
       FROM {$tbl_p} p
       INNER JOIN {$tbl_s} s ON s.id = p.session_id
       INNER JOIN {$tbl_q} q ON q.id = s.quiz_id
       LEFT  JOIN {$learner_table} l ON l.id = p.learner_id
       WHERE p.id = %d
         AND (p.email = %s OR l.email = %s)
         AND p.is_anonymized = 0
       LIMIT 1",
      $participant_id, $email, $email
    ) );
  }

  private function learner_portal_get_primary_learner_profile( $email ) {
    global $wpdb;
    $email = sanitize_email( $email );
    if ( '' === $email ) {
      return null;
    }

    return $wpdb->get_row(
      $wpdb->prepare(
        "SELECT l.*, c.name AS company_name FROM {$this->learner_table} l LEFT JOIN {$this->company_table} c ON c.id = l.company_id WHERE l.email = %s ORDER BY l.updated_at DESC, l.id DESC LIMIT 1",
        $email
      )
    );
  }

  private function learner_portal_get_learner_rows_by_email( $email ) {
    global $wpdb;
    $email = sanitize_email( $email );
    if ( '' === $email ) {
      return array();
    }

    return $wpdb->get_results(
      $wpdb->prepare(
        "SELECT l.*, c.name AS company_name, s.title AS session_title, s.start_date, s.end_date, s.start_at, s.end_at, s.location, s.remote_link, s.notes AS session_notes, s.status AS session_status, f.title AS formation_title, f.description_text AS formation_description, f.objectives, f.duration, f.program_file_url, f.shared_docs, f.shared_links, f.address AS formation_address, f.city AS formation_city, f.postal_code AS formation_postal_code, f.notes AS formation_notes
         FROM {$this->learner_table} l
         LEFT JOIN {$this->company_table} c ON c.id = l.company_id
         LEFT JOIN {$this->session_table} s ON s.id = l.session_id
         LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id
         WHERE l.email = %s
         ORDER BY l.updated_at DESC, l.id DESC",
        $email
      )
    );
  }

  /**
   * ACDC 3.21.07 — Récupère les demandes de signature électronique en attente
   * pour l'adresse email du compte apprenant.
   *
   * @param string $email
   * @return array
   */
  public function get_learner_pending_signatures( $email ) {
    if ( ! class_exists( 'ACDC_Sig_Core' ) ) {
      return array();
    }
    global $wpdb;
    $sig = new ACDC_Sig_Core();
    $sig->init_tables();
    $tbl = $sig->table_requests;
    $email = sanitize_email( $email );
    if ( '' === $email ) {
      return array();
    }
    return $wpdb->get_results( $wpdb->prepare(
      "SELECT id, token, doc_type, signer_name, status, expires_at, signed_at, doc_url, signed_doc_url, created_at
       FROM {$tbl}
       WHERE signer_email = %s
         AND status NOT IN ('supprime')
       ORDER BY created_at DESC
       LIMIT 30",
      $email
    ) );
  }

    private function learner_portal_get_trainer_label_for_session( $session_id ) {
    global $wpdb;
    if ( ! $session_id ) {
      return '';
    }
    // 1. Chercher via le groupe (chemin historique)
    $group = $wpdb->get_row( $wpdb->prepare( "SELECT trainer_name FROM {$this->group_table} WHERE session_id = %d ORDER BY id DESC LIMIT 1", absint( $session_id ) ) );
    if ( $group && ! empty( $group->trainer_name ) ) {
      return (string) $group->trainer_name;
    }
    // 2. Fallback : trainer_id directement sur la session (nouveau formulaire)
    $session_trainer = $wpdb->get_row( $wpdb->prepare(
      "SELECT t.first_name, t.last_name FROM {$this->session_table} s
       INNER JOIN {$this->trainer_table} t ON t.id = s.trainer_id
       WHERE s.id = %d LIMIT 1",
      absint( $session_id )
    ) );
    if ( $session_trainer ) {
      return trim( $session_trainer->first_name . ' ' . $session_trainer->last_name );
    }
    return '';
  }

  private function learner_portal_build_access_index() {
    global $wpdb;

    $cache_key = 'acdc_of_learner_portal_access_index';
    $cached = get_transient( $cache_key );
    if ( is_array( $cached ) ) {
      return $cached;
    }

    $learners = $wpdb->get_results( "SELECT id, email, first_name, usage_last_name, session_id FROM {$this->learner_table} WHERE email <> '' ORDER BY id DESC" );
    $registrations = $wpdb->get_results( "SELECT id, learner_id, learner_ids, formation_id, updated_at FROM {$this->training_registration_table} WHERE extranet_access = 1 AND is_draft = 0 ORDER BY updated_at DESC, id DESC" );
    $sessions = $wpdb->get_results( "SELECT id, formation_id, start_date, start_at, end_at, end_date, trainer_id FROM {$this->session_table}" );
    $formations = $wpdb->get_results( "SELECT * FROM {$this->formation_table}" );

    $learners_by_id = array();
    foreach ( (array) $learners as $learner ) {
      $learners_by_id[ (int) $learner->id ] = $learner;
    }

    $sessions_by_id = array();
    foreach ( (array) $sessions as $session ) {
      $sessions_by_id[ (int) $session->id ] = $session;
    }

    $formations_by_id = array();
    foreach ( (array) $formations as $formation ) {
      $formations_by_id[ (int) $formation->id ] = $formation;
    }

    $index = array();
    foreach ( (array) $registrations as $registration ) {
      $learner_ids = array();
      if ( ! empty( $registration->learner_id ) ) {
        $learner_ids[] = absint( $registration->learner_id );
      }
      if ( ! empty( $registration->learner_ids ) ) {
        foreach ( explode( ',', (string) $registration->learner_ids ) as $raw_id ) {
          $raw_id = absint( trim( $raw_id ) );
          if ( $raw_id ) {
            $learner_ids[] = $raw_id;
          }
        }
      }
      $learner_ids = array_values( array_unique( array_filter( $learner_ids ) ) );
      if ( empty( $learner_ids ) ) {
        continue;
      }

      foreach ( $learner_ids as $learner_id ) {
        if ( empty( $learners_by_id[ $learner_id ] ) || empty( $learners_by_id[ $learner_id ]->email ) ) {
          continue;
        }

        $learner   = $learners_by_id[ $learner_id ];
        $email     = sanitize_email( $learner->email );
        $session   = ! empty( $learner->session_id ) && ! empty( $sessions_by_id[ (int) $learner->session_id ] ) ? $sessions_by_id[ (int) $learner->session_id ] : null;
        $formation = null;
        if ( ! empty( $registration->formation_id ) && ! empty( $formations_by_id[ (int) $registration->formation_id ] ) ) {
          $formation = $formations_by_id[ (int) $registration->formation_id ];
        } elseif ( $session && ! empty( $formations_by_id[ (int) $session->formation_id ] ) ) {
          $formation = $formations_by_id[ (int) $session->formation_id ];
        }

        $end_reference = '';
        if ( $session ) {
          $end_reference = ! empty( $session->end_at ) ? $session->end_at : ( ! empty( $session->end_date ) ? $session->end_date . ' 23:59:59' : '' );
        }
        if ( '' === $end_reference ) {
          $end_reference = ! empty( $registration->updated_at ) ? $registration->updated_at : current_time( 'mysql' );
        }

        $index[ $email ][] = array(
          'registration'     => $registration,
          'learner'          => $learner,
          'session'          => $session,
          'formation'        => $formation,
          'expires_at'       => $this->learner_portal_add_months( $end_reference, 6 ),
          'trainer_label'    => $session ? $this->learner_portal_get_trainer_label_for_session( $session->id ) : '',
        );
      }
    }

    set_transient( $cache_key, $index, 5 * MINUTE_IN_SECONDS );

    return $index;
  }

  private function learner_portal_flush_access_index_cache() {
    delete_transient( 'acdc_of_learner_portal_access_index' );
  }

  public function learner_portal_sync_accounts( $force = false ) {
    global $wpdb;

    if ( $force ) {
      $this->learner_portal_flush_access_index_cache();
    }

    if ( ! $force ) {
      $last_sync = (int) get_option( 'acdc_of_learner_portal_last_sync', 0 );
      if ( $last_sync && ( time() - $last_sync ) < 300 ) {
        return;
      }
    }

    $index   = $this->learner_portal_build_access_index();
    $now     = current_time( 'timestamp' );
    $created = 0;
    $updated = 0;
    $expired = 0;

    foreach ( $index as $email => $items ) {
      if ( empty( $items ) ) {
        continue;
      }

      usort( $items, static function( $a, $b ) {
        return strcmp( (string) $b['expires_at'], (string) $a['expires_at'] );
      } );

      $active_items = array_values( array_filter( $items, function( $item ) use ( $now ) {
        return $this->learner_portal_parse_mysql_time( $item['expires_at'] ) >= $now;
      } ) );
      if ( empty( $active_items ) ) {
        continue;
      }

      $primary_item       = $active_items[0];
      $access_expires_at  = $primary_item['expires_at'];
      $primary_learner_id = ! empty( $primary_item['learner']->id ) ? (int) $primary_item['learner']->id : 0;
      $account            = $this->learner_portal_get_account_by_email( $email );
      $status             = $account && in_array( $account->status, array( 'active', 'password_to_change', 'blocked' ), true ) ? $account->status : 'never_activated';
      $now_mysql          = $this->learner_portal_now_mysql();

      if ( ! $account ) {
        $plain_password = wp_generate_password( 12, false, false );
        $wpdb->insert(
          $this->learner_portal_account_table,
          array(
            'primary_learner_id'           => $primary_learner_id,
            'email'                        => $email,
            'password_hash'                => wp_hash_password( $plain_password ),
            'status'                       => 'never_activated',
            'must_change_password'         => 1,
            'access_opened_at'             => $now_mysql,
            'access_expires_at'            => $access_expires_at,
            'last_access_notice_key'       => null,
            'last_access_notice_at'        => null,
            'last_access_notice_expiry_at' => null,
            'created_at'                   => $now_mysql,
            'updated_at'                   => $now_mysql,
          ),
          array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        $account = $this->learner_portal_get_account( $wpdb->insert_id );
        if ( $account ) {
          $account->temp_password_plain = $plain_password;
          $this->learner_portal_log_event( $account->id, 'account_created', array( 'auto_sync' => true ), $primary_learner_id );
          $this->learner_portal_send_activation_email( $account );
          $created++;
        }
      } else {
        $new_status = $status;
        if ( $this->learner_portal_parse_mysql_time( $access_expires_at ) < $now ) {
          $new_status = 'expired';
        } elseif ( 'expired' === $account->status ) {
          $new_status = ! empty( $account->first_activated_at ) ? 'active' : 'never_activated';
        }

        $update_data = array(
          'primary_learner_id' => $primary_learner_id,
          'access_expires_at'  => $access_expires_at,
          'status'             => $new_status,
          'updated_at'         => $now_mysql,
        );

        if ( (string) $account->access_expires_at !== (string) $access_expires_at ) {
          $update_data['last_access_notice_key'] = null;
          $update_data['last_access_notice_at'] = null;
          $update_data['last_access_notice_expiry_at'] = null;
        }

        $wpdb->update(
          $this->learner_portal_account_table,
          $update_data,
          array( 'id' => (int) $account->id ),
          array_fill( 0, count( $update_data ), '%s' ),
          array( '%d' )
        );
        $updated++;
      }
    }

    $existing_accounts = $wpdb->get_results( "SELECT * FROM {$this->learner_portal_account_table}" );
    foreach ( (array) $existing_accounts as $account ) {
      if ( empty( $index[ $account->email ] ) ) {
        $wpdb->update(
          $this->learner_portal_account_table,
          array(
            'status'          => 'expired',
            'access_expires_at' => ! empty( $account->access_expires_at ) ? $account->access_expires_at : $this->learner_portal_now_mysql(),
            'updated_at'      => $this->learner_portal_now_mysql(),
          ),
          array( 'id' => (int) $account->id ),
          array( '%s', '%s', '%s' ),
          array( '%d' )
        );
        $expired++;
      }
    }

    update_option( 'acdc_of_learner_portal_last_sync', time(), false );
    update_option( 'acdc_of_learner_portal_last_sync_summary', array( 'created' => $created, 'updated' => $updated, 'expired' => $expired ), false );
  }

  private function learner_portal_send_access_notice_email( $account, $notice_key ) {
    if ( ! $account ) {
      return false;
    }

    $primary = $this->learner_portal_get_primary_learner_profile( $account->email );
    $display_name = $primary ? trim( $primary->first_name . ' ' . $primary->usage_last_name ) : $account->email;
    $days_remaining = max( 0, (int) ceil( ( $this->learner_portal_parse_mysql_time( $account->access_expires_at ) - current_time( 'timestamp' ) ) / DAY_IN_SECONDS ) );
    $subject = 'Information sur votre accès extranet apprenant';
    $body  = '<p>Bonjour ' . esc_html( $display_name ) . ',</p>';

    if ( 'expiring_15' === $notice_key ) {
      $subject = 'Votre accès extranet expire bientôt';
      $body .= '<p>Votre accès extranet apprenant expirera dans environ 15 jours.</p>';
    } elseif ( 'expiring_3' === $notice_key ) {
      $subject = 'Votre accès extranet expire dans 3 jours';
      $body .= '<p>Votre accès extranet apprenant expirera très prochainement.</p>';
    } else {
      $subject = 'Votre accès extranet est expiré';
      $body .= '<p>Votre accès extranet apprenant est désormais expiré.</p>';
    }

    $body .= '<div style="margin:18px 0;padding:16px;border:1px solid #E9C77C;border-radius:10px;background:#fbf8f7;">';
    $body .= '<p style="margin:0 0 8px;"><strong>Identifiant :</strong> ' . esc_html( $account->email ) . '</p>';
    $body .= '<p style="margin:0 0 8px;"><strong>Date d’expiration :</strong> ' . esc_html( $this->learner_portal_format_date( $account->access_expires_at, true ) ) . '</p>';
    if ( 'expired' !== $notice_key ) {
      $body .= '<p style="margin:0;"><strong>Temps restant estimé :</strong> ' . esc_html( $days_remaining ) . ' jour(s)</p>';
    }
    $body .= '</div>';

    if ( 'expired' === $notice_key ) {
      $body .= '<p>Vous pouvez utiliser le formulaire de contact de la page de connexion pour demander une réactivation.</p>';
      $body .= '<p><a href="' . esc_url( $this->learner_portal_login_url( array( 'view' => 'expired', 'email' => rawurlencode( $account->email ) ) ) ) . '">Accéder au formulaire de réactivation</a></p>';
    } else {
      $body .= '<p><a href="' . esc_url( $this->learner_portal_login_url() ) . '">Accéder à mon espace apprenant</a></p>';
    }

    $body .= '<p>Besoin d’aide ? Contactez ' . esc_html( $this->learner_portal_contact_email() ) . '.</p>';

    return $this->learner_portal_send_email( $account->email, $subject, $body );
  }

  private function learner_portal_mark_notice_sent( $account_id, $notice_key, $expiry_at ) {
    global $wpdb;
    $wpdb->update(
      $this->learner_portal_account_table,
      array(
        'last_access_notice_key'       => sanitize_key( $notice_key ),
        'last_access_notice_at'        => $this->learner_portal_now_mysql(),
        'last_access_notice_expiry_at' => $expiry_at,
        'updated_at'                   => $this->learner_portal_now_mysql(),
      ),
      array( 'id' => absint( $account_id ) ),
      array( '%s', '%s', '%s', '%s' ),
      array( '%d' )
    );
  }

  private function learner_portal_process_access_notifications() {
    global $wpdb;

    $accounts = $wpdb->get_results( "SELECT * FROM {$this->learner_portal_account_table} ORDER BY id DESC" );
    $now      = current_time( 'timestamp' );

    foreach ( (array) $accounts as $account ) {
      $expiry_ts = $this->learner_portal_parse_mysql_time( $account->access_expires_at );
      if ( ! $expiry_ts ) {
        continue;
      }

      $same_notice_expiry = ! empty( $account->last_access_notice_expiry_at ) && (string) $account->last_access_notice_expiry_at === (string) $account->access_expires_at;
      $days_remaining = (int) floor( ( $expiry_ts - $now ) / DAY_IN_SECONDS );

      if ( $expiry_ts <= $now ) {
        if ( 'expired' !== $account->status ) {
          $wpdb->update( $this->learner_portal_account_table, array( 'status' => 'expired', 'updated_at' => $this->learner_portal_now_mysql() ), array( 'id' => absint( $account->id ) ), array( '%s', '%s' ), array( '%d' ) );
        }
        if ( ! $same_notice_expiry || 'expired' !== $account->last_access_notice_key ) {
          if ( $this->learner_portal_send_access_notice_email( $account, 'expired' ) ) {
            $this->learner_portal_mark_notice_sent( $account->id, 'expired', $account->access_expires_at );
            $this->learner_portal_log_event( $account->id, 'access_expired_email_sent' );
          }
        }
        continue;
      }

      if ( $days_remaining <= 3 ) {
        if ( ! $same_notice_expiry || 'expiring_3' !== $account->last_access_notice_key ) {
          if ( $this->learner_portal_send_access_notice_email( $account, 'expiring_3' ) ) {
            $this->learner_portal_mark_notice_sent( $account->id, 'expiring_3', $account->access_expires_at );
            $this->learner_portal_log_event( $account->id, 'access_expiry_reminder_sent', array( 'days' => 3 ) );
          }
        }
        continue;
      }

      if ( $days_remaining <= 15 ) {
        if ( ! $same_notice_expiry || ! in_array( $account->last_access_notice_key, array( 'expiring_15', 'expiring_3' ), true ) ) {
          if ( $this->learner_portal_send_access_notice_email( $account, 'expiring_15' ) ) {
            $this->learner_portal_mark_notice_sent( $account->id, 'expiring_15', $account->access_expires_at );
            $this->learner_portal_log_event( $account->id, 'access_expiry_reminder_sent', array( 'days' => 15 ) );
          }
        }
      }
    }
  }

  public function maybe_repair_learner_portal_runtime_state() {
    if ( function_exists( 'wp_installing' ) && wp_installing() ) {
      return;
    }

    $cache_key = 'acdc_of_learner_portal_runtime_state_checked';
    if ( false !== get_transient( $cache_key ) ) {
      if ( $this->maybe_schedule_runtime_hook( 'acdc_of_learner_portal_cron_maintenance', 'hourly', 180 ) && method_exists( $this, 'log_error' ) ) {
        $this->log_error( 'learner_portal_runtime', 'Planification du portail apprenant restaurée.' );
      }
      return;
    }

    $parent_id = (int) get_option( 'acdc_of_learner_portal_parent_page_id', 0 );
    $login_id  = (int) get_option( 'acdc_of_learner_portal_login_page_id', 0 );
    $parent_ok = $parent_id && 'publish' === get_post_status( $parent_id );
    $login_ok  = $login_id && 'publish' === get_post_status( $login_id );

    if ( ! $parent_ok || ! $login_ok || '[acdc_of_learner_portal_home]' !== trim( (string) get_post_field( 'post_content', $parent_id ) ) ) {
      if ( method_exists( $this, 'ensure_default_pages' ) ) {
        $this->ensure_default_pages();
      }
    }

    set_transient( $cache_key, 1, HOUR_IN_SECONDS );

    if ( $this->maybe_schedule_runtime_hook( 'acdc_of_learner_portal_cron_maintenance', 'hourly', 180 ) && method_exists( $this, 'log_error' ) ) {
      $this->log_error( 'learner_portal_runtime', 'Planification du portail apprenant restaurée.' );
    }
  }

  public function handle_learner_portal_cron_maintenance() {
    $this->learner_portal_sync_accounts( true );
    $this->learner_portal_process_access_notifications();
  }

  private function learner_portal_get_access_items_for_email( $email ) {
    $index = $this->learner_portal_build_access_index();
    $email = sanitize_email( $email );
    $items = isset( $index[ $email ] ) ? $index[ $email ] : array();
    $now   = current_time( 'timestamp' );

    $items = array_values( array_filter( $items, function( $item ) use ( $now ) {
      return $this->learner_portal_parse_mysql_time( $item['expires_at'] ) >= $now;
    } ) );

    usort( $items, static function( $a, $b ) {
      return strcmp( (string) $b['expires_at'], (string) $a['expires_at'] );
    } );

    return $items;
  }

  private function learner_portal_get_access_item( $email, $registration_id ) {
    foreach ( $this->learner_portal_get_access_items_for_email( $email ) as $item ) {
      if ( ! empty( $item['registration']->id ) && (int) $item['registration']->id === absint( $registration_id ) ) {
        return $item;
      }
    }
    return null;
  }

  private function learner_portal_get_upcoming_sessions( $email, $limit = 5 ) {
    $items = array();
    foreach ( $this->learner_portal_get_access_items_for_email( $email ) as $item ) {
      if ( empty( $item['session'] ) ) {
        continue;
      }
      $session = $item['session'];
      $start_ref = ! empty( $session->start_at ) ? $session->start_at : ( ! empty( $session->start_date ) ? $session->start_date . ' 08:00:00' : '' );
      $ts = $this->learner_portal_parse_mysql_time( $start_ref );
      if ( $ts < current_time( 'timestamp' ) - DAY_IN_SECONDS ) {
        continue;
      }
      $items[ (int) $session->id ] = array(
        'session'    => $session,
        'formation'  => $item['formation'],
        'trainer'    => $item['trainer_label'],
        'registration_id' => (int) $item['registration']->id,
      );
    }

    usort( $items, function( $a, $b ) {
      $a_ts = $this->learner_portal_parse_mysql_time( ! empty( $a['session']->start_at ) ? $a['session']->start_at : $a['session']->start_date );
      $b_ts = $this->learner_portal_parse_mysql_time( ! empty( $b['session']->start_at ) ? $b['session']->start_at : $b['session']->start_date );
      return $a_ts <=> $b_ts;
    } );

    return array_slice( array_values( $items ), 0, max( 1, absint( $limit ) ) );
  }

  private function learner_portal_get_library_entries( $email ) {
    $entries = array();
    foreach ( $this->learner_portal_get_access_items_for_email( $email ) as $item ) {
      $formation    = ! empty( $item['formation'] ) ? $item['formation'] : null;
      $registration = ! empty( $item['registration'] ) ? $item['registration'] : null;
      $session      = ! empty( $item['session'] ) ? $item['session'] : null;
      if ( ! $formation || ! $registration ) {
        continue;
      }

      $formation_title = ! empty( $formation->title ) ? $formation->title : 'Formation';
      $session_label   = $session && ! empty( $session->title ) ? $session->title : '';

      if ( ! empty( $formation->program_file_url ) ) {
        $entries[] = array(
          'label'          => 'Programme de formation',
          'formation'      => $formation_title,
          'session'        => $session_label,
          'type'           => 'pdf',
          'type_label'     => $this->learner_portal_get_resource_type_label( 'pdf' ),
          'registration_id'=> (int) $registration->id,
          'resource_type'  => 'program',
          'resource_index' => 0,
          'url'            => $this->learner_portal_get_document_download_url( (int) $registration->id, 'program' ),
          'external'       => false,
        );
      }

      foreach ( $this->learner_portal_parse_multiline_urls( $formation->shared_docs ) as $index => $url ) {
        $path = wp_parse_url( $url, PHP_URL_PATH );
        $ext  = strtolower( pathinfo( (string) $path, PATHINFO_EXTENSION ) );
        $type = 'pdf' === $ext ? 'pdf' : 'document';
        /* ACDC 3.20.77 — Utiliser le vrai nom du fichier au lieu d'un label générique. */
        $doc_name = $path ? rawurldecode( basename( (string) $path ) ) : '';
        $label    = '' !== $doc_name ? $doc_name : ( 'Document partagé ' . ( $index + 1 ) );
        $entries[] = array(
          'label'          => $label,
          'formation'      => $formation_title,
          'session'        => $session_label,
          'type'           => $type,
          'type_label'     => $this->learner_portal_get_resource_type_label( $type ),
          'registration_id'=> (int) $registration->id,
          'resource_type'  => 'shared_doc',
          'resource_index' => (int) $index,
          'url'            => $this->learner_portal_get_document_download_url( (int) $registration->id, 'shared_doc', array( 'doc_index' => $index ) ),
          'external'       => false,
        );
      }

      foreach ( $this->learner_portal_parse_multiline_urls( $formation->shared_links ) as $index => $url ) {
        $path = wp_parse_url( $url, PHP_URL_PATH );
        $ext  = strtolower( pathinfo( (string) $path, PATHINFO_EXTENSION ) );
        $type = in_array( $ext, array( 'mp4', 'mov', 'avi', 'webm', 'm4v' ), true ) ? 'video' : 'link';
        /* ACDC 3.20.77 — Utiliser host + chemin court comme label au lieu du générique. */
        $parsed   = wp_parse_url( $url );
        $link_name = '';
        if ( ! empty( $parsed['host'] ) ) {
          $link_name = $parsed['host'] . ( isset( $parsed['path'] ) ? rawurldecode( $parsed['path'] ) : '' );
        }
        $label = '' !== $link_name ? $link_name : ( 'Ressource externe ' . ( $index + 1 ) );
        $entries[] = array(
          'label'          => $label,
          'formation'      => $formation_title,
          'session'        => $session_label,
          'type'           => $type,
          'type_label'     => $this->learner_portal_get_resource_type_label( $type ),
          'registration_id'=> (int) $registration->id,
          'resource_type'  => 'shared_link',
          'resource_index' => (int) $index,
          'url'            => $this->learner_portal_get_resource_open_url( (int) $registration->id, 'shared_link', (int) $index, $url ),
          'external'       => true,
        );
      }
    }
    return $entries;
  }

  private function learner_portal_get_document_download_url( $registration_id, $document_type, $extra = array() ) {
    $args = array_merge(
      array(
        'lp_action'      => 'download_document',
        'registration_id'=> absint( $registration_id ),
        'document_type'  => sanitize_key( $document_type ),
      ),
      $extra
    );
    $url = $this->portal_page_url( $args );
    return wp_nonce_url( $url, 'acdc_learner_download_document_' . absint( $registration_id ) . '_' . sanitize_key( $document_type ) . '_' . absint( isset( $extra['doc_index'] ) ? $extra['doc_index'] : 0 ) );
  }

  private function learner_portal_get_resource_open_url( $registration_id, $resource_type, $resource_index, $target_url ) {
    $args = array(
      'lp_action'       => 'open_resource',
      'registration_id' => absint( $registration_id ),
      'resource_type'   => sanitize_key( $resource_type ),
      'resource_index'  => absint( $resource_index ),
      'target_url'      => rawurlencode( esc_url_raw( $target_url ) ),
    );
    $url = $this->portal_page_url( $args );
    return wp_nonce_url( $url, 'acdc_learner_open_resource_' . absint( $registration_id ) . '_' . sanitize_key( $resource_type ) . '_' . absint( $resource_index ) );
  }

  private function learner_portal_has_log_event( $account_id, $event_type, $event_data = array() ) {
    global $wpdb;

    if ( empty( $this->learner_portal_log_table ) ) {
      return false;
    }

    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT event_data FROM {$this->learner_portal_log_table} WHERE account_id = %d AND event_type = %s ORDER BY id DESC LIMIT 200",
        absint( $account_id ),
        sanitize_key( $event_type )
      )
    );

    if ( empty( $rows ) ) {
      return false;
    }

    foreach ( $rows as $row ) {
      $decoded = array();
      if ( ! empty( $row->event_data ) ) {
        $decoded = json_decode( (string) $row->event_data, true );
        if ( ! is_array( $decoded ) ) {
          $decoded = array();
        }
      }
      $match = true;
      foreach ( $event_data as $key => $value ) {
        if ( ! array_key_exists( $key, $decoded ) || (string) $decoded[ $key ] !== (string) $value ) {
          $match = false;
          break;
        }
      }
      if ( $match ) {
        return true;
      }
    }

    return false;
  }

  private function learner_portal_is_document_new( $account_id, $registration_id, $document_type, $doc_index = 0 ) {
    return ! $this->learner_portal_has_log_event(
      $account_id,
      'document_downloaded',
      array(
        'registration_id' => absint( $registration_id ),
        'document_type'   => sanitize_key( $document_type ),
        'doc_index'       => absint( $doc_index ),
      )
    );
  }

  private function learner_portal_is_resource_new( $account_id, $registration_id, $resource_type, $resource_index = 0 ) {
    return ! $this->learner_portal_has_log_event(
      $account_id,
      'resource_opened',
      array(
        'registration_id' => absint( $registration_id ),
        'resource_type'   => sanitize_key( $resource_type ),
        'resource_index'  => absint( $resource_index ),
      )
    );
  }

  private function learner_portal_get_new_items_count( $email, $account_id ) {
    $count  = 0;
    $groups = $this->learner_portal_build_document_groups( $email );
    foreach ( $groups as $group ) {
      if ( empty( $group['items'] ) ) {
        continue;
      }
      foreach ( $group['items'] as $item ) {
        if ( empty( $item['available'] ) ) {
          continue;
        }
        if ( ! empty( $item['document_type'] ) && $this->learner_portal_is_document_new( $account_id, (int) $item['registration_id'], $item['document_type'], isset( $item['doc_index'] ) ? (int) $item['doc_index'] : 0 ) ) {
          $count++;
        }
      }
    }

    foreach ( $this->learner_portal_get_library_entries( $email ) as $entry ) {
      if ( $this->learner_portal_is_resource_new( $account_id, (int) $entry['registration_id'], $entry['resource_type'], (int) $entry['resource_index'] ) ) {
        $count++;
      }
    }

    return $count;
  }

  private function learner_portal_count_sessions_by_month( $sessions ) {
    $counts = array();
    foreach ( (array) $sessions as $entry ) {
      if ( empty( $entry['session'] ) ) {
        continue;
      }
      $value = ! empty( $entry['session']->start_at ) ? $entry['session']->start_at : $entry['session']->start_date;
      $ts = $this->learner_portal_parse_mysql_time( $value );
      if ( ! $ts ) {
        continue;
      }
      $key = wp_date( 'Y-m', $ts );
      if ( empty( $counts[ $key ] ) ) {
        $counts[ $key ] = array(
          'label' => wp_date( 'F Y', $ts ),
          'count' => 0,
        );
      }
      $counts[ $key ]['count']++;
    }
    return $counts;
  }

  private function learner_portal_count_sessions_by_week( $sessions ) {
    $counts = array();
    foreach ( (array) $sessions as $entry ) {
      if ( empty( $entry['session'] ) ) {
        continue;
      }
      $value = ! empty( $entry['session']->start_at ) ? $entry['session']->start_at : $entry['session']->start_date;
      $ts = $this->learner_portal_parse_mysql_time( $value );
      if ( ! $ts ) {
        continue;
      }
      $week_start = strtotime( 'monday this week', $ts );
      if ( false === $week_start ) {
        $week_start = $ts;
      }
      $key = wp_date( 'o-\WW', $week_start );
      if ( empty( $counts[ $key ] ) ) {
        $counts[ $key ] = array(
          'label' => 'Semaine du ' . wp_date( 'd/m/Y', $week_start ),
          'count' => 0,
        );
      }
      $counts[ $key ]['count']++;
    }
    return $counts;
  }

  private function learner_portal_get_resource_type_label( $type ) {
    $labels = array(
      'pdf'      => 'PDF',
      'document' => 'Document',
      'link'     => 'Lien',
      'video'    => 'Vidéo',
      'model'    => 'Modèle',
    );
    return isset( $labels[ $type ] ) ? $labels[ $type ] : ucfirst( (string) $type );
  }

  private function learner_portal_build_document_groups( $email, $registration_id = 0 ) {
    $groups = array(
      'program'      => array( 'label' => 'Programme de formation', 'items' => array() ),
      'convocations' => array( 'label' => 'Convocations', 'items' => array() ),
      'rules'        => array( 'label' => 'Règlements et livret d’accueil', 'items' => array() ),
      'analyses_besoin'  => array( 'label' => 'Analyses du besoin', 'items' => array() ),
      'results'      => array( 'label' => 'Résultats', 'items' => array() ),
      'certificates' => array( 'label' => 'Certificats et attestations', 'items' => array() ),
      'contracts'    => array( 'label' => 'Conventions / contrats', 'items' => array() ),
      'shared'       => array( 'label' => 'Documents partagés', 'items' => array() ),
    );

    foreach ( $this->learner_portal_get_access_items_for_email( $email ) as $item ) {
      $registration = $item['registration'];
      if ( $registration_id && (int) $registration->id !== absint( $registration_id ) ) {
        continue;
      }
      $formation    = $item['formation'];
      $formation_title = $formation && ! empty( $formation->title ) ? $formation->title : ( ! empty( $registration->formation_title ) ? $registration->formation_title : 'Formation' );
      $base = array(
        'formation' => $formation_title,
        'registration_id' => (int) $registration->id,
      );

      $prog_url = '';
      if ( $formation && ! empty( $formation->id ) ) {
        // Si program_file_url existe ET n'est pas une URL Manager (admin-ajax), l'utiliser directement
        if ( ! empty( $formation->program_file_url )
          && false === strpos( (string) $formation->program_file_url, 'admin-ajax.php' )
          && false === strpos( (string) $formation->program_file_url, 'admin-post.php' )
        ) {
          $prog_url = (string) $formation->program_file_url;
        } elseif ( method_exists( $this, 'get_programme_pdf_url' ) ) {
          // Générer le PDF depuis le SAAS directement
          $prog_url = $this->get_programme_pdf_url( (int) $formation->id );
        }
      }
      $groups['program']['items'][] = array_merge( $base, array(
        'label'         => 'Programme de formation',
        'document_type' => 'program',
        'doc_index'     => 0,
        'available'     => '' !== $prog_url,
        'url'           => $prog_url,
        'direct_url'    => true,
      ) );

      $groups['convocations']['items'][] = array_merge( $base, array(
        'label' => 'Convocation',
        'document_type' => 'convocation',
        'doc_index' => 0,
        'available' => ! empty( $registration->convocation_document_url ),
        'url' => ! empty( $registration->convocation_document_url ) ? $this->learner_portal_get_document_download_url( $registration->id, 'convocation' ) : '',
      ) );

      $groups['results']['items'][] = array_merge( $base, array(
        'label' => 'Résultat du positionnement',
        'document_type' => 'positioning_result',
        'doc_index' => 0,
        'available' => ! empty( $registration->positioning_result_document_url ),
        'url' => ! empty( $registration->positioning_result_document_url ) ? $this->learner_portal_get_document_download_url( $registration->id, 'positioning_result' ) : '',
      ) );
      $groups['results']['items'][] = array_merge( $base, array(
        'label' => 'Résultat de l’évaluation des acquis',
        'document_type' => 'evaluation_result',
        'doc_index' => 0,
        'available' => ! empty( $registration->evaluation_result_document_url ),
        'url' => ! empty( $registration->evaluation_result_document_url ) ? $this->learner_portal_get_document_download_url( $registration->id, 'evaluation_result' ) : '',
      ) );

      // Résultats de quiz (positionnement, live, évaluation) — générés automatiquement
      if ( ! empty( $registration->id ) ) {
        global $wpdb;
        $tbl_qz_p = $wpdb->prefix . 'acdc_of_qz_participants';
        $tbl_qz_s = $wpdb->prefix . 'acdc_of_qz_sessions';
        $tbl_qz_q = $wpdb->prefix . 'acdc_of_qz_quizzes';
        if ( $this->acdc_schema_has_column( $tbl_qz_p, 'result_document_url' ) ) {
          $quiz_results = $wpdb->get_results( $wpdb->prepare(
            "SELECT qp.id, qp.result_document_url, qp.completed_at,
                    qq.title AS quiz_title, qq.quiz_purpose
             FROM {$tbl_qz_p} qp
             INNER JOIN {$tbl_qz_s} qs ON qs.id = qp.session_id
             INNER JOIN {$tbl_qz_q} qq ON qq.id = qs.quiz_id
             WHERE qp.registration_id = %d
               AND qp.result_document_url IS NOT NULL
               AND qp.result_document_url != ''
             ORDER BY qp.completed_at DESC",
            (int) $registration->id
          ) );
          $purpose_labels = array(
            'live'        => 'Quiz live',
            'positioning' => 'Test de positionnement',
            'assessment'  => 'Evaluation des acquis',
          );
          foreach ( $quiz_results as $qr ) {
            $purpose_label = isset( $purpose_labels[ $qr->quiz_purpose ] ) ? $purpose_labels[ $qr->quiz_purpose ] : 'Quiz';
            $date_str = ! empty( $qr->completed_at ) ? date( 'd/m/Y', strtotime( $qr->completed_at ) ) : '';
            $groups['results']['items'][] = array_merge( $base, array(
              'label'         => $purpose_label . ' - ' . $qr->quiz_title . ( $date_str ? ' (' . $date_str . ')' : '' ),
              'document_type' => 'quiz_result',
              'doc_index'     => 0,
              'available'     => true,
              'url'           => (string) $qr->result_document_url,
              'direct_url'    => true,
            ) );
          }
        }
      }

      $groups['certificates']['items'][] = array_merge( $base, array(
        'label' => 'Certificat de réalisation',
        'document_type' => 'completion_certificate',
        'doc_index' => 0,
        'available' => ! empty( $registration->completion_certificate_document_url ),
        'url' => ! empty( $registration->completion_certificate_document_url ) ? $this->learner_portal_get_document_download_url( $registration->id, 'completion_certificate' ) : '',
      ) );
      $groups['certificates']['items'][] = array_merge( $base, array(
        'label' => 'Attestation de fin de formation',
        'document_type' => 'end_training_certificate',
        'doc_index' => 0,
        'available' => ! empty( $registration->end_training_certificate_document_url ),
        'url' => ! empty( $registration->end_training_certificate_document_url ) ? $this->learner_portal_get_document_download_url( $registration->id, 'end_training_certificate' ) : '',
      ) );

      // ACDC 3.21.08 — Règlement intérieur : URL depuis le profil organisme
      $profile_opts = $this->get_company_profile_options();
      $rules_url    = ! empty( $profile_opts['training_rules_url'] ) ? esc_url_raw( (string) $profile_opts['training_rules_url'] ) : '';
      $groups['rules']['items'][] = array_merge( $base, array(
        'label'         => 'Règlement intérieur',
        'document_type' => 'rulebook',
        'doc_index'     => 0,
        'available'     => ! empty( $rules_url ),
        'url'           => ! empty( $rules_url ) ? $rules_url : '',
        'direct_url'    => true,
      ) );
      $welcome_book_url = ! empty( $profile_opts['welcome_booklet_url'] ) ? esc_url_raw( (string) $profile_opts['welcome_booklet_url'] ) : '';
      $groups['rules']['items'][] = array_merge( $base, array(
        'label'         => 'Livret d’accueil',
        'document_type' => 'welcome_book',
        'doc_index'     => 0,
        'available'     => ! empty( $welcome_book_url ),
        'url'           => $welcome_book_url,
        'direct_url'    => true,
      ) );

      // ACDC 3.21.08 — Convention : chercher dans acdc_of_registration_contracts
      // via FIND_IN_SET sur learner_id du dossier d'inscription courant
      $contract_doc_url = '';
      if ( ! empty( $registration->learner_id ) ) {
        global $wpdb;
        $rc = $wpdb->get_row( $wpdb->prepare(
          "SELECT document_url FROM {$this->registration_contract_table}
           WHERE document_url != ''
             AND document_url IS NOT NULL
             AND ( FIND_IN_SET( %d, learner_ids ) > 0
                OR learner_ids = %s )
           ORDER BY updated_at DESC, id DESC LIMIT 1",
          (int) $registration->learner_id,
          (string) $registration->learner_id
        ) );
        $contract_doc_url = $rc && ! empty( $rc->document_url ) ? esc_url_raw( (string) $rc->document_url ) : '';
      }
      $groups['contracts']['items'][] = array_merge( $base, array(
        'label'         => 'Convention / contrat',
        'document_type' => 'contract',
        'doc_index'     => 0,
        'available'     => ! empty( $contract_doc_url ),
        'url'           => $contract_doc_url,
        'direct_url'    => true,
      ) );

      // ACDC 3.25.22 — Analyses du besoin de l'apprenant (PDFs générés)
      if ( ! empty( $registration->learner_id ) ) {
        global $wpdb;
        $nads_apprenant = $wpdb->get_results( $wpdb->prepare(
          "SELECT id, title, updated_at, document_url_apprenant
           FROM {$this->need_analysis_table}
           WHERE apprenant_id = %d AND is_model = 0
             AND document_url_apprenant IS NOT NULL AND document_url_apprenant != ''
           ORDER BY id DESC",
          (int) $registration->learner_id
        ) );
        foreach ( $nads_apprenant as $nad_item ) {
          $nad_date  = ! empty( $nad_item->updated_at ) ? mysql2date( 'd/m/Y', $nad_item->updated_at ) : '';
          $nad_label = ! empty( $nad_item->title ) ? (string) $nad_item->title : 'Analyse du besoin';
          if ( $nad_date ) { $nad_label .= ' (' . $nad_date . ')'; }
          $groups['analyses_besoin']['items'][] = array_merge( $base, array(
            'label'      => $nad_label,
            'document_type' => 'nad_apprenant',
            'doc_index'  => (int) $nad_item->id,
            'available'  => true,
            'url'        => esc_url_raw( (string) $nad_item->document_url_apprenant ),
            'direct_url' => true,
          ) );
        }
      }

      foreach ( $this->learner_portal_parse_multiline_urls( $formation ? $formation->shared_docs : '' ) as $index => $url ) {
        /* ACDC 3.20.77 — Vrai nom du fichier au lieu du label générique. */
        $path     = wp_parse_url( $url, PHP_URL_PATH );
        $doc_name = $path ? rawurldecode( basename( (string) $path ) ) : '';
        $label    = '' !== $doc_name ? $doc_name : ( 'Document partagé ' . ( $index + 1 ) );
        $groups['shared']['items'][] = array_merge( $base, array(
          'label' => $label,
          'document_type' => 'shared_doc',
          'doc_index' => (int) $index,
          'available' => true,
          'url' => $this->learner_portal_get_document_download_url( $registration->id, 'shared_doc', array( 'doc_index' => $index ) ),
        ) );
      }
    }

    return $groups;
  }

  private function learner_portal_get_document_count_by_group( $groups ) {
    $counts = array();
    foreach ( (array) $groups as $group_key => $group ) {
      $counts[ $group_key ] = 0;
      if ( empty( $group['items'] ) || ! is_array( $group['items'] ) ) {
        continue;
      }
      foreach ( $group['items'] as $item ) {
        if ( ! empty( $item['available'] ) ) {
          $counts[ $group_key ]++;
        }
      }
    }
    return $counts;
  }

  private function learner_portal_get_primary_document_summary( $email, $registration_id ) {
    $groups = $this->learner_portal_build_document_groups( $email, $registration_id );
    $map = array(
      'program' => 'Programme',
      'convocations' => 'Convocation',
      'results' => 'Résultats',
      'certificates' => 'Attestation',
    );
    $summary = array();
    foreach ( $map as $group_key => $label ) {
      $available = false;
      if ( ! empty( $groups[ $group_key ]['items'] ) ) {
        foreach ( $groups[ $group_key ]['items'] as $item ) {
          if ( ! empty( $item['available'] ) ) {
            $available = true;
            break;
          }
        }
      }
      $summary[] = $label . ' : ' . ( $available ? 'Disponible' : 'Bientôt disponible' );
    }
    return $summary;
  }

  private function learner_portal_get_access_notifications( $email ) {
    $notifications = array();
    $items = $this->learner_portal_get_access_items_for_email( $email );
    $now = current_time( 'timestamp' );
    foreach ( $items as $item ) {
      $formation_title = ! empty( $item['formation']->title ) ? $item['formation']->title : ( ! empty( $item['registration']->formation_title ) ? $item['registration']->formation_title : 'Formation' );
      $expiry_ts = $this->learner_portal_parse_mysql_time( $item['expires_at'] );
      if ( $expiry_ts ) {
        $days = (int) floor( ( $expiry_ts - $now ) / DAY_IN_SECONDS );
        if ( $days >= 0 && $days <= 15 ) {
          $notifications[] = array(
            'level' => $days <= 3 ? 'error' : 'warning',
            'label' => 'Expiration prochaine de l’accès',
            'message' => $formation_title . ' — accès jusqu’au ' . $this->learner_portal_format_date( $item['expires_at'], true ),
          );
        }
      }
      $docs = $this->learner_portal_get_primary_document_summary( $email, (int) $item['registration']->id );
      foreach ( $docs as $doc_message ) {
        if ( false !== strpos( $doc_message, 'Disponible' ) ) {
          $notifications[] = array(
            'level' => 'info',
            'label' => 'Document disponible',
            'message' => $formation_title . ' — ' . $doc_message,
          );
          break;
        }
      }
    }

    $sessions = $this->learner_portal_get_upcoming_sessions( $email, 3 );
    foreach ( $sessions as $entry ) {
      $session = $entry['session'];
      $notifications[] = array(
        'level' => 'info',
        'label' => 'Séance à venir',
        'message' => ( ! empty( $entry['formation']->title ) ? $entry['formation']->title : $session->title ) . ' — ' . $this->learner_portal_format_date( ! empty( $session->start_at ) ? $session->start_at : $session->start_date, true ),
      );
    }

    return array_slice( $notifications, 0, 5 );
  }

  private function learner_portal_get_document_source_url( $account_email, $registration_id, $document_type, $doc_index = 0 ) {
    $item = $this->learner_portal_get_access_item( $account_email, $registration_id );
    if ( ! $item ) {
      return '';
    }

    $registration = $item['registration'];
    $formation    = ! empty( $item['formation'] ) ? $item['formation'] : null;

    switch ( $document_type ) {
      case 'program':
        if ( $formation && ! empty( $formation->program_file_url ) ) {
          return (string) $formation->program_file_url;
        }
        // Fallback : URL de génération à la volée du programme PDF
        if ( $formation && ! empty( $formation->id ) && method_exists( $this, 'get_programme_pdf_url' ) ) {
          return (string) $this->get_programme_pdf_url( (int) $formation->id );
        }
        return '';
      case 'convocation':
        return ! empty( $registration->convocation_document_url ) ? (string) $registration->convocation_document_url : '';
      case 'positioning_result':
        return ! empty( $registration->positioning_result_document_url ) ? (string) $registration->positioning_result_document_url : '';
      case 'evaluation_result':
        return ! empty( $registration->evaluation_result_document_url ) ? (string) $registration->evaluation_result_document_url : '';
      case 'completion_certificate':
        return ! empty( $registration->completion_certificate_document_url ) ? (string) $registration->completion_certificate_document_url : '';
      case 'end_training_certificate':
        return ! empty( $registration->end_training_certificate_document_url ) ? (string) $registration->end_training_certificate_document_url : '';
      case 'shared_doc':
        $docs = $this->learner_portal_parse_multiline_urls( $formation ? $formation->shared_docs : '' );
        return isset( $docs[ $doc_index ] ) ? $docs[ $doc_index ] : '';
      default:
        return '';
    }
  }

  /**
   * URL source d'une ressource externe (lien/vidéo partagé) de la formation, avec
   * contrôle d'appartenance par e-mail. Complète get_document_source_url() (jusqu'ici
   * la méthode était appelée mais absente → fatal à l'ouverture d'une ressource).
   */
  private function learner_portal_get_resource_source_url( $account_email, $registration_id, $resource_type, $resource_index = 0 ) {
    $item = $this->learner_portal_get_access_item( $account_email, $registration_id );
    if ( ! $item ) {
      return '';
    }
    $formation = ! empty( $item['formation'] ) ? $item['formation'] : null;
    switch ( $resource_type ) {
      case 'shared_link':
      case 'video':
      case 'link':
        $links = $this->learner_portal_parse_multiline_urls( $formation ? $formation->shared_links : '' );
        return isset( $links[ $resource_index ] ) ? (string) $links[ $resource_index ] : '';
      default:
        return '';
    }
  }

  private function learner_portal_get_dashboard_stats( $email ) {
    $items     = $this->learner_portal_get_access_items_for_email( $email );
    $upcoming  = $this->learner_portal_get_upcoming_sessions( $email, 5 );
    $documents = $this->learner_portal_build_document_groups( $email );
    $document_count = 0;
    foreach ( $documents as $group ) {
      foreach ( $group['items'] as $doc ) {
        if ( ! empty( $doc['available'] ) ) {
          $document_count++;
        }
      }
    }
    return array(
      'formations' => count( $items ),
      'upcoming'   => count( $upcoming ),
      'documents'  => $document_count,
    );
  }

  private function learner_portal_get_accounts( $args = array() ) {
    global $wpdb;

    if ( ! is_array( $args ) ) {
      $args = array( 'search' => (string) $args );
    }

    $search = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
    $status = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '';

    $sql    = "SELECT a.*, l.first_name, l.usage_last_name, l.company_id, c.name AS company_name FROM {$this->learner_portal_account_table} a LEFT JOIN {$this->learner_table} l ON l.id = a.primary_learner_id LEFT JOIN {$this->company_table} c ON c.id = l.company_id";
    $where  = array();
    $params = array();

    if ( '' !== $search ) {
      $like    = '%' . $wpdb->esc_like( $search ) . '%';
      $where[] = '(a.email LIKE %s OR l.first_name LIKE %s OR l.usage_last_name LIKE %s OR c.name LIKE %s)';
      array_push( $params, $like, $like, $like, $like );
    }

    if ( '' !== $status && in_array( $status, array( 'never_activated', 'active', 'password_to_change', 'blocked', 'expired', 'disabled' ), true ) ) {
      $where[] = 'a.status = %s';
      $params[] = $status;
    }

    if ( ! empty( $where ) ) {
      $sql .= ' WHERE ' . implode( ' AND ', $where );
    }

    $sql .= ' ORDER BY a.updated_at DESC, a.id DESC';

    return ! empty( $params ) ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
  }

  private function learner_portal_get_account_overview_stats() {
    global $wpdb;

    $table = $this->learner_portal_account_table;
    $row   = $wpdb->get_row(
      "SELECT COUNT(*) AS total,
              SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_count,
              SUM(CASE WHEN status = 'never_activated' THEN 1 ELSE 0 END) AS never_activated_count,
              SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) AS blocked_count,
              SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) AS expired_count,
              SUM(CASE WHEN status = 'disabled' THEN 1 ELSE 0 END) AS disabled_count
         FROM {$table}",
      ARRAY_A
    );

    return array(
      'total'                 => isset( $row['total'] ) ? (int) $row['total'] : 0,
      'active'                => isset( $row['active_count'] ) ? (int) $row['active_count'] : 0,
      'never_activated'       => isset( $row['never_activated_count'] ) ? (int) $row['never_activated_count'] : 0,
      'blocked'               => isset( $row['blocked_count'] ) ? (int) $row['blocked_count'] : 0,
      'expired'               => isset( $row['expired_count'] ) ? (int) $row['expired_count'] : 0,
      'disabled'              => isset( $row['disabled_count'] ) ? (int) $row['disabled_count'] : 0,
      'with_recent_failures'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE failed_login_count > %d", 0 ) ),
    );
  }

  private function learner_portal_get_account_logs( $account_id, $limit = 50 ) {
    global $wpdb;
    return $wpdb->get_results(
      $wpdb->prepare(
        "SELECT * FROM {$this->learner_portal_log_table} WHERE account_id = %d ORDER BY id DESC LIMIT %d",
        absint( $account_id ),
        absint( $limit )
      )
    );
  }

}
