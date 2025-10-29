<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Meta keys for storing data
const ECV_META_KEY = '_ecv_variations_data';
const ECV_COMBINATIONS_META_KEY = '_ecv_combinations_data';

// Meta key for storing conditional rules
const ECV_RULES_META_KEY = '_ecv_conditional_rules';

// Meta keys for group-based pricing (legacy)
const ECV_GROUP_PRICING_META_KEY = '_ecv_group_pricing_enabled';
const ECV_GROUP_PRICING_DATA_META_KEY = '_ecv_group_pricing_data';

// Meta keys for grouped data (new simplified format - to be deprecated)
const ECV_GROUPED_DATA_META_KEY = '_ecv_grouped_data';

// Meta keys for cross group pricing data
const ECV_CROSS_GROUP_DATA_META_KEY = '_ecv_cross_group_data';

// Meta key for separate extra attributes (independent panel)
const ECV_EXTRA_ATTRS_META_KEY = '_ecv_extra_attrs_data';

// Meta key for product-level extra fields (dynamic, flexible system)
const ECV_PRODUCT_EXTRA_FIELDS_META_KEY = '_ecv_product_extra_fields';

function ecv_get_variations_data( $post_id ) {
    $data = get_post_meta( $post_id, ECV_META_KEY, true );
    return is_array( $data ) ? $data : [];
}

function ecv_save_variations_data( $post_id, $data ) {
    if ( empty( $data ) ) {
        delete_post_meta( $post_id, ECV_META_KEY );
    } else {
        update_post_meta( $post_id, ECV_META_KEY, $data );
    }
}

function ecv_get_combinations_data( $post_id ) {
    $data = get_post_meta( $post_id, ECV_COMBINATIONS_META_KEY, true );
    return is_array( $data ) ? $data : [];
}

function ecv_save_combinations_data( $post_id, $data ) {
    if ( empty( $data ) ) {
        delete_post_meta( $post_id, ECV_COMBINATIONS_META_KEY );
    } else {
        update_post_meta( $post_id, ECV_COMBINATIONS_META_KEY, $data );
    }
}

function ecv_delete_variations_data( $post_id ) {
    delete_post_meta( $post_id, ECV_META_KEY );
    delete_post_meta( $post_id, ECV_COMBINATIONS_META_KEY );
}

function ecv_get_conditional_rules( $post_id ) {
    $rules = get_post_meta( $post_id, ECV_RULES_META_KEY, true );
    return is_array( $rules ) ? $rules : [];
}

function ecv_save_conditional_rules( $post_id, $rules ) {
    if ( empty( $rules ) ) {
        delete_post_meta( $post_id, ECV_RULES_META_KEY );
    } else {
        update_post_meta( $post_id, ECV_RULES_META_KEY, $rules );
    }
}

// Helper functions for display settings
function ecv_get_display_type( $post_id ) {
    $display_type = get_post_meta( $post_id, '_ecv_display_type', true );
    return in_array( $display_type, ['buttons', 'dropdown', 'radio'] ) ? $display_type : 'buttons';
}

function ecv_get_show_images_setting( $post_id ) {
    $show_images = get_post_meta( $post_id, '_ecv_show_images', true );
    return $show_images === 'no' ? 'no' : 'yes'; // Default to 'yes'
}

// Group-based pricing functions
function ecv_get_group_pricing_enabled( $post_id ) {
    $enabled = get_post_meta( $post_id, ECV_GROUP_PRICING_META_KEY, true );
    return $enabled === 'yes' ? 'yes' : 'no';
}

function ecv_save_group_pricing_enabled( $post_id, $enabled ) {
    update_post_meta( $post_id, ECV_GROUP_PRICING_META_KEY, $enabled === 'yes' ? 'yes' : 'no' );
}

function ecv_get_group_pricing_data( $post_id ) {
    $data = get_post_meta( $post_id, ECV_GROUP_PRICING_DATA_META_KEY, true );
    return is_array( $data ) ? $data : [];
}

function ecv_save_group_pricing_data( $post_id, $data ) {
    if ( empty( $data ) ) {
        delete_post_meta( $post_id, ECV_GROUP_PRICING_DATA_META_KEY );
    } else {
        update_post_meta( $post_id, ECV_GROUP_PRICING_DATA_META_KEY, $data );
    }
}

function ecv_delete_group_pricing_data( $post_id ) {
    delete_post_meta( $post_id, ECV_GROUP_PRICING_META_KEY );
    delete_post_meta( $post_id, ECV_GROUP_PRICING_DATA_META_KEY );
}

// Extra attributes accessors
function ecv_get_extra_attrs_data( $post_id ) {
    $data = get_post_meta( $post_id, ECV_EXTRA_ATTRS_META_KEY, true );
    return is_array( $data ) ? $data : [];
}

function ecv_save_extra_attrs_data( $post_id, $data ) {
    if ( empty( $data ) ) {
        delete_post_meta( $post_id, ECV_EXTRA_ATTRS_META_KEY );
    } else {
        update_post_meta( $post_id, ECV_EXTRA_ATTRS_META_KEY, $data );
    }
}

// Helper function to convert group-based pricing to traditional format
function ecv_convert_groups_to_traditional_format( $group_pricing_data, $post_id = null ) {
    error_log('ECV Convert Advanced Groups: Starting conversion with data: ' . print_r($group_pricing_data, true));
    
    $data = [];
    $combinations = [];
    
    if ( empty( $group_pricing_data ) || !is_array( $group_pricing_data ) ) {
        error_log('ECV Convert Advanced Groups: Empty or invalid group data');
        return [ 'data' => $data, 'combinations' => $combinations ];
    }
    
    // Check if this is the new advanced format (with combination_id) or old format
    $is_advanced_format = !empty($group_pricing_data[0]['combination_id']);
    
    if ($is_advanced_format) {
        return ecv_convert_advanced_group_combinations_to_traditional($group_pricing_data, $post_id);
    } else {
        // Fallback to old format conversion
        return ecv_convert_simple_groups_to_traditional($group_pricing_data, $post_id);
    }
}

// Convert advanced group combinations format to traditional
function ecv_convert_advanced_group_combinations_to_traditional($group_combinations, $post_id = null) {
    error_log('ECV Convert Advanced Combinations: Starting with ' . count($group_combinations) . ' combinations');
    
    $data = [];
    $combinations = [];
    
    // Get group images and per-value button images if available
    $group_images_data = [];
    $value_button_images = [];
    $attribute_display_types = [];
    if ($post_id) {
        $group_images_data = get_post_meta($post_id, '_ecv_group_images_data', true) ?: [];
        $value_button_images = get_post_meta($post_id, '_ecv_value_button_images', true) ?: [];
        $attribute_display_types = get_post_meta($post_id, '_ecv_attribute_display_types', true) ?: [];
    }
    
    // Get all unique attributes from all group combinations
    $all_attributes = [];
    foreach ($group_combinations as $combination) {
        foreach ($combination['attributes'] as $attribute_name => $attribute_data) {
            if (!in_array($attribute_name, $all_attributes)) {
                $all_attributes[] = $attribute_name;
            }
        }
    }
    
    // Build traditional attribute structure - avoid duplicates by tracking unique value-group pairs
    foreach ($all_attributes as $attribute_name) {
        $variants = [];
        $added_variants = []; // Track unique value-group combinations to avoid duplicates
        
        // For each group combination, add variants for this attribute
        foreach ($group_combinations as $combo_index => $combination) {
            if (isset($combination['attributes'][$attribute_name])) {
                $attribute_data = $combination['attributes'][$attribute_name];
                $group_name = $attribute_data['group_name'];
                $values = $attribute_data['values'];
                
                foreach ($values as $value) {
                    // Create unique key for this value-group combination
                    $unique_key = $value . '_' . $group_name;
                    
                    // Only add if we haven't already added this exact value-group combination
                    if (!in_array($unique_key, $added_variants)) {
                        // Use the combination image for this group's values (for product image changes)
                        $variant_image = !empty($combination['combination_image']) ? $combination['combination_image'] : '';
                        
                        // Get group button image if available
                        $group_button_image = '';
                        $group_image_key = $attribute_name . ':' . $group_name;
                        
                        // Try exact key first
                        if (!empty($group_images_data[$group_image_key])) {
                            $group_button_image = $group_images_data[$group_image_key];
                        } else {
                            // Try alternative key formats
                            $alternative_keys = [
                                strtolower($attribute_name . ':' . $group_name),
                                $attribute_name . '_' . $group_name,
                                $group_name . ':' . $attribute_name,
                                $group_name
                            ];
                            
                            foreach ($alternative_keys as $alt_key) {
                                if (!empty($group_images_data[$alt_key])) {
                                    $group_button_image = $group_images_data[$alt_key];
                                    break;
                                }
                            }
                            
                            // If still not found, minimal logging only if WP_DEBUG is enabled
                            if (empty($group_button_image) && defined('WP_DEBUG') && WP_DEBUG) {
                                error_log('ECV Debug: No group button image found for: ' . $group_image_key);
                            }
                        }
                        
                        $variant_data = [
                            'name' => $value,
                            'image' => $variant_image, // Combination image (used for main image swaps)
                            'group_button_image' => $group_button_image, // Default per-group button image
                            'price_modifier' => 0,
                            'description' => '',
                            'group' => $group_name,
                            'group_combination' => $combination['combination_id'] // Track which combination this belongs to
                        ];

                        // Attach per-value button image if available
                        // Key path: $value_button_images[$attribute_name][$group_name][$value]
                        if (!empty($value_button_images[$attribute_name]) &&
                            !empty($value_button_images[$attribute_name][$group_name]) &&
                            !empty($value_button_images[$attribute_name][$group_name][$value])) {
                            $variant_data['button_image'] = $value_button_images[$attribute_name][$group_name][$value];
                        }
                        
                        
                        $variants[] = $variant_data;
                        $added_variants[] = $unique_key;
                    }
                }
            }
        }
        
        // Get display type for this attribute
        $attr_key_lower = strtolower($attribute_name);
        $display_type = isset($attribute_display_types[$attr_key_lower]) 
            ? $attribute_display_types[$attr_key_lower] 
            : 'buttons';
        
        $data[] = [
            'name' => $attribute_name,
            'display_type' => $display_type,
            'variants' => $variants
        ];
    }
    
    error_log('ECV Convert Advanced Combinations: Converted data: ' . print_r($data, true));
    
    // Generate specific combinations based on group combinations
    $combinations = ecv_generate_group_combination_variants($group_combinations);
    
    return ['data' => $data, 'combinations' => $combinations];
}

// Fallback for simple group format
function ecv_convert_simple_groups_to_traditional($group_pricing_data, $post_id = null) {
    $data = [];
    $combinations = [];
    
    // Get group images data if available
    $group_images_data = [];
    $attribute_display_types = [];
    if ($post_id) {
        $group_images_data = get_post_meta($post_id, '_ecv_group_images_data', true) ?: [];
        $attribute_display_types = get_post_meta($post_id, '_ecv_attribute_display_types', true) ?: [];
    }
    
    // Convert groups to attributes (like traditional system)
    foreach ( $group_pricing_data as $group_index => $group ) {
        if ( !empty( $group['name'] ) && !empty( $group['variations'] ) && is_array( $group['variations'] ) ) {
            $variants = [];
            foreach ( $group['variations'] as $variation ) {
                $variants[] = [
                    'name' => $variation['value'],
                    'image' => $variation['image'] ?? '',
                    'group_price' => floatval( $group['price'] ?? 0 ),
                    'group_image' => $group['image'] ?? '',
                    'price_modifier' => 0,
                    'description' => $group['description'] ?? ''
                ];
            }
            
            $attribute_name = $group['name'];
            if (!empty($group['variations'][0]['attribute'])) {
                $attribute_name = $group['variations'][0]['attribute'];
            }
            
            // Get display type for this attribute
            $attr_key_lower = strtolower($attribute_name);
            $display_type = isset($attribute_display_types[$attr_key_lower]) 
                ? $attribute_display_types[$attr_key_lower] 
                : 'buttons';
            
            $data[] = [
                'name' => $attribute_name,
                'display_type' => $display_type,
                'variants' => $variants,
                'group_name' => $group['name'],
                'group_price' => floatval( $group['price'] ?? 0 ),
                'group_image' => $group['image'] ?? ''
            ];
        }
    }
    
    // Generate combinations using traditional method
    if ( !empty( $data ) ) {
        $combinations = ecv_generate_traditional_combinations( $data );
    }
    
    return [ 'data' => $data, 'combinations' => $combinations ];
}

// Generate variants for each group combination
function ecv_generate_group_combination_variants($group_combinations) {
    error_log('ECV Generate Group Combination Variants: Starting with ' . count($group_combinations) . ' group combinations');
    
    $combinations = [];
    $combination_index = 0;
    
    // Process each group combination separately
    foreach ($group_combinations as $group_combo) {
        error_log('ECV Generate Group Combination Variants: Processing ' . $group_combo['combination_id']);
        
        // Build variant arrays for this specific group combination only
        $variant_arrays = [];
        
        foreach ($group_combo['attributes'] as $attribute_name => $attribute_data) {
            $variants = [];
            foreach ($attribute_data['values'] as $value) {
                // Use the combination image for this group's values
                $variant_image = !empty($group_combo['combination_image']) ? $group_combo['combination_image'] : '';
                
                $variants[] = [
                    'attribute' => $attribute_name,
                    'name' => $value,
                    'image' => $variant_image,
                    'group_name' => $attribute_data['group_name']
                ];
            }
            $variant_arrays[] = $variants;
        }
        
        // Generate cartesian product for ONLY this group combination
        if (!empty($variant_arrays)) {
            $variant_combinations = ecv_cartesian_product($variant_arrays);
            
            foreach ($variant_combinations as $variant_combo) {
                // Generate unique SKU
                $sku_parts = [];
                foreach ($variant_combo as $variant) {
                    $sku_parts[] = sanitize_title($variant['name']);
                }
                $sku = implode('-', $sku_parts) . '-' . $group_combo['combination_id'];
                
                // Determine sale price
                $sale_price = '';
                if (!empty($group_combo['combination_sale_price']) && 
                    $group_combo['combination_sale_price'] < $group_combo['combination_price']) {
                    $sale_price = $group_combo['combination_sale_price'];
                }
                
                $combinations[] = [
                    'id' => 'group-combo-' . $combination_index,
                    'enabled' => true,
                    'sku' => $sku,
                    'price' => $group_combo['combination_price'],
                    'sale_price' => $sale_price,
                    'stock' => $group_combo['combination_stock'],
                    'main_image_url' => $group_combo['combination_image'],
                    'variants' => $variant_combo,
                    'attributes' => array_map(function($v) {
                        return [
                            'attribute' => $v['attribute'],
                            'value' => $v['name']
                        ];
                    }, $variant_combo),
                    'is_group_based' => true,
                    'group_combination_id' => $group_combo['combination_id'],
                    'group_combination_description' => $group_combo['combination_description']
                ];
                
                $combination_index++;
            }
        }
    }
    
    error_log('ECV Generate Group Combination Variants: Generated ' . count($combinations) . ' total combinations');
    return $combinations;
}

// Helper function to generate combinations in traditional format
function ecv_generate_traditional_combinations( $data ) {
    error_log('ECV Generate Traditional Combinations: Starting with data: ' . print_r($data, true));
    
    $combinations = [];
    
    if ( empty( $data ) ) {
        error_log('ECV Generate Traditional Combinations: No data provided');
        return $combinations;
    }
    
    // Get all variant arrays (like traditional system)
    $variant_arrays = [];
    foreach ( $data as $attr ) {
        $variants = [];
        foreach ( $attr['variants'] as $variant ) {
            $variants[] = [
                'attribute' => $attr['name'],
                'name' => $variant['name'],
                'image' => $variant['image'] ?? '',
                'group_price' => $variant['group_price'] ?? 0,
                'group_image' => $variant['group_image'] ?? '',
                'group_name' => $attr['group_name'] ?? $attr['name'] // Track group name
            ];
        }
        $variant_arrays[] = $variants;
    }
    
    error_log('ECV Generate Traditional Combinations: Variant arrays: ' . print_r($variant_arrays, true));
    
    // Generate cartesian product
    $variation_combinations = ecv_cartesian_product( $variant_arrays );
    
    error_log('ECV Generate Traditional Combinations: Generated ' . count($variation_combinations) . ' combinations');
    
    foreach ( $variation_combinations as $index => $combination ) {
        // Calculate total price from group prices (not individual variant prices)
        $total_price = 0;
        $group_names = [];
        $seen_groups = [];
        
        foreach ( $combination as $variant ) {
            // Only add group price once per group to avoid double-counting
            $group_name = $variant['group_name'] ?? $variant['attribute'];
            if (!in_array($group_name, $seen_groups)) {
                $total_price += floatval( $variant['group_price'] ?? 0 );
                $seen_groups[] = $group_name;
            }
            $group_names[] = $variant['attribute'];
        }
        
        // Generate unique SKU
        $sku_parts = [];
        foreach ($combination as $variant) {
            $sku_parts[] = sanitize_title($variant['attribute'] . '-' . $variant['name']);
        }
        $sku = implode('_', $sku_parts);
        
        // Get main image from first variant or first group image
        $main_image = '';
        foreach ($combination as $variant) {
            if (!empty($variant['image'])) {
                $main_image = $variant['image'];
                break;
            }
            if (!empty($variant['group_image'])) {
                $main_image = $variant['group_image'];
                break;
            }
        }
        
        $combinations[] = [
            'id' => 'group-' . $index,
            'enabled' => true,
            'sku' => $sku,
            'price' => $total_price,
            'sale_price' => '',
            'stock' => '',
            'main_image_url' => $main_image,
            'variants' => $combination,
            'attributes' => array_map( function( $v ) {
                return [
                    'attribute' => $v['attribute'],
                    'value' => $v['name']
                ];
            }, $combination ),
            'is_group_based' => true // Flag to identify group-based combinations
        ];
    }
    
    error_log('ECV Generate Traditional Combinations: Final combinations: ' . print_r($combinations, true));
    return $combinations;
}

// Helper function to generate variation combinations from groups
function ecv_generate_variation_combinations( $groups ) {
    if ( empty( $groups ) ) {
        return [];
    }
    
    // Extract variations from each group
    $variation_arrays = [];
    foreach ( $groups as $group ) {
        if ( !empty( $group['variations'] ) && is_array( $group['variations'] ) ) {
            $variation_arrays[] = $group['variations'];
        }
    }
    
    if ( empty( $variation_arrays ) ) {
        return [];
    }
    
    // Generate cartesian product of variations
    return ecv_cartesian_product( $variation_arrays );
}

// Helper function to generate cartesian product
function ecv_cartesian_product( $arrays ) {
    if ( empty( $arrays ) ) {
        return [[]];
    }
    
    $result = [[]];
    
    foreach ( $arrays as $array ) {
        $temp = [];
        foreach ( $result as $result_item ) {
            foreach ( $array as $array_item ) {
                $temp[] = array_merge( $result_item, [$array_item] );
            }
        }
        $result = $temp;
    }
    
    return $result;
}

// Grouped data functions
function ecv_get_grouped_data( $post_id ) {
    $data = get_post_meta( $post_id, ECV_GROUPED_DATA_META_KEY, true );
    return is_array( $data ) ? $data : [];
}

function ecv_save_grouped_data( $post_id, $data ) {
    update_post_meta( $post_id, ECV_GROUPED_DATA_META_KEY, $data );
}

function ecv_delete_grouped_data( $post_id ) {
    delete_post_meta( $post_id, ECV_GROUPED_DATA_META_KEY );
}

// Cross Group Pricing data functions
function ecv_get_cross_group_data( $post_id ) {
    $data = get_post_meta( $post_id, ECV_CROSS_GROUP_DATA_META_KEY, true );
    return is_array( $data ) ? $data : [];
}

function ecv_save_cross_group_data( $post_id, $data ) {
    if ( empty( $data ) ) {
        delete_post_meta( $post_id, ECV_CROSS_GROUP_DATA_META_KEY );
    } else {
        update_post_meta( $post_id, ECV_CROSS_GROUP_DATA_META_KEY, $data );
    }
}

function ecv_delete_cross_group_data( $post_id ) {
    delete_post_meta( $post_id, ECV_CROSS_GROUP_DATA_META_KEY );
}

// Convert cross group data to traditional format for frontend compatibility
function ecv_convert_cross_group_to_traditional_format( $cross_group_data, $post_id = null ) {
    error_log('ECV Convert Cross Groups: Starting conversion with data: ' . print_r($cross_group_data, true));
    
    $data = [];
    $combinations = [];
    
    if ( empty( $cross_group_data ) || !is_array( $cross_group_data ) ) {
        error_log('ECV Convert Cross Groups: Empty or invalid cross group data');
        return [ 'data' => $data, 'combinations' => $combinations ];
    }
    
    // Check if this data has already been formatted as combinations from import
    $is_import_combinations_format = !empty($cross_group_data[0]['combination_id']);
    
    if ($is_import_combinations_format) {
        // Use existing advanced combination converter for imported data
        return ecv_convert_advanced_group_combinations_to_traditional($cross_group_data, $post_id);
    } else {
        // Convert admin UI format to combination format first
        $combinations_data = ecv_convert_admin_cross_group_to_combinations($cross_group_data);
        return ecv_convert_advanced_group_combinations_to_traditional($combinations_data, $post_id);
    }
}

// Generate all possible combinations from cross group data
function ecv_generate_cross_group_combinations( $cross_group_data ) {
    if ( empty( $cross_group_data ) ) {
        return [];
    }
    
    $combinations = [];
    $combo_index = 0;
    
    // First, collect all attribute-value pairs for each group
    $group_attribute_pairs = [];
    foreach ($cross_group_data as $group_name => $group_data) {
        if (!empty($group_data['attributes'])) {
            foreach ($group_data['attributes'] as $attr_name => $attr_values) {
                if (is_array($attr_values)) {
                    foreach ($attr_values as $value) {
                        if (!empty($value)) {
                            $group_attribute_pairs[] = [
                                'group_name' => $group_name,
                                'attribute' => $attr_name,
                                'value' => $value,
                                'base_price' => $group_data['base_price'] ?? 0
                            ];
                        }
                    }
                }
            }
        }
    }
    
    // Generate cross combinations - each combination from one group can combine with each from another
    $group_names = array_keys($cross_group_data);
    
    if (count($group_names) >= 2) {
        // Generate cross-group combinations (Group1 + Group2)
        for ($i = 0; $i < count($group_names); $i++) {
            for ($j = $i + 1; $j < count($group_names); $j++) {
                $group1_name = $group_names[$i];
                $group2_name = $group_names[$j];
                
                $group1_pairs = array_filter($group_attribute_pairs, function($pair) use ($group1_name) {
                    return $pair['group_name'] === $group1_name;
                });
                
                $group2_pairs = array_filter($group_attribute_pairs, function($pair) use ($group2_name) {
                    return $pair['group_name'] === $group2_name;
                });
                
                // Create all combinations between these two groups
                foreach ($group1_pairs as $pair1) {
                    foreach ($group2_pairs as $pair2) {
                        $combination_id = $pair1['group_name'] . '+' . $pair1['attribute'] . '+' . $pair1['value'] . 
                                         '_' . $pair2['group_name'] . '+' . $pair2['attribute'] . '+' . $pair2['value'];
                        
                        $combinations[] = [
                            'combination_id' => $combination_id,
                            'price' => $pair1['base_price'] + $pair2['base_price'],
                            'attributes' => [
                                $pair1['attribute'] => [
                                    'group_name' => $pair1['group_name'],
                                    'values' => [$pair1['value']]
                                ],
                                $pair2['attribute'] => [
                                    'group_name' => $pair2['group_name'], 
                                    'values' => [$pair2['value']]
                                ]
                            ]
                        ];
                        $combo_index++;
                    }
                }
            }
        }
    } else {
        // Single group - generate individual combinations
        foreach ($group_attribute_pairs as $pair) {
            $combination_id = $pair['group_name'] . '+' . $pair['attribute'] . '+' . $pair['value'];
            
            $combinations[] = [
                'combination_id' => $combination_id,
                'price' => $pair['base_price'],
                'attributes' => [
                    $pair['attribute'] => [
                        'group_name' => $pair['group_name'],
                        'values' => [$pair['value']]
                    ]
                ]
            ];
        }
    }
    
    return $combinations;
}

// Product-level extra fields accessors (Dynamic system)
// Fields format: array of ['field_key' => 'unique_key', 'field_type' => 'image|text|pdf', 'field_value' => 'value']

function ecv_get_product_extra_fields($post_id) {
    $fields = get_post_meta($post_id, ECV_PRODUCT_EXTRA_FIELDS_META_KEY, true);
    return is_array($fields) ? $fields : array();
}

function ecv_save_product_extra_fields($post_id, $fields) {
    if (empty($fields) || !is_array($fields)) {
        delete_post_meta($post_id, ECV_PRODUCT_EXTRA_FIELDS_META_KEY);
    } else {
        update_post_meta($post_id, ECV_PRODUCT_EXTRA_FIELDS_META_KEY, $fields);
    }
}

function ecv_get_product_extra_field_by_key($post_id, $field_key) {
    $fields = ecv_get_product_extra_fields($post_id);
    foreach ($fields as $field) {
        if (isset($field['field_key']) && $field['field_key'] === $field_key) {
            return $field;
        }
    }
    return null;
}

function ecv_add_or_update_product_extra_field($post_id, $field_key, $field_type, $field_value) {
    $fields = ecv_get_product_extra_fields($post_id);
    $found = false;
    
    // Update existing field
    foreach ($fields as &$field) {
        if (isset($field['field_key']) && $field['field_key'] === $field_key) {
            $field['field_type'] = $field_type;
            $field['field_value'] = $field_value;
            $found = true;
            break;
        }
    }
    unset($field);
    
    // Add new field if not found
    if (!$found) {
        $fields[] = array(
            'field_key' => $field_key,
            'field_type' => $field_type,
            'field_value' => $field_value
        );
    }
    
    ecv_save_product_extra_fields($post_id, $fields);
}

function ecv_delete_product_extra_field($post_id, $field_key) {
    $fields = ecv_get_product_extra_fields($post_id);
    $fields = array_filter($fields, function($field) use ($field_key) {
        return !isset($field['field_key']) || $field['field_key'] !== $field_key;
    });
    $fields = array_values($fields); // Re-index array
    ecv_save_product_extra_fields($post_id, $fields);
}

// Convert Excel-like admin UI format to combination format (like import does)
function ecv_convert_admin_cross_group_to_combinations( $admin_cross_group_data ) {
    if ( empty( $admin_cross_group_data ) ) {
        return [];
    }
    
    $combinations = [];
    
    // Check if this is Excel-like format
    if (isset($admin_cross_group_data['groupsDefinition']) && isset($admin_cross_group_data['combinations'])) {
        // This is Excel-like format - convert to import combination format
        $groups_definition = $admin_cross_group_data['groupsDefinition'];
        $admin_combinations = $admin_cross_group_data['combinations'];
        
        // Parse groups definition to get attributes structure
        $parsed_groups = ecv_parse_groups_definition($groups_definition);
        
        // Convert each admin combination to import format
        foreach ($admin_combinations as $admin_combo) {
            if (empty($admin_combo['combination_name'])) {
                continue;
            }
            
            // Parse combination name (e.g., "G1+L1+C1+D1" for 4 groups)
            $group_names = explode('+', $admin_combo['combination_name']);
            $group_names = array_map('trim', $group_names);
            
            error_log('ECV Convert Admin: Processing combination: ' . $admin_combo['combination_name'] . ' with groups: ' . print_r($group_names, true));
            
            if (count($group_names) < 1) {
                error_log('ECV Convert Admin: Skipping combination with no groups: ' . $admin_combo['combination_name']);
                continue;
            }
            
            // Build attributes array from parsed groups and combination
            $attributes = [];
            
            foreach ($parsed_groups as $attribute_name => $attribute_groups) {
                error_log('ECV Convert Admin: Checking attribute: ' . $attribute_name . ' with groups: ' . print_r(array_keys($attribute_groups), true));
                
                // Find which group from this attribute is being used in the combination
                $selected_group = null;
                $selected_values = [];
                
                // Check each group in the combination to see if it belongs to this attribute
                foreach ($group_names as $group_name) {
                    if (isset($attribute_groups[$group_name])) {
                        $selected_group = $group_name;
                        $selected_values = $attribute_groups[$group_name];
                        error_log('ECV Convert Admin: Found group ' . $group_name . ' for attribute ' . $attribute_name . ' with values: ' . print_r($selected_values, true));
                        break;
                    }
                }
                
                if ($selected_group) {
                    $attributes[$attribute_name] = [
                        'group_name' => $selected_group,
                        'values' => $selected_values
                    ];
                }
            }
            
            error_log('ECV Convert Admin: Final attributes for combination: ' . print_r($attributes, true));
            
            $combinations[] = [
                'combination_id' => str_replace('+', '_', $admin_combo['combination_name']), // G1+C1 -> G1_C1
                'attributes' => $attributes,
                'combination_price' => floatval($admin_combo['price']) ?: 0,
                'combination_sale_price' => !empty($admin_combo['sale_price']) ? floatval($admin_combo['sale_price']) : '',
                'combination_stock' => !empty($admin_combo['stock']) ? intval($admin_combo['stock']) : '',
                'combination_image' => $admin_combo['image'] ?: '',
                'combination_description' => $admin_combo['description'] ?: ''
            ];
        }
    }
    
    return $combinations;
}
