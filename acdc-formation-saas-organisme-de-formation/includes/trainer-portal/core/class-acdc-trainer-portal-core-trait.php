<?php
/**
 * ACDC 3.20.83 — Trait Core du portail Formateur (auth custom).
 * Calqué sur le module Apprenant ; toutes les valeurs et patterns sont symétriques.
 *
 * Tables utilisées :
 * - wp_acdc_of_trainer_portal_accounts (compte avec MDP haché)
 * - wp_acdc_of_trainer_portal_tokens   (activation, reset)
 * - wp_acdc_of_trainer_portal_sessions (cookies authentifiés)
 * - wp_acdc_of_trainer_portal_logs     (audit)
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

trait ACDC_Trainer_Portal_Core_Trait {

  /* ----------------------------------------------------------------------
   * Utilitaires généraux
   * ---------------------------------------------------------------------- */

  private function trainer_portal_now_mysql() {
    return current_time( 'mysql' );
  }

  private function trainer_portal_random_token( $length = 32 ) {
    if ( function_exists( 'random_bytes' ) ) {
      return bin2hex( random_bytes( max( 8, (int) $length ) ) );
    }
    return wp_generate_password( max( 16, (int) $length * 2 ), false, false );
  }

  private function trainer_portal_hash_token( $raw ) {
    return hash( 'sha256', (string) $raw );
  }

  private function trainer_portal_hash_password( $plain ) {
    return wp_hash_password( (string) $plain );
  }

  private function trainer_portal_check_password( $plain, $hash ) {
    return wp_check_password( (string) $plain, (string) $hash );
  }

  private function trainer_portal_get_client_ip() {
    // Sécurité : REMOTE_ADDR est la seule source non falsifiable par le client. Les en-têtes
    // X-Forwarded-For / X-Real-IP ne sont pris en compte que si un proxy de confiance est
    // explicitement déclaré (constante ACDC_TRUSTED_PROXY), sinon ils sont ignorés.
    $candidates = ( defined( 'ACDC_TRUSTED_PROXY' ) && ACDC_TRUSTED_PROXY )
      ? array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' )
      : array( 'REMOTE_ADDR' );
    foreach ( $candidates as $key ) {
      if ( ! empty( $_SERVER[ $key ] ) ) {
        $ip = is_array( $_SERVER[ $key ] ) ? reset( $_SERVER[ $key ] ) : $_SERVER[ $key ];
        $ip = trim( explode( ',', (string) $ip )[0] );
        if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
          return $ip;
        }
      }
    }
    return '';
  }

  private function trainer_portal_get_user_agent() {
    return isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( (string) $_SERVER['HTTP_USER_AGENT'], 0, 250 ) : '';
  }

  private function trainer_portal_parse_mysql_time( $mysql_string ) {
    if ( empty( $mysql_string ) ) {
      return 0;
    }
    $ts = strtotime( $mysql_string );
    return $ts ? $ts : 0;
  }

  /* ----------------------------------------------------------------------
   * Logs
   * ---------------------------------------------------------------------- */

  private function trainer_portal_log_event( $account_id, $event_type, $event_data = array(), $trainer_id = 0 ) {
    global $wpdb;
    if ( empty( $this->trainer_portal_log_table ) ) {
      return;
    }
    $wpdb->insert(
      $this->trainer_portal_log_table,
      array(
        'account_id'  => absint( $account_id ),
        'trainer_id'  => absint( $trainer_id ),
        'event_type'  => sanitize_key( $event_type ),
        'ip_address'  => $this->trainer_portal_get_client_ip(),
        'user_agent'  => $this->trainer_portal_get_user_agent(),
        'event_data'  => ! empty( $event_data ) ? wp_json_encode( $event_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '',
        'created_at'  => $this->trainer_portal_now_mysql(),
      ),
      array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
    );
  }

  /* ----------------------------------------------------------------------
   * Routing & redirections
   * ---------------------------------------------------------------------- */

  private function trainer_portal_page_url( $page_key = 'login', $args = array() ) {
    $page_id = (int) get_option( 'acdc_of_trainer_portal_page_id', 0 );
    $url = $page_id ? get_permalink( $page_id ) : home_url( '/extranet-formateur/' );
    if ( 'login' !== $page_key ) {
      $args['view'] = $page_key;
    }
    return add_query_arg( $args, $url );
  }

  private function trainer_portal_login_url( $args = array() ) {
    return $this->trainer_portal_page_url( 'login', $args );
  }

  private function trainer_portal_redirect( $page_key = 'dashboard', $message = '', $type = 'success', $args = array() ) {
    if ( '' !== $message ) {
      $args['notice']      = rawurlencode( $message );
      $args['notice_type'] = sanitize_key( $type );
    }
    wp_safe_redirect( $this->trainer_portal_page_url( $page_key, $args ) );
    exit;
  }

  /* ----------------------------------------------------------------------
   * Comptes
   * ---------------------------------------------------------------------- */

  private function trainer_portal_get_account( $account_id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->trainer_portal_account_table} WHERE id = %d", absint( $account_id ) ) );
  }

  private function trainer_portal_get_account_by_email( $email ) {
    global $wpdb;
    $email = sanitize_email( $email );
    if ( '' === $email ) {
      return null;
    }
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->trainer_portal_account_table} WHERE email = %s", $email ) );
  }

  private function trainer_portal_get_account_by_trainer_id( $trainer_id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->trainer_portal_account_table} WHERE trainer_id = %d", absint( $trainer_id ) ) );
  }

  /* ----------------------------------------------------------------------
   * Tokens
   * ---------------------------------------------------------------------- */

  private function trainer_portal_revoke_tokens( $account_id, $type ) {
    global $wpdb;
    $wpdb->update(
      $this->trainer_portal_token_table,
      array( 'revoked_at' => $this->trainer_portal_now_mysql() ),
      array(
        'account_id' => absint( $account_id ),
        'token_type' => sanitize_key( $type ),
        'revoked_at' => null,
      ),
      array( '%s' ),
      array( '%d', '%s', '%s' )
    );
  }

  private function trainer_portal_create_token( $account_id, $type, $expires_at, $meta = array() ) {
    global $wpdb;
    $this->trainer_portal_revoke_tokens( $account_id, $type );
    $raw = $this->trainer_portal_random_token( 24 );
    $wpdb->insert(
      $this->trainer_portal_token_table,
      array(
        'account_id' => absint( $account_id ),
        'token_hash' => $this->trainer_portal_hash_token( $raw ),
        'token_type' => sanitize_key( $type ),
        'expires_at' => $expires_at,
        'meta_json'  => ! empty( $meta ) ? wp_json_encode( $meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '',
        'created_at' => $this->trainer_portal_now_mysql(),
      ),
      array( '%d', '%s', '%s', '%s', '%s', '%s' )
    );
    return $raw;
  }

  private function trainer_portal_get_token_row( $token, $type ) {
    global $wpdb;
    $token_hash = $this->trainer_portal_hash_token( $token );
    return $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM {$this->trainer_portal_token_table} WHERE token_hash = %s AND token_type = %s AND consumed_at IS NULL AND revoked_at IS NULL ORDER BY id DESC LIMIT 1",
        $token_hash,
        sanitize_key( $type )
      )
    );
  }

  private function trainer_portal_consume_token( $token_id ) {
    global $wpdb;
    $wpdb->update(
      $this->trainer_portal_token_table,
      array( 'consumed_at' => $this->trainer_portal_now_mysql() ),
      array( 'id' => absint( $token_id ) ),
      array( '%s' ),
      array( '%d' )
    );
  }

  /* ----------------------------------------------------------------------
   * Sessions & cookies
   * ---------------------------------------------------------------------- */

  private function trainer_portal_cookie_name() {
    return 'acdc_trainer_portal_session';
  }

  private function trainer_portal_send_cookie( $name, $value, $expires, $path, $domain, $secure ) {
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

  private function trainer_portal_set_cookie( $token, $expires_at ) {
    $expires = $this->trainer_portal_parse_mysql_time( $expires_at );
    if ( ! $expires ) {
      $expires = current_time( 'timestamp' ) + WEEK_IN_SECONDS;
    }
    $name   = $this->trainer_portal_cookie_name();
    $path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
    $domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
    $secure = is_ssl();
    if ( headers_sent() ) {
      $_COOKIE[ $name ] = $token;
      return;
    }
    $this->trainer_portal_send_cookie( $name, $token, $expires, $path, $domain, $secure );
    $_COOKIE[ $name ] = $token;
  }

  private function trainer_portal_clear_cookie() {
    $name   = $this->trainer_portal_cookie_name();
    $path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
    $domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
    $secure = is_ssl();
    if ( ! headers_sent() ) {
      $this->trainer_portal_send_cookie( $name, '', time() - HOUR_IN_SECONDS, $path, $domain, $secure );
    }
    unset( $_COOKIE[ $name ] );
  }

  private function trainer_portal_delete_active_sessions( $account_id ) {
    global $wpdb;
    $wpdb->update(
      $this->trainer_portal_session_table,
      array( 'revoked_at' => $this->trainer_portal_now_mysql() ),
      array(
        'account_id' => absint( $account_id ),
        'revoked_at' => null,
      ),
      array( '%s' ),
      array( '%d', '%s' )
    );
  }

  private function trainer_portal_revoke_session_by_hash( $session_hash ) {
    global $wpdb;
    $wpdb->update(
      $this->trainer_portal_session_table,
      array( 'revoked_at' => $this->trainer_portal_now_mysql() ),
      array( 'session_hash' => $session_hash, 'revoked_at' => null ),
      array( '%s' ),
      array( '%s', '%s' )
    );
  }

  private function trainer_portal_create_session( $account_id ) {
    global $wpdb;
    $this->trainer_portal_delete_active_sessions( $account_id );
    $raw       = $this->trainer_portal_random_token( 32 );
    $expires   = wp_date( 'Y-m-d H:i:s', strtotime( '+7 days', current_time( 'timestamp' ) ) );
    $now_mysql = $this->trainer_portal_now_mysql();
    $wpdb->insert(
      $this->trainer_portal_session_table,
      array(
        'account_id'       => absint( $account_id ),
        'session_hash'     => $this->trainer_portal_hash_token( $raw ),
        'ip_address'       => $this->trainer_portal_get_client_ip(),
        'user_agent'       => $this->trainer_portal_get_user_agent(),
        'expires_at'       => $expires,
        'last_activity_at' => $now_mysql,
        'created_at'       => $now_mysql,
      ),
      array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
    );
    $this->trainer_portal_set_cookie( $raw, $expires );
    return $raw;
  }

  private function trainer_portal_get_current_account() {
    global $wpdb;
    $cookie_name = $this->trainer_portal_cookie_name();
    if ( empty( $_COOKIE[ $cookie_name ] ) ) {
      return null;
    }
    $raw  = (string) $_COOKIE[ $cookie_name ];
    $hash = $this->trainer_portal_hash_token( $raw );
    $session = $wpdb->get_row( $wpdb->prepare(
      "SELECT * FROM {$this->trainer_portal_session_table} WHERE session_hash = %s AND revoked_at IS NULL ORDER BY id DESC LIMIT 1",
      $hash
    ) );
    if ( ! $session ) {
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
        $this->trainer_portal_get_client_ip(),
        $this->trainer_portal_get_user_agent()
      )
    ) {
      $this->trainer_portal_revoke_session_by_hash( $hash );
      $this->trainer_portal_clear_cookie();
      return null;
    }
    $expires_ts = $this->trainer_portal_parse_mysql_time( $session->expires_at );
    if ( ! $expires_ts || $expires_ts < current_time( 'timestamp' ) ) {
      $this->trainer_portal_revoke_session_by_hash( $hash );
      $this->trainer_portal_clear_cookie();
      return null;
    }
    // Mise à jour passive de last_activity_at.
    $wpdb->update(
      $this->trainer_portal_session_table,
      array( 'last_activity_at' => $this->trainer_portal_now_mysql() ),
      array( 'id' => (int) $session->id ),
      array( '%s' ),
      array( '%d' )
    );
    $account = $this->trainer_portal_get_account( (int) $session->account_id );
    if ( ! $account ) {
      $this->trainer_portal_clear_cookie();
      return null;
    }
    return $account;
  }

  private function trainer_portal_require_auth() {
    $account = $this->trainer_portal_get_current_account();
    if ( ! $account ) {
      $this->trainer_portal_redirect( 'login', 'Veuillez vous connecter pour accéder à votre espace formateur.', 'error' );
    }
    return $account;
  }

  /* ----------------------------------------------------------------------
   * E-mails
   * ---------------------------------------------------------------------- */

  private function trainer_portal_contact_email() {
    $email = (string) get_option( 'admin_email', '' );
    return $email ? $email : '';
  }

  /**
   * Envoi via le système transactionnel central (template ACDC complet).
   */
  private function trainer_portal_send_email( $to, $subject, $body_html ) {
    $to = sanitize_email( $to );
    if ( '' === $to || '' === trim( wp_strip_all_tags( $body_html ) ) ) {
      return false;
    }
    return $this->acdc_send_transactional_email(
      $to,
      $subject,
      array(
        'greeting_name' => 'Formateur',
        'intro_html' => '',
        'body_html' => $body_html,
        'footer_notice' => 'Cet e-mail a été envoyé dans le cadre de votre espace formateur. Vos données sont traitées conformément au RGPD.',
      ),
      array(
        'source_module' => 'trainer-portal',
        'source_action' => 'portal_email',
        'email_category' => 'gestion_formateur',
        'email_audience' => 'formateur',
      )
    );
  }

  /**
   * Envoi de l'e-mail d'activation à un formateur invité.
   */
  /**
   * ACDC 3.25.169 — Construit le lien d'activation d'un compte formateur.
   *
   * Extrait de l'envoi d'e-mail pour que l'administration puisse afficher ce lien
   * à l'écran, comme du côté apprenant : quand le courriel se perd, le compte
   * reste « jamais activé » et rien ne permet plus d'y entrer.
   *
   * @param object $account Compte portail formateur.
   *
   * @return string URL d'activation, ou chaîne vide.
   */
  private function trainer_portal_build_activation_url( $account ) {
    if ( ! $account ) {
      return '';
    }
    $token = $this->trainer_portal_create_token(
      $account->id,
      'activation',
      wp_date( 'Y-m-d H:i:s', strtotime( '+7 days', current_time( 'timestamp' ) ) ),
      array( 'purpose' => 'first_activation' )
    );
    return $this->trainer_portal_login_url( array( 'view' => 'activate', 'token' => rawurlencode( $token ) ) );
  }

  private function trainer_portal_send_activation_email( $account, $trainer ) {
    if ( ! $account || ! $trainer ) {
      return false;
    }
    $display_name   = trim( (string) $trainer->first_name . ' ' . (string) $trainer->last_name );
    $activation_url = $this->trainer_portal_build_activation_url( $account );

    $body  = '<p>Bonjour ' . esc_html( $display_name ) . ',</p>';
    $body .= '<p>Votre accès à l’espace formateur ACDC Formation a été créé.</p>';
    $body .= '<div style="margin:18px 0;padding:16px;border:1px solid #E9C77C;border-radius:10px;background:#fbf8f7;">';
    $body .= '<p style="margin:0 0 8px;"><strong>Identifiant de connexion :</strong> ' . esc_html( $account->email ) . '</p>';
    $body .= '<p style="margin:0;">Pour activer votre compte et définir votre mot de passe, cliquez sur le bouton ci-dessous.</p>';
    $body .= '</div>';
    $body .= '<p><a href="' . esc_url( $activation_url ) . '" style="display:inline-block;padding:12px 18px;border-radius:10px;background:#D7A24B;color:#0B0706;text-decoration:none;font-weight:600;">Activer mon accès formateur</a></p>';
    $body .= '<p>Le lien d’activation est valable 7 jours. Au-delà, vous pourrez en demander un nouveau via la page « Mot de passe oublié ».</p>';
    $body .= '<p>Si vous rencontrez une difficulté, contactez ' . esc_html( $this->trainer_portal_contact_email() ) . '.</p>';

    return $this->trainer_portal_send_email( $account->email, 'Activation de votre espace formateur', $body );
  }

  /**
   * Envoi de l'e-mail de réinitialisation de mot de passe.
   */
  private function trainer_portal_send_reset_email( $account, $trainer = null ) {
    if ( ! $account ) {
      return false;
    }
    $token = $this->trainer_portal_create_token(
      $account->id,
      'reset',
      wp_date( 'Y-m-d H:i:s', strtotime( '+2 days', current_time( 'timestamp' ) ) ),
      array( 'purpose' => 'password_reset' )
    );
    $display_name = $trainer ? trim( (string) $trainer->first_name . ' ' . (string) $trainer->last_name ) : (string) $account->email;
    $reset_url    = $this->trainer_portal_login_url( array( 'view' => 'reset', 'token' => rawurlencode( $token ) ) );

    $body  = '<p>Bonjour ' . esc_html( $display_name ) . ',</p>';
    $body .= '<p>Vous avez demandé la réinitialisation de votre mot de passe.</p>';
    $body .= '<p><a href="' . esc_url( $reset_url ) . '" style="display:inline-block;padding:12px 18px;border-radius:10px;background:#D7A24B;color:#0B0706;text-decoration:none;font-weight:600;">Réinitialiser mon mot de passe</a></p>';
    $body .= '<p>Si vous n’êtes pas à l’origine de cette demande, ignorez simplement cet e-mail. Le lien expire automatiquement dans 48 heures.</p>';
    $body .= '<p><a href="' . esc_url( $this->trainer_portal_login_url() ) . '">Retour à la page de connexion</a></p>';

    return $this->trainer_portal_send_email( $account->email, 'Réinitialisation de votre mot de passe', $body );
  }

  /* ----------------------------------------------------------------------
   * Affichage des notices front
   * ---------------------------------------------------------------------- */

  private function trainer_portal_render_notice() {
    if ( empty( $_GET['notice'] ) ) {
      return;
    }
    $message = sanitize_text_field( wp_unslash( (string) $_GET['notice'] ) );
    $type    = isset( $_GET['notice_type'] ) ? sanitize_key( wp_unslash( (string) $_GET['notice_type'] ) ) : 'info';
    $allowed = array( 'success', 'error', 'info', 'warning' );
    if ( ! in_array( $type, $allowed, true ) ) {
      $type = 'info';
    }
    echo '<div class="acdc-alert acdc-alert-' . esc_attr( $type ) . '"><p>' . esc_html( $message ) . '</p></div>';
  }

  /* ----------------------------------------------------------------------
   * Création de la page /extranet-formateur/ à l'install
   * ---------------------------------------------------------------------- */

  private function ensure_trainer_portal_page() {
    $page_id = (int) get_option( 'acdc_of_trainer_portal_page_id', 0 );
    if ( $page_id && get_post_status( $page_id ) ) {
      return $page_id;
    }
    // Recherche d'une page existante avec le même slug (réutilisation).
    $existing = get_page_by_path( 'extranet-formateur' );
    if ( $existing ) {
      update_option( 'acdc_of_trainer_portal_page_id', (int) $existing->ID );
      return (int) $existing->ID;
    }
    $new_id = wp_insert_post( array(
      'post_title'     => 'Extranet formateur',
      'post_name'      => 'extranet-formateur',
      'post_status'    => 'publish',
      'post_type'      => 'page',
      'post_content'   => '[acdc_trainer_portal_login]',
      'comment_status' => 'closed',
      'ping_status'    => 'closed',
    ) );
    if ( $new_id && ! is_wp_error( $new_id ) ) {
      update_option( 'acdc_of_trainer_portal_page_id', (int) $new_id );
      return (int) $new_id;
    }
    return 0;
  }

  /**
   * ACDC 3.20.96 — Statistiques du tableau de bord formateur.
   *
   * Reproduction du pattern apprenant (learner_portal_get_dashboard_stats) :
   * une fonction unique qui retourne un tableau associatif consommé par le rendu.
   *
   * @param object $trainer
   * @return array{
   *   sessions_upcoming:int,
   *   qualiopi_valid:int,
   *   qualiopi_to_renew:int,
   *   availability_halfdays:int,
   *   availability_total_halfdays:int,
   *   library_total:int
   * }
   */
  private function trainer_portal_get_dashboard_stats( $trainer ) {
    global $wpdb;
    $tid = (int) $trainer->id;

    // Sessions à venir : même logique que render_trainer_portal_sessions_list mais en COUNT pur.
    $now_sql = current_time( 'mysql' );
    $sessions_upcoming = (int) $wpdb->get_var( $wpdb->prepare(
      "SELECT COUNT(DISTINCT s.id)
       FROM {$this->session_table} s
       WHERE ( s.trainer_id = %d
            OR EXISTS ( SELECT 1 FROM {$this->group_table} g WHERE g.session_id = s.id AND g.trainer_id = %d ) )
         AND COALESCE(s.start_at, CONCAT(COALESCE(s.start_date,'1970-01-01'), ' 00:00:00')) >= %s",
      $tid, $tid, $now_sql
    ) );

    // Justificatifs Qualiopi : valides (is_qualiopi_proof=1, expires_at NULL ou > NOW)
    // et à renouveler (≤ 30 jours).
    $qualiopi_valid = (int) $wpdb->get_var( $wpdb->prepare(
      "SELECT COUNT(*) FROM {$this->trainer_document_table}
       WHERE trainer_id = %d AND is_qualiopi_proof = 1
         AND ( expires_at IS NULL OR expires_at = '0000-00-00' OR expires_at >= %s )",
      $tid, current_time( 'Y-m-d' )
    ) );
    $qualiopi_to_renew = 0;
    if ( method_exists( $this, 'get_acdc_qualiopi_upcoming_expirations' ) ) {
      $qualiopi_to_renew = count( (array) $this->get_acdc_qualiopi_upcoming_expirations( $trainer, 30 ) );
    }

    // Disponibilités : nombre de demi-journées actives sur 14 (7 jours × 2 demi-journées).
    $availability_halfdays = 0;
    $availability_total    = 14;
    if ( ! empty( $trainer->availability_json ) && method_exists( $this, 'parse_trainer_availability' ) ) {
      $av = $this->parse_trainer_availability( $trainer->availability_json );
      if ( ! empty( $av['weekly'] ) && is_array( $av['weekly'] ) ) {
        foreach ( $av['weekly'] as $day ) {
          if ( ! empty( $day['morning'] ) )   { $availability_halfdays++; }
          if ( ! empty( $day['afternoon'] ) ) { $availability_halfdays++; }
        }
      }
    }

    // Bibliothèque : total documents (toutes catégories confondues).
    $library_total = (int) $wpdb->get_var( $wpdb->prepare(
      "SELECT COUNT(*) FROM {$this->trainer_document_table} WHERE trainer_id = %d",
      $tid
    ) );

    return array(
      'sessions_upcoming'           => $sessions_upcoming,
      'qualiopi_valid'              => $qualiopi_valid,
      'qualiopi_to_renew'           => $qualiopi_to_renew,
      'availability_halfdays'       => $availability_halfdays,
      'availability_total_halfdays' => $availability_total,
      'library_total'               => $library_total,
    );
  }

  /**
   * ACDC 3.20.96 — Prochaines sessions du formateur (liste limitée).
   * Réutilisée dans le bloc « Mes prochaines sessions » du dashboard.
   *
   * @param int $trainer_id
   * @param int $limit
   * @return array Liste de stdClass sessions, triée par date croissante.
   */
  private function trainer_portal_get_upcoming_sessions_list( $trainer_id, $limit = 5 ) {
    global $wpdb;
    $trainer_id = (int) $trainer_id;
    $limit      = max( 1, (int) $limit );
    $now_sql    = current_time( 'mysql' );
    $rows = $wpdb->get_results( $wpdb->prepare(
      "SELECT DISTINCT s.id, s.title, s.start_at, s.start_date, s.end_at, s.end_date, s.location, s.remote_link,
              f.title AS formation_title, f.modality AS formation_modality
       FROM {$this->session_table} s
       LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id
       WHERE ( s.trainer_id = %d
            OR EXISTS ( SELECT 1 FROM {$this->group_table} g WHERE g.session_id = s.id AND g.trainer_id = %d ) )
         AND COALESCE(s.start_at, CONCAT(COALESCE(s.start_date,'1970-01-01'), ' 00:00:00')) >= %s
       ORDER BY COALESCE(s.start_at, CONCAT(COALESCE(s.start_date,'1970-01-01'), ' 00:00:00')) ASC, s.id ASC
       LIMIT %d",
      $trainer_id, $trainer_id, $now_sql, $limit
    ) );
    return is_array( $rows ) ? $rows : array();
  }

  /**
   * ACDC 3.20.96 — Derniers justificatifs marqués « preuve Qualiopi ».
   * Réutilisée dans le bloc « Mes derniers justificatifs Qualiopi » du dashboard.
   *
   * @param int $trainer_id
   * @param int $limit
   * @return array Liste de stdClass documents, triée par date d'ajout décroissante.
   */
  private function trainer_portal_get_recent_qualiopi_docs( $trainer_id, $limit = 5 ) {
    global $wpdb;
    $trainer_id = (int) $trainer_id;
    $limit      = max( 1, (int) $limit );
    $rows = $wpdb->get_results( $wpdb->prepare(
      "SELECT id, label, file_name, category, expires_at, created_at
       FROM {$this->trainer_document_table}
       WHERE trainer_id = %d AND is_qualiopi_proof = 1
       ORDER BY COALESCE(created_at, '1970-01-01') DESC, id DESC
       LIMIT %d",
      $trainer_id, $limit
    ) );
    return is_array( $rows ) ? $rows : array();
  }

  /**
   * ACDC 3.25.99 — Auto-reparation runtime du portail formateur.
   * Aligne sur le scenario du portail apprenant (maybe_repair_learner_portal_runtime_state) :
   * verifie que la page du portail existe et est publiee, la recree sinon via
   * ensure_trainer_portal_page(), puis (re)planifie la maintenance horaire si elle a ete perdue.
   * Throttle par transient (1 h) pour ne pas peser sur chaque requete.
   */
  public function maybe_repair_trainer_portal_runtime_state() {
    if ( function_exists( 'wp_installing' ) && wp_installing() ) {
      return;
    }
    $cache_key = 'acdc_of_trainer_portal_runtime_state_checked';
    if ( false !== get_transient( $cache_key ) ) {
      $this->maybe_schedule_runtime_hook( 'acdc_of_trainer_portal_cron_maintenance', 'hourly', 180 );
      return;
    }
    $page_id = (int) get_option( 'acdc_of_trainer_portal_page_id', 0 );
    $page_ok = $page_id && 'publish' === get_post_status( $page_id );
    if ( ! $page_ok && method_exists( $this, 'ensure_trainer_portal_page' ) ) {
      $this->ensure_trainer_portal_page();
    }
    set_transient( $cache_key, 1, HOUR_IN_SECONDS );
    $this->maybe_schedule_runtime_hook( 'acdc_of_trainer_portal_cron_maintenance', 'hourly', 180 );
  }

  /**
   * ACDC 3.25.100 — Maintenance planifiee du portail formateur (cron horaire).
   * Aligne sur le scenario du portail apprenant (handle_learner_portal_cron_maintenance) :
   * 1) provisionnement automatique des comptes formateurs (equivalent de learner_portal_sync_accounts) ;
   * 2) purge des sessions et jetons DEJA expires (les tables n'avaient aucune purge).
   * N'affecte aucune ligne active.
   */
  public function handle_trainer_portal_cron_maintenance() {
    global $wpdb;
    $this->trainer_portal_provision_accounts();
    $now = current_time( 'mysql' );
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->trainer_portal_session_table} WHERE expires_at < %s", $now ) );
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->trainer_portal_token_table} WHERE expires_at < %s", $now ) );
  }

  /**
   * ACDC 3.25.100 — Provisionnement automatique des comptes du portail formateur.
   * Equivalent formateur de learner_portal_sync_accounts(), calque sur le bloc de creation
   * de compte deja utilise a la creation de contrat (dossiers-contracts) :
   * pour chaque formateur EXTERNE (is_self_trainer = 0) dont l'acces est active (access_enabled = 1)
   * et l'e-mail valide, si aucun compte portail n'existe encore, on cree un compte 'never_activated'
   * et on envoie l'e-mail d'activation via trainer_portal_send_activation_email().
   * Garde-fous : plafond d'envois par passe (evite tout envoi massif et respecte les limites SMTP
   * de l'hebergement) ; throttle 5 min ; respect de la contrainte UNIQUE sur l'e-mail.
   */
  public function trainer_portal_provision_accounts() {
    global $wpdb;
    $last = (int) get_option( 'acdc_of_trainer_portal_last_provision', 0 );
    if ( $last && ( time() - $last ) < 300 ) {
      return;
    }
    update_option( 'acdc_of_trainer_portal_last_provision', time(), false );

    $max_per_run = 15;
    $done        = 0;
    $now         = current_time( 'mysql' );

    $trainers = $wpdb->get_results( "SELECT * FROM {$this->trainer_table} WHERE access_enabled = 1 AND is_self_trainer = 0 AND email <> '' ORDER BY id ASC" );
    foreach ( (array) $trainers as $trainer ) {
      if ( $done >= $max_per_run ) {
        break;
      }
      $email = sanitize_email( (string) $trainer->email );
      if ( ! is_email( $email ) ) {
        continue;
      }
      $existing = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$this->trainer_portal_account_table} WHERE trainer_id = %d", (int) $trainer->id ) );
      if ( $existing ) {
        continue;
      }
      $email_taken = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->trainer_portal_account_table} WHERE email = %s", $email ) );
      if ( $email_taken ) {
        continue;
      }
      $wpdb->insert(
        $this->trainer_portal_account_table,
        array(
          'trainer_id'           => (int) $trainer->id,
          'email'                => $email,
          'password_hash'        => wp_hash_password( wp_generate_password( 32, true, true ) ),
          'status'               => 'never_activated',
          'must_change_password' => 1,
          'created_at'           => $now,
          'updated_at'           => $now,
        ),
        array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
      );
      $account_id = (int) $wpdb->insert_id;
      if ( ! $account_id ) {
        continue;
      }
      $account = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->trainer_portal_account_table} WHERE id = %d", $account_id ) );
      if ( $account && method_exists( $this, 'trainer_portal_send_activation_email' ) ) {
        $this->trainer_portal_send_activation_email( $account, $trainer );
      }
      $done++;
    }
  }
}
