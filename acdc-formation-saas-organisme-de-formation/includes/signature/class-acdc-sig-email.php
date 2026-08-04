<?php
/**
 * ACDC Signature — Email
 *
 * Envoi des emails de demande de signature,
 * confirmation de signature, notification admin.
 *
 * @package ACDC_Formation_SAAS
 * @since   3.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ACDC_Sig_Email {

    private ACDC_Sig_Core $core;

    public function __construct( ACDC_Sig_Core $core ) {
        $this->core = $core;
    }

    /* -----------------------------------------------------------------------
     * Email de demande de signature
     * -------------------------------------------------------------------- */

    /**
     * Construit les données de marque à partir des options WordPress.
     * Réplique acdc_get_transactional_email_branding() sans dépendre du trait kernel.
     */
    private function get_sig_email_branding() {
        $branding          = get_option( 'acdc_of_branding', array() );
        if ( ! is_array( $branding ) ) { $branding = array(); }
        $marketing         = get_option( 'acdc_of_marketing_settings', array() );
        if ( ! is_array( $marketing ) ) { $marketing = array(); }

        $company_name = ! empty( $branding['company_name'] ) ? sanitize_text_field( (string) $branding['company_name'] ) : 'ACDC Formation';
        $logo_url     = ! empty( $branding['logo_url'] )     ? esc_url( (string) $branding['logo_url'] ) : '';
        $website      = ! empty( $branding['website'] )      ? esc_url_raw( (string) $branding['website'] ) : home_url( '/' );
        $phone        = ! empty( $branding['phone'] )        ? sanitize_text_field( (string) $branding['phone'] ) : '';
        $email        = ! empty( $marketing['sender_email'] ) ? sanitize_email( (string) $marketing['sender_email'] ) : '';
        if ( '' === $email && ! empty( $branding['email'] ) ) { $email = sanitize_email( (string) $branding['email'] ); }
        if ( '' === $email ) { $email = sanitize_email( (string) get_option( 'admin_email' ) ); }
        $sender_name  = ! empty( $marketing['sender_name'] ) ? sanitize_text_field( (string) $marketing['sender_name'] ) : $company_name;
        $reply_to     = ! empty( $marketing['reply_to'] )    ? sanitize_email( (string) $marketing['reply_to'] ) : $email;
        $address_bits = array_filter( array(
            ! empty( $branding['address'] )     ? sanitize_text_field( (string) $branding['address'] ) : '',
            trim( ( ! empty( $branding['postal_code'] ) ? sanitize_text_field( (string) $branding['postal_code'] ) : '' )
                . ' ' . ( ! empty( $branding['city'] ) ? sanitize_text_field( (string) $branding['city'] ) : '' ) ),
        ) );

        return array(
            'company_name' => $company_name,
            'subtitle'     => 'AZUR COMPÉTENCES DÉVELOPPEMENT & CONSEIL',
            'logo_url'     => $logo_url,
            'website'      => $website,
            'website_label'=> preg_replace( '#^https?://#', '', rtrim( $website, '/' ) ),
            'phone'        => $phone,
            'email'        => $email,
            'sender_name'  => $sender_name,
            'reply_to'     => $reply_to,
            'address_line' => implode( ' — ', $address_bits ),
        );
    }

    public function send_signature_email( $request_id ) {
        global $wpdb;
        $request = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->core->table_requests} WHERE id = %d", $request_id )
        );
        if ( ! $request ) {
            return false;
        }

        $s        = $this->core->get_settings();
        $b        = $this->get_sig_email_branding();
        $doc_type = isset( $request->doc_type ) ? (string) $request->doc_type : '';
        $doc_label = ACDC_Sig_Core::DOC_TYPES[ $doc_type ] ?? $doc_type;

        // Libellés différenciés convention / contrat
        if ( 'convention' === $doc_type ) {
            $doc_label_possessif = 'votre convention de formation';
            $doc_label_court     = 'Convention de formation';
            $sign_btn_label      = 'Signer votre convention';
            $footer_notice       = 'Cet e-mail a été envoyé suite à l\'établissement de votre convention de formation. Vos données sont traitées conformément au RGPD.';
        } elseif ( 'contrat' === $doc_type ) {
            $doc_label_possessif = 'votre contrat individuel de formation';
            $doc_label_court     = 'Contrat individuel de formation';
            $sign_btn_label      = 'Signer votre contrat';
            $footer_notice       = 'Cet e-mail a été envoyé suite à l\'établissement de votre contrat de formation. Vos données sont traitées conformément au RGPD.';
        } elseif ( 'devis' === $doc_type ) {
            $doc_label_possessif = 'votre devis de formation';
            $doc_label_court     = 'Devis de formation';
            $sign_btn_label      = 'Signer votre devis';
            $footer_notice       = 'Cet e-mail a été envoyé suite à l\'établissement de votre devis de formation. Vos données sont traitées conformément au RGPD.';
        } else {
            $doc_label_possessif = 'le document';
            $doc_label_court     = $doc_label;
            $sign_btn_label      = 'Signer le document';
            $footer_notice       = 'Cet e-mail a été envoyé par ' . $b['company_name'] . '. Vos données sont traitées conformément au RGPD.';
        }

        $sign_url      = add_query_arg( 'sig', $request->token, $this->core->get_signature_page_url() );
        $doc_url       = ! empty( $request->doc_url ) ? (string) $request->doc_url : '';
        /* B4 — Objet/intro spécifiques au type de document. Les réglages globaux par défaut
           (« Document à signer » / intro générique) aplatissaient tous les types : le client
           ne savait pas ce qu'il signait (ni devis, ni convention). On n'utilise le réglage
           global que s'il a été personnalisé (différent du défaut) ; sinon on reprend le
           libellé propre au type (Devis / Convention / Contrat …). */
        $sig_default_subject = 'Document à signer';
        $sig_default_intro   = 'Vous avez reçu une demande de signature électronique. Merci de signer le document en cliquant sur le bouton ci-dessous.';
        $custom_subject = ( '' !== trim( (string) $s['email_subject'] ) && $sig_default_subject !== $s['email_subject'] );
        $custom_intro   = ( '' !== trim( (string) $s['email_intro'] ) && $sig_default_intro !== $s['email_intro'] );
        $subject       = $custom_subject ? $s['email_subject'] : ( $doc_label_court . ' à signer — ' . $b['company_name'] );
        $intro         = $custom_intro   ? $s['email_intro']   : ( 'Vous trouverez ci-dessous ' . $doc_label_possessif . '. Nous vous invitons à en prendre connaissance, puis à le signer électroniquement avant son expiration.' );
        $btn_color     = $s['btn_color']     ?: '#C5A253';
        /* +2h — expires_at est stocké en heure locale (current_time). mysql2date() le rend
           tel quel ; wp_date(strtotime()) le relisait comme UTC et ajoutait l'offset (+2h). */
        $expires_label = $request->expires_at ? mysql2date( 'd/m/Y à H:i', $request->expires_at ) : '';

        // --- Bloc "Lire le document" (bouton contour marine) ---
        $read_block = '';
        if ( '' !== $doc_url ) {
            $read_block = '<p style="text-align:center;margin:28px 0 6px;">'
                        . '<a href="' . esc_url( $doc_url ) . '" style="display:inline-block;padding:13px 26px;background:#ffffff;color:#1f335d;text-decoration:none;border-radius:8px;font-weight:700;font-size:16px;border:2px solid #1f335d;">'
                        . '📄 Lire ' . esc_html( $doc_label_possessif ) . '</a>'
                        . '</p>'
                        . '<p style="text-align:center;font-size:14px;color:#66738b;margin:0 0 28px;">Prenez le temps de lire le document avant de le signer.</p>';
        }

        // --- Alerte expiration ---
        $expire_block = $expires_label
            ? '<p style="color:#856404;background:#fff3cd;padding:10px 14px;border-radius:8px;margin:0 0 20px;">⏳ Ce lien expire le <strong>' . esc_html( $expires_label ) . '</strong></p>'
            : '';

        // --- Conseil smartphone ---
        // ACDC sécurité: QR distant retiré (fuite de token vers tiers)
        $qr_block   = '<div style="text-align:center;margin:20px 0 24px;">'
                    . '<p style="font-size:14px;color:#374151;font-weight:700;margin:0 0 6px;">📱 Sur smartphone ?</p>'
                    . '<p style="font-size:14px;color:#444;margin:0;">Ouvrez cet e-mail sur votre téléphone et touchez le bouton de signature ci-dessous.</p>'
                    . '</div>';

        $conseil_block = $qr_block
                       . '<p style="background:#f0f7ff;border-left:3px solid #4a90d9;padding:10px 14px;border-radius:4px;font-size:14px;color:#444;margin:0 0 20px;">'
                       . '💻 <strong>Sur ordinateur ou smartphone ?</strong> Cliquez sur le bouton de signature ci-dessous.</p>';

        // --- Bouton de signature (doré, plein) ---
        $sign_block = '<p style="text-align:center;margin:28px 0;">'
                    . '<a href="' . esc_url( $sign_url ) . '" style="display:inline-block;padding:14px 30px;background:' . esc_attr( $btn_color ) . ';color:#0B0706;text-decoration:none;border-radius:8px;font-weight:700;font-size:16px;">'
                    . '✍ ' . esc_html( $sign_btn_label ) . '</a>'
                    . '</p>';

        // --- Lien de secours ---
        $fallback_block = '<p style="font-size:13px;color:#66738b;margin:0 0 16px;">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>'
                        . '<a href="' . esc_url( $sign_url ) . '" style="color:#5579bf;word-break:break-all;">' . esc_url( $sign_url ) . '</a></p>';

        // --- Construction HTML selon charte ACDC ---
        $logo_html = $b['logo_url']
            ? '<div style="margin-bottom:18px;"><img src="' . esc_url( $b['logo_url'] ) . '" alt="Logo" style="max-width:120px;height:auto;"></div>'
            : '';

        $footer_contact = $b['phone'] ? esc_html( $b['phone'] ) . ' · ' : '';
        $footer_contact .= '<a href="mailto:' . esc_attr( $b['email'] ) . '" style="color:#5579bf;text-decoration:underline;">' . esc_html( $b['email'] ) . '</a>';
        $footer_contact .= ' · <a href="' . esc_url( $b['website'] ) . '" style="color:#5579bf;text-decoration:underline;">' . esc_html( $b['website_label'] ) . '</a>';
        if ( $b['address_line'] ) {
            $footer_contact .= '<br>' . esc_html( $b['address_line'] );
        }

        $body = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>'
              . '<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,sans-serif;color:#24324a;">'
              . '<div style="max-width:860px;margin:0 auto;background:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 8px 24px rgba(28,44,64,0.08);">'
              // En-tête branding
              . '<div style="padding:44px 56px 24px;text-align:center;">'
              . $logo_html
              . '<div style="font-size:34px;line-height:1.2;font-weight:800;color:#1f335d;">' . esc_html( $b['company_name'] ) . '</div>'
              . '<div style="font-size:18px;line-height:1.4;letter-spacing:1px;color:#45567b;text-transform:uppercase;">' . esc_html( $b['subtitle'] ) . '</div>'
              . '</div>'
              // Corps
              . '<div style="padding:12px 56px 56px;">'
              . '<p style="font-size:19px;line-height:1.7;margin:0 0 24px;">Bonjour <strong>' . esc_html( $request->signer_name ) . '</strong>,</p>'
              . '<p style="font-size:18px;line-height:1.7;margin:0 0 20px;">' . esc_html( $intro ) . '</p>'
              . $expire_block
              . $read_block
              . $conseil_block
              . $sign_block
              . $fallback_block
              . '<p style="font-size:18px;line-height:1.7;margin:30px 0 0;">Si vous avez des questions, contactez-nous à <a href="mailto:' . esc_attr( $b['email'] ) . '" style="color:#c59a2a;text-decoration:underline;">' . esc_html( $b['email'] ) . '</a>'
              . ( $b['phone'] ? ' ou au <strong>' . esc_html( $b['phone'] ) . '</strong>' : '' ) . '.</p>'
              . '<p style="font-size:18px;line-height:1.7;margin:36px 0 0;">Cordialement,<br><strong>L\'équipe ' . esc_html( $b['company_name'] ) . '</strong></p>'
              . '</div>'
              // Pied de page
              . '<div style="padding:28px 40px;text-align:center;border-top:1px solid #e3e7ef;background:#fafafa;color:#6a7488;font-size:13px;line-height:1.6;">'
              . $footer_contact
              . '<br>' . esc_html( $footer_notice )
              . '</div>'
              . '</div>'
              . '</body></html>';

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . sanitize_text_field( $b['sender_name'] ) . ' <' . sanitize_email( $b['email'] ) . '>',
        );
        if ( ! empty( $b['reply_to'] ) ) {
            $headers[] = 'Reply-To: ' . sanitize_email( $b['reply_to'] );
        }

        $sent = wp_mail( $request->signer_email, $subject, $body, $headers );

        $this->core->log_event(
            $request_id,
            'email_sent',
            'Email envoyé à ' . $request->signer_email . ( $sent ? ' — OK' : ' — ECHEC' )
        );

        return $sent;
    }

    /* -----------------------------------------------------------------------
     * Email de confirmation avec lien vers le certificat
     * -------------------------------------------------------------------- */

    public function send_signed_doc_email( $request_id ) {
        global $wpdb;
        $request = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->core->table_requests} WHERE id = %d", $request_id )
        );
        if ( ! $request || empty( $request->signed_doc_url ) ) {
            return false;
        }

        $s          = $this->core->get_settings();
        $doc_label  = ACDC_Sig_Core::DOC_TYPES[ $request->doc_type ] ?? $request->doc_type;
        $from_name  = $s['from_name']  ?: get_bloginfo( 'name' );
        $from_email = $s['from_email'] ?: get_option( 'admin_email' );

        $body = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>'
              . '<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#222">'
              . '<div style="background:#1a2744;padding:20px;text-align:center">'
              . '<h1 style="color:#fff;margin:0;font-size:22px">✅ Document signé</h1>'
              . '</div>'
              . '<div style="padding:24px;background:#fff;border:1px solid #e2e2e2;border-top:none">'
              . '<p>Bonjour <strong>' . esc_html( $request->signer_name ) . '</strong>,</p>'
              . '<p>Votre signature a bien été enregistrée pour le document : <strong>' . esc_html( $doc_label ) . '</strong></p>'
              . '<p style="text-align:center;margin:24px 0">'
              . '<a href="' . esc_url( $request->signed_doc_url ) . '" style="background:#1a2744;color:#fff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:700">'
              . '📄 Télécharger le certificat</a></p>'
              . '</div></body></html>';

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . sanitize_text_field( $from_name ) . ' <' . sanitize_email( $from_email ) . '>',
        );

        return wp_mail(
            $request->signer_email,
            'Votre signature a été enregistrée — ' . $doc_label,
            $body,
            $headers
        );
    }

    /* -----------------------------------------------------------------------
     * E-mail OTP — double authentification niveau renforcé
     * -------------------------------------------------------------------- */

    public function send_otp_email( $request_id, $otp ) {
        global $wpdb;
        $request = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->core->table_requests} WHERE id = %d", $request_id )
        );
        if ( ! $request ) {
            return false;
        }

        $s          = $this->core->get_settings();
        $from_name  = $s['from_name']  ?: get_bloginfo( 'name' );
        $from_email = $s['from_email'] ?: get_option( 'admin_email' );
        $doc_label  = ACDC_Sig_Core::DOC_TYPES[ $request->doc_type ] ?? $request->doc_type;
        $org_name   = esc_html( get_bloginfo( 'name' ) );

        $body = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>'
              . '<body style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;color:#222">'
              . '<div style="background:#1a2744;padding:20px;text-align:center">'
              . '<h1 style="color:#fff;margin:0;font-size:20px">🔒 Code de vérification</h1>'
              . '<p style="color:#c9a84c;margin:6px 0 0;font-size:13px">' . esc_html( $doc_label ) . '</p>'
              . '</div>'
              . '<div style="padding:28px;background:#fff;border:1px solid #e2e2e2;border-top:none">'
              . '<p>Bonjour <strong>' . esc_html( $request->signer_name ) . '</strong>,</p>'
              . '<p>Pour accéder au document à signer, veuillez saisir le code ci-dessous :</p>'
              . '<div style="text-align:center;margin:28px 0">'
              . '<div style="display:inline-block;background:#f0f4ff;border:2px solid #1a2744;border-radius:10px;padding:18px 36px">'
              . '<span style="font-size:36px;font-weight:900;letter-spacing:10px;color:#1a2744;font-family:monospace">' . esc_html( $otp ) . '</span>'
              . '</div>'
              . '</div>'
              . '<p style="text-align:center;color:#856404;background:#fff3cd;padding:8px 12px;border-radius:4px;font-size:13px">⏳ Ce code est valable <strong>15 minutes</strong>.</p>'
              . '<p style="font-size:12px;color:#666;margin-top:20px">Ce code est personnel et confidentiel. Ne le communiquez à personne.<br>'
              . 'Si vous n\'avez pas demandé à signer un document, ignorez cet e-mail.</p>'
              . '</div>'
              . '<div style="padding:14px;text-align:center;font-size:11px;color:#999">'
              . $org_name . ' — ' . esc_html( home_url() )
              . '</div></body></html>';

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . sanitize_text_field( $from_name ) . ' <' . sanitize_email( $from_email ) . '>',
        );

        $sent = wp_mail(
            $request->signer_email,
            'Votre code de vérification — ' . esc_html( $doc_label ),
            $body,
            $headers
        );

        $this->core->log_event(
            $request_id,
            'otp_sent',
            'Code OTP envoyé à ' . $request->signer_email . ( $sent ? ' — OK' : ' — ECHEC' )
        );

        return $sent;
    }

    /* -----------------------------------------------------------------------
     * Notification admin (signature ou refus)
     * -------------------------------------------------------------------- */

    public function notify_admin( $request_id, $event ) {
        global $wpdb;
        $request = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->core->table_requests} WHERE id = %d", $request_id )
        );
        if ( ! $request ) {
            return;
        }

        $s           = $this->core->get_settings();
        $admin_email = $s['admin_email'] ?: get_option( 'admin_email' );
        $doc_label   = ACDC_Sig_Core::DOC_TYPES[ $request->doc_type ] ?? $request->doc_type;

        /* Référence du document (n° devis / convention…) : conservée dans les notes de la demande.
           Permet une notification interne contextualisée (« … — Devis DE-2026-6 »). */
        $doc_ref     = '';
        $notes_arr   = json_decode( isset( $request->notes ) ? (string) $request->notes : '', true );
        if ( is_array( $notes_arr ) && ! empty( $notes_arr['doc_reference'] ) ) {
            $doc_ref = (string) $notes_arr['doc_reference'];
        }
        $doc_full = $doc_label . ( '' !== $doc_ref ? ' ' . $doc_ref : '' );

        if ( 'signe' === $event ) {
            $subject = '✅ Document signé — ' . $request->signer_name . ' — ' . $doc_full;
            $msg     = esc_html( $request->signer_name ) . ' a signé le document « ' . esc_html( $doc_full ) . ' » le ' . wp_date( 'd/m/Y à H:i' ) . '.';
        } else {
            $subject = '⚠️ Signature refusée — ' . $request->signer_name . ( '' !== $doc_ref ? ' — ' . $doc_full : '' );
            $msg     = esc_html( $request->signer_name ) . ' a refusé de signer le document « ' . esc_html( $doc_full ) . ' ».';
        }

        // Lien front-office extranet (tab conventions)
        $portal_page_id = (int) get_option( 'acdc_of_extranet_dashboard_page_id', 0 );
        if ( ! $portal_page_id ) { $portal_page_id = (int) get_option( 'acdc_of_portal_page_id', 0 ); }
        $portal_base = $portal_page_id ? get_permalink( $portal_page_id ) : home_url( '/' );
        $admin_url = add_query_arg( array( 'tab' => 'registration_contract' ), $portal_base );
        $body      = '<p>' . $msg . '</p><p><a href="' . esc_url( $admin_url ) . '">Voir dans le tableau de bord</a></p>';
        $headers   = array( 'Content-Type: text/html; charset=UTF-8' );

        /* En-têtes X-ACDC : rattachement/archivage de la notification interne au bon document
           (colonne « Source » de l'Archive des e-mails contextualisée au lieu de « plugin »). */
        $related_type = isset( $notes_arr['entity_type'] ) ? sanitize_key( (string) $notes_arr['entity_type'] ) : sanitize_key( (string) $request->doc_type );
        $related_id   = 0;
        if ( is_array( $notes_arr ) ) {
            foreach ( array( 'quote_id', 'contract_id', 'entity_id', 'invoice_id' ) as $k ) {
                if ( ! empty( $notes_arr[ $k ] ) ) { $related_id = (int) $notes_arr[ $k ]; break; }
            }
        }
        $headers[] = 'X-ACDC-Source-Module: signature';
        $headers[] = 'X-ACDC-Source-Action: ' . ( 'signe' === $event ? 'document_signed' : 'document_refused' );
        $headers[] = 'X-ACDC-Related-Entity-Type: ' . ( '' !== $related_type ? $related_type : 'document' );
        if ( $related_id ) { $headers[] = 'X-ACDC-Related-Entity-Id: ' . $related_id; }
        $headers[] = 'X-ACDC-Email-Category: signature';
        $headers[] = 'X-ACDC-Email-Audience: interne';

        wp_mail( $admin_email, $subject, $body, $headers );
    }
}
