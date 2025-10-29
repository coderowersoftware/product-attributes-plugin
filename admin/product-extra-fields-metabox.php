<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$extra_fields = ecv_get_product_extra_fields($post->ID);

// Add empty field if none exist
if (empty($extra_fields)) {
    $extra_fields = array(
        array(
            'field_key' => '',
            'field_type' => 'image',
            'field_value' => ''
        )
    );
}
?>

<div id="ecv-product-extra-fields-panel">
    <div class="ecv-extra-fields-info" style="background: #f0f8ff; padding: 15px; border-left: 4px solid #0073aa; margin-bottom: 20px;">
        <h4 style="margin: 0 0 10px 0;">📋 Product-Level Extra Fields</h4>
        <p style="margin: 0 0 10px 0;">Add custom fields (images, text, PDFs) for this product. Each field gets a unique shortcode that you can use in Elementor.</p>
        <p style="margin: 0; font-size: 12px; color: #666;">
            <strong>Usage:</strong> After adding a field, use its shortcode like <code>[ecv_field key="banner_image"]</code> in Elementor containers.
        </p>
    </div>

    <div id="ecv-extra-fields-container">
        <?php if (!empty($extra_fields)) : ?>
            <?php foreach ($extra_fields as $index => $field) : ?>
                <?php ecv_render_extra_field_row($index, $field); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <button type="button" class="button button-secondary" id="ecv-add-extra-field" style="margin-top: 10px;">
        <span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span> Add Extra Field
    </button>

    <input type="hidden" name="ecv_product_extra_fields_data" id="ecv_product_extra_fields_data" value="">
</div>

<script type="text/html" id="ecv-extra-field-row-template">
    <?php ecv_render_extra_field_row('{{INDEX}}', null); ?>
</script>

<script>
// Inline script to avoid conflicts with other plugins
(function() {
    if (typeof jQuery === 'undefined') {
        console.error('ECV: jQuery not loaded!');
        return;
    }
    
    jQuery(document).ready(function($) {
        console.log('ECV: Inline script loaded');
        
        var fieldIndex = $('#ecv-extra-fields-container .ecv-extra-field-row').length;
        console.log('ECV: Initial field index:', fieldIndex);
        
        // Add new field
        $(document).on('click', '#ecv-add-extra-field', function(e) {
            e.preventDefault();
            console.log('ECV: Add button clicked!');
            
            var template = $('#ecv-extra-field-row-template').html();
            if (!template) {
                alert('Error: Template not found. Please refresh the page.');
                console.error('ECV: Template not found!');
                return;
            }
            
            template = template.replace(/\{\{INDEX\}\}/g, fieldIndex);
            $('#ecv-extra-fields-container').append(template);
            fieldIndex++;
            updateFieldNumbers();
            console.log('ECV: Field added');
        });
        
        // Remove field
        $(document).on('click', '.ecv-remove-field-btn', function(e) {
            e.preventDefault();
            if (confirm('Remove this field?')) {
                $(this).closest('.ecv-extra-field-row').remove();
                updateFieldNumbers();
            }
        });
        
        // Field type change
        $(document).on('change', '.ecv-field-type', function() {
            var $row = $(this).closest('.ecv-extra-field-row');
            var fieldType = $(this).val();
            
            if (fieldType === 'text') {
                $row.find('.ecv-field-media-input').hide();
                $row.find('.ecv-field-text-input').show();
            } else {
                $row.find('.ecv-field-text-input').hide();
                $row.find('.ecv-field-media-input').show();
            }
        });
        
        // Field key change
        $(document).on('input', '.ecv-field-key', function() {
            var $row = $(this).closest('.ecv-extra-field-row');
            var fieldKey = $(this).val().trim();
            
            if (fieldKey) {
                if ($row.find('.ecv-shortcode-display').length) {
                    $row.find('.ecv-shortcode-display code').text('[ecv_field key="' + fieldKey + '"]');
                } else {
                    $row.find('.ecv-extra-field-value-container').append(
                        '<div class="ecv-shortcode-display"><strong>Shortcode:</strong> <code>[ecv_field key="' + fieldKey + '"]</code></div>'
                    );
                }
            }
        });
        
        // Media uploader
        $(document).on('click', '.ecv-upload-media', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $row = $button.closest('.ecv-extra-field-row');
            var $input = $row.find('.ecv-media-url');
            var fieldType = $row.find('.ecv-field-type').val();
            
            if (typeof wp !== 'undefined' && wp.media) {
                var frame = wp.media({
                    title: 'Select ' + fieldType,
                    button: { text: 'Use this file' },
                    library: { type: fieldType === 'image' ? 'image' : 'application/pdf' },
                    multiple: false
                });
                
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $input.val(attachment.url);
                    
                    if (fieldType === 'image') {
                        $row.find('.ecv-field-image-preview').remove();
                        $button.after('<div class="ecv-field-image-preview"><img src="' + attachment.url + '" alt="Preview"></div>');
                    }
                });
                
                frame.open();
            } else {
                alert('Media uploader not available. Please enter URL manually.');
            }
        });
        
        // Update field numbers
        function updateFieldNumbers() {
            $('#ecv-extra-fields-container .ecv-extra-field-row').each(function(index) {
                $(this).find('.ecv-extra-field-title').text('Extra Field #' + (index + 1));
                $(this).attr('data-index', index);
            });
        }
        
        // Save on form submit
        $('form#post').on('submit', function() {
            var fieldsData = [];
            
            $('#ecv-extra-fields-container .ecv-extra-field-row').each(function() {
                var $row = $(this);
                var fieldKey = $row.find('.ecv-field-key').val().trim();
                var fieldType = $row.find('.ecv-field-type').val();
                var fieldValue = '';
                
                if (fieldType === 'text') {
                    fieldValue = $row.find('.ecv-text-value').val();
                } else {
                    fieldValue = $row.find('.ecv-media-url').val();
                }
                
                if (fieldKey && fieldValue) {
                    fieldsData.push({
                        field_key: fieldKey,
                        field_type: fieldType,
                        field_value: fieldValue
                    });
                }
            });
            
            $('#ecv_product_extra_fields_data').val(JSON.stringify(fieldsData));
            console.log('ECV: Saving fields:', fieldsData);
        });
        
        console.log('ECV: All handlers registered');
    });
})();
</script>

<style>
.ecv-extra-field-row {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 15px;
    position: relative;
}

.ecv-extra-field-row:hover {
    border-color: #0073aa;
}

.ecv-extra-field-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f0f0f0;
}

.ecv-extra-field-title {
    font-weight: 600;
    font-size: 14px;
    color: #2271b1;
}

.ecv-extra-field-body {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.ecv-extra-field-group {
    display: flex;
    flex-direction: column;
}

.ecv-extra-field-group label {
    font-weight: 600;
    margin-bottom: 5px;
    font-size: 13px;
    color: #333;
}

.ecv-extra-field-group input[type="text"],
.ecv-extra-field-group select,
.ecv-extra-field-group textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.ecv-extra-field-group textarea {
    min-height: 80px;
    resize: vertical;
}

.ecv-extra-field-value-container {
    grid-column: 1 / -1;
}

.ecv-field-image-preview {
    margin-top: 10px;
}

.ecv-field-image-preview img {
    max-width: 200px;
    height: auto;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.ecv-upload-button {
    margin-top: 5px;
}

.ecv-remove-field-btn {
    background: #dc3232;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 12px;
}

.ecv-remove-field-btn:hover {
    background: #a00;
}

.ecv-shortcode-display {
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    padding: 8px 12px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 12px;
    color: #0073aa;
    margin-top: 10px;
    display: inline-block;
}

.ecv-shortcode-display code {
    background: none;
    padding: 0;
}
</style>

<?php
function ecv_render_extra_field_row($index, $field) {
    $field_key = isset($field['field_key']) ? esc_attr($field['field_key']) : '';
    $field_type = isset($field['field_type']) ? esc_attr($field['field_type']) : 'text';
    $field_value = isset($field['field_value']) ? $field['field_value'] : '';
    $is_template = strpos($index, '{{') !== false;
    ?>
    <div class="ecv-extra-field-row" data-index="<?php echo esc_attr($index); ?>">
        <div class="ecv-extra-field-header">
            <span class="ecv-extra-field-title">Extra Field #<?php echo esc_html($index + 1); ?></span>
            <button type="button" class="ecv-remove-field-btn">
                <span class="dashicons dashicons-trash" style="font-size: 14px; vertical-align: middle;"></span>
                Remove
            </button>
        </div>
        
        <div class="ecv-extra-field-body">
            <div class="ecv-extra-field-group">
                <label>Field Key (Unique ID) <span style="color: red;">*</span></label>
                <input type="text" 
                       class="ecv-field-key" 
                       placeholder="e.g., banner_image, specs_pdf, feature_text"
                       value="<?php echo $field_key; ?>"
                       <?php echo $is_template ? '' : 'required'; ?>>
                <small style="color: #666; margin-top: 5px; display: block;">
                    Use lowercase, no spaces (use underscores). This will be used in your shortcode.
                </small>
            </div>

            <div class="ecv-extra-field-group">
                <label>Field Type <span style="color: red;">*</span></label>
                <select class="ecv-field-type" <?php echo $is_template ? '' : 'required'; ?>>
                    <option value="image" <?php selected($field_type, 'image'); ?>>Image</option>
                    <option value="text" <?php selected($field_type, 'text'); ?>>Text</option>
                    <option value="pdf" <?php selected($field_type, 'pdf'); ?>>PDF</option>
                </select>
            </div>

            <div class="ecv-extra-field-value-container ecv-extra-field-group">
                <label>Field Value <span style="color: red;">*</span></label>
                
                <!-- For Image/PDF types -->
                <div class="ecv-field-media-input" style="<?php echo in_array($field_type, ['image', 'pdf']) ? '' : 'display:none;'; ?>">
                    <input type="text" 
                           class="ecv-field-value ecv-media-url" 
                           placeholder="Upload or enter URL"
                           value="<?php echo esc_attr($field_value); ?>"
                           <?php echo $is_template ? '' : 'required'; ?>>
                    <button type="button" class="button ecv-upload-button ecv-upload-media">
                        <span class="dashicons dashicons-upload" style="vertical-align: middle;"></span>
                        Upload <?php echo ucfirst($field_type); ?>
                    </button>
                    <?php if ($field_type === 'image' && !empty($field_value)) : ?>
                        <div class="ecv-field-image-preview">
                            <img src="<?php echo esc_url($field_value); ?>" alt="Preview">
                        </div>
                    <?php endif; ?>
                </div>

                <!-- For Text type -->
                <div class="ecv-field-text-input" style="<?php echo $field_type === 'text' ? '' : 'display:none;'; ?>">
                    <textarea class="ecv-field-value ecv-text-value" 
                              placeholder="Enter your text here..."
                              <?php echo $is_template ? '' : 'required'; ?>><?php echo esc_textarea($field_value); ?></textarea>
                </div>

                <!-- Shortcode display -->
                <?php if (!$is_template && !empty($field_key)) : ?>
                    <div class="ecv-shortcode-display">
                        <strong>Shortcode:</strong> <code>[ecv_field key="<?php echo esc_attr($field_key); ?>"]</code>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
?>
