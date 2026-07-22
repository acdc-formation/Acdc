<?php
defined( 'ABSPATH' ) || exit;

$e   = function( $v ) { return esc_html( (string) $v ); };
$nl  = function( $v ) { return nl2br( esc_html( (string) $v ) ); };
$d   = function( $key ) use ( $data ) { return isset( $data[ $key ] ) ? $data[ $key ] : ''; };
$de  = function( $key ) use ( $data, $e ) { return $e( isset( $data[ $key ] ) ? $data[ $key ] : '' ); };
$show = function( $v ) use ( $e ) { $v = trim( (string) $v ); return $v !== '' ? $e( $v ) : '—'; };

$addr_lines  = array_values( array_filter( is_array( $d('org_addr') ) ? $d('org_addr') : array() ) );
$addr_inline = implode( ' - ', $addr_lines );

$header = function( $title ) use ( $d, $e, $de, $addr_lines ) { ?>
<table width="100%" style="margin-bottom:4pt;border-collapse:collapse;">
  <tr>
    <td width="60" style="vertical-align:middle;">
      <?php if ( $d('logo_url') ) : ?>
      <img src="<?php echo $e( $d('logo_url') ); ?>" width="55" height="55" alt="Logo" style="object-fit:contain;">
      <?php endif; ?>
    </td>
    <td style="vertical-align:middle;padding-left:6pt;">
      <span style="font-size:12pt;font-weight:bold;color:#0C2D52;"><?php echo $de('org_name'); ?></span><br>
      <span style="font-size:7pt;color:#C5A253;text-transform:uppercase;letter-spacing:0.05em;"><?php echo $de('org_sub'); ?></span>
    </td>
    <td style="vertical-align:top;text-align:right;font-size:7pt;color:#374151;line-height:1.55;">
      <?php foreach ( $addr_lines as $l ) : echo $e( $l ) . '<br>'; endforeach; ?>
      <?php if ( $d('org_siret') ) : ?>Siret : <?php echo $de('org_siret'); ?><br><?php endif; ?>
      <?php if ( $d('org_nda') ) : ?>NDA : <?php echo $de('org_nda'); ?><?php endif; ?>
    </td>
  </tr>
</table>
<hr style="border:none;border-top:1.2pt solid #C5A253;margin-bottom:4pt;">
<p style="font-size:14pt;font-weight:bold;color:#0C2D52;margin-bottom:6pt;"><?php echo $e( $title ); ?></p>
<?php };
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:helvetica,arial,sans-serif; font-size:9pt; color:#1f2937; }
@page { margin:12mm 12mm 16mm 12mm; }
.stitle { font-size:7pt; font-weight:bold; color:#C5A253; text-transform:uppercase; letter-spacing:.05em; margin-bottom:3pt; margin-top:8pt; }
.lbl { font-size:8pt; font-weight:bold; color:#0C2D52; }
.val { font-size:8pt; color:#1f2937; }
.q-label { font-size:8pt; font-weight:bold; color:#0C2D52; margin-bottom:2pt; }
.q-val { font-size:8.5pt; color:#1f2937; line-height:1.45; padding:3pt 5pt; background:#f8fafc; border:0.5pt solid #e2e8f0; border-radius:3pt; min-height:16pt; }
.q-empty { color:#9ca3af; font-style:italic; }
.pdf-footer { font-size:6pt; color:#4b5563; text-align:center; line-height:1.7; }
</style>
</head>
<body>

<?php $header( 'Analyse du besoin — ' . $d('learner_name') ); ?>

<?php /* ── Identification ── */ ?>
<table width="100%" style="border-collapse:collapse;margin-bottom:6pt;">
  <tr>
    <td width="49%" style="vertical-align:top;padding-right:6pt;">
      <p class="stitle" style="margin-top:0;">Apprenant</p>
      <table width="100%" style="border-collapse:collapse;">
        <tr><td width="80" class="lbl" style="padding:1pt 0;white-space:nowrap;">Nom :</td><td class="val" style="padding:1pt 0;"><?php echo $show( $d('learner_name') ); ?></td></tr>
        <?php if ( $d('learner_email') ) : ?><tr><td class="lbl" style="padding:1pt 0;white-space:nowrap;">E-mail :</td><td class="val" style="padding:1pt 0;"><?php echo $show( $d('learner_email') ); ?></td></tr><?php endif; ?>
        <?php if ( $d('formation_title') ) : ?><tr><td class="lbl" style="padding:1pt 0;white-space:nowrap;">Formation :</td><td class="val" style="padding:1pt 0;"><?php echo $show( $d('formation_title') ); ?></td></tr><?php endif; ?>
      </table>
    </td>
    <td width="51%" style="vertical-align:top;padding-left:6pt;">
      <p class="stitle" style="margin-top:0;">Analyse</p>
      <table width="100%" style="border-collapse:collapse;">
        <tr><td width="80" class="lbl" style="padding:1pt 0;white-space:nowrap;">Référence :</td><td class="val" style="padding:1pt 0;">#<?php echo $e( $d('nad_id') ); ?></td></tr>
        <tr><td class="lbl" style="padding:1pt 0;white-space:nowrap;">Statut :</td><td class="val" style="padding:1pt 0;"><?php echo $show( $d('nad_statut') ); ?></td></tr>
        <tr><td class="lbl" style="padding:1pt 0;white-space:nowrap;">Date :</td><td class="val" style="padding:1pt 0;"><?php echo $show( $d('nad_date') ); ?></td></tr>
        <?php if ( $d('thematique') ) : ?><tr><td class="lbl" style="padding:1pt 0;white-space:nowrap;">Thématique :</td><td class="val" style="padding:1pt 0;"><?php echo $show( $d('thematique') ); ?></td></tr><?php endif; ?>
      </table>
    </td>
  </tr>
</table>

<?php /* ── Questions / réponses ── */ ?>
<?php
$questions = $d('questions');
if ( ! empty( $questions ) ) :
  // Regrouper par bloc
  $blocs_grouped = array();
  foreach ( $questions as $q ) {
    $bloc_label = ! empty( $q['bloc_label'] ) ? (string) $q['bloc_label'] : 'Autres';
    $blocs_grouped[ $bloc_label ][] = $q;
  }
  foreach ( $blocs_grouped as $bloc_label => $qs ) :
?>
<p class="stitle"><?php echo $e( $bloc_label ); ?></p>
<table width="100%" style="border-collapse:collapse;">
  <?php
  $cols = 2;
  $chunks = array_chunk( $qs, $cols );
  foreach ( $chunks as $row ) :
    // Compléter avec cellule vide si impair
    while ( count($row) < $cols ) { $row[] = null; }
  ?>
  <tr>
    <?php foreach ( $row as $qitem ) :
      $cell_width = round( 100 / $cols ) . '%';
      if ( $qitem === null ) : ?>
      <td width="<?php echo $cell_width; ?>" style="vertical-align:top;padding:2pt 3pt;"></td>
    <?php else :
      $val = isset( $qitem['val'] ) ? $qitem['val'] : '';
      $is_empty = '' === $val;
      $is_long  = strlen( $val ) > 120;
    ?>
      <td width="<?php echo $cell_width; ?>" style="vertical-align:top;padding:2pt 3pt;">
        <p class="q-label"><?php echo $e( $qitem['label'] ); ?></p>
        <p class="q-val<?php echo $is_empty ? ' q-empty' : ''; ?>"><?php echo $is_empty ? '—' : $nl( $val ); ?></p>
      </td>
    <?php endif; ?>
    <?php endforeach; ?>
  </tr>
  <?php endforeach; ?>
</table>
<?php endforeach; ?>
<?php endif; ?>

<htmlpagefooter name="nadfooter">
<p class="pdf-footer">
  <?php echo $e( $addr_inline ); ?><?php if ( $d('org_siret') ) : ?> - Siret : <?php echo $de('org_siret'); ?><?php endif; ?><?php if ( $d('org_nda') ) : ?> - NDA : <?php echo $de('org_nda'); ?><?php endif; ?><br>
  <?php if ( $d('org_email') ) : ?>e-mail : <?php echo $de('org_email'); ?><?php endif; ?><?php if ( $d('org_tel') ) : ?> - Tél : <?php echo $de('org_tel'); ?><?php endif; ?><?php if ( $d('org_web') ) : ?> - site web : <?php echo $de('org_web'); ?><?php endif; ?>
</p>
</htmlpagefooter>
<sethtmlpagefooter name="nadfooter" value="1" />

</body>
</html>
