<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$extra_fields = ecv_get_product_extra_fields($post->ID);

// Debug: Log what we retrieved
error_log('ECV Admin Metabox: Loading for product ID ' . $post->ID);
error_log('ECV Admin Metabox: Retrieved ' . count($extra_fields) . ' fields from database');
if (!empty($extra_fields)) {
    error_log('ECV Admin Metabox: Fields data: ' . print_r($extra_fields, true));
}

// Count actual fields (non-empty)
$actual_field_count = 0;
foreach ($extra_fields as $field) {
    if (!empty($field['field_key']) && !empty($field['field_value'])) {
        $actual_field_count++;
    }
}

// Ensure at least 3 empty slots
$min_fields = 3;
$current_count = count($extra_fields);
if ($current_count < $min_fields) {
    for ($i = $current_count; $i < $min_fields; $i++) {
        $extra_fields[] = array(
            'field_key' => '',
            'field_type' => 'text',
            'field_value' => ''
        );
    }
}
?>

<div class="ecv-product-extra-fields-wrapper">
    <?php if ($actual_field_count > 0) : ?>
    <div class="ecv-import-success-notice" style="background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin-bottom: 20px; border-radius: 3px;">
        <h3 style="margin: 0 0 10px 0; color: #155724;">✅ Extra Fields Detected!</h3>
        <p style="margin: 0; line-height: 1.6; color: #155724;">
            <strong><?php echo $actual_field_count; ?> field<?php echo $actual_field_count > 1 ? 's' : ''; ?> found</strong> for this product. 
            <?php if (get_post_meta($post->ID, '_ecv_imported_from_csv', true)) : ?>
            <span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; margin-left: 5px;">IMPORTED FROM CSV</span>
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>
    
    <div class="ecv-extra-fields-info" style="background: #e7f3ff; padding: 15px; border-left: 4px solid #0073aa; margin-bottom: 20px; border-radius: 3px;">
        <h3 style="margin: 0 0 10px 0; color: #0073aa;">📋 Product Extra Fields</h3>
        <p style="margin: 0 0 10px 0; line-height: 1.6;">
            Add custom fields (images, text, PDFs) for this product. Each field gets a unique shortcode for use in Elementor or anywhere else.
        </p>
        <p style="margin: 0; font-size: 13px; color: #666;">
            <strong>Usage Example:</strong> <code>[ecv_field key="banner_image"]</code>
        </p>
    </div>

    <table class="widefat ecv-extra-fields-table" style="border: 1px solid #ddd; margin-bottom: 20px;">
        <thead>
            <tr style="background: #f9f9f9;">
                <th style="padding: 12px; width: 5%; text-align: center;">#</th>
                <th style="padding: 12px; width: 25%;">Field Key <span style="color: red;">*</span></th>
                <th style="padding: 12px; width: 15%;">Type <span style="color: red;">*</span></th>
                <th style="padding: 12px; width: 50%;">Value <span style="color: red;">*</span></th>
                <th style="padding: 12px; width: 5%;"></th>
            </tr>
        </thead>
        <tbody id="ecv-fields-tbody">
            <?php foreach ($extra_fields as $index => $field) : 
                $field_key = isset($field['field_key']) ? esc_attr($field['field_key']) : '';
                $field_type = isset($field['field_type']) ? esc_attr($field['field_type']) : 'text';
                $field_value = isset($field['field_value']) ? $field['field_value'] : '';
            ?>
            <tr class="ecv-field-row" data-index="<?php echo $index; ?>">
                <td style="padding: 12px; text-align: center; background: #f9f9f9; font-weight: bold;">
                    <?php echo ($index + 1); ?>
                </td>
                <td style="padding: 12px;">
                    <input type="text" 
                           name="ecv_field_keys[]" 
                           value="<?php echo $field_key; ?>" 
                           placeholder="e.g., banner_image"
                           style="width: 100%; padding: 6px 8px;"
                           class="ecv-field-key-input">
                    <small style="display: block; margin-top: 5px; color: #666;">
                        Lowercase, underscores only
                    </small>
                    <?php if (!empty($field_key)) : ?>
                    <div style="margin-top: 8px; padding: 6px 10px; background: #f0f0f0; border-radius: 3px;">
                        <strong>Shortcode:</strong> <code style="color: #0073aa;">[ecv_field key="<?php echo $field_key; ?>"]</code>
                    </div>
                    <?php endif; ?>
                </td>
                <td style="padding: 12px;">
                    <select name="ecv_field_types[]" 
                            style="width: 100%; padding: 6px 8px;"
                            class="ecv-field-type-select">
                        <option value="image" <?php selected($field_type, 'image'); ?>>Image</option>
                        <option value="text" <?php selected($field_type, 'text'); ?>>Text</option>
                        <option value="pdf" <?php selected($field_type, 'pdf'); ?>>PDF</option>
                    </select>
                </td>
                <td style="padding: 12px;">
                    <?php if ($field_type === 'text') : ?>
                        <!-- Text Field -->
                        <textarea name="ecv_field_values[]" 
                                  rows="3" 
                                  placeholder="Enter text here..."
                                  style="width: 100%; padding: 6px 8px;"
                                  class="ecv-field-value-input"><?php echo esc_textarea($field_value); ?></textarea>
                    <?php else : ?>
                        <!-- Image/PDF Field -->
                        <input type="text" 
                               name="ecv_field_values[]" 
                               value="<?php echo esc_attr($field_value); ?>" 
                               placeholder="<?php echo $field_type === 'image' ? 'Image URL' : 'PDF URL'; ?>"
                               style="width: 100%; padding: 6px 8px; margin-bottom: 5px;"
                               class="ecv-field-value-input">
                        <button type="button" 
                                class="button ecv-upload-btn"
                                data-field-type="<?php echo $field_type; ?>"
                                data-row-index="<?php echo $index; ?>">
                            <span class="dashicons dashicons-upload" style="vertical-align: middle;"></span>
                            Upload <?php echo ucfirst($field_type); ?>
                        </button>
                        <?php if ($field_type === 'image' && !empty($field_value)) : ?>
                        <div style="margin-top: 10px;">
                            <img src="<?php echo esc_url($field_value); ?>" 
                                 style="max-width: 200px; height: auto; border: 1px solid #ddd; border-radius: 3px;">
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td style="padding: 12px; text-align: center;">
                    <?php if ($index >= 3 || !empty($field_key)) : // Only show delete for rows 4+ or if has data ?>
                    <button type="button" 
                            class="button ecv-delete-field-btn"
                            style="color: #b32d2e;"
                            title="Remove this field">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <button type="button" class="button button-primary button-large ecv-add-field-btn" style="margin-bottom: 10px;">
        <span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span> Add Another Field
    </button>
    
    <p style="color: #666; font-size: 13px; margin-top: 15px;">
        <strong>💡 Tip:</strong> Leave empty rows blank - they won't be saved. Fill in at least the Field Key and Value to save a field.
    </p>
</div>

<style>
.ecv-extra-fields-table td {
    vertical-align: top;
}
.ecv-field-row:hover {
    background-color: #f9f9f9;
}
.ecv-delete-field-btn:hover .dashicons {
    color: #dc3232;
}
</style>

<script>
(function($) {
    'use strict';
    
    if (typeof $ === 'undefined') {
        console.error('ECV: jQuery not available');
        return;
    }
    
    $(document).ready(function() {
        console.log('ECV Extra Fields: Script loaded');
        
        var rowCounter = <?php echo count($extra_fields); ?>;
        
        // Add field button
        $('.ecv-add-field-btn').on('click', function(e) {
            e.preventDefault();
            console.log('ECV: Adding new field row');
            
            var newRow = '<tr class="ecv-field-row" data-index="' + rowCounter + '">' +
                '<td style="padding: 12px; text-align: center; background: #f9f9f9; font-weight: bold;">' + (rowCounter + 1) + '</td>' +
                '<td style="padding: 12px;">' +
                '<input type="text" name="ecv_field_keys[]" value="" placeholder="e.g., banner_image" style="width: 100%; padding: 6px 8px;" class="ecv-field-key-input">' +
                '<small style="display: block; margin-top: 5px; color: #666;">Lowercase, underscores only</small>' +
                '</td>' +
                '<td style="padding: 12px;">' +
                '<select name="ecv_field_types[]" style="width: 100%; padding: 6px 8px;" class="ecv-field-type-select">' +
                '<option value="image">Image</option>' +
                '<option value="text">Text</option>' +
                '<option value="pdf">PDF</option>' +
                '</select>' +
                '</td>' +
                '<td style="padding: 12px;">' +
                '<input type="text" name="ecv_field_values[]" value="" placeholder="Image URL" style="width: 100%; padding: 6px 8px; margin-bottom: 5px;" class="ecv-field-value-input">' +
                '<button type="button" class="button ecv-upload-btn" data-field-type="image" data-row-index="' + rowCounter + '">' +
                '<span class="dashicons dashicons-upload" style="vertical-align: middle;"></span> Upload Image' +
                '</button>' +
                '</td>' +
                '<td style="padding: 12px; text-align: center;">' +
                '<button type="button" class="button ecv-delete-field-btn" style="color: #b32d2e;" title="Remove this field">' +
                '<span class="dashicons dashicons-trash"></span>' +
                '</button>' +
                '</td>' +
                '</tr>';
            
            $('#ecv-fields-tbody').append(newRow);
            rowCounter++;
            updateRowNumbers();
        });
        
        // Delete field button
        $(document).on('click', '.ecv-delete-field-btn', function(e) {
            e.preventDefault();
            if (confirm('Remove this field?')) {
                $(this).closest('tr').remove();
                updateRowNumbers();
            }
        });
        
        // Type change handler
        $(document).on('change', '.ecv-field-type-select', function() {
            var $row = $(this).closest('tr');
            var $valueCell = $row.find('td').eq(3);
            var fieldType = $(this).val();
            var currentValue = $row.find('.ecv-field-value-input').val();
            var rowIndex = $row.data('index');
            
            if (fieldType === 'text') {
                $valueCell.html(
                    '<textarea name="ecv_field_values[]" rows="3" placeholder="Enter text here..." ' +
                    'style="width: 100%; padding: 6px 8px;" class="ecv-field-value-input">' + currentValue + '</textarea>'
                );
            } else {
                $valueCell.html(
                    '<input type="text" name="ecv_field_values[]" value="' + currentValue + '" ' +
                    'placeholder="' + (fieldType === 'image' ? 'Image URL' : 'PDF URL') + '" ' +
                    'style="width: 100%; padding: 6px 8px; margin-bottom: 5px;" class="ecv-field-value-input">' +
                    '<button type="button" class="button ecv-upload-btn" data-field-type="' + fieldType + '" data-row-index="' + rowIndex + '">' +
                    '<span class="dashicons dashicons-upload" style="vertical-align: middle;"></span> Upload ' + 
                    (fieldType === 'image' ? 'Image' : 'PDF') + '</button>'
                );
            }
        });
        
        // Upload button handler
        $(document).on('click', '.ecv-upload-btn', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $input = $button.siblings('.ecv-field-value-input');
            var fieldType = $button.data('field-type');
            
            if (typeof wp !== 'undefined' && typeof wp.media !== 'undefined') {
                var frame = wp.media({
                    title: 'Select ' + (fieldType === 'image' ? 'Image' : 'PDF'),
                    button: { text: 'Use this file' },
                    library: { type: fieldType === 'image' ? 'image' : 'application/pdf' },
                    multiple: false
                });
                
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $input.val(attachment.url);
                    
                    if (fieldType === 'image') {
                        var $preview = $button.siblings('div');
                        if ($preview.length) {
                            $preview.find('img').attr('src', attachment.url);
                        } else {
                            $button.after('<div style="margin-top: 10px;"><img src="' + attachment.url + '" style="max-width: 200px; height: auto; border: 1px solid #ddd; border-radius: 3px;"></div>');
                        }
                    }
                });
                
                frame.open();
            } else {
                alert('Media uploader not available. Please enter the URL manually.');
            }
        });
        
        // Show shortcode when key is typed
        $(document).on('input', '.ecv-field-key-input', function() {
            var $cell = $(this).parent();
            var key = $(this).val().trim();
            
            $cell.find('.ecv-shortcode-display').remove();
            
            if (key) {
                $(this).siblings('small').after(
                    '<div class="ecv-shortcode-display" style="margin-top: 8px; padding: 6px 10px; background: #f0f0f0; border-radius: 3px;">' +
                    '<strong>Shortcode:</strong> <code style="color: #0073aa;">[ecv_field key="' + key + '"]</code>' +
                    '</div>'
                );
            }
        });
        
        // Update row numbers
        function updateRowNumbers() {
            $('#ecv-fields-tbody tr').each(function(index) {
                $(this).find('td').first().text(index + 1);
                $(this).attr('data-index', index);
            });
        }
        
        console.log('ECV Extra Fields: All handlers initialized');
    });
})(jQuery);
</script>
<?php
