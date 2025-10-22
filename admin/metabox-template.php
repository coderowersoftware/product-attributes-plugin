<div id="ecv-variations-admin">
    <?php if (isset($is_csv_imported) && $is_csv_imported): ?>
    <!-- CSV Import Notice -->
    <div class="notice notice-info" style="margin: 0 0 20px 0; padding: 10px 15px; background: #e8f4f8; border-left: 4px solid #00a0d2;">
        <h4 style="margin: 5px 0 10px 0;"><?php _e('📦 CSV-Imported Product (Read-Only Variations)', 'exp-custom-variations'); ?></h4>
        <p style="margin: 0 0 10px 0;">
            <strong><?php _e('This product was imported from CSV and uses the advanced attribute-column format.', 'exp-custom-variations'); ?></strong>
        </p>
        <p style="margin: 0 0 10px 0;">
            <?php _e('✓ You can view variation data below', 'exp-custom-variations'); ?><br/>
            <?php _e('✓ You can update product name, description, price, images, etc.', 'exp-custom-variations'); ?><br/>
            <?php _e('✓ Clicking "Update" will NOT modify your variations', 'exp-custom-variations'); ?>
        </p>
        <p style="margin: 0; padding: 10px; background: #fff; border-left: 3px solid #ffb900;">
            <strong>⚠️ <?php _e('To modify variations:', 'exp-custom-variations'); ?></strong><br/>
            <?php _e('1. Export your product to CSV', 'exp-custom-variations'); ?><br/>
            <?php _e('2. Edit the CSV file', 'exp-custom-variations'); ?><br/>
            <?php _e('3. Re-import to update variations', 'exp-custom-variations'); ?>
        </p>
    </div>
    <?php endif; ?>
    
    <!-- Global Display Settings -->
    <div class="ecv-global-display-section">
        <h4>Global Display Settings</h4>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="ecv_show_images"><?php _e('Show Images', 'exp-custom-variations'); ?></label>
                </th>
                <td>
                    <?php
                    $show_images = get_post_meta($post->ID, '_ecv_show_images', true) ?: 'yes';
                    ?>
                    <input type="checkbox" name="ecv_show_images" id="ecv_show_images" value="yes" <?php checked($show_images, 'yes'); ?> />
                    <label for="ecv_show_images"><?php _e('Show variant images (for buttons and radio)', 'exp-custom-variations'); ?></label>
                    <p class="description"><?php _e('This setting applies to all attributes that support images (buttons and radio buttons).', 'exp-custom-variations'); ?></p>
                </td>
            </tr>
        </table>
    </div>
    
    <hr/>
    
    <!-- Variation Mode Selection -->
    <div class="ecv-variation-mode-section">
        <h4>Variation Mode</h4>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="ecv_variation_mode"><?php _e('Variation Mode', 'exp-custom-variations'); ?></label>
                </th>
                <td>
                    <?php
                    $variation_mode = get_post_meta($post->ID, '_ecv_variation_mode', true) ?: 'traditional';
                    ?>
                    <select name="ecv_variation_mode" id="ecv_variation_mode">
                        <option value="traditional" <?php selected($variation_mode, 'traditional'); ?>><?php _e('Traditional Variations', 'exp-custom-variations'); ?></option>
                        <option value="cross_group" <?php selected($variation_mode, 'cross_group'); ?>><?php _e('Cross Group Pricing', 'exp-custom-variations'); ?></option>
                    </select>
                    <p class="description"><?php _e('Choose between traditional attribute-based variations or cross-group pricing format that allows any combination of groups with individual pricing per combination.', 'exp-custom-variations'); ?></p>
                </td>
            </tr>
        </table>
    </div>
    
    <hr/>
    
    <!-- Traditional Attributes Section -->
    <div class="ecv-attributes-section" id="ecv-attributes-section" style="display: <?php echo $variation_mode === 'traditional' ? 'block' : 'none'; ?>;">
        <h4>Traditional Attributes</h4>
        <div id="ecv-attributes-list"></div>
        <button type="button" id="ecv-add-attribute" class="button">Add Attribute</button>
    </div>
    
    <!-- Cross Group Pricing Section -->
    <div class="ecv-cross-group-section" id="ecv-cross-group-section" style="display: <?php echo $variation_mode === 'cross_group' ? 'block' : 'none'; ?>;">
        <h4>Cross Group Pricing</h4>
        <p class="description">Enter cross-group data just like in Excel/CSV format. Each row represents a combination with individual pricing.</p>
        
        <!-- Groups Definition Section -->
        <div class="ecv-groups-definition-section" style="margin-bottom: 20px;">
            <h5>Groups Definition</h5>
            <p class="description">Define your groups first (e.g., finish:G1=Matte,Glossy|G2=Textured;colour:C1=Red,Blue|C2=White,Yellow)</p>
            <textarea id="ecv-groups-definition" class="ecv-groups-definition" rows="3" placeholder="finish:G1=Matte,Glossy|G2=Textured,Smooth;colour:C1=Red,Blue,Green,Black|C2=White,Yellow,Purple,Orange"></textarea>
            <button type="button" id="ecv-parse-groups-definition" class="button">Parse & Generate Combinations</button>
        </div>
        
        <!-- Group Images Management Section -->
        <div class="ecv-group-images-section" id="ecv-group-images-section" style="margin-bottom: 20px; display: none;">
            <h5>Group Button Images</h5>
            <p class="description">Set images for each group that will appear on the selection buttons (separate from combination product images)</p>
            <div id="ecv-group-images-list"></div>
        </div>
        
        <!-- Excel-like Table for Combinations -->
        <div class="ecv-cross-table-section">
            <h5>Combinations (Excel-like Input)</h5>
            <div class="ecv-table-container">
                <table id="ecv-cross-combinations-table" class="ecv-cross-table">
                    <thead>
                        <tr>
                            <th>Combination Name</th>
                            <th>Price</th>
                            <th>Sale Price</th>
                            <th>Stock</th>
                            <th>Image URL</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ecv-cross-combinations-body">
                        <!-- Rows will be added dynamically -->
                    </tbody>
                </table>
                <button type="button" id="ecv-add-combination-row" class="button">Add Combination Row</button>
            </div>
        </div>
    </div>

    <hr/>

    <!-- Combinations Matrix -->
    <div class="ecv-combinations-section">
        <h4>Variations</h4>
        <div class="ecv-bulk-actions">
            <button type="button" id="ecv-create-variations" class="button">Create All Possible Variations</button>
            <button type="button" id="ecv-bulk-edit" class="button">Bulk Edit</button>
        </div>
        <div id="ecv-combinations-list"></div>
    </div>
</div>

<!-- Hidden inputs for data -->
<input type="hidden" name="ecv_variations_data" id="ecv_variations_data" value='<?php echo esc_attr(json_encode($data)); ?>' />
<input type="hidden" name="ecv_combinations_data" id="ecv_combinations_data" value='<?php echo esc_attr(json_encode($combinations)); ?>' />
<input type="hidden" name="ecv_cross_group_data" id="ecv_cross_group_data" value='<?php echo esc_attr(json_encode(isset($cross_group_data) ? $cross_group_data : [])); ?>' />

<script>
window.ecv_variations_data = <?php echo json_encode($data); ?>;
window.ecv_combinations_data = <?php echo json_encode($combinations); ?>;
window.ecv_cross_group_data = <?php echo json_encode(isset($cross_group_data) ? $cross_group_data : []); ?>;
window.ecv_variation_mode = '<?php echo $variation_mode; ?>';
window.ecv_group_images_data = <?php echo json_encode($group_images_data); ?>;
window.ecv_is_csv_imported = <?php echo isset($is_csv_imported) && $is_csv_imported ? 'true' : 'false'; ?>;

// If this is a CSV-imported product, display variations as read-only
if (window.ecv_is_csv_imported) {
    console.log('ECV: CSV-imported product detected - variations are read-only');
    
    // Display variation data in a read-only table below
    jQuery(document).ready(function($) {
        // Show combinations data in read-only format
        if (window.ecv_combinations_data && window.ecv_combinations_data.length > 0) {
            var html = '<div style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">';
            html += '<h4>📊 Current Variations (' + window.ecv_combinations_data.length + ' combinations)</h4>';
            html += '<p class="description">These variations are managed via CSV import. To modify them, export → edit → re-import the CSV.</p>';
            html += '<div style="max-height: 400px; overflow-y: auto; margin-top: 10px;">';
            html += '<table class="wp-list-table widefat fixed striped" style="background: white;">';
            html += '<thead><tr><th>SKU</th><th>Attributes</th><th>Price</th><th>Sale Price</th><th>Stock</th><th>Status</th></tr></thead>';
            html += '<tbody>';
            
            window.ecv_combinations_data.forEach(function(combo) {
                var attrs = [];
                if (combo.variants) {
                    combo.variants.forEach(function(v) {
                        attrs.push(v.attribute + ': ' + v.name + (v.group_name ? ' (' + v.group_name + ')' : ''));
                    });
                }
                html += '<tr>';
                html += '<td>' + (combo.sku || '-') + '</td>';
                html += '<td>' + attrs.join(', ') + '</td>';
                html += '<td>₹' + (combo.price || '0') + '</td>';
                html += '<td>' + (combo.sale_price ? '₹' + combo.sale_price : '-') + '</td>';
                html += '<td>' + (combo.stock || '-') + '</td>';
                html += '<td>' + (combo.enabled === false ? '❌ Disabled' : '✓ Enabled') + '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table></div></div>';
            $('.ecv-combinations-section').html(html);
        }
        
        // Hide admin UI controls for CSV-imported products
        $('#ecv-attributes-section, #ecv-cross-group-section').css('opacity', '0.5').find('input, textarea, button, select').prop('disabled', true);
    });
}
</script>
<hr/>
<h4>Conditional Pricing Rules</h4>
<div id="ecv-conditional-rules-admin"></div>
<input type="hidden" name="ecv_conditional_rules" id="ecv_conditional_rules" value='<?php echo esc_attr(json_encode(isset($rules) ? $rules : [])); ?>' />
<small>Example: If Size = Large and Finish = Nickel, set price to 100. Rules are checked in order; first match wins.</small> 