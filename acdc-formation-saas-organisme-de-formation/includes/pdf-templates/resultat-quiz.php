<?php
/**
 * ACDC 3.25.328 — Le résultat d'un quiz, tel que l'apprenant peut l'emporter.
 *
 * L'extranet affichait un bouton « Télécharger mes résultats (PDF) » conditionné
 * à une colonne « result_document_url » que RIEN, dans tout le plugin, n'écrit —
 * ni ne crée. Le bouton n'est donc jamais apparu, à aucun apprenant, depuis son
 * ajout. Cette pièce se fabrique désormais à la demande, à partir des mêmes
 * données que l'écran : ce qui est lu est ce qui est imprimé.
 *
 * Même charte que les autres documents : l'en-tête vient de la fiche identité,
 * jamais d'une adresse recopiée ici.
 *
 * @package ACDC
 */

defined( 'ABSPATH' ) || exit;

$e    = function ( $v ) { return esc_html( (string) $v ); };
$nl   = function ( $v ) { return nl2br( esc_html( (string) $v ) ); };
$d    = function ( $key ) use ( $data ) { return isset( $data[ $key ] ) ? $data[ $key ] : ''; };
$de   = function ( $key ) use ( $data, $e ) { return $e( isset( $data[ $key ] ) ? $data[ $key ] : '' ); };
$show = function ( $v ) use ( $e ) { $v = trim( (string) $v ); return '' !== $v ? $e( $v ) : '—'; };

$addr_lines = array_values( array_filter( is_array( $d( 'org_addr' ) ) ? $d( 'org_addr' ) : array() ) );

$header = function ( $title ) use ( $d, $e, $de, $addr_lines ) { ?>
<table width="100%" style="margin-bottom:4pt;border-collapse:collapse;">
  <tr>
    <td width="60" style="vertical-align:middle;">
      <?php if ( $d( 'logo_url' ) ) : ?>
      <img src="<?php echo $e( $d( 'logo_url' ) ); ?>" width="55" height="55" alt="Logo" style="object-fit:contain;">
      <?php endif; ?>
    </td>
    <td style="vertical-align:middle;padding-left:6pt;">
      <span style="font-size:12pt;font-weight:bold;color:#0C2D52;"><?php echo $de( 'org_name' ); ?></span><br>
      <span style="font-size:7pt;color:#C5A253;text-transform:uppercase;letter-spacing:0.05em;"><?php echo $de( 'org_sub' ); ?></span>
    </td>
    <td style="vertical-align:top;text-align:right;font-size:7pt;color:#374151;line-height:1.55;">
      <?php foreach ( $addr_lines as $l ) : echo $e( $l ) . '<br>'; endforeach; ?>
      <?php if ( $d( 'org_siret' ) ) : ?>Siret : <?php echo $de( 'org_siret' ); ?><br><?php endif; ?>
      <?php if ( $d( 'org_nda' ) ) : ?>NDA : <?php echo $de( 'org_nda' ); ?><?php endif; ?>
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
.stitle { font-size:7pt; font-weight:bold; color:#C5A253; text-transform:uppercase; letter-spacing:.05em; margin:8pt 0 3pt; }
.lbl { font-size:8pt; font-weight:bold; color:#0C2D52; }
.val { font-size:8pt; color:#1f2937; }
.q { margin-bottom:7pt; page-break-inside:avoid; }
.q-title { font-size:9pt; font-weight:bold; color:#0C2D52; margin-bottom:2pt; }
.q-line { font-size:8.5pt; line-height:1.45; padding:2pt 5pt; border-radius:3pt; margin-bottom:1.5pt; }
.q-ok { background:#eaf7ef; border:0.5pt solid #b7e0c6; }
.q-ko { background:#fdecec; border:0.5pt solid #f2bdbd; }
.q-neutre { background:#f8fafc; border:0.5pt solid #e2e8f0; }
.q-empty { color:#9ca3af; font-style:italic; }
.badge { font-size:7.5pt; font-weight:bold; padding:1pt 5pt; border-radius:8pt; }
.b-ok { background:#eaf7ef; color:#1c7a44; }
.b-ko { background:#fdecec; color:#b3261e; }
.b-part { background:#fff4e5; color:#8a5a12; }
.b-att { background:#eef2f7; color:#41506b; }
.pdf-footer { font-size:6pt; color:#4b5563; text-align:center; line-height:1.7; }
</style>
</head>
<body>

<?php $header( $d( 'titre_document' ) ); ?>

<table width="100%" style="border-collapse:collapse;margin-bottom:6pt;">
  <tr>
    <td width="50%" style="vertical-align:top;padding-right:6pt;">
      <p class="stitle">Apprenant</p>
      <p class="val"><span class="lbl">Nom :</span> <?php echo $show( $d( 'learner_name' ) ); ?></p>
      <p class="val"><span class="lbl">Formation :</span> <?php echo $show( $d( 'formation_title' ) ); ?></p>
    </td>
    <td width="50%" style="vertical-align:top;">
      <p class="stitle">Épreuve</p>
      <p class="val"><span class="lbl">Nature :</span> <?php echo $show( $d( 'purpose_label' ) ); ?></p>
      <p class="val"><span class="lbl">Passée le :</span> <?php echo $show( $d( 'completed_at' ) ); ?></p>
      <p class="val"><span class="lbl">Score :</span> <?php echo $show( $d( 'score_label' ) ); ?>
        <?php if ( '' !== (string) $d( 'verdict_label' ) ) : ?>
          <span class="badge <?php echo $e( $d( 'verdict_class' ) ); ?>"><?php echo $de( 'verdict_label' ); ?></span>
        <?php endif; ?>
      </p>
      <?php if ( '' !== (string) $d( 'seuil_label' ) ) : ?>
      <p class="val"><span class="lbl">Seuil de réussite :</span> <?php echo $de( 'seuil_label' ); ?></p>
      <?php endif; ?>
    </td>
  </tr>
</table>

<p class="stitle">Vos réponses, question par question</p>

<?php foreach ( (array) $d( 'questions' ) as $i => $q ) : ?>
  <div class="q">
    <p class="q-title">
      Q<?php echo (int) $i + 1; ?>. <?php echo $e( $q['title'] ); ?>
      <?php if ( '' !== (string) $q['badge_label'] ) : ?>
        <span class="badge <?php echo $e( $q['badge_class'] ); ?>"><?php echo $e( $q['badge_label'] ); ?></span>
      <?php endif; ?>
    </p>
    <?php if ( '' !== (string) $q['reponse_libre'] ) : ?>
      <div class="q-line q-neutre"><?php echo $nl( $q['reponse_libre'] ); ?></div>
    <?php elseif ( empty( $q['options'] ) ) : ?>
      <div class="q-line q-neutre q-empty">Aucune réponse fournie.</div>
    <?php else : ?>
      <?php foreach ( $q['options'] as $opt ) : ?>
        <div class="q-line <?php echo $e( $opt['classe'] ); ?>">
          <?php echo $e( $opt['marque'] ); ?> <?php echo $e( $opt['texte'] ); ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<hr style="border:none;border-top:0.5pt solid #e2e8f0;margin:8pt 0 4pt;">
<p class="pdf-footer">
  <?php echo $de( 'org_name' ); ?> — <?php echo $e( implode( ' - ', $addr_lines ) ); ?><br>
  <?php if ( $d( 'org_tel' ) ) : ?><?php echo $de( 'org_tel' ); ?> · <?php endif; ?>
  <?php if ( $d( 'org_email' ) ) : ?><?php echo $de( 'org_email' ); ?> · <?php endif; ?>
  <?php echo $de( 'org_web' ); ?><br>
  Document édité le <?php echo $de( 'edite_le' ); ?>.
</p>

</body>
</html>
