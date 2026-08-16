<?php
/**
 * ACDC 3.20.83 — Trait Actions du portail Formateur (handlers POST).
 * Calqué sur le module Apprenant.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

trait ACDC_Trainer_Portal_Actions_Trait {

  /**
   * Traitement de la soumission du formulaire de connexion.
   */
  public function handle_trainer_login() {
    check_admin_referer( 'acdc_trainer_login' );
    $email    = isset( $_POST['trainer_email'] ) ? sanitize_email( wp_unslash( $_POST['trainer_email'] ) ) : '';
    $password = isset( $_POST['trainer_password'] ) ? (string) wp_unslash( $_POST['trainer_password'] ) : '';

    if ( '' === $email || '' === $password ) {
      $this->trainer_portal_redirect( 'login', 'Veuillez renseigner votre e-mail et votre mot de passe.', 'error' );
    }

    $account = $this->trainer_portal_get_account_by_email( $email );
    if ( ! $account ) {
      $this->trainer_portal_log_event( 0, 'login_failed', array( 'reason' => 'unknown_email' ) );
      $this->trainer_portal_redirect( 'login', 'E-mail ou mot de passe incorrect.', 'error' );
    }

    // Compte bloqué (trop d'échecs récents) ?
    if ( ! empty( $account->blocked_until ) && strtotime( $account->blocked_until ) > current_time( 'timestamp' ) ) {
      $this->trainer_portal_log_event( $account->id, 'login_blocked', array( 'until' => $account->blocked_until ) );
      $this->trainer_portal_redirect( 'login', 'Trop de tentatives infructueuses. Réessayez dans quelques minutes.', 'error' );
    }

    if ( 'never_activated' === $account->status ) {
      $this->trainer_portal_redirect( 'login', 'Votre compte n’a pas encore été activé. Consultez l’e-mail d’invitation reçu, ou demandez un nouveau lien via « Mot de passe oublié ».', 'error' );
    }

    if ( 'disabled' === $account->status ) {
      $this->trainer_portal_redirect( 'login', 'Votre accès est actuellement désactivé. Contactez l’administrateur.', 'error' );
    }

    if ( ! $this->trainer_portal_check_password( $password, $account->password_hash ) ) {
      // Incrémenter le compteur d'échecs.
      global $wpdb;
      $new_failed = ( (int) $account->failed_login_count ) + 1;
      $update     = array( 'failed_login_count' => $new_failed, 'updated_at' => $this->trainer_portal_now_mysql() );
      // Verrouillage après 5 échecs : 15 minutes.
      if ( $new_failed >= 5 ) {
        $update['blocked_until']      = gmdate( 'Y-m-d H:i:s', strtotime( '+15 minutes', current_time( 'timestamp' ) ) );
        $update['failed_login_count'] = 0;
      }
      $wpdb->update( $this->trainer_portal_account_table, $update, array( 'id' => (int) $account->id ) );
      $this->trainer_portal_log_event( (int) $account->id, 'login_failed', array( 'reason' => 'bad_password' ), (int) $account->trainer_id );
      $this->trainer_portal_redirect( 'login', 'E-mail ou mot de passe incorrect.', 'error' );
    }

    // Connexion réussie : reset compteur, set last_login, créer session.
    global $wpdb;
    $wpdb->update(
      $this->trainer_portal_account_table,
      array(
        'failed_login_count' => 0,
        'blocked_until'      => null,
        'last_login_at'      => $this->trainer_portal_now_mysql(),
        'updated_at'         => $this->trainer_portal_now_mysql(),
      ),
      array( 'id' => (int) $account->id )
    );

    $this->trainer_portal_create_session( (int) $account->id );
    $this->trainer_portal_log_event( (int) $account->id, 'login_success', array(), (int) $account->trainer_id );
    $this->trainer_portal_redirect( 'dashboard' );
  }

  /**
   * Déconnexion : révoque la session courante et purge le cookie.
   */
  public function handle_trainer_logout() {
    /* ACDC 3.25.275 — La déconnexion vérifie son jeton, comme celle de
       l'apprenant. Le lien en portait un depuis toujours — `wp_nonce_url()` —
       mais personne ne le lisait : n'importe quelle page tierce pouvait donc
       déconnecter un formateur d'un simple lien. Sans conséquence sur ses
       données, mais c'est une action qu'il n'a pas demandée, et l'écart avec le
       portail apprenant n'avait aucune raison d'être. */
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'acdc_trainer_logout' ) ) {
      $this->trainer_portal_redirect( 'login', 'Jeton de sécurité invalide.', 'error' );
    }
    $cookie_name = $this->trainer_portal_cookie_name();
    if ( ! empty( $_COOKIE[ $cookie_name ] ) ) {
      $hash = $this->trainer_portal_hash_token( (string) $_COOKIE[ $cookie_name ] );
      $this->trainer_portal_revoke_session_by_hash( $hash );
    }
    $account = $this->trainer_portal_get_current_account();
    if ( $account ) {
      $this->trainer_portal_log_event( (int) $account->id, 'logout_manual', array(), (int) $account->trainer_id );
    }
    $this->trainer_portal_clear_cookie();
    $this->trainer_portal_redirect( 'login', 'Vous êtes déconnecté.', 'success' );
  }

  /**
   * Demande de réinitialisation : envoie un e-mail si le compte existe.
   */
  public function handle_trainer_request_reset() {
    check_admin_referer( 'acdc_trainer_request_reset' );
    /* ACDC 3.25.291 — Cette demande n'était pas limitée : on pouvait la rejouer
       en boucle sur une adresse. */
    if ( $this->acdc_trop_de_tentatives( 'reinit_formateur' ) ) {
      $this->trainer_portal_redirect( 'forgot', 'Trop de demandes depuis ce réseau. Patientez un quart d’heure avant de réessayer.', 'error' );
    }
    $email = isset( $_POST['trainer_email'] ) ? sanitize_email( wp_unslash( $_POST['trainer_email'] ) ) : '';
    if ( '' === $email ) {
      $this->trainer_portal_redirect( 'forgot', 'Veuillez saisir votre e-mail.', 'error' );
    }
    $account = $this->trainer_portal_get_account_by_email( $email );
    // Réponse identique que le compte existe ou non (anti-énumération).
    if ( $account && 'disabled' !== $account->status ) {
      $trainer = $this->get_trainer( (int) $account->trainer_id );
      $this->trainer_portal_send_reset_email( $account, $trainer );
      $this->trainer_portal_log_event( (int) $account->id, 'reset_requested', array(), (int) $account->trainer_id );
    }
    $this->trainer_portal_redirect( 'login', 'Si un compte existe pour cet e-mail, un lien de réinitialisation vient d’être envoyé.', 'success' );
  }

  /**
   * Validation d'un token de reset et changement de mot de passe.
   */
  public function handle_trainer_reset_password() {
    check_admin_referer( 'acdc_trainer_reset_password' );
    $token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
    $p1    = isset( $_POST['password_1'] ) ? (string) wp_unslash( $_POST['password_1'] ) : '';
    $p2    = isset( $_POST['password_2'] ) ? (string) wp_unslash( $_POST['password_2'] ) : '';

    if ( '' === $token ) {
      $this->trainer_portal_redirect( 'login', 'Lien invalide ou expiré.', 'error' );
    }
    if ( strlen( $p1 ) < 8 ) {
      $this->trainer_portal_redirect( 'reset', 'Le mot de passe doit contenir au moins 8 caractères.', 'error', array( 'token' => $token ) );
    }
    if ( $p1 !== $p2 ) {
      $this->trainer_portal_redirect( 'reset', 'Les deux mots de passe ne correspondent pas.', 'error', array( 'token' => $token ) );
    }

    $row = $this->trainer_portal_get_token_row( $token, 'reset' );
    if ( ! $row || strtotime( $row->expires_at ) < current_time( 'timestamp' ) ) {
      $this->trainer_portal_redirect( 'login', 'Lien invalide ou expiré.', 'error' );
    }

    global $wpdb;
    $wpdb->update(
      $this->trainer_portal_account_table,
      array(
        'password_hash'        => $this->trainer_portal_hash_password( $p1 ),
        'must_change_password' => 0,
        'failed_login_count'   => 0,
        'blocked_until'        => null,
        'updated_at'           => $this->trainer_portal_now_mysql(),
      ),
      array( 'id' => (int) $row->account_id )
    );
    $this->trainer_portal_consume_token( (int) $row->id );
    $this->trainer_portal_log_event( (int) $row->account_id, 'password_reset_done' );
    $this->trainer_portal_redirect( 'login', 'Votre mot de passe a été mis à jour. Vous pouvez vous connecter.', 'success' );
  }

  /**
   * Activation initiale d'un compte (premier accès) via le token d'invitation.
   */
  public function handle_trainer_activate() {
    check_admin_referer( 'acdc_trainer_activate' );
    $token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
    $p1    = isset( $_POST['password_1'] ) ? (string) wp_unslash( $_POST['password_1'] ) : '';
    $p2    = isset( $_POST['password_2'] ) ? (string) wp_unslash( $_POST['password_2'] ) : '';

    if ( '' === $token ) {
      $this->trainer_portal_redirect( 'login', 'Lien d’activation invalide ou expiré.', 'error' );
    }
    if ( strlen( $p1 ) < 8 ) {
      $this->trainer_portal_redirect( 'activate', 'Le mot de passe doit contenir au moins 8 caractères.', 'error', array( 'token' => $token ) );
    }
    if ( $p1 !== $p2 ) {
      $this->trainer_portal_redirect( 'activate', 'Les deux mots de passe ne correspondent pas.', 'error', array( 'token' => $token ) );
    }

    $row = $this->trainer_portal_get_token_row( $token, 'activation' );
    if ( ! $row || strtotime( $row->expires_at ) < current_time( 'timestamp' ) ) {
      $this->trainer_portal_redirect( 'login', 'Lien d’activation invalide ou expiré. Demandez un nouveau lien via « Mot de passe oublié ».', 'error' );
    }

    global $wpdb;
    $now = $this->trainer_portal_now_mysql();
    $wpdb->update(
      $this->trainer_portal_account_table,
      array(
        'password_hash'        => $this->trainer_portal_hash_password( $p1 ),
        'must_change_password' => 0,
        'status'               => 'active',
        'first_activated_at'   => $now,
        'failed_login_count'   => 0,
        'blocked_until'        => null,
        'updated_at'           => $now,
      ),
      array( 'id' => (int) $row->account_id )
    );
    $this->trainer_portal_consume_token( (int) $row->id );
    $this->trainer_portal_log_event( (int) $row->account_id, 'account_activated' );

    // Connexion automatique post-activation.
    $this->trainer_portal_create_session( (int) $row->account_id );
    $this->trainer_portal_redirect( 'dashboard', 'Votre accès est activé. Bienvenue dans votre espace formateur.', 'success' );
  }

  /* ====================================================================
   * ACDC 3.20.87 — Bibliothèque personnelle, côté formateur (auth custom).
   * Le formateur connecté à son portail peut déposer/voir/supprimer ses
   * propres documents. Les notes_admin et le flag Qualiopi sont gérés par
   * l'admin uniquement (le formateur les voit en lecture seule).
   * ==================================================================== */

  /**
   * Upload d'un document par le formateur lui-même (depuis son extranet).
   */
  public function handle_trainer_upload_own_document() {
    check_admin_referer( 'acdc_trainer_upload_own_document' );
    $account = $this->trainer_portal_require_auth();
    $trainer = $this->get_trainer( (int) $account->trainer_id );
    if ( ! $trainer ) {
      $this->trainer_portal_redirect( 'library', 'Profil formateur introuvable. Contactez l’administrateur.', 'error' );
    }

    // 1. Validation catégorie.
    $category = isset( $_POST['category'] ) ? sanitize_key( wp_unslash( $_POST['category'] ) ) : '';
    $cats     = $this->get_acdc_trainer_document_categories();
    if ( '' === $category || ! isset( $cats[ $category ] ) ) {
      $this->trainer_portal_redirect( 'library', 'Catégorie de document invalide.', 'error' );
    }

    // 2. Validation fichier.
    if ( empty( $_FILES['document_file'] ) || empty( $_FILES['document_file']['name'] ) || UPLOAD_ERR_OK !== (int) $_FILES['document_file']['error'] ) {
      $error_code = isset( $_FILES['document_file']['error'] ) ? (int) $_FILES['document_file']['error'] : -1;
      $msg = ( UPLOAD_ERR_INI_SIZE === $error_code || UPLOAD_ERR_FORM_SIZE === $error_code )
        ? 'Le fichier dépasse la taille maximale autorisée.'
        : 'Aucun fichier valide reçu.';
      $this->trainer_portal_redirect( 'library', $msg, 'error' );
    }

    $file_name_orig = sanitize_file_name( (string) $_FILES['document_file']['name'] );
    $file_size      = (int) $_FILES['document_file']['size'];
    $file_tmp       = (string) $_FILES['document_file']['tmp_name'];

    if ( $file_size > $this->get_acdc_trainer_document_max_size_bytes() ) {
      $this->trainer_portal_redirect( 'library', 'Le fichier dépasse 100 Mo.', 'error' );
    }
    if ( ! is_uploaded_file( $file_tmp ) ) {
      $this->trainer_portal_redirect( 'library', 'Erreur d’upload : fichier temporaire introuvable.', 'error' );
    }

    // 3. Validation extension + mime.
    $allowed_mimes = $this->get_acdc_trainer_document_allowed_mimes();
    $ext           = strtolower( pathinfo( $file_name_orig, PATHINFO_EXTENSION ) );
    if ( '' === $ext || ! isset( $allowed_mimes[ $ext ] ) ) {
      $this->trainer_portal_redirect( 'library', 'Format de fichier non autorisé. Formats acceptés : PDF, JPG, PNG, DOC, DOCX, PPTX.', 'error' );
    }
    $check = wp_check_filetype_and_ext( $file_tmp, $file_name_orig, $allowed_mimes );
    if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
      $this->trainer_portal_redirect( 'library', 'Le contenu du fichier ne correspond pas à son extension. Upload refusé.', 'error' );
    }

    // 4. Préparation du dossier.
    $dirs = $this->ensure_trainer_documents_upload_dir();
    if ( ! $dirs ) {
      $this->trainer_portal_redirect( 'library', 'Impossible de préparer le dossier d’upload.', 'error' );
    }
    $trainer_dir = trailingslashit( $dirs['base_dir'] ) . (int) $trainer->id;
    if ( ! file_exists( $trainer_dir ) ) {
      wp_mkdir_p( $trainer_dir );
    }

    // 5. Nom de fichier sécurisé.
    $base_slug = pathinfo( $file_name_orig, PATHINFO_FILENAME );
    $base_slug = $this->acdc_strip_accents( $base_slug );
    $base_slug = preg_replace( '/[^a-zA-Z0-9_-]+/', '-', $base_slug );
    $base_slug = trim( strtolower( (string) $base_slug ), '-' );
    if ( '' === $base_slug ) {
      $base_slug = 'doc';
    }
    $base_slug   = substr( $base_slug, 0, 80 );
    $stored_name = $category . '_' . time() . '_' . $base_slug . '.' . $ext;
    $stored_full = trailingslashit( $trainer_dir ) . $stored_name;

    if ( ! @move_uploaded_file( $file_tmp, $stored_full ) ) {
      $this->trainer_portal_redirect( 'library', 'Erreur lors du dépôt du fichier.', 'error' );
    }
    @chmod( $stored_full, 0644 );

    // 6. Insertion en BD (uploaded_by = "trainer", pas de notes_admin, pas de Qualiopi flag).
    global $wpdb;
    $now = current_time( 'mysql' );
    $relative_path = (int) $trainer->id . '/' . $stored_name;
    $label         = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
    if ( '' === $label ) {
      $label = $cats[ $category ]['label'];
    }
    $issued_at  = isset( $_POST['issued_at'] ) && '' !== $_POST['issued_at'] ? sanitize_text_field( wp_unslash( $_POST['issued_at'] ) ) : null;
    $expires_at = isset( $_POST['expires_at'] ) && '' !== $_POST['expires_at'] ? sanitize_text_field( wp_unslash( $_POST['expires_at'] ) ) : null;

    $wpdb->insert(
      $this->trainer_document_table,
      array(
        'trainer_id'        => (int) $trainer->id,
        'category'          => $category,
        'label'             => $label,
        'file_url'          => $relative_path,
        'file_name'         => $file_name_orig,
        'issued_at'         => $issued_at,
        'expires_at'        => $expires_at,
        'uploaded_by'       => 'trainer',
        'uploader_user_id'  => 0,
        'is_qualiopi_proof' => 0,
        'notes_admin'       => '',
        'created_at'        => $now,
        'updated_at'        => $now,
      )
    );
    $this->trainer_portal_log_event( (int) $account->id, 'document_uploaded', array( 'category' => $category, 'file_name' => $file_name_orig ), (int) $trainer->id );
    $this->trainer_portal_redirect( 'library', 'Document ajouté à votre bibliothèque.', 'success' );
  }

  /**
   * Suppression par le formateur d'un de SES propres documents.
   * Vérification stricte : trainer_id du doc === trainer_id du compte connecté.
   */
  public function handle_trainer_delete_own_document() {
    $account     = $this->trainer_portal_require_auth();
    $document_id = isset( $_GET['document_id'] ) ? absint( wp_unslash( $_GET['document_id'] ) ) : 0;
    if ( ! $document_id ) {
      $this->trainer_portal_redirect( 'library', 'Document introuvable.', 'error' );
    }
    check_admin_referer( 'acdc_trainer_delete_own_document_' . $document_id );

    global $wpdb;
    $doc = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->trainer_document_table} WHERE id = %d", $document_id ) );
    if ( ! $doc ) {
      $this->trainer_portal_redirect( 'library', 'Document introuvable.', 'error' );
    }
    if ( (int) $doc->trainer_id !== (int) $account->trainer_id ) {
      $this->trainer_portal_log_event( (int) $account->id, 'document_delete_forbidden', array( 'document_id' => (int) $document_id ), (int) $account->trainer_id );
      $this->trainer_portal_redirect( 'library', 'Vous ne pouvez supprimer que vos propres documents.', 'error' );
    }

    // Suppression fichier physique + BD.
    $abs = $this->get_trainer_document_absolute_path( (string) $doc->file_url );
    if ( $abs && file_exists( $abs ) ) {
      @unlink( $abs );
    }
    $wpdb->delete( $this->trainer_document_table, array( 'id' => (int) $document_id ) );
    $this->trainer_portal_log_event( (int) $account->id, 'document_deleted', array( 'document_id' => (int) $document_id ), (int) $account->trainer_id );
    $this->trainer_portal_redirect( 'library', 'Document supprimé.', 'success' );
  }

  /**
   * Téléchargement par le formateur de SES propres documents.
   * Vérification stricte : trainer_id du doc === trainer_id du compte connecté.
   */
  /**
   * ACDC 3.25.248 — LE FORMATEUR TÉLÉCHARGE SON PROPRE CONTRAT.
   *
   * « Télécharger mon exemplaire signé » pointait vers l'adresse directe du
   * fichier dans /uploads/acdc-of-contracts/. Ce dossier est volontairement
   * interdit d'accès direct depuis la 3.25.148 — un contrat porte le nom,
   * l'e-mail et le SIRET du formateur, et son nom de fichier était devinable.
   * Le serveur répondait donc 403 : il faisait exactement ce qu'on lui avait
   * demandé.
   *
   * Une route gardée existait déjà, mais elle exige `manage_options` : un
   * formateur connecté à SON extranet n'est pas administrateur WordPress. La
   * corriger seule aurait remplacé le 403 par un « accès refusé » — aussi
   * inutile.
   *
   * Il fallait donc une porte côté formateur, sur le modèle éprouvé du
   * téléchargement de ses documents : session du portail, jeton, et surtout
   * VÉRIFICATION D'APPARTENANCE. Sans elle, un formateur authentifié pourrait
   * lire le contrat d'un autre en changeant un numéro dans l'adresse — ce qui
   * serait pire que le 403 qu'on corrige. Le refus est journalisé : une
   * tentative d'accès croisé doit laisser une trace.
   *
   * On n'affaiblit pas la protection du dossier : on ouvre une porte contrôlée.
   */
  public function handle_trainer_download_own_contract() {
    $account     = $this->trainer_portal_require_auth();
    $contract_id = isset( $_GET['contract_id'] ) ? absint( wp_unslash( $_GET['contract_id'] ) ) : 0;
    $signed      = isset( $_GET['signed'] ) && '1' === (string) $_GET['signed'];
    if ( ! $contract_id ) {
      wp_die( esc_html( 'Contrat introuvable.' ) );
    }
    check_admin_referer( 'acdc_trainer_download_own_contract_' . $contract_id );

    global $wpdb;
    $contract = $wpdb->get_row( $wpdb->prepare(
      "SELECT id, trainer_id, contract_pdf_url, signed_document_url FROM {$this->trainer_contract_table} WHERE id = %d",
      $contract_id
    ) );
    if ( ! $contract ) {
      wp_die( esc_html( 'Contrat introuvable.' ) );
    }
    if ( (int) $contract->trainer_id !== (int) $account->trainer_id ) {
      $this->trainer_portal_log_event( (int) $account->id, 'contract_download_forbidden', array( 'contract_id' => (int) $contract_id ), (int) $account->trainer_id );
      wp_die( esc_html( 'Accès refusé : vous ne pouvez télécharger que vos propres contrats.' ) );
    }

    /* Le chemin est résolu par la même fonction que la route administrateur :
       elle vérifie que le fichier se trouve bien SOUS le dossier de ce contrat,
       ce qui interdit toute remontée d'arborescence. */
    $real_path = method_exists( $this, 'acdc_trainer_contract_file_path' )
      ? $this->acdc_trainer_contract_file_path( $contract, $signed )
      : '';
    /* Un exemplaire signé demandé mais absent : on sert l'original plutôt que
       de renvoyer « introuvable » sur un contrat qui existe. */
    if ( '' === $real_path && $signed && method_exists( $this, 'acdc_trainer_contract_file_path' ) ) {
      $real_path = $this->acdc_trainer_contract_file_path( $contract, false );
    }
    if ( '' === $real_path || ! file_exists( $real_path ) ) {
      wp_die( esc_html( 'Fichier introuvable sur le serveur.' ) );
    }

    $this->trainer_portal_log_event( (int) $account->id, 'contract_downloaded', array( 'contract_id' => (int) $contract_id, 'signed' => $signed ? 1 : 0 ), (int) $account->trainer_id );

    while ( ob_get_level() ) { ob_end_clean(); }
    nocache_headers();
    header( 'Content-Type: application/pdf' );
    header( 'Content-Disposition: inline; filename="' . basename( $real_path ) . '"' );
    header( 'Content-Length: ' . filesize( $real_path ) );
    readfile( $real_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
    exit;
  }

  /** URL de téléchargement d'un contrat par son formateur, jeton compris. */
  private function acdc_trainer_own_contract_url( $contract_id, $signed = false ) {
    $args = array( 'action' => 'acdc_trainer_download_own_contract', 'contract_id' => (int) $contract_id );
    if ( $signed ) {
      $args['signed'] = 1;
    }
    return wp_nonce_url(
      add_query_arg( $args, admin_url( 'admin-post.php' ) ),
      'acdc_trainer_download_own_contract_' . (int) $contract_id
    );
  }

  public function handle_trainer_download_own_document() {
    $account     = $this->trainer_portal_require_auth();
    $document_id = isset( $_GET['document_id'] ) ? absint( wp_unslash( $_GET['document_id'] ) ) : 0;
    if ( ! $document_id ) {
      wp_die( esc_html( 'Document introuvable.' ) );
    }
    check_admin_referer( 'acdc_trainer_download_own_document_' . $document_id );

    global $wpdb;
    $doc = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->trainer_document_table} WHERE id = %d", $document_id ) );
    if ( ! $doc ) {
      wp_die( esc_html( 'Document introuvable.' ) );
    }
    if ( (int) $doc->trainer_id !== (int) $account->trainer_id ) {
      $this->trainer_portal_log_event( (int) $account->id, 'document_download_forbidden', array( 'document_id' => (int) $document_id ), (int) $account->trainer_id );
      wp_die( esc_html( 'Accès refusé : vous ne pouvez télécharger que vos propres documents.' ) );
    }

    $abs = $this->get_trainer_document_absolute_path( (string) $doc->file_url );
    if ( ! $abs || ! file_exists( $abs ) ) {
      wp_die( esc_html( 'Fichier introuvable sur le serveur.' ) );
    }

    $filename = ! empty( $doc->file_name ) ? (string) $doc->file_name : basename( $abs );
    $mime     = function_exists( 'mime_content_type' ) ? @mime_content_type( $abs ) : '';
    if ( ! $mime ) {
      $allowed = $this->get_acdc_trainer_document_allowed_mimes();
      $ext     = strtolower( pathinfo( $abs, PATHINFO_EXTENSION ) );
      $mime    = isset( $allowed[ $ext ] ) ? $allowed[ $ext ] : 'application/octet-stream';
    }

    nocache_headers();
    header( 'Content-Type: ' . $mime );
    header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $filename ) . '"' );
    header( 'Content-Length: ' . filesize( $abs ) );
    header( 'X-Content-Type-Options: nosniff' );
    $this->trainer_portal_log_event( (int) $account->id, 'document_downloaded', array( 'document_id' => (int) $document_id ), (int) $account->trainer_id );
    readfile( $abs );
    exit;
  }

  /* ====================================================================
   * ACDC 3.20.89 — Édition du profil par le formateur lui-même.
   * ==================================================================== */

  /**
   * Mise à jour des champs profil par le formateur.
   * Sécurité : allowlist stricte côté serveur — tout champ hors liste est ignoré.
   * Photo : remplacement via wp_handle_upload (cohérent avec l'admin).
   */
  public function handle_trainer_update_own_profile() {
    check_admin_referer( 'acdc_trainer_update_own_profile' );
    $account = $this->trainer_portal_require_auth();
    $trainer = $this->get_trainer( (int) $account->trainer_id );
    if ( ! $trainer ) {
      $this->trainer_portal_redirect( 'profile', 'Profil formateur introuvable. Contactez l’administrateur.', 'error' );
    }

    global $wpdb;
    $input = isset( $_POST['profile'] ) && is_array( $_POST['profile'] ) ? wp_unslash( $_POST['profile'] ) : array();

    $editable = $this->get_acdc_trainer_self_editable_fields();
    $data     = array();

    // 1. Texte / sélection.
    foreach ( array( 'gender', 'first_name', 'last_name', 'phone' ) as $key ) {
      if ( in_array( $key, $editable, true ) && isset( $input[ $key ] ) ) {
        $data[ $key ] = sanitize_text_field( (string) $input[ $key ] );
      }
    }

    // 2. Date de naissance.
    if ( in_array( 'birth_date', $editable, true ) && isset( $input['birth_date'] ) ) {
      $bd = trim( (string) $input['birth_date'] );
      $data['birth_date'] = ( '' === $bd ) ? null : sanitize_text_field( $bd );
    }

    // 3. Bio.
    if ( in_array( 'description_text', $editable, true ) && isset( $input['description_text'] ) ) {
      $data['description_text'] = wp_kses_post( (string) $input['description_text'] );
    }

    // 4. Préférences notifications + opt-in apprenants (cases à cocher).
    foreach ( array( 'session_reminder_enabled', 'session_start_enabled', 'learner_info_photo', 'learner_info_name', 'learner_info_description', 'learner_info_availability' ) as $key ) {
      if ( in_array( $key, $editable, true ) ) {
        $data[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
      }
    }

    // 5. Validation : prénom et nom obligatoires.
    if ( isset( $data['first_name'] ) && '' === trim( $data['first_name'] ) ) {
      $this->trainer_portal_redirect( 'profile', 'Le prénom est obligatoire.', 'error' );
    }
    if ( isset( $data['last_name'] ) && '' === trim( $data['last_name'] ) ) {
      $this->trainer_portal_redirect( 'profile', 'Le nom est obligatoire.', 'error' );
    }

    // 6. Photo : upload optionnel.
    if ( ! empty( $_FILES['profile_photo'] ) && ! empty( $_FILES['profile_photo']['name'] ) && UPLOAD_ERR_OK === (int) $_FILES['profile_photo']['error'] ) {
      $size_max = $this->get_acdc_trainer_photo_max_size_bytes();
      if ( (int) $_FILES['profile_photo']['size'] > $size_max ) {
        $this->trainer_portal_redirect( 'profile', 'La photo dépasse 5 Mo.', 'error' );
      }
      $allowed = $this->get_acdc_trainer_photo_allowed_mimes();
      $ext     = strtolower( pathinfo( (string) $_FILES['profile_photo']['name'], PATHINFO_EXTENSION ) );
      if ( '' === $ext || ! isset( $allowed[ $ext ] ) ) {
        $this->trainer_portal_redirect( 'profile', 'Format de photo non autorisé. Formats acceptés : JPG, PNG, WEBP.', 'error' );
      }
      $check = wp_check_filetype_and_ext( (string) $_FILES['profile_photo']['tmp_name'], (string) $_FILES['profile_photo']['name'], $allowed );
      if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
        $this->trainer_portal_redirect( 'profile', 'Le contenu du fichier ne correspond pas à son extension. Upload refusé.', 'error' );
      }
      // Upload via le mécanisme WordPress standard (cohérent avec l'admin).
      require_once ABSPATH . 'wp-admin/includes/file.php';
      $upload = wp_handle_upload(
        $_FILES['profile_photo'],
        array( 'test_form' => false, 'mimes' => $allowed )
      );
      if ( ! empty( $upload['error'] ) ) {
        $this->trainer_portal_redirect( 'profile', 'Erreur lors de l’upload de la photo : ' . $upload['error'], 'error' );
      }
      if ( ! empty( $upload['url'] ) ) {
        $data['photo_url'] = esc_url_raw( (string) $upload['url'] );
      }
    }

    // 7. Demande de retrait de la photo (case à cocher dédiée).
    if ( ! empty( $_POST['remove_photo'] ) ) {
      $data['photo_url'] = '';
    }

    // 8. Sauvegarde si quelque chose a changé.
    if ( ! empty( $data ) ) {
      $data['updated_at'] = current_time( 'mysql' );
      $wpdb->update( $this->trainer_table, $data, array( 'id' => (int) $trainer->id ) );
      $this->trainer_portal_log_event( (int) $account->id, 'profile_updated', array( 'fields' => array_keys( $data ) ), (int) $trainer->id );
    }

    $this->trainer_portal_redirect( 'profile', 'Votre profil a bien été mis à jour.', 'success' );
  }

  /* ====================================================================
   * ACDC 3.20.90 — Calendrier annuel des disponibilités (côté formateur).
   * ==================================================================== */

  /**
   * Mise à jour du schéma hebdomadaire « habituel » du formateur.
   * Le schéma est la base sur laquelle les exceptions s'appliquent.
   */
  public function handle_trainer_update_weekly_schedule() {
    check_admin_referer( 'acdc_trainer_update_weekly_schedule' );
    $account = $this->trainer_portal_require_auth();
    $trainer = $this->get_trainer( (int) $account->trainer_id );
    if ( ! $trainer ) {
      $this->trainer_portal_redirect( 'availability', 'Profil formateur introuvable. Contactez l’administrateur.', 'error' );
    }

    $availability = $this->parse_trainer_availability( $trainer->availability_json );
    $weekly_input = isset( $_POST['weekly'] ) && is_array( $_POST['weekly'] ) ? wp_unslash( $_POST['weekly'] ) : array();

    foreach ( $this->get_acdc_weekday_keys() as $day ) {
      $row = isset( $weekly_input[ $day ] ) && is_array( $weekly_input[ $day ] ) ? $weekly_input[ $day ] : array();
      $availability['weekly'][ $day ] = array(
        'morning'   => ! empty( $row['morning'] ),
        'afternoon' => ! empty( $row['afternoon'] ),
      );
    }

    global $wpdb;
    $wpdb->update(
      $this->trainer_table,
      array(
        'availability_json' => $this->serialize_trainer_availability( $availability ),
        'updated_at'        => current_time( 'mysql' ),
      ),
      array( 'id' => (int) $trainer->id )
    );
    $this->trainer_portal_log_event( (int) $account->id, 'availability_weekly_updated', array(), (int) $trainer->id );
    $this->trainer_portal_redirect( 'availability', 'Votre rythme habituel a été mis à jour.', 'success' );
  }

  /**
   * Endpoint AJAX : bascule l'état d'une demi-journée à une date donnée.
   * Auto-cleaning : si après bascule l'état correspond au schéma habituel,
   * l'exception est supprimée plutôt qu'enregistrée.
   *
   * Renvoie un JSON :
   * {
   *   success: true,
   *   data: {
   *     morning: bool, afternoon: bool, is_exception: bool, note: string,
   *     cssClass: 'morning|afternoon état complet pour mise à jour DOM'
   *   }
   * }
   */
  public function handle_trainer_toggle_availability() {
    if ( ! check_ajax_referer( 'acdc_trainer_toggle_availability', '_wpnonce', false ) ) {
      wp_send_json_error( array( 'message' => 'Jeton de sécurité invalide. Rechargez la page.' ), 403 );
    }
    $account = $this->trainer_portal_get_current_account();
    if ( ! $account ) {
      wp_send_json_error( array( 'message' => 'Session expirée. Reconnectez-vous.' ), 401 );
    }
    $trainer = $this->get_trainer( (int) $account->trainer_id );
    if ( ! $trainer ) {
      wp_send_json_error( array( 'message' => 'Profil formateur introuvable.' ), 404 );
    }

    $date_str = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
    $part     = isset( $_POST['part'] ) ? sanitize_key( wp_unslash( $_POST['part'] ) ) : '';

    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_str ) ) {
      wp_send_json_error( array( 'message' => 'Date invalide.' ), 400 );
    }
    if ( ! in_array( $part, array( 'morning', 'afternoon' ), true ) ) {
      wp_send_json_error( array( 'message' => 'Demi-journée invalide.' ), 400 );
    }

    $availability = $this->parse_trainer_availability( $trainer->availability_json );

    // État courant.
    $state = $this->get_trainer_day_availability( $availability, $date_str );

    // Bascule la demi-journée demandée.
    $new_morning   = $state['morning'];
    $new_afternoon = $state['afternoon'];
    if ( 'morning' === $part ) {
      $new_morning = ! $new_morning;
    } else {
      $new_afternoon = ! $new_afternoon;
    }

    // Vérifier si le nouvel état correspond au schéma habituel.
    $weekday = $this->date_to_weekday_key( $date_str );
    $base    = isset( $availability['weekly'][ $weekday ] ) ? $availability['weekly'][ $weekday ] : array( 'morning' => false, 'afternoon' => false );
    $matches_base = ( ! empty( $base['morning'] ) === $new_morning ) && ( ! empty( $base['afternoon'] ) === $new_afternoon );

    if ( $matches_base ) {
      // Supprimer l'exception si elle existait.
      if ( isset( $availability['exceptions'][ $date_str ] ) ) {
        unset( $availability['exceptions'][ $date_str ] );
      }
      $is_exception = false;
    } else {
      // Enregistrer/mettre à jour l'exception.
      $note = isset( $availability['exceptions'][ $date_str ]['note'] ) ? (string) $availability['exceptions'][ $date_str ]['note'] : '';
      $availability['exceptions'][ $date_str ] = array(
        'morning'   => $new_morning,
        'afternoon' => $new_afternoon,
        'note'      => $note,
      );
      $is_exception = true;
    }

    // Sauvegarde.
    global $wpdb;
    $wpdb->update(
      $this->trainer_table,
      array(
        'availability_json' => $this->serialize_trainer_availability( $availability ),
        'updated_at'        => current_time( 'mysql' ),
      ),
      array( 'id' => (int) $trainer->id )
    );

    wp_send_json_success( array(
      'date'         => $date_str,
      'part'         => $part,
      'morning'      => $new_morning,
      'afternoon'    => $new_afternoon,
      'is_exception' => $is_exception,
      'morning_class'   => $this->build_availability_cell_class( $new_morning, $is_exception ),
      'afternoon_class' => $this->build_availability_cell_class( $new_afternoon, $is_exception ),
    ) );
  }

  /**
   * Helper interne : retourne la classe CSS pour une demi-cellule du calendrier.
   */
  private function build_availability_cell_class( $is_available, $is_exception ) {
    if ( $is_available && $is_exception ) {
      return 'is-yes is-exception';
    }
    if ( $is_available ) {
      return 'is-yes';
    }
    if ( $is_exception ) {
      return 'is-no is-exception';
    }
    return 'is-no';
  }

  /**
   * ACDC 3.24.28 — M8b : Sauvegarde du bilan post-formation (5 champs, modifiable).
   */
  public function handle_trainer_save_session_report() {
    global $wpdb;
    $account = $this->trainer_portal_require_auth();
    $trainer_id = (int) $account->trainer_id;

    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'acdc_trainer_save_session_report' ) ) {
      $this->trainer_portal_redirect( 'sessions', 'Erreur de sécurité.', 'error' );
    }

    $session_id = isset( $_POST['session_id'] ) ? absint( wp_unslash( $_POST['session_id'] ) ) : 0;
    if ( ! $session_id ) {
      $this->trainer_portal_redirect( 'sessions', 'Session introuvable.', 'error' );
    }

    $owns = $wpdb->get_var( $wpdb->prepare(
      "SELECT id FROM {$this->session_table} WHERE id = %d AND ( trainer_id = %d OR EXISTS ( SELECT 1 FROM {$this->group_table} g WHERE g.session_id = %d AND g.trainer_id = %d ) ) LIMIT 1",
      $session_id, $trainer_id, $session_id, $trainer_id
    ) );
    if ( ! $owns ) {
      $this->trainer_portal_redirect( 'sessions', 'Accès refusé.', 'error' );
    }

    $existing = $wpdb->get_var( $wpdb->prepare( "SELECT report_submitted_at FROM {$this->session_table} WHERE id = %d LIMIT 1", $session_id ) );
    $submitted_at = $existing ? $existing : current_time( 'mysql' );

    $data = array(
      'report_group_level'        => isset( $_POST['report_group_level'] ) ? sanitize_text_field( wp_unslash( $_POST['report_group_level'] ) ) : '',
      'report_objectives_reached' => isset( $_POST['report_objectives_reached'] ) ? sanitize_text_field( wp_unslash( $_POST['report_objectives_reached'] ) ) : '',
      'report_incidents'          => isset( $_POST['report_incidents'] ) ? sanitize_textarea_field( wp_unslash( $_POST['report_incidents'] ) ) : '',
      'report_recommendations'    => isset( $_POST['report_recommendations'] ) ? sanitize_textarea_field( wp_unslash( $_POST['report_recommendations'] ) ) : '',
      'report_submitted_at'       => $submitted_at,
    );

    $wpdb->update( $this->session_table, $data, array( 'id' => $session_id ) );

    $this->trainer_portal_redirect( 'sessions', 'Bilan enregistré.', 'success', array( 'session_id' => $session_id ) );
  }

  /**
   * ACDC 3.24.28 — M8b : Sauvegarde d'une entrée du cahier de texte (upsert par session_id + slot_date).
   */
  public function handle_trainer_save_logbook_entry() {
    global $wpdb;
    $account = $this->trainer_portal_require_auth();
    $trainer_id = (int) $account->trainer_id;

    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'acdc_trainer_save_logbook_entry' ) ) {
      $this->trainer_portal_redirect( 'sessions', 'Erreur de sécurité.', 'error' );
    }

    $session_id = isset( $_POST['session_id'] ) ? absint( wp_unslash( $_POST['session_id'] ) ) : 0;
    $slot_date  = isset( $_POST['slot_date'] ) ? sanitize_text_field( wp_unslash( $_POST['slot_date'] ) ) : '';
    $slot_label = isset( $_POST['slot_label'] ) ? sanitize_text_field( wp_unslash( $_POST['slot_label'] ) ) : '';
    $content    = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';

    if ( ! $session_id || ! $slot_date ) {
      $this->trainer_portal_redirect( 'sessions', 'Données manquantes.', 'error' );
    }

    $owns = $wpdb->get_var( $wpdb->prepare(
      "SELECT id FROM {$this->session_table} WHERE id = %d AND ( trainer_id = %d OR EXISTS ( SELECT 1 FROM {$this->group_table} g WHERE g.session_id = %d AND g.trainer_id = %d ) ) LIMIT 1",
      $session_id, $trainer_id, $session_id, $trainer_id
    ) );
    if ( ! $owns ) {
      $this->trainer_portal_redirect( 'sessions', 'Accès refusé.', 'error' );
    }

    $existing_id = $wpdb->get_var( $wpdb->prepare(
      "SELECT id FROM {$this->trainer_logbook_table} WHERE session_id = %d AND slot_date = %s LIMIT 1",
      $session_id, $slot_date
    ) );

    $now = current_time( 'mysql' );
    if ( $existing_id ) {
      $wpdb->update( $this->trainer_logbook_table, array( 'content' => $content, 'slot_label' => $slot_label, 'updated_at' => $now ), array( 'id' => (int) $existing_id ) );
    } else {
      $wpdb->insert( $this->trainer_logbook_table, array( 'session_id' => $session_id, 'slot_date' => $slot_date, 'slot_label' => $slot_label, 'content' => $content, 'updated_at' => $now ) );
    }

    $this->trainer_portal_redirect( 'sessions', 'Entrée enregistrée.', 'success', array( 'session_id' => $session_id ) );
  }
}
