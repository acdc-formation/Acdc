<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Learner_Portal_Render_Trait {


  public function render_learner_portal_home_shortcode() {
    $account = $this->learner_portal_get_current_account();

    if ( $account ) {
      wp_safe_redirect( $this->learner_portal_page_url( 'dashboard' ) );
      exit;
    }

    ob_start();
    ?>
    <div class="acdc-portal-shell acdc-learner-home-shell">
      <div class="acdc-panel acdc-learner-home-panel">
        <div class="acdc-login-branding acdc-learner-home-branding">
          <img src="<?php echo esc_url( $this->get_plugin_logo_url() ); ?>" alt="ACDC Formation" class="acdc-login-logo">
          <h1>Extranet apprenants</h1>
          <p>Accès sécurisé à vos formations, à votre planning, à vos documents et à votre profil.</p>
        </div>
        <?php $this->render_front_notice(); ?>
        <div class="acdc-grid-2cols acdc-mt-24">
          <div class="acdc-panel">
            <h3>Déjà inscrit</h3>
            <p>Connectez-vous pour accéder à votre espace apprenant.</p>
            <p><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $this->learner_portal_login_url() ); ?>">Connexion apprenant</a></p>
          </div>
          <div class="acdc-panel">
            <h3>Première connexion</h3>
            <p>Utilisez le lien reçu par e-mail pour activer votre accès et définir votre mot de passe.</p>
            <p><a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->learner_portal_login_url( array( 'view' => 'forgot' ) ) ); ?>">Mot de passe oublié</a></p>
          </div>
        </div>
      </div>
    </div>
    <?php
    return (string) ob_get_clean();
  }

  public function render_learner_login_shortcode() {
    return $this->render_learner_portal_login_shortcode();
  }

  public function render_learner_portal_login_shortcode() {
    $view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'login';
    $token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
    $email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';

    ob_start();
    ?>
    <div class="acdc-portal-shell acdc-login-shell acdc-learner-login-shell">
      <div class="acdc-login-card acdc-learner-login-card">
        <div class="acdc-login-branding">
          <img src="<?php echo esc_url( $this->get_plugin_logo_url() ); ?>" alt="ACDC Formation" class="acdc-login-logo">
          <h1>Espace apprenant</h1>
          <p>Accès sécurisé à vos formations, documents et informations utiles.</p>
        </div>

        <?php $this->render_front_notice(); ?>

        <?php if ( 'forgot' === $view ) : ?>
          <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'acdc_learner_request_reset' ); ?>
            <input type="hidden" name="action" value="acdc_learner_request_reset">
            <p><label for="acdc_learner_forgot_email">Votre e-mail</label><input type="email" id="acdc_learner_forgot_email" name="learner_email" required></p>
            <p><button type="submit" class="acdc-button acdc-button-primary acdc-button-block">Envoyer le lien de réinitialisation</button></p>
          </form>
          <p class="acdc-login-secondary-link">Besoin d’aide ? Contactez <?php echo esc_html( $this->learner_portal_contact_email() ); ?>.</p>
          <p class="acdc-login-secondary-link"><a href="<?php echo esc_url( $this->learner_portal_login_url() ); ?>">Retour à la connexion</a></p>
        <?php elseif ( 'reset' === $view ) : ?>
          <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'acdc_learner_reset_password' ); ?>
            <input type="hidden" name="action" value="acdc_learner_reset_password">
            <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
            <p><label for="acdc_learner_reset_1">Nouveau mot de passe</label><input type="password" id="acdc_learner_reset_1" name="password_1" required minlength="8"></p>
            <p><label for="acdc_learner_reset_2">Confirmer le mot de passe</label><input type="password" id="acdc_learner_reset_2" name="password_2" required minlength="8"></p>
            <p><button type="submit" class="acdc-button acdc-button-primary acdc-button-block">Réinitialiser mon mot de passe</button></p>
          </form>
        <?php elseif ( 'activate' === $view ) : ?>
          <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'acdc_learner_activate' ); ?>
            <input type="hidden" name="action" value="acdc_learner_activate">
            <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
            <p><label for="acdc_learner_activate_1">Créer votre mot de passe</label><input type="password" id="acdc_learner_activate_1" name="password_1" required minlength="8"></p>
            <p><label for="acdc_learner_activate_2">Confirmer le mot de passe</label><input type="password" id="acdc_learner_activate_2" name="password_2" required minlength="8"></p>
            <p><button type="submit" class="acdc-button acdc-button-primary acdc-button-block">Activer mon accès</button></p>
          </form>
        <?php elseif ( 'expired' === $view ) : ?>
          <div class="acdc-alert acdc-alert-error"><p>Votre accès extranet est expiré ou désactivé.</p><p>Pour toute aide, contactez <?php echo esc_html( $this->learner_portal_contact_email() ); ?>.</p></div>
          <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'acdc_learner_expired_contact' ); ?>
            <input type="hidden" name="action" value="acdc_learner_expired_contact">
            <p><label for="acdc_learner_expired_email">Votre e-mail</label><input type="email" id="acdc_learner_expired_email" name="learner_email" value="<?php echo esc_attr( $email ); ?>" required></p>
            <p><label for="acdc_learner_expired_message">Votre message</label><textarea id="acdc_learner_expired_message" name="message" rows="4" placeholder="Précisez votre besoin de réactivation ou d’assistance."></textarea></p>
            <p><button type="submit" class="acdc-button acdc-button-primary acdc-button-block">Envoyer ma demande</button></p>
          </form>
          <p class="acdc-login-secondary-link"><a href="<?php echo esc_url( $this->learner_portal_login_url() ); ?>">Retour à la connexion</a></p>
        <?php else : ?>
          <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'acdc_learner_login' ); ?>
            <input type="hidden" name="action" value="acdc_learner_login">
            <p><label for="acdc_learner_email">E-mail</label><input type="email" id="acdc_learner_email" name="learner_email" required></p>
            <p><label for="acdc_learner_password">Mot de passe</label><input type="password" id="acdc_learner_password" name="learner_password" required></p>
            <p><button type="submit" class="acdc-button acdc-button-primary acdc-button-block">Se connecter</button></p>
          </form>
          <p class="acdc-login-secondary-link"><a href="<?php echo esc_url( $this->learner_portal_login_url( array( 'view' => 'forgot' ) ) ); ?>">Mot de passe oublié</a></p>
        <?php endif; ?>
      </div>
    </div>
    <?php
    return (string) ob_get_clean();
  }

  public function render_learner_portal_shortcode( $atts = array() ) {
    $atts    = shortcode_atts( array( 'tab' => 'dashboard' ), $atts, 'acdc_of_learner_portal' );
    $tab     = sanitize_key( $atts['tab'] );
    $account = $this->learner_portal_get_current_account();

    if ( ! $account ) {
      return '<div class="acdc-portal-shell"><div class="acdc-panel"><h2>Accès requis</h2><p>Veuillez vous connecter pour accéder à votre espace apprenant.</p><p><a class="acdc-button acdc-button-primary" href="' . esc_url( $this->learner_portal_login_url() ) . '">Aller à la connexion</a></p></div></div>';
    }

    $primary = $this->learner_portal_get_primary_learner_profile( $account->email );
    $items   = $this->learner_portal_get_access_items_for_email( $account->email );

    ob_start();
    ?>
    <div class="acdc-portal-shell acdc-learner-portal-shell">
      <div class="acdc-portal-layout acdc-learner-portal-layout">
        <aside class="acdc-portal-nav acdc-learner-portal-nav">
          <?php $this->render_brand_block(); ?>
          <?php $this->render_learner_portal_navigation( $tab ); ?>
        </aside>
        <main class="acdc-portal-content acdc-learner-portal-content">
          <div class="acdc-extranet-topbar acdc-learner-topbar">
            <div>
              <strong><?php echo esc_html( $primary ? $this->learner_portal_display_name( $primary ) : $account->email ); ?></strong>
              <div class="acdc-login-secondary-link">Statut : <?php echo esc_html( $this->learner_portal_status_label( $account->status ) ); ?></div>
            </div>
            <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'acdc_learner_logout' ), admin_url( 'admin-post.php' ) ), 'acdc_learner_logout' ) ); ?>">Se déconnecter</a>
          </div>

          <?php $this->render_front_notice(); ?>

          <?php
          // ACDC 3.25.31 — Dispatch des actions portal apprenant (évite admin-post.php bloqué par WAF)
          if ( isset( $_GET['lp_action'] ) ) {
            $lp_action = sanitize_key( wp_unslash( $_GET['lp_action'] ) );
            if ( 'download_document' === $lp_action ) {
              $this->handle_learner_portal_download_document();
              exit;
            }
            if ( 'open_resource' === $lp_action ) {
              $this->handle_learner_portal_open_resource();
              exit;
            }
          }
          ?><?php
          switch ( $tab ) {
            case 'formations':
              $this->render_learner_portal_formations_tab( $account, $items );
              break;
            case 'formation':
              $this->render_learner_portal_formation_tab( $account, $items );
              break;
            case 'planning':
              $this->render_learner_portal_planning_tab( $account );
              break;
            case 'documents':
              $this->render_learner_portal_documents_tab( $account );
              break;
            case 'library':
              $this->render_learner_portal_library_tab( $account );
              break;
            case 'profile':
              $this->render_learner_portal_profile_tab( $account, $primary );
              break;
            case 'mes_quiz':
              $qz_participant_id = isset( $_GET['qz_participant'] ) ? absint( wp_unslash( $_GET['qz_participant'] ) ) : 0;
              if ( $qz_participant_id > 0 ) {
                $this->render_learner_portal_quiz_detail( $account, $qz_participant_id );
              } else {
                $this->render_learner_portal_mes_quiz_tab( $account );
              }
              break;
            case 'mes_signatures':
              $this->render_learner_portal_mes_signatures_tab( $account );
              break;
            case 'dashboard':
            default:
              $this->render_learner_portal_dashboard_tab( $account, $primary );
              break;
          }
          ?>
        </main>
      </div>
    </div>
    <?php
    return (string) ob_get_clean();
  }

  private function render_learner_portal_navigation( $active_tab ) {
    $items = array(
      'dashboard'  => array( 'label' => 'Tableau de bord', 'url' => $this->learner_portal_page_url( 'dashboard' ) ),
      'formations' => array( 'label' => 'Mes formations', 'url' => $this->learner_portal_page_url( 'formations' ) ),
      'planning'   => array( 'label' => 'Mon planning', 'url' => $this->learner_portal_page_url( 'planning' ) ),
      'documents'  => array( 'label' => 'Mes documents', 'url' => $this->learner_portal_page_url( 'documents' ) ),
      'library'    => array( 'label' => 'Ma bibliothèque', 'url' => $this->learner_portal_page_url( 'library' ) ),
      'mes_quiz'        => array( 'label' => 'Mes quiz', 'url' => $this->learner_portal_page_url( 'mes_quiz' ) ),
      'mes_signatures'  => array( 'label' => 'Mes signatures', 'url' => $this->learner_portal_page_url( 'mes_signatures' ) ),
      'profile'         => array( 'label' => 'Mon profil', 'url' => $this->learner_portal_page_url( 'profile' ) ),
    );
    echo '<nav class="acdc-learner-nav-list">';
    foreach ( $items as $tab => $item ) {
      $class = $tab === $active_tab ? ' acdc-learner-nav-item-active' : '';
      echo '<a class="acdc-learner-nav-item' . esc_attr( $class ) . '" href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
    }
    echo '</nav>';
  }

  private function render_learner_portal_dashboard_tab( $account, $primary ) {
    $stats         = $this->learner_portal_get_dashboard_stats( $account->email );
    $items         = $this->learner_portal_get_access_items_for_email( $account->email );
    $sessions      = $this->learner_portal_get_upcoming_sessions( $account->email, 5 );
    $notifications = $this->learner_portal_get_access_notifications( $account->email );
    $new_items_count = $this->learner_portal_get_new_items_count( $account->email, (int) $account->id );
    $display_name  = $primary ? $this->learner_portal_display_name( $primary ) : $account->email;
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Tableau de bord</h2>
        <p>Retrouvez l’essentiel de votre parcours, vos échéances et vos documents utiles.</p>
      </div>
    </section>
    <div class="acdc-cards-grid acdc-cards-grid-dashboard acdc-cards-grid-workflow acdc-learner-dashboard-grid">
      <?php $this->render_workflow_card( 'Mes formations', $stats['formations'], $this->learner_portal_page_url( 'formations' ) ); ?>
      <?php $this->render_workflow_card( 'Prochaines séances', $stats['upcoming'], $this->learner_portal_page_url( 'planning' ) ); ?>
      <?php $this->render_workflow_card( 'Documents disponibles', $stats['documents'], $this->learner_portal_page_url( 'documents' ) ); ?>
      <?php
      $pending_quizzes_count = count( $this->get_learner_pending_quizzes( $account->email ) );
      if ( $pending_quizzes_count > 0 ) :
        $this->render_workflow_card( 'Quiz à faire', $pending_quizzes_count, $this->learner_portal_page_url( 'mes_quiz' ), true );
      endif;
      ?>
      <?php $this->render_workflow_card( 'Formateur référent', ! empty( $items[0]['trainer_label'] ) ? $items[0]['trainer_label'] : '—', $this->learner_portal_page_url( 'formations' ) ); ?>
      <?php /* ACDC 3.20.95 — Carte « Mon profil » retirée. Le nom complet de l'apprenant
               est déjà visible dans la topbar en haut à droite + dans la sidebar (lien Mon profil),
               et l'utilisation de render_workflow_card avec un nom long produisait un effet
               typographique disproportionné. Le lien d'accès au profil reste dans la sidebar. */ ?>
    </div>

    <div class="acdc-grid-2cols acdc-mt-24">
      <div class="acdc-panel">
        <h3>Mes formations</h3>
        <?php if ( ! empty( $items ) ) : ?>
          <ul class="acdc-learner-list-plain">
            <?php foreach ( $items as $item ) : ?>
              <?php $title = ! empty( $item['formation']->title ) ? $item['formation']->title : ( ! empty( $item['registration']->formation_title ) ? $item['registration']->formation_title : 'Formation' ); ?>
              <li><a href="<?php echo esc_url( $this->learner_portal_page_url( 'formation', array( 'registration_id' => (int) $item['registration']->id ) ) ); ?>"><?php echo esc_html( $title ); ?></a><br><small>Accès jusqu’au <?php echo esc_html( $this->learner_portal_format_date( $item['expires_at'], true ) ); ?></small></li>
            <?php endforeach; ?>
          </ul>
        <?php else : ?>
          <p>Aucune formation accessible pour le moment.</p>
        <?php endif; ?>
      </div>
      <div class="acdc-panel">
        <h3>Prochaines séances</h3>
        <?php if ( ! empty( $sessions ) ) : ?>
          <ul class="acdc-learner-list-plain">
            <?php foreach ( $sessions as $entry ) : ?>
              <?php $session = $entry['session']; ?>
              <li>
                <strong><?php echo esc_html( ! empty( $entry['formation']->title ) ? $entry['formation']->title : $session->title ); ?></strong><br>
                <small><?php echo esc_html( $this->learner_portal_format_date( ! empty( $session->start_at ) ? $session->start_at : $session->start_date, true ) ); ?><?php if ( ! empty( $session->location ) ) : ?> · <?php echo esc_html( $session->location ); ?><?php elseif ( ! empty( $session->remote_link ) ) : ?> · Distanciel<?php endif; ?></small>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else : ?>
          <p>Aucune séance à venir.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="acdc-grid-2cols acdc-mt-24">
      <div class="acdc-panel">
        <h3>Notifications</h3>
        <?php if ( ! empty( $notifications ) ) : ?>
          <ul class="acdc-learner-list-plain">
            <?php foreach ( $notifications as $notification ) : ?>
              <li><strong><?php echo esc_html( $notification['label'] ); ?></strong><br><small><?php echo esc_html( $notification['message'] ); ?></small></li>
            <?php endforeach; ?>
          </ul>
        <?php else : ?>
          <p>Aucune notification pour le moment.</p>
        <?php endif; ?>
      </div>
      <div class="acdc-panel">
        <h3>Accès rapide</h3>
        <div class="acdc-inline-wrap">
          <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $this->learner_portal_page_url( 'documents' ) ); ?>">Ouvrir mes documents</a>
          <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->learner_portal_page_url( 'profile', array( 'panel' => 'password' ) ) ); ?>">Modifier mon mot de passe</a>
        </div>
      </div>
    </div>
    <?php
  }

  /**
   * ACDC 3.21.06 — Onglet "Mes quiz" du portail apprenant.
   * Deux sections : quiz en attente (avec lien de passation) + quiz complétés.
   */
  private function render_learner_portal_mes_quiz_tab( $account ) {
    $pending   = $this->get_learner_pending_quizzes( $account->email );
    $completed = $this->get_learner_completed_quizzes( $account->email );

    $purpose_labels = array(
      'live'        => 'Quiz live',
      'positioning' => 'Test de positionnement',
      'diagnostic'  => 'Évaluation diagnostique',
      'assessment'  => 'Évaluation des acquis',
      'poll'        => 'Sondage',
    );
    $status_labels = array(
      'pending'  => 'En attente',
      'invited'  => 'Invité',
      'opened'   => 'Ouvert',
      'started'  => 'En cours',
      'partial'  => 'En cours',
    );
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Mes quiz</h2>
        <p class="acdc-section-subtitle">Retrouvez vos tests de positionnement, évaluations et quiz à compléter.</p>
      </div>
    </section>

    <?php if ( ! empty( $pending ) ) : ?>
    <div class="acdc-results-section" style="margin-bottom:28px;">
      <?php echo $this->qz_section_lock_svg(); ?>
      <div class="acdc-results-eyebrow">À COMPLÉTER</div>
      <div class="acdc-results-inner">
        <table class="acdc-results-table">
          <thead>
            <tr>
              <th>Quiz / Test</th>
              <th>Type</th>
              <th>Statut</th>
              <th>Reçu le</th>
              <th>Expire le</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $pending as $p ) :
              $passation_url = $this->build_qz_async_public_url( (string) $p->secure_token );
              $purpose_label = isset( $purpose_labels[ $p->quiz_purpose ] ) ? $purpose_labels[ $p->quiz_purpose ] : ucfirst( (string) $p->quiz_purpose );
              $status_label  = isset( $status_labels[ $p->participant_status ] ) ? $status_labels[ $p->participant_status ] : (string) $p->participant_status;
              $expires_label = ! empty( $p->token_expires_at ) ? $this->learner_portal_format_date( $p->token_expires_at, true ) : '—';
              $invited_label = ! empty( $p->invited_at ) ? $this->learner_portal_format_date( $p->invited_at, true ) : '—';
            ?>
            <tr>
              <td><strong><?php echo esc_html( $p->quiz_title ); ?></strong></td>
              <td><?php echo esc_html( $purpose_label ); ?></td>
              <td>
                <span style="display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#fff4d6;color:#8a6d2a;">
                  <?php echo esc_html( $status_label ); ?>
                </span>
              </td>
              <td><?php echo esc_html( $invited_label ); ?></td>
              <td><?php echo esc_html( $expires_label ); ?></td>
              <td>
                <a href="<?php echo esc_url( $passation_url ); ?>" class="acdc-button acdc-button-primary" style="height:32px;padding:0 14px;font-size:13px;display:inline-flex;align-items:center;border-radius:8px;" target="_blank">
                  <?php echo 'partial' === $p->participant_status || 'started' === $p->participant_status ? 'Reprendre' : 'Commencer'; ?>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php else : ?>
    <div class="acdc-panel" style="margin-bottom:28px;">
      <p>Aucun quiz ou test en attente pour le moment.</p>
    </div>
    <?php endif; ?>

    <?php if ( empty( $completed ) ) : ?>
      <?php /* ACDC 3.25.176 — La section « Complétés » n'existait QUE si l'apprenant
               avait au moins une passation : sans elle, la page ne montrait rien et rien
               n'indiquait qu'un historique existe. Un apprenant — et la recette — en
               concluait que la consultation de ses résultats n'était pas prévue. */ ?>
      <div class="acdc-results-section">
        <div class="acdc-results-eyebrow">MES RÉSULTATS</div>
        <div class="acdc-results-inner" style="padding:18px 20px;">
          <p style="margin:0 0 6px;">Aucune passation terminée pour le moment.</p>
          <p style="margin:0;color:#5a6577;font-size:13px;">
            Vos résultats apparaîtront ici dès qu'un test ou une évaluation aura été
            passé et corrigé. Une évaluation passée en salle n'y figure que si vous vous
            êtes identifié en rejoignant la session.
          </p>
        </div>
      </div>
    <?php endif; ?>

    <?php if ( ! empty( $completed ) ) : ?>
    <div class="acdc-results-section">
      <?php echo $this->qz_section_lock_svg(); ?>
      <div class="acdc-results-eyebrow">COMPLÉTÉS</div>
      <div class="acdc-results-inner">
        <table class="acdc-results-table">
          <thead>
            <tr>
              <th>Quiz / Test</th>
              <th>Type</th>
              <th>Complété le</th>
              <th>Score</th>
              <th>Résultat</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $completed as $p ) :
              $purpose_label  = isset( $purpose_labels[ $p->quiz_purpose ] ) ? $purpose_labels[ $p->quiz_purpose ] : ucfirst( (string) $p->quiz_purpose );
              $completed_date = ! empty( $p->completed_at ) ? $this->learner_portal_format_date( $p->completed_at, true ) : '—';
              $score_pct      = null !== $p->total_score_percentage ? number_format( (float) $p->total_score_percentage, 1, ',', '' ) . ' %' : '—';
              if ( null !== $p->is_passed ) {
                $result_label = (int) $p->is_passed === 1
                  ? '<span style="color:#2e7d32;font-weight:600;">✓ Réussi</span>'
                  : '<span style="color:#c62828;font-weight:600;">✗ Non réussi</span>';
              } else {
                $result_label = '<span style="color:#5a6577;">—</span>';
              }
              /* ACDC 3.25.215 — Le lien « Détail » sortait du portail.
                 Il collait « &qz_participant=… » à la fin d'un permalien qui ne
                 porte aucun paramètre : l'adresse obtenue se terminait par
                 « /mes-quiz/&qz_participant=3 », que WordPress ne sait pas
                 résoudre. Il tentait alors de deviner la page voulue et
                 atterrissait ailleurs — sur les CGU, en l'occurrence.
                 On passe le paramètre par la fonction, qui l'ajoute proprement,
                 avec un « ? » si c'est le premier. */
              $detail_url = $this->learner_portal_page_url( 'mes_quiz', array( 'qz_participant' => (int) $p->participant_id ) );
              $has_pdf    = ! empty( $p->result_document_url );
            ?>
            <tr>
              <td><strong><?php echo esc_html( $p->quiz_title ); ?></strong></td>
              <td><?php echo esc_html( $purpose_label ); ?></td>
              <td><?php echo esc_html( $completed_date ); ?></td>
              <td><?php echo esc_html( $score_pct ); ?></td>
              <td><?php echo $result_label; ?></td>
              <td style="white-space:nowrap">
                <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $detail_url ); ?>"
                   style="padding:4px 10px;font-size:12px;">
                  🔍 Détail
                </a>
                <?php if ( $has_pdf ) : ?>
                  <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( (string) $p->result_document_url ); ?>"
                     download target="_blank"
                     style="padding:4px 10px;font-size:12px;margin-left:4px;">
                    📄 PDF
                  </a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
    <?php
  }

    /**
   * Vue détaillée d'un résultat de quiz pour l'apprenant.
   * Lecture seule — question par question, sans formulaire de correction.
   *
   * @param object $account
   * @param int    $participant_id
   */
  private function render_learner_portal_quiz_detail( $account, $participant_id ) {
    $p = $this->get_learner_quiz_participant( $participant_id, $account->email );
    if ( ! $p ) {
      ?>
      <div class="acdc-panel">
        <p>Résultat introuvable ou accès non autorisé.</p>
        <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->learner_portal_page_url( 'mes_quiz' ) ); ?>">
          ← Retour à mes quiz
        </a>
      </div>
      <?php
      return;
    }

    $questions = $this->get_qz_questions_for_quiz( (int) $p->quiz_id );
    $answers   = $this->get_qz_answers_by_participant( $participant_id );

    $purpose_labels = array(
      'live'        => 'Quiz live',
      'positioning' => 'Test de positionnement',
      'diagnostic'  => 'Évaluation diagnostique',
      'assessment'  => 'Évaluation des acquis',
    );
    $purpose_label  = $purpose_labels[ $p->quiz_purpose ] ?? ucfirst( (string) $p->quiz_purpose );
    $score_pct      = null !== $p->total_score_percentage
      ? number_format( (float) $p->total_score_percentage, 1, ',', ' ' ) . ' %'
      : null;
    $has_pdf        = ! empty( $p->result_document_url );
    $back_url       = $this->learner_portal_page_url( 'mes_quiz' );

    // Statut résultat
    if ( null !== $p->is_passed ) {
      $result_html = (int) $p->is_passed === 1
        ? '<span class="acdc-qz-result-badge acdc-qz-result-badge-correct">✓ Réussi</span>'
        : '<span class="acdc-qz-result-badge acdc-qz-result-badge-wrong">✗ Non réussi</span>';
    } else {
      $result_html = '';
    }
    ?>

    <!-- Retour -->
    <div class="acdc-qz-results-back-bar">
      <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $back_url ); ?>">
        ← Mes quiz
      </a>
    </div>

    <!-- Hero -->
    <header class="acdc-qz-results-hero">
      <p class="acdc-qz-results-hero-eyebrow"><?php echo esc_html( $purpose_label ); ?></p>
      <h1 class="acdc-qz-results-hero-title"><?php echo esc_html( (string) $p->quiz_title ); ?></h1>
      <p class="acdc-qz-results-hero-meta">
        <?php if ( $score_pct ) : ?>
          <strong>Score :</strong> <?php echo esc_html( $score_pct ); ?>
          <?php echo $result_html; ?>
          &nbsp;·&nbsp;
        <?php endif; ?>
        <?php if ( ! empty( $p->completed_at ) ) : ?>
          <strong>Complété le :</strong>
          <?php echo esc_html( $this->learner_portal_format_date( $p->completed_at, true ) ); ?>
        <?php endif; ?>
      </p>
      <?php if ( $has_pdf ) : ?>
        <div style="margin-top:12px;">
          <a class="acdc-button acdc-button-primary"
             href="<?php echo esc_url( (string) $p->result_document_url ); ?>"
             download target="_blank">
            📄 Télécharger mes résultats (PDF)
          </a>
        </div>
      <?php endif; ?>
    </header>

    <!-- Détail question par question -->
    <?php if ( empty( $questions ) ) : ?>
      <div class="acdc-results-section">
        <p style="padding:16px;">Les questions de ce quiz ne sont plus disponibles.</p>
      </div>
    <?php else : ?>
    <section class="acdc-qz-results-section">
      <?php echo $this->qz_section_lock_svg(); ?>
      <header class="acdc-qz-results-section-header">
        <h2>Vos réponses</h2>
        <p class="acdc-qz-results-section-subtitle">
          Retrouvez ici chacune de vos réponses et la correction associée.
        </p>
      </header>

      <ol class="acdc-qz-results-question-list">
        <?php foreach ( $questions as $idx => $q ) :
          $a              = $answers[ (int) $q->id ] ?? null;
          $is_open        = self::ACDC_OF_QZ_QTYPE_OPEN_TEXT === $q->type;
          $is_poll        = self::ACDC_OF_QZ_QTYPE_POLL === $q->type;
          $correct_answers = $this->get_qz_answers_for_question_public( (int) $q->id );
        ?>
          <li class="acdc-qz-result-question">
            <header class="acdc-qz-result-question-header">
              <span class="acdc-qz-result-question-num">Q<?php echo (int) $idx + 1; ?></span>
              <h3><?php echo esc_html( $q->title ); ?></h3>
              <?php if ( ! $is_poll && $a && null !== $a->is_correct ) : ?>
                <?php
                /* ACDC 3.25.168 — L'apprenant lisait « Faux » sur une réponse qu'il avait
                   en partie juste, alors que ses points, eux, étaient comptés au prorata.
                   Le badge dit maintenant la même chose que le score. */
                $lp_verdict = $this->qz_answer_verdict( $a->is_correct, $a->score_ratio ?? null );
                $lp_class = array(
                    'correct' => 'acdc-qz-result-badge-correct',
                    'partial' => 'acdc-qz-result-badge-partial',
                    'wrong'   => 'acdc-qz-result-badge-wrong',
                    'pending' => 'acdc-qz-result-badge-pending',
                );
                $lp_icon = array( 'correct' => '✓', 'partial' => '◐', 'wrong' => '✗', 'pending' => '⏳' );
                ?>
                <span class="acdc-qz-result-badge <?php echo esc_attr( $lp_class[ $lp_verdict['state'] ] ); ?>">
                  <?php echo esc_html( $lp_icon[ $lp_verdict['state'] ] . ' ' . $lp_verdict['label'] ); ?>
                </span>
              <?php elseif ( $is_open && $a ) : ?>
                <span class="acdc-qz-result-badge acdc-qz-result-badge-pending">⏳ En cours de correction</span>
              <?php endif; ?>
            </header>

            <?php if ( ! empty( $q->description ) ) : ?>
              <p class="acdc-qz-result-question-desc"><?php echo esc_html( (string) $q->description ); ?></p>
            <?php endif; ?>

            <?php if ( ! $a ) : ?>
              <p class="acdc-qz-result-no-answer"><em>Aucune réponse fournie.</em></p>

            <?php elseif ( $is_poll ) : ?>
              <!-- Sondage : afficher la réponse sans notion de correct/faux -->
              <div class="acdc-qz-result-open-answer">
                <?php
                $selected_ids = array();
                if ( ! empty( $a->answer_ids_json ) ) {
                  $dec = json_decode( (string) $a->answer_ids_json, true );
                  if ( is_array( $dec ) ) { $selected_ids = array_map( 'intval', $dec ); }
                }
                foreach ( $correct_answers as $ca ) :
                  if ( in_array( (int) $ca->id, $selected_ids, true ) ) : ?>
                    <p>— <?php echo esc_html( $ca->text ); ?></p>
                  <?php endif;
                endforeach; ?>
              </div>

            <?php elseif ( $is_open ) : ?>
              <!-- Réponse libre -->
              <div class="acdc-qz-result-open-answer">
                <p><strong>Votre réponse :</strong></p>
                <blockquote><?php echo nl2br( esc_html( (string) $a->answer_text ) ); ?></blockquote>
              </div>

            <?php else : ?>
              <!-- QCM, V/F, Puzzle -->
              <?php $this->render_qz_result_question_choices( $q, $a, $correct_answers ); ?>

            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
    <?php endif; ?>

    <!-- Bouton PDF en pied de page -->
    <?php if ( $has_pdf ) : ?>
      <div style="padding:0 0 24px;">
        <a class="acdc-button acdc-button-primary"
           href="<?php echo esc_url( (string) $p->result_document_url ); ?>"
           download target="_blank">
          📄 Télécharger mes résultats (PDF)
        </a>
      </div>
    <?php endif; ?>
    <?php
  }

  /**
   * ACDC 3.21.07 — Onglet "Mes signatures" du portail apprenant.
   * Affiche tous les documents en attente de signature ou déjà signés.
   */
  private function render_learner_portal_mes_signatures_tab( $account ) {
    $requests = $this->get_learner_pending_signatures( $account->email );
    $sig_page_url = class_exists( 'ACDC_Sig_Core' ) ? ( new ACDC_Sig_Core() )->get_signature_page_url() : '';

    $status_labels = array(
      'envoye'  => array( 'label' => 'En attente', 'color' => '#fff4d6', 'text' => '#8a6d2a' ),
      'ouvert'  => array( 'label' => 'Ouvert',     'color' => '#e9f2fa', 'text' => '#1e4777' ),
      'signe'   => array( 'label' => 'Signé ✓',    'color' => '#e8f5e9', 'text' => '#1B5E20' ),
      'refuse'  => array( 'label' => 'Refusé',     'color' => '#ffebee', 'text' => '#B71C1C' ),
      'expire'  => array( 'label' => 'Expiré',     'color' => '#f5f5f5', 'text' => '#757575' ),
    );
    $doc_type_labels = array(
      'convention'  => 'Convention de formation',
      'contrat'     => 'Contrat de formation',
      'emargement'  => "Feuille d'émargement",
    );
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Mes signatures</h2>
        <p class="acdc-section-subtitle">Documents en attente de votre signature électronique et historique.</p>
      </div>
    </section>

    <?php if ( ! empty( $requests ) ) : ?>
    <div class="acdc-results-section" style="margin-bottom:28px;">
      <?php echo $this->qz_section_lock_svg(); ?>
      <div class="acdc-results-eyebrow">DOCUMENTS</div>
      <div class="acdc-results-inner">
        <table class="acdc-results-table">
          <thead>
            <tr>
              <th>Type de document</th>
              <th>Statut</th>
              <th>Reçu le</th>
              <th>Expire le</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $requests as $req ) :
              $doc_label    = isset( $doc_type_labels[ $req->doc_type ] ) ? $doc_type_labels[ $req->doc_type ] : ucfirst( (string) $req->doc_type );
              $st           = isset( $status_labels[ $req->status ] ) ? $status_labels[ $req->status ] : array( 'label' => $req->status, 'color' => '#f5f5f5', 'text' => '#757575' );
              $received     = ! empty( $req->created_at ) ? $this->learner_portal_format_date( $req->created_at, true ) : '—';
              $expires      = ! empty( $req->expires_at ) ? $this->learner_portal_format_date( $req->expires_at, true ) : '—';
              $sign_url     = $sig_page_url ? add_query_arg( 'sig', rawurlencode( (string) $req->token ), $sig_page_url ) : '';
              $is_pending   = in_array( $req->status, array( 'envoye', 'ouvert' ), true );
              $is_signed    = 'signe' === $req->status;
            ?>
            <tr>
              <td><strong><?php echo esc_html( $doc_label ); ?></strong></td>
              <td>
                <span style="display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:<?php echo esc_attr( $st['color'] ); ?>;color:<?php echo esc_attr( $st['text'] ); ?>;">
                  <?php echo esc_html( $st['label'] ); ?>
                </span>
              </td>
              <td><?php echo esc_html( $received ); ?></td>
              <td><?php echo esc_html( $expires ); ?></td>
              <td>
                <?php if ( $is_pending && $sign_url ) : ?>
                  <a href="<?php echo esc_url( $sign_url ); ?>" class="acdc-button acdc-button-primary" style="height:32px;padding:0 14px;font-size:13px;display:inline-flex;align-items:center;border-radius:8px;" target="_blank">
                    ✍ Signer
                  </a>
                <?php elseif ( $is_signed && ! empty( $req->signed_doc_url ) ) : ?>
                  <a href="<?php echo esc_url( $req->signed_doc_url ); ?>" class="acdc-button acdc-button-soft" style="height:32px;padding:0 14px;font-size:13px;display:inline-flex;align-items:center;border-radius:8px;" target="_blank">
                    ↓ Télécharger
                  </a>
                <?php else : ?>
                  <span style="color:#8a9ab0;font-size:13px;">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php else : ?>
    <div class="acdc-panel">
      <p>Aucun document en attente de signature pour le moment.</p>
    </div>
    <?php endif; ?>
    <?php
  }

    private function render_learner_portal_formations_tab( $account, $items ) {
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Mes formations</h2>
        <p>Consultez les formations encore accessibles dans votre extranet.</p>
      </div>
    </section>
    <div class="acdc-grid-2cols acdc-learner-formation-grid">
      <?php if ( ! empty( $items ) ) : foreach ( $items as $item ) : ?>
        <?php
        $formation = ! empty( $item['formation'] ) ? $item['formation'] : null;
        $session   = ! empty( $item['session'] ) ? $item['session'] : null;
        $title     = $formation && ! empty( $formation->title ) ? $formation->title : ( ! empty( $item['registration']->formation_title ) ? $item['registration']->formation_title : 'Formation' );
        $doc_summary = $this->learner_portal_get_primary_document_summary( $account->email, (int) $item['registration']->id );
        ?>
        <div class="acdc-panel acdc-learner-formation-card">
          <h3><?php echo esc_html( $title ); ?></h3>
          <?php
          $date_debut = '';
          $date_fin   = '';
          if ( $session ) {
            $date_debut = ! empty( $session->start_at )   ? $this->learner_portal_format_date( $session->start_at )
                        : ( ! empty( $session->start_date ) ? $this->learner_portal_format_date( $session->start_date ) : '' );
            $date_fin   = ! empty( $session->end_at )     ? $this->learner_portal_format_date( $session->end_at )
                        : ( ! empty( $session->end_date )   ? $this->learner_portal_format_date( $session->end_date )   : '' );
          }
          ?>
          <?php if ( $date_debut || $date_fin ) : ?>
          <p><strong>Dates :</strong>
            <?php echo esc_html( $date_debut ?: '?' ); ?>
            <?php if ( $date_fin ) : ?> &rarr; <?php echo esc_html( $date_fin ); ?><?php endif; ?>
          </p>
          <?php endif; ?>
          <?php if ( ! empty( $item['trainer_label'] ) ) : ?>
          <p><strong>Formateur référent :</strong> <?php echo esc_html( $item['trainer_label'] ); ?></p>
          <?php endif; ?>
          <?php
          $next_seance_label = '';
          if ( $session ) {
            $next_ts = 0;
            if ( ! empty( $session->start_at ) )     { $next_ts = strtotime( $session->start_at ); }
            elseif ( ! empty( $session->start_date ) ) { $next_ts = strtotime( $session->start_date ); }
            if ( $next_ts > time() ) {
              $next_seance_label = ! empty( $session->start_at )
                ? $this->learner_portal_format_date( $session->start_at, true )
                : $this->learner_portal_format_date( $session->start_date );
            }
          }
          ?>
          <?php if ( $next_seance_label ) : ?>
          <p><strong>Prochaine séance :</strong> <?php echo esc_html( $next_seance_label ); ?></p>
          <?php endif; ?>
          <p><strong>Accès extranet jusqu'au :</strong> <?php echo esc_html( $this->learner_portal_format_date( $item['expires_at'], true ) ); ?></p>
          <ul class="acdc-learner-list-plain acdc-learner-list-compact">
            <?php foreach ( $doc_summary as $doc_line ) : ?>
              <li><small><?php echo esc_html( $doc_line ); ?></small></li>
            <?php endforeach; ?>
          </ul>
          <p><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $this->learner_portal_page_url( 'formation', array( 'registration_id' => (int) $item['registration']->id ) ) ); ?>">Voir la formation</a></p>
        </div>
      <?php endforeach; else : ?>
        <div class="acdc-panel"><p>Aucune formation accessible.</p></div>
      <?php endif; ?>
    </div>
    <?php
  }

  private function render_learner_portal_formation_tab( $account, $items ) {
    $registration_id = isset( $_GET['registration_id'] ) ? absint( wp_unslash( $_GET['registration_id'] ) ) : 0;
    if ( ! $registration_id && ! empty( $items[0]['registration']->id ) ) {
      $registration_id = (int) $items[0]['registration']->id;
    }
    $item = $this->learner_portal_get_access_item( $account->email, $registration_id );
    if ( ! $item ) {
      echo '<div class="acdc-panel"><p>Formation introuvable.</p></div>';
      return;
    }

    $formation = ! empty( $item['formation'] ) ? $item['formation'] : null;
    $session   = ! empty( $item['session'] ) ? $item['session'] : null;
    $title     = $formation && ! empty( $formation->title ) ? $formation->title : ( ! empty( $item['registration']->formation_title ) ? $item['registration']->formation_title : 'Formation' );
    $docs      = $this->learner_portal_build_document_groups( $account->email, $registration_id );
    $library_entries = array_values( array_filter( $this->learner_portal_get_library_entries( $account->email ), function( $entry ) use ( $title ) {
      return $entry['formation'] === $title;
    } ) );
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Ma formation</h2>
        <p><?php echo esc_html( $title ); ?></p>
      </div>
      <?php if ( count( $items ) > 1 ) : ?>
        <div>
          <select onchange="if(this.value){window.location=this.value;}">
            <?php foreach ( $items as $switch_item ) : ?>
              <?php $switch_title = ! empty( $switch_item['formation']->title ) ? $switch_item['formation']->title : ( ! empty( $switch_item['registration']->formation_title ) ? $switch_item['registration']->formation_title : 'Formation' ); ?>
              <option value="<?php echo esc_url( $this->learner_portal_page_url( 'formation', array( 'registration_id' => (int) $switch_item['registration']->id ) ) ); ?>" <?php selected( (int) $switch_item['registration']->id, $registration_id ); ?>><?php echo esc_html( $switch_title ); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
    </section>
    <div class="acdc-panel acdc-mb-18">
      <h3><?php echo esc_html( $title ); ?></h3>
      <p><?php echo esc_html( $formation && ! empty( $formation->description_text ) ? wp_strip_all_tags( $formation->description_text ) : 'Description non renseignée.' ); ?></p>
      <div class="acdc-grid-2cols">
        <div>
          <p><strong>Objectifs :</strong><br><?php echo esc_html( $formation && ! empty( $formation->objectives ) ? wp_strip_all_tags( $formation->objectives ) : 'Non renseignés.' ); ?></p>
          <p><strong>Durée :</strong> <?php echo esc_html( $formation && ! empty( $formation->duration ) ? $formation->duration : 'Non renseignée' ); ?></p>
          <?php
          $det_date_debut = '';
          $det_date_fin   = '';
          if ( $session ) {
            $det_date_debut = ! empty( $session->start_at )   ? $this->learner_portal_format_date( $session->start_at )
                            : ( ! empty( $session->start_date ) ? $this->learner_portal_format_date( $session->start_date ) : '' );
            $det_date_fin   = ! empty( $session->end_at )     ? $this->learner_portal_format_date( $session->end_at )
                            : ( ! empty( $session->end_date )   ? $this->learner_portal_format_date( $session->end_date )   : '' );
          }
          ?>
          <?php if ( $det_date_debut || $det_date_fin ) : ?>
          <p><strong>Dates :</strong>
            <?php echo esc_html( $det_date_debut ?: '?' ); ?>
            <?php if ( $det_date_fin ) : ?> &rarr; <?php echo esc_html( $det_date_fin ); ?><?php endif; ?>
          </p>
          <?php endif; ?>
          <?php if ( ! empty( $item['trainer_label'] ) ) : ?>
          <p><strong>Formateur référent :</strong> <?php echo esc_html( $item['trainer_label'] ); ?></p>
          <?php endif; ?>
        </div>
        <div>
          <p><strong>Informations pratiques :</strong></p>
          <p>Adresse : <?php echo esc_html( $formation && ! empty( $formation->formation_address ) ? $formation->formation_address : ( $session && ! empty( $session->location ) ? $session->location : 'Non renseignée' ) ); ?></p>
          <p>Code postal / Ville : <?php echo esc_html( trim( ( $formation && ! empty( $formation->formation_postal_code ) ? $formation->formation_postal_code : '' ) . ' ' . ( $formation && ! empty( $formation->formation_city ) ? $formation->formation_city : '' ) ) ?: 'Non renseignés' ); ?></p>
          <?php
          /* ACDC 3.25.175 — « Lien distanciel : Non renseigné » s'affichait sur une
             formation en PRÉSENTIEL, où ce lien n'a aucune raison d'exister : le champ
             faisait croire à un oubli de l'organisme. On ne montre la ligne que
             lorsqu'un lien existe, ou lorsque la formation comporte effectivement une
             part à distance. */
          $lp_modality = strtolower( (string) ( $formation->modality ?? '' ) );
          $lp_is_remote = ( '' !== $lp_modality && false === strpos( $lp_modality, 'présentiel' ) && false === strpos( $lp_modality, 'presentiel' ) );
          ?>
          <?php if ( ! empty( $session->remote_link ) ) : ?>
            <p>Lien distanciel : <a href="<?php echo esc_url( $session->remote_link ); ?>" target="_blank" rel="noopener">Ouvrir le lien</a></p>
          <?php elseif ( $lp_is_remote ) : ?>
            <p>Lien distanciel : Non renseigné</p>
          <?php endif; ?>
          <p>Entreprise liée : <?php echo esc_html( ! empty( $item['learner']->company_name ) ? $item['learner']->company_name : ( ! empty( $item['registration']->company_label ) ? $item['registration']->company_label : 'Non renseignée' ) ); ?></p>
        </div>
      </div>
    </div>

    <div class="acdc-grid-2cols">
      <div class="acdc-panel">
        <h3>Documents principaux</h3>
        <ul class="acdc-learner-list-plain">
          <?php foreach ( array( 'program', 'convocations', 'results', 'certificates' ) as $group_key ) : ?>
            <?php if ( empty( $docs[ $group_key ]['items'] ) ) { continue; } ?>
            <?php foreach ( $docs[ $group_key ]['items'] as $doc ) : ?>
              <li><?php echo esc_html( $doc['label'] ); ?> — <?php if ( ! empty( $doc['available'] ) ) : ?><a href="<?php echo esc_url( $doc['url'] ); ?>">Télécharger</a><?php else : ?><span><?php echo esc_html( $this->learner_portal_document_status_label( $doc ) ); ?></span><?php endif; ?></li>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="acdc-panel">
        <h3>Bibliothèque liée</h3>
        <?php if ( ! empty( $library_entries ) ) : ?>
          <ul class="acdc-learner-list-plain">
            <?php foreach ( $library_entries as $entry ) : ?>
              <li><?php echo esc_html( $entry['label'] ); ?> — <a href="<?php echo esc_url( $entry['url'] ); ?>" <?php echo ! empty( $entry['external'] ) ? 'target="_blank" rel="noopener"' : ''; ?>>Ouvrir</a></li>
            <?php endforeach; ?>
          </ul>
        <?php else : ?>
          <p>Aucune ressource liée à cette formation pour le moment.</p>
        <?php endif; ?>
      </div>
    </div>
    <?php
  }

  private function render_learner_portal_planning_tab( $account ) {
    $view              = isset( $_GET['view_mode'] ) ? sanitize_key( wp_unslash( $_GET['view_mode'] ) ) : 'list';
    $formation_filter  = isset( $_GET['formation_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['formation_filter'] ) ) : '';
    $sessions          = $this->learner_portal_get_upcoming_sessions( $account->email, 100 );

    $formation_options = array();
    foreach ( $sessions as $entry ) {
      $title = ! empty( $entry['formation']->title ) ? $entry['formation']->title : ( ! empty( $entry['session']->title ) ? $entry['session']->title : 'Formation' );
      $formation_options[ $title ] = $title;
    }
    if ( '' !== $formation_filter ) {
      $sessions = array_values( array_filter( $sessions, function( $entry ) use ( $formation_filter ) {
        $title = ! empty( $entry['formation']->title ) ? $entry['formation']->title : ( ! empty( $entry['session']->title ) ? $entry['session']->title : '' );
        return $title === $formation_filter;
      } ) );
    }

    $month_counts = $this->learner_portal_count_sessions_by_month( $sessions );
    $week_counts  = $this->learner_portal_count_sessions_by_week( $sessions );
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Mon planning</h2>
        <p>Vue mois, semaine et liste de vos séances à venir.</p>
      </div>
      <div class="acdc-inline-wrap">
        <a class="acdc-button <?php echo 'month' === $view ? 'acdc-button-primary' : 'acdc-button-soft'; ?>" href="<?php echo esc_url( $this->learner_portal_page_url( 'planning', array_filter( array( 'view_mode' => 'month', 'formation_filter' => $formation_filter ?: null ) ) ) ); ?>">Mois</a>
        <a class="acdc-button <?php echo 'week' === $view ? 'acdc-button-primary' : 'acdc-button-soft'; ?>" href="<?php echo esc_url( $this->learner_portal_page_url( 'planning', array_filter( array( 'view_mode' => 'week', 'formation_filter' => $formation_filter ?: null ) ) ) ); ?>">Semaine</a>
        <a class="acdc-button <?php echo 'list' === $view ? 'acdc-button-primary' : 'acdc-button-soft'; ?>" href="<?php echo esc_url( $this->learner_portal_page_url( 'planning', array_filter( array( 'view_mode' => 'list', 'formation_filter' => $formation_filter ?: null ) ) ) ); ?>">Liste</a>
      </div>
    </section>

    <div class="acdc-panel acdc-mb-18">
      <form method="get" class="acdc-inline-wrap acdc-inline-wrap-center">
        <input type="hidden" name="portal_tab" value="planning">
        <input type="hidden" name="view_mode" value="<?php echo esc_attr( $view ); ?>">
        <label for="acdc-planning-formation-filter"><strong>Formation :</strong></label>
        <select id="acdc-planning-formation-filter" name="formation_filter">
          <option value="">Toutes les formations</option>
          <?php foreach ( $formation_options as $option ) : ?>
            <option value="<?php echo esc_attr( $option ); ?>" <?php selected( $formation_filter, $option ); ?>><?php echo esc_html( $option ); ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="acdc-button acdc-button-soft">Filtrer</button>
      </form>
    </div>

    <?php if ( 'month' === $view ) : ?>
      <div class="acdc-grid-3cols acdc-learner-calendar-grid">
        <?php if ( ! empty( $month_counts ) ) : foreach ( $month_counts as $data ) : ?>
          <div class="acdc-panel acdc-learner-calendar-card">
            <h3><?php echo esc_html( $data['label'] ); ?></h3>
            <p><strong><?php echo esc_html( (string) $data['count'] ); ?></strong> séance(s) prévue(s)</p>
          </div>
        <?php endforeach; else : ?>
          <div class="acdc-panel"><p>Aucune séance à venir.</p></div>
        <?php endif; ?>
      </div>
    <?php elseif ( 'week' === $view ) : ?>
      <div class="acdc-grid-3cols acdc-learner-calendar-grid">
        <?php if ( ! empty( $week_counts ) ) : foreach ( $week_counts as $data ) : ?>
          <div class="acdc-panel acdc-learner-calendar-card">
            <h3><?php echo esc_html( $data['label'] ); ?></h3>
            <p><strong><?php echo esc_html( (string) $data['count'] ); ?></strong> séance(s) prévue(s)</p>
          </div>
        <?php endforeach; else : ?>
          <div class="acdc-panel"><p>Aucune séance à venir.</p></div>
        <?php endif; ?>
      </div>
    <?php else : ?>
      <div class="acdc-panel">
        <?php if ( ! empty( $sessions ) ) : ?>
          <table class="acdc-table">
            <thead><tr><th>Formation</th><th>Date</th><th>Début</th><th>Fin</th><th>Lieu / lien</th><th>Formateur</th><th>Commentaire</th></tr></thead>
            <tbody>
              <?php foreach ( $sessions as $entry ) : ?>
                <?php $session = $entry['session']; ?>
                <tr>
                  <td><?php echo esc_html( ! empty( $entry['formation']->title ) ? $entry['formation']->title : $session->title ); ?></td>
                  <td><?php echo esc_html( $this->learner_portal_format_date( ! empty( $session->start_at ) ? $session->start_at : $session->start_date ) ); ?></td>
                  <td><?php echo esc_html( ! empty( $session->start_at ) ? wp_date( 'H:i', strtotime( $session->start_at ) ) : '—' ); ?></td>
                  <td><?php echo esc_html( ! empty( $session->end_at ) ? wp_date( 'H:i', strtotime( $session->end_at ) ) : '—' ); ?></td>
                  <td>
                    <?php if ( ! empty( $session->remote_link ) ) : ?>
                      <a href="<?php echo esc_url( $session->remote_link ); ?>" target="_blank" rel="noopener">Accès distanciel</a>
                    <?php else : ?>
                      <?php echo esc_html( ! empty( $session->location ) ? $session->location : 'Non renseigné' ); ?>
                    <?php endif; ?>
                  </td>
                  <td><?php echo esc_html( ! empty( $entry['trainer'] ) ? $entry['trainer'] : 'Non renseigné' ); ?></td>
                  <td><?php echo esc_html( ! empty( $session->notes ) ? wp_trim_words( wp_strip_all_tags( $session->notes ), 14 ) : '—' ); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else : ?>
          <p>Aucune séance à venir.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php
  }

  private function render_learner_portal_documents_tab( $account ) {
    $groups = $this->learner_portal_build_document_groups( $account->email );
    $counts = $this->learner_portal_get_document_count_by_group( $groups );
    $active_group = isset( $_GET['doc_tab'] ) ? sanitize_key( wp_unslash( $_GET['doc_tab'] ) ) : 'program';
    $registration_id = isset( $_GET['registration_id'] ) ? absint( wp_unslash( $_GET['registration_id'] ) ) : 0;
    if ( empty( $groups[ $active_group ] ) ) {
      $active_group = 'program';
    }
    $items = ! empty( $groups[ $active_group ]['items'] ) ? $groups[ $active_group ]['items'] : array();
    if ( $registration_id ) {
      $items = array_values( array_filter( $items, function( $item ) use ( $registration_id ) {
        return ! empty( $item['registration_id'] ) && (int) $item['registration_id'] === $registration_id;
      } ) );
    }
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Mes documents</h2>
        <p>Consultez et téléchargez vos documents classés par type.</p>
      </div>
    </section>
    <?php
    /* ACDC 3.25.207 — Dire la règle plutôt que la laisser deviner. Un apprenant
       qui voit « à la fin de la formation » sans explication croit à une panne ;
       il écrit à l'organisme, et l'organisme lui répond ce que cette phrase
       aurait suffi à dire. */
    $has_locked = false;
    foreach ( $groups as $group ) {
      foreach ( (array) ( $group['items'] ?? array() ) as $doc ) {
        if ( ! empty( $doc['locked'] ) ) {
          $has_locked = true;
          break 2;
        }
      }
    }
    ?>
    <?php if ( $has_locked ) : ?>
      <div class="acdc-panel acdc-mb-18" style="border-left:4px solid #C5A253;">
        <p style="margin:0;">Vos documents officiels — programme, convocation, livret d’accueil, règlement intérieur — sont disponibles dès maintenant. Les supports de cours et les résultats d’évaluation s’ouvriront à la fin de votre formation, ou plus tôt si votre formateur les débloque.</p>
      </div>
    <?php endif; ?>
    <div class="acdc-inline-wrap acdc-mb-18">
      <?php foreach ( $groups as $group_key => $group ) : ?>
        <a class="acdc-button <?php echo $group_key === $active_group ? 'acdc-button-primary' : 'acdc-button-soft'; ?>" href="<?php echo esc_url( $this->learner_portal_page_url( 'documents', array_filter( array( 'doc_tab' => $group_key, 'registration_id' => $registration_id ?: null ) ) ) ); ?>"><?php echo esc_html( $group['label'] . ' (' . ( isset( $counts[ $group_key ] ) ? (int) $counts[ $group_key ] : 0 ) . ')' ); ?></a>
      <?php endforeach; ?>
    </div>
    <div class="acdc-panel">
      <?php if ( 'results' === $active_group ) : ?>
        <?php // ── Onglet Résultats : tableau enrichi avec Type / Formation / Nom / Statut / Date / Action ── ?>
        <table class="acdc-table">
          <thead>
            <tr>
              <th>Type</th>
              <th>Formation</th>
              <th>Nom</th>
              <th>Statut</th>
              <th>Date</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ( ! empty( $items ) ) : ?>
              <?php foreach ( $items as $doc ) :
                // Déduire le type depuis document_type et label
                $doc_type = $doc['document_type'] ?? '';
                if ( 'quiz_result' === $doc_type ) {
                  // Le label contient "Quiz live — Titre (date)"
                  if ( strpos( $doc['label'], 'Quiz live' ) !== false ) {
                    $type_label = 'Quiz live';
                    $type_badge = 'acdc-badge-live';
                  } elseif ( strpos( $doc['label'], 'Test de positionnement' ) !== false ) {
                    $type_label = 'Test de positionnement';
                    $type_badge = 'acdc-badge-positioning';
                  } else {
                    $type_label = 'Évaluation des acquis';
                    $type_badge = 'acdc-badge-assessment';
                  }
                  // Extraire le nom du quiz et la date depuis le label "Type — Nom (dd/mm/yyyy)"
                  $nom_raw   = preg_replace( '/^[^—]+— /', '', $doc['label'] );
                  $nom_clean = preg_replace( '/\s*\(\d{2}\/\d{2}\/\d{4}\)\s*$/', '', $nom_raw );
                  preg_match( '/\((\d{2}\/\d{2}\/\d{4})\)/', $doc['label'], $date_m );
                  $date_display = ! empty( $date_m[1] ) ? $date_m[1] : '—';
                } else {
                  // Documents standards (positionnement BDD, évaluation BDD)
                  if ( 'positioning_result' === $doc_type ) {
                    $type_label = 'Test de positionnement';
                    $type_badge = 'acdc-badge-positioning';
                  } elseif ( 'evaluation_result' === $doc_type ) {
                    $type_label = 'Évaluation des acquis';
                    $type_badge = 'acdc-badge-assessment';
                  } else {
                    $type_label = 'Résultat';
                    $type_badge = '';
                  }
                  $nom_clean    = $doc['label'];
                  $date_display = '—';
                }
                $statut = $this->learner_portal_document_status_label( $doc );
                $is_new = ! empty( $doc['available'] ) && ! empty( $doc_type )
                          && $this->learner_portal_is_document_new(
                              (int) $account->id,
                              (int) ( $doc['registration_id'] ?? 0 ),
                              $doc_type,
                              (int) ( $doc['doc_index'] ?? 0 )
                          );
              ?>
              <tr class="<?php echo empty( $doc['available'] ) ? 'acdc-learner-doc-unavailable' : ''; ?>">
                <td>
                  <span class="acdc-qz-purpose-badge acdc-qz-purpose-<?php echo esc_attr( $type_badge ); ?>">
                    <?php echo esc_html( $type_label ); ?>
                  </span>
                </td>
                <td><?php echo esc_html( $doc['formation'] ?? '—' ); ?></td>
                <td>
                  <?php echo esc_html( $nom_clean ); ?>
                  <?php if ( $is_new ) : ?>
                    <span class="acdc-learner-badge-new">Nouveau</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span style="color:<?php echo ! empty( $doc['available'] ) ? '#1B5E20' : '#6b7280'; ?>; font-weight:600">
                    <?php echo esc_html( $statut ); ?>
                  </span>
                </td>
                <td><?php echo esc_html( $date_display ); ?></td>
                <td>
                  <?php if ( ! empty( $doc['available'] ) && ! empty( $doc['url'] ) ) : ?>
                    <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $doc['url'] ); ?>" target="_blank"
                       style="padding:4px 12px;font-size:12px">
                      ⬇ Télécharger
                    </a>
                  <?php else : ?>
                    <span style="color:#9ca3af">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php else : ?>
              <tr><td colspan="6" style="text-align:center;color:#6b7280;padding:24px">
                Aucun résultat disponible pour le moment.
              </td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      <?php else : ?>
        <?php // ── Autres onglets : tableau standard inchangé ── ?>
        <table class="acdc-table">
          <thead><tr><th>Document</th><th>Formation</th><th>Statut</th><th>Action</th></tr></thead>
          <tbody>
            <?php if ( ! empty( $items ) ) : ?>
              <?php foreach ( $items as $doc ) : ?>
                <tr class="<?php echo empty( $doc['available'] ) ? 'acdc-learner-doc-unavailable' : ''; ?>">
                  <td><?php echo esc_html( $doc['label'] ); ?><?php if ( ! empty( $doc['available'] ) && ! empty( $doc['document_type'] ) && $this->learner_portal_is_document_new( (int) $account->id, (int) $doc['registration_id'], $doc['document_type'], isset( $doc['doc_index'] ) ? (int) $doc['doc_index'] : 0 ) ) : ?> <span class="acdc-learner-badge-new">Nouveau</span><?php endif; ?></td>
                  <td><?php echo esc_html( $doc['formation'] ); ?></td>
                  <td><?php echo esc_html( $this->learner_portal_document_status_label( $doc ) ); ?></td>
                  <td><?php if ( ! empty( $doc['available'] ) ) : ?><a href="<?php echo esc_url( $doc['url'] ); ?>" target="_blank" rel="noopener">📄 Ouvrir</a><?php else : ?><span>—</span><?php endif; ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else : ?>
              <tr><td colspan="4">Aucun document dans cet onglet pour la sélection courante.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <?php
  }

  private function render_learner_portal_library_tab( $account ) {
    $entries           = $this->learner_portal_get_library_entries( $account->email );
    $formation_filter  = isset( $_GET['library_formation'] ) ? sanitize_text_field( wp_unslash( $_GET['library_formation'] ) ) : '';
    $type_filter       = isset( $_GET['library_type'] ) ? sanitize_key( wp_unslash( $_GET['library_type'] ) ) : '';

    $formation_options = array();
    $type_options      = array();
    foreach ( $entries as $entry ) {
      $formation_options[ $entry['formation'] ] = $entry['formation'];
      $type_options[ $entry['type'] ] = $entry['type_label'];
    }

    if ( '' !== $formation_filter ) {
      $entries = array_values( array_filter( $entries, function( $entry ) use ( $formation_filter ) {
        return $entry['formation'] === $formation_filter;
      } ) );
    }
    if ( '' !== $type_filter ) {
      $entries = array_values( array_filter( $entries, function( $entry ) use ( $type_filter ) {
        return $entry['type'] === $type_filter;
      } ) );
    }

    $grouped = array();
    foreach ( $entries as $entry ) {
      if ( empty( $grouped[ $entry['formation'] ] ) ) {
        $grouped[ $entry['formation'] ] = array();
      }
      $grouped[ $entry['formation'] ][] = $entry;
    }
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Ma bibliothèque</h2>
        <p>Ressources pédagogiques classées par formation et filtrables par type.</p>
      </div>
    </section>

    <div class="acdc-panel acdc-mb-18">
      <form method="get" class="acdc-inline-wrap acdc-inline-wrap-center">
        <input type="hidden" name="portal_tab" value="library">
        <label for="acdc-library-formation-filter"><strong>Formation :</strong></label>
        <select id="acdc-library-formation-filter" name="library_formation">
          <option value="">Toutes les formations</option>
          <?php foreach ( $formation_options as $option ) : ?>
            <option value="<?php echo esc_attr( $option ); ?>" <?php selected( $formation_filter, $option ); ?>><?php echo esc_html( $option ); ?></option>
          <?php endforeach; ?>
        </select>
        <label for="acdc-library-type-filter"><strong>Type :</strong></label>
        <select id="acdc-library-type-filter" name="library_type">
          <option value="">Tous les types</option>
          <?php foreach ( $type_options as $option_key => $option_label ) : ?>
            <option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $type_filter, $option_key ); ?>><?php echo esc_html( $option_label ); ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="acdc-button acdc-button-soft">Filtrer</button>
      </form>
    </div>

    <?php if ( ! empty( $grouped ) ) : ?>
      <?php foreach ( $grouped as $formation_title => $formation_entries ) : ?>
        <div class="acdc-panel acdc-mb-18">
          <h3><?php echo esc_html( $formation_title ); ?></h3>
          <table class="acdc-table">
            <thead><tr><th>Ressource</th><th>Séance</th><th>Type</th><th>Statut</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach ( $formation_entries as $entry ) : ?>
                <?php $is_new = $this->learner_portal_is_resource_new( (int) $account->id, (int) $entry['registration_id'], $entry['resource_type'], (int) $entry['resource_index'] ); ?>
                <tr>
                  <td><?php echo esc_html( $entry['label'] ); ?><?php if ( $is_new ) : ?> <span class="acdc-learner-badge-new">Nouveau</span><?php endif; ?></td>
                  <td><?php echo esc_html( ! empty( $entry['session'] ) ? $entry['session'] : '—' ); ?></td>
                  <td><?php echo esc_html( $entry['type_label'] ); ?></td>
                  <td><?php echo $is_new ? 'Non consultée' : 'Déjà consultée'; ?></td>
                  <td><a href="<?php echo esc_url( $entry['url'] ); ?>">Ouvrir</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endforeach; ?>
    <?php else : ?>
      <div class="acdc-panel"><p>Aucune ressource partagée disponible pour la sélection courante.</p></div>
    <?php endif; ?>
    <?php
  }

  private function render_learner_portal_profile_tab( $account, $primary ) {
    $photo_url = ! empty( $account->photo_url ) ? $account->photo_url : '';
    $display_name = $primary ? $this->learner_portal_display_name( $primary ) : $account->email;
    $initials = $primary ? strtoupper( substr( $primary->first_name, 0, 1 ) . substr( $primary->usage_last_name, 0, 1 ) ) : strtoupper( substr( $account->email, 0, 2 ) );
    $items = $this->learner_portal_get_access_items_for_email( $account->email );
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Mon profil</h2>
        <p>Consultez vos informations de compte et mettez à jour votre photo ou votre mot de passe.</p>
      </div>
    </section>
    <div class="acdc-grid-2cols">
      <div class="acdc-panel">
        <h3>Informations du compte</h3>
        <div class="acdc-learner-profile-header">
          <?php if ( $photo_url ) : ?>
            <img src="<?php echo esc_url( $photo_url ); ?>" alt="<?php echo esc_attr( $display_name ); ?>" class="acdc-learner-avatar">
          <?php else : ?>
            <div class="acdc-learner-avatar acdc-learner-avatar-fallback"><?php echo esc_html( $initials ); ?></div>
          <?php endif; ?>
          <div>
            <strong><?php echo esc_html( $display_name ); ?></strong><br>
            <small>Statut : <?php echo esc_html( $this->learner_portal_status_label( $account->status ) ); ?></small>
          </div>
        </div>
        <div class="acdc-grid-2cols">
          <p><strong>Prénom :</strong><br><?php echo esc_html( $primary ? $primary->first_name : '—' ); ?></p>
          <p><strong>Nom :</strong><br><?php echo esc_html( $primary ? $primary->usage_last_name : '—' ); ?></p>
          <p><strong>E-mail :</strong><br><?php echo esc_html( $account->email ); ?></p>
          <p><strong>Téléphone :</strong><br><?php echo esc_html( $primary && ! empty( $primary->phone ) ? $primary->phone : '—' ); ?></p>
          <p><strong>Date de naissance :</strong><br><?php echo esc_html( $primary && ! empty( $primary->birth_date ) ? $this->learner_portal_format_date( $primary->birth_date ) : '—' ); ?></p>
          <p><strong>Entreprise liée :</strong><br><?php echo esc_html( $primary && ! empty( $primary->company_name ) ? $primary->company_name : '—' ); ?></p>
          <p><strong>Adresse :</strong><br><?php echo esc_html( $primary && ! empty( $primary->address ) ? $primary->address : '—' ); ?></p>
          <p><strong>Code postal :</strong><br><?php echo esc_html( $primary && ! empty( $primary->postal_code ) ? $primary->postal_code : '—' ); ?></p>
          <p><strong>Ville :</strong><br><?php echo esc_html( $primary && ! empty( $primary->city ) ? $primary->city : '—' ); ?></p>
          <p><strong>Dernière connexion :</strong><br><?php echo esc_html( ! empty( $account->last_login_at ) ? $this->learner_portal_format_date( $account->last_login_at, true ) : '—' ); ?></p>
          <p><strong>Accès jusqu’au :</strong><br><?php echo esc_html( $this->learner_portal_format_date( $account->access_expires_at, true ) ); ?></p>
        </div>
        <h3>Formation(s) associée(s)</h3>
        <?php if ( ! empty( $items ) ) : ?>
          <ul class="acdc-learner-list-plain">
            <?php foreach ( $items as $item ) : ?>
              <?php $formation_title = ! empty( $item['formation']->title ) ? $item['formation']->title : ( ! empty( $item['registration']->formation_title ) ? $item['registration']->formation_title : 'Formation' ); ?>
              <li><?php echo esc_html( $formation_title ); ?> — <small>Accès jusqu’au <?php echo esc_html( $this->learner_portal_format_date( $item['expires_at'], true ) ); ?></small></li>
            <?php endforeach; ?>
          </ul>
        <?php else : ?>
          <p>Aucune formation associée.</p>
        <?php endif; ?>
      </div>
      <div class="acdc-panel">
        <h3>Photo de profil</h3>
        <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
          <?php wp_nonce_field( 'acdc_learner_update_profile' ); ?>
          <input type="hidden" name="action" value="acdc_learner_update_profile">
          <input type="hidden" name="existing_photo_url" value="<?php echo esc_attr( $photo_url ); ?>">
          <p><label>Photo / avatar</label><input type="file" name="portal_user_photo" accept="image/*"></p>
          <p><button type="submit" class="acdc-button acdc-button-primary">Modifier ma photo</button></p>
        </form>
        <hr>
        <h3 id="acdc-learner-password-panel">Mot de passe</h3>
        <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <?php wp_nonce_field( 'acdc_learner_change_password' ); ?>
          <input type="hidden" name="action" value="acdc_learner_change_password">
          <p><label>Nouveau mot de passe</label><input type="password" name="password_1" minlength="8" required></p>
          <p><label>Confirmer le mot de passe</label><input type="password" name="password_2" minlength="8" required></p>
          <p><button type="submit" class="acdc-button acdc-button-primary">Modifier mon mot de passe</button></p>
        </form>
      </div>
    </div>
    <?php
  }

  public function render_admin_learner_portal_page() {
    $search          = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
    $status_filter   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
    $detail_account  = isset( $_GET['account_id'] ) ? absint( wp_unslash( $_GET['account_id'] ) ) : 0;
    $accounts        = $this->learner_portal_get_accounts( array( 'search' => $search, 'status' => $status_filter ) );
    $summary         = get_option( 'acdc_of_learner_portal_last_sync_summary', array() );
    $overview        = $this->learner_portal_get_account_overview_stats();
    $detail          = $detail_account ? $this->learner_portal_get_account( $detail_account ) : null;
    $status_options  = array(
      ''                   => 'Tous les statuts',
      'never_activated'    => 'Jamais activé',
      'active'             => 'Actif',
      'password_to_change' => 'Mot de passe à changer',
      'blocked'            => 'Bloqué temporairement',
      'expired'            => 'Expiré',
      'disabled'           => 'Désactivé manuellement',
    );
    ?>
    <div class="wrap acdc-admin-wrap">
      <div class="acdc-portal-shell acdc-portal-shell-extranet acdc-admin-mode">
        <div class="acdc-portal-content acdc-portal-content-extranet">
          <?php $this->render_front_notice(); ?>
          <section class="acdc-section-head">
            <div>
              <h2>Pilotage accès extranet apprenants</h2>
              <p>Suivi des comptes apprenants, statuts d’accès, expirations, formations liées et historiques sensibles.</p>
            </div>
            <div class="acdc-inline-wrap">
              <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'acdc_admin_learner_portal_sync' ); ?>
                <input type="hidden" name="action" value="acdc_admin_learner_portal_sync">
                <button type="submit" class="acdc-button acdc-button-primary">Synchroniser les accès</button>
              </form>
            </div>
          </section>

          <div class="acdc-cards-grid acdc-cards-grid-dashboard acdc-cards-grid-workflow acdc-mb-18">
            <?php $this->render_workflow_card( 'Comptes synchronisés', isset( $summary['updated'] ) ? (int) $summary['updated'] : 0, '#' ); ?>
            <?php $this->render_workflow_card( 'Nouveaux comptes', isset( $summary['created'] ) ? (int) $summary['created'] : 0, '#' ); ?>
            <?php $this->render_workflow_card( 'Comptes expirés', isset( $summary['expired'] ) ? (int) $summary['expired'] : 0, '#' ); ?>
            <?php $this->render_workflow_card( 'Actifs', isset( $overview['active'] ) ? (int) $overview['active'] : 0, '#' ); ?>
            <?php $this->render_workflow_card( 'Jamais activés', isset( $overview['never_activated'] ) ? (int) $overview['never_activated'] : 0, '#' ); ?>
            <?php $this->render_workflow_card( 'Bloqués', isset( $overview['blocked'] ) ? (int) $overview['blocked'] : 0, '#' ); ?>
          </div>

          <div class="acdc-panel acdc-mb-18">
            <form method="get" action="">
              <input type="hidden" name="page" value="acdc-of-learner-portal">
              <div class="acdc-inline-wrap acdc-inline-wrap-center">
                <input type="search" name="q" value="<?php echo esc_attr( $search ); ?>" placeholder="Rechercher un apprenant, un e-mail, une entreprise">
                <select name="status">
                  <?php foreach ( $status_options as $status_key => $status_label ) : ?>
                    <option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $status_filter, $status_key ); ?>><?php echo esc_html( $status_label ); ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="acdc-button acdc-button-soft">Filtrer</button>
                <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( admin_url( 'admin.php?page=acdc-of-learner-portal' ) ); ?>">Réinitialiser</a>
              </div>
            </form>
            <div class="acdc-inline-wrap acdc-mt-10">
              <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( admin_url( 'admin.php?page=acdc-of-learner-portal&status=expired' ) ); ?>">Voir les expirés</a>
              <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( admin_url( 'admin.php?page=acdc-of-learner-portal&status=blocked' ) ); ?>">Voir les bloqués</a>
              <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( admin_url( 'admin.php?page=acdc-of-learner-portal&status=never_activated' ) ); ?>">Voir les jamais activés</a>
            </div>
          </div>

          <?php if ( $detail ) : ?>
            <?php
            $detail_items = $this->learner_portal_get_access_items_for_email( $detail->email );
            $detail_logs  = $this->learner_portal_get_account_logs( $detail->id, 25 );
            $detail_name  = trim( (string) ( $detail->first_name ?: '' ) . ' ' . (string) ( $detail->usage_last_name ?: '' ) );
            if ( '' === $detail_name ) {
              $detail_name = $detail->email;
            }
            ?>
            <div class="acdc-panel acdc-mb-18">
              <div class="acdc-section-head">
                <div>
                  <h3>Détail du compte apprenant</h3>
                  <p><?php echo esc_html( $detail_name ); ?> — <?php echo esc_html( $detail->email ); ?></p>
                </div>
                <div class="acdc-inline-wrap">
                  <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( admin_url( 'admin.php?page=acdc-of-learner-portal' ) ); ?>">Fermer le détail</a>
                </div>
              </div>

              <div class="acdc-cards-grid acdc-cards-grid-dashboard acdc-mb-18">
                <?php $this->render_workflow_card( 'Statut', $this->learner_portal_status_label( $detail->status ), '#' ); ?>
                <?php $this->render_workflow_card( 'Dernière connexion', ! empty( $detail->last_login_at ) ? $this->learner_portal_format_date( $detail->last_login_at, true ) : 'Jamais', '#' ); ?>
                <?php $this->render_workflow_card( 'Expiration', $this->learner_portal_format_date( $detail->access_expires_at, true ), '#' ); ?>
                <?php $this->render_workflow_card( 'Échecs récents', absint( $detail->failed_login_count ), '#' ); ?>
              </div>

              <div class="acdc-table-wrap acdc-mb-18">
                <table class="acdc-table">
                  <tbody>
                    <tr><th>Entreprise liée</th><td><?php echo esc_html( ! empty( $detail->company_name ) ? $detail->company_name : '—' ); ?></td><th>Ouverture</th><td><?php echo esc_html( $this->learner_portal_format_date( $detail->access_opened_at, true ) ); ?></td></tr>
                    <tr><th>Première activation</th><td><?php echo esc_html( ! empty( $detail->first_activated_at ) ? $this->learner_portal_format_date( $detail->first_activated_at, true ) : 'Jamais' ); ?></td><th>Blocage jusqu’au</th><td><?php echo esc_html( ! empty( $detail->blocked_until ) ? $this->learner_portal_format_date( $detail->blocked_until, true ) : '—' ); ?></td></tr>
                  </tbody>
                </table>
              </div>

              <div class="acdc-inline-wrap acdc-mb-18">
                <?php foreach ( array( 'enable' => 'Activer', 'disable' => 'Désactiver', 'prolong' => 'Prolonger 30j', 'reset' => 'Envoyer réinitialisation', 'send_activation' => 'Renvoyer ouverture' ) as $action_key => $label ) : ?>
                  <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_admin_learner_portal_status&account_id=' . (int) $detail->id . '&do=' . $action_key ), 'acdc_admin_learner_portal_status_' . (int) $detail->id . '_' . $action_key ) ); ?>"><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
              </div>

              <h4>Formations accessibles</h4>
              <div class="acdc-table-wrap acdc-mb-18">
                <table class="acdc-table">
                  <thead>
                    <tr>
                      <th>Formation</th>
                      <th>Session</th>
                      <th>Formateur référent</th>
                      <th>Date de fin</th>
                      <th>Expiration d’accès</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ( ! empty( $detail_items ) ) : ?>
                      <?php foreach ( $detail_items as $detail_item ) : ?>
                        <?php
                        $detail_session   = ! empty( $detail_item['session'] ) ? $detail_item['session'] : null;
                        $detail_formation = ! empty( $detail_item['formation'] ) ? $detail_item['formation'] : null;
                        $detail_end       = $detail_session && ! empty( $detail_session->end_date ) ? $detail_session->end_date : '';
                        ?>
                        <tr>
                          <td><?php echo esc_html( $detail_formation && ! empty( $detail_formation->title ) ? $detail_formation->title : '—' ); ?></td>
                          <td><?php echo esc_html( $detail_session && ! empty( $detail_session->title ) ? $detail_session->title : '—' ); ?></td>
                          <td><?php echo esc_html( ! empty( $detail_item['trainer_label'] ) ? $detail_item['trainer_label'] : '—' ); ?></td>
                          <td><?php echo esc_html( $detail_end ? $this->learner_portal_format_date( $detail_end ) : '—' ); ?></td>
                          <td><?php echo esc_html( ! empty( $detail_item['expires_at'] ) ? $this->learner_portal_format_date( $detail_item['expires_at'], true ) : '—' ); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else : ?>
                      <tr><td colspan="5">Aucune formation accessible trouvée pour ce compte.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>

              <h4>Historique des accès</h4>
              <div class="acdc-table-wrap">
                <table class="acdc-table">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Événement</th>
                      <th>IP</th>
                      <th>Navigateur</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ( ! empty( $detail_logs ) ) : ?>
                      <?php foreach ( $detail_logs as $log ) : ?>
                        <tr>
                          <td><?php echo esc_html( $this->learner_portal_format_date( $log->created_at, true ) ); ?></td>
                          <td><?php echo esc_html( $log->event_type ); ?></td>
                          <td><?php echo esc_html( ! empty( $log->ip_address ) ? $log->ip_address : '—' ); ?></td>
                          <td><?php echo esc_html( ! empty( $log->user_agent ) ? $log->user_agent : '—' ); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else : ?>
                      <tr><td colspan="4">Aucun historique enregistré pour ce compte.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php endif; ?>

          <div class="acdc-panel">
            <div class="acdc-table-wrap">
              <table class="acdc-table">
                <thead>
                  <tr>
                    <th>Prénom et nom</th>
                    <th>E-mail</th>
                    <th>Entreprise liée</th>
                    <th>Formation(s) accessible(s)</th>
                    <th>Statut</th>
                    <th>Ouverture</th>
                    <th>Expiration</th>
                    <th>Dernière connexion</th>
                    <th>Échecs récents</th>
                    <th>Accès actif</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ( ! empty( $accounts ) ) : foreach ( $accounts as $account ) : ?>
                    <?php
                    $actions = array(
                      'enable'          => 'Activer',
                      'disable'         => 'Désactiver',
                      'prolong'         => 'Prolonger 30j',
                      'reset'           => 'Réinitialiser',
                      'send_activation' => 'Renvoyer ouverture',
                    );
                    $account_items   = $this->learner_portal_get_access_items_for_email( $account->email );
                    $formation_count = is_array( $account_items ) ? count( $account_items ) : 0;
                    $account_name    = trim( (string) ( $account->first_name ?: '' ) . ' ' . (string) ( $account->usage_last_name ?: '' ) );
                    if ( '' === $account_name ) {
                      $account_name = '—';
                    }
                    ?>
                    <tr>
                      <td><?php echo esc_html( $account_name ); ?></td>
                      <td><?php echo esc_html( $account->email ); ?></td>
                      <td><?php echo esc_html( ! empty( $account->company_name ) ? $account->company_name : '—' ); ?></td>
                      <td><?php echo esc_html( $formation_count > 0 ? $formation_count : 0 ); ?></td>
                      <td><?php echo esc_html( $this->learner_portal_status_label( $account->status ) ); ?></td>
                      <td><?php echo esc_html( $this->learner_portal_format_date( $account->access_opened_at, true ) ); ?></td>
                      <td><?php echo esc_html( $this->learner_portal_format_date( $account->access_expires_at, true ) ); ?></td>
                      <td><?php echo esc_html( ! empty( $account->last_login_at ) ? $this->learner_portal_format_date( $account->last_login_at, true ) : 'Jamais' ); ?></td>
                      <td><?php echo esc_html( absint( $account->failed_login_count ) ); ?></td>
                      <td><?php echo esc_html( in_array( $account->status, array( 'active', 'password_to_change', 'blocked' ), true ) ? 'Oui' : 'Non' ); ?></td>
                      <td>
                        <div class="acdc-learner-admin-actions">
                          <a href="<?php echo esc_url( admin_url( 'admin.php?page=acdc-of-learner-portal&account_id=' . (int) $account->id ) ); ?>">Voir le détail</a>
                          <?php foreach ( $actions as $action_key => $label ) : ?>
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_admin_learner_portal_status&account_id=' . (int) $account->id . '&do=' . $action_key ), 'acdc_admin_learner_portal_status_' . (int) $account->id . '_' . $action_key ) ); ?>"><?php echo esc_html( $label ); ?></a>
                          <?php endforeach; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; else : ?>
                    <tr><td colspan="11">Aucun compte extranet apprenant détecté.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php
  }


  /**
   * Carte KPI utilisée dans les dashboards portal apprenant et formateur.
   * Signature : render_workflow_card( $label, $value, $url, $highlight = false )
   *
   * @param string      $label     Libellé de la carte.
   * @param string|int  $value     Valeur affichée (chiffre ou texte court).
   * @param string      $url       URL de la carte (lien).
   * @param bool        $highlight Vrai si la carte doit attirer l'attention (classe --alert).
   *
   * @since 3.21.36 — Méthode manquante restaurée.
   */
  private function render_workflow_card( $label, $value, $url = '#', $highlight = false ) {
    $label    = (string) $label;
    $value    = (string) $value;
    $url      = ( $url && '#' !== $url ) ? esc_url( $url ) : '#';
    $extra_class = $highlight ? ' acdc-workflow-card--alert' : '';
    echo '<a href="' . $url . '" class="acdc-workflow-card' . esc_attr( $extra_class ) . '">'
      . '<span class="acdc-workflow-value">' . esc_html( $value ) . '</span>'
      . '<span class="acdc-workflow-label">' . esc_html( $label ) . '</span>'
      . '</a>';
  }

}
