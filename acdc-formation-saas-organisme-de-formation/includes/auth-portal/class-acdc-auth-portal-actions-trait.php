<?php
/**
 * ACDC Auth Portal — actions et traitements
 *
 * Extraction incrémentale du module authentification front
 * et utilisateurs portail.
 * Version : 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Auth_Portal_Actions_Trait {

  public function handle_front_login() {
    $nonce = isset( $_POST['_wpnonce'] ) ? wp_unslash( $_POST['_wpnonce'] ) : '';
    if ( ! wp_verify_nonce( $nonce, 'acdc_front_login' ) ) {
      $this->redirect_to_login( 'Jeton de sécurité invalide.', 'error' );
    }

    // --- SEC-01 : Protection brute force par IP ---
    $raw_ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
    $ip_key   = 'acdc_login_fail_' . md5( $raw_ip );
    $attempts = (int) get_transient( $ip_key );
    if ( $attempts >= 5 ) {
      $this->redirect_to_login( 'Trop de tentatives échouées. Veuillez patienter 15 minutes.', 'error' );
    }
    // --- fin SEC-01 ---

    $username = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
    $password = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '';
    $remember = ! empty( $_POST['rememberme'] );

    $creds = array(
      'user_login'   => $username,
      'user_password' => $password,
      'remember'     => $remember,
    );

    $user = wp_signon( $creds, is_ssl() );

    if ( is_wp_error( $user ) ) {
      // Incrémenter le compteur de tentatives (TTL 15 min)
      set_transient( $ip_key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
      $this->redirect_to_login( 'Connexion impossible. Vérifiez vos identifiants.', 'error' );
    }

    if ( ! user_can( $user, 'manage_options' ) ) {
      wp_logout();
      // Compter aussi les tentatives avec un compte sans droits suffisants
      set_transient( $ip_key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
      $this->redirect_to_login( 'Votre compte ne dispose pas des droits nécessaires.', 'error' );
    }

    // Succès : réinitialiser le compteur
    delete_transient( $ip_key );
    wp_set_current_user( $user->ID );
    $this->redirect_to_portal( 'dashboard', 'Connexion réussie.', 'success' );
  }

  public function handle_front_logout() {
    if ( ! is_user_logged_in() ) {
      $this->redirect_to_login();
    }

    $nonce = isset( $_GET['_wpnonce'] ) ? wp_unslash( $_GET['_wpnonce'] ) : '';
    if ( ! wp_verify_nonce( $nonce, 'acdc_front_logout' ) ) {
      $this->redirect_to_portal( 'dashboard', 'Jeton de sécurité invalide.', 'error' );
    }

    wp_logout();
    $this->redirect_to_login( 'Vous êtes déconnecté.', 'success' );
  }

public function handle_save_portal_user() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }
  check_admin_referer( 'acdc_save_portal_user' );

  $user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
  $input = isset( $_POST['portal_user'] ) && is_array( $_POST['portal_user'] ) ? wp_unslash( $_POST['portal_user'] ) : array();

  $first_name = isset( $input['first_name'] ) ? sanitize_text_field( $input['first_name'] ) : '';
  $last_name = isset( $input['last_name'] ) ? sanitize_text_field( $input['last_name'] ) : '';
  $email   = isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '';
  $phone   = isset( $input['phone'] ) ? sanitize_text_field( $input['phone'] ) : '';
  $role_label = isset( $input['role_label'] ) ? sanitize_text_field( $input['role_label'] ) : '';

  $is_admin_page = is_admin() && isset( $_POST['page'] ) && 'acdc-of-users' === $_POST['page'];
  $form_extra = array(
    'action' => $user_id ? 'edit' : 'new',
    'item_id' => $user_id,
  );

  if ( '' === $first_name || '' === $last_name || '' === $email || '' === $phone || '' === $role_label ) {
    $required_fields = array();
    foreach ( array( 'first_name', 'last_name', 'email', 'phone', 'role_label' ) as $field_key ) {
      if ( empty( $input[ $field_key ] ) ) {
        $required_fields[] = $field_key;
      }
    }
    $this->acdc_store_form_state( 'portal_user', $input, $required_fields );
    $message = 'Prénom, nom, e-mail, téléphone et rôle sont obligatoires.';
    if ( $is_admin_page ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-users&action=' . ( $user_id ? 'edit&item_id=' . $user_id : 'new' ) . '&notice=' . rawurlencode( $message ) . '&notice_type=error' ) );
      exit;
    }
    $this->redirect_to_portal( 'users', $message, 'error', $form_extra );
  }

  if ( ! is_email( $email ) ) {
    $this->acdc_store_form_state( 'portal_user', $input, array( 'email' ) );
    $message = 'Veuillez renseigner une adresse e-mail valide.';
    if ( $is_admin_page ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-users&action=' . ( $user_id ? 'edit&item_id=' . $user_id : 'new' ) . '&notice=' . rawurlencode( $message ) . '&notice_type=error' ) );
      exit;
    }
    $this->redirect_to_portal( 'users', $message, 'error', $form_extra );
  }

  $existing_user_with_email = email_exists( $email );
  if ( $existing_user_with_email && (int) $existing_user_with_email !== (int) $user_id ) {
    $this->acdc_store_form_state( 'portal_user', $input, array( 'email' ) );
    $message = 'Cette adresse e-mail est déjà utilisée par un autre utilisateur.';
    if ( $is_admin_page ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-users&action=' . ( $user_id ? 'edit&item_id=' . $user_id : 'new' ) . '&notice=' . rawurlencode( $message ) . '&notice_type=error' ) );
      exit;
    }
    $this->redirect_to_portal( 'users', $message, 'error', $form_extra );
  }

  $photo_url = $this->handle_optional_upload( 'portal_user_photo' );
  if ( empty( $photo_url ) && isset( $input['photo_url'] ) ) {
    $photo_url = esc_url_raw( $input['photo_url'] );
  }
  if ( empty( $photo_url ) && $user_id ) {
    $photo_url = (string) get_user_meta( $user_id, 'acdc_user_photo_url', true );
  }

  $display_name = trim( $first_name . ' ' . $last_name );
  if ( '' === $display_name ) {
    $display_name = $email;
  }

  $existing_wp_user = $user_id ? get_user_by( 'id', $user_id ) : false;
  $is_existing_managed_user = $existing_wp_user instanceof \WP_User ? $this->is_managed_portal_user( $existing_wp_user->ID ) : false;

  $userdata = array(
    'user_email'  => $email,
    'first_name'  => $first_name,
    'last_name'  => $last_name,
    'display_name' => $display_name,
    'nickname'   => $display_name,
  );

  if ( $user_id ) {
    if ( ! $is_existing_managed_user ) {
      $message = 'Les comptes WordPress natifs ne sont pas modifiables depuis ce module. Créez ou modifiez uniquement des utilisateurs gérés par l’extranet.';
      if ( $is_admin_page ) {
        wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-users&notice=' . rawurlencode( $message ) . '&notice_type=error' ) );
        exit;
      }
      $this->redirect_to_portal( 'users', $message, 'error' );
    }
    $userdata['ID'] = $user_id;
    $result = wp_update_user( $userdata );
    $message = 'Utilisateur mis à jour.';
  } else {
    $userdata['user_login'] = $this->generate_unique_username_from_email( $email, $first_name, $last_name );
    $userdata['user_pass'] = wp_generate_password( 16, true, true );
    $userdata['role']     = 'subscriber';
    $result = wp_insert_user( $userdata );
    $message = 'Utilisateur créé.';
  }

  if ( is_wp_error( $result ) ) {
    $this->acdc_store_form_state( 'portal_user', $input );
    $error_message = $result->get_error_message();
    if ( $is_admin_page ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-users&action=' . ( $user_id ? 'edit&item_id=' . $user_id : 'new' ) . '&notice=' . rawurlencode( $error_message ) . '&notice_type=error' ) );
      exit;
    }
    $this->redirect_to_portal( 'users', $error_message, 'error', $form_extra );
  }

  $saved_user_id = (int) $result;
  update_user_meta( $saved_user_id, 'acdc_portal_managed', '1' );
  update_user_meta( $saved_user_id, 'acdc_portal_role_label', $role_label );
  update_user_meta( $saved_user_id, 'acdc_user_photo_url', $photo_url );
  update_user_meta( $saved_user_id, 'acdc_user_gender', isset( $input['gender'] ) ? sanitize_text_field( $input['gender'] ) : '' );
  update_user_meta( $saved_user_id, 'acdc_user_phone', $phone );
  update_user_meta( $saved_user_id, 'acdc_user_birth_date', isset( $input['birth_date'] ) ? sanitize_text_field( $input['birth_date'] ) : '' );
  $calendar_color = isset( $input['calendar_color'] ) ? sanitize_hex_color( $input['calendar_color'] ) : '';
  if ( '' === $calendar_color ) {
    delete_user_meta( $saved_user_id, 'acdc_calendar_color' );
  } else {
    update_user_meta( $saved_user_id, 'acdc_calendar_color', $calendar_color );
  }

  if ( $is_admin_page ) {
    $query = array(
      'page'    => 'acdc-of-users',
      'notice'   => rawurlencode( $message ),
      'notice_type' => 'success',
    );
    if ( ! empty( $_POST['save_and_add'] ) ) {
      $query['action'] = 'new';
    }
    wp_safe_redirect( add_query_arg( $query, admin_url( 'admin.php' ) ) );
    exit;
  }

  $extra = ! empty( $_POST['save_and_add'] ) ? array( 'action' => 'new' ) : array();
  $this->redirect_to_portal( 'users', $message, 'success', $extra );
}


public function handle_save_portal_user_color() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }

  $user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
  if ( ! $user_id ) {
    wp_die( esc_html( 'Utilisateur introuvable.' ) );
  }

  check_admin_referer( 'acdc_save_portal_user_color_' . $user_id );

  $color = isset( $_POST['calendar_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['calendar_color'] ) ) : '';
  if ( '' === $color ) {
    delete_user_meta( $user_id, 'acdc_calendar_color' );
    $message = 'Couleur calendrier réinitialisée.';
  } else {
    update_user_meta( $user_id, 'acdc_calendar_color', $color );
    $message = 'Couleur calendrier enregistrée.';
  }

  $is_admin_page = is_admin() && isset( $_POST['page'] ) && 'acdc-of-users' === wp_unslash( $_POST['page'] );
  if ( $is_admin_page ) {
    wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-users&notice=' . rawurlencode( $message ) . '&notice_type=success' ) );
    exit;
  }

  $this->redirect_to_portal( 'users', $message, 'success' );
}

public function handle_delete_portal_user() {
  if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html( 'Accès refusé.' ) );
  }

  $user_id = isset( $_GET['user_id'] ) ? absint( wp_unslash( $_GET['user_id'] ) ) : 0;
  if ( ! $user_id ) {
    wp_die( esc_html( 'Utilisateur introuvable.' ) );
  }

  check_admin_referer( 'acdc_delete_portal_user_' . $user_id );

  if ( get_current_user_id() === $user_id ) {
    $message = 'Vous ne pouvez pas supprimer votre propre compte depuis ce module.';
    if ( is_admin() ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-users&notice=' . rawurlencode( $message ) . '&notice_type=error' ) );
      exit;
    }
    $this->redirect_to_portal( 'users', $message, 'error' );
  }

  if ( ! $this->is_managed_portal_user( $user_id ) ) {
    $message = 'La suppression est réservée aux utilisateurs gérés par l’extranet.';
    if ( is_admin() ) {
      wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-users&notice=' . rawurlencode( $message ) . '&notice_type=error' ) );
      exit;
    }
    $this->redirect_to_portal( 'users', $message, 'error' );
  }

  require_once ABSPATH . 'wp-admin/includes/user.php';
  $deleted = wp_delete_user( $user_id );
  $message = $deleted ? 'Utilisateur supprimé.' : 'Impossible de supprimer cet utilisateur.';
  $notice_type = $deleted ? 'success' : 'error';

  if ( is_admin() ) {
    wp_safe_redirect( admin_url( 'admin.php?page=acdc-of-users&notice=' . rawurlencode( $message ) . '&notice_type=' . $notice_type ) );
    exit;
  }

  $this->redirect_to_portal( 'users', $message, $notice_type );
}

}
