<?php
/**
 * Template — E-mail de confirmation après passation.
 * Variables : identiques au template initial + 'score' (optionnel).
 *
 * @package ACDC_OF_SAAS
 * @since   3.21.03.1
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$display_first = '' !== trim( (string) $first_name ) ? trim( (string) $first_name ) : '';
$score = isset( $args['score'] ) ? $args['score'] : null;

// hotfix49 — Libellés contextuels selon la finalité
$confirm_h1 = array(
    'positioning' => 'Test de positionnement validé !',
    'diagnostic'  => 'Évaluation diagnostique validée !',
    'assessment'  => 'Évaluation des acquis validée !',
    'live'        => 'Quiz validé !',
);
$confirm_body = array(
    'positioning' => 'Vos réponses au test de positionnement',
    'diagnostic'  => 'Vos réponses à l’évaluation diagnostique',
    'assessment'  => 'Vos réponses à l’évaluation des acquis',
    'live'        => 'Vos réponses au quiz',
);
$confirm_h1_text   = isset( $confirm_h1[ $quiz->quiz_purpose ] )   ? $confirm_h1[ $quiz->quiz_purpose ]   : $confirm_h1['live'];
$confirm_body_text = isset( $confirm_body[ $quiz->quiz_purpose ] ) ? $confirm_body[ $quiz->quiz_purpose ] : $confirm_body['live'];
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?php echo esc_html( $organisation['name'] ); ?></title></head>
<body style="margin:0;padding:0;background:#eef2f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background:#eef2f6;padding:30px 10px;"><tr><td align="center">
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;width:100%;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 4px 14px rgba(0,0,0,0.04);">

<tr><td align="center" style="padding:42px 32px 8px 32px;">
<?php if ( ! empty( $organisation['logo_url'] ) ) : ?>
<img src="<?php echo esc_url( $organisation['logo_url'] ); ?>" alt="<?php echo esc_attr( $organisation['name'] ); ?>" width="140" style="display:block;width:140px;height:auto;margin:0 auto 18px auto;">
<?php endif; ?>
<div style="font-size:48px;line-height:1;color:#1d6e4f;margin-bottom:8px;">✓</div>
<h1 style="margin:0;font-size:28px;font-weight:700;color:#0f2c52;letter-spacing:-0.5px;">
<?php echo esc_html( $confirm_h1_text ); ?>
</h1>
</td></tr>

<tr><td style="padding:24px 40px 12px 40px;font-size:15px;line-height:1.6;color:#1f2937;">

<p style="margin:0 0 6px 0;font-size:16px;">
Bonjour <strong><?php echo esc_html( $display_first ); ?></strong>,
</p>

<p style="margin:18px 0 0 0;">
<?php echo esc_html( $confirm_body_text ); ?> <strong><?php echo esc_html( $quiz->title ); ?></strong> ont bien été enregistrées. Merci pour votre participation.
</p>

<?php if ( null !== $score ) : ?>
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:22px 0;">
<tr><td style="background:#d4f0e2;border-left:3px solid #1d6e4f;padding:14px 18px;border-radius:6px;font-size:14px;color:#1d6e4f;">
<strong>Score indicatif : <?php echo esc_html( number_format_i18n( $score, 1 ) ); ?>%</strong><br>
<span style="font-size:13px;color:#4b5d76;font-weight:normal;">Votre formateur reviendra vers vous avec une analyse plus détaillée si nécessaire.</span>
</td></tr>
</table>
<?php endif; ?>

<p style="margin:18px 0 0 0;">
Si vous avez des questions sur cette passation, contactez votre formateur
<?php if ( ! empty( $organisation['email'] ) ) : ?>
à <a href="mailto:<?php echo esc_attr( $organisation['email'] ); ?>" style="color:#d6a353;text-decoration:none;"><?php echo esc_html( $organisation['email'] ); ?></a>
<?php endif; ?>.
</p>

<p style="margin:24px 0 0 0;">
Cordialement,<br>
<strong>L'équipe <?php echo esc_html( $organisation['name'] ); ?></strong>
</p>

</td></tr>

<tr><td style="padding:24px 40px 32px 40px;border-top:1px solid #e5eaf2;text-align:center;font-size:12px;color:#6b7280;line-height:1.6;">
<?php
$footer_parts = array();
if ( ! empty( $organisation['phone'] ) )   { $footer_parts[] = esc_html( $organisation['phone'] ); }
if ( ! empty( $organisation['email'] ) )   { $footer_parts[] = '<a href="mailto:' . esc_attr( $organisation['email'] ) . '" style="color:#1e4777;">' . esc_html( $organisation['email'] ) . '</a>'; }
echo implode( ' &middot; ', $footer_parts );
?><br>
<span style="font-size:11px;color:#9ca3af;">Vos données sont traitées conformément au RGPD.</span>
</td></tr>

</table>
</td></tr></table>
</body></html>
