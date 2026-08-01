<?php
/**
 * ACDC SAAS OF — Générateur CERFA BPF
 * Version : 3.21.123
 * PHP 7.3+ — zéro dépendance externe
 * Fond JPEG hébergé dans la médiathèque WP (1768x2500 px)
 * Coordonnées texte en points PDF (595.28 x 841.89), origine haut-gauche — yt +10 appliqué globalement
 * Conversion pour _build_simple_pdf_string : x inchangé, y = 841.89 - y_json - h_json
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ACDC_Cerfa_BPF_Generator {

  const PH   = 841.89;
  const PW   = 595.28;
  const IW   = 1768;
  const IH   = 2500;

  const URL_P1 = 'https://acdcformation.com/wp-content/uploads/2026/05/Cerfa-BPF-page-1.jpg';
  const URL_P2 = 'https://acdcformation.com/wp-content/uploads/2026/05/Cerfa-BPF-page-2.jpg';

  /** Instance parente exposant _build_simple_pdf_string_public */
  public $parent_instance = null;

  /** Encode en latin1 */
  private function enc( $str ) {
    $r = @iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', (string) $str );
    return ( false !== $r && '' !== $r ) ? $r : (string) $str;
  }

  /**
   * Construit un élément texte.
   * x, yt : position depuis le HAUT de la page (coords JSON éditeur, pts PDF).
   * h : hauteur de la boîte (non utilisée pour la baseline — conservée pour compat JSON).
   * Formule baseline PDF : y = PH - yt - fs
   * (en CSS le texte démarre à top=yt, la baseline est à yt+fs ; en PDF Tm y=baseline depuis le bas)
   * cs = character spacing (letter-spacing).
   */
  private function tp( $x, $yt, $h, $text, $fs = 10, $bold = false, $cs = 0.0 ) {
    $el = array(
      'text'  => $this->enc( (string) $text ),
      'x'     => (float) $x,
      'y'     => round( self::PH - $yt, 2 ),
      'size'  => (float) $fs,
      'font'  => $bold ? 'Helvetica-Bold' : 'Helvetica',
      'color' => '#111111',
    );
    if ( abs( $cs ) > 0.001 ) { $el['char_spacing'] = (float) $cs; }
    return $el;
  }

  /** Formate un montant entier */
  private function m( $v ) { return number_format( (float)( $v ?: 0 ), 0, '.', ' ' ); }

  /**
   * Construit un élément texte aligné à droite dans une boîte.
   * x_right = bord droit de la boîte en pts.
   * Estimation largeur : ~0.6 × fs × nb_chars (Helvetica proportionnelle).
   */
  private function tpr( $x_right, $yt, $h, $text, $fs = 10, $bold = false ) {
    $text = (string) $text;
    $avg_w = $bold ? 0.65 : 0.60;
    $estimated_w = strlen( $text ) * $fs * $avg_w;
    $x = max( 0, $x_right - $estimated_w );
    return $this->tp( $x, $yt, $h, $text, $fs, $bold );
  }

  /**
   * Charge une image depuis une URL et la retourne en JPEG brut (compatible moteur PDF DCTDecode).
   * Gère PNG, JPEG. Fond blanc pour la transparence PNG.
   */
  private function load_remote_image( $url ) {
    $response = wp_remote_get( $url, array( 'timeout' => 10, 'sslverify' => true ) );
    if ( is_wp_error( $response ) ) {
      error_log( 'ACDC BPF sig: erreur ' . $response->get_error_message() );
      return null;
    }
    if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
      error_log( 'ACDC BPF sig: HTTP ' . wp_remote_retrieve_response_code( $response ) );
      return null;
    }
    $raw = wp_remote_retrieve_body( $response );
    if ( empty( $raw ) ) { return null; }

    // Détecter PNG (magic bytes 89 50 4E 47)
    $is_png = ( strlen( $raw ) > 4 && substr( $raw, 0, 4 ) === "\x89PNG" );

    if ( $is_png && function_exists( 'imagecreatefromstring' ) ) {
      $src = @imagecreatefromstring( $raw );
      if ( ! $src ) {
        error_log( 'ACDC BPF sig: imagecreatefromstring échoué' );
        return null;
      }
      $w = imagesx( $src );
      $h = imagesy( $src );
      // Fond blanc pour aplatir la transparence
      $dst = imagecreatetruecolor( $w, $h );
      imagealphablending( $dst, false );
      imagesavealpha( $dst, false );
      $white = imagecolorallocate( $dst, 255, 255, 255 );
      imagefill( $dst, 0, 0, $white );
      imagealphablending( $dst, true );
      imagecopy( $dst, $src, 0, 0, 0, 0, $w, $h );
      imagedestroy( $src );
      ob_start();
      imagejpeg( $dst, null, 92 );
      $jpeg = ob_get_clean();
      imagedestroy( $dst );
      if ( empty( $jpeg ) ) {
        error_log( 'ACDC BPF sig: imagejpeg vide' );
        return null;
      }
      error_log( 'ACDC BPF sig: PNG converti en JPEG ' . strlen( $jpeg ) . ' bytes ' . $w . 'x' . $h );
      return array( 'data' => $jpeg, 'width' => $w, 'height' => $h );
    }

    // Déjà JPEG — extraire dimensions
    if ( function_exists( 'getimagesizefromstring' ) ) {
      $info = @getimagesizefromstring( $raw );
      if ( $info && isset( $info[0] ) ) {
        return array( 'data' => $raw, 'width' => (int) $info[0], 'height' => (int) $info[1] );
      }
    }
    return array( 'data' => $raw, 'width' => 800, 'height' => 300 );
  }

  /**
   * Charge une image JPEG depuis une URL via wp_remote_get.
   * Cache le résultat en transient 24h pour éviter les requêtes répétées.
   */
  private function load_jpeg_url( $url, $cache_key ) {
    $cached = get_transient( $cache_key );
    if ( false !== $cached && ! empty( $cached['data'] ) ) {
      error_log( 'ACDC BPF: image depuis cache transient ' . $cache_key );
      return $cached;
    }
    error_log( 'ACDC BPF: chargement image ' . $url );
    $response = wp_remote_get( $url, array( 'timeout' => 15, 'sslverify' => true ) );
    if ( is_wp_error( $response ) ) {
      error_log( 'ACDC BPF: wp_remote_get erreur: ' . $response->get_error_message() );
      return null;
    }
    $code = wp_remote_retrieve_response_code( $response );
    if ( 200 !== (int) $code ) {
      error_log( 'ACDC BPF: HTTP ' . $code . ' pour ' . $url );
      return null;
    }
    $data = wp_remote_retrieve_body( $response );
    if ( empty( $data ) ) {
      error_log( 'ACDC BPF: corps vide pour ' . $url );
      return null;
    }
    $result = array( 'data' => $data, 'width' => self::IW, 'height' => self::IH );
    set_transient( $cache_key, $result, DAY_IN_SECONDS );
    error_log( 'ACDC BPF: image chargée ' . strlen( $data ) . ' bytes' );
    return $result;
  }

  /** Génère le CERFA BPF et l'écrit à $output_path. */
  public function generate( $cerfa_blank_path, $bpf, $output_path ) {

    $img1 = $this->load_jpeg_url( self::URL_P1, 'acdc_bpf_cerfa_p1' );
    $img2 = $this->load_jpeg_url( self::URL_P2, 'acdc_bpf_cerfa_p2' );

    if ( null === $img1 || null === $img2 ) {
      error_log( 'ACDC BPF: impossible de charger les images CERFA.' );
      return false;
    }

    $org    = isset( $bpf['org'] )    ? $bpf['org']    : array();
    $period = isset( $bpf['period'] ) ? $bpf['period'] : array();
    $d      = isset( $bpf['data'] )   ? $bpf['data']   : array();
    $s      = function( $a, $k ) { return isset( $a[$k] ) ? (string) $a[$k] : ''; };

    $f1s = (int)( isset($d['f1_salaries'])     ? $d['f1_salaries']     : 0 );
    $f1d = (int)( isset($d['f1_demandeurs'])   ? $d['f1_demandeurs']   : 0 );
    $f1i = (int)( isset($d['f1_independants']) ? $d['f1_independants'] : 0 );
    $f1a = (int)( isset($d['f1_autres'])       ? $d['f1_autres']       : 0 );
    $f1t = $f1s + $f1d + $f1i + $f1a;
    $f1h = (float)( isset($d['f1_total_heures']) ? $d['f1_total_heures'] : 0 );
    $f4r = isset($d['f4']) ? (array)$d['f4'] : array();

    $nsf_labels = array(
      '326' => 'Informatique, reseaux',    '413' => 'Capacites comportementales',
      '320' => 'Communication',            '315' => 'RH, gestion du personnel',
      '334' => 'Accueil, hotellerie',      '221' => 'Agroalimentaire',
      '333' => 'Enseignement, formation',  '414' => 'Organisation',
      '412' => 'Apprentissages de base',
    );

    // Bloc fond image (réutilisable)
    $bg = function( $img, $key ) {
      return array(
        'type'           => 'image',
        'image_key'      => $key,
        'image_data'     => $img['data'],
        'image_width'    => $img['width'],
        'image_height'   => $img['height'],
        'x'              => 0,
        'y'              => 0,
        'display_width'  => self::PW,
        'display_height' => self::PH,
      );
    };

    // ═══════════════════════════════════════════════════════════════════
    // PAGE 1 — coordonnées JSON éditeur (pts PDF, y depuis le HAUT, h=hauteur boîte)
    // ═══════════════════════════════════════════════════════════════════
    $p1 = array( array( 'type' => 'page_meta', 'width' => self::PW, 'height' => self::PH ), $bg( $img1, 'cerfa_p1' ) );

    // Cadre A — coordonnées calibrées éditeur v8
    $p1[] = $this->tp( 121,   167,   14, $s($org,'nda'),        10, false, 9.0 );
    $p1[] = $this->tp( 299,   181.5, 14, $s($org,'siret'),      10, false, 8.5 );
    $p1[] = $this->tp( 505.5, 181.5, 14, $s($org,'naf_code'),   10, false, 8.0 );
    $p1[] = $this->tp(  88,   181.5, 14, $s($org,'legal_form'), 10 );
    $p1[] = $this->tp( 184,   199.5, 14, $s($org,'enterprise'), 10, true );
    $p1[] = $this->tp(  60,   230,   14, $s($org,'address'),    10 );
    $p1[] = $this->tp( 255,   260.5, 14, 'X',                   10, true );
    $p1[] = $this->tp(  37,   274.5, 14, $s($org,'phone'),      10 );
    $p1[] = $this->tp( 329.5, 273.5, 14, $s($org,'email'),      10 );

    // Cadre B
    $p1[] = $this->tp(  243.5, 331.5, 14, $s($period,'start'), 10, false, 3.1 );
    $p1[] = $this->tp(  340.5, 331.5, 14, $s($period,'end'),   10, false, 3.1 );
    $p1[] = $this->tpr( 528,   349.5, 14, 'X',                 10, true );

    // Cadre C — produits
    $p1[] = $this->tpr( 555.5, 397,   14, $this->m( isset($d['c1'])  ? $d['c1']  : 0 ),  8 );
    $p1[] = $this->tpr( 528,   425.5, 14, $this->m( isset($d['ca'])  ? $d['ca']  : 0 ),  8 );
    $p1[] = $this->tpr( 528,   439.5, 14, $this->m( isset($d['cb'])  ? $d['cb']  : 0 ),  8 );
    $p1[] = $this->tpr( 527.5, 453.5, 14, $this->m( isset($d['cc'])  ? $d['cc']  : 0 ),  8 );
    $p1[] = $this->tpr( 528,   467.5, 14, $this->m( isset($d['cd'])  ? $d['cd']  : 0 ),  8 );
    $p1[] = $this->tpr( 528,   481.5, 14, $this->m( isset($d['ce'])  ? $d['ce']  : 0 ),  8 );
    $p1[] = $this->tpr( 528,   495.5, 14, $this->m( isset($d['cf'])  ? $d['cf']  : 0 ),  8 );
    $p1[] = $this->tpr( 528.5, 509.5, 14, $this->m( isset($d['cg'])  ? $d['cg']  : 0 ),  8 );
    $p1[] = $this->tpr( 528,   523.5, 14, $this->m( isset($d['ch'])  ? $d['ch']  : 0 ),  8 );
    $p1[] = $this->tpr( 554.5, 537.5, 14, $this->m( isset($d['c2'])  ? $d['c2']  : 0 ),  8 );
    $p1[] = $this->tpr( 554.5, 551.5, 14, $this->m( isset($d['c3'])  ? $d['c3']  : 0 ),  8 );
    $p1[] = $this->tpr( 554.5, 565.5, 14, $this->m( isset($d['c4'])  ? $d['c4']  : 0 ),  8 );
    $p1[] = $this->tpr( 554.5, 579.5, 14, $this->m( isset($d['c5'])  ? $d['c5']  : 0 ),  8 );
    $p1[] = $this->tpr( 554.5, 593.5, 14, $this->m( isset($d['c6'])  ? $d['c6']  : 0 ),  8 );
    $p1[] = $this->tpr( 554.5, 607.5, 14, $this->m( isset($d['c7'])  ? $d['c7']  : 0 ),  8 );
    $p1[] = $this->tpr( 554.5, 621.5, 14, $this->m( isset($d['c8'])  ? $d['c8']  : 0 ),  8 );
    $p1[] = $this->tpr( 554.5, 634.5, 14, $this->m( isset($d['c9'])  ? $d['c9']  : 0 ),  8 );
    $p1[] = $this->tpr( 554.5, 649.5, 14, $this->m( isset($d['c10']) ? $d['c10'] : 0 ),  8 );
    $p1[] = $this->tpr( 554.5, 663.5, 14, $this->m( isset($d['c11']) ? $d['c11'] : 0 ),  8 );
    $p1[] = $this->tpr( 558.5, 685.5, 14, $this->m( isset($d['c_total']) ? $d['c_total'] : 0 ), 8, true );
    $p1[] = $this->tpr( 557.5, 703.5, 14, (string)(int)( isset($d['pct_ca']) ? $d['pct_ca'] : 100 ), 8, false, 5.0 );

    // Cadre D — charges
    $p1[] = $this->tpr( 558.5, 742,   14, $this->m( isset($d['d_total'])    ? $d['d_total']    : 0 ), 8 );
    $p1[] = $this->tpr( 458.5, 756.5, 14, $this->m( isset($d['d_salaires']) ? $d['d_salaires'] : 0 ), 8 );
    $p1[] = $this->tpr( 458.5, 770.5, 14, $this->m( isset($d['d_achats'])   ? $d['d_achats']   : 0 ), 8 );

    // ═══════════════════════════════════════════════════════════════════
    // PAGE 2
    // ═══════════════════════════════════════════════════════════════════
    $p2 = array( array( 'type' => 'page_meta', 'width' => self::PW, 'height' => self::PH ), $bg( $img2, 'cerfa_p2' ) );

    // Cadre E — formateurs
    $nb_int = (int)( isset($d['e_nb_internes']) ? $d['e_nb_internes'] : 0 );
    $h_int  = (float)( isset($d['e_h_internes']) ? $d['e_h_internes'] : 0 );
    $nb_ext = (int)( isset($d['e_nb_externes']) ? $d['e_nb_externes'] : 0 );
    $h_ext  = (float)( isset($d['e_h_externes']) ? $d['e_h_externes'] : 0 );
    $p2[] = $this->tpr( 471.5, 54,   14, (string) $nb_int,              8 );
    $p2[] = $this->tpr( 569,   54,   14, number_format( $h_int, 0, '.', ' ' ),    8 );
    $p2[] = $this->tpr( 471.5, 68,   14, (string) $nb_ext,              8 );
    $p2[] = $this->tpr( 569,   68,   14, number_format( $h_ext, 0, '.', ' ' ),    8 );

    // Cadre F1 — types de stagiaires
    $p2[] = $this->tpr( 440, 211,   14, (string) $f1s,                  8 );
    $p2[] = $this->tpr( 569, 211,   14, '0',                            8 );
    $p2[] = $this->tpr( 440, 223,   14, '0',                            8 );
    $p2[] = $this->tpr( 569, 223,   14, '0',                            8 );
    $p2[] = $this->tpr( 440, 234.5, 14, (string) $f1d,                  8 );
    $p2[] = $this->tpr( 569, 234.5, 14, '0',                            8 );
    $p2[] = $this->tpr( 440, 246,   14, (string) $f1i,                  8 );
    $p2[] = $this->tpr( 569, 246,   14, '0',                            8 );
    $p2[] = $this->tpr( 440, 257,   14, (string) $f1a,                  8 );
    $p2[] = $this->tpr( 569, 257,   14, number_format( $f1h, 0, '.', ' ' ),       8 );
    $p2[] = $this->tpr( 440, 279,   14, (string) $f1t,                  8, true );
    $p2[] = $this->tpr( 569, 279,   14, number_format( $f1h, 0, '.', ' ' ),       8, true );

    // Cadre F2 — sous-traité
    $p2[] = $this->tpr( 440, 316.5, 14, '0', 8 );
    $p2[] = $this->tpr( 569, 316.5, 14, '0', 8 );

    // Cadre F3 — objectif général — 12 lignes × 2 colonnes — coordonnées calibrées v9
    // a = RNCP titre | a1-a6 = sous-niveaux | b = RS | c = CQP non inscrit
    // d = Autres formations pro | e = Bilans | f = VAE | tot = TOTAL(3)
    $f3_rncp  = (int)( isset($d['f3_rncp'])  ? $d['f3_rncp']  : 0 );
    $f3_rncp_h= (float)( isset($d['f3_rncp_h'])  ? $d['f3_rncp_h']  : 0 );
    $f3_a1    = (int)( isset($d['f3_a1'])    ? $d['f3_a1']    : 0 ); // dont niv 6-8
    $f3_a1_h  = (float)( isset($d['f3_a1_h'])    ? $d['f3_a1_h']    : 0 );
    $f3_a2    = (int)( isset($d['f3_a2'])    ? $d['f3_a2']    : 0 ); // dont niv 5
    $f3_a2_h  = (float)( isset($d['f3_a2_h'])    ? $d['f3_a2_h']    : 0 );
    $f3_a3    = (int)( isset($d['f3_a3'])    ? $d['f3_a3']    : 0 ); // dont niv 4
    $f3_a3_h  = (float)( isset($d['f3_a3_h'])    ? $d['f3_a3_h']    : 0 );
    $f3_a4    = (int)( isset($d['f3_a4'])    ? $d['f3_a4']    : 0 ); // dont niv 3
    $f3_a4_h  = (float)( isset($d['f3_a4_h'])    ? $d['f3_a4_h']    : 0 );
    $f3_a5    = (int)( isset($d['f3_a5'])    ? $d['f3_a5']    : 0 ); // dont niv 2
    $f3_a5_h  = (float)( isset($d['f3_a5_h'])    ? $d['f3_a5_h']    : 0 );
    $f3_a6    = (int)( isset($d['f3_a6'])    ? $d['f3_a6']    : 0 ); // dont CQP sans niv
    $f3_a6_h  = (float)( isset($d['f3_a6_h'])    ? $d['f3_a6_h']    : 0 );
    $f3_rs    = (int)( isset($d['f3_rs'])    ? $d['f3_rs']    : 0 ); // b = RS
    $f3_rs_h  = (float)( isset($d['f3_rs_h'])    ? $d['f3_rs_h']    : 0 );
    $f3_cqp   = (int)( isset($d['f3_cqp'])   ? $d['f3_cqp']   : 0 ); // c = CQP non inscrit
    $f3_cqp_h = (float)( isset($d['f3_cqp_h'])   ? $d['f3_cqp_h']   : 0 );
    // d = Autres formations — fallback sur f1t si non renseigné (comportement historique)
    $f3_autre = (int)( isset($d['f3_autre']) ? $d['f3_autre'] : $f1t );
    $f3_autre_h = (float)( isset($d['f3_autre_h']) ? $d['f3_autre_h'] : $f1h );
    $f3_bilan = (int)( isset($d['f3_bilan']) ? $d['f3_bilan'] : 0 ); // e = Bilans
    $f3_bilan_h = (float)( isset($d['f3_bilan_h']) ? $d['f3_bilan_h'] : 0 );
    $f3_vae   = (int)( isset($d['f3_vae'])   ? $d['f3_vae']   : 0 ); // f = VAE
    $f3_vae_h = (float)( isset($d['f3_vae_h'])   ? $d['f3_vae_h']   : 0 );
    $f3_tot_nb = $f3_rncp + $f3_rs + $f3_cqp + $f3_autre + $f3_bilan + $f3_vae;
    $f3_tot_h  = $f3_rncp_h + $f3_rs_h + $f3_cqp_h + $f3_autre_h + $f3_bilan_h + $f3_vae_h;
    // Si aucune donnée F3 spécifique → fallback sur f1t/f1h pour d (autres formations)
    if ( 0 === $f3_tot_nb ) { $f3_tot_nb = $f1t; $f3_tot_h = $f1h; }

    $p2[] = $this->tpr( 440, 362.5, 14, $f3_rncp  ? (string)$f3_rncp  : '',  8 ); // a nb
    $p2[] = $this->tpr( 569, 362.5, 14, $f3_rncp_h ? number_format($f3_rncp_h,0,'.',' ') : '', 8 ); // a h
    $p2[] = $this->tpr( 440, 374,   14, $f3_a1    ? (string)$f3_a1    : '',  8 ); // a1 niv6-8
    $p2[] = $this->tpr( 569, 374,   14, $f3_a1_h  ? number_format($f3_a1_h,0,'.',' ')  : '', 8 );
    $p2[] = $this->tpr( 440, 386,   14, $f3_a2    ? (string)$f3_a2    : '',  8 ); // a2 niv5
    $p2[] = $this->tpr( 569, 386,   14, $f3_a2_h  ? number_format($f3_a2_h,0,'.',' ')  : '', 8 );
    $p2[] = $this->tpr( 440, 398,   14, $f3_a3    ? (string)$f3_a3    : '',  8 ); // a3 niv4
    $p2[] = $this->tpr( 569, 398,   14, $f3_a3_h  ? number_format($f3_a3_h,0,'.',' ')  : '', 8 );
    $p2[] = $this->tpr( 440, 409.5, 14, $f3_a4    ? (string)$f3_a4    : '',  8 ); // a4 niv3
    $p2[] = $this->tpr( 569, 409.5, 14, $f3_a4_h  ? number_format($f3_a4_h,0,'.',' ')  : '', 8 );
    $p2[] = $this->tpr( 440, 421,   14, $f3_a5    ? (string)$f3_a5    : '',  8 ); // a5 niv2
    $p2[] = $this->tpr( 569, 421,   14, $f3_a5_h  ? number_format($f3_a5_h,0,'.',' ')  : '', 8 );
    $p2[] = $this->tpr( 440, 433,   14, $f3_a6    ? (string)$f3_a6    : '',  8 ); // a6 CQP sans niv
    $p2[] = $this->tpr( 569, 433,   14, $f3_a6_h  ? number_format($f3_a6_h,0,'.',' ')  : '', 8 );
    $p2[] = $this->tpr( 440, 453,   14, $f3_rs    ? (string)$f3_rs    : '',  8 ); // b RS
    $p2[] = $this->tpr( 569, 453,   14, $f3_rs_h  ? number_format($f3_rs_h,0,'.',' ')  : '', 8 );
    $p2[] = $this->tpr( 440, 464.5, 14, $f3_cqp   ? (string)$f3_cqp   : '', 8 ); // c CQP non inscrit
    $p2[] = $this->tpr( 569, 464.5, 14, $f3_cqp_h ? number_format($f3_cqp_h,0,'.',' ') : '', 8 );
    $p2[] = $this->tpr( 440, 476,   14, (string)$f3_autre,   8 ); // d autres formations
    $p2[] = $this->tpr( 569, 476,   14, number_format($f3_autre_h,0,'.',' '), 8 );
    $p2[] = $this->tpr( 440, 488,   14, $f3_bilan ? (string)$f3_bilan : '', 8 ); // e bilans
    $p2[] = $this->tpr( 569, 488,   14, $f3_bilan_h ? number_format($f3_bilan_h,0,'.',' ') : '', 8 );
    $p2[] = $this->tpr( 440, 499.5, 14, $f3_vae   ? (string)$f3_vae   : '', 8 ); // f VAE
    $p2[] = $this->tpr( 569, 499.5, 14, $f3_vae_h ? number_format($f3_vae_h,0,'.',' ')  : '', 8 );
    $p2[] = $this->tpr( 440, 516.5, 14, (string)$f3_tot_nb,                  8, true ); // TOTAL nb
    $p2[] = $this->tpr( 569, 516.5, 14, number_format($f3_tot_h,0,'.',' '),   8, true ); // TOTAL h

    // Cadre F4 — spécialités NSF — coordonnées calibrées v9
    $f4_y_base = array( 573, 584.5, 596.5, 608, 619.5 );
    // yt NSF (colonne code) légèrement décalé par rapport au libellé sur certaines lignes
    $f4_y_nsf  = array( 573, 586,   597.5, 609, 621   );
    $f4_tot_nb = 0;
    $f4_tot_h  = 0;
    foreach ( array_slice( $f4r, 0, 5 ) as $idx => $r ) {
      $nsf  = trim( (string)( isset($r['specialty']) ? $r['specialty'] : '' ) );
      $lbl  = isset($r['specialty_label']) ? $r['specialty_label'] : ( isset($nsf_labels[$nsf]) ? $nsf_labels[$nsf] : $nsf );
      $nb_r = (int)( isset($r['nb_apprenants']) ? $r['nb_apprenants'] : 0 );
      $h_r  = (int)( isset($r['heures_total'])  ? $r['heures_total']  : 0 );
      $f4_tot_nb += $nb_r;
      $f4_tot_h  += $h_r;
      $yt     = $f4_y_base[$idx];
      $yt_nsf = $f4_y_nsf[$idx];
      $p2[] = $this->tp(  24.5, $yt,     14, mb_strimwidth( $lbl, 0, 40, '.' ), 8 );
      $p2[] = $this->tp(  320,  $yt_nsf, 14, $nsf,                               8, false, 6.4 );
      $p2[] = $this->tpr( 440,  $yt,     14, (string) $nb_r,                     8 );
      $p2[] = $this->tpr( 569,  $yt,     14, (string) $h_r,                      8 );
    }
    $p2[] = $this->tpr( 440, 655.5, 14, (string)( $f4_tot_nb ?: $f1t ),   8, true );
    $p2[] = $this->tpr( 569, 655.5, 14, (string)( $f4_tot_h  ?: (int)$f1h ), 8, true );

    // Cadre G — sous-traitance reçue
    $g_nb = (int)( isset($d['g_nb_stag']) ? $d['g_nb_stag'] : 0 );
    $g_h  = (float)( isset($d['g_heures']) ? $d['g_heures'] : 0 );
    $p2[] = $this->tpr( 440, 706.5, 14, (string) $g_nb,              8 );
    $p2[] = $this->tpr( 569, 706.5, 14, number_format( $g_h, 0, '.', ' ' ),    8 );

    // Cadre H — textes
    $city      = $this->enc( $s($org,'city') . ',' );
    $date_gen  = $this->enc( $s($period,'date_generation') );
    $sign      = $this->enc( $s($org,'dirigeant_nom') . ' - ' . $s($org,'dirigeant_qualite') );
    $p2[] = $this->tp(  81,   743.5, 14, $s($org,'dirigeant_nom'),      8 );
    $p2[] = $this->tp( 332.5, 743.5, 14, $s($org,'dirigeant_qualite'),  8 );
    $p2[] = $this->tp(  29.5, 759.5, 14, $city,                          8 ); // h_lieu
    $p2[] = $this->tp( 265.5, 759.5, 14, $date_gen,                      8 ); // h_date
    $p2[] = $this->tp( 114,   773.5, 14, $sign,                          8 );
    $p2[] = $this->tp(  45,   788,   14, $s($org,'email'),               8 );
    $p2[] = $this->tp( 258,   788,   14, $s($org,'phone'),               8 );

    // Signature image — cadre H
    // x=405.7 (coin gauche), y=2.89 (coin bas, en pts depuis le bas), w=172.8, h=70
    $sig_result = $this->load_remote_image( 'https://acdcformation.com/wp-content/uploads/2026/05/Signature-seule-David-Contal-scaled.png' );
    if ( ! empty( $sig_result ) && is_array( $sig_result ) && ! empty( $sig_result['data'] ) ) {
      $sig_display_w = 164;
      $sig_display_h = 71.5;
      $sig_yt        = 760;
      $sig_x_left    = 420; // align right : x_right=584
      $sig_y_bottom  = round( self::PH - $sig_yt - $sig_display_h, 2 );
      $p2[] = array(
        'type'           => 'image',
        'image_key'      => 'bpf_signature',
        'image_data'     => $sig_result['data'],
        'image_width'    => $sig_result['width'],
        'image_height'   => $sig_result['height'],
        'x'              => $sig_x_left,
        'y'              => $sig_y_bottom,
        'display_width'  => $sig_display_w,
        'display_height' => $sig_display_h,
      );
      error_log( 'ACDC BPF sig: x=' . $sig_x_left . ' y=' . $sig_y_bottom . ' w=' . $sig_display_w . ' h=' . $sig_display_h );
    } else {
      error_log( 'ACDC BPF sig: non injectée — load_remote_image null ou invalide' );
    }

    // ── Génération PDF ────────────────────────────────────────────────────
    if ( ! isset( $this->parent_instance ) || ! method_exists( $this->parent_instance, '_build_simple_pdf_string_public' ) ) {
      error_log( 'ACDC BPF: parent_instance manquant.' );
      return false;
    }

    error_log( 'ACDC BPF: appel _build_simple_pdf_string_public p1=' . count($p1) . ' p2=' . count($p2) );
    $pdf_content = $this->parent_instance->_build_simple_pdf_string_public( array( $p1, $p2 ) );
    error_log( 'ACDC BPF: pdf_content size=' . strlen( (string)$pdf_content ) );

    if ( empty( $pdf_content ) ) {
      error_log( 'ACDC BPF: PDF vide.' );
      return false;
    }

    $written = file_put_contents( $output_path, $pdf_content );
    error_log( 'ACDC BPF: ecriture=' . $written . ' path=' . $output_path );
    return ( false !== $written && $written >= 1024 );
  }
}
