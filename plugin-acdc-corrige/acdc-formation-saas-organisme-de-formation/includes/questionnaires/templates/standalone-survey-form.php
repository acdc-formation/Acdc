<?php
/**
 * Template autonome (sans header/footer du thème) pour le formulaire
 * public d'enquête de satisfaction.
 *
 * Servi via le filtre `template_include` uniquement pour les sessions
 * de type survey (mid_survey, hot_survey, cold_survey, trainer_survey,
 * company_survey, funder_survey).
 *
 * @package ACDC_OF_SAAS
 * @since   3.21.19
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$queried      = get_queried_object();
$page_title   = $queried ? (string) $queried->post_title : 'Enquête de satisfaction';
$page_content = $queried ? (string) $queried->post_content : '[acdc_questionnaire_session]';

header( 'X-Robots-Tag: noindex, nofollow', true );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $page_title ); ?> — <?php bloginfo( 'name' ); ?></title>
<?php wp_head(); ?>
<style>
html, body { margin: 0 !important; padding: 0 !important; background: #f5f6fa !important; min-height: 100vh; }
body { font-family: 'Helvetica Neue', Arial, sans-serif !important; color: #24324a !important; }
#wpadminbar { display: none !important; }
html { margin-top: 0 !important; }
.wp-site-blocks, .site, .site-header, .site-footer, .wp-block-template-part { display: none !important; }
.entry-content, .post-content, .page-content, .wp-block-post-content { padding: 0 !important; margin: 0 !important; }
/* Reset portal shell qui s'activerait si le CSS admin est chargé */
.acdc-portal-shell { all: unset !important; display: block !important; }
</style>
<?php
/* ACDC 3.21.19-hotfix1 : forcer le chargement du CSS surveys sur la page standalone. */
if ( defined( 'ACDC_OF_SAAS_URL' ) ) {
    echo '<link rel="stylesheet" href="' . esc_url( ACDC_OF_SAAS_URL . 'assets/css/surveys.css?v=' . ACDC_OF_SAAS_VERSION ) . '" data-no-optimize="1">';
}
?>
</head>
<body class="acdc-survey-public-body">
<?php echo do_shortcode( $page_content ); ?>
<?php wp_footer(); ?>
</body>
</html>
