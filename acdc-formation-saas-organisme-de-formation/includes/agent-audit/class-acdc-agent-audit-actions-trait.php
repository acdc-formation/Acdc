<?php
/**
 * ACDC Exploration contrôlée — actions.
 *
 * @since 3.16.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Agent_Audit_Actions_Trait {

  public function handle_agent_audit_save_settings() {
    $this->require_manage_options();
    check_admin_referer( 'acdc_agent_audit_save_settings' );

    $input = isset( $_POST['agent_audit'] ) && is_array( $_POST['agent_audit'] ) ? wp_unslash( $_POST['agent_audit'] ) : array();
    $defaults = $this->get_agent_audit_settings_defaults();
    $settings = array(
      'mode_enabled'           => ! empty( $input['mode_enabled'] ) ? '1' : '0',
      'block_wp_mail'          => ! empty( $input['block_wp_mail'] ) ? '1' : '0',
      'show_admin_banner'      => ! empty( $input['show_admin_banner'] ) ? '1' : '0',
      'default_role'           => isset( $input['default_role'] ) && in_array( $input['default_role'], array( 'administrator', 'subscriber' ), true ) ? $input['default_role'] : $defaults['default_role'],
      'default_duration_hours' => max( 1, min( 168, isset( $input['default_duration_hours'] ) ? absint( $input['default_duration_hours'] ) : $defaults['default_duration_hours'] ) ),
      'scope_notes'            => isset( $input['scope_notes'] ) ? sanitize_textarea_field( $input['scope_notes'] ) : $defaults['scope_notes'],
      'extra_instructions'     => isset( $input['extra_instructions'] ) ? sanitize_textarea_field( $input['extra_instructions'] ) : $defaults['extra_instructions'],
      'custom_prompt_template'  => isset( $input['custom_prompt_template'] ) ? sanitize_textarea_field( $input['custom_prompt_template'] ) : $defaults['custom_prompt_template'],
    );

    update_option( 'acdc_of_agent_audit_settings', $settings, false );
    $this->log_action_event( 'agent_audit_save_settings', 'agent_audit', 0, 'success', $settings );
    $this->redirect_agent_audit_page( 'Paramètres d\'exploration enregistrés.' );
  }

  public function handle_agent_audit_create_user() {
    $this->require_manage_options();
    check_admin_referer( 'acdc_agent_audit_create_user' );

    $input = isset( $_POST['agent_audit_create'] ) && is_array( $_POST['agent_audit_create'] ) ? wp_unslash( $_POST['agent_audit_create'] ) : array();
    $settings = $this->get_agent_audit_settings();

    $role = isset( $input['role'] ) && in_array( $input['role'], array( 'administrator', 'subscriber' ), true ) ? $input['role'] : $settings['default_role'];
    $duration_hours = max( 1, min( 168, isset( $input['duration_hours'] ) ? absint( $input['duration_hours'] ) : $settings['default_duration_hours'] ) );
    $email = ! empty( $input['email'] ) ? sanitize_email( $input['email'] ) : $this->build_agent_audit_email();

    if ( ! is_email( $email ) ) {
      $this->redirect_agent_audit_page( 'Adresse e-mail invalide pour le compte d\'exploration.', 'error' );
    }

    if ( email_exists( $email ) ) {
      $this->redirect_agent_audit_page( 'Cette adresse e-mail est déjà utilisée.', 'error' );
    }

    $username = $this->build_agent_audit_username();
    $password = wp_generate_password( 20, true, true );
    $display_name = 'Agent exploration ' . gmdate( 'd/m/Y H:i' );

    $user_id = wp_insert_user(
      array(
        'user_login'   => $username,
        'user_pass'    => $password,
        'user_email'   => $email,
        'display_name' => $display_name,
        'first_name'   => 'Agent',
        'last_name'    => 'Exploration',
        'role'         => $role,
      )
    );

    if ( is_wp_error( $user_id ) ) {
      $this->redirect_agent_audit_page( $user_id->get_error_message(), 'error' );
    }

    $expires_at = time() + ( $duration_hours * HOUR_IN_SECONDS );
    update_user_meta( $user_id, '_acdc_agent_audit_managed', '1' );
    update_user_meta( $user_id, '_acdc_agent_audit_expires_at', $expires_at );
    update_user_meta( $user_id, '_acdc_agent_audit_disabled', '0' );
    update_user_meta( $user_id, '_acdc_agent_audit_role_label', 'administrator' === $role ? 'Administrateur de test' : 'Lecture limitée' );
    update_user_meta( $user_id, 'acdc_portal_managed', '1' );
    update_user_meta( $user_id, 'acdc_portal_role_label', 'administrator' === $role ? 'Administrateur' : 'Lecture seule' );

    $payload = array(
      'user_id'        => (int) $user_id,
      'username'       => $username,
      'password'       => $password,
      'email'          => $email,
      'login_url'      => $this->login_page_url(),
      'portal_url'     => $this->portal_page_url(),
      'role'           => $role,
      'expires_at'     => $expires_at,
      'expires_label'  => date_i18n( 'd/m/Y H:i', $expires_at ),
    );
    $this->store_agent_audit_credentials( $payload );
    $this->log_action_event( 'agent_audit_create_user', 'agent_audit', (int) $user_id, 'success', array( 'role' => $role, 'duration_hours' => $duration_hours ) );

    $this->redirect_agent_audit_page( 'Compte temporaire d\'exploration créé.' );
  }

  public function handle_agent_audit_delete_user() {
    $this->require_manage_options();
    $this->verify_nonce_or_die( 'acdc_agent_audit_delete_user' );

    $user_id = isset( $_REQUEST['user_id'] ) ? absint( wp_unslash( $_REQUEST['user_id'] ) ) : 0;
    if ( $user_id <= 0 || '1' !== (string) get_user_meta( $user_id, '_acdc_agent_audit_managed', true ) ) {
      $this->redirect_agent_audit_page( 'Compte d\'exploration introuvable.', 'error' );
    }

    if ( ! function_exists( 'wp_delete_user' ) ) {
      require_once ABSPATH . 'wp-admin/includes/user.php';
    }

    wp_delete_user( $user_id );
    $this->log_action_event( 'agent_audit_delete_user', 'agent_audit', $user_id, 'success' );
    $this->redirect_agent_audit_page( 'Compte d\'exploration supprimé.' );
  }

}
