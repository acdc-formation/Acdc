<?php
/**
 * Template autonome (sans header/footer du thème) pour la page de passation
 * publique d'un quiz async ou live.
 *
 * Servi via le filtre `template_include`. Charge directement les assets
 * publics du module quiz et exécute le contenu de la page (shortcode).
 *
 * @package ACDC_OF_SAAS
 * @since   3.21.03.1-hotfix3
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Récupère le post avant tout pour le titre et le contenu
$queried = get_queried_object();
$page_title = $queried ? (string) $queried->post_title : 'Quiz';
$page_content = $queried ? (string) $queried->post_content : '';

// Force l'en-tête HTTP pour signaler aux moteurs de ne pas indexer
header( 'X-Robots-Tag: noindex, nofollow', true );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $page_title ); ?> — <?php bloginfo( 'name' ); ?></title>

<?php
// Enregistre nos assets pour qu'ils soient enqueued par wp_head().
// On le fait ici (avant wp_head) pour être sûr qu'ils s'ajoutent dans la sortie.
wp_enqueue_style(
    'acdc-quizzes-public',
    ACDC_OF_SAAS_URL . 'assets/css/quizzes-public.css',
    array(),
    ACDC_OF_SAAS_VERSION
);
wp_enqueue_script(
    'acdc-quizzes-public',
    ACDC_OF_SAAS_URL . 'assets/js/quizzes-public.js',
    array(),
    ACDC_OF_SAAS_VERSION,
    true
);

// wp_head() émet les <link rel=stylesheet>, scripts dans le head, meta SEO etc.
wp_head();
?>

<style>
    /* Reset minimal pour éviter d'hériter de styles parasites */
    html, body { margin: 0; padding: 0; background: #eef2f6; overflow-x: hidden; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1f2937; min-height: 100vh; }
    /* Cache la barre admin WordPress qui pourrait s'incruster en haut si on est connecté */
    #wpadminbar { display: none !important; }
    html { margin-top: 0 !important; }
</style>
</head>
<body class="acdc-qz-public-body">

<?php
// On exécute le contenu de la page (qui contient le shortcode [acdc_qz_async_public])
echo do_shortcode( $page_content );

// wp_footer() pour les scripts en footer
wp_footer();
?>

</body>
</html>
