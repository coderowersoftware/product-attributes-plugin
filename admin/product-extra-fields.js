jQuery(document).ready(function($) {
    'use strict';
    
    console.log('ECV Product Extra Fields: JavaScript loaded');
    console.log('ECV: jQuery version:', $.fn.jquery);
    console.log('ECV: Add button exists:', $('#ecv-add-extra-field').length);

    let fieldIndex = $('#ecv-extra-fields-container .ecv-extra-field-row').length;
    console.log('ECV: Initial field index:', fieldIndex);

    // Add new field
    $('#ecv-add-extra-field').on('click', function() {
        console.log('ECV: Add Field button clicked!');
        let template = $('#ecv-extra-field-row-template').html();
        console.log('ECV: Template HTML length:', template ? template.length : 'NULL');
        if (!template) {
            console.error('ECV: Template not found!');
            alert('Error: Field template not found. Please refresh the page.');
            return;
        }
        template = template.replace(/\{\{INDEX\}\}/g, fieldIndex);
        console.log('ECV: Appending field with index:', fieldIndex);
        $('#ecv-extra-fields-container').append(template);
        fieldIndex++;
        updateFieldNumbers();
        console.log('ECV: Field added successfully');
    });

    // Remove field
    $(document).on('click', '.ecv-remove-field-btn', function() {
        if (confirm('Are you sure you want to remove this field?')) {
            $(this).closest('.ecv-extra-field-row').remove();
            updateFieldNumbers();
        }
    });

    // Handle field type change
    $(document).on('change', '.ecv-field-type', function() {
        let $row = $(this).closest('.ecv-extra-field-row');
        let fieldType = $(this).val();
        
        // Show/hide appropriate input based on type
        if (fieldType === 'text') {
            $row.find('.ecv-field-media-input').hide();
            $row.find('.ecv-field-text-input').show();
            $row.find('.ecv-media-url').removeAttr('required');
            $row.find('.ecv-text-value').attr('required', 'required');
        } else {
            $row.find('.ecv-field-text-input').hide();
            $row.find('.ecv-field-media-input').show();
            $row.find('.ecv-text-value').removeAttr('required');
            $row.find('.ecv-media-url').attr('required', 'required');
        }

        // Update upload button text
        let label = fieldType === 'image' ? 'Image' : (fieldType === 'pdf' ? 'PDF' : 'File');
        $row.find('.ecv-upload-media').html('<span class="dashicons dashicons-upload" style="vertical-align: middle;"></span> Upload ' + label);
    });

    // Handle field key change (update shortcode display)
    $(document).on('input', '.ecv-field-key', function() {
        let $row = $(this).closest('.ecv-extra-field-row');
        let fieldKey = $(this).val().trim();
        
        if (fieldKey) {
            // Update or create shortcode display
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
        
        let $button = $(this);
        let $row = $button.closest('.ecv-extra-field-row');
        let $input = $row.find('.ecv-media-url');
        let fieldType = $row.find('.ecv-field-type').val();
        
        // Create media frame
        let frame = wp.media({
            title: 'Select or Upload ' + (fieldType === 'image' ? 'Image' : 'PDF'),
            button: {
                text: 'Use this file'
            },
            library: {
                type: fieldType === 'image' ? 'image' : 'application/pdf'
            },
            multiple: false
        });

        // When file is selected
        frame.on('select', function() {
            let attachment = frame.state().get('selection').first().toJSON();
            $input.val(attachment.url);
            
            // Show image preview
            if (fieldType === 'image') {
                $row.find('.ecv-field-image-preview').remove();
                $button.after('<div class="ecv-field-image-preview"><img src="' + attachment.url + '" alt="Preview"></div>');
            }
        });

        frame.open();
    });

    // Update field numbers
    function updateFieldNumbers() {
        $('#ecv-extra-fields-container .ecv-extra-field-row').each(function(index) {
            $(this).find('.ecv-extra-field-title').text('Extra Field #' + (index + 1));
            $(this).attr('data-index', index);
        });
    }

    // Collect field data before form submission
    $('form#post').on('submit', function() {
        let fieldsData = [];
        
        $('#ecv-extra-fields-container .ecv-extra-field-row').each(function() {
            let $row = $(this);
            let fieldKey = $row.find('.ecv-field-key').val().trim();
            let fieldType = $row.find('.ecv-field-type').val();
            let fieldValue = '';
            
            if (fieldType === 'text') {
                fieldValue = $row.find('.ecv-text-value').val();
            } else {
                fieldValue = $row.find('.ecv-media-url').val();
            }
            
            // Only add if field key and value exist
            if (fieldKey && fieldValue) {
                fieldsData.push({
                    field_key: fieldKey,
                    field_type: fieldType,
                    field_value: fieldValue
                });
            }
        });
        
        $('#ecv_product_extra_fields_data').val(JSON.stringify(fieldsData));
    });
});
