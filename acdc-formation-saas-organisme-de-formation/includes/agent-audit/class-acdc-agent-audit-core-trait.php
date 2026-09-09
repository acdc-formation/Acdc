<?php
/**
 * ACDC Exploration contrôlée — noyau.
 *
 * @since 3.16.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Agent_Audit_Core_Trait {

  private function get_agent_audit_default_prompt_template() {
    return implode( "\n", array(
      "Rôle",
      "Tu es un agent de recette fonctionnelle chargé d'explorer intégralement un site WordPress ACDC SAAS OF.",
      "",
      "Accès",
      "- URL de connexion : {{LOGIN_URL}}",
      "- URL du tableau de bord : {{PORTAL_URL}}",
      "- Identifiant : {{USERNAME}}",
      "- Mot de passe : {{PASSWORD}}",
      "- Expiration du compte : {{EXPIRES}}",
      "",
      "Objectif",
      "Explorer le plugin et le site de manière méthodique afin d'identifier les bugs, incohérences, régressions, problèmes d'ergonomie, actions cassées, liens morts, erreurs d'affichage, formulaires défaillants, téléchargements non fonctionnels, permissions incohérentes et comportements inattendus.",
      "",
      "Règles impératives",
      "- Utiliser uniquement le compte dédié fourni.",
      "- Ne pas modifier les réglages globaux non nécessaires.",
      "- Ne pas supprimer les données métiers existantes.",
      "- Créer uniquement des données de test identifiables si un test l'exige.",
      "- Respecter le périmètre suivant : {{SCOPE_NOTES}}",
      "- Instructions complémentaires : {{EXTRA_INSTRUCTIONS}}",
      "",
      "Parcours à explorer",
      "1. Connexion et déconnexion.",
      "2. Tableau de bord et blocs cliquables.",
      "3. Menus et sous-menus.",
      "4. Répertoires : apprenants, groupes, entreprises, financeurs, formations, formateurs, utilisateurs.",
      "5. CRM commercial : prospects, suivi commercial, calendrier.",
      "6. Inscriptions, dossiers, conventions / contrats.",
      "7. Séances : calendrier, création, validation, listes.",
      "8. Quiz, tests, enquêtes, évaluations, sessions questionnaires et résultats.",
      "9. Documents, devis, factures, téléchargements et exports.",
      "10. Communication marketing.",
      "11. Outils, statistiques, réglages et profils.",
      "",
      "Format attendu du compte rendu",
      "Pour chaque anomalie :",
      "- Module / écran",
      "- Chemin exact pour reproduire",
      "- Action effectuée",
      "- Résultat observé",
      "- Résultat attendu",
      "- Gravité : bloquant / majeur / mineur / cosmétique",
      "- Capture ou élément de preuve si disponible",
      "",
      "Sortie finale",
      "Produire un rapport structuré, priorisé, directement exploitable pour correction.",
    ) );
  }

  private function get_agent_audit_settings_defaults() {
    return array(
      'mode_enabled'            => '0',
      'block_wp_mail'           => '1',
      'show_admin_banner'       => '1',
      'default_role'            => 'subscriber',
      'default_duration_hours'  => 24,
      'scope_notes'             => "Exploration complète autorisée sur environnement de test. Ne pas supprimer les données métiers existantes. Créer uniquement des données de test identifiables. Bloquer les envois réels d'e-mails pendant l'audit.",
      'extra_instructions'      => "Tester les menus, formulaires, modales, téléchargements, exports, rôles, pages front et extranet. Documenter chaque bug avec chemin d'accès, résultat observé, résultat attendu et niveau de gravité.",
      'custom_prompt_template'  => $this->get_agent_audit_default_prompt_template(),
    );
  }

  private function get_agent_audit_settings() {
    $defaults = $this->get_agent_audit_settings_defaults();
    $saved = get_option( 'acdc_of_agent_audit_settings', array() );
    if ( ! is_array( $saved ) ) {
      $saved = array();
    }
    $settings = wp_parse_args( $saved, $defaults );
    $settings['default_duration_hours'] = max( 1, min( 168, absint( $settings['default_duration_hours'] ) ) );
    $settings['default_role'] = in_array( $settings['default_role'], array( 'administrator', 'subscriber' ), true ) ? $settings['default_role'] : 'subscriber';
    return $settings;
  }

  private function get_agent_audit_role_options() {
    return array(
      'administrator' => 'Administrateur de test',
      'subscriber'    => 'Lecture limitée',
    );
  }

  private function get_agent_audit_users() {
    $users = get_users(
      array(
        'meta_key'   => '_acdc_agent_audit_managed',
        'meta_value' => '1',
        'orderby'    => 'registered',
        'order'      => 'DESC',
      )
    );

    $rows = array();
    foreach ( $users as $user ) {
      if ( ! ( $user instanceof WP_User ) ) {
        continue;
      }
      $expires = (int) get_user_meta( $user->ID, '_acdc_agent_audit_expires_at', true );
      $disabled = '1' === (string) get_user_meta( $user->ID, '_acdc_agent_audit_disabled', true );
      $role_label = (string) get_user_meta( $user->ID, '_acdc_agent_audit_role_label', true );
      $rows[] = array(
        'id'            => (int) $user->ID,
        'login'         => (string) $user->user_login,
        'email'         => (string) $user->user_email,
        'display_name'  => (string) $user->display_name,
        'registered'    => (string) $user->user_registered,
        'expires_at'    => $expires,
        'is_expired'    => $expires > 0 && $expires < time(),
        'is_disabled'   => $disabled,
        'role_label'    => $role_label ? $role_label : ( ! empty( $user->roles[0] ) ? $user->roles[0] : '—' ),
      );
    }
    return $rows;
  }

  private function build_agent_audit_email() {
    $host = wp_parse_url( home_url(), PHP_URL_HOST );
    $host = is_string( $host ) ? strtolower( $host ) : 'example.local';
    $host = preg_replace( '/[^a-z0-9.-]/', '', $host );
    if ( '' === $host || false === strpos( $host, '.' ) ) {
      $host = 'example.local';
    }
    return 'agent-audit-' . gmdate( 'YmdHis' ) . '@' . $host;
  }

  private function build_agent_audit_username() {
    $base = 'agent_audit_' . gmdate( 'ymd_His' );
    $candidate = $base;
    $i = 1;
    while ( username_exists( $candidate ) ) {
      $candidate = $base . '_' . $i;
      $i++;
    }
    return $candidate;
  }

  private function store_agent_audit_credentials( $payload ) {
    set_transient( 'acdc_of_agent_audit_last_credentials', $payload, DAY_IN_SECONDS );
  }

  private function get_agent_audit_last_credentials() {
    $payload = get_transient( 'acdc_of_agent_audit_last_credentials' );
    return is_array( $payload ) ? $payload : array();
  }

  private function clear_agent_audit_last_credentials() {
    delete_transient( 'acdc_of_agent_audit_last_credentials' );
  }

  private function get_agent_audit_status_label( $row ) {
    if ( ! empty( $row['is_disabled'] ) ) {
      return 'Désactivé';
    }
    if ( ! empty( $row['is_expired'] ) ) {
      return 'Expiré';
    }
    return 'Actif';
  }

  private function is_agent_audit_mode_enabled() {
    $settings = $this->get_agent_audit_settings();
    return '1' === (string) $settings['mode_enabled'];
  }

  public function maybe_block_mail_during_agent_audit( $return, $atts ) {
    $settings = $this->get_agent_audit_settings();
    if ( '1' !== (string) $settings['mode_enabled'] || '1' !== (string) $settings['block_wp_mail'] ) {
      return $return;
    }

    $this->log_action_event(
      'agent_audit_blocked_mail',
      'agent_audit',
      0,
      'success',
      array(
        'to'      => isset( $atts['to'] ) ? $atts['to'] : '',
        'subject' => isset( $atts['subject'] ) ? sanitize_text_field( (string) $atts['subject'] ) : '',
      )
    );

    return true;
  }

  public function maybe_block_agent_audit_login( $user, $username, $password ) {
    if ( $user instanceof WP_User ) {
      $managed = '1' === (string) get_user_meta( $user->ID, '_acdc_agent_audit_managed', true );
      if ( $managed ) {
        $disabled = '1' === (string) get_user_meta( $user->ID, '_acdc_agent_audit_disabled', true );
        $expires_at = (int) get_user_meta( $user->ID, '_acdc_agent_audit_expires_at', true );
        if ( $disabled || ( $expires_at > 0 && $expires_at < time() ) ) {
          return new WP_Error( 'acdc_agent_audit_disabled', 'Ce compte d\'exploration est expiré ou désactivé.' );
        }
      }
    }
    return $user;
  }

  public function maybe_disable_expired_agent_audit_users() {
    if ( ! is_user_logged_in() ) {
      return;
    }

    $user = wp_get_current_user();
    if ( ! ( $user instanceof WP_User ) || empty( $user->ID ) ) {
      return;
    }

    if ( '1' !== (string) get_user_meta( $user->ID, '_acdc_agent_audit_managed', true ) ) {
      return;
    }

    $expires_at = (int) get_user_meta( $user->ID, '_acdc_agent_audit_expires_at', true );
    if ( $expires_at <= 0 || $expires_at >= time() ) {
      return;
    }

    update_user_meta( $user->ID, '_acdc_agent_audit_disabled', '1' );
    wp_logout();
    wp_safe_redirect( $this->login_page_url( array( 'notice' => rawurlencode( 'Le compte d\'exploration a expiré.' ), 'notice_type' => 'error' ) ) );
    exit;
  }

  public function render_agent_audit_mode_notice() {
    if ( ! current_user_can( 'manage_options' ) ) {
      return;
    }
    $settings = $this->get_agent_audit_settings();
    if ( '1' !== (string) $settings['mode_enabled'] || '1' !== (string) $settings['show_admin_banner'] ) {
      return;
    }
    $message = 'Mode exploration sécurisé actif';
    if ( '1' === (string) $settings['block_wp_mail'] ) {
      $message .= ' — envois e-mail WordPress bloqués';
    }
    echo '<div class="notice notice-warning"><p><strong>ACDC SAAS OF</strong> — ' . esc_html( $message ) . '.</p></div>';
  }

  private function get_agent_audit_prompt( $credentials = array() ) {
    $login_url = $this->login_page_url();
    $portal_url = $this->portal_page_url();
    $settings = $this->get_agent_audit_settings();

    $username = ! empty( $credentials['username'] ) ? $credentials['username'] : '__IDENTIFIANT__';
    $password = ! empty( $credentials['password'] ) ? $credentials['password'] : '__MOT_DE_PASSE__';
    $expires  = ! empty( $credentials['expires_label'] ) ? $credentials['expires_label'] : '__DATE_EXPIRATION__';

    $prompt = array(
      'Rôle',
      'Tu es un agent de recette fonctionnelle chargé d\'explorer intégralement un site WordPress ACDC SAAS OF.',
      '',
      'Accès',
      '- URL de connexion : ' . $login_url,
      '- URL du tableau de bord : ' . $portal_url,
      '- Identifiant : ' . $username,
      '- Mot de passe : ' . $password,
      '- Expiration du compte : ' . $expires,
      '',
      'Objectif',
      'Explorer le plugin et le site de manière méthodique afin d\'identifier les bugs, incohérences, régressions, problèmes d\'ergonomie, actions cassées, liens morts, erreurs d\'affichage, formulaires défaillants, téléchargements non fonctionnels, permissions incohérentes et comportements inattendus.',
      '',
      'Règles impératives',
      '- Utiliser uniquement le compte dédié fourni.',
      '- Ne pas modifier les réglages globaux non nécessaires.',
      '- Ne pas supprimer les données métiers existantes.',
      '- Créer uniquement des données de test identifiables si un test l\'exige.',
      '- Respecter le périmètre suivant : ' . $settings['scope_notes'],
      '- Instructions complémentaires : ' . $settings['extra_instructions'],
      '',
      'Parcours à explorer',
      '1. Connexion et déconnexion.',
      '2. Tableau de bord et blocs cliquables.',
      '3. Menus et sous-menus.',
      '4. Répertoires : apprenants, groupes, entreprises, financeurs, formations, formateurs, utilisateurs.',
      '5. CRM commercial : prospects, suivi commercial, calendrier.',
      '6. Inscriptions, dossiers, conventions / contrats.',
      '7. Séances : calendrier, création, validation, listes.',
      '8. Quiz, tests, enquêtes, évaluations, sessions questionnaires et résultats.',
      '9. Documents, devis, factures, téléchargements et exports.',
      '10. Communication marketing.',
      '11. Outils, statistiques, réglages et profils.',
      '',
      'Format attendu du compte rendu',
      'Pour chaque anomalie :',
      '- Module / écran',
      '- Chemin exact pour reproduire',
      '- Action effectuée',
      '- Résultat observé',
      '- Résultat attendu',
      '- Gravité : bloquant / majeur / mineur / cosmétique',
      '- Capture ou élément de preuve si disponible',
      '',
      'Sortie finale',
      'Produire un rapport structuré, priorisé, directement exploitable pour correction.',
    );

    return implode( "
", $prompt );
  }

  private function render_agent_audit_status_badge( $label ) {
    $class = 'acdc-chip';
    if ( 'Actif' === $label ) {
      $class .= ' acdc-chip-success';
    } elseif ( 'Expiré' === $label ) {
      $class .= ' acdc-chip-warning';
    } else {
      $class .= ' acdc-chip-muted';
    }
    return '<span class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
  }

  private function redirect_agent_audit_page( $message, $type = 'success', $is_admin_page = true ) {
    if ( $is_admin_page ) {
      wp_safe_redirect( add_query_arg( array(
        'page'        => 'acdc-of-agent-audit',
        'notice'      => rawurlencode( $message ),
        'notice_type' => rawurlencode( $type ),
      ), admin_url( 'admin.php' ) ) );
      exit;
    }

    $this->redirect_to_portal( 'agent_audit', $message, $type );
  }

}
