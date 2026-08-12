<?php
/**
 * ACDC Signature — Public
 *
 * Shortcode [acdc_signature_page], formulaire mobile de signature,
 * handler de soumission (avec rate limiting et vérifications).
 *
 * @package ACDC_Formation_SAAS
 * @since   3.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ACDC_Sig_Public {

    private ACDC_Sig_Core  $core;
    private ACDC_Sig_PDF   $pdf;
    private ACDC_Sig_Email $email;

    public function __construct( ACDC_Sig_Core $core, ACDC_Sig_PDF $pdf, ACDC_Sig_Email $email ) {
        $this->core  = $core;
        $this->pdf   = $pdf;
        $this->email = $email;
    }

    /* -----------------------------------------------------------------------
     * Template blank
     * -------------------------------------------------------------------- */

    public function maybe_use_blank_template( $template ) {
        $page_id = (int) get_option( ACDC_Sig_Core::OPTION_PAGE, 0 );
        if ( $page_id && is_page( $page_id ) ) {
            $blank = ACDC_OF_SAAS_DIR . 'templates/acdc-blank-template.php';
            if ( file_exists( $blank ) ) {
                return $blank;
            }
        }
        return $template;
    }

    /* -----------------------------------------------------------------------
     * Shortcode
     * -------------------------------------------------------------------- */

    public function render_shortcode( $atts ) {
        $token = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';

        if ( empty( $token ) ) {
            return $this->message( 'error', 'Lien de signature invalide ou manquant.' );
        }

        // Retour immédiat après signature — afficher la confirmation
        if ( ! empty( $_GET['signed'] ) && '1' === (string) $_GET['signed'] ) {
            return $this->render_signed_confirmation( $token );
        }

        global $wpdb;
        $request = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->core->table_requests} WHERE token = %s", $token )
        );

        if ( ! $request ) {
            return $this->message( 'error', 'Ce lien de signature est introuvable.' );
        }

        if ( 'signe' === $request->status ) {
            return $this->message( 'success', 'Ce document a déjà été signé. Merci !' );
        }

        if ( in_array( $request->status, array( 'expire', 'supprime', 'refuse' ), true ) ) {
            return $this->message( 'error', "Ce lien n'est plus valide. Veuillez contacter votre organisme de formation." );
        }

        if ( $request->expires_at && strtotime( $request->expires_at ) < current_time( 'timestamp' ) ) {
            $wpdb->update( $this->core->table_requests, array( 'status' => 'expire', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $request->id ) );
            $this->core->log_event( $request->id, 'expired', 'Lien expiré à l\'ouverture.' );
            return $this->message( 'error', 'Ce lien a expiré. Veuillez contacter votre organisme de formation.' );
        }

        // OTP e-mail — vérification identité niveau renforcé
        if ( ACDC_Sig_Core::LEVEL_RENFORCE === $request->sig_level && ! $this->core->is_otp_verified( $request->token ) ) {
            // Premier accès : passer le statut à "ouvert" et envoyer automatiquement le code
            if ( 'envoye' === $request->status ) {
                $wpdb->update( $this->core->table_requests, array( 'status' => 'ouvert', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $request->id ) );
                $this->core->log_event( $request->id, 'opened', 'Lien ouvert.', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '' );
                $otp = $this->core->generate_otp();
                $this->core->store_otp( $request->id, $otp );
                $this->email->send_otp_email( $request->id, $otp );
            }
            return $this->render_otp_form( $request );
        }

        if ( 'envoye' === $request->status ) {
            $wpdb->update( $this->core->table_requests, array( 'status' => 'ouvert', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $request->id ) );
            $this->core->log_event( $request->id, 'opened', 'Lien ouvert.', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '' );
        }

        return $this->render_form( $request );
    }

    /* -----------------------------------------------------------------------
     * Formulaire OTP — saisie du code de vérification (niveau renforcé)
     * -------------------------------------------------------------------- */

    private function render_otp_form( $request ) {
        $s          = $this->core->get_settings();
        $doc_label  = ACDC_Sig_Core::DOC_TYPES[ $request->doc_type ] ?? $request->doc_type;
        $btn_color  = $s['btn_color'] ?: '#c9a84c';
        $action_url = esc_url( admin_url( 'admin-post.php' ) );
        $resend_url = esc_url( admin_url( 'admin-post.php' ) );

        // Masquer partiellement l'e-mail pour l'affichage
        $email       = $request->signer_email;
        $at_pos      = strpos( $email, '@' );
        $local       = $at_pos ? substr( $email, 0, $at_pos ) : $email;
        $domain_part = $at_pos ? substr( $email, $at_pos ) : '';
        $masked      = ( strlen( $local ) > 2 )
            ? substr( $local, 0, 2 ) . str_repeat( '*', min( strlen( $local ) - 2, 4 ) ) . $domain_part
            : $local . $domain_part;

        // Messages d'erreur
        $error = '';
        if ( isset( $_GET['otp_error'] ) ) {
            if ( 'invalid' === $_GET['otp_error'] ) {
                $error = '❌ Code incorrect. Vérifiez le code reçu par e-mail et réessayez.';
            } elseif ( 'expired' === $_GET['otp_error'] ) {
                $error = '⏳ Ce code a expiré. Cliquez sur « Renvoyer un code » pour en recevoir un nouveau.';
            }
        }

        ob_start();
        ?>
        <style>
        *{box-sizing:border-box}body{margin:0;font-family:'Lato',sans-serif;background:#faf9f7}
        .sig-wrap{max-width:520px;margin:0 auto;padding:20px 16px 40px}
        .sig-hd{background:#1a2744;color:#fff;border-radius:8px 8px 0 0;padding:20px;text-align:center}
        .sig-hd h1{margin:0;font-size:20px;color:#fff !important}.sig-hd p{margin:6px 0 0;opacity:.8;font-size:14px;color:#fff}
        .sig-badge{display:inline-block;margin-top:8px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);border-radius:12px;padding:2px 10px;font-size:11px;letter-spacing:.5px}
        .sig-body{background:#fff;border:1px solid #e2e2e2;border-top:none;border-radius:0 0 8px 8px;padding:28px}
        .otp-info{text-align:center;color:#4b5d76;font-size:14px;margin:0 0 24px;line-height:1.5}
        .otp-info strong{color:#1a2744}
        .otp-field{text-align:center;margin-bottom:20px}
        .otp-field input{font-size:28px;font-weight:900;letter-spacing:8px;font-family:monospace;text-align:center;width:200px;padding:14px 12px;border:2px solid #c9a84c;border-radius:8px;color:#1a2744;outline:none}
        .otp-field input:focus{border-color:#1a2744;box-shadow:0 0 0 3px rgba(26,39,68,.1)}
        .otp-error{background:#f8d7da;border:1px solid #f5c6cb;border-radius:6px;padding:10px 14px;font-size:13px;color:#721c24;margin-bottom:16px;text-align:center}
        .otp-submit{display:block;width:100%;padding:14px;background:<?php echo esc_attr( $btn_color ); ?>;color:#fff;border:none;border-radius:6px;font-size:16px;font-weight:700;cursor:pointer}
        .otp-submit:hover{filter:brightness(.92)}
        .otp-resend{text-align:center;margin-top:14px}
        .otp-resend form{display:inline}
        .otp-resend button{background:none;border:none;color:#1a2744;font-size:13px;cursor:pointer;text-decoration:underline;padding:0}
        .otp-resend button:hover{color:#c9a84c}
        </style>

        <div class="sig-wrap">
            <div class="sig-hd">
                <h1>🔒 Vérification de votre identité</h1>
                <p><?php echo esc_html( $doc_label ); ?></p>
                <span class="sig-badge">Signature renforcée — double authentification</span>
            </div>
            <div class="sig-body">
                <?php if ( $error ) : ?>
                <div class="otp-error"><?php echo esc_html( $error ); ?></div>
                <?php endif; ?>

                <p class="otp-info">
                    Bonjour <strong><?php echo esc_html( $request->signer_name ); ?></strong>,<br>
                    un code à 6 chiffres vient d'être envoyé à<br>
                    <strong><?php echo esc_html( $masked ); ?></strong>
                </p>

                <form method="post" action="<?php echo $action_url; ?>">
                    <?php wp_nonce_field( 'acdc_sig_otp_verify_' . $request->token ); ?>
                    <input type="hidden" name="action" value="acdc_sig_otp_verify">
                    <input type="hidden" name="sig_token" value="<?php echo esc_attr( $request->token ); ?>">

                    <div class="otp-field">
                        <input type="text"
                               name="otp_code"
                               id="otp-code-input"
                               inputmode="numeric"
                               pattern="[0-9]{6}"
                               maxlength="6"
                               autocomplete="one-time-code"
                               placeholder="——————"
                               required
                               autofocus>
                    </div>

                    <button type="submit" class="otp-submit">✅ Valider et accéder au document</button>
                </form>

                <div class="otp-resend">
                    <form method="post" action="<?php echo $resend_url; ?>">
                        <?php wp_nonce_field( 'acdc_sig_otp_resend_' . $request->token ); ?>
                        <input type="hidden" name="action" value="acdc_sig_otp_resend">
                        <input type="hidden" name="sig_token" value="<?php echo esc_attr( $request->token ); ?>">
                        <button type="submit">↻ Renvoyer un code</button>
                    </form>
                </div>
            </div>
        </div>

        <script>
        (function(){
            var inp = document.getElementById('otp-code-input');
            if ( ! inp ) { return; }
            var form = inp.closest('form');
            /* ACDC 3.25.154 — Verrou anti double soumission.
               L'auto-submit au 6e chiffre, suivi du clic de l'utilisateur sur
               « Valider », envoyait DEUX POST quasi simultanés. Le premier validait
               le code (verify_otp est à usage unique et le consomme), le second ne
               trouvait plus rien et repartait en otp_error=expired — message trompeur
               affiché au signataire alors que son identité venait d'être vérifiée. */
            var submitted = false;
            function submitOnce() {
                if ( submitted ) { return; }
                submitted = true;
                if ( form ) {
                    var btn = form.querySelector('button[type=submit], input[type=submit]');
                    if ( btn ) { btn.disabled = true; }
                    form.submit();
                }
            }
            if ( form ) {
                form.addEventListener('submit', function( e ){
                    if ( submitted ) { e.preventDefault(); return; }
                    submitted = true;
                });
            }
            inp.addEventListener('input', function(){
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
                if ( this.value.length === 6 ) {
                    submitOnce();
                }
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /* -----------------------------------------------------------------------
     * Formulaire de signature mobile
     * -------------------------------------------------------------------- */

    private function render_form( $request ) {
        // Refus via GET
        if ( isset( $_GET['refuse'] ) && '1' === $_GET['refuse'] ) {
            // Protection : exiger un nonce valide pour empêcher un refus déclenché
            // par un simple GET (pré-chargeur de lien / antivirus e-mail).
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'acdc_sig_refuse_' . $request->token ) ) {
                return $this->message( 'error', "Lien de refus invalide ou expiré. Veuillez utiliser le bouton « Refuser » de la page de signature." );
            }
            global $wpdb;
            $wpdb->update( $this->core->table_requests, array( 'status' => 'refuse', 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $request->id ) );
            $this->core->log_event( $request->id, 'refused', 'Refus par le signataire.', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '' );
            $this->email->notify_admin( $request->id, 'refuse' );
            return $this->message( 'info', "Vous avez refusé de signer ce document. L'organisme de formation a été notifié." );
        }

        $s           = $this->core->get_settings();
        $doc_label   = ACDC_Sig_Core::DOC_TYPES[ $request->doc_type ] ?? $request->doc_type;
        $btn_color   = $s['btn_color']    ?: '#c9a84c';
        $btn_label   = $s['btn_label']    ?: 'Signer le document';
        $legal       = $s['legal_notice'] ?: "En apposant ma signature, je confirme avoir pris connaissance du document ci-dessus et en accepte le contenu.";
        $action_url  = esc_url( admin_url( 'admin-post.php' ) );
        $is_renforce = ( ACDC_Sig_Core::LEVEL_RENFORCE === $request->sig_level );

        /* Correction audit MOYEN : valider l'URL du document avant de la rendre dans une iframe.
           ACDC 3.25.151 : on ne pointe plus sur le fichier statique — le dossier des contrats
           est désormais interdit d'accès direct (403), ce qui rendait l'aperçu illisible. Le
           document transite par un service autorisé par le jeton de signature. */
        $safe_doc_url = ( $request->doc_url && $this->core->is_safe_doc_url( $request->doc_url ) )
                        ? $this->get_doc_view_url( $request->token )
                        : '';

        $refuse_url = esc_url( wp_nonce_url( add_query_arg( array( 'sig' => $request->token, 'refuse' => '1' ), $this->core->get_signature_page_url() ), 'acdc_sig_refuse_' . $request->token ) );

        ob_start();
        ?>
        <style>
        *{box-sizing:border-box}body{margin:0;font-family:'Lato',sans-serif;background:#faf9f7}
        .sig-wrap{max-width:600px;margin:0 auto;padding:20px 16px 40px}
        .sig-hd{background:#1a2744;color:#fff;border-radius:8px 8px 0 0;padding:20px;text-align:center}
        .sig-hd h1{margin:0;font-size:20px;color:#fff !important}.sig-hd p{margin:6px 0 0;opacity:.8;font-size:14px;color:#fff}
        .sig-badge{display:inline-block;margin-top:8px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);border-radius:12px;padding:2px 10px;font-size:11px;letter-spacing:.5px}
        .sig-body{background:#fff;border:1px solid #e2e2e2;border-top:none;border-radius:0 0 8px 8px;padding:24px}
        .sig-info{background:#f0f4ff;border-left:3px solid #1a2744;padding:10px 14px;border-radius:0 4px 4px 0;font-size:13px;margin-bottom:16px}
        .sig-notice{background:#fff8e8;border:1px solid #e8c97a;border-radius:6px;padding:10px 14px;font-size:12px;color:#7a5c00;margin-bottom:16px}
        .sig-doc{border:1px solid #e2e2e2;border-radius:6px;overflow:hidden;margin-bottom:20px}
        .sig-doc iframe{width:100%;height:380px;border:none;display:block}
        .sig-doc-link{background:#f5f5f5;padding:10px 14px;font-size:13px}
        .sig-doc-link a{color:#1a2744;font-weight:600}
        .sig-step{background:#faf9f7;border:1px solid #e8e3d8;border-radius:6px;padding:16px;margin-bottom:16px}
        .sig-step-title{font-size:13px;font-weight:700;color:#1a2744;margin:0 0 10px;display:flex;align-items:center;gap:8px}
        .sig-step-num{background:#c9a84c;color:#fff;width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0}
        .sig-canvas-wrap{position:relative;border:2px dashed #c9a84c;border-radius:6px;background:#fff;margin:8px 0 4px;touch-action:none}
        .sig-canvas-wrap canvas{display:block;width:100%;cursor:crosshair}
        .sig-hint{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);color:#bbb;font-size:13px;pointer-events:none;text-align:center}
        .sig-clear{background:none;border:1px solid #ccc;color:#666;padding:5px 14px;border-radius:4px;cursor:pointer;font-size:12px;margin-top:4px}
        .sig-photo-zone:hover{background:#fdf8ef}
        .sig-photo-hint{color:#888;font-size:13px}
        .sig-photo-hint strong{display:block;color:#1a2744;font-size:14px;margin-bottom:4px}
        .sig-legal{font-size:12px;color:#666;background:#faf9f7;border:1px solid #eee;border-radius:4px;padding:10px 12px;margin:14px 0}
        .sig-confirm label{display:flex;align-items:flex-start;gap:8px;font-size:13px;cursor:pointer;margin-bottom:8px}
        .sig-confirm input{margin-top:2px;flex-shrink:0}
        .sig-submit{display:block;width:100%;padding:14px;background:<?php echo esc_attr( $btn_color ); ?>;color:#fff;border:none;border-radius:6px;font-size:16px;font-weight:700;cursor:pointer;margin-top:18px}
        .sig-submit:disabled{opacity:.5;cursor:not-allowed}
        .sig-submit:hover:not(:disabled){filter:brightness(.92)}
        .sig-refuse{text-align:center;margin-top:14px}
        .sig-refuse a{color:#c00;font-size:12px}
        </style>

        <div class="sig-wrap">
            <div class="sig-hd">
                <h1>✍ Signature électronique</h1>
                <p><?php echo esc_html( $doc_label ); ?></p>
                <span class="sig-badge"><?php echo $is_renforce ? '🔒 Niveau renforcé' : '✅ Niveau simple'; ?></span>
            </div>
            <div class="sig-body">
                <div class="sig-info">Bonjour <strong><?php echo esc_html( $request->signer_name ); ?></strong>, veuillez lire attentivement le document ci-dessous avant de le signer.</div>

                <?php if ( $is_renforce ) : ?>
                <div class="sig-notice">🔒 Ce document requiert une <strong>signature renforcée</strong> : votre identité a été vérifiée par code e-mail (double authentification).</div>
                <?php endif; ?>

                <?php if ( $safe_doc_url ) : ?>
                <div class="sig-doc">
                    <iframe src="<?php echo esc_url( $safe_doc_url ); ?>" title="Document à signer"></iframe>
                    <div class="sig-doc-link"><a href="<?php echo esc_url( $safe_doc_url ); ?>" target="_blank">📄 Ouvrir dans un nouvel onglet</a></div>
                </div>
                <?php else : ?>
                <div class="sig-doc" style="padding:20px;text-align:center;color:#666"><p>Aucun document joint à cette demande.</p></div>
                <?php endif; ?>

                <form method="post" action="<?php echo $action_url; ?>" id="sig-form" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'acdc_sig_submit_' . $request->token ); ?>
                    <input type="hidden" name="action" value="acdc_sig_submit">
                    <input type="hidden" name="sig_token" value="<?php echo esc_attr( $request->token ); ?>">
                    <input type="hidden" name="sig_data" id="sig-data-input">

                    <div class="sig-step">
                        <p class="sig-step-title"><span class="sig-step-num">1</span>Votre signature manuscrite</p>
                        <p style="font-size:12px;color:#666;margin:0 0 4px">Dessinez votre signature ci-dessous (doigt sur mobile, souris sur ordinateur).</p>
                        <div class="sig-canvas-wrap" id="sig-canvas-wrap">
                            <canvas id="sig-canvas" height="160"></canvas>
                            <div class="sig-hint" id="sig-hint">Signez ici</div>
                        </div>
                        <button type="button" class="sig-clear" id="sig-clear-btn">↺ Effacer</button>
                    </div>

                    <?php
                    /* ACDC 3.25.236 — LA MENTION « BON POUR ACCORD » SUR LE DEVIS.
                       Une signature manuscrite prouve qui a signé ; la mention
                       recopiée prouve ce que l'on a accepté. Sur un devis, c'est
                       elle qui vaut acceptation de l'offre, et l'usage veut
                       qu'elle soit écrite de la main du signataire. Elle
                       n'existait nulle part : le devis portait un cadre
                       « Bon pour accord » vide de toute mention.
                       Deux façons de la produire, parce que les deux ont cours :
                       au clavier, ou à la main sur un second canevas. */
                    $is_quote = ( 'devis' === (string) $request->doc_type );
                    ?>
                    <?php if ( $is_quote ) : ?>
                    <div class="sig-step">
                        <p class="sig-step-title"><span class="sig-step-num">2</span>La mention « Bon pour accord »</p>
                        <p style="font-size:12px;color:#666;margin:0 0 8px">Recopiez la mention ci-dessous. Elle sera imprimée sur le devis, à côté de votre signature.</p>
                        <p style="font-size:13px;font-weight:700;color:#1a2744;margin:0 0 8px">« Bon pour accord »</p>
                        <input type="text" name="accord_mention" id="sig-accord-input" maxlength="180"
                               placeholder="Recopiez ici : Bon pour accord"
                               style="width:100%;height:42px;border:1px solid #d6dbe4;border-radius:8px;padding:0 12px;font-size:14px;box-sizing:border-box;">
                        <p style="font-size:12px;color:#666;margin:12px 0 4px">Ou écrivez-la à la main :</p>
                        <div class="sig-canvas-wrap" id="sig-accord-wrap">
                            <canvas id="sig-accord-canvas" height="120"></canvas>
                            <div class="sig-hint" id="sig-accord-hint">Écrivez « Bon pour accord » ici</div>
                        </div>
                        <button type="button" class="sig-clear" id="sig-accord-clear">↺ Effacer la mention manuscrite</button>
                        <input type="hidden" name="accord_signature" id="sig-accord-data">
                        <p id="sig-accord-error" style="display:none;color:#b32d2e;font-size:12px;margin:8px 0 0;">Recopiez la mention au clavier, ou écrivez-la à la main.</p>
                    </div>
                    <?php endif; ?>

                    <div class="sig-legal"><?php echo esc_html( $legal ); ?></div>

                    <div class="sig-confirm">
                        <label><input type="checkbox" id="sig-consent" required> J'ai lu le document et j'accepte de le signer électroniquement.</label>
                    </div>

                    <button type="submit" class="sig-submit" id="sig-submit-btn" disabled><?php echo esc_html( $btn_label ); ?></button>
                </form>

                <div class="sig-refuse"><a href="<?php echo $refuse_url; ?>" onclick="return confirm('Êtes-vous sûr de vouloir refuser de signer ce document ?')">Refuser de signer</a></div>
            </div>
        </div>

        <script>
        (function(){
            var isR=<?php echo $is_renforce ? 'true' : 'false'; ?>;
            var c=document.getElementById('sig-canvas'),ctx=c.getContext('2d'),w=document.getElementById('sig-canvas-wrap');
            var hint=document.getElementById('sig-hint'),inp=document.getElementById('sig-data-input');
            var btn=document.getElementById('sig-submit-btn'),ck=document.getElementById('sig-consent');
            var clr=document.getElementById('sig-clear-btn');
            /* ACDC 3.25.236 — UNE SIGNATURE PIXELLISÉE N'EST PAS UN DÉTAIL.
               Le canevas fixait sa mémoire graphique à la largeur CSS, sans tenir
               compte de la densité de l'écran : sur un écran moderne — deux à
               trois pixels physiques pour un pixel CSS — le tracé était dessiné
               en basse définition puis agrandi. D'où l'escalier visible à
               l'écran, et pire encore dans le PDF, où l'image est réétirée.
               Le tracé lui-même n'aidait pas : des segments droits reliant les
               points bruts du pointeur donnent une ligne brisée, jamais une
               écriture. On relie désormais les points par des courbes passant
               par leurs milieux — c'est ce qui fait la fluidité d'une signature.
               Une signature est une pièce probante : elle doit ressembler à
               celle de la personne, sinon elle se conteste. */
            var drawing=false,hasSig=false,dpr=1,lastX=0,lastY=0;
            function resize(){
              var d=hasSig?c.toDataURL():'',cw=w.offsetWidth||600,ch=160;
              dpr=Math.min(3,Math.max(1,window.devicePixelRatio||1));
              c.width=Math.round(cw*dpr);c.height=Math.round(ch*dpr);
              c.style.width=cw+'px';c.style.height=ch+'px';
              ctx.setTransform(dpr,0,0,dpr,0,0);
              ctx.strokeStyle='#1a2744';ctx.lineWidth=2.2;ctx.lineCap=ctx.lineJoin='round';
              if(d){var i=new Image();i.onload=function(){ctx.drawImage(i,0,0,cw,ch);};i.src=d;}
            }
            resize();window.addEventListener('resize',function(){setTimeout(resize,100);});
            function pos(e){var r=c.getBoundingClientRect(),src=(e.touches&&e.touches[0])?e.touches[0]:e;
              return{x:(src.clientX-r.left),y:(src.clientY-r.top)};}
            function sd(e){e.preventDefault();drawing=true;var p=pos(e);lastX=p.x;lastY=p.y;
              /* Le point du posé vaut trait : un point ou un geste très court
                 doit laisser une trace. */
              ctx.beginPath();ctx.arc(p.x,p.y,ctx.lineWidth/2,0,6.2832);ctx.fillStyle='#1a2744';ctx.fill();
              hasSig=true;hint.style.display='none';upd();}
            function md(e){if(!drawing)return;e.preventDefault();var p=pos(e);
              var mx=(lastX+p.x)/2,my=(lastY+p.y)/2;
              ctx.beginPath();ctx.moveTo(lastX,lastY);ctx.quadraticCurveTo(lastX,lastY,mx,my);ctx.stroke();
              lastX=p.x;lastY=p.y;hasSig=true;upd();}
            function ed(){drawing=false;}
            c.addEventListener('mousedown',sd);c.addEventListener('mousemove',md);c.addEventListener('mouseup',ed);c.addEventListener('mouseleave',ed);
            c.addEventListener('touchstart',sd,{passive:false});c.addEventListener('touchmove',md,{passive:false});c.addEventListener('touchend',ed);
            clr.addEventListener('click',function(){ctx.save();ctx.setTransform(1,0,0,1,0,0);ctx.clearRect(0,0,c.width,c.height);ctx.restore();hasSig=false;hint.style.display='';inp.value='';upd();});
            ck.addEventListener('change',upd);
            /* ACDC 3.25.236 — Le second canevas, pour la mention manuscrite du
               devis. Il partage la logique du premier : même densité d'écran,
               mêmes courbes. Écrire « Bon pour accord » demande de la finesse —
               c'est du texte, pas un paraphe. */
            var accordCanvas=document.getElementById('sig-accord-canvas');
            var accordInput=document.getElementById('sig-accord-input');
            var accordData=document.getElementById('sig-accord-data');
            var accordErr=document.getElementById('sig-accord-error');
            var accordHas=false;
            if(accordCanvas){
              var ac=accordCanvas,actx=ac.getContext('2d'),aw=document.getElementById('sig-accord-wrap');
              var ahint=document.getElementById('sig-accord-hint'),adraw=false,alx=0,aly=0;
              function aresize(){
                var d=accordHas?ac.toDataURL():'',cw=aw.offsetWidth||600,ch=120;
                ac.width=Math.round(cw*dpr);ac.height=Math.round(ch*dpr);
                ac.style.width=cw+'px';ac.style.height=ch+'px';
                actx.setTransform(dpr,0,0,dpr,0,0);
                actx.strokeStyle='#1a2744';actx.fillStyle='#1a2744';actx.lineWidth=2;actx.lineCap=actx.lineJoin='round';
                if(d){var i=new Image();i.onload=function(){actx.drawImage(i,0,0,cw,ch);};i.src=d;}
              }
              aresize();window.addEventListener('resize',function(){setTimeout(aresize,100);});
              function apos(e){var r=ac.getBoundingClientRect(),src=(e.touches&&e.touches[0])?e.touches[0]:e;
                return{x:(src.clientX-r.left),y:(src.clientY-r.top)};}
              function asd(e){e.preventDefault();adraw=true;var p=apos(e);alx=p.x;aly=p.y;
                actx.beginPath();actx.arc(p.x,p.y,actx.lineWidth/2,0,6.2832);actx.fill();
                accordHas=true;ahint.style.display='none';upd();}
              function amd(e){if(!adraw)return;e.preventDefault();var p=apos(e);
                var mx=(alx+p.x)/2,my=(aly+p.y)/2;
                actx.beginPath();actx.moveTo(alx,aly);actx.quadraticCurveTo(alx,aly,mx,my);actx.stroke();
                alx=p.x;aly=p.y;accordHas=true;upd();}
              function aed(){adraw=false;}
              ac.addEventListener('mousedown',asd);ac.addEventListener('mousemove',amd);
              ac.addEventListener('mouseup',aed);ac.addEventListener('mouseleave',aed);
              ac.addEventListener('touchstart',asd,{passive:false});ac.addEventListener('touchmove',amd,{passive:false});
              ac.addEventListener('touchend',aed);ac.addEventListener('touchcancel',aed);
              document.getElementById('sig-accord-clear').addEventListener('click',function(){
                actx.save();actx.setTransform(1,0,0,1,0,0);actx.clearRect(0,0,ac.width,ac.height);actx.restore();
                accordHas=false;ahint.style.display='';accordData.value='';upd();});
              if(accordInput){accordInput.addEventListener('input',upd);}
            }

            function accordOk(){
              if(!accordCanvas){return true;}
              var typed=accordInput&&accordInput.value?accordInput.value.trim():'';
              return ( typed.length>2 || accordHas );
            }
            function upd(){
              btn.disabled=!(hasSig&&ck.checked&&accordOk());
              if(accordErr){accordErr.style.display=(hasSig&&ck.checked&&!accordOk())?'block':'none';}
            }
            document.getElementById('sig-form').addEventListener('submit',function(){
              // Fond blanc avant capture pour éviter le fond noir dans le certificat PDF
              var tmp=document.createElement('canvas');tmp.width=c.width;tmp.height=c.height;
              var tctx=tmp.getContext('2d');tctx.fillStyle='#ffffff';tctx.fillRect(0,0,tmp.width,tmp.height);
              tctx.drawImage(c,0,0);inp.value=tmp.toDataURL('image/png');
              if(accordCanvas&&accordHas){
                var atmp=document.createElement('canvas');atmp.width=accordCanvas.width;atmp.height=accordCanvas.height;
                var atctx=atmp.getContext('2d');atctx.fillStyle='#ffffff';atctx.fillRect(0,0,atmp.width,atmp.height);
                atctx.drawImage(accordCanvas,0,0);accordData.value=atmp.toDataURL('image/png');
              }
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /* -----------------------------------------------------------------------
     * Handler : soumission de la signature
     * Corrections audit : rate limiting, taille sig_data, vérification écriture PDF
     * -------------------------------------------------------------------- */

    public function handle_submit() {
        $token    = sanitize_text_field( wp_unslash( $_POST['sig_token'] ?? '' ) );
        $sig_data = wp_unslash( $_POST['sig_data'] ?? '' );

        if ( empty( $token ) ) {
            wp_safe_redirect( $this->core->get_signature_page_url() . '?sig_error=token' );
            exit;
        }

        check_admin_referer( 'acdc_sig_submit_' . $token );

        $ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );

        // Correction audit CRITIQUE : rate limiting par IP + token
        if ( ! $this->core->check_rate_limit( $token, $ip ) ) {
            wp_safe_redirect( $this->core->get_signature_page_url() . '?sig=' . urlencode( $token ) . '&sig_error=flood' );
            exit;
        }

        // Correction audit MOYEN : vérifier la taille du payload sig_data
        if ( empty( $sig_data ) || strlen( $sig_data ) > ACDC_Sig_Core::MAX_SIG_DATA_BYTES ) {
            wp_safe_redirect( $this->core->get_signature_page_url() . '?sig=' . urlencode( $token ) . '&sig_error=vide' );
            exit;
        }

        if ( 0 !== strpos( $sig_data, 'data:image/' ) ) {
            wp_safe_redirect( $this->core->get_signature_page_url() . '?sig=' . urlencode( $token ) . '&sig_error=vide' );
            exit;
        }

        global $wpdb;
        $request = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->core->table_requests} WHERE token = %s", $token )
        );

        if ( ! $request || 'signe' === $request->status ) {
            wp_safe_redirect( $this->core->get_signature_page_url() . '?sig=' . urlencode( $token ) );
            exit;
        }

        // Défense en profondeur : pour le niveau renforcé, revérifier l'OTP côté
        // handler avant de sceller (l'affichage ne suffit pas à garantir la vérif).
        if ( ACDC_Sig_Core::LEVEL_RENFORCE === $request->sig_level && ! $this->core->is_otp_verified( $request->token ) ) {
            wp_safe_redirect( $this->core->get_signature_page_url() . '?sig=' . urlencode( $token ) . '&sig_error=otp' );
            exit;
        }

        $ua = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' );

        // ACDC 3.25.113 — verrou atomique anti double-signature (TOCTOU).
        // On pose le statut « signe » (+ horodatage / identité signataire) via un UPDATE
        // conditionnel `WHERE status <> 'signe'` AVANT toute génération de PDF. Deux
        // soumissions concurrentes du même token : une seule verra rows_affected >= 1 ;
        // la perdante s'arrête proprement sans régénérer ni réécrire quoi que ce soit.
        $now     = current_time( 'mysql' );
        $claimed = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->core->table_requests}
                    SET status = 'signe', signed_at = %s, signer_ip = %s, signer_ua = %s, updated_at = %s
                  WHERE id = %d AND status <> 'signe'",
                $now,
                $ip,
                $ua,
                $now,
                $request->id
            )
        );

        // ACDC 3.25.113 — verrou atomique anti double-signature (TOCTOU).
        // Aucune ligne affectée => une autre requête a déjà scellé la demande entre la
        // garde initiale (~l.414) et ici : on redirige comme la garde initiale, sans
        // régénérer le PDF ni réécrire les empreintes.
        if ( $claimed < 1 ) {
            wp_safe_redirect( $this->core->get_signature_page_url() . '?sig=' . urlencode( $token ) );
            exit;
        }

        $sig_img_path  = $this->pdf->save_signature_image( $sig_data, $request->id );

        /* ACDC 3.25.236 — La mention « Bon pour accord » du devis est scellée
           AVANT la régénération du document : c'est elle qui doit s'y imprimer.
           Elle est enregistrée sur le devis, jamais sur la demande de signature
           — la demande disparaît, le devis reste, et c'est le devis qui fait
           preuve de l'acceptation. */
        $this->maybe_store_accord_mention( $request );

        $pdf_result      = $this->pdf->generate_audit_pdf( $request, $sig_img_path );
        $signed_doc_url  = $pdf_result['url']  ?? '';
        $signed_doc_path = $pdf_result['path'] ?? '';

        // Scellement à valeur probante : empreinte SHA-256 du document source présenté au
        // signataire et du PDF signé généré. Toute altération ultérieure devient détectable.
        $doc_sha256        = \ACDC\Support\DocumentSeal::hashFile( (string) $request->doc_path );
        $signed_pdf_sha256 = \ACDC\Support\DocumentSeal::hashFile( (string) $signed_doc_path );

        // ACDC 3.25.113 — verrou atomique anti double-signature (TOCTOU).
        // Le statut a déjà été verrouillé ci-dessus ; on complète seulement les colonnes
        // dérivées du PDF (URL/chemin + empreintes) pour la ligne dont nous détenons le verrou.
        $wpdb->update(
            $this->core->table_requests,
            array(
                'signed_doc_url'    => $signed_doc_url,
                'signed_doc_path'   => $signed_doc_path,
                'doc_sha256'        => $doc_sha256,
                'signed_pdf_sha256' => $signed_pdf_sha256,
                'updated_at'        => current_time( 'mysql' ),
            ),
            array( 'id' => $request->id )
        );

        $details = 'Document signé.';
        if ( ACDC_Sig_Core::LEVEL_RENFORCE === $request->sig_level ) {
            $details .= ' Identité vérifiée par OTP e-mail.';
            $this->core->clear_otp_verified( $request->token );
        }
        if ( '' !== $doc_sha256 ) {
            $details .= ' Empreinte SHA-256 du document : ' . $doc_sha256 . '.';
        }
        $this->core->log_event( $request->id, 'signed', $details, $ip, $ua );

        // Lever le rate limit après signature réussie
        $this->core->clear_rate_limit( $token, $ip );

        $this->email->notify_admin( $request->id, 'signe' );
        $this->email->send_signed_doc_email( $request->id );
        do_action( 'acdc_sig_request_signed', $request->id, $request );

        wp_safe_redirect( $this->core->get_signature_page_url() . '?sig=' . urlencode( $token ) . '&signed=1' );
        exit;
    }

    /* -----------------------------------------------------------------------
     * Handler : validation du code OTP
     * -------------------------------------------------------------------- */

    public function handle_otp_verify() {
        $token    = sanitize_text_field( wp_unslash( $_POST['sig_token'] ?? '' ) );
        $otp_code = sanitize_text_field( wp_unslash( $_POST['otp_code']  ?? '' ) );

        if ( empty( $token ) ) {
            wp_safe_redirect( $this->core->get_signature_page_url() );
            exit;
        }

        check_admin_referer( 'acdc_sig_otp_verify_' . $token );

        global $wpdb;
        $request = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->core->table_requests} WHERE token = %s", $token )
        );

        if ( ! $request ) {
            wp_safe_redirect( $this->core->get_signature_page_url() );
            exit;
        }

        // Rate-limit anti brute-force OTP (clé distincte de la soumission).
        if ( ! $this->core->check_rate_limit( 'otp_' . $token, $_SERVER['REMOTE_ADDR'] ?? '' ) ) {
            wp_safe_redirect( add_query_arg( array( 'sig' => urlencode( $token ), 'otp_error' => 'ratelimited' ), $this->core->get_signature_page_url() ) );
            exit;
        }

        $result = $this->core->verify_otp( $request->id, $otp_code );

        /* ACDC 3.25.153 — verify_otp() est à USAGE UNIQUE : il supprime le code dès
           qu'il l'a validé. Toute soumission en double (double clic, renvoi de
           formulaire, requête rejouée) trouvait donc « plus de code » et repartait
           avec otp_error=expired — alors que l'identité venait d'être vérifiée et que
           l'étape suivante s'affichait normalement. Message d'erreur trompeur affiché
           dans la barre d'adresse du signataire.
           Si l'OTP est DÉJÀ marqué vérifié pour ce jeton, la vérification est un
           succès idempotent : on ne pose pas d'erreur. */
        if ( 'ok' !== $result && $this->core->is_otp_verified( $request->token, true ) ) {
            $result = 'ok';
        }
        /* Un code « consommé » sans vérification enregistrée reste un échec, mais on le
           présente comme périmé : c'est ce que l'utilisateur doit comprendre (redemander
           un code), et cela évite d'exposer un état interne dans l'URL. */
        if ( 'consumed' === $result ) {
            $result = 'expired';
        }

        if ( 'ok' === $result ) {
            $this->core->mark_otp_verified( $token );
            $this->core->log_event( $request->id, 'otp_verified', 'Code OTP validé.', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '' );
            wp_safe_redirect( add_query_arg( 'sig', urlencode( $token ), $this->core->get_signature_page_url() ) );
        } else {
            $this->core->log_event( $request->id, 'otp_failed', 'Échec OTP : ' . $result, $_SERVER['REMOTE_ADDR'] ?? '' );
            wp_safe_redirect( add_query_arg( array( 'sig' => urlencode( $token ), 'otp_error' => $result ), $this->core->get_signature_page_url() ) );
        }

        exit;
    }

    /* -----------------------------------------------------------------------
     * ACDC 3.25.151 — Service du document à signer, autorisé PAR LE JETON.
     *
     * Depuis le verrouillage du dossier des contrats (.htaccess), l'aperçu de la
     * page de signature renvoyait 403 : le signataire était invité à signer un
     * document qu'il ne pouvait pas lire — ce qui vide la signature de sa valeur.
     * Ce service est nécessairement NON authentifié au sens WordPress : c'est le
     * jeton de signature (64 caractères) qui fait autorité, exactement comme pour
     * l'accès à la page elle-même. Contrôles appliqués :
     *   - jeton valide et demande existante ;
     *   - demande non expirée, non révoquée, non refusée ;
     *   - pour le niveau renforcé, OTP préalablement vérifié ;
     *   - chemin confiné au dossier des téléversements (anti-traversée).
     * -------------------------------------------------------------------- */

    public function handle_serve_doc() {
        global $wpdb;

        $token = sanitize_text_field( wp_unslash( $_GET['sig'] ?? '' ) );
        if ( '' === $token ) {
            status_header( 404 );
            exit;
        }

        $request = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->core->table_requests} WHERE token = %s", $token )
        );
        if ( ! $request ) {
            status_header( 404 );
            exit;
        }

        // Demande close ou périmée : plus aucun accès au document.
        if ( in_array( (string) $request->status, array( 'refuse', 'revoque', 'expire' ), true ) ) {
            status_header( 403 );
            exit;
        }
        if ( ! empty( $request->expires_at ) && strtotime( (string) $request->expires_at ) < time() ) {
            status_header( 403 );
            exit;
        }

        // Niveau renforcé : le document ne s'ouvre qu'après vérification d'identité.
        if ( ACDC_Sig_Core::LEVEL_RENFORCE === $request->sig_level && ! $this->core->is_otp_verified( $request->token ) ) {
            status_header( 403 );
            exit;
        }

        // Chemin : on privilégie doc_path, sinon on le reconstruit depuis doc_url.
        $path = (string) $request->doc_path;
        if ( '' === $path || ! file_exists( $path ) ) {
            $uploads = wp_upload_dir();
            $path    = str_replace( trailingslashit( $uploads['baseurl'] ), trailingslashit( $uploads['basedir'] ), (string) $request->doc_url );
        }

        $uploads   = wp_upload_dir();
        $real_path = realpath( $path );
        $real_base = realpath( $uploads['basedir'] );
        if ( ! $real_path || ! $real_base || 0 !== strpos( $real_path, $real_base ) || ! is_file( $real_path ) ) {
            status_header( 404 );
            exit;
        }

        $ext  = strtolower( pathinfo( $real_path, PATHINFO_EXTENSION ) );
        $mime = ( 'pdf' === $ext ) ? 'application/pdf' : ( 'html' === $ext || 'htm' === $ext ? 'text/html; charset=UTF-8' : 'application/octet-stream' );

        while ( ob_get_level() ) { ob_end_clean(); }
        nocache_headers();
        header( 'Content-Type: ' . $mime );
        header( 'Content-Disposition: inline; filename="' . basename( $real_path ) . '"' );
        header( 'Content-Length: ' . filesize( $real_path ) );
        header( 'X-Content-Type-Options: nosniff' );
        readfile( $real_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        exit;
    }

    /** URL de consultation du document à signer, portée par le jeton. */
    public function get_doc_view_url( $token ) {
        return add_query_arg(
            array( 'action' => 'acdc_sig_doc', 'sig' => rawurlencode( (string) $token ) ),
            admin_url( 'admin-post.php' )
        );
    }

    /* -----------------------------------------------------------------------
     * Handler : renvoi d'un nouveau code OTP
     * -------------------------------------------------------------------- */

    public function handle_otp_resend() {
        $token = sanitize_text_field( wp_unslash( $_POST['sig_token'] ?? '' ) );

        if ( empty( $token ) ) {
            wp_safe_redirect( $this->core->get_signature_page_url() );
            exit;
        }

        check_admin_referer( 'acdc_sig_otp_resend_' . $token );

        global $wpdb;
        $request = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->core->table_requests} WHERE token = %s", $token )
        );

        if ( ! $request ) {
            wp_safe_redirect( $this->core->get_signature_page_url() );
            exit;
        }

        // Rate-limit anti abus sur le renvoi d'OTP (clé distincte).
        if ( ! $this->core->check_rate_limit( 'otpresend_' . $token, $_SERVER['REMOTE_ADDR'] ?? '' ) ) {
            wp_safe_redirect( add_query_arg( array( 'sig' => urlencode( $token ), 'otp_error' => 'ratelimited' ), $this->core->get_signature_page_url() ) );
            exit;
        }

        // Générer et envoyer un nouveau code (efface l'ancien transient)
        $otp = $this->core->generate_otp();
        $this->core->store_otp( $request->id, $otp );
        $this->email->send_otp_email( $request->id, $otp );
        $this->core->log_event( $request->id, 'otp_resent', 'Code OTP renvoyé à la demande.', $_SERVER['REMOTE_ADDR'] ?? '' );

        wp_safe_redirect( add_query_arg( 'sig', urlencode( $token ), $this->core->get_signature_page_url() ) );
        exit;
    }

    /* -----------------------------------------------------------------------
     * Page de confirmation immédiate post-signature
     * -------------------------------------------------------------------- */

    private function render_signed_confirmation( $token ) {
        global $wpdb;

        $request = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->core->table_requests} WHERE token = %s", $token )
        );

        // Branding organisme
        $branding     = get_option( 'acdc_of_branding', array() );
        $company_name = ! empty( $branding['company_name'] ) ? sanitize_text_field( (string) $branding['company_name'] ) : get_bloginfo( 'name' );
        $logo_url     = ! empty( $branding['logo_url'] ) ? esc_url( (string) $branding['logo_url'] ) : '';
        $email        = ! empty( $branding['email'] ) ? sanitize_email( (string) $branding['email'] ) : get_option( 'admin_email' );
        $phone        = ! empty( $branding['phone'] ) ? sanitize_text_field( (string) $branding['phone'] ) : '';

        // Données du document
        $signer_name = $request ? esc_html( (string) $request->signer_name ) : '';
        $doc_type    = $request ? (string) $request->doc_type : '';
        $doc_labels  = ACDC_Sig_Core::DOC_TYPES;

        if ( 'convention' === $doc_type ) {
            $doc_label  = 'votre convention de formation';
            $headline   = 'Votre convention est signée !';
            $body_text  = 'Votre signature a bien été enregistrée. Un exemplaire du document signé vous sera transmis par e-mail dans les plus brefs délais.';
        } elseif ( 'contrat' === $doc_type ) {
            $doc_label  = 'votre contrat de formation';
            $headline   = 'Votre contrat est signé !';
            $body_text  = 'Votre signature a bien été enregistrée. Un exemplaire du document signé vous sera transmis par e-mail dans les plus brefs délais.';
        } else {
            $doc_label  = 'votre document';
            $headline   = 'Votre signature a bien été enregistrée !';
            $body_text  = 'Un exemplaire du document signé vous sera transmis par e-mail dans les plus brefs délais.';
        }

        $greeting = $signer_name ? 'Merci, <strong>' . $signer_name . '</strong> !' : 'Merci !';

        $logo_html = $logo_url
            ? '<div style="margin-bottom:16px;"><img src="' . $logo_url . '" alt="Logo" style="max-width:100px;height:auto;"></div>'
            : '';

        $contact_html = '';
        if ( $email || $phone ) {
            $parts = array();
            if ( $phone ) { $parts[] = esc_html( $phone ); }
            if ( $email ) { $parts[] = '<a href="mailto:' . esc_attr( $email ) . '" style="color:#1f335d;font-weight:600;">' . esc_html( $email ) . '</a>'; }
            $contact_html = '<p style="font-size:13px;color:#6b7280;margin:20px 0 0;">Une question ? Contactez-nous : ' . implode( ' · ', $parts ) . '</p>';
        }

        return '<div style="font-family:Arial,sans-serif;max-width:560px;margin:60px auto;background:#fff;border-radius:16px;box-shadow:0 8px 24px rgba(0,0,0,0.08);overflow:hidden;">'
             . '<div style="background:#f3e3bf;padding:28px;text-align:center;">'
             . $logo_html
             . '<div style="font-size:22px;font-weight:800;color:#1f335d;">' . esc_html( $company_name ) . '</div>'
             . '</div>'
             . '<div style="padding:40px 36px;text-align:center;">'
             . '<div style="width:72px;height:72px;background:#d1fae5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:36px;line-height:72px;">✓</div>'
             . '<h2 style="font-size:22px;font-weight:800;color:#1f335d;margin:0 0 10px;">' . esc_html( $headline ) . '</h2>'
             . '<p style="font-size:16px;color:#374151;line-height:1.6;margin:0 0 8px;">' . $greeting . '</p>'
             . '<p style="font-size:15px;color:#4b5563;line-height:1.7;margin:0;">' . esc_html( $body_text ) . '</p>'
             . '<div style="margin:28px 0;padding:16px 20px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;font-size:14px;color:#065f46;">'
             . '📄 ' . ucfirst( $doc_label ) . ' — signature enregistrée le ' . esc_html( wp_date( 'j F Y à H\hi' ) )
             . '</div>'
             . $contact_html
             . '</div>'
             . '<div style="padding:16px;text-align:center;background:#f9fafb;font-size:12px;color:#9ca3af;border-top:1px solid #e5e7eb;">'
             . esc_html( $company_name ) . ' — Ce document a force contractuelle.'
             . '</div>'
             . '</div>';
    }

    /* -----------------------------------------------------------------------
     * Message générique
     * -------------------------------------------------------------------- */

    public function message( $type, $text ) {
        $map = array(
            'success' => array( '#d4edda', '#155724', '✅' ),
            'error'   => array( '#f8d7da', '#721c24', '❌' ),
            'info'    => array( '#cfe2ff', '#084298', 'ℹ️' ),
        );
        $c = $map[ $type ] ?? $map['info'];
        return '<div style="max-width:520px;margin:60px auto;background:' . $c[0] . ';border:1px solid ' . $c[1] . ';border-radius:8px;padding:30px;text-align:center;font-family:Arial,sans-serif">'
             . '<p style="font-size:40px;margin:0 0 12px">' . $c[2] . '</p>'
             . '<p style="font-size:16px;color:#222;margin:0">' . esc_html( $text ) . '</p>'
             . '</div>';
    }

    /**
     * ACDC 3.25.236 — Enregistre la mention d'acceptation recopiée sur un devis.
     *
     * Une signature manuscrite prouve QUI a signé ; la mention « Bon pour
     * accord » prouve CE QUE l'on a accepté. Sur un devis, c'est elle qui vaut
     * acceptation de l'offre, et l'usage veut qu'elle soit de la main du
     * signataire. Elle n'existait nulle part : le document portait un cadre
     * intitulé « Bon pour accord » et rien dedans.
     *
     * Les deux formes sont acceptées et conservées telles quelles — la saisie
     * au clavier et le tracé manuscrit. On ne normalise pas le texte : ce que
     * la personne a écrit est ce qui doit s'imprimer.
     */
    private function maybe_store_accord_mention( $request ) {
        if ( ! $request || 'devis' !== (string) $request->doc_type ) {
            return;
        }

        $notes    = json_decode( (string) ( $request->notes ?? '' ), true );
        $quote_id = ( is_array( $notes ) && ! empty( $notes['quote_id'] ) ) ? (int) $notes['quote_id'] : 0;
        if ( $quote_id <= 0 ) {
            return;
        }

        global $wpdb;
        $quote_table = $wpdb->prefix . 'acdc_of_quotes';

        $mention = isset( $_POST['accord_mention'] ) ? sanitize_text_field( wp_unslash( $_POST['accord_mention'] ) ) : '';
        $data    = array();

        if ( '' !== $mention ) {
            $data['accord_mention'] = $mention;
        }

        $accord_image = isset( $_POST['accord_signature'] ) ? wp_unslash( $_POST['accord_signature'] ) : '';
        if ( '' !== $accord_image && 0 === strpos( $accord_image, 'data:image/' ) && strlen( $accord_image ) <= ACDC_Sig_Core::MAX_SIG_DATA_BYTES ) {
            $path = $this->pdf->save_signature_image( $accord_image, (int) $request->id . '-accord' );
            if ( $path ) {
                $data['accord_signature_path'] = $path;
            }
        }

        if ( empty( $data ) ) {
            return;
        }

        $wpdb->update( $quote_table, $data, array( 'id' => $quote_id ) );
    }
}
