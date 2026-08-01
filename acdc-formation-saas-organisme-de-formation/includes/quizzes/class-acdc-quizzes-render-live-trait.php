<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * ACDC Formation SAAS — Module Quizzes
 * Render Live trait — pages publiques host et player du moteur de jeu live.
 *
 * 3.21.04.2-a — Création initiale.
 *
 * Shortcodes :
 *  - [acdc_qz_live_host]   → écran de pilotage formateur (page /acdc-quiz-live-host/)
 *  - [acdc_qz_live_player] → écran apprenant (page /acdc-quiz-live/)
 *
 * Les deux écrans sont en pleine page, sans header de thème ni sidebar.
 * Le rendu est gouverné par templates en JavaScript (states machine côté client),
 * alimenté par les endpoints AJAX implémentés dans le Actions trait.
 *
 * @package ACDC_OF_SAAS
 * @since   3.21.04.2-a
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait ACDC_Quizzes_Render_Live_Trait {

    /**
     * Enregistre les shortcodes live.
     */
    public function register_qz_live_shortcodes() {
        add_shortcode( 'acdc_qz_live_host', array( $this, 'shortcode_qz_live_host' ) );
        // [acdc_qz_live_player] est déjà enregistré dans render trait, on n'y touche pas.
    }

    /**
     * Shortcode [acdc_qz_live_host] — écran de pilotage du formateur.
     */
    public function shortcode_qz_live_host( $atts = array() ) {
        if ( ! is_user_logged_in() ) {
            return '<div class="acdc-qz-live-error"><p>Vous devez être connecté pour piloter une session live.</p></div>';
        }
        $session_id = isset( $_GET['session'] ) ? (int) $_GET['session'] : 0;
        if ( $session_id <= 0 ) {
            return '<div class="acdc-qz-live-error"><p>Aucune session précisée.</p></div>';
        }
        $session = $this->get_qz_dispatch_session( $session_id );
        if ( ! $session || 'live' !== $session->delivery_mode ) {
            return '<div class="acdc-qz-live-error"><p>Session live introuvable.</p></div>';
        }
        if ( (int) $session->host_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
            return '<div class="acdc-qz-live-error"><p>Vous n\'êtes pas le formateur de cette session.</p></div>';
        }
        $quiz = $this->get_qz_quiz( (int) $session->quiz_id );
        $quiz_title = $quiz ? (string) $quiz->title : 'Quiz';

        // URL apprenant pour QR code
        $player_page_id = (int) get_option( 'acdc_of_qz_live_public_page_id', 0 );
        $player_url = $player_page_id ? get_permalink( $player_page_id ) : home_url( '/acdc-quiz-live/' );
        $player_url_with_pin = add_query_arg( array( 'pin' => $session->pin_code ), $player_url );

        $nonce = wp_create_nonce( 'acdc_of_qz_live_host_' . $session_id );
        $ajax_url = admin_url( 'admin-ajax.php' );

        // Logo ACDC : on tente de récupérer le logo configuré par le plugin, sinon fallback.
        $logo_url = $this->qz_get_brand_logo_url();

        ob_start();
        ?>
        <div class="acdc-qz-live-host" data-session-id="<?php echo (int) $session_id; ?>"
             data-pin="<?php echo esc_attr( $session->pin_code ); ?>"
             data-nonce="<?php echo esc_attr( $nonce ); ?>"
             data-ajax-url="<?php echo esc_url( $ajax_url ); ?>"
             data-player-url="<?php echo esc_url( $player_url_with_pin ); ?>"
             data-quiz-title="<?php echo esc_attr( $quiz_title ); ?>">

            <!-- HEADER -->
            <header class="acdc-qz-live-host-header">
                <div class="acdc-qz-live-host-brand">
                    <?php if ( $logo_url ) : ?>
                        <img src="<?php echo esc_url( $logo_url ); ?>" alt="ACDC Formation" class="acdc-qz-live-host-logo" />
                    <?php else : ?>
                        <span class="acdc-qz-live-host-brand-text">ACDC Formation</span>
                        <span class="acdc-qz-live-host-brand-sub">ORGANISME DE FORMATION</span>
                    <?php endif; ?>
                </div>
                <div class="acdc-qz-live-host-header-actions">
                    <!-- Bouton Commencer déplacé dans la zone centrale -->
                </div>
            </header>

            <!-- MAIN -->
            <main class="acdc-qz-live-host-main" id="acdc-qz-host-main">

                <!-- État LOBBY (par défaut au chargement) -->
                <section class="acdc-qz-host-state acdc-qz-host-state-lobby is-active" data-state="lobby">
                    <aside class="acdc-qz-host-card acdc-qz-host-card-join">
                        <header class="acdc-qz-host-card-header">
                            <h2>Rejoindre</h2>
                            <span class="acdc-qz-host-card-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </span>
                        </header>
                        <div class="acdc-qz-host-qr" id="acdc-qz-host-qr"></div>
                        <p class="acdc-qz-host-pin-label">Code PIN du jeu</p>
                        <p class="acdc-qz-host-pin"><?php echo esc_html( $this->qz_format_pin( $session->pin_code ) ); ?></p>
                        <div class="acdc-qz-host-pin-sep"><span></span><span class="acdc-qz-host-pin-sep-dot">◆</span><span></span></div>
                        <p class="acdc-qz-host-join-hint">
                            <strong>URL du quiz :</strong><br>
                            <?php echo esc_url( $player_url ); ?>
                        </p>
                    </aside>

                    <div class="acdc-qz-host-center">
                        <div class="acdc-qz-host-center-stars">◆</div>
                        <h2 class="acdc-qz-host-center-title">Rejoignez le quiz</h2>
                        <div class="acdc-qz-host-center-quiz-name">
                            <?php echo esc_html( $quiz_title ); ?>
                        </div>
                        <button type="button" class="acdc-button acdc-button-primary acdc-qz-host-start-btn-center" id="acdc-qz-host-start-btn">
                            Commencer
                        </button>
                    </div>

                    <aside class="acdc-qz-host-card acdc-qz-host-card-participants">
                        <header class="acdc-qz-host-card-header">
                            <h2>Participants</h2>
                            <span class="acdc-qz-host-participants-count">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle>
                                </svg>
                                <span id="acdc-qz-host-count-pill">0</span>
                            </span>
                        </header>
                        <ul class="acdc-qz-host-participants-list" id="acdc-qz-host-participants-list">
                            <li class="acdc-qz-host-participants-empty">En attente de participants</li>
                        </ul>
                    </aside>
                </section>

                <!-- État QUESTION ACTIVE -->
                <section class="acdc-qz-host-state acdc-qz-host-state-question" data-state="question">
                    <div class="acdc-qz-host-timer-bar-wrap" id="acdc-qz-host-timer-wrap" style="display:none">
                        <div class="acdc-qz-host-timer-bar" id="acdc-qz-host-timer-bar"></div>
                        <span class="acdc-qz-host-timer-count" id="acdc-qz-host-timer-count">—</span>
                    </div>
                    <h2 class="acdc-qz-host-question-title" id="acdc-qz-host-q-title">…</h2>
                    <div class="acdc-qz-host-question-answers" id="acdc-qz-host-q-answers"></div>
                    <footer class="acdc-qz-host-question-footer">
                        <span class="acdc-qz-host-q-counter" id="acdc-qz-host-q-num">1/1</span>
                        <span class="acdc-qz-host-q-type" id="acdc-qz-host-q-type"></span>
                        <button type="button" class="acdc-button acdc-button-primary acdc-qz-host-footer-btn" id="acdc-qz-host-reveal-btn">
                            Réponse
                        </button>
                    </footer>
                </section>

                <!-- État RÉVÉLATION (overlay sur même layout que question) -->
                <section class="acdc-qz-host-state acdc-qz-host-state-reveal" data-state="reveal">
                    <h2 class="acdc-qz-host-question-title" id="acdc-qz-host-reveal-title">…</h2>
                    <div class="acdc-qz-host-reveal-stats" id="acdc-qz-host-reveal-stats" style="display:none"></div>
                    <div class="acdc-qz-host-question-answers acdc-qz-host-reveal-answers-grid" id="acdc-qz-host-reveal-answers"></div>
                    <footer class="acdc-qz-host-question-footer">
                        <span class="acdc-qz-host-q-counter" id="acdc-qz-host-reveal-q-num"></span>
                        <span class="acdc-qz-host-q-type" id="acdc-qz-host-reveal-q-type"></span>
                        <button type="button" class="acdc-button acdc-button-primary acdc-qz-host-footer-btn" id="acdc-qz-host-next-btn">
                            Question suivante →
                        </button>
                    </footer>
                </section>

                <!-- État PODIUM FINAL -->
                <section class="acdc-qz-host-state acdc-qz-host-state-podium" data-state="podium">
                    <div class="acdc-qz-podium-wrap">
                        <div class="acdc-qz-podium" id="acdc-qz-host-podium"></div>
                    </div>
                    <div class="acdc-qz-host-leaderboard-rest" id="acdc-qz-host-leaderboard-rest"></div>
                    <canvas class="acdc-qz-confetti-canvas" id="acdc-qz-host-confetti"></canvas>
                </section>

            </main>

            <!-- FOOTER -->
            <footer class="acdc-qz-live-host-footer">
                <div class="acdc-qz-live-host-footer-left">
                    <span class="acdc-qz-host-footer-pill">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M3 7h18v10H3z"/><path d="M7 7v10M17 7v10"/></svg>
                        Rejoindre
                    </span>
                    <span class="acdc-qz-host-footer-url">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        <?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>
                    </span>
                    <span class="acdc-qz-host-footer-pin">
                        Code PIN du jeu : <strong><?php echo esc_html( $this->qz_format_pin( $session->pin_code ) ); ?></strong>
                    </span>
                </div>
                <div class="acdc-qz-live-host-footer-right">
                    <span class="acdc-qz-host-footer-pill" title="Participants connectés">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                        <span id="acdc-qz-host-count-footer">0</span>
                    </span>
                    <button type="button" class="acdc-qz-host-footer-icon" title="Plein écran" id="acdc-qz-host-fullscreen-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M3 3h6v2H5v4H3zM21 3v6h-2V5h-4V3zM3 21v-6h2v4h4v2zM21 21h-6v-2h4v-4h2z"/></svg>
                    </button>
                </div>
            </footer>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Helper : formate un PIN à 6 chiffres en "XXX XXXX" (lisibilité).
     */
    private function qz_format_pin( $pin ) {
        $pin = (string) $pin;
        if ( strlen( $pin ) === 6 ) {
            return substr( $pin, 0, 3 ) . ' ' . substr( $pin, 3 );
        }
        if ( strlen( $pin ) === 7 ) {
            return substr( $pin, 0, 3 ) . ' ' . substr( $pin, 3 );
        }
        return $pin;
    }

    /**
     * Helper : retourne l'URL du logo ACDC configuré dans les réglages globaux.
     */
    private function qz_get_brand_logo_url() {
        // Tentatives multiples : option plugin → custom logo WP → vide
        $logo_id = (int) get_option( 'acdc_of_brand_logo_id', 0 );
        if ( $logo_id ) {
            $url = wp_get_attachment_url( $logo_id );
            if ( $url ) { return $url; }
        }
        $custom_logo = get_theme_mod( 'custom_logo' );
        if ( $custom_logo ) {
            $url = wp_get_attachment_url( $custom_logo );
            if ( $url ) { return $url; }
        }
        return '';
    }
}
