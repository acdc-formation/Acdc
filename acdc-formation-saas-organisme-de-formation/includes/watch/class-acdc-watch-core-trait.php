<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * ACDC Watch Core Trait
 * Collecte automatique : RSS, YouTube Data API, Google Alerts RSS, Legifrance PISTE.
 * Dédoublonnage par MD5(url). Pas de file_get_contents — wp_remote_get() uniquement.
 * PHP 7.3 compatible — pas de typed properties, pas de fn().
 */
trait ACDC_Watch_Core_Trait {

  /* -----------------------------------------------------------------------
   * C03 (audit 3.25.90) — Chiffrement au repos des clés API (OpenAI, Anthropic,
   * Gemini, Perplexity, YouTube). Objectif : un export SQL de wp_options ne révèle
   * plus les clés en clair. La clé de chiffrement est dérivée d'un secret hors base
   * applicative (constante ACDC_SECRET_KEY si définie, sinon wp_salt) — donc absente
   * d'une simple sauvegarde de la base. Saisie depuis l'interface conservée :
   * déchiffrement à l'affichage et à l'usage. Rétro-compatible : une valeur sans le
   * marqueur "acdcenc1:" est traitée comme du clair (transition douce).
   * ----------------------------------------------------------------------- */

  private function acdc_secret_cipher_key() {
    $base = defined( "ACDC_SECRET_KEY" ) ? ACDC_SECRET_KEY : wp_salt( "secure_auth" );
    return hash( "sha256", "acdc_of_watch_v1|" . (string) $base, true );
  }

  public function acdc_secret_encrypt( $plain ) {
    $plain = (string) $plain;
    if ( "" === $plain ) { return ""; }
    if ( ! function_exists( "openssl_encrypt" ) ) { return $plain; }
    $iv = function_exists( "random_bytes" ) ? random_bytes( 16 ) : openssl_random_pseudo_bytes( 16 );
    $ct = openssl_encrypt( $plain, "aes-256-cbc", $this->acdc_secret_cipher_key(), OPENSSL_RAW_DATA, $iv );
    if ( false === $ct ) { return $plain; }
    return "acdcenc1:" . base64_encode( $iv . $ct );
  }

  public function acdc_secret_decrypt( $stored ) {
    $stored = (string) $stored;
    if ( 0 !== strpos( $stored, "acdcenc1:" ) ) { return $stored; }
    if ( ! function_exists( "openssl_decrypt" ) ) { return ""; }
    $raw = base64_decode( substr( $stored, 9 ), true );
    if ( false === $raw || strlen( $raw ) < 17 ) { return ""; }
    $iv = substr( $raw, 0, 16 );
    $ct = substr( $raw, 16 );
    $pt = openssl_decrypt( $ct, "aes-256-cbc", $this->acdc_secret_cipher_key(), OPENSSL_RAW_DATA, $iv );
    return ( false === $pt ) ? "" : $pt;
  }

  public function maybe_encrypt_watch_keys() {
    if ( "1" === (string) get_option( "acdc_of_watch_keys_encrypted_v1", "" ) ) { return; }
    $opts = array( "acdc_of_watch_api_openai", "acdc_of_watch_api_anthropic", "acdc_of_watch_api_gemini", "acdc_of_watch_api_perplexity", "acdc_of_watch_api_youtube" );
    foreach ( $opts as $o ) {
      $cur = (string) get_option( $o, "" );
      if ( "" !== $cur && 0 !== strpos( $cur, "acdcenc1:" ) ) {
        update_option( $o, $this->acdc_secret_encrypt( $cur ) );
      }
    }
    update_option( "acdc_of_watch_keys_encrypted_v1", "1" );
  }

  /* -----------------------------------------------------------------------
   * Sources par défaut — indicateur 23 (légal), 24 (métiers), 25 (pédago)
   * ----------------------------------------------------------------------- */

  private function get_ia_default_watch_sources() {
    return array(

      /* ── Axe légal (ind. 23) ── */
      array( 'id' => 'rss_francecompetences',  'label' => 'France Compétences — Actualités',        'type' => 'rss',     'axis' => 'legal',      'url' => 'https://www.francecompetences.fr/feed/', 'active' => 1 ),
      array( 'id' => 'rss_travailemploi',       'label' => 'Travail-Emploi.gouv.fr',                 'type' => 'rss',     'axis' => 'legal',      'url' => 'https://travail-emploi.gouv.fr/spip.php?page=backend', 'active' => 1 ),
      array( 'id' => 'rss_urssaf',              'label' => 'URSSAF — Actualités',                    'type' => 'rss',     'axis' => 'legal',      'url' => 'https://www.urssaf.fr/portail/home/actualites.rss.html', 'active' => 1 ),
      array( 'id' => 'rss_dreets_paca',         'label' => 'DREETS PACA',                            'type' => 'rss',     'axis' => 'legal',      'url' => 'https://www.paca.dreets.gouv.fr/rss', 'active' => 1 ),
      array( 'id' => 'ga_qualiopi_reforme',     'label' => 'Google Alerts — Qualiopi réforme',       'type' => 'rss',     'axis' => 'legal',      'url' => '', 'active' => 0 ),
      array( 'id' => 'ga_cpf_evolution',        'label' => 'Google Alerts — CPF évolution',          'type' => 'rss',     'axis' => 'legal',      'url' => '', 'active' => 0 ),
      array( 'id' => 'ga_formation_loi',        'label' => 'Google Alerts — formation professionnelle loi', 'type' => 'rss', 'axis' => 'legal', 'url' => '', 'active' => 0 ),

      /* ── Axe métiers (ind. 24) ── */
      array( 'id' => 'rss_francetravail_stats', 'label' => 'France Travail — Statistiques métiers',  'type' => 'rss',     'axis' => 'metiers',    'url' => 'https://www.pole-emploi.org/statistiques-analyses/publications-et-open-data/publications.html?rss', 'active' => 1 ),
      array( 'id' => 'ga_ia_formation',         'label' => 'Google Alerts — IA générative formation', 'type' => 'rss',    'axis' => 'metiers',    'url' => '', 'active' => 0 ),
      array( 'id' => 'ga_nocode_formation',     'label' => 'Google Alerts — no-code formation',       'type' => 'rss',    'axis' => 'metiers',    'url' => '', 'active' => 0 ),
      array( 'id' => 'ga_marketing_tpe',        'label' => 'Google Alerts — marketing digital TPE',   'type' => 'rss',    'axis' => 'metiers',    'url' => '', 'active' => 0 ),
      array( 'id' => 'ga_management_formation', 'label' => 'Google Alerts — management leadership formation', 'type' => 'rss', 'axis' => 'metiers', 'url' => '', 'active' => 0 ),
      array( 'id' => 'yt_ludo_salenne',         'label' => 'YouTube — Ludo Salenne (IA générative)',  'type' => 'youtube', 'axis' => 'metiers',    'url' => 'UCYm6_1-wBimiOrFBM8cSPEA', 'active' => 0 ),

      /* ── Axe pédagogique (ind. 25) ── */
      array( 'id' => 'rss_thotcursus',          'label' => 'Thot Cursus — Formation',                'type' => 'rss',     'axis' => 'pedagogique', 'url' => 'https://cursus.edu/feed/', 'active' => 1 ),
      array( 'id' => 'rss_elearning_industry',  'label' => 'eLearning Industry',                     'type' => 'rss',     'axis' => 'pedagogique', 'url' => 'https://elearningindustry.com/feed', 'active' => 1 ),
      array( 'id' => 'rss_journaldunet',        'label' => 'Journal du Net — Formation',              'type' => 'rss',     'axis' => 'pedagogique', 'url' => 'https://www.journaldunet.com/management/formation/rss.xml', 'active' => 1 ),
      array( 'id' => 'ga_edtech_formation',     'label' => 'Google Alerts — EdTech formation pro',   'type' => 'rss',     'axis' => 'pedagogique', 'url' => '', 'active' => 0 ),
      array( 'id' => 'ga_innovation_pedago',    'label' => 'Google Alerts — innovation pédagogique', 'type' => 'rss',     'axis' => 'pedagogique', 'url' => '', 'active' => 0 ),
    );
  }

  /* -----------------------------------------------------------------------
   * Accesseurs table
   * ----------------------------------------------------------------------- */

  private function get_watch_items_table() {
    global $wpdb;
    return $wpdb->prefix . 'acdc_of_watch_items';
  }

  /* -----------------------------------------------------------------------
   * Récupération des sources configurées (option + defaults)
   * ----------------------------------------------------------------------- */

  private function get_ia_watch_sources() {
    $saved   = get_option( 'acdc_of_watch_sources', array() );
    $default = $this->get_ia_default_watch_sources();
    // ACDC 3.25.110 — ids de sources par défaut explicitement supprimées par l'utilisateur :
    // elles ne doivent JAMAIS être ré-injectées par la fusion ci-dessous.
    $deleted_defaults = array_flip( (array) get_option( 'acdc_of_watch_deleted_default_ids', array() ) );
    if ( empty( $saved ) ) {
      if ( empty( $deleted_defaults ) ) {
        return $default;
      }
      return array_values( array_filter( $default, static function ( $d ) use ( $deleted_defaults ) {
        return ! isset( $deleted_defaults[ $d['id'] ] );
      } ) );
    }
    // Fusionner : les sources sauvegardées priment ; les nouvelles defaults sont ajoutées,
    // sauf celles que l'utilisateur a supprimées.
    $saved_ids = array();
    foreach ( $saved as $s ) {
      $saved_ids[ $s['id'] ] = true;
    }
    foreach ( $default as $d ) {
      if ( empty( $saved_ids[ $d['id'] ] ) && ! isset( $deleted_defaults[ $d['id'] ] ) ) {
        $saved[] = $d;
      }
    }
    return $saved;
  }

  private function get_ia_watch_sources_active() {
    $all = $this->get_ia_watch_sources();
    $active = array();
    foreach ( $all as $s ) {
      if ( ! empty( $s['active'] ) && ! empty( $s['url'] ) ) {
        $active[] = $s;
      }
    }
    return $active;
  }

  /* -----------------------------------------------------------------------
   * Cron de collecte principal
   * ----------------------------------------------------------------------- */

  public function process_watch_collect_cron() {
    // Purge automatique avant collecte
    $this->_purge_stale_watch_items();

    $sources = $this->get_ia_watch_sources_active();
    foreach ( $sources as $source ) {
      if ( 'rss' === $source['type'] ) {
        $this->_collect_rss_source( $source );
      } elseif ( 'youtube' === $source['type'] ) {
        $this->_collect_youtube_source( $source );
      }
    }

    // Mémoriser la date de cette collecte pour la fenêtre glissante suivante
    update_option( 'acdc_of_watch_last_collected_at', current_time( 'mysql' ) );
  }

  /* -----------------------------------------------------------------------
   * Purge automatique des articles parasites ou périmés
   * — Statut "new" depuis plus de 30 jours (jamais analysé)
   * — Titre commençant par "Par :" (artefact flux de commentaires)
   * ----------------------------------------------------------------------- */

  private function _purge_stale_watch_items() {
    global $wpdb;
    $tbl           = $this->get_watch_items_table();
    $deadline_new  = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days', current_time( 'timestamp' ) ) );
    $deadline_irr  = gmdate( 'Y-m-d H:i:s', strtotime( '-90 days', current_time( 'timestamp' ) ) );

    // Supprimer les articles non analysés depuis plus de 30 jours
    $wpdb->query( $wpdb->prepare(
      "DELETE FROM {$tbl} WHERE status = 'new' AND collected_at < %s",
      $deadline_new
    ) );

    // Supprimer les articles irrelevant depuis plus de 90 jours
    $wpdb->query( $wpdb->prepare(
      "DELETE FROM {$tbl} WHERE status = 'irrelevant' AND collected_at < %s",
      $deadline_irr
    ) );

    // Supprimer les artefacts "Par : Auteur" (flux commentaires mal configurés)
    $wpdb->query(
      "DELETE FROM {$tbl} WHERE title LIKE 'Par :%'"
    );
  }

  /* -----------------------------------------------------------------------
   * Collecte RSS (via fetch_feed WordPress — SimplePie intégré)
   * ----------------------------------------------------------------------- */

  private function _collect_rss_source( $source ) {
    if ( empty( $source['url'] ) ) {
      return;
    }

    if ( ! function_exists( 'fetch_feed' ) ) {
      require_once ABSPATH . WPINC . '/feed.php';
    }

    $feed = fetch_feed( $source['url'] );
    if ( is_wp_error( $feed ) ) {
      return;
    }

    $max_items        = (int) get_option( 'acdc_of_watch_max_per_source', 10 );
    $items            = $feed->get_items( 0, $max_items );
    $last_collected   = get_option( 'acdc_of_watch_last_collected_at', '' );

    foreach ( $items as $item ) {
      $url   = $item->get_permalink();
      $title = $item->get_title();
      if ( empty( $url ) || empty( $title ) ) {
        continue;
      }
      $pub_date = $item->get_date( 'Y-m-d H:i:s' );

      // Filtre fenêtre glissante : ignorer les articles publiés avant la dernière collecte
      if ( $last_collected && $pub_date && $pub_date <= $last_collected ) {
        continue;
      }

      $summary = wp_strip_all_tags( $item->get_description() );
      $summary = mb_substr( $summary, 0, 500 );

      $this->_insert_watch_item_if_new( array(
        'source_id'   => $source['id'],
        'source_type' => 'rss',
        'watch_axis'  => $source['axis'],
        'title'       => $title,
        'url'         => $url,
        'summary'     => $summary,
        'published_at' => $pub_date ? $pub_date : null,
      ) );
    }
  }

  /* -----------------------------------------------------------------------
   * Collecte YouTube (API Data v3)
   * ----------------------------------------------------------------------- */

  private function _collect_youtube_source( $source ) {
    $api_key    = $this->acdc_secret_decrypt( get_option( 'acdc_of_watch_api_youtube', '' ) );
    $raw        = ! empty( $source['url'] ) ? trim( sanitize_text_field( $source['url'] ) ) : '';

    if ( empty( $api_key ) || empty( $raw ) ) {
      return;
    }

    // Résolution : extraire le channel ID depuis une URL ou un handle
    $channel_id = $raw;
    if ( strpos( $raw, 'youtube.com/channel/' ) !== false ) {
      // https://www.youtube.com/channel/UC...
      preg_match( '#youtube\.com/channel/([A-Za-z0-9_-]+)#', $raw, $m );
      $channel_id = ! empty( $m[1] ) ? $m[1] : $raw;
    } elseif ( strpos( $raw, 'youtube.com/@' ) !== false || ( strpos( $raw, '@' ) === 0 ) ) {
      // https://www.youtube.com/@Handle ou @Handle — résolution via API
      $handle = ltrim( preg_replace( '#https?://(?:www\.)?youtube\.com/#', '', $raw ), '@' );
      $resolve_url = 'https://www.googleapis.com/youtube/v3/channels?key=' . urlencode( $api_key ) . '&forHandle=' . urlencode( $handle ) . '&part=id';
      $res = wp_remote_get( $resolve_url, array( 'timeout' => 10, 'sslverify' => true ) );
      if ( ! is_wp_error( $res ) ) {
        $d = json_decode( wp_remote_retrieve_body( $res ), true );
        $channel_id = isset( $d['items'][0]['id'] ) ? $d['items'][0]['id'] : $raw;
      }
    }

    $cache_key = 'acdc_watch_yt_' . md5( $channel_id );
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) {
      return; // quota : 1 appel par canal par 24h
    }

    $api_url = 'https://www.googleapis.com/youtube/v3/search?key=' . urlencode( $api_key ) . '&channelId=' . urlencode( $channel_id ) . '&part=snippet&order=date&maxResults=5&type=video';

    $response = wp_remote_get( $api_url, array( 'timeout' => 15, 'sslverify' => true ) );
    if ( is_wp_error( $response ) ) {
      return;
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( empty( $data['items'] ) ) {
      return;
    }

    set_transient( $cache_key, 1, DAY_IN_SECONDS );
    $last_collected = get_option( 'acdc_of_watch_last_collected_at', '' );

    foreach ( $data['items'] as $item ) {
      $video_id = isset( $item['id']['videoId'] ) ? $item['id']['videoId'] : '';
      if ( empty( $video_id ) ) {
        continue;
      }
      $title    = isset( $item['snippet']['title'] ) ? $item['snippet']['title'] : '';
      $pub_raw  = isset( $item['snippet']['publishedAt'] ) ? $item['snippet']['publishedAt'] : '';
      $pub_date = $pub_raw ? wp_date( 'Y-m-d H:i:s', strtotime( $pub_raw ) ) : null;

      // Filtre fenêtre glissante
      if ( $last_collected && $pub_date && $pub_date <= $last_collected ) {
        continue;
      }

      $summary = isset( $item['snippet']['description'] ) ? mb_substr( $item['snippet']['description'], 0, 500 ) : '';
      $url     = 'https://www.youtube.com/watch?v=' . $video_id;

      $this->_insert_watch_item_if_new( array(
        'source_id'   => $source['id'],
        'source_type' => 'youtube',
        'watch_axis'  => $source['axis'],
        'title'       => $title,
        'url'         => $url,
        'summary'     => $summary,
        'published_at' => $pub_date,
      ) );
    }
  }

  /* -----------------------------------------------------------------------
   * Insertion dédoublonnée
   * ----------------------------------------------------------------------- */

  /**
   * ACDC 3.25.112 — Normalise une URL de veille pour une déduplication cohérente :
   * schéma + hôte en minuscules, slash final retiré, ancre supprimée, et paramètres
   * de tracking (utm_*, fbclid, gclid, mc_cid/mc_eid) filtrés + tri des paramètres restants.
   */
  private function _normalize_watch_url( $url ) {
    $url = esc_url_raw( (string) $url );
    if ( '' === $url ) {
      return '';
    }
    $parts = wp_parse_url( $url );
    if ( ! $parts || empty( $parts['host'] ) ) {
      return $url;
    }
    $scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'https';
    $host   = strtolower( $parts['host'] );
    $path   = isset( $parts['path'] ) ? rtrim( $parts['path'], '/' ) : '';
    $query  = '';
    if ( ! empty( $parts['query'] ) ) {
      parse_str( $parts['query'], $q );
      foreach ( array_keys( $q ) as $k ) {
        if ( 0 === strpos( (string) $k, 'utm_' ) || in_array( $k, array( 'fbclid', 'gclid', 'mc_cid', 'mc_eid' ), true ) ) {
          unset( $q[ $k ] );
        }
      }
      if ( ! empty( $q ) ) {
        ksort( $q );
        $query = '?' . http_build_query( $q );
      }
    }
    return $scheme . '://' . $host . $path . $query;
  }

  private function _insert_watch_item_if_new( $data ) {
    global $wpdb;
    $tbl = $this->get_watch_items_table();

    if ( empty( $data['url'] ) ) {
      return;
    }

    $url_clean = $this->_normalize_watch_url( $data['url'] );
    if ( '' === $url_clean ) {
      return;
    }
    $url_hash  = md5( $url_clean );
    $exists    = $wpdb->get_var( $wpdb->prepare(
      "SELECT id FROM {$tbl} WHERE MD5(url) = %s LIMIT 1",
      $url_hash
    ) );
    if ( $exists ) {
      return;
    }

    $now = current_time( 'mysql' );
    $wpdb->insert( $tbl, array(
      'source_id'    => isset( $data['source_id'] ) ? sanitize_key( $data['source_id'] ) : '',
      'source_type'  => isset( $data['source_type'] ) ? sanitize_key( $data['source_type'] ) : 'rss',
      'watch_axis'   => isset( $data['watch_axis'] ) ? sanitize_key( $data['watch_axis'] ) : '',
      'title'        => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '',
      'url'          => $url_clean,
      'summary'      => isset( $data['summary'] ) ? sanitize_textarea_field( $data['summary'] ) : '',
      'published_at' => isset( $data['published_at'] ) ? $data['published_at'] : null,
      'status'       => 'new',
      'collected_at' => $now,
      'updated_at'   => $now,
    ) );
  }

  /* -----------------------------------------------------------------------
   * Requêtes BDD
   * ----------------------------------------------------------------------- */

  private function get_watch_items_list( $args = array() ) {
    global $wpdb;
    $tbl    = $this->get_watch_items_table();
    $where  = array( '1=1' );
    $values = array();

    if ( ! empty( $args['axis'] ) ) {
      $where[]  = 'watch_axis = %s';
      $values[] = $args['axis'];
    }
    if ( ! empty( $args['status'] ) ) {
      $where[]  = 'status = %s';
      $values[] = $args['status'];
    }
    if ( ! empty( $args['status_in'] ) && is_array( $args['status_in'] ) ) {
      $placeholders = implode( ',', array_fill( 0, count( $args['status_in'] ), '%s' ) );
      $where[]      = "status IN ({$placeholders})";
      foreach ( $args['status_in'] as $sv ) {
        $values[] = $sv;
      }
    }
    if ( ! empty( $args['urgency'] ) ) {
      $where[]  = 'ai_urgency = %s';
      $values[] = $args['urgency'];
    }
    if ( isset( $args['min_score'] ) ) {
      $where[]  = 'ai_score >= %d';
      $values[] = (int) $args['min_score'];
    }

    $where_sql = implode( ' AND ', $where );
    $order     = ! empty( $args['order'] ) ? 'collected_at DESC' : 'COALESCE(published_at, collected_at) DESC';
    $limit     = ! empty( $args['limit'] ) ? (int) $args['limit'] : 50;

    $sql = "SELECT * FROM {$tbl} WHERE {$where_sql} ORDER BY {$order} LIMIT {$limit}";
    if ( ! empty( $values ) ) {
      $sql = $wpdb->prepare( $sql, $values );
    }
    return $wpdb->get_results( $sql );
  }

  private function get_watch_item( $id ) {
    global $wpdb;
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->get_watch_items_table()} WHERE id = %d", (int) $id ) );
  }

  private function get_watch_dashboard_stats() {
    global $wpdb;
    $tbl      = $this->get_watch_items_table();
    $month_start = gmdate( 'Y-m-01 00:00:00', current_time( 'timestamp' ) );

    $stats = array(
      'total_new'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} WHERE status = 'new'" ),
      'total_month'       => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl} WHERE collected_at >= %s", $month_start ) ),
      'total_relevant'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} WHERE ai_score >= 6" ),
      'total_exploited'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} WHERE status = 'exploited'" ),
      'total_irrelevant'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl} WHERE status = 'irrelevant'" ),
      'last_exploitation' => $wpdb->get_var( "SELECT MAX(exploited_at) FROM {$tbl} WHERE status = 'exploited'" ),
    );

    foreach ( array( 'legal', 'metiers', 'pedagogique' ) as $ax ) {
      $stats[ 'axis_' . $ax ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl} WHERE watch_axis = %s AND collected_at >= %s", $ax, $month_start ) );
    }

    return $stats;
  }

  /* -----------------------------------------------------------------------
   * Source label depuis l'id
   * ----------------------------------------------------------------------- */

  private function get_watch_source_label( $source_id ) {
    $sources = $this->get_ia_watch_sources();
    foreach ( $sources as $s ) {
      if ( $s['id'] === $source_id ) {
        return $s['label'];
      }
    }
    return $source_id;
  }

  /* -----------------------------------------------------------------------
   * Labels axes
   * ----------------------------------------------------------------------- */

  private function get_watch_axis_label( $axis ) {
    $map = array(
      'legal'       => 'Légal / réglementaire',
      'metiers'     => 'Métiers / emplois',
      'pedagogique' => 'Pédagogique / technologique',
    );
    return isset( $map[ $axis ] ) ? $map[ $axis ] : $axis;
  }

  private function get_watch_axis_color( $axis ) {
    $map = array(
      'legal'       => '#1E4777',
      'metiers'     => '#8b5b23',
      'pedagogique' => '#35b37e',
    );
    return isset( $map[ $axis ] ) ? $map[ $axis ] : '#4b5d76';
  }

  private function get_watch_urgency_label( $urgency ) {
    $map = array(
      'immediat'    => 'Immédiat',
      'ce_mois'     => 'Ce mois',
      'surveiller'  => 'Surveiller',
    );
    return isset( $map[ $urgency ] ) ? $map[ $urgency ] : '—';
  }

  private function get_watch_urgency_color( $urgency ) {
    $map = array(
      'immediat'   => '#dc2626',
      'ce_mois'    => '#d6a353',
      'surveiller' => '#4b5d76',
    );
    return isset( $map[ $urgency ] ) ? $map[ $urgency ] : '#4b5d76';
  }

}
