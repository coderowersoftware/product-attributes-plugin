<?php
/*
Plugin Name: Exp Custom Variations
Description: Custom product attributes and dynamic pricing for WooCommerce-independent products.
Version: 1.5.2
Author: Chirag Bhardwaj

Changelog:
v1.5.2 - ADMIN PANEL IMPROVEMENTS: Configurable metabox visibility
         * Added config.php for centralized plugin configuration
         * Custom Variations metabox now hidden by default (products managed via CSV)
         * Easy toggle to show/hide metaboxes via configuration constants
         * Improved admin interface - cleaner product edit page
         * Configuration documentation included
v1.5.1 - ENHANCED CSV IMPORT: Added dynamic column format for extra fields
         * NEW CSV format: Extra:field_key|field_type (e.g., Extra:banner_image|image)
         * Explicit type declaration - no more guessing from file extensions
         * Cleaner, more organized CSV structure
         * Works alongside legacy formats for backward compatibility
         * Comprehensive documentation with examples and guides
         * Sample CSV files included for quick start
         * Automatic exclusion of standard WooCommerce columns
         * Visual verification tools (check-extra-fields.php)
         * Enhanced debug logging and import results display
v1.5.0 - PRODUCT EXTRA FIELDS: Added unlimited custom fields system with shortcode support
         * New "Product Extra Fields" metabox in admin for unlimited fields per product
         * Three field types: Image, Text, PDF with media uploader integration
         * Flexible shortcode system: [ecv_field key="field_key"] for Elementor
         * Smart CSV import/export with auto-detection (Extra_Image_1, Banner_Image patterns)
         * Conditional display shortcode: [ecv_field_exists]
         * Display all fields shortcode: [ecv_all_fields]
         * Full Elementor compatibility with responsive styling
         * Product-specific content management for banners, PDFs, and text
v1.4.1 - IMPORT/EXPORT EXTRA ATTRIBUTES: Added CSV import/export support for extra attributes
         * Export products with extra attributes and pricing to CSV
         * Import extra attributes with prices from CSV files
         * Updated all CSV templates with extra attributes examples
         * Format: AttrName[DisplayType:Option1:Price1,Option2:Price2]
         * Full round-trip support (export → edit → import)
         * Bulk management of extra attributes via spreadsheet
v1.4.0 - EXTRA ATTRIBUTES PRICING: Added price support for extra attributes
         * Added price input fields for each extra attribute option in admin panel
         * Extra attribute prices are displayed next to options on product page
         * Extra attribute prices automatically added to main product price
         * Prices included in cart, checkout, and order totals
         * Works with all display types (buttons, dropdown, radio)
         * Full integration with existing price calculation system
v1.3.2 - CURRENCY SYMBOL UPDATE: Changed currency symbol from British Pound to Indian Rupee
         * Added configurable currency symbol constant (ECV_CURRENCY_SYMBOL)
         * Updated frontend price display to use Indian Rupee (₹) symbol
         * Enhanced cross-group variation image assignment functionality
         * Made currency symbol configurable for future customization
v1.3.1 - ELEMENTOR PRO COMPATIBILITY FIX: Complete compatibility with Elementor Pro
         * Fixed CSS specificity conflicts with Elementor's styling system
         * Enhanced JavaScript initialization for Elementor widgets
         * Added proper script dependencies and load order
         * Maintained original styling while ensuring compatibility
         * Added mobile responsiveness for Elementor layouts
         * Fixed image updates for Elementor Pro gallery widgets
v1.3.0 - MAJOR ENHANCEMENT: Advanced Excel/CSV Import for Variation Groups
         * Added automatic variation group creation from CSV import
         * Enhanced import with attribute image support (swatches, icons)
         * Smart product grouping - multiple CSV rows create single product with all variations
         * Attribute images automatically downloaded and imported to media library
         * CSV format supports both combined (Size:Large|Color:Red) and separate column formats
         * Added comprehensive CSV format documentation and sample files
         * Enhanced admin panel integration - imported variations appear immediately
         * Improved error handling and import result reporting
v1.2.0 - Enhanced to support per-attribute display types (each attribute can have its own display type)
         Added dropdown selectors in admin for each attribute
         Updated frontend rendering to handle mixed display types
         Improved JavaScript to manage different input types per attribute
v1.1.0 - Added multiple display types (buttons, dropdown, radio) for variation options
         Added admin settings for display type and image visibility
         Enhanced CSS and JavaScript to support all display types
         Improved mobile responsiveness and accessibility
*/

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ECV_PATH', plugin_dir_path( __FILE__ ) );
define( 'ECV_URL', plugin_dir_url( __FILE__ ) );
define( 'ECV_CURRENCY_SYMBOL', '₹' ); // Currency symbol used in price display

date_default_timezone_set('UTC');

// Load configuration
if (file_exists(ECV_PATH . 'config.php')) {
    require_once ECV_PATH . 'config.php';
}

// Load includes
require_once ECV_PATH . 'includes/data-handler.php';
require_once ECV_PATH . 'includes/helpers.php';
require_once ECV_PATH . 'includes/export-handler.php';
require_once ECV_PATH . 'includes/import-handler.php';

// Admin
if ( is_admin() ) {
    require_once ECV_PATH . 'admin/admin-ui.php';
    require_once ECV_PATH . 'admin/import-export.php';
}
// Public
require_once ECV_PATH . 'public/frontend-ui.php';
require_once ECV_PATH . 'public/extra-ui.php';
require_once ECV_PATH . 'public/product-extra-fields-shortcodes.php';

// Activation hook (for custom table if needed)
// register_activation_hook( __FILE__, 'ecv_activate_plugin' );
// function ecv_activate_plugin() {
//     // Table creation logic if needed
// }

// Add custom variation data to cart item
add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id, $variation_id) {
    // Check both POST and REQUEST data (for AJAX)
    $combo_raw = '';
    if (!empty($_POST['ecv_selected_combination'])) {
        $combo_raw = stripslashes($_POST['ecv_selected_combination']);
        error_log('ECV: Found variant data in POST: ' . substr($combo_raw, 0, 100) . '...');
    } elseif (!empty($_REQUEST['ecv_selected_combination'])) {
        $combo_raw = stripslashes($_REQUEST['ecv_selected_combination']);
        error_log('ECV: Found variant data in REQUEST: ' . substr($combo_raw, 0, 100) . '...');
    }
    
    if (!empty($combo_raw)) {
        $combo = json_decode(urldecode($combo_raw), true);
        if (!$combo) { $combo = json_decode($combo_raw, true); }
        if ($combo && is_array($combo)) {
            $cart_item_data['ecv_selected_combination'] = $combo;
        }
    }

    // Handle Extra Attributes (separate, optional)
    $extra_raw = '';
    if (!empty($_POST['ecv_extra_attributes'])) {
        $extra_raw = stripslashes($_POST['ecv_extra_attributes']);
    } elseif (!empty($_REQUEST['ecv_extra_attributes'])) {
        $extra_raw = stripslashes($_REQUEST['ecv_extra_attributes']);
    }
    if (!empty($extra_raw)) {
        $extra = json_decode(urldecode($extra_raw), true);
        if (!$extra) { $extra = json_decode($extra_raw, true); }
        if (is_array($extra)) {
            $cart_item_data['ecv_extra_attributes'] = $extra;
        }
    }
    
    return $cart_item_data;
}, 10, 3);

// Handle AJAX add to cart for variant data
add_action('wp_ajax_woocommerce_add_to_cart', 'ecv_handle_ajax_add_to_cart', 5);
add_action('wp_ajax_nopriv_woocommerce_add_to_cart', 'ecv_handle_ajax_add_to_cart', 5);

function ecv_handle_ajax_add_to_cart() {
    // Ensure variant data is available in $_POST for the add_cart_item_data filter
    if (!empty($_REQUEST['ecv_selected_combination']) && empty($_POST['ecv_selected_combination'])) {
        $_POST['ecv_selected_combination'] = $_REQUEST['ecv_selected_combination'];
    }
    // Also forward extra attributes if present
    if (!empty($_REQUEST['ecv_extra_attributes']) && empty($_POST['ecv_extra_attributes'])) {
        $_POST['ecv_extra_attributes'] = $_REQUEST['ecv_extra_attributes'];
    }
}

// Hook into WooCommerce AJAX add to cart specifically
add_action('woocommerce_ajax_added_to_cart', function($product_id) {
    // Log successful AJAX add to cart
    if (!empty($_POST['ecv_selected_combination'])) {
        error_log('ECV: Successfully added product with variant data via AJAX: ' . $_POST['ecv_selected_combination']);
    }
});

// Additional hook for themes that might use different AJAX handlers
add_filter('woocommerce_ajax_add_to_cart_validation', function($passed, $product_id, $quantity) {
    // Ensure we have variant data when required
    if (!empty($_POST['ecv_selected_combination'])) {
        error_log('ECV: AJAX validation with variant data for product ' . $product_id);
    }
    return $passed;
}, 10, 3);

// Set custom price in cart
add_action('woocommerce_before_calculate_totals', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    
    // Prevent infinite loop
    static $done = false;
    if ($done) return;
    $done = true;
    
    try {
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (!empty($cart_item['ecv_selected_combination']) && 
                is_array($cart_item['ecv_selected_combination']) && 
                isset($cart_item['data'])) {
                
                $combo = $cart_item['ecv_selected_combination'];
                $price = null;
                
                // Determine which price to use
                if (!empty($combo['sale_price']) && is_numeric($combo['sale_price']) && 
                    !empty($combo['price']) && is_numeric($combo['price']) && 
                    floatval($combo['sale_price']) < floatval($combo['price'])) {
                    $price = floatval($combo['sale_price']);
                } elseif (!empty($combo['price']) && is_numeric($combo['price'])) {
                    $price = floatval($combo['price']);
                }
                
                // Add extra attributes prices if any
                $extra_price = 0;
                if (!empty($cart_item['ecv_extra_attributes']) && is_array($cart_item['ecv_extra_attributes'])) {
                    foreach ($cart_item['ecv_extra_attributes'] as $extra) {
                        if (!empty($extra['price']) && is_numeric($extra['price'])) {
                            $extra_price += floatval($extra['price']);
                        }
                    }
                }
                
                // Set the price if valid
                if ($price && $price > 0) {
                    $final_price = $price + $extra_price;
                    $cart_item['data']->set_price($final_price);
                    // Force WooCommerce to recalculate totals
                    $cart_item['data']->set_regular_price($final_price);
                    if (!empty($combo['sale_price']) && floatval($combo['sale_price']) < floatval($combo['price'])) {
                        $cart_item['data']->set_sale_price($final_price);
                    }
                    error_log('ECV: Set price for cart item ' . $cart_item_key . ': ' . $final_price . ' (base: ' . $price . ', extra: ' . $extra_price . ')');
                }
            }
        }
    } catch (Exception $e) {
        error_log('ECV: Error in cart price calculation: ' . $e->getMessage());
    }
    
    $done = false; // Reset for next run
});

// Display custom data in cart/checkout
add_filter('woocommerce_get_item_data', function($item_data, $cart_item) {
    if (!is_array($item_data)) { $item_data = array(); }

    // Core combination attributes
    if (!empty($cart_item['ecv_selected_combination']) && is_array($cart_item['ecv_selected_combination'])) {
        $combo = $cart_item['ecv_selected_combination'];
        try {
            if (!empty($combo['attributes']) && is_array($combo['attributes'])) {
                foreach ($combo['attributes'] as $attr) {
                    if (is_array($attr) && isset($attr['attribute']) && isset($attr['value'])) {
                        $item_data[] = array(
                            'name' => esc_html($attr['attribute']),
                            'value' => esc_html($attr['value']),
                            'display' => esc_html($attr['value']),
                        );
                    }
                }
            }
            if (!is_admin() && !wp_doing_ajax() && !empty($combo['main_image_url'])) {
                $item_data[] = array(
                    'name' => __('Selected Variant', 'exp-custom-variations'),
                    'value' => '',
                    'display' => '<img src="' . esc_url($combo['main_image_url']) . '" style="max-width:80px;height:auto;border:1px solid #ddd;border-radius:4px;vertical-align:middle;" alt="Selected Variant" />',
                );
            }
            if (!empty($combo['sku'])) {
                $item_data[] = array(
                    'name' => __('Variant SKU', 'exp-custom-variations'),
                    'value' => esc_html($combo['sku']),
                    'display' => esc_html($combo['sku']),
                );
            }
            if (!is_admin() && !empty($combo['price'])) {
                $price = floatval($combo['price']);
                $sale_price = !empty($combo['sale_price']) ? floatval($combo['sale_price']) : null;
                if ($price > 0) {
                    $price_display = wc_price($price);
                    if ($sale_price && $sale_price < $price) {
                        $price_display = '<del>' . wc_price($price) . '</del> ' . wc_price($sale_price);
                    }
                    $item_data[] = array(
                        'name' => __('Variant Price', 'exp-custom-variations'),
                        'value' => strip_tags($price_display),
                        'display' => $price_display,
                    );
                }
            }
        } catch (Exception $e) { error_log('ECV: Error displaying cart item data: ' . $e->getMessage()); }
    }

    // Extra attributes (separate)
    if (!empty($cart_item['ecv_extra_attributes']) && is_array($cart_item['ecv_extra_attributes'])) {
        foreach ($cart_item['ecv_extra_attributes'] as $extra) {
            if (!empty($extra['attribute']) && isset($extra['value'])) {
                $display_value = esc_html($extra['value']);
                // Add price to display if available
                if (!empty($extra['price']) && floatval($extra['price']) > 0) {
                    $display_value .= ' (+' . wc_price($extra['price']) . ')';
                }
                $item_data[] = array(
                    'name' => esc_html($extra['attribute']),
                    'value' => esc_html($extra['value']),
                    'display' => $display_value,
                );
            }
        }
    }

    return $item_data;
}, 20, 2);

// Save custom data to order item meta
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
    if (!empty($values['ecv_selected_combination'])) {
        $combo = $values['ecv_selected_combination'];
        $item->add_meta_data('_ecv_selected_combination', $combo);
        if (!empty($combo['sku'])) { $item->add_meta_data('_ecv_variant_sku', $combo['sku']); }
        if (!empty($combo['price'])) { $item->add_meta_data('_ecv_variant_price', $combo['price']); }
        if (!empty($combo['sale_price'])) { $item->add_meta_data('_ecv_variant_sale_price', $combo['sale_price']); }
        if (!empty($combo['main_image_url'])) { $item->add_meta_data('_ecv_variant_image', $combo['main_image_url']); }
        if (!empty($combo['attributes'])) {
            foreach ($combo['attributes'] as $index => $attr) {
                $item->add_meta_data('_ecv_variant_' . sanitize_key($attr['attribute']), $attr['value']);
            }
            $item->add_meta_data('_ecv_variant_attributes', wp_json_encode($combo['attributes']));
        }
        $item->add_meta_data(__('Custom Variant Details', 'exp-custom-variations'), ecv_format_variant_summary($combo), true);
    }

    // Extra attributes (separate)
    if (!empty($values['ecv_extra_attributes']) && is_array($values['ecv_extra_attributes'])) {
        $item->add_meta_data('_ecv_extra_attributes', $values['ecv_extra_attributes']);
        // Also flatten as visible fields with prices
        $total_extra_price = 0;
        foreach ($values['ecv_extra_attributes'] as $extra) {
            if (!empty($extra['attribute']) && isset($extra['value'])) {
                $display_value = $extra['value'];
                // Add price to display if available
                if (!empty($extra['price']) && floatval($extra['price']) > 0) {
                    $display_value .= ' (+' . wc_price($extra['price']) . ')';
                    $total_extra_price += floatval($extra['price']);
                }
                $item->add_meta_data($extra['attribute'], $display_value, true);
            }
        }
        // Save total extra price for reference
        if ($total_extra_price > 0) {
            $item->add_meta_data('_ecv_extra_attributes_price', $total_extra_price);
        }
    }
}, 10, 4);

// Helper function to format variant summary
function ecv_format_variant_summary($combo) {
    $summary = '';
    
    if (!empty($combo['attributes'])) {
        $attrs = array();
        foreach ($combo['attributes'] as $attr) {
            $attrs[] = $attr['attribute'] . ': ' . $attr['value'];
        }
        $summary .= implode(', ', $attrs);
    }
    
    if (!empty($combo['sku'])) {
        $summary .= $summary ? ' | SKU: ' . $combo['sku'] : 'SKU: ' . $combo['sku'];
    }
    
    if (!empty($combo['price'])) {
        $price_text = 'Price: ' . wc_price($combo['price']);
        if (!empty($combo['sale_price']) && $combo['sale_price'] < $combo['price']) {
            $price_text .= ' (Sale: ' . wc_price($combo['sale_price']) . ')';
        }
        $summary .= $summary ? ' | ' . $price_text : $price_text;
    }
    
    return $summary ?: __('Custom variant selected', 'exp-custom-variations');
}

// 1. Cart & Checkout (Classic) - Enhanced thumbnail handling
add_filter('woocommerce_cart_item_thumbnail', function($image, $cart_item, $cart_item_key) {
    try {
        if (!empty($cart_item['ecv_selected_combination']) && 
            is_array($cart_item['ecv_selected_combination']) &&
            !empty($cart_item['ecv_selected_combination']['main_image_url'])) {
            $variant_image = $cart_item['ecv_selected_combination']['main_image_url'];
            if (filter_var($variant_image, FILTER_VALIDATE_URL)) {
                // More specific image attributes for better cart display
                $image_html = '<img src="' . esc_url($variant_image) . '" ';
                $image_html .= 'alt="Variant Image" ';
                $image_html .= 'class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail" ';
                $image_html .= 'width="64" height="64" ';
                $image_html .= 'style="width: 64px; height: 64px; object-fit: cover; border-radius: 4px;" />';
                return $image_html;
            }
        }
    } catch (Exception $e) {
        error_log('ECV: Error in cart thumbnail: ' . $e->getMessage());
    }
    return $image;
}, 99, 3); // Higher priority

add_filter('woocommerce_checkout_item_thumbnail', function($image, $cart_item, $cart_item_key) {
    try {
        if (!empty($cart_item['ecv_selected_combination']) && 
            is_array($cart_item['ecv_selected_combination']) &&
            !empty($cart_item['ecv_selected_combination']['main_image_url'])) {
            $variant_image = $cart_item['ecv_selected_combination']['main_image_url'];
            if (filter_var($variant_image, FILTER_VALIDATE_URL)) {
                // Enhanced image display for checkout
                $image_html = '<img src="' . esc_url($variant_image) . '" ';
                $image_html .= 'alt="Variant Image" ';
                $image_html .= 'class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail" ';
                $image_html .= 'width="64" height="64" ';
                $image_html .= 'style="width: 64px; height: 64px; object-fit: cover; border-radius: 4px;" />';
                return $image_html;
            }
        }
    } catch (Exception $e) {
        error_log('ECV: Error in checkout thumbnail: ' . $e->getMessage());
    }
    return $image;
}, 99, 3); // Higher priority

// 2. Order History (Customer)
add_filter('woocommerce_order_item_thumbnail', function($image, $item) {
    $variant_image = '';
    if (is_a($item, 'WC_Order_Item_Product')) {
        $meta = $item->get_meta('_ecv_selected_combination', true);
        if (!empty($meta['main_image_url'])) {
            $variant_image = $meta['main_image_url'];
        }
    }
    if ($variant_image) {
        return '<img src="' . esc_url($variant_image) . '" alt="" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail" />';
    }
    return $image;
}, 10, 2);

// 3. Admin Order Details
add_filter('woocommerce_admin_order_item_thumbnail', function($image, $item) {
    $variant_image = '';
    if (is_a($item, 'WC_Order_Item_Product')) {
        $meta = $item->get_meta('_ecv_selected_combination', true);
        if (!empty($meta['main_image_url'])) {
            $variant_image = $meta['main_image_url'];
        }
    }
    if ($variant_image) {
        return '<img src="' . esc_url($variant_image) . '" alt="" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail" />';
    }
    return $image;
}, 10, 2);

// Force cart/checkout to use selected variant image as product image
add_filter('woocommerce_cart_item_product', function($product, $cart_item, $cart_item_key) {
    if (!empty($cart_item['ecv_selected_combination']['main_image_url'])) {
        $product = clone $product;
        $image_url = $cart_item['ecv_selected_combination']['main_image_url'];
        
        // Override product image methods
        $product->get_image = function($size = 'woocommerce_thumbnail', $attr = array(), $placeholder = true) use ($image_url) {
            $image_html = '<img src="' . esc_url($image_url) . '" ';
            $image_html .= 'alt="Variant Image" ';
            $image_html .= 'class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail" ';
            $image_html .= 'width="64" height="64" ';
            $image_html .= 'style="width: 64px; height: 64px; object-fit: cover; border-radius: 4px;" />';
            return $image_html;
        };
        $product->get_thumbnail_id = function() { return 0; };
        
        // For themes that might use different methods
        add_filter('wp_get_attachment_image_src', function($image, $attachment_id, $size) use ($image_url) {
            if ($attachment_id === 0 || $attachment_id === false) {
                return array($image_url, 64, 64, false);
            }
            return $image;
        }, 10, 3);
    }
    return $product;
}, 10, 3);

// For WooCommerce Blocks: show the selected main image URL as the product image
add_filter('woocommerce_cart_item_data', function($cart_data, $cart_item) {
    if (!empty($cart_item['ecv_selected_combination']['main_image_url'])) {
        // Remove any previous image meta
        $cart_data = array_filter($cart_data, function($row) {
            return $row['key'] !== __('Selected Variant Image', 'exp-custom-variations');
        });
        $cart_data[] = array(
            'key'   => __('Selected Variant Image', 'exp-custom-variations'),
            'value' => $cart_item['ecv_selected_combination']['main_image_url'],
        );
    }
    return $cart_data;
}, 20, 2);

// Enqueue custom JS for WooCommerce Blocks to render variant image as an actual image
add_action('enqueue_block_assets', function() {
    if (function_exists('is_cart') && (is_cart() || is_checkout())) {
        wp_enqueue_script(
            'ecv-blocks-variant-image',
            ECV_URL . 'public/blocks-variant-image.js',
            array('wp-element', 'wc-blocks-registry'),
            '1.0',
            true
        );
    }
});

// Enqueue cart image fix JavaScript on cart and checkout pages
add_action('wp_enqueue_scripts', function() {
    if (function_exists('is_cart') && function_exists('is_checkout') && 
        (is_cart() || is_checkout() || is_account_page())) {
        wp_enqueue_script(
            'ecv-cart-image-fix',
            ECV_URL . 'public/cart-image-fix.js',
            array('jquery'),
            '1.0',
            true
        );
    }
});

// Expose selected_variant_image to WooCommerce Blocks cart/checkout JS
add_filter('woocommerce_blocks_cart_item_data', function($cart_data, $cart_item) {
    if (!empty($cart_item['ecv_selected_combination']['main_image_url'])) {
        $cart_data['selected_variant_image'] = $cart_item['ecv_selected_combination']['main_image_url'];
    }
    return $cart_data;
}, 10, 2);

// Display variant data in order details (customer account)
add_filter('woocommerce_order_item_meta_start', function($item_id, $item, $order, $plain_text) {
    if (is_a($item, 'WC_Order_Item_Product')) {
        $combo = $item->get_meta('_ecv_selected_combination', true);
        if (!empty($combo)) {
            echo '<div class="ecv-order-item-details" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px;">';
            
            if (!empty($combo['attributes'])) {
                echo '<h4 style="margin: 0 0 8px 0; font-size: 14px;">' . __('Selected Options:', 'exp-custom-variations') . '</h4>';
                foreach ($combo['attributes'] as $attr) {
                    echo '<div style="margin-bottom: 4px;"><strong>' . esc_html($attr['attribute']) . ':</strong> ' . esc_html($attr['value']) . '</div>';
                }
            }
            
            if (!empty($combo['main_image_url'])) {
                echo '<div style="margin-top: 8px;"><strong>' . __('Selected Variant Image:', 'exp-custom-variations') . '</strong><br/>';
                echo '<img src="' . esc_url($combo['main_image_url']) . '" style="max-width: 120px; height: auto; border: 1px solid #ddd; border-radius: 4px; margin-top: 4px;" alt="Selected Variant" /></div>';
            }
            
            if (!empty($combo['sku'])) {
                echo '<div style="margin-top: 4px;"><strong>' . __('Variant SKU:', 'exp-custom-variations') . '</strong> ' . esc_html($combo['sku']) . '</div>';
            }
            
            echo '</div>';
        }
    }
}, 10, 4);

// Display variant data in admin order details
add_action('woocommerce_before_order_item_line_item_html', function($item, $item_key, $order) {
    if (is_admin() && is_a($item, 'WC_Order_Item_Product')) {
        $combo = $item->get_meta('_ecv_selected_combination', true);
        if (!empty($combo)) {
            echo '<tr class="ecv-admin-order-details"><td colspan="6" style="padding: 10px; background: #f9f9f9; border-left: 4px solid #2271b1;">';
            
            echo '<strong style="color: #2271b1;">' . __('Custom Variant Details:', 'exp-custom-variations') . '</strong><br/>';
            
            if (!empty($combo['attributes'])) {
                echo '<div style="margin: 8px 0;">';
                foreach ($combo['attributes'] as $attr) {
                    echo '<span style="display: inline-block; margin-right: 15px; background: #fff; padding: 2px 6px; border-radius: 3px; border: 1px solid #ddd;">';
                    echo '<strong>' . esc_html($attr['attribute']) . ':</strong> ' . esc_html($attr['value']);
                    echo '</span>';
                }
                echo '</div>';
            }
            
            if (!empty($combo['main_image_url'])) {
                echo '<div style="margin: 8px 0;">';
                echo '<strong>' . __('Variant Image:', 'exp-custom-variations') . '</strong><br/>';
                echo '<img src="' . esc_url($combo['main_image_url']) . '" style="max-width: 100px; height: auto; border: 1px solid #ddd; border-radius: 4px; margin-top: 4px;" alt="Variant" />';
                echo '</div>';
            }
            
            if (!empty($combo['sku'])) {
                echo '<div style="margin: 4px 0;"><strong>' . __('Variant SKU:', 'exp-custom-variations') . '</strong> <code>' . esc_html($combo['sku']) . '</code></div>';
            }
            
            if (!empty($combo['price'])) {
                $price_info = '<strong>' . __('Variant Price:', 'exp-custom-variations') . '</strong> ' . wc_price($combo['price']);
                if (!empty($combo['sale_price']) && $combo['sale_price'] < $combo['price']) {
                    $price_info = '<strong>' . __('Variant Price:', 'exp-custom-variations') . '</strong> <del>' . wc_price($combo['price']) . '</del> ' . wc_price($combo['sale_price']) . ' <span style="color: #d63638;">(Sale)</span>';
                }
                echo '<div style="margin: 4px 0;">' . $price_info . '</div>';
            }
            
            echo '</td></tr>';
        }
    }
}, 10, 3);

// Display variant data in email templates
add_action('woocommerce_order_item_meta_start', function($item_id, $item, $order, $plain_text) {
    if (is_a($item, 'WC_Order_Item_Product')) {
        $combo = $item->get_meta('_ecv_selected_combination', true);
        if (!empty($combo)) {
            if ($plain_text) {
                // Plain text email format
                echo "\n" . __('Selected Options:', 'exp-custom-variations') . "\n";
                if (!empty($combo['attributes'])) {
                    foreach ($combo['attributes'] as $attr) {
                        echo "• " . $attr['attribute'] . ": " . $attr['value'] . "\n";
                    }
                }
                if (!empty($combo['sku'])) {
                    echo "• " . __('Variant SKU:', 'exp-custom-variations') . ": " . $combo['sku'] . "\n";
                }
                if (!empty($combo['main_image_url'])) {
                    echo "• " . __('Variant Image:', 'exp-custom-variations') . ": " . $combo['main_image_url'] . "\n";
                }
            } else {
                // Use plain text format for HTML emails too (removes HTML styling)
                echo "\n" . __('Selected Options:', 'exp-custom-variations') . "\n";
                if (!empty($combo['attributes'])) {
                    foreach ($combo['attributes'] as $attr) {
                        echo "• " . $attr['attribute'] . ": " . $attr['value'] . "\n";
                    }
                }
                if (!empty($combo['sku'])) {
                    echo "• " . __('Variant SKU:', 'exp-custom-variations') . ": " . $combo['sku'] . "\n";
                }
                if (!empty($combo['main_image_url'])) {
                    echo "• " . __('Variant Image URL:', 'exp-custom-variations') . ": " . $combo['main_image_url'] . "\n";
                }
            }
        }
    }
}, 15, 4);

// Add custom column to admin orders list
add_filter('manage_edit-shop_order_columns', function($columns) {
    $columns['ecv_variants'] = __('Custom Variants', 'exp-custom-variations');
    return $columns;
});

// Populate the custom column
add_action('manage_shop_order_posts_custom_column', function($column, $post_id) {
    if ($column === 'ecv_variants') {
        $order = wc_get_order($post_id);
        if ($order) {
            $has_variants = false;
            foreach ($order->get_items() as $item) {
                $combo = $item->get_meta('_ecv_selected_combination', true);
                if (!empty($combo)) {
                    $has_variants = true;
                    echo '<div style="margin-bottom: 8px; padding: 4px; background: #f0f8ff; border-radius: 3px; border-left: 3px solid #0073aa;">';
                    echo '<strong>' . esc_html($item->get_name()) . '</strong><br/>';
                    
                    if (!empty($combo['attributes'])) {
                        foreach ($combo['attributes'] as $attr) {
                            echo '<small>' . esc_html($attr['attribute']) . ': ' . esc_html($attr['value']) . '</small><br/>';
                        }
                    }
                    
                    if (!empty($combo['main_image_url'])) {
                        echo '<img src="' . esc_url($combo['main_image_url']) . '" style="max-width: 40px; height: auto; margin-top: 2px; border: 1px solid #ddd; border-radius: 2px;" />';
                    }
                    
                    echo '</div>';
                }
            }
            if (!$has_variants) {
                echo '<span style="color: #999; font-style: italic;">' . __('No custom variants', 'exp-custom-variations') . '</span>';
            }
        }
    }
}, 10, 2);

// Make the column sortable (optional)
add_filter('manage_edit-shop_order_sortable_columns', function($columns) {
    $columns['ecv_variants'] = 'ecv_variants';
    return $columns;
});
