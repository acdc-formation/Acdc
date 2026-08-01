<?php
defined( 'ABSPATH' ) || exit;

$e  = function( $v ) { return esc_html( (string) $v ); };
$nl = function( $v ) { return nl2br( esc_html( (string) $v ) ); };
$d  = function( $key ) use ( $data ) { return isset( $data[ $key ] ) ? $data[ $key ] : ''; };
$de = function( $key ) use ( $data, $e ) { return $e( isset( $data[ $key ] ) ? $data[ $key ] : '' ); };
$show = function( $v ) use ( $e ) { $v = trim((string)$v); return $v !== '' ? $e($v) : '—'; };

$addr_lines  = array_values( array_filter( is_array( $d('org_addr') ) ? $d('org_addr') : array() ) );
$addr_inline = implode(' - ', $addr_lines);

$header = function( $title ) use ( $d, $e, $de, $addr_lines ) { ?>
<table width="100%" style="margin-bottom:4pt;border-collapse:collapse;">
  <tr>
    <td width="60" style="vertical-align:middle;">
      <?php if ( $d('logo_url') ) : ?>
      <img src="<?php echo $e($d('logo_url')); ?>" width="55" height="55" alt="Logo" style="object-fit:contain;">
      <?php endif; ?>
    </td>
    <td style="vertical-align:middle;padding-left:6pt;">
      <span style="font-size:12pt;font-weight:bold;color:#0C2D52;"><?php echo $de('org_name'); ?></span><br>
      <span style="font-size:7pt;color:#C5A253;text-transform:uppercase;letter-spacing:0.05em;"><?php echo $de('org_sub'); ?></span>
    </td>
    <td style="vertical-align:top;text-align:right;font-size:7pt;color:#374151;line-height:1.55;">
      <?php foreach ( $addr_lines as $l ) : echo $e($l).'<br>'; endforeach; ?>
      <?php if ( $d('org_siret') ) : ?>Siret : <?php echo $de('org_siret'); ?><br><?php endif; ?>
      <?php if ( $d('org_nda') ) : ?>NDA : <?php echo $de('org_nda'); ?><?php endif; ?>
    </td>
  </tr>
</table>
<hr style="border:none;border-top:1.2pt solid #C5A253;margin-bottom:4pt;">
<p style="font-size:14pt;font-weight:bold;color:#0C2D52;margin-bottom:6pt;"><?php echo $e($title); ?></p>
<?php };

$footer_name = 'mainfooter';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:helvetica,arial,sans-serif; font-size:9pt; color:#1f2937; }
@page { margin:12mm 12mm 16mm 12mm; }
.stitle { font-size:7pt; font-weight:bold; color:#C5A253; text-transform:uppercase; letter-spacing:.05em; margin-bottom:3pt; }
.lbl { font-size:8pt; font-weight:bold; color:#0C2D52; }
.val { font-size:8pt; color:#1f2937; }
.tlabel { font-size:8pt; font-weight:bold; color:#0C2D52; margin-bottom:1pt; margin-top:3pt; }
.tbody  { font-size:8pt; color:#374151; line-height:1.45; margin-bottom:1pt; }
.mtitle { font-size:7pt; font-weight:bold; color:#C5A253; text-transform:uppercase; margin-bottom:2pt; }
.mval   { font-size:9pt; color:#1f2937; }
.pdf-footer { font-size:6pt; color:#4b5563; text-align:center; line-height:1.7; }
.p2body { font-size:8pt; color:#374151; line-height:1.5; min-height:55pt; }
</style>
</head>
<body>

<?php $header('Recueil des besoins'); ?>

<table width="100%" style="border-collapse:collapse;margin-bottom:4pt;">
  <tr>
    <td width="49%" style="vertical-align:top;padding-right:6pt;">
      <p class="stitle">Identification</p>
      <table width="100%" style="border-collapse:collapse;">
        <tr><td width="90" class="lbl" style="padding:1pt 0;white-space:nowrap;">Entreprise :</td><td class="val" style="padding:1pt 0;"><?php echo $show($d('company_name')); ?></td></tr>
        <tr><td class="lbl" style="padding:1pt 0;white-space:nowrap;">Contact :</td><td class="val" style="padding:1pt 0;"><?php echo $show($d('contact_name')); ?></td></tr>
        <tr><td class="lbl" style="padding:1pt 0;white-space:nowrap;">Thématique :</td><td class="val" style="padding:1pt 0;"><?php echo $show($d('theme')); ?></td></tr>
        <tr><td class="lbl" style="padding:1pt 0;white-space:nowrap;">Canal de recueil :</td><td class="val" style="padding:1pt 0;"><?php echo $show($d('collection_channel')); ?></td></tr>
        <tr><td class="lbl" style="padding:1pt 0;white-space:nowrap;">Date du recueil :</td><td class="val" style="padding:1pt 0;"><?php echo $show($d('collection_date')); ?></td></tr>
        <tr><td class="lbl" style="padding:1pt 0;white-space:nowrap;">Interlocuteur ACDC :</td><td class="val" style="padding:1pt 0;"><?php echo $show($d('acdc_interlocutor')); ?></td></tr>
        <tr><td class="lbl" style="padding:1pt 0;white-space:nowrap;">Statut :</td><td class="val" style="padding:1pt 0;"><?php echo $show($d('status')); ?></td></tr>
      </table>
    </td>
    <td width="51%" style="vertical-align:top;padding-left:6pt;">
      <p class="stitle">Public concerné</p>
      <table width="100%" style="border-collapse:collapse;">
        <tr><td width="95" class="lbl" style="padding:1pt 0;white-space:nowrap;">Public :</td><td class="val" style="padding:1pt 0;"><?php echo $show($d('target_audience')); ?></td></tr>
        <tr><td class="lbl" style="padding:1pt 0;white-space:nowrap;">Niveau :</td><td class="val" style="padding:1pt 0;"><?php echo $show($d('audience_level')); ?></td></tr>
        <tr><td class="lbl" style="padding:1pt 0;white-space:nowrap;">Effectif :</td><td class="val" style="padding:1pt 0;"><?php echo $show($d('learners_count')); ?></td></tr>
        <tr><td class="lbl" style="padding:1pt 0;white-space:nowrap;">Format souhaité :</td><td class="val" style="padding:1pt 0;"><?php echo $show($d('desired_format')); ?></td></tr>
        <tr><td class="lbl" style="padding:1pt 0;white-space:nowrap;">Financement :</td><td class="val" style="padding:1pt 0;"><?php echo $show($d('planned_funding')); ?></td></tr>
        <tr><td class="lbl" style="padding:1pt 0;white-space:nowrap;">Urgence :</td><td class="val" style="padding:1pt 0;"><?php echo $show($d('urgency')); ?></td></tr>
        <tr><td class="lbl" style="padding:1pt 0;white-space:nowrap;">Échéance souhaitée :</td><td class="val" style="padding:1pt 0;"><?php echo $show($d('desired_deadline')); ?></td></tr>
      </table>
    </td>
  </tr>
</table>

<p class="stitle">Besoin</p>
<?php if (trim($d('expressed_need'))) : ?><p class="tlabel">Besoin exprimé</p><p class="tbody"><?php echo $nl($d('expressed_need')); ?></p><?php endif; ?>
<?php if (trim($d('reframed_need'))) : ?><p class="tlabel">Besoin reformulé</p><p class="tbody"><?php echo $nl($d('reframed_need')); ?></p><?php endif; ?>
<?php if (trim($d('context_text'))) : ?><p class="tlabel">Contexte</p><p class="tbody"><?php echo $nl($d('context_text')); ?></p><?php endif; ?>
<?php if (trim($d('current_situation'))) : ?><p class="tlabel">Situation actuelle</p><p class="tbody"><?php echo $nl($d('current_situation')); ?></p><?php endif; ?>

<p class="stitle" style="margin-top:2pt;">Objectifs attendus</p>
<p class="tbody"><?php echo $nl($d('expected_objectives')); ?></p>

<p class="stitle" style="margin-top:2pt;">Contraintes</p>
<p class="tbody"><?php echo $nl($d('constraints_text')); ?></p>

<p class="stitle" style="margin-top:2pt;">Prochaine action</p>
<p class="tbody"><?php echo $show($d('next_action')); ?></p>

<table width="50%" style="border-collapse:collapse;margin-top:6pt;">
  <tr>
    <td style="vertical-align:top;">
      <p class="mtitle">Budget estimatif</p>
      <p class="mval" style="margin-bottom:5pt;"><?php echo $show($d('budget')); ?></p>
      <p class="mtitle">Date prochaine action</p>
      <p class="mval" style="margin-bottom:5pt;"><?php echo $show($d('next_action_date')); ?></p>
      <p class="mtitle">Fait à</p>
      <p class="mval">Cogolin, le <?php echo date_i18n('d/m/Y'); ?></p>
    </td>
  </tr>
</table>
<?php if ($d('signature_url')) : ?>
<div style="position:absolute; right:12mm; bottom:-10mm; width:95mm; text-align:center;">
  <img src="<?php echo $e($d('signature_url')); ?>" width="360" alt="Cachet et signature">
</div>
<?php endif; ?>

<htmlpagefooter name="mainfooter">
<p class="pdf-footer">
  <?php echo $e($addr_inline); ?><?php if ($d('org_siret')) : ?> - Siret : <?php echo $de('org_siret'); ?><?php endif; ?><?php if ($d('org_nda')) : ?> - NDA : <?php echo $de('org_nda'); ?><?php endif; ?><br>
  <?php if ($d('org_email')) : ?>e-mail : <?php echo $de('org_email'); ?><?php endif; ?><?php if ($d('org_tel')) : ?> - Tél : <?php echo $de('org_tel'); ?><?php endif; ?><?php if ($d('org_web')) : ?> - site web : <?php echo $de('org_web'); ?><?php endif; ?>
</p>
</htmlpagefooter>
<sethtmlpagefooter name="mainfooter" value="1" />

<?php if (!$d('client_only')) : ?>
<pagebreak />
<?php $header('Recueil des besoins — suite'); ?>
<p class="stitle">Résumé interne</p>
<p class="p2body"><?php if (trim($d('internal_summary'))) echo $nl($d('internal_summary')); ?></p>
<p class="stitle" style="margin-top:8pt;">Observations complémentaires</p>
<p class="p2body"></p>
<p class="stitle" style="margin-top:8pt;">Synthèse / préconisation</p>
<p class="p2body"></p>
<?php endif; ?>

</body>
</html>
