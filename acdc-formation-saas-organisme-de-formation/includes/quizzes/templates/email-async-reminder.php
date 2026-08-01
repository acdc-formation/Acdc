<?php
/**
 * Template — E-mail de rappel (24h avant expiration).
 * Variables : identiques au template initial.
 *
 * @package ACDC_OF_SAAS
 * @since   3.21.03.1
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$display_first = '' !== trim( (string) $first_name ) ? trim( (string) $first_name ) : '';
$expires_human = '' !== $expires_at ? mysql2date( 'l j F Y \\à H\\hi', $expires_at, true ) : '';
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?php echo esc_html( $organisation['name'] ); ?></title></head>
<body style="margin:0;padding:0;background:#eef2f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background:#eef2f6;padding:30px 10px;"><tr><td align="center">
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;width:100%;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 4px 14px rgba(0,0,0,0.04);">

<tr><td align="center" style="padding:42px 32px 8px 32px;">
<?php if ( ! empty( $organisation['logo_url'] ) ) : ?>
<img src="<?php echo esc_url( $organisation['logo_url'] ); ?>" alt="<?php echo esc_attr( $organisation['name'] ); ?>" width="140" style="display:block;width:140px;height:auto;margin:0 auto 18px auto;">
<?php endif; ?>
<h1 style="margin:0;font-size:28px;font-weight:700;color:#0f2c52;letter-spacing:-0.5px;">
⏰ Rappel — Plus que 24h
</h1>
</td></tr>

<tr><td style="padding:24px 40px 12px 40px;font-size:15px;line-height:1.6;color:#1f2937;">

<p style="margin:0 0 6px 0;font-size:16px;">
Bonjour <strong><?php echo esc_html( $display_first ); ?></strong>,
</p>

<p style="margin:18px 0 0 0;">
Nous attendons toujours votre passation du quiz <strong><?php echo esc_html( $quiz->title ); ?></strong>. Le délai expire bientôt.
</p>

<?php if ( '' !== $expires_human ) : ?>
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:22px 0;">
<tr><td style="background:#fef6e4;border-left:3px solid #f0b45e;padding:14px 18px;border-radius:6px;font-size:14px;color:#5f4515;">
<strong>⏰ Date limite :</strong> <?php echo esc_html( $expires_human ); ?>
</td></tr>
</table>
<?php endif; ?>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin:28px 0 18px 0;">
<tr><td style="background:#d6a353;border-radius:999px;">
<a href="<?php echo esc_url( $passation_url ); ?>" style="display:inline-block;padding:14px 32px;color:#1f2937;text-decoration:none;font-weight:700;font-size:15px;">
Accéder au quiz maintenant
</a>
</td></tr></table>

<p style="margin:18px 0 0 0;font-size:13px;color:#6b7280;">
Si vous avez déjà commencé, vous pouvez reprendre où vous en étiez via le bouton ci-dessus. Si vous rencontrez une difficulté, contactez votre formateur
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
<span style="font-size:11px;color:#9ca3af;">Cet e-mail vous est envoyé pour vous rappeler une passation en cours.</span>
</td></tr>

</table>
</td></tr></table>
</body></html>
