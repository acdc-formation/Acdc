<?php
/**
 * Template — E-mail de relance manuelle (formateur → apprenant).
 * Envoyé manuellement depuis l'interface, distinct du rappel automatique 24h.
 *
 * Variables : $email, $first_name, $last_name, $quiz, $passation_url,
 *             $expires_at, $organisation, $sender_name (formateur qui relance)
 *
 * @package ACDC_OF_SAAS
 * @since   3.21.04.3-hotfix57
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$display_first = '' !== trim( (string) $first_name ) ? trim( (string) $first_name ) : 'apprenant(e)';
$expires_human = '' !== $expires_at ? mysql2date( 'l j F Y \à H\hi', $expires_at, true ) : '';

$purpose_label = array(
    'positioning' => 'votre test de positionnement',
    'diagnostic'  => 'votre évaluation diagnostique',
    'assessment'  => 'votre évaluation des acquis',
    'live'        => 'votre quiz',
);
$quiz_label = isset( $purpose_label[ $quiz->quiz_purpose ] ) ? $purpose_label[ $quiz->quiz_purpose ] : 'votre quiz';

$purpose_title = array(
    'positioning' => '📋 Relance — Test de positionnement',
    'diagnostic'  => '📋 Relance — Évaluation diagnostique',
    'assessment'  => '📋 Relance — Évaluation des acquis',
    'live'        => '📋 Relance — Quiz',
);
$title_h1 = isset( $purpose_title[ $quiz->quiz_purpose ] ) ? $purpose_title[ $quiz->quiz_purpose ] : '📋 Relance';
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( $organisation['name'] ); ?></title></head>
<body style="margin:0;padding:0;background:#eef2f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background:#eef2f6;padding:30px 10px;"><tr><td align="center">
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;width:100%;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 4px 14px rgba(0,0,0,0.04);">

<tr><td align="center" style="padding:42px 32px 8px 32px;">
<?php if ( ! empty( $organisation['logo_url'] ) ) : ?>
<img src="<?php echo esc_url( $organisation['logo_url'] ); ?>" alt="<?php echo esc_attr( $organisation['name'] ); ?>" width="140" style="display:block;width:140px;height:auto;margin:0 auto 18px auto;">
<?php endif; ?>
<h1 style="margin:0;font-size:26px;font-weight:700;color:#0f2c52;letter-spacing:-0.5px;">
<?php echo esc_html( $title_h1 ); ?>
</h1>
</td></tr>

<tr><td style="padding:24px 40px 12px 40px;font-size:15px;line-height:1.6;color:#1f2937;">

<p style="margin:0 0 6px 0;font-size:16px;">
Bonjour <strong><?php echo esc_html( $display_first ); ?></strong>,
</p>

<p style="margin:18px 0 0 0;">
Votre formateur vous contacte car <?php echo esc_html( $quiz_label ); ?> —
<strong><?php echo esc_html( $quiz->title ); ?></strong> — n'a pas encore été complété.
</p>

<p style="margin:14px 0 0 0;">
Cette étape est importante pour le bon déroulement de votre formation. Merci de le compléter dès que possible.
</p>

<?php if ( '' !== $expires_human ) : ?>
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:22px 0;">
<tr><td style="background:#fef6e4;border-left:3px solid #f0b45e;padding:14px 18px;border-radius:6px;font-size:14px;color:#5f4515;">
<strong>⏰ Date limite de réponse :</strong> <?php echo esc_html( $expires_human ); ?>
</td></tr>
</table>
<?php endif; ?>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin:28px 0 18px 0;">
<tr><td style="background:#d6a353;border-radius:999px;">
<a href="<?php echo esc_url( $passation_url ); ?>" style="display:inline-block;padding:14px 32px;color:#1f2937;text-decoration:none;font-weight:700;font-size:15px;">
Accéder maintenant
</a>
</td></tr></table>

<p style="margin:18px 0 0 0;font-size:13px;color:#6b7280;">
Si le bouton ne fonctionne pas, copiez ce lien :<br>
<a href="<?php echo esc_url( $passation_url ); ?>" style="color:#1e4777;word-break:break-all;font-size:12px;"><?php echo esc_html( $passation_url ); ?></a>
</p>

<?php if ( ! empty( $sender_name ) ) : ?>
<p style="margin:22px 0 0 0;">
Pour toute question, contactez votre formateur<?php if ( ! empty( $organisation['email'] ) ) : ?>
 à <a href="mailto:<?php echo esc_attr( $organisation['email'] ); ?>" style="color:#d6a353;text-decoration:none;font-weight:600;"><?php echo esc_html( $organisation['email'] ); ?></a><?php endif ?>.
</p>
<?php endif; ?>

<p style="margin:24px 0 0 0;">
Cordialement,<br>
<strong><?php echo ! empty( $sender_name ) ? esc_html( $sender_name ) . ' — ' : ''; ?>L'équipe <?php echo esc_html( $organisation['name'] ); ?></strong>
</p>

</td></tr>

<tr><td style="padding:24px 40px 32px 40px;border-top:1px solid #e5eaf2;text-align:center;font-size:12px;color:#6b7280;line-height:1.6;">
<?php
$footer_parts = array();
if ( ! empty( $organisation['phone'] ) )   { $footer_parts[] = esc_html( $organisation['phone'] ); }
if ( ! empty( $organisation['email'] ) )   { $footer_parts[] = '<a href="mailto:' . esc_attr( $organisation['email'] ) . '" style="color:#1e4777;">' . esc_html( $organisation['email'] ) . '</a>'; }
echo implode( ' &middot; ', $footer_parts );
?>
<?php if ( ! empty( $organisation['address'] ) ) : ?><br><?php echo esc_html( $organisation['address'] ); ?><?php endif; ?><br>
<span style="font-size:11px;color:#9ca3af;">Cet e-mail de relance a été envoyé dans le cadre de votre formation — trace Qualiopi conservée.</span>
</td></tr>

</table>
</td></tr></table>
</body></html>
