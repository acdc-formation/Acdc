<?php
/**
 * ACDC Signature — PDF
 *
 * Génération du certificat PDF d'audit natif (sans dépendance externe).
 * Moteur identique à render_simple_pdf() du plugin principal.
 * Gestion des images signature et pièce d'identité.
 *
 * @package ACDC_Formation_SAAS
 * @since   3.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ACDC_Sig_PDF {

    private ACDC_Sig_Core $core;

    public function __construct( ACDC_Sig_Core $core ) {
        $this->core = $core;
    }

    /* -----------------------------------------------------------------------
     * Point d'entrée principal
     * -------------------------------------------------------------------- */

    public function generate_audit_pdf( $request, $sig_img_path, $id_photo_path = '' ) {
        $upload_dir   = wp_upload_dir();
        $sig_dir      = $upload_dir['basedir'] . '/acdc-signatures/' . $request->id . '/';
        $sig_url_base = $upload_dir['baseurl']  . '/acdc-signatures/' . $request->id . '/';

        $filename = 'certificat-' . $request->id . '-' . time() . '.pdf';
        $filepath = $sig_dir . $filename;
        $fileurl  = $sig_url_base . $filename;

        $pages = $this->build_audit_pdf_pages( $request, $sig_img_path, $id_photo_path );
        $pdf   = $this->render_to_string( $pages );

        // Correction audit MOYEN : vérifier le résultat de l'écriture
        if ( ! $this->core->safe_file_write( $filepath, $pdf, 'certificat PDF' ) ) {
            return array( 'url' => '', 'path' => '' );
        }

        return array( 'url' => $fileurl, 'path' => $filepath );
    }

    /* -----------------------------------------------------------------------
     * Construction des pages du PDF d'audit
     * -------------------------------------------------------------------- */

    private function build_audit_pdf_pages( $request, $sig_img_path, $id_photo_path = '' ) {
        $doc_label   = ACDC_Sig_Core::DOC_TYPES[ $request->doc_type ] ?? $request->doc_type;
        $signed_at   = wp_date( 'd/m/Y à H:i:s' );
        $is_renforce = ( ACDC_Sig_Core::LEVEL_RENFORCE === $request->sig_level );
        $level_label = $is_renforce ? 'Renforcée' : 'Simple';
        $ip          = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
        $ua          = sanitize_text_field( substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 80 ) );

        $marine     = '#1a2744';
        $or         = '#c9a84c';
        $muted      = '#4a5568';
        $border     = '#e2e2e2';
        $success    = '#155724';
        $success_bg = '#d4edda';
        $W = 595; $H = 842;

        $e = array();

        // Entête
        $e[] = array( 'type' => 'rect', 'x' => 0, 'y' => $H - 80, 'width' => $W, 'height' => 80, 'fill_color' => $marine );
        $e[] = array( 'type' => 'rect', 'x' => 0, 'y' => $H - 86, 'width' => $W, 'height' => 6,  'fill_color' => $or );
        $e[] = array( 'text' => 'CERTIFICAT DE SIGNATURE ELECTRONIQUE', 'x' => 42, 'y' => $H - 30, 'size' => 14, 'font' => 'Helvetica-Bold', 'color' => '#FFFFFF' );
        $e[] = array( 'text' => strtoupper( $doc_label ), 'x' => 42, 'y' => $H - 50, 'size' => 10, 'font' => 'Helvetica', 'color' => '#c9a84c' );
        $e[] = array( 'text' => 'Emis le ' . $signed_at, 'x' => 42, 'y' => $H - 66, 'size' => 8.5, 'font' => 'Helvetica', 'color' => '#aab8cc' );

        // Cachet signé
        $e[] = array( 'type' => 'rect', 'x' => 370, 'y' => $H - 136, 'width' => 185, 'height' => 36, 'fill_color' => $success_bg, 'stroke_color' => $success, 'line_width' => 1.5 );
        $e[] = array( 'text' => 'DOCUMENT SIGNE ELECTRONIQUEMENT', 'x' => 378, 'y' => $H - 113, 'size' => 6.5, 'font' => 'Helvetica-Bold', 'color' => $success );
        $e[] = array( 'text' => $signed_at, 'x' => 390, 'y' => $H - 127, 'size' => 7.5, 'font' => 'Helvetica', 'color' => $success );

        // Identité signataire
        $y = $H - 110;
        $e[] = array( 'text' => 'IDENTITE DU SIGNATAIRE', 'x' => 42, 'y' => $y, 'size' => 9, 'font' => 'Helvetica-Bold', 'color' => $marine );
        $e[] = array( 'type' => 'rect', 'x' => 42, 'y' => $y - 2, 'width' => 60, 'height' => 2, 'fill_color' => $or );

        $y -= 14;
        foreach ( array(
            array( 'Nom et prenom',   $request->signer_name ),
            array( 'Adresse e-mail',  $request->signer_email ),
            array( 'Qualite / role',  $request->signer_role ?: '—' ),
        ) as $row ) {
            $e[] = array( 'type' => 'rect', 'x' => 42,  'y' => $y - 14, 'width' => 150, 'height' => 18, 'fill_color' => '#f5f7fa', 'stroke_color' => $border, 'line_width' => .5 );
            $e[] = array( 'type' => 'rect', 'x' => 192, 'y' => $y - 14, 'width' => 360, 'height' => 18, 'stroke_color' => $border, 'line_width' => .5 );
            $e[] = array( 'text' => $row[0], 'x' => 48,  'y' => $y - 4, 'size' => 8,   'font' => 'Helvetica-Bold', 'color' => $muted );
            $e[] = array( 'text' => $this->truncate( $row[1], 55 ), 'x' => 198, 'y' => $y - 4, 'size' => 8.5, 'font' => 'Helvetica', 'color' => '#1a202c' );
            $y -= 18;
        }

        // Métadonnées
        $y -= 16;
        $e[] = array( 'text' => 'METADONNEES DE SIGNATURE', 'x' => 42, 'y' => $y, 'size' => 9, 'font' => 'Helvetica-Bold', 'color' => $marine );
        $e[] = array( 'type' => 'rect', 'x' => 42, 'y' => $y - 2, 'width' => 75, 'height' => 2, 'fill_color' => $or );

        $y -= 14;
        $meta_rows = array(
            array( 'Type de document',      $doc_label ),
            array( 'Date et heure',         $signed_at ),
            array( 'Adresse IP',            $ip ?: '—' ),
            array( 'Niveau tracabilite',    $level_label ),
            array( 'Identifiant unique',    $this->truncate( $request->token, 52 ) ),
        );
        if ( $ua ) {
            $meta_rows[] = array( 'Navigateur / appareil', $this->truncate( $ua, 55 ) );
        }
        foreach ( $meta_rows as $row ) {
            $e[] = array( 'type' => 'rect', 'x' => 42,  'y' => $y - 14, 'width' => 150, 'height' => 18, 'fill_color' => '#f5f7fa', 'stroke_color' => $border, 'line_width' => .5 );
            $e[] = array( 'type' => 'rect', 'x' => 192, 'y' => $y - 14, 'width' => 360, 'height' => 18, 'stroke_color' => $border, 'line_width' => .5 );
            $e[] = array( 'text' => $row[0], 'x' => 48,  'y' => $y - 4, 'size' => 8,   'font' => 'Helvetica-Bold', 'color' => $muted );
            $fsize = ( 'Navigateur / appareil' === $row[0] ) ? 7.5 : 8.5;
            $e[] = array( 'text' => $this->truncate( $row[1], 55 ), 'x' => 198, 'y' => $y - 4, 'size' => $fsize, 'font' => 'Helvetica', 'color' => '#1a202c' );
            $y -= 18;
        }

        // Journal d'audit
        $y -= 20;
        $e[] = array( 'text' => "JOURNAL D'AUDIT", 'x' => 42, 'y' => $y, 'size' => 9, 'font' => 'Helvetica-Bold', 'color' => $marine );
        $e[] = array( 'type' => 'rect', 'x' => 42, 'y' => $y - 2, 'width' => 46, 'height' => 2, 'fill_color' => $or );

        global $wpdb;
        $audit_events = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT event, details, ip, created_at FROM {$this->core->table_audit}
                 WHERE request_id = %d ORDER BY created_at ASC LIMIT 20",
                $request->id
            )
        );

        $event_labels = array(
            'created'      => 'Demande creee',
            'email_sent'   => 'E-mail envoye',
            'opened'       => 'Lien ouvert',
            'signed'       => 'Document signe',
            'refused'      => 'Signature refusee',
            'resent'       => 'E-mail renvoye',
            'expired'      => 'Lien expire',
            'relance_auto' => 'Relance automatique',
        );

        $y -= 14;
        $e[] = array( 'type' => 'rect', 'x' => 42, 'y' => $y - 14, 'width' => 510, 'height' => 18, 'fill_color' => $marine );
        $e[] = array( 'text' => 'Date / Heure', 'x' => 48,  'y' => $y - 4, 'size' => 8, 'font' => 'Helvetica-Bold', 'color' => '#FFFFFF' );
        $e[] = array( 'text' => 'Evenement',    'x' => 160, 'y' => $y - 4, 'size' => 8, 'font' => 'Helvetica-Bold', 'color' => '#FFFFFF' );
        $e[] = array( 'text' => 'IP',           'x' => 300, 'y' => $y - 4, 'size' => 8, 'font' => 'Helvetica-Bold', 'color' => '#FFFFFF' );
        $e[] = array( 'text' => 'Detail',       'x' => 370, 'y' => $y - 4, 'size' => 8, 'font' => 'Helvetica-Bold', 'color' => '#FFFFFF' );
        $y -= 18;

        foreach ( $audit_events as $ev ) {
            $ev_label  = $event_labels[ $ev->event ] ?? $ev->event;
            // Conversion UTC → Europe/Paris
            // Tous les événements sont stockés en UTC (gmdate dans log_event)
            try {
                $_tz = new DateTimeZone( 'Europe/Paris' );
                $_dt = new DateTime( $ev->created_at, new DateTimeZone( 'UTC' ) );
                $_dt->setTimezone( $_tz );
                $ev_date = $_dt->format( 'd/m/Y H:i:s' );
            } catch ( Exception $_e ) {
                $ev_date = gmdate( 'd/m/Y H:i:s', strtotime( $ev->created_at ) + 7200 );
            }
            $ev_detail = $this->truncate( $ev->details, 28 );
            $bg        = ( 'signed' === $ev->event ) ? $success_bg : '#ffffff';
            $e[] = array( 'type' => 'rect', 'x' => 42, 'y' => $y - 14, 'width' => 510, 'height' => 16, 'fill_color' => $bg, 'stroke_color' => $border, 'line_width' => .5 );
            $e[] = array( 'text' => $ev_date,  'x' => 48,  'y' => $y - 4, 'size' => 7.5, 'font' => 'Helvetica',      'color' => $muted );
            $e[] = array( 'text' => $ev_label, 'x' => 160, 'y' => $y - 4, 'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => '#1a202c' );
            $e[] = array( 'text' => $this->truncate( $ev->ip ?? '', 18 ), 'x' => 300, 'y' => $y - 4, 'size' => 7, 'font' => 'Helvetica', 'color' => $muted );
            $e[] = array( 'text' => $ev_detail, 'x' => 370, 'y' => $y - 4, 'size' => 7, 'font' => 'Helvetica', 'color' => $muted );
            $y -= 16;
        }

        // Signature manuscrite
        $y -= 20;
        $e[] = array( 'text' => 'APPOSITION DE LA SIGNATURE MANUSCRITE', 'x' => 42, 'y' => $y, 'size' => 9, 'font' => 'Helvetica-Bold', 'color' => $marine );
        $e[] = array( 'type' => 'rect', 'x' => 42, 'y' => $y - 2, 'width' => 115, 'height' => 2, 'fill_color' => $or );
        $y -= 14;

        if ( $sig_img_path && file_exists( $sig_img_path ) && function_exists( 'imagecreatefromstring' ) ) {
            $sig_img = $this->prepare_image( $sig_img_path, 200, 80 );
            if ( $sig_img ) {
                $e[] = array( 'type' => 'rect', 'x' => 42, 'y' => $y - 90, 'width' => 220, 'height' => 90, 'stroke_color' => $border, 'line_width' => 1 );
                $e[] = array( 'type' => 'image', 'image_key' => $sig_img['key'], 'image_data' => $sig_img['data'], 'image_width' => $sig_img['width'], 'image_height' => $sig_img['height'], 'display_width' => $sig_img['display_width'], 'display_height' => $sig_img['display_height'], 'x' => 52, 'y' => $y - 85 );
                $e[] = array( 'text' => $request->signer_name, 'x' => 42, 'y' => $y - 94,  'size' => 7.5, 'font' => 'Helvetica-Bold', 'color' => $muted );
                $e[] = array( 'text' => $signed_at,            'x' => 42, 'y' => $y - 104, 'size' => 7,   'font' => 'Helvetica',      'color' => '#999' );
                $y -= 110;
            } else {
                $e[] = array( 'text' => '[Image de signature non disponible]', 'x' => 42, 'y' => $y, 'size' => 8, 'font' => 'Helvetica', 'color' => '#c00' );
                $y -= 14;
            }
        } else {
            $e[] = array( 'text' => '[Signature non disponible]', 'x' => 42, 'y' => $y, 'size' => 8, 'font' => 'Helvetica', 'color' => $muted );
            $y -= 14;
        }

        // Niveau renforcé — mention vérification OTP e-mail
        if ( $is_renforce ) {
            $y -= 10;
            $e[] = array( 'text' => 'VERIFICATION D\'IDENTITE (NIVEAU RENFORCE)', 'x' => 42, 'y' => $y, 'size' => 9, 'font' => 'Helvetica-Bold', 'color' => $marine );
            $e[] = array( 'type' => 'rect', 'x' => 42, 'y' => $y - 2, 'width' => 130, 'height' => 2, 'fill_color' => $or );
            $y -= 16;
            $e[] = array( 'text' => 'Identite verifiee par code OTP envoye a l\'adresse e-mail du signataire', 'x' => 42, 'y' => $y, 'size' => 8, 'font' => 'Helvetica', 'color' => $marine );
            $y -= 12;
            $e[] = array( 'text' => 'Double authentification : validation du lien (e-mail) + code a usage unique (OTP)', 'x' => 42, 'y' => $y, 'size' => 8, 'font' => 'Helvetica', 'color' => $muted );
            $y -= 14;
        }

        // Pied de page
        $e[] = array( 'type' => 'rect', 'x' => 0, 'y' => 0,  'width' => $W, 'height' => 40,  'fill_color' => '#f5f7fa' );
        $e[] = array( 'type' => 'rect', 'x' => 0, 'y' => 40, 'width' => $W, 'height' => 1.5, 'fill_color' => $or );
        $e[] = array( 'text' => 'Ce certificat a ete genere automatiquement par ACDC Formation — acdc-formation.com', 'x' => 42, 'y' => 26, 'size' => 7.5, 'font' => 'Helvetica', 'color' => $muted );
        $e[] = array( 'text' => 'Identifiant : ' . $this->truncate( $request->token, 60 ), 'x' => 42, 'y' => 14, 'size' => 6.5, 'font' => 'Helvetica', 'color' => '#999' );

        return array( $e );
    }

    /* -----------------------------------------------------------------------
     * Rendu PDF en chaîne binaire
     * -------------------------------------------------------------------- */

    private function render_to_string( array $pages ) {
        $objects  = array();
        $page_ids = array();

        $add_object = static function( $content ) use ( &$objects ) {
            $objects[] = $content;
            return count( $objects );
        };

        $font_r = $add_object( '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>' );
        $font_b = $add_object( '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>' );

        $image_map = array();
        foreach ( $pages as $page_lines ) {
            foreach ( $page_lines as $line ) {
                if ( isset( $line['type'] ) && 'image' === $line['type']
                     && ! empty( $line['image_key'] ) && ! isset( $image_map[ $line['image_key'] ] )
                     && ! empty( $line['image_data'] ) ) {
                    $img_obj = '<< /Type /XObject /Subtype /Image'
                             . ' /Width '  . (int) $line['image_width']
                             . ' /Height ' . (int) $line['image_height']
                             . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode'
                             . ' /Length ' . strlen( $line['image_data'] )
                             . " >>\nstream\n" . $line['image_data'] . "\nendstream";
                    $image_map[ $line['image_key'] ] = array(
                        'name'      => 'Im' . ( count( $image_map ) + 1 ),
                        'object_id' => $add_object( $img_obj ),
                    );
                }
            }
        }

        $content_ids = $page_xobj = $page_sizes = array();
        foreach ( $pages as $page_lines ) {
            $stream = ''; $used = array(); $pw = 595; $ph = 842;
            foreach ( $page_lines as $line ) {
                $type = $line['type'] ?? 'text';
                if ( 'page_meta' === $type ) { if ( ! empty( $line['width'] ) ) $pw = (float) $line['width']; if ( ! empty( $line['height'] ) ) $ph = (float) $line['height']; continue; }
                if ( 'image' === $type ) {
                    if ( empty( $line['image_key'] ) || empty( $image_map[ $line['image_key'] ] ) ) continue;
                    $n = $image_map[ $line['image_key'] ]['name'];
                    $used[ $n ] = $image_map[ $line['image_key'] ]['object_id'];
                    $stream .= sprintf( "q %.2f 0 0 %.2f %.2f %.2f cm /%s Do Q\n", (float) $line['display_width'], (float) $line['display_height'], (float) $line['x'], (float) $line['y'], $n );
                    continue;
                }
                if ( 'rect' === $type ) {
                    $fill   = isset( $line['fill_color'] )   ? $this->hex_rgb( $line['fill_color'] )   : null;
                    $stroke = isset( $line['stroke_color'] ) ? $this->hex_rgb( $line['stroke_color'] ) : null;
                    $stream .= "q\n";
                    if ( $fill )   $stream .= sprintf( "%.4f %.4f %.4f rg\n", ...$fill );
                    if ( $stroke ) $stream .= sprintf( "%.4f %.4f %.4f RG\n", ...$stroke );
                    $stream .= sprintf( "%.2f w\n", (float) ( $line['line_width'] ?? 1 ) );
                    $stream .= sprintf( "%.2f %.2f %.2f %.2f re\n", (float) $line['x'], (float) $line['y'], (float) $line['width'], (float) $line['height'] );
                    $stream .= ( $fill && $stroke ) ? "B\n" : ( $fill ? "f\n" : "S\n" );
                    $stream .= "Q\n";
                    continue;
                }
                if ( ! isset( $line['text'] ) ) continue;
                $text = $line['text'];
                if ( is_array( $text ) ) {
                    $text = isset( $text['text'] ) ? $text['text'] : implode( ' ', array_map( 'strval', array_filter( $text, 'is_scalar' ) ) );
                }
                if ( is_object( $text ) && method_exists( $text, '__toString' ) ) {
                    $text = (string) $text;
                }
                $text = is_scalar( $text ) ? (string) $text : '';
                $tw = 0.0;
                if ( 0 === strpos( $text, '[[TW:' ) ) {
                    $tw_end = strpos( $text, ']]' );
                    if ( false !== $tw_end ) {
                        $tw = (float) substr( $text, 5, $tw_end - 5 );
                        $text = substr( $text, $tw_end + 2 );
                    }
                }
                $fid   = ( 'Helvetica-Bold' === ( $line['font'] ?? '' ) ) ? $font_b : $font_r;
                $color = isset( $line['color'] ) ? $this->hex_rgb( $line['color'] ) : array( 0, 0, 0 );
                $stream .= "BT\n" . sprintf( "%.4f %.4f %.4f rg\n", ...$color ) . sprintf( "/F%d %s Tf\n", $fid, (float) ( $line['size'] ?? 9 ) );
                if ( $tw > 0.001 ) {
                    $stream .= sprintf( "%.4f Tw\n", $tw );
                }
                $rx = (float) $line['x'];
                if ( ! empty( $line['center'] ) ) {
                    $lpw = isset( $line['page_w'] ) ? (float) $line['page_w'] : $pw;
                    $est_w = mb_strlen( $text, 'UTF-8' ) * 0.5 * (float) ( $line['size'] ?? 9 );
                    $rx = ( $lpw - $est_w ) / 2.0;
                }
                $stream .= sprintf( "1 0 0 1 %.2f %.2f Tm\n", $rx, (float) $line['y'] ) . '(' . $this->pdf_encode( $text ) . ") Tj\n";
                if ( $tw > 0.001 ) {
                    $stream .= "0 Tw\n";
                }
                $stream .= "ET\n";
            }
            $content_ids[] = $add_object( '<< /Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . "\nendstream" );
            $page_xobj[]   = $used;
            $page_sizes[]  = array( 'w' => $pw, 'h' => $ph );
        }

        foreach ( $content_ids as $i => $cid ) {
            $xobj = '';
            if ( ! empty( $page_xobj[ $i ] ) ) {
                $chunks = array();
                foreach ( $page_xobj[ $i ] as $n => $oid ) $chunks[] = "/$n $oid 0 R";
                $xobj = ' /XObject << ' . implode( ' ', $chunks ) . ' >>';
            }
            $pw = (float) ( $page_sizes[ $i ]['w'] ?? 595 );
            $ph = (float) ( $page_sizes[ $i ]['h'] ?? 842 );
            $page_ids[] = $add_object( "<< /Type /Page /Parent PAGES_ROOT /MediaBox [0 0 {$pw} {$ph}] /Resources << /Font << /F{$font_r} {$font_r} 0 R /F{$font_b} {$font_b} 0 R >>{$xobj} >> /Contents {$cid} 0 R >>" );
        }

        $kids = array_map( function( $p ) { return "$p 0 R"; }, $page_ids );
        $pr   = $add_object( '<< /Type /Pages /Count ' . count( $page_ids ) . ' /Kids [ ' . implode( ' ', $kids ) . ' ] >>' );
        foreach ( $page_ids as $i => $pid ) $objects[ $pid - 1 ] = str_replace( 'PAGES_ROOT', "$pr 0 R", $objects[ $pid - 1 ] );
        $cat = $add_object( "<< /Type /Catalog /Pages {$pr} 0 R >>" );

        $pdf = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
        $off = array( 0 );
        foreach ( $objects as $i => $obj ) { $off[] = strlen( $pdf ); $pdf .= ( $i + 1 ) . " 0 obj\n$obj\nendobj\n"; }
        $xp = strlen( $pdf );
        $pdf .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
        for ( $i = 1; $i <= count( $objects ); $i++ ) $pdf .= sprintf( "%010d 00000 n \n", $off[ $i ] );
        $pdf .= "trailer\n<< /Size " . ( count( $objects ) + 1 ) . " /Root {$cat} 0 R >>\nstartxref\n{$xp}\n%%EOF";
        return $pdf;
    }

    /* -----------------------------------------------------------------------
     * Utilitaires image et PDF
     * -------------------------------------------------------------------- */

    public function prepare_image( $path, $max_w = 200, $max_h = 100 ) {
        if ( empty( $path ) || ! file_exists( $path ) || ! function_exists( 'imagecreatefromstring' ) ) return null;
        $raw = @file_get_contents( $path );
        if ( ! $raw ) return null;
        $image = @imagecreatefromstring( $raw );
        if ( ! $image ) return null;
        $w = imagesx( $image ); $h = imagesy( $image );
        if ( ! $w || ! $h ) { imagedestroy( $image ); return null; }
        $ratio = min( $max_w / $w, $max_h / $h, 1 );
        $dw    = max( 1, (int) round( $w * $ratio ) );
        $dh    = max( 1, (int) round( $h * $ratio ) );
        ob_start(); imageinterlace( $image, true ); imagejpeg( $image, null, 88 ); $jpeg = ob_get_clean(); imagedestroy( $image );
        if ( ! $jpeg ) return null;
        return array( 'key' => md5( $jpeg ), 'data' => $jpeg, 'width' => $w, 'height' => $h, 'display_width' => $dw, 'display_height' => $dh );
    }

    private function hex_rgb( $hex ) {
        $hex = ltrim( (string) $hex, '#' );
        if ( 3 === strlen( $hex ) ) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        if ( 6 !== strlen( $hex ) ) return array( 0, 0, 0 );
        return array( hexdec( substr($hex,0,2) ) / 255, hexdec( substr($hex,2,2) ) / 255, hexdec( substr($hex,4,2) ) / 255 );
    }

    private function pdf_encode( $text ) {
        $text = (string) $text;
        $text = mb_convert_encoding( $text, 'Windows-1252', 'UTF-8' );
        return addcslashes( $text, '()\\' );
    }

    private function truncate( $text, $max ) {
        $text = (string) $text;
        return mb_strlen( $text ) <= $max ? $text : mb_substr( $text, 0, $max - 1 ) . '...';
    }

    /* -----------------------------------------------------------------------
     * Sauvegarde de la signature dessinée (canvas PNG base64)
     * Correction audit MOYEN : vérification MIME + taille
     * -------------------------------------------------------------------- */

    public function save_signature_image( $data_url, $request_id ) {
        // Correction audit CRITIQUE : vérifier la taille avant décodage
        if ( strlen( $data_url ) > ACDC_Sig_Core::MAX_SIG_DATA_BYTES * 1.4 ) {
            return '';
        }

        if ( ! preg_match( '/^data:image\/(\w+);base64,(.+)$/', $data_url, $matches ) ) {
            return '';
        }

        $ext = in_array( $matches[1], array( 'png', 'jpg', 'jpeg' ), true ) ? $matches[1] : 'png';
        $raw = base64_decode( $matches[2] );

        // Correction audit MOYEN : vérifier le MIME réel du binaire décodé
        if ( function_exists( 'finfo_open' ) ) {
            $finfo = new finfo( FILEINFO_MIME_TYPE );
            $mime  = $finfo->buffer( $raw );
            if ( ! in_array( $mime, array( 'image/png', 'image/jpeg', 'image/gif' ), true ) ) {
                return '';
            }
        }

        $upload_dir = wp_upload_dir();
        $sig_dir    = $upload_dir['basedir'] . '/acdc-signatures/' . $request_id . '/';
        $filename   = 'signature-' . $request_id . '-' . time() . '.' . $ext;
        $filepath   = $sig_dir . $filename;

        if ( ! $this->core->safe_file_write( $filepath, $raw, 'signature image' ) ) {
            return '';
        }

        return $filepath;
    }

    /* -----------------------------------------------------------------------
     * Sauvegarde de la photo de pièce d'identité
     * -------------------------------------------------------------------- */

    public function save_id_photo( array $file, $request_id ) {
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
        $finfo = new finfo( FILEINFO_MIME_TYPE );
        $mime  = $finfo->file( $file['tmp_name'] );
        if ( ! in_array( $mime, $allowed_types, true ) ) {
            return new WP_Error( 'invalid_type', "Type de fichier non autorisé pour la pièce d'identité." );
        }

        $upload_dir = wp_upload_dir();
        $sig_dir    = $upload_dir['basedir'] . '/acdc-signatures/' . $request_id . '/';
        // Sous-dossier dédié à la pièce d'identité, protégé par .htaccess
        $id_dir     = $sig_dir . 'id/';
        wp_mkdir_p( $id_dir );

        // Protection Apache : uniquement sur le sous-dossier /id/
        // Cela évite de bloquer le certificat PDF dans le dossier parent
        $htaccess = $id_dir . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            file_put_contents( $htaccess, "deny from all\n" );
        }

        $ext      = strtolower( preg_replace( '/[^a-z0-9]/i', '', pathinfo( $file['name'], PATHINFO_EXTENSION ) ?: 'jpg' ) );
        $filename = 'id-' . $request_id . '-' . time() . '.' . $ext;
        $filepath = $id_dir . $filename;
        $fileurl  = $upload_dir['baseurl'] . '/acdc-signatures/' . $request_id . '/id/' . $filename;

        if ( ! move_uploaded_file( $file['tmp_name'], $filepath ) ) {
            return new WP_Error( 'move_failed', 'Impossible de sauvegarder la photo.' );
        }

        return array( 'path' => $filepath, 'url' => $fileurl );
    }

    /* -----------------------------------------------------------------------
     * Upload PDF
     * -------------------------------------------------------------------- */

    public function upload_pdf( array $file ) {
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        add_filter( 'upload_mimes', array( $this, 'allow_pdf_mime' ) );
        $result = wp_handle_upload( $file, array( 'test_form' => false, 'mimes' => array( 'pdf' => 'application/pdf' ) ) );
        remove_filter( 'upload_mimes', array( $this, 'allow_pdf_mime' ) );
        if ( isset( $result['error'] ) ) {
            return new WP_Error( 'upload_failed', $result['error'] );
        }
        return $result;
    }

    public function allow_pdf_mime( $mimes ) {
        $mimes['pdf'] = 'application/pdf';
        return $mimes;
    }
}
