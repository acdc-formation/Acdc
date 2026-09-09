<?php
/**
 * Template autonome (sans header/footer du thème) pour le formulaire
 * public d'analyse du besoin.
 *
 * Servi via le filtre `template_include`.
 *
 * @package ACDC_OF_SAAS
 * @since   3.21.13
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$queried      = get_queried_object();
$page_title   = $queried ? (string) $queried->post_title : 'Analyse du besoin';
$page_content = $queried ? (string) $queried->post_content : '[acdc_nad_formulaire]';

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
/* Supprimer tout habillage thème */
.wp-site-blocks, .site, .site-header, .site-footer, .wp-block-template-part { display: none !important; }
.entry-content, .post-content, .page-content, .wp-block-post-content { padding: 0 !important; margin: 0 !important; }
</style>
</head>
<body class="acdc-nad-public-body">
<?php echo do_shortcode( $page_content ); ?>
<?php wp_footer(); ?>
</body>
</html>
