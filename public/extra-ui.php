<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Render Extra Attributes on frontend as a separate, independent block
add_action('wp_enqueue_scripts', function(){
    $js_file  = ECV_PATH . 'public/extra.js';
    $css_file = ECV_PATH . 'public/extra.css';
    $js_ver   = file_exists($js_file) ? filemtime($js_file) : '1.0';
    $css_ver  = file_exists($css_file) ? filemtime($css_file) : '1.0';
    wp_enqueue_script('ecv-extra-js', ECV_URL . 'public/extra.js', ['jquery'], $js_ver, true);
    wp_enqueue_style('ecv-extra-css', ECV_URL . 'public/extra.css', [], $css_ver);
});

function ecv_render_extra_attributes_block($post_id){
    $extra = ecv_get_extra_attrs_data($post_id);
    if (empty($extra)) return;
    include ECV_PATH . 'templates/extra-attributes.php';
}

// Shortcode and auto-injection after main variations UI
add_shortcode('ecv_extra_attributes', function($atts){
    global $post; if (!$post) return '';
    ob_start();
    ecv_render_extra_attributes_block($post->ID);
    return ob_get_clean();
});

add_action('woocommerce_single_product_summary', function(){
    global $post; if (!$post) return;
    ecv_render_extra_attributes_block($post->ID);
}, 22); // Priority 22: After main variations (21), before add to cart (30)
