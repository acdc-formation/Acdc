<?php
/**
 * ACDC 3.21.22-hotfix8 — Template HTML proposition commerciale
 * Mise en page EXACTE selon Mise_en_page_proposition_commerciale_finalise_.html
 * Variables attendues :
 *   $p            = objet proposition
 *   $formation    = objet formation (ou objet vide)
 *   $trainers     = array formateurs
 *   $acdc         = array coordonnées ACDC
 *   $date_prop    = date formatée
 *   $cgv_text     = CGV HTML
 *   $total_hours  = int heures totales
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---- Fallback entreprise liée pour les champs client vides ---- */
if ( ! empty( $p->company_id ) ) {
  $_linked_company = $this->get_company( (int) $p->company_id );
  if ( $_linked_company ) {
    if ( empty( $p->client_address )     && ! empty( $_linked_company->address ) )     { $p->client_address     = (string) $_linked_company->address; }
    if ( empty( $p->client_postal_code ) && ! empty( $_linked_company->postal_code ) ) { $p->client_postal_code = (string) $_linked_company->postal_code; }
    if ( empty( $p->client_city )        && ! empty( $_linked_company->city ) )        { $p->client_city        = (string) $_linked_company->city; }
    if ( empty( $p->client_siret )       && ! empty( $_linked_company->siret ) )       { $p->client_siret       = (string) $_linked_company->siret; }
    if ( empty( $p->client_activity )    && ! empty( $_linked_company->activity ) )    { $p->client_activity    = (string) $_linked_company->activity; }
  }
}

/* ---- Résolution images ---- */
$th_code   = ! empty( $p->thematique ) ? (string) $p->thematique : ( ! empty( $formation->thematique ) ? (string) $formation->thematique : '' );
$th_imgs   = $th_code ? $this->get_proposal_thematique_images( $th_code ) : array();
$img_cover = ! empty( $th_imgs['cover'] )     ? $th_imgs['cover']     : ( ! empty( $formation->catalog_image_url ) ? (string) $formation->catalog_image_url : '' );
$img_prog  = ! empty( $th_imgs['programme'] ) ? $th_imgs['programme'] : $img_cover;
$si        = $this->get_proposal_static_images();
$logo_url        = $acdc['logo_url'] ?? '';
$logo_favicon_url = $acdc['logo_favicon_url'] ?? $logo_url;

/* ---- Textes personnalisés ---- */
$about_project_txt    = ! empty( $p->about_project )         ? (string) $p->about_project         : '';
$objectives_txt       = ! empty( $p->custom_objectives )     ? (string) $p->custom_objectives     : ( ! empty( $formation->objectives ) ? strip_tags( (string) $formation->objectives ) : '' );
/* P7 — "Objectif de la formation" : toujours depuis la fiche formation */
$formation_obj_txt    = ! empty( $formation->objectives ) ? strip_tags( (string) $formation->objectives ) : '';
/* Méthodes pédagogiques : extraire la partie avant 'Moyens techniques' */
$moyens_raw        = ! empty( $formation->moyens_pedago ) ? strip_tags( (string) $formation->moyens_pedago ) : '';
$moyens_pedago_part = '';
if ( $moyens_raw ) {
  $mp_parts = preg_split( '/Moyens\\s+techniques/iu', $moyens_raw, 2 );
  $moyens_pedago_part = trim( preg_replace( '/^Moyens\\s+p[eé]dagogiques\\s*/iu', '', trim( $mp_parts[0] ) ) );
}
$methods_txt       = ! empty( $p->custom_methods ) ? (string) $p->custom_methods : $moyens_pedago_part;
/* Évaluations : custom prop > formation entree + sortie */
$evaluation_txt = '';
if ( ! empty( $p->custom_evaluation ) && trim( strip_tags( (string) $p->custom_evaluation ) ) !== '' ) {
  $evaluation_txt = (string) $p->custom_evaluation;
} else {
  $eval_parts = array();
  $ee = trim( strip_tags( ! empty( $formation->evaluation_entree ) ? (string) $formation->evaluation_entree : '' ) );
  $es = trim( strip_tags( ! empty( $formation->evaluation_sortie ) ? (string) $formation->evaluation_sortie : '' ) );
  if ( $ee !== '' ) { $eval_parts[] = $ee; }
  if ( $es !== '' ) { $eval_parts[] = $es; }
  $evaluation_txt = implode( "\n", $eval_parts );
}
/* Convertit HTML en texte multi-lignes (li → lignes, br → \n) avant strip_tags */
$html_to_lines = function( $html ) {
  $s = preg_replace( '/<\/li>\s*/i', "\n", $html );
  $s = preg_replace( '/<li[^>]*>\s*/i', "• ", $s );
  $s = preg_replace( '/<br\s*\/?>/i', "\n", $s );
  $s = preg_replace( '/<\/p>\s*/i', "\n", $s );
  return trim( strip_tags( $s ) );
};
$prerequisites_txt    = ! empty( $p->custom_prerequisites )  ? (string) $p->custom_prerequisites  : ( ! empty( $formation->prerequisites ) ? $html_to_lines( (string) $formation->prerequisites ) : '' );
$description_txt      = ! empty( $formation->description_text ) ? $html_to_lines( (string) $formation->description_text ) : '';
$prog_j1_txt  = ! empty( $p->program_j1  ) ? (string) $p->program_j1  : '';
$prog_j2_txt  = ! empty( $p->program_j2  ) ? (string) $p->program_j2  : '';
$prog_j3_txt  = ! empty( $p->program_j3  ) ? (string) $p->program_j3  : '';
$prog_j4_txt  = ! empty( $p->program_j4  ) ? (string) $p->program_j4  : '';
$prog_j5_txt  = ! empty( $p->program_j5  ) ? (string) $p->program_j5  : '';
$prog_j6_txt  = ! empty( $p->program_j6  ) ? (string) $p->program_j6  : '';
$prog_j7_txt  = ! empty( $p->program_j7  ) ? (string) $p->program_j7  : '';
$prog_j8_txt  = ! empty( $p->program_j8  ) ? (string) $p->program_j8  : '';
$prog_j9_txt  = ! empty( $p->program_j9  ) ? (string) $p->program_j9  : '';
$prog_j10_txt = ! empty( $p->program_j10 ) ? (string) $p->program_j10 : '';
$prog_days    = max( 1, min( 10, (int) $p->formation_days ) );
/* Numéros de pages précalculés pour la navigation */
$pg_ressources = 9 + $prog_days;
$pg_financiere  = 10 + $prog_days;
$pg_contact     = 11 + $prog_days;
$prog_vars    = array( $prog_j1_txt, $prog_j2_txt, $prog_j3_txt, $prog_j4_txt, $prog_j5_txt,
                       $prog_j6_txt, $prog_j7_txt, $prog_j8_txt, $prog_j9_txt, $prog_j10_txt );
$extra_resources_txt  = ! empty( $p->extra_resources )
  ? (string) $p->extra_resources
  : ( ! empty( $formation->ressources ) ? $html_to_lines( (string) $formation->ressources ) : '' );

/* Si aucun prog journée mais programme global → tout en J1 */
if ( ! $prog_j1_txt && ! empty( $formation->program ) ) {
  $prog_j1_txt = strip_tags( (string) $formation->program );
}

/* ---- Helpers ---- */
$img_tag = function( $url, $w = '100%', $h = '100%' ) {
  if ( ! $url ) { return ''; }
  return '<img src="' . esc_url( $url ) . '" alt="" style="width:' . $w . ';height:' . $h . ';object-fit:cover;display:block;">';
};

$img_block = function( $url, $height_px, $margin = '0' ) use ( $img_tag ) {
  if ( $url ) {
    return '<div style="width:100%;height:' . (int) $height_px . 'px;overflow:hidden;flex-shrink:0;margin:' . $margin . ';">'
      . $img_tag( $url, '100%', '100%' ) . '</div>';
  }
  return '<div style="width:100%;height:' . (int) $height_px . 'px;background:linear-gradient(90deg,#3a2a1e,#7a5030 50%,#2c1a0e);flex-shrink:0;margin:' . $margin . ';"></div>';
};

$logo_block = function( $url, $size = '99px' ) {
  if ( $url ) {
    return '<img src="' . esc_url( $url ) . '" alt="Logo" style="width:' . $size . ';height:' . $size . ';object-fit:contain;">';
  }
  return '<span style="font-size:7.5px;color:#888;font-family:Arial,sans-serif;font-style:italic;text-align:center;">Logo ACDC</span>';
};

$logo_placeholder = '<div style="width:99px;height:99px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
  . $logo_block( $logo_url ) . '</div>';

$formation_title_esc = esc_html( $p->formation_title ?: 'Formation professionnelle' );
$fl_footer = '<div class="fl">Notre proposition — ' . $formation_title_esc . '</div>';

$pied_page = function( $n ) {
  return '<div style="background:#f1dcc0;height:20px;width:100%;flex-shrink:0;display:flex;align-items:center;justify-content:flex-end;padding-right:14px;font-size:7.5px;color:#475569">' . (int) $n . '</div>';
};

$header_std = function( $title_override = null ) use ( $formation_title_esc, $logo_placeholder ) {
  $t = $title_override ? esc_html( $title_override ) : $formation_title_esc;
  return '<div class="tb" style="width:354px;height:43px;background:#8b5b23;font-size:18px;justify-content:center">Notre proposition</div>'
    . '<div class="sh"><span style="font-size:18px">' . $t . '</span>' . $logo_placeholder . '</div>';
};

$top_corner = '<div style="position:absolute;top:0;right:0;width:241px;height:20px;background:#f1dcc0;z-index:2"></div>';

/* ---- Parse programme texte → blocs HTML ---- */
$render_prog = function( $txt ) {
  if ( ! trim( $txt ) ) {
    return '<div class="day-block"><p>Programme à compléter.</p></div>';
  }
  $html = '';
  $in_block  = false;
  $in_session = false;
  $in_ul = false;
  foreach ( explode( "\n", $txt ) as $raw_line ) {
    $line = trim( $raw_line );
    if ( $line === '' ) { continue; }
    /* Titre de journée */
    if ( preg_match( '/^(Jour|Day)\s*[0-9]/ui', $line ) || preg_match( '/^(Évaluation|Evaluation)/ui', $line ) ) {
      if ( $in_ul )      { $html .= '</ul>'; $in_ul = false; }
      if ( $in_session ) { $html .= '</div>'; $in_session = false; }
      if ( $in_block )   { $html .= '</div>'; $in_block = false; }
      $html .= '<div class="day-block"><div class="day-title">' . esc_html( $line ) . '</div>';
      $in_block = true;
    /* Titre de session (Matinée / Après-midi / ...) */
    } elseif ( preg_match( '/^(Matin|Après-midi|Aprés-midi|Session|Module)/ui', $line ) ) {
      if ( $in_ul )  { $html .= '</ul>'; $in_ul = false; }
      if ( $in_session ) { $html .= '</div>'; $in_session = false; }
      if ( ! $in_block ) { $html .= '<div class="day-block">'; $in_block = true; }
      $html .= '<div class="day-session"><div class="day-session-title">' . esc_html( $line ) . '</div>';
      $in_session = true;
    /* Compétences développées */
    } elseif ( preg_match( '/^Compétences?/ui', $line ) ) {
      if ( $in_ul ) { $html .= '</ul>'; $in_ul = false; }
      $html .= '<div class="day-compet"><strong>Compétences développées :</strong> ' . esc_html( preg_replace( '/^Compétences?\s*(développées)?\s*:?\s*/ui', '', $line ) ) . '</div>';
    /* Élément de liste */
    } elseif ( preg_match( '/^[-\x{2022}\x{00b7}]\s*(.+)/u', $line, $m ) ) {
      if ( ! $in_ul ) { $html .= '<ul>'; $in_ul = true; }
      $html .= '<li>' . esc_html( $m[1] ) . '</li>';
    } else {
      if ( $in_ul ) { $html .= '</ul>'; $in_ul = false; }
      if ( ! $in_block ) { $html .= '<div class="day-block">'; $in_block = true; }
      $html .= '<p>' . esc_html( $line ) . '</p>';
    }
  }
  if ( $in_ul )      { $html .= '</ul>'; }
  if ( $in_session ) { $html .= '</div>'; }
  if ( $in_block )   { $html .= '</div>'; }
  return $html ?: '<div class="day-block"><p>' . esc_html( $txt ) . '</p></div>';
};

/* Objectifs → liste <li> */
$render_obj_list = function( $txt ) {
  if ( ! trim( $txt ) ) { return ''; }
  $items = array_filter( array_map( 'trim', explode( "\n", $txt ) ) );
  $html = '';
  foreach ( $items as $item ) {
    $item = preg_replace( '/^[-\x{2022}\x{00b7}]\s*/u', '', $item );
    if ( ! $item ) { continue; }
    /* détecter verbe d'action en gras — Unicode (Évaluer, Créer, Distinguer, etc.) */
    if ( preg_match( '/^([\p{L}]+)\s+(.+)/u', $item, $m ) && mb_strlen( $m[1] ) < 20 ) {
      $html .= '<li><strong style="color:#0f2c52">' . esc_html( $m[1] ) . '</strong> ' . esc_html( $m[2] ) . '</li>';
    } else {
      $html .= '<li>' . esc_html( $item ) . '</li>';
    }
  }
  return $html;
};

/* Resources → tableau ou liste */
$render_resources_table = function( $txt ) {
  if ( ! trim( $txt ) ) {
    /* valeurs par défaut */
    return '<tr style="border:1px solid #d4bc8a"><td style="border:1px solid #d4bc8a;padding:8px 10px;width:38%;background:#fffdf7;vertical-align:middle"><strong style="color:#0f2c52;font-size:12px">Support pédagogique</strong></td><td style="border:1px solid #d4bc8a;padding:8px 10px;color:#33475f;vertical-align:middle">Document de synthèse remis aux participants.</td></tr>';
  }
  $html = '';
  foreach ( explode( "\n", $txt ) as $line ) {
    $line = trim( $line );
    if ( ! $line ) { continue; }
    if ( strpos( $line, ':' ) !== false ) {
      list( $label, $desc ) = explode( ':', $line, 2 );
      $html .= '<tr style="border:1px solid #d4bc8a"><td style="border:1px solid #d4bc8a;padding:8px 10px;width:38%;background:#fffdf7;vertical-align:middle"><strong style="color:#0f2c52;font-size:12px">' . esc_html( trim( $label ) ) . '</strong></td><td style="border:1px solid #d4bc8a;padding:8px 10px;color:#33475f;vertical-align:middle">' . esc_html( trim( $desc ) ) . '</td></tr>';
    } else {
      $html .= '<tr><td colspan="2" style="border:1px solid #d4bc8a;padding:8px 10px;color:#33475f;">' . esc_html( $line ) . '</td></tr>';
    }
  }
  return $html;
};

$nb_days    = (int) $p->formation_days;
$client_mode = isset( $client_mode ) ? (bool) $client_mode : false;
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Proposition — <?php echo esc_html( $p->client_company ); ?> — <?php echo $formation_title_esc; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Rubik:wght@700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
<?php if ( $client_mode ) : ?>
body{font-family:'Georgia',serif;background:#fff;display:block;margin:0;padding:0;}
#nav{display:none!important;}
<?php else : ?>
body{font-family:'Georgia',serif;background:#2a2a2a;display:flex;height:100vh;overflow:hidden}
<?php endif; ?>

/* Nav gauche */
#nav{width:160px;background:#0f2c52;display:flex;flex-direction:column;overflow-y:auto;flex-shrink:0}
#nav-header{padding:12px 10px 10px;border-bottom:1px solid rgba(214,163,83,.3)}
#nav-header h1{color:#d6a353;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;font-family:Arial,sans-serif}
#nav-header p{color:rgba(255,255,255,.4);font-size:12px;margin-top:3px;font-family:Arial,sans-serif}
.nb{display:block;width:100%;background:transparent;border:none;border-bottom:1px solid rgba(255,255,255,.06);color:rgba(255,255,255,.55);padding:9px 10px;font-size:12px;cursor:pointer;font-family:Arial,sans-serif;text-align:left;line-height:1.3;transition:all .15s}
.nb:hover{background:rgba(214,163,83,.1);color:#fff}
.nb.on{background:#d6a353;color:#0f2c52;font-weight:700}
.nb span{display:block;font-size:7.5px;opacity:.65;margin-top:1px}
.nb.on span{opacity:.55}

/* Zone A4 */
#canvas{flex:1;overflow-y:auto;padding:20px;display:flex;justify-content:center;align-items:flex-start;background:#3a3a3a}

/* Page A4 */
.pg{width:595px;height:842px;max-height:842px;overflow:hidden;background:#ffffff;display:none;flex-direction:column;font-family:Arial,Helvetica,sans-serif;font-size:11px;flex-shrink:0}
.pg.on{display:flex}
<?php if ( $client_mode ) : ?>
.pg{display:flex!important;margin:0 auto 20px;box-shadow:0 2px 8px rgba(0,0,0,.12);}
#canvas{overflow:visible;padding:20px 0;background:#e8e8e8;display:block;}
<?php endif; ?>

/* Composants communs */
.tb{background:#b1812d;color:#fff;height:24px;min-height:24px;display:flex;align-items:center;padding:0 14px;font-weight:700;font-size:8.5px;letter-spacing:.06em;text-transform:uppercase;flex-shrink:0}
.sh{display:flex;justify-content:space-between;align-items:center;padding:8px 14px 6px;color:#113860;font-weight:700;font-size:8.5px;flex-shrink:0}
.rib{margin:-7px auto 10px;width:fit-content;min-width:150px;background:#c99d4a;color:#fff;font-weight:800;font-size:8.5px;padding:4px 14px;text-align:center;text-transform:uppercase;letter-spacing:.04em;position:relative;z-index:2;flex-shrink:0}
.cnt{padding:0 14px;font-size:11px;line-height:1.1;color:#33475f;text-align:justify;flex:1}
.cnt p{margin-bottom:6px}
.cnt strong{color:#113860}
.cnt ul{margin:0 0 6px 12px}
.cnt li{margin-bottom:0.5px}
.tc{color:#113860;text-align:center;font-weight:800;font-size:11px;margin-bottom:8px;font-family:Arial,Helvetica,sans-serif}
.fl{padding:3px 14px;font-size:7px;color:#113860;font-weight:700;text-transform:uppercase;flex-shrink:0}

/* Page 1 — Couverture */
.cr{width:385px;height:60px;margin:-30px auto 0;background:#d6a353;color:#fff;font-weight:800;font-size:20px;text-transform:uppercase;position:relative;z-index:2;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.ct{text-align:center;color:#164a6e;margin:14px 0 0;font-size:24px;font-weight:800;text-transform:uppercase;flex-shrink:0;font-family:Arial,Helvetica,sans-serif}
.cw{text-align:center;font-size:20px;font-style:italic;line-height:1.5;color:#0f2c52;margin-top:4px;flex-shrink:0}
.ib{margin-top:auto;margin-bottom:0;width:595px;min-height:170px;max-height:200px;overflow:hidden;background:linear-gradient(135deg,#8A5A2B,#E9C77C,#6B3F1D);color:#164a6e;padding:20px 24px;font-size:18px;line-height:1.6;font-style:italic;font-weight:700;flex-shrink:0;display:flex;align-items:center}

/* Tables */
.tbl{width:100%;border-collapse:collapse;margin:5px 0}
.tbl th,.tbl td{border:.4px solid #d4bc8a;padding:4px 6px;vertical-align:top}
.tbl th{background:#d5aa5a;color:#fff;font-weight:800;text-align:left}

/* Page Objectifs — split */
.sp{display:grid;grid-template-columns:80px 1fr;gap:10px;padding:0 14px;overflow:hidden}
.ss{background:#d1a04d;color:#fff}
.sb{padding:7px 6px;border-bottom:1px solid rgba(255,255,255,.25);text-align:center}
.sb:last-child{border-bottom:none}
.sb .l{font-size:7.5px;font-weight:800;margin-bottom:2px}
.sb .v{font-size:7px;line-height:1.3}
.btt{display:inline-block;background:#c99d4a;color:#fff;padding:2.5px 7px;font-size:7.5px;font-weight:800;text-transform:uppercase;margin-bottom:5px}

/* Programme journée */
.day-block{margin-bottom:10px}
.day-title{color:#0f2c52;font-size:13px;font-weight:700;margin-bottom:4px;padding:6px 0 2px}
.day-session{margin-bottom:7px}
.day-session-title{color:#0f2c52;font-size:inherit;font-weight:700;margin-bottom:3px}
.day-compet{font-size:inherit;color:#0f2c52;margin-top:4px;margin-bottom:6px;line-height:inherit}

/* Contact */
.cg2{display:grid;grid-template-columns:1fr 70px;gap:12px;margin:10px 14px 0;flex-shrink:0}
.cl{font-size:8px;line-height:1.65;color:#41566f}
.cn{font-size:10px;font-weight:700;color:#c99d4a;margin-bottom:4px;display:block}

/* Print */
.print-bar{position:fixed;top:0;left:0;right:0;background:#0f2c52;color:#fff;padding:10px 20px;display:flex;align-items:center;justify-content:space-between;z-index:9999;font-family:Arial,sans-serif;font-size:14px;}
.print-bar button{background:#d6a353;color:#0f2c52;border:none;border-radius:8px;padding:8px 18px;font-weight:700;font-size:14px;cursor:pointer;}
@media print{
  @page{size:A4 portrait;margin:0;}
  html,body{margin:0;padding:0;background:#fff;height:auto;display:block;overflow:visible;}
  *{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}
  #nav{display:none!important;}
  .print-bar{display:none!important;}
  .no-print{display:none!important;}
  .pg{display:flex!important;width:595px!important;height:842px!important;max-height:842px!important;overflow:hidden!important;page-break-after:always;break-after:page;box-shadow:none!important;margin:0!important;padding:0!important;zoom:1.3333!important;}
  .pg:last-of-type{page-break-after:avoid!important;break-after:avoid!important;}
  #canvas{overflow:visible;padding:0;display:block;background:#fff;}
}
</style>
</head>
<body>

<?php if ( $client_mode ) : ?>
<div class="no-print" style="background:#0f2c52;padding:10px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:999;font-family:Arial,sans-serif;">
  <span style="color:rgba(255,255,255,.7);font-size:12px;">
    &#9432;&nbsp; Pour un PDF parfait : <strong style="color:#d6a353;">Plus de paramètres</strong> → cochez <em>Graphiques d'arrière-plan</em> → décochez <em>En-têtes et pieds de page</em> → Marges : <em>Aucune</em>
  </span>
  <button onclick="window.print()" style="background:#d6a353;color:#0f2c52;border:none;border-radius:8px;padding:9px 20px;font-weight:700;font-size:14px;cursor:pointer;font-family:Arial,sans-serif;flex-shrink:0;margin-left:16px;">&#128438; Imprimer / Enregistrer en PDF</button>
</div>
<?php else : ?>
<div class="print-bar">
  <span>Proposition — <?php echo esc_html( $p->client_company ); ?> — <?php echo $formation_title_esc; ?></span>
  <button onclick="window.print()">&#128438; Imprimer / Enregistrer en PDF</button>
</div>
<?php endif; ?>

<!-- Navigation -->
<div id="nav">
  <div id="nav-header"><h1>Aperçu</h1><p>Proposition Commerciale</p></div>
  <button class="nb on" onclick="gp(1,this)">1. Couverture<span>Titre &middot; Client</span></button>
  <button class="nb" onclick="gp(2,this)">2. &Agrave; propos<span>Pr&eacute;sentation client</span></button>
  <button class="nb" onclick="gp(3,this)">3. Projet<span>Organisation</span></button>
  <button class="nb" onclick="gp(4,this)">4. Nous<span>ACDC Formation</span></button>
  <button class="nb" onclick="gp(5,this)">5. Formateurs<span>Bios</span></button>
  <button class="nb" onclick="gp(6,this)">6. Notre approche<span>Avant &middot; Pendant &middot; Apr&egrave;s</span></button>
  <button class="nb" onclick="gp(7,this)">7. Objectifs<span>P&eacute;dagogiques</span></button>
  <button class="nb" onclick="gp(8,this)">8. Obj. &amp; M&eacute;thodes<span>P&eacute;dagogie &middot; &Eacute;valuation</span></button>
  <?php for ( $_nj = 1; $_nj <= $prog_days; $_nj++ ) : $_npg = 8 + $_nj; ?>
  <button class="nb" onclick="gp(<?php echo $_npg; ?>,this)"><?php echo $_npg; ?>. Programme J<?php echo $_nj; ?><span>D&eacute;roul&eacute; journ&eacute;e <?php echo $_nj; ?></span></button>
  <?php endfor; ?>
  <button class="nb" onclick="gp(<?php echo $pg_ressources; ?>,this)"><?php echo $pg_ressources; ?>. Ressources<span>Compl&eacute;mentaires</span></button>
  <button class="nb" onclick="gp(<?php echo $pg_financiere; ?>,this)"><?php echo $pg_financiere; ?>. Financier<span>Tarifs &middot; Total</span></button>
  <button class="nb" onclick="gp(<?php echo $pg_contact; ?>,this)"><?php echo $pg_contact; ?>. Contact<span>Coordonn&eacute;es</span></button>
</div>

<div id="canvas">

<!-- PAGE 1 : Couverture -->
<div class="pg" id="pg1" style="position:relative">
  <div style="position:absolute;top:0;right:0;width:270px;height:20px;background:#f1dcc0;z-index:2"></div>

  <div style="display:grid;grid-template-columns:325px 1fr;height:198px;flex-shrink:0;position:relative;z-index:1">
    <div style="height:198px;background:linear-gradient(135deg,#8A5A2B,#E9C77C,#6B3F1D);display:flex;align-items:center;justify-content:center;padding:16px">
      <div style="color:#0f2c52;font-weight:800;font-size:20px;line-height:1.3;text-align:center;font-family:'Rubik',Arial,sans-serif"><?php echo nl2br( $formation_title_esc ); ?></div>
    </div>
    <div style="background:#fff;height:198px;display:flex;align-items:center;justify-content:flex-end;padding-right:56px">
      <?php if ( $logo_url ) : ?>
        <img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo ACDC" style="width:99px;height:99px;object-fit:contain;flex-shrink:0;">
      <?php else : ?>
        <div style="width:99px;height:99px;background:#f4f5f7;border:1.5px dashed #b0b7c3;display:flex;align-items:center;justify-content:center;font-size:7.5px;color:#888;font-family:Arial,sans-serif;font-style:italic;text-align:center;flex-shrink:0">Logo ACDC</div>
      <?php endif; ?>
    </div>
  </div>

  <div style="height:45px;flex-shrink:0"></div>

  <!-- Image catalogue formation (thématique) -->
  <div style="height:198px;width:100%;position:relative;overflow:hidden;flex-shrink:0">
    <?php if ( $img_cover ) : ?>
      <img src="<?php echo esc_url( $img_cover ); ?>" alt="" style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;">
    <?php else : ?>
      <div style="position:absolute;top:0;left:0;width:100%;height:100%;background:linear-gradient(90deg,#241a1d,#6a4a2a 45%,#1b2432);"></div>
    <?php endif; ?>
  </div>

  <div class="cr">Proposition commerciale</div>
  <div class="ct"><?php echo esc_html( $p->client_company ); ?></div>
  <div class="cw">
    À l'attention de&nbsp;:<br>
    <strong><?php echo esc_html( $p->client_name ); ?></strong><br>
    <?php echo esc_html( $p->client_title ); ?>
  </div>
  <div style="flex:1"></div>
  <div class="ib">
    Date de la proposition&nbsp;: <?php echo esc_html( $date_prop ); ?><br>
    Proposition réalisée par&nbsp;: <?php echo esc_html( $acdc['contact_name'] ?? 'David Contal' ); ?><br>
    Tél&nbsp;: <?php echo esc_html( $acdc['phone'] ); ?> · <?php echo esc_html( $acdc['email'] ); ?>
  </div>
  <div style="background:#f1dcc0;height:20px;flex-shrink:0;display:flex;align-items:center;justify-content:flex-end;padding-right:14px;font-size:7.5px;color:#475569">1</div>
</div>

<!-- PAGE 2 : À propos du client -->
<div class="pg" id="pg2" style="position:relative">
  <?php echo $top_corner; ?>
  <?php echo $header_std(); ?>
  <!-- Image à propos 142px -->
  <div style="width:100%;height:142px;flex-shrink:0;overflow:hidden">
    <?php if ( ! empty( $si['image_apropos'] ) ) : ?>
      <img src="<?php echo esc_url( $si['image_apropos'] ); ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
    <?php else : ?>
      <div style="width:100%;height:142px;background:linear-gradient(90deg,#3a2a1e,#7a5030 50%,#2c1a0e);"></div>
    <?php endif; ?>
  </div>
  <div class="rib" style="width:496px;height:48px;background:#d6a353;font-size:20px;margin-top:-24px;margin-bottom:10px;display:flex;align-items:center;justify-content:center">À propos de vous</div>
  <div class="cnt" style="color:#0f2c52">
    <div class="tc" style="color:#164a6e;font-size:20px"><?php echo esc_html( $p->client_company ); ?></div>
    <?php if ( ! empty( $p->client_about_text ) ) :
      foreach ( explode( "\n", $p->client_about_text ) as $para ) :
        $para = trim( $para );
        if ( $para ) echo '<p>' . nl2br( esc_html( $para ) ) . '</p>';
      endforeach;
    else : ?>
      <p>Description non renseign&#233;e.</p>
    <?php endif; ?>
    <?php if ( $p->client_siret || $p->client_address || $p->client_postal_code || $p->client_city || $p->client_activity ) : ?>
      <p><strong>Informations administratives&nbsp;:</strong><br>
      <?php if ( $p->client_company ) echo 'Raison sociale&nbsp;: ' . esc_html( $p->client_company ) . '<br>'; ?>
      <?php if ( $p->client_siret )   echo 'SIRET&nbsp;: '          . esc_html( $p->client_siret )   . '<br>'; ?>
      <?php if ( $p->client_address ) echo 'Adresse du siège&nbsp;: ' . esc_html( $p->client_address ) . '<br>'; ?>
      <?php
        $postal_city = trim( ( ! empty( $p->client_postal_code ) ? $p->client_postal_code . ' ' : '' ) . ( ! empty( $p->client_city ) ? $p->client_city : '' ) );
        if ( $postal_city ) echo esc_html( $postal_city ) . '<br>';
      ?>
      <?php if ( $p->client_activity ) echo 'Activit&#233;&nbsp;: ' . esc_html( $p->client_activity ); ?>
      </p>
    <?php endif; ?>
  </div>
  <div style="flex:1"></div>
  <?php echo $fl_footer; ?>
  <?php echo $pied_page( 2 ); ?>
</div>

<!-- PAGE 3 : Votre projet & vos besoins -->
<div class="pg" id="pg3" style="position:relative">
  <?php echo $top_corner; ?>
  <?php echo $header_std(); ?>
  <!-- Image Votre projet & vos besoins -->
  <div style="width:100%;height:142px;flex-shrink:0;overflow:hidden">
    <?php if ( ! empty( $si['votre_projet'] ) ) : ?>
      <img src="<?php echo esc_url( $si['votre_projet'] ); ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
    <?php else : ?>
      <div style="width:100%;height:142px;background:linear-gradient(90deg,#6a4829,#b78a5e 35%,#e3d4bb 100%);flex-shrink:0;"></div>
    <?php endif; ?>
  </div>
  <div class="rib" style="width:496px;height:48px;background:#d6a353;font-size:20px;margin-top:-24px;margin-bottom:10px;display:flex;align-items:center;justify-content:center">Votre projet &amp; vos besoins</div>
  <div class="cnt" style="color:#0f2c52">
    <div class="tc" style="color:#164a6e;font-size:20px"><?php echo esc_html( $p->client_company ); ?></div>
    <?php if ( $about_project_txt ) :
      foreach ( explode( "\n", $about_project_txt ) as $l ) :
        $l = trim( $l );
        if ( $l ) echo '<p>' . esc_html( $l ) . '</p>';
      endforeach;
    else : ?>
      <p>À la suite de nos échanges, vous souhaitez mettre en place une formation adaptée à vos usages professionnels concrets.</p>
      <p>La proposition a été revue afin de mieux correspondre à vos contraintes d'organisation et à vos effectifs.</p>
    <?php endif; ?>
    <table class="tbl">
      <tr><th>Élément</th><th>Organisation retenue</th></tr>
      <tr><td><strong>Durée</strong></td><td><?php echo (int) $p->formation_days; ?> jours de <?php echo (int) $p->formation_hours_per_day; ?> heures (soit <?php echo $total_hours; ?> heures)</td></tr>
      <tr><td><strong>Effectifs</strong></td><td>Groupe&nbsp;: <?php echo (int) $p->formation_learners_count; ?> apprenant(s)</td></tr>
      <tr><td><strong>Financement</strong></td><td><?php echo $p->formation_funding ? esc_html( $p->formation_funding ) : 'aucun'; ?></td></tr>
    </table>
    <p>Cette organisation permet de travailler en effectifs restreints, de favoriser la pratique et de sécuriser l'appropriation des usages par chaque participant.</p>
    <?php
    $_pub_p3 = ! empty( $p->formation_public )
      ? (string) $p->formation_public
      : ( ! empty( $formation->catalog_audience ) ? strip_tags( (string) $formation->catalog_audience ) : '' );
    if ( $_pub_p3 || $p->formation_learners_count ) : ?>
      <p><strong>Public concern&eacute;&nbsp;:</strong></p>
      <ul>
        <?php if ( $_pub_p3 ) foreach ( array_filter( array_map( 'trim', explode( "\n", $_pub_p3 ) ) ) as $_pub_line ) echo '<li>' . esc_html( $_pub_line ) . '</li>'; ?>
      </ul>
    <?php endif; ?>
  </div>
  <div style="flex:1"></div>
  <?php echo $fl_footer; ?>
  <?php echo $pied_page( 3 ); ?>
</div>

<!-- PAGE 4 : Nous — ACDC Formation -->
<div class="pg" id="pg4" style="position:relative">
  <?php echo $top_corner; ?>
  <?php echo $header_std(); ?>
  <div style="width:100%;height:142px;flex-shrink:0;overflow:hidden">
    <?php if ( ! empty( $si['nous'] ) ) : ?>
      <img src="<?php echo esc_url( $si['nous'] ); ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
    <?php else : ?>
      <div style="width:100%;height:142px;background:linear-gradient(90deg,#291e1f,#41323a 36%,#202531);"></div>
    <?php endif; ?>
  </div>
  <div class="rib" style="width:496px;height:48px;background:#d6a353;font-size:20px;margin-top:-24px;margin-bottom:10px;display:flex;align-items:center;justify-content:center">Nous</div>
  <div class="cnt" style="color:#0f2c52">
    <div class="tc" style="color:#164a6e;font-size:20px"><?php echo esc_html( $acdc['name'] ); ?></div>
    <?php
    $acdc_desc = ! empty( $p->about_acdc_text )
      ? (string) $p->about_acdc_text
      : get_option( 'acdc_of_proposal_about_acdc', '' );
    ?>
    <?php if ( $acdc_desc ) : ?>
      <?php echo wp_kses_post( wpautop( esc_html( $acdc_desc ) ) ); ?>
    <?php else : ?>
      <p><strong><?php echo esc_html( $acdc['name'] ); ?></strong> conçoit et anime des formations professionnelles centrées sur les usages réels du terrain. Notre objectif est simple&nbsp;: permettre aux participants de repartir avec des méthodes, des outils et des supports directement utilisables dans leur activité quotidienne.</p>
      <p>Basée à Cogolin, <strong><?php echo esc_html( $acdc['name'] ); ?></strong> intervient auprès d'entreprises, d'indépendants, de dirigeants, de formateurs et de professionnels souhaitant développer des compétences opérationnelles, notamment dans les domaines de l'intelligence artificielle, du no-code, du marketing digital, du web, du management et des compétences relationnelles.</p>
      <p>Notre approche repose sur quatre principes&nbsp;:</p>
      <ul>
        <li>Partir du besoin réel du client&nbsp;;</li>
        <li>Adapter le contenu au niveau des participants&nbsp;;</li>
        <li>Faire pratiquer rapidement sur des cas concrets&nbsp;;</li>
        <li>Transformer les apports théoriques en méthodes applicables après la formation.</li>
      </ul>
    <?php endif; ?>
  </div>
  <div style="flex:1"></div>
  <?php echo $fl_footer; ?>
  <?php echo $pied_page( 4 ); ?>
</div>

<!-- PAGE 5 : Nos formateurs -->
<div class="pg" id="pg5" style="position:relative">
  <?php echo $top_corner; ?>
  <?php echo $header_std(); ?>
  <div style="width:100%;height:142px;flex-shrink:0;overflow:hidden">
    <?php if ( ! empty( $si['nous_formateurs'] ) ) : ?>
      <img src="<?php echo esc_url( $si['nous_formateurs'] ); ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
    <?php else : ?>
      <div style="width:100%;height:142px;background:linear-gradient(90deg,#3d2a1f,#1e1f23 55%,#3d2a1f);"></div>
    <?php endif; ?>
  </div>
  <div class="rib" style="width:496px;height:48px;background:#d6a353;font-size:20px;margin-top:-24px;margin-bottom:10px;display:flex;align-items:center;justify-content:center">Nos formateurs</div>
  <div class="cnt" style="color:#0f2c52;padding-top:14px">
    <?php if ( ! empty( $trainers ) ) :
      foreach ( $trainers as $tr ) :
        $full_name    = trim( $tr->first_name . ' ' . $tr->last_name );
        $photo_url    = ! empty( $tr->photo_url ) ? (string) $tr->photo_url : '';
        $bio_override = isset( $trainer_bios[ (int) $tr->id ] ) && $trainer_bios[ (int) $tr->id ] !== ''
          ? (string) $trainer_bios[ (int) $tr->id ]
          : ( ! empty( $tr->description_text ) ? (string) $tr->description_text : '' );
    ?>
      <div style="display:flex;gap:14px;align-items:flex-start;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid #f0e6dc;">
        <?php if ( $photo_url ) : ?>
          <img src="<?php echo esc_url( $photo_url ); ?>" alt="<?php echo esc_attr( $full_name ); ?>"
               style="width:68px;height:68px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid #d6a353;">
        <?php else : ?>
          <div style="width:68px;height:68px;border-radius:50%;background:#fbf2e3;flex-shrink:0;border:2px solid #d6a353;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;color:#8a6d2a;">
            <?php echo esc_html( mb_strtoupper( mb_substr( $tr->first_name, 0, 1 ) ) ); ?>
          </div>
        <?php endif; ?>
        <div style="flex:1;">
          <p style="font-weight:700;margin:0 0 5px;color:#0f2c52;"><?php echo esc_html( $full_name ); ?></p>
          <?php if ( $bio_override ) :
            foreach ( explode( "\n", $bio_override ) as $bline ) :
              $bline = trim( $bline );
              if ( $bline ) echo '<p style="margin:0 0 4px;line-height:1.55;">' . esc_html( $bline ) . '</p>';
            endforeach;
          else : ?>
            <p style="margin:0;color:#4b5d76;">Formateur certifi&#233;, expert dans les domaines couverts par cette formation.</p>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach;
    else : ?>
      <p>Les formateurs seront d&#233;sign&#233;s en fonction de la th&#233;matique et du niveau des participants.</p>
    <?php endif; ?>
  </div>
  <div style="flex:1"></div>
  <?php echo $fl_footer; ?>
  <?php echo $pied_page( 5 ); ?>
</div>

<!-- PAGE 6 : Notre approche — Avant · Pendant · Après -->
<div class="pg" id="pg6" style="position:relative">
  <?php echo $top_corner; ?>
  <?php echo $header_std(); ?>
  <div style="width:100%;height:142px;flex-shrink:0;overflow:hidden">
    <?php if ( ! empty( $si['avant_pendant_apres'] ) ) : ?>
      <img src="<?php echo esc_url( $si['avant_pendant_apres'] ); ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
    <?php else : ?>
      <div style="width:100%;height:142px;background:linear-gradient(90deg,#3a2a1e,#7a5030 50%,#2c1a0e);"></div>
    <?php endif; ?>
  </div>
  <div class="rib" style="width:496px;height:48px;background:#d6a353;font-size:20px;margin-top:-24px;margin-bottom:10px;display:flex;align-items:center;justify-content:center">Notre proposition&nbsp;: une approche complète</div>

  <div style="padding:0 14px 6px;color:#0f2c52;line-height:inherit;flex-shrink:0;text-align:justify">
    <strong>Avant, pendant et après la formation&nbsp;:</strong> tout est pensé pour renforcer l'implication, l'ancrage des savoirs et la mise en pratique.
  </div>

  <?php
  /* Charger les variables approche avec override proposition */
  $app_bef_p6 = ! empty( $p->approach_before )
    ? (string) $p->approach_before
    : get_option( 'acdc_of_proposal_approach_before', "Entretien préparatoire avec la personne en charge du projet formation pour affiner les objectifs et recueillir des cas métiers.\nTest de positionnement en ligne et recueil des attentes participants." );
  $app_dur_p6 = ! empty( $p->approach_during )
    ? (string) $p->approach_during
    : get_option( 'acdc_of_proposal_approach_during', "Formateurs expérimentés et certifiés.\nSupports de cours et fiche mémo.\nLivret de l'apprenant.\n20% de théorie - 80% de mise en pratique.\nÉvaluation de la satisfaction.\nÉvaluation des acquis." );
  $app_aft_p6 = ! empty( $p->approach_after )
    ? (string) $p->approach_after
    : get_option( 'acdc_of_proposal_approach_after', "Bibliothèque de prompts\nÉvaluation à froid\nÉvaluation de la satisfaction.\nÉvaluation des acquis." );
  ?>
  <div style="padding:0 14px;flex:1;display:flex;flex-direction:column;gap:10px">
    <!-- AVANT -->
    <div style="display:grid;grid-template-columns:70px 1fr;gap:10px;align-items:start">
      <div style="display:flex;justify-content:center;padding-top:4px"><svg width="60" height="60" viewBox="0 0 64 64"><rect x="14" y="8" width="36" height="46" rx="4" fill="#e8c87a" stroke="#c99d4a" stroke-width="1.5"/><rect x="22" y="4" width="20" height="8" rx="3" fill="#c99d4a"/><line x1="20" y1="24" x2="44" y2="24" stroke="#8b6820" stroke-width="1.5"/><line x1="20" y1="32" x2="44" y2="32" stroke="#8b6820" stroke-width="1.5"/><line x1="20" y1="40" x2="36" y2="40" stroke="#8b6820" stroke-width="1.5"/><polyline points="18,38 22,43 30,34" fill="none" stroke="#c99d4a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
      <div>
        <div style="background:#d6a353;color:#fff;font-weight:700;font-size:16px;padding:0 8px;margin-bottom:5px;letter-spacing:.03em;height:28px;display:flex;align-items:center;flex-shrink:0">Avant&nbsp;: pr&#233;parer</div>
        <?php foreach ( array_filter( array_map( 'trim', explode( "\n", $app_bef_p6 ) ) ) as $ln ) : ?>
          <p style="margin-bottom:3px"><?php echo esc_html( $ln ); ?></p>
        <?php endforeach; ?>
      </div>
    </div>
    <!-- PENDANT -->
    <div style="display:grid;grid-template-columns:70px 1fr;gap:10px;align-items:start">
      <div style="display:flex;justify-content:center;padding-top:4px"><svg width="60" height="60" viewBox="0 0 64 64"><circle cx="32" cy="32" r="22" fill="#e8c87a" stroke="#c99d4a" stroke-width="1.5"/><polygon points="26,22 26,42 46,32" fill="#c99d4a"/></svg></div>
      <div>
        <div style="background:#d6a353;color:#fff;font-weight:700;font-size:16px;padding:0 8px;margin-bottom:5px;letter-spacing:.03em;height:28px;display:flex;align-items:center;flex-shrink:0">Pendant&nbsp;: ex&#233;cuter</div>
        <?php foreach ( array_filter( array_map( 'trim', explode( "\n", $app_dur_p6 ) ) ) as $ln ) : ?>
          <p style="margin-bottom:3px"><?php echo esc_html( $ln ); ?></p>
        <?php endforeach; ?>
      </div>
    </div>
    <!-- APRÈS -->
    <div style="display:grid;grid-template-columns:70px 1fr;gap:10px;align-items:start">
      <div style="display:flex;justify-content:center;padding-top:4px"><svg width="60" height="60" viewBox="0 0 64 64"><path d="M12 32 A20 20 0 0 1 52 32" fill="none" stroke="#c99d4a" stroke-width="3" stroke-linecap="round"/><path d="M52 32 A20 20 0 0 1 12 32" fill="none" stroke="#e8c87a" stroke-width="3" stroke-linecap="round"/><polygon points="52,22 52,34 44,28" fill="#c99d4a"/><polygon points="12,42 12,30 20,36" fill="#e8c87a" stroke="#c99d4a" stroke-width="1"/></svg></div>
      <div>
        <div style="background:#d6a353;color:#fff;font-weight:700;font-size:16px;padding:0 8px;margin-bottom:5px;letter-spacing:.03em;height:28px;display:flex;align-items:center;flex-shrink:0">Apr&#232;s</div>
        <?php foreach ( array_filter( array_map( 'trim', explode( "\n", $app_aft_p6 ) ) ) as $ln ) : ?>
          <p style="margin-bottom:3px"><?php echo esc_html( $ln ); ?></p>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div style="flex:1"></div>
  <?php echo $fl_footer; ?>
  <?php echo $pied_page( 6 ); ?>
</div>

<!-- PAGE 7 : Objectifs pédagogiques -->
<div class="pg" id="pg7" style="position:relative">
  <?php echo $top_corner; ?>
  <?php echo $header_std(); ?>
  <div style="width:100%;height:142px;flex-shrink:0;overflow:hidden">
    <?php if ( ! empty( $si['objectif'] ) ) : ?>
      <img src="<?php echo esc_url( $si['objectif'] ); ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
    <?php else : ?>
      <div style="width:100%;height:142px;background:linear-gradient(90deg,#5f3d22,#7b5638 35%,#b78857 100%);"></div>
    <?php endif; ?>
  </div>
  <div class="sp" style="grid-template-columns:125px 1fr;flex:1;align-items:stretch;margin-top:-14px;position:relative;z-index:1;overflow:hidden;">
    <div class="ss" style="width:125px;min-height:0;flex:1;display:flex;flex-direction:column">
      <div class="sb" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;border-bottom:3px solid #fef6e4;padding:8px;overflow:hidden">
        <div class="l" style="color:#164a6e;font-size:12px;font-weight:800;margin-bottom:4px;flex-shrink:0">Dur&#233;e&nbsp;:</div>
        <div class="v" style="color:#ffffff;font-size:11px;line-height:1.4;"><?php echo (int) $p->formation_days; ?> jours (<?php echo $total_hours; ?>h)</div>
      </div>
      <div class="sb" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;border-bottom:3px solid #fef6e4;padding:8px;overflow:hidden">
        <div class="l" style="color:#164a6e;font-size:12px;font-weight:800;margin-bottom:4px;flex-shrink:0">Public&nbsp;:</div>
        <div class="v" style="color:#ffffff;font-size:11px;line-height:1.4;word-break:break-word;"><?php
          $_pub_p7 = ! empty( $p->formation_public ) ? (string) $p->formation_public : ( ! empty( $formation->catalog_audience ) ? strip_tags( (string) $formation->catalog_audience ) : (string) $p->client_company );
          echo esc_html( $_pub_p7 );
          ?><br><?php echo (int) $p->formation_learners_count; ?> apprenant(s)</div>
      </div>
      <div class="sb" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;text-align:center;border-bottom:3px solid #fef6e4;padding:8px;overflow:hidden">
        <div class="l" style="color:#164a6e;font-size:12px;font-weight:800;margin-bottom:4px;flex-shrink:0">Pr&#233; requis&nbsp;:</div>
        <div class="v" style="color:#ffffff;font-size:10px;line-height:1.45;text-align:center;word-break:break-word;overflow:hidden;"><?php echo $prerequisites_txt ? nl2br( esc_html( $prerequisites_txt ) ) : 'Aucun'; ?></div>
      </div>
      <div class="sb" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;border-bottom:none;padding:8px;overflow:hidden">
        <div class="l" style="color:#164a6e;font-size:12px;font-weight:800;margin-bottom:4px;flex-shrink:0">Modalit&#233;&nbsp;:</div>
        <div class="v" style="color:#ffffff;font-size:10px;line-height:1.4;word-break:break-word;"><?php
          $mod_raw = $p->formation_location ? (string) $p->formation_location : ( ! empty( $formation->modality ) ? (string) $formation->modality : 'Présentiel' );
          /* Insérer un saut de ligne avant le code postal (5 chiffres) */
          $mod_display = preg_replace( '/\s+(\d{5}\b)/', "\n$1", $mod_raw );
          echo nl2br( esc_html( $mod_display ) );
        ?></div>
      </div>
    </div>
    <div class="cnt" style="padding:0 14px">
      <div class="btt" style="display:flex;align-items:center;width:100%;height:28px;background:#d6a353;font-size:16px;padding:0 8px;margin-bottom:5px;text-transform:none">Objectif de la formation</div>
      <?php if ( $formation_obj_txt ) :
        foreach ( explode( "\n", $formation_obj_txt ) as $ol ) :
          $ol = trim( preg_replace( '/^[-\x{2022}\x{00b7}]\s*/u', '', $ol ) );
          if ( $ol ) echo '<p>' . esc_html( $ol ) . '</p>';
        endforeach;
      else : ?>
        <p>À l'issue de la formation, les apprenants seront capables de mettre en œuvre les compétences acquises dans leur pratique professionnelle quotidienne.</p>
      <?php endif; ?>
      <div class="btt" style="display:flex;align-items:center;width:100%;height:28px;background:#d6a353;font-size:16px;padding:0 8px;margin-bottom:5px;text-transform:none">Pourquoi cette formation&nbsp;?</div>
      <?php if ( $description_txt ) :
        foreach ( explode( "\n", $description_txt ) as $dl ) :
          $dl = trim( $dl );
          if ( $dl ) echo '<p style="margin-bottom:0">' . esc_html( $dl ) . '</p>';
        endforeach;
      else : ?>
        <p style="margin-bottom:0">Cette formation vous aide &#224; d&#233;velopper des comp&#233;tences directement applicables, &#224; s&#233;curiser vos pratiques et &#224; ancrer durablement les nouvelles m&#233;thodes dans votre quotidien professionnel.</p>
      <?php endif; ?>
    </div>
  </div>
  <?php echo $fl_footer; ?>
  <?php echo $pied_page( 7 ); ?>
</div>

<!-- PAGE 8 : Objectifs & Méthodes -->
<div class="pg" id="pg8" style="position:relative">
  <?php echo $top_corner; ?>
  <?php echo $header_std(); ?>
  <div style="width:100%;height:142px;flex-shrink:0;overflow:hidden">
    <?php if ( ! empty( $si['objectifs_methodes'] ) ) : ?>
      <img src="<?php echo esc_url( $si['objectifs_methodes'] ); ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
    <?php else : ?>
      <div style="width:100%;height:142px;background:linear-gradient(90deg,#3b281f,#8e6032 40%,#2c2a31);"></div>
    <?php endif; ?>
  </div>
  <div class="cnt" style="padding:0 14px;display:flex;flex-direction:column">
    <div style="background:#d6a353;color:#fff;font-weight:800;font-size:16px;height:28px;width:391px;margin-left:-14px;padding:0 14px;display:flex;align-items:center;text-transform:none;letter-spacing:.02em;margin-bottom:6px;flex-shrink:0">Objectifs pédagogiques</div>
    <ul style="margin:0 0 8px 14px">
      <?php if ( $objectives_txt ) :
        echo $render_obj_list( $objectives_txt );
      else : ?>
        <li><strong style="color:#0f2c52">Comprendre</strong> les fondamentaux de la thématique et ses applications professionnelles.</li>
        <li><strong style="color:#0f2c52">Identifier</strong> les usages pertinents pour son activité.</li>
        <li><strong style="color:#0f2c52">Mettre en pratique</strong> les acquis sur des cas concrets.</li>
      <?php endif; ?>
    </ul>
    <div style="background:#d6a353;color:#fff;font-weight:800;font-size:16px;height:28px;width:391px;margin-left:-14px;padding:0 14px;display:flex;align-items:center;text-transform:none;letter-spacing:.02em;margin-bottom:6px;flex-shrink:0">Méthodes pédagogiques</div>
    <ul style="margin:0 0 8px 14px">
      <?php if ( $methods_txt ) :
        foreach ( explode( "\n", $methods_txt ) as $ml ) :
          $ml = trim( preg_replace( '/^[-\x{2022}\x{00b7}]\s*/u', '', $ml ) );
          if ( $ml ) echo '<li>' . esc_html( $ml ) . '</li>';
        endforeach;
      else : ?>
        <li><?php echo $p->formation_location ? 'Formation en présentiel, ' . esc_html( $p->formation_location ) . '.' : 'Formation en présentiel.'; ?></li>
        <li>Alternance de repères méthodologiques, démonstrations et mises en pratique.</li>
        <li>Travail à partir de cas d'usage proches de vos situations réelles.</li>
        <li>Accompagnement pas à pas pour sécuriser la prise en main.</li>
        <li>Support pédagogique remis à chaque participant.</li>
      <?php endif; ?>
    </ul>
    <div style="background:#d6a353;color:#fff;font-weight:800;font-size:16px;height:28px;width:391px;margin-left:-14px;padding:0 14px;display:flex;align-items:center;text-transform:none;letter-spacing:.02em;margin-bottom:6px;flex-shrink:0">Modalit&#233;s d&#8217;&#233;valuation</div>
    <ul style="margin:0 0 8px 14px">
      <?php if ( $evaluation_txt ) :
        foreach ( explode( "\n", $evaluation_txt ) as $el ) :
          $el = trim( preg_replace( '/^[-\x{2022}\x{00b7}]\s*/u', '', $el ) );
          if ( $el ) echo '<li>' . esc_html( $el ) . '</li>';
        endforeach;
      else : ?>
        <li><strong style="color:#0f2c52">Questionnaire</strong> de validation des connaissances.</li>
        <li><strong style="color:#0f2c52">Feedback</strong> sur les productions r&#233;alis&#233;es pendant la formation.</li>
      <?php endif; ?>
    </ul>
  </div>
  <?php echo $fl_footer; ?>
  <?php echo $pied_page( 8 ); ?>
</div>

<?php
/* Pages programme dynamiques — 1 page par journée selon formation_days */
$_pg_num = 9;
for ( $_j = 1; $_j <= $prog_days; $_j++ ) :
  $_prog_txt = $prog_vars[ $_j - 1 ];
  $_pg_cur   = $_pg_num++;
?>
<div class="pg" id="pg<?php echo $_pg_cur; ?>" style="position:relative">
  <?php echo $top_corner; ?>
  <?php echo $header_std(); ?>
  <div style="width:100%;height:113px;flex-shrink:0;overflow:hidden">
    <?php if ( $img_prog ) : ?>
      <img src="<?php echo esc_url( $img_prog ); ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
    <?php else : ?>
      <div style="width:100%;height:113px;background:linear-gradient(90deg,#3b281f,#8e6032 40%,#2c2a31);"></div>
    <?php endif; ?>
  </div>
  <div class="rib" style="width:368px;height:48px;background:#d6a353;font-size:18px;margin-top:-24px;margin-bottom:10px;margin-left:0;display:flex;align-items:center;justify-content:center">D&#233;roul&#233; de la journ&#233;e <?php echo $_j; ?></div>
  <div class="cnt">
    <?php echo $_prog_txt ? $render_prog( $_prog_txt ) : '<div class="day-block"><p>Programme journ\u00e9e ' . $_j . ' \u00e0 compl\u00e9ter.</p></div>'; ?>
  </div>
  <div style="flex:1"></div>
  <?php echo $fl_footer; ?>
  <?php echo $pied_page( $_pg_cur ); ?>
</div>

<?php endfor; ?>

<?php
/* Numérotation dynamique des pages suivantes */
$pg_ressources = $_pg_num++;
$pg_financiere  = $_pg_num++;
$pg_contact     = $_pg_num++;
?>

<?php /* ── Ressources complémentaires ── */ ?>
<!-- PAGE 12 : Ressources complémentaires -->
<div class="pg" id="pg<?php echo $pg_ressources; ?>" style="position:relative">
  <?php echo $top_corner; ?>
  <?php echo $header_std(); ?>
  <div style="width:100%;height:142px;flex-shrink:0;overflow:hidden">
    <?php if ( ! empty( $si['ressources'] ) ) : ?>
      <img src="<?php echo esc_url( $si['ressources'] ); ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
    <?php else : ?>
      <div style="width:100%;height:142px;background:linear-gradient(90deg,#3b281f,#8e6032 40%,#2c2a31);"></div>
    <?php endif; ?>
  </div>
  <div style="background:#d6a353;color:#fff;font-weight:800;font-size:18px;width:100%;height:48px;margin-top:-24px;padding:0 14px;display:flex;align-items:center;justify-content:center;text-transform:uppercase;letter-spacing:.03em;flex-shrink:0;position:relative;z-index:1">Ressources complémentaires mises à disposition</div>

  <div style="padding:10px 14px 6px;flex-shrink:0;margin-top:14px">
    <table style="width:100%;border-collapse:collapse">
      <?php echo $render_resources_table( $extra_resources_txt ); ?>
    </table>
  </div>



  <div style="flex:1"></div>
  <?php echo $fl_footer; ?>
  <?php echo $pied_page( $pg_ressources ); ?>
</div>

<?php /* ── Proposition financière ── */ ?>
<!-- PAGE 13 : Proposition financière -->
<div class="pg" id="pg<?php echo $pg_financiere; ?>" style="position:relative">
  <?php echo $top_corner; ?>
  <?php echo $header_std(); ?>
  <div class="cnt" style="padding-top:16px">
    <p style="font-size:15px;font-weight:700;color:#0f2c52;margin-bottom:12px">Date de la proposition&nbsp;: <?php echo esc_html( $date_prop ); ?></p>

    <div style="background:#d6a353;color:#fff;font-weight:800;font-size:18px;height:48px;display:flex;align-items:center;justify-content:center;text-transform:uppercase;letter-spacing:.04em;margin-bottom:0">Proposition financière</div>

    <table class="tbl" style="margin-bottom:16px">
      <tr>
        <td style="padding:14px 12px;width:42%;vertical-align:middle"><strong style="color:#0f2c52">Lieu de formation</strong></td>
        <td style="padding:14px 12px;color:#0f2c52;vertical-align:middle"><?php echo $p->formation_location ? nl2br( esc_html( $p->formation_location ) ) : 'À définir'; ?></td>
      </tr>
      <tr>
        <td style="padding:14px 12px;vertical-align:middle"><strong style="color:#0f2c52">Date prévue de la formation</strong></td>
        <td style="padding:14px 12px;color:#0f2c52;vertical-align:middle"><?php echo $p->formation_dates ? nl2br( esc_html( $p->formation_dates ) ) : 'À confirmer'; ?></td>
      </tr>
      <tr>
        <td style="padding:14px 12px;vertical-align:middle"><strong style="color:#0f2c52">Date limite de confirmation</strong></td>
        <td style="padding:14px 12px;color:#0f2c52;vertical-align:middle"><?php echo $p->proposal_deadline ? esc_html( $p->proposal_deadline ) : 'Le plus t&ocirc;t possible'; ?></td>
      </tr>
    </table>

    <table class="tbl">
      <tr>
        <th>Désignation</th><th>Montant net de TVA</th><th>Quantité</th><th>Remise</th><th>Total net de TVA</th>
      </tr>
      <tr>
        <td><?php echo $formation_title_esc; ?></td>
        <td><strong><?php echo number_format( (float) $p->formation_price_per_day, 0, ',', '&#160;' ); ?>&nbsp;€/jour</strong></td>
        <td><?php echo (int) $p->formation_days; ?></td>
        <td><?php echo $p->formation_discount ? esc_html( $p->formation_discount ) : '&mdash;'; ?></td>
        <td><strong><?php echo number_format( (float) $p->formation_total, 0, ',', '&#160;' ); ?>&nbsp;€</strong><br><span style="font-weight:400;font-size:10px">net de TVA</span></td>
      </tr>
      <tr><td>Ressources compl&eacute;mentaires</td><td><strong><?php echo $p->extra_resources_label ? esc_html( $p->extra_resources_label ) : 'Offertes'; ?></strong></td><td></td><td></td><td></td></tr>
      <tr><td>Frais de d&eacute;placement et d&apos;h&eacute;bergement</td><td><strong><?php echo $p->travel_costs_label ? esc_html( $p->travel_costs_label ) : 'Offerts'; ?></strong></td><td></td><td></td><td></td></tr>
      <tr style="background:#f0e8d8">
        <td colspan="4"><strong>TOTAL DE LA PROPOSITION</strong></td>
        <td><strong><?php echo number_format( (float) $p->formation_total, 0, ',', '&#160;' ); ?>&nbsp;€</strong><br><span style="font-weight:400;font-size:10px">net de TVA</span></td>
      </tr>
    </table>

    <p style="margin-top:6px;color:#475569">TVA non applicable – article 293 B du CGI</p>
    <p style="margin-top:3px;color:#475569">Déclaration d'activité enregistrée sous le numéro <?php echo esc_html( $acdc['nda'] ); ?> auprès du préfet de région PACA.<br>Cet enregistrement ne vaut pas agrément de l'État.</p>
    <p style="font-weight:700;text-align:right;margin-top:8px;color:#0f2c52">PROPOSITION VALABLE <?php echo (int) $p->proposal_validity_months ?: 3; ?> MOIS</p>
  </div>
  <div style="flex:1"></div>
  <?php echo $fl_footer; ?>
  <?php echo $pied_page( $pg_financiere ); ?>
</div>

<?php /* ── Contact ── */ ?>
<!-- PAGE 16 : Contact -->
<div class="pg" id="pg<?php echo $pg_contact; ?>" style="position:relative">
  <?php echo $top_corner; ?>
  <?php echo $header_std(); ?>

  <!-- Image contact 255px -->
  <div style="width:100%;height:255px;overflow:hidden;flex-shrink:0">
    <?php if ( ! empty( $si['contact'] ) ) : ?>
      <img src="<?php echo esc_url( $si['contact'] ); ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
    <?php else : ?>
      <div style="width:100%;height:255px;background:linear-gradient(90deg,#3d2a1f,#1e1f23 55%,#3d2a1f);"></div>
    <?php endif; ?>
  </div>

  <!-- Bannière Nous contacter -->
  <div style="background:#d6a353;color:#fff;font-weight:800;font-size:16px;width:100%;padding:10px 16px;flex-shrink:0;line-height:1.5">
    NOUS CONTACTER&nbsp;:<br>
    <span style="font-size:15px;font-weight:700">À votre disposition,</span>
  </div>

  <!-- Contenu contact -->
  <div style="padding:4px 14px;flex:1;font-family:Arial,sans-serif">
    <p style="color:#d6a353;font-size:14px;font-weight:700;margin-bottom:12px"><?php echo esc_html( $acdc['contact_name'] ?? 'David Contal' ); ?></p>

    <!-- Coordonnées avec icônes SVG -->
    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px">
      <div style="display:flex;align-items:center;gap:10px">
        <svg width="28" height="28" viewBox="0 0 28 28"><circle cx="14" cy="14" r="13" fill="#f4f0e4" stroke="#d6a353" stroke-width="1.5"/><path d="M8 10h12v9H8z" fill="none" stroke="#0f2c52" stroke-width="1.2"/><polyline points="8,10 14,16 20,10" fill="none" stroke="#0f2c52" stroke-width="1.2"/></svg>
        <span style="color:#0f2c52;text-decoration:underline"><?php echo esc_html( $acdc['email'] ); ?></span>
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <svg width="28" height="28" viewBox="0 0 24 24"><circle cx="12" cy="12" r="11" fill="#f4f0e4" stroke="#d6a353" stroke-width="1.3"/><path d="M17.5 13.9v2a1.33 1.33 0 0 1-1.45 1.33 13.2 13.2 0 0 1-5.76-2.05 13 13 0 0 1-3.99-3.99 13.2 13.2 0 0 1-2.05-5.8A1.33 1.33 0 0 1 5.57 4h2a1.33 1.33 0 0 1 1.33 1.15c.08.56.23 1.1.42 1.87a1.33 1.33 0 0 1-.3 1.41l-.84.84a10.67 10.67 0 0 0 4.12 4.12l.84-.84a1.33 1.33 0 0 1 1.41-.3c.77.2 1.31.35 1.87.42A1.33 1.33 0 0 1 17.5 13.9z" fill="none" stroke="#0f2c52" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span style="color:#0f2c52;text-decoration:underline"><?php echo esc_html( $acdc['phone'] ); ?></span>
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <svg width="28" height="28" viewBox="0 0 28 28"><circle cx="14" cy="14" r="13" fill="#f4f0e4" stroke="#d6a353" stroke-width="1.5"/><circle cx="14" cy="14" r="7" fill="none" stroke="#0f2c52" stroke-width="1.2"/><ellipse cx="14" cy="14" rx="3" ry="7" fill="none" stroke="#0f2c52" stroke-width="1.2"/><line x1="7" y1="14" x2="21" y2="14" stroke="#0f2c52" stroke-width="1.2"/></svg>
        <span style="color:#0f2c52;text-decoration:underline"><?php echo esc_html( $acdc['website'] ?? 'https://acdc-formation.com' ); ?></span>
      </div>
    </div>

    <!-- Deux colonnes : adresse + logo -->
    <div style="display:grid;grid-template-columns:1fr auto;gap:16px;align-items:end">
      <div style="font-size:11px;color:#33475f;line-height:1.7">
        <p style="font-weight:700;color:#0f2c52;margin-bottom:4px"><?php echo esc_html( $acdc['name'] ); ?></p>
        <?php echo nl2br( esc_html( $acdc['address'] . "\n" . $acdc['city'] ) ); ?><br>
        <?php if ( $acdc['siret'] ) echo 'Siret&nbsp;: ' . esc_html( $acdc['siret'] ) . '<br>'; ?>
        Déclaration d'activité enregistrée sous le numéro <?php echo esc_html( $acdc['nda'] ); ?><br>
        auprès du préfet de région Provence-Alpes-Côte d'Azur.<br>
        Cet enregistrement ne vaut pas agrément de l'État.
      </div>
      <div style="width:170px;height:170px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-left:auto">
        <?php if ( $logo_favicon_url ) : ?>
          <img src="<?php echo esc_url( $logo_favicon_url ); ?>" alt="Logo" style="width:170px;height:170px;object-fit:contain;border-radius:8px;">
        <?php else : ?>
          <div style="width:170px;height:170px;background:#f4f5f7;border:1.5px dashed #b0b7c3;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:7.5px;color:#888;font-family:Arial,sans-serif;font-style:italic;">Favicon</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div style="flex:1"></div>
  <?php echo $fl_footer; ?>
  <?php echo $pied_page( $pg_contact ); ?>
</div>

</div><!-- /#canvas -->

<?php if ( ! $client_mode ) : ?>
<script>
function gp(n,el){
  document.querySelectorAll('.pg').forEach(function(p){p.classList.remove('on')});
  document.querySelectorAll('.nb').forEach(function(b){b.classList.remove('on')});
  var pg=document.getElementById('pg'+n);
  if(pg){pg.classList.add('on');}
  el.classList.add('on');
  document.getElementById('canvas').scrollTop=0;
}
/* Ouvrir page 1 par défaut */
(function(){var b=document.querySelector('.nb');if(b){gp(1,b);}})();
</script>
<?php endif; ?>
</body>
</html>
