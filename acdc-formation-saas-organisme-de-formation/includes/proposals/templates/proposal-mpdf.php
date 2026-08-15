<?php
defined( 'ABSPATH' ) || exit;
/**
 * Template mPDF — Proposition commerciale
 * Reproduit fidèlement la structure visuelle du viewer HTML (tb/sh/image/rib/cnt/fl/pied)
 */

$e   = function( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); };
$nl  = function( $v ) { return nl2br( htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ) ); };
$eu  = function( $v ) { return htmlspecialchars( esc_url( (string) $v ), ENT_QUOTES, 'UTF-8' ); };

/* ---- Helpers ---- */
$html_to_plain = function( $html ) {
  $t = preg_replace( '/<li[^>]*>/i', "\n• ", (string) $html );
  $t = preg_replace( '/<br\s*\/?>/i', "\n", $t );
  $t = preg_replace( '/<\/p>/i', "\n", $t );
  return trim( strip_tags( $t ) );
};

$para = function( $txt ) use ( $nl ) {
  $paras = array_filter( array_map( 'trim', explode( "\n\n", (string) $txt ) ) );
  $out = '';
  foreach ( $paras as $pt ) {
    $out .= '<p style="margin-bottom:5pt;text-align:justify;font-size:12pt;color:#33475f;">' . $nl( $pt ) . '</p>';
  }
  return $out ?: '<p style="color:#aaa;font-style:italic;">—</p>';
};

$blist = function( $txt ) use ( $e ) {
  $items = array_filter( array_map( 'trim', explode( "\n", (string) $txt ) ) );
  if ( empty( $items ) ) return '';
  $out = '<table width="100%" style="border-collapse:collapse;">';
  foreach ( $items as $item ) {
    $item = preg_replace( '/^[-•·\x{2022}]\s*/u', '', $item );
    if ( $item ) $out .= '<tr><td style="width:12pt;font-size:12pt;color:#33475f;vertical-align:top;">•</td><td style="font-size:12pt;color:#33475f;padding-bottom:3pt;">' . $e( $item ) . '</td></tr>';
  }
  return $out . '</table>';
};

/* ---- Composants visuels identiques au HTML ---- */
/* Bandeau titre de section (RIB) */
$rib = function( $title ) use ( $e ) {
  return '<table width="100%" style="border-collapse:collapse;margin-bottom:8pt;">'
       . '<tr><td style="background:#c99d4a;color:#fff;font-size:13pt;font-weight:bold;padding:6pt 14pt;text-transform:uppercase;letter-spacing:0.04em;text-align:center;">'
       . $e( $title ) . '</td></tr></table>';
};

/* Footer NOTRE PROPOSITION + pied paginé */
$fl   = '<p style="font-size:8pt;color:#113860;font-weight:bold;text-transform:uppercase;margin-top:4pt;">Notre proposition — ' . htmlspecialchars( $p->formation_title ?: 'Formation professionnelle', ENT_QUOTES, 'UTF-8' ) . '</p>';

/* ---- Données ---- */
$logo_url   = $acdc['logo_url'] ?? '';
$client_co  = htmlspecialchars( $p->client_company, ENT_QUOTES, 'UTF-8' );
/* ACDC 3.25.226 — Même correction que sur le rendu HTML : la référence de
   formation accompagne le titre, du premier document au dernier. */
$form_title_esc = htmlspecialchars(
  method_exists( $this, 'acdc_formation_labelled' )
    ? $this->acdc_formation_labelled( (int) ( $p->formation_id ?? 0 ), $p->formation_title ?: 'Formation professionnelle' )
    : ( $p->formation_title ?: 'Formation professionnelle' ),
  ENT_QUOTES,
  'UTF-8'
);
$prog_days  = max( 1, min( 10, (int) $p->formation_days ) );

/* Images thématique */
$th_code  = ! empty( $p->thematique ) ? (string) $p->thematique : ( ! empty( $formation->thematique ) ? (string) $formation->thematique : '' );
$th_imgs  = $th_code ? $this->get_proposal_thematique_images( $th_code ) : array();
$img_cover= ! empty( $th_imgs['cover'] ) ? (string) $th_imgs['cover'] : ( ! empty( $formation->catalog_image_url ) ? (string) $formation->catalog_image_url : '' );
$img_prog = ! empty( $th_imgs['programme'] ) ? (string) $th_imgs['programme'] : $img_cover;

/* ACDC 3.25.250 — Toutes les images du document passent par la copie réduite
   (voir acdc_pdf_image_src) : le visuel de thématique est repris en bandeau sur
   chaque page, un original de 2,6 Mo pesait donc treize fois. Les quatre
   variables ci-dessous sont les seules sources d'images du document — les
   $_imgN plus bas retombent tous sur $img_cover. */
$logo_url  = $this->acdc_pdf_image_src( $logo_url,  400 );
$img_cover = $this->acdc_pdf_image_src( $img_cover, 1240 );
$img_prog  = $this->acdc_pdf_image_src( $img_prog,  1240 );

/* Générateur de header de page standard */
$pg_header = function( $img_url = '' ) use ( $logo_url, $form_title_esc, $eu ) {
  $logo_html = $logo_url ? '<img src="' . $eu($logo_url) . '" style="width:40pt;height:40pt;display:block;object-fit:contain;">' : '';
  $img_row   = $img_url
    ? '<tr><td style="height:113pt;padding:0;line-height:0;"><img src="' . $eu($img_url) . '" style="width:100%;height:113pt;display:block;object-fit:cover;"></td></tr>'
    : '<tr><td style="height:113pt;background:#3a2a1f;"></td></tr>';
  return
    /* Barre NOTRE PROPOSITION — 24pt = 8.5mm */
    '<table width="100%" style="border-collapse:collapse;margin-bottom:0;">'
    . '<tr><td style="background:#b1812d;color:#fff;font-size:8pt;font-weight:bold;letter-spacing:0.06em;text-transform:uppercase;padding:0 14pt;height:24pt;vertical-align:middle;">NOTRE PROPOSITION</td></tr>'
    . '</table>'
    /* Header formation + logo — 40pt = 14.1mm */
    . '<table width="100%" style="border-collapse:collapse;margin-bottom:0;">'
    . '<tr style="height:40pt;">'
    . '<td style="padding:6pt 14pt;font-size:9.5pt;font-weight:bold;color:#113860;vertical-align:middle;">' . $form_title_esc . '</td>'
    . '<td style="width:54pt;text-align:right;padding:4pt 14pt;vertical-align:middle;">' . $logo_html . '</td>'
    . '</tr>'
    . '</table>'
    /* Image — 113pt = 39.9mm */
    . '<table width="100%" style="border-collapse:collapse;margin-bottom:0;">' . $img_row . '</table>';
};

/* Générateur de footer de page — 20pt = 7.1mm */
$pg_footer = function( $n ) use ( $fl ) {
  return $fl
    . '<table width="100%" style="border-collapse:collapse;margin-top:0;">'
    . '<tr><td style="background:#f1dcc0;font-size:8pt;color:#475569;text-align:right;padding:0 14pt;height:20pt;vertical-align:middle;">' . (int) $n . '</td></tr>'
    . '</table>';
};

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:Arial,Helvetica,sans-serif; font-size:12pt; color:#33475f; }
@page { margin: 0; }
@page :first { margin: 0; }
.pb { page-break-before:always; }
p { margin-bottom:4pt; line-height:1.5; }
ul,ol { margin:0 0 4pt 14pt; }
li { font-size:12pt; margin-bottom:2pt; }
.lbl { font-weight:bold; color:#113860; font-size:12pt; }
.cnt { padding:0 14pt; }
table.fin { border-collapse:collapse; width:100%; font-size:11pt; }
table.fin th { background:#c99d4a; color:#fff; padding:5pt 8pt; font-weight:bold; border:0.5pt solid #d4bc8a; }
table.fin td { border:0.5pt solid #d4bc8a; padding:5pt 8pt; vertical-align:middle; }
table.fin tr.tot td { background:#f8f0e0; font-weight:bold; color:#113860; }
table.res { border-collapse:collapse; width:100%; font-size:11pt; }
table.res td { border:0.5pt solid #d4bc8a; padding:6pt 8pt; vertical-align:middle; }
table.res td.lbl { width:38%; font-weight:bold; color:#113860; background:#fffdf7; }
</style>
</head>
<body>

<?php
/* ══════════════════════════════════════════════
   PAGE 1 — COUVERTURE (pleine page sans marges)
   Table : 70+260+40+262+100=732pt + 55 padding = 787 ~ A4
   ══════════════════════════════════════════════ */
?>
<table style="border-collapse:collapse;table-layout:fixed;width:210mm;">
  <tr>
    <td style="height:75pt;padding:10pt 14pt 6pt;background:#fff;vertical-align:middle;">
      <table width="100%" style="border-collapse:collapse;">
        <tr>
          <td style="vertical-align:middle;font-size:17pt;font-weight:bold;color:#113860;line-height:1.2;"><?php echo $form_title_esc; ?></td>
          <td style="width:80pt;text-align:right;vertical-align:middle;">
            <?php if ( $logo_url ) : ?>
            <img src="<?php echo $eu($logo_url); ?>" style="width:63pt;height:49pt;display:block;object-fit:contain;">
            <?php endif; ?>
          </td>
        </tr>
      </table>
    </td>
  </tr>
  <tr>
    <td style="height:250pt;padding:0;line-height:0;">
      <?php if ( $img_cover ) : ?>
      <img src="<?php echo $eu($img_cover); ?>" style="width:210mm;height:88.2mm;display:block;object-fit:cover;">
      <?php else : ?>
      <div style="height:250pt;background:#3a2a1f;"></div>
      <?php endif; ?>
    </td>
  </tr>
  <tr>
    <td style="height:42pt;background:#c99d4a;vertical-align:middle;text-align:center;padding:0;">
      <span style="font-size:15pt;font-weight:bold;color:#fff;text-transform:uppercase;letter-spacing:0.06em;">PROPOSITION COMMERCIALE</span>
    </td>
  </tr>
  <tr>
    <td style="height:270pt;vertical-align:middle;text-align:center;padding:20pt 30pt;background:#fff;">
      <p style="font-size:22pt;font-weight:bold;color:#113860;margin-bottom:14pt;"><?php echo $client_co; ?></p>
      <p style="font-size:10pt;color:#6b7280;font-style:italic;margin-bottom:6pt;">À l'attention de&nbsp;:</p>
      <p style="font-size:15pt;font-weight:bold;font-style:italic;color:#113860;margin-bottom:6pt;"><?php echo $e($p->client_name); ?></p>
      <?php if ( $p->client_title ) : ?>
      <p style="font-size:12pt;font-style:italic;color:#33475f;"><?php echo $e($p->client_title); ?></p>
      <?php endif; ?>
    </td>
  </tr>
  <tr>
    <td style="height:105pt;background:#c99d4a;vertical-align:middle;padding:14pt 20pt;">
      <p style="font-size:11pt;font-weight:bold;font-style:italic;color:#fff;margin-bottom:6pt;">Date de la proposition&nbsp;: <?php echo $e($date_prop); ?></p>
      <p style="font-size:11pt;font-weight:bold;font-style:italic;color:#fff;margin-bottom:6pt;">Proposition réalisée par&nbsp;: <?php echo $e($acdc['contact_name'] ?? ''); ?></p>
      <p style="font-size:11pt;font-weight:bold;font-style:italic;color:#fff;">Tél&nbsp;: <?php echo $e($acdc['phone']); ?> · <?php echo $e($acdc['email']); ?></p>
    </td>
  </tr>
</table>

<?php
/* ══════════════════ PAGE 2 — À PROPOS DE VOUS ══════════════════ */
$_img2 = ! empty( $th_imgs['apropos'] ) ? (string)$th_imgs['apropos'] : $img_cover;
?>
<div class="pb"></div>
<?php echo $pg_header( $_img2 ); echo $rib( 'À propos de vous' ); ?>
<div class="cnt">
<h2 style="font-size:12pt;font-weight:bold;color:#113860;margin-bottom:8pt;"><?php echo $client_co; ?></h2>
<?php echo $para( ! empty( $p->client_about_text ) ? (string)$p->client_about_text : '' ); ?>
<?php if ( $p->client_siret || $p->client_address || $p->client_activity ) : ?>
<table width="100%" style="border-collapse:collapse;margin-top:8pt;font-size:8.5pt;background:#f8f9fb;border:0.5pt solid #dde1e8;">
  <?php if ( $p->client_siret )   : ?><tr><td class="lbl" style="width:110pt;padding:3pt 8pt;border-bottom:0.5pt solid #dde1e8;">Raison sociale</td><td style="padding:3pt 8pt;border-bottom:0.5pt solid #dde1e8;"><?php echo $e($p->client_company); ?></td></tr><?php endif; ?>
  <?php if ( $p->client_siret )   : ?><tr><td class="lbl" style="padding:3pt 8pt;border-bottom:0.5pt solid #dde1e8;">SIRET</td><td style="padding:3pt 8pt;border-bottom:0.5pt solid #dde1e8;"><?php echo $e($p->client_siret); ?></td></tr><?php endif; ?>
  <?php if ( $p->client_address ) : ?><tr><td class="lbl" style="padding:3pt 8pt;border-bottom:0.5pt solid #dde1e8;">Adresse</td><td style="padding:3pt 8pt;border-bottom:0.5pt solid #dde1e8;"><?php echo $e($p->client_address); ?></td></tr><?php endif; ?>
  <?php if ( $p->client_activity ): ?><tr><td class="lbl" style="padding:3pt 8pt;">Activité</td><td style="padding:3pt 8pt;"><?php echo $e($p->client_activity); ?></td></tr><?php endif; ?>
</table>
<?php endif; ?>
</div>
<?php echo $pg_footer(2); ?>

<?php
/* ══════════════════ PAGE 3 — VOTRE PROJET ══════════════════ */
$_img3 = ! empty( $th_imgs['projet'] ) ? (string)$th_imgs['projet'] : $img_cover;
?>
<div class="pb"></div>
<?php echo $pg_header( $_img3 ); echo $rib( 'Votre projet & vos besoins' ); ?>
<div class="cnt">
<h2 style="font-size:12pt;font-weight:bold;color:#113860;margin-bottom:6pt;"><?php echo $client_co; ?></h2>
<?php echo $para( ! empty( $p->about_project ) ? (string)$p->about_project : '' ); ?>
<table class="fin" style="margin:8pt 0 10pt;">
  <tr><th style="width:40%;">Élément</th><th>Organisation retenue</th></tr>
  <tr><td class="lbl">Durée</td><td><?php echo $e($p->formation_days); ?> jour<?php echo (int)$p->formation_days>1?'s':'';?> de <?php echo $e($p->formation_hours_per_day);?>h (<?php echo $e($total_hours);?>h)</td></tr>
  <tr><td class="lbl">Effectifs</td><td>Groupe : <?php echo $e($p->formation_learners_count);?> apprenant<?php echo (int)$p->formation_learners_count>1?'s':'';?></td></tr>
  <?php if($p->formation_funding):?><tr><td class="lbl">Financement</td><td><?php echo $e($p->formation_funding);?></td></tr><?php endif;?>
  <?php if($p->formation_dates):?><tr><td class="lbl">Dates prévues</td><td><?php echo $nl($p->formation_dates);?></td></tr><?php endif;?>
  <?php if($p->formation_location):?><tr><td class="lbl">Lieu</td><td><?php echo $nl($p->formation_location);?></td></tr><?php endif;?>
</table>
<?php
$_pub = ! empty( $p->formation_public ) ? (string) $p->formation_public
      : ( ! empty( $formation->catalog_audience ) ? strip_tags( (string) $formation->catalog_audience ) : '' );
if ( $_pub ) :
?>
<p class="lbl" style="margin-top:6pt;">Public concerné</p>
<?php echo $blist( $_pub ); ?>
<?php endif; ?>
</div>
<?php echo $pg_footer(3); ?>

<?php
/* ══════════════════ PAGE 4 — NOUS ══════════════════ */
$_img4 = ! empty( $th_imgs['nous'] ) ? (string)$th_imgs['nous'] : $img_cover;
$_about_acdc = ! empty( $p->about_acdc_text ) ? (string)$p->about_acdc_text
             : get_option( 'acdc_of_proposal_about_acdc', "ACDC Formation conçoit et anime des formations professionnelles centrées sur les usages réels du terrain." );
?>
<div class="pb"></div>
<?php echo $pg_header( $_img4 ); echo $rib( 'Nous' ); ?>
<div class="cnt">
<h2 style="font-size:12pt;font-weight:bold;color:#113860;margin-bottom:6pt;"><?php echo $e($acdc['name']); ?></h2>
<?php echo $para( $_about_acdc ); ?>
</div>
<?php echo $pg_footer(4); ?>

<?php
/* ══════════════════ PAGE 5 — FORMATEURS ══════════════════ */
if ( ! empty( $trainers ) ) :
?>
<div class="pb"></div>
<?php echo $pg_header(); echo $rib( 'Nos formateurs' ); ?>
<div class="cnt">
<?php foreach ( $trainers as $_tr ) :
  $_bio_ov = isset( $trainer_bios[(int)$_tr->id] ) ? (string)$trainer_bios[(int)$_tr->id] : '';
  $_bio    = $_bio_ov ?: ( ! empty( $_tr->bio ) ? (string)$_tr->bio : '' );
?>
<h3 style="font-size:10pt;font-weight:bold;color:#113860;margin:4pt 0 3pt;"><?php echo $e(trim($_tr->first_name.' '.$_tr->last_name)); ?></h3>
<?php echo $para( $_bio ); ?><br>
<?php endforeach; ?>
</div>
<?php echo $pg_footer(5); ?>
<?php endif; ?>

<?php
/* ══════════════════ PAGE 6 — NOTRE APPROCHE ══════════════════ */
$_app_bef = ! empty( $p->approach_before ) ? (string)$p->approach_before : get_option('acdc_of_proposal_approach_before', "Entretien préparatoire.\nTest de positionnement en ligne.");
$_app_dur = ! empty( $p->approach_during ) ? (string)$p->approach_during : get_option('acdc_of_proposal_approach_during', "Formateurs expérimentés et certifiés.\n20% de théorie - 80% de mise en pratique.");
$_app_aft = ! empty( $p->approach_after  ) ? (string)$p->approach_after  : get_option('acdc_of_proposal_approach_after',  "Bibliothèque de prompts.\nÉvaluation à froid.");
?>
<div class="pb"></div>
<?php echo $pg_header(); echo $rib( 'Notre approche : une proposition complète' ); ?>
<div class="cnt">
<p style="font-weight:bold;margin-bottom:10pt;">Avant, pendant et après la formation : tout est pensé pour renforcer l'implication, l'ancrage des savoirs et la mise en pratique.</p>
<table width="100%" style="border-collapse:collapse;margin-bottom:6pt;">
  <tr style="background:#f8f0e0;"><td style="padding:5pt 10pt;font-weight:bold;color:#c99d4a;font-size:12pt;border-bottom:0.5pt solid #d4bc8a;">▶ Avant : préparer</td></tr>
  <tr><td style="padding:4pt 10pt 8pt;"><?php echo $blist($_app_bef); ?></td></tr>
  <tr style="background:#f8f0e0;"><td style="padding:5pt 10pt;font-weight:bold;color:#c99d4a;font-size:12pt;border-top:0.5pt solid #d4bc8a;border-bottom:0.5pt solid #d4bc8a;">▶ Pendant : exécuter</td></tr>
  <tr><td style="padding:4pt 10pt 8pt;"><?php echo $blist($_app_dur); ?></td></tr>
  <tr style="background:#f8f0e0;"><td style="padding:5pt 10pt;font-weight:bold;color:#c99d4a;font-size:12pt;border-top:0.5pt solid #d4bc8a;border-bottom:0.5pt solid #d4bc8a;">▶ Après : ancrer</td></tr>
  <tr><td style="padding:4pt 10pt 8pt;"><?php echo $blist($_app_aft); ?></td></tr>
</table>
</div>
<?php echo $pg_footer(6); ?>

<?php
/* ══════════════════ PAGE 7 — OBJECTIF DE LA FORMATION ══════════════════ */
$_img7 = ! empty( $th_imgs['objectifs'] ) ? (string)$th_imgs['objectifs'] : $img_cover;
$_obj_form = ! empty( $formation->objectives ) ? $html_to_plain( (string)$formation->objectives ) : '';
$_desc     = ! empty( $formation->description_text ) ? $html_to_plain( (string)$formation->description_text ) : '';
$_pub7     = ! empty( $p->formation_public ) ? (string)$p->formation_public : ( ! empty($formation->catalog_audience) ? strip_tags((string)$formation->catalog_audience) : '' );
$_prereq   = ! empty( $formation->prerequisites ) ? $html_to_plain( (string)$formation->prerequisites ) : '';
?>
<div class="pb"></div>
<?php echo $pg_header( $_img7 ); echo $rib( 'Objectif de la formation' ); ?>
<div class="cnt">
<table width="100%" style="border-collapse:collapse;">
  <tr>
    <td width="35%" style="vertical-align:top;padding-right:10pt;border-right:1pt solid #e5e7eb;">
      <?php if($p->formation_days):?><p class="lbl" style="margin-bottom:2pt;">Durée :</p><p style="color:#113860;font-weight:bold;margin-bottom:6pt;"><?php echo $e($p->formation_days);?> j (<?php echo $e($total_hours);?>h)</p><?php endif;?>
      <?php if($_pub7):?><p class="lbl" style="margin-bottom:2pt;">Public cible :</p><p style="color:#113860;margin-bottom:6pt;"><?php echo $e($_pub7);?></p><?php endif;?>
      <?php if($_prereq):?><p class="lbl" style="margin-bottom:2pt;">Prérequis :</p><p style="color:#33475f;margin-bottom:6pt;"><?php echo $nl($_prereq);?></p><?php endif;?>
      <?php if($p->formation_location):?><p class="lbl" style="margin-bottom:2pt;">Modalité :</p><p style="color:#113860;"><?php echo $nl($p->formation_location);?></p><?php endif;?>
    </td>
    <td width="65%" style="vertical-align:top;padding-left:10pt;">
      <div style="background:#f8f0e0;padding:8pt;border-left:3pt solid #c99d4a;margin-bottom:8pt;">
        <p class="lbl" style="margin-bottom:4pt;">Objectif de la formation</p>
        <?php echo $para( $_obj_form ); ?>
      </div>
      <?php if($_desc):?><p class="lbl" style="margin-bottom:4pt;">Pourquoi cette formation&nbsp;?</p><?php echo $blist($_desc);?><?php endif;?>
    </td>
  </tr>
</table>
</div>
<?php echo $pg_footer(7); ?>

<?php
/* ══════════════════ PAGE 8 — OBJ. PÉDAGO ══════════════════ */
$_img8   = ! empty( $th_imgs['pedago'] ) ? (string)$th_imgs['pedago'] : $img_cover;
$_obj_p  = ! empty( $p->custom_objectives ) ? (string)$p->custom_objectives : $html_to_plain( (string)($formation->objectives??'') );
$_meth   = ! empty( $p->custom_methods )   ? (string)$p->custom_methods : strip_tags((string)($formation->moyens_pedago??''));
$_eval   = ! empty( $p->custom_evaluation ) ? (string)$p->custom_evaluation : '';
$_obj_items = array_filter( array_map('trim', explode("\n", $_obj_p)) );
?>
<div class="pb"></div>
<?php echo $pg_header( $_img8 ); echo $rib( 'Objectifs pédagogiques' ); ?>
<div class="cnt">
<table width="100%" style="border-collapse:collapse;margin-bottom:8pt;">
<?php foreach( $_obj_items as $_oi ) :
  $_oi = preg_replace('/^[-•·\x{2022}]\s*/u','',$_oi);
  if(!$_oi) continue;
  if(preg_match('/^([\p{L}]+)\s+(.+)/u',$_oi,$_om)&&mb_strlen($_om[1])<20):
?>
<tr><td style="width:10pt;font-size:12pt;color:#33475f;vertical-align:top;">•</td><td style="font-size:12pt;color:#33475f;padding-bottom:3pt;"><strong style="color:#113860;"><?php echo $e($_om[1]);?></strong> <?php echo $e($_om[2]);?></td></tr>
<?php else:?>
<tr><td style="width:10pt;font-size:12pt;color:#33475f;vertical-align:top;">•</td><td style="font-size:12pt;color:#33475f;padding-bottom:3pt;"><?php echo $e($_oi);?></td></tr>
<?php endif; endforeach;?>
</table>
<?php if($_meth):echo $rib('Méthodes pédagogiques'); echo '<div class="cnt">'.$blist($_meth).'</div>';endif;?>
<?php if($_eval):echo $rib('Modalités d\'évaluation'); echo '<div class="cnt">'.$blist($_eval).'</div>';endif;?>
</div>
<?php echo $pg_footer(8); ?>

<?php
/* ══════════════════ PAGES PROGRAMME ══════════════════ */
$prog_vars_arr = array(
  !empty($p->program_j1) ?(string)$p->program_j1:'', !empty($p->program_j2)?(string)$p->program_j2:'',
  !empty($p->program_j3) ?(string)$p->program_j3:'', !empty($p->program_j4)?(string)$p->program_j4:'',
  !empty($p->program_j5) ?(string)$p->program_j5:'', !empty($p->program_j6)?(string)$p->program_j6:'',
  !empty($p->program_j7) ?(string)$p->program_j7:'', !empty($p->program_j8)?(string)$p->program_j8:'',
  !empty($p->program_j9) ?(string)$p->program_j9:'', !empty($p->program_j10)?(string)$p->program_j10:'',
);
for($__j=1;$__j<=$prog_days;$__j++):
  $_ptxt = $prog_vars_arr[$__j-1];
  $_plines = array_filter(array_map('trim',explode("\n",$_ptxt)));
?>
<div class="pb"></div>
<?php echo $pg_header($img_prog); echo $rib('Déroulé de la journée '.$__j); ?>
<div class="cnt">
<?php if(empty($_plines)):?>
<p style="color:#aaa;font-style:italic;">Programme de la journée <?php echo $__j;?> à compléter.</p>
<?php else: foreach($_plines as $_pl):
  if(preg_match('/^Jour\s+\d+/ui',$_pl)):?>
<p style="font-size:9.5pt;font-weight:bold;color:#113860;margin:4pt 0 2pt;"><?php echo $e($_pl);?></p>
<?php elseif(preg_match('/^(Matin|Après-midi)/ui',$_pl)):?>
<p style="font-size:12pt;font-weight:bold;color:#c99d4a;margin:4pt 0 2pt;"><?php echo $e($_pl);?></p>
<?php elseif(preg_match('/^Compétences/ui',$_pl)):?>
<p style="font-size:8.5pt;margin:3pt 0;"><strong style="color:#113860;">Compétences développées :</strong> <?php echo $e(preg_replace('/^Compétences?\s*(développées)?\s*:\s*/ui','',$_pl));?></p>
<?php elseif(preg_match('/^(Évaluation|Evaluation)/ui',$_pl)):?>
<p style="font-size:12pt;font-weight:bold;color:#113860;margin:4pt 0 2pt;"><?php echo $e($_pl);?></p>
<?php elseif(preg_match('/^[-•·\x{2022}]\s*(.+)/u',$_pl,$_pm)):?>
<table style="border-collapse:collapse;width:100%;margin-bottom:1pt;"><tr><td style="width:10pt;font-size:8.5pt;color:#33475f;vertical-align:top;">•</td><td style="font-size:8.5pt;color:#33475f;"><?php echo $e($_pm[1]);?></td></tr></table>
<?php else:?>
<p style="font-size:8.5pt;font-style:italic;color:#33475f;margin:2pt 0;"><?php echo $e($_pl);?></p>
<?php endif; endforeach; endif;?>
</div>
<?php echo $pg_footer(8+$__j); ?>
<?php endfor;?>

<?php
/* ══════════════════ PAGE RESSOURCES ══════════════════ */
$_pg_res = 9 + $prog_days;
$_res_txt = !empty($p->extra_resources)?(string)$p->extra_resources:(!empty($formation->ressources)?strip_tags((string)$formation->ressources):'');
$_res_lines = array_filter(array_map('trim',explode("\n",$_res_txt)));
?>
<div class="pb"></div>
<?php echo $pg_header(); echo $rib('Ressources complémentaires mises à disposition'); ?>
<div class="cnt">
<table class="res" style="margin-top:6pt;">
<?php foreach($_res_lines as $_rl):
  if(strpos($_rl,':')!==false):
    list($_rl_lab,$_rl_dsc)=explode(':',$_rl,2);?>
<tr><td class="lbl"><?php echo $e(trim($_rl_lab));?></td><td><?php echo $e(trim($_rl_dsc));?></td></tr>
<?php else:?><tr><td colspan="2"><?php echo $e($_rl);?></td></tr>
<?php endif; endforeach;?>
</table>
</div>
<?php echo $pg_footer($_pg_res); ?>

<?php
/* ══════════════════ PAGE FINANCIÈRE ══════════════════ */
$_pg_fin = 10 + $prog_days;
?>
<div class="pb"></div>
<?php echo $pg_header(); ?>
<div class="cnt">
<p class="lbl" style="margin-bottom:10pt;">Date de la proposition&nbsp;: <?php echo $e($date_prop);?></p>
<?php echo $rib('Proposition financière');?>
<table class="fin" style="margin-bottom:8pt;">
  <?php if($p->formation_location):?><tr><td class="lbl" style="width:38%;">Lieu de formation</td><td><?php echo $nl($p->formation_location);?></td></tr><?php endif;?>
  <?php if($p->formation_dates):?><tr><td class="lbl">Date prévue</td><td><?php echo $nl($p->formation_dates);?></td></tr><?php endif;?>
  <tr><td class="lbl">Date limite de confirmation</td><td><?php echo $p->proposal_deadline?$e($p->proposal_deadline):'Le plus tôt possible';?></td></tr>
</table>
<table class="fin" style="margin-bottom:6pt;">
  <tr><th style="width:40%;">Désignation</th><th>Montant net TVA</th><th>Qté</th><th>Remise</th><th>Total net TVA</th></tr>
  <tr>
    <td><?php echo $form_title_esc;?></td>
    <td style="font-weight:bold;"><?php echo $e(number_format((float)$p->formation_price_per_day,0,',',' '));?>&nbsp;€/jour</td>
    <td style="text-align:center;font-weight:bold;"><?php echo $e($p->formation_days);?></td>
    <td style="text-align:center;"><?php echo $p->formation_discount?$e($p->formation_discount):'—';?></td>
    <td style="font-weight:bold;"><?php echo $e(number_format((float)$p->formation_total,0,',',' '));?>&nbsp;€</td>
  </tr>
  <tr><td>Ressources complémentaires</td><td style="font-weight:bold;"><?php echo $p->extra_resources_label?$e($p->extra_resources_label):'Offertes';?></td><td></td><td></td><td></td></tr>
  <tr><td>Frais de déplacement</td><td style="font-weight:bold;"><?php echo $p->travel_costs_label?$e($p->travel_costs_label):'Offertes';?></td><td></td><td></td><td></td></tr>
  <tr class="tot"><td colspan="4" style="text-align:right;">TOTAL DE LA PROPOSITION</td><td><?php echo $e(number_format((float)$p->formation_total,0,',',' '));?>&nbsp;€ net de TVA</td></tr>
</table>
<p style="font-size:8pt;color:#6b7280;margin-bottom:3pt;">TVA non applicable – article 293 B du CGI</p>
<p style="font-size:8pt;color:#6b7280;">Déclaration d'activité enregistrée sous le numéro <?php echo $e($acdc['nda']);?> auprès du préfet de région Provence-Alpes-Côte d'Azur.</p>
<p style="text-align:center;font-weight:bold;color:#113860;text-decoration:underline;margin-top:10pt;">PROPOSITION VALABLE <?php echo $p->proposal_validity_months?$e($p->proposal_validity_months):'3';?> MOIS</p>
</div>
<?php echo $pg_footer($_pg_fin);?>

<?php
/* ══════════════════ PAGE CONTACT ══════════════════ */
$_pg_con = 11 + $prog_days;
$_fav = $this->acdc_pdf_image_src( $acdc['logo_favicon_url'] ?? $logo_url, 400 );
?>
<div class="pb"></div>
<?php echo $pg_header(); echo $rib('Nous contacter'); ?>
<div class="cnt">
<p style="font-size:11pt;color:#c99d4a;font-weight:bold;margin-bottom:6pt;"><?php echo $e($acdc['contact_name']??'');?></p>
<table width="100%" style="border-collapse:collapse;">
  <tr>
    <td width="55%" style="vertical-align:top;padding-right:12pt;">
      <p style="margin-bottom:4pt;">✉ <a href="mailto:<?php echo $e($acdc['email']);?>" style="color:#113860;"><?php echo $e($acdc['email']);?></a></p>
      <p style="margin-bottom:4pt;">☎ <?php echo $e($acdc['phone']);?></p>
      <p style="margin-bottom:10pt;">🌐 <a href="<?php echo $eu($acdc['website']??'');?>" style="color:#113860;"><?php echo $e($acdc['website']??'');?></a></p>
      <p class="lbl" style="margin-bottom:3pt;"><?php echo $e($acdc['name']);?></p>
      <p style="font-size:8.5pt;margin-bottom:2pt;"><?php echo $e($acdc['address']);?></p>
      <p style="font-size:8.5pt;margin-bottom:2pt;"><?php echo $e($acdc['city']);?></p>
      <?php if($acdc['siret']):?><p style="font-size:8pt;margin-bottom:2pt;">Siret : <?php echo $e($acdc['siret']);?></p><?php endif;?>
      <p style="font-size:7.5pt;color:#6b7280;margin-top:4pt;">NDA : <?php echo $e($acdc['nda']);?></p>
    </td>
    <td width="45%" style="text-align:center;vertical-align:middle;">
      <?php if($_fav):?><img src="<?php echo $eu($_fav);?>" style="width:100pt;height:100pt;display:block;margin:0 auto;object-fit:contain;"><?php endif;?>
    </td>
  </tr>
</table>
</div>
<?php echo $pg_footer($_pg_con);?>

</body>
</html>
