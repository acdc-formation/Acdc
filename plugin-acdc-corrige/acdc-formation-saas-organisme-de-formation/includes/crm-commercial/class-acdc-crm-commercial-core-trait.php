<?php
    /**
     * Trait ACDC_Crm_Commercial_Core_Trait
     *
     * Sous-bloc CRM commercial extrait incrémentalement depuis class-acdc-plugin.php.
     *
     * @since 3.12.0
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    trait ACDC_Crm_Commercial_Core_Trait {

  private function admin_prospect_followup_url( $extra = array() ) {
    $args = array_merge( array( 'page' => 'acdc-of-prospect-followup' ), $extra );
    return admin_url( 'admin.php?' . http_build_query( $args ) );
  }


  private function get_prospects() {
    global $wpdb;
    return $wpdb->get_results( "SELECT * FROM {$this->prospect_table} ORDER BY created_at DESC, id DESC" );
  }


  private function prospect_has_dashboard_alert( $prospect, $latest_rdv = null ) {
    $alert_status = $this->get_prospect_alert_status( $prospect );

    return isset( $alert_status['code'] ) && 'soon' === (string) $alert_status['code'];
  }



  protected function get_prospect_alert_status( $prospect ) {
    static $cache = array();

    if ( ! is_object( $prospect ) || empty( $prospect->id ) ) {
      return array(
        'code'  => 'none',
        'label' => 'Aucune alerte',
        'tone'  => 'blue',
        'hint'  => 'Aucun rendez-vous commercial enregistré.',
      );
    }

    $prospect_id = (int) $prospect->id;
    if ( isset( $cache[ $prospect_id ] ) ) {
      return $cache[ $prospect_id ];
    }

    $rows          = $this->get_prospect_rdv_rows( $prospect_id );
    $latest_open_rdv = null;
    $now_timestamp   = current_time( 'timestamp' );

    foreach ( (array) $rows as $row ) {
      if ( ! $this->is_prospect_rdv_cancelled( $row ) ) {
        $latest_open_rdv = $row;
        break;
      }
    }

    if ( ! $latest_open_rdv && ! empty( $prospect->rdv_at ) ) {
      $latest_open_rdv = (object) array(
        'rdv_at'       => $prospect->rdv_at,
        'comment_text' => ! empty( $prospect->rdv_notes ) ? $prospect->rdv_notes : '',
        'info_html'    => '',
      );
    }

    if ( ! $latest_open_rdv ) {
      $cache[ $prospect_id ] = array(
        'code'  => 'none',
        'label' => 'Aucune alerte',
        'tone'  => 'blue',
        'hint'  => 'Aucun rendez-vous commercial actif pour ce suivi.',
      );

      return $cache[ $prospect_id ];
    }

    $latest_timestamp = ! empty( $latest_open_rdv->rdv_at ) ? strtotime( (string) $latest_open_rdv->rdv_at ) : 0;

    if ( ! $latest_timestamp || $latest_timestamp < $now_timestamp ) {
      $rdv_label = $latest_timestamp ? mysql2date( 'd/m/Y', $latest_open_rdv->rdv_at ) : '';
      $cache[ $prospect_id ] = array(
        'code'  => 'done',
        'label' => 'Terminé',
        'tone'  => 'success',
        'hint'  => $rdv_label ? 'Dernier rendez-vous : ' . $rdv_label . '.' . "\n" . 'Suivi à relancer si nécessaire.' : 'Le dernier rendez-vous est passé.',
      );

      return $cache[ $prospect_id ];
    }

    $days_remaining = (int) ceil( ( $latest_timestamp - $now_timestamp ) / DAY_IN_SECONDS );
    $rdv_label      = mysql2date( 'd/m/Y', $latest_open_rdv->rdv_at );

    $has_post_comment = ! empty( $latest_open_rdv->post_comment_text ) && '' !== trim( (string) $latest_open_rdv->post_comment_text );

    if ( $days_remaining <= 7 && ! $has_post_comment ) {
      $cache[ $prospect_id ] = array(
        'code'  => 'soon',
        'label' => 'RDV imminent',
        'tone'  => 'danger',
        'hint'  => 'Rendez-vous dans ' . $days_remaining . ' jour(s) — le ' . $rdv_label . '.',
      );
    } elseif ( $days_remaining <= 7 && $has_post_comment ) {
      $cache[ $prospect_id ] = array(
        'code'  => 'done',
        'label' => 'Terminé',
        'tone'  => 'success',
        'hint'  => 'Rendez-vous du ' . $rdv_label . ' — commentaire enregistré, alerte levée.',
      );
    } else {
      $cache[ $prospect_id ] = array(
        'code'  => 'upcoming',
        'label' => 'RDV planifié',
        'tone'  => 'warning',
        'hint'  => 'Rendez-vous dans ' . $days_remaining . ' jour(s) — le ' . $rdv_label . '.',
      );
    }

    return $cache[ $prospect_id ];
  }

  protected function get_prospect_alert_status_counts( $prospects ) {
    $counts = array(
      'none'     => 0,
      'upcoming' => 0,
      'soon'     => 0,
      'done'     => 0,
    );

    foreach ( (array) $prospects as $prospect ) {
      $status = $this->get_prospect_alert_status( $prospect );
      $code   = isset( $status['code'] ) ? (string) $status['code'] : 'none';
      if ( ! isset( $counts[ $code ] ) ) {
        $counts[ $code ] = 0;
      }
      $counts[ $code ]++;
    }

    return $counts;
  }

  protected function get_prospect_alert_status_badge_html( $prospect, $compact = false ) {
    $status = $this->get_prospect_alert_status( $prospect );
    $class  = 'acdc-alert-status-badge is-' . sanitize_html_class( (string) ( $status['code'] ?? 'none' ) );
    if ( $compact ) {
      $class .= ' is-compact';
    }

    return sprintf(
      '<span class="%1$s" title="%2$s">%3$s</span>',
      esc_attr( $class ),
      esc_attr( (string) ( $status['hint'] ?? '' ) ),
      esc_html( (string) ( $status['label'] ?? 'Aucune alerte' ) )
    );
  }


  private function get_inactive_prospect_count( $days = 10 ) {
    global $wpdb;
    $terminal   = array( 'Converti', 'Perdu' );
    $threshold  = wp_date( 'Y-m-d H:i:s', time() - ( (int) $days * DAY_IN_SECONDS ) );
    $placeholders = implode( ',', array_fill( 0, count( $terminal ), '%s' ) );
    return (int) $wpdb->get_var( $wpdb->prepare(
      "SELECT COUNT(*) FROM {$this->prospect_table}
       WHERE ( status IS NULL OR status NOT IN ( {$placeholders} ) )
       AND updated_at < %s",
      array_merge( $terminal, array( $threshold ) )
    ) );
  }

  private function get_overdue_prospect_followup_count() {
    $prospects = $this->get_prospects();

    if ( empty( $prospects ) ) {
      return 0;
    }

    $count = 0;

    foreach ( $prospects as $prospect ) {
      if ( $this->prospect_has_dashboard_alert( $prospect ) ) {
        $count++;
      }
    }

    return $count;
  }


  private function get_prospect( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->prospect_table} WHERE id = %d", $id ) );
  }


  private function get_prospect_primary_email( $prospect ) {
    if ( ! $prospect ) {
      return '';
    }
    if ( $this->is_individual_prospect_profile( $prospect->profile_type ) ) {
      return ! empty( $prospect->email ) ? sanitize_email( $prospect->email ) : '';
    }
    if ( ! empty( $prospect->signer_email ) ) {
      return sanitize_email( $prospect->signer_email );
    }
    return ! empty( $prospect->email ) ? sanitize_email( $prospect->email ) : '';
  }


  private function get_prospect_rdv_rows( $prospect_id ) {
    global $wpdb;
    return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$this->prospect_rdv_table} WHERE prospect_id = %d ORDER BY rdv_at DESC, id DESC", (int) $prospect_id ) );
  }


  private function is_prospect_rdv_cancelled( $rdv_row ) {
    return ! empty( $rdv_row ) && ! empty( $rdv_row->comment_text ) && false !== strpos( (string) $rdv_row->comment_text, '[RDV_ANNULE]' );
  }


  private function get_prospect_rdv_mode( $rdv_row ) {
    $source = ! empty( $rdv_row->comment_text ) ? strtolower( (string) $rdv_row->comment_text ) : '';
    if ( false !== strpos( $source, 'visioconférence' ) || false !== strpos( $source, 'visioconference' ) ) {
      return 'visioconference';
    }
    if ( false !== strpos( $source, 'téléphonique' ) || false !== strpos( $source, 'telephonique' ) ) {
      return 'telephonique';
    }
    if ( false !== strpos( $source, 'dans nos bureaux' ) ) {
      return 'au_bureau';
    }
    if ( false !== strpos( $source, 'dans vos bureaux' ) ) {
      return 'chez_le_client';
    }
    return '';
  }


  private function get_prospect_rdv_meeting_link( $rdv_row ) {
    $content = '';
    if ( ! empty( $rdv_row->info_html ) ) {
      $content .= ' ' . (string) $rdv_row->info_html;
    }
    if ( ! empty( $rdv_row->comment_text ) ) {
      $content .= ' ' . (string) $rdv_row->comment_text;
    }
    if ( preg_match( "~https?://[^\s\"'<>]+~i", $content, $matches ) ) {
      return esc_url_raw( $matches[0] );
    }
    return '';
  }


  private function get_prospect_rdv_comments_text( $rdv_row ) {
    if ( empty( $rdv_row ) || empty( $rdv_row->info_html ) ) {
      return '';
    }

    $content = (string) $rdv_row->info_html;
    $content = preg_replace( '~<p[^>]*>\s*<strong>Modalit[^<]*</strong>.*?</p>~is', '', $content );
    $content = wp_strip_all_tags( $content );
    $content = trim( preg_replace( '/\s+/', ' ', $content ) );

    return sanitize_text_field( $content );
  }

  private function get_prospect_rdv_post_comment_text( $rdv_row ) {
    if ( empty( $rdv_row ) || empty( $rdv_row->post_comment_text ) ) {
      return '';
    }

    return sanitize_textarea_field( (string) $rdv_row->post_comment_text );
  }


  private function get_prospect_latest_rdv( $prospect, $rdv_rows = null ) {
    if ( null === $rdv_rows ) {
      $rdv_rows = $this->get_prospect_rdv_rows( ! empty( $prospect->id ) ? (int) $prospect->id : 0 );
    }

    if ( ! empty( $rdv_rows ) ) {
      foreach ( $rdv_rows as $rdv_row ) {
        if ( ! $this->is_prospect_rdv_cancelled( $rdv_row ) ) {
          return $rdv_row;
        }
      }
    }

    if ( ! empty( $prospect->rdv_at ) ) {
      return (object) array(
        'rdv_at'         => $prospect->rdv_at,
        'timezone_label' => wp_timezone_string() ? wp_timezone_string() : 'Europe/Paris',
        'comment_text'   => ! empty( $prospect->rdv_notes ) ? $prospect->rdv_notes : '',
        'info_html'      => '',
      );
    }

    return null;
  }


  private function get_prospect_rdv_summary( $rdv_row ) {
    if ( empty( $rdv_row ) ) {
      return '';
    }

    if ( ! empty( $rdv_row->comment_text ) ) {
      return sanitize_textarea_field( (string) $rdv_row->comment_text );
    }

    if ( ! empty( $rdv_row->info_html ) ) {
      return wp_trim_words( wp_strip_all_tags( (string) $rdv_row->info_html ), 20, '…' );
    }

    return '';
  }


  private function render_prospect_rdv_detail_html( $rdv_row ) {
    if ( empty( $rdv_row ) ) {
      return 'Rendez-vous enregistré depuis le suivi commercial.';
    }

    $parts = array();
    $summary = ! empty( $rdv_row->comment_text ) ? str_replace( '[RDV_ANNULE] ', '', (string) $rdv_row->comment_text ) : '';

    if ( $summary ) {
      $summary_html = make_clickable( nl2br( esc_html( $summary ) ) );
      $parts[] = '<p><strong>Résumé du rendez-vous</strong><br>' . $summary_html . '</p>';
    }

    if ( ! empty( $rdv_row->info_html ) ) {
      $info_html = (string) $rdv_row->info_html;
      $info_html = preg_replace( '#<p[^>]*>\s*<strong>Modalité du rendez-vous\s*:</strong>.*?</p>#is', '', $info_html );
      $info_html = trim( $info_html );
      if ( '' !== wp_strip_all_tags( $info_html ) ) {
        $info_html = make_clickable( wp_kses_post( $info_html ) );
        $parts[] = '<div><strong>Compléments d’information</strong>' . wpautop( $info_html ) . '</div>';
      }
    }

    if ( $this->is_prospect_rdv_cancelled( $rdv_row ) ) {
      $parts[] = '<p><strong>Statut</strong><br>Rendez-vous annulé.</p>';
    }

    if ( empty( $parts ) ) {
      return 'Rendez-vous enregistré depuis le suivi commercial.';
    }

    return implode( '', $parts );
  }


  private function get_prospect_rdv_events( $year, $month ) {
    global $wpdb;

    $year = max( 2000, min( 2100, (int) $year ) );
    $month = max( 1, min( 12, (int) $month ) );

    $start = sprintf( '%04d-%02d-01 00:00:00', $year, $month );
    $next_month = $month === 12 ? 1 : $month + 1;
    $next_year = $month === 12 ? $year + 1 : $year;
    $end  = sprintf( '%04d-%02d-01 00:00:00', $next_year, $next_month );

    $sql = $wpdb->prepare(
      "SELECT r.*, p.id AS prospect_id, p.profile_type, p.first_name, p.last_name, p.company_name, p.desired_training, p.assigned_to FROM {$this->prospect_rdv_table} r LEFT JOIN {$this->prospect_table} p ON p.id = r.prospect_id WHERE r.rdv_at IS NOT NULL AND r.rdv_at != '' AND r.rdv_at >= %s AND r.rdv_at < %s AND ( r.comment_text IS NULL OR r.comment_text = '' OR r.comment_text NOT LIKE %s ) ORDER BY r.rdv_at ASC, p.last_name ASC, p.first_name ASC",
      $start,
      $end,
      '%[RDV_ANNULE]%'
    );

    return $wpdb->get_results( $sql );
  }


  private function get_prospect_profile_options() {
    return array(
      '' => 'Choisir une option',
      'Particulier' => 'Particulier',
      'Entreprise' => 'Entreprise',
      'Indépendant' => 'Indépendant',
      'Salarié' => 'Salarié',
    );
  }


  private function is_individual_prospect_profile( $profile ) {
    return in_array( (string) $profile, array( 'Particulier', 'Salarié' ), true );
  }


  private function is_company_prospect_profile( $profile ) {
    return in_array( (string) $profile, array( 'Entreprise', 'Indépendant', 'Entreprise / indépendant' ), true );
  }


  private function get_prospect_status_options() {
    return array(
      'À traiter'           => 'À traiter',
      'Premier contact'     => 'Premier contact',
      'À relancer'          => 'À relancer',
      'Rendez-vous planifié'=> 'Rendez-vous planifié',
      'Recueil envoyé'  => 'Recueil envoyé',
      'Proposition envoyée' => 'Proposition envoyée',
      'Devis envoyé'        => 'Devis envoyé',
      'Converti'            => 'Converti',
      'Perdu'               => 'Perdu',
    );
  }

  private function get_prospect_status_order() {
    return array(
      'À traiter'           => 1,
      'Premier contact'     => 2,
      'À relancer'          => 3,
      'Rendez-vous planifié'=> 4,
      'Recueil envoyé'  => 5,
      'Proposition envoyée' => 6,
      'Devis envoyé'        => 7,
      'Converti'            => 8,
      'Perdu'               => 9,
    );
  }

  protected function maybe_advance_prospect_status( $prospect_id, $target_status ) {
    if ( ! $prospect_id || ! $target_status ) {
      return false;
    }
    $order = $this->get_prospect_status_order();
    $target_rank = isset( $order[ $target_status ] ) ? (int) $order[ $target_status ] : 0;
    if ( ! $target_rank ) {
      return false;
    }
    // Never auto-advance to Converti or Perdu
    if ( in_array( $target_status, array( 'Converti', 'Perdu' ), true ) ) {
      return false;
    }
    $prospect = $this->get_prospect( (int) $prospect_id );
    if ( ! $prospect ) {
      return false;
    }
    $current_status = ! empty( $prospect->status ) ? (string) $prospect->status : 'À traiter';
    $current_rank   = isset( $order[ $current_status ] ) ? (int) $order[ $current_status ] : 0;
    // Block advancement if currently Converti or Perdu
    if ( in_array( $current_status, array( 'Converti', 'Perdu' ), true ) ) {
      return false;
    }
    // Only advance, never regress
    if ( $target_rank <= $current_rank ) {
      return false;
    }
    global $wpdb;
    $result = $wpdb->update(
      $this->prospect_table,
      array( 'status' => $target_status, 'updated_at' => current_time( 'mysql' ) ),
      array( 'id' => (int) $prospect_id ),
      array( '%s', '%s' ),
      array( '%d' )
    );
    return false !== $result;
  }

  protected function get_prospect_status_badge_html( $status, $compact = false ) {
    $palette = array(
      'À traiter'           => array( 'bg' => '#f0f4ff', 'color' => '#3b5bdb', 'border' => 'rgba(59,91,219,.18)' ),
      'Premier contact'     => array( 'bg' => '#f0f9ff', 'color' => '#0369a1', 'border' => 'rgba(3,105,161,.18)' ),
      'À relancer'          => array( 'bg' => '#fff7ed', 'color' => '#c2410c', 'border' => 'rgba(194,65,12,.20)' ),
      'Rendez-vous planifié'=> array( 'bg' => '#fdf4ff', 'color' => '#7e22ce', 'border' => 'rgba(126,34,206,.18)' ),
      'Recueil envoyé'  => array( 'bg' => '#fefce8', 'color' => '#854d0e', 'border' => 'rgba(133,77,14,.20)' ),
      'Proposition envoyée' => array( 'bg' => '#fff1f9', 'color' => '#9d174d', 'border' => 'rgba(157,23,77,.18)' ),
      'Devis envoyé'        => array( 'bg' => '#f0fdf4', 'color' => '#15803d', 'border' => 'rgba(21,128,61,.18)' ),
      'Converti'            => array( 'bg' => '#ecfdf5', 'color' => '#065f46', 'border' => 'rgba(6,95,70,.20)' ),
      'Perdu'               => array( 'bg' => '#fef2f2', 'color' => '#991b1b', 'border' => 'rgba(153,27,27,.18)' ),
    );
    $label   = $status ?: 'À traiter';
    $p       = isset( $palette[ $label ] ) ? $palette[ $label ] : array( 'bg' => '#f8fafc', 'color' => '#475569', 'border' => 'rgba(148,163,184,.20)' );
    $pad     = $compact ? '4px 9px' : '5px 12px';
    $fz      = $compact ? '11px' : '12px';
    return '<span style="display:inline-flex;align-items:center;padding:' . $pad . ';border-radius:999px;background:' . esc_attr( $p['bg'] ) . ';color:' . esc_attr( $p['color'] ) . ';border:1px solid ' . esc_attr( $p['border'] ) . ';font-size:' . $fz . ';font-weight:700;line-height:1.2;white-space:nowrap;">' . esc_html( $label ) . '</span>';
  }


  private function get_prospect_training_options() {
    $options = array( '' => 'Choisir une option', 'À définir' => 'À définir' );
    foreach ( $this->get_formations() as $formation ) {
      $options[ $formation->title ] = $formation->title;
    }
    return $options;
  }


  private function get_prospect_source_options() {
    return array(
      '' => 'Choisir une option',
      'Organique' => 'Organique',
      'Site internet' => 'Site internet',
      'Bouche à oreille' => 'Bouche à oreille',
      'Recommandation' => 'Recommandation',
      'Moteur de recherche' => 'Moteur de recherche',
      'LinkedIn' => 'LinkedIn',
      'Facebook' => 'Facebook',
      'Autre' => 'Autre',
    );
  }


  private function get_prospect_copy_recipients( $prospect ) {
    $rows = array();
    if ( $prospect && ! empty( $prospect->copy_recipients_json ) ) {
      $decoded = json_decode( (string) $prospect->copy_recipients_json, true );
      if ( is_array( $decoded ) ) {
        foreach ( $decoded as $email ) {
          $email = sanitize_email( $email );
          if ( $email ) {
            $rows[] = $email;
          }
        }
      }
    }
    if ( empty( $rows ) && $prospect && ! empty( $prospect->copy_recipients ) ) {
      $parts = preg_split( '/[,;]+/', (string) $prospect->copy_recipients );
      foreach ( $parts as $email ) {
        $email = sanitize_email( trim( (string) $email ) );
        if ( $email ) {
          $rows[] = $email;
        }
      }
    }
    return array_values( array_unique( array_filter( $rows ) ) );
  }


  private function get_prospect_additional_contacts( $prospect ) {
    if ( ! $prospect || empty( $prospect->additional_contacts_json ) ) {
      return array();
    }
    $decoded = json_decode( (string) $prospect->additional_contacts_json, true );
    if ( ! is_array( $decoded ) ) {
      $decoded = json_decode( (string) $prospect->additional_contacts_json );
    }
    if ( ! is_array( $decoded ) ) {
      return array();
    }
    $rows = array();
    foreach ( $decoded as $entry ) {
      if ( is_object( $entry ) ) {
        $entry = (array) $entry;
      }
      if ( ! is_array( $entry ) ) {
        continue;
      }
      $row = array(
        'last_name'          => isset( $entry['last_name'] ) ? sanitize_text_field( $entry['last_name'] ) : '',
        'first_name'         => isset( $entry['first_name'] ) ? sanitize_text_field( $entry['first_name'] ) : '',
        'job_title'          => isset( $entry['job_title'] ) ? sanitize_text_field( $entry['job_title'] ) : '',
        'email'              => isset( $entry['email'] ) ? sanitize_email( $entry['email'] ) : '',
        'phone'              => isset( $entry['phone'] ) ? sanitize_text_field( $entry['phone'] ) : '',
        'is_copy_recipient'  => ! empty( $entry['is_copy_recipient'] ) ? '1' : '0',
      );
      if ( implode( '', $row ) !== '' ) {
        $rows[] = $row;
      }
    }
    return $rows;
  }


  private function get_prospect_display_name( $prospect ) {
    if ( ! $prospect ) {
      return '—';
    }
    $profile = isset( $prospect->profile_type ) ? (string) $prospect->profile_type : '';
    if ( $this->is_individual_prospect_profile( $profile ) ) {
      $name = trim( trim( (string) $prospect->first_name ) . ' ' . trim( (string) $prospect->last_name ) );
      return '' !== $name ? $name : '—';
    }
    $contact = trim( trim( (string) $prospect->signer_first_name ) . ' ' . trim( (string) $prospect->signer_last_name ) );
    if ( '' !== $contact ) {
      return $contact;
    }
    $legacy = trim( trim( (string) $prospect->first_name ) . ' ' . trim( (string) $prospect->last_name ) );
    return '' !== $legacy ? $legacy : '—';
  }


  private function get_prospect_company_display_name( $prospect ) {
    if ( ! $prospect ) {
      return '—';
    }
    if ( ! empty( $prospect->company_name ) ) {
      return (string) $prospect->company_name;
    }
    $profile = isset( $prospect->profile_type ) ? (string) $prospect->profile_type : '';
    return $profile !== '' ? $profile : 'Particulier';
  }


  private function get_prospect_related_email_addresses( $prospect ) {
    $emails = array();

    if ( ! $prospect ) {
      return $emails;
    }

    $primary_email = $this->get_prospect_primary_email( $prospect );
    if ( $primary_email ) {
      $emails[] = strtolower( $primary_email );
    }

    if ( ! empty( $prospect->email ) ) {
      $emails[] = strtolower( sanitize_email( $prospect->email ) );
    }

    if ( ! empty( $prospect->signer_email ) ) {
      $emails[] = strtolower( sanitize_email( $prospect->signer_email ) );
    }

    foreach ( $this->get_prospect_copy_recipients( $prospect ) as $email ) {
      $emails[] = strtolower( sanitize_email( $email ) );
    }

    foreach ( $this->get_prospect_additional_contacts( $prospect ) as $contact ) {
      if ( ! empty( $contact['email'] ) ) {
        $emails[] = strtolower( sanitize_email( $contact['email'] ) );
      }
    }

    return array_values( array_unique( array_filter( $emails ) ) );
  }


  private function acdc_normalize_email_archive_match_text( $value ) {
    $value = wp_strip_all_tags( (string) $value );
    $value = remove_accents( $value );
    $value = strtolower( $value );
    $value = preg_replace( '/[^a-z0-9]+/', ' ', $value );
    return trim( preg_replace( '/\s+/', ' ', $value ) );
  }


  private function get_prospect_related_name_pairs( $prospect ) {
    $pairs = array();

    if ( ! $prospect ) {
      return $pairs;
    }

    $candidates = array(
      array(
        'first_name' => isset( $prospect->first_name ) ? $prospect->first_name : '',
        'last_name'  => isset( $prospect->last_name ) ? $prospect->last_name : '',
      ),
      array(
        'first_name' => isset( $prospect->signer_first_name ) ? $prospect->signer_first_name : '',
        'last_name'  => isset( $prospect->signer_last_name ) ? $prospect->signer_last_name : '',
      ),
    );

    foreach ( $this->get_prospect_additional_contacts( $prospect ) as $contact ) {
      $candidates[] = array(
        'first_name' => isset( $contact['first_name'] ) ? $contact['first_name'] : '',
        'last_name'  => isset( $contact['last_name'] ) ? $contact['last_name'] : '',
      );
    }

    foreach ( $candidates as $candidate ) {
      $first = $this->acdc_normalize_email_archive_match_text( isset( $candidate['first_name'] ) ? $candidate['first_name'] : '' );
      $last  = $this->acdc_normalize_email_archive_match_text( isset( $candidate['last_name'] ) ? $candidate['last_name'] : '' );
      if ( '' === $first && '' === $last ) {
        continue;
      }
      $full = trim( $first . ' ' . $last );
      $pairs[ $full ] = array(
        'first' => $first,
        'last'  => $last,
        'full'  => $full,
      );
    }

    $display_name = $this->acdc_normalize_email_archive_match_text( $this->get_prospect_display_name( $prospect ) );
    if ( '' !== $display_name && ! isset( $pairs[ $display_name ] ) ) {
      $parts = array_values( array_filter( explode( ' ', $display_name ) ) );
      $pairs[ $display_name ] = array(
        'first' => isset( $parts[0] ) ? $parts[0] : '',
        'last'  => count( $parts ) > 1 ? end( $parts ) : '',
        'full'  => $display_name,
      );
    }

    return array_values( $pairs );
  }


  private function is_marketing_archive_name_match_for_prospect( $entry, $prospect ) {
    if ( ! is_array( $entry ) || ! $prospect ) {
      return false;
    }

    $haystack_parts = array();
    foreach ( array( 'subject', 'body', 'from_name', 'source_action', 'source_module' ) as $field ) {
      if ( ! empty( $entry[ $field ] ) ) {
        $haystack_parts[] = (string) $entry[ $field ];
      }
    }

    $haystack = $this->acdc_normalize_email_archive_match_text( implode( ' ', $haystack_parts ) );
    if ( '' === $haystack ) {
      return false;
    }

    foreach ( $this->get_prospect_related_name_pairs( $prospect ) as $pair ) {
      $full  = isset( $pair['full'] ) ? $pair['full'] : '';
      $first = isset( $pair['first'] ) ? $pair['first'] : '';
      $last  = isset( $pair['last'] ) ? $pair['last'] : '';

      if ( '' !== $full && false !== strpos( $haystack, $full ) ) {
        return true;
      }

      if ( '' !== $first && '' !== $last ) {
        $first_found = preg_match( '/(^| )' . preg_quote( $first, '/' ) . '( |$)/', $haystack );
        $last_found  = preg_match( '/(^| )' . preg_quote( $last, '/' ) . '( |$)/', $haystack );
        if ( $first_found && $last_found ) {
          return true;
        }
      }
    }

    return false;
  }


  private function get_marketing_archive_prospect_email_sharing_map() {
    static $map = null;

    if ( null !== $map ) {
      return $map;
    }

    $map = array();
    foreach ( $this->get_prospects() as $candidate ) {
      if ( ! $candidate || empty( $candidate->id ) ) {
        continue;
      }
      foreach ( $this->get_prospect_related_email_addresses( $candidate ) as $email ) {
        if ( '' === $email ) {
          continue;
        }
        if ( ! isset( $map[ $email ] ) ) {
          $map[ $email ] = array();
        }
        $map[ $email ][ (int) $candidate->id ] = true;
      }
    }

    return $map;
  }


  private function is_marketing_archive_email_match_unique_for_prospect( $prospect, $entry_emails ) {
    if ( ! $prospect || empty( $prospect->id ) || empty( $entry_emails ) ) {
      return false;
    }

    $sharing_map = $this->get_marketing_archive_prospect_email_sharing_map();
    foreach ( (array) $entry_emails as $email ) {
      $email = strtolower( sanitize_email( $email ) );
      if ( '' === $email ) {
        continue;
      }
      if ( empty( $sharing_map[ $email ] ) ) {
        continue;
      }
      $prospect_ids = array_keys( (array) $sharing_map[ $email ] );
      if ( 1 === count( $prospect_ids ) && (int) $prospect->id === (int) $prospect_ids[0] ) {
        return true;
      }
    }

    return false;
  }


  private function get_marketing_archive_for_prospect( $prospect ) {
    if ( ! $prospect || empty( $prospect->id ) ) {
      return array();
    }

    $matched_rows = array();
    $emails       = $this->get_prospect_related_email_addresses( $prospect );

    foreach ( $this->get_marketing_email_archive() as $entry ) {
      $entry_id = isset( $entry['id'] ) ? (string) $entry['id'] : '';
      if ( '' === $entry_id ) {
        continue;
      }

      $is_direct_match = (
        'prospect' === ( isset( $entry['related_entity_type'] ) ? (string) $entry['related_entity_type'] : '' )
        && (int) $prospect->id === (int) ( isset( $entry['related_entity_id'] ) ? $entry['related_entity_id'] : 0 )
      );

      $entry_emails = array();
      foreach ( array( 'to', 'cc', 'bcc' ) as $field ) {
        if ( empty( $entry[ $field ] ) || ! is_array( $entry[ $field ] ) ) {
          continue;
        }
        foreach ( $entry[ $field ] as $email ) {
          $email = strtolower( sanitize_email( $email ) );
          if ( $email ) {
            $entry_emails[] = $email;
          }
        }
      }
      $entry_emails = array_values( array_unique( array_filter( $entry_emails ) ) );

      $is_email_match = ! empty( $emails ) && ! empty( array_intersect( $emails, $entry_emails ) );
      $is_name_match  = $this->is_marketing_archive_name_match_for_prospect( $entry, $prospect );
      $is_unique_email_match = $is_email_match ? $this->is_marketing_archive_email_match_unique_for_prospect( $prospect, $entry_emails ) : false;

      $is_fallback_match = false;
      if ( ! $is_direct_match ) {
        if ( $is_email_match && $is_unique_email_match ) {
          $is_fallback_match = true;
        } elseif ( $is_email_match && $is_name_match ) {
          $is_fallback_match = true;
        } elseif ( ! $is_email_match && $is_name_match ) {
          $is_fallback_match = true;
        }
      }

      if ( ! $is_direct_match && ! $is_fallback_match ) {
        continue;
      }

      if ( ! $is_direct_match ) {
        $entry['related_entity_type'] = 'prospect';
        $entry['related_entity_id']   = (int) $prospect->id;
      }

      $matched_rows[ $entry_id ] = $entry;
    }

    return array_values( $matched_rows );
  }


  private function get_prospect_related_needs( $prospect ) {
    global $wpdb;

    if ( ! $prospect ) {
      return array();
    }

    $need_ids = array();
    $rows_by_id = array();

    $select_sql = "SELECT id, source_prospect_id, collection_date, status, sent_at, next_action, priority_level, expressed_need, expected_objectives, created_at FROM {$this->need_table} WHERE %s ORDER BY created_at DESC LIMIT 10";

    if ( ! empty( $prospect->id ) ) {
      $prospect_rows = $wpdb->get_results(
        $wpdb->prepare(
          str_replace( '%s', 'source_prospect_id = %d', $select_sql ),
          (int) $prospect->id
        )
      );

      if ( is_array( $prospect_rows ) ) {
        foreach ( $prospect_rows as $row ) {
          $row_id = ! empty( $row->id ) ? (int) $row->id : 0;
          if ( $row_id && ! isset( $rows_by_id[ $row_id ] ) ) {
            $rows_by_id[ $row_id ] = $row;
            $need_ids[] = $row_id;
          }
        }
      }
    }

    if ( ! empty( $prospect->company_name ) ) {
      $company_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->company_table} WHERE name = %s ORDER BY id DESC LIMIT 1", (string) $prospect->company_name ) );
      if ( $company_id ) {
        $company_rows = $wpdb->get_results(
          $wpdb->prepare(
            str_replace( '%s', 'company_id = %d', $select_sql ),
            (int) $company_id
          )
        );

        if ( is_array( $company_rows ) ) {
          foreach ( $company_rows as $row ) {
            $row_id = ! empty( $row->id ) ? (int) $row->id : 0;
            if ( $row_id && ! isset( $rows_by_id[ $row_id ] ) ) {
              $rows_by_id[ $row_id ] = $row;
              $need_ids[] = $row_id;
            }
          }
        }
      }
    }

    if ( empty( $rows_by_id ) ) {
      return array();
    }

    usort(
      $rows_by_id,
      static function( $a, $b ) {
        $a_date = ! empty( $a->created_at ) ? strtotime( (string) $a->created_at ) : 0;
        $b_date = ! empty( $b->created_at ) ? strtotime( (string) $b->created_at ) : 0;
        if ( $a_date === $b_date ) {
          return 0;
        }
        return ( $a_date > $b_date ) ? -1 : 1;
      }
    );

    return array_slice( $rows_by_id, 0, 10 );
  }


  private function get_prospect_related_proposals( $prospect ) {
    global $wpdb;

    if ( ! $prospect || empty( $prospect->id ) ) {
      return array();
    }

    $proposal_table = $wpdb->prefix . 'acdc_of_proposals';
    $need_table     = $this->need_table;

    return $wpdb->get_results( $wpdb->prepare(
      "SELECT p.id, p.need_id, p.title, p.client_company, p.formation_title, p.status, p.created_at, p.pdf_url, p.last_sent_at
       FROM {$proposal_table} p
       INNER JOIN {$need_table} n ON n.id = p.need_id
       WHERE n.source_prospect_id = %d
       ORDER BY p.created_at DESC
       LIMIT 20",
      (int) $prospect->id
    ) );
  }

    private function get_prospect_related_contracts( $prospect ) {
    global $wpdb;
    if ( ! $prospect ) {
      return array();
    }

    if ( empty( $prospect->id ) ) {
      return array();
    }

    $rows = $wpdb->get_results( $wpdb->prepare(
      "SELECT id, title, commanditaire_type, formation_title, start_date, end_date, created_at, updated_at, document_url, document_path, signed_document_url, signed_document_path
       FROM {$this->registration_contract_table}
       WHERE source_prospect_id = %d
       ORDER BY created_at DESC
       LIMIT 10",
      (int) $prospect->id
    ) );

    return is_array( $rows ) ? $rows : array();
  }

  private function get_prospect_related_quotes( $prospect ) {
    if ( ! $prospect ) {
      return array();
    }

    $desired_training = ! empty( $prospect->desired_training ) ? trim( (string) $prospect->desired_training ) : '';
    $prospect_name    = trim( (string) $this->get_prospect_display_name( $prospect ) );
    $profile_type     = ! empty( $prospect->profile_type ) ? (string) $prospect->profile_type : '';
    $commanditaire_type = $this->is_individual_prospect_profile( $profile_type ) ? 'Particulier' : 'Entreprise';
    $rows = array();

    foreach ( (array) $this->get_mock_quotes_data( 'action' ) as $row ) {
      $matches = false;

      if ( ! empty( $row['commanditaire_type'] ) && (string) $row['commanditaire_type'] === $commanditaire_type ) {
        $matches = true;
      }

      if ( '' !== $desired_training && ! empty( $row['formation_full'] ) && false !== stripos( (string) $row['formation_full'], $desired_training ) ) {
        $matches = true;
      }

      if ( '' !== $desired_training && ! empty( $row['formation'] ) && false !== stripos( (string) $row['formation'], $desired_training ) ) {
        $matches = true;
      }

      if ( '' !== $prospect_name && ! empty( $row['commanditaire_name'] ) && 0 === strcasecmp( trim( (string) $row['commanditaire_name'] ), $prospect_name ) ) {
        $matches = true;
      }

      if ( $matches ) {
        $rows[] = (object) $row;
      }
    }

    return array_slice( $rows, 0, 10 );
  }




  private function is_prospect_ui_context() {
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    $tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
    if ( is_admin() ) {
      return 'acdc-of-prospects' === $page;
    }
    return 'prospects' === $tab;
  }



private function send_prospect_rdv_notification_email( $prospect, $appointment ) {
  $to = $this->get_prospect_primary_email( $prospect );
  if ( empty( $to ) || ! is_email( $to ) ) {
    return false;
  }

  $branding = $this->get_branding_options();
  $company_name = ! empty( $branding['company_name'] ) ? $branding['company_name'] : 'ACDC Formation';
  $prospect_name = $this->get_prospect_display_name( $prospect );
  $meeting_date = ! empty( $appointment['rdv_at'] ) ? mysql2date( 'd/m/Y', $appointment['rdv_at'] ) : 'À confirmer';
  $meeting_time = ! empty( $appointment['rdv_at'] ) ? mysql2date( 'H\hi', $appointment['rdv_at'] ) : 'À confirmer';
  $timezone = ! empty( $appointment['timezone_label'] ) ? $appointment['timezone_label'] : ( wp_timezone_string() ? wp_timezone_string() : 'Europe/Paris' );
  $details_html = ! empty( $appointment['info_html'] ) ? wpautop( wp_kses_post( $appointment['info_html'] ) ) : '<p style="font-size:19px;line-height:1.7;margin:0;">Nous vous remercions pour votre intérêt. Ce rendez-vous nous permettra d’échanger sur votre besoin et les suites possibles.</p>';
  $summary_training = ! empty( $prospect->desired_training ) ? (string) $prospect->desired_training : 'À définir';
  $summary_company = (string) $this->get_prospect_company_display_name( $prospect );
  $subject = ! empty( $appointment['email_subject'] ) ? $appointment['email_subject'] : 'Confirmation de votre rendez-vous avec ' . $company_name;

  $intro_html = '<p style="font-size:19px;line-height:1.7;margin:0 0 28px;">Votre rendez-vous a bien été enregistré. Nous reviendrons vers vous à la date prévue pour échanger sur votre besoin.</p>';
  $summary_rows = array(
    array( 'label' => 'Prospect', 'value' => $prospect_name ),
    array( 'label' => 'Profil', 'value' => $prospect->profile_type ?: '—' ),
    array( 'label' => 'Entreprise', 'value' => $summary_company ?: '—' ),
    array( 'label' => 'Formation souhaitée', 'value' => $summary_training ),
    array( 'label' => 'Date', 'value' => $meeting_date ),
    array( 'label' => 'Heure', 'value' => $meeting_time . ' — ' . $timezone ),
  );

  $headers = array(
    'source_module' => 'crm-commercial',
    'source_action' => 'prospect_rdv',
    'related_entity_type' => 'prospect',
    'related_entity_id' => (int) $prospect->id,
    'related_sub_id' => ! empty( $appointment['id'] ) ? (int) $appointment['id'] : 0,
    'email_category' => 'commercial',
    'email_audience' => 'prospect',
  );
  $copy_recipients = $this->get_prospect_copy_recipients( $prospect );
  if ( ! empty( $copy_recipients ) ) {
    $headers['extra_headers'] = array( 'Cc: ' . implode( ',', $copy_recipients ) );
  }

  return $this->acdc_send_transactional_email(
    $to,
    $subject,
    array(
      'greeting_name' => $prospect_name,
      'intro_html' => $intro_html,
      'summary_title' => 'RÉCAPITULATIF DE VOTRE RENDEZ-VOUS',
      'summary_rows' => $summary_rows,
      'body_html' => $details_html,
      'footer_notice' => 'Cet e-mail a été envoyé suite à votre demande de rendez-vous. Vos données sont traitées conformément au RGPD.',
    ),
    $headers
  );
}


private function maybe_create_learner_from_confirmed_prospect( $prospect_id, $data ) {
  return false;
}
    

private function get_archive_view_url_for_entry( $archive_id ) {
  return $this->portal_page_url( array( 'tab' => 'marketing_email_archive', 'action' => 'view', 'archive_id' => $archive_id ) );
}

}
