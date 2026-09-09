<?php
/**
 * ACDC Exploration contrôlée — rendus.
 *
 * @since 3.16.1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Agent_Audit_Render_Trait {

  public function render_admin_agent_audit_page() {
    $this->render_admin_portal_wrapper( 'agent_audit' );
  }

  private function render_front_agent_audit_tab() {
    $settings = $this->get_agent_audit_settings();
    $users = $this->get_agent_audit_users();
    $credentials = $this->get_agent_audit_last_credentials();
    $prompt = $this->get_agent_audit_prompt( $credentials );
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Exploration contrôlée</h2>
        <p>Créez un compte temporaire dédié pour un agent d'exploration, activez un mode sécurisé et copiez un prompt prêt à l'emploi.</p>
      </div>
    </section>

    <div class="acdc-grid-2cols acdc-mb-18">
      <div class="acdc-panel">
        <h3>Rappel de sécurité</h3>
        <ul class="acdc-simple-list">
          <li>Utiliser de préférence une préproduction.</li>
          <li>Ne jamais partager votre compte principal.</li>
          <li>Bloquer les envois réels d'e-mails pendant l'audit.</li>
          <li>Supprimer le compte d'exploration après usage.</li>
        </ul>
      </div>
      <div class="acdc-panel">
        <h3>Accès utiles</h3>
        <ul class="acdc-simple-list">
          <li><strong>Connexion :</strong> <a href="<?php echo esc_url( $this->login_page_url() ); ?>" target="_blank" rel="noopener">Ouvrir</a></li>
          <li><strong>Tableau de bord :</strong> <a href="<?php echo esc_url( $this->portal_page_url() ); ?>" target="_blank" rel="noopener">Ouvrir</a></li>
        </ul>
      </div>
    </div>

    <div class="acdc-grid-2cols acdc-mb-18">
      <div class="acdc-panel">
        <h3>Mode exploration sécurisé</h3>
        <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <?php wp_nonce_field( 'acdc_agent_audit_save_settings' ); ?>
          <input type="hidden" name="action" value="acdc_agent_audit_save_settings">
          <p class="acdc-checkbox-line"><label><input type="checkbox" name="agent_audit[mode_enabled]" value="1" <?php checked( '1', (string) $settings['mode_enabled'] ); ?>> Activer le mode exploration sécurisé</label></p>
          <p class="acdc-checkbox-line"><label><input type="checkbox" name="agent_audit[block_wp_mail]" value="1" <?php checked( '1', (string) $settings['block_wp_mail'] ); ?>> Bloquer les envois e-mail WordPress</label></p>
          <p class="acdc-checkbox-line"><label><input type="checkbox" name="agent_audit[show_admin_banner]" value="1" <?php checked( '1', (string) $settings['show_admin_banner'] ); ?>> Afficher une bannière de rappel dans l'administration</label></p>
          <div class="acdc-grid-2cols">
            <p><label>Rôle par défaut</label><select name="agent_audit[default_role]"><?php foreach ( $this->get_agent_audit_role_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $settings['default_role'] ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
            <p><label>Durée par défaut (heures)</label><input type="number" min="1" max="168" name="agent_audit[default_duration_hours]" value="<?php echo esc_attr( (string) $settings['default_duration_hours'] ); ?>"></p>
          </div>
          <p><label>Périmètre / règles</label><textarea name="agent_audit[scope_notes]" rows="5"><?php echo esc_textarea( $settings['scope_notes'] ); ?></textarea></p>
          <p><label>Instructions complémentaires</label><textarea name="agent_audit[extra_instructions]" rows="5"><?php echo esc_textarea( $settings['extra_instructions'] ); ?></textarea></p>
          <p><label>Modèle du prompt prêt à copier</label><textarea name="agent_audit[custom_prompt_template]" rows="14"><?php echo esc_textarea( $settings['custom_prompt_template'] ); ?></textarea></p>
          <p class="description">Variables disponibles : {{LOGIN_URL}}, {{PORTAL_URL}}, {{USERNAME}}, {{PASSWORD}}, {{EXPIRES}}, {{SCOPE_NOTES}}, {{EXTRA_INSTRUCTIONS}}</p>
          <p><button type="submit" class="acdc-button acdc-button-primary">Enregistrer les paramètres</button></p>
        </form>
      </div>

      <div class="acdc-panel">
        <h3>Créer un compte temporaire</h3>
        <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <?php wp_nonce_field( 'acdc_agent_audit_create_user' ); ?>
          <input type="hidden" name="action" value="acdc_agent_audit_create_user">
          <div class="acdc-grid-2cols">
            <p><label>Rôle</label><select name="agent_audit_create[role]"><?php foreach ( $this->get_agent_audit_role_options() as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $settings['default_role'] ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
            <p><label>Durée (heures)</label><input type="number" min="1" max="168" name="agent_audit_create[duration_hours]" value="<?php echo esc_attr( (string) $settings['default_duration_hours'] ); ?>"></p>
          </div>
          <p><label>E-mail du compte (laisser vide pour génération automatique)</label><input type="email" name="agent_audit_create[email]" value=""></p>
          <p><button type="submit" class="acdc-button acdc-button-primary">Créer le compte d'exploration</button></p>
        </form>
      </div>
    </div>

    <?php if ( ! empty( $credentials ) ) : ?>
      <div class="acdc-panel acdc-mb-18">
        <h3>Derniers identifiants générés</h3>
        <div class="acdc-grid-2cols">
          <div>
            <p><strong>Identifiant :</strong> <code><?php echo esc_html( $credentials['username'] ); ?></code></p>
            <p><strong>Mot de passe :</strong> <code><?php echo esc_html( $credentials['password'] ); ?></code></p>
            <p><strong>E-mail :</strong> <code><?php echo esc_html( $credentials['email'] ); ?></code></p>
          </div>
          <div>
            <p><strong>Connexion :</strong> <a href="<?php echo esc_url( $credentials['login_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $credentials['login_url'] ); ?></a></p>
            <p><strong>Tableau de bord :</strong> <a href="<?php echo esc_url( $credentials['portal_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $credentials['portal_url'] ); ?></a></p>
            <p><strong>Expiration :</strong> <?php echo esc_html( $credentials['expires_label'] ); ?></p>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="acdc-panel acdc-mb-18">
      <h3>Comptes d'exploration existants</h3>
      <div class="acdc-table-wrap">
        <table class="acdc-table">
          <thead><tr><th>Identifiant</th><th>E-mail</th><th>Rôle</th><th>Expiration</th><th>Statut</th><th>Action</th></tr></thead>
          <tbody>
          <?php if ( ! empty( $users ) ) : foreach ( $users as $row ) : ?>
            <?php $status = $this->get_agent_audit_status_label( $row ); ?>
            <tr>
              <td><?php echo esc_html( $row['login'] ); ?></td>
              <td><?php echo esc_html( $row['email'] ); ?></td>
              <td><?php echo esc_html( $row['role_label'] ); ?></td>
              <td><?php echo ! empty( $row['expires_at'] ) ? esc_html( date_i18n( 'd/m/Y H:i', $row['expires_at'] ) ) : '—'; ?></td>
              <td><?php echo $this->render_agent_audit_status_badge( $status ); ?></td>
              <td><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_agent_audit_delete_user&user_id=' . (int) $row['id'] ), 'acdc_agent_audit_delete_user' ) ); ?>" onclick="return confirm('Supprimer définitivement ce compte d\'exploration ?');">Supprimer</a></td>
            </tr>
          <?php endforeach; else : ?>
            <tr><td colspan="6">Aucun compte d'exploration géré par le plugin.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="acdc-panel">
      <h3>Prompt prêt à copier</h3>
      <p>Utilisez ce prompt avec l'agent de votre choix. Si vous venez de créer un compte, les identifiants sont déjà injectés ci-dessous.</p>
      <textarea rows="24" readonly style="width:100%;font-family:monospace;"><?php echo esc_textarea( $prompt ); ?></textarea>
    </div>
    <?php
  }

}
