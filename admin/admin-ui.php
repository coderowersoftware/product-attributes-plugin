<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Add meta box
add_action('add_meta_boxes', function() {
    add_meta_box(
        'ecv_variations_box',
        __('Custom Variations', 'exp-custom-variations'),
        'ecv_render_variations_metabox',
        'product',
        'normal',
        'high'
    );
});

function ecv_render_variations_metabox($post) {
    wp_nonce_field('ecv_save_variations', 'ecv_variations_nonce');
    $data = ecv_get_variations_data($post->ID);
    $combinations = ecv_get_combinations_data($post->ID);
    $rules = ecv_get_conditional_rules($post->ID);
    $grouped_data = ecv_get_grouped_data($post->ID); // Keep for backward compatibility
    $cross_group_data = ecv_get_cross_group_data($post->ID);
    $group_images_data = get_post_meta($post->ID, '_ecv_group_images_data', true) ?: [];
    
    // Check if this product was imported from CSV (has combination format data)
    $is_csv_imported = !empty($cross_group_data) && !isset($cross_group_data['groupsDefinition']);
    
    include ECV_PATH . 'admin/metabox-template.php';
}


// Enqueue admin scripts with enhanced compatibility
add_action('admin_enqueue_scripts', function($hook) {
    global $post, $pagenow;
    
    // For product edit pages
    if ($hook == 'post.php' || $hook == 'post-new.php') {
        if (isset($post) && $post->post_type === 'product') {
            // Enhanced dependency handling
            $dependencies = ['jquery', 'wp-util'];
            
            // Add Elementor dependencies if available
            if (wp_script_is('elementor-admin', 'registered')) {
                $dependencies[] = 'elementor-admin';
            }
            
            // Enqueue with version based on file modification time for cache busting
            $js_file = ECV_PATH . 'admin/admin.js';
            $css_file = ECV_PATH . 'admin/admin.css';
            $js_version = file_exists($js_file) ? filemtime($js_file) : '1.0';
            $css_version = file_exists($css_file) ? filemtime($css_file) : '1.0';
            
            wp_enqueue_script('ecv-admin-js', ECV_URL . 'admin/admin.js', $dependencies, $js_version, true);
            wp_enqueue_style('ecv-admin-css', ECV_URL . 'admin/admin.css', [], $css_version);
            
            // Localize script with debug info
            wp_localize_script('ecv-admin-js', 'ecvAdminConfig', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ecv_admin_nonce'),
                'debug' => defined('WP_DEBUG') && WP_DEBUG,
                'elementorActive' => defined('ELEMENTOR_VERSION'),
                'version' => '1.3.2'
            ]);
            
            wp_enqueue_media();
            
            // Debug logging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('ECV Admin: Scripts enqueued for product edit page. Hook: ' . $hook . ', Post ID: ' . ($post->ID ?? 'unknown'));
            }
        }
    }
    
    // For orders list and order edit pages
    if (($hook == 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'shop_order') ||
        ($hook == 'post.php' && isset($post) && $post->post_type === 'shop_order') ||
        ($pagenow === 'admin.php' && isset($_GET['page']) && strpos($_GET['page'], 'wc-orders') !== false)) {
        wp_enqueue_style('ecv-admin-css', ECV_URL . 'admin/admin.css');
    }
});

// Save meta - Use high priority to run AFTER WooCommerce
add_action('save_post_product', function($post_id, $post, $update) {
    // CRITICAL: Early return if this is not our form submission
    // This prevents WooCommerce updates from clearing variation data
    if (!isset($_POST['ecv_variations_nonce'])) {
        return; // Not our form, don't touch variation data
    }
    
    if (!wp_verify_nonce($_POST['ecv_variations_nonce'], 'ecv_save_variations')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    
    // Save show images setting
    $show_images = isset($_POST['ecv_show_images']) ? 'yes' : 'no';
    update_post_meta($post_id, '_ecv_show_images', $show_images);
    
    // Save variation mode
    $variation_mode = isset($_POST['ecv_variation_mode']) ? sanitize_text_field($_POST['ecv_variation_mode']) : 'traditional';
    update_post_meta($post_id, '_ecv_variation_mode', $variation_mode);
    
    // Debug: Log all POST data keys to see what's being submitted
    error_log('ECV Debug: All POST keys: ' . implode(', ', array_keys($_POST)));
    error_log('ECV Debug: Looking for ecv_group_images_data in POST...');
    
    // Save group images data
    if (isset($_POST['ecv_group_images_data'])) {
        $group_images_raw = $_POST['ecv_group_images_data'];
        error_log('ECV Debug: ✓ Found ecv_group_images_data in POST');
        error_log('ECV Debug: Raw group images data received: ' . $group_images_raw);
        error_log('ECV Debug: Raw data length: ' . strlen($group_images_raw));
        error_log('ECV Debug: Raw data type: ' . gettype($group_images_raw));
        
        if (empty($group_images_raw)) {
            error_log('ECV Debug: ⚠️ Group images data is empty string');
            update_post_meta($post_id, '_ecv_group_images_data', []);
        } else {
            $group_images_data = json_decode(stripslashes($group_images_raw), true);
            error_log('ECV Debug: JSON decode result: ' . (($group_images_data === null) ? 'NULL (JSON error)' : 'SUCCESS'));
            if ($group_images_data === null) {
                error_log('ECV Debug: JSON decode error: ' . json_last_error_msg());
            }
            error_log('ECV Debug: Decoded group images data: ' . print_r($group_images_data, true));
            
            if ($group_images_data && is_array($group_images_data)) {
                // Sanitize image URLs
                $sanitized_images = [];
                foreach ($group_images_data as $key => $url) {
                    $sanitized_key = sanitize_text_field($key);
                    $sanitized_url = esc_url_raw($url);
                    if (!empty($sanitized_url)) {
                        $sanitized_images[$sanitized_key] = $sanitized_url;
                    }
                }
                error_log('ECV Debug: ✓ Sanitized group images (' . count($sanitized_images) . ' items): ' . print_r($sanitized_images, true));
                update_post_meta($post_id, '_ecv_group_images_data', $sanitized_images);
                
                // Verify it was saved
                $saved_data = get_post_meta($post_id, '_ecv_group_images_data', true);
                error_log('ECV Debug: ✓ Verified saved data: ' . print_r($saved_data, true));
                
                // CRITICAL: If we're in cross-group mode, regenerate frontend data with new group images
                if ($variation_mode === 'cross_group') {
                    $cross_group_data = ecv_get_cross_group_data($post_id);
                    if (!empty($cross_group_data)) {
                        error_log('ECV Debug: 🔄 Regenerating frontend data because group images changed');
                        $converted = ecv_convert_cross_group_to_traditional_format($cross_group_data, $post_id);
                        ecv_save_variations_data($post_id, $converted['data']);
                        ecv_save_combinations_data($post_id, $converted['combinations']);
                        error_log('ECV Debug: 🔄 Frontend data regenerated with new group images');
                    }
                }
            } else {
                error_log('ECV Debug: ⚠️ Group images data was empty or invalid after decode, clearing');
                update_post_meta($post_id, '_ecv_group_images_data', []);
            }
        }
    } else {
        error_log('ECV Debug: ❌ No ecv_group_images_data found in POST - data not being submitted!');
        // Check if any related fields exist
        $related_fields = ['ecv_variations_data', 'ecv_combinations_data', 'ecv_cross_group_data'];
        foreach ($related_fields as $field) {
            if (isset($_POST[$field])) {
                error_log('ECV Debug: Found related field: ' . $field . ' (length: ' . strlen($_POST[$field]) . ')');
            }
        }
    }
    
    if ($variation_mode === 'cross_group') {
        // Check if product has CSV-imported data (combination format)
        $existing_cross_group_data = ecv_get_cross_group_data($post_id);
        $is_csv_imported = !empty($existing_cross_group_data) && !isset($existing_cross_group_data['groupsDefinition']);
        
        if ($is_csv_imported) {
            // This product was imported from CSV - DO NOT modify variation data from admin panel
            // Only allow updates via CSV re-import to preserve the exact format
            error_log('ECV Save: Product has CSV-imported variations - preserving existing data (read-only mode)');
            
            // Still allow updating display types and group images meta if provided
            if (!empty($existing_cross_group_data)) {
                ecv_extract_and_save_attribute_display_types($post_id, $existing_cross_group_data);
            }
        } else {
            // This is Excel-like format from admin UI - save normally
            if (isset($_POST['ecv_cross_group_data'])) {
                $cross_group_raw = $_POST['ecv_cross_group_data'];
                error_log('ECV Save: Cross-group data received (length: ' . strlen($cross_group_raw) . ')');
                
                if (!empty($cross_group_raw)) {
                    $cross_group_data = json_decode(stripslashes($cross_group_raw), true);
                    if ($cross_group_data && is_array($cross_group_data) && !empty($cross_group_data)) {
                        $cross_group_data = ecv_sanitize_variation_data($cross_group_data);
                        ecv_save_cross_group_data($post_id, $cross_group_data);
                        
                        // Extract and save attribute display types from cross-group data BEFORE conversion
                        ecv_extract_and_save_attribute_display_types($post_id, $cross_group_data);
                        
                        // IMPORTANT: Convert AFTER group images and display types are saved so conversion can access them
                        error_log('ECV Save: Converting cross-group data to frontend format');
                        $converted = ecv_convert_cross_group_to_traditional_format($cross_group_data, $post_id);
                        ecv_save_variations_data($post_id, $converted['data']);
                        ecv_save_combinations_data($post_id, $converted['combinations']);
                        error_log('ECV Save: ✓ Cross-group data saved and converted');
                    } else {
                        error_log('ECV Save: ⚠️ Cross-group data was empty after decode - NOT clearing existing data');
                    }
                } else {
                    error_log('ECV Save: ⚠️ Cross-group data field was empty - NOT clearing existing data');
                }
            } else {
                error_log('ECV Save: ℹ️ No cross-group data field in POST - preserving existing data');
            }
        }
    } elseif ($variation_mode === 'grouped') {
        // Save grouped data (deprecated but keep for backward compatibility)
        if (isset($_POST['ecv_grouped_data'])) {
            $grouped_raw = $_POST['ecv_grouped_data'];
            error_log('ECV Save: Grouped data received (length: ' . strlen($grouped_raw) . ')');
            
            if (!empty($grouped_raw)) {
                $grouped_data = json_decode(stripslashes($grouped_raw), true);
                if ($grouped_data && is_array($grouped_data) && !empty($grouped_data)) {
                    $grouped_data = ecv_sanitize_variation_data($grouped_data);
                    ecv_save_grouped_data($post_id, $grouped_data);
                    
                    // Convert grouped data to traditional format for frontend
                    $converted = ecv_convert_groups_to_traditional_format($grouped_data, $post_id);
                    ecv_save_variations_data($post_id, $converted['data']);
                    ecv_save_combinations_data($post_id, $converted['combinations']);
                    error_log('ECV Save: ✓ Grouped data saved and converted');
                } else {
                    error_log('ECV Save: ⚠️ Grouped data was empty after decode - NOT clearing existing data');
                }
            } else {
                error_log('ECV Save: ⚠️ Grouped data field was empty - NOT clearing existing data');
            }
        } else {
            error_log('ECV Save: ℹ️ No grouped data field in POST - preserving existing data');
        }
    } else {
        // Save traditional data
        // CRITICAL: Only save if data is present and not empty to prevent accidental clearing
        if (isset($_POST['ecv_variations_data'])) {
            $data_raw = $_POST['ecv_variations_data'];
            error_log('ECV Save: Traditional variations data received (length: ' . strlen($data_raw) . ')');
            
            if (!empty($data_raw)) {
                $data = json_decode(stripslashes($data_raw), true);
                if ($data && is_array($data) && !empty($data)) {
                    $data = ecv_sanitize_variation_data($data);
                    
                    // Extract and save attribute display types from traditional data
                    ecv_extract_and_save_display_types_from_traditional($post_id, $data);
                    
                    ecv_save_variations_data($post_id, $data);
                    error_log('ECV Save: ✓ Variations data saved (' . count($data) . ' attributes)');
                } else {
                    error_log('ECV Save: ⚠️ Variations data was empty after decode - NOT clearing existing data');
                }
            } else {
                error_log('ECV Save: ⚠️ Variations data field was empty - NOT clearing existing data');
            }
        } else {
            error_log('ECV Save: ℹ️ No variations data field in POST - preserving existing data');
        }
        
        if (isset($_POST['ecv_combinations_data'])) {
            $combinations_raw = $_POST['ecv_combinations_data'];
            error_log('ECV Save: Traditional combinations data received (length: ' . strlen($combinations_raw) . ')');
            
            if (!empty($combinations_raw)) {
                $combinations = json_decode(stripslashes($combinations_raw), true);
                if ($combinations && is_array($combinations) && !empty($combinations)) {
                    $combinations = ecv_sanitize_variation_data($combinations);
                    ecv_save_combinations_data($post_id, $combinations);
                    error_log('ECV Save: ✓ Combinations data saved (' . count($combinations) . ' combinations)');
                } else {
                    error_log('ECV Save: ⚠️ Combinations data was empty after decode - NOT clearing existing data');
                }
            } else {
                error_log('ECV Save: ⚠️ Combinations data field was empty - NOT clearing existing data');
            }
        } else {
            error_log('ECV Save: ℹ️ No combinations data field in POST - preserving existing data');
        }
    }

}, 20, 3); // Priority 20 (after WooCommerce at 10), 3 parameters

/**
 * Extract and save attribute display types from cross-group admin data
 * This ensures display types are preserved when saving from the admin panel
 */
function ecv_extract_and_save_attribute_display_types($product_id, $cross_group_data) {
    // For cross-group format from the Excel-like admin UI,
    // we need to parse the groupsDefinition to extract attribute names
    // and infer display types from the variations_data or use defaults
    
    // Check if this is the Excel-like format with groupsDefinition
    if (isset($cross_group_data['groupsDefinition'])) {
        // Parse groups definition to get attributes
        $groups_definition = $cross_group_data['groupsDefinition'];
        error_log('ECV Extract Display Types: Parsing groupsDefinition: ' . $groups_definition);
        
        // Get existing traditional variations data to check for display_type
        $existing_variations = ecv_get_variations_data($product_id);
        $display_types = array();
        
        // Try to parse attribute names from groupsDefinition
        // Format: "Finish: G1=Matte,Glossy|G2=Textured,Smooth; Colour: C1=Red,Blue|C2=Green,Yellow"
        $attribute_parts = explode(';', $groups_definition);
        
        foreach ($attribute_parts as $attribute_part) {
            $colon_pos = strpos($attribute_part, ':');
            if ($colon_pos !== false) {
                $attribute_name = trim(substr($attribute_part, 0, $colon_pos));
                $attr_key_lower = strtolower($attribute_name);
                
                // Check if this attribute has a display type in existing data
                $found_display_type = 'buttons'; // Default
                foreach ($existing_variations as $attr) {
                    if (strtolower($attr['name']) === $attr_key_lower && !empty($attr['display_type'])) {
                        $found_display_type = $attr['display_type'];
                        break;
                    }
                }
                
                $display_types[$attr_key_lower] = $found_display_type;
            }
        }
        
        if (!empty($display_types)) {
            update_post_meta($product_id, '_ecv_attribute_display_types', $display_types);
            error_log('ECV Extract Display Types: Saved display types: ' . print_r($display_types, true));
        }
    } else {
        // For combination-based format imported from CSV, extract from attributes
        $display_types = array();
        
        if (is_array($cross_group_data)) {
            foreach ($cross_group_data as $combination) {
                if (isset($combination['attributes']) && is_array($combination['attributes'])) {
                    foreach ($combination['attributes'] as $attr_name => $attr_data) {
                        $attr_key_lower = strtolower($attr_name);
                        
                        // Check if display_type is specified
                        if (isset($attr_data['display_type']) && !isset($display_types[$attr_key_lower])) {
                            $display_types[$attr_key_lower] = $attr_data['display_type'];
                        }
                    }
                }
            }
        }
        
        if (!empty($display_types)) {
            update_post_meta($product_id, '_ecv_attribute_display_types', $display_types);
            error_log('ECV Extract Display Types: Saved display types from combinations: ' . print_r($display_types, true));
        }
    }
}

/**
 * Extract and save attribute display types from traditional variations data
 */
function ecv_extract_and_save_display_types_from_traditional($product_id, $variations_data) {
    $display_types = array();
    
    foreach ($variations_data as $attribute) {
        if (isset($attribute['name']) && isset($attribute['display_type'])) {
            $attr_key_lower = strtolower($attribute['name']);
            $display_types[$attr_key_lower] = $attribute['display_type'];
        }
    }
    
    if (!empty($display_types)) {
        update_post_meta($product_id, '_ecv_attribute_display_types', $display_types);
        error_log('ECV Extract Display Types: Saved display types from traditional data: ' . print_r($display_types, true));
    }
}


