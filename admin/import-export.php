<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Add import/export menu
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=product',
        __('Import/Export Variants', 'exp-custom-variations'),
        __('Import/Export Variants', 'exp-custom-variations'),
        'manage_woocommerce',
        'ecv-import-export',
        'ecv_render_import_export_page'
    );
});

function ecv_render_import_export_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Import/Export Custom Variations', 'exp-custom-variations'); ?></h1>
        
        <div class="ecv-import-export-container">
            <!-- Export Section -->
            <div class="postbox" style="margin-top: 20px;">
                <h2 class="hndle"><?php _e('Export Variants', 'exp-custom-variations'); ?></h2>
                <div class="inside">
                    <p><?php _e('Export all products with their custom variations to a CSV file.', 'exp-custom-variations'); ?></p>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('ecv_export_nonce', 'ecv_export_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Export Options', 'exp-custom-variations'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="export_product_data" value="1" checked />
                                        <?php _e('Include basic product data (title, description, price, etc.)', 'exp-custom-variations'); ?>
                                    </label><br/>
                                    <label>
                                        <input type="checkbox" name="export_variant_data" value="1" checked />
                                        <?php _e('Include custom variant data', 'exp-custom-variations'); ?>
                                    </label><br/>
                                    <label>
                                        <input type="checkbox" name="export_group_pricing" value="1" />
                                        <?php _e('Include group-based pricing data', 'exp-custom-variations'); ?>
                                    </label><br/>
                                    <label>
                                        <input type="checkbox" name="export_images" value="1" checked />
                                        <?php _e('Include image URLs', 'exp-custom-variations'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Product Selection', 'exp-custom-variations'); ?></th>
                                <td>
                                    <label>
                                        <input type="radio" name="export_scope" value="all" checked />
                                        <?php _e('All products', 'exp-custom-variations'); ?>
                                    </label><br/>
                                    <label>
                                        <input type="radio" name="export_scope" value="variants_only" />
                                        <?php _e('Only products with custom variants', 'exp-custom-variations'); ?>
                                    </label><br/>
                                    <label>
                                        <input type="radio" name="export_scope" value="specific" />
                                        <?php _e('Specific product IDs:', 'exp-custom-variations'); ?>
                                        <input type="text" name="specific_ids" placeholder="1,2,3,4" style="margin-left: 10px;" />
                                    </label>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="ecv_export" class="button-primary" value="<?php _e('Export to CSV', 'exp-custom-variations'); ?>" />
                        </p>
                    </form>
                </div>
            </div>

            <!-- Import Section -->
            <div class="postbox" style="margin-top: 20px;">
                <h2 class="hndle"><?php _e('Import Variants', 'exp-custom-variations'); ?></h2>
                <div class="inside">
                    <p><?php _e('Import products and their custom variations from a CSV file.', 'exp-custom-variations'); ?></p>
                    
                    <form method="post" action="" enctype="multipart/form-data">
                        <?php wp_nonce_field('ecv_import_nonce', 'ecv_import_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('CSV File', 'exp-custom-variations'); ?></th>
                                <td>
                                    <input type="file" name="import_file" accept=".csv" required />
                                    <p class="description">
                                        <?php _e('Upload a CSV file with product and variant data. ', 'exp-custom-variations'); ?>
                                        <a href="#" id="download-template"><?php _e('Download templates', 'exp-custom-variations'); ?></a>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Import Options', 'exp-custom-variations'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="update_existing" value="1" />
                                        <?php _e('Update existing products (match by ID or SKU)', 'exp-custom-variations'); ?>
                                    </label><br/>
                                    <label>
                                        <input type="checkbox" name="create_new" value="1" checked />
                                        <?php _e('Create new products', 'exp-custom-variations'); ?>
                                    </label><br/>
                                    <label>
                                        <input type="checkbox" name="dry_run" value="1" />
                                        <?php _e('Dry run (preview only, don\'t save)', 'exp-custom-variations'); ?>
                                    </label><br/>
                                    <div style="margin-top: 15px; padding: 10px; background: #f0f8ff; border: 1px solid #0073aa; border-radius: 4px;">
                                        <strong style="color: #0073aa;"><?php _e('New Unified Format Support:', 'exp-custom-variations'); ?></strong><br/>
                                        <?php _e('The plugin now automatically detects format type from your CSV. Use the "Unified Format" template for the best experience.', 'exp-custom-variations'); ?>
                                    </div>
                                    <details style="margin-top: 10px;">
                                        <summary style="cursor: pointer; color: #666; font-size: 12px;"><?php _e('Legacy Format Options (Deprecated)', 'exp-custom-variations'); ?></summary>
                                        <div style="margin-top: 10px; padding-left: 15px; border-left: 3px solid #ccc;">
                                            <label>
                                                <input type="checkbox" name="import_cross_group_format" value="1" />
                                                <?php _e('Force cross-group format processing (for old CSV files)', 'exp-custom-variations'); ?>
                                            </label><br/>
                                            <label>
                                                <input type="checkbox" name="import_grouped_format" value="1" />
                                                <?php _e('Force grouped format processing (for old CSV files)', 'exp-custom-variations'); ?>
                                            </label><br/>
                                            <small style="color: #666;"><?php _e('Note: These options are only needed for legacy CSV files. New unified format is automatically detected.', 'exp-custom-variations'); ?></small>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="ecv_import" class="button-primary" value="<?php _e('Import CSV', 'exp-custom-variations'); ?>" />
                        </p>
                    </form>
                </div>
            </div>

            <!-- Template Download -->
            <div class="postbox" style="margin-top: 20px;">
                <h2 class="hndle"><?php _e('CSV Template', 'exp-custom-variations'); ?></h2>
                <div class="inside">
                    <p><?php _e('Download the CSV template to import products with custom variations.', 'exp-custom-variations'); ?></p>
                    
                    <div style="display: flex; gap: 15px; margin-bottom: 15px; flex-wrap: wrap;">
                        <form method="post" action="" style="display: inline-block;">
                            <?php wp_nonce_field('ecv_template_nonce', 'ecv_template_nonce'); ?>
                            <input type="hidden" name="template_type" value="attribute_column" />
                            <input type="submit" name="ecv_download_template" class="button button-primary button-large" 
                                   value="<?php _e('📥 Download CSV Template', 'exp-custom-variations'); ?>" 
                                   style="background: linear-gradient(45deg, #0073aa, #005f8a); border-color: #005f8a; box-shadow: 0 2px 4px rgba(0,115,170,0.3);" />
                        </form>
                    </div>
                    
                    <div style="margin: 15px 0; padding: 12px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;">
                        <strong>✨ <?php _e('Template Features:', 'exp-custom-variations'); ?></strong>
                        <ul style="margin: 8px 0 0 20px; padding: 0;">
                            <li><?php _e('Dynamic attribute columns - add as many as you need', 'exp-custom-variations'); ?></li>
                            <li><?php _e('Per-button images with pipe-separated URLs', 'exp-custom-variations'); ?></li>
                            <li><?php _e('Supports both simple and grouped attributes', 'exp-custom-variations'); ?></li>
                            <li><?php _e('Display type control per attribute (buttons|dropdown|radio)', 'exp-custom-variations'); ?></li>
                            <li><?php _e('Combination pricing with stock and images', 'exp-custom-variations'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="notice notice-info">
                        <p><strong><?php _e('How to Use:', 'exp-custom-variations'); ?></strong></p>
                        <ol style="margin: 8px 0 0 20px; padding: 0;">
                            <li><?php _e('Download the template and open it in Excel or Google Sheets', 'exp-custom-variations'); ?></li>
                            <li><?php _e('Follow the example rows to structure your data', 'exp-custom-variations'); ?></li>
                            <li><?php _e('Add more attribute columns as needed (Attribute:{Name}|display_type)', 'exp-custom-variations'); ?></li>
                            <li><?php _e('For button images, use {Attribute}:Button Images column with pipe-separated URLs', 'exp-custom-variations'); ?></li>
                            <li><?php _e('Save and upload your CSV file', 'exp-custom-variations'); ?></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .ecv-import-export-container .postbox {
            max-width: 800px;
        }
        .form-table th {
            width: 200px;
        }
        .form-table input[type="text"] {
            width: 300px;
        }
    </style>

    <script>
        document.getElementById('download-template').addEventListener('click', function(e) {
            e.preventDefault();
            // Scroll to the template section
            document.querySelector('.postbox:last-of-type').scrollIntoView({ behavior: 'smooth' });
        });
    </script>
    <?php
}

// Handle export
add_action('admin_init', function() {
    if (isset($_POST['ecv_export']) && wp_verify_nonce($_POST['ecv_export_nonce'], 'ecv_export_nonce')) {
        ecv_handle_export();
    }
});

// Handle import
add_action('admin_init', function() {
    if (isset($_POST['ecv_import']) && wp_verify_nonce($_POST['ecv_import_nonce'], 'ecv_import_nonce')) {
        ecv_handle_import();
    }
});

// Handle template download
add_action('admin_init', function() {
    if (isset($_POST['ecv_download_template']) && wp_verify_nonce($_POST['ecv_template_nonce'], 'ecv_template_nonce')) {
        ecv_download_template();
    }
});
