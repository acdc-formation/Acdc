<?php
/**
 * ACDC Émargement Numérique — Emails
 *
 * @package ACDC_Formation_SAAS
 * @since   3.21.09
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ACDC_Emarg_Email {

    /** @var ACDC_Emarg_Core */
    private $core;

    public function __construct( ACDC_Emarg_Core $core ) {
        $this->core = $core;
    }

    private function get_from() {
        $opts = get_option( 'acdc_of_company_profile_options', array() );
        return array(
            'name'  => ! empty( $opts['company_name'] ) ? $opts['company_name'] : get_bloginfo( 'name' ),
            'email' => ! empty( $opts['email'] ) ? $opts['email'] : get_option( 'admin_email' ),
        );
    }

    private function wrap( $title, $subtitle, $body_html ) {
        $from = $this->get_from();
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>'
             . '<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#222">'
             . '<div style="background:#1a2744;padding:20px 24px;text-align:center">'
             . '<h1 style="color:#fff;margin:0;font-size:22px">' . esc_html( $title ) . '</h1>'
             . '<p style="color:#c9a84c;margin:6px 0 0;font-size:14px">' . esc_html( $subtitle ) . '</p>'
             . '</div>'
             . '<div style="padding:24px;background:#fff;border:1px solid #e2e2e2;border-top:none">'
             . $body_html
             . '</div>'
             . '<div style="padding:12px;text-align:center;font-size:11px;color:#999">'
             . esc_html( $from['name'] )
             . '</div></body></html>';
    }

    /* -----------------------------------------------------------------------
     * Email au formateur — déclencher la séance
     * -------------------------------------------------------------------- */
    public function send_trainer_email( $emarg_session ) {
        $sign_url = $this->core->get_public_url( 'formateur', $emarg_session->trainer_token );
        $from     = $this->get_from();

        global $wpdb;
        $session_table = $wpdb->prefix . 'acdc_of_sessions';
        $session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$session_table} WHERE id = %d", $emarg_session->session_id ) );
        $session_label = $session ? ( $session->title ?: 'Séance #' . $session->id ) : 'Séance';
        $date_label = '';
        if ( $session && $session->start_at ) {
            $date_label = wp_date( 'd/m/Y à H\hi', strtotime( $session->start_at ) );
        }

        // Émargement par séance : n'ajoute un contexte séance QUE pour les créneaux >= 1.
        // Le cas mono-séance (seance_index = 0) reste STRICTEMENT identique à l'existant.
        if ( ! empty( $emarg_session->seance_index ) && (int) $emarg_session->seance_index > 0 ) {
            if ( ! empty( $emarg_session->seance_label ) ) {
                $session_label = $session_label . ' — ' . $emarg_session->seance_label;
            }
            if ( ! empty( $emarg_session->seance_start_at ) ) {
                $date_label = wp_date( 'd/m/Y à H\hi', strtotime( $emarg_session->seance_start_at ) );
            }
        }

        $qr_url  = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=10&data=' . rawurlencode( $sign_url );

        $body = '<p>Bonjour <strong>' . esc_html( $emarg_session->trainer_name ) . '</strong>,</p>'
              . '<p style="margin:12px 0">Vous êtes formateur pour la séance suivante :</p>'
              . '<div style="background:#f8f9fa;border-left:3px solid #c9a84c;padding:12px 16px;margin:16px 0;border-radius:0 6px 6px 0">'
              . '<strong>' . esc_html( $session_label ) . '</strong>'
              . ( $date_label ? '<br><span style="color:#6b7280;font-size:13px">' . esc_html( $date_label ) . '</span>' : '' )
              . '</div>'
              . '<p>Cliquez sur le bouton ci-dessous pour <strong>ouvrir la séance et signer votre feuille d\'émargement</strong>.</p>'
              . '<p style="text-align:center;margin:28px 0">'
              . '<a href="' . esc_url( $sign_url ) . '" style="background:#c9a84c;color:#fff;text-decoration:none;padding:14px 28px;border-radius:6px;font-weight:700;font-size:16px;display:inline-block">▶ Ouvrir la séance</a>'
              . '</p>'
              . '<div style="margin:24px 0;padding:20px;background:#f8f9fa;border:1px solid #e2e6ea;border-radius:8px;text-align:center">'
              . '<p style="margin:0 0 12px;font-size:13px;font-weight:700;color:#1a2744">📱 QR Code — émargement apprenants</p>'
              . '<p style="margin:0 0 14px;font-size:12px;color:#6b7280">Affichez ce QR code sur votre écran ou imprimez-le.<br>Chaque apprenant le scanne pour signer directement depuis son téléphone.</p>'
              . '<img src="' . esc_url( $qr_url ) . '" alt="QR Code émargement" width="180" height="180" style="display:inline-block;border-radius:6px;border:1px solid #e2e6ea">'
              . '</div>'
              . '<p style="font-size:12px;color:#666">Si le bouton ne fonctionne pas : <a href="' . esc_url( $sign_url ) . '">' . esc_url( $sign_url ) . '</a></p>';

        $html = $this->wrap( 'Émargement — Séance à ouvrir', $session_label, $body );
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from['name'] . ' <' . $from['email'] . '>',
        );
        /* ACDC 3.25.161 — Attribution d'archive : sans ces en-têtes, l'envoi
           s'affiche « plugin / wp_mail » dans l'archive, sans module identifiable. */
        $headers[] = 'X-ACDC-Source-Module: emargement';
        $headers[] = 'X-ACDC-Source-Action: session_to_open';
        $headers[] = 'X-ACDC-Email-Category: emargement';
        wp_mail( $emarg_session->trainer_email, 'Séance à ouvrir — ' . $session_label, $html, $headers );
    }

    /* -----------------------------------------------------------------------
     * Email individuel à un apprenant
     * -------------------------------------------------------------------- */
    public function send_learner_email( $learner_row ) {
        if ( empty( $learner_row->learner_email ) ) { return; }
        $sign_url = $this->core->get_public_url( 'apprenant', $learner_row->sign_token );
        $from     = $this->get_from();

        global $wpdb;
        $session_table = $wpdb->prefix . 'acdc_of_sessions';
        $session = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$session_table} WHERE id = %d", $learner_row->session_id ) );
        $session_label = $session ? ( $session->title ?: 'Séance #' . $session->id ) : 'Séance';

        $body = '<p>Bonjour <strong>' . esc_html( $learner_row->learner_name ) . '</strong>,</p>'
              . '<p style="margin:12px 0">Votre formateur vous invite à signer votre feuille d\'émargement pour la séance :</p>'
              . '<div style="background:#f8f9fa;border-left:3px solid #c9a84c;padding:12px 16px;margin:16px 0">'
              . '<strong>' . esc_html( $session_label ) . '</strong>'
              . '</div>'
              . '<p style="text-align:center;margin:28px 0">'
              . '<a href="' . esc_url( $sign_url ) . '" style="background:#c9a84c;color:#fff;text-decoration:none;padding:14px 28px;border-radius:6px;font-weight:700;font-size:16px;display:inline-block">✍ Signer mon émargement</a>'
              . '</p>'
              . '<p style="font-size:12px;color:#666">📱 <strong>Conseil :</strong> Ouvrez ce lien sur votre smartphone pour signer avec le doigt.</p>'
              . '<p style="font-size:12px;color:#666">Lien : <a href="' . esc_url( $sign_url ) . '">' . esc_url( $sign_url ) . '</a></p>'
              . '<div style="margin:20px 0;padding:16px;background:#f8f9fa;border:1px solid #e2e6ea;border-radius:8px;text-align:center">'
              . '<p style="margin:0 0 10px;font-size:12px;font-weight:700;color:#1a2744">📱 QR Code — signature rapide</p>'
              . '<p style="margin:0 0 12px;font-size:11px;color:#6b7280">Scannez ce QR code avec votre smartphone pour signer directement.</p>'
              . '<img src="' . esc_url( 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=10&data=' . rawurlencode( $sign_url ) ) . '" alt="QR Code signature" width="160" height="160" style="display:inline-block;border-radius:6px;border:1px solid #e2e6ea">'
              . '</div>';

        $html = $this->wrap( 'Émargement — Signature requise', $session_label, $body );
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from['name'] . ' <' . $from['email'] . '>',
        );
        /* ACDC 3.25.161 — Attribution d'archive : sans ces en-têtes, l'envoi
           s'affiche « plugin / wp_mail » dans l'archive, sans module identifiable. */
        $headers[] = 'X-ACDC-Source-Module: emargement';
        $headers[] = 'X-ACDC-Source-Action: learner_signature';
        $headers[] = 'X-ACDC-Email-Category: emargement';
        wp_mail( $learner_row->learner_email, 'Émargement — ' . $session_label, $html, $headers );
    }
}
