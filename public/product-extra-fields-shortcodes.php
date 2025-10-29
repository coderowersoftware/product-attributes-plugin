<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Main shortcode for product extra fields
 * Usage: [ecv_field key="banner_image"] or [ecv_field key="feature_text"]
 */
add_shortcode('ecv_field', 'ecv_render_product_extra_field_shortcode');

function ecv_render_product_extra_field_shortcode($atts) {
    global $post;
    
    // Parse attributes
    $atts = shortcode_atts(array(
        'key' => '',
        'class' => '',
        'style' => '',
        'default' => '', // Default value if field not found
    ), $atts, 'ecv_field');
    
    // Get product ID
    $product_id = null;
    if ($post && $post->post_type === 'product') {
        $product_id = $post->ID;
    } elseif (isset($_GET['product_id'])) {
        $product_id = intval($_GET['product_id']);
    }
    
    if (!$product_id || empty($atts['key'])) {
        return $atts['default'];
    }
    
    // Get the field
    $field = ecv_get_product_extra_field_by_key($product_id, $atts['key']);
    
    if (!$field || empty($field['field_value'])) {
        return $atts['default'];
    }
    
    // Render based on field type
    $output = '';
    $field_type = isset($field['field_type']) ? $field['field_type'] : 'text';
    $field_value = $field['field_value'];
    
    // Build class string
    $class_string = 'ecv-extra-field ecv-field-' . esc_attr($field_type);
    if (!empty($atts['class'])) {
        $class_string .= ' ' . esc_attr($atts['class']);
    }
    
    // Build style string
    $style_string = '';
    if (!empty($atts['style'])) {
        $style_string = ' style="' . esc_attr($atts['style']) . '"';
    }
    
    switch ($field_type) {
        case 'image':
            $output = sprintf(
                '<div class="%s"%s><img src="%s" alt="%s" class="ecv-extra-image" /></div>',
                $class_string,
                $style_string,
                esc_url($field_value),
                esc_attr($atts['key'])
            );
            break;
            
        case 'pdf':
            $output = sprintf(
                '<div class="%s"%s><a href="%s" class="ecv-extra-pdf-link" target="_blank" rel="noopener"><span class="ecv-pdf-icon">📄</span> <span class="ecv-pdf-text">View PDF</span></a></div>',
                $class_string,
                $style_string,
                esc_url($field_value)
            );
            break;
            
        case 'text':
        default:
            $output = sprintf(
                '<div class="%s"%s>%s</div>',
                $class_string,
                $style_string,
                wp_kses_post($field_value)
            );
            break;
    }
    
    return $output;
}

/**
 * Shortcode to check if a field exists
 * Usage: [ecv_field_exists key="banner_image"]Content to show[/ecv_field_exists]
 */
add_shortcode('ecv_field_exists', 'ecv_field_exists_shortcode');

function ecv_field_exists_shortcode($atts, $content = null) {
    global $post;
    
    $atts = shortcode_atts(array(
        'key' => '',
    ), $atts, 'ecv_field_exists');
    
    if (empty($atts['key'])) {
        return '';
    }
    
    $product_id = null;
    if ($post && $post->post_type === 'product') {
        $product_id = $post->ID;
    } elseif (isset($_GET['product_id'])) {
        $product_id = intval($_GET['product_id']);
    }
    
    if (!$product_id) {
        return '';
    }
    
    $field = ecv_get_product_extra_field_by_key($product_id, $atts['key']);
    
    if ($field && !empty($field['field_value'])) {
        return do_shortcode($content);
    }
    
    return '';
}

/**
 * Shortcode to display all extra fields for a product
 * Usage: [ecv_all_fields]
 */
add_shortcode('ecv_all_fields', 'ecv_display_all_product_extra_fields_shortcode');

function ecv_display_all_product_extra_fields_shortcode($atts) {
    global $post;
    
    $atts = shortcode_atts(array(
        'class' => 'ecv-all-extra-fields',
        'layout' => 'grid', // grid or list
    ), $atts, 'ecv_all_fields');
    
    $product_id = null;
    if ($post && $post->post_type === 'product') {
        $product_id = $post->ID;
    } elseif (isset($_GET['product_id'])) {
        $product_id = intval($_GET['product_id']);
    }
    
    if (!$product_id) {
        return '';
    }
    
    $fields = ecv_get_product_extra_fields($product_id);
    
    if (empty($fields)) {
        return '';
    }
    
    $layout_class = $atts['layout'] === 'grid' ? 'ecv-fields-grid' : 'ecv-fields-list';
    
    ob_start();
    ?>
    <div class="<?php echo esc_attr($atts['class']) . ' ' . esc_attr($layout_class); ?>">
        <?php foreach ($fields as $field) : ?>
            <?php 
            $field_key = isset($field['field_key']) ? $field['field_key'] : '';
            if (!empty($field_key)) {
                echo do_shortcode('[ecv_field key="' . esc_attr($field_key) . '"]');
            }
            ?>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Legacy shortcodes for backward compatibility
 * These allow direct access by naming pattern
 */
function ecv_register_legacy_extra_field_shortcodes() {
    // Register common patterns
    $patterns = array(
        'ecv_extra_image_1', 'ecv_extra_image_2', 'ecv_extra_image_3',
        'ecv_extra_text_1', 'ecv_extra_text_2', 'ecv_extra_text_3',
        'ecv_extra_pdf_1', 'ecv_extra_pdf_2', 'ecv_extra_pdf_3',
        'ecv_banner_image', 'ecv_feature_text', 'ecv_specs_pdf'
    );
    
    foreach ($patterns as $pattern) {
        add_shortcode($pattern, function($atts) use ($pattern) {
            // Remove ecv_ prefix to get the key
            $key = str_replace('ecv_', '', $pattern);
            return do_shortcode('[ecv_field key="' . $key . '"]');
        });
    }
}
add_action('init', 'ecv_register_legacy_extra_field_shortcodes');
