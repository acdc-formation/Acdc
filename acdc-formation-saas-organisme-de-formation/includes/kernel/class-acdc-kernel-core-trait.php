<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Kernel_Core_Trait {


  private function acdc_get_form_state_transient_key( $form_key ) {
    return 'acdc_of_form_state_' . sanitize_key( (string) $form_key ) . '_' . get_current_user_id();
  }

  private function acdc_sanitize_form_state_input( $value ) {
    if ( is_array( $value ) ) {
      $clean = array();
      foreach ( $value as $key => $item ) {
        $clean[ $key ] = $this->acdc_sanitize_form_state_input( $item );
      }
      return $clean;
    }

    return is_scalar( $value ) ? wp_unslash( (string) $value ) : '';
  }

  private function acdc_store_form_state( $form_key, $input, $required_fields = array() ) {
    if ( ! function_exists( 'set_transient' ) || ! is_user_logged_in() ) {
      return;
    }

    set_transient(
      $this->acdc_get_form_state_transient_key( $form_key ),
      array(
        'input'           => $this->acdc_sanitize_form_state_input( $input ),
        'required_fields' => array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $required_fields ) ) ) ),
        'stored_at'       => time(),
      ),
      10 * MINUTE_IN_SECONDS
    );
  }

  private function acdc_consume_form_state( $form_key ) {
    if ( ! function_exists( 'get_transient' ) || ! function_exists( 'delete_transient' ) || ! is_user_logged_in() ) {
      return array();
    }

    $state = get_transient( $this->acdc_get_form_state_transient_key( $form_key ) );
    if ( is_array( $state ) ) {
      delete_transient( $this->acdc_get_form_state_transient_key( $form_key ) );
      return $state;
    }

    return array();
  }



  private function acdc_apply_form_state_to_record( $record, $state ) {
    if ( empty( $state['input'] ) || ! is_array( $state['input'] ) ) {
      return $record;
    }

    if ( ! is_object( $record ) ) {
      $record = (object) array();
    }

    foreach ( $state['input'] as $key => $value ) {
      $record->{$key} = $value;
    }

    return $record;
  }

  private function acdc_get_form_required_fields( $state ) {
    if ( empty( $state['required_fields'] ) || ! is_array( $state['required_fields'] ) ) {
      return array();
    }

    return array_values( array_unique( array_map( 'sanitize_key', $state['required_fields'] ) ) );
  }

  private function acdc_is_field_invalid( $key, $required_fields ) {
    return in_array( sanitize_key( (string) $key ), array_map( 'sanitize_key', (array) $required_fields ), true );
  }

  private function acdc_get_invalid_field_class( $key, $required_fields, $class_name = 'acdc-field-invalid' ) {
    return $this->acdc_is_field_invalid( $key, $required_fields ) ? ' class="' . esc_attr( $class_name ) . '"' : '';
  }

  private function acdc_get_invalid_field_note( $key, $required_fields, $message = 'Champ obligatoire à renseigner.' ) {
    if ( ! $this->acdc_is_field_invalid( $key, $required_fields ) ) {
      return '';
    }

    return '<span class="acdc-field-help acdc-field-help-error">' . esc_html( $message ) . '</span>';
  }

  private function acdc_render_form_validation_style( $scope = '.acdc-form' ) {
    $scope = trim( (string) $scope );
    if ( '' === $scope ) {
      $scope = '.acdc-form';
    }

    echo '<style>'
      . $scope . ' .acdc-field-invalid{border-color:var(--acdc-danger)!important;box-shadow:0 0 0 2px rgba(224,109,109,.12);}'
      . $scope . ' .acdc-field-help-error{display:block;margin-top:6px;color:var(--acdc-danger)!important;font-size:12px;line-height:1.45;}'
      . '</style>';
  }

/**
 * L'identité de l'organisme — le seul endroit qui la connaisse.
 *
 * ACDC 3.25.290. « Je veux que lorsque je changerai le SIRET, le NDA, le nom
 * du dirigeant, tout soit changé partout. » Ce n'était pas le cas : deux
 * fiches portaient la même identité, une cinquantaine de lectures demandaient
 * une clé qui n'existe pas — « siret » là où la fiche enregistre
 * « siret_identification » — et repartaient donc systématiquement sur une
 * valeur écrite en dur, tandis que la facture, le programme de formation et le
 * PDF de résultat de quiz n'ouvraient même pas les réglages.
 *
 * La fiche entreprise fait foi ; la fiche marque ne sert que de secours, champ
 * par champ. La règle des champs vides est dans OrgIdentity : rien n'est
 * inventé, et une étiquette ne survit pas à sa valeur.
 *
 * @return array<string,string>
 */
/**
 * Compte les sollicitations d'une même origine et dit si la limite est franchie.
 *
 * ACDC 3.25.291. La connexion aux portails et l'entrée dans un quiz étaient
 * protégées contre les essais répétés ; les DEMANDES DE RÉINITIALISATION de mot
 * de passe ne l'étaient pas. Une adresse pouvait donc être sollicitée en boucle :
 * la boîte de l'apprenant se remplit, et surtout le domaine expéditeur finit
 * classé en indésirable — ce jour-là, ce ne sont plus les réinitialisations qui
 * n'arrivent pas, ce sont les convocations.
 *
 * Le compteur vit dans un transitoire : il s'efface tout seul, et ne peut donc
 * pas bloquer durablement quelqu'un de légitime.
 *
 * @param string $portee   Ce que l'on compte (« reinit_apprenant »…).
 * @param int    $limite   Nombre de tentatives tolérées sur la fenêtre.
 * @param int    $fenetre  Durée de la fenêtre, en secondes.
 * @return bool True si la limite est DÉJÀ atteinte — l'appelant doit refuser.
 */
/**
 * Applique la conservation à la piste d'audit.
 *
 * ACDC 3.25.292. Deux durées, parce que deux choses différentes vivent dans la
 * même ligne.
 *
 *   — L'ÉVÉNEMENT lui-même relève de la politique déjà déclarée par le projet
 *     pour les journaux d'audit (src/Support/Retention.php, catégorie « audit »).
 *     C'est une preuve : elle doit tenir aussi longtemps que ce qu'elle prouve.
 *   — L'ADRESSE IP et le navigateur sont des données personnelles. Les garder
 *     aussi longtemps que la preuve serait disproportionné : ils servent à
 *     comprendre un incident, ce qui se fait dans les semaines qui suivent, pas
 *     dix ans après. Ils sont donc effacés au bout d'un an, et la ligne reste.
 *
 * Les deux opérations sont bornées et rejouables : les relancer deux fois ne
 * change rien de plus que les relancer une fois.
 *
 * @return array{anonymisees:int,supprimees:int}
 */
private function purge_system_logs() {
  global $wpdb;
  $bilan = array( 'anonymisees' => 0, 'supprimees' => 0 );
  if ( empty( $this->system_log_table ) || ! class_exists( '\\ACDC\\Support\\Retention' ) ) {
    return $bilan;
  }
  if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->system_log_table ) ) !== $this->system_log_table ) {
    return $bilan;
  }

  /* 1. L'origine s'efface au bout d'un an, la ligne reste. */
  $limite_ip = gmdate( 'Y-m-d H:i:s', strtotime( '-1 year', (int) current_time( 'timestamp' ) ) );
  $bilan['anonymisees'] = (int) $wpdb->query(
    $wpdb->prepare(
      "UPDATE {$this->system_log_table} SET ip_address = NULL, user_agent = NULL
        WHERE created_at < %s AND ( ip_address IS NOT NULL OR user_agent IS NOT NULL )",
      $limite_ip
    )
  );

  /* 2. L'événement s'efface à l'échéance de la politique de conservation. */
  $annees = (int) \ACDC\Support\Retention::yearsFor( 'audit' );
  if ( $annees > 0 ) {
    $limite_ligne = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $annees . ' years', (int) current_time( 'timestamp' ) ) );
    $bilan['supprimees'] = (int) $wpdb->query(
      $wpdb->prepare( "DELETE FROM {$this->system_log_table} WHERE created_at < %s", $limite_ligne )
    );
  }

  update_option(
    'acdc_of_system_log_purge_report',
    array(
      'at'          => current_time( 'mysql' ),
      'anonymisees' => $bilan['anonymisees'],
      'supprimees'  => $bilan['supprimees'],
      'annees'      => $annees,
    ),
    false
  );
  return $bilan;
}

/**
 * Les dernières entrées de la piste d'audit, pour l'écran qui les affiche.
 *
 * ACDC 3.25.292. La table existait, elle était alimentée, et RIEN NE LA LISAIT :
 * aucun écran ne l'affichait, nulle part. Un journal que personne ne peut ouvrir
 * n'est pas une traçabilité, c'est une croyance.
 *
 * @param int    $limite
 * @param string $filtre  '' pour tout, 'echec' pour les seules actions échouées.
 * @return array
 */
private function get_system_log_entries( $limite = 100, $filtre = '' ) {
  global $wpdb;
  if ( empty( $this->system_log_table ) ) {
    return array();
  }
  $limite = max( 1, min( 500, (int) $limite ) );
  if ( 'echec' === $filtre ) {
    return (array) $wpdb->get_results(
      $wpdb->prepare(
        "SELECT * FROM {$this->system_log_table} WHERE result_status <> 'success' ORDER BY id DESC LIMIT %d",
        $limite
      )
    );
  }
  return (array) $wpdb->get_results(
    $wpdb->prepare( "SELECT * FROM {$this->system_log_table} ORDER BY id DESC LIMIT %d", $limite )
  );
}

/** Le nombre total d'entrées conservées, et la plus ancienne. */
private function get_system_log_summary() {
  global $wpdb;
  $vide = array( 'total' => 0, 'plus_ancienne' => '' );
  if ( empty( $this->system_log_table ) ) {
    return $vide;
  }
  $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->system_log_table}" );
  if ( $total < 1 ) {
    return $vide;
  }
  return array(
    'total'         => $total,
    'plus_ancienne' => (string) $wpdb->get_var( "SELECT MIN(created_at) FROM {$this->system_log_table}" ),
  );
}

/** L'adresse d'où part la requête, ou « inconnue ». */
private function acdc_adresse_appelante() {
  $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
  return '' !== $ip ? substr( $ip, 0, 45 ) : 'inconnue';
}

/** Le navigateur déclaré, tronqué : il sert à reconnaître un poste, pas à profiler. */
private function acdc_navigateur_appelant() {
  $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '';
  return substr( $ua, 0, 255 );
}

private function acdc_trop_de_tentatives( $portee, $limite = 5, $fenetre = 900 ) {
  $ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : 'inconnue';
  $cle = 'acdc_rl_' . sanitize_key( (string) $portee ) . '_' . md5( $ip );
  $n   = (int) get_transient( $cle );
  if ( $n >= (int) $limite ) {
    return true;
  }
  set_transient( $cle, $n + 1, (int) $fenetre );
  return false;
}

private function acdc_org_identity() {
  static $identite = null;
  if ( null !== $identite ) {
    return $identite;
  }
  $fiche = method_exists( $this, 'get_company_profile_options' )
    ? $this->get_company_profile_options()
    : get_option( 'acdc_of_company_profile', array() );
  $marque = method_exists( $this, 'get_branding_options' )
    ? $this->get_branding_options()
    : get_option( 'acdc_of_branding', array() );

  $identite = \ACDC\Support\OrgIdentity::fromOptions( $fiche, $marque );
  return $identite;
}

/**
 * Le nom qui s'affiche comme expéditeur d'un e-mail de l'organisme.
 *
 * ACDC 3.25.290. Deux envois lisaient « enterprise_contact_name », une clé qui
 * n'existe pas dans la fiche : ils retombaient donc toujours sur le nom du
 * site WordPress. On nomme la personne qui signe si elle est renseignée, sinon
 * l'organisme, et le nom du site en dernier recours seulement.
 *
 * @return string
 */
private function acdc_expediteur_organisme() {
  $id = $this->acdc_org_identity();
  if ( '' !== $id['signataire'] ) {
    return $id['signataire'];
  }
  if ( '' !== $id['raison_sociale'] ) {
    return $id['raison_sociale'];
  }
  return (string) get_bloginfo( 'name' );
}

private function acdc_get_transactional_email_branding() {
    $branding = method_exists( $this, 'get_branding_options' ) ? $this->get_branding_options() : array();
    $marketing_settings = get_option( 'acdc_of_marketing_settings', array() );
    if ( ! is_array( $marketing_settings ) ) {
      $marketing_settings = array();
    }

    /* ACDC 3.25.290 — L'en-tête et le pied de TOUS les e-mails transactionnels
       lisaient la fiche marque, avec le téléphone et l'adresse de l'organisme
       écrits en dur en repli. Un e-mail est un document comme un autre : il
       part de la même identité que les conventions et les factures. */
    $identite = $this->acdc_org_identity();

    $company_name = '' !== $identite['raison_sociale'] ? sanitize_text_field( $identite['raison_sociale'] ) : '';
    $logo_url = ! empty( $branding['logo_url'] ) ? esc_url( (string) $branding['logo_url'] ) : '';
    $website = '' !== $identite['site'] ? esc_url_raw( $identite['site'] ) : home_url( '/' );
    $phone = sanitize_text_field( $identite['telephone'] );
    /* L'expéditeur marketing prime : c'est une adresse d'envoi, pas une
       identité — elle peut légitimement différer de l'adresse de contact. */
    $email = ! empty( $marketing_settings['sender_email'] ) ? sanitize_email( (string) $marketing_settings['sender_email'] ) : '';
    if ( '' === $email && '' !== $identite['email'] ) {
      $email = sanitize_email( $identite['email'] );
    }
    $reply_to = ! empty( $marketing_settings['reply_to'] ) ? sanitize_email( (string) $marketing_settings['reply_to'] ) : $email;
    $sender_name = ! empty( $marketing_settings['sender_name'] ) ? sanitize_text_field( (string) $marketing_settings['sender_name'] ) : $company_name;
    $address_line = \ACDC\Support\OrgIdentity::addressLine( $identite, ' — ' );

    return array(
      'company_name'   => $company_name,
      'subtitle'       => 'AZUR COMPÉTENCES DÉVELOPPEMENT & CONSEIL',
      'logo_url'       => $logo_url,
      'website'        => $website,
      'website_label'  => preg_replace( '#^https?://#', '', rtrim( $website, '/' ) ),
      'phone'          => $phone,
      'email'          => $email,
      'reply_to'       => $reply_to,
      'sender_name'    => $sender_name,
      'address_line'   => $address_line,
    );
  }

private function acdc_build_transactional_email_html( $args = array() ) {
    $branding = $this->acdc_get_transactional_email_branding();
    $greeting_name = isset( $args['greeting_name'] ) ? trim( wp_strip_all_tags( (string) $args['greeting_name'] ) ) : '';
    $intro_html = isset( $args['intro_html'] ) ? (string) $args['intro_html'] : '';
    $body_html = isset( $args['body_html'] ) ? (string) $args['body_html'] : '';
    $summary_title = isset( $args['summary_title'] ) ? trim( wp_strip_all_tags( (string) $args['summary_title'] ) ) : '';
    $summary_rows = ! empty( $args['summary_rows'] ) && is_array( $args['summary_rows'] ) ? $args['summary_rows'] : array();
    $footer_notice = isset( $args['footer_notice'] ) ? trim( wp_strip_all_tags( (string) $args['footer_notice'] ) ) : 'Cet e-mail a été envoyé par ACDC Formation. Vos données sont traitées conformément au RGPD.';

    /* ACDC 3.25.272 — CE GABARIT ÉTAIT ILLISIBLE SUR UN TÉLÉPHONE.
     *
     * Relevé sur l'iPhone de David, captures à l'appui : le titre passait sur
     * deux lignes, le sous-titre sur quatre, et son code de vérification se
     * coupait en deux — « 7 6 9 7 » puis « 6 0 ». Un code qu'on doit recopier
     * ne doit jamais passer à la ligne.
     *
     * La cause n'était pas mystérieuse : 860 px de large, 56 px de marge de
     * chaque côté, un titre à 34 px, un corps à 19 px. Sur un écran de 390 px,
     * il restait environ 250 px utiles. Et le document n'avait ni en-tête, ni
     * balise viewport, ni la moindre règle d'adaptation : rien n'était prévu
     * pour un petit écran, nulle part.
     *
     * LE PARTI PRIS : les tailles écrites en ligne sont celles du MOBILE, et
     * une règle d'adaptation les agrandit sur grand écran. C'est l'inverse de
     * ce qu'on fait d'habitude, et c'est volontaire : plusieurs messageries
     * suppriment les feuilles de style. Si cela arrive, il reste la version
     * mobile — lisible partout — au lieu de la version bureau, illisible sur un
     * téléphone. Le mode dégradé doit rester lisible.
     */
    $summary_html = '';
    if ( $summary_title && ! empty( $summary_rows ) ) {
      $summary_html .= '<div style="background:#f7f7f8;border-radius:10px;padding:18px 20px;margin:0 0 24px;">';
      $summary_html .= '<div style="font-size:13px;font-weight:800;letter-spacing:0.4px;text-transform:uppercase;color:#1f335d;margin-bottom:12px;">' . esc_html( $summary_title ) . '</div>';
      $first = true;
      foreach ( $summary_rows as $row ) {
        $label = ! empty( $row['label'] ) ? trim( wp_strip_all_tags( (string) $row['label'] ) ) : '';
        $value = isset( $row['value'] ) ? trim( wp_strip_all_tags( (string) $row['value'] ) ) : '';
        if ( '' === $label ) {
          continue;
        }
        /* Le récapitulatif était une table à deux colonnes dont la première
           occupait 38 % : sur un téléphone, le libellé tenait dans 95 px et la
           valeur dans 155 px. « Intelligence artificielle » y passait sur deux
           lignes, et un libellé court se retrouvait centré à côté d'une valeur
           de cinq lignes — la ligne ne voulait plus rien dire.
           Empilé, le libellé au-dessus de sa valeur, il n'y a plus de largeur à
           partager : c'est lisible sur un téléphone ET sur un écran large. */
        $summary_html .= '<div style="padding:' . ( $first ? '0' : '12px' ) . ' 0 12px;border-top:' . ( $first ? 'none' : '1px solid #e6e8ee' ) . ';">'
          . '<div style="font-size:12px;line-height:1.4;text-transform:uppercase;letter-spacing:0.4px;color:#66738b;">' . esc_html( $label ) . '</div>'
          . '<div style="font-size:15px;line-height:1.5;font-weight:700;color:#222;">' . esc_html( '' !== $value ? $value : '—' ) . '</div>'
          . '</div>';
        $first = false;
      }
      $summary_html .= '</div>';
    }

    $styles = 'body{margin:0;padding:0;width:100%!important;}'
      . 'img{border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;}'
      . 'a{color:#c59a2a;}'
      /* LE CORPS DU MESSAGE N'EST PAS ÉCRIT ICI.
         Chaque envoi compose son propre contenu — une trentaine d'endroits dans
         le plugin — et beaucoup posent des tailles calibrées pour un grand
         écran : 18, 19 px, parfois davantage. Les corriger un par un serait
         long, et surtout le prochain paragraphe écrit ailleurs recommencerait.
         On plafonne donc à la source, pour le petit écran seulement. Une règle
         marquée `!important` l'emporte sur un style posé en ligne : c'est le
         seul moyen de reprendre la main sur du contenu qu'on ne maîtrise pas. */
      . '@media only screen and (max-width:599px){'
      . '.acdc-mail-body p,.acdc-mail-body li,.acdc-mail-body td,.acdc-mail-body div{font-size:15px!important;line-height:1.6!important;}'
      . '.acdc-mail-body h1{font-size:20px!important;line-height:1.3!important;}'
      . '.acdc-mail-body h2{font-size:17px!important;line-height:1.35!important;}'
      . '.acdc-mail-body h3{font-size:15px!important;line-height:1.4!important;}'
      . '.acdc-mail-body img{max-width:100%!important;height:auto!important;}'
      /* Une adresse ou un lien long ne doit pas élargir tout le message : il
         vaut mieux le couper que de faire défiler la page entière. */
      . '.acdc-mail-body a{word-break:break-word!important;}'
      /* Les tableaux de mise en page restent, mais ils cessent d'imposer une
         largeur que l'écran n'a pas. */
      . '.acdc-mail-body table{width:100%!important;max-width:100%!important;}'
      . '}'
      /* Au-delà de 600 px on retrouve le confort d'origine — jamais en dessous. */
      . '@media only screen and (min-width:600px){'
      . '.acdc-mail-shell{padding:24px!important;}'
      . '.acdc-mail-head{padding:40px 48px 20px!important;}'
      . '.acdc-mail-body{padding:8px 48px 44px!important;}'
      . '.acdc-mail-foot{padding:24px 40px!important;}'
      . '.acdc-mail-title{font-size:30px!important;}'
      . '.acdc-mail-subtitle{font-size:15px!important;}'
      . '.acdc-mail-text{font-size:17px!important;}'
      . '}';

    return '<!DOCTYPE html><html lang="fr"><head>'
      . '<meta charset="utf-8">'
      /* Sans cette ligne, iOS ne sait pas que la page est prévue pour la
         largeur de l'écran : il la met à l'échelle, et tout devient énorme ou
         minuscule selon l'élément le plus large. */
      . '<meta name="viewport" content="width=device-width,initial-scale=1">'
      . '<meta name="x-apple-disable-message-reformatting">'
      . '<meta name="color-scheme" content="light only">'
      . '<title>' . esc_html( $branding['company_name'] ) . '</title>'
      . '<style>' . $styles . '</style>'
      . '</head>'
      . '<body style="margin:0;padding:0;background:#f3f4f6;">'
      . '<div class="acdc-mail-shell" style="margin:0;padding:12px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#24324a;">'
      . '<div style="max-width:860px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;">'
      . '<div class="acdc-mail-head" style="padding:24px 20px 12px;text-align:center;">'
      . ( $branding['logo_url'] ? '<div style="margin-bottom:14px;"><img src="' . esc_url( $branding['logo_url'] ) . '" alt="' . esc_attr( $branding['company_name'] ) . '" width="110" style="max-width:110px;height:auto;"></div>' : '' )
      . '<div class="acdc-mail-title" style="font-size:22px;line-height:1.25;font-weight:800;color:#1f335d;">' . esc_html( $branding['company_name'] ) . '</div>'
      /* Le sous-titre passait sur quatre lignes : en majuscules espacées, il
         est le plus large de tout le message. Il perd son interlettrage et
         redescend à une taille qui tient sur une ou deux lignes. */
      . '<div class="acdc-mail-subtitle" style="font-size:12px;line-height:1.4;color:#45567b;text-transform:uppercase;">' . esc_html( $branding['subtitle'] ) . '</div>'
      . '</div>'
      . '<div class="acdc-mail-body" style="padding:8px 20px 28px;">'
      . '<p class="acdc-mail-text" style="font-size:15px;line-height:1.6;margin:0 0 18px;">Bonjour <strong>' . esc_html( $greeting_name ? $greeting_name : '—' ) . '</strong>,</p>'
      . $intro_html
      . $summary_html
      . $body_html
      . '<p class="acdc-mail-text" style="font-size:15px;line-height:1.6;margin:24px 0 0;">Si vous avez des questions, vous pouvez nous contacter directement à <a href="mailto:' . esc_attr( $branding['email'] ) . '" style="color:#c59a2a;text-decoration:underline;word-break:break-word;">' . esc_html( $branding['email'] ) . '</a> ou au <strong>' . esc_html( $branding['phone'] ) . '</strong>.</p>'
      . '<p class="acdc-mail-text" style="font-size:15px;line-height:1.6;margin:24px 0 0;">Cordialement,<br><strong>L’équipe ' . esc_html( $branding['company_name'] ) . '</strong></p>'
      . '</div>'
      . '<div class="acdc-mail-foot" style="padding:18px 20px;text-align:center;border-top:1px solid #e3e7ef;background:#fafafa;color:#6a7488;font-size:12px;line-height:1.6;">'
      . esc_html( $branding['phone'] ) . ' · <a href="mailto:' . esc_attr( $branding['email'] ) . '" style="color:#5579bf;text-decoration:underline;word-break:break-word;">' . esc_html( $branding['email'] ) . '</a> · <a href="' . esc_url( $branding['website'] ) . '" style="color:#5579bf;text-decoration:underline;word-break:break-word;">' . esc_html( $branding['website_label'] ) . '</a>'
      . ( $branding['address_line'] ? '<br>' . esc_html( $branding['address_line'] ) : '' )
      . '<br>' . esc_html( $footer_notice )
      . '</div></div></div>'
      . '</body></html>';
  }

private function acdc_get_transactional_email_headers( $args = array() ) {
    $branding = $this->acdc_get_transactional_email_branding();
    $headers = array( 'Content-Type: text/html; charset=UTF-8' );
    if ( ! empty( $branding['email'] ) ) {
      $headers[] = 'From: ' . $branding['sender_name'] . ' <' . $branding['email'] . '>';
    }
    if ( ! empty( $branding['reply_to'] ) ) {
      $headers[] = 'Reply-To: ' . $branding['reply_to'];
    }
    $map = array(
      'source_module' => 'X-ACDC-Source-Module',
      'source_action' => 'X-ACDC-Source-Action',
      'related_entity_type' => 'X-ACDC-Related-Entity-Type',
      'related_entity_id' => 'X-ACDC-Related-Entity-Id',
      'related_sub_id' => 'X-ACDC-Related-Sub-Id',
      'email_category' => 'X-ACDC-Email-Category',
      'email_audience' => 'X-ACDC-Email-Audience',
    );
    foreach ( $map as $key => $header_name ) {
      if ( isset( $args[ $key ] ) && '' !== (string) $args[ $key ] ) {
        $headers[] = $header_name . ': ' . sanitize_text_field( (string) $args[ $key ] );
      }
    }
    if ( ! empty( $args['extra_headers'] ) && is_array( $args['extra_headers'] ) ) {
      $headers = array_merge( $headers, array_values( $args['extra_headers'] ) );
    }
    return $headers;
  }

/**
 * ACDC 3.25.229 — Le point de passage commun accepte des pièces jointes.
 *
 * Il n'en acceptait pas, si bien que tout envoi devant joindre un document
 * devait contourner ce point de passage et appeler wp_mail directement — donc
 * échapper au garde-fou du mode recette et à l'attribution d'archive. Ajouter
 * le paramètre ici, c'est refermer la porte plutôt que d'en ouvrir une autre.
 */
/**
 * ACDC 3.25.246 — LA PORTE D'ENTRÉE PUBLIQUE DES E-MAILS.
 *
 * Le module de signature et celui d'émargement sont des CLASSES AUTONOMES :
 * ils ne composent pas le plugin et ne peuvent donc pas appeler la méthode
 * privée ci-dessous. Faute de porte, ils écrivaient leur HTML à la main et
 * appelaient wp_mail() directement — d'où des e-mails sans en-tête, sans logo
 * et sans pied de page, reçus par les mêmes personnes que les autres.
 *
 * Le défaut de mise en forme n'était que le symptôme visible. Le vrai : la
 * méthode privée est AUSSI le seul endroit qui consulte le mode recette. Tout
 * envoi qui la contourne part même lorsque la recette est censée retenir le
 * courrier — précisément le trou par lequel une analyse du besoin nominative
 * était partie vers un domaine étranger.
 *
 * Une seule porte, donc, et publique : la mise en forme et le garde-fou
 * voyagent ensemble. On ne peut plus obtenir l'une sans l'autre.
 */
public function acdc_send_branded_email( $to, $subject, $template_args = array(), $header_args = array(), $attachments = array() ) {
  return $this->acdc_send_transactional_email( $to, $subject, $template_args, $header_args, $attachments );
}

private function acdc_send_transactional_email( $to, $subject, $template_args = array(), $header_args = array(), $attachments = array() ) {
    $to = sanitize_email( (string) $to );
    if ( '' === $to || ! is_email( $to ) ) {
      return false;
    }

    /* ACDC 3.25.218 — LE MODE RECETTE PROTÈGE ENFIN TOUTES LES PORTES.
       Il n'arbitrait que les envois du workflow. Toutes les autres sorties —
       analyses du besoin, convocations, invitations de quiz, demandes de
       signature — passaient directement à wp_mail sans jamais consulter la
       liste des destinataires autorisés. Le filet ne couvrait donc qu'une
       porte sur dix, et l'on croyait la maison fermée.
       La recette l'a démontré de la pire façon : une analyse du besoin
       NOMINATIVE est partie vers un domaine étranger, à cause d'une adresse
       mal saisie sur une fiche apprenant. Le mode recette était décoché ce
       jour-là — mais coché, il n'aurait rien empêché non plus, puisque ce
       chemin ne le consultait pas.
       Le contrôle est donc remonté ici, au point de passage commun. Un envoi
       refusé est journalisé nommément : un blocage silencieux ferait chercher
       pendant des heures un e-mail qui n'est jamais parti. */
    /* ACDC 3.25.295 — UNE SEULE EXCEPTION, ET ELLE NE SORT PAS DE LA MAISON.
       Le mode recette existe pour qu'aucun courrier n'atteigne un TIERS — un
       apprenant, un financeur, un formateur. L'exploitant qui reçoit une alerte
       sur ses propres sauvegardes n'est pas un tiers, et cette alerte est
       précisément celle qui doit percer le silence : la faire retenir par la
       recette reviendrait à taire l'avertissement qui prévient qu'on ne
       s'avertit plus.
       L'exception est donc doublement fermée : il faut que l'appelant l'ait
       demandée explicitement ET que le destinataire soit l'adresse
       d'administration du site. Aucune combinaison ne permet d'atteindre
       quelqu'un d'autre. */
    $alerte_exploitant = ! empty( $header_args['alerte_exploitant'] )
      && strtolower( $to ) === strtolower( (string) get_option( 'admin_email' ) );

    if ( ! $alerte_exploitant && method_exists( $this, 'acdc_wf_may_send_to' ) && ! $this->acdc_wf_may_send_to( $to ) ) {
      $this->insert_system_log( array(
        'log_level'   => 'warning',
        'event_type'  => 'email_blocked_test_mode',
        'action_key'  => 'transactional_email',
        'object_type' => 'email',
        'object_id'   => 0,
        'message'     => 'Envoi bloqué par le mode recette : destinataire hors liste autorisée.',
        'context_json' => array(
          'destinataire' => $to,
          'objet'        => wp_strip_all_tags( (string) $subject ),
        ),
      ) );
      return false;
    }

    /* ACDC 3.25.246 — Un gabarit propre peut passer par la porte commune.
       L'invitation à signer a sa mise en page à elle — bandeau d'expiration,
       deux boutons, consigne smartphone — et elle est réussie : la couler dans
       le gabarit générique la ferait régresser. Mais elle appelait wp_mail
       directement, donc elle échappait au mode recette, alors que c'est
       l'e-mail qui porte le LIEN DE SIGNATURE. On accepte donc un HTML déjà
       composé : la mise en forme reste libre, le garde-fou devient obligatoire. */
    $html = ( ! empty( $template_args['raw_html'] ) && is_string( $template_args['raw_html'] ) )
      ? $template_args['raw_html']
      : $this->acdc_build_transactional_email_html( $template_args );
    $headers = $this->acdc_get_transactional_email_headers( $header_args );
    return wp_mail( $to, wp_strip_all_tags( (string) $subject ), $html, $headers, array_values( (array) $attachments ) );
  }

  /* ACDC 3.23.11 — Récurrences WP cron custom pour la veille IA. */
  public function acdc_register_cron_schedules( $schedules ) {
    if ( ! isset( $schedules['acdc_6hours'] ) ) {
      $schedules['acdc_6hours'] = array(
        'interval' => 6 * HOUR_IN_SECONDS,
        'display'  => 'Toutes les 6 heures (ACDC)',
      );
    }
    /* ACDC 3.25.185 — Le rappel d'émargement se pose 30 minutes avant une
       demi-journée : une cadence horaire le manquerait d'une demi-heure. */
    if ( ! isset( $schedules['acdc_quarter_hour'] ) ) {
      $schedules['acdc_quarter_hour'] = array(
        'interval' => 15 * MINUTE_IN_SECONDS,
        'display'  => 'Tous les quarts d’heure (ACDC)',
      );
    }
    if ( ! isset( $schedules['acdc_fortnightly'] ) ) {
      $schedules['acdc_fortnightly'] = array(
        'interval' => 14 * DAY_IN_SECONDS,
        'display'  => 'Tous les 15 jours (ACDC)',
      );
    }
    if ( ! isset( $schedules['monthly'] ) ) {
      $schedules['monthly'] = array(
        'interval' => 30 * DAY_IN_SECONDS,
        'display'  => 'Mensuel (ACDC)',
      );
    }
    // ACDC 3.25.115 — déclarer weekly (auto-suffisance si un filtre la retire).
    if ( ! isset( $schedules['weekly'] ) ) {
      $schedules['weekly'] = array(
        'interval' => 7 * DAY_IN_SECONDS,
        'display'  => 'Une fois par semaine (ACDC)',
      );
    }
    return $schedules;
  }

  /* -----------------------------------------------------------------------
   * Veille IA — fréquence de collecte configurable
   * ----------------------------------------------------------------------- */

  public function _get_watch_collect_recurrence() {
    $freq = get_option( 'acdc_of_watch_collect_frequency', 'weekly' );
    $map  = array(
      'daily'       => 'daily',
      'weekly'      => 'weekly',
      'fortnightly' => 'acdc_fortnightly',
      'monthly'     => 'monthly',
    );
    return isset( $map[ $freq ] ) ? $map[ $freq ] : 'weekly';
  }

  public function _reschedule_watch_collect_cron() {
    $hook = 'acdc_of_watch_collect_cron';
    $freq = (string) get_option( 'acdc_of_watch_collect_frequency', 'weekly' );
    $day  = (int) get_option( 'acdc_of_watch_collect_day', 1 ); // 1=lundi
    $hour = (int) get_option( 'acdc_of_watch_collect_hour', 8 );

    // Déprogrammer le cron existant
    $timestamp = wp_next_scheduled( $hook );
    if ( $timestamp ) {
      wp_unschedule_event( $timestamp, $hook );
    }

    // Calculer le prochain déclenchement selon jour + heure configurés
    $recurrence = $this->_get_watch_collect_recurrence();
    $now        = current_time( 'timestamp' );
    $target     = mktime( $hour, 0, 0, (int) date( 'n', $now ), (int) date( 'j', $now ), (int) date( 'Y', $now ) );

    // Trouver le prochain jour de semaine configuré si fréquence weekly/fortnightly
    if ( in_array( $freq, array( 'weekly', 'fortnightly' ), true ) ) {
      $current_dow = (int) date( 'N', $now ); // 1=lundi, 7=dimanche
      $target_dow  = $day === 0 ? 7 : $day;  // Convertir 0=dimanche en 7
      $diff_days   = ( $target_dow - $current_dow + 7 ) % 7;
      if ( 0 === $diff_days && $target <= $now ) {
        $diff_days = 7;
      }
      $target += $diff_days * DAY_IN_SECONDS;
    } elseif ( $target <= $now ) {
      // daily/monthly : si l'heure est passée aujourd'hui, décaler au lendemain
      $target += DAY_IN_SECONDS;
    }

    wp_schedule_event( $target, $recurrence, $hook );
  }

  private function maybe_schedule_runtime_hook( $hook, $recurrence, $offset = 0 ) {
    if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
      return false;
    }

    if ( wp_next_scheduled( $hook ) ) {
      return false;
    }

    wp_schedule_event( time() + max( 0, (int) $offset ), $recurrence, $hook );
    return true;
  }  public function maybe_repair_runtime_state() {
    if ( function_exists( 'wp_installing' ) && wp_installing() ) {
      return;
    }

    $repaired = array();

    if ( $this->maybe_schedule_runtime_hook( 'acdc_of_surveys_cron_dispatches', 'hourly', 300 ) ) {
      $repaired[] = 'Planification des envois automatiques restaurée';
    }
    if ( $this->maybe_schedule_runtime_hook( 'acdc_of_surveys_cron_reminders', 'hourly', 600 ) ) {
      $repaired[] = 'Planification des relances automatiques restaurée';
    }
    if ( $this->maybe_schedule_runtime_hook( 'acdc_of_surveys_cron_expirations', 'hourly', 900 ) ) {
      $repaired[] = 'Planification des expirations restaurée';
    }
    if ( $this->maybe_schedule_runtime_hook( 'acdc_of_surveys_cron_action_followups', 'hourly', 1200 ) ) {
      $repaired[] = 'Planification du suivi interne restaurée';
    }
    /* ACDC 3.20.93 — Cron quotidien des alertes Qualiopi.
       Offset 1500s pour étaler par rapport aux autres crons existants. */
    if ( $this->maybe_schedule_runtime_hook( 'acdc_of_qualiopi_alerts_cron', 'daily', 1500 ) ) {
      $repaired[] = 'Planification des alertes Qualiopi restaurée';
    }

    /* ACDC 3.21.05 — Cron quotidien de clôture automatique des sessions terminées.
       Offset 3600s (1h) pour s'assurer de tourner après tous les autres crons. */
    if ( $this->maybe_schedule_runtime_hook( 'acdc_of_session_close_cron', 'daily', 3600 ) ) {
      $repaired[] = 'Planification de la clôture automatique des sessions restaurée';
    }

    /* ACDC 3.21.49 — Cron quotidien d'envoi des convocations J-7 et rappels J-1.
       Offset 4500s pour tourner après tous les crons existants. */
    if ( $this->maybe_schedule_runtime_hook( 'acdc_of_convocation_cron', 'daily', 4500 ) ) {
      $repaired[] = 'Planification des convocations automatiques restaurée';
    }

    /* ACDC 3.21.64 — Cron quotidien d'envoi du test de positionnement 48h avant séance.
       Offset 5400s pour tourner après le cron convocation. */
    if ( $this->maybe_schedule_runtime_hook( 'acdc_of_positioning_test_cron', 'daily', 5400 ) ) {
      $repaired[] = 'Planification du test de positionnement automatique restaurée';
    }

    /* ACDC 3.23.31 — Crons veille IA : fréquence configurable, analyse 6h, digest hebdo, rappel mensuel. */
    $collect_recurrence = $this->_get_watch_collect_recurrence();
    if ( $this->maybe_schedule_runtime_hook( 'acdc_of_watch_collect_cron', $collect_recurrence, 7200 ) ) {
      $repaired[] = 'Planification collecte veille IA restaurée';
    }
    if ( $this->maybe_schedule_runtime_hook( 'acdc_of_watch_analyze_cron', 'acdc_6hours', 10800 ) ) {
      $repaired[] = 'Planification analyse veille IA restaurée';
    }
    if ( $this->maybe_schedule_runtime_hook( 'acdc_of_watch_digest_cron', 'weekly', 0 ) ) {
      $repaired[] = 'Planification digest veille restaurée';
    }
    if ( $this->maybe_schedule_runtime_hook( 'acdc_of_watch_reminder_cron', 'monthly', 0 ) ) {
      $repaired[] = 'Planification rappel veille restaurée';
    }

    // ACDC 3.25.115 — armer aussi sur init (hook wp = front-only, non auto-réparant).
    if ( $this->maybe_schedule_runtime_hook( 'acdc_nad_cron_send_and_relance', 'twicedaily', 1800 ) ) {
      $repaired[] = 'Planification envoi/relance analyses du besoin restaurée';
    }
    if ( $this->maybe_schedule_runtime_hook( 'acdc_of_cron_push_indicators', 'daily', 2100 ) ) {
      $repaired[] = 'Planification push indicateurs SAAS restaurée';
    }
    if ( $this->maybe_schedule_runtime_hook( 'acdc_of_cron_sync_formations', 'daily', 2400 ) ) {
      $repaired[] = 'Planification sync formations Manager restaurée';
    }
    if ( $this->maybe_schedule_runtime_hook( 'acdc_of_absence_alert_cron', 'daily', 2700 ) ) {
      $repaired[] = 'Planification alerte absences restaurée';
    }
    /* ACDC 3.25.185 — Cron d'orchestration du parcours. */
    if ( $this->maybe_schedule_runtime_hook( 'acdc_of_workflow_cron', 'acdc_quarter_hour', 120 ) ) {
      $repaired[] = 'Planification du workflow restaurée';
    }
    // ACDC 3.25.118 — passage automatique des factures impayées en retard (quotidien).
    if ( $this->maybe_schedule_runtime_hook( 'acdc_of_invoices_overdue_cron', 'daily', 3000 ) ) {
      $repaired[] = 'Planification passage factures en retard restaurée';
    }

    if ( ! empty( $repaired ) ) {
      if ( method_exists( $this, 'log_error' ) ) {
        $this->log_error( 'runtime', implode( ' | ', $repaired ) );
      }
      if ( function_exists( 'set_transient' ) ) {
        set_transient(
          'acdc_of_saas_runtime_notice',
          array(
            'time'  => current_time( 'mysql' ),
            'items' => $repaired,
          ),
          DAY_IN_SECONDS
        );
      }
    }
  }
  /**
   * ACDC 3.25.157 — Correspondance page wp-admin masquée → onglet extranet.
   *
   * register_admin_menu() enregistre une trentaine de sous-pages puis les retire
   * aussitôt du menu (remove_submenu_page). Toute redirection vers l'une d'elles
   * est donc un cul-de-sac : l'écriture réussit, et l'utilisateur atterrit sur
   * « Vous n'avez pas l'autorisation d'accéder à cette page ». Le plugin comptait
   * 126 redirections de ce type, écrites à la main dans quatorze fichiers.
   *
   * Seuls figurent ici les slugs dont l'onglet extranet équivalent existe
   * réellement (vérifié dans le répartiteur d'onglets). Les autres — écrans
   * purement techniques comme le système d'icônes ou l'espace signatures —
   * restent volontairement absents : mieux vaut une page wp-admin qu'une
   * redirection vers un onglet inexistant.
   *
   * @return array slug wp-admin => onglet extranet.
   */
  private function acdc_admin_slug_to_front_tab_map() {
    return array(
      'acdc-of-needs'                  => 'needs',
      'acdc-of-calendar'               => 'calendar',
      'acdc-of-sessions-calendar'      => 'sessions_calendar',
      'acdc-of-pre-meetings'           => 'pre_meetings',
      'acdc-of-sessions-pending'       => 'sessions_pending',
      'acdc-of-sessions-validated'     => 'sessions_validated',
      'acdc-of-prospects'              => 'prospects',
      'acdc-of-prospect-followup'      => 'prospect_followup',
      'acdc-of-learners'               => 'learners',
      'acdc-of-formations'             => 'formations',
      'acdc-of-groups'                 => 'groups',
      'acdc-of-companies'              => 'companies',
      'acdc-of-funders'                => 'funders',
      'acdc-of-trainers'               => 'trainers',
      'acdc-of-quiz'                   => 'quiz',
      'acdc-of-need-analyses'          => 'need_analyses',
      'acdc-of-training-files'         => 'training_files',
      'acdc-of-registration-contract'  => 'registration_contract',
      'acdc-of-register-training'      => 'register_training',
      'acdc-of-mid-surveys'            => 'mid_surveys',
      'acdc-of-hot-surveys'            => 'hot_surveys',
      'acdc-of-cold-surveys'           => 'cold_surveys',
      'acdc-of-trainer-surveys'        => 'trainer_surveys',
      'acdc-of-company-surveys'        => 'company_surveys',
      'acdc-of-funder-surveys'         => 'funder_surveys',
      'acdc-of-evaluations'            => 'evaluations',
      'acdc-of-complaints'             => 'complaints',
      'acdc-of-questionnaire-sessions' => 'questionnaire_sessions',
      'acdc-of-questionnaire-results'  => 'questionnaire_results',
      'acdc-of-questionnaire-settings' => 'questionnaire_settings',
      'acdc-of-users'                  => 'users',
      'acdc-of-contacts'               => 'contacts',
      'acdc-of-documents'              => 'documents',
      'acdc-of-settings'               => 'settings',
    );
  }

  /**
   * ACDC 3.25.157 — Réoriente vers l'extranet une redirection visant une page
   * wp-admin masquée, quand la demande ne vient pas de wp-admin.
   *
   * Branché sur le filtre `wp_redirect`, ce correctif vaut pour les 126 appels
   * existants ET pour ceux à venir : corriger les appels un par un laisserait
   * fatalement des chemins derrière, comme la recette l'a montré deux fois.
   *
   * L'origine est déterminée dans cet ordre : paramètre `ctx` explicite, puis
   * référent HTTP. Sous admin-post.php, is_admin() vaut toujours vrai et ne peut
   * donc pas servir de critère.
   *
   * @param string $location URL de destination.
   * @return string
   */
  public function acdc_redirect_hidden_admin_page_to_front( $location ) {
    if ( ! is_string( $location ) || false === strpos( $location, 'page=acdc-of-' ) ) {
      return $location;
    }
    if ( ! preg_match( '~[?&]page=(acdc-of-[a-z0-9-]+)~', $location, $m ) ) {
      return $location;
    }
    $map = $this->acdc_admin_slug_to_front_tab_map();
    if ( ! isset( $map[ $m[1] ] ) ) {
      return $location; // Page non masquée, ou sans équivalent extranet : on ne touche à rien.
    }

    /* Navigation réelle dans wp-admin : on respecte la destination. is_admin() n'est
       trompeur que sous admin-post.php et admin-ajax.php, qui sont précisément les
       points d'entrée des actions lancées depuis l'extranet — on les exclut. */
    $script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
    if ( is_admin() && ! in_array( $script, array( 'admin-post.php', 'admin-ajax.php' ), true ) ) {
      return $location;
    }

    /* Demande explicitement issue de wp-admin : on respecte la destination. */
    $ctx = isset( $_REQUEST['ctx'] ) ? sanitize_key( wp_unslash( $_REQUEST['ctx'] ) ) : '';
    if ( 'admin' === $ctx ) {
      return $location;
    }
    if ( '' === $ctx ) {
      $referer = wp_get_referer();
      if ( $referer && false !== strpos( $referer, '/wp-admin/' ) ) {
        return $location;
      }
    }

    /* On conserve tous les paramètres d'origine (notice, item_id, action…) en
       remplaçant seulement l'adresse de base et la clé `page` par `tab`. */
    $query = wp_parse_url( $location, PHP_URL_QUERY );
    $args  = array();
    if ( $query ) {
      wp_parse_str( $query, $args );
    }
    unset( $args['page'] );
    $args['tab'] = $map[ $m[1] ];
    return $this->portal_page_url( $args );
  }

  /**
   * ACDC 3.25.184 — La migration de version ne peut plus se rejouer en parallèle.
   *
   * Cette méthode est accrochée sur `init`, donc à CHAQUE requête, publique
   * comprise. Tant que `acdc_of_saas_version` ne portait pas encore le nouveau
   * numéro — et il n'est écrit qu'à la toute fin de install_or_update() —, toute
   * requête entrante rejouait l'intégralité du travail : un instantané JSON+ZIP
   * des ~31 tables métier, puis un dbDelta sur ~50 tables et des dizaines de
   * SHOW COLUMNS. Une seule exécution est déjà longue ; dix requêtes simultanées
   * (onglets ouverts, heartbeat de l'admin, requêtes de l'installateur) en
   * lançaient dix, chacune ralentissant les autres, et aucune n'atteignait la
   * ligne qui aurait arrêté la ronde. C'est l'installation « qui mouline »
   * pendant dix minutes, et c'est la même mécanique que la panne du 9 août.
   *
   * Deux protections, dans cet ordre :
   *
   *   1. UN VERROU POSÉ AVANT LE TRAVAIL. Un INSERT sec dans la table des
   *      options : la clé unique sur option_name fait échouer le second
   *      arrivant, qui repart sans rien faire. Une seule requête migre.
   *   2. UN COMPTEUR DE TENTATIVES. Si la requête qui détient le verrou meurt
   *      (délai PHP dépassé), le verrou est repris au bout de 15 minutes, deux
   *      fois au plus. À la troisième, on cesse d'essayer et on l'écrit noir sur
   *      blanc : mieux vaut un site en ligne avec un schéma en retard et une
   *      alerte visible qu'un site indisponible qui se relance sans fin.
   *
   * On ne pose PAS le numéro de version avant le travail, contrairement au
   * correctif 3.25.177 sur les reprises de données : là-bas, perdre une reprise
   * était rattrapable ; ici, sauter la migration du schéma laisserait des
   * colonnes manquantes. Le verrou remplace le drapeau, et le compteur borne la
   * casse.
   *
   * Enfin, le travail lourd ne s'exécute plus que dans l'administration, en CLI
   * ou en cron — jamais sur une page publique, ni sur admin-ajax.php (que le
   * quiz en salle interroge en boucle). Une mise à jour par l'installateur
   * WordPress réactive le plugin, et `activate()` fait déjà la migration : le
   * chemin nominal ne dépend donc pas de cette méthode.
   */
  public function maybe_upgrade() {
    $may_run = $this->acdc_upgrade_may_run_here();
    if ( ! $may_run ) {
      return;
    }

    $upgrade_done = false;
    $installed    = get_option( 'acdc_of_saas_version' );
    if ( ACDC_OF_SAAS_VERSION !== $installed ) {
      $lock = $this->acdc_acquire_upgrade_lock( (string) $installed );

      if ( 'abandoned' === $lock ) {
        $this->acdc_report_upgrade_abandoned( (string) $installed );
      }

      if ( 'acquired' === $lock ) {
        try {
          if ( ! empty( $installed ) ) {
            $this->backup_data_before_update( $installed, ACDC_OF_SAAS_VERSION );
          }
          $this->install_or_update();
          $this->ensure_default_pages();
          /* ACDC 3.25.93 — C02 (audit) : (re)construit le rôle "acdc_portal_admin" à
             capacités limitées et resynchronise ses capacités avec administrator. */
          if ( method_exists( $this, 'ensure_acdc_portal_admin_role' ) ) {
            $this->ensure_acdc_portal_admin_role( true );
          }
          /* ACDC 3.25.279 — LES MIGRATIONS LOURDES TOURNENT ICI, SOUS LE VERROU.
             Elles étaient plus bas, hors de toute condition, donc rejouées à
             chaque affichage de page tant que leur drapeau n'était pas posé —
             pendant que la mise à jour recopiait déjà la base. Deux écritures
             concurrentes sur les mêmes tables, à l'instant le plus chargé de la
             vie du plugin. Leur place est ici : une fois, pendant
             l'installation, à l'abri du verrou. */
          $this->backfill_qualiopi_toggles_default_state();
          $this->retire_legacy_positioning_test_module();
          $this->backfill_org_identity_from_code();

          delete_option( 'acdc_of_upgrade_blocked' );
          $upgrade_done = true;
        } finally {
          $this->acdc_release_upgrade_lock();
        }
      }
    }

    /* ACDC 3.25.279 — LE CACHE A RENDU PERMANENT UN INCIDENT D'UNE SECONDE.
       Relevé en recette le 15 août : après la mise à jour, l'extranet affichait
       le code brut `[acdc_of_portal …]` au lieu du tableau de bord, et l'a fait
       jusqu'à la réinstallation du plugin. Aucune erreur fatale, aucun e-mail
       d'alerte de WordPress, aucune trace : une page avait simplement été
       rendue pendant que la mise à jour occupait le démarrage, et le cache du
       serveur l'a resservie telle quelle bien après que tout soit rentré dans
       l'ordre.
       Une mise à jour qui vient de changer le schéma et les pages du plugin
       doit donc jeter le cache derrière elle. */
    if ( ! empty( $upgrade_done ) ) {
      if ( function_exists( 'wp_cache_flush' ) ) {
        wp_cache_flush();
      }
      /* ACDC 3.25.282 — Les réponses publiques mises en cache décrivent une
         version qui n'est plus celle qui tourne. `wp_cache_flush()` ne touche
         pas les transients stockés en base : on les nomme. */
      delete_transient( 'acdc_of_indicators_global' );
      /* LiteSpeed (l'hébergement de production) écoute cette action ; les
         autres caches qui l'implémentent en profitent aussi. Sur un serveur
         sans cache, elle ne fait rien. */
      do_action( 'litespeed_purge_all' );
      do_action( 'acdc_of_after_upgrade', ACDC_OF_SAAS_VERSION );
    }
    /* ACDC 3.20.83 — Le rôle WordPress "acdc_trainer" du 3.20.82 est abandonné au profit
       d'une auth custom (cohérence avec l'extranet apprenant). Nettoyage des artefacts. */
    $this->cleanup_legacy_wp_trainer_role();

    /* ACDC 3.20.92 — Backfill des liens groups/sessions ↔ trainer_id.
       Idempotent : tourne au plus une fois grâce au flag d'options.
       Si la base ne contient aucun trainer_name peuplé, le backfill est un noop. */
    $this->backfill_groups_and_sessions_trainer_ids();

    /* ACDC 3.22.0 — Migration one-shot : synchronise is_self_trainer depuis trainer_type
       pour les formateurs créés avant que handle_save_trainer() ne gère cette dérivation.
       Idempotent : flag d'option empêche toute re-exécution. */
    $this->backfill_is_self_trainer_from_trainer_type();

    /* ACDC 3.25.279 — Les deux migrations de la 3.25.278 sont remontées dans le
       bloc de mise à jour, sous le verrou. Ici, elles étaient réexaminées à
       chaque affichage de page. */

    /* ACDC 3.25.251 — Purge des adresses de programme héritées du Manager.
       Elles portent un nonce périmé : elles ne mènent nulle part et ne servent
       plus qu'à faire croire qu'un programme est disponible. */
    $this->purge_dead_manager_programme_urls();

    /* ACDC 3.25.259 — Rattrapage des devis créés sans leur prospect, et remise
       à niveau des statuts qui n'ont pas pu avancer faute de ce lien. */
    if ( method_exists( $this, 'acdc_backfill_quote_prospect_links' ) ) {
      $this->acdc_backfill_quote_prospect_links();
    }

    /* ACDC 3.25.92 — C03 (audit) : chiffrement au repos des clés API de veille.
       Idempotent : flag d'option acdc_of_watch_keys_encrypted_v1 empêche toute
       re-exécution. Les valeurs déjà au format "acdcenc1:" sont ignorées. */
    if ( method_exists( $this, 'maybe_encrypt_watch_keys' ) ) {
      $this->maybe_encrypt_watch_keys();
    }

    /* ACDC 3.25.93 — C02 (audit) : migration one-shot des comptes admin portail
       (promus administrator natif) vers le rôle dédié à capacités limitées.
       Idempotent (flag). Garde-fous : jamais l'utilisateur n°1, jamais le compte
       fondateur, jamais un super-admin, toujours au moins un administrator natif. */
    if ( method_exists( $this, 'migrate_portal_admins_to_custom_role' ) ) {
      $this->migrate_portal_admins_to_custom_role();
    }
  }

  /**
   * ACDC 3.25.184 — Où la migration de schéma a le droit de s'exécuter.
   *
   * Nulle part sur le front : une page publique ne doit jamais payer un dbDelta.
   * Ni sur admin-ajax.php / admin-post.php, qui sont is_admin() mais servent les
   * actions de l'extranet et le pilotage du quiz en salle — une migration y
   * bloquerait une session en cours. Reste : les vraies pages de wp-admin,
   * WP-CLI et le cron.
   */
  private function acdc_upgrade_may_run_here() {
    if ( defined( 'WP_CLI' ) && WP_CLI ) {
      return true;
    }
    if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
      return true;
    }
    if ( ! is_admin() ) {
      return false;
    }
    $script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
    return ! in_array( $script, array( 'admin-ajax.php', 'admin-post.php' ), true );
  }

  /**
   * ACDC 3.25.184 — Prise du verrou de migration.
   *
   * Renvoie 'acquired' (à nous de migrer), 'busy' (une autre requête s'en
   * charge, ou vient d'échouer et le délai de reprise n'est pas écoulé) ou
   * 'abandoned' (trois tentatives ont échoué : on ne relance plus).
   *
   * L'acquisition passe par un INSERT sec, sans ON DUPLICATE KEY : c'est la clé
   * unique sur option_name qui arbitre, côté base, entre deux requêtes qui
   * arrivent dans la même milliseconde. add_option() ne conviendrait pas — il
   * écrit en ON DUPLICATE KEY UPDATE et laisserait passer les deux.
   */
  private function acdc_acquire_upgrade_lock( $from_version ) {
    global $wpdb;

    $option  = 'acdc_of_upgrade_lock';
    $now     = time();
    $stale_after = 900; // 15 minutes : au-delà, la requête détentrice est morte.

    $raw = $wpdb->get_var( $wpdb->prepare(
      "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
      $option
    ) );

    if ( null !== $raw ) {
      $lock     = json_decode( (string) $raw, true );
      $lock     = is_array( $lock ) ? $lock : array();
      $started  = isset( $lock['started_at'] ) ? (int) $lock['started_at'] : 0;
      $attempts = isset( $lock['attempts'] ) ? (int) $lock['attempts'] : 1;

      if ( ( $now - $started ) < $stale_after ) {
        return 'busy';
      }
      if ( $attempts >= 3 ) {
        return 'abandoned';
      }

      /* Reprise d'un verrou périmé. La comparaison sur l'ancienne valeur rend
         l'UPDATE atomique : si deux requêtes reprennent en même temps, une seule
         voit une ligne modifiée. */
      $payload = wp_json_encode( array(
        'target'     => ACDC_OF_SAAS_VERSION,
        'from'       => (string) $from_version,
        'started_at' => $now,
        'attempts'   => $attempts + 1,
      ) );
      $taken = $wpdb->query( $wpdb->prepare(
        "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
        $payload,
        $option,
        (string) $raw
      ) );
      $this->acdc_forget_option_cache( $option );
      return ( (int) $taken > 0 ) ? 'acquired' : 'busy';
    }

    $payload = wp_json_encode( array(
      'target'     => ACDC_OF_SAAS_VERSION,
      'from'       => (string) $from_version,
      'started_at' => $now,
      'attempts'   => 1,
    ) );
    $suppress = $wpdb->suppress_errors( true );
    $inserted = $wpdb->query( $wpdb->prepare(
      "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
      $option,
      $payload
    ) );
    $wpdb->suppress_errors( $suppress );
    $this->acdc_forget_option_cache( $option );

    return ( (int) $inserted > 0 ) ? 'acquired' : 'busy';
  }

  private function acdc_release_upgrade_lock() {
    global $wpdb;
    $option = 'acdc_of_upgrade_lock';
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", $option ) );
    $this->acdc_forget_option_cache( $option );
  }

  /* Le verrou est écrit et lu en SQL direct : il faut invalider à la main ce que
     l'API des options garde en cache, sans quoi get_option() servirait une valeur
     obsolète au reste de la requête. */
  private function acdc_forget_option_cache( $option ) {
    if ( function_exists( 'wp_cache_delete' ) ) {
      wp_cache_delete( $option, 'options' );
      wp_cache_delete( 'notoptions', 'options' );
    }
  }

  /**
   * ACDC 3.25.184 — Trois tentatives ont échoué : on cesse de relancer, et on le
   * dit. Le site reste en ligne avec son schéma précédent ; l'écran
   * d'administration porte un avertissement, et le journal garde la trace.
   */
  private function acdc_report_upgrade_abandoned( $from_version ) {
    $already = (string) get_option( 'acdc_of_upgrade_blocked', '' );
    if ( $already === ACDC_OF_SAAS_VERSION ) {
      return; // Déjà signalé pour cette version : on ne réécrit pas à chaque page.
    }
    update_option( 'acdc_of_upgrade_blocked', ACDC_OF_SAAS_VERSION, false );
    $this->log_error( 'upgrade', 'Migration de schéma abandonnée après trois tentatives.', array(
      'from' => (string) $from_version,
      'to'   => ACDC_OF_SAAS_VERSION,
    ) );
  }

  /**
   * ACDC 3.25.184 — Avertissement visible quand la migration a été abandonnée.
   * Le bouton relance une seule tentative, en effaçant le verrou : c'est une
   * action délibérée, jamais un automatisme.
   */
  public function acdc_render_upgrade_blocked_notice() {
    if ( ! current_user_can( 'manage_options' ) ) {
      return;
    }
    $blocked = (string) get_option( 'acdc_of_upgrade_blocked', '' );
    if ( '' === $blocked ) {
      return;
    }
    $url = wp_nonce_url(
      add_query_arg( 'action', 'acdc_retry_upgrade', admin_url( 'admin-post.php' ) ),
      'acdc_retry_upgrade'
    );
    echo '<div class="notice notice-error"><p><strong>ACDC Formation — migration de base incomplète.</strong> ';
    echo 'La mise à jour vers la version ' . esc_html( $blocked ) . ' n\'a pas pu aller au bout après trois tentatives. ';
    echo 'Le site fonctionne, mais certaines colonnes peuvent manquer. ';
    echo '<a href="' . esc_url( $url ) . '" class="button button-primary">Relancer la migration</a></p></div>';
  }

  /**
   * ACDC 3.20.83 — Nettoyage des artefacts WordPress posés en 3.20.82 :
   * - Suppression des comptes WordPress qui n'ont QUE le rôle "acdc_trainer" (créés par l'ancienne handle_invite_trainer)
   * - Dissociation : remise à 0 des trainer.user_id qui pointaient vers ces comptes
   * - Suppression du rôle "acdc_trainer" lui-même
   *
   * Idempotent : si rien à nettoyer, l'opération est un no-op.
   */
  private function cleanup_legacy_wp_trainer_role() {
    global $wpdb;
    $role = get_role( 'acdc_trainer' );
    if ( ! $role ) {
      return; // Déjà nettoyé.
    }

    // Récupérer les utilisateurs WP ayant le rôle acdc_trainer.
    $users = get_users( array( 'role' => 'acdc_trainer', 'fields' => array( 'ID' ) ) );
    foreach ( $users as $u ) {
      $user_obj = get_userdata( (int) $u->ID );
      if ( ! $user_obj ) {
        continue;
      }
      $roles = (array) $user_obj->roles;
      // On ne supprime QUE les comptes mono-rôle (sécurité : pas de suppression accidentelle d'admins).
      if ( count( $roles ) === 1 && 'acdc_trainer' === $roles[0] ) {
        // Dissocier les fiches formateur qui pointaient vers cet utilisateur.
        $wpdb->update(
          $this->trainer_table,
          array( 'user_id' => 0, 'invited_at' => null, 'updated_at' => current_time( 'mysql' ) ),
          array( 'user_id' => (int) $u->ID )
        );
        // Suppression du compte WP.
        if ( ! function_exists( 'wp_delete_user' ) ) {
          require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        wp_delete_user( (int) $u->ID );
      } else {
        // Compte multi-rôles : on retire juste acdc_trainer, on ne supprime pas le compte.
        $user_obj->remove_role( 'acdc_trainer' );
      }
    }

    // Suppression du rôle lui-même.
    remove_role( 'acdc_trainer' );
  }

  /**
   * ACDC 3.20.92 — Backfill des liens groups.trainer_id et sessions.trainer_id.
   *
   * Étape 1 — Match nominatif sur groups : pour chaque groupe avec trainer_name peuplé
   *           et trainer_id NULL, on tente un match unique normalisé (lowercase, sans accents,
   *           espaces compactés) avec first_name + ' ' + last_name des trainers existants.
   * Étape 2 — Propagation groups → sessions : pour chaque session avec trainer_id NULL,
   *           si elle a un (et un seul) groupe associé avec trainer_id renseigné,
   *           on copie ce trainer_id sur la session.
   *
   * Idempotent : ne tourne qu'une fois par version (flag stocké en option).
   * Sécurité : ne jamais écraser un trainer_id déjà renseigné. Ne jamais écrire de match ambigu.
   * Performance : aucune migration BD si les colonnes ou les données sont absentes. Ce backfill
   *              s'exécute lors du `maybe_upgrade()` qui ne se déclenche qu'au changement de
   *              numéro de version, donc une fois par déploiement.
   *
   * Le compteur de lignes non résolues est stocké en option pour un futur affichage admin.
   */
  private function backfill_groups_and_sessions_trainer_ids() {
    if ( get_option( 'acdc_of_saas_3_20_92_backfill_done' ) === '1' ) {
      return;
    }
    global $wpdb;

    // Sécurité : si la colonne n'a pas pu être créée (ex. permissions DB), on quitte sans tag.
    $col_groups = $wpdb->get_var( $wpdb->prepare(
      "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'trainer_id'",
      DB_NAME, $this->group_table
    ) );
    $col_sessions = $wpdb->get_var( $wpdb->prepare(
      "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'trainer_id'",
      DB_NAME, $this->session_table
    ) );
    if ( ! $col_groups || ! $col_sessions ) {
      return; // Migration de colonnes incomplète, on retentera au prochain bump de version.
    }

    $stats = array( 'groups_matched' => 0, 'groups_unresolved' => 0, 'sessions_propagated' => 0 );

    // ÉTAPE 1 — Match nominatif sur les groupes.
    $candidate_groups = $wpdb->get_results(
      "SELECT id, trainer_name FROM {$this->group_table}
       WHERE trainer_id IS NULL AND trainer_name IS NOT NULL AND trainer_name <> ''"
    );
    if ( $candidate_groups ) {
      $trainers = $wpdb->get_results( "SELECT id, first_name, last_name FROM {$this->trainer_table}" );
      // Indexation des trainers par leur nom complet normalisé.
      $by_name = array();
      foreach ( $trainers as $t ) {
        $full = $this->acdc_normalize_for_match( (string) $t->first_name . ' ' . (string) $t->last_name );
        if ( '' === $full ) { continue; }
        if ( ! isset( $by_name[ $full ] ) ) {
          $by_name[ $full ] = array();
        }
        $by_name[ $full ][] = (int) $t->id;
      }
      foreach ( $candidate_groups as $g ) {
        $needle = $this->acdc_normalize_for_match( (string) $g->trainer_name );
        if ( '' === $needle || ! isset( $by_name[ $needle ] ) || count( $by_name[ $needle ] ) !== 1 ) {
          $stats['groups_unresolved']++;
          continue;
        }
        $wpdb->update(
          $this->group_table,
          array( 'trainer_id' => $by_name[ $needle ][0] ),
          array( 'id' => (int) $g->id )
        );
        $stats['groups_matched']++;
      }
    }

    // ÉTAPE 2 — Propagation groups → sessions (uniquement si un seul groupe avec trainer_id).
    $stats['sessions_propagated'] = (int) $wpdb->query(
      "UPDATE {$this->session_table} s
       INNER JOIN (
         SELECT g.session_id, MIN(g.trainer_id) AS trainer_id
         FROM {$this->group_table} g
         WHERE g.session_id IS NOT NULL AND g.trainer_id IS NOT NULL
         GROUP BY g.session_id
         HAVING COUNT(DISTINCT g.trainer_id) = 1
       ) AS t ON t.session_id = s.id
       SET s.trainer_id = t.trainer_id
       WHERE s.trainer_id IS NULL"
    );

    update_option( 'acdc_of_saas_3_20_92_backfill_stats', $stats, false );
    update_option( 'acdc_of_saas_3_20_92_backfill_done', '1', false );
  }

  /**
   * ACDC 3.22.0 — Migration one-shot : synchronise is_self_trainer depuis trainer_type.
   * Avant 3.22.0, handle_save_trainer() dérivait is_self_trainer depuis une case à cocher
   * dédiée qui n'existe plus dans le formulaire depuis 3.20.100 → tous les formateurs
   * créés/mis à jour après 3.20.100 avaient is_self_trainer = 0 quelle que soit leur type.
   * Ce backfill corrige les enregistrements historiques en une seule passe.
   * Idempotent : ne s'exécute qu'une fois grâce au flag d'option.
   */
  private function backfill_is_self_trainer_from_trainer_type() {
    if ( get_option( 'acdc_of_saas_3_22_0_is_self_trainer_backfill_done' ) === '1' ) {
      return;
    }
    global $wpdb;
    $wpdb->query( "UPDATE {$this->trainer_table} SET is_self_trainer = CASE WHEN trainer_type = 'Interne' THEN 1 ELSE 0 END" );
    update_option( 'acdc_of_saas_3_22_0_is_self_trainer_backfill_done', '1', false );
  }

  /**
   * Les automatismes Qualiopi d'une formation : leur nom, leur état par défaut,
   * et CE QU'ILS COMMANDENT VRAIMENT.
   *
   * ACDC 3.25.278 — Cette liste existait en quatre exemplaires divergents :
   * l'écran, la sauvegarde, l'export Excel et l'import. « Enquête financeur »
   * était dans les deux premiers et absente des deux autres — elle disparaissait
   * à chaque aller-retour par le tableur, sans que rien ne le signale.
   *
   * La colonne `commande` n'est pas de la documentation : c'est elle que lit le
   * balayage tests/scan-interrupteur-branche.php. Un interrupteur déclaré ici
   * sans automatisme qui le consulte est un décor — un écran qui affirme retirer
   * un document du parcours alors qu'il y reste. C'est exactement ce qui s'est
   * produit pour les trois quiz structurels, préparés quoi qu'on décide.
   *
   * @return array<string,array{label:string,default:int,commande:string}>
   */
  public function acdc_qualiopi_toggles() {
    return array(
      'convocation_enabled'           => array( 'label' => 'Convocation de début de formation',    'default' => 1, 'commande' => 'convocation automatique des apprenants' ),
      'positioning_test_enabled'      => array( 'label' => 'Test de positionnement',               'default' => 0, 'commande' => 'quiz de positionnement préparé sur l’action' ),
      'diagnostic_evaluation_enabled' => array( 'label' => 'Évaluation diagnostique',              'default' => 1, 'commande' => 'quiz diagnostique préparé sur l’action' ),
      'intermediate_survey_enabled'   => array( 'label' => 'Enquête de satisfaction intermédiaire', 'default' => 0, 'commande' => 'enquête à mi-parcours' ),
      'hot_survey_enabled'            => array( 'label' => 'Enquête de satisfaction à chaud',      'default' => 1, 'commande' => 'enquête de fin de formation' ),
      'evaluation_enabled'            => array( 'label' => 'Évaluation des acquis',                'default' => 1, 'commande' => 'quiz d’évaluation des acquis préparé sur l’action' ),
      'end_documents_enabled'         => array( 'label' => 'Documents de fin de formation',        'default' => 1, 'commande' => 'certificat de réalisation et attestation' ),
      'cold_survey_enabled'           => array( 'label' => 'Enquête de satisfaction à froid',      'default' => 1, 'commande' => 'enquête différée' ),
      'trainer_survey_enabled'        => array( 'label' => 'Enquête formateur',                    'default' => 1, 'commande' => 'enquête adressée au formateur' ),
      'company_survey_enabled'        => array( 'label' => 'Enquête entreprise',                   'default' => 1, 'commande' => 'enquête adressée au commanditaire' ),
      'funder_survey_enabled'         => array( 'label' => 'Enquête financeur',                    'default' => 1, 'commande' => 'enquête adressée au financeur' ),
    );
  }

  /**
   * ACDC 3.25.278 — Les automatismes Qualiopi remis dans l'état voulu.
   *
   * Demandé en recette : « ils doivent tous être cochés sauf Test de
   * positionnement et Enquête de satisfaction intermédiaire ». Changer la
   * valeur par défaut d'une colonne ne touche que les LIGNES À VENIR : les
   * formations déjà enregistrées gardent ce qu'elles portent. Sans cette passe,
   * l'écran continuerait d'afficher l'ancien réglage sur toutes les fiches
   * existantes, et il faudrait les rouvrir une par une.
   *
   * Elle écrase donc les interrupteurs sur toutes les formations, une seule
   * fois. C'est volontaire et c'est ce qui a été demandé — un réglage
   * particulier posé auparavant sur une fiche sera remis à la règle commune.
   */
  /**
   * L'identité qui vivait dans le code passe une fois dans la fiche.
   *
   * ACDC 3.25.290. Le SIRET, le NDA, l'adresse et les coordonnées étaient
   * écrits en dur à deux titres : dans des documents, et comme VALEURS PAR
   * DÉFAUT des champs de réglage. Cette seconde forme est la plus perverse :
   * un champ vidé se remplissait tout seul à la lecture suivante, et l'ancien
   * numéro de déclaration d'activité revenait sur les documents.
   *
   * On veut désormais qu'un champ vide reste vide. Vider les valeurs par
   * défaut sans rien faire d'autre effacerait donc, sur un site déjà en
   * service, tout ce qui n'avait jamais été saisi à la main — c'est-à-dire
   * l'identité affichée aujourd'hui sur les conventions et les attestations.
   *
   * Cette migration écrit une fois dans la fiche ce que le code affichait, et
   * seulement là où la fiche est muette. Rien ne change à l'écran ; ce qui
   * était magique devient modifiable. C'est le dernier endroit du plugin où
   * ces valeurs figurent, et elles y figurent pour pouvoir en disparaître.
   */
  private function backfill_org_identity_from_code() {
    if ( '1' === get_option( 'acdc_of_saas_identity_backfilled' ) ) {
      return;
    }

    /* Ce que le code affichait avant la 3.25.290, à sa place légitime : une
       migration, datée, exécutée une fois. */
    $historique = array(
      'enterprise'                  => 'ACDC Formation',
      'siret_identification'        => '405 109 901 00042',
      'activity_declaration_number' => '93 83 08347 83',
      'address'                     => '7 avenue Paul Cézanne',
      'postal_code'                 => '83310',
      'city'                        => 'Cogolin',
      'country'                     => 'France',
      'enterprise_contact_email'    => 'contact@acdc-formation.com',
      'enterprise_contact_phone'    => '06 78 26 91 10',
    );

    $fiche = get_option( 'acdc_of_company_profile', array() );
    if ( ! is_array( $fiche ) ) {
      $fiche = array();
    }
    $marque = get_option( 'acdc_of_branding', array() );
    if ( ! is_array( $marque ) ) {
      $marque = array();
    }

    /* La fiche d'abord, la marque ensuite, le code en dernier recours : on ne
       remplace jamais une saisie, on ne comble qu'un silence. */
    $courant = \ACDC\Support\OrgIdentity::fromOptions( $fiche, $marque );
    $carte   = array(
      'enterprise'                  => 'raison_sociale',
      'siret_identification'        => 'siret',
      'activity_declaration_number' => 'nda',
      'address'                     => 'adresse',
      'postal_code'                 => 'code_postal',
      'city'                        => 'ville',
      'country'                     => 'pays',
      'enterprise_contact_email'    => 'email',
      'enterprise_contact_phone'    => 'telephone',
    );

    $modifie = false;
    foreach ( $carte as $cle_fiche => $cle_identite ) {
      if ( isset( $fiche[ $cle_fiche ] ) && '' !== trim( (string) $fiche[ $cle_fiche ] ) ) {
        continue; /* déjà saisi : on n'y touche pas */
      }
      $valeur = '' !== $courant[ $cle_identite ]
        ? $courant[ $cle_identite ]
        : ( isset( $historique[ $cle_fiche ] ) ? $historique[ $cle_fiche ] : '' );
      if ( '' !== $valeur ) {
        $fiche[ $cle_fiche ] = $valeur;
        $modifie             = true;
      }
    }

    if ( $modifie ) {
      update_option( 'acdc_of_company_profile', $fiche, false );
      wp_cache_delete( 'acdc_of_company_profile', 'options' );
    }
    update_option( 'acdc_of_saas_identity_backfilled', '1', false );
  }

  private function backfill_qualiopi_toggles_default_state() {
    if ( get_option( 'acdc_of_saas_3_25_278_toggles_done' ) === '1' ) {
      return;
    }
    global $wpdb;
    if ( empty( $this->formation_table ) ) {
      return;
    }
    $colonnes = array();
    foreach ( (array) $wpdb->get_col( "SHOW COLUMNS FROM {$this->formation_table}" ) as $col ) {
      $colonnes[ (string) $col ] = true;
    }
    $sets = array();
    foreach ( $this->acdc_qualiopi_toggles() as $champ => $meta ) {
      if ( isset( $colonnes[ $champ ] ) ) {
        $sets[] = $champ . ' = ' . (int) $meta['default'];
      }
    }
    if ( empty( $sets ) ) {
      return;
    }
    $wpdb->query( "UPDATE {$this->formation_table} SET " . implode( ', ', $sets ) );
    update_option( 'acdc_of_saas_3_25_278_toggles_done', '1', false );
  }

  /**
   * ACDC 3.25.278 — L'ANCIEN MODULE « TESTS DE POSITIONNEMENT » EST RETIRÉ.
   *
   * Relevé en recette : « je ne comprends pas pourquoi il y a 2 tests de
   * positionnement ». Il y en avait bien deux dans le menu, portant le même
   * nom : ce module-ci, et le quiz de positionnement. Décision de David — « le
   * seul qui sera valide sera fait par le quiz ».
   *
   * CE MODULE N'A JAMAIS FONCTIONNÉ. Son envoi automatique était programmé tous
   * les jours, mais la fonction qu'il appelait n'existait pas : un rendez-vous
   * quotidien, personne au bout du fil. Aucun test n'est donc jamais parti par
   * cette voie — et chaque passage plantait l'exécution des tâches planifiées,
   * emportant avec lui ce qui était programmé derrière.
   *
   * ON COPIE AVANT DE SUPPRIMER. La table peut contenir des tests composés à la
   * main. Une suppression demandée reste une suppression irréversible : on
   * dépose donc une copie en clair dans le répertoire des sauvegardes, puis on
   * supprime. Si la copie ne peut pas s'écrire, on ne supprime pas — mieux vaut
   * une table qui traîne qu'une preuve perdue.
   *
   * Les RÉSULTATS de positionnement ne sont pas concernés : ils vivent sur la
   * fiche d'inscription (`positioning_result_document_url`), c'est là que le
   * quiz les dépose, et ils restent consultables dans chaque dossier.
   */
  private function retire_legacy_positioning_test_module() {
    if ( get_option( 'acdc_of_saas_3_25_278_positioning_retired' ) === '1' ) {
      return;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'acdc_of_positioning_tests';

    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
      wp_clear_scheduled_hook( 'acdc_of_positioning_test_cron' );
      update_option( 'acdc_of_saas_3_25_278_positioning_retired', '1', false );
      return;
    }

    $rows = (array) $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A );
    if ( ! empty( $rows ) ) {
      $base = $this->get_backup_base_directory();
      if ( '' === $base || ! wp_mkdir_p( $base ) ) {
        return;   // pas de copie possible : on ne supprime rien.
      }
      $fichier = trailingslashit( $base ) . 'acdc-tests-positionnement-retires-' . gmdate( 'Ymd-His' ) . '.json';
      $ecrit   = file_put_contents( $fichier, wp_json_encode( $rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
      if ( false === $ecrit ) {
        return;   // idem : la suppression attend que la copie soit possible.
      }
      update_option( 'acdc_of_saas_3_25_278_positioning_backup', $fichier, false );
    }

    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
    wp_clear_scheduled_hook( 'acdc_of_positioning_test_cron' );
    update_option( 'acdc_of_saas_3_25_278_positioning_retired', '1', false );
  }

  /**
   * ACDC 3.25.251 — Les adresses de programme qui ne mènent nulle part sont effacées.
   *
   * Le motif visé est étroit et sans ambiguïté : une route d'administration
   * (`admin-ajax.php` / `admin-post.php`) ET une action de l'ancien plugin
   * Manager (`acdc_fm_…`, `acdc_pdf_nonce`). Ces adresses portent un nonce, donc
   * un jeton valable vingt-quatre heures : elles sont mortes depuis longtemps.
   *
   * Rien d'utile n'est perdu — on efface une adresse qui ne désigne aucun
   * fichier. Ce qui est gagné : les écrans cessent d'annoncer un programme
   * qu'ils ne peuvent pas fournir, et le formulaire de formation cesse de
   * proposer « ouvrir » sur un lien qui affiche « Lien invalide ».
   *
   * Idempotent : un flag d'option empêche toute nouvelle passe.
   */
  private function purge_dead_manager_programme_urls() {
    if ( '1' === get_option( 'acdc_of_saas_3_25_251_manager_programme_purged' ) ) {
      return;
    }
    global $wpdb;
    $wpdb->query(
      "UPDATE {$this->formation_table}
          SET program_file_url = ''
        WHERE ( program_file_url LIKE '%admin-ajax.php%' OR program_file_url LIKE '%admin-post.php%' )
          AND ( program_file_url LIKE '%acdc_fm_%' OR program_file_url LIKE '%acdc_pdf_nonce%' )"
    );
    update_option( 'acdc_of_saas_3_25_251_manager_programme_purged', '1', false );
  }

  /**
   * ACDC 3.20.92 — Normalisation simple pour comparaison nominative.
   * Lowercase, suppression d'accents, trim, compactage des espaces multiples.
   * Utilisée par backfill_groups_and_sessions_trainer_ids() pour matcher
   * trainer_name (saisi manuellement) avec first_name + last_name.
   */
  private function acdc_normalize_for_match( $value ) {
    $s = (string) $value;
    if ( '' === $s ) { return ''; }
    if ( function_exists( 'remove_accents' ) ) {
      $s = remove_accents( $s );
    }
    $s = strtolower( $s );
    $s = preg_replace( '/\s+/', ' ', $s );
    return trim( $s );
  }

  /* ====================================================================
   * ACDC 3.20.93 — Alertes Qualiopi automatiques.
   *
   * Cron quotidien `acdc_of_qualiopi_alerts_cron` (planté dans maybe_repair_runtime_state).
   * Scanne 3 sources (URSSAF, RC pro, trainer_documents avec is_qualiopi_proof = 1)
   * et envoie 2 e-mails séparés (formateur ton personnel + admin ton synthétique)
   * aux seuils J-30, J-7, J-0.
   *
   * Anti-doublon par option `acdc_of_qualiopi_alerts_log` :
   * clé = "t{trainer_id}_{type}_{expires_at}_{seuil}", valeur = date d'envoi.
   * Si la date expires_at change côté admin, la clé change automatiquement et
   * une nouvelle alerte sera envoyée au prochain seuil pertinent.
   * ==================================================================== */

  /**
   * Liste les seuils d'alerte (en jours avant expiration). 3.20.93 = J-30, J-7, J-0.
   * @return int[]
   */
  protected function get_acdc_qualiopi_alert_thresholds() {
    return array( 30, 7, 0 );
  }

  /**
   * Lit le log anti-doublon depuis l'option et le purge des entrées obsolètes (>60j).
   * @return array<string,string>
   */
  protected function get_acdc_qualiopi_alert_log() {
    $raw = get_option( 'acdc_of_qualiopi_alerts_log', array() );
    if ( ! is_array( $raw ) ) { $raw = array(); }
    $cutoff_ts = current_time( 'timestamp' ) - ( 60 * DAY_IN_SECONDS );
    $clean = array();
    foreach ( $raw as $key => $sent_at ) {
      if ( ! is_string( $sent_at ) || '' === $sent_at ) { continue; }
      $sent_ts = strtotime( $sent_at );
      if ( $sent_ts && $sent_ts >= $cutoff_ts ) {
        $clean[ (string) $key ] = (string) $sent_at;
      }
    }
    return $clean;
  }

  /**
   * Persiste le log anti-doublon en option.
   * @param array<string,string> $log
   */
  protected function set_acdc_qualiopi_alert_log( $log ) {
    update_option( 'acdc_of_qualiopi_alerts_log', is_array( $log ) ? $log : array(), false );
  }

  /**
   * Helper : libellé humain d'un type d'expiration.
   * @param string $type 'urssaf' | 'rcpro' | 'doc_<category>'
   * @return string
   */
  protected function acdc_qualiopi_type_label( $type ) {
    if ( 'urssaf' === $type ) { return 'Attestation URSSAF'; }
    if ( 'rcpro' === $type ) { return 'Assurance RC professionnelle'; }
    return 'Justificatif Qualiopi';
  }

  /**
   * Recense les expirations à venir d'un formateur dans les N prochains jours.
   * Utilisé par le cron, par le test manuel et par les bandeaux d'info.
   *
   * @param object|int $trainer Objet formateur ou ID.
   * @param int        $window  Fenêtre en jours (défaut 30).
   * @return array<int,array{type:string,label:string,custom_label:string,expires_at:string,days:int,document_id:int,is_qualiopi_proof:bool}>
   */
  protected function get_acdc_qualiopi_upcoming_expirations( $trainer, $window = 30 ) {
    if ( is_numeric( $trainer ) ) {
      $trainer = $this->get_trainer( (int) $trainer );
    }
    if ( ! $trainer || ! isset( $trainer->id ) ) {
      return array();
    }
    global $wpdb;
    $today_ts = strtotime( wp_date( 'Y-m-d' ) );
    $items = array();

    // Source 1 — URSSAF
    if ( ! empty( $trainer->urssaf_attestation_expires_at ) && $trainer->urssaf_attestation_expires_at !== '0000-00-00' ) {
      $exp_ts = strtotime( (string) $trainer->urssaf_attestation_expires_at );
      if ( $exp_ts ) {
        $days = (int) round( ( $exp_ts - $today_ts ) / DAY_IN_SECONDS );
        if ( $days <= (int) $window ) {
          $items[] = array(
            'type'              => 'urssaf',
            'label'             => $this->acdc_qualiopi_type_label( 'urssaf' ),
            'custom_label'      => '',
            'expires_at'        => (string) $trainer->urssaf_attestation_expires_at,
            'days'              => $days,
            'document_id'       => 0,
            'is_qualiopi_proof' => true,
          );
        }
      }
    }

    // Source 2 — RC pro
    if ( ! empty( $trainer->rc_pro_expires_at ) && $trainer->rc_pro_expires_at !== '0000-00-00' ) {
      $exp_ts = strtotime( (string) $trainer->rc_pro_expires_at );
      if ( $exp_ts ) {
        $days = (int) round( ( $exp_ts - $today_ts ) / DAY_IN_SECONDS );
        if ( $days <= (int) $window ) {
          $items[] = array(
            'type'              => 'rcpro',
            'label'             => $this->acdc_qualiopi_type_label( 'rcpro' ),
            'custom_label'      => '',
            'expires_at'        => (string) $trainer->rc_pro_expires_at,
            'days'              => $days,
            'document_id'       => 0,
            'is_qualiopi_proof' => true,
          );
        }
      }
    }

    // Source 3 — trainer_documents avec is_qualiopi_proof = 1 (arbitrage 2a strict).
    $rows = $wpdb->get_results( $wpdb->prepare(
      "SELECT id, category, label, expires_at, is_qualiopi_proof
       FROM {$this->trainer_document_table}
       WHERE trainer_id = %d
         AND is_qualiopi_proof = 1
         AND expires_at IS NOT NULL
         AND expires_at <> '0000-00-00'",
      (int) $trainer->id
    ) );
    foreach ( (array) $rows as $row ) {
      $exp_ts = strtotime( (string) $row->expires_at );
      if ( ! $exp_ts ) { continue; }
      $days = (int) round( ( $exp_ts - $today_ts ) / DAY_IN_SECONDS );
      if ( $days > (int) $window ) { continue; }
      $items[] = array(
        'type'              => 'doc_' . (int) $row->id,
        'label'             => $this->acdc_qualiopi_type_label( 'doc' ),
        'custom_label'      => (string) $row->label,
        'expires_at'        => (string) $row->expires_at,
        'days'              => $days,
        'document_id'       => (int) $row->id,
        'is_qualiopi_proof' => true,
      );
    }

    // Tri par urgence : du plus proche au plus lointain.
    usort( $items, function( $a, $b ) {
      return $a['days'] - $b['days'];
    } );
    return $items;
  }

  /**
   * Routine principale du cron Qualiopi (cible : tous les formateurs).
   * Idempotente : pilotée par l'option `acdc_of_qualiopi_alerts_log`.
   * Renvoie un compteur d'alertes effectivement envoyées.
   *
   * @return int Nombre d'alertes envoyées (paires formateur+admin comptées séparément).
   */
  public function process_qualiopi_expiration_alerts() {
    global $wpdb;
    $trainers = $wpdb->get_results( "SELECT * FROM {$this->trainer_table}" );
    $log = $this->get_acdc_qualiopi_alert_log();
    $thresholds = $this->get_acdc_qualiopi_alert_thresholds();
    $today = wp_date( 'Y-m-d' );
    $sent_count = 0;

    foreach ( (array) $trainers as $trainer ) {
      // Élargi à la fenêtre la plus large (30j) pour récupérer toutes les expirations
      // candidates en une seule lecture.
      $expirations = $this->get_acdc_qualiopi_upcoming_expirations( $trainer, max( $thresholds ) );
      foreach ( $expirations as $exp ) {
        // On déclenche uniquement aux jours pile correspondant aux seuils.
        if ( ! in_array( (int) $exp['days'], $thresholds, true ) ) {
          continue;
        }
        $key = 't' . (int) $trainer->id . '_' . $exp['type'] . '_' . $exp['expires_at'] . '_' . (int) $exp['days'];
        if ( isset( $log[ $key ] ) ) {
          continue; // Déjà envoyée pour cette combinaison.
        }
        $sent = $this->acdc_qualiopi_send_alert_pair( $trainer, $exp );
        $log[ $key ] = $today;
        $sent_count += $sent;
      }
    }

    $this->set_acdc_qualiopi_alert_log( $log );
    return $sent_count;
  }

  /**
   * Envoie le couple d'alertes (formateur + admin) pour une expiration donnée.
   * Arbitrage 1.4 validé : deux e-mails séparés, ton personnel pour le formateur,
   * ton synthétique pour l'admin.
   *
   * @param object $trainer
   * @param array  $exp Item de get_acdc_qualiopi_upcoming_expirations()
   * @return int Nombre d'envois réussis (0, 1 ou 2).
   */
  protected function acdc_qualiopi_send_alert_pair( $trainer, $exp ) {
    $count = 0;
    $days = (int) $exp['days'];
    $urgency = ( 0 === $days ) ? 'today' : ( ( 7 === $days ) ? 'urgent' : 'preventive' );
    $document_label = '' !== $exp['custom_label'] ? $exp['custom_label'] . ' (' . $exp['label'] . ')' : $exp['label'];
    $expires_disp = mysql2date( 'd/m/Y', $exp['expires_at'] );
    $first_name = trim( (string) $trainer->first_name );
    $full_name = trim( (string) $trainer->first_name . ' ' . (string) $trainer->last_name );
    if ( '' === $full_name ) { $full_name = 'Formateur sans nom'; }

    // 1) E-mail formateur — ton personnel.
    $trainer_email = sanitize_email( (string) $trainer->email );
    if ( '' !== $trainer_email && is_email( $trainer_email ) ) {
      $subject_trainer = $this->acdc_qualiopi_subject_for( 'trainer', $urgency, $document_label, $days );
      $body_trainer = $this->acdc_qualiopi_build_body_for_trainer( $document_label, $expires_disp, $days, $urgency );
      $sent = $this->acdc_send_transactional_email(
        $trainer_email,
        $subject_trainer,
        array(
          'greeting_name' => $first_name,
          'intro_html'    => '',
          'body_html'     => $body_trainer,
          'footer_notice' => 'Cette alerte automatique vous est envoyée pour vous aider à maintenir votre conformité Qualiopi. Vous pouvez mettre à jour votre justificatif depuis votre espace formateur.',
        ),
        array(
          'source_module'  => 'qualiopi_alerts',
          'source_action'  => 'expiration_alert',
          'email_category' => 'qualiopi',
          'email_audience' => 'formateur',
        )
      );
      if ( $sent ) { $count++; }
    }

    // 2) E-mail admin — ton synthétique.
    $admin_email = sanitize_email( (string) get_option( 'admin_email', '' ) );
    if ( '' !== $admin_email && is_email( $admin_email ) ) {
      $subject_admin = $this->acdc_qualiopi_subject_for( 'admin', $urgency, $document_label, $days );
      $body_admin = $this->acdc_qualiopi_build_body_for_admin( $full_name, $document_label, $expires_disp, $days, $urgency );
      $sent = $this->acdc_send_transactional_email(
        $admin_email,
        $subject_admin,
        array(
          'greeting_name' => 'administrateur',
          'intro_html'    => '',
          'body_html'     => $body_admin,
          'footer_notice' => 'Notification Qualiopi automatique. Pilotée par le module ACDC SAAS OF — fréquence quotidienne, déclenchée aux seuils J-30, J-7 et J-0.',
        ),
        array(
          'source_module'  => 'qualiopi_alerts',
          'source_action'  => 'expiration_alert',
          'email_category' => 'qualiopi',
          'email_audience' => 'admin',
        )
      );
      if ( $sent ) { $count++; }
    }
    return $count;
  }

  /**
   * Sujet d'e-mail Qualiopi.
   * @param string $audience 'trainer' | 'admin'
   * @param string $urgency  'preventive' | 'urgent' | 'today'
   * @param string $document_label
   * @param int    $days
   * @return string
   */
  protected function acdc_qualiopi_subject_for( $audience, $urgency, $document_label, $days ) {
    if ( 'admin' === $audience ) {
      if ( 'today' === $urgency ) { return 'Qualiopi — Expiration aujourd’hui : ' . $document_label; }
      if ( 'urgent' === $urgency ) { return 'Qualiopi — Expiration dans 7 jours : ' . $document_label; }
      return 'Qualiopi — Expiration dans 30 jours : ' . $document_label;
    }
    // Formateur.
    if ( 'today' === $urgency ) { return 'Votre justificatif expire aujourd’hui'; }
    if ( 'urgent' === $urgency ) { return 'Votre justificatif expire dans 7 jours'; }
    return 'Votre justificatif arrive à expiration dans 30 jours';
  }

  /**
   * Corps e-mail formateur — ton personnel, deuxième personne du pluriel, CTA vers le portail.
   * @return string HTML
   */
  protected function acdc_qualiopi_build_body_for_trainer( $document_label, $expires_disp, $days, $urgency ) {
    $intro = '';
    if ( 'today' === $urgency ) {
      $intro = 'Une de vos pièces justificatives Qualiopi expire <strong>aujourd’hui</strong>. Pour rester conforme et éviter toute interruption, prenez quelques minutes pour la mettre à jour.';
    } elseif ( 'urgent' === $urgency ) {
      $intro = 'Une de vos pièces justificatives Qualiopi expire <strong>dans 7 jours</strong>. Il est temps de la renouveler pour rester serein côté conformité.';
    } else {
      $intro = 'Bonne nouvelle : vous avez encore <strong>30 jours</strong> pour mettre à jour une de vos pièces justificatives Qualiopi. Prendre les devants maintenant vous évitera de courir le mois prochain.';
    }
    $portal_url = $this->trainer_portal_page_url( 'library' );
    $cta_label = ( 'today' === $urgency || 'urgent' === $urgency ) ? 'Mettre à jour mon justificatif' : 'Préparer le renouvellement';

    $html  = '<p style="margin:0 0 14px;">' . $intro . '</p>';
    $html .= '<table cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:18px 0;border-collapse:collapse;">';
    $html .= '<tr><td style="padding:8px 12px;background:#f7f9fc;border:1px solid #e6ebf2;border-radius:8px;">';
    $html .= '<strong style="display:block;color:#1E4777;font-size:14px;margin-bottom:4px;">' . esc_html( $document_label ) . '</strong>';
    $html .= '<span style="color:#5a6577;font-size:13px;">Date d’expiration : <strong>' . esc_html( $expires_disp ) . '</strong> (' . ( 0 === (int) $days ? 'aujourd’hui' : 'dans ' . (int) $days . ' jour' . ( (int) $days > 1 ? 's' : '' ) ) . ')</span>';
    $html .= '</td></tr></table>';
    $html .= '<p style="margin:0 0 16px;">Connectez-vous à votre espace formateur pour téléverser le document mis à jour. Notre équipe administrative est notifiée en parallèle.</p>';
    $html .= '<p style="margin:18px 0;text-align:center;"><a href="' . esc_url( $portal_url ) . '" style="display:inline-block;padding:12px 22px;background:#D7A24B;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;font-size:14px;">' . esc_html( $cta_label ) . '</a></p>';
    return $html;
  }

  /**
   * Corps e-mail admin — ton synthétique, troisième personne, données d'identification.
   * @return string HTML
   */
  protected function acdc_qualiopi_build_body_for_admin( $trainer_full_name, $document_label, $expires_disp, $days, $urgency ) {
    $title_line = '';
    if ( 'today' === $urgency ) {
      $title_line = 'Expiration <strong>aujourd’hui</strong> sur la fiche de ' . esc_html( $trainer_full_name ) . '.';
    } elseif ( 'urgent' === $urgency ) {
      $title_line = 'Expiration <strong>dans 7 jours</strong> sur la fiche de ' . esc_html( $trainer_full_name ) . '.';
    } else {
      $title_line = 'Expiration <strong>dans 30 jours</strong> sur la fiche de ' . esc_html( $trainer_full_name ) . '.';
    }

    $html  = '<p style="margin:0 0 14px;">' . $title_line . '</p>';
    $html .= '<table cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:14px 0;border-collapse:collapse;">';
    $html .= '<tr><td style="padding:10px 14px;background:#f7f9fc;border:1px solid #e6ebf2;border-radius:8px;font-size:13px;line-height:1.7;">';
    $html .= '<strong style="color:#1E4777;">Formateur :</strong> ' . esc_html( $trainer_full_name ) . '<br>';
    $html .= '<strong style="color:#1E4777;">Justificatif :</strong> ' . esc_html( $document_label ) . '<br>';
    $html .= '<strong style="color:#1E4777;">Date d’expiration :</strong> ' . esc_html( $expires_disp ) . ' (' . ( 0 === (int) $days ? 'aujourd’hui' : 'J-' . (int) $days ) . ')<br>';
    $html .= '<strong style="color:#1E4777;">Action attendue :</strong> ' . ( 'today' === $urgency ? 'Suspendre les nouvelles affectations tant que la pièce n’est pas renouvelée.' : 'Relancer le formateur si pas de mise à jour reçue prochainement.' );
    $html .= '</td></tr></table>';
    $html .= '<p style="margin:0 0 6px;color:#5a6577;font-size:12px;">Le formateur a reçu en parallèle une notification personnelle l’invitant à renouveler son document depuis l’extranet.</p>';
    return $html;
  }

  /**
   * Variante non-cron du moteur : test manuel sur un seul formateur.
   * Ignore le log anti-doublon. Renvoie une structure de retour pour affichage.
   *
   * @param int $trainer_id
   * @return array{sent:int,recipients:string[],items:int}
   */
  public function acdc_qualiopi_run_test_for_trainer( $trainer_id ) {
    $trainer = $this->get_trainer( (int) $trainer_id );
    if ( ! $trainer ) {
      return array( 'sent' => 0, 'recipients' => array(), 'items' => 0 );
    }
    $expirations = $this->get_acdc_qualiopi_upcoming_expirations( $trainer, max( $this->get_acdc_qualiopi_alert_thresholds() ) );
    $thresholds = $this->get_acdc_qualiopi_alert_thresholds();
    $sent_total = 0;
    $recipients = array();
    $matched_items = 0;
    foreach ( $expirations as $exp ) {
      if ( ! in_array( (int) $exp['days'], $thresholds, true ) ) {
        continue;
      }
      $matched_items++;
      $sent_total += $this->acdc_qualiopi_send_alert_pair( $trainer, $exp );
      if ( ! empty( $trainer->email ) && is_email( $trainer->email ) ) {
        $recipients[ $trainer->email ] = $trainer->email;
      }
      $admin_email = (string) get_option( 'admin_email', '' );
      if ( '' !== $admin_email && is_email( $admin_email ) ) {
        $recipients[ $admin_email ] = $admin_email;
      }
    }
    return array(
      'sent'       => $sent_total,
      'recipients' => array_values( $recipients ),
      'items'      => $matched_items,
    );
  }

  private function install_or_update() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    /* ACDC 3.25.148 — F11 : verrouiller RÉTROACTIVEMENT le dossier des contrats
       formateurs. Les fichiers générés avant ce correctif sont restés accessibles
       publiquement (nom, e-mail et SIRET du formateur) ; on pose le .htaccess sur
       la racine du dossier ainsi que sur chaque sous-dossier existant. */
    $acdc_up   = wp_upload_dir();
    $acdc_croot = trailingslashit( $acdc_up['basedir'] ) . 'acdc-of-contracts/';
    if ( is_dir( $acdc_croot ) ) {
      foreach ( array_merge( array( $acdc_croot ), (array) glob( $acdc_croot . '*', GLOB_ONLYDIR ) ) as $acdc_cdir ) {
        $acdc_cdir = trailingslashit( (string) $acdc_cdir );
        if ( ! file_exists( $acdc_cdir . '.htaccess' ) ) {
          file_put_contents( $acdc_cdir . '.htaccess', "deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        }
        if ( ! file_exists( $acdc_cdir . 'index.php' ) ) {
          file_put_contents( $acdc_cdir . 'index.php', "<?php // Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        }
      }
    }

    /* ACDC 3.25.153 — V3.8 : purger les comptes de portail formateur ORPHELINS,
       c'est-à-dire rattachés à un formateur qui n'existe plus. Ils bloquaient la
       réutilisation d'une adresse e-mail (« déjà associé à un autre formateur »)
       tout en restant invisibles depuis l'interface. */
    $acdc_pa = $wpdb->prefix . 'acdc_of_trainer_portal_accounts';
    $acdc_tr = $wpdb->prefix . 'acdc_of_trainers';
    if ( $acdc_pa === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $acdc_pa ) ) ) {
      $acdc_orphans = (array) $wpdb->get_col(
        "SELECT a.id FROM {$acdc_pa} a LEFT JOIN {$acdc_tr} t ON t.id = a.trainer_id WHERE t.id IS NULL"
      );
      if ( ! empty( $acdc_orphans ) ) {
        $acdc_ids = implode( ',', array_map( 'absint', $acdc_orphans ) );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}acdc_of_trainer_portal_sessions WHERE account_id IN ({$acdc_ids})" );
        $wpdb->query( "DELETE FROM {$wpdb->prefix}acdc_of_trainer_portal_tokens   WHERE account_id IN ({$acdc_ids})" );
        $wpdb->query( "DELETE FROM {$acdc_pa} WHERE id IN ({$acdc_ids})" );
      }
    }

    $charset_collate = $wpdb->get_charset_collate();

    $sql_companies = "CREATE TABLE {$this->company_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      name VARCHAR(190) NOT NULL,
      legal_form VARCHAR(100) DEFAULT '',
      siret VARCHAR(20) DEFAULT '',
      naf_code VARCHAR(190) DEFAULT '',
      nafa_code VARCHAR(190) DEFAULT '',
      aprn_code VARCHAR(190) DEFAULT '',
      email VARCHAR(190) DEFAULT '',
      phone VARCHAR(50) DEFAULT '',
      website VARCHAR(190) DEFAULT '',
      address TEXT,
      address_extra TEXT,
      postal_code VARCHAR(20) DEFAULT '',
      city VARCHAR(120) DEFAULT '',
      signer_first_name VARCHAR(120) DEFAULT '',
      signer_last_name VARCHAR(120) DEFAULT '',
      signer_quality VARCHAR(190) DEFAULT '',
      signature_documents VARCHAR(255) DEFAULT '',
      copy_recipients TEXT,
      contacts_annexes LONGTEXT,
      contact_alert_at DATETIME DEFAULT NULL,
      notes LONGTEXT,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY name (name),
      KEY city (city)
    ) {$charset_collate};";

    $sql_contacts = "CREATE TABLE {$this->contact_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      company_id BIGINT UNSIGNED DEFAULT NULL,
      first_name VARCHAR(120) NOT NULL,
      last_name VARCHAR(120) NOT NULL,
      job_title VARCHAR(190) DEFAULT '',
      email VARCHAR(190) DEFAULT '',
      phone VARCHAR(50) DEFAULT '',
      notes LONGTEXT,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY company_id (company_id),
      KEY email (email)
    ) {$charset_collate};";

    $sql_documents = "CREATE TABLE {$this->document_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      company_id BIGINT UNSIGNED DEFAULT NULL,
      contact_id BIGINT UNSIGNED DEFAULT NULL,
      title VARCHAR(190) NOT NULL,
      document_type VARCHAR(100) DEFAULT '',
      file_url TEXT NOT NULL,
      file_path TEXT,
      mime_type VARCHAR(120) DEFAULT '',
      uploaded_by BIGINT UNSIGNED DEFAULT NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY company_id (company_id),
      KEY contact_id (contact_id),
      KEY document_type (document_type)
    ) {$charset_collate};";

    $sql_formations = "CREATE TABLE {$this->formation_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      title VARCHAR(190) NOT NULL,
      code VARCHAR(100) DEFAULT '',
      base_formation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
      variant_number INT UNSIGNED NOT NULL DEFAULT 0,
      status VARCHAR(50) DEFAULT 'VALIDÉE',
      qualiopi_compliant TINYINT(1) NOT NULL DEFAULT 1,
      duration VARCHAR(100) DEFAULT '',
      modality VARCHAR(100) DEFAULT '',
      description_text LONGTEXT,
      prerequisites LONGTEXT,
      objectives LONGTEXT,
      program LONGTEXT,
      address TEXT,
      postal_code VARCHAR(20) DEFAULT '',
      city VARCHAR(120) DEFAULT '',
      price_ht VARCHAR(50) DEFAULT '',
      program_file_url TEXT,
      include_bpf TINYINT(1) NOT NULL DEFAULT 1,
      service_objective VARCHAR(190) DEFAULT '',
      service_objective_level VARCHAR(190) DEFAULT '',
      specialty VARCHAR(190) DEFAULT '',
      shared_docs LONGTEXT,
      internal_docs LONGTEXT,
      shared_links LONGTEXT,
      convocation_enabled TINYINT(1) NOT NULL DEFAULT 1,
      positioning_test_enabled TINYINT(1) NOT NULL DEFAULT 0,
      diagnostic_evaluation_enabled TINYINT(1) NOT NULL DEFAULT 1,
      intermediate_survey_enabled TINYINT(1) NOT NULL DEFAULT 0,
      hot_survey_enabled TINYINT(1) NOT NULL DEFAULT 1,
      evaluation_enabled TINYINT(1) NOT NULL DEFAULT 1,
      end_documents_enabled TINYINT(1) NOT NULL DEFAULT 1,
      cold_survey_enabled TINYINT(1) NOT NULL DEFAULT 0,
      trainer_survey_enabled TINYINT(1) NOT NULL DEFAULT 1,
      company_survey_enabled TINYINT(1) NOT NULL DEFAULT 1,
      catalog_public TINYINT(1) NOT NULL DEFAULT 1,
      catalog_slug VARCHAR(190) DEFAULT '',
      catalog_image_url TEXT,
      catalog_order INT DEFAULT 0,
      catalog_audience LONGTEXT,
      future_sessions LONGTEXT,
      is_draft TINYINT(1) NOT NULL DEFAULT 0,
      notes LONGTEXT,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY title (title),
      KEY code (code),
      KEY status (status),
      KEY is_active (is_active),
      KEY is_draft (is_draft)
    ) {$charset_collate};";

    $sql_sessions = "CREATE TABLE {$this->session_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      formation_id BIGINT UNSIGNED DEFAULT NULL,
      company_id BIGINT UNSIGNED DEFAULT NULL,
      title VARCHAR(190) NOT NULL,
      session_type VARCHAR(50) DEFAULT '',
      attendance_method VARCHAR(50) DEFAULT '',
      session_format VARCHAR(50) DEFAULT '',
      start_at DATETIME DEFAULT NULL,
      end_at DATETIME DEFAULT NULL,
      start_date DATE DEFAULT NULL,
      end_date DATE DEFAULT NULL,
      location VARCHAR(190) DEFAULT '',
      remote_link TEXT,
      status VARCHAR(50) DEFAULT 'Planifiée',
      max_learners INT UNSIGNED DEFAULT 0,
      is_draft TINYINT(1) NOT NULL DEFAULT 0,
      schedule_json LONGTEXT,
      notes LONGTEXT,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY formation_id (formation_id),
      KEY company_id (company_id),
      KEY status (status),
      KEY session_type (session_type),
      KEY is_draft (is_draft)
    ) {$charset_collate};";

    $sql_learners = "CREATE TABLE {$this->learner_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      company_id BIGINT UNSIGNED DEFAULT NULL,
      session_id BIGINT UNSIGNED DEFAULT NULL,
      prospect_id BIGINT UNSIGNED DEFAULT NULL,
      gender VARCHAR(30) DEFAULT '',
      first_name VARCHAR(120) NOT NULL,
      last_name VARCHAR(120) DEFAULT '',
      usage_last_name VARCHAR(120) DEFAULT '',
      birth_last_name VARCHAR(120) DEFAULT '',
      email VARCHAR(190) DEFAULT '',
      phone VARCHAR(50) DEFAULT '',
      birth_date DATE DEFAULT NULL,
      birth_place VARCHAR(190) DEFAULT '',
      address TEXT,
      address_extra TEXT,
      postal_code VARCHAR(20) DEFAULT '',
      city VARCHAR(120) DEFAULT '',
      is_france_travail VARCHAR(20) DEFAULT '',
      socio_category VARCHAR(120) DEFAULT '',
      education_level VARCHAR(120) DEFAULT '',
      job_title VARCHAR(190) DEFAULT '',
      contact_alert_at DATETIME DEFAULT NULL,
      accessibility_needs LONGTEXT,
      comment_text LONGTEXT,
      post_comment_text LONGTEXT,
      status VARCHAR(50) DEFAULT 'Pré-inscrit',
      funding VARCHAR(100) DEFAULT '',
      notes LONGTEXT,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY company_id (company_id),
      KEY session_id (session_id),
      KEY prospect_id (prospect_id),
      KEY email (email),
      KEY status (status),
      KEY updated_at (updated_at),
      KEY session_status (session_id, status)
    ) {$charset_collate};";

    $sql_needs = "CREATE TABLE {$this->need_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      company_id BIGINT UNSIGNED DEFAULT NULL,
      contact_id BIGINT UNSIGNED DEFAULT NULL,
      source_prospect_id BIGINT UNSIGNED DEFAULT NULL,
      theme VARCHAR(120) DEFAULT '',
      collection_channel VARCHAR(120) DEFAULT '',
      collection_date DATE DEFAULT NULL,
      acdc_interlocutor VARCHAR(190) DEFAULT '',
      status VARCHAR(50) DEFAULT 'Nouveau',
      expressed_need LONGTEXT,
      reframed_need LONGTEXT,
      context_text LONGTEXT,
      current_situation LONGTEXT,
      target_audience LONGTEXT,
      audience_level VARCHAR(100) DEFAULT '',
      learners_count VARCHAR(50) DEFAULT '',
      expected_objectives LONGTEXT,
      constraints_text LONGTEXT,
      urgency VARCHAR(50) DEFAULT '',
      desired_deadline DATE DEFAULT NULL,
      budget VARCHAR(100) DEFAULT '',
      desired_format VARCHAR(100) DEFAULT '',
      planned_funding VARCHAR(100) DEFAULT '',
      next_action LONGTEXT,
      next_action_date DATE DEFAULT NULL,
      priority_level VARCHAR(50) DEFAULT 'Normale',
      internal_summary LONGTEXT,
      created_by BIGINT UNSIGNED DEFAULT NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY company_id (company_id),
      KEY contact_id (contact_id),
      KEY source_prospect_id (source_prospect_id),
      KEY status (status),
      KEY collection_date (collection_date)
    ) {$charset_collate};";

    $sql_prospects = "CREATE TABLE {$this->prospect_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      profile_type VARCHAR(60) DEFAULT '',
      gender VARCHAR(30) DEFAULT '',
      first_name VARCHAR(120) DEFAULT '',
      last_name VARCHAR(120) DEFAULT '',
      company_name VARCHAR(190) DEFAULT '',
      siret VARCHAR(30) DEFAULT '',
      naf_code VARCHAR(190) DEFAULT '',
      address TEXT,
      postal_code VARCHAR(20) DEFAULT '',
      city VARCHAR(120) DEFAULT '',
      email VARCHAR(190) DEFAULT '',
      phone VARCHAR(50) DEFAULT '',
      is_france_travail VARCHAR(20) DEFAULT '',
      birth_date DATE DEFAULT NULL,
      job_title VARCHAR(190) DEFAULT '',
      education_level VARCHAR(120) DEFAULT '',
      accessibility_needs LONGTEXT,
      signer_first_name VARCHAR(120) DEFAULT '',
      signer_last_name VARCHAR(120) DEFAULT '',
      signer_quality VARCHAR(190) DEFAULT '',
      signer_email VARCHAR(190) DEFAULT '',
      signer_phone VARCHAR(50) DEFAULT '',
      company_phone VARCHAR(50) DEFAULT '',
      additional_contacts_json LONGTEXT,
      desired_training VARCHAR(190) DEFAULT '',
      session_label VARCHAR(190) DEFAULT '',
      status VARCHAR(60) DEFAULT 'À traiter',
      rdv_at DATETIME DEFAULT NULL,
      rdv_notes LONGTEXT,
      last_followup VARCHAR(190) DEFAULT '',
      assigned_to VARCHAR(190) DEFAULT '',
      source VARCHAR(190) DEFAULT '',
      copy_recipients TEXT,
      copy_recipients_json LONGTEXT,
      comment_text LONGTEXT,
      contact_alert_at DATETIME DEFAULT NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY email (email),
      KEY status (status),
      KEY rdv_at (rdv_at),
      KEY siret (siret)
    ) {$charset_collate};";


    $sql_prospect_rdvs = "CREATE TABLE {$this->prospect_rdv_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      prospect_id BIGINT UNSIGNED NOT NULL,
      rdv_at DATETIME DEFAULT NULL,
      timezone_label VARCHAR(120) DEFAULT '',
      info_html LONGTEXT,
      comment_text LONGTEXT,
      notify_email TINYINT(1) NOT NULL DEFAULT 1,
      email_subject VARCHAR(190) DEFAULT '',
      email_to VARCHAR(190) DEFAULT '',
      email_cc LONGTEXT,
      email_sent_at DATETIME DEFAULT NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY prospect_id (prospect_id),
      KEY rdv_at (rdv_at),
      KEY email_sent_at (email_sent_at)
    ) {$charset_collate};";


    $sql_prospect_activities = "CREATE TABLE {$this->prospect_activity_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      prospect_id BIGINT UNSIGNED NOT NULL,
      activity_type VARCHAR(40) NOT NULL DEFAULT 'note',
      direction VARCHAR(20) DEFAULT '',
      channel VARCHAR(60) DEFAULT '',
      subject VARCHAR(255) DEFAULT '',
      summary LONGTEXT,
      outcome VARCHAR(120) DEFAULT '',
      ref_type VARCHAR(40) DEFAULT '',
      ref_id VARCHAR(190) DEFAULT '',
      occurred_at DATETIME DEFAULT NULL,
      created_by VARCHAR(190) DEFAULT '',
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY prospect_id (prospect_id),
      KEY occurred_at (occurred_at)
    ) {$charset_collate};";


    $sql_groups = "CREATE TABLE {$this->group_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      name VARCHAR(190) NOT NULL,
      comment_text LONGTEXT,
      formation_id BIGINT UNSIGNED DEFAULT NULL,
      session_id BIGINT UNSIGNED DEFAULT NULL,
      attendance_method VARCHAR(100) DEFAULT '',
      trainer_name VARCHAR(190) DEFAULT '',
      learner_ids LONGTEXT,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY name (name),
      KEY formation_id (formation_id),
      KEY session_id (session_id)
    ) {$charset_collate};";

    

    $sql_funders = "CREATE TABLE {$this->funder_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      name VARCHAR(190) NOT NULL DEFAULT '',
      sector VARCHAR(190) DEFAULT '',
      address VARCHAR(255) DEFAULT '',
      city VARCHAR(120) DEFAULT '',
      postal_code VARCHAR(20) DEFAULT '',
      funder_type VARCHAR(120) NOT NULL DEFAULT '',
      phone VARCHAR(50) DEFAULT '',
      email VARCHAR(190) DEFAULT '',
      website VARCHAR(255) DEFAULT '',
      contact_type VARCHAR(120) DEFAULT '',
      has_paca_presence VARCHAR(10) DEFAULT '',
      paca_contact_details TEXT NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY name (name),
      KEY funder_type (funder_type),
      KEY email (email),
      KEY postal_code (postal_code)
    ) {$charset_collate};";

    $sql_quizzes = "CREATE TABLE {$this->quiz_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      title VARCHAR(190) NOT NULL,
      description_text LONGTEXT,
      correction_type VARCHAR(50) NOT NULL DEFAULT 'Correction automatique',
      duration_minutes INT UNSIGNED NOT NULL DEFAULT 5,
      question_blocks LONGTEXT,
      scoring_blocks LONGTEXT,
      alert_notation VARCHAR(50) DEFAULT '',
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY title (title),
      KEY correction_type (correction_type)
    ) {$charset_collate};";

    /* ACDC 3.25.278 — Table retirée avec son module. */

    $sql_evaluations = "CREATE TABLE {$this->evaluation_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      title VARCHAR(190) NOT NULL,
      description_text LONGTEXT,
      correction_type VARCHAR(50) NOT NULL DEFAULT 'Correction automatique',
      duration_minutes INT UNSIGNED NOT NULL DEFAULT 5,
      formation_ids LONGTEXT,
      question_blocks LONGTEXT,
      scoring_blocks LONGTEXT,
      alert_notation VARCHAR(50) DEFAULT '',
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY title (title),
      KEY correction_type (correction_type)
    ) {$charset_collate};";

    $sql_need_analyses = "CREATE TABLE {$this->need_analysis_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      title VARCHAR(190) NOT NULL,
      description_text LONGTEXT,
      analysis_type VARCHAR(50) NOT NULL DEFAULT 'Entreprise',
      blocks_json LONGTEXT,
      is_model TINYINT(1) NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY title (title),
      KEY analysis_type (analysis_type),
      KEY is_model (is_model)
    ) {$charset_collate};";

    $sql_registration_contracts = "CREATE TABLE {$this->registration_contract_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      title VARCHAR(190) NOT NULL,
      generate_mode VARCHAR(120) DEFAULT '',
      commanditaire_type VARCHAR(50) NOT NULL DEFAULT 'Entreprise',
      source_prospect_id BIGINT UNSIGNED DEFAULT NULL,
      company_id BIGINT UNSIGNED DEFAULT NULL,
      learner_ids LONGTEXT,
      formation_id BIGINT UNSIGNED DEFAULT NULL,
      formation_title VARCHAR(190) DEFAULT '',
      start_date DATE DEFAULT NULL,
      end_date DATE DEFAULT NULL,
      legal_reference LONGTEXT,
      objectives_text LONGTEXT,
      accessibility_handicap LONGTEXT,
      material_environment LONGTEXT,
      implementation_followup_evaluation LONGTEXT,
      cancellation_terms LONGTEXT,
      price_ht VARCHAR(50) DEFAULT '',
      vat_rate VARCHAR(20) DEFAULT '',
      deposit_enabled TINYINT(1) NOT NULL DEFAULT 0,
      deposit_amount_ht VARCHAR(50) DEFAULT '',
      public_funding VARCHAR(50) DEFAULT '',
      transport_fees_enabled TINYINT(1) NOT NULL DEFAULT 0,
      transport_fees_amount_ht VARCHAR(50) DEFAULT '',
      meal_fees_enabled TINYINT(1) NOT NULL DEFAULT 0,
      meal_fees_amount_ht VARCHAR(50) DEFAULT '',
      financial_provisions LONGTEXT,
      payment_terms LONGTEXT,
      disputes_terms LONGTEXT,
      withdrawal_delay_days VARCHAR(20) DEFAULT '',
      additional_sections LONGTEXT,
      document_url TEXT,
      document_path TEXT,
      signature_request_id BIGINT UNSIGNED DEFAULT NULL,
      signature_status VARCHAR(50) DEFAULT '',
      signature_sent_at DATETIME DEFAULT NULL,
      signature_completed_at DATETIME DEFAULT NULL,
      signed_document_url TEXT,
      signed_document_path TEXT,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY title (title),
      KEY commanditaire_type (commanditaire_type),
      KEY source_prospect_id (source_prospect_id),
      KEY company_id (company_id),
      KEY formation_id (formation_id),
      KEY signature_request_id (signature_request_id)
    ) {$charset_collate};";

    $sql_pre_meetings = "CREATE TABLE {$this->pre_meeting_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      learner_id BIGINT UNSIGNED DEFAULT NULL,
      learner_label VARCHAR(190) DEFAULT '',
      formation_id BIGINT UNSIGNED DEFAULT NULL,
      formation_title VARCHAR(190) DEFAULT '',
      rdv_at DATETIME DEFAULT NULL,
      duration_label VARCHAR(20) DEFAULT '',
      trainer_id BIGINT UNSIGNED DEFAULT NULL,
      trainer_label VARCHAR(190) DEFAULT '',
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY learner_id (learner_id),
      KEY formation_id (formation_id),
      KEY trainer_id (trainer_id),
      KEY rdv_at (rdv_at)
    ) {$charset_collate};";

    $sql_training_registrations = "CREATE TABLE {$this->training_registration_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      title VARCHAR(190) NOT NULL,
      belongs_to_group VARCHAR(10) DEFAULT '',
      autofill_contract_id BIGINT UNSIGNED DEFAULT NULL,
      autofill_contract_label VARCHAR(190) DEFAULT '',
      group_id BIGINT UNSIGNED DEFAULT NULL,
      group_label VARCHAR(190) DEFAULT '',
      learner_id BIGINT UNSIGNED DEFAULT NULL,
      learner_label VARCHAR(190) DEFAULT '',
      learner_ids LONGTEXT,
      learners_label LONGTEXT,
      company_id BIGINT UNSIGNED DEFAULT NULL,
      company_label VARCHAR(190) DEFAULT '',
      formation_id BIGINT UNSIGNED DEFAULT NULL,
      formation_title VARCHAR(190) DEFAULT '',
      extranet_access TINYINT(1) NOT NULL DEFAULT 1,
      price_ht VARCHAR(50) DEFAULT '',
      transport_fees_enabled TINYINT(1) NOT NULL DEFAULT 0,
      meal_fees_enabled TINYINT(1) NOT NULL DEFAULT 0,
      convocation_document_url TEXT,
      convocation_document_path TEXT,
      positioning_result_document_url TEXT,
      positioning_result_document_path TEXT,
      diagnostic_result_document_url TEXT,
      diagnostic_result_document_path TEXT,
      mid_survey_document_url TEXT,
      mid_survey_document_path TEXT,
      hot_survey_document_url TEXT,
      hot_survey_document_path TEXT,
      cold_survey_document_url TEXT,
      cold_survey_document_path TEXT,
      evaluation_result_document_url TEXT,
      evaluation_result_document_path TEXT,
      completion_certificate_document_url TEXT,
      completion_certificate_document_path TEXT,
      end_training_certificate_document_url TEXT,
      end_training_certificate_document_path TEXT,
      is_draft TINYINT(1) NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY title (title),
      KEY group_id (group_id),
      KEY learner_id (learner_id),
      KEY company_id (company_id),
      KEY formation_id (formation_id),
      KEY is_draft (is_draft)
    ) {$charset_collate};";

dbDelta( $sql_companies );
    $this->maybe_add_table_column( $this->company_table, 'naf_code', "VARCHAR(190) DEFAULT ''" );
    $this->maybe_add_table_column( $this->company_table, 'nafa_code', "VARCHAR(190) DEFAULT ''" );
    $this->maybe_add_table_column( $this->company_table, 'aprn_code', "VARCHAR(190) DEFAULT ''" );
    $this->maybe_add_table_column( $this->company_table, 'address_extra', 'TEXT NULL' );
    $this->maybe_add_table_column( $this->company_table, 'signer_first_name', "VARCHAR(120) DEFAULT ''" );
    $this->maybe_add_table_column( $this->company_table, 'signer_last_name', "VARCHAR(120) DEFAULT ''" );
    $this->maybe_add_table_column( $this->company_table, 'signer_quality', "VARCHAR(190) DEFAULT ''" );
    $this->maybe_add_table_column( $this->company_table, 'signature_documents', "VARCHAR(255) DEFAULT ''" );
    $this->maybe_add_table_column( $this->company_table, 'copy_recipients', 'TEXT NULL' );
    $this->maybe_add_table_column( $this->company_table, 'contacts_annexes', 'LONGTEXT NULL' );
    $this->maybe_add_table_column( $this->company_table, 'contact_alert_at', 'DATETIME DEFAULT NULL' );
    dbDelta( $sql_contacts );
    dbDelta( $sql_documents );
    dbDelta( $sql_formations );
    $this->maybe_add_table_column( $this->formation_table, 'code', "VARCHAR(100) DEFAULT ''" );
    $this->maybe_add_table_column( $this->formation_table, 'base_formation_id', "BIGINT UNSIGNED NOT NULL DEFAULT 0" );
    $this->maybe_add_table_column( $this->formation_table, 'variant_number', "INT UNSIGNED NOT NULL DEFAULT 0" );
    /* ACDC 3.20.76 — niveau de qualification (visible quand l'objectif est une formation RNCP). */
    $this->maybe_add_table_column( $this->formation_table, 'service_objective_level', "VARCHAR(190) DEFAULT ''" );
    dbDelta( $sql_sessions );
    dbDelta( $sql_learners );
    dbDelta( $sql_needs );
    dbDelta( $sql_prospects );
    dbDelta( $sql_prospect_rdvs );
    dbDelta( $sql_prospect_activities );
    dbDelta( $sql_groups );
    $sql_trainers = "CREATE TABLE {$this->trainer_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      is_self_trainer TINYINT(1) NOT NULL DEFAULT 0,
      photo_url TEXT,
      gender VARCHAR(30) DEFAULT '',
      first_name VARCHAR(120) NOT NULL,
      last_name VARCHAR(120) NOT NULL,
      email VARCHAR(190) DEFAULT '',
      phone VARCHAR(50) DEFAULT '',
      birth_date DATE DEFAULT NULL,
      nda_number VARCHAR(120) DEFAULT '',
      role_name VARCHAR(120) DEFAULT 'Formateur simple',
      trainer_type VARCHAR(120) DEFAULT '',
      description_text LONGTEXT,
      availability_json LONGTEXT,
      session_reminder_enabled TINYINT(1) NOT NULL DEFAULT 1,
      session_start_enabled TINYINT(1) NOT NULL DEFAULT 1,
      contact_alert_at DATETIME DEFAULT NULL,
      comment_text LONGTEXT,
      learner_info_photo TINYINT(1) NOT NULL DEFAULT 0,
      learner_info_name TINYINT(1) NOT NULL DEFAULT 0,
      learner_info_description TINYINT(1) NOT NULL DEFAULT 0,
      learner_info_availability TINYINT(1) NOT NULL DEFAULT 0,
      siret VARCHAR(30) DEFAULT '',
      access_enabled TINYINT(1) NOT NULL DEFAULT 1,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY email (email),
      KEY last_name (last_name),
      KEY role_name (role_name)
    ) {$charset_collate};";

    dbDelta( $sql_funders );
    dbDelta( $sql_trainers );

    /* ACDC 3.20.82 — Extension de wp_acdc_of_trainers pour le module portail formateur.
       Ajout idempotent via maybe_add_table_column pour ne pas casser les bases existantes. */
    $this->maybe_add_table_column( $this->trainer_table, 'user_id', "BIGINT UNSIGNED NOT NULL DEFAULT 0" );
    $this->maybe_add_table_column( $this->trainer_table, 'permissions_json', "LONGTEXT" );
    $this->maybe_add_table_column( $this->trainer_table, 'permission_profile', "VARCHAR(20) NOT NULL DEFAULT 'simple'" );
    $this->maybe_add_table_column( $this->trainer_table, 'legal_status', "VARCHAR(50) DEFAULT ''" );
    $this->maybe_add_table_column( $this->trainer_table, 'urssaf_attestation_expires_at', "DATE DEFAULT NULL" );
    $this->maybe_add_table_column( $this->trainer_table, 'rc_pro_expires_at', "DATE DEFAULT NULL" );
    $this->maybe_add_table_column( $this->trainer_table, 'expertise_nsf_codes', "TEXT" );
    $this->maybe_add_table_column( $this->trainer_table, 'invited_at', "DATETIME DEFAULT NULL" );

    /* ACDC 3.20.92 — Lien direct entre groupes/sessions et la fiche formateur authentifiée.
       Avant la 3.20.92, l'identification du formateur sur un groupe se faisait uniquement
       via la chaîne libre `trainer_name` (jamais alimentée par l'UI active).
       Ces colonnes posent une référence id-based vers wp_acdc_of_trainers, indispensable pour
       l'onglet « Mes sessions » du portail formateur. Idempotent via maybe_add_table_column.
       Backfill automatique au déploiement : voir backfill_groups_and_sessions_trainer_ids(). */
    $this->maybe_add_table_column( $this->group_table, 'trainer_id', 'BIGINT UNSIGNED NULL' );
    $this->maybe_add_table_index( $this->group_table, 'trainer_id', 'INDEX trainer_id (trainer_id)' );
    $this->maybe_add_table_column( $this->session_table, 'trainer_id', 'BIGINT UNSIGNED NULL' );
    $this->maybe_add_table_index( $this->session_table, 'trainer_id', 'INDEX trainer_id (trainer_id)' );

    /* ACDC 3.20.82 — Bibliothèque personnelle du formateur (CV, diplômes, attestations, etc.). */
    $sql_trainer_documents = "CREATE TABLE {$this->trainer_document_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      trainer_id BIGINT UNSIGNED NOT NULL,
      category VARCHAR(50) NOT NULL DEFAULT 'other',
      label VARCHAR(255) NOT NULL DEFAULT '',
      file_url TEXT,
      file_name VARCHAR(255) DEFAULT '',
      issued_at DATE DEFAULT NULL,
      expires_at DATE DEFAULT NULL,
      uploaded_by VARCHAR(20) NOT NULL DEFAULT 'admin',
      uploader_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
      is_qualiopi_proof TINYINT(1) NOT NULL DEFAULT 0,
      notes_admin LONGTEXT,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY trainer_id (trainer_id),
      KEY category (category),
      KEY expires_at (expires_at)
    ) {$charset_collate};";
    dbDelta( $sql_trainer_documents );

    /* ACDC 3.20.82 — Ressources pédagogiques que le formateur partage avec les apprenants. */
    $sql_trainer_resources = "CREATE TABLE {$this->trainer_resource_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      trainer_id BIGINT UNSIGNED NOT NULL,
      formation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
      session_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
      label VARCHAR(255) NOT NULL DEFAULT '',
      file_url TEXT,
      file_name VARCHAR(255) DEFAULT '',
      external_url TEXT,
      description_text TEXT,
      is_visible_to_learners TINYINT(1) NOT NULL DEFAULT 1,
      published_at DATETIME DEFAULT NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY trainer_id (trainer_id),
      KEY formation_id (formation_id),
      KEY session_id (session_id)
    ) {$charset_collate};";
    dbDelta( $sql_trainer_resources );

    /* ACDC 3.25.207 — Documents de séance : la table existait depuis la 3.20.82,
       déclarée et créée, mais jamais écrite ni lue. Elle est faite pour cela —
       « ressources pédagogiques que le formateur partage avec les apprenants ».
       On la complète plutôt que d'en créer une seconde à côté : deux tables pour
       la même chose finissent toujours par diverger.
       Quatre colonnes manquaient au besoin réel : le destinataire nommé, le
       moment de visibilité, l'auteur du dépôt et l'avis aux apprenants. */
    $this->maybe_add_table_column( $this->trainer_resource_table, 'learner_id', 'BIGINT UNSIGNED NOT NULL DEFAULT 0' );
    $this->maybe_add_table_column( $this->trainer_resource_table, 'visibility', "VARCHAR(20) NOT NULL DEFAULT 'unlock'" );
    $this->maybe_add_table_column( $this->trainer_resource_table, 'notify_learners', 'TINYINT(1) NOT NULL DEFAULT 0' );
    $this->maybe_add_table_column( $this->trainer_resource_table, 'uploaded_by', "VARCHAR(20) NOT NULL DEFAULT 'trainer'" );
    $this->maybe_add_table_column( $this->trainer_resource_table, 'uploader_user_id', 'BIGINT UNSIGNED NOT NULL DEFAULT 0' );
    $this->maybe_add_table_index( $this->trainer_resource_table, 'learner_id', 'INDEX learner_id (learner_id)' );

    /* Le déblocage anticipé de l'extranet apprenant se décide séance par séance :
       c'est le formateur qui sait que sa formation est finie avant l'heure. */
    $this->maybe_add_table_column( $this->session_table, 'documents_unlocked_at', 'DATETIME NULL' );

    /* ACDC 3.20.83 — Auth custom du portail formateur (4 tables symétriques à l'apprenant). */
    $sql_trainer_portal_accounts = "CREATE TABLE {$this->trainer_portal_account_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      trainer_id BIGINT UNSIGNED NOT NULL,
      email VARCHAR(190) NOT NULL,
      password_hash VARCHAR(255) NOT NULL,
      status VARCHAR(40) NOT NULL DEFAULT 'never_activated',
      must_change_password TINYINT(1) NOT NULL DEFAULT 1,
      first_activated_at DATETIME NULL,
      last_login_at DATETIME NULL,
      failed_login_count INT UNSIGNED NOT NULL DEFAULT 0,
      blocked_until DATETIME NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY email (email),
      KEY trainer_id (trainer_id),
      KEY status (status),
      KEY blocked_until (blocked_until)
    ) {$charset_collate};";
    dbDelta( $sql_trainer_portal_accounts );

    $sql_trainer_portal_tokens = "CREATE TABLE {$this->trainer_portal_token_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      account_id BIGINT UNSIGNED NOT NULL,
      token_hash VARCHAR(255) NOT NULL,
      token_type VARCHAR(40) NOT NULL,
      expires_at DATETIME NOT NULL,
      consumed_at DATETIME NULL,
      revoked_at DATETIME NULL,
      meta_json LONGTEXT NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY account_id (account_id),
      KEY token_hash (token_hash(191)),
      KEY token_type (token_type),
      KEY expires_at (expires_at)
    ) {$charset_collate};";
    dbDelta( $sql_trainer_portal_tokens );

    $sql_trainer_portal_sessions = "CREATE TABLE {$this->trainer_portal_session_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      account_id BIGINT UNSIGNED NOT NULL,
      session_hash VARCHAR(255) NOT NULL,
      ip_address VARCHAR(100) NULL,
      user_agent VARCHAR(255) NULL,
      expires_at DATETIME NOT NULL,
      last_activity_at DATETIME NOT NULL,
      revoked_at DATETIME NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY account_id (account_id),
      KEY session_hash (session_hash(191)),
      KEY expires_at (expires_at),
      KEY revoked_at (revoked_at)
    ) {$charset_collate};";
    dbDelta( $sql_trainer_portal_sessions );

    $sql_trainer_portal_logs = "CREATE TABLE {$this->trainer_portal_log_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      account_id BIGINT UNSIGNED NULL,
      trainer_id BIGINT UNSIGNED NULL,
      event_type VARCHAR(60) NOT NULL,
      ip_address VARCHAR(100) NULL,
      user_agent VARCHAR(255) NULL,
      event_data LONGTEXT NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY account_id (account_id),
      KEY trainer_id (trainer_id),
      KEY event_type (event_type),
      KEY created_at (created_at)
    ) {$charset_collate};";
    dbDelta( $sql_trainer_portal_logs );

    dbDelta( $sql_quizzes );
    dbDelta( $sql_evaluations );
    dbDelta( $sql_need_analyses );

    /* ACDC 3.21.10 — Bibliothèque modulaire des blocs et questions. */
    $sql_need_blocks = "CREATE TABLE {$this->need_block_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      code VARCHAR(60) NOT NULL,
      nom VARCHAR(190) NOT NULL,
      description LONGTEXT,
      type_bloc VARCHAR(30) NOT NULL DEFAULT 'personnalise',
      profil_lie VARCHAR(60) DEFAULT NULL,
      thematique_liee VARCHAR(60) DEFAULT NULL,
      formation_liee BIGINT UNSIGNED DEFAULT NULL,
      ordre INT UNSIGNED NOT NULL DEFAULT 10,
      actif TINYINT(1) NOT NULL DEFAULT 1,
      verrouille TINYINT(1) NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY code (code),
      KEY type_bloc (type_bloc),
      KEY actif (actif)
    ) {$charset_collate};";
    $sql_need_questions = "CREATE TABLE {$this->need_question_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      bloc_id BIGINT UNSIGNED NOT NULL,
      code VARCHAR(100) NOT NULL,
      ordre INT UNSIGNED NOT NULL DEFAULT 10,
      libelle LONGTEXT NOT NULL,
      type_reponse VARCHAR(30) NOT NULL DEFAULT 'texte_court',
      obligatoire TINYINT(1) NOT NULL DEFAULT 0,
      options LONGTEXT DEFAULT NULL,
      aide TEXT DEFAULT NULL,
      placeholder VARCHAR(255) DEFAULT NULL,
      condition_affichage LONGTEXT DEFAULT NULL,
      source_prefill VARCHAR(190) DEFAULT NULL,
      visible_admin TINYINT(1) NOT NULL DEFAULT 1,
      visible_public TINYINT(1) NOT NULL DEFAULT 1,
      afficher_pdf TINYINT(1) NOT NULL DEFAULT 1,
      actif TINYINT(1) NOT NULL DEFAULT 1,
      verrouille TINYINT(1) NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY code (code),
      KEY bloc_id (bloc_id),
      KEY actif (actif)
    ) {$charset_collate};";
    dbDelta( $sql_need_blocks );
    dbDelta( $sql_need_questions );

    /* ACDC 3.21.15 — Table thématiques de formation. */
    $sql_thematiques = "CREATE TABLE {$this->thematique_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      code VARCHAR(60) NOT NULL,
      label VARCHAR(190) NOT NULL,
      image_hero_url TEXT DEFAULT NULL,
      icon_emoji VARCHAR(20) DEFAULT NULL,
      couleur_hex VARCHAR(7) DEFAULT NULL,
      bloc_nad_id BIGINT UNSIGNED DEFAULT NULL,
      ordre INT UNSIGNED NOT NULL DEFAULT 10,
      actif TINYINT(1) NOT NULL DEFAULT 1,
      verrouille TINYINT(1) NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY code (code),
      KEY actif (actif)
    ) {$charset_collate};";
    dbDelta( $sql_thematiques );
    $this->seed_thematiques();

    dbDelta( $sql_registration_contracts );
    // ACDC 3.21.08 — Champs signataire commanditaire
    $this->maybe_add_table_column( $this->registration_contract_table, 'commanditaire_last_name',           "VARCHAR(120) DEFAULT ''" );
    $this->maybe_add_table_column( $this->registration_contract_table, 'commanditaire_first_name',          "VARCHAR(120) DEFAULT ''" );
    $this->maybe_add_table_column( $this->registration_contract_table, 'commanditaire_signer_last_name',    "VARCHAR(120) DEFAULT ''" );
    $this->maybe_add_table_column( $this->registration_contract_table, 'commanditaire_signer_first_name',   "VARCHAR(120) DEFAULT ''" );
    $this->maybe_add_table_column( $this->registration_contract_table, 'public_funding_name',        "VARCHAR(120) DEFAULT ''" );
    $this->maybe_add_table_column( $this->registration_contract_table, 'public_funding_name_custom', "VARCHAR(190) DEFAULT ''" );
    /* ACDC 3.25.258 — LA CONVENTION PORTE LA DÉCISION DE FINANCEMENT.
       Jusqu'ici elle ne retenait qu'un NOM de financeur, choisi dans une liste
       qui mêlait le répertoire et quinze valeurs écrites en dur. Un nom ne
       permet ni d'adresser une facture, ni de retrouver l'interlocuteur : on
       rattache donc la fiche elle-même. S'y ajoutent les deux informations qui
       décident de la facturation — la subrogation de paiement, et le montant
       accordé avec sa référence d'accord. */
    $this->maybe_add_table_column( $this->registration_contract_table, 'funder_id',             'BIGINT UNSIGNED DEFAULT NULL' );
    $this->maybe_add_table_column( $this->registration_contract_table, 'funding_subrogation',   'TINYINT(1) NOT NULL DEFAULT 0' );
    $this->maybe_add_table_column( $this->registration_contract_table, 'funding_pec_reference', "VARCHAR(120) DEFAULT ''" );
    $this->maybe_add_table_column( $this->registration_contract_table, 'funding_pec_amount_ht', "VARCHAR(50) DEFAULT ''" );
    /* Le devis d'origine : sans lui, la facture ne peut pas retrouver la
       convention qui décide de son destinataire. */
    $this->maybe_add_table_column( $this->registration_contract_table, 'quote_id',              'BIGINT UNSIGNED DEFAULT NULL' );
    /* ACDC 3.25.265 — LE FORMATEUR DE LA CONVENTION.
       La convention crée les séances au moment de sa signature, et le code qui
       les crée lisait déjà $contract->trainer_id — une colonne qui n'existait
       pas. Toute séance née d'une convention naissait donc SANS formateur, et
       il n'y avait aucun écran pour le désigner dans ce parcours. Trois
       conséquences en chaîne : l'apprenant ne voyait pas son formateur
       référent, le rappel de contrat formateur annonçait « non désigné » sans
       recours, et la convocation ne pouvait pas le nommer. */
    $this->maybe_add_table_column( $this->registration_contract_table, 'trainer_id',            'BIGINT UNSIGNED DEFAULT NULL' );
    $this->maybe_add_table_index( $this->registration_contract_table, 'trainer_id', 'INDEX trainer_id (trainer_id)' );
    $this->maybe_add_table_index( $this->registration_contract_table, 'quote_id',  'INDEX quote_id (quote_id)' );
    $this->maybe_add_table_index( $this->registration_contract_table, 'funder_id', 'INDEX funder_id (funder_id)' );

    /* ACDC 3.25.258 — LA FACTURE DIT À QUI ELLE S'ADRESSE.
       Elle ne portait qu'un « financeur » en texte libre, jamais renseigné par
       la conversion. Ces colonnes disent, sans interprétation possible : à qui
       elle est adressée, pour quelle part, au titre de quel accord, et quelle
       est la facture jumelle en cas de prise en charge partielle. */
    $this->maybe_add_table_column( $this->invoice_table, 'funder_id',           'BIGINT UNSIGNED DEFAULT NULL' );
    $this->maybe_add_table_column( $this->invoice_table, 'billed_to',           "VARCHAR(10) NOT NULL DEFAULT 'client'" );
    $this->maybe_add_table_column( $this->invoice_table, 'pec_reference',       "VARCHAR(120) DEFAULT ''" );
    $this->maybe_add_table_column( $this->invoice_table, 'pec_subrogation',     'TINYINT(1) NOT NULL DEFAULT 0' );
    $this->maybe_add_table_column( $this->invoice_table, 'pec_total_ht',        'DECIMAL(12,2) NOT NULL DEFAULT 0' );
    $this->maybe_add_table_column( $this->invoice_table, 'sibling_invoice_id',  'BIGINT UNSIGNED DEFAULT NULL' );
    $this->maybe_add_table_column( $this->invoice_table, 'beneficiary_label',   "VARCHAR(190) DEFAULT ''" );
    $this->maybe_add_table_column( $this->invoice_table, 'contract_id',         'BIGINT UNSIGNED DEFAULT NULL' );
    $this->maybe_add_table_index( $this->invoice_table, 'funder_id', 'INDEX funder_id (funder_id)' );
    // ACDC 3.21.17 — Délai envoi analyses du besoin sur les conventions.
    $this->maybe_add_table_column( $this->registration_contract_table, 'nad_send_delay_days', 'SMALLINT UNSIGNED DEFAULT 0' );
    $this->maybe_add_table_column( $this->registration_contract_table, 'seances_dates', 'TEXT NULL' );
    // ACDC 3.21.17 — Colonnes relances sur les analyses du besoin.
    $this->maybe_add_table_column( $this->need_analysis_table, 'scheduled_send_at', 'DATETIME DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'relance_1_at',      'DATETIME DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'relance_2_at',      'DATETIME DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'relance_3_at',      'DATETIME DEFAULT NULL' );

    // ACDC 3.21.14 — Thématique sur la fiche formation.
    $this->maybe_add_table_column( $this->formation_table, 'thematique', 'VARCHAR(60) DEFAULT NULL' );

    // ACDC 3.21.22-hotfix23 — Programme pédagogique structuré (blocs Jour/Matin/Après-midi).
    $this->maybe_add_table_column( $this->formation_table, 'programme_detail', 'LONGTEXT DEFAULT NULL' );

    // ACDC 3.21.23 — Moyens pédagogiques, ressources, sanction et évaluations détaillées.
    $this->maybe_add_table_column( $this->formation_table, 'moyens_pedago',      'LONGTEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->formation_table, 'ressources',         'LONGTEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->formation_table, 'sanction',           'LONGTEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->formation_table, 'evaluation_entree',  'LONGTEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->formation_table, 'evaluation_sortie',  'LONGTEXT DEFAULT NULL' );

    // ACDC 3.21.36 — Champs étendus (sync manager + programme PDF complet).
    $this->maybe_add_table_column( $this->formation_table, 'accroche',            'LONGTEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->formation_table, 'trainer_ref',         'LONGTEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->formation_table, 'accessibilite',       'LONGTEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->formation_table, 'referent_handicap',   "VARCHAR(190) DEFAULT ''" );
    $this->maybe_add_table_column( $this->formation_table, 'suivi',               'LONGTEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->formation_table, 'lieu_acces',          'LONGTEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->formation_table, 'demarches',           'LONGTEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->formation_table, 'annulation',          'LONGTEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->formation_table, 'platform_url',        'TEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->formation_table, 'effectif_min',        'SMALLINT UNSIGNED NOT NULL DEFAULT 0' );
    $this->maybe_add_table_column( $this->formation_table, 'effectif_max',        'SMALLINT UNSIGNED NOT NULL DEFAULT 0' );
    $this->maybe_add_table_column( $this->formation_table, 'cpf_eligible',        'TINYINT(1) NOT NULL DEFAULT 0' );
    $this->maybe_add_table_column( $this->formation_table, 'cpf_code',            "VARCHAR(50) DEFAULT ''" );
    $this->maybe_add_table_column( $this->formation_table, 'financement',         'LONGTEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->formation_table, 'taux_reussite',       'SMALLINT UNSIGNED NOT NULL DEFAULT 0' );
    $this->maybe_add_table_column( $this->formation_table, 'taux_satisfaction',   'SMALLINT UNSIGNED NOT NULL DEFAULT 0' );
    $this->maybe_add_table_column( $this->formation_table, 'taux_recommandation', 'SMALLINT UNSIGNED NOT NULL DEFAULT 0' );
    $this->maybe_add_table_column( $this->formation_table, 'taux_completion',     'SMALLINT UNSIGNED NOT NULL DEFAULT 0' );
    $this->maybe_add_table_column( $this->formation_table, 'manager_formation_id', 'BIGINT UNSIGNED DEFAULT NULL' );
    // ACDC 3.21.55 — Colonnes programme enrichi depuis sync Manager.
    $this->maybe_add_table_column( $this->formation_table, 'objectifs_detail', 'LONGTEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->formation_table, 'tarifs_detail',    'LONGTEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->formation_table, 'jours_count',      'TINYINT UNSIGNED NOT NULL DEFAULT 0' );
    // ACDC 3.21.49 — Colonnes toggles Qualiopi (absentes du maybe_add — manquaient sur installations existantes).
    $this->maybe_add_table_column( $this->formation_table, 'convocation_enabled',          'TINYINT(1) NOT NULL DEFAULT 1' );
    /* ACDC 3.25.278 — Le test de positionnement vérifie les prérequis d'accès.
       Il n'entre pas dans le parcours par défaut : c'est une décision prise
       formation par formation, pas une étape de tous les parcours. */
    $this->maybe_add_table_column( $this->formation_table, 'positioning_test_enabled',     'TINYINT(1) NOT NULL DEFAULT 0' );
    /* ACDC 3.25.278 — L'évaluation diagnostique n'avait pas d'interrupteur : le
       quiz correspondant était préparé quoi qu'on décide, et l'écran ne
       proposait rien pour l'arrêter. */
    $this->maybe_add_table_column( $this->formation_table, 'diagnostic_evaluation_enabled','TINYINT(1) NOT NULL DEFAULT 1' );
    $this->maybe_add_table_column( $this->formation_table, 'intermediate_survey_enabled',  'TINYINT(1) NOT NULL DEFAULT 0' );
    $this->maybe_add_table_column( $this->formation_table, 'hot_survey_enabled',           'TINYINT(1) NOT NULL DEFAULT 1' );
    $this->maybe_add_table_column( $this->formation_table, 'evaluation_enabled',           'TINYINT(1) NOT NULL DEFAULT 1' );
    $this->maybe_add_table_column( $this->formation_table, 'end_documents_enabled',        'TINYINT(1) NOT NULL DEFAULT 1' );
    $this->maybe_add_table_column( $this->formation_table, 'cold_survey_enabled',          'TINYINT(1) NOT NULL DEFAULT 1' );
    $this->maybe_add_table_column( $this->formation_table, 'trainer_survey_enabled',       'TINYINT(1) NOT NULL DEFAULT 1' );
    $this->maybe_add_table_column( $this->formation_table, 'company_survey_enabled',       'TINYINT(1) NOT NULL DEFAULT 1' );
    $this->maybe_add_table_column( $this->formation_table, 'qualiopi_compliant',           'TINYINT(1) NOT NULL DEFAULT 1' );
    $this->maybe_add_table_column( $this->formation_table, 'funder_survey_enabled',        'TINYINT(1) NOT NULL DEFAULT 1' );
    // ACDC 3.24.8 — R2 : codes certification RNCP / RS (indicateurs 3 et 7).
    $this->maybe_add_table_column( $this->formation_table, 'rncp_code', "VARCHAR(60) DEFAULT ''" );
    $this->maybe_add_table_column( $this->formation_table, 'rs_code',   "VARCHAR(60) DEFAULT ''" );
    // ACDC 3.21.49 — Traçabilité des envois de convocation automatique.
    $this->maybe_add_table_column( $this->session_table, 'convocation_sent_at',        'DATETIME DEFAULT NULL' );
    $this->maybe_add_table_column( $this->session_table, 'convocation_reminder_sent_at', 'DATETIME DEFAULT NULL' );
    // ACDC 3.23.0 — Traçabilité envoi convocation commanditaire (entreprise).
    $this->maybe_add_table_column( $this->session_table, 'convocation_company_sent_at', 'DATETIME DEFAULT NULL' );
    /* ACDC 3.25.225 — L'attestation d'absence est la troisième pièce de fin de
       formation. Elle a besoin de ses propres colonnes : la confondre avec le
       certificat reviendrait à écraser l'une par l'autre. */
    $this->maybe_add_table_column( $this->training_registration_table, 'absence_certificate_document_url', 'TEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->training_registration_table, 'absence_certificate_document_path', 'TEXT DEFAULT NULL' );
    /* ACDC 3.25.280 — Le résultat de l'évaluation diagnostique se dépose sur le
       dossier, comme celui du test de positionnement et celui de l'évaluation
       des acquis. Il était le seul des trois quiz structurels à ne laisser
       aucune trace sur la fiche d'inscription. */
    $this->maybe_add_table_column( $this->training_registration_table, 'diagnostic_result_document_url',  'TEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->training_registration_table, 'diagnostic_result_document_path', 'TEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->session_table, 'completion_certificate_sent_at', 'DATETIME DEFAULT NULL' );
    $this->maybe_add_table_column( $this->session_table, 'end_training_certificate_sent_at', 'DATETIME DEFAULT NULL' );
    $this->maybe_add_table_column( $this->session_table, 'positioning_sent_at', 'DATETIME DEFAULT NULL' );
    /* ACDC 3.24.28 — M8b : Bilan post-formation formateur. */
    $this->maybe_add_table_column( $this->session_table, 'report_group_level',        "VARCHAR(50) DEFAULT ''" );
    $this->maybe_add_table_column( $this->session_table, 'report_objectives_reached', "VARCHAR(20) DEFAULT ''" );
    $this->maybe_add_table_column( $this->session_table, 'report_incidents',          'LONGTEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->session_table, 'report_recommendations',    'LONGTEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->session_table, 'report_submitted_at',       'DATETIME DEFAULT NULL' );
    $this->maybe_add_table_column( $this->prospect_table,  'desired_format',               "VARCHAR(60) DEFAULT ''" );

    // ACDC 3.21.10 — Module Analyse du besoin — colonnes modulaires.
    $this->maybe_add_table_column( $this->need_analysis_table, 'profil',                    'VARCHAR(30) DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'thematique',                'VARCHAR(60) DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'statut',                    "VARCHAR(30) NOT NULL DEFAULT 'brouillon'" );
    $this->maybe_add_table_column( $this->need_analysis_table, 'source_type',               'VARCHAR(50) DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'source_id',                 'BIGINT UNSIGNED DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'formation_id',              'BIGINT UNSIGNED DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'repondant_nom',             'VARCHAR(120) DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'repondant_prenom',          'VARCHAR(120) DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'repondant_email',           'VARCHAR(190) DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'entreprise_id',             'BIGINT UNSIGNED DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'apprenant_id',              'BIGINT UNSIGNED DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'dossier_id',                'BIGINT UNSIGNED DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'questions_snapshot',        'LONGTEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'reponses',                  'LONGTEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'synthese',                  'LONGTEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'token_public',              'VARCHAR(64) DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'token_expire_at',           'DATETIME DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'sent_at',                   'DATETIME DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'document_url_commanditaire',  'TEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'document_path_commanditaire', 'TEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'document_url_apprenant',      'TEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'document_path_apprenant',     'TEXT DEFAULT NULL' );
    /* ACDC 3.21.12 — Blocs sélectionnés (JSON array IDs ordonnés) + notes internes. */
    $this->maybe_add_table_column( $this->need_analysis_table, 'blocs_ids', 'LONGTEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_analysis_table, 'notes_internes', 'LONGTEXT DEFAULT NULL' );
    // ACDC 3.21.29 — Masquage automatique côté formulaire public si valeur connue via convention.
    $this->maybe_add_table_column( $this->need_question_table, 'masquer_si_prefill', 'TINYINT(1) NOT NULL DEFAULT 0' );
    // ACDC 3.21.29-hotfix4 — Préremplissage planning + champs entreprise : masqués si déjà connus.
    $wpdb->query( $wpdb->prepare(
      "UPDATE {$this->need_question_table} SET source_prefill = %s WHERE code = %s AND (source_prefill IS NULL OR source_prefill = '')",
      'dossier.planning', 'ent_planning'
    ) );
    // Masquer si prérempli : tous les champs déjà connus via la convention ou la proposition commerciale.
    $codes_masquer = array( 'ent_nb_participants', 'ent_planning', 'ent_modalite', 'ent_lieu', 'ent_opco' );
    foreach ( $codes_masquer as $code ) {
      $wpdb->query( $wpdb->prepare(
        "UPDATE {$this->need_question_table} SET masquer_si_prefill = 1 WHERE code = %s",
        $code
      ) );
    }
    // ACDC 3.21.29-hotfix4 — Préremplissage postes/métiers depuis le recueil des besoins.
    $wpdb->query( $wpdb->prepare(
      "UPDATE {$this->need_question_table} SET source_prefill = %s, masquer_si_prefill = 1 WHERE code = %s AND (source_prefill IS NULL OR source_prefill = '')",
      'source.target_audience', 'ent_postes'
    ) );
    // ACDC 3.21.29-hotfix4b — Planning et nb_groupes déplacés du NAD vers le recueil des besoins.
    $wpdb->query( $wpdb->prepare(
      "UPDATE {$this->need_question_table} SET actif = 0 WHERE code IN (%s, %s)",
      'ent_planning', 'ent_nb_groupes'
    ) );
    $this->maybe_add_table_column( $this->need_table, 'formation_planning',   "VARCHAR(100) DEFAULT ''" );
    $this->maybe_add_table_column( $this->need_table, 'formation_nb_groupes', "VARCHAR(50) DEFAULT ''"  );
    // Seed blocs et questions systeme si non encore initialises.
    $this->seed_need_blocks_and_questions();
    // ACDC 3.21.13 — Page publique formulaire analyse du besoin.
    $this->ensure_nad_public_page();

    dbDelta( $sql_training_registrations );
    // ACDC 3.23.2 — Statut global dossier de formation (workflow Qualiopi).
    $this->maybe_add_table_column( $this->training_registration_table, 'workflow_status', "VARCHAR(50) NOT NULL DEFAULT 'prospect_cree'" );
    // Migration : initialiser workflow_status sur les dossiers existants qui n'ont pas encore de statut.
    $wpdb->query( "UPDATE {$this->training_registration_table} SET workflow_status = 'prospect_cree' WHERE workflow_status = '' OR workflow_status IS NULL" );
    // ACDC 3.25.72 — Formateur désigné sur le dossier d'inscription.
    $this->maybe_add_table_column( $this->training_registration_table, 'trainer_id', 'BIGINT UNSIGNED NULL' );
    $this->maybe_add_table_column( $this->training_registration_table, 'trainer_label', "VARCHAR(190) DEFAULT ''" );
    dbDelta( $sql_pre_meetings );

    $sql_questionnaire_sessions = "CREATE TABLE {$this->questionnaire_session_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      source_type VARCHAR(50) NOT NULL DEFAULT '',
      source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
      session_title VARCHAR(190) NOT NULL,
      formation_id BIGINT UNSIGNED DEFAULT NULL,
      seance_id BIGINT UNSIGNED DEFAULT NULL,
      formateur_id BIGINT UNSIGNED DEFAULT NULL,
      session_date DATETIME DEFAULT NULL,
      status VARCHAR(30) NOT NULL DEFAULT 'brouillon',
      public_token VARCHAR(120) NOT NULL,
      public_url TEXT,
      qr_code_url TEXT,
      current_question_index INT UNSIGNED NOT NULL DEFAULT 0,
      pseudo_required TINYINT(1) NOT NULL DEFAULT 1,
      pseudo_editable TINYINT(1) NOT NULL DEFAULT 1,
      restrict_to_registered_learners TINYINT(1) NOT NULL DEFAULT 1,
      show_score_after_each_question TINYINT(1) NOT NULL DEFAULT 0,
      show_correction_after_each_question TINYINT(1) NOT NULL DEFAULT 0,
      show_final_score TINYINT(1) NOT NULL DEFAULT 1,
      session_settings_json LONGTEXT,
      started_at DATETIME DEFAULT NULL,
      ended_at DATETIME DEFAULT NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY public_token (public_token),
      KEY source_id (source_id),
      KEY source_type (source_type),
      KEY formation_id (formation_id),
      KEY seance_id (seance_id),
      KEY formateur_id (formateur_id),
      KEY status (status)
    ) {$charset_collate};";

    $sql_questionnaire_participants = "CREATE TABLE {$this->questionnaire_participant_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      session_id BIGINT UNSIGNED NOT NULL,
      apprenant_id BIGINT UNSIGNED DEFAULT NULL,
      participant_token VARCHAR(120) NOT NULL,
      pseudo VARCHAR(120) NOT NULL,
      device_type VARCHAR(20) DEFAULT '',
      participant_status VARCHAR(30) NOT NULL DEFAULT 'en_attente',
      joined_at DATETIME DEFAULT NULL,
      finished_at DATETIME DEFAULT NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY participant_token (participant_token),
      KEY session_id (session_id),
      KEY apprenant_id (apprenant_id)
    ) {$charset_collate};";

    $sql_questionnaire_answers = "CREATE TABLE {$this->questionnaire_answer_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      session_id BIGINT UNSIGNED NOT NULL,
      participant_id BIGINT UNSIGNED NOT NULL,
      question_index INT UNSIGNED NOT NULL DEFAULT 0,
      answer_value LONGTEXT,
      is_correct TINYINT(1) NOT NULL DEFAULT 0,
      points_awarded INT NOT NULL DEFAULT 0,
      answered_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY session_id (session_id),
      KEY participant_id (participant_id),
      KEY question_index (question_index)
    ) {$charset_collate};";

    dbDelta( $sql_questionnaire_sessions );
    dbDelta( $sql_questionnaire_participants );
    dbDelta( $sql_questionnaire_answers );

    $sql_questionnaire_models = "CREATE TABLE {$this->questionnaire_model_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      survey_type VARCHAR(50) NOT NULL,
      survey_subtype VARCHAR(50) NULL,
      title VARCHAR(255) NOT NULL,
      description LONGTEXT NULL,
      version_label VARCHAR(50) NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      is_default TINYINT(1) NOT NULL DEFAULT 0,
      questionnaire_json LONGTEXT NOT NULL,
      settings_json LONGTEXT NULL,
      created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY survey_type (survey_type),
      KEY survey_subtype (survey_subtype),
      KEY is_active (is_active),
      KEY is_default (is_default)
    ) {$charset_collate};";

    $sql_questionnaire_actions = "CREATE TABLE {$this->questionnaire_action_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      questionnaire_session_id BIGINT UNSIGNED NOT NULL,
      participant_id BIGINT UNSIGNED NULL,
      action_type VARCHAR(50) NOT NULL,
      action_label VARCHAR(255) NOT NULL,
      action_status VARCHAR(30) NOT NULL DEFAULT 'ouverte',
      assigned_to BIGINT UNSIGNED NULL,
      opened_at DATETIME NULL,
      closed_at DATETIME NULL,
      notes LONGTEXT NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY questionnaire_session_id (questionnaire_session_id),
      KEY participant_id (participant_id),
      KEY action_type (action_type),
      KEY action_status (action_status),
      KEY assigned_to (assigned_to)
    ) {$charset_collate};";

    $sql_questionnaire_logs = "CREATE TABLE {$this->questionnaire_log_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      questionnaire_session_id BIGINT UNSIGNED NULL,
      participant_id BIGINT UNSIGNED NULL,
      event_type VARCHAR(50) NOT NULL,
      event_label VARCHAR(255) NOT NULL,
      event_payload LONGTEXT NULL,
      created_by BIGINT UNSIGNED NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY questionnaire_session_id (questionnaire_session_id),
      KEY participant_id (participant_id),
      KEY event_type (event_type),
      KEY created_at (created_at)
    ) {$charset_collate};";

    dbDelta( $sql_questionnaire_models );
    dbDelta( $sql_questionnaire_actions );
    dbDelta( $sql_questionnaire_logs );

    $sql_system_logs = "CREATE TABLE {$this->system_log_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      log_level VARCHAR(20) NOT NULL DEFAULT 'info',
      event_type VARCHAR(80) NOT NULL,
      action_key VARCHAR(120) NULL,
      object_type VARCHAR(80) NULL,
      object_id BIGINT UNSIGNED NULL,
      user_id BIGINT UNSIGNED NULL,
      result_status VARCHAR(20) NOT NULL DEFAULT 'success',
      message TEXT NULL,
      context_json LONGTEXT NULL,
      source VARCHAR(40) NOT NULL DEFAULT 'plugin',
      /* ACDC 3.25.292 — Sans l'origine d'une action, un journal dit ce qui a été
         fait mais pas d'où : impossible de distinguer une manipulation depuis le
         bureau d'une manipulation depuis ailleurs. Les journaux des portails
         apprenant et formateur enregistraient déjà les deux ; celui du plugin,
         non. L'adresse est effacée au bout d'un an — voir purge_system_logs(). */
      ip_address VARCHAR(45) NULL,
      user_agent VARCHAR(255) NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY log_level (log_level),
      KEY event_type (event_type),
      KEY action_key (action_key),
      KEY object_type (object_type),
      KEY object_id (object_id),
      KEY user_id (user_id),
      KEY result_status (result_status),
      KEY created_at (created_at)
    ) {$charset_collate};";

    $sql_learner_portal_accounts = "CREATE TABLE {$this->learner_portal_account_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      primary_learner_id BIGINT UNSIGNED NULL,
      email VARCHAR(190) NOT NULL,
      password_hash VARCHAR(255) NOT NULL,
      status VARCHAR(40) NOT NULL DEFAULT 'never_activated',
      must_change_password TINYINT(1) NOT NULL DEFAULT 1,
      photo_url TEXT NULL,
      access_opened_at DATETIME NULL,
      access_expires_at DATETIME NULL,
      first_activated_at DATETIME NULL,
      last_login_at DATETIME NULL,
      failed_login_count INT UNSIGNED NOT NULL DEFAULT 0,
      blocked_until DATETIME NULL,
      last_access_notice_key VARCHAR(40) NULL,
      last_access_notice_at DATETIME NULL,
      last_access_notice_expiry_at DATETIME NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY email (email),
      KEY primary_learner_id (primary_learner_id),
      KEY status (status),
      KEY access_expires_at (access_expires_at),
      KEY blocked_until (blocked_until)
    ) {$charset_collate};";

    $sql_learner_portal_tokens = "CREATE TABLE {$this->learner_portal_token_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      account_id BIGINT UNSIGNED NOT NULL,
      token_hash VARCHAR(255) NOT NULL,
      token_type VARCHAR(40) NOT NULL,
      expires_at DATETIME NOT NULL,
      consumed_at DATETIME NULL,
      revoked_at DATETIME NULL,
      meta_json LONGTEXT NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY account_id (account_id),
      KEY token_hash (token_hash(191)),
      KEY token_type (token_type),
      KEY expires_at (expires_at)
    ) {$charset_collate};";

    $sql_learner_portal_sessions = "CREATE TABLE {$this->learner_portal_session_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      account_id BIGINT UNSIGNED NOT NULL,
      session_hash VARCHAR(255) NOT NULL,
      ip_address VARCHAR(100) NULL,
      user_agent VARCHAR(255) NULL,
      expires_at DATETIME NOT NULL,
      last_activity_at DATETIME NOT NULL,
      revoked_at DATETIME NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY account_id (account_id),
      KEY session_hash (session_hash(191)),
      KEY expires_at (expires_at),
      KEY revoked_at (revoked_at)
    ) {$charset_collate};";

    $sql_learner_portal_logs = "CREATE TABLE {$this->learner_portal_log_table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      account_id BIGINT UNSIGNED NULL,
      learner_id BIGINT UNSIGNED NULL,
      event_type VARCHAR(60) NOT NULL,
      ip_address VARCHAR(100) NULL,
      user_agent VARCHAR(255) NULL,
      event_data LONGTEXT NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      KEY account_id (account_id),
      KEY learner_id (learner_id),
      KEY event_type (event_type),
      KEY created_at (created_at)
    ) {$charset_collate};";

    dbDelta( $sql_learner_portal_accounts );
    dbDelta( $sql_learner_portal_tokens );
    dbDelta( $sql_learner_portal_sessions );
    dbDelta( $sql_learner_portal_logs );
    dbDelta( $sql_system_logs );

    $this->maybe_add_table_column( $this->funder_table, 'name', "VARCHAR(190) NOT NULL DEFAULT ''" );
    $this->maybe_add_table_column( $this->funder_table, 'sector', "VARCHAR(190) DEFAULT ''" );
    $this->maybe_add_table_column( $this->funder_table, 'address', "VARCHAR(255) DEFAULT ''" );
    $this->maybe_add_table_column( $this->funder_table, 'city', "VARCHAR(120) DEFAULT ''" );
    $this->maybe_add_table_column( $this->funder_table, 'postal_code', "VARCHAR(20) DEFAULT ''" );
    $this->maybe_add_table_column( $this->funder_table, 'website', "VARCHAR(255) DEFAULT ''" );
    $this->maybe_add_table_column( $this->funder_table, 'contact_type', "VARCHAR(120) DEFAULT ''" );
    $this->maybe_add_table_column( $this->funder_table, 'has_paca_presence', "VARCHAR(10) DEFAULT ''" );
    $this->maybe_add_table_column( $this->funder_table, 'paca_contact_details', 'TEXT NULL' );
    /* ACDC 3.25.257 — L'INTERLOCUTEUR DÉDIÉ ET L'ESPACE EN LIGNE DU FINANCEUR.
       Un OPCO se traite avec quelqu'un, pas avec un standard, et son espace en
       ligne demande un compte que l'organisme crée lui-même. Ces informations
       vivaient jusqu'ici dans un carnet, hors du dossier qu'elles servent.
       Le mot de passe est CHIFFRÉ AU REPOS (voir handle_save_funder) : une
       sauvegarde SQL de la base ne le révèle pas. */
    $this->maybe_add_table_column( $this->funder_table, 'contact_first_name', "VARCHAR(120) DEFAULT ''" );
    $this->maybe_add_table_column( $this->funder_table, 'contact_last_name',  "VARCHAR(120) DEFAULT ''" );
    $this->maybe_add_table_column( $this->funder_table, 'contact_phone',      "VARCHAR(50) DEFAULT ''" );
    $this->maybe_add_table_column( $this->funder_table, 'contact_email',      "VARCHAR(190) DEFAULT ''" );
    $this->maybe_add_table_column( $this->funder_table, 'portal_url',         'TEXT NULL' );
    $this->maybe_add_table_column( $this->funder_table, 'portal_login',       "VARCHAR(190) DEFAULT ''" );
    $this->maybe_add_table_column( $this->funder_table, 'portal_password',    'TEXT NULL' );
    $this->maybe_add_table_index( $this->funder_table, 'name', 'INDEX name (name)' );
    $this->maybe_add_table_index( $this->funder_table, 'postal_code', 'INDEX postal_code (postal_code)' );

    $this->acdc_seed_default_funders();

    $this->maybe_add_table_column( $this->need_table, 'source_prospect_id', 'BIGINT UNSIGNED DEFAULT NULL' );
    $this->maybe_add_table_index( $this->need_table, 'source_prospect_id', 'INDEX source_prospect_id (source_prospect_id)' );
    $this->maybe_add_table_column( $this->need_table, 'funder_id', 'BIGINT UNSIGNED DEFAULT NULL' );
    $this->maybe_add_table_column( $this->need_table, 'sent_at', 'DATETIME DEFAULT NULL' );
    $this->maybe_add_table_index( $this->need_table, 'sent_at', 'INDEX sent_at (sent_at)' );

    /* hotfix9 — Prospects : thématique + formation liée */
    $this->maybe_add_table_column( $this->prospect_table, 'desired_thematique',    "VARCHAR(60) DEFAULT ''" );
    $this->maybe_add_table_column( $this->prospect_table, 'desired_formation_id',  'BIGINT UNSIGNED DEFAULT NULL' );
    $this->maybe_add_table_column( $this->prospect_table, 'planned_funding',        "VARCHAR(100) DEFAULT ''" );
    $this->maybe_add_table_column( $this->prospect_table, 'funder_id',              'BIGINT UNSIGNED DEFAULT NULL' );
    /* hotfix12 — Prospects : email entreprise */
    $this->maybe_add_table_column( $this->prospect_table, 'company_email', "VARCHAR(190) DEFAULT ''" );
    /* hotfix13 — Prospects Particulier/Salarié : employeur */
    $this->maybe_add_table_column( $this->prospect_table, 'employer_name', "VARCHAR(190) DEFAULT ''" );
    /* 3.24.69 — Site web et activité du prospect (entreprise) */
    $this->maybe_add_table_column( $this->prospect_table, 'website',  "VARCHAR(255) DEFAULT ''" );
    $this->maybe_add_table_column( $this->prospect_table, 'activity', "VARCHAR(255) DEFAULT ''" );

    $this->maybe_add_table_column( $this->questionnaire_action_table, 'target_due_days', 'INT UNSIGNED NULL' );
    $this->maybe_add_table_column( $this->questionnaire_action_table, 'due_at', 'DATETIME NULL' );
    $this->maybe_add_table_column( $this->questionnaire_action_table, 'internal_followup_last_key', 'VARCHAR(60) NULL' );
    $this->maybe_add_table_column( $this->questionnaire_action_table, 'internal_followup_last_sent_at', 'DATETIME NULL' );
    $this->maybe_add_table_column( $this->questionnaire_action_table, 'internal_followup_count', 'INT UNSIGNED NOT NULL DEFAULT 0' );
    // ACDC 3.25.113 — colonne priority_level (actions Qualiopi) absente du CREATE d'origine.
    $this->maybe_add_table_column( $this->questionnaire_action_table, 'priority_level', "VARCHAR(50) NOT NULL DEFAULT 'Moyenne'" );
    $this->maybe_add_table_index( $this->questionnaire_action_table, 'target_due_days', 'INDEX target_due_days (target_due_days)' );
    $this->maybe_add_table_index( $this->questionnaire_action_table, 'due_at', 'INDEX due_at (due_at)' );
    $this->maybe_add_table_index( $this->questionnaire_action_table, 'internal_followup_last_sent_at', 'INDEX internal_followup_last_sent_at (internal_followup_last_sent_at)' );

    $this->maybe_add_table_column( $this->questionnaire_session_table, 'survey_type', 'VARCHAR(30) NULL' );
    $this->maybe_add_table_column( $this->questionnaire_session_table, 'survey_subtype', 'VARCHAR(30) NULL' );
    $this->maybe_add_table_column( $this->questionnaire_session_table, 'survey_model_id', 'BIGINT UNSIGNED NULL' );
    $this->maybe_add_table_column( $this->questionnaire_session_table, 'registration_id', 'BIGINT UNSIGNED NULL' );
    $this->maybe_add_table_column( $this->questionnaire_session_table, 'company_id', 'BIGINT UNSIGNED NULL' );
    $this->maybe_add_table_column( $this->questionnaire_session_table, 'company_contact_id', 'BIGINT UNSIGNED NULL' );
    $this->maybe_add_table_column( $this->questionnaire_session_table, 'funder_id', 'BIGINT UNSIGNED NULL' );
    $this->maybe_add_table_column( $this->questionnaire_session_table, 'funder_contact_id', 'BIGINT UNSIGNED NULL' );
    $this->maybe_add_table_column( $this->questionnaire_session_table, 'annual_campaign_year', 'SMALLINT UNSIGNED NULL' );
    $this->maybe_add_table_column( $this->questionnaire_session_table, 'send_mode', "VARCHAR(20) NOT NULL DEFAULT 'manual'" );
    $this->maybe_add_table_column( $this->questionnaire_session_table, 'scheduled_at', 'DATETIME NULL' );
    $this->maybe_add_table_column( $this->questionnaire_session_table, 'dispatch_sent_at', 'DATETIME NULL' );
    $this->maybe_add_table_column( $this->questionnaire_session_table, 'deadline_at', 'DATETIME NULL' );
    $this->maybe_add_table_column( $this->questionnaire_session_table, 'mail_subject', 'VARCHAR(255) NULL' );
    $this->maybe_add_table_column( $this->questionnaire_session_table, 'mail_intro', 'LONGTEXT NULL' );
    $this->maybe_add_table_column( $this->questionnaire_session_table, 'reminder_subject', 'VARCHAR(255) NULL' );
    $this->maybe_add_table_column( $this->questionnaire_session_table, 'reminder_intro', 'LONGTEXT NULL' );
    $this->maybe_add_table_column( $this->questionnaire_session_table, 'reminder_days_json', 'LONGTEXT NULL' );
    $this->maybe_add_table_column( $this->questionnaire_session_table, 'alert_settings_json', 'LONGTEXT NULL' );
    $this->maybe_add_table_column( $this->questionnaire_session_table, 'results_pdf_document_id', 'BIGINT UNSIGNED NULL' );
    $this->maybe_add_table_column( $this->questionnaire_session_table, 'is_survey_session', 'TINYINT(1) NOT NULL DEFAULT 0' );

    $this->maybe_add_table_index( $this->questionnaire_session_table, 'survey_type', 'INDEX survey_type (survey_type)' );
    $this->maybe_add_table_index( $this->questionnaire_session_table, 'survey_subtype', 'INDEX survey_subtype (survey_subtype)' );
    $this->maybe_add_table_index( $this->questionnaire_session_table, 'survey_model_id', 'INDEX survey_model_id (survey_model_id)' );
    $this->maybe_add_table_index( $this->questionnaire_session_table, 'company_id', 'INDEX company_id (company_id)' );
    $this->maybe_add_table_index( $this->questionnaire_session_table, 'funder_id', 'INDEX funder_id (funder_id)' );
    $this->maybe_add_table_index( $this->questionnaire_session_table, 'scheduled_at', 'INDEX scheduled_at (scheduled_at)' );
    $this->maybe_add_table_index( $this->questionnaire_session_table, 'deadline_at', 'INDEX deadline_at (deadline_at)' );
    $this->maybe_add_table_index( $this->questionnaire_session_table, 'is_survey_session', 'INDEX is_survey_session (is_survey_session)' );

    $this->maybe_add_table_column( $this->questionnaire_participant_table, 'recipient_type', 'VARCHAR(30) NULL' );
    $this->maybe_add_table_column( $this->questionnaire_participant_table, 'registration_id', 'BIGINT UNSIGNED NULL' );
    $this->maybe_add_table_column( $this->questionnaire_participant_table, 'trainer_id', 'BIGINT UNSIGNED NULL' );
    $this->maybe_add_table_column( $this->questionnaire_participant_table, 'company_contact_id', 'BIGINT UNSIGNED NULL' );
    $this->maybe_add_table_column( $this->questionnaire_participant_table, 'funder_contact_id', 'BIGINT UNSIGNED NULL' );
    $this->maybe_add_table_column( $this->questionnaire_participant_table, 'email', 'VARCHAR(255) NULL' );
    $this->maybe_add_table_column( $this->questionnaire_participant_table, 'full_name', 'VARCHAR(255) NULL' );
    $this->maybe_add_table_column( $this->questionnaire_participant_table, 'opened_at', 'DATETIME NULL' );
    $this->maybe_add_table_column( $this->questionnaire_participant_table, 'started_at', 'DATETIME NULL' );
    $this->maybe_add_table_column( $this->questionnaire_participant_table, 'responded_at', 'DATETIME NULL' );
    $this->maybe_add_table_column( $this->questionnaire_participant_table, 'reminder_count', 'INT UNSIGNED NOT NULL DEFAULT 0' );
    $this->maybe_add_table_column( $this->questionnaire_participant_table, 'last_reminder_at', 'DATETIME NULL' );
    $this->maybe_add_table_column( $this->questionnaire_participant_table, 'final_score', 'DECIMAL(10,2) NULL' );
    $this->maybe_add_table_column( $this->questionnaire_participant_table, 'alert_flag', 'TINYINT(1) NOT NULL DEFAULT 0' );
    $this->maybe_add_table_column( $this->questionnaire_participant_table, 'issue_flag', 'TINYINT(1) NOT NULL DEFAULT 0' );
    $this->maybe_add_table_column( $this->questionnaire_participant_table, 'complaint_flag', 'TINYINT(1) NOT NULL DEFAULT 0' );
    $this->maybe_add_table_column( $this->questionnaire_participant_table, 'reservation_flag', 'TINYINT(1) NOT NULL DEFAULT 0' );
    $this->maybe_add_table_column( $this->questionnaire_participant_table, 'improvement_need_flag', 'TINYINT(1) NOT NULL DEFAULT 0' );

    $this->maybe_add_table_index( $this->questionnaire_participant_table, 'recipient_type', 'INDEX recipient_type (recipient_type)' );
    $this->maybe_add_table_index( $this->questionnaire_participant_table, 'registration_id', 'INDEX registration_id (registration_id)' );
    $this->maybe_add_table_index( $this->questionnaire_participant_table, 'trainer_id', 'INDEX trainer_id (trainer_id)' );
    $this->maybe_add_table_index( $this->questionnaire_participant_table, 'company_contact_id', 'INDEX company_contact_id (company_contact_id)' );
    $this->maybe_add_table_index( $this->questionnaire_participant_table, 'funder_contact_id', 'INDEX funder_contact_id (funder_contact_id)' );
    $this->maybe_add_table_index( $this->questionnaire_participant_table, 'responded_at', 'INDEX responded_at (responded_at)' );
    $this->maybe_add_table_index( $this->questionnaire_participant_table, 'alert_flag', 'INDEX alert_flag (alert_flag)' );

    $this->maybe_add_table_column( $this->questionnaire_answer_table, 'question_key', 'VARCHAR(120) NULL' );
    $this->maybe_add_table_column( $this->questionnaire_answer_table, 'question_label', 'TEXT NULL' );
    $this->maybe_add_table_column( $this->questionnaire_answer_table, 'answer_type', 'VARCHAR(50) NULL' );
    $this->maybe_add_table_column( $this->questionnaire_answer_table, 'numeric_score', 'DECIMAL(10,2) NULL' );
    $this->maybe_add_table_column( $this->questionnaire_answer_table, 'is_alert', 'TINYINT(1) NOT NULL DEFAULT 0' );

    $this->maybe_add_table_index( $this->questionnaire_answer_table, 'question_key', 'INDEX question_key (question_key)' );
    $this->maybe_add_table_index( $this->questionnaire_answer_table, 'answer_type', 'INDEX answer_type (answer_type)' );
    $this->maybe_add_table_index( $this->questionnaire_answer_table, 'is_alert', 'INDEX is_alert (is_alert)' );

    $this->maybe_add_table_column( $this->learner_portal_account_table, 'last_access_notice_key', 'VARCHAR(40) NULL' );
    $this->maybe_add_table_column( $this->learner_portal_account_table, 'last_access_notice_at', 'DATETIME NULL' );
    $this->maybe_add_table_column( $this->learner_portal_account_table, 'last_access_notice_expiry_at', 'DATETIME NULL' );
    /* ACDC 3.25.260 — Date du dernier e-mail d'ouverture d'accès. Sans elle,
       rien n'empêchait deux chemins d'envoi de faire chacun le sien : les
       apprenants recevaient deux fois le même message. */
    $this->maybe_add_table_column( $this->learner_portal_account_table, 'last_activation_email_at', 'DATETIME NULL' );

    $this->maybe_add_table_index( $this->learner_portal_account_table, 'status', 'INDEX status (status)' );
    $this->maybe_add_table_index( $this->learner_portal_account_table, 'access_expires_at', 'INDEX access_expires_at (access_expires_at)' );
    $this->maybe_add_table_index( $this->learner_portal_account_table, 'blocked_until', 'INDEX blocked_until (blocked_until)' );
    $this->maybe_add_table_index( $this->learner_portal_token_table, 'token_type', 'INDEX token_type (token_type)' );
    $this->maybe_add_table_index( $this->learner_portal_token_table, 'expires_at', 'INDEX expires_at (expires_at)' );
    $this->maybe_add_table_index( $this->learner_portal_session_table, 'expires_at', 'INDEX expires_at (expires_at)' );
    $this->maybe_add_table_index( $this->learner_portal_log_table, 'event_type', 'INDEX event_type (event_type)' );
    // ACDC 3.21.22 — Table propositions commerciales
    if ( method_exists( $this, 'maybe_create_proposal_table' ) ) {
      $this->maybe_create_proposal_table();
    }
    // ACDC 3.21.26 — Table devis
    if ( method_exists( $this, 'maybe_create_quotes_table' ) ) {
      $this->maybe_create_quotes_table();
    }
    // ACDC 3.24.20 — Table factures réelles
    if ( method_exists( $this, 'maybe_create_invoices_table' ) ) {
      $this->maybe_create_invoices_table();
    }

    add_option( 'acdc_of_surveys_db_version', '1.0.0' );
    add_option( 'acdc_of_keep_data_on_uninstall', 'yes' );
    add_option( 'acdc_of_db_version', '3.0.0' );
    add_option( 'acdc_of_data_protection_level', '3' );
    add_option( 'acdc_of_purge_allowed_in_production', 'no' );
    add_option( 'acdc_of_backup_retention_count', 30 );
    add_option( 'acdc_of_last_backup_file', '' );
    add_option( 'acdc_of_last_backup_at', '' );
    add_option( 'acdc_of_last_manual_backup_file', '' );
    add_option( 'acdc_of_last_manual_backup_at', '' );
    add_option( 'acdc_of_last_safety_backup_file', '' );
    add_option( 'acdc_of_last_safety_backup_at', '' );
    $this->ensure_questionnaire_public_page();
    if ( method_exists( $this, 'maybe_seed_survey_engine_settings' ) ) {
      $this->maybe_seed_survey_engine_settings();
    }

    /* ACDC 3.22.1 — Table missions/contrats formateurs (source de vérité Cadre D et E du BPF). */
    $sql_trainer_contracts = "CREATE TABLE IF NOT EXISTS {$this->trainer_contract_table} (" . "  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT," . "  trainer_id    BIGINT UNSIGNED NOT NULL," . "  label         VARCHAR(255) NOT NULL DEFAULT ''," . "  formation_ref VARCHAR(255) NOT NULL DEFAULT ''," . "  date_start    DATE NULL," . "  date_end      DATE NULL," . "  nb_heures     DECIMAL(8,2)  NOT NULL DEFAULT 0," . "  taux_ht       DECIMAL(10,2) NOT NULL DEFAULT 0," . "  montant_ht    DECIMAL(10,2) NOT NULL DEFAULT 0," . "  statut        VARCHAR(50)   NOT NULL DEFAULT 'En attente'," . "  notes         TEXT," . "  contract_pdf_url VARCHAR(512) NOT NULL DEFAULT ''," . "  created_at    DATETIME      NOT NULL," . "  PRIMARY KEY (id)," . "  KEY trainer_id (trainer_id)," . "  KEY date_start (date_start)" . ") {$charset_collate};";
    dbDelta( $sql_trainer_contracts );
    /* ACDC 3.22.3 — Colonnes signature électronique sur les contrats formateurs. */
    $this->maybe_add_table_column( $this->trainer_contract_table, 'signature_request_id', 'BIGINT UNSIGNED DEFAULT NULL' );
    $this->maybe_add_table_column( $this->trainer_contract_table, 'signature_status',     "VARCHAR(40) NOT NULL DEFAULT ''" );
    $this->maybe_add_table_column( $this->trainer_contract_table, 'signed_document_url',  'TEXT DEFAULT NULL' );
    /* ACDC 3.25.157 — Archivage d'un contrat signé : horodatage de la sortie du PDF
       hors de l'application (téléchargement par l'organisme). Tant que cette colonne
       est NULL, la mission signée n'est pas supprimable : c'est ce qui garantit qu'une
       pièce comptable n'est jamais détruite sans copie. */
    $this->maybe_add_table_column( $this->trainer_contract_table, 'archived_at',          'DATETIME DEFAULT NULL' );
    /* ACDC 3.25.227 — La mission d'un formateur était reliée à sa formation par
       une CHAÎNE DE CARACTÈRES. La recette l'a relevé : le sélecteur listait
       vingt et une options pour dix titres — les variantes d'une même formation
       portent le même intitulé et devenaient indiscernables — et renommer une
       formation aurait rendu tous les contrats muets. On stocke l'identifiant,
       la chaîne ne servant plus qu'à l'affichage et à la rétrocompatibilité. */
    $this->maybe_add_table_column( $this->trainer_contract_table, 'formation_id', 'BIGINT UNSIGNED DEFAULT NULL' );
    /* ACDC 3.25.227 — L'horodatage de la signature. Il n'était nulle part :
       le contrat passait « signée » sans que l'on sache QUAND. C'est
       précisément ce qui manquait au PDF pour valoir preuve. */
    $this->maybe_add_table_column( $this->trainer_contract_table, 'signature_completed_at', 'DATETIME DEFAULT NULL' );
    /* ACDC 3.25.231 — Trace de l'invitation d'un formateur : elle empêche
       d'expédier un e-mail d'activation par inscription au lieu d'un par
       formateur. */
    $this->maybe_add_table_column( $this->trainer_portal_account_table, 'activation_sent_at', 'DATETIME DEFAULT NULL' );
    /* ACDC 3.25.236 — La mention manuscrite « Bon pour accord » recopiée par le
       signataire du devis. Elle a une valeur juridique propre, distincte de la
       signature : c'est l'écrit qui vaut acceptation de l'offre. */
    $this->maybe_add_table_column( $this->quote_table, 'accord_mention', "VARCHAR(190) NOT NULL DEFAULT ''" );
    $this->maybe_add_table_column( $this->quote_table, 'accord_signature_path', 'TEXT DEFAULT NULL' );

    /* ACDC 3.25.242 — LE DÉROULÉ DES SÉANCES, DÉCIDÉ SUR LA CONVENTION.
       Jusqu'ici la convention connaissait les DATES et rien d'autre : à la
       création des séances, les horaires étaient écrits en dur (09h00–12h30 /
       13h30–17h00), le format recopié de la modalité du catalogue, le type
       déduit du nombre d'apprenants, et le lien distanciel jamais renseigné.
       La convention supposait au lieu de dire — sur une pièce que signe un
       commanditaire et que lit un financeur.
         seances_schedule_json : par journée, le format et les quatre horaires.
         session_type / attendance_method / remote_link : valables pour toute la
           convention, comme David l'a tranché (un seul formateur, un seul lien).
         signed_schedule_json : l'instantané figé au moment de la signature, qui
           permet de dire plus tard ce qui a divergé. */
    $this->maybe_add_table_column( $this->registration_contract_table, 'seances_schedule_json', 'LONGTEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->registration_contract_table, 'session_type', "VARCHAR(50) NOT NULL DEFAULT ''" );
    $this->maybe_add_table_column( $this->registration_contract_table, 'attendance_method', "VARCHAR(50) NOT NULL DEFAULT ''" );
    $this->maybe_add_table_column( $this->registration_contract_table, 'remote_link', 'TEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->registration_contract_table, 'signed_schedule_json', 'LONGTEXT DEFAULT NULL' );

    /* ACDC 3.25.245 — LE LIEU DE LA CONVENTION, ENFIN ÉCRIT QUELQUE PART.
       La convention n'avait aucun champ de lieu : elle le devinait à
       l'impression par une cascade de cinq sources, et imprimait « À définir »
       quand aucune ne répondait — pendant que le devis, lui, portait l'adresse
       complète. Une convention est pourtant la pièce qui ENGAGE sur le lieu :
       qu'il soit deviné plutôt que saisi et relu ne tient pas devant un
       auditeur. Le lieu se saisit désormais, et se corrige avant signature. */
    $this->maybe_add_table_column( $this->registration_contract_table, 'formation_address', 'TEXT DEFAULT NULL' );
    $this->maybe_add_table_column( $this->registration_contract_table, 'formation_postal_code', "VARCHAR(20) NOT NULL DEFAULT ''" );
    $this->maybe_add_table_column( $this->registration_contract_table, 'formation_city', "VARCHAR(120) NOT NULL DEFAULT ''" );

    /* ACDC 3.24.11 — Bilans compétences formateurs (indicateur 21 Qualiopi). */
    $sql_trainer_evaluations = "CREATE TABLE IF NOT EXISTS {$this->trainer_evaluation_table} (" . "  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT," . "  trainer_id     BIGINT UNSIGNED NOT NULL," . "  evaluation_date DATE NOT NULL," . "  eval_type      VARCHAR(50) NOT NULL DEFAULT 'entretien'," . "  skills_evaluated TEXT," . "  level_reached  TINYINT UNSIGNED NOT NULL DEFAULT 0," . "  objectives_set TEXT," . "  comment_text   TEXT," . "  created_at     DATETIME NOT NULL," . "  PRIMARY KEY (id)," . "  KEY trainer_id (trainer_id)," . "  KEY evaluation_date (evaluation_date)" . ") {$charset_collate};";
    dbDelta( $sql_trainer_evaluations );

    /* ACDC 3.24.28 — M8b : Cahier de texte formateur (une entrée par jour par session). */
    $sql_trainer_logbook = "CREATE TABLE IF NOT EXISTS {$this->trainer_logbook_table} (" . "  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT," . "  session_id  BIGINT UNSIGNED NOT NULL," . "  slot_date   DATE NOT NULL," . "  slot_label  VARCHAR(190) NOT NULL DEFAULT ''," . "  content     LONGTEXT NOT NULL DEFAULT ''," . "  updated_at  DATETIME NOT NULL," . "  PRIMARY KEY (id)," . "  UNIQUE KEY session_slot (session_id, slot_date)," . "  KEY session_id (session_id)" . ") {$charset_collate};";
    dbDelta( $sql_trainer_logbook );

    /* ACDC 3.22.18 — H4 : Registre des réclamations transverse (indicateur 31 Qualiopi). */
    $sql_complaints = "CREATE TABLE IF NOT EXISTS {$this->complaint_table} (" . "  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT," . "  registration_id     BIGINT UNSIGNED DEFAULT NULL," . "  session_id          BIGINT UNSIGNED DEFAULT NULL," . "  source              VARCHAR(50) NOT NULL DEFAULT 'apprenant'," . "  type                VARCHAR(50) NOT NULL DEFAULT 'reclamation'," . "  summary             TEXT NOT NULL," . "  severity            VARCHAR(20) NOT NULL DEFAULT 'mineure'," . "  status              VARCHAR(20) NOT NULL DEFAULT 'ouverte'," . "  assigned_to         VARCHAR(255) NOT NULL DEFAULT ''," . "  corrective_action   TEXT," . "  improvement_action  TEXT," . "  improvement_id      BIGINT UNSIGNED DEFAULT NULL," . "  improvement_deadline DATE DEFAULT NULL," . "  effectiveness_eval  TEXT," . "  acknowledged_at     DATETIME DEFAULT NULL," . "  opened_at           DATETIME NOT NULL," . "  closed_at           DATETIME DEFAULT NULL," . "  created_at          DATETIME NOT NULL," . "  updated_at          DATETIME NOT NULL," . "  PRIMARY KEY (id)," . "  KEY registration_id (registration_id)," . "  KEY session_id (session_id)," . "  KEY status (status)," . "  KEY severity (severity)," . "  KEY opened_at (opened_at)" . ") {$charset_collate};";
    dbDelta( $sql_complaints );

    /* ACDC 3.23.11 — Module veille automatisée IA (V1→V6 — indicateurs 23/24/25 Qualiopi). */
    $sql_watch_items = "CREATE TABLE IF NOT EXISTS {$this->watch_items_table} (" . "  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT," . "  source_id       VARCHAR(190)  NOT NULL DEFAULT ''," . "  source_type     VARCHAR(50)   NOT NULL DEFAULT 'rss'," . "  watch_axis      VARCHAR(20)   NOT NULL DEFAULT ''," . "  title           TEXT          NOT NULL," . "  url             TEXT," . "  summary         TEXT," . "  full_content    LONGTEXT," . "  ai_score        TINYINT UNSIGNED DEFAULT NULL," . "  ai_summary      TEXT," . "  ai_analysis     TEXT," . "  ai_action       TEXT," . "  ai_urgency      VARCHAR(20)   DEFAULT NULL," . "  ai_note         TEXT," . "  ai_diffusion    TEXT," . "  ai_formation_id BIGINT UNSIGNED DEFAULT NULL," . "  ai_model        VARCHAR(50)   DEFAULT NULL," . "  status          VARCHAR(20)   NOT NULL DEFAULT 'new'," . "  exploited_at    DATETIME      DEFAULT NULL," . "  exploitation_note TEXT," . "  action_taken    TEXT," . "  diffused_at     DATETIME      DEFAULT NULL," . "  diffusion_note  TEXT," . "  published_at    DATETIME      DEFAULT NULL," . "  collected_at    DATETIME      NOT NULL," . "  updated_at      DATETIME      NOT NULL," . "  PRIMARY KEY (id)," . "  KEY source_id (source_id)," . "  KEY watch_axis (watch_axis)," . "  KEY status (status)," . "  KEY ai_score (ai_score)," . "  KEY collected_at (collected_at)" . ") {$charset_collate};";
    dbDelta( $sql_watch_items );

    /* ACDC 3.24.12 — R-12 : Alerte absences / ruptures de parcours (indicateur 12 Qualiopi).
       absence_alert_sent_at : horodatage du dernier envoi d'alerte absence.
       absence_count         : nombre d'absences détectées (toutes séances confondues). */
    $this->maybe_add_table_column( $this->learner_table, 'absence_alert_sent_at', 'DATETIME DEFAULT NULL' );
    $this->maybe_add_table_column( $this->learner_table, 'absence_count', 'SMALLINT UNSIGNED NOT NULL DEFAULT 0' );

    /* ACDC 3.25.185 — Module workflow : parcours, étapes, et le délai d'envoi
       de l'analyse des besoins saisi dans la convention (le schéma de David dit
       « selon temps définis dans la création de la convention »). */
    if ( method_exists( $this, 'acdc_wf_install_schema' ) ) {
      $this->acdc_wf_install_schema();
    }
    $this->maybe_add_table_column( $this->registration_contract_table, 'nad_delay_days', 'SMALLINT UNSIGNED DEFAULT NULL' );

    /* ACDC 3.25.240 — Lève l'interdiction posée par erreur sur la racine des
       téléversements en 3.25.225. Sans ce passage, réinstaller le plugin ne
       répare rien : le fichier existe déjà, et toutes nos écritures sont
       gardées par `! file_exists()`. */
    if ( method_exists( $this, 'acdc_repair_uploads_htaccess' ) ) {
      $this->acdc_repair_uploads_htaccess();
    }

    update_option( 'acdc_of_saas_version', ACDC_OF_SAAS_VERSION );
    update_option( 'acdc_of_db_version', '3.0.0', false );
    update_option( 'acdc_of_data_protection_level', '3', false );
    if ( ! get_option( 'acdc_of_purge_allowed_in_production', null ) ) { update_option( 'acdc_of_purge_allowed_in_production', 'no', false ); }
    if ( ! get_option( 'acdc_of_backup_retention_count', null ) ) { update_option( 'acdc_of_backup_retention_count', 30, false ); }
    if ( null === get_option( 'acdc_of_last_manual_backup_file', null ) ) { update_option( 'acdc_of_last_manual_backup_file', '', false ); }
    if ( null === get_option( 'acdc_of_last_manual_backup_at', null ) ) { update_option( 'acdc_of_last_manual_backup_at', '', false ); }
    $this->ensure_soft_delete_schema();
    $this->init_marketing_module_defaults();
    $this->ensure_marketing_public_page();
  }

  /* C11 (audit 3.25.90) — Cache d'existence de colonne pour éviter de répéter des
     "SHOW COLUMNS" à chaque rendu (notamment le portail apprenant). On ne met en
     cache QUE les résultats positifs (une colonne existante ne disparaît jamais) :
     un résultat négatif n'est jamais caché, donc une colonne ajoutée ensuite par
     maybe_add_table_column est détectée immédiatement au check suivant — aucun
     risque d'invalidation obsolète. */
  public function acdc_schema_has_column( $table, $column ) {
    $key = "acdc_schemacol_" . md5( (string) $table . "|" . (string) $column );
    if ( "1" === (string) get_transient( $key ) ) {
      return true;
    }
    global $wpdb;
    $exists = ! empty( $wpdb->get_col( "SHOW COLUMNS FROM {$table} LIKE '" . esc_sql( $column ) . "'" ) );
    if ( $exists ) {
      set_transient( $key, "1", DAY_IN_SECONDS );
    }
    return $exists;
  }

  private function maybe_add_table_column( $table, $column, $definition ) {
    global $wpdb;

    if ( empty( $table ) || empty( $column ) || empty( $definition ) ) {
      return false;
    }

    $exists = $wpdb->get_var(
      $wpdb->prepare(
        "SHOW COLUMNS FROM {$table} LIKE %s",
        $column
      )
    );

    if ( $exists ) {
      return false;
    }

    $result = $wpdb->query( "ALTER TABLE {$table} ADD COLUMN {$column} {$definition}" );

    if ( false === $result ) {
      $this->log_error( 'db_add_column', $wpdb->last_error, array( 'table' => $table, 'column' => $column ) );
      return false;
    }

    return true;
  }  private function maybe_modify_table_column( $table, $column, $definition ) {
    global $wpdb;

    if ( empty( $table ) || empty( $column ) || empty( $definition ) ) {
      return false;
    }

    $column_data = $wpdb->get_row(
      $wpdb->prepare(
        "SHOW COLUMNS FROM {$table} LIKE %s",
        $column
      ),
      ARRAY_A
    );

    if ( empty( $column_data ) || ! is_array( $column_data ) ) {
      return false;
    }

    $current_type = isset( $column_data['Type'] ) ? strtolower( (string) $column_data['Type'] ) : '';
    $current_null = isset( $column_data['Null'] ) ? strtoupper( (string) $column_data['Null'] ) : 'YES';
    $current_default = array_key_exists( 'Default', $column_data ) ? $column_data['Default'] : null;

    $needs_change = false;

    if ( 'naf_code' === $column ) {
      if ( false === strpos( $current_type, 'varchar(190)' ) ) {
        $needs_change = true;
      }
      if ( 'NO' === $current_null ) {
        $needs_change = true;
      }
      if ( '' !== (string) $current_default ) {
        $needs_change = true;
      }
    }

    if ( ! $needs_change ) {
      return false;
    }

    $result = $wpdb->query( "ALTER TABLE {$table} MODIFY COLUMN {$column} {$definition}" );

    if ( false === $result ) {
      $this->log_error( 'db_modify_column', $wpdb->last_error, array( 'table' => $table, 'column' => $column ) );
      return false;
    }

    return true;
  }  private function maybe_add_table_index( $table, $index_name, $index_sql ) {

    global $wpdb;

    if ( empty( $table ) || empty( $index_name ) || empty( $index_sql ) ) {
      return false;
    }

    $exists = $wpdb->get_var(
      $wpdb->prepare(
        "SHOW INDEX FROM {$table} WHERE Key_name = %s",
        $index_name
      )
    );

    if ( $exists ) {
      return false;
    }

    $result = $wpdb->query( "ALTER TABLE {$table} ADD {$index_sql}" );

    if ( false === $result ) {
      $this->log_error( 'db_add_index', $wpdb->last_error, array( 'table' => $table, 'index' => $index_name ) );
      return false;
    }

    return true;
  }private function is_acdc_front_page( $page_id = 0 ) {
  $page_id = $page_id ? (int) $page_id : (int) get_queried_object_id();
  $keys = array(
    'acdc_of_login_page_id',
    'acdc_of_portal_page_id',
    'acdc_of_extranet_parent_page_id',
    'acdc_of_extranet_login_page_id',
    'acdc_of_extranet_dashboard_page_id',
    'acdc_of_extranet_companies_page_id',
    'acdc_of_extranet_contacts_page_id',
    'acdc_of_extranet_documents_page_id',
    'acdc_of_extranet_settings_page_id',
    'acdc_of_extranet_needs_page_id',
    'acdc_of_extranet_prospects_page_id',
    'acdc_of_extranet_learners_page_id',
    'acdc_of_catalog_page_id',
    'acdc_of_learner_portal_parent_page_id',
    'acdc_of_learner_portal_login_page_id',
    'acdc_of_learner_portal_dashboard_page_id',
    'acdc_of_learner_portal_formations_page_id',
    'acdc_of_learner_portal_formation_page_id',
    'acdc_of_learner_portal_planning_page_id',
    'acdc_of_learner_portal_documents_page_id',
    'acdc_of_learner_portal_library_page_id',
    'acdc_of_learner_portal_mes_quiz_page_id',
    'acdc_of_learner_portal_mes_signatures_page_id',
    'acdc_of_learner_portal_profile_page_id',
    /* ACDC 3.20.85 — Page du portail formateur (auth custom). */
    'acdc_of_trainer_portal_page_id',
  );
  $page_ids = array();
  foreach ( $keys as $key ) {
    $value = (int) get_option( $key, 0 );
    if ( $value > 0 ) {
      $page_ids[] = $value;
    }
  }
  return $page_id > 0 && in_array( $page_id, $page_ids, true );
}  public function use_blank_template_for_acdc_pages( $template ) {
    // Cas 1 : page normale ou page d'accueil statique reconnue par WordPress
    if ( ( is_page() || is_front_page() || is_singular() ) && $this->is_acdc_front_page() ) {
      $custom_template = ACDC_OF_SAAS_DIR . 'templates/acdc-blank-template.php';
      if ( file_exists( $custom_template ) ) {
        return $custom_template;
      }
    }
    // Cas 2 : fallback — détecter par shortcode dans le contenu de la page courante
    if ( ! is_admin() ) {
      global $post;
      if ( $post instanceof WP_Post && ! empty( $post->post_content ) ) {
        $acdc_shortcodes = array( '[acdc_of_login]', '[acdc_of_portal]', '[acdc_of_quality_compliance]' );
        foreach ( $acdc_shortcodes as $sc ) {
          if ( false !== strpos( $post->post_content, $sc ) ) {
            $custom_template = ACDC_OF_SAAS_DIR . 'templates/acdc-blank-template.php';
            if ( file_exists( $custom_template ) ) {
              return $custom_template;
            }
          }
        }
      }
    }

    return $template;
  }  private function get_plugin_logo_url() {
    $custom_logo_id = (int) get_option( 'acdc_of_company_logo_id', 0 );
    if ( $custom_logo_id > 0 ) {
      $custom_logo = wp_get_attachment_image_url( $custom_logo_id, 'full' );
      if ( $custom_logo ) {
        return $custom_logo;
      }
    }

    if ( method_exists( $this, 'get_branding_options' ) ) {
      $branding = $this->get_branding_options();
      if ( ! empty( $branding['logo_url'] ) ) {
        return esc_url_raw( $branding['logo_url'] );
      }
    }

    $remote_default_logo = 'https://acdcformation.com/wp-content/uploads/2026/03/Logo-ACDC.png';
    if ( ! empty( $remote_default_logo ) ) {
      return $remote_default_logo;
    }

    $plugin_logo = ACDC_OF_SAAS_URL . 'assets/img/acdc-logo.png';
    return $plugin_logo;
  }  public function filter_site_icon_url( $url, $size, $blog_id ) {
    if ( ! $this->is_acdc_admin_screen() ) {
      return $url;
    }

    $plugin_logo = $this->get_plugin_logo_url();
    if ( $plugin_logo ) {
      return add_query_arg( 'ver', ACDC_OF_SAAS_VERSION, $plugin_logo );
    }
    return $url;
  }  private function is_acdc_admin_screen() {

    if ( ! is_admin() ) {
      return false;
    }

    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( $page === '' ) {
      return false;
    }

    return strpos( $page, 'acdc-of-' ) === 0;
  }public function ensure_default_pages() {

  $legacy_pages = array(
    'acdc_of_login_page_id' => array( 'title' => 'Connexion gestion ACDC', 'slug' => 'connexion-gestion-acdc', 'content' => '[acdc_of_login]' ),
    'acdc_of_portal_page_id' => array( 'title' => 'Espace gestion ACDC', 'slug' => 'espace-gestion-acdc', 'content' => '[acdc_of_portal]' ),
    'acdc_of_quality_compliance_page_id' => array( 'title' => 'Pilotage qualité ACDC', 'slug' => 'pilotage-qualite-acdc', 'content' => '[acdc_of_quality_compliance]' ),
  );
  foreach ( $legacy_pages as $option_key => $page ) {
    $page_id = (int) get_option( $option_key, 0 );
    if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
      $existing = get_page_by_path( $page['slug'] );
      if ( $existing instanceof WP_Post ) {
        $page_id = (int) $existing->ID;
        wp_update_post( array( 'ID' => $page_id, 'post_content' => $page['content'] ) );
      } else {
        $page_id = wp_insert_post( array( 'post_title' => $page['title'], 'post_name' => $page['slug'], 'post_content' => $page['content'], 'post_status' => 'publish', 'post_type' => 'page' ) );
      }
      update_option( $option_key, (int) $page_id );
    }
  }
  $parent_id = (int) get_option( 'acdc_of_extranet_parent_page_id', 0 );
  if ( ! $parent_id || 'publish' !== get_post_status( $parent_id ) ) {
    $existing_parent = get_page_by_path( 'extranet' );
    if ( $existing_parent instanceof WP_Post ) {
      $parent_id = (int) $existing_parent->ID;
    } else {
      $parent_id = wp_insert_post( array( 'post_title' => 'Extranet', 'post_name' => 'extranet', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'page' ) );
    }
    update_option( 'acdc_of_extranet_parent_page_id', (int) $parent_id );
  }
  $catalog_page_id = (int) get_option( 'acdc_of_catalog_page_id', 0 );
  if ( ! $catalog_page_id || 'publish' !== get_post_status( $catalog_page_id ) ) {
    $existing_catalog = get_page_by_path( 'catalogue' );
    if ( $existing_catalog instanceof WP_Post ) {
      $catalog_page_id = (int) $existing_catalog->ID;
      wp_update_post( array( 'ID' => $catalog_page_id, 'post_content' => '[acdc_of_catalog]' ) );
    } else {
      $catalog_page_id = wp_insert_post( array( 'post_title' => 'Catalogue', 'post_name' => 'catalogue', 'post_content' => '[acdc_of_catalog]', 'post_status' => 'publish', 'post_type' => 'page' ) );
    }
    update_option( 'acdc_of_catalog_page_id', (int) $catalog_page_id );
  }
  $children = array(
    'acdc_of_extranet_login_page_id'   => array( 'title' => 'Extranet Connexion', 'slug' => 'connexion', 'content' => '[acdc_of_login]' ),
    'acdc_of_extranet_dashboard_page_id' => array( 'title' => 'Extranet Tableau de bord', 'slug' => 'tableau-de-bord', 'content' => '[acdc_of_portal tab="dashboard"]' ),
    'acdc_of_extranet_needs_page_id'   => array( 'title' => 'Extranet Recueils de besoin', 'slug' => 'recueils', 'content' => '[acdc_of_portal tab="needs"]' ),
    'acdc_of_extranet_prospects_page_id' => array( 'title' => 'Extranet Prospects', 'slug' => 'prospects', 'content' => '[acdc_of_portal tab="prospects"]' ),
    'acdc_of_extranet_learners_page_id' => array( 'title' => 'Extranet Apprenants', 'slug' => 'apprenants', 'content' => '[acdc_of_portal tab="learners"]' ),
    'acdc_of_extranet_companies_page_id' => array( 'title' => 'Extranet Entreprises', 'slug' => 'entreprises', 'content' => '[acdc_of_portal tab="companies"]' ),
    'acdc_of_extranet_contacts_page_id' => array( 'title' => 'Extranet Contacts', 'slug' => 'contacts', 'content' => '[acdc_of_portal tab="contacts"]' ),
    'acdc_of_extranet_documents_page_id' => array( 'title' => 'Extranet Documents', 'slug' => 'documents', 'content' => '[acdc_of_portal tab="documents"]' ),
    'acdc_of_extranet_settings_page_id' => array( 'title' => 'Extranet Paramètres', 'slug' => 'parametres', 'content' => '[acdc_of_portal tab="settings"]' ),
  );
  foreach ( $children as $option_key => $page ) {
    $page_id = (int) get_option( $option_key, 0 );
    $path = 'extranet/' . $page['slug'];
    if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
      $existing = get_page_by_path( $path );
      if ( $existing instanceof WP_Post ) {
        $page_id = (int) $existing->ID;
        wp_update_post( array( 'ID' => $page_id, 'post_parent' => $parent_id, 'post_content' => $page['content'] ) );
      } else {
        $page_id = wp_insert_post( array( 'post_title' => $page['title'], 'post_name' => $page['slug'], 'post_parent' => $parent_id, 'post_content' => $page['content'], 'post_status' => 'publish', 'post_type' => 'page' ) );
      }
      update_option( $option_key, (int) $page_id );
    }
  }

  $learner_parent_id = (int) get_option( 'acdc_of_learner_portal_parent_page_id', 0 );
  if ( ! $learner_parent_id || 'publish' !== get_post_status( $learner_parent_id ) ) {
    $existing_learner_parent = get_page_by_path( 'espace-apprenant' );
    if ( $existing_learner_parent instanceof WP_Post ) {
      $learner_parent_id = (int) $existing_learner_parent->ID;
    } else {
      $learner_parent_id = wp_insert_post( array( 'post_title' => 'Extranet apprenants', 'post_name' => 'espace-apprenant', 'post_content' => '[acdc_of_learner_portal_home]', 'post_status' => 'publish', 'post_type' => 'page' ) );
    }
    update_option( 'acdc_of_learner_portal_parent_page_id', (int) $learner_parent_id );
  }
  if ( $learner_parent_id ) {
    wp_update_post( array(
      'ID'           => $learner_parent_id,
      'post_title'   => 'Extranet apprenants',
      'post_content' => '[acdc_of_learner_portal_home]',
      'post_status'  => 'publish',
    ) );
  }
  $learner_children = array(
    'acdc_of_learner_portal_login_page_id'      => array( 'title' => 'Connexion apprenant', 'slug' => 'connexion', 'content' => '[acdc_of_learner_login]' ),
    'acdc_of_learner_portal_dashboard_page_id'  => array( 'title' => 'Tableau de bord apprenant', 'slug' => 'tableau-de-bord', 'content' => '[acdc_of_learner_portal tab="dashboard"]' ),
    'acdc_of_learner_portal_formations_page_id' => array( 'title' => 'Mes formations', 'slug' => 'mes-formations', 'content' => '[acdc_of_learner_portal tab="formations"]' ),
    'acdc_of_learner_portal_formation_page_id'  => array( 'title' => 'Ma formation', 'slug' => 'ma-formation', 'content' => '[acdc_of_learner_portal tab="formation"]' ),
    'acdc_of_learner_portal_planning_page_id'   => array( 'title' => 'Mon planning', 'slug' => 'mon-planning', 'content' => '[acdc_of_learner_portal tab="planning"]' ),
    'acdc_of_learner_portal_documents_page_id'  => array( 'title' => 'Mes documents', 'slug' => 'mes-documents', 'content' => '[acdc_of_learner_portal tab="documents"]' ),
    'acdc_of_learner_portal_library_page_id'    => array( 'title' => 'Ma bibliothèque', 'slug' => 'ma-bibliotheque', 'content' => '[acdc_of_learner_portal tab="library"]' ),
    'acdc_of_learner_portal_mes_quiz_page_id'        => array( 'title' => 'Mes quiz', 'slug' => 'mes-quiz', 'content' => '[acdc_of_learner_portal tab="mes_quiz"]' ),
    'acdc_of_learner_portal_mes_signatures_page_id'  => array( 'title' => 'Mes signatures', 'slug' => 'mes-signatures', 'content' => '[acdc_of_learner_portal tab="mes_signatures"]' ),
    'acdc_of_learner_portal_profile_page_id'         => array( 'title' => 'Mon profil', 'slug' => 'mon-profil', 'content' => '[acdc_of_learner_portal tab="profile"]' ),
  );
  foreach ( $learner_children as $option_key => $page ) {
    $page_id = (int) get_option( $option_key, 0 );
    $path = 'espace-apprenant/' . $page['slug'];
    if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
      $existing = get_page_by_path( $path );
      if ( $existing instanceof WP_Post ) {
        $page_id = (int) $existing->ID;
      } else {
        $page_id = wp_insert_post( array( 'post_title' => $page['title'], 'post_name' => $page['slug'], 'post_parent' => $learner_parent_id, 'post_content' => $page['content'], 'post_status' => 'publish', 'post_type' => 'page' ) );
      }
      update_option( $option_key, (int) $page_id );
    }
    if ( $page_id ) {
      wp_update_post( array(
        'ID'           => $page_id,
        'post_title'   => $page['title'],
        'post_parent'  => $learner_parent_id,
        'post_content' => $page['content'],
        'post_status'  => 'publish',
      ) );
    }
  }

  if ( method_exists( $this, 'ensure_company_static_document_pages' ) ) {
    $this->ensure_company_static_document_pages();
  }

  $questionnaire_page_id = (int) get_option( 'acdc_of_questionnaire_session_page_id', 0 );
  if ( ! $questionnaire_page_id || 'publish' !== get_post_status( $questionnaire_page_id ) ) {
    $existing_questionnaire = get_page_by_path( 'questionnaire-session' );
    if ( $existing_questionnaire instanceof WP_Post ) {
      $questionnaire_page_id = (int) $existing_questionnaire->ID;
      wp_update_post( array( 'ID' => $questionnaire_page_id, 'post_content' => '[acdc_of_questionnaire_session]' ) );
    } else {
      $questionnaire_page_id = wp_insert_post( array( 'post_title' => 'Questionnaire Session', 'post_name' => 'questionnaire-session', 'post_content' => '[acdc_of_questionnaire_session]', 'post_status' => 'publish', 'post_type' => 'page' ) );
    }
    update_option( 'acdc_of_questionnaire_session_page_id', (int) $questionnaire_page_id );
  }

  /* ACDC 3.20.83 — Création de la page /extranet-formateur/ (auth custom). */
  if ( method_exists( $this, 'ensure_trainer_portal_page' ) ) {
    $this->ensure_trainer_portal_page();
  }
}public function register_shortcodes() {
    add_shortcode( 'acdc_of_login', array( $this, 'render_login_shortcode' ) );
    add_shortcode( 'acdc_of_portal', array( $this, 'render_portal_shortcode' ) );
    add_shortcode( 'acdc_of_quality_compliance', array( $this, 'render_quality_compliance_shortcode' ) );
    add_shortcode( 'acdc_of_catalog', array( $this, 'render_catalog_shortcode' ) );
    add_shortcode( 'acdc_of_marketing_public', array( $this, 'render_marketing_public_shortcode' ) );
    add_shortcode( 'acdc_of_questionnaire_session', array( $this, 'render_questionnaire_session_shortcode' ) );
    add_shortcode( 'acdc_of_learner_login', array( $this, 'render_learner_login_shortcode' ) );
    add_shortcode( 'acdc_of_learner_portal_home', array( $this, 'render_learner_portal_home_shortcode' ) );
    add_shortcode( 'acdc_of_learner_portal', array( $this, 'render_learner_portal_shortcode' ) );
    if ( method_exists( $this, 'render_company_static_document_shortcode' ) ) {
      add_shortcode( 'acdc_of_company_static_doc', array( $this, 'render_company_static_document_shortcode' ) );
    }
    /* ACDC 3.21.13 — Shortcode formulaire public analyse du besoin. */
    add_shortcode( 'acdc_nad_formulaire', array( $this, 'render_nad_formulaire_shortcode' ) );
  }  private function is_admin_manager() {
    /* Compat historique : les admins manage_options gardent tout leur accès.
       Voie ouverte : la capacité pivot « accès ERP » (modèle de capacités métier,
       remplacement progressif et NON régressif du tout-manage_options). */
    return current_user_can( 'manage_options' )
      || current_user_can( \ACDC\Support\Capabilities::PRIMARY );
  }  private function now_mysql() {
    return current_time( 'mysql' );
  }  private function current_date() {
    return current_time( 'Y-m-d' );
  }  private function log_error( $context, $message, $data = array() ) {
    $payload = array(
      'context' => sanitize_key( (string) $context ),
      'message' => wp_strip_all_tags( (string) $message ),
      'data'    => is_array( $data ) ? $data : array(),
    );

    if ( function_exists( 'error_log' ) ) {
      error_log( '[ACDC SAAS OF] ' . wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
    }

    $this->insert_system_log( array(
      'log_level'     => 'error',
      'event_type'    => 'system_error',
      'action_key'    => sanitize_key( (string) $context ),
      'object_type'   => 'system',
      'object_id'     => 0,
      'user_id'       => get_current_user_id(),
      'result_status' => 'error',
      'message'       => wp_strip_all_tags( (string) $message ),
      'context_json'  => is_array( $data ) ? $data : array(),
      'source'        => 'plugin',
    ) );
  }  private function require_manage_options( $message = 'Accès refusé.' ) {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html( $message ) );
    }
  }

  private function require_admin_manager_access( $message = 'Accès réservé aux administrateurs.' ) {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      $this->redirect_to_login( $message, 'error' );
    }
  }

  private function require_manage_options_nonce( $action, $query_arg = '_wpnonce', $message = 'Accès refusé.' ) {
    $this->require_manage_options( $message );
    $this->verify_nonce_or_die( $action, $query_arg );
  }

  private function require_admin_manager_nonce( $action, $query_arg = '_wpnonce', $message = 'Accès réservé aux administrateurs.' ) {
    $this->require_admin_manager_access( $message );
    $this->verify_nonce_or_die( $action, $query_arg );
  }

  private function get_current_plugin_role_label() {
    if ( ! is_user_logged_in() ) {
      return '';
    }

    $user_id = get_current_user_id();
    $label   = (string) get_user_meta( $user_id, 'acdc_portal_role_label', true );
    if ( '' !== $label ) {
      return $label;
    }

    $wp_user = wp_get_current_user();
    if ( $wp_user instanceof \WP_User && ! empty( $wp_user->roles ) && is_array( $wp_user->roles ) ) {
      $primary_role = (string) reset( $wp_user->roles );
      if ( 'administrator' === $primary_role ) {
        return 'Administrateur WordPress';
      }
      if ( 'subscriber' === $primary_role ) {
        return 'Lecture seule';
      }
    }

    return '';
  }

  private function is_current_plugin_read_only_user() {
    $label = strtolower( remove_accents( (string) $this->get_current_plugin_role_label() ) );
    return in_array( $label, array( 'lecture seule', 'lecture limitee', 'lecture limitée' ), true );
  }

  private function get_plugin_screen_restricted_tabs() {
    return array(
      'users',
      'settings',
      'agent_audit',
      'maintenance',
      'configuration',
      'marketing_settings',
      'marketing_supervision',
      'billing_settings',
      'questionnaire_settings',
      'ui_system',
      'ui_variables',
    );
  }

  private function get_plugin_screen_write_actions() {
    return array( 'new', 'edit', 'delete', 'archive', 'duplicate', 'dupliquer', 'save', 'create', 'update', 'assign', 'relance', 'send', 'plan', 'close', 'reopen', 'sources', 'new_article', 'edit_article', 'new_source', 'edit_source' );
  }

  private function current_user_can_access_plugin_screen( $tab, $action = 'list', $context = 'front' ) {
    $tab    = sanitize_key( (string) $tab );
    $action = sanitize_key( (string) $action );

    if ( current_user_can( 'manage_options' ) ) {
      return true;
    }

    if ( ! is_user_logged_in() ) {
      return false;
    }

    if ( 'admin' === $context ) {
      return false;
    }

    $read_only = $this->is_current_plugin_read_only_user();
    if ( ! $read_only ) {
      return false;
    }

    if ( in_array( $tab, $this->get_plugin_screen_restricted_tabs(), true ) ) {
      return false;
    }

    if ( in_array( $action, $this->get_plugin_screen_write_actions(), true ) ) {
      return false;
    }

    return true;
  }

  /**
   * Les actions publiques qui ne passent PAS par un gestionnaire
   * `admin_post_nopriv_` — routes front, formulaires publics interceptés
   * ailleurs. La liste ne sert plus qu'à ces cas-là.
   */
  private function get_public_plugin_admin_post_actions() {
    return array(
      'acdc_catalog_register',
      'acdc_download_need_pdf',
      'acdc_front_login',
      'acdc_front_logout',
      'acdc_join_questionnaire_session',
      'acdc_marketing_public_submit',
      'acdc_sig_submit',
      'acdc_submit_public_questionnaire_session',
      'acdc_submit_questionnaire_answer',
    );
  }

  /**
   * ACDC 3.25.255 — DEUX LISTES DE CE QUI EST PUBLIC, ET ELLES AVAIENT DIVERGÉ.
   *
   * Cette garde exige `manage_options` pour toute action « acdc_ » passant par
   * admin-post.php, sauf celles inscrites ici. La liste en comptait NEUF. Le
   * plugin déclare TRENTE-ET-UNE actions publiques, par
   * `add_action( 'admin_post_nopriv_acdc_…' )`. Vingt-deux étaient donc refusées
   * à tout visiteur non connecté, avec « Action non autorisée. » — et invisibles
   * pour un administrateur, qui passe la garde sans la voir.
   *
   * Conséquences observées : aucun signataire extérieur ne pouvait valider son
   * code de vérification, ni consulter le document à signer, ni demander un
   * nouveau code ; aucun formateur ne pouvait activer son accès, déposer un
   * document de séance ou réinitialiser son mot de passe ; le PDF de proposition
   * envoyé au prospect était refusé. Le portail apprenant, lui, fonctionnait :
   * la règle de préfixe ci-dessous l'autorisait en bloc.
   *
   * La liste était une SECONDE source de vérité. Or enregistrer un gestionnaire
   * `admin_post_nopriv_x`, c'est déjà déclarer que x est public : c'est la
   * déclaration qui fait foi. On la lit directement, et la dérive devient
   * impossible.
   *
   * Ce que la garde protège reste protégé : chaque gestionnaire public refait
   * son propre contrôle — jeton de signature à usage unique, nonce lié au
   * jeton, session du portail formateur ou apprenant. La garde n'était pas leur
   * protection, elle était leur obstacle.
   */
  private function is_public_plugin_admin_post_action( $action ) {
    $action = sanitize_key( (string) $action );
    if ( '' === $action ) {
      return false;
    }

    if ( 0 === strpos( $action, 'acdc_learner_' ) ) {
      return true;
    }

    /* La déclaration fait foi : un gestionnaire public existe pour cette
       action, donc l'action est publique. */
    if ( has_action( 'admin_post_nopriv_' . $action ) ) {
      return true;
    }

    return in_array( $action, $this->get_public_plugin_admin_post_actions(), true );
  }

  public function enforce_plugin_request_permissions() {
    if ( wp_doing_ajax() ) {
      return;
    }

    $action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
    if ( '' !== $action && 0 === strpos( $action, 'acdc_' ) && ! $this->is_public_plugin_admin_post_action( $action ) ) {
      $this->require_manage_options( 'Action non autorisée.' );
    }

    if ( ! is_admin() ) {
      return;
    }

    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( '' === $page || 0 !== strpos( $page, 'acdc-of-' ) ) {
      return;
    }

    $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
    if ( '' === $tab ) {
      $slug_map = array(
        'acdc-of-dashboard'             => 'dashboard',
        'acdc-of-needs'                 => 'needs',
        'acdc-of-calendar'              => 'calendar',
        'acdc-of-sessions-calendar'     => 'sessions_calendar',
        'acdc-of-pre-meetings'          => 'pre_meetings',
        'acdc-of-sessions-pending'      => 'sessions_pending',
        'acdc-of-sessions-validated'    => 'sessions_validated',
        'acdc-of-prospects'             => 'prospects',
        'acdc-of-prospect-followup'     => 'prospect_followup',
        'acdc-of-learners'              => 'learners',
        'acdc-of-formations'            => 'formations',
        'acdc-of-groups'                => 'groups',
        'acdc-of-companies'             => 'companies',
        'acdc-of-funders'               => 'funders',
        'acdc-of-trainers'              => 'trainers',
        'acdc-of-quiz'                  => 'quiz',
        'acdc-of-need-analyses'         => 'need_analyses',
        'acdc-of-training-files'        => 'training_files',
        'acdc-of-registration-contract' => 'registration_contract',
        'acdc-of-register-training'     => 'register_training',
        'acdc-of-mid-surveys'           => 'mid_surveys',
        'acdc-of-hot-surveys'           => 'hot_surveys',
        'acdc-of-evaluations'           => 'evaluations',
        'acdc-of-questionnaire-sessions'=> 'questionnaire_sessions',
        'acdc-of-questionnaire-results' => 'questionnaire_results',
        'acdc-of-questionnaire-settings'=> 'questionnaire_settings',
        'acdc-of-cold-surveys'          => 'cold_surveys',
        'acdc-of-trainer-surveys'       => 'trainer_surveys',
        'acdc-of-company-surveys'       => 'company_surveys',
        'acdc-of-funder-surveys'        => 'funder_surveys',
        'acdc-of-users'                 => 'users',
        'acdc-of-contacts'              => 'contacts',
        'acdc-of-documents'             => 'documents',
        'acdc-of-learner-portal'        => 'learner_portal',
        'acdc-of-settings'              => 'settings',
        'acdc-of-agent-audit'           => 'agent_audit',
        'acdc-of-configuration'         => 'configuration',
        'acdc-of-maintenance'           => 'maintenance',
        'acdc-of-ui-system'             => 'ui_system',
        'acdc-of-ui-variables'          => 'ui_variables',
      );
      $tab = isset( $slug_map[ $page ] ) ? $slug_map[ $page ] : '';
    }

    if ( '' !== $tab && ! $this->current_user_can_access_plugin_screen( $tab, isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list', 'admin' ) ) {
      wp_die( esc_html( 'Accès refusé à cet écran.' ) );
    }
  }

  private function verify_nonce_or_die( $action, $query_arg = '_wpnonce', $message = 'La vérification de sécurité a échoué.' ) {
    $nonce = isset( $_REQUEST[ $query_arg ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $query_arg ] ) ) : '';
    if ( '' === $nonce || ! wp_verify_nonce( $nonce, $action ) ) {
      wp_die( esc_html( $message ) );
    }
  }  private function secure_admin_post_url( $action, $args = array(), $nonce_action = '' ) {
    $url = add_query_arg( array_merge( array( 'action' => $action ), $args ), admin_url( 'admin-post.php' ) );
    if ( $nonce_action ) {
      $url = wp_nonce_url( $url, $nonce_action );
    }
    return $url;
  }  private function send_html_download_response( $filename, $html ) {
    while ( ob_get_level() ) {
      ob_end_clean();
    }
    nocache_headers();
    header( 'X-Content-Type-Options: nosniff' );
    header( 'X-Frame-Options: SAMEORIGIN' );
    header( 'Referrer-Policy: same-origin' );
    header( 'Content-Type: text/html; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
    $html = (string) $html;
    header( 'Content-Length: ' . strlen( $html ) );
    echo $html;
    exit;
  }
  private function send_json_download_response( $filename, $payload ) {
    while ( ob_get_level() ) {
      ob_end_clean();
    }
    nocache_headers();
    header( 'X-Content-Type-Options: nosniff' );
    header( 'X-Frame-Options: SAMEORIGIN' );
    header( 'Referrer-Policy: same-origin' );
    header( 'Content-Type: application/json; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
    $payload = (string) $payload;
    header( 'Content-Length: ' . strlen( $payload ) );
    echo $payload;
    exit;
  }
  private function send_file_download_response( $path, $content_type = 'application/octet-stream', $download_name = '' ) {
    $path = (string) $path;
    if ( '' === $path || ! is_file( $path ) ) {
      wp_die( esc_html( 'Fichier introuvable.' ) );
    }
    while ( ob_get_level() ) {
      ob_end_clean();
    }
    nocache_headers();
    header( 'X-Content-Type-Options: nosniff' );
    header( 'X-Frame-Options: SAMEORIGIN' );
    header( 'Referrer-Policy: same-origin' );
    header( 'Content-Type: ' . $content_type );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( '' !== $download_name ? $download_name : basename( $path ) ) . '"' );
    $size = @filesize( $path );
    if ( false !== $size ) {
      header( 'Content-Length: ' . (string) $size );
    }
    readfile( $path );
    exit;
  }  private function get_safe_db_error_message( $fallback = 'Une erreur technique est survenue.' ) {
    global $wpdb;
    if ( ! empty( $wpdb->last_error ) ) {
      $this->log_error( 'db', $wpdb->last_error );
    }
    return $fallback;
  }  /**
   * Enregistre une action dans la piste d'audit.
   *
   * ACDC 3.25.292 — LE JOURNAL S'EFFAÇAIT TOUT SEUL. Cette fonction recopiait
   * chaque événement dans une option limitée aux 200 DERNIERS : passé ce seuil,
   * les plus anciens disparaissaient définitivement, et une semaine chargée
   * suffisait à tout effacer. Personne ne lisait cette option — aucun écran ne
   * l'affichait — et elle était réécrite en entier à chaque action, ce qui
   * coûtait une écriture de plus en plus lourde à mesure qu'elle grossissait.
   *
   * La table, elle, gardait déjà tout. Il ne manquait que trois choses : de quoi
   * savoir d'où venait l'action, une règle de conservation, et un écran pour la
   * relire. Les trois sont arrivées avec cette version ; l'option, elle, s'en va.
   */
  private function log_action_event( $action, $object_type, $object_id = 0, $result = 'success', $extra = array() ) {
    $entry = array(
      'timestamp'   => current_time( 'mysql' ),
      'user_id'     => get_current_user_id(),
      'action'      => sanitize_key( (string) $action ),
      'object_type' => sanitize_key( (string) $object_type ),
      'object_id'   => absint( $object_id ),
      'result'      => sanitize_key( (string) $result ),
      'extra'       => is_array( $extra ) ? $extra : array(),
    );

    $this->insert_system_log( array(
      'log_level'     => ( 'success' === $entry['result'] ) ? 'info' : 'warning',
      'event_type'    => 'action_event',
      'action_key'    => $entry['action'],
      'object_type'   => $entry['object_type'],
      'object_id'     => $entry['object_id'],
      'user_id'       => $entry['user_id'],
      'result_status' => $entry['result'],
      'message'       => $entry['action'] . ' sur ' . $entry['object_type'],
      'context_json'  => $entry['extra'],
      'source'        => 'plugin',
    ) );
  }

  private function insert_system_log( $args = array() ) {
    global $wpdb;

    if ( empty( $this->system_log_table ) ) {
      return false;
    }

    $defaults = array(
      'log_level'     => 'info',
      'event_type'    => 'generic',
      'action_key'    => '',
      'object_type'   => '',
      'object_id'     => 0,
      'user_id'       => get_current_user_id(),
      'result_status' => 'success',
      'message'       => '',
      'context_json'  => array(),
      'source'        => 'plugin',
    );

    $data = wp_parse_args( is_array( $args ) ? $args : array(), $defaults );
    $insert = array(
      'log_level'     => sanitize_key( (string) $data['log_level'] ),
      'event_type'    => sanitize_key( (string) $data['event_type'] ),
      'action_key'    => sanitize_key( (string) $data['action_key'] ),
      'object_type'   => sanitize_key( (string) $data['object_type'] ),
      'object_id'     => absint( $data['object_id'] ),
      'user_id'       => absint( $data['user_id'] ),
      'result_status' => sanitize_key( (string) $data['result_status'] ),
      'message'       => wp_strip_all_tags( (string) $data['message'] ),
      'context_json'  => wp_json_encode( is_array( $data['context_json'] ) ? $data['context_json'] : array(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
      'source'        => sanitize_key( (string) $data['source'] ),
      'ip_address'    => $this->acdc_adresse_appelante(),
      'user_agent'    => $this->acdc_navigateur_appelant(),
      'created_at'    => current_time( 'mysql' ),
    );

    $format = array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );
    return ( false !== $wpdb->insert( $this->system_log_table, $insert, $format ) );
  }

  private function get_plugin_table_map() {
    $carte = array(
      'companies' => $this->company_table,
      'contacts' => $this->contact_table,
      'documents' => $this->document_table,
      'formations' => $this->formation_table,
      'sessions' => $this->session_table,
      'learners' => $this->learner_table,
      'needs' => $this->need_table,
      'prospects' => $this->prospect_table,
      'groups' => $this->group_table,
      'funders' => $this->funder_table,
      'trainers' => $this->trainer_table,
      'quizzes' => $this->quiz_table,
      'evaluations' => $this->evaluation_table,
      'need_analyses' => $this->need_analysis_table,
      'registration_contracts' => $this->registration_contract_table,
      'training_registrations' => $this->training_registration_table,
      'prospect_rdvs' => $this->prospect_rdv_table,
      'pre_meetings' => $this->pre_meeting_table,
      'questionnaire_sessions' => $this->questionnaire_session_table,
      'questionnaire_participants' => $this->questionnaire_participant_table,
      'questionnaire_answers' => $this->questionnaire_answer_table,
      'questionnaire_models' => $this->questionnaire_model_table,
      'questionnaire_actions' => $this->questionnaire_action_table,
      'questionnaire_logs' => $this->questionnaire_log_table,
      'learner_portal_accounts' => $this->learner_portal_account_table,
      'learner_portal_tokens' => $this->learner_portal_token_table,
      'learner_portal_sessions' => $this->learner_portal_session_table,
      'learner_portal_logs' => $this->learner_portal_log_table,
      'system_logs' => $this->system_log_table,
    ) + $this->acdc_satellite_table_map();

    /* ACDC 3.25.293 — CES DEUX LISTES SONT TENUES À LA MAIN, ET ELLES AVAIENT
       DÉCROCHÉ. Ensemble elles nomment 40 tables ; le plugin en compte 56.
       Étaient donc absentes de toutes les sauvegardes : les FACTURES, les DEVIS,
       le registre des réclamations, les contrats de sous-traitance, les quatre
       tables du portail formateur, ses documents, ses évaluations et son cahier
       de bord, les blocs et questions de recueil, les activités de prospection,
       les thématiques et la veille.
       Une liste qu'il faut penser à compléter finit toujours par ne plus l'être.
       On demande donc à la base ce qu'elle contient : toute table du plugin est
       emportée, y compris celle qu'un module créera demain.
       Les étiquettes ci-dessus sont conservées telles quelles — ce sont les noms
       des fichiers à l'intérieur des archives déjà produites, et une archive
       faite hier doit rester restaurable aujourd'hui. */
    global $wpdb;
    $connues = array_flip( array_filter( $carte ) );
    $motif   = $wpdb->esc_like( $wpdb->prefix . 'acdc_' ) . '%';
    foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $motif ) ) as $table ) {
      $table = (string) $table;
      if ( '' === $table || isset( $connues[ $table ] ) ) {
        continue;
      }
      $carte[ substr( $table, strlen( $wpdb->prefix ) ) ] = $table;
    }

    return $carte;
  }

  /**
   * ACDC 3.25.192 — Les tables satellites entrent dans les deux périmètres.
   *
   * Émargement, moteur de quiz et signature électronique vivaient hors de la
   * sauvegarde. Les tables de signature étaient même PURGÉES sans avoir jamais
   * été sauvegardées : une suppression totale détruisait la trace des documents
   * signés sans laisser aucun moyen de revenir en arrière.
   *
   * Pour l'émargement et le quiz, la conséquence était l'inverse et tout aussi
   * grave : n'étant purgés nulle part, ils survivaient à la suppression des
   * séances qu'ils documentent. Or la purge remet les compteurs d'identifiants
   * à 1 : les séances recréées ensuite reprennent les anciens numéros, et
   * l'émargement orphelin s'y raccroche tout seul. Une formation pouvait ainsi
   * afficher une feuille signée par des personnes qui n'y étaient jamais
   * venues — sur une pièce exigée par Qualiopi.
   *
   * La règle est simple et vaut dans les deux sens : ce qui documente une
   * donnée doit être sauvegardé avec elle, et disparaître avec elle.
   */
  private function acdc_satellite_table_map() {
    global $wpdb;
    $qz = $wpdb->prefix . 'acdc_of_qz_';

    return array(
      'emarg_sessions'    => $wpdb->prefix . 'acdc_of_emarg_sessions',
      'emarg_learners'    => $wpdb->prefix . 'acdc_of_emarg_learners',
      'sig_requests'      => $wpdb->prefix . 'acdc_sig_requests',
      'sig_audit'         => $wpdb->prefix . 'acdc_sig_audit',
      'qz_quizzes'        => $qz . 'quizzes',
      'qz_questions'      => $qz . 'questions',
      'qz_answers'        => $qz . 'answers',
      'qz_objectives'     => $qz . 'objectives',
      'qz_sessions'       => $qz . 'sessions',
      'qz_participants'   => $qz . 'participants',
      'qz_player_answers' => $qz . 'player_answers',
      'qz_logs'           => $qz . 'logs',
      'workflow_runs'     => $this->workflow_run_table,
      'workflow_steps'    => $this->workflow_step_table,
      /* ACDC 3.25.207 — Les documents déposés sur une séance sont des pièces
         Qualiopi : ils entrent dans les deux périmètres, sauvegarde et purge,
         au même titre que l'émargement. Une preuve qu'une restauration ne
         rendrait pas n'est pas une preuve. */
      'session_documents' => $this->trainer_resource_table,
    );
  }


  private function get_backup_base_directory() {
    $uploads = wp_upload_dir();
    if ( empty( $uploads['basedir'] ) ) {
      return '';
    }
    $dir = trailingslashit( $uploads['basedir'] ) . 'acdc-backups';
    if ( ! file_exists( $dir ) ) {
      wp_mkdir_p( $dir );
    }
    return is_dir( $dir ) ? $dir : '';
  }

  /**
   * ACDC 3.25.204 — Le repère de formation : « 1.0 » et « 1.1 ».
   *
   * Une même formation existe en présentiel et en distanciel : deux fiches, deux
   * tarifs, deux identifiants techniques qui se suivent sans rien dire de leur
   * parenté. La liste affichait 1 et 2, puis 3 et 4, et rien ne signalait que 1
   * et 2 sont la même formation.
   *
   * Le repère est un AFFICHAGE, jamais l'identifiant réel. La colonne `id` est
   * référencée par les devis, les conventions, les séances et les inscriptions :
   * la renuméroter romprait tous ces rattachements. On calcule donc un numéro de
   * famille par-dessus, sans toucher à la base.
   *
   * Deux formations appartiennent à la même famille lorsqu'elles portent le même
   * intitulé — ou, mieux, lorsque l'une déclare l'autre comme formation de base.
   * Une variante au titre différent (« … en 2 jours ») forme donc sa propre
   * famille : c'est une autre offre, avec sa durée et son prix.
   *
   * Le rang dans la famille suit la modalité : le présentiel porte .0, le
   * distanciel .1, le reste ensuite. Une formation sans jumelle porte quand même
   * un .0, pour que la colonne reste homogène et qu'une jumelle puisse arriver.
   */
  private function acdc_formation_reference_map() {
    static $map = null;
    if ( null !== $map ) {
      return $map;
    }

    global $wpdb;
    $map = array();

    $rows = $wpdb->get_results(
      "SELECT id, title, modality, base_formation_id FROM {$this->formation_table} ORDER BY id ASC"
    );
    if ( empty( $rows ) ) {
      return $map;
    }

    /* Regroupement par famille, dans l'ordre d'apparition des identifiants :
       la numérotation affichée suit ainsi l'ordre que David connaît déjà. */
    $families = array();
    $by_title = array();
    foreach ( $rows as $row ) {
      $key = '';
      if ( ! empty( $row->base_formation_id ) ) {
        $key = 'base:' . (int) $row->base_formation_id;
      } else {
        $normalised = strtolower( trim( preg_replace( '/\s+/u', ' ', (string) $row->title ) ) );
        if ( ! isset( $by_title[ $normalised ] ) ) {
          $by_title[ $normalised ] = 'title:' . (int) $row->id;
        }
        $key = $by_title[ $normalised ];
      }
      if ( ! isset( $families[ $key ] ) ) {
        $families[ $key ] = array();
      }
      $families[ $key ][] = $row;
    }

    $family_number = 0;
    foreach ( $families as $members ) {
      $family_number++;

      usort( $members, static function( $a, $b ) {
        $rank = static function( $modality ) {
          $modality = strtolower( (string) $modality );
          if ( false !== strpos( $modality, 'présentiel' ) || false !== strpos( $modality, 'presentiel' ) ) {
            return 0;
          }
          if ( false !== strpos( $modality, 'distanciel' ) ) {
            return 1;
          }
          return 2;
        };
        $ra = $rank( $a->modality );
        $rb = $rank( $b->modality );
        return ( $ra === $rb ) ? ( (int) $a->id <=> (int) $b->id ) : ( $ra <=> $rb );
      } );

      foreach ( array_values( $members ) as $index => $member ) {
        $map[ (int) $member->id ] = $family_number . '.' . $index;
      }
    }

    return $map;
  }

  /**
   * Le repère affiché d'une formation. Renvoie l'identifiant technique tel quel
   * si la formation est introuvable — mieux vaut un numéro brut qu'une case vide.
   */
  public function acdc_formation_reference( $formation_id ) {
    $formation_id = (int) $formation_id;
    if ( $formation_id <= 0 ) {
      return '';
    }
    $map = $this->acdc_formation_reference_map();
    return isset( $map[ $formation_id ] ) ? $map[ $formation_id ] : (string) $formation_id;
  }

  /**
   * Le titre d'une formation précédé de son repère de famille : « 1.0 — Créer
   * et animer une page Facebook ». Utilisé partout où la formation est nommée
   * dans une pièce qui sort de l'application.
   */
  public function acdc_formation_labelled( $formation_id, $title ) {
    $title = trim( (string) $title );
    $ref   = $this->acdc_formation_reference( $formation_id );
    if ( '' === $ref || '' === $title ) {
      return $title;
    }
    /* Un repère déjà présent en tête du titre ne se redouble pas. */
    if ( 0 === strpos( $title, $ref . ' ' ) || 0 === strpos( $title, $ref . ' —' ) ) {
      return $title;
    }
    return $ref . ' — ' . $title;
  }

  private function get_backup_retention_count() {
    $count = absint( get_option( 'acdc_of_backup_retention_count', 30 ) );
    return $count > 0 ? $count : 30;
  }

  /**
   * ACDC 3.25.184 — La rétention est appliquée par famille, plus globalement.
   *
   * Elle ne l'était pas : les répertoires étaient triés par date, tous types
   * confondus, et tout ce qui dépassait le quota partait. Une rafale
   * d'instantanés automatiques suffisait donc à effacer les sauvegardes
   * manuelles et les sauvegardes de sécurité — celles que l'on prend justement
   * avant une opération risquée. C'est ce qui s'est produit le 9 août.
   *
   * Désormais, les instantanés automatiques d'avant-mise-à-jour (« update-… »)
   * ont leur propre quota, plafonné à dix, et ne peuvent plus évincer une
   * sauvegarde décidée par un humain. Les autres se partagent la rétention
   * réglée dans l'écran de configuration.
   */
  private function prune_backup_directories() {
    $base = $this->get_backup_base_directory();
    if ( '' === $base ) {
      return;
    }
    $dirs = glob( trailingslashit( $base ) . '*', GLOB_ONLYDIR );
    if ( ! is_array( $dirs ) ) {
      return;
    }

    $retain = $this->get_backup_retention_count();
    $newest_first = static function( $a, $b ) { return filemtime( $b ) <=> filemtime( $a ); };

    $automatic = array();
    $deliberate = array();
    foreach ( $dirs as $dir ) {
      /* Le nom d'un répertoire vaut « Ymd-His-<libellé> » : la famille se lit
         après l'horodatage, jamais ailleurs dans le nom. */
      if ( preg_match( '/^\d{8}-\d{6}-update-/', basename( $dir ) ) ) {
        $automatic[] = $dir;
      } else {
        $deliberate[] = $dir;
      }
    }

    usort( $automatic, $newest_first );
    usort( $deliberate, $newest_first );

    $obsolete = array_merge(
      array_slice( $automatic, min( $retain, 10 ) ),
      array_slice( $deliberate, $retain )
    );

    foreach ( $obsolete as $dir ) {
      $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::CHILD_FIRST
      );
      foreach ( $files as $file ) {
        if ( $file->isDir() ) {
          @rmdir( $file->getPathname() );
        } else {
          @unlink( $file->getPathname() );
        }
      }
      @rmdir( $dir );
    }
  }

  /* ACDC 3.25.184 — Existe-t-il déjà un instantané pour cette transition de
     version ? Le nom du répertoire porte l'horodatage devant, d'où le motif. */
  private function acdc_update_snapshot_exists( $label ) {
    $base = $this->get_backup_base_directory();
    if ( '' === $base ) {
      return false;
    }
    $found = glob( trailingslashit( $base ) . '*-' . sanitize_file_name( strtolower( (string) $label ) ), GLOB_ONLYDIR );
    return is_array( $found ) && ! empty( $found );
  }

/**
 * Le nom d'une sauvegarde, tel qu'il se lit.
 *
 * ACDC 3.25.293 — Demandé par l'exploitant : « Sauvegarde du jj-mm-aaaa —
 * 00h00mn ». C'est le nom affiché, et celui de l'archive. Sur le disque le
 * fichier garde une forme sans espace ni tiret long : un nom de fichier voyage
 * mal d'un système à l'autre, et une archive qu'on ne peut plus ouvrir ne
 * protège rien.
 */
private function acdc_nom_sauvegarde( $quand = null ) {
  /* ACDC 3.25.295 — LE DÉCALAGE ÉTAIT COMPTÉ DEUX FOIS.
     current_time('timestamp') ne rend PAS un instant : il rend time() auquel le
     décalage du site a déjà été ajouté. wp_date() attend l'inverse — un instant
     vrai, qu'il convertit lui-même à l'heure du site. Les deux enchaînés
     ajoutaient donc le décalage une seconde fois : l'archive déposée à 13h11
     s'appelait « 15h10mn », et l'écran affichait les deux chiffres à quelques
     lignes d'intervalle sans que rien ne signale la contradiction.
     C'est un relevé de l'agent de recette qui l'a vu. */
  $quand = ( null === $quand ) ? time() : ( is_numeric( $quand ) ? (int) $quand : strtotime( (string) $quand ) );
  if ( (int) $quand <= 0 ) {
    return 'Sauvegarde';
  }
  return sprintf( 'Sauvegarde du %s — %sh%smn', wp_date( 'd-m-Y', (int) $quand ), wp_date( 'H', (int) $quand ), wp_date( 'i', (int) $quand ) );
}

/** Le même nom, utilisable comme nom de fichier partout. */
private function acdc_nom_fichier_sauvegarde( $quand = null ) {
  $quand = ( null === $quand ) ? time() : ( is_numeric( $quand ) ? (int) $quand : strtotime( (string) $quand ) );
  return 'sauvegarde-du-' . wp_date( 'd-m-Y-H\hi\m\n', (int) $quand );
}

  private function get_backup_run_directory( $label ) {
    $base = $this->get_backup_base_directory();
    if ( '' === $base ) {
      return '';
    }
    $timestamp = time();
    $stamp = wp_date( 'Ymd-His', $timestamp );
    $slug = sanitize_file_name( strtolower( (string) $label ) );
    $dir = trailingslashit( $base ) . $stamp . '-' . $slug;
    if ( ! file_exists( $dir ) ) {
      wp_mkdir_p( $dir );
    }
    return is_dir( $dir ) ? $dir : '';
  }

  /**
   * ACDC 3.25.178 — Un site est réputé de PRODUCTION par défaut.
   *
   * Cette fonction ne renvoyait vrai que si l'une des constantes WP_ENV,
   * WP_ENVIRONMENT_TYPE ou APP_ENV était définie ET valait « prod ». Sur une
   * installation WordPress ordinaire, aucune de ces constantes n'existe : la
   * fonction renvoyait donc FAUX sur un vrai site de production, et l'écran
   * annonçait « Environnement détecté : hors production » à un organisme de
   * formation travaillant sur ses données réelles.
   *
   * Conséquence : le blocage de la purge en production, que l'écran présente
   * comme une protection active, ne s'appliquait jamais. Une protection qui ne
   * se déclenche pas est pire que pas de protection, parce qu'on lui fait
   * confiance.
   *
   * La règle s'inverse : on est en production SAUF si l'environnement se déclare
   * explicitement comme autre chose. C'est aussi la convention de WordPress, dont
   * wp_get_environment_type() répond « production » par défaut. Un site de
   * développement se déclare ; un site de production n'a rien à faire pour être
   * protégé.
   *
   * @return bool
   */
  private function is_production_environment() {
    $candidates = array();
    if ( function_exists( 'wp_get_environment_type' ) ) {
      $candidates[] = wp_get_environment_type();
    }
    if ( defined( 'WP_ENV' ) ) {
      $candidates[] = WP_ENV;
    }
    if ( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
      $candidates[] = WP_ENVIRONMENT_TYPE;
    }
    if ( defined( 'APP_ENV' ) ) {
      $candidates[] = APP_ENV;
    }

    $non_production = array( 'local', 'development', 'dev', 'staging', 'preprod', 'preproduction', 'test' );
    foreach ( $candidates as $candidate ) {
      $candidate = strtolower( trim( (string) $candidate ) );
      if ( '' === $candidate ) {
        continue;
      }
      if ( in_array( $candidate, array( 'prod', 'production', 'live' ), true ) ) {
        return true;
      }
      if ( in_array( $candidate, $non_production, true ) ) {
        return false;
      }
    }
    // Aucune déclaration exploitable : on protège.
    return true;
  }

  private function is_production_purge_blocked() {
    return $this->is_production_environment() && 'yes' !== get_option( 'acdc_of_purge_allowed_in_production', 'no' );
  }

  private function ensure_soft_delete_schema() {
    foreach ( $this->get_plugin_table_map() as $label => $table ) {
      if ( empty( $table ) || false !== strpos( $label, 'logs' ) || 'system_logs' === $label ) {
        continue;
      }
      $this->maybe_add_table_column( $table, 'deleted_at', 'DATETIME NULL' );
      $this->maybe_add_table_column( $table, 'deleted_by', 'BIGINT UNSIGNED NULL' );
      $this->maybe_add_table_index( $table, 'deleted_at', 'INDEX deleted_at (deleted_at)' );
      $this->maybe_add_table_index( $table, 'deleted_by', 'INDEX deleted_by (deleted_by)' );
    }
  }

  private function create_safety_backup_snapshot( $reason, $context = array() ) {
    $result = $this->backup_data_snapshot( 'safety-' . sanitize_key( (string) $reason ), array(
      'reason' => sanitize_key( (string) $reason ),
      'context' => is_array( $context ) ? $context : array(),
    ) );
    if ( ! empty( $result['manifest'] ) ) {
      update_option( 'acdc_of_last_safety_backup_file', $result['manifest'], false );
      update_option( 'acdc_of_last_safety_backup_at', current_time( 'mysql' ), false );
    }
    return $result;
  }

  private function create_manual_backup_snapshot( $reason = 'manual_backup', $context = array() ) {
    $result = $this->backup_data_snapshot( 'manual-' . sanitize_key( (string) $reason ), array(
      'reason' => sanitize_key( (string) $reason ),
      'context' => is_array( $context ) ? $context : array(),
      'backup_type' => 'manual',
    ) );
    if ( ! empty( $result['manifest'] ) ) {
      update_option( 'acdc_of_last_manual_backup_file', $result['manifest'], false );
      update_option( 'acdc_of_last_manual_backup_at', current_time( 'mysql' ), false );
    }
    return $result;
  }

  private function get_backup_options_snapshot() {
    $keys = array(
      'acdc_of_branding',
      'acdc_of_company_profile',
      'acdc_of_contract_params',
      'acdc_of_convocation_params',
      'acdc_of_subcontract_params',
      'acdc_of_attestation_params',
      'acdc_of_billing_settings',
      'acdc_of_catalog_settings',
      'acdc_of_questionnaire_settings',
      'acdc_of_marketing_settings',
      'acdc_of_agent_audit_settings',
      'acdc_of_keep_data_on_uninstall',
      'acdc_of_db_version',
      'acdc_of_purge_allowed_in_production',
      'acdc_of_backup_retention_count',
      'acdc_of_last_backup_file',
      'acdc_of_last_backup_at',
      'acdc_of_last_manual_backup_file',
      'acdc_of_last_manual_backup_at',
      'acdc_of_last_safety_backup_file',
      'acdc_of_last_safety_backup_at',
      'acdc_of_surveys_db_version',
      'acdc_sig_settings',
      'acdc_of_settings_page_id',
      'acdc_of_login_page_id',
      'acdc_of_catalog_page_id',
      'acdc_of_questionnaire_public_page_id',
      'acdc_of_marketing_public_page_id',
      'acdc_sig_page_id',
      'acdc_of_extranet_settings_page_id',
    );
    $snapshot = array();
    foreach ( $keys as $key ) {
      $snapshot[ $key ] = get_option( $key, null );
    }
    return $snapshot;
  }

  private function restore_backup_options_snapshot( $snapshot ) {
    if ( ! is_array( $snapshot ) ) {
      return;
    }
    foreach ( $snapshot as $key => $value ) {
      if ( ! is_string( $key ) || '' === $key ) {
        continue;
      }
      update_option( $key, $value, false );
    }
  }

  private function get_backup_absolute_path( $relative ) {
    $relative = preg_replace( '#^[\\/]+#', '', (string) $relative );
    if ( '' === $relative || false !== strpos( $relative, '..' ) ) {
      return '';
    }
    $base = $this->get_backup_base_directory();
    if ( '' === $base ) {
      return '';
    }
    $path = trailingslashit( $base ) . $relative;
    if ( ! file_exists( $path ) ) {
      return '';
    }
    // Défense en profondeur : le chemin résolu doit rester sous le répertoire de base.
    $real_path = realpath( $path );
    $real_base = realpath( $base );
    if ( false === $real_path || false === $real_base
      || 0 !== strpos( $real_path, trailingslashit( $real_base ) ) ) {
      return '';
    }
    return $path;
  }

  private function package_backup_snapshot( $dir, $label ) {
    if ( '' === $dir || ! is_dir( $dir ) || ! class_exists( 'ZipArchive' ) ) {
      return '';
    }
    /* ACDC 3.25.293 — L'archive s'appelait « manual-manual_backup.zip ». Elle
       porte désormais sa date : c'est ce qu'on lit dans un dossier de
       sauvegardes, et c'est ce qui a été demandé. */
    $zip_path = trailingslashit( $dir ) . sanitize_file_name( $this->acdc_nom_fichier_sauvegarde() ) . '.zip';
    $zip = new ZipArchive();
    if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
      return '';
    }
    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
      RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ( $iterator as $file ) {
      $pathname = $file->getPathname();
      if ( $pathname === $zip_path ) {
        continue;
      }
      $local = preg_replace( '#^[\\/]+#', '', str_replace( $dir, '', $pathname ) );
      if ( $file->isDir() ) {
        $zip->addEmptyDir( $local );
      } else {
        $zip->addFile( $pathname, $local );
      }
    }
    $zip->close();
    return file_exists( $zip_path ) ? basename( $dir ) . '/' . basename( $zip_path ) : '';
  }

  private function restore_backup_snapshot_from_manifest( $manifest_path ) {
    global $wpdb;

    if ( '' === $manifest_path || ! file_exists( $manifest_path ) ) {
      return new WP_Error( 'acdc_backup_manifest_missing', 'Fichier manifeste introuvable.' );
    }

    $manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
    if ( ! is_array( $manifest ) || empty( $manifest['tables'] ) ) {
      return new WP_Error( 'acdc_backup_manifest_invalid', 'Structure de sauvegarde invalide.' );
    }

    $this->create_safety_backup_snapshot( 'restore_backup', array( 'user_id' => get_current_user_id() ) );

    $report = array();

    /* ── LES FICHIERS DE PREUVE REVIENNENT AVEC LES LIGNES ──────────────
       ACDC 3.25.293 — Restaurer la base sans les fichiers rendait des
       émargements dont la signature n'existait plus. On ne remplace jamais un
       fichier déjà présent : une restauration répare ce qui manque, elle
       n'écrase pas ce qui vit. */
    $dossier_fichiers = trailingslashit( dirname( $manifest_path ) ) . 'fichiers';
    if ( is_dir( $dossier_fichiers ) ) {
      $uploads_cible = wp_get_upload_dir();
      $rendus = 0;
      $deja   = 0;
      $rates  = 0;
      if ( ! empty( $uploads_cible['basedir'] ) ) {
        $base_cible = trailingslashit( $uploads_cible['basedir'] );
        foreach ( new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator( $dossier_fichiers, FilesystemIterator::SKIP_DOTS ),
          RecursiveIteratorIterator::SELF_FIRST
        ) as $fichier ) {
          if ( $fichier->isDir() ) {
            continue;
          }
          $relatif_f = ltrim( str_replace( $dossier_fichiers, '', $fichier->getPathname() ), '/\\' );
          /* Une entrée d'archive ne doit jamais pouvoir écrire hors des
             téléversements : on refuse tout chemin qui remonte. */
          if ( false !== strpos( $relatif_f, '..' ) ) {
            $rates++;
            continue;
          }
          $destination_f = $base_cible . $relatif_f;
          if ( file_exists( $destination_f ) ) {
            $deja++;
            continue;
          }
          wp_mkdir_p( dirname( $destination_f ) );
          if ( @copy( $fichier->getPathname(), $destination_f ) ) {
            $rendus++;
          } else {
            $rates++;
          }
        }
      }
      /* Même forme que les tables : le récapitulatif compte les « ko » pour
         décider si la restauration est incomplète, et un fichier de preuve non
         restauré doit s'y voir autant qu'une ligne refusée. */
      $report[] = array(
        'label'   => 'fichiers de preuve',
        'table'   => '(' . $rendus . ' restauré(s), ' . $deja . ' déjà présent(s))',
        'ok'      => $rendus,
        'ko'      => $rates,
        'error'   => $rates > 0 ? 'copie impossible vers les téléversements' : '',
        'dropped' => array(),
      );
    }

    $dir = dirname( $manifest_path );
    $map = $this->get_plugin_table_map();
    $allowed = array();
    foreach ( $map as $label => $table_name ) {
      $allowed[ $label ] = $table_name;
    }

    foreach ( $manifest['tables'] as $table_entry ) {
      if ( empty( $table_entry['label'] ) || empty( $table_entry['file'] ) ) {
        continue;
      }
      $label = (string) $table_entry['label'];
      if ( empty( $allowed[ $label ] ) ) {
        continue;
      }
      $table = $allowed[ $label ];
      $json_path = trailingslashit( $dir ) . basename( (string) $table_entry['file'] );
      if ( ! file_exists( $json_path ) ) {
        continue;
      }
      $payload = json_decode( (string) file_get_contents( $json_path ), true );
      if ( ! is_array( $payload ) || ! isset( $payload['rows'] ) || ! is_array( $payload['rows'] ) ) {
        continue;
      }
      /* ACDC 3.25.179 — Le résultat de l'insertion n'était JAMAIS vérifié. La table
         était vidée, ses lignes échouaient une à une en silence, et la restauration
         s'annonçait réussie sur une table restée vide. C'est exactement ce qui est
         arrivé aux apprenants lors de la récupération du 9 août : leurs données
         étaient bien dans l'archive, et personne n'a su qu'elles n'étaient pas
         reparties. Une restauration qui échoue à moitié doit le dire. */
      /* ACDC 3.25.180 — La restauration exigeait une correspondance EXACTE des
         colonnes. Une seule colonne présente dans l'archive et absente de la table —
         parce que le schéma a évolué entre la sauvegarde et la restauration — et
         MySQL refuse la ligne entière : « Unknown column ». La table venait pourtant
         d'être vidée. C'est ainsi que les 3 apprenants et les 9 rendez-vous du
         9 août ont disparu alors qu'ils figuraient bien dans l'archive.
         On restaure désormais l'intersection : les colonnes que la table connaît
         réellement, et l'on nomme celles qu'on a dû laisser de côté. Restaurer une
         ligne amputée d'un champ vaut infiniment mieux que ne pas la restaurer. */
      $live_columns = array();
      foreach ( (array) $wpdb->get_results( "SHOW COLUMNS FROM {$table}", ARRAY_A ) as $col ) {
        if ( ! empty( $col['Field'] ) ) {
          $live_columns[ $col['Field'] ] = true;
        }
      }
      $dropped = array();

      $wpdb->query( "DELETE FROM {$table}" );
      $ok = 0;
      $ko = 0;
      $first_error = '';
      foreach ( $payload['rows'] as $row ) {
        if ( ! is_array( $row ) || empty( $row ) ) {
          continue;
        }
        if ( ! empty( $live_columns ) ) {
          foreach ( array_keys( $row ) as $col_name ) {
            if ( ! isset( $live_columns[ $col_name ] ) ) {
              $dropped[ $col_name ] = true;
              unset( $row[ $col_name ] );
            }
          }
          if ( empty( $row ) ) {
            $ko++;
            continue;
          }
        }
        $inserted = $wpdb->insert( $table, $row );
        if ( false === $inserted ) {
          $ko++;
          if ( '' === $first_error ) {
            $first_error = (string) $wpdb->last_error;
          }
          continue;
        }
        $ok++;
      }
      $report[] = array(
        'label'   => $label,
        'table'   => $table,
        'ok'      => $ok,
        'ko'      => $ko,
        'error'   => $first_error,
        'dropped' => array_keys( $dropped ),
      );
      if ( ! empty( $dropped ) ) {
        $this->log_error( 'restore_backup', 'Colonnes absentes de la table, ignorées.', array(
          'table'    => $table,
          'colonnes' => array_keys( $dropped ),
        ) );
      }
      if ( $ko > 0 ) {
        $this->log_error( 'restore_backup', 'Lignes non restaurées.', array(
          'table'   => $table,
          'echecs'  => $ko,
          'reussis' => $ok,
          'erreur'  => $first_error,
        ) );
      }
    }

    $options_path = trailingslashit( $dir ) . 'options.json';
    if ( file_exists( $options_path ) ) {
      $options = json_decode( (string) file_get_contents( $options_path ), true );
      if ( is_array( $options ) ) {
        $this->restore_backup_options_snapshot( $options );
      }
    }

    /* ACDC 3.25.179 — Le compte rendu remonte jusqu'à l'écran : une table vidée puis
       non repeuplée ne doit plus passer pour une réussite. */
    $failed = array();
    $adapted = array();
    foreach ( $report as $line ) {
      if ( $line['ko'] > 0 ) {
        $failed[] = $line['label'] . ' (' . $line['ko'] . ' ligne(s) refusée(s))';
      }
      if ( ! empty( $line['dropped'] ) ) {
        $adapted[] = $line['label'] . ' : ' . implode( ', ', $line['dropped'] );
      }
    }
    $status = empty( $failed ) ? 'success' : 'partial';
    $this->log_action_event( 'restore_backup', 'settings', 0, $status, array(
      'manifest' => basename( dirname( $manifest_path ) ) . '/manifest.json',
      'rapport'  => $report,
    ) );
    return array(
      'success'  => true,
      'partial'  => ! empty( $failed ),
      'failed'   => $failed,
      'adapted'  => $adapted,
      'report'   => $report,
      'manifest' => basename( dirname( $manifest_path ) ) . '/manifest.json',
    );
  }

  private function backup_data_snapshot( $label, $extra_manifest = array() ) {
    global $wpdb;

    $dir = $this->get_backup_run_directory( $label );
    if ( '' === $dir ) {
      $this->log_error( 'backup_snapshot', 'Répertoire de sauvegarde indisponible.', array( 'label' => $label ) );
      return array( 'success' => false, 'manifest' => '' );
    }

    $manifest = array(
      'created_at' => current_time( 'mysql' ),
      'nom' => $this->acdc_nom_sauvegarde(),
      'label' => (string) $label,
      'plugin_version' => ACDC_OF_SAAS_VERSION,
      'schema_version' => get_option( 'acdc_of_db_version', '3.0.0' ),
      'tables' => array(),
      'options_file' => 'options.json',
      'extra' => is_array( $extra_manifest ) ? $extra_manifest : array(),
    );

    foreach ( $this->get_plugin_table_map() as $table_label => $table ) {
      if ( empty( $table ) ) {
        continue;
      }
      $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
      if ( $exists !== $table ) {
        continue;
      }
      $rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
      $columns = $wpdb->get_results( "SHOW COLUMNS FROM {$table}", ARRAY_A );
      $data = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A );
      $table_file = trailingslashit( $dir ) . sanitize_file_name( $table_label ) . '.json';
      file_put_contents( $table_file, wp_json_encode( array(
        'table' => $table,
        'label' => $table_label,
        'columns' => $columns,
        'rows' => $data,
      ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
      $manifest['tables'][] = array(
        'label' => $table_label,
        'table' => $table,
        'rows' => $rows,
        'file' => basename( $table_file ),
      );
    }

    /* ── LES FICHIERS DE PREUVE ─────────────────────────────────────────
       ACDC 3.25.293 — La base ne contient que l'ADRESSE d'une signature
       manuscrite : l'image, elle, est un fichier dans les téléversements. Une
       restauration rendait donc des émargements dont la signature avait disparu.
       Idem pour les contrats, les pièces d'identité et les propositions.
       On emporte les dossiers du plugin — sauf celui des sauvegardes, qui se
       contiendrait lui-même. Le total est borné : une archive qui remplit le
       disque du serveur ne protège plus rien, elle met en panne. Ce qui est
       laissé de côté est NOMMÉ dans le manifeste, jamais passé sous silence. */
    $manifest['fichiers'] = array( 'dossiers' => array(), 'octets' => 0, 'ignores' => array() );
    $uploads_dir = wp_get_upload_dir();
    if ( ! empty( $uploads_dir['basedir'] ) && is_dir( $uploads_dir['basedir'] ) ) {
      $budget = (int) apply_filters( 'acdc_of_backup_files_budget', 512 * 1024 * 1024 );
      $cumul  = 0;
      $racine = trailingslashit( $uploads_dir['basedir'] );
      $cible  = trailingslashit( $dir ) . 'fichiers/';
      foreach ( (array) glob( $racine . 'acdc*', GLOB_ONLYDIR ) as $source ) {
        $nom_dossier = basename( (string) $source );
        if ( 'acdc-backups' === $nom_dossier ) {
          continue; /* le dossier des sauvegardes ne se sauvegarde pas lui-même */
        }
        $pris  = 0;
        $poids = 0;
        $trop_gros = false;
        foreach ( new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
          RecursiveIteratorIterator::SELF_FIRST
        ) as $fichier ) {
          if ( $fichier->isDir() ) {
            continue;
          }
          $taille = (int) $fichier->getSize();
          if ( $cumul + $taille > $budget ) {
            $trop_gros = true;
            break;
          }
          $relatif_f   = ltrim( str_replace( $racine, '', $fichier->getPathname() ), '/\\' );
          $destination = $cible . $relatif_f;
          wp_mkdir_p( dirname( $destination ) );
          if ( @copy( $fichier->getPathname(), $destination ) ) {
            $pris++;
            $poids += $taille;
            $cumul += $taille;
          } else {
            $manifest['fichiers']['ignores'][] = array( 'dossier' => $nom_dossier, 'raison' => 'copie impossible : ' . $relatif_f );
          }
        }
        if ( $pris > 0 ) {
          $manifest['fichiers']['dossiers'][] = array( 'dossier' => $nom_dossier, 'fichiers' => $pris, 'octets' => $poids );
        }
        if ( $trop_gros ) {
          $manifest['fichiers']['ignores'][] = array( 'dossier' => $nom_dossier, 'raison' => 'budget d’archive atteint' );
          break;
        }
      }
      $manifest['fichiers']['octets'] = $cumul;
    }

    /* ── CE QUI N'A PAS ÉTÉ PRIS EST DIT ────────────────────────────────
       Le défaut d'origine n'était pas d'oublier des tables : c'était de ne pas
       le dire. Une sauvegarde qui s'annonce réussie en ayant laissé des données
       derrière elle est plus dangereuse qu'une sauvegarde ratée. */
    $emportees = array();
    foreach ( $manifest['tables'] as $entree ) {
      if ( ! empty( $entree['table'] ) ) {
        $emportees[ (string) $entree['table'] ] = true;
      }
    }
    $manifest['tables_absentes'] = array();
    $motif_tables = $wpdb->esc_like( $wpdb->prefix . 'acdc_' ) . '%';
    foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $motif_tables ) ) as $table_base ) {
      if ( ! isset( $emportees[ (string) $table_base ] ) ) {
        $manifest['tables_absentes'][] = (string) $table_base;
      }
    }
    $manifest['complete'] = empty( $manifest['tables_absentes'] ) && empty( $manifest['fichiers']['ignores'] );

    $options_path = trailingslashit( $dir ) . 'options.json';
    file_put_contents( $options_path, wp_json_encode( $this->get_backup_options_snapshot(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );

    $manifest_path = trailingslashit( $dir ) . 'manifest.json';
    file_put_contents( $manifest_path, wp_json_encode( $manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
    $relative = basename( $dir ) . '/manifest.json';
    $archive_relative = $this->package_backup_snapshot( $dir, $label );
    if ( $archive_relative ) {
      $manifest['archive'] = basename( $archive_relative );
      file_put_contents( $manifest_path, wp_json_encode( $manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
    }
    $this->prune_backup_directories();

    $this->insert_system_log( array(
      'log_level' => 'info',
      'event_type' => 'backup_snapshot',
      'action_key' => sanitize_key( (string) $label ),
      'object_type' => 'system',
      'object_id' => 0,
      'user_id' => get_current_user_id(),
      'result_status' => 'success',
      'message' => 'Sauvegarde versionnée créée.',
      'context_json' => array( 'manifest' => $relative, 'label' => $label ),
      'source' => 'backup',
    ) );

    return array( 'success' => true, 'manifest' => $relative, 'directory' => basename( $dir ), 'archive' => $archive_relative );
  }

  private function get_backup_directory() {
    return $this->get_backup_base_directory();
  }

  private function backup_data_before_update( $from_version, $to_version ) {
    $label = 'update-' . sanitize_key( (string) $from_version ) . '-to-' . sanitize_key( (string) $to_version );

    /* ACDC 3.25.184 — Un instantané par couple de versions, pas un par tentative.
       Le 9 août, trente instantanés « update-325170-to-325176 » ont été créés en
       47 minutes par une migration qui se rejouait : chacun repoussait d'un cran
       les sauvegardes plus anciennes hors de la rétention, jusqu'à les effacer
       toutes. Le second dump de la même transition n'apporte rien — l'état de la
       base au moment du premier est précisément celui qu'il faut pouvoir
       retrouver. */
    if ( $this->acdc_update_snapshot_exists( $label ) ) {
      return true;
    }

    $result = $this->backup_data_snapshot( $label, array(
      'from_version' => (string) $from_version,
      'to_version' => (string) $to_version,
      'backup_type' => 'before_update',
    ) );

    if ( empty( $result['success'] ) ) {
      return false;
    }

    update_option( 'acdc_of_last_backup_file', $result['manifest'], false );
    update_option( 'acdc_of_last_backup_at', current_time( 'mysql' ), false );
    return true;
  }

  private function acdc_normalize_notice_type( $type ) {
    $type = sanitize_key( (string) $type );
    return in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ? $type : 'info';
  }

  private function acdc_append_notice_args( $args, $message = '', $type = 'info' ) {
    $args = is_array( $args ) ? $args : array();
    if ( '' !== (string) $message ) {
      $args['notice'] = rawurlencode( (string) $message );
      $args['notice_type'] = $this->acdc_normalize_notice_type( $type );
    }
    if ( empty( $args['_acdc_rt'] ) ) {
      $args['_acdc_rt'] = time();
    }
    return $args;
  }

  private function admin_page_url( $page, $args = array() ) {
    $args = is_array( $args ) ? $args : array();
    $args['page'] = sanitize_key( (string) $page );
    return add_query_arg( $args, admin_url( 'admin.php' ) );
  }

  private function redirect_to_admin_page( $page, $message = '', $type = 'success', $args = array() ) {
    if ( function_exists( 'acdc_of_saas_purge_all_caches' ) ) {
      acdc_of_saas_purge_all_caches();
    }
    nocache_headers();
    wp_safe_redirect( $this->admin_page_url( $page, $this->acdc_append_notice_args( $args, $message, $type ) ) );
    exit;
  }

  private function get_page_context_classes( $tab = 'dashboard', $action = 'list' ) {
    $tab  = sanitize_html_class( $tab ? $tab : 'dashboard' );
    $action = sanitize_html_class( $action ? $action : 'list' );

    return trim( 'acdc-page acdc-page-' . $tab . ' acdc-page-action-' . $action );
  }  private function ensure_storage_ready() {
    $this->install_or_update();
  }  private function redirect_with_db_result( $result, $success_message, $error_context, $tab, $is_admin_page = false, $admin_page = '', $extra = array(), $add_new = false ) {
    global $wpdb;

    if ( function_exists( 'acdc_of_saas_purge_all_caches' ) ) {
      acdc_of_saas_purge_all_caches();
    }

    if ( false === $result ) {
      if ( ! empty( $wpdb->last_error ) ) {
        error_log( '[ACDC SAAS OF][DB] ' . $wpdb->last_error );
      }
      $message = $error_context . ' Une erreur technique est survenue.';
      if ( $is_admin_page && $admin_page ) {
        $this->redirect_to_admin_page( $admin_page, $message, 'error' );
      }
      if ( empty( $extra['_acdc_rt'] ) ) {
        $extra['_acdc_rt'] = time();
      }
      $this->redirect_to_portal( $tab, $message, 'error', $extra );
    }

    if ( $is_admin_page && $admin_page ) {
      $query = array();
      if ( $add_new ) {
        $query['action'] = 'new';
      }
      $this->redirect_to_admin_page( $admin_page, $success_message, 'success', $query );
    }

    $redirect_extra = $add_new ? array_merge( $extra, array( 'action' => 'new' ) ) : $extra;
    if ( empty( $redirect_extra['_acdc_rt'] ) ) {
      $redirect_extra['_acdc_rt'] = time();
    }
    $this->redirect_to_portal( $tab, $success_message, 'success', $redirect_extra );
  }private function portal_page_url( $args = array() ) {
  $page_id = (int) get_option( 'acdc_of_extranet_dashboard_page_id', 0 );
  if ( ! $page_id ) {
    $page_id = (int) get_option( 'acdc_of_portal_page_id', 0 );
  }
  $url = $page_id ? get_permalink( $page_id ) : home_url( '/' );
  return add_query_arg( $args, $url );
}  private function quality_compliance_page_url( $args = array() ) {
  $page_id = (int) get_option( 'acdc_of_quality_compliance_page_id', 0 );
  if ( ! $page_id ) {
    return $this->portal_page_url( array_merge( array( 'tab' => 'improvement_pilotage' ), $args ) );
  }
  $url = get_permalink( $page_id );
  return add_query_arg( $args, $url );
}  private function redirect_to_portal( $tab = 'dashboard', $message = '', $type = 'success', $extra = array() ) {
    if ( function_exists( 'acdc_of_saas_purge_all_caches' ) ) {
      acdc_of_saas_purge_all_caches();
    }

    $args = array_merge( array( 'tab' => $tab ), $extra );
    if ( empty( $args['_acdc_rt'] ) ) {
      $args['_acdc_rt'] = time();
    }
    $args = $this->acdc_append_notice_args( $args, $message, $type );
    nocache_headers();
    wp_safe_redirect( $this->portal_page_url( $args ) );
    exit;
  }

  private function acdc_get_post_return_after_save( $default = 'view', $allowed = array() ) {
    if ( empty( $allowed ) ) {
      $allowed = array( 'view', 'edit', 'list', 'archived', 'new' );
    }

    $requested = isset( $_POST['return_after_save'] ) ? sanitize_key( wp_unslash( $_POST['return_after_save'] ) ) : '';
    if ( $requested && in_array( $requested, $allowed, true ) ) {
      return $requested;
    }

    return $default;
  }

  private function acdc_get_post_return_tab( $default = '' ) {
    $requested = isset( $_POST['return_tab'] ) ? sanitize_key( wp_unslash( $_POST['return_tab'] ) ) : '';
    return $requested ? $requested : $default;
  }  private function require_front_manager( $tab = 'dashboard' ) {
    if ( ! is_user_logged_in() || ! $this->is_admin_manager() ) {
      $this->redirect_to_login( 'Accès réservé aux administrateurs.', 'error' );
    }

    if ( ! wp_doing_ajax() && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
      check_admin_referer( 'acdc_front_secure_action' );
    }
  }  private function get_nav_icon_svg( $icon ) {
    $svg = '';
    switch ( $icon ) {
      case 'dashboard':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13.5 12 5l9 8.5"/><path d="M6.5 10.5V20h11V10.5"/><path d="M10 20v-5h4v5"/></svg>';
        break;
      case 'crm':
      case 'groups':
      case 'users':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="3"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 4.13a4 4 0 0 1 0 7.75"/></svg>';
        break;
      case 'calendar':
      case 'sessions':
      case 'deadline':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>';
        break;
      case 'prospects':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="8" r="4"/><path d="M4 20a6 6 0 0 1 12 0"/><path d="m17 17 4 4"/><circle cx="17" cy="17" r="3"/></svg>';
        break;
      case 'bell':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9a6 6 0 1 1 12 0c0 6 2 7 2 7H4s2-1 2-7"/><path d="M10 20a2 2 0 0 0 4 0"/></svg>';
        break;
      case 'filter':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5h18l-7 8v5l-4 2v-7L3 5Z"/></svg>';
        break;
      case 'more-horizontal':
        $svg = '<svg viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>';
        break;
      case 'eye':
      case 'view':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="3"/></svg>';
        break;
      case 'edit':
      case 'pencil':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';
        break;
      case 'copy':
      case 'duplicate':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="8" width="11" height="11" rx="2"/><path d="M5 16H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
        break;
      case 'archive':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8"/><path d="M10 12h4"/></svg>';
        break;
      /* ACDC 3.20.104 — 4 nouvelles icônes d'action introduites pour les pages
         enquêtes/quiz/évaluations/documents : créer-session, sessions, résultats, ouvrir.
         Style strictement aligné sur les autres : viewBox 24x24, stroke 1.9, lignes rondes. */
      case 'add-circle':
      case 'create-session':
      case 'add-session':
      case 'new-session':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v8"/><path d="M8 12h8"/></svg>';
        break;
      case 'clock-list':
      case 'sessions-list':
      case 'sessions-clock':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h12"/><path d="M3 12h8"/><path d="M3 18h8"/><circle cx="17" cy="16" r="4"/><path d="M17 14v2l1.5 1"/></svg>';
        break;
      case 'bar-chart':
      case 'results':
      case 'stats':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/></svg>';
        break;
      case 'external-link':
      case 'open':
      case 'open-external':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h6v6"/><path d="M20 4 10 14"/><path d="M19 13v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h6"/></svg>';
        break;
      case 'trash':
      case 'delete':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>';
        break;
      case 'directories':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>';
        break;
      case 'learners':
      case 'trainers':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
        break;
      case 'companies':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-3v17"/><path d="M19 21V11l-7-2"/><path d="M9 9h.01M9 13h.01M9 17h.01M15 13h.01M15 17h.01"/></svg>';
        break;
      case 'funders':
      case 'billing':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M14.5 9.5c0-1.1-1.12-2-2.5-2s-2.5.9-2.5 2 1.12 2 2.5 2 2.5.9 2.5 2-1.12 2-2.5 2-2.5-.9-2.5-2"/><path d="M12 6v12"/></svg>';
        break;
      case 'formations':
      case 'program':
      case 'catalog':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 17A2.5 2.5 0 0 0 4 19.5V5.5A2.5 2.5 0 0 1 6.5 3H20v14"/></svg>';
        break;
      case 'evaluations':
        /* ACDC 3.21.04.1-hotfix3 — Conserve l'icône clipboard+checkmark
           pour le label parent du groupe "Quiz / Test / Évaluation". */
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 12h5M8 16h3"/><path d="m16.5 15 1 1 2-2"/></svg>';
        break;
      case 'quiz':
        /* ACDC 3.21.04.1-hotfix3 — SVG dédié : bulle de dialogue avec point d'interrogation. */
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z"/><path d="M9.5 9a2.5 2.5 0 0 1 4.9.5c0 1.5-2.4 2-2.4 3.5"/><circle cx="12" cy="16" r="0.7" fill="currentColor"/></svg>';
        break;
      case 'need':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.1 9a3 3 0 1 1 5.8 1c0 2-3 2-3 4"/><path d="M12 17h.01"/></svg>';
        break;
      case 'positioning':
        /* ACDC 3.21.04.1-hotfix3 — SVG dédié : presse-papiers avec crayon. */
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 2h6a1 1 0 0 1 1 1v2H8V3a1 1 0 0 1 1-1z"/><path d="M16 4h2a1 1 0 0 1 1 1v6"/><path d="M5 5h3"/><path d="M5 5a1 1 0 0 0-1 1v14a2 2 0 0 0 2 2h7"/><path d="M8 10h6"/><path d="M8 14h4"/><path d="m17.5 14.5 3 3-4 4h-3v-3l4-4z"/><path d="m16.5 15.5 3 3"/></svg>';
        break;
      case 'evaluation_acquired':
        /* ACDC 3.21.04.1-hotfix3 — Nouveau SVG : certificat avec items cochés et médaille. */
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="14" height="17" rx="1.5"/><path d="m6 8 1.5 1.5L10 7"/><path d="m6 13 1.5 1.5L10 12"/><path d="M12 8.5h2"/><path d="M12 13.5h2"/><circle cx="17" cy="18" r="3.5"/><path d="m15.5 19.5.6 2.5L17 21l.9 1 .6-2.5"/></svg>';
        break;
      case 'results':
      case 'evaluation_result':
      case 'statistics':
      case 'report':
      case 'bpf':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20v-11"/></svg>';
        break;
      case 'survey':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="m12 2 2.2 4.46L19 7.27l-3.5 3.41.83 4.82L12 13.27 7.67 15.5l.83-4.82L5 7.27l4.8-.81z"/></svg>';
        break;
      case 'enrollment':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h7a4 4 0 0 1 0 8H4"/><path d="M20 17h-7a4 4 0 0 1 0-8h7"/><path d="M8 12h8"/></svg>';
        break;
      case 'contract':
      case 'quote':
      case 'invoice':
      case 'documents':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H7a2 2 0 0 0-2 2v16l3-2 3 2 3-2 3 2V8z"/><path d="M14 2v6h6"/></svg>';
        break;
      case 'attendance':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h6M9 12h2M9 16h2"/><path d="m14.5 15 1.5 1.5 3-3"/></svg>';
        break;
      case 'convocation':
      case 'emails':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>';
        break;
      case 'certificate':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="m8.5 14.5-1 6L12 18l4.5 2.5-1-6"/></svg>';
        break;
      case 'phone':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.8 19.8 0 0 1 3.08 5.18 2 2 0 0 1 5.05 3h3a2 2 0 0 1 2 1.72l.35 2.47a2 2 0 0 1-.57 1.71L8.1 10.63a16 16 0 0 0 5.27 5.27l1.73-1.73a2 2 0 0 1 1.71-.57l2.47.35A2 2 0 0 1 22 16.92z"/></svg>';
        break;
      case 'clipboard':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3h6"/><path d="M9 3a2 2 0 0 0-2 2v1H6a2 2 0 0 0-2 2v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V8a2 2 0 0 0-2-2h-1V5a2 2 0 0 0-2-2"/><path d="M9 12h6"/><path d="M9 16h4"/><path d="M8 8.5h.01" stroke-width="2.4"/></svg>';
        break;
      case 'create':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>';
        break;
      case 'pending':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3.2-6.9"/><path d="M21 3v6h-6"/></svg>';
        break;
      case 'validated':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="m20 6-11 11-5-5"/></svg>';
        break;
      case 'settings':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 .6 1.65 1.65 0 0 0-.33 1V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-.33-1 1.65 1.65 0 0 0-1-.6 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-.6-1 1.65 1.65 0 0 0-1-.33H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1-.33 1.65 1.65 0 0 0 .6-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-.6 1.65 1.65 0 0 0 .33-1V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 .33 1 1.65 1.65 0 0 0 1 .6 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c0 .38.13.74.37 1 .26.28.62.44 1 .44H21a2 2 0 1 1 0 4h-.09c-.38 0-.74.16-1 .44-.24.26-.37.62-.37 1.06z"/></svg>';
        break;
      case 'tools':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.66 5.66L3 18v3h3l6.04-6.04a4 4 0 0 0 5.66-5.66l-2.12 2.12-3.54-3.54z"/></svg>';
        break;
      case 'alert':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16h.01"/></svg>';
        break;
      case 'improvement':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17 9 11l4 4 8-8"/><path d="M14 7h7v7"/></svg>';
        break;
      case 'link':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.07 0l2.83-2.83a5 5 0 0 0-7.07-7.07L10 5"/><path d="M14 11a5 5 0 0 0-7.07 0L4.1 13.83a5 5 0 0 0 7.07 7.07L14 19"/></svg>';
        break;
      case 'services':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M7 7V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v2"/><path d="M3 12h18"/></svg>';
        break;
      case 'watch':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 5h13a4 4 0 0 1 4 4v10H7a4 4 0 0 1-4-4z"/><path d="M16 5v14"/><path d="M7 9h5"/></svg>';
        break;
      case 'company-profile':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M6 21V7.5L12 4l6 3.5V21"/><path d="M9 10h.01M9 14h.01M9 18h.01M15 10h.01M15 14h.01M15 18h.01"/></svg>';
        break;
      case 'user-profile':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0"/></svg>';
        break;
      case 'logout':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>';
        break;
      case 'chevron-right':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>';
        break;
      case 'chevron-down':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
        break;
      case 'view':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="3"/></svg>';
        break;
      case 'edit-pencil':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>';
        break;
      case 'trash-bin':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>';
        break;
      case 'close':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6"/><path d="M15 9l-6 6"/></svg>';
        break;
      case 'clipboard':
      case 'followup':
      case 'task':
        /* ACDC 3.20.59 — glyphe presse-papier pour Suivi commercial. */
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="12" height="17" rx="2"/><path d="M9 4V3a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1"/><path d="M9 10h6"/><path d="M9 14h6"/><path d="M9 18h4"/></svg>';
        break;
      // ACDC 3.21.29-hotfix4 — Icône renvoi d'e-mail (avion en papier).
      case 'send':
      case 'paper-plane':
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';
        break;
      default:
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>';
        break;
    }

    return $svg;
  }

  /**
   * ACDC 3.20.60 — Config pour AcdcActionHub (côté JS).
   *
   * Renvoie la configuration utilisée par le moteur JavaScript AcdcActionHub
   * pour décider quel SVG afficher selon le type d'action détecté.
   *
   * Structure :
   * - 'icons' : tableau type → nom-de-glyphe (lecture des options back office,
   *            avec fallback sur valeur par défaut si l'option n'existe pas).
   * - 'glyphs' : bibliothèque nom-de-glyphe → SVG-string (catalogue complet).
   *
   * Le JS lit d'abord ACDC_ACTION_HUB_CONFIG.icons[type] pour connaître le
   * glyphe choisi par l'utilisateur, puis va chercher le SVG dans
   * ACDC_ACTION_HUB_CONFIG.glyphs[nom-de-glyphe].
   *
   * Cette indirection permet à l'utilisateur de remplacer (par exemple)
   * l'icône Voir par un crayon ou par un calendrier sans toucher au code.
   */
  private function get_action_hub_config() {
    $branding = method_exists( $this, 'get_branding_options' ) ? $this->get_branding_options() : array();

    /* Mapping type interne du moteur JS → option back office. */
    $type_option_map = array(
      'view'     => 'action_icon_view',
      'edit'     => 'action_icon_edit',
      'delete'   => 'action_icon_delete',
      'more'     => 'action_icon_more',
      'followup' => 'action_icon_followup',
      'archive'  => 'action_icon_archive',
      'copy'     => 'action_icon_duplicate',
    );

    /* Valeurs par défaut si l'option est manquante. Identiques aux defaults
     * du back office pour ne pas créer de divergence. */
    $type_defaults = array(
      'view'     => 'eye',
      'edit'     => 'edit-pencil',
      'delete'   => 'trash-bin',
      'more'     => 'more-horizontal',
      'followup' => 'clipboard',
      'archive'  => 'archive',
      'copy'     => 'copy',
    );

    $icons = array();
    foreach ( $type_option_map as $type => $option_key ) {
      $value = isset( $branding[ $option_key ] ) ? sanitize_key( $branding[ $option_key ] ) : '';
      $icons[ $type ] = $value ? $value : $type_defaults[ $type ];
    }

    /* Catalogue de glyphes : tous les noms exposés par le back office plus
     * leurs aliases connus de get_nav_icon_svg(). On tire systématiquement
     * sur la fonction get_nav_icon_svg() pour rester cohérent avec le rendu PHP. */
    $glyph_names = array(
      'more-horizontal', 'eye', 'view', 'edit', 'edit-pencil', 'pencil',
      'trash', 'trash-bin', 'delete',
      'copy', 'duplicate', 'archive',
      'clipboard', 'followup', 'task',
      'document', 'folder', 'calendar', 'chart', 'settings',
      'filter', 'search', 'send', 'signature', 'warning', 'quality',
      'dashboard', 'user', 'users', 'school', 'building', 'finance',
      'book', 'download', 'upload', 'plus', 'check', 'close',
      'mail', 'phone',
    );

    $glyphs = array();
    foreach ( $glyph_names as $name ) {
      $glyphs[ $name ] = $this->get_nav_icon_svg( $name );
    }

    return array(
      'icons'  => $icons,
      'glyphs' => $glyphs,
    );
  }

  private function admin_tab_url( $tab, $extra = array() ) {
    $args = array_merge( array( 'page' => 'acdc-of-dashboard', 'tab' => $tab ), $extra );
    return admin_url( 'admin.php?' . http_build_query( $args ) );
  }  private function get_convocation_params_defaults() {
    return array(
      'training_location_presentiel' => '',
      'training_recommendations' => "Ces recommandations s'appliquent à tous les formats de formation (présentiel, distanciel ou mixte) : Prévoir une arrivée 10 minutes avant le début de la session. Apporter de quoi prendre des notes (ordinateur, tablette ou carnet). Vérifier la stabilité de la connexion Internet en cas de formation à distance. Se munir d'une pièce d'identité pour l'émargement ou l'accès sécurisé aux locaux. En cas de besoin spécifique (handicap, matériel, accessibilité), en informer le référent handicap avant le démarrage de la formation.",
      'training_access_means' => "Les informations pratiques (adresse) sont précisées dans la convocation. Les formations se déroulent dans des locaux accessibles en voiture et généralement desservis par les transports publics. Un parking ou un stationnement à proximité est indiqué lorsque cela est possible.",
      'pmr_access' => '1',
      'pmr_access_details' => "En cas de participant à mobilité réduite, l'organisme de formation s'engage à proposer une salle adaptée respectant les normes d'accessibilité. L'information sera communiquée individuellement lors de la convocation.",
      'training_contact_person' => '',
      'training_contact_details' => '',
      'attachment_url' => '',
      'preview_label' => 'Convocation de début de formation',
      'additional_sections' => array(),
    );
  }  private function get_convocation_params_options() {
    $defaults = $this->get_convocation_params_defaults();
    $saved = get_option( 'acdc_of_convocation_params', array() );
    if ( ! is_array( $saved ) ) {
      $saved = array();
    }
    $options = wp_parse_args( $saved, $defaults );
    if ( ! isset( $options['additional_sections'] ) || ! is_array( $options['additional_sections'] ) ) {
      $options['additional_sections'] = $defaults['additional_sections'];
    }
    return $options;
  }  private function get_attestation_params_defaults() {
    return array(
      'additional_sections' => array(
        array( 'title' => '', 'content' => '' ),
        array( 'title' => '', 'content' => '' ),
      ),
    );
  }  private function get_attestation_params_options() {
    $defaults = $this->get_attestation_params_defaults();
    $saved = get_option( 'acdc_of_attestation_params', array() );
    $saved = is_array( $saved ) ? $saved : array();
    $additional = isset( $saved['additional_sections'] ) && is_array( $saved['additional_sections'] ) ? $saved['additional_sections'] : $defaults['additional_sections'];
    return array(
      'additional_sections' => $additional,
    );
  }  private function get_counts() {
    global $wpdb;

    $prospects_count = 0;
    if ( isset( $this->prospect_table ) && ! empty( $this->prospect_table ) ) {
      $prospects_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->prospect_table}" );
    }

    return array(
      'companies'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->company_table}" ),
      'contacts'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->contact_table}" ),
      'documents'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->document_table}" ),
      'formations' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->formation_table}" ),
      'sessions'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->session_table}" ),
      'learners'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->learner_table}" ),
      'prospects'  => $prospects_count,
    );
  }  /**
   * ACDC 3.25.157 — LOT 2 : recherche et pagination des commanditaires.
   * Sans arguments, le comportement est inchangé (toutes les lignes, triées par nom) :
   * les appelants existants ne bougent pas.
   *
   * @param array $args search (string), limit (int), offset (int), count (bool).
   * @return array|int Lignes, ou total si count.
   */
  private function get_companies( $args = array() ) {
    global $wpdb;
    $search = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
    $where  = '';
    $params = array();
    if ( '' !== $search ) {
      $like   = '%' . $wpdb->esc_like( $search ) . '%';
      /* Le SIRET est recherché sur ses chiffres seuls : le répertoire contient les
         deux graphies (avec et sans espaces), une recherche littérale en manquerait. */
      $digits = preg_replace( '/\D/', '', $search );
      $where  = " WHERE ( name LIKE %s OR city LIKE %s OR postal_code LIKE %s OR email LIKE %s OR REPLACE(REPLACE(siret,' ',''),'.','') LIKE %s )";
      $params = array( $like, $like, $like, $like, '%' . $wpdb->esc_like( '' !== $digits ? $digits : $search ) . '%' );
    }
    if ( ! empty( $args['count'] ) ) {
      $sql = "SELECT COUNT(*) FROM {$this->company_table}{$where}";
      return (int) ( empty( $params ) ? $wpdb->get_var( $sql ) : $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) );
    }
    $sql = "SELECT * FROM {$this->company_table}{$where} ORDER BY name ASC";
    if ( isset( $args['limit'] ) && (int) $args['limit'] > 0 ) {
      $sql     .= ' LIMIT %d OFFSET %d';
      $params[] = (int) $args['limit'];
      $params[] = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
    }
    return empty( $params ) ? $wpdb->get_results( $sql ) : $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
  }  private function get_company( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->company_table} WHERE id = %d", $id ) );
  }

  private function acdc_normalize_company_matching_label( $value ) {
    $value = trim( wp_strip_all_tags( (string) $value ) );
    if ( '' === $value ) {
      return '';
    }

    $value = html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );
    $value = remove_accents( $value );
    $value = strtolower( $value );
    $value = preg_replace( '/\([^)]*\)/', ' ', $value );
    $value = preg_replace( '/(sarl|sas|sasu|eurl|ei|eirl|sa|snc|scop|scic|sasu|sci|sca|selarl|selas|micro[- ]entreprise|entreprise individuelle|auto[- ]entrepreneur)/u', ' ', $value );
    $value = preg_replace( '/[^a-z0-9]+/u', ' ', $value );
    $value = preg_replace( '/\s+/', ' ', $value );
    return trim( $value );
  }

  private function acdc_get_company_matching_labels( $raw_label ) {
    $raw_label = trim( wp_strip_all_tags( (string) $raw_label ) );
    if ( '' === $raw_label ) {
      return array();
    }

    $labels = array( $raw_label );

    if ( preg_match_all( '/\(([^)]*)\)/', $raw_label, $matches ) ) {
      foreach ( (array) $matches[1] as $inner ) {
        $inner = trim( (string) $inner );
        if ( '' !== $inner ) {
          $labels[] = $inner;
        }
      }
    }

    $outside_parentheses = trim( preg_replace( '/\([^)]*\)/', ' ', $raw_label ) );
    if ( '' !== $outside_parentheses ) {
      $labels[] = $outside_parentheses;
    }

    $parts = preg_split( '/\s*(?:\/|-|–|—)\s*/u', $raw_label );
    if ( is_array( $parts ) ) {
      foreach ( $parts as $part ) {
        $part = trim( (string) $part );
        if ( '' !== $part ) {
          $labels[] = $part;
        }
      }
    }

    $normalized_labels = array();
    foreach ( array_filter( array_unique( $labels ) ) as $label ) {
      $normalized = $this->acdc_normalize_company_matching_label( $label );
      if ( '' !== $normalized ) {
        $normalized_labels[] = $normalized;
      }
    }

    return array_values( array_unique( $normalized_labels ) );
  }

  private function acdc_find_company_id_from_prospect( $prospect, $companies = null ) {
    if ( ! $prospect ) {
      return 0;
    }

    if ( null === $companies ) {
      $companies = $this->get_companies();
    }

    $prospect_siret = isset( $prospect->siret ) ? preg_replace( '/\D+/', '', (string) $prospect->siret ) : '';
    $prospect_company_name = isset( $prospect->company_name ) ? trim( (string) $prospect->company_name ) : '';
    $prospect_labels = $this->acdc_get_company_matching_labels( $prospect_company_name );
    $prospect_email = isset( $prospect->email ) ? strtolower( trim( (string) $prospect->email ) ) : '';
    $prospect_phone = isset( $prospect->company_phone ) ? preg_replace( '/\D+/', '', (string) $prospect->company_phone ) : '';

    foreach ( (array) $companies as $company ) {
      $company_id = isset( $company->id ) ? (int) $company->id : 0;
      if ( ! $company_id ) {
        continue;
      }

      $company_siret = isset( $company->siret ) ? preg_replace( '/\D+/', '', (string) $company->siret ) : '';
      if ( $prospect_siret && $company_siret && $prospect_siret === $company_siret ) {
        return $company_id;
      }
    }

    $best_company_id = 0;
    $best_score = 0;

    foreach ( (array) $companies as $company ) {
      $company_id = isset( $company->id ) ? (int) $company->id : 0;
      if ( ! $company_id ) {
        continue;
      }

      $company_name = isset( $company->name ) ? trim( (string) $company->name ) : '';
      $company_legal_form = isset( $company->legal_form ) ? trim( (string) $company->legal_form ) : '';
      $company_email = isset( $company->email ) ? strtolower( trim( (string) $company->email ) ) : '';
      $company_phone = isset( $company->phone ) ? preg_replace( '/\D+/', '', (string) $company->phone ) : '';
      $candidate_labels = array();

      foreach ( array_filter(
        array(
          $company_name,
          $company_legal_form,
          trim( $company_name . ' ' . $company_legal_form ),
          trim( $company_legal_form . ' ' . $company_name ),
          trim( $company_name . ' (' . $company_legal_form . ')' ),
          trim( $company_legal_form . ' (' . $company_name . ')' ),
        )
      ) as $candidate ) {
        $candidate_labels = array_merge( $candidate_labels, $this->acdc_get_company_matching_labels( $candidate ) );
      }

      $candidate_labels = array_values( array_unique( array_filter( $candidate_labels ) ) );
      $score = 0;

      if ( $prospect_email && $company_email && $prospect_email === $company_email ) {
        $score += 30;
      }
      if ( $prospect_phone && $company_phone && $prospect_phone === $company_phone ) {
        $score += 20;
      }

      foreach ( $candidate_labels as $candidate_normalized ) {
        foreach ( $prospect_labels as $prospect_label_normalized ) {
          if ( '' === $candidate_normalized || '' === $prospect_label_normalized ) {
            continue;
          }

          if ( $candidate_normalized === $prospect_label_normalized ) {
            return $company_id;
          }

          if ( false !== strpos( $prospect_label_normalized, $candidate_normalized ) || false !== strpos( $candidate_normalized, $prospect_label_normalized ) ) {
            $score = max( $score, 25 );
          }

          $prospect_tokens  = array_values( array_filter( explode( ' ', $prospect_label_normalized ) ) );
          $candidate_tokens = array_values( array_filter( explode( ' ', $candidate_normalized ) ) );
          if ( ! empty( $prospect_tokens ) && ! empty( $candidate_tokens ) ) {
            $common_tokens = array_intersect( $prospect_tokens, $candidate_tokens );
            $common_count  = count( $common_tokens );
            if ( $common_count >= 2 ) {
              $score = max( $score, 10 + ( $common_count * 5 ) );
            }
          }
        }
      }

      if ( $score > $best_score ) {
        $best_score = $score;
        $best_company_id = $company_id;
      }
    }

    return $best_score >= 20 ? (int) $best_company_id : 0;
  }

  private function acdc_find_contact_id_from_prospect( $prospect, $company_id = 0 ) {
    if ( ! $prospect || ! $company_id ) {
      return 0;
    }

    foreach ( (array) $this->get_related_contacts( (int) $company_id ) as $contact ) {
      $contact_id = isset( $contact->id ) ? (int) $contact->id : 0;
      if ( ! $contact_id ) {
        continue;
      }

      $contact_email = isset( $contact->email ) ? strtolower( trim( (string) $contact->email ) ) : '';
      $signer_email = isset( $prospect->signer_email ) ? strtolower( trim( (string) $prospect->signer_email ) ) : '';
      $prospect_email = isset( $prospect->email ) ? strtolower( trim( (string) $prospect->email ) ) : '';
      $contact_first = isset( $contact->first_name ) ? trim( (string) $contact->first_name ) : '';
      $contact_last = isset( $contact->last_name ) ? trim( (string) $contact->last_name ) : '';
      $signer_first = isset( $prospect->signer_first_name ) ? trim( (string) $prospect->signer_first_name ) : '';
      $signer_last = isset( $prospect->signer_last_name ) ? trim( (string) $prospect->signer_last_name ) : '';
      $prospect_first = isset( $prospect->first_name ) ? trim( (string) $prospect->first_name ) : '';
      $prospect_last = isset( $prospect->last_name ) ? trim( (string) $prospect->last_name ) : '';

      if ( $signer_email && $contact_email && $signer_email === $contact_email ) {
        return $contact_id;
      }
      if ( $prospect_email && $contact_email && $prospect_email === $contact_email ) {
        return $contact_id;
      }
      if ( '' !== $signer_first && '' !== $signer_last && 0 === strcasecmp( $signer_first, $contact_first ) && 0 === strcasecmp( $signer_last, $contact_last ) ) {
        return $contact_id;
      }
      if ( '' !== $prospect_first && '' !== $prospect_last && 0 === strcasecmp( $prospect_first, $contact_first ) && 0 === strcasecmp( $prospect_last, $contact_last ) ) {
        return $contact_id;
      }
    }

    return 0;
  }

  /**
   * Le nom d'une formation, partout où on la choisit ou on la lit.
   *
   * ACDC 3.25.277 — Point d'entrée unique. Il en existait quatre, divergents,
   * et trois écrans qui n'écrivaient que l'intitulé nu : deux fiches du même
   * nom, l'une en présentiel l'autre en distanciel, s'y présentaient comme deux
   * lignes rigoureusement identiques. La règle est désormais dans
   * \ACDC\Support\FormationLabel, où elle se vérifie sans base de données.
   *
   * @param object|array|null $formation Ligne de acdc_of_formations.
   * @return string « CODE | Intitulé (Modalité) », segments vides omis.
   */
  public function acdc_formation_choice_label( $formation ) {
    return \ACDC\Support\FormationLabel::fromRow( $formation );
  }

  /**
   * La cellule « Formation » d'un tableau, modalité comprise.
   *
   * ACDC 3.25.277 — Les tableaux (séances, dossiers, résultats, extranets) ne
   * lisent pas la fiche formation : leur requête ne ramène qu'un
   * `formation_title` par jointure. Deux séances de la même formation, l'une en
   * présentiel l'autre en distanciel, s'y affichaient donc sur deux lignes
   * portant exactement le même nom. Les requêtes ramènent désormais aussi
   * `formation_modality`, et cette fonction compose la cellule.
   *
   * Elle rend le tiret cadratin quand il n'y a rien à écrire : une cellule vide
   * dans un tableau se lit comme une colonne cassée, pas comme une donnée
   * absente.
   *
   * @param object|array|null $row      Ligne de résultat portant formation_title.
   * @param string            $fallback Ce qu'on écrit quand la formation manque.
   * @return string
   */
  /**
   * Le format d'une séance : le sien, à défaut celui de sa formation.
   *
   * ACDC 3.25.277 — Une séance porte sa propre colonne `session_format`, très
   * souvent vide : personne ne la ressaisit quand la formation la déclare
   * déjà. La feuille d'émargement le savait et retombait sur la modalité de la
   * formation ; les tableaux de séances, eux, affichaient un tiret. La règle
   * était donc juste à un endroit et absente ailleurs — recopiée nulle part,
   * appelée nulle part.
   *
   * @param object|array|null $session Ligne portant session_format, et si
   *                                   possible formation_modality.
   * @return string
   */
  public function acdc_session_format_label( $session ) {
    $row = is_object( $session ) ? get_object_vars( $session ) : ( is_array( $session ) ? $session : array() );
    foreach ( array( 'session_format', 'formation_modality', 'modality' ) as $key ) {
      if ( isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) && '' !== trim( (string) $row[ $key ] ) ) {
        return \ACDC\Support\FormationLabel::modality( (string) $row[ $key ] );
      }
    }
    return '—';
  }

  public function acdc_formation_cell( $row, $fallback = '—' ) {
    $label = \ACDC\Support\FormationLabel::fromJoinedRow( $row );
    return '' !== $label ? $label : $fallback;
  }

  /**
   * ACDC hotfix63 — Label formation pour les menus déroulants.
   * Format : "1 Titre" pour la formation mère, "1.1 Titre" pour une variante.
   *
   * ACDC 3.25.277 — Le repère numérique est conservé (huit écrans l'affichent
   * depuis toujours, le retirer en passant serait une perte silencieuse) mais
   * la modalité vient désormais s'ajouter derrière, comme partout ailleurs.
   *
   * @param object $formation  Ligne de acdc_of_formations.
   * @return string
   */
  public function format_formation_option_label( $formation ) {
      $is_variant = ! empty( $formation->base_formation_id ) && (int) $formation->base_formation_id > 0;
      $code = $is_variant
          ? (int) $formation->base_formation_id . '.' . (int) $formation->variant_number
          : (string) (int) $formation->id;
      return \ACDC\Support\FormationLabel::prefixed(
          $code,
          isset( $formation->title ) ? (string) $formation->title : '',
          isset( $formation->modality ) ? (string) $formation->modality : ''
      );
  }

  private function get_formations( $args = array() ) {
    global $wpdb;
    $defaults = array(
      'archived'   => null,
      'search'     => '',
      'limit'      => 0,
      'thematique' => '',  // ACDC 3.21.14 — filtre par thématique
    );
    $args = wp_parse_args( $args, $defaults );
    $where = array();
    $values = array();

    if ( null !== $args['archived'] ) {
      $where[] = 'is_active = %d';
      $values[] = $args['archived'] ? 0 : 1;
    }

    if ( '' !== $args['search'] ) {
      $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
      $where[] = '(title LIKE %s OR code LIKE %s OR modality LIKE %s OR city LIKE %s)';
      array_push( $values, $like, $like, $like, $like );
    }

    // ACDC 3.21.14 — Filtre thématique
    if ( '' !== $args['thematique'] ) {
      $where[] = 'thematique = %s';
      $values[] = $args['thematique'];
    }

    $sql = "SELECT * FROM {$this->formation_table}";
    if ( ! empty( $where ) ) {
      $sql .= ' WHERE ' . implode( ' AND ', $where );
    }
    // Tri groupé : chaque variante apparaît immédiatement après sa formation mère,
    // les groupes étant ordonnés par ID croissant de la mère (1, 1.1, 2, 2.1, ...).
    // Le 3ᵉ critère (id ASC) garantit un ordre stable en cas d'égalité.
    $sql .= ' ORDER BY (CASE WHEN base_formation_id > 0 THEN base_formation_id ELSE id END) ASC, variant_number ASC, id ASC';
    if ( ! empty( $args['limit'] ) ) {
      $sql .= ' LIMIT ' . absint( $args['limit'] );
    }

    if ( ! empty( $values ) ) {
      return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
    }
    return $wpdb->get_results( $sql );
  }  private function get_formation( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->formation_table} WHERE id = %d", $id ) );
  }

  /**
   * ACDC 3.25.251 — UN NONCE AVAIT ÉTÉ RANGÉ DANS UNE COLONNE DE BASE.
   *
   * La colonne `program_file_url` de certaines formations contient une adresse
   * héritée de l'ancien plugin Manager :
   *
   *   /wp-admin/admin-ajax.php?action=acdc_fm_download_programme_pdf&…&acdc_pdf_nonce=…
   *
   * Un nonce WordPress est un jeton daté — vingt-quatre heures — et lié à
   * l'utilisateur qui l'a créé. Le ranger dans une donnée, c'est enregistrer une
   * clé qui ne rentre déjà plus dans la serrure le lendemain. Ces liens sont
   * morts pour tout le monde, et deux fois morts pour un destinataire d'e-mail
   * qui n'est même pas connecté : d'où le « Lien invalide » constaté par David
   * sur la convention comme dans « Documents → Programmes de formation ».
   *
   * Un seul écran le savait — le portail apprenant, qui écartait ces adresses
   * en dur. Tous les autres affichaient la colonne telle quelle. La
   * connaissance vit désormais ici, et nulle part ailleurs.
   */
  private function acdc_is_dead_manager_url( $url ) {
    $url = is_scalar( $url ) ? trim( (string) $url ) : '';
    if ( '' === $url ) {
      return false;
    }
    /* Le motif complet : une route d'administration ET une action du Manager.
       Les deux ensemble — une URL d'uploads contenant « admin-ajax » dans son
       nom de fichier resterait un fichier parfaitement valide. */
    $is_admin_route = ( false !== strpos( $url, 'admin-ajax.php' ) || false !== strpos( $url, 'admin-post.php' ) );
    return $is_admin_route && ( false !== strpos( $url, 'acdc_fm_' ) || false !== strpos( $url, 'acdc_pdf_nonce' ) );
  }

  /**
   * Le programme de cette formation sous forme de FICHIER, ou rien.
   *
   * C'est ce qu'il faut pour une pièce jointe ou pour un lien destiné à
   * quelqu'un qui n'a pas de compte : un fichier déposé dans les uploads, que
   * l'on peut joindre et que le destinataire peut ouvrir. La page programme du
   * portail, elle, exige une connexion.
   *
   * @return array{path:string,url:string} Chemin et adresse, vides si aucun fichier.
   */
  private function acdc_formation_programme_file( $formation ) {
    $none = array( 'path' => '', 'url' => '' );
    if ( empty( $formation ) || empty( $formation->program_file_url ) ) {
      return $none;
    }
    $url = trim( (string) $formation->program_file_url );
    if ( '' === $url || $this->acdc_is_dead_manager_url( $url ) ) {
      return $none;
    }

    $uploads = wp_get_upload_dir();
    if ( empty( $uploads['baseurl'] ) || empty( $uploads['basedir'] ) ) {
      return array( 'path' => '', 'url' => $url );
    }
    if ( 0 !== strpos( $url, (string) $uploads['baseurl'] ) ) {
      /* Adresse externe : utilisable en lien, pas en pièce jointe. */
      return array( 'path' => '', 'url' => $url );
    }
    $relative = ltrim( substr( $url, strlen( (string) $uploads['baseurl'] ) ), '/' );
    $path     = trailingslashit( (string) $uploads['basedir'] ) . $relative;
    if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
      /* L'adresse promet un fichier qui n'est pas là : ne rien affirmer. */
      return $none;
    }
    return array( 'path' => $path, 'url' => $url );
  }

  /**
   * L'adresse à donner pour consulter le programme d'une formation.
   *
   * Le fichier déposé fait foi. À défaut, la page programme du portail, qui
   * fabrique un lien neuf à chaque affichage — et qui, elle, demande une
   * connexion : c'est pourquoi les envois passent par
   * acdc_formation_programme_file(), jamais par ici.
   */
  private function acdc_formation_programme_url( $formation ) {
    $file = $this->acdc_formation_programme_file( $formation );
    if ( '' !== $file['url'] ) {
      return $file['url'];
    }
    if ( ! empty( $formation->id ) && method_exists( $this, 'get_programme_pdf_url' ) ) {
      return (string) $this->get_programme_pdf_url( (int) $formation->id );
    }
    return '';
  }private function get_pre_meetings( $search = '' ) {
    global $wpdb;
    $where = '';
    if ( '' !== trim( (string) $search ) ) {
      $like = '%' . $wpdb->esc_like( trim( (string) $search ) ) . '%';
      $where = $wpdb->prepare( "WHERE l.first_name LIKE %s OR l.usage_last_name LIKE %s OR l.email LIKE %s OR f.title LIKE %s OR t.first_name LIKE %s OR t.last_name LIKE %s", $like, $like, $like, $like, $like, $like );
    }
    $sql = "SELECT pm.*, l.first_name AS learner_first_name, l.usage_last_name AS learner_usage_last_name, l.email AS learner_email, f.title AS real_formation_title, t.first_name AS trainer_first_name, t.last_name AS trainer_last_name
      FROM {$this->pre_meeting_table} pm
      LEFT JOIN {$this->learner_table} l ON l.id = pm.learner_id
      LEFT JOIN {$this->formation_table} f ON f.id = pm.formation_id
      LEFT JOIN {$this->trainer_table} t ON t.id = pm.trainer_id
      {$where}
      ORDER BY pm.rdv_at DESC, pm.id DESC";
    return $wpdb->get_results( $sql );
  }  private function get_pre_meeting( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->pre_meeting_table} WHERE id = %d", $id ) );
  }  private function get_pre_meeting_default_record() {
    return (object) array(
      'id' => 0,
      'learner_id' => 0,
      'learner_label' => '',
      'formation_id' => 0,
      'formation_title' => '',
      'rdv_at' => '',
      'duration_label' => '',
      'trainer_id' => 0,
      'trainer_label' => '',
      'created_at' => '',
      'updated_at' => '',
    );
  }
  private function get_needs() {
    global $wpdb;
    $sql = "SELECT n.*,
            e.name AS company_name,
            CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) AS contact_name,
            p.company_name AS source_prospect_company_name,
            TRIM(CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, ''))) AS source_prospect_contact_name,
            p.id AS source_prospect_id_resolved,
            p.profile_type AS source_prospect_profile_type,
            TRIM(CONCAT(COALESCE(p.signer_first_name, ''), ' ', COALESCE(p.signer_last_name, ''))) AS source_prospect_signer_name
        FROM {$this->need_table} n
        LEFT JOIN {$this->company_table} e ON e.id = n.company_id
        LEFT JOIN {$this->contact_table} c ON c.id = n.contact_id
        LEFT JOIN {$this->prospect_table} p ON p.id = n.source_prospect_id
        ORDER BY n.created_at DESC, n.id DESC";
    return $wpdb->get_results( $sql );
  }  private function get_need( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->need_table} WHERE id = %d", $id ) );
  }  private function get_need_theme_options() {
    return array(
      '' => '— Choisir —',
      'Intelligence artificielle' => 'Intelligence artificielle',
      'Marketing digital et web' => 'Marketing digital et web',
      'No-code et automatisation' => 'No-code et automatisation',
      'WordPress' => 'WordPress',
      'Management' => 'Management',
      'Management en restauration' => 'Management en restauration',
      'Soft skills' => 'Soft skills',
      'Hygiène alimentaire' => 'Hygiène alimentaire',
      'Sur mesure' => 'Sur mesure',
      'Autre' => 'Autre',
    );
  }  private function get_need_collection_channel_options() {
    return array(
      '' => '—',
      'Téléphone' => 'Téléphone',
      'Visio' => 'Visio',
      'E-mail' => 'E-mail',
      'Formulaire web' => 'Formulaire web',
      'Présentiel' => 'Présentiel',
      'Réseau social' => 'Réseau social',
      'Recommandation' => 'Recommandation',
      'Autre' => 'Autre',
    );
  }  private function get_need_status_options() {
    return array(
      'Nouveau' => 'Nouveau',
      'À qualifier' => 'À qualifier',
      'En cours' => 'En cours',
      'À relancer' => 'À relancer',
      'Converti' => 'Converti',
      'Perdu' => 'Perdu',
      'Archivé' => 'Archivé',
    );
  }  private function get_need_level_options() {
    return array(
      '' => '—',
      'Débutant' => 'Débutant',
      'Intermédiaire' => 'Intermédiaire',
      'Avancé' => 'Avancé',
      'Mixte' => 'Mixte',
      'À définir' => 'À définir',
    );
  }  private function get_need_urgency_options() {
    return array(
      '' => '—',
      'Faible' => 'Faible',
      'Normale' => 'Normale',
      'Haute' => 'Haute',
      'Critique' => 'Critique',
    );
  }  private function get_need_format_options() {
    return array(
      '' => '—',
      'Présentiel' => 'Présentiel',
      'Distanciel' => 'Distanciel',
      'Classe virtuelle' => 'Classe virtuelle',
      'E-learning' => 'E-learning',
      'Hybride' => 'Hybride',
      'À définir' => 'À définir',
    );
  }  private function get_need_funding_options() {
    return array(
      '' => '—',
      'OPCO' => 'OPCO',
      'Autofinancement entreprise' => 'Autofinancement entreprise',
      'CPF' => 'CPF',
      'France Travail' => 'France Travail',
      'Autre' => 'Autre',
      'À définir' => 'À définir',
    );
  }  private function get_need_priority_options() {
    return array(
      'Basse' => 'Basse',
      'Normale' => 'Normale',
      'Haute' => 'Haute',
      'Critique' => 'Critique',
    );
  }  private function get_local_path_from_upload_url( $url ) {
    $url = trim( (string) $url );
    if ( '' === $url ) {
      return '';
    }
    $uploads = wp_get_upload_dir();
    if ( empty( $uploads['baseurl'] ) || empty( $uploads['basedir'] ) ) {
      return '';
    }
    if ( 0 === strpos( $url, $uploads['baseurl'] ) ) {
      /* ACDC 3.25.291 — LA LIGNE QUI CALCULE LE CHEMIN MANQUAIT.
         $relative se nettoyait lui-même sans avoir jamais été rempli : la
         fonction rendait donc le dossier des téléversements, jamais le fichier.
         Elle sert de dernier recours quand seule l'URL d'un document est
         connue — pour joindre une convocation à un e-mail, par exemple. Résultat
         invisible et fâcheux : le message partait sans sa pièce jointe. */
      $relative = substr( $url, strlen( (string) $uploads['baseurl'] ) );
      $relative = preg_replace( '#^[\\/]+#', '', (string) $relative );
      return trailingslashit( $uploads['basedir'] ) . $relative;
    }
    return '';
  }  private function get_training_convocation_document_info( $registration, $context = array() ) {
    $info = array(
      'url'   => '',
      'path'  => '',
      'size'  => '',
      'label' => 'Convocation de début de formation',
    );
    if ( ! $registration ) {
      return $info;
    }
    $url  = isset( $registration->convocation_document_url ) ? trim( (string) $registration->convocation_document_url ) : '';
    $path = isset( $registration->convocation_document_path ) ? trim( (string) $registration->convocation_document_path ) : '';
    if ( '' === $path && '' !== $url ) {
      $path = $this->get_local_path_from_upload_url( $url );
    }
    if ( '' === $url ) {
      global $wpdb;
      $learner_name = isset( $context['learner_name'] ) ? (string) $context['learner_name'] : ( ! empty( $registration->learner_label ) ? (string) $registration->learner_label : '' );
      $formation_title = isset( $context['formation_title'] ) ? (string) $context['formation_title'] : ( ! empty( $registration->formation_title ) ? (string) $registration->formation_title : '' );
      $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->document_table} WHERE (document_type LIKE %s OR title LIKE %s) ORDER BY id DESC LIMIT 25", '%Convocation%', '%Convocation%' ) );
      foreach ( (array) $rows as $row ) {
        $haystack = trim( (string) $row->title . ' ' . (string) $row->document_type );
        $match = false;
        if ( '' !== $learner_name && false !== stripos( $haystack, $learner_name ) ) {
          $match = true;
        }
        if ( ! $match && '' !== $formation_title && false !== stripos( $haystack, $formation_title ) ) {
          $match = true;
        }
        if ( ! $match ) {
          continue;
        }
        $url  = ! empty( $row->file_url ) ? (string) $row->file_url : '';
        $path = ! empty( $row->file_path ) ? (string) $row->file_path : '';
        break;
      }
    }
    $info['url'] = $url;
    $info['path'] = $path;
    if ( '' !== $path && file_exists( $path ) ) {
      $size = filesize( $path );
      if ( $size ) {
        if ( $size >= 1048576 ) {
          $info['size'] = number_format_i18n( $size / 1048576, 1 ) . ' MB';
        } elseif ( $size >= 1024 ) {
          $info['size'] = number_format_i18n( $size / 1024, 0 ) . ' KB';
        } else {
          $info['size'] = $size . ' octets';
        }
      }
    }
    return $info;
  }  private function get_training_convocation_context( $registration ) {
    $formation = ! empty( $registration->formation_id ) ? $this->get_formation( (int) $registration->formation_id ) : null;
    $learner = ! empty( $registration->learner_id ) ? $this->get_learner( (int) $registration->learner_id ) : null;
    $company = ! empty( $registration->company_id ) ? $this->get_company( (int) $registration->company_id ) : null;
    $sessions = array();
    foreach ( $this->get_sessions() as $session ) {
      if ( ! empty( $session->is_draft ) ) {
        continue;
      }
      if ( (int) $session->formation_id === (int) $registration->formation_id ) {
        $sessions[] = $session;
      }
    }
    usort( $sessions, static function( $a, $b ) {
      $a_ts = ! empty( $a->start_at ) ? strtotime( (string) $a->start_at ) : strtotime( (string) $a->start_date . ' 00:00:00' );
      $b_ts = ! empty( $b->start_at ) ? strtotime( (string) $b->start_at ) : strtotime( (string) $b->start_date . ' 00:00:00' );
      return $a_ts <=> $b_ts;
    } );
    $start_date = '';
    $end_date = '';
    if ( ! empty( $sessions ) ) {
      $first = $sessions[0];
      $last = $sessions[ count( $sessions ) - 1 ];
      $start_date = ! empty( $first->start_date ) ? (string) $first->start_date : ( ! empty( $first->start_at ) ? gmdate( 'Y-m-d', strtotime( (string) $first->start_at ) ) : '' );
      $end_date = ! empty( $last->end_date ) ? (string) $last->end_date : ( ! empty( $last->end_at ) ? gmdate( 'Y-m-d', strtotime( (string) $last->end_at ) ) : $start_date );
      if ( '' === $end_date ) {
        $end_date = $start_date;
      }
    }
    if ( '' === $start_date ) {
      $start_date = ! empty( $registration->created_at ) ? gmdate( 'Y-m-d', strtotime( (string) $registration->created_at ) ) : '';
      $end_date = $start_date;
    }
    /* ACDC 3.25.224 — LA LIGNE DU COMMANDITAIRE PORTAIT LE NOM DE SA
       SIGNATAIRE. Un dossier de trois apprenantes affichait quatre lignes dont
       deux au nom de Bérengère Valeriano : elle signe pour Skill Conseil ET
       suit la formation. La quatrième ligne n'est pas un doublon, c'est la
       ligne du commanditaire — mais elle empruntait l'identité d'une personne
       physique au lieu de porter la raison sociale, ce qui la rendait
       indiscernable d'une inscription.
       La règle de David est sans exception : dès qu'il y a une entreprise,
       c'est l'entreprise qui nomme. La personne vient après, jamais à la
       place. */
    $is_commanditaire = empty( $registration->learner_id ) && ! empty( $registration->company_id );

    $learner_name = '';
    if ( $learner ) {
      $learner_name = trim( $learner->first_name . ' ' . ( ! empty( $learner->usage_last_name ) ? $learner->usage_last_name : $learner->last_name ) );
    } elseif ( $is_commanditaire && $company && ! empty( $company->name ) ) {
      $learner_name = (string) $company->name;
      if ( ! empty( $registration->learner_label ) ) {
        $learner_name .= ' — à l’attention de ' . (string) $registration->learner_label;
      }
    } elseif ( ! empty( $registration->learner_label ) ) {
      $learner_name = (string) $registration->learner_label;
    } elseif ( ! empty( $registration->learners_label ) ) {
      $learner_name = (string) $registration->learners_label;
    } elseif ( ! empty( $registration->group_label ) ) {
      $learner_name = (string) $registration->group_label;
    } else {
      $learner_name = 'Apprenant';
    }
    $formation_title = $formation && ! empty( $formation->title ) ? (string) $formation->title : (string) $registration->formation_title;
    $format = $formation && ! empty( $formation->modality ) ? (string) $formation->modality : '—';
    $duration = $formation && ! empty( $formation->duration ) ? (string) $formation->duration : '';
    if ( '' === $duration ) {
      /* ACDC 3.25.224 — La durée de la fiche formation est un champ libre que
         personne ne remplit. Plutôt qu'un tiret sur une convocation, on
         additionne les créneaux réellement planifiés. */
      $planned = $this->acdc_completion_planned_time( wp_list_pluck( $sessions, 'id' ) );
      $duration = ! empty( $planned['label'] ) ? $planned['label'] : '—';
    }
    $email = $learner && ! empty( $learner->email ) ? (string) $learner->email : '—';
    $phone = $learner && ! empty( $learner->phone ) ? (string) $learner->phone : '—';
    $document = $this->get_training_convocation_document_info( $registration, array(
      'learner_name' => $learner_name,
      'formation_title' => $formation_title,
    ) );
    return array(
      'formation' => $formation,
      'learner' => $learner,
      'company' => $company,
      'sessions' => $sessions,
      'start_date' => $start_date,
      'end_date' => $end_date,
      'learner_name' => $learner_name,
      'is_commanditaire' => $is_commanditaire,
      'formation_title' => $formation_title,
      'format' => $format,
      'duration' => $duration,
      'email' => $email,
      'phone' => $phone,
      'document' => $document,
    );
  }  private function get_training_convocation_display_file_name( $registration, $context = array(), $document = array() ) {
    $context = is_array( $context ) ? $context : array();
    $document = is_array( $document ) ? $document : array();
    if ( ! empty( $document['url'] ) ) {
      $path = wp_parse_url( (string) $document['url'], PHP_URL_PATH );
      if ( $path ) {
        return basename( (string) $path );
      }
    }
    $learner = isset( $context['learner_name'] ) ? (string) $context['learner_name'] : ( ! empty( $registration->learner_label ) ? (string) $registration->learner_label : 'apprenant' );
    $formation = isset( $context['formation_title'] ) ? (string) $context['formation_title'] : ( ! empty( $registration->formation_title ) ? (string) $registration->formation_title : 'formation' );
    return sanitize_file_name( 'convocation-' . $learner . '-' . $formation . '.pdf' );
  }  private function get_training_convocation_download_url( $registration, $mode = 'attachment' ) {
    if ( ! $registration || empty( $registration->id ) ) {
      return '';
    }
    $mode = 'inline' === $mode ? 'inline' : 'attachment';
    return wp_nonce_url( admin_url( 'admin-post.php?action=acdc_download_training_convocation_document&registration_id=' . (int) $registration->id . '&mode=' . rawurlencode( $mode ) ), 'acdc_download_training_convocation_document_' . (int) $registration->id );
  }  private function get_training_convocation_entries( $search = '' ) {
    $search = trim( (string) $search );
    $items = $this->get_training_registrations( false );
    $rows = array();
    foreach ( (array) $items as $entry ) {
      $context = $this->get_training_convocation_context( $entry );
      /* ACDC 3.25.224 — « Le commanditaire n'a pas lieu d'avoir de
         convocation, ce sont uniquement les apprenants. » Règle de David,
         appliquée à la source plutôt qu'écran par écran. */
      if ( ! empty( $context['is_commanditaire'] ) ) {
        continue;
      }
      $haystack = strtolower( implode( ' ', array_filter( array(
        (string) $entry->id,
        (string) $context['learner_name'],
        (string) $context['formation_title'],
        (string) $context['email'],
        (string) $context['phone'],
      ) ) ) );
      if ( '' !== $search && false === strpos( $haystack, strtolower( $search ) ) ) {
        continue;
      }
      $state = '—';
      if ( ! empty( $context['end_date'] ) ) {
        $end_ts = strtotime( (string) $context['end_date'] . ' 23:59:59' );
        if ( $end_ts && $end_ts < current_time( 'timestamp' ) ) {
          $state = 'Formation terminée - ---';
        }
      }
      $rows[] = array(
        'registration' => $entry,
        'context' => $context,
        'state' => $state,
      );
    }
    return $rows;
  }

  /**
   * ACDC 3.25.249 — LA CONVOCATION EST ENFIN CONSERVÉE.
   *
   * Le PDF de convocation n'existait que le temps d'un affichage : il était
   * fabriqué à la volée pour le téléchargement, et joint à l'e-mail depuis un
   * fichier temporaire. Il n'était JAMAIS enregistré sur le dossier. Trois
   * conséquences en cascade :
   *   — l'apprenant ne la retrouvait pas dans son extranet, alors que le rayon
   *     « Convocations » existe et n'attendait que cette adresse ;
   *   — la pastille « Convocation » de la barre de complétude restait grise,
   *     puisqu'elle lit précisément cette colonne — d'où « les convocations sont
   *     parties mais pas en vert » ;
   *   — et l'organisme n'avait aucune trace de la pièce envoyée, alors qu'une
   *     convocation se justifie devant un financeur.
   *
   * Le nom du fichier porte un condensat dérivé des clés du site : déterministe,
   * donc une régénération retrouve le même fichier, mais impossible à deviner de
   * l'extérieur — même procédé que les attestations et les contrats formateurs.
   *
   * @return array{url:string,path:string} Vide si la fabrication échoue.
   */
  /**
   * ACDC 3.25.252 — IL N'Y A PLUS QU'UNE CONVOCATION.
   *
   * Trois codes l'écrivaient, et ils ne disaient pas la même chose : l'envoi
   * manuel annonçait « Formation / Dates / Durée / Format », l'envoi automatique
   * d'une séance ajoutait les horaires et le lieu, et le moteur — celui qui
   * expédie la veille à 17 h, donc celui que reçoivent la plupart des apprenants
   * — n'avait ni horaires, ni pièce jointe, et renvoyait vers l'extranet au lieu
   * de donner la convocation. Selon le chemin emprunté par l'organisme, la même
   * personne recevait pour la même formation un courrier différent.
   *
   * Le récapitulatif est composé ici, une fois. Les trois chemins l'appellent.
   *
   * @param array $args session, contract, formation, registration, formation_title, start, end.
   * @return array{summary_rows:array,body_html:string,attachments:array,pdf_url:string}
   */
  private function acdc_convocation_email_parts( $args = array() ) {
    $session      = isset( $args['session'] ) && is_object( $args['session'] ) ? $args['session'] : null;
    $contract     = isset( $args['contract'] ) && is_object( $args['contract'] ) ? $args['contract'] : null;
    $registration = isset( $args['registration'] ) && is_object( $args['registration'] ) ? $args['registration'] : null;
    $formation    = isset( $args['formation'] ) && is_object( $args['formation'] ) ? $args['formation'] : null;

    $formation_title = trim( (string) ( $args['formation_title'] ?? '' ) );
    if ( '' === $formation_title && $formation && ! empty( $formation->title ) ) {
      $formation_title = (string) $formation->title;
    }
    if ( '' === $formation_title ) {
      $formation_title = 'Formation';
    }

    /* La convocation dit COMBIEN DE JOURS, pas seulement quand ça commence :
       une formation de deux jours annoncée par sa seule date de début laisse
       l'apprenant organiser une seule journée. */
    $rows = array(
      array( 'label' => 'Formation', 'value' => $formation_title ),
      array( 'label' => ( $args['dates_label'] ?? '' ) ?: 'Dates', 'value' => $this->acdc_convocation_dates_label( $args['start'] ?? '', $args['end'] ?? '' ) ),
      array( 'label' => 'Horaires', 'value' => $this->acdc_convocation_hours_label( $session, $contract ) ),
      array( 'label' => 'Lieu / format', 'value' => $this->acdc_convocation_location_label( $session, $contract, $formation ) ),
    );

    $remote_link = '';
    if ( $session && ! empty( $session->remote_link ) ) {
      $remote_link = (string) $session->remote_link;
    } elseif ( $contract && ! empty( $contract->remote_link ) ) {
      $remote_link = (string) $contract->remote_link;
    }

    /* Le document lui-même : fabriqué et conservé AVANT la composition, pour
       que le message puisse porter son lien. */
    $stored = array( 'url' => '', 'path' => '' );
    if ( $registration && method_exists( $this, 'acdc_store_training_convocation_pdf' ) ) {
      $stored = $this->acdc_store_training_convocation_pdf( $registration );
    }

    $body = '<p style="font-size:18px;line-height:1.7;margin:0 0 18px;">Vous êtes convoqué(e) à la formation indiquée ci-dessus. Merci de vous présenter quelques minutes avant le début de la première demi-journée, muni(e) des documents nécessaires.</p>';
    if ( '' !== $remote_link ) {
      $body .= '<p style="font-size:17px;line-height:1.7;margin:0 0 18px;">Lien de connexion : <a href="' . esc_url( $remote_link ) . '" style="color:#C5A253;text-decoration:underline;">' . esc_html( $remote_link ) . '</a></p>';
    }
    if ( ! empty( $stored['url'] ) ) {
      $body .= '<p style="margin:24px 0;text-align:center;"><a href="' . esc_url( (string) $stored['url'] ) . '" style="display:inline-block;padding:14px 28px;background:#C5A253;color:#0B0706;text-decoration:none;border-radius:8px;font-weight:700;font-size:16px;">📄 Télécharger ma convocation</a></p>';
    } else {
      /* Sans document, on ne promet pas un téléchargement : on renvoie à
         l'espace apprenant, qui existe toujours. */
      $portal_id  = (int) get_option( 'acdc_of_learner_portal_page_id', 0 );
      $portal_url = $portal_id ? get_permalink( $portal_id ) : home_url( '/' );
      $body .= '<p style="margin:24px 0;text-align:center;"><a href="' . esc_url( $portal_url ) . '" style="display:inline-block;padding:14px 28px;background:#C5A253;color:#0B0706;text-decoration:none;border-radius:8px;font-weight:700;font-size:16px;">Accéder à mon espace apprenant</a></p>';
    }

    $attachments = ( ! empty( $stored['path'] ) && file_exists( (string) $stored['path'] ) ) ? array( (string) $stored['path'] ) : array();

    return array(
      'summary_rows' => $rows,
      'body_html'    => $body,
      'attachments'  => $attachments,
      'pdf_url'      => (string) $stored['url'],
    );
  }

  /**
   * « Vendredi 14 août 2026 » sur une journée, « Du … au … » sur plusieurs.
   *
   * Accepte indifféremment un horodatage réel ou une date de base : les deux
   * circulent dans le plugin, et une convocation qui refuse une des deux formes
   * affiche « À préciser » sur un dossier parfaitement daté.
   */
  private function acdc_convocation_dates_label( $start, $end = '' ) {
    $fmt = function ( $value ) {
      if ( empty( $value ) ) {
        return '';
      }
      if ( is_numeric( $value ) ) {
        return (int) $value > 0 ? ucfirst( wp_date( 'l d F Y', (int) $value ) ) : '';
      }
      /* Date locale de la base : mysql2date, jamais strtotime. */
      $label = mysql2date( 'l d F Y', (string) $value, true );
      return $label ? ucfirst( $label ) : '';
    };

    $start_label = $fmt( $start );
    $end_label   = $fmt( $end );

    if ( '' === $start_label && '' === $end_label ) {
      return 'À préciser';
    }
    if ( '' === $end_label || $end_label === $start_label ) {
      return $start_label ?: $end_label;
    }
    if ( '' === $start_label ) {
      return $end_label;
    }
    return 'Du ' . lcfirst( $start_label ) . ' au ' . lcfirst( $end_label );
  }

  /**
   * Les horaires annoncés à l'apprenant.
   *
   * Deux sources, dans l'ordre où elles font foi : le déroulé de la séance —
   * ce qui a été réellement planifié — puis celui de la convention, qui est ce
   * qui a été contractualisé. Aucune heure inventée : sans l'une ni l'autre, on
   * le dit.
   */
  private function acdc_convocation_hours_label( $session, $contract = null ) {
    if ( $session && method_exists( $this, 'acdc_session_hours_label' ) ) {
      $label = (string) $this->acdc_session_hours_label( $session );
      if ( '' !== $label && '—' !== $label ) {
        return $label;
      }
    }

    if ( $contract && ! empty( $contract->seances_schedule_json ) ) {
      $decoded = json_decode( (string) $contract->seances_schedule_json, true );
      if ( is_array( $decoded ) ) {
        foreach ( $decoded as $day ) {
          if ( ! is_array( $day ) ) {
            continue;
          }
          $am = ( ! empty( $day['am_start'] ) && ! empty( $day['am_end'] ) )
            ? str_replace( ':', 'h', (string) $day['am_start'] ) . '–' . str_replace( ':', 'h', (string) $day['am_end'] )
            : '';
          $pm = ( ! empty( $day['pm_start'] ) && ! empty( $day['pm_end'] ) )
            ? str_replace( ':', 'h', (string) $day['pm_start'] ) . '–' . str_replace( ':', 'h', (string) $day['pm_end'] )
            : '';
          $parts = array_filter( array( $am, $pm ) );
          if ( $parts ) {
            /* Les journées d'une même convention partagent leurs horaires dans
               l'immense majorité des cas : on annonce ceux de la première, et
               la convocation détaillée en pièce jointe donne le jour par jour. */
            return implode( ' et ', $parts );
          }
        }
      }
    }

    return 'À préciser';
  }

  /**
   * Le lieu annoncé à l'apprenant.
   *
   * La séance d'abord — c'est le terrain —, puis la convention, qui est la
   * pièce qui engage, puis la fiche formation. Le distanciel n'est retenu que
   * si aucune adresse n'existe : une formation en salle avec un lien de secours
   * reste une formation en salle.
   *
   * « À préciser » plutôt qu'un tiret : un tiret ne dit rien à quelqu'un qui
   * doit se déplacer.
   */
  private function acdc_convocation_location_label( $session, $contract = null, $formation = null ) {
    $compose = static function ( $address, $postal_code = '', $city = '' ) {
      $address = trim( (string) $address );
      if ( '' === $address ) {
        return '';
      }
      $tail = trim( trim( (string) $postal_code ) . ' ' . trim( (string) $city ) );
      /* Ne pas répéter le code postal ou la ville déjà présents dans la ligne. */
      if ( '' !== $tail && false === stripos( $address, $tail ) ) {
        return $address . ', ' . $tail;
      }
      return $address;
    };

    if ( $session && ! empty( $session->location ) ) {
      $label = $compose( $session->location, $session->postal_code ?? '', $session->city ?? '' );
      if ( '' !== $label ) {
        return $label;
      }
    }

    if ( $contract ) {
      $label = $compose(
        $contract->formation_address ?? '',
        $contract->formation_postal_code ?? '',
        $contract->formation_city ?? ''
      );
      if ( '' !== $label ) {
        return $label;
      }
    }

    if ( $formation ) {
      $label = $compose( $formation->address ?? '', $formation->postal_code ?? '', $formation->city ?? '' );
      if ( '' !== $label ) {
        return $label;
      }
    }

    $remote = ( $session && ! empty( $session->remote_link ) ) || ( $contract && ! empty( $contract->remote_link ) );
    return $remote ? 'Distanciel' : 'À préciser';
  }

  private function acdc_store_training_convocation_pdf( $registration, $context = array() ) {
    $empty = array( 'url' => '', 'path' => '' );
    if ( ! is_object( $registration ) || empty( $registration->id ) ) {
      return $empty;
    }

    /* Déjà enregistrée et le fichier est là : on ne refabrique pas. Une
       convocation déjà expédiée ne doit pas changer sous les pieds de
       l'apprenant qui la relit. */
    if ( ! empty( $registration->convocation_document_path ) && file_exists( (string) $registration->convocation_document_path ) ) {
      return array(
        'url'  => (string) $registration->convocation_document_url,
        'path' => (string) $registration->convocation_document_path,
      );
    }

    if ( empty( $context ) ) {
      $context = $this->get_training_convocation_context( $registration );
    }
    $pages = $this->build_training_convocation_pdf_pages( $registration, $context );
    if ( empty( $pages ) || ! method_exists( $this, '_build_simple_pdf_string' ) ) {
      return $empty;
    }
    $pdf = $this->_build_simple_pdf_string( $pages );
    if ( '' === (string) $pdf ) {
      return $empty;
    }

    $uploads = wp_upload_dir();
    if ( empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
      return $empty;
    }
    $dir = trailingslashit( $uploads['basedir'] ) . 'acdc-convocations/';
    wp_mkdir_p( $dir );

    $token    = substr( wp_hash( 'acdc-convocation-' . (int) $registration->id ), 0, 20 );
    $filename = sanitize_file_name( 'convocation-' . (int) $registration->id . '-' . $token . '.pdf' );
    $path     = $dir . $filename;
    if ( false === file_put_contents( $path, $pdf ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
      return $empty;
    }
    $url = trailingslashit( $uploads['baseurl'] ) . 'acdc-convocations/' . $filename;

    global $wpdb;
    $wpdb->update(
      $this->training_registration_table,
      array(
        'convocation_document_url'  => esc_url_raw( $url ),
        'convocation_document_path' => $path,
        'updated_at'                => $this->now_mysql(),
      ),
      array( 'id' => (int) $registration->id )
    );

    /* L'objet en mémoire doit refléter la base : l'appelant s'en sert juste
       après pour composer l'e-mail. */
    $registration->convocation_document_url  = $url;
    $registration->convocation_document_path = $path;

    return array( 'url' => $url, 'path' => $path );
  }

  private function build_training_convocation_pdf_pages( $registration, $context = array() ) {
    $context = is_array( $context ) ? $context : array();
    if ( empty( $context ) ) {
      $context = $this->get_training_convocation_context( $registration );
    }
    $profile = $this->get_company_profile_options();
    $params = $this->get_convocation_params_options();
    $branding = $this->get_branding_options();
    $org_name = ! empty( $profile['enterprise'] ) ? $profile['enterprise'] : ( ! empty( $branding['company_name'] ) ? $branding['company_name'] : 'ACDC Formation' );
    /* ACDC 3.25.254 — Le bandeau beige, le logo et le cachet étaient préparés
       ici ; la charte s'en charge. Seules restent les données du texte. */
    $title_color = '#0C2D52';
    $org_city = ! empty( $profile['city'] ) ? $profile['city'] : ( ! empty( $branding['city'] ) ? $branding['city'] : 'Ville non renseignée' );
    $org_email = ! empty( $profile['enterprise_contact_email'] ) ? $profile['enterprise_contact_email'] : ( ! empty( $branding['email'] ) ? $branding['email'] : '' );

    $learner_name = $context['learner_name'];
    $formation_title = $context['formation_title'];
    $format = $context['format'];
    $dates_text = 'Dates de l’action de formation : ' . $this->format_pdf_date( $context['start_date'] ) . ' au ' . $this->format_pdf_date( $context['end_date'] );

    /* ACDC 3.25.224 — LA CONVOCATION NE DOIT RIEN INVENTER, ET NE RIEN TAIRE.
       Elle annonçait « à 09:00 » par défaut — un horaire écrit en dur, donc
       potentiellement faux — n'affichait aucune date de fin exploitable, et
       tombait sur « — » pour le lieu dès qu'une séance existait sans adresse
       propre : la branche de repli était derrière un `elseif` qui ne pouvait
       plus être atteint. Une convocation est le document sur lequel la
       personne se fonde pour se déplacer ; s'y tromper d'heure ou de lieu est
       la faute la plus concrète du parcours.
       On lit donc le planning réel, demi-journée par demi-journée, et l'on
       nomme explicitement ce qui manque plutôt que de le combler. */
    $slots = array();
    foreach ( (array) ( $context['sessions'] ?? array() ) as $session_row ) {
      $decoded = ! empty( $session_row->schedule_json ) ? json_decode( (string) $session_row->schedule_json, true ) : null;
      if ( is_array( $decoded ) && ! empty( $decoded ) ) {
        foreach ( array_values( $decoded ) as $slot ) {
          $slot = (array) $slot;
          if ( ! empty( $slot['start_at'] ) ) {
            $slots[] = array( (string) $slot['start_at'], (string) ( $slot['end_at'] ?? '' ) );
          }
        }
        continue;
      }
      if ( ! empty( $session_row->start_at ) ) {
        $slots[] = array( (string) $session_row->start_at, (string) ( $session_row->end_at ?? '' ) );
      }
    }
    usort( $slots, static function( $a, $b ) {
      return strcmp( $a[0], $b[0] );
    } );

    $start_hour = '';
    if ( ! empty( $slots ) ) {
      $start_hour = date_i18n( 'H:i', strtotime( $slots[0][0] ) );
    }

    $schedule_lines = array();
    foreach ( $slots as $slot ) {
      $line = date_i18n( 'l d/m/Y', strtotime( $slot[0] ) ) . ' — ' . date_i18n( 'H:i', strtotime( $slot[0] ) );
      if ( '' !== $slot[1] ) {
        $line .= ' à ' . date_i18n( 'H:i', strtotime( $slot[1] ) );
      }
      $schedule_lines[] = $line;
    }

    /* Le lieu, en cascade et sans trou : la séance, puis la formation, puis
       l'adresse du commanditaire — « dans la convention c'est sur le lieu de
       l'entreprise ». */
    $location = '';
    foreach ( (array) ( $context['sessions'] ?? array() ) as $session_row ) {
      if ( ! empty( $session_row->location ) ) {
        $location = (string) $session_row->location;
        break;
      }
    }
    if ( '' === $location && ! empty( $context['formation']->address ) ) {
      $location = trim( implode( ' ', array_filter( array( $context['formation']->address, $context['formation']->postal_code, $context['formation']->city ) ) ) );
    }
    if ( '' === $location && ! empty( $context['company'] ) ) {
      $company_row = $context['company'];
      $location = trim( implode( ' ', array_filter( array(
        (string) ( $company_row->address ?? '' ),
        (string) ( $company_row->postal_code ?? '' ),
        (string) ( $company_row->city ?? '' ),
      ) ) ) );
    }
    if ( '' === $location ) {
      $location = 'à préciser — aucune adresse n’est renseignée sur la séance, la formation ni le commanditaire';
    }

    $blocks = array(
      /* Le titre est porté par l'en-tête de la charte : le répéter ici en
         ferait un doublon à deux tailles différentes. */
      array( 'type' => 'spacer' ),
      /* Pas de civilité devinée : « M. » était écrit en dur devant chaque
         nom, y compris ceux de trois apprenantes. */
      array( 'type' => 'line', 'text' => $learner_name ),
      array( 'type' => 'paragraph', 'text' => 'Nous avons le plaisir de vous adresser notre convocation à la formation : ' . $formation_title . '.' ),
      array( 'type' => 'paragraph', 'text' => 'Vous trouverez ci-dessous toutes les informations pratiques à savoir :' ),
      array( 'type' => 'line', 'text' => 'Format de la formation : ' . $format ),
      array( 'type' => 'line', 'text' => $dates_text ),
      array( 'type' => 'line', 'text' => 'Durée de l’action de formation : ' . $context['duration'] ),
      array(
        'type' => 'line',
        'text' => '' !== $start_hour
          ? 'Votre formation débutera le ' . $this->format_pdf_date( $context['start_date'] ) . ' à ' . $start_hour
          : 'Votre formation débutera le ' . $this->format_pdf_date( $context['start_date'] ) . ' — horaire communiqué séparément',
      ),
      array( 'type' => 'paragraph', 'text' => 'La formation se déroulera dans les locaux situés à l’adresse suivante : ' . $location ),
    );

    if ( ! empty( $schedule_lines ) ) {
      $blocks[] = array( 'type' => 'heading', 'text' => 'Vos horaires, demi-journée par demi-journée' );
      foreach ( $schedule_lines as $schedule_line ) {
        $blocks[] = array( 'type' => 'line', 'text' => $schedule_line );
      }
    }

    $blocks = array_merge( $blocks, array(
      array( 'type' => 'heading', 'text' => 'Recommandations pour suivre la formation' ),
      array( 'type' => 'paragraph', 'text' => ! empty( $params['training_recommendations'] ) ? $params['training_recommendations'] : 'Merci de vous présenter 10 minutes avant le début de la session et de prévoir de quoi prendre des notes.' ),
      array( 'type' => 'heading', 'text' => 'Moyens d’accès à la formation' ),
      array( 'type' => 'paragraph', 'text' => ! empty( $params['training_access_means'] ) ? $params['training_access_means'] : 'Les informations pratiques (adresse) sont précisées dans la convocation.' ),
      array( 'type' => 'line', 'text' => 'Accès PMR : ' . ( ! empty( $params['pmr_access'] ) ? 'Oui' : 'Non' ) ),
      array( 'type' => 'paragraph', 'text' => ! empty( $params['pmr_access_details'] ) ? $params['pmr_access_details'] : 'Détail accès PMR non renseigné.' ),
      array( 'type' => 'line', 'text' => 'Référent de l’organisme de formation : ' . ( ! empty( $params['training_contact_person'] ) ? $params['training_contact_person'] : $org_name ) ),
      array( 'type' => 'line', 'text' => 'Contact de l’organisme de formation : ' . ( ! empty( $params['training_contact_details'] ) ? $params['training_contact_details'] : $org_email ) ),
    ) );
    if ( ! empty( $params['additional_sections'] ) && is_array( $params['additional_sections'] ) ) {
      foreach ( $params['additional_sections'] as $section ) {
        if ( empty( $section['title'] ) && empty( $section['content'] ) ) {
          continue;
        }
        $blocks[] = array( 'type' => 'heading', 'text' => (string) $section['title'] );
        $blocks[] = array( 'type' => 'paragraph', 'text' => (string) $section['content'] );
      }
    }
    $blocks[] = array( 'type' => 'spacer' );
    $blocks[] = array( 'type' => 'line', 'text' => 'Fait à ' . $org_city );
    $blocks[] = array( 'type' => 'line', 'text' => 'Le : ' . date_i18n( 'd/m/Y' ) );

    $pages = array();
    $current = array();
    $y = 792;

    /* ACDC 3.25.254 — LA CONVOCATION ENTRE DANS LA CHARTE.
       Elle portait un bandeau beige pleine largeur, la raison sociale en
       capitales à 15 pt et son titre posé au milieu du bandeau : elle ne
       ressemblait ni au contrat formateur ni à la convention. Elle prend
       maintenant le même en-tête et le même pied que les autres pièces du
       dossier — c'est le même organisme qui les signe. */
    $header = $this->acdc_pdf_charte_header( 'Convocation en formation' );
    $current = array_merge( $current, $header );
    $y = 700;

    foreach ( $blocks as $block ) {
      $type = $block['type'];
      if ( 'spacer' === $type ) {
        $y -= 12;
        continue;
      }
      $font_size = 11;
      $line_height = 14;
      if ( 'title' === $type ) {
        $font_size = 17;
        $line_height = 22;
      } elseif ( 'heading' === $type ) {
        $font_size = 11;
        $line_height = 16;
      }
      $lines = array( $block['text'] );
      if ( in_array( $type, array( 'paragraph', 'title' ), true ) ) {
        $lines = $this->pdf_wrap_text( $block['text'], 'title' === $type ? 46 : 92 );
      }
      foreach ( $lines as $line ) {
        /* 86 pt suffisaient tant qu'il n'y avait pas de pied de page ; il en
           occupe 30, et le texte passait par-dessus. */
        if ( $y < 110 ) {
          $pages[] = $current;
          $current = array_merge( array(), $header );
          $y = 700;
        }
        $current[] = array(
          'text' => $line,
          /* Aligné sur la marge de la charte : le corps commençait vingt points
             plus à droite que l'en-tête, et le décalage se voyait. */
          'x' => 35.43,
          'y' => $y,
          'size' => $font_size,
          'font' => in_array( $type, array( 'title', 'heading' ), true ) ? 'Helvetica-Bold' : 'Helvetica',
          'color' => in_array( $type, array( 'title', 'heading' ), true ) ? $title_color : '#1f2937',
        );
        $y -= $line_height;
      }
      $y -= 4;
    }

    $stamp_line = $this->acdc_pdf_charte_stamp( 360, 52, 170, 128 );
    if ( $stamp_line ) {
      $current[] = $stamp_line;
    }
    if ( ! empty( $current ) ) {
      $pages[] = $current;
    }
    /* Le pied de page manquait : une convocation partait sans l'adresse, le
       SIRET ni le NDA de l'organisme, quand la convention et le contrat les
       portent. On l'ajoute sur chaque page. */
    foreach ( $pages as $index => $page_lines ) {
      $this->acdc_pdf_charte_footer( $page_lines );
      $pages[ $index ] = $page_lines;
    }
    return $pages;
  }  private function get_training_file_status_label( $registration ) {
    if ( ! $registration ) {
      return 'Brouillon';
    }
    if ( ! empty( $registration->is_draft ) ) {
      return 'Brouillon';
    }
    $has_contract = ! empty( $registration->autofill_contract_id ) || ! empty( $registration->autofill_contract_label );
    $has_convocation = ! empty( $registration->convocation_document_url ) || ! empty( $registration->convocation_document_path );
    if ( $has_contract && $has_convocation ) {
      return 'Dossier prêt';
    }
    if ( $has_contract ) {
      return 'Contractualisation en place';
    }
    return 'À compléter';
  }  private function get_training_file_linked_sessions( $registration ) {
    if ( ! $registration ) {
      return array();
    }

    $formation_id = isset( $registration->formation_id ) ? absint( $registration->formation_id ) : 0;
    $company_id   = isset( $registration->company_id ) ? absint( $registration->company_id ) : 0;
    $sessions     = $this->get_sessions();
    $linked       = array();

    foreach ( (array) $sessions as $session ) {
      $session_formation_id = isset( $session->formation_id ) ? absint( $session->formation_id ) : 0;
      $session_company_id   = isset( $session->company_id ) ? absint( $session->company_id ) : 0;
      if ( $formation_id && $session_formation_id !== $formation_id ) {
        continue;
      }
      if ( $company_id && $session_company_id !== $company_id ) {
        continue;
      }
      $linked[] = $session;
    }

    usort( $linked, static function( $a, $b ) {
      $a_date = isset( $a->start_at ) && $a->start_at ? (string) $a->start_at : ( isset( $a->start_date ) ? (string) $a->start_date : '' );
      $b_date = isset( $b->start_at ) && $b->start_at ? (string) $b->start_at : ( isset( $b->start_date ) ? (string) $b->start_date : '' );
      return strcmp( $b_date, $a_date );
    } );

    return $linked;
  }  private function get_training_file_session_view_url( $session ) {
    $session_id = isset( $session->id ) ? absint( $session->id ) : 0;
    if ( ! $session_id ) {
      return '';
    }

    if ( ! empty( $session->is_draft ) || 'Brouillon' === (string) ( $session->status ?? '' ) ) {
      return is_admin()
        ? admin_url( 'admin.php?page=acdc-of-create-session&action=edit&item_id=' . $session_id )
        : $this->portal_page_url( array( 'tab' => 'create_session', 'action' => 'edit', 'item_id' => $session_id ) );
    }

    return is_admin()
      ? admin_url( 'admin.php?page=acdc-of-dashboard&tab=sessions_validated&action=view&item_id=' . $session_id )
      : $this->portal_page_url( array( 'tab' => 'sessions_validated', 'action' => 'view', 'item_id' => $session_id ) );
  }  private function get_training_file_history_entries( $registration ) {
    $entries = array();
    if ( ! $registration ) {
      return $entries;
    }
    $entries[] = array(
      'date' => (string) ( $registration->created_at ?? '' ),
      'title' => 'Dossier créé',
      'detail' => 'Création initiale du dossier de formation.',
    );
    if ( ! empty( $registration->updated_at ) && $registration->updated_at !== $registration->created_at ) {
      $entries[] = array(
        'date' => (string) $registration->updated_at,
        'title' => 'Dossier mis à jour',
        'detail' => 'Dernière mise à jour enregistrée sur le dossier.',
      );
    }
    $profile = $this->get_training_file_profile( (int) $registration->id );
    if ( ! empty( $profile['updated_at'] ) ) {
      $entries[] = array(
        'date' => (string) $profile['updated_at'],
        'title' => 'Bloc dossier mis à jour',
        'detail' => 'Le besoin, les objectifs, le positionnement, la contractualisation, l’accueil ou le suivi des séances ont été complétés.',
      );
    }
    $document_map = array(
      'convocation_document_url' => 'Convocation disponible',
      'positioning_result_document_url' => 'Résultat de positionnement disponible',
      'diagnostic_result_document_url' => 'Résultat de l’évaluation diagnostique disponible',
      'mid_survey_document_url' => 'Enquête intermédiaire disponible',
      'hot_survey_document_url' => 'Enquête à chaud disponible',
      'cold_survey_document_url' => 'Enquête à froid disponible',
      'evaluation_result_document_url' => 'Évaluation des acquis disponible',
      'completion_certificate_document_url' => 'Certificat de réalisation disponible',
      'end_training_certificate_document_url' => 'Attestation de fin de formation disponible',
    );
    foreach ( $document_map as $field => $label ) {
      if ( ! empty( $registration->$field ) ) {
        $entries[] = array(
          'date' => (string) ( $registration->updated_at ?? $registration->created_at ?? '' ),
          'title' => $label,
          'detail' => 'Un document lié au dossier est présent dans le système.',
        );
      }
    }

    if ( ! empty( $profile['sessions']['session_summary'] ) || ! empty( $profile['sessions']['attendance_status'] ) || ! empty( $profile['sessions']['operational_notes'] ) ) {
      $entries[] = array(
        'date' => ! empty( $profile['updated_at'] ) ? (string) $profile['updated_at'] : (string) ( $registration->updated_at ?? $registration->created_at ?? '' ),
        'title' => 'Suivi opérationnel des séances mis à jour',
        'detail' => ! empty( $profile['sessions']['attendance_status'] ) ? (string) $profile['sessions']['attendance_status'] : 'Informations de séance, d’émargement ou d’incident enregistrées.',
      );
    }
    if ( ! empty( $profile['evaluations']['evaluation_summary'] ) || ! empty( $profile['evaluations']['final_evaluation_status'] ) || ! empty( $profile['evaluations']['hot_survey_status'] ) || ! empty( $profile['evaluations']['cold_survey_status'] ) ) {
      $entries[] = array(
        'date' => ! empty( $profile['updated_at'] ) ? (string) $profile['updated_at'] : (string) ( $registration->updated_at ?? $registration->created_at ?? '' ),
        'title' => 'Évaluations et enquêtes mises à jour',
        'detail' => ! empty( $profile['evaluations']['evaluation_summary'] ) ? (string) $profile['evaluations']['evaluation_summary'] : 'Des évaluations, enquêtes ou résultats liés au dossier ont été enregistrés.',
      );
    }
    if ( ! empty( $profile['complaints']['complaint_summary'] ) || ! empty( $profile['complaints']['complaint_status'] ) || ! empty( $profile['complaints']['incident_summary'] ) || ! empty( $profile['complaints']['improvement_action'] ) ) {
      $entries[] = array(
        'date' => ! empty( $profile['updated_at'] ) ? (string) $profile['updated_at'] : (string) ( $registration->updated_at ?? $registration->created_at ?? '' ),
        'title' => 'Réclamations / incidents / amélioration continue mis à jour',
        'detail' => ! empty( $profile['complaints']['improvement_action'] ) ? (string) $profile['complaints']['improvement_action'] : ( ! empty( $profile['complaints']['complaint_summary'] ) ? (string) $profile['complaints']['complaint_summary'] : 'Des informations de réclamation, incident ou amélioration continue ont été enregistrées.' ),
      );
    }
    if ( ! empty( $profile['quality']['regulatory_watch'] ) || ! empty( $profile['quality']['quality_indicators'] ) || ! empty( $profile['quality']['internal_audit_summary'] ) || ! empty( $profile['quality']['quality_summary'] ) ) {
      $entries[] = array(
        'date' => ! empty( $profile['updated_at'] ) ? (string) $profile['updated_at'] : (string) ( $registration->updated_at ?? $registration->created_at ?? '' ),
        'title' => 'Veilles / indicateurs qualité / audit interne mis à jour',
        'detail' => ! empty( $profile['quality']['quality_summary'] ) ? (string) $profile['quality']['quality_summary'] : ( ! empty( $profile['quality']['internal_audit_summary'] ) ? (string) $profile['quality']['internal_audit_summary'] : 'Des informations de veille, indicateurs qualité ou audit interne ont été enregistrées.' ),
      );
    }
    usort( $entries, static function( $a, $b ) {
      return strcmp( (string) ( $b['date'] ?? '' ), (string) ( $a['date'] ?? '' ) );
    } );
    return $entries;
  }  private function get_training_file_profile_defaults() {
    return array(
      'need' => array(
        'beneficiary_need' => '',
        'company_need' => '',
        'funder_need' => '',
        'context' => '',
        'constraints' => '',
        'feasibility' => '',
        'handicap_adaptation' => '',
        'internal_summary' => '',
      ),
      'objectives' => array(
        'operational_objectives' => '',
        'pedagogical_objectives' => '',
        'targeted_skills' => '',
        'program_content' => '',
        'modalities' => '',
        'evaluation_methods' => '',
        'certification_reference' => '',
      ),
      'positioning' => array(
        'prerequisites_checked' => '',
        'entry_test' => '',
        'interview_notes' => '',
        'self_positioning' => '',
        'initial_level' => '',
        'identified_gaps' => '',
        'decided_adaptation' => '',
      ),
      'contract' => array(
        'contract_type' => '',
        'funder' => '',
        'contract_dates' => '',
        'signature_status' => '',
        'sent_at' => '',
        'received_at' => '',
        'follow_up' => '',
        'attachments_summary' => '',
      ),
      'welcome' => array(
        'invitation_sent' => '',
        'welcome_pack_sent' => '',
        'internal_rules_shared' => '',
        'pedagogical_contact' => '',
        'administrative_contact' => '',
        'lms_access' => '',
        'technical_support' => '',
        'pedagogical_support' => '',
        'safety_information' => '',
        'psh_information' => '',
        'delivery_proof' => '',
      ),
      'sessions' => array(
        'session_summary' => '',
        'attendance_status' => '',
        'attendance_method' => '',
        'incidents' => '',
        'operational_notes' => '',
      ),
      'support' => array(
        'pedagogical_referent' => '',
        'support_date' => '',
        'support_type' => '',
        'encounters_summary' => '',
        'identified_difficulties' => '',
        'hazards' => '',
        'dropout_risk' => '',
        'corrective_actions' => '',
        'specific_support' => '',
        'company_exchanges' => '',
        'follow_up_status' => '',
      ),
      'evaluations' => array(
        'positioning_status' => '',
        'mid_survey_status' => '',
        'hot_survey_status' => '',
        'final_evaluation_status' => '',
        'cold_survey_status' => '',
        'trainer_survey_status' => '',
        'company_survey_status' => '',
        'funder_survey_status' => '',
        'evaluation_summary' => '',
        'related_documents_summary' => '',
      ),
      'documents' => array(
        'proof_status' => '',
        'pedagogical_documents' => '',
        'administrative_documents' => '',
        'end_of_training_documents' => '',
        'document_delivery_proof' => '',
        'document_summary' => '',
      ),
      'complaints' => array(
        'complaint_status' => '',
        'complaint_source' => '',
        'complaint_summary' => '',
        'incident_summary' => '',
        'severity_level' => '',
        'assigned_manager' => '',
        'corrective_measure' => '',
        'improvement_action' => '',
        'improvement_deadline' => '',
        'improvement_effectiveness' => '',
      ),
      'compliance' => array(
        'handicap_status' => '',
        'handicap_referent' => '',
        'compensation_need' => '',
        'adaptation_decision' => '',
        'mobilized_resources' => '',
        'subcontractor_name' => '',
        'subcontracting_scope' => '',
        'subcontracting_contract' => '',
        'subcontracting_quality_control' => '',
        'internal_trainer_name' => '',
        'internal_trainer_skills' => '',
        'internal_training_actions' => '',
        'compliance_summary' => '',
      ),
      'quality' => array(
        'regulatory_watch' => '',
        'business_watch' => '',
        'pedagogical_watch' => '',
        'quality_indicators' => '',
        'internal_audit_summary' => '',
        'internal_audit_actions' => '',
        'quality_summary' => '',
      ),
      'public_info' => array(
        'public_title' => '',
        'public_summary' => '',
        'public_prerequisites' => '',
        'public_modalities' => '',
        'public_duration' => '',
        'public_access_delay' => '',
        'public_tariffs' => '',
        'public_contacts' => '',
        'public_certification' => '',
        'public_results' => '',
        'publication_status' => '',
        'published_at' => '',
        'publication_notes' => '',
      ),
      'accounting' => array(
        'funder_link_id' => '',
        'funder_type_label' => '',
        'funder_dossier_number' => '',
        'funder_pec_status' => '',
        'funder_amount' => '',
        'funder_sent_date' => '',
        'funder_expected_reply_date' => '',
        'funder_pec_doc_url' => '',
        'funder_other_doc_url' => '',
        'quote_reference' => '',
        'invoice_reference' => '',
        'credit_note_reference' => '',
        'activity_report_reference' => '',
        'billing_status' => '',
        'billing_amount' => '',
        'payment_status' => '',
        'payment_due_date' => '',
        'funder_billing_notes' => '',
        'reporting_summary' => '',
        'accounting_notes' => '',
      ),
      'updated_at' => '',
      'updated_by' => 0,
    );
  }  private function get_training_file_profiles() {
    $profiles = get_option( 'acdc_of_training_file_profiles', array() );
    return is_array( $profiles ) ? $profiles : array();
  }  private function get_training_file_profile( $registration_id ) {
    $defaults = $this->get_training_file_profile_defaults();
    $profiles = $this->get_training_file_profiles();
    $profile = isset( $profiles[ $registration_id ] ) && is_array( $profiles[ $registration_id ] ) ? $profiles[ $registration_id ] : array();
    return array_replace_recursive( $defaults, $profile );
  }  private function save_training_file_profile( $registration_id, $profile ) {
    $profiles = $this->get_training_file_profiles();
    $profiles[ $registration_id ] = $profile;
    update_option( 'acdc_of_training_file_profiles', $profiles, false );
  }

  /* ACDC 3.25.278 — Les cinq lectures de l'ancienne table « tests de
     positionnement » sont retirées avec elle : le module faisait double emploi
     avec le quiz, et son envoi automatique n'a jamais fonctionné. */

  /* -----------------------------------------------------------------------
   * ACDC 3.21.10 — Seed des blocs et questions système (idempotent).
   * -------------------------------------------------------------------- */
  /* =====================================================================
   * ACDC 3.21.13 — Page publique analyse du besoin (auto-créée au boot).
   * ===================================================================== */
  private function ensure_nad_public_page() {
    if ( (int) get_option( 'acdc_of_nad_public_page_id', 0 ) > 0 ) {
      return;
    }
    $page_id = wp_insert_post( array(
      'post_title'   => 'Analyse du besoin',
      'post_name'    => 'acdc-analyse-besoin',
      'post_content' => '[acdc_nad_formulaire]',
      'post_status'  => 'publish',
      'post_type'    => 'page',
      'meta_input'   => array( '_acdc_nad_public_page' => '1' ),
    ) );
    if ( $page_id && ! is_wp_error( $page_id ) ) {
      update_option( 'acdc_of_nad_public_page_id', $page_id );
    }
  }

  /* =====================================================================
   * ACDC 3.21.13 — Moteur d'auto-création des analyses depuis une convention.
   * ===================================================================== */

  /* =====================================================================
   * ACDC 3.21.17 — Cron envoi différé + relances automatiques.
   * ===================================================================== */

  public function cron_nad_send_and_relance_core() {
    global $wpdb;
    $now = current_time( 'mysql' );

    // 1. Envois différés : scheduled_send_at <= NOW() ET sent_at IS NULL
    $to_send = $wpdb->get_results(
      "SELECT * FROM {$this->need_analysis_table}
       WHERE is_model = 0
         AND sent_at IS NULL
         AND token_public IS NOT NULL
         AND scheduled_send_at IS NOT NULL
         AND scheduled_send_at <= '{$now}'
         AND statut NOT IN ('traite','archive','expire')"
    );
    foreach ( $to_send as $analysis ) {
      $this->nad_send_initial_email( $analysis );
      $wpdb->update( $this->need_analysis_table, array(
        'sent_at'    => $now,
        'statut'     => 'nouveau',
        'updated_at' => $now,
      ), array( 'id' => (int) $analysis->id ) );
    }

    // 2. Relances automatiques : envoyé depuis > 48h, pas encore répondu
    $delay = "DATE_SUB(NOW(), INTERVAL 48 HOUR)";
    $to_relance = $wpdb->get_results(
      "SELECT * FROM {$this->need_analysis_table}
       WHERE is_model = 0
         AND sent_at IS NOT NULL
         AND sent_at <= {$delay}
         AND statut NOT IN ('traite','archive','expire')
         AND token_public IS NOT NULL"
    );
    foreach ( $to_relance as $analysis ) {
      $r1 = ! empty( $analysis->relance_1_at );
      $r2 = ! empty( $analysis->relance_2_at );
      $r3 = ! empty( $analysis->relance_3_at );

      if ( ! $r1 && strtotime( $analysis->sent_at ) <= strtotime( '-48 hours' ) ) {
        $this->nad_send_relance_email( $analysis, 1 );
        $wpdb->update( $this->need_analysis_table, array( 'relance_1_at' => $now, 'statut' => 'a_traiter', 'updated_at' => $now ), array( 'id' => (int) $analysis->id ) );
      } elseif ( $r1 && ! $r2 && strtotime( $analysis->relance_1_at ) <= strtotime( '-48 hours' ) ) {
        $this->nad_send_relance_email( $analysis, 2 );
        $wpdb->update( $this->need_analysis_table, array( 'relance_2_at' => $now, 'updated_at' => $now ), array( 'id' => (int) $analysis->id ) );
      } elseif ( $r2 && ! $r3 && strtotime( $analysis->relance_2_at ) <= strtotime( '-48 hours' ) ) {
        $this->nad_send_relance_email( $analysis, 3 );
        $wpdb->update( $this->need_analysis_table, array( 'relance_3_at' => $now, 'updated_at' => $now ), array( 'id' => (int) $analysis->id ) );
      }
    }

    // ACDC 3.21.29-hotfix4b — Expiration automatique : passer à 'expire' les analyses non soumises
    // dont le token_expire_at est dépassé (token 30 jours).
    $wpdb->query(
      "UPDATE {$this->need_analysis_table}
       SET statut = 'expire', updated_at = '{$now}'
       WHERE is_model = 0
         AND statut NOT IN ('traite','archive','expire')
         AND token_expire_at IS NOT NULL
         AND token_expire_at < '{$now}'"
    );
  }

  /**
   * ACDC 3.25.210 — L'adresse du destinataire se RELIT sur la fiche apprenant.
   *
   * L'analyse du besoin recopiait l'adresse au moment de sa création, et ne la
   * relisait plus jamais. Corriger l'e-mail dans la fiche de l'apprenant ne
   * changeait donc rien : les relances repartaient à l'ancienne adresse, sans
   * aucun moyen de les rediriger. C'est la même erreur que le moteur du
   * workflow évite par construction — ne pas mémoriser ce que l'on peut relire.
   *
   * Le rattrapage vaut pour l'APPRENANT seulement, et c'est délibéré : la fiche
   * d'un apprenant porte une adresse et une seule, celle de la personne
   * désignée. Pour un commanditaire, le répondant est une personne nommée dans
   * l'entreprise, et l'adresse générique de l'entreprise n'est pas la sienne :
   * la relire reviendrait à réexpédier son courrier à l'accueil. La copie
   * enregistrée reste donc la référence de ce côté-là.
   *
   * La correction est aussi ÉCRITE sur la fiche d'analyse : sans cela, l'écran
   * continuerait d'afficher l'ancienne adresse tout en envoyant à la nouvelle,
   * et l'on remplacerait un défaut par un mensonge.
   *
   * @return array{0:string,1:string} l'adresse et le prénom retenus.
   */
  private function nad_resolve_recipient( $analysis ) {
    $email  = ! empty( $analysis->repondant_email ) ? (string) $analysis->repondant_email : '';
    $prenom = ! empty( $analysis->repondant_prenom ) ? (string) $analysis->repondant_prenom : ( ! empty( $analysis->repondant_nom ) ? (string) $analysis->repondant_nom : '' );

    $learner_id = isset( $analysis->apprenant_id ) ? (int) $analysis->apprenant_id : 0;
    if ( $learner_id <= 0 ) {
      return array( $email, $prenom );
    }

    $learner = $this->get_learner( $learner_id );
    if ( ! $learner || ! is_email( (string) $learner->email ) ) {
      return array( $email, $prenom );
    }

    $fresh = sanitize_email( (string) $learner->email );
    if ( 0 !== strcasecmp( $fresh, $email ) ) {
      global $wpdb;
      $wpdb->update(
        $this->need_analysis_table,
        array( 'repondant_email' => $fresh, 'updated_at' => current_time( 'mysql' ) ),
        array( 'id' => (int) $analysis->id )
      );
      $this->insert_system_log( array(
        'log_level'   => 'info',
        'event_type'  => 'nad_recipient_refreshed',
        'action_key'  => 'need_analysis',
        'object_type' => 'need_analysis',
        'object_id'   => (int) $analysis->id,
        'message'     => 'Adresse du répondant realignée sur la fiche apprenant.',
        'context_json' => array( 'apprenant_id' => $learner_id ),
      ) );
      $email = $fresh;
    }

    if ( '' !== trim( (string) $learner->first_name ) ) {
      $prenom = (string) $learner->first_name;
    }

    return array( $email, $prenom );
  }

  /**
   * ACDC 3.25.211 — Le nom qui désigne une analyse du besoin.
   *
   * Quatre notifications se sont succédé pour un même dossier, dont deux
   * portant exactement « Bérengère Valeriano » : elle est à la fois la
   * signataire de Skill Conseil et une apprenante de la formation. Deux
   * analyses distinctes, deux réponses distinctes, un seul intitulé — rien ne
   * permettait de savoir laquelle venait d'arriver.
   *
   * Deux choses manquaient, et il faut les deux :
   *   — L'ENTREPRISE. Dès qu'un commanditaire est identifié, c'est lui qui
   *     nomme le dossier ; la personne vient ensuite, « à l'attention de ».
   *     C'est la règle que David a fixée, et c'est aussi la convention du
   *     courrier professionnel.
   *   — LE RÔLE. Il ne suffit pas d'ajouter l'entreprise : les deux analyses de
   *     Bérengère porteraient alors le même « Skill Conseil — à l'attention de
   *     Bérengère Valeriano ». Ce qui les sépare n'est pas la personne, c'est
   *     la qualité en laquelle elle a répondu.
   */
  private function nad_analysis_display_label( $analysis ) {
    $person = trim( (string) ( $analysis->repondant_prenom ?? '' ) . ' ' . (string) ( $analysis->repondant_nom ?? '' ) );

    /* ACDC 3.25.260 — Même cascade que le courrier et le formulaire : l'écran
       ne peut pas nommer l'entreprise moins bien que l'e-mail. */
    $company = $this->nad_analysis_company_name( $analysis );

    $profil = strtolower( (string) ( $analysis->profil ?? '' ) );
    $role   = ( 'entreprise' === $profil || 'independant' === $profil ) ? 'commanditaire' : 'apprenant';

    if ( '' === $person && '' === $company ) {
      return 'Répondant inconnu';
    }
    if ( '' === $company ) {
      return $person . ' (' . $role . ')';
    }
    if ( '' === $person ) {
      return $company;
    }
    return $company . ' — à l’attention de ' . $person . ' (' . $role . ')';
  }

  /**
   * ACDC 3.25.224 — L'entreprise nomme le courrier, comme elle nomme l'écran.
   *
   * La 3.25.211 avait fait entrer la raison sociale dans les intitulés des
   * analyses, à l'écran. Les e-mails, eux, étaient restés anonymes : « Un petit
   * rappel pour votre analyse du besoin », sans un mot sur le dossier concerné.
   * Une signataire qui reçoit deux relances — l'une comme commanditaire de son
   * entreprise, l'autre comme apprenante — se retrouve avec deux messages
   * rigoureusement identiques dans sa boîte, et n'a aucun moyen de savoir
   * lequel répond à quoi. C'est la même faute que celle corrigée à l'écran,
   * commise dans le canal où elle coûte le plus cher.
   *
   * @return array{0:string,1:string} Le préfixe d'objet, et la mention
   *                                  « à l'attention de » quand elle a un sens.
   */
  /**
   * ACDC 3.25.260 — LA RAISON SOCIALE D'UNE ANALYSE, EN UN SEUL ENDROIT.
   *
   * Cette cascade existait, écrite au fil de trois versions, mais À
   * L'INTÉRIEUR de la composition des e-mails. Le formulaire, lui, ne lisait
   * que `entreprise.name` du préremplissage — vide dès que l'analyse vient
   * d'un prospect, puisqu'une analyse issue d'un prospect ne porte pas
   * d'entreprise_id. D'où l'accueil « Bonjour Valeriano 👋 » au lieu de
   * « Bonjour Skill Conseil 👋 » : l'écran retombait sur le prénom du
   * répondant faute d'avoir cherché plus loin que la première source.
   *
   * Trois sources, dans l'ordre où elles font autorité : la fiche entreprise
   * rattachée, le prospect d'origine, le dossier d'inscription. La fonction
   * rend une chaîne vide quand aucune ne répond — pas un nom inventé.
   */
  private function nad_analysis_company_name( $analysis ) {
    if ( ! $analysis ) {
      return '';
    }
    global $wpdb;
    $company = '';

    if ( ! empty( $analysis->entreprise_id ) ) {
      $row = $this->get_company( (int) $analysis->entreprise_id );
      if ( $row && ! empty( $row->name ) ) {
        $company = trim( (string) $row->name );
      }
    }

    /* ACDC 3.25.226 — Une analyse issue d'un PROSPECT ne porte pas
       d'`entreprise_id`, seulement un `source_id`. On interroge le prospect
       avant de renoncer. */
    if ( '' === $company && ! empty( $analysis->source_id ) && 'prospect' === (string) ( $analysis->source_type ?? '' ) ) {
      $prospect = $wpdb->get_row( $wpdb->prepare(
        "SELECT company_name FROM {$this->prospect_table} WHERE id = %d",
        (int) $analysis->source_id
      ) );
      if ( $prospect && ! empty( $prospect->company_name ) ) {
        $company = trim( (string) $prospect->company_name );
      }
    }

    /* Dernier recours : le dossier d'inscription qui a déclenché l'analyse. */
    if ( '' === $company && ! empty( $analysis->dossier_id ) ) {
      $contract = $wpdb->get_row( $wpdb->prepare(
        "SELECT company_id FROM {$this->registration_contract_table} WHERE id = %d",
        (int) $analysis->dossier_id
      ) );
      if ( $contract && ! empty( $contract->company_id ) ) {
        $row = $this->get_company( (int) $contract->company_id );
        if ( $row && ! empty( $row->name ) ) {
          $company = trim( (string) $row->name );
        }
      }
    }

    return $company;
  }

  /**
   * ACDC 3.25.263 — QUI L'ANALYSE DU BESOIN SALUE-T-ELLE ?
   *
   * La règle est celle de la 3.21.29 : pour un commanditaire entreprise, c'est
   * l'ENTITÉ qui est destinataire, pas la personne qui tient le clavier. La
   * 3.25.260 a corrigé sa source — une analyse issue d'un prospect n'a pas
   * d'entreprise_id, il faut aller chercher plus loin — mais elle ne l'a
   * corrigée QU'À UN ENDROIT : l'en-tête du formulaire.
   *
   * Les deux écrans de remerciement, eux, lisaient encore le prénom du
   * répondant. D'où « Bonjour Skill Conseil 👋 » en haut de page et « Merci
   * Valeriano ! » à la fin — deux réponses différentes à la même question,
   * dans le même document. C'est la panne la plus fréquente de ce plugin, et
   * elle se reproduit chaque fois qu'une règle est recopiée au lieu d'être
   * appelée.
   *
   * Un seul endroit, désormais, pour trois écrans.
   *
   * @param object     $analysis L'analyse du besoin.
   * @param array|null $prefill  Valeurs de préremplissage déjà calculées, si on les a.
   * @return string Le nom à saluer ; le prénom du répondant à défaut.
   */
  private function nad_greeting_name( $analysis, $prefill = null ) {
    if ( ! $analysis ) {
      return '';
    }
    $prenom = ! empty( $analysis->repondant_prenom ) ? trim( (string) $analysis->repondant_prenom ) : '';
    $profil = strtolower( (string) ( $analysis->profil ?? '' ) );

    if ( 'entreprise' !== $profil && 'independant' !== $profil ) {
      return $prenom;
    }

    $entreprise = ( is_array( $prefill ) && ! empty( $prefill['entreprise.name'] ) )
      ? trim( (string) $prefill['entreprise.name'] )
      : '';
    if ( '' === $entreprise ) {
      $entreprise = $this->nad_analysis_company_name( $analysis );
    }
    return '' !== $entreprise ? $entreprise : $prenom;
  }

  private function nad_email_identity( $analysis ) {
    $company = $this->nad_analysis_company_name( $analysis );

    if ( '' === $company ) {
      return array( '', '', '' );
    }

    $person  = trim( (string) ( $analysis->repondant_prenom ?? '' ) . ' ' . (string) ( $analysis->repondant_nom ?? '' ) );

    /* ACDC 3.25.228 — « Skill Conseil — à l'attention de Skill Conseil — à
       l'attention de Bérengère Valeriano ». La 3.25.226 a bien empêché le
       libellé d'affichage de se déverser dans les colonnes de nom, mais elle
       n'a pas nettoyé les analyses DÉJÀ créées : leur `repondant_nom` contient
       encore la chaîne entière, et le gabarit la repréfixait. On la démonte
       donc aussi à la lecture — c'est de toute façon la seule défense qui
       vaille pour une donnée que trois versions ont pu polluer. */
    if ( false !== mb_strpos( $person, 'attention de' ) ) {
      $split = preg_split( '/\s*—?\s*à l[’\']attention de\s*/u', $person );
      if ( is_array( $split ) && ! empty( $split ) ) {
        $person = trim( (string) end( $split ) );
      }
    }

    $profil  = strtolower( (string) ( $analysis->profil ?? '' ) );
    $role    = ( 'entreprise' === $profil || 'independant' === $profil ) ? 'commanditaire' : 'apprenant';

    /* La personne ne peut pas être l'entreprise elle-même : sur un dossier où
       les deux se confondent, « à l'attention de Skill Conseil » n'apprend
       rien et se lit comme une erreur. */
    if ( '' !== $person && 0 === strcasecmp( $person, $company ) ) {
      $person = '';
    }

    $attention = '';
    if ( '' !== $person ) {
      $attention = '<p style="font-size:14px;color:#6b7280;margin:0 0 14px;">'
                 . esc_html( $company ) . ' — à l’attention de ' . esc_html( $person )
                 . ' (' . esc_html( $role ) . ')</p>';
    }

    /* ACDC 3.25.231 — DEUX ANALYSES, UN SEUL OBJET.
       La recette l'a mesuré : une signataire qui est aussi apprenante reçoit
       deux e-mails dont l'objet est rigoureusement identique, et rien dans sa
       boîte ne lui dit lequel répond à quoi. L'écran, lui, les sépare par une
       colonne Profil — mais on ne lit pas ses e-mails dans l'écran.
       La qualité entre donc dans le préfixe d'objet. C'est le seul endroit qui
       compte : le corps, lui, était déjà correct. */
    $prefix = $company;
    if ( '' !== $person ) {
      $prefix .= ' (' . $role . ' : ' . $person . ')';
    }

    return array( $prefix . ' — ', $attention, $company );
  }

  /** Envoi initial de l'analyse du besoin (appelé par le cron si délai > 0). */
  private function nad_send_initial_email( $analysis ) {
    list( $email, $prenom ) = $this->nad_resolve_recipient( $analysis );
    if ( ! is_email( $email ) || empty( $analysis->token_public ) ) { return; }
    $form_url   = $this->nad_get_public_form_url( (string) $analysis->token_public );
    $is_cmd     = ( 'entreprise' === ( $analysis->profil ?? '' ) || 'independant' === ( $analysis->profil ?? '' ) );
    $fake_contract = null;
    if ( ! empty( $analysis->dossier_id ) ) {
      global $wpdb;
      $fake_contract = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->registration_contract_table} WHERE id = %d", (int) $analysis->dossier_id ) );
    }
    $identity = $this->nad_email_identity( $analysis );
    if ( $is_cmd && ! empty( $identity[2] ) ) {
      $prenom = (string) $identity[2];
    }
    if ( $is_cmd ) {
      $this->nad_send_email_commanditaire( $email, $prenom, (string) ( $analysis->title ?? '' ), $form_url, $fake_contract, $identity );
    } else {
      $this->nad_send_email_apprenant( $email, $prenom, (string) ( $analysis->title ?? '' ), $form_url, $fake_contract, $identity );
    }
  }

  /**
   * Envoi d'une relance (numéro 1, 2 ou 3).
   *
   * ACDC 3.25.228 — CETTE FONCTION NE DISAIT PAS SI ELLE AVAIT ENVOYÉ.
   * Elle sortait silencieusement quand l'adresse était invalide ou le jeton
   * absent, et le mode recette pouvait refuser le destinataire sans qu'elle
   * en sache rien. L'appelant, lui, annonçait « Relance N envoyée à … » dans
   * tous les cas. La recette a mis le doigt dessus : un avis de succès, zéro
   * e-mail dans l'archive. Un écran qui ment sur un envoi est pire qu'un écran
   * muet — on ne cherche pas ce qu'on croit avoir fait.
   *
   * @return bool Vrai si l'e-mail est réellement parti.
   */
  private function nad_send_relance_email( $analysis, $num ) {
    /* Une relance part à l'adresse d'AUJOURD'HUI, pas à celle du jour de la
       création : c'est précisément quand la première n'est pas arrivée que
       l'adresse a été corrigée entre-temps. */
    list( $email, $prenom ) = $this->nad_resolve_recipient( $analysis );
    if ( ! is_email( $email ) || empty( $analysis->token_public ) ) { return false; }

    $form_url = $this->nad_get_public_form_url( (string) $analysis->token_public );
    $cta = '<div style="text-align:center;margin:32px 0;">'
         . '<a href="' . esc_url( $form_url ) . '" style="display:inline-block;background:#d6a353;color:#fff;font-weight:700;font-size:17px;padding:15px 36px;border-radius:10px;text-decoration:none;">Remplir mon analyse du besoin</a>'
         . '</div>';

    $intros = array(
      1 => '<p style="font-size:16px;line-height:1.7;">Nous espérons que vous allez bien !</p>'
         . '<p style="font-size:16px;line-height:1.7;">Nous nous permettons de vous rappeler gentiment que votre <strong>analyse du besoin de formation</strong> est en attente de votre réponse. Elle ne prend que quelques minutes et nous permettra de préparer une expérience d\'apprentissage vraiment adaptée à vos besoins.</p>',
      2 => '<p style="font-size:16px;line-height:1.7;">Nous revenons vers vous avec toute notre bienveillance 🙂</p>'
         . '<p style="font-size:16px;line-height:1.7;">Votre <strong>analyse du besoin</strong> est toujours disponible. Nous comprenons que votre agenda soit chargé — mais même cinq minutes suffisent pour nous aider à personnaliser votre parcours de formation.</p>',
      3 => '<p style="font-size:16px;line-height:1.7;">C\'est notre dernier petit rappel, promis ! 😊</p>'
         . '<p style="font-size:16px;line-height:1.7;">Si vous n\'avez pas encore eu le temps de remplir votre <strong>analyse du besoin</strong>, ce lien reste accessible. N\'hésitez pas à nous contacter si vous avez la moindre question ou si vous souhaitez que nous la remplissions ensemble.</p>',
    );

    $subjects = array(
      1 => 'Un petit rappel pour votre analyse du besoin',
      2 => 'Votre analyse du besoin vous attend encore',
      3 => 'Dernier rappel — votre analyse du besoin',
    );

    list( $prefix, $attention, $company_name ) = array_pad( $this->nad_email_identity( $analysis ), 3, '' );

    /* ACDC 3.25.226 — « Bonjour Skill, » : la salutation prenait le premier mot
       d'un libellé d'entreprise traité comme un prénom. Quand le destinataire
       EST une entreprise, on la salue par sa raison sociale entière ; la
       personne, elle, est nommée juste en dessous par la mention « à
       l'attention de ». */
    $profil_lc = strtolower( (string) ( $analysis->profil ?? '' ) );
    if ( '' !== $company_name && in_array( $profil_lc, array( 'entreprise', 'independant' ), true ) ) {
      $prenom = $company_name;
    }

    $branding = $this->acdc_get_transactional_email_branding();
    return (bool) $this->acdc_send_transactional_email(
      $email,
      $prefix . $subjects[ $num ],
      array(
        'greeting_name' => $prenom,
        'intro_html'    => $attention . $intros[ $num ],
        'body_html'     => $cta . '<p style="font-size:13px;color:#888;text-align:center;">Ce lien est personnel et valable 30 jours après la signature de votre convention.</p>',
        'footer_notice' => 'Cet e-mail a été envoyé dans le cadre de votre inscription en formation. Vos données sont traitées conformément au RGPD.',
      ),
      array( 'source_module' => 'analyse_besoin', 'source_action' => 'relance_' . $num )
    );
  }

  /** Compte les analyses en attente (envoyées, non répondues). */
  private function nad_count_pending_analyses() {
    global $wpdb;
    return (int) $wpdb->get_var(
      "SELECT COUNT(*) FROM {$this->need_analysis_table}
       WHERE is_model = 0
         AND sent_at IS NOT NULL
         AND statut NOT IN ('traite','archive','expire')"
    );
  }

  /** Données complètes pour la page dashboard analyses en alerte. */
  private function nad_get_alert_analyses() {
    global $wpdb;
    return $wpdb->get_results(
      "SELECT * FROM {$this->need_analysis_table}
       WHERE is_model = 0
         AND sent_at IS NOT NULL
         AND statut NOT IN ('traite','archive','expire')
       ORDER BY sent_at ASC"
    );
  }

    /** Génère un token sécurisé unique. */
  private function nad_generate_token() {
    $token = '';
    if ( function_exists( 'random_bytes' ) ) {
      $token = bin2hex( random_bytes( 32 ) );
    } else {
      $token = md5( uniqid( wp_rand(), true ) . microtime( true ) );
    }
    return $token;
  }

  /** URL publique du formulaire pour un token donné. */
  private function nad_get_public_form_url( $token ) {
    $page_id = (int) get_option( 'acdc_of_nad_public_page_id', 0 );
    $base    = $page_id ? get_permalink( $page_id ) : home_url( '/acdc-analyse-besoin/' );
    return add_query_arg( 'adb_token', rawurlencode( $token ), $base );
  }

  /** Sélection automatique des blocs selon le profil et la thématique. */
  private function nad_auto_select_blocs( $profil, $thematique = '' ) {
    $ids = array();
    // 1. Blocs système par code fixe (demarrage, commun, profil, thematique)
    foreach ( array( 'demarrage', 'commun', $profil, $thematique ) as $code ) {
      if ( empty( $code ) ) { continue; }
      $bloc = $this->get_need_block_by_code( $code );
      if ( $bloc && (int) $bloc->actif ) {
        $nb = $this->get_need_questions_count_by_bloc( (int) $bloc->id );
        if ( $nb > 0 ) { $ids[] = (int) $bloc->id; }
      }
    }
    // 2. Blocs personnalisés (non-verrouillés) correspondant au profil ou à la thématique
    // ACDC 3.21.29-hotfix4 — inclure les blocs ajoutés manuellement par l'utilisateur.
    global $wpdb;
    $custom_blocs = $wpdb->get_results( "SELECT * FROM {$this->need_block_table} WHERE verrouille = 0 AND actif = 1 ORDER BY ordre ASC, id ASC" );
    foreach ( $custom_blocs as $cb ) {
      // Inclure si le bloc est universel (pas de restriction) ou correspond au profil/thématique
      $match_profil = ( empty( $cb->profil_lie ) || $cb->profil_lie === $profil );
      $match_them   = ( empty( $cb->thematique_liee ) || ( ! empty( $thematique ) && $cb->thematique_liee === $thematique ) );
      if ( $match_profil && $match_them ) {
        $nb = $this->get_need_questions_count_by_bloc( (int) $cb->id );
        if ( $nb > 0 ) { $ids[] = (int) $cb->id; }
      }
    }
    return array_values( array_unique( $ids ) );
  }

  /** Détecte la thématique d'une formation depuis son specialty ou title. */
  private function nad_detect_thematique_from_formation( $formation ) {
    if ( ! $formation ) { return ''; }
    $text = strtolower( ( $formation->specialty ?? '' ) . ' ' . ( $formation->title ?? '' ) . ' ' . ( $formation->description_text ?? '' ) );
    // ACDC 3.21.13-hotfix2 — Spécifique avant générique (restauration avant management)
    $map = array(
      'ia'                     => array( 'intelligence artificielle', 'chatgpt', 'ia générative', 'ia generat', 'llm', 'prompt' ),
      'automatisation_nocode'  => array( 'automatisation', 'no-code', 'nocode', 'make', 'zapier', 'n8n', 'airtable' ),
      'marketing_wordpress'    => array( 'marketing digital', 'wordpress', 'réseaux sociaux', 'seo', 'linkedin', 'instagram' ),
      'management_restauration'=> array( 'restauration', 'restaurant', 'hôtel', 'hotelier', 'hôtellerie', 'management en restauration', 'manager restaur' ),
      'hygiene_alimentaire'    => array( 'hygiène', 'haccp', 'alimentaire', 'sanitaire' ),
      'management_leadership'  => array( 'management', 'leadership', 'manager', 'encadrement' ),
      'softskills'             => array( 'soft skill', 'communication', 'confiance', 'gestion du stress', 'intelligence émotionnelle' ),
    );
    foreach ( $map as $code => $keywords ) {
      foreach ( $keywords as $kw ) {
        if ( false !== strpos( $text, $kw ) ) {
          return $code;
        }
      }
    }
    return '';
  }

  /**
   * Créé automatiquement les analyses du besoin après signature d'une convention.
   * Appelé via acdc_sig_request_signed (prio 20).
   */
  public function nad_auto_create_from_contract( $contract, $sig_request = null ) {
    global $wpdb;
    if ( ! $contract ) { return; }

    $contract_id  = (int) $contract->id;
    $formation_id = ! empty( $contract->formation_id ) ? (int) $contract->formation_id : 0;
    $company_id   = ! empty( $contract->company_id )   ? (int) $contract->company_id   : 0;
    $now          = current_time( 'mysql' );
    // ACDC 3.21.17 — Délai configurable avant envoi
    $send_delay   = ! empty( $contract->nad_send_delay_days ) ? (int) $contract->nad_send_delay_days : 0;
    // ACDC 3.21.29-hotfix4b — Utiliser current_time() pour respecter le fuseau WordPress (interdit date()).
    $scheduled_at = current_time( 'timestamp' );
    if ( $send_delay > 0 ) { $scheduled_at = strtotime( '+' . $send_delay . ' days', $scheduled_at ); }
    $scheduled_at = date( 'Y-m-d H:i:s', $scheduled_at );
    $expire_at    = date( 'Y-m-d H:i:s', strtotime( '+' . ( 30 + $send_delay ) . ' days', current_time( 'timestamp' ) ) );

    // Charger la formation pour détecter la thématique
    $formation    = $formation_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->formation_table} WHERE id = %d", $formation_id ) ) : null;
    // ACDC 3.21.14 — Thématique depuis colonne dédiée (priorité) ou détection textuelle (fallback)
    $thematique = '';
    if ( $formation ) {
      $thematique = ! empty( $formation->thematique ) ? (string) $formation->thematique : $this->nad_detect_thematique_from_formation( $formation );
    }
    $form_title   = $formation ? (string) $formation->title : ( ! empty( $contract->formation_title ) ? (string) $contract->formation_title : '' );

    // ---- 1. Analyse commanditaire ----
    // ACDC 3.21.13-hotfix1 — Mapping commanditaire_type → profil bloc (5 profils)
    $cmd_type_map = array(
      'Entreprise'  => 'entreprise',
      'Indépendant' => 'independant',
      'Particulier' => 'particulier',
      'Apprenant'   => 'apprenant',
      'Salarié'     => 'salarie',
    );
    $raw_cmd_type = ! empty( $contract->commanditaire_type ) ? (string) $contract->commanditaire_type : 'Entreprise';
    $cmd_profil   = isset( $cmd_type_map[ $raw_cmd_type ] ) ? $cmd_type_map[ $raw_cmd_type ] : 'entreprise';
    $cmd_blocs  = $this->nad_auto_select_blocs( $cmd_profil, $thematique );
    $cmd_token  = $this->nad_generate_token();

    // ACDC 3.21.13-hotfix4 — signer_email = email exact du signataire (champ direct sur la demande)
    $cmd_nom     = ''; $cmd_prenom = ''; $cmd_email = ''; $cmd_company_hint = '';
    if ( $sig_request && ! empty( $sig_request->signer_email ) && is_email( $sig_request->signer_email ) ) {
      $cmd_email = sanitize_email( (string) $sig_request->signer_email );
    }
    if ( $sig_request && ! empty( $sig_request->signer_name ) ) {
      /* ACDC 3.25.226 — RÉGRESSION QUE J'AI INTRODUITE EN 3.25.225.
         En imposant « Raison sociale — à l'attention de Prénom Nom » comme
         libellé d'affichage du prospect, j'ai laissé ce libellé se déverser
         dans un champ qui attend « Prénom Nom ». La découpe sur le premier
         espace donnait alors prénom « Skill » et nom « Conseil — à l'attention
         de Bérengère Valeriano », d'où le « Bonjour Skill, » relevé en recette.
         Une chaîne destinée à l'œil n'est pas une donnée : on la démonte avant
         de s'en servir. La partie avant le séparateur est la raison sociale,
         celle qui suit est la personne. */
      $raw_signer = trim( (string) $sig_request->signer_name );
      if ( false !== mb_strpos( $raw_signer, 'à l’attention de' ) || false !== mb_strpos( $raw_signer, "à l'attention de" ) ) {
        $split = preg_split( '/\s*—\s*à l[’\']attention de\s*/u', $raw_signer, 2 );
        if ( is_array( $split ) && count( $split ) === 2 ) {
          $cmd_company_hint = trim( (string) $split[0] );
          $raw_signer       = trim( (string) $split[1] );
        }
      }
      $parts = explode( ' ', $raw_signer, 2 );
      $cmd_prenom = $parts[0] ?? '';
      $cmd_nom    = $parts[1] ?? '';
    }

    // Récupérer contact commanditaire — résolution avec fallbacks multiples
    $is_individual_cmd = in_array( $cmd_profil, array( 'particulier', 'apprenant', 'salarie' ), true );

    if ( ! $is_individual_cmd && $company_id ) {
      // Entreprise / Indépendant → signataire de la société
      $company = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->company_table} WHERE id = %d", $company_id ) );
      if ( $company ) {
        // Nom : signataire ou raison sociale
        $cmd_nom    = ! empty( $company->signer_last_name )  ? (string) $company->signer_last_name  : (string) $company->name;
        $cmd_prenom = ! empty( $company->signer_first_name ) ? (string) $company->signer_first_name : '';
        // Email : email société → email signataire → email contact principal
        if ( ! empty( $company->email ) && is_email( $company->email ) ) {
          $cmd_email = (string) $company->email;
        } elseif ( ! empty( $company->signer_email ) && is_email( $company->signer_email ) ) {
          $cmd_email = (string) $company->signer_email;
        }
      }
    }
    // Compléter / corriger depuis le prospect (source fiable pour personnes physiques + fallback entreprise)
    if ( ! empty( $contract->source_prospect_id ) ) {
      $prospect = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->prospect_table} WHERE id = %d", (int) $contract->source_prospect_id ) );
      if ( $prospect ) {
        if ( $is_individual_cmd ) {
          // Particulier / Salarié / Apprenant → toujours utiliser le prospect
          $cmd_nom    = (string) $prospect->last_name;
          $cmd_prenom = (string) $prospect->first_name;
          $cmd_email  = (string) $prospect->email;
        } elseif ( '' === $cmd_email && ! empty( $prospect->email ) && is_email( $prospect->email ) ) {
          // Entreprise sans email → fallback prospect
          $cmd_email = (string) $prospect->email;
          if ( '' === $cmd_nom ) { $cmd_nom = (string) $prospect->last_name; $cmd_prenom = (string) $prospect->first_name; }
        }
        if ( ! $company_id ) {
          $company_id = $this->acdc_find_company_id_from_prospect( $prospect );
        }
      }
    }
    // Fallback final : email signataire convention (champ commanditaire_first_name/last_name si renseigné)
    if ( '' === $cmd_email ) {
      $cmd_signer_email = $wpdb->get_var( $wpdb->prepare(
        "SELECT commanditaire_signer_email FROM {$this->registration_contract_table} WHERE id = %d",
        $contract_id
      ) );
      if ( ! empty( $cmd_signer_email ) && is_email( $cmd_signer_email ) ) {
        $cmd_email = (string) $cmd_signer_email;
      }
    }

    $send_now = ( 0 === $send_delay );
    // ACDC 3.21.29-hotfix4 — Nom du commanditaire dans le titre (société ou personne).
    $cmd_label_title = 'Commanditaire';
    if ( ! $is_individual_cmd && ! empty( $company ) && ! empty( $company->name ) ) {
      $cmd_label_title = (string) $company->name;
    } elseif ( $cmd_nom || $cmd_prenom ) {
      $cmd_label_title = trim( $cmd_prenom . ' ' . $cmd_nom );
    }
    $wpdb->insert( $this->need_analysis_table, array(
      'title'            => 'Analyse du besoin — ' . $cmd_label_title . ( $form_title ? ' — ' . $form_title : '' ),
      'description_text' => '',
      'analysis_type'    => ucfirst( $cmd_profil ),
      'blocks_json'      => wp_json_encode( array() ),
      'is_model'         => 0,
      'profil'           => $cmd_profil,
      'thematique'       => $thematique,
      'statut'           => 'nouveau',
      'source_type'      => $company_id ? 'entreprise' : ( ! empty( $contract->source_prospect_id ) ? 'prospect' : null ),
      'source_id'        => $company_id ?: ( ! empty( $contract->source_prospect_id ) ? (int) $contract->source_prospect_id : null ),
      'entreprise_id'    => $company_id ?: null,
      'formation_id'     => $formation_id ?: null,
      'dossier_id'       => $contract_id,
      'repondant_nom'    => $cmd_nom,
      'repondant_prenom' => $cmd_prenom,
      'repondant_email'  => $cmd_email,
      'blocs_ids'        => wp_json_encode( $cmd_blocs ),
      'token_public'     => $cmd_token,
      'token_expire_at'   => $expire_at,
      'scheduled_send_at' => $scheduled_at,
      'sent_at'           => null,
      'created_at'        => $now,
      'updated_at'        => $now,
    ) );
    $cmd_analysis_id = (int) $wpdb->insert_id;

    // Envoyer email commanditaire immédiatement si délai = 0
    if ( $cmd_analysis_id && $send_now && is_email( $cmd_email ) ) {
      $this->nad_send_email_commanditaire(
        $cmd_email,
        $cmd_prenom ?: $cmd_nom,
        $form_title,
        $this->nad_get_public_form_url( $cmd_token ),
        $contract,
        $this->nad_email_identity( (object) array(
          'entreprise_id'    => $company_id,
          'repondant_prenom' => $cmd_prenom,
          'repondant_nom'    => $cmd_nom,
          'profil'           => $cmd_profil,
        ) )
      );
      $wpdb->update( $this->need_analysis_table, array( 'sent_at' => $now ), array( 'id' => $cmd_analysis_id ) );
    }

    // ---- 2. Analyses par apprenant ----
    // ACDC 3.21.13-hotfix3 — learner_ids : JSON et CSV (les deux formats existent)
    $learner_ids = array();
    if ( ! empty( $contract->learner_ids ) ) {
      $raw_lids = (string) $contract->learner_ids;
      $decoded  = json_decode( $raw_lids, true );
      if ( is_array( $decoded ) ) {
        // Format JSON : [{"id":1,...}, 2, ...]
        foreach ( $decoded as $entry ) {
          $lid = is_array( $entry ) && isset( $entry['id'] ) ? absint( $entry['id'] ) : absint( $entry );
          if ( $lid ) { $learner_ids[] = $lid; }
        }
      } else {
        // Format CSV : "1,2,3,4"
        foreach ( explode( ',', $raw_lids ) as $raw_id ) {
          $lid = absint( trim( $raw_id ) );
          if ( $lid ) { $learner_ids[] = $lid; }
        }
      }
    }
    $learner_ids = array_values( array_unique( array_filter( $learner_ids ) ) );

    // Notice admin : résumé création analyses
    $nad_notice = sprintf(
      'Analyses du besoin créées depuis la convention #%d : 1 commanditaire%s%s.',
      $contract_id,
      ! empty( $learner_ids ) ? ' + ' . count( $learner_ids ) . ' apprenant(s)' : ' (aucun apprenant dans la convention)',
      '' === $cmd_email ? ' ⚠️ Email commanditaire introuvable — vérifiez la fiche entreprise/prospect' : ' — Emails envoyés'
    );
    set_transient( 'acdc_nad_creation_notice_' . $contract_id, $nad_notice, 3600 );

    foreach ( $learner_ids as $learner_id ) {
      $learner = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->learner_table} WHERE id = %d", $learner_id ) );
      if ( ! $learner ) { continue; }

      $app_blocs = $this->nad_auto_select_blocs( 'apprenant', $thematique );
      $app_token = $this->nad_generate_token();

      $wpdb->insert( $this->need_analysis_table, array(
        'title'            => 'Analyse du besoin — ' . trim( $learner->first_name . ' ' . $learner->last_name ) . ( $form_title ? ' — '  . $form_title : '' ),
        'description_text' => '',
        'analysis_type'    => 'Apprenant',
        'blocks_json'      => wp_json_encode( array() ),
        'is_model'         => 0,
        'profil'           => 'apprenant',
        'thematique'       => $thematique,
        'statut'           => 'nouveau',
        'source_type'      => 'apprenant',
        'source_id'        => $learner_id,
        'apprenant_id'     => $learner_id,
        'entreprise_id'    => $company_id ?: null,
        'formation_id'     => $formation_id ?: null,
        'dossier_id'       => $contract_id,
        'repondant_nom'    => (string) $learner->last_name,
        'repondant_prenom' => (string) $learner->first_name,
        'repondant_email'  => (string) $learner->email,
        'blocs_ids'        => wp_json_encode( $app_blocs ),
        'token_public'     => $app_token,
        'token_expire_at'   => $expire_at,
        'scheduled_send_at' => $scheduled_at,
        'sent_at'           => null,
        'created_at'        => $now,
        'updated_at'        => $now,
      ) );
      $app_analysis_id = (int) $wpdb->insert_id;

      if ( $app_analysis_id && $send_now && is_email( $learner->email ) ) {
        $this->nad_send_email_apprenant(
          $learner->email,
          (string) $learner->first_name,
          $form_title,
          $this->nad_get_public_form_url( $app_token ),
          $contract,
          $this->nad_email_identity( (object) array(
            'entreprise_id'    => $company_id,
            'repondant_prenom' => (string) $learner->first_name,
            'repondant_nom'    => (string) $learner->last_name,
            'profil'           => 'apprenant',
          ) )
        );
        $wpdb->update( $this->need_analysis_table, array( 'sent_at' => $now ), array( 'id' => $app_analysis_id ) );
      }
    }
  }

  /** Email au commanditaire avec lien vers le formulaire. */
  private function nad_send_email_commanditaire( $to, $prenom, $formation_title, $form_url, $contract, $identity = array( '', '' ) ) {
    list( $prefix, $attention ) = array_pad( (array) $identity, 2, '' );
    $subject = $prefix . 'Votre analyse du besoin — ' . ( $formation_title ?: 'Formation' );
    $cta     = '<div style="text-align:center;margin:32px 0;">'
             . '<a href="' . esc_url( $form_url ) . '" style="display:inline-block;background:#d6a353;color:#fff;font-weight:700;font-size:18px;padding:16px 40px;border-radius:10px;text-decoration:none;">Remplir l\'analyse du besoin</a>'
             . '</div>';
    $this->acdc_send_transactional_email(
      $to,
      $subject,
      array(
        'greeting_name' => $prenom,
        'intro_html'    => $attention
                         . '<p style="font-size:17px;line-height:1.7;">Dans le cadre de la convention de formation que vous avez signée, nous avons préparé une <strong>analyse du besoin</strong> personnalisée.</p>'
                         . '<p style="font-size:17px;line-height:1.7;">Cette analyse nous permettra d\'adapter le contenu de la formation à vos objectifs et à ceux de vos collaborateurs.</p>',
        'summary_title' => 'Formation concernée',
        'summary_rows'  => array(
          array( 'label' => 'Intitulé',   'value' => $formation_title ?: '—' ),
          array( 'label' => 'Date début', 'value' => ! empty( $contract->start_date ) ? wp_date( 'd/m/Y', strtotime( $contract->start_date ) ) : '—' ),
        ),
        'body_html'     => $cta
                         . '<p style="font-size:15px;color:#666;text-align:center;">Ce lien est valable 30 jours. Il vous est réservé, merci de ne pas le partager.</p>',
        'footer_notice' => 'Cet e-mail a été envoyé dans le cadre de votre convention de formation. Vos données sont traitées conformément au RGPD.',
      ),
      array( 'source_module' => 'analyse_besoin', 'source_action' => 'envoi_commanditaire' )
    );
  }

  /** Email à un apprenant avec lien vers le formulaire. */
  private function nad_send_email_apprenant( $to, $prenom, $formation_title, $form_url, $contract, $identity = array( '', '' ) ) {
    list( $prefix, $attention ) = array_pad( (array) $identity, 2, '' );
    $subject = $prefix . 'Votre analyse du besoin — ' . ( $formation_title ?: 'Formation' );
    $cta     = '<div style="text-align:center;margin:32px 0;">'
             . '<a href="' . esc_url( $form_url ) . '" style="display:inline-block;background:#d6a353;color:#fff;font-weight:700;font-size:18px;padding:16px 40px;border-radius:10px;text-decoration:none;">Remplir mon analyse du besoin</a>'
             . '</div>';
    $this->acdc_send_transactional_email(
      $to,
      $subject,
      array(
        'greeting_name' => $prenom,
        'intro_html'    => $attention
                         . '<p style="font-size:17px;line-height:1.7;">Vous allez prochainement participer à une formation et nous souhaitons vous offrir la <strong>meilleure expérience d\'apprentissage possible</strong>.</p>'
                         . '<p style="font-size:17px;line-height:1.7;">Pour cela, nous avons besoin de mieux vous connaître : vos objectifs, votre niveau actuel et vos attentes. Cela ne prendra que quelques minutes.</p>',
        'summary_title' => 'Formation concernée',
        'summary_rows'  => array(
          array( 'label' => 'Intitulé',   'value' => $formation_title ?: '—' ),
          array( 'label' => 'Date début', 'value' => ! empty( $contract->start_date ) ? wp_date( 'd/m/Y', strtotime( $contract->start_date ) ) : '—' ),
        ),
        'body_html'     => $cta
                         . '<p style="font-size:15px;color:#666;text-align:center;">Ce lien est personnel et valable 30 jours. Merci de ne pas le partager.</p>',
        'footer_notice' => 'Cet e-mail a été envoyé dans le cadre de votre inscription en formation. Vos données sont traitées conformément au RGPD.',
      ),
      array( 'source_module' => 'analyse_besoin', 'source_action' => 'envoi_apprenant' )
    );
  }


    /* =====================================================================
   * ACDC 3.21.15 — Seed + getters thématiques.
   * ===================================================================== */

  private function seed_thematiques() {
    global $wpdb;
    if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->thematique_table}" ) > 0 ) { return; }
    $now = current_time( 'mysql' );
    $systeme = array(
      array( 'code' => 'ia',                     'label' => 'Intelligence artificielle',    'icon_emoji' => '🤖', 'couleur_hex' => '#1e4777', 'image_hero_url' => 'https://acdcformation.com/wp-content/uploads/2026/05/IA-analyse-du-besoin.png',                       'ordre' => 10 ),
      array( 'code' => 'automatisation_nocode',  'label' => 'Automatisation & No-code',     'icon_emoji' => '⚙️', 'couleur_hex' => '#2d5a3d', 'image_hero_url' => 'https://acdcformation.com/wp-content/uploads/2026/05/No-code-analyse-du-besoin.png',                    'ordre' => 20 ),
      array( 'code' => 'marketing_wordpress',    'label' => 'Marketing digital & WordPress','icon_emoji' => '📣', 'couleur_hex' => '#5a2d82', 'image_hero_url' => 'https://acdcformation.com/wp-content/uploads/2026/05/Marketing-digital-analyse-du-besoin.png',         'ordre' => 30 ),
      array( 'code' => 'management_leadership',  'label' => 'Management & leadership',      'icon_emoji' => '🤝', 'couleur_hex' => '#1a3a5c', 'image_hero_url' => 'https://acdcformation.com/wp-content/uploads/2026/05/Management-analyse-du-besoin.png',                 'ordre' => 40 ),
      array( 'code' => 'management_restauration','label' => 'Management en restauration',   'icon_emoji' => '🍽️', 'couleur_hex' => '#3d1a0a', 'image_hero_url' => 'https://acdcformation.com/wp-content/uploads/2026/05/Management-restauration-analyse-du-besoin.png',    'ordre' => 50 ),
      array( 'code' => 'hygiene_alimentaire',    'label' => 'Hygiène alimentaire',          'icon_emoji' => '🧼', 'couleur_hex' => '#0a3d2d', 'image_hero_url' => 'https://acdcformation.com/wp-content/uploads/2026/05/Hygiene-alimentaire-analyse-du-besoin.png',       'ordre' => 60 ),
      array( 'code' => 'softskills',             'label' => 'Soft skills',                  'icon_emoji' => '💡', 'couleur_hex' => '#3d2d0a', 'image_hero_url' => 'https://acdcformation.com/wp-content/uploads/2026/05/Soft-skill-analyse-du-besoin.png',                 'ordre' => 70 ),
    );
    foreach ( $systeme as $t ) {
      $wpdb->insert( $this->thematique_table, array(
        'code'          => $t['code'],
        'label'         => $t['label'],
        'image_hero_url'=> $t['image_hero_url'],
        'icon_emoji'    => $t['icon_emoji'],
        'couleur_hex'   => $t['couleur_hex'],
        'bloc_nad_id'   => null,
        'ordre'         => $t['ordre'],
        'actif'         => 1,
        'verrouille'    => 1,
        'created_at'    => $now,
        'updated_at'    => $now,
      ) );
    }
  }

  private function get_thematiques( $only_active = true ) {
    global $wpdb;
    $where = $only_active ? 'WHERE actif = 1' : '';
    return $wpdb->get_results( "SELECT * FROM {$this->thematique_table} {$where} ORDER BY ordre ASC, id ASC" );
  }

  private function get_thematique_by_code( $code ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->thematique_table} WHERE code = %s LIMIT 1", $code ) );
  }

  /**
   * hotfix11 — Label d'option formation : Numéro Titre — Modalité — Prix
   * Numéro :
   *   - Formation de base  (base_formation_id = 0) → id seul       ex. "3"
   *   - Variante           (base_formation_id > 0) → parent.variante ex. "3.1"
   * Seuls les segments Modalité et Prix non vides sont ajoutés.
   * La valeur de l'option reste toujours l'id réel de la formation.
   */
  private function build_formation_option_label( $f ) {
    /* ACDC 3.25.277 — Le numéro, le nom et la modalité viennent de la règle
       commune ; le prix reste propre à ces deux écrans commerciaux, où il aide
       à choisir. */
    $parts = array( $this->format_formation_option_label( $f ) );
    if ( ! empty( $f->price_ht ) ) {
      $price = trim( (string) $f->price_ht );
      if ( strpos( $price, '€' ) === false && strpos( $price, 'EUR' ) === false ) {
        $price .= ' €';
      }
      $parts[] = $price;
    }
    return implode( ' — ', $parts );
  }

  private function get_thematiques_map( $only_active = true ) {
    $map = array();
    foreach ( $this->get_thematiques( $only_active ) as $t ) {
      $map[ $t->code ] = $t->label;
    }
    return $map;
  }

  private function get_thematiques_hero_map( $only_active = true ) {
    $map = array();
    foreach ( $this->get_thematiques( $only_active ) as $t ) {
      if ( ! empty( $t->image_hero_url ) ) {
        $map[ $t->code ] = $t->image_hero_url;
      }
    }
    return $map;
  }

    private function seed_need_blocks_and_questions() {
    global $wpdb;
    $bloc_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->need_block_table}" );
    if ( $bloc_count > 0 ) {
      // ACDC 3.21.29-hotfix4 — Vérifier que les blocs système ont bien des questions.
      // Si aucune question active n'existe pour les blocs verrouillés, forcer le re-seed.
      $locked_with_q = (int) $wpdb->get_var(
        "SELECT COUNT(DISTINCT b.id) FROM {$this->need_block_table} b
         INNER JOIN {$this->need_question_table} q ON q.bloc_id = b.id AND q.actif = 1
         WHERE b.verrouille = 1"
      );
      if ( $locked_with_q > 0 ) {
        return; // blocs système + questions OK
      }
      // Blocs existent mais sans questions → re-seed des blocs système uniquement
    }
    $this->do_seed_system_blocks();
  }

  /**
   * ACDC 3.21.16 — Seed restructuré (sans redondances).
   * Appelé au premier boot ET via le bouton "Réinitialiser les blocs système".
   */
  public function do_seed_system_blocks() {
    global $wpdb;
    $now = current_time( 'mysql' );

    // ---- Blocs système ----
    $blocs = array(
      array( 'code' => 'demarrage',              'nom' => 'Bloc de démarrage',                     'type_bloc' => 'commun',      'profil_lie' => null,          'thematique_liee' => null,                  'ordre' => 0 ),
      array( 'code' => 'commun',                 'nom' => 'Informations communes',                  'type_bloc' => 'commun',      'profil_lie' => null,          'thematique_liee' => null,                  'ordre' => 5 ),
      array( 'code' => 'entreprise',             'nom' => 'Profil — Entreprise',                   'type_bloc' => 'profil',      'profil_lie' => 'entreprise',  'thematique_liee' => null,                  'ordre' => 10 ),
      array( 'code' => 'salarie',                'nom' => 'Profil — Salarié',                      'type_bloc' => 'profil',      'profil_lie' => 'salarie',     'thematique_liee' => null,                  'ordre' => 20 ),
      array( 'code' => 'apprenant',              'nom' => 'Profil — Apprenant',                    'type_bloc' => 'profil',      'profil_lie' => 'apprenant',   'thematique_liee' => null,                  'ordre' => 30 ),
      array( 'code' => 'particulier',            'nom' => 'Profil — Particulier',                  'type_bloc' => 'profil',      'profil_lie' => 'particulier', 'thematique_liee' => null,                  'ordre' => 40 ),
      array( 'code' => 'independant',            'nom' => 'Profil — Indépendant',                  'type_bloc' => 'profil',      'profil_lie' => 'independant', 'thematique_liee' => null,                  'ordre' => 50 ),
      array( 'code' => 'ia',                     'nom' => 'Thématique — Intelligence artificielle', 'type_bloc' => 'thematique', 'profil_lie' => null,          'thematique_liee' => 'ia',                  'ordre' => 60 ),
      array( 'code' => 'automatisation_nocode',  'nom' => 'Thématique — Automatisation & No-code', 'type_bloc' => 'thematique', 'profil_lie' => null,          'thematique_liee' => 'automatisation_nocode','ordre' => 70 ),
      array( 'code' => 'marketing_wordpress',    'nom' => 'Thématique — Marketing digital',        'type_bloc' => 'thematique', 'profil_lie' => null,          'thematique_liee' => 'marketing_wordpress',  'ordre' => 80 ),
      array( 'code' => 'management_leadership',  'nom' => 'Thématique — Management & leadership',  'type_bloc' => 'thematique', 'profil_lie' => null,          'thematique_liee' => 'management_leadership', 'ordre' => 90 ),
      array( 'code' => 'management_restauration','nom' => 'Thématique — Management restauration',  'type_bloc' => 'thematique', 'profil_lie' => null,          'thematique_liee' => 'management_restauration','ordre' => 100 ),
      array( 'code' => 'hygiene_alimentaire',    'nom' => 'Thématique — Hygiène alimentaire',      'type_bloc' => 'thematique', 'profil_lie' => null,          'thematique_liee' => 'hygiene_alimentaire',   'ordre' => 110 ),
      array( 'code' => 'softskills',             'nom' => 'Thématique — Soft skills',              'type_bloc' => 'thematique', 'profil_lie' => null,          'thematique_liee' => 'softskills',            'ordre' => 120 ),
    );

    // ---- Questions restructurées (sans redondances) ----
    $questions_by_bloc = array(

      // --- BLOC DÉMARRAGE : routing uniquement ---
      'demarrage' => array(
        array( 'code' => 'dem_profil',      'ordre' => 10, 'libelle' => 'Vous remplissez cette analyse en tant que :', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Particulier","Apprenant","Salarié","Entreprise","Indépendant"]', 'source_prefill' => 'source.profil' ),
        array( 'code' => 'dem_thematique',  'ordre' => 20, 'libelle' => 'Quelle est la thématique de votre formation ?', 'type_reponse' => 'choix_unique_thematiques', 'obligatoire' => 1, 'source_prefill' => 'formation.thematique' ),
        array( 'code' => 'dem_formation',   'ordre' => 30, 'libelle' => 'Quelle formation est concernée ?', 'type_reponse' => 'texte_court', 'obligatoire' => 0, 'source_prefill' => 'formation.titre', 'masquer_si_prefill' => 1 ),
        array( 'code' => 'dem_source',      'ordre' => 40, 'libelle' => 'À quelle fiche cette analyse est-elle rattachée ?', 'type_reponse' => 'texte_court', 'obligatoire' => 0, 'source_prefill' => 'source.label', 'masquer_si_prefill' => 1 ),
      ),

      // --- BLOC COMMUN : identité + universels uniquement ---
      'commun' => array(
        array( 'code' => 'com_nom',          'ordre' => 10, 'libelle' => 'Nom', 'type_reponse' => 'texte_court', 'obligatoire' => 1, 'source_prefill' => 'source.last_name' ),
        array( 'code' => 'com_prenom',       'ordre' => 20, 'libelle' => 'Prénom', 'type_reponse' => 'texte_court', 'obligatoire' => 1, 'source_prefill' => 'source.first_name' ),
        array( 'code' => 'com_email',        'ordre' => 30, 'libelle' => 'Adresse e-mail', 'type_reponse' => 'email', 'obligatoire' => 1, 'source_prefill' => 'source.email' ),
        array( 'code' => 'com_telephone',    'ordre' => 40, 'libelle' => 'Téléphone', 'type_reponse' => 'telephone', 'obligatoire' => 0, 'source_prefill' => 'source.phone' ),
        array( 'code' => 'com_disponibilites','ordre' => 50, 'libelle' => 'Avez-vous des disponibilités ou contraintes de planning à nous signaler ?', 'type_reponse' => 'texte_long', 'obligatoire' => 0 ),
        array( 'code' => 'com_handicap',     'ordre' => 60, 'libelle' => 'Avez-vous un besoin d\'adaptation particulier lié à une situation de handicap ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Oui","Non","Je ne souhaite pas répondre"]', 'source_prefill' => 'source.accessibility_needs' ),
        array( 'code' => 'com_handicap_detail', 'ordre' => 70, 'libelle' => 'Si oui, merci de nous préciser le besoin pour que nous puissions adapter les conditions.', 'type_reponse' => 'texte_long', 'obligatoire' => 0, 'condition_affichage' => '{"field":"com_handicap","operator":"=","value":"Oui"}' ),
        array( 'code' => 'com_commentaires', 'ordre' => 80, 'libelle' => 'Avez-vous des informations complémentaires à partager avec nous ?', 'type_reponse' => 'texte_long', 'obligatoire' => 0 ),
        array( 'code' => 'com_consentement', 'ordre' => 90, 'libelle' => 'J\'accepte que mes réponses soient utilisées pour analyser mon besoin de formation et personnaliser mon parcours.', 'type_reponse' => 'consentement', 'obligatoire' => 1 ),
      ),

      // --- BLOC ENTREPRISE : questions commanditaire uniquement ---
      'entreprise' => array(
        array( 'code' => 'ent_raison',           'ordre' => 10, 'libelle' => 'Pour quelle(s) raison(s) faites-vous appel à cette formation ?', 'type_reponse' => 'choix_multiple', 'obligatoire' => 1, 'options' => '["Développer les compétences de mes collaborateurs","Répondre à une évolution de poste","Obligation réglementaire","Reconversion professionnelle","Améliorer l\'efficacité interne","Autre"]' ),
        array( 'code' => 'ent_initiateur',       'ordre' => 20, 'libelle' => 'Qui a initié ce besoin de formation ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Moi-même","La direction","Le service RH","Un manager","Les salariés concernés","Autre"]' ),
        array( 'code' => 'ent_nb_participants',  'ordre' => 30, 'libelle' => 'Combien de salariés seront concernés par cette formation ?', 'type_reponse' => 'nombre', 'obligatoire' => 1, 'source_prefill' => 'dossier.nb_apprenants', 'masquer_si_prefill' => 1 ),
        array( 'code' => 'ent_postes',           'ordre' => 40, 'libelle' => 'Quels sont les postes ou métiers des personnes à former ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1 ),
        array( 'code' => 'ent_planning',         'ordre' => 50, 'libelle' => 'Tous les participants seront-ils formés en même temps ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Oui, en groupe unique","Non, en plusieurs groupes","À définir ensemble"]' ),
        array( 'code' => 'ent_nb_groupes',       'ordre' => 60, 'libelle' => 'Si plusieurs groupes, combien en prévoyez-vous ?', 'type_reponse' => 'nombre', 'obligatoire' => 0, 'condition_affichage' => '{"field":"ent_planning","operator":"=","value":"Non, en plusieurs groupes"}' ),
        array( 'code' => 'ent_modalite',         'ordre' => 70, 'libelle' => 'Quelle modalité de formation souhaitez-vous ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Présentiel","Distanciel","Hybride"]', 'source_prefill' => 'dossier.modalite', 'masquer_si_prefill' => 1 ),
        array( 'code' => 'ent_lieu',             'ordre' => 80, 'libelle' => 'Si présentiel ou hybride, quel est le lieu envisagé ?', 'type_reponse' => 'texte_court', 'obligatoire' => 0, 'condition_affichage' => '{"field":"ent_modalite","operator":"in","value":"[\"Présentiel\",\"Hybride\"]"}', 'source_prefill' => 'formation.ville' ),
        array( 'code' => 'ent_attentes',         'ordre' => 90, 'libelle' => 'Quelles sont vos attentes précises vis-à-vis de cette formation ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1 ),
        array( 'code' => 'ent_objectifs_ok',     'ordre' => 100, 'libelle' => 'Le programme proposé correspond-il à vos attentes ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Oui totalement","Partiellement","Non, des ajustements sont nécessaires","Je ne sais pas encore"]' ),
        array( 'code' => 'ent_ajustements',      'ordre' => 110, 'libelle' => 'Quels ajustements ou sujets particuliers souhaiteriez-vous aborder ?', 'type_reponse' => 'texte_long', 'obligatoire' => 0, 'condition_affichage' => '{"field":"ent_objectifs_ok","operator":"in","value":"[\"Partiellement\",\"Non, des ajustements sont nécessaires\"]"}' ),
        array( 'code' => 'ent_handicap',         'ordre' => 120, 'libelle' => 'Un ou plusieurs participants ont-ils des besoins spécifiques liés à une situation de handicap ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Oui","Non","Je ne sais pas"]' ),
        array( 'code' => 'ent_handicap_detail',  'ordre' => 130, 'libelle' => 'Si oui, merci de préciser les besoins identifiés.', 'type_reponse' => 'texte_long', 'obligatoire' => 0, 'condition_affichage' => '{"field":"ent_handicap","operator":"=","value":"Oui"}' ),
        array( 'code' => 'ent_opco',             'ordre' => 140, 'libelle' => 'Souhaitez-vous solliciter un OPCO pour financer tout ou partie de la formation ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Oui","Non","Je ne sais pas"]', 'source_prefill' => 'dossier.financeur', 'masquer_si_prefill' => 1 ),
        array( 'code' => 'ent_opco_nom',         'ordre' => 150, 'libelle' => 'Si oui, quel est l\'OPCO concerné ?', 'type_reponse' => 'texte_court', 'obligatoire' => 0, 'condition_affichage' => '{"field":"ent_opco","operator":"=","value":"Oui"}', 'source_prefill' => 'dossier.financeur_nom', 'masquer_si_prefill' => 1 ),
      ),

      // --- BLOC SALARIÉ : spécifique au contexte salarié ---
      'salarie' => array(
        array( 'code' => 'sal_poste',          'ordre' => 10, 'libelle' => 'Quel est votre poste actuel ?', 'type_reponse' => 'texte_court', 'obligatoire' => 1, 'source_prefill' => 'source.job_title' ),
        array( 'code' => 'sal_missions',       'ordre' => 20, 'libelle' => 'Quelles sont vos principales missions au quotidien ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1 ),
        array( 'code' => 'sal_raison',         'ordre' => 30, 'libelle' => 'Pourquoi souhaitez-vous suivre cette formation ?', 'type_reponse' => 'choix_multiple', 'obligatoire' => 1, 'options' => '["Développer mes compétences","M\'adapter à mon poste","Préparer une évolution","Répondre à une demande de mon employeur","Gagner en efficacité","Reconversion","Autre"]' ),
        array( 'code' => 'sal_difficultes',    'ordre' => 40, 'libelle' => 'Quelles difficultés rencontrez-vous aujourd\'hui dans votre travail en lien avec cette formation ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1 ),
        array( 'code' => 'sal_resultat',       'ordre' => 50, 'libelle' => 'Que souhaitez-vous être capable de faire différemment après la formation ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1 ),
        array( 'code' => 'sal_employeur',      'ordre' => 60, 'libelle' => 'Votre employeur est-il informé de cette démarche de formation ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Oui, il a initié la démarche","Oui, il est au courant","Non","Je ne sais pas"]' ),
        array( 'code' => 'sal_financement',    'ordre' => 70, 'libelle' => 'Savez-vous comment cette formation sera financée ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 0, 'options' => '["Employeur","OPCO","CPF","Personnel","Je ne sais pas"]', 'source_prefill' => 'dossier.financeur_type' ),
      ),

      // --- BLOC APPRENANT : pédagogie et logistique apprenant ---
      'apprenant' => array(
        array( 'code' => 'app_niveau',          'ordre' => 10, 'libelle' => 'Quel est votre niveau de départ sur cette thématique ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Aucune pratique — je pars de zéro","Quelques notions, pas de pratique réelle","Déjà pratiqué occasionnellement","Pratique régulière","Niveau avancé"]', 'source_prefill' => 'positionnement.niveau' ),
        array( 'code' => 'app_difficultes',     'ordre' => 20, 'libelle' => 'Avez-vous des difficultés spécifiques à signaler avant d\'entrer en formation ?', 'type_reponse' => 'texte_long', 'obligatoire' => 0, 'aide' => 'Exemples : dyslexie, difficultés de lecture, anxiété à l\'écrit…' ),
        array( 'code' => 'app_materiel',        'ordre' => 30, 'libelle' => 'Si la formation se déroule à distance ou en hybride, disposez-vous du matériel nécessaire ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 0, 'options' => '["Oui","Non","Partiellement","Non concerné — formation en présentiel"]', 'aide' => 'Ordinateur, connexion internet, logiciels. En présentiel dans nos locaux, le matériel est fourni.' ),
        array( 'code' => 'app_materiel_detail', 'ordre' => 40, 'libelle' => 'Si non ou partiellement, qu\'est-ce qu\'il vous manque ?', 'type_reponse' => 'texte_long', 'obligatoire' => 0, 'condition_affichage' => '{"field":"app_materiel","operator":"in","value":"[\"Non\",\"Partiellement\"]"}' ),
        array( 'code' => 'app_objectif',        'ordre' => 50, 'libelle' => 'Quel résultat concret espérez-vous obtenir à l\'issue de cette formation ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1 ),
        array( 'code' => 'app_financement',     'ordre' => 60, 'libelle' => 'Comment cette formation est-elle financée ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 0, 'options' => '["CPF","Employeur","France Travail","Financement personnel","Autre","Je ne sais pas"]', 'source_prefill' => 'dossier.financeur_type' ),
      ),

      // --- BLOC PARTICULIER : contexte personnel ---
      'particulier' => array(
        array( 'code' => 'par_situation',   'ordre' => 10, 'libelle' => 'Quelle est votre situation actuelle ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Salarié","Demandeur d\'emploi","En reconversion","Retraité","Étudiant","Indépendant","Autre"]', 'source_prefill' => 'source.profile_type' ),
        array( 'code' => 'par_projet',      'ordre' => 20, 'libelle' => 'Quel est votre projet avec cette formation (professionnel, personnel, reconversion…) ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1, 'source_prefill' => 'source.desired_training' ),
        array( 'code' => 'par_motivation',  'ordre' => 30, 'libelle' => 'Pourquoi souhaitez-vous vous former sur ce sujet maintenant ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1 ),
        array( 'code' => 'par_niveau',      'ordre' => 40, 'libelle' => 'Avez-vous déjà des connaissances ou de l\'expérience sur ce sujet ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Aucune","Quelques bases","Intermédiaire","Avancé"]' ),
        array( 'code' => 'par_usage',       'ordre' => 50, 'libelle' => 'Dans quel cadre souhaitez-vous utiliser ces compétences ?', 'type_reponse' => 'choix_multiple', 'obligatoire' => 1, 'options' => '["Projet personnel","Projet professionnel","Recherche d\'emploi","Création d\'activité","Culture générale","Autre"]' ),
        array( 'code' => 'par_financement', 'ordre' => 60, 'libelle' => 'Comment envisagez-vous de financer cette formation ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Financement personnel","CPF","France Travail","Employeur","Autre","Je ne sais pas"]', 'source_prefill' => 'dossier.financeur_type' ),
        array( 'code' => 'par_resultat',    'ordre' => 70, 'libelle' => 'Quel résultat concret attendez-vous à l\'issue de cette formation ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1 ),
      ),

      // --- BLOC INDÉPENDANT : contexte activité indépendante ---
      'independant' => array(
        array( 'code' => 'ind_activite',   'ordre' => 10, 'libelle' => 'Quelle est votre activité principale ?', 'type_reponse' => 'texte_court', 'obligatoire' => 1, 'source_prefill' => 'source.job_title' ),
        array( 'code' => 'ind_statut',     'ordre' => 20, 'libelle' => 'Quel est votre statut juridique ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 0, 'options' => '["Micro-entreprise","Entreprise individuelle","Société (SARL, SAS…)","Profession libérale","Autre"]' ),
        array( 'code' => 'ind_probleme',   'ordre' => 30, 'libelle' => 'Quel problème concret souhaitez-vous résoudre grâce à cette formation ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1 ),
        array( 'code' => 'ind_objectif',   'ordre' => 40, 'libelle' => 'Quel objectif professionnel visez-vous à court terme ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1 ),
        array( 'code' => 'ind_besoins',    'ordre' => 50, 'libelle' => 'Quels besoins souhaitez-vous traiter en priorité ?', 'type_reponse' => 'choix_multiple', 'obligatoire' => 1, 'options' => '["Gagner du temps","Trouver des clients","Mieux communiquer","Automatiser des tâches","Structurer mon activité","Créer ou améliorer mon site","Améliorer ma posture professionnelle","Autre"]' ),
        array( 'code' => 'ind_livrable',   'ordre' => 60, 'libelle' => 'Souhaitez-vous repartir avec un livrable concret ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Oui","Non","Peu importe"]' ),
        array( 'code' => 'ind_livrable_type','ordre' => 70, 'libelle' => 'Quel type de livrable serait le plus utile pour vous ?', 'type_reponse' => 'choix_multiple', 'obligatoire' => 0, 'options' => '["Plan d\'action","Automatisation opérationnelle","Site ou page web","Modèles de contenu","Méthode de travail","Tableau de suivi","Autre"]', 'condition_affichage' => '{"field":"ind_livrable","operator":"=","value":"Oui"}' ),
        array( 'code' => 'ind_financement','ordre' => 80, 'libelle' => 'Comment souhaitez-vous financer cette formation ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 0, 'options' => '["Personnel","CPF","France Travail","Employeur","Autre","Je ne sais pas"]', 'source_prefill' => 'dossier.financeur_type' ),
      ),

      // --- BLOCS THÉMATIQUES : inchangés (pas de redondance détectée) ---
      'ia' => array(
        array( 'code' => 'ia_utilisation',      'ordre' => 10, 'libelle' => 'Utilisez-vous déjà ChatGPT ou un autre outil d\'intelligence artificielle ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Oui régulièrement","Oui occasionnellement","J\'ai testé","Non jamais"]' ),
        array( 'code' => 'ia_outils',           'ordre' => 20, 'libelle' => 'Quels outils d\'IA utilisez-vous déjà ?', 'type_reponse' => 'texte_long', 'obligatoire' => 0, 'placeholder' => 'ChatGPT, Gemini, Copilot, Claude, Perplexity…' ),
        array( 'code' => 'ia_usages',           'ordre' => 30, 'libelle' => 'Pour quels usages souhaitez-vous utiliser l\'intelligence artificielle ?', 'type_reponse' => 'choix_multiple', 'obligatoire' => 1, 'options' => '["Rédaction","Analyse de documents","Création de contenus","Recherche d\'idées","Résumés","Communication","Organisation","Automatisation","Autre"]' ),
        array( 'code' => 'ia_taches',           'ordre' => 40, 'libelle' => 'Quelles tâches aimeriez-vous améliorer grâce à l\'IA ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1 ),
        array( 'code' => 'ia_niveau_prompt',    'ordre' => 50, 'libelle' => 'Avez-vous déjà appris à rédiger des prompts structurés ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Oui","Non","Un peu"]' ),
        array( 'code' => 'ia_confidentialite',  'ordre' => 60, 'libelle' => 'Manipulez-vous des données sensibles ou confidentielles dans votre activité ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Oui","Non","Je ne sais pas"]' ),
        array( 'code' => 'ia_resultat',         'ordre' => 70, 'libelle' => 'Quel résultat concret attendez-vous après cette formation ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1 ),
      ),

      'automatisation_nocode' => array(
        array( 'code' => 'nc_outils',      'ordre' => 10, 'libelle' => 'Quels outils utilisez-vous actuellement dans votre organisation ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1, 'placeholder' => 'Notion, Airtable, Google Sheets, Excel, WordPress…' ),
        array( 'code' => 'nc_outils_auto', 'ordre' => 20, 'libelle' => 'Utilisez-vous déjà un outil d\'automatisation ?', 'type_reponse' => 'choix_multiple', 'obligatoire' => 0, 'options' => '["Make","Zapier","n8n","Airtable Automations","Google Apps Script","Autre","Aucun"]' ),
        array( 'code' => 'nc_taches',      'ordre' => 30, 'libelle' => 'Quelles tâches répétitives souhaitez-vous automatiser ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1 ),
        array( 'code' => 'nc_donnees',     'ordre' => 40, 'libelle' => 'Quelles données souhaitez-vous faire circuler entre vos outils ?', 'type_reponse' => 'texte_long', 'obligatoire' => 0 ),
        array( 'code' => 'nc_niveau',      'ordre' => 50, 'libelle' => 'Quel est votre niveau avec les outils numériques ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Débutant","Intermédiaire","Avancé","Je ne sais pas"]' ),
        array( 'code' => 'nc_blocages',    'ordre' => 60, 'libelle' => 'Quels sont vos principaux blocages aujourd\'hui ?', 'type_reponse' => 'choix_multiple', 'obligatoire' => 0, 'options' => '["Manque de méthode","Manque de temps","Peur de casser quelque chose","Outils trop complexes","Données dispersées","Autre"]' ),
        array( 'code' => 'nc_resultat',    'ordre' => 70, 'libelle' => 'Quel résultat concret attendez-vous de cette formation ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1, 'placeholder' => 'Ex : automatisation opérationnelle, méthode, scénario…' ),
      ),

      'marketing_wordpress' => array(
        array( 'code' => 'mkt_site',       'ordre' => 10, 'libelle' => 'Avez-vous déjà un site internet ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Oui","Non","En cours de création"]' ),
        array( 'code' => 'mkt_url',        'ordre' => 20, 'libelle' => 'Si oui, quelle est l\'adresse de votre site ?', 'type_reponse' => 'url', 'obligatoire' => 0, 'condition_affichage' => '{"field":"mkt_site","operator":"=","value":"Oui"}', 'source_prefill' => 'entreprise.website' ),
        array( 'code' => 'mkt_objectif',   'ordre' => 30, 'libelle' => 'Quel est votre objectif principal en communication digitale ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Gagner en visibilité","Générer des prospects","Vendre en ligne","Améliorer mon image professionnelle","Créer ou refondre un site","Autre"]' ),
        array( 'code' => 'mkt_canaux',     'ordre' => 40, 'libelle' => 'Quels canaux utilisez-vous déjà ?', 'type_reponse' => 'choix_multiple', 'obligatoire' => 1, 'options' => '["Site internet","LinkedIn","Facebook","Instagram","Google Business Profile","E-mailing","Aucun"]' ),
        array( 'code' => 'mkt_difficultes','ordre' => 50, 'libelle' => 'Quelles difficultés rencontrez-vous dans votre communication digitale ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1 ),
        array( 'code' => 'mkt_contenus',   'ordre' => 60, 'libelle' => 'Quels types de contenus souhaitez-vous créer ou améliorer ?', 'type_reponse' => 'choix_multiple', 'obligatoire' => 0, 'options' => '["Pages web","Articles de blog","Publications réseaux sociaux","Fiche Google Business","E-mails","Visuels","Vidéos"]' ),
        array( 'code' => 'mkt_resultat',   'ordre' => 70, 'libelle' => 'Quel résultat concret attendez-vous de cette formation ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1 ),
      ),

      'management_leadership' => array(
        array( 'code' => 'mgmt_equipe',       'ordre' => 10, 'libelle' => 'Encadrez-vous actuellement une équipe ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Oui","Non","Prochainement"]' ),
        array( 'code' => 'mgmt_nb',           'ordre' => 20, 'libelle' => 'Combien de personnes managez-vous ou allez-vous manager ?', 'type_reponse' => 'nombre', 'obligatoire' => 0, 'condition_affichage' => '{"field":"mgmt_equipe","operator":"in","value":"[\"Oui\",\"Prochainement\"]"}' ),
        array( 'code' => 'mgmt_role',         'ordre' => 30, 'libelle' => 'Quel est votre rôle managérial actuel ou futur ?', 'type_reponse' => 'texte_court', 'obligatoire' => 1 ),
        array( 'code' => 'mgmt_situations',   'ordre' => 40, 'libelle' => 'Quelles situations managériales souhaitez-vous améliorer ?', 'type_reponse' => 'choix_multiple', 'obligatoire' => 1, 'options' => '["Communication","Motivation de l\'équipe","Délégation","Gestion des conflits","Organisation","Suivi de l\'activité","Posture de manager","Autre"]' ),
        array( 'code' => 'mgmt_difficultes',  'ordre' => 50, 'libelle' => 'Quelles difficultés rencontrez-vous concrètement dans votre rôle de manager ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1 ),
        array( 'code' => 'mgmt_changement',   'ordre' => 60, 'libelle' => 'Quel changement concret souhaitez-vous observer après la formation ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1 ),
        array( 'code' => 'mgmt_contexte',     'ordre' => 70, 'libelle' => 'Votre environnement présente-t-il des contraintes particulières ?', 'type_reponse' => 'texte_long', 'obligatoire' => 0, 'placeholder' => 'Équipe à distance, turn-over important, tensions, saisonnalité…' ),
      ),

      'management_restauration' => array(
        array( 'code' => 'resto_type',          'ordre' => 10, 'libelle' => 'Quel type d\'établissement est concerné ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Restaurant traditionnel","Restaurant rapide","Hôtel-restaurant","Bar","Traiteur","Restauration collective","Autre"]' ),
        array( 'code' => 'resto_equipe',        'ordre' => 20, 'libelle' => 'Combien de personnes composent l\'équipe à former ?', 'type_reponse' => 'nombre', 'obligatoire' => 1 ),
        array( 'code' => 'resto_profils',       'ordre' => 30, 'libelle' => 'Quels profils sont concernés par la formation ?', 'type_reponse' => 'choix_multiple', 'obligatoire' => 1, 'options' => '["Dirigeant","Manager / Chef de rang","Responsable de salle","Personnel de salle","Personnel de cuisine","Autre"]' ),
        array( 'code' => 'resto_problemes',     'ordre' => 40, 'libelle' => 'Quels sont les principaux défis rencontrés dans votre établissement ?', 'type_reponse' => 'choix_multiple', 'obligatoire' => 1, 'options' => '["Turn-over élevé","Communication interne","Organisation du service","Gestion du stress","Qualité de service","Gestion des conflits","Intégration des nouveaux","Autre"]' ),
        array( 'code' => 'resto_periode',       'ordre' => 50, 'libelle' => 'Quelle période serait la plus adaptée pour former l\'équipe ?', 'type_reponse' => 'texte_court', 'obligatoire' => 0, 'aide' => 'Tenant compte des périodes creuses ou de forte activité.' ),
        array( 'code' => 'resto_resultat',      'ordre' => 60, 'libelle' => 'Quel résultat concret attendez-vous après la formation ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1 ),
      ),

      'hygiene_alimentaire' => array(
        array( 'code' => 'hyg_secteur',      'ordre' => 10, 'libelle' => 'Quel est votre secteur d\'activité ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Restauration traditionnelle","Restauration rapide","Restauration collective","Traiteur","Commerce alimentaire","Production alimentaire","Autre"]' ),
        array( 'code' => 'hyg_formation',    'ordre' => 20, 'libelle' => 'Avez-vous déjà suivi une formation en hygiène alimentaire ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Oui — il y a moins de 3 ans","Oui — il y a plus de 3 ans","Non","Je ne sais pas"]' ),
        array( 'code' => 'hyg_pms',          'ordre' => 30, 'libelle' => 'Disposez-vous d\'un Plan de Maîtrise Sanitaire (PMS) ou de procédures internes ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Oui, à jour","Oui, mais à actualiser","Non","En cours de création"]' ),
        array( 'code' => 'hyg_besoins',      'ordre' => 40, 'libelle' => 'Quels sujets souhaitez-vous approfondir ?', 'type_reponse' => 'choix_multiple', 'obligatoire' => 1, 'options' => '["Bonnes pratiques d\'hygiène","Chaîne du froid","Plan de Maîtrise Sanitaire","Traçabilité","Nettoyage et désinfection","Gestion des non-conformités","Allergènes","Autre"]' ),
        array( 'code' => 'hyg_contexte',     'ordre' => 50, 'libelle' => 'Avez-vous été soumis à un contrôle sanitaire récemment ou avez-vous des points de vigilance particuliers ?', 'type_reponse' => 'texte_long', 'obligatoire' => 0 ),
        array( 'code' => 'hyg_resultat',     'ordre' => 60, 'libelle' => 'Quel résultat concret attendez-vous de cette formation ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1 ),
      ),

      'softskills' => array(
        array( 'code' => 'ss_competence',   'ordre' => 10, 'libelle' => 'Quelle compétence souhaitez-vous développer en priorité ?', 'type_reponse' => 'choix_multiple', 'obligatoire' => 1, 'options' => '["Communication","Écoute active","Gestion du stress","Prise de parole en public","Confiance en soi","Intelligence émotionnelle","Gestion des conflits","Organisation personnelle","Autre"]' ),
        array( 'code' => 'ss_situations',   'ordre' => 20, 'libelle' => 'Dans quelles situations concrètes rencontrez-vous des difficultés ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1 ),
        array( 'code' => 'ss_contexte',     'ordre' => 30, 'libelle' => 'Cette démarche est-elle liée à un contexte particulier ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 1, 'options' => '["Situation professionnelle","Évolution de poste","Management","Relation client","Situation personnelle","Autre"]' ),
        array( 'code' => 'ss_objectif',     'ordre' => 40, 'libelle' => 'Quel changement concret souhaitez-vous observer après la formation ?', 'type_reponse' => 'texte_long', 'obligatoire' => 1 ),
        array( 'code' => 'ss_priorite',     'ordre' => 50, 'libelle' => 'Ce besoin est-il urgent pour vous ?', 'type_reponse' => 'choix_unique', 'obligatoire' => 0, 'options' => '["Oui, c\'est prioritaire","Non, à moyen terme","Je ne sais pas"]' ),
      ),

    ); // fin $questions_by_bloc

    // ---- Insertion blocs + questions ----
    foreach ( $blocs as $bloc_def ) {
      $wpdb->insert( $this->need_block_table, array(
        'code'            => $bloc_def['code'],
        'nom'             => $bloc_def['nom'],
        'description'     => '',
        'type_bloc'       => $bloc_def['type_bloc'],
        'profil_lie'      => $bloc_def['profil_lie'],
        'thematique_liee' => $bloc_def['thematique_liee'],
        'formation_liee'  => null,
        'ordre'           => $bloc_def['ordre'],
        'actif'           => 1,
        'verrouille'      => 1,
        'created_at'      => $now,
        'updated_at'      => $now,
      ) );
      $bloc_id = (int) $wpdb->insert_id;
      if ( ! $bloc_id || ! isset( $questions_by_bloc[ $bloc_def['code'] ] ) ) { continue; }
      foreach ( $questions_by_bloc[ $bloc_def['code'] ] as $q ) {
        $wpdb->insert( $this->need_question_table, array(
          'bloc_id'              => $bloc_id,
          'code'                 => $q['code'],
          'ordre'                => $q['ordre'],
          'libelle'              => $q['libelle'],
          'type_reponse'         => $q['type_reponse'],
          'obligatoire'          => isset( $q['obligatoire'] )         ? (int) $q['obligatoire']                    : 0,
          'options'              => isset( $q['options'] )             ? $q['options']                              : null,
          'aide'                 => isset( $q['aide'] )                ? $q['aide']                                 : null,
          'placeholder'          => isset( $q['placeholder'] )         ? $q['placeholder']                          : null,
          'condition_affichage'  => isset( $q['condition_affichage'] ) ? $q['condition_affichage']                  : null,
          'source_prefill'       => isset( $q['source_prefill'] )      ? $q['source_prefill']                       : null,
          'masquer_si_prefill'   => isset( $q['masquer_si_prefill'] )  ? (int) $q['masquer_si_prefill']             : 0,
          'visible_admin'        => 1,
          'visible_public'       => 1,
          'afficher_pdf'         => 1,
          'actif'                => 1,
          'verrouille'           => 1,
          'created_at'           => $now,
          'updated_at'           => $now,
        ) );
      }
    }
  }


  /** ACDC 3.21.16 — Réinitialiser les blocs système (force re-seed). */
  public function reset_nad_system_blocks() {
    global $wpdb;
    // Supprimer questions verrouillées
    $bloc_ids = $wpdb->get_col( "SELECT id FROM {$this->need_block_table} WHERE verrouille = 1" );
    if ( ! empty( $bloc_ids ) ) {
      $placeholders = implode( ',', array_fill( 0, count( $bloc_ids ), '%d' ) );
      $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->need_question_table} WHERE bloc_id IN ({$placeholders}) AND verrouille = 1", ...$bloc_ids ) );
      $wpdb->query( "DELETE FROM {$this->need_block_table} WHERE verrouille = 1" );
    }
    $this->do_seed_system_blocks();

    // ACDC 3.21.29-hotfix4 — Resynchroniser blocs_ids sur toutes les analyses non-traitées.
    // Les IDs changent après chaque reset → les analyses gardaient des IDs obsolètes.
    $analyses = $wpdb->get_results(
      "SELECT id, profil, thematique FROM {$this->need_analysis_table}
       WHERE statut != 'traite' AND is_model = 0"
    );
    $now = current_time( 'mysql' );
    foreach ( $analyses as $a ) {
      $new_ids = $this->nad_auto_select_blocs(
        ! empty( $a->profil )     ? (string) $a->profil     : 'commun',
        ! empty( $a->thematique ) ? (string) $a->thematique : ''
      );
      if ( ! empty( $new_ids ) ) {
        $wpdb->update(
          $this->need_analysis_table,
          array( 'blocs_ids' => wp_json_encode( $new_ids ), 'updated_at' => $now ),
          array( 'id' => (int) $a->id )
        );
      }
    }
  }


  private function get_need_blocks( $filters = array() ) {
    global $wpdb;
    $where = '1=1';
    $args  = array();
    if ( ! empty( $filters['type_bloc'] ) ) {
      $where .= ' AND type_bloc = %s';
      $args[] = $filters['type_bloc'];
    }
    if ( ! empty( $filters['profil_lie'] ) ) {
      $where .= ' AND profil_lie = %s';
      $args[] = $filters['profil_lie'];
    }
    if ( ! empty( $filters['thematique_liee'] ) ) {
      $where .= ' AND thematique_liee = %s';
      $args[] = $filters['thematique_liee'];
    }
    if ( isset( $filters['actif'] ) ) {
      $where .= ' AND actif = %d';
      $args[] = (int) $filters['actif'];
    }
    $sql = "SELECT * FROM {$this->need_block_table} WHERE {$where} ORDER BY ordre ASC, id ASC";
    return empty( $args ) ? $wpdb->get_results( $sql ) : $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
  }

  private function get_need_block( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->need_block_table} WHERE id = %d", $id ) );
  }

  private function get_need_block_by_code( $code ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->need_block_table} WHERE code = %s LIMIT 1", $code ) );
  }

  private function get_need_questions( $bloc_id, $only_active = false ) {
    global $wpdb;
    $where = 'bloc_id = %d';
    if ( $only_active ) {
      $where .= ' AND actif = 1';
    }
    return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->need_question_table} WHERE {$where} ORDER BY ordre ASC, id ASC", $bloc_id ) );
  }

  private function get_need_question( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->need_question_table} WHERE id = %d", $id ) );
  }

  private function get_need_questions_count_by_bloc( $bloc_id ) {
    global $wpdb;
    return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->need_question_table} WHERE bloc_id = %d AND actif = 1", $bloc_id ) );
  }

  private function need_block_type_label( $type ) {
    $map = array(
      'commun'       => 'Commun',
      'profil'       => 'Profil',
      'thematique'   => 'Thématique',
      'formation'    => 'Formation',
      'personnalise' => 'Personnalisé',
    );
    return isset( $map[ $type ] ) ? $map[ $type ] : ucfirst( $type );
  }

  private function need_profil_label( $code ) {
    $map = array(
      'particulier' => 'Particulier',
      'apprenant'   => 'Apprenant',
      'salarie'     => 'Salarié',
      'entreprise'  => 'Entreprise',
      'independant' => 'Indépendant',
    );
    return isset( $map[ $code ] ) ? $map[ $code ] : ucfirst( $code );
  }

  private function need_thematique_label( $code ) {
    $map = array(
      'ia'                     => 'Intelligence artificielle',
      'automatisation_nocode'  => 'Automatisation & No-code',
      'marketing_wordpress'    => 'Marketing digital & WordPress',
      'management_leadership'  => 'Management & leadership',
      'management_restauration'=> 'Management restauration',
      'hygiene_alimentaire'    => 'Hygiène alimentaire',
      'softskills'             => 'Soft skills',
    );
    return isset( $map[ $code ] ) ? $map[ $code ] : ucfirst( $code );
  }

  private function need_statut_label( $code ) {
    $map = array(
      'brouillon' => 'Brouillon',
      'nouveau'   => 'Nouveau',
      'a_traiter' => 'À traiter',
      'traite'    => 'Traité',
      'archive'   => 'Archivé',
    );
    return isset( $map[ $code ] ) ? $map[ $code ] : ucfirst( $code );
  }

  private function need_type_reponse_label( $code ) {
    $map = array(
      'texte_court'   => 'Texte court',
      'texte_long'    => 'Texte long',
      'choix_unique'  => 'Choix unique',
      'choix_multiple'=> 'Cases à cocher',
      'nombre'        => 'Nombre',
      'date'          => 'Date',
      'email'         => 'E-mail',
      'telephone'     => 'Téléphone',
      'consentement'  => 'Consentement',
      'url'           => 'Adresse web',
    );
    return isset( $map[ $code ] ) ? $map[ $code ] : ucfirst( $code );
  }

  private function get_need_analyses( $search = '', $include_models = true ) {
    global $wpdb;
    $where = '1=1';
    $args = array();
    if ( ! $include_models ) {
      $where .= ' AND na.is_model = 0';
    }
    if ( '' !== $search ) {
      $where .= ' AND na.title LIKE %s';
      $args[] = '%' . $wpdb->esc_like( $search ) . '%';
    }
    // ACDC 3.21.29-hotfix4 — LEFT JOIN entreprise pour afficher le nom commanditaire dans la liste.
    $sql = "SELECT na.*, c.name AS company_name
            FROM {$this->need_analysis_table} na
            LEFT JOIN {$this->company_table} c ON na.entreprise_id = c.id
            WHERE {$where}
            ORDER BY na.is_model DESC, na.updated_at DESC, na.id DESC";
    return empty( $args ) ? $wpdb->get_results( $sql ) : $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
  }  private function get_need_analysis( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->need_analysis_table} WHERE id = %d", $id ) );
  }

  /* -----------------------------------------------------------------------
   * ACDC 3.21.11 — Moteur de préremplissage (prefill dynamique).
   * -------------------------------------------------------------------- */

  /**
   * Retourne une map source_prefill_path => valeur_courante
   * en lisant les fiches sources liées à l'analyse.
   * Option 3B : toujours relire la fiche source, jamais stocker la valeur.
   */
  private function get_nad_prefill_values( $analysis ) {
    global $wpdb;
    if ( ! $analysis ) {
      return array();
    }
    $values = array();

    // ---- Source principale (prospect ou apprenant) ----
    $source_type = ! empty( $analysis->source_type ) ? (string) $analysis->source_type : '';
    $source_id   = ! empty( $analysis->source_id )   ? (int)    $analysis->source_id   : 0;
    if ( $source_type && $source_id ) {
      $src = $this->nad_get_source_data( $source_type, $source_id );
      foreach ( $src as $k => $v ) {
        $values[ 'source.' . $k ] = $v;
      }
    }

    // ---- Apprenant lié (si distinct de la source principale) ----
    $apprenant_id = ! empty( $analysis->apprenant_id ) ? (int) $analysis->apprenant_id : 0;
    if ( $apprenant_id && ( 'apprenant' !== $source_type || $apprenant_id !== $source_id ) ) {
      $app = $this->nad_get_source_data( 'apprenant', $apprenant_id );
      foreach ( $app as $k => $v ) {
        if ( ! isset( $values[ 'source.' . $k ] ) || '' === $values[ 'source.' . $k ] ) {
          $values[ 'source.' . $k ] = $v;
        }
      }
    }

    // ---- Entreprise ----
    $entreprise_id = ! empty( $analysis->entreprise_id ) ? (int) $analysis->entreprise_id : 0;
    if ( $entreprise_id ) {
      $ent = $this->nad_get_entreprise_data( $entreprise_id );
      foreach ( $ent as $k => $v ) {
        $values[ 'entreprise.' . $k ] = $v;
      }
    }
    /* ACDC 3.25.260 — Une analyse issue d'un prospect n'a pas d'entreprise_id :
       la raison sociale existe pourtant, sur le prospect ou sur le dossier.
       Sans ce repli, le formulaire accueillait le commanditaire par le prénom
       de son interlocuteur. */
    if ( empty( $values['entreprise.name'] ) ) {
      $company_fallback = $this->nad_analysis_company_name( $analysis );
      if ( '' !== $company_fallback ) {
        $values['entreprise.name'] = $company_fallback;
      }
    }

    // ---- Formation ----
    $formation_id = ! empty( $analysis->formation_id ) ? (int) $analysis->formation_id : 0;
    if ( $formation_id ) {
      $form = $this->nad_get_formation_data( $formation_id );
      foreach ( $form as $k => $v ) {
        $values[ 'formation.' . $k ] = $v;
      }
    }

    // ---- Dossier / Convention ----
    $dossier_id = ! empty( $analysis->dossier_id ) ? (int) $analysis->dossier_id : 0;
    if ( $dossier_id ) {
      $dos = $this->nad_get_dossier_data( $dossier_id );
      foreach ( $dos as $k => $v ) {
        $values[ 'dossier.' . $k ] = $v;
      }
    }

    // ---- Champs dérivés ----
    if ( ! empty( $values['source.last_name'] ) || ! empty( $values['source.first_name'] ) ) {
      $values['source.label'] = trim( ( $values['source.first_name'] ?? '' ) . ' ' . ( $values['source.last_name'] ?? '' ) );
    }
    if ( ! empty( $analysis->profil ) ) {
      $values['source.profil'] = $this->need_profil_label( $analysis->profil );
    }

    // ---- Recueil des besoins + Proposition commerciale (via convention → prospect) ----
    // ACDC 3.21.29-hotfix4 — Remonter les données du recueil et de la proposition commerciale.
    $dossier_id_for_need = ! empty( $analysis->dossier_id ) ? (int) $analysis->dossier_id : 0;
    if ( $dossier_id_for_need ) {
      $contract_row = $wpdb->get_row( $wpdb->prepare(
        "SELECT source_prospect_id, company_id, formation_id FROM {$this->registration_contract_table} WHERE id = %d",
        $dossier_id_for_need
      ) );
      if ( $contract_row ) {
        $prospect_id_for_need  = ! empty( $contract_row->source_prospect_id ) ? (int) $contract_row->source_prospect_id : 0;
        $company_id_for_need   = ! empty( $contract_row->company_id ) ? (int) $contract_row->company_id : 0;
        $formation_id_for_need = ! empty( $contract_row->formation_id ) ? (int) $contract_row->formation_id : 0;

        // Recueil des besoins : prendre le plus récent lié au prospect ou à l'entreprise
        $need_row = null;
        if ( $prospect_id_for_need ) {
          $need_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->need_table} WHERE source_prospect_id = %d ORDER BY created_at DESC LIMIT 1",
            $prospect_id_for_need
          ) );
        }
        if ( ! $need_row && $company_id_for_need ) {
          $need_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->need_table} WHERE company_id = %d ORDER BY created_at DESC LIMIT 1",
            $company_id_for_need
          ) );
        }
        if ( $need_row ) {
          if ( ! empty( $need_row->learners_count )  && empty( $values['dossier.nb_apprenants'] ) )  { $values['dossier.nb_apprenants']  = (string) $need_row->learners_count; }
          if ( ! empty( $need_row->target_audience ) && empty( $values['source.target_audience'] ) ) { $values['source.target_audience']  = (string) $need_row->target_audience; }
          if ( ! empty( $need_row->desired_format )  && empty( $values['dossier.modalite'] ) )        { $values['dossier.modalite']         = (string) $need_row->desired_format; }
          if ( ! empty( $need_row->planned_funding ) && empty( $values['dossier.financeur'] ) )       { $values['dossier.financeur']        = (string) $need_row->planned_funding; }
        }

        $proposal_table = $wpdb->prefix . 'acdc_of_proposals';
        // Proposition commerciale : prendre la plus récente liée au prospect ou à l'entreprise
        $proposal_row = null;
        if ( $prospect_id_for_need ) {
          $proposal_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$proposal_table} WHERE source_prospect_id = %d ORDER BY created_at DESC LIMIT 1",
            $prospect_id_for_need
          ) );
        }
        if ( ! $proposal_row && $company_id_for_need ) {
          $proposal_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$proposal_table} WHERE company_id = %d ORDER BY created_at DESC LIMIT 1",
            $company_id_for_need
          ) );
        }
        if ( $proposal_row ) {
          if ( ! empty( $proposal_row->formation_learners_count ) && empty( $values['dossier.nb_apprenants'] ) ) {
            $values['dossier.nb_apprenants'] = (string) $proposal_row->formation_learners_count;
          }
          if ( ! empty( $proposal_row->formation_location ) && empty( $values['formation.ville'] ) ) {
            $values['formation.ville'] = (string) $proposal_row->formation_location;
          }
          if ( ! empty( $proposal_row->formation_funding ) && empty( $values['dossier.financeur'] ) ) {
            $values['dossier.financeur'] = (string) $proposal_row->formation_funding;
          }
        }

        // Dériver le planning à partir du nb_apprenants (si pas déjà dérivé via dossier)
        if ( empty( $values['dossier.planning'] ) && ! empty( $values['dossier.nb_apprenants'] ) ) {
          $nb_der = (int) $values['dossier.nb_apprenants'];
          $values['dossier.planning'] = $nb_der === 1 ? 'Oui, en groupe unique' : ( $nb_der > 1 ? 'À définir ensemble' : '' );
        }
      }
    }
    // Garantit le préremplissage même quand source_type/source_id est absent (ex: commanditaire entreprise).
    if ( empty( $values['source.last_name'] )  && ! empty( $analysis->repondant_nom ) )    { $values['source.last_name']  = (string) $analysis->repondant_nom; }
    if ( empty( $values['source.first_name'] ) && ! empty( $analysis->repondant_prenom ) ) { $values['source.first_name'] = (string) $analysis->repondant_prenom; }
    if ( empty( $values['source.email'] )      && ! empty( $analysis->repondant_email ) )  { $values['source.email']      = (string) $analysis->repondant_email; }
    if ( empty( $values['source.label'] ) && ( ! empty( $values['source.first_name'] ) || ! empty( $values['source.last_name'] ) ) ) {
      $values['source.label'] = trim( ( $values['source.first_name'] ?? '' ) . ' ' . ( $values['source.last_name'] ?? '' ) );
    }

    return $values;
  }

  /** Données depuis un prospect ou un apprenant. */
  private function nad_get_source_data( $type, $id ) {
    global $wpdb;
    $map = array();
    if ( 'prospect' === $type ) {
      $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->prospect_table} WHERE id = %d", $id ) );
      if ( $row ) {
        $map['last_name']          = (string) $row->last_name;
        $map['first_name']         = (string) $row->first_name;
        $map['email']              = (string) $row->email;
        $map['phone']              = (string) $row->phone;
        $map['job_title']          = (string) $row->job_title;
        $map['desired_training']   = (string) $row->desired_training;
        $map['accessibility_needs']= (string) $row->accessibility_needs;
        $map['profile_type']       = (string) $row->profile_type;
        $map['company_name']       = (string) $row->company_name;
        $map['city']               = (string) $row->city;
      }
    } elseif ( 'apprenant' === $type ) {
      $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->learner_table} WHERE id = %d", $id ) );
      if ( $row ) {
        $map['last_name']          = (string) $row->last_name;
        $map['first_name']         = (string) $row->first_name;
        $map['email']              = (string) $row->email;
        $map['phone']              = (string) $row->phone;
        $map['job_title']          = (string) $row->job_title;
        $map['accessibility_needs']= (string) $row->accessibility_needs;
        $map['education_level']    = (string) $row->education_level;
        $map['city']               = (string) $row->city;
      }
    }
    return $map;
  }

  /** Données depuis une entreprise. */
  private function nad_get_entreprise_data( $id ) {
    global $wpdb;
    $map = array();
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->company_table} WHERE id = %d", $id ) );
    if ( $row ) {
      $map['name']              = (string) $row->name;
      $map['website']           = (string) $row->website;
      $map['city']              = (string) $row->city;
      $map['signer_last_name']  = (string) $row->signer_last_name;
      $map['signer_first_name'] = (string) $row->signer_first_name;
      $map['signer_quality']    = (string) $row->signer_quality;
      $map['siret']             = (string) $row->siret;
      $map['phone']             = (string) $row->phone;
      $map['email']             = (string) $row->email;
    }
    return $map;
  }

  /** Données depuis une formation. */
  private function nad_get_formation_data( $id ) {
    global $wpdb;
    $map = array();
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->formation_table} WHERE id = %d", $id ) );
    if ( $row ) {
      $map['titre']     = (string) $row->title;
      $map['modalite']  = (string) $row->modality;
      $map['objectifs'] = wp_strip_all_tags( (string) $row->objectives );
      $map['duree']     = (string) $row->duration;
      $map['ville']     = (string) $row->city;
      // ACDC 3.21.14 — Thématique depuis la colonne dédiée (priorité sur la détection textuelle)
      $map['thematique'] = ! empty( $row->thematique ) ? (string) $row->thematique : $this->nad_detect_thematique_from_formation( $row );
    }
    return $map;
  }

  /** Données depuis un dossier (convention de formation). */
  private function nad_get_dossier_data( $id ) {
    global $wpdb;
    $map = array();
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->registration_contract_table} WHERE id = %d", $id ) );
    if ( $row ) {
      $map['date_debut']     = ! empty( $row->start_date ) ? wp_date( 'd/m/Y', strtotime( (string) $row->start_date ) ) : '';
      $map['date_fin']       = ! empty( $row->end_date )   ? wp_date( 'd/m/Y', strtotime( (string) $row->end_date ) )   : '';
      $map['financeur']      = (string) $row->public_funding;
      $map['financeur_nom']  = (string) ( isset( $row->public_funding_name ) ? $row->public_funding_name : '' );
      $map['financeur_type'] = (string) $row->public_funding;
      $map['titre']          = (string) $row->title;
      // ACDC 3.21.29-hotfix4 — Nb apprenants source 1 : learner_ids de la convention
      $nb = 0;
      if ( ! empty( $row->learner_ids ) ) {
        $decoded = json_decode( $row->learner_ids, true );
        $lids    = is_array( $decoded ) ? array_filter( $decoded ) : array();
        $nb      = count( $lids );
      }
      // Source 2 : inscriptions liées à cette convention (autofill_contract_id)
      if ( $nb === 0 ) {
        $nb = (int) $wpdb->get_var( $wpdb->prepare(
          "SELECT COUNT(*) FROM {$this->training_registration_table} WHERE autofill_contract_id = %d",
          $id
        ) );
      }
      $map['nb_apprenants'] = $nb > 0 ? (string) $nb : '';
      // Dériver le planning à partir du nb d'apprenants
      if ( $nb === 1 ) {
        $map['planning'] = 'Oui, en groupe unique';
      } elseif ( $nb > 1 ) {
        $map['planning'] = 'À définir ensemble';
      } else {
        $map['planning'] = '';
      }
      // Modalité depuis la formation liée
      if ( ! empty( $row->formation_id ) ) {
        $form_row = $wpdb->get_row( $wpdb->prepare(
          "SELECT modality FROM {$this->formation_table} WHERE id = %d",
          (int) $row->formation_id
        ) );
        if ( $form_row && ! empty( $form_row->modality ) ) {
          $map['modalite'] = (string) $form_row->modality;
        }
      }
    }
    return $map;
  }

  /** Résout la valeur d'un champ dans la map prefill. */
  private function get_thematique_by_id( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->thematique_table} WHERE id = %d", (int) $id ) );
  }

  private function nad_resolve_prefill( $source_prefill, $prefill_map ) {
    if ( empty( $source_prefill ) || empty( $prefill_map ) ) {
      return '';
    }
    return isset( $prefill_map[ $source_prefill ] ) ? (string) $prefill_map[ $source_prefill ] : '';
  }

  private function get_need_analysis_block_label( $type ) {
    return 'cases_a_cocher' === $type ? 'Cases à cocher' : 'Champ texte';
  }  private function normalize_need_analysis_blocks( $blocks_in ) {
    $blocks = array();
    if ( ! is_array( $blocks_in ) ) {
      return $blocks;
    }
    foreach ( $blocks_in as $block ) {
      $type = isset( $block['block_type'] ) ? sanitize_text_field( $block['block_type'] ) : 'champ_texte';
      $question = isset( $block['question'] ) ? sanitize_text_field( $block['question'] ) : '';
      $choice_type = isset( $block['choice_type'] ) ? sanitize_text_field( $block['choice_type'] ) : 'Choix unique';
      $responses = array();
      if ( isset( $block['responses'] ) && is_array( $block['responses'] ) ) {
        foreach ( $block['responses'] as $response ) {
          $response = sanitize_text_field( $response );
          if ( '' !== $response ) {
            $responses[] = $response;
          }
        }
      }
      if ( '' === $question ) {
        continue;
      }
      $normalized_type = in_array( $type, array( 'cases_a_cocher', 'champ_texte' ), true ) ? $type : 'champ_texte';
      $blocks[] = array(
        'block_type'  => $normalized_type,
        'question'    => $question,
        'choice_type' => 'Choix multiple' === $choice_type ? 'Choix multiple' : 'Choix unique',
        'responses'   => 'cases_a_cocher' === $normalized_type ? $responses : array(),
      );
    }
    return $blocks;
  }  private function get_groups() {
    global $wpdb;
    $sql = "SELECT g.*, f.title AS formation_title, f.modality AS formation_modality, s.start_date, s.end_date
        FROM {$this->group_table} g
        LEFT JOIN {$this->formation_table} f ON f.id = g.formation_id
        LEFT JOIN {$this->session_table} s ON s.id = g.session_id
        ORDER BY g.created_at DESC, g.id DESC";
    return $wpdb->get_results( $sql );
  }  private function get_group( $id ) {
    global $wpdb;
    $sql = "SELECT g.*, f.title AS formation_title, f.modality AS formation_modality, s.start_date, s.end_date
        FROM {$this->group_table} g
        LEFT JOIN {$this->formation_table} f ON f.id = g.formation_id
        LEFT JOIN {$this->session_table} s ON s.id = g.session_id
        WHERE g.id = %d";
    return $wpdb->get_row( $wpdb->prepare( $sql, $id ) );
  }  private function get_group_learner_names( $group ) {
    if ( empty( $group->learner_ids ) ) {
      return array();
    }
    $ids = array_filter( array_map( 'absint', explode( ',', (string) $group->learner_ids ) ) );
    if ( empty( $ids ) ) {
      return array();
    }
    global $wpdb;
    $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
    $sql = $wpdb->prepare( "SELECT first_name, usage_last_name FROM {$this->learner_table} WHERE id IN ($placeholders) ORDER BY first_name ASC", $ids );
    $rows = $wpdb->get_results( $sql );
    return array_map( static function( $row ) {
      return trim( $row->first_name . ' ' . $row->usage_last_name );
    }, $rows );
  }  private function acdc_ensure_group_document_space( $group ) {
    if ( ! $group || empty( $group->id ) ) {
      return array();
    }
    $spaces = get_option( 'acdc_of_group_document_spaces', array() );
    if ( ! is_array( $spaces ) ) {
      $spaces = array();
    }
    $group_id = (int) $group->id;
    $spaces[ $group_id ] = array(
      'group_id'        => $group_id,
      'group_name'      => isset( $group->name ) ? sanitize_text_field( $group->name ) : '',
      'formation_id'    => isset( $group->formation_id ) ? (int) $group->formation_id : 0,
      'formation_title' => isset( $group->formation_title ) ? sanitize_text_field( $group->formation_title ) : '',
      'session_id'      => isset( $group->session_id ) ? (int) $group->session_id : 0,
      'start_date'      => isset( $group->start_date ) ? sanitize_text_field( $group->start_date ) : '',
      'end_date'        => isset( $group->end_date ) ? sanitize_text_field( $group->end_date ) : '',
      'updated_at'      => $this->now_mysql(),
    );
    update_option( 'acdc_of_group_document_spaces', $spaces, false );
    return $spaces[ $group_id ];
  }  private function acdc_ensure_session_document_space( $session_id ) {
    $session_id = absint( $session_id );
    if ( ! $session_id ) {
      return array();
    }
    global $wpdb;
    $session = $wpdb->get_row( $wpdb->prepare( "SELECT s.*, f.title AS formation_title FROM {$this->session_table} s LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id WHERE s.id = %d", $session_id ) );
    if ( ! $session ) {
      return array();
    }
    $spaces = get_option( 'acdc_of_session_document_spaces', array() );
    if ( ! is_array( $spaces ) ) {
      $spaces = array();
    }
    $spaces[ $session_id ] = array(
      'session_id'      => $session_id,
      'title'           => isset( $session->title ) ? sanitize_text_field( $session->title ) : '',
      'formation_id'    => isset( $session->formation_id ) ? (int) $session->formation_id : 0,
      'formation_title' => isset( $session->formation_title ) ? sanitize_text_field( $session->formation_title ) : '',
      'start_date'      => isset( $session->start_date ) ? sanitize_text_field( $session->start_date ) : '',
      'end_date'        => isset( $session->end_date ) ? sanitize_text_field( $session->end_date ) : '',
      'updated_at'      => $this->now_mysql(),
    );
    update_option( 'acdc_of_session_document_spaces', $spaces, false );
    return $spaces[ $session_id ];
  }  private function get_calendar_month_label( $timestamp ) {
    $months = array(
      1 => 'janvier',
      2 => 'février',
      3 => 'mars',
      4 => 'avril',
      5 => 'mai',
      6 => 'juin',
      7 => 'juillet',
      8 => 'août',
      9 => 'septembre',
      10 => 'octobre',
      11 => 'novembre',
      12 => 'décembre',
    );

    $month = (int) gmdate( 'n', $timestamp );
    $year = gmdate( 'Y', $timestamp );

    return ucfirst( $months[ $month ] ) . ' ' . $year;
  }  private function build_calendar_day_names() {
    return array( 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche' );
  }private function get_assignment_options() {
    $options = array( '' => '— Sélectionner —' );
    $users = get_users( array( 'orderby' => 'display_name', 'order' => 'ASC' ) );
    foreach ( $users as $user ) {
      $options[ $user->display_name ] = $user->display_name;
    }
    return $options;
  }  private function get_funders() {
    global $wpdb;
    return $wpdb->get_results( "SELECT * FROM {$this->funder_table} ORDER BY name ASC, created_at DESC, id DESC" );
  }  private function get_funder( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->funder_table} WHERE id = %d", $id ) );
  }  private function get_funder_type_options() {
    return array(
      '' => 'Choisir une option',
      'OPCO' => 'OPCO',
      'Entreprise' => 'Entreprise',
      'France Travail' => 'France Travail',
      'CPF' => 'CPF',
      'Autre' => 'Autre',
    );
  }  private function get_default_funder_dataset() {
    return array(
      array( 'name' => 'AFDAS', 'sector' => 'Culture médias sport loisirs', 'address' => '66 rue Stendhal', 'city' => 'Paris', 'postal_code' => '75020', 'phone' => '01 44 78 39 39', 'email' => '', 'website' => 'https://www.afdas.com', 'funder_type' => 'OPCO', 'contact_type' => 'Formulaire + régional', 'has_paca_presence' => 'Oui', 'paca_contact_details' => 'https://www.afdas.com/en-region/delegation-provence-alpes-cote-dazur-corse.html' ),
      array( 'name' => 'ATLAS', 'sector' => 'Banque assurance conseil', 'address' => '148 boulevard Haussmann', 'city' => 'Paris', 'postal_code' => '75008', 'phone' => '01 43 46 01 10', 'email' => '', 'website' => 'https://www.opco-atlas.fr', 'funder_type' => 'OPCO', 'contact_type' => 'Réseau régional', 'has_paca_presence' => 'Oui', 'paca_contact_details' => 'https://www.opco-atlas.fr/contact.html | PACA Corse : Naïma Latreche / Magali Rasamison' ),
      array( 'name' => 'AKTO', 'sector' => "Services intensifs main d'oeuvre", 'address' => '14 rue Riquet', 'city' => 'Paris', 'postal_code' => '75019', 'phone' => '', 'email' => '', 'website' => 'https://www.akto.fr', 'funder_type' => 'OPCO', 'contact_type' => 'Formulaire', 'has_paca_presence' => 'Oui', 'paca_contact_details' => 'https://www.akto.fr/nous-contacter/organismes-de-formation/' ),
      array( 'name' => 'OPCO EP', 'sector' => 'Entreprises de proximité', 'address' => '53 rue Ampère', 'city' => 'Paris', 'postal_code' => '75017', 'phone' => '09 70 83 88 37', 'email' => '', 'website' => 'https://www.opcoep.fr', 'funder_type' => 'OPCO', 'contact_type' => 'Téléphone + conseiller', 'has_paca_presence' => 'Oui', 'paca_contact_details' => 'https://www.opcoep.fr/nous-contacter | https://www.opcoep.fr/ressources/centre-ressources/contact/Carte-CF-PACA-opcoep.pdf' ),
      array( 'name' => 'OPCO Mobilités', 'sector' => 'Transport logistique', 'address' => '43 bis route de Vaugirard', 'city' => 'Meudon', 'postal_code' => '92190', 'phone' => '04 28 89 14 89', 'email' => 'pacac@opcomobilites.fr', 'website' => 'https://www.opcomobilites.fr', 'funder_type' => 'OPCO', 'contact_type' => 'Réseau régional', 'has_paca_presence' => 'Oui', 'paca_contact_details' => 'https://www.opcomobilites.fr/contacter-mon-conseiller/?region_id=13#contact-section | PACA Corse : 04 28 89 14 89 / pacac@opcomobilites.fr' ),
      array( 'name' => 'OPCO Santé', 'sector' => 'Sanitaire social', 'address' => '31 rue Anatole France', 'city' => 'Levallois-Perret', 'postal_code' => '92300', 'phone' => '04 13 68 00 15', 'email' => '', 'website' => 'https://www.opco-sante.fr', 'funder_type' => 'OPCO', 'contact_type' => 'Réseau régional', 'has_paca_presence' => 'Oui', 'paca_contact_details' => 'https://www.opco-sante.fr/contact/' ),
      array( 'name' => 'OPCO 2i', 'sector' => 'Industrie', 'address' => '55 rue de Châteaudun', 'city' => 'Paris', 'postal_code' => '75009', 'phone' => '0 805 69 03 57', 'email' => '', 'website' => 'https://www.opco2i.fr', 'funder_type' => 'OPCO', 'contact_type' => 'Réseau régional', 'has_paca_presence' => 'Oui', 'paca_contact_details' => 'https://www.opco2i.fr/nous-connaitre/opco-2i-dans-votre-region/2i-provence-alpes-cote-dazur-corse/' ),
      array( 'name' => 'Constructys', 'sector' => 'BTP', 'address' => '32 rue René Boulanger', 'city' => 'Paris', 'postal_code' => '75010', 'phone' => '', 'email' => '', 'website' => 'https://www.constructys.fr', 'funder_type' => 'OPCO', 'contact_type' => 'Réseau régional', 'has_paca_presence' => 'Oui', 'paca_contact_details' => 'https://www.constructys.fr/constructys-provence-alpes-cote-azur-corse/contacts/' ),
      array( 'name' => 'Opcommerce', 'sector' => 'Commerce', 'address' => '251 boulevard Pereire', 'city' => 'Paris', 'postal_code' => '75017', 'phone' => '04 42 25 18 05', 'email' => 'paca@lopcommerce.com', 'website' => 'https://www.lopcommerce.com', 'funder_type' => 'OPCO', 'contact_type' => 'Réseau régional', 'has_paca_presence' => 'Oui', 'paca_contact_details' => 'https://www.lopcommerce.com/contact/ | PACA : Alexandra MATHEY - Meyreuil' ),
      array( 'name' => 'Uniformation', 'sector' => 'Cohésion sociale', 'address' => '43 boulevard Diderot', 'city' => 'Paris', 'postal_code' => '75012', 'phone' => '', 'email' => '', 'website' => 'https://www.uniformation.fr', 'funder_type' => 'OPCO', 'contact_type' => 'Réseau régional', 'has_paca_presence' => 'Oui', 'paca_contact_details' => 'https://www.uniformation.fr/nous-contacter' ),
      array( 'name' => 'OCAPIAT', 'sector' => 'Agriculture agroalimentaire', 'address' => '153 rue de la Pompe', 'city' => 'Paris', 'postal_code' => '75179', 'phone' => '01 70 38 38 38', 'email' => '', 'website' => 'https://www.ocapiat.fr', 'funder_type' => 'OPCO', 'contact_type' => 'Réseau régional', 'has_paca_presence' => 'Oui', 'paca_contact_details' => 'https://www.ocapiat.fr/nous-contacter/' ),
    );
  }  private function acdc_seed_default_funders() {
    global $wpdb;
    $dataset = $this->get_default_funder_dataset();
    if ( empty( $dataset ) ) {
      return;
    }
    $now = current_time( 'mysql' );
    foreach ( $dataset as $row ) {
      $name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
      if ( '' === $name ) {
        continue;
      }
      $existing_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->funder_table} WHERE name = %s LIMIT 1", $name ) );
      $data = array(
        'name' => $name,
        'sector' => isset( $row['sector'] ) ? sanitize_text_field( $row['sector'] ) : '',
        'address' => isset( $row['address'] ) ? sanitize_text_field( $row['address'] ) : '',
        'city' => isset( $row['city'] ) ? sanitize_text_field( $row['city'] ) : '',
        'postal_code' => isset( $row['postal_code'] ) ? sanitize_text_field( $row['postal_code'] ) : '',
        'funder_type' => isset( $row['funder_type'] ) ? sanitize_text_field( $row['funder_type'] ) : 'OPCO',
        'phone' => isset( $row['phone'] ) ? sanitize_text_field( $row['phone'] ) : '',
        'email' => isset( $row['email'] ) ? sanitize_email( $row['email'] ) : '',
        'website' => isset( $row['website'] ) ? esc_url_raw( $row['website'] ) : '',
        'contact_type' => isset( $row['contact_type'] ) ? sanitize_text_field( $row['contact_type'] ) : '',
        'has_paca_presence' => isset( $row['has_paca_presence'] ) ? sanitize_text_field( $row['has_paca_presence'] ) : '',
        'paca_contact_details' => isset( $row['paca_contact_details'] ) ? sanitize_textarea_field( $row['paca_contact_details'] ) : '',
        'updated_at' => $now,
      );
      if ( $existing_id > 0 ) {
        $wpdb->update( $this->funder_table, $data, array( 'id' => $existing_id ) );
      } else {
        $data['created_at'] = $now;
        $wpdb->insert( $this->funder_table, $data );
      }
    }
  }  private function get_trainers( $search = '' ) {
    global $wpdb;
    $where = '';
    if ( '' !== trim( (string) $search ) ) {
      $like = '%' . $wpdb->esc_like( trim( (string) $search ) ) . '%';
      $where = $wpdb->prepare( "WHERE first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s OR role_name LIKE %s OR trainer_type LIKE %s", $like, $like, $like, $like, $like, $like );
    }
    return $wpdb->get_results( "SELECT * FROM {$this->trainer_table} {$where} ORDER BY created_at DESC, id DESC" );
  }  private function get_trainer( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->trainer_table} WHERE id = %d", $id ) );
  }  private function get_trainer_gender_options() {
    return array( '' => 'Choisir une option', 'Femme' => 'Femme', 'Homme' => 'Homme', 'Autre' => 'Autre' );
  }  private function get_trainer_role_options() {
    return array( 'Formateur simple' => 'Formateur simple', 'Formateur autonome' => 'Formateur autonome' );
  }  private function get_trainer_type_options() {
    return array( '' => 'Choisir une option', 'Interne' => 'Interne', 'Externe' => 'Externe' );
  }  private function get_trainer_access_matrix() {
    return array(
      array( 'Menu : Tableau de bord', array( 'simple' => true, 'autonome' => true ) ),
      array( 'Menu : CRM', array( 'simple' => false, 'autonome' => false ) ),
      array( 'Menu : Répertoire - Apprenants', array( 'simple' => 'partial', 'autonome' => 'partial' ) ),
      array( 'Menu : Répertoire - Groupes', array( 'simple' => 'partial', 'autonome' => 'partial' ) ),
      array( 'Menu : Répertoire - Entreprises', array( 'simple' => false, 'autonome' => false ) ),
      array( 'Menu : Répertoire - Formations', array( 'simple' => true, 'autonome' => true ) ),
      array( 'Menu : Répertoire - Formateurs', array( 'simple' => false, 'autonome' => false ) ),
      array( 'Menu : Répertoire - Utilisateurs', array( 'simple' => false, 'autonome' => false ) ),
      array( 'Menu : Inscription / Suivi', array( 'simple' => false, 'autonome' => false ) ),
      array( 'Menu : Séances', array( 'simple' => 'partial', 'autonome' => 'partial' ) ),
      array( 'Menu : Quiz / Enquêtes / Éval.', array( 'simple' => true, 'autonome' => true ) ),
      array( 'Menu : Documents', array( 'simple' => 'partial', 'autonome' => 'partial' ) ),
      array( 'Menu : E-mails et relances', array( 'simple' => false, 'autonome' => false ) ),
      array( 'Menu : Divers', array( 'simple' => false, 'autonome' => true ) ),
      array( 'Menu : Devis & Factures', array( 'simple' => false, 'autonome' => false ) ),
      array( 'Menu : Statistiques', array( 'simple' => false, 'autonome' => false ) ),
    );
  }

  /**
   * ACDC 3.20.82 — Permissions actives du portail formateur.
   * Liste des interrupteurs par formateur, avec valeurs par défaut selon le profil.
   * Ces clés sont stockées en JSON dans wp_acdc_of_trainers.permissions_json.
   *
   * @return array<string,array{label:string,description:string,defaults:array<string,bool>}>
   */
  protected function get_acdc_trainer_permissions_definition() {
    return array(
      'view_dashboard' => array(
        'label'       => 'Tableau de bord',
        'description' => "Affiche le tableau de bord d’accueil du portail formateur.",
        'defaults'    => array( 'simple' => true,  'autonome' => true ),
      ),
      'edit_own_profile' => array(
        'label'       => 'Modifier sa fiche personnelle',
        'description' => "Permet au formateur de mettre à jour ses coordonnées, sa bio et sa photo.",
        'defaults'    => array( 'simple' => true,  'autonome' => true ),
      ),
      'manage_own_documents' => array(
        'label'       => 'Gérer ses justificatifs',
        'description' => "Permet d’ajouter, voir et remplacer ses CV, diplômes, attestations URSSAF, RC pro, etc.",
        'defaults'    => array( 'simple' => true,  'autonome' => true ),
      ),
      'view_own_sessions' => array(
        'label'       => 'Voir ses sessions',
        'description' => "Liste des sessions à venir et passées sur lesquelles le formateur est affecté.",
        'defaults'    => array( 'simple' => true,  'autonome' => true ),
      ),
      'view_session_learners' => array(
        'label'       => 'Voir les apprenants de ses sessions',
        'description' => "Liste des apprenants inscrits aux sessions du formateur (nom, prénom uniquement).",
        'defaults'    => array( 'simple' => true,  'autonome' => true ),
      ),
      'view_learner_personal_data' => array(
        'label'       => 'Voir les données personnelles des apprenants',
        'description' => "Donne accès aux coordonnées (e-mail, téléphone, adresse) — usage RGPD à arbitrer.",
        'defaults'    => array( 'simple' => false, 'autonome' => true ),
      ),
      'manage_resources' => array(
        'label'       => 'Mettre des ressources à disposition des apprenants',
        'description' => "Téléverser des supports pédagogiques visibles dans l’extranet apprenant.",
        'defaults'    => array( 'simple' => true,  'autonome' => true ),
      ),
      'mark_attendance' => array(
        'label'       => 'Émarger les sessions',
        'description' => "Confirmer la présence des apprenants sur les sessions animées.",
        'defaults'    => array( 'simple' => false, 'autonome' => true ),
      ),
      'view_evaluations' => array(
        'label'       => 'Consulter les évaluations',
        'description' => "Accès aux résultats d’évaluation des apprenants sur ses sessions.",
        'defaults'    => array( 'simple' => false, 'autonome' => true ),
      ),
      'send_messages_to_learners' => array(
        'label'       => 'Envoyer des messages aux apprenants',
        'description' => "Permet de communiquer via la messagerie interne du portail.",
        'defaults'    => array( 'simple' => false, 'autonome' => true ),
      ),
      'view_qualiopi_status' => array(
        'label'       => 'Voir son statut Qualiopi',
        'description' => "Indicateur de validité administrative (URSSAF, RC pro, dates d’expiration).",
        'defaults'    => array( 'simple' => true,  'autonome' => true ),
      ),
      /* ACDC 3.21.03.2 — Permissions sur le module Quiz / Test / Évaluation côté portail formateur. */
      'view_own_quizzes' => array(
        'label'       => 'Voir ses quiz',
        'description' => "Affiche la liste des quiz, tests de positionnement et évaluations rattachés aux formations animées par le formateur.",
        'defaults'    => array( 'simple' => true,  'autonome' => true ),
      ),
      'dispatch_quiz' => array(
        'label'       => 'Envoyer un quiz par e-mail',
        'description' => "Permet d'envoyer un quiz actif aux apprenants depuis le portail formateur, et de gérer les invitations (relances, prolongations, annulations).",
        'defaults'    => array( 'simple' => false, 'autonome' => true ),
      ),
      'create_quiz' => array(
        'label'       => 'Créer et modifier des quiz',
        'description' => "Permet au formateur de créer ses propres quiz, et de les modifier tant qu'ils ne sont pas verrouillés.",
        'defaults'    => array( 'simple' => false, 'autonome' => true ),
      ),
    );
  }

  /**
   * ACDC 3.20.82 — Renvoie les permissions actives d'un formateur sous forme tableau associatif clé => bool.
   * Si aucune valeur n'est stockée, applique les valeurs par défaut du profil.
   *
   * @param object|null $trainer Objet formateur (ou null).
   * @return array<string,bool>
   */
  protected function get_acdc_trainer_active_permissions( $trainer ) {
    $defs    = $this->get_acdc_trainer_permissions_definition();
    $profile = $trainer && isset( $trainer->permission_profile ) ? (string) $trainer->permission_profile : 'simple';
    if ( ! in_array( $profile, array( 'simple', 'autonome', 'custom' ), true ) ) {
      $profile = 'simple';
    }
    $stored = array();
    if ( $trainer && ! empty( $trainer->permissions_json ) ) {
      $decoded = json_decode( (string) $trainer->permissions_json, true );
      if ( is_array( $decoded ) ) {
        $stored = $decoded;
      }
    }
    $result = array();
    foreach ( $defs as $key => $row ) {
      if ( 'custom' === $profile && array_key_exists( $key, $stored ) ) {
        $result[ $key ] = (bool) $stored[ $key ];
      } else {
        $default_profile = ( 'autonome' === $profile ) ? 'autonome' : 'simple';
        $result[ $key ]  = ! empty( $row['defaults'][ $default_profile ] );
      }
    }
    return $result;
  }

  /**
   * ACDC 3.20.82 — Vérification de capability métier pour un formateur connecté.
   * À appeler depuis le futur portail formateur avant chaque action sensible.
   *
   * @param int    $trainer_id  ID du formateur ciblé.
   * @param string $permission  Clé de permission (ex. "manage_resources").
   * @return bool
   */
  protected function trainer_can( $trainer_id, $permission ) {
    $trainer = $this->get_trainer( (int) $trainer_id );
    if ( ! $trainer ) {
      return false;
    }
    $perms = $this->get_acdc_trainer_active_permissions( $trainer );
    return ! empty( $perms[ $permission ] );
  }

  /* ====================================================================
   * ACDC 3.20.86 — Bibliothèque personnelle du formateur (CV, diplômes, attestations).
   * ==================================================================== */

  /**
   * Catégories de documents supportées + libellé + indicateur de date d'expiration recommandée.
   *
   * @return array<string,array{label:string,icon:string,expiry_recommended:bool}>
   */
  protected function get_acdc_trainer_document_categories() {
    return array(
      'cv'                 => array( 'label' => 'CV',                              'icon' => 'media-document',  'expiry_recommended' => false ),
      'diploma'            => array( 'label' => 'Diplômes',                        'icon' => 'awards',          'expiry_recommended' => false ),
      'certification'      => array( 'label' => 'Certifications',                  'icon' => 'star-filled',     'expiry_recommended' => true ),
      'urssaf_attestation' => array( 'label' => 'Attestation URSSAF (vigilance)',  'icon' => 'shield-alt',      'expiry_recommended' => true ),
      'rc_pro'             => array( 'label' => 'Assurance RC Pro',                'icon' => 'shield',          'expiry_recommended' => true ),
      'mandate'            => array( 'label' => 'Mandats et conventions',          'icon' => 'media-text',      'expiry_recommended' => false ),
      'qualiopi_proof'     => array( 'label' => 'Preuves Qualiopi',                'icon' => 'yes-alt',         'expiry_recommended' => false ),
      /* ACDC 3.22.7 — Catégorie Contrats formateurs (générée automatiquement depuis acdc_of_trainer_contracts). */
      'trainer_contract'   => array( 'label' => 'Contrats formateurs',             'icon' => 'media-document',  'expiry_recommended' => false, 'readonly' => true ),
      'other'              => array( 'label' => 'Autres justificatifs',            'icon' => 'media-default',   'expiry_recommended' => false ),
    );
  }

  /**
   * Types MIME autorisés pour la bibliothèque.
   * Limite : 100 Mo par fichier.
   */
  protected function get_acdc_trainer_document_allowed_mimes() {
    return array(
      'pdf'  => 'application/pdf',
      'jpg'  => 'image/jpeg',
      'jpeg' => 'image/jpeg',
      'png'  => 'image/png',
      'doc'  => 'application/msword',
      'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    );
  }

  protected function get_acdc_trainer_document_max_size_bytes() {
    return 100 * 1024 * 1024; // 100 Mo
  }

  /**
   * Garantit l'existence du dossier d'upload sécurisé pour la bibliothèque formateur,
   * avec un .htaccess qui interdit l'accès direct (l'accès se fait uniquement via le
   * handler download contrôlé par capability).
   *
   * @return array{base_dir:string,base_url:string}|null
   */
  protected function ensure_trainer_documents_upload_dir() {
    $uploads = wp_upload_dir();
    if ( empty( $uploads['basedir'] ) ) {
      return null;
    }
    $root = trailingslashit( $uploads['basedir'] ) . 'acdc-of/trainer-documents';
    if ( ! file_exists( $root ) ) {
      wp_mkdir_p( $root );
    }
    // .htaccess de protection (bloque l'accès direct).
    $ht = trailingslashit( $root ) . '.htaccess';
    if ( ! file_exists( $ht ) ) {
      $rules  = "# ACDC 3.20.86 — Documents formateurs : accès direct interdit.\n";
      $rules .= "Order deny,allow\n";
      $rules .= "Deny from all\n";
      @file_put_contents( $ht, $rules );
    }
    // index.php vide pour empêcher tout listing résiduel.
    $idx = trailingslashit( $root ) . 'index.php';
    if ( ! file_exists( $idx ) ) {
      @file_put_contents( $idx, "<?php\n// Silence is golden.\n" );
    }
    return array(
      'base_dir' => $root,
      'base_url' => trailingslashit( $uploads['baseurl'] ) . 'acdc-of/trainer-documents',
    );
  }

  /**
   * Reconstruit un chemin absolu sécurisé à partir d'un chemin relatif stocké en BD.
   * Empêche les path traversal en validant que le chemin résolu est bien sous notre dossier.
   *
   * @param string $relative_path Chemin relatif (ex. "12/cv_1700000000_cv-david.pdf").
   * @return string|null Chemin absolu valide ou null.
   */
  protected function get_trainer_document_absolute_path( $relative_path ) {
    $relative_path = ltrim( (string) $relative_path, '/\\' );
    if ( '' === $relative_path || false !== strpos( $relative_path, '..' ) ) {
      return null;
    }
    $dirs = $this->ensure_trainer_documents_upload_dir();
    if ( ! $dirs ) {
      return null;
    }
    $absolute = trailingslashit( $dirs['base_dir'] ) . $relative_path;
    $real     = realpath( $absolute );
    if ( ! $real || 0 !== strpos( $real, realpath( $dirs['base_dir'] ) ) ) {
      return null;
    }
    return $real;
  }

  /**
   * Liste les documents d'un formateur, regroupés par catégorie.
   *
   * @param int $trainer_id
   * @return array<string,array<int,object>> map catégorie => liste de docs (objets DB)
   */
  protected function get_trainer_documents_grouped( $trainer_id ) {
    global $wpdb;
    $rows = $wpdb->get_results( $wpdb->prepare(
      "SELECT * FROM {$this->trainer_document_table} WHERE trainer_id = %d ORDER BY category ASC, created_at DESC",
      absint( $trainer_id )
    ) );
    $cats = $this->get_acdc_trainer_document_categories();
    $out  = array();
    foreach ( array_keys( $cats ) as $key ) {
      $out[ $key ] = array();
    }
    foreach ( (array) $rows as $row ) {
      $cat = isset( $row->category ) ? (string) $row->category : 'other';
      if ( ! isset( $out[ $cat ] ) ) {
        $out[ 'other' ][] = $row;
      } else {
        $out[ $cat ][] = $row;
      }
    }
    return $out;
  }

  /**
   * Retourne un libellé d'expiration : "Expire le DD/MM/AAAA" + style selon proximité.
   *
   * @return array{label:string,state:string} state ∈ ok|warning|expired|none
   */
  protected function describe_trainer_document_expiry( $expires_at ) {
    if ( empty( $expires_at ) || '0000-00-00' === $expires_at ) {
      return array( 'label' => '', 'state' => 'none' );
    }
    $ts = strtotime( $expires_at );
    if ( ! $ts ) {
      return array( 'label' => '', 'state' => 'none' );
    }
    $today    = strtotime( wp_date( 'Y-m-d' ) );
    $diff_days = (int) floor( ( $ts - $today ) / DAY_IN_SECONDS );
    $formatted = wp_date( 'd/m/Y', $ts );
    if ( $diff_days < 0 ) {
      return array( 'label' => 'Expiré le ' . $formatted, 'state' => 'expired' );
    }
    if ( $diff_days <= 60 ) {
      return array( 'label' => 'Expire le ' . $formatted . ' (' . $diff_days . ' j)', 'state' => 'warning' );
    }
    return array( 'label' => 'Expire le ' . $formatted, 'state' => 'ok' );
  }

  /* ====================================================================
   * ACDC 3.20.89 — Édition du profil par le formateur lui-même.
   * Allowlist stricte des champs éditables côté portail.
   * ==================================================================== */

  /**
   * Liste blanche des champs de la table trainers que le formateur peut modifier
   * depuis son propre portail. Tous les autres champs (e-mail, NDA, SIRET,
   * statut juridique, codes NSF, permissions, etc.) restent réservés à l'admin.
   *
   * @return array<int,string>
   */
  protected function get_acdc_trainer_self_editable_fields() {
    return array(
      'gender',
      'first_name',
      'last_name',
      'phone',
      'birth_date',
      'description_text',
      'session_reminder_enabled',
      'session_start_enabled',
      'learner_info_photo',
      'learner_info_name',
      'learner_info_description',
      'learner_info_availability',
    );
  }

  /**
   * Types MIME autorisés pour la photo de profil formateur.
   * Limite : 5 Mo.
   */
  protected function get_acdc_trainer_photo_allowed_mimes() {
    return array(
      'jpg'  => 'image/jpeg',
      'jpeg' => 'image/jpeg',
      'png'  => 'image/png',
      'webp' => 'image/webp',
    );
  }

  protected function get_acdc_trainer_photo_max_size_bytes() {
    return 5 * 1024 * 1024; // 5 Mo
  }

  /* ====================================================================
   * ACDC 3.20.90 — Calendrier annuel des disponibilités du formateur.
   *
   * Modèle BD (champ availability_json) :
   * {
   *   "weekly": {
   *     "lundi":    { "morning": true, "afternoon": true },
   *     ...
   *   },
   *   "exceptions": {
   *     "2026-08-15": { "morning": false, "afternoon": false, "note": "Vacances" },
   *     ...
   *   }
   * }
   *
   * Rétrocompatibilité : les anciennes valeurs ("Disponible"/"Fermé"/"Matin
   * uniquement"/"Après-midi uniquement") sont automatiquement converties en lecture.
   * ==================================================================== */

  /**
   * Liste ordonnée des jours de la semaine, clé interne (en minuscules).
   *
   * @return array<int,string>
   */
  protected function get_acdc_weekday_keys() {
    return array( 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche' );
  }

  /**
   * Liste des libellés pour affichage.
   */
  protected function get_acdc_weekday_labels() {
    return array(
      'lundi'    => 'Lundi',
      'mardi'    => 'Mardi',
      'mercredi' => 'Mercredi',
      'jeudi'    => 'Jeudi',
      'vendredi' => 'Vendredi',
      'samedi'   => 'Samedi',
      'dimanche' => 'Dimanche',
    );
  }

  /**
   * Schéma par défaut pour un nouveau formateur : Lun-Ven dispo matin+après-midi, week-end fermé.
   */
  protected function get_default_trainer_availability() {
    return array(
      'weekly' => array(
        'lundi'    => array( 'morning' => true,  'afternoon' => true  ),
        'mardi'    => array( 'morning' => true,  'afternoon' => true  ),
        'mercredi' => array( 'morning' => true,  'afternoon' => true  ),
        'jeudi'    => array( 'morning' => true,  'afternoon' => true  ),
        'vendredi' => array( 'morning' => true,  'afternoon' => true  ),
        'samedi'   => array( 'morning' => false, 'afternoon' => false ),
        'dimanche' => array( 'morning' => false, 'afternoon' => false ),
      ),
      'exceptions' => array(),
    );
  }

  /**
   * Convertit une valeur legacy ("Disponible"/"Fermé"/"Matin uniquement"/"Après-midi uniquement")
   * vers la structure { morning, afternoon }.
   */
  private function legacy_availability_to_halfdays( $value ) {
    $value = is_string( $value ) ? trim( $value ) : '';
    switch ( strtolower( remove_accents( $value ) ) ) {
      case 'disponible':
        return array( 'morning' => true, 'afternoon' => true );
      case 'matin uniquement':
        return array( 'morning' => true, 'afternoon' => false );
      case 'apres-midi uniquement':
      case 'apres midi uniquement':
        return array( 'morning' => false, 'afternoon' => true );
      case 'ferme':
      default:
        return array( 'morning' => false, 'afternoon' => false );
    }
  }

  /**
   * Parse le JSON brut en BD vers la structure normalisée.
   * Gère :
   * - Nouveau format (weekly + exceptions)
   * - Ancien format (lundi=>"Disponible", etc.)
   * - JSON null/vide → schéma par défaut
   *
   * @param string|null $availability_json
   * @return array{weekly:array<string,array{morning:bool,afternoon:bool}>,exceptions:array<string,array{morning:bool,afternoon:bool,note:string}>}
   */
  protected function parse_trainer_availability( $availability_json ) {
    $default = $this->get_default_trainer_availability();
    if ( empty( $availability_json ) ) {
      return $default;
    }
    $data = json_decode( (string) $availability_json, true );
    if ( ! is_array( $data ) || empty( $data ) ) {
      return $default;
    }

    // Détection : nouveau format ?
    if ( isset( $data['weekly'] ) && is_array( $data['weekly'] ) ) {
      $weekly = array();
      foreach ( $this->get_acdc_weekday_keys() as $day ) {
        $row = isset( $data['weekly'][ $day ] ) && is_array( $data['weekly'][ $day ] ) ? $data['weekly'][ $day ] : array();
        $weekly[ $day ] = array(
          'morning'   => ! empty( $row['morning'] ),
          'afternoon' => ! empty( $row['afternoon'] ),
        );
      }
      $exceptions = array();
      if ( isset( $data['exceptions'] ) && is_array( $data['exceptions'] ) ) {
        foreach ( $data['exceptions'] as $date_key => $exc ) {
          if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date_key ) ) {
            continue;
          }
          if ( ! is_array( $exc ) ) {
            continue;
          }
          $exceptions[ $date_key ] = array(
            'morning'   => ! empty( $exc['morning'] ),
            'afternoon' => ! empty( $exc['afternoon'] ),
            'note'      => isset( $exc['note'] ) ? (string) $exc['note'] : '',
          );
        }
      }
      return array( 'weekly' => $weekly, 'exceptions' => $exceptions );
    }

    // Sinon : ancien format (lundi=>"Disponible", etc.).
    $weekly = array();
    foreach ( $this->get_acdc_weekday_keys() as $day ) {
      $legacy_value = isset( $data[ $day ] ) ? $data[ $day ] : 'Fermé';
      $weekly[ $day ] = $this->legacy_availability_to_halfdays( $legacy_value );
    }
    return array( 'weekly' => $weekly, 'exceptions' => array() );
  }

  /**
   * Sérialise la structure vers JSON pour stockage en BD.
   * Nettoie automatiquement les exceptions qui correspondent au schéma habituel
   * (évite de garder en BD des exceptions inutiles).
   */
  protected function serialize_trainer_availability( $structure ) {
    $weekly = isset( $structure['weekly'] ) && is_array( $structure['weekly'] ) ? $structure['weekly'] : $this->get_default_trainer_availability()['weekly'];
    $exceptions_raw = isset( $structure['exceptions'] ) && is_array( $structure['exceptions'] ) ? $structure['exceptions'] : array();

    // Filtrer les exceptions qui correspondent au schéma habituel.
    $exceptions = array();
    foreach ( $exceptions_raw as $date_key => $exc ) {
      if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date_key ) ) {
        continue;
      }
      $weekday = $this->date_to_weekday_key( $date_key );
      if ( ! $weekday ) {
        continue;
      }
      $base = isset( $weekly[ $weekday ] ) ? $weekly[ $weekday ] : array( 'morning' => false, 'afternoon' => false );
      if ( ! empty( $exc['morning'] ) === ! empty( $base['morning'] ) && ! empty( $exc['afternoon'] ) === ! empty( $base['afternoon'] ) ) {
        // L'exception correspond au schéma habituel → on la retire.
        continue;
      }
      $exceptions[ $date_key ] = array(
        'morning'   => ! empty( $exc['morning'] ),
        'afternoon' => ! empty( $exc['afternoon'] ),
        'note'      => isset( $exc['note'] ) ? sanitize_text_field( (string) $exc['note'] ) : '',
      );
    }

    return wp_json_encode( array( 'weekly' => $weekly, 'exceptions' => $exceptions ), JSON_UNESCAPED_UNICODE );
  }

  /**
   * Convertit une date YYYY-MM-DD en clé de jour de semaine (lundi, mardi, ...).
   *
   * @return string|null
   */
  protected function date_to_weekday_key( $date_str ) {
    $ts = strtotime( $date_str );
    if ( ! $ts ) {
      return null;
    }
    $w = (int) wp_date( 'N', $ts ); // 1 = Lundi, 7 = Dimanche
    $map = array( 1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche' );
    return isset( $map[ $w ] ) ? $map[ $w ] : null;
  }

  /**
   * Calcule l'état d'une demi-journée à une date donnée pour un formateur.
   *
   * @param array  $availability Structure normalisée (issue de parse_trainer_availability).
   * @param string $date_str     YYYY-MM-DD
   * @return array{morning:bool,afternoon:bool,is_exception:bool,note:string}
   */
  protected function get_trainer_day_availability( $availability, $date_str ) {
    if ( isset( $availability['exceptions'][ $date_str ] ) ) {
      $exc = $availability['exceptions'][ $date_str ];
      return array(
        'morning'      => ! empty( $exc['morning'] ),
        'afternoon'    => ! empty( $exc['afternoon'] ),
        'is_exception' => true,
        'note'         => isset( $exc['note'] ) ? (string) $exc['note'] : '',
      );
    }
    $weekday = $this->date_to_weekday_key( $date_str );
    if ( ! $weekday || ! isset( $availability['weekly'][ $weekday ] ) ) {
      return array( 'morning' => false, 'afternoon' => false, 'is_exception' => false, 'note' => '' );
    }
    $base = $availability['weekly'][ $weekday ];
    return array(
      'morning'      => ! empty( $base['morning'] ),
      'afternoon'    => ! empty( $base['afternoon'] ),
      'is_exception' => false,
      'note'         => '',
    );
  }

  private function get_quizzes( $search = '' ) {
    global $wpdb;
    $where = '';
    if ( '' !== trim( (string) $search ) ) {
      $like = '%' . $wpdb->esc_like( trim( (string) $search ) ) . '%';
      $where = $wpdb->prepare( "WHERE title LIKE %s OR description_text LIKE %s OR correction_type LIKE %s", $like, $like, $like );
    }
    return $wpdb->get_results( "SELECT * FROM {$this->quiz_table} {$where} ORDER BY updated_at DESC, id DESC" );
  }  private function get_quiz( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->quiz_table} WHERE id = %d", $id ) );
  }  private function get_quiz_correction_options() {
    return array(
      'Correction automatique' => 'Correction automatique',
      'Sans correction automatique' => 'Sans correction automatique',
    );
  }  private function get_quiz_question_type_options() {
    return array(
      '' => 'Choisir une option',
      'Choix unique' => 'Choix unique',
      'Choix multiples' => 'Choix multiples',
      'Question ouverte' => 'Question ouverte',
      'Cases à cocher' => 'Cases à cocher',
      'Notation' => 'Notation',
    );
  }  private function get_quiz_notation_type_options() {
    return array(
      '' => 'Choisir une option',
      'Acquis / Non acquis' => 'Acquis / Non acquis',
      'Niveaux' => 'Niveaux',
      'Score sur 20' => 'Score sur 20',
      'Pourcentage' => 'Pourcentage',
    );
  }  private function datetime_local_value( $value ) {
    if ( empty( $value ) || '0000-00-00 00:00:00' === $value ) {
      return '';
    }
    $timestamp = strtotime( $value );
    /* ACDC — Fuseau : les champs datetime-local sont saisis et affichés en heure locale
       (stockage cohérent avec current_time('mysql') + affichage mysql2date). On conserve
       donc l'heure « murale » telle quelle, sans décalage gmt_offset (qui provoquait un
       −2 h à l'affichage : 17:00 saisi devenait 15:00). */
    return $timestamp ? gmdate( 'Y-m-d\TH:i', $timestamp ) : '';
  }  private function datetime_from_local( $value ) {
    $value = is_string( $value ) ? trim( $value ) : '';
    if ( '' === $value ) {
      return null;
    }
    $value = $this->acdc_normalize_fr_datetime( $value );
    $timestamp = strtotime( $value );
    if ( ! $timestamp ) {
      return null;
    }
    /* ACDC — Fuseau : stockage de l'heure locale « murale » telle que saisie (voir
       datetime_local_value). Aucun décalage gmt_offset, sinon −2 h à l'affichage et,
       pour une date sans heure, le jour bascule la veille. */
    return gmdate( 'Y-m-d H:i:s', $timestamp );
  }

  /**
   * Normalise une date française « jj/mm/aaaa » (avec heure optionnelle) en ISO
   * « aaaa-mm-jj hh:mm:ss ». Indispensable avant strtotime(), qui interprète les dates
   * à slashes au format américain m/d/Y (« 03/08/2026 » → 8 mars) et inverse mois/jour.
   * Les valeurs déjà en ISO (champs datetime-local : « 2026-08-03T17:00 ») ne matchent
   * pas le motif et sont renvoyées telles quelles.
   */
  private function acdc_normalize_fr_datetime( $value ) {
    $value = is_string( $value ) ? trim( $value ) : '';
    if ( '' === $value ) {
      return $value;
    }
    if ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})(?:[\sT]+(\d{1,2}):(\d{2}))?#', $value, $m ) ) {
      if ( checkdate( (int) $m[2], (int) $m[1], (int) $m[3] ) ) {
        return sprintf(
          '%04d-%02d-%02d %02d:%02d:00',
          (int) $m[3], (int) $m[2], (int) $m[1],
          isset( $m[4] ) ? (int) $m[4] : 0,
          isset( $m[5] ) ? (int) $m[5] : 0
        );
      }
    }
    return $value;
  }


  /**
   * ACDC 3.25.226 — UNE CONVENTION ANNONÇAIT 180 000 € POUR 1 800 €.
   *
   * Cette fonction supprimait TOUS les points avant de convertir, en supposant
   * que le point ne peut être qu'un séparateur de milliers à la française.
   * C'est vrai pour « 1.800,00 », c'est faux pour « 1800.00 » — la forme sous
   * laquelle la base stocke un décimal. Le tarif d'une convention passait donc
   * de 1 800,00 à 180 000,00 : cent fois trop, sur un document contractuel
   * signé électroniquement et transmis au commanditaire et au financeur. Le
   * devis, qui n'emprunte pas ce chemin, imprimait le bon montant — deux pièces
   * du même dossier se contredisaient d'un facteur cent.
   *
   * La règle appliquée est celle qu'un lecteur humain applique sans y penser :
   * le séparateur décimal est le DERNIER rencontré, sauf s'il est seul de son
   * espèce et suivi d'exactement trois chiffres, auquel cas il sépare les
   * milliers. « 1800.00 » → 1800,00. « 1.800,00 » → 1800,00. « 1,800.00 » →
   * 1800,00. « 1.800 » → 1800,00. « 1800.5 » → 1800,50.
   *
   * Le cas « 1.800 » reste ambigu par nature ; on tranche vers la lecture
   * française, qui est celle des saisies de ce plugin — et sous-estimer un
   * prix de mille fois serait aussi grave que le surestimer.
   */
  private function normalize_price_number( $value, $decimals = 2 ) {
    $value = is_scalar( $value ) ? (string) $value : '';
    $value = html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES, 'UTF-8' );
    $value = str_replace( array( '€', ' ', "\xc2\xa0" ), '', $value );
    $value = preg_replace( '/[^0-9,.-]/u', '', $value ); // strip "HT", "TTC" etc.

    $negative = ( 0 === strpos( $value, '-' ) );
    $value    = str_replace( '-', '', $value );

    $last_comma = strrpos( $value, ',' );
    $last_dot   = strrpos( $value, '.' );

    if ( false === $last_comma && false === $last_dot ) {
      $number = is_numeric( $value ) ? (float) $value : 0.0;
      return number_format( $negative ? -$number : $number, (int) $decimals, ',', '' );
    }

    $sep_pos  = max( false === $last_comma ? -1 : $last_comma, false === $last_dot ? -1 : $last_dot );
    $fraction = substr( $value, $sep_pos + 1 );
    $only_one_kind = ( false === $last_comma || false === $last_dot );

    /* Séparateur unique de son espèce suivi de trois chiffres : des milliers. */
    if ( $only_one_kind && 3 === strlen( $fraction ) && ctype_digit( $fraction ) && 1 === substr_count( $value, $value[ $sep_pos ] ) ) {
      $number = (float) preg_replace( '/[^0-9]/', '', $value );
      return number_format( $negative ? -$number : $number, (int) $decimals, ',', '' );
    }

    $integer = preg_replace( '/[^0-9]/', '', substr( $value, 0, $sep_pos ) );
    $number  = (float) ( ( '' === $integer ? '0' : $integer ) . '.' . preg_replace( '/[^0-9]/', '', $fraction ) );

    return number_format( $negative ? -$number : $number, (int) $decimals, ',', '' );
  }  private function get_activity_reports_scope() {
    $scope = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : '';
    return in_array( $scope, array( 'trainers', 'business_managers' ), true ) ? $scope : '';
  }  private function format_currency_eur( $value ) {
    $number = (float) str_replace( array( '€', ' ', ',' ), array( '', '', '.' ), (string) $value );
    return number_format_i18n( $number, 2 ) . ' €';
  }  private function get_guide_button_html( $message = '' ) {
    $message = trim( (string) $message );
    if ( '' === $message ) {
      $message = 'Consultez le contexte de la page, renseignez les champs obligatoires, puis validez pour enregistrer.';
    }
    return "<button type=\"button\" class=\"acdc-button acdc-button-primary\" onclick=\"window.alert(" . esc_attr( wp_json_encode( $message ) ) . ");\">Afficher le guide d'utilisation</button>";
  }  private function get_custom_links_records() {
    return array(
      array(
        'title' => 'Catalogue de formations',
        'url'   => 'https://acdcformation.com/catalogue/',
      ),
      array(
        'title' => 'Portail de connexion extranet apprenant',
        'url'   => '',
      ),
      array(
        'title' => 'Portail de connexion extranet formateurs',
        'url'   => '',
      ),
    );
  }  private function get_performance_statistics_scope() {
    $scope = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : '';
    return in_array( $scope, array( 'progress', 'satisfaction_learners', 'satisfaction_partners', 'success' ), true ) ? $scope : '';
  }  private function get_financial_statistics_scope() {
    $scope = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : '';
    return in_array( $scope, array( 'potential', 'action', 'ancillary' ), true ) ? $scope : '';
  }  private function get_completed_quiz_documents( $args = array() ) {
    global $wpdb;

    $defaults = array(
      'search'   => '',
      'limit'    => 25,
      'offset'   => 0,
      'count'    => false,
    );
    $args = wp_parse_args( $args, $defaults );

    $where = array( "(LOWER(d.document_type) LIKE '%quiz%' OR LOWER(d.title) LIKE '%quiz%')" );
    $values = array();

    if ( '' !== $args['search'] ) {
      $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
      $where[] = '(d.title LIKE %s OR d.document_type LIKE %s OR e.name LIKE %s OR CONCAT(COALESCE(c.first_name, ""), " ", COALESCE(c.last_name, "")) LIKE %s)';
      array_push( $values, $like, $like, $like, $like );
    }

    $where_sql = implode( ' AND ', $where );

    if ( ! empty( $args['count'] ) ) {
      $sql = "SELECT COUNT(*) FROM {$this->document_table} d LEFT JOIN {$this->company_table} e ON e.id = d.company_id LEFT JOIN {$this->contact_table} c ON c.id = d.contact_id WHERE {$where_sql}";
      if ( ! empty( $values ) ) {
        $sql = $wpdb->prepare( $sql, $values );
      }
      return (int) $wpdb->get_var( $sql );
    }

    $limit = max( 1, (int) $args['limit'] );
    $offset = max( 0, (int) $args['offset'] );
    $values[] = $limit;
    $values[] = $offset;

    $sql = "SELECT d.*, e.name AS company_name, CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) AS contact_name FROM {$this->document_table} d LEFT JOIN {$this->company_table} e ON e.id = d.company_id LEFT JOIN {$this->contact_table} c ON c.id = d.contact_id WHERE {$where_sql} ORDER BY d.created_at DESC LIMIT %d OFFSET %d";
    $sql = $wpdb->prepare( $sql, $values );
    return $wpdb->get_results( $sql );
  }  private function get_attendance_sheet_entries( $search = '' ) {
    $items = $this->get_validated_sessions( array( 'search' => $search ) );
    if ( empty( $items ) ) {
      return array();
    }

    foreach ( $items as $item ) {
      $item->attendance_created_label = $this->get_attendance_sheet_signature_date_label( $item );
      $item->attendance_duration_label = $this->get_session_duration_label( $item );
      $item->attendance_progression_label = 'Voir détails';
      $item->attendance_state_label = 'Voir détails';
      if ( empty( $item->trainer_signature_label ) ) {
        /* ACDC 3.25.200 — Voir la note du module séances : le mot était écrit en
           dur, il est désormais lu sur la feuille d'émargement. */
        $item->trainer_signature_label = $this->get_attendance_sheet_trainer_signature_label( $item );
      }
      if ( empty( $item->learner_signature_label ) ) {
        $item->learner_signature_label = 'Voir détails';
      }
    }

    return $items;
  }  private function get_positioning_result_document_info( $registration, $context = array() ) {
    $info = array(
      'url'   => '',
      'path'  => '',
      'size'  => '',
      'label' => 'Résultat test de positionnement',
    );
    if ( ! $registration ) {
      return $info;
    }
    $url  = isset( $registration->positioning_result_document_url ) ? trim( (string) $registration->positioning_result_document_url ) : '';
    $path = isset( $registration->positioning_result_document_path ) ? trim( (string) $registration->positioning_result_document_path ) : '';
    if ( '' === $path && '' !== $url ) {
      $path = $this->get_local_path_from_upload_url( $url );
    }
    if ( '' === $url ) {
      global $wpdb;
      $learner_name = isset( $context['learner_name'] ) ? (string) $context['learner_name'] : ( ! empty( $registration->learner_label ) ? (string) $registration->learner_label : '' );
      $formation_title = isset( $context['formation_title'] ) ? (string) $context['formation_title'] : ( ! empty( $registration->formation_title ) ? (string) $registration->formation_title : '' );
      $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->document_table} WHERE (document_type LIKE %s OR title LIKE %s) ORDER BY id DESC LIMIT 25", '%positionnement%', '%positionnement%' ) );
      foreach ( (array) $rows as $row ) {
        $haystack = trim( (string) $row->title . ' ' . (string) $row->document_type );
        $match = false;
        if ( '' !== $learner_name && false !== stripos( $haystack, $learner_name ) ) {
          $match = true;
        }
        if ( ! $match && '' !== $formation_title && false !== stripos( $haystack, $formation_title ) ) {
          $match = true;
        }
        if ( ! $match ) {
          continue;
        }
        $url  = ! empty( $row->file_url ) ? (string) $row->file_url : '';
        $path = ! empty( $row->file_path ) ? (string) $row->file_path : '';
        break;
      }
    }
    $info['url'] = $url;
    $info['path'] = $path;
    if ( '' !== $path && file_exists( $path ) ) {
      $size = filesize( $path );
      if ( $size ) {
        if ( $size >= 1048576 ) {
          $info['size'] = number_format_i18n( $size / 1048576, 1 ) . ' MB';
        } elseif ( $size >= 1024 ) {
          $info['size'] = number_format_i18n( $size / 1024, 0 ) . ' KB';
        } else {
          $info['size'] = $size . ' octets';
        }
      }
    }
    return $info;
  }  private function get_positioning_result_display_file_name( $registration, $context = array(), $document = array() ) {
    $context = is_array( $context ) ? $context : array();
    $document = is_array( $document ) ? $document : array();
    if ( ! empty( $document['url'] ) ) {
      $path = wp_parse_url( (string) $document['url'], PHP_URL_PATH );
      if ( $path ) {
        return basename( (string) $path );
      }
    }
    $learner = isset( $context['learner_name'] ) ? (string) $context['learner_name'] : ( ! empty( $registration->learner_label ) ? (string) $registration->learner_label : 'apprenant' );
    $formation = isset( $context['formation_title'] ) ? (string) $context['formation_title'] : ( ! empty( $registration->formation_title ) ? (string) $registration->formation_title : 'formation' );
    return sanitize_file_name( 'resultat-test-positionnement-' . $learner . '-' . $formation . '.pdf' );
  }

  private function get_positioning_result_context( $registration ) {
    $convocation_context = $this->get_training_convocation_context( $registration );
    $formation = isset( $convocation_context['formation'] ) ? $convocation_context['formation'] : null;
    /* ACDC 3.25.278 — L'ANCIEN MODULE EST RETIRÉ, SON ÉCHAFAUDAGE AUSSI.
       Ce contexte allait chercher la DÉFINITION du test — ses questions, sa
       grille de correction — pour composer un intitulé de résultat. Cette
       définition n'existe plus : le quiz est désormais le seul test de
       positionnement, et ce qu'il laisse est un DOCUMENT de résultat déposé sur
       la fiche d'inscription. Le contexte se réduit donc à cette preuve-là.

       Les clés sont conservées, vides : elles sont lues par le composeur de PDF
       et par l'écran du dossier, qui n'ont pas à savoir que le module a
       disparu. */
    $result_label = '—';
    $document     = $this->get_positioning_result_document_info( $registration, $convocation_context );
    if ( ! empty( $document['url'] ) || ! empty( $document['path'] ) ) {
      $result_label = 'Résultat disponible';
    }
    $convocation_context['test'] = null;
    $convocation_context['test_title'] = 'Test de positionnement';
    $convocation_context['test_source_label'] = 'Plateforme';
    $convocation_context['correction_type'] = 'Correction automatique';
    $convocation_context['result_label'] = $result_label;
    $convocation_context['correct_answers'] = null;
    $convocation_context['total_questions'] = 0;
    $convocation_context['questions_details'] = array();
    $convocation_context['document'] = $document;
    /* ACDC 3.25.224 — Le troisième écran de documents affichait la même durée
       vide et la même période approchée que les deux autres. Il lit désormais
       la même source. */
    $convocation_context = $this->acdc_apply_completion_period(
      $convocation_context,
      $this->acdc_completion_state_for_registration( $registration )
    );
    return $convocation_context;
  }  private function get_positioning_result_download_url( $registration, $mode = 'attachment' ) {
    if ( ! $registration || empty( $registration->id ) ) {
      return '';
    }
    $mode = 'inline' === $mode ? 'inline' : 'attachment';
    return wp_nonce_url( admin_url( 'admin-post.php?action=acdc_download_positioning_result_document&registration_id=' . (int) $registration->id . '&mode=' . rawurlencode( $mode ) ), 'acdc_download_positioning_result_document_' . (int) $registration->id );
  }  private function get_positioning_result_rows( $search = '' ) {
    $search = trim( (string) $search );
    $items = $this->get_training_registrations( false );
    $rows = array();
    foreach ( (array) $items as $entry ) {
      if ( empty( $entry->formation_id ) ) {
        continue;
      }
      $context = $this->get_positioning_result_context( $entry );
      /* Un test de positionnement se passe : le commanditaire n'en passe pas. */
      if ( ! empty( $context['is_commanditaire'] ) ) {
        continue;
      }
      $formation_enabled = ! empty( $context['formation'] ) && ! empty( $context['formation']->positioning_test_enabled );
      if ( ! $formation_enabled && empty( $context['test'] ) && empty( $context['document']['url'] ) && empty( $context['document']['path'] ) ) {
        continue;
      }
      $haystack = strtolower( implode( ' ', array_filter( array(
        (string) $entry->id,
        (string) $context['learner_name'],
        (string) $context['test_source_label'],
        (string) $context['test_title'],
        (string) $context['formation_title'],
      ) ) ) );
      if ( '' !== $search && false === strpos( $haystack, strtolower( $search ) ) ) {
        continue;
      }
      $state = '—';
      if ( ! empty( $context['end_date'] ) ) {
        $end_ts = strtotime( (string) $context['end_date'] . ' 23:59:59' );
        if ( $end_ts && $end_ts < current_time( 'timestamp' ) ) {
          $state = 'Formation terminée - ---';
        }
      }
      $rows[] = array(
        'registration' => $entry,
        'context' => $context,
        'state' => $state,
      );
    }
    return $rows;
  }  private function build_positioning_result_pdf_pages( $registration, $context = array() ) {
    $context = is_array( $context ) ? $context : array();
    if ( empty( $context ) ) {
      $context = $this->get_positioning_result_context( $registration );
    }
    $profile = $this->get_company_profile_options();
    $branding = $this->get_branding_options();
    $org_name = ! empty( $profile['enterprise'] ) ? $profile['enterprise'] : ( ! empty( $branding['company_name'] ) ? $branding['company_name'] : 'ACDC Formation' );
    $header_bg = ! empty( $profile['header_footer_bg'] ) ? $profile['header_footer_bg'] : '#F3E3BF';
    $title_color = '#0C2D52';
    $muted = '#1E4777';
    $logo = $this->prepare_pdf_jpeg_image( $this->acdc_resolve_pdf_logo_url(), 42, 42 );
    $footer_logo = ! empty( $profile['footer_logo_url'] ) ? $this->prepare_pdf_jpeg_image( $profile['footer_logo_url'], 120, 40 ) : null;

    $score_label = ( null !== $context['correct_answers'] && ! empty( $context['total_questions'] ) ) ? sprintf( '%d / %d', (int) $context['correct_answers'], (int) $context['total_questions'] ) : $context['result_label'];

    $pages = array();
    $page = array();
    $y = 800;
    $page[] = array( 'type' => 'rect', 'x' => 0, 'y' => 760, 'width' => 595, 'height' => 82, 'fill_color' => $header_bg );
    if ( $logo ) {
      $page[] = array(
        'type' => 'image', 'image_key' => $logo['key'], 'image_data' => $logo['data'], 'image_width' => $logo['width'], 'image_height' => $logo['height'], 'display_width' => $logo['display_width'], 'display_height' => $logo['display_height'], 'x' => 250, 'y' => 786,
      );
    }
    $page[] = array( 'text' => strtoupper( $org_name ), 'x' => 228, 'y' => 812, 'size' => 14, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    $page[] = array( 'text' => $context['learner_name'], 'x' => 230, 'y' => 782, 'size' => 18, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    $page[] = array( 'text' => 'Formation : ' . $context['formation_title'], 'x' => 105, 'y' => 760, 'size' => 8.5, 'font' => 'Helvetica', 'color' => $title_color );
    $page[] = array( 'text' => 'Résultats test de positionnement', 'x' => 220, 'y' => 744, 'size' => 8.5, 'font' => 'Helvetica', 'color' => $title_color );
    $page[] = array( 'text' => 'Effectué le ' . $this->format_pdf_date( $context['start_date'] ), 'x' => 235, 'y' => 730, 'size' => 8.5, 'font' => 'Helvetica', 'color' => $muted );
    $page[] = array( 'text' => 'Score : ' . $score_label, 'x' => 250, 'y' => 700, 'size' => 14, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    $y = 664;
    foreach ( (array) $context['questions_details'] as $question ) {
      $block_height = 84;
      if ( $y - $block_height < 92 ) {
        if ( $footer_logo ) {
          $page[] = array(
            'type' => 'image', 'image_key' => $footer_logo['key'], 'image_data' => $footer_logo['data'], 'image_width' => $footer_logo['width'], 'image_height' => $footer_logo['height'], 'display_width' => $footer_logo['display_width'], 'display_height' => $footer_logo['display_height'], 'x' => 235, 'y' => 34,
          );
        }
        $pages[] = $page;
        $page = array();
        $y = 800;
      }
      $page[] = array( 'type' => 'rect', 'x' => 40, 'y' => $y - 70, 'width' => 515, 'height' => 70, 'stroke_color' => '#B8E0CF', 'line_width' => 1.5 );
      $page[] = array( 'text' => 'Question ' . (int) $question['number'] . ' : ' . $question['label'], 'x' => 54, 'y' => $y - 18, 'size' => 9.5, 'font' => 'Helvetica-Bold', 'color' => '#1F2937' );
      $page[] = array( 'text' => 'Résultat : ' . $context['result_label'], 'x' => 420, 'y' => $y - 18, 'size' => 8.5, 'font' => 'Helvetica-Bold', 'color' => '#35B37E' );
      $page[] = array( 'type' => 'rect', 'x' => 58, 'y' => $y - 52, 'width' => 460, 'height' => 24, 'fill_color' => '#F6F7F9', 'stroke_color' => '#E5E7EB', 'line_width' => 1 );
      $expected = ! empty( $question['expected'] ) ? $question['expected'] : 'Réponse non renseignée';
      $page[] = array( 'text' => $expected, 'x' => 74, 'y' => $y - 40, 'size' => 8.3, 'font' => 'Helvetica', 'color' => '#1F2937' );
      $y -= 92;
    }
    if ( $footer_logo ) {
      $page[] = array(
        'type' => 'image', 'image_key' => $footer_logo['key'], 'image_data' => $footer_logo['data'], 'image_width' => $footer_logo['width'], 'image_height' => $footer_logo['height'], 'display_width' => $footer_logo['display_width'], 'display_height' => $footer_logo['display_height'], 'x' => 235, 'y' => 34,
      );
    } else {
      $page[] = array( 'text' => strtoupper( $org_name ), 'x' => 220, 'y' => 50, 'size' => 11.5, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    }
    $pages[] = $page;
    return $pages;
  }private function get_completion_certificate_document_info( $registration, $context = array() ) {
  $info = array(
    'url'   => '',
    'path'  => '',
    'size'  => '',
    'label' => 'Certificat de réalisation',
  );
  if ( ! $registration ) {
    return $info;
  }
  $url  = isset( $registration->completion_certificate_document_url ) ? trim( (string) $registration->completion_certificate_document_url ) : '';
  $path = isset( $registration->completion_certificate_document_path ) ? trim( (string) $registration->completion_certificate_document_path ) : '';
  if ( '' === $path && '' !== $url ) {
    $path = $this->get_local_path_from_upload_url( $url );
  }
  $info['url'] = $url;
  $info['path'] = $path;
  if ( '' !== $path && file_exists( $path ) ) {
    $size = filesize( $path );
    if ( $size ) {
      if ( $size >= 1048576 ) {
        $info['size'] = number_format_i18n( $size / 1048576, 1 ) . ' MB';
      } elseif ( $size >= 1024 ) {
        $info['size'] = number_format_i18n( $size / 1024, 0 ) . ' KB';
      } else {
        $info['size'] = $size . ' octets';
      }
    }
  }
  return $info;
}private function get_completion_certificate_display_file_name( $registration, $context = array(), $document = array() ) {
  $context = is_array( $context ) ? $context : array();
  $document = is_array( $document ) ? $document : array();
  if ( ! empty( $document['url'] ) ) {
    $path = wp_parse_url( (string) $document['url'], PHP_URL_PATH );
    if ( $path ) {
      return basename( (string) $path );
    }
  }
  $learner = isset( $context['learner_name'] ) ? (string) $context['learner_name'] : ( ! empty( $registration->learner_label ) ? (string) $registration->learner_label : 'apprenant' );
  $formation = isset( $context['formation_title'] ) ? (string) $context['formation_title'] : ( ! empty( $registration->formation_title ) ? (string) $registration->formation_title : 'formation' );
  return sanitize_file_name( 'certificat-realisation-' . $learner . '-' . $formation . '.pdf' );
}
  /**
   * ACDC 3.25.224 — Une seule durée, une seule période, sur les trois écrans.
   *
   * La recette a relevé deux contradictions sur des pièces qui font preuve :
   * « Durée (H) » vide sur les quatre lignes d'un dossier de quatre
   * demi-journées, et une heure de fin qui n'était pas la même que celle
   * affichée par le parcours. Les deux ont la même origine : ces écrans
   * lisaient la fiche FORMATION — une durée saisie à la main, jamais remplie,
   * et des dates approchées — au lieu du planning et de l'émargement.
   *
   * On applique la hiérarchie qui vaut pour toutes les pièces de fin :
   * ce qui est SIGNÉ prime, le PRÉVU comble le silence, et le mot le dit
   * explicitement pour qu'aucun lecteur ne prenne l'un pour l'autre. Les
   * dates redeviennent des dates : l'heure de fin d'une action de formation
   * n'a de sens que sur une feuille d'émargement, pas sur un certificat qui
   * couvre plusieurs journées.
   */
  private function acdc_apply_completion_period( $context, $acdc_state ) {
    $signed  = isset( $acdc_state['time'] ) && is_array( $acdc_state['time'] ) ? $acdc_state['time'] : array();
    $planned = isset( $acdc_state['planned'] ) && is_array( $acdc_state['planned'] ) ? $acdc_state['planned'] : array();

    if ( ! empty( $signed['half_days'] ) && ! empty( $signed['label'] ) ) {
      $context['duration']        = $signed['label'] . ' émargées';
      $context['duration_source'] = 'signed';
    } elseif ( ! empty( $planned['label'] ) ) {
      $context['duration']        = $planned['label'] . ' prévues';
      $context['duration_source'] = 'planned';
    } else {
      $context['duration_source'] = 'formation';
    }

    $first = ! empty( $signed['first_at'] ) ? (string) $signed['first_at'] : (string) ( $planned['first_at'] ?? '' );
    $last  = ! empty( $signed['last_at'] ) ? (string) $signed['last_at'] : (string) ( $planned['last_at'] ?? '' );

    if ( '' !== $first ) {
      $context['start_date'] = substr( $first, 0, 10 );
    }
    if ( '' !== $last ) {
      $context['end_date'] = substr( $last, 0, 10 );
    }

    $context['period_start_at'] = $first;
    $context['period_end_at']   = $last;

    return $context;
  }

  private function get_completion_certificate_context( $registration ) {
  $context = $this->get_training_convocation_context( $registration );
  $document = $this->get_completion_certificate_document_info( $registration, $context );
  $profile = $this->get_company_profile_options();
  $context['document'] = $document;
  $context['certificate_title'] = 'Certificat de réalisation';
  $context['source_label'] = 'Plateforme';
  $context['result_label'] = ( ! empty( $document['url'] ) || ! empty( $document['path'] ) ) ? 'Complété' : 'Disponible';
  /* ACDC 3.25.220 — L'ASSIDUITÉ SE LIT, ELLE NE S'AFFIRME PAS.
     Ce champ valait « Oui » en dur, sans jamais consulter le moindre
     émargement : le certificat de réalisation — la pièce même que réclame le
     financeur pour solder un dossier — attestait une présence que personne
     n'avait vérifiée. C'est la définition d'un faux, et c'est le document sur
     lequel il ne fallait surtout pas se le permettre.
     Il porte désormais les heures RÉELLEMENT signées, et l'assiduité n'est
     acquise que si la personne a émargé au moins une demi-journée. */
  $acdc_state = $this->acdc_completion_state_for_registration( $registration );
  $context['completion_state'] = $acdc_state;
  $context['hours_label']      = $acdc_state['time']['label'];
  $context['hours_minutes']    = (int) $acdc_state['time']['minutes'];
  $context['half_days']        = (int) $acdc_state['time']['half_days'];
  $context['assiduity']        = $acdc_state['time']['half_days'] > 0 ? 'Oui' : 'Non';
  $context['is_due']           = ! empty( $acdc_state['certificat'] );
  $context = $this->acdc_apply_completion_period( $context, $acdc_state );
  /* ACDC 3.25.290 — Ville et raison sociale de l'émetteur : plus de repli en
     dur, la fiche fait foi. */
  $context['issuer_city'] = $this->acdc_org_identity()['ville'];
  $context['issuer_name'] = $this->acdc_org_identity()['raison_sociale'];
  $signatory = trim( (string) ( $profile['first_name'] ?? '' ) . ' ' . (string) ( $profile['last_name'] ?? '' ) );
  if ( '' === trim( $signatory ) ) {
    $signatory = 'David';
  }
  $context['signatory_name'] = trim( $signatory );
  $context['signatory_role'] = ! empty( $profile['signatory_role'] ) ? (string) $profile['signatory_role'] : 'Président';
  return $context;
}private function get_completion_certificate_download_url( $registration, $mode = 'attachment' ) {
  if ( ! $registration || empty( $registration->id ) ) {
    return '';
  }
  $mode = 'inline' === $mode ? 'inline' : 'attachment';
  return wp_nonce_url( admin_url( 'admin-post.php?action=acdc_download_completion_certificate_document&registration_id=' . (int) $registration->id . '&mode=' . rawurlencode( $mode ) ), 'acdc_download_completion_certificate_document_' . (int) $registration->id );
}private function get_completion_certificate_rows( $search = '' ) {
  $search = trim( (string) $search );
  $items = $this->get_training_registrations( false );
  $rows = array();
  foreach ( (array) $items as $entry ) {
    if ( empty( $entry->formation_id ) ) {
      continue;
    }
    $context = $this->get_completion_certificate_context( $entry );
    /* ACDC 3.25.224 — Un certificat de réalisation est NOMINATIF : il atteste
       qu'une personne a suivi des heures. Le commanditaire, lui, n'a suivi
       aucune heure — il a commandé et payé. Sa ligne n'a donc rien à faire
       ici, et c'est elle qui donnait l'impression d'un doublon. */
    if ( ! empty( $context['is_commanditaire'] ) ) {
      continue;
    }
    $formation_enabled = ! empty( $context['formation'] ) && ! empty( $context['formation']->end_documents_enabled );
    if ( ! $formation_enabled && empty( $context['document']['url'] ) && empty( $context['document']['path'] ) ) {
      continue;
    }
    $haystack = strtolower( implode( ' ', array_filter( array(
      (string) $entry->id,
      (string) $context['learner_name'],
      (string) $context['formation_title'],
      (string) $context['email'],
      (string) $context['phone'],
    ) ) ) );
    if ( '' !== $search && false === strpos( $haystack, strtolower( $search ) ) ) {
      continue;
    }
    $state = '—';
    if ( ! empty( $context['end_date'] ) ) {
      $end_ts = strtotime( (string) $context['end_date'] . ' 23:59:59' );
      if ( $end_ts && $end_ts < current_time( 'timestamp' ) ) {
        $state = 'Formation terminée - ---';
      }
    }
    $rows[] = array(
      'registration' => $entry,
      'context' => $context,
      'state' => $state,
    );
  }
  return $rows;
}private function build_completion_certificate_pdf_pages( $registration, $context = array() ) {
  $context = is_array( $context ) ? $context : array();
  if ( empty( $context ) ) {
    $context = $this->get_completion_certificate_context( $registration );
  }
  $profile = $this->get_company_profile_options();
  $logo = $this->prepare_pdf_jpeg_image( $this->acdc_resolve_pdf_logo_url(), 160, 58 );
  /* Le cachet passe par la charte (acdc_pdf_charte_stamp), plus bas. */
  $navy = '#0C2D52';
  $gold = '#C5A253';
  $gold_soft = '#E8D8B1';
  $muted = '#6b7280';
  $paper = '#fffdf8';
  $pages = array();
  $page = array(
    array( 'type' => 'page_meta', 'width' => 842, 'height' => 595 ),
    array( 'type' => 'rect', 'x' => 0, 'y' => 0, 'width' => 842, 'height' => 595, 'fill_color' => $paper ),
    array( 'type' => 'rect', 'x' => 14, 'y' => 14, 'width' => 814, 'height' => 567, 'stroke_color' => $navy, 'line_width' => 14 ),
    array( 'type' => 'rect', 'x' => 28, 'y' => 28, 'width' => 786, 'height' => 539, 'stroke_color' => $gold, 'line_width' => 2 ),
    array( 'text' => 'Document officiel', 'x' => 340, 'y' => 505, 'size' => 10, 'font' => 'Helvetica', 'color' => $gold ),
    array( 'text' => 'CERTIFICAT DE RÉALISATION', 'x' => 250, 'y' => 480, 'size' => 18, 'font' => 'Helvetica-Bold', 'color' => $navy ),
    array( 'text' => 'Action de formation', 'x' => 350, 'y' => 462, 'size' => 10, 'font' => 'Helvetica', 'color' => $muted ),
    array( 'text' => 'Certificat', 'x' => 700, 'y' => 535, 'size' => 10, 'font' => 'Helvetica', 'color' => $muted ),
    array( 'text' => 'Référence : CF / ' . (int) $registration->id . ' / ' . date_i18n( 'Y' ), 'x' => 640, 'y' => 522, 'size' => 9, 'font' => 'Helvetica', 'color' => $muted ),
    array( 'text' => 'Je soussigné(e) ' . $context['signatory_name'] . ', représentant légal du dispensateur de formation ACDC-Formation atteste que', 'x' => 96, 'y' => 405, 'size' => 11, 'font' => 'Helvetica', 'color' => '#1e2a36' ),
    array( 'text' => strtoupper( $context['learner_name'] ), 'x' => 250, 'y' => 375, 'size' => 22, 'font' => 'Helvetica-Bold', 'color' => $navy ),
    array( 'type' => 'rect', 'x' => 195, 'y' => 365, 'width' => 450, 'height' => 0.8, 'fill_color' => $gold_soft ),
    array( 'text' => 'a suivi l’action de formation', 'x' => 330, 'y' => 342, 'size' => 11, 'font' => 'Helvetica', 'color' => '#1e2a36' ),
    array( 'text' => $context['formation_title'], 'x' => 140, 'y' => 318, 'size' => 14, 'font' => 'Helvetica-Bold', 'color' => $navy ),
    array( 'type' => 'rect', 'x' => 115, 'y' => 308, 'width' => 610, 'height' => 0.8, 'fill_color' => $gold_soft ),
  );
  if ( $logo ) {
    $page[] = array( 'type' => 'image', 'image_key' => $logo['key'], 'image_data' => $logo['data'], 'image_width' => $logo['width'], 'image_height' => $logo['height'], 'display_width' => $logo['display_width'], 'display_height' => $logo['display_height'], 'x' => 58, 'y' => 505 );
  } else {
    $page[] = array( 'text' => 'ACDC-FORMATION', 'x' => 60, 'y' => 530, 'size' => 14, 'font' => 'Helvetica-Bold', 'color' => $navy );
  }
  $meta_x = 88
  ;
  $meta_y = 235;
  $box_w = 155;
  $box_h = 58;
  $gap = 15;
  $meta = array(
    array( 'Dates', 'Du ' . $this->format_pdf_date( $context['start_date'] ) . ' au ' . $this->format_pdf_date( $context['end_date'] ) ),
    array( 'Format', $context['format'] ),
    array( 'Durée', $context['duration'] ),
    array( 'Assiduité', $context['assiduity'] ),
  );
  foreach ( $meta as $idx => $item ) {
    $x = $meta_x + ( $idx * ( $box_w + $gap ) );
    $page[] = array( 'type' => 'rect', 'x' => $x, 'y' => $meta_y, 'width' => $box_w, 'height' => $box_h, 'stroke_color' => '#d8dee7', 'line_width' => 1 );
    $page[] = array( 'type' => 'rect', 'x' => $x, 'y' => $meta_y + $box_h - 3, 'width' => $box_w, 'height' => 3, 'fill_color' => $gold );
    $page[] = array( 'text' => strtoupper( $item[0] ), 'x' => $x + 12, 'y' => $meta_y + 38, 'size' => 8.5, 'font' => 'Helvetica', 'color' => $muted );
    foreach ( $this->pdf_wrap_text( $item[1], 26 ) as $line_index => $line ) {
      $page[] = array( 'text' => $line, 'x' => $x + 12, 'y' => $meta_y + 20 - ( $line_index * 12 ), 'size' => 10.5, 'font' => 'Helvetica-Bold', 'color' => $navy );
    }
  }
  $legal = "Sans préjudice des délais imposés par les règles fiscales, comptables ou commerciales, je m'engage à conserver l'ensemble des pièces justificatives qui ont permis d'établir le présent certificat pendant une durée de 3 (trois) ans à compter de la fin de l'année du dernier paiement.";
  $page[] = array( 'type' => 'rect', 'x' => 95, 'y' => 160, 'width' => 652, 'height' => 45, 'stroke_color' => '#d8dee7', 'line_width' => 1 );
  foreach ( $this->pdf_wrap_text( $legal, 118 ) as $i => $line ) {
    $page[] = array( 'text' => $line, 'x' => 105, 'y' => 190 - ( $i * 10 ), 'size' => 8.5, 'font' => 'Helvetica', 'color' => '#5b6775' );
  }
  $page[] = array( 'text' => 'ACDC', 'x' => 75, 'y' => 78, 'size' => 18, 'font' => 'Helvetica-Bold', 'color' => $gold );
  $page[] = array( 'text' => 'Formation', 'x' => 75, 'y' => 60, 'size' => 16, 'font' => 'Helvetica-Bold', 'color' => $navy );
  $page[] = array( 'text' => 'Action de formation / Certificat de réalisation', 'x' => 130, 'y' => 70, 'size' => 9, 'font' => 'Helvetica', 'color' => $muted );
  $page[] = array( 'text' => 'Pour le dispensateur de la formation', 'x' => 325, 'y' => 90, 'size' => 9, 'font' => 'Helvetica', 'color' => $muted );
  $page[] = array( 'type' => 'rect', 'x' => 300, 'y' => 80, 'width' => 180, 'height' => 0.8, 'fill_color' => $navy );
  $page[] = array( 'text' => 'Fait à : ' . $context['issuer_city'], 'x' => 610, 'y' => 92, 'size' => 9.5, 'font' => 'Helvetica', 'color' => '#475569' );
  $page[] = array( 'text' => 'Le : ' . date_i18n( 'd/m/Y' ), 'x' => 610, 'y' => 78, 'size' => 9.5, 'font' => 'Helvetica', 'color' => '#475569' );
  $page[] = array( 'text' => $this->acdc_org_identity()['raison_sociale'], 'x' => 610, 'y' => 53, 'size' => 10.5, 'font' => 'Helvetica-Bold', 'color' => $navy );
  $page[] = array( 'text' => 'Organisme de formation', 'x' => 610, 'y' => 40, 'size' => 9.5, 'font' => 'Helvetica', 'color' => '#475569' );
  /* ACDC 3.25.264 — LE CACHET RECOUVRAIT LA DATE.
     Posé en (612, 55), il montait jusqu'à y=120 et passait sur « Fait à : … »
     et « Le : … », imprimés en 92 et 78 : sur le document livré, le tampon
     barrait la date d'émission. Il descend sous les mentions, et sa taille
     vient de la charte comme partout ailleurs. La forme normalisée du
     certificat n'est pas en cause — un cachet qui masque une mention n'est
     pas une mise en page, c'est un défaut. */
  $stamp_line = $this->acdc_pdf_charte_stamp( 612, 8 );
  if ( $stamp_line ) {
    $page[] = $stamp_line;
  }
  $pages[] = $page;
  return $pages;
}private function get_end_training_certificate_document_info( $registration, $context = array() ) {
  $info = array(
    'url'   => '',
    'path'  => '',
    'size'  => '',
    'label' => 'Attestation de fin de formation',
  );
  if ( ! $registration ) {
    return $info;
  }
  $url  = isset( $registration->end_training_certificate_document_url ) ? trim( (string) $registration->end_training_certificate_document_url ) : '';
  $path = isset( $registration->end_training_certificate_document_path ) ? trim( (string) $registration->end_training_certificate_document_path ) : '';
  if ( '' === $path && '' !== $url ) {
    $path = $this->get_local_path_from_upload_url( $url );
  }
  $info['url'] = $url;
  $info['path'] = $path;
  if ( '' !== $path && file_exists( $path ) ) {
    $size = filesize( $path );
    if ( $size ) {
      if ( $size >= 1048576 ) {
        $info['size'] = number_format_i18n( $size / 1048576, 1 ) . ' MB';
      } elseif ( $size >= 1024 ) {
        $info['size'] = number_format_i18n( $size / 1024, 0 ) . ' KB';
      } else {
        $info['size'] = $size . ' octets';
      }
    }
  }
  return $info;
}private function get_end_training_certificate_display_file_name( $registration, $context = array(), $document = array() ) {
  $context = is_array( $context ) ? $context : array();
  $document = is_array( $document ) ? $document : array();
  if ( ! empty( $document['url'] ) ) {
    $path = wp_parse_url( (string) $document['url'], PHP_URL_PATH );
    if ( $path ) {
      return basename( (string) $path );
    }
  }
  $learner = isset( $context['learner_name'] ) ? (string) $context['learner_name'] : ( ! empty( $registration->learner_label ) ? (string) $registration->learner_label : 'apprenant' );
  $formation = isset( $context['formation_title'] ) ? (string) $context['formation_title'] : ( ! empty( $registration->formation_title ) ? (string) $registration->formation_title : 'formation' );
  return sanitize_file_name( 'attestation-fin-formation-' . $learner . '-' . $formation . '.pdf' );
}private function get_end_training_certificate_context( $registration ) {
  $context = $this->get_training_convocation_context( $registration );
  $document = $this->get_end_training_certificate_document_info( $registration, $context );
  $profile = $this->get_company_profile_options();
  $context['document'] = $document;
  $context['source_label'] = 'Plateforme';
  /* ACDC 3.25.264 — Les OBJECTIFS de l'action : l'article L6353-1 les exige sur
     l'attestation, au même titre que sa nature, sa durée et les résultats de
     l'évaluation. Ils n'étaient nulle part dans ce contexte — le document ne
     pouvait donc pas les porter. */
  $context['objectives'] = ( ! empty( $context['formation'] ) && ! empty( $context['formation']->objectives ) )
    ? (string) $context['formation']->objectives
    : '';
  /* ACDC 3.25.220 — Le RÉSULTAT décrivait l'état du FICHIER, pas celui des
     acquis : « Complétée » signifiait « un PDF existe », et l'attestation de
     fin de formation ne lisait jamais l'évaluation qu'elle est censée
     attester. Elle porte maintenant le score réel, la mention de réussite, et
     ne se déclare due que si l'évaluation a été passée ET réussie. */
  $acdc_state = $this->acdc_completion_state_for_registration( $registration );
  $context['completion_state'] = $acdc_state;
  $context['hours_label']      = $acdc_state['time']['label'];
  $context['assessment_taken'] = ! empty( $acdc_state['assessment']['taken'] );
  $context['assessment_score'] = ( null !== $acdc_state['assessment']['score'] )
    ? number_format( (float) $acdc_state['assessment']['score'], 1, ',', '' ) . ' %'
    : '';
  $context['assessment_quiz']  = (string) $acdc_state['assessment']['quiz_title'];
  $context['is_due']           = ! empty( $acdc_state['attestation'] );
  $context['due_reason']       = (string) $acdc_state['reason'];
  $context = $this->acdc_apply_completion_period( $context, $acdc_state );
  if ( ! $context['assessment_taken'] ) {
    $context['result_label'] = 'Évaluation des acquis non passée';
  } elseif ( true === $acdc_state['assessment']['passed'] ) {
    $context['result_label'] = 'Acquis validés' . ( '' !== $context['assessment_score'] ? ' — ' . $context['assessment_score'] : '' );
  } else {
    $context['result_label'] = 'Acquis non validés' . ( '' !== $context['assessment_score'] ? ' — ' . $context['assessment_score'] : '' );
  }
  /* ACDC 3.25.290 — Ville et raison sociale de l'émetteur : plus de repli en
     dur, la fiche fait foi. */
  $context['issuer_city'] = $this->acdc_org_identity()['ville'];
  $context['issuer_name'] = $this->acdc_org_identity()['raison_sociale'];
  /* ACDC 3.25.290 — Le signataire retombait sur un nom écrit en dur : une
     attestation aurait continué de nommer l'ancien dirigeant. */
  $signatory = $this->acdc_org_identity()['signataire'];
  $context['signatory_name'] = trim( $signatory );
  $context['signatory_role'] = ! empty( $profile['signatory_role'] ) ? (string) $profile['signatory_role'] : 'Président';
  return $context;
}private function get_end_training_certificate_download_url( $registration, $mode = 'attachment' ) {
  if ( ! $registration || empty( $registration->id ) ) {
    return '';
  }
  $mode = 'inline' === $mode ? 'inline' : 'attachment';
  return wp_nonce_url( admin_url( 'admin-post.php?action=acdc_download_end_training_certificate_document&registration_id=' . (int) $registration->id . '&mode=' . rawurlencode( $mode ) ), 'acdc_download_end_training_certificate_document_' . (int) $registration->id );
}private function get_end_training_certificate_rows( $search = '' ) {
  $search = trim( (string) $search );
  $items = $this->get_training_registrations( false );
  $rows = array();
  foreach ( (array) $items as $entry ) {
    if ( empty( $entry->formation_id ) ) {
      continue;
    }
    $context = $this->get_end_training_certificate_context( $entry );
    /* Même règle que le certificat : une attestation de fin de formation
       nomme la personne dont les acquis ont été évalués. Le commanditaire
       n'en a pas. */
    if ( ! empty( $context['is_commanditaire'] ) ) {
      continue;
    }
    $haystack = strtolower( implode( ' ', array_filter( array(
      (string) $entry->id,
      (string) $context['learner_name'],
      (string) $context['formation_title'],
      (string) $context['email'],
      (string) $context['phone'],
      (string) $context['result_label'],
    ) ) ) );
    if ( '' !== $search && false === strpos( $haystack, strtolower( $search ) ) ) {
      continue;
    }
    $state = '—';
    if ( ! empty( $context['end_date'] ) ) {
      $end_ts = strtotime( (string) $context['end_date'] . ' 23:59:59' );
      if ( $end_ts && $end_ts < current_time( 'timestamp' ) ) {
        $state = 'Formation terminée - ---';
      }
    }
    $rows[] = array(
      'registration' => $entry,
      'context' => $context,
      'state' => $state,
    );
  }
  return $rows;
}
/**
 * ACDC 3.25.225 — L'ATTESTATION D'ABSENCE : LA TROISIÈME PIÈCE.
 *
 * Elle manquait, et son absence coûtait cher dans les deux sens. Sans elle, un
 * apprenant qui n'est jamais venu recevait soit un certificat mensonger, soit
 * rien du tout — et le commanditaire, qui a commandé et payé, n'avait aucune
 * pièce à joindre à son dossier de financement pour expliquer le trou.
 *
 * Elle constate, elle n'accuse pas : elle dit ce que les feuilles d'émargement
 * portent, à savoir aucune signature, et se garde de toute interprétation sur
 * la raison. C'est un document adressé au COMMANDITAIRE.
 */
private function build_absence_certificate_pdf_pages( $registration, $context = array() ) {
  $context = is_array( $context ) && ! empty( $context ) ? $context : $this->get_completion_certificate_context( $registration );
  $profile = $this->get_company_profile_options();

  $logo = $this->prepare_pdf_jpeg_image( $this->acdc_resolve_pdf_logo_url(), 180, 54 );
  $stamp_source = ! empty( $profile['stamp_only_url'] ) ? $profile['stamp_only_url'] : ( ! empty( $profile['stamp_url'] ) ? $profile['stamp_url'] : '' );
  $stamp = ! empty( $stamp_source ) ? $this->prepare_pdf_jpeg_image( $stamp_source, 170, 60 ) : null;

  $navy  = '#0C2D52';
  $gold  = '#C5A253';
  $ink   = '#1f2937';
  $muted = '#6b7280';

  $org_name  = $this->acdc_org_identity()['raison_sociale'];
  $org_city  = $this->acdc_org_identity()['ville'];
  $signatory = trim( (string) ( $profile['first_name'] ?? '' ) . ' ' . (string) ( $profile['last_name'] ?? '' ) );
  if ( '' === $signatory ) {
    $signatory = 'La direction';
  }
  $signatory_role = ! empty( $profile['signatory_role'] ) ? (string) $profile['signatory_role'] : 'Président';

  $sponsor = '';
  if ( ! empty( $registration->company_id ) ) {
    $company = $this->get_company( (int) $registration->company_id );
    if ( $company && ! empty( $company->name ) ) {
      $sponsor = (string) $company->name;
    }
  }
  if ( '' === $sponsor ) {
    $sponsor = ! empty( $registration->company_label ) ? (string) $registration->company_label : '—';
  }

  $learner_name = (string) ( $context['learner_name'] ?? '—' );
  $formation    = (string) ( $context['formation_title'] ?? '—' );
  $planned      = (string) ( $context['duration'] ?? '—' );
  $period       = $this->format_pdf_date( $context['start_date'] ?? '' ) . ' au ' . $this->format_pdf_date( $context['end_date'] ?? '' );

  $page = array(
    array( 'type' => 'page_meta', 'width' => 595, 'height' => 842 ),
    array( 'type' => 'rect', 'x' => 0, 'y' => 0, 'width' => 595, 'height' => 842, 'fill_color' => '#ffffff' ),
    array( 'type' => 'rect', 'x' => 40, 'y' => 773, 'width' => 515, 'height' => 1.4, 'fill_color' => $gold ),
  );
  if ( $logo ) {
    $page[] = array( 'type' => 'image', 'image_key' => $logo['key'], 'image_data' => $logo['data'], 'image_width' => $logo['width'], 'image_height' => $logo['height'], 'display_width' => $logo['display_width'], 'display_height' => $logo['display_height'], 'x' => 40, 'y' => 782 );
  } else {
    $page[] = array( 'text' => $org_name, 'x' => 40, 'y' => 804, 'size' => 16, 'font' => 'Helvetica-Bold', 'color' => $navy );
  }

  $page[] = array( 'text' => 'ATTESTATION D’ABSENCE', 'x' => 170, 'y' => 726, 'size' => 15, 'font' => 'Helvetica-Bold', 'color' => '#111827' );
  $page[] = array( 'text' => 'Action de formation professionnelle', 'x' => 196, 'y' => 710, 'size' => 9, 'font' => 'Helvetica', 'color' => $muted );
  $page[] = array( 'text' => 'Référence : ABS / ' . (int) $registration->id . ' / ' . date_i18n( 'Y' ), 'x' => 380, 'y' => 748, 'size' => 8.5, 'font' => 'Helvetica', 'color' => $muted );

  $y = 660;
  $line = function( $text, $size = 10, $font = 'Helvetica', $color = null ) use ( &$page, &$y, $ink ) {
    $page[] = array( 'text' => $text, 'x' => 42, 'y' => $y, 'size' => $size, 'font' => $font, 'color' => $color ?: $ink );
    $y -= 20;
  };

  $line( 'Je soussigné(e) ' . $signatory . ', ' . $signatory_role . ' de ' . $org_name . ', atteste que :', 10 );
  $y -= 6;
  $line( 'Apprenant concerné : ' . $learner_name, 11, 'Helvetica-Bold' );
  $line( 'Commanditaire : ' . $sponsor, 10 );
  $line( 'Action de formation : ' . $formation, 10 );
  $line( 'Période prévue : ' . $period, 10 );
  $line( 'Volume prévu : ' . $planned, 10 );
  $y -= 10;
  $line( 'n’a signé AUCUNE feuille d’émargement sur la période ci-dessus.', 10.5, 'Helvetica-Bold' );
  $y -= 4;
  $line( 'En conséquence, et conformément aux règles applicables aux actions de', 9.6 );
  $line( 'formation professionnelle, aucun certificat de réalisation ni attestation de', 9.6 );
  $line( 'fin de formation ne peut être délivré : ces pièces attestent d’heures suivies', 9.6 );
  $line( 'et d’acquis évalués, que rien ne permet ici de constater.', 9.6 );
  $y -= 10;
  $line( 'La présente attestation est délivrée au commanditaire pour servir et valoir', 9.6 );
  $line( 'ce que de droit, notamment auprès de son financeur.', 9.6 );

  $y -= 30;
  $page[] = array( 'text' => 'Fait à ' . $org_city . ', le ' . date_i18n( 'd/m/Y' ), 'x' => 42, 'y' => $y, 'size' => 9.6, 'font' => 'Helvetica', 'color' => $ink );
  $page[] = array( 'text' => $signatory . ' — ' . $signatory_role, 'x' => 340, 'y' => $y, 'size' => 9.6, 'font' => 'Helvetica-Bold', 'color' => $navy );
  if ( $stamp ) {
    $page[] = array( 'type' => 'image', 'image_key' => $stamp['key'], 'image_data' => $stamp['data'], 'image_width' => $stamp['width'], 'image_height' => $stamp['height'], 'display_width' => $stamp['display_width'], 'display_height' => $stamp['display_height'], 'x' => 340, 'y' => $y - 78 );
  }

  return array( $page );
}

  /**
   * ACDC 3.25.264 — LE CADRE DU DIPLÔME.
   *
   * Le certificat de réalisation portait déjà cette allure — paysage, cadre
   * navy, filet doré, « Document officiel » — et c'est elle qui a été retenue
   * comme cible pour l'attestation de fin de formation, jusqu'ici imprimée
   * comme un formulaire administratif à lignes pointillées : « Nom : …… »,
   * « Adresse : …… ». Un document qu'on affiche au mur ne se remplit pas au
   * stylo.
   *
   * Le cadre est ici, l'attestation le remplit. Le certificat de réalisation,
   * lui, garde son propre code : c'est la pièce NORMALISÉE que le financeur
   * attend, et la faire passer sur un gabarit partagé la déplacerait d'un
   * point ou deux pour un gain nul. On ne refactorise pas un document dont la
   * forme est le contrat.
   *
   * @param array $args title, subtitle, kicker, reference.
   * @return array Les éléments de page du cadre, prêts à être complétés.
   */
  private function acdc_diploma_frame_elements( $args = array() ) {
    $navy  = '#0C2D52';
    $gold  = '#C5A253';
    $muted = '#6b7280';
    $paper = '#fffdf8';

    $title     = (string) ( $args['title'] ?? '' );
    $kicker    = (string) ( $args['kicker'] ?? 'Document officiel' );
    $subtitle  = (string) ( $args['subtitle'] ?? '' );
    $ref_label = (string) ( $args['reference_label'] ?? '' );
    $reference = (string) ( $args['reference'] ?? '' );

    /* Le titre est centré à l'œil : la police Helvetica-Bold de ce moteur PDF
       n'expose pas ses métriques ici, on approche par la largeur moyenne. */
    $title_size = 18;
    $title_x    = max( 60, (int) round( ( 842 - ( strlen( $title ) * $title_size * 0.60 ) ) / 2 ) );
    $kicker_x   = max( 60, (int) round( ( 842 - ( strlen( $kicker ) * 10 * 0.62 ) ) / 2 ) );
    $sub_x      = max( 60, (int) round( ( 842 - ( strlen( $subtitle ) * 10 * 0.52 ) ) / 2 ) );

    $elements = array(
      array( 'type' => 'page_meta', 'width' => 842, 'height' => 595 ),
      array( 'type' => 'rect', 'x' => 0,  'y' => 0,  'width' => 842, 'height' => 595, 'fill_color' => $paper ),
      array( 'type' => 'rect', 'x' => 14, 'y' => 14, 'width' => 814, 'height' => 567, 'stroke_color' => $navy, 'line_width' => 14 ),
      array( 'type' => 'rect', 'x' => 28, 'y' => 28, 'width' => 786, 'height' => 539, 'stroke_color' => $gold, 'line_width' => 2 ),
      array( 'text' => $kicker, 'x' => $kicker_x, 'y' => 505, 'size' => 10, 'font' => 'Helvetica', 'color' => $gold ),
      array( 'text' => $title,  'x' => $title_x,  'y' => 478, 'size' => $title_size, 'font' => 'Helvetica-Bold', 'color' => $navy ),
    );
    if ( '' !== $subtitle ) {
      $elements[] = array( 'text' => $subtitle, 'x' => $sub_x, 'y' => 460, 'size' => 10, 'font' => 'Helvetica', 'color' => $muted );
    }
    if ( '' !== $ref_label ) {
      $elements[] = array( 'text' => $ref_label, 'x' => 700, 'y' => 535, 'size' => 10, 'font' => 'Helvetica', 'color' => $muted );
    }
    if ( '' !== $reference ) {
      $elements[] = array( 'text' => $reference, 'x' => 640, 'y' => 522, 'size' => 9, 'font' => 'Helvetica', 'color' => $muted );
    }
    return $elements;
  }

  private function build_end_training_certificate_pdf_pages( $registration, $context = array() ) {
    $context = is_array( $context ) ? $context : array();
    if ( empty( $context ) ) {
      $context = $this->get_end_training_certificate_context( $registration );
    }
    $profile = $this->get_company_profile_options();
    $navy    = '#0C2D52';
    $gold    = '#C5A253';
    $muted   = '#6b7280';
    $ink     = '#1e2a36';

    /* ACDC 3.25.264 — L'ATTESTATION DEVIENT UN DIPLÔME.
       Elle s'imprimait comme un imprimé à remplir à la main — « Nom : …… »,
       « Adresse : …… », « Téléphone : …… » — alors que c'est la pièce que
       l'apprenant garde, montre, et parfois affiche. Elle reprend donc le
       langage visuel du certificat de réalisation : paysage, cadre navy,
       filet doré.
       Ce qui change, c'est l'ALLURE. Les mentions imposées par l'article
       L6353-1 restent toutes : la nature et la durée de l'action, ses
       objectifs, et les résultats de l'évaluation des acquis. Un diplôme qui
       les perdrait ne serait plus une attestation de fin de formation.
       Ce qui disparaît, en revanche : l'adresse postale, le téléphone et le
       courriel de l'apprenant. Un document destiné à être montré n'a pas à
       porter les coordonnées personnelles de celui qui le montre — et la loi
       ne les demande pas. */
    $page = $this->acdc_diploma_frame_elements( array(
      'title'           => 'ATTESTATION DE FIN DE FORMATION',
      'kicker'          => 'Document officiel',
      'subtitle'        => 'Article L6353-1 du code du travail',
      'reference_label' => 'Attestation',
      'reference'       => 'Référence : AF / ' . (int) $registration->id . ' / ' . date_i18n( 'Y' ),
    ) );

    $logo = $this->prepare_pdf_jpeg_image( $this->acdc_resolve_pdf_logo_url(), 160, 58 );
    if ( $logo ) {
      $page[] = array( 'type' => 'image', 'image_key' => $logo['key'], 'image_data' => $logo['data'], 'image_width' => $logo['width'], 'image_height' => $logo['height'], 'display_width' => $logo['display_width'], 'display_height' => $logo['display_height'], 'x' => 58, 'y' => 505 );
    } else {
      $page[] = array( 'text' => 'ACDC-FORMATION', 'x' => 60, 'y' => 530, 'size' => 14, 'font' => 'Helvetica-Bold', 'color' => $navy );
    }

    /* ── Le titulaire, en grand : c'est le sujet du document ──────────────── */
    $learner_name = strtoupper( trim( (string) $context['learner_name'] ) );
    $name_x = max( 90, (int) round( ( 842 - ( strlen( $learner_name ) * 22 * 0.62 ) ) / 2 ) );
    $page[] = array( 'text' => 'Décernée à', 'x' => 385, 'y' => 412, 'size' => 11, 'font' => 'Helvetica', 'color' => $ink );
    $page[] = array( 'text' => $learner_name, 'x' => $name_x, 'y' => 380, 'size' => 22, 'font' => 'Helvetica-Bold', 'color' => $navy );
    $page[] = array( 'type' => 'rect', 'x' => 195, 'y' => 370, 'width' => 450, 'height' => 0.8, 'fill_color' => '#E8D8B1' );

    $learner_birth = '';
    if ( ! empty( $context['learner'] ) && ! empty( $context['learner']->birth_date ) ) {
      $learner_birth = $this->format_pdf_date( $context['learner']->birth_date );
    }
    if ( '' !== $learner_birth ) {
      $page[] = array( 'text' => 'né(e) le ' . $learner_birth, 'x' => 385, 'y' => 356, 'size' => 9.5, 'font' => 'Helvetica', 'color' => $muted );
    }

    /* ── L'action suivie ──────────────────────────────────────────────────── */
    $page[] = array( 'text' => 'pour avoir suivi la formation', 'x' => 335, 'y' => 334, 'size' => 11, 'font' => 'Helvetica', 'color' => $ink );
    $formation_title = (string) $context['formation_title'];
    $ftitle_x = max( 90, (int) round( ( 842 - ( strlen( $formation_title ) * 14 * 0.58 ) ) / 2 ) );
    $page[] = array( 'text' => $formation_title, 'x' => $ftitle_x, 'y' => 310, 'size' => 14, 'font' => 'Helvetica-Bold', 'color' => $navy );
    $page[] = array( 'type' => 'rect', 'x' => 115, 'y' => 300, 'width' => 610, 'height' => 0.8, 'fill_color' => '#E8D8B1' );

    /* ── Les quatre mentions légales, en boîtes ───────────────────────────── */
    $meta_x = 88;
    $meta_y = 225;
    $box_w  = 155;
    $box_h  = 58;
    $gap    = 15;
    $resultat = (string) $context['result_label'];
    $meta = array(
      array( 'Dates',    'Du ' . $this->format_pdf_date( $context['start_date'] ) . ' au ' . $this->format_pdf_date( $context['end_date'] ) ),
      array( 'Durée',    (string) $context['duration'] ),
      array( 'Modalité', (string) ( $context['format'] ?? '' ) ),
      array( 'Résultat de l’évaluation', $resultat ),
    );
    foreach ( $meta as $idx => $item ) {
      $x = $meta_x + ( $idx * ( $box_w + $gap ) );
      $page[] = array( 'type' => 'rect', 'x' => $x, 'y' => $meta_y, 'width' => $box_w, 'height' => $box_h, 'stroke_color' => '#d8dee7', 'line_width' => 1 );
      $page[] = array( 'type' => 'rect', 'x' => $x, 'y' => $meta_y + $box_h - 3, 'width' => $box_w, 'height' => 3, 'fill_color' => $gold );
      $page[] = array( 'text' => strtoupper( $item[0] ), 'x' => $x + 12, 'y' => $meta_y + 38, 'size' => 8, 'font' => 'Helvetica', 'color' => $muted );
      foreach ( $this->pdf_wrap_text( $item[1], 26 ) as $line_index => $line ) {
        $page[] = array( 'text' => $line, 'x' => $x + 12, 'y' => $meta_y + 20 - ( $line_index * 12 ), 'size' => 10.5, 'font' => 'Helvetica-Bold', 'color' => $navy );
      }
    }

    /* ── La nature de l'action et ses objectifs — exigés par L6353-1 ──────── */
    $objectifs = '';
    if ( ! empty( $context['objectives'] ) ) {
      $objectifs = trim( wp_strip_all_tags( (string) $context['objectives'] ) );
    }
    $mentions = "Action de formation au sens de l’article L6313-1 du code du travail, concourant au développement des compétences.";
    if ( '' !== $objectifs ) {
      $mentions .= ' Objectifs : ' . $objectifs;
    }
    $page[] = array( 'type' => 'rect', 'x' => 95, 'y' => 150, 'width' => 652, 'height' => 45, 'stroke_color' => '#d8dee7', 'line_width' => 1 );
    foreach ( array_slice( $this->pdf_wrap_text( $mentions, 118 ), 0, 4 ) as $i => $line ) {
      $page[] = array( 'text' => $line, 'x' => 105, 'y' => 180 - ( $i * 10 ), 'size' => 8.5, 'font' => 'Helvetica', 'color' => '#5b6775' );
    }

    /* ── Pied : cachet, signature, mentions de l'organisme ───────────────── */
    $page[] = array( 'text' => 'ACDC', 'x' => 75, 'y' => 78, 'size' => 18, 'font' => 'Helvetica-Bold', 'color' => $gold );
    $page[] = array( 'text' => 'Formation', 'x' => 75, 'y' => 60, 'size' => 16, 'font' => 'Helvetica-Bold', 'color' => $navy );
    $page[] = array( 'text' => 'Attestation de fin de formation', 'x' => 130, 'y' => 70, 'size' => 9, 'font' => 'Helvetica', 'color' => $muted );
    $page[] = array( 'text' => 'Pour l’organisme de formation', 'x' => 325, 'y' => 90, 'size' => 9, 'font' => 'Helvetica', 'color' => $muted );
    $page[] = array( 'type' => 'rect', 'x' => 300, 'y' => 80, 'width' => 180, 'height' => 0.8, 'fill_color' => $navy );
    $page[] = array( 'text' => (string) $context['signatory_name'] . ' — ' . (string) $context['signatory_role'], 'x' => 300, 'y' => 66, 'size' => 9, 'font' => 'Helvetica', 'color' => $muted );
    $page[] = array( 'text' => 'Fait à : ' . (string) $context['issuer_city'], 'x' => 610, 'y' => 92, 'size' => 9.5, 'font' => 'Helvetica', 'color' => '#475569' );
    $page[] = array( 'text' => 'Le : ' . date_i18n( 'd/m/Y' ), 'x' => 610, 'y' => 78, 'size' => 9.5, 'font' => 'Helvetica', 'color' => '#475569' );

    /* Le cachet est posé SOUS les mentions, pas dessus : c'est le défaut
       constaté sur le certificat, où il recouvrait « Fait à » et la date. */
    $stamp = $this->acdc_pdf_charte_stamp( 612, 8 );
    if ( $stamp ) {
      $page[] = $stamp;
    }

    return array( $page );
  }
  private function get_allowed_mimes_for_upload_key( $file_key ) {
    $image_mimes = array(
      'jpg|jpeg' => 'image/jpeg',
      'png'      => 'image/png',
      'webp'     => 'image/webp',
      'gif'      => 'image/gif',
    );

    $document_mimes = array(
      'pdf'      => 'application/pdf',
      'doc'      => 'application/msword',
      'docx'     => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'xls'      => 'application/vnd.ms-excel',
      'xlsx'     => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'csv'      => 'text/csv',
      'ods'      => 'application/vnd.oasis.opendocument.spreadsheet',
      'odt'      => 'application/vnd.oasis.opendocument.text',
      'ppt'      => 'application/vnd.ms-powerpoint',
      'pptx'     => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
      'txt'      => 'text/plain',
    );

    switch ( $file_key ) {
      case 'catalog_image':
      case 'trainer_photo':
      case 'portal_user_photo':
        return $image_mimes;
      case 'formations_file':
        return array(
          'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
      case 'prospects_file':
        return array(
          'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
      case 'program_file':
      case 'shared_docs':
      case 'internal_docs':
      case 'document_file':
      case 'watch_attachments':
        return $document_mimes;
      default:
        return array_merge( $image_mimes, $document_mimes );
    }
  }  private function get_max_upload_size_for_upload_key( $file_key ) {
    return 10 * 1024 * 1024;
  }  private function validate_uploaded_file_array( $file, $file_key ) {
    if ( empty( $file['name'] ) || empty( $file['tmp_name'] ) ) {
      return new \WP_Error( 'acdc_missing_upload', 'Fichier manquant.' );
    }

    if ( ! empty( $file['error'] ) ) {
      return new \WP_Error( 'acdc_upload_error', 'Le téléversement a échoué.' );
    }

    $max_size = $this->get_max_upload_size_for_upload_key( $file_key );
    if ( ! empty( $file['size'] ) && (int) $file['size'] > $max_size ) {
      return new \WP_Error( 'acdc_upload_too_large', 'Le fichier dépasse la taille maximale autorisée.' );
    }

    $allowed_mimes = $this->get_allowed_mimes_for_upload_key( $file_key );
    $checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $allowed_mimes );
    if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
      return new \WP_Error( 'acdc_upload_invalid_type', 'Type de fichier non autorisé.' );
    }

    return array(
      'name'     => sanitize_file_name( $file['name'] ),
      'type'     => $checked['type'],
      'tmp_name' => $file['tmp_name'],
      'error'    => 0,
      'size'     => isset( $file['size'] ) ? (int) $file['size'] : 0,
    );
  }

  private function read_xlsx_rows( $file_path ) {
    if ( ! file_exists( $file_path ) ) {
      return new \WP_Error( 'acdc_import_file_missing', 'Le fichier importé est introuvable.' );
    }
    if ( ! class_exists( 'ZipArchive' ) ) {
      return new \WP_Error( 'acdc_import_zip_missing', 'Le serveur ne dispose pas du support ZIP nécessaire à la lecture des fichiers Excel.' );
    }

    $zip = new \ZipArchive();
    if ( true !== $zip->open( $file_path ) ) {
      return new \WP_Error( 'acdc_import_zip_open', 'Impossible d’ouvrir le fichier Excel.' );
    }

    $workbook_rels_xml = $zip->getFromName( 'xl/_rels/workbook.xml.rels' );
    $workbook_xml = $zip->getFromName( 'xl/workbook.xml' );
    if ( false === $workbook_xml ) {
      $zip->close();
      return new \WP_Error( 'acdc_import_workbook_missing', 'Structure Excel invalide : workbook introuvable.' );
    }

    $shared_strings = array();
    $shared_xml = $zip->getFromName( 'xl/sharedStrings.xml' );
    if ( false !== $shared_xml ) {
      $shared_xml = $this->acdc_strip_spreadsheetml_prefix( $shared_xml );
      $shared = @simplexml_load_string( $shared_xml );
      if ( $shared ) {
        foreach ( $shared->si as $si ) {
          $text = '';
          if ( isset( $si->t ) ) {
            $text = (string) $si->t;
          } elseif ( isset( $si->r ) ) {
            foreach ( $si->r as $run ) {
              $text .= (string) $run->t;
            }
          }
          $shared_strings[] = html_entity_decode( $text, ENT_QUOTES | ENT_XML1, 'UTF-8' );
        }
      }
    }

    $sheet_path = 'xl/worksheets/sheet1.xml';
    if ( false !== $workbook_rels_xml ) {
      $rels = @simplexml_load_string( $workbook_rels_xml );
      $workbook = @simplexml_load_string( $workbook_xml );
      if ( $rels && $workbook && isset( $workbook->sheets->sheet[0] ) ) {
        $namespaces = $workbook->getNamespaces( true );
        $sheet_attrs = $workbook->sheets->sheet[0]->attributes( isset( $namespaces['r'] ) ? $namespaces['r'] : null );
        $first_rel_id = isset( $sheet_attrs['id'] ) ? (string) $sheet_attrs['id'] : '';
        if ( $first_rel_id ) {
          foreach ( $rels->Relationship as $rel ) {
            $attrs = $rel->attributes();
            if ( isset( $attrs['Id'] ) && (string) $attrs['Id'] === $first_rel_id && ! empty( $attrs['Target'] ) ) {
              $target = ltrim( (string) $attrs['Target'], '/' );
              $sheet_path = 0 === strpos( $target, 'xl/' ) ? $target : 'xl/' . $target;
              break;
            }
          }
        }
      }
    }

    $sheet_xml = $zip->getFromName( $sheet_path );
    $zip->close();
    if ( false === $sheet_xml ) {
      return new \WP_Error( 'acdc_import_sheet_missing', 'Impossible de lire la première feuille du fichier Excel.' );
    }

    $sheet_xml = $this->acdc_strip_spreadsheetml_prefix( $sheet_xml );
    $sheet = @simplexml_load_string( $sheet_xml );
    if ( ! $sheet || ! isset( $sheet->sheetData ) ) {
      return new \WP_Error( 'acdc_import_sheet_invalid', 'Le contenu de la feuille Excel est invalide.' );
    }

    $rows = array();
    foreach ( $sheet->sheetData->row as $row ) {
      $current = array();
      $max_index = -1;
      foreach ( $row->c as $cell ) {
        $attrs = $cell->attributes();
        $ref = isset( $attrs['r'] ) ? (string) $attrs['r'] : '';
        $index = $this->xlsx_column_index_from_ref( $ref );
        if ( $index < 0 ) {
          continue;
        }
        $value = '';
        $type = isset( $attrs['t'] ) ? (string) $attrs['t'] : '';
        if ( 'inlineStr' === $type && isset( $cell->is ) ) {
          foreach ( $cell->is->children() as $child ) {
            $value .= (string) $child;
          }
        } elseif ( 's' === $type ) {
          $shared_index = isset( $cell->v ) ? (int) $cell->v : -1;
          $value = isset( $shared_strings[ $shared_index ] ) ? $shared_strings[ $shared_index ] : '';
        } else {
          $value = isset( $cell->v ) ? (string) $cell->v : '';
        }
        $current[ $index ] = trim( html_entity_decode( (string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8' ) );
        if ( $index > $max_index ) {
          $max_index = $index;
        }
      }
      if ( $max_index >= 0 ) {
        $normalized = array();
        for ( $i = 0; $i <= $max_index; $i++ ) {
          $normalized[] = isset( $current[ $i ] ) ? $current[ $i ] : '';
        }
        $rows[] = $normalized;
      }
    }

    return $rows;
  }

  /**
   * Normalise un XML de feuille de calcul OOXML dont les balises de l'espace
   * de noms spreadsheetml principal sont prefixees (ex: x:worksheet, x:sheetData),
   * en retirant ce prefixe pour que SimpleXML lise les noeuds sans enregistrement
   * de namespace. Sans effet sur les fichiers deja sans prefixe (LibreOffice, exports ACDC).
   *
   * @param string $xml
   * @return string
   */
  private function acdc_strip_spreadsheetml_prefix( $xml ) {
    $xml = (string) $xml;
    if ( '' === $xml ) {
      return $xml;
    }
    // Detecte le prefixe associe au namespace spreadsheetml principal.
    if ( ! preg_match( '/xmlns:([A-Za-z0-9_]+)\s*=\s*"http:\/\/schemas\.openxmlformats\.org\/spreadsheetml\/2006\/main"/', $xml, $m ) ) {
      return $xml; // pas de prefixe sur ce namespace : rien a faire.
    }
    $prefix = $m[1];
    if ( '' === $prefix ) {
      return $xml;
    }
    // Retire le prefixe des balises ouvrantes/fermantes : <x:tag ...> et </x:tag>.
    $xml = preg_replace( '/<' . preg_quote( $prefix, '/' ) . ':/', '<', $xml );
    $xml = preg_replace( '/<\/' . preg_quote( $prefix, '/' ) . ':/', '</', $xml );
    // Transforme la declaration xmlns:prefix en namespace par defaut pour rester valide.
    $xml = preg_replace( '/xmlns:' . preg_quote( $prefix, '/' ) . '\s*=\s*"http:\/\/schemas\.openxmlformats\.org\/spreadsheetml\/2006\/main"/', 'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"', $xml, 1 );
    // Supprime d'eventuelles occurrences residuelles de la meme declaration.
    $xml = str_replace( 'xmlns:' . $prefix . '="http://schemas.openxmlformats.org/spreadsheetml/2006/main"', '', $xml );
    return $xml;
  }

  private function xlsx_column_index_from_ref( $ref ) {
    if ( ! preg_match( '/^([A-Z]+)/i', (string) $ref, $matches ) ) {
      return -1;
    }
    $letters = strtoupper( $matches[1] );
    $index = 0;
    $length = strlen( $letters );
    for ( $i = 0; $i < $length; $i++ ) {
      $index = ( $index * 26 ) + ( ord( $letters[ $i ] ) - 64 );
    }
    return $index - 1;
  }

  private function normalize_import_header_label( $label ) {
    $label = remove_accents( wp_strip_all_tags( (string) $label ) );
    $label = strtolower( (string) $label );
    $label = preg_replace( '/[^a-z0-9\s]/', ' ', $label );
    $label = trim( preg_replace( '/\s+/', ' ', $label ) );
    return $label;
  }

  private function map_formation_import_headers( $header_row ) {
    $aliases = array(
      // Identifiant formation (priorité sur le titre pour la mise à jour)
      'formation_id'    => array( 'id formation', 'formation id', 'identifiant formation', 'id_formation' ),
      // Champs principaux
      'title'           => array( 'intitule', 'intitule de la formation', 'titre', 'title', 'nom formation' ),
      'status'          => array( 'status', 'statut' ),
      'qualiopi_compliant' => array( 'parcours qualiopi conforme', 'qualiopi conforme', 'qualiopi' ),
      'description_text' => array( 'description', 'description de la formation' ),
      // "Objectifs de la formation" (nouveau format enrichi)
      'objectives'      => array( 'objectifs', 'objectif', 'objectifs pedagogiques', 'objectifs de la formation' ),
      'catalog_audience' => array( 'public cible', 'public', 'publics', 'beneficiaires' ),
      'modality'        => array( 'format', 'modalite', 'modalites' ),
      'address'         => array( 'adresse', 'lieu' ),
      'postal_code'     => array( 'code postal', 'cp' ),
      'city'            => array( 'ville' ),
      'price_ht'        => array( 'tarif de l’action de formation (€ ht)', "tarif de l'action de formation (€ ht)", 'tarif ht', 'tarif', 'prix ht', 'prix' ),
      // "Durée (HH:MM)" normalise en "duree hh mm"
      'duration'        => array( 'duree', 'durée', 'duree hh mm', 'duree hhimm' ),
      'prerequisites'   => array( 'prerequis', 'pre-requis', 'pré requis', 'pré-requis', 'prérequis' ),
      'program'         => array( 'programme', 'contenu' ),
      // Thématique ACDC → champ specialty (mapping demandé)
      'specialty'       => array( 'thematique', 'thematique acdc', 'specialite', 'specialty' ),
      // Champs complémentaires texte — ajoutés en 3.21.23
      'moyens_pedago'     => array( 'moyens pedagogiques et techniques', 'moyens pedagogiques', 'moyens pedago' ),
      'ressources'        => array( 'ressources remises', 'ressources remises aux apprenants', 'ressources' ),
      'sanction'          => array( 'validation de la formation', 'sanction', 'validation' ),
      'evaluation_entree' => array( 'evaluation positionnement entree', 'evaluation positionnement', 'evaluation entree', 'test positionnement description' ),
      'evaluation_sortie' => array( 'evaluation des acquis sortie', 'evaluation acquis sortie', 'evaluation sortie' ),
      // ACDC 3.21.36 — Nouveaux champs étendus
      'accroche'           => array( 'accroche', 'accroche commerciale', 'pourquoi cette formation', 'pourquoi' ),
      'trainer_ref'        => array( 'formateur', 'formateurs', 'formateur referent', 'formateurs referents', 'trainer ref' ),
      'accessibilite'      => array( 'accessibilite', 'accessibilite handicap', 'handicap' ),
      'referent_handicap'  => array( 'referent handicap', 'contact handicap', 'referent' ),
      'suivi'              => array( 'suivi', 'suivi execution', 'suivi exécution' ),
      'lieu_acces'         => array( 'lieu acces', 'lieu et delais acces', 'delais acces' ),
      'demarches'          => array( 'demarches', 'demarches administratives', 'démarches' ),
      'annulation'         => array( 'annulation', 'annulation retractation', 'retractation' ),
      'platform_url'       => array( 'plateforme', 'url plateforme', 'lien connexion', 'url connexion', 'lien teams', 'lien zoom', 'lms' ),
      'effectif_min'       => array( 'effectif min', 'participants min', 'nb min' ),
      'effectif_max'       => array( 'effectif max', 'participants max', 'nb max' ),
      'cpf_eligible'       => array( 'cpf', 'eligible cpf', 'cpf eligible' ),
      'cpf_code'           => array( 'code cpf', 'cpf code' ),
      'financement'        => array( 'financement', 'financements', 'financements acceptes' ),
      'taux_reussite'      => array( 'taux reussite', 'taux de reussite' ),
      'taux_satisfaction'  => array( 'taux satisfaction', 'taux de satisfaction' ),
      'taux_recommandation'=> array( 'taux recommandation', 'taux de recommandation', 'taux nps' ),
      'taux_completion'    => array( 'taux completion', 'taux de completion', 'taux completude' ),
      'catalog_image_url'  => array( 'image url', 'image', 'vignette', 'url image', 'thumbnail' ),
      'program_file_url'   => array( 'url programme', 'programme pdf', 'lien programme', 'url program' ),
      'manager_formation_id' => array( 'manager formation id', 'manager id', 'id manager' ),
      // Toggles Qualiopi
      // "Convocation de début de formation" (nouveau format) + "convocation" (ancien)
      /* ACDC 3.25.278 — « Enquête financeur » et « Évaluation diagnostique »
         manquaient à cette table : un fichier exporté puis réimporté perdait
         ces deux réglages sans le dire. Les intitulés historiques restent
         reconnus, un ancien fichier s'importe donc toujours. */
      'convocation_enabled'           => array( 'convocation', 'convocation de debut de formation' ),
      'positioning_test_enabled'      => array( 'test de positionnement' ),
      'diagnostic_evaluation_enabled' => array( 'evaluation diagnostique', 'evaluation diagnostic' ),
      'intermediate_survey_enabled'   => array( 'evaluation intermediaire', 'enquete intermediaire', 'enquete de satisfaction intermediaire' ),
      'hot_survey_enabled'            => array( 'evaluation a chaud', 'enquete a chaud', 'enquete de satisfaction a chaud' ),
      'evaluation_enabled'            => array( 'evaluation des acquis' ),
      'end_documents_enabled'         => array( 'documents de fin de formation', 'documents fin de formation' ),
      'cold_survey_enabled'           => array( 'evaluation a froid', 'enquete a froid', 'enquete de satisfaction a froid' ),
      'trainer_survey_enabled'        => array( 'enquete formateur' ),
      'company_survey_enabled'        => array( 'enquete entreprise' ),
      'funder_survey_enabled'         => array( 'enquete financeur' ),
    );

    $mapping = array();
    foreach ( (array) $header_row as $index => $label ) {
      $normalized = $this->normalize_import_header_label( $label );
      if ( '' === $normalized ) {
        continue;
      }
      foreach ( $aliases as $field => $labels ) {
        foreach ( $labels as $alias ) {
          if ( $normalized === $this->normalize_import_header_label( $alias ) ) {
            $mapping[ $field ] = (int) $index;
            continue 3;
          }
        }
      }
    }

    // Détection des colonnes programme pédagogique structuré.
    // Motif attendu après normalisation : "jour N matin [champ]" ou "jour N apres midi [champ]".
    // Les deux formes (avec ou sans tirets) normalisent de façon identique.
    foreach ( (array) $header_row as $index => $label ) {
      $normalized = $this->normalize_import_header_label( $label );
      if ( preg_match( '/^jour ([1-5]) (matin|apres midi) (.+)$/', $normalized, $m ) ) {
        $jour   = (int) $m[1];
        $moment = ( 'apres midi' === $m[2] ) ? 'apm' : 'matin';
        $champ  = $this->acdc_map_programme_champ( trim( $m[3] ) );
        if ( $champ && $jour >= 1 && $jour <= 5 ) {
          $key = 'prog_j' . $jour . '_' . $moment . '_' . $champ;
          if ( ! isset( $mapping[ $key ] ) ) {
            $mapping[ $key ] = (int) $index;
          }
        }
      }
    }

    return $mapping;
  }

  /**
   * Résout le suffixe normalisé d'une colonne programme vers une clé de champ interne.
   *
   * @param string $suffix Partie normalisée après "jour N matin|apm ".
   * @return string|null  'titre'|'contenus'|'competences'|'opo', ou null si non reconnu.
   */
  private function acdc_map_programme_champ( $suffix ) {
    // Les alias sont ordonnés du plus spécifique au plus générique.
    $champ_map = array(
      'titre'       => array( 'titre de la session', 'titre de session', 'titre' ),
      'contenus'    => array( 'contenus pedagogiques', 'contenu pedagogique', 'contenus' ),
      'competences' => array( 'competences developpees', 'competences ciblees', 'competences' ),
      'opo'         => array(
        'objectifs pedagogiques opo',   // couvre "…opo — une capacité par ligne" par startswith
        'objectifs pedagogiques opos',
        'objectifs pedagogiques',
        'objectifs pédagogiques',
        'opo',
      ),
    );
    foreach ( $champ_map as $key => $aliases ) {
      foreach ( $aliases as $alias ) {
        // Correspondance exacte ou le suffixe commence par cet alias (ex. "opo une capacite...")
        if ( $suffix === $alias || 0 === strpos( $suffix, $alias ) ) {
          return $key;
        }
      }
    }
    return null;
  }

  /**
   * Génère le fichier XLSX complet pour l’export de toutes les formations.
   * Utilise les shared strings (standard XLSX) pour une compatibilité maximale avec Excel.
   */
  private function build_formations_xlsx( $formations ) {
    if ( ! class_exists( 'ZipArchive' ) ) { return ''; }

    $headers = array(
      'Qualiopi conforme', 'Intitulé', 'Description de la formation',
      'Objectifs de la formation', 'Prérequis', 'Public cible',
      'Format', 'Adresse', 'CP', 'Ville',
      "Tarif de l'action de formation (€ HT)", 'Durée (HH:MM)',
    );
    /* ACDC 3.25.278 — Les colonnes d'interrupteurs viennent de la liste
       commune. Écrites à la main, elles avaient divergé : « Évaluation
       diagnostique » n'existait pas et « Enquête financeur » manquait — un
       aller-retour par le tableur perdait donc silencieusement ce réglage. */
    foreach ( $this->acdc_qualiopi_toggles() as $_tg ) {
      $headers[] = $_tg['label'];
    }
    for ( $j = 1; $j <= 5; $j++ ) {
      foreach ( array( 'Matin', 'Après-midi' ) as $_xlm ) {
        $headers[] = 'Jour ' . $j . ' - ' . $_xlm . ' - Titre de la session';
        $headers[] = 'Jour ' . $j . ' - ' . $_xlm . ' - Contenus pédagogiques';
        $headers[] = 'Jour ' . $j . ' - ' . $_xlm . ' - Compétences développées';
        $headers[] = 'Jour ' . $j . ' - ' . $_xlm . ' - Objectifs pédagogiques (OPO) — une capacité par ligne';
      }
    }
    $headers[] = 'Moyens pédagogiques et techniques';
    $headers[] = 'Ressources remises';
    $headers[] = 'Validation de la formation';
    $headers[] = 'Évaluation positionnement (entrée)';
    $headers[] = 'Évaluation des acquis (sortie)';
    $headers[] = 'ID formation';
    $headers[] = 'Slug formation';
    $headers[] = 'URL formation';

    // Supprime les caractères de contrôle invalides XML (préserve \n \r \t)
    $clean = function( $s ) {
      return preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $s );
    };
    // Échappe pour XML et encode \n en &#10; (obligatoire dans les shared strings XLSX)
    $xe = function( $s ) use ( $clean ) {
      $s = $clean( $s );
      $s = htmlspecialchars( $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
      $s = str_replace( "\n", '&#10;', $s );
      $s = str_replace( "\r", '',      $s );
      return $s;
    };
    $to_text = function( $html ) use ( $clean ) {
      $t = preg_replace( '#<(br|/p|/li|/h[1-6])[^>]*>#i', "\n", (string) $html );
      $t = wp_strip_all_tags( $t );
      $t = trim( preg_replace( '/\n{3,}/', "\n\n", $t ) );
      return $clean( $t );
    };
    $yn = function( $val ) {
      return ( isset( $val ) && (int) $val === 1 ) ? 'oui' : 'non';
    };
    $col_letter = function( $n ) {
      $l = '';
      while ( $n > 0 ) { $n--; $l = chr( 65 + ( $n % 26 ) ) . $l; $n = intdiv( $n, 26 ); }
      return $l;
    };
    $cat_id   = (int) get_option( 'acdc_of_catalog_page_id', 0 );
    $cat_base = $cat_id ? get_permalink( $cat_id ) : home_url( '/catalogue/' );

    $all_rows = array( $headers );
    foreach ( (array) $formations as $f ) {
      $prog = array();
      if ( ! empty( $f->programme_detail ) ) {
        $dp = json_decode( (string) $f->programme_detail, true );
        if ( is_array( $dp ) ) {
          foreach ( $dp as $_b ) {
            if ( isset( $_b['jour'], $_b['moment'] ) ) {
              $prog[ (int) $_b['jour'] ][ $_b['moment'] ] = $_b;
            }
          }
        }
      }
      $gp = function( $j, $m, $k ) use ( $prog ) {
        return isset( $prog[ $j ][ $m ][ $k ] ) ? (string) $prog[ $j ][ $m ][ $k ] : '';
      };
      $pub = ! empty( $f->code ) ? (string) $f->code : (string) (int) $f->id;
      $row = array(
        $yn( $f->qualiopi_compliant ),
        (string) $f->title,
        $to_text( $f->description_text ),
        $to_text( $f->objectives ),
        $to_text( $f->prerequisites ),
        $to_text( $f->catalog_audience ),
        (string) $f->modality,
        (string) $f->address,
        (string) $f->postal_code,
        (string) $f->city,
        (string) $f->price_ht,
        (string) $f->duration,
      );
      foreach ( $this->acdc_qualiopi_toggles() as $_tk => $_tg ) {
        $row[] = $yn( isset( $f->$_tk ) ? $f->$_tk : $_tg['default'] );
      }
      for ( $_xj = 1; $_xj <= 5; $_xj++ ) {
        foreach ( array( 'matin', 'apm' ) as $_xm ) {
          $row[] = $gp( $_xj, $_xm, 'titre' );
          $row[] = $gp( $_xj, $_xm, 'contenus' );
          $row[] = $gp( $_xj, $_xm, 'competences' );
          $row[] = $gp( $_xj, $_xm, 'opo' );
        }
      }
      $row[] = isset( $f->moyens_pedago )     ? (string) $f->moyens_pedago     : '';
      $row[] = isset( $f->ressources )        ? (string) $f->ressources        : '';
      $row[] = isset( $f->sanction )          ? (string) $f->sanction          : '';
      $row[] = isset( $f->evaluation_entree ) ? (string) $f->evaluation_entree : '';
      $row[] = isset( $f->evaluation_sortie ) ? (string) $f->evaluation_sortie : '';
      $row[] = (string) (int) $f->id;
      $row[] = (string) $f->catalog_slug;
      $row[] = add_query_arg( array( 'formation_id' => $pub ), $cat_base );
      $all_rows[] = $row;
    }

    // Shared strings : toutes les valeurs texte centralisées, référencées par index.
    $shared     = array();
    $shared_map = array();
    $add_str    = function( $val ) use ( &$shared, &$shared_map ) {
      $k = (string) $val;
      if ( ! isset( $shared_map[ $k ] ) ) {
        $shared_map[ $k ] = count( $shared );
        $shared[]         = $k;
      }
      return (int) $shared_map[ $k ];
    };

    // Feuille 1 : construction simultanée du XML et de l’index shared strings
    $s1_data = '';
    foreach ( $all_rows as $_ri => $_rd ) {
      $_rn  = $_ri + 1;
      $_hdr = ( 0 === $_ri );
      $_rx  = '';
      foreach ( $_rd as $_ci => $_cv ) {
        $_ref = $col_letter( $_ci + 1 ) . $_rn;
        $_v   = (string) $_cv;
        $_nl  = ( false !== strpos( $_v, "\n" ) );
        $_si  = $add_str( $_v );
        $_s   = $_hdr ? 2 : ( $_nl ? 1 : 0 );
        $_rx .= '<c r="' . $_ref . '" t="s" s="' . $_s . '"><v>' . $_si . '</v></c>';
      }
      $s1_data .= '<row r="' . $_rn . '">' . $_rx . '</row>';
    }
    $xml_sheet1 =
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
      '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
      '<sheetViews><sheetView workbookViewId="0">' .
      '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>' .
      '</sheetView></sheetViews>' .
      '<sheetFormatPr defaultRowHeight="15"/>' .
      '<sheetData>' . $s1_data . '</sheetData>' .
      '</worksheet>';

    // Shared strings XML
    $ss_count = count( $shared );
    $ss_data  = '';
    foreach ( $shared as $_sv ) {
      $_sp   = ( '' !== $_sv && ( ' ' === $_sv[0] || ' ' === $_sv[ strlen( $_sv ) - 1 ] ) )
               ? ' xml:space="preserve"' : '';
      $ss_data .= '<si><t' . $_sp . '>' . $xe( $_sv ) . '</t></si>';
    }
    $shared_str_xml =
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
      '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"' .
      ' count="' . $ss_count . '" uniqueCount="' . $ss_count . '">' .
      $ss_data . '</sst>';

    // Feuille 2 : Listes
    $listes  = array(
      array( 'oui', 'Présentiel' ), array( 'non', 'Distanciel' ),
      array( '',    'Hybride'      ), array( '',    'E-learning'  ),
    );
    $s2_data = '';
    foreach ( $listes as $_li => $_lr ) {
      $_rn2    = $_li + 1;
      $s2_data .= '<row r="' . $_rn2 . '">';
      foreach ( $_lr as $_lci => $_lcv ) {
        if ( '' === $_lcv ) { continue; }
        $s2_data .= '<c r="' . chr( 65 + $_lci ) . $_rn2 . '" t="s" s="0"><v>' . $add_str( $_lcv ) . '</v></c>';
      }
      $s2_data .= '</row>';
    }
    $xml_sheet2 =
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
      '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
      '<sheetData>' . $s2_data . '</sheetData></worksheet>';

    // Styles : 0=normal, 1=wrapText+top, 2=en-tête gras+fond bleu
    $styles_xml =
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
      '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
      '<fonts count="2">' .
        '<font><sz val="10"/><name val="Calibri"/><charset val="1"/></font>' .
        '<font><b/><sz val="10"/><name val="Calibri"/><charset val="1"/></font>' .
      '</fonts>' .
      '<fills count="3">' .
        '<fill><patternFill patternType="none"/></fill>' .
        '<fill><patternFill patternType="gray125"/></fill>' .
        '<fill><patternFill patternType="solid"><fgColor rgb="FFD9E1F2"/></fgColor></fill>' .
      '</fills>' .
      '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
      '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
      '<cellXfs count="3">' .
        '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' .
        '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"><alignment wrapText="1" vertical="top"/></xf>' .
        '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0"><alignment wrapText="1"/></xf>' .
      '</cellXfs>' .
      '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>' .
      '</styleSheet>';

    $ns_r   = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    $ns_p   = 'http://schemas.openxmlformats.org/package/2006/relationships';
    $ct_xls = 'application/vnd.openxmlformats-officedocument.spreadsheetml';

    $workbook_xml =
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
      '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="' . $ns_r . '">' .
      '<sheets>' .
        '<sheet name="modele_import_formations" sheetId="1" r:id="rId1"/>' .
        '<sheet name="Listes" sheetId="2" r:id="rId2"/>' .
      '</sheets></workbook>';

    $workbook_rels =
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
      '<Relationships xmlns="' . $ns_p . '">' .
        '<Relationship Id="rId1" Type="' . $ns_r . '/worksheet"     Target="worksheets/sheet1.xml"/>' .
        '<Relationship Id="rId2" Type="' . $ns_r . '/worksheet"     Target="worksheets/sheet2.xml"/>' .
        '<Relationship Id="rId3" Type="' . $ns_r . '/styles"        Target="styles.xml"/>' .
        '<Relationship Id="rId4" Type="' . $ns_r . '/sharedStrings" Target="sharedStrings.xml"/>' .
      '</Relationships>';

    $root_rels =
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
      '<Relationships xmlns="' . $ns_p . '">' .
        '<Relationship Id="rId1" Type="' . $ns_r . '/officeDocument" Target="xl/workbook.xml"/>' .
      '</Relationships>';

    $content_types =
      '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
      '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
        '<Default Extension="xml"  ContentType="application/xml"/>' .
        '<Override PartName="/xl/workbook.xml"           ContentType="' . $ct_xls . '.sheet.main+xml"/>' .
        '<Override PartName="/xl/worksheets/sheet1.xml"  ContentType="' . $ct_xls . '.worksheet+xml"/>' .
        '<Override PartName="/xl/worksheets/sheet2.xml"  ContentType="' . $ct_xls . '.worksheet+xml"/>' .
        '<Override PartName="/xl/styles.xml"             ContentType="' . $ct_xls . '.styles+xml"/>' .
        '<Override PartName="/xl/sharedStrings.xml"      ContentType="' . $ct_xls . '.sharedStrings+xml"/>' .
      '</Types>';

    $tmp = tempnam( sys_get_temp_dir(), 'acdc_forms_' ) . '.xlsx';
    $zip = new ZipArchive();
    if ( true !== $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) { return ''; }
    $zip->addFromString( '[Content_Types].xml',        $content_types );
    $zip->addFromString( '_rels/.rels',                $root_rels );
    $zip->addFromString( 'xl/workbook.xml',            $workbook_xml );
    $zip->addFromString( 'xl/_rels/workbook.xml.rels', $workbook_rels );
    $zip->addFromString( 'xl/worksheets/sheet1.xml',   $xml_sheet1 );
    $zip->addFromString( 'xl/worksheets/sheet2.xml',   $xml_sheet2 );
    $zip->addFromString( 'xl/styles.xml',              $styles_xml );
    $zip->addFromString( 'xl/sharedStrings.xml',       $shared_str_xml );
    $zip->close();
    $bytes = file_get_contents( $tmp );
    @unlink( $tmp );
    return false !== $bytes ? $bytes : '';
  }

  private function get_formation_by_title( $title ) {
    global $wpdb;
    $title = sanitize_text_field( (string) $title );
    if ( '' === $title ) {
      return null;
    }
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->formation_table} WHERE title = %s LIMIT 1", $title ) );
  }

  private function build_formation_import_data_from_row( $row, $mapping, $existing = null, $options = array() ) {
    // Option : ne pas écraser les champs vides (cochable à l'import, décochée par défaut).
    $overwrite_empty = ! empty( $options['overwrite_empty'] );

    // Retourne la valeur brute de la cellule pour un champ donné.
    // - null  : colonne absente du mapping, OU cellule vide + preserve mode + formation existante
    //           => le code aval conservera la valeur existante.
    // - ''    : colonne présente, cellule vide, overwrite_empty activé ou nouvelle formation
    //           => écrase avec vide.
    // - str   : cellule non vide => écrase avec la valeur.
    $get_value = function( $field ) use ( $row, $mapping, $existing, $overwrite_empty ) {
      if ( ! isset( $mapping[ $field ] ) ) {
        return null;
      }
      $index = (int) $mapping[ $field ];
      $val   = isset( $row[ $index ] ) ? trim( (string) $row[ $index ] ) : '';
      if ( '' === $val && ! $overwrite_empty && $existing ) {
        return null; // cellule vide + preserve + enregistrement existant => ne pas écraser
      }
      return $val;
    };

    // Retourne une chaîne sanitized (texte court) en conservant la valeur existante si null.
    $get_str = function( $field ) use ( $get_value, $existing ) {
      $raw = $get_value( $field );
      if ( null === $raw ) {
        return $existing && isset( $existing->{$field} ) ? $existing->{$field} : '';
      }
      return sanitize_text_field( $raw );
    };

    // Retourne une chaîne sanitized (textarea) en conservant la valeur existante si null.
    $get_textarea = function( $field ) use ( $get_value, $existing ) {
      $raw = $get_value( $field );
      if ( null === $raw ) {
        return $existing && isset( $existing->{$field} ) ? $existing->{$field} : '';
      }
      return sanitize_textarea_field( $raw );
    };

    // Retourne 0 ou 1.
    // Règles OUI  : oui, o, 1, yes, y, true, vrai, x, active, actif, enabled
    // Règles NON  : non, 0, false, faux, cellule vide (en mode overwrite), ou null => valeur existante
    $get_bool = function( $field ) use ( $get_value, $existing ) {
      $raw = $get_value( $field );
      if ( null === $raw ) {
        // Colonne absente ou cellule vide + preserve : conserver la valeur existante.
        return $existing && isset( $existing->{$field} ) ? (int) $existing->{$field} : 0;
      }
      $normalized = strtolower( remove_accents( trim( (string) $raw ) ) );
      if ( '' === $normalized ) {
        // Cellule vide + overwrite_empty actif : conserver valeur existante ou défaut 0.
        return $existing && isset( $existing->{$field} ) ? (int) $existing->{$field} : 0;
      }
      return in_array( $normalized, array( '1', 'oui', 'o', 'yes', 'y', 'true', 'vrai', 'x', 'active', 'activee', 'actif', 'enabled' ), true ) ? 1 : 0;
    };

    $title = $get_value( 'title' );
    // Si le titre est absent ou vide mais qu'une formation existante est identifiée (par ID),
    // conserver son titre existant plutôt que de rejeter la ligne.
    if ( ( null === $title || '' === $title ) && $existing && ! empty( $existing->title ) ) {
      $title = $existing->title;
    }
    if ( null === $title || '' === $title ) {
      return new \WP_Error( 'acdc_import_missing_title', 'Intitulé manquant.' );
    }

    // Statut : importé si colonne présente et valeur valide ; sinon conserver l'existant ou VALIDÉE.
    $raw_status     = $get_value( 'status' );
    $allowed_status = array( 'VALIDÉE', 'VALIDE', 'BROUILLON', 'ARCHIVÉE', 'ARCHIVEE' );
    if ( null !== $raw_status && '' !== $raw_status && in_array( strtoupper( remove_accents( (string) $raw_status ) ), $allowed_status, true ) ) {
      $import_status = sanitize_text_field( $raw_status );
    } else {
      $import_status = $existing && ! empty( $existing->status ) ? sanitize_text_field( $existing->status ) : 'VALIDÉE';
    }

    $data = array(
      'title'                       => sanitize_text_field( $title ),
      'status'                      => $import_status,
      'qualiopi_compliant'          => $get_bool( 'qualiopi_compliant' ),
      'duration'                    => $get_str( 'duration' ),
      'modality'                    => $get_str( 'modality' ),
      'description_text'            => $get_textarea( 'description_text' ),
      'prerequisites'               => $get_textarea( 'prerequisites' ),
      'objectives'                  => $get_textarea( 'objectives' ),
      'program'                     => $get_textarea( 'program' ),
      'address'                     => $get_str( 'address' ),
      'postal_code'                 => $get_str( 'postal_code' ),
      'city'                        => $get_str( 'city' ),
      'price_ht'                    => $get_str( 'price_ht' ),
      'specialty'                   => $get_str( 'specialty' ),
      'catalog_audience'            => $get_textarea( 'catalog_audience' ),
      'moyens_pedago'             => $get_textarea( 'moyens_pedago' ),
      'ressources'                => $get_textarea( 'ressources' ),
      'sanction'                  => $get_textarea( 'sanction' ),
      'evaluation_entree'         => $get_textarea( 'evaluation_entree' ),
      'evaluation_sortie'         => $get_textarea( 'evaluation_sortie' ),
      // ACDC 3.21.36
      'accroche'                  => $get_textarea( 'accroche' ),
      'trainer_ref'               => $get_textarea( 'trainer_ref' ),
      'accessibilite'             => $get_textarea( 'accessibilite' ),
      'referent_handicap'         => $get_str( 'referent_handicap' ),
      'suivi'                     => $get_textarea( 'suivi' ),
      'lieu_acces'                => $get_textarea( 'lieu_acces' ),
      'demarches'                 => $get_textarea( 'demarches' ),
      'annulation'                => $get_textarea( 'annulation' ),
      'platform_url'              => $get_str( 'platform_url' ),
      'effectif_min'              => (int) ( $get_value( 'effectif_min' ) ?? ( $existing ? (int) ( $existing->effectif_min ?? 0 ) : 0 ) ),
      'effectif_max'              => (int) ( $get_value( 'effectif_max' ) ?? ( $existing ? (int) ( $existing->effectif_max ?? 0 ) : 0 ) ),
      'cpf_eligible'              => $get_bool( 'cpf_eligible' ),
      'cpf_code'                  => $get_str( 'cpf_code' ),
      'financement'               => $get_str( 'financement' ),
      'taux_reussite'             => (int) ( $get_value( 'taux_reussite' ) ?? ( $existing ? (int) ( $existing->taux_reussite ?? 0 ) : 0 ) ),
      'taux_satisfaction'         => (int) ( $get_value( 'taux_satisfaction' ) ?? ( $existing ? (int) ( $existing->taux_satisfaction ?? 0 ) : 0 ) ),
      'taux_recommandation'       => (int) ( $get_value( 'taux_recommandation' ) ?? ( $existing ? (int) ( $existing->taux_recommandation ?? 0 ) : 0 ) ),
      'taux_completion'           => (int) ( $get_value( 'taux_completion' ) ?? ( $existing ? (int) ( $existing->taux_completion ?? 0 ) : 0 ) ),
      'catalog_image_url'         => $get_str( 'catalog_image_url' ),
      'program_file_url'          => $get_str( 'program_file_url' ),
      'manager_formation_id'      => ( null !== $get_value( 'manager_formation_id' ) ) ? absint( $get_value( 'manager_formation_id' ) ) : ( $existing ? (int) ( $existing->manager_formation_id ?? 0 ) : 0 ),
      'convocation_enabled'         => $get_bool( 'convocation_enabled' ),
      'positioning_test_enabled'    => $get_bool( 'positioning_test_enabled' ),
      'intermediate_survey_enabled' => $get_bool( 'intermediate_survey_enabled' ),
      'hot_survey_enabled'          => $get_bool( 'hot_survey_enabled' ),
      'evaluation_enabled'          => $get_bool( 'evaluation_enabled' ),
      'end_documents_enabled'       => $get_bool( 'end_documents_enabled' ),
      'cold_survey_enabled'         => $get_bool( 'cold_survey_enabled' ),
      'trainer_survey_enabled'      => $get_bool( 'trainer_survey_enabled' ),
      'company_survey_enabled'      => $get_bool( 'company_survey_enabled' ),
      'is_draft'                    => $existing && isset( $existing->is_draft ) ? (int) $existing->is_draft : 0,
      'is_active'                   => $existing && isset( $existing->is_active ) ? (int) $existing->is_active : 1,
      'updated_at'                  => $this->now_mysql(),
    );

    // Champs non importés par l'Excel : conserver depuis l'existant, ou initialiser pour une création.
    if ( $existing ) {
      // specialty est déjà dans $data via $get_str (conserver existing si null) — préserver les autres.
      foreach ( array( 'code', 'program_file_url', 'include_bpf', 'service_objective', 'shared_docs', 'internal_docs', 'shared_links', 'catalog_public', 'catalog_slug', 'catalog_image_url', 'catalog_order', 'future_sessions', 'notes', 'created_at' ) as $field ) {
        if ( isset( $existing->{$field} ) && ! isset( $data[ $field ] ) ) {
          $data[ $field ] = $existing->{$field};
        }
      }
    } else {
      $data['created_at']      = $this->now_mysql();
      $data['catalog_public']  = 0;
      $data['catalog_slug']    = '';
      $data['catalog_order']   = 0;
      $data['program_file_url'] = '';
      $data['shared_docs']     = '';
      $data['internal_docs']   = '';
      $data['shared_links']    = '';
      $data['catalog_image_url'] = '';
      $data['future_sessions'] = '';
      $data['notes']           = '';
      $data['code']            = '';
      $data['include_bpf']     = 0;
      $data['service_objective'] = '';
      // specialty déjà initialisé via $get_str (retourne '' si colonne absente et pas d'existant).
    }

    // ---------- Programme pédagogique structuré ----------
    // Clés générées par map_formation_import_headers : prog_jN_matin_titre, etc.
    $blocs_programme = array();
    for ( $j = 1; $j <= 5; $j++ ) {
      foreach ( array( 'matin', 'apm' ) as $moment ) {
        $pfx  = 'prog_j' . $j . '_' . $moment . '_';
        $titre = $get_value( $pfx . 'titre' );
        $cont  = $get_value( $pfx . 'contenus' );
        $comp  = $get_value( $pfx . 'competences' );
        $opo   = $get_value( $pfx . 'opo' );
        // Si toutes les valeurs sont null, la colonne est absente du fichier : ne rien faire.
        if ( null === $titre && null === $cont && null === $comp && null === $opo ) {
          continue;
        }
        // Si au moins une cellule a une valeur non vide, enregistrer ce bloc.
        if ( '' !== (string) $titre || '' !== (string) $cont
          || '' !== (string) $comp  || '' !== (string) $opo ) {
          $blocs_programme[] = array(
            'jour'        => $j,
            'moment'      => $moment,
            'titre'       => sanitize_text_field( (string) $titre ),
            'contenus'    => sanitize_textarea_field( (string) $cont ),
            'competences' => sanitize_textarea_field( (string) $comp ),
            'opo'         => sanitize_textarea_field( (string) $opo ),
          );
        }
      }
    }

    if ( ! empty( $blocs_programme ) ) {
      // Des blocs ont été trouvés dans l'import : écraser programme_detail.
      $data['programme_detail'] = wp_json_encode( $blocs_programme, JSON_UNESCAPED_UNICODE );
    } elseif ( $existing && ! empty( $existing->programme_detail ) ) {
      // Aucun bloc dans ce fichier (colonnes absentes ou toutes vides) : préserver l'existant.
      $data['programme_detail'] = $existing->programme_detail;
    }
    // Pas de programme et pas d'existant : ne pas écrire la clé (NULL en base).

    return $data;
  }

  private function get_dynamic_css( $dummy = null ) {

  $o = $this->get_branding_options();

  $sidebar_width = max( 280, intval( $o['sidebar_width'] ) );
  $menu_font_size = max( 12, intval( $o['menu_font_size'] ) );
  $radius = max( 10, intval( $o['radius'] ) );
  $button_height = max( 36, intval( isset( $o['button_height'] ) ? $o['button_height'] : 40 ) );
  $button_padding_x = max( 8, intval( isset( $o['button_padding_x'] ) ? $o['button_padding_x'] : 14 ) );
  $field_height = max( 36, intval( isset( $o['field_height'] ) ? $o['field_height'] : 40 ) );
  $textarea_height = max( 72, intval( isset( $o['textarea_height'] ) ? $o['textarea_height'] : 96 ) );
  $icon_size = max( 18, intval( isset( $o['icon_size'] ) ? $o['icon_size'] : 25 ) );
  $action_icon_frame_size = max( 24, intval( isset( $o['action_icon_frame_size'] ) ? $o['action_icon_frame_size'] : 30 ) );
  $action_icon_glyph_size = max( 12, intval( isset( $o['action_icon_glyph_size'] ) ? $o['action_icon_glyph_size'] : 16 ) );
  $action_icon_gap = max( 2, intval( isset( $o['action_icon_gap'] ) ? $o['action_icon_gap'] : 8 ) );
  $action_icon_radius = max( 4, intval( isset( $o['action_icon_radius'] ) ? $o['action_icon_radius'] : 10 ) );
  $toggle_width = max( 30, intval( isset( $o['toggle_width'] ) ? $o['toggle_width'] : 44 ) );
  $toggle_height = max( 18, intval( isset( $o['toggle_height'] ) ? $o['toggle_height'] : 25 ) );
  $table_cell_padding_y = max( 6, intval( isset( $o['table_cell_padding_y'] ) ? $o['table_cell_padding_y'] : 12 ) );
  $table_cell_padding_x = max( 8, intval( isset( $o['table_cell_padding_x'] ) ? $o['table_cell_padding_x'] : 12 ) );
  $table_row_height = max( 32, intval( isset( $o['table_row_height'] ) ? $o['table_row_height'] : 48 ) );
  $page_title_size = max( 18, intval( isset( $o['page_title_size'] ) ? $o['page_title_size'] : 25 ) );
  $subtitle_size = max( 14, intval( isset( $o['subtitle_size'] ) ? $o['subtitle_size'] : 18 ) );
  $body_font_size = max( 12, intval( isset( $o['body_font_size'] ) ? $o['body_font_size'] : 14 ) );
  $label_font_size = max( 11, intval( isset( $o['label_font_size'] ) ? $o['label_font_size'] : 13 ) );
  $h1_font_size = max( 16, intval( isset( $o['h1_font_size'] ) ? $o['h1_font_size'] : $page_title_size ) );
  $h2_font_size = max( 14, intval( isset( $o['h2_font_size'] ) ? $o['h2_font_size'] : 20 ) );
  $h3_font_size = max( 13, intval( isset( $o['h3_font_size'] ) ? $o['h3_font_size'] : 18 ) );
  $h4_font_size = max( 12, intval( isset( $o['h4_font_size'] ) ? $o['h4_font_size'] : 16 ) );
  $section_title_size = max( 13, intval( isset( $o['section_title_size'] ) ? $o['section_title_size'] : $h2_font_size ) );
  $small_text_size = max( 10, intval( isset( $o['small_text_size'] ) ? $o['small_text_size'] : 12 ) );
  $field_font_size = max( 10, intval( isset( $o['field_font_size'] ) ? $o['field_font_size'] : $body_font_size ) );
  $button_font_size = max( 10, intval( isset( $o['button_font_size'] ) ? $o['button_font_size'] : 13 ) );
  $table_header_font_size = max( 10, intval( isset( $o['table_header_font_size'] ) ? $o['table_header_font_size'] : 12 ) );
  $table_row_font_size = max( 10, intval( isset( $o['table_row_font_size'] ) ? $o['table_row_font_size'] : 13 ) );
  $table_action_font_size = max( 10, intval( isset( $o['table_action_font_size'] ) ? $o['table_action_font_size'] : 13 ) );
  $badge_font_size = max( 10, intval( isset( $o['badge_font_size'] ) ? $o['badge_font_size'] : 12 ) );
  $tab_font_size = max( 10, intval( isset( $o['tab_font_size'] ) ? $o['tab_font_size'] : 13 ) );
  $modal_title_size = max( 13, intval( isset( $o['modal_title_size'] ) ? $o['modal_title_size'] : 18 ) );
  $modal_body_size = max( 10, intval( isset( $o['modal_body_size'] ) ? $o['modal_body_size'] : $body_font_size ) );
  $help_text_size = max( 10, intval( isset( $o['help_text_size'] ) ? $o['help_text_size'] : 12 ) );
  $error_text_size = max( 10, intval( isset( $o['error_text_size'] ) ? $o['error_text_size'] : 12 ) );
  $kpi_label_size = max( 10, intval( isset( $o['kpi_label_size'] ) ? $o['kpi_label_size'] : 12 ) );
  $kpi_value_size = max( 14, intval( isset( $o['kpi_value_size'] ) ? $o['kpi_value_size'] : 24 ) );
  $weight = static function( $value, $fallback ) { return preg_match( '/^(300|400|500|600|700|800)$/', (string) $value ) ? (string) $value : (string) $fallback; };
  $page_title_weight = $weight( isset( $o['page_title_weight'] ) ? $o['page_title_weight'] : '700', '700' );
  $h1_font_weight = $weight( isset( $o['h1_font_weight'] ) ? $o['h1_font_weight'] : $page_title_weight, '700' );
  $h2_font_weight = $weight( isset( $o['h2_font_weight'] ) ? $o['h2_font_weight'] : '600', '600' );
  $h3_font_weight = $weight( isset( $o['h3_font_weight'] ) ? $o['h3_font_weight'] : '600', '600' );
  $h4_font_weight = $weight( isset( $o['h4_font_weight'] ) ? $o['h4_font_weight'] : '600', '600' );
  $section_title_weight = $weight( isset( $o['section_title_weight'] ) ? $o['section_title_weight'] : '600', '600' );
  $subtitle_weight = $weight( isset( $o['subtitle_weight'] ) ? $o['subtitle_weight'] : '600', '600' );
  $body_font_weight = $weight( isset( $o['body_font_weight'] ) ? $o['body_font_weight'] : '400', '400' );
  $small_text_weight = $weight( isset( $o['small_text_weight'] ) ? $o['small_text_weight'] : '400', '400' );
  $label_font_weight = $weight( isset( $o['label_font_weight'] ) ? $o['label_font_weight'] : '500', '500' );
  $field_font_weight = $weight( isset( $o['field_font_weight'] ) ? $o['field_font_weight'] : '400', '400' );
  $button_font_weight = $weight( isset( $o['button_font_weight'] ) ? $o['button_font_weight'] : '500', '500' );
  $table_header_font_weight = $weight( isset( $o['table_header_font_weight'] ) ? $o['table_header_font_weight'] : '600', '600' );
  $table_row_font_weight = $weight( isset( $o['table_row_font_weight'] ) ? $o['table_row_font_weight'] : '400', '400' );
  $badge_font_weight = $weight( isset( $o['badge_font_weight'] ) ? $o['badge_font_weight'] : '600', '600' );
  $menu_font_weight = $weight( isset( $o['menu_font_weight'] ) ? $o['menu_font_weight'] : '500', '500' );
  $tab_font_weight = $weight( isset( $o['tab_font_weight'] ) ? $o['tab_font_weight'] : '600', '600' );
  $modal_title_weight = $weight( isset( $o['modal_title_weight'] ) ? $o['modal_title_weight'] : '700', '700' );
  $kpi_value_weight = $weight( isset( $o['kpi_value_weight'] ) ? $o['kpi_value_weight'] : '700', '700' );
  $font_weight = preg_match( '/^(400|500|600|700|800)$/', (string) $o['font_weight'] ) ? (string) $o['font_weight'] : '500';
  $line_height = preg_match( '/^[0-9.]+$/', (string) $o['line_height'] ) ? (string) $o['line_height'] : '1.5';
  $panel_padding = max( 8, intval( isset( $o['panel_padding'] ) ? $o['panel_padding'] : 16 ) );
  $modal_padding = max( 8, intval( isset( $o['modal_padding'] ) ? $o['modal_padding'] : 14 ) );
  $panel_shadow = isset( $o['panel_shadow'] ) ? sanitize_text_field( $o['panel_shadow'] ) : '0 4px 14px rgba(28, 44, 64, 0.05)';
  $modal_shadow = isset( $o['modal_shadow'] ) ? sanitize_text_field( $o['modal_shadow'] ) : '0 10px 30px rgba(28, 44, 64, 0.12)';
  $table_header_bg = isset( $o['table_header_bg'] ) ? ( sanitize_hex_color( $o['table_header_bg'] ) ?: '#fbf8f7' ) : '#fbf8f7';
  $table_header_text = isset( $o['table_header_text'] ) ? ( sanitize_hex_color( $o['table_header_text'] ) ?: '#0C2D52' ) : '#0C2D52';
  $table_border = isset( $o['table_border'] ) ? ( sanitize_hex_color( $o['table_border'] ) ?: '#DCE4EC' ) : '#DCE4EC';
  $field_bg = isset( $o['field_bg'] ) ? ( sanitize_hex_color( $o['field_bg'] ) ?: '#FFFFFF' ) : '#FFFFFF';
  $field_border = isset( $o['field_border'] ) ? ( sanitize_hex_color( $o['field_border'] ) ?: '#DCE4EC' ) : '#DCE4EC';
  $field_focus = isset( $o['field_focus'] ) ? ( sanitize_hex_color( $o['field_focus'] ) ?: '#C5A253' ) : '#C5A253';
  $field_error = isset( $o['field_error'] ) ? ( sanitize_hex_color( $o['field_error'] ) ?: '#E06D6D' ) : '#E06D6D';
  $panel_bg = isset( $o['panel_bg'] ) ? ( sanitize_hex_color( $o['panel_bg'] ) ?: '#FFFFFF' ) : '#FFFFFF';
  $panel_header_bg = isset( $o['panel_header_bg'] ) ? ( sanitize_hex_color( $o['panel_header_bg'] ) ?: '#FBF8F7' ) : '#FBF8F7';
  $panel_footer_bg = isset( $o['panel_footer_bg'] ) ? ( sanitize_hex_color( $o['panel_footer_bg'] ) ?: '#FFFFFF' ) : '#FFFFFF';
  $modal_bg = isset( $o['modal_bg'] ) ? ( sanitize_hex_color( $o['modal_bg'] ) ?: '#FFFFFF' ) : '#FFFFFF';
  $modal_header_bg = isset( $o['modal_header_bg'] ) ? ( sanitize_hex_color( $o['modal_header_bg'] ) ?: '#FBF8F7' ) : '#FBF8F7';
  $modal_footer_bg = isset( $o['modal_footer_bg'] ) ? ( sanitize_hex_color( $o['modal_footer_bg'] ) ?: '#FFFFFF' ) : '#FFFFFF';
  $toggle_active_color = isset( $o['toggle_active_color'] ) ? ( sanitize_hex_color( $o['toggle_active_color'] ) ?: '#8b5b23' ) : '#8b5b23';
  $toggle_inactive_color = isset( $o['toggle_inactive_color'] ) ? ( sanitize_hex_color( $o['toggle_inactive_color'] ) ?: '#d9dfe8' ) : '#d9dfe8';
  $button_focus_ring = isset( $o['button_focus_ring'] ) ? ( sanitize_hex_color( $o['button_focus_ring'] ) ?: '#C5A253' ) : '#C5A253';
  $button_disabled_opacity = preg_match( '/^[0-9.]+$/', (string) $o['button_disabled_opacity'] ) ? (string) $o['button_disabled_opacity'] : '0.55';

  $bg = sanitize_hex_color( $o['color_bg'] ) ?: '#F6F8FB';
  $surface = sanitize_hex_color( $o['color_surface'] ) ?: '#F9FAFB';
  $text = sanitize_hex_color( $o['color_text'] ) ?: '#0C2D52';
  $text_muted = sanitize_hex_color( $o['color_text_muted'] ) ?: '#1E4777';
  $primary = sanitize_hex_color( $o['color_primary'] ) ?: '#C5A253';
  $secondary = sanitize_hex_color( $o['color_secondary'] ) ?: '#F3E3BF';
  $button = sanitize_hex_color( $o['color_button'] ) ?: '#D7A24B';
  $button_text = sanitize_hex_color( $o['color_button_text'] ) ?: '#0B0706';
  $link = sanitize_hex_color( $o['color_link'] ) ?: '#0C2D52';
  $border = sanitize_hex_color( $o['color_border'] ) ?: '#DCE4EC';

  $button_primary_style = isset( $o['button_style_primary'] ) ? sanitize_key( $o['button_style_primary'] ) : 'gradient';
  $button_secondary_style = isset( $o['button_style_secondary'] ) ? sanitize_key( $o['button_style_secondary'] ) : 'soft';
  $button_danger_style = isset( $o['button_style_danger'] ) ? sanitize_key( $o['button_style_danger'] ) : 'outline';
  $button_hover_mode = isset( $o['button_hover_mode'] ) ? sanitize_key( $o['button_hover_mode'] ) : 'brighten';
  $icon_style = isset( $o['icon_style'] ) ? sanitize_key( $o['icon_style'] ) : 'outline';
  $menu_dots_style = isset( $o['menu_dots_style'] ) ? sanitize_key( $o['menu_dots_style'] ) : 'filled';
  $select_style = isset( $o['select_style'] ) ? sanitize_key( $o['select_style'] ) : 'standard';
  $checkbox_style = isset( $o['checkbox_style'] ) ? sanitize_key( $o['checkbox_style'] ) : 'rounded';
  $radio_style = isset( $o['radio_style'] ) ? sanitize_key( $o['radio_style'] ) : 'rounded';
  $actions_column_style = isset( $o['actions_column_style'] ) ? sanitize_key( $o['actions_column_style'] ) : 'icons';

  $vars = array(
    '--acdc-font' => $o['font_family'],
    '--acdc-sidebar-width' => $sidebar_width . 'px',
    '--acdc-menu-font-size' => $menu_font_size . 'px',
    '--acdc-radius' => $radius . 'px',
    '--acdc-radius-sm' => $radius . 'px',
    '--acdc-radius-panel' => $radius . 'px',
    '--acdc-radius-modal' => $radius . 'px',
    '--acdc-radius-pill' => $radius . 'px',
    '--acdc-bg' => $bg,
    '--acdc-soft-bg' => '#fbf8f7',
    '--acdc-surface' => $surface,
    '--acdc-white' => $surface,
    '--acdc-text' => $text,
    '--acdc-text-muted' => $text_muted,
    '--acdc-text-light' => '#3C3C3C',
    '--acdc-primary' => $primary,
    '--acdc-primary-dark' => '#D7A24B',
    '--acdc-primary-soft' => $secondary,
    '--acdc-secondary' => '#E9C77C',
    '--acdc-secondary-accent' => '#C9A409',
    '--acdc-button' => $button,
    '--acdc-button-text' => $button_text,
    '--acdc-button-gradient' => 'linear-gradient(90deg, #8A5A2B 0%, #C28A3A 17%, #D7A24B 34%, #E9C77C 50%, #D7A24B 67%, #B7772E 84%, #6B3F1D 100%)',
    '--acdc-button-secondary-bg' => '#E9C77C',
    '--acdc-button-soft-bg' => '#F3E3BF',
    '--acdc-button-hover' => '#E8D2A0',
    '--acdc-button-height' => $button_height . 'px',
    '--acdc-button-padding-x' => $button_padding_x . 'px',
    '--acdc-button-disabled-opacity' => $button_disabled_opacity,
    '--acdc-field-height' => $field_height . 'px',
    '--acdc-textarea-height' => $textarea_height . 'px',
    '--acdc-icon-size' => $icon_size . 'px',
    '--acdc-action-icon-frame-size' => $action_icon_frame_size . 'px',
    '--acdc-action-icon-box-size' => $action_icon_frame_size . 'px',
    '--acdc-action-icon-glyph-size' => $action_icon_glyph_size . 'px',
    '--acdc-action-icon-gap' => $action_icon_gap . 'px',
    '--acdc-action-icon-radius' => $action_icon_radius . 'px',
    '--acdc-action-icon-border' => $o['action_icon_border'],
    '--acdc-action-icon-bg' => $o['action_icon_bg'],
    '--acdc-action-icon-color' => $o['action_icon_color'],
    '--acdc-action-icon-hover-bg' => $o['action_icon_hover_bg'],
    '--acdc-icon-color' => $primary,
    '--acdc-link' => $link,
    '--acdc-border' => $border,
    '--acdc-border-soft' => '#E7EDF3',
    '--acdc-border-strong' => '#0B0706',
    '--acdc-divider' => $primary,
    '--acdc-success' => '#35B37E',
    '--acdc-warning' => '#F0B45E',
    '--acdc-danger' => '#E06D6D',
    '--acdc-info' => '#6E8FB3',
    '--acdc-shadow-panel' => $panel_shadow,
    '--acdc-shadow-modal' => $modal_shadow,
    '--acdc-table-header-bg' => $table_header_bg,
    '--acdc-table-header-text' => $table_header_text,
    '--acdc-page-title-size' => $page_title_size . 'px',
    '--acdc-subtitle-size' => $subtitle_size . 'px',
    '--acdc-body-size' => $body_font_size . 'px',
    '--acdc-label-size' => $label_font_size . 'px',
    '--acdc-page-title-weight' => $page_title_weight,
    '--acdc-h1-size' => $h1_font_size . 'px',
    '--acdc-h1-weight' => $h1_font_weight,
    '--acdc-h2-size' => $h2_font_size . 'px',
    '--acdc-h2-weight' => $h2_font_weight,
    '--acdc-h3-size' => $h3_font_size . 'px',
    '--acdc-h3-weight' => $h3_font_weight,
    '--acdc-h4-size' => $h4_font_size . 'px',
    '--acdc-h4-weight' => $h4_font_weight,
    '--acdc-section-title-size' => $section_title_size . 'px',
    '--acdc-section-title-weight' => $section_title_weight,
    '--acdc-subtitle-weight' => $subtitle_weight,
    '--acdc-body-weight' => $body_font_weight,
    '--acdc-small-size' => $small_text_size . 'px',
    '--acdc-small-weight' => $small_text_weight,
    '--acdc-label-weight' => $label_font_weight,
    '--acdc-field-font-size' => $field_font_size . 'px',
    '--acdc-field-font-weight' => $field_font_weight,
    '--acdc-button-font-size' => $button_font_size . 'px',
    '--acdc-button-font-weight' => $button_font_weight,
    '--acdc-table-header-size' => $table_header_font_size . 'px',
    '--acdc-table-header-weight' => $table_header_font_weight,
    '--acdc-table-row-size' => $table_row_font_size . 'px',
    '--acdc-table-row-weight' => $table_row_font_weight,
    '--acdc-table-action-size' => $table_action_font_size . 'px',
    '--acdc-badge-size' => $badge_font_size . 'px',
    '--acdc-badge-weight' => $badge_font_weight,
    '--acdc-menu-font-weight' => $menu_font_weight,
    '--acdc-tab-size' => $tab_font_size . 'px',
    '--acdc-tab-weight' => $tab_font_weight,
    '--acdc-modal-title-size' => $modal_title_size . 'px',
    '--acdc-modal-title-weight' => $modal_title_weight,
    '--acdc-modal-body-size' => $modal_body_size . 'px',
    '--acdc-help-size' => $help_text_size . 'px',
    '--acdc-error-size' => $error_text_size . 'px',
    '--acdc-kpi-label-size' => $kpi_label_size . 'px',
    '--acdc-kpi-value-size' => $kpi_value_size . 'px',
    '--acdc-kpi-value-weight' => $kpi_value_weight,
    '--acdc-font-weight' => $font_weight,
    '--acdc-line-height' => $line_height,
    '--acdc-field-bg' => $field_bg,
    '--acdc-field-border' => $field_border,
    '--acdc-field-focus' => $field_focus,
    '--acdc-field-error' => $field_error,
    '--acdc-table-cell-padding-y' => $table_cell_padding_y . 'px',
    '--acdc-table-cell-padding-x' => $table_cell_padding_x . 'px',
    '--acdc-table-row-height' => $table_row_height . 'px',
    '--acdc-table-border' => $table_border,
    '--acdc-toggle-active' => $toggle_active_color,
    '--acdc-toggle-inactive' => $toggle_inactive_color,
    '--acdc-toggle-width' => $toggle_width . 'px',
    '--acdc-toggle-height' => $toggle_height . 'px',
    '--acdc-panel-bg' => $panel_bg,
    '--acdc-panel-header-bg' => $panel_header_bg,
    '--acdc-panel-footer-bg' => $panel_footer_bg,
    '--acdc-panel-padding' => $panel_padding . 'px',
    '--acdc-modal-bg' => $modal_bg,
    '--acdc-modal-header-bg' => $modal_header_bg,
    '--acdc-modal-footer-bg' => $modal_footer_bg,
    '--acdc-modal-padding' => $modal_padding . 'px',
    '--acdc-focus-color' => $button_focus_ring,
    '--acdc-focus-ring' => '0 0 0 3px rgba(197, 162, 83, 0.18)',
  );

  $css = ':root{';
  foreach ( $vars as $key => $value ) {
    $css .= $key . ':' . $value . ';';
  }
  $css .= '}';

  $css .= 'body,.acdc-portal-shell,.acdc-page,.acdc-panel,.acdc-table,.acdc-form,.acdc-form-panel,.acdc-config-form,.acdc-contract-builder-form,.acdc-company-profile-form,.acdc-needs-form,.acdc-admin-shell,.acdc-admin-shell *{font-family:var(--acdc-font);font-size:var(--acdc-body-size);line-height:var(--acdc-line-height);font-weight:var(--acdc-body-weight);}';
  $css .= '.wrap h1,.acdc-page h1,.acdc-dashboard-main h1,.acdc-panel h1,.acdc-admin-shell h1,.acdc-ui-preview-brand-name{font-size:var(--acdc-page-title-size)!important;line-height:1.2;font-weight:var(--acdc-page-title-weight)!important;color:var(--acdc-text);}';
  $css .= '.acdc-page h1,.acdc-dashboard-main h1{font-size:var(--acdc-h1-size)!important;font-weight:var(--acdc-h1-weight)!important;}';
  $css .= '.wrap h2,.acdc-page h2,.acdc-panel h2,.acdc-section-head h2,.acdc-needs-section-title,.acdc-workflow-label{font-size:var(--acdc-h2-size)!important;line-height:1.35;font-weight:var(--acdc-h2-weight)!important;color:var(--acdc-text);}';
  $css .= '.wrap h3,.acdc-page h3,.acdc-panel h3,.acdc-profile-section h3,.acdc-company-profile-form .acdc-panel h3,.acdc-card-title,.acdc-panel-title,.acdc-section-title{font-size:var(--acdc-h3-size)!important;line-height:1.35;font-weight:var(--acdc-h3-weight)!important;color:var(--acdc-text);}';
  $css .= '.wrap h4,.acdc-page h4,.acdc-panel h4{font-size:var(--acdc-h4-size)!important;font-weight:var(--acdc-h4-weight)!important;}';
  $css .= '.acdc-section-head h2,.acdc-panel-heading,.acdc-needs-section-title{font-size:var(--acdc-section-title-size)!important;font-weight:var(--acdc-section-title-weight)!important;}';
  $css .= '.description,.acdc-help-inline,.acdc-help-text,.acdc-field-help,.acdc-muted,.acdc-meta,.acdc-card-meta,small{font-size:var(--acdc-small-size)!important;font-weight:var(--acdc-small-weight)!important;line-height:1.45;}';
  $css .= 'label,.acdc-form label,.acdc-form-panel label,.acdc-contract-label,.acdc-stat-label{font-size:var(--acdc-label-size)!important;font-weight:var(--acdc-label-weight)!important;}';
  $css .= '.acdc-panel,.acdc-workflow-card,.acdc-formation-section,.acdc-contract-section-card,.acdc-panel-collapsible,.acdc-profile-section,.acdc-company-profile-form .acdc-panel{background:var(--acdc-panel-bg)!important;border-color:var(--acdc-border)!important;border-radius:var(--acdc-radius-panel)!important;box-shadow:var(--acdc-shadow-panel)!important;}';
  $css .= '.acdc-panel,.acdc-workflow-card,.acdc-formation-section,.acdc-contract-section-card,.acdc-panel-collapsible{padding:var(--acdc-panel-padding)!important;}';
  $css .= '.acdc-profile-section h3,.acdc-company-profile-form .acdc-panel h3,.acdc-needs-section-title,.acdc-panel-heading{background:var(--acdc-panel-header-bg)!important;}';
  $css .= '.acdc-form input,.acdc-form textarea,.acdc-form select,.acdc-config-form input,.acdc-form-panel input[type="text"],.acdc-form-panel input[type="number"],.acdc-form-panel input[type="file"],.acdc-form-panel input[type="email"],.acdc-form-panel input[type="date"],.acdc-form-panel input[type="time"],.acdc-form-panel input[type="tel"],.acdc-form-panel input[type="search"],.acdc-form-panel input[type="url"],.acdc-form-panel select,.acdc-form-panel textarea,.acdc-search-bar input[type="search"],.acdc-list-toolbar input[type="search"],.acdc-contract-grid input[type="text"],.acdc-contract-grid input[type="number"],.acdc-contract-grid input[type="email"],.acdc-contract-grid input[type="date"],.acdc-contract-grid input[type="time"],.acdc-contract-grid input[type="tel"],.acdc-contract-grid input[type="search"],.acdc-contract-grid input[type="url"],.acdc-contract-grid select,.acdc-contract-grid textarea{min-height:var(--acdc-field-height)!important;border-radius:var(--acdc-radius-panel)!important;border-color:var(--acdc-field-border)!important;background:var(--acdc-field-bg)!important;color:var(--acdc-text)!important;font-size:var(--acdc-field-font-size)!important;font-weight:var(--acdc-field-font-weight)!important;}';
  $css .= '.acdc-form textarea,.acdc-form-panel textarea,.acdc-needs-section textarea,.acdc-company-profile-form textarea,.acdc-contract-builder-form textarea,.acdc-contract-grid textarea{min-height:var(--acdc-textarea-height)!important;}';
  $css .= '.acdc-form input:focus,.acdc-form textarea:focus,.acdc-form select:focus,.acdc-config-form input:focus,.acdc-form-panel input:focus,.acdc-form-panel select:focus,.acdc-form-panel textarea:focus,.acdc-search-bar input[type="search"]:focus,.acdc-list-toolbar input[type="search"]:focus,.acdc-contract-grid input:focus,.acdc-contract-grid select:focus,.acdc-contract-grid textarea:focus{border-color:var(--acdc-field-focus)!important;box-shadow:0 0 0 3px color-mix(in srgb, var(--acdc-field-focus) 18%, transparent)!important;}';
  $css .= 'input[type="checkbox"],input[type="radio"]{accent-color:var(--acdc-primary);}';
  $css .= ' .acdc-action-cell,.acdc-table-actions,.acdc-actions,.acdc-row-actions,.acdc-prospect-actions{display:inline-flex;align-items:center;justify-content:flex-end;gap:var(--acdc-action-icon-gap);white-space:nowrap;} .acdc-action-icon,.acdc-icon-action,.acdc-prospect-icon-link,.acdc-prospect-action-trigger,a.acdc-action-icon,button.acdc-action-icon{width:var(--acdc-action-icon-frame-size)!important;height:var(--acdc-action-icon-frame-size)!important;min-width:var(--acdc-action-icon-frame-size)!important;min-height:var(--acdc-action-icon-frame-size)!important;padding:0!important;border:1px solid var(--acdc-action-icon-border)!important;border-radius:var(--acdc-action-icon-radius)!important;background:var(--acdc-action-icon-bg)!important;color:var(--acdc-action-icon-color)!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;line-height:1!important;box-sizing:border-box;text-decoration:none!important;vertical-align:middle;} .acdc-action-icon:hover,.acdc-icon-action:hover,.acdc-prospect-icon-link:hover,.acdc-prospect-action-trigger:hover{background:var(--acdc-action-icon-hover-bg)!important;color:var(--acdc-action-icon-color)!important;} .acdc-action-icon svg,.acdc-icon-action svg,.acdc-prospect-icon-link svg,.acdc-prospect-action-trigger svg{width:var(--acdc-action-icon-glyph-size)!important;height:var(--acdc-action-icon-glyph-size)!important;min-width:var(--acdc-action-icon-glyph-size)!important;min-height:var(--acdc-action-icon-glyph-size)!important;stroke:currentColor;flex:0 0 auto;} .acdc-action-icon .acdc-icon,.acdc-icon-action .acdc-icon,.acdc-prospect-icon-link .acdc-icon,.acdc-prospect-action-trigger .acdc-icon{margin:0!important;display:block;} ';
  $css .= '.acdc-button,.acdc-btn{min-height:var(--acdc-button-height)!important;padding:10px var(--acdc-button-padding-x)!important;border-radius:var(--acdc-radius-pill)!important;font-size:var(--acdc-button-font-size)!important;line-height:16px!important;font-weight:var(--acdc-button-font-weight)!important;}';
  if ( 'solid' === $button_primary_style ) { $css .= '.acdc-button-primary,.acdc-btn-primary{background:var(--acdc-button)!important;color:var(--acdc-button-text)!important;border-color:#6F441A!important;}'; }
  elseif ( 'outline' === $button_primary_style ) { $css .= '.acdc-button-primary,.acdc-btn-primary{background:transparent!important;color:var(--acdc-primary)!important;border-color:var(--acdc-primary)!important;}'; }
  else { $css .= '.acdc-button-primary,.acdc-btn-primary{background:var(--acdc-button-gradient)!important;color:var(--acdc-button-text)!important;border-color:#6F441A!important;}'; }
  if ( 'solid' === $button_secondary_style ) { $css .= '.acdc-button-secondary,.acdc-btn-secondary{background:var(--acdc-button-secondary-bg)!important;color:#0B0706!important;border-color:#6F441A!important;}'; }
  elseif ( 'outline' === $button_secondary_style ) { $css .= '.acdc-button-secondary,.acdc-btn-secondary{background:transparent!important;color:var(--acdc-primary)!important;border-color:var(--acdc-primary)!important;}'; }
  else { $css .= '.acdc-button-secondary,.acdc-btn-secondary{background:var(--acdc-button-soft-bg)!important;color:var(--acdc-text)!important;border-color:#6F441A!important;}'; }
  if ( 'solid' === $button_danger_style ) { $css .= '.acdc-button-danger,.acdc-btn-danger{background:var(--acdc-danger)!important;color:#fff!important;border-color:var(--acdc-danger)!important;}'; }
  else { $css .= '.acdc-button-danger,.acdc-btn-danger{background:#fff!important;color:var(--acdc-danger)!important;border-color:var(--acdc-danger)!important;}'; }
  if ( 'darken' === $button_hover_mode ) { $css .= '.acdc-button:hover,.acdc-btn:hover{filter:brightness(.96)!important;}'; }
  elseif ( 'lift' === $button_hover_mode ) { $css .= '.acdc-button:hover,.acdc-btn:hover{transform:translateY(-2px)!important;box-shadow:var(--acdc-shadow-panel)!important;}'; }
  else { $css .= '.acdc-button:hover,.acdc-btn:hover{filter:brightness(1.03)!important;}'; }
  $css .= '.acdc-button:focus-visible,.acdc-btn:focus-visible{outline:none;box-shadow:0 0 0 3px color-mix(in srgb, var(--acdc-focus-color) 22%, transparent), var(--acdc-shadow-panel)!important;}';
  $css .= '.acdc-button[disabled],.acdc-btn[disabled],.acdc-button.is-disabled,.acdc-btn.is-disabled{opacity:var(--acdc-button-disabled-opacity)!important;cursor:not-allowed!important;}';
  $css .= '.acdc-table-wrap{border-color:var(--acdc-border)!important;border-radius:var(--acdc-radius-panel)!important;background:var(--acdc-panel-bg)!important;}';
  $css .= '.acdc-table th,.acdc-table td{padding:var(--acdc-table-cell-padding-y) var(--acdc-table-cell-padding-x)!important;}';
  $css .= '.acdc-table th{background:var(--acdc-table-header-bg)!important;color:var(--acdc-table-header-text)!important;border-bottom:1px solid var(--acdc-table-border)!important;font-size:var(--acdc-table-header-size)!important;font-weight:var(--acdc-table-header-weight)!important;}';
  $css .= '.acdc-table td{border-bottom:1px solid var(--acdc-table-border)!important;min-height:var(--acdc-table-row-height);color:var(--acdc-text)!important;font-size:var(--acdc-table-row-size)!important;font-weight:var(--acdc-table-row-weight)!important;}';
  if ( 'compact' === sanitize_key( $o['table_density'] ) ) { $css .= '.acdc-table th,.acdc-table td{padding-top:8px!important;padding-bottom:8px!important;}.acdc-table td{font-size:13px!important;}'; }
  elseif ( 'spacious' === sanitize_key( $o['table_density'] ) ) { $css .= '.acdc-table th,.acdc-table td{padding-top:16px!important;padding-bottom:16px!important;}.acdc-table td{font-size:14px!important;}'; }
  if ( 'text' === $actions_column_style ) { $css .= '.acdc-actions-cell-icons,.acdc-table td.acdc-actions-cell-icons{white-space:normal!important;}.acdc-row-action-icon,.acdc-row-view-link,.acdc-row-edit-link,.acdc-row-delete-link,.acdc-row-menu-toggle,.acdc-row-menu-button,.acdc-table-action-trigger,[data-acdc-prospect-menu-toggle],.acdc-prospect-menu-toggle,.acdc-bpf-action-button{width:auto!important;padding:0 6px!important;}'; }
  $css .= '.acdc-login-card,.acdc-user-menu-dropdown,.acdc-dropdown-menu,.acdc-ui-preview-modal{border-radius:var(--acdc-radius-modal)!important;box-shadow:var(--acdc-shadow-modal)!important;background:var(--acdc-modal-bg)!important;}';
  $css .= '.acdc-user-menu-dropdown,.acdc-dropdown-menu,.acdc-modal,.acdc-modal__dialog,.acdc-ui-preview-modal{background:var(--acdc-modal-bg)!important;}';
  $css .= '.acdc-ui-preview-modal-head,.acdc-modal__header{background:var(--acdc-modal-header-bg)!important;color:var(--acdc-table-header-text)!important;padding:var(--acdc-modal-padding)!important;}';
  $css .= '.acdc-ui-preview-modal-body,.acdc-modal__body{padding:var(--acdc-modal-padding)!important;}';
  $css .= '.acdc-ui-preview-modal-foot,.acdc-modal__footer{background:var(--acdc-modal-footer-bg)!important;padding:var(--acdc-modal-padding)!important;}';
  $css .= '.acdc-switch,.acdc-preview-toggle{width:var(--acdc-toggle-width)!important;height:var(--acdc-toggle-height)!important;}';
  $css .= '.acdc-switch.is-active,.acdc-switch[data-state="active"],.acdc-preview-toggle.is-on{background:var(--acdc-toggle-active)!important;}';
  $css .= '.acdc-switch.is-inactive,.acdc-switch[data-state="inactive"],.acdc-preview-toggle.is-off{background:var(--acdc-toggle-inactive)!important;}';
  $css .= '.acdc-nav-brand-title{font-size:var(--acdc-page-title-size)!important;font-weight:var(--acdc-page-title-weight)!important;}.acdc-nav-brand-subtitle,.acdc-nav-subitem{font-size:calc(var(--acdc-body-size) - 1px)!important;}.acdc-nav-item,.acdc-nav-group-summary{font-size:var(--acdc-menu-font-size)!important;font-weight:var(--acdc-menu-font-weight)!important;}.acdc-ui-subtab,.nav-tab,.acdc-tabs a{font-size:var(--acdc-tab-size)!important;font-weight:var(--acdc-tab-weight)!important;}.acdc-badge,.acdc-status-badge,.acdc-alert-status-badge{font-size:var(--acdc-badge-size)!important;font-weight:var(--acdc-badge-weight)!important;}.acdc-row-action-icon,.acdc-row-view-link,.acdc-row-edit-link,.acdc-row-delete-link,.acdc-row-menu-toggle,.acdc-table-action-trigger{font-size:var(--acdc-table-action-size)!important;}.acdc-modal__title,.acdc-modal h2,.acdc-ui-preview-modal-head strong{font-size:var(--acdc-modal-title-size)!important;font-weight:var(--acdc-modal-title-weight)!important;}.acdc-modal__body,.acdc-ui-preview-modal-body{font-size:var(--acdc-modal-body-size)!important;}.acdc-field-help-error,.acdc-error,.notice-error p{font-size:var(--acdc-error-size)!important;}.acdc-kpi-label,.acdc-stat-label{font-size:var(--acdc-kpi-label-size)!important;}.acdc-kpi-value,.acdc-stat-value,.acdc-dashboard-card strong{font-size:var(--acdc-kpi-value-size)!important;font-weight:var(--acdc-kpi-value-weight)!important;}';
  $css .= '.acdc-actions-cell-icons:not(td):not(th),.acdc-actions{display:flex!important;align-items:center!important;justify-content:flex-end!important;gap:var(--acdc-action-icon-gap)!important;}.acdc-row-action-icon,.acdc-row-view-link,.acdc-row-edit-link,.acdc-row-delete-link,.acdc-row-menu-toggle,.acdc-row-menu-button,.acdc-table-action-trigger,[data-acdc-prospect-menu-toggle],.acdc-prospect-menu-toggle,.acdc-bpf-action-button{width:var(--acdc-action-icon-box-size)!important;height:var(--acdc-action-icon-box-size)!important;min-width:var(--acdc-action-icon-box-size)!important;min-height:var(--acdc-action-icon-box-size)!important;padding:0!important;border-radius:var(--acdc-action-icon-radius)!important;border:1px solid var(--acdc-action-icon-border)!important;background:var(--acdc-action-icon-bg)!important;color:var(--acdc-action-icon-color)!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;line-height:1!important;box-sizing:border-box!important;}.acdc-row-action-icon:hover,.acdc-row-view-link:hover,.acdc-row-edit-link:hover,.acdc-row-delete-link:hover,.acdc-row-menu-toggle:hover,.acdc-row-menu-button:hover,.acdc-table-action-trigger:hover,[data-acdc-prospect-menu-toggle]:hover,.acdc-prospect-menu-toggle:hover,.acdc-bpf-action-button:hover{background:var(--acdc-action-icon-hover-bg)!important;}.acdc-row-action-icon svg,.acdc-row-view-link svg,.acdc-row-edit-link svg,.acdc-row-delete-link svg,.acdc-row-menu-toggle svg,.acdc-row-menu-button svg,.acdc-table-action-trigger svg,[data-acdc-prospect-menu-toggle] svg,.acdc-prospect-menu-toggle svg,.acdc-bpf-action-button svg{width:var(--acdc-action-icon-glyph-size)!important;height:var(--acdc-action-icon-glyph-size)!important;display:block!important;}';

  if ( 'filled' === $icon_style ) {
    $css .= '.acdc-actions svg,.acdc-action-icon svg,.acdc-icon-link svg,.acdc-filter-toggle-icons-only svg,.acdc-user-menu-link svg,.acdc-back-button svg,[aria-label="Actions"] svg,[aria-label="Filtres"] svg,[aria-label="Options"] svg,.acdc-table td a svg,.acdc-table td button svg,.acdc-table td [aria-hidden="true"] svg,.acdc-actions svg *,.acdc-action-icon svg *,.acdc-icon-link svg *,.acdc-filter-toggle-icons-only svg *,.acdc-user-menu-link svg *,.acdc-back-button svg *,[aria-label="Actions"] svg *,[aria-label="Filtres"] svg *,[aria-label="Options"] svg *,.acdc-table td a svg *,.acdc-table td button svg *,.acdc-table td [aria-hidden="true"] svg *{fill:currentColor!important;stroke:none!important;}';
  } else {
    $css .= '.acdc-actions svg,.acdc-action-icon svg,.acdc-icon-link svg,.acdc-filter-toggle-icons-only svg,.acdc-user-menu-link svg,.acdc-back-button svg,[aria-label="Actions"] svg,[aria-label="Filtres"] svg,[aria-label="Options"] svg,.acdc-table td a svg,.acdc-table td button svg,.acdc-table td [aria-hidden="true"] svg,.acdc-actions svg *,.acdc-action-icon svg *,.acdc-icon-link svg *,.acdc-filter-toggle-icons-only svg *,.acdc-user-menu-link svg *,.acdc-back-button svg *,[aria-label="Actions"] svg *,[aria-label="Filtres"] svg *,[aria-label="Options"] svg *,.acdc-table td a svg *,.acdc-table td button svg *,.acdc-table td [aria-hidden="true"] svg *{fill:none!important;stroke:currentColor!important;}';
  }
  if ( 'outline' === $menu_dots_style ) {
    $css .= '.acdc-row-menu-toggle svg,.acdc-row-menu-toggle svg *,[data-acdc-row-menu-toggle] svg,[data-acdc-row-menu-toggle] svg *,[data-acdc-bpf-row-menu-toggle] svg,[data-acdc-bpf-row-menu-toggle] svg *{fill:none!important;stroke:currentColor!important;}';
  } else {
    $css .= '.acdc-row-menu-toggle svg,.acdc-row-menu-toggle svg *,[data-acdc-row-menu-toggle] svg,[data-acdc-row-menu-toggle] svg *,[data-acdc-bpf-row-menu-toggle] svg,[data-acdc-bpf-row-menu-toggle] svg *{fill:currentColor!important;stroke:none!important;}';
  }

  if ( ! empty( $o['custom_css'] ) ) {
    $css .= "\n" . (string) $o['custom_css'];
  }

  $scoped_css = is_admin()
    ? ( ! empty( $o['admin_custom_css'] ) ? (string) $o['admin_custom_css'] : '' )
    : ( ! empty( $o['portal_custom_css'] ) ? (string) $o['portal_custom_css'] : '' );

  if ( '' !== trim( $scoped_css ) ) {
    $css .= "\n" . trim( $scoped_css );
  }

  // ACDC 3.25.50 — Liens globaux : couleur gold dans le contenu principal uniquement (pas le menu)
  $css .= '
.acdc-portal-content a:not(.acdc-button):not(.acdc-row-action-icon):not(.acdc-tab):not([class*="acdc-prospect"]):not([class*="acdc-3dots"]),
.acdc-admin-wrap a:not(.acdc-button):not(.acdc-row-action-icon):not(.nav-tab):not(.page-title-action) {
  color: #c6a252 !important;
  font-size: inherit;
  text-decoration: none;
}
.acdc-portal-content a:not(.acdc-button):not(.acdc-row-action-icon):not(.acdc-tab):hover,
.acdc-admin-wrap a:not(.acdc-button):not(.acdc-row-action-icon):not(.nav-tab):hover {
  color: #a8893d !important;
  text-decoration: underline;
}';

  return $css;
}

public function register_admin_menu() {
  add_menu_page( 'ACDC Formation SAAS Organisme de formation', 'ACDC Formation SAAS', 'manage_options', 'acdc-of-dashboard', array( $this, 'render_admin_control_center_page' ), 'dashicons-screenoptions', 58 );
  add_submenu_page( 'acdc-of-dashboard', "Centre d'administration", "Centre d'administration", 'manage_options', 'acdc-of-dashboard', array( $this, 'render_admin_control_center_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'UI / Design système', 'UI / Design système', 'manage_options', 'acdc-of-ui-system', array( $this, 'render_admin_ui_system_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Variables CSS', 'Variables CSS', 'manage_options', 'acdc-of-ui-variables', array( $this, 'render_admin_ui_variables_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Réglages organisme', 'Réglages organisme', 'manage_options', 'acdc-of-configuration', array( $this, 'render_admin_configuration_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Données & maintenance', 'Données & maintenance', 'manage_options', 'acdc-of-maintenance', array( $this, 'render_admin_maintenance_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Recueil des besoins', 'Recueil des besoins', 'manage_options', 'acdc-of-needs', array( $this, 'render_admin_needs_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Calendrier prospects', 'Calendrier prospects', 'manage_options', 'acdc-of-calendar', array( $this, 'render_admin_calendar_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Calendrier des séances', 'Calendrier des séances', 'manage_options', 'acdc-of-sessions-calendar', array( $this, 'render_admin_sessions_calendar_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Rdv préalables', 'Rdv préalables', 'manage_options', 'acdc-of-pre-meetings', array( $this, 'render_admin_pre_meetings_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Séances en cours de validation', 'Séances en cours de validation', 'manage_options', 'acdc-of-sessions-pending', array( $this, 'render_admin_sessions_pending_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Séances validées', 'Séances validées', 'manage_options', 'acdc-of-sessions-validated', array( $this, 'render_admin_sessions_validated_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Prospects', 'Prospects', 'manage_options', 'acdc-of-prospects', array( $this, 'render_admin_prospects_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Suivi commercial', 'Suivi commercial', 'manage_options', 'acdc-of-prospect-followup', array( $this, 'render_admin_prospect_followup_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Apprenants', 'Apprenants', 'manage_options', 'acdc-of-learners', array( $this, 'render_admin_learners_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Formations', 'Formations', 'manage_options', 'acdc-of-formations', array( $this, 'render_admin_formations_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Groupes', 'Groupes', 'manage_options', 'acdc-of-groups', array( $this, 'render_admin_groups_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Entreprises', 'Entreprises', 'manage_options', 'acdc-of-companies', array( $this, 'render_admin_companies_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Financeurs', 'Financeurs', 'manage_options', 'acdc-of-funders', array( $this, 'render_admin_funders_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Formateurs', 'Formateurs', 'manage_options', 'acdc-of-trainers', array( $this, 'render_admin_trainers_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Quiz', 'Quiz', 'manage_options', 'acdc-of-quiz', array( $this, 'render_admin_quiz_page' ) );
  /* ACDC 3.25.278 — Entrée de menu retirée : elle portait le même nom que
     celle du module quiz, pour un module dont l'envoi n'a jamais fonctionné. */
  add_submenu_page( 'acdc-of-dashboard', 'Analyses du besoin', 'Analyses du besoin', 'manage_options', 'acdc-of-need-analyses', array( $this, 'render_admin_need_analyses_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Dossiers de formation', 'Dossiers de formation', 'manage_options', 'acdc-of-training-files', array( $this, 'render_admin_training_files_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Convention/Contrat', 'Convention/Contrat', 'manage_options', 'acdc-of-registration-contract', array( $this, 'render_admin_registration_contract_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Inscrire en formation', 'Inscrire en formation', 'manage_options', 'acdc-of-register-training', array( $this, 'render_admin_register_training_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Enquêtes intermédiaires', 'Enquêtes intermédiaires', 'manage_options', 'acdc-of-mid-surveys', array( $this, 'render_admin_mid_surveys_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Enquêtes à chaud', 'Enquêtes à chaud', 'manage_options', 'acdc-of-hot-surveys', array( $this, 'render_admin_hot_surveys_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Registre des réclamations', 'Registre des réclamations', 'manage_options', 'acdc-of-complaints', array( $this, 'render_admin_complaints_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Évaluations des acquis', 'Évaluations des acquis', 'manage_options', 'acdc-of-evaluations', array( $this, 'render_admin_evaluations_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Sessions de questionnaires', 'Sessions de questionnaires', 'manage_options', 'acdc-of-questionnaire-sessions', array( $this, 'render_admin_questionnaire_sessions_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Résultats des sessions', 'Résultats des sessions', 'manage_options', 'acdc-of-questionnaire-results', array( $this, 'render_admin_questionnaire_results_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Paramètres questionnaires', 'Paramètres questionnaires', 'manage_options', 'acdc-of-questionnaire-settings', array( $this, 'render_admin_questionnaire_settings_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Enquêtes à froid', 'Enquêtes à froid', 'manage_options', 'acdc-of-cold-surveys', array( $this, 'render_admin_cold_surveys_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Enquêtes formateurs', 'Enquêtes formateurs', 'manage_options', 'acdc-of-trainer-surveys', array( $this, 'render_admin_trainer_surveys_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Enquêtes entreprises', 'Enquêtes entreprises', 'manage_options', 'acdc-of-company-surveys', array( $this, 'render_admin_company_surveys_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Enquêtes financeurs', 'Enquêtes financeurs', 'manage_options', 'acdc-of-funder-surveys', array( $this, 'render_admin_funder_surveys_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Utilisateurs', 'Utilisateurs', 'manage_options', 'acdc-of-users', array( $this, 'render_admin_users_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Contacts', 'Contacts', 'manage_options', 'acdc-of-contacts', array( $this, 'render_admin_contacts_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Documents', 'Documents', 'manage_options', 'acdc-of-documents', array( $this, 'render_admin_documents_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Extranet apprenant', 'Extranet apprenant', 'manage_options', 'acdc-of-learner-portal', array( $this, 'render_admin_learner_portal_page' ) );
  add_submenu_page( null, 'Paramètres OF', 'Paramètres OF', 'manage_options', 'acdc-of-settings', array( $this, 'render_admin_settings_page' ) );
  add_submenu_page( 'acdc-of-dashboard', 'Exploration contrôlée', 'Exploration contrôlée', 'manage_options', 'acdc-of-agent-audit', array( $this, 'render_admin_agent_audit_page' ) );
  /* ACDC 3.23.19 — Page admin dédiée Veille IA (tab=watch_ia). */
  add_submenu_page( null, 'Veille IA', 'Veille IA', 'manage_options', 'acdc-of-watch-ia', array( $this, 'render_admin_watch_ia_page' ) );

  foreach ( array(
    'acdc-of-needs','acdc-of-calendar','acdc-of-sessions-calendar','acdc-of-pre-meetings','acdc-of-sessions-pending','acdc-of-sessions-validated','acdc-of-prospects','acdc-of-prospect-followup','acdc-of-learners','acdc-of-formations','acdc-of-groups','acdc-of-companies','acdc-of-funders','acdc-of-trainers','acdc-of-quiz','acdc-of-need-analyses','acdc-of-training-files','acdc-of-registration-contract','acdc-of-register-training','acdc-of-mid-surveys','acdc-of-hot-surveys','acdc-of-evaluations','acdc-of-questionnaire-sessions','acdc-of-questionnaire-results','acdc-of-questionnaire-settings','acdc-of-cold-surveys','acdc-of-trainer-surveys','acdc-of-company-surveys','acdc-of-funder-surveys','acdc-of-users','acdc-of-contacts','acdc-of-documents','acdc-of-learner-portal','acdc-of-settings'
  ) as $hidden_slug ) {
    remove_submenu_page( 'acdc-of-dashboard', $hidden_slug );
  }
}private function sanitize_datetime_input( $value ) {
  $value = sanitize_text_field( $value );
  if ( empty( $value ) ) {
    return null;
  }
  $value = $this->acdc_normalize_fr_datetime( $value );
  $timestamp = strtotime( $value );
  /* ACDC — Fuseau : conserver l'heure locale « murale » telle que saisie (cohérent avec
     l'affichage mysql2date). Sans cela, un −2 h était appliqué au stockage (17:00 → 15:00). */
  return $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : null;
}private function format_pdf_date( $value, $with_time = false ) {
  if ( empty( $value ) ) {
    return 'Non renseigné';
  }
  $timestamp = strtotime( (string) $value );
  if ( ! $timestamp ) {
    return (string) $value;
  }
  return $with_time ? date_i18n( 'd/m/Y H:i', $timestamp ) : date_i18n( 'd/m/Y', $timestamp );
}private function normalize_pdf_text( $text ) {
  $text = is_scalar( $text ) ? (string) $text : '';
  $text = wp_strip_all_tags( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) );
  $text = preg_replace( '/\s+/', ' ', $text );
  $text = str_replace( array( '’', '“', '”', '–', '—', '•' ), array( "'", '"', '"', '-', '-', '-' ), $text );
  return trim( $text );
}private function pdf_encode_text( $text ) {
  // Sécurité : supprimer tout marqueur de justification résiduel [[TW:...]] avant encodage
  if ( is_string( $text ) && 0 === strpos( $text, '[[TW:' ) ) {
    $tw_end = strpos( $text, ']]' );
    if ( false !== $tw_end ) {
      $text = substr( $text, $tw_end + 2 );
    }
  }
  $text = $this->normalize_pdf_text( $text );
  if ( '' === $text ) {
    return '';
  }
  if ( function_exists( 'iconv' ) ) {
    $converted = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text );
    if ( false !== $converted ) {
      $text = $converted;
    }
  }
  $text = str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $text );
  return $text;
}private function pdf_wrap_text( $text, $max_chars = 100 ) {
  $text = $this->normalize_pdf_text( $text );
  if ( '' === $text ) {
    return array();
  }
  $wrapped = wordwrap( $text, max( 20, (int) $max_chars ), "
", true );
  return array_values( array_filter( array_map( 'trim', explode( "
", $wrapped ) ), static function( $line ) { return '' !== $line; } ) );
}private function get_acdc_internal_pdf_asset_urls() {
  return array(
    'cachet_signature_url' => home_url( '/wp-content/uploads/2026/04/Cachet-et-signature.png' ),
    'cachet_url'           => home_url( '/wp-content/uploads/2026/04/Cachet-ACDC-Formation.png' ),
    'signature_url'        => home_url( '/wp-content/uploads/2026/04/Signature-David-Contal-scaled.png' ),
    'logo_url'             => home_url( '/wp-content/uploads/2026/03/cropped-Logo-ACDC-1.png' ),
  );
}

/**
 * ACDC 3.25.232 — LE LOGO MANQUAIT SUR LE PDF, PAS SUR L'E-MAIL.
 *
 * Le même document sortait avec l'en-tête complet dans le corps du message et
 * sans logo dans la pièce jointe. La cause n'était pas le rendu mais la
 * SOURCE : chaque générateur de PDF portait sa propre adresse de repli, écrite
 * en dur, et celle-ci désignait un fichier qui n'existe plus dans la
 * médiathèque. Le préparateur d'image ne lit que des fichiers locaux : chemin
 * introuvable, il rend null, et l'en-tête bascule silencieusement sur sa
 * variante sans logo. Aucun message, aucune trace — l'absence de logo est
 * exactement le genre de défaut qu'on ne voit que sur le document fini.
 *
 * Une seule résolution, en cascade, pour tous les PDF : le logo saisi dans le
 * profil de l'organisme, puis le fichier connu de la médiathèque, puis le logo
 * du site WordPress. On ne rend une adresse que si le fichier existe vraiment.
 *
 * @return string URL du logo, ou chaîne vide si aucun fichier n'est lisible.
 */
private function acdc_resolve_pdf_logo_url() {
  static $resolved = null;
  if ( null !== $resolved ) {
    return $resolved;
  }

  $candidates = array();

  $profile = $this->get_company_profile_options();
  if ( ! empty( $profile['logo_url'] ) ) {
    $candidates[] = (string) $profile['logo_url'];
  }

  $assets = $this->get_acdc_internal_pdf_asset_urls();
  if ( ! empty( $assets['logo_url'] ) ) {
    $candidates[] = (string) $assets['logo_url'];
  }

  /* Le logo du site, quand l'organisme en a défini un dans WordPress. */
  $custom_logo_id = (int) get_theme_mod( 'custom_logo' );
  if ( $custom_logo_id > 0 ) {
    $custom_logo = wp_get_attachment_image_url( $custom_logo_id, 'full' );
    if ( $custom_logo ) {
      $candidates[] = (string) $custom_logo;
    }
  }

  foreach ( $candidates as $candidate ) {
    if ( $this->acdc_pdf_asset_is_readable( $candidate ) ) {
      $resolved = $candidate;
      return $resolved;
    }
  }

  $resolved = '';
  return $resolved;
}

/** Le fichier derrière cette adresse existe-t-il vraiment sur le disque ? */
private function acdc_pdf_asset_is_readable( $url ) {
  $url = is_scalar( $url ) ? trim( (string) $url ) : '';
  if ( '' === $url ) {
    return false;
  }

  $uploads = wp_get_upload_dir();
  $path    = '';

  if ( ! empty( $uploads['baseurl'] ) && 0 === strpos( $url, $uploads['baseurl'] ) && ! empty( $uploads['basedir'] ) ) {
    $relative  = (string) wp_parse_url( $url, PHP_URL_PATH );
    $base_path = (string) wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
    if ( '' !== $base_path && 0 === strpos( $relative, $base_path ) ) {
      $relative = substr( $relative, strlen( $base_path ) );
    }
    $path = trailingslashit( $uploads['basedir'] ) . preg_replace( '#^[\/]+#', '', (string) $relative );
  } elseif ( 0 === strpos( $url, home_url( '/' ) ) ) {
    $relative = preg_replace( '#^[\/]+#', '', (string) wp_parse_url( $url, PHP_URL_PATH ) );
    $path     = trailingslashit( ABSPATH ) . $relative;
  }

  return ( '' !== $path && file_exists( $path ) && is_readable( $path ) );
}
  /**
   * ACDC 3.25.254 — LA CHARTE DES DOCUMENTS, ÉCRITE UNE SEULE FOIS.
   *
   * Le contrat formateur, la convention et la convocation dessinaient chacun
   * leur en-tête et leur pied. Ils avaient divergé au point que la convention
   * n'a « plus rien de la charte » : boîtes blanches contre panneaux bleutés,
   * texte noir pur contre encre, titre en minuscules contre capitales, et une
   * convocation à bandeau beige qui ne ressemble à aucun des deux.
   *
   * Pire, le pied de la convention était écrit EN DUR : changer l'adresse ou le
   * SIRET dans les Réglages ne changeait pas la convention. Un document
   * contractuel qui affiche une identité périmée n'est pas un défaut de style.
   *
   * Ces quatre méthodes sont désormais la seule source de la charte. Le contrat
   * formateur reste la référence : ce sont ses mesures, reprises à l'identique.
   *
   * @return array Palette de la charte.
   */
  private function acdc_pdf_charte_colors() {
    return array(
      'navy'  => '#0C2D52',
      'gold'  => '#C5A253',
      'ink'   => '#1f2937',
      'muted' => '#6b7280',
      'line'  => '#d7dde6',
      'panel' => '#f8fafc',
      'band'  => '#eef2f8',
      'soft'  => '#f0f4fa',
    );
  }

  /** Géométrie commune : A4 portrait et marges du contrat formateur. */
  private function acdc_pdf_charte_metrics() {
    return array( 'page_w' => 595, 'page_h' => 842, 'left' => 35.43, 'right' => 35.43 );
  }

  /**
   * L'identité de l'organisme, lue dans les Réglages — jamais écrite en dur.
   */
  private function acdc_pdf_charte_org() {
    /* ACDC 3.25.290 — La charte de l'organisme lisait cinq champs correctement
       et deux de travers : « nda_number » et « website », qui n'existent pas
       dans la fiche. La charte partait donc sans numéro de déclaration
       d'activité et sans site, quoi qu'on saisisse dans les réglages. */
    $__id = $this->acdc_org_identity();
    return array(
      'name'    => $__id['raison_sociale'],
      'address' => \ACDC\Support\OrgIdentity::addressLine( $__id, ', ' ),
      'siret'   => $__id['siret'],
      'nda'     => $__id['nda'],
      'phone'   => $__id['telephone'],
      'email'   => '' !== $__id['email'] ? $__id['email'] : (string) get_option( 'admin_email' ),
      'site'    => \ACDC\Support\OrgIdentity::siteAffiche( $__id ),
      'city'    => $__id['ville'],
    );
  }

  /**
   * L'en-tête : logo, raison sociale, baseline dorée, identité à droite, filet.
   *
   * @param string $title    Titre du document — imprimé en capitales.
   * @param string $subtitle Sous-titre facultatif.
   * @return array Lignes de la page, page_meta compris.
   */
  private function acdc_pdf_charte_header( $title, $subtitle = '' ) {
    $c = $this->acdc_pdf_charte_colors();
    $m = $this->acdc_pdf_charte_metrics();
    $org = $this->acdc_pdf_charte_org();

    $page = array( array( 'type' => 'page_meta', 'width' => $m['page_w'], 'height' => $m['page_h'] ) );
    $page[] = array( 'type' => 'rect', 'x' => $m['left'], 'y' => 754, 'width' => $m['page_w'] - $m['left'] - $m['right'], 'height' => 1.2, 'fill_color' => $c['gold'] );

    $logo = $this->prepare_pdf_jpeg_image( $this->acdc_resolve_pdf_logo_url(), 48, 48 );
    if ( $logo ) {
      $page[] = array(
        'type' => 'image', 'image_key' => $logo['key'], 'image_data' => $logo['data'],
        'image_width' => $logo['width'], 'image_height' => $logo['height'],
        'display_width' => $logo['display_width'], 'display_height' => $logo['display_height'],
        'x' => $m['left'], 'y' => 782,
      );
    }
    $page[] = array( 'text' => $this->acdc_org_identity()['raison_sociale'], 'x' => $m['left'] + 62, 'y' => 806, 'size' => 13.6, 'font' => 'Helvetica-Bold', 'color' => $c['navy'] );
    $page[] = array( 'text' => 'Azur - Compétences - Développement - Conseils', 'x' => $m['left'] + 62, 'y' => 792, 'size' => 8.4, 'font' => 'Helvetica', 'color' => $c['gold'] );

    $company_x = $m['page_w'] - $m['right'] - 130;
    $company_lines = array_values( array_filter( array(
      $org['name'],
      $org['siret'] ? 'Siret : ' . $org['siret'] : '',
      $org['nda'] ? 'NDA : ' . $org['nda'] : '',
    ) ) );
    $cy = 806;
    foreach ( $company_lines as $idx => $lt ) {
      $page[] = array( 'text' => $lt, 'x' => $company_x, 'y' => $cy, 'size' => 8, 'font' => 0 === $idx ? 'Helvetica-Bold' : 'Helvetica', 'color' => '#374151' );
      $cy -= 10;
    }

    $page[] = array( 'text' => $this->acdc_pdf_charte_upper( $title ), 'x' => $m['left'], 'y' => 736, 'size' => 15.6, 'font' => 'Helvetica-Bold', 'color' => $c['navy'] );
    if ( '' !== trim( (string) $subtitle ) ) {
      $page[] = array( 'text' => (string) $subtitle, 'x' => $m['left'], 'y' => 718, 'size' => 8.5, 'font' => 'Helvetica', 'color' => $c['muted'] );
    }
    return $page;
  }

  /**
   * Capitales sans casse-tête d'accents : le moteur PDF écrit en WinAnsi, et
   * strtoupper() laisserait « é » intact au milieu d'un titre en capitales.
   */
  private function acdc_pdf_charte_upper( $text ) {
    $text = (string) $text;
    return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $text, 'UTF-8' ) : strtoupper( $text );
  }

  /** Le pied de page : filet et deux lignes centrées, lues dans les Réglages. */
  private function acdc_pdf_charte_footer( &$page ) {
    $c = $this->acdc_pdf_charte_colors();
    $m = $this->acdc_pdf_charte_metrics();
    $org = $this->acdc_pdf_charte_org();

    $page[] = array( 'type' => 'rect', 'x' => $m['left'] - 5, 'y' => 30, 'width' => $m['page_w'] - 2 * ( $m['left'] - 5 ), 'height' => 1, 'fill_color' => $c['line'] );

    $line1 = implode( ' - ', array_values( array_filter( array(
      $org['address'],
      $org['siret'] ? 'Siret : ' . $org['siret'] : '',
      $org['nda'] ? 'NDA : ' . $org['nda'] : '',
    ) ) ) );
    $line2 = implode( ' - ', array_values( array_filter( array(
      $org['email'] ? 'e-mail : ' . $org['email'] : '',
      $org['phone'] ? 'Tél : ' . $org['phone'] : '',
      $org['site'] ? 'site web : ' . $org['site'] : '',
    ) ) ) );

    if ( '' !== $line1 ) {
      $page[] = array( 'text' => $line1, 'x' => 0, 'y' => 21, 'size' => 6.8, 'font' => 'Helvetica', 'color' => '#4b5563', 'center' => true, 'page_w' => $m['page_w'] );
    }
    if ( '' !== $line2 ) {
      $page[] = array( 'text' => $line2, 'x' => 0, 'y' => 12, 'size' => 6.8, 'font' => 'Helvetica', 'color' => '#4b5563', 'center' => true, 'page_w' => $m['page_w'] );
    }
  }

  /**
   * LE CACHET ET LA SIGNATURE, AUX BONNES PROPORTIONS.
   *
   * Le contrat formateur les plafonnait séparément :
   *
   *     $w = min( 280, largeur * 1.5 );    // 255
   *     $h = min( 160, hauteur * 1.5 );    // 191 → rabaissé à 160
   *
   * Deux plafonds indépendants écrasent l'image dès que l'un des deux mord :
   * un cachet de 1600 × 1200 sortait aplati de 16 %. On calcule désormais UN
   * SEUL rapport de réduction, appliqué aux deux dimensions — la seule façon
   * de garantir qu'un cachet reste rond.
   *
   * @return array|null Ligne image prête à poser, ou null si aucun cachet.
   */
  /**
   * ACDC 3.25.260 — UN SEUL RAPPORT DE RÉDUCTION, POUR TOUTE IMAGE.
   *
   * Deux plafonds indépendants déforment : dès que l'un mord et pas l'autre,
   * l'image est écrasée. La 3.25.254 l'avait corrigé sur le cachet de
   * l'organisme ; la faute survivait sur les signatures manuscrites des
   * signataires — la convention livrait une signature de 894 × 480 dessinée en
   * 180 × 60, soit 38 % d'écrasement en hauteur. Le calcul n'a désormais
   * qu'un seul endroit où être vrai, et un balayage refuse qu'on le refasse
   * ailleurs.
   *
   * On part TOUJOURS des dimensions natives : mettre à l'échelle une taille
   * déjà mise à l'échelle cumule deux arrondis.
   *
   * @return array{0:float,1:float} Largeur et hauteur d'affichage.
   */
  private function acdc_pdf_scaled_size( $width, $height, $max_w, $max_h ) {
    $width  = (float) $width;
    $height = (float) $height;
    if ( $width <= 0 || $height <= 0 ) {
      return array( max( 1.0, (float) $max_w ), max( 1.0, (float) $max_h ) );
    }
    /* Le 1 empêche l'agrandissement : une petite image étirée devient floue. */
    $ratio = min( (float) $max_w / $width, (float) $max_h / $height, 1 );
    return array(
      max( 1.0, round( $width * $ratio, 2 ) ),
      max( 1.0, round( $height * $ratio, 2 ) ),
    );
  }

  /**
   * ACDC 3.25.260 — LA TAILLE DES IMAGES DE SIGNATURE, UNE SEULE FOIS.
   *
   * Le même cachet sortait en 390 × 255 sur la convention, 255 × 191 sur le
   * contrat formateur et 170 × 128 ailleurs : trois tailles pour une seule
   * charte. Sur la convention, cela donnait un cachet de 12 × 9 cm — le tiers
   * de la page.
   *
   * @param string $kind 'stamp' (cachet + signature de l'organisme) ou 'signature'.
   * @return array{0:float,1:float} Plafonds en points PDF.
   */
  private function acdc_pdf_signature_box( $kind = 'stamp' ) {
    /* 170 × 128 pt = 6 × 4,5 cm ; 150 × 55 pt = 5,3 × 1,9 cm. */
    return ( 'signature' === $kind ) ? array( 150.0, 55.0 ) : array( 170.0, 128.0 );
  }

  private function acdc_pdf_charte_stamp( $x, $y, $max_w = null, $max_h = null ) {
    if ( null === $max_w || null === $max_h ) {
      list( $max_w, $max_h ) = $this->acdc_pdf_signature_box( 'stamp' );
    }
    $profile = $this->get_company_profile_options();
    $candidates = array_values( array_filter( array(
      (string) ( $profile['stamp_url'] ?? '' ),
      (string) ( $profile['signature_url'] ?? '' ),
      (string) ( $profile['stamp_only_url'] ?? '' ),
    ) ) );
    if ( empty( $candidates ) ) {
      return null;
    }

    $image = null;
    foreach ( $candidates as $candidate ) {
      /* On demande l'image dans sa taille native : la mise à l'échelle se fait
         ici, en une seule fois, pour ne pas cumuler deux arrondis. */
      $image = $this->prepare_pdf_jpeg_image( $candidate, 4000, 4000 );
      if ( $image ) {
        break;
      }
    }
    if ( ! $image || empty( $image['width'] ) || empty( $image['height'] ) ) {
      return null;
    }

    list( $display_w, $display_h ) = $this->acdc_pdf_scaled_size( $image['width'], $image['height'], $max_w, $max_h );
    return array(
      'type'           => 'image',
      'image_key'      => $image['key'],
      'image_data'     => $image['data'],
      'image_width'    => $image['width'],
      'image_height'   => $image['height'],
      'display_width'  => $display_w,
      'display_height' => $display_h,
      'x'              => $x,
      'y'              => $y,
    );
  }

  private function prepare_pdf_jpeg_image( $url, $max_width = 150, $max_height = 55 ) {
  $url = is_scalar( $url ) ? trim( (string) $url ) : '';
  if ( '' === $url || ! function_exists( 'imagecreatefromstring' ) ) {
    return null;
  }

  $raw = '';
  $path = '';
  $uploads = wp_get_upload_dir();
  if ( ! empty( $uploads['baseurl'] ) && 0 === strpos( $url, $uploads['baseurl'] ) && ! empty( $uploads['basedir'] ) ) {
    $relative = (string) wp_parse_url( $url, PHP_URL_PATH );
    $base_path = (string) wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
    if ( '' !== $base_path && 0 === strpos( $relative, $base_path ) ) {
      $relative = substr( $relative, strlen( $base_path ) );
    }
    $relative = preg_replace( '#^[\/]+#', '', (string) $relative );
    $path = trailingslashit( $uploads['basedir'] ) . $relative;
  } elseif ( 0 === strpos( $url, home_url( '/' ) ) ) {
    $relative = (string) wp_parse_url( $url, PHP_URL_PATH );
    $relative = preg_replace( '#^[\/]+#', '', (string) $relative );
    $path = trailingslashit( ABSPATH ) . $relative;
  }

  if ( $path && file_exists( $path ) && is_readable( $path ) ) {
    $raw = file_get_contents( $path );
  } else {
    return null;
  }

  if ( empty( $raw ) ) {
    return null;
  }

  $image = @imagecreatefromstring( $raw );
  if ( ! $image ) {
    return null;
  }

  $width = imagesx( $image );
  $height = imagesy( $image );
  if ( ! $width || ! $height ) {
    imagedestroy( $image );
    return null;
  }

  $ratio = min( $max_width / $width, $max_height / $height, 1 );
  $display_width = max( 1, (int) round( $width * $ratio ) );
  $display_height = max( 1, (int) round( $height * $ratio ) );

  $flattened = imagecreatetruecolor( $width, $height );
  if ( ! $flattened ) {
    imagedestroy( $image );
    return null;
  }
  $white = imagecolorallocate( $flattened, 255, 255, 255 );
  imagefilledrectangle( $flattened, 0, 0, $width, $height, $white );
  imagealphablending( $flattened, true );
  imagesavealpha( $flattened, false );
  imagecopy( $flattened, $image, 0, 0, 0, 0, $width, $height );

  ob_start();
  imageinterlace( $flattened, true );
  imagejpeg( $flattened, null, 92 );
  $jpeg = ob_get_clean();
  imagedestroy( $flattened );
  imagedestroy( $image );

  if ( ! $jpeg ) {
    return null;
  }

  return array(
    'key' => md5( $jpeg ),
    'data' => $jpeg,
    'width' => $width,
    'height' => $height,
    'display_width' => $display_width,
    'display_height' => $display_height,
  );
}private function pdf_hex_to_rgb( $color ) {
  $color = is_scalar( $color ) ? trim( (string) $color ) : '';
  if ( '' === $color ) {
    return array( 0, 0, 0 );
  }
  $color = ltrim( $color, '#' );
  if ( 3 === strlen( $color ) ) {
    $color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
  }
  if ( 6 !== strlen( $color ) ) {
    return array( 0, 0, 0 );
  }
  return array(
    round( hexdec( substr( $color, 0, 2 ) ) / 255, 4 ),
    round( hexdec( substr( $color, 2, 2 ) ) / 255, 4 ),
    round( hexdec( substr( $color, 4, 2 ) ) / 255, 4 ),
  );
}private function build_blank_attendance_pdf_pages( $learner_count = 1 ) {
  $learner_count = max( 1, min( 60, absint( $learner_count ) ) );
  $profile = $this->get_company_profile_options();
  $branding = $this->get_branding_options();
  $org_name = ! empty( $profile['enterprise'] ) ? $profile['enterprise'] : ( ! empty( $branding['company_name'] ) ? $branding['company_name'] : 'ACDC Formation' );
  $org_address = trim( implode( ' ', array_filter( array( ! empty( $profile['address'] ) ? $profile['address'] : $branding['address'], ! empty( $profile['postal_code'] ) ? $profile['postal_code'] : $branding['postal_code'], ! empty( $profile['city'] ) ? $profile['city'] : $branding['city'] ) ) ) );
  $org_phone = ! empty( $profile['enterprise_contact_phone'] ) ? $profile['enterprise_contact_phone'] : $branding['phone'];
  $org_email = ! empty( $profile['enterprise_contact_email'] ) ? $profile['enterprise_contact_email'] : $branding['email'];
  $org_siret = ! empty( $profile['siret_identification'] ) ? $profile['siret_identification'] : $branding['siret'];
  $website = ! empty( $profile['website_url'] ) ? preg_replace( '#^https?://#', '', $profile['website_url'] ) : preg_replace( '#^https?://#', '', home_url( '/' ) );
  $header_bg = ! empty( $profile['header_footer_bg'] ) ? $profile['header_footer_bg'] : '#F3E3BF';
  $header_text = ! empty( $profile['header_footer_text'] ) ? $profile['header_footer_text'] : '#1E4777';
  $trainer_bg = '#0C4A72';
  $learner_bg = '#CFEBDD';
  $border = '#D9E1EC';
  $muted = '#1E4777';
  $title_color = '#0C2D52';
  $logo = $this->prepare_pdf_jpeg_image( $this->acdc_resolve_pdf_logo_url(), 42, 42 );
  $footer_logo = ! empty( $profile['footer_logo_url'] ) ? $this->prepare_pdf_jpeg_image( $profile['footer_logo_url'], 120, 40 ) : null;

  $rows_per_page = 8;
  $pages = array();
  $remaining = $learner_count;
  $page_index = 0;

  while ( $remaining > 0 ) {
    $page_index++;
    $rows_this_page = min( $rows_per_page, $remaining );
    $remaining -= $rows_this_page;
    $elements = array();

    $elements[] = array( 'type' => 'rect', 'x' => 0, 'y' => 760, 'width' => 595, 'height' => 82, 'fill_color' => $header_bg );
    $elements[] = array( 'type' => 'rect', 'x' => 42, 'y' => 705, 'width' => 511, 'height' => 48, 'stroke_color' => $border, 'line_width' => 1 );
    $elements[] = array( 'type' => 'rect', 'x' => 42, 'y' => 646, 'width' => 511, 'height' => 46, 'stroke_color' => $border, 'line_width' => 1 );

    if ( $logo ) {
      $elements[] = array(
        'type' => 'image',
        'image_key' => $logo['key'],
        'image_data' => $logo['data'],
        'image_width' => $logo['width'],
        'image_height' => $logo['height'],
        'display_width' => $logo['display_width'],
        'display_height' => $logo['display_height'],
        'x' => 50,
        'y' => 783,
      );
    }

    $elements[] = array( 'text' => strtoupper( $org_name ), 'x' => 105, 'y' => 812, 'size' => 16, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    if ( '' !== $org_address ) {
      $elements[] = array( 'text' => $org_address, 'x' => 105, 'y' => 796, 'size' => 8.5, 'font' => 'Helvetica', 'color' => $header_text );
    }
    $contact_line = trim( implode( ' - ', array_filter( array( $org_phone, $org_email ) ) ) );
    if ( '' !== $contact_line ) {
      $elements[] = array( 'text' => $contact_line, 'x' => 105, 'y' => 782, 'size' => 8.5, 'font' => 'Helvetica', 'color' => $header_text );
    }
    $elements[] = array( 'text' => "FEUILLE D'EMARGEMENT", 'x' => 400, 'y' => 808, 'size' => 8.5, 'font' => 'Helvetica-Bold', 'color' => $header_text );
    $elements[] = array( 'text' => 'Séance de formation', 'x' => 392, 'y' => 790, 'size' => 16, 'font' => 'Helvetica-Bold', 'color' => $title_color );

    $elements[] = array( 'text' => 'INTITULÉ DE LA SÉANCE', 'x' => 235, 'y' => 741, 'size' => 8.5, 'font' => 'Helvetica-Bold', 'color' => $muted );

    $label_y = 667;
    $field_w = 108;
    $field_gap = 10;
    $fields_x = array( 52, 52 + $field_w + $field_gap, 52 + 2 * ( $field_w + $field_gap ), 52 + 3 * ( $field_w + $field_gap ) );
    $field_labels = array( 'DATE DU COURS', 'HORAIRES', 'DURÉE', 'LIEU DE LA SÉANCE' );
    foreach ( $fields_x as $idx => $x ) {
      $elements[] = array( 'type' => 'rect', 'x' => $x, 'y' => 654, 'width' => $field_w, 'height' => 22, 'fill_color' => '#F5F7FA', 'stroke_color' => $border, 'line_width' => 1 );
      $elements[] = array( 'text' => $field_labels[ $idx ], 'x' => $x + 10, 'y' => $label_y, 'size' => 7.8, 'font' => 'Helvetica-Bold', 'color' => $muted );
    }

    $table_top = 622;
    $elements[] = array( 'type' => 'rect', 'x' => 42, 'y' => $table_top - 18, 'width' => 511, 'height' => 18, 'fill_color' => '#F6F8FB', 'stroke_color' => $border, 'line_width' => 1 );
    $elements[] = array( 'text' => 'APPRENANT(S)', 'x' => 54, 'y' => $table_top - 6, 'size' => 7.8, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    $elements[] = array( 'type' => 'rect', 'x' => 42, 'y' => $table_top - 36, 'width' => 511, 'height' => 18, 'fill_color' => $learner_bg, 'stroke_color' => $border, 'line_width' => 1 );
    $elements[] = array( 'text' => "NOM DE L'APPRENANT", 'x' => 58, 'y' => $table_top - 24, 'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    $elements[] = array( 'text' => 'STATUT', 'x' => 360, 'y' => $table_top - 24, 'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    $elements[] = array( 'text' => 'SIGNATURE', 'x' => 445, 'y' => $table_top - 24, 'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => $title_color );

    $row_height = 36;
    $base_row_y = $table_top - 72;
    for ( $i = 0; $i < $rows_this_page; $i++ ) {
      $row_y = $base_row_y - ( $i * $row_height );
      $elements[] = array( 'type' => 'rect', 'x' => 42, 'y' => $row_y, 'width' => 511, 'height' => $row_height, 'stroke_color' => $border, 'line_width' => 1 );
      $elements[] = array( 'type' => 'rect', 'x' => 430, 'y' => $row_y + 5, 'width' => 94, 'height' => 26, 'stroke_color' => $border, 'line_width' => 1 );
      $elements[] = array( 'text' => sprintf( '%02d.', ( ( $page_index - 1 ) * $rows_per_page ) + $i + 1 ), 'x' => 52, 'y' => $row_y + 13, 'size' => 9, 'font' => 'Helvetica', 'color' => $muted );
    }

    $trainer_top = $base_row_y - ( $rows_this_page * $row_height ) - 28;
    $elements[] = array( 'type' => 'rect', 'x' => 42, 'y' => $trainer_top - 18, 'width' => 511, 'height' => 18, 'fill_color' => '#F6F8FB', 'stroke_color' => $border, 'line_width' => 1 );
    $elements[] = array( 'text' => 'FORMATEUR(S)', 'x' => 54, 'y' => $trainer_top - 6, 'size' => 7.8, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    $elements[] = array( 'type' => 'rect', 'x' => 42, 'y' => $trainer_top - 36, 'width' => 511, 'height' => 18, 'fill_color' => $trainer_bg, 'stroke_color' => $border, 'line_width' => 1 );
    $elements[] = array( 'text' => 'NOM DU FORMATEUR', 'x' => 58, 'y' => $trainer_top - 24, 'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => '#FFFFFF' );
    $elements[] = array( 'text' => 'SIGNATURE', 'x' => 445, 'y' => $trainer_top - 24, 'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => '#FFFFFF' );
    $elements[] = array( 'type' => 'rect', 'x' => 42, 'y' => $trainer_top - 72, 'width' => 511, 'height' => 36, 'stroke_color' => $border, 'line_width' => 1 );
    $elements[] = array( 'type' => 'rect', 'x' => 430, 'y' => $trainer_top - 67, 'width' => 94, 'height' => 26, 'stroke_color' => $border, 'line_width' => 1 );

    if ( $footer_logo ) {
      $elements[] = array(
        'type' => 'image',
        'image_key' => $footer_logo['key'],
        'image_data' => $footer_logo['data'],
        'image_width' => $footer_logo['width'],
        'image_height' => $footer_logo['height'],
        'display_width' => $footer_logo['display_width'],
        'display_height' => $footer_logo['display_height'],
        'x' => 235,
        'y' => 34,
      );
    } else {
      $elements[] = array( 'text' => strtoupper( $org_name ), 'x' => 220, 'y' => 60, 'size' => 11.5, 'font' => 'Helvetica-Bold', 'color' => $title_color );
    }
    $footer_line = trim( implode( ' - ', array_filter( array( $org_phone, $org_email, $website ) ) ) );
    if ( '' !== $footer_line ) {
      $elements[] = array( 'text' => $footer_line, 'x' => 150, 'y' => 30, 'size' => 8, 'font' => 'Helvetica', 'color' => $muted );
    }
    if ( '' !== $org_siret ) {
      $elements[] = array( 'text' => "SIRET / N° d'identification : " . $org_siret, 'x' => 188, 'y' => 16, 'size' => 6.8, 'font' => 'Helvetica', 'color' => '#8C96A8' );
    }

    $pages[] = $elements;
  }

  return $pages;
}  private function parse_question_choices( $question ) {
    $type = isset( $question['type'] ) ? (string) $question['type'] : '';
    $raw  = isset( $question['options'] ) ? (string) $question['options'] : '';
    $choices = array();
    if ( 'Question ouverte' === $type ) {
      return $choices;
    }
    if ( 'Notation' === $type ) {
      for ( $i = 1; $i <= 5; $i++ ) {
        $choices[] = array( 'value' => (string) $i, 'label' => (string) $i, 'is_correct' => false );
      }
      return $choices;
    }
    if ( 'Vrai / Faux' === $type ) {
      return array(
        array( 'value' => 'Vrai', 'label' => 'Vrai', 'is_correct' => false ),
        array( 'value' => 'Faux', 'label' => 'Faux', 'is_correct' => false ),
      );
    }
    $lines = preg_split( '/\r\n|\r|\n/', $raw );
    foreach ( (array) $lines as $line ) {
      $line = trim( (string) $line );
      if ( '' === $line ) { continue; }
      $is_correct = false;
      if ( 0 === strpos( $line, '*' ) ) {
        $is_correct = true;
        $line = ltrim( substr( $line, 1 ) );
      }
      $choices[] = array( 'value' => $line, 'label' => $line, 'is_correct' => $is_correct );
    }
    return $choices;
  }public function maybe_redirect_legacy_pages() {
  if ( ! is_page() ) {
    return;
  }
  $page_id = get_queried_object_id();
  $legacy_login = (int) get_option( 'acdc_of_login_page_id', 0 );
  $legacy_portal = (int) get_option( 'acdc_of_portal_page_id', 0 );
  if ( $page_id && $page_id === $legacy_login ) {
    wp_safe_redirect( $this->login_page_url() );
    exit;
  }
  if ( $page_id && $page_id === $legacy_portal ) {
    wp_safe_redirect( $this->portal_page_url() );
    exit;
  }
}


  private function get_plugin_data_purge_confirmation_phrase() {
    return 'SUPPRIMER TOUT';
  }

  private function get_plugin_data_purge_table_names() {
    global $wpdb;

    $tables = array(
      $this->company_table,
      $this->contact_table,
      $this->document_table,
      $this->formation_table,
      $this->session_table,
      $this->learner_table,
      $this->need_table,
      $this->prospect_table,
      $this->group_table,
      $this->funder_table,
      $this->trainer_table,
      $this->quiz_table,
      $this->evaluation_table,
      $this->need_analysis_table,
      $this->registration_contract_table,
      $this->training_registration_table,
      $this->prospect_rdv_table,
      $this->pre_meeting_table,
      $this->questionnaire_session_table,
      $this->questionnaire_participant_table,
      $this->questionnaire_answer_table,
      $this->questionnaire_model_table,
      $this->questionnaire_action_table,
      $this->questionnaire_log_table,
    );

    /* ACDC 3.25.192 — Émargement, quiz, signature et parcours entrent dans la
       purge. Ils en étaient absents — sauf la signature — et survivaient donc
       aux séances qu'ils documentent, se raccrochant aux identifiants réutilisés
       par les enregistrements suivants. Ils sont désormais couverts par la
       sauvegarde de sécurité prise juste avant la purge : on ne détruit rien
       dont on n'ait d'abord une copie. */
    $tables = array_merge( $tables, array_values( $this->acdc_satellite_table_map() ) );

    $tables = array_filter( array_unique( array_map( 'strval', $tables ) ) );
    return array_values( $tables );
  }

  private function collect_plugin_generated_file_paths() {
    global $wpdb;

    $paths = array();

    $document_rows = $wpdb->get_results( "SELECT file_path, file_url FROM {$this->document_table}", ARRAY_A );
    if ( is_array( $document_rows ) ) {
      foreach ( $document_rows as $row ) {
        if ( ! empty( $row['file_path'] ) ) {
          $paths[] = (string) $row['file_path'];
        } elseif ( ! empty( $row['file_url'] ) ) {
          $paths[] = $this->get_local_path_from_upload_url( (string) $row['file_url'] );
        }
      }
    }

    $registration_columns = array(
      'convocation_document_path',
      'positioning_result_document_path',
      'diagnostic_result_document_path',
      'mid_survey_document_path',
      'hot_survey_document_path',
      'cold_survey_document_path',
      'evaluation_result_document_path',
      'completion_certificate_document_path',
      'end_training_certificate_document_path',
    );
    $registration_sql = 'SELECT ' . implode( ',', $registration_columns ) . " FROM {$this->training_registration_table}";
    $registration_rows = $wpdb->get_results( $registration_sql, ARRAY_A );
    if ( is_array( $registration_rows ) ) {
      foreach ( $registration_rows as $row ) {
        foreach ( $registration_columns as $column ) {
          if ( ! empty( $row[ $column ] ) ) {
            $paths[] = (string) $row[ $column ];
          }
        }
      }
    }

    $signature_table = $wpdb->prefix . 'acdc_sig_requests';
    $signature_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $signature_table ) );
    if ( $signature_exists === $signature_table ) {
      $signature_rows = $wpdb->get_results( "SELECT doc_path, signed_doc_path, audit_pdf_path FROM {$signature_table}", ARRAY_A );
      if ( is_array( $signature_rows ) ) {
        foreach ( $signature_rows as $row ) {
          foreach ( array( 'doc_path', 'signed_doc_path', 'audit_pdf_path' ) as $column ) {
            if ( ! empty( $row[ $column ] ) ) {
              $paths[] = (string) $row[ $column ];
            }
          }
        }
      }
    }

    $paths = array_filter( array_unique( array_map( 'strval', $paths ) ) );
    return array_values( $paths );
  }

  private function delete_plugin_generated_files( $paths ) {
    if ( empty( $paths ) || ! is_array( $paths ) ) {
      return 0;
    }

    $deleted = 0;
    foreach ( $paths as $path ) {
      $path = trim( (string) $path );
      if ( '' === $path ) {
        continue;
      }
      if ( file_exists( $path ) && is_file( $path ) && is_writable( $path ) ) {
        if ( @unlink( $path ) ) {
          $deleted++;
        }
      }
    }

    return $deleted;
  }

  // ── ACDC 3.23.4 — Score de complétude dossier de formation ───────────────

  /**
   * ACDC 3.25.249 — LA BARRE DIT ENFIN OÙ EN EST LE DOSSIER.
   *
   * Trois défauts la rendaient trompeuse.
   *
   * 1. ELLE MENTAIT SUR L'ÉMARGEMENT. La pastille « Émargement » lisait le
   *    document de l'ENQUÊTE À MI-PARCOURS — une autre pièce, un autre moment.
   *    Elle annonçait donc une chose et en vérifiait une autre. L'assiduité se
   *    lit maintenant là où elle existe : les feuilles signées. Et elle n'est
   *    verte que lorsque TOUTES les demi-journées de cet apprenant sont
   *    réglées, comme David l'a tranché — vert à la première signature serait
   *    plus flatteur et faux devant un financeur.
   *
   * 2. IL LUI MANQUAIT LA MOITIÉ DU PARCOURS. Ouverture de l'extranet,
   *    évaluation diagnostique, évaluation des acquis, certificat de formation
   *    et enquête à froid n'y figuraient pas, alors que chacune a sa colonne en
   *    base depuis longtemps. Le pourcentage se calculait sur un parcours
   *    tronqué, donc il était faux.
   *
   * 3. LE TEST DE POSITIONNEMENT PLOMBAIT LES DOSSIERS QUI N'EN ONT PAS.
   *    Il ne compte désormais que si la formation l'exige : sinon la pastille
   *    DISPARAÎT, du tableau comme du calcul. Une exigence qui ne s'applique
   *    pas ne doit pas peser sur un score.
   *
   * L'ordre est celui du parcours réel, tel que David l'a dicté.
   */
  private function compute_registration_completude_score( $registration ) {
    $doc = static function ( $registration, $field ) {
      return ! empty( $registration->{$field . '_url'} ) || ! empty( $registration->{$field . '_path'} );
    };

    $criteria = array(
      array( 'label' => 'Apprenant / groupe', 'ok' => ! empty( $registration->learner_id ) || ! empty( $registration->group_id ), 'points' => 10 ),
      array( 'label' => 'Formation',          'ok' => ! empty( $registration->formation_id ),                                    'points' => 10 ),
      array( 'label' => 'Convention',         'ok' => ! empty( $registration->autofill_contract_id ),                            'points' => 10 ),
      array( 'label' => 'Convocation',        'ok' => $doc( $registration, 'convocation_document' ),                             'points' => 10 ),
      array( 'label' => 'Ouverture intranet', 'ok' => $this->acdc_registration_portal_is_active( $registration ),                'points' => 5 ),
    );

    /* Le positionnement n'apparaît que s'il est exigé par la formation. */
    if ( $this->acdc_registration_positioning_required( $registration ) ) {
      $criteria[] = array( 'label' => 'Test de positionnement', 'ok' => $doc( $registration, 'positioning_result_document' ), 'points' => 10 );
    }

    $criteria[] = array( 'label' => 'Émargement',               'ok' => $this->acdc_registration_attendance_complete( $registration ), 'points' => 10 );
    $criteria[] = array( 'label' => 'Évaluation diagnostique',  'ok' => $doc( $registration, 'mid_survey_document' ),                  'points' => 5 );
    $criteria[] = array( 'label' => 'Évaluation des acquis',    'ok' => $doc( $registration, 'evaluation_result_document' ),           'points' => 10 );
    $criteria[] = array( 'label' => 'Enquête à chaud',          'ok' => $doc( $registration, 'hot_survey_document' ),                  'points' => 10 );
    /* ACDC 3.25.264 — CES DEUX LIGNES ÉTAIENT INVERSÉES.
       « Attestation de formation » cochait la présence du CERTIFICAT DE
       RÉALISATION, et « Certificat de formation » celle de l'ATTESTATION —
       et aucun de ces deux libellés n'est un nom légal. Deux documents
       différents, deux destinataires différents (le financeur, l'apprenant),
       deux valeurs de points différentes : le score de complétude était
       attribué au mauvais document, sur l'écran qu'on regarde tous les jours.
       Ce sont désormais les noms que la loi leur donne. */
    $criteria[] = array( 'label' => 'Certificat de réalisation',       'ok' => $doc( $registration, 'completion_certificate_document' ),   'points' => 10 );
    $criteria[] = array( 'label' => 'Attestation de fin de formation', 'ok' => $doc( $registration, 'end_training_certificate_document' ), 'points' => 5 );
    $criteria[] = array( 'label' => 'Enquête à froid',          'ok' => $doc( $registration, 'cold_survey_document' ),                 'points' => 5 );

    $total  = 0;
    $earned = 0;
    foreach ( $criteria as $c ) {
      $total += $c['points'];
      if ( $c['ok'] ) {
        $earned += $c['points'];
      }
    }
    return array(
      'percent'  => $total > 0 ? (int) round( $earned / $total * 100 ) : 0,
      'earned'   => $earned,
      'total'    => $total,
      'criteria' => $criteria,
    );
  }

  /**
   * L'extranet de l'apprenant est-il RÉELLEMENT ouvert ?
   *
   * On lit l'état du compte, pas l'envoi de l'e-mail d'activation : un courrier
   * parti n'est pas un accès ouvert. C'est la distinction que David a demandée,
   * et c'est la même que partout ailleurs dans ce plugin entre « envoyé » et
   * « fait ».
   */
  /**
   * ACDC 3.25.265 — CETTE PASTILLE NE POUVAIT PAS DEVENIR VERTE.
   *
   * Elle interrogeait « WHERE learner_id = … » sur la table des comptes
   * extranet. Cette colonne n'existe pas : elle s'appelle primary_learner_id.
   * La requête échouait en silence, rendait null, et « Ouverture intranet »
   * restait grise pour TOUS les dossiers, quel que soit l'état réel du compte.
   *
   * Et le rattachement de fond n'est pas l'apprenant mais l'ADRESSE : un
   * compte extranet sert toutes les inscriptions d'une même personne. On
   * cherche donc par e-mail, avec le rattachement par identifiant en repli
   * pour les comptes créés avant que l'e-mail ne fasse foi.
   *
   * Deux statuts valent « ouvert » : « active », et « password_to_change » —
   * l'accès est ouvert, l'apprenant n'a simplement pas encore choisi son mot
   * de passe. Le refuser reviendrait à dire que l'ouverture n'a pas eu lieu.
   */
  private function acdc_registration_portal_is_active( $registration ) {
    if ( empty( $this->learner_portal_account_table ) ) {
      return false;
    }
    $learner_id = (int) ( $registration->learner_id ?? 0 );
    if ( $learner_id <= 0 ) {
      return false;
    }
    global $wpdb;

    $email = (string) $wpdb->get_var( $wpdb->prepare(
      "SELECT email FROM {$this->learner_table} WHERE id = %d LIMIT 1",
      $learner_id
    ) );

    $status = '';
    if ( '' !== trim( $email ) ) {
      $status = (string) $wpdb->get_var( $wpdb->prepare(
        "SELECT status FROM {$this->learner_portal_account_table} WHERE email = %s ORDER BY id DESC LIMIT 1",
        $email
      ) );
    }
    if ( '' === $status ) {
      $status = (string) $wpdb->get_var( $wpdb->prepare(
        "SELECT status FROM {$this->learner_portal_account_table} WHERE primary_learner_id = %d ORDER BY id DESC LIMIT 1",
        $learner_id
      ) );
    }

    return in_array( $status, array( 'active', 'password_to_change' ), true );
  }

  /** La formation de ce dossier exige-t-elle un test de positionnement ? */
  private function acdc_registration_positioning_required( $registration ) {
    $formation_id = (int) ( $registration->formation_id ?? 0 );
    if ( $formation_id <= 0 || ! method_exists( $this, 'get_formation' ) ) {
      return false;
    }
    $formation = $this->get_formation( $formation_id );
    return ( $formation && ! empty( $formation->positioning_test_enabled ) );
  }

  /**
   * Toutes les demi-journées de cet apprenant sont-elles réglées ?
   *
   * On compte les feuilles où il est attendu, et celles qui portent une
   * signature — ou une absence déclarée. Une absence est un fait établi : le
   * dossier est traité, c'est l'assiduité qui ne l'est pas. Deux questions
   * différentes, et cette barre répond à la première.
   *
   * Sans aucune feuille, la réponse est NON : il n'y a rien à prouver, donc
   * rien de prouvé. Rendre vrai un état vide, c'est exactement ce que fait un
   * écran qui affirme sans avoir lu.
   */
  private function acdc_registration_attendance_complete( $registration ) {
    $learner_id = (int) ( $registration->learner_id ?? 0 );
    if ( $learner_id <= 0 ) {
      return false;
    }
    global $wpdb;
    $table = $wpdb->prefix . 'acdc_of_emarg_learners';
    $row   = $wpdb->get_row( $wpdb->prepare(
      "SELECT COUNT(*) AS total,
              SUM( CASE WHEN ( signed_at IS NOT NULL AND signed_at <> '' ) OR is_absent = 1 THEN 1 ELSE 0 END ) AS settled
         FROM {$table} WHERE learner_id = %d",
      $learner_id
    ) );
    if ( ! $row || (int) $row->total <= 0 ) {
      return false;
    }
    return ( (int) $row->settled >= (int) $row->total );
  }

  // ── ACDC 3.23.2 — Workflow statut dossier de formation ────────────────────

  private function get_workflow_status_list() {
    return array(
      'prospect_cree', 'recueil_en_cours', 'proposition_a_rediger', 'proposition_envoyee',
      'devis_envoye', 'en_attente_signature', 'confirme', 'en_attente_financement',
      'analyse_besoin_en_cours', 'preparation_en_cours', 'convocations_envoyees',
      'en_cours_de_formation', 'formation_realisee', 'documents_finaux_a_envoyer',
      'cloture_administratif', 'cloture_qualite',
    );
  }

  private function get_workflow_status_labels() {
    return array(
      /* ACDC 3.25.249 — « Prospect créé » est un contresens sur un apprenant
         inscrit : la liste des statuts est partagée avec le cycle de vie du
         dossier commercial, où le terme a un sens. Sur la liste des apprenants,
         il désigne en réalité l'état « le dossier existe, rien n'est encore
         parti ». On le nomme donc pour ce qu'il est. */
      'prospect_cree'              => 'Dossier créé',
      'recueil_en_cours'           => 'Recueil en cours',
      'proposition_a_rediger'      => 'Proposition à rédiger',
      'proposition_envoyee'        => 'Proposition envoyée',
      'devis_envoye'               => 'Devis envoyé',
      'en_attente_signature'       => 'En attente signature',
      'confirme'                   => 'Confirmé',
      'en_attente_financement'     => 'En attente financement',
      'analyse_besoin_en_cours'    => 'Analyse du besoin',
      'preparation_en_cours'       => 'Préparation en cours',
      'convocations_envoyees'      => 'Convocations envoyées',
      'en_cours_de_formation'      => 'En cours de formation',
      'formation_realisee'         => 'Formation réalisée',
      'documents_finaux_a_envoyer' => 'Documents à envoyer',
      'cloture_administratif'      => 'Clôture administrative',
      'cloture_qualite'            => 'Clôture qualité',
    );
  }

  private function get_workflow_status_colors() {
    return array(
      'prospect_cree'              => '#6b7280',
      'recueil_en_cours'           => '#6b7280',
      'proposition_a_rediger'      => '#1e4777',
      'proposition_envoyee'        => '#1e4777',
      'devis_envoye'               => '#1e4777',
      'en_attente_signature'       => '#f0b45e',
      'en_attente_financement'     => '#f0b45e',
      'confirme'                   => '#35b37e',
      'analyse_besoin_en_cours'    => '#35b37e',
      'preparation_en_cours'       => '#35b37e',
      'convocations_envoyees'      => '#35b37e',
      'en_cours_de_formation'      => '#35b37e',
      'formation_realisee'         => '#1a7a50',
      'documents_finaux_a_envoyer' => '#1a7a50',
      'cloture_administratif'      => '#0f2c52',
      'cloture_qualite'            => '#0f2c52',
    );
  }

  private function get_workflow_status_index( $status ) {
    $list = $this->get_workflow_status_list();
    $idx = array_search( (string) $status, $list, true );
    return false === $idx ? -1 : (int) $idx;
  }

  private function advance_registration_workflow( $registration_id, $new_status ) {
    $registration_id = absint( $registration_id );
    if ( ! $registration_id ) {
      return;
    }
    $valid = $this->get_workflow_status_list();
    if ( ! in_array( $new_status, $valid, true ) ) {
      return;
    }
    global $wpdb;
    $exists = $wpdb->get_var( "SHOW COLUMNS FROM {$this->training_registration_table} LIKE 'workflow_status'" );
    if ( ! $exists ) {
      return;
    }
    $current = $wpdb->get_var( $wpdb->prepare( "SELECT workflow_status FROM {$this->training_registration_table} WHERE id = %d", $registration_id ) );
    $current_idx = $this->get_workflow_status_index( $current ?: 'prospect_cree' );
    $new_idx     = $this->get_workflow_status_index( $new_status );
    if ( $new_idx > $current_idx ) {
      $wpdb->update( $this->training_registration_table, array( 'workflow_status' => $new_status, 'updated_at' => $this->now_mysql() ), array( 'id' => $registration_id ) );
    }
  }

  private function find_registration_id_for_contract( $contract_id ) {
    $contract_id = absint( $contract_id );
    if ( ! $contract_id ) {
      return 0;
    }
    global $wpdb;
    $id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->training_registration_table} WHERE autofill_contract_id = %d AND is_draft = 0 ORDER BY id ASC LIMIT 1", $contract_id ) );
    return $id ? (int) $id : 0;
  }

  /* -----------------------------------------------------------------------
   * ACDC 3.24.11 — Bilans compétences formateurs (indicateur 21 Qualiopi).
   * ----------------------------------------------------------------------- */

  /**
   * Retourne les bilans d'un formateur triés du plus récent au plus ancien.
   */
  private function get_trainer_evaluations( $trainer_id ) {
    global $wpdb;
    $trainer_id = absint( $trainer_id );
    if ( ! $trainer_id ) { return array(); }
    return (array) $wpdb->get_results( $wpdb->prepare(
      "SELECT * FROM {$this->trainer_evaluation_table} WHERE trainer_id = %d ORDER BY evaluation_date DESC, id DESC",
      $trainer_id
    ) );
  }

  /**
   * Statut conformité ind. 21 pour un formateur.
   * 'green' = bilan < 12 mois | 'orange' = bilan > 12 mois | 'red' = jamais.
   */
  private function get_trainer_evaluation_status( $trainer_id ) {
    global $wpdb;
    $trainer_id = absint( $trainer_id );
    $last_date  = $wpdb->get_var( $wpdb->prepare(
      "SELECT evaluation_date FROM {$this->trainer_evaluation_table} WHERE trainer_id = %d ORDER BY evaluation_date DESC LIMIT 1",
      $trainer_id
    ) );
    if ( ! $last_date ) { return 'red'; }
    $days = (int) floor( ( strtotime( current_time( 'Y-m-d' ) ) - strtotime( $last_date ) ) / DAY_IN_SECONDS );
    return ( $days <= 365 ) ? 'green' : 'orange';
  }

  /**
   * Formateurs sans bilan récent (> 12 mois ou jamais). Hub qualité ind. 21.
   */
  private function get_trainers_without_recent_evaluation() {
    global $wpdb;
    $cutoff = wp_date( 'Y-m-d', strtotime( '-12 months', current_time( 'timestamp' ) ) );
    $rows = $wpdb->get_results(
      "SELECT t.id, t.first_name, t.last_name, MAX(e.evaluation_date) AS last_eval_date" .
      " FROM {$this->trainer_table} AS t" .
      " LEFT JOIN {$this->trainer_evaluation_table} AS e ON e.trainer_id = t.id" .
      " GROUP BY t.id" .
      " HAVING last_eval_date IS NULL OR last_eval_date < '" . esc_sql( $cutoff ) . "'" .
      " ORDER BY t.last_name ASC, t.first_name ASC"
    );
    return $rows ? (array) $rows : array();
  }

  /* ACDC 3.24.43 — Variables disponibles pour l'éditeur de mail direct selon l'entité */
  protected function get_direct_email_variables( $entity_type ) {
    if ( ! function_exists( 'acdc_of_get_variables_registry' ) ) { return array(); }
    $registry = acdc_of_get_variables_registry();

    // Mapping entité → groupes de variables à exposer
    $groups_map = array(
      'prospect' => array( 'prospect', 'formation', 'session' ),
      'learner'  => array( 'apprenant', 'formation', 'session' ),
      'trainer'  => array( 'formateur' ),
      'company'  => array( 'entreprise', 'formation', 'session' ),
      'funder'   => array( 'financeur' ),
    );
    $wanted = isset( $groups_map[ $entity_type ] ) ? $groups_map[ $entity_type ] : array();

    $result = array();
    foreach ( $wanted as $group_key ) {
      if ( empty( $registry[ $group_key ] ) ) { continue; }
      $group = $registry[ $group_key ];
      $vars  = array();
      foreach ( $group['variables'] as $key => $def ) {
        $vars[] = array(
          'key'   => $key,
          'label' => isset( $def['label'] ) ? $def['label'] : $key,
        );
      }
      if ( ! empty( $vars ) ) {
        $result[] = array(
          'label'     => isset( $group['label'] ) ? $group['label'] : $group_key,
          'variables' => $vars,
        );
      }
    }
    return $result;
  }
  /* ACDC 3.24.43 — Construit le contexte de résolution des variables pour un e-mail direct */
  protected function build_direct_email_context( $entity_type, $entity_id ) {
    global $wpdb;
    $context = array();
    switch ( $entity_type ) {
      case 'prospect':
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->prospect_table} WHERE id = %d", $entity_id ) );
        if ( $row ) { $context['prospect'] = (array) $row; }
        break;
      case 'learner':
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->learner_table} WHERE id = %d", $entity_id ) );
        if ( $row ) {
          $context['apprenant'] = (array) $row;
          // Session + formation liées
          if ( ! empty( $row->session_id ) ) {
            $session = $wpdb->get_row( $wpdb->prepare( "SELECT s.*, f.title AS formation_title FROM {$this->session_table} s LEFT JOIN {$this->formation_table} f ON f.id = s.formation_id WHERE s.id = %d", (int) $row->session_id ) );
            if ( $session ) {
              $context['session']   = (array) $session;
              $context['formation'] = array( 'title' => $session->formation_title );
            }
          }
        }
        break;
      case 'trainer':
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->trainer_table} WHERE id = %d", $entity_id ) );
        if ( $row ) { $context['formateur'] = (array) $row; }
        break;
      case 'company':
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->company_table} WHERE id = %d", $entity_id ) );
        if ( $row ) { $context['entreprise'] = (array) $row; }
        break;
      case 'funder':
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->funder_table} WHERE id = %d", $entity_id ) );
        if ( $row ) { $context['financeur'] = (array) $row; }
        break;
    }
    return $context;
  }

}
