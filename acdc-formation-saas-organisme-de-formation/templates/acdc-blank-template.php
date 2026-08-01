<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ACDC 3.25.47 — Masquer la barre admin WordPress sur les pages ACDC
show_admin_bar( false );

global $post;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
    <style>#wpadminbar, #wp-admin-bar { display:none !important; } html { margin-top: 0 !important; }</style>
</head>
<body <?php body_class( 'acdc-blank-template-body' ); ?>>
<?php wp_body_open(); ?>
<div class="acdc-blank-template-page">
    <?php
    if ( $post instanceof WP_Post ) {
        echo apply_filters( 'the_content', $post->post_content );
    }
    ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
