<?php
/**
 * ACDC Auth Portal — noyau et référentiels
 *
 * Extraction incrémentale du module authentification front
 * et utilisateurs portail.
 * Version : 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Auth_Portal_Core_Trait {

private function login_page_url( $args = array() ) {
  $page_id = (int) get_option( 'acdc_of_extranet_login_page_id', 0 );
  if ( ! $page_id ) {
    $page_id = (int) get_option( 'acdc_of_login_page_id', 0 );
  }
  $url = $page_id ? get_permalink( $page_id ) : wp_login_url();
  return add_query_arg( $args, $url );
}

  private function redirect_to_login( $message = '', $type = 'error' ) {
    $args = array();
    if ( '' !== $message ) {
      $args['notice']   = rawurlencode( $message );
      $args['notice_type'] = rawurlencode( $type );
    }
    wp_safe_redirect( $this->login_page_url( $args ) );
    exit;
  }

  private function is_managed_portal_user( $user_id ) {
    return '1' === (string) get_user_meta( (int) $user_id, 'acdc_portal_managed', true );
  }

  public function maybe_restore_internal_admin_role() {
    if ( ! is_user_logged_in() ) {
      return;
    }

    $user = wp_get_current_user();
    if ( ! ( $user instanceof \WP_User ) || empty( $user->ID ) ) {
      return;
    }

    $role_label = (string) get_user_meta( (int) $user->ID, 'acdc_portal_role_label', true );
    if ( ! in_array( $role_label, array( 'Administrateur', 'Administrateur principal' ), true ) ) {
      return;
    }

    if ( user_can( $user, 'manage_options' ) ) {
      return;
    }

    /* C02 (audit 3.25.90) — On n'accorde plus le rôle WordPress "administrator" natif
       (édition de code, gestion de tous les comptes) mais un rôle dédié à capacités
       limitées. Le rôle est créé à la volée s'il manque, pour ne jamais laisser un
       compte sans rôle valide. */
    $this->ensure_acdc_portal_admin_role( false );
    $user->set_role( 'acdc_portal_admin' );
    update_user_meta( (int) $user->ID, 'acdc_portal_role_restored_at', current_time( 'mysql' ) );
  }

  /**
   * C02 (audit 3.25.90) — Construit / resynchronise le rôle "acdc_portal_admin".
   * Capacités = celles d'administrator MOINS les capacités dangereuses (édition de
   * code, installation/MAJ d'extensions et thèmes, MAJ du cœur, gestion des comptes
   * WordPress natifs, administration réseau). On conserve manage_options car TOUTES
   * les pages du plugin reposent dessus — sans cette capacité, l'admin portail
   * perdrait l'accès à l'ERP. $force = true reconstruit le rôle (resync des caps).
   */
  public function ensure_acdc_portal_admin_role( $force = false ) {
    $role = get_role( 'acdc_portal_admin' );
    if ( $role && ! $force ) {
      return;
    }
    $admin = get_role( 'administrator' );
    $caps  = ( $admin && is_array( $admin->capabilities ) ) ? $admin->capabilities : array();
    $blacklist = array( 'edit_plugins', 'edit_themes', 'edit_files', 'install_plugins', 'update_plugins', 'delete_plugins', 'install_themes', 'update_themes', 'delete_themes', 'update_core', 'update_languages', 'edit_users', 'create_users', 'delete_users', 'promote_users', 'remove_users', 'list_users', 'manage_network', 'manage_network_users', 'manage_network_plugins', 'manage_network_themes', 'manage_network_options' );
    foreach ( $blacklist as $cap ) {
      unset( $caps[ $cap ] );
    }
    $caps['read'] = true;
    $caps['manage_options'] = true;
    if ( $role ) {
      remove_role( 'acdc_portal_admin' );
    }
    add_role( 'acdc_portal_admin', 'Administrateur ACDC (portail)', $caps );
  }

  /**
   * C02 (audit 3.25.90) — Migration one-shot des comptes admin portail existants,
   * actuellement promus "administrator" natif, vers le rôle dédié à capacités limitées.
   * Idempotent (flag acdc_of_portal_admin_role_migrated_v1).
   * Garde-fous de sécurité (ne jamais verrouiller l'accès admin) :
   *  - jamais l'utilisateur WordPress n°1 ;
   *  - jamais le compte fondateur (davidcontal@gmail.com) ;
   *  - jamais un super-admin réseau ;
   *  - uniquement les comptes gérés par le portail (acdc_portal_managed = 1)
   *    et étiquetés Administrateur ;
   *  - jamais le dernier administrator natif du site (au moins un est conservé).
   */
  public function migrate_portal_admins_to_custom_role() {
    if ( '1' === (string) get_option( 'acdc_of_portal_admin_role_migrated_v1', '' ) ) {
      return;
    }
    $this->ensure_acdc_portal_admin_role( true );

    $whitelist_email = 'davidcontal@gmail.com';
    $admin_users  = get_users( array( 'role' => 'administrator', 'fields' => array( 'ID', 'user_email' ) ) );
    $total_admins = count( $admin_users );

    foreach ( $admin_users as $u ) {
      $uid = (int) $u->ID;
      if ( 1 === $uid ) {
        continue;
      }
      if ( strtolower( (string) $u->user_email ) === $whitelist_email ) {
        continue;
      }
      if ( function_exists( 'is_super_admin' ) && is_super_admin( $uid ) ) {
        continue;
      }
      if ( '1' !== (string) get_user_meta( $uid, 'acdc_portal_managed', true ) ) {
        continue;
      }
      $label = (string) get_user_meta( $uid, 'acdc_portal_role_label', true );
      if ( ! in_array( $label, array( 'Administrateur', 'Administrateur principal' ), true ) ) {
        continue;
      }
      if ( $total_admins <= 1 ) {
        break;
      }
      $user_obj = get_userdata( $uid );
      if ( ! ( $user_obj instanceof \WP_User ) ) {
        continue;
      }
      $user_obj->set_role( 'acdc_portal_admin' );
      update_user_meta( $uid, 'acdc_portal_admin_migrated_at', current_time( 'mysql' ) );
      $total_admins--;
    }

    update_option( 'acdc_of_portal_admin_role_migrated_v1', '1' );
  }

  private function get_portal_user_role_options() {
    return array(
      '' => 'Choisir une option',
      'Administrateur' => 'Administrateur',
      'Administrateur principal' => 'Administrateur principal',
    );
  }

  private function get_portal_user_default_calendar_color( $user ) {
    if ( ! ( $user instanceof \WP_User ) ) {
      return '';
    }

    $candidates = array();
    $display_name = isset( $user->display_name ) ? (string) $user->display_name : '';
    $first_name = (string) get_user_meta( (int) $user->ID, 'first_name', true );
    $last_name  = (string) get_user_meta( (int) $user->ID, 'last_name', true );

    if ( '' !== $display_name ) {
      $candidates[] = $display_name;
    }
    if ( '' !== trim( $first_name . ' ' . $last_name ) ) {
      $candidates[] = trim( $first_name . ' ' . $last_name );
    }
    if ( '' !== $first_name ) {
      $candidates[] = $first_name;
    }

    foreach ( $candidates as $candidate ) {
      $normalized = remove_accents( wp_strip_all_tags( (string) $candidate ) );
      $normalized = strtolower( preg_replace( '/\s+/', ' ', trim( $normalized ) ) );
      if ( false !== strpos( $normalized, 'ann-cecile' ) || false !== strpos( $normalized, 'ann cecile' ) ) {
        return '#a20797';
      }
      if ( false !== strpos( $normalized, 'david' ) ) {
        return '#37b6ff';
      }
    }

    return '';
  }

  private function get_portal_user_calendar_color( $user ) {
    if ( ! ( $user instanceof \WP_User ) ) {
      return '';
    }

    $stored = (string) get_user_meta( (int) $user->ID, 'acdc_calendar_color', true );
    if ( '' !== $stored ) {
      $stored = sanitize_hex_color( $stored );
      if ( ! empty( $stored ) ) {
        return $stored;
      }
    }

    return $this->get_portal_user_default_calendar_color( $user );
  }

  private function get_portal_users_calendar_color_map() {
    $map = array();
    $users = get_users( array( 'number' => 200, 'fields' => 'all' ) );

    foreach ( $users as $user ) {
      if ( ! ( $user instanceof \WP_User ) ) {
        continue;
      }

      $color = $this->get_portal_user_calendar_color( $user );
      if ( '' === $color ) {
        continue;
      }

      $aliases = array();
      $display_name = isset( $user->display_name ) ? (string) $user->display_name : '';
      $first_name = (string) get_user_meta( (int) $user->ID, 'first_name', true );
      $last_name  = (string) get_user_meta( (int) $user->ID, 'last_name', true );

      if ( '' !== $display_name ) {
        $aliases[] = $display_name;
      }
      if ( '' !== trim( $first_name . ' ' . $last_name ) ) {
        $aliases[] = trim( $first_name . ' ' . $last_name );
      }
      if ( '' !== $first_name ) {
        $aliases[] = $first_name;
      }

      foreach ( $aliases as $alias ) {
        $normalized = remove_accents( wp_strip_all_tags( (string) $alias ) );
        $normalized = strtolower( preg_replace( '/\s+/', ' ', trim( $normalized ) ) );
        if ( '' !== $normalized ) {
          $map[ $normalized ] = $color;
        }
      }
    }

    return $map;
  }

  private function get_calendar_color_for_assignee_label( $label ) {
    $normalized = remove_accents( wp_strip_all_tags( (string) $label ) );
    $normalized = strtolower( preg_replace( '/\s+/', ' ', trim( $normalized ) ) );
    if ( '' === $normalized ) {
      return '';
    }

    $map = $this->get_portal_users_calendar_color_map();
    if ( isset( $map[ $normalized ] ) ) {
      return $map[ $normalized ];
    }

    foreach ( $map as $alias => $color ) {
      if ( false !== strpos( $normalized, $alias ) || false !== strpos( $alias, $normalized ) ) {
        return $color;
      }
    }

    return '';
  }

  private function get_calendar_text_color_for_background( $hex_color ) {
    $hex = sanitize_hex_color( (string) $hex_color );
    if ( empty( $hex ) ) {
      return '';
    }

    $hex = ltrim( $hex, '#' );
    if ( 3 === strlen( $hex ) ) {
      $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    $r = hexdec( substr( $hex, 0, 2 ) );
    $g = hexdec( substr( $hex, 2, 2 ) );
    $b = hexdec( substr( $hex, 4, 2 ) );
    $luma = ( ( 0.299 * $r ) + ( 0.587 * $g ) + ( 0.114 * $b ) );

    return $luma >= 186 ? '#0C2D52' : '#FFFFFF';
  }

  private function get_calendar_hex_rgb_triplet( $hex_color ) {
    $hex = sanitize_hex_color( (string) $hex_color );
    if ( empty( $hex ) ) {
      return null;
    }

    $hex = ltrim( $hex, '#' );
    if ( 3 === strlen( $hex ) ) {
      $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    return array(
      hexdec( substr( $hex, 0, 2 ) ),
      hexdec( substr( $hex, 2, 2 ) ),
      hexdec( substr( $hex, 4, 2 ) ),
    );
  }

  private function get_calendar_past_event_style( $hex_color, $text_color = '#0C2D52' ) {
    $rgb = $this->get_calendar_hex_rgb_triplet( $hex_color );
    if ( empty( $rgb ) ) {
      return 'background:repeating-linear-gradient(-45deg, rgba(197,162,83,0.14) 0, rgba(197,162,83,0.14) 8px, rgba(197,162,83,0.24) 8px, rgba(197,162,83,0.24) 16px);border-color:rgba(197,162,83,0.45);color:#0C2D52;';
    }

    $text = sanitize_hex_color( (string) $text_color );
    if ( empty( $text ) ) {
      $text = '#0C2D52';
    }

    return sprintf(
      'background:repeating-linear-gradient(-45deg, rgba(%1$d,%2$d,%3$d,0.16) 0, rgba(%1$d,%2$d,%3$d,0.16) 8px, rgba(%1$d,%2$d,%3$d,0.30) 8px, rgba(%1$d,%2$d,%3$d,0.30) 16px);border-color:rgba(%1$d,%2$d,%3$d,0.50);color:%4$s;',
      (int) $rgb[0],
      (int) $rgb[1],
      (int) $rgb[2],
      esc_attr( $text )
    );
  }

    private function get_portal_users( $search = '' ) {
    $args = array(
      'orderby' => 'display_name',
      'order'  => 'ASC',
      'number' => 200,
      'fields' => 'all',
    );
    if ( '' !== trim( (string) $search ) ) {
      $args['search'] = '*' . trim( (string) $search ) . '*';
      $args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
    }
    $users = get_users( $args );
    $rows = array();
    foreach ( $users as $user ) {
      $is_managed = $this->is_managed_portal_user( $user->ID );
      $primary_role = '';
      if ( ! empty( $user->roles ) && is_array( $user->roles ) ) {
        $primary_role = (string) reset( $user->roles );
      }
      $is_native_visible = in_array( $primary_role, array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ), true );
      if ( ! $is_managed && ! $is_native_visible ) {
        continue;
      }
      $label = (string) get_user_meta( $user->ID, 'acdc_portal_role_label', true );
      if ( '' === $label ) {
        $label = 'administrator' === $primary_role ? 'Administrateur WordPress' : ucfirst( str_replace( '_', ' ', $primary_role ) );
      }
      $source = $is_managed ? 'Extranet' : 'WordPress';
      $rows[] = (object) array(
        'id' => (int) $user->ID,
        'photo_url' => (string) get_user_meta( $user->ID, 'acdc_user_photo_url', true ),
        'gender' => (string) get_user_meta( $user->ID, 'acdc_user_gender', true ),
        'first_name' => (string) get_user_meta( $user->ID, 'first_name', true ),
        'last_name' => (string) get_user_meta( $user->ID, 'last_name', true ),
        'email' => (string) $user->user_email,
        'phone' => (string) get_user_meta( $user->ID, 'acdc_user_phone', true ),
        'birth_date' => (string) get_user_meta( $user->ID, 'acdc_user_birth_date', true ),
        'calendar_color' => $this->get_portal_user_calendar_color( $user ),
        'role_label' => $label,
        'access_enabled' => user_can( $user, 'read' ) ? 1 : 0,
        'source_label' => $source,
        'is_managed' => $is_managed ? 1 : 0,
        'is_wordpress_native' => $is_managed ? 0 : 1,
      );
    }
    return $rows;
  }

    private function get_portal_user( $id ) {
    $user = get_user_by( 'id', (int) $id );
    if ( ! $user ) {
      return null;
    }
    $is_managed = $this->is_managed_portal_user( $user->ID );
    $primary_role = '';
    if ( ! empty( $user->roles ) && is_array( $user->roles ) ) {
      $primary_role = (string) reset( $user->roles );
    }
    $is_native_visible = in_array( $primary_role, array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ), true );
    if ( ! $is_managed && ! $is_native_visible ) {
      return null;
    }
    $label = (string) get_user_meta( $user->ID, 'acdc_portal_role_label', true );
    if ( '' === $label ) {
      $label = 'administrator' === $primary_role ? 'Administrateur WordPress' : ucfirst( str_replace( '_', ' ', $primary_role ) );
    }
    return (object) array(
      'id' => (int) $user->ID,
      'photo_url' => (string) get_user_meta( $user->ID, 'acdc_user_photo_url', true ),
      'gender' => (string) get_user_meta( $user->ID, 'acdc_user_gender', true ),
      'first_name' => (string) get_user_meta( $user->ID, 'first_name', true ),
      'last_name' => (string) get_user_meta( $user->ID, 'last_name', true ),
      'email' => (string) $user->user_email,
      'phone' => (string) get_user_meta( $user->ID, 'acdc_user_phone', true ),
      'birth_date' => (string) get_user_meta( $user->ID, 'acdc_user_birth_date', true ),
      'calendar_color' => $this->get_portal_user_calendar_color( $user ),
      'role_label' => $label,
      'source_label' => $is_managed ? 'Extranet' : 'WordPress',
      'is_managed' => $is_managed ? 1 : 0,
      'is_wordpress_native' => $is_managed ? 0 : 1,
    );
  }

  private function generate_unique_username_from_email( $email, $first_name = '', $last_name = '' ) {
    $base = '';
    if ( ! empty( $email ) && false !== strpos( $email, '@' ) ) {
      $base = strstr( $email, '@', true );
    }
    if ( '' === $base ) {
      $base = sanitize_user( trim( $first_name . '.' . $last_name ), true );
    }
    if ( '' === $base ) {
      $base = 'utilisateur';
    }
    $base = sanitize_user( $base, true );
    if ( '' === $base ) {
      $base = 'utilisateur';
    }
    $candidate = $base;
    $i = 2;
    while ( username_exists( $candidate ) ) {
      $candidate = $base . $i;
      $i++;
    }
    return $candidate;
  }

}
