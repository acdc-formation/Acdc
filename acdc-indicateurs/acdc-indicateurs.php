<?php
/**
 * Plugin Name:  ACDC Indicateurs
 * Plugin URI:   https://acdc-formation.com/
 * Description:  Affiche les indicateurs de résultats ACDC Formation (apprenants, satisfaction, réussite, heures) en consommant le SAAS via REST API.
 * Version:      1.0.7
 * Author:       ACDC Formation
 * Text Domain:  acdc-indicateurs
 * License:      Propriétaire
 */

defined( 'ABSPATH' ) || exit;

define( 'ACDC_IND_VERSION', '1.0.7' );
define( 'ACDC_IND_FILE',    __FILE__ );
define( 'ACDC_IND_DIR',     plugin_dir_path( __FILE__ ) );
define( 'ACDC_IND_URL',     plugin_dir_url( __FILE__ ) );

require_once ACDC_IND_DIR . 'includes/class-acdc-ind-api.php';
require_once ACDC_IND_DIR . 'includes/class-acdc-ind-shortcode.php';

/* ── Réglages ─────────────────────────────────────────────────────────────── */
add_action( 'admin_menu', function() {
    add_options_page(
        'ACDC Indicateurs',
        'ACDC Indicateurs',
        'manage_options',
        'acdc-indicateurs',
        'acdc_ind_render_settings_page'
    );
} );

function acdc_ind_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }

    // Sauvegarde
    if ( isset( $_POST['acdc_ind_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['acdc_ind_nonce'] ) ), 'acdc_ind_save' ) ) {
        update_option( 'acdc_ind_saas_url',    esc_url_raw( trim( wp_unslash( $_POST['acdc_ind_saas_url'] ?? '' ) ) ) );
        update_option( 'acdc_ind_cache_hours', max( 1, min( 168, (int) ( $_POST['acdc_ind_cache_hours'] ?? 24 ) ) ) );
        update_option( 'acdc_ind_fallback_apprenants', max( 0, (int) ( $_POST['acdc_ind_fallback_apprenants'] ?? 0 ) ) );
        update_option( 'acdc_ind_fallback_heures',     max( 0, (int) ( $_POST['acdc_ind_fallback_heures'] ?? 0 ) ) );
        echo '<div class="notice notice-success"><p>Réglages enregistrés.</p></div>';
    }

    // Vider le cache
    if ( isset( $_POST['acdc_ind_flush'] ) ) {
        delete_transient( 'acdc_ind_global' );
        echo '<div class="notice notice-success"><p>Cache vidé.</p></div>';
    }

    $saas_url    = get_option( 'acdc_ind_saas_url', '' );
    $cache_hours = (int) get_option( 'acdc_ind_cache_hours', 24 );
    $fallback_apprenants = (int) get_option( 'acdc_ind_fallback_apprenants', 0 );
    $fallback_heures     = (int) get_option( 'acdc_ind_fallback_heures', 0 );

    // Test de connexion
    $test_result = '';
    if ( isset( $_POST['acdc_ind_test'] ) && $saas_url ) {
        $api  = new ACDC_Ind_Api();
        $data = $api->fetch_global( true ); // forcer sans cache
        $test_result = $data ? '<span style="color:green;">✅ Connexion réussie — ' . count( $data ) . ' clés reçues.</span>' : '<span style="color:red;">❌ Impossible de joindre le SAAS.</span>';
    }
    ?>
    <div class="wrap">
      <h1>ACDC Indicateurs</h1>
      <form method="post">
        <?php wp_nonce_field( 'acdc_ind_save', 'acdc_ind_nonce' ); ?>
        <table class="form-table">
          <tr>
            <th>URL du SAAS</th>
            <td>
              <input type="url" name="acdc_ind_saas_url" value="<?php echo esc_attr( $saas_url ); ?>" class="regular-text" placeholder="https://acdcformation.com">
              <p class="description">URL de base du SAAS, sans slash final. Ex : <code>https://acdcformation.com</code></p>
            </td>
          </tr>
          <tr>
            <th>Durée du cache (heures)</th>
            <td>
              <input type="number" name="acdc_ind_cache_hours" value="<?php echo esc_attr( (string) $cache_hours ); ?>" min="1" max="168" class="small-text">
              <p class="description">Durée de mise en cache des indicateurs. Défaut : 24h.</p>
            </td>
          </tr>
          <tr>
            <th>Valeur de secours — Apprenants formés</th>
            <td>
              <input type="number" name="acdc_ind_fallback_apprenants" value="<?php echo esc_attr( (string) $fallback_apprenants ); ?>" min="0" class="small-text">
              <p class="description">Dernier recours seulement. Depuis la 1.0.7, le SAAS remonte le total réel — prestations extérieures comprises — et cette valeur ne sert plus que s'il est injoignable.</p>
            </td>
          </tr>
          <tr>
            <th>Valeur de secours — Heures suivies</th>
            <td>
              <input type="number" name="acdc_ind_fallback_heures" value="<?php echo esc_attr( (string) $fallback_heures ); ?>" min="0" class="small-text">
              <p class="description">Dernier recours seulement. Depuis la 1.0.7, le SAAS remonte le total réel — prestations extérieures comprises — et cette valeur ne sert plus que s'il est injoignable.</p>
            </td>
          </tr>
        </table>
        <?php if ( $test_result ) : ?><p><?php echo $test_result; ?></p><?php endif; ?>
        <p>
          <button type="submit" class="button button-primary">Enregistrer</button>
          <button type="submit" name="acdc_ind_test" value="1" class="button" style="margin-left:8px;">Tester la connexion</button>
          <button type="submit" name="acdc_ind_flush" value="1" class="button" style="margin-left:8px;">Vider le cache</button>
        </p>
      </form>
      <hr>
      <h2>Utilisation</h2>
      <p>Placez ce shortcode dans n'importe quelle page ou widget Elementor (HTML) :</p>
      <pre style="background:#f0f0f0;padding:12px;border-radius:6px;">[acdc_indicateurs_globaux]</pre>
      <h3>Attributs disponibles</h3>
      <table class="widefat" style="max-width:600px;">
        <thead><tr><th>Attribut</th><th>Défaut</th><th>Description</th></tr></thead>
        <tbody>
          <tr><td><code>colonnes</code></td><td>3</td><td>Nombre de colonnes (1 à 4)</td></tr>
          <tr><td><code>style</code></td><td>cards</td><td><code>cards</code> ou <code>inline</code></td></tr>
          <tr><td><code>couleur</code></td><td>#8b5b23</td><td>Couleur des chiffres (hex)</td></tr>
          <tr><td><code>items</code></td><td>all</td><td>apprenants, satisfaction, reussite, recommandation, formations, heures (suivies), heures_dispensees — séparés par virgule</td></tr>
        </tbody>
      </table>
      <h3>Exemple personnalisé</h3>
      <pre style="background:#f0f0f0;padding:12px;border-radius:6px;">[acdc_indicateurs_globaux colonnes="4" items="apprenants,satisfaction,reussite,formations" couleur="#0f2c52"]</pre>
    </div>
    <?php
}

/* ── Cron de rafraîchissement ─────────────────────────────────────────────── */
add_action( 'wp', function() {
    if ( ! wp_next_scheduled( 'acdc_ind_refresh_cache' ) ) {
        wp_schedule_event( time(), 'daily', 'acdc_ind_refresh_cache' );
    }
} );

add_action( 'acdc_ind_refresh_cache', function() {
    $api = new ACDC_Ind_Api();
    $api->fetch_global( true ); // force le rafraîchissement
} );

/* ── Désinstallation propre ───────────────────────────────────────────────── */
register_deactivation_hook( __FILE__, function() {
    wp_clear_scheduled_hook( 'acdc_ind_refresh_cache' );
} );
