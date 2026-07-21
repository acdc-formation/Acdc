<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * ACDC Watch AI Trait
 * Pipeline IA : GPT-4o (score + résumé) → Claude (analyse ACDC, score ≥ 6) → Gemini (YouTube) → Perplexity (légal)
 * Toutes les clés API sont lues depuis les options WP — jamais codées en dur.
 * PHP 7.3 compatible.
 */
trait ACDC_Watch_AI_Trait {

  /* -----------------------------------------------------------------------
   * Cron d'analyse IA (lancé toutes les 6h, décalé 1h après la collecte)
   * ----------------------------------------------------------------------- */

  public function process_watch_analyze_cron() {
    global $wpdb;
    $tbl        = $this->get_watch_items_table();
    $batch_size = (int) get_option( 'acdc_of_watch_ai_batch', 20 );
    $min_score  = (int) get_option( 'acdc_of_watch_min_score', 6 );

    // Passe 1 — articles new sans score
    $items = $wpdb->get_results( $wpdb->prepare(
      "SELECT * FROM {$tbl} WHERE status = 'new' AND (ai_score IS NULL OR ai_score = 0) ORDER BY collected_at ASC LIMIT %d",
      $batch_size
    ) );

    if ( ! empty( $items ) ) {
      foreach ( $items as $item ) {
        $this->_analyze_watch_item( $item );
      }
    }

    // Passe 2 — articles reviewed avec score ≥ seuil mais ai_note vide (ré-analyse Claude)
    $reanalyze = $wpdb->get_results( $wpdb->prepare(
      "SELECT * FROM {$tbl} WHERE status = 'reviewed' AND ai_score >= %d AND (ai_note IS NULL OR ai_note = '') ORDER BY collected_at DESC LIMIT 10",
      $min_score
    ) );

    foreach ( $reanalyze as $item ) {
      $summary = ! empty( $item->ai_summary ) ? (string) $item->ai_summary : (string) $item->summary;
      $claude_result = $this->_call_claude_analysis( $item, $summary );
      if ( ! is_wp_error( $claude_result ) && ! empty( $claude_result ) ) {
        $update = array(
          'ai_note'      => isset( $claude_result['note_exploitation'] )  ? sanitize_textarea_field( $claude_result['note_exploitation'] )  : '',
          'ai_action'    => isset( $claude_result['action_concrete'] )    ? sanitize_textarea_field( $claude_result['action_concrete'] )    : '',
          'ai_analysis'  => isset( $claude_result['implications'] )       ? sanitize_textarea_field( $claude_result['implications'] )       : '',
          'ai_diffusion' => isset( $claude_result['diffusion_interne'] )  ? sanitize_textarea_field( $claude_result['diffusion_interne'] )  : '',
          'ai_urgency'   => isset( $claude_result['urgence'] ) && in_array( $claude_result['urgence'], array( 'immediat', 'ce_mois', 'surveiller' ), true ) ? $claude_result['urgence'] : 'surveiller',
          'updated_at'   => current_time( 'mysql' ),
        );
        if ( ! empty( $claude_result['formation_impactee'] ) ) {
          $f_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$this->formation_table} WHERE title LIKE %s AND is_draft = 0 LIMIT 1",
            '%' . $wpdb->esc_like( $claude_result['formation_impactee'] ) . '%'
          ) );
          if ( $f_id ) {
            $update['ai_formation_id'] = (int) $f_id;
          }
        }
        $wpdb->update( $tbl, $update, array( 'id' => (int) $item->id ) );
      }
    }
  }

  /* -----------------------------------------------------------------------
   * Analyse complète d'un article
   * ----------------------------------------------------------------------- */

  private function _analyze_watch_item( $item ) {
    global $wpdb;
    $tbl     = $this->get_watch_items_table();
    $item_id = (int) $item->id;

    // 1 — GPT-4o : score + résumé + axe
    $gpt_result = $this->_call_gpt4o_score( $item );
    if ( is_wp_error( $gpt_result ) || empty( $gpt_result['score'] ) ) {
      // Marquer pour éviter re-tentative infinie
      $wpdb->update( $tbl, array( 'status' => 'reviewed', 'ai_model' => 'gpt4o_failed', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $item_id ) );
      return;
    }

    $score    = (int) $gpt_result['score'];
    $summary  = isset( $gpt_result['resume'] ) ? sanitize_textarea_field( $gpt_result['resume'] ) : '';
    $axis_ai  = isset( $gpt_result['axe'] ) ? sanitize_key( $gpt_result['axe'] ) : '';

    $update_data = array(
      'ai_score'   => $score,
      'ai_summary' => $summary,
      'ai_model'   => 'gpt4o',
      'updated_at' => current_time( 'mysql' ),
    );
    if ( $axis_ai && in_array( $axis_ai, array( 'legal', 'metiers', 'pedagogique' ), true ) ) {
      $update_data['watch_axis'] = $axis_ai;
    }

    $min_score = (int) get_option( 'acdc_of_watch_min_score', 6 );

    // 2 — Claude : analyse approfondie si score ≥ seuil
    if ( $score >= $min_score ) {
      $claude_result = $this->_call_claude_analysis( $item, $summary );
      if ( ! is_wp_error( $claude_result ) && ! empty( $claude_result ) ) {
        $update_data['ai_analysis']    = isset( $claude_result['implications'] ) ? sanitize_textarea_field( $claude_result['implications'] ) : '';
        $update_data['ai_action']      = isset( $claude_result['action_concrete'] ) ? sanitize_textarea_field( $claude_result['action_concrete'] ) : '';
        $update_data['ai_urgency']     = isset( $claude_result['urgence'] ) && in_array( $claude_result['urgence'], array( 'immediat', 'ce_mois', 'surveiller' ), true ) ? $claude_result['urgence'] : 'surveiller';
        $update_data['ai_note']        = isset( $claude_result['note_exploitation'] ) ? sanitize_textarea_field( $claude_result['note_exploitation'] ) : '';
        $update_data['ai_diffusion']   = isset( $claude_result['diffusion_interne'] ) ? sanitize_textarea_field( $claude_result['diffusion_interne'] ) : '';
        $update_data['ai_model']       = 'gpt4o+claude';

        // Formation impactée
        if ( ! empty( $claude_result['formation_impactee'] ) ) {
          global $wpdb;
          $f_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$this->formation_table} WHERE title LIKE %s AND is_draft = 0 LIMIT 1",
            '%' . $wpdb->esc_like( $claude_result['formation_impactee'] ) . '%'
          ) );
          if ( $f_id ) {
            $update_data['ai_formation_id'] = (int) $f_id;
          }
        }
      }
    }

    // 3 — Gemini : si source YouTube
    if ( 'youtube' === $item->source_type ) {
      $gemini_result = $this->_call_gemini_youtube( $item );
      if ( ! is_wp_error( $gemini_result ) && ! empty( $gemini_result['resume'] ) ) {
        $existing_summary = ! empty( $update_data['ai_summary'] ) ? $update_data['ai_summary'] : '';
        $update_data['ai_summary'] = $existing_summary . "\n\n[Analyse Gemini]\n" . sanitize_textarea_field( $gemini_result['resume'] );
        $update_data['ai_model']   = ( isset( $update_data['ai_model'] ) ? $update_data['ai_model'] . '+' : '' ) . 'gemini';

        // Points clés en full_content
        if ( ! empty( $gemini_result['points_cles'] ) && is_array( $gemini_result['points_cles'] ) ) {
          $points_text = implode( "\n• ", $gemini_result['points_cles'] );
          $update_data['full_content'] = '• ' . $points_text;
        }
      }
    }

    // 4 — Perplexity : si axe légal et score ≥ seuil
    $final_axis = isset( $update_data['watch_axis'] ) ? $update_data['watch_axis'] : $item->watch_axis;
    if ( 'legal' === $final_axis && $score >= $min_score ) {
      $perplexity_result = $this->_call_perplexity_legal( $item );
      if ( ! is_wp_error( $perplexity_result ) && ! empty( $perplexity_result['resume_reglementaire'] ) ) {
        $existing_analysis = ! empty( $update_data['ai_analysis'] ) ? $update_data['ai_analysis'] : '';
        $update_data['ai_analysis'] = $existing_analysis . "\n\n[Vérification Perplexity]\n" . sanitize_textarea_field( $perplexity_result['resume_reglementaire'] );
        $update_data['ai_model']    = ( isset( $update_data['ai_model'] ) ? $update_data['ai_model'] . '+' : '' ) . 'perplexity';
      }
    }

    // Classification finale : score 1-3 → irrelevant automatiquement
    $update_data['status'] = ( $score > 0 && $score <= 3 ) ? 'irrelevant' : 'reviewed';
    $wpdb->update( $tbl, $update_data, array( 'id' => $item_id ) );
  }

  /* -----------------------------------------------------------------------
   * GPT-4o — score de pertinence
   * ----------------------------------------------------------------------- */

  private function _call_gpt4o_score( $item ) {
    $api_key = $this->acdc_secret_decrypt( get_option( 'acdc_of_watch_api_openai', '' ) );
    if ( empty( $api_key ) ) {
      return new WP_Error( 'no_key', 'Clé OpenAI manquante.' );
    }

    $prompt = "Évalue la pertinence de cet article pour un organisme de formation français spécialisé en : IA générative, no-code, marketing digital, management, restauration, hygiène alimentaire, soft skills.\n" . "Retourne UNIQUEMENT un JSON sans markdown :\n" . "{\"score\":0,\"resume\":\"200 mots max\",\"axe\":\"legal|metiers|pedagogique\"}\n" . "Article : " . sanitize_text_field( $item->title ) . " — " . sanitize_text_field( $item->summary );

    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
      'timeout'    => 30,
      'sslverify'  => true,
      'headers'    => array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type'  => 'application/json',
      ),
      'body' => wp_json_encode( array(
        'model'    => 'gpt-4o',
        'messages' => array( array( 'role' => 'user', 'content' => $prompt ) ),
        'max_tokens' => 400,
        'temperature' => 0.3,
      ) ),
    ) );

    if ( is_wp_error( $response ) ) {
      return $response;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $text = isset( $body['choices'][0]['message']['content'] ) ? trim( $body['choices'][0]['message']['content'] ) : '';
    $text = preg_replace( '/^```[a-z]*\n?|\n?```$/m', '', $text );
    $data = json_decode( $text, true );
    if ( ! is_array( $data ) || ! isset( $data['score'] ) ) {
      return new WP_Error( 'parse_error', 'Réponse GPT-4o non parseable.' );
    }
    return $data;
  }

  /* -----------------------------------------------------------------------
   * Claude — analyse approfondie
   * ----------------------------------------------------------------------- */

  private function _call_claude_analysis( $item, $gpt_summary ) {
    $api_key = $this->acdc_secret_decrypt( get_option( 'acdc_of_watch_api_anthropic', '' ) );
    if ( empty( $api_key ) ) {
      return new WP_Error( 'no_key', 'Clé Anthropic manquante.' );
    }

    $content = sanitize_text_field( $item->title ) . "\n\n" . ( $gpt_summary ?: sanitize_text_field( $item->summary ) );

    $prompt = "Tu es expert en formation professionnelle française, certifiée Qualiopi.\n" . "Organisme : ACDC Formation (Cogolin, Var).\n" . "Thématiques : IA, no-code, marketing digital, management, restauration, hygiène alimentaire, soft skills.\n\n" . "Article : " . $content . "\n\n" . "Réponds en JSON strict sans markdown :\n" . "{\"implications\":\"...\",\"action_concrete\":\"...\",\"urgence\":\"immediat|ce_mois|surveiller\",\"formation_impactee\":\"titre ou null\",\"note_exploitation\":\"3-5 phrases\",\"diffusion_interne\":\"message court\"}";

    $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
      'timeout'   => 45,
      'sslverify' => true,
      'headers'   => array(
        'x-api-key'         => $api_key,
        'anthropic-version' => '2023-06-01',
        'Content-Type'      => 'application/json',
      ),
      'body' => wp_json_encode( array(
        'model'      => 'claude-sonnet-4-20250514',
        'max_tokens' => 800,
        'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
      ) ),
    ) );

    if ( is_wp_error( $response ) ) {
      return $response;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $text = '';
    if ( ! empty( $body['content'] ) && is_array( $body['content'] ) ) {
      foreach ( $body['content'] as $block ) {
        if ( isset( $block['type'] ) && 'text' === $block['type'] ) {
          $text .= $block['text'];
        }
      }
    }
    $text = trim( preg_replace( '/^```[a-z]*\n?|\n?```$/m', '', $text ) );
    $data = json_decode( $text, true );
    if ( ! is_array( $data ) ) {
      return new WP_Error( 'parse_error', 'Réponse Claude non parseable.' );
    }
    return $data;
  }

  /* -----------------------------------------------------------------------
   * Gemini — transcription YouTube
   * ----------------------------------------------------------------------- */

  private function _call_gemini_youtube( $item ) {
    $api_key = $this->acdc_secret_decrypt( get_option( 'acdc_of_watch_api_gemini', '' ) );
    if ( empty( $api_key ) ) {
      return new WP_Error( 'no_key', 'Clé Gemini manquante.' );
    }

    $prompt = "Résume et analyse cette vidéo YouTube destinée à la formation professionnelle.\n" . "Titre : " . sanitize_text_field( $item->title ) . "\n" . "Description : " . sanitize_text_field( $item->summary ) . "\n" . "URL : " . esc_url_raw( $item->url ) . "\n\n" . "Retourne un JSON sans markdown :\n" . "{\"resume\":\"200 mots max\",\"points_cles\":[\"point1\",\"point2\"],\"innovations\":[\"innovation1\"],\"axe_qualiopi\":\"pedagogique|metiers\"}";

    $api_url  = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . urlencode( $api_key );
    $response = wp_remote_post( $api_url, array(
      'timeout'   => 30,
      'sslverify' => true,
      'headers'   => array( 'Content-Type' => 'application/json' ),
      'body'      => wp_json_encode( array(
        'contents' => array( array( 'parts' => array( array( 'text' => $prompt ) ) ) ),
      ) ),
    ) );

    if ( is_wp_error( $response ) ) {
      return $response;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $text = isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ? trim( $body['candidates'][0]['content']['parts'][0]['text'] ) : '';
    $text = preg_replace( '/^```[a-z]*\n?|\n?```$/m', '', $text );
    $data = json_decode( $text, true );
    if ( ! is_array( $data ) ) {
      return new WP_Error( 'parse_error', 'Réponse Gemini non parseable.' );
    }
    return $data;
  }

  /* -----------------------------------------------------------------------
   * Perplexity — vérification réglementaire
   * ----------------------------------------------------------------------- */

  private function _call_perplexity_legal( $item ) {
    $api_key = $this->acdc_secret_decrypt( get_option( 'acdc_of_watch_api_perplexity', '' ) );
    if ( empty( $api_key ) ) {
      return new WP_Error( 'no_key', 'Clé Perplexity manquante.' );
    }

    $prompt = "Recherche des informations officielles sur ce sujet réglementaire concernant la formation professionnelle française.\n" . "Cite uniquement des sources officielles (legifrance, travail.gouv.fr, francecompetences.fr, dreets.gouv.fr).\n" . "Retourne un JSON sans markdown :\n" . "{\"confirmation\":\"true|false|partielle\",\"sources_officielles\":[\"url1\"],\"resume_reglementaire\":\"résumé réglementation\",\"impact_of\":\"impact organisme de formation\"}\n" . "Sujet : " . sanitize_text_field( $item->title ) . " — " . sanitize_text_field( $item->summary );

    $response = wp_remote_post( 'https://api.perplexity.ai/chat/completions', array(
      'timeout'   => 30,
      'sslverify' => true,
      'headers'   => array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type'  => 'application/json',
      ),
      'body' => wp_json_encode( array(
        'model'    => 'sonar',
        'messages' => array( array( 'role' => 'user', 'content' => $prompt ) ),
        'max_tokens' => 600,
      ) ),
    ) );

    if ( is_wp_error( $response ) ) {
      return $response;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    $text = isset( $body['choices'][0]['message']['content'] ) ? trim( $body['choices'][0]['message']['content'] ) : '';
    $text = preg_replace( '/^```[a-z]*\n?|\n?```$/m', '', $text );
    $data = json_decode( $text, true );
    if ( ! is_array( $data ) ) {
      return new WP_Error( 'parse_error', 'Réponse Perplexity non parseable.' );
    }
    return $data;
  }

  /* -----------------------------------------------------------------------
   * Test de connexion clé API (appelé depuis les réglages AJAX)
   * ----------------------------------------------------------------------- */

  public function ajax_acdc_of_watch_test_api_key() {
    if ( ! current_user_can( 'manage_options' ) ) {
      wp_send_json_error( 'Permissions insuffisantes.' );
    }

    $provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
    $key      = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

    if ( empty( $provider ) || empty( $key ) ) {
      wp_send_json_error( 'Paramètres manquants.' );
    }

    $ok  = false;
    $msg = '';

    switch ( $provider ) {
      case 'openai':
        $r = wp_remote_get( 'https://api.openai.com/v1/models', array( 'timeout' => 10, 'headers' => array( 'Authorization' => 'Bearer ' . $key ) ) );
        $ok  = ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r );
        $msg = $ok ? 'Connexion OpenAI OK.' : 'Erreur connexion OpenAI.';
        break;
      case 'anthropic':
        $r = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
          'timeout' => 15,
          'headers' => array( 'x-api-key' => $key, 'anthropic-version' => '2023-06-01', 'Content-Type' => 'application/json' ),
          'body'    => wp_json_encode( array( 'model' => 'claude-haiku-4-5-20251001', 'max_tokens' => 10, 'messages' => array( array( 'role' => 'user', 'content' => 'ping' ) ) ) ),
        ) );
        $ok  = ! is_wp_error( $r ) && in_array( (int) wp_remote_retrieve_response_code( $r ), array( 200, 529 ), true );
        $msg = $ok ? 'Connexion Anthropic OK.' : 'Erreur connexion Anthropic.';
        break;
      case 'gemini':
        $r = wp_remote_get( 'https://generativelanguage.googleapis.com/v1beta/models?key=' . urlencode( $key ), array( 'timeout' => 10 ) );
        $ok  = ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r );
        $msg = $ok ? 'Connexion Gemini OK.' : 'Erreur connexion Gemini.';
        break;
      case 'perplexity':
        $r = wp_remote_post( 'https://api.perplexity.ai/chat/completions', array(
          'timeout' => 10,
          'headers' => array( 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ),
          'body'    => wp_json_encode( array( 'model' => 'sonar', 'messages' => array( array( 'role' => 'user', 'content' => 'test' ) ), 'max_tokens' => 5 ) ),
        ) );
        $ok  = ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r );
        $msg = $ok ? 'Connexion Perplexity OK.' : 'Erreur connexion Perplexity.';
        break;
      case 'youtube':
        $r = wp_remote_get( 'https://www.googleapis.com/youtube/v3/videos?part=id&id=dQw4w9WgXcQ&key=' . urlencode( $key ), array( 'timeout' => 10 ) );
        $ok  = ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r );
        $msg = $ok ? 'Clé YouTube Data API OK.' : 'Erreur clé YouTube.';
        break;
      default:
        wp_send_json_error( 'Fournisseur inconnu.' );
    }

    if ( $ok ) {
      wp_send_json_success( $msg );
    } else {
      wp_send_json_error( $msg );
    }
  }

}
