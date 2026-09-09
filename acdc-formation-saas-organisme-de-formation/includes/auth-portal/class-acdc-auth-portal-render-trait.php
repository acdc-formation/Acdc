<?php
/**
 * ACDC Auth Portal — rendus et écrans
 *
 * Extraction incrémentale du module authentification front
 * et utilisateurs portail.
 * Version : 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Auth_Portal_Render_Trait {

  public function render_login_shortcode() {
    if ( is_user_logged_in() && $this->is_admin_manager() ) {
      wp_safe_redirect( $this->portal_page_url() );
      exit;
    }

    ob_start();
    ?>
    <div class="acdc-portal-shell acdc-login-shell">
      <div class="acdc-login-card">
        <div class="acdc-login-head">
          <span class="acdc-chip">Connexion sécurisée</span>
          <h1>Espace de gestion ACDC</h1>
          <p>Accès réservé aux administrateurs du site.</p>
        </div>

        <?php $this->render_front_notice(); ?>

        <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <?php wp_nonce_field( 'acdc_front_login' ); ?>
          <input type="hidden" name="action" value="acdc_front_login">

          <p>
            <label for="acdc_front_log">Identifiant ou e-mail</label>
            <input type="text" id="acdc_front_log" name="log" required>
          </p>
          <p>
            <label for="acdc_front_pwd">Mot de passe</label>
            <input type="password" id="acdc_front_pwd" name="pwd" required>
          </p>
          <p class="acdc-checkbox-line">
            <label><input type="checkbox" name="rememberme" value="forever"> Rester connecté sur cet appareil</label>
          </p>
          <p>
            <button type="submit" class="acdc-button acdc-button-primary acdc-button-block">Se connecter</button>
          </p>
        </form>
      </div>
    </div>
    <?php
    return (string) ob_get_clean();
  }

  private function render_front_users_tab( $action, $item_id ) {
    $search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
    $users = $this->get_portal_users( $search );
    $user = $item_id ? $this->get_portal_user( $item_id ) : null;
    $new_url = is_admin() ? admin_url( 'admin.php?page=acdc-of-users&action=new' ) : $this->portal_page_url( array( 'tab' => 'users', 'action' => 'new' ) );
    $page_title = $this->acdc_get_action_page_title( $action, 'Utilisateurs', 'Créer un utilisateur', 'Modifier un utilisateur', 'Voir un utilisateur' );
    ?>
    <section class="acdc-section-head">
      <div>
        <h2><?php echo esc_html( $page_title ); ?></h2>
        <p>Gestion des utilisateurs internes de l’extranet et de leurs informations principales.</p>
      </div>
      <div class="acdc-inline-wrap">
        <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $new_url ); ?>">Créer un utilisateur</a>
      </div>
    </section>
    <?php
    if ( in_array( $action, array( 'new', 'edit', 'view' ), true ) ) {
      $this->render_front_user_form( $user, 'view' === $action );
    }
    /* ACDC 3.20.102 — Barre de recherche et tableau de la liste : affichés uniquement
       en mode liste (action vide). Sur new/edit/view, on ne montre que le formulaire,
       cohérent avec l'approche des formateurs (3.20.99). */
    if ( ! in_array( $action, array( 'new', 'edit', 'view' ), true ) ) :
    ?>
    <div class="acdc-panel acdc-mb-18">
      <form method="get" action="">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-users"><?php endif; ?>
        <input type="hidden" name="tab" value="users">
        <div class="acdc-inline-wrap acdc-inline-wrap-center">
          <input type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="Rechercher" style="max-width:420px;">
          <button type="submit" class="acdc-button acdc-button-soft">Rechercher</button>
        </div>
      </form>
    </div>
    <div class="acdc-panel">
      <div class="acdc-table-wrap">
        <table class="acdc-table" data-acdc-table-id="users-list">
          <thead><tr><th>Photo de profil</th><th>Genre</th><th>Prénom</th><th>Nom</th><th>E-mail</th><th>Téléphone</th><th>Rôle</th><th>Couleur calendrier</th><th>Source</th><th>Accès</th><th>Actions</th></tr></thead>
          <tbody>
          <?php if ( ! empty( $users ) ) : foreach ( $users as $entry ) : ?>
            <tr>
              <td><?php if ( ! empty( $entry->photo_url ) ) : ?><img src="<?php echo esc_url( $entry->photo_url ); ?>" alt="" style="width:36px;height:36px;border-radius:999px;object-fit:cover;"><?php else : ?>—<?php endif; ?></td>
              <td><?php echo esc_html( $entry->gender ?: '—' ); ?></td>
              <td><?php echo esc_html( $entry->first_name ?: '—' ); ?></td>
              <td><?php echo esc_html( $entry->last_name ?: '—' ); ?></td>
              <td><?php echo esc_html( $entry->email ?: '—' ); ?></td>
              <td><?php echo esc_html( $entry->phone ?: '—' ); ?></td>
              <td><?php echo esc_html( $entry->role_label ?: '—' ); ?></td>
              <td>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acdc-inline-wrap acdc-inline-wrap-center" style="gap:8px;">
                  <?php wp_nonce_field( 'acdc_save_portal_user_color_' . (int) $entry->id ); ?>
                  <input type="hidden" name="action" value="acdc_save_portal_user_color">
                  <input type="hidden" name="user_id" value="<?php echo esc_attr( (int) $entry->id ); ?>">
                  <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-users"><?php endif; ?>
                  <input type="color" name="calendar_color" value="<?php echo esc_attr( ! empty( $entry->calendar_color ) ? $entry->calendar_color : '#f3e3bf' ); ?>" aria-label="Couleur calendrier de <?php echo esc_attr( trim( $entry->first_name . ' ' . $entry->last_name ) ); ?>">
                  <button type="submit" class="acdc-button acdc-button-soft">Enregistrer</button>
                </form>
              </td>
              <td><?php echo esc_html( ! empty( $entry->source_label ) ? $entry->source_label : '—' ); ?></td>
              <td><?php echo ! empty( $entry->access_enabled ) ? '<span style="color:#2bbf6a;font-weight:700;">&#10003;</span>' : '<span style="color:#e04f5f;font-weight:700;">&#10005;</span>'; ?></td>
              <td><?php $base = is_admin() ? admin_url( 'admin.php?page=acdc-of-users' ) : $this->portal_page_url( array( 'tab' => 'users' ) ); ?>
                <?php /* ACDC 3.20.103 — Liens texte « Voir | Modifier | Supprimer » remplacés
                         par les icônes inline du système global (cohérence avec les autres pages).
                         Pour les comptes WordPress natifs (is_managed=0), seule l'icône Voir
                         est rendue, mais avec exactement le même markup et la même taille
                         que partout ailleurs (résolution de l'incohérence visuelle de l'œil
                         signalée sur le compte Administrateur WordPress). */ ?>
                <div class="acdc-groups-actions-inline">
                  <a class="acdc-row-action-icon acdc-row-view-link" href="<?php echo esc_url( add_query_arg( array( 'action' => 'view', 'item_id' => $entry->id ), $base ) ); ?>" title="Voir" aria-label="Voir l'utilisateur">
                    <?php echo $this->render_inline_icon( 'eye', 25 ); ?>
                  </a>
                  <?php if ( ! empty( $entry->is_managed ) ) : ?>
                    <a class="acdc-row-action-icon acdc-row-edit-link" href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'item_id' => $entry->id ), $base ) ); ?>" title="Modifier" aria-label="Modifier l'utilisateur">
                      <?php echo $this->render_inline_icon( 'edit', 25 ); ?>
                    </a>
                    <a class="acdc-row-action-icon acdc-row-delete-link" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_delete_portal_user&user_id=' . (int) $entry->id . ( is_admin() ? '&page=acdc-of-users' : '' ) ), 'acdc_delete_portal_user_' . (int) $entry->id ) ); ?>" title="Supprimer" aria-label="Supprimer l'utilisateur" onclick="return confirm('Supprimer cet utilisateur ?');">
                      <?php echo $this->render_inline_icon( 'trash', 25 ); ?>
                    </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; else : ?>
            <tr><td colspan="11">Aucun utilisateur enregistré.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; // ACDC 3.20.102 — fin condition mode liste.
  }

  private function render_front_user_form( $user = null, $read_only = false ) {
    $state = ! $read_only ? $this->acdc_consume_form_state( 'portal_user' ) : array();
    $state_input = ( ! empty( $state['input'] ) && is_array( $state['input'] ) ) ? $state['input'] : array();
    $required_fields = ( ! empty( $state['required_fields'] ) && is_array( $state['required_fields'] ) ) ? array_values( array_unique( array_map( 'sanitize_key', $state['required_fields'] ) ) ) : array();
    $value = function( $key, $default = '' ) use ( $user, $state_input ) { if ( array_key_exists( $key, $state_input ) && ! is_array( $state_input[ $key ] ) ) { return $state_input[ $key ]; } return $user && isset( $user->$key ) ? $user->$key : $default; };
    $field_class = function( $key ) use ( $required_fields ) { return in_array( sanitize_key( $key ), $required_fields, true ) ? ' class="acdc-field-invalid"' : ''; };
    $invalid_note = function( $key ) use ( $required_fields ) { return in_array( sanitize_key( $key ), $required_fields, true ) ? '<span class="acdc-field-help acdc-field-help-error">Champ obligatoire à renseigner.</span>' : ''; };
    $roles = $this->get_portal_user_role_options();
    $genders = $this->get_trainer_gender_options();
    ?>
    <style>.acdc-learner-form .acdc-field-invalid{border-color:var(--acdc-danger)!important;box-shadow:0 0 0 2px rgba(224,109,109,.12);} .acdc-learner-form .acdc-field-help-error{display:block;margin-top:6px;color:var(--acdc-danger)!important;font-size:12px;line-height:1.45;}</style>
    <div class="acdc-panel acdc-needs-section acdc-learner-form-panel">
      <div class="acdc-needs-section-title">Informations</div><?php if ( $user && ! empty( $user->is_wordpress_native ) ) : ?><div style="margin-top:6px;color:#1E4777">Compte WordPress natif visible dans le répertoire. Le rôle WordPress n’est pas modifiable depuis ce module.</div><?php endif; ?>
      <form class="acdc-form acdc-needs-form acdc-learner-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
        <?php wp_nonce_field( 'acdc_save_portal_user' ); ?>
        <input type="hidden" name="action" value="acdc_save_portal_user">
        <input type="hidden" name="user_id" value="<?php echo $user ? esc_attr( $user->id ) : 0; ?>">
        <?php if ( is_admin() ) : ?><input type="hidden" name="page" value="acdc-of-users"><?php endif; ?>
        <?php /* ACDC 3.20.102 — Bouton « Afficher le guide d'utilisation » retiré
                 du formulaire utilisateur (cohérence avec ce qui a été fait sur le formulaire formateur en 3.20.99). */ ?>
        <div class="acdc-grid-2cols">
          <p><label>Photo de profil</label><input type="file" name="portal_user_photo" <?php disabled( $read_only ); ?>><?php if ( ! empty( $value( 'photo_url' ) ) ) : ?><br><small><a href="<?php echo esc_url( $value( 'photo_url' ) ); ?>" target="_blank" rel="noopener">Voir le fichier actuel</a></small><input type="hidden" name="portal_user[photo_url]" value="<?php echo esc_attr( $value( 'photo_url' ) ); ?>"><?php endif; ?></p>
          <p><label>Genre *</label><select name="portal_user[gender]" required <?php disabled( $read_only ); ?>><?php foreach ( $genders as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) $value( 'gender' ), (string) $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
          <p><label>Prénom *</label><input type="text" name="portal_user[first_name]" required value="<?php echo esc_attr( $value( 'first_name' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?><?php echo $field_class( 'first_name' ); ?>><?php echo $invalid_note( 'first_name' ); ?></p>
          <p><label>Nom *</label><input type="text" name="portal_user[last_name]" required value="<?php echo esc_attr( $value( 'last_name' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?><?php echo $field_class( 'last_name' ); ?>><?php echo $invalid_note( 'last_name' ); ?></p>
          <p><label>E-mail *</label><input type="email" name="portal_user[email]" required value="<?php echo esc_attr( $value( 'email' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?><?php echo $field_class( 'email' ); ?>><?php echo $invalid_note( 'email' ); ?></p>
          <p><label>Téléphone *</label><input type="text" name="portal_user[phone]" required value="<?php echo esc_attr( $value( 'phone' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?><?php echo $field_class( 'phone' ); ?>><?php echo $invalid_note( 'phone' ); ?></p>
          <p><label>Date de naissance</label><input type="date" name="portal_user[birth_date]" value="<?php echo esc_attr( $value( 'birth_date' ) ); ?>" <?php echo $read_only ? 'readonly' : ''; ?>></p>
          <p><label>Rôle *</label><select name="portal_user[role_label]" required <?php disabled( $read_only ); ?><?php echo $field_class( 'role_label' ); ?>><?php foreach ( $roles as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) $value( 'role_label' ), (string) $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
          <p><label>Couleur calendrier</label><input type="color" name="portal_user[calendar_color]" value="<?php echo esc_attr( ! empty( $value( 'calendar_color' ) ) ? $value( 'calendar_color' ) : '#f3e3bf' ); ?>" <?php echo $read_only ? 'disabled' : ''; ?>></p>
        </div>
        <?php if ( ! $read_only ) : ?>
          <p class="acdc-actions-end-wrap">
            <a class="acdc-button" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-users' ) : $this->portal_page_url( array( 'tab' => 'users' ) ) ); ?>" class="acdc-button acdc-button-soft">Annuler</a>
            <button type="submit" class="acdc-button acdc-button-accent" name="save_and_add" value="1">Créer & ajouter un autre</button>
            <button type="submit" class="acdc-button acdc-button-primary"><?php echo $user ? 'Modifier un utilisateur' : 'Créer un utilisateur'; ?></button>
          </p>
        <?php else : ?>
          <?php if ( $user && empty( $user->is_wordpress_native ) ) : ?>
            <p><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( is_admin() ? admin_url( 'admin.php?page=acdc-of-users&action=edit&item_id=' . (int) $user->id ) : $this->portal_page_url( array( 'tab' => 'users', 'action' => 'edit', 'item_id' => (int) $user->id ) ) ); ?>">Modifier cet utilisateur</a></p>
          <?php else : ?>
            <p><span class="acdc-button acdc-button-secondary" style="opacity:.85;cursor:default;">Compte WordPress natif protégé</span></p>
          <?php endif; ?>
        <?php endif; ?>
      </form>
    </div>
    <?php
  }

public function render_admin_users_page() { $this->render_admin_portal_wrapper( 'users' ); }

}
