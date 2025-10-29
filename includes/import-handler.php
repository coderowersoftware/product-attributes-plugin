<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function ecv_handle_import() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        wp_die(__('Please upload a valid CSV file.', 'exp-custom-variations'));
    }

    $update_existing = isset($_POST['update_existing']);
    $create_new = isset($_POST['create_new']);
    $dry_run = isset($_POST['dry_run']);
    $import_grouped_format = isset($_POST['import_grouped_format']);
    $import_cross_group_format = isset($_POST['import_cross_group_format']);

    error_log('ECV Import Debug: POST data: ' . print_r($_POST, true));
    error_log('ECV Import Debug: FILES data: ' . print_r($_FILES, true));
    error_log('ECV Import Debug: Update existing: ' . ($update_existing ? 'Yes' : 'No'));
    error_log('ECV Import Debug: Create new: ' . ($create_new ? 'Yes' : 'No'));
    error_log('ECV Import Debug: Dry run: ' . ($dry_run ? 'Yes' : 'No'));
    error_log('ECV Import Debug: Import grouped format: ' . ($import_grouped_format ? 'Yes' : 'No'));
    error_log('ECV Import Debug: Import cross-group format: ' . ($import_cross_group_format ? 'Yes' : 'No'));
    error_log('ECV Import Debug: File path: ' . $_FILES['import_file']['tmp_name']);
    error_log('ECV Import Debug: File exists: ' . (file_exists($_FILES['import_file']['tmp_name']) ? 'Yes' : 'No'));

    // Process the CSV file
    $results = ecv_process_import_csv($_FILES['import_file']['tmp_name'], $update_existing, $create_new, $dry_run, $import_grouped_format, $import_cross_group_format);
    
    // Display results
    ecv_display_import_results($results, $dry_run);
}

function ecv_process_import_csv($file_path, $update_existing, $create_new, $dry_run, $import_grouped_format = false, $import_cross_group_format = false) {
    error_log('ECV CSV Processing: Starting with grouped format: ' . ($import_grouped_format ? 'Yes' : 'No') . ', cross-group format: ' . ($import_cross_group_format ? 'Yes' : 'No'));
    
    $results = array(
        'processed' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => array(),
        'warnings' => array(),
        'success' => array(),
        'extra_fields_imported' => 0
    );
    
    // Check if this is the new unified format
    $is_unified_format = false;

    error_log('ECV CSV Processing: Attempting to open file: ' . $file_path);
    
    if (($handle = fopen($file_path, 'r')) !== FALSE) {
        error_log('ECV CSV Processing: File opened successfully');
        
        // Get headers from first line
        $headers = fgetcsv($handle);
        $row_number = 1;
        
        error_log('ECV CSV Processing: Headers read: ' . print_r($headers, true));

        // Map headers to indices
        $header_map = ecv_map_csv_headers($headers);
        error_log('ECV CSV Processing: Header map created: ' . print_r($header_map, true));
        
        // Check if this is the unified format (look for 'Enable Cross Group' column)
        $is_unified_format = isset($header_map['enable cross group']);
        if ($is_unified_format) {
            error_log('ECV CSV Processing: Detected unified format - Enable Cross Group column found');
        }
        
        // Group rows by product ID/SKU to handle multiple variations per product
        $product_groups = array();
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $row_number++;
            $results['processed']++;
            
            error_log('ECV CSV Processing: Row ' . $row_number . ' data: ' . print_r($data, true));
            
            $product_id = ecv_get_csv_value($data, $header_map, 'id');
            $product_sku = ecv_get_csv_value($data, $header_map, 'sku');
            
            error_log('ECV CSV Processing: Row ' . $row_number . ' - Product ID: ' . $product_id . ', SKU: ' . $product_sku);
            
            // Use product name + SKU as primary key for grouping (since we can't rely on non-existent IDs)
            $product_name = ecv_get_csv_value($data, $header_map, 'name');
            
            // Create a group key based on identifiable information
            $group_key = '';
            if (!empty($product_id) && is_numeric($product_id)) {
                // Check if this ID actually exists
                $existing = wc_get_product($product_id);
                if ($existing) {
                    $group_key = 'existing_id_' . $product_id;
                } else {
                    // Product ID doesn't exist, group by name+sku instead
                    $group_key = 'new_' . sanitize_key($product_name . '_' . $product_sku);
                }
            } elseif (!empty($product_sku)) {
                $existing_id = wc_get_product_id_by_sku($product_sku);
                if ($existing_id) {
                    $group_key = 'existing_sku_' . $product_sku;
                } else {
                    $group_key = 'new_' . sanitize_key($product_name . '_' . $product_sku);
                }
            } else {
                $group_key = 'new_' . sanitize_key($product_name) . '_' . $row_number;
            }
            
            error_log('ECV CSV Processing: Row ' . $row_number . ' - Group key: ' . $group_key);
            
            if (!isset($product_groups[$group_key])) {
                $product_groups[$group_key] = array(
                    'base_data' => $data,
                    'base_header_map' => $header_map,
                    'base_row_number' => $row_number,
                    'variations' => array()
                );
            }
            
            // Add this row as a variation
            $product_groups[$group_key]['variations'][] = array(
                'data' => $data,
                'row_number' => $row_number
            );
        }
        fclose($handle);
        
        error_log('ECV CSV Processing: File closed, total groups: ' . count($product_groups));
        error_log('ECV CSV Processing: Product groups: ' . print_r($product_groups, true));
        
        // Process each product group
        foreach ($product_groups as $group_key => $group) {
            error_log('ECV Processing Group: ' . $group_key);
            error_log('ECV Group Data: ' . print_r($group, true));
            
            try {
                if ($is_unified_format) {
                    error_log('ECV Using unified format processing');
                    $group_result = ecv_process_product_unified_format($group, $update_existing, $create_new, $dry_run, $header_map);
                } elseif ($import_cross_group_format) {
                    error_log('ECV Using cross-group format processing');
                    $group_result = ecv_process_product_cross_group_format($group, $update_existing, $create_new, $dry_run, $header_map);
                } elseif ($import_grouped_format) {
                    error_log('ECV Using grouped format processing');
                    $group_result = ecv_process_product_grouped_format($group, $update_existing, $create_new, $dry_run, $header_map);
                } else {
                    error_log('ECV Using traditional processing');
                    $group_result = ecv_process_product_group($group, $update_existing, $create_new, $dry_run);
                }
                
                error_log('ECV Group Result: ' . print_r($group_result, true));
                
                if ($group_result['action'] === 'created') {
                    $results['created']++;
                    $results['success'][] = "Product created with " . count($group['variations']) . " variation(s) - {$group_result['message']}";
                    if (!empty($group_result['extra_fields_imported'])) {
                        $results['extra_fields_imported']++;
                    }
                } elseif ($group_result['action'] === 'updated') {
                    $results['updated']++;
                    $results['success'][] = "Product updated with " . count($group['variations']) . " variation(s) - {$group_result['message']}";
                    if (!empty($group_result['extra_fields_imported'])) {
                        $results['extra_fields_imported']++;
                    }
                } elseif ($group_result['action'] === 'skipped') {
                    $results['skipped']++;
                    $results['warnings'][] = "Product skipped - {$group_result['message']}";
                }
                
            } catch (Exception $e) {
                error_log('ECV Processing Error: ' . $e->getMessage());
                $results['errors'][] = "Product group {$group_key}: {$e->getMessage()}";
            }
        }
    } else {
        error_log('ECV CSV Processing: ERROR - Could not open CSV file: ' . $file_path);
        $results['errors'][] = 'Could not open CSV file';
    }

    error_log('ECV CSV Processing: Final results: ' . print_r($results, true));
    return $results;
}

function ecv_map_csv_headers($headers) {
    $map = array();
    foreach ($headers as $index => $header) {
        // Remove UTF-8 BOM if present (especially from first column)
        $header = str_replace("\xEF\xBB\xBF", '', $header);
        $clean_header = strtolower(trim($header));
        $map[$clean_header] = $index;
    }
    return $map;
}

function ecv_process_import_row($data, $header_map, $update_existing, $create_new, $dry_run, $row_number) {
    $product_id = ecv_get_csv_value($data, $header_map, 'id');
    $product_sku = ecv_get_csv_value($data, $header_map, 'sku');
    $product_name = ecv_get_csv_value($data, $header_map, 'name');
    
    // Determine if this is an existing product
    $existing_product = null;
    if (!empty($product_id) && is_numeric($product_id)) {
        $existing_product = wc_get_product($product_id);
    } elseif (!empty($product_sku)) {
        $existing_product_id = wc_get_product_id_by_sku($product_sku);
        if ($existing_product_id) {
            $existing_product = wc_get_product($existing_product_id);
        }
    }

    if ($existing_product) {
        if (!$update_existing) {
            return array('action' => 'skipped', 'message' => 'Product exists but update disabled');
        }
        
        if ($dry_run) {
            return array('action' => 'updated', 'message' => 'Would update: ' . $existing_product->get_name());
        }
        
        return ecv_update_product_from_csv($existing_product, $data, $header_map);
    } else {
        if (!$create_new) {
            return array('action' => 'skipped', 'message' => 'Product not found and create new disabled');
        }
        
        if (empty($product_name)) {
            throw new Exception('Product name is required for new products');
        }
        
        if ($dry_run) {
            return array('action' => 'created', 'message' => 'Would create: ' . $product_name);
        }
        
        return ecv_create_product_from_csv($data, $header_map);
    }
}

function ecv_process_product_group($group, $update_existing, $create_new, $dry_run) {
    $base_data = $group['base_data'];
    $header_map = $group['base_header_map'];
    $variations = $group['variations'];
    
    $product_id = ecv_get_csv_value($base_data, $header_map, 'id');
    $product_sku = ecv_get_csv_value($base_data, $header_map, 'sku');
    $product_name = ecv_get_csv_value($base_data, $header_map, 'name');
    
    // Determine if this is an existing product
    $existing_product = null;
    if (!empty($product_id) && is_numeric($product_id)) {
        $existing_product = wc_get_product($product_id);
    } elseif (!empty($product_sku)) {
        $existing_product_id = wc_get_product_id_by_sku($product_sku);
        if ($existing_product_id) {
            $existing_product = wc_get_product($existing_product_id);
        }
    }

    if ($existing_product) {
        if (!$update_existing) {
            return array('action' => 'skipped', 'message' => 'Product exists but update disabled');
        }
        
        if ($dry_run) {
            return array('action' => 'updated', 'message' => 'Would update: ' . $existing_product->get_name());
        }
        
        return ecv_update_product_group_from_csv($existing_product, $group);
    } else {
        if (!$create_new) {
            return array('action' => 'skipped', 'message' => 'Product not found and create new disabled');
        }
        
        if (empty($product_name)) {
            throw new Exception('Product name is required for new products');
        }
        
        if ($dry_run) {
            return array('action' => 'created', 'message' => 'Would create: ' . $product_name);
        }
        
        return ecv_create_product_group_from_csv($group);
    }
}

function ecv_create_product_group_from_csv($group) {
    $base_data = $group['base_data'];
    $header_map = $group['base_header_map'];
    $variations = $group['variations'];
    
    $product = new WC_Product_Simple();
    
    // Set basic product data from the first row
    ecv_set_product_data_from_csv($product, $base_data, $header_map);
    
    $product_id = $product->save();
    
    // Clear existing variation data to avoid conflicts
    ecv_delete_variations_data($product_id);
    
    // Process all variations for this product
    foreach ($variations as $variation) {
        ecv_set_variant_data_from_csv($product_id, $variation['data'], $header_map);
    }
    
    return array('action' => 'created', 'message' => $product->get_name() . " (ID: {$product_id})");
}

function ecv_update_product_group_from_csv($product, $group) {
    $base_data = $group['base_data'];
    $header_map = $group['base_header_map'];
    $variations = $group['variations'];
    
    // Update basic product data from the first row
    ecv_set_product_data_from_csv($product, $base_data, $header_map);
    
    $product->save();
    
    // Clear existing variation data to avoid conflicts
    ecv_delete_variations_data($product->get_id());
    
    // Process all variations for this product
    foreach ($variations as $variation) {
        ecv_set_variant_data_from_csv($product->get_id(), $variation['data'], $header_map);
    }
    
    return array('action' => 'updated', 'message' => $product->get_name() . " (ID: {$product->get_id()})");
}

function ecv_get_csv_value($data, $header_map, $field) {
    $key = strtolower($field);
    if (isset($header_map[$key]) && isset($data[$header_map[$key]])) {
        return trim($data[$header_map[$key]]);
    }
    return '';
}

function ecv_create_product_from_csv($data, $header_map) {
    $product = new WC_Product_Simple();
    
    // Set basic product data (including images)
    ecv_set_product_data_from_csv($product, $data, $header_map);
    
    // Save product with all data including images
    $product_id = $product->save();
    error_log('ECV Import: Product created with ID: ' . $product_id);
    
    // Verify gallery images were saved
    $saved_gallery = $product->get_gallery_image_ids();
    error_log('ECV Import: Gallery IDs after save: ' . print_r($saved_gallery, true));
    
    // Set custom variant data
    ecv_set_variant_data_from_csv($product_id, $data, $header_map);
    
    // Import product extra fields
    $extra_fields_imported = ecv_import_product_extra_fields_from_csv($product_id, $data, $header_map);
    
    return array(
        'action' => 'created', 
        'message' => $product->get_name() . " (ID: {$product_id})",
        'extra_fields_imported' => $extra_fields_imported
    );
}

function ecv_update_product_from_csv($product, $data, $header_map) {
    // Update basic product data (including images)
    ecv_set_product_data_from_csv($product, $data, $header_map);
    
    // Save product with all data including images
    $product->save();
    error_log('ECV Import: Product updated with ID: ' . $product->get_id());
    
    // Verify gallery images were saved
    $saved_gallery = $product->get_gallery_image_ids();
    error_log('ECV Import: Gallery IDs after save: ' . print_r($saved_gallery, true));
    
    // Update custom variant data
    ecv_set_variant_data_from_csv($product->get_id(), $data, $header_map);
    
    // Import product extra fields
    $extra_fields_imported = ecv_import_product_extra_fields_from_csv($product->get_id(), $data, $header_map);
    
    return array(
        'action' => 'updated', 
        'message' => $product->get_name() . " (ID: {$product->get_id()})",
        'extra_fields_imported' => $extra_fields_imported
    );
}


function ecv_set_product_data_from_csv($product, $data, $header_map) {
    // Basic product fields
    $name = ecv_get_csv_value($data, $header_map, 'name');
    if (!empty($name)) $product->set_name($name);
    
    $slug = ecv_get_csv_value($data, $header_map, 'slug');
    if (!empty($slug)) $product->set_slug($slug);
    
    $description = ecv_get_csv_value($data, $header_map, 'description');
    if (!empty($description)) $product->set_description($description);
    
    $short_description = ecv_get_csv_value($data, $header_map, 'short description');
    if (!empty($short_description)) $product->set_short_description($short_description);
    
    $sku = ecv_get_csv_value($data, $header_map, 'sku');
    if (!empty($sku)) $product->set_sku($sku);
    
    $regular_price = ecv_get_csv_value($data, $header_map, 'regular price');
    if (!empty($regular_price)) $product->set_regular_price($regular_price);
    
    $sale_price = ecv_get_csv_value($data, $header_map, 'sale price');
    if (!empty($sale_price)) $product->set_sale_price($sale_price);
    
    $stock_status = ecv_get_csv_value($data, $header_map, 'stock status');
    if (!empty($stock_status)) $product->set_stock_status($stock_status);
    
    $stock_quantity = ecv_get_csv_value($data, $header_map, 'stock quantity');
    if (!empty($stock_quantity)) $product->set_stock_quantity(intval($stock_quantity));
    
    $status = ecv_get_csv_value($data, $header_map, 'status');
    if (!empty($status)) $product->set_status($status);

    // Handle categories
    $categories = ecv_get_csv_value($data, $header_map, 'categories');
    if (!empty($categories)) {
        $category_names = explode('|', $categories);
        $category_ids = array();
        foreach ($category_names as $cat_name) {
            $cat_name = trim($cat_name);
            $term = get_term_by('name', $cat_name, 'product_cat');
            if (!$term) {
                $term = wp_insert_term($cat_name, 'product_cat');
                if (!is_wp_error($term)) {
                    $category_ids[] = $term['term_id'];
                }
            } else {
                $category_ids[] = $term->term_id;
            }
        }
        if (!empty($category_ids)) {
            $product->set_category_ids($category_ids);
        }
    }

    // Handle tags
    $tags = ecv_get_csv_value($data, $header_map, 'tags');
    if (!empty($tags)) {
        $tag_names = explode('|', $tags);
        $tag_ids = array();
        foreach ($tag_names as $tag_name) {
            $tag_name = trim($tag_name);
            $term = get_term_by('name', $tag_name, 'product_tag');
            if (!$term) {
                $term = wp_insert_term($tag_name, 'product_tag');
                if (!is_wp_error($term)) {
                    $tag_ids[] = $term['term_id'];
                }
            } else {
                $tag_ids[] = $term->term_id;
            }
        }
        if (!empty($tag_ids)) {
            $product->set_tag_ids($tag_ids);
        }
    }

    // Handle main image (try both column name formats)
    $main_image_url = ecv_get_csv_value($data, $header_map, 'main product image');
    if (empty($main_image_url)) {
        $main_image_url = ecv_get_csv_value($data, $header_map, 'main image url');
    }
    if (!empty($main_image_url)) {
        error_log('ECV Import: Processing main product image: ' . $main_image_url);
        error_log('ECV Import: Available headers for image lookup: ' . print_r(array_keys($header_map), true));
        $attachment_id = ecv_import_image_from_url($main_image_url, $product->get_name());
        if ($attachment_id) {
            $product->set_image_id($attachment_id);
            error_log('ECV Import: Main product image set successfully, ID: ' . $attachment_id);
        } else {
            error_log('ECV Import: Failed to import main product image from URL: ' . $main_image_url);
        }
    } else {
        error_log('ECV Import: No main product image column found or empty. Header map keys: ' . print_r(array_keys($header_map), true));
    }

    // Handle gallery images
    $gallery_images = ecv_get_csv_value($data, $header_map, 'gallery images');
    if (!empty($gallery_images)) {
        error_log('ECV Import: Processing gallery images: ' . $gallery_images);
        $gallery_urls = explode('|', $gallery_images);
        $gallery_ids = array();
        foreach ($gallery_urls as $url) {
            $url = trim($url);
            if (!empty($url)) {
                error_log('ECV Import: Importing gallery image from URL: ' . $url);
                $attachment_id = ecv_import_image_from_url($url, $product->get_name() . ' Gallery');
                if ($attachment_id) {
                    error_log('ECV Import: Gallery image imported successfully, ID: ' . $attachment_id);
                    $gallery_ids[] = $attachment_id;
                } else {
                    error_log('ECV Import: Failed to import gallery image from: ' . $url);
                }
            }
        }
        if (!empty($gallery_ids)) {
            error_log('ECV Import: Setting gallery image IDs: ' . implode(', ', $gallery_ids));
            $product->set_gallery_image_ids($gallery_ids);
            error_log('ECV Import: Gallery IDs set on product object before save');
        } else {
            error_log('ECV Import: No gallery images were successfully imported');
        }
    } else {
        error_log('ECV Import: No gallery images column found or empty. Header map keys: ' . print_r(array_keys($header_map), true));
    }
}

function ecv_import_product_extra_fields_from_csv($product_id, $data, $header_map) {
    // Import dynamic extra fields from CSV
    // Supports multiple column formats:
    // 1. New dynamic format: "Extra:key|type" (e.g., "Extra:banner_image|image")
    // 2. Legacy patterns: Extra_Image_1, Banner_Image, etc.
    
    error_log('ECV Import Extra Fields: Starting for product ID ' . $product_id);
    error_log('ECV Import Extra Fields: Header map: ' . print_r($header_map, true));
    error_log('ECV Import Extra Fields: Data row: ' . print_r($data, true));
    
    $extra_fields = array();
    $fields_imported = false;
    
    // List of standard WooCommerce/product columns to exclude
    $excluded_columns = array(
        'id', 'name', 'slug', 'sku', 'description', 'short description',
        'regular price', 'sale price', 'stock status', 'stock quantity',
        'categories', 'tags', 'status', 'main product image', 'main image url',
        'gallery images', 'product image', 'images', 'image',
        'has custom variants', 'variant combination id', 'variant sku',
        'variant price', 'variant sale price', 'variant stock', 'variant enabled',
        'variant attributes', 'attribute names', 'attribute values',
        'attribute groups', 'variant groups', 'variant main image',
        'variant attribute images', 'enable cross group', 'groups definition',
        'combination name', 'combination price', 'combination sale price',
        'combination stock', 'combination image', 'combination description',
        'group button images'
    );
    
    // Scan all CSV columns for extra field patterns
    foreach ($header_map as $column_name => $column_index) {
        error_log('ECV Import Extra Fields: Checking column "' . $column_name . '" at index ' . $column_index);
        
        // Skip excluded standard columns
        if (in_array($column_name, $excluded_columns)) {
            error_log('ECV Import Extra Fields: Column "' . $column_name . '" is a standard column, skipping');
            continue;
        }
        
        $field_value = isset($data[$column_index]) ? trim($data[$column_index]) : '';
        
        if (empty($field_value)) {
            error_log('ECV Import Extra Fields: Column "' . $column_name . '" has empty value, skipping');
            continue; // Skip empty values
        }
        
        error_log('ECV Import Extra Fields: Column "' . $column_name . '" has value: ' . substr($field_value, 0, 100));
        
        // Detect field type and key from column name
        $field_key = null;
        $field_type = null;
        
        // NEW PATTERN: Extra:key|type format (e.g., "Extra:banner_image|image", "Extra:feature_text|text")
        // This is the RECOMMENDED format and has highest priority
        if (preg_match('/^extra\s*:\s*([a-z0-9_]+)\s*\|\s*(image|text|pdf)$/i', $column_name, $matches)) {
            $field_key = sanitize_key($matches[1]);
            $field_type = strtolower($matches[2]);
            error_log('ECV Import: ✓ Detected NEW dynamic format - Column: ' . $column_name . ', Key: ' . $field_key . ', Type: ' . $field_type);
        }
        // Pattern 1: extra_image_1, extra_text_1, extra_pdf_1 (must start with "extra")
        elseif (preg_match('/^extra[_\s]+(image|text|pdf)[_\s]+(\d+)$/i', $column_name, $matches)) {
            $field_type = strtolower($matches[1]);
            $field_key = 'extra_' . $field_type . '_' . $matches[2];
            error_log('ECV Import: ✓ Detected legacy numbered format - Column: ' . $column_name . ', Key: ' . $field_key . ', Type: ' . $field_type);
        }
        // Pattern 2: Columns with _image, _text, _pdf suffix (but NOT standard columns)
        // Only process if column name suggests it's a custom field (contains specific keywords)
        elseif (preg_match('/^(banner|feature|hero|promo|spec|detail|custom|extra)[_\s]+(.+?)[_\s]+(image|text|pdf)$/i', $column_name, $matches)) {
            $prefix = sanitize_key($matches[1] . '_' . $matches[2]);
            $field_type = strtolower($matches[3]);
            $field_key = $prefix . '_' . $field_type;
            error_log('ECV Import: ✓ Detected custom named format - Column: ' . $column_name . ', Key: ' . $field_key . ', Type: ' . $field_type);
        }
        // Pattern 3: Simple custom names with type suffix (banner_image, feature_text, etc.)
        // Only if they start with known custom field prefixes
        elseif (preg_match('/^(banner|feature|hero|promo|spec|detail|custom)[_\s]+(image|text|pdf)$/i', $column_name, $matches)) {
            $prefix = sanitize_key($matches[1]);
            $field_type = strtolower($matches[2]);
            $field_key = $prefix . '_' . $field_type;
            error_log('ECV Import: ✓ Detected simple custom format - Column: ' . $column_name . ', Key: ' . $field_key . ', Type: ' . $field_type);
        }
        else {
            // Column doesn't match any extra field pattern
            error_log('ECV Import Extra Fields: Column "' . $column_name . '" does not match any extra field pattern, skipping');
            continue;
        }
        
        // Add field if we detected it
        if ($field_key && $field_type) {
            $extra_fields[] = array(
                'field_key' => $field_key,
                'field_type' => $field_type,
                'field_value' => $field_value
            );
            
            error_log('ECV Import: Found extra field - Key: ' . $field_key . ', Type: ' . $field_type . ', Value: ' . substr($field_value, 0, 50) . '...');
        }
    }
    
    // Save extra fields if any were found
    if (!empty($extra_fields)) {
        error_log('ECV Import Extra Fields: About to save ' . count($extra_fields) . ' fields: ' . print_r($extra_fields, true));
        ecv_save_product_extra_fields($product_id, $extra_fields);
        error_log('ECV Import: ✓ Saved ' . count($extra_fields) . ' extra fields for product ' . $product_id);
        
        // Mark product as having CSV-imported fields
        update_post_meta($product_id, '_ecv_imported_from_csv', current_time('mysql'));
        
        // Verify they were saved
        $saved_fields = ecv_get_product_extra_fields($product_id);
        error_log('ECV Import Extra Fields: Verification - Retrieved ' . count($saved_fields) . ' fields from database: ' . print_r($saved_fields, true));
        
        if (count($saved_fields) === count($extra_fields)) {
            error_log('ECV Import Extra Fields: ✓✓✓ SUCCESS! All fields verified in database!');
            $fields_imported = true;
        } else {
            error_log('ECV Import Extra Fields: ⚠️ WARNING! Field count mismatch. Saved: ' . count($extra_fields) . ', Retrieved: ' . count($saved_fields));
        }
    } else {
        error_log('ECV Import Extra Fields: No extra fields found to save for product ' . $product_id);
    }
    
    return $fields_imported;
}

function ecv_set_variant_data_from_csv($product_id, $data, $header_map) {
    $has_variants = ecv_get_csv_value($data, $header_map, 'has custom variants');
    
    if (strtolower($has_variants) !== 'yes') {
        return; // No variants to process
    }

    // Get existing variant data
    $existing_variations = ecv_get_variations_data($product_id);
    $existing_combinations = ecv_get_combinations_data($product_id);

    // Parse variant data from CSV
    $variant_attributes = ecv_get_csv_value($data, $header_map, 'variant attributes');
    $attr_names = ecv_get_csv_value($data, $header_map, 'attribute names');
    $attr_values = ecv_get_csv_value($data, $header_map, 'attribute values');
    
    if (empty($variant_attributes) && empty($attr_names)) {
        return; // No variant data to process
    }

    // Build variant structure
    $attributes = array();
    if (!empty($variant_attributes)) {
        // Format: "Size:Large|Color:Red"
        $attr_pairs = explode('|', $variant_attributes);
        foreach ($attr_pairs as $pair) {
            $parts = explode(':', $pair, 2);
            if (count($parts) === 2) {
                $attr_name = trim($parts[0]);
                $attr_value = trim($parts[1]);
                $attributes[] = array('attribute' => $attr_name, 'value' => $attr_value);
            }
        }
    } elseif (!empty($attr_names) && !empty($attr_values)) {
        // Separate format: names and values in different columns
        $names = explode('|', $attr_names);
        $values = explode('|', $attr_values);
        for ($i = 0; $i < min(count($names), count($values)); $i++) {
            $attributes[] = array('attribute' => trim($names[$i]), 'value' => trim($values[$i]));
        }
    }

    if (empty($attributes)) {
        return;
    }

    // Create combination data
    $combination_id = ecv_get_csv_value($data, $header_map, 'variant combination id');
    if (empty($combination_id)) {
        $combination_id = 'imported-' . uniqid();
    }

    // Build variants array in the format expected by frontend
    $variants_array = array();
    foreach ($attributes as $attr) {
        $variants_array[] = array(
            'attribute' => $attr['attribute'],
            'name' => $attr['value'],
            'image' => '' // Individual variant images are handled separately
        );
    }

    $combination = array(
        'id' => $combination_id,
        'sku' => ecv_get_csv_value($data, $header_map, 'variant sku'),
        'price' => ecv_get_csv_value($data, $header_map, 'variant price'),
        'sale_price' => ecv_get_csv_value($data, $header_map, 'variant sale price'),
        'stock' => ecv_get_csv_value($data, $header_map, 'variant stock'),
        'enabled' => strtolower(ecv_get_csv_value($data, $header_map, 'variant enabled')) !== 'no', // Default to enabled unless explicitly set to 'no'
        'variants' => $variants_array, // This is the key structure the frontend expects
        'attributes' => $attributes // Keep this for backward compatibility
    );

    // Handle variant main image
    $variant_main_image = ecv_get_csv_value($data, $header_map, 'variant main image');
    if (!empty($variant_main_image)) {
        $attachment_id = ecv_import_image_from_url($variant_main_image, 'Variant Image');
        if ($attachment_id) {
            $combination['main_image_id'] = $attachment_id;
            $combination['main_image_url'] = $variant_main_image;
        }
    }

    // Parse attribute images from CSV
    $attr_images_map = ecv_parse_attribute_images_from_csv($data, $header_map);

    // Get group information from CSV
    $attribute_groups_raw = ecv_get_csv_value($data, $header_map, 'attribute groups'); // For attribute-level groups
    $variant_groups_raw = ecv_get_csv_value($data, $header_map, 'variant groups'); // For variant-level group assignments
    
    // Parse attribute groups mapping (format: "AttributeName:Group1,Group2|OtherAttribute:Group3")
    $attribute_groups_mapping = array();
    if (!empty($attribute_groups_raw)) {
        $attr_group_pairs = explode('|', $attribute_groups_raw);
        foreach ($attr_group_pairs as $pair) {
            $parts = explode(':', $pair, 2);
            if (count($parts) === 2) {
                $attr_name = trim($parts[0]);
                $groups_str = trim($parts[1]);
                $attribute_groups_mapping[$attr_name] = $groups_str;
            }
        }
    }
    
    // Parse variant groups mapping (format: "AttributeName:GroupName|OtherAttribute:OtherGroup")
    $variant_groups_mapping = array();
    if (!empty($variant_groups_raw)) {
        $variant_group_pairs = explode('|', $variant_groups_raw);
        foreach ($variant_group_pairs as $pair) {
            $parts = explode(':', $pair, 2);
            if (count($parts) === 2) {
                $attr_name = trim($parts[0]);
                $group_name = trim($parts[1]);
                $variant_groups_mapping[$attr_name] = $group_name;
            }
        }
    }
    
    // Build variations data structure
    $variations_data = $existing_variations;
    foreach ($attributes as $attr) {
        $attr_name = $attr['attribute'];
        $attr_value = $attr['value'];
        
        // Get groups for this specific attribute
        $attr_specific_groups = isset($attribute_groups_mapping[$attr_name]) ? $attribute_groups_mapping[$attr_name] : '';
        
        // Find or create attribute
        $attr_index = null;
        foreach ($variations_data as $index => $existing_attr) {
            if ($existing_attr['name'] === $attr_name) {
                $attr_index = $index;
                break;
            }
        }
        
        if ($attr_index === null) {
            // Create new attribute with default display type and specific groups
            $attr_index = count($variations_data);
            
            $variations_data[$attr_index] = array(
                'name' => $attr_name,
                'display_type' => 'buttons', // Default display type
                'groups' => $attr_specific_groups, // Add groups specific to this attribute
                'variants' => array()
            );
        } else {
            // Update existing attribute with groups if provided
            if (!empty($attr_specific_groups)) {
                $existing_groups = $variations_data[$attr_index]['groups'] ?? '';
                $new_groups = array_unique(array_filter(array_merge(
                    explode(',', $existing_groups),
                    explode(',', $attr_specific_groups)
                )));
                $variations_data[$attr_index]['groups'] = implode(',', $new_groups);
            }
        }
        
        // Find or create variant
        $variant_exists = false;
        $variant_index = null;
        foreach ($variations_data[$attr_index]['variants'] as $v_index => $existing_variant) {
            if ($existing_variant['name'] === $attr_value) {
                $variant_exists = true;
                $variant_index = $v_index;
                break;
            }
        }
        
        if (!$variant_exists) {
            // Get image URL for this attribute/value combination
            $variant_image = '';
            if (isset($attr_images_map[$attr_name][$attr_value])) {
                $variant_image = $attr_images_map[$attr_name][$attr_value];
            }
            
            // Get the group assignment for this specific variant
            $variant_group = isset($variant_groups_mapping[$attr_name]) ? $variant_groups_mapping[$attr_name] : '';
            
            $variations_data[$attr_index]['variants'][] = array(
                'name' => $attr_value,
                'price_modifier' => 0,
                'description' => '',
                'image' => $variant_image,
                'group' => $variant_group // Assign variant to specified group
            );
        } else {
            // Update existing variant with image and group if provided
            if (isset($attr_images_map[$attr_name][$attr_value])) {
                $variations_data[$attr_index]['variants'][$variant_index]['image'] = $attr_images_map[$attr_name][$attr_value];
            }
            // Update variant group if provided
            if (isset($variant_groups_mapping[$attr_name])) {
                $variations_data[$attr_index]['variants'][$variant_index]['group'] = $variant_groups_mapping[$attr_name];
            }
        }
    }

    // Update or add combination
    $combination_exists = false;
    foreach ($existing_combinations as $index => $existing_combo) {
        if ($existing_combo['id'] === $combination_id) {
            $existing_combinations[$index] = $combination;
            $combination_exists = true;
            break;
        }
    }
    
    if (!$combination_exists) {
        $existing_combinations[] = $combination;
    }

    // Save updated data
    ecv_save_variations_data($product_id, $variations_data);
    ecv_save_combinations_data($product_id, $existing_combinations);
}

function ecv_import_image_from_url($url, $description = '') {
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        error_log('ECV Image Import: Invalid URL: ' . $url);
        return false;
    }

    // Check if image already exists
    $existing = attachment_url_to_postid($url);
    if ($existing) {
        error_log('ECV Image Import: Image already exists in media library, ID: ' . $existing);
        return $existing;
    }

    // Download and import image
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    error_log('ECV Image Import: Downloading image from: ' . $url);
    $temp_file = download_url($url);
    if (is_wp_error($temp_file)) {
        error_log('ECV Image Import: Download failed: ' . $temp_file->get_error_message());
        return false;
    }

    $file_array = array(
        'name' => basename($url),
        'tmp_name' => $temp_file
    );

    error_log('ECV Image Import: Processing downloaded file: ' . $temp_file);
    $attachment_id = media_handle_sideload($file_array, 0, $description);
    
    if (is_wp_error($attachment_id)) {
        error_log('ECV Image Import: Sideload failed: ' . $attachment_id->get_error_message());
        @unlink($temp_file);
        return false;
    }

    error_log('ECV Image Import: Successfully imported image, ID: ' . $attachment_id);
    return $attachment_id;
}

/**
 * Parse attribute images from CSV data
 * Handles both individual variant images and main variant image
 */
function ecv_parse_attribute_images_from_csv($data, $header_map) {
    $attr_images_map = array();
    
    // Parse variant attribute images (format: \"https://example.com/size-large.jpg|https://example.com/color-red.jpg\")
    $variant_attr_images = ecv_get_csv_value($data, $header_map, 'variant attribute images');
    if (!empty($variant_attr_images)) {
        $image_urls = explode('|', $variant_attr_images);
        
        // Try to match images to attributes based on naming convention or order
        $attr_names = ecv_get_csv_value($data, $header_map, 'attribute names');
        $attr_values = ecv_get_csv_value($data, $header_map, 'attribute values');
        
        if (!empty($attr_names) && !empty($attr_values)) {
            $names = explode('|', $attr_names);
            $values = explode('|', $attr_values);
            
            for ($i = 0; $i < min(count($names), count($values), count($image_urls)); $i++) {
                $attr_name = trim($names[$i]);
                $attr_value = trim($values[$i]);
                $image_url = trim($image_urls[$i]);
                
                if (!empty($image_url)) {
                    if (!isset($attr_images_map[$attr_name])) {
                        $attr_images_map[$attr_name] = array();
                    }
                    $attr_images_map[$attr_name][$attr_value] = $image_url;
                }
            }
        }
    }
    
    return $attr_images_map;
}


function ecv_process_product_group_as_group_pricing($group, $update_existing, $create_new, $dry_run) {
    $base_data = $group['base_data'];
    $header_map = $group['base_header_map'];
    $variations = $group['variations'];
    
    $product_id = ecv_get_csv_value($base_data, $header_map, 'id');
    $product_sku = ecv_get_csv_value($base_data, $header_map, 'sku');
    $product_name = ecv_get_csv_value($base_data, $header_map, 'name');
    
    // Determine if this is an existing product
    $existing_product = null;
    if (!empty($product_id) && is_numeric($product_id)) {
        $existing_product = wc_get_product($product_id);
    } elseif (!empty($product_sku)) {
        $existing_product_id = wc_get_product_id_by_sku($product_sku);
        if ($existing_product_id) {
            $existing_product = wc_get_product($existing_product_id);
        }
    }

    if ($existing_product) {
        if (!$update_existing) {
            return array('action' => 'skipped', 'message' => 'Product exists but update disabled');
        }
        
        if ($dry_run) {
            return array('action' => 'updated', 'message' => 'Would update: ' . $existing_product->get_name());
        }
        
        return ecv_update_product_group_as_group_pricing($existing_product, $group);
    } else {
        if (!$create_new) {
            return array('action' => 'skipped', 'message' => 'Product not found and create new disabled');
        }
        
        if (empty($product_name)) {
            throw new Exception('Product name is required for new products');
        }
        
        if ($dry_run) {
            return array('action' => 'created', 'message' => 'Would create: ' . $product_name);
        }
        
        return ecv_create_product_group_as_group_pricing($group);
    }
}

function ecv_create_product_group_as_group_pricing($group) {
    $base_data = $group['base_data'];
    $header_map = $group['base_header_map'];
    $variations = $group['variations'];
    
    $product = new WC_Product_Simple();
    
    // Set basic product data from the first row
    ecv_set_product_data_from_csv($product, $base_data, $header_map);
    
    $product_id = $product->save();
    
    // Clear existing variation data to avoid conflicts
    ecv_delete_variations_data($product_id);
    ecv_delete_group_pricing_data($product_id);
    
    // Enable group pricing
    ecv_save_group_pricing_enabled($product_id, 'yes');
    
    // Process group pricing data from CSV
    $group_pricing_data = ecv_parse_group_pricing_from_csv($variations, $header_map);
    ecv_save_group_pricing_data($product_id, $group_pricing_data);
    
    return array('action' => 'created', 'message' => $product->get_name() . " (ID: {$product_id})");
}

function ecv_update_product_group_as_group_pricing($product, $group) {
    $base_data = $group['base_data'];
    $header_map = $group['base_header_map'];
    $variations = $group['variations'];
    
    // Update basic product data from the first row
    ecv_set_product_data_from_csv($product, $base_data, $header_map);
    
    $product->save();
    
    // Clear existing variation data to avoid conflicts
    ecv_delete_variations_data($product->get_id());
    ecv_delete_group_pricing_data($product->get_id());
    
    // Enable group pricing
    ecv_save_group_pricing_enabled($product->get_id(), 'yes');
    
    // Process group pricing data from CSV
    $group_pricing_data = ecv_parse_group_pricing_from_csv($variations, $header_map);
    ecv_save_group_pricing_data($product->get_id(), $group_pricing_data);
    
    return array('action' => 'updated', 'message' => $product->get_name() . " (ID: {$product->get_id()})");
}

function ecv_parse_group_pricing_from_csv($variations, $header_map) {
    $groups_data = array();
    
    // Group variations by group name
    $groups_map = array();
    
    foreach ($variations as $variation) {
        $data = $variation['data'];
        $group_name = ecv_get_csv_value($data, $header_map, 'group name');
        $group_price = ecv_get_csv_value($data, $header_map, 'group price');
        $group_image = ecv_get_csv_value($data, $header_map, 'group image');
        
        if (empty($group_name)) {
            $group_name = 'Default Group';
        }
        
        if (!isset($groups_map[$group_name])) {
            $groups_map[$group_name] = array(
                'name' => $group_name,
                'price' => $group_price,
                'image' => $group_image,
                'variations' => array()
            );
        }
        
        // Add variation to group
        $variation_data = array(
            'attribute' => ecv_get_csv_value($data, $header_map, 'attribute'),
            'value' => ecv_get_csv_value($data, $header_map, 'value'),
            'image' => ecv_get_csv_value($data, $header_map, 'variation image')
        );
        
        if (!empty($variation_data['attribute']) && !empty($variation_data['value'])) {
            $groups_map[$group_name]['variations'][] = $variation_data;
        }
    }
    
    // Convert to array format
    foreach ($groups_map as $group) {
        if (!empty($group['variations'])) {
            $groups_data[] = $group;
        }
    }
    
    return $groups_data;
}

function ecv_display_import_results($results, $dry_run) {
    $title = $dry_run ? __('Import Preview Results', 'exp-custom-variations') : __('Import Results', 'exp-custom-variations');
    
    echo '<div class="wrap"><h1>' . $title . '</h1>';
    
    // Summary
    echo '<div class="notice notice-info"><p>';
    echo sprintf(__('Processed: %d rows', 'exp-custom-variations'), $results['processed']) . '<br/>';
    echo sprintf(__('Created: %d products', 'exp-custom-variations'), $results['created']) . '<br/>';
    echo sprintf(__('Updated: %d products', 'exp-custom-variations'), $results['updated']) . '<br/>';
    echo sprintf(__('Skipped: %d products', 'exp-custom-variations'), $results['skipped']) . '<br/>';
    echo sprintf(__('Errors: %d', 'exp-custom-variations'), count($results['errors']));
    echo '</p></div>';
    
    // Extra Fields Summary
    if (!empty($results['extra_fields_imported'])) {
        echo '<div class="notice notice-success" style="border-left-color: #28a745;"><p>';
        echo '<strong>✅ Extra Fields Imported:</strong><br/>';
        echo sprintf(__('%d products have extra fields imported from CSV', 'exp-custom-variations'), $results['extra_fields_imported']);
        echo '<br/><em style="font-size: 12px;">Edit any product to see the "Product Extra Fields (Shortcodes)" metabox with imported fields.</em>';
        echo '</p></div>';
    }

    // Success messages
    if (!empty($results['success'])) {
        echo '<div class="notice notice-success"><h3>' . __('Success Messages', 'exp-custom-variations') . '</h3><ul>';
        foreach ($results['success'] as $message) {
            echo '<li>' . esc_html($message) . '</li>';
        }
        echo '</ul></div>';
    }

    // Warnings
    if (!empty($results['warnings'])) {
        echo '<div class="notice notice-warning"><h3>' . __('Warnings', 'exp-custom-variations') . '</h3><ul>';
        foreach ($results['warnings'] as $warning) {
            echo '<li>' . esc_html($warning) . '</li>';
        }
        echo '</ul></div>';
    }

    // Errors
    if (!empty($results['errors'])) {
        echo '<div class="notice notice-error"><h3>' . __('Errors', 'exp-custom-variations') . '</h3><ul>';
        foreach ($results['errors'] as $error) {
            echo '<li>' . esc_html($error) . '</li>';
        }
        echo '</ul></div>';
    }

    echo '<p><a href="' . admin_url('edit.php?post_type=product&page=ecv-import-export') . '" class="button">' . __('Back to Import/Export', 'exp-custom-variations') . '</a></p>';
    echo '</div>';
}

// Process product with grouped format (simplified CSV format)
function ecv_process_product_grouped_format($group, $update_existing, $create_new, $dry_run, $header_map) {
    error_log('ECV Grouped Format: Processing group with ' . count($group['variations']) . ' variations');
    
    $result = array('action' => 'skipped', 'message' => '');
    
    if (empty($group['variations'])) {
        error_log('ECV Grouped Format: No variations found in group');
        return array('action' => 'error', 'message' => 'No variations found');
    }
    
    // Access the correct data structure
    $first_row_data = $group['variations'][0]['data'];
    
    // Get product basic info
    $product_id = ecv_get_csv_value($first_row_data, $header_map, 'id');
    $product_name = ecv_get_csv_value($first_row_data, $header_map, 'name');
    $product_sku = ecv_get_csv_value($first_row_data, $header_map, 'sku');
    
    error_log('ECV Grouped Format: Product ID: ' . $product_id);
    error_log('ECV Grouped Format: Product Name: ' . $product_name);
    error_log('ECV Grouped Format: Product SKU: ' . $product_sku);
    
    if (empty($product_name)) {
        error_log('ECV Grouped Format: ERROR - Product name is empty');
        return array('action' => 'error', 'message' => 'Product name is required');
    }
    
    // Check if product exists
    $existing_product = null;
    if ($product_id && is_numeric($product_id)) {
        $existing_product = wc_get_product($product_id);
    } elseif ($product_sku) {
        $existing_product_id = wc_get_product_id_by_sku($product_sku);
        if ($existing_product_id) {
            $existing_product = wc_get_product($existing_product_id);
        }
    }
    
    if ($existing_product && !$update_existing) {
        error_log('ECV Grouped Format: SKIPPED - Product exists but update_existing is false');
        return array('action' => 'skipped', 'message' => 'Product already exists and update_existing is false');
    }
    
    if (!$existing_product && !$create_new) {
        error_log('ECV Grouped Format: SKIPPED - Product does not exist and create_new is false');
        return array('action' => 'skipped', 'message' => 'Product does not exist and create_new is false');
    }
    
    if ($dry_run) {
        return array('action' => 'dry_run', 'message' => 'Would ' . ($existing_product ? 'update' : 'create') . ' product: ' . $product_name);
    }
    
    // Extract ONLY the variations for THIS product (by SKU)
    $product_variation_data = array();
    foreach ($group['variations'] as $variation) {
        $row_sku = ecv_get_csv_value($variation['data'], $header_map, 'sku');
        if ($row_sku === $product_sku) {
            $product_variation_data[] = $variation['data'];
        }
    }
    
    error_log('ECV Grouped Format: Filtered variation data for SKU ' . $product_sku . ': ' . print_r($product_variation_data, true));
    
    if (empty($product_variation_data)) {
        error_log('ECV Grouped Format: ERROR - No variation data found for this SKU');
        return array('action' => 'error', 'message' => 'No variation data found for SKU: ' . $product_sku);
    }
    
    // Parse grouped format for this specific product only
    $grouped_data = ecv_parse_grouped_format($product_variation_data, $header_map);
    error_log('ECV Grouped Format: Parsed grouped data for ' . $product_sku . ': ' . print_r($grouped_data, true));
    
    if (empty($grouped_data)) {
        error_log('ECV Grouped Format: ERROR - Could not parse grouped format data');
        return array('action' => 'error', 'message' => 'Could not parse grouped format data');
    }
    
    // Create or update product
    if ($existing_product) {
        $product_id = $existing_product->get_id();
        // Update basic product data
        ecv_set_product_data_from_csv($existing_product, $first_row_data, $header_map);
        $existing_product->save();
        $result['action'] = 'updated';
        $result['message'] = 'Updated existing product: ' . $product_name . ' (SKU: ' . $product_sku . ')';
        error_log('ECV Grouped Format: Updating existing product ID: ' . $product_id);
    } else {
        error_log('ECV Grouped Format: Creating new product...');
        $product_id = ecv_create_product_from_grouped_data($grouped_data, $header_map, $first_row_data);
        error_log('ECV Grouped Format: Product creation result: ' . ($product_id ? 'SUCCESS (ID: ' . $product_id . ')' : 'FAILED'));
        
        if (!$product_id) {
            error_log('ECV Grouped Format: ERROR - Failed to create product');
            return array('action' => 'error', 'message' => 'Failed to create product');
        }
        $result['action'] = 'created';
        $result['message'] = 'Created new product: ' . $product_name . ' (SKU: ' . $product_sku . ')';
    }
    
    // Save grouped data as traditional variations for this specific product
    ecv_save_grouped_data_as_traditional($product_id, $grouped_data);
    
    // Import product extra fields from CSV
    error_log('ECV Grouped Format: Importing extra fields for product ID: ' . $product_id);
    ecv_import_product_extra_fields_from_csv($product_id, $first_row_data, $header_map);
    
    return $result;
}

// Parse grouped format CSV data
function ecv_parse_grouped_format($variations, $header_map) {
    error_log('ECV Parse Advanced Grouped Format: Starting with ' . count($variations) . ' variations');
    error_log('ECV Parse Advanced Grouped Format: Header map: ' . print_r($header_map, true));
    
    $grouped_data = array();
    
    foreach ($variations as $row_index => $row) {
        error_log('ECV Parse Advanced Grouped Format: Processing row ' . $row_index . ': ' . print_r($row, true));
        
        // Get attribute groups (e.g., "finish|colour")
        $attribute_groups = ecv_get_csv_value($row, $header_map, 'attribute groups');
        if (empty($attribute_groups)) {
            error_log('ECV Parse Advanced Grouped Format: Skipping row - no attribute groups found');
            continue;
        }
        
        $attributes = array_map('trim', explode('|', $attribute_groups));
        
        // Get group names (e.g., "G1,C1")
        $group_names = ecv_get_csv_value($row, $header_map, 'group names');
        if (empty($group_names)) {
            error_log('ECV Parse Advanced Grouped Format: Skipping row - no group names found');
            continue;
        }
        
        $groups = array_map('trim', explode(',', $group_names));
        
        // Get group values (e.g., "Matte,Glossy|Red,Blue,Green,Black")
        $group_values = ecv_get_csv_value($row, $header_map, 'group values');
        if (empty($group_values)) {
            error_log('ECV Parse Advanced Grouped Format: Skipping row - no group values found');
            continue;
        }
        
        $values_per_attribute = explode('|', $group_values);
        
        // Get all properties for this group combination
        $combination_image = ecv_get_csv_value($row, $header_map, 'group image');
        $combination_price = floatval(ecv_get_csv_value($row, $header_map, 'group price'));
        $combination_sale_price = ecv_get_csv_value($row, $header_map, 'group sale price');
        $combination_stock = ecv_get_csv_value($row, $header_map, 'group stock');
        $combination_description = ecv_get_csv_value($row, $header_map, 'group description');
        
        // Validate data structure
        if (count($attributes) !== count($groups) || count($attributes) !== count($values_per_attribute)) {
            error_log('ECV Parse Advanced Grouped Format: Skipping row - mismatched attribute/group/values count');
            continue;
        }
        
        // Create group combination entry
        $group_combination = array(
            'combination_id' => implode('_', $groups), // e.g., "G1_C1"
            'attributes' => array(),
            'combination_price' => $combination_price,
            'combination_sale_price' => !empty($combination_sale_price) ? floatval($combination_sale_price) : '',
            'combination_stock' => !empty($combination_stock) ? intval($combination_stock) : '',
            'combination_image' => $combination_image,
            'combination_description' => $combination_description
        );
        
        // Process each attribute and its group
        for ($i = 0; $i < count($attributes); $i++) {
            $attribute = $attributes[$i];
            $group_name = $groups[$i];
            $values = array_map('trim', explode(',', $values_per_attribute[$i]));
            
            $group_combination['attributes'][$attribute] = array(
                'group_name' => $group_name,
                'values' => $values
            );
        }
        
        $grouped_data[] = $group_combination;
        
        error_log('ECV Parse Advanced Grouped Format: Created combination: ' . print_r($group_combination, true));
    }
    
    error_log('ECV Parse Advanced Grouped Format: Final grouped data: ' . print_r($grouped_data, true));
    return $grouped_data;
}

// Helper function to parse groups definition string
function ecv_parse_groups_definition($groups_definition) {
    error_log('ECV Parse Groups Definition: Input: ' . $groups_definition);
    
    $parsed_groups = array();
    
    // Split by semicolon to get each attribute
    $attribute_parts = explode(';', $groups_definition);
    
    foreach ($attribute_parts as $attribute_part) {
        // Split by colon to separate attribute name from groups
        $colon_pos = strpos($attribute_part, ':');
        if ($colon_pos === false) {
            continue;
        }
        
        $attribute_name = trim(substr($attribute_part, 0, $colon_pos));
        $groups_string = trim(substr($attribute_part, $colon_pos + 1));
        
        // Parse groups: "G1=Matte,Glossy|G2=Textured,Smooth"
        $group_parts = explode('|', $groups_string);
        $attribute_groups = array();
        
        foreach ($group_parts as $group_part) {
            $equals_pos = strpos($group_part, '=');
            if ($equals_pos === false) {
                continue;
            }
            
            $group_name = trim(substr($group_part, 0, $equals_pos));
            $values_string = trim(substr($group_part, $equals_pos + 1));
            $values = array_map('trim', explode(',', $values_string));
            
            $attribute_groups[$group_name] = $values;
        }
        
        if (!empty($attribute_groups)) {
            $parsed_groups[$attribute_name] = $attribute_groups;
        }
    }
    
    error_log('ECV Parse Groups Definition: Result: ' . print_r($parsed_groups, true));
    return $parsed_groups;
}

// Parse cross-group format CSV data (allows all group combinations)
function ecv_parse_cross_group_format($variations, $header_map) {
    error_log('ECV Parse Cross Group Format: Starting with ' . count($variations) . ' variations');
    
    $grouped_data = array();
    $groups_definition = '';
    
    // First, extract the groups definition from the first row
    foreach ($variations as $row) {
        $groups_def = ecv_get_csv_value($row, $header_map, 'groups definition');
        if (!empty($groups_def)) {
            $groups_definition = $groups_def;
            break;
        }
    }
    
    if (empty($groups_definition)) {
        error_log('ECV Parse Cross Group: No groups definition found');
        return $grouped_data;
    }
    
    // Parse groups definition: "finish:G1=Matte,Glossy|G2=Textured,Smooth;colour:C1=Red,Blue,Green,Black|C2=White,Yellow,Purple,Orange"
    $parsed_groups = ecv_parse_groups_definition($groups_definition);
    error_log('ECV Parse Cross Group: Parsed groups: ' . print_r($parsed_groups, true));
    
    // Process each combination row
    foreach ($variations as $row) {
        $combination_name = ecv_get_csv_value($row, $header_map, 'combination name');
        if (empty($combination_name)) {
            continue;
        }
        
        $combination_price = floatval(ecv_get_csv_value($row, $header_map, 'combination price'));
        $combination_sale_price = ecv_get_csv_value($row, $header_map, 'combination sale price');
        $combination_stock = ecv_get_csv_value($row, $header_map, 'combination stock');
        $combination_image = ecv_get_csv_value($row, $header_map, 'combination image');
        $combination_description = ecv_get_csv_value($row, $header_map, 'combination description');
        
        // Parse combination name (e.g., "G1+C1")
        $group_names = explode('+', $combination_name);
        
        if (count($group_names) !== 2) {
            error_log('ECV Parse Cross Group: Invalid combination name format: ' . $combination_name);
            continue;
        }
        
        $group1_name = trim($group_names[0]);
        $group2_name = trim($group_names[1]);
        
        // Build attributes array from parsed groups and combination
        $attributes = array();
        
        foreach ($parsed_groups as $attribute_name => $attribute_groups) {
            // Find which group from this attribute is being used
            $selected_group = null;
            $selected_values = array();
            
            if (isset($attribute_groups[$group1_name])) {
                $selected_group = $group1_name;
                $selected_values = $attribute_groups[$group1_name];
            } elseif (isset($attribute_groups[$group2_name])) {
                $selected_group = $group2_name;
                $selected_values = $attribute_groups[$group2_name];
            }
            
            if ($selected_group) {
                $attributes[$attribute_name] = array(
                    'group_name' => $selected_group,
                    'values' => $selected_values
                );
            }
        }
        
        $grouped_data[] = array(
            'combination_id' => str_replace('+', '_', $combination_name), // G1+C1 -> G1_C1
            'attributes' => $attributes,
            'combination_price' => $combination_price,
            'combination_sale_price' => !empty($combination_sale_price) ? floatval($combination_sale_price) : '',
            'combination_stock' => !empty($combination_stock) ? intval($combination_stock) : '',
            'combination_image' => $combination_image,
            'combination_description' => $combination_description
        );
        
        error_log('ECV Parse Cross Group: Created combination: ' . $combination_name . ' with attributes: ' . print_r($attributes, true));
    }
    
    error_log('ECV Parse Cross Group: Final grouped data: ' . print_r($grouped_data, true));
    return $grouped_data;
}

// Save grouped data as traditional variations for a specific product
function ecv_save_grouped_data_as_traditional($product_id, $grouped_data) {
    error_log('ECV Save Grouped Data: Starting conversion for product ID: ' . $product_id);
    error_log('ECV Save Grouped Data: Grouped data: ' . print_r($grouped_data, true));
    
    // Use the existing conversion function (pass product ID so converter can read meta like display types and group images)
    $converted = ecv_convert_groups_to_traditional_format($grouped_data, $product_id);
    $data = $converted['data'];
    $combinations = $converted['combinations'];
    
    error_log('ECV Save Grouped Data: Converted data: ' . print_r($data, true));
    error_log('ECV Save Grouped Data: Converted combinations: ' . print_r($combinations, true));
    
    // Clear existing data first
    ecv_delete_variations_data($product_id);
    
    // Save new data
    ecv_save_variations_data($product_id, $data);
    ecv_save_combinations_data($product_id, $combinations);
    
    // Also save the cross group data for admin UI if it's cross-group format
    if (!empty($grouped_data[0]['combination_id'])) {
        // This is cross-group format from import - save as cross group data
        ecv_save_cross_group_data($product_id, $grouped_data);
        update_post_meta($product_id, '_ecv_variation_mode', 'cross_group');
    } else {
        // This is old grouped format - save as grouped data
        ecv_save_grouped_data($product_id, $grouped_data);
        update_post_meta($product_id, '_ecv_variation_mode', 'grouped');
    }
    
    error_log('ECV Save Grouped Data: Data saved successfully');
}

// Process product with cross-group format
function ecv_process_product_cross_group_format($group, $update_existing, $create_new, $dry_run, $header_map) {
    error_log('ECV Cross-Group Format: Processing group with ' . count($group['variations']) . ' variations');
    
    $result = array('action' => 'skipped', 'message' => '');
    
    if (empty($group['variations'])) {
        error_log('ECV Cross-Group Format: No variations found in group');
        return array('action' => 'error', 'message' => 'No variations found');
    }
    
    // Access the correct data structure
    $first_row_data = $group['variations'][0]['data'];
    
    // Get product basic info
    $product_id = ecv_get_csv_value($first_row_data, $header_map, 'id');
    $product_name = ecv_get_csv_value($first_row_data, $header_map, 'name');
    $product_sku = ecv_get_csv_value($first_row_data, $header_map, 'sku');
    
    error_log('ECV Cross-Group Format: Product ID: ' . $product_id);
    error_log('ECV Cross-Group Format: Product Name: ' . $product_name);
    error_log('ECV Cross-Group Format: Product SKU: ' . $product_sku);
    
    if (empty($product_name)) {
        error_log('ECV Cross-Group Format: ERROR - Product name is empty');
        return array('action' => 'error', 'message' => 'Product name is required');
    }
    
    // Check if product exists
    $existing_product = null;
    if ($product_id && is_numeric($product_id)) {
        $existing_product = wc_get_product($product_id);
    } elseif ($product_sku) {
        $existing_product_id = wc_get_product_id_by_sku($product_sku);
        if ($existing_product_id) {
            $existing_product = wc_get_product($existing_product_id);
        }
    }
    
    if ($existing_product && !$update_existing) {
        error_log('ECV Cross-Group Format: SKIPPED - Product exists but update_existing is false');
        return array('action' => 'skipped', 'message' => 'Product already exists and update_existing is false');
    }
    
    if (!$existing_product && !$create_new) {
        error_log('ECV Cross-Group Format: SKIPPED - Product does not exist and create_new is false');
        return array('action' => 'skipped', 'message' => 'Product does not exist and create_new is false');
    }
    
    if ($dry_run) {
        return array('action' => 'dry_run', 'message' => 'Would ' . ($existing_product ? 'update' : 'create') . ' product: ' . $product_name);
    }
    
    // Extract ONLY the variations for THIS product (by SKU)
    $product_variation_data = array();
    foreach ($group['variations'] as $variation) {
        $row_sku = ecv_get_csv_value($variation['data'], $header_map, 'sku');
        if ($row_sku === $product_sku) {
            $product_variation_data[] = $variation['data'];
        }
    }
    
    error_log('ECV Cross-Group Format: Filtered variation data for SKU ' . $product_sku . ': ' . print_r($product_variation_data, true));
    
    if (empty($product_variation_data)) {
        error_log('ECV Cross-Group Format: ERROR - No variation data found for this SKU');
        return array('action' => 'error', 'message' => 'No variation data found for SKU: ' . $product_sku);
    }
    
    // Parse cross-group format for this specific product only
    $cross_group_data = ecv_parse_cross_group_format($product_variation_data, $header_map);
    error_log('ECV Cross-Group Format: Parsed cross-group data for ' . $product_sku . ': ' . print_r($cross_group_data, true));
    
    if (empty($cross_group_data)) {
        error_log('ECV Cross-Group Format: ERROR - Could not parse cross-group format data');
        return array('action' => 'error', 'message' => 'Could not parse cross-group format data');
    }
    
    // Create or update product
    if ($existing_product) {
        $product_id = $existing_product->get_id();
        // Update basic product data
        ecv_set_product_data_from_csv($existing_product, $first_row_data, $header_map);
        
        // Handle main product image for existing products
        $main_product_image = ecv_get_csv_value($first_row_data, $header_map, 'main product image');
        if (!empty($main_product_image)) {
            error_log('ECV Cross-Group Format: Updating main product image: ' . $main_product_image);
            $attachment_id = ecv_import_image_from_url($main_product_image, $product_name . ' Main Image');
            if ($attachment_id) {
                $existing_product->set_image_id($attachment_id);
                error_log('ECV Cross-Group Format: Main product image updated successfully');
            } else {
                error_log('ECV Cross-Group Format: Failed to import main product image');
            }
        }
        
        $existing_product->save();
        $result['action'] = 'updated';
        $result['message'] = 'Updated existing product: ' . $product_name . ' (SKU: ' . $product_sku . ')';
        error_log('ECV Cross-Group Format: Updating existing product ID: ' . $product_id);
    } else {
        error_log('ECV Cross-Group Format: Creating new product...');
        $product_id = ecv_create_product_from_grouped_data($cross_group_data, $header_map, $first_row_data);
        error_log('ECV Cross-Group Format: Product creation result: ' . ($product_id ? 'SUCCESS (ID: ' . $product_id . ')' : 'FAILED'));
        
        if (!$product_id) {
            error_log('ECV Cross-Group Format: ERROR - Failed to create product');
            return array('action' => 'error', 'message' => 'Failed to create product');
        }
        $result['action'] = 'created';
        $result['message'] = 'Created new product: ' . $product_name . ' (SKU: ' . $product_sku . ')';
    }
    
    // Process group button images from CSV FIRST so converter can use them
    $group_button_images = ecv_get_csv_value($first_row_data, $header_map, 'group button images');
    if (!empty($group_button_images)) {
        error_log('ECV Cross-Group Format: Processing group button images: ' . $group_button_images);
        $parsed_group_images = ecv_parse_group_button_images_from_csv($group_button_images);
        if (!empty($parsed_group_images)) {
            update_post_meta($product_id, '_ecv_group_images_data', $parsed_group_images);
            error_log('ECV Cross-Group Format: Group button images saved: ' . print_r($parsed_group_images, true));
        }
    }

    // Save cross-group data as traditional variations for this specific product (after meta saved)
    ecv_save_grouped_data_as_traditional($product_id, $cross_group_data);
    
    // Import product extra fields from CSV
    error_log('ECV Cross-Group Format: Importing extra fields for product ID: ' . $product_id);
    ecv_import_product_extra_fields_from_csv($product_id, $first_row_data, $header_map);
    
    return $result;
}

// Create product from grouped data
function ecv_create_product_from_grouped_data($grouped_data, $header_map, $first_row) {
    error_log('ECV Create Product: Starting product creation');
    error_log('ECV Create Product: Grouped data: ' . print_r($grouped_data, true));
    error_log('ECV Create Product: First row: ' . print_r($first_row, true));
    
    // Create WooCommerce product using native API for better compatibility
    $product = new WC_Product_Simple();
    
    // Use the standard function to set all product data (including gallery images, categories, tags, etc.)
    ecv_set_product_data_from_csv($product, $first_row, $header_map);
    
    // Save the product
    $product_id = $product->save();
    error_log('ECV Create Product: Product save result: ' . ($product_id ? 'SUCCESS (ID: ' . $product_id . ')' : 'FAILED'));
    
    if (!$product_id) {
        error_log('ECV Create Product: Product creation failed - no ID returned');
        return false;
    }
    
    // Verify images were saved by re-fetching the product
    $saved_product = wc_get_product($product_id);
    $main_image_id = $saved_product->get_image_id();
    $gallery_image_ids = $saved_product->get_gallery_image_ids();
    error_log('ECV Create Product: Product created successfully with ID: ' . $product_id);
    error_log('ECV Create Product: Verified main image ID: ' . ($main_image_id ? $main_image_id : 'NONE'));
    error_log('ECV Create Product: Verified gallery image IDs: ' . (!empty($gallery_image_ids) ? implode(', ', $gallery_image_ids) : 'NONE'));
    
    return $product_id;
}

// Parse group button images from CSV format
function ecv_parse_group_button_images_from_csv($group_images_string) {
    error_log('ECV Parse Group Images: Input string: ' . $group_images_string);
    
    $group_images = [];
    
    // Format: "finish:G1=https://example.com/g1-icon.jpg|finish:G2=https://example.com/g2-icon.jpg|colour:C1=https://example.com/c1-icon.jpg"
    $image_entries = explode('|', $group_images_string);
    
    foreach ($image_entries as $entry) {
        $entry = trim($entry);
        if (empty($entry)) continue;
        
        // Split by equals sign to get key=value
        $equals_pos = strpos($entry, '=');
        if ($equals_pos === false) continue;
        
        $group_key = trim(substr($entry, 0, $equals_pos));
        $image_url = trim(substr($entry, $equals_pos + 1));
        
        // Validate URL
        if (!empty($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
            $group_images[$group_key] = $image_url;
        }
    }
    
    error_log('ECV Parse Group Images: Parsed images: ' . print_r($group_images, true));
    return $group_images;
}

/**
 * Detect if CSV uses new attribute-column based format
 * Checks for columns like "Attribute:Size", "Attribute:Finish", etc.
 */
function ecv_is_attribute_column_format($header_map) {
    foreach (array_keys($header_map) as $header) {
        if (strpos($header, 'attribute:') === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Extract attribute column names from header map
 * Returns array like ['size' => ['name' => 'size', 'display_type' => 'dropdown']]
 * Supports format: "Attribute:Size|Dropdown" or "Attribute:Size"
 */
function ecv_get_attribute_columns($header_map) {
    $attribute_columns = array();
    foreach (array_keys($header_map) as $header) {
        if (strpos($header, 'attribute:') === 0) {
            $full_attr = trim(substr($header, 10)); // Remove "attribute:" prefix
            
            // Check if display type is specified with pipe separator
            $display_type = 'buttons'; // Default
            $attr_name = $full_attr;
            
            if (strpos($full_attr, '|') !== false) {
                $parts = explode('|', $full_attr, 2);
                $attr_name = trim($parts[0]);
                $type_raw = strtolower(trim($parts[1]));
                
                // Map display type (support multiple variations)
                if (in_array($type_raw, array('dropdown', 'select'))) {
                    $display_type = 'dropdown';
                } elseif (in_array($type_raw, array('radio', 'radios'))) {
                    $display_type = 'radio';
                } elseif (in_array($type_raw, array('button', 'buttons'))) {
                    $display_type = 'buttons';
                }
            }
            
            $attribute_columns[$attr_name] = array(
                'name' => $attr_name,
                'display_type' => $display_type,
                'original_header' => $header
            );
        }
    }
    return $attribute_columns;
}

/**
 * Extract button image columns for attributes
 * Returns array like ['finish' => 'finish:button images']
 */
function ecv_get_button_image_columns($header_map) {
    $button_image_columns = array();
    foreach (array_keys($header_map) as $header) {
        if (strpos($header, ':') !== false) {
            $header_lc = strtolower($header);
            // Accept both singular and plural: "button image" or "button images"
            if (strpos($header_lc, 'button image') !== false) {
                // Extract attribute name before colon
                $parts = explode(':', $header);
                $attr_name = strtolower(trim($parts[0]));
                $button_image_columns[$attr_name] = $header;
            }
        }
    }
    return $button_image_columns;
}

/**
 * Extract tooltip columns for attributes
 * Returns array like ['cylinder type' => 'cylinder type:tooltip']
 */
function ecv_get_tooltip_columns($header_map) {
    $tooltip_columns = array();
    foreach (array_keys($header_map) as $header) {
        if (strpos($header, ':') !== false) {
            $header_lc = strtolower($header);
            if (strpos($header_lc, 'tooltip') !== false) {
                // Extract attribute name before colon
                $parts = explode(':', $header);
                $attr_name = strtolower(trim($parts[0]));
                $tooltip_columns[$attr_name] = $header;
            }
        }
    }
    return $tooltip_columns;
}

/**
 * Parse attribute value which can be:
 * 1. Simple value: "272"
 * 2. Group with values: "Signature Finish=Antique Brass, Gold Satin, Antique Brass Matte"
 * Returns array with 'group_name', 'values', 'is_grouped'
 */
function ecv_parse_attribute_value($value_string) {
    $value_string = trim($value_string);
    
    if (empty($value_string)) {
        return array(
            'group_name' => '',
            'values' => array(),
            'is_grouped' => false
        );
    }
    
    // Check if it contains a group definition (has "=" sign)
    if (strpos($value_string, '=') !== false) {
        $parts = explode('=', $value_string, 2);
        $group_name = trim($parts[0]);
        $values_string = trim($parts[1]);
        
        // Parse comma-separated values
        $values = array_map('trim', explode(',', $values_string));
        $values = array_filter($values); // Remove empty values
        
        return array(
            'group_name' => $group_name,
            'values' => $values,
            'is_grouped' => true
        );
    } else {
        // Simple value without group
        return array(
            'group_name' => '',
            'values' => array($value_string),
            'is_grouped' => false
        );
    }
}

/**
 * Parse button images which are pipe-separated
 * Example: "exp1.png|exp2.png|exp3.png"
 * Returns array of image URLs
 */
function ecv_parse_button_images($images_string) {
    $images_string = trim($images_string);
    
    if (empty($images_string)) {
        return array();
    }
    
    $images = array_map('trim', explode('|', $images_string));
    return array_filter($images); // Remove empty values
}

/**
 * Process product with new attribute-column format
 */
function ecv_process_product_attribute_column_format($group, $update_existing, $create_new, $dry_run, $header_map) {
    error_log('ECV Attribute Column Format: Processing group with ' . count($group['variations']) . ' variations');
    
    $result = array('action' => 'skipped', 'message' => '');
    
    if (empty($group['variations'])) {
        error_log('ECV Attribute Column Format: No variations found in group');
        return array('action' => 'error', 'message' => 'No variations found');
    }
    
    $first_row_data = $group['variations'][0]['data'];
    
    // Get product basic info
    $product_id = ecv_get_csv_value($first_row_data, $header_map, 'id');
    $product_name = ecv_get_csv_value($first_row_data, $header_map, 'name');
    $product_sku = ecv_get_csv_value($first_row_data, $header_map, 'sku');
    
    error_log('ECV Attribute Column Format: Product ID: ' . $product_id);
    error_log('ECV Attribute Column Format: Product Name: ' . $product_name);
    error_log('ECV Attribute Column Format: Product SKU: ' . $product_sku);
    
    if (empty($product_name)) {
        error_log('ECV Attribute Column Format: ERROR - Product name is empty');
        return array('action' => 'error', 'message' => 'Product name is required');
    }
    
    // Check if product exists
    $existing_product = null;
    if ($product_id && is_numeric($product_id)) {
        $existing_product = wc_get_product($product_id);
    } elseif ($product_sku) {
        $existing_product_id = wc_get_product_id_by_sku($product_sku);
        if ($existing_product_id) {
            $existing_product = wc_get_product($existing_product_id);
        }
    }
    
    if ($existing_product && !$update_existing) {
        error_log('ECV Attribute Column Format: SKIPPED - Product exists but update_existing is false');
        return array('action' => 'skipped', 'message' => 'Product already exists and update_existing is false');
    }
    
    if (!$existing_product && !$create_new) {
        error_log('ECV Attribute Column Format: SKIPPED - Product does not exist and create_new is false');
        return array('action' => 'skipped', 'message' => 'Product does not exist and create_new is false');
    }
    
    if ($dry_run) {
        return array('action' => 'dry_run', 'message' => 'Would ' . ($existing_product ? 'update' : 'create') . ' product: ' . $product_name);
    }
    
    // Parse the new format data
    $parsed_data = ecv_parse_attribute_column_data($group['variations'], $header_map);
    
    if (empty($parsed_data) || empty($parsed_data['cross_group_data'])) {
        error_log('ECV Attribute Column Format: ERROR - Could not parse attribute column format data');
        return array('action' => 'error', 'message' => 'Could not parse attribute column format data');
    }
    
    // Create or update product
    if ($existing_product) {
        $product_id = $existing_product->get_id();
        ecv_set_product_data_from_csv($existing_product, $first_row_data, $header_map);
        $existing_product->save();
        
        // Verify images were saved
        $saved_product = wc_get_product($product_id);
        $main_image_id = $saved_product->get_image_id();
        $gallery_image_ids = $saved_product->get_gallery_image_ids();
        error_log('ECV Attribute Column Format: Updating existing product ID: ' . $product_id);
        error_log('ECV Attribute Column Format: Verified main image ID: ' . ($main_image_id ? $main_image_id : 'NONE'));
        error_log('ECV Attribute Column Format: Verified gallery image IDs: ' . (!empty($gallery_image_ids) ? implode(', ', $gallery_image_ids) : 'NONE'));
        
        $result['action'] = 'updated';
        $result['message'] = 'Updated existing product: ' . $product_name . ' (SKU: ' . $product_sku . ')';
    } else {
        error_log('ECV Attribute Column Format: Creating new product...');
        $product_id = ecv_create_product_from_grouped_data($parsed_data['cross_group_data'], $header_map, $first_row_data);
        
        if (!$product_id) {
            error_log('ECV Attribute Column Format: ERROR - Failed to create product');
            return array('action' => 'error', 'message' => 'Failed to create product');
        }
        $result['action'] = 'created';
        $result['message'] = 'Created new product: ' . $product_name . ' (SKU: ' . $product_sku . ')';
    }
    
    // Save attribute display types FIRST so converter can read them
    if (!empty($parsed_data['attribute_display_types'])) {
        update_post_meta($product_id, '_ecv_attribute_display_types', $parsed_data['attribute_display_types']);
        error_log('ECV Attribute Column Format: Attribute display types saved: ' . print_r($parsed_data['attribute_display_types'], true));
    }
    
    // Save group button images FIRST so converter can use them
    if (!empty($parsed_data['group_images'])) {
        update_post_meta($product_id, '_ecv_group_images_data', $parsed_data['group_images']);
        error_log('ECV Attribute Column Format: Group button images saved: ' . print_r($parsed_data['group_images'], true));
    }

    // Save per-value button images FIRST so converter can use them
    if (!empty($parsed_data['value_images'])) {
        update_post_meta($product_id, '_ecv_value_button_images', $parsed_data['value_images']);
        error_log('ECV Attribute Column Format: Value button images saved: ' . print_r($parsed_data['value_images'], true));
    }

    // Save tooltips for dropdown attributes
    if (!empty($parsed_data['tooltips'])) {
        update_post_meta($product_id, '_ecv_dropdown_tooltips', $parsed_data['tooltips']);
        error_log('ECV Attribute Column Format: Dropdown tooltips saved: ' . print_r($parsed_data['tooltips'], true));
    }

    // Save the parsed data AFTER meta is stored
    ecv_save_grouped_data_as_traditional($product_id, $parsed_data['cross_group_data']);
    
    // Import product extra fields from CSV
    error_log('ECV Attribute Column Format: Importing extra fields for product ID: ' . $product_id);
    ecv_import_product_extra_fields_from_csv($product_id, $first_row_data, $header_map);
    
    return $result;
}

/**
 * Parse attribute column data from CSV rows
 * Returns array with 'cross_group_data' and 'group_images'
 */
function ecv_parse_attribute_column_data($variations, $header_map) {
    error_log('ECV Parse Attribute Column Data: Starting with ' . count($variations) . ' variations');
    
    $attribute_columns = ecv_get_attribute_columns($header_map);
    $button_image_columns = ecv_get_button_image_columns($header_map);
    $tooltip_columns = ecv_get_tooltip_columns($header_map);
    
    error_log('ECV Parse Attribute Column Data: Attribute columns: ' . print_r($attribute_columns, true));
    error_log('ECV Parse Attribute Column Data: Button image columns: ' . print_r($button_image_columns, true));
    error_log('ECV Parse Attribute Column Data: Tooltip columns: ' . print_r($tooltip_columns, true));
    
    $cross_group_data = array();
    $group_images = array();
    $group_definitions = array(); // Track all groups per attribute
    $attribute_display_types = array(); // Track display types for each attribute
    $tooltips = array(); // Track tooltips per attribute per value
    
    // First pass: collect all group definitions and display types
    foreach ($variations as $variation) {
        $row = $variation['data'];
        
        foreach ($attribute_columns as $attr_key => $attr_info) {
            $attr_name = is_array($attr_info) ? $attr_info['name'] : $attr_info;
            $display_type = is_array($attr_info) ? $attr_info['display_type'] : 'buttons';
            
            // Store display type for this attribute
            $attr_key_lower = strtolower($attr_name);
            if (!isset($attribute_display_types[$attr_key_lower])) {
                $attribute_display_types[$attr_key_lower] = $display_type;
            }
            
            // Get the original header for CSV lookup
            $header_lookup = is_array($attr_info) && isset($attr_info['original_header']) 
                ? $attr_info['original_header'] 
                : 'attribute:' . strtolower($attr_name);
            
            $attr_value = ecv_get_csv_value($row, $header_map, $header_lookup);
            
            if (!empty($attr_value)) {
                $parsed = ecv_parse_attribute_value($attr_value);
                
                if ($parsed['is_grouped'] && !empty($parsed['group_name'])) {
                    $attr_key = strtolower($attr_name);
                    
                    if (!isset($group_definitions[$attr_key])) {
                        $group_definitions[$attr_key] = array();
                    }
                    
                    if (!isset($group_definitions[$attr_key][$parsed['group_name']])) {
                        $group_definitions[$attr_key][$parsed['group_name']] = $parsed['values'];
                    }
                }
            }
        }
    }
    
    error_log('ECV Parse Attribute Column Data: Group definitions: ' . print_r($group_definitions, true));
    
    // Second pass: create combinations
    foreach ($variations as $row_index => $variation) {
        $row = $variation['data'];
        
        // Build attributes for this combination
        $combination_attributes = array();
        $combination_id_parts = array();
        $has_attributes = false;
        
        foreach ($attribute_columns as $attr_key => $attr_info) {
            $attr_name = is_array($attr_info) ? $attr_info['name'] : $attr_info;
            $display_type = is_array($attr_info) ? $attr_info['display_type'] : 'buttons';
            
            // Get the original header for CSV lookup
            $header_lookup = is_array($attr_info) && isset($attr_info['original_header']) 
                ? $attr_info['original_header'] 
                : 'attribute:' . strtolower($attr_name);
            
            $attr_value = ecv_get_csv_value($row, $header_map, $header_lookup);
            
            if (!empty($attr_value)) {
                $parsed = ecv_parse_attribute_value($attr_value);
                
                // Skip if value is "none" (treat as no value)
                $is_none_value = !empty($parsed['values']) && 
                                 count($parsed['values']) === 1 && 
                                 strtolower($parsed['values'][0]) === 'none';
                
                if (!empty($parsed['values']) && !$is_none_value) {
                    $has_attributes = true;
                    
                    // Properly capitalize attribute name
                    $formatted_attr_name = ucwords(str_replace('_', ' ', $attr_name));
                    
                    $combination_attributes[$formatted_attr_name] = array(
                        'group_name' => $parsed['is_grouped'] ? $parsed['group_name'] : '',
                        'values' => $parsed['values'],
                        'display_type' => $display_type
                    );
                    
                    // Build combination ID
                    if ($parsed['is_grouped']) {
                        $combination_id_parts[] = $parsed['group_name'];
                    } else {
                        $combination_id_parts[] = $parsed['values'][0];
                    }
                }
            }
        }
        
        // Skip if no attributes
        if (!$has_attributes || empty($combination_attributes)) {
            continue;
        }
        
        // Get combination properties
        $combination_price = ecv_get_csv_value($row, $header_map, 'combination price');
        $combination_sale_price = ecv_get_csv_value($row, $header_map, 'combination sale price');
        $combination_stock = ecv_get_csv_value($row, $header_map, 'combination stock');
        $combination_image = ecv_get_csv_value($row, $header_map, 'combination image');
        $combination_description = ecv_get_csv_value($row, $header_map, 'combination description');
        
        // Generate combination ID
        $combination_id = !empty($combination_id_parts) ? implode('_', $combination_id_parts) : 'combo_' . $row_index;
        $combination_id = sanitize_key($combination_id);
        
        $cross_group_data[] = array(
            'combination_id' => $combination_id,
            'attributes' => $combination_attributes,
            'combination_price' => floatval($combination_price),
            'combination_sale_price' => !empty($combination_sale_price) ? floatval($combination_sale_price) : '',
            'combination_stock' => !empty($combination_stock) ? intval($combination_stock) : '',
            'combination_image' => $combination_image,
            'combination_description' => $combination_description
        );
        
        error_log('ECV Parse Attribute Column Data: Created combination ' . $combination_id . ': ' . print_r($combination_attributes, true));
    }
    
    // Third pass: parse button images per value (per button) by row
    // For each row, if the attribute cell defines a group with values, and the corresponding
    // "{Attribute}:Button Images" cell has pipe-separated URLs, map each URL to the respective value
    $value_images = array();
    if (!empty($variations)) {
        foreach ($variations as $variation) {
            $row = $variation['data'];

            foreach ($button_image_columns as $attr_name => $column_name) {
                // Determine the attribute header used in this CSV for this attribute
                $attr_info = isset($attribute_columns[$attr_name]) ? $attribute_columns[$attr_name] : null;
                if (!$attr_info) continue;
                $attr_header = is_array($attr_info) && isset($attr_info['original_header'])
                    ? $attr_info['original_header']
                    : 'attribute:' . strtolower($attr_name);

                // Parse the attribute cell to know which group and which values are on this row
                $attr_cell = ecv_get_csv_value($row, $header_map, $attr_header);
                if (empty($attr_cell)) continue;
                $parsed_attr = ecv_parse_attribute_value($attr_cell);
                $group_name = $parsed_attr['group_name']; // Can be empty for simple attributes
                $values = $parsed_attr['values'];
                if (empty($values)) continue; // Only skip if no values at all

                // Parse images from this row for this attribute
                $images_string = ecv_get_csv_value($row, $header_map, $column_name);
                if (empty($images_string)) continue;
                $images = ecv_parse_button_images($images_string);
                if (empty($images)) continue;

                // Map images to values by index
                // For simple (non-grouped) attributes, use empty string as group key
                $formatted_attr_name = ucwords(str_replace('_', ' ', $attr_name));
                $group_key = !empty($group_name) ? $group_name : ''; // Use empty string for simple attributes
                $count = min(count($values), count($images));
                for ($i = 0; $i < $count; $i++) {
                    $val = trim($values[$i]);
                    $img = trim($images[$i]);
                    if (!empty($val) && !empty($img)) {
                        if (!isset($value_images[$formatted_attr_name])) $value_images[$formatted_attr_name] = array();
                        if (!isset($value_images[$formatted_attr_name][$group_key])) $value_images[$formatted_attr_name][$group_key] = array();
                        // Set only if not already set to preserve the first occurrence
                        if (empty($value_images[$formatted_attr_name][$group_key][$val])) {
                            $value_images[$formatted_attr_name][$group_key][$val] = $img;
                        }
                    }
                }

            }
        }
    }
    
    // Fourth pass: parse tooltips per value (only for dropdown attributes)
    if (!empty($tooltip_columns) && !empty($variations)) {
        foreach ($variations as $variation) {
            $row = $variation['data'];

            foreach ($tooltip_columns as $attr_name => $column_name) {
                // Determine the attribute header used in this CSV for this attribute
                $attr_info = isset($attribute_columns[$attr_name]) ? $attribute_columns[$attr_name] : null;
                if (!$attr_info) continue;
                
                // Only process tooltips for dropdown attributes
                $display_type = is_array($attr_info) ? $attr_info['display_type'] : 'buttons';
                if ($display_type !== 'dropdown') continue;
                
                $attr_header = is_array($attr_info) && isset($attr_info['original_header'])
                    ? $attr_info['original_header']
                    : 'attribute:' . strtolower($attr_name);

                // Parse the attribute cell to know which group and which values are on this row
                $attr_cell = ecv_get_csv_value($row, $header_map, $attr_header);
                if (empty($attr_cell)) continue;
                $parsed_attr = ecv_parse_attribute_value($attr_cell);
                $group_name = $parsed_attr['group_name']; // Can be empty for simple attributes
                $values = $parsed_attr['values'];
                if (empty($values)) continue;

                // Parse tooltips from this row for this attribute
                $tooltips_string = ecv_get_csv_value($row, $header_map, $column_name);
                if (empty($tooltips_string)) continue;
                
                // Split tooltips by pipe separator
                $tooltip_items = array_map('trim', explode('|', $tooltips_string));
                if (empty($tooltip_items)) continue;

                // Map tooltips to values by index
                $formatted_attr_name = ucwords(str_replace('_', ' ', $attr_name));
                $group_key = !empty($group_name) ? $group_name : ''; // Use empty string for simple attributes
                $count = min(count($values), count($tooltip_items));
                for ($i = 0; $i < $count; $i++) {
                    $val = trim($values[$i]);
                    $tooltip = trim($tooltip_items[$i]);
                    if (!empty($val) && !empty($tooltip)) {
                        if (!isset($tooltips[$formatted_attr_name])) $tooltips[$formatted_attr_name] = array();
                        if (!isset($tooltips[$formatted_attr_name][$group_key])) $tooltips[$formatted_attr_name][$group_key] = array();
                        // Set only if not already set to preserve the first occurrence
                        if (empty($tooltips[$formatted_attr_name][$group_key][$val])) {
                            $tooltips[$formatted_attr_name][$group_key][$val] = $tooltip;
                        }
                    }
                }
            }
        }
    }
    
    error_log('ECV Parse Attribute Column Data: Final cross-group data: ' . print_r($cross_group_data, true));
    error_log('ECV Parse Attribute Column Data: Final group images: ' . print_r($group_images, true));
    error_log('ECV Parse Attribute Column Data: Attribute display types: ' . print_r($attribute_display_types, true));
    error_log('ECV Parse Attribute Column Data: Tooltips: ' . print_r($tooltips, true));
    
    return array(
        'cross_group_data' => $cross_group_data,
        'group_images' => $group_images,
        'value_images' => $value_images,
        'attribute_display_types' => $attribute_display_types,
        'tooltips' => $tooltips
    );
}

// Process product with unified format
function ecv_process_product_unified_format($group, $update_existing, $create_new, $dry_run, $header_map) {
    error_log('ECV Unified Format: Processing group with ' . count($group['variations']) . ' variations');
    
    $result = array('action' => 'skipped', 'message' => '');
    
    if (empty($group['variations'])) {
        error_log('ECV Unified Format: No variations found in group');
        return array('action' => 'error', 'message' => 'No variations found');
    }
    
    // Check if this is the new attribute-column format
    if (ecv_is_attribute_column_format($header_map)) {
        error_log('ECV Unified Format: Detected attribute-column format');
        return ecv_process_product_attribute_column_format($group, $update_existing, $create_new, $dry_run, $header_map);
    }
    
    // Access the first row to determine the format
    $first_row_data = $group['variations'][0]['data'];
    
    // Get the 'Enable Cross Group' value to determine which format to use
    $enable_cross_group = strtolower(trim(ecv_get_csv_value($first_row_data, $header_map, 'enable cross group')));
    $is_cross_group = ($enable_cross_group === 'yes' || $enable_cross_group === '1' || $enable_cross_group === 'true');
    
    error_log('ECV Unified Format: Enable Cross Group value: "' . $enable_cross_group . '", Using cross-group: ' . ($is_cross_group ? 'Yes' : 'No'));
    
    if ($is_cross_group) {
        // Use cross-group format processing
        error_log('ECV Unified Format: Delegating to cross-group processing');
        return ecv_process_product_cross_group_format($group, $update_existing, $create_new, $dry_run, $header_map);
    } else {
        // Use traditional/combinational format processing
        error_log('ECV Unified Format: Delegating to traditional processing');
        return ecv_process_product_traditional_format($group, $update_existing, $create_new, $dry_run, $header_map);
    }
}

// Process product with traditional format from unified CSV
function ecv_process_product_traditional_format($group, $update_existing, $create_new, $dry_run, $header_map) {
    error_log('ECV Traditional Format: Processing group with ' . count($group['variations']) . ' variations');
    
    $result = array('action' => 'skipped', 'message' => '');
    
    if (empty($group['variations'])) {
        error_log('ECV Traditional Format: No variations found in group');
        return array('action' => 'error', 'message' => 'No variations found');
    }
    
    // Access the correct data structure
    $first_row_data = $group['variations'][0]['data'];
    
    // Get product basic info
    $product_id = ecv_get_csv_value($first_row_data, $header_map, 'id');
    $product_name = ecv_get_csv_value($first_row_data, $header_map, 'name');
    $product_sku = ecv_get_csv_value($first_row_data, $header_map, 'sku');
    
    error_log('ECV Traditional Format: Product ID: ' . $product_id);
    error_log('ECV Traditional Format: Product Name: ' . $product_name);
    error_log('ECV Traditional Format: Product SKU: ' . $product_sku);
    
    if (empty($product_name)) {
        error_log('ECV Traditional Format: ERROR - Product name is empty');
        return array('action' => 'error', 'message' => 'Product name is required');
    }
    
    // Check if product exists
    $existing_product = null;
    if ($product_id && is_numeric($product_id)) {
        $existing_product = wc_get_product($product_id);
    } elseif ($product_sku) {
        $existing_product_id = wc_get_product_id_by_sku($product_sku);
        if ($existing_product_id) {
            $existing_product = wc_get_product($existing_product_id);
        }
    }
    
    if ($existing_product && !$update_existing) {
        error_log('ECV Traditional Format: SKIPPED - Product exists but update_existing is false');
        return array('action' => 'skipped', 'message' => 'Product already exists and update_existing is false');
    }
    
    if (!$existing_product && !$create_new) {
        error_log('ECV Traditional Format: SKIPPED - Product does not exist and create_new is false');
        return array('action' => 'skipped', 'message' => 'Product does not exist and create_new is false');
    }
    
    if ($dry_run) {
        return array('action' => 'dry_run', 'message' => 'Would ' . ($existing_product ? 'update' : 'create') . ' product: ' . $product_name);
    }
    
    // Create or update product
    if ($existing_product) {
        $product_id = $existing_product->get_id();
        // Update basic product data
        ecv_set_product_data_from_csv($existing_product, $first_row_data, $header_map);
        $existing_product->save();
        $result['action'] = 'updated';
        $result['message'] = 'Updated existing product: ' . $product_name . ' (SKU: ' . $product_sku . ')';
        error_log('ECV Traditional Format: Updating existing product ID: ' . $product_id);
    } else {
        error_log('ECV Traditional Format: Creating new product...');
        $product = new WC_Product_Simple();
        ecv_set_product_data_from_csv($product, $first_row_data, $header_map);
        $product_id = $product->save();
        error_log('ECV Traditional Format: Product creation result: ' . ($product_id ? 'SUCCESS (ID: ' . $product_id . ')' : 'FAILED'));
        
        if (!$product_id) {
            error_log('ECV Traditional Format: ERROR - Failed to create product');
            return array('action' => 'error', 'message' => 'Failed to create product');
        }
        $result['action'] = 'created';
        $result['message'] = 'Created new product: ' . $product_name . ' (SKU: ' . $product_sku . ')';
    }
    
    // Clear existing variation data to avoid conflicts
    ecv_delete_variations_data($product_id);
    
    // Process all variations for this product using traditional format
    foreach ($group['variations'] as $variation) {
        ecv_set_variant_data_from_csv($product_id, $variation['data'], $header_map);
    }
    
    // Import product extra fields from CSV
    error_log('ECV Traditional Format: Importing extra fields for product ID: ' . $product_id);
    ecv_import_product_extra_fields_from_csv($product_id, $first_row_data, $header_map);
    
    return $result;
}
