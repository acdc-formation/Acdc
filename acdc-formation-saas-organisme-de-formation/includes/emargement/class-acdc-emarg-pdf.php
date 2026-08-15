<?php
/**
 * ACDC Émargement Numérique — Générateur PDF feuille d'émargement
 *
 * Génère un PDF de feuille de présence numérique (preuve Qualiopi) incluant
 * les signatures du formateur et des apprenants.
 *
 * Moteur PDF natif PHP — aucune dépendance externe.
 *
 * @package ACDC_Formation_SAAS
 * @since   3.21.09-hotfix24
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ACDC_Emarg_PDF {

    /** @var ACDC_Emarg_Core */
    private $core;

    /* Dimensions A4 portrait en points */
    const PW = 595.0;
    const PH = 842.0;
    const ML = 45.0;  // marge gauche
    const MR = 45.0;  // marge droite
    const MT = 45.0;  // marge haut (depuis le bas en coords PDF = PH - MT)
    const MB = 40.0;  // marge bas

    /* Couleurs ACDC */
    const MARINE  = '#0f2c52';
    const GOLD    = '#c9a84c';
    const LGRAY   = '#f0f4f8';
    const DGRAY   = '#4b5d76';
    const SUCCESS = '#27ae60';
    const DANGER  = '#c0392b';
    const BORDER  = '#dce4ec';

    public function __construct( ACDC_Emarg_Core $core ) {
        $this->core = $core;
    }

    /* -----------------------------------------------------------------------
     * Point d'entrée public — génère et sert le PDF en téléchargement
     * -------------------------------------------------------------------- */
    public function serve_pdf( $session_id ) {
        $pdf = $this->get_pdf_content( $session_id );
        if ( ! $pdf ) { wp_die( 'Impossible de générer le PDF.', 500 ); }

        $filename    = 'emargement-seance-' . absint( $session_id ) . '-' . gmdate( 'Ymd' ) . '.pdf';
        $preview     = ! empty( $_GET['preview'] );
        $disposition = $preview ? 'inline' : 'attachment';
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: ' . $disposition . '; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $pdf ) );
        header( 'Cache-Control: no-cache, no-store' );
        echo $pdf;
        exit;
    }

    /* Retourne le contenu binaire du PDF émargement (sans header/exit) — utilisé par ZIP exporter */
    public function get_pdf_content( $session_id ) {
        $session_id = absint( $session_id );
        if ( ! $session_id ) { return false; }

        // Émargement par séance : on récupère toutes les feuilles signées de la session.
        // - 0 feuille signée              → false (identique à aujourd'hui : non signé).
        // - 1 seule feuille signée        → chemin legacy, sortie STRICTEMENT identique.
        // - plusieurs feuilles signées    → PDF multi-pages (une feuille par séance).
        $all_sheets    = $this->core->get_all_by_session_id( $session_id );
        $signed_sheets = array();
        foreach ( (array) $all_sheets as $sh ) {
            if ( 'signe' === $sh->trainer_status ) { $signed_sheets[] = $sh; }
        }
        if ( empty( $signed_sheets ) ) { return false; }

        // Feuille primaire (index le plus bas) pour le chemin mono-séance legacy.
        $emarg = $signed_sheets[0];

        global $wpdb;
        $session_table   = $wpdb->prefix . 'acdc_of_sessions';
        $formation_table = $wpdb->prefix . 'acdc_of_formations';

        $session = $wpdb->get_row( $wpdb->prepare(
            "SELECT s.*, f.title AS formation_title, f.code AS formation_code
             FROM {$session_table} s
             LEFT JOIN {$formation_table} f ON f.id = s.formation_id
             WHERE s.id = %d",
            $session_id
        ) );
        if ( ! $session ) { return false; }

        // ACDC 3.25.82 — Enrichir la séance depuis la convention et la formation si champs vides
        if ( empty( $session->start_at ) || empty( $session->location ) || empty( $session->session_format ) ) {
          $reg_table     = $wpdb->prefix . 'acdc_of_training_registrations';
          $contract_table = $wpdb->prefix . 'acdc_of_registration_contracts';
          // Chercher une inscription liée à cette séance ou cette formation
          $contract_data = $wpdb->get_row( $wpdb->prepare(
            "SELECT rc.seances_dates, rc.start_date, rc.end_date
             FROM {$contract_table} rc
             INNER JOIN {$reg_table} tr ON tr.autofill_contract_id = rc.id
             WHERE tr.formation_id = %d
             ORDER BY tr.updated_at DESC LIMIT 1",
            (int) $session->formation_id
          ) );
          // Fallback direct sur la formation
          $formation_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT address, postal_code, city, modality FROM {$formation_table} WHERE id = %d",
            (int) $session->formation_id
          ) );
          // Date : depuis start_date de la session, ou seances_dates de la convention
          if ( empty( $session->start_at ) ) {
            if ( ! empty( $session->start_date ) ) {
              $session->start_at = $session->start_date . ' 09:00:00';
            } elseif ( $contract_data && ! empty( $contract_data->seances_dates ) ) {
              $dates = array_values( array_filter( array_map( 'trim', explode( ',', $contract_data->seances_dates ) ) ) );
              if ( ! empty( $dates[0] ) ) { $session->start_at = $dates[0] . ' 09:00:00'; }
            } elseif ( $contract_data && ! empty( $contract_data->start_date ) ) {
              $session->start_at = $contract_data->start_date . ' 09:00:00';
            }
          }
          // Lieu : depuis la formation
          if ( empty( $session->location ) && $formation_row ) {
            $addr_parts = array_filter( array(
              ! empty( $formation_row->address )     ? $formation_row->address : '',
              ! empty( $formation_row->postal_code ) ? $formation_row->postal_code : '',
              ! empty( $formation_row->city )        ? $formation_row->city : '',
            ) );
            if ( ! empty( $addr_parts ) ) { $session->location = implode( ', ', $addr_parts ); }
          }
          // Format : depuis la formation
          if ( empty( $session->session_format ) && $formation_row && ! empty( $formation_row->modality ) ) {
            $session->session_format = (string) $formation_row->modality;
          }
        }

        /* ACDC 3.25.290 — Ce bloc refaisait à la main l'arbitrage entre la fiche
           entreprise et la fiche marque. Il le faisait juste ; il le faisait
           SEUL, et une règle recopiée finit toujours par diverger de l'originale. */
        $__id     = \ACDC\Support\OrgIdentity::fromOptions(
            get_option( 'acdc_of_company_profile', array() ),
            get_option( 'acdc_of_branding', array() )
        );
        $org_name = '' !== $__id['raison_sociale'] ? $__id['raison_sociale'] : get_bloginfo( 'name' );
        $org_nda  = $__id['nda'];
        $org_addr = \ACDC\Support\OrgIdentity::addressLine( $__id, ', ' );

        $n = count( $signed_sheets );

        // Chemin mono-séance (une seule feuille signée) : sortie STRICTEMENT identique
        // à l'existant — aucun bandeau séance, aucune surcharge de date.
        if ( 1 === $n ) {
            $learners = $this->core->get_learners_for_emarg( $emarg->id );
            $pages    = $this->build_pages( $emarg, $session, $learners, $org_name, $org_nda, $org_addr );
            return $this->render_to_string( $pages );
        }

        // Chemin multi-séances : une page (ou plus) par feuille signée, avec bandeau
        // « Séance k/N — date ». La date de séance provient des métadonnées de la feuille
        // si disponibles, sinon de la session.
        $all_pages = array();
        foreach ( $signed_sheets as $k => $sheet ) {
            $sess_for_sheet = clone $session;
            if ( ! empty( $sheet->seance_start_at ) ) {
                $sess_for_sheet->start_at = $sheet->seance_start_at;
                $sess_for_sheet->end_at   = ! empty( $sheet->seance_end_at ) ? $sheet->seance_end_at : $sess_for_sheet->end_at;
            }
            $seance_label = ! empty( $sheet->seance_label )
                ? $sheet->seance_label
                : sprintf( 'Séance %d/%d', (int) $k + 1, $n );
            $learners  = $this->core->get_learners_for_emarg( $sheet->id );
            $all_pages = array_merge(
                $all_pages,
                $this->build_pages( $sheet, $sess_for_sheet, $learners, $org_name, $org_nda, $org_addr, $seance_label )
            );
        }
        return $this->render_to_string( $all_pages );
    }

    /* -----------------------------------------------------------------------
     * Construction des pages
     * -------------------------------------------------------------------- */
    private function build_pages( $emarg, $session, $learners, $org_name, $org_nda, $org_addr, $seance_label = '' ) {
        $lines = array();
        $pw = self::PW;
        $ph = self::PH;
        $ml = self::ML;
        $mr = self::MR;
        $cw = $pw - $ml - $mr; // largeur de contenu

        $lines[] = array( 'type' => 'page_meta', 'width' => $pw, 'height' => $ph );

        /* ---- Bandeau header ---- */
        $header_h = 62.0;
        $header_y = $ph - self::MT - $header_h;
        $lines[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $header_y, 'width' => $cw, 'height' => $header_h, 'fill_color' => self::MARINE );
        /* Titre */
        $lines[] = array( 'type' => 'text', 'text' => 'FEUILLE D\'EMARGEMENT NUMERIQUE', 'font' => 'Helvetica-Bold', 'size' => 14, 'color' => '#ffffff', 'x' => $ml + 14, 'y' => $header_y + 38 );
        $lines[] = array( 'type' => 'text', 'text' => 'Preuve de presence - ' . $org_name, 'font' => 'Helvetica', 'size' => 9, 'color' => self::GOLD, 'x' => $ml + 14, 'y' => $header_y + 20 );
        /* Date génération */
        $gen_date = 'Genere le ' . wp_date( 'd/m/Y a H\hi' );
        $lines[] = array( 'type' => 'text', 'text' => $gen_date, 'font' => 'Helvetica', 'size' => 8, 'color' => '#aabbd0', 'x' => $ml + $cw - 160, 'y' => $header_y + 20 );

        $y = $header_y - 20;

        /* ---- Bandeau séance (uniquement en multi-séances) ----
         * Rendu SEULEMENT si $seance_label est fourni : le cas mono-séance
         * (label vide) ne pousse aucune ligne → mise en page identique à l'existant. */
        if ( '' !== (string) $seance_label ) {
            $seance_bar_h = 16.0;
            $lines[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $y - $seance_bar_h + 12, 'width' => $cw, 'height' => $seance_bar_h, 'fill_color' => self::GOLD );
            $lines[] = array( 'type' => 'text', 'text' => $this->truncate( (string) $seance_label, 90 ), 'font' => 'Helvetica-Bold', 'size' => 9, 'color' => self::MARINE, 'x' => $ml + 6, 'y' => $y );
            $y -= ( $seance_bar_h + 8 );
        }

        /* ---- Section organisme ---- */
        if ( $org_nda || $org_addr ) {
            $lines[] = array( 'type' => 'text', 'text' => $org_name, 'font' => 'Helvetica-Bold', 'size' => 9, 'color' => self::MARINE, 'x' => $ml, 'y' => $y );
            $y -= 13;
            if ( $org_addr ) {
                $lines[] = array( 'type' => 'text', 'text' => $org_addr, 'font' => 'Helvetica', 'size' => 8, 'color' => self::DGRAY, 'x' => $ml, 'y' => $y );
                $y -= 12;
            }
            if ( $org_nda ) {
                $lines[] = array( 'type' => 'text', 'text' => 'NDA : ' . $org_nda, 'font' => 'Helvetica', 'size' => 8, 'color' => self::DGRAY, 'x' => $ml, 'y' => $y );
                $y -= 12;
            }
            $y -= 6;
            $lines[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $y, 'width' => $cw, 'height' => 0.5, 'fill_color' => self::BORDER );
            $y -= 14;
        }

        /* ---- Section séance ---- */
        $formation_label = ! empty( $session->formation_title ) ? $session->formation_title : '—';
        if ( ! empty( $session->formation_code ) ) { $formation_label .= '  (' . $session->formation_code . ')'; }
        if ( ! empty( $session->start_at ) ) {
            $date_label = mysql2date( 'd/m/Y', $session->start_at );
            if ( ! empty( $session->end_at ) && gmdate( 'Y-m-d', strtotime( $session->end_at ) ) !== gmdate( 'Y-m-d', strtotime( $session->start_at ) ) ) {
                $date_label .= ' au ' . mysql2date( 'd/m/Y', $session->end_at );
            }
            $heure_debut = mysql2date( 'H\hi', $session->start_at );
            $heure_fin   = ! empty( $session->end_at ) ? mysql2date( 'H\hi', $session->end_at ) : '';
            $date_label .= '  |  ' . $heure_debut . ( $heure_fin ? ' - ' . $heure_fin : '' );
        }

        $location_label = ! empty( $session->location ) ? $session->location : '—';
        $format_label   = ! empty( $session->session_format ) ? $session->session_format : '—';

        /* Bandeau titre section */
        $sb_h = 18.0;
        $lines[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $y - $sb_h + 14, 'width' => $cw, 'height' => $sb_h, 'fill_color' => self::LGRAY );
        $lines[] = array( 'type' => 'text', 'text' => 'INFORMATIONS DE LA SEANCE', 'font' => 'Helvetica-Bold', 'size' => 8, 'color' => self::MARINE, 'x' => $ml + 6, 'y' => $y + 1 );
        $y -= ( $sb_h + 4 );

        $col2 = $ml + $cw * 0.5;
        // Formation sur toute la largeur avec libellé + valeur
        $lines[] = array( 'type' => 'text', 'text' => 'Formation :', 'font' => 'Helvetica-Bold', 'size' => 8, 'color' => self::MARINE, 'x' => $ml, 'y' => $y );
        $lines[] = array( 'type' => 'text', 'text' => $this->truncate( $formation_label, 110 ), 'font' => 'Helvetica', 'size' => 8, 'color' => self::DGRAY, 'x' => $ml + 55, 'y' => $y );
        $y -= 14;
        $this->field_line( $lines, $ml, $y, 'Date(s)', $date_label, 8 );
        $this->field_line( $lines, $col2, $y, 'Formateur', $emarg->trainer_name, 8 );  $y -= 14;
        $this->field_line( $lines, $ml, $y, 'Lieu', $location_label, 8 );
        $this->field_line( $lines, $col2, $y, 'Format', $format_label, 8 );  $y -= 18;

        $lines[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $y, 'width' => $cw, 'height' => 0.5, 'fill_color' => self::BORDER );
        $y -= 14;

        /* ---- Section signature formateur ---- */
        $lines[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $y - $sb_h + 14, 'width' => $cw, 'height' => $sb_h, 'fill_color' => self::LGRAY );
        $lines[] = array( 'type' => 'text', 'text' => 'SIGNATURE DU FORMATEUR', 'font' => 'Helvetica-Bold', 'size' => 8, 'color' => self::MARINE, 'x' => $ml + 6, 'y' => $y + 1 );
        $y -= ( $sb_h + 6 );

        $signed_at_label = ! empty( $emarg->trainer_signed_at ) ? mysql2date( 'd/m/Y a H\hi', $emarg->trainer_signed_at ) : '';
        $lines[] = array( 'type' => 'text', 'text' => $emarg->trainer_name, 'font' => 'Helvetica-Bold', 'size' => 9, 'color' => self::MARINE, 'x' => $ml, 'y' => $y );
        if ( $signed_at_label ) {
            $lines[] = array( 'type' => 'text', 'text' => 'Signe le ' . $signed_at_label, 'font' => 'Helvetica', 'size' => 8, 'color' => self::DGRAY, 'x' => $ml, 'y' => $y - 12 );
        }

        /* Signature PNG formateur */
        $trainer_sig_img = null;
        if ( ! empty( $emarg->trainer_sig_path ) && file_exists( $emarg->trainer_sig_path ) ) {
            $trainer_sig_img = $this->prepare_image( $emarg->trainer_sig_path, 160, 50 );
        }
        if ( $trainer_sig_img ) {
            $lines[] = array(
                'type'          => 'image',
                'image_key'     => $trainer_sig_img['key'],
                'image_data'    => $trainer_sig_img['data'],
                'image_width'   => $trainer_sig_img['width'],
                'image_height'  => $trainer_sig_img['height'],
                'display_width' => $trainer_sig_img['display_width'],
                'display_height'=> $trainer_sig_img['display_height'],
                'x'             => $ml + $cw - $trainer_sig_img['display_width'] - 10,
                'y'             => $y - $trainer_sig_img['display_height'] + 4,
            );
        }
        $y -= 56;
        $lines[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $y, 'width' => $cw, 'height' => 0.5, 'fill_color' => self::BORDER );
        $y -= 14;

        /* ---- Tableau apprenants ---- */
        $lines[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $y - $sb_h + 14, 'width' => $cw, 'height' => $sb_h, 'fill_color' => self::LGRAY );
        $lines[] = array( 'type' => 'text', 'text' => 'LISTE DES APPRENANTS', 'font' => 'Helvetica-Bold', 'size' => 8, 'color' => self::MARINE, 'x' => $ml + 6, 'y' => $y + 1 );
        $y -= ( $sb_h + 4 );

        /* En-tête tableau */
        $col_name   = $ml;
        $col_status = $ml + $cw * 0.44;
        $col_time   = $ml + $cw * 0.58;
        $col_sig    = $ml + $cw * 0.70;
        $col_w_sig  = $cw * 0.30;
        $th_h = 16.0;
        $lines[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $y - $th_h + 12, 'width' => $cw, 'height' => $th_h, 'fill_color' => self::MARINE );
        $lines[] = array( 'type' => 'text', 'text' => 'NOM DE L\'APPRENANT',       'font' => 'Helvetica-Bold', 'size' => 7, 'color' => '#ffffff', 'x' => $col_name + 4,   'y' => $y - 2 );
        $lines[] = array( 'type' => 'text', 'text' => 'STATUT',    'font' => 'Helvetica-Bold', 'size' => 7, 'color' => '#ffffff', 'x' => $col_status + 4, 'y' => $y - 2 );
        $lines[] = array( 'type' => 'text', 'text' => 'HEURE',     'font' => 'Helvetica-Bold', 'size' => 7, 'color' => '#ffffff', 'x' => $col_time + 4,   'y' => $y - 2 );
        $lines[] = array( 'type' => 'text', 'text' => 'SIGNATURE', 'font' => 'Helvetica-Bold', 'size' => 7, 'color' => '#ffffff', 'x' => $col_sig + 4,    'y' => $y - 2 );
        $y -= ( $th_h + 4 );

        /* Lignes apprenants */
        $pages_arr = array();
        $current_page_lines = $lines;
        $row_h = 42.0;

        foreach ( $learners as $idx => $lr ) {
            /* Nouvelle page si nécessaire */
            if ( $y - $row_h < self::MB + 30 ) {
                $pages_arr[] = $current_page_lines;
                $current_page_lines = array();
                $current_page_lines[] = array( 'type' => 'page_meta', 'width' => $pw, 'height' => $ph );
                $y = $ph - self::MT;
                /* Répéter l'en-tête tableau sur la nouvelle page */
                $current_page_lines[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $y - $th_h + 12, 'width' => $cw, 'height' => $th_h, 'fill_color' => self::MARINE );
                $current_page_lines[] = array( 'type' => 'text', 'text' => 'NOM DE L\'APPRENANT', 'font' => 'Helvetica-Bold', 'size' => 7, 'color' => '#ffffff', 'x' => $col_name + 4, 'y' => $y - 2 );
                $current_page_lines[] = array( 'type' => 'text', 'text' => 'STATUT',    'font' => 'Helvetica-Bold', 'size' => 7, 'color' => '#ffffff', 'x' => $col_status + 4, 'y' => $y - 2 );
                $current_page_lines[] = array( 'type' => 'text', 'text' => 'HEURE',     'font' => 'Helvetica-Bold', 'size' => 7, 'color' => '#ffffff', 'x' => $col_time + 4, 'y' => $y - 2 );
                $current_page_lines[] = array( 'type' => 'text', 'text' => 'SIGNATURE', 'font' => 'Helvetica-Bold', 'size' => 7, 'color' => '#ffffff', 'x' => $col_sig + 4, 'y' => $y - 2 );
                $y -= ( $th_h + 4 );
            }

            $is_signed = 'signe'  === $lr->status;
            $is_absent = 'absent' === $lr->status;
            $row_bg    = ( $idx % 2 === 0 ) ? '#ffffff' : self::LGRAY;
            $status_label = $is_absent ? 'Absent' : ( $is_signed ? 'Present' : 'En attente' );
            $status_color = $is_absent ? self::DANGER : ( $is_signed ? self::SUCCESS : '#856404' );
            $heure_label  = ( $is_signed && ! empty( $lr->signed_at ) ) ? mysql2date( 'H\hi', $lr->signed_at ) : '—';

            /* Ligne de fond */
            $current_page_lines[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $y - $row_h + 12, 'width' => $cw, 'height' => $row_h, 'fill_color' => $row_bg );
            /* Séparateur bas */
            $current_page_lines[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $y - $row_h + 12, 'width' => $cw, 'height' => 0.5, 'fill_color' => self::BORDER );

            $row_text_y = $y - 2;
            $current_page_lines[] = array( 'type' => 'text', 'text' => $this->truncate( $lr->learner_name, 40 ), 'font' => 'Helvetica', 'size' => 8, 'color' => self::MARINE, 'x' => $col_name + 4, 'y' => $row_text_y );
            $current_page_lines[] = array( 'type' => 'text', 'text' => $status_label, 'font' => 'Helvetica-Bold', 'size' => 8, 'color' => $status_color, 'x' => $col_status + 4, 'y' => $row_text_y );
            $current_page_lines[] = array( 'type' => 'text', 'text' => $heure_label,  'font' => 'Helvetica', 'size' => 8, 'color' => self::DGRAY, 'x' => $col_time + 4, 'y' => $row_text_y );

            /* Signature PNG apprenant */
            if ( $is_signed && ! empty( $lr->sig_path ) && file_exists( $lr->sig_path ) ) {
                $sig_img = $this->prepare_image( $lr->sig_path, (int) round( $col_w_sig * 0.9 ), 30 );
                if ( $sig_img ) {
                    $current_page_lines[] = array(
                        'type'           => 'image',
                        'image_key'      => $sig_img['key'],
                        'image_data'     => $sig_img['data'],
                        'image_width'    => $sig_img['width'],
                        'image_height'   => $sig_img['height'],
                        'display_width'  => $sig_img['display_width'],
                        'display_height' => $sig_img['display_height'],
                        'x'              => $col_sig + 4,
                        'y'              => $y - $sig_img['display_height'] + 4,
                    );
                }
            }
            $y -= $row_h;
        }

        /* ---- Pied de page ---- */
        $footer_y = self::MB;
        $current_page_lines[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $footer_y + 10, 'width' => $cw, 'height' => 0.5, 'fill_color' => self::BORDER );
        $current_page_lines[] = array( 'type' => 'text', 'text' => 'Document genere automatiquement par ACDC Formation — ' . wp_date( 'd/m/Y a H\hi' ) . ' — Preuve Qualiopi', 'font' => 'Helvetica', 'size' => 7, 'color' => self::DGRAY, 'x' => $ml, 'y' => $footer_y );

        $pages_arr[] = $current_page_lines;
        return $pages_arr;
    }

    /* -----------------------------------------------------------------------
     * Utilitaire : affiche label + valeur sur une ligne
     * -------------------------------------------------------------------- */
    private function field_line( &$lines, $x, $y, $label, $value, $size = 8 ) {
        $lines[] = array( 'type' => 'text', 'text' => $label . ' :', 'font' => 'Helvetica-Bold', 'size' => $size, 'color' => self::MARINE, 'x' => $x, 'y' => $y );
        $lines[] = array( 'type' => 'text', 'text' => $this->truncate( (string) $value, 55 ), 'font' => 'Helvetica', 'size' => $size, 'color' => self::DGRAY, 'x' => $x + 55, 'y' => $y );
    }

    /* -----------------------------------------------------------------------
     * Moteur PDF natif (auto-suffisant, copie de class-acdc-sig-pdf.php)
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
            $stream = ''; $used = array(); $pw = self::PW; $ph = self::PH;
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
                $text = is_scalar( $line['text'] ) ? (string) $line['text'] : '';
                $fid  = ( 'Helvetica-Bold' === ( $line['font'] ?? '' ) ) ? $font_b : $font_r;
                $color = isset( $line['color'] ) ? $this->hex_rgb( $line['color'] ) : array( 0, 0, 0 );
                $stream .= "BT\n" . sprintf( "%.4f %.4f %.4f rg\n", ...$color ) . sprintf( "/F%d %s Tf\n", $fid, (float) ( $line['size'] ?? 9 ) );
                $stream .= sprintf( "1 0 0 1 %.2f %.2f Tm\n", (float) $line['x'], (float) $line['y'] ) . '(' . $this->pdf_encode( $text ) . ") Tj\n";
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
            $ppw = (float) ( $page_sizes[ $i ]['w'] ?? self::PW );
            $pph = (float) ( $page_sizes[ $i ]['h'] ?? self::PH );
            $page_ids[] = $add_object( "<< /Type /Page /Parent PAGES_ROOT /MediaBox [0 0 {$ppw} {$pph}] /Resources << /Font << /F{$font_r} {$font_r} 0 R /F{$font_b} {$font_b} 0 R >>{$xobj} >> /Contents {$cid} 0 R >>" );
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

    private function prepare_image( $path, $max_w = 200, $max_h = 60 ) {
        if ( ! function_exists( 'imagecreatefromstring' ) ) { return null; }
        $raw = @file_get_contents( $path );
        if ( ! $raw ) { return null; }
        $image = @imagecreatefromstring( $raw );
        if ( ! $image ) { return null; }
        $w = imagesx( $image ); $h = imagesy( $image );
        if ( ! $w || ! $h ) { imagedestroy( $image ); return null; }
        $ratio = min( $max_w / $w, $max_h / $h, 1 );
        $dw    = max( 1, (int) round( $w * $ratio ) );
        $dh    = max( 1, (int) round( $h * $ratio ) );
        ob_start(); imageinterlace( $image, true ); imagejpeg( $image, null, 88 ); $jpeg = ob_get_clean(); imagedestroy( $image );
        if ( ! $jpeg ) { return null; }
        return array( 'key' => md5( $jpeg ), 'data' => $jpeg, 'width' => $w, 'height' => $h, 'display_width' => $dw, 'display_height' => $dh );
    }

    private function hex_rgb( $hex ) {
        $hex = ltrim( (string) $hex, '#' );
        if ( 3 === strlen( $hex ) ) { $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]; }
        if ( 6 !== strlen( $hex ) ) { return array( 0, 0, 0 ); }
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
     * Certificat de signature électronique — émargement
     * Génère un PDF preuve identique au module sig pour un signataire emarg.
     * $type : 'trainer' | 'learner'
     * $id   : id dans la table correspondante
     * -------------------------------------------------------------------- */
    public function serve_signature_cert( $type, $id, $preview = false ) {
        $pdf = $this->get_cert_content( $type, $id );
        if ( ! $pdf ) { wp_die( 'Signature introuvable.', 400 ); }

        $filename    = 'certificat-emargement-' . sanitize_title( $type ) . '-' . absint( $id ) . '-' . gmdate('Ymd') . '.pdf';
        $disposition = $preview ? 'inline' : 'attachment';
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: ' . $disposition . '; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $pdf ) );
        header( 'Cache-Control: no-cache, no-store' );
        echo $pdf;
        exit;
    }

    /* Retourne le contenu binaire du certificat (sans header/exit) — utilisé par ZIP exporter */
    public function get_cert_content( $type, $id ) {
        global $wpdb;
        $id = absint( $id );
        if ( ! $id ) { return false; }

        $st = $wpdb->prefix . 'acdc_of_sessions';
        $ft = $wpdb->prefix . 'acdc_of_formations';

        if ( 'trainer' === $type ) {
            $row = $wpdb->get_row( $wpdb->prepare(
                "SELECT es.*, s.title AS session_title, s.start_at, f.title AS formation_title
                 FROM {$this->core->table_sessions} es
                 LEFT JOIN {$st} s ON s.id = es.session_id
                 LEFT JOIN {$ft} f ON f.id = s.formation_id
                 WHERE es.id = %d AND es.trainer_status = 'signe'",
                $id
            ) );
            if ( ! $row ) { return false; }
            $signer_name  = $row->trainer_name;
            $signer_email = $row->trainer_email ?: '';
            $signer_role  = 'Formateur';
            $signed_at    = $row->trainer_signed_at;
            $sig_path     = $row->trainer_sig_path;
            $signer_ip    = isset( $row->trainer_ip ) ? $row->trainer_ip : '—';
            $signer_ua    = isset( $row->trainer_ua ) ? $row->trainer_ua : '—';
            $token        = $row->trainer_token;
        } else {
            $el = $wpdb->prefix . 'acdc_of_emarg_learners';
            $es = $this->core->table_sessions;
            $row = $wpdb->get_row( $wpdb->prepare(
                "SELECT l.*, s.title AS session_title, s.start_at, f.title AS formation_title
                 FROM {$el} l
                 LEFT JOIN {$es} es ON es.id = l.emarg_session_id
                 LEFT JOIN {$st} s ON s.id = es.session_id
                 LEFT JOIN {$ft} f ON f.id = s.formation_id
                 WHERE l.id = %d AND l.status = 'signe'",
                $id
            ) );
            if ( ! $row ) { return false; }
            $signer_name  = $row->learner_name;
            $signer_email = $row->learner_email ?: '';
            $signer_role  = 'Stagiaire';
            $signed_at    = $row->signed_at;
            $sig_path     = $row->sig_path;
            $signer_ip    = ! empty( $row->ip ) ? $row->ip : '—';
            $signer_ua    = isset( $row->learner_ua ) ? $row->learner_ua : '—';
            $token        = $row->sign_token;
        }

        $__id     = \ACDC\Support\OrgIdentity::fromOptions(
            get_option( 'acdc_of_company_profile', array() ),
            get_option( 'acdc_of_branding', array() )
        );
        $org_name = '' !== $__id['raison_sociale'] ? $__id['raison_sociale'] : get_bloginfo( 'name' );
        $org_site = '' !== $__id['site'] ? $__id['site'] : home_url();

        $formation_label = ! empty( $row->formation_title ) ? $row->formation_title : '—';
        $doc_type_label  = 'Feuille d\'emargement';
        $issued_label    = mysql2date( 'd/m/Y \a H\hi', $signed_at );
        $uid_short       = substr( sha1( $token . $signed_at . $signer_name ), 0, 48 ) . '...';

        $pages = $this->build_cert_pages(
            $signer_name, $signer_email, $signer_role,
            $doc_type_label, $formation_label,
            $issued_label, $signed_at,
            $signer_ip, $signer_ua, $uid_short,
            $sig_path, $org_name, $org_site, $token
        );
        return $this->render_to_string( $pages );
    }

    private function build_cert_pages(
        $signer_name, $signer_email, $signer_role,
        $doc_type_label, $formation_label,
        $issued_label, $signed_at,
        $ip, $ua, $uid_short,
        $sig_path, $org_name, $org_site, $token
    ) {
        $lines = array();
        $pw = self::PW; $ph = self::PH;
        $ml = self::ML; $mr = self::MR;
        $cw = $pw - $ml - $mr;
        $lines[] = array( 'type' => 'page_meta', 'width' => $pw, 'height' => $ph );

        /* ---- Bandeau header ---- */
        $hh = 68.0;
        $hy = $ph - self::MT - $hh;
        $lines[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $hy, 'width' => $cw, 'height' => $hh, 'fill_color' => self::MARINE );
        $lines[] = array( 'type' => 'text', 'text' => 'CERTIFICAT DE SIGNATURE ELECTRONIQUE', 'font' => 'Helvetica-Bold', 'size' => 14, 'color' => '#ffffff', 'x' => $ml + 14, 'y' => $hy + 44 );
        $lines[] = array( 'type' => 'text', 'text' => strtoupper( $doc_type_label ), 'font' => 'Helvetica', 'size' => 9, 'color' => self::GOLD, 'x' => $ml + 14, 'y' => $hy + 26 );
        $lines[] = array( 'type' => 'text', 'text' => 'Emis le ' . $issued_label, 'font' => 'Helvetica', 'size' => 8, 'color' => '#aabbd0', 'x' => $ml + 14, 'y' => $hy + 12 );

        /* Encart "Document signé" en haut droite */
        $bx = $pw - $mr - 140; $by = $hy + 8;
        $lines[] = array( 'type' => 'rect', 'x' => $bx, 'y' => $by, 'width' => 135, 'height' => 48, 'fill_color' => '#1a4a7a' );
        $lines[] = array( 'type' => 'text', 'text' => 'DOCUMENT SIGNE', 'font' => 'Helvetica-Bold', 'size' => 7, 'color' => '#ffffff', 'x' => $bx + 6, 'y' => $by + 34 );
        $lines[] = array( 'type' => 'text', 'text' => 'ELECTRONIQUEMENT', 'font' => 'Helvetica-Bold', 'size' => 7, 'color' => '#ffffff', 'x' => $bx + 6, 'y' => $by + 24 );
        $signed_label = mysql2date( 'd/m/Y \a H\hi', $signed_at );
        $lines[] = array( 'type' => 'text', 'text' => $signed_label, 'font' => 'Helvetica', 'size' => 8, 'color' => self::GOLD, 'x' => $bx + 6, 'y' => $by + 10 );

        $y = $hy - 22;

        /* ---- Helper section header ---- */
        $section = function( &$lines, $title, &$y, $ml, $cw ) {
            $y -= 4;
            $lines[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $y - 14, 'width' => $cw, 'height' => 18, 'fill_color' => '#f0f4f8' );
            $lines[] = array( 'type' => 'text', 'text' => $title, 'font' => 'Helvetica-Bold', 'size' => 8, 'color' => self::MARINE, 'x' => $ml + 6, 'y' => $y - 1 );
            $y -= 22;
        };

        /* ---- Identité signataire ---- */
        $section( $lines, 'IDENTITE DU SIGNATAIRE', $y, $ml, $cw );
        $this->cert_row( $lines, $ml, $cw, $y, 'Nom et prenom', $signer_name );   $y -= 14;
        $this->cert_row( $lines, $ml, $cw, $y, 'Adresse e-mail', $signer_email ?: '—' ); $y -= 14;
        $this->cert_row( $lines, $ml, $cw, $y, 'Qualite / role', $signer_role );  $y -= 18;

        /* ---- Métadonnées ---- */
        $section( $lines, 'METADONNEES DE SIGNATURE', $y, $ml, $cw );
        $this->cert_row( $lines, $ml, $cw, $y, 'Type de document', $doc_type_label );    $y -= 14;
        $this->cert_row( $lines, $ml, $cw, $y, 'Formation', $this->truncate( $formation_label, 60 ) ); $y -= 14;
        $this->cert_row( $lines, $ml, $cw, $y, 'Date et heure', $signed_label );          $y -= 14;
        $this->cert_row( $lines, $ml, $cw, $y, 'Adresse IP', $ip );                       $y -= 14;
        $this->cert_row( $lines, $ml, $cw, $y, 'Niveau tracabilite', 'Simple' );          $y -= 14;
        $this->cert_row( $lines, $ml, $cw, $y, 'Identifiant unique', $this->truncate( $uid_short, 58 ) ); $y -= 14;
        $this->cert_row( $lines, $ml, $cw, $y, 'Navigateur / appareil', $this->truncate( $ua, 60 ) );  $y -= 18;

        /* ---- Journal d'audit ---- */
        $section( $lines, 'JOURNAL D\'AUDIT', $y, $ml, $cw );
        // En-tête tableau
        $lines[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $y - 14, 'width' => $cw, 'height' => 18, 'fill_color' => self::MARINE );
        $lines[] = array( 'type' => 'text', 'text' => 'Date / Heure', 'font' => 'Helvetica-Bold', 'size' => 7, 'color' => '#ffffff', 'x' => $ml + 4, 'y' => $y - 1 );
        $lines[] = array( 'type' => 'text', 'text' => 'Evenement', 'font' => 'Helvetica-Bold', 'size' => 7, 'color' => '#ffffff', 'x' => $ml + 130, 'y' => $y - 1 );
        $lines[] = array( 'type' => 'text', 'text' => 'IP', 'font' => 'Helvetica-Bold', 'size' => 7, 'color' => '#ffffff', 'x' => $ml + 230, 'y' => $y - 1 );
        $lines[] = array( 'type' => 'text', 'text' => 'Detail', 'font' => 'Helvetica-Bold', 'size' => 7, 'color' => '#ffffff', 'x' => $ml + 310, 'y' => $y - 1 );
        $y -= 22;
        // Lignes journal
        $events = array(
            array( 'date' => $issued_label,                                         'event' => 'Demande creee',    'ip' => $ip, 'detail' => 'Demande creee.' ),
            array( 'date' => mysql2date( 'd/m/Y \a H\hi', $signed_at ),  'event' => 'Lien ouvert',      'ip' => $ip, 'detail' => 'Lien ouvert.' ),
            array( 'date' => $signed_label,                                         'event' => 'Document signe',   'ip' => $ip, 'detail' => 'Document signe avec succes.' ),
        );
        foreach ( $events as $idx => $ev ) {
            $row_bg = ( $idx % 2 === 0 ) ? '#ffffff' : self::LGRAY;
            $lines[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $y - 12, 'width' => $cw, 'height' => 16, 'fill_color' => $row_bg );
            $lines[] = array( 'type' => 'text', 'text' => $ev['date'],   'font' => 'Helvetica', 'size' => 7, 'color' => self::DGRAY, 'x' => $ml + 4,   'y' => $y );
            $lines[] = array( 'type' => 'text', 'text' => $ev['event'],  'font' => 'Helvetica-Bold', 'size' => 7, 'color' => self::MARINE, 'x' => $ml + 130, 'y' => $y );
            $lines[] = array( 'type' => 'text', 'text' => $ev['ip'],     'font' => 'Helvetica', 'size' => 7, 'color' => self::DGRAY, 'x' => $ml + 230, 'y' => $y );
            $lines[] = array( 'type' => 'text', 'text' => $ev['detail'], 'font' => 'Helvetica', 'size' => 7, 'color' => self::DGRAY, 'x' => $ml + 310, 'y' => $y );
            $y -= 16;
        }
        $y -= 10;

        /* ---- Signature manuscrite ---- */
        $section( $lines, 'APPOSITION DE LA SIGNATURE MANUSCRITE', $y, $ml, $cw );
        $sig_img = null;
        if ( ! empty( $sig_path ) && file_exists( $sig_path ) ) {
            $sig_img = $this->prepare_image( $sig_path, 180, 60 );
        }
        if ( $sig_img ) {
            $lines[] = array(
                'type'          => 'image',
                'image_key'     => $sig_img['key'],
                'image_data'    => $sig_img['data'],
                'image_width'   => $sig_img['width'],
                'image_height'  => $sig_img['height'],
                'display_width' => $sig_img['display_width'],
                'display_height'=> $sig_img['display_height'],
                'x'             => $ml,
                'y'             => $y - $sig_img['display_height'] - 8,
            );
            $y -= ( $sig_img['display_height'] + 12 );
        }
        $lines[] = array( 'type' => 'text', 'text' => $signer_name, 'font' => 'Helvetica-Bold', 'size' => 9, 'color' => self::GOLD, 'x' => $ml, 'y' => $y );
        $y -= 13;
        $lines[] = array( 'type' => 'text', 'text' => $signed_label, 'font' => 'Helvetica', 'size' => 8, 'color' => self::DGRAY, 'x' => $ml, 'y' => $y );

        /* ---- Pied de page ---- */
        $lines[] = array( 'type' => 'rect', 'x' => $ml, 'y' => self::MB + 12, 'width' => $cw, 'height' => 0.5, 'fill_color' => self::BORDER );
        $lines[] = array( 'type' => 'text', 'text' => 'Ce certificat a ete genere automatiquement par ' . $org_name . ' — ' . $org_site, 'font' => 'Helvetica', 'size' => 7, 'color' => self::DGRAY, 'x' => $ml, 'y' => self::MB + 4 );
        $lines[] = array( 'type' => 'text', 'text' => 'Identifiant : ' . $uid_short, 'font' => 'Helvetica', 'size' => 6, 'color' => '#9ca3af', 'x' => $ml, 'y' => self::MB - 5 );

        return array( $lines );
    }

    private function cert_row( &$lines, $ml, $cw, $y, $label, $value ) {
        $col2 = $ml + 150;
        $lines[] = array( 'type' => 'rect', 'x' => $ml, 'y' => $y - 10, 'width' => $cw, 'height' => 14, 'fill_color' => '#f8f9fa' );
        $lines[] = array( 'type' => 'text', 'text' => $label, 'font' => 'Helvetica-Bold', 'size' => 8, 'color' => self::MARINE, 'x' => $ml + 6, 'y' => $y );
        $lines[] = array( 'type' => 'text', 'text' => $this->truncate( (string) $value, 55 ), 'font' => 'Helvetica', 'size' => 8, 'color' => self::DGRAY, 'x' => $col2, 'y' => $y );
    }
}
