<?php
/**
 * ACDC 3.20.83 — Trait Render du portail Formateur.
 * Visuel calqué sur l'extranet apprenant existant (mêmes classes CSS, même structure).
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

trait ACDC_Trainer_Portal_Render_Trait {

  /**
   * Shortcode principal : affiche soit le formulaire de connexion (et ses variantes),
   * soit le dashboard si le formateur est authentifié.
   * Utilisable en posant [acdc_trainer_portal_login] dans une page WordPress.
   */
  public function render_trainer_portal_login_shortcode() {
    $view  = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
    $token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

    // Si déjà authentifié : on entre dans le portail (dashboard ou autre vue interne).
    $account = $this->trainer_portal_get_current_account();
    if ( $account ) {
      $internal_views = array( '', 'dashboard', 'library', 'profile', 'availability', 'sessions', 'contracts' );

      /* ACDC 3.21.03.2-a — Filtre d'extensibilité du dispatcher du portail formateur.
         Permet à des modules tiers (ex. quiz) de déclarer des vues internes additionnelles.
         Le filtre reçoit le tableau des vues connues et doit retourner le tableau enrichi.
         Strict parallèle au filtre 'acdc_portal_internal_views' utilisé dans l'extranet apprenant. */
      $internal_views = apply_filters( 'acdc_trainer_portal_internal_views', $internal_views, $account );

      $active = in_array( $view, $internal_views, true ) ? ( '' === $view ? 'dashboard' : $view ) : 'dashboard';
      switch ( $active ) {
        case 'library':
          $body = $this->render_trainer_portal_library( $account );
          break;
        case 'contracts':
          $body = $this->render_trainer_portal_contracts( $account );
          break;
        case 'profile':
          $body = $this->render_trainer_portal_profile( $account );
          break;
        case 'availability':
          $body = $this->render_trainer_portal_availability( $account );
          break;
        case 'sessions':
          /* ACDC 3.20.92 — Mes sessions. Le rendu gère permissions view_own_sessions
             et view_session_learners en interne (arbitrage 1b : page bloquée avec message). */
          $body = $this->render_trainer_portal_sessions( $account );
          break;
        case 'dashboard':
          $body = $this->render_trainer_portal_dashboard_body( $account );
          break;
        default:
          /* ACDC 3.21.03.2-a — Filtre d'extensibilité.
             Si la vue n'est pas dans le switch, on délègue à un filtre que les modules tiers
             peuvent intercepter. Si aucun module ne répond (filtre retourne null), on retombe
             sur le dashboard. Convention : le module qui répond retourne le HTML du body. */
          $body = apply_filters( 'acdc_trainer_portal_render_unknown_view', null, $active, $account );
          if ( null === $body || '' === $body ) {
            $body = $this->render_trainer_portal_dashboard_body( $account );
            $active = 'dashboard';
          }
          break;
      }
      return $this->render_trainer_portal_authenticated_layout( $account, $body, $active );
    }

    // Vues anonymes (login / forgot / reset / activate).
    ob_start();
    ?>
    <div class="acdc-portal-shell acdc-login-shell acdc-trainer-login-shell">
      <div class="acdc-login-card acdc-trainer-login-card">
        <div class="acdc-login-branding">
          <img src="<?php echo esc_url( $this->get_plugin_logo_url() ); ?>" alt="ACDC Formation" class="acdc-login-logo">
          <h1>Espace formateur</h1>
          <p>Accès sécurisé à votre espace personnel, vos sessions et vos justificatifs.</p>
        </div>

        <?php $this->trainer_portal_render_notice(); ?>

        <?php if ( 'forgot' === $view ) : ?>
          <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'acdc_trainer_request_reset' ); ?>
            <input type="hidden" name="action" value="acdc_trainer_request_reset">
            <p><label for="acdc_trainer_forgot_email">Votre e-mail</label><input type="email" id="acdc_trainer_forgot_email" name="trainer_email" required></p>
            <p><button type="submit" class="acdc-button acdc-button-primary acdc-button-block">Envoyer le lien de réinitialisation</button></p>
          </form>
          <p class="acdc-login-secondary-link"><a href="<?php echo esc_url( $this->trainer_portal_login_url() ); ?>">Retour à la connexion</a></p>

        <?php elseif ( 'reset' === $view ) : ?>
          <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'acdc_trainer_reset_password' ); ?>
            <input type="hidden" name="action" value="acdc_trainer_reset_password">
            <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
            <p><label for="acdc_trainer_reset_1">Nouveau mot de passe</label><input type="password" id="acdc_trainer_reset_1" name="password_1" required minlength="8"></p>
            <p><label for="acdc_trainer_reset_2">Confirmer le mot de passe</label><input type="password" id="acdc_trainer_reset_2" name="password_2" required minlength="8"></p>
            <p><button type="submit" class="acdc-button acdc-button-primary acdc-button-block">Réinitialiser mon mot de passe</button></p>
          </form>

        <?php elseif ( 'activate' === $view ) : ?>
          <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'acdc_trainer_activate' ); ?>
            <input type="hidden" name="action" value="acdc_trainer_activate">
            <input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
            <p><label for="acdc_trainer_activate_1">Créer votre mot de passe</label><input type="password" id="acdc_trainer_activate_1" name="password_1" required minlength="8"></p>
            <p><label for="acdc_trainer_activate_2">Confirmer le mot de passe</label><input type="password" id="acdc_trainer_activate_2" name="password_2" required minlength="8"></p>
            <p><button type="submit" class="acdc-button acdc-button-primary acdc-button-block">Activer mon accès</button></p>
          </form>

        <?php else : /* login */ ?>
          <form class="acdc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'acdc_trainer_login' ); ?>
            <input type="hidden" name="action" value="acdc_trainer_login">
            <p><label for="acdc_trainer_email">E-mail</label><input type="email" id="acdc_trainer_email" name="trainer_email" required></p>
            <p><label for="acdc_trainer_password">Mot de passe</label><input type="password" id="acdc_trainer_password" name="trainer_password" required></p>
            <p><button type="submit" class="acdc-button acdc-button-primary acdc-button-block">Se connecter</button></p>
          </form>
          <p class="acdc-login-secondary-link"><a href="<?php echo esc_url( $this->trainer_portal_login_url( array( 'view' => 'forgot' ) ) ); ?>">Mot de passe oublié</a></p>
        <?php endif; ?>
      </div>
    </div>
    <?php
    return (string) ob_get_clean();
  }

  /**
   * ACDC 3.20.87 — Layout commun pour toutes les vues authentifiées :
   * topbar (logo + nom + déconnexion) + nav d'onglets + corps.
   *
   * @param object $account
   * @param string $body_html
   * @param string $active_tab
   * @return string
   */
  private function render_trainer_portal_authenticated_layout( $account, $body_html, $active_tab = 'dashboard' ) {
    $trainer = $this->get_trainer( (int) $account->trainer_id );
    $first   = $trainer ? (string) $trainer->first_name : '';
    $last    = $trainer ? (string) $trainer->last_name  : '';
    $display_name = trim( $first . ' ' . $last );
    if ( '' === $display_name ) {
      $display_name = isset( $account->email ) ? (string) $account->email : '';
    }
    $logout_url = wp_nonce_url(
      add_query_arg( array( 'action' => 'acdc_trainer_logout' ), admin_url( 'admin-post.php' ) ),
      'acdc_trainer_logout'
    );
    $tabs = array(
      'dashboard'    => array( 'label' => 'Tableau de bord',     'url' => $this->trainer_portal_page_url( 'dashboard' ) ),
      /* ACDC 3.20.92 — Onglet Mes sessions. Toujours visible (arbitrage 1b validé) ;
         la fonction de rendu fait elle-même le contrôle de permission view_own_sessions. */
      'sessions'     => array( 'label' => 'Mes sessions',        'url' => $this->trainer_portal_page_url( 'sessions' ) ),
      'library'      => array( 'label' => 'Ma bibliothèque',     'url' => $this->trainer_portal_page_url( 'library' ) ),
      'contracts'    => array( 'label' => 'Mes contrats',        'url' => $this->trainer_portal_page_url( 'contracts' ) ),
      'availability' => array( 'label' => 'Mes disponibilités',  'url' => $this->trainer_portal_page_url( 'availability' ) ),
      'profile'      => array( 'label' => 'Mon profil',          'url' => $this->trainer_portal_page_url( 'profile' ) ),
    );

    /* ACDC 3.21.03.2-a — Filtre d'extensibilité.
       Permet à des modules tiers (ex. quiz) d'ajouter leurs propres onglets à la sidebar
       formateur sans modifier ce fichier. Le module reçoit le tableau $tabs et l'objet $account
       du formateur courant ; il peut insérer/retirer/réordonner des entrées.
       Convention : chaque entrée est array( 'label' => string, 'url' => string ) indexée par
       une clé courte (slug), parallèle à 'dashboard', 'sessions', etc. */
    $tabs = apply_filters( 'acdc_trainer_portal_navigation_tabs', $tabs, $account );

    ob_start();
    ?>
    <?php /* ACDC 3.20.95 — Refonte du shell formateur en miroir structurel du shell apprenant.
             Adoption de la même grille acdc-portal-layout (sidebar verticale + zone centrale)
             et des mêmes patterns DOM (acdc-portal-nav, acdc-portal-content, acdc-extranet-topbar).
             Le CSS spécifique vit désormais dans assets/css/trainer-portal.css ;
             plus de <style> inline dans le rendu. */ ?>
    <div class="acdc-portal-shell acdc-trainer-portal-shell">
      <div class="acdc-portal-layout acdc-trainer-portal-layout">
        <aside class="acdc-portal-nav acdc-trainer-portal-nav">
          <?php $this->render_brand_block(); ?>
          <nav class="acdc-trainer-nav-list">
            <?php foreach ( $tabs as $key => $tab ) : ?>
              <?php $is_active = ( $active_tab === $key ) ? ' acdc-trainer-nav-item-active' : ''; ?>
              <a class="acdc-trainer-nav-item<?php echo esc_attr( $is_active ); ?>" href="<?php echo esc_url( $tab['url'] ); ?>"><?php echo esc_html( $tab['label'] ); ?></a>
            <?php endforeach; ?>
          </nav>
        </aside>
        <main class="acdc-portal-content acdc-trainer-portal-content">
          <div class="acdc-extranet-topbar acdc-trainer-topbar">
            <div>
              <strong><?php echo esc_html( '' !== $display_name ? $display_name : 'Formateur' ); ?></strong>
              <div class="acdc-login-secondary-link">Espace formateur</div>
            </div>
            <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $logout_url ); ?>">Se déconnecter</a>
          </div>

          <?php $this->trainer_portal_render_notice(); ?>
          <?php echo $body_html; // Déjà échappé en amont. ?>
        </main>
      </div>
    </div>
    <?php
    return (string) ob_get_clean();
  }

  /**
   * ACDC 3.20.96 — Corps du dashboard formateur enrichi : 4 cartes statistiques en haut,
   * bandeau Qualiopi (3.20.93) déplacé sous les cartes, puis deux rangées de 2 colonnes
   * (« Mes prochaines sessions » + « Mes derniers justificatifs Qualiopi »
   *  puis « Notifications » + « Accès rapide »).
   * Architecture miroir du dashboard apprenant pour cohérence visuelle complète.
   */
  private function render_trainer_portal_dashboard_body( $account ) {
    $trainer = $this->get_trainer( (int) $account->trainer_id );
    $first   = $trainer ? (string) $trainer->first_name : '';

    /* ACDC 3.20.93 — Bandeau « Statut Qualiopi » : récap des dates d'expiration proches.
       Conditionné à la permission view_qualiopi_status (active par défaut sur les deux profils
       Simple et Autonome depuis la 3.20.91). */
    $qualiopi_html = '';
    if ( $trainer && $this->trainer_can( (int) $trainer->id, 'view_qualiopi_status' ) ) {
      $upcoming = $this->get_acdc_qualiopi_upcoming_expirations( $trainer, 30 );
      if ( ! empty( $upcoming ) ) {
        $worst_days = PHP_INT_MAX;
        foreach ( $upcoming as $e ) { if ( $e['days'] < $worst_days ) { $worst_days = $e['days']; } }
        if ( $worst_days <= 0 )      { $bg = '#fdecec'; $border = '#c62828'; $title_color = '#c62828'; $title = 'Action requise — un de vos justificatifs est arrivé à expiration'; }
        elseif ( $worst_days <= 7 )  { $bg = '#fff3d6'; $border = '#a06b00'; $title_color = '#a06b00'; $title = 'Renouvellement urgent à prévoir cette semaine'; }
        else                         { $bg = '#fffae6'; $border = '#caa53a'; $title_color = '#a06b00'; $title = 'Pensez à renouveler vos justificatifs Qualiopi'; }
        ob_start();
        ?>
        <div class="acdc-panel" style="border:1px solid <?php echo esc_attr( $border ); ?>;border-left:4px solid <?php echo esc_attr( $border ); ?>;border-radius:10px;padding:18px 20px;margin-bottom:16px;background:<?php echo esc_attr( $bg ); ?>;">
          <strong style="display:block;color:<?php echo esc_attr( $title_color ); ?>;font-size:15px;"><?php echo esc_html( $title ); ?></strong>
          <ul style="margin:8px 0 0;padding-left:20px;color:#333;font-size:13px;line-height:1.65;">
            <?php foreach ( $upcoming as $exp ) :
              $exp_disp = mysql2date( 'd/m/Y', $exp['expires_at'] );
              $days_label = ( 0 === (int) $exp['days'] ) ? 'aujourd’hui' : ( $exp['days'] < 0 ? 'expirée le ' . $exp_disp : 'le ' . $exp_disp . ' (dans ' . (int) $exp['days'] . ' jour' . ( (int) $exp['days'] > 1 ? 's' : '' ) . ')' );
              $label_full = '' !== $exp['custom_label'] ? $exp['custom_label'] . ' (' . $exp['label'] . ')' : $exp['label'];
            ?>
              <li><strong><?php echo esc_html( $label_full ); ?></strong> — <?php echo esc_html( $days_label ); ?></li>
            <?php endforeach; ?>
          </ul>
          <p style="margin:12px 0 0;"><a href="<?php echo esc_url( $this->trainer_portal_page_url( 'library' ) ); ?>" style="color:<?php echo esc_attr( $title_color ); ?>;font-weight:600;font-size:13px;">Mettre à jour mes justificatifs →</a></p>
        </div>
        <?php
        $qualiopi_html = (string) ob_get_clean();
      }
    }

    /* ACDC 3.20.96 — Récupération des stats et listes pour les 4 cartes + 2 blocs. */
    $stats              = $trainer ? $this->trainer_portal_get_dashboard_stats( $trainer ) : array(
      'sessions_upcoming' => 0, 'qualiopi_valid' => 0, 'qualiopi_to_renew' => 0,
      'availability_halfdays' => 0, 'availability_total_halfdays' => 14, 'library_total' => 0,
    );
    $upcoming_sessions  = $trainer ? $this->trainer_portal_get_upcoming_sessions_list( (int) $trainer->id, 5 ) : array();
    $recent_qualiopi    = $trainer ? $this->trainer_portal_get_recent_qualiopi_docs( (int) $trainer->id, 5 ) : array();

    /* Valeur affichée dans la carte Qualiopi : si urgences, on affiche le compteur d'urgences,
       sinon on affiche le compteur de justificatifs valides. */
    $qualiopi_card_value = $stats['qualiopi_to_renew'] > 0
      ? (string) $stats['qualiopi_to_renew'] . ' à renouveler'
      : (string) $stats['qualiopi_valid'];

    /* Valeur affichée dans la carte Disponibilités : "X/14 demi-j." */
    $availability_card_value = $stats['availability_halfdays'] . '/' . $stats['availability_total_halfdays'] . ' demi-j.';

    ob_start();
    ?>
    <section class="acdc-section-head">
      <div>
        <h2>Tableau de bord</h2>
        <p>Vue synthétique de votre activité, vos justificatifs et vos disponibilités.</p>
      </div>
    </section>

    <?php /* ACDC 3.20.96 — 4 cartes statistiques (mêmes classes CSS que l'apprenant). */ ?>
    <div class="acdc-cards-grid acdc-cards-grid-dashboard acdc-cards-grid-workflow acdc-trainer-dashboard-grid">
      <?php $this->render_workflow_card( 'Mes sessions à venir', (string) $stats['sessions_upcoming'], $this->trainer_portal_page_url( 'sessions' ) ); ?>
      <?php $this->render_workflow_card( 'Justificatifs Qualiopi', $qualiopi_card_value, $this->trainer_portal_page_url( 'library' ) ); ?>
      <?php $this->render_workflow_card( 'Disponibilités', $availability_card_value, $this->trainer_portal_page_url( 'availability' ) ); ?>
      <?php $this->render_workflow_card( 'Bibliothèque', (string) $stats['library_total'], $this->trainer_portal_page_url( 'library' ) ); ?>
    </div>

    <?php /* Bandeau Qualiopi : visible uniquement s'il y a des urgences. Placé sous les cartes
             pour que la vue synthétique apparaisse en premier. */ ?>
    <?php if ( '' !== $qualiopi_html ) : ?>
      <div class="acdc-mt-24"><?php echo $qualiopi_html; ?></div>
    <?php endif; ?>

    <?php /* Première rangée de blocs 2 colonnes : sessions + justificatifs Qualiopi récents. */ ?>
    <div class="acdc-grid-2cols acdc-mt-24">
      <div class="acdc-panel">
        <h3>Mes prochaines sessions</h3>
        <?php if ( ! empty( $upcoming_sessions ) ) : ?>
          <ul class="acdc-trainer-list-plain">
            <?php foreach ( $upcoming_sessions as $s ) :
              $title = ! empty( $s->formation_title ) ? $s->formation_title : ( ! empty( $s->title ) ? $s->title : 'Session' );
              $ref   = ! empty( $s->start_at ) ? $s->start_at : ( ! empty( $s->start_date ) ? $s->start_date . ' 00:00:00' : '' );
              /* ACDC 3.25.188 — Une demi-journée est une séance à part entière.
                 Sans l'heure, un formateur voyait deux lignes strictement
                 identiques le 26, deux le 27, deux le 28, sans savoir laquelle
                 était le matin. L'heure ne s'affiche que si elle est renseignée :
                 une séance datée sans horaire ne doit pas prétendre commencer à
                 minuit. */
              $when  = $ref ? mysql2date( 'd/m/Y', $ref ) : '—';
              if ( ! empty( $s->start_at ) && '00:00:00' !== substr( (string) $s->start_at, 11 ) ) {
                $when .= ' — ' . mysql2date( 'H\hi', $s->start_at );
                if ( ! empty( $s->end_at ) ) {
                  $when .= ' à ' . mysql2date( 'H\hi', $s->end_at );
                }
              }
              $loc   = '';
              if ( ! empty( $s->location ) )         { $loc = (string) $s->location; }
              elseif ( ! empty( $s->remote_link ) )  { $loc = 'Distanciel'; }
            ?>
              <li>
                <strong><?php echo esc_html( $title ); ?></strong><br>
                <small><?php echo esc_html( $when ); ?><?php if ( '' !== $loc ) : ?> · <?php echo esc_html( $loc ); ?><?php endif; ?></small>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else : ?>
          <p style="margin:0;color:#5a6577;font-size:14px;">Aucune session à venir.</p>
        <?php endif; ?>
      </div>
      <div class="acdc-panel">
        <h3>Mes derniers justificatifs Qualiopi</h3>
        <?php if ( ! empty( $recent_qualiopi ) ) : ?>
          <ul class="acdc-trainer-list-plain">
            <?php foreach ( $recent_qualiopi as $doc ) :
              $doc_label = ! empty( $doc->label ) ? $doc->label : $doc->file_name;
              $added     = ! empty( $doc->created_at ) ? mysql2date( 'd/m/Y', $doc->created_at ) : '';
              $expires   = ( ! empty( $doc->expires_at ) && '0000-00-00' !== $doc->expires_at ) ? mysql2date( 'd/m/Y', $doc->expires_at ) : '';
            ?>
              <li>
                <strong><?php echo esc_html( $doc_label ); ?></strong><br>
                <small>
                  <?php if ( '' !== $added ) : ?>Ajouté le <?php echo esc_html( $added ); ?><?php endif; ?>
                  <?php if ( '' !== $expires ) : ?> · expire le <?php echo esc_html( $expires ); ?><?php endif; ?>
                </small>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else : ?>
          <p style="margin:0;color:#5a6577;font-size:14px;">Aucun justificatif Qualiopi pour le moment.</p>
        <?php endif; ?>
      </div>
    </div>

    <?php /* Seconde rangée de blocs 2 colonnes : notifications + accès rapide.
             Notifications : pour l'instant aucun mécanisme côté formateur, on affiche
             un message neutre — frère jumeau structurel de l'apprenant. */ ?>
    <div class="acdc-grid-2cols acdc-mt-24">
      <div class="acdc-panel">
        <h3>Notifications</h3>
        <p style="margin:0;color:#5a6577;font-size:14px;">Aucune notification pour le moment.</p>
      </div>
      <div class="acdc-panel">
        <h3>Accès rapide</h3>
        <div class="acdc-inline-wrap">
          <a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $this->trainer_portal_page_url( 'library' ) ); ?>">Mettre à jour mes justificatifs</a>
          <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $this->trainer_portal_page_url( 'profile' ) ); ?>">Modifier mon mot de passe</a>
        </div>
      </div>
    </div>
    <?php
    return (string) ob_get_clean();
  }

  /**
   * ACDC 3.20.87 — Vue « Ma bibliothèque » côté formateur.
   * Le formateur voit ses propres documents, peut en ajouter et en supprimer.
   * Les notes_admin et le flag Qualiopi (posé par l'admin) sont en lecture seule.
   */
  private function render_trainer_portal_library( $account ) {
    $trainer = $this->get_trainer( (int) $account->trainer_id );
    if ( ! $trainer ) {
      return '<div class="acdc-alert acdc-alert-error"><p>Profil formateur introuvable. Contactez l’administrateur.</p></div>';
    }
    $cats    = $this->get_acdc_trainer_document_categories();
    $grouped = $this->get_trainer_documents_grouped( (int) $trainer->id );

    ob_start();
    ?>
    <style>
      .acdc-tdoc-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:16px}
      @media(max-width:1280px){.acdc-tdoc-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
      @media(max-width:760px){.acdc-tdoc-grid{grid-template-columns:1fr}}
      .acdc-tdoc-card{background:#fff;border:1px solid #e6ebf2;border-radius:10px;padding:16px}
      .acdc-tdoc-card-head{display:flex;align-items:center;gap:10px;margin-bottom:10px;color:#1E4777}
      .acdc-tdoc-card-head h4{margin:0;font-size:14px;font-weight:600;flex:1 1 auto}
      .acdc-tdoc-count{font-size:12px;color:#5a6577;font-weight:500;background:#f7f9fc;border:1px solid #e6ebf2;border-radius:999px;padding:2px 9px}
      .acdc-tdoc-empty{color:#7d8898;font-size:13px;font-style:italic;padding:8px 0}
      .acdc-tdoc-list{list-style:none;padding:0;margin:0 0 8px}
      .acdc-tdoc-item{display:flex;align-items:flex-start;gap:8px;padding:10px 0;border-top:1px solid #eef2f7;font-size:13px}
      .acdc-tdoc-item:first-child{border-top:none}
      .acdc-tdoc-item-main{flex:1 1 auto;min-width:0}
      .acdc-tdoc-item-title{display:block;color:#1E4777;font-weight:500;word-break:break-word}
      .acdc-tdoc-item-meta{display:block;margin-top:3px;color:#5a6577;font-size:12px}
      .acdc-tdoc-actions{display:flex;gap:6px;flex:none}
      .acdc-tdoc-btn{display:inline-flex;align-items:center;justify-content:center;height:30px;padding:0 10px;font-size:12px;border-radius:6px;text-decoration:none;border:1px solid #e6ebf2;background:#fff;color:#1E4777;cursor:pointer}
      .acdc-tdoc-btn:hover{background:#f7f9fc}
      .acdc-tdoc-btn-danger{color:#c62828;border-color:#f3c5c5}
      .acdc-tdoc-btn-danger:hover{background:#fdecec}
      .acdc-tdoc-badge{display:inline-block;font-size:11px;font-weight:600;padding:2px 8px;border-radius:999px;margin-right:5px;vertical-align:middle}
      .acdc-tdoc-badge-ok{background:#e7f4ec;color:#1a7d3b}
      .acdc-tdoc-badge-warning{background:#fff3d6;color:#a06b00}
      .acdc-tdoc-badge-expired{background:#fdecec;color:#c62828}
      .acdc-tdoc-badge-qualiopi{background:#eef0fb;color:#3f4ca8}
      .acdc-tdoc-add-form{background:#fff;border:1px solid #e6ebf2;border-radius:10px;padding:18px;margin-bottom:18px}
      .acdc-tdoc-add-form .acdc-tdoc-form-row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:10px}
      @media(max-width:880px){.acdc-tdoc-add-form .acdc-tdoc-form-row{grid-template-columns:1fr}}
      .acdc-tdoc-add-form label{display:block;font-size:12px;font-weight:600;color:#1E4777;margin-bottom:4px}
      .acdc-tdoc-add-form input[type="text"],.acdc-tdoc-add-form input[type="date"],.acdc-tdoc-add-form select{width:100%;height:40px;padding:8px 10px;border:1px solid #e6ebf2;border-radius:8px;font-size:13px;box-sizing:border-box;background:#fff}
      .acdc-tdoc-add-form input[type="file"]{font-size:13px;padding:8px 0}
      .acdc-tdoc-intro{color:#5a6577;font-size:14px;line-height:1.6;margin:0 0 16px}
    </style>

    <h2 style="margin:0 0 6px;color:#1E4777;font-size:22px;">Ma bibliothèque</h2>
    <p class="acdc-tdoc-intro">
      Déposez ici vos justificatifs : CV, diplômes, certifications, attestation URSSAF, RC pro, etc.
      Les documents avec une date d’expiration sont surveillés visuellement.<br>
      Formats acceptés : PDF, JPG, PNG, DOC, DOCX, PPTX. Taille maximale : 100 Mo par fichier.
    </p>

    <form class="acdc-tdoc-add-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
      <?php wp_nonce_field( 'acdc_trainer_upload_own_document' ); ?>
      <input type="hidden" name="action" value="acdc_trainer_upload_own_document">

      <strong style="display:block;margin-bottom:10px;color:#1E4777;font-size:14px;">Ajouter un document</strong>

      <div class="acdc-tdoc-form-row">
        <div>
          <label for="acdc_tdoc_self_category">Catégorie *</label>
          <select id="acdc_tdoc_self_category" name="category" required>
            <?php foreach ( $cats as $key => $row ) : ?>
              <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $row['label'] ); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="acdc_tdoc_self_label">Libellé (optionnel)</label>
          <input type="text" id="acdc_tdoc_self_label" name="label" placeholder="Ex. Diplôme BTS communication 2018">
        </div>
        <div>
          <label for="acdc_tdoc_self_file">Fichier *</label>
          <input type="file" id="acdc_tdoc_self_file" name="document_file" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.pptx">
        </div>
      </div>

      <div class="acdc-tdoc-form-row">
        <div>
          <label for="acdc_tdoc_self_issued">Date d’émission (optionnel)</label>
          <input type="date" id="acdc_tdoc_self_issued" name="issued_at">
        </div>
        <div>
          <label for="acdc_tdoc_self_expires">Date d’expiration (recommandée pour URSSAF, RC pro)</label>
          <input type="date" id="acdc_tdoc_self_expires" name="expires_at">
        </div>
        <div></div>
      </div>

      <p style="margin:0;text-align:right;">
        <button type="submit" class="acdc-button acdc-button-primary">Ajouter le document</button>
      </p>
    </form>

    <div class="acdc-tdoc-grid">
      <?php foreach ( $cats as $cat_key => $cat_row ) : ?>
        <?php $docs = isset( $grouped[ $cat_key ] ) ? $grouped[ $cat_key ] : array(); ?>
        <div class="acdc-tdoc-card">
          <div class="acdc-tdoc-card-head">
            <h4><?php echo esc_html( $cat_row['label'] ); ?></h4>
            <span class="acdc-tdoc-count"><?php echo count( $docs ); ?></span>
          </div>
          <?php if ( empty( $docs ) ) : ?>
            <p class="acdc-tdoc-empty">Aucun document.</p>
          <?php else : ?>
            <ul class="acdc-tdoc-list">
              <?php foreach ( $docs as $doc ) :
                $expiry = $this->describe_trainer_document_expiry( isset( $doc->expires_at ) ? $doc->expires_at : '' );
                $download_url = wp_nonce_url(
                  add_query_arg( array( 'action' => 'acdc_trainer_download_own_document', 'document_id' => (int) $doc->id ), admin_url( 'admin-post.php' ) ),
                  'acdc_trainer_download_own_document_' . (int) $doc->id
                );
                $delete_url = wp_nonce_url(
                  add_query_arg( array( 'action' => 'acdc_trainer_delete_own_document', 'document_id' => (int) $doc->id ), admin_url( 'admin-post.php' ) ),
                  'acdc_trainer_delete_own_document_' . (int) $doc->id
                );
                $is_admin_uploaded = ( 'admin' === ( isset( $doc->uploaded_by ) ? $doc->uploaded_by : '' ) );
              ?>
              <li class="acdc-tdoc-item">
                <div class="acdc-tdoc-item-main">
                  <span class="acdc-tdoc-item-title"><?php echo esc_html( $doc->label ? $doc->label : $doc->file_name ); ?></span>
                  <span class="acdc-tdoc-item-meta">
                    <?php if ( ! empty( $doc->is_qualiopi_proof ) ) : ?>
                      <span class="acdc-tdoc-badge acdc-tdoc-badge-qualiopi">Qualiopi</span>
                    <?php endif; ?>
                    <?php if ( 'expired' === $expiry['state'] ) : ?>
                      <span class="acdc-tdoc-badge acdc-tdoc-badge-expired"><?php echo esc_html( $expiry['label'] ); ?></span>
                    <?php elseif ( 'warning' === $expiry['state'] ) : ?>
                      <span class="acdc-tdoc-badge acdc-tdoc-badge-warning"><?php echo esc_html( $expiry['label'] ); ?></span>
                    <?php elseif ( 'ok' === $expiry['state'] ) : ?>
                      <span class="acdc-tdoc-badge acdc-tdoc-badge-ok"><?php echo esc_html( $expiry['label'] ); ?></span>
                    <?php endif; ?>
                    <span style="display:block;margin-top:2px;color:#7d8898;font-size:11px;">
                      <?php echo esc_html( $doc->file_name ); ?>
                      <?php if ( ! empty( $doc->created_at ) ) : ?> · ajouté le <?php echo esc_html( mysql2date( 'd/m/Y', $doc->created_at ) ); ?><?php endif; ?>
                      <?php if ( $is_admin_uploaded ) : ?> · déposé par l’administrateur<?php endif; ?>
                    </span>
                  </span>
                </div>
                <div class="acdc-tdoc-actions">
                  <a class="acdc-tdoc-btn" href="<?php echo esc_url( $download_url ); ?>">Télécharger</a>
                  <?php if ( ! $is_admin_uploaded ) : ?>
                    <a class="acdc-tdoc-btn acdc-tdoc-btn-danger" href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('Supprimer définitivement ce document ?');">Supprimer</a>
                  <?php endif; ?>
                </div>
              </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
    return (string) ob_get_clean();
  }

  /**
   * ACDC 3.20.89 — Vue « Mon profil » côté formateur.
   * Le formateur édite ses champs personnels (photo, identité, coordonnées,
   * bio, préférences, opt-in apprenants). Les champs admin restent en lecture seule.
   */
  private function render_trainer_portal_profile( $account ) {
    $trainer = $this->get_trainer( (int) $account->trainer_id );
    if ( ! $trainer ) {
      return '<div class="acdc-alert acdc-alert-error"><p>Profil formateur introuvable. Contactez l’administrateur.</p></div>';
    }
    $genders = $this->get_trainer_gender_options();
    $photo_url = isset( $trainer->photo_url ) ? (string) $trainer->photo_url : '';

    ob_start();
    ?>
    <style>
      .acdc-tprof-stack{display:flex;flex-direction:column;gap:18px}
      .acdc-tprof-card{background:#fff;border:1px solid #e6ebf2;border-radius:10px;padding:24px}
      .acdc-tprof-card h3{margin:0 0 4px;color:#1E4777;font-size:16px;font-weight:600}
      .acdc-tprof-card .acdc-tprof-card-help{margin:0 0 18px;color:#5a6577;font-size:13px;line-height:1.5}
      .acdc-tprof-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
      @media(max-width:760px){.acdc-tprof-grid{grid-template-columns:1fr}}
      .acdc-tprof-field label{display:block;font-size:12px;font-weight:600;color:#1E4777;margin-bottom:4px}
      .acdc-tprof-field input[type="text"],.acdc-tprof-field input[type="tel"],.acdc-tprof-field input[type="date"],.acdc-tprof-field select,.acdc-tprof-field textarea{width:100%;height:40px;padding:8px 10px;border:1px solid #e6ebf2;border-radius:8px;font-size:14px;box-sizing:border-box;background:#fff;color:#1E4777}
      .acdc-tprof-field textarea{height:auto;min-height:120px;line-height:1.5}
      .acdc-tprof-field-readonly input,.acdc-tprof-field-readonly select{background:#f7f9fc!important;color:#7d8898!important;cursor:not-allowed}
      .acdc-tprof-photo-row{display:flex;align-items:center;gap:18px;flex-wrap:wrap}
      .acdc-tprof-photo-current{flex:none}
      .acdc-tprof-photo-current img{width:96px;height:96px;border-radius:50%;object-fit:cover;border:2px solid #e6ebf2;display:block}
      .acdc-tprof-photo-placeholder{width:96px;height:96px;border-radius:50%;background:#f3e3bf;color:#1E4777;display:flex;align-items:center;justify-content:center;font-size:36px;font-weight:600;border:2px solid #e6ebf2}
      .acdc-tprof-photo-actions{flex:1 1 200px;min-width:0}
      .acdc-tprof-photo-actions input[type="file"]{font-size:13px;padding:8px 0;display:block;margin-bottom:8px}
      .acdc-tprof-checkbox{display:flex;align-items:flex-start;gap:10px;padding:8px 0}
      .acdc-tprof-checkbox input{margin-top:3px;width:auto;height:auto}
      .acdc-tprof-checkbox label{font-size:13px;color:#1E4777;line-height:1.5;cursor:pointer}
      .acdc-tprof-checkbox .acdc-tprof-help{display:block;margin-top:2px;color:#5a6577;font-size:12px;font-weight:400}
      .acdc-tprof-readonly-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
      @media(max-width:760px){.acdc-tprof-readonly-list{grid-template-columns:1fr}}
      .acdc-tprof-readonly-row{padding:10px 14px;background:#f7f9fc;border:1px solid #e6ebf2;border-radius:8px}
      .acdc-tprof-readonly-row strong{display:block;font-size:12px;color:#5a6577;font-weight:500;margin-bottom:2px;text-transform:uppercase;letter-spacing:.5px}
      .acdc-tprof-readonly-row span{display:block;font-size:14px;color:#1E4777;font-weight:500;word-break:break-word}
      .acdc-tprof-readonly-row em{color:#7d8898;font-style:italic;font-weight:400}
      .acdc-tprof-actions-bar{display:flex;justify-content:flex-end;gap:10px;padding-top:8px}
    </style>

    <h2 style="margin:0 0 6px;color:#1E4777;font-size:22px;">Mon profil</h2>
    <p style="margin:0 0 18px;color:#5a6577;font-size:14px;line-height:1.6;">
      Tenez à jour vos informations personnelles, votre présentation et vos préférences. Les champs administratifs (e-mail, NDA, statut juridique, etc.) sont en lecture seule — pour toute modification, contactez l’administrateur ACDC.
    </p>

    <form class="acdc-tprof-stack" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
      <?php wp_nonce_field( 'acdc_trainer_update_own_profile' ); ?>
      <input type="hidden" name="action" value="acdc_trainer_update_own_profile">

      <!-- Carte 1 — Identité et photo -->
      <div class="acdc-tprof-card">
        <h3>Identité et photo</h3>
        <p class="acdc-tprof-card-help">Votre nom et prénom sont utilisés sur les conventions, attestations et programmes.</p>

        <div class="acdc-tprof-photo-row">
          <div class="acdc-tprof-photo-current">
            <?php if ( '' !== $photo_url ) : ?>
              <img src="<?php echo esc_url( $photo_url ); ?>" alt="Photo de profil">
            <?php else : ?>
              <div class="acdc-tprof-photo-placeholder"><?php echo esc_html( strtoupper( mb_substr( (string) $trainer->first_name, 0, 1 ) . mb_substr( (string) $trainer->last_name, 0, 1 ) ) ); ?></div>
            <?php endif; ?>
          </div>
          <div class="acdc-tprof-photo-actions">
            <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp">
            <p style="margin:4px 0 0;color:#5a6577;font-size:12px;">JPG, PNG ou WEBP. 5 Mo maximum.</p>
            <?php if ( '' !== $photo_url ) : ?>
              <label style="display:flex;align-items:center;gap:8px;margin-top:10px;font-size:13px;color:#c62828;cursor:pointer;">
                <input type="checkbox" name="remove_photo" value="1" style="width:auto;height:auto;">
                Retirer ma photo actuelle
              </label>
            <?php endif; ?>
          </div>
        </div>

        <div class="acdc-tprof-grid" style="margin-top:18px;">
          <div class="acdc-tprof-field">
            <label for="acdc_tprof_gender">Civilité</label>
            <select id="acdc_tprof_gender" name="profile[gender]">
              <?php foreach ( $genders as $k => $lbl ) : ?>
                <option value="<?php echo esc_attr( $k ); ?>" <?php selected( (string) $trainer->gender, (string) $k ); ?>><?php echo esc_html( $lbl ); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="acdc-tprof-field">
            <label for="acdc_tprof_birth">Date de naissance</label>
            <input type="date" id="acdc_tprof_birth" name="profile[birth_date]" value="<?php echo esc_attr( ! empty( $trainer->birth_date ) && '0000-00-00' !== $trainer->birth_date ? $trainer->birth_date : '' ); ?>">
          </div>
          <div class="acdc-tprof-field">
            <label for="acdc_tprof_first">Prénom *</label>
            <input type="text" id="acdc_tprof_first" name="profile[first_name]" required value="<?php echo esc_attr( $trainer->first_name ); ?>">
          </div>
          <div class="acdc-tprof-field">
            <label for="acdc_tprof_last">Nom *</label>
            <input type="text" id="acdc_tprof_last" name="profile[last_name]" required value="<?php echo esc_attr( $trainer->last_name ); ?>">
          </div>
        </div>
      </div>

      <!-- Carte 2 — Coordonnées et présentation -->
      <div class="acdc-tprof-card">
        <h3>Coordonnées et présentation</h3>
        <p class="acdc-tprof-card-help">Votre numéro de téléphone et votre bio peuvent être visibles par les apprenants si vous l’autorisez ci-dessous.</p>

        <div class="acdc-tprof-grid">
          <div class="acdc-tprof-field">
            <label for="acdc_tprof_phone">Téléphone</label>
            <input type="tel" id="acdc_tprof_phone" name="profile[phone]" value="<?php echo esc_attr( $trainer->phone ); ?>" placeholder="06 XX XX XX XX">
          </div>
          <div></div>
        </div>

        <div class="acdc-tprof-field" style="margin-top:14px;">
          <label for="acdc_tprof_desc">Présentation / bio</label>
          <textarea id="acdc_tprof_desc" name="profile[description_text]" rows="6" placeholder="Quelques lignes pour valoriser votre expertise, votre parcours, vos domaines d’intervention..."><?php echo esc_textarea( $trainer->description_text ); ?></textarea>
        </div>
      </div>

      <!-- Carte 3 — Préférences et confidentialité -->
      <div class="acdc-tprof-card">
        <h3>Préférences et confidentialité</h3>
        <p class="acdc-tprof-card-help">Choisissez quelles informations vous acceptez de partager avec les apprenants, et comment vous souhaitez être notifié.</p>

        <div style="margin-bottom:14px;">
          <strong style="display:block;color:#1E4777;font-size:13px;margin-bottom:8px;">Notifications</strong>
          <div class="acdc-tprof-checkbox">
            <input type="checkbox" id="acdc_tprof_remind" name="profile[session_reminder_enabled]" value="1" <?php checked( ! empty( $trainer->session_reminder_enabled ) ); ?>>
            <label for="acdc_tprof_remind">
              Me rappeler de mes prochaines sessions
              <span class="acdc-tprof-help">Recevoir un e-mail de rappel avant chaque session animée.</span>
            </label>
          </div>
          <div class="acdc-tprof-checkbox">
            <input type="checkbox" id="acdc_tprof_start" name="profile[session_start_enabled]" value="1" <?php checked( ! empty( $trainer->session_start_enabled ) ); ?>>
            <label for="acdc_tprof_start">
              M’avertir au démarrage de chaque session
              <span class="acdc-tprof-help">Notification au moment où une session que vous animez débute.</span>
            </label>
          </div>
        </div>

        <div>
          <strong style="display:block;color:#1E4777;font-size:13px;margin-bottom:8px;">Informations visibles par les apprenants (RGPD)</strong>
          <div class="acdc-tprof-checkbox">
            <input type="checkbox" id="acdc_tprof_li_photo" name="profile[learner_info_photo]" value="1" <?php checked( ! empty( $trainer->learner_info_photo ) ); ?>>
            <label for="acdc_tprof_li_photo">Autoriser l’affichage de ma photo aux apprenants</label>
          </div>
          <div class="acdc-tprof-checkbox">
            <input type="checkbox" id="acdc_tprof_li_name" name="profile[learner_info_name]" value="1" <?php checked( ! empty( $trainer->learner_info_name ) ); ?>>
            <label for="acdc_tprof_li_name">Autoriser l’affichage de mon nom et prénom aux apprenants</label>
          </div>
          <div class="acdc-tprof-checkbox">
            <input type="checkbox" id="acdc_tprof_li_desc" name="profile[learner_info_description]" value="1" <?php checked( ! empty( $trainer->learner_info_description ) ); ?>>
            <label for="acdc_tprof_li_desc">Autoriser l’affichage de ma bio aux apprenants</label>
          </div>
          <div class="acdc-tprof-checkbox">
            <input type="checkbox" id="acdc_tprof_li_avail" name="profile[learner_info_availability]" value="1" <?php checked( ! empty( $trainer->learner_info_availability ) ); ?>>
            <label for="acdc_tprof_li_avail">Autoriser l’affichage de mes disponibilités aux apprenants</label>
          </div>
        </div>
      </div>

      <div class="acdc-tprof-actions-bar">
        <button type="submit" class="acdc-button acdc-button-primary">Enregistrer mes modifications</button>
      </div>
    </form>

    <!-- Carte 4 — Informations administratives (lecture seule) -->
    <div class="acdc-tprof-card" style="margin-top:18px;">
      <h3>Informations administratives</h3>
      <p class="acdc-tprof-card-help">Pour modifier ces informations, contactez l’administrateur ACDC.</p>

      <div class="acdc-tprof-readonly-list">
        <div class="acdc-tprof-readonly-row">
          <strong>E-mail de connexion</strong>
          <span><?php echo esc_html( $account->email ); ?></span>
        </div>
        <div class="acdc-tprof-readonly-row">
          <strong>Rôle dans l’organisme</strong>
          <span><?php echo '' !== $trainer->role_name ? esc_html( $trainer->role_name ) : '<em>Non renseigné</em>'; ?></span>
        </div>
        <div class="acdc-tprof-readonly-row">
          <strong>Type de formateur</strong>
          <span><?php echo '' !== $trainer->trainer_type ? esc_html( $trainer->trainer_type ) : '<em>Non renseigné</em>'; ?></span>
        </div>
        <div class="acdc-tprof-readonly-row">
          <strong>Statut juridique</strong>
          <span><?php echo '' !== ( $trainer->legal_status ?? '' ) ? esc_html( $trainer->legal_status ) : '<em>Non renseigné</em>'; ?></span>
        </div>
        <div class="acdc-tprof-readonly-row">
          <strong>SIRET</strong>
          <span><?php echo '' !== $trainer->siret ? esc_html( $trainer->siret ) : '<em>Non renseigné</em>'; ?></span>
        </div>
        <div class="acdc-tprof-readonly-row">
          <strong>Numéro NDA</strong>
          <span><?php echo '' !== $trainer->nda_number ? esc_html( $trainer->nda_number ) : '<em>Non renseigné</em>'; ?></span>
        </div>
      </div>

      <p style="margin:14px 0 0;color:#5a6577;font-size:12px;font-style:italic;">
        Vos justificatifs administratifs (CV, diplômes, attestation URSSAF, RC pro, etc.) se gèrent depuis l’onglet « Ma bibliothèque ».
        Vos disponibilités hebdomadaires seront éditables prochainement via un calendrier annuel dédié.
      </p>
    </div>
    <?php
    return (string) ob_get_clean();
  }

  /**
   * ACDC 3.20.90 — Vue « Mes disponibilités » côté formateur.
   * Édition du rythme habituel (cases à cocher matin/après-midi par jour de la semaine)
   * + calendrier mensuel cliquable pour gérer les exceptions.
   */
  private function render_trainer_portal_availability( $account ) {
    $trainer = $this->get_trainer( (int) $account->trainer_id );
    if ( ! $trainer ) {
      return '<div class="acdc-alert acdc-alert-error"><p>Profil formateur introuvable. Contactez l’administrateur.</p></div>';
    }
    $availability = $this->parse_trainer_availability( $trainer->availability_json );

    // Mois affiché : ?month=YYYY-MM ou mois courant.
    $month_param = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : '';
    if ( ! preg_match( '/^\d{4}-\d{2}$/', $month_param ) ) {
      $month_param = wp_date( 'Y-m' );
    }
    $month_ts = strtotime( $month_param . '-01' );
    if ( ! $month_ts ) {
      $month_ts = strtotime( wp_date( 'Y-m' ) . '-01' );
    }

    $weekdays = $this->get_acdc_weekday_labels();
    $ajax_url = admin_url( 'admin-post.php' );
    $nonce    = wp_create_nonce( 'acdc_trainer_toggle_availability' );

    ob_start();
    ?>
    <style>
      .acdc-avail-stack{display:flex;flex-direction:column;gap:18px}
      .acdc-avail-card{background:#fff;border:1px solid #e6ebf2;border-radius:10px;padding:24px}
      .acdc-avail-card h3{margin:0 0 4px;color:#1E4777;font-size:16px;font-weight:600}
      .acdc-avail-card-help{margin:0 0 18px;color:#5a6577;font-size:13px;line-height:1.5}

      /* Tableau du rythme habituel */
      .acdc-avail-weekly{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:10px}
      @media(max-width:760px){.acdc-avail-weekly{grid-template-columns:repeat(2,1fr)}}
      .acdc-avail-day{background:#f7f9fc;border:1px solid #e6ebf2;border-radius:8px;padding:12px;text-align:center}
      .acdc-avail-day strong{display:block;color:#1E4777;font-size:13px;margin-bottom:8px}
      .acdc-avail-day label{display:flex;align-items:center;justify-content:center;gap:6px;font-size:12px;color:#5a6577;cursor:pointer;padding:4px 0}
      .acdc-avail-day input{width:auto;height:auto;margin:0}

      /* Calendrier mensuel */
      .acdc-cal-toolbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;gap:12px;flex-wrap:wrap}
      .acdc-cal-toolbar .acdc-cal-month-name{flex:1 1 auto;text-align:center;color:#1E4777;font-size:18px;font-weight:600;text-transform:capitalize}
      .acdc-cal-nav{display:flex;gap:8px;flex:none}
      .acdc-cal-nav a{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border:1px solid #e6ebf2;border-radius:8px;color:#1E4777;text-decoration:none;font-size:18px;background:#fff}
      .acdc-cal-nav a:hover{background:#f7f9fc}

      .acdc-cal-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:6px}
      .acdc-cal-header{display:contents}
      .acdc-cal-dayname{text-align:center;font-size:11px;font-weight:600;color:#5a6577;text-transform:uppercase;letter-spacing:.5px;padding:6px 0}
      .acdc-cal-day{background:#fff;border:1px solid #e6ebf2;border-radius:8px;overflow:hidden;min-height:64px;display:flex;flex-direction:column;position:relative}
      .acdc-cal-day-empty{background:transparent;border:none;min-height:64px}
      .acdc-cal-day-num{font-size:11px;font-weight:600;color:#5a6577;padding:4px 6px;background:#f7f9fc;border-bottom:1px solid #e6ebf2;display:flex;justify-content:space-between;align-items:center}
      .acdc-cal-day-num.is-today{background:#fff3d6;color:#a06b00}
      .acdc-cal-cells{display:grid;grid-template-columns:repeat(2,1fr);flex:1 1 auto}
      .acdc-cal-cell{border:none;cursor:pointer;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;padding:8px 4px;display:flex;align-items:center;justify-content:center;transition:opacity .15s;background:transparent;color:#5a6577}
      .acdc-cal-cell:first-child{border-right:1px solid #e6ebf2}
      .acdc-cal-cell.is-yes{background:#e7f4ec;color:#1a7d3b}
      .acdc-cal-cell.is-no{background:#fdecec;color:#c62828}
      .acdc-cal-cell.is-yes.is-exception{background:#1a7d3b;color:#fff}
      .acdc-cal-cell.is-no.is-exception{background:#c62828;color:#fff}
      .acdc-cal-cell:hover{opacity:.85}
      .acdc-cal-cell.is-loading{opacity:.4;cursor:wait}

      .acdc-cal-legend{display:flex;flex-wrap:wrap;gap:12px;margin-top:14px;font-size:12px;color:#5a6577}
      .acdc-cal-legend span{display:inline-flex;align-items:center;gap:6px}
      .acdc-cal-legend i{display:inline-block;width:14px;height:14px;border-radius:3px;border:1px solid rgba(0,0,0,.06)}
      .acdc-cal-legend i.is-yes{background:#e7f4ec}
      .acdc-cal-legend i.is-no{background:#fdecec}
      .acdc-cal-legend i.is-yes-exc{background:#1a7d3b}
      .acdc-cal-legend i.is-no-exc{background:#c62828}

      .acdc-avail-actions-bar{display:flex;justify-content:flex-end;gap:10px;padding-top:8px}
    </style>

    <h2 style="margin:0 0 6px;color:#1E4777;font-size:22px;">Mes disponibilités</h2>
    <p style="margin:0 0 18px;color:#5a6577;font-size:14px;line-height:1.6;">
      Définissez d’abord votre <strong>rythme habituel</strong> (les demi-journées où vous êtes disponible en règle générale), puis utilisez le <strong>calendrier mensuel</strong> ci-dessous pour ajouter des exceptions ponctuelles (vacances, jours off, dispos exceptionnelles le week-end, etc.).
    </p>

    <div class="acdc-avail-stack">

      <!-- Carte 1 — Rythme habituel -->
      <div class="acdc-avail-card">
        <h3>Mon rythme habituel</h3>
        <p class="acdc-avail-card-help">Cochez les demi-journées où vous êtes habituellement disponible. Ces réglages servent de base au calendrier ci-dessous.</p>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <?php wp_nonce_field( 'acdc_trainer_update_weekly_schedule' ); ?>
          <input type="hidden" name="action" value="acdc_trainer_update_weekly_schedule">

          <div class="acdc-avail-weekly">
            <?php foreach ( $weekdays as $key => $label ) :
              $base = isset( $availability['weekly'][ $key ] ) ? $availability['weekly'][ $key ] : array( 'morning' => false, 'afternoon' => false );
            ?>
              <div class="acdc-avail-day">
                <strong><?php echo esc_html( $label ); ?></strong>
                <label>
                  <input type="checkbox" name="weekly[<?php echo esc_attr( $key ); ?>][morning]" value="1" <?php checked( ! empty( $base['morning'] ) ); ?>>
                  Matin
                </label>
                <label>
                  <input type="checkbox" name="weekly[<?php echo esc_attr( $key ); ?>][afternoon]" value="1" <?php checked( ! empty( $base['afternoon'] ) ); ?>>
                  Après-midi
                </label>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="acdc-avail-actions-bar" style="margin-top:18px;">
            <button type="submit" class="acdc-button acdc-button-primary">Enregistrer mon rythme habituel</button>
          </div>
        </form>
      </div>

      <!-- Carte 2 — Calendrier mensuel -->
      <div class="acdc-avail-card">
        <h3>Calendrier des exceptions</h3>
        <p class="acdc-avail-card-help">Cliquez sur une demi-journée (Matin / Après-midi) pour basculer son état. Les jours qui correspondent à votre rythme habituel sont en couleurs claires ; les exceptions ressortent en couleurs vives.</p>

        <?php echo $this->render_trainer_availability_calendar( $availability, $month_ts, true, $ajax_url, $nonce, null, (int) $trainer->id ); ?>

        <div class="acdc-cal-legend">
          <span><i class="is-yes"></i> Disponible (rythme habituel)</span>
          <span><i class="is-no"></i> Indisponible (rythme habituel)</span>
          <span><i class="is-yes-exc"></i> Disponibilité exceptionnelle</span>
          <span><i class="is-no-exc"></i> Indisponibilité exceptionnelle</span>
          <span><i style="background:#C5A253;"></i> 📋 Mission / contrat</span>
          <span><i style="background:#1E4777;"></i> 🎓 Session assignée</span>
        </div>
      </div>
    </div>

    <script>
    (function(){
      var cal = document.querySelector('[data-acdc-cal-editable]');
      if(!cal) return;
      var nonce = cal.dataset.nonce;
      var ajaxUrl = cal.dataset.ajaxurl;

      cal.addEventListener('click', function(e){
        var btn = e.target.closest('[data-acdc-cell]');
        if(!btn) return;
        e.preventDefault();
        if(btn.classList.contains('is-loading')) return;

        var date = btn.dataset.date;
        var part = btn.dataset.part;
        btn.classList.add('is-loading');

        var fd = new FormData();
        fd.append('action', 'acdc_trainer_toggle_availability');
        fd.append('_wpnonce', nonce);
        fd.append('date', date);
        fd.append('part', part);

        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function(r){ return r.json(); })
          .then(function(data){
            if(!data || !data.success){
              alert((data && data.data && data.data.message) ? data.data.message : 'Erreur lors de la sauvegarde.');
              btn.classList.remove('is-loading');
              return;
            }
            // Mettre à jour les deux cellules de cette date (matin et après-midi)
            // car la suppression d'une exception peut affecter les deux.
            var dayBlock = btn.closest('[data-acdc-day="'+date+'"]');
            if(dayBlock){
              var morningCell   = dayBlock.querySelector('[data-part="morning"]');
              var afternoonCell = dayBlock.querySelector('[data-part="afternoon"]');
              if(morningCell)   morningCell.className   = 'acdc-cal-cell ' + data.data.morning_class;
              if(afternoonCell) afternoonCell.className = 'acdc-cal-cell ' + data.data.afternoon_class;
            }
            btn.classList.remove('is-loading');
          })
          .catch(function(){
            alert('Erreur réseau. Réessayez.');
            btn.classList.remove('is-loading');
          });
      });
    })();
    </script>
    <?php
    return (string) ob_get_clean();
  }

  /**
   * ACDC 3.20.92 — Page « Mes sessions » du portail formateur.
   *
   * Vue liste OU vue détaillée selon présence du paramètre ?session_id en GET.
   * Sécurité (arbitrage 1b validé) :
   *   - L'onglet est toujours visible dans la nav.
   *   - Cette fonction fait elle-même le contrôle de permission view_own_sessions.
   *   - Si refusée : message clair, pas de leak d'information.
   *
   * Source des données : union de deux liens id-based, indépendants depuis la 3.20.92 :
   *   - sessions.trainer_id  (alias direct sur la session)
   *   - groups.trainer_id    (formateur désigné via un des groupes de la session)
   *
   * @param object $account Compte portail formateur authentifié.
   * @return string HTML.
   */
  private function render_trainer_portal_sessions( $account ) {
    $trainer_id = (int) $account->trainer_id;

    // Contrôle de permission de premier niveau (arbitrage 1b).
    if ( ! $this->trainer_can( $trainer_id, 'view_own_sessions' ) ) {
      ob_start();
      ?>
      <div class="acdc-panel" style="padding:24px;text-align:center;">
        <h3 style="margin:0 0 10px;color:#1E4777;">Mes sessions</h3>
        <p style="margin:0;color:#5a6577;">
          Vous n’avez pas l’autorisation d’accéder à cette section.
          Si vous pensez qu’il s’agit d’une erreur, contactez l’administration.
        </p>
      </div>
      <?php
      return (string) ob_get_clean();
    }

    $session_id = isset( $_GET['session_id'] ) ? absint( wp_unslash( $_GET['session_id'] ) ) : 0;
    if ( $session_id > 0 ) {
      return $this->render_trainer_portal_session_detail( $trainer_id, $session_id );
    }
    return $this->render_trainer_portal_sessions_list( $trainer_id );
  }

  /**
   * ACDC 3.20.92 — Vue liste des sessions du formateur, séparée en « à venir » / « passées ».
   * @param int $trainer_id
   * @return string HTML
   */
  private function render_trainer_portal_sessions_list( $trainer_id ) {
    global $wpdb;

    /* Requête : on prend les sessions où le formateur est désigné soit directement
       (sessions.trainer_id) soit via un de ses groupes (groups.trainer_id).
       DISTINCT sur s.id pour ne pas dupliquer si plusieurs groupes correspondent. */
    $rows = $wpdb->get_results( $wpdb->prepare(
      "SELECT s.*, f.title AS formation_title, f.duration AS formation_duration,
              c.name AS company_name,
              ( SELECT COUNT(*) FROM {$this->learner_table} l WHERE l.session_id = s.id ) AS learner_count
       FROM {$this->session_table} s
       LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id
       LEFT JOIN {$this->company_table} c ON c.id = s.company_id
       WHERE s.trainer_id = %d
          OR EXISTS ( SELECT 1 FROM {$this->group_table} g WHERE g.session_id = s.id AND g.trainer_id = %d )
       GROUP BY s.id
       ORDER BY COALESCE(s.start_at, CONCAT(COALESCE(s.start_date,'1970-01-01'), ' 00:00:00')) DESC, s.id DESC",
      $trainer_id, $trainer_id
    ) );

    $now_ts = current_time( 'timestamp' );
    $upcoming = array();
    $past     = array();
    foreach ( (array) $rows as $row ) {
      $ref = $row->start_at ? $row->start_at : ( $row->start_date ? $row->start_date . ' 00:00:00' : '' );
      $ref_ts = $ref ? strtotime( $ref ) : 0;
      if ( $ref_ts && $ref_ts >= $now_ts ) {
        $upcoming[] = $row;
      } else {
        $past[] = $row;
      }
    }
    // Tri à venir : du plus proche au plus lointain.
    usort( $upcoming, function( $a, $b ) {
      $ta = strtotime( $a->start_at ? $a->start_at : ( $a->start_date ? $a->start_date . ' 00:00:00' : '0' ) );
      $tb = strtotime( $b->start_at ? $b->start_at : ( $b->start_date ? $b->start_date . ' 00:00:00' : '0' ) );
      return $ta - $tb;
    } );

    ob_start();
    ?>
    <style>
      .acdc-tportal-sessions h3{margin:0 0 14px;color:#1E4777;font-size:18px}
      .acdc-tportal-sessions .acdc-panel{margin-bottom:24px;padding:20px}
      .acdc-tportal-sessions .acdc-session-card{display:flex;gap:14px;align-items:flex-start;padding:14px 16px;background:#fff;border:1px solid #e6ebf2;border-radius:10px;margin-bottom:10px;transition:border-color .15s,box-shadow .15s}
      .acdc-tportal-sessions .acdc-session-card:hover{border-color:#d6a353;box-shadow:0 2px 8px rgba(214,163,83,.12)}
      .acdc-tportal-sessions .acdc-session-card-body{flex:1 1 auto;min-width:0}
      .acdc-tportal-sessions .acdc-session-card-title{display:block;font-size:15px;font-weight:600;color:#1E4777;text-decoration:none}
      .acdc-tportal-sessions .acdc-session-card-title:hover{text-decoration:underline}
      .acdc-tportal-sessions .acdc-session-card-meta{display:block;margin-top:4px;font-size:13px;color:#5a6577;line-height:1.55}
      .acdc-tportal-sessions .acdc-session-card-meta strong{color:#1E4777;font-weight:600}
      .acdc-tportal-sessions .acdc-session-card-actions{flex:none;display:flex;flex-direction:column;align-items:flex-end;gap:6px}
      .acdc-tportal-sessions .acdc-status-pill{display:inline-block;padding:3px 10px;border-radius:14px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;background:#f7f9fc;color:#5a6577;border:1px solid #e6ebf2}
      .acdc-tportal-sessions .acdc-status-pill.s-planifiee{background:#e8f0fe;color:#1E4777;border-color:#c7d8f2}
      .acdc-tportal-sessions .acdc-status-pill.s-encours{background:#fff3d6;color:#a06b00;border-color:#f0d690}
      .acdc-tportal-sessions .acdc-status-pill.s-terminee{background:#e7f4ec;color:#1a7d3b;border-color:#bcdcc4}
      .acdc-tportal-sessions .acdc-status-pill.s-annulee{background:#fdecec;color:#c62828;border-color:#f3c0c0}
      .acdc-tportal-sessions .acdc-empty{padding:18px 16px;background:#f7f9fc;border:1px dashed #d6dbe4;border-radius:10px;color:#5a6577;font-size:13px;text-align:center}
    </style>
    <div class="acdc-tportal-sessions">
      <div class="acdc-panel">
        <h3>Sessions à venir</h3>
        <?php if ( empty( $upcoming ) ) : ?>
          <div class="acdc-empty">Aucune session à venir pour le moment.<br><small>L’administration vous assigne aux groupes ou aux sessions ; elles apparaîtront ici dès que ce sera fait.</small></div>
        <?php else : ?>
          <?php foreach ( $upcoming as $row ) : echo $this->render_trainer_portal_session_card( $row ); endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="acdc-panel">
        <h3>Sessions passées</h3>
        <?php if ( empty( $past ) ) : ?>
          <div class="acdc-empty">Aucune session passée enregistrée.</div>
        <?php else : ?>
          <?php foreach ( $past as $row ) : echo $this->render_trainer_portal_session_card( $row ); endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php
    return (string) ob_get_clean();
  }

  /**
   * ACDC 3.20.92 — Carte d'une session (vue liste).
   * @param object $row
   * @return string HTML
   */
  private function render_trainer_portal_session_card( $row ) {
    $period = '';
    if ( ! empty( $row->start_date ) ) {
      $start_disp = mysql2date( 'd/m/Y', $row->start_date );
      $end_disp   = ! empty( $row->end_date ) && $row->end_date !== $row->start_date ? mysql2date( 'd/m/Y', $row->end_date ) : '';
      $period = $end_disp ? $start_disp . ' → ' . $end_disp : $start_disp;
    } elseif ( ! empty( $row->start_at ) ) {
      $period = mysql2date( 'd/m/Y H:i', $row->start_at );
    }
    $status = (string) $row->status;
    $status_class = 's-' . sanitize_html_class( strtolower( str_replace( array( 'é', 'è', 'ê', 'à' ), array( 'e', 'e', 'e', 'a' ), $status ) ) );
    $detail_url = $this->trainer_portal_page_url( 'sessions', array( 'session_id' => (int) $row->id ) );
    /* ACDC 3.24.28 — Badge bilan manquant sur les sessions passées. */
    $is_past = false;
    $ref = ! empty( $row->start_at ) ? $row->start_at : ( ! empty( $row->start_date ) ? $row->start_date . ' 00:00:00' : '' );
    if ( $ref ) { $is_past = strtotime( $ref ) < current_time( 'timestamp' ); }
    $needs_report_badge = $is_past && empty( $row->report_submitted_at );

    ob_start();
    ?>
    <div class="acdc-session-card">
      <div class="acdc-session-card-body">
        <a class="acdc-session-card-title" href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html( $row->title ); ?></a>
        <span class="acdc-session-card-meta">
          <?php if ( ! empty( $row->formation_title ) ) : ?><strong><?php echo esc_html( $row->formation_title ); ?></strong> · <?php endif; ?>
          <?php if ( $period ) : ?><?php echo esc_html( $period ); ?><?php endif; ?>
          <?php if ( ! empty( $row->location ) ) : ?> · <?php echo esc_html( $row->location ); ?><?php endif; ?>
          <?php if ( ! empty( $row->company_name ) ) : ?><br><?php echo esc_html( $row->company_name ); ?><?php endif; ?>
          <?php if ( (int) $row->learner_count > 0 ) : ?> · <?php echo (int) $row->learner_count; ?> apprenant<?php echo $row->learner_count > 1 ? 's' : ''; ?><?php endif; ?>
        </span>
      </div>
      <div class="acdc-session-card-actions">
        <span class="acdc-status-pill <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status ); ?></span>
        <?php if ( $needs_report_badge ) : ?>
          <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:#a06b00;background:#fff3d6;border:1px solid #f0d690;border-radius:10px;padding:3px 10px;">📋 Bilan à compléter</span>
        <?php endif; ?>
        <a class="acdc-button acdc-button-soft" href="<?php echo esc_url( $detail_url ); ?>" style="height:32px;padding:0 14px;display:inline-flex;align-items:center;border-radius:8px;font-size:12px;">Voir le détail</a>
      </div>
    </div>
    <?php
    return (string) ob_get_clean();
  }

  /**
   * ACDC 3.20.92 — Vue détaillée d'une session avec apprenants associés.
   * Vérifie strictement que la session appartient bien au formateur connecté
   * (sessions.trainer_id ou groups.trainer_id), sinon redirection vers la liste.
   *
   * @param int $trainer_id
   * @param int $session_id
   * @return string HTML
   */
  private function render_trainer_portal_session_detail( $trainer_id, $session_id ) {
    global $wpdb;

    $session = $wpdb->get_row( $wpdb->prepare(
      "SELECT s.*, f.title AS formation_title, f.duration AS formation_duration,
              c.name AS company_name
       FROM {$this->session_table} s
       LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id
       LEFT JOIN {$this->company_table} c ON c.id = s.company_id
       WHERE s.id = %d
         AND ( s.trainer_id = %d
               OR EXISTS ( SELECT 1 FROM {$this->group_table} g WHERE g.session_id = s.id AND g.trainer_id = %d ) )
       LIMIT 1",
      $session_id, $trainer_id, $trainer_id
    ) );

    if ( ! $session ) {
      ob_start();
      ?>
      <div class="acdc-panel" style="padding:24px;text-align:center;">
        <h3 style="margin:0 0 10px;color:#1E4777;">Session introuvable</h3>
        <p style="margin:0 0 16px;color:#5a6577;">Cette session n’existe pas, ou vous n’y êtes pas associé.</p>
        <p><a class="acdc-button acdc-button-primary" href="<?php echo esc_url( $this->trainer_portal_page_url( 'sessions' ) ); ?>">Retour à mes sessions</a></p>
      </div>
      <?php
      return (string) ob_get_clean();
    }

    $can_see_learners = $this->trainer_can( $trainer_id, 'view_session_learners' );
    $can_see_personal = $this->trainer_can( $trainer_id, 'view_learner_personal_data' );

    $learners = array();
    if ( $can_see_learners ) {
      /* ACDC 3.25.211 — La liste se déduit du dossier, elle ne se lit plus dans
         une colonne qui ne peut désigner qu'une seule séance. Voir la note du
         résolveur : sur une formation de deux jours, le second jour affichait
         « aucun apprenant inscrit » alors que trois personnes y assistaient. */
      $learners = $this->acdc_session_learners( $session );
    }

    $period = '';
    if ( ! empty( $session->start_date ) ) {
      $start_disp = mysql2date( 'd/m/Y', $session->start_date );
      $end_disp   = ! empty( $session->end_date ) && $session->end_date !== $session->start_date ? mysql2date( 'd/m/Y', $session->end_date ) : '';
      $period = $end_disp ? $start_disp . ' → ' . $end_disp : $start_disp;
    } elseif ( ! empty( $session->start_at ) ) {
      $period = mysql2date( 'd/m/Y H:i', $session->start_at );
    }

    ob_start();
    ?>
    <style>
      .acdc-tportal-session-detail h3{margin:0 0 14px;color:#1E4777;font-size:18px}
      .acdc-tportal-session-detail .acdc-panel{margin-bottom:20px;padding:22px}
      .acdc-tportal-session-detail .acdc-info-grid{display:grid;grid-template-columns:160px 1fr;gap:8px 18px;font-size:14px}
      .acdc-tportal-session-detail .acdc-info-grid dt{color:#5a6577;font-weight:500;margin:0}
      .acdc-tportal-session-detail .acdc-info-grid dd{color:#1E4777;margin:0}
      .acdc-tportal-session-detail table.acdc-table{width:100%;border-collapse:collapse}
      .acdc-tportal-session-detail table.acdc-table th{background:#f7f9fc;color:#1E4777;font-size:12px;text-transform:uppercase;letter-spacing:.4px;padding:10px 12px;text-align:left;border-bottom:1px solid #e6ebf2}
      .acdc-tportal-session-detail table.acdc-table td{padding:10px 12px;border-bottom:1px solid #f0f3f8;color:#1E4777;font-size:14px}
      .acdc-tportal-session-detail .acdc-perm-warning{padding:14px 16px;background:#fff8e6;border:1px solid #f0d690;border-radius:8px;color:#a06b00;font-size:13px;line-height:1.55}
      .acdc-tportal-session-detail .acdc-back{display:inline-flex;align-items:center;gap:6px;color:#5a6577;text-decoration:none;font-size:13px;margin-bottom:16px}
      .acdc-tportal-session-detail .acdc-back:hover{color:#1E4777}
    </style>
    <div class="acdc-tportal-session-detail">
      <a class="acdc-back" href="<?php echo esc_url( $this->trainer_portal_page_url( 'sessions' ) ); ?>">← Retour à mes sessions</a>

      <div class="acdc-panel">
        <h3><?php echo esc_html( $session->title ); ?></h3>
        <dl class="acdc-info-grid">
          <?php if ( ! empty( $session->formation_title ) ) : ?>
            <dt>Formation</dt><dd><?php echo esc_html( $session->formation_title ); ?><?php if ( ! empty( $session->formation_duration ) ) : ?> <span style="color:#5a6577;">(<?php echo esc_html( $session->formation_duration ); ?>)</span><?php endif; ?></dd>
          <?php endif; ?>
          <?php if ( ! empty( $session->company_name ) ) : ?>
            <dt>Entreprise</dt><dd><?php echo esc_html( $session->company_name ); ?></dd>
          <?php endif; ?>
          <?php if ( $period ) : ?>
            <dt>Période</dt><dd><?php echo esc_html( $period ); ?></dd>
          <?php endif; ?>
          <?php if ( ! empty( $session->location ) ) : ?>
            <dt>Lieu</dt><dd><?php echo esc_html( $session->location ); ?></dd>
          <?php endif; ?>
          <?php if ( ! empty( $session->session_format ) ) : ?>
            <dt>Format</dt><dd><?php echo esc_html( $session->session_format ); ?></dd>
          <?php endif; ?>
          <?php if ( ! empty( $session->remote_link ) ) : ?>
            <dt>Lien distanciel</dt><dd><a href="<?php echo esc_url( $session->remote_link ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $session->remote_link ); ?></a></dd>
          <?php endif; ?>
          <dt>Statut</dt><dd><?php echo esc_html( $session->status ); ?></dd>
          <?php if ( ! empty( $session->notes ) ) : ?>
            <dt>Notes</dt><dd style="white-space:pre-wrap;"><?php echo esc_html( $session->notes ); ?></dd>
          <?php endif; ?>
        </dl>
      </div>

      <div class="acdc-panel">
        <h3>Apprenants</h3>
        <?php if ( ! $can_see_learners ) : ?>
          <div class="acdc-perm-warning">
            Vous n’avez pas l’autorisation de consulter la liste des apprenants de cette session.
            Si vous pensez qu’il s’agit d’une erreur, contactez l’administration.
          </div>
        <?php elseif ( empty( $learners ) ) : ?>
          <div class="acdc-empty" style="padding:18px 16px;background:#f7f9fc;border:1px dashed #d6dbe4;border-radius:10px;color:#5a6577;font-size:13px;text-align:center;">Aucun apprenant inscrit pour cette session.</div>
        <?php else : ?>
          <?php if ( ! $can_see_personal ) : ?>
            <p style="margin:0 0 12px;color:#5a6577;font-size:12px;">Coordonnées masquées : vous pouvez voir l’identité des apprenants mais pas leurs données personnelles (e-mail, téléphone).</p>
          <?php endif; ?>
          <table class="acdc-table">
            <thead>
              <tr>
                <th>Nom</th>
                <th>Prénom</th>
                <?php if ( $can_see_personal ) : ?>
                  <th>E-mail</th>
                  <th>Téléphone</th>
                <?php endif; ?>
                <th>Statut</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ( $learners as $l ) :
              $display_last = ! empty( $l->usage_last_name ) ? $l->usage_last_name : $l->last_name;
            ?>
              <tr>
                <td><?php echo esc_html( $display_last ); ?></td>
                <td><?php echo esc_html( $l->first_name ); ?></td>
                <?php if ( $can_see_personal ) : ?>
                  <td><?php echo esc_html( $l->email ); ?></td>
                  <td><?php echo esc_html( $l->phone ); ?></td>
                <?php endif; ?>
                <td><?php echo esc_html( $l->status ); ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <?php
      /* ---------------------------------------------------------------
       * ACDC 3.24.28 — BLOC A : Cahier de texte (1 entrée par jour).
       * --------------------------------------------------------------- */
      global $wpdb;
      $schedule = array();
      if ( ! empty( $session->schedule_json ) ) {
        $decoded = json_decode( $session->schedule_json, true );
        if ( is_array( $decoded ) ) { $schedule = $decoded; }
      }
      /* Dédupliquer par date (une entrée par jour, pas par demi-journée). */
      $days_seen = array();
      $day_slots = array();
      foreach ( $schedule as $slot ) {
        $d = ! empty( $slot['start_date'] ) ? $slot['start_date'] : ( ! empty( $slot['start_at'] ) ? substr( $slot['start_at'], 0, 10 ) : '' );
        if ( $d && ! isset( $days_seen[ $d ] ) ) {
          $days_seen[ $d ] = true;
          $day_slots[] = array( 'date' => $d, 'label' => mysql2date( 'l d/m/Y', $d ) );
        }
      }
      /* Si aucun créneau planifié : une seule zone libre. */
      if ( empty( $day_slots ) ) {
        $day_slots = array( array( 'date' => 'libre', 'label' => 'Déroulé de la session' ) );
      }
      /* Charger les entrées existantes du cahier. */
      $logbook_rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT slot_date, content, updated_at FROM {$this->trainer_logbook_table} WHERE session_id = %d",
        $session_id
      ) );
      $logbook_map = array();
      foreach ( (array) $logbook_rows as $lr ) { $logbook_map[ $lr->slot_date ] = $lr; }
      ?>
      <style>
        .acdc-tportal-logbook h3{margin:0 0 14px;color:#1E4777;font-size:18px}
        .acdc-tportal-logbook .acdc-log-slot{margin-bottom:16px}
        .acdc-tportal-logbook .acdc-log-slot-label{font-size:13px;font-weight:600;color:#1E4777;margin-bottom:6px;display:block;text-transform:capitalize}
        .acdc-tportal-logbook textarea{width:100%;min-height:90px;border:1px solid #d6dbe4;border-radius:8px;padding:10px 12px;font-size:13px;color:#1E4777;resize:vertical;box-sizing:border-box}
        .acdc-tportal-logbook textarea:focus{outline:none;border-color:#d6a353;box-shadow:0 0 0 3px rgba(214,163,83,.15)}
        .acdc-tportal-logbook .acdc-log-meta{font-size:11px;color:#9ba8b5;margin-top:4px}
        .acdc-tportal-logbook .acdc-log-save-btn{margin-top:8px;height:36px;padding:0 18px;font-size:13px}
        .acdc-tportal-report h3{margin:0 0 14px;color:#1E4777;font-size:18px}
        .acdc-tportal-report .acdc-report-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        .acdc-tportal-report .acdc-report-field label{display:block;font-size:12px;font-weight:600;color:#5a6577;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px}
        .acdc-tportal-report .acdc-report-field select,.acdc-tportal-report .acdc-report-field textarea{width:100%;border:1px solid #d6dbe4;border-radius:8px;padding:9px 12px;font-size:13px;color:#1E4777;box-sizing:border-box}
        .acdc-tportal-report .acdc-report-field select:focus,.acdc-tportal-report .acdc-report-field textarea:focus{outline:none;border-color:#d6a353;box-shadow:0 0 0 3px rgba(214,163,83,.15)}
        .acdc-tportal-report .acdc-report-field textarea{min-height:80px;resize:vertical}
        .acdc-tportal-report .acdc-report-full{grid-column:1/-1}
        .acdc-tportal-report .acdc-report-submitted{font-size:12px;color:#1a7d3b;background:#e7f4ec;border:1px solid #bcdcc4;border-radius:8px;padding:8px 14px;margin-bottom:14px;display:inline-block}
        @media(max-width:600px){.acdc-tportal-report .acdc-report-grid{grid-template-columns:1fr}}
      </style>

      <div class="acdc-panel acdc-tportal-logbook">
        <h3>📓 Cahier de texte</h3>
        <?php foreach ( $day_slots as $ds ) :
          $existing_content = isset( $logbook_map[ $ds['date'] ] ) ? $logbook_map[ $ds['date'] ]->content : '';
          $existing_updated = isset( $logbook_map[ $ds['date'] ] ) ? $logbook_map[ $ds['date'] ]->updated_at : '';
        ?>
        <div class="acdc-log-slot">
          <span class="acdc-log-slot-label"><?php echo esc_html( $ds['label'] ); ?></span>
          <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="acdc_trainer_save_logbook_entry">
            <?php wp_nonce_field( 'acdc_trainer_save_logbook_entry' ); ?>
            <input type="hidden" name="session_id" value="<?php echo (int) $session_id; ?>">
            <input type="hidden" name="slot_date" value="<?php echo esc_attr( $ds['date'] ); ?>">
            <input type="hidden" name="slot_label" value="<?php echo esc_attr( $ds['label'] ); ?>">
            <textarea name="content" placeholder="Décrivez le contenu abordé durant cette journée…"><?php echo esc_textarea( $existing_content ); ?></textarea>
            <?php if ( $existing_updated ) : ?>
              <div class="acdc-log-meta">Dernière mise à jour : <?php echo esc_html( mysql2date( 'd/m/Y à H:i', $existing_updated ) ); ?></div>
            <?php endif; ?>
            <button type="submit" class="acdc-button acdc-button-primary acdc-log-save-btn">Enregistrer</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>

      <?php
      /* ---------------------------------------------------------------
       * ACDC 3.25.207 — BLOC C : Documents remis aux apprenants.
       *
       * Le formateur dépose ici ce qui ne concerne QUE cette séance : un
       * support retravaillé la veille, une consigne, un corrigé individuel.
       * Ces pièces ne rejoignent pas la bibliothèque de la formation — elles
       * appartiennent au dossier de cette séance, et l'organisme y accède
       * comme preuve Qualiopi.
       *
       * Deux choix au moment du dépôt, et ils ne sont pas décoratifs :
       *   — pour toute la séance, ou pour un apprenant nommé ;
       *   — visible tout de suite, ou à la fin de la formation.
       * --------------------------------------------------------------- */
      $unlock_state     = $this->acdc_session_docs_unlock_state( $session );
      $session_documents = $this->acdc_session_documents( (int) $session_id );
      ?>
      <div class="acdc-panel acdc-tportal-sdocs">
        <h3>📎 Documents remis aux apprenants</h3>

        <?php
        /* ACDC 3.25.208 — Le bandeau compte ce qu'il annonce.
           Il affirmait « les supports s'ouvriront à la fin de la séance » alors
           qu'un document déposé en visibilité « tout de suite » était déjà
           consultable : le formateur pouvait croire que rien n'était parti. Le
           message distingue maintenant ce qui est ouvert de ce qui attend. */
        $sdocs_open_now = 0;
        $sdocs_waiting  = 0;
        foreach ( (array) $session_documents as $sdoc_count ) {
          if ( 'immediate' === (string) $sdoc_count->visibility ) {
            $sdocs_open_now++;
          } else {
            $sdocs_waiting++;
          }
        }
        $sdocs_learner_count = count( (array) $learners );
        ?>
        <div class="acdc-sdocs-state">
          <?php if ( ! empty( $unlock_state['unlocked'] ) ) : ?>
            <?php if ( 'manual' === $unlock_state['reason'] ) : ?>
              <span class="acdc-sdocs-badge is-open">Espace débloqué le <?php echo esc_html( mysql2date( 'd/m/Y à H:i', $unlock_state['unlocked_at'] ) ); ?></span>
            <?php else : ?>
              <span class="acdc-sdocs-badge is-open">Séance terminée : les apprenants ont accès à l’ensemble de leurs documents.</span>
            <?php endif; ?>
          <?php else : ?>
            <span class="acdc-sdocs-badge is-locked">
              <?php $sdocs_when = ! empty( $unlock_state['end_label'] ) ? ' (' . $unlock_state['end_label'] . ')' : ''; ?>
              <?php if ( $sdocs_open_now > 0 && $sdocs_waiting > 0 ) : ?>
                <?php echo esc_html( $sdocs_open_now ); ?> document<?php echo $sdocs_open_now > 1 ? 's' : ''; ?> déjà visible<?php echo $sdocs_open_now > 1 ? 's' : ''; ?> ;
                <?php echo esc_html( $sdocs_waiting ); ?> autre<?php echo $sdocs_waiting > 1 ? 's' : ''; ?> s’ouvrira<?php echo $sdocs_waiting > 1 ? 'ont' : ''; ?> à la fin de la séance<?php echo esc_html( $sdocs_when ); ?>.
              <?php elseif ( $sdocs_open_now > 0 ) : ?>
                Tous les documents déposés sont déjà visibles par les apprenants.
              <?php else : ?>
                Les supports s’ouvriront à la fin de la séance<?php echo esc_html( $sdocs_when ); ?>.
              <?php endif; ?>
            </span>

            <?php
            /* Le bouton n'apparaît QUE s'il y a quelque chose à débloquer et
               quelqu'un à prévenir. Il s'affichait sur une séance vide, sans
               document ni apprenant : proposer de débloquer un ensemble vide et
               d'en avertir personne n'est pas une action, c'est un piège. */
            ?>
            <?php if ( $sdocs_waiting > 0 && $sdocs_learner_count > 0 ) : ?>
              <?php
              /* La confirmation ne repose sur AUCUN script : un gestionnaire
                 d'événement en ligne peut être retiré par un filtre de contenu
                 ou une politique de sécurité, et l'action partirait alors au
                 premier clic. Le repli en <details> est du HTML pur : le bouton
                 réel n'existe à l'écran qu'une fois l'avertissement déplié. */
              ?>
              <details class="acdc-sdocs-confirm">
                <summary>Débloquer maintenant</summary>
                <div class="acdc-sdocs-confirm-body">
                  <p>
                    <?php echo esc_html( $sdocs_waiting ); ?> document<?php echo $sdocs_waiting > 1 ? 's' : ''; ?>
                    deviendra<?php echo $sdocs_waiting > 1 ? 'ont' : ''; ?> immédiatement accessible<?php echo $sdocs_waiting > 1 ? 's' : ''; ?>
                    à <?php echo esc_html( $sdocs_learner_count ); ?> apprenant<?php echo $sdocs_learner_count > 1 ? 's' : ''; ?>,
                    qui recevr<?php echo $sdocs_learner_count > 1 ? 'ont' : 'a'; ?> un e-mail. Cette action ne s’annule pas.
                  </p>
                  <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                        onsubmit="return confirm('Débloquer maintenant ? Les apprenants en seront prévenus par e-mail.');">
                    <input type="hidden" name="action" value="acdc_trainer_unlock_session_documents">
                    <?php wp_nonce_field( 'acdc_trainer_unlock_session_documents' ); ?>
                    <input type="hidden" name="session_id" value="<?php echo (int) $session_id; ?>">
                    <button type="submit" class="acdc-button acdc-button-primary" style="height:34px;padding:0 16px;font-size:13px;">Oui, débloquer et prévenir</button>
                  </form>
                </div>
              </details>
            <?php elseif ( $sdocs_waiting > 0 ) : ?>
              <span class="acdc-sdocs-note">Aucun apprenant inscrit sur cette séance : il n’y a personne à prévenir.</span>
            <?php endif; ?>
          <?php endif; ?>
        </div>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="acdc-sdocs-form">
          <input type="hidden" name="action" value="acdc_trainer_upload_session_document">
          <?php wp_nonce_field( 'acdc_trainer_upload_session_document' ); ?>
          <input type="hidden" name="session_id" value="<?php echo (int) $session_id; ?>">

          <div class="acdc-sdocs-grid">
            <label>
              <span>Fichier</span>
              <input type="file" name="document_file" required>
              <small>PDF, JPG, PNG, DOC, DOCX, PPTX — 100 Mo maximum.</small>
            </label>
            <label>
              <span>Intitulé</span>
              <input type="text" name="label" placeholder="Support de la journée 2, corrigé de l’exercice…">
              <small>Laissé vide, le nom du fichier sera utilisé.</small>
            </label>
            <label>
              <span>Destinataire</span>
              <select name="learner_id">
                <option value="0">Tous les apprenants de la séance</option>
                <?php foreach ( (array) $learners as $l ) :
                  $l_last = ! empty( $l->usage_last_name ) ? $l->usage_last_name : $l->last_name; ?>
                  <option value="<?php echo (int) $l->id; ?>"><?php echo esc_html( trim( $l->first_name . ' ' . $l_last ) ); ?></option>
                <?php endforeach; ?>
              </select>
              <small>Un document nominatif n’est visible que par la personne désignée.</small>
            </label>
            <label>
              <span>Visibilité</span>
              <select name="visibility">
                <option value="unlock">À la fin de la formation</option>
                <option value="immediate">Tout de suite</option>
              </select>
              <small>Une consigne pour demain part tout de suite ; un corrigé attend.</small>
            </label>
          </div>

          <label class="acdc-sdocs-notify">
            <input type="checkbox" name="notify" value="1">
            Prévenir les apprenants par e-mail (uniquement pour un document visible tout de suite).
          </label>

          <div style="margin-top:14px;">
            <button type="submit" class="acdc-button acdc-button-primary" style="height:40px;padding:0 24px;">Déposer le document</button>
          </div>
        </form>

        <?php if ( empty( $session_documents ) ) : ?>
          <div class="acdc-empty" style="margin-top:18px;padding:18px 16px;background:#f7f9fc;border:1px dashed #d6dbe4;border-radius:10px;color:#5a6577;font-size:13px;text-align:center;">Aucun document déposé sur cette séance.</div>
        <?php else : ?>
          <table class="acdc-table" style="margin-top:18px;">
            <thead><tr><th>Document</th><th>Destinataire</th><th>Visibilité</th><th>Déposé le</th><th></th></tr></thead>
            <tbody>
            <?php foreach ( $session_documents as $sdoc ) :
              $download_url = wp_nonce_url(
                admin_url( 'admin-post.php?action=acdc_trainer_download_session_document&document_id=' . (int) $sdoc->id ),
                'acdc_trainer_download_session_document_' . (int) $sdoc->id
              );
              $may_delete = $this->acdc_session_document_trainer_may_delete( $sdoc, $session ) && (int) $sdoc->trainer_id === (int) $trainer_id;
              $target     = 'Toute la séance';
              if ( (int) $sdoc->learner_id > 0 ) {
                foreach ( (array) $learners as $l ) {
                  if ( (int) $l->id === (int) $sdoc->learner_id ) {
                    $target = trim( $l->first_name . ' ' . ( ! empty( $l->usage_last_name ) ? $l->usage_last_name : $l->last_name ) );
                    break;
                  }
                }
              }
            ?>
              <tr>
                <td><a href="<?php echo esc_url( $download_url ); ?>"><?php echo esc_html( $this->acdc_session_document_label( $sdoc ) ); ?></a></td>
                <td><?php echo esc_html( $target ); ?></td>
                <td><?php echo 'immediate' === (string) $sdoc->visibility ? 'Tout de suite' : 'À la fin de la formation'; ?></td>
                <td><?php echo esc_html( ! empty( $sdoc->created_at ) ? mysql2date( 'd/m/Y à H:i', $sdoc->created_at ) : '—' ); ?></td>
                <td>
                  <?php if ( $may_delete ) : ?>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acdc_trainer_delete_session_document&document_id=' . (int) $sdoc->id ), 'acdc_trainer_delete_session_document_' . (int) $sdoc->id ) ); ?>"
                       onclick="return confirm('Retirer ce document ?');" style="color:#b03030;">Retirer</a>
                  <?php else : ?>
                    <span style="color:#9ba8b5;font-size:12px;" title="La séance est terminée : le document est devenu une pièce du dossier.">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <p style="margin:10px 0 0;color:#5a6577;font-size:12px;">Un document déposé peut être retiré tant que la séance n’est pas terminée. Ensuite, il fait partie du dossier de formation : seul l’organisme peut encore intervenir.</p>
        <?php endif; ?>
      </div>
      <style>
        .acdc-tportal-sdocs h3{margin:0 0 14px;color:#1E4777;font-size:18px}
        .acdc-tportal-sdocs .acdc-sdocs-state{margin-bottom:16px}
        .acdc-tportal-sdocs .acdc-sdocs-badge{display:inline-block;padding:6px 12px;border-radius:8px;font-size:13px}
        .acdc-tportal-sdocs .acdc-sdocs-badge.is-open{background:#e7f4ec;border:1px solid #bcdcc4;color:#1a7d3b}
        .acdc-tportal-sdocs .acdc-sdocs-badge.is-locked{background:#fff8e6;border:1px solid #f0d690;color:#a06b00}
        .acdc-tportal-sdocs .acdc-sdocs-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        .acdc-tportal-sdocs .acdc-sdocs-grid label{display:block}
        .acdc-tportal-sdocs .acdc-sdocs-grid label>span{display:block;font-size:12px;font-weight:600;color:#5a6577;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px}
        .acdc-tportal-sdocs input[type=text],.acdc-tportal-sdocs select,.acdc-tportal-sdocs input[type=file]{width:100%;border:1px solid #d6dbe4;border-radius:8px;padding:9px 12px;font-size:13px;color:#1E4777;box-sizing:border-box;background:#fff}
        .acdc-tportal-sdocs small{display:block;margin-top:4px;color:#9ba8b5;font-size:11px}
        .acdc-tportal-sdocs .acdc-sdocs-notify{display:block;margin-top:14px;font-size:13px;color:#1E4777}
        .acdc-tportal-sdocs .acdc-sdocs-note{display:inline-block;margin-left:10px;font-size:12px;color:#9ba8b5}
        .acdc-tportal-sdocs .acdc-sdocs-confirm{display:inline-block;margin-left:10px;vertical-align:middle}
        .acdc-tportal-sdocs .acdc-sdocs-confirm>summary{display:inline-block;cursor:pointer;padding:6px 14px;border:1px solid #d6dbe4;border-radius:8px;background:#fff;color:#1E4777;font-size:13px;font-weight:600;list-style:none}
        .acdc-tportal-sdocs .acdc-sdocs-confirm>summary::-webkit-details-marker{display:none}
        .acdc-tportal-sdocs .acdc-sdocs-confirm[open]>summary{background:#f7f9fc}
        .acdc-tportal-sdocs .acdc-sdocs-confirm-body{margin-top:10px;padding:14px 16px;background:#fff8e6;border:1px solid #f0d690;border-radius:10px;max-width:520px}
        .acdc-tportal-sdocs .acdc-sdocs-confirm-body p{margin:0 0 12px;font-size:13px;color:#a06b00;line-height:1.55}
        @media(max-width:600px){.acdc-tportal-sdocs .acdc-sdocs-grid{grid-template-columns:1fr}}
      </style>

      <?php
      /* ---------------------------------------------------------------
       * ACDC 3.24.28 — BLOC B : Bilan post-formation.
       * --------------------------------------------------------------- */
      $report_submitted = ! empty( $session->report_submitted_at );
      ?>
      <div class="acdc-panel acdc-tportal-report">
        <h3>📊 Bilan post-formation</h3>
        <?php if ( $report_submitted ) : ?>
          <div class="acdc-report-submitted">✅ Bilan déposé le <?php echo esc_html( mysql2date( 'd/m/Y à H:i', $session->report_submitted_at ) ); ?> — modifiable ci-dessous.</div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
          <input type="hidden" name="action" value="acdc_trainer_save_session_report">
          <?php wp_nonce_field( 'acdc_trainer_save_session_report' ); ?>
          <input type="hidden" name="session_id" value="<?php echo (int) $session_id; ?>">
          <div class="acdc-report-grid">
            <div class="acdc-report-field">
              <label>Niveau du groupe</label>
              <select name="report_group_level">
                <option value="">— Non renseigné —</option>
                <?php
                $levels = array( 'debutant' => 'Débutant', 'intermediaire' => 'Intermédiaire', 'avance' => 'Avancé', 'heterogene' => 'Hétérogène' );
                foreach ( $levels as $val => $lbl ) :
                  $sel = ( (string) $session->report_group_level === $val ) ? ' selected' : '';
                ?>
                  <option value="<?php echo esc_attr( $val ); ?>"<?php echo $sel; ?>><?php echo esc_html( $lbl ); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="acdc-report-field">
              <label>Objectifs atteints</label>
              <select name="report_objectives_reached">
                <option value="">— Non renseigné —</option>
                <?php
                $obj_opts = array( 'oui' => 'Oui, totalement', 'partiellement' => 'Partiellement', 'non' => 'Non' );
                foreach ( $obj_opts as $val => $lbl ) :
                  $sel = ( (string) $session->report_objectives_reached === $val ) ? ' selected' : '';
                ?>
                  <option value="<?php echo esc_attr( $val ); ?>"<?php echo $sel; ?>><?php echo esc_html( $lbl ); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="acdc-report-field acdc-report-full">
              <label>Incidents / difficultés rencontrés</label>
              <textarea name="report_incidents" placeholder="Décrire tout incident, difficulté technique ou comportementale, retard… Laisser vide si aucun."><?php echo esc_textarea( (string) $session->report_incidents ); ?></textarea>
            </div>
            <div class="acdc-report-field acdc-report-full">
              <label>Recommandations pour la prochaine session</label>
              <textarea name="report_recommendations" placeholder="Ajustements pédagogiques, prérequis à renforcer, rythme, supports…"><?php echo esc_textarea( (string) $session->report_recommendations ); ?></textarea>
            </div>
          </div>
          <div style="margin-top:16px;">
            <button type="submit" class="acdc-button acdc-button-primary" style="height:40px;padding:0 24px;">💾 Enregistrer le bilan</button>
          </div>
        </form>
      </div>
    </div>
    <?php
    return (string) ob_get_clean();
  }

  /**
   * ACDC 3.20.90 — Helper réutilisable : calendrier mensuel d'un formateur.
   *
   * @param array  $availability Structure normalisée.
   * @param int    $month_ts     Timestamp du 1er du mois à afficher.
   * @param bool   $editable     Si true : cellules cliquables avec action AJAX. Sinon, lecture seule.
   * @param string $ajax_url     URL AJAX (utilisée si editable).
   * @param string $nonce        Nonce (utilisée si editable).
   * @param string $base_nav_url URL de base pour les boutons de navigation prev/next mois (par défaut : page courante avec ?view=...).
   * @return string HTML
   */
  private function render_trainer_availability_calendar( $availability, $month_ts, $editable, $ajax_url = '', $nonce = '', $base_nav_url = null, $trainer_id = 0 ) {
    $year  = (int) wp_date( 'Y', $month_ts );
    $month = (int) wp_date( 'n', $month_ts );
    $first_day_of_month = mktime( 0, 0, 0, $month, 1, $year );
    $first_weekday      = (int) wp_date( 'N', $first_day_of_month ); // 1 = lundi
    $days_in_month      = (int) wp_date( 't', $first_day_of_month );
    $today_str          = wp_date( 'Y-m-d' );
    $month_label        = wp_date( 'F Y', $first_day_of_month );

    // URLs prev/next.
    $prev_ts = strtotime( '-1 month', $first_day_of_month );
    $next_ts = strtotime( '+1 month', $first_day_of_month );
    if ( null === $base_nav_url ) {
      $base_nav_url = $this->trainer_portal_page_url( 'availability' );
    }
    $prev_url = add_query_arg( 'month', wp_date( 'Y-m', $prev_ts ), $base_nav_url );
    $next_url = add_query_arg( 'month', wp_date( 'Y-m', $next_ts ), $base_nav_url );

    $weekday_short = array( 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim' );

    // ── ACDC 3.22.7 — Dates de missions dans le calendrier ───────────────
    $mission_days = array(); // 'YYYY-MM-DD' => label mission
    if ( $trainer_id > 0 && isset( $this->trainer_contract_table ) ) {
      global $wpdb;
      $mission_month_start = sprintf( '%04d-%02d-01', $year, $month );
      $mission_month_end   = sprintf( '%04d-%02d-%02d', $year, $month, $days_in_month );
      $missions_in_month   = (array) $wpdb->get_results( $wpdb->prepare(
        "SELECT label, date_start, date_end FROM {$this->trainer_contract_table} WHERE trainer_id = %d AND date_start IS NOT NULL AND date_start <= %s AND (date_end IS NULL OR date_end >= %s)",
        $trainer_id, $mission_month_end, $mission_month_start
      ) );
      foreach ( $missions_in_month as $mc ) {
        $mc_start = strtotime( (string) $mc->date_start );
        $mc_end   = ! empty( $mc->date_end ) ? strtotime( (string) $mc->date_end ) : $mc_start;
        $cur_ts   = max( $mc_start, strtotime( $mission_month_start ) );
        $end_ts   = min( $mc_end,   strtotime( $mission_month_end ) );
        while ( $cur_ts <= $end_ts ) {
          $day_key = gmdate( 'Y-m-d', $cur_ts );
          $mission_days[ $day_key ] = wp_strip_all_tags( html_entity_decode( (string) $mc->label, ENT_QUOTES, 'UTF-8' ) );
          $cur_ts += DAY_IN_SECONDS;
        }
      }
    }

    // ── ACDC 3.24.17 — Sessions assignées dans le calendrier ─────────────
    $session_days = array();
    if ( $trainer_id > 0 ) {
      global $wpdb;
      $sess_month_start = sprintf( '%04d-%02d-01', $year, $month );
      $sess_month_end   = sprintf( '%04d-%02d-%02d', $year, $month, $days_in_month );
      $sql_sessions = "SELECT s.id, s.title, s.start_date, s.end_date, f.title AS formation_title FROM {$this->session_table} s LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id WHERE s.start_date IS NOT NULL AND s.start_date <= %s AND (s.end_date IS NULL OR s.end_date >= %s) AND (s.trainer_id = %d OR EXISTS (SELECT 1 FROM {$this->group_table} g WHERE g.session_id = s.id AND g.trainer_id = %d))";
      $sessions_in_month = (array) $wpdb->get_results( $wpdb->prepare( $sql_sessions, $sess_month_end, $sess_month_start, $trainer_id, $trainer_id ) );
      foreach ( $sessions_in_month as $sc ) {
        $sc_label = ! empty( $sc->formation_title ) ? wp_strip_all_tags( (string) $sc->formation_title ) : wp_strip_all_tags( (string) $sc->title );
        $sc_start = strtotime( (string) $sc->start_date );
        $sc_end   = ! empty( $sc->end_date ) ? strtotime( (string) $sc->end_date ) : $sc_start;
        $cur_ts   = max( $sc_start, strtotime( $sess_month_start ) );
        $end_ts   = min( $sc_end,   strtotime( $sess_month_end ) );
        while ( $cur_ts <= $end_ts ) {
          $day_key = gmdate( 'Y-m-d', $cur_ts );
          $session_days[ $day_key ] = $sc_label;
          $cur_ts += DAY_IN_SECONDS;
        }
      }
    }

    ob_start();
    ?>
    <div class="acdc-cal-toolbar">
      <div class="acdc-cal-nav"><a href="<?php echo esc_url( $prev_url ); ?>" aria-label="Mois précédent">‹</a></div>
      <div class="acdc-cal-month-name"><?php echo esc_html( $month_label ); ?></div>
      <div class="acdc-cal-nav"><a href="<?php echo esc_url( $next_url ); ?>" aria-label="Mois suivant">›</a></div>
    </div>

    <div class="acdc-cal-grid"<?php if ( $editable ) : ?> data-acdc-cal-editable data-ajaxurl="<?php echo esc_attr( $ajax_url ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>"<?php endif; ?>>
      <div class="acdc-cal-header">
        <?php foreach ( $weekday_short as $w ) : ?>
          <div class="acdc-cal-dayname"><?php echo esc_html( $w ); ?></div>
        <?php endforeach; ?>
      </div>

      <?php
      // Cases vides avant le 1er.
      for ( $i = 1; $i < $first_weekday; $i++ ) {
        echo '<div class="acdc-cal-day-empty"></div>';
      }

      for ( $d = 1; $d <= $days_in_month; $d++ ) {
        $date_str = sprintf( '%04d-%02d-%02d', $year, $month, $d );
        $state    = $this->get_trainer_day_availability( $availability, $date_str );
        $is_today = ( $date_str === $today_str );
        $morning_class   = $this->build_availability_cell_class( $state['morning'], $state['is_exception'] );
        $afternoon_class = $this->build_availability_cell_class( $state['afternoon'], $state['is_exception'] );
        $tag_attr = $editable ? 'data-acdc-cell data-date="' . esc_attr( $date_str ) . '"' : '';
        ?>
        <div class="acdc-cal-day" data-acdc-day="<?php echo esc_attr( $date_str ); ?>">
          <div class="acdc-cal-day-num<?php echo $is_today ? ' is-today' : ''; ?>"><span><?php echo (int) $d; ?></span></div>
          <?php if ( isset( $mission_days[ $date_str ] ) ) : ?>
          <div style="font-size:9px;color:#fff;background:#C5A253;border-radius:3px;padding:1px 3px;margin-bottom:2px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;max-width:100%;" title="<?php echo esc_attr( $mission_days[ $date_str ] ); ?>">📋 <?php echo esc_html( mb_strimwidth( $mission_days[ $date_str ], 0, 10, '…' ) ); ?></div>
          <?php endif; ?>
          <?php if ( isset( $session_days[ $date_str ] ) ) : ?>
          <div style="font-size:9px;color:#fff;background:#1E4777;border-radius:3px;padding:1px 3px;margin-bottom:2px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;max-width:100%;" title="<?php echo esc_attr( $session_days[ $date_str ] ); ?>">🎓 <?php echo esc_html( mb_strimwidth( $session_days[ $date_str ], 0, 10, '…' ) ); ?></div>
          <?php endif; ?>
          <div class="acdc-cal-cells">
            <?php if ( $editable ) : ?>
              <button type="button" class="acdc-cal-cell <?php echo esc_attr( $morning_class ); ?>" data-acdc-cell data-date="<?php echo esc_attr( $date_str ); ?>" data-part="morning" title="Matin du <?php echo esc_attr( wp_date( 'd/m/Y', strtotime( $date_str ) ) ); ?>">M</button>
              <button type="button" class="acdc-cal-cell <?php echo esc_attr( $afternoon_class ); ?>" data-acdc-cell data-date="<?php echo esc_attr( $date_str ); ?>" data-part="afternoon" title="Après-midi du <?php echo esc_attr( wp_date( 'd/m/Y', strtotime( $date_str ) ) ); ?>">A</button>
            <?php else : ?>
              <div class="acdc-cal-cell <?php echo esc_attr( $morning_class ); ?>" title="Matin du <?php echo esc_attr( wp_date( 'd/m/Y', strtotime( $date_str ) ) ); ?>">M</div>
              <div class="acdc-cal-cell <?php echo esc_attr( $afternoon_class ); ?>" title="Après-midi du <?php echo esc_attr( wp_date( 'd/m/Y', strtotime( $date_str ) ) ); ?>">A</div>
            <?php endif; ?>
          </div>
        </div>
        <?php
      }
      ?>
    </div>
    <?php
    return (string) ob_get_clean();
  }

  /**
   * ACDC 3.20.83 (legacy) — fonction conservée pour compatibilité, redirige vers le body dashboard + layout.
   * Plus utilisée directement, mais des extensions tierces pourraient l'appeler.
   */
  private function render_trainer_portal_dashboard( $account ) {
    return $this->render_trainer_portal_authenticated_layout( $account, $this->render_trainer_portal_dashboard_body( $account ), 'dashboard' );
  }

  /**
   * ACDC 3.22.7 — Onglet "Mes contrats" du portail formateur.
   * Affiche tous les contrats de sous-traitance du formateur avec statut de signature,
   * bouton de téléchargement du PDF et dates de missions dans un mini-calendrier.
   */
  private function render_trainer_portal_contracts( $account ) {
    $trainer = $this->get_trainer( (int) $account->trainer_id );
    if ( ! $trainer ) {
      return '<div class="acdc-alert acdc-alert-error"><p>Profil formateur introuvable.</p></div>';
    }
    if ( ! isset( $this->trainer_contract_table ) ) {
      return '<div class="acdc-alert acdc-alert-info"><p>Module contrats non disponible.</p></div>';
    }
    global $wpdb;
    $contracts = (array) $wpdb->get_results( $wpdb->prepare(
      "SELECT * FROM {$this->trainer_contract_table} WHERE trainer_id = %d ORDER BY date_start DESC",
      (int) $trainer->id
    ) );
    ob_start();
    ?>
    <h2 style="margin:0 0 6px;color:#1E4777;font-size:22px;">Mes contrats</h2>
    <p style="color:#5a6577;font-size:14px;margin:0 0 20px;">Retrouvez ici tous vos contrats de sous-traitance et leur statut de signature.</p>
    <?php if ( empty( $contracts ) ) : ?>
    <?php
    /* ACDC 3.25.225 — « Mes contrats » restait vide sans dire pourquoi, alors
       que le formateur animait quatre demi-journées et avait reçu son contrat
       en PDF. Un écran vide ne distingue pas « rien n'existe » de « quelque
       chose existe mais je ne le vois pas » — et c'est cette confusion qui a
       fait conclure à une panne. On dit donc ce qui est : aucun contrat de
       sous-traitance n'est enregistré à votre nom, et voici les missions que
       l'organisme vous a bel et bien confiées.
       On ne fabrique surtout pas de contrat à partir des séances : une
       mission planifiée n'est pas un engagement contractuel signé. */
    $missions = $this->trainer_portal_get_upcoming_sessions_list( (int) $trainer->id, 20 );
    ?>
    <div style="background:#f8fafc;border:1px solid #e6ebf2;border-radius:10px;padding:24px;color:#5a6577;">
      <p style="margin:0 0 6px;font-size:14px;color:#1E4777;"><strong>Aucun contrat de sous-traitance n'est enregistré à votre nom.</strong></p>
      <p style="margin:0;font-size:13px;">Un contrat apparaît ici dès que l'organisme en établit un depuis votre fiche formateur. Recevoir un contrat par e-mail ne suffit pas : c'est l'enregistrement dans l'application qui alimente cet écran.</p>
      <?php if ( ! empty( $missions ) ) : ?>
        <p style="margin:18px 0 8px;font-size:13px;color:#1E4777;"><strong>Vos missions planifiées</strong> — elles ne valent pas contrat, mais elles montrent ce qui vous est confié :</p>
        <ul style="margin:0;padding-left:18px;font-size:13px;line-height:1.7;">
        <?php foreach ( (array) $missions as $mission ) :
          $mission_date = '';
          if ( ! empty( $mission->start_at ) ) {
            $mission_date = mysql2date( 'd/m/Y H\hi', (string) $mission->start_at );
          } elseif ( ! empty( $mission->start_date ) ) {
            $mission_date = mysql2date( 'd/m/Y', (string) $mission->start_date );
          }
          ?>
          <li><?php echo esc_html( trim( (string) ( $mission->formation_title ?? $mission->title ?? 'Séance' ) ) ); ?><?php echo '' !== $mission_date ? ' — ' . esc_html( $mission_date ) : ''; ?></li>
        <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <?php else : ?>
    <style>
      .acdc-tc-portal-card{background:#fff;border:1px solid #e6ebf2;border-radius:12px;padding:20px;margin-bottom:14px}
      .acdc-tc-portal-card-head{display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap;margin-bottom:12px}
      .acdc-tc-portal-card-title{font-size:15px;font-weight:700;color:#1E4777;flex:1 1 auto}
      .acdc-tc-portal-badge{display:inline-block;font-size:11px;font-weight:600;padding:3px 10px;border-radius:999px}
      .acdc-tc-portal-meta{display:flex;flex-wrap:wrap;gap:16px;font-size:13px;color:#5a6577;margin-bottom:14px}
      .acdc-tc-portal-meta strong{color:#1E4777}
      .acdc-tc-portal-actions{display:flex;gap:10px;flex-wrap:wrap}
      .acdc-tc-portal-cal{background:#f8fafc;border:1px solid #e6ebf2;border-radius:8px;padding:12px 16px;margin-top:12px;font-size:13px;color:#374151}
      .acdc-tc-portal-cal-title{font-weight:600;color:#1E4777;margin-bottom:6px}
      .acdc-tc-portal-cal-bar{display:flex;align-items:center;gap:10px}
      .acdc-tc-portal-cal-icon{font-size:18px}
      .acdc-tc-portal-cal-dates{font-size:13px;color:#374151}
    </style>
    <?php foreach ( $contracts as $c ) :
      $is_signed   = 'signée' === (string) $c->signature_status;
      $is_sent     = 'envoyée' === (string) $c->signature_status;
      /* ACDC 3.25.248 — On ne pointe plus l'adresse du fichier : le dossier des
         contrats est interdit d'accès direct, ce lien rendait 403. Le contrat
         passe par la route du portail, qui vérifie que ce contrat appartient
         bien au formateur connecté. */
      $has_pdf     = ( $is_signed && ! empty( $c->signed_document_url ) ) || ! empty( $c->contract_pdf_url );
      $pdf_url     = $has_pdf ? $this->acdc_trainer_own_contract_url( (int) $c->id, (bool) ( $is_signed && ! empty( $c->signed_document_url ) ) ) : '';
      $badge_col   = $is_signed ? '#1a7d3b' : ( $is_sent ? '#a06b00' : '#5a6577' );
      $badge_bg    = $is_signed ? '#e7f4ec'  : ( $is_sent ? '#fff3d6'  : '#f0f4fa' );
      $badge_lbl   = $is_signed ? '✅ Signé'  : ( $is_sent ? '⏳ Signature en attente' : '⬜ Non signé' );
      $d_start     = ! empty( $c->date_start ) ? date_i18n( 'd/m/Y', strtotime( $c->date_start ) ) : '';
      $d_end       = ! empty( $c->date_end )   ? date_i18n( 'd/m/Y', strtotime( $c->date_end ) )   : '';
    ?>
    <div class="acdc-tc-portal-card">
      <div class="acdc-tc-portal-card-head">
        <div class="acdc-tc-portal-card-title"><?php echo esc_html( $c->label ); ?></div>
        <span class="acdc-tc-portal-badge" style="background:<?php echo esc_attr( $badge_bg ); ?>;color:<?php echo esc_attr( $badge_col ); ?>;"><?php echo esc_html( $badge_lbl ); ?></span>
      </div>
      <div class="acdc-tc-portal-meta">
        <?php if ( ! empty( $c->formation_ref ) ) : ?>
        <div>📚 Formation : <strong><?php echo esc_html( $c->formation_ref ); ?></strong></div>
        <?php endif; ?>
        <?php if ( '' !== $d_start ) : ?>
        <div>📅 Période : <strong><?php echo esc_html( $d_start . ( '' !== $d_end ? ' → ' . $d_end : '' ) ); ?></strong></div>
        <?php endif; ?>
        <div>⏱ Volume : <strong><?php echo esc_html( number_format( (float) $c->nb_heures, 1, ',', ' ' ) . ' H' ); ?></strong></div>
        <div>💶 Montant : <strong><?php echo esc_html( number_format( (float) $c->montant_ht, 2, ',', ' ' ) . ' € HT' ); ?></strong></div>
        <div>📊 Statut règlement : <strong><?php echo esc_html( $c->statut ?: '—' ); ?></strong></div>
      </div>
      <?php if ( '' !== $d_start ) : ?>
      <div class="acdc-tc-portal-cal">
        <div class="acdc-tc-portal-cal-title">📆 Dates de mission</div>
        <div class="acdc-tc-portal-cal-bar">
          <span class="acdc-tc-portal-cal-icon">🗓</span>
          <span class="acdc-tc-portal-cal-dates">
            <?php echo esc_html( 'Du ' . $d_start . ( '' !== $d_end ? ' au ' . $d_end : '' ) ); ?>
            <?php if ( ! empty( $c->nb_heures ) ) : ?>— <?php echo esc_html( number_format( (float) $c->nb_heures, 1, ',', ' ' ) . ' H' ); ?><?php endif; ?>
          </span>
          <?php if ( ! empty( $c->date_start ) ) :
            // Lien Ajouter au calendrier (iCal)
            $ical_start  = gmdate( 'Ymd', strtotime( $c->date_start ) );
            $ical_end    = ! empty( $c->date_end ) ? gmdate( 'Ymd\T235959\Z', strtotime( $c->date_end ) ) : gmdate( 'Ymd\T235959\Z', strtotime( $c->date_start ) );
            $ical_title  = rawurlencode( wp_strip_all_tags( (string) $c->label ) );
            $ical_detail = rawurlencode( wp_strip_all_tags( 'Mission : ' . (string) $c->label . ' — ' . number_format( (float) $c->nb_heures, 1, ',', ' ' ) . ' H' ) );
            $ical_url    = 'data:text/calendar;charset=utf8,' . rawurlencode( "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nSUMMARY:" . wp_strip_all_tags( (string) $c->label ) . "\r\nDTSTART;VALUE=DATE:" . $ical_start . "\r\nDTEND;VALUE=DATE:" . $ical_end . "\r\nDESCRIPTION:" . wp_strip_all_tags( 'Mission de formation : ' . (string) $c->label ) . "\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n" );
          ?>
          <a href="<?php echo $ical_url; ?>" download="mission-<?php echo (int) $c->id; ?>.ics" class="acdc-button acdc-button-soft" style="font-size:12px;height:28px;padding:0 10px;margin-left:12px;">📥 Ajouter au calendrier</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php if ( '' !== $pdf_url ) : ?>
      <div class="acdc-tc-portal-actions" style="margin-top:12px;">
        <a href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener" class="acdc-button acdc-button-primary" style="font-size:13px;">📄 <?php echo $is_signed ? 'Télécharger mon exemplaire signé' : 'Voir le contrat'; ?></a>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    <?php
    return (string) ob_get_clean();
  }
}
