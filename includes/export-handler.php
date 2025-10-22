<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function ecv_handle_export() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $export_product_data = isset($_POST['export_product_data']);
    $export_variant_data = isset($_POST['export_variant_data']);
    $export_group_pricing = isset($_POST['export_group_pricing']);
    $export_images = isset($_POST['export_images']);
    $export_scope = sanitize_text_field($_POST['export_scope']);
    $specific_ids = sanitize_text_field($_POST['specific_ids']);

    // Get products based on scope
    $product_ids = ecv_get_products_for_export($export_scope, $specific_ids);
    
    if (empty($product_ids)) {
        wp_die(__('No products found for export.', 'exp-custom-variations'));
    }

    // Generate CSV in attribute-column format (current standard)
    $csv_data = ecv_generate_export_csv_attribute_column($product_ids, $export_product_data, $export_images);
    
    // Send CSV file
    ecv_send_csv_download($csv_data, 'custom-variations-export-' . date('Y-m-d-H-i-s') . '.csv');
}

function ecv_get_products_for_export($scope, $specific_ids = '') {
    $args = array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids'
    );

    switch ($scope) {
        case 'variants_only':
            $args['meta_query'] = array(
                array(
                    'key' => ECV_META_KEY,
                    'compare' => 'EXISTS'
                )
            );
            break;
        
        case 'specific':
            if (!empty($specific_ids)) {
                $ids = array_map('intval', explode(',', $specific_ids));
                $args['post__in'] = array_filter($ids);
            } else {
                return array();
            }
            break;
        
        case 'all':
        default:
            // No additional filtering
            break;
    }

    return get_posts($args);
}

function ecv_generate_export_csv($product_ids, $include_product_data, $include_variant_data, $include_images, $include_group_pricing = false) {
    $csv_data = array();
    
    // Generate headers
    $headers = ecv_get_csv_headers($include_product_data, $include_variant_data, $include_images);
    $csv_data[] = $headers;

    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if (!$product) continue;

        $variations_data = ecv_get_variations_data($product_id);
        $combinations_data = ecv_get_combinations_data($product_id);
        $group_pricing_enabled = ecv_get_group_pricing_enabled($product_id);
        $group_pricing_data = ecv_get_group_pricing_data($product_id);

        if ($include_group_pricing && $group_pricing_enabled === 'yes' && !empty($group_pricing_data)) {
            // Product has group-based pricing - create a row for each variation in each group
            foreach ($group_pricing_data as $group) {
                foreach ($group['variations'] as $variation) {
                    $row = ecv_generate_group_pricing_row($product, $group, $variation, $include_product_data, $include_images);
                    $csv_data[] = $row;
                }
            }
        } elseif (!empty($combinations_data)) {
            // Product has traditional variants - create a row for each combination
            foreach ($combinations_data as $combination) {
                $row = ecv_generate_product_row($product, $variations_data, $combination, $include_product_data, $include_variant_data, $include_images);
                $csv_data[] = $row;
            }
        } else {
            // Product without variants - create single row
            $row = ecv_generate_product_row($product, array(), null, $include_product_data, $include_variant_data, $include_images);
            $csv_data[] = $row;
        }
    }

    return $csv_data;
}

function ecv_get_csv_headers($include_product_data, $include_variant_data, $include_images, $include_group_pricing = false) {
    $headers = array('ID');

    if ($include_product_data) {
        $headers = array_merge($headers, array(
            'Name',
            'Slug',
            'Description',
            'Short Description',
            'SKU',
            'Regular Price',
            'Sale Price',
            'Stock Status',
            'Stock Quantity',
            'Categories',
            'Tags',
            'Product Type',
            'Status'
        ));

        if ($include_images) {
            $headers[] = 'Main Image URL';
            $headers[] = 'Gallery Images';
        }
    }

    if ($include_group_pricing) {
        $headers = array_merge($headers, array(
            'Has Group Pricing',
            'Group Name',
            'Group Price',
            'Group Image',
            'Attribute',
            'Value',
            'Variation Image'
        ));
    } elseif ($include_variant_data) {
        $headers = array_merge($headers, array(
            'Has Custom Variants',
            'Variant Combination ID',
            'Variant SKU',
            'Variant Price',
            'Variant Sale Price',
            'Variant Stock',
            'Variant Enabled',
            'Variant Attributes',
            'Attribute Names',
            'Attribute Values',
            'Attribute Groups',
            'Variant Groups'
        ));

        if ($include_images) {
            $headers[] = 'Variant Main Image';
            $headers[] = 'Variant Attribute Images';
        }
    }
    
    // Add extra attributes columns
    $headers = array_merge($headers, array(
        'Extra Attributes'
    ));

    return $headers;
}

function ecv_generate_product_row($product, $variations_data, $combination, $include_product_data, $include_variant_data, $include_images, $include_group_pricing = false) {
    $row = array($product->get_id());

    if ($include_product_data) {
        // Basic product data
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
        $tags = wp_get_post_terms($product->get_id(), 'product_tag', array('fields' => 'names'));
        
        $row = array_merge($row, array(
            $product->get_name(),
            $product->get_slug(),
            $product->get_description(),
            $product->get_short_description(),
            $product->get_sku(),
            $product->get_regular_price(),
            $product->get_sale_price(),
            $product->get_stock_status(),
            $product->get_stock_quantity(),
            implode('|', $categories),
            implode('|', $tags),
            $product->get_type(),
            $product->get_status()
        ));

        if ($include_images) {
            $main_image = wp_get_attachment_image_url($product->get_image_id(), 'full');
            $gallery_ids = $product->get_gallery_image_ids();
            $gallery_urls = array();
            foreach ($gallery_ids as $gallery_id) {
                $gallery_urls[] = wp_get_attachment_image_url($gallery_id, 'full');
            }
            
            $row[] = $main_image ?: '';
            $row[] = implode('|', $gallery_urls);
        }
    }

    if ($include_variant_data) {
        $has_variants = !empty($variations_data) ? 'Yes' : 'No';
        
        if ($combination) {
            // Build variant attributes from variations_data and combination
            $attributes = array();
            $attr_names = array();
            $attr_values = array();
            $attr_images = array();
            
            // Use the combination's variants array (the correct structure from admin.js)
            if (!empty($combination['variants'])) {
                foreach ($combination['variants'] as $variant) {
                    $attr_name = $variant['attribute'] ?? '';
                    $attr_value = $variant['name'] ?? '';
                    
                    if (!empty($attr_name) && !empty($attr_value)) {
                        $attributes[] = $attr_name . ':' . $attr_value;
                        $attr_names[] = $attr_name;
                        $attr_values[] = $attr_value;
                        
                        // Get variant image from the variant itself or variations_data
                        if ($include_images) {
                            if (!empty($variant['image'])) {
                                $attr_images[] = $variant['image'];
                            } else {
                                // Fallback: look in variations_data
                                foreach ($variations_data as $attr_data) {
                                    if ($attr_data['name'] === $attr_name) {
                                        foreach ($attr_data['variants'] as $var) {
                                            if ($var['name'] === $attr_value && !empty($var['image'])) {
                                                $attr_images[] = $var['image'];
                                                break 2;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            // Fallback: try to build from variations_data structure
            elseif (!empty($variations_data)) {
                // This is a fallback method - generate attributes based on combination index
                $combo_index = intval($combination['id'] ?? 0);
                $variant_indices = ecv_decode_combination_index($combo_index, $variations_data);
                
                foreach ($variations_data as $attr_index => $attr_data) {
                    if (isset($variant_indices[$attr_index])) {
                        $variant_index = $variant_indices[$attr_index];
                        if (isset($attr_data['variants'][$variant_index])) {
                            $variant = $attr_data['variants'][$variant_index];
                            $attr_name = $attr_data['name'];
                            $attr_value = $variant['name'];
                            
                            $attributes[] = $attr_name . ':' . $attr_value;
                            $attr_names[] = $attr_name;
                            $attr_values[] = $attr_value;
                            
                            if ($include_images && !empty($variant['image'])) {
                                $attr_images[] = $variant['image'];
                            }
                        }
                    }
                }
            }

            // Extract group information
            $attribute_groups_mapping = array();
            $variant_groups_mapping = array();
            
            if (!empty($combination['variants'])) {
                foreach ($combination['variants'] as $variant) {
                    $attr_name = $variant['attribute'] ?? '';
                    $attr_value = $variant['name'] ?? '';
                    
                    // Find the groups for this attribute and specific variant value
                    foreach ($variations_data as $attr_data) {
                        if ($attr_data['name'] === $attr_name) {
                            // Get attribute-level groups for this specific attribute
                            if (!empty($attr_data['groups'])) {
                                $groups = explode(',', $attr_data['groups']);
                                $groups = array_map('trim', $groups);
                                $groups = array_filter($groups); // Remove empty values
                                if (!empty($groups)) {
                                    $attribute_groups_mapping[] = $attr_name . ':' . implode(',', $groups);
                                }
                            }
                            
                            // Find the specific variant and its assigned group
                            foreach ($attr_data['variants'] as $var) {
                                if ($var['name'] === $attr_value) {
                                    if (!empty($var['group'])) {
                                        $variant_groups_mapping[] = $attr_name . ':' . $var['group'];
                                    }
                                    break;
                                }
                            }
                            break;
                        }
                    }
                }
            }
            
            // Create final groups strings with pipe separation
            $attribute_groups = implode('|', $attribute_groups_mapping);
            $variant_groups = implode('|', $variant_groups_mapping);
            
            $row = array_merge($row, array(
                $has_variants,
                $combination['id'] ?? '',
                $combination['sku'] ?? '',
                $combination['price'] ?? '',
                $combination['sale_price'] ?? '',
                $combination['stock'] ?? '',
                !empty($combination['enabled']) ? 'Yes' : 'No',
                implode('|', $attributes),
                implode('|', $attr_names),
                implode('|', $attr_values),
                $attribute_groups,
                $variant_groups
            ));

            if ($include_images) {
                $row[] = $combination['main_image_url'] ?? '';
                $row[] = implode('|', $attr_images);
            }
        } else {
            // No variants - includes the group columns
            $empty_variant_fields = array($has_variants, '', '', '', '', '', '', '', '', '', '', '');
            if ($include_images) {
                $empty_variant_fields = array_merge($empty_variant_fields, array('', ''));
            }
            $row = array_merge($row, $empty_variant_fields);
        }
    }
    
    // Add extra attributes data
    $extra_attrs = ecv_get_extra_attrs_data($product->get_id());
    $row[] = ecv_format_extra_attrs_for_export($extra_attrs);

    return $row;
}

/**
 * Format extra attributes for CSV export
 * Format: AttrName1[Option1:Price1,Option2:Price2];AttrName2[Option1:Price1,Option2:Price2]
 */
function ecv_format_extra_attrs_for_export($extra_attrs) {
    if (empty($extra_attrs) || !is_array($extra_attrs)) {
        return '';
    }
    
    $formatted_attrs = array();
    
    foreach ($extra_attrs as $attr) {
        if (empty($attr['name'])) continue;
        
        $attr_name = $attr['name'];
        $display_type = isset($attr['display_type']) ? $attr['display_type'] : 'dropdown';
        $variants = isset($attr['variants']) ? $attr['variants'] : array();
        
        // Format variants with prices: Option1:Price1,Option2:Price2
        $formatted_variants = array();
        foreach ($variants as $variant) {
            $variant_name = isset($variant['name']) ? $variant['name'] : '';
            $variant_price = isset($variant['price']) && $variant['price'] > 0 ? $variant['price'] : '0';
            
            if (!empty($variant_name)) {
                $formatted_variants[] = $variant_name . ':' . $variant_price;
            }
        }
        
        if (!empty($formatted_variants)) {
            // Format: AttrName[DisplayType:Option1:Price1,Option2:Price2]
            $formatted_attrs[] = $attr_name . '[' . $display_type . ':' . implode(',', $formatted_variants) . ']';
        }
    }
    
    return implode(';', $formatted_attrs);
}

// Helper function to decode combination index back to variant indices
function ecv_decode_combination_index($combo_index, $variations_data) {
    $variant_indices = array();
    $remaining = $combo_index;
    
    // Work backwards through attributes
    for ($i = count($variations_data) - 1; $i >= 0; $i--) {
        $variant_count = count($variations_data[$i]['variants']);
        if ($variant_count > 0) {
            $variant_indices[$i] = $remaining % $variant_count;
            $remaining = intval($remaining / $variant_count);
        }
    }
    
    return $variant_indices;
}

function ecv_send_csv_download($csv_data, $filename) {
    // Clean any output buffer to prevent corruption
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

    // Create file pointer connected to the output stream
    $output = fopen('php://output', 'w');

    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Output CSV data
    foreach ($csv_data as $row) {
        // Ensure all row data is properly escaped and clean
        $clean_row = array_map(function($field) {
            // Remove any line breaks and clean the field
            return str_replace(array("\r\n", "\r", "\n"), ' ', (string)$field);
        }, $row);
        
        fputcsv($output, $clean_row);
    }

    fclose($output);
    exit;
}

function ecv_generate_group_pricing_row($product, $group, $variation, $include_product_data, $include_images) {
    $row = array($product->get_id());

    if ($include_product_data) {
        // Basic product data
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
        $tags = wp_get_post_terms($product->get_id(), 'product_tag', array('fields' => 'names'));
        
        $row = array_merge($row, array(
            $product->get_name(),
            $product->get_slug(),
            $product->get_description(),
            $product->get_short_description(),
            $product->get_sku(),
            $product->get_regular_price(),
            $product->get_sale_price(),
            $product->get_stock_status(),
            $product->get_stock_quantity(),
            implode('|', $categories),
            implode('|', $tags),
            $product->get_type(),
            $product->get_status()
        ));

        if ($include_images) {
            $main_image = wp_get_attachment_image_url($product->get_image_id(), 'full');
            $gallery_ids = $product->get_gallery_image_ids();
            $gallery_urls = array();
            foreach ($gallery_ids as $gallery_id) {
                $gallery_urls[] = wp_get_attachment_image_url($gallery_id, 'full');
            }
            
            $row[] = $main_image ?: '';
            $row[] = implode('|', $gallery_urls);
        }
    }

    // Group pricing data
    $row = array_merge($row, array(
        'Yes', // Has Group Pricing
        $group['name'], // Group Name
        $group['price'], // Group Price
        $group['image'], // Group Image
        $variation['attribute'], // Attribute
        $variation['value'], // Value
        $variation['image'] // Variation Image
    ));

    return $row;
}

function ecv_download_template() {
    $template_type = isset($_POST['template_type']) ? sanitize_text_field($_POST['template_type']) : 'attribute_column';
    
    if ($template_type === 'attribute_column') {
        ecv_download_attribute_column_template();
    } elseif ($template_type === 'group_pricing') {
        ecv_download_group_pricing_template();
    } elseif ($template_type === 'grouped_format') {
        ecv_download_grouped_format_template();
    } elseif ($template_type === 'cross_group_format') {
        ecv_download_cross_group_format_template();
    } elseif ($template_type === 'unified') {
        ecv_download_unified_template();
    } else {
        ecv_download_attribute_column_template();
    }
}

function ecv_download_traditional_template() {
    $headers = ecv_get_csv_headers(true, true, true);
    
    // Sample data for traditional variations
    $sample_data = array(
        array(
            '123', // ID
            'Sample Product', // Name
            'sample-product', // Slug
            'This is a sample product description', // Description
            'Short description', // Short Description
            'SAMPLE-SKU', // SKU
            '99.99', // Regular Price
            '79.99', // Sale Price
            'instock', // Stock Status
            '50', // Stock Quantity
            'Category 1|Category 2', // Categories
            'Tag 1|Tag 2', // Tags
            'simple', // Product Type
            'publish', // Status
            'https://example.com/main-image.jpg', // Main Image URL
            'https://example.com/gallery1.jpg|https://example.com/gallery2.jpg', // Gallery Images
            'Yes', // Has Custom Variants
            'combo-1', // Variant Combination ID
            'SAMPLE-VAR-001', // Variant SKU
            '89.99', // Variant Price
            '69.99', // Variant Sale Price
            '25', // Variant Stock
            'Yes', // Variant Enabled
            'Size:Large|Color:Red', // Variant Attributes
            'Size|Color', // Attribute Names
            'Large|Red', // Attribute Values
            'Size:Premium Sizes,Luxury|Color:Color Options,Luxury', // Attribute Groups
            'Size:Premium|Color:Color Options', // Variant Groups
            'https://example.com/variant-main.jpg', // Variant Main Image
            'https://example.com/size-large.jpg|https://example.com/color-red.jpg', // Variant Attribute Images
            'Gift Wrapping[buttons:Yes:50,No:0];With Installation[dropdown:Professional:300,DIY:0]' // Extra Attributes
        )
    );

    $csv_data = array($headers);
    $csv_data = array_merge($csv_data, $sample_data);

    ecv_send_csv_download($csv_data, 'traditional-variations-template.csv');
}

function ecv_download_group_pricing_template() {
    $headers = ecv_get_csv_headers(true, false, true, true);
    
    // Sample data for group-based pricing
    $sample_data = array(
        array(
            '123', // ID
            'Premium T-Shirt Bundle', // Name
            'premium-tshirt-bundle', // Slug
            'High-quality cotton t-shirt with multiple group options', // Description
            'Premium cotton comfort with group pricing', // Short Description
            'TSHIRT-BUNDLE', // SKU
            '0', // Regular Price (0 for group-based pricing)
            '', // Sale Price
            'instock', // Stock Status
            '100', // Stock Quantity
            'Clothing|T-Shirts', // Categories
            'premium|cotton|bundle', // Tags
            'simple', // Product Type
            'publish', // Status
            'https://example.com/tshirt-bundle-main.jpg', // Main Image URL
            'https://example.com/tshirt-bundle-gallery1.jpg|https://example.com/tshirt-bundle-gallery2.jpg', // Gallery Images
            'Yes', // Has Group Pricing
            'Size Options', // Group Name
            '15.99', // Group Price
            'https://example.com/size-group-image.jpg', // Group Image
            'Size', // Attribute
            'Small', // Value
            'https://example.com/size-small-icon.jpg' // Variation Image
        ),
        array(
            '123', // ID
            'Premium T-Shirt Bundle', // Name
            'premium-tshirt-bundle', // Slug
            'High-quality cotton t-shirt with multiple group options', // Description
            'Premium cotton comfort with group pricing', // Short Description
            'TSHIRT-BUNDLE', // SKU
            '0', // Regular Price (0 for group-based pricing)
            '', // Sale Price
            'instock', // Stock Status
            '100', // Stock Quantity
            'Clothing|T-Shirts', // Categories
            'premium|cotton|bundle', // Tags
            'simple', // Product Type
            'publish', // Status
            'https://example.com/tshirt-bundle-main.jpg', // Main Image URL
            'https://example.com/tshirt-bundle-gallery1.jpg|https://example.com/tshirt-bundle-gallery2.jpg', // Gallery Images
            'Yes', // Has Group Pricing
            'Size Options', // Group Name
            '15.99', // Group Price
            'https://example.com/size-group-image.jpg', // Group Image
            'Size', // Attribute
            'Medium', // Value
            'https://example.com/size-medium-icon.jpg' // Variation Image
        ),
        array(
            '123', // ID
            'Premium T-Shirt Bundle', // Name
            'premium-tshirt-bundle', // Slug
            'High-quality cotton t-shirt with multiple group options', // Description
            'Premium cotton comfort with group pricing', // Short Description
            'TSHIRT-BUNDLE', // SKU
            '0', // Regular Price (0 for group-based pricing)
            '', // Sale Price
            'instock', // Stock Status
            '100', // Stock Quantity
            'Clothing|T-Shirts', // Categories
            'premium|cotton|bundle', // Tags
            'simple', // Product Type
            'publish', // Status
            'https://example.com/tshirt-bundle-main.jpg', // Main Image URL
            'https://example.com/tshirt-bundle-gallery1.jpg|https://example.com/tshirt-bundle-gallery2.jpg', // Gallery Images
            'Yes', // Has Group Pricing
            'Size Options', // Group Name
            '15.99', // Group Price
            'https://example.com/size-group-image.jpg', // Group Image
            'Size', // Attribute
            'Large', // Value
            'https://example.com/size-large-icon.jpg' // Variation Image
        ),
        array(
            '123', // ID
            'Premium T-Shirt Bundle', // Name
            'premium-tshirt-bundle', // Slug
            'High-quality cotton t-shirt with multiple group options', // Description
            'Premium cotton comfort with group pricing', // Short Description
            'TSHIRT-BUNDLE', // SKU
            '0', // Regular Price (0 for group-based pricing)
            '', // Sale Price
            'instock', // Stock Status
            '100', // Stock Quantity
            'Clothing|T-Shirts', // Categories
            'premium|cotton|bundle', // Tags
            'simple', // Product Type
            'publish', // Status
            'https://example.com/tshirt-bundle-main.jpg', // Main Image URL
            'https://example.com/tshirt-bundle-gallery1.jpg|https://example.com/tshirt-bundle-gallery2.jpg', // Gallery Images
            'Yes', // Has Group Pricing
            'Size Options', // Group Name
            '15.99', // Group Price
            'https://example.com/size-group-image.jpg', // Group Image
            'Size', // Attribute
            'X-Large', // Value
            'https://example.com/size-xl-icon.jpg' // Variation Image
        ),
        array(
            '123', // ID
            'Premium T-Shirt Bundle', // Name
            'premium-tshirt-bundle', // Slug
            'High-quality cotton t-shirt with multiple group options', // Description
            'Premium cotton comfort with group pricing', // Short Description
            'TSHIRT-BUNDLE', // SKU
            '0', // Regular Price (0 for group-based pricing)
            '', // Sale Price
            'instock', // Stock Status
            '100', // Stock Quantity
            'Clothing|T-Shirts', // Categories
            'premium|cotton|bundle', // Tags
            'simple', // Product Type
            'publish', // Status
            'https://example.com/tshirt-bundle-main.jpg', // Main Image URL
            'https://example.com/tshirt-bundle-gallery1.jpg|https://example.com/tshirt-bundle-gallery2.jpg', // Gallery Images
            'Yes', // Has Group Pricing
            'Color Options', // Group Name
            '8.99', // Group Price
            'https://example.com/color-group-image.jpg', // Group Image
            'Color', // Attribute
            'Red', // Value
            'https://example.com/color-red-swatch.jpg' // Variation Image
        ),
        array(
            '123', // ID
            'Premium T-Shirt Bundle', // Name
            'premium-tshirt-bundle', // Slug
            'High-quality cotton t-shirt with multiple group options', // Description
            'Premium cotton comfort with group pricing', // Short Description
            'TSHIRT-BUNDLE', // SKU
            '0', // Regular Price (0 for group-based pricing)
            '', // Sale Price
            'instock', // Stock Status
            '100', // Stock Quantity
            'Clothing|T-Shirts', // Categories
            'premium|cotton|bundle', // Tags
            'simple', // Product Type
            'publish', // Status
            'https://example.com/tshirt-bundle-main.jpg', // Main Image URL
            'https://example.com/tshirt-bundle-gallery1.jpg|https://example.com/tshirt-bundle-gallery2.jpg', // Gallery Images
            'Yes', // Has Group Pricing
            'Color Options', // Group Name
            '8.99', // Group Price
            'https://example.com/color-group-image.jpg', // Group Image
            'Color', // Attribute
            'Blue', // Value
            'https://example.com/color-blue-swatch.jpg' // Variation Image
        ),
        array(
            '123', // ID
            'Premium T-Shirt Bundle', // Name
            'premium-tshirt-bundle', // Slug
            'High-quality cotton t-shirt with multiple group options', // Description
            'Premium cotton comfort with group pricing', // Short Description
            'TSHIRT-BUNDLE', // SKU
            '0', // Regular Price (0 for group-based pricing)
            '', // Sale Price
            'instock', // Stock Status
            '100', // Stock Quantity
            'Clothing|T-Shirts', // Categories
            'premium|cotton|bundle', // Tags
            'simple', // Product Type
            'publish', // Status
            'https://example.com/tshirt-bundle-main.jpg', // Main Image URL
            'https://example.com/tshirt-bundle-gallery1.jpg|https://example.com/tshirt-bundle-gallery2.jpg', // Gallery Images
            'Yes', // Has Group Pricing
            'Color Options', // Group Name
            '8.99', // Group Price
            'https://example.com/color-group-image.jpg', // Group Image
            'Color', // Attribute
            'Green', // Value
            'https://example.com/color-green-swatch.jpg' // Variation Image
        ),
        array(
            '123', // ID
            'Premium T-Shirt Bundle', // Name
            'premium-tshirt-bundle', // Slug
            'High-quality cotton t-shirt with multiple group options', // Description
            'Premium cotton comfort with group pricing', // Short Description
            'TSHIRT-BUNDLE', // SKU
            '0', // Regular Price (0 for group-based pricing)
            '', // Sale Price
            'instock', // Stock Status
            '100', // Stock Quantity
            'Clothing|T-Shirts', // Categories
            'premium|cotton|bundle', // Tags
            'simple', // Product Type
            'publish', // Status
            'https://example.com/tshirt-bundle-main.jpg', // Main Image URL
            'https://example.com/tshirt-bundle-gallery1.jpg|https://example.com/tshirt-bundle-gallery2.jpg', // Gallery Images
            'Yes', // Has Group Pricing
            'Color Options', // Group Name
            '8.99', // Group Price
            'https://example.com/color-group-image.jpg', // Group Image
            'Color', // Attribute
            'Black', // Value
            'https://example.com/color-black-swatch.jpg' // Variation Image
        )
    );

    $csv_data = array($headers);
    $csv_data = array_merge($csv_data, $sample_data);

    ecv_send_csv_download($csv_data, 'group-based-pricing-template.csv');
}

function ecv_download_grouped_format_template() {
    $headers = array(
        'ID', 'Name', 'SKU', 'Description', 'Attribute Groups', 'Group Names', 'Group Values', 'Group Image', 'Group Price', 'Group Description'
    );
    
    // Sample data for grouped format
    $sample_data = array(
        array(
            '123', // ID
            'Premium T-Shirt Bundle', // Name
            'TSHIRT-BUNDLE', // SKU
            'High-quality cotton t-shirt with multiple options', // Description
            'finish|colour', // Attribute Groups
            'G1,C1', // Group Names
            'Matte,Glossy|Red,Blue,Green,Black', // Group Values
            'https://example.com/finish-group.jpg', // Group Image
            '15.99', // Group Price
            'Premium finish options' // Group Description
        ),
        array(
            '123', // ID
            'Premium T-Shirt Bundle', // Name
            'TSHIRT-BUNDLE', // SKU
            'High-quality cotton t-shirt with multiple options', // Description
            'finish|colour', // Attribute Groups
            'G2,C2', // Group Names
            'Textured,Smooth|White,Yellow,Purple,Orange', // Group Values
            'https://example.com/finish-group2.jpg', // Group Image
            '12.99', // Group Price
            'Standard finish options' // Group Description
        ),
        array(
            '124', // ID
            'Deluxe Phone Case', // Name
            'PHONE-CASE', // SKU
            'Protective phone case with style options', // Description
            'material|pattern', // Attribute Groups
            'Basic,Premium', // Group Names
            'Plastic,Leather|Solid,Striped,Polka Dot', // Group Values
            'https://example.com/material-group.jpg', // Group Image
            '8.99', // Group Price
            'Basic material options' // Group Description
        ),
        array(
            '124', // ID
            'Deluxe Phone Case', // Name
            'PHONE-CASE', // SKU
            'Protective phone case with style options', // Description
            'material|pattern', // Attribute Groups
            'Luxury,Designer', // Group Names
            'Wood,Glass|Geometric,Floral,Abstract', // Group Values
            'https://example.com/luxury-group.jpg', // Group Image
            '25.99', // Group Price
            'Premium material options' // Group Description
        )
    );

    $csv_data = array($headers);
    $csv_data = array_merge($csv_data, $sample_data);

    ecv_send_csv_download($csv_data, 'grouped-format-template.csv');
}

function ecv_download_cross_group_format_template() {
    $headers = array(
        'ID', 'Name', 'SKU', 'Description', 'Groups Definition', 'Combination Name', 'Combination Price', 'Combination Sale Price', 'Combination Stock', 'Combination Image', 'Combination Description', 'Main Product Image', 'Group Button Images', 'Extra Attributes'
    );
    
    // Sample data for cross-group format - this is the template data from the created file
    $sample_data = array(
        array(
            '101', // ID
            'Premium T-Shirt with Cross Groups', // Name
            'TSHIRT-CROSS', // SKU
            'A t-shirt with cross-group combinations for finish and colour', // Description
            'finish:G1=Matte,Glossy|G2=Textured,Smooth;colour:C1=Red,Blue,Green,Black|C2=White,Yellow,Purple,Orange', // Groups Definition
            'G1+C1', // Combination Name
            '25.00', // Combination Price
            '22.50', // Combination Sale Price
            '50', // Combination Stock
            'https://example.com/g1c1.jpg', // Combination Image
            'Matte finish with bright colours', // Combination Description
            'https://example.com/main-product.jpg', // Main Product Image
            'finish:G1=https://example.com/g1-icon.jpg|finish:G2=https://example.com/g2-icon.jpg|colour:C1=https://example.com/c1-icon.jpg|colour:C2=https://example.com/c2-icon.jpg', // Group Button Images
            'Gift Box[buttons:Yes:10,No:0];Express Shipping[dropdown:1-Day:25,2-Day:15,Standard:0]' // Extra Attributes
        ),
        array(
            '101', // ID
            'Premium T-Shirt with Cross Groups', // Name
            'TSHIRT-CROSS', // SKU
            'A t-shirt with cross-group combinations for finish and colour', // Description
            'finish:G1=Matte,Glossy|G2=Textured,Smooth;colour:C1=Red,Blue,Green,Black|C2=White,Yellow,Purple,Orange', // Groups Definition
            'G1+C2', // Combination Name
            '28.00', // Combination Price
            '25.20', // Combination Sale Price
            '30', // Combination Stock
            'https://example.com/g1c2.jpg', // Combination Image
            'Matte finish with pastel colours', // Combination Description
            'https://example.com/main-product.jpg', // Main Product Image
            'finish:G1=https://example.com/g1-icon.jpg|finish:G2=https://example.com/g2-icon.jpg|colour:C1=https://example.com/c1-icon.jpg|colour:C2=https://example.com/c2-icon.jpg', // Group Button Images
            'Gift Box[buttons:Yes:10,No:0];Express Shipping[dropdown:1-Day:25,2-Day:15,Standard:0]' // Extra Attributes
        ),
        array(
            '101', // ID
            'Premium T-Shirt with Cross Groups', // Name
            'TSHIRT-CROSS', // SKU
            'A t-shirt with cross-group combinations for finish and colour', // Description
            'finish:G1=Matte,Glossy|G2=Textured,Smooth;colour:C1=Red,Blue,Green,Black|C2=White,Yellow,Purple,Orange', // Groups Definition
            'G2+C1', // Combination Name
            '30.00', // Combination Price
            '27.00', // Combination Sale Price
            '40', // Combination Stock
            'https://example.com/g2c1.jpg', // Combination Image
            'Textured finish with bright colours', // Combination Description
            'https://example.com/main-product.jpg', // Main Product Image
            'finish:G1=https://example.com/g1-icon.jpg|finish:G2=https://example.com/g2-icon.jpg|colour:C1=https://example.com/c1-icon.jpg|colour:C2=https://example.com/c2-icon.jpg', // Group Button Images
            'Gift Box[buttons:Yes:10,No:0];Express Shipping[dropdown:1-Day:25,2-Day:15,Standard:0]' // Extra Attributes
        ),
        array(
            '101', // ID
            'Premium T-Shirt with Cross Groups', // Name
            'TSHIRT-CROSS', // SKU
            'A t-shirt with cross-group combinations for finish and colour', // Description
            'finish:G1=Matte,Glossy|G2=Textured,Smooth;colour:C1=Red,Blue,Green,Black|C2=White,Yellow,Purple,Orange', // Groups Definition
            'G2+C2', // Combination Name
            '33.00', // Combination Price
            '29.70', // Combination Sale Price
            '20', // Combination Stock
            'https://example.com/g2c2.jpg', // Combination Image
            'Textured finish with pastel colours', // Combination Description
            'https://example.com/main-product.jpg', // Main Product Image
            'finish:G1=https://example.com/g1-icon.jpg|finish:G2=https://example.com/g2-icon.jpg|colour:C1=https://example.com/c1-icon.jpg|colour:C2=https://example.com/c2-icon.jpg', // Group Button Images
            'Gift Box[buttons:Yes:10,No:0];Express Shipping[dropdown:1-Day:25,2-Day:15,Standard:0]' // Extra Attributes
        )
    );
    
    $csv_data = array($headers);
    $csv_data = array_merge($csv_data, $sample_data);
    
    ecv_send_csv_download($csv_data, 'cross-group-format-template.csv');
}

function ecv_download_unified_template() {
    // Unified format headers - combines all necessary fields from both formats
    $headers = array(
        // Basic product data
        'ID', 'Name', 'SKU', 'Description', 'Short Description',
        'Regular Price', 'Sale Price', 'Stock Status', 'Stock Quantity', 
        'Categories', 'Tags', 'Status', 'Main Product Image', 'Gallery Images',
        
        // Format control column - THIS IS THE KEY!
        'Enable Cross Group',
        
        // Cross-group specific columns (used when Enable Cross Group = Yes)
        'Groups Definition', 'Combination Name', 'Combination Price', 
        'Combination Sale Price', 'Combination Stock', 'Combination Image', 
        'Combination Description', 'Group Button Images',
        
        // Combinational specific columns (used when Enable Cross Group = No)
        'Has Custom Variants', 'Variant Combination ID', 'Variant SKU',
        'Variant Price', 'Variant Sale Price', 'Variant Stock', 'Variant Enabled',
        'Variant Attributes', 'Attribute Names', 'Attribute Values',
        'Attribute Groups', 'Variant Groups', 'Variant Main Image', 'Variant Attribute Images',
        
        // Extra attributes (works with both formats)
        'Extra Attributes'
    );
    
    // Sample data demonstrating both formats in one CSV
    $sample_data = array(
        // Example 1: Cross-group format (Enable Cross Group = Yes)
        array(
            '101', // ID
            'Premium T-Shirt with Cross Groups', // Name
            'TSHIRT-CROSS', // SKU
            'A t-shirt with cross-group combinations for finish and colour', // Description
            'Premium cotton with advanced group options', // Short Description
            '0', // Regular Price (will be overridden by combination pricing)
            '', // Sale Price
            'instock', // Stock Status
            '', // Stock Quantity (managed per combination)
            'Clothing|T-Shirts', // Categories
            'premium|cross-group', // Tags
            'publish', // Status
            'https://example.com/tshirt-main.jpg', // Main Product Image
            'https://example.com/gallery1.jpg|https://example.com/gallery2.jpg', // Gallery Images
            
            // FORMAT CONTROL - Set to "Yes" to enable cross-group mode
            'Yes', // Enable Cross Group
            
            // Cross-group specific data (filled when Enable Cross Group = Yes)
            'finish:G1=Matte,Glossy|G2=Textured,Smooth;colour:C1=Red,Blue,Green,Black|C2=White,Yellow,Purple,Orange', // Groups Definition
            'G1+C1', // Combination Name
            '25.00', // Combination Price
            '22.50', // Combination Sale Price
            '50', // Combination Stock
            'https://example.com/g1c1.jpg', // Combination Image
            'Matte finish with bright colours', // Combination Description
            'finish:G1=https://example.com/g1-icon.jpg|finish:G2=https://example.com/g2-icon.jpg|colour:C1=https://example.com/c1-icon.jpg|colour:C2=https://example.com/c2-icon.jpg', // Group Button Images
            
            // Combinational specific data (empty when Enable Cross Group = Yes)
            '', '', '', '', '', '', '', '', '', '', '', '', '', '',
            
            // Extra Attributes (works with both formats)
            'Gift Box[buttons:Yes:10,No:0];Express Shipping[dropdown:1-Day:25,2-Day:15,Standard:0]' // Extra Attributes
        ),
        array(
            '101', // ID
            'Premium T-Shirt with Cross Groups', // Name
            'TSHIRT-CROSS', // SKU
            'A t-shirt with cross-group combinations for finish and colour', // Description
            'Premium cotton with advanced group options', // Short Description
            '0', // Regular Price
            '', // Sale Price
            'instock', // Stock Status
            '', // Stock Quantity
            'Clothing|T-Shirts', // Categories
            'premium|cross-group', // Tags
            'publish', // Status
            'https://example.com/tshirt-main.jpg', // Main Product Image
            'https://example.com/gallery1.jpg|https://example.com/gallery2.jpg', // Gallery Images
            
            'Yes', // Enable Cross Group
            
            // Second cross-group combination
            'finish:G1=Matte,Glossy|G2=Textured,Smooth;colour:C1=Red,Blue,Green,Black|C2=White,Yellow,Purple,Orange', // Groups Definition
            'G1+C2', // Combination Name
            '28.00', // Combination Price
            '25.20', // Combination Sale Price
            '30', // Combination Stock
            'https://example.com/g1c2.jpg', // Combination Image
            'Matte finish with pastel colours', // Combination Description
            'finish:G1=https://example.com/g1-icon.jpg|finish:G2=https://example.com/g2-icon.jpg|colour:C1=https://example.com/c1-icon.jpg|colour:C2=https://example.com/c2-icon.jpg', // Group Button Images
            
            '', '', '', '', '', '', '', '', '', '', '', '', '', '',
            
            'Gift Box[buttons:Yes:10,No:0];Express Shipping[dropdown:1-Day:25,2-Day:15,Standard:0]' // Extra Attributes
        ),
        
        // Example 2: Combinational format (Enable Cross Group = No)
        array(
            '102', // ID
            'Classic Product with Traditional Variants', // Name
            'CLASSIC-PROD', // SKU
            'A product with traditional combinational variations', // Description
            'Classic product with individual variant pricing', // Short Description
            '99.99', // Regular Price
            '79.99', // Sale Price
            'instock', // Stock Status
            '100', // Stock Quantity
            'Classic|Products', // Categories
            'traditional|variants', // Tags
            'publish', // Status
            'https://example.com/classic-main.jpg', // Main Product Image
            'https://example.com/classic-gallery1.jpg', // Gallery Images
            
            // FORMAT CONTROL - Set to "No" to use combinational mode
            'No', // Enable Cross Group
            
            // Cross-group specific data (empty when Enable Cross Group = No)
            '', '', '', '', '', '', '', '',
            
            // Combinational specific data (filled when Enable Cross Group = No)
            'Yes', // Has Custom Variants
            'combo-1', // Variant Combination ID
            'CLASSIC-VAR-001', // Variant SKU
            '89.99', // Variant Price
            '69.99', // Variant Sale Price
            '25', // Variant Stock
            'Yes', // Variant Enabled
            'Size:Large|Color:Red', // Variant Attributes
            'Size|Color', // Attribute Names
            'Large|Red', // Attribute Values
            'Size:Premium Sizes|Color:Color Options', // Attribute Groups
            'Size:Premium|Color:Standard', // Variant Groups
            'https://example.com/variant-main.jpg', // Variant Main Image
            'https://example.com/size-large.jpg|https://example.com/color-red.jpg', // Variant Attribute Images
            
            'Assembly Service[radio:Professional:150,DIY:0]' // Extra Attributes
        ),
        array(
            '102', // ID
            'Classic Product with Traditional Variants', // Name
            'CLASSIC-PROD', // SKU
            'A product with traditional combinational variations', // Description
            'Classic product with individual variant pricing', // Short Description
            '99.99', // Regular Price
            '79.99', // Sale Price
            'instock', // Stock Status
            '100', // Stock Quantity
            'Classic|Products', // Categories
            'traditional|variants', // Tags
            'publish', // Status
            'https://example.com/classic-main.jpg', // Main Product Image
            'https://example.com/classic-gallery1.jpg', // Gallery Images
            
            'No', // Enable Cross Group
            
            '', '', '', '', '', '', '', '',
            
            // Second combinational variant
            'Yes', // Has Custom Variants
            'combo-2', // Variant Combination ID
            'CLASSIC-VAR-002', // Variant SKU
            '94.99', // Variant Price
            '74.99', // Variant Sale Price
            '15', // Variant Stock
            'Yes', // Variant Enabled
            'Size:Medium|Color:Blue', // Variant Attributes
            'Size|Color', // Attribute Names
            'Medium|Blue', // Attribute Values
            'Size:Standard Sizes|Color:Color Options', // Attribute Groups
            'Size:Standard|Color:Standard', // Variant Groups
            'https://example.com/variant-main2.jpg', // Variant Main Image
            'https://example.com/size-medium.jpg|https://example.com/color-blue.jpg', // Variant Attribute Images
            
            'Assembly Service[radio:Professional:150,DIY:0]' // Extra Attributes
        )
    );
    
    $csv_data = array($headers);
    $csv_data = array_merge($csv_data, $sample_data);
    
    ecv_send_csv_download($csv_data, 'unified-variations-template.csv');
}

function ecv_download_attribute_column_template() {
    // Attribute-column format headers - the current recommended format
    $headers = array(
        // Basic product data
        'ID', 'Name', 'SKU', 'Description', 'Short Description',
        'Regular Price', 'Sale Price', 'Stock Status', 'Stock Quantity', 
        'Categories', 'Tags', 'Status', 'Main Product Image', 'Gallery Images',
        
        // Format control
        'Enable Cross Group',
        
        // Attribute columns (dynamic - add as many as needed)
        'Attribute:Size|dropdown',
        'Size:Button Images',
        'Size:Tooltip',
        'Attribute:Finish|button',
        'Finish:Button Images',
        'Finish:Tooltip',
        'Attribute:Lock|button',
        'Lock:Button Images',
        'Lock:Tooltip',
        'Attribute:Door Thickness',
        'Door Thickness:Button Images',
        'Door Thickness:Tooltip',
        
        // Combination data
        'Combination Price', 'Combination Sale Price', 'Combination Stock', 
        'Combination Image', 'Combination Description',
        
        // Extra attributes (optional)
        'Extra Attributes'
    );
    
    // Sample data demonstrating the attribute-column format
    $sample_data = array(
        // Example 1: Size 272 + Signature Finish group (3 finish options) + Handle Pair Only
        array(
            '101', // ID
            'Premium Door Handle', // Name
            'DH001', // SKU
            'Professional door handle with multiple finish options', // Description
            'Premium quality door handle', // Short Description
            '0', // Regular Price
            '', // Sale Price
            'instock', // Stock Status
            '', // Stock Quantity
            'Hardware|Doors', // Categories
            'premium|luxury', // Tags
            'publish', // Status
            'https://example.com/main.jpg', // Main Product Image
            'https://example.com/g1.jpg|https://example.com/g2.jpg', // Gallery Images
            'Yes', // Enable Cross Group
            
            // Attributes
            '272', // Attribute:Size|dropdown (simple value)
            'https://example.com/size-272.png', // Size:Button Images (one image per value)
            'You have selected size 272', // Size:Tooltip (tooltip for this value)
            'Signature Finish=Antique Brass, Gold Satin, Antique Brass Matte', // Attribute:Finish|button (grouped values)
            'https://example.com/brass.png|https://example.com/gold.png|https://example.com/matte.png', // Finish:Button Images (one per value in order)
            '', // Finish:Tooltip (empty - no tooltip for button types)
            'Handle Pair Only', // Attribute:Lock|button
            '', // Lock:Button Images
            '', // Lock:Tooltip (empty - no tooltip for button types)
            'none', // Attribute:Door Thickness (none means not applicable)
            '', // Door Thickness:Button Images
            '', // Door Thickness:Tooltip
            
            // Combination data
            '500', // Combination Price
            '150', // Combination Sale Price
            '50', // Combination Stock
            'https://example.com/combo1.jpg', // Combination Image
            'Size 272 with Signature Finish' // Combination Description
        ),
        
        // Example 2: Size 272 + Speciality Finishes group (5 finish options) + Handle Pair Only
        array(
            '101', // ID
            'Premium Door Handle', // Name
            'DH001', // SKU
            'Professional door handle with multiple finish options', // Description
            'Premium quality door handle', // Short Description
            '0', // Regular Price
            '', // Sale Price
            'instock', // Stock Status
            '', // Stock Quantity
            'Hardware|Doors', // Categories
            'premium|luxury', // Tags
            'publish', // Status
            'https://example.com/main.jpg', // Main Product Image
            'https://example.com/g1.jpg|https://example.com/g2.jpg', // Gallery Images
            'Yes', // Enable Cross Group
            
            // Attributes
            '272', // Attribute:Size|dropdown
            'https://example.com/size-272.png', // Size:Button Images
            'You have selected size 272', // Size:Tooltip
            'Speciality Finishes=Antique Gold, Antique copper, Polished Brass, Burnished Brass, Antique Silver', // Attribute:Finish|button
            'https://example.com/gold.png|https://example.com/copper.png|https://example.com/brass.png|https://example.com/burnished.png|https://example.com/silver.png', // Finish:Button Images (5 URLs for 5 values)
            '', // Finish:Tooltip
            'Handle Pair Only', // Attribute:Lock|button
            '', // Lock:Button Images
            '', // Lock:Tooltip
            'none', // Attribute:Door Thickness
            '', // Door Thickness:Button Images
            '', // Door Thickness:Tooltip
            
            // Combination data
            '600', // Combination Price
            '300', // Combination Sale Price
            '30', // Combination Stock
            'https://example.com/combo2.jpg', // Combination Image
            'Size 272 with Speciality Finishes' // Combination Description
        ),
        
        // Example 3: Size 330 + Signature Finish + Handle Pair Only
        array(
            '101', // ID
            'Premium Door Handle', // Name
            'DH001', // SKU
            'Professional door handle with multiple finish options', // Description
            'Premium quality door handle', // Short Description
            '0', // Regular Price
            '', // Sale Price
            'instock', // Stock Status
            '', // Stock Quantity
            'Hardware|Doors', // Categories
            'premium|luxury', // Tags
            'publish', // Status
            'https://example.com/main.jpg', // Main Product Image
            'https://example.com/g1.jpg|https://example.com/g2.jpg', // Gallery Images
            'Yes', // Enable Cross Group
            
            // Attributes
            '330', // Attribute:Size|dropdown (different value)
            'https://example.com/size-330.png', // Size:Button Images (different image for size 330)
            'You have selected size 330', // Size:Tooltip (different tooltip for size 330)
            'Signature Finish=Antique Brass, Gold Satin, Antique Brass Matte', // Attribute:Finish|button
            'https://example.com/brass.png|https://example.com/gold.png|https://example.com/matte.png', // Finish:Button Images
            '', // Finish:Tooltip
            'Handle Pair Only', // Attribute:Lock|button
            '', // Lock:Button Images
            '', // Lock:Tooltip
            'none', // Attribute:Door Thickness
            '', // Door Thickness:Button Images
            '', // Door Thickness:Tooltip
            
            // Combination data
            '6000', // Combination Price
            '3000', // Combination Sale Price
            '50', // Combination Stock
            'https://example.com/combo3.jpg', // Combination Image
            'Size 330 with Signature Finish' // Combination Description
        ),
        
        // Example 4: Size 272 + Signature Finish + With Lock + Door Thickness (all attributes)
        array(
            '101', // ID
            'Premium Door Handle', // Name
            'DH001', // SKU
            'Professional door handle with multiple finish options', // Description
            'Premium quality door handle', // Short Description
            '0', // Regular Price
            '', // Sale Price
            'instock', // Stock Status
            '', // Stock Quantity
            'Hardware|Doors', // Categories
            'premium|luxury', // Tags
            'publish', // Status
            'https://example.com/main.jpg', // Main Product Image
            'https://example.com/g1.jpg|https://example.com/g2.jpg', // Gallery Images
            'Yes', // Enable Cross Group
            
            // Attributes
            '272', // Attribute:Size|dropdown
            'https://example.com/size-272.png', // Size:Button Images
            'You have selected size 272', // Size:Tooltip
            'Signature Finish=Antique Brass, Gold Satin, Antique Brass Matte', // Attribute:Finish|button
            'https://example.com/brass.png|https://example.com/gold.png|https://example.com/matte.png', // Finish:Button Images
            '', // Finish:Tooltip
            'With 3 Pin Lock Body', // Attribute:Lock|button (different lock option)
            '', // Lock:Button Images
            '', // Lock:Tooltip
            'Upto 39mm', // Attribute:Door Thickness (actual value, not "none")
            '', // Door Thickness:Button Images
            '', // Door Thickness:Tooltip
            
            // Combination data
            '650', // Combination Price
            '200', // Combination Sale Price
            '50', // Combination Stock
            'https://example.com/combo4.jpg', // Combination Image
            'Size 272 with Signature Finish + Lock + Door Thickness' // Combination Description
        )
    );
    
    $csv_data = array($headers);
    $csv_data = array_merge($csv_data, $sample_data);
    
    ecv_send_csv_download($csv_data, 'attribute-column-template.csv');
}

function ecv_generate_export_csv_attribute_column($product_ids, $include_product_data, $include_images) {
    $csv_rows = array();
    
    // Collect all products and their data first to determine unique attributes
    $products_data = array();
    $all_attributes = array();
    
    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if (!$product) continue;
        
        $cross_group_data = get_post_meta($product_id, '_ecv_cross_group_data', true) ?: array();
        $variations_data = ecv_get_variations_data($product_id);
        $combinations_data = ecv_get_combinations_data($product_id);
        $value_button_images = get_post_meta($product_id, '_ecv_value_button_images', true) ?: array();
        $attribute_display_types = get_post_meta($product_id, '_ecv_attribute_display_types', true) ?: array();
        $dropdown_tooltips = get_post_meta($product_id, '_ecv_dropdown_tooltips', true) ?: array();
        
        // Collect unique attributes from variations_data
        if (!empty($variations_data)) {
            foreach ($variations_data as $attr) {
                $attr_name = $attr['name'];
                if (!in_array($attr_name, $all_attributes)) {
                    $all_attributes[] = $attr_name;
                }
            }
        }
        
        $products_data[] = array(
            'product' => $product,
            'product_id' => $product_id,
            'cross_group_data' => $cross_group_data,
            'variations_data' => $variations_data,
            'combinations_data' => $combinations_data,
            'value_button_images' => $value_button_images,
            'attribute_display_types' => $attribute_display_types,
            'dropdown_tooltips' => $dropdown_tooltips
        );
    }
    
    // Build headers dynamically based on all attributes found
    $headers = array();
    
    // Basic product data
    if ($include_product_data) {
        $headers = array_merge($headers, array(
            'ID', 'Name', 'SKU', 'Description', 'Short Description',
            'Regular Price', 'Sale Price', 'Stock Status', 'Stock Quantity',
            'Categories', 'Tags', 'Status'
        ));
        
        if ($include_images) {
            $headers[] = 'Main Product Image';
            $headers[] = 'Gallery Images';
        }
    } else {
        $headers[] = 'ID';
    }
    
    // Enable Cross Group
    $headers[] = 'Enable Cross Group';
    
    // Add attribute columns dynamically
    foreach ($all_attributes as $attr_name) {
        $headers[] = 'Attribute:' . $attr_name;
        $headers[] = $attr_name . ':Button Images';
        // Add tooltip column for all attributes (will be filled only for dropdown types)
        $headers[] = $attr_name . ':Tooltip';
    }
    
    // Combination data
    $headers = array_merge($headers, array(
        'Combination Price', 'Combination Sale Price', 'Combination Stock',
        'Combination Image', 'Combination Description'
    ));
    
    $csv_rows[] = $headers;
    
    // Generate rows for each product
    foreach ($products_data as $data) {
        $product = $data['product'];
        $product_id = $data['product_id'];
        $variations_data = $data['variations_data'];
        $combinations_data = $data['combinations_data'];
        $value_button_images = $data['value_button_images'];
        $attribute_display_types = $data['attribute_display_types'];
        $dropdown_tooltips = $data['dropdown_tooltips'];
        
        if (empty($combinations_data)) {
            // No combinations - export basic product info
            $row = ecv_build_attribute_column_row_basic($product, $all_attributes, $include_product_data, $include_images);
            $csv_rows[] = $row;
            continue;
        }
        
        // Export one row per combination
        foreach ($combinations_data as $combination) {
            $row = ecv_build_attribute_column_row(
                $product,
                $variations_data,
                $combination,
                $all_attributes,
                $value_button_images,
                $attribute_display_types,
                $dropdown_tooltips,
                $include_product_data,
                $include_images
            );
            $csv_rows[] = $row;
        }
    }
    
    return $csv_rows;
}

function ecv_build_attribute_column_row($product, $variations_data, $combination, $all_attributes, $value_button_images, $attribute_display_types, $dropdown_tooltips, $include_product_data, $include_images) {
    $row = array();
    
    // Basic product data
    if ($include_product_data) {
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
        $tags = wp_get_post_terms($product->get_id(), 'product_tag', array('fields' => 'names'));
        
        $row = array_merge($row, array(
            $product->get_id(),
            $product->get_name(),
            $product->get_sku(),
            $product->get_description(),
            $product->get_short_description(),
            $product->get_regular_price(),
            $product->get_sale_price(),
            $product->get_stock_status(),
            $product->get_stock_quantity(),
            implode('|', $categories),
            implode('|', $tags),
            $product->get_status()
        ));
        
        if ($include_images) {
            $main_image = wp_get_attachment_image_url($product->get_image_id(), 'full');
            $gallery_ids = $product->get_gallery_image_ids();
            $gallery_urls = array();
            foreach ($gallery_ids as $gallery_id) {
                $gallery_urls[] = wp_get_attachment_image_url($gallery_id, 'full');
            }
            $row[] = $main_image ?: '';
            $row[] = implode('|', $gallery_urls);
        }
    } else {
        $row[] = $product->get_id();
    }
    
    // Enable Cross Group
    $row[] = 'Yes';
    
    // Build attribute values and images from combination
    $combo_variants = !empty($combination['variants']) ? $combination['variants'] : array();
    
    foreach ($all_attributes as $attr_name) {
        // Find this attribute in variations_data
        $attr_data = null;
        foreach ($variations_data as $attr) {
            if ($attr['name'] === $attr_name) {
                $attr_data = $attr;
                break;
            }
        }
        
        // Find variants for this attribute in the combination
        $attr_variants_in_combo = array();
        $attr_group = '';
        foreach ($combo_variants as $variant) {
            if ($variant['attribute'] === $attr_name) {
                $attr_variants_in_combo[] = $variant['name'];
                $attr_group = !empty($variant['group']) ? $variant['group'] : '';
            }
        }
        
        // Format attribute value
        if (empty($attr_variants_in_combo)) {
            $row[] = 'none';
            $row[] = '';  // Button images
            $row[] = '';  // Tooltip
        } else {
            // Check if it's a grouped attribute
            $has_groups = false;
            if ($attr_data && !empty($attr_data['variants'])) {
                foreach ($attr_data['variants'] as $v) {
                    if (!empty($v['group'])) {
                        $has_groups = true;
                        break;
                    }
                }
            }
            
            if ($has_groups && !empty($attr_group)) {
                // Grouped format: GroupName=Value1, Value2, Value3
                $row[] = $attr_group . '=' . implode(', ', $attr_variants_in_combo);
            } else {
                // Simple format: just the value
                $row[] = $attr_variants_in_combo[0];
            }
            
            // Button images
            $button_images = array();
            foreach ($attr_variants_in_combo as $variant_name) {
                $img = '';
                if (!empty($value_button_images[$attr_name])) {
                    // Check with group key
                    if (!empty($attr_group) && !empty($value_button_images[$attr_name][$attr_group][$variant_name])) {
                        $img = $value_button_images[$attr_name][$attr_group][$variant_name];
                    }
                    // Check with empty group key (simple attributes)
                    elseif (!empty($value_button_images[$attr_name][''][$variant_name])) {
                        $img = $value_button_images[$attr_name][''][$variant_name];
                    }
                }
                $button_images[] = $img;
            }
            $row[] = implode('|', $button_images);
            
            // Tooltips (only for dropdown attributes)
            $attr_display_type = isset($attribute_display_types[strtolower($attr_name)]) ? $attribute_display_types[strtolower($attr_name)] : 'buttons';
            if ($attr_display_type === 'dropdown') {
                $tooltips = array();
                foreach ($attr_variants_in_combo as $variant_name) {
                    $tooltip = '';
                    if (!empty($dropdown_tooltips[$attr_name])) {
                        // Check with group key
                        if (!empty($attr_group) && !empty($dropdown_tooltips[$attr_name][$attr_group][$variant_name])) {
                            $tooltip = $dropdown_tooltips[$attr_name][$attr_group][$variant_name];
                        }
                        // Check with empty group key (simple attributes)
                        elseif (!empty($dropdown_tooltips[$attr_name][''][$variant_name])) {
                            $tooltip = $dropdown_tooltips[$attr_name][''][$variant_name];
                        }
                    }
                    $tooltips[] = $tooltip;
                }
                $row[] = implode('|', $tooltips);
            } else {
                // Not a dropdown, empty tooltip column
                $row[] = '';
            }
        }
    }
    
    // Combination data
    $row[] = !empty($combination['price']) ? $combination['price'] : '';
    $row[] = !empty($combination['sale_price']) ? $combination['sale_price'] : '';
    $row[] = !empty($combination['stock']) ? $combination['stock'] : '';
    $row[] = !empty($combination['main_image_url']) ? $combination['main_image_url'] : '';
    $row[] = '';
    
    return $row;
}

function ecv_build_attribute_column_row_basic($product, $all_attributes, $include_product_data, $include_images) {
    $row = array();
    
    // Basic product data
    if ($include_product_data) {
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
        $tags = wp_get_post_terms($product->get_id(), 'product_tag', array('fields' => 'names'));
        
        $row = array_merge($row, array(
            $product->get_id(),
            $product->get_name(),
            $product->get_sku(),
            $product->get_description(),
            $product->get_short_description(),
            $product->get_regular_price(),
            $product->get_sale_price(),
            $product->get_stock_status(),
            $product->get_stock_quantity(),
            implode('|', $categories),
            implode('|', $tags),
            $product->get_status()
        ));
        
        if ($include_images) {
            $main_image = wp_get_attachment_image_url($product->get_image_id(), 'full');
            $gallery_ids = $product->get_gallery_image_ids();
            $gallery_urls = array();
            foreach ($gallery_ids as $gallery_id) {
                $gallery_urls[] = wp_get_attachment_image_url($gallery_id, 'full');
            }
            $row[] = $main_image ?: '';
            $row[] = implode('|', $gallery_urls);
        }
    } else {
        $row[] = $product->get_id();
    }
    
    // Enable Cross Group
    $row[] = 'No';
    
    // Empty attributes
    foreach ($all_attributes as $attr_name) {
        $row[] = '';  // Attribute value
        $row[] = '';  // Button images
        $row[] = '';  // Tooltip
    }
    
    // Empty combination data
    $row[] = '';
    $row[] = '';
    $row[] = '';
    $row[] = '';
    $row[] = '';
    
    return $row;
}
