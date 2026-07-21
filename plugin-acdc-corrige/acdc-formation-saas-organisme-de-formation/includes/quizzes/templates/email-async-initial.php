<?php
/**
 * Template — E-mail d'invitation à passer un quiz async.
 *
 * Variables disponibles :
 *   $email, $first_name, $last_name, $token, $quiz, $expires_at, $custom_message
 *   $passation_url, $organisation (array)
 *
 * Style HTML inline (compatibilité Outlook), couleurs alignées sur la charte ACDC.
 *
 * @package ACDC_OF_SAAS
 * @since   3.21.03.1
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$display_first = '' !== trim( (string) $first_name ) ? trim( (string) $first_name ) : '';
$expires_human = '' !== $expires_at ? mysql2date( 'l j F Y \\à H\\hi', $expires_at, true ) : '';

$purpose_intro = array(
    'positioning' => 'Avant le démarrage de votre formation, votre formateur vous invite à passer un test de positionnement. Il permet d\'adapter le contenu à votre niveau actuel.',
    'assessment'  => 'À l\'issue de votre formation, votre formateur vous invite à passer une évaluation des acquis pour mesurer votre progression.',
    'live'        => 'Votre formateur vous invite à passer un quiz.',
);
$intro_text = $purpose_intro[ $quiz->quiz_purpose ] ?? $purpose_intro['live'];
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( $organisation['name'] ); ?></title>
</head>
<body style="margin:0;padding:0;background:#eef2f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background:#eef2f6;padding:30px 10px;">
<tr><td align="center">

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;width:100%;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 4px 14px rgba(0,0,0,0.04);">

<!-- En-tête : logo + nom organisme -->
<tr><td align="center" style="padding:42px 32px 8px 32px;">
<?php if ( ! empty( $organisation['logo_url'] ) ) : ?>
<img src="<?php echo esc_url( $organisation['logo_url'] ); ?>" alt="<?php echo esc_attr( $organisation['name'] ); ?>" width="140" style="display:block;width:140px;height:auto;margin:0 auto 18px auto;">
<?php endif; ?>
<h1 style="margin:0;font-size:32px;font-weight:700;color:#0f2c52;letter-spacing:-0.5px;">
<?php echo esc_html( $organisation['name'] ); ?>
</h1>
<?php if ( ! empty( $organisation['tagline'] ) ) : ?>
<div style="margin:6px 0 0 0;font-size:13px;font-weight:600;color:#4b5d76;letter-spacing:1.5px;text-transform:uppercase;">
<?php echo esc_html( $organisation['tagline'] ); ?>
</div>
<?php endif; ?>
</td></tr>

<!-- Corps -->
<tr><td style="padding:32px 40px 12px 40px;font-size:15px;line-height:1.6;color:#1f2937;">

<p style="margin:0 0 6px 0;font-size:16px;">
Bonjour <strong><?php echo esc_html( $display_first ); ?></strong>,
</p>

<p style="margin:18px 0 0 0;">
<?php echo esc_html( $intro_text ); ?>
</p>

<?php
$purpose_label = array(
    'positioning' => 'Test de positionnement',
    'assessment'  => 'Évaluation des acquis',
    'live'        => 'Quiz',
);
$label = $purpose_label[ $quiz->quiz_purpose ] ?? 'Quiz';
?>
<p style="margin:14px 0 0 0;">
<?php echo esc_html( $label ); ?> : <strong><?php echo esc_html( $quiz->title ); ?></strong><br>
<?php if ( '' !== $expires_human ) : ?>
Délai pour répondre : <strong>jusqu'au <?php echo esc_html( $expires_human ); ?></strong>
<?php endif; ?>
</p>

<?php if ( '' !== trim( (string) $custom_message ) ) : ?>
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:22px 0;">
<tr><td style="background:#fbf8f7;border-left:3px solid #d6a353;padding:14px 18px;border-radius:6px;font-size:14px;color:#4b5d76;">
<?php echo wp_kses_post( $custom_message ); ?>
</td></tr>
</table>
<?php endif; ?>

<!-- Bouton CTA -->
<table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin:28px 0 18px 0;">
<tr><td style="background:#d6a353;border-radius:999px;">
<?php
$cta_labels = array(
    'positioning' => 'Accéder au test de positionnement',
    'assessment'  => 'Accéder à l’évaluation des acquis',
    'live'        => 'Accéder au quiz',
);
$cta_label = isset( $cta_labels[ $quiz->quiz_purpose ] ) ? $cta_labels[ $quiz->quiz_purpose ] : 'Accéder au quiz';
?>
<a href="<?php echo esc_url( $passation_url ); ?>" style="display:inline-block;padding:14px 32px;color:#1f2937;text-decoration:none;font-weight:700;font-size:15px;">
<?php echo esc_html( $cta_label ); ?>
</a>
</td></tr>
</table>

<p style="margin:18px 0 0 0;font-size:13px;color:#6b7280;">
Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
<a href="<?php echo esc_url( $passation_url ); ?>" style="color:#1e4777;word-break:break-all;font-size:12px;">
<?php echo esc_html( $passation_url ); ?>
</a>
</p>

<p style="margin:22px 0 0 0;">
Si vous avez des questions, vous pouvez nous contacter
<?php if ( ! empty( $organisation['email'] ) ) : ?>
à <a href="mailto:<?php echo esc_attr( $organisation['email'] ); ?>" style="color:#d6a353;font-weight:600;text-decoration:none;"><?php echo esc_html( $organisation['email'] ); ?></a>
<?php endif; ?>
<?php if ( ! empty( $organisation['phone'] ) ) : ?>
ou au <strong><?php echo esc_html( $organisation['phone'] ); ?></strong>
<?php endif; ?>.
</p>

<p style="margin:24px 0 0 0;">
Cordialement,<br>
<strong>L'équipe <?php echo esc_html( $organisation['name'] ); ?></strong>
</p>

</td></tr>

<!-- Footer -->
<tr><td style="padding:24px 40px 32px 40px;border-top:1px solid #e5eaf2;text-align:center;font-size:12px;color:#6b7280;line-height:1.6;">
<?php
$footer_parts = array();
if ( ! empty( $organisation['phone'] ) )   { $footer_parts[] = esc_html( $organisation['phone'] ); }
if ( ! empty( $organisation['email'] ) )   { $footer_parts[] = '<a href="mailto:' . esc_attr( $organisation['email'] ) . '" style="color:#1e4777;">' . esc_html( $organisation['email'] ) . '</a>'; }
if ( ! empty( $organisation['website'] ) ) { $footer_parts[] = '<a href="' . esc_url( $organisation['website'] ) . '" style="color:#1e4777;">' . esc_html( str_replace( array( 'https://', 'http://' ), '', untrailingslashit( $organisation['website'] ) ) ) . '</a>'; }
echo implode( ' &middot; ', $footer_parts );
?><br>
<?php if ( ! empty( $organisation['address'] ) ) : ?>
<?php echo esc_html( $organisation['address'] ); ?><br>
<?php endif; ?>
<span style="font-size:11px;color:#9ca3af;">
Cet e-mail a été envoyé dans le cadre de votre formation. Vos données sont traitées conformément au RGPD.
</span>
</td></tr>

</table>

</td></tr>
</table>
</body>
</html>
