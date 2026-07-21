<?php
/**
 * ACDC Émargement Numérique — Pages publiques
 *
 * Mode 1 : formateur   — signature formateur + QR code
 * Mode 2 : liste       — liste apprenants (affiché via QR ou post-signature formateur)
 * Mode 3 : apprenant   — signature d'un apprenant individuel (lien email)
 * Mode 4 : confirmation — page de confirmation après signature
 *
 * @package ACDC_Formation_SAAS
 * @since   3.21.09
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ACDC_Emarg_Public {

    /** @var ACDC_Emarg_Core */
    private $core;
    /** @var ACDC_Emarg_Email */
    private $email;

    public function __construct( ACDC_Emarg_Core $core, ACDC_Emarg_Email $email ) {
        $this->core  = $core;
        $this->email = $email;
    }

    /* -----------------------------------------------------------------------
     * Exclusion cache — doit tourner dès init (avant template_redirect)
     * pour que LiteSpeed ne serve pas une page avec un nonce périmé.
     * -------------------------------------------------------------------- */
    public function maybe_set_nocache() {
        if ( ! isset( $_GET['acdc_emarg'] ) ) { return; }
        // WordPress natif
        nocache_headers();
        // LiteSpeed Cache Plugin (LSCWP)
        do_action( 'litespeed_control_set_nocache', 'acdc_emargement_public_page' );
        // Constante générique respectée par la plupart des plugins de cache
        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }
        // Header direct LiteSpeed Server
        header( 'X-LiteSpeed-Cache-Control: no-cache' );
    }

    /* -----------------------------------------------------------------------
     * template_redirect — intercepter les requêtes ?acdc_emarg=
     * Gère aussi bien les GET (affichage) que les POST (soumission signature)
     * afin d'éviter tout passage par /wp-admin/admin-post.php,
     * qui peut être bloqué par LiteSpeed WAF pour les mobiles non authentifiés.
     * -------------------------------------------------------------------- */
    public function maybe_handle() {
        if ( ! isset( $_GET['acdc_emarg'] ) ) { return; }
        $mode  = sanitize_key( wp_unslash( $_GET['acdc_emarg'] ) );
        $token = sanitize_text_field( wp_unslash( $_GET['tok'] ?? '' ) );
        if ( '' === $token ) { return; }

        status_header( 200 );
        nocache_headers();
        header( 'X-LiteSpeed-Cache-Control: no-cache' );

        // POST = soumission d'un formulaire de signature
        if ( 'POST' === strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
            $action = sanitize_key( wp_unslash( $_POST['emarg_action'] ?? '' ) );
            if ( 'trainer_sign' === $action ) {
                $this->process_trainer_sign( $token );
            } elseif ( 'learner_sign' === $action ) {
                $this->process_learner_sign( $token );
            }
            exit;
        }

        // GET = affichage de la page
        $this->render_page( $mode, $token );
        exit;
    }

    /* -----------------------------------------------------------------------
     * Handlers POST (admin-post nopriv)
     * -------------------------------------------------------------------- */
    public function process_trainer_sign( $token = null ) {
        if ( null === $token ) { $token = sanitize_text_field( wp_unslash( $_POST['emarg_tok'] ?? '' ) ); }
        $sig_data = wp_unslash( $_POST['sig_data'] ?? '' );
        if ( '' === $token ) { wp_die( 'Lien invalide ou expiré.', 403 ); }

        $emarg = $this->core->get_by_trainer_token( $token );
        if ( ! $emarg || 'signe' === $emarg->trainer_status ) {
            wp_safe_redirect( $this->core->get_public_url( 'formateur', $token ) . '&emarg_err=already' );
            exit;
        }

        // Charger la session pour start_at
        global $wpdb;
        $session_table = $wpdb->prefix . 'acdc_of_sessions';
        $session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$session_table} WHERE id = %d", $emarg->session_id ) );
        $start_at = $session ? $session->start_at : null;

        $ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
        $ok = $this->core->save_trainer_signature( $emarg->id, $sig_data, $ip, $start_at );

        if ( ! $ok ) {
            wp_safe_redirect( $this->core->get_public_url( 'formateur', $token ) . '&emarg_err=sig' );
            exit;
        }

        // Rediriger vers la liste apprenants
        wp_safe_redirect( $this->core->get_public_url( 'liste', $emarg->list_token ) . '&just_signed=1' );
        exit;
    }

    public function process_learner_sign( $token = null ) {
        if ( null === $token ) { $token = sanitize_text_field( wp_unslash( $_POST['emarg_tok'] ?? '' ) ); }
        $sig_data = wp_unslash( $_POST['sig_data'] ?? '' );
        if ( '' === $token ) { wp_die( 'Lien invalide ou expiré.', 403 ); }

        $learner = $this->core->get_learner_by_sign_token( $token );
        if ( ! $learner || 'signe' === $learner->status ) {
            wp_safe_redirect( $this->core->get_public_url( 'apprenant', $token ) . '&emarg_err=already' );
            exit;
        }

        global $wpdb;
        $session_table = $wpdb->prefix . 'acdc_of_sessions';
        $session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$session_table} WHERE id = %d", $learner->session_id ) );
        $start_at = $session ? $session->start_at : null;

        $ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
        $result = $this->core->save_learner_signature( $learner->id, $sig_data, $ip, $start_at );

        if ( ! $result ) {
            wp_safe_redirect( $this->core->get_public_url( 'apprenant', $token ) . '&emarg_err=sig' );
            exit;
        }

        $late = (int) ( $result['late_minutes'] ?? 0 );
        $emarg = $this->core->get_by_list_token( '' ); // placeholder
        // Retrouver le list_token pour le retour
        $emarg_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->core->table_sessions} WHERE id = %d",
            $learner->emarg_session_id
        ) );
        $list_url = $emarg_row ? $this->core->get_public_url( 'liste', $emarg_row->list_token ) : home_url('/');

        wp_safe_redirect( add_query_arg( array( 'signed' => '1', 'late' => $late ), $list_url ) );
        exit;
    }

    public function handle_send_learner_email() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die(); }
        $learner_row_id = absint( $_POST['learner_row_id'] ?? 0 );
        check_admin_referer( 'acdc_emarg_send_' . $learner_row_id );
        global $wpdb;
        $learner = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->core->table_learners} WHERE id = %d", $learner_row_id
        ) );
        if ( $learner && '' !== $learner->learner_email ) {
            $this->email->send_learner_email( $learner );
        }
        wp_safe_redirect( wp_get_referer() ?: home_url('/') );
        exit;
    }

    /* -----------------------------------------------------------------------
     * Rendu principal
     * -------------------------------------------------------------------- */
    private function render_page( $mode, $token ) {
        ?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>Émargement — ACDC Formation</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#f5f6fa;min-height:100vh;color:#1a2744}
.emarg-wrap{max-width:680px;margin:0 auto;padding:20px 16px 40px}
.emarg-header{background:#1a2744;color:#fff;padding:18px 24px;border-radius:10px 10px 0 0;margin-bottom:0}
.emarg-header h1{font-size:20px;margin:0 0 2px}
.emarg-header p{font-size:13px;color:#c9a84c;margin:0}
.emarg-card{background:#fff;border:1px solid #e2e6ea;border-radius:0 0 10px 10px;padding:24px}
.emarg-section-title{font-size:14px;font-weight:700;color:#1a2744;margin:0 0 12px;border-bottom:2px solid #c9a84c;padding-bottom:6px}
canvas{display:block;width:100%;height:160px;border:2px dashed #c9a84c;border-radius:8px;background:#fff;cursor:crosshair;touch-action:none}
.btn{display:inline-block;padding:12px 28px;border-radius:8px;font-weight:700;font-size:15px;cursor:pointer;border:none;text-align:center;text-decoration:none}
.btn-primary{background:#c9a84c;color:#fff}
.btn-soft{background:#fff;color:#1a2744;border:1.5px solid #1a2744}
.btn-danger{background:#fff;color:#c00;border:1.5px solid #c00;font-size:13px;padding:8px 16px}
.btn-sm{padding:8px 16px;font-size:13px}
.emarg-actions{display:flex;gap:12px;margin-top:16px;flex-wrap:wrap}
.emarg-hint{font-size:12px;color:#6b7280;margin-top:6px}
.emarg-alert{padding:10px 14px;border-radius:6px;margin-bottom:14px;font-size:14px}
.emarg-alert-success{background:#d4edda;color:#155724}
.emarg-alert-error{background:#f8d7da;color:#721c24}
.emarg-alert-info{background:#cfe2ff;color:#084298}
.emarg-qr-section{margin-top:24px;padding-top:20px;border-top:1px solid #e2e6ea;text-align:center}
.emarg-qr-section canvas{width:180px!important;height:180px!important;display:inline-block;border:none;border-radius:8px;cursor:default}
.learner-list{list-style:none;padding:0;margin:0}
.learner-list li{padding:12px 16px;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center;gap:12px}
.learner-list li:last-child{border-bottom:none}
.learner-name{font-weight:600;font-size:15px}
.learner-meta{font-size:12px;color:#6b7280;margin-top:2px}
.learner-status{display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:700;white-space:nowrap}
.status-pending{background:#fff3cd;color:#856404}
.status-signed{background:#d4edda;color:#155724}
.status-late{background:#fff3cd;color:#856404}
.status-absent{background:#f8d7da;color:#721c24}
.learner-sign-btn{background:#1a2744;color:#fff;border:none;padding:8px 16px;border-radius:6px;font-size:13px;cursor:pointer;text-decoration:none;display:inline-block}
.sig-preview{max-width:200px;max-height:80px;border:1px solid #e2e6ea;border-radius:4px;display:block;margin:8px 0 0}
@media(max-width:480px){
  .emarg-wrap{padding:10px 8px 30px}
  .emarg-card{padding:16px}
  .btn{width:100%}
  .emarg-actions{flex-direction:column}
  .learner-list li{flex-direction:column;align-items:flex-start}
}
</style>
</head>
<body>
<script src="<?php echo esc_url( ACDC_OF_SAAS_URL . 'assets/js/vendor/qrcode-generator.min.js' ); ?>"></script>
<script>
function initCanvas(canvasId) {
  var c=document.getElementById(canvasId),ctx=c.getContext('2d');
  c.width=c.offsetWidth||600;c.height=160;
  ctx.strokeStyle='#1a2744';ctx.lineWidth=2.5;ctx.lineCap=ctx.lineJoin='round';
  var drawing=false,hasSig=false;
  function getPos(e){var r=c.getBoundingClientRect(),src=e.touches?e.touches[0]:e;return{x:(src.clientX-r.left)*(c.width/r.width),y:(src.clientY-r.top)*(c.height/r.height)};}
  c.addEventListener('mousedown',function(e){drawing=true;ctx.beginPath();var p=getPos(e);ctx.moveTo(p.x,p.y);});
  c.addEventListener('mousemove',function(e){if(!drawing)return;var p=getPos(e);ctx.lineTo(p.x,p.y);ctx.stroke();hasSig=true;});
  c.addEventListener('mouseup',function(){drawing=false;});
  c.addEventListener('touchstart',function(e){e.preventDefault();drawing=true;ctx.beginPath();var p=getPos(e);ctx.moveTo(p.x,p.y);},{passive:false});
  c.addEventListener('touchmove',function(e){e.preventDefault();if(!drawing)return;var p=getPos(e);ctx.lineTo(p.x,p.y);ctx.stroke();hasSig=true;},{passive:false});
  c.addEventListener('touchend',function(){drawing=false;});
  window.addEventListener('resize',function(){var d=c.toDataURL();c.width=c.offsetWidth||600;ctx.strokeStyle='#1a2744';ctx.lineWidth=2.5;ctx.lineCap=ctx.lineJoin='round';if(hasSig){var i=new Image();i.onload=function(){ctx.drawImage(i,0,0,c.width,160);};i.src=d;}});
  return {getDataURL:function(){
    var tmp=document.createElement('canvas');tmp.width=c.width;tmp.height=160;
    var tctx=tmp.getContext('2d');tctx.fillStyle='#ffffff';tctx.fillRect(0,0,tmp.width,160);tctx.drawImage(c,0,0);
    return tmp.toDataURL('image/png');
  },hasSig:function(){return hasSig;},clear:function(){ctx.clearRect(0,0,c.width,160);hasSig=false;}};
}
</script>
<div class="emarg-wrap">
<?php
        switch ( $mode ) {
            case 'formateur': $this->render_trainer_page( $token ); break;
            case 'liste':     $this->render_learner_list( $token ); break;
            case 'apprenant': $this->render_learner_sign( $token ); break;
            default:
                echo '<div class="emarg-alert emarg-alert-error">Lien invalide ou expiré.</div>';
        }
?>
</div>
</body></html>
<?php
    }

    /* -----------------------------------------------------------------------
     * Mode 1 : formateur
     * -------------------------------------------------------------------- */
    private function render_trainer_page( $token ) {
        $emarg = $this->core->get_by_trainer_token( $token );
        if ( ! $emarg ) {
            echo '<div class="emarg-alert emarg-alert-error">Lien invalide ou expiré.</div>';
            return;
        }
        if ( 'signe' === $emarg->trainer_status ) {
            echo '<div class="emarg-alert emarg-alert-success">✅ Vous avez déjà signé. <a href="' . esc_url( $this->core->get_public_url('liste', $emarg->list_token) ) . '">Voir la liste des apprenants →</a></div>';
            return;
        }

        global $wpdb;
        $session_table = $wpdb->prefix . 'acdc_of_sessions';
        $session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$session_table} WHERE id = %d", $emarg->session_id ) );
        $session_label = $session ? ( $session->title ?: 'Séance #' . $session->id ) : 'Séance';

        if ( isset( $_GET['emarg_err'] ) ) {
            echo '<div class="emarg-alert emarg-alert-error">Erreur : veuillez dessiner votre signature avant de valider.</div>';
        }
        ?>
        <div class="emarg-header">
            <h1>✍ Émargement formateur</h1>
            <p><?php echo esc_html( $session_label ); ?></p>
        </div>
        <div class="emarg-card">
            <div class="emarg-section-title">Bonjour <?php echo esc_html( $emarg->trainer_name ); ?>,</div>
            <p style="margin-bottom:16px;font-size:14px;color:#4b5d76;">Veuillez apposer votre signature ci-dessous pour ouvrir la séance. Une fois signé, vous accéderez à la liste des apprenants pour l'émargement.</p>

            <form id="trainer-sign-form" method="post" action="<?php echo esc_url( add_query_arg( array( 'acdc_emarg' => 'formateur', 'tok' => $token ), home_url('/') ) ); ?>">
                <input type="hidden" name="emarg_action" value="trainer_sign">
                <input type="hidden" name="sig_data" id="sig-data-trainer">

                <div class="emarg-section-title" style="margin-top:0">Votre signature</div>
                <canvas id="trainer-canvas"></canvas>
                <p class="emarg-hint">Dessinez votre signature avec le doigt ou la souris.</p>
                <div class="emarg-actions">
                    <button type="button" class="btn btn-soft btn-sm" onclick="trainerSig.clear()">Effacer</button>
                    <button type="submit" class="btn btn-primary" onclick="return submitTrainer()">✅ Signer et ouvrir la séance</button>
                </div>
            </form>

            <div class="emarg-qr-section">
                <div class="emarg-section-title">📱 Signer depuis votre mobile</div>
                <p style="font-size:13px;color:#6b7280;margin-bottom:12px;">Scannez ce QR code avec votre smartphone pour signer sur votre téléphone.</p>
                <div id="qr-trainer"></div>
                <p class="emarg-hint" style="margin-top:8px"><?php echo esc_url( $this->core->get_public_url('formateur', $token) ); ?></p>
            </div>
        </div>
        <script>
        var trainerSig = initCanvas('trainer-canvas');
        function submitTrainer() {
            if (!trainerSig.hasSig()) { alert('Veuillez dessiner votre signature avant de valider.'); return false; }
            document.getElementById('sig-data-trainer').value = trainerSig.getDataURL();
            return true;
        }
        // QR code
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof qrcode === 'function') {
                var qr = qrcode(0, 'M');
                qr.addData(<?php echo wp_json_encode( $this->core->get_public_url('formateur', $token) ); ?>);
                qr.make();
                var el = document.getElementById('qr-trainer');
                if (el) { el.innerHTML = qr.createImgTag(4, 4); }
            }
        });
        </script>
        <?php
    }

    /* -----------------------------------------------------------------------
     * Mode 2 : liste apprenants
     * -------------------------------------------------------------------- */
    private function render_learner_list( $token ) {
        $emarg = $this->core->get_by_list_token( $token );
        if ( ! $emarg ) {
            echo '<div class="emarg-alert emarg-alert-error">Lien invalide ou expiré.</div>';
            return;
        }

        global $wpdb;
        $session_table = $wpdb->prefix . 'acdc_of_sessions';
        $session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$session_table} WHERE id = %d", $emarg->session_id ) );
        $session_label = $session ? ( $session->title ?: 'Séance #' . $session->id ) : 'Séance';

        $learners = $this->core->get_learners_for_emarg( $emarg->id );

        if ( isset( $_GET['just_signed'] ) ) {
            echo '<div class="emarg-alert emarg-alert-success">✅ Signature du formateur enregistrée. Les apprenants peuvent maintenant signer.</div>';
        }
        if ( isset( $_GET['signed'] ) ) {
            $late = (int) ( $_GET['late'] ?? 0 );
            $msg  = $late > 0 ? "✅ Signature enregistrée — Retard : {$late} minutes." : '✅ Signature enregistrée avec succès.';
            echo '<div class="emarg-alert emarg-alert-success">' . esc_html( $msg ) . '</div>';
        }
        ?>
        <div class="emarg-header">
            <h1>📋 Liste des apprenants</h1>
            <p><?php echo esc_html( $session_label ); ?> — <?php echo esc_html( $emarg->trainer_name ); ?></p>
        </div>
        <div class="emarg-card">
            <?php if ( 'signe' !== $emarg->trainer_status ) : ?>
            <div class="emarg-alert emarg-alert-info">⚠️ Le formateur n'a pas encore signé. <a href="<?php echo esc_url( $this->core->get_public_url('formateur', $emarg->trainer_token) ); ?>">Signer maintenant →</a></div>
            <?php endif; ?>

            <!-- QR code pour que les apprenants scannent -->
            <div style="text-align:center;margin-bottom:24px;">
                <div class="emarg-section-title">📱 QR Code émargement apprenants</div>
                <p style="font-size:13px;color:#6b7280;margin-bottom:12px;">Affichez ce QR code sur votre écran — chaque apprenant le scanne pour signer.</p>
                <div id="qr-learners" style="display:inline-block"></div>
            </div>

            <div class="emarg-section-title">Liste des apprenants</div>
            <ul class="learner-list">
                <?php foreach ( $learners as $lr ) :
                    $is_signed = 'signe' === $lr->status;
                    $is_absent = 'absent' === $lr->status;
                    $is_late   = $is_signed && (int) $lr->late_minutes > 0;
                    if ( $is_late ) { $status_class = 'status-late'; $status_label = 'Retard ' . $lr->late_minutes . ' min'; }
                    elseif ( $is_signed ) { $status_class = 'status-signed'; $status_label = 'Présent'; }
                    elseif ( $is_absent ) { $status_class = 'status-absent'; $status_label = 'Absent'; }
                    else { $status_class = 'status-pending'; $status_label = 'En attente'; }
                    $sign_url = $this->core->get_public_url( 'apprenant', $lr->sign_token );
                ?>
                <li>
                    <div>
                        <div class="learner-name"><?php echo esc_html( $lr->learner_name ); ?></div>
                        <?php if ( $is_signed && $lr->signed_at ) : ?>
                        <div class="learner-meta">Signé à <?php echo esc_html( wp_date( 'H\hi', strtotime( $lr->signed_at ) ) ); ?></div>
                        <?php endif; ?>
                        <?php if ( $is_signed && $lr->sig_url ) : ?>
                        <img src="<?php echo esc_url( $lr->sig_url ); ?>" class="sig-preview" alt="Signature">
                        <?php endif; ?>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
                        <span class="learner-status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
                        <?php if ( ! $is_signed && ! $is_absent ) : ?>
                        <a href="<?php echo esc_url( $sign_url ); ?>" class="learner-sign-btn">Signer</a>
                        <?php if ( $lr->learner_email ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="margin:0">
                            <?php wp_nonce_field( 'acdc_emarg_send_' . $lr->id ); ?>
                            <input type="hidden" name="action" value="acdc_emarg_send_learner_email">
                            <input type="hidden" name="learner_row_id" value="<?php echo esc_attr( $lr->id ); ?>">
                            <button type="submit" class="btn btn-soft btn-sm">✉ Envoyer lien</button>
                        </form>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php if ( ! $is_absent ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="margin:0"
                              onsubmit="return confirm('Marquer <?php echo esc_js( $lr->learner_name ); ?> comme absent ?')">
                            <?php wp_nonce_field( 'acdc_emarg_absent_' . $lr->id ); ?>
                            <input type="hidden" name="action" value="acdc_emarg_mark_absent">
                            <input type="hidden" name="learner_row_id" value="<?php echo esc_attr( $lr->id ); ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Marquer absent</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof qrcode === 'function') {
                var qr = qrcode(0, 'M');
                qr.addData(<?php echo wp_json_encode( $this->core->get_public_url('liste', $token) ); ?>);
                qr.make();
                var el = document.getElementById('qr-learners');
                if (el) { el.innerHTML = qr.createImgTag(4, 4); }
            }
        });
        </script>
        <?php
    }

    /* -----------------------------------------------------------------------
     * Mode 3 : signature apprenant
     * -------------------------------------------------------------------- */
    private function render_learner_sign( $token ) {
        $learner = $this->core->get_learner_by_sign_token( $token );
        if ( ! $learner ) {
            echo '<div class="emarg-alert emarg-alert-error">Lien invalide ou expiré.</div>';
            return;
        }
        if ( 'signe' === $learner->status ) {
            echo '<div class="emarg-alert emarg-alert-success">✅ Vous avez déjà signé. Merci !</div>';
            return;
        }

        global $wpdb;
        $session_table = $wpdb->prefix . 'acdc_of_sessions';
        $session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$session_table} WHERE id = %d", $learner->session_id ) );
        $session_label = $session ? ( $session->title ?: 'Séance #' . $session->id ) : 'Séance';

        if ( isset( $_GET['emarg_err'] ) ) {
            echo '<div class="emarg-alert emarg-alert-error">Veuillez dessiner votre signature avant de valider.</div>';
        }
        ?>
        <div class="emarg-header">
            <h1>✍ Signature émargement</h1>
            <p><?php echo esc_html( $session_label ); ?></p>
        </div>
        <div class="emarg-card">
            <p style="font-size:16px;font-weight:700;margin-bottom:16px;">Bonjour <?php echo esc_html( $learner->learner_name ); ?>,</p>
            <p style="font-size:14px;color:#4b5d76;margin-bottom:20px;">Veuillez signer ci-dessous pour confirmer votre présence à cette séance de formation.</p>

            <form id="learner-sign-form" method="post" action="<?php echo esc_url( add_query_arg( array( 'acdc_emarg' => 'apprenant', 'tok' => $token ), home_url('/') ) ); ?>">
                <input type="hidden" name="emarg_action" value="learner_sign">
                <input type="hidden" name="sig_data" id="sig-data-learner">

                <div class="emarg-section-title">Votre signature</div>
                <canvas id="learner-canvas"></canvas>
                <p class="emarg-hint">Dessinez votre signature avec le doigt (ou la souris sur ordinateur).</p>
                <div class="emarg-actions">
                    <button type="button" class="btn btn-soft btn-sm" onclick="learnerSig.clear()">Effacer</button>
                    <button type="submit" class="btn btn-primary" onclick="return submitLearner()">✅ Valider ma présence</button>
                </div>
            </form>
        </div>
        <script>
        var learnerSig = initCanvas('learner-canvas');
        function submitLearner() {
            if (!learnerSig.hasSig()) { alert('Veuillez dessiner votre signature avant de valider.'); return false; }
            document.getElementById('sig-data-learner').value = learnerSig.getDataURL();
            return true;
        }
        </script>
        <?php
    }
}
